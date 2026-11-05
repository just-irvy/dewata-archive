import assert from 'node:assert/strict';
import { test } from 'node:test';

import { registerChat } from './index.mjs';

/** Membuat instance komponen Alpine tanpa browser: $nextTick dan $refs ditiru. */
function makeComponent(config = {}) {
    let factory;
    registerChat({ data: (_name, fn) => { factory = fn; } });

    const component = factory({ greeting: 'Halo', history: [], maxHistory: 10, maxChars: 500, endpoint: '/chat', loggedIn: false, ...config });
    component.$nextTick = (fn) => fn();
    component.$refs = { input: { focus() { component.focused = true; } }, log: {} };
    component.sent = [];
    component.send = async function () { this.sent.push(this.draft); };

    return component;
}

test('komponen punya semua aksi yang dipanggil dari markup', () => {
    const c = makeComponent();
    for (const name of ['toggle', 'show', 'close', 'ask', 'openWith', 'submitBar', 'startOver', 'send', 'scrollDown']) {
        assert.equal(typeof c[name], 'function', name);
    }
});

test('Buka pada bilah membuka jendela dan memfokuskan kolom ketik', () => {
    const c = makeComponent();
    c.submitBar();
    assert.equal(c.open, true);
    assert.equal(c.focused, true);
    assert.deepEqual(c.sent, []);
});

test('Buka dengan teks yang sudah diketik langsung mengirimnya', () => {
    const c = makeComponent();
    c.draft = 'hotel di ubud';
    c.submitBar();
    assert.equal(c.open, true);
    assert.deepEqual(c.sent, ['hotel di ubud']);
});

test('Kirim saat jendela terbuka tidak menutupnya', () => {
    const c = makeComponent();
    c.show();
    c.draft = 'pura di gianyar';
    c.submitBar();
    assert.equal(c.open, true);
    assert.deepEqual(c.sent, ['pura di gianyar']);
});

test('init mengukur tinggi bilah dari elemennya', () => {
    const c = makeComponent();
    c.$refs.bar = { offsetHeight: 61 };
    globalThis.window = { addEventListener() {} };
    c.init();
    assert.equal(c.barHeight, 61);
    delete globalThis.window;
});

test('toggle dan close bekerja', () => {
    const c = makeComponent();
    c.toggle();
    assert.equal(c.open, true);
    c.toggle();
    assert.equal(c.open, false);
    c.close();
    assert.equal(c.open, false);
});

test('openWith mengabaikan teks terlalu pendek tetapi tetap membuka', () => {
    const c = makeComponent();
    c.openWith('a');
    assert.equal(c.open, true);
    assert.deepEqual(c.sent, []);
});
