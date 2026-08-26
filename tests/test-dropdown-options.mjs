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

// --- Minimal DOM ---------------------------------------------------------
class FakeOption {
  constructor(value) {
    this.value = value;
    this.dataset = { baseText: value };
    this.textContent = value;
    this.hidden = false;
    this.disabled = false;
    this.parent = null;
  }
  remove() {
    if (this.parent) {
      const i = this.parent.children.indexOf(this);
      if (i >= 0) this.parent.children.splice(i, 1);
      this.parent = null;
    }
  }
}

class FakeSelect {
  constructor(values) {
    const placeholder = new FakeOption('');
    placeholder.dataset = {};
    this.children = [placeholder];
    placeholder.parent = this;
    values.forEach(v => {
      const o = new FakeOption(v);
      o.parent = this;
      this.children.push(o);
    });
    this._value = '';
  }
  get options() { return this.children; }
  get value() { return this._value; }
  set value(v) {
    // Matches a real <select>: a value with no matching option clears it.
    this._value = this.children.some(o => o.value === v) ? v : '';
  }
  insertBefore(node, ref) {
    node.remove();
    const i = ref ? this.children.indexOf(ref) : this.children.length;
    this.children.splice(i < 0 ? this.children.length : i, 0, node);
    node.parent = this;
  }
  querySelectorAll() { return this.children.slice(); }
  visible() { return this.children.filter(o => o.value).map(o => o.textContent); }
}

// --- Globals the module graph touches at import time ---------------------
const noop = () => {};
globalThis.window = {
  addEventListener: noop, removeEventListener: noop,
  matchMedia: () => ({ matches: false }),
  location: { search: '', pathname: '/', href: 'https://example.test/' },
  innerWidth: 1280,
  IntersectionObserver: undefined,
};
globalThis.document = {
  addEventListener: noop, removeEventListener: noop,
  querySelector: () => null, querySelectorAll: () => [],
  getElementById: () => null,
  createElement: () => ({ style: {}, dataset: {}, classList: { add: noop, remove: noop, toggle: noop }, appendChild: noop, addEventListener: noop, setAttribute: noop }),
  dispatchEvent: noop,
};
Object.defineProperty(globalThis, 'navigator', { value: { userAgent: 'node' }, configurable: true });
globalThis.rtgData = { settings: {} };
globalThis.CSS = { escape: s => s };

const filters = await import('../frontend/js/modules/filters.js');
const { state } = await import('../frontend/js/modules/state.js');

// --- Wire the fake selects into the module's DOM cache -------------------
const BRANDS = ['Falken', 'Goodyear', 'Michelin', 'Nokian', 'Pirelli', 'Toyo'];
const brandSelect = new FakeSelect(BRANDS);
const categorySelect = new FakeSelect(['All-Season', 'All-Terrain']);

state.domCache.filterBrand = brandSelect;
state.domCache.filterCategory = categorySelect;

let fail = 0;
const check = (label, expected, actual) => {
  const e = JSON.stringify(expected), a = JSON.stringify(actual);
  if (e === a) { console.log(`  ok   ${label}`); return; }
  fail++;
  console.log(`  FAIL ${label}\n       expected: ${e}\n       actual:   ${a}`);
};

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

console.log("size keeps its zeroes");
const sizeSelect = new FakeSelect(['255/65R19', '275/50R22']);
applyOptionCounts(sizeSelect, counts({ '275/50R22': 5 }), false);
check('nothing leaves the size list',
  ['255/65R19 (0)', '275/50R22 (5)'], sizeSelect.visible());

console.log("an option a cached page left hidden is un-hidden on the way back in");
const stale = new FakeSelect(['Michelin']);
stale.children[1].hidden = true;
applyOptionCounts(stale, counts({ Michelin: 2 }), true);
check('hidden flag cleared', false, stale.children[1].hidden);

console.log(fail ? `\n${fail} FAILED` : "\nall passed");
process.exit(fail ? 1 : 0);
