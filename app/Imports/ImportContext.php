<?php

namespace App\Imports;

/** Fakta dari database yang dibutuhkan pemeta baris. Dipisah agar pemeta tidak bergantung pada framework. */
final class ImportContext
{
    /**
     * @param  array<int, string>  $regionSlugs  slug wilayah yang sudah ada dan terbit
     * @param  array<int, string>  $existingExternalIds  external_id yang sudah tersimpan
     * @param  array<int, string>  $existingPlaceSlugs
     * @param  array<int, string>  $existingHotelSlugs
     */
    public function __construct(
        public readonly array $regionSlugs = [],
        public readonly array $existingExternalIds = [],
        public readonly array $existingPlaceSlugs = [],
        public readonly array $existingHotelSlugs = [],
    ) {
    }
}
