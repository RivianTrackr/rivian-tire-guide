/**
 * Tests for the load range filter rules.
 *
 * Run with:  node tests/test-load-range.mjs
 */
import assert from 'node:assert/strict';
import {
  XL_PLUS, XL_PLUS_SET, loadRangeRank, loadRangeMatches, loadRangeOptions, loadRangeCounts,
} from '../frontend/js/modules/load-range.js';

let failures = 0;
function test(name, fn) {
  try { fn(); console.log('  ✓ ' + name); }
  catch (e) { failures++; console.log('  ✗ ' + name + '\n    ' + e.message); }
}

console.log('loadRangeMatches');
test('no filter matches everything, blanks included', () => {
  assert.equal(loadRangeMatches('SL', ''), true);
  assert.equal(loadRangeMatches('', ''), true);
});
test('an exact rating matches case-insensitively and trimmed', () => {
  assert.equal(loadRangeMatches(' xl ', 'XL'), true);
  assert.equal(loadRangeMatches('SL', 'XL'), false);
});
test('XL or higher takes XL and the light-truck letters, not SL or a blank', () => {
  for (const r of XL_PLUS_SET) assert.equal(loadRangeMatches(r, XL_PLUS), true, r);
  assert.equal(loadRangeMatches('SL', XL_PLUS), false);
  assert.equal(loadRangeMatches('', XL_PLUS), false);
});

console.log('loadRangeRank');
test('sidewall order, unknown last', () => {
  assert.ok(loadRangeRank('SL') < loadRangeRank('XL'));
  assert.ok(loadRangeRank('XL') < loadRangeRank('C'));
  assert.ok(loadRangeRank('D') < loadRangeRank('E'));
  assert.equal(loadRangeRank('LT'), -1);
});

console.log('loadRangeOptions');
test('present ratings in sidewall order, XL or higher after XL', () => {
  const opts = loadRangeOptions(['E', 'sl', 'XL', 'XL', '', 'D']).map(o => o.value);
  assert.deepEqual(opts, ['SL', 'XL', XL_PLUS, 'D', 'E']);
});
test('an SL-only catalog offers no XL or higher', () => {
  assert.deepEqual(loadRangeOptions(['SL', 'SL']).map(o => o.value), ['SL']);
});
test('XL or higher still appears when XL itself is absent but E is present', () => {
  assert.deepEqual(loadRangeOptions(['SL', 'E']).map(o => o.value), ['SL', XL_PLUS, 'E']);
});
test('unknown spellings sort after the known ones', () => {
  assert.deepEqual(loadRangeOptions(['LT', 'SL']).map(o => o.value), ['SL', 'LT']);
});

console.log('loadRangeCounts');
test('each rating counts itself and XL or higher sums the qualifying ones', () => {
  const c = loadRangeCounts(['SL', 'XL', 'XL', 'E', '']);
  assert.equal(c.get('SL'), 1);
  assert.equal(c.get('XL'), 2);
  assert.equal(c.get('E'), 1);
  assert.equal(c.get(XL_PLUS), 3);
});

if (failures) { console.log(`\n${failures} load range test(s) failed`); process.exit(1); }
console.log('\nAll load range tests passed');
