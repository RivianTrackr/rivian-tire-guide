<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders a plugin "page" INSIDE the active theme via a virtual (fake) post,
 * instead of a standalone HTML document that bypasses the theme.
 *
 * Theme-agnostic: it sets the main query up as a singular page so the theme's
 * normal page template renders header + the_content + footer — works for both
 * classic (page.php) and block/FSE themes (which render the content block).
 *
 * SEO for these virtual URLs is delegated to All in One SEO (or any SEO plugin)
 * through its filters, with core-title/canonical fallbacks when none is active.
 *
 * Call RTG_Theme_Render::render() from a template_redirect handler once the
 * request has been resolved to a known page. Only one virtual page renders per
 * request.
 */
class RTG_Theme_Render {

    /** @var array|null Active virtual-page spec for this request. */
    private static $active = null;

    /**
     * Take over the current request and render a virtual page in the theme.
     *
     * @param array $args {
     *   @type string   $title       Document + queried-object title.
     *   @type string   $slug        post_name for the fake post.
     *   @type callable $content     Echoes the body HTML (becomes the_content).
     *   @type callable $head        Optional. Echoes extra <head> markup (JSON-LD) on wp_head.
     *   @type string   $canonical   Optional canonical URL.
     *   @type string   $description Optional meta description.
     *   @type bool     $noindex     Optional. Mark robots noindex,follow (utility pages).
     *   @type string   $image       Optional. Social preview image URL (og:image / twitter:image).
     * }
     */
    public static function render( $args ) {
        self::$active = wp_parse_args( $args, array(
            'title'       => '',
            'slug'        => 'rtg',
            'content'     => null,
            'head'        => null,
            'canonical'   => '',
            'description' => '',
            'noindex'     => false,
            'image'       => '',
        ) );

        global $wp_query;

        $post = new WP_Post( (object) array(
            'ID'             => 0,
            'post_author'    => 0,
            'post_date'      => current_time( 'mysql' ),
            'post_date_gmt'  => current_time( 'mysql', true ),
            'post_title'     => self::$active['title'],
            'post_name'      => sanitize_title( self::$active['slug'] ),
            'post_content'   => '',
            'post_excerpt'   => self::$active['description'],
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
            'filter'         => 'raw',
        ) );

        // Present the request as a normal singular page so the theme renders it.
        $wp_query->post              = $post;
        $wp_query->posts             = array( $post );
        $wp_query->queried_object    = $post;
        $wp_query->queried_object_id = 0;
        $wp_query->post_count        = 1;
        $wp_query->current_post      = -1;
        $wp_query->found_posts       = 1;
        $wp_query->max_num_pages     = 1;
        $wp_query->is_page           = true;
        $wp_query->is_singular       = true;
        $wp_query->is_single         = false;
        $wp_query->is_archive        = false;
        $wp_query->is_home           = false;
        $wp_query->is_front_page     = false;
        $wp_query->is_404            = false;

        status_header( 200 );

        // Blocksy renders a (visually hidden) hero <h1 class="page-title">
        // from the virtual post's title — a duplicate h1 on SEO pages.
        // Returning false (non-null) from blocksy:hero:custom-source makes
        // blocksy_get_page_title_source() falsy, which suppresses the hero
        // section entirely for this request. No-op on non-Blocksy themes.
        add_filter( 'blocksy:hero:custom-source', '__return_false' );

        // Our content is pre-built HTML; stop wpautop from injecting stray tags.
        remove_filter( 'the_content', 'wpautop' );
        add_filter( 'the_content', array( __CLASS__, 'filter_the_content' ), 9 );

        // Themes print the page title themselves above the_content, but our
        // content partials render their own <h1> — blank the virtual post's
        // DISPLAY title so it isn't shown twice. The document <title> and
        // AIOSEO tags are unaffected (handled by the filters below).
        add_filter( 'the_title', array( __CLASS__, 'filter_the_title' ), 20, 2 );

        // Title / description / canonical / robots via core + AIOSEO filters.
        add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 20 );
        add_filter( 'document_title_parts', array( __CLASS__, 'filter_document_title_parts' ), 20 );
        add_filter( 'aioseo_title', array( __CLASS__, 'filter_document_title' ), 20 );
        add_filter( 'aioseo_description', array( __CLASS__, 'filter_meta_description' ), 20 );
        add_filter( 'aioseo_canonical_url', array( __CLASS__, 'filter_canonical' ), 20 );

        if ( self::$active['noindex'] ) {
            add_filter( 'wp_robots', array( __CLASS__, 'filter_robots_noindex' ), 20 );
            add_filter( 'aioseo_robots_meta', array( __CLASS__, 'filter_aioseo_robots_noindex' ), 20 );
        }

        // The social image. A virtual post has no featured image, so every
        // SEO plugin would fall back to its site default or nothing; hand each
        // the page's image through its own filter, and print the tags
        // ourselves when no plugin is present.
        if ( self::$active['image'] ) {
            add_filter( 'aioseo_facebook_tags', array( __CLASS__, 'filter_aioseo_facebook_tags' ), 20 );
            add_filter( 'aioseo_twitter_tags', array( __CLASS__, 'filter_aioseo_twitter_tags' ), 20 );
            add_filter( 'wpseo_opengraph_image', array( __CLASS__, 'filter_image' ), 20 );
            add_filter( 'wpseo_twitter_image', array( __CLASS__, 'filter_image' ), 20 );
            add_filter( 'rank_math/opengraph/facebook/image', array( __CLASS__, 'filter_image' ), 20 );
            add_filter( 'rank_math/opengraph/twitter/image', array( __CLASS__, 'filter_image' ), 20 );
        }

        add_action( 'wp_head', array( __CLASS__, 'output_head' ), 1 );
    }

    /**
     * Whether a virtual page is active for this request.
     */
    public static function is_active() {
        return null !== self::$active;
    }

    public static function filter_the_content( $content ) {
        if ( ! self::$active || ! is_callable( self::$active['content'] ) ) {
            return $content;
        }
        ob_start();
        call_user_func( self::$active['content'] );
        return self::ad_blocklist_markup() . ob_get_clean();
    }

    /**
     * Mediavine's per-page opt-out: a settings element the ad script reads
     * on load and, with data-blocklist-all, serves nothing on the page.
     * Printed at the top of every tire-related page (the guide, tire pages,
     * compare, the review page, the changelog) so the reading experience
     * is not broken up by ads. Filter `rtg_block_ads` to false to keep ads.
     *
     * @return string Markup, or '' when ads are allowed.
     */
    public static function ad_blocklist_markup() {
        if ( ! apply_filters( 'rtg_block_ads', true ) ) {
            return '';
        }
        return '<div id="mediavine-settings" data-blocklist-all="1"></div>';
    }

    /**
     * Suppress the theme-rendered page title for the virtual post (ID 0).
     * Real posts (nav menu items, widgets, other loops) have non-zero IDs
     * and pass through untouched.
     */
    public static function filter_the_title( $title, $post_id = null ) {
        if ( self::$active && 0 === (int) $post_id ) {
            return '';
        }
        return $title;
    }

    public static function filter_document_title( $title ) {
        return self::$active && self::$active['title'] ? self::$active['title'] : $title;
    }

    public static function filter_document_title_parts( $parts ) {
        if ( self::$active && self::$active['title'] ) {
            $parts['title'] = self::$active['title'];
        }
        return $parts;
    }

    public static function filter_meta_description( $desc ) {
        return self::$active && self::$active['description'] ? self::$active['description'] : $desc;
    }

    public static function filter_canonical( $url ) {
        return self::$active && self::$active['canonical'] ? self::$active['canonical'] : $url;
    }

    public static function filter_image( $url ) {
        return self::$active && self::$active['image'] ? self::$active['image'] : $url;
    }

    /** AIOSEO hands over its Open Graph tags as one array keyed by property. */
    public static function filter_aioseo_facebook_tags( $tags ) {
        if ( ! self::$active || ! self::$active['image'] || ! is_array( $tags ) ) {
            return $tags;
        }
        $tags['og:image']            = self::$active['image'];
        $tags['og:image:secure_url'] = self::$active['image'];
        unset( $tags['og:image:width'], $tags['og:image:height'] );
        return $tags;
    }

    public static function filter_aioseo_twitter_tags( $tags ) {
        if ( ! self::$active || ! self::$active['image'] || ! is_array( $tags ) ) {
            return $tags;
        }
        $tags['twitter:image'] = self::$active['image'];
        $tags['twitter:card']  = 'summary_large_image';
        return $tags;
    }

    public static function filter_robots_noindex( $robots ) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
        return $robots;
    }

    public static function filter_aioseo_robots_noindex( $meta ) {
        return 'noindex,follow';
    }

    /**
     * Emit canonical/description fallbacks (only when no SEO plugin is present)
     * and any page-specific <head> markup (JSON-LD).
     */
    public static function output_head() {
        if ( ! self::$active ) {
            return;
        }

        if ( ! self::seo_plugin_active() ) {
            if ( self::$active['canonical'] ) {
                echo '<link rel="canonical" href="' . esc_url( self::$active['canonical'] ) . '" />' . "\n";
            }
            if ( self::$active['description'] ) {
                echo '<meta name="description" content="' . esc_attr( self::$active['description'] ) . '" />' . "\n";
            }
            if ( self::$active['noindex'] ) {
                echo '<meta name="robots" content="noindex,follow" />' . "\n";
            }
            if ( self::$active['image'] ) {
                echo '<meta property="og:title" content="' . esc_attr( self::$active['title'] ) . '" />' . "\n";
                if ( self::$active['description'] ) {
                    echo '<meta property="og:description" content="' . esc_attr( self::$active['description'] ) . '" />' . "\n";
                }
                if ( self::$active['canonical'] ) {
                    echo '<meta property="og:url" content="' . esc_url( self::$active['canonical'] ) . '" />' . "\n";
                }
                echo '<meta property="og:image" content="' . esc_url( self::$active['image'] ) . '" />' . "\n";
                echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
                echo '<meta name="twitter:image" content="' . esc_url( self::$active['image'] ) . '" />' . "\n";
            }
        }

        if ( is_callable( self::$active['head'] ) ) {
            call_user_func( self::$active['head'] );
        }
    }

    /**
     * Detect a known SEO plugin so we don't duplicate canonical/description tags.
     */
    private static function seo_plugin_active() {
        return function_exists( 'aioseo' )        // All in One SEO
            || defined( 'AIOSEO_VERSION' )
            || defined( 'WPSEO_VERSION' )         // Yoast
            || class_exists( 'RankMath' );        // Rank Math
    }
}
