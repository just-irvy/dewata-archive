<?php

namespace App\Http\Requests\Admin;

use App\Models\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CollectionPlaceRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'place_id' => [
                Rule::requiredIf($this->isMethod('post')),
                'integer',
                Rule::exists('places', 'id')->where('status', 'published')->whereNull('deleted_at'),
            ],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
            'editor_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $collection = $this->route('collection');

            if ($this->isMethod('post') && $collection instanceof Collection && filled($this->input('place_id'))
                && $collection->places()->whereKey($this->input('place_id'))->exists()) {
                $validator->errors()->add('place_id', 'Tempat ini sudah ada di koleksi.');
            }
        }];
    }
}
