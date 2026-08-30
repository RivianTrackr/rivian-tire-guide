<?php
/**
 * Tests for RTG_Admin class — CSV import/export and settings.
 */
class Test_RTG_Admin extends WP_UnitTestCase {

    private $admin;

    public function setUp(): void {
        parent::setUp();
        RTG_Activator::activate();

        // Set up an admin user.
        $user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );

        $this->admin = new RTG_Admin();
    }

    public function test_menu_pages_registered() {
        // Register menus.
        $this->admin->register_menu();

        global $submenu;
        $this->assertArrayHasKey( 'rtg-dashboard', $submenu );

        // Check expected submenu slugs.
        $slugs = array_map( function ( $item ) {
            return $item[2]; // menu slug is index 2
        }, $submenu['rtg-dashboard'] );

        $this->assertContains( 'rtg-dashboard', $slugs );
        $this->assertContains( 'rtg-tires', $slugs );
        $this->assertContains( 'rtg-tire-edit', $slugs );
        $this->assertContains( 'rtg-reviews', $slugs );
        $this->assertContains( 'rtg-import', $slugs );
        $this->assertContains( 'rtg-settings', $slugs );
    }

    public function test_dropdown_options_storage() {
        // Save some dropdown options.
        update_option( 'rtg_dropdown_options', array(
            'brands'     => array( 'Michelin', 'Goodyear', 'BFGoodrich' ),
            'categories' => array( 'All-Season', 'All-Terrain', 'Winter' ),
        ) );

        $brands = RTG_Admin::get_dropdown_options( 'brands' );
        $this->assertContains( 'Michelin', $brands );
        $this->assertContains( 'Goodyear', $brands );
        $this->assertCount( 3, $brands );
    }

    public function test_settings_save_and_retrieve() {
        $settings = array(
            'rows_per_page'          => 24,
            'cdn_prefix'             => '',
            'compare_slug'           => 'compare-tires',
            'server_side_pagination' => true,
            'theme_colors'           => array( 'accent' => '#ff0000' ),
        );
        update_option( 'rtg_settings', $settings );

        $saved = get_option( 'rtg_settings' );
        $this->assertEquals( 24, $saved['rows_per_page'] );
        $this->assertTrue( $saved['server_side_pagination'] );
        $this->assertEquals( 'compare-tires', $saved['compare_slug'] );
    }

    // --- Editing a tire from the frontend ---

    /**
     * Who may edit is one question with one answer: the capability every
     * admin screen here is registered under. A logged-out visitor and a
     * subscriber both get no.
     */
    public function test_only_users_with_the_admin_capability_may_edit() {
        $this->assertTrue( RTG_Admin::can_edit_tires() );

        wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
        $this->assertFalse( RTG_Admin::can_edit_tires() );

        wp_set_current_user( 0 );
        $this->assertFalse( RTG_Admin::can_edit_tires() );
    }

    /**
     * The link the frontend hands out addresses the edit screen by tire_id,
     * and the base it is built from is the same URL with the id left off, so
     * a card completing it in JavaScript lands in the same place.
     */
    public function test_the_edit_url_carries_the_tire_id() {
        $url = RTG_Admin::tire_edit_url( 'my-tire_1' );

        $this->assertStringContainsString( 'page=rtg-tire-edit', $url );
        $this->assertStringContainsString( 'tire_id=my-tire_1', $url );
        $this->assertSame( RTG_Admin::tire_edit_url_base() . 'my-tire_1', $url );
    }

    /**
     * The edit screen answers to both addresses, and a tire reached by its
     * tire_id still reports the row number the form posts back.
     */
    public function test_the_edit_screen_resolves_either_address() {
        RTG_Database::insert_tire( array(
            'tire_id' => 'EDIT-001',
            'brand'   => 'Michelin',
            'model'   => 'Defender LTX M/S2',
            'size'    => '275/65R18',
        ) );

        $stored = RTG_Database::get_tire( 'EDIT-001' );

        $by_tire_id = RTG_Admin::resolve_edit_target( array( 'tire_id' => 'EDIT-001' ) );
        $this->assertSame( 'EDIT-001', $by_tire_id['tire']['tire_id'] );
        $this->assertSame( intval( $stored['id'] ), $by_tire_id['id'] );

        $by_row = RTG_Admin::resolve_edit_target( array( 'id' => intval( $stored['id'] ) ) );
        $this->assertSame( 'EDIT-001', $by_row['tire']['tire_id'] );
    }

    /**
     * No address, a malformed one, or one naming a tire that isn't there all
     * mean "adding a new tire" — never a fatal, and never someone else's row.
     */
    public function test_an_unresolvable_address_means_adding() {
        foreach ( array(
            'nothing given'   => array(),
            'malformed'       => array( 'tire_id' => '../../wp-config' ),
            'unknown tire'    => array( 'tire_id' => 'NOPE-999' ),
            'unknown row'     => array( 'id' => 999999 ),
        ) as $label => $request ) {
            $target = RTG_Admin::resolve_edit_target( $request );

            $this->assertNull( $target['tire'], $label . ' should not resolve a tire' );
            $this->assertSame( 0, $target['id'], $label . ' should not resolve a row' );
        }
    }

    /**
     * The guide's cards are built in JavaScript from localized settings, so
     * the edit link only reaches a browser whose user may actually use it —
     * a visitor is never sent the control at all.
     */
    public function test_the_guide_only_localizes_the_edit_url_for_editors() {
        $frontend = new RTG_Frontend();

        $frontend->render_shortcode( array() );
        $this->assertStringContainsString(
            'rtg-tire-edit',
            (string) wp_scripts()->get_data( 'rtg-tire-guide', 'data' )
        );

        // A fresh registry, so the second render localizes from scratch.
        $GLOBALS['wp_scripts'] = null;
        wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

        $visitor_frontend = new RTG_Frontend();
        $visitor_frontend->render_shortcode( array() );

        $visitor_data = (string) wp_scripts()->get_data( 'rtg-tire-guide', 'data' );
        $this->assertNotSame( '', $visitor_data, 'the visitor should still be given the guide data' );
        $this->assertStringNotContainsString( 'rtg-tire-edit', $visitor_data );
    }

    // --- Settings save ---

    public function tearDown(): void {
        $_POST = array();
        parent::tearDown();
    }

    private function settings_post( $overrides = array() ) {
        return array_merge( array(
            'rows_per_page'            => '12',
            'cdn_prefix'               => '',
            'compare_slug'             => 'tire-compare',
            'user_reviews_slug'        => 'user-reviews',
            'tire_review_slug'         => 'tire-review',
            'analytics_retention_days' => '90',
        ), $overrides );
    }

    /**
     * rtg_settings also carries the keys the Roamer Sync and Tire Discovery
     * pages save (CJ credentials, sync toggles, custom URLs). Saving the main
     * Settings page must merge over them, never replace the whole option.
     */
    public function test_settings_save_preserves_keys_owned_by_other_pages() {
        update_option( 'rtg_settings', array(
            'rows_per_page'        => 12,
            'cj_pat'               => 'secret-token',
            'cj_company_id'        => '1234567',
            'catalog_sync_enabled' => true,
            'roamer_sync_url'      => 'https://example.com/feed.json',
        ) );

        $_POST = $this->settings_post( array( 'rows_per_page' => '24' ) );
        $this->admin->save_settings_from_post();

        $saved = get_option( 'rtg_settings' );
        $this->assertSame( 24, $saved['rows_per_page'] );
        $this->assertSame( 'secret-token', $saved['cj_pat'], 'the CJ credential must survive a Settings save' );
        $this->assertSame( '1234567', $saved['cj_company_id'] );
        $this->assertTrue( $saved['catalog_sync_enabled'] );
        $this->assertSame( 'https://example.com/feed.json', $saved['roamer_sync_url'] );
    }

    /**
     * A cleared or absurd rows-per-page field must never reach the database:
     * a stored 0 meant LIMIT 0 plus a division by zero on every server-side
     * pagination request.
     */
    public function test_rows_per_page_is_clamped_on_save() {
        $_POST = $this->settings_post( array( 'rows_per_page' => '0' ) );
        $this->admin->save_settings_from_post();
        $this->assertSame( 4, get_option( 'rtg_settings' )['rows_per_page'] );

        $_POST = $this->settings_post( array( 'rows_per_page' => '500' ) );
        $this->admin->save_settings_from_post();
        $this->assertSame( 48, get_option( 'rtg_settings' )['rows_per_page'] );
    }

    // --- CSV import ---

    private function csv_tire( $tire_id ) {
        $data = array(
            'tire_id'          => $tire_id,
            'size'             => '275/65R20',
            'diameter'         => '20"',
            'brand'            => 'Michelin',
            'model'            => 'Defender LTX',
            'category'         => 'All-Season',
            'price'            => 299.99,
            'mileage_warranty' => 60000,
            'weight_lb'        => 38.5,
            'three_pms'        => 'No',
            'tread'            => '10/32',
            'load_index'       => '116',
            'max_load_lb'      => 2756,
            'load_range'       => 'SL',
            'speed_rating'     => 'T',
            'psi'              => '51',
            'utqg'             => '620 A B',
            'tags'             => 'oem',
            'link'             => 'https://example.com/tire',
        );
        $efficiency = RTG_Database::calculate_efficiency( $data );
        return array_merge( $data, $efficiency );
    }

    /**
     * An update-mode file carrying only some columns must leave every other
     * stored column untouched — a partial price file once blanked size,
     * category, links and tags, and re-derived the grade from empty specs.
     */
    public function test_partial_csv_update_only_writes_the_columns_present() {
        RTG_Database::insert_tire( $this->csv_tire( 'CSV-001' ) );
        $before = RTG_Database::get_tire( 'CSV-001' );

        $col_map = array( 'tire_id' => 0, 'price' => 1 );
        $result  = $this->admin->import_csv_row( array( 'CSV-001', '199.99' ), $col_map, 'update' );

        $this->assertSame( 'updated', $result );
        $after = RTG_Database::get_tire( 'CSV-001' );
        $this->assertEquals( 199.99, floatval( $after['price'] ) );
        $this->assertSame( $before['size'], $after['size'] );
        $this->assertSame( $before['category'], $after['category'] );
        $this->assertSame( $before['link'], $after['link'] );
        $this->assertSame( $before['tags'], $after['tags'] );
        $this->assertSame( $before['weight_lb'], $after['weight_lb'] );
        $this->assertSame( $before['mileage_warranty'], $after['mileage_warranty'] );
        $this->assertSame(
            $before['efficiency_grade'],
            $after['efficiency_grade'],
            'a price-only update must not re-derive the grade from blanked specs'
        );
    }

    public function test_duplicate_csv_row_is_skipped_outside_update_mode() {
        RTG_Database::insert_tire( $this->csv_tire( 'CSV-002' ) );
        $before = RTG_Database::get_tire( 'CSV-002' );

        $col_map = array( 'tire_id' => 0, 'price' => 1 );
        $result  = $this->admin->import_csv_row( array( 'CSV-002', '1.00' ), $col_map, 'skip' );

        $this->assertSame( 'skipped', $result );
        $this->assertSame( $before['price'], RTG_Database::get_tire( 'CSV-002' )['price'] );
    }

    /**
     * The export was advertised as a re-importable backup while silently
     * dropping model_aliases (which drive discovery matching) and
     * bundle_link. Both are in the CSV contract now, round-trip included.
     */
    public function test_csv_carries_model_aliases_and_bundle_link() {
        $col_map = array(
            'tire_id'       => 0,
            'brand'         => 1,
            'model'         => 2,
            'model_aliases' => 3,
            'bundle_link'   => 4,
        );
        $result = $this->admin->import_csv_row(
            array( 'CSV-010', 'Michelin', 'Defender LTX', "Defender LTX MS2\nDefender MS2", 'https://example.com/bundle' ),
            $col_map,
            'skip'
        );

        $this->assertSame( 'imported', $result );
        $tire = RTG_Database::get_tire( 'CSV-010' );
        $this->assertSame( "Defender LTX MS2\nDefender MS2", $tire['model_aliases'], 'aliases keep their one-per-line shape' );
        $this->assertSame( 'https://example.com/bundle', $tire['bundle_link'] );
    }

    /**
     * A blocked duplicate save stashes the whole submission; the edit form
     * takes it back exactly once.
     */
    public function test_blocked_save_stash_is_returned_once_then_cleared() {
        set_transient( 'rtg_blocked_save_' . get_current_user_id(), array( 'brand' => 'Michelin', 'price' => 250.0 ), 60 );

        $this->assertSame(
            array( 'brand' => 'Michelin', 'price' => 250.0 ),
            RTG_Admin::take_blocked_save()
        );
        $this->assertSame( array(), RTG_Admin::take_blocked_save(), 'the stash is one-shot' );
    }

    public function test_new_csv_row_is_imported_with_a_derived_grade() {
        $col_map = array( 'tire_id' => 0, 'brand' => 1, 'model' => 2, 'size' => 3, 'weight_lb' => 4, 'category' => 5 );
        $result  = $this->admin->import_csv_row(
            array( 'CSV-003', 'Goodyear', 'Wrangler', '275/65R20', '42', 'All-Terrain' ),
            $col_map,
            'skip'
        );

        $this->assertSame( 'imported', $result );
        $tire = RTG_Database::get_tire( 'CSV-003' );
        $this->assertSame( 'Goodyear', $tire['brand'] );
        $this->assertContains( $tire['efficiency_grade'], array( 'A', 'B', 'C', 'D', 'F' ) );
    }
}
