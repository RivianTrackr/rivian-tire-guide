<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * A catalog source backed by a JSON document instead of a live API.
 *
 * This exists for two reasons. It makes the whole discovery pipeline testable
 * and demonstrable without affiliate credentials, and it gives a usable
 * fallback for any retailer with no machine-readable feed: point it at a JSON
 * file you maintain by hand and the queue, qualification and digest all work
 * exactly as they do for an API-backed source.
 *
 * The document is either a bare array of products or an object with a
 * `products` key:
 *
 *   { "advertiser_name": "Tire Rack", "products": [ { "external_id": "...", ... } ] }
 *
 * @since 1.59.0
 */
class RTG_Catalog_Source_Fixture implements RTG_Catalog_Source {

    /** Bundled sample, used when no source URL is configured. */
    const DEFAULT_PATH = 'includes/data/catalog-fixture-sample.json';

    /** HTTP timeout in seconds when the fixture is remote. */
    const REQUEST_TIMEOUT = 15;

    /** @var string Location of the JSON document (URL or absolute path). */
    private $location;

    /** @var string Last failure reason, '' when the last fetch succeeded. */
    private $last_error = '';

    /**
     * @param string $location URL or absolute path. Defaults to the bundled sample.
     */
    public function __construct( $location = '' ) {
        $this->location = '' !== $location ? $location : RTG_PLUGIN_DIR . self::DEFAULT_PATH;
    }

    /**
     * @return string Source slug.
     */
    public function get_slug() {
        return 'fixture';
    }

    /**
     * @return string Human-readable source name.
     */
    public function get_label() {
        return 'JSON feed';
    }

    /**
     * @return string Last error message, or ''.
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Read the document and return its products.
     *
     * Sizes are accepted for interface parity but not used to filter: the
     * qualifier applies the size rule downstream, and filtering here would
     * hide near misses that are worth seeing in the rejected view.
     *
     * @param string[] $sizes Canonical sizes (unused).
     * @return array[] Normalized products.
     */
    public function fetch( $sizes ) {
        $this->last_error = '';

        $body = $this->read();
        if ( null === $body ) {
            return array();
        }

        $data = json_decode( $body, true );
        if ( ! is_array( $data ) ) {
            $this->last_error = 'Fixture is not valid JSON.';
            return array();
        }

        $advertiser_name = '';
        $advertiser_id   = '';
        if ( isset( $data['products'] ) ) {
            $advertiser_name = (string) ( $data['advertiser_name'] ?? '' );
            $advertiser_id   = (string) ( $data['advertiser_id'] ?? '' );
            $products        = $data['products'];
        } else {
            $products = $data;
        }

        if ( ! is_array( $products ) ) {
            $this->last_error = 'Fixture has no products array.';
            return array();
        }

        $normalized = array();
        foreach ( $products as $product ) {
            if ( ! is_array( $product ) || empty( $product['external_id'] ) ) {
                continue;
            }

            // Document-level advertiser details apply to any product that
            // doesn't name its own, so a single-retailer file needn't repeat
            // them on every row.
            $product['advertiser_name'] = (string) ( $product['advertiser_name'] ?? $advertiser_name );
            $product['advertiser_id']   = (string) ( $product['advertiser_id'] ?? $advertiser_id );

            $normalized[] = $product;
        }

        return $normalized;
    }

    /**
     * Read the fixture from disk or over HTTP.
     *
     * @return string|null Raw document body, or null on failure.
     */
    private function read() {
        if ( preg_match( '#^https?://#i', $this->location ) ) {
            $response = wp_remote_get( $this->location, array(
                'timeout'   => self::REQUEST_TIMEOUT,
                'sslverify' => true,
            ) );

            if ( is_wp_error( $response ) ) {
                $this->last_error = $response->get_error_message();
                return null;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $code ) {
                $this->last_error = 'HTTP ' . $code;
                return null;
            }

            return wp_remote_retrieve_body( $response );
        }

        if ( ! file_exists( $this->location ) || ! is_readable( $this->location ) ) {
            $this->last_error = 'Fixture file not found or unreadable.';
            return null;
        }

        $body = file_get_contents( $this->location ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $body ) {
            $this->last_error = 'Could not read fixture file.';
            return null;
        }

        return $body;
    }
}
