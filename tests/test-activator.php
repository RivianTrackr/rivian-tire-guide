<?php
/**
 * Tests for RTG_Activator — table creation and migration system.
 */
class Test_RTG_Activator extends WP_UnitTestCase {

    public function tearDown(): void {
        parent::tearDown();

        // dbDelta's DDL commits the test transaction mid-test, so the
        // upgrade lock taken before it survives the rollback that would
        // otherwise remove it. Release it for real for the next test.
        RTG_Lock::release( 'db_upgrade' );
        $GLOBALS['wpdb']->query( 'COMMIT' );
    }

    public function test_activate_creates_tables() {
        RTG_Activator::activate();

        global $wpdb;
        $tires_table   = $wpdb->prefix . 'rtg_tires';
        $ratings_table = $wpdb->prefix . 'rtg_ratings';

        $tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}rtg_%'" );
        $this->assertContains( $tires_table, $tables );
        $this->assertContains( $ratings_table, $tables );
    }

    public function test_activate_sets_db_version() {
        RTG_Activator::activate();

        $db_version = (int) get_option( 'rtg_db_version', 0 );
        $this->assertEquals( RTG_Activator::DB_VERSION, $db_version );
    }

    public function test_activate_sets_plugin_version() {
        RTG_Activator::activate();

        $version = get_option( 'rtg_version' );
        $this->assertEquals( RTG_VERSION, $version );
    }

    public function test_maybe_upgrade_runs_migrations() {
        // Simulate an old installation.
        update_option( 'rtg_db_version', 0 );

        RTG_Activator::maybe_upgrade();

        $db_version = (int) get_option( 'rtg_db_version', 0 );
        $this->assertEquals( RTG_Activator::DB_VERSION, $db_version );
    }

    public function test_maybe_upgrade_skips_when_current() {
        update_option( 'rtg_db_version', RTG_Activator::DB_VERSION );

        // This should be a no-op — verify it doesn't error.
        RTG_Activator::maybe_upgrade();

        $db_version = (int) get_option( 'rtg_db_version' );
        $this->assertEquals( RTG_Activator::DB_VERSION, $db_version );
    }

    public function test_tires_table_has_expected_columns() {
        RTG_Activator::activate();

        global $wpdb;
        $table = $wpdb->prefix . 'rtg_tires';
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

        $expected = array(
            'id', 'tire_id', 'size', 'diameter', 'brand', 'model', 'category',
            'price', 'mileage_warranty', 'weight_lb', 'three_pms', 'tread',
            'load_index', 'max_load_lb', 'load_range', 'speed_rating', 'psi',
            'utqg', 'tags', 'link', 'image', 'efficiency_score', 'efficiency_grade',
            'bundle_link', 'sort_order', 'created_at', 'updated_at',
        );

        foreach ( $expected as $col ) {
            $this->assertContains( $col, $columns, "Missing column: {$col}" );
        }
    }

    public function test_ratings_table_has_unique_constraint() {
        RTG_Activator::activate();

        global $wpdb;
        $table = $wpdb->prefix . 'rtg_ratings';
        $indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'user_tire'" );

        $this->assertNotEmpty( $indexes, 'user_tire unique index should exist' );
    }


    /**
     * Migration 23: the columns the guide sorts and moderates on are indexed.
     */
    public function test_sort_and_status_columns_are_indexed() {
        RTG_Activator::activate();

        global $wpdb;
        $expected = array(
            $wpdb->prefix . 'rtg_tires'         => array( 'idx_roamer_efficiency', 'idx_created_at' ),
            $wpdb->prefix . 'rtg_ratings'       => array( 'idx_review_status' ),
            $wpdb->prefix . 'rtg_search_events' => array( 'idx_session_date' ),
        );

        foreach ( $expected as $table => $indexes ) {
            foreach ( $indexes as $name ) {
                $rows = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $name ) );
                $this->assertNotEmpty( $rows, "{$table} should carry {$name}" );
            }
        }

        $this->assertSame( RTG_Activator::DB_VERSION, (int) get_option( 'rtg_db_version' ) );
    }

    /**
     * The migration is idempotent: running it against a schema that already
     * has the indexes adds nothing and fails nothing.
     */
    public function test_index_migration_is_idempotent() {
        RTG_Activator::activate();
        update_option( 'rtg_db_version', 22 );

        RTG_Activator::maybe_upgrade();

        global $wpdb;
        $rows = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->prefix}rtg_tires WHERE Key_name = 'idx_created_at'" );
        $this->assertCount( 1, $rows, 'exactly one idx_created_at, not a duplicate' );
        $this->assertSame( RTG_Activator::DB_VERSION, (int) get_option( 'rtg_db_version' ) );
    }

    /**
     * Concurrent requests after an update all reach maybe_upgrade(); only
     * the one holding the lock migrates, the rest return without touching
     * the schema.
     */
    public function test_maybe_upgrade_yields_while_another_request_holds_the_lock() {
        RTG_Activator::activate();
        update_option( 'rtg_db_version', 22 );

        RTG_Lock::acquire( 'db_upgrade', 60 );
        try {
            RTG_Activator::maybe_upgrade();
            $this->assertSame( 22, (int) get_option( 'rtg_db_version' ), 'the locked-out request must not migrate' );
        } finally {
            RTG_Lock::release( 'db_upgrade' );
        }

        RTG_Activator::maybe_upgrade();
        $this->assertSame( RTG_Activator::DB_VERSION, (int) get_option( 'rtg_db_version' ) );
    }
}
