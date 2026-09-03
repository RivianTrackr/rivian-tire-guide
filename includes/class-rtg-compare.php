<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tire comparison page.
 *
 * Renders inside the active theme at /{compare_slug}/ (via RTG_Theme_Render),
 * and is also available as the [rivian_tire_compare] shortcode for placing on
 * any page. The comparison itself is built client-side by compare.js from the
 * ?compare= tire indices. Marked noindex — it's a utility view.
 */
class RTG_Compare {

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render' ) );
        add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
        add_shortcode( 'rivian_tire_compare', array( $this, 'shortcode' ) );
    }

    public function register_rewrite() {
        $settings = get_option( 'rtg_settings', array() );
        $slug     = sanitize_title( $settings['compare_slug'] ?? 'tire-compare' );
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

    /**
     * Enqueue + localize the compare assets. Shared by the route and shortcode.
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
            'rtg-compare',
            RTG_PLUGIN_URL . 'frontend/js/compare' . $suffix . '.js',
            array( 'rtg-shared' ),
            RTG_VERSION,
            true
        );
        $settings = get_option( 'rtg_settings', array() );
        wp_localize_script( 'rtg-compare', 'rtgData', array(
            'tires'    => RTG_Database::get_tires_as_array(),
            'settings' => array(
                'tirePageUrl'     => home_url( '/' . RTG_Tire_Page::slug_base() . '/' ),
                'guideUrl'        => RTG_Tire_Page::guide_url(),
                'compareUrl'      => home_url( '/' . sanitize_title( $settings['compare_slug'] ?? 'tire-compare' ) . '/' ),
                'vehicleSizeMap'  => RTG_Database::get_vehicle_size_map(),
                'loadIndexFloors' => RTG_Fitment::floors(),
                'stalePriceDays'  => RTG_Stale_Prices::stale_days(),
                // The "in plain words" paragraph; empty when the advisor has no key.
                'compareSummaryRest' => RTG_Advisor::is_live() ? RTG_Advisor::compare_summary_url() : '',
            ),
        ) );
    }

    /**
     * Render the compare page inside the theme at its own URL.
     */
    public function maybe_render() {
        if ( ! get_query_var( 'rtg_compare' ) ) {
            return;
        }

        self::enqueue_assets();

        RTG_Theme_Render::render( array(
            'title'   => 'Tire Comparison — Rivian Tire Guide',
            'slug'    => 'tire-compare',
            'noindex' => true,
            'content' => function () {
                include RTG_PLUGIN_DIR . 'frontend/templates/compare.php';
            },
        ) );
    }

    /**
     * [rivian_tire_compare] — render the compare UI inside any page.
     */
    public function shortcode( $atts ) {
        self::enqueue_assets();
        ob_start();
        include RTG_PLUGIN_DIR . 'frontend/templates/compare.php';
        return ob_get_clean();
    }
}
