/* =====================================================================
   Rivian Tire Guide — Shared Utilities
   Canonical implementations of URL validation and escaping functions.
   Used by both rivian-tires.js (main guide) and compare.js.
   ===================================================================== */

var RTG_SHARED = (function() {
  'use strict';

  // --- HTML Escaping ---

  function escapeHTML(str) {
    if (typeof str !== 'string') return '';
    return String(str).replace(/[&<>"'\/]/g, function(s) {
      return {
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#39;', '/': '&#x2F;'
      }[s];
    });
  }

  // --- URL Validation ---

  var ALLOWED_IMAGE_HOSTNAMES = ['riviantrackr.com', 'cdn.riviantrackr.com'];

  var ALLOWED_LINK_DOMAINS = [
    'riviantrackr.com', 'tirerack.com', 'discounttire.com', 'amazon.com', 'amzn.to',
    'costco.com', 'walmart.com', 'goodyear.com', 'bridgestonetire.com', 'michelinman.com',
    'continental-tires.com', 'pirelli.com', 'yokohamatire.com', 'coopertire.com',
    'bfgoodrichtires.com', 'firestone.com', 'generaltire.com', 'hankooktire.com',
    'kumhotire.com', 'toyo.com', 'falkentire.com', 'nittotire.com', 'simpletire.com',
    'prioritytire.com', 'evsportline.com', 'tsportline.com',
    'anrdoezrs.net', 'dpbolvw.net', 'jdoqocy.com', 'kqzyfj.com', 'tkqlhce.com',
    'commission-junction.com', 'cj.com', 'linksynergy.com', 'click.linksynergy.com',
    'shareasale.com', 'avantlink.com', 'impact.com', 'partnerize.com'
  ];

  var ALLOWED_REVIEW_DOMAINS = [
    'riviantrackr.com', 'www.riviantrackr.com',
    'youtube.com', 'www.youtube.com', 'youtu.be',
    'tiktok.com', 'www.tiktok.com',
    'instagram.com', 'www.instagram.com'
  ];

  function isDomainAllowed(hostname, domainList) {
    hostname = hostname.toLowerCase();
    return domainList.some(function(domain) {
      return hostname === domain || hostname.endsWith('.' + domain);
    });
  }

  function safeImageURL(url) {
    if (typeof url !== 'string') return '';
    var trimmed = url.trim();
    if (!trimmed) return '';
    try {
      var u = new URL(trimmed);
      if (!/^https?:$/.test(u.protocol)) return '';
      if (!ALLOWED_IMAGE_HOSTNAMES.includes(u.hostname)) return '';
      if (u.pathname.includes('..') || u.pathname.includes('//')) return '';
      return trimmed;
    } catch (e) {
      return '';
    }
  }

  function safeLinkURL(url) {
    if (typeof url !== 'string' || !url.trim()) return '';
    var trimmed = url.trim();
    try {
      var urlObj = new URL(trimmed);
      if (urlObj.protocol !== 'https:') return '';
      if (urlObj.pathname.includes('..')) return '';
      if (!isDomainAllowed(urlObj.hostname, ALLOWED_LINK_DOMAINS)) return '';
      return trimmed;
    } catch (e) {
      return '';
    }
  }

  function safeReviewLinkURL(url) {
    if (typeof url !== 'string' || !url.trim()) return '';
    var trimmed = url.trim();
    try {
      var urlObj = new URL(trimmed);
      if (urlObj.protocol !== 'https:') return '';
      if (urlObj.pathname.includes('..')) return '';
      if (!isDomainAllowed(urlObj.hostname, ALLOWED_REVIEW_DOMAINS)) return '';
      return trimmed;
    } catch (e) {
      return '';
    }
  }

  // --- Inline SVG Icon System ---

  /**
   * Render an inline SVG icon from the icon map provided by rtgData.icons.
   *
   * @param {string} name  Icon name (e.g. 'heart', 'arrow-left').
   * @param {number} size  Width/height in px (default 16).
   * @param {string} cls   Extra CSS class(es) (optional).
   * @return {string} SVG markup, or empty string if icon not found.
   */
  function icon(name, size, cls) {
    size = size || 16;
    var icons = (typeof rtgData !== 'undefined' && rtgData.icons) ? rtgData.icons : {};
    var def = icons[name];
    if (!def) return '';
    var classAttr = 'rtg-icon' + (cls ? ' ' + cls : '');
    return '<svg class="' + classAttr + '" width="' + size + '" height="' + size
      + '" viewBox="' + def.viewBox + '" aria-hidden="true">' + def.paths + '</svg>';
  }

  // --- Public API ---

  return {
    escapeHTML: escapeHTML,
    icon: icon,
    safeImageURL: safeImageURL,
    safeLinkURL: safeLinkURL,
    safeReviewLinkURL: safeReviewLinkURL,
    isDomainAllowed: isDomainAllowed,
    ALLOWED_IMAGE_HOSTNAMES: ALLOWED_IMAGE_HOSTNAMES,
    ALLOWED_LINK_DOMAINS: ALLOWED_LINK_DOMAINS,
    ALLOWED_REVIEW_DOMAINS: ALLOWED_REVIEW_DOMAINS
  };
})();
