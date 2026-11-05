import assert from 'node:assert/strict';
import { test } from 'node:test';

import { MAX_TURN_CHARS, buildHistory, describeError } from './helpers.mjs';

test('buildHistory membuang sapaan lokal, pesan gagal, dan pesan kosong', () => {
    const history = buildHistory([
        { role: 'assistant', text: 'Halo', local: true },
        { role: 'user', text: 'hotel di ubud' },
        { role: 'assistant', text: 'Ini pilihannya' },
        { role: 'user', text: 'oke', failed: true },
        { role: 'user', text: '   ' },
    ]);

    assert.deepEqual(history, [
        { role: 'user', text: 'hotel di ubud' },
        { role: 'model', text: 'Ini pilihannya' },
    ]);
});

test('buildHistory memotong ke pesan terakhir dan selalu diawali pesan pengguna', () => {
    const messages = [];
    for (let i = 0; i < 8; i++) {
        messages.push({ role: 'user', text: `tanya ${i}` }, { role: 'assistant', text: `jawab ${i}` });
    }

    const history = buildHistory(messages, 5);

    assert.equal(history[0].role, 'user');
    assert.ok(history.length <= 5);
    assert.equal(history.at(-1).text, 'jawab 7');
});

test('buildHistory memendekkan teks panjang', () => {
    const [turn] = buildHistory([{ role: 'user', text: 'x'.repeat(MAX_TURN_CHARS + 500) }]);

    assert.equal(turn.text.length, MAX_TURN_CHARS);
});

test('describeError memberi pesan ramah per status', () => {
    assert.match(describeError(419, null), /kedaluwarsa/);
    assert.match(describeError(429, null), /Terlalu banyak/);
    assert.equal(describeError(422, { errors: { message: ['Terlalu pendek.'] } }), 'Terlalu pendek.');
    assert.equal(describeError(503, { message: 'Asisten sedang ramai.', code: 'quota' }), 'Asisten sedang ramai.');
    assert.match(describeError(500, null), /tidak tersedia/);
});
