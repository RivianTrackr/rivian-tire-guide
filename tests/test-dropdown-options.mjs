/**
 * Tests for the filter dropdowns' option list.
 *
 * These drive the real module — applyOptionCounts() and
 * restoreDetachedFilterOptions() as the guide calls them — against a mini-DOM
 * built here, because the plugin has no browser test harness and the two bugs
 * this covers were both invisible to reading:
 *
 *   1. Marking an option hidden doesn't remove it from a native select popup
 *      in every browser, so "(0)" rows stayed on screen where they shouldn't.
 *   2. Setting select.value to an option that isn't in the DOM silently
 *      clears the select, which would have made the back button drop a filter
 *      it was returning to.
 *
 * Run with:  node tests/test-dropdown-options.mjs
 * Exit code 0 = all tests passed, 1 = one or more failures.
 */

// --- Minimal DOM: tests/lib/fake-dom.mjs ---------------------------------
import { FakeSelect, installGlobals, makeChecker } from './lib/fake-dom.mjs';

installGlobals();

const filters = await import('../frontend/js/modules/filters.js');
const { state } = await import('../frontend/js/modules/state.js');

// --- Wire the fake selects into the module's DOM cache -------------------
const BRANDS = ['Falken', 'Goodyear', 'Michelin', 'Nokian', 'Pirelli', 'Toyo'];
const brandSelect = new FakeSelect(BRANDS);
const categorySelect = new FakeSelect(['All-Season', 'All-Terrain']);

state.domCache.filterBrand = brandSelect;
state.domCache.filterCategory = categorySelect;

const { check, failed } = makeChecker();

const { applyOptionCounts, restoreDetachedFilterOptions } = filters;
const counts = m => new Map(Object.entries(m));

console.log("a size filter leaves only the brands that make one");
applyOptionCounts(brandSelect, counts({ Michelin: 1, Pirelli: 2 }), true);
check('empties are gone from the DOM, not just marked hidden',
  ['Michelin (1)', 'Pirelli (2)'], brandSelect.visible());

console.log("what you selected stays listed even at zero");
brandSelect.value = 'Pirelli';
applyOptionCounts(brandSelect, counts({ Michelin: 1 }), true);
check('the selected brand survives', ['Michelin (1)', 'Pirelli (0)'], brandSelect.visible());
check('and is still the value', 'Pirelli', brandSelect.value);

console.log("brands come back, in alphabetical order, when they have tires again");
brandSelect.value = '';
applyOptionCounts(brandSelect, counts({ Falken: 3, Michelin: 1, Toyo: 2 }), true);
check('restored in place, not appended',
  ['Falken (3)', 'Michelin (1)', 'Toyo (2)'], brandSelect.visible());

console.log("everything comes back");
applyOptionCounts(brandSelect, counts({ Falken: 1, Goodyear: 1, Michelin: 1, Nokian: 1, Pirelli: 1, Toyo: 1 }), true);
check('the full list, in order',
  ['Falken (1)', 'Goodyear (1)', 'Michelin (1)', 'Nokian (1)', 'Pirelli (1)', 'Toyo (1)'],
  brandSelect.visible());

console.log("a filter restored from the URL can still be selected");
applyOptionCounts(brandSelect, counts({ Michelin: 1 }), true);
check('Toyo is out of the list', ['Michelin (1)'], brandSelect.visible());
brandSelect.value = 'Toyo';
check('selecting a detached brand would silently fail', '', brandSelect.value);
restoreDetachedFilterOptions();
brandSelect.value = 'Toyo';
check('after restoring, the back button gets its filter', 'Toyo', brandSelect.value);

console.log("sizes drop out of their wheel group, and take an emptied heading with them");
const makeSizes = () => new FakeSelect([
  { label: '19" Wheels', values: ['255/65R19', '275/60R19'] },
  { label: '22" Wheels', values: ['275/50R22'] },
]);

let sizeSelect = makeSizes();
state.domCache.filterSize = sizeSelect;

applyOptionCounts(sizeSelect, counts({ '255/65R19': 4, '275/50R22': 5 }), true);
check('an empty size goes, its heading stays while a sibling remains',
  ['[19" Wheels]', '255/65R19 (4)', '[22" Wheels]', '275/50R22 (5)'],
  sizeSelect.visible());

applyOptionCounts(sizeSelect, counts({ '275/50R22': 5 }), true);
check('a heading with nothing left under it goes too',
  ['[22" Wheels]', '275/50R22 (5)'], sizeSelect.visible());

applyOptionCounts(sizeSelect, counts({ '255/65R19': 1, '275/60R19': 2, '275/50R22': 5 }), true);
check('the group comes back, in its place, with its sizes in order',
  ['[19" Wheels]', '255/65R19 (1)', '275/60R19 (2)', '[22" Wheels]', '275/50R22 (5)'],
  sizeSelect.visible());

console.log("a size still listed can be selected, and survives at zero");
sizeSelect.value = '275/60R19';
applyOptionCounts(sizeSelect, counts({ '275/50R22': 5 }), true);
check('the chosen size holds its place under its heading',
  ['[19" Wheels]', '275/60R19 (0)', '[22" Wheels]', '275/50R22 (5)'],
  sizeSelect.visible());
check('and is still the value', '275/60R19', sizeSelect.value);

console.log("changing vehicle rebuilds the size list in place");
// The real cascade wipes this same element and refills it, so anything held
// back from the old list is a stale node that must never be put back.
const { populateSizeDropdownGrouped } = filters;
sizeSelect = new FakeSelect([]);
state.domCache.filterSize = sizeSelect;

populateSizeDropdownGrouped('filterSize', ['255/65R19', '275/60R19', '275/50R22']);
applyOptionCounts(sizeSelect, counts({ '275/50R22': 5 }), true);
check('the wide list narrows to what has tires',
  ['[22" Wheels]', '275/50R22 (5)'], sizeSelect.visible());

populateSizeDropdownGrouped('filterSize', ['275/50R22', '285/45R22']);
check('the rebuilt list is only the new vehicle\'s fitments',
  ['[22" Wheels]', '275/50R22', '285/45R22'], sizeSelect.visible());

restoreDetachedFilterOptions();
check('and nothing from the old list comes back with it',
  ['[22" Wheels]', '275/50R22', '285/45R22'], sizeSelect.visible());

console.log("an option a cached page left hidden is un-hidden on the way back in");
const stale = new FakeSelect(['Michelin']);
stale.children[1].hidden = true;
applyOptionCounts(stale, counts({ Michelin: 2 }), true);
check('hidden flag cleared', false, stale.children[1].hidden);

console.log(failed() ? `\n${failed()} FAILED` : "\nall passed");
process.exit(failed() ? 1 : 0);
