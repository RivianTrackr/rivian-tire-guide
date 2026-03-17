<?php
/**
 * Tests for RTG_Mailer class.
 */
class Test_RTG_Mailer extends WP_UnitTestCase {

    public function test_is_opted_out_returns_false_by_default() {
        $this->assertFalse( RTG_Mailer::is_opted_out( 'test@example.com' ) );
    }

    public function test_is_opted_out_respects_user_meta() {
        $user_id = $this->factory->user->create( array( 'user_email' => 'optout@example.com' ) );
        update_user_meta( $user_id, 'rtg_email_optout', 1 );

        $this->assertTrue( RTG_Mailer::is_opted_out( 'optout@example.com' ) );

        // Cleanup.
        delete_user_meta( $user_id, 'rtg_email_optout' );
    }

    public function test_is_opted_out_respects_guest_option() {
        $email = 'guest-optout@example.com';
        $hash  = hash( 'sha256', strtolower( trim( $email ) ) );
        update_option( 'rtg_optout_' . $hash, 1, false );

        $this->assertTrue( RTG_Mailer::is_opted_out( $email ) );

        // Cleanup.
        delete_option( 'rtg_optout_' . $hash );
    }

    public function test_get_unsubscribe_url_contains_params() {
        $url = RTG_Mailer::get_unsubscribe_url( 'test@example.com' );
        $this->assertStringContainsString( 'rtg_unsubscribe=1', $url );
        $this->assertStringContainsString( 'email=', $url );
        $this->assertStringContainsString( 'token=', $url );
    }

    public function test_send_approval_skips_opted_out_user() {
        $user_id = $this->factory->user->create( array( 'user_email' => 'skip@example.com' ) );
        update_user_meta( $user_id, 'rtg_email_optout', 1 );

        $result = RTG_Mailer::send_approval_notification(
            'skip@example.com',
            'Test User',
            array( 'rating' => 5, 'tire_id' => 'test-001' ),
            array( 'brand' => 'TestBrand', 'model' => 'TestModel' )
        );

        $this->assertFalse( $result );

        // Cleanup.
        delete_user_meta( $user_id, 'rtg_email_optout' );
    }

    public function test_send_approval_rejects_invalid_email() {
        $result = RTG_Mailer::send_approval_notification(
            'not-an-email',
            'Test User',
            array( 'rating' => 5, 'tire_id' => 'test-001' ),
            array( 'brand' => 'TestBrand', 'model' => 'TestModel' )
        );

        $this->assertFalse( $result );
    }
}
