<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Log;

/**
 * Satu pertanyaan masuk, satu jawaban keluar. Mengatur putaran model <-> tool.
 *
 * Tidak menyimpan percakapan. Riwayat dikirim ulang oleh browser pada setiap permintaan
 * dan dipotong server (lihat ChatRequest). Dengan begitu tidak ada tabel chat yang perlu
 * kebijakan retensi.
 */
final class ChatOrchestrator
{
    public function __construct(
        private readonly ChatModel $model,
        private readonly ChatTools $tools,
    ) {}

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     * @return array{answer: string, cards: array<int, array<string, mixed>>, notes: array<int, string>}
     *
     * @throws ChatException
     */
    public function ask(string $message, array $history = []): array
    {
        $contents = [];
        foreach ($history as $turn) {
            $contents[] = ['role' => $turn['role'], 'parts' => [['text' => $turn['text']]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $started = microtime(true);
        $system = ChatPrompt::system($this->tools->catalogOptions());
        $toolNames = [];
        $declarations = $this->tools->declarations();
        $cards = [];
        $allowedPrices = [];
        $answer = null;

        for ($round = 0; $round <= (int) config('chatbot.max_tool_rounds'); $round++) {
            // Anggaran waktu habis: berhenti memakai tool dan minta jawaban akhir dari data yang ada.
            if ($round > 0 && (microtime(true) - $started) > (int) config('chatbot.time_budget_seconds')) {
                break;
            }

            $content = $this->model->generate($system, $contents, $declarations);
            $calls = array_values(array_filter($content['parts'], fn ($part) => isset($part['functionCall'])));

            if ($calls === []) {
                $answer = $this->textOf($content);
                break;
            }

            // Content model dikembalikan apa adanya (termasuk thoughtSignature bila ada).
            $contents[] = $content;
            $responses = [];

            foreach ($calls as $part) {
                $call = $part['functionCall'];
                $toolNames[] = (string) ($call['name'] ?? '');
                $args = is_array($call['args'] ?? null) ? $call['args'] : [];
                $result = $this->tools->run((string) ($call['name'] ?? ''), $args);

                foreach (['price_min', 'price_max'] as $key) {
                    if (isset($args[$key]) && is_numeric($args[$key])) {
                        $allowedPrices[] = (int) $args[$key];
                    }
                }
                foreach ($this->pricesIn($result['data']) as $price) {
                    $allowedPrices[] = $price;
                }
                foreach ($result['cards'] as $card) {
                    $cards[$card['key']] ??= $card;
                }

                $response = ['name' => $call['name'] ?? '', 'response' => ['result' => $result['data']]];
                if (isset($call['id'])) {
                    $response['id'] = $call['id'];
                }
                $responses[] = ['functionResponse' => $response];
            }

            $contents[] = ['role' => 'user', 'parts' => $responses];
        }

        if ($answer === null) {
            // Batas putaran tool habis: minta jawaban akhir tanpa tool.
            $answer = $this->textOf($this->model->generate($system, $contents, []));
        }

        // Hanya metrik. Isi pertanyaan dan jawaban tidak dicatat (FILE 15).
        Log::info('chatbot.ask', [
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'rounds' => $round,
            'tools' => $toolNames,
        ]);

        if ($answer === '') {
            throw new ChatException('empty');
        }

        // Nominal budget yang diucapkan pengguna di pesan ini juga sah disebut ulang.
        foreach (PriceGuard::mentionedAmounts($message) as $amount) {
            $allowedPrices[] = $amount;
        }

        $notes = [];
        if (PriceGuard::unknownAmounts($answer, $allowedPrices) !== []) {
            $notes[] = 'Ada angka harga pada jawaban yang tidak berasal dari data arsip. Pegang angka pada kartu hotel dan cek ulang di sumbernya.';
        }

        return [
            'answer' => $answer,
            'cards' => array_slice(array_values($cards), 0, 8),
            'notes' => $notes,
        ];
    }

    /** @param  array<string, mixed>  $content */
    private function textOf(array $content): string
    {
        $text = '';

        foreach ($content['parts'] as $part) {
            // Bagian "thought" adalah ringkasan penalaran model dan tidak ditampilkan.
            if (isset($part['text']) && empty($part['thought'])) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function pricesIn(array $data): array
    {
        $prices = [];

        array_walk_recursive($data, function ($value, $key) use (&$prices) {
            if (in_array($key, ['harga_min', 'harga_max', 'min', 'max'], true) && is_int($value)) {
                $prices[] = $value;
            }
        });

        return $prices;
    }
}
