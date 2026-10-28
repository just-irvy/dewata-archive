import {
    BETA_MODES,
    BETA_WARNING,
    DEFAULT_ROUTE_ERROR,
    ROUTE_MESSAGES,
    buildRouteRequest,
    safeHref,
    summarizeRoute,
    withTimeout,
} from './helpers.mjs';

const ROUTE_STYLE = { strokeColor: '#9C4935', strokeWeight: 5 };

/**
 * Mengendalikan satu peta: marker, info window, dan rute. Tidak bergantung pada Alpine.
 * Peta adalah utilitas. Bila apa pun gagal, halaman dan daftar tetap utuh dan pengguna
 * mendapat pesan jujur. Jarak garis lurus tidak pernah ditampilkan sebagai jarak jalan.
 */
export class MapController {
    constructor({
        payload,
        canvas,
        loadLibraries,
        onChange = () => {},
        createElement = (tag) => document.createElement(tag),
        dispatch = (name, detail) => window.dispatchEvent(new CustomEvent(name, { detail })),
        focusById = (id) => {
            const element = document.getElementById(id);
            if (!element) return false;
            element.focus();
            return true;
        },
        timeoutMs = 10000,
    }) {
        this.payload = payload;
        this.canvas = canvas;
        this.loadLibraries = loadLibraries;
        this.onChange = onChange;
        this.createElement = createElement;
        this.dispatch = dispatch;
        this.focusById = focusById;
        this.timeoutMs = timeoutMs;

        this.state = 'idle';
        this.routeState = 'none';
        this.map = null;
        this.libs = null;
        this.info = null;
        this.markers = new Map();
        this.polylines = [];
    }

    #set(patch) {
        Object.assign(this, patch);
        this.onChange(patch);
    }

    async load() {
        if (this.state === 'loading' || this.state === 'ready') {
            return;
        }

        this.#set({ state: 'loading', errorCode: null });

        try {
            const names = ['maps', 'marker', 'core'];
            if (this.payload.route) names.push('routes');

            this.libs = await withTimeout(this.loadLibraries(names), this.timeoutMs);
            this.#buildMap();
            this.#set({ state: 'ready' });
        } catch (error) {
            this.fail(error?.code ?? 'load');
            return;
        }

        if (this.payload.route) {
            await this.drawRoute();
        }
    }

    /** Dipanggil juga oleh gm_authFailure saat key ditolak Google. */
    fail(code = 'load') {
        this.#clearMarkers();
        this.#clearPolylines();
        this.#set({ state: 'error', errorCode: code, routeState: 'none' });
    }

    #buildMap() {
        const { Map: GoogleMap, InfoWindow } = this.libs.maps;
        const markers = this.payload.markers ?? [];
        const first = markers[0];

        this.map = new GoogleMap(this.canvas, {
            mapId: this.payload.mapId,
            center: { lat: first.lat, lng: first.lng },
            zoom: 14,
            // Gulir halaman tidak terhenti oleh peta di layar sentuh.
            gestureHandling: 'cooperative',
            mapTypeControl: false,
            streetViewControl: false,
            clickableIcons: false,
        });
        this.info = new InfoWindow();

        this.#drawMarkers();
        this.#fit();
    }

    #pin(marker) {
        const element = this.createElement('div');
        element.className = `archive-pin archive-pin-${marker.kind ?? 'place'}${marker.label ? '' : ' archive-pin-dot'}`;
        element.textContent = marker.label ?? '';

        return element;
    }

    #drawMarkers() {
        const { AdvancedMarkerElement } = this.libs.marker;

        for (const marker of this.payload.markers ?? []) {
            const object = new AdvancedMarkerElement({
                map: this.map,
                position: { lat: marker.lat, lng: marker.lng },
                title: marker.title,
                content: this.#pin(marker),
                gmpClickable: true,
            });

            object.addListener('click', () => this.select(marker.id));
            this.markers.set(marker.id, object);
        }
    }

    #fit() {
        const markers = this.payload.markers ?? [];

        if (markers.length === 1) {
            this.map.setCenter({ lat: markers[0].lat, lng: markers[0].lng });
            this.map.setZoom(15);

            return;
        }

        const { LatLngBounds } = this.libs.core;
        const bounds = new LatLngBounds();
        markers.forEach((marker) => bounds.extend({ lat: marker.lat, lng: marker.lng }));
        this.map.fitBounds(bounds, 48);
    }

    /** Klik marker: fokus ke item daftar yang sama. Bila tidak ada, buka info window. */
    select(id) {
        const marker = (this.payload.markers ?? []).find((item) => item.id === id);
        if (!marker) return;

        this.dispatch('archive-map:select', { id });

        if (marker.focusId && this.focusById(marker.focusId)) {
            return;
        }

        const body = this.createElement('div');
        const title = this.createElement('strong');
        title.textContent = marker.title;
        body.appendChild(title);

        const href = safeHref(marker.href);
        if (href) {
            const link = this.createElement('a');
            link.href = href;
            link.textContent = 'Buka halaman';
            body.appendChild(this.createElement('br'));
            body.appendChild(link);
        }

        this.info.setContent(body);
        this.info.open({ map: this.map, anchor: this.markers.get(id) });
    }

    async drawRoute() {
        const route = this.payload.route;
        const active = route.active !== false;

        this.#set({ routeState: 'loading', routeError: null, routeLegs: [], routeTotal: null, routeWarnings: [] });

        let built;
        try {
            built = buildRouteRequest(route);
        } catch (error) {
            this.#set({ routeState: 'error', routeError: ROUTE_MESSAGES[error.code] ?? DEFAULT_ROUTE_ERROR });

            return;
        }

        try {
            const { Route } = this.libs.routes;
            const { routes } = await withTimeout(Route.computeRoutes(built.request), this.timeoutMs);
            const first = routes?.[0];

            if (!first) {
                throw new Error('no_route');
            }

            this.#clearPolylines();
            this.polylines = first.createPolylines();
            for (const polyline of this.polylines) {
                polyline.setOptions({ ...ROUTE_STYLE, strokeOpacity: active ? 0.95 : 0.35 });
                polyline.setMap(this.map);
            }

            const summary = summarizeRoute(first, built.sequence);
            const warnings = [...(first.warnings ?? [])];
            if (BETA_MODES.includes(route.mode)) warnings.push(BETA_WARNING);

            this.#set({
                routeState: 'ready',
                routeLegs: summary.legs,
                routeTotal: summary.total,
                routeWarnings: warnings,
            });
        } catch {
            // Marker dan daftar tetap tampil. Tidak ada garis lurus pengganti.
            this.#clearPolylines();
            this.#set({ routeState: 'error', routeError: DEFAULT_ROUTE_ERROR, routeLegs: [], routeTotal: null });
        }
    }

    /** Dipakai planner untuk berganti hari atau mengubah urutan tanpa membuat peta baru. */
    async update(payload) {
        this.payload = payload;

        if (this.state !== 'ready') {
            return;
        }

        this.#clearMarkers();
        this.#clearPolylines();
        this.#drawMarkers();
        this.#fit();

        if (payload.route) {
            if (!this.libs.routes) {
                this.libs.routes = await this.loadLibraries(['routes']).then((libs) => libs.routes);
            }
            await this.drawRoute();
        } else {
            this.#set({ routeState: 'none', routeLegs: [], routeTotal: null, routeWarnings: [] });
        }
    }

    #clearMarkers() {
        for (const object of this.markers.values()) {
            object.map = null;
        }
        this.markers.clear();
        this.info?.close?.();
    }

    #clearPolylines() {
        for (const polyline of this.polylines) {
            polyline.setMap(null);
        }
        this.polylines = [];
    }
}
