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
     * Ceiling for a run started from the browser, whatever the setting says.
     *
     * A CDN or proxy in front of the site (Cloudflare's 524 names its own)
     * stops waiting for the origin after roughly 100 seconds — a limit the
     * configured budget can't negotiate with. The browser request has to fit
     * the sweep, the re-key, the prune, link sync and price sync all under
     * it, so the sweep's share stops here. The nightly cron run doesn't
     * answer to a proxy and keeps the full configured budget; the rotation
     * cursors mean a capped run still makes progress, never loses coverage.
     */
    const INTERACTIVE_BUDGET = 75;

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
        $sources = array();

        // CJ covers both retailers the guide links to, so it leads when it has
        // the credentials it needs; an unconfigured CJ is simply absent rather
        // than failing every run with the same message.
        if ( RTG_Catalog_Source_CJ::is_configured() ) {
            $sources[] = new RTG_Catalog_Source_CJ();
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
     * @param int $budget_cap Optional ceiling on the run budget, in seconds.
     *                        A browser-started run passes INTERACTIVE_BUDGET
     *                        so the request fits under a fronting proxy's
     *                        wait limit; 0 means the configured budget rules.
     * @return array Statistics for the run, as saved to STATS_OPTION.
     */
    public static function run( $budget_cap = 0 ) {
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
        $variants    = self::build_variant_index();

        $run_started = microtime( true );
        $run_budget  = isset( $settings['catalog_run_budget'] )
            ? max( 30, min( 900, intval( $settings['catalog_run_budget'] ) ) )
            : self::RUN_BUDGET;

        $budget_capped = $budget_cap > 0 && $budget_cap < $run_budget;
        if ( $budget_capped ) {
            $run_budget = intval( $budget_cap );
        }

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
                // Whether each fitment was read completely is the question
                // that decides whether coverage can ever be trusted, so it is
                // carried as data rather than left inside an error sentence.
                'coverage'       => method_exists( $source, 'get_last_coverage' )
                    ? $source->get_last_coverage()
                    : array(),
            );

            foreach ( $products as $product ) {
                $ingested  = self::ingest_product( $product, $slug, $source->get_label(), $context, $guide_index, $variants );
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

        $stats['elapsed']       = round( microtime( true ) - $run_started, 1 );
        $stats['run_budget']    = $run_budget;
        $stats['budget_capped'] = $budget_capped;

        // Rows the sweep didn't revisit still hold the match they were given
        // when they were last seen, which the guide may have moved on from.
        $stats['rematched'] = RTG_Candidates::refresh_matches( $guide_index, $variants );

        // Near misses that can never become anything else are let go — wrong
        // fitments, and listings the catalog itself dropped two months ago.
        // Counted in the stats rather than silent, like everything else here.
        $stats['pruned'] = RTG_Candidates::prune( $sizes );

        if ( ! empty( $stats['errors'] ) && 0 === $stats['fetched'] ) {
            $stats['status']  = 'error';
            $stats['message'] = $stats['errors'][0]['message'];
        }

        // Links first, prices second, both off the same fetch: a tire that
        // just gained its link gets its price in the same run instead of
        // waiting a day, and a separate schedule would double the API calls
        // to learn what this run already knows.
        $stats['links']  = RTG_Link_Sync::run();
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
     * One path for judging and storing a fetched product, whoever fetched it.
     *
     * @param array  $product     Normalized product from a source.
     * @param string $slug        Source slug.
     * @param string $label       Source label, used when the feed names no advertiser.
     * @param array  $context     Qualification context.
     * @param array  $guide_index Match key => tire_id.
     * @param array  $variants    Variant index from build_variant_index(), or null to skip
     *                            the name-drift pass.
     * @return array { evaluated: array, result: array }
     */
    public static function ingest_product( $product, $slug, $label, $context, $guide_index, $variants = null ) {
        $evaluated = RTG_Tire_Qualifier::evaluate( $product, $context );
        $match_key = self::match_key( $evaluated['brand'], $evaluated['model'], $evaluated['size'] );

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
            'matched_tire_id' => self::resolve_guide_match(
                $evaluated['brand'],
                $evaluated['model'],
                $evaluated['size'],
                $guide_index,
                $variants,
                $evaluated['load_index']
            ),
            'raw'             => $product,
        ) );

        return array(
            'evaluated' => $evaluated,
            'result'    => $result,
        );
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
            foreach ( self::match_keys_for_tire( $tire ) as $key ) {
                $index[ $key ] = $tire['tire_id'];
            }
        }

        return $index;
    }

    /**
     * Guide tires bucketed by brand and fitment, for the name-drift pass.
     *
     * The exact index only recognizes a listing the retailer spells the way
     * the guide does. Retailers routinely don't: Goodyear's "Wrangler
     * All-Terrain Adventure with Kevlar" is listed as "Wrangler All-Terrain
     * Adventure", and a key built from the shorter name misses the tire the
     * guide already carries. Every such listing then arrives as a new option
     * for a tire already stocked, which is the queue lying about its own job.
     *
     * Aliases fix this one spelling at a time, by hand, after someone notices.
     * This bucket lets the matcher notice on its own, and it is deliberately
     * narrow: brand and fitment must already be identical, so all the model
     * comparison decides is whether two names for the same brand's same-size
     * tire are one tire.
     *
     * @param array|null $tires Guide tires to index; null means all.
     * @return array "brandkey|size" => list of { tire_id, id, model }.
     */
    public static function build_variant_index( $tires = null ) {
        if ( null === $tires ) {
            $tires = RTG_Database::get_all_tires();
        }

        $index = array();

        foreach ( (array) $tires as $tire ) {
            $brand_key = RTG_Tire_Qualifier::normalize_brand( $tire['brand'] ?? '' );
            $size      = RTG_Tire_Qualifier::normalize_size( $tire['size'] ?? '' );

            if ( '' === $brand_key || '' === $size ) {
                continue;
            }

            foreach ( self::model_spellings( $tire ) as $spelling ) {
                $index[ $brand_key . '|' . $size ][] = array(
                    'tire_id'    => (string) ( $tire['tire_id'] ?? '' ),
                    'id'         => intval( $tire['id'] ?? 0 ),
                    'model'      => $spelling,
                    'load_index' => (string) ( $tire['load_index'] ?? '' ),
                );
            }
        }

        return $index;
    }

    /**
     * Every name a guide tire goes by: its model, then each alias.
     *
     * Blank lines are dropped, and so is a blank model — a tire with no name
     * can't be recognized by one.
     *
     * @param array $tire Guide tire (model, model_aliases).
     * @return string[] Spellings, primary first.
     */
    public static function model_spellings( $tire ) {
        $brand     = (string) ( $tire['brand'] ?? '' );
        $spellings = array();

        foreach ( array_merge(
            array( (string) ( $tire['model'] ?? '' ) ),
            preg_split( '/[\r\n]+/', (string) ( $tire['model_aliases'] ?? '' ) )
        ) as $name ) {
            $name = trim( $name );
            if ( '' === $name ) {
                continue;
            }

            $spellings[ $name ] = true;

            $without_brand = self::without_brand_prefix( $name, $brand );
            if ( '' !== $without_brand ) {
                $spellings[ $without_brand ] = true;
            }
        }

        return array_keys( $spellings );
    }

    /**
     * The guide tire a catalog listing already is, if the guide has it.
     *
     * Exact keys first, because they are certain. Only when none matches does
     * the name-drift pass run, and only on a listing whose brand and fitment
     * already sit on a guide tire.
     *
     * @param string     $brand    Listing brand.
     * @param string     $model    Listing model, as the retailer spells it.
     * @param string     $size     Listing size.
     * @param array      $index    Match key => tire_id, from build_guide_index().
     * @param array|null $variants Variant index, or null to use exact keys only.
     * @param string     $load_index The listing's load rating, when it has one.
     * @return string Guide tire_id, or '' when the listing is genuinely new.
     */
    public static function resolve_guide_match( $brand, $model, $size, $index, $variants = null, $load_index = '' ) {
        $key = self::match_key( $brand, $model, $size );

        // An exact key is certain enough to stand on its own. The load rating
        // is deliberately not consulted here: a feed that reports one loosely
        // must not un-match a tire the guide names exactly.
        if ( '' !== $key && isset( $index[ $key ] ) ) {
            return (string) $index[ $key ];
        }

        if ( empty( $variants ) ) {
            return '';
        }

        return self::variant_match( $brand, $model, $size, $variants, $load_index );
    }

    /**
     * The one guide tire whose name is this listing's name, spelled differently.
     *
     * Sameness is RTG_Coverage's containment rule — the one the coverage page
     * already uses to report a tire as "listed under another name" — so both
     * screens agree about what counts as the same tire instead of holding two
     * opinions.
     *
     * Two claimants mean no match. A listing called "Scorpion" sits inside
     * both "Scorpion Zero" and "Scorpion Verde", and picking either would file
     * the listing under a tire it may well not be. Ambiguity resolves to "new":
     * a queue row a human dismisses costs a click, a wrong match hides a real
     * find.
     *
     * A load rating that disagrees settles it: two tires of one brand, one
     * fitment and near-identical names but different ratings are the ordinary
     * shape of a variant — "Scorpion XTM AT" at 116 beside the EV-specific
     * "Scorpion XTM AT Elect" at 119 — and a name comparison alone would file
     * one under the other. Only a rating known on both sides counts against a
     * match; a feed that omits it is silent, not contradictory.
     *
     * @param string $brand      Listing brand.
     * @param string $model      Listing model.
     * @param string $size       Listing size.
     * @param array  $variants   Variant index from build_variant_index().
     * @param string $load_index The listing's load rating, when it has one.
     * @return string Guide tire_id, or '' when nothing unambiguously matches.
     */
    public static function variant_match( $brand, $model, $size, $variants, $load_index = '' ) {
        $bucket = self::variant_bucket( $brand, $model, $size, $variants );
        $found  = array();

        foreach ( $bucket as $entry ) {
            if ( self::load_ratings_disagree( $load_index, $entry['load_index'] ?? '' ) ) {
                continue;
            }

            if ( RTG_Coverage::model_similarity( $entry['model'], $model ) >= RTG_Coverage::VARIANT_THRESHOLD ) {
                $found[ $entry['tire_id'] ] = true;
            }
        }

        return 1 === count( $found ) ? (string) key( $found ) : '';
    }

    /**
     * Whether two load ratings are both known and not the same.
     *
     * Ratings arrive as "119", "119/116" (single- then dual-wheel) and blank.
     * The leading figure is the one the guide compares, and a blank on either
     * side means unknown — which is not disagreement.
     *
     * @param string $listing Listing load rating.
     * @param string $guide   Guide tire load rating.
     * @return bool True only when both are readable and differ.
     */
    public static function load_ratings_disagree( $listing, $guide ) {
        $a = intval( trim( (string) $listing ) );
        $b = intval( trim( (string) $guide ) );

        return $a > 0 && $b > 0 && $a !== $b;
    }

    /**
     * The guide tire whose name most resembles a listing's, match or not.
     *
     * What the queue shows against a row it is still calling new: this brand
     * makes a tire in this fitment and the guide carries it, so if the two
     * names are the same tire the fix is one alias, not a second entry. Only
     * names sharing something are offered — an unrelated model in the same
     * fitment is noise, not a lead.
     *
     * @param string $brand    Listing brand.
     * @param string $model    Listing model.
     * @param string $size     Listing size.
     * @param array  $variants Variant index from build_variant_index().
     * @param float  $floor    Similarity a name must reach to be worth showing.
     * @return array|null { tire_id, id, model, similarity }, or null.
     */
    public static function nearest_guide_variant( $brand, $model, $size, $variants, $floor = 0.5 ) {
        $nearest = null;

        foreach ( self::variant_bucket( $brand, $model, $size, $variants ) as $entry ) {
            $score = RTG_Coverage::model_similarity( $entry['model'], $model );

            if ( $score < $floor ) {
                continue;
            }

            if ( null === $nearest || $score > $nearest['similarity'] ) {
                $nearest = array(
                    'tire_id'    => $entry['tire_id'],
                    'id'         => $entry['id'],
                    'model'      => $entry['model'],
                    'load_index' => $entry['load_index'] ?? '',
                    'similarity' => $score,
                );
            }
        }

        return $nearest;
    }

    /**
     * Guide tires sharing a listing's brand and fitment.
     *
     * @param string $brand    Listing brand.
     * @param string $model    Listing model.
     * @param string $size     Listing size.
     * @param array  $variants Variant index from build_variant_index().
     * @return array Entries in the shape build_variant_index() stores.
     */
    private static function variant_bucket( $brand, $model, $size, $variants ) {
        $brand_key = RTG_Tire_Qualifier::normalize_brand( $brand );
        $size      = RTG_Tire_Qualifier::normalize_size( $size );

        if ( '' === $brand_key || '' === $size || '' === trim( (string) $model ) ) {
            return array();
        }

        return (array) ( $variants[ $brand_key . '|' . $size ] ?? array() );
    }

    /**
     * The guide tire a would-be new tire collides with, if any.
     *
     * The discovery queue can't duplicate the guide — candidates that match
     * an existing tire file under Existing before anyone sees an Add button.
     * A hand-typed tire had no such guard: the only uniqueness check was on
     * the tire ID, which auto-generates. This is that check, using the same
     * keys the matcher lives by — brand and size normalized, punctuation
     * squashed, aliases expanded on BOTH sides, so "Defender LTX M/S 2"
     * collides with "Defender LTX M/S2" and an alias collides with the model
     * it aliases — and then the same name-drift pass the discovery queue uses,
     * so a tire typed in under the retailer's shorter name collides with the
     * guide entry carrying the manufacturer's full one.
     *
     * @param array      $tire  Proposed tire (brand, model, size, model_aliases).
     * @param array|null $tires Guide tires to compare against; null means all.
     * @return string Existing tire_id, or '' when the tire is genuinely new.
     */
    public static function find_guide_match( $tire, $tires = null ) {
        $keys = array_flip( self::match_keys_for_tire( $tire ) );
        if ( empty( $keys ) ) {
            return '';
        }

        if ( null === $tires ) {
            $tires = RTG_Database::get_all_tires();
        }

        foreach ( $tires as $existing ) {
            foreach ( self::match_keys_for_tire( $existing ) as $key ) {
                if ( isset( $keys[ $key ] ) ) {
                    return (string) ( $existing['tire_id'] ?? '' );
                }
            }
        }

        $variants = self::build_variant_index( $tires );

        foreach ( self::model_spellings( $tire ) as $spelling ) {
            $match = self::variant_match(
                $tire['brand'] ?? '',
                $spelling,
                $tire['size'] ?? '',
                $variants,
                $tire['load_index'] ?? ''
            );
            if ( '' !== $match ) {
                return $match;
            }
        }

        return '';
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
     * Every key a guide tire answers to: its own model, plus each alias.
     *
     * Retailers spell a model their own way — "Ridge Grappler LT" for the
     * guide's "Ridge Grappler" — and matching, coverage, pricing and delisting
     * all key on the model. Aliases let the matcher accept the retailer's
     * spelling without renaming what readers see, and this helper is the one
     * place the expansion happens so no caller can forget one spelling.
     *
     * @param array $tire Guide tire (brand, model, size, model_aliases).
     * @return string[] Match keys, primary first, de-duplicated.
     */
    public static function match_keys_for_tire( $tire ) {
        $brand = $tire['brand'] ?? '';
        $size  = $tire['size'] ?? '';

        $keys = array();

        foreach ( self::model_spellings( $tire ) as $spelling ) {
            $key = self::match_key( $brand, $spelling, $size );
            if ( '' !== $key ) {
                $keys[ $key ] = true;
            }
        }

        // A tire with no model at all still keys on brand and size. match_key()
        // accepts an empty model — a legacy of guide rows that predate models —
        // and model_spellings() drops the blank, so it is added back here
        // rather than being lost. A tire that HAS a name never gets this key:
        // it would collide same-brand tires in the index and match any
        // candidate whose model failed to parse.
        if ( empty( $keys ) ) {
            $primary = self::match_key( $brand, $tire['model'] ?? '', $size );
            if ( '' !== $primary ) {
                $keys[ $primary ] = true;
            }
        }

        return array_keys( $keys );
    }

    /**
     * One name with the tire's own brand taken off the front, when it's there.
     *
     * The queue tells an admin to add "this listing's name" as an alias, and a
     * listing's name reads "Toyo Open Country A/T III EV All Terrain" — brand
     * included, because that is how the row displays it. Keys carry the brand
     * separately, so pasting that put the brand in twice and the alias matched
     * nothing, which is a trap of our own making. Both forms are indexed now:
     * the natural paste works, and a model-only alias is unaffected.
     *
     * @param string $name  Name as typed.
     * @param string $brand The tire's brand.
     * @return string The remainder, normalized, or '' when there is no brand
     *                on the front or nothing would be left of the name.
     */
    public static function without_brand_prefix( $name, $brand ) {
        $brand_key = RTG_Tire_Qualifier::normalize_brand( $brand );
        $name_key  = RTG_Tire_Qualifier::normalize_brand( $name );

        if ( '' === $brand_key || '' === $name_key || 0 !== strpos( $name_key, $brand_key ) ) {
            return '';
        }

        // "Toyo" alone as an alias leaves nothing to key on, which would make
        // the tire answer to its bare brand and collide with every sibling.
        return substr( $name_key, strlen( $brand_key ) );
    }

    /**
     * @return array|false Last run's statistics, or false if never run.
     */
    public static function get_stats() {
        return get_option( self::STATS_OPTION, false );
    }
}
