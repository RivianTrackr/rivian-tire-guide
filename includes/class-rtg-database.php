<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Database {

    private static function tires_table() {
        global $wpdb;
        return $wpdb->prefix . 'rtg_tires';
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
            'sort_order'       => 0,
        );

        $data = wp_parse_args( $data, $defaults );

        $formats = array(
            '%s', '%s', '%s', '%s', '%s', '%s',
            '%f', '%d', '%f',
            '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s',
            '%d', '%s', '%s',
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
                "SELECT tire_id, AVG(rating) as average, COUNT(*) as count FROM {$table} WHERE tire_id IN ({$placeholders}) GROUP BY tire_id",
                ...$tire_ids
            ),
            ARRAY_A
        );

        $ratings = array();
        foreach ( $results as $row ) {
            $ratings[ $row['tire_id'] ] = array(
                'average' => round( (float) $row['average'], 1 ),
                'count'   => (int) $row['count'],
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
                "SELECT tire_id, rating FROM {$table} WHERE tire_id IN ({$placeholders}) AND user_id = %d",
                ...$args
            ),
            ARRAY_A
        );

        $ratings = array();
        foreach ( $results as $row ) {
            $ratings[ $row['tire_id'] ] = (int) $row['rating'];
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

    public static function set_rating( $tire_id, $user_id, $rating ) {
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
                array( 'rating' => $rating ),
                array( 'tire_id' => $tire_id, 'user_id' => $user_id ),
                array( '%d' ),
                array( '%s', '%d' )
            );
        }

        return $wpdb->insert(
            $table,
            array(
                'tire_id' => $tire_id,
                'user_id' => $user_id,
                'rating'  => $rating,
            ),
            array( '%s', '%d', '%d' )
        );
    }
}
