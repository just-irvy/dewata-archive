<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            // hotel, resort, villa, guesthouse, boutique
            $table->string('type', 20);
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->text('summary');

            // Harga selalu per kamar per malam, dalam rupiah penuh. Null berarti tidak tersedia.
            $table->unsignedBigInteger('price_min')->nullable();
            $table->unsignedBigInteger('price_max')->nullable();
            $table->char('currency', 3)->default('IDR');
            // curated, provider, estimate. Null bila harga tidak ada.
            $table->string('price_basis', 20)->nullable();
            $table->string('price_status', 20)->default('unknown');
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'region_id']);
            $table->index(['status', 'price_min']);
        });

        Schema::create('hotel_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_min')->nullable();
            $table->unsignedBigInteger('price_max')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->string('price_basis', 20)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('checked_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_price_snapshots');
        Schema::dropIfExists('hotels');
    }
};
