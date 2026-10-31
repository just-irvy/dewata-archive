<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsArchiveData;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

/** Fase 5: header keamanan, cache halaman pribadi, lokal dan zona waktu, batas laju pencarian. */
class HardeningTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    public function test_private_pages_are_not_cached(): void
    {
        $response = $this->actingAs($this->makeUser())->get('/account')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_ui_is_indonesian_and_uses_bali_time_by_default(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('Asia/Makassar', config('app.timezone'));
        // String UI berasal dari lang/id. Tanpa lokal id, navigasi tampil sebagai kunci mentah.
        $this->get('/')->assertSee('Jelajah')->assertDontSee('ui.nav.explore');
    }

    public function test_public_search_is_rate_limited(): void
    {
        for ($i = 0; $i < 90; $i++) {
            $this->get('/places?q=a');
        }
        $this->get('/places?q=a')->assertStatus(429);
    }

    public function test_account_overview_lists_plans(): void
    {
        $user = $this->makeUser();
        $user->tripPlans()->create(['title' => 'Arsip di akun', 'creation_mode' => 'manual', 'days_count' => 2]);

        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Arsip di akun')->assertSee('Semua 1 arsip');
    }
}
