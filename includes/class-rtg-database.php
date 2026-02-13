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

    // --- Tire CRUD ---

    public static function get_all_tires() {
        global $wpdb;
        $table = self::tires_table();
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A );
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
        return $result !== false ? $wpdb->insert_id : false;
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

        return $wpdb->update( $table, $data, array( 'tire_id' => $tire_id ), $formats, array( '%s' ) );
    }

    public static function delete_tire( $tire_id ) {
        global $wpdb;
        $table = self::tires_table();
        return $wpdb->delete( $table, array( 'tire_id' => $tire_id ), array( '%s' ) );
    }

    public static function delete_tires( $tire_ids ) {
        global $wpdb;
        $table = self::tires_table();

        if ( empty( $tire_ids ) ) {
            return 0;
        }

        $placeholders = implode( ', ', array_fill( 0, count( $tire_ids ), '%s' ) );
        return $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$table} WHERE tire_id IN ({$placeholders})", ...$tire_ids )
        );
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
