<?php

namespace App\Support;

use App\Models\Hotel;
use App\Models\Place;
use App\Models\Region;

/**
 * Penanda bergaya registri arsip (desain Stitch): kode berkas dan koordinat derajat-menit-detik.
 * Semuanya diturunkan dari data asli (ID record, wilayah, koordinat), bukan nomor karangan.
 */
final class ArchiveCode
{
    public static function region(?Region $region): string
    {
        if ($region === null) {
            return 'BAL';
        }

        $slug = preg_replace('/^(kota|kabupaten)-/', '', (string) $region->slug);

        return strtoupper(substr(preg_replace('/[^a-z]/', '', $slug) ?: 'bal', 0, 3));
    }

    /** ARC·BAD·004 untuk tempat, PNG·GIA·012 untuk penginapan. */
    public static function for(Place|Hotel $record): string
    {
        $prefix = $record instanceof Hotel ? 'PNG' : 'ARC';

        return $prefix.'·'.self::region($record->region).'·'.str_pad((string) $record->id, 3, '0', STR_PAD_LEFT);
    }

    /** 8°22′09″S 115°07′48″E. Null bila koordinat tidak ada. */
    public static function dms(mixed $lat, mixed $lng): ?string
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $part = function (float $value, string $pos, string $neg): string {
            $abs = abs($value);
            $d = (int) floor($abs);
            $m = (int) floor(($abs - $d) * 60);
            $s = (int) round((($abs - $d) * 60 - $m) * 60);
            if ($s === 60) {
                $s = 0;
                $m++;
            }
            if ($m === 60) {
                $m = 0;
                $d++;
            }

            return sprintf('%d°%02d′%02d″%s', $d, $m, $s, $value < 0 ? $neg : $pos);
        };

        return $part((float) $lat, 'N', 'S').' '.$part((float) $lng, 'E', 'W');
    }
}
