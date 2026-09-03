/* jshint esversion: 11 */

/**
 * "Help me choose" — the AI Tire Advisor's guided flow.
 *
 * Three questions (which Rivian, what matters, what budget) and an optional
 * note, posted to the advisor route. Back come up to three picks with a
 * headline, a reason and an honest trade-off each, grounded in the catalog
 * by the server: it only ever names tires that fit. When the site has no
 * API key the same route answers with the guide's own rules, so the button
 * works everywhere and says which it was.
 *
 * The dialog is built on the review modal's shell (Escape closes, Tab is
 * trapped, focus returns to the button). "Show in guide" applies the pick
 * to the live filters and scrolls to its card.
 */

import { state } from './state.js';
import {
  getSelectedVehicle, setActiveVehicle, cascadeVehicleToSizes,
  filterAndRender, restoreDetachedFilterOptions
} from './filters.js';
import { isServerSide, serverSideFilterAndRender } from './server.js';

function settings() {
  const s = (typeof rtgData !== 'undefined' && rtgData.settings) ? rtgData.settings : {};
  return s.advisor || {};
}

function sizeMap() {
  const s = (typeof rtgData !== 'undefined' && rtgData.settings) ? rtgData.settings : {};
  return s.vehicleSizeMap && typeof s.vehicleSizeMap === 'object' && !Array.isArray(s.vehicleSizeMap) ? s.vehicleSizeMap : {};
}

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function money(n) {
  return '$' + Math.round(Number(n) || 0).toLocaleString('en-US');
}

/** The answers as the server expects them. Exported for the test. */
export function collectAnswers(form) {
  const vehicle = form.querySelector('.rtg-adv-seg-btn.is-active')?.dataset.vehicle || '';
  const size = form.querySelector('#rtgAdvSize')?.value || '';
  const priorities = Array.from(form.querySelectorAll('.rtg-adv-chip.is-active')).map(c => c.dataset.priority);
  const budget = form.querySelector('#rtgAdvBudget')?.value || '';
  const notes = (form.querySelector('#rtgAdvNotes')?.value || '').trim().slice(0, 300);
  return { vehicle, size, priorities, budget, notes };
}

function buildForm(form, defaults) {
  const map = sizeMap();
  const vehicles = Object.keys(map);

  // 1. Which Rivian.
  const q1 = el('div', 'rtg-adv-q');
  q1.appendChild(el('div', 'rtg-adv-q-label', '1. Which Rivian?'));
  const seg = el('div', 'rtg-adv-seg');
  seg.setAttribute('role', 'radiogroup');
  seg.setAttribute('aria-label', 'Vehicle');
  const makeSeg = (value, label) => {
    const b = el('button', 'rtg-adv-seg-btn', label);
    b.type = 'button';
    b.dataset.vehicle = value;
    b.setAttribute('role', 'radio');
    b.setAttribute('aria-checked', 'false');
    b.addEventListener('click', () => {
      seg.querySelectorAll('.rtg-adv-seg-btn').forEach(x => {
        x.classList.remove('is-active');
        x.setAttribute('aria-checked', 'false');
      });
      b.classList.add('is-active');
      b.setAttribute('aria-checked', 'true');
      fillSizes(value);
    });
    return b;
  };
  vehicles.forEach(v => seg.appendChild(makeSeg(v, v)));
  seg.appendChild(makeSeg('', 'Not sure'));
  q1.appendChild(seg);

  const sizeWrap = el('div', 'rtg-adv-size');
  const sizeLabel = el('label', 'rtg-adv-sub-label', 'Tire size');
  sizeLabel.setAttribute('for', 'rtgAdvSize');
  const sizeSel = el('select', 'rtg-adv-select');
  sizeSel.id = 'rtgAdvSize';
  sizeWrap.appendChild(sizeLabel);
  sizeWrap.appendChild(sizeSel);
  q1.appendChild(sizeWrap);

  function fillSizes(vehicle) {
    sizeSel.textContent = '';
    const any = el('option', '', vehicle ? `Any ${vehicle} size` : 'Any size');
    any.value = '';
    sizeSel.appendChild(any);
    let sizes = vehicle && Array.isArray(map[vehicle]) ? map[vehicle] : [];
    if (!vehicle) {
      const all = new Set();
      vehicles.forEach(v => (map[v] || []).forEach(s => all.add(s)));
      sizes = Array.from(all).sort();
    }
    sizes.forEach(s => {
      const o = el('option', '', s);
      o.value = s;
      sizeSel.appendChild(o);
    });
  }

  // 2. What matters.
  const q2 = el('div', 'rtg-adv-q');
  q2.appendChild(el('div', 'rtg-adv-q-label', '2. What matters most? Pick up to three.'));
  const chips = el('div', 'rtg-adv-chips');
  const priorities = settings().priorities || {};
  Object.keys(priorities).forEach(key => {
    const c = el('button', 'rtg-adv-chip', priorities[key]);
    c.type = 'button';
    c.dataset.priority = key;
    c.setAttribute('aria-pressed', 'false');
    c.addEventListener('click', () => {
      const on = c.classList.contains('is-active');
      if (!on && chips.querySelectorAll('.is-active').length >= 3) {
        chips.classList.add('is-full');
        setTimeout(() => chips.classList.remove('is-full'), 400);
        return;
      }
      c.classList.toggle('is-active', !on);
      c.setAttribute('aria-pressed', on ? 'false' : 'true');
    });
    chips.appendChild(c);
  });
  q2.appendChild(chips);

  // 3. Budget + notes.
  const q3 = el('div', 'rtg-adv-q');
  q3.appendChild(el('div', 'rtg-adv-q-label', '3. Budget, and anything else we should know?'));
  const budgetSel = el('select', 'rtg-adv-select');
  budgetSel.id = 'rtgAdvBudget';
  budgetSel.setAttribute('aria-label', 'Budget per tire');
  (settings().budgets || []).forEach(b => {
    const o = el('option', '', b.label);
    o.value = b.value;
    budgetSel.appendChild(o);
  });
  q3.appendChild(budgetSel);
  const notes = el('textarea', 'rtg-adv-notes');
  notes.id = 'rtgAdvNotes';
  notes.maxLength = 300;
  notes.rows = 2;
  notes.placeholder = 'Optional. For example: mostly highway, a camper a few times a year, Colorado winters.';
  notes.setAttribute('aria-label', 'Anything else');
  q3.appendChild(notes);

  form.appendChild(q1);
  form.appendChild(q2);
  form.appendChild(q3);

  // Defaults: the vehicle the guide already knows.
  const preset = defaults.vehicle && vehicles.includes(defaults.vehicle) ? defaults.vehicle : '';
  const presetBtn = seg.querySelector(`.rtg-adv-seg-btn[data-vehicle="${CSS.escape(preset)}"]`) || seg.querySelector('.rtg-adv-seg-btn');
  if (presetBtn) presetBtn.click();
}

function renderPicks(container, data) {
  container.textContent = '';
  const live = data.source === 'claude';

  if (data.summary) {
    container.appendChild(el('p', 'rtg-adv-summary', data.summary));
  }

  if (!Array.isArray(data.picks) || !data.picks.length) {
    const empty = el('div', 'rtg-adv-empty');
    empty.appendChild(el('div', 'rtg-adv-empty-title', 'No match yet'));
    empty.appendChild(el('p', 'rtg-adv-empty-text', 'Nothing in the guide fits those answers. Try a wider budget or fewer must-haves.'));
    container.appendChild(empty);
    return;
  }

  const list = el('ol', 'rtg-adv-picks');
  data.picks.forEach((pick, i) => {
    const t = pick.tire || {};
    const item = el('li', 'rtg-adv-pick');

    const rank = el('span', 'rtg-adv-rank', String(i + 1));
    item.appendChild(rank);

    const body = el('div', 'rtg-adv-pick-body');
    const name = el('div', 'rtg-adv-pick-name');
    name.appendChild(el('span', 'rtg-adv-pick-brand', t.brand || ''));
    name.appendChild(el('span', 'rtg-adv-pick-model', t.model || ''));
    body.appendChild(name);

    const chips = el('div', 'rtg-adv-pick-chips');
    (t.vehicles || []).forEach(v => chips.appendChild(el('span', 'rtg-adv-pick-chip is-fit', `Fits ${v}`)));
    if (t.size) chips.appendChild(el('span', 'rtg-adv-pick-chip', t.size));
    if (t.category) chips.appendChild(el('span', 'rtg-adv-pick-chip', t.category));
    if (t.three_pms) chips.appendChild(el('span', 'rtg-adv-pick-chip', '3PMS'));
    body.appendChild(chips);

    const stats = el('div', 'rtg-adv-pick-stats');
    if (t.price > 0) {
      const s = el('span', 'rtg-adv-pick-stat');
      s.appendChild(el('strong', '', money(t.price)));
      s.appendChild(document.createTextNode(` per tire · ${money(t.set_price)} a set`));
      stats.appendChild(s);
    }
    if (t.efficiency) {
      const s = el('span', 'rtg-adv-pick-stat is-eff');
      s.appendChild(el('strong', '', `${Number(t.efficiency).toFixed(2)} mi/kWh`));
      s.appendChild(document.createTextNode(t.efficiency_limited ? ' · limited data' : ` · ${t.efficiency_vehicles} vehicles`));
      stats.appendChild(s);
    }
    if (t.rating && t.rating_count > 0) {
      const s = el('span', 'rtg-adv-pick-stat');
      s.appendChild(el('strong', '', `${t.rating}★`));
      s.appendChild(document.createTextNode(` · ${t.rating_count} owner${t.rating_count === 1 ? '' : 's'}`));
      stats.appendChild(s);
    }
    if (stats.childNodes.length) body.appendChild(stats);

    if (pick.headline) body.appendChild(el('div', 'rtg-adv-pick-headline', pick.headline));
    if (pick.reason) body.appendChild(el('p', 'rtg-adv-pick-reason', pick.reason));
    if (pick.tradeoff) {
      const tr = el('p', 'rtg-adv-pick-tradeoff');
      tr.appendChild(el('strong', '', 'Trade-off: '));
      tr.appendChild(document.createTextNode(pick.tradeoff));
      body.appendChild(tr);
    }

    const actions = el('div', 'rtg-adv-pick-actions');
    if (t.url) {
      const a = el('a', 'rtg-adv-action is-primary', 'View tire');
      a.href = t.url;
      actions.appendChild(a);
    }
    const show = el('button', 'rtg-adv-action', 'Show in guide');
    show.type = 'button';
    show.addEventListener('click', () => showInGuide(t, data.input || {}));
    actions.appendChild(show);
    body.appendChild(actions);

    item.appendChild(body);
    list.appendChild(item);
  });
  container.appendChild(list);

  container.appendChild(el('p', 'rtg-adv-source', live
    ? 'Written by Claude from the guide\'s own numbers. Prices and efficiency come from the catalog, not the model.'
    : 'Ranked by the guide\'s rules. Add an API key in the plugin settings for written advice.'));
}

let closeCurrent = null;

/** Apply the pick to the guide's filters and scroll to its card. */
function showInGuide(tire, input) {
  restoreDetachedFilterOptions();
  const vehicle = input.vehicle && Array.isArray(state.VALID_VEHICLES) && state.VALID_VEHICLES.includes(input.vehicle) ? input.vehicle : '';
  if (vehicle) {
    setActiveVehicle(vehicle, true);
    cascadeVehicleToSizes(vehicle, state.VALID_SIZES || []);
  }
  const sizeEl = document.getElementById('filterSize');
  if (sizeEl) sizeEl.value = tire.size && Array.from(sizeEl.options).some(o => o.value === tire.size) ? tire.size : '';
  const brandEl = document.getElementById('filterBrand');
  if (brandEl) brandEl.value = tire.brand && Array.from(brandEl.options).some(o => o.value === tire.brand) ? tire.brand : '';
  const search = document.getElementById('searchInput');
  if (search) search.value = '';

  state.lastFilterState = null;
  if (isServerSide()) {
    serverSideFilterAndRender();
  } else {
    filterAndRender();
  }
  if (closeCurrent) closeCurrent();

  let tries = 0;
  const find = () => {
    const card = document.querySelector(`.tire-card[data-tire-id="${CSS.escape(tire.tire_id)}"]`);
    if (card) {
      card.scrollIntoView({ behavior: 'smooth', block: 'center' });
      card.classList.add('rtg-advisor-hit');
      setTimeout(() => card.classList.remove('rtg-advisor-hit'), 2400);
      return;
    }
    if (++tries < 12) setTimeout(find, 250);
  };
  setTimeout(find, 150);
}

function openAdvisor(trigger) {
  const existing = document.getElementById('rtg-advisor-modal');
  if (existing) existing.remove();

  const overlay = el('div', 'rtg-review-modal-overlay rtg-adv-overlay');
  overlay.id = 'rtg-advisor-modal';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-labelledby', 'rtgAdvTitle');

  const modal = el('div', 'rtg-review-modal rtg-adv-modal');
  const header = el('div', 'rtg-review-modal-header');
  const title = el('h3', '', 'Help me choose');
  title.id = 'rtgAdvTitle';
  const closeBtn = el('button', 'rtg-review-modal-close');
  closeBtn.type = 'button';
  closeBtn.setAttribute('aria-label', 'Close');
  closeBtn.innerHTML = '&times;';
  header.appendChild(title);
  header.appendChild(closeBtn);

  const body = el('div', 'rtg-adv-body');
  const form = el('form', 'rtg-adv-form');
  form.noValidate = true;
  buildForm(form, { vehicle: getSelectedVehicle() });
  const results = el('div', 'rtg-adv-results');
  results.hidden = true;
  results.setAttribute('aria-live', 'polite');
  body.appendChild(form);
  body.appendChild(results);

  const footer = el('div', 'rtg-review-modal-footer rtg-adv-footer');
  const status = el('span', 'rtg-adv-status');
  const again = el('button', 'rtg-adv-action rtg-adv-again', 'Start over');
  again.type = 'button';
  again.hidden = true;
  const submit = el('button', 'rtg-wn-done rtg-adv-submit', 'Find my tires');
  submit.type = 'submit';
  submit.setAttribute('form', 'rtgAdvForm');
  form.id = 'rtgAdvForm';
  const done = el('button', 'rtg-wn-done rtg-adv-done', 'Done');
  done.type = 'button';
  done.hidden = true;
  footer.appendChild(status);
  footer.appendChild(again);
  footer.appendChild(submit);
  footer.appendChild(done);

  modal.appendChild(header);
  modal.appendChild(body);
  modal.appendChild(footer);
  overlay.appendChild(modal);
  document.body.appendChild(overlay);
  requestAnimationFrame(() => overlay.classList.add('active'));

  const returnFocusTo = trigger || document.activeElement;
  function closeModal() {
    overlay.classList.remove('active');
    document.removeEventListener('keydown', modalKeydown);
    setTimeout(() => overlay.remove(), 200);
    closeCurrent = null;
    if (returnFocusTo && typeof returnFocusTo.focus === 'function') {
      returnFocusTo.focus({ preventScroll: true });
    }
  }
  closeCurrent = closeModal;

  function modalKeydown(e) {
    if (e.key === 'Escape') {
      closeModal();
      return;
    }
    if (e.key !== 'Tab') return;
    const focusables = overlay.querySelectorAll('button:not([disabled]):not([hidden]), select, textarea, a[href], [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  closeBtn.addEventListener('click', closeModal);
  done.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', modalKeydown);
  const firstSeg = form.querySelector('.rtg-adv-seg-btn.is-active') || closeBtn;
  firstSeg.focus();

  again.addEventListener('click', () => {
    results.hidden = true;
    form.hidden = false;
    again.hidden = true;
    done.hidden = true;
    submit.hidden = false;
    status.textContent = '';
    title.textContent = 'Help me choose';
    const firstSegBtn = form.querySelector('.rtg-adv-seg-btn.is-active');
    if (firstSegBtn) firstSegBtn.focus();
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const answers = collectAnswers(form);
    const url = settings().url;
    if (!url) return;

    submit.disabled = true;
    submit.textContent = 'Thinking…';
    status.textContent = settings().live ? 'Reading the catalog and writing your picks.' : 'Ranking the catalog.';

    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(answers),
    })
      .then(r => r.json().then(data => ({ ok: r.ok, status: r.status, data })))
      .then(({ ok, status: code, data }) => {
        submit.disabled = false;
        submit.textContent = 'Find my tires';
        status.textContent = '';
        if (!ok || !data || data.ok === false) {
          status.textContent = (data && data.error) || (code === 429 ? 'Too many requests. Give it a minute.' : 'Something went wrong. Try again.');
          return;
        }
        form.hidden = true;
        submit.hidden = true;
        again.hidden = false;
        done.hidden = false;
        results.hidden = false;
        title.textContent = 'Your picks';
        renderPicks(results, data);
        modal.scrollTop = 0;
        const firstAction = results.querySelector('.rtg-adv-action');
        if (firstAction) firstAction.focus({ preventScroll: true });
      })
      .catch(() => {
        submit.disabled = false;
        submit.textContent = 'Find my tires';
        status.textContent = 'Something went wrong. Try again.';
      });
  });
}

/** Wire the "Help me choose" button. Safe when it isn't on the page. */
export function initAdvisor() {
  const btn = document.getElementById('rtgAdvisorOpen');
  if (!btn) return;
  if (!settings().url) {
    btn.hidden = true;
    return;
  }
  btn.addEventListener('click', () => openAdvisor(btn));
}
