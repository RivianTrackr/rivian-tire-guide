/* jshint esversion: 11 */

/**
 * Security / input validation utilities.
 */

import { safeString } from './helpers.js';
import { ALLOWED_LINK_DOMAINS, ALLOWED_REVIEW_DOMAINS, ALLOWED_IMAGE_HOSTNAMES } from './allowed-domains.js';

/**
 * Image extensions the guide will display.
 *
 * Must stay in step with RTG_Tire_Images::KNOWN_EXTENSIONS, the set the
 * importer actually writes into the images folder. When the two disagree the
 * importer saves a file the guide then refuses — and refuses it in the worst
 * way, because a card with no usable image renders with no image area at all,
 * so it reads as "no picture was ever downloaded" rather than "this one is
 * not allowed". An AVIF from a retailer sat on disk unseen for exactly that
 * reason.
 */
export const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

const IMAGE_EXTENSION_GROUP = '(' + IMAGE_EXTENSIONS.join('|') + ')';

// Security: Input validation patterns
export const VALIDATION_PATTERNS = {
  search: /^[a-zA-Z0-9\s\-\/\.\+\*\(\)]*$/,
  tireId: /^[a-zA-Z0-9\-_]+$/,
  numeric: /^\d+(\.\d+)?$/,
  affiliateUrl: /^https:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}\/[a-zA-Z0-9\-\/_\.%&=?#:+]*$/,
  imageUrl: new RegExp('^https://(cdn\\.)?riviantrackr\\.com/.*\\.' + IMAGE_EXTENSION_GROUP + '$', 'i')
};

/** Looks like an image URL at all — used only to decide whether to warn. */
const LOOKS_LIKE_IMAGE = new RegExp('\\.' + IMAGE_EXTENSION_GROUP + '$', 'i');

// Security: Numeric bounds
export const NUMERIC_BOUNDS = {
  price: { min: 0, max: 2000 },
  warranty: { min: 0, max: 100000 },
  weight: { min: 0, max: 200 },
  rating: { min: 1, max: 5 },
  page: { min: 1, max: 1000 }
};

export const ALLOWED_SORT_OPTIONS = [
  "roamer-efficiency",
  "price-asc", "price-desc",
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
    if (LOOKS_LIKE_IMAGE.test(trimmed)) {
      console.warn('Image URL failed validation:', trimmed);
    }
    return "";
  }

  try {
    const urlObj = new URL(trimmed);

    if (urlObj.protocol !== 'https:') return "";
    if (!ALLOWED_IMAGE_HOSTNAMES.includes(urlObj.hostname)) return "";

    if (urlObj.pathname.includes('..') || urlObj.pathname.includes('//')) {
      return "";
    }

    return trimmed;
  } catch (e) {
    if (LOOKS_LIKE_IMAGE.test(trimmed)) {
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

    const hostname = urlObj.hostname.toLowerCase();
    const isAllowed = ALLOWED_LINK_DOMAINS.some(domain => {
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

    const hostname = urlObj.hostname.toLowerCase();
    const isAllowed = ALLOWED_REVIEW_DOMAINS.some(domain => {
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
      // Some columns must NOT have <>"'& stripped:
      //   18 link, 19 image, 22 review_link — URLs where & separates query
      //     params (stripping it corrupts the destination); each is validated
      //     by its own strict allowlist helper (safeLinkURL / safeImageURL /
      //     safeReviewLinkURL) before use, so <>"' can't slip through.
      //   27 vehicle_breakdown — JSON, needs its quotes.
      //   28 slug — identifier, url-encoded at use.
      if (i === 18 || i === 19 || i === 22 || i === 27 || i === 28) {
        sanitized[i] = cell;
      } else {
        sanitized[i] = cell.replace(/[<>\"'&]/g, "").trim();

        if (sanitized[i].length > 500) {
          sanitized[i] = sanitized[i].substring(0, 500);
        }
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
