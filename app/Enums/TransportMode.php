<?php

namespace App\Enums;

enum TransportMode: string
{
    case Motorbike = 'motorbike';
    case Car = 'car';
    case Driver = 'driver';
    case Public = 'public';
    case Undecided = 'undecided';

    public function label(): string
    {
        return match ($this) {
            self::Motorbike => 'Motor',
            self::Car => 'Mobil sendiri',
            self::Driver => 'Mobil dengan sopir',
            self::Public => 'Transportasi umum',
            self::Undecided => 'Belum ditentukan',
        };
    }
}
