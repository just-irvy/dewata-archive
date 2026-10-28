<?php

namespace App\Http\Requests\Site;

use App\Content\HotelSearch;
use App\Enums\HotelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotelIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'region' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(HotelType::class)],
            'min' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'max' => array_filter(['nullable', 'integer', 'min:0', 'max:1000000000', filled($this->input('min')) ? 'gte:min' : null]),
            'sort' => ['nullable', Rule::in(HotelSearch::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{region: ?string, type: ?string, min: ?int, max: ?int, sort: string} */
    public function filters(): array
    {
        return [
            'region' => $this->input('region') ?: null,
            'type' => $this->input('type') ?: null,
            'min' => $this->filled('min') ? $this->integer('min') : null,
            'max' => $this->filled('max') ? $this->integer('max') : null,
            'sort' => $this->input('sort') ?: 'terbaru',
        ];
    }
}
