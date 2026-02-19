# Changelog

All notable changes to the Rivian Tire Guide plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.11.0] - 2026-02-19

### Added
- **Affiliate Links dashboard** — New admin page (Tire Guide > Affiliate Links) providing a centralized view of all tire purchase, bundle, and review links. Summary stats show counts for affiliate, regular, and missing links at a glance.
- **Link classification** — Automatically detects whether a purchase link is an affiliate link (via known affiliate network domains like CJ, ShareASale, AvantLink, Impact, etc.) or a regular direct retailer link, displayed as color-coded badges.
- **Filter tabs** — Quick-filter buttons to show only tires with affiliate links, regular links, missing links, missing bundle links, or missing review links — making it easy to find which tires still need affiliate links added.
- **Inline link editing** — Edit all three link fields (purchase, bundle, review) directly in the table row with AJAX save — no page reload required.
- **Search** — Search the affiliate links table by brand, model, or tire ID.

### Changed
- **Plugin version** — Bumped to 1.11.0.

## [1.10.0] - 2026-02-18

### Added
- **Favorites / Wishlist system** — Logged-in users can now save tires to a personal favorites list by clicking the heart icon on each tire card. New `wp_rtg_favorites` database table stores user preferences. Includes a "My Favorites" filter toggle to show only favorited tires, with a badge count on the toggle. Optimistic UI updates for instant feedback.
- **Smart No Results state** — The empty state when no tires match filters now shows an illustrated view with specific, actionable suggestions (e.g., "Remove size filter", "Show all brands", "Clear all filters") based on which filters are active.
- **Enhanced image lazy loading** — Added `IntersectionObserver`-based lazy loading with `data-src` pattern and shimmer placeholder animation. Images fade in smoothly on load. Falls back to native `loading="lazy"` when IntersectionObserver is unavailable.
- **Browser back/forward for filters** — Filter changes now push to browser history via `pushState`, enabling back/forward navigation through filter states. Added `popstate` listener to restore filters from URL.
- **Favorites filter in URL** — The `?favorites=1` URL parameter persists the favorites filter for shareable links.

### Changed
- **Card enter/exit animations** — Cards now use a slide-up (`translateY`) + scale animation with cubic-bezier easing and staggered delays (40ms per card on desktop) for a smoother cascade effect on filter changes.
- **Database schema version** — Bumped to v7 with migration for the new favorites table.
- **Plugin version** — Bumped to 1.10.0.

## [1.9.4.2] - 2026-02-18

### Changed
- **Larger star ratings** — Bumped SVG star sizes across all contexts for better visibility: default 20→22px, interactive 24→26px, review modal 36→40px, mini stars 16→18px, mobile modal 28→32px.

## [1.9.4] - 2026-02-18

### Changed
- **Mobile-first card body spacing** — Rewrote tire card body spacing to use a single `gap` as the source of truth instead of mixing gap with individual child margins. Base styles target mobile, with a `min-width: 601px` breakpoint scaling up padding for desktop.
- **Brand/model tightened** — Brand name and model title now sit closer together as a visual unit, with more breathing room around the star rating area below.
- **Review actions spacing** — Increased separation between the star row and the "Write a Review" / "View Reviews" action links so the stars feel like they float in their own space.

### Fixed
- **User-rated stars overriding average display** — The `.user-rated` CSS class was forcing `star-fill` opacity to 1 on all stars up to the user's personal rating, regardless of the actual average. A 3/5 average could show 5 full green stars if the user had rated 5. The user-rated styling now only colorizes stars that are already filled based on the average.

## [1.9.0] - 2026-02-18

### Added
- **SVG star ratings with half-star support** — Replaced the old Unicode star characters with layered SVG stars (background outline, full fill, and half fill via `clip-path`). Ratings round to the nearest 0.5 for accurate half-star display.
- **Star color settings** — New admin settings for Star Filled, Star User-Rated, and Star Empty colors, output as CSS custom properties (`--rtg-star-filled`, `--rtg-star-user`, `--rtg-star-empty`).

### Changed
- **Rebrand to orange/gold accent** — Primary accent shifted from green to orange/gold (`#fba919`) with an optimized dark navy palette. Updated all CSS custom property defaults, admin color picker defaults, and the comparison page theme.

## [1.8.4] - 2026-02-18

### Fixed
- **Shared page links reverting to page 1** — Fixed pagination links losing the current page when sharing. Renamed the page URL parameter from `page` to `pg` to avoid a conflict with a reserved WordPress query variable.

## [1.8.3] - 2026-02-18

### Added
- **Highlighted user reviews** — Reviews with text now display a prominent badge and CTA styling. Rating-only entries (no review body) are also included in the reviews drawer with a blank body.

### Changed
- **Write-review button styling** — Aligned the write-review button with matching pill styling for consistency.

## [1.8.2] - 2026-02-17

### Fixed
- **Server-side pagination: Clear All filters** — The "Clear All" button and individual filter chip dismiss actions now correctly fetch fresh data from the server when server-side pagination is enabled. Previously they called the client-side render path, which operates on an empty dataset in server-side mode, resulting in no tires being displayed.

## [1.8.1] - 2026-02-17

### Changed
- **Grade scale simplified** — Removed the "E" grade from the efficiency scale. Grades now use A / B / C / D / F only, across the PHP calculation engine, admin preview calculator, frontend tire cards, and comparison page.

## [1.8.0] - 2026-02-17

### Added
- **Tire duplication** — "Duplicate" action on each tire row in the admin list. Creates a copy with a new auto-generated tire ID and opens the edit form immediately.
- **Recalculate Grades button** — One-click bulk recalculation of efficiency scores for all tires from the admin tire list header. Useful after algorithm changes or CSV imports.
- **Admin list filters** — Brand, Size, and Category dropdown filters alongside the existing search bar on the All Tires page.
- **Load Index column** — Sortable Load Index column added to the admin tire list table.
- **Tag suggestions** — Previously-used tags appear as clickable chips on the tire edit form for quick reuse. Clicking toggles the tag in or out of the comma-separated tags field.
- **Size-to-diameter mapping** — New "Size → Tire Diameter" setting in the Dropdown Options section. Maps each tire size to its overall diameter (e.g. `275/65R20 = 34.1"`). Selecting a size on the tire edit form auto-fills the diameter field.
- **Image URL prefix** — The CDN Image Prefix from settings is now shown as a static label before the image filename input on the tire edit form. Only the filename portion needs to be entered; the full URL is assembled on save.
- **UTQG "None" fallback** — Tires with no UTQG value now display "None" on frontend cards and the comparison page instead of a blank or dash.

### Changed
- **Sort order field hidden** — The sort order input on the tire edit form is now a hidden field (preserving its value) to reduce form clutter.
- **Search matches tags** — Admin tire search now also matches against the tags field.

## [1.7.8] - 2026-02-17

### Added
- "User Reviews Page Slug" setting in admin settings to configure the shortcode page slug.
- "Back to Tire Guide" link on the user reviews page.
- Tire names on the user reviews page now link to the tire guide filtered to that tire.

## [1.7.7] - 2026-02-17

### Added
- `[rivian_user_reviews]` shortcode — displays all reviews by a user (via `?reviewer=ID` URL param).
- Reviewer names in review cards now link to the user's reviews page.
- New `user_reviews_slug` setting (defaults to `user-reviews`).

## [1.7.6] - 2026-02-17

### Added
- "Officially Reviewed" toggle filter to show only tires with an official RivianTrackr review.

## [1.7.5] - 2026-02-17

### Changed
- Renamed review buttons to "Read/Watch Official Review" to distinguish from community reviews.

### Fixed
- Fixed escaped slashes in review text caused by WordPress magic quotes.

## [1.7.4] - 2026-02-17

### Changed
- **Ratings admin "Review" column** — Now shows a "View Reviews" link that navigates to the Reviews page filtered by that tire, instead of displaying inline review text.
- **Re-moderation on edit** — Editing a review (title or body) resets it to pending status for non-admin users, ensuring all changes are re-approved. Title-only reviews are now also subject to moderation.

## [1.7.3] - 2026-02-17

### Added
- **Toast notifications** — Users now see confirmation feedback after submitting a review: "Your rating has been saved!", "Your review has been updated.", or "Thanks! Your review has been submitted and is pending approval." depending on context.
- **Admin pending-reviews notice** — A dismissible info banner appears on the WordPress dashboard and Tire Guide admin pages when reviews are awaiting moderation, with a direct link to the pending queue.
- **Improved reviews drawer empty state** — When a tire has no reviews, the drawer shows a friendly heading, icon, and "Write a Review" CTA button (for logged-in users) instead of a plain text message.

## [1.7.2] - 2026-02-17

### Changed
- Updated logged-out prompt to "Log in or sign up to review tires" with separate login and registration links.

## [1.7.1] - 2026-02-17

### Added
- **Review moderation** — New admin "Reviews" page (Tire Guide > Reviews) with status tabs (All, Pending, Approved, Rejected). Pending review count displays as a badge in the admin menu. Admins can approve, reject, or delete reviews. Only approved reviews are visible on the frontend and in Schema.org structured data.
- **Admin auto-approve** — Reviews submitted by users with `manage_options` capability are automatically approved; all other reviews default to "pending" status.
- **Database migration 6** — Adds `review_status` column (VARCHAR 20, default `'approved'`) to the ratings table. Existing reviews are grandfathered as approved.

### Fixed
- **Review date showing original rating date** — The reviews drawer and Schema.org markup now use the `updated_at` timestamp instead of `created_at`, so editing a review shows the correct date (e.g. "Today") instead of when the original star rating was submitted.
- **Review date timezone** — Review relative dates ("Today", "Yesterday") now use the WordPress timezone setting instead of UTC or the browser's local time.

## [1.7.0] - 2026-02-17

### Added
- **User text reviews** — Users can now write optional text reviews alongside star ratings. Clicking a star or the "Write a Review" button opens a review modal with star selector, optional title (200 char limit), and review body (5,000 char limit). Existing reviews can be edited from the same modal.
- **Reviews drawer** — Each tire card shows a review count link (e.g. "3 reviews") that opens a slide-in drawer displaying all written reviews with author name, star rating, relative date, title, and body text. Paginated at 10 reviews per page.
- **Review AJAX endpoints** — New `get_tire_reviews` public endpoint for fetching paginated reviews. Extended `submit_tire_rating` to accept `review_title` and `review_text` fields with length validation and sanitization.
- **Schema.org Review markup** — Individual `Review` objects (up to 5 per tire) are now included in the JSON-LD structured data alongside `AggregateRating` for rich snippet eligibility.
- **Admin review column** — The Ratings & Reviews admin table now displays review title and truncated review text with hover tooltip for each entry.
- **Database migration 5** — Adds `review_title` (VARCHAR 200) and `review_text` (TEXT) columns to the ratings table for existing installations.

## [1.6.1] - 2026-02-17

### Changed
- **Sort options refined** — Removed low-value sort options (Brand A→Z, Brand Z→A, Weight Heavy→Light). Added "Newest Added" (sorts by date added, descending) and "Most Reviewed" (sorts by number of ratings, with average rating as tiebreaker). Default remains Rating: High → Low.

## [1.6.0] - 2026-02-17

### Added
- **Review link on tire cards** — Each tire can now link to an article or video review via a new `review_link` field. The button adapts based on the URL: YouTube/TikTok links show "Watch Review" with a play icon, while article links (RivianTrackr, Instagram) show "Read Review" with a newspaper icon. Styled with a purple CTA button.
- **Review link in admin** — New "Review Link" input in the Pricing & Links section of the tire edit form, with description text guiding accepted platforms.
- **Review link in CSV import/export** — The `review_link` column is included in CSV exports and recognized during imports.
- **Review link on compare page** — The "Where to Buy" section now includes the review link alongside existing View Tire and Bundle buttons.
- **Review link URL validation** — Frontend validates review links against an allowlist of domains: riviantrackr.com, YouTube, TikTok, and Instagram.
- **Database migration 4** — Adds the `review_link` column to the tires table for existing installations.

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
