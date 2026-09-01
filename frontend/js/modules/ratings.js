/* jshint esversion: 11 */

/**
 * Rating system, review modal, reviews drawer, and toast notifications.
 */

import { state } from './state.js';
import { rtgColor, starSVGMarkup, getDOMElement } from './helpers.js';
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
// the row's slug (index 28). Returns '' when either is unavailable.
function tirePageUrlFor(tireId) {
  const base = (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.tirePageUrl) ? rtgData.settings.tirePageUrl : '';
  if (!base || !Array.isArray(state.allRows)) return '';
  const row = state.allRows.find(r => Array.isArray(r) && r[0] === tireId);
  const slug = row && typeof row[28] === 'string' ? row[28].trim() : '';
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

export function openReviewModal(tireId, preselectedRating = 0) {
  const existing = document.getElementById('rtg-review-modal');
  if (existing) existing.remove();

  const existingReview = state.userReviews[tireId] || {};
  const currentRating = preselectedRating || existingReview.rating || 0;
  const currentTitle = existingReview.review_title || '';
  const currentText = existingReview.review_text || '';

  const card = document.querySelector(`[data-tire-id="${CSS.escape(tireId)}"].tire-card`);
  const brand = card ? card.querySelector('.tire-card-brand')?.textContent || '' : '';
  const model = card ? card.querySelector('.tire-card-model')?.textContent || '' : '';

  const overlay = document.createElement('div');
  overlay.id = 'rtg-review-modal';
  overlay.className = 'rtg-review-modal-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-label', 'Write a review');

  const modal = document.createElement('div');
  modal.className = 'rtg-review-modal';

  const header = document.createElement('div');
  header.className = 'rtg-review-modal-header';

  const titleEl = document.createElement('h3');
  titleEl.textContent = brand && model ? `Review ${brand} ${model}` : 'Write a Review';

  const closeBtn = document.createElement('button');
  closeBtn.className = 'rtg-review-modal-close';
  closeBtn.setAttribute('aria-label', 'Close');
  closeBtn.innerHTML = '&times;';

  header.appendChild(titleEl);
  header.appendChild(closeBtn);

  const starSection = document.createElement('div');
  starSection.className = 'rtg-review-modal-stars';

  const starLabel = document.createElement('label');
  starLabel.textContent = 'Your Rating';

  const starsRow = document.createElement('div');
  starsRow.className = 'rtg-review-stars-select';
  starsRow.setAttribute('role', 'radiogroup');
  starsRow.setAttribute('aria-label', 'Select rating');

  let selectedRating = currentRating;

  for (let i = 1; i <= 5; i++) {
    const star = document.createElement('span');
    star.className = 'rtg-review-star' + (i <= selectedRating ? ' selected' : '');
    star.dataset.value = i;
    star.innerHTML = starSVGMarkup(40);
    star.setAttribute('role', 'radio');
    star.setAttribute('aria-checked', i === selectedRating ? 'true' : 'false');
    star.setAttribute('aria-label', `${i} star${i !== 1 ? 's' : ''}`);
    star.setAttribute('tabindex', i === (selectedRating || 1) ? '0' : '-1');
    starsRow.appendChild(star);
  }

  const ratingText = document.createElement('span');
  ratingText.className = 'rtg-review-rating-text';
  const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
  ratingText.textContent = selectedRating > 0 ? ratingLabels[selectedRating] : 'Select a rating';

  starSection.appendChild(starLabel);
  starSection.appendChild(starsRow);
  starSection.appendChild(ratingText);

  starsRow.addEventListener('click', (e) => {
    const star = e.target.closest('.rtg-review-star');
    if (!star) return;
    selectedRating = parseInt(star.dataset.value);
    starsRow.querySelectorAll('.rtg-review-star').forEach((s, idx) => {
      s.classList.toggle('selected', idx < selectedRating);
      s.setAttribute('aria-checked', idx + 1 === selectedRating ? 'true' : 'false');
    });
    ratingText.textContent = ratingLabels[selectedRating] || '';
  });

  starsRow.addEventListener('mouseenter', (e) => {
    const star = e.target.closest('.rtg-review-star');
    if (!star) return;
    const val = parseInt(star.dataset.value);
    starsRow.querySelectorAll('.rtg-review-star').forEach((s, idx) => {
      s.classList.toggle('hovered', idx < val);
    });
  }, true);

  starsRow.addEventListener('mouseleave', () => {
    starsRow.querySelectorAll('.rtg-review-star').forEach(s => s.classList.remove('hovered'));
  }, true);

  const isGuest = !state.isLoggedIn;

  // Guest identity fields (name, email, honeypot) — created here, appended in order below.
  let nameInput, emailInput, honeypotInput, guestNameSection, guestEmailSection;
  if (isGuest) {
    guestNameSection = document.createElement('div');
    guestNameSection.className = 'rtg-review-field';

    const nameLabel = document.createElement('label');
    nameLabel.textContent = 'Your Name';
    nameLabel.setAttribute('for', 'rtg-review-name');

    nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.id = 'rtg-review-name';
    nameInput.className = 'rtg-review-input';
    nameInput.placeholder = 'John Doe';
    nameInput.maxLength = 100;
    nameInput.required = true;
    try { nameInput.value = localStorage.getItem('rtg_guest_name') || ''; } catch (e) { /* private browsing */ }

    guestNameSection.appendChild(nameLabel);
    guestNameSection.appendChild(nameInput);

    guestEmailSection = document.createElement('div');
    guestEmailSection.className = 'rtg-review-field';

    const emailLabel = document.createElement('label');
    emailLabel.textContent = 'Your Email';
    emailLabel.setAttribute('for', 'rtg-review-email');

    emailInput = document.createElement('input');
    emailInput.type = 'email';
    emailInput.id = 'rtg-review-email';
    emailInput.className = 'rtg-review-input';
    emailInput.placeholder = 'you@example.com';
    emailInput.maxLength = 254;
    emailInput.required = true;
    try { emailInput.value = localStorage.getItem('rtg_guest_email') || ''; } catch (e) { /* private browsing */ }

    const emailNote = document.createElement('div');
    emailNote.className = 'rtg-review-char-count';
    emailNote.textContent = 'Your email will not be displayed publicly.';

    guestEmailSection.appendChild(emailLabel);
    guestEmailSection.appendChild(emailInput);
    guestEmailSection.appendChild(emailNote);

    // Honeypot field — hidden from real users, bots fill it.
    honeypotInput = document.createElement('input');
    honeypotInput.type = 'text';
    honeypotInput.name = 'website';
    honeypotInput.tabIndex = -1;
    honeypotInput.autocomplete = 'off';
    honeypotInput.setAttribute('aria-hidden', 'true');
    honeypotInput.style.cssText = 'position:absolute;left:-9999px;top:-9999px;opacity:0;height:0;width:0;';
  }

  const titleSection = document.createElement('div');
  titleSection.className = 'rtg-review-field';

  const titleLabel = document.createElement('label');
  titleLabel.textContent = isGuest ? 'Review Title' : 'Review Title (optional)';
  titleLabel.setAttribute('for', 'rtg-review-title');

  const titleInput = document.createElement('input');
  titleInput.type = 'text';
  titleInput.id = 'rtg-review-title';
  titleInput.className = 'rtg-review-input';
  titleInput.placeholder = 'Sum up your experience...';
  titleInput.maxLength = 200;
  titleInput.value = currentTitle;

  titleSection.appendChild(titleLabel);
  titleSection.appendChild(titleInput);

  const textSection = document.createElement('div');
  textSection.className = 'rtg-review-field';

  const textLabel = document.createElement('label');
  textLabel.textContent = isGuest ? 'Your Review' : 'Your Review (optional)';
  textLabel.setAttribute('for', 'rtg-review-text');

  const textArea = document.createElement('textarea');
  textArea.id = 'rtg-review-text';
  textArea.className = 'rtg-review-textarea';
  textArea.placeholder = 'Share your experience with this tire\u2026 How does it handle, ride comfort, noise level, tread wear?';
  textArea.maxLength = 5000;
  textArea.rows = 5;
  textArea.value = currentText;

  const charCount = document.createElement('div');
  charCount.className = 'rtg-review-char-count';
  charCount.textContent = `${currentText.length}/5000`;

  textArea.addEventListener('input', () => {
    charCount.textContent = `${textArea.value.length}/5000`;
  });

  textSection.appendChild(textLabel);
  textSection.appendChild(textArea);
  textSection.appendChild(charCount);

  // Guest notice about moderation.
  if (isGuest) {
    const notice = document.createElement('div');
    notice.className = 'rtg-review-guest-notice';
    notice.textContent = 'Your review will be visible after admin approval. You\u2019ll receive an email when it\u2019s approved.';
    textSection.appendChild(notice);
  }

  const footer = document.createElement('div');
  footer.className = 'rtg-review-modal-footer';

  const cancelBtn = document.createElement('button');
  cancelBtn.className = 'rtg-review-btn-cancel';
  cancelBtn.textContent = 'Cancel';

  const submitBtn = document.createElement('button');
  submitBtn.className = 'rtg-review-btn-submit';
  submitBtn.textContent = currentText ? 'Update Review' : 'Submit Review';

  const errorMsg = document.createElement('div');
  errorMsg.className = 'rtg-review-error';

  footer.appendChild(errorMsg);

  // Delete button for logged-in users editing an existing rating.
  const isEditing = state.isLoggedIn && !isGuest && (existingReview.rating > 0);
  if (isEditing) {
    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'rtg-review-btn-delete';
    deleteBtn.textContent = 'Delete Rating';
    deleteBtn.addEventListener('click', () => {
      if (!confirm('Delete your rating for this tire?')) return;
      deleteBtn.disabled = true;
      deleteBtn.textContent = 'Deleting...';
      deleteTireRating(tireId)
        .then(() => {
          closeModal();
          showToast('Your rating has been deleted.', 'success');
        })
        .catch(err => {
          deleteBtn.disabled = false;
          deleteBtn.textContent = 'Delete Rating';
          errorMsg.textContent = typeof err === 'string' ? err : (err.message || 'Failed to delete.');
        });
    });
    footer.appendChild(deleteBtn);
  }

  footer.appendChild(cancelBtn);
  footer.appendChild(submitBtn);

  modal.appendChild(header);
  modal.appendChild(starSection);
  if (isGuest) {
    modal.appendChild(guestNameSection);
    modal.appendChild(guestEmailSection);
    modal.appendChild(honeypotInput);
  }
  modal.appendChild(titleSection);
  modal.appendChild(textSection);
  modal.appendChild(footer);
  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  requestAnimationFrame(() => overlay.classList.add('active'));
  if (isGuest && nameInput) {
    nameInput.focus();
  } else {
    titleInput.focus();
  }

  // Remember the opener so keyboard focus returns there on close.
  const returnFocusTo = document.activeElement;

  function closeModal() {
    overlay.classList.remove('active');
    document.removeEventListener('keydown', modalKeydown);
    setTimeout(() => overlay.remove(), 200);
    if (returnFocusTo && typeof returnFocusTo.focus === 'function') {
      returnFocusTo.focus({ preventScroll: true });
    }
  }

  // Escape closes; Tab is trapped inside the dialog (wraps at both ends).
  // One listener, always removed in closeModal — the old Escape-only
  // handler leaked when the modal was closed via button/backdrop.
  function modalKeydown(e) {
    if (e.key === 'Escape') {
      closeModal();
      return;
    }
    if (e.key !== 'Tab') return;

    const focusables = overlay.querySelectorAll(
      'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'
    );
    if (!focusables.length) return;

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', modalKeydown);

  submitBtn.addEventListener('click', () => {
    if (selectedRating < 1 || selectedRating > 5) {
      errorMsg.textContent = 'Please select a star rating.';
      return;
    }

    // Guest-specific validation.
    if (isGuest) {
      if (!nameInput.value.trim()) {
        errorMsg.textContent = 'Please enter your name.';
        nameInput.focus();
        return;
      }
      if (!emailInput.value.trim() || !emailInput.validity.valid) {
        errorMsg.textContent = 'Please enter a valid email address.';
        emailInput.focus();
        return;
      }
      if (!titleInput.value.trim() && !textArea.value.trim()) {
        errorMsg.textContent = 'Please write a review title or body text.';
        titleInput.focus();
        return;
      }
    }

    errorMsg.textContent = '';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    const hasReviewContent = textArea.value.trim().length > 0 || titleInput.value.trim().length > 0;
    const isUpdate = !!currentText || !!currentTitle;

    let submitPromise;
    if (isGuest) {
      submitPromise = submitGuestTireRating(
        tireId, selectedRating,
        nameInput.value.trim(), emailInput.value.trim(),
        titleInput.value.trim(), textArea.value.trim(),
        honeypotInput ? honeypotInput.value : ''
      );
    } else {
      submitPromise = submitTireRating(tireId, selectedRating, titleInput.value.trim(), textArea.value.trim());
    }

    submitPromise
      .then((result) => {
        closeModal();
        if (isGuest) {
          // Remember guest info for next review.
          try {
            localStorage.setItem('rtg_guest_name', nameInput.value.trim());
            localStorage.setItem('rtg_guest_email', emailInput.value.trim());
          } catch (e) { /* private browsing */ }
          // Track pending review so the card shows "Review Pending".
          state.guestPendingReviews = state.guestPendingReviews || new Set();
          state.guestPendingReviews.add(tireId);
          updateRatingDisplay(tireId);
          showToast('Thanks! Your review has been submitted and is pending approval.', 'info');
        } else if (hasReviewContent && result && result.review_status === 'pending') {
          showToast('Thanks! Your review has been submitted and is pending approval.', 'info');
        } else if (isUpdate) {
          showToast('Your review has been updated.', 'success');
        } else {
          showToast('Your review has been saved!', 'success');
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.textContent = currentText ? 'Update Review' : 'Submit Review';
        errorMsg.textContent = typeof err === 'string' ? err : (err.message || 'Failed to submit. Please try again.');
      });
  });
}

// ── Toast Notifications ──

export function showToast(message, type = 'success', duration = 4000) {
  let container = document.querySelector('.rtg-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'rtg-toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `rtg-toast rtg-toast-${type}`;

  const icon = document.createElement('span');
  icon.className = 'rtg-toast-icon';
  icon.textContent = type === 'success' ? '\u2714' : '\u2139';

  const text = document.createElement('span');
  text.textContent = message;

  toast.appendChild(icon);
  toast.appendChild(text);
  container.appendChild(toast);

  requestAnimationFrame(() => {
    requestAnimationFrame(() => toast.classList.add('visible'));
  });

  setTimeout(() => {
    toast.classList.remove('visible');
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

