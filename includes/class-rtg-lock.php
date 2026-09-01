<?php
/**
 * Mutual exclusion for the plugin's background jobs.
 *
 * Every sync used to run unguarded: the five-minute Roamer tick could
 * outlive its own interval and overlap itself, and "Run Discovery Now"
 * could start while the nightly cron was mid-flight — two writers upserting
 * the same candidates, both advancing the sweep cursor, both firing link
 * and price sync. A lock is taken for the run's worst-case duration and
 * released when it finishes; a run that dies without releasing (a fatal, a
 * killed request) leaves a lock that expires on its own.
 *
 * With a persistent object cache the lock is an atomic `wp_cache_add`.
 * Without one it is a row in wp_options inserted directly — the unique
 * index on option_name is what makes the insert atomic. The options API is
 * bypassed on purpose: add_option() checks then inserts (two callers can
 * both pass the check) and its INSERT carries ON DUPLICATE KEY UPDATE,
 * which would let the loser overwrite the winner.
 *
 * @since 1.87.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Lock {

    /** Option / cache key prefix. */
    const PREFIX = 'rtg_lock_';

    /** Object cache group. */
    const GROUP = 'rtg_locks';

    /**
     * Try to take a named lock.
     *
     * @param string $name Lock name (e.g. "catalog_sync").
     * @param int    $ttl  Seconds after which an unreleased lock is considered
     *                     abandoned and may be taken over.
     * @return bool True when this caller now holds the lock.
     */
    public static function acquire( $name, $ttl ) {
        $key     = self::key( $name );
        $ttl     = max( 1, intval( $ttl ) );
        $now     = time();
        $expires = $now + $ttl;

        if ( self::using_object_cache() ) {
            return (bool) wp_cache_add( $key, $expires, self::GROUP, $ttl );
        }

        global $wpdb;

        // A plain INSERT: on a duplicate option_name it fails, and the failure
        // is the answer. wpdb suppresses the SQL error and returns false.
        $suppress = $wpdb->suppress_errors( true );
        $inserted = $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $key,
            (string) $expires
        ) );
        $wpdb->suppress_errors( $suppress );

        if ( $inserted ) {
            return true;
        }

        // Held by someone. Take it over only if it has expired, and only if
        // it still carries the expiry we read — a second taker racing us
        // matches zero rows.
        $held = $wpdb->get_var( $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $key
        ) );

        if ( null === $held ) {
            // Released between our INSERT and SELECT; try once more.
            return self::acquire( $name, $ttl );
        }

        if ( intval( $held ) > $now ) {
            return false;
        }

        $taken = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
            (string) $expires,
            $key,
            (string) $held
        ) );

        return $taken > 0;
    }

    /**
     * Release a lock this caller holds.
     *
     * @param string $name Lock name.
     */
    public static function release( $name ) {
        $key = self::key( $name );

        if ( self::using_object_cache() ) {
            wp_cache_delete( $key, self::GROUP );
            return;
        }

        global $wpdb;
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $key ) );
    }

    /**
     * Whether a lock is currently held (and unexpired).
     *
     * @param string $name Lock name.
     * @return bool
     */
    public static function is_held( $name ) {
        $key = self::key( $name );

        if ( self::using_object_cache() ) {
            return false !== wp_cache_get( $key, self::GROUP );
        }

        global $wpdb;
        $held = $wpdb->get_var( $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $key
        ) );

        return null !== $held && intval( $held ) > time();
    }

    /**
     * The result a job returns when it finds another run in progress.
     *
     * Shaped like the job's other early returns ('disabled') so the admin
     * scripts and stats readers need no special case beyond the status.
     *
     * @param string $what Human name of the job, for the message.
     * @return array
     */
    public static function busy_result( $what ) {
        return array(
            'status'  => 'locked',
            'message' => sprintf( 'Another %s is already running. Try again in a minute.', $what ),
            'time'    => current_time( 'mysql' ),
        );
    }

    private static function key( $name ) {
        return self::PREFIX . sanitize_key( $name );
    }

    private static function using_object_cache() {
        return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
    }
}
