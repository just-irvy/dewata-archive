<?php

namespace App\Support;

/** Menerjemahkan nama input bersarang (hours[mon][open]) ke notasi titik untuk old() dan $errors. */
final class FieldName
{
    public static function dot(string $name): string
    {
        return trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    }

    public static function id(string $name): string
    {
        return str_replace('.', '-', self::dot($name));
    }
}
