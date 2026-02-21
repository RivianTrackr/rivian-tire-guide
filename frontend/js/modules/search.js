/* jshint esversion: 11 */

/**
 * Smart search — index building, fuzzy matching, suggestions dropdown.
 */

import { state } from './state.js';
import { rtgColor, rtgIcon, safeString, getDOMElement, debounce } from './helpers.js';
import { filterAndRender } from './filters.js';

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

// Search suggestion cache
let suggestionCache = new Map();
const CACHE_LIMIT = 100;

// Debounced search function
let searchTimeout = null;
const SEARCH_DELAY = 200;

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

function generateSearchSuggestions(query, limit = 8) {
  if (!query || query.length < 1) return [];

  const cacheKey = `${query.toLowerCase()}_${limit}`;
  if (suggestionCache.has(cacheKey)) {
    return suggestionCache.get(cacheKey);
  }

  const queryLower = query.toLowerCase().trim();
  const suggestions = [];
  const seen = new Set();

  const addSuggestion = (item, type, score = 1) => {
    const key = `${type}:${item.display.toLowerCase()}`;
    if (!seen.has(key) && suggestions.length < limit) {
      seen.add(key);
      suggestions.push({
        ...item,
        type,
        score,
        query: queryLower
      });
    }
  };

  const isGoodMatch = (text, query) => {
    const textLower = text.toLowerCase();
    const qLower = query.toLowerCase();

    if (textLower === qLower) return { match: true, score: 1.0 };
    if (textLower.startsWith(qLower)) return { match: true, score: 0.9 };

    const words = textLower.split(/[\s\-\/\+\(\)]+/);
    const queryWords = qLower.split(/[\s\-\/\+\(\)]+/);

    const wordMatch = words.some(word => word.startsWith(qLower));
    if (wordMatch) return { match: true, score: 0.8 };

    const allWordsMatch = queryWords.every(qWord =>
      qWord.length >= 2 && words.some(word => word.startsWith(qWord))
    );
    if (allWordsMatch && queryWords.length > 1) return { match: true, score: 0.7 };

    if (qLower.length === 1) {
      return { match: false, score: 0 };
    }

    if (qLower.length <= 3) {
      const hasWordBoundary = words.some(word => word.startsWith(qLower));
      return { match: hasWordBoundary, score: hasWordBoundary ? 0.6 : 0 };
    }

    if (textLower.includes(qLower) && qLower.length >= 4) {
      return { match: true, score: 0.5 };
    }

    return { match: false, score: 0 };
  };

  searchIndex.brands.forEach((item) => {
    const matchResult = isGoodMatch(item.display, queryLower);
    if (matchResult.match) {
      addSuggestion(item, 'brand', matchResult.score);
    }
  });

  searchIndex.models.forEach((item) => {
    const brandMatch = isGoodMatch(item.brand, queryLower);
    const modelMatch = isGoodMatch(item.model, queryLower);
    const fullMatch = isGoodMatch(item.display, queryLower);

    const bestMatch = Math.max(brandMatch.score, modelMatch.score, fullMatch.score);
    if (bestMatch > 0) {
      addSuggestion(item, 'model', bestMatch * 0.95);
    }
  });

  searchIndex.categories.forEach((item) => {
    const matchResult = isGoodMatch(item.display, queryLower);
    if (matchResult.match) {
      addSuggestion(item, 'category', matchResult.score * 0.9);
    }
  });

  searchIndex.sizes.forEach((item) => {
    const matchResult = isGoodMatch(item.display, queryLower);
    if (matchResult.match) {
      addSuggestion(item, 'size', matchResult.score * 0.85);
    }
  });

  if (suggestions.length < 3 && queryLower.length >= 4) {
    const fuzzyMatches = [];

    searchIndex.combined.forEach((rowSet, term) => {
      if (term.length >= queryLower.length) {
        const similarity = fuzzyMatch(queryLower, term, 0.8);
        if (similarity > 0.8 && fuzzyMatches.length < 10) {
          const brandMatch = searchIndex.brands.get(term);
          const categoryMatch = searchIndex.categories.get(term);

          if (brandMatch) {
            fuzzyMatches.push({ item: brandMatch, type: 'brand', score: similarity * 0.6 });
          } else if (categoryMatch) {
            fuzzyMatches.push({ item: categoryMatch, type: 'category', score: similarity * 0.5 });
          }
        }
      }
    });

    fuzzyMatches
      .sort((a, b) => b.score - a.score)
      .slice(0, limit - suggestions.length)
      .forEach(match => addSuggestion(match.item, match.type, match.score));
  }

  const sortedSuggestions = suggestions
    .sort((a, b) => {
      if (Math.abs(a.score - b.score) > 0.05) {
        return b.score - a.score;
      }
      return b.count - a.count;
    })
    .slice(0, limit);

  if (suggestionCache.size >= CACHE_LIMIT) {
    const firstKey = suggestionCache.keys().next().value;
    suggestionCache.delete(firstKey);
  }
  suggestionCache.set(cacheKey, sortedSuggestions);

  return sortedSuggestions;
}

function showSearchSuggestions(suggestions, inputElement) {
  const existingDropdown = document.querySelector('.search-suggestions-dropdown');
  if (existingDropdown) {
    existingDropdown.remove();
  }

  // When AI is enabled we always show the dropdown (for the "Ask AI" row)
  // even when there are no local search matches.
  const hasAi = isAiEnabled() && inputElement.value.trim().length >= 2;
  if (!suggestions.length && !hasAi) return;

  const dropdown = document.createElement('div');
  dropdown.className = 'search-suggestions-dropdown';
  dropdown.style.cssText = `
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: ${rtgColor('bg-primary')};
    border: 1px solid #475569;
    border-radius: 0 0 8px 8px;
    border-top: none;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  `;

  suggestions.forEach((suggestion, index) => {
    const item = document.createElement('div');
    item.className = 'search-suggestion-item';
    item.style.cssText = `
      padding: 12px 16px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      transition: background-color 0.2s ease;
      border-bottom: 1px solid ${rtgColor('border')};
    `;

    if (index === suggestions.length - 1) {
      item.style.borderBottom = 'none';
    }

    const getIcon = (type) => {
      switch (type) {
        case 'brand': return 'building';
        case 'model': return 'circle';
        case 'category': return 'tags';
        case 'size': return 'ruler';
        default: return 'magnifying-glass';
      }
    };

    const content = document.createElement('div');
    content.style.cssText = 'display: flex; align-items: center; gap: 12px; flex: 1;';

    const icon = document.createElement('span');
    icon.innerHTML = rtgIcon(getIcon(suggestion.type), 14);
    icon.style.cssText = `color: ${rtgColor('text-muted')}; width: 16px; display: flex; align-items: center;`;

    const text = document.createElement('div');
    text.style.cssText = `color: ${rtgColor('text-light')}; font-weight: 500;`;
    text.textContent = suggestion.display;

    content.appendChild(icon);
    content.appendChild(text);

    const meta = document.createElement('div');
    meta.style.cssText = 'display: flex; align-items: center; gap: 8px;';

    const typeBadge = document.createElement('span');
    typeBadge.style.cssText = `
      background: ${rtgColor('bg-input')};
      color: #9ca3af;
      font-size: 11px;
      padding: 2px 6px;
      border-radius: 4px;
      text-transform: uppercase;
      font-weight: 600;
    `;
    typeBadge.textContent = suggestion.type;

    const count = document.createElement('span');
    count.style.cssText = 'color: #6b7280; font-size: 12px;';
    count.textContent = `${suggestion.count} tire${suggestion.count !== 1 ? 's' : ''}`;

    meta.appendChild(typeBadge);
    meta.appendChild(count);

    item.appendChild(content);
    item.appendChild(meta);

    item.addEventListener('mouseenter', () => {
      item.style.backgroundColor = rtgColor('bg-input');
    });

    item.addEventListener('mouseleave', () => {
      item.style.backgroundColor = 'transparent';
    });

    item.addEventListener('click', () => {
      applySuggestion(suggestion, inputElement);
      hideSearchSuggestions();
    });

    dropdown.appendChild(item);
  });

  // Append an "Ask AI" suggestion when AI is enabled and there is a query.
  if (isAiEnabled()) {
    const currentQuery = inputElement.value.trim();
    if (currentQuery.length >= 2) {
      const aiItem = document.createElement('div');
      aiItem.className = 'search-suggestion-item search-suggestion-ai';
      aiItem.style.cssText = `
        padding: 12px 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: background-color 0.2s ease;
        border-top: 1px solid ${rtgColor('border')};
        background: color-mix(in srgb, ${rtgColor('accent')} 6%, ${rtgColor('bg-primary')});
      `;

      const aiContent = document.createElement('div');
      aiContent.style.cssText = 'display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;';

      const aiIcon = document.createElement('span');
      aiIcon.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>';
      aiIcon.style.cssText = `color: ${rtgColor('accent')}; width: 16px; display: flex; align-items: center; font-size: 14px;`;

      const aiText = document.createElement('div');
      aiText.style.cssText = `color: ${rtgColor('text-light')}; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;`;
      aiText.textContent = `Ask AI: "${currentQuery}"`;

      aiContent.appendChild(aiIcon);
      aiContent.appendChild(aiText);

      const aiBadge = document.createElement('span');
      aiBadge.style.cssText = `
        background: color-mix(in srgb, ${rtgColor('accent')} 20%, ${rtgColor('bg-input')});
        color: ${rtgColor('accent')};
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 4px;
        text-transform: uppercase;
        font-weight: 600;
        flex-shrink: 0;
      `;
      aiBadge.textContent = 'AI';

      aiItem.appendChild(aiContent);
      aiItem.appendChild(aiBadge);

      aiItem.addEventListener('mouseenter', () => {
        aiItem.style.backgroundColor = `color-mix(in srgb, ${rtgColor('accent')} 15%, ${rtgColor('bg-primary')})`;
      });

      aiItem.addEventListener('mouseleave', () => {
        aiItem.style.backgroundColor = `color-mix(in srgb, ${rtgColor('accent')} 6%, ${rtgColor('bg-primary')})`;
      });

      aiItem.addEventListener('click', () => {
        hideSearchSuggestions();
        import('./ai-recommend.js').then(({ submitAiQuery }) => {
          submitAiQuery(currentQuery);
        });
      });

      dropdown.appendChild(aiItem);
    }
  }

  const container = inputElement.closest('.search-container');
  if (container) {
    container.style.position = 'relative';
    container.appendChild(dropdown);
  }
}

function applySuggestion(suggestion, inputElement) {
  switch (suggestion.type) {
    case 'brand': {
      const brandSelect = getDOMElement('filterBrand');
      if (brandSelect) {
        brandSelect.value = suggestion.display;
      }
      inputElement.value = '';
      break;
    }
    case 'category': {
      const categorySelect = getDOMElement('filterCategory');
      if (categorySelect) {
        categorySelect.value = suggestion.display;
      }
      inputElement.value = '';
      break;
    }
    case 'size': {
      const sizeSelect = getDOMElement('filterSize');
      if (sizeSelect) {
        sizeSelect.value = suggestion.display;
      }
      inputElement.value = '';
      break;
    }
    case 'model': {
      const modelBrandSelect = getDOMElement('filterBrand');
      if (modelBrandSelect && suggestion.brand) {
        modelBrandSelect.value = suggestion.brand;
      }
      inputElement.value = suggestion.model;
      break;
    }
  }

  filterAndRender();
}

export function hideSearchSuggestions() {
  const dropdown = document.querySelector('.search-suggestions-dropdown');
  if (dropdown) {
    dropdown.style.opacity = '0';
    setTimeout(() => {
      if (dropdown.parentNode) {
        dropdown.parentNode.removeChild(dropdown);
      }
    }, 150);
  }
}

function handleSmartSearch(inputElement) {
  clearTimeout(searchTimeout);

  const query = inputElement.value.trim();

  if (query.length === 0) {
    hideSearchSuggestions();
    return;
  }

  searchTimeout = setTimeout(() => {
    const suggestions = generateSearchSuggestions(query);
    showSearchSuggestions(suggestions, inputElement);
  }, SEARCH_DELAY);
}

export function initializeSmartSearch() {
  const searchInput = getDOMElement('searchInput');
  if (!searchInput) return;

  buildSearchIndex();

  const newSearchInput = searchInput.cloneNode(true);
  searchInput.parentNode.replaceChild(newSearchInput, searchInput);

  state.domCache["searchInput"] = newSearchInput;

  newSearchInput.addEventListener('input', (e) => {
    handleSmartSearch(e.target);
    debounce(filterAndRender, 500)();
  });

  newSearchInput.addEventListener('focus', (e) => {
    if (e.target.value.trim()) {
      handleSmartSearch(e.target);
    }
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-container')) {
      hideSearchSuggestions();
    }
  });

  newSearchInput.addEventListener('keydown', (e) => {
    const dropdown = document.querySelector('.search-suggestions-dropdown');
    if (!dropdown) return;

    const items = dropdown.querySelectorAll('.search-suggestion-item');
    const activeItem = dropdown.querySelector('.search-suggestion-item.active');
    let activeIndex = activeItem ? Array.from(items).indexOf(activeItem) : -1;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, items.length - 1);
        break;
      case 'ArrowUp':
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, -1);
        break;
      case 'Enter':
        e.preventDefault();
        if (activeIndex >= 0) {
          items[activeIndex].click();
        }
        return;
      case 'Escape':
        hideSearchSuggestions();
        return;
      default:
        return;
    }

    items.forEach((item, index) => {
      if (index === activeIndex) {
        item.classList.add('active');
        item.style.backgroundColor = rtgColor('accent');
        item.style.color = '#1a1a1a';
      } else {
        item.classList.remove('active');
        item.style.backgroundColor = 'transparent';
        item.style.color = rtgColor('text-light');
      }
    });
  });

  document.addEventListener('filtersReset', () => {
    const input = document.querySelector('#searchInput');
    if (input) {
      input.value = '';
      hideSearchSuggestions();
    }
  });

  document.addEventListener('click', (e) => {
    if (e.target.textContent && e.target.textContent.includes('Clear All')) {
      setTimeout(() => {
        const input = document.querySelector('#searchInput');
        if (input) {
          input.value = '';
          hideSearchSuggestions();
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
