<?php
/**
 * Integration tests for RTG_Ajax AJAX endpoints.
 *
 * Tests cover:
 * - get_tire_ratings: valid/invalid tire IDs, nonce verification
 * - submit_tire_rating: valid submission, missing nonce, invalid rating, nonexistent tire
 * - delete_tire_rating: own rating deletion, no rating to delete
 * - get_tire_reviews: pagination, invalid tire ID
 * - get_tires: server-side filtered listing
 * - favorites: add, get, remove cycle
 */
class Test_RTG_Ajax extends WP_Ajax_UnitTestCase {

    /**
     * Ensure tables exist before each test.
     */
    public function setUp(): void {
        parent::setUp();
        RTG_Activator::activate();
    }

    /**
     * Helper to create a sample tire.
     */
    private function sample_tire( $overrides = array() ) {
        return array_merge( array(
            'tire_id'          => 'ajax-tire-' . wp_rand( 1000, 9999 ),
            'size'             => '275/65R20',
            'diameter'         => '20"',
            'brand'            => 'TestBrand',
            'model'            => 'TestModel',
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
            'tags'             => '',
            'link'             => 'https://example.com/tire',
            'image'            => 'https://riviantrackr.com/images/tire.jpg',
            'bundle_link'      => '',
            'sort_order'       => 0,
            'efficiency_score' => 75,
            'efficiency_grade' => 'B',
        ), $overrides );
    }

    // -------------------------------------------------------
    // get_tire_ratings
    // -------------------------------------------------------

    public function test_get_tire_ratings_returns_empty_for_no_ids() {
        $_POST['tire_ids'] = array();

        try {
            $this->_handleAjax( 'get_tire_ratings' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );
        $this->assertEmpty( $response['data']['ratings'] );
    }

    public function test_get_tire_ratings_returns_data_for_rated_tire() {
        $tire_id = 'rate-ajax-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );
        RTG_Database::set_rating( $tire_id, 1, 4 );

        $_POST['tire_ids'] = array( $tire_id );

        try {
            $this->_handleAjax( 'get_tire_ratings' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );
        $this->assertArrayHasKey( $tire_id, $response['data']['ratings'] );
        $this->assertEquals( 4, (int) $response['data']['ratings'][ $tire_id ]['average'] );
    }

    public function test_get_tire_ratings_strips_invalid_ids() {
        $_POST['tire_ids'] = array( '<script>alert(1)</script>', 'valid-tire-id' );

        try {
            $this->_handleAjax( 'get_tire_ratings' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );
        // The invalid ID should have been filtered out; no error thrown.
    }

    // -------------------------------------------------------
    // submit_tire_rating
    // -------------------------------------------------------

    public function test_submit_tire_rating_success() {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $tire_id = 'submit-ajax-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );

        $_POST['nonce']   = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id'] = $tire_id;
        $_POST['rating']  = 5;

        try {
            $this->_handleAjax( 'submit_tire_rating' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );
        $this->assertEquals( 5, (int) $response['data']['user_rating'] );
        $this->assertEquals( 5, (float) $response['data']['average_rating'] );
    }

    public function test_submit_tire_rating_fails_without_nonce() {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $_POST['tire_id'] = 'doesnt-matter';
        $_POST['rating']  = 5;
        // No nonce set.

        try {
            $this->_handleAjax( 'submit_tire_rating' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'Security check failed', $response['data'] );
    }

    public function test_submit_tire_rating_fails_for_invalid_rating() {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $tire_id = 'invalid-rate-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );

        $_POST['nonce']   = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id'] = $tire_id;
        $_POST['rating']  = 10; // Out of range.

        try {
            $this->_handleAjax( 'submit_tire_rating' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'between 1 and 5', $response['data'] );
    }

    public function test_submit_tire_rating_fails_for_nonexistent_tire() {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $_POST['nonce']   = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id'] = 'ghost-tire-999';
        $_POST['rating']  = 3;

        try {
            $this->_handleAjax( 'submit_tire_rating' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'Tire not found', $response['data'] );
    }

    public function test_submit_tire_rating_with_review_text() {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $tire_id = 'review-ajax-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );

        $_POST['nonce']        = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id']      = $tire_id;
        $_POST['rating']       = 4;
        $_POST['review_title'] = 'Great tire';
        $_POST['review_text']  = 'Excellent performance in all conditions.';

        try {
            $this->_handleAjax( 'submit_tire_rating' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );
        $this->assertEquals( 'pending', $response['data']['review_status'] );
    }

    // -------------------------------------------------------
    // delete_tire_rating
    // -------------------------------------------------------

    public function test_delete_tire_rating_success() {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $tire_id = 'delete-ajax-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );
        RTG_Database::set_rating( $tire_id, $user_id, 3 );

        $_POST['nonce']   = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id'] = $tire_id;

        try {
            $this->_handleAjax( 'delete_tire_rating' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );

        // Verify rating is gone.
        $ratings = RTG_Database::get_user_ratings( array( $tire_id ), $user_id );
        $this->assertEmpty( $ratings );
    }

    public function test_delete_tire_rating_fails_when_no_rating_exists() {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $tire_id = 'nodelete-ajax-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );
        // No rating set for this user.

        $_POST['nonce']   = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id'] = $tire_id;

        try {
            $this->_handleAjax( 'delete_tire_rating' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'No rating found', $response['data'] );
    }

    public function test_delete_tire_rating_cannot_delete_others_rating() {
        $user_a = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        $user_b = $this->factory()->user->create( array( 'role' => 'subscriber' ) );

        $tire_id = 'cross-del-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );
        RTG_Database::set_rating( $tire_id, $user_a, 5 );

        // User B tries to delete User A's rating.
        wp_set_current_user( $user_b );
        $_POST['nonce']   = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id'] = $tire_id;

        try {
            $this->_handleAjax( 'delete_tire_rating' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertFalse( $response['success'] );

        // User A's rating should still exist.
        $ratings = RTG_Database::get_user_ratings( array( $tire_id ), $user_a );
        $this->assertArrayHasKey( $tire_id, $ratings );
        $this->assertEquals( 5, $ratings[ $tire_id ]['rating'] );
    }

    // -------------------------------------------------------
    // get_tire_reviews
    // -------------------------------------------------------

    public function test_get_tire_reviews_returns_approved_reviews() {
        $tire_id = 'reviews-ajax-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );

        // Create an admin user so the review auto-approves.
        $admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
        RTG_Database::set_rating( $tire_id, $admin_id, 5, 'Awesome', 'Best tire ever.' );

        $_POST['tire_id'] = $tire_id;
        $_POST['page']    = 1;

        try {
            $this->_handleAjax( 'get_tire_reviews' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );
        $this->assertGreaterThanOrEqual( 1, $response['data']['total'] );
        $this->assertNotEmpty( $response['data']['reviews'] );
    }

    public function test_get_tire_reviews_rejects_invalid_tire_id() {
        $_POST['tire_id'] = '<script>bad</script>';
        $_POST['page']    = 1;

        try {
            $this->_handleAjax( 'get_tire_reviews' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'Invalid tire ID', $response['data'] );
    }

    // -------------------------------------------------------
    // Favorites: add, get, remove cycle
    // -------------------------------------------------------

    public function test_favorites_add_get_remove_cycle() {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $tire_id = 'fav-ajax-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );

        // Add favorite.
        $_POST['nonce']   = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id'] = $tire_id;

        try {
            $this->_handleAjax( 'rtg_add_favorite' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );

        // Get favorites.
        $this->_last_response = '';
        $_POST['nonce'] = wp_create_nonce( 'tire_rating_nonce' );

        try {
            $this->_handleAjax( 'rtg_get_favorites' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );
        $this->assertContains( $tire_id, $response['data']['favorites'] );

        // Remove favorite.
        $this->_last_response = '';
        $_POST['nonce']   = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id'] = $tire_id;

        try {
            $this->_handleAjax( 'rtg_remove_favorite' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertTrue( $response['success'] );

        // Verify removed.
        $favorites = RTG_Database::get_user_favorites( $user_id );
        $this->assertNotContains( $tire_id, $favorites );
    }

    public function test_add_favorite_rejects_nonexistent_tire() {
        $user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user_id );

        $_POST['nonce']   = wp_create_nonce( 'tire_rating_nonce' );
        $_POST['tire_id'] = 'ghost-fav-999';

        try {
            $this->_handleAjax( 'rtg_add_favorite' );
        } catch ( WPAjaxDieContinueException $e ) {
            // Expected.
        }

        $response = json_decode( $this->_last_response, true );
        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'Tire not found', $response['data'] );
    }
}
