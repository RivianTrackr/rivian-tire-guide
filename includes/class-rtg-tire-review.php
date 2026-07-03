<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * "Write a review" page.
 *
 * Renders inside the active theme at /{tire_review_slug}/ (via RTG_Theme_Render)
 * and is also available as the [rivian_tire_review] shortcode. The review form
 * is JS-driven (tire-review.js). Marked noindex — it's a utility view.
 */
class RTG_Tire_Review {

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render' ) );
        add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
        add_shortcode( 'rivian_tire_review', array( $this, 'shortcode' ) );
    }

    public function register_rewrite() {
        $settings = get_option( 'rtg_settings', array() );
        $slug     = sanitize_title( $settings['tire_review_slug'] ?? 'tire-review' );
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

    /**
     * Enqueue + localize review assets. Shared by the route and shortcode.
     */
    private static function enqueue_assets() {
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

        // Pre-selected tire from ?tire=.
        $preselected = isset( $_GET['tire'] ) ? sanitize_text_field( wp_unslash( $_GET['tire'] ) ) : '';
        if ( $preselected && ! preg_match( '/^[A-Za-z0-9_-]+$/', $preselected ) ) {
            $preselected = '';
        }

        // Lightweight tire list with named keys for the review page JS.
        $review_tires = array();
        foreach ( RTG_Database::get_tires_as_array() as $row ) {
            $review_tires[] = array(
                'tire_id'  => $row[0],
                'brand'    => $row[3],
                'model'    => $row[4],
                'size'     => $row[1],
                'category' => $row[5],
                'image'    => $row[19],
            );
        }

        wp_localize_script( 'rtg-tire-review', 'rtgTireReview', array(
            'tires'           => $review_tires,
            'ajaxurl'         => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'tire_rating_nonce' ),
            'is_logged_in'    => is_user_logged_in(),
            'login_url'       => wp_login_url( home_url( '/' ) ),
            'preselectedTire' => $preselected,
            'tireGuideUrl'    => RTG_Tire_Page::guide_url(),
        ) );
    }

    /**
     * Render the review page inside the theme at its own URL.
     */
    public function maybe_render() {
        if ( ! get_query_var( 'rtg_tire_review' ) ) {
            return;
        }

        self::enqueue_assets();

        RTG_Theme_Render::render( array(
            'title'   => 'Write a Tire Review — Rivian Tire Guide',
            'slug'    => 'tire-review',
            'noindex' => true,
            'content' => function () {
                include RTG_PLUGIN_DIR . 'frontend/templates/tire-review.php';
            },
        ) );
    }

    /**
     * [rivian_tire_review] — render the review UI inside any page.
     */
    public function shortcode( $atts ) {
        self::enqueue_assets();
        ob_start();
        include RTG_PLUGIN_DIR . 'frontend/templates/tire-review.php';
        return ob_get_clean();
    }
}
