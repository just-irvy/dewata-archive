<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryRequest extends AdminRequest
{
    private function category(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->filled('slug') ? $this->input('slug') : $this->input('name'))),
            'is_published' => $this->boolean('is_published'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('categories', 'slug')->ignore($this->category()?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon_key' => ['nullable', 'alpha_dash', 'max:40'],
            'is_published' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $category = $this->category();

            if ($category === null || $this->boolean('is_published')) {
                return;
            }

            // Tempat terbit tidak boleh kehilangan kategori terbit terakhirnya.
            $orphaned = $category->places()->published()
                ->whereDoesntHave('categories', fn ($q) => $q->where('is_published', true)->where('categories.id', '!=', $category->id))
                ->exists();

            if ($orphaned) {
                $validator->errors()->add('is_published', 'Ada tempat terbit yang hanya punya kategori ini. Beri kategori lain dulu.');
            }
        }];
    }
}
