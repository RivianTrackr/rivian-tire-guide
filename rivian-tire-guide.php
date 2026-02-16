<?php
/**
 * Plugin Name: Rivian Tire Guide
 * Description: Interactive tire guide for Rivian vehicles with filtering, comparison, and ratings.
 * Version: 1.5.0
 * Author: RivianTrackr
 * Text Domain: rivian-tire-guide
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RTG_VERSION', '1.5.0' );
define( 'RTG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RTG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RTG_PLUGIN_FILE', __FILE__ );
define( 'RTG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include class files.
require_once RTG_PLUGIN_DIR . 'includes/class-rtg-activator.php';
require_once RTG_PLUGIN_DIR . 'includes/class-rtg-deactivator.php';
require_once RTG_PLUGIN_DIR . 'includes/class-rtg-database.php';
require_once RTG_PLUGIN_DIR . 'includes/class-rtg-admin.php';
require_once RTG_PLUGIN_DIR . 'includes/class-rtg-frontend.php';
require_once RTG_PLUGIN_DIR . 'includes/class-rtg-ajax.php';
require_once RTG_PLUGIN_DIR . 'includes/class-rtg-compare.php';
require_once RTG_PLUGIN_DIR . 'includes/class-rtg-schema.php';
require_once RTG_PLUGIN_DIR . 'includes/class-rtg-meta.php';

// Activation / Deactivation hooks.
register_activation_hook( __FILE__, array( 'RTG_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RTG_Deactivator', 'deactivate' ) );

// Initialize plugin components.
add_action( 'plugins_loaded', 'rtg_init' );

function rtg_init() {
    // Run pending database migrations on update.
    RTG_Activator::maybe_upgrade();

    // Admin panel.
    if ( is_admin() ) {
        new RTG_Admin();
    }

    // Frontend shortcode and assets.
    new RTG_Frontend();

    // AJAX handlers (both admin and frontend).
    new RTG_Ajax();

    // Compare page rewrite rules.
    new RTG_Compare();

    // Schema.org structured data.
    new RTG_Schema();

    // Open Graph / Twitter Card meta tags.
    new RTG_Meta();
}
