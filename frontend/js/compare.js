/* =====================================================================
   Rivian Tire Guide — Compare Page
   ===================================================================== */

// --- Utilities ---

function rtgColor(name) {
  return getComputedStyle(document.documentElement).getPropertyValue('--rtg-' + name).trim();
}

function escapeHTML(str) {
  return String(str).replace(/[&<>"']/g, c =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c])
  );
}

function safeImageURL(url) {
  if (typeof url !== "string") return "";
  const trimmed = url.trim();
  const allowedHostnames = ["riviantrackr.com", "cdn.riviantrackr.com"];
  try {
    const u = new URL(trimmed);
    if (!/^https?:$/.test(u.protocol)) return "";
    if (!allowedHostnames.includes(u.hostname)) return "";
    if (u.pathname.includes('..') || u.pathname.includes('//')) return "";
    return trimmed;
  } catch {
    return "";
  }
}

function safeLinkURL(url) {
  if (typeof url !== "string" || !url.trim()) return "";
  const trimmed = url.trim();
  try {
    const urlObj = new URL(trimmed);
    if (urlObj.protocol !== 'https:') return "";
    if (urlObj.pathname.includes('..')) return "";
    const allowedDomains = [
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
    const hostname = urlObj.hostname.toLowerCase();
    const isAllowed = allowedDomains.some(domain =>
      hostname === domain || hostname.endsWith('.' + domain)
    );
    if (!isAllowed) return "";
    return trimmed;
  } catch {
    return "";
  }
}

function safeReviewLinkURL(url) {
  if (typeof url !== "string" || !url.trim()) return "";
  const trimmed = url.trim();
  try {
    const urlObj = new URL(trimmed);
    if (urlObj.protocol !== 'https:') return "";
    if (urlObj.pathname.includes('..')) return "";
    const allowedDomains = [
      'riviantrackr.com', 'www.riviantrackr.com',
      'youtube.com', 'www.youtube.com', 'youtu.be',
      'tiktok.com', 'www.tiktok.com',
      'instagram.com', 'www.instagram.com'
    ];
    const hostname = urlObj.hostname.toLowerCase();
    const isAllowed = allowedDomains.some(domain =>
      hostname === domain || hostname.endsWith('.' + domain)
    );
    if (!isAllowed) return "";
    return trimmed;
  } catch {
    return "";
  }
}

function getCompareIndexes() {
  const params = new URLSearchParams(window.location.search);
  return (params.get("compare") || "")
    .split(",")
    .map(n => parseInt(n.trim()))
    .filter(n => !isNaN(n));
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
  effScore: 20, effGrade: 21, bundleLink: 22, reviewLink: 23
};

// --- Efficiency badge colors ---
const GRADE_COLORS = {
  A: "#5ec095", B: "#a3e635", C: "#facc15", D: "#f97316", E: "#ef4444", F: "#b91c1c"
};

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

  const scores = tires.map(t => parseInt(t[COL.effScore])).filter(n => !isNaN(n) && n > 0);
  if (scores.length > 1) best.effScore = Math.max(...scores);

  return best;
}

// --- Build efficiency badge HTML ---
function effBadge(score, grade) {
  const s = escapeHTML(score || "-");
  const g = escapeHTML((grade || "-").toUpperCase());
  const color = GRADE_COLORS[g] || "#94a3b8";
  return `<span class="cmp-eff-badge">
    <span class="cmp-eff-grade" style="background:${color}">${g}</span>
    <span class="cmp-eff-score">${s}/100</span>
  </span>`;
}

// --- Build tag HTML ---
function renderTags(tagStr) {
  if (!tagStr || tagStr === "-") return "-";
  const tags = tagStr.split(/[,|]/).map(t => t.trim()).filter(Boolean);
  if (!tags.length) return "-";
  return `<div class="cmp-tags">${tags.map(tag => {
    const lower = tag.toLowerCase();
    let cls = "cmp-tag";
    if (lower.includes("ev rated")) cls += " cmp-tag-ev";
    else if (lower.includes("3pms") || lower.includes("3-peak")) cls += " cmp-tag-3pms";
    else if (lower.includes("studded")) cls += " cmp-tag-studded";
    return `<span class="${cls}">${escapeHTML(tag)}</span>`;
  }).join("")}</div>`;
}

// --- Build CTA buttons ---
function renderCTAs(tire) {
  const link = safeLinkURL(tire[COL.link]);
  const bundle = safeLinkURL(tire[COL.bundleLink]);
  const review = safeReviewLinkURL(tire[COL.reviewLink]);
  if (!link && !bundle && !review) return "-";
  let html = '<div class="cmp-cta-wrap">';
  if (link) {
    html += `<a href="${escapeHTML(link)}" target="_blank" rel="noopener noreferrer" class="cmp-cta cmp-cta-primary">
      View Tire <i class="fa-solid fa-arrow-up-right-from-square"></i></a>`;
  }
  if (bundle) {
    html += `<a href="${escapeHTML(bundle)}" target="_blank" rel="noopener noreferrer" class="cmp-cta cmp-cta-bundle">
      Wheel &amp; Tire Bundle <i class="fa-solid fa-arrow-up-right-from-square"></i></a>`;
  }
  if (review) {
    const isVideo = review.includes('youtube.com') || review.includes('youtu.be') || review.includes('tiktok.com');
    const icon = isVideo ? 'fa-circle-play' : 'fa-newspaper';
    const label = isVideo ? 'Watch Official Review' : 'Read Official Review';
    html += `<a href="${escapeHTML(review)}" target="_blank" rel="noopener noreferrer" class="cmp-cta cmp-cta-review">
      ${label} <i class="fa-solid ${icon}"></i></a>`;
  }
  html += '</div>';
  return html;
}

// --- Spec section builder ---
function specSection(icon, title, rows, tires, best, colCount) {
  let body = '';
  rows.forEach(([label, getter, bestKey]) => {
    const values = tires.map(t => {
      const val = getter(t);
      const isBest = bestKey && best[bestKey] !== undefined && parseFloat(val) === best[bestKey];
      return `<div class="cmp-row-value${isBest ? ' is-best' : ''}">${typeof val === 'string' && val.startsWith('<') ? val : escapeHTML(val || "-")}</div>`;
    });
    body += `<div class="cmp-row">
      <div class="cmp-row-label">${escapeHTML(label)}</div>
      <div class="cmp-row-values" style="--cmp-cols:${colCount}">${values.join("")}</div>
    </div>`;
  });

  return `<div class="cmp-section">
    <div class="cmp-section-header" onclick="this.parentElement.classList.toggle('collapsed')">
      <i class="cmp-section-icon fa-solid ${icon}"></i>
      <span class="cmp-section-title">${escapeHTML(title)}</span>
      <i class="cmp-section-chevron fa-solid fa-chevron-down"></i>
    </div>
    <div class="cmp-section-body">${body}</div>
  </div>`;
}

// --- Main render ---
function renderComparison(rows, indexes) {
  const tires = indexes.map(i => rows[i]).filter(Boolean);
  const container = document.getElementById("comparisonContent");

  if (!tires.length) return; // Keep the default empty state from PHP.

  const best = findBestValues(tires);
  const n = tires.length;

  // --- Tire header cards ---
  let html = `<div class="cmp-tire-headers" style="--cmp-cols:${n}">`;
  tires.forEach(t => {
    const img = safeImageURL(t[COL.image]);
    const diameter = t[COL.diameter] || "";
    const diameterDisplay = diameter && !diameter.includes('"') ? diameter + '"' : diameter;

    html += `<div class="cmp-tire-header">
      <div class="cmp-tire-img-wrap">
        ${img ? `<img src="${escapeHTML(img)}" alt="${escapeHTML(t[COL.brand] + ' ' + t[COL.model])}" loading="lazy" />` :
          '<i class="fa-solid fa-image" style="font-size:32px;color:var(--rtg-border)"></i>'}
      </div>
      <div class="cmp-tire-info">
        <div class="cmp-tire-brand">${escapeHTML(t[COL.brand])}</div>
        <div class="cmp-tire-model">${escapeHTML(t[COL.model])}</div>
        <div class="cmp-tire-size">${escapeHTML(t[COL.size])} &middot; ${escapeHTML(diameterDisplay)} &middot; ${escapeHTML(t[COL.category])}</div>
        <div class="cmp-tire-meta">
          <div class="cmp-tire-meta-item">
            <span class="cmp-tire-meta-label">Price</span>
            <span class="cmp-tire-meta-value">${fmtPrice(t[COL.price])}</span>
          </div>
          <div class="cmp-tire-meta-item">
            <span class="cmp-tire-meta-label">Weight</span>
            <span class="cmp-tire-meta-value">${fmtWeight(t[COL.weight])}</span>
          </div>
          <div class="cmp-tire-meta-item">
            <span class="cmp-tire-meta-label">Efficiency</span>
            <span class="cmp-tire-meta-value">${effBadge(t[COL.effScore], t[COL.effGrade])}</span>
          </div>
        </div>
      </div>
    </div>`;
  });
  html += '</div>';

  // --- Subtitle ---
  html = `<p class="cmp-subtitle">Comparing ${n} tire${n !== 1 ? "s" : ""} side by side. Best values are <span style="color:var(--rtg-accent);font-weight:600">highlighted</span>.</p>` + html;

  // --- Spec sections ---

  html += specSection('fa-dollar-sign', 'Price & Value', [
    ['Price', t => fmtPrice(t[COL.price]), 'price'],
    ['Mileage Warranty', t => fmtWarranty(t[COL.warranty]), 'warranty'],
    ['Category', t => t[COL.category] || "-"],
  ], tires, best, n);

  html += specSection('fa-gauge-high', 'Performance', [
    ['Efficiency', t => effBadge(t[COL.effScore], t[COL.effGrade]), 'effScore'],
    ['Speed Rating', t => t[COL.speedRating] || "-"],
    ['UTQG', t => t[COL.utqg] || "-"],
    ['3PMS Rated', t => {
      const v = (t[COL.threePms] || "").toLowerCase();
      return v === "yes" ? '<span style="color:var(--rtg-accent);font-weight:600"><i class="fa-solid fa-check"></i> Yes</span>' : 'No';
    }],
  ], tires, best, n);

  html += specSection('fa-weight-hanging', 'Size & Weight', [
    ['Tire Size', t => t[COL.size] || "-"],
    ['Rim Diameter', t => {
      const d = t[COL.diameter] || "-";
      return d !== "-" && !d.includes('"') ? d + '"' : d;
    }],
    ['Weight', t => fmtWeight(t[COL.weight]), 'weight'],
    ['Tread Depth', t => t[COL.tread] || "-"],
  ], tires, best, n);

  html += specSection('fa-truck', 'Load & Pressure', [
    ['Load Index', t => t[COL.loadIndex] || "-"],
    ['Max Load', t => fmtLoad(t[COL.maxLoad])],
    ['Load Range', t => t[COL.loadRange] || "-"],
    ['Max PSI', t => {
      const v = t[COL.psi];
      return v && v !== "-" ? v + " psi" : "-";
    }],
  ], tires, best, n);

  html += specSection('fa-tags', 'Tags & Features', [
    ['Tags', t => renderTags(t[COL.tags])],
  ], tires, best, n);

  html += specSection('fa-cart-shopping', 'Where to Buy', [
    ['Links', t => renderCTAs(t)],
  ], tires, best, n);

  container.innerHTML = html;
}

// --- Share button ---
function initShareButton() {
  const btn = document.getElementById("shareBtn");
  if (!btn) return;
  btn.addEventListener("click", () => {
    const url = window.location.href;

    function showCopied() {
      const icon = btn.querySelector("i");
      const spanEl = btn.querySelector("span");
      const origIcon = icon.className;
      const origText = spanEl ? spanEl.textContent : "";
      icon.className = "fa-solid fa-check";
      if (spanEl) spanEl.textContent = "Copied!";
      setTimeout(() => {
        icon.className = origIcon;
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

// --- Init ---
document.addEventListener("DOMContentLoaded", () => {
  const indexes = getCompareIndexes();
  initShareButton();
  if (!indexes.length) return;

  if (typeof rtgData !== 'undefined' && rtgData.tires && Array.isArray(rtgData.tires)) {
    renderComparison(rtgData.tires, indexes);
  } else {
    document.getElementById("comparisonContent").innerHTML =
      '<div class="cmp-empty"><div class="cmp-empty-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
      '<div class="cmp-empty-title">Data unavailable</div>' +
      '<div class="cmp-empty-text">Tire data could not be loaded.</div></div>';
  }
});
