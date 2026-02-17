# Changelog

All notable changes to the Rivian Tire Guide plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.5.3] - 2026-02-17

### Changed
- **Pill background on tire spec values** — Spec values on tire cards now display with a subtle pill-shaped background (`var(--rtg-border)`, 6px radius) matching the existing wheel drawer code tag style, improving visual distinction from labels.

## [1.5.2] - 2026-02-17

### Changed
- **Monospace font for tire spec values** — Tire specification values (size, price, weight, tread depth, load index, etc.) now render in a monospace font on both the main tire card view and the comparison page for improved readability of numeric data.

## [1.5.0] - 2026-02-16

### Added
- **Open Graph & Twitter Card meta tags** — Sharing a `?tire=` link on social platforms now shows a rich preview with the tire name, description, price, and image. Default meta tags are output on the catalog page when no tire is specified.
- **Native share sheet** — The share button now uses `navigator.share()` on supported devices (mobile), opening the native share sheet with the tire name and URL. Falls back to clipboard copy on desktop. Icon updated from link to share-nodes.

## [1.4.8] - 2026-02-16

### Changed
- **Tire deep-link shows single tire** — Opening a `?tire=` link now isolates that tire as the only visible card, hiding filters, sort bar, and pagination. A "View all tires" back link appears above the card to return to the full catalog.

## [1.4.7] - 2026-02-16

### Fixed
- **Tire deep-link not activating** — Fixed shareable tire links not scrolling or highlighting on page load. The async render pipeline (throttled RAF + rating Promises) meant cards weren't in the DOM when the deep-link handler ran. Now polls for the card element reliably.

## [1.4.6] - 2026-02-16

### Added
- **Shareable tire links** — Each tire card now has a link button (visible on hover) that copies a direct URL to that tire. Opening the link scrolls to the tire and highlights it with a brief accent glow, even navigating to the correct page. If the tire is hidden by active filters, filters are automatically cleared.

## [1.4.5] - 2026-02-16

### Removed
- **Back-to-top button** — Removed the fixed-position scroll button as it interfered with page interactions.

## [1.4.4] - 2026-02-16

### Added
- **Active filter chips** — Selected filters now display as dismissible chips below the filter bar for quick visibility and one-click removal.

## [1.4.3] - 2026-02-16

### Changed
- **Compare checkbox repositioned** — Moved the compare checkbox to the top-right corner of the tire card image for easier access.

## [1.4.2] - 2026-02-16

### Fixed
- **Compare bar button text wrapping** — Fixed compare bar buttons wrapping their text on desktop viewports.

## [1.4.1] - 2026-02-16

### Fixed
- **Mobile filter button width** — Fixed the mobile filter toggle button stretching too wide.
- **Compare text wrapping** — Fixed text overflow in compare bar on smaller screens.

## [1.4.0] - 2026-02-16

### Changed
- **Main tire guide redesign** — Revamped the entire frontend to match the compare page design language. Filter section now uses a card container with a section header bar (icon + title), bordered cards with hover accent highlight, and consistent typography/spacing across all elements.
- **Tire cards refactored** — Cards use semantic CSS classes (`tire-card-*`) instead of inline JavaScript styles. New structure separates image, body content, and action areas. Brand name is now accent-colored uppercase (matching compare page's `.cmp-tire-brand` pattern). Spec rows use muted label + primary value styling with subtle dividers.
- **Efficiency badge** — Updated to match the compare page's two-part badge pattern (`tire-card-eff-grade` + `tire-card-eff-score`) with consistent sizing.
- **Tags** — Restyled to match compare page `.cmp-tag` sizing (11px, 3px 8px padding, 4px radius).
- **Buttons unified** — All CTA buttons now use class-based styling: primary (accent green), bundle (blue), disabled (bordered muted), and compare (ghost bordered). All share `.15s` transitions.
- **Filter controls refined** — Select dropdowns and toggle switches now have border + hover accent highlight. Slider wrappers use bordered containers. Clear All button uses dashed border ghost style.
- **Compare bar** — Refined with `backdrop-filter: blur`, card background, border, and class-based button variants (go/clear).
- **Mobile toggle button** — Restyled from solid accent fill to bordered card style with icon.

### Added
- **Print styles for tire cards** — Cards hide actions and use light-friendly colors when printed.
- **Reduced motion support** — Tire card transitions respect `prefers-reduced-motion`.
- **Hover state on tire cards** — Subtle border color shift to accent on hover.

## [1.3.0] - 2026-02-15

### Added
- **Server-side pagination** — New optional mode (Settings > Server-side Pagination) that fetches tires via AJAX instead of embedding the full dataset in the page. Includes `rtg_get_tires` and `rtg_get_filter_options` AJAX endpoints with full server-side filtering, sorting, and pagination. Recommended for catalogs with 200+ tires.
- **Database migration versioning** — Schema changes are now tracked via a numbered migration system (`rtg_db_version` option). Migrations run automatically on plugin update via `plugins_loaded`. New migrations can be added to `RTG_Activator` with a simple method pattern.
- **Production asset minification** — New `build.sh` script generates `.min.css` and `.min.js` files using terser/csso (falls back to basic minification). Frontend and admin classes automatically serve minified assets when available and `SCRIPT_DEBUG` is off.
- **PHPUnit test suite** — Full test scaffolding with `phpunit.xml`, WordPress test bootstrap, and test cases covering database CRUD, cascade deletes, cache invalidation, efficiency calculation, filtered pagination, ratings upsert, migration versioning, and admin menu registration.
- **Tags index** — Added database index on `tags(100)` column for faster server-side tag filtering (applied via migration 2).

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
