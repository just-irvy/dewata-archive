<?php

namespace App\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/** Menangkap nilai sebelum dan sesudah dari atribut yang berubah, untuk audit log. */
final class Changes
{
    /**
     * Panggil setelah fill() dan sebelum save().
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function capture(Model $model): array
    {
        $before = [];
        $after = [];

        foreach (array_keys($model->getDirty()) as $key) {
            $before[$key] = $model->exists ? self::plain($model->getOriginal($key)) : null;
            $after[$key] = self::plain($model->getAttributeValue($key));
        }

        return [$before, $after];
    }

    public static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default => $value,
        };
    }
}
