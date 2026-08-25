<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Surfaces the prices that only a person can refresh, before they rot.
 *
 * Covered tires re-price themselves daily. The rest — the ones the affiliate
 * feed doesn't carry — update only when someone edits them, and nothing was
 * measuring how long ago that was. A stale price isn't a broken page or a
 * failed run, so neither the link checker nor the health alerts would ever
 * mention it; it just quietly diverges from what the reader pays.
 *
 * Monthly, deliberately: prices drift over weeks, not hours, and a monthly
 * list of a dozen tires is a checklist where a daily one is a nag.
 *
 * @since 1.74.0
 */
class RTG_Stale_Prices {

    /** WP-Cron hook name. */
    const CRON_HOOK = 'rtg_stale_price_report';

    /** Days without a price touch before a tire makes the list. */
    const DEFAULT_STALE_DAYS = 90;

    /**
     * Schedule the monthly report if it isn't already scheduled.
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'monthly', self::CRON_HOOK );
        }
    }

    /**
     * Remove the scheduled event on plugin deactivation.
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * When this tire's price was last touched by anything, as best we know.
     *
     * A synced price carries its own timestamp. A manual tire's closest
     * proxy is updated_at — any edit implies a person looked at the row —
     * which errs toward not nagging rather than toward false alarms.
     *
     * @param array $tire Guide tire row.
     * @return int Unix time, or 0 when nothing is known.
     */
    public static function last_price_touch( $tire ) {
        $synced = strtotime( (string) ( $tire['price_synced_at'] ?? '' ) );
        $edited = strtotime( (string) ( $tire['updated_at'] ?? '' ) );

        return max( (int) $synced, (int) $edited );
    }

    /**
     * The tires whose price is stale, oldest first.
     *
     * Pure of the clock and the database: rows and "now" come in, the list
     * comes out.
     *
     * @param array[] $tires      Guide tires.
     * @param int     $now        Unix time.
     * @param int     $stale_days Days without a touch before a tire counts.
     * @return array[] Stale tires, each with a last_touch timestamp added.
     */
    public static function find_stale( $tires, $now, $stale_days = self::DEFAULT_STALE_DAYS ) {
        $cutoff = $now - ( $stale_days * DAY_IN_SECONDS );
        $stale  = array();

        foreach ( (array) $tires as $tire ) {
            if ( floatval( $tire['price'] ?? 0 ) <= 0 ) {
                // A tire with no price has nothing to go stale.
                continue;
            }

            $touched = self::last_price_touch( $tire );

            if ( $touched > 0 && $touched < $cutoff ) {
                $tire['last_touch'] = $touched;
                $stale[]            = $tire;
            }
        }

        usort( $stale, function ( $a, $b ) {
            return $a['last_touch'] <=> $b['last_touch'];
        } );

        return $stale;
    }

    /**
     * Run the monthly report.
     *
     * @return array Statistics for the run.
     */
    public static function run() {
        $settings = get_option( 'rtg_settings', array() );

        if ( isset( $settings['stale_price_report_enabled'] ) && ! $settings['stale_price_report_enabled'] ) {
            return array( 'status' => 'disabled' );
        }

        $stale = self::find_stale(
            RTG_Database::get_all_tires(),
            current_time( 'timestamp' ),
            (int) apply_filters( 'rtg_stale_price_days', self::DEFAULT_STALE_DAYS )
        );

        if ( ! empty( $stale ) ) {
            RTG_Mailer::send_stale_price_report( $stale );
        }

        return array(
            'status' => 'success',
            'stale'  => count( $stale ),
            'time'   => current_time( 'mysql' ),
        );
    }
}
