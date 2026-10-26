<?php

namespace Tests\Feature;

use App\Models\Region;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_nine_administrative_regions_and_is_idempotent(): void
    {
        $this->seed(RegionSeeder::class);
        $this->seed(RegionSeeder::class);

        $this->assertSame(9, Region::count());
        $this->assertSame(9, Region::query()->published()->where('region_type', 'administrative')->count());
    }
}
