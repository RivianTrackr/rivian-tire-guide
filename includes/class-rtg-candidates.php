<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Storage for tires discovered in affiliate catalogs but not yet in the guide.
 *
 * This table is the memory the manual process never had: which products have
 * already been looked at, and what was decided about them. Without it every
 * sync would re-surface the same rejects and the review queue would be
 * unusable within a fortnight.
 *
 * A candidate's status is therefore sticky where a human touched it. Dismissed
 * and imported rows keep their status across every subsequent sync — only
 * machine-assigned statuses (new / rejected / existing) are recomputed.
 *
 * @since 1.59.0
 */
class RTG_Candidates {

    /** Qualifies, not in the guide, waiting for a decision. */
    const STATUS_NEW = 'new';

    /** Failed at least one qualification rule. Kept so near misses stay visible. */
    const STATUS_REJECTED = 'rejected';

    /**
     * Would qualify, but the retailer lists it as out of stock. Kept out of
     * the review queue, under its own tab, until a sweep finds it back in
     * stock; then it surfaces as new, announced like a first sighting.
     */
    const STATUS_SOLD_OUT = 'sold_out';

    /** Already matches a tire in the guide. */
    const STATUS_EXISTING = 'existing';

    /** A human said no. Never resurfaces in the queue or a digest. */
    const STATUS_DISMISSED = 'dismissed';

    /** A human added it to the guide from this queue. */
    const STATUS_IMPORTED = 'imported';

    /**
     * Statuses a sync is allowed to overwrite. Anything else was set by a
     * person and outranks whatever this run concluded.
     */
    const MACHINE_STATUSES = array( self::STATUS_NEW, self::STATUS_SOLD_OUT, self::STATUS_REJECTED, self::STATUS_EXISTING );

    /**
     * Availability wordings, as normalize_availability() spells them, that
     * mean the retailer cannot sell the tire today. Preorder and backorder
     * are not here: those can be ordered.
     */
    const OUT_OF_STOCK = array( 'out of stock', 'outofstock', 'sold out', 'soldout', 'unavailable', 'not available', 'discontinued' );

    /**
     * One spelling for a retailer's stock wording: lower case, one space
     * between words, so "Out_Of_Stock" and "out of stock" file the same way.
     *
     * @param string $value Wording as the source sent it.
     * @return string Normalized wording, at most 30 characters.
     */
    public static function normalize_availability( $value ) {
        $value = strtolower( trim( (string) $value ) );
        $value = preg_replace( '/[\s_\-]+/', ' ', $value );

        return substr( $value, 0, 30 );
    }

    /**
     * @param string $availability Stock wording, raw or normalized.
     * @return bool Whether the retailer says the tire cannot be bought today.
     */
    public static function is_out_of_stock( $availability ) {
        return in_array( self::normalize_availability( $availability ), self::OUT_OF_STOCK, true );
    }

    /**
     * The status a sweep concludes for a listing, before any human decision.
     *
     * Matching a guide tire settles it first, ahead of qualification. A
     * product you already stock is "already in the guide" whatever the
     * rules make of the listing's own wording; judging it first would file
     * a tire you own under near misses because its listing happened to omit
     * a load index, which is both untrue and unhelpful. A near miss comes
     * next: stock only matters for a tire that could be added, and a near
     * miss could not be whatever the shelf says, so what it fell short on is
     * the useful thing to read. Of what is left, an out-of-stock listing is
     * sold out and the rest are new.
     *
     * @param string $matched_tire_id Guide tire it matches, or ''.
     * @param bool   $qualifies       Whether it passed the qualification rules.
     * @param string $availability    Retailer's stock wording, or ''.
     * @return string One of the machine statuses.
     */
    public static function compute_status( $matched_tire_id, $qualifies, $availability = '' ) {
        if ( '' !== (string) $matched_tire_id ) {
            return self::STATUS_EXISTING;
        }
        if ( empty( $qualifies ) ) {
            return self::STATUS_REJECTED;
        }

        return self::is_out_of_stock( $availability ) ? self::STATUS_SOLD_OUT : self::STATUS_NEW;
    }

    /**
     * @return string Fully-prefixed candidates table name.
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'rtg_tire_candidates';
    }

    /**
     * Insert a freshly seen product, or update the row we already hold for it.
     *
     * @param array $row Candidate fields. Requires source, advertiser_id, external_id.
     * @return array {
     *     @type int    $id            Row ID, or 0 on failure.
     *     @type bool   $is_new        True when this product had never been seen.
     *     @type bool   $newly_surfaced True when the row is in STATUS_NEW and wasn't before.
     *     @type string $status        Status the row now holds.
     * }
     */
    public static function upsert( $row ) {
        self::forget_matched_by_tire();
        global $wpdb;
        $table = self::table();

        $source        = (string) ( $row['source'] ?? '' );
        $advertiser_id = (string) ( $row['advertiser_id'] ?? '' );
        $external_id   = (string) ( $row['external_id'] ?? '' );

        if ( '' === $source || '' === $external_id ) {
            return array(
                'id'             => 0,
                'is_new'         => false,
                'newly_surfaced' => false,
                'status'         => '',
            );
        }

        $now = current_time( 'mysql' );

        // The status this run concludes, before human decisions are applied.
        $computed_status = self::compute_status(
            (string) ( $row['matched_tire_id'] ?? '' ),
            ! empty( $row['qualifies'] ),
            (string) ( $row['availability'] ?? '' )
        );

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status FROM {$table} WHERE source = %s AND advertiser_id = %s AND external_id = %s",
                $source,
                $advertiser_id,
                $external_id
            ),
            ARRAY_A
        );

        $data = array(
            'source'          => $source,
            'advertiser_id'   => $advertiser_id,
            'advertiser_name' => (string) ( $row['advertiser_name'] ?? '' ),
            'external_id'     => $external_id,
            'brand'           => (string) ( $row['brand'] ?? '' ),
            'model'           => (string) ( $row['model'] ?? '' ),
            'size'            => (string) ( $row['size'] ?? '' ),
            'load_index'      => (string) ( $row['load_index'] ?? '' ),
            'load_range'      => (string) ( $row['load_range'] ?? '' ),
            'speed_rating'    => (string) ( $row['speed_rating'] ?? '' ),
            'price'           => floatval( $row['price'] ?? 0 ),
            'availability'    => self::normalize_availability( $row['availability'] ?? '' ),
            'link'            => (string) ( $row['link'] ?? '' ),
            'image'           => (string) ( $row['image'] ?? '' ),
            'match_key'       => (string) ( $row['match_key'] ?? '' ),
            'qualifies'       => ! empty( $row['qualifies'] ) ? 1 : 0,
            'fail_reasons'    => wp_json_encode( array(
                'reasons'  => $row['fail_reasons'] ?? array(),
                'warnings' => $row['warnings'] ?? array(),
            ) ),
            'matched_tire_id' => (string) ( $row['matched_tire_id'] ?? '' ),
            // Stored comma-separated with no spaces so FIND_IN_SET can filter
            // the queue by platform without a LIKE scan.
            'fits_vehicles'   => implode( ',', array_map( 'strval', (array) ( $row['fits_vehicles'] ?? array() ) ) ),
            'raw_json'        => wp_json_encode( $row['raw'] ?? array() ),
            'last_seen_at'    => $now,
        );

        $formats = array(
            '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s',
            '%f', '%s', '%s', '%s',
            '%s', '%d', '%s', '%s', '%s',
            '%s', '%s',
        );

        if ( $existing ) {
            $prev_status = (string) $existing['status'];

            // A person's decision survives the sync; only machine statuses move.
            $next_status = in_array( $prev_status, self::MACHINE_STATUSES, true )
                ? $computed_status
                : $prev_status;

            $data['status'] = $next_status;
            $formats[]      = '%s';

            $wpdb->update( $table, $data, array( 'id' => intval( $existing['id'] ) ), $formats, array( '%d' ) );

            return array(
                'id'             => intval( $existing['id'] ),
                'is_new'         => false,
                'newly_surfaced' => self::STATUS_NEW === $next_status && self::STATUS_NEW !== $prev_status,
                'status'         => $next_status,
            );
        }

        $data['status']        = $computed_status;
        $data['first_seen_at'] = $now;
        $formats[]             = '%s';
        $formats[]             = '%s';

        $inserted = $wpdb->insert( $table, $data, $formats );

        return array(
            'id'             => $inserted ? intval( $wpdb->insert_id ) : 0,
            'is_new'         => (bool) $inserted,
            'newly_surfaced' => (bool) $inserted && self::STATUS_NEW === $computed_status,
            'status'         => $computed_status,
        );
    }

    /**
     * Fetch a single candidate by row ID.
     *
     * @param int $id Row ID.
     * @return array|null Candidate row, or null when absent.
     */
    public static function get( $id ) {
        global $wpdb;
        $table = self::table();

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", intval( $id ) ),
            ARRAY_A
        );

        return $row ? self::hydrate( $row ) : null;
    }

    /**
     * List candidates for the admin queue.
     *
     * @param array $args {
     *     @type string $status  Status to filter on, or 'all'. Default 'new'.
     *     @type string $size    Canonical size to filter on. Default '' (any).
     *     @type string $source  Source slug to filter on. Default '' (any).
     *     @type string $vehicle Vehicle the tire must be legal on (e.g. 'R1'). Default '' (any).
     *     @type int    $limit   Maximum rows. Default 200.
     *     @type int    $offset  Rows to skip. Default 0.
     * }
     * @return array[] Candidate rows, newest first.
     */
    public static function query( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $args = wp_parse_args( $args, array(
            'status'  => self::STATUS_NEW,
            'size'    => '',
            'source'  => '',
            'brand'   => '',
            'vehicle' => '',
            'limit'   => 200,
            'offset'  => 0,
        ) );

        $where  = array( '1=1' );
        $params = array();

        if ( '' !== $args['status'] && 'all' !== $args['status'] ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if ( '' !== $args['size'] ) {
            $where[]  = 'size = %s';
            $params[] = $args['size'];
        }
        if ( '' !== $args['source'] ) {
            $where[]  = 'source = %s';
            $params[] = $args['source'];
        }
        if ( '' !== ( $args['brand'] ?? '' ) ) {
            $where[]  = 'brand = %s';
            $params[] = $args['brand'];
        }
        if ( '' !== $args['vehicle'] ) {
            $where[]  = 'FIND_IN_SET( %s, fits_vehicles )';
            $params[] = $args['vehicle'];
        }

        $params[] = max( 1, min( 500, intval( $args['limit'] ) ) );
        $params[] = max( 0, intval( $args['offset'] ) );

        $sql = 'SELECT * FROM ' . $table
            . ' WHERE ' . implode( ' AND ', $where )
            . ' ORDER BY first_seen_at DESC, id DESC LIMIT %d OFFSET %d';

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        return array_map( array( __CLASS__, 'hydrate' ), $rows ?: array() );
    }

    /**
     * How many rows match a query() filter set, ignoring its limit — so a
     * capped listing can say "showing N of M" instead of silently truncating.
     *
     * @param array $args Same filter keys as query(); limit/offset ignored.
     * @return int Matching row count.
     */
    public static function count_matching( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $args = wp_parse_args( $args, array(
            'status'  => self::STATUS_NEW,
            'size'    => '',
            'source'  => '',
            'brand'   => '',
            'vehicle' => '',
        ) );

        $where  = array( '1=1' );
        $params = array();

        if ( '' !== $args['status'] && 'all' !== $args['status'] ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if ( '' !== $args['size'] ) {
            $where[]  = 'size = %s';
            $params[] = $args['size'];
        }
        if ( '' !== $args['source'] ) {
            $where[]  = 'source = %s';
            $params[] = $args['source'];
        }
        if ( '' !== ( $args['brand'] ?? '' ) ) {
            $where[]  = 'brand = %s';
            $params[] = $args['brand'];
        }
        if ( '' !== $args['vehicle'] ) {
            $where[]  = 'FIND_IN_SET( %s, fits_vehicles )';
            $params[] = $args['vehicle'];
        }

        $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . implode( ' AND ', $where );

        return intval( $wpdb->get_var( $params ? $wpdb->prepare( $sql, $params ) : $sql ) );
    }

    /**
     * Count candidates grouped by status.
     *
     * @return array Status slug => count, with every known status present.
     */
    public static function get_counts() {
        global $wpdb;
        $table = self::table();

        // Read on every admin screen for the menu badge, so it is cached and
        // forgotten by every candidate write (see forget_matched_by_tire).
        $cached = get_transient( self::COUNTS_CACHE );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $counts = array(
            self::STATUS_NEW       => 0,
            self::STATUS_SOLD_OUT  => 0,
            self::STATUS_REJECTED  => 0,
            self::STATUS_EXISTING  => 0,
            self::STATUS_DISMISSED => 0,
            self::STATUS_IMPORTED  => 0,
        );

        $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );

        foreach ( $rows ?: array() as $row ) {
            $counts[ $row['status'] ] = intval( $row['total'] );
        }

        set_transient( self::COUNTS_CACHE, $counts, 5 * MINUTE_IN_SECONDS );

        return $counts;
    }

    /**
     * Count candidates awaiting review, grouped by the vehicle they fit.
     *
     * A tire legal on both platforms counts once for each, so the totals
     * deliberately overlap rather than partitioning the queue.
     *
     * @param string $status Status to count within. Default STATUS_NEW.
     * @return array Vehicle => count, for vehicles with at least one match.
     */
    public static function get_vehicle_counts( $status = self::STATUS_NEW ) {
        global $wpdb;
        $table = self::table();

        $rows = $wpdb->get_col(
            $wpdb->prepare( "SELECT fits_vehicles FROM {$table} WHERE status = %s", $status )
        );

        $counts = array();
        foreach ( $rows ?: array() as $value ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' ) as $vehicle ) {
                $counts[ $vehicle ] = ( $counts[ $vehicle ] ?? 0 ) + 1;
            }
        }

        ksort( $counts );

        return $counts;
    }

    /**
     * Group every candidate carrying a match key, keyed by that key.
     *
     * Only the columns coverage and pricing read are selected. Each row also
     * holds an untouched copy of the source product node, and pulling those
     * for sixteen thousand rows to compare prices would cost tens of megabytes
     * to answer a question that needs none of it.
     *
     * @return array match key => candidate rows.
     */
    public static function get_by_match_key() {
        global $wpdb;
        $table = self::table();

        $rows = $wpdb->get_results(
            "SELECT id, match_key, brand, model, size, load_index, price, link, image,
                    advertiser_id, advertiser_name, status, first_seen_at, last_seen_at
             FROM {$table} WHERE match_key <> ''",
            ARRAY_A
        );

        $by_key = array();
        foreach ( $rows ?: array() as $row ) {
            $row['price']             = floatval( $row['price'] );
            $by_key[ $row['match_key'] ][] = $row;
        }

        return $by_key;
    }

    /**
     * Group every candidate that matches a guide tire, keyed by that tire.
     *
     * Matched by comparing match keys at read time rather than by reading the
     * matched_tire_id written during a sweep, because that column is only
     * accurate for rows the most recent sweep happened to revisit. It goes
     * stale two ways, and both were losing real coverage:
     *
     *   - A tire added or renamed in the guide today doesn't retro-match the
     *     candidate rows already stored for it. They keep matched_tire_id = ''
     *     until a sweep sees those products again, and a sweep is budget-
     *     limited and rotates through sizes, so that can be several days.
     *   - build_guide_index() maps one key to one tire, so when two guide
     *     tires share a brand, model and size — the same tire in two load
     *     ratings — only the last one indexed was ever written to a candidate.
     *     The other could never be covered at all.
     *
     * Comparing keys here costs one extra pass over the guide and fixes both:
     * coverage reflects the guide as it stands right now.
     *
     * Names are compared as well as keys, on the same rule the matcher uses,
     * so a retailer listing the tire under a shorter name than the guide's
     * still counts as carrying it.
     *
     * Dismissed rows are included deliberately: dismissing a candidate means
     * "don't offer this as a new tire", not "stop pricing the tire I already
     * stock from it".
     *
     * @return array tire_id => candidate rows.
     */
    public static function get_matched_by_tire() {
        // One catalog run asks for this table up to three times with identical
        // inputs (link sync, price sync, and the discovery admin page), and
        // computing it means a similarity comparison of every candidate
        // against every same-brand/size guide entry — tens of millions of
        // string comparisons at catalog scale. Memoize per request; every
        // candidate mutator forgets it.
        if ( null !== self::$matched_by_tire_memo ) {
            return self::$matched_by_tire_memo;
        }

        $by_key   = self::get_by_match_key();
        $tires    = RTG_Database::get_all_tires();
        $variants = RTG_Catalog_Sync::build_variant_index( $tires );

        // The listings this brand makes in this fitment whose name is a guide
        // tire's name spelled differently. Without them a retailer carrying
        // the tire under a shorter name reads as no retailer carrying it at
        // all, and the tire's price has nowhere to come from.
        //
        // Resolved once per listing rather than once per listing per tire: the
        // answer depends only on the listing, and the guide runs to hundreds
        // of rows.
        $by_name = array();

        foreach ( $by_key as $rows ) {
            foreach ( $rows as $row ) {
                $tire_id = RTG_Catalog_Sync::variant_match(
                    $row['brand'],
                    $row['model'],
                    $row['size'],
                    $variants,
                    $row['load_index'] ?? ''
                );

                if ( '' !== $tire_id ) {
                    $by_name[ $tire_id ][] = $row;
                }
            }
        }

        $by_tire = array();

        foreach ( $tires as $tire ) {
            $tire_id = (string) $tire['tire_id'];
            $rows    = array();
            $seen    = array();

            // A tire answers to its own model and to each alias, and a
            // retailer may list it under either — collect every spelling.
            foreach ( RTG_Catalog_Sync::match_keys_for_tire( $tire ) as $key ) {
                foreach ( $by_key[ $key ] ?? array() as $row ) {
                    $rows[]                       = $row;
                    $seen[ intval( $row['id'] ) ] = true;
                }
            }

            foreach ( $by_name[ $tire_id ] ?? array() as $row ) {
                if ( isset( $seen[ intval( $row['id'] ) ] ) ) {
                    continue;
                }

                $rows[]                       = $row;
                $seen[ intval( $row['id'] ) ] = true;
            }

            if ( ! empty( $rows ) ) {
                $by_tire[ $tire_id ] = $rows;
            }
        }

        self::$matched_by_tire_memo = $by_tire;

        return $by_tire;
    }

    /** Request-level memo for get_matched_by_tire(); null = not computed. */
    private static $matched_by_tire_memo = null;

    /** Transient holding get_counts(), read on every admin screen for the menu badge. */
    const COUNTS_CACHE = 'rtg_candidate_status_counts';

    /**
     * Forget the memoized match table and the cached status counts. Called by
     * every method that writes candidate rows; guide-tire writes reach it via
     * RTG_Database::flush_cache().
     */
    public static function forget_matched_by_tire() {
        self::$matched_by_tire_memo = null;
        delete_transient( self::COUNTS_CACHE );
    }

    /**
     * Re-point stored matches at the guide as it stands now.
     *
     * A row's matched_tire_id is set when a sweep sees the product, so a tire
     * added or renamed in the guide since then leaves rows pointing at nothing
     * — and they stay in the review queue as "awaiting review" for a tire that
     * is already stocked. Re-keying every row against the current guide is one
     * query and a comparison, so it runs each sync rather than waiting for the
     * rotation to come back around to that size.
     *
     * A status a person set is left alone. Only the machine ones follow the
     * new match.
     *
     * @param array      $guide_index Match key => tire_id, from RTG_Catalog_Sync.
     * @param array|null $variants    Variant index, or null for exact keys only.
     * @return int Rows whose match changed.
     */
    public static function refresh_matches( $guide_index, $variants = null, $live_tire_ids = null ) {
        self::forget_matched_by_tire();
        global $wpdb;
        $table = self::table();

        $rows = $wpdb->get_results(
            "SELECT id, match_key, matched_tire_id, status, qualifies, availability, brand, model, size, load_index
             FROM {$table} WHERE match_key <> ''",
            ARRAY_A
        );

        $live    = is_array( $live_tire_ids ) ? array_flip( $live_tire_ids ) : null;
        $changed = 0;

        foreach ( $rows ?: array() as $row ) {
            $should = RTG_Catalog_Sync::resolve_guide_match(
                $row['brand'],
                $row['model'],
                $row['size'],
                $guide_index,
                $variants,
                $row['load_index']
            );

            if ( self::STATUS_IMPORTED === (string) $row['status'] ) {
                $changed += self::refresh_imported( $row, $should, $live );
                continue;
            }

            if ( $should === (string) $row['matched_tire_id'] ) {
                continue;
            }

            $data    = array( 'matched_tire_id' => $should );
            $formats = array( '%s' );

            if ( in_array( (string) $row['status'], self::MACHINE_STATUSES, true ) ) {
                // Same precedence as a sweep.
                $data['status'] = self::compute_status( $should, ! empty( $row['qualifies'] ), (string) ( $row['availability'] ?? '' ) );
                $formats[]      = '%s';
            }

            $wpdb->update( $table, $data, array( 'id' => intval( $row['id'] ) ), $formats, array( '%d' ) );
            $changed++;
        }

        // This WAS a reconcile pass — the discovery page's throttled one can
        // stand down for the window.
        set_transient( 'rtg_discovery_reconciled', 1, 10 * MINUTE_IN_SECONDS );

        return $changed;
    }

    /**
     * Keep one imported row honest about the tire it became.
     *
     * "Imported" is a statement of fact about the guide — a person added this
     * listing to it — and unlike a dismissal, that fact can stop being true.
     * Delete the tire and nothing brought the listing back: it was in neither
     * the guide nor the queue, which is the one way a tire can go missing
     * without anything saying so.
     *
     * Only the tire_id recorded at import decides it. Re-matching by name
     * would resurface every tire whose model was edited on the way in — a
     * routine thing to do, and not a deletion — where an id that is no longer
     * in the guide can only mean one thing.
     *
     * A row imported before ids were recorded has nothing to go on and is left
     * alone, except to note the tire it still matches, so it is covered the
     * next time round.
     *
     * @param array      $row    Candidate row.
     * @param string     $should The guide tire it matches now, or ''.
     * @param array|null $live   tire_id => any, for every tire in the guide;
     *                           null when the caller didn't say.
     * @return int 1 when the row changed, 0 when it didn't.
     */
    private static function refresh_imported( $row, $should, $live ) {
        global $wpdb;

        $recorded = (string) $row['matched_tire_id'];

        // Nothing recorded, but it matches a tire today — remember which, so a
        // later deletion is recognizable.
        if ( '' === $recorded ) {
            if ( '' === $should ) {
                return 0;
            }

            $wpdb->update(
                self::table(),
                array( 'matched_tire_id' => $should ),
                array( 'id' => intval( $row['id'] ) ),
                array( '%s' ),
                array( '%d' )
            );

            return 1;
        }

        if ( null === $live || isset( $live[ $recorded ] ) ) {
            return 0;
        }

        // The tire this became is gone from the guide, so the listing is
        // undecided again — back to whatever the rules make of it.
        $wpdb->update(
            self::table(),
            array(
                'matched_tire_id' => '',
                'status'          => self::compute_status( '', ! empty( $row['qualifies'] ), (string) ( $row['availability'] ?? '' ) ),
            ),
            array( 'id' => intval( $row['id'] ) ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return 1;
    }

    /**
     * Re-point every stored match at the guide as it stands right now.
     *
     * refresh_matches() runs inside a catalog sync, which is nightly. A tire
     * added or renamed between syncs left its listings sitting in the queue
     * under "awaiting review" for a tire already stocked — the admin's own
     * edit didn't reach the queue until the next run. Opening the queue
     * reconciles it, so what the page shows is the guide as it is, not as the
     * last sweep left it.
     *
     * @return int Rows whose match changed.
     */
    public static function reconcile_with_guide() {
        $tires = RTG_Database::get_all_tires();

        return self::refresh_matches(
            RTG_Catalog_Sync::build_guide_index(),
            RTG_Catalog_Sync::build_variant_index( $tires ),
            wp_list_pluck( $tires, 'tire_id' )
        );
    }

    /**
     * Which retailers carry each guide tire.
     *
     * @return array tire_id => list of advertiser names, de-duplicated.
     */
    public static function get_retailer_coverage() {
        $coverage = array();

        foreach ( self::get_matched_by_tire() as $tire_id => $candidates ) {
            $retailers = array();
            foreach ( $candidates as $candidate ) {
                $name = trim( (string) ( $candidate['advertiser_name'] ?? '' ) );
                if ( '' !== $name ) {
                    $retailers[ $name ] = true;
                }
            }

            $names = array_keys( $retailers );
            sort( $names );
            $coverage[ $tire_id ] = $names;
        }

        return $coverage;
    }

    /**
     * Remove every row from a brand the guide does not cover.
     *
     * The hide brand policy says such a tire is never listed. New ones are
     * never stored; this clears the ones stored before the policy, and any
     * left behind when a brand is taken off the list. Rows a person imported
     * are kept: they are the guide's own tires now, whatever their brand.
     *
     * @param array $covered_brands The brand list from Settings.
     * @return int Rows removed.
     */
    public static function purge_uncovered_brands( $covered_brands ) {
        global $wpdb;
        $table = self::table();

        $known = array();
        foreach ( (array) $covered_brands as $brand ) {
            $key = RTG_Tire_Qualifier::normalize_brand( $brand );
            if ( '' !== $key ) {
                $known[ $key ] = true;
            }
        }

        // An empty list would mean "every brand is uncovered": the same
        // guard prune() has against a settings read coming back empty.
        if ( empty( $known ) ) {
            return 0;
        }

        $brands = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT brand FROM {$table} WHERE status != %s",
            self::STATUS_IMPORTED
        ) );

        $removed = 0;
        foreach ( (array) $brands as $brand ) {
            if ( isset( $known[ RTG_Tire_Qualifier::normalize_brand( $brand ) ] ) ) {
                continue;
            }
            $deleted = $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table} WHERE brand = %s AND status != %s",
                $brand,
                self::STATUS_IMPORTED
            ) );
            $removed += false === $deleted ? 0 : intval( $deleted );
        }

        if ( $removed > 0 ) {
            self::forget_matched_by_tire();
        }

        return $removed;
    }

    /**
     * Delete near misses that can never become anything else.
     *
     * The near-miss pile exists so "why was this rejected?" stays answerable,
     * but two kinds of row answer no question worth keeping. A rejected row
     * in a fitment the guide doesn't stock can never qualify — wrong fitment
     * is definitionally permanent — and one accumulated eighteen thousand of
     * those. A rejected row unseen for two months describes a listing the
     * catalog itself dropped. Deleting either costs nothing visible: if the
     * product reappears, the next sweep re-files it identically.
     *
     * Only STATUS_REJECTED and STATUS_SOLD_OUT are ever touched. Dismissed
     * and imported rows are human decisions and are the memory that stops
     * things resurfacing; new rows are awaiting one. A sold-out row is
     * waiting on the retailer, and one the catalog itself dropped two months
     * ago is not coming back into stock.
     *
     * @param string[] $guide_sizes Canonical sizes the guide stocks.
     * @param int      $stale_days  Days unseen before a rejected or sold-out row goes.
     * @return array { off_fitment: int, stale: int }
     */
    public static function prune( $guide_sizes, $stale_days = 60 ) {
        self::forget_matched_by_tire();
        global $wpdb;
        $table = self::table();

        $normalized = array();
        foreach ( (array) $guide_sizes as $size ) {
            $key = RTG_Tire_Qualifier::normalize_size( $size );
            if ( '' !== $key ) {
                $normalized[] = $key;
            }
        }

        $out = array(
            'off_fitment' => 0,
            'stale'       => 0,
        );

        // With no sizes to compare against, "off-fitment" is undefined —
        // deleting everything because a settings read came back empty would
        // be the destructive version of every silent failure this feature
        // has had.
        if ( ! empty( $normalized ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $normalized ), '%s' ) );

            $off = $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table} WHERE status IN ( %s, %s ) AND size NOT IN ( {$placeholders} )",
                array_merge( array( self::STATUS_REJECTED, self::STATUS_SOLD_OUT ), $normalized )
            ) );

            $out['off_fitment'] = false === $off ? 0 : intval( $off );
        }

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, intval( $stale_days ) ) * DAY_IN_SECONDS ) );

        $stale = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE status IN ( %s, %s ) AND last_seen_at < %s",
            self::STATUS_REJECTED,
            self::STATUS_SOLD_OUT,
            $cutoff
        ) );

        $out['stale'] = false === $stale ? 0 : intval( $stale );

        return $out;
    }

    /**
     * Count candidates in one status, grouped by brand.
     *
     * The review queue's practical problem is volume, and volume clusters by
     * brand — a page of Winruns is one decision, not sixty. Counts make that
     * decision visible before anyone scrolls.
     *
     * @param string $status Status to count within.
     * @return array Brand => count, largest first.
     */
    public static function get_brand_counts( $status = self::STATUS_NEW ) {
        global $wpdb;
        $table = self::table();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT brand, COUNT(*) AS total FROM {$table} WHERE status = %s GROUP BY brand ORDER BY total DESC",
                $status
            ),
            ARRAY_A
        );

        $counts = array();
        foreach ( $rows ?: array() as $row ) {
            $counts[ (string) $row['brand'] ] = intval( $row['total'] );
        }

        return $counts;
    }

    /**
     * Apply one decision to every row a filter matches.
     *
     * Acts on the database query, not the visible page — "dismiss all
     * Winrun" means all of them, not the 200 the screen happened to show.
     * Restricted to moving rows out of (or back into) the queue: bulk can
     * only write dismissed or new, and only over queue, sold-out or
     * dismissed rows, so it can never overwrite an imported row or invent a
     * status. A restored row the retailer still lists as out of stock lands
     * in the sold-out tab, not the queue.
     *
     * @param array  $filter { status, brand, size, vehicle } — status required.
     * @param string $to     STATUS_DISMISSED or STATUS_NEW.
     * @return int Rows changed.
     */
    public static function bulk_set_status( $filter, $to ) {
        self::forget_matched_by_tire();
        global $wpdb;
        $table = self::table();

        if ( ! in_array( $to, array( self::STATUS_DISMISSED, self::STATUS_NEW ), true ) ) {
            return 0;
        }

        $from = (string) ( $filter['status'] ?? '' );

        // Only queue, sold-out and dismissed rows may move in bulk; everything
        // else either belongs to the machine's own classification or records
        // an import, and neither is a bulk decision.
        $movable = self::STATUS_DISMISSED === $to
            ? array( self::STATUS_NEW, self::STATUS_SOLD_OUT )
            : array( self::STATUS_DISMISSED );
        if ( ! in_array( $from, $movable, true ) ) {
            return 0;
        }

        if ( self::STATUS_NEW === $to ) {
            $marks      = implode( ',', array_fill( 0, count( self::OUT_OF_STOCK ), '%s' ) );
            $set        = "status = CASE WHEN availability IN ( {$marks} ) THEN %s ELSE %s END";
            $set_params = array_merge( self::OUT_OF_STOCK, array( self::STATUS_SOLD_OUT, self::STATUS_NEW ) );
        } else {
            $set        = 'status = %s';
            $set_params = array( $to );
        }

        $where  = array( 'status = %s' );
        $params = array( $from );

        if ( '' !== ( $filter['brand'] ?? '' ) ) {
            $where[]  = 'brand = %s';
            $params[] = $filter['brand'];
        }
        if ( '' !== ( $filter['size'] ?? '' ) ) {
            $where[]  = 'size = %s';
            $params[] = $filter['size'];
        }
        if ( '' !== ( $filter['vehicle'] ?? '' ) ) {
            $where[]  = 'FIND_IN_SET( %s, fits_vehicles )';
            $params[] = $filter['vehicle'];
        }

        $params = array_merge( $set_params, array( current_time( 'mysql' ) ), $params );

        $changed = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET {$set}, reviewed_at = %s WHERE " . implode( ' AND ', $where ),
            $params
        ) );

        return false === $changed ? 0 : intval( $changed );
    }

    /**
     * Record a human decision on a candidate.
     *
     * @param int    $id     Row ID.
     * @param string $status One of the STATUS_* constants.
     * @return bool Whether the row was updated.
     */
    public static function set_status( $id, $status, $tire_id = null ) {
        self::forget_matched_by_tire();
        global $wpdb;
        $table = self::table();

        $allowed = array(
            self::STATUS_NEW,
            self::STATUS_SOLD_OUT,
            self::STATUS_REJECTED,
            self::STATUS_EXISTING,
            self::STATUS_DISMISSED,
            self::STATUS_IMPORTED,
        );

        if ( ! in_array( $status, $allowed, true ) ) {
            return false;
        }

        $data = array(
            'status'      => $status,
            'reviewed_at' => current_time( 'mysql' ),
        );
        $formats = array( '%s', '%s' );

        // Which tire this listing became. Recorded on import so that deleting
        // that tire can bring the listing back to the queue rather than
        // stranding it in neither place.
        if ( null !== $tire_id ) {
            $data['matched_tire_id'] = (string) $tire_id;
            $formats[]               = '%s';
        }

        $updated = $wpdb->update(
            $table,
            $data,
            array( 'id' => intval( $id ) ),
            $formats,
            array( '%d' )
        );

        return false !== $updated;
    }

    /**
     * Decode the JSON columns and cast the numeric ones.
     *
     * @param array $row Raw database row.
     * @return array Row with fail_reasons/raw decoded and types normalized.
     */
    private static function hydrate( $row ) {
        $row['qualifies'] = ! empty( $row['qualifies'] );
        $row['price']     = floatval( $row['price'] );

        $row['fits_vehicles'] = array_values( array_filter(
            array_map( 'trim', explode( ',', (string) ( $row['fits_vehicles'] ?? '' ) ) ),
            'strlen'
        ) );
        $row['raw']       = json_decode( (string) ( $row['raw_json'] ?? '' ), true ) ?: array();

        // Rows written before warnings existed hold a bare array of failures;
        // newer ones hold { reasons, warnings }. Read both so an upgrade
        // doesn't blank the reasons already on screen.
        $decoded = json_decode( (string) ( $row['fail_reasons'] ?? '' ), true ) ?: array();
        if ( isset( $decoded['reasons'] ) || isset( $decoded['warnings'] ) ) {
            $row['fail_reasons'] = (array) ( $decoded['reasons'] ?? array() );
            $row['warnings']     = (array) ( $decoded['warnings'] ?? array() );
        } else {
            $row['fail_reasons'] = $decoded;
            $row['warnings']     = array();
        }

        unset( $row['raw_json'] );

        return $row;
    }
}
