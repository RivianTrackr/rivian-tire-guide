<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Frontend {

    private $shortcode_present = false;

    public function __construct() {
        add_shortcode( 'rivian_tire_guide', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 20 );
    }

    public function render_shortcode( $atts ) {
        $this->shortcode_present = true;

        // Enqueue assets now that we know the shortcode is on the page.
        $this->enqueue_assets();

        ob_start();
        include RTG_PLUGIN_DIR . 'frontend/templates/tire-guide.php';
        return ob_get_clean();
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

    private function enqueue_assets() {
        if ( wp_script_is( 'rtg-tire-guide', 'enqueued' ) ) {
            return;
        }

        $settings = get_option( 'rtg_settings', array() );
        $compare_slug = $settings['compare_slug'] ?? 'tire-compare';

        // CSS.
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
            array(),
            '6.5.0'
        );

        $suffix = self::asset_suffix();

        wp_enqueue_style(
            'rtg-styles',
            RTG_PLUGIN_URL . 'frontend/css/rivian-tires' . $suffix . '.css',
            array(),
            RTG_VERSION
        );

        // Theme color overrides — output as inline CSS custom properties.
        $theme_colors = $settings['theme_colors'] ?? array();
        if ( ! empty( $theme_colors ) ) {
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

        // JS — no PapaParse needed.
        wp_enqueue_script(
            'rtg-tire-guide',
            RTG_PLUGIN_URL . 'frontend/js/rivian-tires' . $suffix . '.js',
            array(),
            RTG_VERSION,
            true
        );

        $server_side = ! empty( $settings['server_side_pagination'] );

        // Localize tire data.
        $localized = array(
            'settings' => array(
                'rowsPerPage'  => intval( $settings['rows_per_page'] ?? 12 ),
                'compareUrl'   => home_url( '/' . $compare_slug . '/' ),
                'serverSide'   => $server_side,
                'ajaxurl'      => admin_url( 'admin-ajax.php' ),
                'tireNonce'    => wp_create_nonce( 'rtg_tire_nonce' ),
            ),
        );

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
            'timezone'     => wp_timezone_string(),
        ) );
    }
}
