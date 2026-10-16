/* jshint esversion: 11 */

/**
 * Saved tires in the guide (2.14.0).
 *
 * A member's shortlist. The heart on a card sits in the title row beside
 * Compare and Share; the "Saved" chip in the filter bar shows only the
 * list. Members only: a signed-out tap opens a sign-in prompt in the
 * dialog shell, with the tire carried on the login URL so the save
 * completes on return. The list of IDs comes with the page (rtgData) and
 * is refreshed from the ratings response, which is never cached per user.
 *
 * Page scripts that own the "what to re-render when the list changes"
 * decision register it with setOnChange().
 */

import { state } from './state.js';
import { rtgIcon, safeString, getDOMElement } from './helpers.js';
import { VALIDATION_PATTERNS } from './validation.js';
import { openDialog, dialogButton } from './dialog.js';

const RETURN_PARAM = 'rtg_save';
let onChange = () => {};
let toastTimer = null;

function settings() {
  return (typeof rtgData !== 'undefined' && rtgData.settings) ? rtgData.settings : {};
}

function auth() {
  return (typeof tireRatingAjax !== 'undefined' && tireRatingAjax) ? tireRatingAjax : {};
}

export function isLoggedIn() {
  const a = auth();
  return state.isLoggedIn || a.is_logged_in === true || a.is_logged_in === '1' || a.is_logged_in === 1;
}

export function isSaved(tireId) {
  return state.savedIds.has(tireId);
}

export function savedCount() {
  return state.savedIds.size;
}

/**
 * Replace the known list (from the page, or a ratings response) and repaint
 * every heart and the chip. Only applies when signed in: a cached page may
 * carry nothing, and a signed-out visitor has no list.
 */
export function setSavedIds(ids) {
  state.savedIds = new Set((Array.isArray(ids) ? ids : []).filter(id => typeof id === 'string' && VALIDATION_PATTERNS.tireId.test(id)));
  refreshSavedButtons();
  syncSavedChip();
}

export function setOnChange(fn) {
  if (typeof fn === 'function') onChange = fn;
}

/* ---------- the heart on a card ---------- */

/**
 * The card's heart: a 32px tool button like Compare and Share. Filled when
 * the tire is on the list. Signed out, the tap opens the sign-in prompt.
 */
export function createSaveTool(tireId, brand, model) {
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'tire-card-tool tire-card-tool-save';
  btn.dataset.tireId = tireId;
  btn.innerHTML = rtgIcon('heart-outline', 15, 'tire-card-tool-heart-off') + rtgIcon('heart', 15, 'tire-card-tool-heart-on');
  paintButton(btn, isSaved(tireId), `${safeString(brand)} ${safeString(model)}`.trim());
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (!isLoggedIn()) {
      openSignInDialog({ tireId, name: `${safeString(brand)} ${safeString(model)}`.trim(), returnFocus: btn });
      return;
    }
    toggleSaved(tireId);
  });
  return btn;
}

function paintButton(btn, saved, name) {
  btn.classList.toggle('is-saved', saved);
  btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
  btn.title = saved ? 'Saved. Remove from your saved tires' : 'Save this tire';
  btn.setAttribute('aria-label', (saved ? 'Remove ' : 'Save ') + (name || 'this tire') + (saved ? ' from your saved tires' : ''));
}

export function refreshSavedButtons() {
  document.querySelectorAll('.tire-card-tool-save[data-tire-id]').forEach(btn => {
    const id = btn.dataset.tireId;
    const card = btn.closest('.tire-card');
    const name = card ? `${card.querySelector('.tire-card-brand')?.textContent || ''} ${card.querySelector('.tire-card-model')?.textContent || ''}`.trim() : '';
    paintButton(btn, isSaved(id), name);
  });
}

/* ---------- saving ---------- */

/**
 * Save or remove a tire. Optimistic: the heart and the chip repaint at
 * once and roll back if the server says no.
 *
 * @param {string}  tireId
 * @param {boolean} [want] true to save, false to remove; omitted toggles.
 * @returns {Promise<boolean>} the saved state after the call.
 */
export function toggleSaved(tireId, want) {
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) return Promise.resolve(false);
  const a = auth();
  if (!a.ajaxurl || !a.nonce) return Promise.resolve(isSaved(tireId));

  const before = isSaved(tireId);
  const next = typeof want === 'boolean' ? want : !before;
  if (next) state.savedIds.add(tireId); else state.savedIds.delete(tireId);
  refreshSavedButtons();
  syncSavedChip();
  if (next) showSavedToast(); else dismissToast();

  const formData = new FormData();
  formData.append('action', 'rtg_toggle_saved_tire');
  formData.append('nonce', a.nonce);
  formData.append('tire_id', tireId);
  formData.append('saved', next ? '1' : '0');

  return fetch(a.ajaxurl, { method: 'POST', body: formData, credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data || !data.success) {
        if (before) state.savedIds.add(tireId); else state.savedIds.delete(tireId);
        refreshSavedButtons();
        syncSavedChip();
        if (data && data.data && data.data.code === 'login_required') {
          state.isLoggedIn = false;
          openSignInDialog({ tireId });
        }
        return before;
      }
      const saved = !!data.data.saved;
      if (saved) state.savedIds.add(tireId); else state.savedIds.delete(tireId);
      refreshSavedButtons();
      syncSavedChip();
      // With the Saved filter on, a removed tire leaves the list.
      if (!saved && getDOMElement('filterSaved')?.checked) onChange();
      return saved;
    })
    .catch(() => {
      if (before) state.savedIds.add(tireId); else state.savedIds.delete(tireId);
      refreshSavedButtons();
      syncSavedChip();
      return before;
    });
}

/* ---------- the toast ---------- */

function showSavedToast() {
  dismissToast();
  const toast = document.createElement('div');
  toast.className = 'rtg-save-toast';
  toast.setAttribute('role', 'status');
  toast.innerHTML = rtgIcon('heart', 15, 'rtg-save-toast-icon');
  const text = document.createElement('span');
  text.className = 'rtg-save-toast-text';
  text.textContent = 'Saved to your tires.';
  toast.appendChild(text);
  const url = settings().savedTiresUrl;
  if (url) {
    const link = document.createElement('a');
    link.className = 'rtg-save-toast-link';
    link.href = url;
    link.textContent = `View saved (${savedCount()})`;
    toast.appendChild(link);
  }
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add('is-visible'));
  toastTimer = setTimeout(dismissToast, 4000);
}

function dismissToast() {
  clearTimeout(toastTimer);
  document.querySelectorAll('.rtg-save-toast').forEach(t => {
    t.classList.remove('is-visible');
    setTimeout(() => t.remove(), 300);
  });
}

/* ---------- the sign-in prompt ---------- */

/**
 * Signed out: a short interruption in the dialog shell, not a redirect.
 * The sign-in link carries the tire so the save completes on return.
 */
export function openSignInDialog({ tireId = '', name = '', returnFocus = null } = {}) {
  const a = auth();
  const dlg = openDialog({ id: 'rtgSaveSignIn', title: 'Save this tire', size: 'sm', returnFocus });

  const p = document.createElement('p');
  p.className = 'rtg-save-dialog-text';
  p.textContent = (name ? `${name}: saved tires ` : 'Saved tires ')
    + 'live with your RivianTrackr account, so they follow you from your phone to your desk. Sign in and '
    + (tireId ? 'this one is saved as soon as you land back here.' : 'your list is right here.');
  dlg.body.appendChild(p);

  dlg.footer.appendChild(dialogButton('Not now', { className: 'rtg-save-dialog-later' }));
  dlg.footer.lastChild.addEventListener('click', () => dlg.close());

  const signIn = document.createElement('a');
  signIn.className = 'rtg-dialog-btn rtg-dialog-btn-primary';
  signIn.innerHTML = rtgIcon('right-to-bracket', 14) + ' Sign in to save';
  signIn.href = loginUrlFor(tireId, a.login_url);
  dlg.footer.appendChild(signIn);
}

// The site's login, returning to this page with the tire to save on it.
function loginUrlFor(tireId, loginUrl) {
  try {
    const back = new URL(window.location.href);
    if (tireId) back.searchParams.set(RETURN_PARAM, tireId); else back.searchParams.delete(RETURN_PARAM);
    const login = new URL(loginUrl || '/wp-login.php', window.location.origin);
    login.searchParams.set('redirect_to', back.toString());
    return login.toString();
  } catch (e) {
    return loginUrl || '/wp-login.php';
  }
}

/* ---------- the "Saved" chip ---------- */

export function syncSavedChip() {
  const chip = document.querySelector('.rtg-fchip[data-toggle="filterSaved"]');
  if (!chip) return;
  const n = savedCount();
  const badge = chip.querySelector('.rtg-fchip-count');
  if (badge) {
    badge.textContent = String(n);
    badge.hidden = !isLoggedIn() || n === 0;
  }
  const empty = isLoggedIn() && n === 0;
  chip.classList.toggle('is-empty', empty);
  chip.setAttribute('aria-disabled', empty ? 'true' : 'false');
  chip.closest('.rtg-fitem')?.classList.toggle('is-empty', empty);
}

/**
 * Wire the chip and complete a save carried back from the login page.
 * Called once by the guide's init, before the first render.
 */
export function initSaved() {
  state.savedIds = new Set();
  if (isLoggedIn()) setSavedIds(settings().savedTireIds || []);
  else syncSavedChip();

  const chip = document.querySelector('.rtg-fchip[data-toggle="filterSaved"]');
  if (chip) {
    // Before the filter bar's own handler: signed out, the tap asks to sign
    // in; signed in with nothing saved, it says where the heart is.
    chip.addEventListener('click', (e) => {
      if (isLoggedIn() && savedCount() > 0) return;
      e.preventDefault();
      e.stopPropagation();
      if (!isLoggedIn()) {
        openSignInDialog({ returnFocus: chip });
      } else {
        showHintToast('Tap the heart on a tire to save it here.');
      }
    }, true);
  }

  // Back from the login page with a tire to save.
  try {
    const url = new URL(window.location.href);
    const pending = url.searchParams.get(RETURN_PARAM);
    if (pending) {
      url.searchParams.delete(RETURN_PARAM);
      window.history.replaceState(window.history.state, '', url.toString());
      if (isLoggedIn() && VALIDATION_PATTERNS.tireId.test(pending) && !isSaved(pending)) {
        toggleSaved(pending, true);
      }
    }
  } catch (e) { /* leave the address as it is */ }
}

function showHintToast(message) {
  dismissToast();
  const toast = document.createElement('div');
  toast.className = 'rtg-save-toast';
  toast.setAttribute('role', 'status');
  toast.innerHTML = rtgIcon('heart-outline', 15, 'rtg-save-toast-icon');
  const text = document.createElement('span');
  text.className = 'rtg-save-toast-text';
  text.textContent = message;
  toast.appendChild(text);
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add('is-visible'));
  toastTimer = setTimeout(dismissToast, 3500);
}
