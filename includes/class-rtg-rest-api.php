<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API endpoints for the Rivian Tire Guide plugin.
 *
 * Registers public read-only endpoints under the rtg/v1 namespace
 * for listing tires, fetching individual tire data, retrieving reviews,
 * and calculating efficiency scores.
 *
 * @since 1.14.0
 */
class RTG_REST_API {

    /**
     * API namespace.
     *
     * @var string
     */
    const NAMESPACE = 'rtg/v1';

    /**
     * Hook into rest_api_init to register routes.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register all REST API routes.
     */
    public function register_routes() {

        // GET /wp-json/rtg/v1/tires — List tires with filtering, sorting, pagination.
        register_rest_route(
            self::NAMESPACE,
            '/tires',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_tires' ),
                'permission_callback' => '__return_true',
                'args'                => $this->get_tires_collection_params(),
            )
        );

        // GET /wp-json/rtg/v1/tires/{tire_id} — Get a single tire.
        register_rest_route(
            self::NAMESPACE,
            '/tires/(?P<tire_id>[a-zA-Z0-9\-_]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_tire' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'tire_id' => array(
                        'description'       => 'Unique tire identifier.',
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => array( $this, 'validate_tire_id' ),
                    ),
                ),
            )
        );

        // GET /wp-json/rtg/v1/tires/{tire_id}/reviews — Get reviews for a tire.
        register_rest_route(
            self::NAMESPACE,
            '/tires/(?P<tire_id>[a-zA-Z0-9\-_]+)/reviews',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_tire_reviews' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'tire_id'  => array(
                        'description'       => 'Unique tire identifier.',
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => array( $this, 'validate_tire_id' ),
                    ),
                    'page'     => array(
                        'description'       => 'Current page of the review collection.',
                        'type'              => 'integer',
                        'default'           => 1,
                        'minimum'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'per_page' => array(
                        'description'       => 'Maximum number of reviews per page.',
                        'type'              => 'integer',
                        'default'           => 10,
                        'minimum'           => 1,
                        'maximum'           => 50,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // POST /wp-json/rtg/v1/efficiency — Calculate efficiency score.
        register_rest_route(
            self::NAMESPACE,
            '/efficiency',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'calculate_efficiency' ),
                'permission_callback' => '__return_true',
                'args'                => $this->get_efficiency_params(),
            )
        );
    }

    // ------------------------------------------------------------------
    // Endpoint callbacks
    // ------------------------------------------------------------------

    /**
     * GET /tires — Return a paginated, filterable list of tires.
     *
     * @param WP_REST_Request $request Full request object.
     * @return WP_REST_Response
     */
    public function get_tires( WP_REST_Request $request ) {

        $filters = array();

        $size = $request->get_param( 'size' );
        if ( ! empty( $size ) ) {
            $filters['size'] = sanitize_text_field( $size );
        }

        $brand = $request->get_param( 'brand' );
        if ( ! empty( $brand ) ) {
            $filters['brand'] = sanitize_text_field( $brand );
        }

        $category = $request->get_param( 'category' );
        if ( ! empty( $category ) ) {
            $filters['category'] = sanitize_text_field( $category );
        }

        $three_pms = $request->get_param( 'three_pms' );
        if ( $three_pms ) {
            $filters['three_pms'] = true;
        }

        $sort     = sanitize_text_field( $request->get_param( 'sort' ) );
        $page     = absint( $request->get_param( 'page' ) );
        $per_page = absint( $request->get_param( 'per_page' ) );

        // Validate sort value.
        $allowed_sorts = array(
            'efficiency_score',
            'price-asc',
            'price-desc',
            'warranty-desc',
            'weight-asc',
            'newest',
        );
        if ( ! in_array( $sort, $allowed_sorts, true ) ) {
            $sort = 'efficiency_score';
        }

        $page     = max( 1, $page );
        $per_page = max( 1, min( 100, $per_page ) );

        $result      = RTG_Database::get_filtered_tires( $filters, $sort, $page, $per_page );
        $total       = (int) $result['total'];
        $total_pages = (int) ceil( $total / $per_page );

        $response = new WP_REST_Response(
            array(
                'tires'       => $result['rows'],
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => $total_pages,
            ),
            200
        );

        // Standard pagination headers.
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', $total_pages );

        return $response;
    }

    /**
     * GET /tires/{tire_id} — Return a single tire with its rating data.
     *
     * @param WP_REST_Request $request Full request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_tire( WP_REST_Request $request ) {
        $tire_id = sanitize_text_field( $request->get_param( 'tire_id' ) );

        $tire = RTG_Database::get_tire( $tire_id );

        if ( ! $tire ) {
            return new WP_Error(
                'rtg_tire_not_found',
                'Tire not found.',
                array( 'status' => 404 )
            );
        }

        // Fetch rating aggregates for this tire.
        $ratings_data = RTG_Database::get_tire_ratings( array( $tire_id ) );
        $rating       = isset( $ratings_data[ $tire_id ] )
            ? $ratings_data[ $tire_id ]
            : array(
                'average'      => 0,
                'count'        => 0,
                'review_count' => 0,
            );

        $tire['rating'] = $rating;

        return new WP_REST_Response( $tire, 200 );
    }

    /**
     * GET /tires/{tire_id}/reviews — Return paginated reviews for a tire.
     *
     * @param WP_REST_Request $request Full request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_tire_reviews( WP_REST_Request $request ) {
        $tire_id  = sanitize_text_field( $request->get_param( 'tire_id' ) );
        $page     = absint( $request->get_param( 'page' ) );
        $per_page = absint( $request->get_param( 'per_page' ) );

        $page     = max( 1, $page );
        $per_page = max( 1, min( 50, $per_page ) );

        // Verify the tire exists.
        $tire = RTG_Database::get_tire( $tire_id );
        if ( ! $tire ) {
            return new WP_Error(
                'rtg_tire_not_found',
                'Tire not found.',
                array( 'status' => 404 )
            );
        }

        $offset  = ( $page - 1 ) * $per_page;
        $reviews = RTG_Database::get_tire_reviews( $tire_id, $per_page, $offset );
        $total   = RTG_Database::get_tire_review_count( $tire_id );

        $total_pages = (int) ceil( $total / $per_page );

        $response = new WP_REST_Response(
            array(
                'reviews'     => $reviews,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => $total_pages,
            ),
            200
        );

        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', $total_pages );

        return $response;
    }

    /**
     * POST /efficiency — Calculate an efficiency score from tire spec data.
     *
     * @param WP_REST_Request $request Full request object.
     * @return WP_REST_Response
     */
    public function calculate_efficiency( WP_REST_Request $request ) {
        $data = array(
            'size'          => sanitize_text_field( $request->get_param( 'size' ) ),
            'weight_lb'     => floatval( $request->get_param( 'weight_lb' ) ),
            'tread'         => sanitize_text_field( $request->get_param( 'tread' ) ),
            'load_range'    => sanitize_text_field( $request->get_param( 'load_range' ) ),
            'speed_rating'  => sanitize_text_field( $request->get_param( 'speed_rating' ) ),
            'utqg'          => sanitize_text_field( $request->get_param( 'utqg' ) ),
            'category'      => sanitize_text_field( $request->get_param( 'category' ) ),
            'three_pms'     => sanitize_text_field( $request->get_param( 'three_pms' ) ),
        );

        $result = RTG_Database::calculate_efficiency( $data );

        return new WP_REST_Response(
            array(
                'efficiency_score' => $result['efficiency_score'],
                'efficiency_grade' => $result['efficiency_grade'],
            ),
            200
        );
    }

    // ------------------------------------------------------------------
    // Validation helpers
    // ------------------------------------------------------------------

    /**
     * Validate a tire_id URL parameter.
     *
     * @param string          $value   Parameter value.
     * @param WP_REST_Request $request Full request object.
     * @param string          $param   Parameter name.
     * @return true|WP_Error
     */
    public function validate_tire_id( $value, $request, $param ) {
        if ( ! is_string( $value ) || strlen( $value ) > 50 ) {
            return new WP_Error(
                'rtg_invalid_tire_id',
                'Tire ID must be a string of 50 characters or fewer.',
                array( 'status' => 400 )
            );
        }

        if ( ! preg_match( '/^[a-zA-Z0-9\-_]+$/', $value ) ) {
            return new WP_Error(
                'rtg_invalid_tire_id',
                'Tire ID contains invalid characters. Only alphanumeric characters, hyphens, and underscores are allowed.',
                array( 'status' => 400 )
            );
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Parameter definitions
    // ------------------------------------------------------------------

    /**
     * Get the query parameter definitions for the tires collection endpoint.
     *
     * @return array
     */
    private function get_tires_collection_params() {
        return array(
            'size'      => array(
                'description'       => 'Filter by exact tire size (e.g. 275/60R20).',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'brand'     => array(
                'description'       => 'Filter by exact brand name.',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'category'  => array(
                'description'       => 'Filter by tire category (e.g. All-Season, All-Terrain).',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'three_pms' => array(
                'description'       => 'Filter to only 3-Peak Mountain Snowflake rated tires.',
                'type'              => 'boolean',
                'default'           => false,
            ),
            'sort'      => array(
                'description'       => 'Sort order for results.',
                'type'              => 'string',
                'default'           => 'efficiency_score',
                'enum'              => array(
                    'efficiency_score',
                    'price-asc',
                    'price-desc',
                    'warranty-desc',
                    'weight-asc',
                    'newest',
                ),
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'page'      => array(
                'description'       => 'Current page of the collection.',
                'type'              => 'integer',
                'default'           => 1,
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page'  => array(
                'description'       => 'Maximum number of tires per page.',
                'type'              => 'integer',
                'default'           => 12,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ),
        );
    }

    /**
     * Get the parameter definitions for the efficiency calculation endpoint.
     *
     * @return array
     */
    private function get_efficiency_params() {
        return array(
            'size'         => array(
                'description'       => 'Tire size string (e.g. 275/60R20).',
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'weight_lb'    => array(
                'description'       => 'Tire weight in pounds.',
                'type'              => 'number',
                'default'           => 0,
                'sanitize_callback' => 'floatval',
            ),
            'tread'        => array(
                'description'       => 'Tread depth (e.g. 10/32).',
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'load_range'   => array(
                'description'       => 'Load range rating (e.g. SL, XL, E).',
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'speed_rating' => array(
                'description'       => 'Speed rating letter (e.g. T, H, V).',
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'utqg'         => array(
                'description'       => 'UTQG rating string (e.g. 620 A B).',
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'category'     => array(
                'description'       => 'Tire category (e.g. All-Season, All-Terrain, Winter).',
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'three_pms'    => array(
                'description'       => '3-Peak Mountain Snowflake certification (Yes or No).',
                'type'              => 'string',
                'default'           => 'No',
                'enum'              => array( 'Yes', 'No' ),
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }
}
