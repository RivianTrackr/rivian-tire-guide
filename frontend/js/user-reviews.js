/**
 * A member's reviews (frontend/templates/user-reviews.php).
 *
 * ?reviewer=ID names a member. Without it the page is "My reviews": the
 * signed-in visitor's own, so a menu item can link the bare page; a
 * signed-out visitor is sent to log in and lands back here. Cards render
 * the way a review reads on the tire page (stars, month, owner tags,
 * title, words), with the tire on top as a link to its page.
 */
(function () {
  "use strict";

  var cfg = (typeof rtgUserReviews !== "undefined" && rtgUserReviews) || {};
  var currentUserId = parseInt(cfg.currentUserId, 10) || 0;
  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  var listEl = document.getElementById("rtg-user-reviews-list");
  var titleEl = document.getElementById("rtg-user-reviews-title");
  var crumbEl = document.getElementById("rtg-user-reviews-crumb");
  var countEl = document.getElementById("rtg-user-reviews-count");
  var writeEl = document.getElementById("rtg-user-reviews-write");
  var pagerEl = document.getElementById("rtg-user-reviews-pagination");
  if (!listEl) return;

  function icon(name, extra) {
    var i = document.createElement('i');
    i.className = 'fa-solid fa-' + name + (extra ? ' ' + extra : '');
    i.setAttribute('aria-hidden', 'true');
    return i;
  }

  function setHeading(text) {
    if (titleEl) titleEl.textContent = text;
    if (crumbEl) crumbEl.textContent = text;
    document.title = text + ' — Rivian Tire Guide';
  }

  // --- Who --------------------------------------------------------------

  var params = new URLSearchParams(window.location.search);
  var userId = parseInt(params.get("reviewer"), 10) || 0;
  var isOwn = false;

  if (!userId && currentUserId) {
    userId = currentUserId;
    isOwn = true;
    // Put the ID in the address so the page can be bookmarked or shared.
    try {
      var own = new URL(window.location.href);
      own.searchParams.set("reviewer", String(userId));
      window.history.replaceState(window.history.state, "", own.toString());
    } catch (e) { /* leave the address as it is */ }
  } else if (userId && currentUserId && userId === currentUserId) {
    isOwn = true;
  }

  if (isOwn && writeEl) writeEl.hidden = false;

  if (!userId) {
    setHeading("My reviews");
    renderEmpty({
      icon: 'user-lock',
      heading: "Sign in to see your reviews",
      text: "Your reviews are kept with your account. Sign in and they will be right here.",
      ctaHref: cfg.loginUrl || '',
      ctaIcon: 'right-to-bracket',
      ctaLabel: 'Sign in',
      ctaPrimary: true
    });
    return;
  }

  setHeading(isOwn ? "My reviews" : "Reviews");
  loadUserReviews(1);

  // --- Load -------------------------------------------------------------

  function loadUserReviews(page) {
    listEl.setAttribute('aria-busy', 'true');

    var fd = new FormData();
    fd.append("action", "rtg_get_user_reviews");
    fd.append("user_id", userId);
    fd.append("page", page);
    if (cfg.nonce) fd.append("nonce", cfg.nonce);

    fetch(cfg.ajaxurl, { method: "POST", body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        listEl.removeAttribute('aria-busy');
        if (!data || !data.success) {
          renderEmpty({ icon: 'triangle-exclamation', heading: "Could not load reviews", text: "Something went wrong on our side. Reload the page to try again." });
          return;
        }

        var d = data.data;
        setHeading(isOwn ? "My reviews" : "Reviews by " + (d.display_name || 'a member'));

        var total = parseInt(d.total, 10) || 0;
        if (countEl) {
          countEl.textContent = total
            ? (isOwn ? "You have written " : (d.display_name || 'This member') + " has written ")
              + total + " review" + (total !== 1 ? "s" : "") + " on the guide."
            : "";
        }

        if (!d.reviews || !d.reviews.length) {
          if (pagerEl) pagerEl.innerHTML = "";
          renderEmpty(isOwn
            ? { icon: 'pen-to-square', heading: "You haven’t written a review yet", text: "Rate the tires you have driven on and help the next owner choose. Your reviews collect here.", ctaHref: writeEl ? writeEl.href : '', ctaIcon: 'pen', ctaLabel: 'Write a review', ctaPrimary: true }
            : { icon: 'comment-slash', heading: "No reviews yet", text: "This member hasn’t published a review on the guide." });
          return;
        }

        listEl.innerHTML = "";
        d.reviews.forEach(function (review) {
          listEl.appendChild(renderReview(review));
        });

        renderPagination(parseInt(d.page, 10) || 1, parseInt(d.total_pages, 10) || 1);
      })
      .catch(function () {
        listEl.removeAttribute('aria-busy');
        renderEmpty({ icon: 'triangle-exclamation', heading: "Could not load reviews", text: "Check your connection and reload the page." });
      });
  }

  // --- Cards ------------------------------------------------------------

  // The tire's own page by slug; the guide with ?tire= for a tire that
  // has no slug yet.
  function tireUrl(review) {
    if (!review.tire_id) return "";
    try {
      if (review.slug && cfg.tirePageBase) {
        return cfg.tirePageBase + encodeURIComponent(review.slug) + '/';
      }
      if (cfg.tireGuideUrl) {
        var url = new URL(cfg.tireGuideUrl, window.location.href);
        url.searchParams.set("tire", review.tire_id);
        return url.toString();
      }
    } catch (e) { /* fall through */ }
    return "";
  }

  function renderTire(review) {
    var href = tireUrl(review);
    var row = document.createElement(href ? 'a' : 'div');
    row.className = 'ur-review-tire';
    if (href) {
      row.href = href;
      row.setAttribute('aria-label', 'Open ' + ((review.brand || '') + ' ' + (review.model || '')).trim() + ' on the guide');
    }

    var thumb = document.createElement('span');
    thumb.className = 'ur-review-tire-img';
    thumb.setAttribute('aria-hidden', 'true');
    if (review.image) {
      var img = document.createElement('img');
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      // A photo that fails to load gives way to the placeholder ring.
      img.onerror = function () {
        thumb.innerHTML = '';
        thumb.appendChild(icon('circle-notch'));
      };
      img.src = review.image;
      thumb.appendChild(img);
    } else {
      thumb.appendChild(icon('circle-notch'));
    }
    row.appendChild(thumb);

    var text = document.createElement('span');
    text.className = 'ur-review-tire-text';
    if (review.brand) {
      var brand = document.createElement('span');
      brand.className = 'ur-review-tire-brand';
      brand.textContent = review.brand;
      text.appendChild(brand);
    }
    var name = document.createElement('span');
    name.className = 'ur-review-tire-name';
    name.textContent = review.model || review.brand || review.tire_id;
    text.appendChild(name);
    if (review.size) {
      var size = document.createElement('span');
      size.className = 'ur-review-tire-size';
      size.textContent = review.size;
      text.appendChild(size);
    }
    row.appendChild(text);

    if (href) row.appendChild(icon('chevron-right', 'ur-review-tire-go'));
    return row;
  }

  function renderStars(rating) {
    var wrap = document.createElement('span');
    wrap.className = 'ur-review-stars';
    var visual = document.createElement('span');
    visual.setAttribute('aria-hidden', 'true');
    for (var i = 1; i <= 5; i++) {
      var fill = rating >= i ? 'full' : (rating >= i - 0.5 ? 'half' : 'empty');
      var star = document.createElement('span');
      star.className = 'ur-star ur-star-' + fill;
      star.textContent = '★';
      visual.appendChild(star);
    }
    var sr = document.createElement('span');
    sr.className = 'ur-sr-only';
    sr.textContent = 'Rated ' + String(Math.round(rating * 10) / 10) + ' out of 5';
    wrap.appendChild(visual);
    wrap.appendChild(sr);
    return wrap;
  }

  // "Aug 2026" from a MySQL datetime, read as text so the browser's time
  // zone can't shift a review across a month boundary (as the tire page).
  function formatMonth(value) {
    var m = /^(\d{4})-(\d{2})/.exec(String(value || ''));
    if (!m) return '';
    var month = parseInt(m[2], 10);
    if (month < 1 || month > 12) return '';
    return MONTHS[month - 1] + ' ' + m[1];
  }

  // Same tags the tire page shows under a reviewer's name.
  function renderMeta(review) {
    var tags = [];
    if (review.is_owner && parseInt(review.is_owner, 10) === 1) {
      var owner = document.createElement('span');
      owner.className = 'ur-review-tag ur-review-owner';
      owner.appendChild(icon('certificate'));
      owner.appendChild(document.createTextNode('Verified owner'));
      tags.push(owner);
    }
    if (review.vehicle) {
      var veh = document.createElement('span');
      veh.className = 'ur-review-tag';
      veh.textContent = review.vehicle;
      tags.push(veh);
    }
    var miles = parseInt(review.miles, 10) || 0;
    if (miles > 0) {
      var mi = document.createElement('span');
      mi.className = 'ur-review-tag';
      mi.textContent = miles.toLocaleString() + ' mi on this set';
      tags.push(mi);
    }
    if (!tags.length) return null;
    var wrap = document.createElement('div');
    wrap.className = 'ur-review-meta';
    tags.forEach(function (t) { wrap.appendChild(t); });
    return wrap;
  }

  function renderReview(review) {
    var card = document.createElement('article');
    card.className = 'ur-review';

    card.appendChild(renderTire(review));

    var head = document.createElement('div');
    head.className = 'ur-review-head';
    head.appendChild(renderStars(parseFloat(review.rating) || 0));
    var when = formatMonth(review.updated_at || review.created_at);
    if (when) {
      var date = document.createElement('span');
      date.className = 'ur-review-date';
      date.textContent = when;
      head.appendChild(date);
    }
    card.appendChild(head);

    var meta = renderMeta(review);
    if (meta) card.appendChild(meta);

    if (review.review_title) {
      var title = document.createElement('div');
      title.className = 'ur-review-title';
      title.textContent = review.review_title;
      card.appendChild(title);
    }
    if (review.review_text) {
      var body = document.createElement('div');
      body.className = 'ur-review-body';
      body.textContent = review.review_text;
      card.appendChild(body);
    } else if (!review.review_title) {
      var only = document.createElement('p');
      only.className = 'ur-review-rating-only';
      only.textContent = 'Rating only, no written review.';
      card.appendChild(only);
    }

    return card;
  }

  // --- Empty state --------------------------------------------------------

  function renderEmpty(opts) {
    listEl.innerHTML = "";
    var empty = document.createElement('div');
    empty.className = 'ur-empty';
    var ic = document.createElement('div');
    ic.className = 'ur-empty-icon';
    ic.appendChild(icon(opts.icon || 'comment'));
    empty.appendChild(ic);
    var heading = document.createElement('div');
    heading.className = 'ur-empty-heading';
    heading.textContent = opts.heading;
    empty.appendChild(heading);
    if (opts.text) {
      var p = document.createElement('p');
      p.className = 'ur-empty-text';
      p.textContent = opts.text;
      empty.appendChild(p);
    }
    if (opts.ctaHref) {
      var a = document.createElement('a');
      a.className = 'ur-cta ' + (opts.ctaPrimary ? 'ur-cta-primary' : 'ur-cta-secondary');
      a.href = opts.ctaHref;
      if (opts.ctaIcon) a.appendChild(icon(opts.ctaIcon));
      a.appendChild(document.createTextNode(' ' + opts.ctaLabel));
      empty.appendChild(a);
    }
    listEl.appendChild(empty);
  }

  // --- Pagination -------------------------------------------------------

  function renderPagination(currentPage, totalPages) {
    if (!pagerEl) return;
    pagerEl.innerHTML = "";
    if (totalPages <= 1) return;

    function pageButton(label, iconName, target, iconFirst) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ur-cta ur-cta-secondary';
      if (iconFirst) btn.appendChild(icon(iconName));
      btn.appendChild(document.createTextNode((iconFirst ? ' ' : '') + label + (iconFirst ? '' : ' ')));
      if (!iconFirst) btn.appendChild(icon(iconName));
      if (target < 1 || target > totalPages) {
        btn.disabled = true;
      } else {
        btn.addEventListener('click', function () {
          loadUserReviews(target);
          var top = listEl.getBoundingClientRect().top + window.pageYOffset - 80;
          window.scrollTo({ top: top > 0 ? top : 0, behavior: 'smooth' });
        });
      }
      return btn;
    }

    pagerEl.appendChild(pageButton('Previous', 'chevron-left', currentPage - 1, true));
    var info = document.createElement('span');
    info.className = 'ur-pagination-info';
    info.textContent = "Page " + currentPage + " of " + totalPages;
    pagerEl.appendChild(info);
    pagerEl.appendChild(pageButton('Next', 'chevron-right', currentPage + 1, false));
  }
})();
