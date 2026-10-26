<?php

namespace App\Enums;

enum TransportMode: string
{
    case Motorbike = 'motorbike';
    case Car = 'car';
    case Driver = 'driver';
    case Public = 'public';
    case Undecided = 'undecided';
}
