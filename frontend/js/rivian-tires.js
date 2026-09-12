/* jshint esversion: 11 */

/**
 * Rivian Tire Guide — Main Entry Point
 *
 * This file imports and wires together all modules. The source is organized
 * into small, focused modules under ./modules/ and esbuild bundles them into
 * a single output file for the browser.
 */

import { state, ROWS_PER_PAGE } from './modules/state.js';
import { getDOMElement, debounce, rtgIcon } from './modules/helpers.js';
import { VALIDATION_PATTERNS, validateAndSanitizeCSVRow } from './modules/validation.js';
import { RTG_ANALYTICS } from './modules/analytics.js';
import { showTooltipModal } from './modules/tooltips.js';
import { initializeSmartSearch } from './modules/search.js';
import { openReviewModal, loadTireRatings } from './modules/ratings.js';
import { renderCards } from './modules/cards.js';
import { updateCompareBar, openComparison, clearCompare, setupCompareCheckboxes } from './modules/compare.js';
import {
  buildFilterIndexes, filterAndRender, setupSliderHandlers, resetFilters,
  populateDropdown, populateSizeDropdownGrouped,
  populateVehicleToggle, getSelectedVehicle, setActiveVehicle, cascadeVehicleToSizes,
  applyFiltersFromURL, applyCompareFromURL, applyTireDeepLink,
  applyShortcodePrefilters,
  setUpdateCompareBar, adaptPriceSlider
} from './modules/filters.js';
import { isServerSide, fetchTiresFromServer, fetchDropdownOptions, serverSideFilterAndRender } from './modules/server.js';
import { initWhatsNew } from './modules/whats-new.js';
import { initAdvisor } from './modules/advisor.js';
import { initFilterBar } from './modules/filter-bar.js';

// Wire up the compare bar function to break the circular dependency
setUpdateCompareBar(updateCompareBar);

// Expose globals that other scripts or WordPress might need
window.openComparison = openComparison;
window.clearCompare = clearCompare;
window.resetFilters = resetFilters;

// Set login status immediately if WordPress data is available
if (typeof tireRatingAjax !== 'undefined') {
  state.isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
}

// --- Event Delegation ---

function setupEventDelegation() {
  if (state.eventDelegationSetup) return;

  document.addEventListener('click', function(e) {
    // Star click -> open review modal
    const star = e.target.closest('.rating-stars.interactive .star');
    if (star) {
      const tireId = star.dataset.tireId;
      const rating = parseInt(star.dataset.rating);

      if (!VALIDATION_PATTERNS.tireId.test(tireId) ||
          !Number.isInteger(rating) ||
          rating < 1 || rating > 5) {
        console.error('Invalid rating data');
        return;
      }

      openReviewModal(tireId, rating);
      return;
    }

    // (The write-review pill and reviews drawer were removed in 1.55.2 /
    // 1.56.0 — the review count now links to the tire page's reviews
    // section, and review writing happens via the stars or review page.)
  });

  document.addEventListener('mouseenter', function(e) {
    // The pointer entering the window targets the document itself.
    if (!(e.target instanceof Element)) return;
    const star = e.target.closest('.rating-stars.interactive .star');
    if (!star) return;

    const rating = parseInt(star.dataset.rating);
    if (!Number.isInteger(rating) || rating < 1 || rating > 5) return;

    const container = star.closest('.rating-stars');
    const stars = container.querySelectorAll('.star');

    stars.forEach((s, index) => {
      if (index < rating) {
        s.classList.add('hover');
      } else {
        s.classList.remove('hover');
      }
    });
  }, true);

  document.addEventListener('mouseleave', function(e) {
    const container = e.target.closest('.rating-stars');
    if (!container) return;

    const stars = container.querySelectorAll('.star');
    stars.forEach(s => s.classList.remove('hover'));
  }, true);

  // Affiliate click tracking via event delegation. The official-review link
  // was demoted from a .tire-card-cta-review button to a .tire-card-review-link
  // text link (1.55.2) — both classes are tracked for compatibility.
  document.addEventListener('click', function(e) {
    const link = e.target.closest(
      '.tire-card-cta-primary, .tire-card-cta-review, .tire-card-review-link'
    );
    if (!link) return;

    const card = link.closest('.tire-card');
    if (!card) return;

    const tireId = card.dataset.tireId;
    if (!tireId || !VALIDATION_PATTERNS.tireId.test(tireId)) return;

    let linkType = 'purchase';
    if (link.classList.contains('tire-card-cta-review') || link.classList.contains('tire-card-review-link')) linkType = 'review';

    RTG_ANALYTICS.trackClick(tireId, linkType);
  });

  // Keyboard navigation for star ratings (arrow keys, Enter/Space).
  document.addEventListener('keydown', function(e) {
    const star = e.target.closest('.rating-stars.interactive .star');
    if (!star) return;

    const container = star.closest('.rating-stars');
    const stars = Array.from(container.querySelectorAll('.star'));
    const currentIndex = stars.indexOf(star);

    if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
      e.preventDefault();
      const next = stars[Math.min(currentIndex + 1, stars.length - 1)];
      next.setAttribute('tabindex', '0');
      star.setAttribute('tabindex', '-1');
      next.focus();
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
      e.preventDefault();
      const prev = stars[Math.max(currentIndex - 1, 0)];
      prev.setAttribute('tabindex', '0');
      star.setAttribute('tabindex', '-1');
      prev.focus();
    } else if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      star.click();
    }
  });

  state.eventDelegationSetup = true;
}

// --- UI Initialization ---

function initializeUI() {
  const ssMode = isServerSide();
  const filterFn = ssMode ? serverSideFilterAndRender : filterAndRender;
  const debouncedFilterFn = ssMode ? debounce(serverSideFilterAndRender, 500) : debounce(filterAndRender, 500);

  if (!ssMode) {
    // Use admin-managed sizes if available, otherwise derive from tire data.
    const adminSizes = (typeof rtgData !== 'undefined' && rtgData.settings && Array.isArray(rtgData.settings.adminSizes) && rtgData.settings.adminSizes.length > 0)
      ? rtgData.settings.adminSizes
      : null;

    const dataSizes = [...new Set(state.allRows.map(r => String(r[1] || '').trim()))].filter(Boolean);
    // Merge admin sizes with any sizes found in data to ensure all tires are filterable.
    state.VALID_SIZES = adminSizes
      ? [...new Set([...adminSizes, ...dataSizes])]
      : dataSizes;
    state.VALID_BRANDS = [...new Set(state.allRows.map(r => String(r[3] || '').trim()))].filter(Boolean);
    state.VALID_CATEGORIES = [...new Set(state.allRows.map(r => String(r[5] || '').trim()))].filter(Boolean);

    populateSizeDropdownGrouped("filterSize", state.VALID_SIZES);
    populateDropdown("filterBrand", state.allRows.map(r => r[3]));
    populateDropdown("filterCategory", state.allRows.map(r => r[5]));
  }

  // Initialize vehicle state from localized data (works for both modes before server-side overrides).
  if (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.vehicleSizeMap) {
    state.vehicleSizeMap = rtgData.settings.vehicleSizeMap;
    state.thirdPartySizes = rtgData.settings.thirdPartySizes || {};
    state.VALID_VEHICLES = Object.keys(state.vehicleSizeMap).sort();
    populateVehicleToggle(state.vehicleSizeMap);
  }

  // Wire vehicle toggle click handler.
  const vehicleContainer = document.getElementById('vehicleToggle');
  if (vehicleContainer) {
    vehicleContainer.addEventListener('click', (e) => {
      const btn = e.target.closest('.rtg-vehicle-btn');
      if (!btn) return;
      const vehicle = btn.dataset.vehicle || '';
      setActiveVehicle(vehicle);
      cascadeVehicleToSizes(vehicle, state.VALID_SIZES);
      state.lastFilterState = null;
      filterFn();
    });
  }

  const inputsToWatch = [
    { id: "searchInput", listener: debouncedFilterFn },
    { id: "filterSize", listener: filterFn },
    { id: "filterBrand", listener: filterFn },
    { id: "filterCategory", listener: filterFn },
    { id: "filter3pms", listener: filterFn },
    { id: "filterOEM", listener: filterFn },
  ];

  inputsToWatch.forEach(({ id, listener }) => {
    const el = getDOMElement(id);
    if (el) {
      el.addEventListener("input", listener);
    }
  });

  applyShortcodePrefilters();
  applyFiltersFromURL();
  applyCompareFromURL();
  setupSliderHandlers();
  setupEventDelegation();
  initializeSmartSearch();

  if (ssMode) {
    // Slider handlers are bound once, mode-aware, in setupSliderHandlers().
    fetchDropdownOptions().then(() => {
      fetchTiresFromServer(state.currentPage);
    });
  } else {
    buildFilterIndexes();
    if (!applyTireDeepLink()) {
      filterAndRender();
    }

    const countDisplay = getDOMElement("tireCount");
    if (countDisplay) {
      countDisplay.textContent = `${state.filteredRows.length} tire${state.filteredRows.length !== 1 ? "s" : ""}`;
    }
  }
}

// --- Popstate handler for browser back/forward ---
window.addEventListener('popstate', function() {
  if (isServerSide()) return;
  state.lastFilterState = null;
  state.restoringFromURL = true; // cleared by finishFilterAndRender
  applyFiltersFromURL();
  filterAndRender();
});

// Initialize analytics tracking.
RTG_ANALYTICS.init();

// Show skeleton loading placeholders while data loads.
(function showSkeletonLoading() {
  const tireCards = document.getElementById('tireCards');
  if (!tireCards || tireCards.children.length > 0) return;
  const count = (typeof rtgData !== 'undefined' && rtgData.settings) ? (rtgData.settings.rowsPerPage || 12) : 12;
  const grid = document.createElement('div');
  grid.className = 'rtg-skeleton-grid';
  grid.id = 'rtg-skeleton-loader';
  for (let i = 0; i < Math.min(count, 12); i++) {
    grid.innerHTML += '<div class="rtg-skeleton-card">'
      + '<div class="rtg-skeleton-shimmer rtg-skeleton-image"></div>'
      + '<div class="rtg-skeleton-shimmer rtg-skeleton-title"></div>'
      + '<div class="rtg-skeleton-shimmer rtg-skeleton-subtitle"></div>'
      + '<div class="rtg-skeleton-row"><div class="rtg-skeleton-shimmer rtg-skeleton-badge"></div><div class="rtg-skeleton-shimmer rtg-skeleton-badge"></div></div>'
      + '<div class="rtg-skeleton-shimmer rtg-skeleton-text"></div>'
      + '<div class="rtg-skeleton-shimmer rtg-skeleton-text-short"></div>'
      + '<div class="rtg-skeleton-shimmer rtg-skeleton-stars"></div>'
      + '</div>';
  }
  tireCards.appendChild(grid);
})();

// --- Load tire data from WordPress localized script ---
if (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.serverSide) {
  state.serverSideMode = true;

  if (typeof tireRatingAjax !== 'undefined') {
    state.isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
  }

  state.allRows = [];
  state.filteredRows = [];
  initializeUI();
} else if (typeof rtgData !== 'undefined' && rtgData.tires && Array.isArray(rtgData.tires)) {
  state.allRows = rtgData.tires
    .map(validateAndSanitizeCSVRow)
    .filter(row => row && row.length && row[0]);
  state.filteredRows = state.allRows;

  // Raise the price-slider ceiling to the most expensive tire so nothing is
  // silently excluded by the template's hardcoded max.
  const maxPrice = state.allRows.reduce((max, row) => {
    const p = parseFloat(row[6]);
    return Number.isFinite(p) && p > max ? p : max;
  }, 0);
  adaptPriceSlider(maxPrice);

  if (typeof tireRatingAjax !== 'undefined') {
    state.isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
  }
  initFilterBar({ onClearAll: resetFilters });
  initializeUI();
} else {
  console.error('Tire guide data not available. Ensure the [rivian_tire_guide] shortcode is used.');
}

// --- DOMContentLoaded: sort, tooltips, wheel drawer ---
document.addEventListener("DOMContentLoaded", () => {
  initWhatsNew();
  initAdvisor();

  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.info-tooltip-trigger');
    if (trigger) {
      e.preventDefault();
      e.stopPropagation();
      const tooltipKey = trigger.dataset.tooltipKey;
      showTooltipModal(tooltipKey, trigger);
    }
  });

  const sortDropdown = getDOMElement("sortBy");
  if (sortDropdown) {
    const ssMode = isServerSide();
    sortDropdown.addEventListener("change", ssMode ? serverSideFilterAndRender : filterAndRender);
  }

  const trigger = getDOMElement("wheelDrawerTrigger");
  const drawer = getDOMElement("wheelDrawer");
  const wheelCallout = getDOMElement("wheelDrawerContainer");
  if (trigger && drawer && wheelCallout) {
    trigger.addEventListener("click", () => {
      const isOpen = wheelCallout.classList.toggle("open");
      drawer.style.display = isOpen ? "block" : "none";
      trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Vehicle tab switching inside wheel drawer.
    const wheelTabs = wheelCallout.querySelectorAll(".wheel-tab");
    const wheelPanels = wheelCallout.querySelectorAll(".wheel-tab-panel");
    wheelTabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        wheelTabs.forEach((t) => { t.classList.remove("active"); t.setAttribute("aria-selected", "false"); });
        wheelPanels.forEach((p) => { p.classList.remove("active"); p.hidden = true; });
        tab.classList.add("active");
        tab.setAttribute("aria-selected", "true");
        const panel = document.getElementById(tab.getAttribute("aria-controls"));
        if (panel) { panel.classList.add("active"); panel.hidden = false; }
      });
    });
  }
});
