<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('region_type', 20)->default('administrative');
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['region_type', 'is_published']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon_key', 40)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tag_type', 20)->default('theme');
            // Definisi tema dipakai di wizard minat agar "cultural heritage" tidak
            // disamakan dengan "instagrammable".
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('tag_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('regions');
    }
};
