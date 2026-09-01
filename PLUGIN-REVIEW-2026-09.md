# Rivian Tire Guide — Enhancement & Feature Review (v1.86.0)

**Date:** 2026-09-01
**Baseline:** v1.86.0, DB schema v22
**Scope:** consumer frontend, admin, backend/data/integrations, tests & CI.
**Method:** every item was verified against the current code with file:line references. Items fixed in 1.85.0 / 1.86.0 are not re-listed; open items from `PLUGIN-REVIEW-2026-08.md` and `PLUGIN-REVIEW.md` are summarized in §5 rather than repeated.

> **Status:** B1–B20 and the dead code listed under §1 were fixed in **1.87.0** (see `CHANGELOG.md`). Line references in §1 describe the 1.86.0 code. Sections 2–5 remain open.

---

## Overall assessment

The catalog, sync, and discovery machinery is mature and well-tested for what it does. The 1.85/1.86 releases closed every high- and medium-priority defect from the August review. What is left falls into three buckets:

1. **The product is still a catalog, not an advisor.** The data to answer "is this tire safe and sensible for *my* Rivian" (load index floors, real-world mi/kWh, OEM baseline, set pricing) is all stored but never turned into a decision on the card. That is the highest-leverage feature gap.
2. **Two features silently degrade in server-side pagination mode** (sliders, no-results suggestions), and the server-side price filter still has the $600 dead zone the client fix removed.
3. **Platform debt that blocks the roadmap**: no lifecycle hooks, positional row arrays, no price history, AIOSEO-only sitemap, zero i18n, the "AI Queries" analytics panel with no AI feature behind it.

---

## 1. Bugs found in this pass (fix first)

| # | Finding | Where |
|---|---------|-------|
| B1 | **Server-side price filter still ignores values ≥ $600.** The 1.85 fix removed the sentinel from the client, but the SQL builder still gates on `< 600`, and the AJAX default is 600. With server-side pagination on, "under $700" applies no price constraint. | `includes/class-rtg-database.php:847`, `includes/class-rtg-ajax.php:535` |
| B2 | **Sliders are double-bound in server-side mode.** `setupSliderHandlers()` always binds the client `filterAndRender`; `rivian-tires.js` also binds `serverSideFilterAndRender`. In SS mode `state.allRows` is empty, so the client handler wipes the grid and flashes "No tires match" before the fetch returns. | `frontend/js/modules/filters.js:902`, `frontend/js/rivian-tires.js:221-226` |
| B3 | **Smart no-results suggestions are inert in server-side mode.** Every suggestion action calls `filterAndRender()` directly; only "Clear all" branches on `isServerSide()`. | `frontend/js/modules/filters.js:1117-1166` vs `:984` |
| B4 | **Compare page links to a hardcoded `/rivian-tire-guide/` path** instead of `RTG_Tire_Page::guide_url()`. Breaks on any site with a different guide slug. | `frontend/templates/compare.php:420,441` |
| B5 | **Price sync flushes the whole cache once per tire.** `update_tire()` calls `flush_cache()` unconditionally inside the price-sync loop; 100 updates = 400 transient deletes and a cold cache. `update_roamer_data` already solved this pattern (flush once at end). | `includes/class-rtg-price-sync.php:369`, `includes/class-rtg-database.php:510` |
| B6 | **No overlap lock on any sync job.** Catalog (daily), Roamer (5 min), price, link sync, link checker: none take a lock. "Run Discovery Now" can run concurrently with the nightly cron, double-writing candidates and firing link/price sync twice. | `includes/class-rtg-catalog-sync.php:121`, `class-rtg-roamer-sync.php:70`, `class-rtg-ajax.php:1128` |
| B7 | **Missing indexes on the default sort columns.** `rtg_tires` has no index on `roamer_efficiency` (default sort for REST and AJAX) or `created_at` (newest sort); `rtg_ratings` has none on `review_status` though moderation filters on it. | `includes/class-rtg-activator.php:100-110,125-128` |
| B8 | **`bundle_link` cannot be set from the UI.** It is a DB column and CSV column, but `handle_tire_save()` never reads it and `tire-edit.php` has no field. CSV import is the only way in. | `includes/class-rtg-admin.php:1394,1621`, `admin/views/tire-edit.php` |
| B9 | **CSV round-trip drops `slug` and `roamer_tire_id`.** Export → re-import in update mode loses every stable public URL and every Roamer link. | `includes/class-rtg-admin.php:1390-1395` |
| B10 | **Dead admin action.** `handle_recalculate_efficiency()` and its success notice exist but nothing links to it. | `includes/class-rtg-admin.php:546,1301`, `admin/views/tire-list.php:17` |
| B11 | **Duplicate check is insert-only.** Editing an existing tire into an exact brand/model/size collision with another row is accepted silently. | `includes/class-rtg-admin.php:895` |
| B12 | **Discovery settings POST is handled inside the view with no redirect.** Refresh re-submits; every other screen uses handle_actions() + redirect. | `admin/views/tire-discovery.php:9-76` |
| B13 | **Global admin form-submit handler.** `$('form').on('submit')` reads the bulk-action select from anywhere in the document, so submitting the search form while "Delete" is selected pops the delete confirm. | `admin/js/admin-scripts.js:48` |
| B14 | **Sort/pagination links carry stale state.** `add_query_arg()` against `REQUEST_URI` keeps `message=deleted` and out-of-range `paged`. | `admin/views/tire-list.php:73,146,150`, `admin/views/reviews-list.php:160,164` |
| B15 | **Delete confirm understates the damage.** "Delete this tire?" also deletes every rating attached to it. | `admin/views/tire-list.php:237`, `includes/class-rtg-database.php:580` |
| B16 | **Menu badges run two aggregate queries on every admin screen** (`admin_menu` fires on Posts, Plugins, everywhere). | `includes/class-rtg-admin.php:293,371` |
| B17 | **Guide tooltip modal leaks its Escape listener** (removed only on the Escape path) and has no focus trap or focus return, unlike the review modal and tire-page tooltip. | `frontend/js/modules/tooltips.js:252-258` |
| B18 | **`escapeHTML` output written to `alt` / `aria-label`** double-escapes; "AT&T" is read as "AT amp semicolon T". | `frontend/js/modules/cards.js:251,289,345`, `image-modal.js:22,39` |
| B19 | **`escapeHTML`-style hygiene: `date()` in schema output** should be `gmdate()`. | `includes/class-rtg-schema.php:182` |
| B20 | **`RTG_Tire_Images` fetch proxy has no host allowlist and no rate limit** on its AJAX action; it also spoofs a Chrome UA. `wp_safe_remote_get` covers SSRF, but an admin-capability open fetch proxy deserves a retailer-host allowlist. | `includes/class-rtg-tire-images.php:71,382-420`, `includes/class-rtg-ajax.php:150` |

Dead code worth deleting while in the area: `state.cardCache` (write-only, `cards.js:210,650-655`), `search.js:15-159` (index + `fuzzyMatch` nothing reads, `hideSearchSuggestions` stub), the `#compareModal` handler in `rivian-tires.js:316`, and `search.js:217-226` (global click handler that text-matches "Clear All"). The `tire-page.js:17-34` tooltip copy duplicates `TOOLTIP_DATA`.

---

## 2. Feature enhancements: shopper-facing (highest value)

These turn stored data into a decision. Each is small because the inputs already exist in the 29-column row.

| # | Feature | Why it matters | Where it plugs in |
|---|---------|----------------|-------------------|
| F1 | **Load-index fitment warning.** When a vehicle is selected (or on the tire page), flag any tire whose `load_index` is under the floor (R1 ≥ 116, R2 ≥ 112). The tooltip explains the rule; nothing enforces it. | The single most important safety signal a Rivian owner needs, and the guide already knows the answer. | `cards.js:198-203` (row destructure), `tire-page-content.php:168`, compare row; floor constants already in discovery (`catalog_min_load_index`) |
| F2 | **Efficiency delta vs stock.** Show "+0.18 mi/kWh vs OEM ≈ +14 mi range" instead of a bare mi/kWh pill. Inputs: `roamer_efficiency`, OEM tag (`row[17]`), vehicle size map, pack kWh per model. | Translates the Roamer number into the thing owners actually care about: range. | `cards.js:468`, `compare.js` Performance section, tire page |
| F3 | **Set-of-four pricing.** "$289 ea · $1,156 / set" on card, tire page, and compare. | Nobody buys one tire; the per-tire number hides the real cost. | `cards.js:417`, `tire-page-content.php:134-141` |
| F4 | **Price freshness.** Render `price_synced_at` / `updated_at` as "price as of Aug 28" next to the price; add a "stale" hint past the `rtg_stale_price_days` threshold. | Shoppers can't tell a fresh CJ price from a year-old manual one. | same as F3; `class-rtg-stale-prices.php:26` already defines the threshold |
| F5 | **Price history + price-drop badge + alerts.** New `rtg_price_history` (tire_id, price, retailer, observed_at) written by `update_tire()` when price changes. Unlocks a sparkline on the tire page, "lowest in 90 days", a "price dropped" card badge, and the roadmap's subscribe-to-price-drop email via `RTG_Mailer`. | Price sync currently overwrites history in place; every past price is destroyed. | `class-rtg-price-sync.php:369`, `class-rtg-database.php:510`, migration 23 |
| F6 | **Tire page: compare, favorite, share, and "show more reviews".** The page a Google visitor lands on has only three CTAs and caps reviews at 10 with no way to see the rest. | Pillar 2 brought the traffic; the page can't convert it into a comparison or a saved tire. | `tire-page-content.php:50,428-438`, `tire-page.js` |
| F7 | **Tire page internal linking.** "Other sizes of this model" and "Similar tires in this size" blocks (same category + size, sorted by efficiency). | Roadmap Phase 2C; improves crawl depth and gives the visitor a next step. | `tire-page-content.php:442-505`, query via `build_filter_where_clause` |
| F8 | **Compare page: link headers to tire pages, per-column remove, "add another tire".** The `COL` map stops at 27 so it can't see `slug`. | Compare is currently a dead end. | `frontend/js/compare.js:67-74,209-230` |
| F9 | **Review sorting and filtering.** Most recent / highest / lowest / by vehicle on the tire page; `get_tire_reviews` is hardcoded `ORDER BY updated_at DESC`. | The reviews drawer was removed and nothing replaced its browsing affordances. | `class-rtg-database.php:1676`, `tire-page-content.php:50` |
| F10 | **Guest favorites.** localStorage shortlist for logged-out visitors, merged into `rtg_favorites` on login. | The heart is hidden for guests and clicking redirects to login, dropping anonymous shoppers from the funnel. | `tire-guide.php:20`, `favorites.js:31-36` |
| F11 | **Live search on the guide.** Port the working typeahead from the review page; the guide's index is built but never read. | Roadmap Pillar 4 item; the code exists in `tire-review.js:130-250`. | `search.js:164`, `tire-review.js` |
| F12 | **Vehicle memory + cascade feedback.** Persist the vehicle toggle (localStorage / user meta) and announce when a vehicle change clears the size select. | Silent size reset (`filters.js:56-61`) is the a11y item A8; persistence makes F1 and F2 work on first paint. | `filters.js:34-61` |
| F13 | **Distinct empty states.** "No tires in this size yet" vs "No tires match your filters" (currently always the latter). | Small, but the first message is an honest answer and the second is an invitation to relax filters. | `filters.js:1181` |

---

## 3. Feature enhancements: reviews → trust & community

Still open from the 2.0 roadmap Pillar 3. Listed in the order they compound.

| # | Feature | Notes |
|---|---------|-------|
| F14 | **Verified-owner badge.** "I own this tire" (user meta or a small `rtg_ownership` table) surfaces as a badge on the review and enables an "Owners say" filter. Today a guest and a three-year owner look identical (`class-rtg-database.php:1699-1706`). |
| F15 | **Multi-axis ratings.** Wet grip, snow, noise, comfort, wear. The modal already *asks* for these in prose (`ratings.js:576`) but stores one star value. Add nullable tinyint columns to `rtg_ratings`; roll up per-axis averages on the tire page and a "quietest / best in snow" sort. |
| F16 | **"Was this helpful?" votes.** Adds a sort signal for F9 and a moderation signal. |
| F17 | **Review photos.** Moderated upload through the media library, thumbnail on the review, lightbox via `image-modal.js`. |
| F18 | **Reviewer notifications.** Email the reviewer when a reply/approval lands is done; add "your review got N helpful votes" and "someone answered your question" once F16 and Q&A exist. |

---

## 4. Admin & operator enhancements

| # | Feature | Where |
|---|---------|-------|
| A1 | **Trash / undo for tires.** `delete_tire()` hard-deletes the row and all ratings. Add `deleted_at`, a Trash tab, restore, and a 30-day purge. | `class-rtg-database.php:574-585` |
| A2 | **Audit log.** `rtg_audit` (actor, entity, field diffs, source: ui / csv / sync / cron). Every handler writes without recording the user; sync jobs rewrite prices with no trace. Pairs with F5. | `class-rtg-admin.php:783,955,1020,1088` |
| A3 | **CSV import dry-run.** Parse, validate, and show a per-row insert / update / skip / error table before writing. Still not implemented. | `class-rtg-admin.php:1429-1531` |
| A4 | **Bulk review moderation.** Checkbox + Approve / Reject / Delete selected, same pattern as tire bulk actions. Still one-at-a-time. | `admin/views/reviews-list.php` |
| A5 | **Export reviews and analytics to CSV.** `fputcsv` exists only for tires; analytics and reviews are screen-only. | `admin/views/analytics.php`, `reviews-list.php` |
| A6 | **WP-CLI commands.** `wp rtg sync catalog|roamer|prices|links`, `wp rtg import <file> [--dry-run]`, `wp rtg recalc-efficiency`, `wp rtg cleanup-analytics`. Removes the browser-timeout budget dance and the 2 MB CSV cap for large operations. | new `includes/class-rtg-cli.php` |
| A7 | **Capability split.** `manage_options` is hard-coded in ~25 places. `rtg_manage_tires` / `rtg_moderate_reviews` / `rtg_view_analytics` let an editor moderate reviews without site-admin rights. | `class-rtg-admin.php:13` |
| A8 | **Media library picker + bulk image upload.** Image is a bare text field with a hard-coded CDN prefix (duplicated in two files). | `tire-edit.php:403`, `class-rtg-admin.php:1381` |
| A9 | **Notification recipients + test email + sent log.** Nine mailer paths hard-code `admin_email`. Add a recipient list, digest-vs-immediate per event, a "Send test" button, and an in-admin log of what went out. | `class-rtg-mailer.php:80,167,276,398,511,769,810,844,893` |
| A10 | **Cron job registry.** One admin card listing all five hooks with last run, duration, next run, failure count, "run now", and a shared lock (fixes B6). `RTG_Health` probes only the catalog sync. | `rivian-tire-guide.php:75-99`, `class-rtg-health.php` |
| A11 | **Settings that are missing or hard-coded.** No input for `tire_page_slug` (read by `class-rtg-tire-page.php:35`, absent from `settings.php`). Hard-coded: image CDN prefix, admin page size 20, CSV cap 2 MB, link-check batch 50 / 15 s, stale-price 90 d, health staleness 36 h. | `settings.php`, `tire-list.php:40`, `class-rtg-link-checker.php:32,38`, `class-rtg-stale-prices.php:26`, `class-rtg-health.php:43` |
| A12 | **Dashboard insights.** Discovery funnel (new → imported, time to decision), review velocity per week, click-through rate per tire (needs an impression event to give clicks a denominator), price-change feed (needs F5). | `dashboard.php`, `analytics.php:377` |
| A13 | **Near-duplicate report.** Scan the *existing* catalog for brand/model/size near-collisions using `RTG_Coverage::model_similarity()`. Duplicates are only blocked at insert. | `class-rtg-coverage.php:293` |
| A14 | **Bundle Chart.js locally.** Loaded from jsDelivr with no SRI and no fallback; analytics renders blank under a strict CSP or offline. | `class-rtg-admin.php:457` |
| A15 | **Wheel list and affiliate-links pagination/search.** Both load the whole table with no search, sort, or pagination. | `wheel-list.php:14`, `affiliate-links.php:31,277` |
| A16 | **Stop full-page reloads after admin AJAX.** Roamer and discovery actions `location.reload()` on success and `alert()` on failure, losing filters and scroll. | `rtg-roamer.js:34,83,264,321`, `rtg-discovery.js:51,195` |
| A17 | **Inline validation on the tire form.** Only brand/model are `required`; size format, load index vs max load, slug uniqueness are server-only and a failed save (outside the duplicate path) loses the form. | `tire-edit.php:204,215` |

---

## 5. Platform & architecture

| # | Item | Status / rationale |
|---|------|--------------------|
| P1 | **Lifecycle hooks.** Zero `do_action()` in the plugin. Add `rtg_tire_created/updated/deleted`, `rtg_review_submitted/approved`, `rtg_price_changed`, `rtg_sync_complete`. Prerequisite for A2, F5 alerts, webhooks, and external logging. | Open since the v1.51 roadmap |
| P2 | **Keyed row objects.** Still 29-element positional arrays; `compare.js` `COL` map already stopped at 27 and lost `slug`. Emit `{tire_id, brand, ...}` from `to_frontend_row()` and migrate `cards.js`, `filters.js`, `compare.js`, `ratings.js`. REST `/tires` currently leaks the array while `/tires/{id}` returns an object. | `class-rtg-database.php:290-322`, `class-rtg-rest-api.php:292` |
| P3 | **Sitemap for core / Yoast / Rank Math.** Only `aioseo_sitemap_additional_pages` is hooked. Add `wp_sitemaps_add_provider` as the primary and Yoast/RM filters. | `class-rtg-tire-page.php:25` |
| P4 | **REST parity and new endpoints.** `/tires` supports 4 filters vs 9 on AJAX (same `build_filter_where_clause`). Add `search`, `vehicle`, `oem`, `price_max`, `warranty_min`; new `/suggest?q=`, `/compare?ids=`, `/sizes`, `/brands`, authenticated `/favorites` and `POST /reviews`. | `class-rtg-rest-api.php:243-267`, `class-rtg-ajax.php:526-545` |
| P5 | **AI Tire Advisor: build it or remove the panel.** No Claude/Anthropic code exists; `search_type='ai'` is queried but never written; the analytics page still shows "AI Queries" and "Search vs AI Usage". Either ship Pillar 1 (`POST /rtg/v1/recommend`, structured picks grounded in the live catalog, `search_type='ai'` events) or drop the dead panels and the `rtg_ai_*` uninstall lines. | `analytics.php:38,76-79`, `class-rtg-database.php:2260`, `uninstall.php:25` |
| P6 | **i18n.** Zero `__()` calls and no `load_plugin_textdomain()` despite the text-domain header. Large but mechanical; do it before more UI lands. | all PHP, `rivian-tire-guide.php:7` |
| P7 | **Cache the uncached hot paths.** `get_filter_options` (4 DISTINCT + MAX per call), the `[rivian_tire_guide]` page lookup on every tire-page and guide render, the full-table load for the sitemap filter. A thin `RTG_Cache` wrapper with versioned keys makes these one-liners. | `class-rtg-ajax.php:568-590`, `class-rtg-tire-page.php:253,272-281`, `class-rtg-frontend.php:65-74` |
| P8 | **Sync-job base class.** Six jobs hand-roll the same skeleton (enabled check, stats option, cron-vs-manual notify, mailer). An `RTG_Sync_Job` base gives lock (B6), time budget, stats, and retry/backoff on CJ 429/5xx once. | `class-rtg-catalog-source-cj.php:531` |
| P9 | **`RTG_Database` split.** 2,431 lines, ~80 static methods; stopped growing but never split. Tires / slugs / ratings / wheels / analytics / favorites / efficiency are natural seams. | `class-rtg-database.php` |
| P10 | **Uninstall completeness.** Never drops `rtg_wheels`; leaves ~11 options; never clears scheduled hooks; not multisite-aware. | `uninstall.php` |
| P11 | **Observability.** One `error_log` line in the whole plugin. Add a `do_action('rtg_log', $level, $msg, $ctx)` and a structured sync-run table. | `class-rtg-ajax.php:77` |
| P12 | **Tests & CI.** No PHPUnit coverage for `RTG_REST_API`, `RTG_Candidates` (934 lines), `RTG_Link_Checker`, `RTG_Mailer`, `RTG_Schema`, `RTG_Meta`, `RTG_Tire_Page` routing/301s, `RTG_Compare`, `RTG_Health`. `phpunit.xml` still uses PHPUnit 8 `<filter><whitelist>` on 9.6 so coverage config is ignored. PHPCS is advisory with ~380 findings and no ratchet; no phpstan. | `phpunit.xml:15-21`, `.github/workflows/ci.yml:160-164` |

---

## 6. Status of prior review items still open

From `PLUGIN-REVIEW-2026-08.md` (v1.84.2), still open at 1.86.0: L1 (locks, now B6), L2 (`sslverify => false` in the link checker, `class-rtg-link-checker.php:234`), L3 (CSV formula escaping), L8 (`rtg_settings` autoloaded with the CJ PAT), L9 (production hostname in `RTG_Tire_Images::URL_PREFIX`), L10 (uninstall, now P10), L14 (tooltip listener, now B17), L15 (dead code), L16 (double escape, now B18), L17 (`tire-review.js` has no esbuild target), L18 (PHPCS/phpstan, now P12), L19 (reviews tabs, now B14), L20 (card exit animation). Accessibility items A1–A9 are all still open; A1 (vehicle toggle uses `aria-pressed` buttons inside a `radiogroup`), A2 (compare section headers are click-only divs, `compare.js:174`), A3 (drawer has no Escape / focus return), A4 (toasts lack `role="status"`), A7 (guest privacy note not `aria-describedby`), and A8 (silent cascade, see F12) are the ones with the most user impact.

From `PLUGIN-REVIEW.md` (v1.48 roadmap, 35 items): partially delivered are 4.4 (bulk edit shipped as a two-step form, not inline) and 4.9 (tire pages now carry Product + Breadcrumb JSON-LD). Everything else is still pending; the ones this review re-prioritizes are 1.2 price history (F5), 1.3 fitment (F1), 2.1 ownership (F14), 2.7 price alerts (F5), 3.1 bulk review actions (A4), 3.2 analytics export (A5), 3.6 audit log (A2), 4.2 i18n (P6).

---

## Suggested order of attack

1. **Server-side mode parity, one release:** B1, B2, B3, B4 land in three files and can ship with a test that runs the filter suite in both modes.
2. **Sync hygiene:** B5 (flush once), B6 + A10 (lock + registry), B7 (indexes, migration 23).
3. **The "advisor" release:** F1, F2, F3, F4, F12 together. Every one is a render change over data already in the row, and together they change what the guide *is*.
4. **Tire page as landing page:** F6, F7, F8, F9 (and P3 so non-AIOSEO sites get indexed).
5. **Price history (F5) on top of P1 hooks**, then A2 audit log rides the same events.
6. **Admin safety net:** A1 trash, A3 dry-run, A4 bulk moderation, B8–B15 cleanups.
7. **Decide P5** (AI advisor or remove the panels) before the next roadmap cycle.
