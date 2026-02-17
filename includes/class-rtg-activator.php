<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Activator {

    /**
     * Current database schema version.
     * Increment this whenever a migration is added.
     */
    const DB_VERSION = 3;

    public static function activate() {
        self::create_tables();
        self::run_migrations();
        update_option( 'rtg_version', RTG_VERSION );
        update_option( 'rtg_flush_rewrite', 1 );
    }

    /**
     * Run on plugins_loaded to apply pending migrations on update.
     */
    public static function maybe_upgrade() {
        $installed_db = (int) get_option( 'rtg_db_version', 0 );
        if ( $installed_db < self::DB_VERSION ) {
            self::create_tables();
            self::run_migrations();
        }
    }

    /**
     * Create or update tables via dbDelta (idempotent).
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tires_table   = $wpdb->prefix . 'rtg_tires';
        $ratings_table = $wpdb->prefix . 'rtg_ratings';

        $wheels_table  = $wpdb->prefix . 'rtg_wheels';

        $sql = "CREATE TABLE {$wheels_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL DEFAULT '',
            stock_size VARCHAR(30) NOT NULL DEFAULT '',
            alt_sizes VARCHAR(200) NOT NULL DEFAULT '',
            image TEXT NOT NULL,
            vehicles VARCHAR(200) NOT NULL DEFAULT '',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_sort_order (sort_order)
        ) $charset_collate;

        CREATE TABLE {$tires_table} (
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

    /**
     * Run numbered migrations sequentially.
     * Each migration only runs once — the current version is stored in rtg_db_version.
     */
    private static function run_migrations() {
        $installed_db = (int) get_option( 'rtg_db_version', 0 );

        $migrations = array(
            1 => 'migrate_1_initial_schema',
            2 => 'migrate_2_add_tags_index',
            3 => 'migrate_3_create_wheels_table',
        );

        foreach ( $migrations as $version => $method ) {
            if ( $installed_db < $version && method_exists( __CLASS__, $method ) ) {
                call_user_func( array( __CLASS__, $method ) );
                update_option( 'rtg_db_version', $version );
            }
        }
    }

    // --- Individual migrations ---

    /**
     * Migration 1: Initial schema.
     * No-op since dbDelta handles table creation, but marks the baseline.
     */
    private static function migrate_1_initial_schema() {
        // Baseline — tables created by dbDelta above.
    }

    /**
     * Migration 2: Add index on tags column for server-side tag filtering.
     */
    private static function migrate_2_add_tags_index() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tires';

        // Only add if not already present.
        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_tags'" );
        if ( empty( $indexes ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD KEY idx_tags (tags(100))" );
        }
    }

    /**
     * Migration 3: Create wheels table for stock wheel guide.
     * Table creation handled by dbDelta above; this marks the migration.
     */
    private static function migrate_3_create_wheels_table() {
        // Table created by dbDelta above.
    }
}
