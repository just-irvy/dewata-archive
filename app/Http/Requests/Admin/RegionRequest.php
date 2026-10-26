<?php

namespace App\Http\Requests\Admin;

use App\Enums\PublishStatus;
use App\Enums\RegionType;
use App\Models\Region;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegionRequest extends AdminRequest
{
    private function region(): ?Region
    {
        $region = $this->route('region');

        return $region instanceof Region ? $region : null;
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
            'slug' => ['required', 'string', 'max:120', Rule::unique('regions', 'slug')->ignore($this->region()?->id)],
            'region_type' => ['required', Rule::enum(RegionType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $region = $this->region();

            if ($region === null || $this->boolean('is_published')) {
                return;
            }

            $published = PublishStatus::Published->value;
            $inUse = $region->places()->where('status', $published)->exists()
                || $region->hotels()->where('status', $published)->exists();

            if ($inUse) {
                $validator->errors()->add('is_published', 'Wilayah masih dipakai tempat atau hotel yang terbit. Arsipkan atau pindahkan dulu.');
            }
        }];
    }
}
