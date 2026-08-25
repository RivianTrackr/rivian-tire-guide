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
     * Dismissed rows are included deliberately: dismissing a candidate means
     * "don't offer this as a new tire", not "stop pricing the tire I already
     * stock from it".
     *
     * @return array tire_id => candidate rows.
     */
    public static function get_matched_by_tire() {
        $by_key  = self::get_by_match_key();
        $by_tire = array();

        foreach ( RTG_Database::get_all_tires() as $tire ) {
            $key = RTG_Catalog_Sync::match_key(
                $tire['brand'] ?? '',
                $tire['model'] ?? '',
                $tire['size'] ?? ''
            );

            if ( '' !== $key && ! empty( $by_key[ $key ] ) ) {
                $by_tire[ (string) $tire['tire_id'] ] = $by_key[ $key ];
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
     * @param array $guide_index Match key => tire_id, from RTG_Catalog_Sync.
     * @return int Rows whose match changed.
     */
    public static function refresh_matches( $guide_index ) {
        global $wpdb;
        $table = self::table();

        $rows = $wpdb->get_results(
            "SELECT id, match_key, matched_tire_id, status, qualifies FROM {$table} WHERE match_key <> ''",
            ARRAY_A
        );

        $changed = 0;

        foreach ( $rows ?: array() as $row ) {
            $should = (string) ( $guide_index[ $row['match_key'] ] ?? '' );

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
