<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class HotelAdminTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'region_id' => Region::factory()->create()->id,
            'name' => 'Villa Uji',
            'type' => 'villa',
            'lat' => '-8.5000000',
            'lng' => '115.2600000',
            'summary' => 'Villa kecil dengan kolam.',
            'price_status' => 'unknown',
        ], $overrides);
    }

    public function test_admin_creates_a_draft_hotel(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))
            ->post('/admin/hotels', $this->payload(['status' => 'published']))
            ->assertSessionHasNoErrors();

        $hotel = Hotel::query()->firstOrFail();
        $this->assertSame('draft', $hotel->status->value);
        $this->assertSame('villa-uji', $hotel->slug);
    }

    public function test_regular_user_cannot_create_hotels(): void
    {
        $this->actingAs($this->makeUser())->post('/admin/hotels', $this->payload())->assertForbidden();
        $this->assertSame(0, Hotel::count());
    }

    public function test_max_price_cannot_be_below_min_price(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))
            ->post('/admin/hotels', $this->payload([
                'price_min' => 500000, 'price_max' => 300000,
                'price_basis' => 'curated', 'price_status' => 'verified',
            ]))
            ->assertSessionHasErrors('price_max');
    }

    public function test_price_requires_a_basis_and_basis_requires_a_price(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)->post('/admin/hotels', $this->payload(['price_min' => 400000]))
            ->assertSessionHasErrors('price_basis');

        $this->actingAs($admin)->post('/admin/hotels', $this->payload(['name' => 'Lain', 'price_basis' => 'estimate']))
            ->assertSessionHasErrors('price_basis');
    }

    public function test_price_status_must_be_unknown_without_a_price(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))
            ->post('/admin/hotels', $this->payload(['price_status' => 'verified']))
            ->assertSessionHasErrors('price_status');
    }

    public function test_price_changes_create_snapshots_but_other_edits_do_not(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $base = $this->payload([
            'price_min' => 400000, 'price_max' => 600000,
            'price_basis' => 'curated', 'price_status' => 'verified',
            'source_url' => 'https://example.test/harga', 'verified_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)->post('/admin/hotels', $base)->assertSessionHasNoErrors();
        $hotel = Hotel::query()->firstOrFail();
        $this->assertSame(1, $hotel->priceSnapshots()->count());

        $this->actingAs($admin)->put("/admin/hotels/{$hotel->id}", array_replace($base, ['summary' => 'Ringkasan baru.']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $hotel->priceSnapshots()->count());

        $this->actingAs($admin)->put("/admin/hotels/{$hotel->id}", array_replace($base, ['price_min' => 450000]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $hotel->priceSnapshots()->count());
    }
}
