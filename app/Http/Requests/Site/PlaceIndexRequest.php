<?php

namespace App\Http\Requests\Site;

use App\Content\PlaceSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:120'],
            'access' => ['nullable', 'array'],
            'access.*' => ['string', Rule::in(array_keys(config('archive.accessibility_attributes')))],
            'sort' => ['nullable', Rule::in(PlaceSearch::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{q: ?string, region: ?string, category: ?string, tags: array<int, string>, access: array<int, string>, sort: string} */
    public function filters(): array
    {
        return [
            'q' => $this->filled('q') ? trim($this->string('q')->toString()) : null,
            'region' => $this->input('region') ?: null,
            'category' => $this->input('category') ?: null,
            'tags' => array_values(array_filter((array) $this->input('tags', []))),
            'access' => array_values(array_filter((array) $this->input('access', []))),
            'sort' => $this->input('sort') ?: 'terbaru',
        ];
    }
}
