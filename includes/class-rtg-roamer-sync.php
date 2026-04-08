<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Syncs real-world tire efficiency data from Rivian Roamer.
 *
 * Fetches the tire-efficiency.json feed, matches entries to local tires
 * by brand + model + size, and stores the efficiency data. Ambiguous
 * matches (same brand+model+size with different load ratings) are
 * skipped for manual review.
 *
 * @since 1.29.0
 */
class RTG_Roamer_Sync {

    /** WP-Cron hook name. */
    const CRON_HOOK = 'rtg_roamer_sync';

    /** Default Rivian Roamer feed URL. */
    const DEFAULT_URL = 'https://rivianroamer.com/guides/tire-efficiency.json';

    /** Option key for sync stats. */
    const STATS_OPTION = 'rtg_roamer_sync_stats';

    /** Option key for permanently hidden Roamer tire IDs. */
    const HIDDEN_OPTION = 'rtg_roamer_hidden_ids';

    /** HTTP request timeout in seconds. */
    const REQUEST_TIMEOUT = 15;

    /**
     * Schedule the twicedaily cron event if not already scheduled.
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'twicedaily', self::CRON_HOOK );
        }
    }

    /**
     * Remove the scheduled cron event on plugin deactivation.
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Run the sync. Called by WP-Cron or manually via admin.
     *
     * @return array Sync results with matched, skipped, unmatched counts.
     */
    public static function run() {
        $settings = get_option( 'rtg_settings', array() );

        // Check if sync is enabled (defaults to enabled).
        if ( isset( $settings['roamer_sync_enabled'] ) && ! $settings['roamer_sync_enabled'] ) {
            return array(
                'status'  => 'disabled',
                'message' => 'Roamer sync is disabled in settings.',
            );
        }

        $url = ! empty( $settings['roamer_sync_url'] ) ? $settings['roamer_sync_url'] : self::DEFAULT_URL;

        // Fetch the feed.
        $response = wp_remote_get( $url, array(
            'timeout'   => self::REQUEST_TIMEOUT,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            $result = array(
                'status'  => 'error',
                'message' => $response->get_error_message(),
                'time'    => current_time( 'mysql' ),
            );
            update_option( self::STATS_OPTION, $result, false );
            return $result;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            $result = array(
                'status'  => 'error',
                'message' => 'HTTP ' . $http_code,
                'time'    => current_time( 'mysql' ),
            );
            update_option( self::STATS_OPTION, $result, false );
            return $result;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) || empty( $data['tires'] ) ) {
            $result = array(
                'status'  => 'error',
                'message' => 'Invalid JSON structure or empty tires array.',
                'time'    => current_time( 'mysql' ),
            );
            update_option( self::STATS_OPTION, $result, false );
            return $result;
        }

        // Ensure the vehicle_breakdown column exists before writing.
        // dbDelta can silently fail for TEXT columns on older MySQL.
        self::ensure_breakdown_column();

        // Fetch all local tires.
        $local_tires = RTG_Database::get_all_tires();

        // Build lookup maps.
        // 1. Map roamer_tire_id → local tire_id (for tires already linked).
        $linked_map = array(); // roamer_tire_id => tire_id
        // 2. Map normalized key → array of local tire_ids (for auto-matching).
        $norm_map = array(); // normalized_key => [ tire_id, tire_id, ... ]

        foreach ( $local_tires as $tire ) {
            if ( ! empty( $tire['roamer_tire_id'] ) ) {
                // Support comma-separated roamer_tire_ids (from multi-assign).
                $ids = array_map( 'trim', explode( ',', $tire['roamer_tire_id'] ) );
                foreach ( $ids as $rid ) {
                    if ( ! empty( $rid ) ) {
                        $linked_map[ $rid ] = $tire['tire_id'];
                    }
                }
            }

            $key = self::normalize_key( $tire['brand'], $tire['model'], $tire['size'] );
            if ( ! isset( $norm_map[ $key ] ) ) {
                $norm_map[ $key ] = array();
            }
            $norm_map[ $key ][] = $tire['tire_id'];
        }

        $matched   = 0;
        $skipped   = 0;
        $unmatched = 0;
        $ambiguous_list = array();
        $unmatched_list = array();
        $now = current_time( 'mysql' );

        // Load permanently hidden Roamer IDs so they are excluded from unmatched.
        $hidden_ids = get_option( self::HIDDEN_OPTION, array() );
        $hidden_set = is_array( $hidden_ids ) ? array_flip( $hidden_ids ) : array();

        foreach ( $data['tires'] as $roamer_tire ) {
            $roamer_id = sanitize_text_field( $roamer_tire['tire_id'] ?? '' );
            if ( empty( $roamer_id ) ) {
                continue;
            }

            // Skip permanently hidden tires.
            if ( isset( $hidden_set[ $roamer_id ] ) ) {
                continue;
            }

            // Source value is km/kWh — convert to mi/kWh for storage and display.
            $breakdown_raw = $roamer_tire['vehicle_breakdown'] ?? array();
            $eff_data = array(
                'roamer_efficiency'         => round( floatval( $roamer_tire['efficiency_km_per_kwh'] ?? 0 ) * 0.621371, 2 ),
                'roamer_total_km'           => floatval( $roamer_tire['total_distance_km'] ?? 0 ),
                'roamer_vehicle_count'      => intval( $roamer_tire['vehicle_count'] ?? 0 ),
                'roamer_vehicle_breakdown'  => is_array( $breakdown_raw ) ? wp_json_encode( $breakdown_raw ) : '',
                'roamer_synced_at'          => $now,
            );

            // Fast path: already linked by roamer_tire_id.
            if ( isset( $linked_map[ $roamer_id ] ) ) {
                RTG_Database::update_tire( $linked_map[ $roamer_id ], $eff_data );
                $matched++;
                continue;
            }

            // Try auto-match by normalized brand + model + size.
            $brand = sanitize_text_field( $roamer_tire['brand'] ?? '' );
            $model = sanitize_text_field( $roamer_tire['model'] ?? '' );
            $size  = sanitize_text_field( $roamer_tire['size'] ?? '' );
            $key   = self::normalize_key( $brand, $model, $size );

            if ( isset( $norm_map[ $key ] ) ) {
                $candidates = $norm_map[ $key ];

                if ( count( $candidates ) === 1 ) {
                    // Exact single match — link and update.
                    $eff_data['roamer_tire_id'] = $roamer_id;
                    RTG_Database::update_tire( $candidates[0], $eff_data );
                    // Add to linked map so subsequent Roamer entries with same ID use fast path.
                    $linked_map[ $roamer_id ] = $candidates[0];
                    $matched++;
                } else {
                    // Multiple matches (different load ratings) — skip for manual review.
                    $ambiguous_list[] = array(
                        'roamer_tire_id'      => $roamer_id,
                        'name'                => $brand . ' ' . $model,
                        'size'                => $size,
                        'efficiency'          => $eff_data['roamer_efficiency'],
                        'total_km'            => $eff_data['roamer_total_km'],
                        'vehicle_count'       => $eff_data['roamer_vehicle_count'],
                        'vehicle_breakdown'   => $eff_data['roamer_vehicle_breakdown'],
                        'candidates'          => $candidates,
                    );
                    $skipped++;
                }
            } else {
                // No match in guide.
                $unmatched_list[] = array(
                    'roamer_tire_id'      => $roamer_id,
                    'name'                => ( $roamer_tire['brand'] ?? '' ) . ' ' . ( $roamer_tire['model'] ?? '' ),
                    'size'                => $size,
                    'efficiency'          => $eff_data['roamer_efficiency'],
                    'total_km'            => $eff_data['roamer_total_km'],
                    'vehicle_count'       => $eff_data['roamer_vehicle_count'],
                    'vehicle_breakdown'   => $eff_data['roamer_vehicle_breakdown'],
                );
                $unmatched++;
            }
        }

        RTG_Database::flush_cache();

        $result = array(
            'status'         => 'success',
            'time'           => $now,
            'total_roamer'   => count( $data['tires'] ),
            'matched'        => $matched,
            'skipped'        => $skipped,
            'unmatched'      => $unmatched,
            'ambiguous_list' => $ambiguous_list,
            'unmatched_list' => $unmatched_list,
            'meta'           => $data['meta'] ?? array(),
        );

        // Detect newly appeared ambiguous/unmatched tires and send notification.
        $notify_enabled = $settings['roamer_notify_enabled'] ?? true;
        if ( $notify_enabled && ( ! empty( $ambiguous_list ) || ! empty( $unmatched_list ) ) ) {
            $prev_stats = get_option( self::STATS_OPTION, array() );
            self::maybe_send_notification( $prev_stats, $ambiguous_list, $unmatched_list );
        }

        update_option( self::STATS_OPTION, $result, false );

        return $result;
    }

    /**
     * Normalize brand + model + size into a comparison key.
     *
     * @param string $brand Tire brand.
     * @param string $model Tire model.
     * @param string $size  Tire size.
     * @return string Normalized key for matching.
     */
    public static function normalize_key( $brand, $model, $size ) {
        $brand = strtolower( trim( $brand ) );
        $model = strtolower( trim( $model ) );

        // Normalize size: strip leading "LT", uppercase, remove spaces.
        $size = strtoupper( trim( $size ) );
        $size = preg_replace( '/^LT/', '', $size );
        $size = str_replace( ' ', '', $size );

        // Collapse multiple spaces and normalize special chars.
        $brand = preg_replace( '/\s+/', ' ', $brand );
        $model = preg_replace( '/\s+/', ' ', $model );

        return $brand . '|' . $model . '|' . $size;
    }

    /**
     * Get the most recent sync stats.
     *
     * @return array|false Stats array or false if never synced.
     */
    public static function get_stats() {
        return get_option( self::STATS_OPTION, false );
    }

    /**
     * Get mapping status for all tires (for admin UI).
     *
     * @return array { matched: [], unlinked: [] }
     */
    public static function get_mapping_status() {
        $tires   = RTG_Database::get_all_tires();
        $matched  = array();
        $unlinked = array();

        foreach ( $tires as $tire ) {
            $entry = array(
                'tire_id'              => $tire['tire_id'],
                'brand'                => $tire['brand'],
                'model'                => $tire['model'],
                'size'                 => $tire['size'],
                'load_range'           => $tire['load_range'],
                'roamer_tire_id'       => $tire['roamer_tire_id'] ?? '',
                'roamer_efficiency'         => floatval( $tire['roamer_efficiency'] ?? 0 ),
                'roamer_total_km'           => floatval( $tire['roamer_total_km'] ?? 0 ),
                'roamer_vehicle_count'      => intval( $tire['roamer_vehicle_count'] ?? 0 ),
                'roamer_vehicle_breakdown'  => $tire['roamer_vehicle_breakdown'] ?? '',
                'roamer_synced_at'          => $tire['roamer_synced_at'] ?? '',
            );

            if ( ! empty( $tire['roamer_tire_id'] ) ) {
                $matched[] = $entry;
            } else {
                $unlinked[] = $entry;
            }
        }

        return array(
            'matched'  => $matched,
            'unlinked' => $unlinked,
        );
    }

    /**
     * Compare new sync results against previous stats and send an email
     * notification if there are newly appeared ambiguous or unmatched tires.
     *
     * @param array $prev_stats       Previous sync stats from the DB.
     * @param array $ambiguous_list   Current ambiguous tires.
     * @param array $unmatched_list   Current unmatched tires.
     */
    private static function maybe_send_notification( $prev_stats, $ambiguous_list, $unmatched_list ) {
        // Build sets of previously known IDs.
        $prev_ambiguous_ids = array();
        if ( ! empty( $prev_stats['ambiguous_list'] ) ) {
            foreach ( $prev_stats['ambiguous_list'] as $item ) {
                $prev_ambiguous_ids[ $item['roamer_tire_id'] ] = true;
            }
        }
        $prev_unmatched_ids = array();
        if ( ! empty( $prev_stats['unmatched_list'] ) ) {
            foreach ( $prev_stats['unmatched_list'] as $item ) {
                $prev_unmatched_ids[ $item['roamer_tire_id'] ] = true;
            }
        }

        // Find only newly appeared entries.
        $new_ambiguous = array_filter( $ambiguous_list, function ( $item ) use ( $prev_ambiguous_ids ) {
            return ! isset( $prev_ambiguous_ids[ $item['roamer_tire_id'] ] );
        } );
        $new_unmatched = array_filter( $unmatched_list, function ( $item ) use ( $prev_unmatched_ids ) {
            return ! isset( $prev_unmatched_ids[ $item['roamer_tire_id'] ] );
        } );

        if ( empty( $new_ambiguous ) && empty( $new_unmatched ) ) {
            return;
        }

        RTG_Mailer::send_roamer_sync_notification( $new_ambiguous, $new_unmatched );
    }

    /**
     * Ensure the roamer_vehicle_breakdown column exists in the tires table.
     * dbDelta can silently fail to add TEXT columns on MySQL < 8.0.13, so
     * we check and add it explicitly before every sync.
     */
    private static function ensure_breakdown_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tires';
        $cols  = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

        if ( ! in_array( 'roamer_vehicle_breakdown', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN roamer_vehicle_breakdown TEXT AFTER roamer_vehicle_count" );
        }
    }
}
