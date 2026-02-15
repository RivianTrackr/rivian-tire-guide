# Changelog

All notable changes to the Rivian Tire Guide plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.2.0] - 2026-02-15

### Added
- **CSV import and export** — New admin page (Tire Guide > Import / Export) for bulk importing tires from CSV and exporting the full catalog as a CSV backup. Supports duplicate handling (skip or update), auto-generated tire IDs, and auto-calculated efficiency scores.
- **Transient caching for tire queries** — `get_all_tires()` results are now cached in a WordPress transient (1 hour TTL) and automatically invalidated on insert, update, or delete operations.
- **Accessibility improvements** — Star ratings now have ARIA `role`, `aria-label`, `aria-checked` attributes and full keyboard navigation (arrow keys, Enter/Space). Filter toggle and wheel drawer buttons have `aria-expanded`/`aria-controls`. Compare checkboxes include descriptive `aria-label`. Tire count is an `aria-live` region. Image modal supports Escape key to close and has `role="dialog"`.

### Fixed
- **Orphaned ratings on tire delete** — Deleting a tire (single or bulk) now also removes its associated ratings from the database, preventing orphaned records.

## [1.1.4] - 2026-02-15

### Fixed
- **Diameter dropdown not persisting on save** — WordPress magic quotes were escaping the `"` character in diameter values (e.g. `20"` became `20\"`), causing a mismatch on reload. Added `wp_unslash()` to all POST data in the tire save handler.
- **Dropdown values not matching stored data** — If a tire's stored value for any dropdown field (brand, size, diameter, category, load range, speed rating) wasn't in the managed options list, the field would silently reset to empty on save. The current stored value is now always included as a dropdown option.

## [1.1.3] - 2026-02-15

### Fixed
- **Efficiency grade A color** — Reverted grade A badge back to fixed `#5ec095` green, independent of the theme accent color.

## [1.1.2] - 2026-02-15

### Changed
- **Accent colors fully themeable** — All hardcoded `#5ec095` / `rgba(94, 192, 149, …)` references replaced with `var(--rtg-accent)` / `rtgColor('accent')` so Primary Accent and Accent Hover are fully controllable from admin settings.

### Fixed
- **Diameter missing inch symbol** — Diameter values stored without a trailing `"` (e.g. `33`, `32.8`) now display as `33"` and `32.8"` on tire cards and the comparison page.

## [1.1.1] - 2026-02-15

### Fixed
- **Star ratings showing 0** — Frontend was not passing the nonce with `get_tire_ratings` AJAX requests, causing the new CSRF check to reject rating fetches for logged-in users.
- **Broken images on comparison page** — `safeImageURL()` was hardcoding a CDN optimization prefix on every validated URL. Now returns the validated URL directly.

## [1.1.0] - 2026-02-15

### Added
- **Schema.org structured data** — Automatic JSON-LD output (Product + AggregateRating + ItemList) on pages using the `[rivian_tire_guide]` shortcode for SEO rich snippets.
- **Rate limiting on rating submissions** — Transient-based limiter (10 submissions per 60-second window per user) to prevent abuse.
- **Tire existence validation** — Rating submissions now verify the tire exists in the database before saving.
- **Nonce verification on `get_tire_ratings`** — Logged-in users' read requests are now CSRF-protected.
- **Content-Security-Policy headers** — The standalone comparison page now sends `CSP`, `X-Content-Type-Options`, `X-Frame-Options`, and `Referrer-Policy` headers.
- **URL validation in comparison page** — Image, affiliate, and bundle link URLs are now validated through `safeImageURL()` and `safeLinkURL()` domain allowlists with `escapeHTML()` applied to all attributes.
- **CSS injection prevention** — Theme color values are re-validated with `sanitize_hex_color()` at render time in both the frontend shortcode and comparison template.
- **README.md** — Comprehensive project documentation.
- **CHANGELOG.md** — Version history.
- **SECURITY.md** — Security policy and responsible disclosure instructions.

### Fixed
- **XSS in comparison page** — Link and image URLs were inserted into HTML attributes without escaping. All dynamic values now pass through `escapeHTML()` and domain-validated URL functions.
- **Uninstall cleanup** — Added `delete_option('rtg_dropdown_options')` to the uninstall handler to remove all plugin data on deletion.
- **Bundle link hover state** — Fixed `onmouseout` color not changing (was identical to `onmouseover`).

### Security
- 8 security improvements addressing CSRF, XSS, rate limiting, CSP, CSS injection, and data validation. See the individual items above for details.

## [1.0.5] - Initial tracked release

### Features
- Interactive tire catalog with filtering, sorting, and pagination
- Smart search with fuzzy matching and type-ahead suggestions
- Side-by-side tire comparison page
- User star ratings (1-5) with AJAX submission
- Efficiency scoring algorithm (A-F grade, 0-100 score)
- Full admin CRUD for tire management
- Ratings management dashboard
- Customizable theme colors (11 CSS custom properties)
- Configurable dropdown options for tire fields
- Shortcode: `[rivian_tire_guide]`
- Custom rewrite rules for comparison page
- Proper activation/deactivation/uninstall hooks
