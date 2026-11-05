<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChatRequest;
use App\Services\Chat\ChatException;
use App\Services\Chat\ChatOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    public static function enabled(): bool
    {
        return (bool) config('chatbot.enabled') && filled(config('services.gemini.key'));
    }

    public function __invoke(ChatRequest $request, ChatOrchestrator $chat): JsonResponse
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

        try {
            return response()->json($chat->ask($request->message(), $request->history()));
        } catch (ChatException $e) {
            return $this->failure($e);
        }
    }

    private function failure(ChatException $e): JsonResponse
    {
        return response()->json(['message' => $e->userMessage(), 'code' => $e->reason], $e->status);
    }
}
