<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tamu boleh bertanya (FILE 05). Pembatasan lewat rate limiter.
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:'.config('chatbot.max_message_chars')],
            'history' => ['sometimes', 'array', 'max:'.(config('chatbot.max_history_messages') * 2)],
            'history.*.role' => ['required', 'in:user,model'],
            'history.*.text' => ['required', 'string', 'max:'.config('chatbot.max_history_chars') * 2],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Tulis pertanyaan Anda dulu.',
            'message.min' => 'Pertanyaan terlalu pendek.',
            'message.max' => 'Pertanyaan terlalu panjang. Ringkas hingga '.config('chatbot.max_message_chars').' karakter.',
        ];
    }

    public function message(): string
    {
        return trim(strip_tags((string) $this->validated('message')));
    }

    /**
     * Riwayat dipotong ke N pesan terakhir, tiap pesan dipendekkan, dan harus diawali pesan
     * pengguna. Riwayat berasal dari browser, jadi dianggap tidak tepercaya: ia hanya konteks,
     * tidak pernah memberi izin tambahan (izin dijaga ChatTools).
     *
     * @return array<int, array{role: string, text: string}>
     */
    public function history(): array
    {
        $turns = collect($this->validated('history') ?? [])
            ->map(fn (array $t) => [
                'role' => $t['role'],
                'text' => Str::limit(trim(strip_tags($t['text'])), (int) config('chatbot.max_history_chars'), ''),
            ])
            ->filter(fn (array $t) => $t['text'] !== '')
            ->take(-(int) config('chatbot.max_history_messages'))
            ->values();

        while ($turns->isNotEmpty() && $turns->first()['role'] !== 'user') {
            $turns->shift();
        }

        return $turns->all();
    }
}
