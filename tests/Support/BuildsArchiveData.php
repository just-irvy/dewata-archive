<?php

namespace Tests\Support;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Place;
use App\Models\Region;
use App\Models\User;

/** Pembuat data uji sederhana. Factory penuh menyusul di Fase 1. */
trait BuildsArchiveData
{
    protected function makeUser(UserRole $role = UserRole::User): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->refresh();
    }

    protected function makePlace(PublishStatus $status = PublishStatus::Published): Place
    {
        $region = Region::query()->firstOrCreate(
            ['slug' => 'gianyar'],
            ['name' => 'Gianyar', 'is_published' => true],
        );

        $place = new Place([
            'region_id' => $region->id,
            'name' => 'Tempat Uji '.fake()->unique()->numerify('###'),
            'slug' => 'tempat-uji-'.fake()->unique()->numerify('####'),
            'summary' => 'Ringkasan uji.',
            'lat' => -8.5069000,
            'lng' => 115.2625000,
        ]);
        $place->forceFill(['status' => $status])->save();

        return $place;
    }
}
