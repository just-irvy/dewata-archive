// Fungsi murni untuk peta dan rute. Tidak menyentuh DOM atau Google, jadi bisa dites dengan `node --test`.

/** Google menagih lebih tinggi untuk 11 intermediate waypoint ke atas (maksimum 25). Kita berhenti di 10. */
export const MAX_INTERMEDIATES = 10;

export const BETA_MODES = ['WALKING', 'BICYCLING', 'TWO_WHEELER'];

/** Google mewajibkan peringatan ini untuk rute jalan kaki, sepeda, dan motor. */
export const BETA_WARNING =
    'Rute jalan kaki, sepeda, dan motor masih beta. Jalur kecil atau jalan setapak bisa belum tercatat.';

export const SUPPORTED_MODES = ['DRIVING', 'TWO_WHEELER', 'WALKING', 'BICYCLING'];

export class RouteInputError extends Error {
    constructor(code) {
        super(code);
        this.name = 'RouteInputError';
        this.code = code;
    }
}

export const ROUTE_MESSAGES = {
    too_many_stops: 'Terlalu banyak pemberhentian untuk satu rute. Kurangi tempat pada hari ini.',
    need_two_points: 'Rute membutuhkan minimal dua titik.',
    no_stops: 'Belum ada tempat untuk dirutekan.',
    bad_mode: 'Moda perjalanan ini belum didukung untuk rute.',
};

export const DEFAULT_ROUTE_ERROR = 'Rute jalan belum tersedia.';

/** Data yang hilang (null, undefined, kosong) bukan nol. Number(null) bernilai 0, jadi dicek dulu. */
function toNumber(raw) {
    return raw === null || raw === undefined || raw === '' ? Number.NaN : Number(raw);
}

/** 850 m, 3,2 km, 12 km. Koma sebagai pemisah desimal. */
export function formatDistance(meters) {
    const value = toNumber(meters);

    if (!Number.isFinite(value) || value < 0) {
        return '-';
    }
    if (value < 1000) {
        return `${Math.round(value)} m`;
    }

    const km = Math.round(value / 100) / 10;

    return `${Number.isInteger(km) ? km : km.toFixed(1).replace('.', ',')} km`;
}

/** kurang dari 1 menit, 35 menit, 1 jam, 1 jam 20 menit. */
export function formatDuration(millis) {
    const value = toNumber(millis);

    if (!Number.isFinite(value) || value < 0) {
        return '-';
    }

    const minutes = Math.round(value / 60000);

    if (minutes < 1) {
        return 'kurang dari 1 menit';
    }
    if (minutes < 60) {
        return `${minutes} menit`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours} jam` : `${hours} jam ${rest} menit`;
}

const literal = (point) => ({ lat: Number(point.lat), lng: Number(point.lng) });

/**
 * Menyusun permintaan computeRoutes untuk satu hari (FILE 11).
 *
 * Dengan asal (hotel): asal = hotel, tujuan = tempat terakhir, atau hotel lagi bila returnToOrigin.
 * Bila kembali ke hotel, semua tempat menjadi intermediate. Tempat terakhir tidak pernah
 * dimasukkan sekaligus sebagai destination dan intermediate.
 * Tanpa asal: titik pertama menjadi asal dan titik terakhir menjadi tujuan.
 */
export function buildRouteRequest({ origin = null, stops = [], returnToOrigin = false, mode = 'DRIVING' }) {
    if (!SUPPORTED_MODES.includes(mode)) {
        throw new RouteInputError('bad_mode');
    }
    if (stops.length === 0) {
        throw new RouteInputError('no_stops');
    }

    let from;
    let to;
    let middle;

    if (origin) {
        from = origin;
        if (returnToOrigin) {
            to = origin;
            middle = stops;
        } else {
            to = stops[stops.length - 1];
            middle = stops.slice(0, -1);
        }
    } else {
        if (stops.length < 2) {
            throw new RouteInputError('need_two_points');
        }
        from = stops[0];
        to = stops[stops.length - 1];
        middle = stops.slice(1, -1);
    }

    if (middle.length > MAX_INTERMEDIATES) {
        throw new RouteInputError('too_many_stops');
    }

    return {
        request: {
            origin: literal(from),
            destination: literal(to),
            intermediates: middle.map((point) => ({ location: literal(point) })),
            travelMode: mode,
            // Hanya field yang dipakai. Jangan gunakan wildcard.
            fields: ['path', 'legs', 'distanceMeters', 'durationMillis', 'warnings'],
        },
        // Urutan titik sesuai urutan leg: asal, intermediate, tujuan.
        sequence: [from, ...middle, to],
    };
}

/**
 * Ringkasan per leg dan total. Total harian adalah jumlah nilai leg, bukan selisih koordinat.
 *
 * @returns {{legs: Array<{from: string, to: string, distance: string, duration: string}>, total: {distance: string, duration: string}}}
 */
export function summarizeRoute(route, sequence) {
    const rawLegs = Array.isArray(route?.legs) ? route.legs : [];

    const legs = rawLegs.map((leg, index) => ({
        from: sequence[index]?.label ?? `Titik ${index + 1}`,
        to: sequence[index + 1]?.label ?? `Titik ${index + 2}`,
        distance: formatDistance(leg.distanceMeters),
        duration: formatDuration(leg.durationMillis),
    }));

    const meters = rawLegs.reduce((sum, leg) => sum + (Number(leg.distanceMeters) || 0), 0);
    const millis = rawLegs.reduce((sum, leg) => sum + (Number(leg.durationMillis) || 0), 0);

    return { legs, total: { distance: formatDistance(meters), duration: formatDuration(millis) } };
}

/** Hanya tautan relatif atau http(s) yang boleh masuk ke info window. */
export function safeHref(href) {
    return typeof href === 'string' && /^(\/|https?:\/\/)/i.test(href) ? href : null;
}

export function withTimeout(promise, ms, code = 'timeout') {
    let timer;

    const timeout = new Promise((_, reject) => {
        timer = setTimeout(() => {
            const error = new Error(code);
            error.code = code;
            reject(error);
        }, ms);
    });

    return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}
