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
        RTG_AI::flush_cache();
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

    public static function get_tire_count( $search = '', $admin_filters = array() ) {
        global $wpdb;
        $table = self::tires_table();

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $search ) ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '( tire_id LIKE %s OR brand LIKE %s OR model LIKE %s OR tags LIKE %s )';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        if ( ! empty( $admin_filters['brand'] ) ) {
            $where[]  = 'brand = %s';
            $values[] = $admin_filters['brand'];
        }

        if ( ! empty( $admin_filters['size'] ) ) {
            $where[]  = 'size = %s';
            $values[] = $admin_filters['size'];
        }

        if ( ! empty( $admin_filters['category'] ) ) {
            $where[]  = 'category = %s';
            $values[] = $admin_filters['category'];
        }

        $where_sql = implode( ' AND ', $where );

        if ( ! empty( $values ) ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$values )
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
    }

    public static function search_tires( $search = '', $per_page = 20, $page = 1, $orderby = 'id', $order = 'ASC', $admin_filters = array() ) {
        global $wpdb;
        $table = self::tires_table();

        $allowed_orderby = array( 'id', 'tire_id', 'brand', 'model', 'size', 'category', 'price', 'mileage_warranty', 'weight_lb', 'efficiency_score', 'efficiency_grade', 'load_index' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'id';
        }
        $order = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';

        $offset = max( 0, ( $page - 1 ) * $per_page );

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $search ) ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '( tire_id LIKE %s OR brand LIKE %s OR model LIKE %s OR tags LIKE %s )';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        if ( ! empty( $admin_filters['brand'] ) ) {
            $where[]  = 'brand = %s';
            $values[] = $admin_filters['brand'];
        }

        if ( ! empty( $admin_filters['size'] ) ) {
            $where[]  = 'size = %s';
            $values[] = $admin_filters['size'];
        }

        if ( ! empty( $admin_filters['category'] ) ) {
            $where[]  = 'category = %s';
            $values[] = $admin_filters['category'];
        }

        $where_sql = implode( ' AND ', $where );
        $values[]  = $per_page;
        $values[]  = $offset;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                ...$values
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
                (string) $tire['review_link'],
                (string) $tire['created_at'],
            );
        }

        return array(
            'rows'  => $result,
            'total' => $total,
        );
    }

    /**
     * Get distinct values for a column (for admin filter dropdowns).
     *
     * @param string $column Column name.
     * @return array Sorted list of distinct non-empty values.
     */
    public static function get_distinct_values( $column ) {
        global $wpdb;
        $table = self::tires_table();

        $allowed = array( 'brand', 'size', 'category', 'diameter', 'load_range', 'speed_rating' );
        if ( ! in_array( $column, $allowed, true ) ) {
            return array();
        }

        return $wpdb->get_col( "SELECT DISTINCT {$column} FROM {$table} WHERE {$column} != '' ORDER BY {$column} ASC" );
    }

    /**
     * Get all statistics for the admin dashboard.
     *
     * @return array Dashboard statistics.
     */
    public static function get_dashboard_stats() {
        global $wpdb;
        $tires_table   = self::tires_table();
        $ratings_table = self::ratings_table();

        $stats = array();

        // Core tire aggregates.
        $stats['core'] = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_tires,
                ROUND(AVG(CASE WHEN price > 0 THEN price ELSE NULL END), 2) as avg_price,
                ROUND(AVG(CASE WHEN efficiency_score > 0 THEN efficiency_score ELSE NULL END), 0) as avg_efficiency,
                MIN(CASE WHEN price > 0 THEN price ELSE NULL END) as min_price,
                MAX(price) as max_price,
                MIN(CASE WHEN weight_lb > 0 THEN weight_lb ELSE NULL END) as min_weight,
                MAX(weight_lb) as max_weight,
                ROUND(AVG(CASE WHEN weight_lb > 0 THEN weight_lb ELSE NULL END), 1) as avg_weight,
                SUM(CASE WHEN image = '' OR image IS NULL THEN 1 ELSE 0 END) as missing_images,
                SUM(CASE WHEN link = '' OR link IS NULL THEN 1 ELSE 0 END) as missing_links
            FROM {$tires_table}",
            ARRAY_A
        );

        // Tires by category.
        $stats['by_category'] = $wpdb->get_results(
            "SELECT category, COUNT(*) as count
             FROM {$tires_table}
             WHERE category != ''
             GROUP BY category
             ORDER BY count DESC",
            ARRAY_A
        );

        // Tires by brand (top 10).
        $stats['by_brand'] = $wpdb->get_results(
            "SELECT brand, COUNT(*) as count
             FROM {$tires_table}
             WHERE brand != ''
             GROUP BY brand
             ORDER BY count DESC
             LIMIT 10",
            ARRAY_A
        );

        // Tires by size.
        $stats['by_size'] = $wpdb->get_results(
            "SELECT size, COUNT(*) as count
             FROM {$tires_table}
             WHERE size != ''
             GROUP BY size
             ORDER BY count DESC",
            ARRAY_A
        );

        // Efficiency grade distribution.
        $stats['by_grade'] = $wpdb->get_results(
            "SELECT efficiency_grade, COUNT(*) as count
             FROM {$tires_table}
             WHERE efficiency_grade != ''
             GROUP BY efficiency_grade
             ORDER BY FIELD(efficiency_grade, 'A', 'B', 'C', 'D', 'E', 'F')",
            ARRAY_A
        );

        // Ratings summary.
        $stats['ratings'] = $wpdb->get_row(
            "SELECT COUNT(*) as total_ratings,
                    ROUND(AVG(rating), 1) as avg_rating
             FROM {$ratings_table}",
            ARRAY_A
        );

        // Top rated tires (top 5 by average rating, min 1 rating).
        $stats['top_rated'] = $wpdb->get_results(
            "SELECT t.tire_id, t.brand, t.model, t.image,
                    ROUND(AVG(r.rating), 1) as avg_rating,
                    COUNT(r.id) as rating_count
             FROM {$ratings_table} r
             INNER JOIN {$tires_table} t ON r.tire_id = t.tire_id
             GROUP BY r.tire_id
             HAVING rating_count >= 1
             ORDER BY avg_rating DESC, rating_count DESC
             LIMIT 5",
            ARRAY_A
        );

        // Most reviewed tires (top 5 by approved review count).
        $stats['most_reviewed'] = $wpdb->get_results(
            "SELECT t.tire_id, t.brand, t.model, t.image,
                    COUNT(r.id) as review_count,
                    ROUND(AVG(r.rating), 1) as avg_rating
             FROM {$ratings_table} r
             INNER JOIN {$tires_table} t ON r.tire_id = t.tire_id
             WHERE r.review_text != '' AND r.review_status = 'approved'
             GROUP BY r.tire_id
             ORDER BY review_count DESC
             LIMIT 5",
            ARRAY_A
        );

        // Pending reviews count.
        $stats['pending_reviews'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$ratings_table}
             WHERE review_text != '' AND review_status = 'pending'"
        );

        // Recently added tires (last 5).
        $stats['recent_tires'] = $wpdb->get_results(
            "SELECT tire_id, brand, model, category, image, created_at
             FROM {$tires_table}
             ORDER BY created_at DESC
             LIMIT 5",
            ARRAY_A
        );

        // Affiliate link coverage.
        $affiliate_domains = RTG_Admin::get_affiliate_domains();
        if ( ! empty( $affiliate_domains ) ) {
            $like_clauses = array();
            $values       = array();
            foreach ( $affiliate_domains as $domain ) {
                $like_clauses[] = 'link LIKE %s';
                $values[]       = '%' . $wpdb->esc_like( $domain ) . '%';
            }
            $where = implode( ' OR ', $like_clauses );
            $stats['affiliate_count'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tires_table} WHERE {$where}",
                    ...$values
                )
            );
        } else {
            $stats['affiliate_count'] = 0;
        }

        return $stats;
    }

    /**
     * Get all unique tags used across tires.
     *
     * @return array Sorted list of unique tag strings.
     */
    public static function get_all_tags() {
        global $wpdb;
        $table = self::tires_table();
        $rows = $wpdb->get_col( "SELECT DISTINCT tags FROM {$table} WHERE tags != ''" );

        $tags = array();
        foreach ( $rows as $tag_string ) {
            $parts = array_map( 'trim', explode( ',', $tag_string ) );
            foreach ( $parts as $part ) {
                if ( $part !== '' ) {
                    $tags[ $part ] = true;
                }
            }
        }

        $result = array_keys( $tags );
        sort( $result );
        return $result;
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
        } else {
            $grade = 'F';
        }

        return array(
            'efficiency_score' => $score,
            'efficiency_grade' => $grade,
        );
    }

    /**
     * Recalculate efficiency score and grade for all tires.
     *
     * Useful when the algorithm changes or data gets out of sync.
     *
     * @return int Number of tires updated.
     */
    public static function recalculate_all_efficiency() {
        $tires = self::get_all_tires();
        $count = 0;

        foreach ( $tires as $tire ) {
            $efficiency = self::calculate_efficiency( $tire );
            if (
                (int) $tire['efficiency_score'] !== $efficiency['efficiency_score'] ||
                $tire['efficiency_grade'] !== $efficiency['efficiency_grade']
            ) {
                self::update_tire( $tire['tire_id'], array(
                    'efficiency_score' => $efficiency['efficiency_score'],
                    'efficiency_grade' => $efficiency['efficiency_grade'],
                ) );
                $count++;
            }
        }

        self::flush_cache();
        return $count;
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
                "SELECT tire_id, AVG(rating) as average, COUNT(*) as count, SUM(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) as review_count FROM {$table} WHERE tire_id IN ({$placeholders}) GROUP BY tire_id",
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

    /**
     * Get summary statistics for all reviews.
     *
     * @return array { 'total' => int, 'tires_rated' => int, 'unique_users' => int, 'avg_rating' => float }
     */
    public static function get_review_summary() {
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

        // Determine review status: admins auto-approve, others pending.
        $has_review_content = ! empty( $review_text ) || ! empty( $review_title );
        $review_status      = 'approved';
        if ( $has_review_content && ! user_can( $user_id, 'manage_options' ) ) {
            $review_status = 'pending';
        }

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE tire_id = %s AND user_id = %d",
                $tire_id,
                $user_id
            )
        );

        if ( $existing ) {
            $update_data = array(
                'rating'       => $rating,
                'review_title' => $review_title,
                'review_text'  => $review_text,
            );
            $update_formats = array( '%d', '%s', '%s' );

            // Reset status to pending whenever review content is present (re-moderation on edit).
            if ( $has_review_content ) {
                $update_data['review_status'] = $review_status;
                $update_formats[]             = '%s';
            }

            return $wpdb->update(
                $table,
                $update_data,
                array( 'tire_id' => $tire_id, 'user_id' => $user_id ),
                $update_formats,
                array( '%s', '%d' )
            );
        }

        return $wpdb->insert(
            $table,
            array(
                'tire_id'       => $tire_id,
                'user_id'       => $user_id,
                'rating'        => $rating,
                'review_title'  => $review_title,
                'review_text'   => $review_text,
                'review_status' => $review_status,
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Get reviews (all approved ratings) for a specific tire.
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
                "SELECT id, tire_id, user_id, rating, review_title, review_text, created_at, updated_at
                 FROM {$table}
                 WHERE tire_id = %s AND review_status = 'approved'
                 ORDER BY updated_at DESC
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
     * Count reviews (all approved ratings) for a specific tire.
     *
     * @param string $tire_id Tire identifier.
     * @return int Review count.
     */
    public static function get_tire_review_count( $tire_id ) {
        global $wpdb;
        $table = self::ratings_table();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE tire_id = %s AND review_status = 'approved'",
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
    // --- Admin Review Management ---

    /**
     * Search reviews for admin management.
     *
     * Every rating entry is considered a review (star-only reviews included).
     *
     * @param string $search   Search term.
     * @param string $status   Filter by review_status (empty = all).
     * @param int    $per_page Results per page.
     * @param int    $page     Page number.
     * @param string $orderby  Column to sort by.
     * @param string $order    ASC or DESC.
     * @return array Reviews with tire and user info.
     */
    public static function search_reviews( $search = '', $status = '', $per_page = 20, $page = 1, $orderby = 'r.updated_at', $order = 'DESC' ) {
        global $wpdb;
        $table = self::ratings_table();
        $tires = self::tires_table();

        $allowed_orderby = array( 'r.id', 'r.tire_id', 'r.rating', 'r.created_at', 'r.updated_at', 'r.review_status', 't.brand', 't.model' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'r.updated_at';
        }
        $order  = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';
        $offset = max( 0, ( $page - 1 ) * $per_page );

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $status ) ) {
            $where[]  = 'r.review_status = %s';
            $values[] = $status;
        }

        if ( ! empty( $search ) ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '( r.tire_id LIKE %s OR t.brand LIKE %s OR t.model LIKE %s OR r.review_title LIKE %s OR r.review_text LIKE %s )';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $values[]  = $per_page;
        $values[]  = $offset;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, t.brand, t.model FROM {$table} r LEFT JOIN {$tires} t ON r.tire_id = t.tire_id WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                ...$values
            ),
            ARRAY_A
        );
    }

    /**
     * Count reviews for admin, optionally filtered by status.
     *
     * Every rating entry is considered a review (star-only reviews included).
     *
     * @param string $search Search term.
     * @param string $status Filter by review_status (empty = all).
     * @return int Count.
     */
    public static function get_review_count( $search = '', $status = '' ) {
        global $wpdb;
        $table = self::ratings_table();
        $tires = self::tires_table();

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $status ) ) {
            $where[]  = 'r.review_status = %s';
            $values[] = $status;
        }

        if ( ! empty( $search ) ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '( r.tire_id LIKE %s OR t.brand LIKE %s OR t.model LIKE %s OR r.review_title LIKE %s OR r.review_text LIKE %s )';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $where_sql = implode( ' AND ', $where );

        $sql = "SELECT COUNT(*) FROM {$table} r LEFT JOIN {$tires} t ON r.tire_id = t.tire_id WHERE {$where_sql}";
        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, ...$values );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Get review counts by status for the admin tabs.
     *
     * Every rating entry is considered a review (star-only reviews included).
     *
     * @return array { 'all' => int, 'pending' => int, 'approved' => int, 'rejected' => int }
     */
    public static function get_review_status_counts() {
        global $wpdb;
        $table = self::ratings_table();

        $rows = $wpdb->get_results(
            "SELECT review_status, COUNT(*) as cnt FROM {$table} GROUP BY review_status",
            ARRAY_A
        );

        $counts = array( 'all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0 );
        foreach ( $rows as $row ) {
            $s = $row['review_status'];
            if ( isset( $counts[ $s ] ) ) {
                $counts[ $s ] = (int) $row['cnt'];
            }
            $counts['all'] += (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Update review status (approve, reject).
     *
     * @param int    $rating_id Rating row ID.
     * @param string $status    New status: 'approved' or 'rejected'.
     * @return int|false Number of rows updated.
     */
    public static function update_review_status( $rating_id, $status ) {
        global $wpdb;
        $table = self::ratings_table();

        $allowed = array( 'approved', 'rejected', 'pending' );
        if ( ! in_array( $status, $allowed, true ) ) {
            return false;
        }

        return $wpdb->update(
            $table,
            array( 'review_status' => $status ),
            array( 'id' => $rating_id ),
            array( '%s' ),
            array( '%d' )
        );
    }

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

    /**
     * Get all approved reviews by a specific user, joined with tire info.
     *
     * @param int $user_id WordPress user ID.
     * @param int $limit   Max results.
     * @param int $offset  Offset for pagination.
     * @return array Reviews with tire brand/model/image.
     */
    public static function get_user_reviews( $user_id, $limit = 20, $offset = 0 ) {
        global $wpdb;
        $ratings_table = self::ratings_table();
        $tires_table   = self::tires_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.tire_id, r.rating, r.review_title, r.review_text, r.created_at, r.updated_at,
                        t.brand, t.model, t.image
                 FROM {$ratings_table} r
                 LEFT JOIN {$tires_table} t ON r.tire_id = t.tire_id
                 WHERE r.user_id = %d AND r.review_status = 'approved'
                 ORDER BY r.updated_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    /**
     * Count approved reviews by a specific user.
     *
     * @param int $user_id WordPress user ID.
     * @return int Review count.
     */
    public static function get_user_review_count( $user_id ) {
        global $wpdb;
        $table = self::ratings_table();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND review_status = 'approved'",
                $user_id
            )
        );
    }

    // --- Affiliate Link Management ---

    /**
     * Get link status summary counts for the affiliate links dashboard.
     *
     * @return array { 'total' => int, 'affiliate' => int, 'regular' => int, 'missing' => int, 'bundle_set' => int, 'bundle_missing' => int }
     */
    public static function get_link_status_counts() {
        global $wpdb;
        $table = self::tires_table();

        $row = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN link = '' THEN 1 ELSE 0 END) as missing_link,
                SUM(CASE WHEN bundle_link = '' THEN 1 ELSE 0 END) as missing_bundle,
                SUM(CASE WHEN bundle_link != '' THEN 1 ELSE 0 END) as has_bundle,
                SUM(CASE WHEN review_link = '' THEN 1 ELSE 0 END) as missing_review,
                SUM(CASE WHEN review_link != '' THEN 1 ELSE 0 END) as has_review
            FROM {$table}",
            ARRAY_A
        );

        return array(
            'total'          => (int) ( $row['total'] ?? 0 ),
            'missing_link'   => (int) ( $row['missing_link'] ?? 0 ),
            'missing_bundle' => (int) ( $row['missing_bundle'] ?? 0 ),
            'has_bundle'     => (int) ( $row['has_bundle'] ?? 0 ),
            'missing_review' => (int) ( $row['missing_review'] ?? 0 ),
            'has_review'     => (int) ( $row['has_review'] ?? 0 ),
        );
    }

    /**
     * Get all tires with link info for the affiliate links management page.
     *
     * @param string $link_filter Filter: 'all', 'affiliate', 'regular', 'missing'.
     * @param string $search      Search term.
     * @return array Tire rows with id, tire_id, brand, model, size, link, bundle_link, review_link.
     */
    public static function get_tires_for_link_management( $link_filter = 'all', $search = '' ) {
        global $wpdb;
        $table = self::tires_table();

        $where  = array( '1=1' );
        $values = array();

        // Load affiliate domains from settings.
        $affiliate_domains = RTG_Admin::get_affiliate_domains();

        switch ( $link_filter ) {
            case 'missing':
                $where[] = "link = ''";
                break;
            case 'affiliate':
                $clauses = array();
                foreach ( $affiliate_domains as $domain ) {
                    $clauses[] = 'link LIKE %s';
                    $values[]  = '%' . $wpdb->esc_like( $domain ) . '%';
                }
                $where[] = '( ' . implode( ' OR ', $clauses ) . ' )';
                break;
            case 'regular':
                $not_clauses = array( "link != ''" );
                foreach ( $affiliate_domains as $domain ) {
                    $not_clauses[] = 'link NOT LIKE %s';
                    $values[]      = '%' . $wpdb->esc_like( $domain ) . '%';
                }
                $where[] = '( ' . implode( ' AND ', $not_clauses ) . ' )';
                break;
            case 'no_review':
                $where[] = "review_link = ''";
                break;
        }

        if ( ! empty( $search ) ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '( tire_id LIKE %s OR brand LIKE %s OR model LIKE %s )';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $where_sql = implode( ' AND ', $where );

        if ( ! empty( $values ) ) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, tire_id, brand, model, size, category, link, bundle_link, review_link FROM {$table} WHERE {$where_sql} ORDER BY brand ASC, model ASC",
                    ...$values
                ),
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                "SELECT id, tire_id, brand, model, size, category, link, bundle_link, review_link FROM {$table} WHERE {$where_sql} ORDER BY brand ASC, model ASC",
                ARRAY_A
            );
        }

        return $results;
    }

    /**
     * Update only link fields for a tire (used by affiliate links AJAX).
     *
     * @param string $tire_id     Tire identifier.
     * @param string $link        Primary link URL.
     * @param string $bundle_link Bundle link URL.
     * @param string $review_link Review link URL.
     * @return int|false Number of rows updated.
     */
    public static function update_tire_links( $tire_id, $link, $bundle_link, $review_link ) {
        global $wpdb;
        $table = self::tires_table();

        $result = $wpdb->update(
            $table,
            array(
                'link'        => $link,
                'bundle_link' => $bundle_link,
                'review_link' => $review_link,
            ),
            array( 'tire_id' => $tire_id ),
            array( '%s', '%s', '%s' ),
            array( '%s' )
        );

        if ( $result !== false ) {
            self::flush_cache();
        }

        return $result;
    }

    // --- Analytics (Click & Search Tracking) ---

    private static function click_events_table() {
        global $wpdb;
        return $wpdb->prefix . 'rtg_click_events';
    }

    private static function search_events_table() {
        global $wpdb;
        return $wpdb->prefix . 'rtg_search_events';
    }

    /**
     * Generate a privacy-safe session hash for deduplication.
     * Uses SHA-256 of IP + User-Agent + date. No PII is stored.
     *
     * @return string 64-character hex hash.
     */
    private static function generate_session_hash() {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $date = gmdate( 'Y-m-d' );
        return hash( 'sha256', $ip . '|' . $ua . '|' . $date );
    }

    /**
     * Record an affiliate link click event.
     *
     * @param string $tire_id  Tire identifier.
     * @param string $link_type One of: 'purchase', 'bundle', 'review'.
     * @return bool True on insert, false if deduplicated or failed.
     */
    public static function insert_click_event( $tire_id, $link_type ) {
        global $wpdb;
        $table        = self::click_events_table();
        $session_hash = self::generate_session_hash();

        // Dedup: skip if same session + tire + type within 5 seconds.
        $recent = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE session_hash = %s AND tire_id = %s AND link_type = %s
             AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)",
            $session_hash, $tire_id, $link_type
        ) );

        if ( $recent > 0 ) {
            return false;
        }

        $referrer = isset( $_SERVER['HTTP_REFERER'] )
            ? esc_url_raw( substr( $_SERVER['HTTP_REFERER'], 0, 500 ) )
            : '';

        return (bool) $wpdb->insert( $table, array(
            'tire_id'      => $tire_id,
            'link_type'    => $link_type,
            'session_hash' => $session_hash,
            'referrer_url' => $referrer,
        ), array( '%s', '%s', '%s', '%s' ) );
    }

    /**
     * Record a search/filter event.
     *
     * @param string $search_query User's search text.
     * @param string $filters_json JSON-encoded active filters.
     * @param string $sort_by      Sort option used.
     * @param int    $result_count Number of matching tires.
     * @param string $search_type  'search' for regular searches, 'ai' for AI queries.
     * @return bool True on insert, false if deduplicated or failed.
     */
    public static function insert_search_event( $search_query, $filters_json, $sort_by, $result_count, $search_type = 'search' ) {
        global $wpdb;
        $table        = self::search_events_table();
        $session_hash = self::generate_session_hash();

        // Dedup: skip if same session + query within 3 seconds.
        $recent = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE session_hash = %s AND search_query = %s
             AND created_at > DATE_SUB(NOW(), INTERVAL 3 SECOND)",
            $session_hash, $search_query
        ) );

        if ( $recent > 0 ) {
            return false;
        }

        $allowed_types = array( 'search', 'ai' );
        if ( ! in_array( $search_type, $allowed_types, true ) ) {
            $search_type = 'search';
        }

        return (bool) $wpdb->insert( $table, array(
            'search_query' => substr( $search_query, 0, 200 ),
            'filters_json' => $filters_json,
            'sort_by'      => $sort_by,
            'result_count' => max( 0, $result_count ),
            'search_type'  => $search_type,
            'session_hash' => $session_hash,
        ), array( '%s', '%s', '%s', '%d', '%s', '%s' ) );
    }

    /**
     * Get aggregated analytics data for the admin dashboard.
     *
     * @param int $days Number of days to look back.
     * @return array Analytics data arrays.
     */
    public static function get_analytics_data( $days = 30 ) {
        global $wpdb;
        $clicks = self::click_events_table();
        $search = self::search_events_table();
        $tires  = self::tires_table();
        $days   = max( 1, min( 365, intval( $days ) ) );
        $since  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        // Use the WordPress site timezone for daily groupings so charts
        // reflect the site owner's local dates instead of UTC.
        $utc_offset = wp_timezone()->getOffset( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) );
        $sign       = $utc_offset >= 0 ? '+' : '-';
        $abs        = abs( $utc_offset );
        $tz_offset  = sprintf( '%s%02d:%02d', $sign, intdiv( $abs, 3600 ), ( $abs % 3600 ) / 60 );

        $data = array();

        // Click totals by type.
        $data['click_totals'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT link_type, COUNT(*) as total, COUNT(DISTINCT session_hash) as unique_sessions
             FROM {$clicks} WHERE created_at >= %s GROUP BY link_type",
            $since
        ), ARRAY_A );

        // Top clicked tires (top 10).
        $data['top_clicked'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.tire_id, t.brand, t.model, t.image,
                    COUNT(*) as click_count,
                    COUNT(DISTINCT c.session_hash) as unique_clicks
             FROM {$clicks} c
             INNER JOIN {$tires} t ON c.tire_id = t.tire_id
             WHERE c.created_at >= %s
             GROUP BY c.tire_id
             ORDER BY click_count DESC
             LIMIT 10",
            $since
        ), ARRAY_A );

        // Clicks over time (daily, in site timezone).
        $data['clicks_daily'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(CONVERT_TZ(created_at, '+00:00', %s)) as date, link_type, COUNT(*) as count
             FROM {$clicks} WHERE created_at >= %s
             GROUP BY DATE(CONVERT_TZ(created_at, '+00:00', %s)), link_type
             ORDER BY date ASC",
            $tz_offset, $since, $tz_offset
        ), ARRAY_A );

        // Top search queries (regular searches only, top 20).
        $data['top_searches'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT search_query, COUNT(*) as count,
                    ROUND(AVG(result_count), 0) as avg_results
             FROM {$search}
             WHERE created_at >= %s AND search_query != ''
               AND (search_type = 'search' OR search_type = '' OR search_type IS NULL)
             GROUP BY search_query
             ORDER BY count DESC
             LIMIT 20",
            $since
        ), ARRAY_A );

        // Top AI queries (top 20).
        $data['top_ai_queries'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT search_query, COUNT(*) as count,
                    ROUND(AVG(result_count), 0) as avg_results
             FROM {$search}
             WHERE created_at >= %s AND search_query != '' AND search_type = 'ai'
             GROUP BY search_query
             ORDER BY count DESC
             LIMIT 20",
            $since
        ), ARRAY_A );

        // Zero-result searches (top 10, regular searches only).
        $data['zero_result_searches'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT search_query, COUNT(*) as count
             FROM {$search}
             WHERE created_at >= %s AND result_count = 0 AND search_query != ''
               AND (search_type = 'search' OR search_type = '' OR search_type IS NULL)
             GROUP BY search_query
             ORDER BY count DESC
             LIMIT 10",
            $since
        ), ARRAY_A );

        // Most used filters.
        $data['filter_usage'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT filters_json, COUNT(*) as count
             FROM {$search}
             WHERE created_at >= %s AND filters_json != '{}' AND filters_json != ''
             GROUP BY filters_json
             ORDER BY count DESC
             LIMIT 50",
            $since
        ), ARRAY_A );

        // Search volume daily (in site timezone).
        $data['searches_daily'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(CONVERT_TZ(created_at, '+00:00', %s)) as date, COUNT(*) as count
             FROM {$search} WHERE created_at >= %s
             GROUP BY DATE(CONVERT_TZ(created_at, '+00:00', %s))
             ORDER BY date ASC",
            $tz_offset, $since, $tz_offset
        ), ARRAY_A );

        // Summary totals.
        $total_searches = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$search} WHERE created_at >= %s AND (search_type = 'search' OR search_type = '' OR search_type IS NULL)", $since
        ) );
        $total_ai = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$search} WHERE created_at >= %s AND search_type = 'ai'", $since
        ) );

        $data['summary'] = array(
            'total_clicks'    => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$clicks} WHERE created_at >= %s", $since
            ) ),
            'unique_clickers' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT session_hash) FROM {$clicks} WHERE created_at >= %s", $since
            ) ),
            'total_searches'  => $total_searches,
            'total_ai_queries' => $total_ai,
            'unique_searchers' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT session_hash) FROM {$search} WHERE created_at >= %s", $since
            ) ),
        );

        return $data;
    }

    /**
     * Delete analytics events older than the retention period.
     *
     * @param int|null $days Retention period in days. Reads from settings if null.
     * @return array { 'deleted_clicks' => int, 'deleted_searches' => int }
     */
    public static function cleanup_analytics( $days = null ) {
        global $wpdb;

        if ( $days === null ) {
            $settings = get_option( 'rtg_settings', array() );
            $days = intval( $settings['analytics_retention_days'] ?? 90 );
        }

        $days   = max( 7, min( 365, $days ) );
        $clicks = self::click_events_table();
        $search = self::search_events_table();
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $deleted_clicks = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$clicks} WHERE created_at < %s", $cutoff
        ) );

        $deleted_searches = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$search} WHERE created_at < %s", $cutoff
        ) );

        return array(
            'deleted_clicks'   => (int) $deleted_clicks,
            'deleted_searches' => (int) $deleted_searches,
        );
    }

    // --- Favorites ---

    private static function favorites_table() {
        global $wpdb;
        return $wpdb->prefix . 'rtg_favorites';
    }

    /**
     * Get all favorite tire IDs for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array Array of tire_id strings.
     */
    public static function get_user_favorites( $user_id ) {
        global $wpdb;
        $table = self::favorites_table();

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT tire_id FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );
    }

    /**
     * Add a tire to a user's favorites.
     *
     * @param string $tire_id Tire ID.
     * @param int    $user_id WordPress user ID.
     * @return bool True on success.
     */
    public static function add_favorite( $tire_id, $user_id ) {
        global $wpdb;
        $table = self::favorites_table();

        $result = $wpdb->replace(
            $table,
            array(
                'tire_id' => $tire_id,
                'user_id' => $user_id,
            ),
            array( '%s', '%d' )
        );

        return $result !== false;
    }

    /**
     * Remove a tire from a user's favorites.
     *
     * @param string $tire_id Tire ID.
     * @param int    $user_id WordPress user ID.
     * @return bool True on success.
     */
    public static function remove_favorite( $tire_id, $user_id ) {
        global $wpdb;
        $table = self::favorites_table();

        $result = $wpdb->delete(
            $table,
            array(
                'tire_id' => $tire_id,
                'user_id' => $user_id,
            ),
            array( '%s', '%d' )
        );

        return $result !== false;
    }
}
