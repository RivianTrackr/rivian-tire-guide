/* =====================================================================
   Rivian Tire Guide — Tire Efficiency Rankings Page
   Shows the most efficient tire per vehicle type and category
   based on real-world Rivian Roamer data (mi/kWh).
   Uses RTG_SHARED (rtg-shared.js) for URL validation & escaping.
   ===================================================================== */

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

// --- Column index map (matches the localized array order) ---
var COL = {
  tireId: 0, size: 1, diameter: 2, brand: 3, model: 4, category: 5,
  price: 6, warranty: 7, weight: 8, threePms: 9, tread: 10,
  loadIndex: 11, maxLoad: 12, loadRange: 13, speedRating: 14,
  psi: 15, utqg: 16, tags: 17, link: 18, image: 19,
  effScore: 20, effGrade: 21, reviewLink: 22, createdAt: 23,
  roamerEfficiency: 24, roamerSessionCount: 25, roamerVehicleCount: 26,
  vehicleBreakdown: 27
};

// --- Efficiency grade colors ---
var GRADE_COLORS = {
  A: '#5ec095', B: '#a3e635', C: '#facc15', D: '#f97316', F: '#b91c1c'
};

// --- Category display order ---
var CATEGORY_ORDER = [
  'All-Season', 'Highway', 'Performance', 'All-Terrain',
  'Rugged Terrain', 'Mud-Terrain', 'Winter'
];

// --- Active filters (module-level so renderWinnerCard can access them) ---
var activeFilters = { model: '', gen: '', pack: '' };

// --- Parse vehicle breakdown JSON string ---
function parseBreakdown(jsonStr) {
  if (!jsonStr) return [];
  try { return JSON.parse(jsonStr); } catch (e) { return []; }
}

// --- Parse a variant name like "Gen 2 R1S Max" into components ---
function parseVariant(name) {
  var match = name.match(/^(Gen \d+)\s+(R1[TS]|R2|R3)\s*(.*)/i);
  if (!match) return { gen: '', model: '', pack: '', full: name };
  return { gen: match[1], model: match[2].toUpperCase(), pack: match[3].trim(), full: name };
}

// --- Check if a single variant matches the active filters ---
function variantMatchesFilters(parsed, filters) {
  if (filters.model && parsed.model !== filters.model) return false;
  if (filters.gen && parsed.gen !== filters.gen) return false;
  if (filters.pack && parsed.pack !== filters.pack) return false;
  return true;
}

// --- Check if a tire has any variant matching the active filters ---
function tireMatchesFilters(tire, filters) {
  if (!filters.model && !filters.gen && !filters.pack) return true;
  var bd = parseBreakdown(tire[COL.vehicleBreakdown]);
  for (var i = 0; i < bd.length; i++) {
    var parsed = parseVariant(bd[i][0]);
    if (variantMatchesFilters(parsed, filters)) return true;
  }
  return false;
}

// --- Collect all unique filter values from breakdown data ---
// Returns { models: [...], gens: [...], packs: [...] }
function collectFilterOptions(tires) {
  var models = {};
  var gens = {};
  var packs = {};

  for (var i = 0; i < tires.length; i++) {
    var bd = parseBreakdown(tires[i][COL.vehicleBreakdown]);
    for (var j = 0; j < bd.length; j++) {
      var parsed = parseVariant(bd[j][0]);
      if (parsed.model) models[parsed.model] = true;
      if (parsed.gen) gens[parsed.gen] = true;
      if (parsed.pack) packs[parsed.pack] = true;
    }
  }

  return {
    models: Object.keys(models).sort(),
    gens: Object.keys(gens).sort(),
    packs: Object.keys(packs).sort()
  };
}

// --- Collect available options for downstream filters given current selections ---
// This enables cascading: picking "R1T" narrows the available gens and packs.
function collectAvailableOptions(tires, filters) {
  var gens = {};
  var packs = {};

  for (var i = 0; i < tires.length; i++) {
    var bd = parseBreakdown(tires[i][COL.vehicleBreakdown]);
    for (var j = 0; j < bd.length; j++) {
      var parsed = parseVariant(bd[j][0]);
      // Check if variant passes the model filter (or no model filter set).
      if (filters.model && parsed.model !== filters.model) continue;
      if (parsed.gen) gens[parsed.gen] = true;
      // Check if variant passes model + gen filter for packs.
      if (filters.gen && parsed.gen !== filters.gen) continue;
      if (parsed.pack) packs[parsed.pack] = true;
    }
  }

  return {
    gens: Object.keys(gens).sort(),
    packs: Object.keys(packs).sort()
  };
}

// --- Find best tire per category, filtered by active filters ---
// Returns { category: { tire: [...], source: 'roamer'|'calculated' } }
function findWinners(tires, filters) {
  var roamerBest = {};
  var calcBest = {};

  for (var i = 0; i < tires.length; i++) {
    var tire = tires[i];

    // Filter by vehicle filters if active.
    if (!tireMatchesFilters(tire, filters)) continue;

    var category = tire[COL.category] || 'Other';
    var roamerEff = parseFloat(tire[COL.roamerEfficiency]);
    var calcScore = parseInt(tire[COL.effScore], 10);

    // Track best Roamer efficiency per category.
    if (roamerEff > 0) {
      if (!roamerBest[category] || roamerEff > parseFloat(roamerBest[category][COL.roamerEfficiency])) {
        roamerBest[category] = tire;
      }
    }

    // Track best calculated efficiency score per category.
    if (calcScore > 0) {
      if (!calcBest[category] || calcScore > parseInt(calcBest[category][COL.effScore], 10)) {
        calcBest[category] = tire;
      }
    }
  }

  // Merge: prefer Roamer data, fall back to calculated.
  var winners = {};
  var allCategories = {};
  var key;
  for (key in roamerBest) { allCategories[key] = true; }
  for (key in calcBest) { allCategories[key] = true; }

  for (key in allCategories) {
    if (roamerBest[key]) {
      winners[key] = { tire: roamerBest[key], source: 'roamer' };
    } else if (calcBest[key]) {
      winners[key] = { tire: calcBest[key], source: 'calculated' };
    }
  }

  return winners;
}

// --- Render vehicle breakdown pills for a card ---
function renderBreakdownPills(tire) {
  var bd = parseBreakdown(tire[COL.vehicleBreakdown]);
  if (bd.length === 0) return '';

  var html = '<div class="eff-card-variants">';
  for (var k = 0; k < bd.length; k++) {
    var vName = bd[k][0];
    var vCount = bd[k][1];
    var parsed = parseVariant(vName);
    var isActive = variantMatchesFilters(parsed, activeFilters) &&
                   (activeFilters.model || activeFilters.gen || activeFilters.pack);
    html += '<span class="eff-variant-pill' + (isActive ? ' active' : '') + '">';
    html += escapeHTML(vName) + ' <small>(' + vCount + ')</small>';
    html += '</span>';
  }
  html += '</div>';
  return html;
}

// --- Render a winner card ---
// entry: { tire: [...], source: 'roamer'|'calculated' }
function renderWinnerCard(entry) {
  var tire = entry.tire;
  var source = entry.source;
  var img = safeImageURL(tire[COL.image]);
  var grade = (tire[COL.effGrade] || '-').toUpperCase();
  var gradeColor = GRADE_COLORS[grade] || '#94a3b8';
  var price = parseFloat(tire[COL.price]);
  var priceStr = isNaN(price) ? '-' : '$' + price.toFixed(2);
  var link = safeLinkURL(tire[COL.link]);

  var html = '<div class="eff-card">';

  // Category badge
  html += '<div class="eff-card-category">' + escapeHTML(tire[COL.category]) + '</div>';

  // Source badge
  if (source === 'roamer') {
    html += '<span class="eff-card-source-badge eff-source-roamer">' + rtgIcon('signal', 10) + ' Real-World Data</span>';
  } else {
    html += '<span class="eff-card-source-badge eff-source-calculated">' + rtgIcon('calculator', 10) + ' Calculated</span>';
  }

  // Image
  html += '<div class="eff-card-img-wrap">';
  if (img) {
    html += '<img src="' + escapeHTML(img) + '" alt="' + escapeHTML(tire[COL.brand] + ' ' + tire[COL.model]) + '" loading="lazy" />';
  } else {
    html += rtgIcon('image', 40, 'eff-placeholder-icon');
  }
  html += '</div>';

  // Tire info
  html += '<div class="eff-card-brand">' + escapeHTML(tire[COL.brand]) + '</div>';
  html += '<div class="eff-card-model">' + escapeHTML(tire[COL.model]) + '</div>';
  html += '<div class="eff-card-size">' + escapeHTML(tire[COL.size]) + '</div>';

  // Efficiency display — differs by source
  if (source === 'roamer') {
    var efficiency = parseFloat(tire[COL.roamerEfficiency]).toFixed(2);
    var sessions = parseInt(tire[COL.roamerSessionCount]) || 0;
    var vehicles = parseInt(tire[COL.roamerVehicleCount]) || 0;

    html += '<div class="eff-card-efficiency">';
    html += '<span class="eff-card-efficiency-value">' + escapeHTML(efficiency) + '</span>';
    html += '<span class="eff-card-efficiency-unit">mi/kWh</span>';
    html += '</div>';

    html += '<div class="eff-card-meta">';
    html += '<span>' + rtgIcon('database', 12) + ' ' + sessions.toLocaleString() + ' sessions</span>';
    html += '<span>' + rtgIcon('car', 12) + ' ' + vehicles + ' vehicle' + (vehicles !== 1 ? 's' : '') + '</span>';
    html += '</div>';
  } else {
    var score = parseInt(tire[COL.effScore], 10) || 0;

    html += '<div class="eff-card-efficiency">';
    html += '<span class="eff-card-efficiency-value">' + score + '</span>';
    html += '<span class="eff-card-efficiency-unit">/ 100</span>';
    html += '</div>';

    html += '<div class="eff-card-meta">';
    html += '<span>' + rtgIcon('gauge-high', 12) + ' Efficiency Score</span>';
    html += '<span>' + rtgIcon('ranking-star', 12) + ' Grade ' + escapeHTML(grade) + '</span>';
    html += '</div>';
  }

  // Vehicle breakdown pills
  html += renderBreakdownPills(tire);

  // Grade + Price row
  html += '<div class="eff-card-footer">';
  html += '<span class="eff-card-grade" style="background:' + gradeColor + '">' + escapeHTML(grade) + '</span>';
  html += '<span class="eff-card-price">' + escapeHTML(priceStr) + '</span>';
  html += '</div>';

  // Buy link
  if (link) {
    html += '<a href="' + escapeHTML(link) + '" target="_blank" rel="noopener noreferrer" class="eff-card-cta">';
    html += 'View Tire ' + rtgIcon('arrow-up-right', 12);
    html += '</a>';
  }

  html += '</div>';
  return html;
}

// --- Render the grid ---
function renderGrid(tires) {
  var grid = document.getElementById('effGrid');
  if (!grid) return;

  var winners = findWinners(tires, activeFilters);
  var hasFilter = activeFilters.model || activeFilters.gen || activeFilters.pack;

  if (Object.keys(winners).length === 0) {
    var filterDesc = buildFilterDescription();
    grid.innerHTML = '<div class="eff-empty">' +
      '<div class="eff-empty-icon">' + rtgIcon('chart-line', 48) + '</div>' +
      '<div class="eff-empty-title">No efficiency data available</div>' +
      '<div class="eff-empty-text">No tires with efficiency data are available' +
      (filterDesc ? ' for ' + escapeHTML(filterDesc) + '.' : '.') +
      '</div></div>';
    return;
  }

  var html = '';
  for (var i = 0; i < CATEGORY_ORDER.length; i++) {
    var cat = CATEGORY_ORDER[i];
    if (winners[cat]) {
      html += renderWinnerCard(winners[cat]);
    }
  }

  // Render any categories not in the predefined order.
  var keys = Object.keys(winners);
  for (var j = 0; j < keys.length; j++) {
    if (CATEGORY_ORDER.indexOf(keys[j]) === -1) {
      html += renderWinnerCard(winners[keys[j]]);
    }
  }

  grid.innerHTML = html;
}

// --- Build a human-readable description of the active filters ---
function buildFilterDescription() {
  var parts = [];
  if (activeFilters.gen) parts.push(activeFilters.gen);
  if (activeFilters.model) parts.push(activeFilters.model);
  if (activeFilters.pack) parts.push(activeFilters.pack);
  return parts.join(' ');
}

// --- Render a single filter row ---
function renderFilterRow(containerId, label, options, activeValue, dataAttr) {
  var container = document.getElementById(containerId);
  if (!container) return;

  if (options.length === 0) {
    container.style.display = 'none';
    container.innerHTML = '';
    return;
  }

  container.style.display = '';
  var html = '<span class="eff-filter-label">' + escapeHTML(label) + '</span>';
  html += '<button type="button" class="eff-filter-btn' + (!activeValue ? ' active' : '') + '" data-' + dataAttr + '="" aria-pressed="' + (!activeValue ? 'true' : 'false') + '">All</button>';
  for (var i = 0; i < options.length; i++) {
    var val = options[i];
    var isActive = activeValue === val;
    html += '<button type="button" class="eff-filter-btn' + (isActive ? ' active' : '') + '" data-' + dataAttr + '="' + escapeHTML(val) + '" aria-pressed="' + (isActive ? 'true' : 'false') + '">' + escapeHTML(val) + '</button>';
  }
  container.innerHTML = html;
}

// --- Update all filter rows and re-render grid ---
function updateFilters(tires, allOptions) {
  // Always show model row with all options.
  renderFilterRow('effFilterModel', 'Vehicle', allOptions.models, activeFilters.model, 'model');

  // Cascade: available gens/packs depend on the model + gen selection.
  var available = collectAvailableOptions(tires, activeFilters);
  renderFilterRow('effFilterGen', 'Generation', available.gens, activeFilters.gen, 'gen');
  renderFilterRow('effFilterPack', 'Battery', available.packs, activeFilters.pack, 'pack');

  renderGrid(tires);
}

// --- Attach click handlers to a filter row ---
function initFilterRow(containerId, filterKey, tires, allOptions) {
  var container = document.getElementById(containerId);
  if (!container) return;

  container.addEventListener('click', function(e) {
    var btn = e.target.closest('.eff-filter-btn');
    if (!btn) return;

    var value = btn.getAttribute('data-' + filterKey) || '';
    activeFilters[filterKey] = value;

    // When a higher-level filter changes, reset lower-level filters.
    if (filterKey === 'model') {
      activeFilters.gen = '';
      activeFilters.pack = '';
    } else if (filterKey === 'gen') {
      activeFilters.pack = '';
    }

    updateFilters(tires, allOptions);
  });
}

// --- Share button ---
function initShareButton() {
  var btn = document.getElementById('effShareBtn');
  if (!btn) return;
  btn.addEventListener('click', function() {
    var url = window.location.href;

    function showCopied() {
      var iconEl = btn.querySelector('i');
      var spanEl = btn.querySelector('span');
      var origIcon = iconEl ? iconEl.outerHTML : '';
      var origText = spanEl ? spanEl.textContent : '';
      if (iconEl) iconEl.outerHTML = rtgIcon('check', 16);
      if (spanEl) spanEl.textContent = 'Copied!';
      setTimeout(function() {
        var current = btn.querySelector('i');
        if (current) current.outerHTML = origIcon;
        if (spanEl) spanEl.textContent = origText;
      }, 2000);
    }

    if (navigator.share) {
      navigator.share({ title: 'Rivian Tire Efficiency Rankings', url: url }).catch(function() {});
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(showCopied);
    }
  });
}

// --- Init ---
document.addEventListener('DOMContentLoaded', function() {
  initShareButton();

  if (typeof rtgEfficiency === 'undefined' || !rtgEfficiency.tires || !Array.isArray(rtgEfficiency.tires)) {
    var grid = document.getElementById('effGrid');
    if (grid) {
      grid.innerHTML = '<div class="eff-empty">' +
        '<div class="eff-empty-icon">' + rtgIcon('triangle-exclamation', 48) + '</div>' +
        '<div class="eff-empty-title">Data unavailable</div>' +
        '<div class="eff-empty-text">Tire data could not be loaded.</div></div>';
    }
    return;
  }

  var tires = rtgEfficiency.tires;
  var allOptions = collectFilterOptions(tires);

  // Initialize filter rows.
  initFilterRow('effFilterModel', 'model', tires, allOptions);
  initFilterRow('effFilterGen', 'gen', tires, allOptions);
  initFilterRow('effFilterPack', 'pack', tires, allOptions);

  // Initial render.
  updateFilters(tires, allOptions);
});
