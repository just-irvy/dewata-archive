<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            // Nama area populer (Ubud, Canggu) yang dipakai pelancong. Wilayah administratif tetap
            // ada di region_id, sehingga batas kabupaten dan label pemasaran tidak bercampur.
            $table->string('area_label', 100)->nullable()->after('region_id');
            // Idempotensi impor: satu external_id tidak boleh dibuat dua kali.
            $table->unique('external_id');
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->string('area_label', 100)->nullable()->after('region_id');
            $table->string('external_id', 100)->nullable()->unique()->after('slug');
            $table->string('attribution')->nullable()->after('verified_at');
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 60);
            $table->string('original_filename');
            $table->char('file_hash', 64)->index();
            // staged: sudah diparse dan menunggu persetujuan. committed: sudah dibuat sebagai draf.
            $table->string('status', 20)->default('staged')->index();
            $table->json('totals')->nullable();
            $table->json('notes')->nullable();
            $table->foreignId('committed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('external_id', 120)->nullable()->index();
            $table->string('entity', 10)->nullable();
            // valid, warning, error
            $table->string('status', 10);
            // create, skip. Berubah menjadi created atau rolled_back setelah commit dan rollback.
            $table->string('action', 12)->default('create');
            $table->json('payload');
            $table->json('issues');
            $table->unsignedBigInteger('record_id')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn(['area_label', 'external_id', 'attribution']);
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn('area_label');
        });
    }
};
