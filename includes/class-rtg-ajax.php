<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RTG_Ajax {

    /**
     * Maximum review submissions per window per user/IP.
     */
    const RATE_LIMIT_MAX    = 3;
    const RATE_LIMIT_WINDOW = 300; // seconds (5 minutes)

    public function __construct() {
        // Review handlers — available to both logged-in and logged-out users.
        add_action( 'wp_ajax_get_tire_ratings', array( $this, 'get_tire_ratings' ) );
        add_action( 'wp_ajax_nopriv_get_tire_ratings', array( $this, 'get_tire_ratings' ) );

        // Submit review — logged-in users only.
        add_action( 'wp_ajax_submit_tire_rating', array( $this, 'submit_tire_rating' ) );

        // Delete own rating — logged-in users only.
        add_action( 'wp_ajax_delete_tire_rating', array( $this, 'delete_tire_rating' ) );

        // Submit guest review — non-logged-in users only.
        add_action( 'wp_ajax_nopriv_submit_guest_tire_rating', array( $this, 'submit_guest_tire_rating' ) );

        // Get reviews for a tire — public.
        add_action( 'wp_ajax_get_tire_reviews', array( $this, 'get_tire_reviews' ) );
        add_action( 'wp_ajax_nopriv_get_tire_reviews', array( $this, 'get_tire_reviews' ) );

        // Get reviews by a user — public.
        add_action( 'wp_ajax_rtg_get_user_reviews', array( $this, 'get_user_reviews' ) );
        add_action( 'wp_ajax_nopriv_rtg_get_user_reviews', array( $this, 'get_user_reviews' ) );

        // Server-side filtered tire listing — public.
        add_action( 'wp_ajax_rtg_get_tires', array( $this, 'get_tires' ) );
        add_action( 'wp_ajax_nopriv_rtg_get_tires', array( $this, 'get_tires' ) );

        // Filter dropdown options — public.
        add_action( 'wp_ajax_rtg_get_filter_options', array( $this, 'get_filter_options' ) );
        add_action( 'wp_ajax_nopriv_rtg_get_filter_options', array( $this, 'get_filter_options' ) );

        // Favorites — logged-in users only.
        add_action( 'wp_ajax_rtg_get_favorites', array( $this, 'get_favorites' ) );
        add_action( 'wp_ajax_rtg_add_favorite', array( $this, 'add_favorite' ) );
        add_action( 'wp_ajax_rtg_remove_favorite', array( $this, 'remove_favorite' ) );

        // Admin: update tire links (affiliate link management).
        add_action( 'wp_ajax_rtg_update_tire_links', array( $this, 'update_tire_links' ) );

        // Analytics: click tracking — public.
        add_action( 'wp_ajax_rtg_track_click', array( $this, 'track_click' ) );
        add_action( 'wp_ajax_nopriv_rtg_track_click', array( $this, 'track_click' ) );

        // Analytics: search tracking — public.
        add_action( 'wp_ajax_rtg_track_search', array( $this, 'track_search' ) );
        add_action( 'wp_ajax_nopriv_rtg_track_search', array( $this, 'track_search' ) );

        // Analytics: admin data endpoint.
        add_action( 'wp_ajax_rtg_get_analytics', array( $this, 'get_analytics' ) );

        // Efficiency calculator — admin only.
        add_action( 'wp_ajax_rtg_calculate_efficiency', array( $this, 'calculate_efficiency' ) );

        // Link health check — admin only.
        add_action( 'wp_ajax_rtg_check_links', array( $this, 'check_links' ) );
        add_action( 'wp_ajax_rtg_check_links_start', array( $this, 'check_links_start' ) );
        add_action( 'wp_ajax_rtg_check_links_batch', array( $this, 'check_links_batch' ) );
        add_action( 'wp_ajax_rtg_check_links_finish', array( $this, 'check_links_finish' ) );

        // AI tire recommendations — public.
        add_action( 'wp_ajax_rtg_ai_recommend', array( $this, 'ai_recommend' ) );
        add_action( 'wp_ajax_nopriv_rtg_ai_recommend', array( $this, 'ai_recommend' ) );

        // Roamer sync — admin only.
        add_action( 'wp_ajax_rtg_roamer_sync_now', array( $this, 'roamer_sync_now' ) );
        add_action( 'wp_ajax_rtg_roamer_assign', array( $this, 'roamer_assign' ) );
        add_action( 'wp_ajax_rtg_roamer_unlink', array( $this, 'roamer_unlink' ) );
        add_action( 'wp_ajax_rtg_roamer_hide', array( $this, 'roamer_hide' ) );
        add_action( 'wp_ajax_rtg_roamer_restore', array( $this, 'roamer_restore' ) );
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
        $raw_ids  = array_slice( $raw_ids, 0, 200 ); // Cap to prevent query explosion.
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
            $user_data = RTG_Database::get_user_ratings( $tire_ids, get_current_user_id() );
            // Extract just the rating number for backward compatibility,
            // and include full user review data separately.
            foreach ( $user_data as $tid => $data ) {
                $user_ratings[ $tid ] = $data['rating'];
            }
        }

        wp_send_json_success( array(
            'ratings'      => $ratings,
            'user_ratings' => $user_ratings,
            'user_reviews' => $user_data ?? array(),
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

        // Strip WordPress magic quotes before sanitizing.
        $post = wp_unslash( $_POST );

        $tire_id      = sanitize_text_field( $post['tire_id'] ?? '' );
        $rating       = intval( $post['rating'] ?? 0 );
        $review_title = sanitize_text_field( $post['review_title'] ?? '' );
        $review_text  = sanitize_textarea_field( $post['review_text'] ?? '' );

        // Validate tire_id format.
        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        // Validate rating range.
        if ( $rating < 1 || $rating > 5 ) {
            wp_send_json_error( 'Rating must be between 1 and 5.' );
        }

        // Validate review field lengths.
        if ( mb_strlen( $review_title ) > 200 ) {
            wp_send_json_error( 'Review title must be 200 characters or less.' );
        }

        if ( mb_strlen( $review_text ) > 5000 ) {
            wp_send_json_error( 'Review text must be 5,000 characters or less.' );
        }

        // Verify the tire exists before accepting a rating.
        $tire = RTG_Database::get_tire( $tire_id );
        if ( ! $tire ) {
            wp_send_json_error( 'Tire not found.' );
        }

        // Record this submission for rate limiting.
        $this->record_rate_limit( $user_id );

        // Determine the review status that will be applied.
        $is_admin           = user_can( $user_id, 'manage_options' );
        $has_review_content = ! empty( $review_text ) || ! empty( $review_title );
        if ( $has_review_content && ! $is_admin ) {
            $review_status = 'pending';
        } else {
            $review_status = 'approved';
        }

        // Save the rating with optional review.
        RTG_Database::set_rating( $tire_id, $user_id, $rating, $review_title, $review_text );

        // Return updated rating data.
        $ratings     = RTG_Database::get_tire_ratings( array( $tire_id ) );
        $tire_rating = $ratings[ $tire_id ] ?? array( 'average' => 0, 'count' => 0, 'review_count' => 0 );

        wp_send_json_success( array(
            'average_rating' => $tire_rating['average'],
            'rating_count'   => $tire_rating['count'],
            'review_count'   => $tire_rating['review_count'],
            'user_rating'    => $rating,
            'user_review'    => $review_text,
            'review_status'  => $review_status,
        ) );
    }

    /**
     * Submit a guest tire review (non-logged-in users).
     * Requires name, email, rating, and review content. Always pending.
     */
    public function submit_guest_tire_rating() {
        // Verify nonce.
        if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        // Honeypot: if the hidden website field has a value, it's a bot.
        if ( ! empty( $_POST['website'] ) ) {
            // Silently pretend success so bots don't know they were caught.
            wp_send_json_success( array( 'review_status' => 'pending' ) );
        }

        // IP-based rate limiting for guests.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ( $this->is_guest_rate_limited( $ip ) ) {
            wp_send_json_error( 'Too many submissions. Please wait a moment and try again.' );
        }

        // Strip WordPress magic quotes before sanitizing.
        $post = wp_unslash( $_POST );

        $tire_id      = sanitize_text_field( $post['tire_id'] ?? '' );
        $rating       = intval( $post['rating'] ?? 0 );
        $guest_name   = sanitize_text_field( $post['guest_name'] ?? '' );
        $guest_email  = sanitize_email( $post['guest_email'] ?? '' );
        $review_title = sanitize_text_field( $post['review_title'] ?? '' );
        $review_text  = sanitize_textarea_field( $post['review_text'] ?? '' );

        // Validate tire_id format.
        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        // Validate rating range.
        if ( $rating < 1 || $rating > 5 ) {
            wp_send_json_error( 'Rating must be between 1 and 5.' );
        }

        // Validate guest name.
        if ( empty( $guest_name ) || mb_strlen( $guest_name ) > 100 ) {
            wp_send_json_error( 'Name is required (100 characters max).' );
        }

        // Validate guest email.
        if ( empty( $guest_email ) || ! is_email( $guest_email ) ) {
            wp_send_json_error( 'A valid email address is required.' );
        }

        // Validate review field lengths.
        if ( mb_strlen( $review_title ) > 200 ) {
            wp_send_json_error( 'Review title must be 200 characters or less.' );
        }

        if ( mb_strlen( $review_text ) > 5000 ) {
            wp_send_json_error( 'Review text must be 5,000 characters or less.' );
        }

        // Guests must provide review content (not just a star rating).
        if ( empty( $review_title ) && empty( $review_text ) ) {
            wp_send_json_error( 'Please write a review title or body text.' );
        }

        // Verify the tire exists.
        $tire = RTG_Database::get_tire( $tire_id );
        if ( ! $tire ) {
            wp_send_json_error( 'Tire not found.' );
        }

        // Check for duplicate guest review on same tire.
        if ( RTG_Database::guest_review_exists( $guest_email, $tire_id ) ) {
            wp_send_json_error( 'You have already reviewed this tire.' );
        }

        // Record rate limit hit.
        $this->record_guest_rate_limit( $ip );

        // Save the guest review (always pending).
        RTG_Database::set_guest_rating( $tire_id, $guest_name, $guest_email, $rating, $review_title, $review_text );

        // Notify admin about the new guest review.
        RTG_Mailer::send_admin_guest_review_notification( $guest_name, $guest_email, array(
            'tire_id'      => $tire_id,
            'rating'       => $rating,
            'review_title' => $review_title,
            'review_text'  => $review_text,
        ) );

        wp_send_json_success( array(
            'review_status' => 'pending',
        ) );
    }

    /**
     * Delete the current user's own rating for a tire.
     * Logged-in users only, with nonce verification.
     */
    public function delete_tire_rating() {
        if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to delete ratings.' );
        }

        $user_id = get_current_user_id();
        $tire_id = sanitize_text_field( $_POST['tire_id'] ?? '' );

        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        $result = RTG_Database::delete_user_rating( $tire_id, $user_id );

        if ( $result ) {
            $ratings     = RTG_Database::get_tire_ratings( array( $tire_id ) );
            $tire_rating = $ratings[ $tire_id ] ?? array( 'average' => 0, 'count' => 0, 'review_count' => 0 );

            wp_send_json_success( array(
                'average_rating' => $tire_rating['average'],
                'rating_count'   => $tire_rating['count'],
                'review_count'   => $tire_rating['review_count'],
            ) );
        } else {
            wp_send_json_error( 'No rating found to delete.' );
        }
    }

    /**
     * Check if a guest IP has exceeded the rate limit.
     *
     * @param string $ip Client IP address.
     * @return bool True if rate-limited.
     */
    private function is_guest_rate_limited( $ip ) {
        $transient_key = 'rtg_rate_guest_' . md5( $ip );
        $attempts      = get_transient( $transient_key );

        if ( false === $attempts ) {
            return false;
        }

        return (int) $attempts >= self::RATE_LIMIT_MAX;
    }

    /**
     * Record a guest rate-limit hit.
     *
     * @param string $ip Client IP address.
     */
    private function record_guest_rate_limit( $ip ) {
        $transient_key = 'rtg_rate_guest_' . md5( $ip );
        $attempts      = get_transient( $transient_key );

        if ( false === $attempts ) {
            set_transient( $transient_key, 1, self::RATE_LIMIT_WINDOW );
        } else {
            set_transient( $transient_key, (int) $attempts + 1, self::RATE_LIMIT_WINDOW );
        }
    }

    /**
     * Get reviews for a specific tire.
     * Available to both logged-in and logged-out users.
     * Nonce verified for logged-in users to prevent CSRF.
     */
    public function get_tire_reviews() {
        if ( is_user_logged_in() ) {
            if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
                wp_send_json_error( 'Security check failed.' );
            }
        }

        $tire_id = sanitize_text_field( $_POST['tire_id'] ?? '' );

        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        $page    = max( 1, intval( $_POST['page'] ?? 1 ) );
        $per_page = 10;
        $offset  = ( $page - 1 ) * $per_page;

        $reviews = RTG_Database::get_tire_reviews( $tire_id, $per_page, $offset );
        $total   = RTG_Database::get_tire_review_count( $tire_id );

        wp_send_json_success( array(
            'reviews'     => $reviews,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => ceil( $total / $per_page ),
        ) );
    }

    /**
     * Get all approved reviews by a specific user.
     * Nonce verified for logged-in users to prevent CSRF.
     */
    public function get_user_reviews() {
        if ( is_user_logged_in() ) {
            if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
                wp_send_json_error( 'Security check failed.' );
            }
        }

        $user_id = absint( $_POST['user_id'] ?? 0 );

        if ( ! $user_id ) {
            wp_send_json_error( 'Invalid user ID.' );
        }

        $page     = max( 1, intval( $_POST['page'] ?? 1 ) );
        $per_page = 10;
        $offset   = ( $page - 1 ) * $per_page;

        $reviews = RTG_Database::get_user_reviews( $user_id, $per_page, $offset );
        $total   = RTG_Database::get_user_review_count( $user_id );

        $user = get_user_by( 'ID', $user_id );

        wp_send_json_success( array(
            'reviews'      => $reviews,
            'total'        => $total,
            'page'         => $page,
            'total_pages'  => ceil( $total / $per_page ),
            'display_name' => $user ? $user->display_name : 'Anonymous',
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
            'tire_id'      => sanitize_text_field( $_POST['tire_id'] ?? '' ),
            'search'       => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
            'vehicle'      => sanitize_text_field( $_POST['vehicle'] ?? '' ),
            'size'         => sanitize_text_field( $_POST['size'] ?? '' ),
            'brand'        => sanitize_text_field( $_POST['brand'] ?? '' ),
            'category'     => sanitize_text_field( $_POST['category'] ?? '' ),
            'three_pms'    => ! empty( $_POST['three_pms'] ),
            'ev_rated'     => ! empty( $_POST['ev_rated'] ),
            'studded'      => ! empty( $_POST['studded'] ),
            'oem'          => ! empty( $_POST['oem'] ),
            'reviewed'     => ! empty( $_POST['reviewed'] ),
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
            'newest', 'roamer-efficiency',
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

        $db_sizes   = $wpdb->get_col( "SELECT DISTINCT size FROM {$table} WHERE size != '' ORDER BY size ASC" );
        $brands     = $wpdb->get_col( "SELECT DISTINCT brand FROM {$table} WHERE brand != '' ORDER BY brand ASC" );
        $categories = $wpdb->get_col( "SELECT DISTINCT category FROM {$table} WHERE category != '' ORDER BY category ASC" );

        // Merge admin-managed sizes with sizes found in the database.
        $admin_sizes = RTG_Admin::get_dropdown_options( 'sizes' );
        $merged_sizes = array_unique( array_merge( $admin_sizes, $db_sizes ) );
        sort( $merged_sizes );

        wp_send_json_success( array(
            'sizes'          => array_map( 'sanitize_text_field', array_values( $merged_sizes ) ),
            'brands'         => array_map( 'sanitize_text_field', $brands ),
            'categories'     => array_map( 'sanitize_text_field', $categories ),
            'vehicleSizeMap' => RTG_Database::get_vehicle_size_map(),
        ) );
    }

    /**
     * Get the current user's favorite tire IDs.
     */
    public function get_favorites() {
        if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in.' );
        }

        $favorites = RTG_Database::get_user_favorites( get_current_user_id() );

        wp_send_json_success( array(
            'favorites' => $favorites,
        ) );
    }

    /**
     * Add a tire to the current user's favorites.
     */
    public function add_favorite() {
        if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in.' );
        }

        $tire_id = sanitize_text_field( $_POST['tire_id'] ?? '' );

        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        // Verify the tire exists.
        $tire = RTG_Database::get_tire( $tire_id );
        if ( ! $tire ) {
            wp_send_json_error( 'Tire not found.' );
        }

        $result = RTG_Database::add_favorite( $tire_id, get_current_user_id() );

        if ( $result ) {
            wp_send_json_success( array( 'tire_id' => $tire_id ) );
        } else {
            wp_send_json_error( 'Failed to add favorite.' );
        }
    }

    /**
     * Remove a tire from the current user's favorites.
     */
    public function remove_favorite() {
        if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in.' );
        }

        $tire_id = sanitize_text_field( $_POST['tire_id'] ?? '' );

        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        $result = RTG_Database::remove_favorite( $tire_id, get_current_user_id() );

        if ( $result ) {
            wp_send_json_success( array( 'tire_id' => $tire_id ) );
        } else {
            wp_send_json_error( 'Failed to remove favorite.' );
        }
    }

    /**
     * Admin AJAX: Update tire links from the affiliate links dashboard.
     * Requires manage_options capability.
     */
    public function update_tire_links() {
        if ( ! check_ajax_referer( 'rtg_affiliate_links_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $post = wp_unslash( $_POST );

        $tire_id     = sanitize_text_field( $post['tire_id'] ?? '' );
        $link        = esc_url_raw( $post['link'] ?? '' );
        $bundle_link = esc_url_raw( $post['bundle_link'] ?? '' );
        $review_link = esc_url_raw( $post['review_link'] ?? '' );

        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        // Verify the tire exists.
        $tire = RTG_Database::get_tire( $tire_id );
        if ( ! $tire ) {
            wp_send_json_error( 'Tire not found.' );
        }

        $result = RTG_Database::update_tire_links( $tire_id, $link, $bundle_link, $review_link );

        if ( false !== $result ) {
            wp_send_json_success( array(
                'tire_id'     => $tire_id,
                'link'        => $link,
                'bundle_link' => $bundle_link,
                'review_link' => $review_link,
            ) );
        } else {
            wp_send_json_error( 'Failed to update links.' );
        }
    }

    // --- Analytics Tracking ---

    /**
     * Track an affiliate link click.
     * Available to both logged-in and logged-out users.
     */
    public function track_click() {
        if ( ! check_ajax_referer( 'rtg_analytics_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        $tire_id   = sanitize_text_field( $_POST['tire_id'] ?? '' );
        $link_type = sanitize_text_field( $_POST['link_type'] ?? 'purchase' );

        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        $allowed_types = array( 'purchase', 'review' );
        if ( ! in_array( $link_type, $allowed_types, true ) ) {
            $link_type = 'purchase';
        }

        RTG_Database::insert_click_event( $tire_id, $link_type );

        wp_send_json_success();
    }

    /**
     * Track a search/filter event.
     * Available to both logged-in and logged-out users.
     */
    public function track_search() {
        if ( ! check_ajax_referer( 'rtg_analytics_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        $search_query = sanitize_text_field( wp_unslash( $_POST['search_query'] ?? '' ) );
        $filters_json = sanitize_text_field( wp_unslash( $_POST['filters_json'] ?? '{}' ) );
        $sort_by      = sanitize_text_field( wp_unslash( $_POST['sort_by'] ?? '' ) );
        $result_count = intval( $_POST['result_count'] ?? 0 );
        $search_type  = sanitize_text_field( $_POST['search_type'] ?? 'search' );

        // Only allow known search types.
        if ( ! in_array( $search_type, array( 'search', 'ai' ), true ) ) {
            $search_type = 'search';
        }

        // Validate filters JSON.
        if ( strlen( $filters_json ) > 1000 ) {
            $filters_json = '{}';
        }
        json_decode( $filters_json );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $filters_json = '{}';
        }

        RTG_Database::insert_search_event( $search_query, $filters_json, $sort_by, $result_count, $search_type );

        wp_send_json_success();
    }

    /**
     * Calculate efficiency score and grade from tire spec data.
     * Uses the canonical PHP formula — eliminates the need for a duplicate JS implementation.
     * Admin-only endpoint for the tire edit form.
     */
    public function calculate_efficiency() {
        if ( ! check_ajax_referer( 'rtg_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $data = array(
            'size'         => sanitize_text_field( $_POST['size'] ?? '' ),
            'weight_lb'    => floatval( $_POST['weight_lb'] ?? 0 ),
            'tread'        => sanitize_text_field( $_POST['tread'] ?? '' ),
            'load_range'   => sanitize_text_field( $_POST['load_range'] ?? '' ),
            'speed_rating' => sanitize_text_field( $_POST['speed_rating'] ?? '' ),
            'utqg'         => sanitize_text_field( $_POST['utqg'] ?? '' ),
            'category'     => sanitize_text_field( $_POST['category'] ?? '' ),
            'three_pms'    => sanitize_text_field( $_POST['three_pms'] ?? 'No' ),
        );

        $result = RTG_Database::calculate_efficiency( $data );

        wp_send_json_success( $result );
    }

    /**
     * AI tire recommendation endpoint.
     * Available to both logged-in and logged-out users.
     */
    public function ai_recommend() {
        if ( ! check_ajax_referer( 'rtg_ai_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! RTG_AI::is_enabled() ) {
            wp_send_json_error( 'AI recommendations are not available.' );
        }

        $query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );

        if ( empty( trim( $query ) ) ) {
            wp_send_json_error( 'Please enter a search query.' );
        }

        if ( mb_strlen( $query ) > 500 ) {
            wp_send_json_error( 'Query is too long. Please keep it under 500 characters.' );
        }

        $result = RTG_AI::get_recommendations( $query );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        // Include full tire row data so the frontend can render cards for
        // tires that may not already be loaded (e.g. server-side pagination).
        $rec_ids = array_column( $result['recommendations'], 'tire_id' );
        $result['tire_rows'] = RTG_Database::get_tires_by_ids( $rec_ids );

        wp_send_json_success( $result );
    }

    /**
     * Run the affiliate link health check on demand.
     * Requires manage_options capability.
     */
    public function check_links() {
        if ( ! check_ajax_referer( 'rtg_affiliate_links_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        // Allow enough time for outbound HTTP requests (up to 50 links).
        set_time_limit( 300 );

        $results = RTG_Link_Checker::run();

        wp_send_json_success( $results );
    }

    /**
     * Start a batched link check — returns the list of tires to check.
     */
    public function check_links_start() {
        if ( ! check_ajax_referer( 'rtg_affiliate_links_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $tires = RTG_Link_Checker::get_linkable_tires();

        wp_send_json_success( array(
            'tires'      => $tires,
            'total'      => count( $tires ),
            'batch_size' => RTG_Link_Checker::PROGRESS_BATCH_SIZE,
        ) );
    }

    /**
     * Check a single batch of links by tire IDs.
     */
    public function check_links_batch() {
        if ( ! check_ajax_referer( 'rtg_affiliate_links_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $tires = isset( $_POST['tires'] ) ? $_POST['tires'] : array();
        if ( ! is_array( $tires ) || empty( $tires ) ) {
            wp_send_json_error( 'No tires provided.' );
        }

        // Sanitize the batch.
        $batch = array();
        foreach ( $tires as $tire ) {
            $batch[] = array(
                'tire_id' => sanitize_text_field( $tire['tire_id'] ?? '' ),
                'brand'   => sanitize_text_field( $tire['brand'] ?? '' ),
                'model'   => sanitize_text_field( $tire['model'] ?? '' ),
                'link'    => esc_url_raw( $tire['link'] ?? '' ),
            );
        }

        set_time_limit( 120 );

        $result = RTG_Link_Checker::check_batch( $batch );

        wp_send_json_success( $result );
    }

    /**
     * Finalize a batched link check — save results and send notification.
     */
    public function check_links_finish() {
        if ( ! check_ajax_referer( 'rtg_affiliate_links_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $total  = isset( $_POST['total'] ) ? absint( $_POST['total'] ) : 0;
        $broken = isset( $_POST['broken'] ) ? $_POST['broken'] : array();

        // Sanitize broken entries.
        $clean_broken = array();
        if ( is_array( $broken ) ) {
            foreach ( $broken as $entry ) {
                $clean_broken[] = array(
                    'tire_id' => sanitize_text_field( $entry['tire_id'] ?? '' ),
                    'brand'   => sanitize_text_field( $entry['brand'] ?? '' ),
                    'model'   => sanitize_text_field( $entry['model'] ?? '' ),
                    'url'     => esc_url_raw( $entry['url'] ?? '' ),
                    'status'  => sanitize_text_field( $entry['status'] ?? '' ),
                    'reason'  => sanitize_text_field( $entry['reason'] ?? '' ),
                    'http'    => absint( $entry['http'] ?? 0 ),
                );
            }
        }

        $results = RTG_Link_Checker::save_results( $total, $clean_broken );

        wp_send_json_success( $results );
    }

    /**
     * Get analytics data for the admin dashboard.
     * Requires manage_options capability.
     */
    public function get_analytics() {
        if ( ! check_ajax_referer( 'rtg_analytics_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $period = intval( $_POST['period'] ?? 30 );
        $data   = RTG_Database::get_analytics_data( $period );

        wp_send_json_success( $data );
    }

    /**
     * Trigger a Roamer sync immediately.
     */
    public function roamer_sync_now() {
        check_ajax_referer( 'rtg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $result = RTG_Roamer_Sync::run();
        wp_send_json_success( $result );
    }

    /**
     * Manually assign one or more Roamer tire IDs to a local tire.
     *
     * Accepts a single roamer_tire_id or a JSON array of roamer_tire_ids.
     * When multiple IDs are provided, efficiency is averaged weighted by
     * session count, and counts are summed.
     */
    public function roamer_assign() {
        check_ajax_referer( 'rtg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $tire_id = sanitize_text_field( $_POST['tire_id'] ?? '' );

        if ( empty( $tire_id ) || ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) ) {
            wp_send_json_error( 'Invalid or missing tire_id.' );
        }

        // Support single or multiple roamer IDs.
        $roamer_ids = array();
        if ( ! empty( $_POST['roamer_tire_ids'] ) ) {
            $raw = json_decode( stripslashes( $_POST['roamer_tire_ids'] ), true );
            if ( is_array( $raw ) ) {
                foreach ( $raw as $rid ) {
                    $clean = sanitize_text_field( $rid );
                    if ( ! empty( $clean ) ) {
                        $roamer_ids[] = $clean;
                    }
                }
            }
        } elseif ( ! empty( $_POST['roamer_tire_id'] ) ) {
            $roamer_ids[] = sanitize_text_field( $_POST['roamer_tire_id'] );
        }

        if ( empty( $roamer_ids ) ) {
            wp_send_json_error( 'Missing roamer_tire_id(s).' );
        }

        // Look up Roamer data from stored sync stats.
        $stats      = get_option( RTG_Roamer_Sync::STATS_OPTION, array() );
        $all_roamer = array_merge(
            $stats['ambiguous_list'] ?? array(),
            $stats['unmatched_list'] ?? array()
        );

        // Build map of roamer_tire_id => data.
        $roamer_map = array();
        foreach ( $all_roamer as $entry ) {
            $roamer_map[ $entry['roamer_tire_id'] ] = $entry;
        }

        if ( count( $roamer_ids ) === 1 ) {
            // Single assignment.
            $rid = $roamer_ids[0];
            $update = array( 'roamer_tire_id' => $rid );

            // Pull efficiency data from stats if available.
            if ( isset( $roamer_map[ $rid ] ) ) {
                $update['roamer_efficiency']    = floatval( $roamer_map[ $rid ]['efficiency'] ?? 0 );
                $update['roamer_session_count'] = intval( $roamer_map[ $rid ]['session_count'] ?? 0 );
                $update['roamer_synced_at']     = current_time( 'mysql' );
            }

            $result = RTG_Database::update_tire( $tire_id, $update );
        } else {
            // Multiple assignment — weighted average by session count.
            $total_sessions  = 0;
            $weighted_eff    = 0;
            $total_vehicles  = 0;
            $total_km        = 0;
            $id_parts        = array();

            foreach ( $roamer_ids as $rid ) {
                if ( isset( $roamer_map[ $rid ] ) ) {
                    $eff     = floatval( $roamer_map[ $rid ]['efficiency'] ?? 0 );
                    $sess    = intval( $roamer_map[ $rid ]['session_count'] ?? 0 );
                    $weighted_eff   += $eff * $sess;
                    $total_sessions += $sess;
                    $total_vehicles += intval( $roamer_map[ $rid ]['vehicle_count'] ?? 0 );
                    $total_km       += floatval( $roamer_map[ $rid ]['total_km'] ?? 0 );
                }
                $id_parts[] = $rid;
            }

            $avg_eff = $total_sessions > 0 ? $weighted_eff / $total_sessions : 0;

            $result = RTG_Database::update_tire( $tire_id, array(
                'roamer_tire_id'       => implode( ',', $id_parts ),
                'roamer_efficiency'    => round( $avg_eff, 2 ),
                'roamer_session_count' => $total_sessions,
                'roamer_total_km'      => $total_km,
                'roamer_vehicle_count' => $total_vehicles,
                'roamer_synced_at'     => current_time( 'mysql' ),
            ) );
        }

        if ( false === $result ) {
            wp_send_json_error( 'Failed to update tire.' );
        }

        // Remove assigned IDs from stored sync stats so they don't persist.
        if ( ! empty( $stats ) ) {
            $assigned_set = array_flip( $roamer_ids );

            if ( ! empty( $stats['ambiguous_list'] ) ) {
                $stats['ambiguous_list'] = array_values( array_filter(
                    $stats['ambiguous_list'],
                    function ( $item ) use ( $assigned_set ) {
                        return ! isset( $assigned_set[ $item['roamer_tire_id'] ] );
                    }
                ) );
            }

            if ( ! empty( $stats['unmatched_list'] ) ) {
                $stats['unmatched_list'] = array_values( array_filter(
                    $stats['unmatched_list'],
                    function ( $item ) use ( $assigned_set ) {
                        return ! isset( $assigned_set[ $item['roamer_tire_id'] ] );
                    }
                ) );
            }

            update_option( RTG_Roamer_Sync::STATS_OPTION, $stats, false );
        }

        wp_send_json_success( array( 'updated' => $result ) );
    }

    /**
     * Unlink a Roamer tire ID from a local tire.
     */
    public function roamer_unlink() {
        check_ajax_referer( 'rtg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $tire_id = sanitize_text_field( $_POST['tire_id'] ?? '' );

        if ( empty( $tire_id ) ) {
            wp_send_json_error( 'Missing tire_id.' );
        }

        $result = RTG_Database::update_tire( $tire_id, array(
            'roamer_tire_id'       => '',
            'roamer_efficiency'    => 0,
            'roamer_session_count' => 0,
            'roamer_total_km'      => 0,
            'roamer_vehicle_count' => 0,
            'roamer_synced_at'     => null,
        ) );

        if ( false === $result ) {
            wp_send_json_error( 'Failed to unlink tire.' );
        }

        wp_send_json_success( array( 'unlinked' => $tire_id ) );
    }

    /**
     * Permanently hide one or more unmatched Roamer tires.
     *
     * Hidden IDs are stored in a dedicated option and excluded from
     * future syncs and the admin unmatched list.
     */
    public function roamer_hide() {
        check_ajax_referer( 'rtg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $roamer_ids = array();
        if ( ! empty( $_POST['roamer_tire_ids'] ) ) {
            $raw = json_decode( stripslashes( $_POST['roamer_tire_ids'] ), true );
            if ( is_array( $raw ) ) {
                foreach ( $raw as $rid ) {
                    $clean = sanitize_text_field( $rid );
                    if ( ! empty( $clean ) ) {
                        $roamer_ids[] = $clean;
                    }
                }
            }
        }

        if ( empty( $roamer_ids ) ) {
            wp_send_json_error( 'Missing roamer_tire_ids.' );
        }

        // Append to the persistent hidden list.
        $hidden = get_option( RTG_Roamer_Sync::HIDDEN_OPTION, array() );
        if ( ! is_array( $hidden ) ) {
            $hidden = array();
        }
        $hidden = array_unique( array_merge( $hidden, $roamer_ids ) );
        update_option( RTG_Roamer_Sync::HIDDEN_OPTION, $hidden, false );

        // Remove hidden IDs from stored sync stats so they disappear immediately.
        $stats = get_option( RTG_Roamer_Sync::STATS_OPTION, array() );
        if ( ! empty( $stats ) ) {
            $hidden_set = array_flip( $roamer_ids );

            if ( ! empty( $stats['unmatched_list'] ) ) {
                $stats['unmatched_list'] = array_values( array_filter(
                    $stats['unmatched_list'],
                    function ( $item ) use ( $hidden_set ) {
                        return ! isset( $hidden_set[ $item['roamer_tire_id'] ] );
                    }
                ) );
            }

            if ( ! empty( $stats['ambiguous_list'] ) ) {
                $stats['ambiguous_list'] = array_values( array_filter(
                    $stats['ambiguous_list'],
                    function ( $item ) use ( $hidden_set ) {
                        return ! isset( $hidden_set[ $item['roamer_tire_id'] ] );
                    }
                ) );
            }

            update_option( RTG_Roamer_Sync::STATS_OPTION, $stats, false );
        }

        wp_send_json_success( array( 'hidden' => $roamer_ids ) );
    }

    /**
     * Restore previously hidden Roamer tire IDs.
     *
     * Removes IDs from the hidden list so they reappear on the next sync.
     */
    public function roamer_restore() {
        check_ajax_referer( 'rtg_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $roamer_ids = array();
        if ( ! empty( $_POST['roamer_tire_ids'] ) ) {
            $raw = json_decode( stripslashes( $_POST['roamer_tire_ids'] ), true );
            if ( is_array( $raw ) ) {
                foreach ( $raw as $rid ) {
                    $clean = sanitize_text_field( $rid );
                    if ( ! empty( $clean ) ) {
                        $roamer_ids[] = $clean;
                    }
                }
            }
        }

        if ( empty( $roamer_ids ) ) {
            wp_send_json_error( 'Missing roamer_tire_ids.' );
        }

        $hidden = get_option( RTG_Roamer_Sync::HIDDEN_OPTION, array() );
        if ( ! is_array( $hidden ) ) {
            $hidden = array();
        }

        $restore_set = array_flip( $roamer_ids );
        $hidden = array_values( array_filter( $hidden, function ( $id ) use ( $restore_set ) {
            return ! isset( $restore_set[ $id ] );
        } ) );

        update_option( RTG_Roamer_Sync::HIDDEN_OPTION, $hidden, false );

        wp_send_json_success( array( 'restored' => $roamer_ids ) );
    }
}
