<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RTG_Price_Fetcher — Automated price extraction from affiliate link URLs.
 *
 * Fetches product pages via wp_remote_get() and attempts to extract the current
 * price using site-specific parsers for known retailers, with a generic fallback
 * that checks structured data (JSON-LD, meta tags) and common HTML patterns.
 *
 * Limitations / caveats:
 *  - Many retailers serve JS-rendered pages; server-side fetches may not see the
 *    final DOM. Structured data in the <head> is the most reliable signal.
 *  - Sites may block automated requests (CAPTCHAs, rate limits, bot detection).
 *  - Affiliate redirect URLs (Commission Junction, ShareASale, etc.) require
 *    following redirects to reach the actual product page.
 *  - HTML selectors can change at any time; parsers may need periodic updates.
 */
class RTG_Price_Fetcher {

    /** WordPress cron hook name. */
    const CRON_HOOK = 'rtg_price_fetch_cron';

    /** Custom cron interval name. */
    const CRON_INTERVAL = 'rtg_weekly';

    /** Option key for the fetch log. */
    const LOG_OPTION = 'rtg_price_fetch_log';

    /** Maximum tires to process per cron run (avoid timeouts). */
    const BATCH_SIZE = 20;

    /** HTTP request timeout in seconds per URL. */
    const REQUEST_TIMEOUT = 15;

    // ------------------------------------------------------------------
    // Cron scheduling
    // ------------------------------------------------------------------

    /**
     * Register the custom weekly interval and the cron action hook.
     * Called during plugins_loaded.
     */
    public static function init() {
        add_filter( 'cron_schedules', array( __CLASS__, 'add_weekly_schedule' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'run_cron' ) );
    }

    /**
     * Add a 'weekly' interval to WP-Cron.
     */
    public static function add_weekly_schedule( $schedules ) {
        if ( ! isset( $schedules[ self::CRON_INTERVAL ] ) ) {
            $schedules[ self::CRON_INTERVAL ] = array(
                'interval' => 7 * DAY_IN_SECONDS,
                'display'  => __( 'Once Weekly', 'rivian-tire-guide' ),
            );
        }
        return $schedules;
    }

    /**
     * Schedule the weekly cron event if not already scheduled.
     * Called on plugin activation.
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
        }
    }

    /**
     * Unschedule the cron event.
     * Called on plugin deactivation.
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    // ------------------------------------------------------------------
    // Execution
    // ------------------------------------------------------------------

    /**
     * WP-Cron callback — fetch prices for all tires that have an affiliate link.
     */
    public static function run_cron() {
        self::fetch_all_prices();
    }

    /**
     * Fetch prices for all tires with affiliate links.
     *
     * @return array Summary: [ 'updated' => int, 'failed' => int, 'skipped' => int ]
     */
    public static function fetch_all_prices() {
        global $wpdb;
        $table = RTG_Database::tires_table_public();

        $tires = $wpdb->get_results(
            "SELECT tire_id, link, price, fetched_price FROM {$table} WHERE link != '' ORDER BY id ASC",
            ARRAY_A
        );

        if ( empty( $tires ) ) {
            self::save_log( array(
                'run_at'  => current_time( 'mysql' ),
                'updated' => 0,
                'failed'  => 0,
                'skipped' => 0,
                'details' => array(),
            ) );
            return array( 'updated' => 0, 'failed' => 0, 'skipped' => 0 );
        }

        $updated = 0;
        $failed  = 0;
        $skipped = 0;
        $details = array();

        foreach ( $tires as $tire ) {
            $link = $tire['link'];

            if ( empty( $link ) ) {
                $skipped++;
                continue;
            }

            $result = self::fetch_price_from_url( $link );

            if ( $result['success'] && $result['price'] > 0 ) {
                $wpdb->update(
                    $table,
                    array(
                        'fetched_price'      => $result['price'],
                        'price_updated_at'   => current_time( 'mysql' ),
                        'price_fetch_status' => 'success',
                    ),
                    array( 'tire_id' => $tire['tire_id'] ),
                    array( '%f', '%s', '%s' ),
                    array( '%s' )
                );
                $updated++;
                $details[] = array(
                    'tire_id' => $tire['tire_id'],
                    'status'  => 'success',
                    'price'   => $result['price'],
                    'source'  => $result['source'],
                );
            } else {
                // Mark as failed but don't clear existing fetched_price.
                $wpdb->update(
                    $table,
                    array(
                        'price_fetch_status' => 'failed',
                        'price_updated_at'   => current_time( 'mysql' ),
                    ),
                    array( 'tire_id' => $tire['tire_id'] ),
                    array( '%s', '%s' ),
                    array( '%s' )
                );
                $failed++;
                $details[] = array(
                    'tire_id' => $tire['tire_id'],
                    'status'  => 'failed',
                    'error'   => $result['error'] ?? 'No price found',
                );
            }
        }

        // Flush the tire cache so frontend sees updated prices.
        RTG_Database::flush_cache();

        $log = array(
            'run_at'  => current_time( 'mysql' ),
            'updated' => $updated,
            'failed'  => $failed,
            'skipped' => $skipped,
            'details' => $details,
        );
        self::save_log( $log );

        return array( 'updated' => $updated, 'failed' => $failed, 'skipped' => $skipped );
    }

    /**
     * Fetch the price for a single tire by tire_id.
     *
     * @param string $tire_id
     * @return array [ 'success' => bool, 'price' => float|null, 'error' => string|null ]
     */
    public static function fetch_single_price( $tire_id ) {
        global $wpdb;
        $table = RTG_Database::tires_table_public();

        $tire = $wpdb->get_row(
            $wpdb->prepare( "SELECT tire_id, link FROM {$table} WHERE tire_id = %s", $tire_id ),
            ARRAY_A
        );

        if ( ! $tire || empty( $tire['link'] ) ) {
            return array( 'success' => false, 'price' => null, 'error' => 'No affiliate link' );
        }

        $result = self::fetch_price_from_url( $tire['link'] );

        if ( $result['success'] && $result['price'] > 0 ) {
            $wpdb->update(
                $table,
                array(
                    'fetched_price'      => $result['price'],
                    'price_updated_at'   => current_time( 'mysql' ),
                    'price_fetch_status' => 'success',
                ),
                array( 'tire_id' => $tire_id ),
                array( '%f', '%s', '%s' ),
                array( '%s' )
            );
            RTG_Database::flush_cache();
        } else {
            $wpdb->update(
                $table,
                array(
                    'price_fetch_status' => 'failed',
                    'price_updated_at'   => current_time( 'mysql' ),
                ),
                array( 'tire_id' => $tire_id ),
                array( '%s', '%s' ),
                array( '%s' )
            );
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Price extraction
    // ------------------------------------------------------------------

    /**
     * Attempt to extract a price from a URL.
     *
     * @param string $url The affiliate or product URL.
     * @return array [ 'success' => bool, 'price' => float|null, 'source' => string, 'error' => string|null ]
     */
    public static function fetch_price_from_url( $url ) {
        $url = esc_url_raw( $url );

        if ( empty( $url ) ) {
            return array( 'success' => false, 'price' => null, 'source' => '', 'error' => 'Empty URL' );
        }

        // Fetch the page HTML.
        $response = wp_remote_get( $url, array(
            'timeout'    => self::REQUEST_TIMEOUT,
            'user-agent' => 'Mozilla/5.0 (compatible; RivianTireGuide/1.0; +https://riviantrackr.com)',
            'headers'    => array(
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ),
            'redirection' => 5,
            'sslverify'   => true,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'price'   => null,
                'source'  => '',
                'error'   => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code < 200 || $status_code >= 400 ) {
            return array(
                'success' => false,
                'price'   => null,
                'source'  => '',
                'error'   => 'HTTP ' . $status_code,
            );
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) {
            return array(
                'success' => false,
                'price'   => null,
                'source'  => '',
                'error'   => 'Empty response body',
            );
        }

        // Determine the final URL (after redirects).
        $final_url = self::get_final_url( $response, $url );
        $domain    = self::extract_domain( $final_url );

        // Try site-specific parsers first.
        $site_parsers = array(
            'tirerack.com'   => 'parse_tirerack',
            'simpletire.com' => 'parse_simpletire',
            'amazon.com'     => 'parse_amazon',
            'walmart.com'    => 'parse_walmart',
            'discounttire.com' => 'parse_generic_structured',
        );

        foreach ( $site_parsers as $site_domain => $method ) {
            if ( strpos( $domain, $site_domain ) !== false && method_exists( __CLASS__, $method ) ) {
                $price = call_user_func( array( __CLASS__, $method ), $html );
                if ( $price > 0 ) {
                    return array(
                        'success' => true,
                        'price'   => $price,
                        'source'  => $site_domain,
                        'error'   => null,
                    );
                }
            }
        }

        // Fallback: generic structured data extraction.
        $price = self::parse_generic_structured( $html );
        if ( $price > 0 ) {
            return array(
                'success' => true,
                'price'   => $price,
                'source'  => $domain . ' (structured data)',
                'error'   => null,
            );
        }

        // Last resort: regex patterns for common price HTML.
        $price = self::parse_generic_html( $html );
        if ( $price > 0 ) {
            return array(
                'success' => true,
                'price'   => $price,
                'source'  => $domain . ' (html pattern)',
                'error'   => null,
            );
        }

        return array(
            'success' => false,
            'price'   => null,
            'source'  => $domain,
            'error'   => 'No price found on page',
        );
    }

    // ------------------------------------------------------------------
    // Site-specific parsers
    // ------------------------------------------------------------------

    /**
     * TireRack.com — Extract price from structured data or known HTML patterns.
     */
    private static function parse_tirerack( $html ) {
        // Try JSON-LD first.
        $price = self::extract_jsonld_price( $html );
        if ( $price > 0 ) {
            return $price;
        }

        // TireRack meta tag: <meta itemprop="price" content="199.99">
        if ( preg_match( '/<meta\s+[^>]*itemprop=["\']price["\'][^>]*content=["\']([0-9]+\.?[0-9]*)["\'][^>]*>/i', $html, $m ) ) {
            return self::sanitize_price( $m[1] );
        }

        // Fallback to common patterns.
        return self::parse_generic_structured( $html );
    }

    /**
     * SimpleTire.com — Extract price from structured data or meta tags.
     */
    private static function parse_simpletire( $html ) {
        // Try JSON-LD first.
        $price = self::extract_jsonld_price( $html );
        if ( $price > 0 ) {
            return $price;
        }

        // og:price:amount
        $price = self::extract_og_price( $html );
        if ( $price > 0 ) {
            return $price;
        }

        return self::parse_generic_structured( $html );
    }

    /**
     * Amazon.com — Extract price from various Amazon price selectors.
     */
    private static function parse_amazon( $html ) {
        // JSON-LD Product data.
        $price = self::extract_jsonld_price( $html );
        if ( $price > 0 ) {
            return $price;
        }

        // Amazon-specific: <span class="a-price-whole">199</span><span class="a-price-fraction">99</span>
        if ( preg_match( '/<span[^>]*class="a-price-whole"[^>]*>([0-9,]+)<\/span>.*?<span[^>]*class="a-price-fraction"[^>]*>([0-9]+)<\/span>/s', $html, $m ) ) {
            $whole    = str_replace( ',', '', $m[1] );
            $fraction = $m[2];
            return self::sanitize_price( $whole . '.' . $fraction );
        }

        // Amazon: <span id="priceblock_ourprice" ...>$199.99</span>
        if ( preg_match( '/<span[^>]*id="priceblock_(?:ourprice|dealprice|saleprice)"[^>]*>\s*\$([0-9,]+\.?[0-9]*)\s*<\/span>/i', $html, $m ) ) {
            return self::sanitize_price( str_replace( ',', '', $m[1] ) );
        }

        // Amazon: <span class="a-offscreen">$199.99</span> (inside price container)
        if ( preg_match( '/<span[^>]*class="a-offscreen"[^>]*>\$([0-9,]+\.?[0-9]*)<\/span>/i', $html, $m ) ) {
            return self::sanitize_price( str_replace( ',', '', $m[1] ) );
        }

        return 0;
    }

    /**
     * Walmart.com — Extract price from structured data.
     */
    private static function parse_walmart( $html ) {
        $price = self::extract_jsonld_price( $html );
        if ( $price > 0 ) {
            return $price;
        }

        return self::parse_generic_structured( $html );
    }

    // ------------------------------------------------------------------
    // Generic parsers
    // ------------------------------------------------------------------

    /**
     * Extract price from structured data: JSON-LD, meta itemprop, Open Graph.
     */
    private static function parse_generic_structured( $html ) {
        // 1. JSON-LD Product schema.
        $price = self::extract_jsonld_price( $html );
        if ( $price > 0 ) {
            return $price;
        }

        // 2. <meta itemprop="price" content="...">
        if ( preg_match( '/<meta\s+[^>]*itemprop=["\']price["\'][^>]*content=["\']([0-9]+\.?[0-9]*)["\'][^>]*>/i', $html, $m ) ) {
            return self::sanitize_price( $m[1] );
        }
        // Also check reversed attribute order.
        if ( preg_match( '/<meta\s+[^>]*content=["\']([0-9]+\.?[0-9]*)["\'][^>]*itemprop=["\']price["\'][^>]*>/i', $html, $m ) ) {
            return self::sanitize_price( $m[1] );
        }

        // 3. <span itemprop="price" content="...">
        if ( preg_match( '/<(?:span|div|p)[^>]*itemprop=["\']price["\'][^>]*content=["\']([0-9]+\.?[0-9]*)["\'][^>]*>/i', $html, $m ) ) {
            return self::sanitize_price( $m[1] );
        }

        // 4. Open Graph product price.
        $price = self::extract_og_price( $html );
        if ( $price > 0 ) {
            return $price;
        }

        return 0;
    }

    /**
     * Last-resort: look for common price HTML patterns.
     *
     * This is the least reliable method and may match non-tire prices (shipping,
     * related products, etc.), so we apply some heuristic bounds.
     */
    private static function parse_generic_html( $html ) {
        $candidates = array();

        // Pattern: class containing "price" with a dollar amount inside.
        if ( preg_match_all( '/<[^>]*class="[^"]*price[^"]*"[^>]*>\s*\$?([0-9,]+\.?[0-9]*)\s*</i', $html, $matches ) ) {
            foreach ( $matches[1] as $m ) {
                $val = self::sanitize_price( str_replace( ',', '', $m ) );
                if ( $val > 0 ) {
                    $candidates[] = $val;
                }
            }
        }

        // Pattern: data-price="123.45" attribute.
        if ( preg_match_all( '/data-price=["\']([0-9,]+\.?[0-9]*)["\']/', $html, $matches ) ) {
            foreach ( $matches[1] as $m ) {
                $val = self::sanitize_price( str_replace( ',', '', $m ) );
                if ( $val > 0 ) {
                    $candidates[] = $val;
                }
            }
        }

        if ( empty( $candidates ) ) {
            return 0;
        }

        // Heuristic: filter to plausible tire price range ($30–$1500).
        $plausible = array_filter( $candidates, function ( $p ) {
            return $p >= 30 && $p <= 1500;
        } );

        if ( ! empty( $plausible ) ) {
            // Return the first plausible price (most likely the primary product price).
            return reset( $plausible );
        }

        return 0;
    }

    // ------------------------------------------------------------------
    // Structured data helpers
    // ------------------------------------------------------------------

    /**
     * Extract price from JSON-LD Product / Offer schema.
     */
    private static function extract_jsonld_price( $html ) {
        // Find all <script type="application/ld+json"> blocks.
        if ( ! preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches ) ) {
            return 0;
        }

        foreach ( $matches[1] as $json_str ) {
            $data = json_decode( trim( $json_str ), true );
            if ( ! is_array( $data ) ) {
                continue;
            }

            // Handle @graph arrays.
            $items = isset( $data['@graph'] ) ? $data['@graph'] : array( $data );

            foreach ( $items as $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }

                $type = $item['@type'] ?? '';
                if ( $type !== 'Product' && $type !== 'IndividualProduct' ) {
                    continue;
                }

                // Direct price on the Product.
                if ( isset( $item['offers'] ) ) {
                    $price = self::extract_offer_price( $item['offers'] );
                    if ( $price > 0 ) {
                        return $price;
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Extract price from an offers object (single Offer or AggregateOffer).
     */
    private static function extract_offer_price( $offers ) {
        // Single offer object.
        if ( isset( $offers['price'] ) ) {
            return self::sanitize_price( $offers['price'] );
        }

        // lowPrice on AggregateOffer.
        if ( isset( $offers['lowPrice'] ) ) {
            return self::sanitize_price( $offers['lowPrice'] );
        }

        // Array of offers — take the first valid price.
        if ( isset( $offers[0] ) && is_array( $offers[0] ) ) {
            foreach ( $offers as $offer ) {
                if ( isset( $offer['price'] ) ) {
                    $price = self::sanitize_price( $offer['price'] );
                    if ( $price > 0 ) {
                        return $price;
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Extract price from Open Graph product:price:amount meta tag.
     */
    private static function extract_og_price( $html ) {
        if ( preg_match( '/<meta[^>]*property=["\']product:price:amount["\'][^>]*content=["\']([0-9]+\.?[0-9]*)["\'][^>]*>/i', $html, $m ) ) {
            return self::sanitize_price( $m[1] );
        }
        // Reversed attribute order.
        if ( preg_match( '/<meta[^>]*content=["\']([0-9]+\.?[0-9]*)["\'][^>]*property=["\']product:price:amount["\'][^>]*>/i', $html, $m ) ) {
            return self::sanitize_price( $m[1] );
        }
        return 0;
    }

    // ------------------------------------------------------------------
    // Utilities
    // ------------------------------------------------------------------

    /**
     * Sanitize and validate an extracted price value.
     *
     * @param mixed $value Raw price string or number.
     * @return float Sanitized price, or 0 if invalid.
     */
    private static function sanitize_price( $value ) {
        $price = floatval( str_replace( ',', '', (string) $value ) );

        // Basic sanity: must be positive and within a reasonable tire price range.
        if ( $price <= 0 || $price > 2000 ) {
            return 0;
        }

        return round( $price, 2 );
    }

    /**
     * Get the final URL after redirects from a wp_remote_get response.
     */
    private static function get_final_url( $response, $original_url ) {
        // Check for the 'x-redirect-to' header or the response URL.
        $redirects = wp_remote_retrieve_header( $response, 'x-redirect-to' );
        if ( ! empty( $redirects ) ) {
            return is_array( $redirects ) ? end( $redirects ) : $redirects;
        }

        // wp_remote_get with redirection param follows redirects automatically.
        // The final URL isn't directly available, so check Location header.
        $location = wp_remote_retrieve_header( $response, 'location' );
        if ( ! empty( $location ) ) {
            return is_array( $location ) ? end( $location ) : $location;
        }

        return $original_url;
    }

    /**
     * Extract the registrable domain from a URL.
     */
    private static function extract_domain( $url ) {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) {
            return '';
        }
        // Strip www. prefix.
        return preg_replace( '/^www\./', '', strtolower( $host ) );
    }

    // ------------------------------------------------------------------
    // Logging
    // ------------------------------------------------------------------

    /**
     * Save the most recent fetch log (keeps last 10 runs).
     */
    private static function save_log( $entry ) {
        $logs = get_option( self::LOG_OPTION, array() );
        if ( ! is_array( $logs ) ) {
            $logs = array();
        }

        array_unshift( $logs, $entry );

        // Keep only the last 10 runs.
        $logs = array_slice( $logs, 0, 10 );

        update_option( self::LOG_OPTION, $logs, false );
    }

    /**
     * Get the fetch log history.
     *
     * @return array
     */
    public static function get_log() {
        $logs = get_option( self::LOG_OPTION, array() );
        return is_array( $logs ) ? $logs : array();
    }

    /**
     * Get the next scheduled run time.
     *
     * @return int|false Unix timestamp or false if not scheduled.
     */
    public static function get_next_run() {
        return wp_next_scheduled( self::CRON_HOOK );
    }
}
