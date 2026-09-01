# Rivian Tire Guide — Roadmap

**Current release:** 1.87.0 (DB schema v23)
**Updated:** 2026-09-01

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
| A11Y4 | Toasts in the ratings module and on the tire-review page lack `role="status"` (the favorites toast does it right). | `frontend/js/modules/ratings.js`, `frontend/js/tire-review.js` |
| A11Y5 | Tire-review page search dropdown lacks combobox semantics. | `frontend/js/tire-review.js` |
| A11Y6 | Per-review stars on tire pages are `aria-hidden` with no text alternative. | `frontend/templates/tire-page-content.php` |
| A11Y7 | The guest-review privacy note is not associated with the email field via `aria-describedby`. | `frontend/js/modules/ratings.js` (guest fields), `frontend/templates/tire-review.php` |
| A11Y8 | Silent side effects: the vehicle→size cascade clears the size with no announcement, and the four-tire compare cap silently un-checks the fifth box. (Pairs with F12.) | `frontend/js/modules/filters.js`, `frontend/js/modules/compare.js` |
| A11Y9 | `filterResultCount` and `tireCount` are two live regions announcing the same number. | `frontend/templates/tire-guide.php` |

## 3. Shopper-facing features

These turn data the plugin already stores into a decision on the card. They
are what moves the guide from "a catalog you browse" to "an advisor that
guides you", the through-line of the 2.0 plan. Each is small because the
inputs already exist in the 29-column row.

| ID | Feature | Why it matters | Where it plugs in |
|----|---------|----------------|-------------------|
| F1 | **Load-index fitment warning.** With a vehicle selected (or on the tire page), flag any tire whose load index is under the floor (R1 ≥ 116, R2 ≥ 112). The tooltip explains the rule; nothing enforces it. Persist the vehicle choice (F12) so it works on first paint. | The most important safety signal a Rivian owner needs, and the guide already knows the answer. Floors already live in the discovery settings (`catalog_vehicle_min_load_index`). | `frontend/js/modules/cards.js`, `frontend/templates/tire-page-content.php`, compare rows |
| F2 | **Efficiency delta vs stock.** Show "+0.18 mi/kWh vs OEM ≈ +14 mi range" instead of a bare mi/kWh pill. Inputs: `roamer_efficiency`, the OEM tag, the vehicle size map, pack kWh per model. | Translates the Roamer number into the thing owners care about: range. | `cards.js` (Roamer pill), `compare.js` Performance section, tire page |
| F3 | **Set-of-four pricing.** "$289 ea · $1,156 / set" on card, tire page and compare. | Nobody buys one tire. | `cards.js`, `tire-page-content.php` |
| F4 | **Price freshness.** Render `price_synced_at` / `updated_at` as "price as of Aug 28"; hint when past the `rtg_stale_price_days` threshold. | Shoppers can't tell a fresh CJ price from a year-old manual one. | same as F3; threshold in `class-rtg-stale-prices.php` |
| F5 | **Price history, price-drop badge, price-drop alerts.** New `rtg_price_history` (tire_id, price, retailer, observed_at) written by `update_tire()` on a price change. Unlocks a sparkline on the tire page, "lowest in 90 days", a "price dropped" card badge, and a subscribe-to-drop email via `RTG_Mailer`. Depends on P1 hooks. | Price sync overwrites history in place today; every past price is destroyed. | `class-rtg-price-sync.php`, `class-rtg-database.php`, migration 24 |
| F6 | **Tire page: compare, favorite, share, and show-more reviews.** The page a Google visitor lands on has three CTAs and caps reviews at 10 with no way to see the rest. | Pillar 2 brought the traffic; the page can't convert it into a comparison or a saved tire. | `tire-page-content.php`, `tire-page.js` |
| F7 | **Tire page internal linking.** "Other sizes of this model" and "Similar tires in this size" blocks (same category + size, by efficiency). | Improves crawl depth and gives the visitor a next step. | `tire-page-content.php`, via `build_filter_where_clause` |
| F8 | **Compare page: link headers to tire pages, per-column remove, "add another tire".** The `COL` map stops at 27 so it can't see `slug`. | Compare is a dead end today. | `frontend/js/compare.js` |
| F9 | **Review sorting and filtering** on the tire page: recent / highest / lowest / by vehicle. `get_tire_reviews` is hardcoded `ORDER BY updated_at DESC`. | The reviews drawer was removed and nothing replaced its browsing affordances. | `class-rtg-database.php`, `tire-page-content.php` |
| F10 | **Guest favorites.** localStorage shortlist for logged-out visitors, merged into `rtg_favorites` on login. | The heart is hidden for guests and clicking redirects to login, dropping anonymous shoppers. | `tire-guide.php`, `favorites.js` |
| F11 | **Live search on the guide.** Port the working typeahead from the tire-review page. | The review page already has it (`tire-review.js`); the guide is button/Enter-only. | `frontend/js/modules/search.js` |
| F12 | **Vehicle memory and cascade feedback.** Persist the vehicle toggle (localStorage or user meta) and announce when a vehicle change clears the size select. | Closes A11Y8's first half and makes F1/F2 work on first paint. | `filters.js` |
| F13 | **Distinct empty states.** "No tires in this size yet" vs "No tires match your filters" (currently always the latter). | The first is an honest answer; the second is an invitation to relax filters. | `filters.js` (`renderSmartNoResults`) |
| F14 | **Adaptive UX leftovers from the 2.0 plan.** Live search preview (F11), saved and shareable searches, seasonal callouts using the existing category field. | — | — |

## 4. Reviews → trust and community

The 2.0 plan's Pillar 3, still entirely open. Listed in the order they compound.

| ID | Feature | Notes |
|----|---------|-------|
| R1 | **Verified-owner badge.** "I own this tire" (user meta or a small `rtg_ownership` table with purchase date, price, vehicle) surfaces as a badge on the review and enables an "Owners say" filter. A guest and a three-year owner look identical today. |
| R2 | **Multi-axis ratings.** Wet grip, snow, noise, comfort, wear. The review modal already *asks* for these in prose but stores one star value. Nullable tinyint columns on `rtg_ratings`; per-axis averages on the tire page; "quietest / best in snow" sorts. |
| R3 | **"Was this helpful?" votes.** A sort signal for F9 and a moderation signal. |
| R4 | **Review photos.** Moderated upload through the media library, thumbnail on the review, lightbox via `image-modal.js`. Video links (YouTube/Vimeo via `wp_oembed_get()`) fit the same column family. |
| R5 | **Tire wear / mileage logging** for owned tires (depends on R1): periodic tread-depth entries, a wear chart, projected life vs the mileage warranty, anonymous aggregate "average owner gets X miles". |
| R6 | **Community Q&A** on tire pages: questions, answers, upvotes, accepted answers, a moderation tab, email on new answers. |
| R7 | **Public profile page** (`[rtg_user_profile]`): reviews, favorites, owned tires; link from review attribution. |
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
| P2 | **Keyed row objects.** Still 29-element positional arrays; `compare.js`'s `COL` map already stopped at 27 and lost `slug`. Emit `{tire_id, brand, ...}` from `to_frontend_row()` and migrate `cards.js`, `filters.js`, `compare.js`, `ratings.js`. REST `/tires` leaks the array while `/tires/{id}` returns an object. | The single highest-leverage refactor; every new card feature above pays its tax |
| P3 | **Sitemap for core / Yoast / Rank Math.** Only the AIOSEO filter is hooked; add `wp_sitemaps_add_provider` as the primary plus the Yoast and Rank Math filters. | `class-rtg-tire-page.php` |
| P4 | **REST parity and new endpoints.** `/tires` supports 4 filters vs 9 on AJAX (same `build_filter_where_clause`). Add `search`, `vehicle`, `oem`, `price_max`, `warranty_min`; new `/suggest?q=`, `/compare?ids=`, `/sizes`, `/brands`, authenticated `/favorites` and `POST /reviews`. | `class-rtg-rest-api.php` |
| P5 | **AI Tire Advisor: build it or remove the panel.** No Claude/Anthropic code exists; `search_type='ai'` is queried but never written; the Analytics page still shows "AI Queries" and "Search vs AI Usage"; `uninstall.php` sweeps legacy `rtg_ai_*` options. Either ship it (`POST /rtg/v1/recommend`, structured picks grounded in the live catalog with a filter-engine fallback, `search_type='ai'` events, key via a `wp-config.php` constant, the existing rate limiter and transient cache) or drop the dead panels. | Decide before the next roadmap cycle |
| P6 | **i18n.** Zero `__()` calls and no `load_plugin_textdomain()` despite the text-domain header. Large but mechanical; do it before more UI lands. | all PHP, `frontend/js` strings via `wp_localize_script` |
| P7 | **Cache the uncached hot paths.** `get_filter_options` (4 DISTINCT + MAX per call), the `[rivian_tire_guide]` page lookup on every tire-page, guide, compare and admin-list render, the full-table load for the sitemap filter. A thin `RTG_Cache` wrapper with versioned keys makes these one-liners and gives an object-cache story. | `class-rtg-ajax.php`, `class-rtg-tire-page.php` (`guide_url`), `class-rtg-frontend.php` |
| P8 | **Sync-job base class.** Six jobs hand-roll the same skeleton (enabled check, lock, stats option, cron-vs-manual notify, mailer). An `RTG_Sync_Job` base gives time budget, stats, and retry/backoff on CJ 429/5xx once; H3 and H5 fall out of it. | sync classes, `class-rtg-catalog-source-cj.php` |
| P9 | **`RTG_Database` split.** ~2,500 lines, ~80 static methods; stopped growing but never split. Tires / slugs / ratings / wheels / analytics / favorites / efficiency are natural seams. | `class-rtg-database.php` |
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

---

## Suggested order of attack

1. **The advisor release:** F1, F2, F3, F4, F12 together. Every one is a render change over data already in the row, and together they change what the guide is.
2. **Tire page as landing page:** F6, F7, F8, F9, and P3 so non-AIOSEO sites get indexed.
3. **Hooks, then price history:** P1 first, F5 on top of it, ADM2 riding the same events.
4. **Hygiene sweep:** §1 and §2 in one release, the way 1.85–1.87 cleared the earlier reviews.
5. **Admin safety net:** ADM1 trash, ADM3 dry-run, ADM4 bulk moderation.
6. **Decide P5** (build the AI advisor or remove the panels).
7. **P2 keyed rows** before any further card work, so the next feature doesn't pay the positional-array tax.

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
- Tire Discovery (CJ catalog monitoring, qualification, review queue, link and
  price sync, coverage, health alerts, stale-price report, image import), the
  unified rate limiter, the cached `/feed`, the rotating link checker.
