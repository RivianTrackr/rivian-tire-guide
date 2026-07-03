<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Individual, crawlable tire pages at /{slug}/{tire-slug}/.
 *
 * Mirrors the standalone-page pattern used by RTG_Tire_Review: registers a
 * rewrite rule, resolves the path segment to a tire, and renders a
 * server-side template (tire content lives in the initial HTML for SEO).
 *
 * Legacy raw-tire_id URLs (/{slug}/{tire_id}/) 301 to the canonical slug URL.
 * The one-shot rewrite flush is handled by RTG_Tire_Review::maybe_flush_rewrites
 * (both classes share the rtg_flush_rewrite option); migration 17 sets that flag
 * on upgrade so the new rule registers.
 */
class RTG_Tire_Page {

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render' ) );
    }

    /**
     * The URL base segment for tire pages (default "tires", configurable).
     *
     * @return string Sanitized slug base.
     */
    public static function slug_base() {
        $settings = get_option( 'rtg_settings', array() );
        $slug     = $settings['tire_page_slug'] ?? 'tires';
        $slug     = sanitize_title( $slug );
        return $slug ? $slug : 'tires';
    }

    /**
     * Canonical URL for a tire slug.
     *
     * @param string $slug Tire slug.
     * @return string Absolute URL.
     */
    public static function tire_url( $slug ) {
        return home_url( '/' . self::slug_base() . '/' . rawurlencode( $slug ) . '/' );
    }

    public function register_rewrite() {
        $base = self::slug_base();
        add_rewrite_rule( '^' . $base . '/([^/]+)/?$', 'index.php?rtg_tire=$matches[1]', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'rtg_tire';
        return $vars;
    }

    /**
     * Resolve the request to a tire and render it, or 404.
     */
    public function maybe_render() {
        $raw = get_query_var( 'rtg_tire' );
        if ( '' === $raw || null === $raw ) {
            return;
        }

        $key  = sanitize_title( wp_unslash( $raw ) );
        $tire = $key ? RTG_Database::get_tire_by_slug( $key ) : null;

        // Fall back to a raw tire_id lookup, then 301 to the canonical slug URL.
        if ( ! $tire && RTG_Database::validate_tire_id( $raw ) ) {
            $by_id = RTG_Database::get_tire( $raw );
            if ( $by_id ) {
                $slug = ! empty( $by_id['slug'] ) ? $by_id['slug'] : RTG_Database::sync_tire_slug( $by_id['tire_id'] );
                if ( $slug ) {
                    wp_safe_redirect( self::tire_url( $slug ), 301 );
                    exit;
                }
                $tire = $by_id;
            }
        }

        if ( ! $tire ) {
            $this->render_404();
            return;
        }

        // Security headers (match the standalone review page).
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );

        $GLOBALS['rtg_tire_page_tire'] = $tire;
        include RTG_PLUGIN_DIR . 'frontend/templates/tire-page.php';
        exit;
    }

    /**
     * Emit a proper 404 and let the theme render its 404 template.
     */
    private function render_404() {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }
}
