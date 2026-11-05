import { buildHistory, describeError, initialMessages } from './helpers.mjs';

const REQUEST_TIMEOUT_MS = 70000;

/**
 * Komponen Alpine untuk widget chat. Percakapan hanya ada di memori halaman (tidak ada
 * localStorage atau sessionStorage). Menutup tab menghapus percakapan.
 */
export function registerChat(Alpine) {
    Alpine.data('archiveChat', (config) => ({
        config,
        open: false,
        busy: false,
        error: null,
        draft: '',
        messages: initialMessages(config.greeting, config.history),
        barHeight: 0,

        init() {
            // Tinggi bilah diukur dari elemennya (0 bila bilah tidak tampil), lalu dipakai jendela dan pengganjal.
            const bar = this.$refs.bar;
            if (!bar) return;
            const measure = () => { this.barHeight = bar.offsetHeight; };
            measure();
            if (typeof ResizeObserver !== 'undefined') new ResizeObserver(measure).observe(bar);
            window.addEventListener('resize', measure);
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            this.open = true;
            this.scrollDown();
            this.$nextTick(() => this.$refs.input?.focus());
        },

        close() {
            if (!this.open) return;
            this.open = false;
        },

        // Pengguna login: hapus percakapan tersimpan di server, lalu mulai dari sapaan.
        async startOver() {
            if (this.busy || !this.config.clearEndpoint) return;
            this.busy = true;
            this.error = null;

            try {
                const response = await fetch(this.config.clearEndpoint, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });

                if (!response.ok) {
                    this.error = describeError(response.status, await response.json().catch(() => null));
                    return;
                }

                this.messages = initialMessages(this.config.greeting, []);
            } catch {
                this.error = describeError(0, null);
            } finally {
                this.busy = false;
                this.$nextTick(() => this.$refs.input?.focus());
            }
        },

        ask(text) {
            this.draft = text;
            this.send();
        },

        // Pertanyaan dari luar jendela (event archive-chat-ask, bilah bawah): buka jendela lalu kirim.
        openWith(text) {
            const question = typeof text === 'string' ? text.trim() : '';
            this.show();
            if (question.length >= 2) {
                this.draft = question;
                this.$nextTick(() => this.send());
            }
        },

        // Enter pada kolom bilah: tertutup berarti buka (dan kirim bila sudah ada teks), terbuka berarti kirim.
        submitBar() {
            this.open ? this.send() : this.openWith(this.draft);
        },

        scrollDown() {
            this.$nextTick(() => {
                const log = this.$refs.log;
                if (log) log.scrollTop = log.scrollHeight;
            });
        },

        async send() {
            const text = this.draft.trim();
            if (this.busy || text.length < 2) return;

            // Pengguna login tidak mengirim riwayat: server memakai percakapan tersimpan.
            const history = this.config.loggedIn ? [] : buildHistory(this.messages, this.config.maxHistory);

            this.messages.push({ role: 'user', text });
            this.draft = '';
            this.error = null;
            this.busy = true;
            this.scrollDown();

            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

            try {
                const response = await fetch(this.config.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ message: text, history }),
                });

                const body = await response.json().catch(() => null);

                if (!response.ok) {
                    this.error = describeError(response.status, body);
                    this.messages[this.messages.length - 1].failed = true;
                    return;
                }

                this.messages.push({
                    role: 'assistant',
                    text: body.answer,
                    cards: body.cards ?? [],
                    notes: body.notes ?? [],
                });
            } catch (e) {
                this.error = e.name === 'AbortError'
                    ? 'Asisten terlalu lama menjawab. Coba lagi sebentar lagi.'
                    : describeError(0, null);
                this.messages[this.messages.length - 1].failed = true;
            } finally {
                clearTimeout(timer);
                this.busy = false;
                this.scrollDown();
                this.$nextTick(() => this.$refs.input?.focus());
            }
        },
    }));
}
