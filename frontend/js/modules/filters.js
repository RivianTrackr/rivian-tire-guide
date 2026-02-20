/* jshint esversion: 11 */

/**
 * Filtering, sorting, pagination, URL state, and active filter chips.
 */

import { state, filterIndexes, ROWS_PER_PAGE } from './state.js';
import { rtgColor, rtgIcon, safeString, getDOMElement, debounce, updateSliderBackground, styleButton, escapeHTML } from './helpers.js';
import { VALIDATION_PATTERNS, NUMERIC_BOUNDS, ALLOWED_SORT_OPTIONS, sanitizeInput, validateNumeric } from './validation.js';
import { RTG_ANALYTICS } from './analytics.js';
import { renderCards, preloadNextPageImages } from './cards.js';
import { loadTireRatings } from './ratings.js';
import { isPreciseMatch, hideSearchSuggestions } from './search.js';
import { isServerSide, serverSideFilterAndRender } from './server.js';

export function buildFilterIndexes() {
  filterIndexes.sizeIndex.clear();
  filterIndexes.brandIndex.clear();
  filterIndexes.categoryIndex.clear();
  filterIndexes.priceIndex.length = 0;
  filterIndexes.warrantyIndex.length = 0;
  filterIndexes.weightIndex.length = 0;

  state.allRows.forEach((row, index) => {
    const [tireId, size, diameter, brand, model, category, price, warranty, weight] = row;

    const sizeKey = safeString(size).toLowerCase();
    if (sizeKey) {
      if (!filterIndexes.sizeIndex.has(sizeKey)) filterIndexes.sizeIndex.set(sizeKey, []);
      filterIndexes.sizeIndex.get(sizeKey).push(index);
    }

    const brandKey = safeString(brand).toLowerCase();
    if (brandKey) {
      if (!filterIndexes.brandIndex.has(brandKey)) filterIndexes.brandIndex.set(brandKey, []);
      filterIndexes.brandIndex.get(brandKey).push(index);
    }

    const categoryKey = safeString(category).toLowerCase();
    if (categoryKey) {
      if (!filterIndexes.categoryIndex.has(categoryKey)) filterIndexes.categoryIndex.set(categoryKey, []);
      filterIndexes.categoryIndex.get(categoryKey).push(index);
    }

    const priceVal = validateNumeric(price, NUMERIC_BOUNDS.price);
    const warrantyVal = validateNumeric(warranty, NUMERIC_BOUNDS.warranty);
    const weightVal = validateNumeric(weight, NUMERIC_BOUNDS.weight);

    filterIndexes.priceIndex.push({ index, value: priceVal });
    filterIndexes.warrantyIndex.push({ index, value: warrantyVal });
    filterIndexes.weightIndex.push({ index, value: weightVal });
  });

  filterIndexes.priceIndex.sort((a, b) => a.value - b.value);
  filterIndexes.warrantyIndex.sort((a, b) => a.value - b.value);
  filterIndexes.weightIndex.sort((a, b) => a.value - b.value);
}

function binarySearchMax(arr, maxValue) {
  let left = 0;
  let right = arr.length - 1;

  while (left <= right) {
    const mid = Math.floor((left + right) / 2);
    if (arr[mid].value <= maxValue) {
      left = mid + 1;
    } else {
      right = mid - 1;
    }
  }

  return new Set(arr.slice(0, right + 1).map(item => item.index));
}

function getFilteredIndexes(filters) {
  let candidateIndexes = new Set(state.allRows.map((_, i) => i));

  if (filters.Size) {
    const sizeSet = new Set(filterIndexes.sizeIndex.get(filters.Size.toLowerCase()) || []);
    candidateIndexes = new Set([...candidateIndexes].filter(x => sizeSet.has(x)));
  }

  if (filters.Brand) {
    const brandSet = new Set(filterIndexes.brandIndex.get(filters.Brand.toLowerCase()) || []);
    candidateIndexes = new Set([...candidateIndexes].filter(x => brandSet.has(x)));
  }

  if (filters.Category) {
    const categorySet = new Set(filterIndexes.categoryIndex.get(filters.Category.toLowerCase()) || []);
    candidateIndexes = new Set([...candidateIndexes].filter(x => categorySet.has(x)));
  }

  if (filters.PriceMax < 600) {
    const priceSet = binarySearchMax(filterIndexes.priceIndex, filters.PriceMax);
    candidateIndexes = new Set([...candidateIndexes].filter(x => priceSet.has(x)));
  }

  if (filters.WarrantyMax < 80000) {
    const warrantySet = binarySearchMax(filterIndexes.warrantyIndex, filters.WarrantyMax);
    candidateIndexes = new Set([...candidateIndexes].filter(x => warrantySet.has(x)));
  }

  if (filters.WeightMax < 70) {
    const weightSet = binarySearchMax(filterIndexes.weightIndex, filters.WeightMax);
    candidateIndexes = new Set([...candidateIndexes].filter(x => weightSet.has(x)));
  }

  return [...candidateIndexes].map(i => state.allRows[i]).filter(row => {
    if (filters.search) {
      const brandText = safeString(row[3]);
      const modelText = safeString(row[4]);
      const searchText = `${brandText} ${modelText}`;

      const matchesBrand = isPreciseMatch(brandText, filters.search);
      const matchesModel = isPreciseMatch(modelText, filters.search);
      const matchesCombined = isPreciseMatch(searchText, filters.search);

      if (!matchesBrand && !matchesModel && !matchesCombined) {
        return false;
      }
    }

    if (filters["3PMS"] && !safeString(row[9]).toLowerCase().includes("yes")) return false;
    if (filters["EVRated"] && !safeString(row[17]).toLowerCase().includes("ev rated")) return false;
    if (filters["Studded"] && !safeString(row[17]).toLowerCase().includes("studded available")) return false;
    if (filters["Reviewed"] && !safeString(row[23])) return false;
    if (filters["Favorites"] && !state.userFavorites.has(row[0])) return false;

    return true;
  });
}

function throttledRender() {
  if (state.isRendering) {
    state.pendingRender = true;
    return;
  }

  if (state.renderAnimationFrame) {
    cancelAnimationFrame(state.renderAnimationFrame);
  }

  state.renderAnimationFrame = requestAnimationFrame(() => {
    state.isRendering = true;
    render();

    setTimeout(() => {
      state.isRendering = false;
      state.renderAnimationFrame = null;
      if (state.pendingRender) {
        state.pendingRender = false;
        throttledRender();
      }
    }, 16);
  });
}

function paginate(data) {
  const start = (state.currentPage - 1) * ROWS_PER_PAGE;
  return data.slice(start, start + ROWS_PER_PAGE);
}

function render() {
  const visible = paginate(state.filteredRows);

  const tireIds = visible.map(row => row[0]).filter(Boolean);

  loadTireRatings(tireIds).then(() => {
    renderCards(visible);
    renderPaginationControls(state.filteredRows);

    const noResults = getDOMElement("noResults");
    const tireCards = getDOMElement("tireCards");
    if (state.filteredRows.length === 0) {
      renderSmartNoResults();
      noResults.style.display = "block";
      tireCards.style.display = "none";
    } else {
      noResults.style.display = "none";
      tireCards.style.display = "grid";
    }

    if ('requestIdleCallback' in window) {
      requestIdleCallback(() => preloadNextPageImages());
    } else {
      setTimeout(preloadNextPageImages, 100);
    }
  });
}

function renderPaginationControls(totalRows) {
  const container = getDOMElement("paginationControls");
  container.innerHTML = "";
  const totalPages = Math.ceil(totalRows.length / ROWS_PER_PAGE);
  if (totalPages <= 1) return;

  const prev = document.createElement("button");
  prev.textContent = "Previous";
  prev.disabled = state.currentPage === 1;
  styleButton(prev);
  prev.onclick = () => {
    state.currentPage--;
    render();
    updateURLFromFilters();
    const filterTop = getDOMElement("filterTop");
    if (filterTop) filterTop.scrollIntoView({ behavior: "smooth" });
  };
  container.appendChild(prev);

  const pageInfo = document.createElement("span");
  pageInfo.textContent = `Page ${state.currentPage} of ${totalPages}`;
  pageInfo.style.cssText = `color: ${rtgColor('text-primary')}; font-weight: 500; display: flex; align-items: center;`;
  container.appendChild(pageInfo);

  const next = document.createElement("button");
  next.textContent = "Next";
  next.disabled = state.currentPage === totalPages;
  styleButton(next);
  next.onclick = () => {
    state.currentPage++;
    render();
    updateURLFromFilters();
    const filterTop = getDOMElement("filterTop");
    if (filterTop) filterTop.scrollIntoView({ behavior: "smooth" });
  };
  container.appendChild(next);
}

export function filterAndRender() {
  const searchInput = document.querySelector('#searchInput');
  const priceMax = getDOMElement("priceMax");
  const warrantyMax = getDOMElement("warrantyMax");
  const weightMax = getDOMElement("weightMax");
  const filter3pms = getDOMElement("filter3pms");
  const filterEVRated = getDOMElement("filterEVRated");
  const filterStudded = getDOMElement("filterStudded");
  const filterReviewed = getDOMElement("filterReviewed");
  const filterFavorites = getDOMElement("filterFavorites");
  const filterSize = getDOMElement("filterSize");
  const filterBrand = getDOMElement("filterBrand");
  const filterCategory = getDOMElement("filterCategory");
  const sortBy = getDOMElement("sortBy");

  const searchVal = searchInput ? sanitizeInput(searchInput.value, VALIDATION_PATTERNS.search) : "";
  const priceVal = priceMax ? validateNumeric(priceMax.value, NUMERIC_BOUNDS.price, 600) : 600;
  const warrantyVal = warrantyMax ? validateNumeric(warrantyMax.value, NUMERIC_BOUNDS.warranty, 80000) : 80000;
  const weightVal = weightMax ? validateNumeric(weightMax.value, NUMERIC_BOUNDS.weight, 70) : 70;

  const f = {
    search: searchVal.toLowerCase(),
    PriceMax: priceVal,
    WarrantyMax: warrantyVal,
    WeightMax: weightVal,
    "3PMS": filter3pms?.checked || false,
    "EVRated": filterEVRated?.checked || false,
    "Studded": filterStudded?.checked || false,
    "Reviewed": filterReviewed?.checked || false,
    "Favorites": filterFavorites?.checked || false,
    Size: filterSize?.value && state.VALID_SIZES.includes(filterSize.value) ? filterSize.value : "",
    Brand: filterBrand?.value && state.VALID_BRANDS.includes(filterBrand.value) ? filterBrand.value : "",
    Category: filterCategory?.value && state.VALID_CATEGORIES.includes(filterCategory.value) ? filterCategory.value : ""
  };

  const sortOption = sortBy?.value && ALLOWED_SORT_OPTIONS.includes(sortBy.value) ? sortBy.value : "";

  const currentFilterState = JSON.stringify({
    ...f,
    sortOption,
    currentPage: state.currentPage
  });

  if (state.lastFilterState === currentFilterState) {
    return;
  }
  state.lastFilterState = currentFilterState;

  state.filteredRows = getFilteredIndexes(f);

  // Track search/filter usage when non-default filters are active.
  const activeFilters = {};
  if (f.search) activeFilters.search = f.search;
  if (f.Size) activeFilters.size = f.Size;
  if (f.Brand) activeFilters.brand = f.Brand;
  if (f.Category) activeFilters.category = f.Category;
  if (f["3PMS"]) activeFilters.three_pms = true;
  if (f["EVRated"]) activeFilters.ev_rated = true;
  if (f["Studded"]) activeFilters.studded = true;
  if (f.PriceMax < 600) activeFilters.price_max = f.PriceMax;
  if (f.WarrantyMax < 80000) activeFilters.warranty_max = f.WarrantyMax;
  if (f.WeightMax < 70) activeFilters.weight_max = f.WeightMax;

  if (f.search || Object.keys(activeFilters).length > 0) {
    RTG_ANALYTICS.trackSearch(
      f.search || '',
      activeFilters,
      sortOption,
      state.filteredRows.length
    );
  }

  if (sortOption === "rating-desc" || sortOption === "most-reviewed") {
    const allFilteredTireIds = state.filteredRows.map(row => row[0]).filter(Boolean);
    loadTireRatings(allFilteredTireIds).then(() => {
      applySorting(sortOption);
      finishFilterAndRender();
    });
  } else {
    applySorting(sortOption);
    finishFilterAndRender();
  }
}

function applySorting(sortOption) {
  if (!sortOption || sortOption === "efficiencyGrade") {
    state.filteredRows.sort((a, b) => {
      const aScore = validateNumeric(a[20], { min: 0, max: 100 }, 0);
      const bScore = validateNumeric(b[20], { min: 0, max: 100 }, 0);
      return bScore - aScore;
    });
    return;
  }

  switch (sortOption) {
    case "reviewed":
      state.filteredRows.sort((a, b) => {
        const aHasPick = safeString(a[17]).toLowerCase().includes("reviewed");
        const bHasPick = safeString(b[17]).toLowerCase().includes("reviewed");
        return bHasPick - aHasPick;
      });
      break;

    case "rating-desc":
      state.filteredRows.sort((a, b) => {
        const aRating = validateNumeric(state.tireRatings[a[0]]?.average, { min: 0, max: 5 }, 0);
        const bRating = validateNumeric(state.tireRatings[b[0]]?.average, { min: 0, max: 5 }, 0);
        const ratingDiff = bRating - aRating;

        if (Math.abs(ratingDiff) < 0.01) {
          const aCount = validateNumeric(state.tireRatings[a[0]]?.count, { min: 0, max: 10000 }, 0);
          const bCount = validateNumeric(state.tireRatings[b[0]]?.count, { min: 0, max: 10000 }, 0);
          const countDiff = bCount - aCount;
          return Math.abs(countDiff) < 0.01 ? safeString(a[0]).localeCompare(safeString(b[0])) : countDiff;
        }
        return ratingDiff;
      });
      break;

    case "most-reviewed":
      state.filteredRows.sort((a, b) => {
        const aCount = validateNumeric(state.tireRatings[a[0]]?.count, { min: 0, max: 10000 }, 0);
        const bCount = validateNumeric(state.tireRatings[b[0]]?.count, { min: 0, max: 10000 }, 0);
        const countDiff = bCount - aCount;

        if (Math.abs(countDiff) < 0.01) {
          const aRating = validateNumeric(state.tireRatings[a[0]]?.average, { min: 0, max: 5 }, 0);
          const bRating = validateNumeric(state.tireRatings[b[0]]?.average, { min: 0, max: 5 }, 0);
          const ratingDiff = bRating - aRating;
          return Math.abs(ratingDiff) < 0.01 ? safeString(a[0]).localeCompare(safeString(b[0])) : ratingDiff;
        }
        return countDiff;
      });
      break;

    case "price-asc":
      state.filteredRows.sort((a, b) => validateNumeric(a[6], NUMERIC_BOUNDS.price, 0) - validateNumeric(b[6], NUMERIC_BOUNDS.price, 0));
      break;
    case "price-desc":
      state.filteredRows.sort((a, b) => validateNumeric(b[6], NUMERIC_BOUNDS.price, 0) - validateNumeric(a[6], NUMERIC_BOUNDS.price, 0));
      break;
    case "warranty-desc":
      state.filteredRows.sort((a, b) => validateNumeric(b[7], NUMERIC_BOUNDS.warranty, 0) - validateNumeric(a[7], NUMERIC_BOUNDS.warranty, 0));
      break;
    case "weight-asc":
      state.filteredRows.sort((a, b) => validateNumeric(a[8], NUMERIC_BOUNDS.weight, 0) - validateNumeric(b[8], NUMERIC_BOUNDS.weight, 0));
      break;
    case "newest":
      state.filteredRows.sort((a, b) => safeString(b[24]).localeCompare(safeString(a[24])));
      break;
  }
}

function finishFilterAndRender() {
  const tireCountEl = getDOMElement("tireCount");
  if (tireCountEl) {
    tireCountEl.textContent = `Showing ${state.filteredRows.length} tire${state.filteredRows.length === 1 ? "" : "s"}`;
  }
  if (state.initialRenderDone) {
    state.currentPage = 1;
  } else {
    const totalPages = Math.max(1, Math.ceil(state.filteredRows.length / ROWS_PER_PAGE));
    if (state.currentPage > totalPages) {
      state.currentPage = totalPages;
    }
    state.initialRenderDone = true;
  }
  throttledRender();
  updateURLFromFilters();
  renderActiveFilterChips();
}

export function populateDropdown(id, values) {
  const select = getDOMElement(id);
  if (!select) return;

  const cleaned = [...new Set(values.map(v => safeString(v).trim()))]
    .filter(v => v && v.length <= 100)
    .sort((a, b) => a.localeCompare(b))
    .slice(0, 200);

  cleaned.forEach(v => {
    const option = document.createElement("option");
    option.value = v;
    option.textContent = v;
    select.appendChild(option);
  });
}

export function populateSizeDropdownGrouped(id, rows) {
  const select = getDOMElement(id);
  if (!select) return;

  const sizes = [...new Set(rows.map(r => safeString(r[1])).filter(v => v))];
  const groups = {};

  sizes.forEach(size => {
    const match = size.match(/R(\d{2})/i);
    const rim = match ? match[1] : "Other";
    if (!groups[rim]) groups[rim] = [];
    groups[rim].push(size);
  });

  const sortedRims = Object.keys(groups).sort((a, b) => {
    if (a === "Other") return 1;
    if (b === "Other") return -1;
    return parseInt(a) - parseInt(b);
  });

  select.innerHTML = "";

  const defaultOption = document.createElement("option");
  defaultOption.value = "";
  defaultOption.textContent = "All Sizes";
  select.appendChild(defaultOption);

  sortedRims.forEach(rim => {
    const optgroup = document.createElement("optgroup");
    optgroup.label = `${rim}" Wheels`;

    groups[rim].sort().forEach(size => {
      const option = document.createElement("option");
      option.value = size;
      option.textContent = size;
      optgroup.appendChild(option);
    });

    select.appendChild(optgroup);
  });
}

export function setupSliderHandlers() {
  const sliders = [
    { id: "priceMax", label: "priceVal", format: val => `$${val}`, bounds: NUMERIC_BOUNDS.price },
    { id: "warrantyMax", label: "warrantyVal", format: val => `${Number(val).toLocaleString()} miles`, bounds: NUMERIC_BOUNDS.warranty },
    { id: "weightMax", label: "weightVal", format: val => `${val} lb`, bounds: NUMERIC_BOUNDS.weight },
  ];

  sliders.forEach(({ id, label, format, bounds }) => {
    const input = getDOMElement(id);
    const output = getDOMElement(label);
    if (input && output) {
      input.addEventListener("input", () => {
        const validValue = validateNumeric(input.value, bounds, bounds.max);
        input.value = validValue;
        output.textContent = format(validValue);
        updateSliderBackground(input);
      });

      input.addEventListener("input", debounce(filterAndRender, 400));

      const initialValue = validateNumeric(input.value, bounds, bounds.max);
      output.textContent = format(initialValue);
      updateSliderBackground(input);
    }
  });
}

export function resetFilters() {
  const elements = [
    { id: "searchInput", value: "" },
    { id: "filterSize", value: "" },
    { id: "filterBrand", value: "" },
    { id: "filterCategory", value: "" },
    { id: "priceMax", value: 600 },
    { id: "warrantyMax", value: 80000 },
    { id: "weightMax", value: 70 },
    { id: "sortBy", value: "rating-desc" }
  ];

  elements.forEach(({ id, value }) => {
    const el = getDOMElement(id);
    if (el) el.value = value;
  });

  const checkboxes = ["filter3pms", "filterEVRated", "filterStudded", "filterReviewed", "filterFavorites"];
  checkboxes.forEach(id => {
    const el = getDOMElement(id);
    if (el) el.checked = false;
  });

  const displayUpdates = [
    { id: "priceVal", text: "$600" },
    { id: "warrantyVal", text: "80,000 miles" },
    { id: "weightVal", text: "70 lb" }
  ];

  displayUpdates.forEach(({ id, text }) => {
    const el = getDOMElement(id);
    if (el) el.textContent = text;
  });

  ["priceMax", "warrantyMax", "weightMax"].forEach(id => {
    const slider = getDOMElement(id);
    if (slider) updateSliderBackground(slider);
  });

  hideSearchSuggestions();
  delete state.domCache["searchInput"];
  state.lastFilterState = null;

  document.dispatchEvent(new CustomEvent('filtersReset'));
  if (isServerSide()) {
    serverSideFilterAndRender();
  } else {
    filterAndRender();
  }
  history.replaceState(null, "", location.pathname);
}

/* === Active Filter Chips === */
export function renderActiveFilterChips() {
  const container = getDOMElement("activeFilters");
  if (!container) return;

  const chips = [];

  const searchVal = getDOMElement("searchInput")?.value?.trim();
  if (searchVal) {
    chips.push({ label: "Search", value: searchVal, clear: () => { const el = getDOMElement("searchInput"); if (el) el.value = ""; delete state.domCache["searchInput"]; } });
  }

  const sizeEl = getDOMElement("filterSize");
  if (sizeEl?.value) {
    chips.push({ label: "Size", value: sizeEl.value, clear: () => { sizeEl.value = ""; } });
  }

  const brandEl = getDOMElement("filterBrand");
  if (brandEl?.value) {
    chips.push({ label: "Brand", value: brandEl.value, clear: () => { brandEl.value = ""; } });
  }

  const categoryEl = getDOMElement("filterCategory");
  if (categoryEl?.value) {
    chips.push({ label: "Category", value: categoryEl.value, clear: () => { categoryEl.value = ""; } });
  }

  const priceEl = getDOMElement("priceMax");
  const priceVal = priceEl ? parseInt(priceEl.value) : 600;
  if (priceVal < 600) {
    chips.push({ label: "Price", value: "\u2264 $" + priceVal, clear: () => { priceEl.value = 600; const lbl = getDOMElement("priceVal"); if (lbl) lbl.textContent = "$600"; updateSliderBackground(priceEl); } });
  }

  const warrantyEl = getDOMElement("warrantyMax");
  const warrantyVal = warrantyEl ? parseInt(warrantyEl.value) : 80000;
  if (warrantyVal < 80000) {
    chips.push({ label: "Warranty", value: "\u2264 " + Number(warrantyVal).toLocaleString() + " mi", clear: () => { warrantyEl.value = 80000; const lbl = getDOMElement("warrantyVal"); if (lbl) lbl.textContent = "80,000 miles"; updateSliderBackground(warrantyEl); } });
  }

  const weightEl = getDOMElement("weightMax");
  const weightVal = weightEl ? parseInt(weightEl.value) : 70;
  if (weightVal < 70) {
    chips.push({ label: "Weight", value: "\u2264 " + weightVal + " lb", clear: () => { weightEl.value = 70; const lbl = getDOMElement("weightVal"); if (lbl) lbl.textContent = "70 lb"; updateSliderBackground(weightEl); } });
  }

  if (getDOMElement("filter3pms")?.checked) {
    chips.push({ label: "3PMS", value: "Yes", clear: () => { getDOMElement("filter3pms").checked = false; } });
  }
  if (getDOMElement("filterEVRated")?.checked) {
    chips.push({ label: "EV Rated", value: "Yes", clear: () => { getDOMElement("filterEVRated").checked = false; } });
  }
  if (getDOMElement("filterStudded")?.checked) {
    chips.push({ label: "Studded", value: "Yes", clear: () => { getDOMElement("filterStudded").checked = false; } });
  }
  if (getDOMElement("filterReviewed")?.checked) {
    chips.push({ label: "Reviewed", value: "Yes", clear: () => { getDOMElement("filterReviewed").checked = false; } });
  }
  if (getDOMElement("filterFavorites")?.checked) {
    chips.push({ label: "Favorites", value: "Yes", clear: () => { getDOMElement("filterFavorites").checked = false; } });
  }

  container.innerHTML = "";

  chips.forEach(chip => {
    const el = document.createElement("span");
    el.className = "filter-chip";

    const label = document.createElement("span");
    label.className = "filter-chip-label";
    label.textContent = chip.label + ":";

    const value = document.createElement("span");
    value.textContent = chip.value;

    const dismiss = document.createElement("button");
    dismiss.className = "filter-chip-dismiss";
    dismiss.setAttribute("aria-label", "Remove " + chip.label + " filter");
    dismiss.innerHTML = rtgIcon('xmark', 12);
    dismiss.addEventListener("click", () => {
      chip.clear();
      state.lastFilterState = null;
      if (isServerSide()) {
        serverSideFilterAndRender();
      } else {
        filterAndRender();
      }
    });

    el.appendChild(label);
    el.appendChild(value);
    el.appendChild(dismiss);
    container.appendChild(el);
  });
}

export function renderSmartNoResults() {
  const container = getDOMElement("noResults");
  if (!container) return;

  const suggestions = [];
  const activeFilterNames = [];

  const sizeEl = getDOMElement("filterSize");
  const brandEl = getDOMElement("filterBrand");
  const categoryEl = getDOMElement("filterCategory");
  const priceEl = getDOMElement("priceMax");
  const warrantyEl = getDOMElement("warrantyMax");
  const weightEl = getDOMElement("weightMax");
  const searchEl = getDOMElement("searchInput");

  if (sizeEl?.value) activeFilterNames.push("Size");
  if (brandEl?.value) activeFilterNames.push("Brand");
  if (categoryEl?.value) activeFilterNames.push("Category");
  if (priceEl && parseInt(priceEl.value) < 600) activeFilterNames.push("Price");
  if (warrantyEl && parseInt(warrantyEl.value) < 80000) activeFilterNames.push("Warranty");
  if (weightEl && parseInt(weightEl.value) < 70) activeFilterNames.push("Weight");
  if (getDOMElement("filter3pms")?.checked) activeFilterNames.push("3PMS");
  if (getDOMElement("filterEVRated")?.checked) activeFilterNames.push("EV Rated");
  if (getDOMElement("filterStudded")?.checked) activeFilterNames.push("Studded");
  if (getDOMElement("filterReviewed")?.checked) activeFilterNames.push("Reviewed");
  if (getDOMElement("filterFavorites")?.checked) activeFilterNames.push("Favorites");
  if (searchEl?.value?.trim()) activeFilterNames.push("Search");

  if (activeFilterNames.length > 1) {
    suggestions.push({
      label: rtgIcon('rotate-left', 14) + ' Clear all filters',
      action: () => resetFilters()
    });
  }

  if (getDOMElement("filterFavorites")?.checked) {
    suggestions.push({
      label: rtgIcon('heart-outline', 14) + ' Show all tires (not just favorites)',
      action: () => { getDOMElement("filterFavorites").checked = false; state.lastFilterState = null; filterAndRender(); }
    });
  }

  if (sizeEl?.value) {
    suggestions.push({
      label: rtgIcon('ruler', 14) + ` Remove size filter (${escapeHTML(sizeEl.value)})`,
      action: () => { sizeEl.value = ""; state.lastFilterState = null; filterAndRender(); }
    });
  }

  if (brandEl?.value) {
    suggestions.push({
      label: rtgIcon('building', 14) + ' Show all brands',
      action: () => { brandEl.value = ""; state.lastFilterState = null; filterAndRender(); }
    });
  }

  if (categoryEl?.value) {
    suggestions.push({
      label: rtgIcon('tags', 14) + ' Show all categories',
      action: () => { categoryEl.value = ""; state.lastFilterState = null; filterAndRender(); }
    });
  }

  if (priceEl && parseInt(priceEl.value) < 600) {
    suggestions.push({
      label: rtgIcon('dollar-sign', 14) + ' Increase price limit to max',
      action: () => { priceEl.value = 600; getDOMElement("priceVal").textContent = "$600"; updateSliderBackground(priceEl); state.lastFilterState = null; filterAndRender(); }
    });
  }

  if (getDOMElement("filter3pms")?.checked) {
    suggestions.push({
      label: rtgIcon('snowflake', 14) + ' Remove 3PMS filter',
      action: () => { getDOMElement("filter3pms").checked = false; state.lastFilterState = null; filterAndRender(); }
    });
  }

  if (searchEl?.value?.trim()) {
    suggestions.push({
      label: rtgIcon('magnifying-glass', 14) + ` Clear search "${escapeHTML(safeString(searchEl.value.trim(), 30))}"`,
      action: () => { searchEl.value = ""; delete state.domCache["searchInput"]; state.lastFilterState = null; filterAndRender(); }
    });
  }

  const displaySuggestions = suggestions.slice(0, 4);

  container.innerHTML = '';

  const icon = document.createElement('div');
  icon.className = 'no-results-icon';
  icon.innerHTML = rtgIcon('magnifying-glass', 24);
  container.appendChild(icon);

  const title = document.createElement('div');
  title.className = 'no-results-title';
  title.textContent = 'No tires match your filters';
  container.appendChild(title);

  const desc = document.createElement('div');
  desc.className = 'no-results-description';
  if (activeFilterNames.length > 0) {
    desc.textContent = `You have ${activeFilterNames.length} active filter${activeFilterNames.length > 1 ? 's' : ''}: ${activeFilterNames.join(', ')}. Try adjusting your filters to see more options.`;
  } else {
    desc.textContent = 'Try adjusting the filters or search to see more options.';
  }
  container.appendChild(desc);

  if (displaySuggestions.length > 0) {
    const suggestionsContainer = document.createElement('div');
    suggestionsContainer.className = 'no-results-suggestions';

    displaySuggestions.forEach(suggestion => {
      const btn = document.createElement('button');
      btn.className = 'no-results-suggestion-btn';
      btn.innerHTML = suggestion.label;
      btn.addEventListener('click', suggestion.action);
      suggestionsContainer.appendChild(btn);
    });

    container.appendChild(suggestionsContainer);
  }
}

export function updateURLFromFilters() {
  const params = new URLSearchParams();

  if (state.currentPage > 1) {
    params.set("pg", state.currentPage);
  }

  const getVal = id => getDOMElement(id)?.value;
  const getChecked = id => getDOMElement(id)?.checked;

  const searchVal = getVal("searchInput");
  if (searchVal && VALIDATION_PATTERNS.search.test(searchVal)) {
    params.set("search", searchVal);
  }

  const sizeVal = getVal("filterSize");
  if (sizeVal && state.VALID_SIZES.includes(sizeVal)) {
    params.set("size", sizeVal);
  }

  const brandVal = getVal("filterBrand");
  if (brandVal && state.VALID_BRANDS.includes(brandVal)) {
    params.set("brand", brandVal);
  }

  const categoryVal = getVal("filterCategory");
  if (categoryVal && state.VALID_CATEGORIES.includes(categoryVal)) {
    params.set("category", categoryVal);
  }

  if (getChecked("filter3pms")) params.set("3pms", "1");
  if (getChecked("filterEVRated")) params.set("ev", "1");
  if (getChecked("filterStudded")) params.set("studded", "1");
  if (getChecked("filterReviewed")) params.set("reviewed", "1");

  const currentSort = getVal("sortBy");
  if (currentSort && ALLOWED_SORT_OPTIONS.includes(currentSort) && currentSort !== "rating-desc") {
    params.set("sort", currentSort);
  }

  const priceVal = parseInt(getVal("priceMax"));
  if (priceVal && priceVal !== 600 && priceVal >= 0 && priceVal <= 2000) {
    params.set("price", priceVal);
  }

  const warrantyVal = parseInt(getVal("warrantyMax"));
  if (warrantyVal && warrantyVal !== 80000 && warrantyVal >= 0 && warrantyVal <= 100000) {
    params.set("warranty", warrantyVal);
  }

  const weightVal = parseInt(getVal("weightMax"));
  if (weightVal && weightVal !== 70 && weightVal >= 0 && weightVal <= 200) {
    params.set("weight", weightVal);
  }

  if (getDOMElement("filterFavorites")?.checked) {
    params.set("favorites", "1");
  }

  const newURL = params.toString()
    ? `${location.pathname}?${params.toString()}`
    : `${location.pathname}`;

  if (state.initialRenderDone && newURL !== location.pathname + location.search) {
    history.pushState(null, "", newURL);
  } else {
    history.replaceState(null, "", newURL);
  }
}

export function applyFiltersFromURL() {
  const params = new URLSearchParams(window.location.search);

  const setVal = (id, val) => {
    const el = document.getElementById(id);
    if (el && val !== null) {
      const sanitized = sanitizeInput(val, VALIDATION_PATTERNS.search);
      el.value = sanitized;
    }
  };

  const setChecked = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.checked = val === "1";
  };

  const searchParam = params.get("search");
  if (searchParam) {
    setVal("searchInput", searchParam);
  }

  const size = sanitizeInput(params.get("size"));
  if (size && state.VALID_SIZES.includes(size)) {
    const el = document.getElementById("filterSize");
    if (el) el.value = size;
  }

  const brand = sanitizeInput(params.get("brand"));
  if (brand && state.VALID_BRANDS.includes(brand)) {
    const el = document.getElementById("filterBrand");
    if (el) el.value = brand;
  }

  const category = sanitizeInput(params.get("category"));
  if (category && state.VALID_CATEGORIES.includes(category)) {
    const el = document.getElementById("filterCategory");
    if (el) el.value = category;
  }

  setChecked("filter3pms", params.get("3pms"));
  setChecked("filterEVRated", params.get("ev"));
  setChecked("filterStudded", params.get("studded"));
  setChecked("filterReviewed", params.get("reviewed"));
  setChecked("filterFavorites", params.get("favorites"));

  const sort = params.get("sort");
  if (sort && ALLOWED_SORT_OPTIONS.includes(sort)) {
    const el = document.getElementById("sortBy");
    if (el) el.value = sort;
  }

  const price = params.get("price");
  const warranty = params.get("warranty");
  const weight = params.get("weight");

  if (price) {
    const validPrice = validateNumeric(price, NUMERIC_BOUNDS.price, 600);
    const el = document.getElementById("priceMax");
    if (el) el.value = validPrice;
  }

  if (warranty) {
    const validWarranty = validateNumeric(warranty, NUMERIC_BOUNDS.warranty, 80000);
    const el = document.getElementById("warrantyMax");
    if (el) el.value = validWarranty;
  }

  if (weight) {
    const validWeight = validateNumeric(weight, NUMERIC_BOUNDS.weight, 70);
    const el = document.getElementById("weightMax");
    if (el) el.value = validWeight;
  }

  const pageParam = params.get("pg") || params.get("page");
  if (pageParam) {
    const validPage = validateNumeric(pageParam, NUMERIC_BOUNDS.page, 1);
    state.currentPage = validPage;
  } else {
    state.currentPage = 1;
  }
}

export function applyCompareFromURL() {
  const params = new URLSearchParams(window.location.search);
  const compareParam = params.get("compare");
  if (!compareParam) return;

  const { updateCompareBar } = require_compare();
  const indexes = compareParam.split(",")
    .map(n => {
      const num = parseInt(n.trim());
      return validateNumeric(num, { min: 0, max: 10000 }, -1);
    })
    .filter(n => n >= 0)
    .slice(0, 4);

  state.compareList = indexes;

  document.querySelectorAll(".compare-checkbox").forEach(cb => {
    const idx = parseInt(cb.dataset.index);
    if (state.compareList.includes(idx)) cb.checked = true;
  });

  updateCompareBar();
}

// Lazy helper to avoid circular import — compare module is simple, import it inline.
function require_compare() {
  // This will be resolved by esbuild at bundle time
  return { updateCompareBar: require_updateCompareBar };
}

// Will be set by the main entry point
let require_updateCompareBar = () => {};
export function setUpdateCompareBar(fn) {
  require_updateCompareBar = fn;
}

export function applyTireDeepLink() {
  const params = new URLSearchParams(window.location.search);
  const tireParam = params.get("tire");
  if (!tireParam || !VALIDATION_PATTERNS.tireId.test(tireParam)) return;

  const tireRow = state.allRows.find(row => row[0] === tireParam);
  if (!tireRow) return;

  state.filteredRows = [tireRow];
  state.currentPage = 1;

  const filterWrapper = document.querySelector(".filter-wrapper");
  const sortWrapper = document.querySelector(".sort-wrapper");
  const activeFilters = getDOMElement("activeFilters");
  const paginationControls = getDOMElement("paginationControls");
  const toggleBtn = document.querySelector(".toggle-filters-btn");
  if (filterWrapper) filterWrapper.style.display = "none";
  if (sortWrapper) sortWrapper.style.display = "none";
  if (activeFilters) activeFilters.style.display = "none";
  if (paginationControls) paginationControls.style.display = "none";
  if (toggleBtn) toggleBtn.style.display = "none";

  const tireSection = getDOMElement("tireSection");
  if (tireSection) {
    const backBar = document.createElement("div");
    backBar.className = "tire-deeplink-bar";

    const backBtn = document.createElement("a");
    backBtn.href = window.location.pathname;
    backBtn.className = "tire-deeplink-back";
    backBtn.innerHTML = rtgIcon('arrow-left', 14) + ' View all tires';

    backBar.appendChild(backBtn);
    tireSection.parentNode.insertBefore(backBar, tireSection);
  }
}

export function applyShortcodePrefilters() {
  if (typeof rtgData === 'undefined' || !rtgData.settings || !rtgData.settings.prefilters) return;
  const pf = rtgData.settings.prefilters;
  if (pf.size) {
    const el = document.getElementById('filterSize');
    if (el) el.value = pf.size;
  }
  if (pf.brand) {
    const el = document.getElementById('filterBrand');
    if (el) el.value = pf.brand;
  }
  if (pf.category) {
    const el = document.getElementById('filterCategory');
    if (el) el.value = pf.category;
  }
  if (pf.sort) {
    const el = document.getElementById('sortBy');
    if (el) el.value = pf.sort;
  }
  if (pf.three_pms) {
    const el = document.getElementById('filter3pms');
    if (el) el.checked = true;
  }
}
