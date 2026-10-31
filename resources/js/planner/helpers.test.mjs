import assert from 'node:assert/strict';
import { test } from 'node:test';

import { conflicts, dropPosition, joinWords, rupiah, stepErrors } from './helpers.mjs';

test('step 1 requires a day count inside the product range', () => {
    assert.equal(stepErrors(1, { days_count: '' }).length, 1);
    assert.equal(stepErrors(1, { days_count: '15' }, { maxDays: 14 }).length, 1);
    assert.deepEqual(stepErrors(1, { days_count: '3' }), []);
});

test('step 2 needs a theme or surprise and caps the theme count', () => {
    assert.equal(stepErrors(2, { themes: [] }).length, 1);
    assert.deepEqual(stepErrors(2, { themes: [], surprise: true }), []);
    assert.equal(stepErrors(2, { themes: ['a', 'b', 'c', 'd', 'e', 'f'] }, { maxThemes: 5 }).length, 1);
});

test('step 5 checks the nightly price range only when hotels are wanted', () => {
    assert.deepEqual(stepErrors(5, { want_hotel: false, price_min: '900', price_max: '100' }), []);
    assert.equal(stepErrors(5, { want_hotel: true, price_min: '900', price_max: '100' }).length, 1);
    assert.deepEqual(stepErrors(5, { want_hotel: true, price_min: '100', price_max: '' }), []);
});

test('conflicts warn about many regions in a short trip', () => {
    const warnings = conflicts({ days_count: '2', region_ids: ['1', '2', '3'], pace: 'relaxed' });
    assert.equal(warnings.length, 2);
    assert.deepEqual(conflicts({ days_count: '4', region_ids: ['1'], pace: 'balanced' }), []);
});

test('public transport is flagged as not routed', () => {
    assert.equal(conflicts({ days_count: '3', region_ids: [], transport_mode: 'public' }).length, 1);
});

test('formatting helpers', () => {
    assert.equal(rupiah('1500000'), 'Rp1.500.000');
    assert.equal(rupiah(''), '');
    assert.equal(joinWords(['A', 'B', 'C']), 'A, B, dan C');
    assert.equal(joinWords(['A', 'B']), 'A dan B');
});

test('drop position is 1-based and ignores the dragged item', () => {
    assert.equal(dropPosition([1, 2, 3], 3, 1, false), 1);
    assert.equal(dropPosition([1, 2, 3], 1, 3, true), 3);
    assert.equal(dropPosition([1, 2], 9, null, false), 3);
});
