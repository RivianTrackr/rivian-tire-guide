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
     * Seconds a whole run may take, across every pass.
     *
     * Each pass used to honour only its own budget, which is not the same as
     * the run having one: a 240-second sweep and a 120-second lookup pass,
     * each able to start a final 30-second request, add up to well past what a
     * web request survives — and then price sync and the re-key still have to
     * happen. That stayed invisible while a lookup returned fifty records and
     * both passes finished early. Reading a thousand made both run to the
     * limit, and the admin's Run Discovery Now started dying with a network
     * error rather than returning a partial result.
     *
     * A run now stops on this ceiling and says what it did not reach. The
     * rotation cursors mean the next run resumes there, so a ceiling costs
     * time-to-complete, never coverage.
     */
    const RUN_BUDGET = 120;

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

        $run_started = microtime( true );
        $run_budget  = isset( $settings['catalog_run_budget'] )
            ? max( 30, min( 900, intval( $settings['catalog_run_budget'] ) ) )
            : self::RUN_BUDGET;

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
            $products = $source->fetch( $sizes, self::seconds_left( $run_started, $run_budget ) );
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
                $ingested  = self::ingest_product( $product, $slug, $source->get_label(), $context, $guide_index );
                $evaluated = $ingested['evaluated'];
                $result    = $ingested['result'];

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

        // Then ask directly for whatever the sweep still hasn't found, so a
        // tire ranked below where paging stops isn't simply never seen.
        $stats['targeted'] = self::run_targeted_lookup(
            $context,
            $guide_index,
            self::seconds_left( $run_started, $run_budget )
        );

        $stats['elapsed'] = round( microtime( true ) - $run_started, 1 );

        foreach ( $stats['targeted']['errors'] as $targeted_error ) {
            $stats['errors'][] = $targeted_error;
        }

        // Rows the sweep didn't revisit still hold the match they were given
        // when they were last seen, which the guide may have moved on from.
        $stats['rematched'] = RTG_Candidates::refresh_matches( $guide_index );

        // Only a run that read nothing at all is a failure. The targeted pass
        // counts toward that: a sweep that timed out while the direct lookups
        // still brought tires in did work, and calling it an error would hide
        // that.
        if ( ! empty( $stats['errors'] ) && 0 === $stats['fetched'] && 0 === $stats['targeted']['ingested'] ) {
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
     * Evaluate one product and write it to the candidates table.
     *
     * Shared by the fitment sweep and the targeted pass so a product reaching
     * the queue by either route is judged and stored identically.
     *
     * @param array  $product     Normalized product from a source.
     * @param string $slug        Source slug.
     * @param string $label       Source label, used when the feed names no advertiser.
     * @param array  $context     Qualification context.
     * @param array  $guide_index Match key => tire_id.
     * @param array  $only_sizes  Store only these fitments, as a size => true
     *                            set; empty accepts any.
     * @return array { evaluated: array, result: array, match_key: string, skipped: bool }
     */
    public static function ingest_product( $product, $slug, $label, $context, $guide_index, $only_sizes = array() ) {
        $evaluated = RTG_Tire_Qualifier::evaluate( $product, $context );
        $match_key = self::match_key( $evaluated['brand'], $evaluated['model'], $evaluated['size'] );

        if ( ! empty( $only_sizes ) && ! isset( $only_sizes[ $evaluated['size'] ] ) ) {
            return array(
                'evaluated' => $evaluated,
                'result'    => array( 'status' => '', 'newly_surfaced' => false, 'id' => 0 ),
                'match_key' => $match_key,
                'skipped'   => true,
            );
        }

        $result = RTG_Candidates::upsert( array(
            'source'          => $slug,
            'advertiser_id'   => $product['advertiser_id'] ?? '',
            'advertiser_name' => $product['advertiser_name'] ?? $label,
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

        return array(
            'evaluated' => $evaluated,
            'result'    => $result,
            'match_key' => $match_key,
            'skipped'   => false,
        );
    }

    /**
     * Search terms for the guide tires nothing in the queue keys to.
     *
     * A term names the brand and model and deliberately omits the size, which
     * the probe settled: asking CJ for "Michelin Defender LTX M/S2 305/45R22"
     * returned that exact model from Tire Rack in 285/45R22 and 275/45R22.
     * The words match; the size is scored, not applied. Leaving it in the term
     * only dilutes the part that works, and the fitment is something this side
     * can filter on exactly.
     *
     * Omitting it also collapses the work: every uncovered size of one model
     * becomes a single request rather than one per fitment.
     *
     * @param array $covered_keys Match keys the queue already holds.
     * @return array {
     *     @type string[] $terms  Distinct "Brand Model" searches to run.
     *     @type array    $wanted Match key => term, for the tires being sought.
     * }
     */
    public static function uncovered_terms( $covered_keys ) {
        $terms  = array();
        $wanted = array();

        foreach ( RTG_Database::get_all_tires() as $tire ) {
            $key = self::match_key( $tire['brand'] ?? '', $tire['model'] ?? '', $tire['size'] ?? '' );

            if ( '' === $key || isset( $covered_keys[ $key ] ) ) {
                continue;
            }

            $term = trim( preg_replace( '/\s+/', ' ', sprintf(
                '%s %s',
                $tire['brand'] ?? '',
                $tire['model'] ?? ''
            ) ) );

            if ( '' === $term ) {
                continue;
            }

            $terms[ $term ] = true;
            $wanted[ $key ] = $term;
        }

        return array(
            'terms'  => array_keys( $terms ),
            'wanted' => $wanted,
        );
    }

    /**
     * Ask each source directly for the tires the sweep didn't find.
     *
     * The sweep searches a bare fitment, which CJ answers with a relevance
     * ranking thousands deep rather than a filter, so a guide tire can sit
     * below where paging stops and never arrive. Asking for it by brand, model
     * and size is a different question and takes one request.
     *
     * @param array $context     Qualification context.
     * @param array $guide_index Match key => tire_id.
     * @return array Statistics for the pass.
     */
    private static function run_targeted_lookup( $context, $guide_index, $ceiling = null ) {
        $stats = array(
            'terms'     => 0,
            'tires'     => 0,
            'checked'   => 0,
            'answered'  => 0,
            'matched'   => 0,
            'pending'   => 0,
            'ingested'  => 0,
            'off_size'  => 0,
            'qualified' => 0,
            'capped'    => 0,
            'deepest'   => 0,
            'discarded' => 0,
            'skipped'   => false,
            'errors'    => array(),
        );

        $uncovered = self::uncovered_terms( RTG_Candidates::get_by_match_key() );

        $stats['terms'] = count( $uncovered['terms'] );
        $stats['tires'] = count( $uncovered['wanted'] );

        if ( empty( $uncovered['terms'] ) ) {
            return $stats;
        }

        // The sweep may already have spent the run's whole allowance. Saying
        // so beats starting a pass that will be killed mid-request, and the
        // rotation means the next run picks these up.
        if ( null !== $ceiling && $ceiling <= 0 ) {
            $stats['skipped'] = true;
            $stats['pending'] = count( $uncovered['terms'] );

            return $stats;
        }

        // Any fitment the guide covers is worth keeping, not only the one that
        // prompted the search. A search for a model returns it in every size
        // CJ holds, and some of those are other guide tires — dropping them
        // would throw away a match we were about to go looking for anyway.
        $guide_sizes = array();
        foreach ( RTG_Admin::get_dropdown_options( 'sizes' ) as $size ) {
            $normalized = RTG_Tire_Qualifier::normalize_size( $size );
            if ( '' !== $normalized ) {
                $guide_sizes[ $normalized ] = true;
            }
        }

        $covered = array();

        foreach ( self::get_sources() as $source ) {
            if ( ! method_exists( $source, 'fetch_terms' ) ) {
                continue;
            }

            $seen       = array();
            $slug       = $source->get_slug();
            $label      = $source->get_label();
            $stats_ref  = &$stats;
            $covered_ref = &$covered;

            // Consumed as each answer arrives rather than collected first. At
            // a thousand records a term and a hundred terms, holding every
            // response would mean a hundred thousand products in memory to
            // keep the few dozen in a fitment the guide uses.
            $lookup = $source->fetch_terms(
                $uncovered['terms'],
                function ( $term, $products ) use (
                    &$stats_ref, &$covered_ref, &$seen,
                    $slug, $label, $context, $guide_index, $guide_sizes, $uncovered
                ) {
                    if ( empty( $products ) ) {
                        return;
                    }

                    $stats_ref['answered']++;

                    foreach ( $products as $product ) {
                        $id = (string) ( $product['external_id'] ?? '' );

                        if ( '' === $id || isset( $seen[ $id ] ) ) {
                            continue;
                        }

                        $ingested = RTG_Catalog_Sync::ingest_product(
                            $product,
                            $slug,
                            $label,
                            $context,
                            $guide_index,
                            $guide_sizes
                        );

                        if ( $ingested['skipped'] ) {
                            // Left out rather than stored. CJ scores a keyword
                            // instead of filtering on it, so most of what a
                            // search returns is a fitment the guide has no use
                            // for; storing it added four thousand near misses
                            // in one run without covering a tire. Canvassing
                            // fitments is the sweep's job.
                            $stats_ref['off_size']++;
                            continue;
                        }

                        $seen[ $id ] = true;
                        $stats_ref['ingested']++;

                        if ( RTG_Candidates::STATUS_NEW === $ingested['result']['status'] ) {
                            $stats_ref['qualified']++;
                        }

                        // The only outcome that means the pass worked: a
                        // product came back that keys to a tire being looked
                        // for. Counting "the request returned something"
                        // instead reported 111 of 111 successful while
                        // coverage did not move at all.
                        if ( isset( $uncovered['wanted'][ $ingested['match_key'] ] ) ) {
                            $covered_ref[ $ingested['match_key'] ] = true;
                        }
                    }
                },
                $ceiling,
                $guide_sizes
            );

            $stats['discarded'] = ( $stats['discarded'] ?? 0 ) + intval( $lookup['discarded'] ?? 0 );
            $stats['checked'] += $lookup['checked'];
            $stats['pending']  = max( $stats['pending'], $lookup['pending'] );
            $stats['capped']  += $lookup['capped'];
            $stats['deepest']  = max( $stats['deepest'], $lookup['deepest'] );

            if ( '' !== $lookup['error'] ) {
                $stats['errors'][] = array(
                    'source'  => $slug,
                    'message' => $lookup['error'],
                );
            }
        }

        $stats['matched'] = count( $covered );

        return $stats;
    }

    /**
     * Seconds remaining in the run's overall allowance.
     *
     * @param float $started Run start, from microtime( true ).
     * @param int   $budget  Allowance in seconds.
     * @return float Seconds left, never below zero.
     */
    private static function seconds_left( $started, $budget ) {
        return max( 0.0, $budget - ( microtime( true ) - $started ) );
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
