<?php

namespace App\Http\Requests\Admin;

use App\Models\Hotel;
use App\Models\Place;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MediaStoreRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'mediable_type' => ['required', Rule::in(['place', 'hotel'])],
            'mediable_id' => ['required', 'integer'],
            'file' => [
                'required', 'file', 'image',
                'mimes:'.implode(',', config('archive.media.mimes')),
                'max:'.config('archive.media.max_kb'),
                'dimensions:max_width=8000,max_height=8000',
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'credit' => ['nullable', 'string', 'max:255'],
            'license_note' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
        ];
    }

    public function mediable(): Model
    {
        $class = $this->input('mediable_type') === 'hotel' ? Hotel::class : Place::class;

        return $class::query()->findOrFail($this->input('mediable_id'));
    }
}
