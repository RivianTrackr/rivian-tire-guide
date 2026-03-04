# Rivian Tire Guide — Enhancement Roadmap

**Plugin:** Rivian Tire Guide v1.24.3
**Updated:** 2026-03-04
**Scope:** 35 planned enhancements across catalog, user features, admin tooling, and technical/SEO improvements

> All 41 original review items (security, performance, code quality, UX, testing) were completed as of v1.20.0. This document tracks the **next phase** of development.

---

## Table of Contents

1. [Catalog & Data](#1-catalog--data)
2. [User Features](#2-user-features)
3. [Admin & Backend](#3-admin--backend)
4. [Technical & SEO](#4-technical--seo)
5. [Priority Matrix](#priority-matrix)

---

## 1. Catalog & Data

### 1.1 — Dark mode toggle for the frontend UI
**Priority:** Medium
**Status:** Pending
**Scope:** Add a theme toggle (light/dark) to the tire guide frontend. The plugin already injects CSS custom properties via `:root{}` in `class-rtg-frontend.php` — extend the existing `sanitize_hex_color()` pipeline with a dark palette. Store preference in `localStorage`. Add a toggle button to the top bar. Update all card, filter, modal, and comparison styles to reference CSS variables instead of hard-coded colors.
**Files:** `class-rtg-frontend.php`, `frontend/css/rivian-tires.css`, `frontend/js/modules/state.js`

### 1.2 — Price history tracking with trend graphs
**Priority:** Medium
**Status:** Pending
**Scope:** New `wp_rtg_price_history` table with columns `tire_id`, `price`, `recorded_at`. Record a snapshot on every tire update via `RTG_Database::update_tire()`. Add a price chart (Chart.js is already loaded for Analytics) to the tire detail/review drawer showing 30/60/90-day trends. Include a "price dropped" badge on tire cards when current price is below the 30-day average.
**Files:** `class-rtg-activator.php` (migration 12), `class-rtg-database.php`, `frontend/js/modules/cards.js`

### 1.3 — Tire fitment validator (Rivian year/model/trim specific)
**Priority:** High
**Status:** Pending
**Scope:** Create a fitment lookup that maps Rivian models (R1T, R1S, R2, R3, R3X) with year and trim to compatible tire sizes, load ratings, and bolt patterns. The `wp_rtg_wheels` table already has `vehicles` and `stock_size`/`alt_sizes` columns — extend this data or add a new `wp_rtg_fitments` table. Surface a "Check Fitment" modal on tire cards that warns if a tire size doesn't match the user's selected vehicle. Persist vehicle selection in `localStorage` or user meta.
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, `class-rtg-ajax.php`, new `frontend/js/modules/fitment.js`

### 1.4 — Tire noise rating (dB) data field and filter
**Priority:** Low
**Status:** Pending
**Scope:** Add `noise_db` decimal column to `wp_rtg_tires` (migration 12+). Include in CSV import/export (column 22). Add a range slider filter alongside the existing price/warranty/weight sliders in `filters.js`. Display the dB value on tire cards with an icon from `RTG_Icons`.
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, `class-rtg-admin.php` (add/edit form), `frontend/js/modules/filters.js`, `frontend/js/modules/cards.js`

### 1.5 — Road hazard warranty details field
**Priority:** Low
**Status:** Pending
**Scope:** Add `road_hazard_warranty` text column to `wp_rtg_tires`. Display as a badge/tooltip on tire cards (similar to 3PMS badge). Include in CSV import/export and admin add/edit forms. Filter toggle: "Has road hazard warranty."
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, `class-rtg-admin.php`, `frontend/js/modules/cards.js`, `frontend/js/modules/filters.js`

### 1.6 — Tire image gallery (multiple images per tire)
**Priority:** Medium
**Status:** Pending
**Scope:** New `wp_rtg_tire_images` table (`tire_id`, `image_url`, `sort_order`, `alt_text`). Currently each tire has a single `image` column — keep that as the primary/thumbnail. Add a gallery lightbox to the existing `image-modal.js` module with swipe navigation. Admin UI: sortable image upload on the tire edit page.
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, `class-rtg-admin.php`, `frontend/js/modules/image-modal.js`

### 1.7 — Tire size calculator / plus-size converter
**Priority:** Low
**Status:** Pending
**Scope:** Standalone calculator widget (shortcode `[rtg_size_calculator]`) that converts between tire size formats (metric/imperial), calculates overall diameter, sidewall height, and shows speedometer error for plus-sizing. Pure frontend math — no backend needed. Link from tire cards to pre-fill the calculator with that tire's size.
**Files:** New `frontend/js/modules/size-calculator.js`, `class-rtg-frontend.php` (shortcode registration)

### 1.8 — Seasonal tire recommendations (based on date/location)
**Priority:** Low
**Status:** Pending
**Scope:** Use the existing `category` field (All-Season, All-Terrain, Winter, etc.) to surface seasonal suggestions. Show a banner or highlighted section based on current month (e.g., "Winter tire season — check these options"). Optional: use browser geolocation or a zip code input to tailor recommendations by climate zone. Could integrate with the existing AI recommendation engine in `class-rtg-ai.php`.
**Files:** `class-rtg-frontend.php`, `frontend/js/modules/cards.js`, optionally `class-rtg-ai.php`

### 1.9 — "Related tires" / "Customers also viewed" suggestions
**Priority:** Medium
**Status:** Pending
**Scope:** Query tires in the same category and size as the viewed tire. Can leverage the existing `wp_rtg_click_events` and `wp_rtg_search_events` tables for collaborative filtering (users who viewed X also viewed Y). Display a horizontal scroll row at the bottom of the tire detail drawer. Limit to 4-6 suggestions.
**Files:** `class-rtg-database.php`, `class-rtg-ajax.php`, `frontend/js/modules/cards.js`

### 1.10 — Tire brand info pages with logo and description
**Priority:** Low
**Status:** Pending
**Scope:** New `wp_rtg_brands` table (`slug`, `name`, `logo_url`, `description`, `website_url`). Auto-populate from distinct `brand` values in `wp_rtg_tires`. Add brand detail pages via rewrite rules (similar to how `class-rtg-compare.php` handles `/compare/`). Link brand names on tire cards to brand pages. Include Schema.org `Brand` markup.
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, new `class-rtg-brands.php`, `class-rtg-schema.php`

---

## 2. User Features

### 2.1 — "I own this tire" user ownership tracking
**Priority:** Medium
**Status:** Pending
**Scope:** New `wp_rtg_ownership` table (`user_id`, `tire_id`, `purchase_date`, `purchase_price`, `vehicle`, `created_at`). Add an "I own this" button on tire cards for logged-in users (similar to the existing favorites heart icon in `favorites.js`). Show owned tire count on user profile. Surface an "Owners say..." section pulling reviews from verified owners.
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, `class-rtg-ajax.php`, new `frontend/js/modules/ownership.js`

### 2.2 — Tire wear / mileage logging for owned tires
**Priority:** Low
**Status:** Pending
**Scope:** Depends on 2.1 (ownership). New `wp_rtg_wear_logs` table (`ownership_id`, `odometer`, `tread_depth_32nds`, `logged_at`). Users log periodic tread measurements. Display a wear chart (Chart.js) and projected remaining mileage vs. the tire's `mileage_warranty`. Could surface aggregate wear data anonymously ("Average owner gets X miles").
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, `class-rtg-ajax.php`, `frontend/js/modules/ownership.js`

### 2.3 — Community Q&A threads on tire pages
**Priority:** Medium
**Status:** Pending
**Scope:** New `wp_rtg_questions` and `wp_rtg_answers` tables. Q&A section below reviews on each tire. Questions have upvotes. Answers can be marked as "accepted" by the question author or admin. Admin moderation queue (extend existing Reviews page or add a Q&A tab). Email notifications via `RTG_Mailer` for new answers to your questions.
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, `class-rtg-ajax.php`, `class-rtg-mailer.php`, `class-rtg-admin.php`

### 2.4 — Comparison history (save/recall past comparisons)
**Priority:** Low
**Status:** Pending
**Scope:** Save comparison sets (tire IDs) to `localStorage` for guests or `wp_rtg_comparisons` table for logged-in users. Show a "Recent Comparisons" dropdown on the compare page (`class-rtg-compare.php`). Each entry stores the tire IDs and a timestamp. Limit to last 10 comparisons.
**Files:** `class-rtg-compare.php`, `frontend/js/compare.js`

### 2.5 — Export comparison to PDF
**Priority:** Low
**Status:** Pending
**Scope:** Generate a PDF of the current comparison table. Use a lightweight JS library (e.g., jsPDF + html2canvas) to capture the comparison grid. Include tire images, specs, ratings, and efficiency scores. Add a "Download PDF" button to the compare page top bar.
**Files:** `frontend/js/compare.js`, `frontend/templates/compare.php`

### 2.6 — Social sharing buttons on tire cards
**Priority:** Low
**Status:** Pending
**Scope:** Share buttons (copy link, X/Twitter, Facebook) on tire cards and comparison pages. The plugin already generates OG meta tags via `class-rtg-meta.php` — this extends that with share action buttons. Use native `navigator.share()` API where available with fallback buttons.
**Files:** `frontend/js/modules/cards.js`, `frontend/css/rivian-tires.css`

### 2.7 — Tire deal / sale price alerts (email notifications)
**Priority:** Medium
**Status:** Pending
**Scope:** Users subscribe to price drop alerts on specific tires. New `wp_rtg_price_alerts` table (`user_id`, `tire_id`, `target_price`, `email`, `active`). When a tire price is updated below the alert threshold (check in `RTG_Database::update_tire()`), send an email via `RTG_Mailer`. Admin settings for alert frequency limits.
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, `class-rtg-mailer.php`, `class-rtg-ajax.php`

### 2.8 — User profile page (reviews + favorites + owned tires)
**Priority:** Medium
**Status:** Pending
**Scope:** Frontend profile page (shortcode `[rtg_user_profile]`) showing a user's reviews (existing `get_user_reviews` endpoint), favorites (existing `wp_rtg_favorites` table), and owned tires (depends on 2.1). Tabbed layout. Users can edit their display name for reviews. Link from the review attribution to the profile page.
**Files:** `class-rtg-frontend.php`, `class-rtg-ajax.php`, `class-rtg-database.php`

---

## 3. Admin & Backend

### 3.1 — Bulk review actions (approve/reject multiple at once)
**Priority:** High
**Status:** Pending
**Scope:** The Reviews admin page currently handles one review at a time. Add checkbox selection + bulk action dropdown (Approve Selected, Reject Selected, Delete Selected) similar to WordPress's native list table pattern. The admin already uses bulk actions for tires in `class-rtg-admin.php` — follow the same pattern with `handle_bulk_review_action()`.
**Files:** `class-rtg-admin.php`, `admin/js/admin-scripts.js`, `admin/views/reviews.php`

### 3.2 — Advanced analytics export to CSV
**Priority:** Medium
**Status:** Pending
**Scope:** Add CSV download buttons to the Analytics admin page for click events, search events, and rating trends. The CSV export pattern already exists in the Import/Export page — reuse `RTG_Admin::export_csv()` logic. Include date range filtering for exports.
**Files:** `class-rtg-admin.php`, `admin/views/analytics.php`

### 3.3 — Webhook notifications for plugin events
**Priority:** Low
**Status:** Pending
**Scope:** Admin settings to configure webhook URLs for events: new review submitted, tire added/updated/deleted, price changed. Fire `wp_remote_post()` with JSON payloads on these events. Add a webhook log table for debugging failed deliveries. Useful for Zapier/Discord/Slack integrations.
**Files:** New `class-rtg-webhooks.php`, `class-rtg-admin.php` (settings page), `class-rtg-activator.php`

### 3.4 — Multi-site network support
**Priority:** Low
**Status:** Pending
**Scope:** Support WordPress multisite with network-wide activation. Each site gets its own tables (already prefixed with `$wpdb->prefix`). Add a network admin page for global settings (shared API keys, default configuration). Handle `switch_to_blog()` in cron jobs like the link checker.
**Files:** `rivian-tire-guide.php`, `class-rtg-activator.php`, `class-rtg-admin.php`

### 3.5 — Scheduled CSV auto-import from URL
**Priority:** Medium
**Status:** Pending
**Scope:** Admin setting to configure a remote CSV URL and import schedule (daily/weekly). Use `wp_cron` to fetch and import tire data automatically. Reuse the existing CSV import logic in `class-rtg-admin.php`. Add import history log and email notification on success/failure via `RTG_Mailer`. Support HTTP basic auth and custom headers for authenticated feeds.
**Files:** `class-rtg-admin.php`, `class-rtg-mailer.php`

### 3.6 — Admin activity log / audit trail
**Priority:** Medium
**Status:** Pending
**Scope:** New `wp_rtg_activity_log` table (`user_id`, `action`, `object_type`, `object_id`, `details_json`, `created_at`). Log all admin actions: tire CRUD, review moderation, settings changes, imports/exports, bulk operations. New admin page (Tire Guide > Activity Log) with filterable list. Auto-prune entries older than 90 days via cron.
**Files:** `class-rtg-activator.php`, new `class-rtg-activity-log.php`, `class-rtg-admin.php`

### 3.7 — Bulk tire price update tool
**Priority:** Medium
**Status:** Pending
**Scope:** Admin tool to update prices for multiple tires at once. Options: percentage increase/decrease across all tires, by brand, or by category. CSV upload for targeted price updates (tire_id + new price). Preview changes before applying. Integrates with price history (1.2) to record all changes.
**Files:** `class-rtg-admin.php`, `admin/views/bulk-price.php`, `class-rtg-database.php`

---

## 4. Technical & SEO

### 4.1 — Progressive Web App (PWA) support
**Priority:** Low
**Status:** Pending
**Scope:** Add a `manifest.json` and service worker for offline-capable tire browsing. Cache the tire catalog, images, and static assets. The plugin already uses `IntersectionObserver` and lazy loading — extend with a service worker for offline fallback. Add "Install App" prompt on mobile.
**Files:** New `frontend/sw.js`, `class-rtg-frontend.php` (manifest + SW registration)

### 4.2 — Multi-language / i18n support (translation-ready)
**Priority:** Medium
**Status:** Pending
**Scope:** Wrap all user-facing strings in `__()` / `esc_html__()` with text domain `rivian-tire-guide` (already set in `.phpcs.xml`). Generate `.pot` file. JS strings via `wp_localize_script()` (already used for AJAX data). Priority: frontend UI strings, admin labels, email templates in `RTG_Mailer`, validation error messages.
**Files:** All PHP files in `includes/` and `admin/`, `frontend/js/modules/*.js`

### 4.3 — Open Graph meta tags for comparison page URLs
**Priority:** Medium
**Status:** Pending
**Scope:** The plugin already has `class-rtg-meta.php` for OG tags on the main guide page. Extend to generate dynamic OG tags for comparison URLs (`/compare/?tires=X,Y,Z`) with a title like "Compare: Tire A vs Tire B" and a description summarizing the compared tires. The compare page already has its own template via `class-rtg-compare.php`.
**Files:** `class-rtg-meta.php`, `class-rtg-compare.php`

### 4.4 — Admin bulk edit for tire fields (inline table editing)
**Priority:** High
**Status:** Pending
**Scope:** Make the All Tires admin list table cells editable inline (click to edit price, category, tags, etc.). The admin already has an All Tires table in `class-rtg-admin.php`. Add `contenteditable` cells or inline input fields with AJAX save on blur/Enter. Batch save button for multiple changes. Highlight unsaved changes.
**Files:** `class-rtg-admin.php`, `admin/js/admin-scripts.js`, `admin/css/admin-styles.css`

### 4.5 — Video reviews / YouTube embed support
**Priority:** Low
**Status:** Pending
**Scope:** Add `video_url` column to `wp_rtg_ratings` for reviewers to attach a YouTube/Vimeo link. Validate URL format. Embed video in the review display using WordPress's `wp_oembed_get()`. Show a play button icon on reviews that include video. Optional: `video_review_url` field on `wp_rtg_tires` for official manufacturer/reviewer videos.
**Files:** `class-rtg-activator.php`, `class-rtg-database.php`, `class-rtg-ajax.php`, `frontend/js/modules/ratings.js`

### 4.6 — JWT authentication for REST API (mobile app ready)
**Priority:** Low
**Status:** Pending
**Scope:** Add JWT token generation endpoint (`POST /rtg/v1/auth/token`) to `class-rtg-rest-api.php`. Issue tokens on valid WordPress credentials. Validate JWT on protected endpoints (rating submission, favorites). Store signing secret in plugin settings. Token expiry and refresh flow. Enables native mobile app integration.
**Files:** `class-rtg-rest-api.php`, `class-rtg-admin.php` (settings)

### 4.7 — Image optimization / WebP conversion on upload
**Priority:** Medium
**Status:** Pending
**Scope:** When admins upload tire images or import via CSV, auto-convert to WebP format using `imagecreatefromjpeg()`/`imagecreatefromping()` + `imagewebp()` (GD library). Store WebP alongside originals. Serve WebP with `<picture>` tag fallback. The existing lazy loading in `image-modal.js` already handles `data-src` swapping — extend to include `data-srcset` for format selection.
**Files:** `class-rtg-admin.php`, `class-rtg-database.php`, `frontend/js/modules/cards.js`, `frontend/js/modules/image-modal.js`

### 4.8 — A/B testing framework for affiliate link placement
**Priority:** Low
**Status:** Pending
**Scope:** Test different CTA button text, colors, and positions on tire cards. Use the existing `wp_rtg_click_events` table to track conversion rates per variant. Admin UI to create experiments with control/variant groups. Assign users to groups via cookie. Report click-through rates per variant on the Analytics page.
**Files:** `class-rtg-admin.php`, `class-rtg-database.php`, `frontend/js/modules/analytics.js`, `frontend/js/modules/cards.js`

### 4.9 — Tire data JSON-LD for individual tire permalink pages
**Priority:** Medium
**Status:** Pending
**Scope:** The plugin already outputs `Product` + `AggregateRating` Schema.org markup via `class-rtg-schema.php` for the main listing. The standalone tire review page (`class-rtg-tire-review.php`) needs its own `Product` JSON-LD with full spec details, individual `Review` objects, `AggregateOffer` for pricing, and `Brand` entity. This improves individual tire page rich snippet eligibility.
**Files:** `class-rtg-schema.php`, `class-rtg-tire-review.php`

### 4.10 — RSS feed for new tire additions
**Priority:** Low
**Status:** Pending
**Scope:** Custom RSS feed at `/feed/rtg-tires/` showing recently added tires with title, price, category, image, and link. Register via `add_feed()` in WordPress. Include `<enclosure>` for tire images. Useful for users who want to monitor new additions via RSS readers. Optional: separate feed for price changes (depends on 1.2).
**Files:** `class-rtg-frontend.php` or new `class-rtg-feeds.php`

---

## Priority Matrix

| Priority | Count | Items |
|----------|-------|-------|
| **High** | 3 | Fitment validator (1.3), Bulk review actions (3.1), Inline bulk edit (4.4) |
| **Medium** | 16 | Dark mode (1.1), Price history (1.2), Image gallery (1.6), Related tires (1.9), Ownership tracking (2.1), Q&A threads (2.3), Sale alerts (2.7), User profile (2.8), Analytics export (3.2), Scheduled import (3.5), Activity log (3.6), Bulk price update (3.7), i18n (4.2), OG meta for compare (4.3), WebP images (4.7), Tire JSON-LD (4.9) |
| **Low** | 16 | Noise rating (1.4), Road hazard field (1.5), Size calculator (1.7), Seasonal recs (1.8), Brand pages (1.10), Mileage logging (2.2), Comparison history (2.4), PDF export (2.5), Social sharing (2.6), Webhooks (3.3), Multi-site (3.4), PWA (4.1), Video reviews (4.5), JWT auth (4.6), A/B testing (4.8), RSS feed (4.10) |

> **Total:** 0 of 35 items resolved (0%)
