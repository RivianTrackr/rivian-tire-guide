<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Outputs Open Graph and Twitter Card meta tags for tire deep-links.
 *
 * When a ?tire= parameter is present on a page containing the
 * [rivian_tire_guide] shortcode, this class outputs tire-specific
 * OG and Twitter Card meta tags for rich social previews.
 */
class RTG_Meta {

    public function __construct() {
        add_action( 'wp_head', array( $this, 'output_meta_tags' ), 5 );
    }

    /**
     * Output OG and Twitter Card meta tags.
     */
    public function output_meta_tags() {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'rivian_tire_guide' ) ) {
            return;
        }

        $tire_id = isset( $_GET['tire'] ) ? sanitize_text_field( wp_unslash( $_GET['tire'] ) ) : '';

        if ( $tire_id && preg_match( '/^[A-Za-z0-9_-]+$/', $tire_id ) ) {
            $this->output_tire_tags( $tire_id );
        } else {
            $this->output_default_tags();
        }
    }

    /**
     * Meta tags for a specific tire deep-link.
     */
    private function output_tire_tags( $tire_id ) {
        $tire = RTG_Database::get_tire( $tire_id );

        if ( ! $tire ) {
            $this->output_default_tags();
            return;
        }

        $brand = $tire['brand'] ?? '';
        $model = $tire['model'] ?? '';
        $size  = $tire['size'] ?? '';

        $title = trim( "$brand $model" ) ?: 'Tire';
        if ( $size ) {
            $title .= " ($size)";
        }
        $title .= ' — Rivian Tire Guide';

        $description = $this->build_description( $tire );
        $url         = $this->get_tire_url( $tire_id );
        $image       = ! empty( $tire['image'] ) ? esc_url( $tire['image'] ) : '';

        $this->render_tags( $title, $description, $url, $image );
    }

    /**
     * Default meta tags for the tire guide catalog page.
     */
    private function output_default_tags() {
        $title       = 'Rivian Tire Guide — Find the Perfect Tires for Your Rivian';
        $description = 'Browse, compare, and filter tires compatible with Rivian vehicles. Find the right fit by size, brand, category, price, and more.';
        $url         = get_permalink();

        $this->render_tags( $title, $description, $url, '' );
    }

    /**
     * Render the actual meta tag HTML.
     */
    private function render_tags( $title, $description, $url, $image ) {
        $title       = esc_attr( $title );
        $description = esc_attr( $description );
        $url         = esc_url( $url );

        echo "\n<!-- Rivian Tire Guide: Open Graph / Twitter Card -->\n";

        // Open Graph.
        echo '<meta property="og:type" content="product" />' . "\n";
        echo '<meta property="og:title" content="' . $title . '" />' . "\n";
        echo '<meta property="og:description" content="' . $description . '" />' . "\n";
        echo '<meta property="og:url" content="' . $url . '" />' . "\n";

        if ( $image ) {
            echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
        }

        // Twitter Card.
        echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . $title . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . $description . '" />' . "\n";

        if ( $image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
        }

        echo "<!-- / Rivian Tire Guide -->\n\n";
    }

    /**
     * Build a social-friendly description for a tire.
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

        if ( ! empty( $tire['price'] ) && $tire['price'] > 0 ) {
            $parts[] = '$' . number_format( (float) $tire['price'], 2 );
        }

        if ( ! empty( $tire['three_pms'] ) && $tire['three_pms'] === 'Yes' ) {
            $parts[] = '3PMS winter rated';
        }

        if ( ! empty( $tire['mileage_warranty'] ) && $tire['mileage_warranty'] > 0 ) {
            $parts[] = number_format( $tire['mileage_warranty'] ) . ' mile warranty';
        }

        $desc = implode( ' · ', $parts );

        return $desc ? $desc . '. Compatible with Rivian vehicles.' : 'Tire compatible with Rivian vehicles.';
    }

    /**
     * Build the canonical URL for a tire deep-link.
     */
    private function get_tire_url( $tire_id ) {
        $base = get_permalink();
        return add_query_arg( 'tire', rawurlencode( $tire_id ), $base );
    }
}
