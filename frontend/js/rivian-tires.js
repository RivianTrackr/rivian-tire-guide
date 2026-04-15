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
import { showTooltipModal, createFilterTooltip } from './modules/tooltips.js';
import { initializeSmartSearch } from './modules/search.js';
import { openReviewModal, openReviewsDrawer, loadTireRatings } from './modules/ratings.js';
import { renderCards } from './modules/cards.js';
import { loadFavorites } from './modules/favorites.js';
import { updateCompareBar, openComparison, clearCompare, setupCompareCheckboxes } from './modules/compare.js';
import {
  buildFilterIndexes, filterAndRender, setupSliderHandlers, resetFilters,
  populateDropdown, populateSizeDropdownGrouped,
  populateVehicleToggle, getSelectedVehicle, setActiveVehicle, cascadeVehicleToSizes,
  applyFiltersFromURL, applyCompareFromURL, applyTireDeepLink,
  applyShortcodePrefilters, renderActiveFilterChips,
  setUpdateCompareBar
} from './modules/filters.js';
import { isServerSide, fetchTiresFromServer, fetchDropdownOptions, serverSideFilterAndRender } from './modules/server.js';
import { initAiRecommend } from './modules/ai-recommend.js';

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

    // Write/Edit review button
    const writeBtn = e.target.closest('.write-review-btn');
    if (writeBtn) {
      const tireId = writeBtn.dataset.tireId;
      if (VALIDATION_PATTERNS.tireId.test(tireId)) {
        const existingRating = state.userRatings[tireId] || 0;
        openReviewModal(tireId, existingRating);
      }
      return;
    }

    // View reviews button
    const viewBtn = e.target.closest('.view-reviews-btn');
    if (viewBtn) {
      const tireId = viewBtn.dataset.tireId;
      if (VALIDATION_PATTERNS.tireId.test(tireId)) {
        openReviewsDrawer(tireId);
      }
      return;
    }
  });

  document.addEventListener('mouseenter', function(e) {
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

  // Affiliate click tracking via event delegation.
  document.addEventListener('click', function(e) {
    const link = e.target.closest(
      '.tire-card-cta-primary, .tire-card-cta-review'
    );
    if (!link) return;

    const card = link.closest('.tire-card');
    if (!card) return;

    const tireId = card.dataset.tireId;
    if (!tireId || !VALIDATION_PATTERNS.tireId.test(tireId)) return;

    let linkType = 'purchase';
    if (link.classList.contains('tire-card-cta-review')) linkType = 'review';

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
    { id: "filterFavorites", listener: filterFn },
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
  initAiRecommend();

  if (ssMode) {
    const sliderIds = ["priceMax", "warrantyMin"];
    sliderIds.forEach(id => {
      const input = getDOMElement(id);
      if (input) input.addEventListener("input", debounce(serverSideFilterAndRender, 500));
    });

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
      countDisplay.textContent = `Showing ${state.filteredRows.length} tire${state.filteredRows.length !== 1 ? "s" : ""}`;
    }
  }

  // Load favorites after UI is ready (non-blocking)
  loadFavorites();
}

// --- Popstate handler for browser back/forward ---
window.addEventListener('popstate', function() {
  if (isServerSide()) return;
  state.lastFilterState = null;
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

  if (typeof tireRatingAjax !== 'undefined') {
    state.isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
  }
  initializeUI();
} else {
  console.error('Tire guide data not available. Ensure the [rivian_tire_guide] shortcode is used.');
}

// --- Compare modal close on backdrop click ---
document.addEventListener("click", (e) => {
  const modal = getDOMElement("compareModal");
  const content = modal?.querySelector("div");
  if (modal?.style.display === "flex" && modal.contains(e.target) && !content?.contains(e.target)) {
    modal.style.display = "none";
    document.body.style.overflow = "";
  }
});

// --- DOMContentLoaded: tooltip setup, sort, mobile filter toggle ---
document.addEventListener("DOMContentLoaded", () => {
  function updateFilterTooltipsDirectly() {
    const switchLabels = document.querySelectorAll('.switch-label');

    switchLabels.forEach(label => {
      const input = label.querySelector('input[type="checkbox"]');
      const switchText = label.querySelector('.switch-text');

      if (input && switchText) {
        const inputId = input.id;
        let tooltipKey = null;
        let labelText = '';

        switch(inputId) {
          case 'filter3pms':
            tooltipKey = '3PMS Filter';
            labelText = '3PMS';
            break;
          case 'filterOEM':
            tooltipKey = 'OEM Filter';
            labelText = 'OEM';
            break;
        }

        if (tooltipKey) {
          const newContent = createFilterTooltip(labelText, tooltipKey);
          switchText.innerHTML = '';
          switchText.appendChild(newContent);
        }
      }
    });
  }

  function updateFilterTooltips() {
    setTimeout(() => {
      const tooltipConfig = [
        { selector: 'filter3pms', label: '3PMS', key: '3PMS Filter' },
        { selector: 'filterOEM', label: 'OEM', key: 'OEM Filter' },
      ];

      tooltipConfig.forEach(({ selector, label, key }) => {
        const el = document.querySelector(`.switch-label:has(input#${selector}) .switch-text`);
        if (el) {
          const newContent = createFilterTooltip(label, key);
          el.innerHTML = '';
          el.appendChild(newContent);
        } else {
          const input = document.getElementById(selector);
          if (input) {
            const switchText = input.parentElement.querySelector('.switch-text');
            if (switchText) {
              const newContent = createFilterTooltip(label, key);
              switchText.innerHTML = '';
              switchText.appendChild(newContent);
            }
          }
        }
      });
    }, 100);
  }

  updateFilterTooltipsDirectly();
  updateFilterTooltips();

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

  const toggleBtn = getDOMElement("toggleFilters");
  const filterContent = getDOMElement("mobileFilterContent");
  if (toggleBtn && filterContent) {
    toggleBtn.setAttribute('aria-expanded', 'false');
    toggleBtn.setAttribute('aria-controls', 'mobileFilterContent');
    toggleBtn.addEventListener("click", () => {
      const isOpen = filterContent.classList.toggle("open");
      toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      // Re-render badge with updated open/close text
      const badge = toggleBtn.querySelector('.mobile-filter-badge');
      const badgeHTML = badge ? ` <span class="mobile-filter-badge">${badge.textContent}</span>` : '';
      toggleBtn.innerHTML = `<i class="fa-solid fa-sliders" aria-hidden="true"></i>&nbsp; ${isOpen ? "Hide" : "Show"} Filters${badgeHTML}`;
    });
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
