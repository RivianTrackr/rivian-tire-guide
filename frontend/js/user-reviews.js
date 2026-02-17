(function () {
  "use strict";

  var tireGuideUrl = (typeof rtgUserReviews !== "undefined" && rtgUserReviews.tireGuideUrl) || "";

  // Render back link
  var backContainer = document.getElementById("rtg-user-reviews-back");
  if (backContainer && tireGuideUrl) {
    var backLink = document.createElement("a");
    backLink.href = tireGuideUrl;
    backLink.className = "rtg-user-reviews-back-link";
    backLink.innerHTML = '<i class="fa-solid fa-arrow-left"></i> Back to Tire Guide';
    backContainer.appendChild(backLink);
  }

  const params = new URLSearchParams(window.location.search);
  const userId = parseInt(params.get("reviewer"), 10);

  if (!userId) {
    const list = document.getElementById("rtg-user-reviews-list");
    if (list) list.innerHTML = '<div class="rtg-reviews-empty"><div class="rtg-reviews-empty-heading">No reviewer specified</div></div>';
    return;
  }

  loadUserReviews(1);

  function loadUserReviews(page) {
    const list = document.getElementById("rtg-user-reviews-list");
    if (!list) return;

    list.innerHTML = '<div class="rtg-reviews-loading">Loading reviews...</div>';

    const fd = new FormData();
    fd.append("action", "rtg_get_user_reviews");
    fd.append("user_id", userId);
    fd.append("page", page);

    fetch(rtgUserReviews.ajaxurl, { method: "POST", body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          list.innerHTML = '<div class="rtg-reviews-empty"><div class="rtg-reviews-empty-heading">Could not load reviews</div></div>';
          return;
        }

        var d = data.data;

        // Update header
        var titleEl = document.getElementById("rtg-user-reviews-title");
        if (titleEl) titleEl.textContent = "Reviews by " + d.display_name;

        var countEl = document.getElementById("rtg-user-reviews-count");
        if (countEl) countEl.textContent = d.total + " review" + (d.total !== 1 ? "s" : "");

        if (!d.reviews || !d.reviews.length) {
          list.innerHTML = '<div class="rtg-reviews-empty"><div class="rtg-reviews-empty-icon">\u270D\uFE0F</div><div class="rtg-reviews-empty-heading">No reviews yet</div></div>';
          return;
        }

        list.innerHTML = "";

        d.reviews.forEach(function (review) {
          list.appendChild(createUserReviewCard(review));
        });

        renderPagination(d.page, d.total_pages);
      })
      .catch(function () {
        list.innerHTML = '<div class="rtg-reviews-empty"><div class="rtg-reviews-empty-heading">Failed to load reviews</div></div>';
      });
  }

  function buildTireUrl(review) {
    if (!tireGuideUrl || !review.tire_id) return "";
    var url = new URL(tireGuideUrl);
    url.searchParams.set("tire", review.tire_id);
    return url.toString();
  }

  function createUserReviewCard(review) {
    var card = document.createElement("div");
    card.className = "rtg-review-card rtg-user-review-card";

    // Tire info header
    var tireInfo = document.createElement("div");
    tireInfo.className = "rtg-user-review-tire";

    var tireUrl = buildTireUrl(review);

    if (review.image) {
      var img = document.createElement("img");
      img.src = review.image;
      img.alt = (review.brand || "") + " " + (review.model || "");
      img.className = "rtg-user-review-tire-img";
      img.loading = "lazy";
      tireInfo.appendChild(img);
    }

    var tireLabel = (review.brand || "") + " " + (review.model || "");
    if (tireUrl) {
      var tireLink = document.createElement("a");
      tireLink.href = tireUrl;
      tireLink.className = "rtg-user-review-tire-name rtg-user-review-tire-link";
      tireLink.textContent = tireLabel;
      tireInfo.appendChild(tireLink);
    } else {
      var tireName = document.createElement("span");
      tireName.className = "rtg-user-review-tire-name";
      tireName.textContent = tireLabel;
      tireInfo.appendChild(tireName);
    }

    card.appendChild(tireInfo);

    // Rating + date row
    var meta = document.createElement("div");
    meta.className = "rtg-review-card-header";

    var starsEl = document.createElement("span");
    starsEl.className = "rtg-review-card-stars";
    starsEl.innerHTML = renderStars(review.rating);
    meta.appendChild(starsEl);

    var dateEl = document.createElement("span");
    dateEl.className = "rtg-review-date";
    dateEl.textContent = formatDate(review.updated_at || review.created_at);
    meta.appendChild(dateEl);

    card.appendChild(meta);

    if (review.review_title) {
      var titleEl = document.createElement("div");
      titleEl.className = "rtg-review-card-title";
      titleEl.textContent = review.review_title;
      card.appendChild(titleEl);
    }

    var bodyEl = document.createElement("div");
    bodyEl.className = "rtg-review-card-body";
    bodyEl.textContent = review.review_text;
    card.appendChild(bodyEl);

    return card;
  }

  function renderStars(rating) {
    var rounded = Math.round(rating);
    var html = "";
    for (var i = 1; i <= 5; i++) {
      html += '<span class="rtg-mini-star' + (i <= rounded ? " filled" : "") + '">\u2605</span>';
    }
    return html;
  }

  function formatDate(dateStr) {
    if (!dateStr) return "";
    var normalized = dateStr.indexOf("T") === -1 && dateStr.indexOf("Z") === -1
      ? dateStr.replace(" ", "T") + "Z"
      : dateStr;
    var date = new Date(normalized);
    if (isNaN(date.getTime())) return dateStr;

    var now = new Date();
    var diffMs = now - date;
    var diffDays = Math.floor(diffMs / 86400000);

    if (diffDays === 0) return "Today";
    if (diffDays === 1) return "Yesterday";
    if (diffDays < 7) return diffDays + " days ago";

    return date.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
  }

  function renderPagination(currentPage, totalPages) {
    var container = document.getElementById("rtg-user-reviews-pagination");
    if (!container) return;
    container.innerHTML = "";

    if (totalPages <= 1) return;

    if (currentPage > 1) {
      var prev = document.createElement("button");
      prev.className = "rtg-pagination-btn";
      prev.innerHTML = '<i class="fa-solid fa-chevron-left"></i> Previous';
      prev.addEventListener("click", function () { loadUserReviews(currentPage - 1); });
      container.appendChild(prev);
    }

    var info = document.createElement("span");
    info.className = "rtg-pagination-info";
    info.textContent = "Page " + currentPage + " of " + totalPages;
    container.appendChild(info);

    if (currentPage < totalPages) {
      var next = document.createElement("button");
      next.className = "rtg-pagination-btn";
      next.innerHTML = 'Next <i class="fa-solid fa-chevron-right"></i>';
      next.addEventListener("click", function () { loadUserReviews(currentPage + 1); });
      container.appendChild(next);
    }
  }
})();
