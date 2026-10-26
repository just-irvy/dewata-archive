<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Collection> */
class CollectionFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Koleksi '.fake()->unique()->numerify('####');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'dek' => 'Pengantar singkat koleksi.',
            'status' => PublishStatus::Draft,
        ];
    }
}
