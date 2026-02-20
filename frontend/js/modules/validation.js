/* jshint esversion: 11 */

/**
 * Security / input validation utilities.
 */

import { safeString } from './helpers.js';

// Security: Input validation patterns
export const VALIDATION_PATTERNS = {
  search: /^[a-zA-Z0-9\s\-\/\.\+\*\(\)]*$/,
  tireId: /^[a-zA-Z0-9\-_]+$/,
  numeric: /^\d+(\.\d+)?$/,
  affiliateUrl: /^https:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}\/[a-zA-Z0-9\-\/_\.%&=?#:+]*$/,
  imageUrl: /^https:\/\/riviantrackr\.com\/.*\.(jpg|jpeg|png|webp)$/i
};

// Security: Numeric bounds
export const NUMERIC_BOUNDS = {
  price: { min: 0, max: 2000 },
  warranty: { min: 0, max: 100000 },
  weight: { min: 0, max: 200 },
  rating: { min: 1, max: 5 },
  page: { min: 1, max: 1000 }
};

export const ALLOWED_SORT_OPTIONS = [
  "efficiencyGrade", "price-asc", "price-desc",
  "warranty-desc", "weight-asc",
  "reviewed", "rating-desc",
  "newest", "most-reviewed"
];

// Security: Enhanced input sanitization
export function sanitizeInput(str, pattern = null) {
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
export function safeImageURL(url) {
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
export function safeLinkURL(url) {
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

// Review link validation function
export function safeReviewLinkURL(url) {
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
export function validateNumeric(value, bounds, defaultValue = 0) {
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

// Validate and sanitize a CSV row from the tire data
export function validateAndSanitizeCSVRow(row) {
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
