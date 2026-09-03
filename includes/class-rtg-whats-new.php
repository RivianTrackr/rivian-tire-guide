<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * "What's new": the owner-facing release notes.
 *
 * CHANGELOG.md is the developer record and reads like one. WHATS-NEW.md at
 * the plugin root is written for the people who use the guide: one heading
 * per release, an optional intro paragraph, then bullets that open with a
 * bold lead sentence. This class parses that file (cached until the file or
 * the plugin version changes), renders it inside the theme at
 * /tire-guide/whats-new/, and serves it as JSON for the guide's "What's new"
 * modal. Nothing lives in the database; the notes ship with the plugin.
 */
class RTG_Whats_New {

    /** The notes file, relative to the plugin root. */
    const FILE = 'WHATS-NEW.md';

    /** URL path of the page, under the site root. */
    const PATH = 'tire-guide/whats-new';

    const QUERY_VAR = 'rtg_whats_new';
    const TRANSIENT = 'rtg_whats_new';
    const REST_ROUTE = '/whats-new';

    public function __construct() {
        add_action( 'init', array( $this, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
    }

    public function register_rewrite() {
        add_rewrite_rule( '^' . self::PATH . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /** Public URL of the page. */
    public static function url() {
        return home_url( '/' . self::PATH . '/' );
    }

    /** URL of the JSON the guide's modal loads. */
    public static function rest_url() {
        return rest_url( RTG_REST_API::NAMESPACE . self::REST_ROUTE );
    }

    public static function file_path() {
        return RTG_PLUGIN_DIR . self::FILE;
    }

    // ------------------------------------------------------------------
    // Data
    // ------------------------------------------------------------------

    /**
     * The parsed releases, newest first. Cached in one transient whose key
     * carries the plugin version and the file's mtime, so a release or an
     * edit to the file refreshes it and nothing has to be flushed by hand.
     *
     * @return array[] See parse().
     */
    public static function get_releases() {
        $path  = self::file_path();
        $mtime = file_exists( $path ) ? (int) filemtime( $path ) : 0;
        $key   = RTG_VERSION . ':' . $mtime;

        $cached = get_transient( self::TRANSIENT );
        if ( is_array( $cached ) && ( $cached['key'] ?? '' ) === $key && isset( $cached['releases'] ) ) {
            return $cached['releases'];
        }

        $releases = $mtime ? self::parse( (string) file_get_contents( $path ) ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file that ships with the plugin
        set_transient( self::TRANSIENT, array( 'key' => $key, 'releases' => $releases ), DAY_IN_SECONDS );

        return $releases;
    }

    /** Version of the newest release in the notes; the plugin version when there are none. */
    public static function latest_version() {
        $releases = self::get_releases();
        return ! empty( $releases ) ? $releases[0]['version'] : RTG_VERSION;
    }

    /**
     * Parse the notes markdown.
     *
     * The format is deliberately small: a `## 1.92.0 - 2026-09-03` heading
     * per release (the version may be bracketed, Keep-a-Changelog style),
     * optional intro lines before the first bullet, and `- ` bullets whose
     * indented continuation lines are joined. Anything before the first
     * release heading is the file's own preamble and is skipped, as are
     * other headings.
     *
     * @param string $markdown The file's contents.
     * @return array[] Each: version, date (Y-m-d), intro (string), items
     *                 (each: lead, detail).
     */
    public static function parse( $markdown ) {
        $releases = array();
        $current  = null;

        foreach ( preg_split( '/\r\n|\r|\n/', (string) $markdown ) as $line ) {
            if ( preg_match( '/^##\s+\[?(\d+\.\d+\.\d+)\]?\s*[-\x{2013}\x{2014}]\s*(\d{4}-\d{2}-\d{2})\s*$/u', $line, $m ) ) {
                if ( $current ) {
                    $releases[] = self::finish( $current );
                }
                $current = array( 'version' => $m[1], 'date' => $m[2], 'intro' => array(), 'items' => array() );
                continue;
            }
            if ( null === $current || ( '' !== $line && '#' === $line[0] ) ) {
                continue;
            }

            $trimmed = trim( $line );
            if ( '' === $trimmed ) {
                continue;
            }
            if ( preg_match( '/^[-*]\s+(.*)$/', $trimmed, $m ) ) {
                $current['items'][] = $m[1];
                continue;
            }
            if ( ! empty( $current['items'] ) ) {
                // An indented line under a bullet continues it.
                if ( preg_match( '/^\s/', $line ) ) {
                    $last = count( $current['items'] ) - 1;
                    $current['items'][ $last ] .= ' ' . $trimmed;
                }
                continue;
            }
            $current['intro'][] = $trimmed;
        }

        if ( $current ) {
            $releases[] = self::finish( $current );
        }

        return $releases;
    }

    private static function finish( $release ) {
        $items = array();
        foreach ( $release['items'] as $raw ) {
            $items[] = self::split_item( $raw );
        }
        return array(
            'version' => $release['version'],
            'date'    => $release['date'],
            'intro'   => implode( ' ', $release['intro'] ),
            'items'   => $items,
        );
    }

    /**
     * Split a bullet into its bold lead and the detail that follows.
     * A bullet without a bold opening is all lead.
     */
    public static function split_item( $raw ) {
        $raw = trim( (string) $raw );
        if ( preg_match( '/^\*\*(.+?)\*\*\s*(.*)$/su', $raw, $m ) ) {
            return array( 'lead' => trim( $m[1] ), 'detail' => trim( $m[2] ) );
        }
        return array( 'lead' => $raw, 'detail' => '' );
    }

    // ------------------------------------------------------------------
    // Rendering
    // ------------------------------------------------------------------

    /**
     * Inline markdown to HTML: bold, code and http(s) links, with everything
     * else escaped first. The file ships with the plugin, but the renderer
     * still treats it as text, so a stray angle bracket in a note can never
     * become markup.
     */
    public static function render_inline( $text ) {
        $html = esc_html( (string) $text );

        $html = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            function ( $m ) {
                $raw = html_entity_decode( $m[2], ENT_QUOTES );
                $url = preg_match( '#^https?://#i', $raw ) ? esc_url( $raw ) : '';
                if ( '' === $url ) {
                    return $m[1];
                }
                return '<a href="' . $url . '">' . $m[1] . '</a>';
            },
            $html
        );
        $html = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
        $html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );

        return $html;
    }

    /** "2026-09-03" as "Sep 3, 2026". */
    public static function format_date( $ymd ) {
        $ts = strtotime( $ymd . ' 12:00:00 UTC' );
        return $ts ? date_i18n( 'M j, Y', $ts ) : $ymd;
    }

    /**
     * The releases with their text rendered to HTML, the one shape the page
     * template and the modal both draw from, so the two can't drift.
     */
    public static function to_view( $releases ) {
        $out = array();
        foreach ( array_values( $releases ) as $i => $r ) {
            $items = array();
            foreach ( $r['items'] as $item ) {
                $items[] = array(
                    'lead'   => self::render_inline( $item['lead'] ),
                    'detail' => self::render_inline( $item['detail'] ),
                );
            }
            $out[] = array(
                'version'      => $r['version'],
                'date'         => $r['date'],
                'date_display' => self::format_date( $r['date'] ),
                'latest'       => 0 === $i,
                'intro'        => self::render_inline( $r['intro'] ),
                'items'        => $items,
            );
        }
        return $out;
    }

    /**
     * The release list markup, shared by the page (server-rendered) and
     * mirrored by the modal (client-rendered from the same view data).
     */
    public static function render_list( $view ) {
        if ( empty( $view ) ) {
            return '<p class="rtg-wn-empty">Nothing to report yet.</p>';
        }
        $html = '';
        foreach ( $view as $r ) {
            $html .= '<article class="rtg-wn-release">';
            $html .= '<header class="rtg-wn-release-head">';
            $html .= '<span class="rtg-wn-version">' . esc_html( $r['version'] ) . '</span>';
            $html .= '<time class="rtg-wn-date" datetime="' . esc_attr( $r['date'] ) . '">' . esc_html( $r['date_display'] ) . '</time>';
            if ( $r['latest'] ) {
                $html .= '<span class="rtg-wn-latest">Latest</span>';
            }
            $html .= '</header>';
            if ( '' !== $r['intro'] ) {
                $html .= '<p class="rtg-wn-intro">' . $r['intro'] . '</p>';
            }
            if ( ! empty( $r['items'] ) ) {
                $html .= '<ul class="rtg-wn-list">';
                foreach ( $r['items'] as $item ) {
                    $html .= '<li><strong>' . $item['lead'] . '</strong>';
                    if ( '' !== $item['detail'] ) {
                        $html .= ' ' . $item['detail'];
                    }
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }
            $html .= '</article>';
        }
        return $html;
    }

    // ------------------------------------------------------------------
    // Delivery
    // ------------------------------------------------------------------

    public function register_rest_route() {
        register_rest_route(
            RTG_REST_API::NAMESPACE,
            self::REST_ROUTE,
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /** GET /wp-json/rtg/v1/whats-new: the notes as rendered view data. */
    public function rest_get() {
        $response = new WP_REST_Response( array(
            'latest'   => self::latest_version(),
            'url'      => self::url(),
            'releases' => self::to_view( self::get_releases() ),
        ) );
        $response->header( 'Cache-Control', 'public, max-age=300' );
        return $response;
    }

    /** Render the page inside the theme at its own URL. */
    public function maybe_render() {
        if ( ! get_query_var( self::QUERY_VAR ) ) {
            return;
        }

        RTG_Theme_Render::render( array(
            'title'       => "What's New — Rivian Tire Guide",
            'slug'        => 'whats-new',
            'canonical'   => self::url(),
            'description' => 'Every change you can see in the Rivian Tire Guide, newest first, in plain language.',
            'content'     => function () {
                include RTG_PLUGIN_DIR . 'frontend/templates/whats-new.php';
            },
        ) );
    }
}
