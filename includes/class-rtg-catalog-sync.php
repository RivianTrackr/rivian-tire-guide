<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Finds tires that have appeared in affiliate catalogs since the last run.
 *
 * The job this replaces is a manual one: periodically searching Tire Rack and
 * SimpleTire for each Rivian fitment and eyeballing the results for anything
 * new. Done by hand it happens rarely and misses things. Here it runs daily,
 * remembers every product it has already judged, and emails only what is both
 * new and actually eligible.
 *
 * Structurally this mirrors RTG_Roamer_Sync — pull an external dataset,
 * normalize it, match it against the guide, leave the leftovers for a human,
 * notify on what's newly appeared — because it is the same problem.
 *
 * @since 1.59.0
 */
class RTG_Catalog_Sync {

    /** WP-Cron hook name. */
    const CRON_HOOK = 'rtg_catalog_sync';

    /** Option key holding the last run's statistics. */
    const STATS_OPTION = 'rtg_catalog_sync_stats';

    /**
     * Schedule the daily sync if it isn't already scheduled.
     *
     * Daily is deliberate: retailer catalogs turn over slowly, and a tire that
     * appears today is no less findable tomorrow. Polling harder would spend
     * API quota to learn nothing.
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Remove the scheduled event on plugin deactivation.
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Sources to pull from.
     *
     * Filterable so a network adapter (CJ, and anything after it) registers
     * itself without this class needing to know it exists.
     *
     * @return RTG_Catalog_Source[] Registered sources.
     */
    public static function get_sources() {
        $settings = get_option( 'rtg_settings', array() );

        $sources = array();

        // CJ covers both retailers the guide links to, so it leads when it has
        // the credentials it needs; an unconfigured CJ is simply absent rather
        // than failing every run with the same message.
        if ( RTG_Catalog_Source_CJ::is_configured() ) {
            $sources[] = new RTG_Catalog_Source_CJ();
        }

        // The JSON source runs whenever a feed URL is set. With CJ configured
        // and no URL set it stays out of the way, so the bundled sample can't
        // seed demo rows into a queue holding real finds.
        $fixture_url = trim( (string) ( $settings['catalog_fixture_url'] ?? '' ) );
        if ( '' !== $fixture_url || empty( $sources ) ) {
            $sources[] = new RTG_Catalog_Source_Fixture( $fixture_url );
        }

        /**
         * Filter the catalog sources the sync pulls from.
         *
         * @param RTG_Catalog_Source[] $sources Registered sources.
         */
        $sources = apply_filters( 'rtg_catalog_sources', $sources );

        // Drop anything that doesn't honour the contract rather than fataling
        // mid-run on a third-party registration.
        return array_values( array_filter( $sources, function ( $source ) {
            return $source instanceof RTG_Catalog_Source;
        } ) );
    }

    /**
     * Run a full discovery pass.
     *
     * @return array Statistics for the run, as saved to STATS_OPTION.
     */
    public static function run() {
        $settings = get_option( 'rtg_settings', array() );

        if ( isset( $settings['catalog_sync_enabled'] ) && ! $settings['catalog_sync_enabled'] ) {
            return array(
                'status'  => 'disabled',
                'message' => 'Catalog sync is disabled in settings.',
                'time'    => current_time( 'mysql' ),
            );
        }

        $sizes       = RTG_Admin::get_dropdown_options( 'sizes' );
        $context     = RTG_Tire_Qualifier::default_context();
        $guide_index = self::build_guide_index();

        $stats = array(
            'status'         => 'success',
            'time'           => current_time( 'mysql' ),
            'fetched'        => 0,
            'qualified'      => 0,
            'rejected'       => 0,
            'existing'       => 0,
            'newly_surfaced' => 0,
            'sources'        => array(),
            'errors'         => array(),
        );

        $surfaced = array();

        foreach ( self::get_sources() as $source ) {
            $slug     = $source->get_slug();
            $products = $source->fetch( $sizes );
            $error    = $source->get_last_error();

            if ( '' !== $error ) {
                $stats['errors'][] = array(
                    'source'  => $slug,
                    'message' => $error,
                );
            }

            $source_stats = array(
                'slug'           => $slug,
                'label'          => $source->get_label(),
                'fetched'        => count( $products ),
                'qualified'      => 0,
                'newly_surfaced' => 0,
                'error'          => $error,
            );

            foreach ( $products as $product ) {
                $evaluated = RTG_Tire_Qualifier::evaluate( $product, $context );
                $match_key = self::match_key( $evaluated['brand'], $evaluated['model'], $evaluated['size'] );

                $result = RTG_Candidates::upsert( array(
                    'source'          => $slug,
                    'advertiser_id'   => $product['advertiser_id'] ?? '',
                    'advertiser_name' => $product['advertiser_name'] ?? $source->get_label(),
                    'external_id'     => $product['external_id'] ?? '',
                    'brand'           => $evaluated['brand'],
                    'model'           => $evaluated['model'],
                    'size'            => $evaluated['size'],
                    'load_index'      => $evaluated['load_index'],
                    'load_range'      => $evaluated['load_range'],
                    'speed_rating'    => $evaluated['speed_rating'],
                    'price'           => $product['price'] ?? 0,
                    'link'            => $product['link'] ?? '',
                    'image'           => $product['image'] ?? '',
                    'match_key'       => $match_key,
                    'qualifies'       => $evaluated['qualifies'],
                    'fail_reasons'    => $evaluated['reasons'],
                    'warnings'        => $evaluated['warnings'] ?? array(),
                    'fits_vehicles'   => $evaluated['fits_vehicles'] ?? array(),
                    'matched_tire_id' => $guide_index[ $match_key ] ?? '',
                    'raw'             => $product,
                ) );

                $stats['fetched']++;

                switch ( $result['status'] ) {
                    case RTG_Candidates::STATUS_NEW:
                        $stats['qualified']++;
                        $source_stats['qualified']++;
                        break;
                    case RTG_Candidates::STATUS_EXISTING:
                        $stats['existing']++;
                        break;
                    case RTG_Candidates::STATUS_REJECTED:
                        $stats['rejected']++;
                        break;
                }

                if ( $result['newly_surfaced'] ) {
                    $stats['newly_surfaced']++;
                    $source_stats['newly_surfaced']++;

                    $surfaced[] = array(
                        'id'              => $result['id'],
                        'brand'           => $evaluated['brand'],
                        'model'           => $evaluated['model'],
                        'size'            => $evaluated['size'],
                        'fits_vehicles'   => $evaluated['fits_vehicles'] ?? array(),
                        'load_index'      => $evaluated['load_index'],
                        'price'           => floatval( $product['price'] ?? 0 ),
                        'advertiser_name' => $product['advertiser_name'] ?? $source->get_label(),
                    );
                }
            }

            $stats['sources'][] = $source_stats;
        }

        // Rows the sweep didn't revisit still hold the match they were given
        // when they were last seen, which the guide may have moved on from.
        $stats['rematched'] = RTG_Candidates::refresh_matches( $guide_index );

        if ( ! empty( $stats['errors'] ) && 0 === $stats['fetched'] ) {
            $stats['status']  = 'error';
            $stats['message'] = $stats['errors'][0]['message'];
        }

        // Prices refresh off the same fetch. A separate schedule would double
        // the API calls to learn what this run already knows.
        $stats['prices'] = RTG_Price_Sync::run();

        update_option( self::STATS_OPTION, $stats, false );

        // Only unattended runs email. A manual run shows its result in the UI,
        // so a duplicate in the inbox would just be noise.
        $is_cron        = defined( 'DOING_CRON' ) && DOING_CRON;
        $notify_enabled = $settings['catalog_notify_enabled'] ?? true;

        if ( $is_cron && $notify_enabled && ! empty( $surfaced ) ) {
            RTG_Mailer::send_catalog_digest_notification( $surfaced );
        }

        return $stats;
    }

    /**
     * Build a lookup of tires already in the guide.
     *
     * Both an exact key and a squashed one are indexed, because a retailer's
     * "Defender LTX M/S 2" and the guide's "Defender LTX M/S2" are the same
     * tire and only the squashed form catches it.
     *
     * A key that misses simply means the candidate looks new — which costs one
     * queue row a human dismisses. That's the right way round: a false "new"
     * is visible and cheap, a false "already have it" would silently hide a
     * genuine find, which is the exact failure this feature exists to fix.
     *
     * @return array Match key => tire_id.
     */
    public static function build_guide_index() {
        $index = array();

        foreach ( RTG_Database::get_all_tires() as $tire ) {
            $key = self::match_key( $tire['brand'] ?? '', $tire['model'] ?? '', $tire['size'] ?? '' );
            if ( '' !== $key ) {
                $index[ $key ] = $tire['tire_id'];
            }
        }

        return $index;
    }

    /**
     * Build the key used to recognize one physical tire across sources.
     *
     * @param string $brand Brand name.
     * @param string $model Model name.
     * @param string $size  Tire size, in any common notation.
     * @return string Match key, or '' when there isn't enough to key on.
     */
    public static function match_key( $brand, $model, $size ) {
        $brand = strtolower( trim( (string) $brand ) );
        $model = strtolower( trim( (string) $model ) );
        $size  = RTG_Tire_Qualifier::normalize_size( $size );

        // Squash everything that retailers punctuate inconsistently, so
        // "M/S 2", "M/S2" and "M-S2" collapse to one key.
        $brand = preg_replace( '/[^a-z0-9]/', '', $brand );
        $model = preg_replace( '/[^a-z0-9]/', '', $model );

        if ( '' === $brand || '' === $size ) {
            return '';
        }

        return $brand . '|' . $model . '|' . $size;
    }

    /**
     * @return array|false Last run's statistics, or false if never run.
     */
    public static function get_stats() {
        return get_option( self::STATS_OPTION, false );
    }
}
