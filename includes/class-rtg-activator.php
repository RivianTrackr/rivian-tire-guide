<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Activator {

    public static function activate() {
        self::create_tables();
        update_option( 'rtg_version', RTG_VERSION );
        update_option( 'rtg_flush_rewrite', 1 );
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tires_table = $wpdb->prefix . 'rtg_tires';
        $ratings_table = $wpdb->prefix . 'rtg_ratings';

        $sql = "CREATE TABLE {$tires_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tire_id VARCHAR(50) NOT NULL,
            size VARCHAR(30) NOT NULL DEFAULT '',
            diameter VARCHAR(20) NOT NULL DEFAULT '',
            brand VARCHAR(100) NOT NULL DEFAULT '',
            model VARCHAR(200) NOT NULL DEFAULT '',
            category VARCHAR(50) NOT NULL DEFAULT '',
            price DECIMAL(8,2) NOT NULL DEFAULT 0,
            mileage_warranty INT UNSIGNED NOT NULL DEFAULT 0,
            weight_lb DECIMAL(5,1) NOT NULL DEFAULT 0,
            three_pms VARCHAR(10) NOT NULL DEFAULT 'No',
            tread VARCHAR(20) NOT NULL DEFAULT '',
            load_index VARCHAR(20) NOT NULL DEFAULT '',
            max_load_lb INT UNSIGNED NOT NULL DEFAULT 0,
            load_range VARCHAR(10) NOT NULL DEFAULT '',
            speed_rating VARCHAR(20) NOT NULL DEFAULT '',
            psi VARCHAR(10) NOT NULL DEFAULT '',
            utqg VARCHAR(30) NOT NULL DEFAULT '',
            tags VARCHAR(500) NOT NULL DEFAULT '',
            link TEXT NOT NULL,
            image TEXT NOT NULL,
            efficiency_score INT UNSIGNED NOT NULL DEFAULT 0,
            efficiency_grade CHAR(1) NOT NULL DEFAULT '',
            bundle_link TEXT NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY tire_id (tire_id),
            KEY idx_size (size),
            KEY idx_brand (brand),
            KEY idx_category (category),
            KEY idx_price (price),
            KEY idx_warranty (mileage_warranty),
            KEY idx_weight (weight_lb),
            KEY idx_efficiency (efficiency_score)
        ) $charset_collate;

        CREATE TABLE {$ratings_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tire_id VARCHAR(50) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_tire (user_id, tire_id),
            KEY idx_tire_id (tire_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
