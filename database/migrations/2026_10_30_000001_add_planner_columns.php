<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fase 4. generation_notes menyimpan peringatan dan keterbatasan hasil rekomendasi
| (FILE 09: "jelaskan keterbatasan"), terpisah dari planner_answers yang hanya berisi
| jawaban pengguna. Index pada place_id mempercepat pengecekan "tempat dipakai di rencana".
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_plans', function (Blueprint $table) {
            $table->json('generation_notes')->nullable()->after('generated_at');
        });

        Schema::table('trip_plan_items', function (Blueprint $table) {
            $table->index('place_id');
        });
    }

    public function down(): void
    {
        Schema::table('trip_plan_items', function (Blueprint $table) {
            $table->dropIndex(['place_id']);
        });

        Schema::table('trip_plans', function (Blueprint $table) {
            $table->dropColumn('generation_notes');
        });
    }
};
