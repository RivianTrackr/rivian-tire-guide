/* jshint esversion: 11 */

/**
 * Image modal — full-screen image preview.
 */

import { getDOMElement, escapeHTML, safeString } from './helpers.js';
import { safeImageURL } from './validation.js';

export function openImageModal(src, altText) {
  const safeSrc = safeImageURL(src);
  if (!safeSrc) {
    console.warn('Invalid image URL for modal:', src);
    return;
  }

  const modal = getDOMElement("imageModal");
  const img = getDOMElement("modalImage");
  if (!modal || !img) return;

  img.src = safeSrc;
  img.alt = escapeHTML(safeString(altText, 200));

  img.onerror = () => {
    const fallback = "https://riviantrackr.com/assets/tire-guide/images/image404.jpg";
    const safeFallback = safeImageURL(fallback);
    if (safeFallback) {
      img.src = safeFallback;
      img.alt = "Image not available";
    }
  };

  // Remember the element that opened the modal so we can return focus to it
  // when the modal closes — proper accessibility for keyboard users.
  const returnFocusTo = document.activeElement;

  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-label', escapeHTML(safeString(altText, 200)) || 'Image preview');
  modal.style.display = "flex";
  document.body.style.overflow = "hidden";

  // Make the modal itself focusable and focus it so keyboard users land inside
  // the dialog context, not the page below it.
  if (!modal.hasAttribute('tabindex')) {
    modal.setAttribute('tabindex', '-1');
  }
  modal.focus({ preventScroll: true });

  const closeModal = () => {
    modal.style.display = "none";
    document.body.style.overflow = "";
    document.removeEventListener('keydown', onKeydown);
    if (returnFocusTo && typeof returnFocusTo.focus === 'function') {
      returnFocusTo.focus({ preventScroll: true });
    }
  };

  const onKeydown = (e) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      closeModal();
      return;
    }
    // Trap Tab inside the modal — only one focusable element (the modal
    // itself), so Tab/Shift+Tab both land back on the modal.
    if (e.key === 'Tab') {
      e.preventDefault();
      modal.focus({ preventScroll: true });
    }
  };
  document.addEventListener('keydown', onKeydown);

  modal.onclick = (e) => {
    if (e.target === modal) {
      closeModal();
    }
  };
}
