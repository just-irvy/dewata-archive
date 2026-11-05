<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Percakapan asisten untuk pengguna yang login. Tamu tidak pernah disimpan.
        // Kartu disimpan sebagai kunci (place:slug, hotel:slug) dan dibangun ulang dari data terbaru saat dimuat.
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);
            $table->text('text');
            $table->json('card_keys')->nullable();
            $table->json('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
