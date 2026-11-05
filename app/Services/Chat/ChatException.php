<?php

namespace App\Services\Chat;

use RuntimeException;

/** Kegagalan chatbot yang aman ditampilkan ke pengguna. Pesan teknis tidak pernah ikut dikirim. */
final class ChatException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status = 503)
    {
        parent::__construct($reason);
    }

    public function userMessage(): string
    {
        return match ($this->reason) {
            'quota' => 'Asisten sedang ramai. Coba lagi beberapa saat lagi. Katalog dan planner tetap bisa dipakai.',
            'blocked' => 'Pertanyaan itu tidak bisa saya jawab. Coba tanyakan tentang tempat wisata atau hotel di Bali.',
            'cap' => 'Asisten sudah mencapai batas pemakaian hari ini. Katalog dan planner tetap bisa dipakai.',
            default => 'Asisten sedang tidak tersedia. Katalog, peta, dan planner tetap berjalan seperti biasa.',
        };
    }
}
