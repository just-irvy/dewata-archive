<?php

namespace Tests\Support;

use App\Enums\PublishStatus;
use App\Enums\TagType;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Planner\GuestDrafts;
use Illuminate\Testing\TestResponse;

/** Katalog kecil yang realistis untuk tes planner: dua wilayah berjauhan, beberapa tema. */
trait BuildsPlannerData
{
    protected Region $gianyar;

    protected Region $buleleng;

    protected Tag $pura;

    protected Tag $alam;

    protected function region(string $slug, string $name): Region
    {
        return Region::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'is_published' => true]);
    }

    protected function theme(string $slug, string $name): Tag
    {
        return Tag::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'tag_type' => TagType::Theme]);
    }

    /** @param  array<int, Tag>  $tags */
    protected function spot(Region $region, string $name, float $lat, float $lng, array $tags = [], array $attrs = [], PublishStatus $status = PublishStatus::Published): Place
    {
        $place = Place::factory()->create(array_merge([
            'region_id' => $region->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numerify('###'),
            'lat' => $lat,
            'lng' => $lng,
            'status' => $status,
            'published_at' => $status === PublishStatus::Published ? now() : null,
            'source_url' => 'https://example.test/sumber',
            'verified_at' => now()->subDay(),
            'verification_status' => VerificationStatus::Verified,
        ], $attrs));

        $place->categories()->attach(Category::query()->firstOrCreate(['slug' => 'wisata'], ['name' => 'Wisata', 'is_published' => true]));
        $place->tags()->attach(collect($tags)->pluck('id'));

        return $place;
    }

    /** Gianyar: enam tempat berdekatan sekitar Ubud. Buleleng: empat tempat di utara. */
    protected function seedCatalog(): void
    {
        $this->gianyar = $this->region('gianyar', 'Gianyar');
        $this->buleleng = $this->region('buleleng', 'Buleleng');
        $this->pura = $this->theme('pura-arsitektur', 'Pura dan Arsitektur');
        $this->alam = $this->theme('alam', 'Alam');

        $this->spot($this->gianyar, 'Pura Tirta', -8.415, 115.315, [$this->pura], ['area_label' => 'Tampaksiring']);
        $this->spot($this->gianyar, 'Pura Gunung Kawi', -8.423, 115.312, [$this->pura], ['area_label' => 'Tampaksiring']);
        $this->spot($this->gianyar, 'Pura Saraswati', -8.506, 115.262, [$this->pura], ['area_label' => 'Ubud']);
        $this->spot($this->gianyar, 'Goa Gajah', -8.523, 115.287, [$this->pura], ['area_label' => 'Ubud']);
        $this->spot($this->gianyar, 'Sawah Tegallalang', -8.433, 115.279, [$this->alam], ['area_label' => 'Ubud']);
        $this->spot($this->gianyar, 'Air Terjun Tegenungan', -8.575, 115.290, [$this->alam]);

        $this->spot($this->buleleng, 'Pura Beji', -8.090, 115.180, [$this->pura]);
        $this->spot($this->buleleng, 'Air Terjun Gitgit', -8.196, 115.137, [$this->alam]);
        $this->spot($this->buleleng, 'Danau Tamblingan', -8.255, 115.093, [$this->alam]);
        $this->spot($this->buleleng, 'Brahmavihara Arama', -8.177, 115.006, [$this->pura]);
    }

    protected function wizard(array $overrides = []): array
    {
        return array_replace([
            'days_count' => 2,
            'themes' => ['pura-arsitektur', 'alam'],
            'flexibility' => 'nearby',
            'pace' => 'balanced',
            'transport_mode' => 'car',
            'daily_time' => 'flexible',
        ], $overrides);
    }

    /** Cookie draf tamu dari respons, untuk dikirim lagi seperti browser. */
    protected function draftCookie(TestResponse $response): string
    {
        return $response->getCookie(GuestDrafts::COOKIE)->getValue();
    }
}
