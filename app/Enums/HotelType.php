<?php

namespace App\Enums;

enum HotelType: string
{
    case Hotel = 'hotel';
    case Resort = 'resort';
    case Villa = 'villa';
    case Guesthouse = 'guesthouse';
    case Boutique = 'boutique';

    public function label(): string
    {
        return match ($this) {
            self::Hotel => 'Hotel',
            self::Resort => 'Resort',
            self::Villa => 'Villa',
            self::Guesthouse => 'Guesthouse',
            self::Boutique => 'Boutique stay',
        };
    }
}
