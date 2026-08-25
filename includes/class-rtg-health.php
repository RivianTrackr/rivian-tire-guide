<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Notices when the discovery pipeline breaks, instead of waiting for a person
 * to open the page and find out.
 *
 * Everything downstream of the sweep — pricing, coverage, delisting detection
 * — degrades silently when the sweep stops. Every failure this feature has
 * actually had would have been invisible until someone visited the admin: a
 * rotated CJ token failing every request with a 401, a GraphQL schema change
 * erroring every run, WP-Cron simply not firing, a fitment quietly no longer
 * being read to completion. The digest email only fires on success with new
 * tires, so success is loud and failure is mute, which is backwards.
 *
 * This class evaluates the last run's own records — nothing here talks to the
 * network — and emails once when something breaks and once when it recovers.
 * The evaluation is a pure function of the stats, so what counts as broken is
 * testable without a database.
 *
 * It also watches for delistings: a tire the catalog dropped is a fact worth
 * an email, not a badge waiting to be visited.
 *
 * @since 1.73.0
 */
class RTG_Health {

    /** Option holding what has already been alerted, so nothing emails twice. */
    const STATE_OPTION = 'rtg_health_state';

    /** Transient throttling the admin-visit probe. */
    const CHECK_TRANSIENT = 'rtg_health_checked';

    /**
     * Hours without a successful sweep before that is itself the problem.
     *
     * The sweep is daily, so a day and a half of silence is not scheduling
     * drift — the cron is not firing, or every run is dying before it can
     * record anything.
     */
    const DEFAULT_STALE_HOURS = 36;

    /** Seconds between admin-visit probes. */
    const ADMIN_CHECK_INTERVAL = 6 * HOUR_IN_SECONDS;

    // --- Evaluation ---

    /**
     * Judge the last run's records.
     *
     * Pure: everything needed is passed in, nothing is fetched. Issues come
     * back keyed by a stable code so the alert state can tell "still broken
     * the same way" from "broken in a new way" — only the second emails.
     *
     * @param array|false $stats       Last run's stats from RTG_Catalog_Sync.
     * @param array       $guide_sizes Canonical sizes the guide stocks.
     * @param int         $now         Unix time to judge staleness against.
     * @param int         $stale_hours Hours of silence that count as broken.
     * @return array Issue code => human-readable description.
     */
    public static function evaluate( $stats, $guide_sizes, $now, $stale_hours = self::DEFAULT_STALE_HOURS ) {
        $issues = array();

        // Never ran at all is setup, not breakage — the admin page says so.
        if ( empty( $stats ) || ! is_array( $stats ) ) {
            return $issues;
        }

        $last_run = strtotime( (string) ( $stats['time'] ?? '' ) );

        if ( $last_run && ( $now - $last_run ) > ( $stale_hours * HOUR_IN_SECONDS ) ) {
            $issues['sweep_stale'] = sprintf(
                'The last discovery run was %s ago, against a daily schedule. Either the schedule is not firing or runs are dying before they can record anything. If the site relies on WP-Cron, a real server cron hitting wp-cron.php is the reliable fix.',
                human_time_diff( $last_run, $now )
            );

            // Everything below describes that stale run; repeating it as
            // separate problems would bury the one that matters.
            return $issues;
        }

        $error_text = '';
        foreach ( (array) ( $stats['errors'] ?? array() ) as $error ) {
            $error_text .= ' ' . (string) ( $error['message'] ?? '' );
        }

        // A rejected token outranks the generic failure it also causes: it
        // has a specific fix (the PAT was rotated — update wp-config or the
        // setting) and it will never heal on its own.
        if ( false !== stripos( $error_text, 'token was rejected' )
            || false !== stripos( $error_text, 'HTTP 401' )
            || false !== stripos( $error_text, 'HTTP 403' ) ) {
            $issues['auth_rejected'] = 'CJ rejected the access token on the last run. This usually means the PAT was rotated without updating RTG_CJ_PAT in wp-config.php (or the settings field). Every sweep will fail until it is updated.';
        } elseif ( 'error' === ( $stats['status'] ?? '' ) ) {
            $issues['sweep_failed'] = sprintf(
                'The last discovery run failed: %s',
                trim( $error_text ) !== '' ? trim( $error_text ) : (string) ( $stats['message'] ?? 'no reason recorded' )
            );
        }

        if ( ! isset( $issues['auth_rejected'] ) && ! isset( $issues['sweep_failed'] )
            && 0 === intval( $stats['fetched'] ?? 0 ) ) {
            $issues['nothing_fetched'] = 'The last discovery run completed but read zero products. The source may be misconfigured or returning an empty catalog.';
        }

        // Fitments no longer read completely. Coverage, "no retailer match"
        // and delisting detection all rest on complete reads; when one
        // regresses they all quietly stop meaning anything for that size.
        $read = RTG_Catalog_Presence::fully_read_sizes( $stats );

        // Judged only when the run recorded coverage at all. A fixture-only
        // run, or stats written before coverage existed, would otherwise flag
        // every guide size as partial. Testing "at least one size was read
        // completely" instead — as the first version did — silenced the worst
        // case: every fitment regressing at once.
        $has_coverage_data = false;
        foreach ( (array) ( $stats['sources'] ?? array() ) as $source ) {
            if ( ! empty( $source['coverage'] ) ) {
                $has_coverage_data = true;
                break;
            }
        }

        $partial = array();
        foreach ( (array) $guide_sizes as $size ) {
            $normalized = RTG_Tire_Qualifier::normalize_size( $size );
            if ( '' !== $normalized && empty( $read[ $normalized ] ) ) {
                $partial[] = $normalized;
            }
        }

        // Only meaningful when the run otherwise worked — a failed run reads
        // nothing completely, and that is already reported above.
        if ( ! empty( $partial ) && empty( $issues ) && $has_coverage_data ) {
            $issues['coverage_partial'] = sprintf(
                'The last sweep did not read %s completely. Coverage and delisting detection are blind for %s until a run reaches %s.',
                implode( ', ', $partial ),
                1 === count( $partial ) ? 'that fitment' : 'those fitments',
                1 === count( $partial ) ? 'it' : 'them'
            );
        }

        return $issues;
    }

    // --- Orchestration ---

    /**
     * Compare the current issues against what was already alerted, and email
     * only the difference.
     *
     * Broken stays broken for days; the email should arrive once at the start
     * and once at the end, not daily in between.
     *
     * @return array The issues currently standing.
     */
    public static function check() {
        $settings = get_option( 'rtg_settings', array() );

        if ( isset( $settings['health_alerts_enabled'] ) && ! $settings['health_alerts_enabled'] ) {
            return array();
        }

        $issues = self::evaluate(
            RTG_Catalog_Sync::get_stats(),
            RTG_Admin::get_dropdown_options( 'sizes' ),
            current_time( 'timestamp' )
        );

        $state    = get_option( self::STATE_OPTION, array() );
        $alerted  = (array) ( $state['issues'] ?? array() );

        $new      = array_diff_key( $issues, $alerted );
        $resolved = array_diff_key( $alerted, $issues );

        if ( ! empty( $new ) ) {
            RTG_Mailer::send_health_alert( $issues );
        } elseif ( ! empty( $resolved ) && empty( $issues ) ) {
            RTG_Mailer::send_health_recovered();
        }

        $state['issues']     = $issues;
        $state['checked_at'] = current_time( 'mysql' );
        update_option( self::STATE_OPTION, $state, false );

        return $issues;
    }

    /**
     * Email tires the catalog has dropped since the last look.
     *
     * The badge on the Affiliate Links page answers the question when it is
     * asked; this answers it when it happens.
     */
    public static function watch_delistings() {
        $settings = get_option( 'rtg_settings', array() );

        if ( isset( $settings['health_alerts_enabled'] ) && ! $settings['health_alerts_enabled'] ) {
            return;
        }

        $presence = RTG_Catalog_Presence::evaluate(
            RTG_Database::get_all_tires(),
            RTG_Candidates::get_by_match_key(),
            RTG_Catalog_Presence::fully_read_sizes( RTG_Catalog_Sync::get_stats() ?: array() ),
            current_time( 'timestamp' )
        );

        $delisted = array();
        foreach ( $presence as $tire_id => $entry ) {
            if ( RTG_Catalog_Presence::STATUS_DELISTED === ( $entry['status'] ?? '' ) ) {
                $delisted[ $tire_id ] = $entry;
            }
        }

        $state = get_option( self::STATE_OPTION, array() );
        $known = (array) ( $state['delisted'] ?? array() );

        $new_ids = array_diff( array_keys( $delisted ), $known );

        if ( ! empty( $new_ids ) ) {
            $rows = array();
            foreach ( RTG_Database::get_all_tires() as $tire ) {
                $tire_id = (string) $tire['tire_id'];
                if ( in_array( $tire_id, $new_ids, true ) ) {
                    $rows[] = array(
                        'brand' => $tire['brand'] ?? '',
                        'model' => $tire['model'] ?? '',
                        'size'  => $tire['size'] ?? '',
                        'label' => $delisted[ $tire_id ]['label'] ?? '',
                    );
                }
            }

            RTG_Mailer::send_delisting_notification( $rows );
        }

        // Stored as the current set, not a union, so a tire that comes back
        // and is dropped again alerts again — that second drop is as much
        // news as the first.
        $state['delisted'] = array_keys( $delisted );
        update_option( self::STATE_OPTION, $state, false );
    }

    /**
     * Runs on the daily sync's cron hook, after the sync itself.
     */
    public static function after_sync() {
        self::check();
        self::watch_delistings();
    }

    /**
     * Runs on admin visits, throttled.
     *
     * This is what catches the cron not firing at all: the sync's own hook
     * can't report a schedule that never triggers it. If nobody visits the
     * admin either, nothing in-process can run — which is why the staleness
     * message recommends a real server cron.
     */
    public static function admin_probe() {
        if ( wp_doing_ajax() || get_transient( self::CHECK_TRANSIENT ) ) {
            return;
        }

        set_transient( self::CHECK_TRANSIENT, 1, self::ADMIN_CHECK_INTERVAL );
        self::check();
    }
}
