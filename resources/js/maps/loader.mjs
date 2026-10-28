import { importLibrary, setOptions } from '@googlemaps/js-api-loader';

let configured = false;

/**
 * Memuat library Google Maps sesuai kebutuhan. Dipanggil hanya saat peta benar-benar akan
 * tampil, sehingga halaman tanpa peta tidak menghubungi Google sama sekali.
 *
 * @param {{key: string, libraries: string[], language?: string, region?: string}} options
 */
export async function loadGoogleLibraries({ key, libraries, language = 'id', region = 'ID' }) {
    if (!configured) {
        setOptions({ key, language, region });
        configured = true;
    }

    const loaded = {};

    await Promise.all(
        libraries.map(async (name) => {
            loaded[name] = await importLibrary(name);
        }),
    );

    return loaded;
}
