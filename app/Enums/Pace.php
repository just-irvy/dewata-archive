<?php

namespace App\Enums;

enum Pace: string
{
    case Relaxed = 'relaxed';
    case Balanced = 'balanced';
    case Packed = 'packed';

    public function label(): string
    {
        return match ($this) {
            self::Relaxed => 'Santai',
            self::Balanced => 'Seimbang',
            self::Packed => 'Padat',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Relaxed => 'Sedikit tempat per hari, banyak jeda.',
            self::Balanced => 'Beberapa tempat dengan jeda yang cukup.',
            self::Packed => 'Lebih banyak tempat, jeda lebih pendek.',
        };
    }
}
