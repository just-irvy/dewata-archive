import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    MAX_INTERMEDIATES,
    RouteInputError,
    buildRouteRequest,
    formatDistance,
    formatDuration,
    safeHref,
    summarizeRoute,
    withTimeout,
} from './helpers.mjs';

const p = (n) => ({ id: `p${n}`, lat: -8 - n / 100, lng: 115 + n / 100, label: `D1·${n}` });
const hotel = { id: 'h', lat: -8.5, lng: 115.2, label: 'H' };

test('formatDistance memakai koma desimal dan satuan yang tepat', () => {
    assert.equal(formatDistance(850), '850 m');
    assert.equal(formatDistance(3240), '3,2 km');
    assert.equal(formatDistance(12000), '12 km');
    assert.equal(formatDistance(0), '0 m');
    assert.equal(formatDistance(-1), '-');
    assert.equal(formatDistance('abc'), '-');
    assert.equal(formatDistance(null), '-');
    assert.equal(formatDistance(undefined), '-');
});

test('formatDuration', () => {
    assert.equal(formatDuration(20000), 'kurang dari 1 menit');
    assert.equal(formatDuration(35 * 60000), '35 menit');
    assert.equal(formatDuration(60 * 60000), '1 jam');
    assert.equal(formatDuration(80 * 60000), '1 jam 20 menit');
    assert.equal(formatDuration(null), '-');
    assert.equal(formatDuration(undefined), '-');
    assert.equal(formatDuration(''), '-');
});

test('hotel + tempat, tanpa kembali: tempat terakhir menjadi tujuan dan bukan intermediate', () => {
    const { request, sequence } = buildRouteRequest({ origin: hotel, stops: [p(1), p(2), p(3)], mode: 'DRIVING' });

    assert.deepEqual(request.origin, { lat: -8.5, lng: 115.2 });
    assert.deepEqual(request.destination, { lat: p(3).lat, lng: p(3).lng });
    assert.equal(request.intermediates.length, 2);
    assert.deepEqual(sequence.map((s) => s.label), ['H', 'D1·1', 'D1·2', 'D1·3']);
});

test('kembali ke hotel: semua tempat menjadi intermediate dan tujuan adalah hotel', () => {
    const { request, sequence } = buildRouteRequest({ origin: hotel, stops: [p(1), p(2)], returnToOrigin: true });

    assert.deepEqual(request.destination, request.origin);
    assert.equal(request.intermediates.length, 2);
    assert.deepEqual(sequence.map((s) => s.label), ['H', 'D1·1', 'D1·2', 'H']);
});

test('tanpa hotel: titik pertama asal, terakhir tujuan, butuh minimal dua titik', () => {
    const { request } = buildRouteRequest({ stops: [p(1), p(2), p(3)] });
    assert.deepEqual(request.origin, { lat: p(1).lat, lng: p(1).lng });
    assert.deepEqual(request.destination, { lat: p(3).lat, lng: p(3).lng });
    assert.equal(request.intermediates.length, 1);

    assert.throws(() => buildRouteRequest({ stops: [p(1)] }), (e) => e instanceof RouteInputError && e.code === 'need_two_points');
});

test('field mask tidak memakai wildcard dan intermediate memakai bentuk {location}', () => {
    const { request } = buildRouteRequest({ origin: hotel, stops: [p(1), p(2)] });

    assert.ok(!request.fields.includes('*'));
    assert.deepEqual(request.fields.toSorted(), ['distanceMeters', 'durationMillis', 'legs', 'path', 'warnings']);
    assert.deepEqual(request.intermediates[0], { location: { lat: p(1).lat, lng: p(1).lng } });
});

test('batas intermediate: 10 boleh, 11 ditolak, daftar kosong dan moda asing ditolak', () => {
    const ten = Array.from({ length: 10 }, (_, i) => p(i));
    const eleven = Array.from({ length: 11 }, (_, i) => p(i));

    assert.equal(MAX_INTERMEDIATES, 10);
    assert.doesNotThrow(() => buildRouteRequest({ origin: hotel, stops: ten, returnToOrigin: true }));
    assert.throws(() => buildRouteRequest({ origin: hotel, stops: eleven, returnToOrigin: true }), { code: 'too_many_stops' });
    assert.throws(() => buildRouteRequest({ origin: hotel, stops: [] }), { code: 'no_stops' });
    assert.throws(() => buildRouteRequest({ origin: hotel, stops: [p(1)], mode: 'TRANSIT' }), { code: 'bad_mode' });
});

test('summarizeRoute menjumlahkan nilai leg dan memberi label dari urutan titik', () => {
    const route = {
        legs: [
            { distanceMeters: 1200, durationMillis: 600000 },
            { distanceMeters: 2100, durationMillis: 900000 },
        ],
    };
    const { legs, total } = summarizeRoute(route, [hotel, p(1), p(2)]);

    assert.deepEqual(legs[0], { from: 'H', to: 'D1·1', distance: '1,2 km', duration: '10 menit' });
    assert.equal(legs[1].to, 'D1·2');
    assert.deepEqual(total, { distance: '3,3 km', duration: '25 menit' });
});

test('summarizeRoute tahan terhadap respons kosong', () => {
    assert.deepEqual(summarizeRoute({}, []).legs, []);
    assert.equal(summarizeRoute(undefined, []).total.distance, '0 m');
});

test('safeHref hanya menerima tautan relatif atau http(s)', () => {
    assert.equal(safeHref('/places/pura'), '/places/pura');
    assert.equal(safeHref('https://contoh.test/x'), 'https://contoh.test/x');
    assert.equal(safeHref('javascript:alert(1)'), null);
    assert.equal(safeHref('data:text/html,x'), null);
    assert.equal(safeHref(undefined), null);
});

test('withTimeout menolak dengan kode timeout', async () => {
    await assert.rejects(withTimeout(new Promise(() => {}), 15), { code: 'timeout' });
    assert.equal(await withTimeout(Promise.resolve(7), 50), 7);
});
