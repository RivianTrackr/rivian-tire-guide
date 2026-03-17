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

        // Review helpfulness voting — public.
        add_action( 'wp_ajax_rtg_vote_review', array( $this, 'vote_review' ) );
        add_action( 'wp_ajax_nopriv_rtg_vote_review', array( $this, 'vote_review' ) );

        // Price history — public.
        add_action( 'wp_ajax_rtg_get_price_history', array( $this, 'get_price_history' ) );
        add_action( 'wp_ajax_nopriv_rtg_get_price_history', array( $this, 'get_price_history' ) );
    }

    /**
     * Get ratings for an array of tire IDs.
     * Available to both logged-in and logged-out users.
     * Nonce verified for logged-in users; open for public reads.
     */
    public function get_tire_ratings() {
        // Verify nonce for logged-in users; verify origin for anonymous users.
        if ( is_user_logged_in() ) {
            if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
                wp_send_json_error( 'Security check failed.' );
            }
        } elseif ( ! RTG_Security::verify_origin() ) {
            wp_send_json_error( 'Invalid request origin.' );
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

        // Rate limiting: max submissions per window per user.
        if ( RTG_Rate_Limiter::is_user_limited( 'review', $user_id, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW ) ) {
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
        RTG_Rate_Limiter::record_user( 'review', $user_id, self::RATE_LIMIT_WINDOW );

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

        // IP-based rate limiting for guests using shared rate limiter.
        if ( RTG_Rate_Limiter::is_limited( 'guest_review', self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW ) ) {
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
        RTG_Rate_Limiter::record( 'guest_review', self::RATE_LIMIT_WINDOW );

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

    // Guest rate limiting now handled by RTG_Rate_Limiter.

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
        } elseif ( ! RTG_Security::verify_origin() ) {
            wp_send_json_error( 'Invalid request origin.' );
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
        } elseif ( ! RTG_Security::verify_origin() ) {
            wp_send_json_error( 'Invalid request origin.' );
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

    // User rate limiting now handled by RTG_Rate_Limiter.

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
            'vehicle'      => sanitize_text_field( $_POST['vehicle'] ?? '' ),
            'size'         => sanitize_text_field( $_POST['size'] ?? '' ),
            'brand'        => sanitize_text_field( $_POST['brand'] ?? '' ),
            'category'     => sanitize_text_field( $_POST['category'] ?? '' ),
            'three_pms'    => ! empty( $_POST['three_pms'] ),
            'ev_rated'     => ! empty( $_POST['ev_rated'] ),
            'studded'      => ! empty( $_POST['studded'] ),
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
     * Cast a helpfulness vote on a review.
     * Available to both logged-in and logged-out users.
     */
    public function vote_review() {
        if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
            if ( ! RTG_Security::verify_origin() ) {
                wp_send_json_error( 'Security check failed.' );
            }
        }

        $review_id = absint( $_POST['review_id'] ?? 0 );
        $vote      = intval( $_POST['vote'] ?? 0 );

        if ( ! $review_id || ! in_array( $vote, array( 1, -1 ), true ) ) {
            wp_send_json_error( 'Invalid vote data.' );
        }

        // Rate limit voting: 20 votes per 5 minutes per IP.
        if ( RTG_Rate_Limiter::is_limited( 'vote', 20, 300 ) ) {
            wp_send_json_error( 'Too many votes. Please wait a moment.' );
        }

        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $result  = RTG_Database::cast_review_vote( $review_id, $vote, $user_id );

        if ( $result ) {
            RTG_Rate_Limiter::record( 'vote', 300 );
            wp_send_json_success( array( 'voted' => true ) );
        } else {
            wp_send_json_error( 'You have already voted on this review.' );
        }
    }

    /**
     * Get price history for a tire.
     * Available to both logged-in and logged-out users.
     */
    public function get_price_history() {
        if ( is_user_logged_in() ) {
            if ( ! check_ajax_referer( 'tire_rating_nonce', 'nonce', false ) ) {
                wp_send_json_error( 'Security check failed.' );
            }
        } elseif ( ! RTG_Security::verify_origin() ) {
            wp_send_json_error( 'Invalid request origin.' );
        }

        $tire_id = sanitize_text_field( $_POST['tire_id'] ?? '' );

        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $tire_id ) || strlen( $tire_id ) > 50 ) {
            wp_send_json_error( 'Invalid tire ID.' );
        }

        $history = RTG_Database::get_price_history( $tire_id, 30 );
        $trend   = RTG_Database::get_price_trend( $tire_id );

        wp_send_json_success( array(
            'history' => $history,
            'trend'   => $trend,
        ) );
    }
}
