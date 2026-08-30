<?php
/**
 * Tests for RTG_Rate_Limiter — the one throttle the AJAX and REST layers share.
 */
class Test_RTG_Rate_Limiter extends WP_UnitTestCase {

    public function test_blocks_only_past_the_limit() {
        $fp = 'test-client-' . wp_rand( 1000, 9999 );

        $this->assertFalse( RTG_Rate_Limiter::hit( 'unit', $fp, 2, 60 ), 'first request passes' );
        $this->assertFalse( RTG_Rate_Limiter::hit( 'unit', $fp, 2, 60 ), 'second request passes' );
        $this->assertTrue( RTG_Rate_Limiter::hit( 'unit', $fp, 2, 60 ), 'third request is blocked' );
    }

    public function test_buckets_and_fingerprints_are_independent() {
        $fp = 'test-client-' . wp_rand( 1000, 9999 );

        $this->assertTrue( RTG_Rate_Limiter::hit( 'bucket-a', $fp, 0, 60 ), 'bucket A exhausted' );
        $this->assertFalse( RTG_Rate_Limiter::hit( 'bucket-b', $fp, 1, 60 ), 'bucket B unaffected' );
        $this->assertFalse( RTG_Rate_Limiter::hit( 'bucket-a', $fp . '-other', 1, 60 ), 'another client unaffected' );
    }

    /**
     * Guests behind one shared or unrewritten proxy address must not throttle
     * each other as a single caller — the user agent is part of the identity.
     */
    public function test_guest_fingerprint_distinguishes_user_agents() {
        wp_set_current_user( 0 );

        $_SERVER['REMOTE_ADDR']     = '203.0.113.7';
        $_SERVER['HTTP_USER_AGENT'] = 'Browser A';
        $a = RTG_Rate_Limiter::fingerprint();

        $_SERVER['HTTP_USER_AGENT'] = 'Browser B';
        $b = RTG_Rate_Limiter::fingerprint();

        $this->assertNotSame( $a, $b );
    }

    public function test_logged_in_fingerprint_is_the_user() {
        $user_id = $this->factory->user->create();
        wp_set_current_user( $user_id );

        $this->assertSame( 'u' . $user_id, RTG_Rate_Limiter::fingerprint() );
    }
}
