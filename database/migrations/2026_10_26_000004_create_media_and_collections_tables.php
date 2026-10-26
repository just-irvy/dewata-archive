<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu tabel media untuk tempat dan hotel (polimorfik). Blueprint hanya mendefinisikan
        // place_media, padahal kartu hotel di dashboard memerlukan foto dengan hak pakai.
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable');
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->string('credit')->nullable();
            $table->string('license_note')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('dek', 300)->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('collection_places', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('editor_note')->nullable();
            $table->primary(['collection_id', 'place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_places');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('media');
    }
};
