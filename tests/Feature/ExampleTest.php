<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Beranda membaca database, jadi tes ini butuh tabel. Tanpa RefreshDatabase tes ini gagal pada SQLite in-memory.
    use RefreshDatabase;
    use WithoutViteAssets;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertStatus(200);
    }
}
