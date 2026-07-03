<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Outputs Schema.org JSON-LD structured data for tire products.
 *
 * Generates Product and AggregateRating markup for each tire displayed
 * by the [rivian_tire_guide] shortcode, improving SEO and enabling
 * rich snippets in search engine results.
 */
class RTG_Schema {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'output_structured_data' ) );
    }

    /**
     * Output JSON-LD structured data for all tires on pages with the shortcode.
     */
    public function output_structured_data() {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'rivian_tire_guide' ) ) {
            return;
        }

        $tires   = RTG_Database::get_all_tires();
        $ratings = $this->get_all_ratings( $tires );

        if ( empty( $tires ) ) {
            return;
        }

        $items = array();

        foreach ( $tires as $tire ) {
            $rating  = $ratings[ $tire['tire_id'] ] ?? null;
            $items[] = self::build_product_item( $tire, $rating );
        }

        $schema = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => 'Rivian Tire Guide',
            'numberOfItems'   => count( $items ),
            'itemListElement' => array(),
        );

        foreach ( $items as $position => $item ) {
            $schema['itemListElement'][] = array(
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'item'     => $item,
            );
        }

        echo "\n<!-- Rivian Tire Guide - Schema.org Structured Data -->\n";
        echo '<script type="application/ld+json">';
        echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        echo "</script>\n";
    }

    /**
     * Build a single Schema.org Product node for one tire.
     *
     * Shared by the catalog ItemList (on the shortcode page) and the standalone
     * per-tire page. Pass $rating (keys: average, count, review_count) to embed
     * AggregateRating + Review; omit it to skip ratings.
     *
     * @param array      $tire   Tire row (associative).
     * @param array|null $rating Optional aggregate rating data for the tire.
     * @return array Product node (no @context — caller adds it for a standalone node).
     */
    public static function build_product_item( $tire, $rating = null ) {
        $item = array(
            '@type'       => 'Product',
            'name'        => self::build_product_name( $tire ),
            'brand'       => array(
                '@type' => 'Brand',
                'name'  => $tire['brand'],
            ),
            'category'    => 'Tires',
            'description' => self::build_description( $tire ),
        );

        // SKU / identifier.
        if ( ! empty( $tire['tire_id'] ) ) {
            $item['sku'] = $tire['tire_id'];
        }

        // Image.
        if ( ! empty( $tire['image'] ) ) {
            $item['image'] = esc_url( $tire['image'] );
        }

        // Offer (price).
        if ( ! empty( $tire['price'] ) && $tire['price'] > 0 ) {
            $item['offers'] = array(
                '@type'         => 'Offer',
                'price'         => number_format( (float) $tire['price'], 2, '.', '' ),
                'priceCurrency' => 'USD',
                'availability'  => 'https://schema.org/InStock',
            );

            if ( ! empty( $tire['link'] ) ) {
                $item['offers']['url'] = esc_url( $tire['link'] );
            }
        }

        // Additional properties.
        $additional = array();

        if ( ! empty( $tire['size'] ) ) {
            $additional[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'Tire Size',
                'value' => $tire['size'],
            );
        }

        if ( ! empty( $tire['load_index'] ) ) {
            $additional[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'Load Index',
                'value' => $tire['load_index'],
            );
        }

        if ( ! empty( $tire['speed_rating'] ) ) {
            $additional[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'Speed Rating',
                'value' => $tire['speed_rating'],
            );
        }

        if ( ! empty( $tire['utqg'] ) ) {
            $additional[] = array(
                '@type' => 'PropertyValue',
                'name'  => 'UTQG',
                'value' => $tire['utqg'],
            );
        }

        if ( ! empty( $tire['weight_lb'] ) && $tire['weight_lb'] > 0 ) {
            $additional[] = array(
                '@type'    => 'QuantitativeValue',
                'name'     => 'Weight',
                'value'    => (float) $tire['weight_lb'],
                'unitCode' => 'LBR',
            );
        }

        if ( ! empty( $additional ) ) {
            $item['additionalProperty'] = $additional;
        }

        // Aggregate rating from user reviews.
        if ( is_array( $rating ) && ! empty( $rating['count'] ) ) {
            $item['aggregateRating'] = array(
                '@type'       => 'AggregateRating',
                'ratingValue' => $rating['average'],
                'bestRating'  => 5,
                'worstRating' => 1,
                'ratingCount' => $rating['count'],
            );

            // Include individual text reviews for rich snippet eligibility.
            if ( ! empty( $rating['review_count'] ) ) {
                $reviews = RTG_Database::get_tire_reviews( $tire['tire_id'], 5 );
                if ( ! empty( $reviews ) ) {
                    $item['review'] = array();
                    foreach ( $reviews as $review ) {
                        $item['review'][] = array(
                            '@type'        => 'Review',
                            'author'       => array(
                                '@type' => 'Person',
                                'name'  => $review['display_name'],
                            ),
                            'datePublished' => date( 'Y-m-d', strtotime( $review['updated_at'] ?? $review['created_at'] ) ),
                            'reviewRating'  => array(
                                '@type'      => 'Rating',
                                'ratingValue' => (int) $review['rating'],
                                'bestRating'  => 5,
                                'worstRating' => 1,
                            ),
                            'name'         => ! empty( $review['review_title'] ) ? $review['review_title'] : null,
                            'reviewBody'   => $review['review_text'],
                        );
                    }
                    // Filter out null name fields.
                    foreach ( $item['review'] as &$r ) {
                        $r = array_filter( $r, function( $v ) { return $v !== null; } );
                    }
                    unset( $r );
                }
            }
        }

        return $item;
    }

    /**
     * Build a standalone Product node (with @context) for a single tire page.
     * Fetches the tire's own rating data.
     *
     * @param array $tire Tire row (associative).
     * @return array Product node including @context.
     */
    public static function build_single_product( $tire ) {
        $ratings = RTG_Database::get_tire_ratings( array( $tire['tire_id'] ) );
        $rating  = $ratings[ $tire['tire_id'] ] ?? null;

        $item = self::build_product_item( $tire, $rating );

        return array_merge( array( '@context' => 'https://schema.org' ), $item );
    }

    /**
     * Build a descriptive product name from tire data.
     *
     * @param array $tire Tire data row.
     * @return string Product name.
     */
    private static function build_product_name( $tire ) {
        $parts = array_filter( array(
            $tire['brand'] ?? '',
            $tire['model'] ?? '',
            $tire['size'] ?? '',
        ) );

        return implode( ' ', $parts ) ?: 'Tire';
    }

    /**
     * Build a product description from tire specifications.
     *
     * @param array $tire Tire data row.
     * @return string Description string.
     */
    private static function build_description( $tire ) {
        $parts = array();

        if ( ! empty( $tire['brand'] ) && ! empty( $tire['model'] ) ) {
            $parts[] = sprintf( '%s %s', $tire['brand'], $tire['model'] );
        }

        if ( ! empty( $tire['category'] ) ) {
            $parts[] = $tire['category'] . ' tire';
        }

        if ( ! empty( $tire['size'] ) ) {
            $parts[] = 'size ' . $tire['size'];
        }

        if ( ! empty( $tire['three_pms'] ) && $tire['three_pms'] === 'Yes' ) {
            $parts[] = '3PMS winter rated';
        }

        if ( ! empty( $tire['mileage_warranty'] ) && $tire['mileage_warranty'] > 0 ) {
            $parts[] = number_format( $tire['mileage_warranty'] ) . ' mile warranty';
        }

        $desc = implode( ', ', $parts );

        return $desc ? $desc . '. Compatible with Rivian vehicles.' : 'Tire compatible with Rivian vehicles.';
    }

    /**
     * Get ratings for all tires in a single query.
     *
     * @param array $tires Array of tire data rows.
     * @return array Keyed by tire_id with 'average' and 'count'.
     */
    private function get_all_ratings( $tires ) {
        $tire_ids = array_column( $tires, 'tire_id' );

        if ( empty( $tire_ids ) ) {
            return array();
        }

        return RTG_Database::get_tire_ratings( $tire_ids );
    }
}
