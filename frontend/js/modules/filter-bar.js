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
 *  - the chip row is one line that scrolls sideways when the chips
 *    outgrow it, with a fade at each edge that still has more; because
 *    the row clips what overflows it, the popover and the toggle chips'
 *    tooltip are fixed-position and placed here from the chip's rect;
 *  - on a phone any chip (or the "Filters" button) opens the whole set
 *    as a bottom sheet;
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

const EDGE = 12;

/**
 * Put a fixed-position box under a chip: left-aligned with it, flipped to
 * its right edge when that would run past the viewport, and above it
 * when there is no room below but more above. Returns the box's rect.
 */
function placeUnder(box, chip, gap) {
  const r = chip.getBoundingClientRect();
  box.style.maxHeight = '';
  const w = box.offsetWidth;
  const h = box.offsetHeight;
  let left = r.left;
  if (left + w > window.innerWidth - EDGE) left = r.right - w;
  left = Math.max(EDGE, left);

  const below = window.innerHeight - r.bottom - gap - EDGE;
  const above = r.top - gap - EDGE;
  let top;
  if (h <= below || below >= above) {
    top = r.bottom + gap;
    if (h > below) box.style.maxHeight = `${Math.max(120, below)}px`;
  } else {
    top = Math.max(EDGE, r.top - gap - h);
    if (h > above) box.style.maxHeight = `${Math.max(120, above)}px`;
  }
  box.style.left = `${Math.round(left)}px`;
  box.style.top = `${Math.round(top)}px`;
  return r;
}

function clearPlacement(box) {
  box.style.left = '';
  box.style.top = '';
  box.style.maxHeight = '';
}

/** A chip scrolled out of the row has nothing on screen to hang from. */
function chipInRow(rect) {
  if (!chips) return true;
  const c = chips.getBoundingClientRect();
  return rect.right > c.left && rect.left < c.right;
}

function placePop() {
  if (!openItem) return;
  const chip = openItem.querySelector('.rtg-fchip[data-pop]');
  const pop = openItem.querySelector('.rtg-fpop');
  if (!chip || !pop) return;
  if (!chipInRow(placeUnder(pop, chip, 8))) closeItem();
}

function closeItem(restoreFocus = false) {
  if (!openItem) return;
  const chip = openItem.querySelector('.rtg-fchip[data-pop]');
  const pop = openItem.querySelector('.rtg-fpop');
  openItem.classList.remove('is-open');
  if (chip) chip.setAttribute('aria-expanded', 'false');
  if (pop) clearPlacement(pop);
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
  placePop();

  const first = pop.querySelector('.rtg-fopt[aria-selected="true"], .rtg-fopt, input[type="range"]');
  if (first) first.focus({ preventScroll: true });
}

/* ---------- the toggle chips' tooltip ---------- */

/**
 * The tip shows on hover and keyboard focus through CSS alone; this only
 * puts it under the chip, centred, kept inside the viewport.
 */
function placeTip(item) {
  const chip = item.querySelector('.rtg-fchip');
  const tip = item.querySelector('.rtg-fchip-tip');
  if (!chip || !tip || isSheetMode()) return;
  const r = chip.getBoundingClientRect();
  const w = Math.min(280, window.innerWidth - 32);
  const left = Math.min(Math.max(16, r.left + r.width / 2 - w / 2), window.innerWidth - 16 - w);
  tip.style.left = `${Math.round(left)}px`;
  tip.style.top = `${Math.round(r.bottom + 8)}px`;
}

/* ---------- the row's scroll edges ---------- */

/** Show a fade only at an edge that has more chips past it. */
function syncScrollEdges() {
  if (!chips || chips.classList.contains('is-sheet')) return;
  const max = chips.scrollWidth - chips.clientWidth;
  chips.classList.toggle('is-scroll-start', chips.scrollLeft <= 1);
  chips.classList.toggle('is-scroll-end', chips.scrollLeft >= max - 1);
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
    placePop();
  }
  // A chip's width changes with its value, so the row's overflow may have.
  syncScrollEdges();
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

  // The popover and the tip are fixed, so they follow the chip through
  // any scroll (the page's or the row's, hence capture) and a resize.
  window.addEventListener('scroll', placePop, { capture: true, passive: true });
  window.addEventListener('resize', () => { placePop(); syncScrollEdges(); });
  if (chips) chips.addEventListener('scroll', syncScrollEdges, { passive: true });
  bar.addEventListener('mouseover', (e) => {
    const item = e.target.closest('.rtg-fitem-toggle');
    if (item) placeTip(item);
  });
  bar.addEventListener('focusin', (e) => {
    const item = e.target.closest('.rtg-fitem-toggle');
    if (item) placeTip(item);
  });

  // The sliders' own handlers repaint the value; the chip follows.
  bar.addEventListener('input', () => syncFilterBar());

  ['priceMax', 'warrantyMin'].forEach(id => {
    const el = document.getElementById(id);
    if (el) updateSliderBackground(el);
  });

  syncFilterBar();
}
