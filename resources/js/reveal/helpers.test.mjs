import assert from 'node:assert/strict';
import { test } from 'node:test';

import { shouldRevealImmediately, staggerDelay } from './helpers.mjs';

test('staggerDelay bertahap dan dibatasi', () => {
    assert.equal(staggerDelay(0), 0);
    assert.equal(staggerDelay(2), 180);
    assert.equal(staggerDelay(40), 540);
    assert.equal(staggerDelay(-3), 0);
});

test('elemen yang sudah di layar langsung tampil', () => {
    assert.equal(shouldRevealImmediately({ top: 100, bottom: 400 }, 800), true);
    assert.equal(shouldRevealImmediately({ top: 900, bottom: 1200 }, 800), false);
    assert.equal(shouldRevealImmediately({ top: -300, bottom: -10 }, 800), false);
});
