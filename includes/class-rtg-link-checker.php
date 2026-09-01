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

    /**
     * Option key for the rotation cursor. The weekly run checks BATCH_SIZE
     * links starting here and advances, so a catalog larger than one batch
     * is still fully covered over successive runs instead of the same
     * alphabetically-first batch being re-checked forever.
     */
    const CURSOR_OPTION = 'rtg_link_check_cursor';

    /** Maximum number of links to check per run (avoids timeouts). */
    const BATCH_SIZE = 50;

    /** Number of links per batch when using the progress-based UI. */
    const PROGRESS_BATCH_SIZE = 5;

    /** HTTP request timeout in seconds per link. */
    const REQUEST_TIMEOUT = 15;

    /** Lock held for the duration of a run. */
    const LOCK_NAME = 'link_check';

    /** Seconds after which an unreleased run lock is considered abandoned: a full slice at the request timeout. */
    const LOCK_TTL = self::BATCH_SIZE * ( self::REQUEST_TIMEOUT + 2 );

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
        // The weekly cron and the "Check all links" button must not run at
        // once: both would advance the rotation cursor and the second
        // writer's verdicts would replace the first's.
        if ( ! RTG_Lock::acquire( self::LOCK_NAME, self::LOCK_TTL ) ) {
            return RTG_Lock::busy_result( 'link check' );
        }

        try {
            return self::run_locked();
        } finally {
            RTG_Lock::release( self::LOCK_NAME );
        }
    }

    /**
     * The check itself, once the lock is held.
     *
     * @return array The full results array that was saved.
     */
    private static function run_locked() {
        $linkable = self::get_linkable_tires();
        $count    = count( $linkable );

        $results = array(
            'checked_at' => current_time( 'mysql' ),
            'total'      => 0,
            'link_count' => $count,
            'broken'     => array(),
        );

        if ( 0 === $count ) {
            update_option( self::RESULTS_OPTION, $results, false );
            return $results;
        }

        // This run's slice, starting at the cursor and wrapping.
        $cursor = intval( get_option( self::CURSOR_OPTION, 0 ) ) % $count;
        $batch  = array_slice( $linkable, $cursor, self::BATCH_SIZE );
        if ( count( $batch ) < self::BATCH_SIZE && $count > count( $batch ) ) {
            $remaining = min( self::BATCH_SIZE - count( $batch ), $cursor );
            $batch     = array_merge( $batch, array_slice( $linkable, 0, $remaining ) );
        }

        $outcome = self::check_batch( $batch );

        update_option( self::CURSOR_OPTION, ( $cursor + count( $batch ) ) % $count, false );

        // A rotating run only re-judged its own slice: keep the previous
        // verdicts for links outside it (as long as those tires still carry
        // a link), replace verdicts inside it.
        $batch_ids    = wp_list_pluck( $batch, 'tire_id' );
        $linkable_ids = wp_list_pluck( $linkable, 'tire_id' );
        $previous     = get_option( self::RESULTS_OPTION, array() );
        $kept         = array();
        if ( ! empty( $previous['broken'] ) && is_array( $previous['broken'] ) ) {
            foreach ( $previous['broken'] as $entry ) {
                if ( ! in_array( $entry['tire_id'], $batch_ids, true ) && in_array( $entry['tire_id'], $linkable_ids, true ) ) {
                    $kept[] = $entry;
                }
            }
        }

        $results['broken'] = array_merge( $kept, $outcome['broken'] );
        $results['total']  = count( $batch );

        update_option( self::RESULTS_OPTION, $results, false );

        // Email only for breakage this run actually found — carried-over
        // entries were already reported when their run found them.
        if ( ! empty( $outcome['broken'] ) ) {
            RTG_Mailer::send_broken_links_notification( $results );
        }

        return $results;
    }

    /**
     * Get the list of tires that have a non-empty purchase link.
     *
     * @return array[] Each element has tire_id, brand, model, link.
     */
    public static function get_linkable_tires() {
        $tires    = RTG_Database::get_tires_for_link_management( 'all', '' );
        $linkable = array();

        foreach ( $tires as $tire ) {
            if ( ! empty( $tire['link'] ) ) {
                $linkable[] = array(
                    'tire_id' => $tire['tire_id'],
                    'brand'   => $tire['brand'],
                    'model'   => $tire['model'],
                    'link'    => $tire['link'],
                );
            }
        }

        return $linkable;
    }

    /**
     * Check a batch of tires and return results for that batch only.
     *
     * @param array[] $tires Subset of tires to check (tire_id, brand, model, link).
     * @return array { checked: int, broken: array[] }
     */
    public static function check_batch( $tires ) {
        $broken  = array();
        $checked = 0;

        foreach ( $tires as $tire ) {
            $checked++;
            $status = self::check_single_link( $tire['link'] );

            if ( 'ok' !== $status['status'] ) {
                $broken[] = array(
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

        return array(
            'checked' => $checked,
            'broken'  => $broken,
        );
    }

    /**
     * Save final results and send notification if needed.
     *
     * @param int   $total  Total links checked.
     * @param array $broken All broken link entries.
     * @return array The saved results array.
     */
    public static function save_results( $total, $broken ) {
        $results = array(
            'checked_at' => current_time( 'mysql' ),
            'total'      => $total,
            'link_count' => $total,
            'broken'     => $broken,
        );

        update_option( self::RESULTS_OPTION, $results, false );

        // A manual full check covered everything — the weekly rotation can
        // start over.
        update_option( self::CURSOR_OPTION, 0, false );

        if ( ! empty( $broken ) ) {
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
        // Amazon short links (amzn.to) are excluded from broken link
        // detection — they frequently time out or return misleading
        // status codes, producing false positives.
        $parsed_host = wp_parse_url( $url, PHP_URL_HOST );
        if ( $parsed_host && 'amzn.to' === strtolower( $parsed_host ) ) {
            return array(
                'status'    => 'ok',
                'reason'    => '',
                'http_code' => 0,
            );
        }

        // Use GET instead of HEAD because many affiliate networks
        // (CJ, ShareASale, etc.) only redirect GET requests.
        $response = wp_remote_get( $url, array(
            'timeout'             => self::REQUEST_TIMEOUT,
            'redirection'         => 10,
            'sslverify'           => false,
            'limit_response_size' => 4096,
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
        // WordPress's HTTP API stores the final URL after following redirects
        // in the transport response object — no second request needed.
        if ( isset( $response['http_response'] ) && $response['http_response'] instanceof WP_HTTP_Requests_Response ) {
            $effective = $response['http_response']->get_response_object()->url;
            if ( ! empty( $effective ) ) {
                return $effective;
            }
        }

        // Fallback: check Location header (for edge cases where the transport
        // doesn't expose the final URL).
        $location = wp_remote_retrieve_header( $response, 'location' );
        if ( ! empty( $location ) ) {
            if ( strpos( $location, 'http' ) !== 0 ) {
                $parsed   = wp_parse_url( $original );
                $location = $parsed['scheme'] . '://' . $parsed['host'] . $location;
            }
            return $location;
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
