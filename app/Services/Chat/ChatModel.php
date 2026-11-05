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
     * @param  array<int, array<string, mixed>>  $functionDeclarations  Tool yang dikenal model. Kosong berarti tanpa tool.
     * @param  bool  $allowCalls  False memaksa jawaban teks: tool tetap dideklarasikan (riwayat berisi
     *                            functionCall tetap sah) tetapi model tidak boleh memanggilnya lagi.
     * @return array<string, mixed> Satu content milik model: ['role' => 'model', 'parts' => [...]].
     *
     * @throws ChatException
     */
    public function generate(string $systemPrompt, array $contents, array $functionDeclarations, bool $allowCalls = true): array;
}
