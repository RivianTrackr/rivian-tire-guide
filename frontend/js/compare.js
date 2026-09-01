/* jshint esversion: 11 */

/* =====================================================================
   Rivian Tire Guide — Compare Page
   Uses RTG_SHARED (rtg-shared.js) for URL validation & escaping, and is
   bundled by esbuild so it can share the guide's fitment, pricing and
   vehicle-memory modules rather than copying them.
   ===================================================================== */

import { fitmentShortfalls, describeShortfalls, parseLoadIndex } from './modules/fitment.js';
import { formatSetPrice, priceFreshness, SET_QUANTITY } from './modules/pricing.js';
import { rememberedVehicle } from './modules/vehicle-memory.js';

const MAX_COMPARE = 4;

// --- Utilities (delegates to shared module) ---

function escapeHTML(str) {
  return RTG_SHARED.escapeHTML(str);
}

function rtgIcon(name, size, cls) {
  return RTG_SHARED.icon(name, size, cls);
}

function safeImageURL(url) {
  return RTG_SHARED.safeImageURL(url);
}

function safeLinkURL(url) {
  return RTG_SHARED.safeLinkURL(url);
}

function safeReviewLinkURL(url) {
  return RTG_SHARED.safeReviewLinkURL(url);
}

function settings() {
  return (typeof rtgData !== 'undefined' && rtgData.settings) ? rtgData.settings : {};
}

function getCompareTokens() {
  const params = new URLSearchParams(window.location.search);
  const seen = new Set();
  return (params.get("compare") || "")
    .split(",")
    .map(s => s.trim())
    .filter(s => {
      if (!s || seen.has(s)) return false;
      seen.add(s);
      return true;
    })
    .slice(0, MAX_COMPARE);
}

/**
 * Rewrite the ?compare= list without a reload and re-render. Removing a
 * column and adding a tire both go through here so the URL — the thing
 * people share — always says what's on screen.
 */
function setCompareTokens(tokens) {
  const url = new URL(window.location.href);
  if (tokens.length) {
    url.searchParams.set("compare", tokens.join(","));
  } else {
    url.searchParams.delete("compare");
  }
  history.replaceState(null, "", url.toString());
  renderPage();
}

function fmtPrice(v) {
  const n = parseFloat(v);
  return isNaN(n) ? "-" : "$" + n.toFixed(2);
}

function fmtWeight(v) {
  const n = parseFloat(v);
  return isNaN(n) ? "-" : n + " lb";
}

function fmtWarranty(v) {
  const n = parseInt(v);
  return isNaN(n) || n === 0 ? "-" : Number(n).toLocaleString() + " mi";
}

function fmtLoad(v) {
  const n = parseInt(v);
  return isNaN(n) || n === 0 ? "-" : Number(n).toLocaleString() + " lb";
}

// --- Column index map (matches the localized array order) ---
const COL = {
  tireId: 0, size: 1, diameter: 2, brand: 3, model: 4, category: 5,
  price: 6, warranty: 7, weight: 8, threePms: 9, tread: 10,
  loadIndex: 11, maxLoad: 12, loadRange: 13, speedRating: 14,
  psi: 15, utqg: 16, tags: 17, link: 18, image: 19,
  effScore: 20, effGrade: 21, reviewLink: 22, createdAt: 23,
  roamerEfficiency: 24, roamerTotalKm: 25, roamerVehicleCount: 26, roamerVehicleBreakdown: 27,
  slug: 28, priceSyncedAt: 29, updatedAt: 30
};

/** Canonical tire page URL for a row, or '' when the slug or base is missing. */
function tirePageUrl(tire) {
  const base = settings().tirePageUrl || '';
  const slug = typeof tire[COL.slug] === 'string' ? tire[COL.slug].trim() : '';
  return (base && slug) ? base + encodeURIComponent(slug) + '/' : '';
}

// --- Determine "best" values for highlighting ---
function findBestValues(tires) {
  const best = {};
  const nums = (key) => tires.map(t => parseFloat(t[key])).filter(n => !isNaN(n) && n > 0);

  const prices = nums(COL.price);
  if (prices.length > 1) best.price = Math.min(...prices);

  const weights = nums(COL.weight);
  if (weights.length > 1) best.weight = Math.min(...weights);

  const warranties = tires.map(t => parseInt(t[COL.warranty])).filter(n => !isNaN(n) && n > 0);
  if (warranties.length > 1) best.warranty = Math.max(...warranties);

  const roamerVals = tires.map(t => parseFloat(t[COL.roamerEfficiency])).filter(n => !isNaN(n) && n > 0);
  if (roamerVals.length > 1) best.roamerEfficiency = Math.max(...roamerVals);

  return best;
}

// --- Build tag HTML ---
function renderTags(tagStr) {
  if (!tagStr || tagStr === "-") return "-";
  const tags = tagStr.split(/[,|]/).map(t => t.trim()).filter(Boolean);
  if (!tags.length) return "-";
  return rawHTML(`<div class="cmp-tags">${tags.map(tag => {
    const lower = tag.toLowerCase();
    let cls = "cmp-tag";
    if (lower.includes("ev rated")) cls += " cmp-tag-ev";
    else if (lower.includes("3pms") || lower.includes("3-peak")) cls += " cmp-tag-3pms";
    else if (lower.includes("studded")) cls += " cmp-tag-studded";
    return `<span class="${cls}">${escapeHTML(tag)}</span>`;
  }).join("")}</div>`);
}

// --- Build CTA buttons ---
function renderCTAs(tire) {
  const link = safeLinkURL(tire[COL.link]);
  const review = safeReviewLinkURL(tire[COL.reviewLink]);
  const page = tirePageUrl(tire);
  if (!link && !review && !page) return "-";
  let html = '<div class="cmp-cta-wrap">';
  if (link) {
    html += `<a href="${escapeHTML(link)}" target="_blank" rel="noopener noreferrer" class="cmp-cta cmp-cta-primary">
      View Tire ${rtgIcon('arrow-up-right', 14)}</a>`;
  }
  if (review) {
    let isVideo = false;
    try {
      const reviewUrl = new URL(review, window.location && window.location.origin ? window.location.origin : undefined);
      const host = reviewUrl.hostname.toLowerCase();
      isVideo =
        host === 'youtube.com' ||
        host === 'www.youtube.com' ||
        host.endsWith('.youtube.com') ||
        host === 'youtu.be' ||
        host === 'www.youtu.be' ||
        host === 'tiktok.com' ||
        host === 'www.tiktok.com' ||
        host.endsWith('.tiktok.com');
    } catch (e) {
      isVideo = false;
    }
    const iconName = isVideo ? 'circle-play' : 'newspaper';
    const label = isVideo ? 'Watch Official Review' : 'Read Official Review';
    html += `<a href="${escapeHTML(review)}" target="_blank" rel="noopener noreferrer" class="cmp-cta cmp-cta-review">
      ${label} ${rtgIcon(iconName, 14)}</a>`;
  }
  if (page) {
    html += `<a href="${escapeHTML(page)}" class="cmp-cta cmp-cta-secondary">
      Full Specs &amp; Reviews ${rtgIcon('arrow-right', 14)}</a>`;
  }
  html += '</div>';
  return rawHTML(html);
}

// --- Price with the set figure and freshness ---
function renderPrice(tire) {
  const n = parseFloat(tire[COL.price]);
  if (isNaN(n) || n <= 0) return '-';

  const fresh = priceFreshness(
    { priceSyncedAt: tire[COL.priceSyncedAt], updatedAt: tire[COL.updatedAt] },
    settings().stalePriceDays
  );

  let html = escapeHTML(fmtPrice(n)) + ' <span class="cmp-price-unit">ea</span>';
  html += `<br><span class="cmp-meta">${escapeHTML(formatSetPrice(n))} / set of ${SET_QUANTITY}</span>`;
  if (fresh.label) {
    html += `<br><span class="cmp-meta cmp-price-asof${fresh.stale ? ' is-stale' : ''}">${escapeHTML(fresh.label)}${fresh.stale ? ' · may be outdated' : ''}</span>`;
  }
  return rawHTML(html, n);
}

// --- Load index with the fitment verdict ---
function renderLoadIndex(tire) {
  const raw = tire[COL.loadIndex] || '';
  if (!raw) return '-';

  const s = settings();
  const shortfalls = fitmentShortfalls(
    { loadIndex: raw, size: tire[COL.size] },
    s.vehicleSizeMap || {},
    s.loadIndexFloors || {},
    rememberedVehicle()
  );
  const text = describeShortfalls(raw, shortfalls);
  if (!text) return raw;

  return rawHTML(
    escapeHTML(raw) +
    `<br><span class="cmp-fitment-warn">${rtgIcon('triangle-exclamation', 12)} ${escapeHTML(text)}</span>`,
    parseLoadIndex(raw)
  );
}

// --- Spec section builder ---

// Getters that build markup wrap it in rawHTML(); everything else is escaped
// unconditionally. The old rule — trust any string starting with "<" — let a
// stored spec value ride into innerHTML unescaped. `value` carries the number
// best-value highlighting compares (the markup itself doesn't parseFloat).
function rawHTML(html, value) {
  return { __html: html, __value: value };
}

function specSection(icon, title, rows, tires, best, colCount) {
  let body = '';
  rows.forEach(([label, getter, bestKey]) => {
    const values = tires.map(t => {
      const val = getter(t);
      const isRaw = !!val && typeof val === 'object' && typeof val.__html === 'string';
      const comparable = isRaw ? parseFloat(val.__value) : parseFloat(val);
      const isBest = bestKey && best[bestKey] !== undefined && comparable === best[bestKey];
      return `<div class="cmp-row-value${isBest ? ' is-best' : ''}">${isRaw ? val.__html : escapeHTML(val || "-")}</div>`;
    });
    body += `<div class="cmp-row">
      <div class="cmp-row-label">${escapeHTML(label)}</div>
      <div class="cmp-row-values" style="--cmp-cols:${colCount}">${values.join("")}</div>
    </div>`;
  });

  return `<div class="cmp-section">
    <div class="cmp-section-header" onclick="this.parentElement.classList.toggle('collapsed')">
      ${rtgIcon(icon, 16, 'cmp-section-icon')}
      <span class="cmp-section-title">${escapeHTML(title)}</span>
      ${rtgIcon('chevron-down', 14, 'cmp-section-chevron')}
    </div>
    <div class="cmp-section-body">${body}</div>
  </div>`;
}

// --- "Add another tire" ---

/**
 * A search box over the whole catalog, so a comparison can grow here
 * instead of only from the guide. The guide link carries the current
 * selection, so picking there continues this comparison rather than
 * starting over.
 */
function renderAddPanel(container, rows, tokens, placement = 'top') {
  const panel = document.createElement('div');
  panel.className = 'cmp-add';
  panel.id = 'cmpAddPanel';

  const slotsLeft = MAX_COMPARE - tokens.length;
  const guideUrl = settings().guideUrl || '';
  let guideLink = '';
  if (guideUrl) {
    try {
      const u = new URL(guideUrl, window.location.origin);
      if (tokens.length) u.searchParams.set('compare', tokens.join(','));
      guideLink = u.toString();
    } catch (e) {
      guideLink = guideUrl;
    }
  }

  panel.innerHTML = `
    <div class="cmp-add-head">
      <span class="cmp-add-title">${rtgIcon('plus', 14)} Add another tire</span>
      <span class="cmp-add-hint">${slotsLeft} more ${slotsLeft === 1 ? 'slot' : 'slots'} · up to ${MAX_COMPARE} tires</span>
    </div>
    <div class="cmp-add-row">
      <label for="cmpAddSearch" class="cmp-sr-only">Search tires to add</label>
      <input id="cmpAddSearch" class="cmp-add-input" type="text" autocomplete="off" maxlength="80"
        placeholder="Search by brand, model, or size…" role="combobox" aria-autocomplete="list"
        aria-expanded="false" aria-controls="cmpAddResults" />
      ${guideLink ? `<a class="cmp-btn" href="${escapeHTML(guideLink)}">${rtgIcon('arrow-left', 13)} <span>Pick from the guide</span></a>` : ''}
    </div>
    <ul id="cmpAddResults" class="cmp-add-results" role="listbox" hidden></ul>`;

  // Above the comparison, right under the subtitle: at the bottom of four
  // sections of specs nobody scrolled down to find it.
  if (placement === 'top') {
    const subtitle = container.querySelector('.cmp-subtitle');
    container.insertBefore(panel, subtitle ? subtitle.nextSibling : container.firstChild);
  } else {
    container.appendChild(panel);
  }

  const input = panel.querySelector('#cmpAddSearch');
  const list = panel.querySelector('#cmpAddResults');
  const selected = new Set(tokens);

  function matches(q) {
    const terms = q.toLowerCase().split(/\s+/).filter(Boolean);
    if (!terms.length) return [];
    return rows.filter(r => {
      if (!r || selected.has(String(r[COL.tireId]))) return false;
      const hay = `${r[COL.brand]} ${r[COL.model]} ${r[COL.size]} ${r[COL.category]}`.toLowerCase();
      return terms.every(t => hay.includes(t));
    }).slice(0, 8);
  }

  function closeList() {
    list.innerHTML = '';
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
  }

  function showResults(q) {
    const found = matches(q);
    list.innerHTML = '';
    if (!found.length) {
      if (q.trim()) {
        const li = document.createElement('li');
        li.className = 'cmp-add-empty';
        li.textContent = 'No tires match.';
        list.appendChild(li);
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      } else {
        closeList();
      }
      return;
    }
    found.forEach(r => {
      const li = document.createElement('li');
      li.setAttribute('role', 'option');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cmp-add-result';
      btn.dataset.tireId = String(r[COL.tireId]);

      const name = document.createElement('span');
      name.className = 'cmp-add-result-name';
      name.textContent = `${r[COL.brand]} ${r[COL.model]}`;

      const meta = document.createElement('span');
      meta.className = 'cmp-add-result-meta';
      const price = parseFloat(r[COL.price]);
      meta.textContent = [r[COL.size], r[COL.category], (price > 0 ? '$' + Math.round(price) : '')].filter(Boolean).join(' · ');

      btn.appendChild(name);
      btn.appendChild(meta);
      li.appendChild(btn);
      list.appendChild(li);
    });
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  }

  let timer = null;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => showResults(input.value), 120);
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeList();
    } else if (e.key === 'ArrowDown') {
      const first = list.querySelector('.cmp-add-result');
      if (first) { e.preventDefault(); first.focus(); }
    } else if (e.key === 'Enter') {
      const first = list.querySelector('.cmp-add-result');
      if (first) { e.preventDefault(); first.click(); }
    }
  });
  list.addEventListener('keydown', (e) => {
    const items = Array.from(list.querySelectorAll('.cmp-add-result'));
    const i = items.indexOf(document.activeElement);
    if (e.key === 'ArrowDown' && i >= 0 && i < items.length - 1) { e.preventDefault(); items[i + 1].focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); (i > 0 ? items[i - 1] : input).focus(); }
    else if (e.key === 'Escape') { closeList(); input.focus(); }
  });
  list.addEventListener('click', (e) => {
    const btn = e.target.closest('.cmp-add-result');
    if (!btn) return;
    const id = btn.dataset.tireId;
    if (!id || selected.has(id) || tokens.length >= MAX_COMPARE) return;
    setCompareTokens(tokens.concat(id));
  });
}

// One document listener for the add panel, whichever render it belongs to:
// re-rendering after each add or remove would otherwise stack a listener
// per render, each holding a detached panel.
document.addEventListener('click', (e) => {
  const panel = document.getElementById('cmpAddPanel');
  if (!panel || panel.contains(e.target)) return;
  const list = panel.querySelector('.cmp-add-results');
  const input = panel.querySelector('.cmp-add-input');
  if (list) { list.innerHTML = ''; list.hidden = true; }
  if (input) input.setAttribute('aria-expanded', 'false');
});

// --- Main render ---
function renderComparison(rows, tokens) {
  // Tokens are tire_ids; a purely numeric token that matches no tire_id is
  // treated as a legacy row index so links shared before the ID format keep
  // rendering something.
  const tires = tokens
    .map(tok => {
      const byId = rows.find(r => r && String(r[COL.tireId]) === tok);
      if (byId) return byId;
      return /^\d+$/.test(tok) ? rows[parseInt(tok, 10)] : null;
    })
    .filter(Boolean);
  const container = document.getElementById("comparisonContent");

  if (!tires.length) return false; // Keep the default empty state from PHP.

  // The tokens that resolved, in order — what removal and adding edit.
  const liveTokens = tires.map(t => String(t[COL.tireId]));

  const best = findBestValues(tires);
  const n = tires.length;

  // --- Tire header cards ---
  let html = `<div class="cmp-tire-headers" style="--cmp-cols:${n}">`;
  tires.forEach(t => {
    const img = safeImageURL(t[COL.image]);
    const diameter = t[COL.diameter] || "";
    const diameterDisplay = diameter && !diameter.includes('"') ? diameter + '"' : diameter;
    const page = tirePageUrl(t);
    const name = escapeHTML(t[COL.brand] + ' ' + t[COL.model]);
    const model = page
      ? `<a class="cmp-tire-model-link" href="${escapeHTML(page)}">${escapeHTML(t[COL.model])}</a>`
      : escapeHTML(t[COL.model]);
    const setPriceText = formatSetPrice(t[COL.price]);

    html += `<div class="cmp-tire-header" data-tire-id="${escapeHTML(String(t[COL.tireId]))}">
      <button type="button" class="cmp-tire-remove" data-remove="${escapeHTML(String(t[COL.tireId]))}" aria-label="Remove ${name} from the comparison" title="Remove from comparison">${rtgIcon('xmark', 14)}</button>
      <div class="cmp-tire-img-wrap">
        ${page ? `<a href="${escapeHTML(page)}" aria-label="${name} tire page">` : ''}
        ${img ? `<img src="${escapeHTML(img)}" alt="${name}" loading="lazy" />` :
          rtgIcon('image', 32, 'cmp-placeholder-icon')}
        ${page ? '</a>' : ''}
      </div>
      <div class="cmp-tire-info">
        <div class="cmp-tire-brand">${escapeHTML(t[COL.brand])}</div>
        <div class="cmp-tire-model">${model}</div>
        <div class="cmp-tire-size">${escapeHTML(t[COL.size])} &middot; ${escapeHTML(diameterDisplay)} &middot; ${escapeHTML(t[COL.category])}</div>
        <div class="cmp-tire-meta">
          <div class="cmp-tire-meta-item">
            <span class="cmp-tire-meta-label">Price</span>
            <span class="cmp-tire-meta-value">${fmtPrice(t[COL.price])}</span>
            ${setPriceText ? `<span class="cmp-tire-meta-sub">${escapeHTML(setPriceText)} / set</span>` : ''}
          </div>
          <div class="cmp-tire-meta-item">
            <span class="cmp-tire-meta-label">Weight</span>
            <span class="cmp-tire-meta-value">${fmtWeight(t[COL.weight])}</span>
          </div>
        </div>
        ${page ? `<a class="cmp-tire-page-link" href="${escapeHTML(page)}">Full specs &amp; reviews ${rtgIcon('arrow-right', 11)}</a>` : ''}
      </div>
    </div>`;
  });
  html += '</div>';

  // --- Subtitle ---
  const subtitle = n === 1
    ? 'One tire so far. Add another below to compare them side by side.'
    : `Comparing ${n} tires side by side. Best values are <span style="color:var(--rtg-accent);font-weight:600">highlighted</span>.`;
  html = `<p class="cmp-subtitle">${subtitle}</p>` + html;

  // --- Spec sections ---

  html += specSection('dollar-sign', 'Price & Value', [
    ['Price', t => renderPrice(t), 'price'],
    ['Mileage Warranty', t => fmtWarranty(t[COL.warranty]), 'warranty'],
    ['Category', t => t[COL.category] || "-"],
  ], tires, best, n);

  html += specSection('gauge-high', 'Performance', [
    ['Real-World Efficiency', t => {
      const v = parseFloat(t[COL.roamerEfficiency]);
      if (!v || v === 0) return '-';
      const miPerKwh = v.toFixed(2);
      const mi = Math.round((parseFloat(t[COL.roamerTotalKm]) || 0) * 0.621371);
      const veh = parseInt(t[COL.roamerVehicleCount]) || 0;
      return rawHTML(
        '<span style="display:inline-block;background:rgba(59,130,246,0.15);border-radius:6px;padding:2px 8px;font-weight:700;color:#60a5fa;">' + miPerKwh + ' mi/kWh</span>' +
        '<br><span style="font-size:11px;color:#a19e97;">' + mi.toLocaleString() + ' mi tracked, ' + veh + ' vehicle' + (veh !== 1 ? 's' : '') + '</span>',
        v
      );
    }, 'roamerEfficiency'],
    ['Speed Rating', t => t[COL.speedRating] || "-"],
    ['UTQG', t => t[COL.utqg] || "None"],
    ['3PMS Rated', t => {
      const v = (t[COL.threePms] || "").toLowerCase();
      return v === "yes" ? rawHTML('<span style="color:#4ade80;font-weight:600">' + rtgIcon('check', 14) + ' Yes</span>') : 'No';
    }],
  ], tires, best, n);

  html += specSection('weight-hanging', 'Size & Weight', [
    ['Tire Size', t => t[COL.size] || "-"],
    ['Rim Diameter', t => {
      const d = t[COL.diameter] || "-";
      return d !== "-" && !d.includes('"') ? d + '"' : d;
    }],
    ['Weight', t => fmtWeight(t[COL.weight]), 'weight'],
    ['Tread Depth', t => t[COL.tread] || "-"],
  ], tires, best, n);

  html += specSection('truck', 'Load & Pressure', [
    ['Load Index', t => renderLoadIndex(t)],
    ['Max Load', t => fmtLoad(t[COL.maxLoad])],
    ['Load Range', t => t[COL.loadRange] || "-"],
    ['Max PSI', t => {
      const v = t[COL.psi];
      return v && v !== "-" ? v + " psi" : "-";
    }],
  ], tires, best, n);

  html += specSection('tags', 'Tags & Features', [
    ['Tags', t => renderTags(t[COL.tags])],
  ], tires, best, n);

  html += specSection('cart-shopping', 'Where to Buy', [
    ['Links', t => renderCTAs(t)],
  ], tires, best, n);

  container.innerHTML = html;

  // Per-column remove: the URL is the state, so drop the token and re-render.
  container.querySelectorAll('.cmp-tire-remove').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.remove;
      setCompareTokens(liveTokens.filter(tok => tok !== id));
    });
  });

  if (n < MAX_COMPARE) {
    renderAddPanel(container, rows, liveTokens);
  }

  return true;
}

// --- Share button ---
function initShareButton() {
  const btn = document.getElementById("shareBtn");
  if (!btn) return;
  btn.addEventListener("click", () => {
    const url = window.location.href;

    function showCopied() {
      const iconEl = btn.querySelector("i");
      const spanEl = btn.querySelector("span");
      const origIcon = iconEl ? iconEl.outerHTML : "";
      const origText = spanEl ? spanEl.textContent : "";
      if (iconEl) iconEl.outerHTML = rtgIcon('check', 16);
      if (spanEl) spanEl.textContent = "Copied!";
      setTimeout(() => {
        const current = btn.querySelector("i");
        if (current) current.outerHTML = origIcon;
        if (spanEl) spanEl.textContent = origText;
      }, 2000);
    }

    if (navigator.share) {
      navigator.share({ title: "Rivian Tire Comparison", url }).catch(() => {});
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(showCopied);
    }
  });
}

// --- Empty states ---

/** The PHP empty state is kept in the DOM's initial HTML; after the last
 *  column is removed it has to be rebuilt here. */
function renderEmptyState(message) {
  const container = document.getElementById("comparisonContent");
  const guideUrl = settings().guideUrl || '';
  container.innerHTML =
    '<div class="cmp-empty"><div class="cmp-empty-icon">' + rtgIcon('scale-balanced', 48) + '</div>' +
    '<div class="cmp-empty-title">No tires selected</div>' +
    '<div class="cmp-empty-text">' + escapeHTML(message) + '</div>' +
    (guideUrl ? `<a href="${escapeHTML(guideUrl)}" class="cmp-btn cmp-btn-primary">${rtgIcon('arrow-left', 13)} Browse Tires</a>` : '') +
    '</div>';
  if (typeof rtgData !== 'undefined' && Array.isArray(rtgData.tires)) {
    renderAddPanel(container, rtgData.tires, [], 'bottom');
  }
}

function renderPage() {
  const tokens = getCompareTokens();
  const container = document.getElementById("comparisonContent");
  if (!container) return;

  if (typeof rtgData === 'undefined' || !rtgData.tires || !Array.isArray(rtgData.tires)) {
    container.innerHTML =
      '<div class="cmp-empty"><div class="cmp-empty-icon">' + rtgIcon('triangle-exclamation', 48) + '</div>' +
      '<div class="cmp-empty-title">Data unavailable</div>' +
      '<div class="cmp-empty-text">Tire data could not be loaded.</div></div>';
    return;
  }

  if (!tokens.length) {
    renderEmptyState('Head back to the tire guide and select tires to compare, or search for one below.');
    return;
  }

  if (!renderComparison(rtgData.tires, tokens)) {
    renderEmptyState('None of the tires in this link exist any more. Pick some to compare.');
  }
}

// --- Init ---
document.addEventListener("DOMContentLoaded", () => {
  initShareButton();
  renderPage();
});
