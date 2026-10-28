<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanImportedCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPLATE = 'Menawarkan pengalaman menginap yang nyaman dengan akses strategis ke berbagai atraksi lokal di Bali.';

    private function templated(int $i, array $extra = []): Place
    {
        return Place::factory()->published()->create([
            'name' => "Tempat {$i}",
            'external_id' => "combined150:T{$i}",
            'summary' => "Tempat {$i} adalah wisata alam di kawasan Ubud. ".self::TEMPLATE,
            'source_url' => 'https://www.google.com/search?q=Tempat%20'.$i,
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ] + $extra);
    }

    public function test_the_default_run_is_a_simulation_that_changes_nothing(): void
    {
        foreach (range(1, 6) as $i) {
            $this->templated($i);
        }

        $this->artisan('archive:clean-imported')
            ->expectsOutputToContain('SIMULASI')
            ->expectsOutputToContain('Ringkasan templat dikosongkan: 6')
            ->assertExitCode(0);

        $this->assertSame(6, Place::where('status', 'published')->count());
        $this->assertSame(0, Place::where('summary', '')->count());
        $this->assertSame(0, AuditLog::where('action', 'place.cleaned')->count());
    }

    public function test_apply_clears_templated_content_and_takes_published_records_down(): void
    {
        foreach (range(1, 6) as $i) {
            $this->templated($i);
        }

        $this->artisan('archive:clean-imported', ['--apply' => true])
            ->expectsOutputToContain('Dibersihkan: 6')
            ->expectsOutputToContain('Ditarik dari publik menjadi draf: 6')
            ->assertExitCode(0);

        $place = Place::where('external_id', 'combined150:T1')->firstOrFail();
        $this->assertSame('', $place->summary);
        $this->assertNull($place->source_url);
        $this->assertNull($place->verified_at);
        $this->assertSame(VerificationStatus::Unknown, $place->verification_status);
        $this->assertSame(PublishStatus::Draft, $place->status);
        $this->assertSame(6, AuditLog::where('action', 'place.cleaned')->count());
    }

    public function test_genuine_and_non_imported_records_are_left_alone(): void
    {
        foreach (range(1, 6) as $i) {
            $this->templated($i);
        }
        $genuine = Place::factory()->published()->create([
            'external_id' => 'combined150:B64',
            'summary' => 'Museum di Mengwi yang menyimpan peralatan upacara Panca Yadnya umat Hindu Bali.',
            'source_url' => 'https://www.indonesia.travel/us-en/destination/bali-nusa-tenggara/bali/uptd-museum-yadnya',
        ]);
        $manual = Place::factory()->published()->create([
            'external_id' => null,
            'summary' => 'Tempat Manual adalah wisata alam di kawasan Ubud. '.self::TEMPLATE,
            'source_url' => 'https://www.google.com/search?q=manual',
        ]);

        $this->artisan('archive:clean-imported', ['--apply' => true])->assertExitCode(0);

        $this->assertSame(PublishStatus::Published, $genuine->fresh()->status);
        $this->assertNotSame('', $genuine->fresh()->summary);
        $this->assertSame(PublishStatus::Published, $manual->fresh()->status);
        $this->assertStringContainsString('google.com/search', $manual->fresh()->source_url);
    }

    public function test_search_links_alone_are_removed_even_when_the_summary_is_genuine(): void
    {
        $hotel = Hotel::factory()->published()->create([
            'external_id' => 'combined150:C1',
            'summary' => 'Hotel kecil dengan dua puluh kamar di tepi pantai Sanur.',
            'source_url' => 'https://www.google.com/search?q=hotel+official+website',
        ]);

        $this->artisan('archive:clean-imported', ['--apply' => true])->expectsOutputToContain('Tautan pencarian dibuang: 1')->assertExitCode(0);

        $fresh = $hotel->fresh();
        $this->assertNull($fresh->source_url);
        $this->assertSame('Hotel kecil dengan dua puluh kamar di tepi pantai Sanur.', $fresh->summary);
        $this->assertSame(PublishStatus::Draft, $fresh->status);
        $this->assertNull($fresh->verified_at);
    }
}
