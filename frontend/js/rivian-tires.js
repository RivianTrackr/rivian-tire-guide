/* jshint esversion: 11 */

// Theme color helper — reads CSS custom properties set by admin.
function rtgColor(name) {
  return getComputedStyle(document.documentElement).getPropertyValue('--rtg-' + name).trim();
}

const ROWS_PER_PAGE = (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.rowsPerPage) ? rtgData.settings.rowsPerPage : 12;
let filteredRows = [];
let allRows = [];
let currentPage = 1;
let compareList = [];

// Pre-computed filter indexes for ultra-fast filtering
let sizeIndex = new Map();
let brandIndex = new Map();
let categoryIndex = new Map();
let priceIndex = [];
let warrantyIndex = [];
let weightIndex = [];

let VALID_SIZES = [];
let VALID_BRANDS = [];
let VALID_CATEGORIES = [];

// Global variables for ratings
let tireRatings = {};
let userRatings = {};
let isLoggedIn = false;

let activeTooltip = null;

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

const TOOLTIP_DATA = {
  'Load Index': {
    title: 'Load Index',
    content: 'Rivian vehicles require tires with a minimum load index of 116 to safely carry the vehicle\'s weight. Using a lower load index can affect safety, handling, and durability.'
  },
  '3PMS Rated': {
    title: '3PMS Rating',
    content: '3PMS (Three-Peak Mountain Snowflake) symbol indicates the tire meets winter traction requirements and is rated for severe snow service according to industry standards.'
  },
  'UTQG': {
    title: 'UTQG Rating',
    content: 'UTQG (Uniform Tire Quality Grading) provides standardized ratings for treadwear, temperature resistance (A, B, C), and traction performance (AA, A, B, C) to help compare tire quality.'
  },
  '3PMS Filter': {
    title: '3PMS Rating Filter',
    content: '3PMS (Three-Peak Mountain Snowflake) means the tire meets winter traction requirements and is rated for severe snow service.'
  },
  'EV Rated Filter': {
    title: 'EV Rated Filter',
    content: 'Filters for tires labeled as EV Rated in their specs or marketing. These are typically optimized for electric vehicles.'
  },
  'Studded Available Filter': {
    title: 'Studded Available Filter', 
    content: 'Filters for tires marked as "Studded Available" — these can be fitted with studs for enhanced traction on ice.'
  },
  'Efficiency Score': {
    title: 'Efficiency Score',
    content: 'This Efficiency Score is a calculated score RivianTrackr created to help assist Rivian owners in identifying which tires are likely to be the most range-friendly. It uses a custom formula that factors in weight, tread depth, tire width, load range, speed rating, UTQG, tire category, and winter certification (3PMS). Lighter tires with shallower tread and less aggressive construction typically score higher. <br><br> The score is an estimate only and does not reflect real-world testing. It should not be viewed as a measure of tire quality, safety, or brand reputation.'
  }
};

// Set login status immediately if WordPress data is available
if (typeof tireRatingAjax !== 'undefined') {
  isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
}

const ALLOWED_SORT_OPTIONS = [
  "efficiencyGrade", "price-asc", "price-desc",
  "warranty-desc", "weight-asc", "weight-desc",
  "alpha", "alpha-desc", "reviewed", "rating-desc", "rating-asc"
];

// Security: Input validation patterns
const VALIDATION_PATTERNS = {
  search: /^[a-zA-Z0-9\s\-\/\.\+\*\(\)]*$/,
  tireId: /^[a-zA-Z0-9\-_]+$/,
  numeric: /^\d+(\.\d+)?$/,
  affiliateUrl: /^https:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}\/[a-zA-Z0-9\-\/_\.%&=?#:+]*$/,
  imageUrl: /^https:\/\/riviantrackr\.com\/.*\.(jpg|jpeg|png|webp)$/i
};

// Security: Numeric bounds
const NUMERIC_BOUNDS = {
  price: { min: 0, max: 2000 },
  warranty: { min: 0, max: 100000 },
  weight: { min: 0, max: 200 },
  rating: { min: 1, max: 5 },
  page: { min: 1, max: 1000 }
};

// Enhanced performance optimizations
let domCache = {};
let isRendering = false;
let pendingRender = false;
let lastFilterState = null;
let renderAnimationFrame = null;

// Card cache and container
let cardCache = new Map();
let cardContainer = null;

// Event delegation setup flag
let eventDelegationSetup = false;

// Batch rating requests
let ratingRequestQueue = [];
let ratingRequestTimeout = null;

// Security: Enhanced HTML escaping
function escapeHTML(str) {
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
function safeString(input, maxLength = 200) {
  if (typeof input !== "string") return "";
  const cleaned = String(input).trim();
  return cleaned.length > maxLength ? cleaned.substring(0, maxLength) : cleaned;
}

// Security: Enhanced input sanitization
function sanitizeInput(str, pattern = null) {
  if (typeof str !== "string") return "";
  
  let cleaned;
  if (pattern === VALIDATION_PATTERNS.search) {
    cleaned = str.replace(/[<>\"'&\\]/g, "").trim();
  } else {
    cleaned = str.replace(/[<>\"'&\/\\]/g, "").trim();
  }
  
  if (pattern && !pattern.test(cleaned)) {
    console.warn('Input failed pattern validation:', cleaned);
    return "";
  }
  
  return cleaned.length > 100 ? cleaned.substring(0, 100) : cleaned;
}

// Security: Strict URL validation
function safeImageURL(url) {
  if (typeof url !== "string" || !url.trim()) return "";
  
  const trimmed = url.trim();
  
  if (!VALIDATION_PATTERNS.imageUrl.test(trimmed)) {
    if (trimmed.match(/\.(jpg|jpeg|png|webp|gif)$/i)) {
      console.warn('Image URL failed validation:', trimmed);
    }
    return "";
  }
  
  try {
    const urlObj = new URL(trimmed);
    
    if (urlObj.protocol !== 'https:') return "";
    if (urlObj.hostname !== 'riviantrackr.com') return "";
    
    if (urlObj.pathname.includes('..') || urlObj.pathname.includes('//')) {
      return "";
    }
    
    return trimmed;
  } catch (e) {
    if (trimmed.match(/\.(jpg|jpeg|png|webp|gif)$/i)) {
      console.warn('Invalid image URL:', trimmed);
    }
    return "";
  }
}

// Security: Safe affiliate link validation
function safeLinkURL(url) {
  if (typeof url !== "string" || !url.trim()) return "";
  
  const trimmed = url.trim();
  
  if (!VALIDATION_PATTERNS.affiliateUrl.test(trimmed)) {
    if (trimmed.startsWith('http')) {
      console.warn('Affiliate link URL failed validation:', trimmed);
    }
    return "";
  }
  
  try {
    const urlObj = new URL(trimmed);
    
    if (urlObj.protocol !== 'https:') return "";
    
    const allowedDomains = [
      'riviantrackr.com', 'tirerack.com', 'discounttire.com', 'amazon.com', 'amzn.to',
      'costco.com', 'walmart.com', 'goodyear.com', 'bridgestonetire.com', 'michelinman.com',
      'continental-tires.com', 'pirelli.com', 'sumitomotire.com', 'yokohamatire.com',
      'coopertire.com', 'bfgoodrichtires.com', 'firestone.com', 'generaltire.com',
      'hankooktire.com', 'kumhotire.com', 'nexentire.com', 'toyo.com', 'falkentire.com',
      'nittotire.com', 'autozone.com', 'pepboys.com', 'ntb.com', 'simpletire.com',
      'prioritytire.com', 'anrdoezrs.net', 'dpbolvw.net', 'jdoqocy.com', 'kqzyfj.com',
      'tkqlhce.com', 'commission-junction.com', 'cj.com', 'linksynergy.com',
      'click.linksynergy.com', 'shareasale.com', 'avantlink.com', 'impact.com', 'partnerize.com'
    ];
    
    const hostname = urlObj.hostname.toLowerCase();
    const isAllowed = allowedDomains.some(domain => {
      return hostname === domain || hostname.endsWith('.' + domain);
    });
    
    if (!isAllowed) {
      console.warn('Affiliate link domain not in allowlist:', hostname);
      return "";
    }
    
    if (urlObj.pathname.includes('..')) {
      return "";
    }
    
    return trimmed;
  } catch (e) {
    console.warn('Invalid affiliate link URL:', trimmed);
    return "";
  }
}

// NEW: Bundle link validation function
function safeBundleLinkURL(url) {
  if (typeof url !== "string" || !url.trim()) return "";
  
  const trimmed = url.trim();
  
  const bundleLinkPattern = /^https:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}\/[a-zA-Z0-9\-\/_\.%&=?#:+]*$/;
  
  if (!bundleLinkPattern.test(trimmed)) {
    return "";
  }
  
  try {
    const urlObj = new URL(trimmed);
    
    if (urlObj.protocol !== 'https:') return "";
    
    const allowedBundleDomains = [
      'riviantrackr.com', 'evsportline.com', 'www.evsportline.com', 'tsportline.com',
      'www.tsportline.com', 'tirerack.com', 'www.tirerack.com', 'discounttire.com',
      'www.discounttire.com', 'costco.com', 'www.costco.com', 'walmart.com',
      'www.walmart.com', 'amazon.com', 'www.amazon.com', 'amzn.to'
    ];
    
    const hostname = urlObj.hostname.toLowerCase();
    const isAllowed = allowedBundleDomains.some(domain => {
      return hostname === domain || hostname.endsWith('.' + domain);
    });
    
    if (!isAllowed) {
      console.warn('Bundle link domain not in allowlist:', hostname);
      return "";
    }
    
    if (urlObj.pathname.includes('..')) {
      return "";
    }
    
    return trimmed;
  } catch (e) {
    console.warn('Invalid bundle link URL:', trimmed);
    return "";
  }
}

// Security: Validate numeric input
function validateNumeric(value, bounds, defaultValue = 0) {
  if (typeof value === 'string') {
    if (!VALIDATION_PATTERNS.numeric.test(value)) {
      return defaultValue;
    }
    value = parseFloat(value);
  }
  
  if (typeof value !== 'number' || isNaN(value)) {
    return defaultValue;
  }
  
  if (value < bounds.min || value > bounds.max) {
    console.warn(`Numeric value ${value} outside bounds [${bounds.min}, ${bounds.max}]`);
    return Math.max(bounds.min, Math.min(bounds.max, value));
  }
  
  return value;
}

function getDOMElement(id) {
  if (!domCache[id]) {
    domCache[id] = document.getElementById(id);
  }
  return domCache[id];
}

function debounce(fn, delay) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn.apply(this, args), delay);
  };
}

function throttledRender() {
  if (isRendering) {
    pendingRender = true;
    return;
  }
  
  if (renderAnimationFrame) {
    cancelAnimationFrame(renderAnimationFrame);
  }
  
  renderAnimationFrame = requestAnimationFrame(() => {
    isRendering = true;
    render();
    
    setTimeout(() => {
      isRendering = false;
      renderAnimationFrame = null;
      if (pendingRender) {
        pendingRender = false;
        throttledRender();
      }
    }, 16);
  });
}

function buildSearchIndex() {
  console.time('Building search index');
  
  Object.values(searchIndex).forEach(index => index.clear());
  
  allRows.forEach((row, rowIndex) => {
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

function fuzzyMatch(pattern, text, threshold = 0.7) {
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
    const queryLower = query.toLowerCase();
    
    if (textLower === queryLower) return { match: true, score: 1.0 };
    if (textLower.startsWith(queryLower)) return { match: true, score: 0.9 };
    
    const words = textLower.split(/[\s\-\/\+\(\)]+/);
    const queryWords = queryLower.split(/[\s\-\/\+\(\)]+/);
    
    const wordMatch = words.some(word => word.startsWith(queryLower));
    if (wordMatch) return { match: true, score: 0.8 };
    
    const allWordsMatch = queryWords.every(qWord => 
      qWord.length >= 2 && words.some(word => word.startsWith(qWord))
    );
    if (allWordsMatch && queryWords.length > 1) return { match: true, score: 0.7 };
    
    if (queryLower.length === 1) {
      return { match: false, score: 0 };
    }
    
    if (queryLower.length <= 3) {
      const hasWordBoundary = words.some(word => word.startsWith(queryLower));
      return { match: hasWordBoundary, score: hasWordBoundary ? 0.6 : 0 };
    }
    
    if (textLower.includes(queryLower) && queryLower.length >= 4) {
      return { match: true, score: 0.5 };
    }
    
    return { match: false, score: 0 };
  };
  
  searchIndex.brands.forEach((item, key) => {
    const matchResult = isGoodMatch(item.display, queryLower);
    if (matchResult.match) {
      addSuggestion(item, 'brand', matchResult.score);
    }
  });
  
  searchIndex.models.forEach((item, key) => {
    const brandMatch = isGoodMatch(item.brand, queryLower);
    const modelMatch = isGoodMatch(item.model, queryLower);
    const fullMatch = isGoodMatch(item.display, queryLower);
    
    const bestMatch = Math.max(brandMatch.score, modelMatch.score, fullMatch.score);
    if (bestMatch > 0) {
      addSuggestion(item, 'model', bestMatch * 0.95);
    }
  });
  
  searchIndex.categories.forEach((item, key) => {
    const matchResult = isGoodMatch(item.display, queryLower);
    if (matchResult.match) {
      addSuggestion(item, 'category', matchResult.score * 0.9);
    }
  });
  
  searchIndex.sizes.forEach((item, key) => {
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
  
  if (!suggestions.length) return;
  
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
        case 'brand': return 'fa-solid fa-building';
        case 'model': return 'fa-solid fa-circle';
        case 'category': return 'fa-solid fa-tags';
        case 'size': return 'fa-solid fa-ruler';
        default: return 'fa-solid fa-search';
      }
    };
    
    const content = document.createElement('div');
    content.style.cssText = 'display: flex; align-items: center; gap: 12px; flex: 1;';
    
    const icon = document.createElement('i');
    icon.className = getIcon(suggestion.type);
    icon.style.cssText = `color: ${rtgColor('text-muted')}; font-size: 14px; width: 16px;`;

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
  
  const container = inputElement.closest('.search-container');
  if (container) {
    container.style.position = 'relative';
    container.appendChild(dropdown);
  }
}

function applySuggestion(suggestion, inputElement) {
  switch (suggestion.type) {
    case 'brand':
      const brandSelect = getDOMElement('filterBrand');
      if (brandSelect) {
        brandSelect.value = suggestion.display;
      }
      inputElement.value = '';
      break;
      
    case 'category':
      const categorySelect = getDOMElement('filterCategory');
      if (categorySelect) {
        categorySelect.value = suggestion.display;
      }
      inputElement.value = '';
      break;
      
    case 'size':
      const sizeSelect = getDOMElement('filterSize');
      if (sizeSelect) {
        sizeSelect.value = suggestion.display;
      }
      inputElement.value = '';
      break;
      
    case 'model':
      const modelBrandSelect = getDOMElement('filterBrand');
      if (modelBrandSelect && suggestion.brand) {
        modelBrandSelect.value = suggestion.brand;
      }
      inputElement.value = suggestion.model;
      break;
  }
  
  filterAndRender();
}

function hideSearchSuggestions() {
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

function initializeSmartSearch() {
  const searchInput = getDOMElement('searchInput');
  if (!searchInput) return;
  
  buildSearchIndex();
  
  const newSearchInput = searchInput.cloneNode(true);
  searchInput.parentNode.replaceChild(newSearchInput, searchInput);
  
  domCache["searchInput"] = newSearchInput;
  
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
    const searchInput = document.querySelector('#searchInput');
    if (searchInput) {
      searchInput.value = '';
      hideSearchSuggestions();
    }
  });

  document.addEventListener('click', (e) => {
    if (e.target.textContent && e.target.textContent.includes('Clear All')) {
      setTimeout(() => {
        const searchInput = document.querySelector('#searchInput');
        if (searchInput) {
          searchInput.value = '';
          hideSearchSuggestions();
        }
      }, 50);
    }
  });
}

function isPreciseMatch(text, query) {
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

function createInfoTooltip(label, tooltipKey) {
  const container = document.createElement('div');
  container.style.cssText = `display: flex; align-items: center; gap: 6px;`;
  
  const labelText = document.createElement('span');
  labelText.textContent = label;
  labelText.style.cssText = `font-weight: 700; font-size: 15px;`;
  
  const infoButton = document.createElement('button');
  infoButton.innerHTML = '<i class="fa-solid fa-circle-info"></i>';
  infoButton.className = 'info-tooltip-trigger';
  infoButton.dataset.tooltipKey = tooltipKey;
  infoButton.style.cssText = `
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 14px;
    cursor: pointer;
    padding: 2px;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
  `;
  
  infoButton.addEventListener('mouseenter', () => {
    infoButton.style.color = rtgColor('accent');
    infoButton.style.backgroundColor = `color-mix(in srgb, ${rtgColor('accent')} 10%, transparent)`;
  });
  
  infoButton.addEventListener('mouseleave', () => {
    infoButton.style.color = rtgColor('text-muted');
    infoButton.style.backgroundColor = 'transparent';
  });
  
  container.appendChild(labelText);
  container.appendChild(infoButton);
  
  return container;
}

function createFilterTooltip(labelText, tooltipKey) {
  const container = document.createElement('div');
  container.style.cssText = `display: flex; align-items: center; gap: 6px;`;
  
  const label = document.createElement('span');
  label.textContent = labelText;
  
  const infoButton = document.createElement('button');
  infoButton.innerHTML = '<i class="fa-solid fa-circle-info"></i>';
  infoButton.className = 'info-tooltip-trigger';
  infoButton.dataset.tooltipKey = tooltipKey;
  infoButton.style.cssText = `
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 14px;
    cursor: pointer;
    padding: 2px;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
  `;
  
  infoButton.addEventListener('mouseenter', () => {
    infoButton.style.color = rtgColor('accent');
    infoButton.style.backgroundColor = `color-mix(in srgb, ${rtgColor('accent')} 10%, transparent)`;
  });
  
  infoButton.addEventListener('mouseleave', () => {
    infoButton.style.color = rtgColor('text-muted');
    infoButton.style.backgroundColor = 'transparent';
  });
  
  container.appendChild(label);
  container.appendChild(infoButton);
  
  return container;
}

function showTooltipModal(tooltipKey, triggerElement) {
  closeTooltipModal();
  
  const tooltipData = TOOLTIP_DATA[tooltipKey];
  if (!tooltipData) return;
  
  const overlay = document.createElement('div');
  overlay.className = 'tooltip-modal-overlay';
  overlay.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.2s ease;
    backdrop-filter: blur(2px);
  `;
  
  const modal = document.createElement('div');
  modal.className = 'tooltip-modal';
  modal.style.cssText = `
    background: ${rtgColor('bg-primary')};
    border-radius: 12px;
    padding: 20px;
    max-width: 400px;
    width: 100%;
    color: ${rtgColor('text-light')};
    border: 1px solid #475569;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    animation: slideUp 0.2s ease;
    position: relative;
  `;
  
  const title = document.createElement('h3');
  title.textContent = tooltipData.title;
  title.style.cssText = `
    margin: 0 0 12px 0;
    font-size: 18px;
    font-weight: 700;
    color: ${rtgColor('accent')};
  `;

  const content = document.createElement('p');
  content.innerHTML = tooltipData.content;
  content.style.cssText = `
    margin: 0 0 16px 0;
    line-height: 1.5;
    font-size: 14px;
    color: #e2e8f0;
  `;
  
  const closeButton = document.createElement('button');
  closeButton.innerHTML = '<i class="fa-solid fa-xmark"></i>';
  closeButton.style.cssText = `
    position: absolute;
    top: 16px;
    right: 16px;
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 16px;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
  `;
  
  closeButton.addEventListener('mouseenter', () => {
    closeButton.style.color = rtgColor('text-light');
    closeButton.style.backgroundColor = 'rgba(148, 163, 184, 0.2)';
  });
  
  closeButton.addEventListener('mouseleave', () => {
    closeButton.style.color = rtgColor('text-muted');
    closeButton.style.backgroundColor = 'transparent';
  });
  
  const gotItButton = document.createElement('button');
  gotItButton.textContent = 'Got it';
  gotItButton.style.cssText = `
    background: ${rtgColor('accent')};
    color: #1a1a1a;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s ease;
    width: 100%;
  `;

  gotItButton.addEventListener('mouseenter', () => {
    gotItButton.style.backgroundColor = rtgColor('accent-hover');
  });

  gotItButton.addEventListener('mouseleave', () => {
    gotItButton.style.backgroundColor = rtgColor('accent');
  });
  
  const closeModal = () => closeTooltipModal();
  closeButton.addEventListener('click', closeModal);
  gotItButton.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });
  
  const handleEscape = (e) => {
    if (e.key === 'Escape') {
      closeModal();
      document.removeEventListener('keydown', handleEscape);
    }
  };
  document.addEventListener('keydown', handleEscape);
  
  modal.appendChild(title);
  modal.appendChild(content);
  modal.appendChild(closeButton);
  modal.appendChild(gotItButton);
  overlay.appendChild(modal);
  
  document.body.appendChild(overlay);
  activeTooltip = overlay;
  
  document.body.style.overflow = 'hidden';
  
  gotItButton.focus();
}

function closeTooltipModal() {
  if (activeTooltip) {
    activeTooltip.style.animation = 'fadeOut 0.2s ease';
    setTimeout(() => {
      if (activeTooltip && activeTooltip.parentNode) {
        activeTooltip.parentNode.removeChild(activeTooltip);
      }
      activeTooltip = null;
      document.body.style.overflow = '';
    }, 200);
  }
}

function buildFilterIndexes() {
  sizeIndex.clear();
  brandIndex.clear();
  categoryIndex.clear();
  priceIndex.length = 0;
  warrantyIndex.length = 0;
  weightIndex.length = 0;
  
  allRows.forEach((row, index) => {
    const [tireId, size, diameter, brand, model, category, price, warranty, weight] = row;
    
    const sizeKey = safeString(size).toLowerCase();
    if (sizeKey) {
      if (!sizeIndex.has(sizeKey)) sizeIndex.set(sizeKey, []);
      sizeIndex.get(sizeKey).push(index);
    }
    
    const brandKey = safeString(brand).toLowerCase();
    if (brandKey) {
      if (!brandIndex.has(brandKey)) brandIndex.set(brandKey, []);
      brandIndex.get(brandKey).push(index);
    }
    
    const categoryKey = safeString(category).toLowerCase();
    if (categoryKey) {
      if (!categoryIndex.has(categoryKey)) categoryIndex.set(categoryKey, []);
      categoryIndex.get(categoryKey).push(index);
    }
    
    const priceVal = validateNumeric(price, NUMERIC_BOUNDS.price);
    const warrantyVal = validateNumeric(warranty, NUMERIC_BOUNDS.warranty);
    const weightVal = validateNumeric(weight, NUMERIC_BOUNDS.weight);
    
    priceIndex.push({ index, value: priceVal });
    warrantyIndex.push({ index, value: warrantyVal });
    weightIndex.push({ index, value: weightVal });
  });
  
  priceIndex.sort((a, b) => a.value - b.value);
  warrantyIndex.sort((a, b) => a.value - b.value);
  weightIndex.sort((a, b) => a.value - b.value);
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

function validateAndSanitizeCSVRow(row) {
  if (!Array.isArray(row)) return null;
  if (row.length < 23) return null;

  const sanitized = new Array(row.length);
  
  for (let i = 0; i < row.length; i++) {
    const cell = row[i];
    
    if (typeof cell === "string") {
      sanitized[i] = cell.replace(/[<>\"'&]/g, "").trim();
      
      if (sanitized[i].length > 500) {
        sanitized[i] = sanitized[i].substring(0, 500);
      }
    } else if (typeof cell === "number") {
      if (i === 6) {
        sanitized[i] = validateNumeric(cell, NUMERIC_BOUNDS.price);
      } else if (i === 7) {
        sanitized[i] = validateNumeric(cell, NUMERIC_BOUNDS.warranty);
      } else if (i === 8) {
        sanitized[i] = validateNumeric(cell, NUMERIC_BOUNDS.weight);
      } else {
        sanitized[i] = typeof cell === 'number' && !isNaN(cell) ? cell : 0;
      }
    } else {
      sanitized[i] = "";
    }
  }

  if (!sanitized[0] || !VALIDATION_PATTERNS.tireId.test(sanitized[0])) {
    return null;
  }

  return sanitized;
}

function updateSliderBackground(slider) {
  const min = parseFloat(slider.min);
  const max = parseFloat(slider.max);
  const val = parseFloat(slider.value);
  const percent = ((val - min) / (max - min)) * 100;
  slider.style.setProperty('--percent', `${percent}%`);
}

function initializeRatingSystem() {
  if (typeof tireRatingAjax !== 'undefined') {
    isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
  }
}

function loadTireRatings(tireIds) {
  if (!tireIds.length) return Promise.resolve();
  
  const validTireIds = tireIds.filter(id => 
    typeof id === 'string' && 
    VALIDATION_PATTERNS.tireId.test(id) && 
    id.length <= 50
  );
  
  if (!validTireIds.length) return Promise.resolve();
  
  ratingRequestQueue.push(...validTireIds);
  
  if (ratingRequestTimeout) {
    clearTimeout(ratingRequestTimeout);
  }
  
  return new Promise((resolve) => {
    ratingRequestTimeout = setTimeout(() => {
      const uniqueIds = [...new Set(ratingRequestQueue)];
      ratingRequestQueue = [];
      
      if (typeof tireRatingAjax !== 'undefined') {
        isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
      }
      
      if (typeof tireRatingAjax === 'undefined') {
        console.warn('WordPress rating system not available');
        resolve();
        return;
      }
      
      const formData = new FormData();
      formData.append('action', 'get_tire_ratings');

      // Include nonce for logged-in users (required for CSRF protection).
      if (tireRatingAjax.nonce) {
        formData.append('nonce', tireRatingAjax.nonce);
      }

      uniqueIds.forEach(tireId => {
        formData.append('tire_ids[]', tireId);
      });
      
      fetch(tireRatingAjax.ajaxurl, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          tireRatings = { ...tireRatings, ...data.data.ratings };
          userRatings = { ...userRatings, ...data.data.user_ratings };
          isLoggedIn = data.data.is_logged_in === true || data.data.is_logged_in === '1' || data.data.is_logged_in === 1;
        }
        resolve();
      })
      .catch(error => {
        console.error('Error loading tire ratings:', error);
        resolve();
      });
    }, 50);
  });
}

function submitTireRating(tireId, rating) {
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    console.error('Invalid tire ID format');
    return Promise.reject('Invalid tire ID');
  }
  
  const validRating = validateNumeric(rating, NUMERIC_BOUNDS.rating);
  if (validRating !== rating) {
    console.error('Invalid rating value');
    return Promise.reject('Invalid rating');
  }
  
  if (!isLoggedIn) {
    if (typeof tireRatingAjax !== 'undefined' && tireRatingAjax.login_url) {
      window.location.href = tireRatingAjax.login_url;
    } else {
      alert('Please log in to rate tires');
    }
    return Promise.reject('Not logged in');
  }
  
  if (typeof tireRatingAjax === 'undefined' || !tireRatingAjax.nonce) {
    console.error('Missing security nonce');
    return Promise.reject('Security validation failed');
  }
  
  const formData = new FormData();
  formData.append('action', 'submit_tire_rating');
  formData.append('tire_id', tireId);
  formData.append('rating', validRating.toString());
  formData.append('nonce', tireRatingAjax.nonce);
  
  return fetch(tireRatingAjax.ajaxurl, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      tireRatings[tireId] = {
        average: validateNumeric(data.data.average_rating, { min: 0, max: 5 }),
        count: validateNumeric(data.data.rating_count, { min: 0, max: 10000 })
      };
      userRatings[tireId] = validateNumeric(data.data.user_rating, NUMERIC_BOUNDS.rating);
      
      updateRatingDisplay(tireId);
      
      return data.data;
    } else {
      throw new Error(data.data || 'Failed to save rating');
    }
  });
}

function createRatingHTML(tireId, average = 0, count = 0, userRating = 0) {
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    console.error('Invalid tire ID in rating creation');
    return '<div>Error: Invalid tire data</div>';
  }
  
  const displayAverage = validateNumeric(average, { min: 0, max: 5 }, 0);
  const displayCount = validateNumeric(count, { min: 0, max: 10000 }, 0);
  const validUserRating = (userRating && userRating > 0) ? validateNumeric(userRating, NUMERIC_BOUNDS.rating, 0) : 0;
  
  const isInteractive = isLoggedIn;
  const isHighRating = displayAverage >= 4.5 && displayCount >= 2;
  
  const container = document.createElement('div');
  container.className = 'tire-rating-container';
  container.dataset.tireId = tireId;
  if (isHighRating) {
    container.dataset.highRating = 'true';
  }
  
  const ratingDisplay = document.createElement('div');
  ratingDisplay.className = 'rating-display';
  
  const starsContainer = document.createElement('div');
  starsContainer.className = `rating-stars ${isInteractive ? 'interactive' : ''}`;
  starsContainer.dataset.tireId = tireId;
  
  for (let i = 1; i <= 5; i++) {
    const star = document.createElement('span');
    star.className = 'star';
    star.dataset.rating = i.toString();
    star.dataset.tireId = tireId;
    star.textContent = '★';
    
    if (displayAverage > 0 && i <= Math.round(displayAverage)) {
      star.classList.add('active');
    }
    if (validUserRating > 0 && i <= validUserRating) {
      star.classList.add('user-rated');
    }
    if (isInteractive) {
      star.style.cursor = 'pointer';
    }
    
    starsContainer.appendChild(star);
  }
  
  const ratingInfo = document.createElement('div');
  ratingInfo.className = 'rating-info';
  
  const averageSpan = document.createElement('span');
  averageSpan.className = 'rating-average';
  averageSpan.textContent = displayAverage > 0 ? displayAverage.toFixed(1) : 'No ratings';
  
  ratingInfo.appendChild(averageSpan);
  
  if (displayCount > 0) {
    const countSpan = document.createElement('span');
    countSpan.className = 'rating-count';
    countSpan.textContent = `(${displayCount} rating${displayCount !== 1 ? 's' : ''})`;
    ratingInfo.appendChild(countSpan);
  }
  
  ratingDisplay.appendChild(starsContainer);
  ratingDisplay.appendChild(ratingInfo);
  container.appendChild(ratingDisplay);
  
  if (!isLoggedIn) {
    const loginPrompt = document.createElement('div');
    loginPrompt.className = 'login-prompt';
    loginPrompt.style.cssText = 'font-size: 12px; color: #9ca3af; margin-top: 4px;';
    
    const loginLink = document.createElement('a');
    loginLink.href = typeof tireRatingAjax !== 'undefined' ? tireRatingAjax.login_url : '/wp-login.php';
    loginLink.style.color = rtgColor('accent');
    loginLink.textContent = 'Log in';
    
    loginPrompt.appendChild(loginLink);
    loginPrompt.appendChild(document.createTextNode(' to rate this tire'));
    container.appendChild(loginPrompt);
  }
  
  return container.outerHTML;
}

function updateRatingDisplay(tireId) {
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    console.error('Invalid tire ID in rating update');
    return;
  }
  
  const container = document.querySelector(`[data-tire-id="${CSS.escape(tireId)}"] .tire-rating-container`);
  if (!container) return;
  
  const ratingData = tireRatings[tireId] || { average: 0, count: 0 };
  const userRating = userRatings[tireId] || 0;
  
  container.outerHTML = createRatingHTML(tireId, ratingData.average, ratingData.count, userRating);
}

function setupEventDelegation() {
  if (eventDelegationSetup) return;
  
  document.addEventListener('click', function(e) {
    const star = e.target.closest('.rating-stars.interactive .star');
    if (!star) return;
    
    const tireId = star.dataset.tireId;
    const rating = parseInt(star.dataset.rating);
    
    if (!VALIDATION_PATTERNS.tireId.test(tireId) || 
        !Number.isInteger(rating) || 
        rating < 1 || rating > 5) {
      console.error('Invalid rating data');
      return;
    }
    
    star.style.opacity = '0.5';
    
    submitTireRating(tireId, rating)
      .then(() => {
        // Success feedback could be added here
      })
      .catch(error => {
        console.error('Rating submission failed:', error);
        star.style.opacity = '1';
        alert('Failed to save rating. Please try again.');
      });
  });
  
  document.addEventListener('mouseenter', function(e) {
    const star = e.target.closest('.rating-stars.interactive .star');
    if (!star || !isLoggedIn) return;
    
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
  
  eventDelegationSetup = true;
}

function applyFiltersFromURL() {
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
  if (size && VALID_SIZES.includes(size)) {
    const el = document.getElementById("filterSize");
    if (el) el.value = size;
  }

  const brand = sanitizeInput(params.get("brand"));
  if (brand && VALID_BRANDS.includes(brand)) {
    const el = document.getElementById("filterBrand");
    if (el) el.value = brand;
  }

  const category = sanitizeInput(params.get("category"));
  if (category && VALID_CATEGORIES.includes(category)) {
    const el = document.getElementById("filterCategory");
    if (el) el.value = category;
  }

  setChecked("filter3pms", params.get("3pms"));
  setChecked("filterEVRated", params.get("ev"));
  setChecked("filterStudded", params.get("studded"));

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

  const pageParam = params.get("page");
  if (pageParam) {
    const validPage = validateNumeric(pageParam, NUMERIC_BOUNDS.page, 1);
    currentPage = validPage;
  } else {
    currentPage = 1;
  }
}

function applyCompareFromURL() {
  const params = new URLSearchParams(window.location.search);
  const compareParam = params.get("compare");
  if (!compareParam) return;

  const indexes = compareParam.split(",")
    .map(n => {
      const num = parseInt(n.trim());
      return validateNumeric(num, { min: 0, max: 10000 }, -1);
    })
    .filter(n => n >= 0)
    .slice(0, 4);

  compareList = indexes;

  document.querySelectorAll(".compare-checkbox").forEach(cb => {
    const idx = parseInt(cb.dataset.index);
    if (compareList.includes(idx)) cb.checked = true;
  });

  updateCompareBar();
}

function renderCards(rows) {
  if (!cardContainer) {
    cardContainer = getDOMElement("tireCards");
  }
  
  if (typeof tireRatingAjax !== 'undefined') {
    isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
  }

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isMobile = window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  
  const animationDuration = prefersReducedMotion ? 0 : (isMobile ? 200 : 300);
  const animationDelay = prefersReducedMotion ? 0 : (isMobile ? 100 : 150);

  const targetTireIds = new Set(
    rows.map(row => row[0])
       .filter(id => VALIDATION_PATTERNS.tireId.test(id))
  );
  
  const currentCards = Array.from(cardContainer.children);
  const cardsToKeep = new Set();
  const cardsToRemove = [];
  
  currentCards.forEach(card => {
    const tireId = card.dataset.tireId;
    if (targetTireIds.has(tireId)) {
      cardsToKeep.add(tireId);
      const row = rows.find(r => r[0] === tireId);
      if (row) {
        const checkbox = card.querySelector('.compare-checkbox');
        if (checkbox) {
          checkbox.dataset.index = allRows.indexOf(row);
        }
      }
    } else {
      cardsToRemove.push(card);
    }
  });
  
  cardsToRemove.forEach(card => {
    if (prefersReducedMotion) {
      if (card.parentNode) {
        card.parentNode.removeChild(card);
      }
    } else {
      card.style.transition = `opacity ${animationDuration}ms ease, transform ${animationDuration}ms ease`;
      card.style.opacity = '0';
      card.style.transform = isMobile ? 'scale(0.98)' : 'scale(0.95)';
      
      setTimeout(() => {
        if (card.parentNode) {
          card.parentNode.removeChild(card);
        }
      }, animationDelay);
    }
  });
  
  const newCards = [];
  rows.forEach((row, index) => {
    const tireId = row[0];
    if (VALIDATION_PATTERNS.tireId.test(tireId) && !cardsToKeep.has(tireId)) {
      const card = createSingleCard(row);
      if (card) {
        if (!prefersReducedMotion) {
          card.style.opacity = '0';
          card.style.transform = isMobile ? 'scale(0.98)' : 'scale(0.95)';
        }
        newCards.push(card);
      }
    }
  });
  
  const fragment = document.createDocumentFragment();
  newCards.forEach(card => fragment.appendChild(card));
  cardContainer.appendChild(fragment);
  
  if (!prefersReducedMotion && newCards.length > 0) {
    requestAnimationFrame(() => {
      newCards.forEach((card, index) => {
        const staggerDelay = isMobile ? index * 50 : 0;
        
        setTimeout(() => {
          card.style.transition = `opacity ${animationDuration}ms ease, transform ${animationDuration}ms ease`;
          card.style.opacity = '1';
          card.style.transform = 'scale(1)';
        }, staggerDelay);
      });
    });
  } else if (prefersReducedMotion) {
    newCards.forEach(card => {
      card.style.opacity = '1';
      card.style.transform = 'scale(1)';
    });
  }
  
  const allCurrentCards = Array.from(cardContainer.children);
  const needsReorder = rows.some((row, index) => {
    const expectedTireId = row[0];
    const actualCard = allCurrentCards[index];
    return !actualCard || actualCard.dataset.tireId !== expectedTireId;
  });
  
  if (needsReorder) {
    rows.forEach((row, targetIndex) => {
      const tireId = row[0];
      if (!VALIDATION_PATTERNS.tireId.test(tireId)) return;
      
      const card = cardContainer.querySelector(`[data-tire-id="${CSS.escape(tireId)}"]`);
      if (card) {
        const currentIndex = Array.from(cardContainer.children).indexOf(card);
        if (currentIndex !== targetIndex) {
          const referenceCard = cardContainer.children[targetIndex];
          if (referenceCard && referenceCard !== card) {
            cardContainer.insertBefore(card, referenceCard);
          } else if (!referenceCard) {
            cardContainer.appendChild(card);
          }
        }
      }
    });
  }
  
  setupCompareCheckboxes();
}

function createSingleCard(row) {
  const [
    tireId, size, diameter, brand, model, category, price, warranty, weight, tpms,
    tread, loadIndex, maxLoad, loadRange, speed, psi, utqg, tags, link, image,
    efficiencyScore, efficiencyGrade, bundleLink
  ] = row;
  
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    console.error('Invalid tire ID in card creation:', tireId);
    return null;
  }
  
  const cacheKey = `card_${tireId}_${Date.now() % 10000}`;
  
  const ratingData = tireRatings[tireId] || { average: 0, count: 0 };
  const userRating = userRatings[tireId] || 0;
  const ratingHTML = createRatingHTML(tireId, ratingData.average, ratingData.count, userRating);
  
  const safeLink = safeLinkURL(link);
  const safeImage = safeImageURL(image);
  const safeBundleLink = safeBundleLinkURL(bundleLink);

  const card = document.createElement("div");
  card.className = "tire-card";
  card.style.cssText = `background: ${rtgColor('bg-card')}; border-radius: 12px; padding: 16px; color: ${rtgColor('text-primary')}; display: flex; flex-direction: column; gap: 8px; position: relative; min-height: 560px; transition: opacity 0.3s ease, transform 0.3s ease;`;
  card.dataset.tireId = tireId;

  if (safeString(tags).toLowerCase().includes("reviewed")) {
    const badge = document.createElement('div');
    badge.style.cssText = `position: absolute; top: 12px; left: 12px; z-index: 10; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 2.5px; border-radius: 10px; background: linear-gradient(135deg, #fba919, #d2de24, #86c440, #5ec095, #34c5ec, #2b96d2, #3571b8, #534da0, #d11d55, #ef3d6c, #ed1a36, #ee383a);`;
    
    const badgeInner = document.createElement('div');
    badgeInner.style.cssText = `background: #ffffff; color: #1a1a1a; font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;`;
    
    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-circle-check';
    icon.style.color = '#ed1a36';
    
    badgeInner.appendChild(icon);
    badgeInner.appendChild(document.createTextNode('Reviewed'));
    badge.appendChild(badgeInner);
    card.appendChild(badge);
  }

  if (safeImage) {
    const imageContainer = document.createElement('div');
    imageContainer.style.cssText = `position: relative; background: #fff; border-radius: 10px; padding: 0 20px; margin-bottom: 12px;`;

    const img = document.createElement('img');
    img.src = safeImage;
    img.alt = `${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`;
    img.loading = 'lazy';
    img.fetchpriority = 'low';
    img.style.cssText = `display: block; width: 100%; height: 160px; object-fit: cover; border-radius: 6px; cursor: zoom-in;`;
    img.onclick = () => openImageModal(safeImage, `${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`);

    imageContainer.appendChild(img);
    card.appendChild(imageContainer);
  }

  const brandEl = document.createElement('div');
  brandEl.style.cssText = `font-size: 14px; font-weight: 600; color: #cbd5e1; line-height: 1.1; margin-bottom: 2px;`;
  brandEl.textContent = safeString(brand);
  card.appendChild(brandEl);

  const modelEl = document.createElement('div');
  modelEl.style.cssText = `font-size: 22px; font-weight: 800; color: #ffffff; line-height: 1.2;`;
  modelEl.textContent = safeString(model);
  card.appendChild(modelEl);

  const ratingDiv = document.createElement('div');
  ratingDiv.innerHTML = ratingHTML;
  card.appendChild(ratingDiv);

// Tags (efficiency grade and others) - UPDATED WITH EFFICIENCY TOOLTIP
  const tagsContainer = document.createElement('div');
  tagsContainer.style.cssText = `display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px;`;
  
  // Efficiency grade tag WITH TOOLTIP
  if (efficiencyGrade) {
    const grade = safeString(efficiencyGrade).trim().toUpperCase();
    if (['A', 'B', 'C', 'D', 'E', 'F'].includes(grade)) {
      const gradeColor = {
        A: rtgColor('accent') || "#5ec095", B: "#a3e635", C: "#facc15",
        D: "#f97316", E: "#ef4444", F: "#b91c1c"
      }[grade];
      
      const gradeTag = document.createElement('span');
      gradeTag.style.cssText = `display: inline-flex; align-items: center; font-size: 12px; border: 1px solid ${gradeColor}; border-radius: 6px; overflow: hidden; font-weight: 600; line-height: 1;`;
      
      const gradeLabel = document.createElement('span');
      gradeLabel.style.cssText = `background-color: ${gradeColor}; color: #1a1a1a; padding: 2px 8px; font-weight: 800; font-size: 12px; line-height: 1.2; display: flex; align-items: center; height: 22px;`;
      gradeLabel.textContent = grade;
      
      const scoreSection = document.createElement('span');
      scoreSection.style.cssText = `color: ${rtgColor('text-light')}; background-color: transparent; padding: 2px 8px; line-height: 1.2; display: flex; align-items: center; height: 22px; gap: 4px;`;
      
      // Create the efficiency text span
      const scoreText = document.createElement('span');
      scoreText.textContent = `Efficiency (${escapeHTML(safeString(efficiencyScore))}/100)`;
      
      // Create the info icon button with same styling as other tooltips
      const infoButton = document.createElement('button');
      infoButton.innerHTML = '<i class="fa-solid fa-circle-info"></i>';
      infoButton.className = 'info-tooltip-trigger';
      infoButton.dataset.tooltipKey = 'Efficiency Score';
      infoButton.style.cssText = `
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 12px;
        cursor: pointer;
        padding: 1px;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
      `;
      
      // Hover effects - identical to other tooltips
      infoButton.addEventListener('mouseenter', () => {
        infoButton.style.color = rtgColor('accent');
        infoButton.style.backgroundColor = `color-mix(in srgb, ${rtgColor('accent')} 10%, transparent)`;
      });
      
      infoButton.addEventListener('mouseleave', () => {
        infoButton.style.color = '#94a3b8';
        infoButton.style.backgroundColor = 'transparent';
      });
      
      // Append text and icon to score section
      scoreSection.appendChild(scoreText);
      scoreSection.appendChild(infoButton);
      
      gradeTag.appendChild(gradeLabel);
      gradeTag.appendChild(scoreSection);
      tagsContainer.appendChild(gradeTag);
    }
  }

  // Other tags
  if (tags && safeString(tags).trim()) {
    const tagList = safeString(tags).split(/[,|]/).map(tag => tag.trim()).filter(tag => tag && tag.toLowerCase() !== 'reviewed');
    
    tagList.forEach(tag => {
      const tagEl = document.createElement('span');
      tagEl.style.cssText = `display: inline-flex; align-items: center; font-size: 12px; font-weight: 600; padding: 4px 10px; background-color: ${rtgColor('border')}; color: ${rtgColor('text-light')}; border-radius: 6px; line-height: 1;`;
      tagEl.textContent = safeString(tag, 30);
      tagsContainer.appendChild(tagEl);
    });
  }
  
  if (tagsContainer.children.length > 0) {
    card.appendChild(tagsContainer);
  }

  const specs = [
    ['Size', `${safeString(size)} (${safeString(diameter)})`],
    ['Category', safeString(category)],
    ['Average Price', price ? `$${validateNumeric(price, NUMERIC_BOUNDS.price)}` : '-'],
    ['Mileage Warranty', warranty ? `${Number(validateNumeric(warranty, NUMERIC_BOUNDS.warranty)).toLocaleString()} miles` : '-'],
    ['Weight', weight ? `${validateNumeric(weight, NUMERIC_BOUNDS.weight)} lb` : '-'],
    ['3PMS Rated', safeString(tpms)],
    ['Tread Depth', safeString(tread)],
    ['Load Index', safeString(loadIndex)],
    ['Max Load', maxLoad ? `${validateNumeric(maxLoad, { min: 0, max: 10000 })} lb` : '-'],
    ['Load Range', safeString(loadRange)],
    ['Speed Rating', safeString(speed)],
    ['Max PSI', safeString(psi)],
    ['UTQG', safeString(utqg)]
  ];

  specs.forEach(([label, value]) => {
    const specRow = document.createElement('div');
    specRow.style.cssText = `display: flex; justify-content: space-between; gap: 12px; align-items: center;`;
    
    const hasTooltip = TOOLTIP_DATA.hasOwnProperty(label);
    
    let labelEl;
    if (hasTooltip) {
      labelEl = createInfoTooltip(label, label);
    } else {
      labelEl = document.createElement('span');
      labelEl.style.cssText = `font-weight: 700; font-size: 15px;`;
      labelEl.textContent = label;
    }
    
    const valueEl = document.createElement('span');
    valueEl.textContent = value || '-';
    
    specRow.appendChild(labelEl);
    specRow.appendChild(valueEl);
    card.appendChild(specRow);
  });

  const actionsContainer = document.createElement('div');
  actionsContainer.style.cssText = `margin-top: auto; display: flex; flex-direction: column; gap: 8px;`;

  if (safeLink) {
    const viewButton = document.createElement('a');
    viewButton.href = safeLink;
    viewButton.target = '_blank';
    viewButton.rel = 'noopener noreferrer';
    viewButton.style.cssText = `background-color: ${rtgColor('accent')}; color: #1a1a1a; font-weight: 600; text-align: center; padding: 10px 16px; border-radius: 8px; text-decoration: none; display: block;`;
    viewButton.innerHTML = 'View Tire&nbsp;<i class="fa-solid fa-square-up-right"></i>';
    actionsContainer.appendChild(viewButton);
  } else {
    const comingSoon = document.createElement('span');
    comingSoon.style.cssText = `background-color: #334155; color: #cbd5e1; font-weight: 600; text-align: center; padding: 10px 16px; border-radius: 8px; display: block; cursor: default;`;
    comingSoon.textContent = 'Coming Soon';
    actionsContainer.appendChild(comingSoon);
  }

  if (safeBundleLink) {
    const bundleButton = document.createElement('a');
    bundleButton.href = safeBundleLink;
    bundleButton.target = '_blank';
    bundleButton.rel = 'noopener noreferrer';
    bundleButton.style.cssText = `background-color: #2563eb; color: #ffffff; font-weight: 600; text-align: center; padding: 10px 16px; border-radius: 8px; text-decoration: none; display: block;`;
    bundleButton.innerHTML = 'Wheel & Tire from EV Sportline&nbsp;<i class="fa-solid fa-square-up-right"></i>';
    
    actionsContainer.appendChild(bundleButton);
  }

  const compareLabel = document.createElement('label');
  compareLabel.className = 'compare-label';
  compareLabel.style.cssText = `background: transparent; border: 2px solid #fff; color: ${rtgColor('accent')}; font-weight: 600; text-align: center; padding: 10px 16px; border-radius: 8px; text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;`;

  const compareCheckbox = document.createElement('input');
  compareCheckbox.type = 'checkbox';
  compareCheckbox.className = 'compare-checkbox';
  compareCheckbox.dataset.id = tireId;
  compareCheckbox.dataset.index = allRows.indexOf(row).toString();
  compareCheckbox.style.display = 'none';

  const customCheckbox = document.createElement('span');
  customCheckbox.className = 'custom-checkbox';
  customCheckbox.style.cssText = `width: 18px; height: 18px; border: 2px solid ${rtgColor('accent')}; border-radius: 4px; background-color: transparent; position: relative; display: inline-block;`;

  const compareText = document.createElement('span');
  compareText.className = 'compare-text';
  compareText.textContent = 'Compare';

  compareLabel.appendChild(compareCheckbox);
  compareLabel.appendChild(customCheckbox);
  compareLabel.appendChild(compareText);
  actionsContainer.appendChild(compareLabel);

  card.appendChild(actionsContainer);

  if (cardCache.size > 100) {
    const firstKey = cardCache.keys().next().value;
    cardCache.delete(firstKey);
  }
  cardCache.set(cacheKey, card.cloneNode(true));

  return card;
}

function setupCompareCheckboxes() {
  const checkboxes = document.querySelectorAll(".compare-checkbox:not([data-listener-attached])");
  checkboxes.forEach(cb => {
    cb.dataset.listenerAttached = "true";
    cb.addEventListener("change", () => {
      const index = parseInt(cb.dataset.index);
      if (!Number.isInteger(index) || index < 0) return;
      
      if (cb.checked) {
        if (compareList.length >= 4) {
          cb.checked = false;
          return;
        }
        if (!compareList.includes(index)) compareList.push(index);
      } else {
        compareList = compareList.filter(i => i !== index);
      }
      updateCompareBar();
      document.querySelectorAll(".compare-checkbox").forEach(box => {
        if (!box.checked) box.disabled = compareList.length >= 4;
      });
    });
  });
}

function preloadNextPageImages() {
  const totalPages = Math.ceil(filteredRows.length / ROWS_PER_PAGE);
  
  if (currentPage >= totalPages) {
    return;
  }

  const nextStart = currentPage * ROWS_PER_PAGE;
  const nextRows = filteredRows.slice(nextStart, nextStart + ROWS_PER_PAGE);

  nextRows.forEach(row => {
    const image = row[19];
    const safeImage = safeImageURL(image);
    if (safeImage) {
      const img = new Image();
      img.src = safeImage;
    }
  });
}

function paginate(data) {
  const start = (currentPage - 1) * ROWS_PER_PAGE;
  return data.slice(start, start + ROWS_PER_PAGE);
}

function renderPaginationControls(totalRows) {
  const container = getDOMElement("paginationControls");
  container.innerHTML = "";
  const totalPages = Math.ceil(totalRows.length / ROWS_PER_PAGE);
  if (totalPages <= 1) return;
  
  const prev = document.createElement("button");
  prev.textContent = "Previous";
  prev.disabled = currentPage === 1;
  styleButton(prev);
  prev.onclick = () => {
    currentPage--;
    render();
    updateURLFromFilters();
    const filterTop = getDOMElement("filterTop");
    if (filterTop) filterTop.scrollIntoView({ behavior: "smooth" });
  };
  container.appendChild(prev);
  
  const pageInfo = document.createElement("span");
  pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
  pageInfo.style.cssText = `color: ${rtgColor('text-primary')}; font-weight: 500; display: flex; align-items: center;`;
  container.appendChild(pageInfo);
  
  const next = document.createElement("button");
  next.textContent = "Next";
  next.disabled = currentPage === totalPages;
  styleButton(next);
  next.onclick = () => {
    currentPage++;
    render();
    updateURLFromFilters();
    const filterTop = getDOMElement("filterTop");
    if (filterTop) filterTop.scrollIntoView({ behavior: "smooth" });
  };
  container.appendChild(next);
}

function styleButton(button) {
  button.style.backgroundColor = rtgColor('bg-primary');
  button.style.color = rtgColor('text-primary');
  button.style.padding = "8px 16px";
  button.style.border = "none";
  button.style.borderRadius = "6px";
  button.style.cursor = "pointer";
  button.style.fontWeight = "600";
  button.onmouseover = () => button.style.backgroundColor = rtgColor('accent');
  button.onmouseout = () => button.style.backgroundColor = rtgColor('bg-primary');
}

function render() {
  const visible = paginate(filteredRows);
  
  const tireIds = visible.map(row => row[0]).filter(Boolean);
  
  loadTireRatings(tireIds).then(() => {
    renderCards(visible);
    renderPaginationControls(filteredRows);
    
    const noResults = getDOMElement("noResults");
    const tireCards = getDOMElement("tireCards");
    if (filteredRows.length === 0) {
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

function updateURLFromFilters() {
  const params = new URLSearchParams();

  if (currentPage > 1) {
    params.set("page", currentPage);
  }

  const getVal = id => getDOMElement(id)?.value;
  const getChecked = id => getDOMElement(id)?.checked;

  const searchVal = getVal("searchInput");
  if (searchVal && VALIDATION_PATTERNS.search.test(searchVal)) {
    params.set("search", searchVal);
  }
  
  const sizeVal = getVal("filterSize");
  if (sizeVal && VALID_SIZES.includes(sizeVal)) {
    params.set("size", sizeVal);
  }
  
  const brandVal = getVal("filterBrand");
  if (brandVal && VALID_BRANDS.includes(brandVal)) {
    params.set("brand", brandVal);
  }
  
  const categoryVal = getVal("filterCategory");
  if (categoryVal && VALID_CATEGORIES.includes(categoryVal)) {
    params.set("category", categoryVal);
  }
  
  if (getChecked("filter3pms")) params.set("3pms", "1");
  if (getChecked("filterEVRated")) params.set("ev", "1");
  if (getChecked("filterStudded")) params.set("studded", "1");
  
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
  
  const newURL = params.toString()
    ? `${location.pathname}?${params.toString()}`
    : `${location.pathname}`;

  history.replaceState(null, "", newURL);
}

function getFilteredIndexes(filters) {
  let candidateIndexes = new Set(allRows.map((_, i) => i));
  
  if (filters.Size) {
    const sizeSet = new Set(sizeIndex.get(filters.Size.toLowerCase()) || []);
    candidateIndexes = new Set([...candidateIndexes].filter(x => sizeSet.has(x)));
  }
  
  if (filters.Brand) {
    const brandSet = new Set(brandIndex.get(filters.Brand.toLowerCase()) || []);
    candidateIndexes = new Set([...candidateIndexes].filter(x => brandSet.has(x)));
  }
  
  if (filters.Category) {
    const categorySet = new Set(categoryIndex.get(filters.Category.toLowerCase()) || []);
    candidateIndexes = new Set([...candidateIndexes].filter(x => categorySet.has(x)));
  }
  
  if (filters.PriceMax < 600) {
    const priceSet = binarySearchMax(priceIndex, filters.PriceMax);
    candidateIndexes = new Set([...candidateIndexes].filter(x => priceSet.has(x)));
  }
  
  if (filters.WarrantyMax < 80000) {
    const warrantySet = binarySearchMax(warrantyIndex, filters.WarrantyMax);
    candidateIndexes = new Set([...candidateIndexes].filter(x => warrantySet.has(x)));
  }
  
  if (filters.WeightMax < 70) {
    const weightSet = binarySearchMax(weightIndex, filters.WeightMax);
    candidateIndexes = new Set([...candidateIndexes].filter(x => weightSet.has(x)));
  }
  
  return [...candidateIndexes].map(i => allRows[i]).filter(row => {
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
    
    return true;
  });
}

function filterAndRender() {
  const searchInput = document.querySelector('#searchInput');
  const priceMax = getDOMElement("priceMax");
  const warrantyMax = getDOMElement("warrantyMax");
  const weightMax = getDOMElement("weightMax");
  const filter3pms = getDOMElement("filter3pms");
  const filterEVRated = getDOMElement("filterEVRated");
  const filterStudded = getDOMElement("filterStudded");
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
    Size: filterSize?.value && VALID_SIZES.includes(filterSize.value) ? filterSize.value : "",
    Brand: filterBrand?.value && VALID_BRANDS.includes(filterBrand.value) ? filterBrand.value : "",
    Category: filterCategory?.value && VALID_CATEGORIES.includes(filterCategory.value) ? filterCategory.value : ""
  };
  
  const sortOption = sortBy?.value && ALLOWED_SORT_OPTIONS.includes(sortBy.value) ? sortBy.value : "";
  
  const currentFilterState = JSON.stringify({
    ...f,
    sortOption,
    currentPage
  });
  
  if (lastFilterState === currentFilterState) {
    return;
  }
  lastFilterState = currentFilterState;
  
  filteredRows = getFilteredIndexes(f);

  if (sortOption === "rating-desc" || sortOption === "rating-asc") {
    const allFilteredTireIds = filteredRows.map(row => row[0]).filter(Boolean);
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
    filteredRows.sort((a, b) => {
      const aScore = validateNumeric(a[20], { min: 0, max: 100 }, 0);
      const bScore = validateNumeric(b[20], { min: 0, max: 100 }, 0);
      return bScore - aScore;
    });
    return;
  }
  
  switch (sortOption) {
    case "reviewed":
      filteredRows.sort((a, b) => {
        const aHasPick = safeString(a[17]).toLowerCase().includes("reviewed");
        const bHasPick = safeString(b[17]).toLowerCase().includes("reviewed");
        return bHasPick - aHasPick;
      });
      break;
      
    case "rating-desc":
      filteredRows.sort((a, b) => {
        const aRating = validateNumeric(tireRatings[a[0]]?.average, { min: 0, max: 5 }, 0);
        const bRating = validateNumeric(tireRatings[b[0]]?.average, { min: 0, max: 5 }, 0);
        const ratingDiff = bRating - aRating;
        
        if (Math.abs(ratingDiff) < 0.01) {
          const aCount = validateNumeric(tireRatings[a[0]]?.count, { min: 0, max: 10000 }, 0);
          const bCount = validateNumeric(tireRatings[b[0]]?.count, { min: 0, max: 10000 }, 0);
          const countDiff = bCount - aCount;
          return Math.abs(countDiff) < 0.01 ? safeString(a[0]).localeCompare(safeString(b[0])) : countDiff;
        }
        return ratingDiff;
      });
      break;
      
    case "rating-asc":
      filteredRows.sort((a, b) => {
        const aRating = validateNumeric(tireRatings[a[0]]?.average, { min: 0, max: 5 }, 0);
        const bRating = validateNumeric(tireRatings[b[0]]?.average, { min: 0, max: 5 }, 0);
        const ratingDiff = aRating - bRating;
        
        if (Math.abs(ratingDiff) < 0.01) {
          const aCount = validateNumeric(tireRatings[a[0]]?.count, { min: 0, max: 10000 }, 0);
          const bCount = validateNumeric(tireRatings[b[0]]?.count, { min: 0, max: 10000 }, 0);
          const countDiff = bCount - aCount;
          return Math.abs(countDiff) < 0.01 ? safeString(a[0]).localeCompare(safeString(b[0])) : countDiff;
        }
        return ratingDiff;
      });
      break;
      
    case "price-asc":
      filteredRows.sort((a, b) => validateNumeric(a[6], NUMERIC_BOUNDS.price, 0) - validateNumeric(b[6], NUMERIC_BOUNDS.price, 0));
      break;
    case "price-desc":
      filteredRows.sort((a, b) => validateNumeric(b[6], NUMERIC_BOUNDS.price, 0) - validateNumeric(a[6], NUMERIC_BOUNDS.price, 0));
      break;
    case "warranty-desc":
      filteredRows.sort((a, b) => validateNumeric(b[7], NUMERIC_BOUNDS.warranty, 0) - validateNumeric(a[7], NUMERIC_BOUNDS.warranty, 0));
      break;
    case "weight-asc":
      filteredRows.sort((a, b) => validateNumeric(a[8], NUMERIC_BOUNDS.weight, 0) - validateNumeric(b[8], NUMERIC_BOUNDS.weight, 0));
      break;
    case "weight-desc":
      filteredRows.sort((a, b) => validateNumeric(b[8], NUMERIC_BOUNDS.weight, 0) - validateNumeric(a[8], NUMERIC_BOUNDS.weight, 0));
      break;
    case "alpha":
      filteredRows.sort((a, b) => safeString(a[3]).toLowerCase().localeCompare(safeString(b[3]).toLowerCase()));
      break;
    case "alpha-desc":
      filteredRows.sort((a, b) => safeString(b[3]).toLowerCase().localeCompare(safeString(a[3]).toLowerCase()));
      break;
  }
}

function finishFilterAndRender() {
  const tireCountEl = getDOMElement("tireCount");
  if (tireCountEl) {
    tireCountEl.textContent = `Showing ${filteredRows.length} tire${filteredRows.length === 1 ? "" : "s"}`;
  }
  currentPage = 1;
  throttledRender();
  updateURLFromFilters();
}

function populateDropdown(id, values) {
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

function populateSizeDropdownGrouped(id, rows) {
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

function setupSliderHandlers() {
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

function resetFilters() {
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
  
  const checkboxes = ["filter3pms", "filterEVRated", "filterStudded"];
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
  delete domCache["searchInput"];
  lastFilterState = null;
  
  document.dispatchEvent(new CustomEvent('filtersReset'));
  filterAndRender();
  history.replaceState(null, "", location.pathname);
}

function updateCompareBar() {
  const bar = getDOMElement("compareBar");
  const count = getDOMElement("compareCount");
  if (!bar || !count) return;
  
  const validCount = Math.max(0, Math.min(4, compareList.length));
  count.textContent = `${validCount} of 4 tires selected`;
  bar.style.display = validCount >= 2 ? "flex" : "none";
}

function openComparison() {
  if (!compareList.length) return;
  
  const validIndexes = compareList
    .filter(index => Number.isInteger(index) && index >= 0 && index < allRows.length)
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

function clearCompare() {
  compareList = [];
  document.querySelectorAll(".compare-checkbox").forEach(cb => {
    cb.checked = false;
    cb.disabled = false;
  });
  updateCompareBar();
}

function initializeUI() {
  VALID_SIZES = [...new Set(allRows.map(r => safeString(r[1]).trim()))].filter(Boolean);
  VALID_BRANDS = [...new Set(allRows.map(r => safeString(r[3]).trim()))].filter(Boolean);
  VALID_CATEGORIES = [...new Set(allRows.map(r => safeString(r[5]).trim()))].filter(Boolean);
  
  populateSizeDropdownGrouped("filterSize", allRows);
  populateDropdown("filterBrand", allRows.map(r => r[3]));
  populateDropdown("filterCategory", allRows.map(r => r[5]));

  const inputsToWatch = [
    { id: "searchInput", listener: debounce(filterAndRender, 500) },
    { id: "filterSize", listener: filterAndRender },
    { id: "filterBrand", listener: filterAndRender },
    { id: "filterCategory", listener: filterAndRender },
    { id: "filter3pms", listener: filterAndRender },
    { id: "filterEVRated", listener: filterAndRender },
    { id: "filterStudded", listener: filterAndRender },
  ];

  inputsToWatch.forEach(({ id, listener }) => {
    const el = getDOMElement(id);
    if (el) {
      el.addEventListener("input", listener);
    }
  });

  applyFiltersFromURL();
  applyCompareFromURL();
  setupSliderHandlers();
  buildFilterIndexes();
  setupEventDelegation();
  initializeSmartSearch();
  filterAndRender();

  const countDisplay = getDOMElement("tireCount");
  if (countDisplay) {
    countDisplay.textContent = `Showing ${filteredRows.length} tire${filteredRows.length !== 1 ? "s" : ""}`;
  }
}

// Load tire data from WordPress localized script (replaces PapaParse CSV fetch).
if (typeof rtgData !== 'undefined' && rtgData.tires && Array.isArray(rtgData.tires)) {
  allRows = rtgData.tires
    .map(validateAndSanitizeCSVRow)
    .filter(row => row && row.length && row[0]);
  filteredRows = allRows;

  if (typeof tireRatingAjax !== 'undefined') {
    isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
  }
  initializeUI();
} else {
  console.error('Tire guide data not available. Ensure the [rivian_tire_guide] shortcode is used.');
}

document.addEventListener("click", (e) => {
  const modal = getDOMElement("compareModal");
  const content = modal?.querySelector("div");
  if (modal?.style.display === "flex" && modal.contains(e.target) && !content?.contains(e.target)) {
    modal.style.display = "none";
    document.body.style.overflow = "";
  }
});

function openImageModal(src, altText) {
  const safeSrc = safeImageURL(src);
  if (!safeSrc) {
    console.warn('Invalid image URL for modal:', src);
    return;
  }
  
  const modal = getDOMElement("imageModal");
  const img = getDOMElement("modalImage");
  if (!modal || !img) return;
  
  img.src = safeSrc;
  img.alt = escapeHTML(safeString(altText, 200));

  img.onerror = () => {
    const fallback = "https://riviantrackr.com/assets/tire-guide/images/image404.jpg";
    const safeFallback = safeImageURL(fallback);
    if (safeFallback) {
      img.src = safeFallback;
      img.alt = "Image not available";
    }
  };

  modal.style.display = "flex";
  document.body.style.overflow = "hidden";
  
  modal.onclick = (e) => {
    if (e.target === modal) {
      modal.style.display = "none";
      document.body.style.overflow = "";
    }
  };
}

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
            labelText = '3PMS Rated';
            break;
          case 'filterEVRated':
            tooltipKey = 'EV Rated Filter';
            labelText = 'EV Rated';
            break;
          case 'filterStudded':
            tooltipKey = 'Studded Available Filter';
            labelText = 'Studded Available';
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
      const filter3pmsLabel = document.querySelector('.switch-label:has(input#filter3pms) .switch-text');
      if (filter3pmsLabel) {
        const newContent = createFilterTooltip('3PMS Rated', '3PMS Filter');
        filter3pmsLabel.innerHTML = '';
        filter3pmsLabel.appendChild(newContent);
      } else {
        const input3pms = document.getElementById('filter3pms');
        if (input3pms) {
          const switchText = input3pms.parentElement.querySelector('.switch-text');
          if (switchText) {
            const newContent = createFilterTooltip('3PMS Rated', '3PMS Filter');
            switchText.innerHTML = '';
            switchText.appendChild(newContent);
          }
        }
      }
      
      const filterEVLabel = document.querySelector('.switch-label:has(input#filterEVRated) .switch-text');
      if (filterEVLabel) {
        const newContent = createFilterTooltip('EV Rated', 'EV Rated Filter');
        filterEVLabel.innerHTML = '';
        filterEVLabel.appendChild(newContent);
      } else {
        const inputEV = document.getElementById('filterEVRated');
        if (inputEV) {
          const switchText = inputEV.parentElement.querySelector('.switch-text');
          if (switchText) {
            const newContent = createFilterTooltip('EV Rated', 'EV Rated Filter');
            switchText.innerHTML = '';
            switchText.appendChild(newContent);
          }
        }
      }
      
      const filterStuddedLabel = document.querySelector('.switch-label:has(input#filterStudded) .switch-text');
      if (filterStuddedLabel) {
        const newContent = createFilterTooltip('Studded Available', 'Studded Available Filter');
        filterStuddedLabel.innerHTML = '';
        filterStuddedLabel.appendChild(newContent);
      } else {
        const inputStudded = document.getElementById('filterStudded');
        if (inputStudded) {
          const switchText = inputStudded.parentElement.querySelector('.switch-text');
          if (switchText) {
            const newContent = createFilterTooltip('Studded Available', 'Studded Available Filter');
            switchText.innerHTML = '';
            switchText.appendChild(newContent);
          }
        }
      }
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
    sortDropdown.addEventListener("input", filterAndRender);
  }

  const toggleBtn = getDOMElement("toggleFilters");
  const filterContent = getDOMElement("mobileFilterContent");
  if (toggleBtn && filterContent) {
    toggleBtn.addEventListener("click", () => {
      const isOpen = filterContent.classList.toggle("open");
      toggleBtn.textContent = isOpen ? "Hide Filters" : "Show Filters";
    });
  }

  const trigger = getDOMElement("wheelDrawerTrigger");
  const drawer = getDOMElement("wheelDrawer");
  if (trigger && drawer) {
    trigger.addEventListener("click", () => {
      drawer.style.display = drawer.style.display === "block" ? "none" : "block";
    });
  }

  initializeRatingSystem();
});