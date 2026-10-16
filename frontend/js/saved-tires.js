/**
 * Saved tires (frontend/templates/saved-tires.php).
 *
 * The signed-in member's shortlist. Each row is the tire page's card header
 * (thumbnail, brand, model, size) linked to the tire's page, three tiles
 * (price with how it moved since the save, efficiency, rating), the
 * retailer button and Remove. "Compare all" hands the list to the compare
 * page. A signed-out visitor gets a sign-in prompt that returns here.
 */
(function () {
  "use strict";

  var cfg = (typeof rtgSavedTires !== "undefined" && rtgSavedTires) || {};
  var listEl = document.getElementById("rtg-saved-tires-list");
  var countEl = document.getElementById("rtg-saved-tires-count");
  var actionsEl = document.getElementById("rtg-saved-tires-actions");
  var compareEl = document.getElementById("rtg-saved-tires-compare");
  if (!listEl) return;

  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var tires = [];

  function icon(name, extra) {
    var i = document.createElement('i');
    i.className = 'fa-solid fa-' + name + (extra ? ' ' + extra : '');
    i.setAttribute('aria-hidden', 'true');
    return i;
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }

  function money(n) {
    n = Number(n) || 0;
    return '$' + Math.round(n).toLocaleString('en-US');
  }

  // "Sep 28" or "Sep 28, 2025" from a MySQL datetime, read as text.
  function formatDay(value) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''));
    if (!m) return '';
    var month = parseInt(m[2], 10);
    if (month < 1 || month > 12) return '';
    var thisYear = String(new Date().getFullYear());
    return MONTHS[month - 1] + ' ' + parseInt(m[3], 10) + (m[1] === thisYear ? '' : ', ' + m[1]);
  }

  function tireUrl(tire) {
    if (tire.slug && cfg.tirePageBase) return cfg.tirePageBase + encodeURIComponent(tire.slug) + '/';
    if (cfg.tireGuideUrl) {
      try {
        var url = new URL(cfg.tireGuideUrl, window.location.href);
        url.searchParams.set('tire', tire.tire_id);
        return url.toString();
      } catch (e) { /* fall through */ }
    }
    return '';
  }

  function safeHttp(url) {
    try {
      var u = new URL(String(url || ''), window.location.href);
      return (u.protocol === 'https:' || u.protocol === 'http:') ? u.toString() : '';
    } catch (e) {
      return '';
    }
  }

  // --- Who --------------------------------------------------------------

  if (!cfg.isLoggedIn) {
    renderEmpty({
      icon: 'user-lock',
      heading: 'Sign in to see your saved tires',
      text: 'Saved tires live with your account, so they follow you from your phone to your desk. Sign in and they will be right here.',
      ctaHref: cfg.loginUrl || '',
      ctaIcon: 'right-to-bracket',
      ctaLabel: 'Sign in',
      ctaPrimary: true
    });
    return;
  }

  load();

  // --- Load -------------------------------------------------------------

  function load() {
    listEl.setAttribute('aria-busy', 'true');
    var fd = new FormData();
    fd.append('action', 'rtg_get_saved_tires');
    fd.append('nonce', cfg.nonce || '');
    fetch(cfg.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        listEl.removeAttribute('aria-busy');
        if (!data || !data.success) {
          if (data && data.data && data.data.code === 'login_required') {
            window.location.href = cfg.loginUrl || window.location.href;
            return;
          }
          renderEmpty({ icon: 'triangle-exclamation', heading: 'Could not load your saved tires', text: 'Something went wrong on our side. Reload the page to try again.' });
          return;
        }
        tires = Array.isArray(data.data.tires) ? data.data.tires : [];
        render();
      })
      .catch(function () {
        listEl.removeAttribute('aria-busy');
        renderEmpty({ icon: 'triangle-exclamation', heading: 'Could not load your saved tires', text: 'Check your connection and reload the page.' });
      });
  }

  // --- Render -----------------------------------------------------------

  function render() {
    var n = tires.length;
    if (countEl) {
      var drops = tires.filter(function (t) { return priceMove(t) < 0; }).length;
      countEl.textContent = n
        ? 'You have ' + n + ' tire' + (n === 1 ? '' : 's') + ' saved.'
          + (drops ? ' ' + (drops === 1 ? 'One has' : drops + ' have') + ' dropped in price since you saved ' + (drops === 1 ? 'it.' : 'them.') : '')
        : 'Tires you save from the guide collect here.';
    }
    if (actionsEl) actionsEl.hidden = n < 2;
    if (compareEl && n >= 2) {
      try {
        var url = new URL(cfg.compareUrl || '/tire-compare/', window.location.href);
        url.searchParams.set('compare', tires.slice(0, 4).map(function (t) { return t.tire_id; }).join(','));
        compareEl.href = url.toString();
        var label = compareEl.querySelector('span');
        if (label) label.textContent = n > 4 ? 'Compare first 4' : 'Compare all ' + n;
      } catch (e) { /* leave the link */ }
    }

    if (!n) {
      renderEmpty({
        icon: 'heart',
        heading: 'Nothing saved yet',
        text: 'Tap the heart on any tire card or tire page and it lands here, with its price, efficiency and rating kept up to date. Save a few, then compare them side by side.',
        ctaHref: cfg.tireGuideUrl || '',
        ctaLabel: 'Browse tires',
        ctaPrimary: true
      });
      return;
    }

    listEl.innerHTML = '';
    tires.forEach(function (tire) { listEl.appendChild(renderTire(tire)); });
  }

  // Positive when the price rose since the save, negative when it fell.
  function priceMove(tire) {
    var now = Number(tire.price) || 0;
    var then = Number(tire.price_at_save) || 0;
    if (!now || !then) return 0;
    return Math.round(now - then);
  }

  function renderTire(tire) {
    var card = el('article', 'st-tire');
    card.dataset.tireId = tire.tire_id;
    var href = tireUrl(tire);
    var name = ((tire.brand || '') + ' ' + (tire.model || '')).trim();

    // Thumbnail
    var thumb = el(href ? 'a' : 'span', 'st-tire-img');
    if (href) { thumb.href = href; thumb.setAttribute('aria-label', 'Open ' + name); }
    thumb.setAttribute('aria-hidden', href ? 'false' : 'true');
    var imgUrl = safeHttp(tire.image);
    if (imgUrl) {
      var img = document.createElement('img');
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      img.onerror = function () { thumb.innerHTML = ''; thumb.appendChild(icon('circle-notch')); };
      img.src = imgUrl;
      thumb.appendChild(img);
    } else {
      thumb.appendChild(icon('circle-notch'));
    }
    card.appendChild(thumb);

    // Brand, model, chips
    var text = el('div', 'st-tire-text');
    if (tire.brand) text.appendChild(el('div', 'st-tire-brand', tire.brand));
    var title = el(href ? 'a' : 'div', 'st-tire-name', tire.model || tire.brand || tire.tire_id);
    if (href) title.href = href;
    text.appendChild(title);
    var chips = el('div', 'st-tire-chips');
    if (tire.size) chips.appendChild(el('span', 'st-chip st-chip-size', tire.size));
    if (tire.category) chips.appendChild(el('span', 'st-chip', tire.category));
    if (tire.three_pms) chips.appendChild(el('span', 'st-chip', '3PMS'));
    var when = formatDay(tire.saved_at);
    if (when) chips.appendChild(el('span', 'st-chip st-chip-muted', 'Saved ' + when));
    text.appendChild(chips);
    card.appendChild(text);

    // Stats
    var stats = el('div', 'st-tire-stats');
    var price = Number(tire.price) || 0;
    var move = priceMove(tire);
    var priceNote;
    if (tire.stock && tire.stock.show) {
      priceNote = el('span', 'st-stat-note is-stock', tire.stock.label);
      if (tire.stock.title) priceNote.title = tire.stock.title;
    } else if (move < 0) {
      priceNote = el('span', 'st-stat-note is-drop', '▼ ' + money(-move) + ' since you saved it');
    } else if (move > 0) {
      priceNote = el('span', 'st-stat-note is-rise', '▲ ' + money(move) + ' since you saved it');
    } else {
      priceNote = el('span', 'st-stat-note', price ? money(price * 4) + ' a set' : 'No price listed');
    }
    stats.appendChild(stat('Price', price ? money(price) : '—', price ? '' : 'is-muted', priceNote));

    var eff = Number(tire.roamer_efficiency) || 0;
    stats.appendChild(stat('Efficiency', eff ? eff.toFixed(2) : '—', eff ? 'is-eff' : 'is-muted', el('span', 'st-stat-note', eff ? 'mi/kWh' : 'No data yet')));

    var avg = Number(tire.rating_average) || 0;
    var cnt = Number(tire.rating_count) || 0;
    stats.appendChild(stat('Rating', cnt ? avg.toFixed(1) : '—', cnt ? '' : 'is-muted', el('span', 'st-stat-note', cnt ? cnt + ' rating' + (cnt === 1 ? '' : 's') : 'No ratings yet')));
    card.appendChild(stats);

    // Actions
    var actions = el('div', 'st-tire-actions');
    var link = safeHttp(tire.link);
    var alt = tire.stock && tire.stock.show ? safeHttp(tire.stock.alt_link) : '';
    if (alt && tire.stock.alt_retailer) {
      var altBtn = el('a', 'st-cta st-cta-secondary st-cta-alt st-cta-small');
      altBtn.href = alt; altBtn.target = '_blank'; altBtn.rel = 'nofollow sponsored noopener';
      altBtn.appendChild(icon('check'));
      altBtn.appendChild(document.createTextNode(' In stock at ' + tire.stock.alt_retailer));
      actions.appendChild(altBtn);
    } else if (link) {
      var buy = el('a', 'st-cta st-cta-primary st-cta-small');
      buy.href = link; buy.target = '_blank'; buy.rel = 'nofollow sponsored noopener';
      buy.appendChild(document.createTextNode(tire.retailer ? 'View at ' + tire.retailer + ' ' : 'View tire '));
      buy.appendChild(icon('up-right-from-square'));
      actions.appendChild(buy);
    }
    var remove = el('button', 'st-remove');
    remove.type = 'button';
    remove.setAttribute('aria-label', 'Remove ' + name + ' from saved tires');
    remove.appendChild(icon('heart'));
    remove.appendChild(document.createTextNode(' Remove'));
    remove.addEventListener('click', function () { removeTire(tire, card, remove); });
    actions.appendChild(remove);
    card.appendChild(actions);

    return card;
  }

  function stat(label, value, valueClass, note) {
    var wrap = el('div', 'st-stat');
    wrap.appendChild(el('span', 'st-stat-label', label));
    wrap.appendChild(el('span', 'st-stat-value' + (valueClass ? ' ' + valueClass : ''), value));
    if (note) wrap.appendChild(note);
    return wrap;
  }

  // --- Remove -----------------------------------------------------------

  function removeTire(tire, card, btn) {
    btn.disabled = true;
    var fd = new FormData();
    fd.append('action', 'rtg_toggle_saved_tire');
    fd.append('nonce', cfg.nonce || '');
    fd.append('tire_id', tire.tire_id);
    fd.append('saved', '0');
    fetch(cfg.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) { btn.disabled = false; return; }
        tires = tires.filter(function (t) { return t.tire_id !== tire.tire_id; });
        card.classList.add('is-leaving');
        setTimeout(function () { render(); }, 200);
      })
      .catch(function () { btn.disabled = false; });
  }

  // --- Empty state --------------------------------------------------------

  function renderEmpty(opts) {
    listEl.innerHTML = '';
    if (actionsEl) actionsEl.hidden = true;
    var empty = el('div', 'st-empty');
    var ic = el('div', 'st-empty-icon');
    ic.appendChild(icon(opts.icon || 'heart'));
    empty.appendChild(ic);
    empty.appendChild(el('div', 'st-empty-heading', opts.heading));
    if (opts.text) empty.appendChild(el('p', 'st-empty-text', opts.text));
    if (opts.ctaHref) {
      var a = el('a', 'st-cta ' + (opts.ctaPrimary ? 'st-cta-primary' : 'st-cta-secondary'));
      a.href = opts.ctaHref;
      if (opts.ctaIcon) a.appendChild(icon(opts.ctaIcon));
      a.appendChild(document.createTextNode((opts.ctaIcon ? ' ' : '') + opts.ctaLabel));
      empty.appendChild(a);
    }
    listEl.appendChild(empty);
  }
})();
