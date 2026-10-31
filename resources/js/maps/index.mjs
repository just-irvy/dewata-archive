/**
 * Mendaftarkan komponen Alpine `archiveMap`. File ini kecil dan dimuat di setiap halaman.
 * Controller dan loader Google baru diunduh saat ada peta di halaman.
 */
const controllers = new Set();
let authHandlerInstalled = false;

function installAuthFailureHandler() {
    if (authHandlerInstalled) return;
    authHandlerInstalled = true;

    // Google memanggil fungsi global ini bila key ditolak (referrer salah, key dibatasi, dan sebagainya).
    window.gm_authFailure = () => controllers.forEach((controller) => controller.fail('auth'));
}

export function registerMaps(Alpine) {
    Alpine.data('archiveMap', (payload) => ({
        state: 'idle',
        errorCode: null,
        routeState: 'none',
        routeError: null,
        routeLegs: [],
        routeTotal: null,
        routeWarnings: [],
        controller: null,
        ready: null,

        init() {
            this.ready = this.$nextTick().then(() => this.boot());
        },

        async boot() {
            const [{ MapController }, { loadGoogleLibraries }] = await Promise.all([
                import('./controller.mjs'),
                import('./loader.mjs'),
            ]);

            this.controller = new MapController({
                payload,
                canvas: this.$refs.canvas,
                loadLibraries: (libraries) => loadGoogleLibraries({ key: payload.key, libraries }),
                onChange: (patch) => Object.assign(this, patch),
            });

            controllers.add(this.controller);
            installAuthFailureHandler();

            if (payload.autoload !== false && 'IntersectionObserver' in window) {
                const observer = new IntersectionObserver(
                    (entries) => {
                        if (entries.some((entry) => entry.isIntersecting)) {
                            observer.disconnect();
                            this.controller.load();
                        }
                    },
                    { rootMargin: '200px' },
                );
                observer.observe(this.$root);
            }
        },

        async load() {
            await this.ready;
            await this.controller?.load();
        },

        /** Planner: ganti hari tanpa membuat peta baru. Rute dihitung ulang untuk hari itu saja. */
        async swap(patch) {
            await this.ready;
            if (!this.controller) return;
            await this.controller.update({ ...this.controller.payload, ...patch });
        },

        destroy() {
            if (this.controller) controllers.delete(this.controller);
        },
    }));
}
