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
            return '';
        }

        $existing = self::existing_file( $base );
        if ( '' !== $existing ) {
            return $existing;
        }

        $url = trim( (string) $url );
        if ( ! preg_match( '#^https?://#i', $url ) ) {
            return '';
        }

        // safe_remote_get refuses redirects into private address space — the
        // URL came from an affiliate feed, not from anyone this site trusts.
        $response = wp_safe_remote_get( $url, array(
            'timeout'             => 15,
            'limit_response_size' => self::MAX_BYTES,
        ) );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return '';
        }

        $type = strtolower( trim( strtok( (string) wp_remote_retrieve_header( $response, 'content-type' ), ';' ) ) );
        if ( ! isset( self::CONTENT_TYPES[ $type ] ) ) {
            return '';
        }

        $body = wp_remote_retrieve_body( $response );
        if ( '' === $body || strlen( $body ) > self::MAX_BYTES ) {
            return '';
        }

        // The header said image; make the bytes agree. AVIF is exempt only
        // because PHP before 8.1 cannot read its dimensions — the content
        // type still had to say image/avif to get here.
        if ( 'avif' !== self::CONTENT_TYPES[ $type ] && false === @getimagesizefromstring( $body ) ) {
            return '';
        }

        $dir = self::dir();
        if ( ! wp_mkdir_p( $dir ) ) {
            return '';
        }

        $filename = $base . '.' . self::CONTENT_TYPES[ $type ];
        $written  = file_put_contents( $dir . '/' . $filename, $body );

        return false === $written ? '' : $filename;
    }
}
