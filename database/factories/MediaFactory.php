<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Media> */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'path' => 'media/uji/'.fake()->unique()->lexify('????????').'.jpg',
            'alt_text' => 'Deskripsi foto uji.',
            'credit' => 'Fotografer Uji',
            'license_note' => 'Izin tertulis, 2026',
            'sort_order' => 0,
        ];
    }

    public function withoutAlt(): static
    {
        return $this->state(['alt_text' => null]);
    }
}
