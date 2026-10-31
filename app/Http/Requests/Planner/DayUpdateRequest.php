<?php

namespace App\Http\Requests\Planner;

use App\Enums\PublishStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DayUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotels', 'id')
                ->where('status', PublishStatus::Published->value)
                ->whereNull('deleted_at')
                ->whereNotNull('lat')],
            'return_to_hotel' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'judul hari', 'notes' => 'catatan hari', 'hotel_id' => 'penginapan'];
    }

    public function dayData(): array
    {
        return [
            'title' => $this->input('title'),
            'notes' => $this->input('notes'),
            'hotel_id' => $this->filled('hotel_id') ? (int) $this->input('hotel_id') : null,
            'return_to_hotel' => $this->boolean('return_to_hotel'),
        ];
    }
}
