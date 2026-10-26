<?php

namespace App\Enums;

/**
 * Status untuk data yang dapat berubah: jam buka, harga, fasilitas, akses.
 * Nilai unknown berarti data belum diketahui, bukan data kosong yang disembunyikan.
 */
enum VerificationStatus: string
{
    case Verified = 'verified';
    case NeedsReview = 'needs_review';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Terverifikasi',
            self::NeedsReview => 'Perlu ditinjau',
            self::Unknown => 'Belum diketahui',
        };
    }
}
