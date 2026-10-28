import assert from 'node:assert/strict';
import { test } from 'node:test';

import { MapController } from './controller.mjs';

class FakeMap {
    constructor(element, options) {
        this.element = element;
        this.options = options;
    }
    setCenter(center) { this.center = center; }
    setZoom(zoom) { this.zoom = zoom; }
    fitBounds(bounds, padding) { this.bounds = bounds; this.padding = padding; }
}
class FakeInfo {
    setContent(content) { this.content = content; }
    open(options) { this.opened = options; }
    close() { this.closed = true; }
}
class FakeMarker {
    constructor(options) { Object.assign(this, options); this.listeners = {}; }
    addListener(name, fn) { this.listeners[name] = fn; }
}
class FakeBounds {
    constructor() { this.points = []; }
    extend(point) { this.points.push(point); }
}
class FakePolyline {
    constructor() { this.map = undefined; this.options = null; }
    setOptions(options) { this.options = options; }
    setMap(map) { this.map = map; }
}

const el = (tag) => ({ tag, children: [], className: '', textContent: '', appendChild(c) { this.children.push(c); } });

const markers = [
    { id: 'a', lat: -8.5, lng: 115.2, title: 'Hotel', label: 'H', kind: 'hotel' },
    { id: 'b', lat: -8.4, lng: 115.3, title: 'Pura', label: 'D1·1', kind: 'place', href: '/places/pura', focusId: 'map-item-b' },
    { id: 'c', lat: -8.3, lng: 115.4, title: 'Sawah', label: 'D1·2', kind: 'place' },
];

function build({ payload = {}, route = null, routeImpl, loader, focusById = () => false, timeoutMs = 100 } = {}) {
    const polyline = new FakePolyline();
    const patches = [];
    const calls = { computeRoutes: 0, request: null };

    const libs = {
        maps: { Map: FakeMap, InfoWindow: FakeInfo },
        marker: { AdvancedMarkerElement: FakeMarker },
        core: { LatLngBounds: FakeBounds },
        routes: {
            Route: {
                async computeRoutes(request) {
                    calls.computeRoutes += 1;
                    calls.request = request;
                    if (routeImpl) return routeImpl(request);
                    return {
                        routes: [{
                            legs: [{ distanceMeters: 1200, durationMillis: 600000 }, { distanceMeters: 800, durationMillis: 300000 }],
                            warnings: ['Rute melewati jalan sempit.'],
                            createPolylines: () => [polyline],
                        }],
                    };
                },
            },
        },
    };

    const controller = new MapController({
        payload: { mapId: 'MAP123', markers, route, ...payload },
        canvas: { id: 'canvas' },
        loadLibraries: loader ?? (async () => libs),
        onChange: (patch) => patches.push(patch),
        createElement: el,
        dispatch: () => {},
        focusById,
        timeoutMs,
    });

    return { controller, patches, calls, polyline, libs };
}

test('load berhasil: peta dibuat dengan opsi yang aman untuk halaman dan semua marker digambar', async () => {
    const { controller } = build();
    await controller.load();

    assert.equal(controller.state, 'ready');
    assert.equal(controller.map.options.mapId, 'MAP123');
    assert.equal(controller.map.options.gestureHandling, 'cooperative');
    assert.equal(controller.map.options.clickableIcons, false);
    assert.equal(controller.markers.size, 3);
    assert.equal(controller.map.padding, 48);
    assert.equal(controller.map.bounds.points.length, 3);

    const hotel = controller.markers.get('a');
    assert.equal(hotel.title, 'Hotel');
    assert.equal(hotel.gmpClickable, true);
    assert.equal(hotel.content.textContent, 'H');
    assert.match(hotel.content.className, /archive-pin-hotel/);
});

test('satu marker: pusatkan dan perbesar, tanpa fitBounds', async () => {
    const { controller } = build({ payload: { markers: [markers[1]] } });
    await controller.load();

    assert.deepEqual(controller.map.center, { lat: -8.4, lng: 115.3 });
    assert.equal(controller.map.zoom, 15);
    assert.equal(controller.map.bounds, undefined);
});

test('marker tanpa label memakai pin titik', async () => {
    const { controller } = build({ payload: { markers: [{ id: 'x', lat: -8, lng: 115, title: 'T' }] } });
    await controller.load();

    assert.match(controller.markers.get('x').content.className, /archive-pin-dot/);
});

test('library gagal dimuat: status error, halaman tidak rusak', async () => {
    const { controller } = build({ loader: async () => { throw new Error('blocked'); } });
    await controller.load();

    assert.equal(controller.state, 'error');
    assert.equal(controller.errorCode, 'load');
    assert.equal(controller.markers.size, 0);
});

test('library tidak kunjung selesai: timeout menjadi error', async () => {
    const { controller } = build({ loader: () => new Promise(() => {}), timeoutMs: 20 });
    await controller.load();

    assert.equal(controller.state, 'error');
    assert.equal(controller.errorCode, 'timeout');
});

test('key ditolak Google (gm_authFailure): peta dibersihkan dan status error', async () => {
    const { controller } = build();
    await controller.load();
    const marker = controller.markers.get('a');

    controller.fail('auth');

    assert.equal(controller.state, 'error');
    assert.equal(controller.errorCode, 'auth');
    assert.equal(marker.map, null);
    assert.equal(controller.markers.size, 0);
});

test('load dipanggil dua kali tidak membuat peta ganda', async () => {
    const { controller } = build();
    await Promise.all([controller.load(), controller.load()]);
    await controller.load();

    assert.equal(controller.markers.size, 3);
});

test('rute berhasil: polyline digambar, ringkasan dan total dihitung dari leg', async () => {
    const { controller, polyline, calls } = build({
        route: { origin: markers[0], stops: [markers[1], markers[2]], mode: 'DRIVING' },
    });
    await controller.load();

    assert.equal(calls.request.travelMode, 'DRIVING');
    assert.equal(calls.request.intermediates.length, 1);
    assert.equal(polyline.map, controller.map);
    assert.equal(polyline.options.strokeColor, '#9C4935');
    assert.equal(polyline.options.strokeOpacity, 0.95);
    assert.equal(controller.routeState, 'ready');
    assert.equal(controller.routeLegs.length, 2);
    assert.deepEqual(controller.routeLegs[0], { from: 'H', to: 'D1·1', distance: '1,2 km', duration: '10 menit' });
    assert.deepEqual(controller.routeTotal, { distance: '2 km', duration: '15 menit' });
    assert.deepEqual(controller.routeWarnings, ['Rute melewati jalan sempit.']);
});

test('rute hari yang tidak aktif digambar lebih tipis', async () => {
    const { controller, polyline } = build({
        route: { origin: markers[0], stops: [markers[1]], mode: 'DRIVING', active: false },
    });
    await controller.load();

    assert.equal(polyline.options.strokeOpacity, 0.35);
});

test('moda motor menambahkan peringatan beta di samping peringatan dari Google', async () => {
    const { controller } = build({
        route: { origin: markers[0], stops: [markers[1]], mode: 'TWO_WHEELER' },
    });
    await controller.load();

    assert.equal(controller.routeWarnings.length, 2);
    assert.match(controller.routeWarnings[1], /beta/);
});

test('rute gagal: marker tetap ada, pesan jujur, tanpa garis lurus pengganti', async () => {
    const { controller, polyline } = build({
        route: { origin: markers[0], stops: [markers[1], markers[2]], mode: 'DRIVING' },
        routeImpl: async () => { throw new Error('quota'); },
    });
    await controller.load();

    assert.equal(controller.state, 'ready');
    assert.equal(controller.markers.size, 3);
    assert.equal(controller.routeState, 'error');
    assert.equal(controller.routeError, 'Rute jalan belum tersedia.');
    assert.equal(controller.routeTotal, null);
    assert.equal(polyline.map, undefined);
});

test('Google tidak mengembalikan rute: dianggap gagal', async () => {
    const { controller } = build({
        route: { origin: markers[0], stops: [markers[1]], mode: 'DRIVING' },
        routeImpl: async () => ({ routes: [] }),
    });
    await controller.load();

    assert.equal(controller.routeState, 'error');
});

test('terlalu banyak pemberhentian: ditolak sebelum memanggil Google', async () => {
    const many = Array.from({ length: 11 }, (_, i) => ({ id: `s${i}`, lat: -8, lng: 115 + i / 100, label: `${i}` }));
    const { controller, calls } = build({
        route: { origin: markers[0], stops: many, returnToOrigin: true, mode: 'DRIVING' },
    });
    await controller.load();

    assert.equal(calls.computeRoutes, 0);
    assert.equal(controller.routeState, 'error');
    assert.match(controller.routeError, /Terlalu banyak pemberhentian/);
});

test('rute timeout: menjadi error rute, bukan error peta', async () => {
    const { controller } = build({
        route: { origin: markers[0], stops: [markers[1]], mode: 'DRIVING' },
        routeImpl: () => new Promise(() => {}),
        timeoutMs: 20,
    });
    await controller.load();

    assert.equal(controller.state, 'ready');
    assert.equal(controller.routeState, 'error');
});

test('klik marker dengan item daftar: fokus pindah ke item dan info window tidak dibuka', async () => {
    let focused = null;
    const { controller } = build({ focusById: (id) => { focused = id; return true; } });
    await controller.load();

    controller.markers.get('b').listeners.click();

    assert.equal(focused, 'map-item-b');
    assert.equal(controller.info.opened, undefined);
});

test('klik marker tanpa item daftar: info window berisi teks polos dan tautan aman', async () => {
    const { controller } = build({
        payload: { markers: [{ id: 'x', lat: -8, lng: 115, title: '<img src=x onerror=alert(1)>', href: '/places/x' }] },
    });
    await controller.load();

    controller.select('x');

    const [title, , link] = controller.info.content.children;
    assert.equal(title.textContent, '<img src=x onerror=alert(1)>');
    assert.equal(link.href, '/places/x');
    assert.equal(controller.info.opened.anchor, controller.markers.get('x'));
});

test('tautan berbahaya tidak masuk info window', async () => {
    const { controller } = build({
        payload: { markers: [{ id: 'x', lat: -8, lng: 115, title: 'T', href: 'javascript:alert(1)' }] },
    });
    await controller.load();

    controller.select('x');

    assert.equal(controller.info.content.children.length, 1);
});

test('update: marker diganti dan rute hilang saat payload tanpa rute', async () => {
    const { controller, polyline } = build({
        route: { origin: markers[0], stops: [markers[1]], mode: 'DRIVING' },
    });
    await controller.load();
    assert.equal(controller.routeState, 'ready');

    await controller.update({ mapId: 'MAP123', markers: [markers[2]], route: null });

    assert.equal(controller.markers.size, 1);
    assert.ok(controller.markers.has('c'));
    assert.equal(controller.routeState, 'none');
    assert.equal(polyline.map, null);
});
