/* jshint esversion: 11 */

/**
 * Card rendering — create, update, and manage tire cards.
 */

import { state, ROWS_PER_PAGE } from './state.js';
import { rtgColor, rtgIcon, escapeHTML, safeString, getDOMElement } from './helpers.js';
import { VALIDATION_PATTERNS, NUMERIC_BOUNDS, validateNumeric, safeImageURL, safeLinkURL, safeReviewLinkURL } from './validation.js';
import { TOOLTIP_DATA, createInfoTooltip } from './tooltips.js';
import { createRatingHTML } from './ratings.js';
import { toggleFavorite } from './favorites.js';
import { setupCompareCheckboxes } from './compare.js';
import { openImageModal } from './image-modal.js';

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
      const row = rows.find(r => r[0] === tireId);
      if (row) {
        const checkbox = card.querySelector('.compare-checkbox');
        if (checkbox) {
          checkbox.dataset.index = state.allRows.indexOf(row);
        }
      }
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

  // Trigger IntersectionObserver for lazy-loaded images
  if (state.cardContainer) observeCardImages(state.cardContainer);
}

export function createSingleCard(row) {
  const [
    tireId, size, diameter, brand, model, category, price, warranty, weight, tpms,
    tread, loadIndex, maxLoad, loadRange, speed, psi, utqg, tags, link, image,
    efficiencyScore, efficiencyGrade, reviewLink
  ] = row;

  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    console.error('Invalid tire ID in card creation:', tireId);
    return null;
  }

  const cacheKey = `card_${tireId}_${Date.now() % 10000}`;

  const ratingData = state.tireRatings[tireId] || { average: 0, count: 0 };
  const userRating = state.userRatings[tireId] || 0;
  const ratingHTML = createRatingHTML(tireId, ratingData.average, ratingData.count, userRating);

  const safeLink = safeLinkURL(link);
  const safeImage = safeImageURL(image);
  const safeReviewLink = safeReviewLinkURL(reviewLink);

  const card = document.createElement("div");
  card.className = "tire-card";
  card.dataset.tireId = tireId;

  if (safeString(tags).toLowerCase().includes("reviewed")) {
    const badge = document.createElement('div');
    badge.className = 'tire-card-badge';

    const badgeInner = document.createElement('div');
    badgeInner.className = 'tire-card-badge-inner';

    const icon = document.createElement('span');
    icon.innerHTML = rtgIcon('circle-check', 14);
    icon.style.display = 'inline-flex';

    badgeInner.appendChild(icon);
    badgeInner.appendChild(document.createTextNode('Reviewed'));
    badge.appendChild(badgeInner);
    card.appendChild(badge);
  }

  // Compare checkbox overlay
  const compareOverlay = document.createElement('label');
  compareOverlay.className = 'tire-card-compare-overlay';
  compareOverlay.setAttribute('aria-label', `Compare ${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`);

  const compareCheckbox = document.createElement('input');
  compareCheckbox.type = 'checkbox';
  compareCheckbox.className = 'compare-checkbox';
  compareCheckbox.dataset.id = tireId;
  compareCheckbox.dataset.index = state.allRows.indexOf(row).toString();

  const compareIcon = document.createElement('span');
  compareIcon.className = 'compare-overlay-icon';

  compareOverlay.appendChild(compareCheckbox);
  compareOverlay.appendChild(compareIcon);

  // Favorite button overlay
  const favBtn = document.createElement('button');
  favBtn.className = 'tire-card-fav-btn';
  favBtn.dataset.tireId = tireId;
  const isFav = state.userFavorites.has(tireId);
  favBtn.classList.toggle('is-favorite', isFav);
  favBtn.setAttribute('aria-label', isFav ? 'Remove from favorites' : 'Add to favorites');
  favBtn.innerHTML = isFav
    ? rtgIcon('heart', 16)
    : rtgIcon('heart-outline', 16);
  favBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleFavorite(tireId);
  });

  // Share button overlay
  const shareBtn = document.createElement('button');
  shareBtn.className = 'tire-card-share-btn';
  shareBtn.setAttribute('aria-label', `Share ${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`);
  shareBtn.innerHTML = rtgIcon('share', 16);
  shareBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const url = new URL(window.location.href);
    url.search = '';
    url.searchParams.set('tire', tireId);
    const shareUrl = url.toString();
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
    img.alt = `${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`;
    img.decoding = 'async';
    img.className = 'rtg-lazy-img';
    if ('IntersectionObserver' in window) {
      img.dataset.src = safeImage;
    } else {
      img.src = safeImage;
    }
    img.onclick = () => openImageModal(safeImage, `${escapeHTML(safeString(brand))} ${escapeHTML(safeString(model))}`);

    imageContainer.appendChild(img);
    imageContainer.appendChild(compareOverlay);
    imageContainer.appendChild(favBtn);
    imageContainer.appendChild(shareBtn);
    card.appendChild(imageContainer);
  } else {
    card.appendChild(compareOverlay);
    card.appendChild(favBtn);
    card.appendChild(shareBtn);
  }

  const bodyEl = document.createElement('div');
  bodyEl.className = 'tire-card-body';

  const brandEl = document.createElement('div');
  brandEl.className = 'tire-card-brand';
  brandEl.textContent = safeString(brand);
  bodyEl.appendChild(brandEl);

  const modelEl = document.createElement('div');
  modelEl.className = 'tire-card-model';
  modelEl.textContent = safeString(model);
  bodyEl.appendChild(modelEl);

  const ratingDiv = document.createElement('div');
  ratingDiv.innerHTML = ratingHTML;
  bodyEl.appendChild(ratingDiv);

  // Tags (efficiency grade and others)
  const tagsContainer = document.createElement('div');
  tagsContainer.className = 'tire-card-tags';

  if (efficiencyGrade) {
    const grade = safeString(efficiencyGrade).trim().toUpperCase();
    if (['A', 'B', 'C', 'D', 'F'].includes(grade)) {
      const gradeColor = {
        A: "#5ec095", B: "#a3e635", C: "#facc15",
        D: "#f97316", F: "#b91c1c"
      }[grade];

      const gradeTag = document.createElement('span');
      gradeTag.className = 'tire-card-eff';

      const gradeLabel = document.createElement('span');
      gradeLabel.className = 'tire-card-eff-grade';
      gradeLabel.style.backgroundColor = gradeColor;
      gradeLabel.textContent = grade;

      const scoreSection = document.createElement('span');
      scoreSection.className = 'tire-card-eff-score';

      const scoreText = document.createElement('span');
      scoreText.textContent = `Efficiency (${escapeHTML(safeString(efficiencyScore))}/100)`;

      const infoButton = document.createElement('button');
      infoButton.innerHTML = '' + rtgIcon('circle-info', 12) + '';
      infoButton.className = 'info-tooltip-trigger';
      infoButton.dataset.tooltipKey = 'Efficiency Score';
      infoButton.style.cssText = `
        background: none;
        border: none;
        color: var(--rtg-text-muted);
        font-size: 12px;
        cursor: pointer;
        padding: 1px;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
      `;

      infoButton.addEventListener('mouseenter', () => {
        infoButton.style.color = rtgColor('accent');
        infoButton.style.backgroundColor = `color-mix(in srgb, ${rtgColor('accent')} 10%, transparent)`;
      });

      infoButton.addEventListener('mouseleave', () => {
        infoButton.style.color = rtgColor('text-muted');
        infoButton.style.backgroundColor = 'transparent';
      });

      scoreSection.appendChild(scoreText);
      scoreSection.appendChild(infoButton);

      gradeTag.appendChild(gradeLabel);
      gradeTag.appendChild(scoreSection);
      tagsContainer.appendChild(gradeTag);
    }
  }

  if (tags && safeString(tags).trim()) {
    const tagList = safeString(tags).split(/[,|]/).map(tag => tag.trim()).filter(tag => tag && tag.toLowerCase() !== 'reviewed');

    tagList.forEach(tag => {
      const tagEl = document.createElement('span');
      tagEl.className = 'tire-card-tag';
      tagEl.textContent = safeString(tag, 30);
      tagsContainer.appendChild(tagEl);
    });
  }

  if (tagsContainer.children.length > 0) {
    bodyEl.appendChild(tagsContainer);
  }

  const specsContainer = document.createElement('div');
  specsContainer.className = 'tire-card-specs';

  const specs = [
    ['Size', `${safeString(size)} (${safeString(diameter)}${safeString(diameter) && !safeString(diameter).includes('"') ? '"' : ''})`],
    ['Category', safeString(category)],
    ['Average Price', price ? `$${validateNumeric(price, NUMERIC_BOUNDS.price)}` : '-'],
    ['Mileage Warranty', warranty ? `${Number(validateNumeric(warranty, NUMERIC_BOUNDS.warranty)).toLocaleString()} miles` : '-'],
    ['Weight', weight ? `${validateNumeric(weight, NUMERIC_BOUNDS.weight)} lb` : '-'],
    ['3PMS Rated', safeString(tpms)],
    ['Tread Depth', safeString(tread)],
    ['Load Index', safeString(loadIndex)],
    ['Max Load', maxLoad ? `${validateNumeric(maxLoad, { min: 0, max: 10000 })} lb` : '-'],
    ['Load Range', safeString(loadRange)],
    ['Speed Rating', safeString(speed)],
    ['Max PSI', safeString(psi)],
    ['UTQG', safeString(utqg) || 'None']
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
  card.appendChild(bodyEl);

  const actionsContainer = document.createElement('div');
  actionsContainer.className = 'tire-card-actions';

  if (safeLink) {
    const viewButton = document.createElement('a');
    viewButton.href = safeLink;
    viewButton.target = '_blank';
    viewButton.rel = 'noopener noreferrer';
    viewButton.className = 'tire-card-cta tire-card-cta-primary';
    viewButton.innerHTML = 'View Tire&nbsp;' + rtgIcon('arrow-up-right', 14);
    actionsContainer.appendChild(viewButton);
  } else {
    const comingSoon = document.createElement('span');
    comingSoon.className = 'tire-card-cta tire-card-cta-disabled';
    comingSoon.textContent = 'Coming Soon';
    actionsContainer.appendChild(comingSoon);
  }

  if (safeReviewLink) {
    const reviewButton = document.createElement('a');
    reviewButton.href = safeReviewLink;
    reviewButton.target = '_blank';
    reviewButton.rel = 'noopener noreferrer';
    reviewButton.className = 'tire-card-cta tire-card-cta-review';
    const isVideo = safeReviewLink.includes('youtube.com') || safeReviewLink.includes('youtu.be') || safeReviewLink.includes('tiktok.com');
    const iconName = isVideo ? 'circle-play' : 'newspaper';
    const label = isVideo ? 'Watch Official Review' : 'Read Official Review';
    reviewButton.innerHTML = `${label}&nbsp;${rtgIcon(iconName, 14)}`;
    actionsContainer.appendChild(reviewButton);
  }

  card.appendChild(actionsContainer);

  if (state.cardCache.size > 100) {
    const firstKey = state.cardCache.keys().next().value;
    state.cardCache.delete(firstKey);
  }
  state.cardCache.set(cacheKey, card.cloneNode(true));

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
