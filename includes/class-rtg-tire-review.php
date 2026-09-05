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
        // A real page at the review slug wins over the built-in route, so the
        // owner can hold the page in WordPress (and give it SEO meta).
        add_filter( 'request', array( $this, 'prefer_real_page' ) );
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
                'slug'     => $row[28] ?? '',
            );
        }

        $settings     = get_option( 'rtg_settings', array() );
        $review_slug  = sanitize_title( $settings['tire_review_slug'] ?? 'tire-review' );
        $current_user = wp_get_current_user();

        wp_localize_script( 'rtg-tire-review', 'rtgTireReview', array(
            'tires'           => $review_tires,
            'ajaxurl'         => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'tire_rating_nonce' ),
            'is_logged_in'    => is_user_logged_in(),
            // Back to this page after signing in, not the home page.
            'login_url'       => wp_login_url( home_url( '/' . $review_slug . '/' ) ),
            'preselectedTire' => $preselected,
            'tireGuideUrl'    => RTG_Tire_Page::guide_url(),
            // "See the tire page" after a submit: base + slug + '/'.
            'tirePageBase'    => home_url( '/' . RTG_Tire_Page::slug_base() . '/' ),
            // Landing: a vehicle switch over the size map, and how many
            // approved reviews each tire has, for "most reviewed for your Rivian".
            'vehicleSizeMap'  => RTG_Database::get_vehicle_size_map(),
            'reviewCounts'    => RTG_Database::get_review_counts_by_tire(),
            'vehicles'        => RTG_Database::REVIEW_VEHICLES,
            // The few with an account: who they are, and whether their words
            // post at once (admins) or wait in the queue like a guest's.
            'displayName'     => is_user_logged_in() ? $current_user->display_name : '',
            'autoApprove'     => current_user_can( 'manage_options' ),
        ) );
    }

    /**
     * The review slug from settings.
     */
    public static function slug() {
        $settings = get_option( 'rtg_settings', array() );
        return sanitize_title( $settings['tire_review_slug'] ?? 'tire-review' );
    }

    /**
     * A published WordPress page living at the review slug, if the owner
     * made one (with the [rivian_tire_review] shortcode on it), or null.
     */
    public static function real_page() {
        $page = get_page_by_path( self::slug(), OBJECT, 'page' );
        return ( $page && 'publish' === $page->post_status ) ? $page : null;
    }

    /**
     * The rewrite rule for the review slug is registered at the top of the
     * stack, so it would shadow a real page with the same slug. When such a
     * page exists, hand the request to it instead: WordPress then renders the
     * page like any other, with the theme's template, the owner's title and
     * the SEO plugin's meta. Without a page, the built-in route (noindex)
     * serves as before.
     *
     * @param array $vars Parsed query vars.
     * @return array
     */
    public function prefer_real_page( $vars ) {
        if ( empty( $vars['rtg_tire_review'] ) ) {
            return $vars;
        }
        $page = self::real_page();
        if ( ! $page ) {
            return $vars;
        }
        unset( $vars['rtg_tire_review'] );
        $vars['pagename'] = self::slug();
        return $vars;
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
        return RTG_Theme_Render::ad_blocklist_markup() . ob_get_clean();
    }
}
