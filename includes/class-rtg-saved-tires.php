<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * "Saved tires" page.
 *
 * A member's shortlist: every tire they tapped the heart on, with its price
 * today against the price the day they saved it, its efficiency and rating,
 * the retailer button, and a way to compare the lot. Renders inside the
 * active theme at /{saved_tires_slug}/ (via RTG_Theme_Render) and is also
 * available as the [rivian_saved_tires] shortcode, the way the review page
 * works. Members only: a signed-out visitor gets a sign-in prompt. Marked
 * noindex, since what it shows is one person's list.
 */
class RTG_Saved_Tires {

    const QUERY_VAR = 'rtg_saved_tires';

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render' ) );
        // A real page at the slug wins over the built-in route, so the owner
        // can hold the page in WordPress (and put it in a menu like any page).
        add_filter( 'request', array( $this, 'prefer_real_page' ) );
        add_shortcode( 'rivian_saved_tires', array( $this, 'shortcode' ) );
    }

    /**
     * The slug from settings, `saved-tires` by default.
     */
    public static function slug() {
        $settings = get_option( 'rtg_settings', array() );
        $slug     = sanitize_title( $settings['saved_tires_slug'] ?? 'saved-tires' );
        return $slug ? $slug : 'saved-tires';
    }

    /**
     * Where the page lives: what the heart's toast, the "Saved" chip and a
     * site menu link to.
     */
    public static function url() {
        return home_url( '/' . self::slug() . '/' );
    }

    public function register_rewrite() {
        add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * A published WordPress page living at the slug, if the owner made one
     * (with the [rivian_saved_tires] shortcode on it), or null.
     */
    public static function real_page() {
        $page = get_page_by_path( self::slug(), OBJECT, 'page' );
        return ( $page && 'publish' === $page->post_status ) ? $page : null;
    }

    public function prefer_real_page( $vars ) {
        if ( empty( $vars[ self::QUERY_VAR ] ) ) {
            return $vars;
        }
        $page = self::real_page();
        if ( ! $page ) {
            return $vars;
        }
        unset( $vars[ self::QUERY_VAR ] );
        $vars['pagename'] = self::slug();
        return $vars;
    }

    /**
     * Enqueue + localize the page's assets. Shared by the route and shortcode.
     */
    private static function enqueue_assets() {
        $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : (
            file_exists( RTG_PLUGIN_DIR . 'frontend/css/saved-tires.min.css' ) ? '.min' : ''
        );

        wp_enqueue_style(
            'rtg-saved-tires',
            RTG_PLUGIN_URL . 'frontend/css/saved-tires' . $suffix . '.css',
            array(),
            RTG_VERSION
        );
        wp_enqueue_script(
            'rtg-saved-tires',
            RTG_PLUGIN_URL . 'frontend/js/saved-tires' . $suffix . '.js',
            array(),
            RTG_VERSION,
            true
        );

        $settings = get_option( 'rtg_settings', array() );

        wp_localize_script( 'rtg-saved-tires', 'rtgSavedTires', array(
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'tire_rating_nonce' ),
            'isLoggedIn'    => is_user_logged_in(),
            // Back to this page after signing in, not the home page.
            'loginUrl'      => wp_login_url( self::url() ),
            'tireGuideUrl'  => RTG_Tire_Page::guide_url(),
            'tirePageBase'  => home_url( '/' . RTG_Tire_Page::slug_base() . '/' ),
            'compareUrl'    => home_url( '/' . sanitize_title( $settings['compare_slug'] ?? 'tire-compare' ) . '/' ),
            'stalePriceDays' => RTG_Stale_Prices::stale_days(),
        ) );
    }

    /**
     * Render the page inside the theme at its own URL.
     */
    public function maybe_render() {
        if ( ! get_query_var( self::QUERY_VAR ) ) {
            return;
        }

        self::enqueue_assets();

        RTG_Theme_Render::render( array(
            'title'   => 'Saved Tires — Rivian Tire Guide',
            'slug'    => self::slug(),
            'noindex' => true,
            'content' => function () {
                include RTG_PLUGIN_DIR . 'frontend/templates/saved-tires.php';
            },
        ) );
    }

    /**
     * [rivian_saved_tires] — render the page inside any WordPress page.
     */
    public function shortcode( $atts ) {
        self::enqueue_assets();
        ob_start();
        include RTG_PLUGIN_DIR . 'frontend/templates/saved-tires.php';
        return RTG_Theme_Render::ad_blocklist_markup() . ob_get_clean();
    }
}
