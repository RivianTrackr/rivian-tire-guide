<?php
/**
 * PHPUnit bootstrap file for the Rivian Tire Guide plugin.
 *
 * Usage:
 *   1. Set WP_TESTS_DIR to point to the WordPress test library:
 *        export WP_TESTS_DIR=/tmp/wordpress-tests-lib
 *   2. Run:
 *        vendor/bin/phpunit
 *
 * For CI, use the standard wp-env or wp-browser setup script to install the
 * WordPress test suite before running tests.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "Could not find {$_tests_dir}/includes/functions.php — have you run the WP test install script?" . PHP_EOL;
    echo 'Set WP_TESTS_DIR to the wordpress-tests-lib directory.' . PHP_EOL;
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    require dirname( __DIR__ ) . '/rivian-tire-guide.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
