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
            'raw_json'        => wp_json_encode( $row['raw'] ?? array() ),
            'last_seen_at'    => $now,
        );

        $formats = array(
            '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s',
            '%f', '%s', '%s',
            '%s', '%d', '%s', '%s', '%s',
            '%s',
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
     *     @type int    $limit   Maximum rows. Default 200.
     *     @type int    $offset  Rows to skip. Default 0.
     * }
     * @return array[] Candidate rows, newest first.
     */
    public static function query( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $args = wp_parse_args( $args, array(
            'status' => self::STATUS_NEW,
            'size'   => '',
            'source' => '',
            'limit'  => 200,
            'offset' => 0,
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
