/**
 * Fungsi murni untuk widget chat. Dipisah dari Alpine agar bisa dites dengan `node --test`.
 */

export const MAX_TURN_CHARS = 1200;

/**
 * Susun riwayat yang dikirim ke server. Sapaan lokal, pesan error, dan pesan kosong dibuang.
 * Riwayat dipotong ke `max` pesan terakhir dan harus diawali pesan pengguna.
 */
export function buildHistory(messages, max = 10) {
    const turns = messages
        .filter((m) => !m.local && !m.failed && typeof m.text === 'string' && m.text.trim() !== '')
        .map((m) => ({
            role: m.role === 'user' ? 'user' : 'model',
            text: m.text.trim().slice(0, MAX_TURN_CHARS),
        }))
        .slice(-max);

    while (turns.length > 0 && turns[0].role !== 'user') {
        turns.shift();
    }

    return turns;
}

/** Pesan error yang ramah untuk tiap status HTTP. Tidak pernah menampilkan detail teknis. */
export function describeError(status, body) {
    if (status === 419) {
        return 'Sesi halaman sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    }
    if (status === 429) {
        return 'Terlalu banyak pertanyaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.';
    }
    if (status === 422) {
        const first = body && body.errors ? Object.values(body.errors).flat()[0] : null;
        return first || body?.message || 'Pertanyaan tidak dapat diproses.';
    }
    if (body && typeof body.message === 'string' && body.code) {
        return body.message;
    }

    return 'Asisten sedang tidak tersedia. Katalog, peta, dan planner tetap bisa dipakai.';
}
