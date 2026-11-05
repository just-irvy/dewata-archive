<?php

namespace App\Services\Chat;

/**
 * Batas antara aplikasi dan penyedia model (FILE 12: logika provider diisolasi agar model bisa diganti).
 * Orkestrator hanya mengenal antarmuka ini, bukan Gemini.
 */
interface ChatModel
{
    /**
     * @param  array<int, array<string, mixed>>  $contents  Riwayat dalam format Gemini (role + parts).
     * @param  array<int, array<string, mixed>>  $functionDeclarations  Tool yang boleh dipanggil. Kosong berarti tanpa tool.
     * @return array<string, mixed>  Satu content milik model: ['role' => 'model', 'parts' => [...]].
     *
     * @throws ChatException
     */
    public function generate(string $systemPrompt, array $contents, array $functionDeclarations): array;
}
