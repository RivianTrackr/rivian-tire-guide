/* jshint esversion: 11 */

/**
 * Smart search — index building, fuzzy matching, and button-based search.
 *
 * The user types a query and explicitly clicks the "Search" button (or
 * presses Enter) to filter locally, or clicks the "AI" button to get
 * AI-powered recommendations.
 */

import { state } from './state.js';
import { safeString, getDOMElement } from './helpers.js';
import { filterAndRender } from './filters.js';
import { isAiActive, clearAiRecommendations } from './ai-recommend.js';

/**
 * Check whether the AI feature is enabled for this page load.
 */
function isAiEnabled() {
  return typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.aiEnabled;
}

// Smart Search Variables
let searchIndex = {
  brands: new Map(),
  models: new Map(),
  categories: new Map(),
  sizes: new Map(),
  tags: new Map(),
  combined: new Map()
};

export function buildSearchIndex() {
  console.time('Building search index');

  Object.values(searchIndex).forEach(index => index.clear());

  state.allRows.forEach((row, rowIndex) => {
    const [tireId, size, diameter, brand, model, category, price, warranty, weight, tpms, tread, loadIndex, maxLoad, loadRange, speed, psi, utqg, tags] = row;

    const safeBrand = safeString(brand).toLowerCase().trim();
    const safeModel = safeString(model).toLowerCase().trim();
    const safeCategory = safeString(category).toLowerCase().trim();
    const safeSize = safeString(size).toLowerCase().trim();
    const safeTags = safeString(tags).toLowerCase().trim();

    if (safeBrand) {
      if (!searchIndex.brands.has(safeBrand)) {
        searchIndex.brands.set(safeBrand, {
          display: safeString(brand).trim(),
          count: 0,
          rows: []
        });
      }
      const brandData = searchIndex.brands.get(safeBrand);
      brandData.count++;
      brandData.rows.push(rowIndex);
    }

    if (safeModel) {
      const modelKey = `${safeBrand} ${safeModel}`;
      if (!searchIndex.models.has(modelKey)) {
        searchIndex.models.set(modelKey, {
          display: `${safeString(brand).trim()} ${safeString(model).trim()}`,
          brand: safeString(brand).trim(),
          model: safeString(model).trim(),
          count: 0,
          rows: []
        });
      }
      const modelData = searchIndex.models.get(modelKey);
      modelData.count++;
      modelData.rows.push(rowIndex);
    }

    if (safeCategory) {
      if (!searchIndex.categories.has(safeCategory)) {
        searchIndex.categories.set(safeCategory, {
          display: safeString(category).trim(),
          count: 0,
          rows: []
        });
      }
      const categoryData = searchIndex.categories.get(safeCategory);
      categoryData.count++;
      categoryData.rows.push(rowIndex);
    }

    if (safeSize) {
      if (!searchIndex.sizes.has(safeSize)) {
        searchIndex.sizes.set(safeSize, {
          display: safeString(size).trim(),
          count: 0,
          rows: []
        });
      }
      const sizeData = searchIndex.sizes.get(safeSize);
      sizeData.count++;
      sizeData.rows.push(rowIndex);
    }

    if (safeTags) {
      const tagList = safeTags.split(/[,|]/).map(tag => tag.trim()).filter(Boolean);
      tagList.forEach(tag => {
        if (!searchIndex.tags.has(tag)) {
          searchIndex.tags.set(tag, {
            display: tag,
            count: 0,
            rows: []
          });
        }
        const tagData = searchIndex.tags.get(tag);
        tagData.count++;
        if (!tagData.rows.includes(rowIndex)) {
          tagData.rows.push(rowIndex);
        }
      });
    }

    const combinedTerms = [
      safeBrand, safeModel, safeCategory, safeSize,
      ...safeTags.split(/[,|]/).map(tag => tag.trim()).filter(Boolean)
    ].filter(Boolean);

    combinedTerms.forEach(term => {
      if (term.length >= 2) {
        if (!searchIndex.combined.has(term)) {
          searchIndex.combined.set(term, new Set());
        }
        searchIndex.combined.get(term).add(rowIndex);
      }
    });
  });
}

export function fuzzyMatch(pattern, text, threshold = 0.7) {
  if (pattern.length === 0) return 1;
  if (text.length === 0) return 0;

  const patternLower = pattern.toLowerCase();
  const textLower = text.toLowerCase();

  if (textLower.includes(patternLower)) {
    return patternLower === textLower ? 1 : 0.9;
  }

  const matrix = Array(pattern.length + 1).fill(null).map(() =>
    Array(text.length + 1).fill(null)
  );

  for (let i = 0; i <= pattern.length; i++) matrix[i][0] = i;
  for (let j = 0; j <= text.length; j++) matrix[0][j] = j;

  for (let i = 1; i <= pattern.length; i++) {
    for (let j = 1; j <= text.length; j++) {
      const cost = patternLower[i - 1] === textLower[j - 1] ? 0 : 1;
      matrix[i][j] = Math.min(
        matrix[i - 1][j] + 1,
        matrix[i][j - 1] + 1,
        matrix[i - 1][j - 1] + cost
      );
    }
  }

  const distance = matrix[pattern.length][text.length];
  const maxLength = Math.max(pattern.length, text.length);
  const similarity = 1 - distance / maxLength;

  return similarity >= threshold ? similarity : 0;
}

/**
 * No-op — kept for backward compatibility with modules that import it.
 */
export function hideSearchSuggestions() {}

/**
 * Execute local search: clear AI state if active and run filterAndRender.
 */
function executeLocalSearch() {
  if (isAiEnabled() && isAiActive()) {
    clearAiRecommendations(true); // this calls filterAndRender internally
  } else {
    filterAndRender();
  }
}

export function initializeSmartSearch() {
  const searchInput = getDOMElement('searchInput');
  if (!searchInput) return;

  buildSearchIndex();

  // Clone to remove any previously attached listeners.
  const newSearchInput = searchInput.cloneNode(true);
  searchInput.parentNode.replaceChild(newSearchInput, searchInput);
  state.domCache["searchInput"] = newSearchInput;

  // Search button triggers local filter.
  const searchBtn = document.getElementById('rtgSearchSubmit');
  if (searchBtn) {
    searchBtn.addEventListener('click', () => {
      executeLocalSearch();
    });
  }

  // Enter key triggers local search.
  newSearchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      executeLocalSearch();
    }
  });

  // Clear input on filter reset.
  document.addEventListener('filtersReset', () => {
    const input = document.querySelector('#searchInput');
    if (input) {
      input.value = '';
    }
  });

  document.addEventListener('click', (e) => {
    if (e.target.textContent && e.target.textContent.includes('Clear All')) {
      setTimeout(() => {
        const input = document.querySelector('#searchInput');
        if (input) {
          input.value = '';
        }
      }, 50);
    }
  });
}

export function isPreciseMatch(text, query) {
  const textLower = text.toLowerCase().trim();
  const queryLower = query.toLowerCase().trim();

  if (!queryLower) return true;
  if (textLower === queryLower) return true;
  if (textLower.startsWith(queryLower)) return true;

  const textParts = textLower.split(/\s+/);
  const queryParts = queryLower.split(/\s+/);

  const allPartsMatch = queryParts.every(queryPart => {
    return textParts.some(textPart => {
      if (textPart === queryPart) return true;
      if (queryPart.length >= 3 && textPart.startsWith(queryPart)) return true;
      if (queryPart.includes('/') || queryPart.includes('-')) {
        return textPart === queryPart || textPart.startsWith(queryPart);
      }
      return false;
    });
  });

  if (allPartsMatch) return true;

  if (queryLower.length >= 4 && textLower.includes(queryLower)) {
    return true;
  }

  return false;
}
