<?php
/**
 * Tests for RTG_Frontend — the URLs the shortcodes hand to their scripts.
 */
class Test_RTG_Frontend extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        RTG_Activator::activate();
    }

    public function tearDown(): void {
        delete_option( 'rtg_settings' );
        parent::tearDown();
    }

    public function test_user_reviews_url_is_the_bare_page_for_no_user() {
        update_option( 'rtg_settings', array( 'user_reviews_slug' => 'user-reviews' ) );
        $this->assertSame( home_url( '/user-reviews/' ), RTG_Frontend::user_reviews_url() );
        $this->assertSame( home_url( '/user-reviews/' ), RTG_Frontend::user_reviews_url( 0 ) );
    }

    public function test_user_reviews_url_names_a_member() {
        update_option( 'rtg_settings', array( 'user_reviews_slug' => 'user-reviews' ) );
        $this->assertSame( home_url( '/user-reviews/?reviewer=42' ), RTG_Frontend::user_reviews_url( 42 ) );
        $this->assertSame( home_url( '/user-reviews/?reviewer=42' ), RTG_Frontend::user_reviews_url( '42' ) );
    }

    public function test_user_reviews_url_follows_the_slug_setting() {
        update_option( 'rtg_settings', array( 'user_reviews_slug' => 'Member Reviews' ) );
        $this->assertSame( home_url( '/member-reviews/?reviewer=7' ), RTG_Frontend::user_reviews_url( 7 ) );
    }

    public function test_user_reviews_url_falls_back_when_the_slug_is_blank() {
        update_option( 'rtg_settings', array( 'user_reviews_slug' => '' ) );
        $this->assertSame( home_url( '/user-reviews/' ), RTG_Frontend::user_reviews_url() );
        delete_option( 'rtg_settings' );
        $this->assertSame( home_url( '/user-reviews/?reviewer=3' ), RTG_Frontend::user_reviews_url( 3 ) );
    }
}
