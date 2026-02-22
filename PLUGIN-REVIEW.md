# Rivian Tire Guide Plugin — Review & Enhancement Plan

**Plugin:** Rivian Tire Guide v1.0.5
**Reviewed:** 2026-02-15
**Updated:** 2026-02-22 (v1.19.3 — Full audit: 35/41 items resolved)
**Scope:** Security, enhancements, performance, code quality, and UX improvements

---

## Table of Contents

1. [Security Updates](#1-security-updates)
2. [Feature Enhancements](#2-feature-enhancements)
3. [Performance Improvements](#3-performance-improvements)
4. [Code Quality & Maintainability](#4-code-quality--maintainability)
5. [UX / Frontend Improvements](#5-ux--frontend-improvements)
6. [Database & Data Integrity](#6-database--data-integrity)
7. [Testing & Reliability](#7-testing--reliability)

---

## 1. Security Updates

### 1.1 — ~~Rate-limit AJAX rating submissions~~ ✅ Resolved
**File:** `includes/class-rtg-ajax.php`
**Priority:** High
**Status:** Resolved in v1.1.0, tightened in v1.19.0.
**Resolution:** Transient-based rate limiter added — logged-in users: 3 submissions per 5-minute window (`RATE_LIMIT_MAX = 3`, `RATE_LIMIT_WINDOW = 300`). Guest IP-based rate limiting also added in v1.19.0 via `is_guest_rate_limited()` / `record_guest_rate_limit()`.

### 1.2 — ~~Add nonce verification to the `get_tire_ratings` AJAX endpoint~~ ✅ Resolved
**File:** `includes/class-rtg-ajax.php`
**Priority:** Medium
**Status:** Resolved in v1.1.0.
**Resolution:** Nonce verification added for logged-in users via `check_ajax_referer( 'tire_rating_nonce', 'nonce', false )`. Guest (`nopriv`) read requests remain open. Also extended to `get_tire_reviews` and `get_user_reviews` endpoints in v1.19.1.

### 1.3 — ~~Validate tire existence before accepting a rating~~ ✅ Resolved
**File:** `includes/class-rtg-ajax.php`
**Priority:** Medium
**Status:** Resolved in v1.1.0.
**Resolution:** `RTG_Database::get_tire( $tire_id )` check added before `set_rating()` in both `submit_tire_rating()` and `submit_guest_tire_rating()`. Returns `wp_send_json_error( 'Tire not found.' )` if the tire does not exist.

### 1.4 — ~~Add Content-Security-Policy headers on the compare page~~ ✅ Resolved
**File:** `includes/class-rtg-compare.php`
**Priority:** Medium
**Status:** Resolved in v1.1.0.
**Resolution:** Security headers added before template output: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Content-Security-Policy` (restricting script-src, style-src, img-src), and `Referrer-Policy: strict-origin-when-cross-origin`.

### 1.5 — ~~Sanitize CSS custom property values more strictly~~ ✅ Resolved
**File:** `includes/class-rtg-frontend.php`
**Priority:** Low
**Status:** Resolved in v1.1.0.
**Resolution:** All theme color values are re-validated with `sanitize_hex_color()` at render time before being injected into the `:root{}` inline style declaration. Only valid hex colors are output.

### 1.6 — ~~Escape image URL output in `compare.js`~~ ✅ Resolved
**File:** `frontend/js/compare.js`
**Priority:** Medium
**Status:** Resolved in v1.1.0, consolidated in v1.14.0.
**Resolution:** All image URLs routed through `safeImageURL()` (delegating to `RTG_SHARED` module) and `escapeHTML()` applied to all `src` and `alt` attribute values. Domain allowlists consolidated into `rtg-shared.js` in v1.14.0.

### 1.7 — ~~Escape link URLs in `compare.js` to prevent XSS~~ ✅ Resolved
**File:** `frontend/js/compare.js`
**Priority:** High
**Status:** Resolved in v1.1.0, consolidated in v1.14.0.
**Resolution:** All URLs routed through `safeLinkURL()`, `safeReviewLinkURL()`, and `safeImageURL()` from the shared `RTG_SHARED` module before insertion. `escapeHTML()` applied to all attribute values. Domain allowlists consolidated into `rtg-shared.js` (v1.14.0) to eliminate duplicate validation logic.

### 1.8 — ~~Delete `rtg_dropdown_options` on uninstall~~ ✅ Resolved
**File:** `uninstall.php`
**Priority:** Low
**Status:** Resolved in v1.1.0.
**Resolution:** `delete_option( 'rtg_dropdown_options' )` added to the uninstall handler alongside the other option deletions.

---

## 2. Feature Enhancements

### 2.1 — ~~CSV/JSON import and export for tire data~~ ✅ Resolved
**Priority:** High
**Status:** Resolved in v1.2.0, enhanced in v1.8.0.
**Resolution:** Admin Import/Export page (Tire Guide > Import / Export) supports CSV import with duplicate handling (skip/update modes), auto-generated tire IDs, auto-calculated efficiency scores, and full catalog CSV export. MIME type validation added in v1.19.1. 21 columns supported including review_link.

### 2.2 — ~~REST API for tire data~~ ✅ Resolved
**Priority:** Medium
**Status:** Resolved in v1.14.0, rate-limited in v1.15.0.
**Resolution:** Full REST API under `rtg/v1` namespace with four endpoints: `GET /tires` (filtered, paginated listing), `GET /tires/{tire_id}` (single tire with ratings), `GET /tires/{tire_id}/reviews` (paginated reviews), and `POST /efficiency` (calculate efficiency score). All inputs validated and sanitized. Per-IP rate limiting (60 req/min reads, 10 req/min writes) added in v1.15.0.

### 2.3 — ~~User review text alongside star ratings~~ ✅ Resolved
**Priority:** Medium
**Status:** Resolved in v1.7.0, moderation in v1.7.1, guest reviews in v1.19.0.
**Resolution:** Full text review system with optional title (200 chars) and body (5,000 chars). Review modal for submission/editing. Admin moderation queue (Tire Guide > Reviews) with approve/reject/delete. Reviews drawer on each tire card. Schema.org `Review` objects in JSON-LD. Guest reviews with name/email added in v1.19.0.

### 2.4 — Allow users to delete their own ratings
**Priority:** Medium
**Issue:** Once a user submits a rating, they can only change it — they cannot remove it. Only admins can delete ratings.
**Recommendation:** Add a `delete_tire_rating` AJAX endpoint that allows logged-in users to delete their own rating (matching `user_id`).

### 2.5 — ~~Shortcode attributes for filtering~~ ✅ Resolved
**Priority:** Low
**Status:** Resolved in v1.14.0.
**Resolution:** The `[rivian_tire_guide]` shortcode now accepts optional pre-filter attributes: `size`, `brand`, `category`, `sort`, and `3pms`. Example: `[rivian_tire_guide brand="Michelin" category="All-Season" sort="price-asc"]`. Attributes validated and passed to frontend via `shortcode_atts()`.

### 2.6 — Admin dashboard widget with quick stats
**Priority:** Low
**Issue:** Plugin stats (total tires, average rating, most-viewed tires) are only accessible from the plugin's own admin pages.
**Recommendation:** Add a WordPress dashboard widget showing key metrics at a glance.

### 2.7 — ~~Schema.org structured data for tire products~~ ✅ Resolved
**Priority:** Medium
**Status:** Resolved in v1.1.0, enhanced in v1.7.0.
**Resolution:** Automatic JSON-LD output (`Product` + `AggregateRating` + `ItemList`) on pages using the shortcode. Individual `Review` objects (up to 5 per tire) added in v1.7.0 for rich snippet eligibility. Guest author names handled in v1.19.0. Implemented in `class-rtg-schema.php`.

### 2.8 — ~~Email notifications for new ratings (admin)~~ ✅ Resolved
**Priority:** Low
**Status:** Resolved in v1.19.0.
**Resolution:** `RTG_Mailer` class sends styled HTML emails via `wp_mail()`: admin notification on guest review submission (with review details, star rating, and moderation link), and reviewer approval notification when reviews are approved. Respects site SMTP configuration.

---

## 3. Performance Improvements

### 3.1 — ~~Server-side pagination instead of loading all tires into JS~~ ✅ Resolved
**File:** `includes/class-rtg-frontend.php`, `includes/class-rtg-ajax.php`
**Priority:** High
**Status:** Resolved in v1.3.0.
**Resolution:** Hybrid approach implemented — optional server-side pagination mode (Settings toggle). When enabled, `rtg_get_tires` AJAX endpoint provides full server-side filtering, sorting, and pagination. Client-side mode still available for smaller catalogs. Tire data only embedded inline when client-side mode is active (`class-rtg-frontend.php:180`).

### 3.2 — ~~Add database version migration system~~ ✅ Resolved
**File:** `includes/class-rtg-activator.php`
**Priority:** Medium
**Status:** Resolved in v1.3.0.
**Resolution:** Numbered migration system with `DB_VERSION` constant (currently 11) and `rtg_db_version` option. `run_migrations()` applies incremental schema changes sequentially. `maybe_upgrade()` runs on `plugins_loaded` to auto-apply pending migrations on plugin update. 11 migrations implemented covering schema additions, index creation, and column changes.

### 3.3 — ~~Cache expensive database queries~~ ✅ Resolved
**File:** `includes/class-rtg-database.php`
**Priority:** Medium
**Status:** Resolved in v1.2.0.
**Resolution:** WordPress transient caching (`get_transient` / `set_transient`) with 1-hour TTL added to `get_all_tires()`. Cache automatically invalidated on tire insert, update, and delete operations.

### 3.4 — ~~Lazy-load Font Awesome or use a subset~~ ✅ Resolved
**File:** `includes/class-rtg-icons.php`, `frontend/js/modules/helpers.js`
**Priority:** Low
**Status:** Resolved in v1.15.0.
**Resolution:** Font Awesome CDN dependency (~60 KB CSS + web fonts) completely replaced with lightweight inline SVGs. New `RTG_Icons` PHP class and `rtgIcon()` JS helper render icons from a shared map of ~35 SVG paths. CSP headers updated to remove the CDN allowance.

### 3.5 — ~~Add lazy loading to tire card images~~ ✅ Resolved
**Priority:** Low
**Status:** Resolved in v1.10.0, refined in v1.19.2.
**Resolution:** `IntersectionObserver`-based lazy loading with `data-src` pattern and shimmer placeholder animation. Images fade in smoothly on load. Root margin of 600px ensures images load well before scrolling into view. Compare page images use native `loading="lazy"`. `decoding="async"` added for non-blocking decode.

---

## 4. Code Quality & Maintainability

### 4.1 — ~~Split `rivian-tires.js` into ES modules~~ ✅ Resolved
**File:** `frontend/js/modules/`
**Priority:** Medium
**Status:** Resolved in v1.15.0.
**Resolution:** Main JS split into 14 focused ES modules: `state.js`, `helpers.js`, `validation.js`, `analytics.js`, `tooltips.js`, `search.js`, `ratings.js`, `cards.js`, `favorites.js`, `compare.js`, `filters.js`, `server.js`, `image-modal.js`, and `ai-recommend.js`. esbuild build pipeline bundles and minifies for production with tree-shaking.

### 4.2 — ~~Remove `console.time` / `console.warn` calls from production~~ ✅ Resolved
**Priority:** Low
**Status:** Resolved in v1.15.0.
**Resolution:** esbuild build pipeline automatically strips all `console` and `debugger` statements from production `.min.js` builds via the `drop` option. Source files retain console calls for development debugging; they never reach production output.

### 4.3 — Use a PHP autoloader instead of manual `require_once`
**File:** `rivian-tire-guide.php:23-29`
**Priority:** Low
**Issue:** All class files are manually required at the top of the main plugin file. As the plugin grows, this becomes error-prone.
**Recommendation:** Implement a PSR-4 compatible autoloader or use `spl_autoload_register()` to automatically load classes from the `includes/` directory based on class name.

### 4.4 — ~~Consolidate duplicate URL validation logic~~ ✅ Resolved
**Files:** `frontend/js/modules/validation.js`, `frontend/js/rtg-shared.js`
**Priority:** Medium
**Status:** Resolved in v1.14.0.
**Resolution:** Shared `escapeHTML`, `safeImageURL`, `safeLinkURL`, and `safeReviewLinkURL` extracted into `rtg-shared.js` with a single domain allowlist. The compare page delegates to `RTG_SHARED` module. Main tire guide imports from `modules/validation.js`. No duplicate implementations remain.

### 4.5 — ~~Consolidate duplicate efficiency calculation logic~~ ✅ Resolved
**Files:** `includes/class-rtg-database.php`, `admin/js/admin-scripts.js`
**Priority:** Medium
**Status:** Resolved in v1.14.0.
**Resolution:** The duplicate 95-line JS efficiency formula in `admin-scripts.js` was replaced with a debounced AJAX call to the canonical PHP `RTG_Database::calculate_efficiency()` via the `rtg_calculate_efficiency` action. The formula now exists only in PHP — a single source of truth.

### 4.6 — Add PHPDoc blocks to all public methods
**Priority:** Low
**Issue:** Many public methods in `RTG_Database` and `RTG_Admin` lack PHPDoc blocks (e.g., `get_tire()`, `insert_tire()`, `delete_tire()`, `update_tire()`, `search_tires()`). Parameter types, return types, and descriptions are missing.
**Recommendation:** Add `@param`, `@return`, and description blocks to all public methods for IDE support and documentation generation.
**Progress:** Some methods have PHPDoc (e.g., `get_filtered_tires()`, `calculate_efficiency()`, `get_tires_by_ids()`), but 35+ public methods in `RTG_Database` and `RTG_Admin` still lack them.

---

## 5. UX / Frontend Improvements

### 5.1 — ~~Persist filter state in URL parameters~~ ✅ Resolved
**Priority:** High
**Status:** Resolved in v1.10.0.
**Resolution:** All filter state synced to URL query parameters via `updateURLFromFilters()` using `history.pushState()`. On page load, `applyFiltersFromURL()` parses URL params and restores all filter state (search, size, brand, category, 3PMS, EV, studded, reviewed, favorites, price/warranty/weight sliders, sort, page). `popstate` listener enables browser back/forward through filter history. Shareable filtered URLs fully functional (e.g., `?brand=Michelin&size=275/65R18&sort=price-asc`).

### 5.2 — ~~Mobile-friendly range slider interaction~~ ✅ Resolved
**Priority:** Medium
**Status:** Resolved in v1.15.0.
**Resolution:** Editable number inputs alongside range sliders on mobile (visible below 600px breakpoint). Number inputs and sliders sync bidirectionally. Slider thumbs have larger 28px touch targets on mobile for easier interaction.

### 5.3 — ~~"Back to guide" link on compare page~~ ✅ Resolved
**File:** `frontend/templates/compare.php`
**Priority:** Low
**Status:** Resolved in v1.7.8.
**Resolution:** "Back to Tire Guide" link with arrow icon added in the compare page top bar (`.cmp-topbar-left`), linking to the tire guide page via `home_url( '/rivian-tire-guide/' )`.

### 5.4 — ~~Skeleton/loading state for tire cards~~ ✅ Resolved
**Priority:** Medium
**Status:** Resolved in v1.14.0.
**Resolution:** CSS shimmer placeholder cards (`.rtg-skeleton-grid`, `.rtg-skeleton-card`) display immediately while tire data loads. Animated gradient shimmer effect via `@keyframes rtg-shimmer`. Respects `prefers-reduced-motion`. Skeleton includes image, title, subtitle, text, stars, and badge placeholders.

### 5.5 — ~~Accessibility (a11y) improvements~~ ✅ Resolved
**Priority:** High
**Status:** Resolved across v1.2.0 and v1.14.0.
**Resolution:** All listed concerns addressed:
- **Star ratings:** ARIA `role`, `aria-label`, `aria-checked` attributes + full keyboard navigation (arrow keys, Enter/Space) — v1.2.0.
- **Filter toggles:** `aria-expanded` / `aria-controls` on mobile filter toggle and wheel drawer trigger — v1.2.0.
- **Tooltips:** Clickable tooltip triggers with modal display (not hover-only) — v1.14.0.
- **Compare checkboxes:** Descriptive `aria-label` attributes — v1.2.0.
- **Additional a11y (v1.14.0):** Skip-to-content link, `aria-label` on all filter controls/search/sort, `role="status"` + `aria-live="polite"` on no-results, screen-reader-only labels, `focus-visible` outline styles on all interactive elements, `.screen-reader-text` utility class.

### 5.6 — ~~Improve the "No results" state~~ ✅ Resolved
**Priority:** Low
**Status:** Resolved in v1.10.0.
**Resolution:** Smart no-results view lists the number and names of active filters, displays up to 4 actionable suggestion buttons to remove specific filters (e.g., "Remove size filter", "Show all brands", "Clear all filters"), and shows a fallback "Search RivianTrackr" link for non-tire queries.

### 5.7 — ~~Add print stylesheet for comparison page~~ ✅ Resolved
**Priority:** Low
**Status:** Resolved in v1.4.0 (tire cards), compare page print styles added separately.
**Resolution:** `@media print` styles switch to white background with dark text, hide interactive elements (top bar, CTA buttons, compare checkboxes), and use light-friendly border colors. Tire card print styles also added in v1.4.0.

---

## 6. Database & Data Integrity

### 6.1 — ~~Add cascade delete for orphaned ratings~~ ✅ Resolved
**File:** `includes/class-rtg-database.php`
**Priority:** Medium
**Status:** Resolved in v1.2.0.
**Resolution:** `delete_tire()` and `delete_tires()` now delete associated ratings before removing tires. PHPUnit tests in `test-database.php` verify cascade deletion behavior.

### 6.2 — ~~Add database table existence check on plugin load~~ ✅ Resolved
**Priority:** Low
**Status:** Resolved in v1.3.0.
**Resolution:** `RTG_Activator::maybe_upgrade()` runs on `plugins_loaded` and checks the stored `rtg_db_version` against `DB_VERSION`. If the installed version is behind, `create_tables()` (using idempotent `dbDelta()`) and `run_migrations()` are re-executed to repair or update the schema.

### 6.3 — ~~Validate `tire_id` format consistency~~ ✅ Resolved
**File:** `includes/class-rtg-database.php`, `frontend/js/modules/validation.js`
**Priority:** Low
**Status:** Resolved across multiple versions.
**Resolution:** Tire ID format validation enforced in both PHP (`preg_match( '/^[a-zA-Z0-9\-_]+$/' )` with 50-char max length) and JavaScript (`VALIDATION_PATTERNS.tireId` regex: `/^[a-zA-Z0-9\-_]+$/`). CSV import rows with invalid tire IDs are rejected.

---

## 7. Testing & Reliability

### 7.1 — ~~Add PHPUnit test suite~~ ✅ Resolved
**Priority:** High
**Status:** Resolved in v1.3.0 (PHP), v1.14.0 (JS).
**Resolution:** Two test suites implemented:
- **PHPUnit** (`tests/test-database.php`): 21 tests covering tire CRUD, ratings upsert, cascade deletes, cache invalidation, efficiency calculation, filtered pagination, and bulk operations. Bootstrap at `tests/bootstrap.php`.
- **JavaScript** (`tests/test-validation.js`): 83 tests covering `escapeHTML`, `sanitizeInput`, `validateNumeric`, `safeImageURL`, `safeLinkURL`, and `fuzzyMatch`.
- **CI:** GitHub Actions workflow runs JS tests, build verification, and PHP syntax linting (PHP 7.4, 8.0, 8.2) on every push/PR — v1.15.0.

### 7.2 — ~~Add JavaScript unit tests~~ ✅ Resolved
**Priority:** Medium
**Status:** Resolved in v1.14.0.
**Resolution:** 83-test suite in `tests/test-validation.js` covering `escapeHTML`, `sanitizeInput`, `validateNumeric`, `safeImageURL`, `safeLinkURL`, and `fuzzyMatch`. Standalone runner (no dependencies) via `node tests/test-validation.js`. Integrated into GitHub Actions CI in v1.15.0.

### 7.3 — Add integration tests for AJAX endpoints
**Priority:** Medium
**Issue:** The AJAX rating endpoints (`get_tire_ratings`, `submit_tire_rating`) have no integration tests verifying correct responses, error handling, and nonce validation.
**Recommendation:** Add integration tests that simulate AJAX requests with valid/invalid nonces, valid/invalid tire IDs, and various edge cases.

### 7.4 — Add PHP linting and coding standards enforcement
**Priority:** Low
**Issue:** No `.phpcs.xml` or linting configuration exists. Code style is consistent but not enforced.
**Recommendation:** Add `phpcs.xml` configured for WordPress Coding Standards and integrate it into the development workflow (pre-commit hook or CI).

---

## Summary Priority Matrix

| Priority | Count | Items |
|----------|-------|-------|
| **High** | 7 — **all resolved** | ~~Rate limiting (1.1)~~ ✅ v1.1.0, ~~Compare page XSS (1.7)~~ ✅ v1.1.0, ~~CSV Import/Export (2.1)~~ ✅ v1.2.0, ~~Server-side pagination (3.1)~~ ✅ v1.3.0, ~~URL filter persistence (5.1)~~ ✅ v1.10.0, ~~Accessibility (5.5)~~ ✅ v1.2.0+v1.14.0, ~~PHPUnit tests (7.1)~~ ✅ v1.3.0 |
| **Medium** | 18 — **16 resolved, 2 open** | ~~Nonce on read endpoint (1.2)~~ ✅ v1.1.0, ~~Validate tire existence (1.3)~~ ✅ v1.1.0, ~~CSP headers (1.4)~~ ✅ v1.1.0, ~~Compare image escaping (1.6)~~ ✅ v1.1.0, ~~REST API (2.2)~~ ✅ v1.14.0, ~~User reviews (2.3)~~ ✅ v1.7.0, Delete own rating (2.4), ~~Schema.org (2.7)~~ ✅ v1.1.0, ~~DB migrations (3.2)~~ ✅ v1.3.0, ~~Query caching (3.3)~~ ✅ v1.2.0, ~~JS modules (4.1)~~ ✅ v1.15.0, ~~Duplicate URL validation (4.4)~~ ✅ v1.14.0, ~~Duplicate efficiency calc (4.5)~~ ✅ v1.14.0, ~~Mobile sliders (5.2)~~ ✅ v1.15.0, ~~Skeleton states (5.4)~~ ✅ v1.14.0, ~~Orphaned ratings (6.1)~~ ✅ v1.2.0, ~~JS tests (7.2)~~ ✅ v1.14.0, AJAX tests (7.3) |
| **Low** | 16 — **12 resolved, 4 open** | ~~CSS re-validation (1.5)~~ ✅ v1.1.0, ~~Uninstall cleanup (1.8)~~ ✅ v1.1.0, ~~Shortcode attributes (2.5)~~ ✅ v1.14.0, Dashboard widget (2.6), ~~Email notifications (2.8)~~ ✅ v1.19.0, ~~Font Awesome subset (3.4)~~ ✅ v1.15.0, ~~Lazy images (3.5)~~ ✅ v1.10.0, ~~Console cleanup (4.2)~~ ✅ v1.15.0, Autoloader (4.3), PHPDoc (4.6), ~~Back link (5.3)~~ ✅ v1.7.8, ~~No results UX (5.6)~~ ✅ v1.10.0, ~~Print stylesheet (5.7)~~ ✅ v1.4.0, ~~DB table check (6.2)~~ ✅ v1.3.0, ~~Tire ID format (6.3)~~ ✅, PHP linting (7.4) |

> **Status (audited 2026-02-22):** 35 of 41 items resolved (85%). 6 items remain open: Delete own rating (2.4), Dashboard widget (2.6), PHP autoloader (4.3), PHPDoc blocks (4.6), AJAX integration tests (7.3), PHP linting/coding standards (7.4).
