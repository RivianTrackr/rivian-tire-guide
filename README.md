# Rivian Tire Guide

A WordPress plugin that provides an interactive tire guide for Rivian vehicle owners. Features advanced filtering, side-by-side comparison, user ratings, and an efficiency scoring system to help drivers choose the best tires for range and performance.

## Features

- **Interactive Tire Catalog** — Browse tires with real-time filtering by size, brand, category, price, weight, warranty, and more.
- **Smart Search** — Fuzzy search with type-ahead suggestions for brands, models, categories, and sizes.
- **Side-by-Side Comparison** — Select up to 4 tires and compare specs in a dedicated comparison page.
- **User Ratings** — Logged-in users can rate tires (1-5 stars) with aggregated averages displayed on each card.
- **Efficiency Scoring** — Proprietary algorithm (A-F grade, 0-100 score) that estimates range-friendliness based on weight, tread depth, load range, speed rating, UTQG, category, width, and 3PMS certification.
- **SEO Structured Data** — Automatic Schema.org JSON-LD output (Product + AggregateRating) for rich search results.
- **Admin Dashboard** — Full CRUD management for tires, ratings overview, customizable dropdown options, and theme color settings.
- **Customizable Theme** — 11 CSS custom property color overrides configurable from the WordPress admin.
- **Shortcode** — Embed the tire guide on any page with `[rivian_tire_guide]`.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+

## Installation

1. Download or clone this repository into your `wp-content/plugins/` directory:
   ```
   wp-content/plugins/rivian-tire-guide/
   ```
2. Activate the plugin via **Plugins > Installed Plugins** in the WordPress admin.
3. The plugin will automatically create the required database tables (`wp_rtg_tires` and `wp_rtg_ratings`).
4. Navigate to **Tire Guide > Settings** to configure display options and theme colors.
5. Add the shortcode `[rivian_tire_guide]` to any page or post.

## Usage

### Shortcode

Place the following shortcode on any WordPress page or post:

```
[rivian_tire_guide]
```

### Adding Tires

1. Go to **Tire Guide > Add New** in the WordPress admin.
2. Fill in tire specifications (brand, model, size, price, weight, etc.).
3. The efficiency score is calculated automatically on save.
4. Tires appear in the frontend guide immediately.

### Comparison Page

The plugin registers a custom URL at `/tire-compare/` (configurable in settings). Users can select tires from the guide and open a side-by-side comparison.

### Settings

Navigate to **Tire Guide > Settings** to configure:

- **Tires Per Page** — Number of tire cards per page (4-48).
- **CDN Image Prefix** — Optional CDN URL for image optimization.
- **Compare Page Slug** — Custom URL slug for the comparison page.
- **Theme Colors** — 11 hex color values for full theme customization.
- **Dropdown Options** — Manage brands, categories, sizes, diameters, load ranges, speed ratings, and load index mappings.

## File Structure

```
rivian-tire-guide/
├── rivian-tire-guide.php          # Main plugin entry point
├── uninstall.php                  # Cleanup on plugin deletion
├── includes/
│   ├── class-rtg-activator.php    # Database table creation
│   ├── class-rtg-deactivator.php  # Deactivation cleanup
│   ├── class-rtg-database.php     # All database operations
│   ├── class-rtg-admin.php        # Admin UI and action handlers
│   ├── class-rtg-frontend.php     # Shortcode and asset enqueue
│   ├── class-rtg-ajax.php         # AJAX endpoints (ratings)
│   ├── class-rtg-compare.php      # Comparison page routing
│   └── class-rtg-schema.php       # Schema.org structured data
├── admin/
│   ├── views/                     # Admin page templates
│   ├── css/                       # Admin stylesheets
│   └── js/                        # Admin scripts
└── frontend/
    ├── templates/                 # Frontend page templates
    ├── css/                       # Frontend stylesheets
    └── js/                        # Frontend scripts
```

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

**Grades:** A (80-100), B (65-79), C (50-64), D (35-49), E (20-34), F (0-19)

> **Note:** This score is an estimate based on specifications. It does not reflect real-world range testing and should not be used as a measure of tire quality or safety.

## Security

This plugin follows WordPress security best practices:

- All database queries use `$wpdb->prepare()` with parameterized placeholders
- CSRF protection via nonces on all forms and AJAX mutations
- Input sanitization (`sanitize_text_field`, `esc_url_raw`, `intval`, etc.)
- Output escaping (`esc_html`, `esc_url`, `esc_attr`)
- Capability checks (`manage_options`) on all admin actions
- Rate limiting on rating submissions (10/minute per user)
- URL domain allowlisting for affiliate and image links
- Content-Security-Policy headers on the standalone comparison page
- HTML escaping on all dynamic content in JavaScript templates

For security concerns, see [SECURITY.md](SECURITY.md).

## License

Proprietary. All rights reserved by RivianTrackr.

## Support

For issues, feature requests, or questions, please open an issue on this repository.
