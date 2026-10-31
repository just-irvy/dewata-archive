// Fungsi murni untuk wizard planner. Tidak menyentuh DOM, jadi bisa dites dengan `node --test`.
// Server tetap memvalidasi ulang semuanya (RecommendRequest). Ini hanya agar pengguna tidak
// baru tahu ada yang kurang setelah menekan tombol terakhir.

export const STEPS = [
    { n: 1, title: 'Kerangka perjalanan' },
    { n: 2, title: 'Minat' },
    { n: 3, title: 'Wilayah' },
    { n: 4, title: 'Tempo dan batasan' },
    { n: 5, title: 'Penginapan' },
    { n: 6, title: 'Tinjau' },
];

const asNumber = (value) => (value === '' || value === null || value === undefined ? null : Number(value));

/** @returns {string[]} pesan galat untuk langkah itu */
export function stepErrors(step, values, { maxDays = 14, maxThemes = 5 } = {}) {
    const errors = [];

    if (step === 1) {
        const days = asNumber(values.days_count);
        if (!Number.isInteger(days) || days < 1 || days > maxDays) {
            errors.push(`Isi jumlah hari antara 1 dan ${maxDays}.`);
        }
    }

    if (step === 2) {
        const themes = values.themes ?? [];
        if (!values.surprise && themes.length === 0) {
            errors.push('Pilih minimal satu tema, atau pilih "Kejutkan saya".');
        }
        if (!values.surprise && themes.length > maxThemes) {
            errors.push(`Pilih paling banyak ${maxThemes} tema agar hasil tidak terlalu luas.`);
        }
    }

    if (step === 5 && values.want_hotel) {
        const min = asNumber(values.price_min);
        const max = asNumber(values.price_max);
        if ((min !== null && min < 0) || (max !== null && max < 0)) {
            errors.push('Harga per malam tidak boleh negatif.');
        }
        if (min !== null && max !== null && max < min) {
            errors.push('Harga maksimal per malam harus sama dengan atau lebih besar dari harga minimal.');
        }
    }

    return errors;
}

/**
 * Peringatan sebelum generate (FILE 08, step 6). Pengguna boleh lanjut dengan peringatan.
 * @returns {string[]}
 */
export function conflicts(values) {
    const days = asNumber(values.days_count) ?? 0;
    const regions = (values.region_ids ?? []).length;
    const warnings = [];

    if (regions > days && days > 0) {
        warnings.push(`Anda memilih ${regions} wilayah untuk ${days} hari. Sebagian wilayah tidak akan masuk agar tiap hari tidak berpindah jauh.`);
    }
    if (values.pace === 'relaxed' && regions > 1 && days <= regions) {
        warnings.push('Tempo santai dengan beberapa wilayah dalam waktu singkat berarti lebih banyak waktu di jalan.');
    }
    if (regions === 0 && values.flexibility === 'willing' && days <= 2) {
        warnings.push('Seluruh Bali dalam dua hari atau kurang dengan perjalanan jauh akan memakan banyak waktu di jalan. Pertimbangkan satu atau dua wilayah.');
    }
    if (values.transport_mode === 'public') {
        warnings.push('Transportasi umum tidak dirutekan di peta karena jadwal dan jalurnya belum terverifikasi. Urutan tempat tetap disusun.');
    }

    return warnings;
}

/** "Rp1.500.000" */
export function rupiah(value) {
    const n = asNumber(value);
    if (n === null || !Number.isFinite(n)) return '';

    return 'Rp' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/** "A", "A dan B", "A, B, dan C" */
export function joinWords(items) {
    const list = items.filter(Boolean);
    if (list.length <= 1) return list[0] ?? '';
    if (list.length === 2) return `${list[0]} dan ${list[1]}`;

    return `${list.slice(0, -1).join(', ')}, dan ${list.at(-1)}`;
}

/** Posisi baru (1-based) saat item dijatuhkan di atas item lain. */
export function dropPosition(ids, draggedId, targetId, after) {
    const rest = ids.filter((id) => id !== draggedId);
    if (targetId === null) return rest.length + 1;
    const index = rest.indexOf(targetId);
    if (index === -1) return rest.length + 1;

    return index + (after ? 2 : 1);
}
