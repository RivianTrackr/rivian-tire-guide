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
            $item = array(
                '@type'       => 'Product',
                'name'        => $this->build_product_name( $tire ),
                'brand'       => array(
                    '@type' => 'Brand',
                    'name'  => $tire['brand'],
                ),
                'category'    => 'Tires',
                'description' => $this->build_description( $tire ),
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
            $tire_id = $tire['tire_id'];
            if ( isset( $ratings[ $tire_id ] ) && $ratings[ $tire_id ]['count'] > 0 ) {
                $item['aggregateRating'] = array(
                    '@type'       => 'AggregateRating',
                    'ratingValue' => $ratings[ $tire_id ]['average'],
                    'bestRating'  => 5,
                    'worstRating' => 1,
                    'ratingCount' => $ratings[ $tire_id ]['count'],
                );

                // Include individual text reviews for rich snippet eligibility.
                if ( $ratings[ $tire_id ]['review_count'] > 0 ) {
                    $reviews = RTG_Database::get_tire_reviews( $tire_id, 5 );
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
                    }
                }
            }

            $items[] = $item;
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
     * Build a descriptive product name from tire data.
     *
     * @param array $tire Tire data row.
     * @return string Product name.
     */
    private function build_product_name( $tire ) {
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
    private function build_description( $tire ) {
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
