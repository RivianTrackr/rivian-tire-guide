# Rivian Tire Guide — 2.0.0 Roadmap

> Status: proposal / planning. Current shipping version: **1.51.0**.
> This document captures the findings of a full-codebase review (frontend/UX,
> backend/data/AI, reviews/admin/SEO/analytics) and lays out what earns a
> major-version release, with a detailed implementation plan for the chosen
> first pillar (**Individual Tire Pages + SEO**).

---

## Positioning: what 2.0 means here

Today the plugin is a solid, well-architected **tire catalog you browse** — clean
REST API, real-world Roamer efficiency data, moderated reviews, genuine
click/search analytics, prepared statements throughout. A `2.0` (semver major)
should be a landmark, not polish. The through-line:

> **From "tire catalog you browse" → "tire advisor that guides you," with every
> tire individually discoverable on the open web.**

### Headline finding — the advertised AI feature does not exist

The workspace docs describe "AI-powered recommendations (Claude API)," but the
review found **no Claude/Anthropic integration anywhere in the code**. The only
trace is an analytics label ("Top AI Queries") and a `search_type` column in
`wp_rtg_search_events` that is never populated with `'ai'`. A major version is
the right moment to either **build the AI advisor for real** or **remove the
claim**. Building it is the stronger move (see Pillar 1).

---

## The four pillars

| # | Pillar | Why it's a 2.0 feature |
|---|--------|------------------------|
| 1 | **AI Tire Advisor** | Delivers the advertised-but-missing feature. Conversational "best winter tires for my R1T under $300" → structured picks + reasoning. Rate-limit buckets, `search_type='ai'`, and transient caching are already scaffolded. |
| 2 | **Individual Tire Pages + SEO** ⭐ *(first focus)* | Biggest organic-growth lever. Every tire is currently trapped inside one shortcode with no indexable URL. Dedicated `Product` pages + schema + sitemap unlock search traffic. |
| 3 | **Reviews → community & trust** | Turns one-off reviews into a retention engine: verified-owner badges, photos, helpful votes, multi-axis ratings, AI-assisted moderation. |
| 4 | **Accessibility + UX baseline** | The quality bar of a 2.0, and the highest-count gap area (13 a11y issues). Focus traps, focus rings, ARIA live regions, reduced-motion, live search. |

---

### Pillar 1 — AI Tire Advisor  *(highest leverage; deferred to a later phase)*

Build a real Claude-powered recommender that turns natural-language questions
into structured, explained tire picks over the live catalog.

- New endpoint, e.g. `POST /wp-json/rtg/v2/recommend` → `{ picks: [{tire_id, reasoning, confidence}], alternatives: [...] }`.
- Claude structured output (tool_use / JSON schema) grounded in the catalog + user prefs (vehicle, budget, conditions).
- Hybrid fallback to the existing filter engine for simple queries or on API failure.
- Reuse the existing per-fingerprint rate limiter (`class-rtg-ajax.php`) and transient caching keyed by normalized query + vehicle.
- Emit `search_type='ai'` events so the existing analytics "AI Queries" surfaces become real.
- API key via `wp-config.php` constant; track token cost; use the latest Claude model.

**Enables:** AI-assisted review moderation (Pillar 3), smarter Roamer match
suggestions.

---

### Pillar 2 — Individual Tire Pages + SEO  ⭐ *(detailed plan below)*

Give every tire a crawlable, server-rendered URL (`/tires/{slug}/`) with a
dedicated `Product` schema, canonical, per-tire OG/meta, breadcrumbs, and a
sitemap — replacing today's JS-only `?tire=` deep links.

See **"Detailed plan: Pillar 2"** below.

---

### Pillar 3 — Reviews → community & trust

- **Verified-owner** badge (WP user meta / attestation) — credibility today is zero; anyone can review any tire.
- **Review photos** (moderated upload) and **"Was this helpful?"** votes (adds a moderation + sort signal).
- **Multi-axis ratings**: wet grip, snow grip, noise, comfort, wear — the axes shoppers actually research, vs a single star.
- **Frontend review sorting/filtering** (Most Helpful / Recent / Highest, filter by vehicle) — the reviews drawer is an unsorted list today.
- **AI-assisted moderation**: toxicity/spam/duplicate scoring on the pending queue (pairs with Pillar 1).
- Light community surface: top reviewers, review counts, public review history.

Anchor files: `includes/class-rtg-reviews.php`, `includes/class-rtg-database.php`
(ratings CRUD ~L1070–1159, moderation ~L1687–1703), `frontend/js/modules/ratings.js`,
`admin/views/reviews-list.php`.

---

### Pillar 4 — Accessibility + UX baseline

**Accessibility (WCAG):**
- Modal focus traps + Escape + focus-return on close (review, compare, tooltip, image modals).
- Visible `:focus-visible` rings on all interactive elements (some buttons `outline:none`).
- `aria-live` on the tire count / result count, `aria-busy` during async fetch.
- `prefers-reduced-motion` must disable **all** animation (tooltip/drawer currently animate regardless).
- `aria-describedby` wiring for guest-review privacy note; verify star-rating SR semantics and hover contrast.

**UX polish:**
- Live search autocomplete/preview (search is button/Enter-only today).
- Cascade feedback when vehicle selection narrows the size dropdown (silent today).
- Adaptive price-slider bounds (hardcoded `$600` max regardless of data).
- Mobile filter-drawer keyboard management + persistent state.
- Distinguish "no tires exist" vs "filters too restrictive" in the empty state.

Anchor files: `frontend/js/rivian-tires.js`, `frontend/js/modules/{filters,ratings,search,tooltips,server}.js`,
`frontend/css/rivian-tires.css`.

---

## Supporting work (fold in; don't headline)

- **Engagement:** saved & shareable comparisons (compare state isn't shareable), saved searches, price-drop / new-tire email alerts (`class-rtg-mailer.php` already exists).
- **Admin productivity:** bulk **edit** (only bulk delete exists), CSV import dry-run/preview, insight cards ("N tires missing images/links").
- **Architecture (enables the pillars):**
  - Split the 2,251-line `RTG_Database` god class into query/service classes.
  - Add `do_action('rtg_tire_*')` / `rtg_review_*` extensibility hooks.
  - Introduce an `RTG_Cache` wrapper (optional Redis/object-cache support).
  - **Retire the fragile 28-element array row format** (`row[24]` magic indices scattered across JS) in favor of keyed objects — the single riskiest foundation for new features.

## Explicitly deferred past 2.0

GraphQL layer, A/B-testing framework, REST auth/webhooks, session replay,
retailer price-scraping (ToS/vendor risk). Real, but none load-bearing for the
2.0 narrative — they'd dilute it.

---

## Detailed plan: Pillar 2 — Individual Tire Pages + SEO

### Problem

- A tire is only reachable via the `[rivian_tire_guide]` shortcode page; the
  "detail view" is a client-side `?tire=<id>` deep link.
- `RTG_Meta` emits per-tire OG/Twitter tags for `?tire=`, but the canonical URL
  is the query-arg URL — not a clean, indexable path.
- `RTG_Schema` emits one big `ItemList` of every tire on the catalog page.
  Google strongly prefers a dedicated **`Product`** page per item for product
  rich results.
- Net effect: **zero individually rankable tire URLs.** For a tire guide, that
  is the largest missed organic-search opportunity.

### Goal

Every tire gets a stable, server-rendered, indexable URL:
`https://site/tires/{brand}-{model}-{size}/` (canonical), carrying dedicated
`Product` + `Review` + `BreadcrumbList` schema, per-tire `<title>`/meta/OG, and
a sitemap entry — while the existing catalog and `?tire=` deep links keep
working (301 → the new URL).

### Approach — mirror the existing standalone-page pattern

`RTG_Tire_Review` (`includes/class-rtg-tire-review.php`) is the template to copy:
`add_rewrite_rule` → `query_vars` → `template_redirect` (render + `exit`), a
one-shot `rtg_flush_rewrite` option to flush on activation/settings change, and
a settings-driven slug. The new work is the same shape, plus **server-rendered
HTML** (SEO requires the tire content in the initial response, not injected by JS).

### Phase 2A — Routing + server-rendered page (the core SEO win)

**New:** `includes/class-rtg-tire-page.php` (`RTG_Tire_Page`)
- Rewrite: `^{tires_slug}/([^/]+)/?$` → `index.php?rtg_tire=$matches[1]` (default slug `tires`, configurable like `tire_review_slug`).
- Resolve the path segment to a tire. Add a stable, human-readable **`slug`
  column** to `wp_rtg_tires` (migration 17) generated from brand+model+size via
  `sanitize_title`, with a uniqueness suffix; keep resolving by raw `tire_id` as
  a fallback so nothing 404s during backfill.
- On no match → proper WP 404 (`$wp_query->set_404()` + status header), never a
  soft-200.
- Reuse the `RTG_Tire_Review` security-header block (X-Content-Type-Options,
  X-Frame-Options, CSP, Referrer-Policy).

**New:** `frontend/templates/tire-page.php` — server-rendered
- Full `<h1>` (brand model size), specs table, price/offer, category, warranty,
  Roamer real-world efficiency, and existing user reviews **in the initial HTML**.
- Progressive enhancement: hydrate ratings/compare/favorite via the existing
  modules, but content must be present without JS.
- Canonical `<link rel="canonical">` → the `/tires/{slug}/` URL.
- Breadcrumb UI: Home → Tire Guide → {Category or Size} → {Tire}.
- "Back to guide" + "Compare" + affiliate CTA (reuse click tracking).

**Modify:** `frontend/js/modules/cards.js` — point each card's title/CTA at the
new `/tires/{slug}/` permalink (progressive: real `<a href>` so crawlers follow
it), instead of only the `?tire=` deep link.

### Phase 2B — Structured data + meta per tire

**New:** `RTG_Schema::output_single_product( $tire )` (or a small
`RTG_Tire_Page` method) emitting a **single `Product`** node (not `ItemList`)
with `offers`, `aggregateRating`, up to N `review`s, and `additionalProperty`
specs — the builder logic already exists in `class-rtg-schema.php` L38–165 and
can be factored out and reused.
- Add **`BreadcrumbList`** schema matching the on-page breadcrumb.
- Add **`VideoObject`** when `review_link` points to a video (YouTube etc.) —
  the review videos are already linked, just unmarked.

**Modify:** `includes/class-rtg-meta.php`
- Generate per-tire `<title>`, meta description, canonical, and `og:image` from
  the tire's own photo for the new route (today OG only fires on the shortcode
  page + `?tire=`). Point canonical at `/tires/{slug}/`.

### Phase 2C — Sitemap + redirects + discoverability

- **Sitemap:** register tire URLs. Prefer hooking the WP core sitemap provider
  (`wp_sitemaps_add_provider`) or Yoast/Rank Math if active; fall back to a
  custom `/tire-sitemap.xml`. Include `lastmod` from `updated_at`.
- **301** legacy `?tire=<id>` (and `/tires/{raw_tire_id}/`) → the canonical
  `/tires/{slug}/` so existing shared links consolidate link equity.
- Internal linking: link category/size facets and related tires from each page
  (crawl depth + topical clustering).
- Flush rewrites once on upgrade via the existing `rtg_flush_rewrite` flag; add
  a settings field for the `tires` slug.

### Files at a glance

| Action | File |
|--------|------|
| New | `includes/class-rtg-tire-page.php` (routing, render, 404, redirects) |
| New | `frontend/templates/tire-page.php` (server-rendered detail) |
| New | `frontend/js/tire-page.js` + esbuild target (hydration only) |
| Modify | `includes/class-rtg-schema.php` (factor out single-`Product`; add Breadcrumb/Video) |
| Modify | `includes/class-rtg-meta.php` (per-tire title/canonical/OG on new route) |
| Modify | `includes/class-rtg-activator.php` (migration 17: `slug` column + backfill; `DB_VERSION` 16→17) |
| Modify | `includes/class-rtg-database.php` (slug generation, resolve-by-slug, sitemap query) |
| Modify | `frontend/js/modules/cards.js` (link cards to `/tires/{slug}/`) |
| Modify | `includes/class-rtg-admin.php` (settings: tires slug; show/edit slug in tire form) |
| Modify | main plugin file (instantiate `RTG_Tire_Page`) |
| Modify | `esbuild.config.mjs` (build `tire-page.js`) |

### Risks & watch-items

- **Rewrite-rule flushing** is a classic footgun — gate behind the one-shot flag; never flush on every request.
- **Slug collisions / renames**: enforce uniqueness; when brand/model/size changes, keep the old slug 301-ing to avoid breaking indexed URLs.
- **Thin-content risk**: a tire with no reviews and sparse specs is a weak page — consider `noindex` until it clears a minimum content threshold, and lean on Roamer data + spec richness.
- **Duplicate content**: exactly one canonical per tire; the catalog `ItemList` and the per-tire `Product` must not compete — the per-tire page owns the `Product` node.
- **Backfill correctness**: generate slugs for all existing rows in migration 17 and verify no collisions before shipping.

### Acceptance criteria

- `GET /tires/{slug}/` returns 200 with full tire content in the **initial HTML** (verify with JS disabled).
- Google Rich Results Test passes for `Product` + `Review` + `BreadcrumbList`.
- Legacy `?tire=<id>` 301s to the canonical URL; unknown slug returns a real 404.
- Tire URLs appear in the sitemap with correct `lastmod`.
- PHPCS clean; PHP 7.4/8.0/8.2 lint green; existing 83 JS tests pass; new tests for slug generation + route resolution.

---

## Appendix — review gap inventory (condensed)

Full detail lives in the review; headline counts:

- **Accessibility:** 13 issues (focus traps, focus rings, ARIA live/busy, reduced-motion, contrast).
- **Missing shopper features:** 13 (saved comparisons, size wizard, price history, availability, multi-axis reviews, alerts).
- **Frontend tech debt:** fragile array row format, monolithic `ratings.js`/`filters.js`, circular-dep hacks, inline styles in JS, DOM cache staleness.
- **Backend:** no AI layer (despite the claim), `RTG_Database` god class, no extensibility hooks, REST is public-only, no down-migrations.
- **SEO:** no individual tire pages, `ItemList`-only schema, no breadcrumb/video schema, query-arg canonicals (→ Pillar 2 addresses all of these).
