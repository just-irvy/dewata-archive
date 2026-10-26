<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        foreach (['/', '/about', '/places', '/regions', '/hotels', '/planner'] as $uri) {
            $this->get($uri)->assertOk()->assertSee('The Dewata Archive');
        }
    }

    public function test_pages_declare_language_and_skip_link(): void
    {
        $this->get('/')
            ->assertSee('<html lang="', false)
            ->assertSee('href="#konten-utama"', false);
    }

    public function test_account_requires_login(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }
}
