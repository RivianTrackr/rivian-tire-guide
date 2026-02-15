# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.1.x   | Yes       |
| < 1.1   | No        |

## Reporting a Vulnerability

If you discover a security vulnerability in the Rivian Tire Guide plugin, please report it responsibly.

**Do not open a public GitHub issue for security vulnerabilities.**

Instead, please contact us directly:

1. **Email:** Send details to the repository owner via GitHub's private contact method.
2. **GitHub Security Advisories:** Use the "Report a vulnerability" button in the Security tab of this repository.

### What to include

- Description of the vulnerability
- Steps to reproduce
- Affected version(s)
- Potential impact
- Suggested fix (if any)

### Response timeline

- **Acknowledgment:** Within 48 hours of report
- **Assessment:** Within 5 business days
- **Fix release:** As soon as possible after verification, typically within 2 weeks for critical issues

## Security Measures

This plugin implements the following security practices:

### Server-Side (PHP)

- **SQL Injection Prevention:** All database queries use `$wpdb->prepare()` with typed placeholders (`%s`, `%d`, `%f`).
- **CSRF Protection:** WordPress nonces (`wp_nonce_field`, `check_admin_referer`, `check_ajax_referer`) on all form submissions and AJAX mutations.
- **Authorization:** `current_user_can('manage_options')` checks on all admin actions.
- **Input Sanitization:** `sanitize_text_field()`, `sanitize_textarea_field()`, `esc_url_raw()`, `intval()`, `floatval()` on all user input.
- **Output Escaping:** `esc_html()`, `esc_url()`, `esc_attr()`, `esc_textarea()` on all output.
- **Rate Limiting:** Transient-based rate limiter on rating submissions (10 per minute per user).
- **Data Validation:** Tire existence checks before accepting ratings; regex validation on tire IDs.
- **CSS Injection Prevention:** `sanitize_hex_color()` re-validation at render time for theme color overrides.
- **Clean Uninstall:** All plugin data (tables, options) removed on uninstall.

### Client-Side (JavaScript)

- **HTML Escaping:** `escapeHTML()` applied to all dynamic content before DOM insertion.
- **URL Validation:** Domain allowlists for affiliate links, bundle links, and image URLs with protocol enforcement (HTTPS only).
- **Input Validation:** Regex patterns for search, tire IDs, numeric values, and URLs.
- **Numeric Bounds:** Range clamping on price, warranty, weight, rating, and page values.
- **Path Traversal Prevention:** Checks for `..` and `//` in URL paths.

### HTTP Headers (Comparison Page)

- `Content-Security-Policy` — Restricts script, style, font, and image sources.
- `X-Content-Type-Options: nosniff` — Prevents MIME type sniffing.
- `X-Frame-Options: SAMEORIGIN` — Prevents clickjacking.
- `Referrer-Policy: strict-origin-when-cross-origin` — Limits referrer information.
