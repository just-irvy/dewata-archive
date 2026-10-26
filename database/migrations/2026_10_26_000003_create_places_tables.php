<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->longText('body')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('address')->nullable();

            // Siklus publikasi dan status verifikasi adalah dua hal berbeda.
            $table->string('status', 20)->default('draft');
            $table->string('verification_status', 20)->default('unknown');
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('published_at')->nullable();

            // Kolom dataset (FILE 16) yang tidak ada di tabel FILE 13.
            $table->string('external_id', 100)->nullable()->index();
            $table->string('attribution')->nullable();
            $table->unsignedSmallInteger('typical_visit_minutes')->nullable();
            $table->string('price_info')->nullable();

            // Data yang bisa berubah memiliki status sendiri. Tanpa sumber, nilainya unknown.
            $table->json('opening_hours')->nullable();
            $table->string('opening_hours_status', 20)->default('unknown');
            $table->json('accessibility')->nullable();
            $table->string('accessibility_status', 20)->default('unknown');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'region_id']);
            $table->index(['lat', 'lng']);
        });

        Schema::create('place_category', function (Blueprint $table) {
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['place_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('place_tag', function (Blueprint $table) {
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['place_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_tag');
        Schema::dropIfExists('place_category');
        Schema::dropIfExists('places');
    }
};
