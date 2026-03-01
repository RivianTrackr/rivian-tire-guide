<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Tire_Review {

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'load_template' ) );

        // Flush rewrite rules once after activation or settings change.
        add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
    }

    public function register_rewrite() {
        $settings = get_option( 'rtg_settings', array() );
        $slug = $settings['tire_review_slug'] ?? 'tire-review';
        $slug = sanitize_title( $slug );

        add_rewrite_rule( '^' . $slug . '/?$', 'index.php?rtg_tire_review=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'rtg_tire_review';
        return $vars;
    }

    public function maybe_flush_rewrites() {
        if ( get_option( 'rtg_flush_rewrite' ) ) {
            flush_rewrite_rules();
            delete_option( 'rtg_flush_rewrite' );
        }
    }

    public function load_template() {
        if ( ! get_query_var( 'rtg_tire_review' ) ) {
            return;
        }

        // Security headers for the standalone review page.
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
            'rtg-tire-review',
            RTG_PLUGIN_URL . 'frontend/js/tire-review.js',
            array( 'rtg-shared' ),
            RTG_VERSION,
            true
        );

        // Pre-selected tire from query param.
        $preselected = isset( $_GET['tire'] ) ? sanitize_text_field( wp_unslash( $_GET['tire'] ) ) : '';
        if ( $preselected && ! preg_match( '/^[A-Za-z0-9_-]+$/', $preselected ) ) {
            $preselected = '';
        }

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

        // Localize tire data and review config for the review page.
        wp_localize_script( 'rtg-tire-review', 'rtgTireReview', array(
            'tires'           => RTG_Database::get_tires_as_array(),
            'ajaxurl'         => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'tire_rating_nonce' ),
            'is_logged_in'    => is_user_logged_in(),
            'login_url'       => wp_login_url( home_url( '/' ) ),
            'register_url'    => wp_registration_url(),
            'preselectedTire' => $preselected,
            'tireGuideUrl'    => $tire_guide_url,
        ) );

        // Load the tire review template.
        include RTG_PLUGIN_DIR . 'frontend/templates/tire-review.php';
        exit;
    }
}
