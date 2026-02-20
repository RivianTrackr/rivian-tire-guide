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

  modal.style.display = "flex";
  document.body.style.overflow = "hidden";

  const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
      modal.style.display = "none";
      document.body.style.overflow = "";
      document.removeEventListener('keydown', closeOnEscape);
    }
  };
  document.addEventListener('keydown', closeOnEscape);

  modal.onclick = (e) => {
    if (e.target === modal) {
      modal.style.display = "none";
      document.body.style.overflow = "";
      document.removeEventListener('keydown', closeOnEscape);
    }
  };
}
