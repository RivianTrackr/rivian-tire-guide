<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Ajax {

    /**
     * Maximum rating submissions per minute per user.
     */
    const RATE_LIMIT_MAX    = 10;
    const RATE_LIMIT_WINDOW = 60; // seconds

    public function __construct() {
        // Rating handlers — available to both logged-in and logged-out users.
        add_action( 'wp_ajax_get_tire_ratings', array( $this, 'get_tire_ratings' ) );
        add_action( 'wp_ajax_nopriv_get_tire_ratings', array( $this, 'get_tire_ratings' ) );

        // Submit rating — logged-in users only.
        add_action( 'wp_ajax_submit_tire_rating', array( $this, 'submit_tire_rating' ) );

        // Server-side filtered tire listing — public.
        add_action( 'wp_ajax_rtg_get_tires', array( $this, 'get_tires' ) );
        add_action( 'wp_ajax_nopriv_rtg_get_tires', array( $this, 'get_tires' ) );

        // Filter dropdown options — public.
        add_action( 'wp_ajax_rtg_get_filter_options', array( $this, 'get_filter_options' ) );
        add_action( 'wp_ajax_nopriv_rtg_get_filter_options', array( $this, 'get_filter_options' ) );
    }

    /**
     * Get ratings for an array of tire IDs.
     * Available to both logged-in and logged-out users.
     * Nonce verified for logged-in users; open for public reads.
     */
    public function get_tire_ratings() {
        // Verify nonce for logged-in users to prevent CSRF.
        if ( is_user_logged_in() ) {
            if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
                wp_send_json_error( 'Security check failed.' );
            }
        }

        $raw_ids  = isset( $_POST['tire_ids'] ) ? (array) $_POST['tire_ids'] : array();
        $tire_ids = array();

        foreach ( $raw_ids as $id ) {
            $clean = sanitize_text_field( $id );
            if ( preg_match( '/^[a-zA-Z0-9\-_]+$/', $clean ) && strlen( $clean ) <= 50 ) {
                $tire_ids[] = $clean;
            }
        }

        if ( empty( $tire_ids ) ) {
            wp_send_json_success( array(
                'ratings'      => array(),
                'user_ratings' => array(),
                'is_logged_in' => is_user_logged_in(),
            ) );
        }

        $ratings      = RTG_Database::get_tire_ratings( $tire_ids );
        $user_ratings = array();

        if ( is_user_logged_in() ) {
            $user_ratings = RTG_Database::get_user_ratings( $tire_ids, get_current_user_id() );
        }

        wp_send_json_success( array(
            'ratings'      => $ratings,
            'user_ratings' => $user_ratings,
            'is_logged_in' => is_user_logged_in(),
        ) );
    }

    /**
     * Submit a tire rating.
     * Logged-in users only, with nonce verification and rate limiting.
     */
    public function submit_tire_rating() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to rate tires.' );
        }

        $user_id = get_current_user_id();

        // Rate limiting: max submissions per minute per user.
        if ( $this->is_rate_limited( $user_id ) ) {
            wp_send_json_error( 'Too many rating submissions. Please wait a moment and try again.' );
        }

        $tire_id = sanitize_text_field( $_POST['tire_id'] ?? '' );
        $rating  = intval( $_POST['rating'] ?? 0 );

        // Validate tire_id format.
        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        // Validate rating range.
        if ( $rating < 1 || $rating > 5 ) {
            wp_send_json_error( 'Rating must be between 1 and 5.' );
        }

        // Verify the tire exists before accepting a rating.
        $tire = RTG_Database::get_tire( $tire_id );
        if ( ! $tire ) {
            wp_send_json_error( 'Tire not found.' );
        }

        // Record this submission for rate limiting.
        $this->record_rate_limit( $user_id );

        // Save the rating.
        RTG_Database::set_rating( $tire_id, $user_id, $rating );

        // Return updated rating data.
        $ratings     = RTG_Database::get_tire_ratings( array( $tire_id ) );
        $tire_rating = $ratings[ $tire_id ] ?? array( 'average' => 0, 'count' => 0 );

        wp_send_json_success( array(
            'average_rating' => $tire_rating['average'],
            'rating_count'   => $tire_rating['count'],
            'user_rating'    => $rating,
        ) );
    }

    /**
     * Check if a user has exceeded the rate limit for rating submissions.
     *
     * @param int $user_id WordPress user ID.
     * @return bool True if rate-limited.
     */
    private function is_rate_limited( $user_id ) {
        $transient_key = 'rtg_rate_' . $user_id;
        $attempts      = get_transient( $transient_key );

        if ( false === $attempts ) {
            return false;
        }

        return (int) $attempts >= self::RATE_LIMIT_MAX;
    }

    /**
     * Record a rating submission for rate limiting.
     *
     * @param int $user_id WordPress user ID.
     */
    private function record_rate_limit( $user_id ) {
        $transient_key = 'rtg_rate_' . $user_id;
        $attempts      = get_transient( $transient_key );

        if ( false === $attempts ) {
            set_transient( $transient_key, 1, self::RATE_LIMIT_WINDOW );
        } else {
            set_transient( $transient_key, (int) $attempts + 1, self::RATE_LIMIT_WINDOW );
        }
    }

    /**
     * Server-side filtered + paginated tire listing.
     * Used when the 'server_side_pagination' setting is enabled.
     */
    public function get_tires() {
        check_ajax_referer( 'rtg_tire_nonce', 'nonce' );

        $settings = get_option( 'rtg_settings', array() );
        $per_page = intval( $settings['rows_per_page'] ?? 12 );

        $filters = array(
            'search'       => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
            'size'         => sanitize_text_field( $_POST['size'] ?? '' ),
            'brand'        => sanitize_text_field( $_POST['brand'] ?? '' ),
            'category'     => sanitize_text_field( $_POST['category'] ?? '' ),
            'three_pms'    => ! empty( $_POST['three_pms'] ),
            'ev_rated'     => ! empty( $_POST['ev_rated'] ),
            'studded'      => ! empty( $_POST['studded'] ),
        );

        $price_max = isset( $_POST['price_max'] ) ? floatval( $_POST['price_max'] ) : 600;
        if ( $price_max >= 0 && $price_max <= 2000 ) {
            $filters['price_max'] = $price_max;
        }

        $warranty_min = isset( $_POST['warranty_min'] ) ? intval( $_POST['warranty_min'] ) : 0;
        if ( $warranty_min >= 0 && $warranty_min <= 100000 ) {
            $filters['warranty_min'] = $warranty_min;
        }

        $weight_max = isset( $_POST['weight_max'] ) ? floatval( $_POST['weight_max'] ) : 70;
        if ( $weight_max >= 0 && $weight_max <= 200 ) {
            $filters['weight_max'] = $weight_max;
        }

        $allowed_sorts = array(
            'efficiency_score', 'price-asc', 'price-desc',
            'warranty-desc', 'weight-asc',
            'newest',
        );
        $sort = sanitize_text_field( $_POST['sort'] ?? 'efficiency_score' );
        if ( ! in_array( $sort, $allowed_sorts, true ) ) {
            $sort = 'efficiency_score';
        }

        $page = max( 1, intval( $_POST['page'] ?? 1 ) );

        $result = RTG_Database::get_filtered_tires( $filters, $sort, $page, $per_page );

        wp_send_json_success( array(
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages' => ceil( $result['total'] / $per_page ),
        ) );
    }

    /**
     * Return distinct filter dropdown values (sizes, brands, categories).
     * Lightweight endpoint used only in server-side pagination mode.
     */
    public function get_filter_options() {
        check_ajax_referer( 'rtg_tire_nonce', 'nonce' );

        global $wpdb;
        $table = RTG_Database::tires_table_public();

        $sizes      = $wpdb->get_col( "SELECT DISTINCT size FROM {$table} WHERE size != '' ORDER BY size ASC" );
        $brands     = $wpdb->get_col( "SELECT DISTINCT brand FROM {$table} WHERE brand != '' ORDER BY brand ASC" );
        $categories = $wpdb->get_col( "SELECT DISTINCT category FROM {$table} WHERE category != '' ORDER BY category ASC" );

        wp_send_json_success( array(
            'sizes'      => array_map( 'sanitize_text_field', $sizes ),
            'brands'     => array_map( 'sanitize_text_field', $brands ),
            'categories' => array_map( 'sanitize_text_field', $categories ),
        ) );
    }
}
