<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChatRequest;
use App\Services\Chat\ChatException;
use App\Services\Chat\ChatHistory;
use App\Services\Chat\ChatOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    public static function enabled(): bool
    {
        return (bool) config('chatbot.enabled') && filled(config('services.gemini.key'));
    }

    public function __invoke(ChatRequest $request, ChatOrchestrator $chat, ChatHistory $stored): JsonResponse
    {
        abort_unless(self::enabled(), 404);

        // Beberapa putaran model bisa melewati batas waktu PHP bawaan (30 detik pada banyak php.ini).
        @set_time_limit(75);

        // Pengaman tagihan: batas total pertanyaan sehari untuk seluruh situs.
        // Tambah dulu lalu periksa hasilnya. increment() atomik, jadi permintaan serentak tidak bisa
        // sama-sama lolos di angka batas seperti pada pola baca-lalu-tambah.
        $counter = 'chatbot.asked.'.now()->toDateString();
        Cache::add($counter, 0, now()->addDay());

        if ((int) Cache::increment($counter) > (int) config('chatbot.rate.global_daily_cap')) {
            return $this->failure(new ChatException('cap', 503));
        }

        $user = $request->user();

        // Pengguna login: konteks dari percakapan tersimpan, riwayat kiriman browser diabaikan.
        // Tamu: riwayat dari browser (hanya memori halaman), tidak pernah disimpan.
        $history = $user !== null ? $stored->contextFor($user) : $request->history();

        try {
            $result = $chat->ask($request->message(), $history);
        } catch (ChatException $e) {
            return $this->failure($e);
        }

        if ($user !== null) {
            $stored->record($user, $request->message(), $result['answer'], array_column($result['cards'], 'key'), $result['notes']);
        }

        return response()->json($result + ['saved' => $user !== null]);
    }

    /** Menghapus percakapan tersimpan milik pengguna yang login ("Mulai baru"). */
    public function clear(ChatHistory $stored): JsonResponse
    {
        abort_unless(self::enabled(), 404);

        $stored->clear(request()->user());

        return response()->json(['cleared' => true]);
    }

    private function failure(ChatException $e): JsonResponse
    {
        return response()->json(['message' => $e->userMessage(), 'code' => $e->reason], $e->status);
    }
}
