/* jshint esversion: 11 */

/**
 * Photo lightbox: a tire card's image at full size, in the shared dialog
 * shell's lightbox mode (darker overlay, no header, a floating close
 * button). Escape, the backdrop and the button close it; focus returns
 * to the image that opened it.
 */

import { safeString } from './helpers.js';
import { safeImageURL } from './validation.js';
import { openDialog } from './dialog.js';

const FALLBACK = "https://riviantrackr.com/assets/tire-guide/images/image404.jpg";

export function openImageModal(src, altText, trigger) {
  const safeSrc = safeImageURL(src);
  if (!safeSrc) {
    console.warn('Invalid image URL for modal:', src);
    return;
  }

  const alt = safeString(altText, 200);
  const dlg = openDialog({
    id: 'rtg-image-modal',
    lightbox: true,
    ariaLabel: alt || 'Image preview',
    returnFocus: trigger && typeof trigger.focus === 'function' ? trigger : document.activeElement,
  });

  const img = document.createElement('img');
  img.className = 'rtg-dialog-image';
  img.src = safeSrc;
  // Property and attribute sinks take plain text (see cards.js).
  img.alt = alt;
  img.onerror = () => {
    const safeFallback = safeImageURL(FALLBACK);
    if (safeFallback && img.src !== safeFallback) {
      img.src = safeFallback;
      img.alt = "Image not available";
    }
  };
  // A tap on the photo itself closes too, as it always has.
  img.addEventListener('click', dlg.close);
  dlg.body.appendChild(img);
}
