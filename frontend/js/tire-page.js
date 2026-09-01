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

/**
 * Compare, save, share, and "Show more reviews".
 *
 * The page a search visitor lands on used to have three outbound CTAs and
 * nothing that kept them: no way to shortlist the tire, compare it, or read
 * past the first ten reviews. Everything here degrades to what the server
 * rendered — the compare link is a plain link, the guest "Save" is a login
 * link, and the reviews button simply isn't there when there are no more.
 */
(function () {
  var cfg = window.rtgTirePage || {};

  // --- Favorite -----------------------------------------------------------
  var favBtn = document.getElementById('rtgTpFav');
  if (favBtn && favBtn.tagName === 'BUTTON' && cfg.ajaxurl && cfg.ratingNonce && cfg.tireId) {
    var busy = false;

    function paintFav(isFav) {
      favBtn.classList.toggle('is-favorite', isFav);
      favBtn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
      var icon = favBtn.querySelector('i');
      var label = favBtn.querySelector('span');
      if (icon) icon.className = (isFav ? 'fa-solid' : 'fa-regular') + ' fa-heart';
      if (label) label.textContent = isFav ? 'Saved' : 'Save';
    }

    favBtn.addEventListener('click', function () {
      if (busy) return;
      if (!cfg.isLoggedIn) {
        if (cfg.loginUrl) window.location.href = cfg.loginUrl;
        return;
      }
      var wasFav = favBtn.classList.contains('is-favorite');
      busy = true;
      paintFav(!wasFav); // optimistic

      var data = new FormData();
      data.append('action', wasFav ? 'rtg_remove_favorite' : 'rtg_add_favorite');
      data.append('tire_id', cfg.tireId);
      data.append('nonce', cfg.ratingNonce);

      fetch(cfg.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json || !json.success) paintFav(wasFav);
        })
        .catch(function () { paintFav(wasFav); })
        .then(function () { busy = false; });
    });
  }

  // --- Share ----------------------------------------------------------------
  var shareBtn = document.getElementById('rtgTpShare');
  if (shareBtn) {
    shareBtn.addEventListener('click', function () {
      var url = cfg.shareUrl || window.location.href;
      var title = cfg.shareTitle || document.title;

      function showCopied() {
        var icon = shareBtn.querySelector('i');
        var label = shareBtn.querySelector('span');
        shareBtn.classList.add('copied');
        if (icon) icon.className = 'fa-solid fa-check';
        if (label) label.textContent = 'Copied!';
        setTimeout(function () {
          shareBtn.classList.remove('copied');
          if (icon) icon.className = 'fa-solid fa-share-nodes';
          if (label) label.textContent = 'Share';
        }, 2000);
      }

      if (navigator.share) {
        navigator.share({ title: title, url: url }).catch(function () {});
      } else if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(showCopied).catch(function () {});
      }
    });
  }

  // --- Show more reviews ----------------------------------------------------
  var moreBtn = document.getElementById('rtgTpMoreReviews');
  var list = document.getElementById('rtgTpReviewList');
  if (moreBtn && list && cfg.ajaxurl && cfg.tireId) {
    var perPage = parseInt(cfg.reviewsPerPage, 10) || 10;

    // Same markup rtg_tire_page_stars() renders server-side.
    function renderStars(rating) {
      var wrap = document.createElement('span');
      wrap.className = 'rtg-tp-review-stars';
      var visual = document.createElement('span');
      visual.setAttribute('aria-hidden', 'true');
      for (var i = 1; i <= 5; i++) {
        var fill = rating >= i ? 'full' : (rating >= i - 0.5 ? 'half' : 'empty');
        var star = document.createElement('span');
        star.className = 'rtg-tp-star rtg-tp-star-' + fill;
        star.textContent = '★';
        visual.appendChild(star);
      }
      var sr = document.createElement('span');
      sr.className = 'rtg-tp-sr-only';
      sr.textContent = 'Rated ' + String(Math.round(rating * 10) / 10) + ' out of 5';
      wrap.appendChild(visual);
      wrap.appendChild(sr);
      return wrap;
    }

    function renderReview(review) {
      var item = document.createElement('div');
      item.className = 'rtg-tp-review';

      var head = document.createElement('div');
      head.className = 'rtg-tp-review-head';
      var author = document.createElement('span');
      author.className = 'rtg-tp-review-author';
      author.textContent = review.display_name || 'Guest';
      head.appendChild(author);
      head.appendChild(renderStars(parseFloat(review.rating) || 0));
      item.appendChild(head);

      if (review.review_title) {
        var title = document.createElement('div');
        title.className = 'rtg-tp-review-title';
        title.textContent = review.review_title;
        item.appendChild(title);
      }
      if (review.review_text) {
        var body = document.createElement('div');
        body.className = 'rtg-tp-review-body';
        body.textContent = review.review_text;
        item.appendChild(body);
      }
      return item;
    }

    function updateButton(loaded, total) {
      var remaining = total - loaded;
      if (remaining <= 0) {
        moreBtn.parentNode.removeChild(moreBtn);
        return;
      }
      moreBtn.textContent = 'Show more reviews (' + remaining + ' more)';
      moreBtn.disabled = false;
    }

    moreBtn.addEventListener('click', function () {
      if (moreBtn.disabled) return;
      var page = (parseInt(moreBtn.dataset.page, 10) || 1) + 1;
      var loaded = parseInt(moreBtn.dataset.loaded, 10) || 0;
      var total = parseInt(moreBtn.dataset.total, 10) || 0;

      moreBtn.disabled = true;
      moreBtn.textContent = 'Loading…';

      var data = new FormData();
      data.append('action', 'get_tire_reviews');
      data.append('tire_id', cfg.tireId);
      data.append('page', String(page));
      if (cfg.ratingNonce) data.append('nonce', cfg.ratingNonce);

      fetch(cfg.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json || !json.success || !json.data || !Array.isArray(json.data.reviews)) {
            throw new Error('bad response');
          }
          var frag = document.createDocumentFragment();
          json.data.reviews.forEach(function (review) {
            frag.appendChild(renderReview(review));
          });
          var first = frag.firstChild;
          list.appendChild(frag);
          if (first && typeof first.focus === 'function') {
            first.setAttribute('tabindex', '-1');
            first.focus({ preventScroll: true });
          }

          loaded += json.data.reviews.length;
          total = parseInt(json.data.total, 10) || total;
          moreBtn.dataset.page = String(page);
          moreBtn.dataset.loaded = String(loaded);
          moreBtn.dataset.total = String(total);
          // The server said there are no more: stop even if the count is off.
          if (json.data.reviews.length < perPage) loaded = total;
          updateButton(loaded, total);
        })
        .catch(function () {
          moreBtn.disabled = false;
          moreBtn.textContent = 'Couldn’t load more reviews. Try again';
        });
    });
  }
})();
