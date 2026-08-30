/* jshint esversion: 11 */

/**
 * Compare bar — select, track, and open tire comparisons.
 *
 * Selections are keyed on tire_id (row[0]), not positions in state.allRows:
 * positions don't exist in server-side pagination mode (allRows stays empty)
 * and shift whenever the catalog changes, which made shared ?compare= links
 * show different tires over time.
 */

import { state } from './state.js';
import { getDOMElement } from './helpers.js';
import { VALIDATION_PATTERNS } from './validation.js';

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

  const validIds = state.compareList
    .filter(id => typeof id === 'string' && VALIDATION_PATTERNS.tireId.test(id))
    .slice(0, 4);

  if (!validIds.length) return;

  try {
    const compareBase = (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.compareUrl) ? rtgData.settings.compareUrl : '/tire-compare/';
    const url = new URL(compareBase, location.origin);
    url.searchParams.set("compare", validIds.join(","));
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
      const tireId = cb.dataset.id || '';
      if (!VALIDATION_PATTERNS.tireId.test(tireId)) return;

      if (cb.checked) {
        if (state.compareList.length >= 4) {
          cb.checked = false;
          return;
        }
        if (!state.compareList.includes(tireId)) state.compareList.push(tireId);
      } else {
        state.compareList = state.compareList.filter(id => id !== tireId);
      }
      updateCompareBar();
      document.querySelectorAll(".compare-checkbox").forEach(box => {
        if (!box.checked) box.disabled = state.compareList.length >= 4;
      });
    });
  });
}
