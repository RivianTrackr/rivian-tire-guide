/* jshint esversion: 11 */

/**
 * Compare bar — select, track, and open tire comparisons.
 */

import { state } from './state.js';
import { getDOMElement } from './helpers.js';

export function updateCompareBar() {
  const bar = getDOMElement("compareBar");
  const count = getDOMElement("compareCount");
  if (!bar || !count) return;

  const validCount = Math.max(0, Math.min(4, state.compareList.length));
  count.textContent = `${validCount} of 4 tires selected`;
  bar.style.display = validCount >= 2 ? "flex" : "none";
}

export function openComparison() {
  if (!state.compareList.length) return;

  const validIndexes = state.compareList
    .filter(index => Number.isInteger(index) && index >= 0 && index < state.allRows.length)
    .slice(0, 4);

  if (!validIndexes.length) return;

  try {
    const compareBase = (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.compareUrl) ? rtgData.settings.compareUrl : '/tire-compare/';
    const url = new URL(compareBase, location.origin);
    url.searchParams.set("compare", validIndexes.join(","));
    window.open(url.toString(), "_blank", "noopener,noreferrer");
  } catch (e) {
    console.error('Error creating comparison URL:', e);
  }
}

export function clearCompare() {
  state.compareList = [];
  document.querySelectorAll(".compare-checkbox").forEach(cb => {
    cb.checked = false;
    cb.disabled = false;
  });
  updateCompareBar();
}

export function setupCompareCheckboxes() {
  const checkboxes = document.querySelectorAll(".compare-checkbox:not([data-listener-attached])");
  checkboxes.forEach(cb => {
    cb.dataset.listenerAttached = "true";
    cb.addEventListener("change", () => {
      const index = parseInt(cb.dataset.index);
      if (!Number.isInteger(index) || index < 0) return;

      if (cb.checked) {
        if (state.compareList.length >= 4) {
          cb.checked = false;
          return;
        }
        if (!state.compareList.includes(index)) state.compareList.push(index);
      } else {
        state.compareList = state.compareList.filter(i => i !== index);
      }
      updateCompareBar();
      document.querySelectorAll(".compare-checkbox").forEach(box => {
        if (!box.checked) box.disabled = state.compareList.length >= 4;
      });
    });
  });
}
