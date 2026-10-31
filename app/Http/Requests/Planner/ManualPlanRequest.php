<?php

namespace App\Http\Requests\Planner;

use App\Enums\TransportMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'days_count' => ['required', 'integer', 'min:1', 'max:'.config('planner.max_days')],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'origin_region_id' => ['nullable', 'integer', Rule::exists('regions', 'id')->where('is_published', true)],
            'transport_mode' => ['nullable', Rule::enum(TransportMode::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul arsip',
            'days_count' => 'jumlah hari',
            'start_date' => 'tanggal mulai',
            'origin_region_id' => 'wilayah titik awal',
            'transport_mode' => 'transportasi',
        ];
    }
}
