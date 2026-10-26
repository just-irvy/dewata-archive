<?php

namespace Database\Seeders;

use App\Enums\RegionType;
use App\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Batas administratif Provinsi Bali: delapan kabupaten dan satu kota.
 * Area editorial (misalnya Ubud atau Canggu) sengaja tidak di-seed. Batas dan
 * labelnya ditetapkan tim konten lewat admin agar tidak bercampur dengan
 * klasifikasi administratif (FILE 07, step 3).
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['Badung', 'badung'],
            ['Bangli', 'bangli'],
            ['Buleleng', 'buleleng'],
            ['Gianyar', 'gianyar'],
            ['Jembrana', 'jembrana'],
            ['Karangasem', 'karangasem'],
            ['Klungkung', 'klungkung'],
            ['Tabanan', 'tabanan'],
            ['Kota Denpasar', 'denpasar'],
        ];

        foreach ($regions as [$name, $slug]) {
            Region::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'region_type' => RegionType::Administrative,
                    'is_published' => true,
                ],
            );
        }
    }
}
