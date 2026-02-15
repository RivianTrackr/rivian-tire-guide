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
        $this->assertArrayHasKey( 'rtg-tires', $submenu );

        // Check expected submenu slugs.
        $slugs = array_map( function ( $item ) {
            return $item[2]; // menu slug is index 2
        }, $submenu['rtg-tires'] );

        $this->assertContains( 'rtg-tires', $slugs );
        $this->assertContains( 'rtg-tire-edit', $slugs );
        $this->assertContains( 'rtg-ratings', $slugs );
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
}
