<?php

namespace App\Services\Chat;

use App\Content\HotelSearch;
use App\Content\PlaceSearch;
use App\Enums\HotelType;
use App\Models\Category;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tool yang boleh dipanggil model (FILE 17: allowlist + parameter tervalidasi).
 *
 * Aturan keras:
 * - Model tidak pernah mengirim SQL, URL, atau nama berkas. Semua argumen dipetakan ke
 *   filter terstruktur yang sudah ada (PlaceSearch, HotelSearch) setelah divalidasi.
 * - Hanya record berstatus published yang pernah keluar dari kelas ini.
 * - Setiap tool mengembalikan dua hal: `data` untuk model dan `cards` untuk UI. Kartu dibuat
 *   dari database, bukan dari teks model, sehingga harga di kartu selalu data asli.
 */
final class ChatTools
{
    public const MAX_PRICE = 200_000_000;

    /** @return array<int, array<string, mixed>> */
    public function declarations(): array
    {
        return [
            [
                'name' => 'get_catalog_options',
                'description' => 'Daftar nilai filter yang sah: wilayah (slug), kategori, tema, dan jenis hotel. Panggil ini bila ragu slug mana yang benar.',
            ],
            [
                'name' => 'search_places',
                'description' => 'Cari tempat wisata terbit di katalog. Semua parameter opsional. Gunakan slug dari get_catalog_options untuk region, category, dan themes.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Kata kunci bebas pendek, misalnya "air terjun" atau "pura".'],
                        'region' => ['type' => 'string', 'description' => 'Slug wilayah, misalnya "gianyar".'],
                        'category' => ['type' => 'string', 'description' => 'Slug kategori.'],
                        'themes' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Slug tema. Cocok bila tempat memiliki salah satunya.'],
                        'limit' => ['type' => 'integer', 'description' => 'Jumlah hasil, 1 sampai 5.'],
                    ],
                ],
            ],
            [
                'name' => 'get_place_details',
                'description' => 'Ambil detail satu tempat terbit berdasarkan slug yang sebelumnya muncul dari search_places.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['slug' => ['type' => 'string']],
                    'required' => ['slug'],
                ],
            ],
            [
                'name' => 'search_hotels',
                'description' => 'Cari hotel terbit menurut wilayah, jenis, dan kisaran harga PER KAMAR PER MALAM dalam rupiah. Hotel tanpa data harga tidak ikut bila ada filter harga.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'region' => ['type' => 'string', 'description' => 'Slug wilayah.'],
                        'type' => ['type' => 'string', 'enum' => array_column(HotelType::cases(), 'value'), 'description' => 'Jenis akomodasi.'],
                        'price_min' => ['type' => 'integer', 'description' => 'Harga minimum per malam dalam rupiah penuh, misalnya 500000.'],
                        'price_max' => ['type' => 'integer', 'description' => 'Harga maksimum per malam dalam rupiah penuh, misalnya 1000000.'],
                        'limit' => ['type' => 'integer', 'description' => 'Jumlah hasil, 1 sampai 5.'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{data: array<string, mixed>, cards: array<int, array<string, mixed>>}
     */
    public function run(string $name, array $args): array
    {
        try {
            return match ($name) {
                'get_catalog_options' => ['data' => $this->catalogOptions(), 'cards' => []],
                'search_places' => $this->searchPlaces($args),
                'get_place_details' => $this->placeDetails($args),
                'search_hotels' => $this->searchHotels($args),
                default => ['data' => ['error' => 'tool_tidak_dikenal'], 'cards' => []],
            };
        } catch (Throwable $e) {
            // Isi argumen tidak dicatat: bisa memuat teks pengguna.
            Log::warning('chatbot.tool.failed', ['tool' => $name, 'exception' => $e::class]);

            return ['data' => ['error' => 'tool_gagal', 'catatan' => 'Data tidak dapat diambil saat ini. Katakan terus terang kepada pengguna.'], 'cards' => []];
        }
    }

    /** @return array<string, mixed> */
    public function catalogOptions(): array
    {
        return Cache::remember('chatbot.catalog_options', now()->addMinutes(10), fn () => [
            'wilayah' => Region::query()->published()->orderBy('name')->get(['slug', 'name'])->map(fn ($r) => ['slug' => $r->slug, 'nama' => $r->name])->all(),
            'kategori' => Category::query()->published()->orderBy('name')->get(['slug', 'name'])->map(fn ($c) => ['slug' => $c->slug, 'nama' => $c->name])->all(),
            'tema' => Tag::query()->where('tag_type', 'theme')->orderBy('name')->get(['slug', 'name'])->map(fn ($t) => ['slug' => $t->slug, 'nama' => $t->name])->all(),
            'jenis_hotel' => array_map(fn (HotelType $t) => ['value' => $t->value, 'nama' => $t->label()], HotelType::cases()),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function searchPlaces(array $args): array
    {
        $options = $this->catalogOptions();
        $ignored = [];

        $region = $this->knownSlug($args['region'] ?? null, $options['wilayah'], 'region', $ignored);
        $category = $this->knownSlug($args['category'] ?? null, $options['kategori'], 'category', $ignored);

        $themes = [];
        foreach (Arr::wrap($args['themes'] ?? []) as $theme) {
            $slug = $this->knownSlug($theme, $options['tema'], 'themes', $ignored);
            if ($slug !== null) {
                $themes[] = $slug;
            }
        }

        $query = is_string($args['query'] ?? null) ? Str::limit(trim($args['query']), 60, '') : null;
        $limit = $this->limit($args['limit'] ?? null);

        $places = (new PlaceSearch([
            'q' => $query,
            'region' => $region,
            'category' => $category,
            'tags' => array_slice($themes, 0, 5),
        ]))->query()
            ->with('tags')
            ->reorder()
            ->orderByRaw('featured_at is null')
            ->orderByDesc('verified_at')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return [
            'data' => [
                'jumlah' => $places->count(),
                'tempat' => $places->map(fn (Place $p) => $this->placeData($p))->all(),
                'filter_diabaikan' => $ignored,
                'catatan' => $places->isEmpty()
                    ? 'Tidak ada tempat terbit yang cocok. Katakan terus terang dan tawarkan melonggarkan wilayah, tema, atau kata kunci.'
                    : 'Hanya sebut tempat yang ada di daftar ini.',
            ],
            'cards' => $places->map(fn (Place $p) => $this->placeCard($p))->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function placeDetails(array $args): array
    {
        $slug = is_string($args['slug'] ?? null) ? $args['slug'] : '';

        $place = preg_match('/^[a-z0-9-]{1,160}$/', $slug) === 1
            ? Place::query()->published()->with(['region', 'categories', 'tags', 'media'])->where('slug', $slug)->first()
            : null;

        if ($place === null) {
            return ['data' => ['error' => 'tempat_tidak_ditemukan'], 'cards' => []];
        }

        return [
            'data' => ['tempat' => $this->placeData($place) + [
                'uraian' => Str::limit(strip_tags((string) $place->body), 900),
                'jam_buka' => $place->opening_hours_status?->value === 'verified'
                    ? $place->opening_hours
                    : 'Belum terverifikasi. Sarankan pengguna mengecek jam buka sebelum berkunjung.',
                'perkiraan_durasi_kunjungan_menit' => $place->typical_visit_minutes,
            ]],
            'cards' => [$this->placeCard($place)],
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function searchHotels(array $args): array
    {
        $options = $this->catalogOptions();
        $ignored = [];

        $region = $this->knownSlug($args['region'] ?? null, $options['wilayah'], 'region', $ignored);
        $type = HotelType::tryFrom((string) ($args['type'] ?? ''))?->value;

        $min = $this->price($args['price_min'] ?? null);
        $max = $this->price($args['price_max'] ?? null);
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $limit = $this->limit($args['limit'] ?? null);
        $filters = ['region' => $region, 'type' => $type, 'min' => $min, 'max' => $max, 'sort' => 'harga'];
        $hasBudget = $min !== null || $max !== null;

        $base = (new HotelSearch($filters))->query();
        $total = (clone $base)->reorder()->count();
        $hotels = $base->limit($limit)->get();

        $outside = collect();
        if ($hotels->isEmpty() && $hasBudget) {
            // Tidak ada yang cocok: tunjukkan yang terdekat dengan budget, diberi tanda jelas "di luar budget".
            // Jarak dihitung di database agar tidak bergantung pada 60 hotel termurah saja.
            $target = $max ?? $min;
            $outside = (new HotelSearch(['region' => $region, 'type' => $type]))->query()
                ->whereNotNull('price_min')
                ->reorder()
                ->orderByRaw('abs(price_min - ?)', [$target])
                ->orderBy('name')
                ->limit(3)
                ->get();
        }

        return [
            'data' => [
                'filter_harga' => ['min' => $min, 'max' => $max, 'satuan' => 'rupiah per kamar per malam'],
                'total_cocok' => $total,
                'hotel' => $hotels->map(fn (Hotel $h) => $this->hotelData($h, false))->all(),
                'terdekat_di_luar_budget' => $outside->map(fn (Hotel $h) => $this->hotelData($h, true))->all(),
                'filter_diabaikan' => $ignored,
                'catatan' => $this->hotelNote($hotels->isEmpty(), $hasBudget, $outside->isNotEmpty()),
            ],
            // toBase(): map() pada koleksi Eloquent KOSONG tetap menghasilkan koleksi Eloquent, dan merge()
            // miliknya memanggil getKey() pada setiap item. Dengan array kartu itu crash, sehingga
            // "hotel terdekat di luar budget" dulu tidak pernah sampai ke pengguna.
            'cards' => $hotels->toBase()->map(fn (Hotel $h) => $this->hotelCard($h, false))
                ->merge($outside->toBase()->map(fn (Hotel $h) => $this->hotelCard($h, true)))
                ->values()->all(),
        ];
    }

    private function hotelNote(bool $empty, bool $hasBudget, bool $hasOutside): string
    {
        $note = 'Harga adalah per kamar per malam dan bukan jaminan ketersediaan. Sebut jenis sumber dan tanggal diperiksa. Jangan menghitung total biaya.';

        if ($hasBudget) {
            $note .= ' Hotel tanpa data harga tidak ikut disaring.';
        }
        if ($empty) {
            $note .= $hasOutside
                ? ' Tidak ada hotel dalam budget. Daftar "terdekat_di_luar_budget" berada DI LUAR budget: katakan itu dengan jelas.'
                : ' Tidak ada hotel yang cocok. Katakan terus terang dan tawarkan melonggarkan wilayah, jenis, atau budget.';
        }

        return $note;
    }

    /** @return array<string, mixed> */
    private function placeData(Place $place): array
    {
        $place->loadMissing(['region', 'categories', 'tags']);

        return [
            'nama' => $place->name,
            'slug' => $place->slug,
            'wilayah' => $place->region?->name,
            'area' => $place->area_label,
            'kategori' => $place->categories->pluck('name')->all(),
            'tema' => $place->tags->pluck('name')->all(),
            'ringkasan' => Str::limit((string) $place->summary, 260),
            'status_verifikasi' => $place->verification_status?->value,
            'diverifikasi_pada' => $place->verified_at?->toDateString(),
            'url' => route('places.show', $place->slug),
        ];
    }

    /** @return array<string, mixed> */
    private function placeCard(Place $place): array
    {
        $hero = $place->relationLoaded('media') ? $place->heroMedia() : null;
        $verified = $place->verified_at?->translatedFormat('j M Y');

        return [
            'type' => 'place',
            'key' => 'place:'.$place->slug,
            'label' => 'Tempat',
            'title' => $place->name,
            'subtitle' => collect([$place->region?->name, $place->area_label])->filter()->unique()->implode(' · '),
            'meta' => $verified ? 'Diverifikasi '.$verified : 'Belum diverifikasi',
            'note' => null,
            'url' => route('places.show', $place->slug),
            'image' => $hero?->url(),
            'image_alt' => $hero?->alt_text,
        ];
    }

    /** @return array<string, mixed> */
    private function hotelData(Hotel $hotel, bool $outsideBudget): array
    {
        $hotel->loadMissing('region');

        return [
            'nama' => $hotel->name,
            'slug' => $hotel->slug,
            'jenis' => $hotel->type?->label(),
            'wilayah' => $hotel->region?->name,
            'area' => $hotel->area_label,
            'harga_min' => $hotel->price_min,
            'harga_max' => $hotel->price_max,
            'satuan_harga' => 'per kamar per malam',
            'jenis_sumber_harga' => $hotel->price_basis?->label(),
            'status_harga' => $hotel->price_status?->label(),
            'harga_diperiksa_pada' => $hotel->verified_at?->toDateString(),
            'harga_sudah_lama' => $this->isStale($hotel),
            'di_luar_budget' => $outsideBudget,
            'ringkasan' => Str::limit((string) $hotel->summary, 220),
            'url' => route('hotels.show', $hotel->slug),
        ];
    }

    /** @return array<string, mixed> */
    private function hotelCard(Hotel $hotel, bool $outsideBudget): array
    {
        $hotel->loadMissing('region');
        $hero = $hotel->relationLoaded('media') ? $hotel->heroMedia() : null;

        $notes = [];
        if ($outsideBudget) {
            $notes[] = 'Di luar budget Anda';
        }
        if ($this->isStale($hotel)) {
            $notes[] = 'Harga sudah lama diperiksa, cek ulang';
        } elseif ($hotel->price_status?->value !== 'verified' && $hotel->hasPrice()) {
            $notes[] = 'Harga perlu ditinjau';
        }

        return [
            'type' => 'hotel',
            'key' => 'hotel:'.$hotel->slug,
            'label' => $hotel->type?->label() ?? 'Hotel',
            'title' => $hotel->name,
            'subtitle' => collect([$hotel->region?->name, $hotel->area_label])->filter()->unique()->implode(' · '),
            'price' => $this->priceLine($hotel),
            'meta' => $hotel->hasPrice()
                ? trim(($hotel->price_basis?->label() ?? '').', '.($hotel->verified_at ? 'diperiksa '.$hotel->verified_at->translatedFormat('j M Y') : 'tanggal periksa tidak tercatat'), ', ')
                : null,
            'note' => $notes === [] ? null : implode('. ', $notes),
            'url' => route('hotels.show', $hotel->slug),
            'image' => $hero?->url(),
            'image_alt' => $hero?->alt_text,
        ];
    }

    private function priceLine(Hotel $hotel): string
    {
        if (! $hotel->hasPrice()) {
            return 'Harga tidak tersedia';
        }

        $low = $hotel->price_min ?? $hotel->price_max;
        $format = fn (int $v) => 'Rp'.number_format($v, 0, ',', '.');

        return $format($low)
            .($hotel->price_max && $hotel->price_max !== $low ? ' sampai '.$format($hotel->price_max) : '')
            .' per kamar per malam';
    }

    private function isStale(Hotel $hotel): bool
    {
        return $hotel->verified_at !== null
            && $hotel->verified_at->lt(now()->subDays((int) config('archive.review_after_days.hotel_price')));
    }

    /**
     * @param  array<int, array{slug: string, nama: string}>  $valid
     * @param  array<int, array<string, mixed>>  $ignored
     */
    private function knownSlug(mixed $value, array $valid, string $field, array &$ignored): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $slug = Str::slug($value);

        if (in_array($slug, array_column($valid, 'slug'), true)) {
            return $slug;
        }

        $ignored[] = ['parameter' => $field, 'nilai' => Str::limit($value, 40, ''), 'alasan' => 'bukan slug yang sah, panggil get_catalog_options'];

        return null;
    }

    private function limit(mixed $value): int
    {
        $max = (int) config('chatbot.results_limit');

        return is_numeric($value) ? max(1, min($max, (int) $value)) : $max;
    }

    private function price(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int < 0 ? null : min($int, self::MAX_PRICE);
    }
}
