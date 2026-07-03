<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Individual, crawlable tire pages at /{slug}/{tire-slug}/.
 *
 * Renders INSIDE the active theme (header/nav/footer) via RTG_Theme_Render,
 * with tire content server-rendered into the_content for SEO. Per-tire
 * title/description/canonical are delegated to the site's SEO plugin
 * (All in One SEO) via filters; Product + BreadcrumbList JSON-LD is emitted
 * on wp_head; and every tire URL is registered into the AIOSEO sitemap.
 *
 * Legacy raw-tire_id URLs (/{slug}/{tire_id}/) 301 to the canonical slug URL.
 */
class RTG_Tire_Page {

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render' ) );

        // Register tire URLs into the All in One SEO XML sitemap.
        add_filter( 'aioseo_sitemap_additional_pages', array( $this, 'aioseo_sitemap_pages' ) );
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
     * Resolve the request to a tire and render it in-theme, or 404.
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

        $brand   = $tire['brand'] ?? '';
        $model   = $tire['model'] ?? '';
        $size    = $tire['size'] ?? '';
        $heading = trim( "$brand $model" ) ?: 'Tire';
        if ( $size ) {
            $heading .= " ($size)";
        }

        $canonical   = self::tire_url( $tire['slug'] ?? $tire['tire_id'] );
        $description = RTG_Meta::build_description( $tire );

        // Make the resolved tire available to the content partial + head callback.
        $GLOBALS['rtg_tire_page_tire'] = $tire;

        RTG_Theme_Render::render( array(
            'title'       => $heading . ' — Rivian Tire Guide',
            'slug'        => $tire['slug'] ?? $tire['tire_id'],
            'canonical'   => $canonical,
            'description' => $description,
            'content'     => function () {
                include RTG_PLUGIN_DIR . 'frontend/templates/tire-page-content.php';
            },
            'head'        => array( $this, 'output_structured_data' ),
        ) );
    }

    /**
     * Emit Product + BreadcrumbList JSON-LD for the current tire (on wp_head).
     */
    public function output_structured_data() {
        $tire = $GLOBALS['rtg_tire_page_tire'] ?? null;
        if ( ! is_array( $tire ) ) {
            return;
        }

        $brand   = $tire['brand'] ?? '';
        $model   = $tire['model'] ?? '';
        $size    = $tire['size'] ?? '';
        $heading = trim( "$brand $model" ) . ( $size ? " ($size)" : '' );
        $category = $tire['category'] ?? '';
        $canonical = self::tire_url( $tire['slug'] ?? $tire['tire_id'] );

        $product = RTG_Schema::build_single_product( $tire );

        // Breadcrumb: Home -> Tire Guide -> [Category] -> Tire.
        $crumbs = array(
            array( 'name' => 'Home', 'url' => home_url( '/' ) ),
            array( 'name' => 'Tire Guide', 'url' => self::guide_url() ),
        );
        if ( $category ) {
            $crumbs[] = array( 'name' => $category, 'url' => add_query_arg( 'category', rawurlencode( $category ), self::guide_url() ) );
        }
        $crumbs[] = array( 'name' => $heading, 'url' => $canonical );

        $breadcrumb = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => array(),
        );
        foreach ( $crumbs as $i => $c ) {
            $breadcrumb['itemListElement'][] = array(
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $c['name'],
                'item'     => esc_url_raw( $c['url'] ),
            );
        }

        echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $product, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
        echo '<script type="application/ld+json">' . wp_json_encode( $breadcrumb, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }

    /**
     * Add every tire's canonical URL to the All in One SEO XML sitemap.
     *
     * @param array $pages Existing additional pages.
     * @return array
     */
    public function aioseo_sitemap_pages( $pages ) {
        if ( ! is_array( $pages ) ) {
            $pages = array();
        }

        foreach ( RTG_Database::get_all_tires() as $tire ) {
            $slug = $tire['slug'] ?? '';
            if ( ! $slug ) {
                continue;
            }
            $pages[] = array(
                'loc'        => self::tire_url( $slug ),
                'lastmod'    => ! empty( $tire['updated_at'] ) ? gmdate( 'c', strtotime( $tire['updated_at'] ) ) : gmdate( 'c' ),
                'changefreq' => 'weekly',
                'priority'   => 0.7,
            );
        }

        return $pages;
    }

    /**
     * URL of the page hosting the [rivian_tire_guide] shortcode (for breadcrumbs).
     */
    public static function guide_url() {
        $guide_pages = get_posts( array(
            'post_type'   => 'page',
            'post_status' => 'publish',
            's'           => '[rivian_tire_guide]',
            'numberposts' => 1,
            'fields'      => 'ids',
        ) );
        return ! empty( $guide_pages ) ? get_permalink( $guide_pages[0] ) : home_url( '/' );
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
