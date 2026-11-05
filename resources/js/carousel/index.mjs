import { offsetFor, slideStyle, swipeDirection, wrap } from './helpers.mjs';

/**
 * Komponen Alpine korsel "Pilihan Editor". Tanpa putar otomatis: pengunjung yang menggeser
 * (panah, titik, tombol panah keyboard, atau usapan). Pengguna kurangi gerak: tanpa transisi dan buram.
 */
export function registerCarousel(Alpine) {
    Alpine.data('editorPicks', (count) => ({
        count,
        active: 0,
        startX: null,
        reduceMotion: typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches,

        next() { this.active = wrap(this.active + 1, this.count); },
        prev() { this.active = wrap(this.active - 1, this.count); },
        goTo(i) { this.active = wrap(i, this.count); },

        styleFor(i) { return slideStyle(offsetFor(i, this.active, this.count), this.reduceMotion); },
        isActive(i) { return i === this.active; },

        pointerStart(e) { this.startX = e.clientX; },
        pointerEnd(e) {
            if (this.startX === null) return;
            const dir = swipeDirection(e.clientX - this.startX);
            this.startX = null;
            if (dir === 1) this.next();
            if (dir === -1) this.prev();
        },
    }));
}
