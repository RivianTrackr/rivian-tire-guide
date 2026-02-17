<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Deactivator {

    public static function deactivate() {
        flush_rewrite_rules();
        delete_option( 'rtg_flush_rewrite' );

        // Unschedule the weekly price fetch cron.
        RTG_Price_Fetcher::unschedule();
    }
}
