<?php

namespace App\Enums;

/** Memisahkan batas administratif dari label area editorial. */
enum RegionType: string
{
    case Administrative = 'administrative';
    case Editorial = 'editorial';
}
