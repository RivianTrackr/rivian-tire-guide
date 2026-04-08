# Changelog

All notable changes to the Rivian Tire Guide plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.44.0] - 2026-04-08

### Changed
- **Roamer schema: sessions → distance** — Replaced `roamer_session_count` with `total_distance_km` from the updated Rivian Roamer feed. All surfaces now display total miles tracked instead of driving session counts. Multi-assign efficiency weighting changed from session count to total distance.
- **Vehicle breakdown by drivetrain** — New `vehicle_breakdown` field from the Roamer feed shows how many vehicles contributed by drivetrain (e.g. "Gen 1 R1T Dual", "Gen 2 R1T Tri"). Displayed in tile tooltips, compare page, admin tire edit, and stored as JSON in the database.
- **Distance displayed in miles** — All user-facing surfaces convert the source km values to miles for consistency with mi/kWh efficiency units. REST API field renamed from `roamer_total_km` to `roamer_total_miles`.
- **Database schema** — Migration 13 drops `roamer_session_count` column and adds `roamer_vehicle_breakdown` TEXT column to `wp_rtg_tires`.
- **Plugin version** — Bumped to 1.44.0.

## [1.43.0] - 2026-04-04

### Changed
- **Redesigned wheel guide section** — The "Not sure which tire fits your Rivian?" drawer now features vehicle tabs (R1T, R1S, R2, etc.) and a card grid layout, replacing the old flat list. Users can quickly filter by their vehicle and browse wheels in a clean, visual card format.

### Improved
- **Mobile wheel guide** — Card grid collapses to a single column on small screens with compact tab styling.

## [1.42.1] - 2026-04-04

### Improved
- **Roamer efficiency info tooltip** — The info icon on the Real-World Efficiency badge now shows the number of driving sessions and vehicles that contributed to the efficiency value for each tire.

### Changed
- **Plugin version** — Bumped to 1.42.1.

## [1.42.0] - 2026-04-03

### Added
- **OEM tire tag support** — Tires tagged with "OEM" display a green badge with a certificate icon on their card and can be filtered via a dedicated toggle.
- **OEM filter and server-side query** — New "OEM" toggle in the Features filter section with full server-side filtering support.

### Improved
- **Shortened feature filter labels** — Renamed "3PMS Rated" → "3PMS", "Studded Available" → "Studded", "Officially Reviewed" → "Reviewed", "My Favorites" → "Favorites" for a cleaner filter UI.
- **Wheel guide hidden on deep links** — The "Not sure which tire fits your Rivian?" section is now hidden when viewing a single tire via direct link.

### Fixed
- **Tire deep links in server-side mode** — Direct tire links (`?tire=tire001`) now work in server-side pagination mode by passing the tire ID to the PHP query.
- **Tire deep link render race condition** — Deep links in client-side mode no longer race with the async filter render.

### Removed
- **Reviewed badge** — Removed the rainbow "Reviewed" badge from tire cards (no longer used).

### Changed
- **Plugin version** — Bumped to 1.42.0.

## [1.41.2] - 2026-04-03

### Fixed
- **Tire deep links broken in server-side mode** — Direct tire links (`?tire=tire001`) were only handled in client-side mode. Added `tire_id` filter support to the server-side AJAX path and PHP query so deep links work regardless of pagination mode.

### Changed
- **Plugin version** — Bumped to 1.41.2.

## [1.41.1] - 2026-04-03

### Fixed
- **Tire deep links not rendering** — Direct tire links (`?tire=tire001`) were setting the filtered data but not re-rendering the card grid, so the page still showed all tires instead of the single linked tire.

### Changed
- **Plugin version** — Bumped to 1.41.1.

## [1.41.0] - 2026-04-03

### Added
- **OEM tire tag support** — Tires tagged with "OEM" now display a green badge with a certificate icon in the top-right corner of their card, making factory-original tires instantly recognizable.
- **OEM filter toggle** — New "OEM Tire" toggle in the filter sidebar lets users filter the catalog to show only Original Equipment tires.
- **OEM server-side filtering** — The OEM filter is applied server-side for consistent results across paginated and AJAX-loaded views.
- **OEM tooltip** — Added info tooltip explaining that OEM tires are Original Equipment from the factory on Rivian vehicles.

### Changed
- **Plugin version** — Bumped to 1.41.0.

## [1.40.0] - 2026-04-03

### Fixed
- **Dropdown counts no longer show (0) when a filter is selected** — Size, Brand, and Category dropdowns now count against rows filtered by everything *except* their own selection, so users see how many tires each option would yield if they switched to it.

### Improved
- **Collapsible advanced filters** — Sliders (price, warranty, weight) and toggle switches (3PMS, EV Rated, Studded, Reviewed, Favorites) are now tucked behind a compact inline "More Filters" toggle, keeping primary filters (vehicle, size, brand, category) always visible and reducing visual clutter.
- **Advanced filter badge** — The "More Filters" toggle shows an active filter count badge and auto-expands when any advanced filter is in use, so users never lose track of applied filters.
- **Filter section labels** — Added "Specifications" and "Features" sub-section labels within the advanced filters panel for clearer visual hierarchy and grouping.
- **Clear All button** — Relocated from the toggle row into the filter header bar for constant visibility, and restyled from red to muted text with accent gold hover to match the UI palette.
- **Sort dropdown moved into filter card** — The sort dropdown now lives inside the filter card beneath the primary filters, matching the "Filter, Sort, and Compare" header and keeping all controls in one place.
- **Two-column toggle grid** — Toggle switches now use a CSS grid layout with `auto-fill` columns for consistent two-column alignment on desktop and clean single-column stacking on mobile.
- **Compact slider group** — Reduced padding, gap, and font size on the price/warranty/weight sliders for a tighter footprint within the advanced filters panel.
- **Wheel guide moved to standalone callout** — The "Not sure which tire fits your Rivian?" wheel guide is now a standalone collapsible section below the filter card with its own styled trigger, separating help content from filter controls.

### Changed
- **Plugin version** — Bumped to 1.40.0.

## [1.36.0] - 2026-04-02

### Fixed
- **Roamer efficiency NaN guard** — Added `Number.isFinite()` check before displaying Roamer real-world efficiency values to prevent `Infinity` or `NaN` from rendering broken badges on tire cards.
- **Rating sort error handling** — Added `.catch()` handler to the `loadTireRatings` promise chain in `filterAndRender` so sorting by rating or most-reviewed still completes on network failure instead of silently breaking.
- **Card cache eviction** — Cache now evicts 20 entries at a time when the 100-entry limit is reached, preventing single-evict thrashing that allowed unbounded memory growth on long sessions.
- **Favorites error feedback** — Failed favorite toggle (network error or server rejection) now shows a visible toast notification instead of silently reverting the optimistic UI update.
- **REST API IP validation** — Rate limiter in the REST API now uses `filter_var(FILTER_VALIDATE_IP)` for proper IPv4/IPv6 validation instead of a regex that could pass malformed addresses.
- **REST efficiency endpoint validation** — The `POST /efficiency` endpoint now returns a 400 error when the required `size` parameter is missing, instead of silently calculating with empty data.
- **AJAX tire_ids cap** — The `get_tire_ratings` AJAX handler now caps the `tire_ids` array to 200 entries to prevent query explosion from malicious or malformed requests.

### Improved
- **Image modal accessibility** — Added `role="dialog"`, `aria-modal="true"`, and `aria-label` attributes to the full-screen image preview modal for screen reader support.
- **Honeypot accessibility** — Added `aria-hidden="true"` to the hidden honeypot field in the guest review modal so screen readers no longer announce it.

### Changed
- **Plugin version** — Bumped to 1.36.0.

## [1.35.0] - 2026-03-31

### Added
- **Hide unmatched Roamer tires** — New "Hide" button on the Roamer Sync page lets you permanently dismiss unmatched tires that aren't compatible with Rivian (e.g. insufficient load rating). Hidden tires are excluded from future syncs and won't reappear.
- **Restore hidden tires** — Hidden Roamer tires can be viewed and restored via a collapsible "View Hidden Tires" button on the Roamer Sync page.
- **Coverage stat** — Sync Status card now shows a linked/total coverage percentage (e.g. "32/45 — 71%") at a glance.

### Changed
- **Default sort is now Real-World Efficiency** — The tire guide sort dropdown defaults to "Real-World Efficiency" instead of "Rating: High → Low", so visitors see efficiency-ranked tires first.
- **Collapsible Linked & Unlinked tables** — Linked Tires and Unlinked Guide Tires sections on the Roamer Sync page are now collapsed by default to reduce clutter, with click-to-expand headers.
- **Relative timestamps** — "Last Sync" and per-tire "Last Synced" now display as relative time (e.g. "2 hours ago") with full datetime on hover.
- **Unmatched sorted by session count** — Unmatched Roamer tires are now sorted by session count descending so the most impactful tires appear first.
- **Plugin version** — Bumped to 1.35.0.

## [1.33.0] - 2026-03-30

### Added
- **Real-world efficiency in AI search** — The AI Tire Advisor now includes Rivian Roamer real-world efficiency data (mi/kWh and session count) in its tire context. When users ask about range or efficiency, the AI factors in actual driving data from Rivian owners alongside the calculated efficiency grade.

### Fixed
- **Roamer efficiency unit conversion** — Source data from Rivian Roamer (`efficiency_km_per_kwh`) is in km/kWh. Values are now correctly converted to mi/kWh (× 0.621371) during sync. Admin labels on the Roamer Sync page and tire edit form updated from "km/kWh" to "mi/kWh" for consistency.
- **Compare view Real-World Efficiency styling** — Added blue pill background behind mi/kWh values and fixed the row label background not filling the full row height on multi-line rows.

### Changed
- **Plugin version** — Bumped to 1.33.0.

## [1.30.0] - 2026-03-30

### Added
- **Rivian Roamer real-world efficiency data** — Integrates live tire efficiency data (mi/kWh) collected from Rivian owners via [Rivian Roamer](https://rivianroamer.com). Data syncs automatically twice daily via WP-Cron and is displayed alongside the calculated efficiency score on tire cards, comparison pages, and the REST API feed.
- **Roamer Sync admin page** — New admin page (Tire Guide > Roamer Sync) for managing the integration: sync status dashboard, settings (enable/disable, feed URL), linked tires table, ambiguous match resolution with dropdown assignment, unmatched Roamer tires with multi-select assign, and paginated unlinked guide tires list.
- **Manual Roamer mapping** — Tires with the same name and size but different load ratings are flagged as ambiguous and skipped for manual review. Admins can assign Roamer data via the sync page or directly on the tire edit form. Multiple Roamer entries can be assigned to one tire with weighted-average efficiency.
- **Real-World Efficiency sort** — New "Real-World Efficiency" option in the sort dropdown, ordering tires by mi/kWh (tires without data sorted to bottom).
- **Real-World Efficiency on compare page** — New row in the Performance section showing mi/kWh with session count and best-value highlighting.
- **Roamer fields in REST API** — The `/wp-json/rtg/v1/feed` endpoint now includes `roamer_efficiency`, `roamer_session_count`, `roamer_vehicle_count`, and `roamer_synced_at`.
- **Tire edit form Roamer section** — New "Rivian Roamer — Real-World Data" card on the tire edit page showing linked Roamer ID, mi/kWh, session count, vehicle count, and km tracked.
- **Dashboard Roamer cards** — New "Rivian Roamer — Real-World Efficiency" overview card with coverage, avg/best/worst mi/kWh, total sessions, total vehicles, and last sync status. New "Most Efficient (Real-World)" ranked list of top 5 tires by Roamer mi/kWh.
- **WP Dashboard widget** — Roamer sync coverage stat (X/Y tires linked) with link to Roamer Sync page.

### Improved
- **Real-world efficiency display** — mi/kWh shown as a bordered pill badge next to the calculated efficiency badge on tire cards, with its own info tooltip linking to Rivian Roamer. Tags (EV Rated, etc.) moved to a dedicated row at the bottom of the spec list.

### Fixed
- **Ambiguous/unmatched assignments persist** — Assigned tires are now removed from the stored sync stats immediately, so they no longer reappear in the ambiguous or unmatched tables after page reload or sync.
- **Multi-assign sync recognition** — Comma-separated `roamer_tire_id` values from multi-assign are now correctly recognized during sync so tires stay linked.

### Changed
- **Database schema** — Migration 12 adds 6 columns to `wp_rtg_tires`: `roamer_tire_id`, `roamer_efficiency`, `roamer_session_count`, `roamer_total_km`, `roamer_vehicle_count`, `roamer_synced_at`.
- **Plugin version** — Bumped to 1.30.0.

## [1.28.2] - 2026-03-16

### Fixed
- **Tire size dropdown no longer disables options** — Previously, selecting a tire size would disable all other size options with zero matches, forcing users to clear filters before switching. All dropdown options now stay enabled so users can freely change their selection.

### Changed
- **Plugin version** — Bumped to 1.28.2.

## [1.28.1] - 2026-03-16

### Changed
- **Minified assets verified** — Rebuilt all minified JS and CSS bundles (esbuild) to ensure production assets are up-to-date with source files.
- **Plugin version** — Bumped to 1.28.1.

## [1.28.0] - 2026-03-15

### Added
- **JSON data feed endpoint** — New public REST API endpoint (`GET /wp-json/rtg/v1/feed`) returns the full tire catalog as a shareable JSON feed. Includes all tire specs, efficiency scores, and rating aggregates. The feed auto-updates whenever tires are added or modified. CORS-enabled for easy external consumption.
- **JSON Feed URL in admin dashboard** — A new "JSON Data Feed" card on the admin dashboard displays the feed URL with a one-click copy button and a preview link, making it easy to share your tire data with others.

### Changed
- **Plugin version** — Bumped to 1.28.0.

## [1.27.0] - 2026-03-06

### Changed
- **Dark theme color refresh** — Updated the default dark theme palette: unified accent hover with primary accent, matched card background to primary background, adjusted deep background, consolidated text colors for better readability, and updated border/divider color.
- **Button text contrast** — Buttons with the accent background now use dark text (#0f172a) instead of white for improved readability.
- **Plugin version** — Bumped to 1.27.0.

## [1.26.1] - 2026-03-06

### Changed
- **Efficiency formula recalibration** — Recalibrated width and weight score baselines to produce fair scores across both R1 and R2 tire ranges. Added speed rating Y to the scoring map. Existing tire scores will shift minimally (~1-2 points). Run "Recalculate All" from the admin to update.
- **R2 default tire sizes** — Added R2 tire sizes to the default sizes dropdown.
- **Plugin version** — Bumped to 1.26.1.

## [1.26.0] - 2026-03-06

### Added
- **Vehicle filter toggle** — A segmented toggle (All / R1 / R2) now appears above the filter dropdowns, letting users instantly filter tires by Rivian vehicle. R1T and R1S are grouped as "R1" since they share the same tire sizes. Selecting a vehicle cascades to narrow the Size dropdown to only compatible sizes.
- **Vehicle-to-size mapping from wheels** — Vehicle compatibility is automatically derived from the stock wheels database. Adding a wheel with a new vehicle (e.g., R2) in the Wheels admin makes it appear in the frontend toggle with no additional configuration.
- **Vehicle shortcode attribute** — New `vehicle` attribute for the `[rivian_tire_guide]` shortcode: `[rivian_tire_guide vehicle="R2"]` pre-filters to that vehicle on page load.
- **Vehicle URL parameter** — `?vehicle=R2` filter state is preserved in the URL for sharing and browser back/forward navigation.
- **Vehicle filter chip** — Active vehicle filter appears as a dismissible chip alongside other active filter pills.
- **Vehicle in smart no-results** — When no tires match, the smart suggestions include an option to remove the vehicle filter.

### Changed
- **Vehicle toggle layout** — Moved the vehicle toggle inline with the Size/Brand/Category dropdowns instead of floating alone above them. Matches dropdown height for a cleaner, more integrated look.
- **Plugin version** — Bumped to 1.26.0.

## [1.25.1] - 2026-03-06

### Improved
- **Frontend tire size filter managed from admin** — The size filter dropdown on the frontend is now sourced from the admin-managed sizes list in Settings → Dropdown Options, merged with sizes found in tire data. New tire sizes added in the admin panel immediately appear in the frontend filter without needing to first create a tire with that size.

### Changed
- **Plugin version** — Bumped to 1.25.1.

## [1.25.0] - 2026-03-04

### Improved
- **Slider labels show filtering direction** — Price and weight sliders now display "Max Price: ≤ $X" and "Max Weight: ≤ X lbs" to clarify they set an upper bound. Weight label now includes "lbs" unit.
- **Warranty filter flipped to minimum threshold** — The warranty slider now filters for tires with *at least* the selected mileage (≥), matching user intent. Previously it filtered for tires *up to* a value which was counterintuitive.
- **Mobile filter button shows active count** — On mobile, the collapsed filter toggle now displays a badge (e.g., "Filters (3)") showing how many filters are active, and properly toggles between "Show/Hide Filters".
- **Live result count in filter panel** — A new inline count ("42 tires match your filters") appears inside the filter section when filters are active, giving immediate feedback without scrolling to results.
- **Dropdown options show tire counts** — Size, Brand, and Category dropdowns now display the number of matching tires per option (e.g., "Continental (12)"). Options with zero matches are disabled to prevent dead-end selections.
- **Clear All button restyled** — The reset button is now visually distinct from filter toggles, using a red outline style with a rotate icon so users recognize it as a destructive action.
- **Tooltip button styles moved to CSS** — Inline styles and `onmouseenter`/`onmouseleave` handlers on info tooltip buttons have been replaced with a proper `.info-tooltip-trigger` CSS class, improving maintainability and touch device support.
- **Sort dropdown handler fixed** — Removed inline `onchange` attribute from the sort dropdown. It now uses a proper event listener that correctly routes through server-side rendering when in server-side mode.

### Changed
- **Plugin version** — Bumped to 1.25.0.

## [1.24.3] - 2026-03-02

### Fixed
- **Review email link broken** — The guest review notification email linked to a non-existent admin page (`rivian-tire-guide-reviews` instead of `rtg-reviews`), causing a "not allowed to access this page" error when clicking the link.

### Changed
- **Plugin version** — Bumped to 1.24.3.

## [1.24.2] - 2026-03-02

### Added
- **Link check progress bar** — The "Check Links Now" button now shows a live progress bar with status text ("Checking link 12 of 38...") and a running count of broken links found. Links are checked in batches of 5 via sequential AJAX calls, replacing the single long-running request. Page auto-reloads after 1.5 seconds with a summary message.

### Fixed
- **Network error alert on page leave** — Navigating away during a link check no longer shows a "Network error" alert. An `isUnloading` flag suppresses error callbacks from cancelled AJAX requests, and a `beforeunload` confirmation warns the user that a check is still running.

### Changed
- **Plugin version** — Bumped to 1.24.2.

## [1.24.0] - 2026-03-02

### Added
- **Affiliate link health checker** — New `RTG_Link_Checker` class that detects broken affiliate links by following redirects and flagging links that land on the supplier homepage instead of the product page. Also catches HTTP errors (4xx/5xx) and connection failures.
- **Weekly automated checks** — WP-Cron runs the link health check once per week. A custom `weekly` cron schedule is registered for this purpose.
- **"Check Links Now" button** — Manual trigger on the Affiliate Links admin page to run the health check on demand without waiting for the weekly schedule.
- **Broken link badges** — Each broken tire row in the Affiliate Links table shows a red "Broken" badge with a tooltip explaining the failure reason (e.g. "Redirects to homepage").
- **"Broken" filter tab** — New filter tab on the Affiliate Links page to show only tires with broken links, alongside the existing All/Affiliate/Regular/Missing/No Review tabs.
- **Broken Links stat card** — New stat card in the Affiliate Links stats grid showing the count of broken links detected.
- **Broken link email notification** — HTML email sent to the site admin when broken links are found, listing each affected tire with its status and failure details, plus a direct link to the admin dashboard.
- **Dashboard health indicator** — Content Health section on the main dashboard now includes a broken affiliate links indicator with a link to the Affiliate Links page for remediation.

### Fixed
- **"Check Links Now" network error** — The AJAX handler could exceed PHP's `max_execution_time` when checking many links. Added `set_time_limit(300)` and eliminated a redundant second HTTP request per link in `get_effective_url()` by reading the final URL from WordPress's transport response object.
- **Affiliate link checker missing broken CJ links** — `check_single_link()` used `wp_remote_head`, but affiliate networks like CJ (jdoqocy.com) only redirect GET requests, not HEAD. Switched to `wp_remote_get` with a 4 KB response size limit so the full redirect chain is followed.

## [1.23.0] - 2026-03-01

### Added
- **Standalone tire review page** — New shareable page at `/tire-review/` where anyone can select a tire and submit a review without navigating the full catalog. Features a searchable tire dropdown, inline review form with star rating, and support for both logged-in and guest reviewers. Deep-link to a specific tire via `?tire=TIRE_ID` for social sharing with tire-specific OG/Twitter meta tags.
- **Tire Review Page Slug setting** — Configurable URL slug for the review page in Settings > Display Settings (default: `tire-review`).

## [1.22.0] - 2026-03-01

### Changed
- **Share image: show tire size on top-rated callout** — The top-rated tire now displays the size in parentheses (e.g. "Michelin Defender LTX M/S (275/65R18)") so tires with the same name but different sizes can be distinguished.
- **Share image: show Avg Efficiency out of 100** — The Avg Efficiency stat card now renders as "72 / 100" instead of just "72" for clearer context.

## [1.21.1] - 2026-03-01

### Fixed
- **Share image: category pills overlapping top-rated callout** — The category pills and top-rated tire banner occupied the same vertical space when 5 brands were present. The callout Y-position is now computed dynamically from the bottom of both the stat cards and the categories section, and brand bar spacing was tightened to give categories more room.

## [1.21.0] - 2026-03-01

### Added
- **Stats share image generator** — New admin page (Tire Guide > Share Image) that generates a branded 1200x630 social media image with top stats from the tire guide. The image uses the frontend dark theme colors (dark navy background, orange accent) and includes total tires, average price, average efficiency score, community reviews, top brands bar chart, category pills, and top-rated tire callout. Customizable title, subtitle, and footer text fields with live canvas preview. Download as PNG, copy to clipboard, or regenerate on demand.

### Changed
- **Plugin version** — Bumped to 1.21.0.

## [1.20.3] - 2026-02-23

### Fixed
- **Officially Reviewed filter not filtering tires** — The client-side filter checked `row[23]` (`created_at`) instead of `row[22]` (`review_link`). Since every tire has a `created_at` timestamp, the filter never excluded anything. Also added the missing server-side plumbing so the filter works in server-side pagination mode: the `reviewed` parameter is now sent in the AJAX request, accepted by the PHP handler, and applied as a `review_link != ''` WHERE clause in the database query.
- **Plugin version** — Bumped to 1.20.3.

## [1.20.1] - 2026-02-22

### Fixed
- **Nonce passed to review endpoints** — `get_tire_reviews` and `rtg_get_user_reviews` AJAX calls now include the nonce for logged-in users, fixing "Security check failed" errors when authenticated users attempted to load reviews or view their review history.
- **Plugin version** — Bumped to 1.20.1.

## [1.20.0] - 2026-02-22

### Added
- **Delete own rating** — Logged-in users can now delete their own tire rating via a new `delete_tire_rating` AJAX endpoint. Backed by `RTG_Database::delete_user_rating()` which only deletes ratings matching the current user. Returns updated aggregate rating data after deletion.
- **Admin dashboard widget** — WordPress dashboard widget ("Tire Guide — Quick Stats") showing total tires, average rating, total ratings, average price, pending review count with link to moderation queue, missing links/images counts, and top-rated tire at a glance.
- **AJAX integration tests** — New `tests/test-ajax.php` with 14 integration tests extending `WP_Ajax_UnitTestCase`. Covers `get_tire_ratings`, `submit_tire_rating` (success, missing nonce, invalid rating, nonexistent tire, review text with pending status), `delete_tire_rating` (success, no rating, cross-user prevention), `get_tire_reviews`, and favorites lifecycle (add/get/remove cycle, nonexistent tire).
- **PHP coding standards enforcement** — `.phpcs.xml` configuration for WordPress Coding Standards (WPCS 3.x). Scans `includes/`, `admin/`, main plugin file, and `uninstall.php`. New `phpcs` CI job in GitHub Actions with `cs2pr` for inline PR annotations.

### Changed
- **PHP autoloader** — Replaced 12 manual `require_once` calls with `spl_autoload_register()`. Maps `RTG_` prefixed class names to `includes/class-rtg-*.php` files automatically. New classes are loaded without editing the main plugin file.
- **PHPDoc blocks** — Added `@param`, `@return`, and description blocks to all public methods in `RTG_Database` and `RTG_Admin`. Cleaned up orphaned dangling PHPDoc block.
- **PLUGIN-REVIEW.md** — All 41 of 41 review items now resolved (100%).
- **Plugin version** — Bumped to 1.20.0.

## [1.19.9] - 2026-02-22

### Changed
- **Auth banner margin** — Added consistent bottom, left, and right margins to the login/register banner so it aligns with the modal's field padding (24px on desktop, 16px on mobile).
- **Plugin version** — Bumped to 1.19.9.

## [1.19.8] - 2026-02-22

### Changed
- **Mobile auth banner redesign** — Redesigned the guest login/register banner in the review modal for mobile. The banner now displays as a centered card with the user icon in a circular accent-tinted badge, descriptive text ("Create an account to edit reviews and favorite tires"), and pill-shaped action buttons — "Sign up" as a filled primary button and "Log in" as an outlined secondary button. Desktop layout remains a compact inline banner.
- **Plugin version** — Bumped to 1.19.8.

## [1.19.7] - 2026-02-22

### Changed
- **Mobile review modal — full-screen takeover** — Replaced the bottom-sheet pattern with a full-screen native-app-style page on screens ≤640px. The modal now covers the entire viewport with a sticky top nav bar (close button on left, centered title), scrollable body, and a full-width sticky "Submit Review" button at the bottom. Stars are 40px with 14px gap for fat-finger tapping. Inputs use 16px font to prevent iOS auto-zoom. Animation uses a spring-like `cubic-bezier(0.32, 0.72, 0, 1)` curve for smooth slide-up. Safe-area insets respect iPhone notch/Dynamic Island and home indicator.
- **Plugin version** — Bumped to 1.19.7.

## [1.19.6] - 2026-02-22

### Fixed
- **Efficiency score info icon too small** — The info icon next to the efficiency score on tire cards was 12px in a 16×16 button, while all other info icons use 14px in a 20×20 button. Matched sizing, padding, and added missing `aria-label` and `type` attributes.
- **Officially Reviewed filter icon wrong color** — The "Officially Reviewed" filter toggle was excluded from the JS tooltip replacement that runs on page load, so it kept the PHP-hardcoded `#94a3b8` color while the other three filter icons used `var(--rtg-text-muted)` (`#8493a5`). Added it to both `updateFilterTooltipsDirectly()` and `updateFilterTooltips()`.
- **AI clear not resetting tire view** — Clicking "Clear" on the AI recommendation summary did not restore the default tire grid because `filterAndRender()` short-circuited on a matching `lastFilterState` cache key. Now clears `state.lastFilterState` before calling `filterAndRender()` so the full filter pipeline re-runs.

### Changed
- **Mobile review modal — bottom sheet** — The review modal on screens ≤640px now slides up from the bottom as a sheet with a drag-handle indicator, rounded top corners, sticky footer with action buttons, larger star touch targets (36px), 16px font inputs to prevent iOS auto-zoom, and `env(safe-area-inset-bottom)` padding for notched devices.
- **Plugin version** — Bumped to 1.19.6.

## [1.19.5] - 2026-02-22

### Security
- **Admin image preview XSS fix** — Sanitized the user-supplied image URL in the admin preview handler using the `URL` constructor to break the CodeQL taint chain (`js/xss-through-dom`). The parsed URL's protocol is checked against `http(s):` and the image extension is validated on `pathname` only, then the sanitized `parsed.href` is assigned to the native `HTMLImageElement.src` property. This replaces the previous regex-on-raw-input approach that CodeQL flagged as DOM text reinterpreted as HTML.

### Changed
- **Plugin version** — Bumped to 1.19.5.

## [1.19.3] - 2026-02-22

### Changed
- **Plugin review audit** — Audited all 7 high-priority items from `PLUGIN-REVIEW.md` and confirmed each was resolved in prior versions: rate limiting (v1.1.0), compare page XSS (v1.1.0), CSV import/export (v1.2.0), server-side pagination (v1.3.0), URL filter persistence (v1.10.0), accessibility (v1.2.0 + v1.14.0), and PHPUnit/JS test suites (v1.3.0 + v1.14.0). Updated the review document with resolution details, version references, and a note on medium/low items also addressed.
- **Plugin version** — Bumped to 1.19.3.

## [1.19.2] - 2026-02-22

### Fixed
- **Tire images loading too late** — Removed conflicting `loading="lazy"` and `fetchpriority="low"` attributes from tire card images. These were double-gating the IntersectionObserver-based lazy loading, causing images to appear blank or pop in on-screen. The IntersectionObserver is now the sole loading controller, with `decoding="async"` for non-blocking decode. Root margin increased from 200px to 600px so images load well before scrolling into view.

### Changed
- **Plugin version** — Bumped to 1.19.2.

## [1.19.1] - 2026-02-21

### Security
- **AI rate limiter IP spoofing fix** — `get_client_ip()` now prioritizes `REMOTE_ADDR` over proxy headers (`X-Forwarded-For`, `X-Real-IP`). Proxy headers are only trusted when `REMOTE_ADDR` is a private/reserved IP, indicating the server is behind a reverse proxy. Previously, attackers could bypass AI rate limiting entirely by forging proxy headers.
- **CSV import MIME validation** — Added `finfo`-based MIME type validation alongside the existing file extension check for defense-in-depth on CSV uploads.
- **Nonce verification on public review endpoints** — `get_tire_reviews` and `get_user_reviews` AJAX endpoints now verify nonces for logged-in users, consistent with the existing `get_tire_ratings` pattern.

### Changed
- **Plugin version** — Bumped to 1.19.1.

## [1.19.0] - 2026-02-21

### Added
- **Guest tire reviews** — Non-logged-in users can now submit tire reviews with their name and email. Guest reviews require a title or body text (not just star ratings) and are always held for admin approval before going live.
- **Guest review modal** — Full review modal for guests with name, email, star rating, title, and body fields. Includes a honeypot field for spam prevention.
- **Interactive stars for guests** — Star ratings are now clickable for logged-out users and open the guest review modal with the selected rating pre-filled.
- **Login/register banner** — Guest review modal shows a "Sign up or Log in to edit reviews and favorite tires" banner encouraging account creation, with links to the login and registration pages.
- **Guest review localStorage pre-fill** — Name and email are saved after a successful guest review and auto-filled the next time the modal opens, reducing friction for multi-tire reviewers.
- **"Review Pending" badge** — After a guest submits, the card swaps "Write a Review" for a styled "Review Pending" indicator (session-only, resets on page reload).
- **Admin email notification for guest reviews** — Site admin receives a styled HTML email with the guest's name, email, star rating, and review snippet whenever a new guest review is submitted, with a "Review in Dashboard" button.
- **Reviewer approval email** — When any review (guest or logged-in) is approved by an admin, the reviewer receives a styled HTML email notification with their review snippet and a link back to the tire guide.
- **IP rate limiting for guests** — Guest submissions are rate-limited to 3 per 5 minutes per IP address.
- **Duplicate guest review detection** — Prevents the same email from reviewing the same tire twice.
- **Database migration 11** — Added `guest_name` and `guest_email` columns to the ratings table, updated the unique key to `(user_id, tire_id, guest_email)` to support multiple guests per tire.
- **RTG_Mailer class** — New mailer class for sending HTML email notifications via `wp_mail()`, respecting any SMTP plugin configuration.
- **Schema.org structured data** — Guest author names are automatically handled in review structured data.

### Changed
- **Guest rate limit tightened** — Reduced from 10 submissions per minute to 3 per 5 minutes for better spam protection.
- **Guest reviewer links** — Reviews from guests (user_id 0) no longer link to a reviewer profile page in the reviews drawer.
- **Admin reviews list** — Guest reviews now display the guest's name and email with a "Guest" badge in the WordPress admin reviews panel.
- **Removed login-prompt CSS** — Cleaned up unused `.login-prompt` styles that were replaced by the guest review flow.

## [1.18.6] - 2026-02-21

### Added
- **RivianTrackr search redirect** — When a search returns no matching tires, a "Search RivianTrackr" link now appears directing users to riviantrackr.com for non-tire topics. Applies to both the local search no-results view and the AI recommendation path.

### Changed
- **AI error display** — When a query is present, the red AI error text is hidden and replaced with only the clean RivianTrackr search redirect link. The error message still appears for edge cases with no query.
- **RivianTrackr link hover** — Removed underline on hover for the RivianTrackr search redirect links.

### Fixed
- **Analytics tire list spacing** — Tire name and "unique visitors" text in the analytics dashboard now stack on separate lines instead of running together.
- **Plugin version** — Bumped to 1.18.6.

## [1.18.3] - 2026-02-21

### Changed
- **AI summary tire names** — Removed underline/highlight styling from tire names in the AI recommendation summary text. Tire chips below the summary already provide clickable navigation to each recommended tire.
- **Button text color** — Changed Ask AI and Search button text color to dark (`#0f172a`) to match the affiliate "View Tire" buttons on tire cards, improving visual consistency across the plugin.
- **Pagination button hover color** — Next/Previous pagination buttons now switch to dark text (`#0f172a`) on hover when the accent background appears, matching the Ask AI and affiliate button style.
- **Mobile search layout** — Fixed Search and Ask AI buttons not wrapping below the search input on mobile. Moved the mobile media query after the base button styles so the overrides take effect correctly.
- **Plugin version** — Bumped to 1.18.3.

## [1.18.0] - 2026-02-20

### Added
- **AI Tire Advisor** — New natural-language search powered by Anthropic's Claude API. Visitors can type queries like "best winter tire for my Rivian with 20 inch wheels" and receive AI-ranked recommendations drawn from the tire catalog data (specs, ratings, reviews, efficiency grades). The AI search bar lives inside the Filter, Sort, and Compare panel alongside the existing search and filters.
- **Admin AI settings** — New "AI Tire Recommendations" settings card with enable/disable toggle, Anthropic API key input, model selector (Claude Haiku 4.5 or Claude Sonnet 4), and configurable per-visitor rate limit.
- **AI rate limiting** — Per-IP rate limiting via WordPress transients to control API costs (default: 10 queries/minute/visitor).
- **AI response caching** — Identical queries are cached for 1 hour to reduce API calls and speed up repeated questions.

### Changed
- **Plugin version** — Bumped to 1.18.0.

## [1.17.2] - 2026-02-20

### Changed
- **Plugin version** — Bumped to 1.17.2 to bust browser and CDN caches after the Newest Added sort fix.

## [1.17.1] - 2026-02-20

### Fixed
- **"Newest Added" sort broken** — The client-side "Newest Added" sort option was reading from the wrong array index (24 instead of 23), causing it to compare empty values and effectively not sort at all. Tires now correctly sort by `created_at` descending.

## [1.17.0] - 2026-02-20

### Changed
- **Analytics timezone** — Daily charts (Clicks Over Time, Search Volume) now group data by the WordPress site timezone instead of UTC, so dates on the analytics dashboard match the site owner's local time.
- **Bar graph alignment** — Fixed "Most Used Filters" horizontal bar chart so bar tracks align consistently regardless of label length. Labels now use a fixed width with text truncation.
- **Plugin version** — Bumped to 1.17.0.

### Removed
- **Bundle links** — Removed the bundle link feature from the entire plugin UI. The bundle link field has been removed from tire editing, the affiliate links dashboard, the comparison page, tire cards, analytics charts, and CSV import/export. The database column is retained for backwards compatibility but is no longer surfaced anywhere.

## [1.15.0] - 2026-02-19

### Added
- **esbuild build pipeline** — New `package.json` with `npm run build` and `npm run build:watch` commands. esbuild minifies all JS and CSS assets, producing `.min.js` and `.min.css` files. Console/debugger statements are automatically stripped from production builds. Replaces the ad-hoc `build.sh` script.
- **GitHub Actions CI** — New `.github/workflows/ci.yml` runs JS tests, build verification, and PHP syntax linting (PHP 7.4, 8.0, 8.2) on every push and pull request.
- **REST API rate limiting** — All REST API endpoints now enforce per-IP rate limits via WordPress transients: 60 requests/minute for read endpoints, 10 requests/minute for the write (efficiency) endpoint. Returns HTTP 429 when exceeded.
- **Inline SVG icon system** — Replaced the Font Awesome 6.5 CDN dependency (~60 KB CSS + web fonts) with lightweight inline SVGs. New `RTG_Icons` PHP class and `rtgIcon()` JS helper render icons from a shared map. All ~35 Font Awesome icon references across JS and PHP templates have been replaced. CSP headers updated to remove cloudflare.com allowance.
- **Mobile range slider improvements** — Added editable number inputs alongside range sliders on mobile (visible below 600px breakpoint). Number inputs and sliders sync bidirectionally. Slider thumbs have larger 28px touch targets on mobile for easier interaction.

### Changed
- **Asset loading** — Frontend, compare, and admin pages now serve minified assets (`.min.js`/`.min.css`) when available and `SCRIPT_DEBUG` is off. Falls back to unminified sources for development.
- **Plugin version** — Bumped to 1.15.0.

## [1.14.0] - 2026-02-19

### Added
- **REST API** — New public REST API under `rtg/v1` namespace with four endpoints: `GET /tires` (filtered, paginated listing), `GET /tires/{tire_id}` (single tire with ratings), `GET /tires/{tire_id}/reviews` (paginated reviews), and `POST /efficiency` (calculate efficiency score from specs). All inputs validated and sanitized per WordPress REST API conventions.
- **Shortcode attributes** — The `[rivian_tire_guide]` shortcode now accepts optional pre-filter attributes: `size`, `brand`, `category`, `sort`, and `3pms`. Example: `[rivian_tire_guide brand="Michelin" category="All-Season" sort="price-asc"]`.
- **Skeleton loading states** — Shimmer placeholder cards display immediately while tire data loads, eliminating the flash of empty content. Respects `prefers-reduced-motion`.
- **Accessibility (a11y) improvements** — Skip-to-content link, `aria-label` attributes on all filter controls, search input, sort dropdown, and tooltip info buttons. `role="status"` and `aria-live="polite"` on no-results container. Screen-reader-only labels for dropdowns. Focus-visible outline styles for all interactive elements (stars, buttons, filters, links). `.screen-reader-text` utility class.
- **JavaScript unit tests** — New `tests/test-validation.js` with 83 tests covering `escapeHTML`, `sanitizeInput`, `validateNumeric`, `safeImageURL`, `safeLinkURL`, and `fuzzyMatch`. Runnable via `node tests/test-validation.js`.
- **Efficiency calculator AJAX endpoint** — Admin tire edit form now calls the canonical PHP `RTG_Database::calculate_efficiency()` via AJAX with debouncing, eliminating the duplicate JS formula.

### Changed
- **Consolidated URL validation** — Extracted shared `escapeHTML`, `safeImageURL`, `safeLinkURL`, and `safeReviewLinkURL` into `rtg-shared.js`. The compare page now delegates to this shared module instead of maintaining duplicate implementations with divergent domain lists.
- **Admin efficiency preview** — Replaced the 95-line duplicate JS efficiency formula in `admin-scripts.js` with a debounced AJAX call to the PHP source of truth. The formula now only exists in `RTG_Database::calculate_efficiency()`.
- **Plugin version** — Bumped to 1.14.0.

## [1.13.1] - 2026-02-19

### Changed
- **Simplified tire card rating display** — Removed redundant review count from the rating line. The average score now shows cleanly next to the stars (e.g. "5.0") while the review count appears only in the actionable "X reviews" button below.
- **Plugin version** — Bumped to 1.13.1.

## [1.13.0] - 2026-02-19

### Added
- **Affiliate click tracking** — Tracks when users click purchase, bundle, and review links using `navigator.sendBeacon()` for zero-latency, privacy-respecting analytics. New `wp_rtg_click_events` database table with server-side 5-second deduplication.
- **Search analytics** — Tracks user search queries, active filters, sort options, and result counts. New `wp_rtg_search_events` database table with 2-second client-side debounce and 3-second server-side deduplication.
- **Analytics admin page** — New admin page (Tire Guide > Analytics) with period selector (7/30/90 days), summary cards (total clicks, unique visitors, total searches), Chart.js line charts for clicks-over-time and search volume, ranked tables for most clicked tires, top search queries, zero-result searches (unmet demand), and most used filters.
- **Analytics data retention** — Configurable retention period (7–365 days, default 90) in Settings. Daily WP-Cron job automatically cleans up old events.

### Changed
- **Database schema version** — Bumped to v9 with migrations 8–9 for click events and search events tables.
- **Plugin version** — Bumped to 1.13.0.

## [1.12.1] - 2026-02-19

### Fixed
- **Dashboard bar charts not rendering** — Horizontal bar fill elements were invisible because `<span>` elements default to `display: inline`, which ignores width/height. Added `display: block` to `.rtg-bar-fill`.

### Changed
- **Plugin version** — Bumped to 1.12.1.

## [1.12.0] - 2026-02-19

### Added
- **Admin dashboard** — New default landing page when opening Tire Guide in the admin panel. Shows overview cards (total tires, average price, average efficiency score, total ratings), breakdowns by category/brand/size/efficiency grade with horizontal bar charts, key insights (price and weight ranges, affiliate link coverage), top rated and most reviewed tires, content health indicators (pending reviews, missing images, missing links with action buttons), and recently added tires.

### Changed
- **Plugin version** — Bumped to 1.12.0.

## [1.11.1] - 2026-02-19

### Added
- **Configurable affiliate domains** — New "Affiliate Link Domains" section on the Settings page. Admins can add or remove affiliate network domains (one per line) to control how links are classified on the Affiliate Links dashboard. Protocols and www prefixes are stripped automatically.

### Changed
- **Plugin version** — Bumped to 1.11.1.

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
