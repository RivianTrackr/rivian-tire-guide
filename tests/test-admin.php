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
}
