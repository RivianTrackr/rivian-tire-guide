<?php
/**
 * Tests for RTG_Activator — table creation and migration system.
 */
class Test_RTG_Activator extends WP_UnitTestCase {

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
}
