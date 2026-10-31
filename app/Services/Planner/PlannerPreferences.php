<?php

namespace App\Services\Planner;

use App\Enums\Pace;
use App\Enums\TransportMode;

/**
 * Jawaban wizard yang sudah tervalidasi (FILE 07 dan FILE 08). Disimpan utuh sebagai
 * planner_answers agar rekomendasi bisa dibuat ulang tanpa bertanya lagi.
 *
 * Tidak ada anggaran total perjalanan. Satu-satunya angka uang adalah kisaran harga
 * hotel per malam, dan itu hanya dipakai untuk mencocokkan hotel.
 */
final class PlannerPreferences
{
    /**
     * @param  array<int, string>  $themes
     * @param  array<int, int>  $regionIds
     * @param  array<int, string>  $mobility
     * @param  array<int, int>  $stayRegionIds
     * @param  array<int, string>  $hotelTypes
     */
    public function __construct(
        public readonly int $daysCount,
        public readonly ?string $startDate = null,
        public readonly ?int $originRegionId = null,
        public readonly ?string $party = null,
        public readonly array $themes = [],
        public readonly bool $surprise = false,
        public readonly array $regionIds = [],
        public readonly string $flexibility = 'nearby',
        public readonly Pace $pace = Pace::Balanced,
        public readonly array $mobility = [],
        public readonly TransportMode $transport = TransportMode::Undecided,
        public readonly string $dailyTime = 'flexible',
        public readonly bool $avoidOutdoor = false,
        public readonly bool $wantHotel = false,
        public readonly array $stayRegionIds = [],
        public readonly ?int $priceMin = null,
        public readonly ?int $priceMax = null,
        public readonly array $hotelTypes = [],
        public readonly ?int $guests = null,
        public readonly ?int $rooms = null,
    ) {}

    public static function fromArray(array $a): self
    {
        $ints = fn ($v) => array_values(array_map('intval', array_filter((array) $v, fn ($x) => $x !== null && $x !== '')));
        $strs = fn ($v) => array_values(array_map('strval', array_filter((array) $v, fn ($x) => $x !== null && $x !== '')));
        $int = fn ($v) => $v === null || $v === '' ? null : (int) $v;

        return new self(
            daysCount: max(1, (int) ($a['days_count'] ?? 1)),
            startDate: ($a['start_date'] ?? null) ?: null,
            originRegionId: $int($a['origin_region_id'] ?? null),
            party: ($a['party'] ?? null) ?: null,
            themes: $strs($a['themes'] ?? []),
            surprise: (bool) ($a['surprise'] ?? false),
            regionIds: $ints($a['region_ids'] ?? []),
            flexibility: ($a['flexibility'] ?? null) === 'willing' ? 'willing' : 'nearby',
            pace: Pace::tryFrom((string) ($a['pace'] ?? '')) ?? Pace::Balanced,
            mobility: $strs($a['mobility'] ?? []),
            transport: TransportMode::tryFrom((string) ($a['transport_mode'] ?? '')) ?? TransportMode::Undecided,
            dailyTime: array_key_exists($a['daily_time'] ?? '', config('planner.daily_time')) ? $a['daily_time'] : 'flexible',
            avoidOutdoor: (bool) ($a['avoid_outdoor'] ?? false),
            wantHotel: (bool) ($a['want_hotel'] ?? false),
            stayRegionIds: $ints($a['stay_region_ids'] ?? []),
            priceMin: $int($a['price_min'] ?? null),
            priceMax: $int($a['price_max'] ?? null),
            hotelTypes: $strs($a['hotel_types'] ?? []),
            guests: $int($a['guests'] ?? null),
            rooms: $int($a['rooms'] ?? null),
        );
    }

    /** Bentuk yang disimpan sebagai planner_answers. Kunci sama dengan nama field form. */
    public function toArray(): array
    {
        return [
            'days_count' => $this->daysCount,
            'start_date' => $this->startDate,
            'origin_region_id' => $this->originRegionId,
            'party' => $this->party,
            'themes' => $this->themes,
            'surprise' => $this->surprise,
            'region_ids' => $this->regionIds,
            'flexibility' => $this->flexibility,
            'pace' => $this->pace->value,
            'mobility' => $this->mobility,
            'transport_mode' => $this->transport->value,
            'daily_time' => $this->dailyTime,
            'avoid_outdoor' => $this->avoidOutdoor,
            'want_hotel' => $this->wantHotel,
            'stay_region_ids' => $this->stayRegionIds,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'hotel_types' => $this->hotelTypes,
            'guests' => $this->guests,
            'rooms' => $this->rooms,
        ];
    }

    public function maxStops(): int
    {
        $base = (int) config("planner.pace.{$this->pace->value}.max_stops");
        $mod = (int) config("planner.daily_time.{$this->dailyTime}", 0);

        return max(1, min((int) config('planner.max_items_per_day'), $base + $mod));
    }

    public function minuteBudget(): int
    {
        $base = (int) config("planner.pace.{$this->pace->value}.day_minutes");
        $mod = (int) config("planner.daily_time.{$this->dailyTime}", 0);

        return max(120, $base + $mod * 60);
    }

    public function radiusKm(): float
    {
        return (float) config("planner.radius_km.{$this->flexibility}");
    }

    public function usesThemes(): bool
    {
        return ! $this->surprise && $this->themes !== [];
    }
}
