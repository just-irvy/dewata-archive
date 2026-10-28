<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            // Pilihan editor untuk "Selected file" di beranda. Beranda memakai yang terbaru
            // di antara tempat terbit. Tanpa kolom ini, sorotan hanya bisa ditebak dari data.
            $table->timestamp('featured_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('featured_at');
        });
    }
};
