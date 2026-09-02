/**
 * Tests for the price presentation module: the set-of-four figure and the
 * "price as of" hint with its stale threshold.
 *
 * Run with:  node tests/test-pricing.mjs
 */
import assert from 'node:assert/strict';
import { setPrice, formatSetPrice, formatWholePrice, parseMysqlDate, formatAsOf, priceFreshness, SET_QUANTITY, AS_OF_MIN_DAYS } from '../frontend/js/modules/pricing.js';

let failures = 0;
function test(name, fn) {
  try {
    fn();
    console.log(`  ✓ ${name}`);
  } catch (e) {
    failures++;
    console.log(`  ✗ ${name}\n    ${e.message}`);
  }
}

console.log('setPrice / formatSetPrice');
test('a set is four tires', () => assert.equal(SET_QUANTITY, 4));
test('multiplies and rounds to the dollar', () => {
  assert.equal(setPrice('289'), 1156);
  assert.equal(setPrice(289.99), 1160);
  assert.equal(setPrice('289.24'), 1157);
});
test('no price, no set', () => {
  assert.equal(setPrice(''), 0);
  assert.equal(setPrice('0'), 0);
  assert.equal(setPrice(null), 0);
  assert.equal(formatSetPrice('abc'), '');
});
test('formats with thousands separators', () => assert.equal(formatSetPrice('289'), '$1,156'));

console.log('formatWholePrice');
test('rounds to the dollar', () => {
  assert.equal(formatWholePrice('442.4'), '$442');
  assert.equal(formatWholePrice(274.99), '$275');
  assert.equal(formatWholePrice('1234.5'), '$1,235');
});
test('no price, no text', () => {
  assert.equal(formatWholePrice(''), '');
  assert.equal(formatWholePrice(0), '');
});

console.log('parseMysqlDate');
test('reads the calendar date of a MySQL datetime', () => {
  const d = parseMysqlDate('2026-08-28 14:03:00');
  assert.equal(d.getFullYear(), 2026);
  assert.equal(d.getMonth(), 7);
  assert.equal(d.getDate(), 28);
});
test('accepts a bare date', () => assert.equal(parseMysqlDate('2026-08-28').getDate(), 28));
test('rejects empties and the zero date', () => {
  assert.equal(parseMysqlDate(''), null);
  assert.equal(parseMysqlDate(null), null);
  assert.equal(parseMysqlDate('0000-00-00 00:00:00'), null);
  assert.equal(parseMysqlDate('yesterday'), null);
});

console.log('formatAsOf');
test('omits the year when it is this year', () => {
  assert.equal(formatAsOf(new Date(2026, 7, 28), new Date(2026, 8, 1)), 'Aug 28');
});
test('includes the year when it is not', () => {
  assert.equal(formatAsOf(new Date(2025, 4, 12), new Date(2026, 8, 1)), 'May 12, 2025');
});

console.log('priceFreshness');
const now = new Date(2026, 8, 1); // Sep 1, 2026
test('a synced price is fresh within the threshold', () => {
  const f = priceFreshness({ priceSyncedAt: '2026-08-28 03:00:00', updatedAt: '2026-06-01 00:00:00' }, 90, now);
  assert.equal(f.label, 'as of Aug 28');
  assert.equal(f.stale, false);
  assert.equal(f.ageDays, 4);
  assert.equal(f.show, false, 'four days old has not earned its line');
});
test('the date shows once it is old enough, before it is stale', () => {
  assert.equal(AS_OF_MIN_DAYS, 30);
  const f = priceFreshness({ updatedAt: '2026-07-15 00:00:00' }, 90, now);
  assert.equal(f.show, true);
  assert.equal(f.stale, false);
});
test('a manual tire falls back to updated_at', () => {
  const f = priceFreshness({ priceSyncedAt: '', updatedAt: '2026-07-04 10:00:00' }, 90, now);
  assert.equal(f.label, 'as of Jul 4');
  assert.equal(f.stale, false);
});
test('the later of the two timestamps wins', () => {
  const f = priceFreshness({ priceSyncedAt: '2026-05-01 00:00:00', updatedAt: '2026-08-30 00:00:00' }, 90, now);
  assert.equal(f.label, 'as of Aug 30');
});
test('older than the threshold is stale', () => {
  const f = priceFreshness({ priceSyncedAt: '', updatedAt: '2026-05-12 00:00:00' }, 90, now);
  assert.equal(f.stale, true);
  assert.equal(f.show, true);
  assert.equal(f.label, 'as of May 12');
});
test('a threshold of zero never marks stale', () => {
  assert.equal(priceFreshness({ updatedAt: '2020-01-01 00:00:00' }, 0, now).stale, false);
});
test('nothing known, nothing said', () => {
  const f = priceFreshness({ priceSyncedAt: '', updatedAt: '' }, 90, now);
  assert.equal(f.label, '');
  assert.equal(f.stale, false);
  assert.equal(f.show, false);
  assert.equal(f.date, null);
});
test('tolerates a missing tire', () => assert.equal(priceFreshness(null, 90, now).label, ''));

if (failures > 0) {
  console.log(`\n${failures} failure(s)`);
  process.exit(1);
}
console.log('\nAll pricing tests passed');
