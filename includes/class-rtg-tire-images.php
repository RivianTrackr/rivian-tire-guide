<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Brings a discovered tire's product image into the guide's own image folder.
 *
 * Guide tires reference images by bare filename inside one folder on this
 * site — permanent, fast, and owned. Candidates from the affiliate catalog
 * carry a retailer CDN URL instead: slow, occasionally watermarked, and gone
 * whenever the retailer reshuffles. Importing a tire used to mean bridging
 * that gap by hand.
 *
 * This class downloads the candidate's image into the folder at import time,
 * named the way a person would name it (brand-model, slugified) — and if a
 * file by that name already exists, it is reused instead of re-fetched, so a
 * second size of the same model shares its sibling's image and a hand-placed
 * file always wins over a download.
 *
 * The URL being fetched is external data from the affiliate feed, so the
 * fetch is defensive: wp_safe_remote_get (no internal addresses), an image
 * content type required, the bytes verified as an actual image, and a size
 * cap. On any failure the caller falls back rather than retrying — a missing
 * local image is visible on the edit screen, not silent.
 *
 * @since 1.77.0
 */
class RTG_Tire_Images {

    /** Where tire images live, relative to the WordPress root. */
    const RELATIVE_DIR = 'assets/tire-guide/images';

    /** Public prefix the guide builds image URLs with (see RTG_Admin). */
    const URL_PREFIX = 'https://riviantrackr.com/assets/tire-guide/images/';

    /** Refuse downloads beyond this many bytes — product shots are small. */
    const MAX_BYTES = 5242880;

    /** Extensions checked when looking for an existing file to reuse. */
    const KNOWN_EXTENSIONS = array( 'webp', 'jpg', 'jpeg', 'png', 'gif', 'avif' );

    /** Content types accepted from the remote server, mapped to extensions. */
    const CONTENT_TYPES = array(
        'image/webp' => 'webp',
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/avif' => 'avif',
    );

    /** Option recording the most recent import attempt, for the admin notice. */
    const LAST_OPTION = 'rtg_tire_images_last';

    /**
     * Sent instead of WordPress's default user agent, which image CDNs
     * routinely refuse as bot traffic.
     */
    const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    /** Why the last import_from_url() call returned '', or '' on success. */
    private static $last_error = '';

    /**
     * @return string Why the last import failed, in a sentence — '' if it didn't.
     */
    public static function get_last_error() {
        return self::$last_error;
    }

    /**
     * @return array|false The most recent import attempt: time, url, filename, error.
     */
    public static function get_last() {
        return get_option( self::LAST_OPTION, false );
    }

    /**
     * Filesystem path of the image folder.
     *
     * Filterable because "site root" is a guess about server layout that one
     * install in a subdirectory would break — the filter is the escape hatch.
     *
     * @return string Absolute path, no trailing slash.
     */
    public static function dir() {
        return apply_filters(
            'rtg_tire_images_dir',
            untrailingslashit( ABSPATH ) . '/' . self::RELATIVE_DIR
        );
    }

    /**
     * The freshest catalog image URL for a tire, or '' when none is known.
     *
     * Matched by the same keys everything else matches on (brand, model or
     * alias, size), and freshest sighting wins — a retailer that reshuffled
     * its CDN leaves stale rows pointing at dead URLs.
     *
     * @param array $tire Tire fields (brand, model, size, model_aliases).
     * @return string Image URL from the candidate rows, or ''.
     */
    public static function catalog_image_for( $tire ) {
        $keys = array_flip( RTG_Catalog_Sync::match_keys_for_tire( $tire ) );
        if ( empty( $keys ) ) {
            return '';
        }

        $best      = '';
        $best_seen = 0;

        foreach ( RTG_Candidates::get_by_match_key() as $key => $rows ) {
            if ( ! isset( $keys[ $key ] ) ) {
                continue;
            }

            foreach ( $rows as $row ) {
                $image = trim( (string) ( $row['image'] ?? '' ) );
                if ( '' === $image ) {
                    continue;
                }

                $seen = (int) strtotime( (string) ( $row['last_seen_at'] ?? '' ) );
                if ( $seen > $best_seen ) {
                    $best_seen = $seen;
                    $best      = $image;
                }
            }
        }

        return $best;
    }

    /**
     * The base filename (no extension) a tire's image goes by.
     *
     * Brand and model, slugified — "Nitto" + "Ridge Grappler" becomes
     * "nitto-ridge-grappler". Sizes share a base name on purpose: retailers
     * photograph the model, not the size.
     *
     * @param string $brand Tire brand.
     * @param string $model Tire model.
     * @return string Slug, or '' when there is nothing to name it by.
     */
    public static function basename_for( $brand, $model ) {
        return sanitize_title( trim( trim( (string) $brand ) . ' ' . trim( (string) $model ) ) );
    }

    /**
     * An already-present file for this tire, if any.
     *
     * @param string $base Base filename from basename_for().
     * @return string Filename with extension, or '' when none exists.
     */
    public static function existing_file( $base ) {
        if ( '' === $base ) {
            return '';
        }

        $dir = self::dir();
        foreach ( self::KNOWN_EXTENSIONS as $extension ) {
            if ( file_exists( $dir . '/' . $base . '.' . $extension ) ) {
                return $base . '.' . $extension;
            }
        }

        return '';
    }

    /**
     * Fetch a product image into the folder and return its filename.
     *
     * Reuses an existing file for the same brand and model without touching
     * the network. Returns '' on any failure — the caller decides what a
     * tire with no local image gets instead.
     *
     * @param string $url   Remote image URL (external data — treated as such).
     * @param string $brand Tire brand, for the filename.
     * @param string $model Tire model, for the filename.
     * @return string Bare filename inside the folder, or ''.
     */
    public static function import_from_url( $url, $brand, $model ) {
        $base = self::basename_for( $brand, $model );
        if ( '' === $base ) {
            return self::fail( $url, 'The tire has no brand or model to name the file by.' );
        }

        $existing = self::existing_file( $base );
        if ( '' !== $existing ) {
            return self::succeed( $url, $existing );
        }

        $url = trim( (string) $url );

        // A feed's image field sometimes carries the network's tracked link
        // rather than the file. Following one lands on a redirect page — HTML,
        // not an image, which is exactly what a "text/html" refusal looks like
        // — and registers an affiliate click nobody made. The plain
        // destination is the thing to fetch, and unwrapping a URL that isn't
        // wrapped returns it untouched.
        $url = RTG_Price_Sync::destination_url( $url );

        if ( ! preg_match( '#^https?://#i', $url ) ) {
            return self::fail( $url, 'The catalog image URL is not an http(s) URL.' );
        }

        $response      = self::request( $url, '' );
        $tried_referer = false;

        // Hotlink protection is the other way an image URL returns a page:
        // the CDN sees no referer, decides this is a bot, and answers 403 or
        // 200 with an "access denied" page. A browser arriving from the
        // retailer's own site is what it expects to see, so ask again as one
        // before giving up.
        if ( self::looks_refused( $response ) ) {
            $origin = self::origin_of( $url );

            if ( '' !== $origin ) {
                $retry         = self::request( $url, $origin );
                $tried_referer = true;

                if ( ! is_wp_error( $retry ) ) {
                    $response = $retry;
                }
            }
        }

        if ( is_wp_error( $response ) ) {
            return self::fail( $url, sprintf( 'The request failed: %s', $response->get_error_message() ) );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return self::fail( $url, sprintf(
                'The image server answered HTTP %d%s.',
                $code,
                $tried_referer ? ', with and without a browser referer' : ''
            ) );
        }

        $type = self::content_type( $response );
        if ( ! isset( self::CONTENT_TYPES[ $type ] ) ) {
            return self::fail( $url, sprintf(
                'The server sent "%s", not an image type%s. The URL in the feed points at a page rather than a file: %s',
                '' !== $type ? $type : 'no content type',
                $tried_referer ? ', with and without a browser referer' : '',
                $url
            ) );
        }

        // A body that reaches the cap exactly was almost certainly cut off
        // mid-file by limit_response_size — saving it would store a broken
        // image that looks fine in a directory listing.
        $body = wp_remote_retrieve_body( $response );
        if ( '' === $body || strlen( $body ) >= self::MAX_BYTES ) {
            return self::fail( $url, '' === $body
                ? 'The server sent an empty body.'
                : sprintf( 'The image is larger than the %d MB cap.', self::MAX_BYTES / 1048576 ) );
        }

        // The header said image; make the bytes agree. AVIF is exempt only
        // because PHP before 8.1 cannot read its dimensions — the content
        // type still had to say image/avif to get here.
        if ( 'avif' !== self::CONTENT_TYPES[ $type ] && false === @getimagesizefromstring( $body ) ) {
            return self::fail( $url, 'The downloaded bytes are not a readable image, whatever the header claimed.' );
        }

        $dir = self::dir();
        if ( ! wp_mkdir_p( $dir ) ) {
            return self::fail( $url, sprintf( 'The folder %s could not be created — PHP may not be allowed to write outside wp-content on this host.', $dir ) );
        }

        if ( ! is_writable( $dir ) ) {
            return self::fail( $url, sprintf( 'The folder %s exists but PHP cannot write to it — check its ownership and permissions.', $dir ) );
        }

        $filename = $base . '.' . self::CONTENT_TYPES[ $type ];
        $written  = file_put_contents( $dir . '/' . $filename, $body );

        if ( false === $written ) {
            return self::fail( $url, sprintf( 'Writing %s into %s failed.', $filename, $dir ) );
        }

        return self::succeed( $url, $filename );
    }

    /**
     * One fetch of a remote image.
     *
     * safe_remote_get refuses redirects into private address space — the URL
     * came from an affiliate feed, not from anyone this site trusts. The user
     * agent is a browser's: image CDNs routinely refuse the default
     * "WordPress/x.y" agent as bot traffic.
     *
     * @param string $url     Image URL.
     * @param string $referer Referer to send, or '' to send none.
     * @return array|WP_Error The response, as wp_safe_remote_get returns it.
     */
    private static function request( $url, $referer ) {
        $args = array(
            'timeout'             => 15,
            'limit_response_size' => self::MAX_BYTES,
            'user-agent'          => self::USER_AGENT,
        );

        if ( '' !== $referer ) {
            $args['headers'] = array( 'Referer' => $referer );
        }

        return wp_safe_remote_get( $url, $args );
    }

    /**
     * Whether a response looks like a refusal a referer might get past.
     *
     * A 403, or a 200 carrying something that isn't an image — the two shapes
     * hotlink protection takes. A transport error or a 404 is not one of them:
     * asking again more politely won't change either.
     *
     * @param array|WP_Error $response Response to judge.
     * @return bool
     */
    private static function looks_refused( $response ) {
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( 403 === $code ) {
            return true;
        }

        return 200 === $code && ! isset( self::CONTENT_TYPES[ self::content_type( $response ) ] );
    }

    /**
     * @param array $response Response to read.
     * @return string Lowercased content type, without any charset.
     */
    private static function content_type( $response ) {
        return strtolower( trim( strtok(
            (string) wp_remote_retrieve_header( $response, 'content-type' ),
            ';'
        ) ) );
    }

    /**
     * The scheme and host a URL sits on, for use as its own referer.
     *
     * @param string $url Absolute URL.
     * @return string e.g. "https://www.tirerack.com", or '' if unreadable.
     */
    private static function origin_of( $url ) {
        $parts = wp_parse_url( $url );

        if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return '';
        }

        return $parts['scheme'] . '://' . $parts['host'];
    }

    /**
     * Record a failed attempt — the reason is the product, since the caller
     * falls back silently and the admin needs to know what to fix.
     *
     * @param string $url    URL that was attempted.
     * @param string $reason One sentence naming the failing step.
     * @return string Always '' — the caller's failure value.
     */
    private static function fail( $url, $reason ) {
        self::$last_error = $reason;

        update_option( self::LAST_OPTION, array(
            'time'     => current_time( 'mysql' ),
            'url'      => (string) $url,
            'filename' => '',
            'error'    => $reason,
        ), false );

        return '';
    }

    /**
     * @param string $url      URL that was attempted (or skipped, on reuse).
     * @param string $filename Filename now referenced.
     * @return string The filename — the caller's success value.
     */
    private static function succeed( $url, $filename ) {
        self::$last_error = '';

        update_option( self::LAST_OPTION, array(
            'time'     => current_time( 'mysql' ),
            'url'      => (string) $url,
            'filename' => $filename,
            'error'    => '',
        ), false );

        return $filename;
    }
}
