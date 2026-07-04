/* jshint esversion: 11 */

/**
 * Individual tire page — affiliate click tracking + info tooltips.
 *
 * The page itself is fully server-rendered; this file reports CTA clicks to
 * the same analytics endpoint the guide uses (rtg_track_click) and powers
 * the (i) info modals on spec labels and the efficiency stat.
 *
 * Click tracking uses sendBeacon so the request survives the navigation;
 * analytics is best-effort and must never break the page.
 */

// Info tooltip content — keep in sync with TOOLTIP_DATA in
// frontend/js/modules/tooltips.js (this page can't import the guide bundle,
// so the subset used here is duplicated).
var RTG_TP_TOOLTIPS = {
  'Load Index': {
    title: 'Load Index',
    content: 'Rivian vehicles require tires with a high enough load index to safely carry the vehicle\'s weight. R1 vehicles (R1T, R1S) require a minimum load index of 116, while R2 vehicles require a minimum of 112. Using a lower load index can affect safety, handling, and durability.'
  },
  '3PMS Rated': {
    title: '3PMS Rating',
    content: '3PMS (Three-Peak Mountain Snowflake) symbol indicates the tire meets winter traction requirements and is rated for severe snow service according to industry standards.'
  },
  'UTQG': {
    title: 'UTQG Rating',
    content: 'UTQG (Uniform Tire Quality Grading) provides standardized ratings for treadwear, temperature resistance (A, B, C), and traction performance (AA, A, B, C) to help compare tire quality.'
  },
  'Real-World Efficiency': {
    title: 'Real-World Efficiency (mi/kWh)',
    content: 'This is real-world energy efficiency data collected from Rivian owners via <a href="https://rivianroamer.com/join?with=riviantrackr" target="_blank" rel="noopener noreferrer" style="color:#60a5fa;text-decoration:underline;">Rivian Roamer</a>. It measures how many miles the vehicle travels per kilowatt-hour of battery energy while using these tires. <br><br> Higher values mean better range efficiency. The data is based on actual driving sessions and updates regularly.'
  }
};

(function () {
  var activeOverlay = null;

  function closeTooltip() {
    if (activeOverlay) {
      var overlay = activeOverlay;
      activeOverlay = null;
      overlay.remove();
      document.body.style.overflow = '';
      if (overlay._returnFocus && typeof overlay._returnFocus.focus === 'function') {
        overlay._returnFocus.focus({ preventScroll: true });
      }
    }
  }

  function openTooltip(key, triggerEl) {
    var data = RTG_TP_TOOLTIPS[key];
    if (!data) return;
    closeTooltip();

    var extra = '';
    if (triggerEl && triggerEl.dataset && triggerEl.dataset.tooltipExtra) {
      extra = '<br><br><strong style="color:#60a5fa;">This tire:</strong> ' + triggerEl.dataset.tooltipExtra;
    }

    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(2px);';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', data.title);

    var modal = document.createElement('div');
    modal.style.cssText = 'background:#16191e;border:1px solid #3a3e45;border-radius:12px;padding:20px;max-width:400px;width:100%;color:#ece9e4;box-shadow:0 10px 25px rgba(0,0,0,0.5);';

    var title = document.createElement('h3');
    title.textContent = data.title;
    title.style.cssText = 'margin:0 0 12px;font-size:18px;font-weight:700;color:#fba919;';

    var content = document.createElement('p');
    content.innerHTML = data.content + extra;
    content.style.cssText = 'margin:0 0 16px;line-height:1.5;font-size:14px;color:#ece9e4;';

    var gotIt = document.createElement('button');
    gotIt.type = 'button';
    gotIt.textContent = 'Got it';
    gotIt.style.cssText = 'background:#fba919;color:#15130e;border:none;padding:8px 16px;border-radius:6px;font-weight:600;cursor:pointer;font-size:14px;width:100%;';

    modal.appendChild(title);
    modal.appendChild(content);
    modal.appendChild(gotIt);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';

    overlay._returnFocus = triggerEl;
    activeOverlay = overlay;

    gotIt.addEventListener('click', closeTooltip);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeTooltip();
    });
    gotIt.focus();
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && activeOverlay) {
      e.preventDefault();
      closeTooltip();
    } else if (e.key === 'Tab' && activeOverlay) {
      // Single focusable element — keep focus on the button.
      e.preventDefault();
      var btn = activeOverlay.querySelector('button');
      if (btn) btn.focus();
    }
  });

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest ? e.target.closest('.info-tooltip-trigger') : null;
    if (trigger && trigger.dataset.tooltipKey) {
      e.preventDefault();
      openTooltip(trigger.dataset.tooltipKey, trigger);
    }
  });
})();

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
