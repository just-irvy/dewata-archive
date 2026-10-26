<?php

namespace App\Http\Requests\Admin;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TagRequest extends AdminRequest
{
    private function tag(): ?Tag
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag ? $tag : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->filled('slug') ? $this->input('slug') : $this->input('name'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('tags', 'slug')->ignore($this->tag()?->id)],
            'tag_type' => ['required', Rule::enum(TagType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
