<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Frontend {

    private $shortcode_present = false;

    public function __construct() {
        add_shortcode( 'rivian_tire_guide', array( $this, 'render_shortcode' ) );
        add_shortcode( 'rivian_user_reviews', array( $this, 'render_user_reviews_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 20 );
    }

    public function render_shortcode( $atts ) {
        $this->shortcode_present = true;

        // Parse shortcode attributes for pre-filtering.
        $atts = shortcode_atts( array(
            'size'     => '',
            'brand'    => '',
            'category' => '',
            'sort'     => '',
            '3pms'     => '',
        ), $atts, 'rivian_tire_guide' );

        // Enqueue assets now that we know the shortcode is on the page.
        $this->enqueue_assets( $atts );

        ob_start();
        include RTG_PLUGIN_DIR . 'frontend/templates/tire-guide.php';
        return ob_get_clean();
    }

    public function render_user_reviews_shortcode( $atts ) {
        $this->enqueue_user_reviews_assets();

        ob_start();
        include RTG_PLUGIN_DIR . 'frontend/templates/user-reviews.php';
        return ob_get_clean();
    }

    private function enqueue_user_reviews_assets() {
        $suffix = self::asset_suffix();

        wp_enqueue_style(
            'rtg-styles',
            RTG_PLUGIN_URL . 'frontend/css/rivian-tires' . $suffix . '.css',
            array(),
            RTG_VERSION
        );

        wp_enqueue_script(
            'rtg-user-reviews',
            RTG_PLUGIN_URL . 'frontend/js/user-reviews.js',
            array(),
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

        wp_localize_script( 'rtg-user-reviews', 'rtgUserReviews', array(
            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'tire_rating_nonce' ),
            'tireGuideUrl' => $tire_guide_url,
        ) );

        $this->inject_theme_color_overrides();
    }

    public function maybe_enqueue_assets() {
        // Early detection via has_shortcode for pages that cache.
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'rivian_tire_guide' ) ) {
            $this->enqueue_assets();
        }
    }

    /**
     * Return '.min' when minified assets exist and SCRIPT_DEBUG is off.
     */
    private static function asset_suffix() {
        if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
            return '';
        }
        if ( file_exists( RTG_PLUGIN_DIR . 'frontend/js/rivian-tires.min.js' ) ) {
            return '.min';
        }
        return '';
    }

    private function enqueue_assets( $shortcode_atts = array() ) {
        if ( wp_script_is( 'rtg-tire-guide', 'enqueued' ) ) {
            return;
        }

        $settings = get_option( 'rtg_settings', array() );
        $compare_slug = $settings['compare_slug'] ?? 'tire-compare';

        $suffix = self::asset_suffix();

        wp_enqueue_style(
            'rtg-styles',
            RTG_PLUGIN_URL . 'frontend/css/rivian-tires' . $suffix . '.css',
            array(),
            RTG_VERSION
        );

        $this->inject_theme_color_overrides();

        // JS — no PapaParse needed.
        wp_enqueue_script(
            'rtg-tire-guide',
            RTG_PLUGIN_URL . 'frontend/js/rivian-tires' . $suffix . '.js',
            array(),
            RTG_VERSION,
            true
        );

        $server_side        = ! empty( $settings['server_side_pagination'] );
        $user_reviews_slug  = $settings['user_reviews_slug'] ?? 'user-reviews';

        // Build shortcode pre-filter overrides.
        $prefilters = array();
        if ( ! empty( $shortcode_atts['size'] ) ) {
            $prefilters['size'] = sanitize_text_field( $shortcode_atts['size'] );
        }
        if ( ! empty( $shortcode_atts['brand'] ) ) {
            $prefilters['brand'] = sanitize_text_field( $shortcode_atts['brand'] );
        }
        if ( ! empty( $shortcode_atts['category'] ) ) {
            $prefilters['category'] = sanitize_text_field( $shortcode_atts['category'] );
        }
        if ( ! empty( $shortcode_atts['sort'] ) ) {
            $allowed_sorts = array( 'efficiencyGrade', 'price-asc', 'price-desc', 'warranty-desc', 'weight-asc', 'newest', 'rating-desc', 'most-reviewed' );
            $sort_val = sanitize_text_field( $shortcode_atts['sort'] );
            if ( in_array( $sort_val, $allowed_sorts, true ) ) {
                $prefilters['sort'] = $sort_val;
            }
        }
        if ( strtolower( $shortcode_atts['3pms'] ?? '' ) === 'yes' ) {
            $prefilters['three_pms'] = true;
        }

        // Localize tire data.
        $localized = array(
            'settings' => array(
                'rowsPerPage'     => intval( $settings['rows_per_page'] ?? 12 ),
                'compareUrl'      => home_url( '/' . $compare_slug . '/' ),
                'userReviewsUrl'  => home_url( '/' . sanitize_title( $user_reviews_slug ) . '/' ),
                'serverSide'      => $server_side,
                'ajaxurl'         => admin_url( 'admin-ajax.php' ),
                'tireNonce'       => wp_create_nonce( 'rtg_tire_nonce' ),
                'analyticsNonce'  => wp_create_nonce( 'rtg_analytics_nonce' ),
            ),
        );

        if ( ! empty( $prefilters ) ) {
            $localized['settings']['prefilters'] = $prefilters;
        }

        // Only embed full tire array when client-side mode is active.
        if ( ! $server_side ) {
            $localized['tires'] = RTG_Database::get_tires_as_array();
        }

        wp_localize_script( 'rtg-tire-guide', 'rtgData', $localized );

        // Rating system localization.
        wp_localize_script( 'rtg-tire-guide', 'tireRatingAjax', array(
            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'tire_rating_nonce' ),
            'is_logged_in' => is_user_logged_in(),
            'login_url'    => wp_login_url( get_permalink() ),
            'register_url' => wp_registration_url(),
            'timezone'     => wp_timezone_string(),
        ) );
    }

    private function inject_theme_color_overrides() {
        $settings     = get_option( 'rtg_settings', array() );
        $theme_colors = $settings['theme_colors'] ?? array();

        if ( empty( $theme_colors ) ) {
            return;
        }

        $var_map = array(
            'accent'       => '--rtg-accent',
            'accent_hover' => '--rtg-accent-hover',
            'bg_primary'   => '--rtg-bg-primary',
            'bg_card'      => '--rtg-bg-card',
            'bg_input'     => '--rtg-bg-input',
            'bg_deep'      => '--rtg-bg-deep',
            'text_primary' => '--rtg-text-primary',
            'text_light'   => '--rtg-text-light',
            'text_muted'   => '--rtg-text-muted',
            'text_heading' => '--rtg-text-heading',
            'border'       => '--rtg-border',
            'star_filled'  => '--rtg-star-filled',
            'star_user'    => '--rtg-star-user',
            'star_empty'   => '--rtg-star-empty',
        );

        $css_vars = '';
        foreach ( $var_map as $key => $prop ) {
            if ( ! empty( $theme_colors[ $key ] ) ) {
                // Re-validate hex color at render time to prevent CSS injection.
                $color = sanitize_hex_color( $theme_colors[ $key ] );
                if ( $color ) {
                    $css_vars .= $prop . ':' . $color . ';';
                }
            }
        }

        if ( $css_vars ) {
            wp_add_inline_style( 'rtg-styles', ':root{' . $css_vars . '}' );
        }
    }
}
