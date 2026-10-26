<?php

namespace App\Enums;

enum TagType: string
{
    case Theme = 'theme';
    case Attribute = 'attribute';
    case Accessibility = 'accessibility';
}
