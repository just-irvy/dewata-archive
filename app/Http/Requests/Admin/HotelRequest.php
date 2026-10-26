<?php

namespace App\Http\Requests\Admin;

use App\Enums\HotelType;
use App\Enums\PriceBasis;
use App\Enums\VerificationStatus;
use App\Models\Hotel;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HotelRequest extends AdminRequest
{
    private function hotel(): ?Hotel
    {
        $hotel = $this->route('hotel');

        return $hotel instanceof Hotel ? $hotel : null;
    }

    protected function prepareForValidation(): void
    {
        $slug = Str::slug((string) ($this->filled('slug') ? $this->input('slug') : $this->input('name')));

        if ($this->hotel()?->status->value === 'published') {
            $slug = $this->hotel()->slug;
        }

        $this->merge(['slug' => $slug]);
    }

    public function rules(): array
    {
        return [
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('hotels', 'slug')->ignore($this->hotel()?->id)],
            'type' => ['required', Rule::enum(HotelType::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'summary' => ['required', 'string', 'max:500'],
            'price_min' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'price_max' => array_filter(['nullable', 'integer', 'min:0', 'max:1000000000', filled($this->input('price_min')) ? 'gte:price_min' : null]),
            'price_basis' => ['nullable', Rule::enum(PriceBasis::class)],
            'price_status' => ['required', Rule::enum(VerificationStatus::class)],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'verified_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $data = $validator->getData();
            $hasPrice = filled($data['price_min'] ?? null) || filled($data['price_max'] ?? null);

            if ($hasPrice && blank($data['price_basis'] ?? null)) {
                $validator->errors()->add('price_basis', 'Pilih jenis sumber harga: terkurasi, penyedia, atau estimasi.');
            }
            if (! $hasPrice && filled($data['price_basis'] ?? null)) {
                $validator->errors()->add('price_basis', 'Harga kosong, jadi jenis sumber harga dikosongkan.');
            }
            if (! $hasPrice && ($data['price_status'] ?? 'unknown') !== 'unknown') {
                $validator->errors()->add('price_status', 'Harga kosong, jadi statusnya harus "Belum diketahui".');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function hotelData(): array
    {
        return $this->validated();
    }
}
