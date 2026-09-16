/**
 * The mini-DOM the frontend module tests run against.
 *
 * The plugin has no browser test harness, so the tests that drive the real
 * filter module (`tests/test-dropdown-options.mjs`, `tests/test-url-filters.mjs`)
 * build just enough of a document here: selects with options and optgroups
 * that behave like a native `<select>` where it matters (setting a value with
 * no matching option clears it), and the globals the module graph touches
 * when it is imported.
 *
 * Usage: call installGlobals() before `await import`-ing a frontend module.
 */

const noop = () => {};

export class FakeNode {
  constructor(tagName) {
    this.tagName = tagName;
    this.dataset = {};
    this.children = [];
    this.parentNode = null;
  }
  remove() {
    if (this.parentNode) {
      const i = this.parentNode.children.indexOf(this);
      if (i >= 0) this.parentNode.children.splice(i, 1);
      this.parentNode = null;
    }
  }
  insertBefore(node, ref) {
    node.remove();
    const i = ref ? this.children.indexOf(ref) : this.children.length;
    this.children.splice(i < 0 ? this.children.length : i, 0, node);
    node.parentNode = this;
  }
  appendChild(node) { this.insertBefore(node, null); }
  set innerHTML(value) {
    if (value !== '') throw new Error('the harness only models innerHTML = ""');
    this.children.forEach(child => { child.parentNode = null; });
    this.children = [];
    this._value = '';
  }
  descendants() {
    return this.children.flatMap(c => [c, ...c.descendants()]);
  }
  querySelectorAll(selector) {
    const wanted = selector.split(',').map(s => s.trim().split(' ').pop().toUpperCase());
    return this.descendants().filter(n => wanted.includes(n.tagName));
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}

export class FakeOption extends FakeNode {
  constructor(value) {
    super('OPTION');
    this.value = value;
    this.dataset = { baseText: value };
    this.textContent = value;
    this.hidden = false;
    this.disabled = false;
  }
}

export class FakeGroup extends FakeNode {
  constructor(label = '', values = []) {
    super('OPTGROUP');
    this.label = label;
    this.value = '';
    values.forEach(v => this.appendChild(new FakeOption(v)));
  }
}

export class FakeSelect extends FakeNode {
  /**
   * @param {Array<string|{label: string, values: string[]}>} entries
   *   Plain values for a flat list, {label, values} for a grouped one.
   */
  constructor(entries) {
    super('SELECT');
    const placeholder = new FakeOption('');
    placeholder.dataset = {};
    this.appendChild(placeholder);
    entries.forEach(entry => {
      this.appendChild(typeof entry === 'string'
        ? new FakeOption(entry)
        : new FakeGroup(entry.label, entry.values));
    });
    this._value = '';
  }
  get options() { return this.querySelectorAll('option'); }
  get value() { return this._value; }
  set value(v) {
    // Matches a real <select>: a value with no matching option clears it.
    this._value = this.options.some(o => o.value === v) ? v : '';
  }
  /** What the popup would show, headings included. */
  visible() {
    return this.children.flatMap(node => node.tagName === 'OPTGROUP'
      ? [`[${node.label}]`, ...node.children.map(o => o.textContent)]
      : (node.value ? [node.textContent] : []));
  }
}

/** A generic element for anything a test doesn't model: a notice, a label. */
export function fakeElement() {
  return {
    style: {}, dataset: {}, textContent: '',
    classList: { add: noop, remove: noop, toggle: noop, contains: () => false },
    appendChild: noop, addEventListener: noop, setAttribute: noop,
  };
}

/**
 * A vehicle toggle button as `getSelectedVehicle()` and `setActiveVehicle()`
 * see it: a data-vehicle, an `active` class, an aria-pressed attribute.
 */
export function fakeVehicleButton(vehicle) {
  const btn = fakeElement();
  btn.dataset.vehicle = vehicle;
  btn.active = false;
  btn.classList = {
    add: noop, remove: noop,
    toggle: (cls, force) => { if (cls === 'active') btn.active = !!force; },
    contains: cls => cls === 'active' && btn.active,
  };
  btn.setAttribute = (name, value) => { btn[name] = value; };
  return btn;
}

/**
 * Install `window`, `document` and friends so a frontend module can be
 * imported. `elements` is a map of id => node that getElementById serves;
 * `vehicleButtons` is the toggle's buttons, for the vehicle selectors.
 */
export function installGlobals({ elements = {}, vehicleButtons = [] } = {}) {
  const storage = new Map();
  globalThis.window = {
    addEventListener: noop, removeEventListener: noop,
    matchMedia: () => ({ matches: false }),
    location: { search: '', pathname: '/', href: 'https://example.test/' },
    innerWidth: 1280,
    IntersectionObserver: undefined,
    localStorage: {
      getItem: key => (storage.has(key) ? storage.get(key) : null),
      setItem: (key, value) => { storage.set(key, String(value)); },
      removeItem: key => { storage.delete(key); },
    },
  };
  globalThis.document = {
    addEventListener: noop, removeEventListener: noop,
    querySelector: selector => (selector === '.rtg-vehicle-btn.active'
      ? vehicleButtons.find(b => b.active) || null
      : null),
    querySelectorAll: selector => (selector === '.rtg-vehicle-btn' ? vehicleButtons : []),
    getElementById: id => elements[id] || null,
    createElement: tag => {
      if (tag === 'option') return new FakeOption('');
      if (tag === 'optgroup') return new FakeGroup();
      return fakeElement();
    },
    dispatchEvent: noop,
  };
  Object.defineProperty(globalThis, 'navigator', { value: { userAgent: 'node' }, configurable: true });
  globalThis.rtgData = { settings: {} };
  globalThis.CSS = { escape: s => s };
  return { elements, vehicleButtons, storage };
}

/** A tiny assertion helper shared by the module tests. */
export function makeChecker() {
  let fail = 0;
  const check = (label, expected, actual) => {
    const e = JSON.stringify(expected), a = JSON.stringify(actual);
    if (e === a) { console.log(`  ok   ${label}`); return; }
    fail++;
    console.log(`  FAIL ${label}\n       expected: ${e}\n       actual:   ${a}`);
  };
  return { check, failed: () => fail };
}
