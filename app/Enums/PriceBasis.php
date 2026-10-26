<?php

namespace App\Enums;

/** Jenis sumber harga. Harga selalu per kamar per malam. */
enum PriceBasis: string
{
    case Curated = 'curated';
    case Provider = 'provider';
    case Estimate = 'estimate';

    public function label(): string
    {
        return match ($this) {
            self::Curated => 'Data terkurasi',
            self::Provider => 'Data penyedia',
            self::Estimate => 'Estimasi',
        };
    }
}
