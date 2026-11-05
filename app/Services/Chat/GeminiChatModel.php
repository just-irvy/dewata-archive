<?php

namespace App\Services\Chat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adapter REST untuk Gemini generateContent. Sengaja tanpa SDK tambahan: satu endpoint,
 * satu header kunci, mudah di-fake pada tes.
 *
 * Privasi: isi percakapan dan isi respons tidak pernah dicatat ke log. Yang dicatat hanya
 * kode status.
 */
final class GeminiChatModel implements ChatModel
{
    public function generate(string $systemPrompt, array $contents, array $functionDeclarations, bool $allowCalls = true): array
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new ChatException('not_configured');
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => (int) config('services.gemini.max_output_tokens'),
            ],
        ];

        $level = strtoupper((string) config('services.gemini.thinking_level'));
        $model = (string) config('services.gemini.model');

        if (in_array($level, ['MINIMAL', 'LOW', 'MEDIUM', 'HIGH'], true) && str_starts_with($model, 'gemini-') && ! str_starts_with($model, 'gemini-2')) {
            $payload['generationConfig']['thinkingConfig'] = ['thinkingLevel' => $level];
        }

        if ($functionDeclarations !== []) {
            $payload['tools'] = [['functionDeclarations' => $functionDeclarations]];
            $payload['toolConfig'] = ['functionCallingConfig' => ['mode' => $allowCalls ? 'AUTO' : 'NONE']];
        }

        $url = rtrim((string) config('services.gemini.base_url'), '/')
            .'/models/'.config('services.gemini.model').':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout((int) config('services.gemini.timeout'))
                ->post($url, $payload);
        } catch (ConnectionException) {
            Log::warning('chatbot.gemini.connection');
            throw new ChatException('timeout');
        }

        if ($response->status() === 429) {
            Log::warning('chatbot.gemini.quota');
            throw new ChatException('quota', 429);
        }

        if ($response->failed()) {
            Log::warning('chatbot.gemini.failed', ['status' => $response->status()]);
            throw new ChatException('provider_error');
        }

        if ($response->json('promptFeedback.blockReason')) {
            throw new ChatException('blocked', 422);
        }

        $content = $response->json('candidates.0.content');

        if (! is_array($content) || empty($content['parts'])) {
            // Contoh: finishReason SAFETY atau MAX_TOKENS tanpa teks.
            Log::warning('chatbot.gemini.empty', ['finish' => $response->json('candidates.0.finishReason')]);
            throw new ChatException(
                $response->json('candidates.0.finishReason') === 'SAFETY' ? 'blocked' : 'empty',
                $response->json('candidates.0.finishReason') === 'SAFETY' ? 422 : 503,
            );
        }

        $content['role'] = 'model';

        return $content;
    }
}
