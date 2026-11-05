<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\User;

/**
 * Percakapan tersimpan untuk pengguna yang login (satu utas per akun).
 *
 * - Konteks untuk model diambil dari sini, bukan dari browser, sehingga riwayat tidak bisa dipalsukan.
 * - Kartu disimpan sebagai kunci dan dibangun ulang dari data terbit terbaru saat dimuat:
 *   harga lama tidak tampil sebagai data, tempat yang sudah tidak terbit hilang dari kartu.
 * - Jumlah pesan per akun dibatasi, dan pesan lama dihapus oleh archive:purge-chat.
 */
final class ChatHistory
{
    public function __construct(private readonly ChatTools $tools) {}

    /** @return array<int, array{role: string, text: string}> giliran terakhir untuk konteks model */
    public function contextFor(User $user): array
    {
        $limit = (int) config('chatbot.max_history_messages');
        $chars = (int) config('chatbot.max_history_chars');

        $turns = ChatMessage::query()->where('user_id', $user->id)
            ->orderByDesc('id')->limit($limit)->get(['role', 'text'])
            ->reverse()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'text' => mb_substr($m->text, 0, $chars)])
            ->values();

        // Gemini mensyaratkan giliran pertama milik pengguna.
        while ($turns->isNotEmpty() && $turns->first()['role'] !== 'user') {
            $turns->shift();
        }

        return $turns->all();
    }

    /**
     * Pesan untuk ditampilkan widget saat halaman dimuat.
     *
     * @return array<int, array{role: string, text: string, cards: array<int, array<string, mixed>>, notes: array<int, string>, restored: bool}>
     */
    public function forWidget(User $user): array
    {
        $messages = ChatMessage::query()->where('user_id', $user->id)
            ->orderByDesc('id')->limit((int) config('chatbot.widget_messages'))->get()
            ->reverse()->values();

        $keys = $messages->flatMap(fn (ChatMessage $m) => $m->card_keys ?? [])->unique()->values()->all();
        $cards = $this->tools->cardsFor($keys);

        return $messages->map(fn (ChatMessage $m) => [
            'role' => $m->role === 'user' ? 'user' : 'assistant',
            'text' => $m->text,
            'cards' => array_values(array_filter(array_map(fn ($k) => $cards[$k] ?? null, $m->card_keys ?? []))),
            'notes' => $m->notes ?? [],
            'restored' => true,
        ])->all();
    }

    /**
     * @param  array<int, string>  $cardKeys
     * @param  array<int, string>  $notes
     */
    public function record(User $user, string $question, string $answer, array $cardKeys, array $notes): void
    {
        $user->chatMessages()->create(['role' => 'user', 'text' => $question]);
        $user->chatMessages()->create([
            'role' => 'model',
            'text' => $answer,
            'card_keys' => $cardKeys === [] ? null : array_values($cardKeys),
            'notes' => $notes === [] ? null : array_values($notes),
        ]);

        // Simpan paling banyak N pesan terakhir per akun.
        $keep = (int) config('chatbot.max_stored_messages');
        $cutoff = ChatMessage::query()->where('user_id', $user->id)->orderByDesc('id')->skip($keep)->value('id');
        if ($cutoff !== null) {
            ChatMessage::query()->where('user_id', $user->id)->where('id', '<=', $cutoff)->delete();
        }
    }

    public function clear(User $user): int
    {
        return ChatMessage::query()->where('user_id', $user->id)->delete();
    }

    public static function purgeOlderThan(int $days): int
    {
        return ChatMessage::query()->where('created_at', '<', now()->subDays($days))->delete();
    }
}
