<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized rate limiting and IP detection utility.
 *
 * Provides a single, consistent implementation of rate limiting
 * and client IP detection used across AJAX, REST API, and AI endpoints.
 *
 * @since 1.29.0
 */
class RTG_Rate_Limiter {

	/**
	 * Check whether the current visitor has exceeded a rate limit.
	 *
	 * @param string $bucket Short identifier for the rate limit bucket (e.g. 'ai', 'review', 'guest_review').
	 * @param int    $limit  Maximum requests allowed per window.
	 * @param int    $window Window length in seconds (default 60).
	 * @return bool True if rate-limited.
	 */
	public static function is_limited( $bucket, $limit, $window = 60 ) {
		$key      = self::transient_key( $bucket );
		$attempts = get_transient( $key );

		if ( false === $attempts ) {
			return false;
		}

		return (int) $attempts >= $limit;
	}

	/**
	 * Record a rate limit hit for the current visitor.
	 *
	 * @param string $bucket Short identifier for the rate limit bucket.
	 * @param int    $window Window length in seconds (default 60).
	 */
	public static function record( $bucket, $window = 60 ) {
		$key      = self::transient_key( $bucket );
		$attempts = get_transient( $key );

		if ( false === $attempts ) {
			set_transient( $key, 1, $window );
		} else {
			set_transient( $key, (int) $attempts + 1, $window );
		}
	}

	/**
	 * Check rate limit and record in one step. Returns WP_Error if limited.
	 *
	 * @param string $bucket Short identifier for the rate limit bucket.
	 * @param int    $limit  Maximum requests allowed per window.
	 * @param int    $window Window length in seconds (default 60).
	 * @return true|WP_Error True if allowed, WP_Error if rate-limited.
	 */
	public static function check( $bucket, $limit, $window = 60 ) {
		if ( self::is_limited( $bucket, $limit, $window ) ) {
			return new WP_Error(
				'rtg_rate_limit',
				'Rate limit exceeded. Please try again later.',
				array( 'status' => 429 )
			);
		}

		self::record( $bucket, $window );
		return true;
	}

	/**
	 * Build a transient key for a given bucket.
	 *
	 * @param string $bucket Bucket identifier.
	 * @return string Transient key.
	 */
	private static function transient_key( $bucket ) {
		return 'rtg_rl_' . $bucket . '_' . md5( self::get_client_ip() );
	}

	/**
	 * Build a transient key for a specific user ID.
	 *
	 * @param string $bucket  Bucket identifier.
	 * @param int    $user_id WordPress user ID.
	 * @return string Transient key.
	 */
	public static function user_transient_key( $bucket, $user_id ) {
		return 'rtg_rl_' . $bucket . '_u' . intval( $user_id );
	}

	/**
	 * Check rate limit for a specific user.
	 *
	 * @param string $bucket  Bucket identifier.
	 * @param int    $user_id WordPress user ID.
	 * @param int    $limit   Maximum requests allowed per window.
	 * @param int    $window  Window length in seconds.
	 * @return bool True if rate-limited.
	 */
	public static function is_user_limited( $bucket, $user_id, $limit, $window = 60 ) {
		$key      = self::user_transient_key( $bucket, $user_id );
		$attempts = get_transient( $key );

		if ( false === $attempts ) {
			return false;
		}

		return (int) $attempts >= $limit;
	}

	/**
	 * Record a rate limit hit for a specific user.
	 *
	 * @param string $bucket  Bucket identifier.
	 * @param int    $user_id WordPress user ID.
	 * @param int    $window  Window length in seconds.
	 */
	public static function record_user( $bucket, $user_id, $window = 60 ) {
		$key      = self::user_transient_key( $bucket, $user_id );
		$attempts = get_transient( $key );

		if ( false === $attempts ) {
			set_transient( $key, 1, $window );
		} else {
			set_transient( $key, (int) $attempts + 1, $window );
		}
	}

	/**
	 * Get the client's IP address.
	 *
	 * Uses REMOTE_ADDR as the primary source since it cannot be spoofed
	 * at the HTTP level. Only falls back to proxy headers when REMOTE_ADDR
	 * is a private/reserved IP (indicating the server is behind a reverse proxy).
	 *
	 * @return string IP address.
	 */
	public static function get_client_ip() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( $_SERVER['REMOTE_ADDR'] ) : '';

		if ( ! empty( $remote_addr ) && filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			// Only trust proxy headers when REMOTE_ADDR is a private/reserved IP,
			// which indicates the request came through a trusted reverse proxy.
			$is_proxied = ! filter_var(
				$remote_addr,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			);

			if ( $is_proxied ) {
				$proxy_headers = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' );
				foreach ( $proxy_headers as $header ) {
					if ( ! empty( $_SERVER[ $header ] ) ) {
						// X-Forwarded-For can contain multiple IPs; take the first.
						$ip = strtok( $_SERVER[ $header ], ',' );
						$ip = trim( $ip );
						if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
							return $ip;
						}
					}
				}
			}

			return $remote_addr;
		}

		return '0.0.0.0';
	}

	/**
	 * Generate a privacy-safe session hash for analytics.
	 *
	 * Uses a server-side pepper (generated once and stored in options)
	 * combined with IP + User-Agent + date to create a hash that
	 * cannot be brute-forced to recover the original IP.
	 *
	 * @return string SHA-256 session hash.
	 */
	public static function session_hash() {
		$pepper = get_option( 'rtg_session_pepper', '' );
		if ( empty( $pepper ) ) {
			$pepper = wp_generate_password( 64, true, true );
			update_option( 'rtg_session_pepper', $pepper, false );
		}

		$ip = self::get_client_ip();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		return hash( 'sha256', $pepper . $ip . $ua . gmdate( 'Y-m-d' ) );
	}
}
