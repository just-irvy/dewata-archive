<?php

namespace Tests\Feature;

use App\Enums\HotelType;
use App\Enums\PriceBasis;
use App\Enums\VerificationStatus;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class HotelPagesTest extends TestCase
{
    use RefreshDatabase;
    use WithoutViteAssets;

    private function priced(string $name, int $min, ?int $max = null, array $extra = []): Hotel
    {
        return Hotel::factory()->published()->create([
            'name' => $name,
            'price_min' => $min,
            'price_max' => $max,
            'price_basis' => PriceBasis::Curated,
            'price_status' => VerificationStatus::Verified,
        ] + $extra);
    }

    public function test_lists_only_published_hotels(): void
    {
        Hotel::factory()->published()->create(['name' => 'Hotel Terbit']);
        Hotel::factory()->create(['name' => 'Hotel Draf']);

        $this->get('/hotels')->assertOk()->assertSee('Hotel Terbit')->assertDontSee('Hotel Draf');
    }

    public function test_price_filter_matches_overlapping_ranges_and_excludes_unpriced_hotels(): void
    {
        $this->priced('Hotel A', 400000, 600000);
        $this->priced('Hotel B', 1000000, 1500000);
        $this->priced('Hotel D', 300000);
        Hotel::factory()->published()->create(['name' => 'Hotel C']);

        $this->get('/hotels?min=500000&max=700000')->assertOk()
            ->assertSee('Hotel A')
            ->assertDontSee('Hotel B')
            ->assertDontSee('Hotel C')
            ->assertDontSee('Hotel D');

        $this->get('/hotels?max=350000')->assertOk()
            ->assertSee('Hotel D')
            ->assertDontSee('Hotel A');
    }

    public function test_without_a_price_filter_unpriced_hotels_are_listed_as_unavailable(): void
    {
        Hotel::factory()->published()->create(['name' => 'Hotel Tanpa Harga']);

        $this->get('/hotels')->assertOk()->assertSee('Hotel Tanpa Harga')->assertSee('Harga tidak tersedia');
    }

    public function test_price_sort_puts_unpriced_hotels_last(): void
    {
        $this->priced('Hotel Mahal', 900000);
        $this->priced('Hotel Murah', 200000);
        Hotel::factory()->published()->create(['name' => 'Hotel Kosong']);

        $this->get('/hotels?sort=harga')->assertSeeInOrder(['Hotel Murah', 'Hotel Mahal', 'Hotel Kosong']);
    }

    public function test_price_block_states_unit_and_source_type(): void
    {
        $this->priced('Hotel Berharga', 400000, 600000);

        $this->get('/hotels')->assertOk()
            ->assertSee('per kamar per malam')
            ->assertSee('Data terkurasi');
    }

    public function test_old_price_checks_are_flagged(): void
    {
        $this->priced('Hotel Lama', 400000, null, ['verified_at' => now()->subDays(200)]);

        $this->get('/hotels')->assertOk()->assertSee('Harga sudah lama diperiksa');
    }

    public function test_filter_by_type(): void
    {
        Hotel::factory()->published()->create(['name' => 'Villa Uji', 'type' => HotelType::Villa]);
        Hotel::factory()->published()->create(['name' => 'Guesthouse Uji', 'type' => HotelType::Guesthouse]);

        $this->get('/hotels?type=villa')->assertSee('Villa Uji')->assertDontSee('Guesthouse Uji');
    }

    public function test_max_below_min_is_rejected(): void
    {
        $this->get('/hotels?min=500000&max=100000')->assertSessionHasErrors('max');
    }

    public function test_detail_page_is_published_only_and_denies_availability_claims(): void
    {
        $hotel = Hotel::factory()->published()->create(['slug' => 'hotel-uji']);
        $draft = Hotel::factory()->create(['slug' => 'hotel-draf']);

        $this->get('/hotels/hotel-uji')->assertOk()->assertSee('Ketersediaan kamar tidak ditampilkan');
        $this->get('/hotels/hotel-draf')->assertNotFound();
    }
}
