import { shouldRevealImmediately, staggerDelay } from './helpers.mjs';

/**
 * Muncul-saat-digulir, tanpa pustaka. Elemen bertanda data-reveal mulai tersembunyi (hanya bila JavaScript
 * aktif, lihat app.css) dan mendapat kelas is-revealed saat masuk layar. Begitu elemen keluar layar, kelas
 * dicabut lagi sehingga animasi terulang saat digulir kembali. Kelompok dengan data-reveal-group
 * memunculkan anak-anaknya bergantian. Pengguna yang memilih kurangi gerak melihat semuanya langsung.
 */
export function startReveal(root = document) {
    const items = Array.from(root.querySelectorAll('[data-reveal]'));
    if (items.length === 0) return;

    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduce || typeof IntersectionObserver === 'undefined') {
        items.forEach((el) => el.classList.add('is-revealed'));
        return;
    }

    // Jeda bertahap untuk anak-anak kelompok.
    root.querySelectorAll('[data-reveal-group]').forEach((group) => {
        Array.from(group.querySelectorAll(':scope > * [data-reveal], :scope > [data-reveal]'))
            .forEach((el, i) => el.style.setProperty('--reveal-delay', `${staggerDelay(i)}ms`));
    });

    // Dua pengamat. Yang pertama memunculkan elemen saat masuk layar (dengan sedikit margin agar tidak
    // muncul mepet tepi). Yang kedua mengembalikannya ke keadaan awal hanya setelah benar-benar keluar
    // dari layar, sehingga animasinya terulang saat pengunjung menggulir kembali ke bagian itu, dan
    // elemen tidak pernah menghilang selagi masih terlihat.
    const revealer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) entry.target.classList.add('is-revealed');
        });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });

    const resetter = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) entry.target.classList.remove('is-revealed');
        });
    }, { threshold: 0 });

    items.forEach((el) => {
        if (shouldRevealImmediately(el.getBoundingClientRect(), window.innerHeight)) {
            el.classList.add('is-revealed');
        }
        revealer.observe(el);
        resetter.observe(el);
    });
}
