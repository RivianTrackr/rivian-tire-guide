/**
 * Tests for the efficiency sample-size judgement.
 *
 * Run with:  node tests/test-efficiency.mjs
 */
import assert from 'node:assert/strict';
import { isLimitedSample, MIN_VEHICLES, MIN_MILES } from '../frontend/js/modules/efficiency.js';

let failures = 0;
function test(name, fn) {
  try { fn(); console.log(`  ✓ ${name}`); }
  catch (e) { failures++; console.log(`  ✗ ${name}\n    ${e.message}`); }
}

console.log('isLimitedSample');
test('thresholds', () => { assert.equal(MIN_VEHICLES, 3); assert.equal(MIN_MILES, 2000); });
test('one vehicle over a few hundred miles is limited', () => assert.equal(isLimitedSample(756, 1), true));
test('few vehicles is limited even with many miles', () => assert.equal(isLimitedSample(50000, 2), true));
test('few miles is limited even with many vehicles', () => assert.equal(isLimitedSample(900, 10), true));
test('a real sample is not', () => assert.equal(isLimitedSample(68324, 64), false));
test('exactly at the floors is not', () => assert.equal(isLimitedSample(2000, 3), false));
test('an unknown sample is not judged', () => {
  assert.equal(isLimitedSample(0, 0), false);
  assert.equal(isLimitedSample('', ''), false);
  assert.equal(isLimitedSample(undefined, undefined), false);
});
test('one known side is judged on its own', () => {
  assert.equal(isLimitedSample(0, 1), true);
  assert.equal(isLimitedSample(500, 0), true);
  assert.equal(isLimitedSample(0, 10), false);
});

if (failures > 0) { console.log(`\n${failures} failure(s)`); process.exit(1); }
console.log('\nAll efficiency tests passed');
