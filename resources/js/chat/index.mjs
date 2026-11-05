import { buildHistory, describeError } from './helpers.mjs';

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
        messages: [{ role: 'assistant', text: config.greeting, cards: [], notes: [], local: true }],

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        close() {
            if (!this.open) return;
            this.open = false;
        },

        ask(text) {
            this.draft = text;
            this.send();
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

            const history = buildHistory(this.messages, this.config.maxHistory);

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
