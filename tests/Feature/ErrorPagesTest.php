<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_unknown_urls_show_the_indonesian_not_found_page(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Jelajahi arsip');
    }
}
