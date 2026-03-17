<?php
/**
 * Tests for RTG_Rate_Limiter class.
 */
class Test_RTG_Rate_Limiter extends WP_UnitTestCase {

    public function test_is_limited_returns_false_initially() {
        $result = RTG_Rate_Limiter::is_limited( 'test_bucket', 5, 60 );
        $this->assertFalse( $result );
    }

    public function test_record_and_limit() {
        $bucket = 'test_limit_' . wp_rand( 1000, 9999 );

        // Should not be limited initially.
        $this->assertFalse( RTG_Rate_Limiter::is_limited( $bucket, 2, 60 ) );

        // Record two hits.
        RTG_Rate_Limiter::record( $bucket, 60 );
        RTG_Rate_Limiter::record( $bucket, 60 );

        // Should now be limited.
        $this->assertTrue( RTG_Rate_Limiter::is_limited( $bucket, 2, 60 ) );
    }

    public function test_check_returns_false_under_limit() {
        $bucket = 'test_check_' . wp_rand( 1000, 9999 );
        $result = RTG_Rate_Limiter::check( $bucket, 10, 60 );
        $this->assertFalse( $result );
    }

    public function test_session_hash_returns_consistent_value() {
        $hash1 = RTG_Rate_Limiter::session_hash();
        $hash2 = RTG_Rate_Limiter::session_hash();
        $this->assertEquals( $hash1, $hash2 );
        $this->assertEquals( 64, strlen( $hash1 ) ); // SHA-256 hex length
    }

    public function test_get_client_ip_returns_string() {
        $ip = RTG_Rate_Limiter::get_client_ip();
        $this->assertIsString( $ip );
        $this->assertNotEmpty( $ip );
    }

    public function test_user_rate_limit() {
        $user_id = $this->factory->user->create();
        wp_set_current_user( $user_id );

        $bucket = 'test_user_' . wp_rand( 1000, 9999 );

        $this->assertFalse( RTG_Rate_Limiter::is_user_limited( $bucket, 1, 60 ) );

        RTG_Rate_Limiter::record_user( $bucket, 60 );

        $this->assertTrue( RTG_Rate_Limiter::is_user_limited( $bucket, 1, 60 ) );

        wp_set_current_user( 0 );
    }
}
