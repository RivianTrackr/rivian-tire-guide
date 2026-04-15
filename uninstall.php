<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rtg_tires" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rtg_ratings" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rtg_favorites" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rtg_click_events" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rtg_search_events" );

delete_option( 'rtg_version' );
delete_option( 'rtg_db_version' );
delete_option( 'rtg_settings' );
delete_option( 'rtg_dropdown_options' );
delete_option( 'rtg_flush_rewrite' );

// Legacy AI feature cleanup (removed in 1.46.0). These are harmless if
// absent — WP's delete_option() and the LIKE delete are both no-ops when
// the option doesn't exist.
delete_option( 'rtg_ai_models_cache' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rtg_ai_%' OR option_name LIKE '_transient_timeout_rtg_ai_%'" );
