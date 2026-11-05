import assert from 'node:assert/strict';
import { test } from 'node:test';

import { offsetFor, slideStyle, swipeDirection, wrap } from './helpers.mjs';

test('wrap melingkar di kedua arah', () => {
    assert.equal(wrap(5, 5), 0);
    assert.equal(wrap(-1, 5), 4);
    assert.equal(wrap(2, 0), 0);
});

test('offsetFor memakai jarak terpendek', () => {
    assert.equal(offsetFor(0, 0, 5), 0);
    assert.equal(offsetFor(1, 0, 5), 1);
    assert.equal(offsetFor(4, 0, 5), -1);
    assert.equal(offsetFor(3, 0, 5), -2);
    assert.equal(offsetFor(0, 0, 1), 0);
});

test('kartu tengah tajam dan penuh, tetangga mengecil, buram, dan pudar', () => {
    const center = slideStyle(0);
    const side = slideStyle(1);
    const far = slideStyle(-2);
    const hidden = slideStyle(3);

    assert.equal(center.opacity, 1);
    assert.equal(center.filter, 'none');
    assert.equal(side.filter, 'blur(2px)');
    assert.ok(side.opacity < center.opacity);
    assert.ok(far.opacity < side.opacity);
    assert.match(far.transform, /^translateX\(-116%\)/);
    assert.equal(hidden.visibility, 'hidden');
});

test('kurangi gerak mematikan buram', () => {
    assert.equal(slideStyle(1, true).filter, 'none');
});

test('swipeDirection butuh ambang', () => {
    assert.equal(swipeDirection(-60), 1);
    assert.equal(swipeDirection(60), -1);
    assert.equal(swipeDirection(10), 0);
});
