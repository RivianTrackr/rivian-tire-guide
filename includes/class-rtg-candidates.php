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
    const MACHINE_STATUSES = array( self::STATUS_NEW, self::STATUS_REJECTED, self::STATUS_EXISTING );

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
        //
        // Matching a guide tire settles it first, ahead of qualification. A
        // product you already stock is "already in the guide" whatever the
        // rules make of the listing's own wording; judging it first would file
        // a tire you own under near misses because its listing happened to omit
        // a load index, which is both untrue and unhelpful.
        if ( ! empty( $row['matched_tire_id'] ) ) {
            $computed_status = self::STATUS_EXISTING;
        } elseif ( ! empty( $row['qualifies'] ) ) {
            $computed_status = self::STATUS_NEW;
        } else {
            $computed_status = self::STATUS_REJECTED;
        }

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
            '%f', '%s', '%s',
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
     * Count candidates grouped by status.
     *
     * @return array Status slug => count, with every known status present.
     */
    public static function get_counts() {
        global $wpdb;
        $table = self::table();

        $counts = array(
            self::STATUS_NEW       => 0,
            self::STATUS_REJECTED  => 0,
            self::STATUS_EXISTING  => 0,
            self::STATUS_DISMISSED => 0,
            self::STATUS_IMPORTED  => 0,
        );

        $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );

        foreach ( $rows ?: array() as $row ) {
            $counts[ $row['status'] ] = intval( $row['total'] );
        }

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
                    $variants
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

        return $by_tire;
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
    public static function refresh_matches( $guide_index, $variants = null ) {
        global $wpdb;
        $table = self::table();

        $rows = $wpdb->get_results(
            "SELECT id, match_key, matched_tire_id, status, qualifies, brand, model, size
             FROM {$table} WHERE match_key <> ''",
            ARRAY_A
        );

        $changed = 0;

        foreach ( $rows ?: array() as $row ) {
            $should = RTG_Catalog_Sync::resolve_guide_match(
                $row['brand'],
                $row['model'],
                $row['size'],
                $guide_index,
                $variants
            );

            if ( $should === (string) $row['matched_tire_id'] ) {
                continue;
            }

            $data    = array( 'matched_tire_id' => $should );
            $formats = array( '%s' );

            if ( in_array( (string) $row['status'], self::MACHINE_STATUSES, true ) ) {
                // Same precedence as a sweep: matching the guide settles the
                // status ahead of whatever the rules made of the listing.
                if ( '' !== $should ) {
                    $data['status'] = self::STATUS_EXISTING;
                } elseif ( ! empty( $row['qualifies'] ) ) {
                    $data['status'] = self::STATUS_NEW;
                } else {
                    $data['status'] = self::STATUS_REJECTED;
                }
                $formats[] = '%s';
            }

            $wpdb->update( $table, $data, array( 'id' => intval( $row['id'] ) ), $formats, array( '%d' ) );
            $changed++;
        }

        return $changed;
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
            RTG_Catalog_Sync::build_variant_index( $tires )
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
     * Only STATUS_REJECTED is ever touched. Dismissed and imported rows are
     * human decisions and are the memory that stops things resurfacing; new
     * rows are awaiting one.
     *
     * @param string[] $guide_sizes Canonical sizes the guide stocks.
     * @param int      $stale_days  Days unseen before a rejected row goes.
     * @return array { off_fitment: int, stale: int }
     */
    public static function prune( $guide_sizes, $stale_days = 60 ) {
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
                "DELETE FROM {$table} WHERE status = %s AND size NOT IN ( {$placeholders} )",
                array_merge( array( self::STATUS_REJECTED ), $normalized )
            ) );

            $out['off_fitment'] = false === $off ? 0 : intval( $off );
        }

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, intval( $stale_days ) ) * DAY_IN_SECONDS ) );

        $stale = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE status = %s AND last_seen_at < %s",
            self::STATUS_REJECTED,
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
     * only write dismissed or new, and only over machine-held or dismissed
     * rows, so it can never overwrite an imported row or invent a status.
     *
     * @param array  $filter { status, brand, size, vehicle } — status required.
     * @param string $to     STATUS_DISMISSED or STATUS_NEW.
     * @return int Rows changed.
     */
    public static function bulk_set_status( $filter, $to ) {
        global $wpdb;
        $table = self::table();

        if ( ! in_array( $to, array( self::STATUS_DISMISSED, self::STATUS_NEW ), true ) ) {
            return 0;
        }

        $from = (string) ( $filter['status'] ?? '' );

        // Only queue rows and dismissed rows may move in bulk; everything
        // else either belongs to the machine's own classification or records
        // an import, and neither is a bulk decision.
        if ( ! in_array( $from, array( self::STATUS_NEW, self::STATUS_DISMISSED ), true ) || $from === $to ) {
            return 0;
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

        array_unshift( $params, $to, current_time( 'mysql' ) );

        $changed = $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = %s, reviewed_at = %s WHERE " . implode( ' AND ', $where ),
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
    public static function set_status( $id, $status ) {
        global $wpdb;
        $table = self::table();

        $allowed = array(
            self::STATUS_NEW,
            self::STATUS_REJECTED,
            self::STATUS_EXISTING,
            self::STATUS_DISMISSED,
            self::STATUS_IMPORTED,
        );

        if ( ! in_array( $status, $allowed, true ) ) {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            array(
                'status'      => $status,
                'reviewed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => intval( $id ) ),
            array( '%s', '%s' ),
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
