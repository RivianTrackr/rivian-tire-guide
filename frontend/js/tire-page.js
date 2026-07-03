/* jshint esversion: 11 */

/**
 * Individual tire page — affiliate click tracking.
 *
 * The page itself is fully server-rendered; this file's only job is to
 * report "View Tire" clicks to the same analytics endpoint the guide uses
 * (action rtg_track_click), so affiliate clicks from tire pages show up in
 * the analytics dashboard alongside guide-card clicks.
 *
 * Uses sendBeacon so the request survives the navigation; analytics is
 * best-effort and must never break the page.
 */
(function () {
  var cfg = window.rtgTirePage;
  if (!cfg || !cfg.ajaxurl || !cfg.nonce || !cfg.tireId) {
    return;
  }

  document.addEventListener('click', function (e) {
    var link = e.target.closest ? e.target.closest('.rtg-tp-cta-primary, .rtg-tp-review-link') : null;
    if (!link) {
      return;
    }

    var linkType = link.classList.contains('rtg-tp-review-link') ? 'review' : 'purchase';

    try {
      var data = new FormData();
      data.append('action', 'rtg_track_click');
      data.append('tire_id', cfg.tireId);
      data.append('link_type', linkType);
      data.append('nonce', cfg.nonce);

      if (navigator.sendBeacon) {
        navigator.sendBeacon(cfg.ajaxurl, data);
      } else if (window.fetch) {
        fetch(cfg.ajaxurl, { method: 'POST', body: data, keepalive: true }).catch(function () {});
      }
    } catch (err) {
      // Never let analytics interfere with the outbound click.
    }
  });
})();
