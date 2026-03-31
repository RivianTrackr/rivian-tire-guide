<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Efficiency {

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'load_template' ) );

        // Flush rewrite rules once after activation or settings change.
        add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
    }

    public function register_rewrite() {
        $settings = get_option( 'rtg_settings', array() );
        $slug = $settings['efficiency_slug'] ?? 'tire-efficiency';
        $slug = sanitize_title( $slug );

        add_rewrite_rule( '^' . $slug . '/?$', 'index.php?rtg_efficiency=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'rtg_efficiency';
        return $vars;
    }

    public function maybe_flush_rewrites() {
        if ( get_option( 'rtg_flush_rewrite' ) ) {
            flush_rewrite_rules();
            delete_option( 'rtg_flush_rewrite' );
            return;
        }

        // Auto-flush once if the efficiency rewrite rule is missing.
        if ( get_transient( 'rtg_efficiency_rewrite_flushed' ) ) {
            return;
        }
        $rules = get_option( 'rewrite_rules' );
        $settings = get_option( 'rtg_settings', array() );
        $slug = sanitize_title( $settings['efficiency_slug'] ?? 'tire-efficiency' );
        if ( ! isset( $rules[ '^' . $slug . '/?$' ] ) ) {
            flush_rewrite_rules();
        }
        set_transient( 'rtg_efficiency_rewrite_flushed', 1, DAY_IN_SECONDS );
    }

    public function load_template() {
        if ( ! get_query_var( 'rtg_efficiency' ) ) {
            return;
        }

        // Security headers for the standalone efficiency page.
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
            'rtg-efficiency',
            RTG_PLUGIN_URL . 'frontend/js/efficiency' . $suffix . '.js',
            array( 'rtg-shared' ),
            RTG_VERSION,
            true
        );

        // Find the page containing the tire guide shortcode for the back link.
        $tire_guide_url = home_url( '/' );
        $guide_pages = get_posts( array(
            'post_type'   => 'page',
            'post_status' => 'publish',
            's'           => '[rivian_tire_guide]',
            'numberposts' => 1,
            'fields'      => 'ids',
        ) );
        if ( ! empty( $guide_pages ) ) {
            $tire_guide_url = get_permalink( $guide_pages[0] );
        }

        // Localize tire data and vehicle map for the efficiency page.
        wp_localize_script( 'rtg-efficiency', 'rtgEfficiency', array(
            'tires'        => RTG_Database::get_tires_as_array(),
            'vehicleSizes' => RTG_Database::get_vehicle_size_map(),
            'tireGuideUrl' => $tire_guide_url,
        ) );

        // Load the efficiency template.
        include RTG_PLUGIN_DIR . 'frontend/templates/efficiency.php';
        exit;
    }
}
