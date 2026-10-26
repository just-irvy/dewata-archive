<?php

namespace Database\Factories;

use App\Enums\HotelType;
use App\Enums\PublishStatus;
use App\Enums\VerificationStatus;
use App\Models\Hotel;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Hotel> */
class HotelFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Penginapan '.fake()->unique()->numerify('####');

        return [
            'region_id' => Region::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => HotelType::Guesthouse,
            'summary' => 'Ringkasan uji untuk '.$name.'.',
            'lat' => fake()->randomFloat(7, -8.8, -8.1),
            'lng' => fake()->randomFloat(7, 114.6, 115.6),
            'currency' => 'IDR',
            'price_status' => VerificationStatus::Unknown,
            'status' => PublishStatus::Draft,
        ];
    }

    public function ready(): static
    {
        return $this->state([
            'source_url' => 'https://example.test/hotel',
            'verified_at' => now()->subDay(),
        ]);
    }

    public function published(): static
    {
        return $this->ready()->state([
            'status' => PublishStatus::Published,
        ]);
    }
}
