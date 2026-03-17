# Rivian Tire Guide — Review & Recommendations

Comprehensive review of enhancements, features, and security improvements.

---

## Security Improvements

### 1. API Key Storage — Encrypt at Rest
**Priority: High**
**File:** `class-rtg-ai.php`, `class-rtg-admin.php` (settings save/load)

The Anthropic API key is stored in `wp_options` as plaintext. If the database is compromised, the key is immediately usable.

**Recommendation:** Encrypt the API key before storing it using WordPress's `AUTH_KEY` salt:
```php
// On save:
$encrypted = openssl_encrypt($api_key, 'aes-256-cbc', AUTH_KEY, 0, substr(AUTH_SALT, 0, 16));
update_option('rtg_ai_api_key_enc', $encrypted);

// On read:
$decrypted = openssl_decrypt($stored, 'aes-256-cbc', AUTH_KEY, 0, substr(AUTH_SALT, 0, 16));
```

This isn't bulletproof (the keys are in `wp-config.php`), but it prevents casual DB dumps from leaking the API key.

---

### 2. CSP Headers — Apply Site-Wide, Not Just Compare Page
**Priority: High**
**File:** `class-rtg-compare.php:43-46`

Content-Security-Policy headers are only set on the standalone compare page. The main tire guide shortcode page, tire review page, and user reviews page have no CSP at all.

**Recommendation:** Add a `send_headers` or `wp_headers` filter that injects CSP headers on all pages where the plugin renders output. Consider using `wp_add_inline_script` with a nonce-based CSP to avoid `'unsafe-inline'` for styles.

---

### 3. REST API `/feed` Endpoint — Unrestricted Data Exposure
**Priority: Medium**
**File:** `class-rtg-rest-api.php:147-209`

The `/wp-json/rtg/v1/feed` endpoint exposes the *entire* tire catalog including affiliate links with `Access-Control-Allow-Origin: *`. This allows anyone to scrape all your tire data and affiliate links programmatically.

**Recommendations:**
- Remove affiliate links (`link`, `review_link`) from the public feed, or gate them behind authentication.
- Replace the wildcard CORS header with a configurable allowlist of domains, or remove it entirely.
- Add cache headers (`Cache-Control: public, max-age=3600`) to reduce repeated scraping load.

---

### 4. Link Checker — SSL Verification Disabled
**Priority: Medium**
**File:** `class-rtg-link-checker.php:213`

`'sslverify' => false` bypasses SSL certificate validation on outbound HTTP requests. This makes the link checker vulnerable to MITM attacks on the server.

**Recommendation:** Set `'sslverify' => true` (the WordPress default). If specific affiliate domains have certificate issues, handle those as known exceptions rather than disabling verification globally.

---

### 5. Guest Review IP Handling — Uses Raw `REMOTE_ADDR`
**Priority: Medium**
**File:** `class-rtg-ajax.php:228`

The guest review handler uses `$_SERVER['REMOTE_ADDR']` directly, while the AI rate limiter (`class-rtg-ai.php:336-366`) has a proper `get_client_ip()` method that handles reverse proxies correctly.

**Recommendation:** Extract the `get_client_ip()` method into a shared utility (or a base class) and use it consistently across all rate-limiting code paths: guest reviews, AI, REST API, and analytics session hashing.

---

### 6. Nonce Skipped for Logged-Out Users on Read Endpoints
**Priority: Low**
**File:** `class-rtg-ajax.php:82-88, 382-386, 412-418`

`get_tire_ratings`, `get_tire_reviews`, and `get_user_reviews` skip nonce verification for logged-out users. While these are read-only endpoints, this means any page on the internet can make cross-origin AJAX requests to fetch your tire data.

**Recommendation:** Either enforce nonces for all users (and serve the nonce via a `<meta>` tag or localized script), or add a lightweight origin/referer check for anonymous requests.

---

### 7. Analytics Session Hash — Weak Privacy Guarantee
**Priority: Low**
**File:** `class-rtg-database.php` (session hash), JS analytics module

The session hash is `SHA-256(IP + User-Agent + date)`. This is better than storing raw IPs, but the input space is small enough that brute-forcing the IP from the hash is trivial (2^32 IPv4 addresses).

**Recommendation:** Add a server-side pepper (a random secret stored in options, generated once on activation) to the hash input: `SHA-256(pepper + IP + UA + date)`. This makes rainbow table attacks infeasible.

---

## Feature Enhancements

### 8. Price History Tracking
**Priority: High**

The plugin stores a single price per tire. Users would benefit from seeing price trends over time.

**Recommendation:**
- Add a `wp_rtg_price_history` table: `(tire_id, price, recorded_at)`.
- Record price on tire creation and each edit where price changes.
- Show a small sparkline or "Price trend" indicator on tire cards (up/down/stable).
- Optional: scheduled cron job that scrapes current affiliate page prices (if feasible).

---

### 9. Tire Size Compatibility Warnings
**Priority: High**

Users can currently view any tire regardless of whether it fits their vehicle. There's no explicit "this tire fits/doesn't fit" indication based on their selected vehicle.

**Recommendation:**
- When a user selects a vehicle (R1T/R1S/R2/R3), visually badge tires that are compatible vs. incompatible using the existing `vehicleSizeMap`.
- Add a warning toast if a user adds an incompatible tire to the compare list.
- Consider adding wheel offset/clearance data to the stock wheels table for more precise fitment.

---

### 10. Export Comparison as PDF/Image
**Priority: Medium**

The compare page currently only works as a live web page. Users frequently want to save or share comparisons.

**Recommendation:**
- Add a "Save as Image" button using `html2canvas` (small library, ~40KB).
- Alternatively, add a "Share Comparison" button that generates a short URL with tire IDs encoded (you already have URL-based state).
- Consider a print stylesheet for the compare template.

---

### 11. Tire Availability / Stock Status
**Priority: Medium**

Affiliate links may point to out-of-stock products, which frustrates users.

**Recommendation:**
- Extend the link checker to look for common "out of stock" patterns in the response HTML (`/out.of.stock|sold.out|unavailable/i`).
- Surface stock status as a badge on tire cards ("In Stock" / "Check Availability").
- Log availability changes over time in analytics for trend visibility.

---

### 12. User Notification Preferences
**Priority: Medium**

Currently, email notifications are sent without user consent or unsubscribe options.

**Recommendation:**
- Add an unsubscribe link to the review approval email (store opt-out in user meta).
- Let users opt-in to notifications for price drops on favorited tires.
- Include a `List-Unsubscribe` email header for better deliverability.

---

### 13. Advanced Search Filters
**Priority: Medium**

Missing filters that Rivian owners commonly care about:

- **Load range filter** (SL/XL/D/E) — currently not filterable from the frontend
- **Speed rating filter** — available in data but not as a dropdown
- **Efficiency grade filter** (A/B/C/D/F) — users may want to filter by grade directly
- **Weight range slider** — currently only max weight, add min weight too
- **Multi-select for categories** — e.g., "All-Season OR All-Terrain"

---

### 14. Review Helpfulness Voting
**Priority: Low**

Users can read reviews but can't indicate which reviews are helpful.

**Recommendation:**
- Add a "Helpful" / "Not Helpful" button to each review.
- Store votes in a new `wp_rtg_review_votes` table.
- Sort reviews by helpfulness score by default.
- Rate-limit voting (1 vote per review per user/IP).

---

### 15. Seasonal Tire Recommendations Widget
**Priority: Low**

Proactively suggest tire swaps based on the current season and user's location.

**Recommendation:**
- Add a dismissible banner: "Winter is coming — check out 3PMS-rated tires for your R1S."
- Trigger based on month (configurable in settings) or use a geolocation API for weather-based suggestions.
- Links directly to a pre-filtered view.

---

## Code Quality & Performance

### 16. Consolidate Rate Limiting into a Shared Class
**Priority: Medium**
**Files:** `class-rtg-ai.php`, `class-rtg-ajax.php`, `class-rtg-rest-api.php`

Rate limiting is implemented independently in three places with slightly different patterns:
- AI: static methods + `get_client_ip()`
- AJAX: instance methods + raw `$_SERVER['REMOTE_ADDR']`
- REST: inline in `check_rate_limit()` with regex sanitization

**Recommendation:** Create a `RTG_Rate_Limiter` utility class:
```php
class RTG_Rate_Limiter {
    public static function check( $bucket, $limit, $window = 60 ) { ... }
    public static function get_client_ip() { ... }
}
```

This eliminates the IP-handling inconsistency (item #5) and makes rate limits testable.

---

### 17. Database Query Optimization — N+1 on Tire Cards
**Priority: Medium**
**File:** `class-rtg-database.php`

When the frontend loads, it fetches all tires, then makes a separate AJAX call to fetch ratings for all tire IDs. This is two round-trips.

**Recommendation:**
- Add a `get_tires_with_ratings()` method that JOINs the tires and ratings tables in a single query.
- Embed the pre-joined rating data in the initial `wp_localize_script` payload.
- This eliminates the initial ratings AJAX call and reduces time-to-interactive.

---

### 18. AI Context Token Efficiency
**Priority: Medium**
**File:** `class-rtg-ai.php:117-151`

`build_tire_context()` serializes *every* tire with *every* field into the system prompt. With 50+ tires, this can consume 3,000+ tokens per request unnecessarily.

**Recommendations:**
- Drop fields that rarely matter for recommendations (PSI, sort_order, created_at, image URL, link URLs).
- Use a more compact format (TSV or structured JSON) instead of pipe-delimited text.
- Consider adding a `max_tires_in_context` setting to limit context size for large catalogs.
- Pre-filter the context based on the query (e.g., if the user mentions "20-inch", only include 20" tires).

---

### 19. Add E2E / Integration Tests
**Priority: Medium**

The test suite covers unit tests (validation, DB) but has no end-to-end tests for:
- The full AJAX review submission flow
- AI recommendation → card rendering pipeline
- Compare page with 4 tires
- CSV import with various edge cases

**Recommendation:** Add a Playwright or Cypress test suite for critical user flows. Even 5-10 E2E tests would catch regressions that unit tests miss.

---

### 20. Upgrade Anthropic API Version
**Priority: Low**
**File:** `class-rtg-ai.php:183`

The plugin uses `anthropic-version: 2023-06-01`. This is the oldest supported version. Newer API versions include improved error handling and features.

**Recommendation:** Update to the latest API version (`2024-10-22` or newer) and test that the response format is still compatible. Consider adding the `anthropic-beta` header for extended features like prompt caching (could significantly reduce costs for the repeated system prompt).

---

## Summary Table

| # | Category | Item | Priority |
|---|----------|------|----------|
| 1 | Security | Encrypt API key at rest | High |
| 2 | Security | CSP headers on all plugin pages | High |
| 3 | Security | Restrict `/feed` endpoint data exposure | Medium |
| 4 | Security | Enable SSL verification in link checker | Medium |
| 5 | Security | Consistent IP detection across rate limiters | Medium |
| 6 | Security | Nonce/origin check for anonymous AJAX reads | Low |
| 7 | Security | Add pepper to analytics session hash | Low |
| 8 | Feature | Price history tracking | High |
| 9 | Feature | Tire size compatibility warnings | High |
| 10 | Feature | Export comparison as PDF/image | Medium |
| 11 | Feature | Tire availability/stock status detection | Medium |
| 12 | Feature | User notification preferences & unsubscribe | Medium |
| 13 | Feature | Advanced search filters (load range, speed, grade) | Medium |
| 14 | Feature | Review helpfulness voting | Low |
| 15 | Feature | Seasonal tire recommendation widget | Low |
| 16 | Code | Consolidate rate limiting into shared class | Medium |
| 17 | Code | Eliminate N+1 query for tire+rating loading | Medium |
| 18 | Code | Optimize AI context token usage | Medium |
| 19 | Code | Add E2E integration tests | Medium |
| 20 | Code | Upgrade Anthropic API version | Low |
