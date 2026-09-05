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
 * Share and "Show more reviews".
 *
 * The page a search visitor lands on used to have three outbound CTAs and
 * nothing that kept them: no way to compare the tire or read past the first
 * ten reviews. Everything here degrades to what the server rendered — the
 * compare link is a plain link, and the reviews button simply isn't there
 * when there are no more.
 */
(function () {
  var cfg = window.rtgTirePage || {};

  // --- Phone buy bar --------------------------------------------------------
  // Pinned to the bottom once the hero's CTA row has scrolled out of view,
  // so the price and the retailer link are one thumb away anywhere on the
  // page. CSS keeps it hidden above phone widths regardless.
  var buyBar = document.getElementById('rtgTpBuyBar');
  var ctaRow = document.querySelector('.rtg-tp-ctas');
  if (buyBar && ctaRow && 'IntersectionObserver' in window) {
    var buyLink = buyBar.querySelector('a');
    var setBar = function (show) {
      buyBar.classList.toggle('is-visible', show);
      buyBar.setAttribute('aria-hidden', show ? 'false' : 'true');
      if (buyLink) buyLink.setAttribute('tabindex', show ? '0' : '-1');
    };
    new IntersectionObserver(function (entries) {
      var entry = entries[0];
      // Show only once the CTA row is above the viewport, not while the
      // visitor is still above it (the breadcrumb, the photo).
      setBar(!entry.isIntersecting && entry.boundingClientRect.top < 0);
    }, { threshold: 0 }).observe(ctaRow);
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

  // --- Reviews: sort, star filter, show more --------------------------------
  // The first page is server-rendered. Changing the sort or the star filter
  // fetches page 1 again and replaces the list; "Show more" appends the next
  // page under whatever sort and filter are active, and its count comes from
  // the filtered total the server returns.
  var moreBtn = document.getElementById('rtgTpMoreReviews');
  var list = document.getElementById('rtgTpReviewList');
  if (moreBtn && list && cfg.ajaxurl && cfg.tireId) {
    var perPage = parseInt(cfg.reviewsPerPage, 10) || 10;
    var moreWrap = moreBtn.parentNode;
    var sortGroup = document.getElementById('rtgTpReviewSort');
    var filterGroup = document.getElementById('rtgTpReviewFilters');
    var caption = document.getElementById('rtgTpReviewCaption');
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var SORT_LABEL = { recent: 'newest first', highest: 'highest rated first', lowest: 'lowest rated first' };

    var state = {
      sort: 'recent',
      rating: 0,
      page: parseInt(moreBtn.dataset.page, 10) || 1,
      loaded: parseInt(moreBtn.dataset.loaded, 10) || 0,
      total: parseInt(moreBtn.dataset.total, 10) || 0,
      allTotal: parseInt(moreBtn.dataset.total, 10) || 0,
      busy: false
    };

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

    // "Aug 2026" from a MySQL datetime, read as text so the browser's time
    // zone can't shift a review across a month boundary.
    function formatMonth(value) {
      var m = /^(\d{4})-(\d{2})/.exec(String(value || ''));
      if (!m) return '';
      var month = parseInt(m[2], 10);
      if (month < 1 || month > 12) return '';
      return MONTHS[month - 1] + ' ' + m[1];
    }

    // Same markup rtg_tire_page_review_meta() renders server-side: owner
    // badge, vehicle, miles on the set. Nothing for older reviews.
    function renderReviewMeta(review) {
      var tags = [];
      if (parseInt(review.is_owner, 10)) {
        var owner = document.createElement('span');
        owner.className = 'rtg-tp-review-tag rtg-tp-review-owner';
        var icon = document.createElement('i');
        icon.className = 'fa-solid fa-certificate';
        icon.setAttribute('aria-hidden', 'true');
        owner.appendChild(icon);
        owner.appendChild(document.createTextNode('Verified owner'));
        tags.push(owner);
      }
      if (review.vehicle) {
        var veh = document.createElement('span');
        veh.className = 'rtg-tp-review-tag';
        veh.textContent = review.vehicle;
        tags.push(veh);
      }
      var miles = parseInt(review.miles, 10) || 0;
      if (miles > 0) {
        var mi = document.createElement('span');
        mi.className = 'rtg-tp-review-tag';
        mi.textContent = miles.toLocaleString() + ' mi on this set';
        tags.push(mi);
      }
      if (!tags.length) return null;
      var wrap = document.createElement('div');
      wrap.className = 'rtg-tp-review-meta';
      tags.forEach(function (t) { wrap.appendChild(t); });
      return wrap;
    }

    function renderReview(review) {
      var item = document.createElement('div');
      item.className = 'rtg-tp-review';

      var head = document.createElement('div');
      head.className = 'rtg-tp-review-head';
      var who = document.createElement('span');
      who.className = 'rtg-tp-review-who';
      var author = document.createElement('span');
      author.className = 'rtg-tp-review-author';
      author.textContent = review.display_name || 'Guest';
      who.appendChild(author);
      var when = formatMonth(review.created_at);
      if (when) {
        var date = document.createElement('span');
        date.className = 'rtg-tp-review-date';
        date.textContent = when;
        who.appendChild(date);
      }
      head.appendChild(who);
      head.appendChild(renderStars(parseFloat(review.rating) || 0));
      item.appendChild(head);

      var meta = renderReviewMeta(review);
      if (meta) item.appendChild(meta);

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

    function updateButton() {
      var remaining = state.total - state.loaded;
      if (remaining <= 0) {
        moreWrap.hidden = true;
        moreBtn.disabled = false;
        return;
      }
      moreWrap.hidden = false;
      moreBtn.textContent = 'Show more reviews (' + remaining + ' more)';
      moreBtn.disabled = false;
      moreBtn.dataset.page = String(state.page);
      moreBtn.dataset.loaded = String(state.loaded);
      moreBtn.dataset.total = String(state.total);
    }

    // One line under the chips saying what the list is showing, with a way
    // back. Hidden while the page is in its server-rendered default.
    function updateCaption() {
      if (!caption) return;
      if (state.sort === 'recent' && state.rating === 0) {
        caption.hidden = true;
        caption.textContent = '';
        return;
      }
      caption.textContent = '';
      var text = document.createElement('span');
      var n = state.total;
      if (state.rating > 0) {
        text.textContent = n + ' review' + (n === 1 ? '' : 's') + ' rated ' + state.rating + ' star' + (state.rating === 1 ? '' : 's') + ', ' + SORT_LABEL[state.sort];
      } else {
        text.textContent = n + ' review' + (n === 1 ? '' : 's') + ', ' + SORT_LABEL[state.sort];
      }
      caption.appendChild(text);
      if (state.rating > 0) {
        var sep = document.createElement('span');
        sep.setAttribute('aria-hidden', 'true');
        sep.textContent = '·';
        caption.appendChild(sep);
        var back = document.createElement('button');
        back.type = 'button';
        back.textContent = 'Show all ' + state.allTotal;
        back.addEventListener('click', function () { setRating(0); });
        caption.appendChild(back);
      }
      caption.hidden = false;
    }

    function fetchPage(page) {
      var data = new FormData();
      data.append('action', 'get_tire_reviews');
      data.append('tire_id', cfg.tireId);
      data.append('page', String(page));
      data.append('sort', state.sort);
      data.append('rating', String(state.rating));
      if (cfg.ratingNonce) data.append('nonce', cfg.ratingNonce);

      return fetch(cfg.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json || !json.success || !json.data || !Array.isArray(json.data.reviews)) {
            throw new Error('bad response');
          }
          return json.data;
        });
    }

    // Sort or filter changed: page 1 again, list replaced in place.
    function reload() {
      if (state.busy) return;
      state.busy = true;
      list.setAttribute('aria-busy', 'true');
      moreBtn.disabled = true;

      fetchPage(1)
        .then(function (d) {
          var frag = document.createDocumentFragment();
          d.reviews.forEach(function (review) { frag.appendChild(renderReview(review)); });
          list.textContent = '';
          list.appendChild(frag);

          state.page = 1;
          state.loaded = d.reviews.length;
          state.total = parseInt(d.total, 10) || 0;
          if (state.rating === 0) state.allTotal = state.total;
          if (d.reviews.length < perPage) state.loaded = state.total;

          // Counts moved since paint (a review approved in between): say so
          // rather than leave an empty list with no explanation.
          if (!d.reviews.length && caption) {
            caption.textContent = '';
            var none = document.createElement('span');
            none.textContent = 'No reviews match that filter.';
            caption.appendChild(none);
            var back = document.createElement('button');
            back.type = 'button';
            back.textContent = 'Show all reviews';
            back.addEventListener('click', function () { setRating(0); });
            caption.appendChild(back);
            caption.hidden = false;
          } else {
            updateCaption();
          }
          updateButton();
        })
        .catch(function () {
          if (caption) {
            caption.textContent = 'Couldn’t load reviews. Try again.';
            caption.hidden = false;
          }
          moreBtn.disabled = false;
        })
        .then(function () {
          state.busy = false;
          list.removeAttribute('aria-busy');
        });
    }

    function setSort(sort) {
      if (!SORT_LABEL[sort] || sort === state.sort) return;
      state.sort = sort;
      if (sortGroup) {
        Array.prototype.forEach.call(sortGroup.querySelectorAll('[data-sort]'), function (btn) {
          var on = btn.dataset.sort === sort;
          btn.classList.toggle('is-active', on);
          btn.setAttribute('aria-checked', on ? 'true' : 'false');
          btn.tabIndex = on ? 0 : -1;
        });
      }
      reload();
    }

    function setRating(rating) {
      rating = parseInt(rating, 10) || 0;
      if (rating < 0 || rating > 5 || rating === state.rating) return;
      state.rating = rating;
      if (filterGroup) {
        Array.prototype.forEach.call(filterGroup.querySelectorAll('[data-rating]'), function (chip) {
          var on = parseInt(chip.dataset.rating, 10) === rating;
          chip.classList.toggle('is-active', on);
          chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
      }
      reload();
    }

    if (sortGroup) {
      sortGroup.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-sort]');
        if (btn) setSort(btn.dataset.sort);
      });
      // Arrow keys move the choice, the way a radio group does.
      sortGroup.addEventListener('keydown', function (e) {
        var keys = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 };
        if (!(e.key in keys)) return;
        var btns = Array.prototype.slice.call(sortGroup.querySelectorAll('[data-sort]'));
        var idx = btns.indexOf(document.activeElement);
        if (idx === -1) return;
        e.preventDefault();
        var next = btns[(idx + keys[e.key] + btns.length) % btns.length];
        next.focus();
        setSort(next.dataset.sort);
      });
    }

    if (filterGroup) {
      filterGroup.addEventListener('click', function (e) {
        var chip = e.target.closest('[data-rating]');
        if (chip && !chip.disabled) setRating(chip.dataset.rating);
      });
    }

    moreBtn.addEventListener('click', function () {
      if (moreBtn.disabled || state.busy) return;
      var page = state.page + 1;

      state.busy = true;
      moreBtn.disabled = true;
      moreBtn.textContent = 'Loading…';

      fetchPage(page)
        .then(function (d) {
          var frag = document.createDocumentFragment();
          d.reviews.forEach(function (review) { frag.appendChild(renderReview(review)); });
          var first = frag.firstChild;
          list.appendChild(frag);
          if (first && typeof first.focus === 'function') {
            first.setAttribute('tabindex', '-1');
            first.focus({ preventScroll: true });
          }

          state.page = page;
          state.loaded += d.reviews.length;
          state.total = parseInt(d.total, 10) || state.total;
          // The server said there are no more: stop even if the count is off.
          if (d.reviews.length < perPage) state.loaded = state.total;
          updateButton();
        })
        .catch(function () {
          moreBtn.disabled = false;
          moreBtn.textContent = 'Couldn’t load more reviews. Try again';
        })
        .then(function () { state.busy = false; });
    });
  }
})();

/**
 * "What owners say": a cached summary of this tire's reviews, written by
 * the advisor from the reviews alone. Fetched after paint so the page never
 * waits on it; the box stays hidden unless a summary comes back. The route
 * answers "pending" while another request is writing it, so we try again.
 */
(function () {
  var cfg = window.rtgTirePage || {};
  var box = document.getElementById('rtgTpOwnersSay');
  if (!box || !cfg.reviewSummaryRest || typeof fetch !== 'function') return;

  var tries = 0;

  function node(tag, className, text) {
    var n = document.createElement(tag);
    if (className) n.className = className;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function list(items, cls, icon) {
    var ul = node('ul', 'rtg-tp-owners-say-list ' + cls);
    items.forEach(function (item) {
      var li = node('li');
      var i = node('i', 'fa-solid ' + icon);
      i.setAttribute('aria-hidden', 'true');
      li.appendChild(i);
      li.appendChild(document.createTextNode(item));
      ul.appendChild(li);
    });
    return ul;
  }

  function render(data) {
    box.textContent = '';
    var label = node('div', 'rtg-tp-owners-say-label');
    var icon = node('i', 'fa-solid fa-comments');
    icon.setAttribute('aria-hidden', 'true');
    label.appendChild(icon);
    label.appendChild(document.createTextNode('What owners say'));
    box.appendChild(label);
    box.appendChild(node('p', 'rtg-tp-owners-say-text', data.summary));
    var pros = Array.isArray(data.pros) ? data.pros : [];
    var cons = Array.isArray(data.cons) ? data.cons : [];
    if (pros.length || cons.length) {
      var lists = node('div', 'rtg-tp-owners-say-lists');
      if (pros.length) lists.appendChild(list(pros, 'is-pro', 'fa-circle-check'));
      if (cons.length) lists.appendChild(list(cons, 'is-con', 'fa-circle-minus'));
      box.appendChild(lists);
    }
    box.appendChild(node('p', 'rtg-tp-owners-say-foot', 'Summarized from ' + data.based_on + ' written review' + (data.based_on === 1 ? '' : 's') + ' by Claude. Read them below to judge for yourself.'));
    box.hidden = false;
  }

  function load() {
    fetch(cfg.reviewSummaryRest, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && data.summary) {
          render(data);
        } else if (data && data.pending && ++tries < 4) {
          setTimeout(load, 3000);
        }
      })
      .catch(function () {});
  }

  load();
})();
