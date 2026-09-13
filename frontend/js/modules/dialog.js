/* jshint esversion: 11 */

/**
 * The dialog shell.
 *
 * One builder for every dialog in the guide: Help me choose, the Changelog,
 * the (i) info notes and the photo lightbox, on the guide page and on the
 * tire page (which loads this same file as rtg-dialog.js). Each caller
 * passes a title and content; the shell supplies the overlay, the header
 * with its close button, the scrolling body, the footer, the entry and exit
 * animation, Escape, the Tab trap, the body scroll lock and the return of
 * focus to whatever opened it. The styling is frontend/css/rtg-dialog.css.
 *
 *   const dlg = openDialog({ title: 'Load index', size: 'sm', returnFocus: btn });
 *   dlg.body.appendChild(paragraph);
 *   dlg.footer.appendChild(button);
 *   dlg.close();
 */

const EXIT_MS = 300;

// How many dialogs hold the body scroll lock. The phone filter sheet takes
// the same class by hand and never nests with these.
let openCount = 0;

function lock() {
  openCount += 1;
  document.body.classList.add('rtg-dialog-open');
}

function unlock() {
  openCount = Math.max(0, openCount - 1);
  if (openCount === 0 && !document.querySelector('.rtg-filter-chips.is-sheet')) {
    document.body.classList.remove('rtg-dialog-open');
  }
}

const FOCUSABLE = 'button:not([disabled]):not([hidden]), a[href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function visible(node) {
  return !!(node.offsetWidth || node.offsetHeight || node.getClientRects().length);
}

/**
 * Build and show a dialog.
 *
 * @param {Object}  opts
 * @param {string}  [opts.id]           id for the overlay; an open dialog with the same id is replaced.
 * @param {string}  [opts.title]        Header title. Omit for a lightbox.
 * @param {string}  [opts.titleId]      id for the title element (aria-labelledby points at it).
 * @param {string}  [opts.ariaLabel]    Used when there is no title.
 * @param {string}  [opts.size]         'sm' (400), default (520), 'lg' (600) or 'xl' (800).
 * @param {string}  [opts.className]    Extra classes for the panel.
 * @param {string}  [opts.bodyClass]    Extra classes for the body.
 * @param {boolean} [opts.lightbox]     Photo mode: no header, a floating close button.
 * @param {Element[]} [opts.headerActions] Buttons placed between the title and the close button.
 * @param {Element} [opts.returnFocus]  Where focus goes on close; defaults to the active element.
 * @param {Function} [opts.onClose]     Called once, after the exit animation starts.
 * @returns {{overlay: Element, panel: Element, header: Element|null, title: Element|null, body: Element, footer: Element, closeBtn: Element, close: Function, setTitle: Function}}
 */
export function openDialog(opts = {}) {
  if (opts.id) {
    const existing = document.getElementById(opts.id);
    if (existing && existing._rtgDialogClose) existing._rtgDialogClose();
    else if (existing) existing.remove();
  }

  const returnFocusTo = opts.returnFocus && typeof opts.returnFocus.focus === 'function'
    ? opts.returnFocus
    : document.activeElement;

  const overlay = document.createElement('div');
  overlay.className = 'rtg-dialog-overlay' + (opts.lightbox ? ' rtg-dialog-overlay--lightbox' : '');
  if (opts.id) overlay.id = opts.id;

  const panel = document.createElement('div');
  panel.className = 'rtg-dialog'
    + (opts.size ? ' rtg-dialog--' + opts.size : '')
    + (opts.lightbox ? ' rtg-dialog--lightbox' : '')
    + (opts.className ? ' ' + opts.className : '');
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-modal', 'true');

  const closeBtn = document.createElement('button');
  closeBtn.type = 'button';
  closeBtn.className = 'rtg-dialog-close';
  closeBtn.setAttribute('aria-label', 'Close');

  let header = null;
  let title = null;
  if (!opts.lightbox) {
    header = document.createElement('div');
    header.className = 'rtg-dialog-header';
    title = document.createElement('h3');
    title.className = 'rtg-dialog-title';
    title.textContent = opts.title || '';
    if (opts.titleId) title.id = opts.titleId;
    header.appendChild(title);
    (opts.headerActions || []).forEach(node => header.appendChild(node));
    header.appendChild(closeBtn);
    panel.appendChild(header);
  }

  if (title && title.id) {
    panel.setAttribute('aria-labelledby', title.id);
  } else if (opts.ariaLabel || opts.title) {
    panel.setAttribute('aria-label', opts.ariaLabel || opts.title);
  }

  const body = document.createElement('div');
  body.className = 'rtg-dialog-body' + (opts.bodyClass ? ' ' + opts.bodyClass : '');
  panel.appendChild(body);

  // The footer stays out of the way until something is put in it
  // (rtg-dialog.css hides it while it is empty).
  const footer = document.createElement('div');
  footer.className = 'rtg-dialog-footer';
  panel.appendChild(footer);

  if (opts.lightbox) panel.appendChild(closeBtn);

  overlay.appendChild(panel);
  document.body.appendChild(overlay);
  lock();

  let closed = false;
  function close() {
    if (closed) return;
    closed = true;
    document.removeEventListener('keydown', onKeydown);
    overlay.classList.remove('is-open');
    overlay._rtgDialogClose = null;
    unlock();
    setTimeout(() => overlay.remove(), EXIT_MS);
    if (returnFocusTo && typeof returnFocusTo.focus === 'function' && document.contains(returnFocusTo)) {
      returnFocusTo.focus({ preventScroll: true });
    }
    if (typeof opts.onClose === 'function') opts.onClose();
  }
  overlay._rtgDialogClose = close;

  function onKeydown(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }
    if (e.key !== 'Tab') return;
    const focusables = Array.from(panel.querySelectorAll(FOCUSABLE)).filter(visible);
    if (!focusables.length) {
      e.preventDefault();
      return;
    }
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
      e.preventDefault();
      first.focus();
    }
  }

  closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) close();
  });
  document.addEventListener('keydown', onKeydown);

  requestAnimationFrame(() => {
    overlay.classList.add('is-open');
    // A caller that already moved focus inside keeps it there.
    if (panel.contains(document.activeElement)) return;
    const preferred = panel.querySelector('[autofocus]');
    (preferred || closeBtn).focus({ preventScroll: true });
  });

  return {
    overlay,
    panel,
    header,
    title,
    body,
    footer,
    closeBtn,
    close,
    setTitle(text) {
      if (title) title.textContent = text;
    },
  };
}

/** A secondary or primary footer button, styled by the shell. */
export function dialogButton(label, { primary = false, type = 'button', className = '' } = {}) {
  const btn = document.createElement('button');
  btn.type = type;
  btn.className = 'rtg-dialog-btn' + (primary ? ' rtg-dialog-btn-primary' : '') + (className ? ' ' + className : '');
  btn.textContent = label;
  return btn;
}
