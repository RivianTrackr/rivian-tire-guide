<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Compare {

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'load_template' ) );

        // Flush rewrite rules once after activation or settings change.
        add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
    }

    public function register_rewrite() {
        $settings = get_option( 'rtg_settings', array() );
        $slug = $settings['compare_slug'] ?? 'tire-compare';
        $slug = sanitize_title( $slug );

        add_rewrite_rule( '^' . $slug . '/?$', 'index.php?rtg_compare=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'rtg_compare';
        return $vars;
    }

    public function maybe_flush_rewrites() {
        if ( get_option( 'rtg_flush_rewrite' ) ) {
            flush_rewrite_rules();
            delete_option( 'rtg_flush_rewrite' );
        }
    }

    public function load_template() {
        if ( ! get_query_var( 'rtg_compare' ) ) {
            return;
        }

        // Security headers for the standalone compare page.
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https://riviantrackr.com https://cdn.riviantrackr.com data:;" );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );

        $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : (
            file_exists( RTG_PLUGIN_DIR . 'frontend/js/rtg-shared.min.js' ) ? '.min' : ''
        );

        wp_enqueue_script(
            'rtg-shared',
            RTG_PLUGIN_URL . 'frontend/js/rtg-shared' . $suffix . '.js',
            array(),
            RTG_VERSION,
            true
        );

        wp_enqueue_script(
            'rtg-compare',
            RTG_PLUGIN_URL . 'frontend/js/compare' . $suffix . '.js',
            array( 'rtg-shared' ),
            RTG_VERSION,
            true
        );

        // Localize tire data for compare page.
        wp_localize_script( 'rtg-compare', 'rtgData', array(
            'tires' => RTG_Database::get_tires_as_array(),
        ) );

        // Load the compare template.
        include RTG_PLUGIN_DIR . 'frontend/templates/compare.php';
        exit;
    }
}
