<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Ajax {

    public function __construct() {
        // Rating handlers — available to both logged-in and logged-out users.
        add_action( 'wp_ajax_get_tire_ratings', array( $this, 'get_tire_ratings' ) );
        add_action( 'wp_ajax_nopriv_get_tire_ratings', array( $this, 'get_tire_ratings' ) );

        // Submit rating — logged-in users only.
        add_action( 'wp_ajax_submit_tire_rating', array( $this, 'submit_tire_rating' ) );
    }

    /**
     * Get ratings for an array of tire IDs.
     * Available to both logged-in and logged-out users.
     */
    public function get_tire_ratings() {
        $raw_ids = isset( $_POST['tire_ids'] ) ? (array) $_POST['tire_ids'] : array();
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

        $ratings = RTG_Database::get_tire_ratings( $tire_ids );
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
     * Logged-in users only, with nonce verification.
     */
    public function submit_tire_rating() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to rate tires.' );
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

        // Save the rating.
        RTG_Database::set_rating( $tire_id, get_current_user_id(), $rating );

        // Return updated rating data.
        $ratings = RTG_Database::get_tire_ratings( array( $tire_id ) );
        $tire_rating = $ratings[ $tire_id ] ?? array( 'average' => 0, 'count' => 0 );

        wp_send_json_success( array(
            'average_rating' => $tire_rating['average'],
            'rating_count'   => $tire_rating['count'],
            'user_rating'    => $rating,
        ) );
    }
}
