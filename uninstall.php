<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rtg_tires" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rtg_ratings" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rtg_favorites" );

delete_option( 'rtg_version' );
delete_option( 'rtg_db_version' );
delete_option( 'rtg_settings' );
delete_option( 'rtg_dropdown_options' );
delete_option( 'rtg_flush_rewrite' );
