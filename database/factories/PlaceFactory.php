<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Place;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Place> */
class PlaceFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Tempat '.fake()->unique()->numerify('####');

        return [
            'region_id' => Region::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'summary' => 'Ringkasan uji untuk '.$name.'.',
            'lat' => fake()->randomFloat(7, -8.8, -8.1),
            'lng' => fake()->randomFloat(7, 114.6, 115.6),
            'status' => PublishStatus::Draft,
            'verification_status' => VerificationStatus::Unknown,
        ];
    }

    /** Draf yang lolos pemeriksaan kesiapan terbit. */
    public function ready(): static
    {
        return $this->state([
            'source_url' => 'https://example.test/sumber',
            'verified_at' => now()->subDay(),
            'verification_status' => VerificationStatus::Verified,
        ])->afterCreating(function (Place $place) {
            $place->categories()->attach(Category::factory()->create());
        });
    }

    public function published(): static
    {
        return $this->ready()->state([
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ]);
    }
}
