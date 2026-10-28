<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\TestCase;

class ApplyResearchCommandTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        Region::factory()->create(['slug' => 'gianyar', 'name' => 'Gianyar']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
        parent::tearDown();
    }

    private function csv(array $rows): string
    {
        $header = ['id', 'name', 'category', 'kabupaten', 'location_area', 'latitude', 'longitude', 'source_url', 'verified_at', 'verification_status', 'summary'];
        $path = sys_get_temp_dir().'/riset-'.uniqid().'.csv';
        $h = fopen($path, 'w');
        fputcsv($h, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($h, $row, ',', '"', '');
        }
        fclose($h);

        return $this->files[] = $path;
    }

    private function draft(string $externalId, array $extra = []): Place
    {
        return Place::factory()->create([
            'external_id' => $externalId,
            'summary' => '',
            'source_url' => null,
            'verified_at' => null,
            'verification_status' => VerificationStatus::Unknown,
        ] + $extra);
    }

    private function row(string $id, string $name, string $summary = 'Museum kecil yang menyimpan peralatan upacara umat Hindu Bali.', array $extra = []): array
    {
        return array_replace(
            [$id, $name, 'Wisata Budaya', 'Gianyar', 'Mengwi', '-8.5000000', '115.2000000', 'https://www.indonesia.travel/x', '2026-10-27', 'needs_review', $summary],
            $extra,
        );
    }

    public function test_the_default_run_is_a_simulation(): void
    {
        $place = $this->draft('combined150:B64');
        $file = $this->csv([$this->row('B64', 'Museum Yadnya')]);

        $this->artisan('archive:apply-research', ['file' => $file])
            ->expectsOutputToContain('SIMULASI')
            ->expectsOutputToContain('Akan diisi: 1')
            ->assertExitCode(0);

        $this->assertSame('', $place->fresh()->summary);
        $this->assertSame(0, AuditLog::where('action', 'place.research_applied')->count());
    }

    public function test_apply_fills_only_the_blank_fields_of_existing_drafts(): void
    {
        $place = $this->draft('combined150:B64', ['name' => 'Museum Yadnya']);
        $file = $this->csv([$this->row('B64', 'Museum Yadnya')]);

        $this->artisan('archive:apply-research', ['file' => $file, '--apply' => true])
            ->expectsOutputToContain('Diisi: 1')
            ->assertExitCode(0);

        $fresh = $place->fresh();
        $this->assertSame('Museum kecil yang menyimpan peralatan upacara umat Hindu Bali.', $fresh->summary);
        $this->assertSame('https://www.indonesia.travel/x', $fresh->source_url);
        $this->assertSame('2026-10-27', $fresh->verified_at->toDateString());
        $this->assertSame(VerificationStatus::NeedsReview, $fresh->verification_status);
        $this->assertSame('Mengwi', $fresh->area_label);
        $this->assertSame(1, AuditLog::where('action', 'place.research_applied')->count());
    }

    public function test_anything_an_editor_already_wrote_is_never_overwritten(): void
    {
        $place = $this->draft('combined150:B64', [
            'summary' => 'Ringkasan tulisan editor sendiri.',
            'source_url' => 'https://sumber-editor.test/a',
            'area_label' => 'Label editor',
        ]);
        $file = $this->csv([$this->row('B64', 'Museum Yadnya')]);

        $this->artisan('archive:apply-research', ['file' => $file, '--apply' => true])->assertExitCode(0);

        $fresh = $place->fresh();
        $this->assertSame('Ringkasan tulisan editor sendiri.', $fresh->summary);
        $this->assertSame('https://sumber-editor.test/a', $fresh->source_url);
        $this->assertSame('Label editor', $fresh->area_label);
        // Tanggal verifikasi masih kosong, jadi bagian itu boleh diisi.
        $this->assertSame(VerificationStatus::NeedsReview, $fresh->verification_status);
    }

    public function test_a_verification_that_an_editor_already_set_is_kept(): void
    {
        $place = $this->draft('combined150:B64', ['verified_at' => '2026-09-01', 'verification_status' => VerificationStatus::Verified]);
        $file = $this->csv([$this->row('B64', 'Museum Yadnya')]);

        $this->artisan('archive:apply-research', ['file' => $file, '--apply' => true])->assertExitCode(0);

        $this->assertSame(VerificationStatus::Verified, $place->fresh()->verification_status);
        $this->assertSame('2026-09-01', $place->fresh()->verified_at->toDateString());
    }

    public function test_published_and_unknown_records_are_skipped(): void
    {
        $published = Place::factory()->published()->create(['external_id' => 'combined150:B72', 'summary' => '']);
        $file = $this->csv([$this->row('B72', 'Penglipuran'), $this->row('B999', 'Tidak Ada')]);

        $this->artisan('archive:apply-research', ['file' => $file, '--apply' => true])
            ->expectsOutputToContain('tidak ada di database 1')
            ->expectsOutputToContain('bukan draf 1')
            ->assertExitCode(0);

        $this->assertSame('', $published->fresh()->summary);
    }

    public function test_templated_rows_and_search_links_are_rejected_here_as_well(): void
    {
        $rows = [];
        foreach (range(1, 8) as $i) {
            $this->draft("combined150:T{$i}", ['name' => "Tempat {$i}"]);
            $rows[] = $this->row("T{$i}", "Tempat {$i}", "Tempat {$i} adalah wisata budaya di Mengwi. Menawarkan pengalaman menginap yang nyaman dengan akses strategis ke atraksi lokal.", [7 => 'https://www.google.com/search?q=tempat']);
        }

        $this->artisan('archive:apply-research', ['file' => $this->csv($rows), '--apply' => true])->assertExitCode(0);

        $fresh = Place::where('external_id', 'combined150:T1')->firstOrFail();
        $this->assertSame('', $fresh->summary);
        $this->assertNull($fresh->source_url);
    }

    public function test_a_verified_claim_in_the_file_is_stored_only_as_needs_review(): void
    {
        $place = $this->draft('combined150:B64');
        $file = $this->csv([$this->row('B64', 'Museum Yadnya', 'Museum kecil yang menyimpan peralatan upacara umat Hindu Bali.', [9 => 'Verified'])]);

        $this->artisan('archive:apply-research', ['file' => $file, '--apply' => true])->assertExitCode(0);

        $this->assertSame(VerificationStatus::NeedsReview, $place->fresh()->verification_status);
    }

    public function test_a_filled_draft_can_then_be_published(): void
    {
        $place = $this->draft('combined150:B64', ['name' => 'Museum Yadnya']);
        $place->categories()->attach(Category::factory()->create());
        $file = $this->csv([$this->row('B64', 'Museum Yadnya')]);

        $this->artisan('archive:apply-research', ['file' => $file, '--apply' => true])->assertExitCode(0);

        $this->actingAs($this->makeUser(UserRole::Admin))->post("/admin/places/{$place->id}/status", ['to' => 'published'])
            ->assertSessionHasNoErrors();
        $this->assertSame(PublishStatus::Published, $place->fresh()->status);
    }

    public function test_hotels_are_filled_too(): void
    {
        $hotel = Hotel::factory()->create(['external_id' => 'combined150:C1', 'summary' => '', 'source_url' => null, 'verified_at' => null]);
        $file = $this->csv([['C1', 'Hotel Uji', 'Hotel', 'Gianyar', 'Ubud', '-8.5', '115.2', 'https://hotel.test/resmi', '2026-10-27', '', 'Hotel kecil dengan dua puluh kamar di tepi sawah.']]);

        $this->artisan('archive:apply-research', ['file' => $file, '--apply' => true])->assertExitCode(0);

        $fresh = $hotel->fresh();
        $this->assertSame('Hotel kecil dengan dua puluh kamar di tepi sawah.', $fresh->summary);
        $this->assertSame('https://hotel.test/resmi', $fresh->source_url);
        $this->assertSame('2026-10-27', $fresh->verified_at->toDateString());
    }

    public function test_a_missing_or_broken_file_fails_cleanly(): void
    {
        $this->artisan('archive:apply-research', ['file' => '/tidak/ada.csv'])->expectsOutputToContain('tidak ditemukan')->assertExitCode(1);

        $broken = sys_get_temp_dir().'/rusak-'.uniqid().'.csv';
        file_put_contents($broken, "id,name\n1,A\n");
        $this->files[] = $broken;
        $this->artisan('archive:apply-research', ['file' => $broken])->expectsOutputToContain('Kolom wajib')->assertExitCode(1);
    }
}
