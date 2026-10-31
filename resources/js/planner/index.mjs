import { STEPS, conflicts, dropPosition, joinWords, rupiah, stepErrors } from './helpers.mjs';

/**
 * Komponen Alpine untuk planner. Semua form tetap berfungsi tanpa JavaScript: wizard menampilkan
 * semua langkah sekaligus, dan editor memakai form biasa. JavaScript hanya memperhalus.
 */
export function registerPlanner(Alpine) {
    /**
     * Wizard mode Rekomendasi. Jawaban hidup di satu objek, jadi Back tidak menghapus apa pun.
     * Field tetap di DOM (hanya disembunyikan), sehingga yang dikirim adalah nilai form asli.
     */
    Alpine.data('plannerWizard', (config) => ({
        step: config.initialStep ?? 1,
        values: config.values,
        labels: config.labels,
        limits: config.limits,
        errors: [],
        steps: STEPS,

        go(n) {
            this.errors = [];
            this.step = n;
            this.$nextTick(() => this.$refs['step' + n]?.focus());
        },

        next() {
            this.errors = stepErrors(this.step, this.values, this.limits);
            if (this.errors.length > 0) {
                this.$nextTick(() => this.$refs.errors?.focus());
                return;
            }
            this.go(Math.min(6, this.step + 1));
        },

        back() {
            this.go(Math.max(1, this.step - 1));
        },

        submit(event) {
            for (const s of [1, 2, 5]) {
                const errors = stepErrors(s, this.values, this.limits);
                if (errors.length > 0) {
                    event.preventDefault();
                    this.go(s);
                    this.errors = errors;
                    return;
                }
            }
            this.busy = true;
        },

        busy: false,

        get conflicts() {
            return conflicts(this.values);
        },

        names(ids, map) {
            return joinWords((ids ?? []).map((id) => map[id]).filter(Boolean));
        },

        summary(group) {
            const text = this.rawSummary(group);
            return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
        },

        rawSummary(group) {
            const v = this.values;
            const l = this.labels;
            switch (group) {
                case 1:
                    return [
                        `${v.days_count || '?'} hari`,
                        v.start_date ? `mulai ${v.start_date}` : 'tanggal belum ditentukan',
                        v.origin_region_id ? `titik awal ${l.regions[v.origin_region_id] ?? ''}` : 'titik awal belum ditentukan',
                        v.party ? l.party[v.party] : null,
                    ].filter(Boolean).join(', ');
                case 2:
                    return v.surprise ? 'Kejutkan saya' : this.names(v.themes, l.themes) || 'Belum dipilih';
                case 3:
                    return `${this.names(v.region_ids, l.regions) || 'Seluruh Bali'}, ${(l.flexibility[v.flexibility] ?? '').toLowerCase()}`;
                case 4:
                    return [
                        l.pace[v.pace],
                        l.transport[v.transport_mode] ? 'transportasi ' + l.transport[v.transport_mode].toLowerCase() : null,
                        l.dailyTime[v.daily_time] ? 'waktu harian ' + l.dailyTime[v.daily_time].toLowerCase() : null,
                        (v.mobility ?? []).length ? 'kebutuhan mobilitas: ' + this.names(v.mobility, l.access) : null,
                        v.avoid_outdoor ? 'kurangi aktivitas luar ruang' : null,
                    ].filter(Boolean).join(', ');
                case 5: {
                    if (!v.want_hotel) return 'Tidak perlu rekomendasi penginapan';
                    const price = v.price_min || v.price_max
                        ? `${rupiah(v.price_min) || 'Rp0'} sampai ${rupiah(v.price_max) || 'tanpa batas'} per malam`
                        : 'tanpa batas harga';
                    return [
                        this.names(v.stay_region_ids, l.regions) || 'wilayah mengikuti itinerary',
                        price,
                        this.names(v.hotel_types, l.hotelTypes),
                    ].filter(Boolean).join(', ');
                }
            }
            return '';
        },
    }));

    /** Editor: pemilih hari untuk peta, salin ringkasan. */
    Alpine.data('plannerEditor', (config) => ({
        day: config.activeDay,
        days: config.days,
        copied: false,

        showDay(n, scroll = false) {
            this.day = n;
            window.dispatchEvent(new CustomEvent('archive-map-swap', { detail: this.days[n] }));
            if (scroll) this.$nextTick(() => document.getElementById('panel-peta')?.scrollIntoView({ block: 'start' }));
        },

        get routeNote() {
            return this.days[this.day]?.routeNote ?? null;
        },

        async copySummary() {
            try {
                await navigator.clipboard.writeText(this.$refs.summary.value);
                this.copied = true;
                setTimeout(() => (this.copied = false), 4000);
            } catch {
                this.$refs.summary.hidden = false;
                this.$refs.summary.select();
            }
        },
    }));

    /** Catatan, durasi, dan jam mulai disimpan tanpa memuat ulang, agar rute tidak dihitung ulang. */
    Alpine.data('itemNoteForm', () => ({
        state: 'idle',
        message: '',

        async save(form) {
            this.state = 'saving';
            this.message = '';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                if (response.ok) {
                    this.state = 'saved';
                    this.message = 'Tersimpan.';
                    return;
                }
                const data = await response.json().catch(() => ({}));
                this.state = 'error';
                this.message = Object.values(data.errors ?? {}).flat().join(' ') || 'Gagal menyimpan. Coba lagi.';
            } catch {
                this.state = 'error';
                this.message = 'Gagal menyimpan. Periksa koneksi lalu coba lagi.';
            }
        },
    }));

    /**
     * Seret dan lepas untuk mengurutkan atau memindah antarhari. Alternatif keyboard dan
     * layar sentuh tetap tombol Naik, Turun, dan Pindah ke hari. Hasilnya selalu disimpan
     * ke server lewat form biasa, sehingga urutan data dan urutan visual tidak pernah berbeda.
     */
    Alpine.data('plannerSortable', (csrf) => ({
        dragging: null,

        start(event, id) {
            this.dragging = id;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(id));
        },

        drop(event, dayId, moveUrl) {
            const id = Number(event.dataTransfer.getData('text/plain') || this.dragging);
            if (!id) return;

            const list = event.currentTarget;
            const items = [...list.querySelectorAll('[data-item-id]')];
            const ids = items.map((el) => Number(el.dataset.itemId));
            const target = event.target.closest('[data-item-id]');
            let after = false;
            if (target) {
                const box = target.getBoundingClientRect();
                after = event.clientY > box.top + box.height / 2;
            }
            const position = dropPosition(ids, id, target ? Number(target.dataset.itemId) : null, after);

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = moveUrl.replace('__ITEM__', String(id));
            for (const [name, value] of Object.entries({ _token: csrf, day_id: dayId, position })) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = String(value);
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
        },
    }));
}
