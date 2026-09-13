/* jshint esversion: 11 */

/**
 * Rating system: loading ratings for the cards, drawing the stars and
 * posting a rating. Writing a review happens on the review page.
 */

import { state } from './state.js';
import { starSVGMarkup } from './helpers.js';
import { VALIDATION_PATTERNS, NUMERIC_BOUNDS, validateNumeric } from './validation.js';

// Resolvers for every loadTireRatings() call awaiting the current debounced
// flush — settled together when it completes (or fails, or can't run).
const pendingRatingResolvers = [];

export function loadTireRatings(tireIds) {
  if (!tireIds.length) return Promise.resolve();

  const validTireIds = tireIds.filter(id =>
    typeof id === 'string' &&
    VALIDATION_PATTERNS.tireId.test(id) &&
    id.length <= 50
  );

  if (!validTireIds.length) return Promise.resolve();

  state.ratingRequestQueue.push(...validTireIds);

  if (state.ratingRequestTimeout) {
    clearTimeout(state.ratingRequestTimeout);
  }

  return new Promise((resolve) => {
    // Calls inside the debounce window share one flush. Every caller's
    // resolver is kept and settled together — clearing the previous timer
    // must never orphan an earlier caller's promise, or the render pipeline
    // awaiting it (sort-by-rating) hangs forever.
    pendingRatingResolvers.push(resolve);

    state.ratingRequestTimeout = setTimeout(() => {
      const settleAll = () => {
        pendingRatingResolvers.splice(0).forEach(fn => fn());
      };

      const uniqueIds = [...new Set(state.ratingRequestQueue)];
      state.ratingRequestQueue = [];

      if (typeof tireRatingAjax !== 'undefined') {
        state.isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
      }

      if (typeof tireRatingAjax === 'undefined') {
        console.warn('WordPress rating system not available');
        settleAll();
        return;
      }

      const formData = new FormData();
      formData.append('action', 'get_tire_ratings');

      if (tireRatingAjax.nonce) {
        formData.append('nonce', tireRatingAjax.nonce);
      }

      uniqueIds.forEach(tireId => {
        formData.append('tire_ids[]', tireId);
      });

      fetch(tireRatingAjax.ajaxurl, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          state.tireRatings = { ...state.tireRatings, ...data.data.ratings };
          state.userRatings = { ...state.userRatings, ...data.data.user_ratings };
          if (data.data.user_reviews) {
            state.userReviews = { ...state.userReviews, ...data.data.user_reviews };
          }
          state.isLoggedIn = data.data.is_logged_in === true || data.data.is_logged_in === '1' || data.data.is_logged_in === 1;
        }
        settleAll();
      })
      .catch(error => {
        console.error('Error loading tire ratings:', error);
        settleAll();
      });
    }, 50);
  });
}

export function submitTireRating(tireId, rating, reviewTitle = '', reviewText = '') {
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    console.error('Invalid tire ID format');
    return Promise.reject('Invalid tire ID');
  }

  const validRating = validateNumeric(rating, NUMERIC_BOUNDS.rating);
  if (validRating !== rating) {
    console.error('Invalid rating value');
    return Promise.reject('Invalid rating');
  }

  if (!state.isLoggedIn) {
    return Promise.reject('Not logged in — use submitGuestTireRating instead');
  }

  if (typeof tireRatingAjax === 'undefined' || !tireRatingAjax.nonce) {
    console.error('Missing security nonce');
    return Promise.reject('Security validation failed');
  }

  const formData = new FormData();
  formData.append('action', 'submit_tire_rating');
  formData.append('tire_id', tireId);
  formData.append('rating', validRating.toString());
  formData.append('nonce', tireRatingAjax.nonce);

  if (reviewTitle) {
    formData.append('review_title', reviewTitle.substring(0, 200));
  }
  if (reviewText) {
    formData.append('review_text', reviewText.substring(0, 5000));
  }

  return fetch(tireRatingAjax.ajaxurl, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      state.tireRatings[tireId] = {
        average: validateNumeric(data.data.average_rating, { min: 0, max: 5 }),
        count: validateNumeric(data.data.rating_count, { min: 0, max: 10000 }),
        review_count: validateNumeric(data.data.review_count, { min: 0, max: 10000 })
      };
      state.userRatings[tireId] = validateNumeric(data.data.user_rating, NUMERIC_BOUNDS.rating);
      if (reviewText) {
        state.userReviews[tireId] = { rating: validRating, review_title: reviewTitle, review_text: reviewText };
      }

      updateRatingDisplay(tireId);

      return data.data;
    } else {
      throw new Error(data.data || 'Failed to save review');
    }
  });
}

export function submitGuestTireRating(tireId, rating, guestName, guestEmail, reviewTitle = '', reviewText = '', honeypot = '') {
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    return Promise.reject('Invalid tire ID');
  }

  const validRating = validateNumeric(rating, NUMERIC_BOUNDS.rating);
  if (validRating !== rating) {
    return Promise.reject('Invalid rating');
  }

  if (!guestName || !guestName.trim()) {
    return Promise.reject('Name is required');
  }

  if (!guestEmail || !guestEmail.trim()) {
    return Promise.reject('Email is required');
  }

  if (!reviewTitle.trim() && !reviewText.trim()) {
    return Promise.reject('Please write a review title or body text');
  }

  if (typeof tireRatingAjax === 'undefined' || !tireRatingAjax.nonce) {
    return Promise.reject('Security validation failed');
  }

  const formData = new FormData();
  formData.append('action', 'submit_guest_tire_rating');
  formData.append('tire_id', tireId);
  formData.append('rating', validRating.toString());
  formData.append('guest_name', guestName.trim().substring(0, 100));
  formData.append('guest_email', guestEmail.trim().substring(0, 254));
  formData.append('nonce', tireRatingAjax.nonce);

  if (reviewTitle) {
    formData.append('review_title', reviewTitle.substring(0, 200));
  }
  if (reviewText) {
    formData.append('review_text', reviewText.substring(0, 5000));
  }
  // Honeypot field — should be empty for real users.
  formData.append('website', honeypot);

  return fetch(tireRatingAjax.ajaxurl, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      return data.data;
    } else {
      throw new Error(data.data || 'Failed to submit review');
    }
  });
}

function deleteTireRating(tireId) {
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    return Promise.reject('Invalid tire ID');
  }
  if (!state.isLoggedIn || typeof tireRatingAjax === 'undefined' || !tireRatingAjax.nonce) {
    return Promise.reject('Not logged in');
  }

  const formData = new FormData();
  formData.append('action', 'delete_tire_rating');
  formData.append('tire_id', tireId);
  formData.append('nonce', tireRatingAjax.nonce);

  return fetch(tireRatingAjax.ajaxurl, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      state.tireRatings[tireId] = {
        average: validateNumeric(data.data.average_rating, { min: 0, max: 5 }),
        count: validateNumeric(data.data.rating_count, { min: 0, max: 10000 }),
        review_count: validateNumeric(data.data.review_count, { min: 0, max: 10000 })
      };
      delete state.userRatings[tireId];
      delete state.userReviews[tireId];
      updateRatingDisplay(tireId);
      return data.data;
    } else {
      throw new Error(data.data || 'Failed to delete rating');
    }
  });
}

// Resolve the canonical tire-page URL for a tire from the localized base +
// the row's slug (index 26). Returns '' when either is unavailable.
function tirePageUrlFor(tireId) {
  const base = (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.tirePageUrl) ? rtgData.settings.tirePageUrl : '';
  if (!base || !Array.isArray(state.allRows)) return '';
  const row = state.allRows.find(r => Array.isArray(r) && r[0] === tireId);
  const slug = row && typeof row[26] === 'string' ? row[26].trim() : '';
  return slug ? base + encodeURIComponent(slug) + '/' : '';
}

export function createRatingHTML(tireId, average = 0, count = 0, userRating = 0) {
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    console.error('Invalid tire ID in rating creation');
    return '<div>Error: Invalid tire data</div>';
  }

  const displayAverage = validateNumeric(average, { min: 0, max: 5 }, 0);
  const displayCount = validateNumeric(count, { min: 0, max: 10000 }, 0);
  const validUserRating = (userRating && userRating > 0) ? validateNumeric(userRating, NUMERIC_BOUNDS.rating, 0) : 0;

  const isInteractive = true;
  const isHighRating = displayAverage >= 4.5 && displayCount >= 2;

  const container = document.createElement('div');
  container.className = 'tire-rating-container';
  container.dataset.tireId = tireId;
  if (isHighRating) {
    container.dataset.highRating = 'true';
  }

  const ratingDisplay = document.createElement('div');
  ratingDisplay.className = 'rating-display';

  const starsContainer = document.createElement('div');
  starsContainer.className = `rating-stars ${isInteractive ? 'interactive' : ''} ${validUserRating > 0 ? 'has-user-rating' : ''}`;
  starsContainer.dataset.tireId = tireId;
  starsContainer.setAttribute('role', isInteractive ? 'radiogroup' : 'img');
  starsContainer.setAttribute('aria-label', displayAverage > 0 ? `Rating: ${displayAverage.toFixed(1)} out of 5 stars` : 'No reviews yet');

  const roundedAvg = displayAverage > 0 ? Math.round(displayAverage * 2) / 2 : 0;

  for (let i = 1; i <= 5; i++) {
    const star = document.createElement('span');
    star.className = 'star';
    star.dataset.rating = i.toString();
    star.dataset.tireId = tireId;
    star.innerHTML = starSVGMarkup(26);

    if (roundedAvg >= i) {
      star.classList.add('active');
    } else if (roundedAvg >= i - 0.5) {
      star.classList.add('active', 'half');
    }

    if (validUserRating > 0 && i <= validUserRating) {
      star.classList.add('user-rated');
    }
    if (isInteractive) {
      star.style.cursor = 'pointer';
      star.setAttribute('role', 'radio');
      star.setAttribute('aria-checked', validUserRating === i ? 'true' : 'false');
      star.setAttribute('aria-label', `${i} star${i !== 1 ? 's' : ''}`);
      star.setAttribute('tabindex', i === (validUserRating || 1) ? '0' : '-1');
    } else {
      star.setAttribute('aria-hidden', 'true');
    }

    starsContainer.appendChild(star);
  }

  const ratingInfo = document.createElement('div');
  ratingInfo.className = 'rating-info';

  const averageSpan = document.createElement('span');
  averageSpan.className = 'rating-average';
  averageSpan.textContent = displayAverage > 0 ? displayAverage.toFixed(1) : 'No reviews';

  ratingInfo.appendChild(averageSpan);

  // Review count as inline text next to the rating (matches the tire page's
  // "5.0 · 1 rating" pattern). Links to the tire page's Owner Reviews
  // section — the reviews drawer was removed in 1.56.0.
  const reviewCount = state.tireRatings[tireId]?.review_count || 0;
  if (reviewCount > 0) {
    const sep = document.createElement('span');
    sep.className = 'rating-count-sep';
    sep.setAttribute('aria-hidden', 'true');
    sep.textContent = '·';
    ratingInfo.appendChild(sep);

    const label = `${reviewCount} review${reviewCount !== 1 ? 's' : ''}`;
    const pageUrl = tirePageUrlFor(tireId);
    if (pageUrl) {
      const countLink = document.createElement('a');
      countLink.className = 'rating-review-count';
      countLink.href = pageUrl + '#rtg-tp-reviews';
      countLink.textContent = label;
      ratingInfo.appendChild(countLink);
    } else {
      const countSpan = document.createElement('span');
      countSpan.className = 'rating-review-count rating-review-count-static';
      countSpan.textContent = label;
      ratingInfo.appendChild(countSpan);
    }
  }

  ratingDisplay.appendChild(starsContainer);
  ratingDisplay.appendChild(ratingInfo);
  container.appendChild(ratingDisplay);

  // The Write/Edit Review pill was removed from cards (1.55.2) and the
  // review-count pill moved inline next to the stars (1.55.3): this row now
  // only carries the guest "Review Pending" badge, and is skipped otherwise.
  if (!state.isLoggedIn && state.guestPendingReviews && state.guestPendingReviews.has(tireId)) {
    const reviewActions = document.createElement('div');
    reviewActions.className = 'review-actions';

    const pendingBadge = document.createElement('span');
    pendingBadge.className = 'review-action-link rtg-review-pending-badge';
    pendingBadge.textContent = 'Review Pending';
    reviewActions.appendChild(pendingBadge);

    container.appendChild(reviewActions);
  }

  return container.outerHTML;
}

export function updateRatingDisplay(tireId) {
  if (!VALIDATION_PATTERNS.tireId.test(tireId)) {
    console.error('Invalid tire ID in rating update');
    return;
  }

  const container = document.querySelector(`[data-tire-id="${CSS.escape(tireId)}"] .tire-rating-container`);
  if (!container) return;

  const ratingData = state.tireRatings[tireId] || { average: 0, count: 0 };
  const userRating = state.userRatings[tireId] || 0;

  container.outerHTML = createRatingHTML(tireId, ratingData.average, ratingData.count, userRating);
}

// ── Review Modal ──
