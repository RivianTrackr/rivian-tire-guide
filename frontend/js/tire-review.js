/* =====================================================================
   Rivian Tire Guide — Standalone Tire Review Page
   Handles tire search/selection, inline review form, and submission.
   Uses RTG_SHARED for HTML escaping and URL validation.
   ===================================================================== */

(function() {
  'use strict';

  var STAR_PATH = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';
  var RATING_LABELS = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

  var config = window.rtgTireReview || {};
  var tires = config.tires || [];
  var isLoggedIn = config.is_logged_in === true || config.is_logged_in === '1' || config.is_logged_in === 1;

  var selectedTireId = null;
  var selectedRating = 0;
  var focusedIndex = -1;

  // --- DOM refs ---
  var searchInput = document.getElementById('rvTireSearch');
  var dropdown = document.getElementById('rvDropdown');
  var tireCard = document.getElementById('rvTireCard');
  var form = document.getElementById('rvForm');
  var successEl = document.getElementById('rvSuccess');
  var starsSelect = document.getElementById('rvStarsSelect');
  var starText = document.getElementById('rvStarText');
  var charCount = document.getElementById('rvCharCount');
  var errorEl = document.getElementById('rvError');
  var submitBtn = document.getElementById('rvSubmitBtn');

  // --- Initialization ---
  init();

  function init() {
    buildStars();
    setupSearch();
    setupForm();
    setupGuestFields();
    restoreGuestInfo();

    // Deep-link: auto-select tire from URL param.
    if (config.preselectedTire) {
      var tire = findTireById(config.preselectedTire);
      if (tire) {
        selectTire(tire);
      }
    }

    // "Change tire" button.
    var changeBtn = document.getElementById('rvChangeTire');
    if (changeBtn) {
      changeBtn.addEventListener('click', function() {
        resetSelection();
        searchInput.focus();
      });
    }

    // "Review Another Tire" button.
    var anotherBtn = document.getElementById('rvReviewAnother');
    if (anotherBtn) {
      anotherBtn.addEventListener('click', function() {
        resetAll();
        searchInput.focus();
      });
    }
  }

  // --- Star builder ---
  function starSVG(size) {
    return '<svg viewBox="0 0 24 24" width="' + size + '" height="' + size + '" aria-hidden="true">' +
      '<path class="star-bg" d="' + STAR_PATH + '" fill="none" stroke="currentColor" stroke-width="1.5"/>' +
      '<path class="star-fill" d="' + STAR_PATH + '" fill="currentColor"/>' +
      '<path class="star-half" d="' + STAR_PATH + '" fill="currentColor" style="clip-path:inset(0 50% 0 0)"/>' +
      '</svg>';
  }

  function buildStars() {
    starsSelect.innerHTML = '';
    for (var i = 1; i <= 5; i++) {
      var star = document.createElement('span');
      star.className = 'rv-star';
      star.dataset.value = i;
      star.innerHTML = starSVG(40);
      star.setAttribute('role', 'radio');
      star.setAttribute('aria-checked', 'false');
      star.setAttribute('aria-label', i + ' star' + (i !== 1 ? 's' : ''));
      star.setAttribute('tabindex', i === 1 ? '0' : '-1');
      starsSelect.appendChild(star);
    }

    starsSelect.addEventListener('click', function(e) {
      var star = e.target.closest('.rv-star');
      if (!star) return;
      selectedRating = parseInt(star.dataset.value, 10);
      updateStarDisplay();
    });

    starsSelect.addEventListener('mouseover', function(e) {
      var star = e.target.closest('.rv-star');
      if (!star) return;
      var val = parseInt(star.dataset.value, 10);
      var stars = starsSelect.querySelectorAll('.rv-star');
      for (var j = 0; j < stars.length; j++) {
        stars[j].classList.toggle('hovered', j < val);
      }
    });

    starsSelect.addEventListener('mouseleave', function() {
      var stars = starsSelect.querySelectorAll('.rv-star');
      for (var j = 0; j < stars.length; j++) {
        stars[j].classList.remove('hovered');
      }
    });
  }

  function updateStarDisplay() {
    var stars = starsSelect.querySelectorAll('.rv-star');
    for (var j = 0; j < stars.length; j++) {
      var idx = j + 1;
      stars[j].classList.toggle('selected', idx <= selectedRating);
      stars[j].setAttribute('aria-checked', idx === selectedRating ? 'true' : 'false');
    }
    starText.textContent = selectedRating > 0 ? RATING_LABELS[selectedRating] : 'Select a rating';
  }

  // --- Tire search ---
  function setupSearch() {
    var debounceTimer;

    searchInput.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function() {
        var query = searchInput.value.trim().toLowerCase();
        if (query.length < 2) {
          closeDropdown();
          return;
        }
        var results = filterTires(query);
        renderDropdown(results);
      }, 150);
    });

    searchInput.addEventListener('focus', function() {
      var query = searchInput.value.trim().toLowerCase();
      if (query.length >= 2) {
        var results = filterTires(query);
        renderDropdown(results);
      }
    });

    searchInput.addEventListener('keydown', function(e) {
      if (!dropdown.classList.contains('open')) return;
      var items = dropdown.querySelectorAll('.rv-dropdown-item');
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        focusedIndex = Math.min(focusedIndex + 1, items.length - 1);
        updateFocus(items);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        focusedIndex = Math.max(focusedIndex - 1, 0);
        updateFocus(items);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (focusedIndex >= 0 && items[focusedIndex]) {
          items[focusedIndex].click();
        }
      } else if (e.key === 'Escape') {
        closeDropdown();
      }
    });

    // Close dropdown on outside click.
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.rv-search-wrap')) {
        closeDropdown();
      }
    });
  }

  function filterTires(query) {
    var terms = query.split(/\s+/);
    return tires.filter(function(t) {
      var haystack = ((t.brand || '') + ' ' + (t.model || '') + ' ' + (t.size || '') + ' ' + (t.category || '')).toLowerCase();
      return terms.every(function(term) {
        return haystack.indexOf(term) !== -1;
      });
    }).slice(0, 20);
  }

  function renderDropdown(results) {
    dropdown.innerHTML = '';
    focusedIndex = -1;

    if (results.length === 0) {
      dropdown.innerHTML = '<div class="rv-dropdown-empty">No tires found. Try a different search.</div>';
      dropdown.classList.add('open');
      return;
    }

    results.forEach(function(tire) {
      var item = document.createElement('div');
      item.className = 'rv-dropdown-item';
      item.dataset.tireId = tire.tire_id;

      var thumb = document.createElement('div');
      thumb.className = 'rv-dropdown-thumb';
      var imgUrl = RTG_SHARED.safeImageURL(tire.image || '');
      if (imgUrl) {
        var img = document.createElement('img');
        img.src = imgUrl;
        img.alt = RTG_SHARED.escapeHTML((tire.brand || '') + ' ' + (tire.model || ''));
        img.loading = 'lazy';
        thumb.appendChild(img);
      }

      var text = document.createElement('div');
      text.className = 'rv-dropdown-text';

      var name = document.createElement('div');
      name.className = 'rv-dropdown-name';
      name.textContent = (tire.brand || '') + ' ' + (tire.model || '');

      var size = document.createElement('div');
      size.className = 'rv-dropdown-size';
      size.textContent = (tire.size || '') + (tire.category ? ' · ' + tire.category : '');

      text.appendChild(name);
      text.appendChild(size);
      item.appendChild(thumb);
      item.appendChild(text);

      item.addEventListener('click', function() {
        selectTire(tire);
        closeDropdown();
      });

      dropdown.appendChild(item);
    });

    dropdown.classList.add('open');
  }

  function updateFocus(items) {
    for (var i = 0; i < items.length; i++) {
      items[i].classList.toggle('focused', i === focusedIndex);
    }
    if (focusedIndex >= 0 && items[focusedIndex]) {
      items[focusedIndex].scrollIntoView({ block: 'nearest' });
    }
  }

  function closeDropdown() {
    dropdown.classList.remove('open');
    focusedIndex = -1;
  }

  // --- Tire selection ---
  function selectTire(tire) {
    selectedTireId = tire.tire_id;
    searchInput.value = (tire.brand || '') + ' ' + (tire.model || '');

    // Populate card.
    var imgContainer = document.getElementById('rvTireImg');
    imgContainer.innerHTML = '';
    var imgUrl = RTG_SHARED.safeImageURL(tire.image || '');
    if (imgUrl) {
      var img = document.createElement('img');
      img.src = imgUrl;
      img.alt = RTG_SHARED.escapeHTML((tire.brand || '') + ' ' + (tire.model || ''));
      imgContainer.appendChild(img);
    }

    document.getElementById('rvTireBrand').textContent = tire.brand || '';
    document.getElementById('rvTireModel').textContent = tire.model || '';
    document.getElementById('rvTireSize').textContent = tire.size || '';

    var categoryEl = document.getElementById('rvTireCategory');
    categoryEl.textContent = tire.category || '';
    categoryEl.style.display = tire.category ? 'inline-block' : 'none';

    // Show card and form.
    tireCard.classList.add('visible');
    form.classList.add('visible');
    successEl.classList.remove('visible');

    // Update form title.
    document.getElementById('rvFormTitle').textContent = 'Review ' + (tire.brand || '') + ' ' + (tire.model || '');

    // Load ratings for this tire.
    loadTireRating(tire.tire_id);

    // Update URL without reload.
    var url = new URL(window.location);
    url.searchParams.set('tire', tire.tire_id);
    window.history.replaceState({}, '', url);
  }

  function loadTireRating(tireId) {
    var starsContainer = document.getElementById('rvTireStars');
    var ratingText = document.getElementById('rvTireRatingText');
    starsContainer.innerHTML = '';
    ratingText.textContent = 'Loading...';

    var formData = new FormData();
    formData.append('action', 'get_tire_ratings');
    formData.append('nonce', config.nonce);
    formData.append('tire_ids[]', tireId);

    fetch(config.ajaxurl, { method: 'POST', body: formData })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.data.ratings && data.data.ratings[tireId]) {
          var rating = data.data.ratings[tireId];
          var avg = parseFloat(rating.average) || 0;
          var count = parseInt(rating.count, 10) || 0;
          renderDisplayStars(starsContainer, avg);
          ratingText.textContent = avg > 0
            ? avg.toFixed(1) + ' (' + count + ' review' + (count !== 1 ? 's' : '') + ')'
            : 'No reviews yet';
        } else {
          renderDisplayStars(starsContainer, 0);
          ratingText.textContent = 'No reviews yet';
        }
      })
      .catch(function() {
        renderDisplayStars(starsContainer, 0);
        ratingText.textContent = 'No reviews yet';
      });
  }

  function renderDisplayStars(container, avg) {
    container.innerHTML = '';
    var rounded = avg > 0 ? Math.round(avg * 2) / 2 : 0;
    for (var i = 1; i <= 5; i++) {
      var span = document.createElement('span');
      if (rounded >= i) {
        span.className = 'star-active';
      } else if (rounded >= i - 0.5) {
        span.className = 'star-half-active';
      }
      span.innerHTML = starSVG(18);
      // For half stars, clip the fill.
      if (rounded >= i - 0.5 && rounded < i) {
        var fill = span.querySelector('.star-fill');
        if (fill) fill.style.clipPath = 'inset(0 50% 0 0)';
      }
      container.appendChild(span);
    }
  }

  function resetSelection() {
    selectedTireId = null;
    selectedRating = 0;
    searchInput.value = '';
    tireCard.classList.remove('visible');
    form.classList.remove('visible');
    successEl.classList.remove('visible');
    errorEl.textContent = '';
    updateStarDisplay();
    document.getElementById('rvReviewTitle').value = '';
    document.getElementById('rvReviewText').value = '';
    charCount.textContent = '0/5000';

    // Remove tire from URL.
    var url = new URL(window.location);
    url.searchParams.delete('tire');
    window.history.replaceState({}, '', url);
  }

  function resetAll() {
    resetSelection();
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Review';
  }

  // --- Guest fields ---
  function setupGuestFields() {
    if (isLoggedIn) return;

    // Show guest-specific UI.
    document.getElementById('rvGuestNameField').style.display = '';
    document.getElementById('rvGuestEmailField').style.display = '';
    document.getElementById('rvGuestNotice').style.display = '';
    document.getElementById('rvLoginBanner').style.display = '';

    // Update labels — fields are required for guests.
    document.getElementById('rvTitleLabel').textContent = 'Review Title';
    document.getElementById('rvTextLabel').textContent = 'Your Review';
  }

  function restoreGuestInfo() {
    if (isLoggedIn) return;
    try {
      var savedName = localStorage.getItem('rtg_guest_name');
      var savedEmail = localStorage.getItem('rtg_guest_email');
      if (savedName) document.getElementById('rvGuestName').value = savedName;
      if (savedEmail) document.getElementById('rvGuestEmail').value = savedEmail;
    } catch (e) { /* private browsing */ }
  }

  // --- Form submission ---
  function setupForm() {
    // Character count.
    var textArea = document.getElementById('rvReviewText');
    textArea.addEventListener('input', function() {
      charCount.textContent = textArea.value.length + '/5000';
    });

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      submitReview();
    });
  }

  function submitReview() {
    errorEl.textContent = '';

    if (!selectedTireId) {
      errorEl.textContent = 'Please select a tire first.';
      return;
    }

    if (selectedRating < 1 || selectedRating > 5) {
      errorEl.textContent = 'Please select a star rating.';
      return;
    }

    var reviewTitle = document.getElementById('rvReviewTitle').value.trim();
    var reviewText = document.getElementById('rvReviewText').value.trim();
    var honeypot = form.querySelector('input[name="website"]').value;

    if (!isLoggedIn) {
      var guestName = document.getElementById('rvGuestName').value.trim();
      var guestEmail = document.getElementById('rvGuestEmail').value.trim();

      if (!guestName) {
        errorEl.textContent = 'Please enter your name.';
        document.getElementById('rvGuestName').focus();
        return;
      }
      if (!guestEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail)) {
        errorEl.textContent = 'Please enter a valid email address.';
        document.getElementById('rvGuestEmail').focus();
        return;
      }
      if (!reviewTitle && !reviewText) {
        errorEl.textContent = 'Please write a review title or body text.';
        document.getElementById('rvReviewTitle').focus();
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      submitGuest(selectedTireId, selectedRating, guestName, guestEmail, reviewTitle, reviewText, honeypot);
    } else {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      submitLoggedIn(selectedTireId, selectedRating, reviewTitle, reviewText);
    }
  }

  function submitLoggedIn(tireId, rating, title, text) {
    var formData = new FormData();
    formData.append('action', 'submit_tire_rating');
    formData.append('tire_id', tireId);
    formData.append('rating', rating.toString());
    formData.append('nonce', config.nonce);
    if (title) formData.append('review_title', title.substring(0, 200));
    if (text) formData.append('review_text', text.substring(0, 5000));

    fetch(config.ajaxurl, { method: 'POST', body: formData })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          var isPending = data.data.review_status === 'pending';
          showSuccess(isPending);
        } else {
          errorEl.textContent = data.data || 'Failed to submit. Please try again.';
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit Review';
        }
      })
      .catch(function() {
        errorEl.textContent = 'Network error. Please try again.';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Review';
      });
  }

  function submitGuest(tireId, rating, name, email, title, text, honeypot) {
    var formData = new FormData();
    formData.append('action', 'submit_guest_tire_rating');
    formData.append('tire_id', tireId);
    formData.append('rating', rating.toString());
    formData.append('guest_name', name.substring(0, 100));
    formData.append('guest_email', email.substring(0, 254));
    formData.append('nonce', config.nonce);
    if (title) formData.append('review_title', title.substring(0, 200));
    if (text) formData.append('review_text', text.substring(0, 5000));
    formData.append('website', honeypot);

    fetch(config.ajaxurl, { method: 'POST', body: formData })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          // Save guest info for next visit.
          try {
            localStorage.setItem('rtg_guest_name', name);
            localStorage.setItem('rtg_guest_email', email);
          } catch (e) { /* private browsing */ }
          showSuccess(true);
        } else {
          errorEl.textContent = data.data || 'Failed to submit. Please try again.';
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit Review';
        }
      })
      .catch(function() {
        errorEl.textContent = 'Network error. Please try again.';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Review';
      });
  }

  function showSuccess(isPending) {
    form.classList.remove('visible');

    var successTitle = document.getElementById('rvSuccessTitle');
    var successText = document.getElementById('rvSuccessText');

    if (isPending) {
      successTitle.textContent = 'Review Submitted!';
      successText.textContent = 'Thanks for sharing your experience! Your review has been submitted and is pending approval.';
    } else {
      successTitle.textContent = 'Review Saved!';
      successText.textContent = 'Thanks for sharing your experience. Your review helps fellow Rivian owners make better tire choices.';
    }

    successEl.classList.add('visible');
    showToast(isPending ? 'Review submitted and pending approval.' : 'Your review has been saved!', isPending ? 'info' : 'success');
  }

  // --- Helpers ---
  function findTireById(id) {
    for (var i = 0; i < tires.length; i++) {
      if (tires[i].tire_id === id) return tires[i];
    }
    return null;
  }

  function showToast(message, type) {
    var container = document.querySelector('.rv-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'rv-toast-container';
      document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.className = 'rv-toast rv-toast-' + (type || 'success');

    var icon = document.createElement('span');
    icon.className = 'rv-toast-icon';
    icon.textContent = type === 'info' ? '\u2139' : '\u2714';

    var text = document.createElement('span');
    text.textContent = message;

    toast.appendChild(icon);
    toast.appendChild(text);
    container.appendChild(toast);

    requestAnimationFrame(function() {
      requestAnimationFrame(function() {
        toast.classList.add('visible');
      });
    });

    setTimeout(function() {
      toast.classList.remove('visible');
      setTimeout(function() { toast.remove(); }, 300);
    }, 4000);
  }

})();
