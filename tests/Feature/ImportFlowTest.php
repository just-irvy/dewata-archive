<?php

namespace Tests\Feature;

use App\Enums\PriceBasis;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\ImportBatch;
use App\Models\Place;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class ImportFlowTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    private const HEADER = ['id', 'name', 'category', 'location_area', 'latitude', 'longitude', 'price_per_night', 'vibe_category', 'historical_value'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['gianyar', 'badung', 'klungkung'] as $slug) {
            Region::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
        }
    }

    private function rows(): array
    {
        return [
            ['1', 'Pura Uji', 'Wisata', 'Ubud', '-8.5000', '115.2000', '0', 'Pura & Sejarah', 'Pura kecil di tepi sawah.'],
            ['2', 'Hotel Uji', 'Hotel', 'Canggu', '-8.6500', '115.1300', '1500000', 'Pesisir', 'Hotel dekat pantai.'],
            ['3', 'Kafe Uji', 'Kafe', 'Ubud', '-8.5100', '115.2600', '0', 'Kuliner Estetik', 'Kafe kecil di gang.'],
            ['4', 'Tempat Rusak', 'Wisata', 'Atlantis', '-8.5200', '115.2700', '0', 'Pesisir', 'Area tidak dikenal.'],
        ];
    }

    private function csv(?array $rows = null): string
    {
        $stream = fopen('php://memory', 'r+');
        fputcsv($stream, self::HEADER, ',', '"', '');
        foreach ($rows ?? $this->rows() as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);

        return stream_get_contents($stream);
    }

    private function upload(?string $csv = null, UserRole $role = UserRole::Admin)
    {
        return $this->actingAs($this->makeUser($role))->post('/admin/imports', [
            'file' => UploadedFile::fake()->createWithContent('dataset.csv', $csv ?? $this->csv()),
        ]);
    }

    private function stageAndCommit(?string $csv = null): ImportBatch
    {
        $this->upload($csv);
        $batch = ImportBatch::query()->latest('id')->firstOrFail();
        $this->actingAs($this->makeUser(UserRole::Admin))->post("/admin/imports/{$batch->id}/commit");

        return $batch->fresh();
    }

    public function test_upload_stages_and_reports_without_creating_any_record(): void
    {
        $this->upload()->assertSessionHasNoErrors()->assertRedirect();

        $batch = ImportBatch::query()->firstOrFail();

        $this->assertSame('staged', $batch->status);
        $this->assertSame(0, Place::count());
        $this->assertSame(0, Hotel::count());
        $this->assertSame(4, $batch->totals['rows']);
        $this->assertSame(1, $batch->totals['error']);
        $this->assertSame(2, $batch->totals['places']);
        $this->assertSame(1, $batch->totals['hotels']);
        $this->assertSame(4, $batch->rows()->count());
    }

    public function test_the_preview_page_shows_issues_notes_and_what_will_happen(): void
    {
        $this->upload();
        $batch = ImportBatch::query()->firstOrFail();

        $this->actingAs($this->makeUser(UserRole::Admin))->get("/admin/imports/{$batch->id}")
            ->assertOk()
            ->assertSee('Tempat Rusak')
            ->assertSee('belum dipetakan')
            ->assertSee('Akan dibuat')
            ->assertSee('Tidak ada kolom sumber');
    }

    public function test_the_error_filter_lists_only_error_rows(): void
    {
        $this->upload();
        $batch = ImportBatch::query()->firstOrFail();

        $this->actingAs($this->makeUser(UserRole::Admin))->get("/admin/imports/{$batch->id}?status=error")
            ->assertOk()->assertSee('Tempat Rusak')->assertDontSee('Pura Uji');
    }

    public function test_only_admins_can_use_imports(): void
    {
        $this->get('/admin/imports')->assertRedirect(route('login'));
        $this->actingAs($this->makeUser())->get('/admin/imports')->assertForbidden();

        $this->upload(null, UserRole::User)->assertForbidden();
        $this->assertSame(0, ImportBatch::count());
    }

    public function test_wrong_file_type_and_missing_columns_are_rejected_with_a_message(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)->post('/admin/imports', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');

        $this->actingAs($admin)->post('/admin/imports', ['file' => UploadedFile::fake()->createWithContent('x.csv', "id,name\n1,A\n")])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ImportBatch::count());
    }

    public function test_commit_creates_drafts_only_with_unknown_verification(): void
    {
        $batch = $this->stageAndCommit();

        $this->assertSame('committed', $batch->status);
        $this->assertSame(2, Place::count());
        $this->assertSame(1, Hotel::count());

        $place = Place::query()->where('external_id', 'combined150:1')->firstOrFail();
        $this->assertSame(PublishStatus::Draft, $place->status);
        $this->assertSame(VerificationStatus::Unknown, $place->verification_status);
        $this->assertNull($place->source_url);
        $this->assertNull($place->verified_at);
        $this->assertSame('Ubud', $place->area_label);
        $this->assertSame('gianyar', $place->region->slug);
        $this->assertSame(['wisata'], $place->categories->pluck('slug')->all());
        $this->assertSame(['pura-sejarah'], $place->tags->pluck('slug')->all());
        $this->assertNotNull($place->tags->first()->description);

        $hotel = Hotel::query()->firstOrFail();
        $this->assertSame(PublishStatus::Draft, $hotel->status);
        $this->assertSame(1500000, $hotel->price_min);
        $this->assertSame(PriceBasis::Estimate, $hotel->price_basis);
        $this->assertSame(VerificationStatus::Unknown, $hotel->price_status);
        $this->assertSame('Canggu', $hotel->area_label);

        $this->assertFalse(Place::where('name', 'Tempat Rusak')->exists());
    }

    public function test_imported_drafts_cannot_be_published_and_are_invisible_to_the_public(): void
    {
        $this->stageAndCommit();
        $place = Place::query()->firstOrFail();
        $hotel = Hotel::query()->firstOrFail();
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)->post("/admin/places/{$place->id}/status", ['to' => 'published'])->assertSessionHasErrors('publish');
        $this->actingAs($admin)->post("/admin/hotels/{$hotel->id}/status", ['to' => 'published'])->assertSessionHasErrors('publish');

        $this->get('/places')->assertOk()->assertDontSee('Pura Uji');
        $this->get('/hotels')->assertOk()->assertDontSee('Hotel Uji');
        $this->get('/places/'.$place->slug)->assertNotFound();
    }

    public function test_committing_twice_is_rejected_and_reimporting_creates_nothing_new(): void
    {
        $first = $this->stageAndCommit();

        $this->actingAs($this->makeUser(UserRole::Admin))->post("/admin/imports/{$first->id}/commit")
            ->assertSessionHasErrors('import');

        $second = $this->stageAndCommit();

        $this->assertSame(3, $second->totals['skipped']);
        $this->assertSame(2, Place::count());
        $this->assertSame(1, Hotel::count());
    }

    public function test_regular_users_cannot_commit_and_admins_cannot_roll_back(): void
    {
        $this->upload();
        $batch = ImportBatch::query()->firstOrFail();

        $this->actingAs($this->makeUser())->post("/admin/imports/{$batch->id}/commit")->assertForbidden();

        $this->actingAs($this->makeUser(UserRole::Admin))->post("/admin/imports/{$batch->id}/commit");
        $this->actingAs($this->makeUser(UserRole::Admin))->post("/admin/imports/{$batch->id}/rollback")->assertForbidden();
        $this->assertSame(3, Place::count() + Hotel::count());
    }

    public function test_rollback_removes_untouched_drafts_and_keeps_what_editors_changed(): void
    {
        $batch = $this->stageAndCommit();
        $edited = Place::query()->where('external_id', 'combined150:1')->firstOrFail();

        $this->travel(10)->seconds();
        $edited->forceFill(['summary' => 'Ditulis ulang oleh editor.'])->save();

        $this->actingAs($this->makeUser(UserRole::SuperAdmin))->post("/admin/imports/{$batch->id}/rollback")
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('rolled_back', $batch->fresh()->status);
        $this->assertSame(1, Place::withTrashed()->count());
        $this->assertTrue(Place::where('external_id', 'combined150:1')->exists());
        $this->assertSame(0, Hotel::withTrashed()->count());
        $this->assertTrue(AuditLog::where('action', 'import_batch.rolled_back')->exists());
    }

    public function test_rollback_keeps_records_that_are_no_longer_drafts_or_have_photos(): void
    {
        $batch = $this->stageAndCommit();
        $place = Place::query()->where('external_id', 'combined150:3')->firstOrFail();
        \App\Models\Media::factory()->for($place, 'mediable')->create();

        $this->actingAs($this->makeUser(UserRole::SuperAdmin))->post("/admin/imports/{$batch->id}/rollback");

        $this->assertTrue(Place::whereKey($place->id)->exists());
    }

    public function test_after_a_rollback_the_same_file_can_be_imported_again(): void
    {
        $batch = $this->stageAndCommit();
        $this->actingAs($this->makeUser(UserRole::SuperAdmin))->post("/admin/imports/{$batch->id}/rollback");
        $this->assertSame(0, Place::withTrashed()->count());

        $again = $this->stageAndCommit();

        $this->assertSame(2, Place::count());
        $this->assertSame(1, Hotel::count());
        $this->assertSame(0, $again->totals['skipped']);
    }

    public function test_slugs_that_already_exist_are_adjusted_at_staging(): void
    {
        Place::factory()->create(['slug' => 'pura-uji']);

        $this->stageAndCommit();

        $this->assertTrue(Place::where('external_id', 'combined150:1')->where('slug', 'pura-uji-ubud')->exists());
    }

    public function test_staging_and_commit_are_audited_with_the_actor(): void
    {
        $this->stageAndCommit();

        $staged = AuditLog::where('action', 'import_batch.staged')->firstOrFail();
        $committed = AuditLog::where('action', 'import_batch.committed')->firstOrFail();

        $this->assertNotNull($staged->actor_user_id);
        $this->assertSame(2, $committed->after_json['created_places']);
        $this->assertSame(1, $committed->after_json['created_hotels']);
    }

    public function test_an_edited_area_label_is_saved_by_the_admin_form(): void
    {
        $place = Place::factory()->create();

        $this->actingAs($this->makeUser(UserRole::Admin))->put("/admin/places/{$place->id}", $this->placePayload([
            'region_id' => $place->region_id, 'name' => $place->name, 'slug' => $place->slug,
            'summary' => $place->summary, 'lat' => (string) $place->lat, 'lng' => (string) $place->lng,
            'area_label' => 'Ubud',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Ubud', $place->fresh()->area_label);
    }

    private function csvV2(): string
    {
        $header = ['id', 'name', 'category', 'kabupaten', 'location_area', 'latitude', 'longitude', 'price_min', 'price_max', 'price_source', 'theme', 'hotel_type', 'source_url', 'source_date', 'summary', 'note'];
        $rows = [
            ['B1', 'Air Terjun Uji', 'Wisata Alam', 'Gianyar', 'Gianyar', '-8.5000000', '115.2000000', '', '', '', 'Alam', '', 'https://www.google.com/maps/place/Air+Terjun+Uji/data=!4m7', '', '', 'Sumber B'],
            ['A1', 'Kafe Uji', 'Kafe', 'Gianyar', 'Ubud', '-8.5100000', '115.2600000', '', '', '', 'Kopi', '', '', '2026-07-21', '', 'Sumber A'],
            ['C9', 'Villa Uji', 'Hotel', 'Badung', 'Canggu', '-8.6500000', '115.1300000', '759033', '', 'Traveloka', '', 'villa', '', '', '', 'Harga Traveloka'],
        ];

        $stream = fopen('php://memory', 'r+');
        fputcsv($stream, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);

        return stream_get_contents($stream);
    }

    public function test_v2_import_keeps_source_links_prices_types_and_never_invents_a_summary(): void
    {
        $this->stageAndCommit($this->csvV2());

        $place = Place::query()->where('external_id', 'combined150:B1')->firstOrFail();
        $this->assertSame('', $place->summary);
        $this->assertStringStartsWith('https://www.google.com/maps/place/', $place->source_url);
        $this->assertNull($place->verified_at);
        $this->assertSame(VerificationStatus::Unknown, $place->verification_status);
        $this->assertSame(['wisata-alam'], $place->categories->pluck('slug')->all());
        $this->assertSame(['alam'], $place->tags->pluck('slug')->all());

        $cafe = Place::query()->where('external_id', 'combined150:A1')->firstOrFail();
        $this->assertSame('2026-07-21', $cafe->source_updated_at->toDateString());
        $this->assertSame('Ubud', $cafe->area_label);
        $this->assertNull($cafe->source_url);

        $hotel = Hotel::query()->firstOrFail();
        $this->assertSame(\App\Enums\HotelType::Villa, $hotel->type);
        $this->assertSame(PriceBasis::Provider, $hotel->price_basis);
        $this->assertSame(759033, $hotel->price_min);
        $this->assertSame(VerificationStatus::Unknown, $hotel->price_status);
    }

    public function test_v2_places_stay_unpublishable_until_an_editor_writes_the_summary_and_verifies(): void
    {
        $this->stageAndCommit($this->csvV2());
        $place = Place::query()->where('external_id', 'combined150:B1')->firstOrFail();

        $response = $this->actingAs($this->makeUser(UserRole::Admin))->post("/admin/places/{$place->id}/status", ['to' => 'published']);

        $response->assertSessionHasErrors('publish');
        $messages = implode(' ', session('errors')->get('publish'));
        $this->assertStringContainsString('Ringkasan belum diisi', $messages);
        $this->assertStringContainsString('Tanggal verifikasi belum diisi', $messages);
        $this->assertStringNotContainsString('Tautan sumber belum diisi', $messages);
    }

    private function csvResearched(): string
    {
        $header = ['id', 'name', 'category', 'kabupaten', 'location_area', 'latitude', 'longitude', 'source_url', 'verified_at', 'verification_status', 'summary'];
        $rows = [
            ['B1', 'Museum Uji', 'Wisata Budaya', 'Gianyar', 'Mengwi', '-8.5000000', '115.2000000', 'https://example.test/museum', '2026-10-27', 'needs_review', 'Museum kecil di sisi barat sebuah pura, menyimpan perlengkapan upacara.'],
            ['B2', 'Tempat Belum Diriset', 'Wisata Alam', 'Gianyar', 'Gianyar', '-8.5100000', '115.2100000', '', '', '', ''],
        ];

        $stream = fopen('php://memory', 'r+');
        fputcsv($stream, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);

        return stream_get_contents($stream);
    }

    public function test_a_researched_row_is_imported_ready_to_publish_while_an_unresearched_row_stays_blocked(): void
    {
        $this->stageAndCommit($this->csvResearched());

        $researched = Place::query()->where('external_id', 'combined150:B1')->firstOrFail();
        $this->assertSame(VerificationStatus::NeedsReview, $researched->verification_status);
        $this->assertSame('2026-10-27', $researched->verified_at->toDateString());
        $this->assertSame('Mengwi', $researched->area_label);

        $other = Place::query()->where('external_id', 'combined150:B2')->firstOrFail();
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)->post("/admin/places/{$researched->id}/status", ['to' => 'published'])->assertSessionHasNoErrors();
        $this->assertSame(PublishStatus::Published, $researched->fresh()->status);

        $this->actingAs($admin)->post("/admin/places/{$other->id}/status", ['to' => 'published'])->assertSessionHasErrors('publish');
        $this->assertSame(PublishStatus::Draft, $other->fresh()->status);
    }

    public function test_a_published_researched_place_shows_its_needs_review_status_publicly(): void
    {
        $this->stageAndCommit($this->csvResearched());
        $place = Place::query()->where('external_id', 'combined150:B1')->firstOrFail();
        $this->actingAs($this->makeUser(UserRole::Admin))->post("/admin/places/{$place->id}/status", ['to' => 'published']);

        $this->get('/places/'.$place->slug)->assertOk()
            ->assertSee('Museum kecil di sisi barat sebuah pura')
            ->assertSee('Perlu ditinjau');
    }
}
