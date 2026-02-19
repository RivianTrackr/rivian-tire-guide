<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Deactivator {

    public static function deactivate() {
        flush_rewrite_rules();
        delete_option( 'rtg_flush_rewrite' );
        wp_clear_scheduled_hook( 'rtg_analytics_cleanup' );
    }
}
