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
  roamerEfficiency: 24, roamerSessionCount: 25, roamerVehicleCount: 26
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

// --- Find best tire per category for a set of compatible sizes ---
// Returns { category: { tire: [...], source: 'roamer'|'calculated' } }
function findWinners(tires, compatibleSizes) {
  var roamerBest = {};
  var calcBest = {};

  for (var i = 0; i < tires.length; i++) {
    var tire = tires[i];

    // If we have size restrictions, filter by compatible sizes.
    if (compatibleSizes && compatibleSizes.indexOf(tire[COL.size]) === -1) continue;

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

// --- Render the grid for the active vehicle ---
function renderGrid(tires, vehicleSizes, activeVehicle) {
  var grid = document.getElementById('effGrid');
  if (!grid) return;

  var sizes = activeVehicle ? vehicleSizes[activeVehicle] || [] : null;
  var winners = findWinners(tires, sizes);

  if (Object.keys(winners).length === 0) {
    grid.innerHTML = '<div class="eff-empty">' +
      '<div class="eff-empty-icon">' + rtgIcon('chart-line', 48) + '</div>' +
      '<div class="eff-empty-title">No efficiency data available</div>' +
      '<div class="eff-empty-text">No tires with efficiency data are available' +
      (activeVehicle ? ' for the ' + escapeHTML(activeVehicle) + '.' : '.') +
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

// --- Vehicle tab click handler ---
function initVehicleTabs(tires, vehicleSizes) {
  var container = document.getElementById('effVehicleToggle');
  if (!container) return;

  var buttons = container.querySelectorAll('.eff-vehicle-btn');
  buttons.forEach(function(btn) {
    btn.addEventListener('click', function() {
      buttons.forEach(function(b) {
        b.classList.remove('active');
        b.setAttribute('aria-pressed', 'false');
      });
      btn.classList.add('active');
      btn.setAttribute('aria-pressed', 'true');

      var vehicle = btn.getAttribute('data-vehicle') || '';
      renderGrid(tires, vehicleSizes, vehicle || null);
    });
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
  var vehicleSizes = rtgEfficiency.vehicleSizes || {};

  // Build vehicle toggle buttons dynamically.
  var toggle = document.getElementById('effVehicleToggle');
  if (toggle) {
    var vehicleKeys = Object.keys(vehicleSizes);
    vehicleKeys.sort();
    var buttonsHtml = '<button type="button" class="eff-vehicle-btn active" data-vehicle="" aria-pressed="true">All Vehicles</button>';
    for (var i = 0; i < vehicleKeys.length; i++) {
      buttonsHtml += '<button type="button" class="eff-vehicle-btn" data-vehicle="' + escapeHTML(vehicleKeys[i]) + '" aria-pressed="false">' + escapeHTML(vehicleKeys[i]) + '</button>';
    }
    toggle.innerHTML = buttonsHtml;
  }

  initVehicleTabs(tires, vehicleSizes);
  renderGrid(tires, vehicleSizes, null);
});
