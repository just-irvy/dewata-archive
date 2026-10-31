<?php

namespace App\Http\Requests\Planner;

use App\Enums\HotelType;
use App\Enums\Pace;
use App\Enums\TagType;
use App\Enums\TransportMode;
use App\Services\Planner\PlannerPreferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Wizard mode Rekomendasi, enam langkah (FILE 07 dan FILE 08). Satu-satunya input uang
 * adalah kisaran harga hotel per malam. Tidak ada field anggaran perjalanan.
 */
class RecommendRequest extends FormRequest
{
    /** Langkah wizard untuk tiap field, agar galat membuka langkah yang benar. */
    public const STEPS = [
        'days_count' => 1, 'start_date' => 1, 'origin_region_id' => 1, 'party' => 1,
        'themes' => 2, 'surprise' => 2,
        'region_ids' => 3, 'flexibility' => 3,
        'pace' => 4, 'mobility' => 4, 'transport_mode' => 4, 'daily_time' => 4, 'avoid_outdoor' => 4,
        'want_hotel' => 5, 'stay_region_ids' => 5, 'price_min' => 5, 'price_max' => 5,
        'hotel_types' => 5, 'guests' => 5, 'rooms' => 5,
        'title' => 6,
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $publishedRegion = Rule::exists('regions', 'id')->where('is_published', true);

        return [
            'title' => ['nullable', 'string', 'max:120'],
            'days_count' => ['required', 'integer', 'min:1', 'max:'.config('planner.max_days')],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'origin_region_id' => ['nullable', 'integer', $publishedRegion],
            'party' => ['nullable', Rule::in(array_keys(config('planner.labels.party')))],

            'surprise' => ['nullable', 'boolean'],
            'themes' => ['nullable', 'array', 'max:'.config('planner.max_themes')],
            'themes.*' => ['string', 'distinct', Rule::exists('tags', 'slug')->where('tag_type', TagType::Theme->value)],

            'region_ids' => ['nullable', 'array', 'max:10'],
            'region_ids.*' => ['integer', 'distinct', $publishedRegion],
            'flexibility' => ['required', Rule::in(['nearby', 'willing'])],

            'pace' => ['required', Rule::enum(Pace::class)],
            'mobility' => ['nullable', 'array'],
            'mobility.*' => ['string', 'distinct', Rule::in(array_keys(config('archive.accessibility_attributes')))],
            'transport_mode' => ['required', Rule::enum(TransportMode::class)],
            'daily_time' => ['required', Rule::in(array_keys(config('planner.daily_time')))],
            'avoid_outdoor' => ['nullable', 'boolean'],

            'want_hotel' => ['nullable', 'boolean'],
            'stay_region_ids' => ['nullable', 'array', 'max:10'],
            'stay_region_ids.*' => ['integer', 'distinct', $publishedRegion],
            'price_min' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'price_max' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'hotel_types' => ['nullable', 'array'],
            'hotel_types.*' => ['string', 'distinct', Rule::enum(HotelType::class)],
            'guests' => ['nullable', 'integer', 'min:1', 'max:30'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:15'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->boolean('surprise') && empty($this->input('themes'))) {
                    $validator->errors()->add('themes', 'Pilih minimal satu tema, atau pilih "Kejutkan saya".');
                }
                $min = $this->input('price_min');
                $max = $this->input('price_max');
                if (is_numeric($min) && is_numeric($max) && (int) $max < (int) $min) {
                    $validator->errors()->add('price_max', 'Harga maksimal per malam harus sama dengan atau lebih besar dari harga minimal.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'days_count' => 'jumlah hari',
            'start_date' => 'tanggal mulai',
            'origin_region_id' => 'wilayah titik awal',
            'themes' => 'tema',
            'region_ids' => 'wilayah',
            'pace' => 'tempo',
            'transport_mode' => 'transportasi',
            'daily_time' => 'waktu harian',
            'price_min' => 'harga minimal per malam',
            'price_max' => 'harga maksimal per malam',
            'guests' => 'jumlah tamu',
            'rooms' => 'jumlah kamar',
        ];
    }

    public function preferences(): PlannerPreferences
    {
        $data = $this->validated();
        $data['surprise'] = $this->boolean('surprise');
        $data['avoid_outdoor'] = $this->boolean('avoid_outdoor');
        $data['want_hotel'] = $this->boolean('want_hotel');

        if ($data['surprise']) {
            $data['themes'] = [];
        }

        // Preferensi hotel hanya disimpan bila pengguna memang meminta rekomendasi hotel.
        if (! $data['want_hotel']) {
            foreach (['stay_region_ids', 'price_min', 'price_max', 'hotel_types', 'guests', 'rooms'] as $key) {
                $data[$key] = null;
            }
        }

        return PlannerPreferences::fromArray($data);
    }
}
