<?php
/**
 * Tests for RTG_Security class.
 */
class Test_RTG_Security extends WP_UnitTestCase {

    public function test_encrypt_decrypt_roundtrip() {
        $plaintext = 'sk-ant-api03-test-key-12345';
        $encrypted = RTG_Security::encrypt( $plaintext );

        $this->assertNotEquals( $plaintext, $encrypted );
        $this->assertStringStartsWith( 'enc:', $encrypted );

        $decrypted = RTG_Security::decrypt( $encrypted );
        $this->assertEquals( $plaintext, $decrypted );
    }

    public function test_decrypt_plaintext_passthrough() {
        // Legacy plaintext keys should pass through unchanged.
        $plaintext = 'sk-ant-api03-legacy-key';
        $result = RTG_Security::decrypt( $plaintext );
        $this->assertEquals( $plaintext, $result );
    }

    public function test_encrypt_empty_string() {
        $result = RTG_Security::encrypt( '' );
        $this->assertEquals( '', $result );
    }

    public function test_decrypt_empty_string() {
        $result = RTG_Security::decrypt( '' );
        $this->assertEquals( '', $result );
    }

    public function test_verify_origin_returns_true_by_default() {
        // With no headers set, verify_origin should return true (permissive).
        $result = RTG_Security::verify_origin();
        $this->assertTrue( $result );
    }
}
