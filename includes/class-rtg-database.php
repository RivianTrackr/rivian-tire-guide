<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Database {

    private static function tires_table() {
        global $wpdb;
        return $wpdb->prefix . 'rtg_tires';
    }

    /**
     * Public accessor for the tires table name (for use in AJAX queries).
     */
    public static function tires_table_public() {
        return self::tires_table();
    }

    private static function ratings_table() {
        global $wpdb;
        return $wpdb->prefix . 'rtg_ratings';
    }

    private static $cache_key = 'rtg_all_tires';

    /**
     * Flush the tire data cache.
     */
    public static function flush_cache() {
        delete_transient( self::$cache_key );
    }

    // --- Tire CRUD ---

    public static function get_all_tires() {
        $cached = get_transient( self::$cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table = self::tires_table();
        $result = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A );

        set_transient( self::$cache_key, $result, HOUR_IN_SECONDS );
        return $result;
    }

    /**
     * Returns tires as a numerically-indexed array of arrays matching the CSV column order.
     * This is the format the frontend JS expects (same as PapaParse output).
     */
    public static function get_tires_as_array() {
        $tires = self::get_all_tires();
        $result = array();

        foreach ( $tires as $tire ) {
            $result[] = array(
                (string) $tire['tire_id'],
                (string) $tire['size'],
                (string) $tire['diameter'],
                (string) $tire['brand'],
                (string) $tire['model'],
                (string) $tire['category'],
                (string) $tire['price'],
                (string) $tire['mileage_warranty'],
                (string) $tire['weight_lb'],
                (string) $tire['three_pms'],
                (string) $tire['tread'],
                (string) $tire['load_index'],
                (string) $tire['max_load_lb'],
                (string) $tire['load_range'],
                (string) $tire['speed_rating'],
                (string) $tire['psi'],
                (string) $tire['utqg'],
                (string) $tire['tags'],
                (string) $tire['link'],
                (string) $tire['image'],
                (string) $tire['efficiency_score'],
                (string) $tire['efficiency_grade'],
                (string) $tire['bundle_link'],
                (string) $tire['review_link'],
                (string) $tire['created_at'],
            );
        }

        return $result;
    }

    public static function get_tire( $tire_id ) {
        global $wpdb;
        $table = self::tires_table();
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE tire_id = %s", $tire_id ),
            ARRAY_A
        );
    }

    public static function get_tire_by_id( $id ) {
        global $wpdb;
        $table = self::tires_table();
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public static function insert_tire( $data ) {
        global $wpdb;
        $table = self::tires_table();

        $defaults = array(
            'tire_id'          => '',
            'size'             => '',
            'diameter'         => '',
            'brand'            => '',
            'model'            => '',
            'category'         => '',
            'price'            => 0,
            'mileage_warranty' => 0,
            'weight_lb'        => 0,
            'three_pms'        => 'No',
            'tread'            => '',
            'load_index'       => '',
            'max_load_lb'      => 0,
            'load_range'       => '',
            'speed_rating'     => '',
            'psi'              => '',
            'utqg'             => '',
            'tags'             => '',
            'link'             => '',
            'image'            => '',
            'efficiency_score' => 0,
            'efficiency_grade' => '',
            'bundle_link'      => '',
            'review_link'      => '',
            'sort_order'       => 0,
        );

        $data = wp_parse_args( $data, $defaults );

        $formats = array(
            '%s', '%s', '%s', '%s', '%s', '%s',
            '%f', '%d', '%f',
            '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s',
            '%d', '%s', '%s', '%s',
            '%d',
        );

        $result = $wpdb->insert( $table, $data, $formats );
        if ( $result !== false ) {
            self::flush_cache();
            return $wpdb->insert_id;
        }
        return false;
    }

    public static function update_tire( $tire_id, $data ) {
        global $wpdb;
        $table = self::tires_table();

        // Remove fields that shouldn't be updated directly.
        unset( $data['id'], $data['created_at'], $data['updated_at'] );

        $formats = array();
        foreach ( $data as $key => $value ) {
            switch ( $key ) {
                case 'price':
                case 'weight_lb':
                    $formats[] = '%f';
                    break;
                case 'mileage_warranty':
                case 'max_load_lb':
                case 'efficiency_score':
                case 'sort_order':
                    $formats[] = '%d';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }

        $result = $wpdb->update( $table, $data, array( 'tire_id' => $tire_id ), $formats, array( '%s' ) );
        self::flush_cache();
        return $result;
    }

    public static function delete_tire( $tire_id ) {
        global $wpdb;
        $table = self::tires_table();
        $ratings = self::ratings_table();

        // Remove associated ratings first.
        $wpdb->delete( $ratings, array( 'tire_id' => $tire_id ), array( '%s' ) );

        $result = $wpdb->delete( $table, array( 'tire_id' => $tire_id ), array( '%s' ) );
        self::flush_cache();
        return $result;
    }

    public static function delete_tires( $tire_ids ) {
        global $wpdb;
        $table = self::tires_table();
        $ratings = self::ratings_table();

        if ( empty( $tire_ids ) ) {
            return 0;
        }

        $placeholders = implode( ', ', array_fill( 0, count( $tire_ids ), '%s' ) );

        // Remove associated ratings first.
        $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$ratings} WHERE tire_id IN ({$placeholders})", ...$tire_ids )
        );

        $result = $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$table} WHERE tire_id IN ({$placeholders})", ...$tire_ids )
        );
        self::flush_cache();
        return $result;
    }

    public static function get_tire_count( $search = '' ) {
        global $wpdb;
        $table = self::tires_table();

        if ( ! empty( $search ) ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE tire_id LIKE %s OR brand LIKE %s OR model LIKE %s",
                    '%' . $wpdb->esc_like( $search ) . '%',
                    '%' . $wpdb->esc_like( $search ) . '%',
                    '%' . $wpdb->esc_like( $search ) . '%'
                )
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    public static function search_tires( $search = '', $per_page = 20, $page = 1, $orderby = 'id', $order = 'ASC' ) {
        global $wpdb;
        $table = self::tires_table();

        $allowed_orderby = array( 'id', 'tire_id', 'brand', 'model', 'size', 'category', 'price', 'mileage_warranty', 'weight_lb', 'efficiency_score', 'efficiency_grade' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'id';
        }
        $order = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';

        $offset = max( 0, ( $page - 1 ) * $per_page );

        if ( ! empty( $search ) ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE tire_id LIKE %s OR brand LIKE %s OR model LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                    '%' . $wpdb->esc_like( $search ) . '%',
                    '%' . $wpdb->esc_like( $search ) . '%',
                    '%' . $wpdb->esc_like( $search ) . '%',
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    }

    public static function tire_id_exists( $tire_id, $exclude_id = 0 ) {
        global $wpdb;
        $table = self::tires_table();

        if ( $exclude_id > 0 ) {
            return (bool) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE tire_id = %s AND id != %d",
                    $tire_id,
                    $exclude_id
                )
            );
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE tire_id = %s", $tire_id )
        );
    }

    public static function get_next_tire_id() {
        global $wpdb;
        $table = self::tires_table();
        $max = $wpdb->get_var( "SELECT MAX(CAST(SUBSTRING(tire_id, 5) AS UNSIGNED)) FROM {$table} WHERE tire_id LIKE 'tire%'" );
        $next = $max ? (int) $max + 1 : 1;
        return sprintf( 'tire%03d', $next );
    }

    /**
     * Server-side filtered + paginated query for the frontend AJAX endpoint.
     *
     * @param array $filters {
     *     @type string $search   Free text search.
     *     @type string $size     Exact size match.
     *     @type string $brand    Exact brand match.
     *     @type string $category Exact category match.
     *     @type bool   $three_pms Filter to 3PMS-rated only.
     *     @type bool   $ev_rated  Filter to EV Rated tags.
     *     @type bool   $studded   Filter to Studded Available tags.
     *     @type float  $price_max Max price.
     *     @type int    $warranty_min Min mileage warranty.
     *     @type float  $weight_max  Max weight.
     * }
     * @param string $sort       Sort key.
     * @param int    $page       Page number (1-based).
     * @param int    $per_page   Results per page.
     * @return array { 'rows' => array[], 'total' => int }
     */
    public static function get_filtered_tires( $filters = array(), $sort = 'efficiency_score', $page = 1, $per_page = 12 ) {
        global $wpdb;
        $table = self::tires_table();

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $filters['search'] ) ) {
            $like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $where[]  = '( brand LIKE %s OR model LIKE %s OR tire_id LIKE %s OR tags LIKE %s )';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        if ( ! empty( $filters['size'] ) ) {
            $where[]  = 'size = %s';
            $values[] = $filters['size'];
        }

        if ( ! empty( $filters['brand'] ) ) {
            $where[]  = 'brand = %s';
            $values[] = $filters['brand'];
        }

        if ( ! empty( $filters['category'] ) ) {
            $where[]  = 'category = %s';
            $values[] = $filters['category'];
        }

        if ( ! empty( $filters['three_pms'] ) ) {
            $where[] = "LOWER(three_pms) = 'yes'";
        }

        if ( ! empty( $filters['ev_rated'] ) ) {
            $where[] = "LOWER(tags) LIKE '%ev rated%'";
        }

        if ( ! empty( $filters['studded'] ) ) {
            $where[] = "LOWER(tags) LIKE '%studded available%'";
        }

        if ( isset( $filters['price_max'] ) && $filters['price_max'] < 600 ) {
            $where[]  = 'price <= %f';
            $values[] = floatval( $filters['price_max'] );
        }

        if ( isset( $filters['warranty_min'] ) && $filters['warranty_min'] > 0 ) {
            $where[]  = 'mileage_warranty >= %d';
            $values[] = intval( $filters['warranty_min'] );
        }

        if ( isset( $filters['weight_max'] ) && $filters['weight_max'] < 70 ) {
            $where[]  = 'weight_lb <= %f';
            $values[] = floatval( $filters['weight_max'] );
        }

        $where_sql = implode( ' AND ', $where );

        // Determine ORDER BY.
        $sort_map = array(
            'efficiency_score' => 'efficiency_score DESC',
            'price-asc'        => 'price ASC',
            'price-desc'       => 'price DESC',
            'warranty-desc'    => 'mileage_warranty DESC',
            'weight-asc'       => 'weight_lb ASC',
            'newest'           => 'created_at DESC',
        );
        $order_sql = isset( $sort_map[ $sort ] ) ? $sort_map[ $sort ] : 'efficiency_score DESC';

        // Count.
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        if ( ! empty( $values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, ...$values );
        }
        $total = (int) $wpdb->get_var( $count_sql );

        // Data.
        $offset   = max( 0, ( $page - 1 ) * $per_page );
        $data_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d";
        $all_vals = array_merge( $values, array( $per_page, $offset ) );
        $rows     = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$all_vals ), ARRAY_A );

        // Convert to frontend array format.
        $result = array();
        foreach ( $rows as $tire ) {
            $result[] = array(
                (string) $tire['tire_id'],
                (string) $tire['size'],
                (string) $tire['diameter'],
                (string) $tire['brand'],
                (string) $tire['model'],
                (string) $tire['category'],
                (string) $tire['price'],
                (string) $tire['mileage_warranty'],
                (string) $tire['weight_lb'],
                (string) $tire['three_pms'],
                (string) $tire['tread'],
                (string) $tire['load_index'],
                (string) $tire['max_load_lb'],
                (string) $tire['load_range'],
                (string) $tire['speed_rating'],
                (string) $tire['psi'],
                (string) $tire['utqg'],
                (string) $tire['tags'],
                (string) $tire['link'],
                (string) $tire['image'],
                (string) $tire['efficiency_score'],
                (string) $tire['efficiency_grade'],
                (string) $tire['bundle_link'],
                (string) $tire['review_link'],
                (string) $tire['created_at'],
            );
        }

        return array(
            'rows'  => $result,
            'total' => $total,
        );
    }

    // --- Efficiency Calculation ---

    /**
     * Calculate efficiency score and grade from tire data.
     *
     * Replicates the Google Sheet formula: weighted combination of
     * width, weight, tread depth, load range, speed rating, UTQG,
     * category, and 3PMS certification.
     *
     * @param array $data Tire data array.
     * @return array ['efficiency_score' => int, 'efficiency_grade' => string]
     */
    public static function calculate_efficiency( $data ) {
        // Width score: extract width from size (e.g., "275/60R20" → 275).
        // Missing data defaults to 0.5 (neutral).
        $width_val = 0;
        $size = $data['size'] ?? '';
        if ( ! empty( $size ) && strpos( $size, '/' ) !== false ) {
            $width_val = floatval( substr( $size, 0, strpos( $size, '/' ) ) );
        }
        $width_score = $width_val > 0 ? ( 305 - $width_val ) / 30 : 0.5;

        // Weight score. Missing data defaults to 0.5 (neutral).
        $weight = floatval( $data['weight_lb'] ?? 0 );
        $weight_score = $weight > 0 ? ( 70 - $weight ) / 40 : 0.5;

        // Tread score: extract numerator from tread (e.g., "10/32" → 10).
        // Missing data defaults to 0.5 (neutral).
        $tread_val = 0;
        $tread = $data['tread'] ?? '';
        if ( ! empty( $tread ) && strpos( $tread, '/' ) !== false ) {
            $tread_val = floatval( substr( $tread, 0, strpos( $tread, '/' ) ) );
        }
        $tread_score = $tread_val > 0 ? ( 20 - $tread_val ) / 11 : 0.5;

        // Load range score.
        $load_range = strtoupper( trim( $data['load_range'] ?? '' ) );
        $load_scores = array( 'SL' => 1, 'HL' => 0.9, 'XL' => 0.9, 'RF' => 0.7, 'D' => 0.3, 'E' => 0, 'F' => 0 );
        $load_score = isset( $load_scores[ $load_range ] ) ? $load_scores[ $load_range ] : 0.5;

        // Speed rating score (first character).
        $speed_raw = trim( $data['speed_rating'] ?? '' );
        $speed_char = ! empty( $speed_raw ) ? strtoupper( substr( $speed_raw, 0, 1 ) ) : '';
        $speed_scores = array( 'P' => 1, 'Q' => 0.95, 'R' => 0.9, 'S' => 0.85, 'T' => 0.8, 'H' => 0.7, 'V' => 0.6 );
        $speed_score = ! empty( $speed_char ) && isset( $speed_scores[ $speed_char ] ) ? $speed_scores[ $speed_char ] : 0.5;

        // UTQG score (first number from e.g., "620 A B").
        $utqg_val = 0;
        $utqg = trim( $data['utqg'] ?? '' );
        if ( ! empty( $utqg ) ) {
            $parts = explode( ' ', $utqg );
            $utqg_val = intval( $parts[0] );
        }
        $utqg_score = $utqg_val === 0 ? 0.5 : ( $utqg_val - 420 ) / 400;

        // Category score.
        $category = $data['category'] ?? '';
        $cat_scores = array(
            'All-Season'    => 1,
            'Performance'   => 1,
            'Highway'       => 1,
            'All-Terrain'   => 0.5,
            'Rugged Terrain' => 0.25,
            'Mud-Terrain'   => 0,
            'Winter'        => 0,
        );
        $cat_score = isset( $cat_scores[ $category ] ) ? $cat_scores[ $category ] : 0.5;

        // 3PMS score (No = better for efficiency).
        $pms_score = ( $data['three_pms'] ?? 'No' ) === 'No' ? 1 : 0;

        // Weighted total (weights sum to 1.0).
        $total = (
            $weight_score * 0.26 +
            $tread_score  * 0.16 +
            $load_score   * 0.16 +
            $speed_score  * 0.10 +
            $utqg_score   * 0.10 +
            $cat_score    * 0.10 +
            $pms_score    * 0.08 +
            $width_score  * 0.04
        );

        // Clamp to 0–100.
        $score = max( 0, min( 100, (int) round( $total * 100 ) ) );

        // Determine grade.
        if ( $score >= 80 ) {
            $grade = 'A';
        } elseif ( $score >= 65 ) {
            $grade = 'B';
        } elseif ( $score >= 50 ) {
            $grade = 'C';
        } elseif ( $score >= 35 ) {
            $grade = 'D';
        } elseif ( $score >= 20 ) {
            $grade = 'E';
        } else {
            $grade = 'F';
        }

        return array(
            'efficiency_score' => $score,
            'efficiency_grade' => $grade,
        );
    }

    // --- Ratings ---

    public static function get_tire_ratings( $tire_ids ) {
        global $wpdb;
        $table = self::ratings_table();

        if ( empty( $tire_ids ) ) {
            return array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $tire_ids ), '%s' ) );
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tire_id, AVG(rating) as average, COUNT(*) as count, SUM(CASE WHEN review_text != '' THEN 1 ELSE 0 END) as review_count FROM {$table} WHERE tire_id IN ({$placeholders}) GROUP BY tire_id",
                ...$tire_ids
            ),
            ARRAY_A
        );

        $ratings = array();
        foreach ( $results as $row ) {
            $ratings[ $row['tire_id'] ] = array(
                'average'      => round( (float) $row['average'], 1 ),
                'count'        => (int) $row['count'],
                'review_count' => (int) $row['review_count'],
            );
        }

        return $ratings;
    }

    public static function get_user_ratings( $tire_ids, $user_id ) {
        global $wpdb;
        $table = self::ratings_table();

        if ( empty( $tire_ids ) || ! $user_id ) {
            return array();
        }

        $placeholders = implode( ', ', array_fill( 0, count( $tire_ids ), '%s' ) );
        $args = array_merge( $tire_ids, array( $user_id ) );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tire_id, rating, review_title, review_text FROM {$table} WHERE tire_id IN ({$placeholders}) AND user_id = %d",
                ...$args
            ),
            ARRAY_A
        );

        $ratings = array();
        foreach ( $results as $row ) {
            $ratings[ $row['tire_id'] ] = array(
                'rating'       => (int) $row['rating'],
                'review_title' => $row['review_title'],
                'review_text'  => $row['review_text'],
            );
        }

        return $ratings;
    }

    public static function get_rating_count( $search = '' ) {
        global $wpdb;
        $table = self::ratings_table();
        $tires = self::tires_table();

        if ( ! empty( $search ) ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} r LEFT JOIN {$tires} t ON r.tire_id = t.tire_id WHERE r.tire_id LIKE %s OR t.brand LIKE %s OR t.model LIKE %s",
                    $like,
                    $like,
                    $like
                )
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    public static function search_ratings( $search = '', $per_page = 20, $page = 1, $orderby = 'r.created_at', $order = 'DESC' ) {
        global $wpdb;
        $table = self::ratings_table();
        $tires = self::tires_table();

        $allowed_orderby = array( 'r.id', 'r.tire_id', 'r.rating', 'r.created_at', 't.brand', 't.model' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'r.created_at';
        }
        $order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';
        $offset = max( 0, ( $page - 1 ) * $per_page );

        if ( ! empty( $search ) ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, t.brand, t.model FROM {$table} r LEFT JOIN {$tires} t ON r.tire_id = t.tire_id WHERE r.tire_id LIKE %s OR t.brand LIKE %s OR t.model LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                    $like,
                    $like,
                    $like,
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, t.brand, t.model FROM {$table} r LEFT JOIN {$tires} t ON r.tire_id = t.tire_id ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    }

    public static function get_ratings_summary() {
        global $wpdb;
        $table = self::ratings_table();

        return $wpdb->get_row(
            "SELECT COUNT(*) as total, COUNT(DISTINCT tire_id) as tires_rated, COUNT(DISTINCT user_id) as unique_users, ROUND(AVG(rating), 1) as avg_rating FROM {$table}",
            ARRAY_A
        );
    }

    public static function delete_rating( $id ) {
        global $wpdb;
        $table = self::ratings_table();
        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }

    // --- Wheels (Stock Wheel Guide) ---

    private static function wheels_table() {
        global $wpdb;
        return $wpdb->prefix . 'rtg_wheels';
    }

    public static function get_all_wheels() {
        global $wpdb;
        $table = self::wheels_table();
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A );
    }

    public static function get_wheel( $id ) {
        global $wpdb;
        $table = self::wheels_table();
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public static function insert_wheel( $data ) {
        global $wpdb;
        $table = self::wheels_table();

        $defaults = array(
            'name'       => '',
            'stock_size' => '',
            'alt_sizes'  => '',
            'image'      => '',
            'vehicles'   => '',
            'sort_order' => 0,
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            $table,
            $data,
            array( '%s', '%s', '%s', '%s', '%s', '%d' )
        );

        return $result !== false ? $wpdb->insert_id : false;
    }

    public static function update_wheel( $id, $data ) {
        global $wpdb;
        $table = self::wheels_table();

        unset( $data['id'], $data['created_at'], $data['updated_at'] );

        $formats = array();
        foreach ( $data as $key => $value ) {
            $formats[] = $key === 'sort_order' ? '%d' : '%s';
        }

        return $wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
    }

    public static function delete_wheel( $id ) {
        global $wpdb;
        $table = self::wheels_table();
        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }

    public static function get_wheel_count() {
        global $wpdb;
        $table = self::wheels_table();
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    public static function set_rating( $tire_id, $user_id, $rating, $review_title = '', $review_text = '' ) {
        global $wpdb;
        $table = self::ratings_table();

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE tire_id = %s AND user_id = %d",
                $tire_id,
                $user_id
            )
        );

        if ( $existing ) {
            return $wpdb->update(
                $table,
                array(
                    'rating'       => $rating,
                    'review_title' => $review_title,
                    'review_text'  => $review_text,
                ),
                array( 'tire_id' => $tire_id, 'user_id' => $user_id ),
                array( '%d', '%s', '%s' ),
                array( '%s', '%d' )
            );
        }

        return $wpdb->insert(
            $table,
            array(
                'tire_id'      => $tire_id,
                'user_id'      => $user_id,
                'rating'       => $rating,
                'review_title' => $review_title,
                'review_text'  => $review_text,
            ),
            array( '%s', '%d', '%d', '%s', '%s' )
        );
    }

    /**
     * Get reviews (ratings with text) for a specific tire.
     *
     * @param string $tire_id Tire identifier.
     * @param int    $limit   Max reviews to return.
     * @param int    $offset  Offset for pagination.
     * @return array Reviews with user display names.
     */
    public static function get_tire_reviews( $tire_id, $limit = 20, $offset = 0 ) {
        global $wpdb;
        $table = self::ratings_table();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, tire_id, user_id, rating, review_title, review_text, created_at
                 FROM {$table}
                 WHERE tire_id = %s AND review_text != ''
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $tire_id,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return array();
        }

        // Map user IDs to display names.
        $user_ids = array_unique( array_column( $rows, 'user_id' ) );
        $user_map = array();
        if ( ! empty( $user_ids ) ) {
            $users = get_users( array( 'include' => $user_ids, 'fields' => array( 'ID', 'display_name' ) ) );
            foreach ( $users as $user ) {
                $user_map[ $user->ID ] = $user->display_name;
            }
        }

        foreach ( $rows as &$row ) {
            $row['display_name'] = $user_map[ $row['user_id'] ] ?? 'Anonymous';
        }

        return $rows;
    }

    /**
     * Count reviews (ratings with text) for a specific tire.
     *
     * @param string $tire_id Tire identifier.
     * @return int Review count.
     */
    public static function get_tire_review_count( $tire_id ) {
        global $wpdb;
        $table = self::ratings_table();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE tire_id = %s AND review_text != ''",
                $tire_id
            )
        );
    }

    /**
     * Get the current user's review for a specific tire (if any).
     *
     * @param string $tire_id Tire identifier.
     * @param int    $user_id WordPress user ID.
     * @return array|null Review data or null.
     */
    public static function get_user_review( $tire_id, $user_id ) {
        global $wpdb;
        $table = self::ratings_table();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT rating, review_title, review_text FROM {$table} WHERE tire_id = %s AND user_id = %d",
                $tire_id,
                $user_id
            ),
            ARRAY_A
        );
    }
}
