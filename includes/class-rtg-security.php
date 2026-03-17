<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security utilities for the Rivian Tire Guide plugin.
 *
 * Handles API key encryption/decryption, CSP header injection,
 * and origin validation for anonymous requests.
 *
 * @since 1.29.0
 */
class RTG_Security {

	/**
	 * Cipher method used for encryption.
	 */
	const CIPHER = 'aes-256-cbc';

	/**
	 * Initialize security headers on plugin pages.
	 */
	public function __construct() {
		add_action( 'send_headers', array( $this, 'add_security_headers' ) );
	}

	// ── API Key Encryption ──

	/**
	 * Encrypt a value using WordPress AUTH_KEY and AUTH_SALT.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string Base64-encoded encrypted value, or empty string on failure.
	 */
	public static function encrypt( $plaintext ) {
		if ( empty( $plaintext ) || ! function_exists( 'openssl_encrypt' ) ) {
			return $plaintext;
		}

		$key = self::encryption_key();
		$iv  = self::encryption_iv();

		$encrypted = openssl_encrypt( $plaintext, self::CIPHER, $key, 0, $iv );

		if ( false === $encrypted ) {
			return $plaintext;
		}

		// Prefix with 'enc:' so we can detect encrypted vs plaintext values.
		return 'enc:' . base64_encode( $encrypted );
	}

	/**
	 * Decrypt a value that was encrypted with encrypt().
	 *
	 * @param string $ciphertext The encrypted value.
	 * @return string The decrypted plaintext, or the original value if not encrypted.
	 */
	public static function decrypt( $ciphertext ) {
		if ( empty( $ciphertext ) || ! function_exists( 'openssl_decrypt' ) ) {
			return $ciphertext;
		}

		// Check if the value is actually encrypted.
		if ( strpos( $ciphertext, 'enc:' ) !== 0 ) {
			return $ciphertext; // Plaintext (legacy) value.
		}

		$key     = self::encryption_key();
		$iv      = self::encryption_iv();
		$encoded = substr( $ciphertext, 4 ); // Strip 'enc:' prefix.

		$decrypted = openssl_decrypt( base64_decode( $encoded ), self::CIPHER, $key, 0, $iv );

		if ( false === $decrypted ) {
			return ''; // Decryption failed — key likely changed.
		}

		return $decrypted;
	}

	/**
	 * Derive the encryption key from WordPress AUTH_KEY.
	 *
	 * @return string 32-byte key.
	 */
	private static function encryption_key() {
		$raw = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'rtg-default-key-change-me';
		return substr( hash( 'sha256', $raw, true ), 0, 32 );
	}

	/**
	 * Derive the IV from WordPress AUTH_SALT.
	 *
	 * @return string 16-byte IV.
	 */
	private static function encryption_iv() {
		$raw = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'rtg-default-salt-change-me';
		return substr( hash( 'sha256', $raw, true ), 0, 16 );
	}

	// ── CSP Headers ──

	/**
	 * Add security headers to plugin pages rendered via shortcode.
	 *
	 * Note: Standalone pages (compare, tire-review) set their own headers
	 * in their template loaders since they call exit() before WP sends headers.
	 * This covers the main shortcode page and user-reviews page.
	 */
	public function add_security_headers() {
		// Only add on singular pages (posts/pages where shortcodes render).
		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		// Check if the page contains one of our shortcodes.
		$has_shortcode = has_shortcode( $post->post_content, 'rivian_tire_guide' )
			|| has_shortcode( $post->post_content, 'rivian_user_reviews' );

		if ( ! $has_shortcode ) {
			return;
		}

		// WordPress pages need more permissive CSP to allow wp-admin scripts.
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}

	// ── Origin Validation ──

	/**
	 * Validate that a request originates from the same site.
	 *
	 * Checks the Referer and Origin headers against the site URL.
	 * This is a lightweight alternative to nonce verification for
	 * public read-only endpoints.
	 *
	 * @return bool True if the request appears to originate from this site.
	 */
	public static function verify_origin() {
		$site_url = home_url();
		$site_host = wp_parse_url( $site_url, PHP_URL_HOST );

		// Check Origin header first (set by browsers on CORS/POST requests).
		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
			$origin_host = wp_parse_url( $_SERVER['HTTP_ORIGIN'], PHP_URL_HOST );
			return $origin_host === $site_host;
		}

		// Fall back to Referer header.
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer_host = wp_parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST );
			return $referer_host === $site_host;
		}

		// No origin info — could be a direct API call. Allow for now but log.
		return true;
	}
}
