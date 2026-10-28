<?php

namespace App\Imports;

/**
 * Memetakan baris mentah dataset ke bentuk yang cocok dengan skema arsip dan menilai kualitasnya.
 * Tidak menyentuh database atau framework, sehingga bisa dites sendiri dan dijalankan di CLI.
 *
 * Prinsip: tidak menebak. Nilai yang tidak dikenal menjadi error atau peringatan, bukan nilai bawaan.
 *
 * Dua bentuk berkas didukung. Format v1 memakai location_area, price_per_night, vibe_category, dan
 * historical_value. Format v2 memakai kabupaten, price_min, price_max, price_source, theme, hotel_type,
 * source_url, source_date, dan summary. Kolom v2 yang kosong tidak pernah diisi dengan tebakan.
 */
final class DatasetMapper
{
    /** Kolom minimum. Selain ini harus ada kolom `kabupaten` atau `location_area`. */
    public const REQUIRED_COLUMNS = ['id', 'name', 'category', 'latitude', 'longitude'];

    public const REGION_COLUMNS = ['kabupaten', 'location_area'];

    /** @var array<string, bool> */
    private array $usedPlaceSlugs = [];

    /** @var array<string, bool> */
    private array $usedHotelSlugs = [];

    /** @param  array<string, mixed>  $config  isi config('archive.import') */
    public function __construct(
        private readonly array $config,
        private readonly ImportContext $context,
        private readonly array $bounds = ['lat' => [-8.95, -8.0], 'lng' => [114.4, 115.8]],
    ) {
        $this->usedPlaceSlugs = array_fill_keys($context->existingPlaceSlugs, true);
        $this->usedHotelSlugs = array_fill_keys($context->existingHotelSlugs, true);
    }

    public static function slug(string $value): string
    {
        $value = str_replace('&', ' and ', $value);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = strtolower($ascii !== false && $ascii !== '' ? $ascii : $value);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    }

    /**
     * @param  array<int, array<string, string>>  $rows  baris mentah, kunci = nama kolom
     * @return array{rows: array<int, MappedRow>, notes: array<int, string>}
     */
    public function mapAll(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $index => $raw) {
            $mapped[] = $this->mapRow($raw, $index + 2);   // baris 1 adalah header
        }

        $this->checkBatch($mapped);

        return ['rows' => $mapped, 'notes' => $this->notes($mapped, $rows)];
    }

    /** @param  array<string, string>  $raw */
    public function mapRow(array $raw, int $rowNumber): MappedRow
    {
        $raw = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $raw);
        $category = (string) ($raw['category'] ?? '');
        $isHotel = in_array($category, $this->config['hotel_categories'], true);
        $isPlace = isset($this->config['place_categories'][$category]);
        $entity = $isHotel ? 'hotel' : ($isPlace ? 'place' : null);

        $id = (string) ($raw['id'] ?? '');
        $externalId = $id !== '' ? "{$this->config['source']}:{$id}" : null;

        $row = new MappedRow($rowNumber, $entity, $externalId, []);

        if ($id === '') {
            $row->add('error', 'missing_id', 'Kolom id kosong. Id dibutuhkan agar impor bisa diulang dengan aman.');
        }
        if ($entity === null) {
            $row->add('error', 'unknown_category', "Kategori \"{$category}\" tidak dikenal.");
        }

        $name = (string) ($raw['name'] ?? '');
        if ($name === '') {
            $row->add('error', 'missing_name', 'Nama kosong.');
        }

        [$regionSlug, $areaLabel] = $this->region($raw, $row);
        [$lat, $lng] = $this->coordinates($raw, $row);

        // Ringkasan. Kosong diperbolehkan (tidak dikarang), tetapi tidak bisa terbit sebelum ditulis editor.
        $summary = (string) ($raw['summary'] ?? $raw['historical_value'] ?? '');
        $limit = $entity === 'hotel' ? 500 : 300;
        if ($summary === '') {
            $row->add('warning', 'no_summary', 'Ringkasan kosong. Wajib ditulis editor sebelum terbit.');
        } elseif (mb_strlen($summary) > $limit) {
            $row->add('error', 'summary_too_long', "Ringkasan melebihi {$limit} karakter.");
        } elseif ($this->isPromotional($summary)) {
            $row->add('warning', 'promo_language', 'Teks bernada promosi. Tulis ulang secara spesifik dan dapat ditelusuri sebelum terbit.');
        }

        $payload = [
            'entity' => $entity,
            'external_id' => $externalId,
            'name' => $name,
            'region_slug' => $regionSlug,
            'area_label' => $areaLabel,
            'summary' => $summary,
            'lat' => $lat,
            'lng' => $lng,
            'source_url' => $this->sourceUrl($raw, $row),
        ];

        $verification = $this->verification($raw, $row);
        $payload['verified_at'] = $verification['verified_at'];

        if ($entity === 'place') {
            $payload['verification_status'] = $verification['verification_status'];
            $payload['category'] = $this->config['place_categories'][$category] ?? null;
            $payload['tag'] = $this->tag($raw, $row);
            $payload['source_updated_at'] = $this->sourceDate($raw, $row);
            $payload['price_ignored'] = $this->priceOnPlace($raw, $row);
        }

        if ($entity === 'hotel') {
            $payload += $this->hotelPrice($raw, $row);
            $payload['hotel_type'] = $this->hotelType($raw, $row);
        }

        if ($externalId !== null && in_array($externalId, $this->context->existingExternalIds, true)) {
            $row->action = 'skip';
            $row->add('warning', 'already_imported', 'Sudah pernah diimpor. Baris dilewati dan data yang ada tidak ditimpa.');
        }

        $payload['slug'] = $entity !== null && $name !== '' ? $this->uniqueSlug($entity, $name, $areaLabel ?? $regionSlug) : null;

        return new MappedRow($rowNumber, $entity, $externalId, $payload, $row->issues, $row->action);
    }

    /** @return array{0: ?string, 1: ?string} slug wilayah dan label area */
    private function region(array $raw, MappedRow $row): array
    {
        $kabupaten = (string) ($raw['kabupaten'] ?? '');
        $area = (string) ($raw['location_area'] ?? '');

        if ($kabupaten !== '') {
            $slug = $this->config['kabupaten_regions'][$kabupaten] ?? null;
            if ($slug === null) {
                $row->add('error', 'unknown_kabupaten', "Kabupaten \"{$kabupaten}\" tidak dikenal.");
            }
        } else {
            $slug = $this->config['area_regions'][$area] ?? null;
            if ($slug === null) {
                $row->add('error', 'unknown_area', "Area \"{$area}\" belum dipetakan ke wilayah administratif.");
            }
        }

        if ($slug !== null && ! in_array($slug, $this->context->regionSlugs, true)) {
            $row->add('error', 'region_missing', "Wilayah \"{$slug}\" belum ada atau belum terbit. Jalankan RegionSeeder.");
        }

        // Label area hanya disimpan bila berbeda dari wilayahnya (Ubud di Gianyar), bukan bila sama (Gianyar di Gianyar).
        $label = ($slug !== null && $area !== '' && self::slug($area) !== $slug) ? $area : null;

        return [$slug, $label];
    }

    /** @return array{0: ?float, 1: ?float} */
    private function coordinates(array $raw, MappedRow $row): array
    {
        $lat = $raw['latitude'] ?? '';
        $lng = $raw['longitude'] ?? '';

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            $row->add('error', 'bad_coordinates', 'Koordinat kosong atau bukan angka.');

            return [null, null];
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            $row->add('error', 'bad_coordinates', 'Koordinat di luar rentang valid.');

            return [null, null];
        }

        [$latMin, $latMax] = $this->bounds['lat'];
        [$lngMin, $lngMax] = $this->bounds['lng'];
        if ($lat < $latMin || $lat > $latMax || $lng < $lngMin || $lng > $lngMax) {
            $row->add('warning', 'outside_bali', 'Koordinat di luar kotak batas Bali. Periksa kembali.');
        }

        return [$lat, $lng];
    }

    /** @return array<string, mixed>|null */
    private function tag(array $raw, MappedRow $row): ?array
    {
        $vibe = (string) ($raw['theme'] ?? $raw['vibe_category'] ?? '');

        if ($vibe === '') {
            return null;
        }
        if (! array_key_exists($vibe, $this->config['vibe_tags'])) {
            $row->add('warning', 'unknown_vibe', "Tema \"{$vibe}\" tidak dikenal. Tag tidak dipasang.");

            return null;
        }
        if ($this->config['vibe_tags'][$vibe] === null) {
            $row->add('warning', 'vibe_ignored', "Tema \"{$vibe}\" sengaja tidak dipasang karena bukan tema. Tentukan tema yang tepat.");

            return null;
        }

        return $this->config['vibe_tags'][$vibe];
    }

    private function sourceUrl(array $raw, MappedRow $row): ?string
    {
        $url = (string) ($raw['source_url'] ?? '');

        if ($url === '') {
            return null;
        }
        if (strlen($url) > 2048 || ! preg_match('#^https?://[^\s]+$#i', $url)) {
            $row->add('warning', 'bad_source_url', 'Tautan sumber bukan URL http(s) yang valid dan diabaikan.');

            return null;
        }

        if (BoilerplateDetector::isSearchLink($url)) {
            $row->add('warning', 'search_link_not_source', 'Tautan sumber adalah halaman hasil pencarian, bukan sumber informasi, dan diabaikan.');

            return null;
        }

        return $url;
    }

    /**
     * Tanggal dan status verifikasi hanya boleh datang dari berkas bila seseorang benar-benar memeriksa
     * sumber. Status "verified" atau "needs_review" tanpa tanggal tidak diterima, dan tanggal tanpa status
     * dicatat sebagai "needs_review" (sudah diperiksa terhadap sumber, belum ditinjau editor).
     *
     * @return array{verified_at: ?string, verification_status: string}
     */
    private function verification(array $raw, MappedRow $row): array
    {
        $date = (string) ($raw['verified_at'] ?? '');
        $status = strtolower(trim((string) ($raw['verification_status'] ?? '')));

        $verifiedAt = null;
        if ($date !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                $row->add('warning', 'bad_verified_at', 'Tanggal verifikasi tidak berformat YYYY-MM-DD dan diabaikan.');
            } elseif ($parsed > new \DateTimeImmutable('today')) {
                $row->add('warning', 'future_verified_at', 'Tanggal verifikasi ada di masa depan dan diabaikan.');
            } else {
                $verifiedAt = $date;
            }
        }

        if ($status !== '' && ! in_array($status, ['verified', 'needs_review', 'unknown'], true)) {
            $row->add('warning', 'bad_verification_status', "Status verifikasi \"{$status}\" tidak dikenal dan diabaikan.");
            $status = '';
        }

        // Impor paling jauh menyatakan "Perlu ditinjau". Status "Terverifikasi" tampil ke publik sebagai klaim
        // bahwa seseorang memeriksa tempat itu, jadi hanya boleh ditetapkan editor di editor tempat.
        if ($status === 'verified') {
            $row->add('warning', 'verified_downgraded', 'Status "Terverifikasi" dari berkas diturunkan menjadi "Perlu ditinjau". Editor menetapkan status itu di editor tempat setelah memeriksa sumbernya.');
            $status = 'needs_review';
        }

        if ($status === '') {
            $status = $verifiedAt !== null ? 'needs_review' : 'unknown';
        }

        if ($verifiedAt === null && $status !== 'unknown') {
            $row->add('warning', 'status_without_date', 'Status verifikasi tanpa tanggal yang valid diturunkan menjadi "Belum diketahui".');
            $status = 'unknown';
        }

        return ['verified_at' => $verifiedAt, 'verification_status' => $status];
    }

    private function sourceDate(array $raw, MappedRow $row): ?string
    {
        $date = (string) ($raw['source_date'] ?? '');

        if ($date === '') {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            $row->add('warning', 'bad_source_date', 'Tanggal sumber tidak berformat YYYY-MM-DD dan diabaikan.');

            return null;
        }

        return $date;
    }

    /** Harga pada tempat non-hotel bernilai 0 atau kosong. Itu berarti "tidak ada data", bukan gratis. */
    private function priceOnPlace(array $raw, MappedRow $row): bool
    {
        $price = $raw['price_per_night'] ?? $raw['price_min'] ?? '';
        if (is_numeric($price) && (float) $price > 0) {
            $row->add('warning', 'price_on_place', 'Harga pada tempat non-hotel diabaikan. Isi informasi tiket di editor bila ada sumbernya.');

            return true;
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function hotelPrice(array $raw, MappedRow $row): array
    {
        $min = $raw['price_min'] ?? $raw['price_per_night'] ?? '';
        $max = $raw['price_max'] ?? '';

        if (! is_numeric($min) || (float) $min <= 0) {
            $row->add('warning', 'no_price', 'Harga hotel kosong atau 0. Disimpan sebagai "tidak tersedia".');

            return ['price_min' => null, 'price_max' => null, 'price_basis' => null];
        }

        $min = (int) round((float) $min);
        $max = is_numeric($max) && (float) $max > 0 ? (int) round((float) $max) : null;

        if ($max !== null && $max < $min) {
            $row->add('warning', 'price_range_inverted', 'Harga tertinggi lebih kecil dari harga terendah dan diabaikan.');
            $max = null;
        }

        // Sumber dikenal menentukan jenis sumber. Tanpa itu, harga dicatat sebagai estimasi.
        $source = (string) ($raw['price_source'] ?? '');
        $basis = $this->config['price_sources'][$source] ?? 'estimate';

        if ($source !== '' && ! isset($this->config['price_sources'][$source])) {
            $row->add('warning', 'unknown_price_source', "Sumber harga \"{$source}\" tidak dikenal. Dicatat sebagai estimasi.");
        }

        // Tidak ada tanggal harga pada dataset mana pun, jadi status verifikasi harga selalu "Belum diketahui".
        return ['price_min' => $min, 'price_max' => $max, 'price_basis' => $basis];
    }

    private function hotelType(array $raw, MappedRow $row): string
    {
        $type = (string) ($raw['hotel_type'] ?? '');

        if ($type === '') {
            return 'hotel';
        }
        if (! in_array($type, $this->config['hotel_types'], true)) {
            $row->add('warning', 'unknown_hotel_type', "Jenis akomodasi \"{$type}\" tidak dikenal. Dicatat sebagai hotel.");

            return 'hotel';
        }

        return $type;
    }

    private function isPromotional(string $text): bool
    {
        $lower = mb_strtolower($text);
        foreach ($this->config['promo_words'] as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $lower)) {
                return true;
            }
        }

        return false;
    }

    /** Petunjuk bisa null pada baris error (area atau kabupaten tidak dikenal). Slug tetap harus terbentuk. */
    private function uniqueSlug(string $entity, string $name, ?string $hint): string
    {
        $base = self::slug($name);
        $used = &$this->{$entity === 'hotel' ? 'usedHotelSlugs' : 'usedPlaceSlugs'};

        $candidate = $base;
        if (isset($used[$candidate]) && $hint !== null && self::slug($hint) !== '') {
            $candidate = $base.'-'.self::slug($hint);
        }
        for ($i = 2; isset($used[$candidate]); $i++) {
            $candidate = $base.'-'.$i;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    /** @param  array<int, MappedRow>  $rows */
    private function checkBatch(array $rows): void
    {
        $byId = [];
        $byCoords = [];

        foreach ($rows as $row) {
            if ($row->externalId !== null) {
                $byId[$row->externalId][] = $row;
            }
            if (isset($row->payload['lat'], $row->payload['lng'])) {
                $byCoords[$row->payload['lat'].','.$row->payload['lng']][] = $row;
            }
        }

        foreach ($byId as $group) {
            foreach (array_slice($group, 1) as $dup) {
                $dup->add('error', 'duplicate_id', 'Id sama dengan baris '.$group[0]->rowNumber.'.');
            }
        }

        $this->blankBoilerplateSummaries($rows);

        foreach ($byCoords as $group) {
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $row) {
                $others = array_map(
                    fn (MappedRow $o) => $o->payload['name'].' (baris '.$o->rowNumber.')',
                    array_values(array_filter($group, fn (MappedRow $o) => $o !== $row)),
                );
                $row->add('warning', 'shared_coordinates', 'Koordinat identik dengan '.implode(', ', $others).'. Bisa properti yang sama atau koordinat perkiraan. Periksa.');
            }
        }
    }

    /** @param  array<int, MappedRow>  $rows */
    private function blankBoilerplateSummaries(array $rows): void
    {
        $records = [];
        foreach ($rows as $i => $row) {
            if (($row->payload['summary'] ?? '') !== '') {
                $records[$i] = ['name' => (string) ($row->payload['name'] ?? ''), 'summary' => $row->payload['summary']];
            }
        }

        $flagged = BoilerplateDetector::flagged($records);

        if ($flagged === []) {
            return;
        }

        foreach ($records as $i => $record) {
            if (BoilerplateDetector::isBoilerplate($record['summary'], $record['name'], $flagged)) {
                $rows[$i]->payload['summary'] = '';
                $rows[$i]->add('warning', 'boilerplate_summary', 'Ringkasan dikosongkan: ada kalimat yang identik di banyak baris, sehingga tampak templat dan bukan hasil riset per tempat. Tulis ringkasan khusus untuk tempat ini.');
            }
        }
    }

    /**
     * Catatan tingkat dataset yang tidak pantas diulang di tiap baris.
     *
     * @param  array<int, MappedRow>  $mapped
     * @param  array<int, array<string, string>>  $raw
     * @return array<int, string>
     */
    private function notes(array $mapped, array $raw): array
    {
        $notes = [];
        $total = count($raw);

        $short = 0;
        foreach ($raw as $r) {
            $lat = (string) ($r['latitude'] ?? '');
            $lng = (string) ($r['longitude'] ?? '');
            if (strlen(substr(strrchr('.'.$lat, '.'), 1)) <= 4 && strlen(substr(strrchr('.'.$lng, '.'), 1)) <= 4) {
                $short++;
            }
        }
        if ($total > 0 && $short / $total > 0.8) {
            $notes[] = "{$short} dari {$total} koordinat hanya 4 desimal (sekitar 11 meter) dan banyak yang berupa angka bulat. Kemungkinan perkiraan, bukan hasil geocoding. Periksa sebelum dipakai untuk rute.";
        }

        $hotels = array_filter($mapped, fn (MappedRow $r) => $r->entity === 'hotel');
        if ($hotels !== []) {
            $provider = count(array_filter($hotels, fn (MappedRow $r) => ($r->payload['price_basis'] ?? null) === 'provider'));
            $estimate = count(array_filter($hotels, fn (MappedRow $r) => ($r->payload['price_basis'] ?? null) === 'estimate'));
            $notes[] = count($hotels).' hotel masuk. Tema (vibe atau theme) tidak dipakai pada hotel. Jenis akomodasi dari kolom hotel_type bila ada, selain itu "hotel".';
            $notes[] = "Harga hotel: {$provider} berjenis data penyedia dan {$estimate} berjenis estimasi. Tidak ada tanggal harga pada dataset, jadi semuanya berstatus verifikasi \"Belum diketahui\" dan hotel tidak bisa terbit sebelum diverifikasi.";
        }

        $withUrl = count(array_filter($mapped, fn (MappedRow $r) => ! empty($r->payload['source_url'])));
        $verified = count(array_filter($mapped, fn (MappedRow $r) => ! empty($r->payload['verified_at'])));

        $hasSourceColumn = $total > 0 && array_key_exists('source_url', $raw[0]);

        $parts = [];
        $parts[] = ! $hasSourceColumn
            ? 'Tidak ada kolom sumber'
            : ($withUrl === 0
                ? 'Tidak ada tautan sumber'
                : "{$withUrl} dari {$total} baris punya tautan sumber, sisanya belum");
        $parts[] = $verified === 0
            ? 'tidak ada tanggal verifikasi'
            : "{$verified} baris punya tanggal verifikasi (status \"Perlu ditinjau\" kecuali ditandai lain), sisanya belum";
        $notes[] = ucfirst(implode('. ', $parts)).'. Tidak ada alamat atau lisensi pada dataset. Semua baris masuk sebagai draf dan tidak ada yang terbit otomatis.';

        $templated = count(array_filter($mapped, fn (MappedRow $r) => in_array('boilerplate_summary', array_column($r->issues, 'code'), true)));
        if ($templated > 0) {
            $notes[] = "{$templated} ringkasan dikosongkan karena kalimat yang sama muncul di banyak baris. Itu ciri teks templat, dan ringkasan templat bisa menyatakan hal yang keliru (misalnya \"pengalaman menginap\" pada kafe atau pantai).";
        }

        $noSummary = count(array_filter($mapped, fn (MappedRow $r) => ($r->payload['summary'] ?? '') === ''));
        if ($noSummary > 0) {
            $notes[] = "{$noSummary} baris tidak punya ringkasan. Ringkasan sengaja dibiarkan kosong, bukan dikarang, dan wajib ditulis editor sebelum terbit.";
        }

        return $notes;
    }
}
