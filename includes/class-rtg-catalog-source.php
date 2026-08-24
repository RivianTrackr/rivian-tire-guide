<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contract for a place tires can be discovered.
 *
 * The sync pipeline — qualify, dedupe, queue, notify — is identical whatever
 * the products came from, so sources are kept behind this interface and
 * registered through the `rtg_catalog_sources` filter. Adding a retailer means
 * writing a fetch() that returns normalized products; nothing downstream
 * changes.
 *
 * fetch() returns a flat array of products, each an associative array:
 *
 *   external_id     string  Stable per-product ID at the source. Required —
 *                           it is how a product is recognized on later runs.
 *   title           string  Full product title, parsed for specs.
 *   brand           string  Manufacturer, when the source states one.
 *   size            string  Tire size, when the source states one.
 *   load_index      string  Load index, when the source states one.
 *   load_range      string  Load range, when the source states one.
 *   speed_rating    string  Speed rating, when the source states one.
 *   price           float   Current price.
 *   link            string  Affiliate-tracked product URL.
 *   image           string  Product image URL.
 *   advertiser_id   string  Retailer ID within the source network.
 *   advertiser_name string  Human-readable retailer name.
 *
 * Only external_id and title are required; the qualifier reads whatever else
 * is present and parses the rest out of the title.
 *
 * A source may also return `_source_node`: the upstream record exactly as it
 * arrived. It is stored with the candidate and never interpreted, so that a
 * later question about a field the mapper didn't keep can be answered from the
 * database rather than by re-running the fetch.
 *
 * @since 1.59.0
 */
interface RTG_Catalog_Source {

    /**
     * @return string Stable slug stored on every candidate row (e.g. 'cj').
     */
    public function get_slug();

    /**
     * @return string Human-readable name for the admin UI.
     */
    public function get_label();

    /**
     * Fetch candidate products for the guide's sizes.
     *
     * Implementations should return an empty array rather than throw when a
     * remote call fails, and report the failure via get_last_error().
     *
     * @param string[] $sizes Canonical sizes the guide cares about.
     * @return array[] Normalized products, as documented above.
     */
    public function fetch( $sizes );

    /**
     * @return string Last error message, or '' when the fetch succeeded.
     */
    public function get_last_error();
}
