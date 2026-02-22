# Rivian Tire Guide Plugin — Review & Enhancement Plan

**Plugin:** Rivian Tire Guide v1.0.5
**Reviewed:** 2026-02-15
**Updated:** 2026-02-22 (v1.19.3 — High-priority audit)
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

### 1.2 — Add nonce verification to the `get_tire_ratings` AJAX endpoint
**File:** `includes/class-rtg-ajax.php:21-52`
**Priority:** Medium
**Issue:** The `get_tire_ratings` endpoint has no nonce check. While it's read-only and intentionally public, any external site could make cross-origin POST requests to fetch tire rating data.
**Recommendation:** Add a nonce check for logged-in users (keep `nopriv` open for public reads but add basic origin validation or use a nonce on the frontend form). Alternatively, switch this endpoint to use `GET` requests since it is read-only, which would make it more semantically correct and less prone to CSRF concerns.

### 1.3 — Validate tire existence before accepting a rating
**File:** `includes/class-rtg-ajax.php:82`
**Priority:** Medium
**Issue:** `set_rating()` is called without first verifying the `tire_id` exists in the `rtg_tires` table. Users can submit ratings for non-existent tires, polluting the ratings table with orphaned records.
**Recommendation:** Add a `RTG_Database::get_tire($tire_id)` check before saving the rating and return an error if the tire does not exist.

### 1.4 — Add Content-Security-Policy headers on the compare page
**File:** `includes/class-rtg-compare.php:37-66`
**Priority:** Medium
**Issue:** The compare page (`compare.php`) renders a full HTML page independently via `template_redirect`, bypassing the WordPress theme's headers. No CSP or security headers are set.
**Recommendation:** Add appropriate security headers (`X-Content-Type-Options`, `X-Frame-Options`, and a `Content-Security-Policy` for script/style sources) before outputting the compare template.

### 1.5 — Sanitize CSS custom property values more strictly
**File:** `includes/class-rtg-frontend.php:74-81`
**Priority:** Low
**Issue:** Theme color values are validated as `#hex` on save (`class-rtg-admin.php:293`) but when output as inline CSS, the stored value is inserted directly into the `:root{}` declaration without re-escaping. If the stored option were tampered with (e.g., via direct DB manipulation), it could inject arbitrary CSS.
**Recommendation:** Re-validate or escape color values at render time with `sanitize_hex_color()` before injecting into inline styles.

### 1.6 — Escape image URL output in `compare.js`
**File:** `frontend/js/compare.js:148`
**Priority:** Medium
**Issue:** The comparison table renders image URLs directly into `<img src="">` without using `safeImageURL()` for all cases. Lines 136-157 check `cellValue.startsWith("http")` but do not call `safeImageURL()` — the function exists in the file but isn't used for comparison image rendering.
**Recommendation:** Route all image URLs through `safeImageURL()` before inserting into HTML, and apply `escapeHTML()` to any URL used in an `href` or `src` attribute.

### 1.7 — ~~Escape link URLs in `compare.js` to prevent XSS~~ ✅ Resolved
**File:** `frontend/js/compare.js`
**Priority:** High
**Status:** Resolved in v1.1.0, consolidated in v1.14.0.
**Resolution:** All URLs routed through `safeLinkURL()`, `safeReviewLinkURL()`, and `safeImageURL()` from the shared `RTG_SHARED` module before insertion. `escapeHTML()` applied to all attribute values. Domain allowlists consolidated into `rtg-shared.js` (v1.14.0) to eliminate duplicate validation logic.

### 1.8 — Delete `rtg_dropdown_options` on uninstall
**File:** `uninstall.php:11-13`
**Priority:** Low
**Issue:** The uninstall handler removes `rtg_version`, `rtg_settings`, and `rtg_flush_rewrite` but does not remove `rtg_dropdown_options`, leaving orphaned data in the options table.
**Recommendation:** Add `delete_option('rtg_dropdown_options');` to the uninstall handler.

---

## 2. Feature Enhancements

### 2.1 — ~~CSV/JSON import and export for tire data~~ ✅ Resolved
**Priority:** High
**Status:** Resolved in v1.2.0, enhanced in v1.8.0.
**Resolution:** Admin Import/Export page (Tire Guide > Import / Export) supports CSV import with duplicate handling (skip/update modes), auto-generated tire IDs, auto-calculated efficiency scores, and full catalog CSV export. MIME type validation added in v1.19.1. 21 columns supported including review_link.

### 2.2 — REST API for tire data
**Priority:** Medium
**Issue:** The plugin only exposes tire data via `wp_localize_script` (inline JS) and the shortcode. There's no programmatic access for external integrations, mobile apps, or headless setups.
**Recommendation:** Register custom REST API endpoints (`/wp-json/rtg/v1/tires`, `/wp-json/rtg/v1/tires/{tire_id}`) with proper permission callbacks and schema validation.

### 2.3 — User review text alongside star ratings
**Priority:** Medium
**Issue:** The rating system is limited to 1-5 stars with no text reviews. Users can't explain why they rated a tire a certain way.
**Recommendation:** Add an optional review text field (with moderation queue in admin). Display approved reviews alongside star ratings on tire cards.

### 2.4 — Allow users to delete their own ratings
**Priority:** Medium
**Issue:** Once a user submits a rating, they can only change it — they cannot remove it. Only admins can delete ratings.
**Recommendation:** Add a `delete_tire_rating` AJAX endpoint that allows logged-in users to delete their own rating (matching `user_id`).

### 2.5 — Shortcode attributes for filtering
**Priority:** Low
**Issue:** The `[rivian_tire_guide]` shortcode accepts no attributes. If the shortcode is placed on multiple pages, it always shows the full tire catalog.
**Recommendation:** Support attributes like `[rivian_tire_guide category="All-Season" size="275/65R18"]` to pre-filter the initial tire list on different pages.

### 2.6 — Admin dashboard widget with quick stats
**Priority:** Low
**Issue:** Plugin stats (total tires, average rating, most-viewed tires) are only accessible from the plugin's own admin pages.
**Recommendation:** Add a WordPress dashboard widget showing key metrics at a glance.

### 2.7 — Schema.org structured data for tire products
**Priority:** Medium
**Issue:** Tire data has no structured data markup. Search engines can't understand the product information on the page.
**Recommendation:** Output `Product` and `AggregateRating` JSON-LD structured data for each tire in the frontend, improving SEO and enabling rich snippets.

### 2.8 — Email notifications for new ratings (admin)
**Priority:** Low
**Issue:** Admins have no way to know when new ratings are submitted without manually checking the ratings page.
**Recommendation:** Add an optional admin email notification when new ratings are submitted, configurable in settings.

---

## 3. Performance Improvements

### 3.1 — ~~Server-side pagination instead of loading all tires into JS~~ ✅ Resolved
**File:** `includes/class-rtg-frontend.php`, `includes/class-rtg-ajax.php`
**Priority:** High
**Status:** Resolved in v1.3.0.
**Resolution:** Hybrid approach implemented — optional server-side pagination mode (Settings toggle). When enabled, `rtg_get_tires` AJAX endpoint provides full server-side filtering, sorting, and pagination. Client-side mode still available for smaller catalogs. Tire data only embedded inline when client-side mode is active (`class-rtg-frontend.php:180`).

### 3.2 — Add database version migration system
**File:** `includes/class-rtg-activator.php`
**Priority:** Medium
**Issue:** The activator runs `dbDelta()` on every activation but has no versioned migration system. Schema changes across plugin updates could be fragile.
**Recommendation:** Add a `rtg_db_version` option and a migration runner that applies incremental schema changes based on the stored version number.

### 3.3 — Cache expensive database queries
**File:** `includes/class-rtg-database.php:20-23`
**Priority:** Medium
**Issue:** `get_all_tires()` and `get_tires_as_array()` run full table scans on every page load (no caching). If the shortcode appears on a high-traffic page, this creates unnecessary DB load.
**Recommendation:** Use WordPress object caching (`wp_cache_get/set`) or transients to cache the tire data. Invalidate the cache on tire CRUD operations.

### 3.4 — Lazy-load Font Awesome or use a subset
**File:** `includes/class-rtg-frontend.php:43-48`, `includes/class-rtg-compare.php:43-48`
**Priority:** Low
**Issue:** The full Font Awesome 6.5.0 CSS (60+ KB) is loaded from CDN. The plugin only uses a handful of icons.
**Recommendation:** Use a Font Awesome kit with only the required icons, or switch to inline SVGs for the ~15 icons actually used. This would cut CSS payload significantly.

### 3.5 — Add `loading="lazy"` to tire card images
**Priority:** Low
**Issue:** Tire card images are rendered without native lazy loading, meaning all visible and off-screen images are fetched immediately.
**Recommendation:** Add `loading="lazy"` and explicit `width`/`height` attributes to `<img>` tags in tire card rendering to improve initial page load and LCP (Largest Contentful Paint).

---

## 4. Code Quality & Maintainability

### 4.1 — Split `rivian-tires.js` into ES modules
**File:** `frontend/js/rivian-tires.js` (~1000+ lines)
**Priority:** Medium
**Issue:** The main frontend JS is a single monolithic file with all functionality (search, filtering, rendering, ratings, tooltips, suggestions, URL validation). This makes maintenance and debugging difficult.
**Recommendation:** Split into focused modules (e.g., `filter.js`, `search.js`, `ratings.js`, `compare.js`, `validation.js`) and use a build tool (esbuild, webpack, or rollup) to bundle them. This also enables tree-shaking unused code.

### 4.2 — Remove `console.time` / `console.warn` calls from production
**File:** `frontend/js/rivian-tires.js:360`, and various `console.warn` calls throughout
**Priority:** Low
**Issue:** Debug logging (`console.time('Building search index')`, `console.warn(...)`) is present in production code. This leaks internal validation details to anyone with DevTools open.
**Recommendation:** Strip console calls for production via a build step, or wrap them in a debug flag (`if (RTG_DEBUG) { ... }`).

### 4.3 — Use a PHP autoloader instead of manual `require_once`
**File:** `rivian-tire-guide.php:23-29`
**Priority:** Low
**Issue:** All class files are manually required at the top of the main plugin file. As the plugin grows, this becomes error-prone.
**Recommendation:** Implement a PSR-4 compatible autoloader or use `spl_autoload_register()` to automatically load classes from the `includes/` directory based on class name.

### 4.4 — Consolidate duplicate URL validation logic
**Files:** `frontend/js/rivian-tires.js:171-296`, `frontend/js/compare.js:7-20`
**Priority:** Medium
**Issue:** URL validation functions (`safeImageURL`, `safeLinkURL`, `safeBundleLinkURL`) exist in both `rivian-tires.js` and `compare.js` with different implementations and domain allowlists. The `compare.js` version has a stricter hostname check, while `rivian-tires.js` has a broader allowlist.
**Recommendation:** Extract shared URL validation into a common module with a single source of truth for allowed domains.

### 4.5 — Consolidate duplicate efficiency calculation logic
**Files:** `includes/class-rtg-database.php:271-364`, `admin/js/admin-scripts.js`
**Priority:** Medium
**Issue:** The efficiency scoring formula is implemented in both PHP (backend) and JavaScript (admin preview). If the formula is updated in one location, it must be manually synchronized in the other.
**Recommendation:** Either generate the JS formula from the PHP source, or move the preview calculation to an AJAX call so there's a single source of truth.

### 4.6 — Add PHPDoc blocks to all public methods
**Priority:** Low
**Issue:** Many public methods in `RTG_Database` and `RTG_Admin` lack PHPDoc blocks (e.g., `get_all_tires()`, `insert_tire()`, `delete_tire()`). Parameter types, return types, and descriptions are missing.
**Recommendation:** Add `@param`, `@return`, and description blocks to all public methods for IDE support and documentation generation.

---

## 5. UX / Frontend Improvements

### 5.1 — ~~Persist filter state in URL parameters~~ ✅ Resolved
**Priority:** High
**Status:** Resolved in v1.10.0.
**Resolution:** All filter state synced to URL query parameters via `updateURLFromFilters()` using `history.pushState()`. On page load, `applyFiltersFromURL()` parses URL params and restores all filter state (search, size, brand, category, 3PMS, EV, studded, reviewed, favorites, price/warranty/weight sliders, sort, page). `popstate` listener enables browser back/forward through filter history. Shareable filtered URLs fully functional (e.g., `?brand=Michelin&size=275/65R18&sort=price-asc`).

### 5.2 — Mobile-friendly range slider interaction
**Priority:** Medium
**Issue:** Range sliders (price, warranty, weight) can be difficult to use on mobile devices, especially with small touch targets.
**Recommendation:** Use dual-handle range sliders with larger touch targets, and display the current min/max values as editable text inputs beside the sliders.

### 5.3 — "Back to guide" link on compare page
**File:** `frontend/templates/compare.php`
**Priority:** Low
**Issue:** The compare page has no navigation back to the main tire guide. Users must use the browser back button.
**Recommendation:** Add a "Back to Tire Guide" link/button in the compare page header.

### 5.4 — Skeleton/loading state for tire cards
**Priority:** Medium
**Issue:** When the page loads, there's a brief flash of empty content while JavaScript processes and renders tire cards.
**Recommendation:** Add CSS skeleton placeholders that display immediately and are replaced once the JS renders the actual cards. This improves perceived performance.

### 5.5 — ~~Accessibility (a11y) improvements~~ ✅ Resolved
**Priority:** High
**Status:** Resolved across v1.2.0 and v1.14.0.
**Resolution:** All listed concerns addressed:
- **Star ratings:** ARIA `role`, `aria-label`, `aria-checked` attributes + full keyboard navigation (arrow keys, Enter/Space) — v1.2.0.
- **Filter toggles:** `aria-expanded` / `aria-controls` on mobile filter toggle and wheel drawer trigger — v1.2.0.
- **Tooltips:** Clickable tooltip triggers with modal display (not hover-only) — v1.14.0.
- **Compare checkboxes:** Descriptive `aria-label` attributes — v1.2.0.
- **Additional a11y (v1.14.0):** Skip-to-content link, `aria-label` on all filter controls/search/sort, `role="status"` + `aria-live="polite"` on no-results, screen-reader-only labels, `focus-visible` outline styles on all interactive elements, `.screen-reader-text` utility class.

### 5.6 — Improve the "No results" state
**Priority:** Low
**Issue:** When filters produce no results, a generic "No results" message appears with no guidance.
**Recommendation:** Show which active filters are narrowing the results and suggest relaxing specific ones (e.g., "No tires match your current filters. Try removing the '3PMS Rated' filter.").

### 5.7 — Add print stylesheet for comparison page
**Priority:** Low
**Issue:** The compare page has no print styles. Printing produces a poorly formatted page with dark backgrounds consuming ink.
**Recommendation:** Add a `@media print` stylesheet that switches to a light theme, removes interactive elements, and optimizes the comparison table for paper.

---

## 6. Database & Data Integrity

### 6.1 — Add a foreign key or cascade delete for orphaned ratings
**File:** `includes/class-rtg-activator.php:60-70`
**Priority:** Medium
**Issue:** The `rtg_ratings` table references `tire_id` from `rtg_tires`, but there's no foreign key constraint. Deleting a tire via `delete_tire()` does not cascade to remove associated ratings, leaving orphaned records.
**Recommendation:** Add `ON DELETE CASCADE` to the ratings table's `tire_id` reference, or add cleanup logic in `RTG_Database::delete_tire()` and `delete_tires()` to also remove associated ratings.

### 6.2 — Add database table existence check on plugin load
**Priority:** Low
**Issue:** If tables are dropped or corrupted outside the plugin, there's no graceful error handling — the plugin would produce fatal errors or silent failures.
**Recommendation:** Add a lightweight table existence check on `plugins_loaded` and display an admin notice if tables are missing, with a "Repair" button that reruns `dbDelta()`.

### 6.3 — Validate `tire_id` format consistency
**File:** `includes/class-rtg-admin.php:196`, `includes/class-rtg-database.php:251-257`
**Priority:** Low
**Issue:** Auto-generated tire IDs follow the format `tire001`, `tire002`, etc., but manually entered tire IDs have no format enforcement beyond `sanitize_text_field()`. Mixed formats could cause inconsistent behavior in the `get_next_tire_id()` query.
**Recommendation:** Either enforce a consistent format (alphanumeric + hyphens, max length) or make the auto-generation more robust against non-standard IDs.

---

## 7. Testing & Reliability

### 7.1 — ~~Add PHPUnit test suite~~ ✅ Resolved
**Priority:** High
**Status:** Resolved in v1.3.0 (PHP), v1.14.0 (JS).
**Resolution:** Two test suites implemented:
- **PHPUnit** (`tests/test-database.php`): 21 tests covering tire CRUD, ratings upsert, cascade deletes, cache invalidation, efficiency calculation, filtered pagination, and bulk operations. Bootstrap at `tests/bootstrap.php`.
- **JavaScript** (`tests/test-validation.js`): 83 tests covering `escapeHTML`, `sanitizeInput`, `validateNumeric`, `safeImageURL`, `safeLinkURL`, and `fuzzyMatch`.
- **CI:** GitHub Actions workflow runs JS tests, build verification, and PHP syntax linting (PHP 7.4, 8.0, 8.2) on every push/PR — v1.15.0.

### 7.2 — Add JavaScript unit tests
**Priority:** Medium
**Issue:** Frontend validation functions (`escapeHTML`, `sanitizeInput`, `safeImageURL`, `safeLinkURL`, `validateNumeric`, `fuzzyMatch`) are untested.
**Recommendation:** Add a Jest (or Vitest) test suite covering all validation and utility functions, especially the security-critical ones like URL validation and HTML escaping.

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
| **Medium** | 13 | Nonce on read endpoint (1.2), Validate tire existence (1.3), CSP headers (1.4), Compare image escaping (1.6), REST API (2.2), User reviews (2.3), Delete own rating (2.4), Schema.org (2.7), DB migrations (3.2), Query caching (3.3), JS modules (4.1), Duplicate URL validation (4.4), Duplicate efficiency calc (4.5), Mobile sliders (5.2), Skeleton states (5.4), Orphaned ratings (6.1), JS tests (7.2), AJAX tests (7.3) |
| **Low** | 11 | CSS re-validation (1.5), Uninstall cleanup (1.8), Shortcode attributes (2.5), Dashboard widget (2.6), Email notifications (2.8), Font Awesome subset (3.4), Lazy images (3.5), Console cleanup (4.2), Autoloader (4.3), PHPDoc (4.6), Back link (5.3), No results UX (5.6), Print stylesheet (5.7), DB table check (6.2), Tire ID format (6.3), PHP linting (7.4) |

> **Note:** Many medium and low priority items have also been addressed in subsequent versions (e.g., REST API in v1.14.0, user reviews in v1.7.0, Schema.org in v1.1.0, DB migrations in v1.3.0, query caching in v1.2.0, JS modules in v1.15.0, URL validation consolidation in v1.14.0, efficiency calc consolidation in v1.14.0, mobile sliders in v1.15.0, skeleton states in v1.14.0, orphaned ratings in v1.2.0, JS tests in v1.14.0). A full audit of medium/low items is recommended as a follow-up.
