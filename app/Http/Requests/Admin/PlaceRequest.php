<?php

namespace App\Http\Requests\Admin;

use App\Enums\VerificationStatus;
use App\Models\Place;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlaceRequest extends AdminRequest
{
    private function place(): ?Place
    {
        $place = $this->route('place');

        return $place instanceof Place ? $place : null;
    }

    protected function prepareForValidation(): void
    {
        $slug = Str::slug((string) ($this->filled('slug') ? $this->input('slug') : $this->input('name')));

        // URL tempat yang sudah terbit tidak boleh berubah (FILE 19: URL detail stabil).
        if ($this->place()?->isPublished()) {
            $slug = $this->place()->slug;
        }

        $this->merge(['slug' => $slug, 'is_featured' => $this->boolean('is_featured')]);
    }

    public function rules(): array
    {
        return [
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'is_featured' => ['boolean'],
            'name' => ['required', 'string', 'max:150'],
            'area_label' => ['nullable', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('places', 'slug')->ignore($this->place()?->id)],
            'summary' => ['required', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:20000'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'verified_at' => ['nullable', 'date', 'before_or_equal:today'],
            'source_updated_at' => ['nullable', 'date', 'before_or_equal:today'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'attribution' => ['nullable', 'string', 'max:255'],
            'typical_visit_minutes' => ['nullable', 'integer', 'between:5,720'],
            'price_info' => ['nullable', 'string', 'max:255'],
            'opening_hours_status' => ['required', Rule::enum(VerificationStatus::class)],
            'accessibility_status' => ['required', Rule::enum(VerificationStatus::class)],
            'hours' => ['nullable', 'array'],
            'hours.*.closed' => ['nullable', 'boolean'],
            'hours.*.open' => ['nullable', 'date_format:H:i'],
            'hours.*.close' => ['nullable', 'date_format:H:i'],
            'accessibility' => ['nullable', 'array'],
            'accessibility.*' => ['nullable', Rule::in(['yes', 'no', 'unknown'])],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $data = $validator->getData();

            foreach (config('archive.weekdays') as $key => $label) {
                $day = $data['hours'][$key] ?? [];
                $closed = filter_var($day['closed'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (! $closed && (filled($day['open'] ?? null) xor filled($day['close'] ?? null))) {
                    $validator->errors()->add("hours.$key.close", "$label: isi jam buka dan jam tutup, atau tandai tutup.");
                }
            }

            $hours = $this->normalizedHours($data['hours'] ?? []);
            $accessibility = $this->normalizedAccessibility($data['accessibility'] ?? []);

            if ($hours === null && ($data['opening_hours_status'] ?? 'unknown') !== 'unknown') {
                $validator->errors()->add('opening_hours_status', 'Jam buka kosong, jadi statusnya harus "Belum diketahui".');
            }
            if ($accessibility === null && ($data['accessibility_status'] ?? 'unknown') !== 'unknown') {
                $validator->errors()->add('accessibility_status', 'Aksesibilitas kosong, jadi statusnya harus "Belum diketahui".');
            }
            if (($data['verification_status'] ?? null) === 'verified' && blank($data['verified_at'] ?? null)) {
                $validator->errors()->add('verified_at', 'Isi tanggal verifikasi bila status verifikasi "Terverifikasi".');
            }
        }];
    }

    /** @return array<string, mixed> Data siap fill() ke model Place. */
    public function placeData(): array
    {
        $validated = $this->validated();

        return Arr::except($validated, ['hours', 'accessibility', 'category_ids', 'tag_ids', 'is_featured']) + [
            'opening_hours' => $this->normalizedHours($validated['hours'] ?? []),
            'accessibility' => $this->normalizedAccessibility($validated['accessibility'] ?? []),
        ];
    }

    /** @return array<int, int> */
    public function categoryIds(): array
    {
        return array_map('intval', $this->validated('category_ids', []) ?? []);
    }

    /** @return array<int, int> */
    public function tagIds(): array
    {
        return array_map('intval', $this->validated('tag_ids', []) ?? []);
    }

    private function normalizedHours(array $input): ?array
    {
        $result = [];

        foreach (array_keys(config('archive.weekdays')) as $key) {
            $day = $input[$key] ?? [];

            if (filter_var($day['closed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $result[$key] = ['closed' => true];
            } elseif (filled($day['open'] ?? null) && filled($day['close'] ?? null)) {
                $result[$key] = ['open' => $day['open'], 'close' => $day['close']];
            }
        }

        return $result === [] ? null : $result;
    }

    private function normalizedAccessibility(array $input): ?array
    {
        $result = [];

        foreach (array_keys(config('archive.accessibility_attributes')) as $key) {
            if (in_array($input[$key] ?? null, ['yes', 'no'], true)) {
                $result[$key] = $input[$key];
            }
        }

        return $result === [] ? null : $result;
    }
}
