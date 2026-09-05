/* =====================================================================
   Rivian Tire Guide — Standalone Tire Review Page
   Tire search and selection, a landing list for people who did not
   arrive from a tire page, the review form (overall stars, six optional
   detail axes, vehicle / miles / owner, words, and for guests name and
   email), duplicate detection for returning guests, load-to-edit for
   the few signed-in users, and submission.
   Uses RTG_SHARED for HTML escaping and URL validation.
   ===================================================================== */

(function() {
  'use strict';

  var STAR_PATH = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';
  var RATING_LABELS = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
  var AXES = [
    { key: 'range',   name: 'Range and efficiency', note: 'Did your mi/kWh hold up?' },
    { key: 'noise',   name: 'Road noise',           note: 'Highway hum at 70 mph' },
    { key: 'comfort', name: 'Ride comfort',         note: 'Expansion joints, washboard' },
    { key: 'wet',     name: 'Wet grip',             note: 'Rain, standing water' },
    { key: 'snow',    name: 'Snow and ice',         note: 'Leave blank if you have not seen winter yet' },
    { key: 'wear',    name: 'Tread wear',           note: 'Wear versus the miles you have on them' }
  ];
  var LS_NAME = 'rtg_guest_name';
  var LS_EMAIL = 'rtg_guest_email';
  var LS_VEHICLE = 'rtg_review_vehicle';
  var POPULAR_MAX = 6;

  var config = window.rtgTireReview || {};
  var tires = config.tires || [];
  var isLoggedIn = config.is_logged_in === true || config.is_logged_in === '1' || config.is_logged_in === 1;
  var autoApprove = config.autoApprove === true || config.autoApprove === '1' || config.autoApprove === 1;
  var vehicleSizeMap = (config.vehicleSizeMap && typeof config.vehicleSizeMap === 'object' && !Array.isArray(config.vehicleSizeMap)) ? config.vehicleSizeMap : {};
  var reviewCounts = (config.reviewCounts && typeof config.reviewCounts === 'object' && !Array.isArray(config.reviewCounts)) ? config.reviewCounts : {};
  var vehicles = Array.isArray(config.vehicles) && config.vehicles.length ? config.vehicles : ['R1T', 'R1S', 'R2', 'R3'];

  var selectedTire = null;
  var selectedRating = 0;
  var axisValues = {};
  var pickedVehicle = '';
  var focusedIndex = -1;
  var existingReview = null;      // signed-in user's review of the selected tire
  var guestBlocked = false;       // guest email already reviewed the selected tire
  var checkSeq = 0;

  // --- DOM refs ---
  var $ = function(id) { return document.getElementById(id); };
  var searchInput = $('rvTireSearch');
  var dropdown = $('rvDropdown');
  var landing = $('rvLanding');
  var tireCard = $('rvTireCard');
  var form = $('rvForm');
  var successEl = $('rvSuccess');
  var starsSelect = $('rvStarsSelect');
  var starText = $('rvStarText');
  var charCount = $('rvCharCount');
  var errorEl = $('rvError');
  var submitBtn = $('rvSubmitBtn');
  var footerNote = $('rvFooterNote');

  init();

  function init() {
    buildStars();
    buildAxes();
    buildVehiclePick();
    buildLanding();
    setupSearch();
    setupForm();
    setupAccountMode();
    restoreGuestInfo();

    if (config.preselectedTire) {
      var tire = findTireById(config.preselectedTire);
      if (tire) selectTire(tire);
    }

    var changeBtn = $('rvChangeTire');
    if (changeBtn) changeBtn.addEventListener('click', function() { resetSelection(); searchInput.focus(); });
    var anotherBtn = $('rvReviewAnother');
    if (anotherBtn) anotherBtn.addEventListener('click', function() { resetAll(); searchInput.focus(); });
    var clearBtn = $('rvWelcomeClear');
    if (clearBtn) clearBtn.addEventListener('click', forgetGuestInfo);
  }

  // --- Stars -----------------------------------------------------------
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
      star.setAttribute('aria-label', i + ' star' + (i !== 1 ? 's' : '') + ', ' + RATING_LABELS[i]);
      star.setAttribute('tabindex', i === 1 ? '0' : '-1');
      starsSelect.appendChild(star);
    }

    starsSelect.addEventListener('click', function(e) {
      var star = e.target.closest('.rv-star');
      if (!star) return;
      selectedRating = parseInt(star.dataset.value, 10);
      updateStarDisplay();
      setFieldError('rvStarError', '');
    });
    starsSelect.addEventListener('keydown', function(e) {
      var star = e.target.closest('.rv-star');
      if (!star) return;
      var v = parseInt(star.dataset.value, 10);
      var next = 0;
      if (e.key === 'ArrowRight' || e.key === 'ArrowUp') next = Math.min(5, v + 1);
      else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') next = Math.max(1, v - 1);
      else if (e.key === ' ' || e.key === 'Enter') next = v;
      if (!next) return;
      e.preventDefault();
      selectedRating = next;
      updateStarDisplay();
      var all = starsSelect.querySelectorAll('.rv-star');
      all[next - 1].focus();
    });
    starsSelect.addEventListener('mouseover', function(e) {
      var star = e.target.closest('.rv-star');
      if (!star) return;
      var val = parseInt(star.dataset.value, 10);
      var stars = starsSelect.querySelectorAll('.rv-star');
      for (var j = 0; j < stars.length; j++) stars[j].classList.toggle('hovered', j < val);
    });
    starsSelect.addEventListener('mouseleave', function() {
      var stars = starsSelect.querySelectorAll('.rv-star');
      for (var j = 0; j < stars.length; j++) stars[j].classList.remove('hovered');
    });
  }

  function updateStarDisplay() {
    var stars = starsSelect.querySelectorAll('.rv-star');
    for (var j = 0; j < stars.length; j++) {
      var idx = j + 1;
      stars[j].classList.toggle('selected', idx <= selectedRating);
      stars[j].setAttribute('aria-checked', idx === selectedRating ? 'true' : 'false');
      stars[j].setAttribute('tabindex', (selectedRating ? idx === selectedRating : idx === 1) ? '0' : '-1');
    }
    starText.textContent = selectedRating > 0 ? selectedRating + ' star' + (selectedRating !== 1 ? 's' : '') + ' · ' + RATING_LABELS[selectedRating] : 'Select a rating';
    starText.classList.remove('is-invalid');
  }

  // --- Detail axes: six rows of five small stars, each optional ---------
  function buildAxes() {
    var wrap = $('rvAxes');
    if (!wrap) return;
    wrap.innerHTML = '';
    AXES.forEach(function(axis) {
      axisValues[axis.key] = 0;
      var row = document.createElement('div');
      row.className = 'rv-axis';

      var text = document.createElement('div');
      text.className = 'rv-axis-text';
      var name = document.createElement('span');
      name.className = 'rv-axis-name';
      name.id = 'rvAxisName-' + axis.key;
      name.textContent = axis.name;
      var note = document.createElement('span');
      note.className = 'rv-axis-note';
      note.textContent = axis.note;
      text.appendChild(name);
      text.appendChild(note);

      var stars = document.createElement('div');
      stars.className = 'rv-axis-stars';
      stars.setAttribute('role', 'radiogroup');
      stars.setAttribute('aria-labelledby', name.id);
      stars.dataset.axis = axis.key;
      for (var i = 1; i <= 5; i++) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'rv-axis-star';
        b.dataset.value = i;
        b.innerHTML = starSVG(20);
        b.setAttribute('role', 'radio');
        b.setAttribute('aria-checked', 'false');
        b.setAttribute('aria-label', i + ' star' + (i !== 1 ? 's' : ''));
        stars.appendChild(b);
      }
      row.appendChild(text);
      row.appendChild(stars);
      wrap.appendChild(row);
    });

    wrap.addEventListener('click', function(e) {
      var b = e.target.closest('.rv-axis-star');
      if (!b) return;
      var group = b.parentNode;
      var key = group.dataset.axis;
      var v = parseInt(b.dataset.value, 10);
      // Pressing the current value again clears the row: "not answered".
      axisValues[key] = axisValues[key] === v ? 0 : v;
      paintAxis(group, axisValues[key]);
    });
    wrap.addEventListener('mouseover', function(e) {
      var b = e.target.closest('.rv-axis-star');
      if (!b) return;
      var v = parseInt(b.dataset.value, 10);
      var all = b.parentNode.querySelectorAll('.rv-axis-star');
      for (var j = 0; j < all.length; j++) all[j].classList.toggle('hovered', j < v);
    });
    wrap.addEventListener('mouseout', function(e) {
      var group = e.target.closest('.rv-axis-stars');
      if (!group) return;
      var all = group.querySelectorAll('.rv-axis-star');
      for (var j = 0; j < all.length; j++) all[j].classList.remove('hovered');
    });
  }

  function paintAxis(group, value) {
    var all = group.querySelectorAll('.rv-axis-star');
    for (var j = 0; j < all.length; j++) {
      all[j].classList.toggle('selected', j + 1 <= value);
      all[j].setAttribute('aria-checked', j + 1 === value ? 'true' : 'false');
    }
  }

  function paintAllAxes() {
    var groups = document.querySelectorAll('#rvAxes .rv-axis-stars');
    for (var i = 0; i < groups.length; i++) paintAxis(groups[i], axisValues[groups[i].dataset.axis] || 0);
  }

  // --- Vehicle segmented controls ----------------------------------------
  function buildSeg(container, options, active, onPick) {
    container.innerHTML = '';
    options.forEach(function(opt) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'rv-seg-btn' + (opt === active ? ' is-active' : '');
      b.setAttribute('role', 'radio');
      b.setAttribute('aria-checked', opt === active ? 'true' : 'false');
      b.dataset.value = opt;
      b.textContent = opt;
      container.appendChild(b);
    });
    container.onclick = function(e) {
      var b = e.target.closest('.rv-seg-btn');
      if (!b) return;
      var v = b.dataset.value;
      // Same button again clears the choice (the form's vehicle is optional).
      var next = (container.dataset.allowClear === '1' && b.classList.contains('is-active')) ? '' : v;
      paintSeg(container, next);
      onPick(next);
    };
  }

  function paintSeg(container, active) {
    var all = container.querySelectorAll('.rv-seg-btn');
    for (var i = 0; i < all.length; i++) {
      var on = all[i].dataset.value === active;
      all[i].classList.toggle('is-active', on);
      all[i].setAttribute('aria-checked', on ? 'true' : 'false');
    }
  }

  function buildVehiclePick() {
    var pick = $('rvVehiclePick');
    if (!pick) return;
    pick.dataset.allowClear = '1';
    buildSeg(pick, vehicles, '', function(v) { pickedVehicle = v; });
  }

  // --- Landing: vehicle switch + most-reviewed tires ---------------------
  function buildLanding() {
    var sw = $('rvVehicleSwitch');
    var list = $('rvPopular');
    if (!sw || !list) return;
    var groups = Object.keys(vehicleSizeMap).sort();
    if (!groups.length) { sw.innerHTML = ''; list.innerHTML = ''; return; }

    var remembered = '';
    try { remembered = localStorage.getItem(LS_VEHICLE) || ''; } catch (e) { /* private browsing */ }
    var active = groups.indexOf(remembered) !== -1 ? remembered : groups[0];

    buildSeg(sw, groups, active, function(v) {
      try { localStorage.setItem(LS_VEHICLE, v); } catch (e) { /* private browsing */ }
      renderPopular(v);
      // A group like R2 or R3 is also the review's vehicle; R1 is not (R1T or R1S).
      if (vehicles.indexOf(v) !== -1 && !pickedVehicle) {
        pickedVehicle = v;
        paintSeg($('rvVehiclePick'), v);
      }
    });
    renderPopular(active);
    if (vehicles.indexOf(active) !== -1) {
      pickedVehicle = active;
      paintSeg($('rvVehiclePick'), active);
    }
  }

  function renderPopular(group) {
    var list = $('rvPopular');
    list.innerHTML = '';
    var sizes = vehicleSizeMap[group] || [];
    var pool = tires.filter(function(t) { return sizes.indexOf(t.size) !== -1; });
    pool.sort(function(a, b) {
      var d = (reviewCounts[b.tire_id] || 0) - (reviewCounts[a.tire_id] || 0);
      if (d) return d;
      return ((a.brand || '') + a.model).localeCompare((b.brand || '') + b.model);
    });
    pool.slice(0, POPULAR_MAX).forEach(function(tire) {
      var n = reviewCounts[tire.tire_id] || 0;
      var item = document.createElement('button');
      item.type = 'button';
      item.className = 'rv-popular-item';

      var thumb = document.createElement('div');
      thumb.className = 'rv-popular-thumb';
      var imgUrl = RTG_SHARED.safeImageURL(tire.image || '');
      if (imgUrl) {
        var img = document.createElement('img');
        img.src = imgUrl;
        img.alt = '';
        img.loading = 'lazy';
        thumb.appendChild(img);
      }
      var text = document.createElement('div');
      text.className = 'rv-popular-text';
      var name = document.createElement('div');
      name.className = 'rv-popular-name';
      name.textContent = (tire.brand || '') + ' ' + (tire.model || '');
      var meta = document.createElement('div');
      meta.className = 'rv-popular-meta';
      meta.textContent = (tire.size || '') + ' · ' + (n ? n + ' review' + (n !== 1 ? 's' : '') : 'No reviews yet, be the first');
      text.appendChild(name);
      text.appendChild(meta);
      var cta = document.createElement('span');
      cta.className = 'rv-popular-cta';
      cta.textContent = 'Review';

      item.appendChild(thumb);
      item.appendChild(text);
      item.appendChild(cta);
      item.addEventListener('click', function() { selectTire(tire); });
      list.appendChild(item);
    });
  }

  // --- Search --------------------------------------------------------------
  function setupSearch() {
    var debounceTimer;
    searchInput.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function() {
        var query = searchInput.value.trim().toLowerCase();
        if (query.length < 2) { closeDropdown(); return; }
        renderDropdown(filterTires(query));
      }, 150);
    });
    searchInput.addEventListener('focus', function() {
      var query = searchInput.value.trim().toLowerCase();
      if (query.length >= 2 && !selectedTire) renderDropdown(filterTires(query));
    });
    searchInput.addEventListener('keydown', function(e) {
      if (!dropdown.classList.contains('open')) return;
      var items = dropdown.querySelectorAll('.rv-dropdown-item');
      if (e.key === 'ArrowDown') { e.preventDefault(); focusedIndex = Math.min(focusedIndex + 1, items.length - 1); updateFocus(items); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); focusedIndex = Math.max(focusedIndex - 1, 0); updateFocus(items); }
      else if (e.key === 'Enter') { e.preventDefault(); if (focusedIndex >= 0 && items[focusedIndex]) items[focusedIndex].click(); }
      else if (e.key === 'Escape') { closeDropdown(); }
    });
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.rv-search-wrap')) closeDropdown();
    });
  }

  function filterTires(query) {
    var terms = query.split(/\s+/);
    return tires.filter(function(t) {
      var haystack = ((t.brand || '') + ' ' + (t.model || '') + ' ' + (t.size || '') + ' ' + (t.category || '')).toLowerCase();
      return terms.every(function(term) { return haystack.indexOf(term) !== -1; });
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
      var n = reviewCounts[tire.tire_id] || 0;
      size.textContent = (tire.size || '') + (tire.category ? ' · ' + tire.category : '') + (n ? ' · ' + n + ' review' + (n !== 1 ? 's' : '') : '');
      text.appendChild(name);
      text.appendChild(size);
      item.appendChild(thumb);
      item.appendChild(text);
      item.addEventListener('click', function() { selectTire(tire); closeDropdown(); });
      dropdown.appendChild(item);
    });
    dropdown.classList.add('open');
  }

  function updateFocus(items) {
    for (var i = 0; i < items.length; i++) items[i].classList.toggle('focused', i === focusedIndex);
    if (focusedIndex >= 0 && items[focusedIndex]) items[focusedIndex].scrollIntoView({ block: 'nearest' });
  }

  function closeDropdown() {
    dropdown.classList.remove('open');
    focusedIndex = -1;
  }

  // --- Tire selection ------------------------------------------------------
  function tirePageUrl(tire) {
    if (!config.tirePageBase || !tire || !tire.slug) return '';
    return config.tirePageBase + encodeURIComponent(tire.slug) + '/';
  }

  function selectTire(tire) {
    selectedTire = tire;
    searchInput.value = (tire.brand || '') + ' ' + (tire.model || '');
    closeDropdown();

    var imgContainer = $('rvTireImg');
    imgContainer.innerHTML = '';
    var imgUrl = RTG_SHARED.safeImageURL(tire.image || '');
    if (imgUrl) {
      var img = document.createElement('img');
      img.src = imgUrl;
      img.alt = RTG_SHARED.escapeHTML((tire.brand || '') + ' ' + (tire.model || ''));
      imgContainer.appendChild(img);
    }
    $('rvTireBrand').textContent = tire.brand || '';
    $('rvTireModel').textContent = tire.model || '';
    $('rvTireSize').textContent = tire.size || '';
    $('rvTireCategory').textContent = tire.category || '';

    var pageLink = $('rvTirePageLink');
    var url = tirePageUrl(tire);
    if (pageLink) {
      pageLink.hidden = !url;
      if (url) pageLink.href = url;
    }

    if (landing) landing.hidden = true;
    tireCard.classList.add('visible');
    form.classList.add('visible');
    successEl.classList.remove('visible');
    $('rvFormTitle').textContent = 'Review ' + (tire.brand || '') + ' ' + (tire.model || '');

    existingReview = null;
    guestBlocked = false;
    hideExistingBanner();
    loadTireRating(tire.tire_id);
    if (!isLoggedIn) checkGuestDuplicate();

    var u = new URL(window.location);
    u.searchParams.set('tire', tire.tire_id);
    window.history.replaceState({}, '', u);
    tireCard.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }

  function loadTireRating(tireId) {
    var starsContainer = $('rvTireStars');
    var ratingText = $('rvTireRatingText');
    starsContainer.innerHTML = '';
    ratingText.textContent = 'Loading...';

    var formData = new FormData();
    formData.append('action', 'get_tire_ratings');
    formData.append('nonce', config.nonce);
    formData.append('tire_ids[]', tireId);

    fetch(config.ajaxurl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!selectedTire || selectedTire.tire_id !== tireId) return;
        var d = data && data.success ? data.data : null;
        if (d && d.ratings && d.ratings[tireId]) {
          var rating = d.ratings[tireId];
          var avg = parseFloat(rating.average) || 0;
          var count = parseInt(rating.count, 10) || 0;
          renderDisplayStars(starsContainer, avg);
          ratingText.textContent = avg > 0 ? avg.toFixed(1) + ' · ' + count + ' review' + (count !== 1 ? 's' : '') : 'No reviews yet';
        } else {
          renderDisplayStars(starsContainer, 0);
          ratingText.textContent = 'No reviews yet';
        }
        if (isLoggedIn && d && d.user_reviews && d.user_reviews[tireId]) {
          existingReview = d.user_reviews[tireId];
          showSignedExistingBanner(existingReview);
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
      if (rounded >= i) span.className = 'star-active';
      else if (rounded >= i - 0.5) span.className = 'star-half-active';
      span.innerHTML = starSVG(18);
      if (rounded >= i - 0.5 && rounded < i) {
        var fill = span.querySelector('.star-fill');
        if (fill) fill.style.clipPath = 'inset(0 50% 0 0)';
      }
      container.appendChild(span);
    }
  }

  function resetSelection() {
    selectedTire = null;
    searchInput.value = '';
    tireCard.classList.remove('visible');
    form.classList.remove('visible');
    successEl.classList.remove('visible');
    if (landing) landing.hidden = false;
    clearForm();
    var u = new URL(window.location);
    u.searchParams.delete('tire');
    window.history.replaceState({}, '', u);
  }

  function clearForm() {
    selectedRating = 0;
    updateStarDisplay();
    AXES.forEach(function(a) { axisValues[a.key] = 0; });
    paintAllAxes();
    $('rvMiles').value = '';
    $('rvOwner').checked = false;
    $('rvReviewTitle').value = '';
    $('rvReviewText').value = '';
    charCount.textContent = '0/5000';
    errorEl.textContent = '';
    ['rvStarError', 'rvWordsError', 'rvNameError', 'rvEmailError'].forEach(function(id) { setFieldError(id, ''); });
    existingReview = null;
    guestBlocked = false;
    hideExistingBanner();
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Review';
  }

  function resetAll() {
    resetSelection();
  }

  // --- Account mode: guests are the main path, the few accounts a banner --
  function setupAccountMode() {
    var guestSection = $('rvGuestSection');
    var signedBanner = $('rvSignedBanner');
    var signedText = $('rvSignedText');
    var ownerHint = $('rvOwnerHint');
    var subtitle = $('rvSubtitle');
    if (!isLoggedIn) {
      // Guests must leave a title or some text: the labels say so.
      $('rvTitleHint').textContent = 'one of title or review is needed';
      $('rvTextHint').textContent = '';
      var signIn = $('rvSignIn');
      if (signIn && config.login_url) signIn.href = config.login_url;
      return;
    }
    if (guestSection) guestSection.hidden = true;
    if (signedBanner) {
      signedBanner.hidden = false;
      var who = config.displayName ? config.displayName : 'your account';
      signedText.textContent = 'Signed in as ' + who + '. ' + (autoApprove ? 'Your review posts right away.' : 'Stars post right away; words wait for a quick check.');
    }
    if (ownerHint) ownerHint.textContent = 'Adds a verified-owner badge to your review.';
    if (subtitle) subtitle.textContent = 'Pick the tire you drove on and rate it. ' + (autoApprove ? 'It posts right away.' : 'Stars post right away; written reviews go live after a quick check.');
    if (footerNote) footerNote.textContent = autoApprove ? 'Posts right away.' : 'Stars post right away. Written reviews go live after a quick check.';
  }

  function restoreGuestInfo() {
    if (isLoggedIn) return;
    var name = '', email = '';
    try {
      name = localStorage.getItem(LS_NAME) || '';
      email = localStorage.getItem(LS_EMAIL) || '';
    } catch (e) { /* private browsing */ }
    if (name) $('rvGuestName').value = name;
    if (email) $('rvGuestEmail').value = email;
    var note = $('rvRememberNote');
    if (note) note.hidden = !(name || email);
    var welcome = $('rvWelcome');
    if (welcome && name) {
      var text = $('rvWelcomeText');
      text.textContent = '';
      text.appendChild(document.createTextNode('Welcome back, '));
      var strong = document.createElement('strong');
      strong.textContent = name;
      text.appendChild(strong);
      text.appendChild(document.createTextNode('. Your name and email are remembered from last time, so the form is shorter.'));
      welcome.hidden = false;
    } else if (welcome) {
      welcome.hidden = true;
    }
  }

  function forgetGuestInfo() {
    try { localStorage.removeItem(LS_NAME); localStorage.removeItem(LS_EMAIL); } catch (e) { /* private browsing */ }
    $('rvGuestName').value = '';
    $('rvGuestEmail').value = '';
    var note = $('rvRememberNote');
    if (note) note.hidden = true;
    var welcome = $('rvWelcome');
    if (welcome) welcome.hidden = true;
    guestBlocked = false;
    hideExistingBanner();
  }

  // --- Existing review banners ---------------------------------------------
  function hideExistingBanner() {
    var b = $('rvExistingBanner');
    if (!b) return;
    b.hidden = true;
    b.textContent = '';
    b.classList.remove('is-block');
    if (!submitBtn.disabled || submitBtn.textContent !== 'Submitting...') submitBtn.disabled = false;
  }

  function monthLabel(value) {
    var m = /^(\d{4})-(\d{2})/.exec(String(value || ''));
    if (!m) return '';
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var month = parseInt(m[2], 10);
    return month >= 1 && month <= 12 ? months[month - 1] + ' ' + m[1] : '';
  }

  function smallStars(n) {
    var wrap = document.createElement('span');
    wrap.className = 'rv-recap-stars';
    wrap.setAttribute('aria-label', n + ' star' + (n !== 1 ? 's' : ''));
    for (var i = 1; i <= 5; i++) {
      var s = document.createElement('span');
      s.className = i <= n ? '' : 'star-empty';
      s.innerHTML = starSVG(13);
      wrap.appendChild(s);
    }
    return wrap;
  }

  function showSignedExistingBanner(review) {
    var b = $('rvExistingBanner');
    if (!b) return;
    b.textContent = '';
    var line = document.createElement('div');
    var when = monthLabel(review.created_at);
    line.appendChild(document.createTextNode('You reviewed this tire' + (when ? ' in ' + when : '') + ' and gave it '));
    line.appendChild(smallStars(parseInt(review.rating, 10) || 0));
    line.appendChild(document.createTextNode(review.review_status === 'pending' ? '. It is waiting for a check. Saving replaces it.' : '. Saving replaces it, and the date stays as first written.'));
    b.appendChild(line);

    var actions = document.createElement('div');
    actions.className = 'rv-banner-actions';
    var load = document.createElement('button');
    load.type = 'button';
    load.className = 'rv-link-btn';
    load.textContent = 'Load it to edit';
    load.addEventListener('click', function() { fillFromReview(review); load.textContent = 'Loaded'; });
    var fresh = document.createElement('button');
    fresh.type = 'button';
    fresh.className = 'rv-link-btn rv-link-muted';
    fresh.textContent = 'Start fresh';
    fresh.addEventListener('click', function() { var keep = existingReview; clearForm(); existingReview = keep; showSignedExistingBanner(keep); });
    var del = document.createElement('button');
    del.type = 'button';
    del.className = 'rv-link-btn rv-link-danger';
    del.textContent = 'Delete my review';
    del.addEventListener('click', function() { deleteOwnReview(del); });
    actions.appendChild(load);
    actions.appendChild(fresh);
    actions.appendChild(del);
    b.appendChild(actions);
    b.hidden = false;
  }

  function fillFromReview(review) {
    selectedRating = parseInt(review.rating, 10) || 0;
    updateStarDisplay();
    AXES.forEach(function(a) { axisValues[a.key] = parseInt(review['rating_' + a.key], 10) || 0; });
    paintAllAxes();
    pickedVehicle = vehicles.indexOf(review.vehicle) !== -1 ? review.vehicle : '';
    paintSeg($('rvVehiclePick'), pickedVehicle);
    $('rvMiles').value = review.miles ? String(review.miles) : '';
    $('rvOwner').checked = !!parseInt(review.is_owner, 10);
    $('rvReviewTitle').value = review.review_title || '';
    $('rvReviewText').value = review.review_text || '';
    charCount.textContent = ($('rvReviewText').value.length) + '/5000';
  }

  function deleteOwnReview(btn) {
    if (!selectedTire) return;
    if (!window.confirm('Delete your review of this tire? This cannot be undone.')) return;
    btn.disabled = true;
    btn.textContent = 'Deleting...';
    var fd = new FormData();
    fd.append('action', 'delete_tire_rating');
    fd.append('tire_id', selectedTire.tire_id);
    fd.append('nonce', config.nonce);
    fetch(config.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data && data.success) {
          clearForm();
          showToast('Your review was deleted.', 'success');
          loadTireRating(selectedTire.tire_id);
        } else {
          btn.disabled = false;
          btn.textContent = 'Delete my review';
          errorEl.textContent = (data && data.data) || 'Could not delete. Please try again.';
        }
      })
      .catch(function() {
        btn.disabled = false;
        btn.textContent = 'Delete my review';
        errorEl.textContent = 'Network error. Please try again.';
      });
  }

  // A returning guest: one review per email per tire, so say so as soon as
  // the email is known, not after they have written everything.
  function checkGuestDuplicate() {
    if (isLoggedIn || !selectedTire) return;
    var email = $('rvGuestEmail').value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      if (guestBlocked) { guestBlocked = false; hideExistingBanner(); }
      return;
    }
    var seq = ++checkSeq;
    var tireId = selectedTire.tire_id;
    var fd = new FormData();
    fd.append('action', 'rtg_check_guest_review');
    fd.append('tire_id', tireId);
    fd.append('guest_email', email);
    fd.append('nonce', config.nonce);
    fd.append('website', form.querySelector('input[name="website"]').value);
    fetch(config.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (seq !== checkSeq || !selectedTire || selectedTire.tire_id !== tireId) return;
        var d = data && data.success ? data.data : null;
        if (d && d.exists) {
          guestBlocked = true;
          showGuestExistingBanner(email, d);
        } else {
          guestBlocked = false;
          hideExistingBanner();
        }
      })
      .catch(function() { /* the server still checks at submit */ });
  }

  function showGuestExistingBanner(email, d) {
    var b = $('rvExistingBanner');
    if (!b) return;
    b.textContent = '';
    b.classList.add('is-block');
    var line = document.createElement('div');
    var strong = document.createElement('strong');
    strong.textContent = email;
    line.appendChild(strong);
    line.appendChild(document.createTextNode(' already reviewed this tire' + (d.month ? ' in ' + d.month : '') + ' and gave it '));
    line.appendChild(smallStars(parseInt(d.rating, 10) || 0));
    line.appendChild(document.createTextNode(d.pending ? '. That review is waiting for a check.' : '.'));
    b.appendChild(line);
    var why = document.createElement('div');
    why.className = 'rv-hint';
    why.textContent = 'One review per email per tire keeps the guide honest. To change what you wrote, reply to the email you got when it was approved.';
    b.appendChild(why);
    var actions = document.createElement('div');
    actions.className = 'rv-banner-actions';
    var change = document.createElement('button');
    change.type = 'button';
    change.className = 'rv-link-btn';
    change.textContent = 'Review a different tire';
    change.addEventListener('click', function() { resetSelection(); searchInput.focus(); });
    var other = document.createElement('button');
    other.type = 'button';
    other.className = 'rv-link-btn rv-link-muted';
    other.textContent = 'Use a different email';
    other.addEventListener('click', function() { $('rvGuestEmail').focus(); $('rvGuestEmail').select(); });
    actions.appendChild(change);
    actions.appendChild(other);
    b.appendChild(actions);
    b.hidden = false;
    submitBtn.disabled = true;
  }

  // --- Form ----------------------------------------------------------------
  function setFieldError(id, msg) {
    var el = $(id);
    if (el) el.textContent = msg || '';
    var input = { rvNameError: 'rvGuestName', rvEmailError: 'rvGuestEmail', rvWordsError: 'rvReviewTitle' }[id];
    if (input && $(input)) $(input).classList.toggle('is-invalid', !!msg);
    if (id === 'rvStarError') starText.classList.toggle('is-invalid', !!msg);
  }

  function setupForm() {
    var textArea = $('rvReviewText');
    textArea.addEventListener('input', function() {
      charCount.textContent = textArea.value.length + '/5000';
      setFieldError('rvWordsError', '');
    });
    $('rvReviewTitle').addEventListener('input', function() { setFieldError('rvWordsError', ''); });

    var miles = $('rvMiles');
    miles.addEventListener('blur', function() {
      var n = parseInt(miles.value.replace(/[^0-9]/g, ''), 10);
      miles.value = n > 0 ? n.toLocaleString() : '';
    });

    if (!isLoggedIn) {
      var emailInput = $('rvGuestEmail');
      var emailTimer;
      emailInput.addEventListener('input', function() {
        setFieldError('rvEmailError', '');
        clearTimeout(emailTimer);
        emailTimer = setTimeout(checkGuestDuplicate, 400);
      });
      emailInput.addEventListener('blur', checkGuestDuplicate);
      $('rvGuestName').addEventListener('input', function() { setFieldError('rvNameError', ''); });
    }

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      submitReview();
    });
  }

  function collectDetails(fd) {
    AXES.forEach(function(a) { if (axisValues[a.key]) fd.append('axis_' + a.key, String(axisValues[a.key])); });
    if (pickedVehicle) fd.append('vehicle', pickedVehicle);
    var miles = parseInt($('rvMiles').value.replace(/[^0-9]/g, ''), 10);
    if (miles > 0) fd.append('miles', String(miles));
    if ($('rvOwner').checked) fd.append('is_owner', '1');
  }

  function submitReview() {
    errorEl.textContent = '';
    if (!selectedTire) { errorEl.textContent = 'Pick a tire first.'; return; }

    var firstBad = null;
    if (selectedRating < 1 || selectedRating > 5) {
      setFieldError('rvStarError', 'Pick a star rating to continue.');
      firstBad = firstBad || starsSelect;
    }
    var reviewTitle = $('rvReviewTitle').value.trim();
    var reviewText = $('rvReviewText').value.trim();
    var honeypot = form.querySelector('input[name="website"]').value;
    var guestName = '', guestEmail = '';

    if (!isLoggedIn) {
      guestName = $('rvGuestName').value.trim();
      guestEmail = $('rvGuestEmail').value.trim();
      if (!reviewTitle && !reviewText) {
        setFieldError('rvWordsError', 'A title or a few words are needed, either one is fine.');
        firstBad = firstBad || $('rvReviewTitle');
      }
      if (!guestName) {
        setFieldError('rvNameError', 'A name to show with the review.');
        firstBad = firstBad || $('rvGuestName');
      }
      if (!guestEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail)) {
        setFieldError('rvEmailError', 'That does not look like a full email address.');
        firstBad = firstBad || $('rvGuestEmail');
      }
      if (guestBlocked) {
        errorEl.textContent = 'This email already reviewed this tire.';
        firstBad = firstBad || $('rvGuestEmail');
      }
    }
    if (firstBad) {
      if (typeof firstBad.focus === 'function') firstBad.focus({ preventScroll: true });
      firstBad.scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    var fd = new FormData();
    fd.append('tire_id', selectedTire.tire_id);
    fd.append('rating', String(selectedRating));
    fd.append('nonce', config.nonce);
    if (reviewTitle) fd.append('review_title', reviewTitle.substring(0, 200));
    if (reviewText) fd.append('review_text', reviewText.substring(0, 5000));
    collectDetails(fd);

    if (isLoggedIn) {
      fd.append('action', 'submit_tire_rating');
    } else {
      fd.append('action', 'submit_guest_tire_rating');
      fd.append('guest_name', guestName.substring(0, 100));
      fd.append('guest_email', guestEmail.substring(0, 254));
      fd.append('website', honeypot);
    }

    fetch(config.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data && data.success) {
          if (!isLoggedIn) {
            try { localStorage.setItem(LS_NAME, guestName); localStorage.setItem(LS_EMAIL, guestEmail); } catch (e) { /* private browsing */ }
          }
          var pending = !isLoggedIn || (data.data && data.data.review_status === 'pending');
          if (selectedTire) reviewCounts[selectedTire.tire_id] = (reviewCounts[selectedTire.tire_id] || 0) + (pending ? 0 : 1);
          showSuccess(pending, guestName, guestEmail);
        } else {
          errorEl.textContent = (data && data.data) || 'Failed to submit. Please try again.';
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

  function showSuccess(isPending, guestName, guestEmail) {
    form.classList.remove('visible');
    var title = $('rvSuccessTitle');
    var text = $('rvSuccessText');
    var tireName = selectedTire ? ((selectedTire.brand || '') + ' ' + (selectedTire.model || '')).trim() : 'this tire';
    var firstName = (guestName || config.displayName || '').split(/\s+/)[0];

    text.textContent = '';
    if (isPending) {
      title.textContent = (firstName ? 'Thanks, ' + firstName + '. ' : 'Thanks. ') + 'It is in the queue.';
      text.appendChild(document.createTextNode('Every review gets a quick look before it goes on the page, which is how the guide stays spam-free. '));
      if (guestEmail) {
        text.appendChild(document.createTextNode('We will email '));
        var strong = document.createElement('strong');
        strong.textContent = guestEmail;
        text.appendChild(strong);
        text.appendChild(document.createTextNode(' when it is live, usually the same day.'));
      } else {
        text.appendChild(document.createTextNode('It is usually live the same day.'));
      }
    } else {
      title.textContent = 'Your review is live';
      text.textContent = 'It is on the ' + tireName + ' page now. ' + (firstName ? 'Thanks, ' + firstName + '. ' : 'Thanks. ') + 'Reviews like this are what the guide runs on.';
    }

    var recap = $('rvSuccessRecap');
    if (recap) {
      recap.textContent = '';
      recap.appendChild(smallStars(selectedRating));
      var parts = ['Your ' + selectedRating + ' star' + (selectedRating !== 1 ? 's' : '')];
      if ($('rvOwner').checked) parts.push('verified owner');
      if (pickedVehicle) parts.push(pickedVehicle);
      var miles = parseInt($('rvMiles').value.replace(/[^0-9]/g, ''), 10);
      if (miles > 0) parts.push(miles.toLocaleString() + ' miles');
      var answered = AXES.filter(function(a) { return axisValues[a.key] > 0; }).length;
      if (answered) parts.push(answered + ' detail' + (answered !== 1 ? 's' : '') + ' rated');
      var span = document.createElement('span');
      span.textContent = parts.join(' · ');
      recap.appendChild(span);
      recap.hidden = false;
    }

    var link = $('rvSuccessTireLink');
    var url = tirePageUrl(selectedTire);
    if (link) {
      link.hidden = !url;
      if (url) {
        link.href = isPending ? url : url + '#rtg-tp-reviews';
        link.textContent = isPending ? 'See the tire page' : 'See your review';
      }
    }

    successEl.classList.add('visible');
    successEl.scrollIntoView({ block: 'start', behavior: 'smooth' });
    showToast(isPending ? 'Review submitted. We will email you when it is live.' : 'Your review is live.', isPending ? 'info' : 'success');
  }

  // --- Helpers ---------------------------------------------------------------
  function findTireById(id) {
    for (var i = 0; i < tires.length; i++) if (tires[i].tire_id === id) return tires[i];
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
    toast.setAttribute('role', 'status');
    var icon = document.createElement('span');
    icon.className = 'rv-toast-icon';
    icon.innerHTML = type === 'info' ? '<i class="fa-solid fa-circle-info" aria-hidden="true"></i>' : '<i class="fa-solid fa-check" aria-hidden="true"></i>';
    var text = document.createElement('span');
    text.textContent = message;
    toast.appendChild(icon);
    toast.appendChild(text);
    container.appendChild(toast);
    requestAnimationFrame(function() { requestAnimationFrame(function() { toast.classList.add('visible'); }); });
    setTimeout(function() {
      toast.classList.remove('visible');
      setTimeout(function() { toast.remove(); }, 300);
    }, 4000);
  }

})();
