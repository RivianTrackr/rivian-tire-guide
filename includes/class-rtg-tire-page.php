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

    /**
     * Reviews rendered into the page, and fetched per "Show more" click.
     * The AJAX feed (RTG_Ajax::get_tire_reviews) pages by the same number.
     */
    const REVIEWS_PER_PAGE = 10;

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

        // Retired slug (tire was renamed)? 301 to the current canonical URL
        // so previously indexed/shared links keep working.
        if ( ! $tire && $key ) {
            $redirect_tire_id = RTG_Database::lookup_slug_redirect( $key );
            if ( $redirect_tire_id ) {
                $target = RTG_Database::get_tire( $redirect_tire_id );
                if ( $target && ! empty( $target['slug'] ) && $target['slug'] !== $key ) {
                    wp_safe_redirect( self::tire_url( $target['slug'] ), 301 );
                    exit;
                }
            }
        }

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

        // Affiliate click tracking for the "View Tire" CTA — same analytics
        // endpoint the guide cards report to.
        $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : (
            file_exists( RTG_PLUGIN_DIR . 'frontend/js/tire-page.min.js' ) ? '.min' : ''
        );
        wp_enqueue_script(
            'rtg-tire-page',
            RTG_PLUGIN_URL . 'frontend/js/tire-page' . $suffix . '.js',
            array(),
            RTG_VERSION,
            true
        );
        $settings     = get_option( 'rtg_settings', array() );
        $review_count = RTG_Database::get_tire_review_count( $tire['tire_id'] );
        $is_favorite  = is_user_logged_in()
            && in_array( $tire['tire_id'], (array) RTG_Database::get_user_favorites( get_current_user_id() ), true );

        wp_localize_script( 'rtg-tire-page', 'rtgTirePage', array(
            'ajaxurl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'rtg_analytics_nonce' ),
            'tireId'         => $tire['tire_id'],
            // Favorites and the reviews feed share the rating nonce the
            // guide uses for the same endpoints.
            'ratingNonce'    => wp_create_nonce( 'tire_rating_nonce' ),
            'isLoggedIn'     => is_user_logged_in(),
            'loginUrl'       => wp_login_url( $canonical ),
            'isFavorite'     => $is_favorite,
            'shareUrl'       => $canonical,
            'shareTitle'     => $heading,
            'reviewTotal'    => $review_count,
            'reviewsPerPage' => self::REVIEWS_PER_PAGE,
            'compareUrl'     => add_query_arg(
                'compare',
                rawurlencode( $tire['tire_id'] ),
                home_url( '/' . sanitize_title( $settings['compare_slug'] ?? 'tire-compare' ) . '/' )
            ),
        ) );

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

        // VideoObject for YouTube review videos — rich-result eligibility.
        $yt_id = self::youtube_id_from_url( $tire['review_link'] ?? '' );
        if ( $yt_id ) {
            $video = array(
                '@context'     => 'https://schema.org',
                '@type'        => 'VideoObject',
                'name'         => $heading . ' — Official Review',
                'description'  => 'Video review of the ' . $heading . ' tire for Rivian vehicles.',
                'thumbnailUrl' => 'https://i.ytimg.com/vi/' . $yt_id . '/hqdefault.jpg',
                'contentUrl'   => esc_url_raw( $tire['review_link'] ),
                'embedUrl'     => 'https://www.youtube.com/embed/' . $yt_id,
                // Approximation: the video's true publish date isn't stored;
                // the tire's created_at is the closest known date. uploadDate
                // is required for video rich results, so we use it rather
                // than omit the video entirely.
                'uploadDate'   => gmdate( 'c', strtotime( $tire['created_at'] ?? 'now' ) ),
            );
            echo '<script type="application/ld+json">' . wp_json_encode( $video, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
        }
    }

    /**
     * Extract a YouTube video ID from a watch/short URL, or '' when the URL
     * isn't a recognizable YouTube video link.
     *
     * @param string $url Review link URL.
     * @return string Video ID or ''.
     */
    public static function youtube_id_from_url( $url ) {
        if ( ! is_string( $url ) || '' === $url ) {
            return '';
        }

        $host = wp_parse_url( $url, PHP_URL_HOST );
        $host = is_string( $host ) ? strtolower( $host ) : '';

        if ( 'youtu.be' === $host ) {
            $path = (string) wp_parse_url( $url, PHP_URL_PATH );
            $id   = trim( $path, '/' );
        } elseif ( 'youtube.com' === $host || substr( $host, -12 ) === '.youtube.com' ) {
            $query = (string) wp_parse_url( $url, PHP_URL_QUERY );
            parse_str( $query, $params );
            $id = $params['v'] ?? '';
        } else {
            return '';
        }

        return preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id ) ? $id : '';
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
