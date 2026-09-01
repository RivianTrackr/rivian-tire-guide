/* jshint esversion: 11 */

/**
 * Search box wiring and the precise-match rule the filter pipeline uses.
 *
 * The user types a query and explicitly clicks the "Search" button (or
 * presses Enter) to filter the local tire list. The index-building and
 * Levenshtein code that once backed typeahead suggestions was removed with
 * the suggestions themselves — nothing had read it since.
 */

import { getDOMElement } from './helpers.js';
import { filterAndRender } from './filters.js';

/**
 * Execute local search — delegates to the main filter pipeline.
 */
function executeLocalSearch() {
  filterAndRender();
}

// Tracked listeners so initializeSmartSearch() can be called multiple times
// without leaking handlers (previous implementation cloned the input node,
// orphaning references held elsewhere in the module cache).
let searchKeydownHandler = null;
let searchButtonHandler = null;

export function initializeSmartSearch() {
  const searchInput = getDOMElement('searchInput');
  if (!searchInput) return;

  // Remove any previously attached listeners before re-binding.
  if (searchKeydownHandler) {
    searchInput.removeEventListener('keydown', searchKeydownHandler);
  }

  // Search button triggers local filter.
  const searchBtn = document.getElementById('rtgSearchSubmit');
  if (searchBtn) {
    if (searchButtonHandler) {
      searchBtn.removeEventListener('click', searchButtonHandler);
    }
    searchButtonHandler = () => executeLocalSearch();
    searchBtn.addEventListener('click', searchButtonHandler);
  }

  // Enter key triggers local search.
  searchKeydownHandler = (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      executeLocalSearch();
    }
  };
  searchInput.addEventListener('keydown', searchKeydownHandler);

  // Clear input on filter reset. (resetFilters() dispatches this; the old
  // document-wide click handler that text-matched "Clear All" is gone.)
  document.addEventListener('filtersReset', () => {
    const input = document.querySelector('#searchInput');
    if (input) {
      input.value = '';
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
