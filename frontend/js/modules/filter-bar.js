/* jshint esversion: 11 */

/**
 * The filter bar: one row of chips over the real controls.
 *
 * The selects, range inputs and checkboxes the filter pipeline reads are
 * still in the page, inside each chip's popover, so everything that reads
 * or writes them (URL state, analytics, server-side mode, the advisor,
 * shortcode prefilters) is unchanged. This module only does the chrome:
 *
 *  - a chip opens its popover; a list popover is built from the select's
 *    current options at open time, so counts and vehicle cascades hold;
 *  - the price and warranty popovers carry presets beside the slider;
 *  - 3PMS and OEM are press-to-toggle chips over their checkboxes;
 *  - on a phone the chip row scrolls sideways, and any chip (or the
 *    "Filters" button) opens the whole set as a bottom sheet;
 *  - syncFilterBar() writes each chip's value, the filter tally and the
 *    Clear all button after every filter pass.
 *
 * Values move through the controls' own "input" events, which is what the
 * entry point listens to, so a click here is the same as a change there.
 */

import { getDOMElement, updateSliderBackground } from './helpers.js';

const SHEET_QUERY = '(max-width: 600px)';

let bar = null;
let chips = null;
let backdrop = null;
let openItem = null;
let onClearAll = () => {};
let lastFocus = null;

function isSheetMode() {
  return window.matchMedia(SHEET_QUERY).matches;
}

function fire(el, type = 'input') {
  el.dispatchEvent(new Event(type, { bubbles: true }));
}

/* ---------- popovers ---------- */

function closeItem(restoreFocus = false) {
  if (!openItem) return;
  const chip = openItem.querySelector('.rtg-fchip[data-pop]');
  openItem.classList.remove('is-open', 'is-right');
  if (chip) chip.setAttribute('aria-expanded', 'false');
  const item = openItem;
  openItem = null;
  if (restoreFocus && chip) chip.focus({ preventScroll: true });
  return item;
}

function openItemPop(item) {
  if (openItem === item) { closeItem(); return; }
  closeItem();
  const chip = item.querySelector('.rtg-fchip[data-pop]');
  const pop = item.querySelector('.rtg-fpop');
  if (!chip || !pop) return;

  const list = pop.querySelector('.rtg-fopts');
  if (list) buildOptionList(list);

  item.classList.add('is-open');
  chip.setAttribute('aria-expanded', 'true');
  openItem = item;

  // Keep the popover on screen: flip to the chip's right edge if it would
  // run past the viewport.
  const rect = pop.getBoundingClientRect();
  if (rect.right > window.innerWidth - 12) item.classList.add('is-right');

  const first = pop.querySelector('.rtg-fopt[aria-selected="true"], .rtg-fopt, input[type="range"]');
  if (first) first.focus({ preventScroll: true });
}

/**
 * Mirror a select's options as a list of buttons. Optgroups become
 * headings; a "(12)" count at the end of a label becomes a muted figure.
 */
function buildOptionList(list) {
  const select = document.getElementById(list.dataset.for);
  if (!select) return;
  list.innerHTML = '';
  const selected = select.value;

  const addOption = (opt) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'rtg-fopt';
    btn.setAttribute('role', 'option');
    btn.dataset.value = opt.value;
    const isSel = opt.value === selected;
    btn.setAttribute('aria-selected', isSel ? 'true' : 'false');
    if (opt.disabled) btn.disabled = true;

    const text = opt.textContent || '';
    const m = text.match(/^(.*)\s\((\d+)\)$/);
    const label = document.createElement('span');
    label.className = 'rtg-fopt-label';
    label.textContent = m ? m[1] : text;
    btn.appendChild(label);
    if (m) {
      const count = document.createElement('span');
      count.className = 'rtg-fopt-count';
      count.textContent = m[2];
      btn.appendChild(count);
    }
    list.appendChild(btn);
  };

  Array.from(select.children).forEach(child => {
    if (child.tagName === 'OPTGROUP') {
      const opts = Array.from(child.children).filter(o => !o.hidden);
      if (!opts.length) return;
      const head = document.createElement('div');
      head.className = 'rtg-fopt-group';
      head.textContent = child.label;
      list.appendChild(head);
      opts.forEach(addOption);
    } else if (!child.hidden) {
      addOption(child);
    }
  });
}

function pickOption(btn) {
  const list = btn.closest('.rtg-fopts');
  const select = list ? document.getElementById(list.dataset.for) : null;
  if (!select) return;
  if (select.value !== btn.dataset.value) {
    select.value = btn.dataset.value;
    fire(select);
  }
  closeItem(true);
}

function applyPreset(btn) {
  const group = btn.closest('.rtg-fpresets');
  const input = group ? document.getElementById(group.dataset.for) : null;
  if (!input) return;
  const v = btn.dataset.value;
  const next = v === 'max' ? input.max : v === 'min' ? input.min : v;
  if (String(input.value) !== String(next)) {
    input.value = next;
    fire(input);
  }
  syncFilterBar();
}

function resetRange(id) {
  const input = document.getElementById(id);
  if (!input) return;
  const home = id === 'priceMax' ? input.max : input.min;
  if (String(input.value) !== String(home)) {
    input.value = home;
    fire(input);
  }
  syncFilterBar();
}

function toggleCheckbox(chip) {
  const cb = document.getElementById(chip.dataset.toggle);
  if (!cb) return;
  cb.checked = !cb.checked;
  fire(cb);
  syncFilterBar();
}

/* ---------- the phone sheet ---------- */

function openSheet() {
  if (!chips || chips.classList.contains('is-sheet')) return;
  closeItem();
  lastFocus = document.activeElement;
  chips.classList.add('is-sheet');
  chips.setAttribute('role', 'dialog');
  chips.setAttribute('aria-modal', 'true');
  chips.setAttribute('aria-label', 'Filters');
  if (backdrop) backdrop.hidden = false;
  document.body.classList.add('rtg-dialog-open');
  const all = document.getElementById('rtgAllFilters');
  if (all) all.setAttribute('aria-expanded', 'true');
  const close = chips.querySelector('.rtg-sheet-close');
  if (close) close.focus({ preventScroll: true });
}

function closeSheet() {
  if (!chips || !chips.classList.contains('is-sheet')) return;
  chips.classList.remove('is-sheet');
  chips.removeAttribute('role');
  chips.removeAttribute('aria-modal');
  chips.removeAttribute('aria-label');
  if (backdrop) backdrop.hidden = true;
  document.body.classList.remove('rtg-dialog-open');
  const all = document.getElementById('rtgAllFilters');
  if (all) all.setAttribute('aria-expanded', 'false');
  if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus({ preventScroll: true });
  lastFocus = null;
}

/* ---------- state -> chrome ---------- */

function setChipValue(key, value) {
  const item = bar.querySelector(`.rtg-fitem[data-key="${key}"]`);
  if (!item) return;
  const out = item.querySelector('.rtg-fchip-value');
  if (out) out.textContent = value || '';
  item.classList.toggle('is-active', !!value);
}

function shortMiles(n) {
  return n >= 1000 ? `${Math.round(n / 1000)}k` : String(n);
}

export function activeFilterCount() {
  let count = 0;
  if (getDOMElement('searchInput')?.value?.trim()) count++;
  if (document.querySelector('.rtg-vehicle-btn.active:not([data-vehicle=""])')) count++;
  ['filterSize', 'filterBrand', 'filterCategory'].forEach(id => { if (getDOMElement(id)?.value) count++; });
  const price = getDOMElement('priceMax');
  if (price && Number(price.value) < Number(price.max)) count++;
  const warranty = getDOMElement('warrantyMin');
  if (warranty && Number(warranty.value) > 0) count++;
  if (getDOMElement('filter3pms')?.checked) count++;
  if (getDOMElement('filterOEM')?.checked) count++;
  return count;
}

/**
 * Bring the chips, tally and Clear all into step with the controls. Called
 * after every filter pass and after every click in the bar.
 *
 * @param {number|null} total Tires now showing, when the caller knows it.
 */
export function syncFilterBar(total = null) {
  if (!bar) return;

  ['size', 'brand', 'category'].forEach(key => {
    const select = getDOMElement(key === 'size' ? 'filterSize' : key === 'brand' ? 'filterBrand' : 'filterCategory');
    const opt = select && select.value ? select.options[select.selectedIndex] : null;
    setChipValue(key, opt ? (opt.dataset.baseText || opt.value) : '');
  });

  const price = getDOMElement('priceMax');
  if (price) {
    const v = Number(price.value), max = Number(price.max);
    setChipValue('price', v < max ? `Under $${v}` : '');
    bar.querySelectorAll('.rtg-fpresets[data-for="priceMax"] .rtg-fpreset').forEach(b => {
      const on = b.dataset.value === 'max' ? v >= max : Number(b.dataset.value) === v;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  const warranty = getDOMElement('warrantyMin');
  if (warranty) {
    const v = Number(warranty.value);
    setChipValue('warranty', v > 0 ? `${shortMiles(v)}+ mi` : '');
    bar.querySelectorAll('.rtg-fpresets[data-for="warrantyMin"] .rtg-fpreset').forEach(b => {
      const on = b.dataset.value === 'min' ? v <= 0 : Number(b.dataset.value) === v;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  bar.querySelectorAll('.rtg-fchip[data-toggle]').forEach(chip => {
    const cb = document.getElementById(chip.dataset.toggle);
    const on = !!(cb && cb.checked);
    chip.setAttribute('aria-pressed', on ? 'true' : 'false');
    chip.closest('.rtg-fitem')?.classList.toggle('is-active', on);
  });

  const count = activeFilterCount();
  const tally = document.getElementById('rtgFilterTally');
  if (tally) {
    tally.textContent = count ? `${count} filter${count === 1 ? '' : 's'}` : '';
    tally.hidden = !count;
  }
  bar.querySelectorAll('[data-clear-all]').forEach(btn => {
    if (btn.classList.contains('rtg-sheet-clear')) btn.disabled = !count;
    else btn.hidden = !count;
  });
  const badge = bar.querySelector('.rtg-fchip-badge');
  if (badge) {
    badge.textContent = String(count);
    badge.hidden = !count;
  }
  const all = document.getElementById('rtgAllFilters');
  if (all) all.classList.toggle('is-active', count > 0);

  if (total !== null) {
    const out = document.getElementById('rtgSheetCount');
    if (out) out.textContent = String(total);
  }

  // The open list may have gone stale (a vehicle change re-scopes sizes).
  if (openItem) {
    const list = openItem.querySelector('.rtg-fopts');
    if (list) buildOptionList(list);
  }
}

/* ---------- wiring ---------- */

export function initFilterBar(options = {}) {
  bar = document.getElementById('rtgFilterBar');
  if (!bar) return;
  chips = document.getElementById('rtgFilterChips');
  backdrop = document.getElementById('rtgSheetBackdrop');
  if (typeof options.onClearAll === 'function') onClearAll = options.onClearAll;

  bar.addEventListener('click', (e) => {
    const target = e.target;

    const opt = target.closest('.rtg-fopt');
    if (opt && bar.contains(opt)) { pickOption(opt); return; }

    const preset = target.closest('.rtg-fpreset');
    if (preset) { applyPreset(preset); return; }

    const reset = target.closest('[data-reset]');
    if (reset) { resetRange(reset.dataset.reset); return; }

    if (target.closest('[data-pop-close]')) { closeItem(true); return; }

    const toggle = target.closest('.rtg-fchip[data-toggle]');
    if (toggle) { toggleCheckbox(toggle); return; }

    const popChip = target.closest('.rtg-fchip[data-pop]');
    if (popChip) {
      if (isSheetMode()) { openSheet(); return; }
      openItemPop(popChip.closest('.rtg-fitem'));
      return;
    }

    if (target.closest('#rtgAllFilters')) { openSheet(); return; }
    if (target.closest('[data-sheet-close]')) { closeSheet(); return; }

    if (target.closest('[data-clear-all]')) {
      onClearAll();
      syncFilterBar();
      return;
    }
  });

  if (backdrop) backdrop.addEventListener('click', closeSheet);

  // A click anywhere else closes an open popover.
  document.addEventListener('click', (e) => {
    if (openItem && !openItem.contains(e.target)) closeItem();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (chips && chips.classList.contains('is-sheet')) { closeSheet(); return; }
    if (openItem) closeItem(true);
  });

  // Leaving phone width with the sheet open: it becomes the row again.
  window.matchMedia(SHEET_QUERY).addEventListener('change', (mq) => {
    if (!mq.matches) closeSheet();
    else closeItem();
  });

  // The sliders' own handlers repaint the value; the chip follows.
  bar.addEventListener('input', () => syncFilterBar());

  ['priceMax', 'warrantyMin'].forEach(id => {
    const el = document.getElementById(id);
    if (el) updateSliderBackground(el);
  });

  syncFilterBar();
}
