/**
 * Tests for the load-index fitment module.
 *
 * The rule the guide's tooltip has always stated — R1 needs 116, R2 needs
 * 112 — is now applied to the card, the tire page and the compare page.
 * These pin down the edges: how the stored load index is read, which
 * vehicles are judged with and without a toggle pressed, and the sentence
 * the warning row shows.
 *
 * Run with:  node tests/test-fitment.mjs
 */
import assert from 'node:assert/strict';
import { parseLoadIndex, fitmentShortfalls, describeShortfalls, thirdPartyEntry, thirdPartyFits, rimInches, describeThirdPartyFits } from '../frontend/js/modules/fitment.js';

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

const MAP = { R1: ['275/65R20', '275/50R22'], R2: ['235/60R19', '255/50R20'] };
const FLOORS = { R1: 116, R2: 112 };

console.log('parseLoadIndex');
test('reads a bare number', () => assert.equal(parseLoadIndex('116'), 116));
test('reads the single-tire figure of an LT dual/single pair', () => assert.equal(parseLoadIndex('121/118'), 121));
test('ignores a trailing speed rating', () => assert.equal(parseLoadIndex('116T'), 116));
test('reads the first figure of an annotated value', () => assert.equal(parseLoadIndex('116 (2756 lb)'), 116));
test('returns 0 for empty, null, and text', () => {
  assert.equal(parseLoadIndex(''), 0);
  assert.equal(parseLoadIndex(null), 0);
  assert.equal(parseLoadIndex('n/a'), 0);
});
test('rejects a figure outside the tire load-index range', () => assert.equal(parseLoadIndex('9999'), 0));

console.log('fitmentShortfalls');
test('with a vehicle pressed, judges that vehicle alone', () => {
  const out = fitmentShortfalls({ loadIndex: '110', size: '275/65R20' }, MAP, FLOORS, 'R1');
  assert.deepEqual(out, [{ vehicle: 'R1', floor: 116 }]);
});
test('with a vehicle pressed, passes when the index meets the floor', () => {
  assert.deepEqual(fitmentShortfalls({ loadIndex: '116', size: '275/65R20' }, MAP, FLOORS, 'R1'), []);
});
test('with a vehicle pressed, judges even a size not in its list (the toggle already narrowed sizes)', () => {
  assert.deepEqual(fitmentShortfalls({ loadIndex: '100', size: '999/99R99' }, MAP, FLOORS, 'R2'), [{ vehicle: 'R2', floor: 112 }]);
});
test('without a vehicle, judges only vehicles whose sizes include this tire', () => {
  assert.deepEqual(fitmentShortfalls({ loadIndex: '110', size: '275/65R20' }, MAP, FLOORS, ''), [{ vehicle: 'R1', floor: 116 }]);
  assert.deepEqual(fitmentShortfalls({ loadIndex: '110', size: '235/60R19' }, MAP, FLOORS, ''), [{ vehicle: 'R2', floor: 112 }]);
});
test('without a vehicle, a size no vehicle takes raises nothing', () => {
  assert.deepEqual(fitmentShortfalls({ loadIndex: '90', size: '205/55R16' }, MAP, FLOORS, ''), []);
});
test('a size both vehicles take can fall short for both', () => {
  const map = { R1: ['275/50R20'], R2: ['275/50R20'] };
  assert.deepEqual(fitmentShortfalls({ loadIndex: '108', size: '275/50R20' }, map, FLOORS, ''),
    [{ vehicle: 'R1', floor: 116 }, { vehicle: 'R2', floor: 112 }]);
});
test('size matching ignores case and whitespace', () => {
  assert.deepEqual(fitmentShortfalls({ loadIndex: '110', size: ' 275/65r20 ' }, MAP, FLOORS, ''), [{ vehicle: 'R1', floor: 116 }]);
});
test('an unknown load index is never a warning', () => {
  assert.deepEqual(fitmentShortfalls({ loadIndex: '', size: '275/65R20' }, MAP, FLOORS, 'R1'), []);
});
test('a vehicle with no floor is skipped', () => {
  assert.deepEqual(fitmentShortfalls({ loadIndex: '90', size: '275/65R20' }, MAP, { R1: 0 }, 'R1'), []);
  assert.deepEqual(fitmentShortfalls({ loadIndex: '90', size: '275/65R20' }, MAP, {}, 'R3'), []);
});
test('tolerates missing maps and floors', () => {
  assert.deepEqual(fitmentShortfalls({ loadIndex: '90', size: '275/65R20' }, null, null, ''), []);
  assert.deepEqual(fitmentShortfalls(null, MAP, FLOORS, 'R1'), []);
});

console.log('describeShortfalls');
test('one vehicle', () => {
  assert.equal(describeShortfalls('110', [{ vehicle: 'R1', floor: 116 }]), 'Load index 110 is below the R1 minimum of 116.');
});
test('two vehicles', () => {
  assert.equal(describeShortfalls('108T', [{ vehicle: 'R1', floor: 116 }, { vehicle: 'R2', floor: 112 }]),
    'Load index 108 is below the R1 (116) and R2 (112) minimums.');
});
test('nothing to say', () => {
  assert.equal(describeShortfalls('116', []), '');
  assert.equal(describeShortfalls('116', null), '');
});

if (failures > 0) {
  console.log(`\n${failures} failure(s)`);
  process.exit(1);
}
console.log('thirdPartyFits');
const THIRD = { R2: { '245/60R18': { wheel: '18" aftermarket wheels', note: 'Needs an 8.5-inch or wider wheel.' } } };
test('a listed size on a chosen vehicle is a third-party fit with its note', () => {
  assert.deepEqual(thirdPartyFits({ size: '245/60R18' }, THIRD, 'R2'), [{ vehicle: 'R2', wheel: '18" aftermarket wheels', note: 'Needs an 8.5-inch or wider wheel.' }]);
});
test('matches the size regardless of case and whitespace', () => {
  assert.equal(thirdPartyEntry(' 245/60r18 ', 'R2', THIRD).wheel, '18" aftermarket wheels');
});
test('a factory size raises nothing', () => {
  assert.deepEqual(thirdPartyFits({ size: '255/50R20' }, THIRD, 'R2'), []);
});
test('a chosen vehicle with no third-party sizes raises nothing', () => {
  assert.deepEqual(thirdPartyFits({ size: '245/60R18' }, THIRD, 'R1'), []);
});
test('without a vehicle, every vehicle in the map is judged', () => {
  const map = { R1: { '245/60R18': { wheel: 'a', note: '' } }, R2: { '245/60R18': { wheel: 'b', note: '' } } };
  assert.deepEqual(thirdPartyFits({ size: '245/60R18' }, map).map(f => f.vehicle), ['R1', 'R2']);
});
test('tolerates a missing or malformed map', () => {
  assert.deepEqual(thirdPartyFits({ size: '245/60R18' }, null, 'R2'), []);
  assert.deepEqual(thirdPartyFits({ size: '245/60R18' }, { R2: 'nope' }, 'R2'), []);
  assert.deepEqual(thirdPartyFits({}, THIRD, 'R2'), []);
});

console.log('describeThirdPartyFits');
test('reads the rim diameter out of the size', () => {
  assert.equal(rimInches('245/60R18'), 18);
  assert.equal(rimInches('275/65r18'), 18);
  assert.equal(rimInches('nope'), 0);
});
test('one vehicle', () => {
  assert.equal(
    describeThirdPartyFits('245/60R18', [{ vehicle: 'R2' }]),
    'Fits R2 on 3rd-party 18" wheels only. Not a factory size, so fitment may vary.'
  );
});
test('two vehicles', () => {
  assert.equal(
    describeThirdPartyFits('245/60R18', [{ vehicle: 'R1' }, { vehicle: 'R2' }]),
    'Fits R1 and R2 on 3rd-party 18" wheels only. Not a factory size, so fitment may vary.'
  );
});
test('a size with no rim diameter drops the inches', () => {
  assert.equal(describeThirdPartyFits('odd', [{ vehicle: 'R2' }]), 'Fits R2 on 3rd-party wheels only. Not a factory size, so fitment may vary.');
});
test('nothing to say when nothing fits', () => assert.equal(describeThirdPartyFits('245/60R18', []), ''));

console.log('\nAll fitment tests passed');
