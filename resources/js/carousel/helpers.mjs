/**
 * Fungsi murni korsel "Pilihan Editor": kartu tengah tajam dan besar, tetangganya mengecil,
 * buram, dan memudar. Dipisah agar bisa dites dengan `node --test`.
 */

/** Indeks melingkar. */
export function wrap(index, count) {
    if (count <= 0) return 0;

    return ((index % count) + count) % count;
}

/** Jarak terpendek dari kartu i ke kartu aktif, dalam langkah, bertanda (negatif = di kiri). */
export function offsetFor(index, active, count) {
    if (count <= 1) return 0;
    let d = wrap(index - active, count);
    if (d > count / 2) d -= count;

    return d;
}

/** Gaya tiap kartu menurut jaraknya ke kartu aktif. Lebih dari 2 langkah: disembunyikan. */
export function slideStyle(offset, reduceMotion = false) {
    const distance = Math.abs(offset);
    if (distance > 2) {
        return { transform: `translateX(${offset * 70}%) scale(0.6)`, opacity: 0, filter: 'none', zIndex: 0, visibility: 'hidden' };
    }

    const scale = distance === 0 ? 1 : distance === 1 ? 0.82 : 0.7;
    const opacity = distance === 0 ? 1 : distance === 1 ? 0.55 : 0.3;
    const blur = reduceMotion || distance === 0 ? 'none' : `blur(${distance === 1 ? 2 : 3}px)`;

    return {
        transform: `translateX(${offset * 58}%) scale(${scale})`,
        opacity,
        filter: blur,
        zIndex: 3 - distance,
        visibility: 'visible',
    };
}

/** Arah geser dari gestur sentuh atau seret kursor: -1 maju, 1 mundur, 0 bukan gestur. */
export function swipeDirection(deltaX, threshold = 40) {
    if (deltaX <= -threshold) return 1;
    if (deltaX >= threshold) return -1;

    return 0;
}
