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

        wp_enqueue_style(
            'rtg-styles',
            RTG_PLUGIN_URL . 'frontend/css/rivian-tires.css',
            array(),
            RTG_VERSION
        );

        // JS — no PapaParse needed.
        wp_enqueue_script(
            'rtg-tire-guide',
            RTG_PLUGIN_URL . 'frontend/js/rivian-tires.js',
            array(),
            RTG_VERSION,
            true
        );

        // Localize tire data.
        wp_localize_script( 'rtg-tire-guide', 'rtgData', array(
            'tires'    => RTG_Database::get_tires_as_array(),
            'settings' => array(
                'rowsPerPage' => intval( $settings['rows_per_page'] ?? 12 ),
                'compareUrl'  => home_url( '/' . $compare_slug . '/' ),
            ),
        ) );

        // Rating system localization.
        wp_localize_script( 'rtg-tire-guide', 'tireRatingAjax', array(
            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'tire_rating_nonce' ),
            'is_logged_in' => is_user_logged_in(),
            'login_url'    => wp_login_url( get_permalink() ),
        ) );
    }
}
