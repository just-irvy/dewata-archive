{{--
| Asisten chat (Fase 6), tampilan C2 "Laci Arsip".
| Bukan jendela mengambang di pojok: bilah tipis yang selalu ada di tepi bawah halaman, dan jendela
| setengah layar yang naik dari bawah saat pengunjung menekan Tanya atau Buka.
| Hanya dirender bila chatbot aktif dan kunci Gemini terpasang. Teks jawaban dirender dengan x-text (bukan HTML).
| Tamu: percakapan hidup di memori halaman saja. Pengguna login: dimuat dari percakapan tersimpan.
|
| bar: "all" (bawaan) bilah tampil di semua lebar layar, "desktop" hanya dari md ke atas (halaman yang
| sudah punya bilah aksi sendiri di ponsel, misalnya editor planner), "none" tanpa bilah.
--}}
@props(['bar' => 'all'])

@if (\App\Http\Controllers\Site\ChatController::enabled())
    @php
        $chatConfig = [
            'endpoint' => route('chat.ask'),
            'greeting' => config('chatbot.greeting'),
            'suggestions' => config('chatbot.suggestions'),
            'maxChars' => (int) config('chatbot.max_message_chars'),
            'maxHistory' => (int) config('chatbot.max_history_messages'),
            'loggedIn' => auth()->check(),
            'history' => auth()->check() ? app(\App\Services\Chat\ChatHistory::class)->forWidget(auth()->user()) : [],
            'clearEndpoint' => auth()->check() ? route('chat.clear') : null,
        ];
    @endphp

    <div x-data="archiveChat(@js($chatConfig))" x-cloak
         @keydown.escape.window="close()"
         @archive-chat-ask.window="openWith($event.detail)"
         data-chat-bar="{{ $bar }}"
         class="print:hidden">

        @if ($bar !== 'none')
            {{-- Pengganjal agar footer tidak tertutup bilah. --}}
            <div aria-hidden="true" :style="{ height: barHeight + 'px' }" class="{{ $bar === 'desktop' ? 'hidden md:block' : '' }}"></div>

            {{--
            | Bilah bawah: selalu tampil dan tidak berubah saat jendela terbuka. Kolom di dalamnya adalah
            | satu-satunya tempat mengetik: tertutup, tombolnya "Buka"; terbuka, tombolnya "Kirim".
            --}}
            <div x-ref="bar" class="chat-bar {{ $bar === 'desktop' ? 'hidden md:block' : '' }}">
                <div class="mx-auto flex w-full max-w-[120rem] items-center gap-3 px-4 py-2 sm:px-8 md:gap-6 lg:px-14">
                    <span class="hidden font-display text-lg font-bold sm:block">Tanya Arsip</span>
                    <span class="hidden flex-1 text-sm text-ink-soft lg:block">Tempat, hotel, atau budget per malam, dijawab dari katalog terbit.</span>

                    <form @submit.prevent="submitBar()" class="flex flex-1 items-center gap-2 rounded-full border border-ink/35 bg-white py-1 pl-4 pr-1 lg:flex-none lg:basis-[28rem]">
                        <label for="archive-chat-input" class="sr-only">Pertanyaan Anda</label>
                        <input id="archive-chat-input" x-ref="input" x-model="draft" type="text" :maxlength="config.maxChars" placeholder="Tanya tempat atau hotel" autocomplete="off" class="min-h-9 w-full min-w-0 flex-1 bg-transparent text-sm outline-none">
                        <span class="typewriter hidden text-[0.6875rem] text-ink-soft md:block" x-show="open" x-text="draft.length + '/' + config.maxChars" aria-hidden="true"></span>
                        <button type="submit" class="min-h-9 shrink-0 rounded-full bg-moss px-4 text-sm font-semibold text-paper hover:bg-ink disabled:opacity-50" :disabled="busy || (open && draft.trim().length < 2)" x-text="open ? 'Kirim' : 'Buka'"></button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Latar diredupkan tipis saat jendela terbuka. Klik di luar jendela menutupnya; bilah tetap bisa dipakai. --}}
        <div x-show="open" x-transition.opacity.duration.200ms @click="close()" aria-hidden="true" class="chat-backdrop"></div>

        {{-- Jendela percakapan: separuh kanan layar di desktop, naik dari bawah di ponsel. Bilah tetap diam. --}}
        <section id="archive-chat-panel" role="dialog" aria-labelledby="archive-chat-title" x-ref="panel"
                 :style="{ '--chat-bar-height': barHeight + 'px' }"
                 x-show="open" x-transition:enter-start="chat-drawer-enter-start" x-transition:leave-end="chat-drawer-enter-start"
                 class="chat-drawer">
            <div class="chat-drawer__inner">
                <div class="flex items-start justify-between gap-3 px-5 pt-4 sm:px-7">
                    <div>
                        <p class="typewriter text-[0.6875rem] uppercase tracking-[0.12em]">Meja informasi · The Dewata Archive</p>
                        <h2 id="archive-chat-title" class="font-display text-2xl font-bold leading-tight">Tanya Arsip</h2>
                    </div>
                    <div class="-mr-2 flex">
                        @auth
                            <button type="button" @click="startOver()" :disabled="busy" x-show="messages.length > 1" class="flex size-11 items-center justify-center text-ink-soft hover:text-ink" aria-label="Buka lembar percakapan baru" title="Lembar baru">
                                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 11v6"/><path d="M9 14h6"/></svg>
                            </button>
                        @endauth
                        <button type="button" @click="close()" class="flex size-11 items-center justify-center hover:text-rust" aria-label="Tutup asisten">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>
                </div>

                <div class="mx-5 mt-3 flex items-center justify-between gap-3 border-y border-ink/15 py-2 text-xs text-ink-soft sm:mx-7">
                    @auth
                        <span>Lembar ini tersimpan di akun Anda, {{ (int) config('chatbot.retention_days') }} hari</span>
                    @else
                        <span>Lembar tamu hilang saat pindah halaman. <a class="font-semibold text-rust underline" href="{{ route('login') }}">Masuk</a> untuk menyimpannya.</span>
                    @endauth
                    <span class="typewriter shrink-0">Jawaban dari katalog terbit</span>
                </div>

                <div x-ref="log" role="log" aria-live="polite" aria-relevant="additions" class="flex-1 space-y-6 overflow-y-auto px-5 py-5 sm:px-7">
                    <p x-show="messages.some((m) => m.restored)" class="typewriter text-[0.75rem]">Lembar sebelumnya. Kartu memakai data katalog terbaru; angka di teks jawaban lama bisa sudah berubah.</p>

                    <template x-for="(m, i) in messages" :key="i">
                        <div :class="m.role === 'user' ? 'flex flex-col items-end gap-1 text-right' : 'flex flex-col gap-2'">
                            <span class="typewriter text-[0.6875rem] uppercase tracking-[0.1em]" :class="m.role === 'user' ? 'text-ink-soft' : 'text-moss'" x-text="m.role === 'user' ? 'Anda' : 'Arsip'"></span>
                            {{-- Gelembung: pengguna di kanan berwarna pasir, arsip di kiri berwarna putih bergaris. --}}
                            <p class="max-w-[85%] whitespace-pre-line px-3.5 py-2.5 text-left text-[0.9375rem] leading-relaxed" :class="m.role === 'user' ? 'rounded-[14px_14px_2px_14px] bg-[#f0e8d8]' : 'rounded-[14px_14px_14px_2px] border border-ink/15 bg-white'" x-text="m.text"></p>

                            <template x-for="note in (m.notes || [])" :key="note">
                                <p class="max-w-[85%] text-xs font-semibold text-oxblood" x-text="note"></p>
                            </template>

                            <ul x-show="(m.cards || []).length" class="w-full">
                                <template x-for="c in (m.cards || [])" :key="c.key">
                                    <li>
                                        <a :href="c.url" class="flex gap-3.5 border-y border-ink/15 py-3.5 text-left hover:text-moss focus-visible:text-moss">
                                            <template x-if="c.image">
                                                <img :src="c.image" :alt="c.image_alt || ''" loading="lazy" class="size-20 shrink-0 rounded object-cover">
                                            </template>
                                            <span class="flex min-w-0 flex-col gap-0.5 leading-snug">
                                                <span class="typewriter text-[0.6875rem] uppercase tracking-[0.08em] text-ink-soft" x-text="[c.label, c.subtitle].filter(Boolean).join(' · ')"></span>
                                                <span class="font-display text-lg font-bold text-ink" x-text="c.title"></span>
                                                <span class="text-[0.9375rem] font-semibold text-ink" x-show="c.price" x-text="c.price"></span>
                                                <span class="text-xs text-ink-soft" x-show="c.meta" x-text="c.meta"></span>
                                                <span class="text-xs font-semibold text-oxblood" x-show="c.note" x-text="c.note"></span>
                                                <span class="mt-1 text-xs font-semibold text-moss" x-text="c.type === 'hotel' ? 'Buka catatan hotel' : 'Buka catatan tempat'"></span>
                                            </span>
                                        </a>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <ul x-show="messages.length === 1 && !busy" class="flex flex-wrap gap-2" aria-label="Contoh pertanyaan">
                        <template x-for="s in config.suggestions" :key="s">
                            <li><button type="button" class="min-h-11 rounded-full border border-ink/30 px-4 py-2 text-left text-sm hover:border-ink" @click="ask(s)" x-text="s"></button></li>
                        </template>
                    </ul>

                    <p x-show="busy" class="typewriter" role="status">Mencari di arsip…</p>
                    <p x-show="error" x-text="error" role="alert" class="text-sm font-semibold text-oxblood"></p>
                </div>

            </div>
        </section>
    </div>
@endif
