<?php

namespace App\Enums;

enum TripPlanStatus: string
{
    case Draft = 'draft';
    case Saved = 'saved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Saved => 'Tersimpan',
        };
    }
}
