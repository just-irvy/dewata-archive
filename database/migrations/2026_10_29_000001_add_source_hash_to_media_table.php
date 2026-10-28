<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Hash berkas asli dari impor foto massal, agar menjalankan impor dua kali tidak menggandakan foto.
            $table->char('source_hash', 64)->nullable()->index()->after('license_note');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['source_hash']);
            $table->dropColumn('source_hash');
        });
    }
};
