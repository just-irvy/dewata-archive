<?php

namespace App\Http\Requests\Admin;

class MediaUpdateRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'credit' => ['nullable', 'string', 'max:255'],
            'license_note' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
        ];
    }
}
