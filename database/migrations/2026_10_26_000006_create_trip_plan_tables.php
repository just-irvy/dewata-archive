<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_plans', function (Blueprint $table) {
            $table->id();
            // Pemilik adalah user ATAU guest. Tepat satu terisi, ditegakkan oleh aplikasi.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('guest_token_hash', 64)->nullable()->unique();
            // Masa simpan draft guest. Kolom ini tidak ada di blueprint, tetapi blueprint
            // mewajibkan guest draft berumur terbatas.
            $table->timestamp('expires_at')->nullable()->index();

            $table->string('title');
            $table->string('creation_mode', 20);
            $table->date('start_date')->nullable();
            $table->unsignedTinyInteger('days_count');
            $table->foreignId('origin_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('pace', 20)->nullable();
            $table->string('transport_mode', 20)->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('planner_answers')->nullable();
            $table->string('recommendation_version', 20)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('trip_plan_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_number');
            $table->date('date')->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            // Rute harian memakai hotel sebagai origin (FILE 11), tetapi blueprint tidak
            // menyebut di mana hotel disimpan. Hotel bisa berbeda tiap hari.
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('return_to_hotel')->default(false);
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['trip_plan_id', 'sort_order']);
        });

        Schema::create('trip_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_plan_day_id')->constrained()->cascadeOnDelete();
            // Tempat yang sudah dipakai di rencana tidak boleh dihapus permanen. Gunakan status archived.
            $table->foreignId('place_id')->constrained()->restrictOnDelete();
            $table->string('item_source', 20);
            $table->string('recommendation_reason', 300)->nullable();
            $table->time('start_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('note')->nullable();
            // Snapshot leg dari item sebelumnya ke item ini. Patuhi ketentuan konten Google Maps.
            $table->unsignedInteger('route_distance_m')->nullable();
            $table->unsignedInteger('route_duration_s')->nullable();
            $table->timestamp('route_calculated_at')->nullable();
            $table->timestamps();

            $table->index(['trip_plan_day_id', 'sort_order']);
        });

        Schema::create('saved_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_places');
        Schema::dropIfExists('trip_plan_items');
        Schema::dropIfExists('trip_plan_days');
        Schema::dropIfExists('trip_plans');
    }
};
