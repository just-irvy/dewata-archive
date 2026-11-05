/**
 * Fungsi murni untuk animasi muncul-saat-digulir (reveal). Dipisah agar bisa dites dengan `node --test`.
 */

/** Jeda (ms) untuk elemen ke-i dalam satu kelompok: bertahap, tetapi tidak melebihi batas. */
export function staggerDelay(index, step = 90, max = 540) {
    const i = Number.isFinite(index) && index > 0 ? Math.floor(index) : 0;

    return Math.min(i * step, max);
}

/** Elemen yang sudah terlihat saat halaman dimuat langsung tampil, tanpa menunggu gulir. */
export function shouldRevealImmediately(rect, viewportHeight) {
    return rect.top < viewportHeight && rect.bottom > 0;
}
