<?php

namespace App\Photos;

use App\Imports\DatasetMapper;

/**
 * Mencocokkan nama berkas foto dengan tempat atau hotel.
 *
 *   museum-yadnya.jpg     foto pertama
 *   museum-yadnya-2.jpg   foto kedua
 *   "Museum Yadnya.JPG"   juga cocok, karena nama berkas diubah menjadi slug lebih dulu
 *
 * Tidak menebak. Nama yang cocok dengan lebih dari satu record dilaporkan ambigu.
 */
final class PhotoMatcher
{
    /**
     * @param  array<string, array<int, int>>  $index  slug atau slug nama => daftar id record
     * @return array{status: string, id?: int, order?: int}
     */
    public static function match(string $filename, array $index): array
    {
        foreach (self::candidates($filename) as [$slug, $order]) {
            $ids = array_values(array_unique($index[$slug] ?? []));

            if (count($ids) === 1) {
                return ['status' => 'ok', 'id' => $ids[0], 'order' => $order];
            }
            if (count($ids) > 1) {
                return ['status' => 'ambiguous'];
            }
        }

        return ['status' => 'none'];
    }

    /**
     * Kandidat (slug, urutan). Nama utuh dicoba lebih dulu, baru dengan akhiran angka dilepas.
     * Dengan begitu tempat bernama "Pura 2" tidak salah dibaca sebagai foto kedua "Pura".
     *
     * @return array<int, array{0: string, 1: int}>
     */
    public static function candidates(string $filename): array
    {
        $base = DatasetMapper::slug(pathinfo($filename, PATHINFO_FILENAME));
        $candidates = [[$base, 1]];

        if (preg_match('/^(.+)-(\d{1,2})$/', $base, $m)) {
            $candidates[] = [$m[1], max(1, (int) $m[2])];
        }

        return $candidates;
    }
}
