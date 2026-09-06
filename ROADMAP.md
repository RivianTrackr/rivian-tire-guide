# Rivian Tire Guide — Roadmap

**Current release:** 2.3.2 (DB schema v25)
**Updated:** 2026-09-06

This is the one place open work is tracked. It replaces the four planning
documents that used to sit beside it — `PLUGIN-REVIEW.md` (the v1.48
enhancement list), `ROADMAP-2.0.md` (the v1.51 four-pillar plan),
`PLUGIN-REVIEW-2026-08.md` (the v1.84.2 full-codebase review) and
`PLUGIN-REVIEW-2026-09.md` (the v1.86.0 review) — each of which mixed
shipped, half-shipped and open items. Everything still open from all four is
here, once; everything shipped is in `CHANGELOG.md`.

**Conventions.** Items carry an ID (`H-` hygiene, `A11Y-` accessibility,
`F-` shopper-facing feature, `R-` reviews and community, `ADM-` admin,
`P-` platform, `B-` backlog). When an item ships, delete it here and describe
it in the changelog. File references name the place to start, not a line
number.

---

## 1. Hygiene still open from the v1.84.2 review

Small, contained fixes. Each was verified still present at 1.87.0.

| ID | Item | Where |
|----|------|-------|
| H1 | The link checker's per-link request disables TLS verification (`sslverify => false`); nothing else in the plugin does. | `includes/class-rtg-link-checker.php` (`check_single_link`) |
| H2 | CSV export writes raw values with no formula-prefix escaping (`=`, `+`, `-`, `@`); a crafted feed-derived link executes when the export opens in Excel. | `includes/class-rtg-admin.php` (`handle_csv_export`) |
| H3 | Manual Roamer runs email despite the "manual runs don't email" design: the new-tire notification gates on the notify setting alone where failures correctly gate on cron **and** the setting. | `includes/class-rtg-roamer-sync.php` (`run_locked`, the `maybe_send_notification` call) |
| H4 | Rating writes don't invalidate the dashboard-stats transient, though the comment on the cache says they do. (1.87.0 added the review-count cache invalidation; the dashboard blob is still stale for up to five minutes after a review.) | `includes/class-rtg-database.php` (`set_rating`, `set_guest_rating`, `update_review_status`, `flush_cache`) |
| H5 | `SHOW COLUMNS` runs before every five-minute Roamer sync, guarding against a dbDelta failure migrations 13 and 14 already patched. | `includes/class-rtg-roamer-sync.php` (schema probe near the top of the run) |
| H6 | `rtg_settings` (which carries the CJ token and query) is autoloaded; pass `false` as the autoload flag on first write. | `includes/class-rtg-activator.php` (defaults seeding) |
| H7 | The production hostname is hardcoded in the image URL prefix, in three places; staging clones import locally but store production URLs. Derive from `home_url()` and expose one constant. | `includes/class-rtg-tire-images.php` (`URL_PREFIX`), `includes/class-rtg-admin.php` (`build_image_url`), `admin/views/tire-edit.php` |
| H8 | `uninstall.php` never drops `rtg_wheels`, leaves ~11 options behind (`rtg_roamer_sync_stats`, `rtg_price_sync_results`, `rtg_link_sync_results`, `rtg_link_check_results`, `rtg_link_check_cursor`, `rtg_cj_sweep_cursor`, `rtg_slug_redirects`, `rtg_health_state`, `rtg_tire_images_last`, `rtg_roamer_hidden_ids`, `rtg_affiliate_domains`, the `rtg_lock_*` rows), never clears scheduled hooks, and is not multisite-aware. | `uninstall.php` |
| H9 | Emptying a Dropdown Options textarea silently reverts to the shipped defaults instead of clearing. | `includes/class-rtg-admin.php` (`save_settings_from_post`, dropdown handling) |
| H10 | The Analytics page has no guard for Chart.js failing to load from the CDN; panels stay "Loading…" forever. Bundling Chart.js locally (ADM14) closes this too. | `admin/views/analytics.php` |
| H11 | Share-image canvas: category pills are measured while the 18px heading font is still active, so they render ~40% too wide, and the hardcoded colors ignore the customized palette. | `admin/js/admin-scripts.js` (share image section) |
| H12 | `frontend/js/tire-review.js` has no esbuild target and is the one asset served permanently unminified. | `esbuild.config.mjs`, `includes/class-rtg-tire-review.php` |
| H13 | Card exit animation is cut short: removal fires after the animation *delay* (100/150 ms) while the transition runs 200/300 ms. | `frontend/js/modules/cards.js` (`renderCards` removal path) |
| H14 | `phpunit.xml` still uses PHPUnit 8 `<filter><whitelist>` on PHPUnit 9.6, so coverage config is silently ignored; PHPCS runs advisory with ~380 findings and no ratchet; no phpstan. A ratcheting baseline plus phpstan at a modest level would have caught the undefined-variable bug 1.86.0 fixed. | `phpunit.xml`, `.github/workflows/ci.yml` |
| H15 | `tire-page.js` hand-copies four `TOOLTIP_DATA` entries because the tire page can't import the guide bundle; the file itself says the copies need manual sync. | `frontend/js/tire-page.js`, `frontend/js/modules/tooltips.js` |

## 2. Accessibility still open

| ID | Item | Where |
|----|------|-------|
| A11Y1 | Vehicle toggle is `role="radiogroup"` containing plain buttons with `aria-pressed`; should be `role="radio"` / `aria-checked` with arrow-key movement. | `frontend/templates/tire-guide.php`, `frontend/js/modules/filters.js` (`setActiveVehicle`) |
| A11Y2 | Compare page section headers are click-only `<div>`s with an inline `onclick`: no button, no `tabindex`, no `aria-expanded`, and the inline handler breaks under a strict CSP. | `frontend/js/compare.js` (section header render) |
| A11Y3 | Mobile filter drawer: no Escape-to-close and no focus return to the toggle button. | `frontend/js/rivian-tires.js` (drawer setup) |
| A11Y4 | Toasts in the ratings module and on the tire-review page lack `role="status"` (the guide's other toasts don't either). | `frontend/js/modules/ratings.js`, `frontend/js/tire-review.js` |
| A11Y5 | Tire-review page search dropdown lacks combobox semantics. | `frontend/js/tire-review.js` |
| A11Y7 | The guest-review privacy note is not associated with the email field via `aria-describedby`. | `frontend/js/modules/ratings.js` (guest fields), `frontend/templates/tire-review.php` |
| A11Y8 | Silent side effect: the four-tire compare cap silently un-checks the fifth box. (The vehicle→size cascade half shipped in 1.88.0 with F12.) | `frontend/js/modules/compare.js` |
| A11Y9 | `filterResultCount` and `tireCount` are two live regions announcing the same number. | `frontend/templates/tire-guide.php` |

## 3. Shopper-facing features

These turn data the plugin already stores into a decision on the card. They
are what moves the guide from "a catalog you browse" to "an advisor that
guides you", the through-line of the 2.0 plan. Each is small because the
inputs already exist in the 32-column row. 1.88.0 shipped F1, F3, F4, F6,
F7, F8, F12 and F13, and 2.1.0 shipped F9; what is left is below.

| ID | Feature | Why it matters | Where it plugs in |
|----|---------|----------------|-------------------|
| F5 | **Price history, price-drop badge, price-drop alerts.** New `rtg_price_history` (tire_id, price, retailer, observed_at) written by `update_tire()` on a price change. Unlocks a sparkline on the tire page, "lowest in 90 days", a "price dropped" card badge, and a subscribe-to-drop email via `RTG_Mailer`. Depends on P1 hooks. | Price sync overwrites history in place today; every past price is destroyed. | `class-rtg-price-sync.php`, `class-rtg-database.php`, migration 24 |
| F11 | **Live search on the guide.** Port the working typeahead from the tire-review page. | The review page already has it (`tire-review.js`); the guide is button/Enter-only. | `frontend/js/modules/search.js` |
| F14 | **Adaptive UX leftovers from the 2.0 plan.** Live search preview (F11), saved and shareable searches, seasonal callouts using the existing category field. | — | — |

## 4. Reviews → trust and community

The 2.0 plan's Pillar 3, still entirely open. Listed in the order they compound.

| ID | Feature | Notes |
|----|---------|-------|
| R1 | **Verified-owner badge.** "I own this tire" (user meta or a small `rtg_ownership` table with purchase date, price, vehicle) surfaces as a badge on the review and enables an "Owners say" filter. A guest and a three-year owner look identical today. |
| R2 | **Multi-axis ratings.** Wet grip, snow, noise, comfort, wear. The review modal already *asks* for these in prose but stores one star value. Nullable tinyint columns on `rtg_ratings`; per-axis averages on the tire page; "quietest / best in snow" sorts. |
| R3 | **"Was this helpful?" votes.** A sort signal for the review list (2.1.0 sorts by date and stars only) and a moderation signal. |
| R4 | **Review photos.** Moderated upload through the media library, thumbnail on the review, lightbox via `image-modal.js`. Video links (YouTube/Vimeo via `wp_oembed_get()`) fit the same column family. |
| R5 | **Tire wear / mileage logging** for owned tires (depends on R1): periodic tread-depth entries, a wear chart, projected life vs the mileage warranty, anonymous aggregate "average owner gets X miles". |
| R6 | **Community Q&A** on tire pages: questions, answers, upvotes, accepted answers, a moderation tab, email on new answers. |
| R7 | **Public profile page** (`[rtg_user_profile]`): reviews, owned tires; link from review attribution. |
| R8 | **AI-assisted moderation** of the pending queue (spam/duplicate/toxicity scoring). Depends on P5. |

## 5. Admin and operator

| ID | Feature | Where |
|----|---------|-------|
| ADM1 | **Trash / undo for tires.** `delete_tire()` hard-deletes the row and every rating. Add `deleted_at`, a Trash tab, restore, a 30-day purge. | `class-rtg-database.php` |
| ADM2 | **Audit log.** `rtg_audit` (actor, entity, field diffs, source: ui / csv / sync / cron). Every handler writes without recording the user; syncs rewrite prices with no trace. Pairs with F5. | `class-rtg-admin.php` handlers, sync classes |
| ADM3 | **CSV import dry-run.** Parse, validate, and show a per-row insert / update / skip / error table before writing. | `class-rtg-admin.php` (`handle_csv_import`) |
| ADM4 | **Bulk review moderation.** Checkbox + Approve / Reject / Delete selected, same pattern as tire bulk actions. | `admin/views/reviews-list.php` |
| ADM5 | **Export reviews and analytics to CSV** with a date range. `fputcsv` exists only for tires. | `admin/views/analytics.php`, `reviews-list.php` |
| ADM6 | **WP-CLI commands.** `wp rtg sync catalog|roamer|prices|links`, `wp rtg import <file> [--dry-run]`, `wp rtg recalc-efficiency`, `wp rtg cleanup-analytics`. Removes the browser-timeout budget dance and the 2 MB CSV cap for large operations. | new `includes/class-rtg-cli.php` |
| ADM7 | **Capability split.** `manage_options` is hardcoded in ~25 places. `rtg_manage_tires` / `rtg_moderate_reviews` / `rtg_view_analytics` let an editor moderate without site-admin rights. | `class-rtg-admin.php` (`EDIT_CAPABILITY`) |
| ADM8 | **Media library picker and bulk image upload.** Image is a bare text field behind a hardcoded prefix (H7). | `admin/views/tire-edit.php` |
| ADM9 | **Notification recipients, test email, sent log.** Nine mailer paths hardcode `admin_email`. Recipient list, digest-vs-immediate per event, a "Send test" button, an in-admin log of what went out. | `class-rtg-mailer.php` |
| ADM10 | **Cron job registry.** One admin card listing all five hooks with last run, duration, next run, failure count, "run now", and the lock state (`RTG_Lock`). `RTG_Health` probes only the catalog sync. | `rivian-tire-guide.php`, `class-rtg-health.php` |
| ADM11 | **Settings that are missing or hardcoded.** No input for `tire_page_slug` (read by the tire page, absent from Settings). Hardcoded: admin list page size 20, CSV cap 2 MB, link-check batch 50 / 15 s, stale-price 90 d, health staleness 36 h. | `admin/views/settings.php` and the classes named |
| ADM12 | **Dashboard insights.** Discovery funnel (new → imported, time to decision), review velocity per week, click-through rate per tire (needs an impression event to give clicks a denominator), price-change feed (needs F5). | `admin/views/dashboard.php`, `analytics.php` |
| ADM13 | **Near-duplicate report.** Scan the existing catalog for brand/model/size near-collisions using `RTG_Coverage::model_similarity()`. Duplicates are only blocked at insert and edit. | `class-rtg-coverage.php` |
| ADM14 | **Bundle Chart.js locally.** Loaded from jsDelivr with no SRI and no fallback; blank under a strict CSP or offline. Closes H10. | `class-rtg-admin.php` (enqueue) |
| ADM15 | **Wheel list and affiliate-links pagination/search.** Both load the whole table with no search, sort or pagination. | `admin/views/wheel-list.php`, `affiliate-links.php` |
| ADM16 | **Stop full-page reloads after admin AJAX.** Roamer and discovery actions `location.reload()` on success and `alert()` on failure, losing filters and scroll. | `admin/js/rtg-roamer.js`, `rtg-discovery.js` |
| ADM17 | **Inline validation on the tire form.** Only brand/model are `required`; size format, load index vs max load, slug uniqueness are server-only. | `admin/views/tire-edit.php` |
| ADM18 | **Scheduled CSV import from a URL** (daily/weekly, basic auth, history, email on result) and a **bulk price update tool** (percent change by brand/category with preview; records into F5's history). | `class-rtg-admin.php` |
| ADM19 | **Inline table editing** on the tire list (click a price or category cell, save on blur). The two-step bulk edit form shipped; inline editing did not. | `admin/views/tire-list.php`, `admin/js/admin-scripts.js` |

## 6. Platform and architecture

| ID | Item | Status / rationale |
|----|------|--------------------|
| P1 | **Lifecycle hooks.** Zero `do_action()` in the plugin. Add `rtg_tire_created/updated/deleted`, `rtg_review_submitted/approved`, `rtg_price_changed`, `rtg_sync_complete`. Prerequisite for ADM2, F5 alerts, webhooks (B10), external logging. | Open since v1.51 |
| P2 | **Keyed row objects.** Still positional arrays — 32 elements as of 1.89.0, which had to append three columns and teach three destructurings about them. Emit `{tire_id, brand, ...}` from `to_frontend_row()` and migrate `cards.js`, `filters.js`, `compare.js`, `ratings.js`. REST `/tires` leaks the array while `/tires/{id}` returns an object. | The single highest-leverage refactor; every new card feature above pays its tax |
| P3 | **Sitemap for core / Yoast / Rank Math.** Only the AIOSEO filter is hooked; add `wp_sitemaps_add_provider` as the primary plus the Yoast and Rank Math filters. | `class-rtg-tire-page.php` |
| P4 | **REST parity and new endpoints.** `/tires` supports 4 filters vs 9 on AJAX (same `build_filter_where_clause`). Add `search`, `vehicle`, `oem`, `price_max`, `warranty_min`; new `/suggest?q=`, `/compare?ids=`, `/sizes`, `/brands`, authenticated `POST /reviews`. | `class-rtg-rest-api.php` |
| P6 | **i18n.** Zero `__()` calls and no `load_plugin_textdomain()` despite the text-domain header. Large but mechanical; do it before more UI lands. | all PHP, `frontend/js` strings via `wp_localize_script` |
| P7 | **Cache the uncached hot paths.** `get_filter_options` (4 DISTINCT + MAX per call), the `[rivian_tire_guide]` page lookup on every tire-page, guide, compare and admin-list render, the full-table load for the sitemap filter. A thin `RTG_Cache` wrapper with versioned keys makes these one-liners and gives an object-cache story. | `class-rtg-ajax.php`, `class-rtg-tire-page.php` (`guide_url`), `class-rtg-frontend.php` |
| P8 | **Sync-job base class.** Six jobs hand-roll the same skeleton (enabled check, lock, stats option, cron-vs-manual notify, mailer). An `RTG_Sync_Job` base gives time budget, stats, and retry/backoff on CJ 429/5xx once; H3 and H5 fall out of it. | sync classes, `class-rtg-catalog-source-cj.php` |
| P9 | **`RTG_Database` split.** ~2,500 lines, ~80 static methods; stopped growing but never split. Tires / slugs / ratings / wheels / analytics / efficiency are natural seams. | `class-rtg-database.php` |
| P10 | **Observability.** One `error_log` line in the plugin. Add `do_action('rtg_log', $level, $msg, $ctx)` and a structured sync-run table (feeds ADM10). | `class-rtg-ajax.php` |
| P11 | **Test coverage gaps.** No PHPUnit coverage for `RTG_REST_API`, `RTG_Candidates` (900+ lines), `RTG_Link_Checker`, `RTG_Mailer`, `RTG_Schema`, `RTG_Meta`, `RTG_Tire_Page` routing and 301s, `RTG_Compare`, `RTG_Health`; the admin AJAX endpoints (`candidate_bulk`, `roamer_assign`) and their capability/nonce rejection paths. | `tests/` |
| P12 | **Down-migrations.** Forward-only; the m15→m16 episode was handled with a compensating forward migration. | `class-rtg-activator.php` |
| P13 | **Roamer ambiguous/unmatched state lives in the stats blob.** `roamer_assign` edits the last run's snapshot, which the next five-minute run overwrites wholesale, so hide/assign race the cron. A small table (or the candidates pattern) makes it race-free. | `class-rtg-ajax.php` (`roamer_assign`), `class-rtg-roamer-sync.php` |

## 7. Backlog

Ideas from the v1.48 list not already absorbed above. Kept so they aren't
re-proposed; none is scheduled.

| ID | Idea |
|----|------|
| B1 | Frontend dark/light toggle (the CSS custom-property pipeline already exists; add a dark palette and a `localStorage` preference). |
| B2 | Tire noise rating (dB) column, filter slider and card badge. |
| B3 | Road-hazard warranty field, badge and filter. |
| B4 | Multiple images per tire (`rtg_tire_images` table, swipe gallery in `image-modal.js`, sortable upload in admin). |
| B5 | Tire size calculator shortcode (metric/imperial, diameter, sidewall, speedometer error), pre-filled from a card. |
| B6 | Brand pages (`rtg_brands`: logo, description, site) with `Brand` schema, linked from cards. |
| B7 | Comparison history (last 10 sets; localStorage for guests, a table for users) and a "Recent Comparisons" dropdown on the compare page. |
| B8 | Export a comparison to PDF. |
| B9 | Open Graph meta for compare URLs ("Compare: A vs B"). `RTG_Meta` covers the guide and tire pages only. |
| B10 | Outbound webhooks (new review, tire added/updated/deleted, price change) with a delivery log. Depends on P1. |
| B11 | Multisite: network-wide activation, `switch_to_blog()` in cron, a network settings page. |
| B12 | PWA: manifest, service worker, offline catalog. |
| B13 | Image optimization: WebP/AVIF conversion on import, `<picture>` fallback. |
| B14 | JWT auth for REST writes (mobile app readiness). |
| B15 | A/B testing of CTA text/placement, measured through the existing click events. |
| B16 | RSS feed of new tires and (with F5) price changes. |

## 8. Explicitly deferred

GraphQL layer, session replay, retailer price-scraping (ToS/vendor risk; CJ's
feed is the sanctioned path). Real, but none load-bearing.

## 9. Tooling

Tools around the code rather than in it. None changes what a visitor sees;
each turns a class of mistake into a red check. Added 2026-09-06 after a
review of what the CI in `ci.yml` cannot see: nothing exercises a real page
in a browser, nothing checks types in the PHP, nothing lints the JavaScript
or CSS, and nothing measures performance or accessibility.

| ID | Item | Why | Where |
|----|------|-----|-------|
| T1 | **PHPStan with the WordPress extension.** `szepeviktor/phpstan-wordpress` on top of `php-stubs/wordpress-stubs`, a generated baseline for today's findings, the level ratcheted up over time. | Catches undefined variables, wrong argument types and nullability mistakes that PHPCS never sees; the undefined-variable bug 1.86.0 fixed is the model case (H14). | `composer.json`, `phpstan.neon`, `.github/workflows/ci.yml` |
| T2 | **WordPress Playground PR previews.** `WordPress/action-wp-playground-pr-preview@v3`, which builds the plugin zip and posts a one-click "Preview in Playground" link on every pull request. | Reviewers use the real guide in the browser before merging instead of reading the diff or deploying to see a change. | `.github/workflows/` |
| T3 | **Playwright end-to-end tests with axe on wp-env.** `@wordpress/env` boots WordPress in Docker, a seed script creates the guide page and a few tires, Playwright drives the vehicle toggle, filters, compare bar and review modal, and `@axe-core/playwright` reports accessibility violations on each page. | The Node tests cover fitment, pricing and validation math only; nothing clicks the guide. The nine open §2 items become checks that stay fixed. | `.wp-env.json`, `playwright.config.js`, `tests/e2e/` |
| T4 | **Biome for JavaScript and CSS.** One binary for linting and formatting `frontend/`, `admin/` and `tests/`, minified output excluded. | The JavaScript and CSS have never had a linter; unused variables, unreachable code and typos go straight through esbuild. | `biome.json`, `package.json` |
| T5 | **Lighthouse CI with a performance budget.** `@lhci/cli` against the wp-env guide page in the same CI job as T3, asserting on Largest Contentful Paint, script bytes and the accessibility score. | The guide lives on a consumer site with ads; a heavier page shows up here before readers feel it. | `lighthouserc.json`, `.github/workflows/ci.yml` |
| T6 | **Bundle Chart.js locally.** `chart.js` as an npm dependency, an esbuild target that exposes `window.Chart`, the analytics page enqueues the local file. | Closes ADM14 and H10: the only third-party CDN script in the plugin, with no SRI and no fallback, blank under a strict CSP or offline. | `esbuild.config.mjs`, `admin/js/rtg-charts.js`, `class-rtg-admin.php` (enqueue) |

Notes from a first pass (2026-09-06), so the next attempt starts ahead:

- **T1.** PHPStan 2.1 at level 5 with `szepeviktor/phpstan-wordpress` 2.0
  reports 95 findings once a bootstrap file defines the `RTG_*` constants
  (without it, 41 more are "constant not found"). Most are `esc_html()` and
  `esc_attr()` given an int or float, which WordPress accepts and the stubs
  type as string; baseline those. Worth a look on their own:
  `RTG_Catalog_Source::fetch()` called with two arguments where the base
  class declares one (`class-rtg-catalog-sync.php`), two `DOING_CRON` reads
  that should be `wp_doing_cron()`, `RTG_Frontend::$shortcode_present`
  written and never read, and `add_submenu_page()` with a null parent.
  Composer's `allow-plugins` should be false; the extension is included by
  path in `phpstan.neon`, no installer plugin needed.
- **T2.** Version 3 of the action needs two workflow files (build, then a
  `workflow_run` publish) and a zip whose top folder is the plugin slug;
  `git archive --prefix=rivian-tire-guide/` of the PR head does it with no
  npm install, since the minified assets are committed. A `.gitattributes`
  with `export-ignore` keeps tests and tooling config out of the zip.
- **T3.** `@wordpress/env` needs a Docker daemon and downloads WordPress
  itself, so it runs in GitHub Actions but not in a sandbox without egress
  to wordpress.org. Seed through a mapped `tests/e2e/fixtures/` directory
  and `wp eval-file`: `RTG_Database::insert_wheel()` (name, stock_size,
  alt_sizes, vehicles) drives the vehicle toggle, `insert_tire()` the cards,
  and a page holding `[rivian_tire_guide]` is the URL under test. Hooks to
  drive: `.rtg-vehicle-btn[data-vehicle]`, `#filterSize`, `[data-tire-id]`
  cards, `.compare-checkbox` and `#compareBar`, `#rtg-review-modal`.
- **T4.** Biome 2.5 lint with the recommended rules on the source (minified
  output excluded) gives 111 errors: 92 `noInnerDeclarations` (`var` inside
  blocks, mostly `tire-review.js` and `tire-page.js`), 18
  `useIterableCallbackReturn` (`forEach` arrows returning `appendChild`),
  and one real `noRedeclare` in `user-reviews.js`. Downgrade the first two
  to warnings and fix the third to start green. The formatter, with the
  codebase's own settings (two-space indent, single quotes, 120 columns),
  would still rewrite about 3,300 JavaScript lines and most of both CSS
  files, so adopt the linter first and the formatter in one mechanical
  commit of its own.
- **T6.** `chart.js/auto` imported by a ten-line `admin/js/rtg-charts.js`
  that assigns `window.Chart`, bundled as an IIFE, is a 200 KB file and a
  one-line enqueue change; the analytics page's inline script is untouched.

---

## Suggested order of attack

1. **Finish the tire page as landing page:** P3 so non-AIOSEO sites get indexed (F6, F7 and F8 shipped in 1.88.0, F9 review sorting in 2.1.0).
2. **Hooks, then price history:** P1 first, F5 on top of it, ADM2 riding the same events.
3. **Hygiene sweep:** §1 and §2 in one release, the way 1.85–1.87 cleared the earlier reviews.
4. **Admin safety net:** ADM1 trash, ADM3 dry-run, ADM4 bulk moderation.
5. **P2 keyed rows** before any further card work, so the next feature doesn't pay the positional-array tax.

---

## What the retired documents claimed that has shipped

For the record, so nothing here is re-proposed. Details are in `CHANGELOG.md`.

- All 41 items of the original v1.0 review (by 1.20.0), and every high- and
  medium-priority finding of the v1.84.2 review (1.85.0 and 1.86.0).
- All twenty bugs and the dead code from the v1.86.0 review (1.87.0),
  including the sync locks, the index migration, and the CSV slug round trip.
- **2.0 Pillar 2, individual tire pages and SEO:** crawlable `/tires/{slug}/`
  routes, server-rendered content, `Product` + `BreadcrumbList` JSON-LD,
  per-tire meta and canonical, `?tire=` consolidation, AIOSEO sitemap entries,
  slug editing with 301s.
- **2.0 Pillar 4 items:** modal focus traps (review, image, tire-page tooltip,
  and as of 1.87.0 the guide tooltip), `aria-live` result counts, `aria-busy`
  in server mode, `:focus-visible` rings, reduced-motion, smart empty states,
  adaptive price-slider ceiling, compare keyed on tire IDs and shareable.
- From the v1.48 list: share buttons on cards (native share with copy
  fallback), bulk edit as a two-step form, tire-page JSON-LD.
- **1.88.0, the advisor release and the tire page as landing page:** the
  load-index fitment warning, set-of-four pricing, price freshness, vehicle
  memory with cascade feedback, distinct empty states; tire-page compare /
  save / share / show-more-reviews, other-sizes and similar-tires links; the
  compare page's tire-page links, per-column remove and add-another-tire.
- **2.0.7 (2.0.0 through 2.0.6 consolidated), the AI Tire Advisor:** Help me choose (grounded picks with a
  rules fallback), What owners say on tire pages, the compare page's
  plain-words paragraph, the settings card, and the `ai` search events the
  analytics panels were waiting for (P5, decided: built).
- Tire Discovery (CJ catalog monitoring, qualification, review queue, link and
  price sync, coverage, health alerts, stale-price report, image import), the
  unified rate limiter, the cached `/feed`, the rotating link checker.
