<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Checks affiliate links for broken redirects (e.g. redirecting to
 * the supplier homepage instead of a product page).
 *
 * Results are stored in the rtg_link_check_results option and
 * surfaced in the admin Affiliate Links page.
 *
 * @since 1.24.0
 */
class RTG_Link_Checker {

    /** WP-Cron hook name. */
    const CRON_HOOK = 'rtg_link_check';

    /** Option key where results are persisted. */
    const RESULTS_OPTION = 'rtg_link_check_results';

    /** Maximum number of links to check per run (avoids timeouts). */
    const BATCH_SIZE = 50;

    /** HTTP request timeout in seconds per link. */
    const REQUEST_TIMEOUT = 15;

    /**
     * Schedule the weekly cron event if not already scheduled.
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'weekly', self::CRON_HOOK );
        }
    }

    /**
     * Remove the scheduled cron event on plugin deactivation.
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Run the link health check.
     *
     * Fetches all tires with a non-empty purchase link, sends an HTTP
     * request to each, and flags links that appear broken.
     *
     * A link is considered "broken" when:
     * - It returns an HTTP error (4xx/5xx).
     * - It redirects to what looks like the supplier homepage (path is "/" or empty).
     * - The request times out or fails entirely.
     *
     * @return array The full results array that was saved.
     */
    public static function run() {
        $tires = RTG_Database::get_tires_for_link_management( 'all', '' );

        $results = array(
            'checked_at' => current_time( 'mysql' ),
            'total'      => 0,
            'broken'     => array(),
        );

        $checked = 0;

        foreach ( $tires as $tire ) {
            if ( empty( $tire['link'] ) ) {
                continue;
            }

            if ( $checked >= self::BATCH_SIZE ) {
                break;
            }

            $checked++;
            $status = self::check_single_link( $tire['link'] );

            if ( 'ok' !== $status['status'] ) {
                $results['broken'][] = array(
                    'tire_id' => $tire['tire_id'],
                    'brand'   => $tire['brand'],
                    'model'   => $tire['model'],
                    'url'     => $tire['link'],
                    'status'  => $status['status'],
                    'reason'  => $status['reason'],
                    'http'    => $status['http_code'],
                );
            }
        }

        $results['total'] = $checked;

        update_option( self::RESULTS_OPTION, $results, false );

        // Send admin email if broken links were found.
        if ( ! empty( $results['broken'] ) ) {
            RTG_Mailer::send_broken_links_notification( $results );
        }

        return $results;
    }

    /**
     * Check a single URL for redirect-to-homepage or error status.
     *
     * @param string $url The URL to check.
     * @return array {status, reason, http_code}
     */
    public static function check_single_link( $url ) {
        $response = wp_remote_head( $url, array(
            'timeout'     => self::REQUEST_TIMEOUT,
            'redirection' => 10,
            'sslverify'   => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'status'    => 'error',
                'reason'    => $response->get_error_message(),
                'http_code' => 0,
            );
        }

        $http_code = wp_remote_retrieve_response_code( $response );

        // Server errors or not-found.
        if ( $http_code >= 400 ) {
            return array(
                'status'    => 'http_error',
                'reason'    => 'HTTP ' . $http_code,
                'http_code' => $http_code,
            );
        }

        // Check if we were redirected to a homepage.
        // wp_remote_head follows redirects, so we check the effective URL
        // from the response headers or the redirect history.
        $redirect_url = self::get_effective_url( $response, $url );

        if ( self::is_homepage( $redirect_url, $url ) ) {
            return array(
                'status'    => 'redirect_homepage',
                'reason'    => 'Redirects to homepage: ' . $redirect_url,
                'http_code' => $http_code,
            );
        }

        return array(
            'status'    => 'ok',
            'reason'    => '',
            'http_code' => $http_code,
        );
    }

    /**
     * Determine the effective (final) URL after redirects.
     *
     * WordPress's HTTP API doesn't always expose the final URL directly,
     * so we fall back to the Location header from a non-following request
     * if needed.
     *
     * @param array|WP_Error $response The wp_remote response.
     * @param string         $original The original URL.
     * @return string The effective URL.
     */
    private static function get_effective_url( $response, $original ) {
        // Check for X-Redirect-By or Location in the last response.
        $location = wp_remote_retrieve_header( $response, 'location' );
        if ( ! empty( $location ) ) {
            // If it's a relative URL, resolve it.
            if ( strpos( $location, 'http' ) !== 0 ) {
                $parsed = wp_parse_url( $original );
                $location = $parsed['scheme'] . '://' . $parsed['host'] . $location;
            }
            return $location;
        }

        // If wp_remote_head followed redirects transparently, we can try
        // a non-following request to see where it leads.
        $probe = wp_remote_head( $original, array(
            'timeout'     => self::REQUEST_TIMEOUT,
            'redirection' => 0,
            'sslverify'   => false,
        ) );

        if ( ! is_wp_error( $probe ) ) {
            $code = wp_remote_retrieve_response_code( $probe );
            if ( $code >= 300 && $code < 400 ) {
                $loc = wp_remote_retrieve_header( $probe, 'location' );
                if ( ! empty( $loc ) ) {
                    if ( strpos( $loc, 'http' ) !== 0 ) {
                        $parsed = wp_parse_url( $original );
                        $loc = $parsed['scheme'] . '://' . $parsed['host'] . $loc;
                    }
                    return $loc;
                }
            }
        }

        return $original;
    }

    /**
     * Determine whether a URL looks like a homepage.
     *
     * Compares the path of the effective URL — if it's "/" or empty,
     * and the original URL had a longer path, we flag it.
     *
     * @param string $effective_url The URL we ended up at.
     * @param string $original_url  The URL we started with.
     * @return bool
     */
    private static function is_homepage( $effective_url, $original_url ) {
        $effective_parsed = wp_parse_url( $effective_url );
        $original_parsed  = wp_parse_url( $original_url );

        $effective_path = trim( $effective_parsed['path'] ?? '', '/' );
        $original_path  = trim( $original_parsed['path'] ?? '', '/' );

        // If the original URL already pointed to "/", don't flag it.
        if ( empty( $original_path ) ) {
            return false;
        }

        // Effective URL lands on "/" — looks like a homepage redirect.
        if ( empty( $effective_path ) ) {
            return true;
        }

        // Some suppliers redirect to a regional homepage like "/en/" or "/us/".
        // Flag short paths (1-3 chars) when the original was longer.
        if ( strlen( $effective_path ) <= 3 && strlen( $original_path ) > 3 ) {
            return true;
        }

        return false;
    }

    /**
     * Get the most recent check results.
     *
     * @return array|false Results array or false if never checked.
     */
    public static function get_results() {
        return get_option( self::RESULTS_OPTION, false );
    }

    /**
     * Get a set of tire IDs that were flagged as broken in the last check.
     *
     * @return array Associative array of tire_id => reason.
     */
    public static function get_broken_tire_ids() {
        $results = self::get_results();
        if ( empty( $results['broken'] ) ) {
            return array();
        }

        $map = array();
        foreach ( $results['broken'] as $entry ) {
            $map[ $entry['tire_id'] ] = $entry['reason'];
        }
        return $map;
    }
}
