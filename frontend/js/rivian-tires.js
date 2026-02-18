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
let userReviews = {};
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
  'Officially Reviewed Filter': {
    title: 'Officially Reviewed Filter',
    content: 'Filters for tires that have an official review from RivianTrackr — either a written article or video review.'
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
  "warranty-desc", "weight-asc",
  "reviewed", "rating-desc",
  "newest", "most-reviewed"
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

// SVG star path and reusable markup
const STAR_PATH = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';
function starSVGMarkup(size = 22) {
  return `<svg viewBox="0 0 24 24" width="${size}" height="${size}" aria-hidden="true">` +
    `<path class="star-bg" d="${STAR_PATH}" fill="none" stroke="currentColor" stroke-width="1.5"/>` +
    `<path class="star-fill" d="${STAR_PATH}" fill="currentColor"/>` +
    `<path class="star-half" d="${STAR_PATH}" fill="currentColor" style="clip-path:inset(0 50% 0 0)"/>` +
    `</svg>`;
}

// Enhanced performance optimizations
let domCache = {};
let isRendering = false;
let pendingRender = false;
let lastFilterState = null;
let initialRenderDone = false;
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

// Review link validation function
function safeReviewLinkURL(url) {
  if (typeof url !== "string" || !url.trim()) return "";

  const trimmed = url.trim();

  const reviewLinkPattern = /^https:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}\/[a-zA-Z0-9\-\/_\.%&=?#:+@]*$/;

  if (!reviewLinkPattern.test(trimmed)) {
    return "";
  }

  try {
    const urlObj = new URL(trimmed);

    if (urlObj.protocol !== 'https:') return "";

    const allowedReviewDomains = [
      'riviantrackr.com', 'www.riviantrackr.com',
      'youtube.com', 'www.youtube.com', 'youtu.be',
      'tiktok.com', 'www.tiktok.com',
      'instagram.com', 'www.instagram.com'
    ];

    const hostname = urlObj.hostname.toLowerCase();
    const isAllowed = allowedReviewDomains.some(domain => {
      return hostname === domain || hostname.endsWith('.' + domain);
    });

    if (!isAllowed) {
      console.warn('Review link domain not in allowlist:', hostname);
      return "";
    }

    if (urlObj.pathname.includes('..')) {
      return "";
    }

    return trimmed;
  } catch (e) {
    console.warn('Invalid review link URL:', trimmed);
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
  labelText.className = 'tire-card-spec-label';
  labelText.textContent = label;
  
  const infoButton = document.createElement('button');
  infoButton.innerHTML = '<i class="fa-solid fa-circle-info"></i>';
  infoButton.className = 'info-tooltip-trigger';
  infoButton.dataset.tooltipKey = tooltipKey;
  infoButton.style.cssText = `
    background: none;
    border: none;
    color: var(--rtg-text-muted);
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
    color: var(--rtg-text-muted);
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
    color: var(--rtg-text-muted);
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
          if (data.data.user_reviews) {
            userReviews = { ...userReviews, ...data.data.user_reviews };
          }
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

function submitTireRating(tireId, rating, reviewTitle = '', reviewText = '') {
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
      alert('Please log in or sign up to review tires');
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

  if (reviewTitle) {
    formData.append('review_title', reviewTitle.substring(0, 200));
  }
  if (reviewText) {
    formData.append('review_text', reviewText.substring(0, 5000));
  }

  return fetch(tireRatingAjax.ajaxurl, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      tireRatings[tireId] = {
        average: validateNumeric(data.data.average_rating, { min: 0, max: 5 }),
        count: validateNumeric(data.data.rating_count, { min: 0, max: 10000 }),
        review_count: validateNumeric(data.data.review_count, { min: 0, max: 10000 })
      };
      userRatings[tireId] = validateNumeric(data.data.user_rating, NUMERIC_BOUNDS.rating);
      if (reviewText) {
        userReviews[tireId] = { rating: validRating, review_title: reviewTitle, review_text: reviewText };
      }

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
  starsContainer.className = `rating-stars ${isInteractive ? 'interactive' : ''} ${validUserRating > 0 ? 'has-user-rating' : ''}`;
  starsContainer.dataset.tireId = tireId;
  starsContainer.setAttribute('role', isInteractive ? 'radiogroup' : 'img');
  starsContainer.setAttribute('aria-label', displayAverage > 0 ? `Rating: ${displayAverage.toFixed(1)} out of 5 stars` : 'No ratings yet');

  // Round to nearest 0.5 for half-star display
  const roundedAvg = displayAverage > 0 ? Math.round(displayAverage * 2) / 2 : 0;

  for (let i = 1; i <= 5; i++) {
    const star = document.createElement('span');
    star.className = 'star';
    star.dataset.rating = i.toString();
    star.dataset.tireId = tireId;
    star.innerHTML = starSVGMarkup(26);

    // Determine fill level based on rounded average
    if (roundedAvg >= i) {
      star.classList.add('active');
    } else if (roundedAvg >= i - 0.5) {
      star.classList.add('active', 'half');
    }

    if (validUserRating > 0 && i <= validUserRating) {
      star.classList.add('user-rated');
    }
    if (isInteractive) {
      star.style.cursor = 'pointer';
      star.setAttribute('role', 'radio');
      star.setAttribute('aria-checked', validUserRating === i ? 'true' : 'false');
      star.setAttribute('aria-label', `${i} star${i !== 1 ? 's' : ''}`);
      star.setAttribute('tabindex', i === (validUserRating || 1) ? '0' : '-1');
    } else {
      star.setAttribute('aria-hidden', 'true');
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

  // Review actions row
  const reviewActions = document.createElement('div');
  reviewActions.className = 'review-actions';

  const reviewCount = tireRatings[tireId]?.review_count || 0;

  if (reviewCount > 0) {
    const viewReviewsBtn = document.createElement('button');
    viewReviewsBtn.className = 'review-action-link view-reviews-btn';
    viewReviewsBtn.dataset.tireId = tireId;
    const reviewIcon = document.createElement('i');
    reviewIcon.className = 'fa-solid fa-message';
    reviewIcon.setAttribute('aria-hidden', 'true');
    viewReviewsBtn.appendChild(reviewIcon);
    viewReviewsBtn.appendChild(document.createTextNode(` ${reviewCount} review${reviewCount !== 1 ? 's' : ''}`));
    reviewActions.appendChild(viewReviewsBtn);
  }

  if (isLoggedIn) {
    const hasReview = userReviews[tireId]?.rating;
    const writeBtn = document.createElement('button');
    writeBtn.className = 'review-action-link write-review-btn';
    writeBtn.dataset.tireId = tireId;
    writeBtn.textContent = hasReview ? 'Edit Review' : 'Write a Review';
    reviewActions.appendChild(writeBtn);
  } else {
    const loginPrompt = document.createElement('a');
    loginPrompt.className = 'login-prompt';
    loginPrompt.href = typeof tireRatingAjax !== 'undefined' ? tireRatingAjax.login_url : '/wp-login.php';

    const promptIcon = document.createElement('i');
    promptIcon.className = 'fa-solid fa-pen-to-square';
    promptIcon.setAttribute('aria-hidden', 'true');
    loginPrompt.appendChild(promptIcon);
    loginPrompt.appendChild(document.createTextNode(' Log in to review this tire'));
    reviewActions.appendChild(loginPrompt);
  }

  container.appendChild(reviewActions);

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

// ── Review Modal ──

function openReviewModal(tireId, preselectedRating = 0) {
  // Remove existing modal if any
  const existing = document.getElementById('rtg-review-modal');
  if (existing) existing.remove();

  const existingReview = userReviews[tireId] || {};
  const currentRating = preselectedRating || existingReview.rating || 0;
  const currentTitle = existingReview.review_title || '';
  const currentText = existingReview.review_text || '';

  // Find tire info from the card
  const card = document.querySelector(`[data-tire-id="${CSS.escape(tireId)}"].tire-card`);
  const brand = card ? card.querySelector('.tire-card-brand')?.textContent || '' : '';
  const model = card ? card.querySelector('.tire-card-model')?.textContent || '' : '';

  const overlay = document.createElement('div');
  overlay.id = 'rtg-review-modal';
  overlay.className = 'rtg-review-modal-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-label', 'Write a review');

  const modal = document.createElement('div');
  modal.className = 'rtg-review-modal';

  // Header
  const header = document.createElement('div');
  header.className = 'rtg-review-modal-header';

  const titleEl = document.createElement('h3');
  titleEl.textContent = brand && model ? `Review ${brand} ${model}` : 'Write a Review';

  const closeBtn = document.createElement('button');
  closeBtn.className = 'rtg-review-modal-close';
  closeBtn.setAttribute('aria-label', 'Close');
  closeBtn.innerHTML = '&times;';

  header.appendChild(titleEl);
  header.appendChild(closeBtn);

  // Star rating selector
  const starSection = document.createElement('div');
  starSection.className = 'rtg-review-modal-stars';

  const starLabel = document.createElement('label');
  starLabel.textContent = 'Your Rating';

  const starsRow = document.createElement('div');
  starsRow.className = 'rtg-review-stars-select';
  starsRow.setAttribute('role', 'radiogroup');
  starsRow.setAttribute('aria-label', 'Select rating');

  let selectedRating = currentRating;

  for (let i = 1; i <= 5; i++) {
    const star = document.createElement('span');
    star.className = 'rtg-review-star' + (i <= selectedRating ? ' selected' : '');
    star.dataset.value = i;
    star.innerHTML = starSVGMarkup(40);
    star.setAttribute('role', 'radio');
    star.setAttribute('aria-checked', i === selectedRating ? 'true' : 'false');
    star.setAttribute('aria-label', `${i} star${i !== 1 ? 's' : ''}`);
    star.setAttribute('tabindex', i === (selectedRating || 1) ? '0' : '-1');
    starsRow.appendChild(star);
  }

  const ratingText = document.createElement('span');
  ratingText.className = 'rtg-review-rating-text';
  const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
  ratingText.textContent = selectedRating > 0 ? ratingLabels[selectedRating] : 'Select a rating';

  starSection.appendChild(starLabel);
  starSection.appendChild(starsRow);
  starSection.appendChild(ratingText);

  // Star interactions within modal
  starsRow.addEventListener('click', (e) => {
    const star = e.target.closest('.rtg-review-star');
    if (!star) return;
    selectedRating = parseInt(star.dataset.value);
    starsRow.querySelectorAll('.rtg-review-star').forEach((s, idx) => {
      s.classList.toggle('selected', idx < selectedRating);
      s.setAttribute('aria-checked', idx + 1 === selectedRating ? 'true' : 'false');
    });
    ratingText.textContent = ratingLabels[selectedRating] || '';
  });

  starsRow.addEventListener('mouseenter', (e) => {
    const star = e.target.closest('.rtg-review-star');
    if (!star) return;
    const val = parseInt(star.dataset.value);
    starsRow.querySelectorAll('.rtg-review-star').forEach((s, idx) => {
      s.classList.toggle('hovered', idx < val);
    });
  }, true);

  starsRow.addEventListener('mouseleave', () => {
    starsRow.querySelectorAll('.rtg-review-star').forEach(s => s.classList.remove('hovered'));
  }, true);

  // Title input
  const titleSection = document.createElement('div');
  titleSection.className = 'rtg-review-field';

  const titleLabel = document.createElement('label');
  titleLabel.textContent = 'Review Title (optional)';
  titleLabel.setAttribute('for', 'rtg-review-title');

  const titleInput = document.createElement('input');
  titleInput.type = 'text';
  titleInput.id = 'rtg-review-title';
  titleInput.className = 'rtg-review-input';
  titleInput.placeholder = 'Sum up your experience...';
  titleInput.maxLength = 200;
  titleInput.value = currentTitle;

  titleSection.appendChild(titleLabel);
  titleSection.appendChild(titleInput);

  // Text area
  const textSection = document.createElement('div');
  textSection.className = 'rtg-review-field';

  const textLabel = document.createElement('label');
  textLabel.textContent = 'Your Review (optional)';
  textLabel.setAttribute('for', 'rtg-review-text');

  const textArea = document.createElement('textarea');
  textArea.id = 'rtg-review-text';
  textArea.className = 'rtg-review-textarea';
  textArea.placeholder = 'Share your experience with this tire\u2026 How does it handle, ride comfort, noise level, tread wear?';
  textArea.maxLength = 5000;
  textArea.rows = 5;
  textArea.value = currentText;

  const charCount = document.createElement('div');
  charCount.className = 'rtg-review-char-count';
  charCount.textContent = `${currentText.length}/5000`;

  textArea.addEventListener('input', () => {
    charCount.textContent = `${textArea.value.length}/5000`;
  });

  textSection.appendChild(textLabel);
  textSection.appendChild(textArea);
  textSection.appendChild(charCount);

  // Footer
  const footer = document.createElement('div');
  footer.className = 'rtg-review-modal-footer';

  const cancelBtn = document.createElement('button');
  cancelBtn.className = 'rtg-review-btn-cancel';
  cancelBtn.textContent = 'Cancel';

  const submitBtn = document.createElement('button');
  submitBtn.className = 'rtg-review-btn-submit';
  submitBtn.textContent = currentText ? 'Update Review' : 'Submit Review';

  const errorMsg = document.createElement('div');
  errorMsg.className = 'rtg-review-error';

  footer.appendChild(errorMsg);
  footer.appendChild(cancelBtn);
  footer.appendChild(submitBtn);

  // Assemble modal
  modal.appendChild(header);
  modal.appendChild(starSection);
  modal.appendChild(titleSection);
  modal.appendChild(textSection);
  modal.appendChild(footer);
  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  // Focus trap & animation
  requestAnimationFrame(() => overlay.classList.add('active'));
  titleInput.focus();

  // Close handlers
  function closeModal() {
    overlay.classList.remove('active');
    setTimeout(() => overlay.remove(), 200);
  }

  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', function escHandler(e) {
    if (e.key === 'Escape') {
      closeModal();
      document.removeEventListener('keydown', escHandler);
    }
  });

  // Submit handler
  submitBtn.addEventListener('click', () => {
    if (selectedRating < 1 || selectedRating > 5) {
      errorMsg.textContent = 'Please select a star rating.';
      return;
    }

    errorMsg.textContent = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    const hasReviewContent = textArea.value.trim().length > 0 || titleInput.value.trim().length > 0;
    const isUpdate = !!currentText || !!currentTitle;

    submitTireRating(tireId, selectedRating, titleInput.value.trim(), textArea.value.trim())
      .then((result) => {
        closeModal();
        if (hasReviewContent && result && result.review_status === 'pending') {
          showToast('Thanks! Your review has been submitted and is pending approval.', 'info');
        } else if (isUpdate) {
          showToast('Your review has been updated.', 'success');
        } else {
          showToast('Your rating has been saved!', 'success');
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.textContent = currentText ? 'Update Review' : 'Submit Review';
        errorMsg.textContent = typeof err === 'string' ? err : 'Failed to submit. Please try again.';
      });
  });
}

// ── Reviews Drawer ──

function openReviewsDrawer(tireId) {
  // Remove existing drawer
  const existing = document.getElementById('rtg-reviews-drawer');
  if (existing) existing.remove();

  const card = document.querySelector(`[data-tire-id="${CSS.escape(tireId)}"].tire-card`);
  const brand = card ? card.querySelector('.tire-card-brand')?.textContent || '' : '';
  const model = card ? card.querySelector('.tire-card-model')?.textContent || '' : '';

  const overlay = document.createElement('div');
  overlay.id = 'rtg-reviews-drawer';
  overlay.className = 'rtg-reviews-drawer-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-label', 'Reviews');

  const drawer = document.createElement('div');
  drawer.className = 'rtg-reviews-drawer';

  // Header
  const header = document.createElement('div');
  header.className = 'rtg-reviews-drawer-header';

  const titleEl = document.createElement('h3');
  titleEl.textContent = brand && model ? `Reviews for ${brand} ${model}` : 'Reviews';

  const ratingData = tireRatings[tireId] || { average: 0, count: 0 };
  const summaryEl = document.createElement('div');
  summaryEl.className = 'rtg-reviews-summary';
  if (ratingData.average > 0) {
    summaryEl.innerHTML = `<span class="rtg-reviews-avg">${ratingData.average.toFixed(1)}</span> <span class="rtg-reviews-stars-mini">${renderStarsHTML(ratingData.average)}</span> <span class="rtg-reviews-total">${ratingData.count} rating${ratingData.count !== 1 ? 's' : ''}</span>`;
  }

  const closeBtn = document.createElement('button');
  closeBtn.className = 'rtg-reviews-drawer-close';
  closeBtn.setAttribute('aria-label', 'Close');
  closeBtn.innerHTML = '&times;';

  header.appendChild(titleEl);
  header.appendChild(summaryEl);
  header.appendChild(closeBtn);

  // Content
  const content = document.createElement('div');
  content.className = 'rtg-reviews-content';
  content.innerHTML = '<div class="rtg-reviews-loading">Loading reviews...</div>';

  drawer.appendChild(header);
  drawer.appendChild(content);
  overlay.appendChild(drawer);
  document.body.appendChild(overlay);

  requestAnimationFrame(() => overlay.classList.add('active'));

  // Close handlers
  function closeDrawer() {
    overlay.classList.remove('active');
    setTimeout(() => overlay.remove(), 200);
  }

  closeBtn.addEventListener('click', closeDrawer);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeDrawer();
  });
  document.addEventListener('keydown', function escHandler(e) {
    if (e.key === 'Escape') {
      closeDrawer();
      document.removeEventListener('keydown', escHandler);
    }
  });

  // Fetch reviews
  loadReviews(tireId, content, 1);
}

function loadReviews(tireId, container, page) {
  const formData = new FormData();
  formData.append('action', 'get_tire_reviews');
  formData.append('tire_id', tireId);
  formData.append('page', page.toString());

  fetch(tireRatingAjax.ajaxurl, {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success || !data.data.reviews.length) {
      const emptyDiv = document.createElement('div');
      emptyDiv.className = 'rtg-reviews-empty';

      const iconEl = document.createElement('div');
      iconEl.className = 'rtg-reviews-empty-icon';
      iconEl.textContent = '\u270D\uFE0F';

      const headingEl = document.createElement('div');
      headingEl.className = 'rtg-reviews-empty-heading';
      headingEl.textContent = 'No reviews yet';

      const subEl = document.createElement('div');
      subEl.textContent = 'Be the first to share your experience with this tire!';

      emptyDiv.appendChild(iconEl);
      emptyDiv.appendChild(headingEl);
      emptyDiv.appendChild(subEl);

      if (isLoggedIn) {
        const ctaBtn = document.createElement('button');
        ctaBtn.className = 'rtg-reviews-empty-cta';
        ctaBtn.textContent = 'Write a Review';
        ctaBtn.addEventListener('click', () => {
          // Close drawer, open review modal
          const overlay = container.closest('.rtg-reviews-drawer-overlay');
          if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => overlay.remove(), 200);
          }
          openReviewModal(tireId, userRatings[tireId] || 0);
        });
        emptyDiv.appendChild(ctaBtn);
      }

      container.innerHTML = '';
      container.appendChild(emptyDiv);
      return;
    }

    container.innerHTML = '';

    data.data.reviews.forEach(review => {
      container.appendChild(createReviewCard(review));
    });

    // Pagination
    if (data.data.total_pages > 1) {
      const pag = document.createElement('div');
      pag.className = 'rtg-reviews-pagination';

      if (page > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = 'rtg-reviews-page-btn';
        prevBtn.textContent = 'Previous';
        prevBtn.addEventListener('click', () => loadReviews(tireId, container, page - 1));
        pag.appendChild(prevBtn);
      }

      const pageInfo = document.createElement('span');
      pageInfo.className = 'rtg-reviews-page-info';
      pageInfo.textContent = `Page ${page} of ${data.data.total_pages}`;
      pag.appendChild(pageInfo);

      if (page < data.data.total_pages) {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'rtg-reviews-page-btn';
        nextBtn.textContent = 'Next';
        nextBtn.addEventListener('click', () => loadReviews(tireId, container, page + 1));
        pag.appendChild(nextBtn);
      }

      container.appendChild(pag);
    }
  })
  .catch(() => {
    container.innerHTML = '<div class="rtg-reviews-empty">Failed to load reviews. Please try again.</div>';
  });
}

function createReviewCard(review) {
  const card = document.createElement('div');
  card.className = 'rtg-review-card';

  const header = document.createElement('div');
  header.className = 'rtg-review-card-header';

  const userReviewsUrl = (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.userReviewsUrl) ? rtgData.settings.userReviewsUrl : '';
  let authorEl;
  if (review.user_id && userReviewsUrl) {
    authorEl = document.createElement('a');
    authorEl.href = userReviewsUrl + '?reviewer=' + encodeURIComponent(review.user_id);
    authorEl.className = 'rtg-review-author rtg-review-author-link';
    authorEl.textContent = review.display_name || 'Anonymous';
  } else {
    authorEl = document.createElement('span');
    authorEl.className = 'rtg-review-author';
    authorEl.textContent = review.display_name || 'Anonymous';
  }

  const starsEl = document.createElement('span');
  starsEl.className = 'rtg-review-card-stars';
  starsEl.innerHTML = renderStarsHTML(review.rating);

  const dateEl = document.createElement('span');
  dateEl.className = 'rtg-review-date';
  dateEl.textContent = formatReviewDate(review.updated_at || review.created_at);

  header.appendChild(authorEl);
  header.appendChild(starsEl);
  header.appendChild(dateEl);

  card.appendChild(header);

  if (review.review_title) {
    const titleEl = document.createElement('div');
    titleEl.className = 'rtg-review-card-title';
    titleEl.textContent = review.review_title;
    card.appendChild(titleEl);
  }

  if (review.review_text) {
    const bodyEl = document.createElement('div');
    bodyEl.className = 'rtg-review-card-body';
    bodyEl.textContent = review.review_text;
    card.appendChild(bodyEl);
  } else {
    const ratingOnly = document.createElement('div');
    ratingOnly.className = 'rtg-review-card-body rtg-review-rating-only';
    ratingOnly.textContent = 'Rating only \u2014 no written review yet.';
    card.appendChild(ratingOnly);
  }

  return card;
}

function renderStarsHTML(rating) {
  const rounded = Math.round(rating * 2) / 2;
  let html = '';
  for (let i = 1; i <= 5; i++) {
    let cls = 'rtg-mini-star';
    if (rounded >= i) cls += ' filled';
    else if (rounded >= i - 0.5) cls += ' half-filled';
    html += `<span class="${cls}">${starSVGMarkup(18)}</span>`;
  }
  return html;
}

function formatReviewDate(dateStr) {
  // MySQL stores CURRENT_TIMESTAMP in UTC. Append 'Z' so JS parses correctly.
  const normalized = dateStr && !dateStr.includes('T') && !dateStr.includes('Z')
    ? dateStr.replace(' ', 'T') + 'Z'
    : dateStr;
  const date = new Date(normalized);

  // Use the WordPress timezone (IANA string e.g. "America/New_York")
  // to determine calendar day boundaries for "Today"/"Yesterday".
  const wpTz = (typeof tireRatingAjax !== 'undefined' && tireRatingAjax.timezone) || undefined;
  const opts = wpTz ? { timeZone: wpTz } : {};

  // Get the calendar date in the WP timezone for both "now" and the review.
  const nowParts = new Intl.DateTimeFormat('en-CA', { ...opts, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  const dateParts = new Intl.DateTimeFormat('en-CA', { ...opts, year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
  const nowDay = new Date(nowParts + 'T00:00:00');
  const reviewDay = new Date(dateParts + 'T00:00:00');
  const diffDays = Math.round((nowDay - reviewDay) / (1000 * 60 * 60 * 24));

  if (diffDays <= 0) return 'Today';
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 30) return `${diffDays} days ago`;
  if (diffDays < 365) {
    const months = Math.floor(diffDays / 30);
    return `${months} month${months !== 1 ? 's' : ''} ago`;
  }
  return date.toLocaleDateString('en-US', { ...opts, month: 'short', day: 'numeric', year: 'numeric' });
}

// ── Toast Notifications ──

function showToast(message, type = 'success', duration = 4000) {
  let container = document.querySelector('.rtg-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'rtg-toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `rtg-toast rtg-toast-${type}`;

  const icon = document.createElement('span');
  icon.className = 'rtg-toast-icon';
  icon.textContent = type === 'success' ? '\u2714' : '\u2139';

  const text = document.createElement('span');
  text.textContent = message;

  toast.appendChild(icon);
  toast.appendChild(text);
  container.appendChild(toast);

  requestAnimationFrame(() => {
    requestAnimationFrame(() => toast.classList.add('visible'));
  });

  setTimeout(() => {
    toast.classList.remove('visible');
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

function setupEventDelegation() {
  if (eventDelegationSetup) return;
  
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
        const existingRating = userRatings[tireId] || 0;
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
  setChecked("filterReviewed", params.get("reviewed"));

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

function applyTireDeepLink() {
  const params = new URLSearchParams(window.location.search);
  const tireParam = params.get("tire");
  if (!tireParam || !VALIDATION_PATTERNS.tireId.test(tireParam)) return;

  // Find the tire row in the full dataset.
  const tireRow = allRows.find(row => row[0] === tireParam);
  if (!tireRow) return;

  // Override filteredRows to show only this tire.
  filteredRows = [tireRow];
  currentPage = 1;

  // Hide filters, sort bar, active filters, and pagination.
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

  // Add a "View all tires" bar above the card.
  const tireSection = getDOMElement("tireSection");
  if (tireSection) {
    const backBar = document.createElement("div");
    backBar.className = "tire-deeplink-bar";

    const backBtn = document.createElement("a");
    backBtn.href = window.location.pathname;
    backBtn.className = "tire-deeplink-back";
    backBtn.innerHTML = '<i class="fa-solid fa-arrow-left"></i> View all tires';

    backBar.appendChild(backBtn);
    tireSection.parentNode.insertBefore(backBar, tireSection);
  }
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
    efficiencyScore, efficiencyGrade, bundleLink, reviewLink
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
  const safeReviewLink = safeReviewLinkURL(reviewLink);

  const card = document.createElement("div");
  card.className = "tire-card";
  card.dataset.tireId = tireId;

  if (safeString(tags).toLowerCase().includes("reviewed")) {
    const badge = document.createElement('div');
    badge.className = 'tire-card-badge';

    const badgeInner = document.createElement('div');
    badgeInner.className = 'tire-card-badge-inner';

    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-circle-check';

    badgeInner.appendChild(icon);
    badgeInner.appendChild(document.createTextNode('Reviewed'));
    badge.appendChild(badgeInner);
    card.appendChild(badge);
  }

  // Compare checkbox overlay
  const compareOverlay = document.createElement('label');
  compareOverlay.className = 'tire-card-compare-overlay';
  compareOverlay.setAttribute('aria-label', `Compare ${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`);

  const compareCheckbox = document.createElement('input');
  compareCheckbox.type = 'checkbox';
  compareCheckbox.className = 'compare-checkbox';
  compareCheckbox.dataset.id = tireId;
  compareCheckbox.dataset.index = allRows.indexOf(row).toString();

  const compareIcon = document.createElement('span');
  compareIcon.className = 'compare-overlay-icon';

  compareOverlay.appendChild(compareCheckbox);
  compareOverlay.appendChild(compareIcon);

  // Share button overlay
  const shareBtn = document.createElement('button');
  shareBtn.className = 'tire-card-share-btn';
  shareBtn.setAttribute('aria-label', `Share ${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`);
  shareBtn.innerHTML = '<i class="fa-solid fa-share-nodes"></i>';
  shareBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const url = new URL(window.location.href);
    url.search = '';
    url.searchParams.set('tire', tireId);
    const shareUrl = url.toString();
    const shareTitle = `${safeString(brand)} ${safeString(model)}`;

    function showCopied() {
      shareBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
      shareBtn.classList.add('copied');
      setTimeout(() => {
        shareBtn.innerHTML = '<i class="fa-solid fa-share-nodes"></i>';
        shareBtn.classList.remove('copied');
      }, 2000);
    }

    if (navigator.share) {
      navigator.share({ title: shareTitle, url: shareUrl }).catch(() => {});
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(shareUrl).then(showCopied);
    }
  });

  if (safeImage) {
    const imageContainer = document.createElement('div');
    imageContainer.className = 'tire-card-image';

    const img = document.createElement('img');
    img.src = safeImage;
    img.alt = `${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`;
    img.loading = 'lazy';
    img.fetchpriority = 'low';
    img.onclick = () => openImageModal(safeImage, `${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`);

    imageContainer.appendChild(img);
    imageContainer.appendChild(compareOverlay);
    imageContainer.appendChild(shareBtn);
    card.appendChild(imageContainer);
  } else {
    card.appendChild(compareOverlay);
    card.appendChild(shareBtn);
  }

  const bodyEl = document.createElement('div');
  bodyEl.className = 'tire-card-body';

  const brandEl = document.createElement('div');
  brandEl.className = 'tire-card-brand';
  brandEl.textContent = safeString(brand);
  bodyEl.appendChild(brandEl);

  const modelEl = document.createElement('div');
  modelEl.className = 'tire-card-model';
  modelEl.textContent = safeString(model);
  bodyEl.appendChild(modelEl);

  const ratingDiv = document.createElement('div');
  ratingDiv.innerHTML = ratingHTML;
  bodyEl.appendChild(ratingDiv);

// Tags (efficiency grade and others)
  const tagsContainer = document.createElement('div');
  tagsContainer.className = 'tire-card-tags';

  // Efficiency grade badge — matches compare page pattern
  if (efficiencyGrade) {
    const grade = safeString(efficiencyGrade).trim().toUpperCase();
    if (['A', 'B', 'C', 'D', 'F'].includes(grade)) {
      const gradeColor = {
        A: "#5ec095", B: "#a3e635", C: "#facc15",
        D: "#f97316", F: "#b91c1c"
      }[grade];

      const gradeTag = document.createElement('span');
      gradeTag.className = 'tire-card-eff';

      const gradeLabel = document.createElement('span');
      gradeLabel.className = 'tire-card-eff-grade';
      gradeLabel.style.backgroundColor = gradeColor;
      gradeLabel.textContent = grade;

      const scoreSection = document.createElement('span');
      scoreSection.className = 'tire-card-eff-score';

      const scoreText = document.createElement('span');
      scoreText.textContent = `Efficiency (${escapeHTML(safeString(efficiencyScore))}/100)`;

      const infoButton = document.createElement('button');
      infoButton.innerHTML = '<i class="fa-solid fa-circle-info"></i>';
      infoButton.className = 'info-tooltip-trigger';
      infoButton.dataset.tooltipKey = 'Efficiency Score';
      infoButton.style.cssText = `
        background: none;
        border: none;
        color: var(--rtg-text-muted);
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

      infoButton.addEventListener('mouseenter', () => {
        infoButton.style.color = rtgColor('accent');
        infoButton.style.backgroundColor = `color-mix(in srgb, ${rtgColor('accent')} 10%, transparent)`;
      });

      infoButton.addEventListener('mouseleave', () => {
        infoButton.style.color = rtgColor('text-muted');
        infoButton.style.backgroundColor = 'transparent';
      });

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
      tagEl.className = 'tire-card-tag';
      tagEl.textContent = safeString(tag, 30);
      tagsContainer.appendChild(tagEl);
    });
  }

  if (tagsContainer.children.length > 0) {
    bodyEl.appendChild(tagsContainer);
  }

  const specsContainer = document.createElement('div');
  specsContainer.className = 'tire-card-specs';

  const specs = [
    ['Size', `${safeString(size)} (${safeString(diameter)}${safeString(diameter) && !safeString(diameter).includes('"') ? '"' : ''})`],
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
    ['UTQG', safeString(utqg) || 'None']
  ];

  specs.forEach(([label, value]) => {
    const specRow = document.createElement('div');
    specRow.className = 'tire-card-spec';

    const hasTooltip = TOOLTIP_DATA.hasOwnProperty(label);

    let labelEl;
    if (hasTooltip) {
      labelEl = createInfoTooltip(label, label);
    } else {
      labelEl = document.createElement('span');
      labelEl.className = 'tire-card-spec-label';
      labelEl.textContent = label;
    }

    const valueEl = document.createElement('span');
    valueEl.className = 'tire-card-spec-value';
    valueEl.textContent = value || '-';

    specRow.appendChild(labelEl);
    specRow.appendChild(valueEl);
    specsContainer.appendChild(specRow);
  });

  bodyEl.appendChild(specsContainer);
  card.appendChild(bodyEl);

  const actionsContainer = document.createElement('div');
  actionsContainer.className = 'tire-card-actions';

  if (safeLink) {
    const viewButton = document.createElement('a');
    viewButton.href = safeLink;
    viewButton.target = '_blank';
    viewButton.rel = 'noopener noreferrer';
    viewButton.className = 'tire-card-cta tire-card-cta-primary';
    viewButton.innerHTML = 'View Tire&nbsp;<i class="fa-solid fa-square-up-right"></i>';
    actionsContainer.appendChild(viewButton);
  } else {
    const comingSoon = document.createElement('span');
    comingSoon.className = 'tire-card-cta tire-card-cta-disabled';
    comingSoon.textContent = 'Coming Soon';
    actionsContainer.appendChild(comingSoon);
  }

  if (safeBundleLink) {
    const bundleButton = document.createElement('a');
    bundleButton.href = safeBundleLink;
    bundleButton.target = '_blank';
    bundleButton.rel = 'noopener noreferrer';
    bundleButton.className = 'tire-card-cta tire-card-cta-bundle';
    bundleButton.innerHTML = 'Wheel & Tire from EV Sportline&nbsp;<i class="fa-solid fa-square-up-right"></i>';
    actionsContainer.appendChild(bundleButton);
  }

  if (safeReviewLink) {
    const reviewButton = document.createElement('a');
    reviewButton.href = safeReviewLink;
    reviewButton.target = '_blank';
    reviewButton.rel = 'noopener noreferrer';
    reviewButton.className = 'tire-card-cta tire-card-cta-review';
    const isVideo = safeReviewLink.includes('youtube.com') || safeReviewLink.includes('youtu.be') || safeReviewLink.includes('tiktok.com');
    const icon = isVideo ? 'fa-circle-play' : 'fa-newspaper';
    const label = isVideo ? 'Watch Official Review' : 'Read Official Review';
    reviewButton.innerHTML = `${label}&nbsp;<i class="fa-solid ${icon}"></i>`;
    actionsContainer.appendChild(reviewButton);
  }

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
    params.set("pg", currentPage);
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
    if (filters["Reviewed"] && !safeString(row[23])) return false;

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
  const filterReviewed = getDOMElement("filterReviewed");
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

  if (sortOption === "rating-desc" || sortOption === "most-reviewed") {
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
      
    case "most-reviewed":
      filteredRows.sort((a, b) => {
        const aCount = validateNumeric(tireRatings[a[0]]?.count, { min: 0, max: 10000 }, 0);
        const bCount = validateNumeric(tireRatings[b[0]]?.count, { min: 0, max: 10000 }, 0);
        const countDiff = bCount - aCount;

        if (Math.abs(countDiff) < 0.01) {
          const aRating = validateNumeric(tireRatings[a[0]]?.average, { min: 0, max: 5 }, 0);
          const bRating = validateNumeric(tireRatings[b[0]]?.average, { min: 0, max: 5 }, 0);
          const ratingDiff = bRating - aRating;
          return Math.abs(ratingDiff) < 0.01 ? safeString(a[0]).localeCompare(safeString(b[0])) : ratingDiff;
        }
        return countDiff;
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
    case "newest":
      filteredRows.sort((a, b) => safeString(b[24]).localeCompare(safeString(a[24])));
      break;
  }
}

function finishFilterAndRender() {
  const tireCountEl = getDOMElement("tireCount");
  if (tireCountEl) {
    tireCountEl.textContent = `Showing ${filteredRows.length} tire${filteredRows.length === 1 ? "" : "s"}`;
  }
  if (initialRenderDone) {
    currentPage = 1;
  } else {
    // On initial load, keep the page from the URL but clamp to valid range.
    const totalPages = Math.max(1, Math.ceil(filteredRows.length / ROWS_PER_PAGE));
    if (currentPage > totalPages) {
      currentPage = totalPages;
    }
    initialRenderDone = true;
  }
  throttledRender();
  updateURLFromFilters();
  renderActiveFilterChips();
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
  
  const checkboxes = ["filter3pms", "filterEVRated", "filterStudded", "filterReviewed"];
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
  if (isServerSide()) {
    serverSideFilterAndRender();
  } else {
    filterAndRender();
  }
  history.replaceState(null, "", location.pathname);
}

/* === Active Filter Chips === */
function renderActiveFilterChips() {
  const container = getDOMElement("activeFilters");
  if (!container) return;

  const chips = [];

  const searchVal = getDOMElement("searchInput")?.value?.trim();
  if (searchVal) {
    chips.push({ label: "Search", value: searchVal, clear: () => { const el = getDOMElement("searchInput"); if (el) el.value = ""; delete domCache["searchInput"]; } });
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
    chips.push({ label: "Price", value: "≤ $" + priceVal, clear: () => { priceEl.value = 600; const lbl = getDOMElement("priceVal"); if (lbl) lbl.textContent = "$600"; updateSliderBackground(priceEl); } });
  }

  const warrantyEl = getDOMElement("warrantyMax");
  const warrantyVal = warrantyEl ? parseInt(warrantyEl.value) : 80000;
  if (warrantyVal < 80000) {
    chips.push({ label: "Warranty", value: "≤ " + Number(warrantyVal).toLocaleString() + " mi", clear: () => { warrantyEl.value = 80000; const lbl = getDOMElement("warrantyVal"); if (lbl) lbl.textContent = "80,000 miles"; updateSliderBackground(warrantyEl); } });
  }

  const weightEl = getDOMElement("weightMax");
  const weightVal = weightEl ? parseInt(weightEl.value) : 70;
  if (weightVal < 70) {
    chips.push({ label: "Weight", value: "≤ " + weightVal + " lb", clear: () => { weightEl.value = 70; const lbl = getDOMElement("weightVal"); if (lbl) lbl.textContent = "70 lb"; updateSliderBackground(weightEl); } });
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
    dismiss.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    dismiss.addEventListener("click", () => {
      chip.clear();
      lastFilterState = null;
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
  const ssMode = isServerSide();
  const filterFn = ssMode ? serverSideFilterAndRender : filterAndRender;
  const debouncedFilterFn = ssMode ? debounce(serverSideFilterAndRender, 500) : debounce(filterAndRender, 500);

  if (!ssMode) {
    VALID_SIZES = [...new Set(allRows.map(r => safeString(r[1]).trim()))].filter(Boolean);
    VALID_BRANDS = [...new Set(allRows.map(r => safeString(r[3]).trim()))].filter(Boolean);
    VALID_CATEGORIES = [...new Set(allRows.map(r => safeString(r[5]).trim()))].filter(Boolean);

    populateSizeDropdownGrouped("filterSize", allRows);
    populateDropdown("filterBrand", allRows.map(r => r[3]));
    populateDropdown("filterCategory", allRows.map(r => r[5]));
  }

  const inputsToWatch = [
    { id: "searchInput", listener: debouncedFilterFn },
    { id: "filterSize", listener: filterFn },
    { id: "filterBrand", listener: filterFn },
    { id: "filterCategory", listener: filterFn },
    { id: "filter3pms", listener: filterFn },
    { id: "filterEVRated", listener: filterFn },
    { id: "filterStudded", listener: filterFn },
    { id: "filterReviewed", listener: filterFn },
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
  setupEventDelegation();
  initializeSmartSearch();


  if (ssMode) {
    // In server-side mode, override slider debounce to use server fetch.
    const sliderIds = ["priceMax", "warrantyMax", "weightMax"];
    sliderIds.forEach(id => {
      const input = getDOMElement(id);
      if (input) input.addEventListener("input", debounce(serverSideFilterAndRender, 500));
    });

    // Populate dropdowns from server then fetch first page.
    fetchDropdownOptions().then(() => {
      fetchTiresFromServer(currentPage);
    });
  } else {
    buildFilterIndexes();
    filterAndRender();
    applyTireDeepLink();

    const countDisplay = getDOMElement("tireCount");
    if (countDisplay) {
      countDisplay.textContent = `Showing ${filteredRows.length} tire${filteredRows.length !== 1 ? "s" : ""}`;
    }
  }
}

// --- Server-side pagination mode ---
let serverSideMode = false;
let serverSideTotal = 0;
let serverSideFetchController = null;

function isServerSide() {
  return serverSideMode && typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.ajaxurl;
}

function fetchTiresFromServer(page) {
  if (serverSideFetchController) serverSideFetchController.abort();
  serverSideFetchController = new AbortController();

  const searchInput = document.querySelector('#searchInput');
  const body = new FormData();
  body.append('action', 'rtg_get_tires');
  body.append('nonce', rtgData.settings.tireNonce);
  body.append('page', page || currentPage);
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
    signal: serverSideFetchController.signal,
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

    filteredRows = rows;
    serverSideTotal = json.data.total || 0;
    currentPage = json.data.page || 1;

    if (tireCountEl) {
      tireCountEl.textContent = `Showing ${serverSideTotal} tire${serverSideTotal === 1 ? '' : 's'}`;
    }

    renderCards(filteredRows);
    renderServerPagination(serverSideTotal, json.data.per_page || ROWS_PER_PAGE, currentPage);
    updateURLFromFilters();

    const noResults = getDOMElement("noResults");
    const tireCards = getDOMElement("tireCards");
    if (filteredRows.length === 0) {
      if (noResults) noResults.style.display = "block";
      if (tireCards) tireCards.style.display = "none";
    } else {
      if (noResults) noResults.style.display = "none";
      if (tireCards) tireCards.style.display = "grid";
    }

    // Load ratings for visible tires.
    const tireIds = filteredRows.map(row => row[0]).filter(Boolean);
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
  prev.onclick = () => { currentPage = page - 1; fetchTiresFromServer(currentPage); scrollToTop(); };
  container.appendChild(prev);

  const pageInfo = document.createElement("span");
  pageInfo.textContent = `Page ${page} of ${totalPages}`;
  pageInfo.style.cssText = `color: ${rtgColor('text-primary')}; font-weight: 500; display: flex; align-items: center;`;
  container.appendChild(pageInfo);

  const next = document.createElement("button");
  next.textContent = "Next";
  next.disabled = page >= totalPages;
  styleButton(next);
  next.onclick = () => { currentPage = page + 1; fetchTiresFromServer(currentPage); scrollToTop(); };
  container.appendChild(next);
}

function scrollToTop() {
  const filterTop = getDOMElement("filterTop");
  if (filterTop) filterTop.scrollIntoView({ behavior: "smooth" });
}

function fetchDropdownOptions() {
  const body = new FormData();
  body.append('action', 'rtg_get_filter_options');
  body.append('nonce', rtgData.settings.tireNonce);

  return fetch(rtgData.settings.ajaxurl, { method: 'POST', body })
    .then(res => res.json())
    .then(json => {
      if (!json.success) return;
      const d = json.data;

      VALID_SIZES = d.sizes || [];
      VALID_BRANDS = d.brands || [];
      VALID_CATEGORIES = d.categories || [];

      // Populate size dropdown grouped by rim diameter.
      const sizeSelect = getDOMElement("filterSize");
      if (sizeSelect) {
        sizeSelect.innerHTML = '';
        const defaultOpt = document.createElement("option");
        defaultOpt.value = '';
        defaultOpt.textContent = 'All Sizes';
        sizeSelect.appendChild(defaultOpt);

        const groups = {};
        VALID_SIZES.forEach(size => {
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

      populateDropdown("filterBrand", VALID_BRANDS);
      populateDropdown("filterCategory", VALID_CATEGORIES);

      // Re-apply URL params to newly populated dropdowns.
      applyFiltersFromURL();
    })
    .catch(err => console.error('Failed to fetch filter options:', err));
}

function serverSideFilterAndRender() {
  currentPage = 1;
  lastFilterState = null;
  fetchTiresFromServer(1);
  renderActiveFilterChips();
}

// Load tire data from WordPress localized script.
if (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.serverSide) {
  // Server-side pagination mode — no embedded tire data.
  serverSideMode = true;

  if (typeof tireRatingAjax !== 'undefined') {
    isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
  }

  // We still need dropdown values — fetch first page to populate them.
  // Initialize UI with empty rows, then fetch data.
  allRows = [];
  filteredRows = [];
  initializeUI();
} else if (typeof rtgData !== 'undefined' && rtgData.tires && Array.isArray(rtgData.tires)) {
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

  // Close modal on Escape key.
  const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
      modal.style.display = "none";
      document.body.style.overflow = "";
      document.removeEventListener('keydown', closeOnEscape);
    }
  };
  document.addEventListener('keydown', closeOnEscape);

  modal.onclick = (e) => {
    if (e.target === modal) {
      modal.style.display = "none";
      document.body.style.overflow = "";
      document.removeEventListener('keydown', closeOnEscape);
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
    toggleBtn.setAttribute('aria-expanded', 'false');
    toggleBtn.setAttribute('aria-controls', 'mobileFilterContent');
    toggleBtn.addEventListener("click", () => {
      const isOpen = filterContent.classList.toggle("open");
      toggleBtn.textContent = isOpen ? "Hide Filters" : "Show Filters";
      toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  const trigger = getDOMElement("wheelDrawerTrigger");
  const drawer = getDOMElement("wheelDrawer");
  if (trigger && drawer) {
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', 'wheelDrawer');
    trigger.addEventListener("click", () => {
      const isOpen = drawer.style.display !== "block";
      drawer.style.display = isOpen ? "block" : "none";
      trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  initializeRatingSystem();
});