<?php

namespace App\Http\Requests\Planner;

use Illuminate\Foundation\Http\FormRequest;

/** Catatan pribadi, durasi kunjungan, dan jam mulai opsional pada satu item. */
class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:720'],
            'start_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function attributes(): array
    {
        return ['note' => 'catatan', 'duration_minutes' => 'durasi kunjungan', 'start_time' => 'jam mulai'];
    }
}
