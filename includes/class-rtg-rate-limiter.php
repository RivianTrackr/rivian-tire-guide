<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The one rate limiter.
 *
 * The plugin had grown several bespoke throttles with different semantics —
 * the AJAX layer's object-cache-aware counter keyed on a user/IP+UA
 * fingerprint, and a REST copy keyed on raw REMOTE_ADDR with a non-atomic
 * transient, which throttled a whole site as one client behind a proxy that
 * doesn't rewrite the address. Both now share this implementation.
 *
 * @since 1.86.0
 */
class RTG_Rate_Limiter {

    /**
     * Identify the current client.
     *
     * Logged-in users are their user ID. Guests are IP + user agent, hashed —
     * the user agent keeps distinct clients behind one shared or unrewritten
     * proxy address from throttling each other as a single caller.
     *
     * @return string Opaque per-client identifier.
     */
    public static function fingerprint() {
        if ( is_user_logged_in() ) {
            return 'u' . get_current_user_id();
        }
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 200 ) : '';
        return 'g' . md5( $ip . '|' . $ua );
    }

    /**
     * Count one request against a bucket and report whether the caller has
     * exceeded $limit within $window seconds.
     *
     * With a persistent object cache (Redis, Memcached) the increment is
     * race-free via `wp_cache_add` + `wp_cache_incr`. The transient fallback
     * is best-effort but bounded: under pathological concurrency the counter
     * drifts by the number of simultaneous writers, not unbounded.
     *
     * @param string $bucket      Logical bucket (e.g. "review", "rest_read").
     * @param string $fingerprint Opaque per-client identifier.
     * @param int    $limit       Max requests allowed in the window.
     * @param int    $window      Window length in seconds.
     * @return bool True when the request should be blocked.
     */
    public static function hit( $bucket, $fingerprint, $limit, $window ) {
        $key = 'rtg_' . $bucket . '_' . md5( $fingerprint );

        if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
            $group = 'rtg_rate_limit';
            if ( wp_cache_add( $key, 1, $group, $window ) ) {
                return 1 > $limit;
            }
            $count = wp_cache_incr( $key, 1, $group );
            return (int) $count > $limit;
        }

        $current = get_transient( $key );
        $new     = ( false === $current ) ? 1 : (int) $current + 1;
        set_transient( $key, $new, $window );
        return $new > $limit;
    }
}
