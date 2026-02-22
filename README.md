# Rivian Tire Guide

A comprehensive WordPress plugin that provides an interactive tire catalog for Rivian vehicle owners. Features advanced filtering, side-by-side comparison, user and guest reviews, AI-powered recommendations, affiliate link management, analytics, and an efficiency scoring system to help drivers choose the best tires for range and performance.

## Features

### Tire Catalog
- **Interactive Tire Cards** — Browse tires with real-time filtering by size, brand, category, price, weight, warranty, 3PMS, EV rated, and studded availability.
- **Smart Search** — Fuzzy search with type-ahead suggestions for brands, models, categories, and sizes.
- **Side-by-Side Comparison** — Select up to 4 tires and compare specs on a dedicated comparison page with best-value highlighting.
- **Shareable Filtered Views** — All filter state persists in URL parameters (`?brand=Michelin&size=275/65R18`), enabling shareable links and browser back/forward navigation.
- **Shareable Tire Links** — Direct links to individual tires with deep-link highlighting.
- **Active Filter Chips** — Dismissible chips show active filters at a glance.
- **Smart No Results** — When filters produce no matches, actionable suggestions help users relax specific filters.

### Ratings & Reviews
- **Star Ratings** — SVG star ratings with half-star precision. Logged-in users rate tires 1-5 stars with keyboard navigation (arrow keys, Enter/Space).
- **Text Reviews** — Optional review title and body alongside star ratings, with a slide-in reviews drawer for each tire.
- **Guest Reviews** — Non-logged-in visitors can submit reviews with name and email. Includes honeypot spam prevention and IP-based rate limiting.
- **Review Moderation** — Admin approval queue with pending/approved/rejected status tabs. Admin-submitted reviews auto-approve; user and guest reviews default to pending.
- **Email Notifications** — Admins receive styled HTML email notifications for new guest reviews. Reviewers receive approval notification emails.

### AI Tire Advisor
- **Natural Language Search** — Powered by Anthropic's Claude API. Visitors type queries like "best winter tire for my Rivian with 20 inch wheels" and receive AI-ranked recommendations from the tire catalog.
- **Response Caching** — Identical queries cached for 1 hour to reduce API costs.
- **Per-IP Rate Limiting** — Configurable rate limit (default: 10 queries/minute) to control API usage.
- **Admin Settings** — Enable/disable toggle, API key input, model selector (Claude Haiku 4.5 or Claude Sonnet 4), and rate limit configuration.

### Efficiency Scoring
- **Proprietary Algorithm** — Weighted formula (0-100 score, A/B/C/D/F grade) estimating range-friendliness based on weight, tread depth, load range, speed rating, UTQG, category, width, and 3PMS certification.
- **Single Source of Truth** — Calculation lives in `RTG_Database::calculate_efficiency()`. Admin form uses AJAX to call the PHP formula directly.

### Analytics
- **Click Tracking** — Tracks affiliate link clicks (purchase, review) via `navigator.sendBeacon()` with 5-second server-side deduplication.
- **Search Analytics** — Tracks search queries, active filters, sort options, and result counts with client-side debounce and server-side deduplication.
- **Admin Dashboard** — Period selector (7/30/90 days), summary cards, Chart.js line charts for clicks and search volume, ranked tables for most clicked tires, top queries, zero-result searches, and most used filters.
- **Data Retention** — Configurable retention period (7-365 days) with daily WP-Cron cleanup.

### Favorites
- **Wishlist System** — Logged-in users save tires to a personal favorites list via heart icon. "My Favorites" filter toggle with badge count. Optimistic UI updates.

### Admin
- **Dashboard** — Overview cards (total tires, average price, efficiency, ratings), breakdowns by category/brand/size/grade, content health indicators (pending reviews, missing images/links).
- **Tire Management** — Full CRUD with search, filters, bulk actions, tire duplication, and tag suggestions.
- **CSV Import/Export** — Bulk import with duplicate handling (skip/update), auto-generated IDs, auto-calculated efficiency, MIME validation, and full catalog export.
- **Reviews Management** — Pending/approved/rejected tabs with approve, reject, and delete actions.
- **Affiliate Links Dashboard** — Centralized view of all purchase and review links with link classification (affiliate vs. direct), filter tabs, and inline AJAX editing.
- **Stock Wheels Guide** — Manage stock wheel data for Rivian models.
- **Analytics** — Visual analytics with Chart.js (see Analytics section above).
- **Settings** — Rows per page, compare slug, user reviews slug, server-side pagination toggle, theme colors (14 CSS custom properties), dropdown options (brands, sizes, categories, load ranges, speed ratings, size-to-diameter mapping, load index-to-lbs mapping), affiliate domains, AI settings, and analytics retention.

### SEO & Social
- **Schema.org Structured Data** — Automatic JSON-LD output (Product, AggregateRating, Review, ItemList) for rich search results.
- **Open Graph & Twitter Cards** — Sharing a `?tire=` link shows rich previews with tire name, description, price, and image.

### Performance
- **Server-Side Pagination** — Optional AJAX mode with server-side filtering/sorting for large catalogs (200+ tires). Client-side mode available for smaller catalogs.
- **Transient Caching** — Tire query results cached for 1 hour, automatically invalidated on write operations.
- **Lazy Loading** — IntersectionObserver-based image lazy loading with shimmer placeholders and 600px root margin preloading.
- **Skeleton Loading** — Shimmer placeholder cards display immediately while data loads.
- **Minified Assets** — esbuild produces `.min.js` and `.min.css` files with console stripping. Served automatically when `SCRIPT_DEBUG` is off.
- **Inline SVG Icons** — Replaced Font Awesome CDN (~60 KB) with lightweight inline SVGs for the ~35 icons used.

### Accessibility
- **Keyboard Navigation** — Arrow keys, Enter/Space for star ratings. Tab navigation for all interactive elements.
- **ARIA Attributes** — `role`, `aria-label`, `aria-checked`, `aria-expanded`, `aria-controls`, `aria-live` on all interactive elements.
- **Skip-to-Content Link** — Keyboard shortcut to skip navigation.
- **Focus-Visible Styles** — Outline styles on all interactive elements.
- **Screen Reader Support** — `.screen-reader-text` utility class, status regions for dynamic content.
- **Reduced Motion** — Respects `prefers-reduced-motion` for animations.

### REST API
- `GET /wp-json/rtg/v1/tires` — Filtered, paginated tire listing.
- `GET /wp-json/rtg/v1/tires/{tire_id}` — Single tire with ratings.
- `GET /wp-json/rtg/v1/tires/{tire_id}/reviews` — Paginated reviews.
- `POST /wp-json/rtg/v1/efficiency` — Calculate efficiency score from specs.
- **Rate Limiting** — 60 req/min for reads, 10 req/min for writes per IP.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Node.js 18+ (for building minified assets)

## Installation

1. Download or clone this repository into your `wp-content/plugins/` directory:
   ```
   wp-content/plugins/rivian-tire-guide/
   ```
2. Install build dependencies and build minified assets:
   ```bash
   cd wp-content/plugins/rivian-tire-guide
   npm install && npm run build
   ```
3. Activate the plugin via **Plugins > Installed Plugins** in the WordPress admin.
4. The plugin will automatically create the required database tables and run migrations.
5. Navigate to **Tire Guide > Settings** to configure display options, theme colors, and AI settings.
6. Add the shortcode `[rivian_tire_guide]` to any page or post.

## Usage

### Shortcodes

**Tire Guide** — Place on any page to display the full catalog:
```
[rivian_tire_guide]
```

With pre-filter attributes:
```
[rivian_tire_guide brand="Michelin" category="All-Season" size="275/65R20" sort="price-asc" 3pms="yes"]
```

**User Reviews** — Display all reviews by a specific user (via `?reviewer=ID` URL param):
```
[rivian_user_reviews]
```

### Adding Tires

1. Go to **Tire Guide > Add New** in the WordPress admin.
2. Fill in tire specifications (brand, model, size, price, weight, etc.).
3. The efficiency score is calculated automatically via AJAX as you fill in specs.
4. Tires appear in the frontend guide immediately.

For bulk operations, use **Tire Guide > Import / Export** to import tires via CSV.

### Comparison Page

The plugin registers a custom URL at `/tire-compare/` (configurable in settings). Users select tires from the guide and compare specs side-by-side with best-value highlighting.

### Settings

Navigate to **Tire Guide > Settings** to configure:

- **Tires Per Page** — Number of tire cards per page (4-48).
- **CDN Image Prefix** — Optional CDN URL for image optimization.
- **Compare Page Slug** — Custom URL slug for the comparison page.
- **User Reviews Page Slug** — Custom URL slug for the user reviews page.
- **Server-Side Pagination** — Enable AJAX-based pagination for large catalogs.
- **Theme Colors** — 14 hex color values for full theme customization (accent, backgrounds, text, borders, stars).
- **Dropdown Options** — Manage brands, categories, sizes, diameters, load ranges, speed ratings, load index mappings, and size-to-diameter mappings.
- **Affiliate Link Domains** — Configure affiliate network domains for link classification.
- **AI Tire Recommendations** — Enable/disable, API key, model selection, and rate limiting.
- **Analytics Retention** — Data retention period (7-365 days).

## File Structure

```
rivian-tire-guide/
├── rivian-tire-guide.php            # Main plugin entry point
├── uninstall.php                    # Cleanup on plugin deletion
├── package.json                     # Build tools (esbuild)
├── esbuild.config.mjs              # Build configuration
├── includes/
│   ├── class-rtg-activator.php      # Database creation & migrations
│   ├── class-rtg-deactivator.php    # Deactivation cleanup
│   ├── class-rtg-database.php       # All database operations & caching
│   ├── class-rtg-admin.php          # Admin UI, CSV import/export, settings
│   ├── class-rtg-frontend.php       # Shortcode rendering & asset enqueue
│   ├── class-rtg-ajax.php           # AJAX endpoints (ratings, reviews, favorites, analytics, AI)
│   ├── class-rtg-compare.php        # Comparison page routing & CSP headers
│   ├── class-rtg-schema.php         # Schema.org JSON-LD structured data
│   ├── class-rtg-meta.php           # Open Graph & Twitter Card meta tags
│   ├── class-rtg-rest-api.php       # REST API endpoints
│   ├── class-rtg-ai.php             # Claude API integration
│   └── class-rtg-mailer.php         # HTML email notifications
├── admin/
│   ├── views/                       # Admin page templates (10 views)
│   ├── css/                         # Admin stylesheets
│   └── js/                          # Admin scripts
├── frontend/
│   ├── templates/                   # Frontend templates (tire-guide, compare, user-reviews)
│   ├── css/                         # Frontend stylesheets
│   └── js/
│       ├── rivian-tires.js          # Main entry point (ES module)
│       ├── compare.js               # Comparison page script
│       ├── rtg-shared.js            # Shared URL validation & escaping
│       ├── user-reviews.js          # User reviews page script
│       └── modules/                 # 14 focused ES modules
│           ├── state.js             # Global state management
│           ├── helpers.js           # DOM utilities, debounce, icons
│           ├── validation.js        # Input sanitization & patterns
│           ├── analytics.js         # Click & search tracking
│           ├── cards.js             # Tire card rendering
│           ├── compare.js           # Compare checkbox & bar
│           ├── favorites.js         # Wishlist AJAX & UI
│           ├── filters.js           # Filter UI, sorting, URL state
│           ├── ratings.js           # Review modal & drawer
│           ├── search.js            # Smart search autocomplete
│           ├── server.js            # Server-side pagination
│           ├── ai-recommend.js      # AI search interface
│           ├── tooltips.js          # Filter help tooltips
│           └── image-modal.js       # Image lightbox
├── tests/
│   ├── bootstrap.php                # PHPUnit WordPress test bootstrap
│   ├── test-database.php            # PHP unit tests (21 tests)
│   ├── test-activator.php           # Activator tests
│   ├── test-admin.php               # Admin tests
│   └── test-validation.js           # JS unit tests (83 tests)
└── .github/
    └── workflows/ci.yml             # CI: JS tests, build, PHP linting
```

## Database Schema

The plugin creates 6 tables (all prefixed with `wp_rtg_`):

| Table | Purpose |
|-------|---------|
| `rtg_tires` | Main tire catalog (25 columns) |
| `rtg_ratings` | User and guest reviews with moderation |
| `rtg_wheels` | Stock wheel guide data |
| `rtg_favorites` | User tire wishlist |
| `rtg_click_events` | Affiliate link click tracking |
| `rtg_search_events` | Search and filter analytics |

Schema changes are managed via a numbered migration system (`rtg_db_version` option, currently v11).

## Efficiency Score

The efficiency score helps Rivian owners identify range-friendly tires using a weighted formula:

| Factor | Weight | Better Score |
|--------|--------|--------------|
| Weight | 26% | Lighter tires |
| Tread Depth | 16% | Shallower tread |
| Load Range | 16% | SL > XL > D > E |
| Speed Rating | 10% | Lower ratings (less rolling resistance) |
| UTQG | 10% | Higher treadwear numbers |
| Category | 10% | All-Season/Highway > All-Terrain > Mud |
| 3PMS Certification | 8% | Non-winter tires |
| Width | 4% | Narrower tires |

**Grades:** A (80-100), B (65-79), C (50-64), D (35-49), F (0-19)

> **Note:** This score is an estimate based on specifications. It does not reflect real-world range testing and should not be used as a measure of tire quality or safety.

## Development

### Build

```bash
npm install
npm run build          # One-time production build (minified JS/CSS, console stripped)
npm run build:watch    # Watch mode for development
```

### Test

```bash
npm test               # Run JS validation tests (83 tests)
```

PHP tests require a WordPress test environment:
```bash
phpunit                # Run PHP unit tests (21 tests)
```

### CI

GitHub Actions runs on every push and PR:
- JavaScript unit tests
- esbuild build verification
- PHP syntax linting (PHP 7.4, 8.0, 8.2)

## Security

This plugin follows WordPress security best practices:

- All database queries use `$wpdb->prepare()` with parameterized placeholders
- CSRF protection via nonces on all forms and AJAX mutations
- Input sanitization (`sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`, `esc_url_raw`, `intval`, etc.)
- Output escaping (`esc_html`, `esc_url`, `esc_attr`)
- Capability checks (`manage_options`) on all admin actions
- Rate limiting on review submissions (3 per 5 minutes per user/IP)
- Rate limiting on REST API endpoints (60/min reads, 10/min writes)
- Rate limiting on AI queries (configurable per-IP)
- URL domain allowlisting for affiliate, review, and image links
- Content-Security-Policy headers on the standalone comparison page
- HTML escaping on all dynamic content in JavaScript templates
- CSV upload MIME type validation via `finfo`
- Honeypot field for guest review spam prevention
- Shared URL validation module (`rtg-shared.js`) as single source of truth

For security concerns, see [SECURITY.md](SECURITY.md).

## License

Proprietary. All rights reserved by RivianTrackr.

## Support

For issues, feature requests, or questions, please open an issue on this repository.
