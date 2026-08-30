# Rivian Tire Guide — Full-Codebase Review (v1.84.2)

**Date:** 2026-08-30
**Scope:** backend (`includes/`), frontend (`frontend/js`, templates, CSS), admin (`admin/`), tests, build & CI.
**Method:** every finding below was verified against the actual code, with file:line references. Items already tracked in `PLUGIN-REVIEW.md` / `ROADMAP-2.0.md` are not re-listed except in the status section at the end.

## Overall assessment

The plugin is in strong shape. Prepared-statement discipline held up under spot-checking with no injectable paths found, the SSRF posture around the image importer is genuinely careful (`wp_safe_remote_get`, extension allowlisting, byte verification), and the newer sync code (catalog/CJ/price/link/presence/health) is unusually well-reasoned — budgets, cursors, pure decision functions, complete-read guards. Most of the v1.51 roadmap's headline items (individual tire pages, live search, smart empty states, focus-visible rings, reduced motion) have shipped.

The review found **10 high-priority defects**, a set of medium/low issues, and a batch of enhancement opportunities not in the existing roadmaps. The highest-value theme: **several features silently degrade in specific modes or ranges** — server-side pagination breaks compare and tire-page links, the price filter has a dead zone above $600, and the main Settings save wipes unrelated configuration.

---

## 1. High-priority bugs

### H1. Main Settings save wipes all Discovery / Roamer / CJ configuration
`includes/class-rtg-admin.php:1086-1097` — `handle_settings_save()` builds `$settings` from scratch with only 8 keys and calls `update_option( 'rtg_settings', $settings )`, replacing the whole option. But `admin/views/roamer-sync.php:14-19` and `admin/views/tire-discovery.php:9-73` store ~25 other keys in the same option (`cj_pat`, `cj_company_id`, `catalog_sync_enabled`, `catalog_min_load_index`, `price_sync_*`, `link_sync_enabled`, `health_alerts_enabled`, `roamer_sync_url`, …) — those views carefully merge; the main Settings page silently destroys all of it. Saving Settings loses CJ credentials and reverts every sync to defaults.
**Fix:** merge over `get_option( 'rtg_settings', [] )` like the other two handlers do.

### H2. CSV import "update" mode silently wipes columns absent from the file
`includes/class-rtg-admin.php:1470-1477` sets `$data[$col] = ''` for every catalog column missing from the CSV header, then `:1493-1496` passes the full array to `update_tire()`, which updates every key passed. A CSV of just `tire_id,brand,model,price` in update mode zeroes size, category, warranty, weight, links, image, and tags on every matched tire — and `:1488-1490` re-derives efficiency from the now-empty data, clobbering the stored grade.
**Fix:** in update mode, only write columns actually present in `$col_map`.

### H3. Server-side rows are missing the `slug` column the frontend reads at index 28
`includes/class-rtg-database.php:862-895` — `get_filtered_tires()` emits 28-element rows, while `get_tires_as_array()` and `get_tires_by_ids()` emit 29 with `slug` last. The frontend reads `row[28]` for tire-page links (`frontend/js/modules/ratings.js:242`, `cards.js:209/231`). With server-side pagination on, every card has `row[28] === undefined` and links to the crawlable `/tires/{slug}/` pages silently disappear — undoing the Pillar-2 SEO work in that mode. This is a live instance of the positional-array fragility the 2.0 roadmap flagged in the abstract.

### H4. Compare is completely non-functional in server-side mode
`frontend/js/modules/cards.js:264` sets `compareCheckbox.dataset.index = state.allRows.indexOf(row)`; in server-side mode `state.allRows` is `[]` (`rivian-tires.js:288`), so every checkbox gets `-1` and the handler rejects it (`modules/compare.js:54`). Checkboxes render, users tick them, nothing happens.
**Fix:** key compare state on `tire_id` instead of array position (see E-F3 below — this also makes compare URLs stable).

### H5. Back/forward navigation leaves stale filters applied (URL ↔ UI desync)
`frontend/js/modules/filters.js:1276-1359` — `applyFiltersFromURL()` only *sets* controls whose params are present; it never resets absent ones (only the three checkboxes are unconditionally reset at `:1327-1329`). Select a vehicle, press Back to the clean URL → popstate re-runs `filterAndRender()`, which reads the still-active button and keeps filtering: URL says "no filters", UI shows filtered results. Applies to search, size, brand, category, sort, and both sliders.

### H6. `?pg=` is clobbered on browser navigation — back-button history trap
`frontend/js/modules/filters.js:494-495` — `finishFilterAndRender()` resets `state.currentPage = 1` on every popstate re-render, discarding the page just restored by `applyFiltersFromURL` (`:1352-1358`). `updateURLFromFilters()` (`:1269-1272`) then sees the URL differs and **pushState**s a new entry *during popstate handling*, so pressing Back can re-add history entries.

### H7. Raised price-slider ceiling disables the price filter between $600 and the new max
`frontend/js/modules/filters.js:169` gates on `if (filters.PriceMax < 600)`, but `adaptPriceSlider()` (`:902-919`) legitimately raises the ceiling above 600. With a $750 ceiling, dragging to $650 silently applies **no** price constraint — $700 tires still show. The hardcoded 600 also survives in `getActiveFilterCount` (`:519`), analytics (`:390`), `renderSmartNoResults` (`:1085`), and `updateURLFromFilters` (`:1252` — a raised default gets written into every shared URL as `price=750`), while the chips use the real ceiling — so badge count, chips, and no-results disagree with each other.
**Fix:** replace every `600` with the slider's actual `max`.

### H8. `rows_per_page` is stored unclamped → `DivisionByZeroError` for every visitor
`includes/class-rtg-admin.php:1087` stores `intval( $_POST['rows_per_page'] ?? 12 )` with no lower clamp (the `min="4"` in `settings.php:72` is client-side only). An emptied field saves 0; `class-rtg-ajax.php:543,580` then computes `ceil( $total / $per_page )` — a fatal on PHP 8 for every server-side-pagination request. Clamp like `analytics_retention_days` is at `:1083-1084`.

### H9. `loadTireRatings()` returns promises that never settle
`frontend/js/modules/ratings.js:30-35` — all callers share one `state.ratingRequestTimeout`; a second call within the 50 ms window clears the first call's timer, orphaning its `resolve` forever. Cards keep the "No reviews" placeholder, and the sort-by-rating path (`filters.js:404-411`) awaits this promise before rendering — two quick filter changes can leave the pipeline permanently hung.

### H10. Zero test coverage on the riskiest admin code paths
`tests/test-admin.php:3` claims "CSV import/export and settings" coverage but tests only menu registration and option storage. `handle_csv_import` (bug H2 above), `handle_csv_export`, `handle_bulk_edit_save`, `handle_settings_save` (bug H1), `handle_tire_save`, and `handle_review_status` have no tests anywhere. Bugs H1 and H2 are exactly the class of regression this coverage would have caught.

---

## 2. Medium-priority bugs

- **M1. Weekly link checker only ever checks the same first 50 links.** `includes/class-rtg-link-checker.php:61-97` — `run()` iterates tires ordered `brand ASC` and breaks at `BATCH_SIZE` (50) with no cursor (unlike the CJ sweep's `rtg_cj_sweep_cursor`). On a catalog larger than 50, tires from ~"H" onward are never cron-checked, and the saved results claim "total: 50" as if complete.
- **M2. Roamer sync (every 5 min) rewrites every matched tire, defeating stale-price detection and the tire cache.** `includes/class-rtg-roamer-sync.php:259-277` sets `roamer_synced_at = $now` unconditionally, bumping `updated_at` on every matched row every 5 minutes. Consequences: (a) `RTG_Stale_Prices` (`class-rtg-stale-prices.php:54-59`) uses `updated_at` as its freshness proxy, so Roamer-matched tires can *never* be reported price-stale; (b) `update_tire()` flushes the cache, giving the 1-hour `rtg_all_tires` transient an effective 5-minute TTL site-wide; (c) N UPDATEs per run even when nothing changed. **Fix:** only write when merged data actually changed, or exclude roamer columns from the freshness heuristic.
- **M3. Cron-triggered catalog sync never raises the PHP time limit.** `class-rtg-ajax.php:1004-1021` raises it for the browser-started run, but `RTG_Catalog_Sync::run()` (`class-rtg-catalog-sync.php:121`) does not. Under a 30–60 s `max_execution_time`, the nightly run (120 s budget + link/price sync + reconcile) is killed mid-flight and records nothing — the exact failure `RTG_Health` exists to diagnose, self-inflicted.
- **M4. Compare page's raw-HTML heuristic can bypass escaping.** `frontend/js/compare.js:148` — any spec value beginning with `<` is injected into `innerHTML` unescaped, and the localized rows skip the guide's sanitize pass (`:303`). Admin-entered stored XSS; low likelihood, but it defeats the escaping architecture.
- **M5. The two URL-allowlist copies have diverged.** `frontend/js/modules/validation.js:122-132` allows `sumitomotire.com`, `nexentire.com`, `autozone.com`, `pepboys.com`, `ntb.com`, which `rtg-shared.js` lacks; `rtg-shared.js:24-36,58` allows `evsportline.com`, `tsportline.com`, `cdn.riviantrackr.com`, and `http:` images, which validation lacks. A link that renders on the guide can silently vanish on the compare page. (Same failure mode as the AVIF bug fixed in 1.83.2 — build both from one exported list.)
- **M6. Discovery page uses `$status_filter` before it's assigned; vehicle counts are always empty.** `admin/views/tire-discovery.php:129` calls `get_vehicle_counts( $status_filter )` but the variable is first set at `:158` — PHP 8 warning, and `WHERE status = ''` matches nothing, so the "(N)" counts in the Vehicle dropdown never render.
- **M7. Export is advertised as a re-importable backup but drops `model_aliases` and `bundle_link`.** `class-rtg-admin.php:1348-1353`; `import-export.php:104` says "can be re-imported or used as a backup." A delete-and-restore loses aliases (which drive discovery matching/pricing/delisting) with no warning.
- **M8. A duplicate-blocked save throws away almost everything the admin typed.** `class-rtg-admin.php:894-911` redirects with only brand/model/size; `tire-edit.php:89-96` restores only those three — price, links, specs, tags are gone. The `duplicate_id` path (`:885-888`) restores nothing at all.
- **M9. Committed minified assets have no drift guard in CI.** `.github/workflows/ci.yml:33-34` runs `npm run build` only to prove it succeeds; no `git diff --exit-code` after, so a PR editing source without rebuilding ships a stale `.min.js` silently. Currently in sync (verified by rebuilding) — the guard is one added line.
- **M10. `build.sh` is a second, divergent build pipeline with a destructive fallback.** It builds only 3 of esbuild's 8 targets (no `admin-scripts.min.js`, so admin JS 404s on a build.sh-only checkout given `class-rtg-admin.php:406-408` keys the `.min` suffix off the CSS file), and its no-tooling fallback (`:41-49`) strips `//.*$`, corrupting any line containing `https://` in a string. Replace its body with `npm ci && npm run build` or delete it.
- **M11. Public REST rate limiting is bypassable and shared-IP hostile.** `class-rtg-rest-api.php:442-460` keys on raw `REMOTE_ADDR` with non-atomic transients; behind a proxy/CDN that doesn't rewrite it, the whole site throttles as one client — and `/feed` (full catalog + ratings JOIN, `__return_true`, CORS `*`) is the expensive endpoint behind it. Cache the `/feed` payload in a transient and consider a trusted-proxy setting.
- **M12. Discovery admin page does heavy writes on every GET.** `admin/views/tire-discovery.php:88` runs `reconcile_with_guide()` (all candidates + per-row UPDATEs) on every render, racing the nightly sync's own `refresh_matches`. Throttle via transient (like `RTG_Health::admin_probe`) or move behind an explicit refresh action.
- **M13. The fuzzy-match table is recomputed up to three times per run/page view.** `class-rtg-candidates.php:374-436` runs `variant_match` (string similarity against every same-brand/size guide entry) per candidate; `RTG_Link_Sync::run()` (`class-rtg-link-sync.php:291`) and `RTG_Price_Sync::run()` (`class-rtg-price-sync.php:346`) each call it back-to-back with identical inputs, and the discovery page adds a third pass. At the ~16k candidate rows the code's comments mention, that's tens of millions of comparisons per sync. Memoize per request/run.
- **M14. Discovery queue truncates at 200 rows with no pagination and no indicator.** `tire-discovery.php:171-176` + `class-rtg-candidates.php:219,247` — a tab badge can say "Dismissed (900)" while the table silently lists 200.
- **M15. PHP version coverage gap in CI.** PHPUnit runs (with real MySQL — good) but only on PHP 8.2 (`ci.yml:36-86`); 7.4/8.0 get syntax lint only, so 7.4-incompatible *behavior* would ship despite the `Requires PHP: 7.4` header.

---

## 3. Low-priority / hygiene

- **L1. No lock around sync runs** — cron + "run now" (or an overrunning 5-min Roamer tick) execute concurrently: duplicate CJ quota spend, last-writer-wins stats, both advancing the sweep cursor. A transient lock with TTL = run budget suffices. Same story for `maybe_upgrade()` migrations racing on `plugins_loaded` after an update (`class-rtg-activator.php:24-30`).
- **L2. `check_single_link` disables TLS verification** (`class-rtg-link-checker.php:212`, `sslverify => false`) — unneeded; the CJ source and Roamer sync verify correctly.
- **L3. CSV export formula injection** — `class-rtg-admin.php:1372-1381` writes raw values (including feed-derived `link`) with no `=`/`+`/`-`/`@` prefix escaping; a crafted feed value executes when the export opens in Excel.
- **L4. Manual Roamer runs email despite the "manual runs don't email" design** — `class-rtg-roamer-sync.php:292` gates on `$notify_enabled` alone where failures correctly use `$is_cron && $notify_enabled` (`:86-88`).
- **L5. Rating writes don't invalidate the dashboard cache despite the comment claiming so** — `class-rtg-database.php:1084` vs. `set_rating`/`delete_rating`/`update_review_status`, none of which call `flush_cache()`.
- **L6. `SHOW COLUMNS` runs before every 5-minute sync** — `class-rtg-roamer-sync.php:498-506` probes the schema 288×/day guarding against a dbDelta failure migrations 13/14 already patched.
- **L7. Missing index for the search-analytics dedup probe** — `insert_search_event` filters on `session_hash` (`class-rtg-database.php:2165-2170`) but `rtg_search_events` has no index on it (`class-rtg-activator.php:156-169`); the click table got `idx_session_date`, the search table didn't.
- **L8. `rtg_settings` (containing the CJ PAT and `cj_query`) is autoloaded** — pass `false` as the autoload flag.
- **L9. Hardcoded production hostname in `RTG_Tire_Images::URL_PREFIX`** (`class-rtg-tire-images.php:35`) — staging/dev clones import locally but store production URLs. Derive from `home_url()`.
- **L10. uninstall.php is incomplete** — drops six tables but not `rtg_wheels`, and leaves ~10 options behind (`rtg_slug_redirects` up to 500 entries, `rtg_roamer_sync_stats` — large, `rtg_health_state`, sweep cursor, sync results…).
- **L11. Emptying a Dropdown Options textarea silently reverts to shipped defaults** instead of clearing (`class-rtg-admin.php:1100-1108`, `:622-628`).
- **L12. Analytics page has no guard for Chart.js failing to load** from the CDN (`analytics.php:254/310`) — panels stay "Loading…" forever.
- **L13. Share-image canvas issues** — category pills measured with the 18px heading font still active (`admin/js/admin-scripts.js:302` vs `:311`) so pills render ~40% too wide; hardcoded theme colors ignore the customized palette (`:131-147`).
- **L14. Guide tooltip modal leaks a document keydown listener per open** (`frontend/js/modules/tooltips.js:252-258` — removed only on Escape, not on the common close paths) and lacks the focus trap/return its tire-page twin already has (`tire-page.js:39-110`).
- **L15. Dead code retaining memory** — `state.cardCache` is written but never read, and its key includes `Date.now() % 10000` so it could never hit (`cards.js:217,654-659`); the search index + Levenshtein `fuzzyMatch` (~135 lines, `search.js:15-159`) is built at init but nothing reads it since suggestions were removed; `rivian-tires.js:314-321` handles a `#compareModal` that no longer exists in any template.
- **L16. Double-escaped text in plain-text sinks** — `escapeHTML()` output assigned to `aria-label`/`alt` announces "AT&amp;T" (`cards.js:258,293,349`, `image-modal.js:22,39`).
- **L17. `frontend/js/tire-review.js` has no esbuild target** — the only asset served permanently unminified (`class-rtg-tire-review.php:57-59`).
- **L18. PHPCS runs `continue-on-error` with ~380 acknowledged findings and the phpunit.xml coverage block uses dead PHPUnit-8 syntax** (`ci.yml:148-150`, `phpunit.xml:16-21`). A ratcheting baseline would stop new findings; no phpstan/psalm exists (either would have caught M6's undefined variable).
- **L19. Reviews status tabs carry stale `paged` and drop the search term** (`reviews-list.php:126`), landing on empty pages.
- **L20. Card exit animation cut short** — removal timeout uses `animationDelay` (100/150 ms) while the transition runs 200/300 ms (`cards.js:121-129`).

---

## 4. Accessibility gaps (beyond what shipped)

Shipped and verified: focus-visible rings, aria-live result counts, aria-busy in server mode, reduced-motion (CSS + JS), review/image modal focus traps, smart empty states. Still open:

- **A1.** Vehicle toggle: `role="radiogroup"` containing plain buttons with `aria-pressed` — should be `role="radio"`/`aria-checked` + arrow keys (`tire-guide.php:44-45`, `filters.js:34,44`).
- **A2.** Compare page section headers are click-only `<div>`s — no button, no `tabindex`, no `aria-expanded` (`compare.js:157`).
- **A3.** Mobile filter drawer: no Escape-to-close, no focus return to `#toggleFilters` (`rivian-tires.js:404-427`).
- **A4.** Toasts in `ratings.js:771-801` and `tire-review.js:554-586` have no `role="status"` (the favorites toast does it right, `favorites.js:110`).
- **A5.** Tire-review search dropdown lacks combobox semantics (`tire-review.js:153-257`).
- **A6.** Per-review stars on tire pages are `aria-hidden` with no text alternative (`tire-page-content.php:487`).
- **A7.** Guest-review privacy note still not associated via `aria-describedby` (`ratings.js:516-518`, `tire-review.php:654`).
- **A8.** Silent side effects: vehicle→size cascade clears the size with no announcement; the 4-tire compare cap silently un-checks the 5th box.
- **A9.** `filterResultCount` and `tireCount` are two live regions announcing the same number — double SR announcements (`tire-guide.php:102,117`).

---

## 5. Enhancement opportunities (new — not in existing roadmaps)

### Backend
- **E-B1. Extract a shared sync-job harness.** `RTG_Catalog_Sync`, `RTG_Roamer_Sync`, `RTG_Price_Sync`, `RTG_Link_Sync`, `RTG_Link_Checker`, `RTG_Stale_Prices` each hand-roll the same skeleton: kill-switch, stats option, cron-vs-manual notify gating, mailer call. A small `RTG_Sync_Job` base (enabled-check, lock, time budget, stats persistence, notify policy) fixes M3, L1, and L4 once, structurally, and every future source inherits it.
- **E-B2. One rate limiter.** Four bespoke throttles exist (AJAX object-cache-aware, REST transient-only, mailer failure throttle, health probe). One utility with atomic increment and configurable keying also lets REST inherit the better fingerprinting (M11).
- **E-B3. Move Roamer ambiguous/unmatched state out of the stats blob.** `roamer_assign` (`class-rtg-ajax.php:1323-1399`) edits the *last run's* `STATS_OPTION` snapshot, which the next 5-minute run overwrites wholesale — hide/assign race the cron. A small table (or the candidates pattern) makes it race-free and shrinks the option.
- **E-B4. Cache the shortcode-page lookup.** `class-rtg-tire-page.php:272-281` and `class-rtg-frontend.php:65-71` run an uncached `LIKE '%[rivian_tire_guide]%'` post search per tire-page view. Cache the resolved page ID in an option invalidated on `save_post`.

### Frontend
- **E-F1. Key compare on `tire_id`, not array position.** `modules/compare.js:32` builds `?compare=0,3,7` from positions — a shared compare URL silently shows *different tires* after any catalog insert. Switching to IDs fixes H4 for free, makes compare links stable/shareable, and closes the missing 4-item cap on the URL path.
- **E-F2. Link the compare page to the individual tire pages.** `compare.js:183-204` renders brand/model as plain text; the slug is right there in the row. Easy internal-linking/SEO win.
- **E-F3. Tire-page reviews: "show more".** `tire-page-content.php:50` caps at 10; the heading shows the real count ("Owner Reviews (23)") but there's no way to see the rest. `tire-page.js` does almost no hydration — a small fetch-more is cheap.
- **E-F4. Unify the pagination renderers.** The server-mode pagination (`server.js:174-199`) duplicates the client version but without its `role="navigation"`, aria-labels, and `aria-live` page info (`filters.js:295-333`).
- **E-F5. Retire the remaining inline-style JS.** `styleButton` (`helpers.js:88-98`) does `getComputedStyle` reads per button per render and sets `onmouseover` handlers; tooltips write ~40-line `cssText` blobs — all expressible as existing design-system classes.
- **E-F6. Trim `updateDropdownCounts()`.** Three extra full filter passes per render (`filters.js:580-608`), each rebuilding the all-indexes Set via spread+filter — fine at ~100 tires, quadratic-ish as the catalog grows.

### Admin
- **E-A1. Dashboard "Missing Images" should link to a filtered list.** `dashboard.php:308-312` links to the unfiltered tire list; no missing-image filter exists on `tire-list.php` (contrast: affiliate links has `link_filter=missing`, which the dashboard uses for links).
- **E-A2. Round out the export/import loop.** Include `model_aliases`, `bundle_link`, and `slug` in the CSV (M7), add an import dry-run (already roadmapped), and formula-escape on export (L3).

### Testing / CI
- **E-T1. Test the admin action handlers** (H10) and the admin AJAX endpoints (`candidate_bulk`, `candidate_set_status`, `roamer_assign` — the most intricate handler in the file — plus capability/nonce rejection paths).
- **E-T2. Add tests for the slug lifecycle** (`sync_tire_slug` collision suffixing, redirect-map capping), REST feed, `RTG_Tire_Page` routing, `RTG_Link_Checker`, `RTG_Mailer`.
- **E-T3. CI additions:** minified-asset drift guard (`git diff --exit-code` after build — one line, M9), PHPUnit on 7.4/8.0 (M15), phpstan at a modest level, a PHPCS ratcheting baseline, and fix the dead PHPUnit-8 coverage config.

---

## 6. Status of previously flagged architecture items (ROADMAP-2.0 @ v1.51)

| Item | Status at v1.84.2 |
|---|---|
| `RTG_Database` god class | **Unresolved** — 2,429 lines; stopped growing (new features correctly built as separate classes) but not split. |
| Extensibility hooks | **Mostly unresolved** — three `apply_filters`, zero `do_action`; no CRUD lifecycle hooks. |
| REST public-read-only | **Unchanged by design** — all routes `__return_true`, none write. |
| Positional 28/29-element row format | **Unresolved and now actively biting** (H3). `compare.js` has a named `COL` map and `cards.js` destructures, but `filters.js` still uses bare `a[24]`/`row[17]`, and compare URLs ride raw indexes. Retiring this remains the single highest-leverage refactor. |
| Down-migrations | **Unresolved** — forward-only; the m15→m16 episode was handled with a compensating forward migration. |
| Bulk review moderation / CSV dry-run / analytics export | **All still open** (per-row links only; no dry-run; screen-only analytics). |

---

## Suggested order of attack

1. **Config-loss + crash fixes (small, high-impact):** H1, H2, H8, M6 — four contained PHP fixes.
2. **Server-side mode parity:** H3 (add `slug` to `get_filtered_tires`), H4/E-F1 (compare by ID).
3. **Filter-state cluster:** H5 + H6 + H7 land in the same file and should ship together with tests.
4. **CI guards:** E-T3's drift guard + phpstan (would have caught M6), then H10's handler tests.
5. **Sync hygiene:** M1 (cursor), M2 (conditional Roamer writes), M3 (time limit), then E-B1 as the structural cleanup.
6. **A11y pass:** A1–A9 — each is a contained, verified change.
