/* jshint esversion: 11 */

/**
 * Canonical URL allowlists — the single source of truth.
 *
 * Two files enforce these lists at runtime: this module (bundled into the
 * main guide via validation.js) and rtg-shared.js (a classic script the
 * compare, tire-review, and user-reviews pages load directly, which cannot
 * import modules and therefore carries a copy). The copies had drifted in
 * BOTH directions — five retailers rendered on the guide but vanished on the
 * compare page, and two rendered on compare but not the guide.
 *
 * tests/test-domain-sync.mjs holds rtg-shared.js to these exact lists, the
 * same way IMAGE_EXTENSIONS is held to RTG_Tire_Images::KNOWN_EXTENSIONS —
 * so a domain can't be half-allowed again. Add or remove a domain HERE, then
 * mirror it in rtg-shared.js; the test fails until both agree.
 */

export const ALLOWED_LINK_DOMAINS = [
  'riviantrackr.com', 'tirerack.com', 'discounttire.com', 'amazon.com', 'amzn.to',
  'costco.com', 'walmart.com', 'goodyear.com', 'bridgestonetire.com', 'michelinman.com',
  'continental-tires.com', 'pirelli.com', 'sumitomotire.com', 'yokohamatire.com',
  'coopertire.com', 'bfgoodrichtires.com', 'firestone.com', 'generaltire.com',
  'hankooktire.com', 'kumhotire.com', 'nexentire.com', 'toyo.com', 'falkentire.com',
  'nittotire.com', 'autozone.com', 'pepboys.com', 'ntb.com', 'simpletire.com',
  'prioritytire.com', 'evsportline.com', 'tsportline.com',
  'anrdoezrs.net', 'dpbolvw.net', 'jdoqocy.com', 'kqzyfj.com', 'tkqlhce.com',
  'commission-junction.com', 'cj.com', 'linksynergy.com', 'click.linksynergy.com',
  'shareasale.com', 'avantlink.com', 'impact.com', 'partnerize.com',
];

export const ALLOWED_REVIEW_DOMAINS = [
  'riviantrackr.com', 'www.riviantrackr.com',
  'youtube.com', 'www.youtube.com', 'youtu.be',
  'tiktok.com', 'www.tiktok.com',
  'instagram.com', 'www.instagram.com',
];

export const ALLOWED_IMAGE_HOSTNAMES = ['riviantrackr.com', 'cdn.riviantrackr.com'];
