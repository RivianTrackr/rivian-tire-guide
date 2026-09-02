/* jshint esversion: 11 */

/**
 * Card rendering — create, update, and manage tire cards.
 */

import { state, ROWS_PER_PAGE } from './state.js';
import { rtgIcon, safeString, getDOMElement } from './helpers.js';
import { VALIDATION_PATTERNS, NUMERIC_BOUNDS, validateNumeric, safeImageURL, safeLinkURL, safeReviewLinkURL } from './validation.js';
import { TOOLTIP_DATA, createInfoTooltip } from './tooltips.js';
import { createRatingHTML } from './ratings.js';
import { setupCompareCheckboxes } from './compare.js';
import { openImageModal } from './image-modal.js';
import { fitmentShortfalls, describeShortfalls } from './fitment.js';
import { formatSetPrice, formatWholePrice, priceFreshness, SET_QUANTITY } from './pricing.js';
import { isLimitedSample } from './efficiency.js';

/**
 * Stored tags that mean nothing to a shopper and never render as chips.
 * "oem" is the corner badge; "riv" is an internal marker.
 */
const HIDDEN_TAGS = new Set(['oem', 'riv']);

// IntersectionObserver for enhanced lazy loading with fade-in
let imageObserver = null;

function setupImageObserver() {
  if (imageObserver || !('IntersectionObserver' in window)) return;

  imageObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        const src = img.dataset.src;
        if (src) {
          img.src = src;
          img.removeAttribute('data-src');
          img.addEventListener('load', () => {
            img.classList.add('rtg-img-loaded');
          }, { once: true });
        }
        imageObserver.unobserve(img);
      }
    });
  }, {
    rootMargin: '600px 0px',
    threshold: 0
  });
}

export function observeCardImages(container) {
  if (!imageObserver) setupImageObserver();
  if (!imageObserver) return;

  const images = container.querySelectorAll('img[data-src]');
  images.forEach(img => imageObserver.observe(img));
}

/**
 * Disconnect the shared IntersectionObserver. Call when the guide is
 * being torn down or the card container is about to be cleared in bulk.
 */
export function disconnectImageObserver() {
  if (imageObserver) {
    imageObserver.disconnect();
    imageObserver = null;
  }
}

// Clean up when the page is unloaded so we don't hold the observer across
// page transitions in persistent-navigation setups.
if (typeof window !== 'undefined') {
  window.addEventListener('pagehide', disconnectImageObserver, { once: true });
}

function removeSkeletonLoader() {
  const skeleton = document.getElementById('rtg-skeleton-loader');
  if (skeleton) skeleton.remove();
}

export function renderCards(rows) {
  removeSkeletonLoader();

  if (!state.cardContainer) {
    state.cardContainer = getDOMElement("tireCards");
  }

  if (typeof tireRatingAjax !== 'undefined') {
    state.isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
  }

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isMobile = window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

  const animationDuration = prefersReducedMotion ? 0 : (isMobile ? 200 : 300);
  const animationDelay = prefersReducedMotion ? 0 : (isMobile ? 100 : 150);

  const targetTireIds = new Set(
    rows.map(row => row[0])
       .filter(id => VALIDATION_PATTERNS.tireId.test(id))
  );

  const currentCards = Array.from(state.cardContainer.children);
  const cardsToKeep = new Set();
  const cardsToRemove = [];

  currentCards.forEach(card => {
    const tireId = card.dataset.tireId;
    if (targetTireIds.has(tireId)) {
      cardsToKeep.add(tireId);
    } else {
      cardsToRemove.push(card);
    }
  });

  cardsToRemove.forEach(card => {
    if (prefersReducedMotion) {
      if (card.parentNode) {
        card.parentNode.removeChild(card);
      }
    } else {
      card.style.transition = `opacity ${animationDuration}ms ease, transform ${animationDuration}ms ease`;
      card.style.opacity = '0';
      card.style.transform = isMobile ? 'translateY(-4px) scale(0.98)' : 'translateY(-8px) scale(0.97)';

      setTimeout(() => {
        if (card.parentNode) {
          card.parentNode.removeChild(card);
        }
      }, animationDelay);
    }
  });

  const newCards = [];
  rows.forEach((row) => {
    const tireId = row[0];
    if (VALIDATION_PATTERNS.tireId.test(tireId) && !cardsToKeep.has(tireId)) {
      const card = createSingleCard(row);
      if (card) {
        if (!prefersReducedMotion) {
          card.style.opacity = '0';
          card.style.transform = isMobile ? 'translateY(8px) scale(0.98)' : 'translateY(12px) scale(0.97)';
        }
        newCards.push(card);
      }
    }
  });

  const fragment = document.createDocumentFragment();
  newCards.forEach(card => fragment.appendChild(card));
  state.cardContainer.appendChild(fragment);

  if (!prefersReducedMotion && newCards.length > 0) {
    requestAnimationFrame(() => {
      newCards.forEach((card, index) => {
        const staggerDelay = isMobile ? index * 50 : index * 40;

        setTimeout(() => {
          card.style.transition = `opacity ${animationDuration}ms cubic-bezier(0.16, 1, 0.3, 1), transform ${animationDuration}ms cubic-bezier(0.16, 1, 0.3, 1)`;
          card.style.opacity = '1';
          card.style.transform = 'translateY(0) scale(1)';
        }, staggerDelay);
      });
    });
  } else if (prefersReducedMotion) {
    newCards.forEach(card => {
      card.style.opacity = '1';
      card.style.transform = 'translateY(0) scale(1)';
    });
  }

  const allCurrentCards = Array.from(state.cardContainer.children);
  const needsReorder = rows.some((row, index) => {
    const expectedTireId = row[0];
    const actualCard = allCurrentCards[index];
    return !actualCard || actualCard.dataset.tireId !== expectedTireId;
  });

  if (needsReorder) {
    rows.forEach((row, targetIndex) => {
      const tireId = row[0];
      if (!VALIDATION_PATTERNS.tireId.test(tireId)) return;

      const card = state.cardContainer.querySelector(`[data-tire-id="${CSS.escape(tireId)}"]`);
      if (card) {
        const currentIndex = Array.from(state.cardContainer.children).indexOf(card);
        if (currentIndex !== targetIndex) {
          const referenceCard = state.cardContainer.children[targetIndex];
          if (referenceCard && referenceCard !== card) {
            state.cardContainer.insertBefore(card, referenceCard);
          } else if (!referenceCard) {
            state.cardContainer.appendChild(card);
          }
        }
      }
    });
  }

  setupCompareCheckboxes();

  // Cards that survive a re-render keep their DOM, so the fitment warning
  // is re-judged for every card here rather than only when one is built:
  // the vehicle toggle is the input that changes most.
  refreshFitmentWarnings(state.cardContainer);

  // Trigger IntersectionObserver for lazy-loaded images
  if (state.cardContainer) observeCardImages(state.cardContainer);
}

// --- Load-index fitment warning ---

/** The vehicle toggle, read from the DOM to keep this module out of filters.js's import cycle. */
function activeVehicle() {
  const btn = document.querySelector('.rtg-vehicle-btn.active');
  return btn ? (btn.dataset.vehicle || '') : '';
}

function fitmentSettings() {
  const settings = (typeof rtgData !== 'undefined' && rtgData.settings) ? rtgData.settings : {};
  const map = (state.vehicleSizeMap && Object.keys(state.vehicleSizeMap).length)
    ? state.vehicleSizeMap
    : (settings.vehicleSizeMap || {});
  return { map, floors: settings.loadIndexFloors || {} };
}

/**
 * Fill or clear one card's fitment slot.
 *
 * With a vehicle pressed, the card is judged against that vehicle's floor.
 * With none, against every vehicle whose size list includes this tire — a
 * 110 in an R1 size is a problem whichever toggle is pressed.
 */
export function applyFitmentWarning(card, vehicle = activeVehicle()) {
  const slot = card.querySelector('.tire-card-fitment-slot');
  if (!slot) return;

  const { map, floors } = fitmentSettings();
  const tire = { loadIndex: card.dataset.loadIndex, size: card.dataset.size };
  const text = describeShortfalls(tire.loadIndex, fitmentShortfalls(tire, map, floors, vehicle));

  if (!text) {
    slot.innerHTML = '';
    slot.hidden = true;
    delete slot.dataset.text;
    card.classList.remove('has-fitment-warning');
    return;
  }

  // Same text: leave the node alone so a re-render doesn't flicker it.
  if (slot.dataset.text === text) return;
  slot.dataset.text = text;
  slot.innerHTML = '';

  const warning = document.createElement('div');
  warning.className = 'tire-card-fitment';
  warning.setAttribute('role', 'note');

  const icon = document.createElement('span');
  icon.className = 'tire-card-fitment-icon';
  icon.innerHTML = rtgIcon('triangle-exclamation', 13);

  const label = document.createElement('span');
  label.className = 'tire-card-fitment-text';
  label.textContent = text;

  const infoBtn = document.createElement('button');
  infoBtn.type = 'button';
  infoBtn.className = 'info-tooltip-trigger';
  infoBtn.dataset.tooltipKey = 'Load Index';
  infoBtn.setAttribute('aria-label', 'More info about Load Index');
  infoBtn.innerHTML = rtgIcon('circle-info', 12);

  warning.appendChild(icon);
  warning.appendChild(label);
  warning.appendChild(infoBtn);
  slot.appendChild(warning);
  slot.hidden = false;
  card.classList.add('has-fitment-warning');
}

export function refreshFitmentWarnings(container) {
  if (!container) return;
  const vehicle = activeVehicle();
  container.querySelectorAll('.tire-card').forEach(card => applyFitmentWarning(card, vehicle));
}

export function createSingleCard(row) {
  const [
    tireId, size, diameter, brand, model, category, price, warranty, weight, tpms,
    tread, loadIndex, maxLoad, loadRange, speed, psi, utqg, tags, link, image,
    /* efficiencyScore */ , /* efficiencyGrade */ , reviewLink, /* createdAt */ ,
    roamerEfficiency, roamerTotalKm, roamerVehicleCount, roamerVehicleBreakdown, slug,
    priceSyncedAt, updatedAt, retailer
  ] = row;

  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    console.error('Invalid tire ID in card creation:', tireId);
    return null;
  }

  const ratingData = state.tireRatings[tireId] || { average: 0, count: 0 };
  const userRating = state.userRatings[tireId] || 0;
  const ratingHTML = createRatingHTML(tireId, ratingData.average, ratingData.count, userRating);

  const safeLink = safeLinkURL(link);
  const safeImage = safeImageURL(image);
  const safeReviewLink = safeReviewLinkURL(reviewLink);

  // Canonical individual tire page URL (/tires/{slug}/). Empty when the slug
  // or localized base is unavailable — callers fall back to the legacy
  // ?tire= deep link on the guide page.
  const tirePageBase = (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.tirePageUrl) ? rtgData.settings.tirePageUrl : '';
  const safeSlug = safeString(slug).trim();
  const tirePageUrl = (tirePageBase && safeSlug) ? tirePageBase + encodeURIComponent(safeSlug) + '/' : '';

  const card = document.createElement("div");
  card.className = "tire-card";
  card.dataset.tireId = tireId;
  // What the fitment warning is judged from, kept on the card so it can be
  // re-judged when the vehicle toggle changes without rebuilding the card.
  card.dataset.loadIndex = safeString(loadIndex, 20);
  card.dataset.size = safeString(size, 30);

  if (safeString(tags).toLowerCase().includes("oem")) {
    const oemBadge = document.createElement('div');
    oemBadge.className = 'tire-card-badge-oem';

    const oemInner = document.createElement('div');
    oemInner.className = 'tire-card-badge-inner';

    const oemIcon = document.createElement('span');
    oemIcon.innerHTML = rtgIcon('certificate', 14);
    oemIcon.style.display = 'inline-flex';

    oemInner.appendChild(oemIcon);
    oemInner.appendChild(document.createTextNode('OEM'));
    oemBadge.appendChild(oemInner);
    card.appendChild(oemBadge);
  }

  // Compare toggle — lives in the title row beside Share, on card color,
  // where it reads; overlaid on the tread it was a 55% black box on black.
  const compareOverlay = document.createElement('label');
  compareOverlay.className = 'tire-card-tool tire-card-tool-compare';
  // Attribute and property sinks take plain text: escapeHTML() here made a
  // screen reader announce "AT&amp;T".
  compareOverlay.setAttribute('aria-label', `Compare ${safeString(brand)} ${safeString(model)}`);

  const compareCheckbox = document.createElement('input');
  compareCheckbox.type = 'checkbox';
  compareCheckbox.className = 'compare-checkbox';
  compareCheckbox.dataset.id = tireId;
  // A selection survives re-renders and page changes: restore it from the
  // ID-keyed compare list.
  compareCheckbox.checked = state.compareList.includes(tireId);
  compareCheckbox.disabled = !compareCheckbox.checked && state.compareList.length >= 4;

  const compareIcon = document.createElement('span');
  compareIcon.className = 'tire-card-tool-icon';
  compareIcon.innerHTML = rtgIcon('scale-balanced', 14);
  compareOverlay.title = 'Add to comparison';

  compareOverlay.appendChild(compareCheckbox);
  compareOverlay.appendChild(compareIcon);

  // Share button
  const shareBtn = document.createElement('button');
  shareBtn.type = 'button';
  shareBtn.className = 'tire-card-tool tire-card-tool-share';
  shareBtn.title = 'Share';
  shareBtn.setAttribute('aria-label', `Share ${safeString(brand)} ${safeString(model)}`);
  shareBtn.innerHTML = rtgIcon('share', 16);
  shareBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    // Share the canonical tire page; fall back to the legacy ?tire= deep link.
    let shareUrl = tirePageUrl;
    if (!shareUrl) {
      const url = new URL(window.location.href);
      url.search = '';
      url.searchParams.set('tire', tireId);
      shareUrl = url.toString();
    }
    const shareTitle = `${safeString(brand)} ${safeString(model)}`;

    function showCopied() {
      shareBtn.innerHTML = rtgIcon('check', 16);
      shareBtn.classList.add('copied');
      setTimeout(() => {
        shareBtn.innerHTML = rtgIcon('share', 16);
        shareBtn.classList.remove('copied');
      }, 2000);
    }

    if (navigator.share) {
      navigator.share({ title: shareTitle, url: shareUrl }).catch(() => {});
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(shareUrl).then(showCopied);
    }
  });

  if (safeImage) {
    const imageContainer = document.createElement('div');
    imageContainer.className = 'tire-card-image';

    const img = document.createElement('img');
    img.alt = `${safeString(brand)} ${safeString(model)}`;
    img.decoding = 'async';
    img.className = 'rtg-lazy-img';
    if ('IntersectionObserver' in window) {
      img.dataset.src = safeImage;
    } else {
      img.src = safeImage;
    }
    img.onclick = () => openImageModal(safeImage, `${safeString(brand)} ${safeString(model)}`);

    imageContainer.appendChild(img);
    card.appendChild(imageContainer);
  }

  const bodyEl = document.createElement('div');
  bodyEl.className = 'tire-card-body';

  // Title row: brand + model on the left, Compare and Share on the right.
  const titleRow = document.createElement('div');
  titleRow.className = 'tire-card-title-row';

  const titleEl = document.createElement('div');
  titleEl.className = 'tire-card-title';

  const brandEl = document.createElement('div');
  brandEl.className = 'tire-card-brand';
  brandEl.textContent = safeString(brand);
  titleEl.appendChild(brandEl);

  const modelEl = document.createElement('div');
  modelEl.className = 'tire-card-model';
  // Link the title to the individual tire page (crawlable, canonical URL).
  if (tirePageUrl) {
    const modelLink = document.createElement('a');
    modelLink.className = 'tire-card-model-link';
    modelLink.href = tirePageUrl;
    modelLink.textContent = safeString(model);
    modelEl.appendChild(modelLink);
  } else {
    modelEl.textContent = safeString(model);
  }
  titleEl.appendChild(modelEl);

  const toolsEl = document.createElement('div');
  toolsEl.className = 'tire-card-tools';
  toolsEl.appendChild(compareOverlay);
  toolsEl.appendChild(shareBtn);

  titleRow.appendChild(titleEl);
  titleRow.appendChild(toolsEl);
  bodyEl.appendChild(titleRow);

  const ratingDiv = document.createElement('div');
  ratingDiv.innerHTML = ratingHTML;
  bodyEl.appendChild(ratingDiv);

  // Load-index fitment warning — filled by applyFitmentWarning() once the
  // card is in the DOM, and again whenever the vehicle toggle changes.
  const fitmentSlot = document.createElement('div');
  fitmentSlot.className = 'tire-card-fitment-slot';
  fitmentSlot.hidden = true;
  bodyEl.appendChild(fitmentSlot);

  // Key stats row — Average Price + Real-World Efficiency, elevated above
  // the spec rows (they're the top decision drivers; efficiency is also the
  // default sort). Both blocks always render so the two-up grid stays
  // consistent across cards; a missing value shows a muted "no data yet"
  // placeholder instead of collapsing to one oversized block. The row is
  // skipped entirely only when BOTH values are missing.
  const statsRow = document.createElement('div');
  statsRow.className = 'tire-card-stats';

  const priceNum = validateNumeric(price, NUMERIC_BOUNDS.price, 0);
  const roamerVal = parseFloat(roamerEfficiency);
  const hasRoamer = Number.isFinite(roamerVal) && roamerVal > 0;

  if (priceNum > 0 || hasRoamer) {
    const priceStat = document.createElement('div');
    priceStat.className = 'tire-card-stat' + (priceNum > 0 ? '' : ' tire-card-stat-empty');

    const priceLabel = document.createElement('div');
    priceLabel.className = 'tire-card-stat-label';
    priceLabel.textContent = 'Avg Price';

    const priceValue = document.createElement('div');
    priceValue.className = 'tire-card-stat-value' + (priceNum > 0 ? '' : ' tire-card-stat-value-na');

    priceStat.appendChild(priceLabel);
    priceStat.appendChild(priceValue);

    if (priceNum > 0) {
      const priceNumEl = document.createElement('span');
      priceNumEl.textContent = formatWholePrice(priceNum);
      const priceUnit = document.createElement('span');
      priceUnit.className = 'tire-card-stat-unit';
      priceUnit.textContent = 'ea';
      priceValue.appendChild(priceNumEl);
      priceValue.appendChild(priceUnit);

      // Nobody buys one tire: the set price is the number the shopper is
      // actually comparing against a budget.
      const setLine = document.createElement('div');
      setLine.className = 'tire-card-stat-meta tire-card-price-set';
      setLine.textContent = `${formatSetPrice(priceNum)} / set of ${SET_QUANTITY}`;
      priceStat.appendChild(setLine);

      // When the price was last touched, and a nudge when that was long
      // enough ago that it may no longer be what the retailer charges.
      const staleDays = (typeof rtgData !== 'undefined' && rtgData.settings) ? rtgData.settings.stalePriceDays : 0;
      const fresh = priceFreshness({ priceSyncedAt, updatedAt }, staleDays);
      if (fresh.show) {
        const asOf = document.createElement('div');
        asOf.className = 'tire-card-stat-meta tire-card-price-asof' + (fresh.stale ? ' is-stale' : '');
        asOf.textContent = fresh.stale ? `${fresh.label} · may be outdated` : fresh.label;
        if (fresh.stale) {
          asOf.title = `This price hasn't been updated in over ${staleDays} days. Check the retailer for the current price.`;
        }
        priceStat.appendChild(asOf);
      }
    } else {
      priceValue.textContent = 'No data yet';
    }

    statsRow.appendChild(priceStat);
  }

  if (priceNum > 0 && !hasRoamer) {
    // Efficiency placeholder — keeps the two-up grid without shouting:
    // the info trigger sits on the label row and the value is one quiet
    // muted line, so the placeholder recedes next to real data.
    const emptyStat = document.createElement('div');
    emptyStat.className = 'tire-card-stat tire-card-stat-empty';

    const emptyLabel = document.createElement('div');
    emptyLabel.className = 'tire-card-stat-label';

    const emptyLabelText = document.createElement('span');
    emptyLabelText.textContent = 'Efficiency';

    const emptyInfoBtn = document.createElement('button');
    emptyInfoBtn.innerHTML = '' + rtgIcon('circle-info', 12) + '';
    emptyInfoBtn.className = 'info-tooltip-trigger';
    emptyInfoBtn.dataset.tooltipKey = 'Real-World Efficiency';
    emptyInfoBtn.setAttribute('aria-label', 'More info about Real-World Efficiency');
    emptyInfoBtn.setAttribute('type', 'button');

    emptyLabel.appendChild(emptyLabelText);
    emptyLabel.appendChild(emptyInfoBtn);

    const emptyValue = document.createElement('div');
    emptyValue.className = 'tire-card-stat-value tire-card-stat-value-na';
    emptyValue.textContent = 'No data yet';

    emptyStat.appendChild(emptyLabel);
    emptyStat.appendChild(emptyValue);
    statsRow.appendChild(emptyStat);
  }

  if (hasRoamer) {
    const roamerStat = document.createElement('div');
    roamerStat.className = 'tire-card-stat tire-card-stat-roamer';

    const roamerStatLabel = document.createElement('div');
    roamerStatLabel.className = 'tire-card-stat-label';
    roamerStatLabel.textContent = 'Efficiency';

    const roamerStatValue = document.createElement('div');
    roamerStatValue.className = 'tire-card-stat-value';

    const roamerNum = document.createElement('span');
    roamerNum.textContent = roamerVal.toFixed(2);

    const roamerUnit = document.createElement('span');
    roamerUnit.className = 'tire-card-stat-unit';
    roamerUnit.textContent = 'mi/kWh';

    const roamerInfoBtn = document.createElement('button');
    roamerInfoBtn.innerHTML = '' + rtgIcon('circle-info', 14) + '';
    roamerInfoBtn.className = 'info-tooltip-trigger';
    roamerInfoBtn.dataset.tooltipKey = 'Real-World Efficiency';
    const totalMi = Math.round((parseFloat(roamerTotalKm) || 0) * 0.621371);
    const veh = parseInt(roamerVehicleCount) || 0;
    const extraParts = [];
    if (totalMi > 0) {
      extraParts.push(totalMi.toLocaleString() + ' mi tracked');
    }
    if (veh > 0) {
      extraParts.push(veh.toLocaleString() + ' vehicle' + (veh !== 1 ? 's' : ''));
    }
    if (extraParts.length > 0) {
      roamerInfoBtn.dataset.tooltipExtra = extraParts.join(' · ');
    }
    roamerInfoBtn.setAttribute('aria-label', 'More info about Real-World Efficiency');
    roamerInfoBtn.setAttribute('type', 'button');

    roamerStatValue.appendChild(roamerNum);
    roamerStatValue.appendChild(roamerUnit);
    roamerStatValue.appendChild(roamerInfoBtn);
    roamerStat.appendChild(roamerStatLabel);
    roamerStat.appendChild(roamerStatValue);

    // The sample behind the number, as meta lines — the price box grew two
    // of them (set price, "as of"), and a bare figure beside three lines
    // left the efficiency box mostly empty. The same facts still ride the
    // tooltip for anyone who opens it.
    let lastMetaLine = null;
    extraParts.forEach(part => {
      const line = document.createElement('div');
      line.className = 'tire-card-stat-meta';
      line.textContent = part;
      roamerStat.appendChild(line);
      lastMetaLine = line;
    });

    // A figure from one vehicle over a few hundred miles reads with the
    // same confidence as one from sixty over sixty thousand. Mute it and
    // say so when the sample is thin — on the last sample line, so the box
    // stays two lines tall like the price box beside it.
    if (isLimitedSample(roamerTotalKm ? parseFloat(roamerTotalKm) * 0.621371 : 0, veh)) {
      roamerStat.classList.add('is-limited');
      if (!lastMetaLine) {
        lastMetaLine = document.createElement('div');
        lastMetaLine.className = 'tire-card-stat-meta';
        roamerStat.appendChild(lastMetaLine);
      }
      const note = document.createElement('span');
      note.className = 'tire-card-stat-limited';
      note.textContent = (lastMetaLine.textContent ? ' · ' : '') + 'Limited data';
      note.title = 'Too few vehicles or miles behind this figure to rely on it yet.';
      lastMetaLine.appendChild(note);
      roamerInfoBtn.dataset.tooltipExtra = (roamerInfoBtn.dataset.tooltipExtra ? roamerInfoBtn.dataset.tooltipExtra + ' · ' : '') + 'limited data so far';
    }

    statsRow.appendChild(roamerStat);
  }

  if (statsRow.children.length > 0) {
    bodyEl.appendChild(statsRow);
  }

  // Tags row — kept for future pills; only appended when populated.
  const tagsContainer = document.createElement('div');
  tagsContainer.className = 'tire-card-tags';

  if (tagsContainer.children.length > 0) {
    bodyEl.appendChild(tagsContainer);
  }

  const specsContainer = document.createElement('div');
  specsContainer.className = 'tire-card-specs';

  // Specs shown on the default card view — only the decision drivers.
  // Category, Load Index, Speed Rating, UTQG, tread depth, max load, load
  // range, and max PSI are intentionally not on the card: they're secondary
  // or cryptic when scanning a grid, and the full spec sheet lives one click
  // away on the individual tire page (plus the compare page, admin form, and
  // CSV import/export). Average Price moved up into the key-stats row.
  // Note: row values are strings, so "0" is truthy — compare the parsed
  // number instead, or missing data renders as "0 miles" / "0 lb".
  const warrantyNum = Number(validateNumeric(warranty, NUMERIC_BOUNDS.warranty, 0));
  const weightNum = Number(validateNumeric(weight, NUMERIC_BOUNDS.weight, 0));
  // 3PMS left the rows for the chips: a boolean that mostly read "No" was
  // spending a full row, and the tire page already shows it as a chip.
  const specs = [
    ['Size', `${safeString(size)} (${safeString(diameter)}${safeString(diameter) && !safeString(diameter).includes('"') ? '"' : ''})`],
    ['Mileage Warranty', warrantyNum > 0 ? `${warrantyNum.toLocaleString()} miles` : 'Not listed'],
    ['Weight', weightNum > 0 ? `${weightNum} lb` : 'Not listed']
  ];

  specs.forEach(([label, value]) => {
    const specRow = document.createElement('div');
    specRow.className = 'tire-card-spec';

    const hasTooltip = TOOLTIP_DATA.hasOwnProperty(label);

    let labelEl;
    if (hasTooltip) {
      labelEl = createInfoTooltip(label, label);
    } else {
      labelEl = document.createElement('span');
      labelEl.className = 'tire-card-spec-label';
      labelEl.textContent = label;
    }

    const valueEl = document.createElement('span');
    valueEl.className = 'tire-card-spec-value';
    valueEl.textContent = value || '-';

    specRow.appendChild(labelEl);
    specRow.appendChild(valueEl);
    specsContainer.appendChild(specRow);
  });

  bodyEl.appendChild(specsContainer);

  // Chips — which Rivian it fits, category, 3PMS, tags (matches the tire
  // page's chip treatment). Each chip is { text, cls, icon }.
  const chips = [];

  // Which Rivian takes this size: in the "All" view nothing else says so.
  const sizeKey = safeString(size).trim().toLowerCase();
  const sizeMap = (state.vehicleSizeMap && Object.keys(state.vehicleSizeMap).length)
    ? state.vehicleSizeMap
    : ((typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.vehicleSizeMap) || {});
  Object.keys(sizeMap).sort().forEach(vehicle => {
    const sizes = Array.isArray(sizeMap[vehicle]) ? sizeMap[vehicle] : [];
    if (sizes.some(v => String(v).trim().toLowerCase() === sizeKey)) {
      chips.push({ text: `Fits ${vehicle}`, cls: 'tire-card-chip-vehicle', icon: 'car' });
    }
  });

  if (safeString(category).trim()) {
    chips.push({ text: safeString(category).trim(), cls: '' });
  }
  if (safeString(tpms).toLowerCase().includes('yes')) {
    chips.push({ text: '3PMS Rated', cls: 'tire-card-chip-3pms', icon: 'snowflake' });
  }
  if (tags && safeString(tags).trim()) {
    safeString(tags).split(/[,|]/).map(tag => tag.trim())
      .filter(tag => tag && !HIDDEN_TAGS.has(tag.toLowerCase()))
      .forEach(tag => chips.push({ text: safeString(tag, 30), cls: '' }));
  }
  if (chips.length > 0) {
    const chipsRow = document.createElement('div');
    chipsRow.className = 'tire-card-chips';
    chips.forEach(chip => {
      const chipEl = document.createElement('span');
      chipEl.className = 'tire-card-chip' + (chip.cls ? ' ' + chip.cls : '');
      if (chip.icon) {
        const iconEl = document.createElement('span');
        iconEl.className = 'tire-card-chip-icon';
        iconEl.innerHTML = rtgIcon(chip.icon, 10);
        chipEl.appendChild(iconEl);
      }
      chipEl.appendChild(document.createTextNode(chip.text));
      chipsRow.appendChild(chipEl);
    });
    bodyEl.appendChild(chipsRow);
  }

  card.appendChild(bodyEl);

  const actionsContainer = document.createElement('div');
  actionsContainer.className = 'tire-card-actions';

  if (safeLink) {
    const viewButton = document.createElement('a');
    viewButton.href = safeLink;
    viewButton.target = '_blank';
    viewButton.rel = 'noopener noreferrer';
    viewButton.className = 'tire-card-cta tire-card-cta-primary';
    // "View at Tire Rack": say where the click goes. The label is resolved
    // server-side (RTG_Retailer) and rides the row at index 31.
    const retailerName = safeString(retailer, 40);
    viewButton.textContent = retailerName ? `View at ${retailerName}` : 'View Tire';
    viewButton.insertAdjacentHTML('beforeend', '&nbsp;' + rtgIcon('arrow-up-right', 14));
    actionsContainer.appendChild(viewButton);
  } else {
    const comingSoon = document.createElement('span');
    comingSoon.className = 'tire-card-cta tire-card-cta-disabled';
    comingSoon.textContent = 'Coming Soon';
    actionsContainer.appendChild(comingSoon);
  }

  // Link row below the CTA: internal tire-page link + demoted official
  // review link (was a second full-width button competing with the CTA).
  const linksRow = document.createElement('div');
  linksRow.className = 'tire-card-links';

  if (tirePageUrl) {
    const detailsLink = document.createElement('a');
    detailsLink.className = 'tire-card-details-link';
    detailsLink.href = tirePageUrl;
    detailsLink.innerHTML = 'Full Specs &amp; Reviews&nbsp;' + rtgIcon('arrow-right', 12);
    linksRow.appendChild(detailsLink);
  }

  if (safeReviewLink) {
    let isVideo = false;
    try {
      const urlObj = new URL(safeReviewLink);
      const hostname = urlObj.hostname.toLowerCase();
      const isYouTube =
        hostname === 'youtube.com' ||
        hostname.endsWith('.youtube.com') ||
        hostname === 'youtu.be';
      const isTikTok =
        hostname === 'tiktok.com' ||
        hostname.endsWith('.tiktok.com');
      isVideo = isYouTube || isTikTok;
    } catch (e) {
      isVideo = false;
    }
    const reviewEl = document.createElement('a');
    reviewEl.href = safeReviewLink;
    reviewEl.target = '_blank';
    reviewEl.rel = 'noopener noreferrer';
    reviewEl.className = 'tire-card-review-link';
    reviewEl.innerHTML = rtgIcon(isVideo ? 'circle-play' : 'newspaper', 12) + '&nbsp;' + (isVideo ? 'Review Video' : 'Official Review');
    linksRow.appendChild(reviewEl);
  }

  if (linksRow.children.length > 0) {
    actionsContainer.appendChild(linksRow);
  }

  card.appendChild(actionsContainer);

  return card;
}

export function preloadNextPageImages() {
  const totalPages = Math.ceil(state.filteredRows.length / ROWS_PER_PAGE);

  if (state.currentPage >= totalPages) {
    return;
  }

  const nextStart = state.currentPage * ROWS_PER_PAGE;
  const nextRows = state.filteredRows.slice(nextStart, nextStart + ROWS_PER_PAGE);

  nextRows.forEach(row => {
    const image = row[19];
    const safeImage = safeImageURL(image);
    if (safeImage) {
      const img = new Image();
      img.src = safeImage;
    }
  });
}
