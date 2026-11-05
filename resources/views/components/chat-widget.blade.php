{{--
| Widget asisten chat (Fase 6). Hanya dirender bila chatbot aktif dan kunci Gemini terpasang.
| Percakapan hidup di memori halaman saja: tidak ada localStorage, tidak ada cookie chat (FILE 05).
| Teks jawaban ditampilkan dengan x-text (bukan HTML), jadi aman dari XSS.
--}}
@if (\App\Http\Controllers\Site\ChatController::enabled())
    @php
        $chatConfig = [
            'endpoint' => route('chat.ask'),
            'greeting' => config('chatbot.greeting'),
            'suggestions' => config('chatbot.suggestions'),
            'maxChars' => (int) config('chatbot.max_message_chars'),
            'maxHistory' => (int) config('chatbot.max_history_messages'),
        ];
    @endphp

    <div x-data="archiveChat(@js($chatConfig))" x-cloak
         @keydown.escape.window="close()"
         class="fixed bottom-4 right-4 z-40 flex flex-col items-end gap-3 print:hidden">

        <section x-show="open" id="archive-chat-panel" role="dialog" aria-labelledby="archive-chat-title"
                 class="flex max-h-[min(40rem,calc(100dvh-6.5rem))] w-[min(26rem,calc(100vw-2rem))] flex-col border-2 border-ink bg-surface shadow-xl">

            <header class="flex items-start justify-between gap-3 border-b-[3px] border-double border-ink px-4 py-3">
                <div>
                    <p class="meta">Meja informasi</p>
                    <h2 id="archive-chat-title" class="font-display text-xl font-bold">Tanya Arsip</h2>
                </div>
                <button type="button" class="btn btn-link -mr-2 -mt-1" @click="close()" aria-label="Tutup asisten">Tutup</button>
            </header>

            <p class="typewriter border-b border-ink/20 px-4 py-2">
                Menjawab dari katalog terbit. Harga hotel per kamar per malam, bukan jaminan ketersediaan. Cek ulang sebelum menginap.
            </p>

            <div x-ref="log" role="log" aria-live="polite" aria-relevant="additions" class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                <template x-for="(m, i) in messages" :key="i">
                    <div :class="m.role === 'user' ? 'flex justify-end' : ''">
                        <div :class="m.role === 'user' ? 'max-w-[85%] bg-ink px-3 py-2 text-paper' : 'w-full'">
                            <p class="whitespace-pre-line text-[0.9375rem]" x-text="m.text"></p>

                            <template x-for="note in (m.notes || [])" :key="note">
                                <p class="mt-2 border-l-4 border-brass-deep bg-paper px-3 py-2 text-sm font-semibold" x-text="note"></p>
                            </template>

                            <ul x-show="(m.cards || []).length" class="mt-3 space-y-2">
                                <template x-for="c in (m.cards || [])" :key="c.key">
                                    <li>
                                        <a :href="c.url" class="flex gap-3 border border-ink/30 bg-paper p-3 hover:border-rust focus-visible:border-rust">
                                            <template x-if="c.image">
                                                <img :src="c.image" :alt="c.image_alt || ''" loading="lazy" class="h-16 w-16 shrink-0 object-cover">
                                            </template>
                                            <span class="min-w-0">
                                                <span class="meta block" x-text="c.label"></span>
                                                <span class="block font-display text-base font-bold leading-snug" x-text="c.title"></span>
                                                <span class="block text-sm text-ink-soft" x-show="c.subtitle" x-text="c.subtitle"></span>
                                                <span class="mt-1 block text-sm font-semibold" x-show="c.price" x-text="c.price"></span>
                                                <span class="typewriter block" x-show="c.meta" x-text="c.meta"></span>
                                                <span class="mt-1 block text-sm font-semibold text-oxblood" x-show="c.note" x-text="c.note"></span>
                                            </span>
                                        </a>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>

                <ul x-show="messages.length === 1 && !busy" class="flex flex-wrap gap-2" aria-label="Contoh pertanyaan">
                    <template x-for="s in config.suggestions" :key="s">
                        <li><button type="button" class="btn btn-secondary min-h-11 text-left text-sm" @click="ask(s)" x-text="s"></button></li>
                    </template>
                </ul>

                <p x-show="busy" class="typewriter" role="status">Mencari di arsip…</p>
                <p x-show="error" x-text="error" role="alert" class="border-l-4 border-oxblood bg-paper px-3 py-2 text-sm font-semibold text-oxblood"></p>
            </div>

            <form @submit.prevent="send()" class="border-t border-ink/20 px-4 py-3">
                <label for="archive-chat-input" class="sr-only">Pertanyaan Anda</label>
                <textarea id="archive-chat-input" x-ref="input" x-model="draft" rows="2" :maxlength="config.maxChars"
                          @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }"
                          placeholder="Contoh: hotel di Ubud di bawah 800 ribu per malam"
                          class="w-full resize-none border border-ink bg-paper px-3 py-2 text-[0.9375rem] focus-visible:outline-2"></textarea>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <span class="typewriter" x-text="draft.length + '/' + config.maxChars" aria-hidden="true"></span>
                    <button type="submit" class="btn btn-primary" :disabled="busy || draft.trim().length < 2">Kirim</button>
                </div>
                @if (config('archive.features.planner'))
                    <p class="mt-2 text-sm">Butuh rencana per hari? <a class="font-semibold text-rust underline" href="{{ route('planner.index') }}">Buka Planner</a></p>
                @endif
            </form>
        </section>

        <button type="button" class="btn btn-primary shadow-lg" @click="toggle()"
                :aria-expanded="open.toString()" aria-controls="archive-chat-panel">
            <span x-text="open ? 'Tutup asisten' : 'Tanya Arsip'"></span>
        </button>
    </div>
@endif
