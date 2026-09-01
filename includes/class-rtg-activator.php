<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Activator {

    /**
     * Current database schema version.
     * Increment this whenever a migration is added.
     */
    const DB_VERSION = 23;

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
        if ( $installed_db >= self::DB_VERSION ) {
            return;
        }

        // Every request after a plugin update lands here until the version
        // option catches up; without a lock, concurrent requests each ran
        // the same ALTERs. Whoever holds the lock migrates; the rest skip
        // and find the work done on their next request.
        if ( ! RTG_Lock::acquire( 'db_upgrade', 5 * MINUTE_IN_SECONDS ) ) {
            return;
        }

        try {
            self::create_tables();
            self::run_migrations();
        } finally {
            RTG_Lock::release( 'db_upgrade' );
        }
    }

    /**
     * Create or update tables via dbDelta (idempotent).
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tires_table         = $wpdb->prefix . 'rtg_tires';
        $ratings_table       = $wpdb->prefix . 'rtg_ratings';
        $wheels_table        = $wpdb->prefix . 'rtg_wheels';
        $favorites_table     = $wpdb->prefix . 'rtg_favorites';
        $click_events_table  = $wpdb->prefix . 'rtg_click_events';
        $search_events_table = $wpdb->prefix . 'rtg_search_events';
        $candidates_table    = $wpdb->prefix . 'rtg_tire_candidates';

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
            model_aliases TEXT NOT NULL,
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
            slug VARCHAR(200) NOT NULL DEFAULT '',
            efficiency_score INT UNSIGNED NOT NULL DEFAULT 0,
            efficiency_grade CHAR(1) NOT NULL DEFAULT '',
            bundle_link TEXT NOT NULL,
            review_link TEXT NOT NULL,
            roamer_tire_id VARCHAR(100) NOT NULL DEFAULT '',
            roamer_efficiency DECIMAL(4,2) NOT NULL DEFAULT 0,
            roamer_total_km DECIMAL(10,1) NOT NULL DEFAULT 0,
            roamer_vehicle_count INT UNSIGNED NOT NULL DEFAULT 0,
            roamer_vehicle_breakdown TEXT,
            roamer_synced_at DATETIME NULL DEFAULT NULL,
            price_source VARCHAR(100) NOT NULL DEFAULT '',
            price_synced_at DATETIME NULL DEFAULT NULL,
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
            KEY idx_efficiency (efficiency_score),
            KEY idx_roamer_tire_id (roamer_tire_id),
            KEY idx_slug (slug),
            KEY idx_roamer_efficiency (roamer_efficiency),
            KEY idx_created_at (created_at)
        ) $charset_collate;

        CREATE TABLE {$ratings_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tire_id VARCHAR(50) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            review_title VARCHAR(200) NOT NULL DEFAULT '',
            review_text TEXT NOT NULL,
            review_status VARCHAR(20) NOT NULL DEFAULT 'approved',
            guest_name VARCHAR(100) NOT NULL DEFAULT '',
            guest_email VARCHAR(254) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_tire (user_id, tire_id, guest_email),
            KEY idx_tire_id (tire_id),
            KEY idx_guest_email (guest_email),
            KEY idx_review_status (review_status)
        ) $charset_collate;

        CREATE TABLE {$favorites_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tire_id VARCHAR(50) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_tire_fav (user_id, tire_id),
            KEY idx_user_id (user_id),
            KEY idx_tire_id (tire_id)
        ) $charset_collate;

        CREATE TABLE {$click_events_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tire_id VARCHAR(50) NOT NULL,
            link_type VARCHAR(20) NOT NULL DEFAULT 'purchase',
            session_hash VARCHAR(64) NOT NULL DEFAULT '',
            referrer_url VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_tire_id (tire_id),
            KEY idx_link_type (link_type),
            KEY idx_created_at (created_at),
            KEY idx_session_date (session_hash, created_at)
        ) $charset_collate;

        CREATE TABLE {$search_events_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            search_query VARCHAR(200) NOT NULL DEFAULT '',
            filters_json VARCHAR(1000) NOT NULL DEFAULT '',
            sort_by VARCHAR(30) NOT NULL DEFAULT '',
            result_count INT UNSIGNED NOT NULL DEFAULT 0,
            search_type VARCHAR(10) NOT NULL DEFAULT 'search',
            session_hash VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_created_at (created_at),
            KEY idx_search_query (search_query(50)),
            KEY idx_search_type (search_type),
            KEY idx_session_date (session_hash, created_at)
        ) $charset_collate;

        CREATE TABLE {$candidates_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(30) NOT NULL DEFAULT '',
            advertiser_id VARCHAR(50) NOT NULL DEFAULT '',
            advertiser_name VARCHAR(100) NOT NULL DEFAULT '',
            external_id VARCHAR(191) NOT NULL DEFAULT '',
            brand VARCHAR(100) NOT NULL DEFAULT '',
            model VARCHAR(200) NOT NULL DEFAULT '',
            size VARCHAR(30) NOT NULL DEFAULT '',
            load_index VARCHAR(20) NOT NULL DEFAULT '',
            load_range VARCHAR(10) NOT NULL DEFAULT '',
            speed_rating VARCHAR(20) NOT NULL DEFAULT '',
            price DECIMAL(8,2) NOT NULL DEFAULT 0,
            link TEXT NOT NULL,
            image TEXT NOT NULL,
            match_key VARCHAR(191) NOT NULL DEFAULT '',
            qualifies TINYINT(1) NOT NULL DEFAULT 0,
            fail_reasons TEXT,
            matched_tire_id VARCHAR(50) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            fits_vehicles VARCHAR(100) NOT NULL DEFAULT '',
            raw_json LONGTEXT,
            first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_product (source, advertiser_id, external_id(100)),
            KEY idx_status (status),
            KEY idx_qualifies (qualifies),
            KEY idx_match_key (match_key),
            KEY idx_first_seen (first_seen_at),
            KEY idx_fits_vehicles (fits_vehicles)
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
            4 => 'migrate_4_add_review_link',
            5 => 'migrate_5_add_review_text',
            6 => 'migrate_6_add_review_status',
            7 => 'migrate_7_create_favorites_table',
            8 => 'migrate_8_create_click_events_table',
            9 => 'migrate_9_create_search_events_table',
            10 => 'migrate_10_add_search_type_column',
            11 => 'migrate_11_add_guest_review_columns',
            12 => 'migrate_12_add_roamer_columns',
            13 => 'migrate_13_roamer_drop_sessions_add_breakdown',
            14 => 'migrate_14_ensure_vehicle_breakdown_column',
            // Note: migration 15 (add roamer_crr) was shipped in 1.50.0 then
            // reverted. Migration 16 drops the column on sites that ran 15.
            16 => 'migrate_16_drop_roamer_crr',
            17 => 'migrate_17_add_slug_column',
            18 => 'migrate_18_create_candidates_table',
            19 => 'migrate_19_add_candidate_fits_vehicles',
            20 => 'migrate_20_add_tire_price_source',
            21 => 'migrate_21_add_model_aliases',
            22 => 'migrate_22_remove_retired_features',
            23 => 'migrate_23_add_sort_and_status_indexes',
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

    /**
     * Migration 4: Add review_link column for linking tire reviews (articles/videos).
     * Column creation handled by dbDelta above; this marks the migration.
     */
    private static function migrate_4_add_review_link() {
        // Column added by dbDelta above.
    }

    /**
     * Migration 5: Add review_title and review_text columns for user text reviews.
     * Columns added by dbDelta above; this marks the migration.
     */
    private static function migrate_5_add_review_text() {
        // Columns added by dbDelta above.
    }

    /**
     * Migration 6: Add review_status column for review moderation.
     * Column added by dbDelta above; existing reviews default to 'approved'.
     */
    private static function migrate_6_add_review_status() {
        // Column added by dbDelta above with DEFAULT 'approved',
        // so all existing rows are automatically approved.
    }

    /**
     * Migration 7: Create favorites table for user tire wishlists.
     * Table creation handled by dbDelta above; this marks the migration.
     */
    private static function migrate_7_create_favorites_table() {
        // Table created by dbDelta above.
    }

    /**
     * Migration 8: Create click events table for affiliate click tracking.
     * Table creation handled by dbDelta above; this marks the migration.
     */
    private static function migrate_8_create_click_events_table() {
        // Table created by dbDelta above.
    }

    /**
     * Migration 9: Create search events table for search analytics.
     * Table creation handled by dbDelta above; this marks the migration.
     */
    private static function migrate_9_create_search_events_table() {
        // Table created by dbDelta above.
    }

    /**
     * Migration 10: Add search_type column to distinguish regular searches from AI queries.
     * Column added by dbDelta above; this marks the migration.
     */
    private static function migrate_10_add_search_type_column() {
        // Column added by dbDelta above with DEFAULT 'search',
        // so all existing rows are automatically tagged as regular searches.
    }

    /**
     * Migration 11: Add guest_name and guest_email columns for guest reviews,
     * and update the unique key to allow multiple guests per tire.
     *
     * Columns added by dbDelta above. This migration handles the unique key
     * change which dbDelta cannot do automatically.
     */
    private static function migrate_11_add_guest_review_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_ratings';

        // Drop the old unique key (user_id, tire_id) and add the new one
        // that includes guest_email so different guests can review the same tire.
        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'user_tire'" );
        if ( ! empty( $indexes ) ) {
            $wpdb->query( "ALTER TABLE {$table} DROP INDEX user_tire" );
            $wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY user_tire (user_id, tire_id, guest_email)" );
        }
    }

    /**
     * Migration 12: Add Rivian Roamer real-world efficiency columns.
     * Columns added by dbDelta above; this marks the migration.
     */
    private static function migrate_12_add_roamer_columns() {
        // Columns added by dbDelta above.
    }

    /**
     * Migration 13: Replace roamer_session_count with roamer_vehicle_breakdown.
     * The Roamer feed no longer provides driving session counts; instead it
     * provides total_distance_km (already stored) and a vehicle_breakdown
     * object. dbDelta cannot drop columns, so we handle removal manually.
     */
    private static function migrate_13_roamer_drop_sessions_add_breakdown() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tires';

        // Drop the obsolete session_count column.
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        if ( in_array( 'roamer_session_count', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} DROP COLUMN roamer_session_count" );
        }

        // Explicitly add vehicle_breakdown column if dbDelta missed it
        // (TEXT columns with DEFAULT can fail on older MySQL/MariaDB).
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        if ( ! in_array( 'roamer_vehicle_breakdown', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN roamer_vehicle_breakdown TEXT AFTER roamer_vehicle_count" );
        }
    }

    /**
     * Migration 14: Ensure roamer_vehicle_breakdown column exists.
     * Migration 13 relied on dbDelta to add the TEXT column, which can
     * fail silently on MySQL < 8.0.13 (TEXT DEFAULT not supported).
     * This migration is a safety net for sites that already ran 13.
     */
    private static function migrate_14_ensure_vehicle_breakdown_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tires';

        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        if ( ! in_array( 'roamer_vehicle_breakdown', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN roamer_vehicle_breakdown TEXT AFTER roamer_vehicle_count" );
        }
    }

    /**
     * Migration 16: Drop the roamer_crr column.
     * The rolling-resistance estimate (added by the reverted migration 15 in
     * 1.50.0) was removed in 1.50.2. This drops the now-unused column on any
     * site that ran migration 15 before the rollback.
     */
    private static function migrate_16_drop_roamer_crr() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tires';

        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        if ( in_array( 'roamer_crr', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} DROP COLUMN roamer_crr" );
        }
    }

    /**
     * Migration 17: Add the slug column for individual tire pages, backfill
     * slugs for all existing tires, and flag a rewrite-rule flush so the new
     * /{slug}/{tire}/ route registers on this request.
     *
     * dbDelta adds the column and index above; the explicit ALTERs here are a
     * safety net for environments where dbDelta misses them.
     */
    private static function migrate_17_add_slug_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tires';

        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        if ( ! in_array( 'slug', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN slug VARCHAR(200) NOT NULL DEFAULT '' AFTER image" );
        }

        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_slug'" );
        if ( empty( $indexes ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD KEY idx_slug (slug)" );
        }

        RTG_Database::backfill_slugs();

        // Register the new rewrite rule on this request.
        update_option( 'rtg_flush_rewrite', 1 );
    }

    /**
     * Migration 18: Create the tire candidates table used by catalog sync.
     *
     * dbDelta above normally creates it. This is the same safety net as
     * migrations 13, 14 and 17: dbDelta can silently skip a table, and the
     * migration loop records version 18 either way, so a site it skipped would
     * never retry and every sync would fail against a missing table.
     */
    private static function migrate_18_create_candidates_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tire_candidates';

        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists === $table ) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                source VARCHAR(30) NOT NULL DEFAULT '',
                advertiser_id VARCHAR(50) NOT NULL DEFAULT '',
                advertiser_name VARCHAR(100) NOT NULL DEFAULT '',
                external_id VARCHAR(191) NOT NULL DEFAULT '',
                brand VARCHAR(100) NOT NULL DEFAULT '',
                model VARCHAR(200) NOT NULL DEFAULT '',
                size VARCHAR(30) NOT NULL DEFAULT '',
                load_index VARCHAR(20) NOT NULL DEFAULT '',
                load_range VARCHAR(10) NOT NULL DEFAULT '',
                speed_rating VARCHAR(20) NOT NULL DEFAULT '',
                price DECIMAL(8,2) NOT NULL DEFAULT 0,
                link TEXT NOT NULL,
                image TEXT NOT NULL,
                match_key VARCHAR(191) NOT NULL DEFAULT '',
                qualifies TINYINT(1) NOT NULL DEFAULT 0,
                fail_reasons TEXT,
                matched_tire_id VARCHAR(50) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'new',
                fits_vehicles VARCHAR(100) NOT NULL DEFAULT '',
                raw_json LONGTEXT,
                first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY source_product (source, advertiser_id, external_id(100)),
                KEY idx_status (status),
                KEY idx_qualifies (qualifies),
                KEY idx_match_key (match_key),
                KEY idx_first_seen (first_seen_at),
                KEY idx_fits_vehicles (fits_vehicles)
            ) {$charset_collate}"
        );
    }

    /**
     * Migration 19: Record which Rivian platforms each candidate is legal on.
     *
     * Size and load index used to be judged as two independent gates, which
     * could not express that an R1 fitment at load index 114 clears a global
     * floor of 112 while being illegal on the only vehicle taking that size.
     * Candidates now carry the vehicles they actually fit, so the review queue
     * can be filtered by platform.
     *
     * Existing rows are left blank and repopulate on the next discovery run;
     * backfilling here would mean re-qualifying every row against rules the
     * next run applies anyway.
     */
    private static function migrate_19_add_candidate_fits_vehicles() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tire_candidates';

        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return;
        }

        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        if ( ! in_array( 'fits_vehicles', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN fits_vehicles VARCHAR(100) NOT NULL DEFAULT '' AFTER status" );
        }

        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_fits_vehicles'" );
        if ( empty( $indexes ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD KEY idx_fits_vehicles (fits_vehicles)" );
        }
    }

    /**
     * Migration 20: Record where each tire's price came from, and when.
     *
     * The daily catalog sync can refresh prices from the retailer a tire's
     * affiliate link points to. Without recording the source, a price on the
     * site is indistinguishable from one typed in by hand, so there is no way
     * to tell a stale manual figure from a fresh synced one — or to notice
     * that syncing quietly stopped working for a tire.
     */
    private static function migrate_20_add_tire_price_source() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tires';

        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

        if ( ! in_array( 'price_source', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN price_source VARCHAR(100) NOT NULL DEFAULT '' AFTER roamer_synced_at" );
        }
        if ( ! in_array( 'price_synced_at', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN price_synced_at DATETIME NULL DEFAULT NULL AFTER price_source" );
        }
    }

    /**
     * Migration 21 (1.74.0): alternate model names on tires.
     *
     * Retailers spell a model their own way — "Ridge Grappler LT" for the
     * guide's "Ridge Grappler" — and matching, coverage and pricing all key
     * on the model. An alias lets the matcher accept the retailer's spelling
     * without renaming what readers see. One alias per line.
     */
    private static function migrate_21_add_model_aliases() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tires';

        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

        if ( ! in_array( 'model_aliases', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN model_aliases TEXT NOT NULL AFTER model" );
        }
    }

    /**
     * Migration 22 (1.75.0): sweep up after retired features.
     *
     * Three things left the plugin in 1.75.0 — the direct-lookup pass (its
     * keyword form cannot beat CJ's ranking), the Google category filter (a
     * documented trap: Tire Rack sends no category, so applying one drops the
     * retailer), and the JSON fixture source (dev scaffolding whose bundled
     * sample seeded "Sample Retailer" rows into real queues). Their stored
     * leftovers go with them, so no orphaned option or demo row outlives the
     * code that understood it.
     */
    private static function migrate_22_remove_retired_features() {
        global $wpdb;

        delete_option( 'rtg_cj_targeted_cursor' );

        $settings = get_option( 'rtg_settings', array() );
        if ( is_array( $settings ) ) {
            unset(
                $settings['cj_targeted_enabled'],
                $settings['cj_targeted_budget'],
                $settings['cj_targeted_limit'],
                $settings['cj_category_names'],
                $settings['catalog_fixture_url']
            );
            update_option( 'rtg_settings', $settings );
        }

        $candidates = $wpdb->prefix . 'rtg_tire_candidates';
        $exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $candidates ) );

        if ( $exists === $candidates ) {
            // Demo rows are identifiable by their source, whatever status a
            // human may have set on one — they never described a real product.
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$candidates} WHERE source = %s", 'fixture' ) );
        }
    }

    /**
     * Migration 23: Index the columns the guide actually sorts and filters on.
     *
     * `roamer_efficiency` is the default sort for both the AJAX and REST
     * listings and `created_at` backs the "newest" sort, yet neither was
     * indexed — every server-side page was a filesort. Review moderation
     * filters on `review_status` with no index, and the search-analytics
     * dedup probe filters on `session_hash` (the click table got that index;
     * the search table never did).
     *
     * dbDelta adds these from the schema above; the explicit ALTERs are the
     * same safety net as migration 17.
     */
    private static function migrate_23_add_sort_and_status_indexes() {
        global $wpdb;

        $wanted = array(
            $wpdb->prefix . 'rtg_tires'         => array(
                'idx_roamer_efficiency' => 'roamer_efficiency',
                'idx_created_at'        => 'created_at',
            ),
            $wpdb->prefix . 'rtg_ratings'       => array(
                'idx_review_status' => 'review_status',
            ),
            $wpdb->prefix . 'rtg_search_events' => array(
                'idx_session_date' => 'session_hash, created_at',
            ),
        );

        foreach ( $wanted as $table => $indexes ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) {
                continue;
            }
            foreach ( $indexes as $name => $columns ) {
                $present = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $name ) );
                if ( empty( $present ) ) {
                    $wpdb->query( "ALTER TABLE {$table} ADD KEY {$name} ({$columns})" );
                }
            }
        }
    }
}
