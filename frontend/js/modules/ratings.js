/* jshint esversion: 11 */

/**
 * Rating system, review modal, reviews drawer, and toast notifications.
 */

import { state } from './state.js';
import { rtgColor, rtgIcon, starSVGMarkup, getDOMElement } from './helpers.js';
import { VALIDATION_PATTERNS, NUMERIC_BOUNDS, validateNumeric } from './validation.js';

export function initializeRatingSystem() {
  if (typeof tireRatingAjax !== 'undefined') {
    state.isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
  }
}

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
    state.ratingRequestTimeout = setTimeout(() => {
      const uniqueIds = [...new Set(state.ratingRequestQueue)];
      state.ratingRequestQueue = [];

      if (typeof tireRatingAjax !== 'undefined') {
        state.isLoggedIn = tireRatingAjax.is_logged_in === true || tireRatingAjax.is_logged_in === '1' || tireRatingAjax.is_logged_in === 1;
      }

      if (typeof tireRatingAjax === 'undefined') {
        console.warn('WordPress rating system not available');
        resolve();
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
        resolve();
      })
      .catch(error => {
        console.error('Error loading tire ratings:', error);
        resolve();
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

  ratingDisplay.appendChild(starsContainer);
  ratingDisplay.appendChild(ratingInfo);
  container.appendChild(ratingDisplay);

  // Review actions row
  const reviewActions = document.createElement('div');
  reviewActions.className = 'review-actions';

  const reviewCount = state.tireRatings[tireId]?.review_count || 0;

  if (reviewCount > 0) {
    const viewReviewsBtn = document.createElement('button');
    viewReviewsBtn.className = 'review-action-link view-reviews-btn';
    viewReviewsBtn.dataset.tireId = tireId;
    const reviewIcon = document.createElement('span');
    reviewIcon.innerHTML = rtgIcon('message', 14);
    reviewIcon.style.display = 'inline-flex';
    viewReviewsBtn.appendChild(reviewIcon);
    viewReviewsBtn.appendChild(document.createTextNode(` ${reviewCount} review${reviewCount !== 1 ? 's' : ''}`));
    reviewActions.appendChild(viewReviewsBtn);
  }

  if (state.isLoggedIn) {
    const hasReview = state.userReviews[tireId]?.rating;
    const writeBtn = document.createElement('button');
    writeBtn.className = 'review-action-link write-review-btn';
    writeBtn.dataset.tireId = tireId;
    writeBtn.textContent = hasReview ? 'Edit Review' : 'Write a Review';
    reviewActions.appendChild(writeBtn);
  } else {
    const hasPending = state.guestPendingReviews && state.guestPendingReviews.has(tireId);
    if (hasPending) {
      const pendingBadge = document.createElement('span');
      pendingBadge.className = 'review-action-link rtg-review-pending-badge';
      pendingBadge.textContent = 'Review Pending';
      reviewActions.appendChild(pendingBadge);
    } else {
      // Guest review button — opens modal with name/email fields.
      const guestBtn = document.createElement('button');
      guestBtn.className = 'review-action-link write-review-btn';
      guestBtn.dataset.tireId = tireId;
      guestBtn.textContent = 'Write a Review';
      reviewActions.appendChild(guestBtn);
    }
  }

  container.appendChild(reviewActions);

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

  // Guest login/register prompt.
  let loginBanner;
  if (isGuest && typeof tireRatingAjax !== 'undefined') {
    loginBanner = document.createElement('div');
    loginBanner.className = 'rtg-review-login-banner';

    const bannerIcon = document.createElement('div');
    bannerIcon.className = 'rtg-review-login-banner-icon';
    bannerIcon.innerHTML = rtgIcon('user', 18);

    const bannerContent = document.createElement('div');
    bannerContent.className = 'rtg-review-login-banner-content';

    const bannerText = document.createElement('p');
    bannerText.className = 'rtg-review-login-banner-text';
    bannerText.textContent = 'Create an account to edit reviews and favorite tires.';

    const bannerActions = document.createElement('div');
    bannerActions.className = 'rtg-review-login-banner-actions';

    const registerLink = document.createElement('a');
    registerLink.href = tireRatingAjax.register_url || '/wp-login.php?action=register';
    registerLink.className = 'rtg-review-login-btn rtg-review-login-btn-primary';
    registerLink.textContent = 'Sign up';

    const orSpan = document.createElement('span');
    orSpan.className = 'rtg-review-login-or';
    orSpan.textContent = 'or';

    const loginLink = document.createElement('a');
    loginLink.href = tireRatingAjax.login_url || '/wp-login.php';
    loginLink.className = 'rtg-review-login-btn rtg-review-login-btn-secondary';
    loginLink.textContent = 'Log in';

    bannerActions.appendChild(registerLink);
    bannerActions.appendChild(orSpan);
    bannerActions.appendChild(loginLink);

    bannerContent.appendChild(bannerText);
    bannerContent.appendChild(bannerActions);

    loginBanner.appendChild(bannerIcon);
    loginBanner.appendChild(bannerContent);
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
  if (loginBanner) {
    modal.appendChild(loginBanner);
  }
  modal.appendChild(footer);
  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  requestAnimationFrame(() => overlay.classList.add('active'));
  if (isGuest && nameInput) {
    nameInput.focus();
  } else {
    titleInput.focus();
  }

  function closeModal() {
    overlay.classList.remove('active');
    setTimeout(() => overlay.remove(), 200);
  }

  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', function escHandler(e) {
    if (e.key === 'Escape') {
      closeModal();
      document.removeEventListener('keydown', escHandler);
    }
  });

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

// ── Reviews Drawer ──

export function openReviewsDrawer(tireId) {
  const existing = document.getElementById('rtg-reviews-drawer');
  if (existing) existing.remove();

  const card = document.querySelector(`[data-tire-id="${CSS.escape(tireId)}"].tire-card`);
  const brand = card ? card.querySelector('.tire-card-brand')?.textContent || '' : '';
  const model = card ? card.querySelector('.tire-card-model')?.textContent || '' : '';

  const overlay = document.createElement('div');
  overlay.id = 'rtg-reviews-drawer';
  overlay.className = 'rtg-reviews-drawer-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-label', 'Reviews');

  const drawer = document.createElement('div');
  drawer.className = 'rtg-reviews-drawer';

  const header = document.createElement('div');
  header.className = 'rtg-reviews-drawer-header';

  const titleEl = document.createElement('h3');
  titleEl.textContent = brand && model ? `Reviews for ${brand} ${model}` : 'Reviews';

  const ratingData = state.tireRatings[tireId] || { average: 0, count: 0 };
  const summaryEl = document.createElement('div');
  summaryEl.className = 'rtg-reviews-summary';
  if (ratingData.average > 0) {
    summaryEl.innerHTML = `<span class="rtg-reviews-avg">${ratingData.average.toFixed(1)}</span> <span class="rtg-reviews-stars-mini">${renderStarsHTML(ratingData.average)}</span> <span class="rtg-reviews-total">${ratingData.count} review${ratingData.count !== 1 ? 's' : ''}</span>`;
  }

  const closeBtn = document.createElement('button');
  closeBtn.className = 'rtg-reviews-drawer-close';
  closeBtn.setAttribute('aria-label', 'Close');
  closeBtn.innerHTML = '&times;';

  header.appendChild(titleEl);
  header.appendChild(summaryEl);
  header.appendChild(closeBtn);

  const content = document.createElement('div');
  content.className = 'rtg-reviews-content';
  content.innerHTML = '<div class="rtg-reviews-loading">Loading reviews...</div>';

  drawer.appendChild(header);
  drawer.appendChild(content);
  overlay.appendChild(drawer);
  document.body.appendChild(overlay);

  requestAnimationFrame(() => overlay.classList.add('active'));

  function closeDrawer() {
    overlay.classList.remove('active');
    setTimeout(() => overlay.remove(), 200);
  }

  closeBtn.addEventListener('click', closeDrawer);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeDrawer();
  });
  document.addEventListener('keydown', function escHandler(e) {
    if (e.key === 'Escape') {
      closeDrawer();
      document.removeEventListener('keydown', escHandler);
    }
  });

  loadReviews(tireId, content, 1);
}

function loadReviews(tireId, container, page) {
  const formData = new FormData();
  formData.append('action', 'get_tire_reviews');
  formData.append('tire_id', tireId);
  formData.append('page', page.toString());

  fetch(tireRatingAjax.ajaxurl, {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success || !data.data.reviews.length) {
      const emptyDiv = document.createElement('div');
      emptyDiv.className = 'rtg-reviews-empty';

      const iconEl = document.createElement('div');
      iconEl.className = 'rtg-reviews-empty-icon';
      iconEl.textContent = '\u270D\uFE0F';

      const headingEl = document.createElement('div');
      headingEl.className = 'rtg-reviews-empty-heading';
      headingEl.textContent = 'No reviews yet';

      const subEl = document.createElement('div');
      subEl.textContent = 'Be the first to share your experience with this tire!';

      emptyDiv.appendChild(iconEl);
      emptyDiv.appendChild(headingEl);
      emptyDiv.appendChild(subEl);

      // Show CTA for both logged-in users and guests.
      const ctaBtn = document.createElement('button');
      ctaBtn.className = 'rtg-reviews-empty-cta';
      ctaBtn.textContent = 'Write a Review';
      ctaBtn.addEventListener('click', () => {
        const overlayEl = container.closest('.rtg-reviews-drawer-overlay');
        if (overlayEl) {
          overlayEl.classList.remove('active');
          setTimeout(() => overlayEl.remove(), 200);
        }
        openReviewModal(tireId, state.userRatings[tireId] || 0);
      });
      emptyDiv.appendChild(ctaBtn);

      container.innerHTML = '';
      container.appendChild(emptyDiv);
      return;
    }

    container.innerHTML = '';

    data.data.reviews.forEach(review => {
      container.appendChild(createReviewCard(review));
    });

    if (data.data.total_pages > 1) {
      const pag = document.createElement('div');
      pag.className = 'rtg-reviews-pagination';

      if (page > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = 'rtg-reviews-page-btn';
        prevBtn.textContent = 'Previous';
        prevBtn.addEventListener('click', () => loadReviews(tireId, container, page - 1));
        pag.appendChild(prevBtn);
      }

      const pageInfo = document.createElement('span');
      pageInfo.className = 'rtg-reviews-page-info';
      pageInfo.textContent = `Page ${page} of ${data.data.total_pages}`;
      pag.appendChild(pageInfo);

      if (page < data.data.total_pages) {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'rtg-reviews-page-btn';
        nextBtn.textContent = 'Next';
        nextBtn.addEventListener('click', () => loadReviews(tireId, container, page + 1));
        pag.appendChild(nextBtn);
      }

      container.appendChild(pag);
    }
  })
  .catch(() => {
    container.innerHTML = '<div class="rtg-reviews-empty">Failed to load reviews. Please try again.</div>';
  });
}

function createReviewCard(review) {
  const card = document.createElement('div');
  card.className = 'rtg-review-card';

  const header = document.createElement('div');
  header.className = 'rtg-review-card-header';

  const userReviewsUrl = (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.userReviewsUrl) ? rtgData.settings.userReviewsUrl : '';
  let authorEl;
  if (review.user_id && parseInt(review.user_id) > 0 && userReviewsUrl) {
    authorEl = document.createElement('a');
    authorEl.href = userReviewsUrl + '?reviewer=' + encodeURIComponent(review.user_id);
    authorEl.className = 'rtg-review-author rtg-review-author-link';
    authorEl.textContent = review.display_name || 'Anonymous';
  } else {
    authorEl = document.createElement('span');
    authorEl.className = 'rtg-review-author';
    authorEl.textContent = review.display_name || 'Anonymous';
  }

  const starsEl = document.createElement('span');
  starsEl.className = 'rtg-review-card-stars';
  starsEl.innerHTML = renderStarsHTML(review.rating);

  const dateEl = document.createElement('span');
  dateEl.className = 'rtg-review-date';
  dateEl.textContent = formatReviewDate(review.updated_at || review.created_at);

  header.appendChild(authorEl);
  header.appendChild(starsEl);
  header.appendChild(dateEl);

  card.appendChild(header);

  if (review.review_title) {
    const titleEl = document.createElement('div');
    titleEl.className = 'rtg-review-card-title';
    titleEl.textContent = review.review_title;
    card.appendChild(titleEl);
  }

  if (review.review_text) {
    const bodyEl = document.createElement('div');
    bodyEl.className = 'rtg-review-card-body';
    bodyEl.textContent = review.review_text;
    card.appendChild(bodyEl);
  } else {
    const ratingOnly = document.createElement('div');
    ratingOnly.className = 'rtg-review-card-body rtg-review-rating-only';
    ratingOnly.textContent = 'Star rating only \u2014 no written review.';
    card.appendChild(ratingOnly);
  }

  return card;
}

export function renderStarsHTML(rating) {
  const rounded = Math.round(rating * 2) / 2;
  let html = '';
  for (let i = 1; i <= 5; i++) {
    let cls = 'rtg-mini-star';
    if (rounded >= i) cls += ' filled';
    else if (rounded >= i - 0.5) cls += ' half-filled';
    html += `<span class="${cls}">${starSVGMarkup(18)}</span>`;
  }
  return html;
}

function formatReviewDate(dateStr) {
  const normalized = dateStr && !dateStr.includes('T') && !dateStr.includes('Z')
    ? dateStr.replace(' ', 'T') + 'Z'
    : dateStr;
  const date = new Date(normalized);

  const wpTz = (typeof tireRatingAjax !== 'undefined' && tireRatingAjax.timezone) || undefined;
  const opts = wpTz ? { timeZone: wpTz } : {};

  const nowParts = new Intl.DateTimeFormat('en-CA', { ...opts, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  const dateParts = new Intl.DateTimeFormat('en-CA', { ...opts, year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
  const nowDay = new Date(nowParts + 'T00:00:00');
  const reviewDay = new Date(dateParts + 'T00:00:00');
  const diffDays = Math.round((nowDay - reviewDay) / (1000 * 60 * 60 * 24));

  if (diffDays <= 0) return 'Today';
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 30) return `${diffDays} days ago`;
  if (diffDays < 365) {
    const months = Math.floor(diffDays / 30);
    return `${months} month${months !== 1 ? 's' : ''} ago`;
  }
  return date.toLocaleDateString('en-US', { ...opts, month: 'short', day: 'numeric', year: 'numeric' });
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

