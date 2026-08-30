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

  // These lists are a COPY of frontend/js/modules/allowed-domains.js — the
  // canonical source — because this file is a classic script and cannot
  // import it. tests/test-domain-sync.mjs fails whenever the two disagree:
  // the drift it prevents once had five retailers rendering on the guide but
  // vanishing on the compare page. Change allowed-domains.js first, then
  // mirror it here.

  var ALLOWED_IMAGE_HOSTNAMES = ['riviantrackr.com', 'cdn.riviantrackr.com'];

  var ALLOWED_LINK_DOMAINS = [
    'riviantrackr.com', 'tirerack.com', 'discounttire.com', 'amazon.com', 'amzn.to',
    'costco.com', 'walmart.com', 'goodyear.com', 'bridgestonetire.com', 'michelinman.com',
    'continental-tires.com', 'pirelli.com', 'sumitomotire.com', 'yokohamatire.com',
    'coopertire.com', 'bfgoodrichtires.com', 'firestone.com', 'generaltire.com',
    'hankooktire.com', 'kumhotire.com', 'nexentire.com', 'toyo.com', 'falkentire.com',
    'nittotire.com', 'autozone.com', 'pepboys.com', 'ntb.com', 'simpletire.com',
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
      if (u.protocol !== 'https:') return '';
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

  // --- Font Awesome Icon Helper ---

  /**
   * Return a Font Awesome <i> tag for the given icon name.
   *
   * @param {string} name  Icon name (e.g. 'heart', 'arrow-left').
   * @param {number} size  Font-size in px (optional).
   * @param {string} cls   Extra CSS class(es) (optional).
   * @return {string} HTML markup.
   */
  function icon(name, size, cls) {
    var faPrefix = 'fa-solid';
    var faName = 'fa-' + name;
    if (name === 'heart-outline') { faPrefix = 'fa-regular'; faName = 'fa-heart'; }
    else if (name === 'arrow-up-right') { faName = 'fa-up-right-from-square'; }
    else if (name === 'trash') { faName = 'fa-trash-can'; }
    else if (name === 'share') { faName = 'fa-share-nodes'; }
    var classStr = faPrefix + ' ' + faName;
    if (cls) classStr += ' ' + cls;
    var style = size ? ' style="font-size:' + size + 'px"' : '';
    return '<i class="' + classStr + '"' + style + ' aria-hidden="true"></i>';
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
