/* jshint esversion: 11 */

/**
 * "What's new" — the owner-facing release notes, opened from the pill in
 * the filter header. The notes are loaded once from the REST endpoint
 * (already rendered to safe HTML by RTG_Whats_New, the same view the
 * standalone page draws from) and shown in the shared dialog shell:
 * Escape closes, Tab is trapped, focus returns to the pill.
 *
 * A dot on the pill means the newest release is one this browser hasn't
 * opened yet. That's remembered in localStorage, the way the vehicle
 * toggle is: a preference of this browser, nothing the server needs.
 */

import { openDialog } from './dialog.js';

export const SEEN_STORAGE_KEY = 'rtg_seen_version';

function settings() {
  return (typeof rtgData !== 'undefined' && rtgData.settings) ? rtgData.settings : {};
}

export function readSeenVersion() {
  try {
    return window.localStorage.getItem(SEEN_STORAGE_KEY) || '';
  } catch (e) {
    return '';
  }
}

export function writeSeenVersion(version) {
  try {
    window.localStorage.setItem(SEEN_STORAGE_KEY, String(version || ''));
  } catch (e) {
    // Private mode or storage disabled: the dot just shows every visit.
  }
}

/** True when the newest release is one this browser hasn't opened yet. */
export function hasUnseen(latest, seen) {
  return Boolean(latest) && latest !== seen;
}

let fetchPromise = null;

function loadNotes() {
  if (fetchPromise) return fetchPromise;
  const url = settings().whatsNewRest;
  if (!url) return Promise.reject(new Error('no endpoint'));
  fetchPromise = fetch(url, { credentials: 'same-origin' })
    .then(r => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
    .catch(err => {
      fetchPromise = null;
      throw err;
    });
  return fetchPromise;
}

/** Mirror of RTG_Whats_New::render_list(); the fields are pre-rendered HTML. */
export function renderReleases(container, releases) {
  container.textContent = '';
  if (!Array.isArray(releases) || !releases.length) {
    const p = document.createElement('p');
    p.className = 'rtg-wn-empty';
    p.textContent = 'Nothing to report yet.';
    container.appendChild(p);
    return;
  }
  releases.forEach(r => {
    const article = document.createElement('article');
    article.className = 'rtg-wn-release';

    const head = document.createElement('header');
    head.className = 'rtg-wn-release-head';
    const version = document.createElement('span');
    version.className = 'rtg-wn-version';
    version.textContent = r.version || '';
    head.appendChild(version);
    const date = document.createElement('time');
    date.className = 'rtg-wn-date';
    if (r.date) date.setAttribute('datetime', r.date);
    date.textContent = r.date_display || r.date || '';
    head.appendChild(date);
    if (r.latest) {
      const latest = document.createElement('span');
      latest.className = 'rtg-wn-latest';
      latest.textContent = 'Latest';
      head.appendChild(latest);
    }
    article.appendChild(head);

    if (r.intro) {
      const intro = document.createElement('p');
      intro.className = 'rtg-wn-intro';
      intro.innerHTML = r.intro;
      article.appendChild(intro);
    }

    if (Array.isArray(r.items) && r.items.length) {
      const list = document.createElement('ul');
      list.className = 'rtg-wn-list';
      r.items.forEach(item => {
        const li = document.createElement('li');
        const strong = document.createElement('strong');
        strong.innerHTML = item.lead || '';
        li.appendChild(strong);
        if (item.detail) {
          li.appendChild(document.createTextNode(' '));
          const span = document.createElement('span');
          span.innerHTML = item.detail;
          li.appendChild(span);
        }
        list.appendChild(li);
      });
      article.appendChild(list);
    }
    container.appendChild(article);
  });
}

function openWhatsNew(trigger) {
  const dlg = openDialog({
    id: 'rtg-whats-new-modal',
    title: 'Changelog',
    titleId: 'rtgWhatsNewTitle',
    size: 'lg',
    bodyClass: 'rtg-wn-body',
    returnFocus: trigger,
  });
  const overlay = dlg.overlay;
  const body = dlg.body;
  const loading = document.createElement('p');
  loading.className = 'rtg-wn-loading';
  loading.textContent = 'Loading the latest changes…';
  body.appendChild(loading);

  loadNotes().then(data => {
    if (!overlay.isConnected) return;
    renderReleases(body, data.releases);
    const pageUrl = settings().whatsNewUrl;
    if (pageUrl) {
      const foot = document.createElement('p');
      foot.className = 'rtg-wn-page-foot';
      const link = document.createElement('a');
      link.className = 'rtg-wn-page-link';
      link.href = pageUrl;
      link.textContent = 'Open as a page';
      foot.appendChild(link);
      body.appendChild(foot);
    }
    if (data.latest) {
      writeSeenVersion(data.latest);
      updateDot();
    }
  }).catch(() => {
    if (!overlay.isConnected) return;
    body.textContent = '';
    const p = document.createElement('p');
    p.className = 'rtg-wn-empty';
    p.textContent = "The notes couldn't be loaded. ";
    const pageUrl = settings().whatsNewUrl;
    if (pageUrl) {
      const a = document.createElement('a');
      a.href = pageUrl;
      a.textContent = 'Open the page instead.';
      p.appendChild(a);
    }
    body.appendChild(p);
  });
}

function updateDot() {
  const btn = document.getElementById('rtgWhatsNew');
  if (!btn) return;
  const unseen = hasUnseen(settings().whatsNewVersion, readSeenVersion());
  btn.classList.toggle('has-unseen', unseen);
  const dot = btn.querySelector('.rtg-whats-new-dot');
  if (dot) dot.hidden = !unseen;
  btn.setAttribute('aria-label', unseen ? "Changelog (new updates)" : "Changelog");
}

/** Wire the filter-header pill. Safe to call when the pill isn't on the page. */
export function initWhatsNew() {
  const btn = document.getElementById('rtgWhatsNew');
  if (!btn) return;
  updateDot();
  btn.addEventListener('click', () => openWhatsNew(btn));
}
