/* jshint esversion: 11 */

/**
 * Server-side pagination mode — fetch tires and filter options from the server.
 */

import { state, ROWS_PER_PAGE } from './state.js';
import { rtgColor, getDOMElement, debounce, styleButton } from './helpers.js';
import { validateAndSanitizeCSVRow } from './validation.js';
import { RTG_ANALYTICS } from './analytics.js';
import { renderCards } from './cards.js';
import { loadTireRatings } from './ratings.js';
import { updateURLFromFilters, renderSmartNoResults, renderActiveFilterChips, applyFiltersFromURL, populateDropdown } from './filters.js';

export function isServerSide() {
  return state.serverSideMode && typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.ajaxurl;
}

export function fetchTiresFromServer(page) {
  if (state.serverSideFetchController) state.serverSideFetchController.abort();
  state.serverSideFetchController = new AbortController();

  const searchInput = document.querySelector('#searchInput');
  const body = new FormData();
  body.append('action', 'rtg_get_tires');
  body.append('nonce', rtgData.settings.tireNonce);
  body.append('page', page || state.currentPage);
  body.append('search', searchInput ? searchInput.value : '');
  body.append('size', getDOMElement("filterSize")?.value || '');
  body.append('brand', getDOMElement("filterBrand")?.value || '');
  body.append('category', getDOMElement("filterCategory")?.value || '');
  body.append('three_pms', getDOMElement("filter3pms")?.checked ? '1' : '');
  body.append('ev_rated', getDOMElement("filterEVRated")?.checked ? '1' : '');
  body.append('studded', getDOMElement("filterStudded")?.checked ? '1' : '');
  body.append('price_max', getDOMElement("priceMax")?.value || '600');
  body.append('weight_max', getDOMElement("weightMax")?.value || '70');

  const warrantyMax = getDOMElement("warrantyMax");
  const warVal = warrantyMax ? parseInt(warrantyMax.value) : 80000;
  body.append('warranty_min', warVal < 80000 ? (80000 - warVal).toString() : '0');

  const sortBy = getDOMElement("sortBy");
  const sortVal = sortBy?.value || 'efficiency_score';
  const sortMap = { efficiencyGrade: 'efficiency_score' };
  body.append('sort', sortMap[sortVal] || sortVal);

  const tireCountEl = getDOMElement("tireCount");
  if (tireCountEl) tireCountEl.textContent = 'Loading...';

  return fetch(rtgData.settings.ajaxurl, {
    method: 'POST',
    body: body,
    signal: state.serverSideFetchController.signal,
  })
  .then(res => res.json())
  .then(json => {
    if (!json.success) {
      console.error('Server tire fetch failed:', json);
      return;
    }
    const rows = (json.data.rows || [])
      .map(validateAndSanitizeCSVRow)
      .filter(row => row && row.length && row[0]);

    state.filteredRows = rows;
    state.serverSideTotal = json.data.total || 0;
    state.currentPage = json.data.page || 1;

    if (tireCountEl) {
      tireCountEl.textContent = `Showing ${state.serverSideTotal} tire${state.serverSideTotal === 1 ? '' : 's'}`;
    }

    renderCards(state.filteredRows);
    renderServerPagination(state.serverSideTotal, json.data.per_page || ROWS_PER_PAGE, state.currentPage);
    updateURLFromFilters();

    const noResults = getDOMElement("noResults");
    const tireCards = getDOMElement("tireCards");
    if (state.filteredRows.length === 0) {
      renderSmartNoResults();
      if (noResults) noResults.style.display = "block";
      if (tireCards) tireCards.style.display = "none";
    } else {
      if (noResults) noResults.style.display = "none";
      if (tireCards) tireCards.style.display = "grid";
    }

    // Track search/filter usage in server-side mode.
    const ssSearch = searchInput ? searchInput.value : '';
    const ssFilters = {};
    if (ssSearch) ssFilters.search = ssSearch;
    const ssSize = getDOMElement("filterSize")?.value || '';
    const ssBrand = getDOMElement("filterBrand")?.value || '';
    const ssCat = getDOMElement("filterCategory")?.value || '';
    if (ssSize) ssFilters.size = ssSize;
    if (ssBrand) ssFilters.brand = ssBrand;
    if (ssCat) ssFilters.category = ssCat;
    if (getDOMElement("filter3pms")?.checked) ssFilters.three_pms = true;
    if (getDOMElement("filterEVRated")?.checked) ssFilters.ev_rated = true;
    if (getDOMElement("filterStudded")?.checked) ssFilters.studded = true;

    if (ssSearch || Object.keys(ssFilters).length > 0) {
      RTG_ANALYTICS.trackSearch(
        ssSearch,
        ssFilters,
        sortVal,
        state.serverSideTotal
      );
    }

    // Load ratings for visible tires.
    const tireIds = state.filteredRows.map(row => row[0]).filter(Boolean);
    loadTireRatings(tireIds);
  })
  .catch(err => {
    if (err.name !== 'AbortError') console.error('Fetch error:', err);
  });
}

function renderServerPagination(total, perPage, page) {
  const container = getDOMElement("paginationControls");
  if (!container) return;
  container.innerHTML = "";
  const totalPages = Math.ceil(total / perPage);
  if (totalPages <= 1) return;

  const prev = document.createElement("button");
  prev.textContent = "Previous";
  prev.disabled = page <= 1;
  styleButton(prev);
  prev.onclick = () => { state.currentPage = page - 1; fetchTiresFromServer(state.currentPage); scrollToTop(); };
  container.appendChild(prev);

  const pageInfo = document.createElement("span");
  pageInfo.textContent = `Page ${page} of ${totalPages}`;
  pageInfo.style.cssText = `color: ${rtgColor('text-primary')}; font-weight: 500; display: flex; align-items: center;`;
  container.appendChild(pageInfo);

  const next = document.createElement("button");
  next.textContent = "Next";
  next.disabled = page >= totalPages;
  styleButton(next);
  next.onclick = () => { state.currentPage = page + 1; fetchTiresFromServer(state.currentPage); scrollToTop(); };
  container.appendChild(next);
}

function scrollToTop() {
  const filterTop = getDOMElement("filterTop");
  if (filterTop) filterTop.scrollIntoView({ behavior: "smooth" });
}

export function fetchDropdownOptions() {
  const body = new FormData();
  body.append('action', 'rtg_get_filter_options');
  body.append('nonce', rtgData.settings.tireNonce);

  return fetch(rtgData.settings.ajaxurl, { method: 'POST', body })
    .then(res => res.json())
    .then(json => {
      if (!json.success) return;
      const d = json.data;

      state.VALID_SIZES = d.sizes || [];
      state.VALID_BRANDS = d.brands || [];
      state.VALID_CATEGORIES = d.categories || [];

      // Populate size dropdown grouped by rim diameter.
      const sizeSelect = getDOMElement("filterSize");
      if (sizeSelect) {
        sizeSelect.innerHTML = '';
        const defaultOpt = document.createElement("option");
        defaultOpt.value = '';
        defaultOpt.textContent = 'All Sizes';
        sizeSelect.appendChild(defaultOpt);

        const groups = {};
        state.VALID_SIZES.forEach(size => {
          const match = size.match(/R(\d{2})/i);
          const rim = match ? match[1] : "Other";
          if (!groups[rim]) groups[rim] = [];
          groups[rim].push(size);
        });
        Object.keys(groups).sort((a, b) => a === "Other" ? 1 : b === "Other" ? -1 : parseInt(a) - parseInt(b)).forEach(rim => {
          const optgroup = document.createElement("optgroup");
          optgroup.label = `${rim}" Wheels`;
          groups[rim].sort().forEach(size => {
            const opt = document.createElement("option");
            opt.value = size;
            opt.textContent = size;
            optgroup.appendChild(opt);
          });
          sizeSelect.appendChild(optgroup);
        });
      }

      populateDropdown("filterBrand", state.VALID_BRANDS);
      populateDropdown("filterCategory", state.VALID_CATEGORIES);

      // Re-apply URL params to newly populated dropdowns.
      applyFiltersFromURL();
    })
    .catch(err => console.error('Failed to fetch filter options:', err));
}

export function serverSideFilterAndRender() {
  state.currentPage = 1;
  state.lastFilterState = null;
  fetchTiresFromServer(1);
  renderActiveFilterChips();
}
