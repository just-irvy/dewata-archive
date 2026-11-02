import assert from 'node:assert/strict';
import { test } from 'node:test';

import { registerMaps } from './index.mjs';

// Tiruan Alpine: membungkus data komponen dengan proxy, seperti reaktivitas Alpine sungguhan.
function fakeAlpine() {
    const factories = {};
    return { factories, data: (name, factory) => (factories[name] = factory) };
}

class WithPrivate {
    #secret = 1;
    read() {
        return this.#secret;
    }
}

test('private fields break when called through a proxy (alasan controller tidak boleh reaktif)', () => {
    const proxied = new Proxy(new WithPrivate(), {});
    assert.throws(() => proxied.read(), TypeError);
});

test('archiveMap keeps the map controller out of reactive component data', () => {
    const Alpine = fakeAlpine();
    registerMaps(Alpine);
    const data = Alpine.factories.archiveMap({ markers: [] });

    assert.equal('controller' in data, false);
    assert.equal(data.state, 'idle');
    assert.equal(typeof data.load, 'function');
    assert.equal(typeof data.swap, 'function');
});
