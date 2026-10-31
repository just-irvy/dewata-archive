<?php

namespace App\Http\Requests\Planner;

use App\Enums\TransportMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'transport_mode' => ['nullable', Rule::enum(TransportMode::class)],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'judul arsip', 'start_date' => 'tanggal mulai', 'transport_mode' => 'transportasi'];
    }
}
