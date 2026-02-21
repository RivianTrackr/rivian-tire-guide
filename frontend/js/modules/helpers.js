/* jshint esversion: 11 */

/**
 * Small shared utility functions used across modules.
 */

import { state } from './state.js';

// Theme color helper — reads CSS custom properties set by admin.
export function rtgColor(name) {
  return getComputedStyle(document.documentElement).getPropertyValue('--rtg-' + name).trim();
}

// Font Awesome icon helper — returns an <i> tag with the correct FA classes.
export function rtgIcon(name, size, cls) {
  var faPrefix = 'fa-solid';
  var faName = 'fa-' + name;
  if (name === 'heart-outline') { faPrefix = 'fa-regular'; faName = 'fa-heart'; }
  else if (name === 'arrow-up-right') { faName = 'fa-up-right-from-square'; }
  else if (name === 'trash') { faName = 'fa-trash-can'; }
  else if (name === 'share') { faName = 'fa-share-nodes'; }
  var classStr = faPrefix + ' ' + faName;
  if (cls) classStr += ' ' + cls;
  var style = size ? ' style="font-size:' + size + 'px"' : '';
  return '<i class="' + classStr + '"' + style + ' aria-hidden="true"></i>';
}

// Security: Enhanced HTML escaping
export function escapeHTML(str) {
  if (typeof str !== 'string') return '';
  return String(str).replace(/[&<>"'\/]/g, function (s) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
      '/': '&#x2F;'
    }[s];
  });
}

// Security: Safe string conversion with length limits
export function safeString(input, maxLength = 200) {
  if (typeof input !== "string") return "";
  const cleaned = String(input).trim();
  return cleaned.length > maxLength ? cleaned.substring(0, maxLength) : cleaned;
}

// Cached DOM element lookup
export function getDOMElement(id) {
  if (!state.domCache[id]) {
    state.domCache[id] = document.getElementById(id);
  }
  return state.domCache[id];
}

// Debounce utility
export function debounce(fn, delay) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn.apply(this, args), delay);
  };
}

// SVG star path and reusable markup
export const STAR_PATH = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';

export function starSVGMarkup(size = 22) {
  return `<svg viewBox="0 0 24 24" width="${size}" height="${size}" aria-hidden="true">` +
    `<path class="star-bg" d="${STAR_PATH}" fill="none" stroke="currentColor" stroke-width="1.5"/>` +
    `<path class="star-fill" d="${STAR_PATH}" fill="currentColor"/>` +
    `<path class="star-half" d="${STAR_PATH}" fill="currentColor" style="clip-path:inset(0 50% 0 0)"/>` +
    `</svg>`;
}

// Slider background update
export function updateSliderBackground(slider) {
  const min = parseFloat(slider.min);
  const max = parseFloat(slider.max);
  const val = parseFloat(slider.value);
  const percent = ((val - min) / (max - min)) * 100;
  slider.style.setProperty('--percent', `${percent}%`);
}

// Style a button with theme colors
export function styleButton(button) {
  button.style.backgroundColor = rtgColor('bg-primary');
  button.style.color = rtgColor('text-primary');
  button.style.padding = "8px 16px";
  button.style.border = "none";
  button.style.borderRadius = "6px";
  button.style.cursor = "pointer";
  button.style.fontWeight = "600";
  button.onmouseover = () => { button.style.backgroundColor = rtgColor('accent'); button.style.color = '#0f172a'; };
  button.onmouseout = () => { button.style.backgroundColor = rtgColor('bg-primary'); button.style.color = rtgColor('text-primary'); };
}
