<?php

namespace App\Enums;

enum CreationMode: string
{
    case Recommended = 'recommended';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Recommended => 'Disusun dari rekomendasi',
            self::Manual => 'Disusun manual',
        };
    }
}
