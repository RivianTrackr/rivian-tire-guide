<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI Tire Advisor (2.0).
 *
 * Three things a model can do that the filter engine cannot, each grounded
 * in the live catalog so it can never recommend a tire that isn't in the
 * guide or doesn't fit the vehicle:
 *
 *  1. "Help me choose": the visitor picks their Rivian, what matters to them
 *     and a budget; the plugin ranks the fitting tires with its own rules,
 *     hands the top candidates to Claude, and gets back three picks with a
 *     reason and an honest trade-off each. Without an API key the same flow
 *     returns the rules' picks with templated reasons, so the button is
 *     never dead.
 *  2. "What owners say": a cached summary of a tire's reviews on its page.
 *  3. "In plain words": a paragraph comparing the tires on the compare page.
 *
 * The client is raw HTTP over wp_remote_post(), the plugin's one convention
 * for outbound calls (no Composer, no vendor directory). Every failure path
 * returns the same array shape and never throws; the last outcome is stored
 * for the settings page. Answers are cached in rtg_ai_* transients keyed on
 * the inputs, the catalog version and the model, so an edit to a tire or a
 * new review refreshes them and uninstall sweeps them.
 *
 * The pure parts (ranking, prompt building, response validation, request
 * body) are static and free of WordPress calls, pinned by
 * tests/contract/advisor.php; the orchestration around them is thin.
 */
class RTG_Advisor {

    const ENDPOINT        = 'https://api.anthropic.com/v1/messages';
    const API_VERSION     = '2023-06-01';
    const FALLBACK_BETA   = 'server-side-fallback-2026-07-01';
    const REQUEST_TIMEOUT = 60;

    const DEFAULT_MODEL      = 'claude-opus-5';
    const DEFAULT_RATE_LIMIT = 10;
    const STATE_OPTION       = 'rtg_ai_state';
    const CACHE_PREFIX       = 'rtg_ai_';

    const CANDIDATE_LIMIT        = 12;
    const PICK_COUNT             = 3;
    const MIN_REVIEWS_FOR_SUMMARY = 2;
    const REVIEWS_FOR_SUMMARY    = 40;
    const MAX_COMPARE            = 4;

    /** Models the settings page offers. The key is the exact API model ID. */
    const MODELS = array(
        'claude-opus-5'    => 'Claude Opus 5 (best advice)',
        'claude-sonnet-5'  => 'Claude Sonnet 5 (faster, cheaper)',
        'claude-haiku-4-5' => 'Claude Haiku 4.5 (cheapest)',
    );

    /** What the visitor can say matters. Key => label on the form. */
    const PRIORITIES = array(
        'efficiency' => 'Range and efficiency',
        'price'      => 'Price',
        'quiet'      => 'Quiet, comfortable ride',
        'winter'     => 'Snow and winter',
        'offroad'    => 'Off-road and trails',
        'towing'     => 'Towing and heavy loads',
        'treadlife'  => 'Long tread life',
    );

    /** One word per priority, for the analytics line and the rules summary. */
    const PRIORITY_SHORT = array(
        'efficiency' => 'range',
        'price'      => 'price',
        'quiet'      => 'quiet',
        'winter'     => 'snow',
        'offroad'    => 'off-road',
        'towing'     => 'towing',
        'treadlife'  => 'tread life',
    );

    /** Per-tire budget ceilings. Key is the ceiling in dollars, '' is any. */
    const BUDGETS = array(
        ''    => 'Any budget',
        '250' => 'Under $250 per tire',
        '350' => 'Under $350 per tire',
        '450' => 'Under $450 per tire',
    );

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    /**
     * The key: a wp-config.php constant when defined (never shown or saved),
     * otherwise the settings field. Same pattern as the CJ token.
     */
    public static function api_key() {
        if ( defined( 'RTG_ANTHROPIC_API_KEY' ) && '' !== (string) RTG_ANTHROPIC_API_KEY ) {
            return (string) RTG_ANTHROPIC_API_KEY;
        }
        $settings = get_option( 'rtg_settings', array() );
        return trim( (string) ( $settings['ai_api_key'] ?? '' ) );
    }

    public static function key_is_constant() {
        return defined( 'RTG_ANTHROPIC_API_KEY' ) && '' !== (string) RTG_ANTHROPIC_API_KEY;
    }

    public static function has_key() {
        return '' !== self::api_key();
    }

    /** The advisor toggle. On by default: without a key it runs on rules. */
    public static function is_enabled() {
        $settings = get_option( 'rtg_settings', array() );
        return ! isset( $settings['ai_enabled'] ) || ! empty( $settings['ai_enabled'] );
    }

    /** Written advice needs both the toggle and a key. */
    public static function is_live() {
        return self::is_enabled() && self::has_key();
    }

    public static function model() {
        $settings = get_option( 'rtg_settings', array() );
        $model    = (string) ( $settings['ai_model'] ?? '' );
        return isset( self::MODELS[ $model ] ) ? $model : self::DEFAULT_MODEL;
    }

    /** Requests per visitor per minute for the advise route. */
    public static function rate_limit() {
        $settings = get_option( 'rtg_settings', array() );
        $limit    = intval( $settings['ai_rate_limit'] ?? self::DEFAULT_RATE_LIMIT );
        return max( 1, min( 60, $limit ?: self::DEFAULT_RATE_LIMIT ) );
    }

    /** The last call's outcome, for the settings page. */
    public static function state() {
        $state = get_option( self::STATE_OPTION, array() );
        return is_array( $state ) ? $state : array();
    }

    private static function record_state( $status, $message, $extra = array() ) {
        update_option( self::STATE_OPTION, array_merge( array(
            'status'  => $status,
            'message' => $message,
            'time'    => current_time( 'mysql' ),
            'model'   => self::model(),
        ), $extra ), false );
    }

    // ------------------------------------------------------------------
    // Routes
    // ------------------------------------------------------------------

    public function register_routes() {
        register_rest_route( RTG_REST_API::NAMESPACE, '/advise', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_advise' ),
            'permission_callback' => '__return_true',
        ) );
        register_rest_route( RTG_REST_API::NAMESPACE, '/tires/(?P<tire_id>[a-zA-Z0-9\-_]+)/review-summary', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_review_summary' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'tire_id' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );
        register_rest_route( RTG_REST_API::NAMESPACE, '/compare-summary', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_compare_summary' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'ids' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );
    }

    public static function advise_url() {
        return rest_url( RTG_REST_API::NAMESPACE . '/advise' );
    }

    public static function review_summary_url( $tire_id ) {
        return rest_url( RTG_REST_API::NAMESPACE . '/tires/' . rawurlencode( $tire_id ) . '/review-summary' );
    }

    public static function compare_summary_url() {
        return rest_url( RTG_REST_API::NAMESPACE . '/compare-summary' );
    }

    private static function throttled( $bucket, $limit ) {
        return RTG_Rate_Limiter::hit( 'rest_ai_' . $bucket, RTG_Rate_Limiter::fingerprint(), $limit, MINUTE_IN_SECONDS );
    }

    private static function rate_limited_response() {
        return new WP_REST_Response( array( 'ok' => false, 'error' => 'Too many requests. Give it a minute and try again.' ), 429 );
    }

    /** POST /advise: the "Help me choose" flow. */
    public function rest_advise( WP_REST_Request $request ) {
        if ( ! self::is_enabled() ) {
            return new WP_REST_Response( array( 'ok' => false, 'error' => 'The advisor is turned off.' ), 404 );
        }
        if ( self::throttled( 'advise', self::rate_limit() ) ) {
            return self::rate_limited_response();
        }

        $input  = self::normalize_input( (array) $request->get_json_params() );
        $result = self::advise( $input );

        // The query for the analytics page: what was asked, how many picks came back.
        RTG_Database::insert_search_event(
            self::describe_request( $input ),
            wp_json_encode( $input ),
            '',
            count( $result['picks'] ),
            'ai'
        );

        $response = new WP_REST_Response( $result, 200 );
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }

    /** GET /tires/{id}/review-summary: "What owners say". */
    public function rest_review_summary( WP_REST_Request $request ) {
        if ( ! self::is_live() ) {
            return new WP_REST_Response( array( 'ok' => false, 'error' => 'Not available.' ), 404 );
        }
        $tire = RTG_Database::get_tire( $request['tire_id'] );
        if ( ! $tire ) {
            return new WP_REST_Response( array( 'ok' => false, 'error' => 'Unknown tire.' ), 404 );
        }
        if ( self::throttled( 'reviews', 20 ) ) {
            return self::rate_limited_response();
        }
        $result   = self::review_summary( $tire );
        $response = new WP_REST_Response( $result, 200 );
        $response->header( 'Cache-Control', $result['ok'] ? 'public, max-age=3600' : 'no-store' );
        return $response;
    }

    /** GET /compare-summary?ids=a,b: "In plain words". */
    public function rest_compare_summary( WP_REST_Request $request ) {
        if ( ! self::is_live() ) {
            return new WP_REST_Response( array( 'ok' => false, 'error' => 'Not available.' ), 404 );
        }
        $ids = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', (string) $request['ids'] ) ) ) ) );
        $ids = array_slice( $ids, 0, self::MAX_COMPARE );
        if ( count( $ids ) < 2 ) {
            return new WP_REST_Response( array( 'ok' => false, 'error' => 'Two to four tires, please.' ), 400 );
        }
        $tires = array();
        foreach ( $ids as $id ) {
            $tire = RTG_Database::get_tire( $id );
            if ( $tire ) {
                $tires[] = $tire;
            }
        }
        if ( count( $tires ) < 2 ) {
            return new WP_REST_Response( array( 'ok' => false, 'error' => 'Unknown tires.' ), 404 );
        }
        if ( self::throttled( 'compare', 20 ) ) {
            return self::rate_limited_response();
        }
        $result   = self::compare_summary( $tires );
        $response = new WP_REST_Response( $result, 200 );
        $response->header( 'Cache-Control', $result['ok'] ? 'public, max-age=3600' : 'no-store' );
        return $response;
    }

    // ------------------------------------------------------------------
    // Help me choose
    // ------------------------------------------------------------------

    /**
     * Clean the form input. Unknown priorities and budgets are dropped, the
     * notes are capped, and the vehicle is left for the caller to check
     * against the size map (the map is data, not a constant).
     */
    public static function normalize_input( $raw ) {
        $priorities = array();
        foreach ( (array) ( $raw['priorities'] ?? array() ) as $p ) {
            $p = strtolower( trim( (string) $p ) );
            if ( isset( self::PRIORITIES[ $p ] ) && ! in_array( $p, $priorities, true ) ) {
                $priorities[] = $p;
            }
        }
        $budget = (string) ( $raw['budget'] ?? '' );
        if ( ! isset( self::BUDGETS[ $budget ] ) ) {
            $budget = '';
        }
        $notes = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) ( $raw['notes'] ?? '' ) ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- must also run without WordPress (tests/contract)
        if ( function_exists( 'mb_substr' ) ) {
            $notes = mb_substr( $notes, 0, 300 );
        } else {
            $notes = substr( $notes, 0, 300 );
        }
        return array(
            'vehicle'    => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $raw['vehicle'] ?? '' ) ) ),
            'size'       => trim( (string) ( $raw['size'] ?? '' ) ),
            'priorities' => array_slice( $priorities, 0, 3 ),
            'budget'     => $budget,
            'notes'      => $notes,
        );
    }

    /** "R2 · range, quiet · under $350" for the analytics page. */
    public static function describe_request( $input ) {
        $parts = array();
        $parts[] = $input['vehicle'] ? $input['vehicle'] . ( $input['size'] ? ' ' . $input['size'] : '' ) : 'Any Rivian';
        $labels  = array();
        foreach ( $input['priorities'] as $p ) {
            $labels[] = self::PRIORITY_SHORT[ $p ];
        }
        $parts[] = $labels ? implode( ', ', $labels ) : 'no priorities';
        if ( '' !== $input['budget'] ) {
            $parts[] = 'under $' . $input['budget'];
        }
        return implode( ' · ', $parts );
    }

    /** Roamer sample too thin to lean on: the same rule the cards use. */
    public static function limited_sample( $tire ) {
        $vehicles = (int) ( $tire['roamer_vehicle_count'] ?? 0 );
        $miles    = (float) ( $tire['roamer_total_km'] ?? 0 ) * 0.621371;
        return $vehicles < 3 || $miles < 2000;
    }

    /**
     * The fields the model sees for one tire: numbers, no links, no prose.
     * Also what the rules score on and what the picks carry back to the UI.
     */
    public static function compact_tire( $tire, $size_map, $floors, $vehicle = '' ) {
        $size     = (string) ( $tire['size'] ?? '' );
        $vehicles = array();
        foreach ( (array) $size_map as $v => $sizes ) {
            if ( in_array( $size, (array) $sizes, true ) ) {
                $vehicles[] = $v;
            }
        }
        $load_index = RTG_Fitment::parse_load_index( $tire['load_index'] ?? '' );
        $floor      = $vehicle && isset( $floors[ $vehicle ] ) ? (int) $floors[ $vehicle ] : 0;
        $fits       = $vehicle ? in_array( $vehicle, $vehicles, true ) && ( 0 === $load_index || 0 === $floor || $load_index >= $floor ) : true;
        $tags       = array_values( array_filter( array_map( 'trim', explode( ',', strtolower( (string) ( $tire['tags'] ?? '' ) ) ) ) ) );
        $price      = round( (float) ( $tire['price'] ?? 0 ) );
        $eff        = (float) ( $tire['roamer_efficiency'] ?? 0 );

        return array(
            'tire_id'          => (string) $tire['tire_id'],
            'brand'            => (string) ( $tire['brand'] ?? '' ),
            'model'            => (string) ( $tire['model'] ?? '' ),
            'size'             => $size,
            'category'         => (string) ( $tire['category'] ?? '' ),
            'price'            => $price,
            'set_price'        => $price * 4,
            'load_index'       => $load_index,
            'load_range'       => (string) ( $tire['load_range'] ?? '' ),
            'fits'             => $fits,
            'vehicles'         => $vehicles,
            'mileage_warranty' => (int) ( $tire['mileage_warranty'] ?? 0 ),
            'weight_lb'        => (float) ( $tire['weight_lb'] ?? 0 ),
            'three_pms'        => 'yes' === strtolower( (string) ( $tire['three_pms'] ?? '' ) ),
            'oem'              => in_array( 'oem', $tags, true ),
            'tags'             => $tags,
            'efficiency'       => $eff > 0 ? $eff : null,
            'efficiency_vehicles' => (int) ( $tire['roamer_vehicle_count'] ?? 0 ),
            'efficiency_miles' => (int) round( (float) ( $tire['roamer_total_km'] ?? 0 ) * 0.621371 ),
            'efficiency_limited' => $eff > 0 ? self::limited_sample( $tire ) : true,
            'rating'           => isset( $tire['rating_average'] ) && null !== $tire['rating_average'] ? round( (float) $tire['rating_average'], 1 ) : null,
            'rating_count'     => (int) ( $tire['rating_count'] ?? 0 ),
            'slug'             => (string) ( $tire['slug'] ?? '' ),
        );
    }

    /**
     * Score one compact tire against the chosen priorities, each component
     * normalized 0..1 within the candidate set ($stats carries the set's
     * min/max). Equal weights per priority, a small boost for owner ratings.
     */
    public static function score( $t, $priorities, $stats ) {
        $norm = function ( $value, $key ) use ( $stats ) {
            $min = $stats[ $key ]['min'] ?? 0;
            $max = $stats[ $key ]['max'] ?? 0;
            if ( $max <= $min ) {
                return $value > 0 ? 1 : 0;
            }
            return max( 0, min( 1, ( $value - $min ) / ( $max - $min ) ) );
        };
        $cat = strtolower( $t['category'] );
        $components = array(
            'efficiency' => null === $t['efficiency'] ? 0 : $norm( $t['efficiency'], 'efficiency' ) * ( $t['efficiency_limited'] ? 0.5 : 1 ),
            'price'      => $t['price'] > 0 ? 1 - $norm( $t['price'], 'price' ) : 0,
            'quiet'      => self::lookup( $cat, array( 'highway' => 1, 'all-season' => 0.9, 'performance' => 0.7, 'winter' => 0.5, 'all-terrain' => 0.3, 'rugged terrain' => 0.2, 'mud-terrain' => 0 ), 0.5 ),
            'winter'     => max( $t['three_pms'] ? 1 : 0, self::lookup( $cat, array( 'winter' => 1, 'all-season' => 0.4, 'all-terrain' => 0.4, 'rugged terrain' => 0.3 ), 0.2 ) ),
            'offroad'    => self::lookup( $cat, array( 'mud-terrain' => 1, 'rugged terrain' => 1, 'all-terrain' => 0.9, 'all-season' => 0.2, 'highway' => 0.1, 'performance' => 0, 'winter' => 0.2 ), 0.3 ),
            'towing'     => max( $norm( $t['load_index'], 'load_index' ), self::lookup( strtolower( $t['load_range'] ), array( 'e' => 1, 'd' => 0.7, 'c' => 0.4 ), 0 ) ),
            'treadlife'  => $t['mileage_warranty'] > 0 ? $norm( $t['mileage_warranty'], 'mileage_warranty' ) : 0,
        );
        $use   = $priorities ? $priorities : array( 'efficiency', 'price', 'treadlife' );
        $total = 0;
        foreach ( $use as $p ) {
            $total += $components[ $p ] ?? 0;
        }
        $total /= max( 1, count( $use ) );
        if ( null !== $t['rating'] && $t['rating_count'] > 0 ) {
            $total += 0.1 * ( $t['rating'] / 5 ) * min( 1, $t['rating_count'] / 5 );
        }
        return array( 'total' => round( $total, 4 ), 'components' => $components );
    }

    private static function lookup( $key, $map, $default ) {
        return isset( $map[ $key ] ) ? $map[ $key ] : $default;
    }

    /**
     * The fitting, in-budget tires ranked by the rules, best first, capped
     * at CANDIDATE_LIMIT. Pure: the tires, the size map and the floors are
     * passed in.
     */
    public static function candidates( $tires, $input, $size_map, $floors ) {
        $vehicle = $input['vehicle'];
        if ( $vehicle && ! isset( $size_map[ $vehicle ] ) ) {
            $vehicle = '';
        }
        $pool = array();
        foreach ( $tires as $tire ) {
            $c = self::compact_tire( $tire, $size_map, $floors, $vehicle );
            if ( $vehicle && ! $c['fits'] ) {
                continue;
            }
            if ( $input['size'] && $c['size'] !== $input['size'] ) {
                continue;
            }
            if ( '' !== $input['budget'] && $c['price'] > (float) $input['budget'] ) {
                continue;
            }
            $pool[] = $c;
        }
        if ( ! $pool ) {
            return array();
        }

        $stats = array();
        foreach ( array( 'efficiency', 'price', 'load_index', 'mileage_warranty' ) as $key ) {
            $values = array();
            foreach ( $pool as $c ) {
                if ( null !== $c[ $key ] && $c[ $key ] > 0 ) {
                    $values[] = (float) $c[ $key ];
                }
            }
            $stats[ $key ] = array( 'min' => $values ? min( $values ) : 0, 'max' => $values ? max( $values ) : 0 );
        }

        foreach ( $pool as $i => $c ) {
            $s = self::score( $c, $input['priorities'], $stats );
            $pool[ $i ]['score']      = $s['total'];
            $pool[ $i ]['components'] = $s['components'];
        }
        usort( $pool, function ( $a, $b ) {
            if ( $a['score'] === $b['score'] ) {
                return strcmp( $a['brand'] . $a['model'], $b['brand'] . $b['model'] );
            }
            return $b['score'] <=> $a['score'];
        } );
        $pool = array_slice( $pool, 0, self::CANDIDATE_LIMIT );
        foreach ( $pool as $i => $c ) {
            $pool[ $i ]['rules_rank'] = $i + 1;
        }
        return $pool;
    }

    /**
     * Picks with templated reasons from the rules alone. What the visitor
     * gets when there is no key, or when the model call fails.
     */
    public static function rules_picks( $candidates, $input ) {
        $picks = array();
        $use   = $input['priorities'] ? $input['priorities'] : array( 'efficiency', 'price', 'treadlife' );
        foreach ( array_slice( $candidates, 0, self::PICK_COUNT ) as $c ) {
            // Its strongest chosen component leads, its weakest is the trade-off.
            $best = null;
            $worst = null;
            foreach ( $use as $p ) {
                $v = $c['components'][ $p ] ?? 0;
                if ( null === $best || $v > $c['components'][ $best ] ) {
                    $best = $p;
                }
                if ( null === $worst || $v < $c['components'][ $worst ] ) {
                    $worst = $p;
                }
            }
            $picks[] = array(
                'tire_id'  => $c['tire_id'],
                'headline' => self::rules_headline( $best, $c ),
                'reason'   => self::rules_reason( $c ),
                'tradeoff' => $worst !== $best ? self::rules_tradeoff( $worst, $c ) : '',
            );
        }
        return $picks;
    }

    private static function rules_headline( $priority, $c ) {
        switch ( $priority ) {
            case 'efficiency':
                return null !== $c['efficiency'] ? 'Strong real-world range' : 'A balanced pick';
            case 'price':
                return 'Easy on the budget';
            case 'quiet':
                return 'Built for a quiet ride';
            case 'winter':
                return $c['three_pms'] ? 'Rated for severe snow' : 'Ready for cold months';
            case 'offroad':
                return 'Made for the trail';
            case 'towing':
                return 'Carries a heavy load';
            case 'treadlife':
                return 'Long tread life';
        }
        return 'A solid all-rounder';
    }

    private static function rules_reason( $c ) {
        $bits = array();
        if ( null !== $c['efficiency'] ) {
            $bits[] = number_format( $c['efficiency'], 2 ) . ' mi/kWh across ' . $c['efficiency_vehicles'] . ' owner vehicle' . ( 1 === $c['efficiency_vehicles'] ? '' : 's' ) . ( $c['efficiency_limited'] ? ' (a small sample)' : '' );
        }
        if ( $c['price'] > 0 ) {
            $bits[] = '$' . number_format( $c['price'] ) . ' per tire, $' . number_format( $c['set_price'] ) . ' a set';
        }
        if ( $c['mileage_warranty'] > 0 ) {
            $bits[] = number_format( $c['mileage_warranty'] ) . ' mile warranty';
        }
        if ( $c['three_pms'] ) {
            $bits[] = '3PMS snow rated';
        }
        if ( null !== $c['rating'] && $c['rating_count'] > 0 ) {
            $bits[] = $c['rating'] . ' stars from ' . $c['rating_count'] . ' owner' . ( 1 === $c['rating_count'] ? '' : 's' );
        }
        $lead = trim( $c['brand'] . ' ' . $c['model'] ) . ' is ' . ( $c['category'] ? strtolower( $c['category'] ) : 'a' ) . ' tire';
        return $bits ? $lead . ': ' . implode( ', ', $bits ) . '.' : $lead . '.';
    }

    private static function rules_tradeoff( $priority, $c ) {
        switch ( $priority ) {
            case 'efficiency':
                return null === $c['efficiency'] ? 'No real-world efficiency data from owners yet.' : 'Not the most efficient of this set.';
            case 'price':
                return 'One of the pricier options here.';
            case 'quiet':
                return 'An aggressive tread tends to hum on the highway.';
            case 'winter':
                return $c['three_pms'] ? '' : 'Not snow rated, so plan on a winter set.';
            case 'offroad':
                return 'Better on pavement than on the trail.';
            case 'towing':
                return 'A lighter-duty load rating than some here.';
            case 'treadlife':
                return $c['mileage_warranty'] > 0 ? 'A shorter warranty than others in this set.' : 'No mileage warranty listed.';
        }
        return '';
    }

    /** A cache key that changes when any tire, price or rating changes. */
    public static function catalog_version( $tires ) {
        $latest = '';
        $ratings = 0;
        foreach ( $tires as $t ) {
            $u = (string) ( $t['updated_at'] ?? '' );
            if ( $u > $latest ) {
                $latest = $u;
            }
            $ratings += (int) ( $t['rating_count'] ?? 0 );
        }
        return md5( count( $tires ) . '|' . $latest . '|' . $ratings . '|' . RTG_VERSION );
    }

    /**
     * The request for the advise call: a stable system prompt and a user
     * message carrying the visitor's answers and the candidates as JSON.
     */
    public static function build_advise_request( $input, $candidates, $floors, $vehicle_names ) {
        $system = "You are the Rivian Tire Guide's advisor on RivianTrackr, helping a Rivian owner choose tires. "
            . "You will receive the owner's vehicle, what matters to them, their budget, optional notes, and a list of candidate tires from the guide's catalog with real numbers: price per tire, load index, mileage warranty, 3PMS snow rating, and real-world efficiency in mi/kWh measured by Rivian owners (with how many vehicles and miles are behind the number; a limited sample is less reliable). Candidates are pre-ranked by the guide's own rules as rules_rank.\n\n"
            . "Choose the best " . self::PICK_COUNT . " tires for this owner from the candidates only, by tire_id. Never invent a tire, a price or a number; use the values given and say the numbers. Write for an owner, not an engineer: plain, warm, direct, no jargon. Address the owner as \"you\" and never refer to yourself; there is no \"I\" in this advice. Say \"minimum load index\" rather than \"floor\", and \"owner-measured\" or \"real-world\" for the efficiency data. Each pick gets a headline of at most seven words, a reason of one or two sentences that cites the numbers behind it, and one honest trade-off sentence (what they give up by choosing it, or an empty string when there is none worth saying). Order the picks best first. Add a summary of one or two short sentences saying how the priorities were weighed. If the candidates are thin for what the owner wants, say so in the summary rather than overselling.";

        $payload = array(
            'owner'      => array(
                'vehicle'          => $input['vehicle'] ? $input['vehicle'] : 'unspecified',
                'vehicle_names'    => $vehicle_names,
                'minimum_load_index' => $input['vehicle'] && isset( $floors[ $input['vehicle'] ] ) ? (int) $floors[ $input['vehicle'] ] : null,
                'size'             => $input['size'] ? $input['size'] : 'any',
                'priorities'       => array_map( function ( $p ) {
                    return self::PRIORITIES[ $p ];
                }, $input['priorities'] ),
                'budget_per_tire'  => '' !== $input['budget'] ? (int) $input['budget'] : null,
                'notes'            => $input['notes'],
            ),
            'candidates' => array_map( function ( $c ) {
                unset( $c['components'], $c['slug'], $c['fits'], $c['tags'] );
                return $c;
            }, $candidates ),
        );

        $schema = array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array( 'summary', 'picks' ),
            'properties'           => array(
                'summary' => array( 'type' => 'string' ),
                'picks'   => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => array( 'tire_id', 'headline', 'reason', 'tradeoff' ),
                        'properties'           => array(
                            'tire_id'  => array( 'type' => 'string' ),
                            'headline' => array( 'type' => 'string' ),
                            'reason'   => array( 'type' => 'string' ),
                            'tradeoff' => array( 'type' => 'string' ),
                        ),
                    ),
                ),
            ),
        );

        return array( 'system' => $system, 'user' => wp_json_encode( $payload ), 'schema' => $schema );
    }

    /**
     * Keep only picks that name a candidate, once each, capped. The schema
     * guarantees the shape; this guarantees the grounding.
     */
    public static function validate_picks( $decoded, $candidates ) {
        $known = array();
        foreach ( $candidates as $c ) {
            $known[ $c['tire_id'] ] = true;
        }
        $seen  = array();
        $picks = array();
        foreach ( (array) ( $decoded['picks'] ?? array() ) as $pick ) {
            $id = (string) ( $pick['tire_id'] ?? '' );
            if ( ! isset( $known[ $id ] ) || isset( $seen[ $id ] ) ) {
                continue;
            }
            $seen[ $id ] = true;
            $picks[] = array(
                'tire_id'  => $id,
                'headline' => self::clean_text( $pick['headline'] ?? '', 80 ),
                'reason'   => self::clean_text( $pick['reason'] ?? '', 600 ),
                'tradeoff' => self::clean_text( $pick['tradeoff'] ?? '', 300 ),
            );
            if ( count( $picks ) >= self::PICK_COUNT ) {
                break;
            }
        }
        return array(
            'summary' => self::clean_text( $decoded['summary'] ?? '', 400 ),
            'picks'   => $picks,
        );
    }

    public static function clean_text( $text, $max ) {
        $text = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- must also run without WordPress (tests/contract)
        if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > $max ) {
            return rtrim( mb_substr( $text, 0, $max ) ) . '…';
        }
        return strlen( $text ) > $max ? rtrim( substr( $text, 0, $max ) ) . '…' : $text;
    }

    /** Fill each pick with the tire fields the UI renders. */
    private static function hydrate_picks( $picks, $candidates ) {
        $by_id = array();
        foreach ( $candidates as $c ) {
            $by_id[ $c['tire_id'] ] = $c;
        }
        $settings = get_option( 'rtg_settings', array() );
        $base     = home_url( '/' . RTG_Tire_Page::slug_base() . '/' );
        foreach ( $picks as $i => $pick ) {
            $c = $by_id[ $pick['tire_id'] ];
            $picks[ $i ]['tire'] = array(
                'tire_id'    => $c['tire_id'],
                'brand'      => $c['brand'],
                'model'      => $c['model'],
                'size'       => $c['size'],
                'category'   => $c['category'],
                'price'      => $c['price'],
                'set_price'  => $c['set_price'],
                'efficiency' => $c['efficiency'],
                'efficiency_vehicles' => $c['efficiency_vehicles'],
                'efficiency_limited'  => $c['efficiency_limited'],
                'three_pms'  => $c['three_pms'],
                'load_index' => $c['load_index'],
                'mileage_warranty' => $c['mileage_warranty'],
                'vehicles'   => $c['vehicles'],
                'rating'     => $c['rating'],
                'rating_count' => $c['rating_count'],
                'url'        => $base . rawurlencode( $c['slug'] ? $c['slug'] : $c['tire_id'] ) . '/',
            );
        }
        return $picks;
    }

    /** The whole flow: cache, candidates, model, fallback. */
    public static function advise( $input ) {
        $tires    = RTG_Database::get_tires_with_ratings();
        $size_map = RTG_Database::get_vehicle_size_map();
        $floors   = RTG_Fitment::floors();
        if ( $input['vehicle'] && ! isset( $size_map[ $input['vehicle'] ] ) ) {
            $input['vehicle'] = '';
        }

        $candidates = self::candidates( $tires, $input, $size_map, $floors );
        if ( ! $candidates ) {
            return array(
                'ok'      => true,
                'source'  => 'rules',
                'summary' => 'Nothing in the guide fits those answers yet. Try a wider budget or fewer must-haves.',
                'picks'   => array(),
                'input'   => $input,
            );
        }

        $live  = self::is_live();
        $model = self::model();
        $key   = self::CACHE_PREFIX . 'advise_' . md5( wp_json_encode( $input ) . '|' . self::catalog_version( $tires ) . '|' . ( $live ? $model : 'rules' ) );
        $cached = get_transient( $key );
        if ( is_array( $cached ) && isset( $cached['picks'] ) ) {
            $cached['cached'] = true;
            return $cached;
        }

        $source = 'rules';
        $picked = null;
        if ( $live ) {
            $req  = self::build_advise_request( $input, $candidates, $floors, array_keys( $size_map ) );
            $call = self::call( $req['system'], $req['user'], $req['schema'], 2048, 'low' );
            if ( $call['ok'] ) {
                $picked = self::validate_picks( $call['data'], $candidates );
                if ( $picked['picks'] ) {
                    $source = 'claude';
                } else {
                    $picked = null;
                }
            }
        }
        if ( null === $picked ) {
            $picked = array(
                'summary' => $input['priorities']
                    ? 'Ranked by the guide\'s rules on ' . implode( ', ', array_map( function ( $p ) {
                        return self::PRIORITY_SHORT[ $p ];
                    }, $input['priorities'] ) ) . '.'
                    : 'Ranked by the guide\'s rules on efficiency, price and tread life.',
                'picks'   => self::rules_picks( $candidates, $input ),
            );
        }

        $result = array(
            'ok'      => true,
            'source'  => $source,
            'summary' => $picked['summary'],
            'picks'   => self::hydrate_picks( $picked['picks'], $candidates ),
            'input'   => $input,
        );
        set_transient( $key, $result, DAY_IN_SECONDS );
        return $result;
    }

    // ------------------------------------------------------------------
    // What owners say
    // ------------------------------------------------------------------

    public static function build_review_request( $tire, $reviews ) {
        $system = "You summarize owner reviews of a tire for other Rivian owners on RivianTrackr. Use only what the reviews say; never add claims of your own, and never quote a reviewer by name. If the reviews disagree, say so. Write plainly and briefly: a summary of at most sixty words, up to three short strengths (a few words each) and up to three short weaknesses, each only when at least one review supports it. Leave the strengths or weaknesses empty rather than padding them.";

        $items = array();
        foreach ( $reviews as $r ) {
            $items[] = array(
                'rating' => (int) $r['rating'],
                'title'  => self::clean_text( $r['review_title'] ?? '', 120 ),
                'text'   => self::clean_text( $r['review_text'] ?? '', 1200 ),
                'when'   => substr( (string) ( $r['created_at'] ?? '' ), 0, 7 ),
            );
        }
        $payload = array(
            'tire'    => trim( ( $tire['brand'] ?? '' ) . ' ' . ( $tire['model'] ?? '' ) ) . ( ! empty( $tire['size'] ) ? ' (' . $tire['size'] . ')' : '' ),
            'reviews' => $items,
        );
        $schema = array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array( 'summary', 'pros', 'cons' ),
            'properties'           => array(
                'summary' => array( 'type' => 'string' ),
                'pros'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'cons'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
            ),
        );
        return array( 'system' => $system, 'user' => wp_json_encode( $payload ), 'schema' => $schema );
    }

    public static function review_summary( $tire ) {
        $tire_id = (string) $tire['tire_id'];
        $reviews = RTG_Database::get_tire_reviews( $tire_id, self::REVIEWS_FOR_SUMMARY );
        $with_text = array_values( array_filter( $reviews, function ( $r ) {
            return '' !== trim( (string) ( $r['review_text'] ?? '' ) );
        } ) );
        if ( count( $with_text ) < self::MIN_REVIEWS_FOR_SUMMARY ) {
            return array( 'ok' => false, 'error' => 'Not enough written reviews yet.' );
        }

        $latest = '';
        foreach ( $with_text as $r ) {
            $latest = max( $latest, (string) ( $r['updated_at'] ?? '' ) );
        }
        $key    = self::CACHE_PREFIX . 'reviews_' . md5( $tire_id . '|' . count( $with_text ) . '|' . $latest . '|' . self::model() );
        $cached = get_transient( $key );
        if ( is_array( $cached ) && ! empty( $cached['ok'] ) ) {
            $cached['cached'] = true;
            return $cached;
        }

        $lock = 'ai_reviews_' . sanitize_key( $tire_id );
        if ( ! RTG_Lock::acquire( $lock, self::REQUEST_TIMEOUT + 5 ) ) {
            return array( 'ok' => false, 'pending' => true, 'error' => 'Being written now.' );
        }
        try {
            $req  = self::build_review_request( $tire, $with_text );
            $call = self::call( $req['system'], $req['user'], $req['schema'], 1024, 'low' );
            if ( ! $call['ok'] ) {
                return array( 'ok' => false, 'error' => $call['error'] );
            }
            $result = array(
                'ok'       => true,
                'summary'  => self::clean_text( $call['data']['summary'] ?? '', 500 ),
                'pros'     => self::clean_list( $call['data']['pros'] ?? array() ),
                'cons'     => self::clean_list( $call['data']['cons'] ?? array() ),
                'based_on' => count( $with_text ),
            );
            if ( '' === $result['summary'] ) {
                return array( 'ok' => false, 'error' => 'Empty summary.' );
            }
            set_transient( $key, $result, 30 * DAY_IN_SECONDS );
            return $result;
        } finally {
            RTG_Lock::release( $lock );
        }
    }

    public static function clean_list( $items, $max = 3 ) {
        $out = array();
        foreach ( (array) $items as $item ) {
            $item = self::clean_text( $item, 60 );
            if ( '' !== $item ) {
                $out[] = $item;
            }
            if ( count( $out ) >= $max ) {
                break;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // In plain words (compare page)
    // ------------------------------------------------------------------

    public static function build_compare_request( $tires, $size_map, $floors ) {
        $system = "You compare tires for a Rivian owner on RivianTrackr's compare page. You will get two to four tires with their real numbers from the guide: price per tire, load index, mileage warranty, 3PMS snow rating, weight, and real-world efficiency in mi/kWh measured by Rivian owners (with the sample behind it; a limited sample is less reliable). Write one paragraph of at most ninety words that says where each tire wins and what you give up with it, citing the numbers. Neutral and plain, like a knowledgeable friend, not a salesperson. Never invent a number or a fact that is not in the data. Do not recommend a winner unless the numbers make it obvious.";
        $items  = array();
        foreach ( $tires as $tire ) {
            $c = self::compact_tire( $tire, $size_map, $floors );
            unset( $c['slug'], $c['fits'], $c['tags'] );
            $items[] = $c;
        }
        $schema = array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array( 'paragraph' ),
            'properties'           => array( 'paragraph' => array( 'type' => 'string' ) ),
        );
        return array( 'system' => $system, 'user' => wp_json_encode( array( 'tires' => $items ) ), 'schema' => $schema );
    }

    public static function compare_summary( $tires ) {
        $ids = array_map( function ( $t ) {
            return (string) $t['tire_id'];
        }, $tires );
        sort( $ids );
        $all    = RTG_Database::get_tires_with_ratings();
        $key    = self::CACHE_PREFIX . 'compare_' . md5( implode( ',', $ids ) . '|' . self::catalog_version( $all ) . '|' . self::model() );
        $cached = get_transient( $key );
        if ( is_array( $cached ) && ! empty( $cached['ok'] ) ) {
            $cached['cached'] = true;
            return $cached;
        }

        $lock = 'ai_compare_' . substr( md5( implode( ',', $ids ) ), 0, 12 );
        if ( ! RTG_Lock::acquire( $lock, self::REQUEST_TIMEOUT + 5 ) ) {
            return array( 'ok' => false, 'pending' => true, 'error' => 'Being written now.' );
        }
        try {
            // Ratings ride along from the catalog rows.
            $by_id = array();
            foreach ( $all as $t ) {
                $by_id[ (string) $t['tire_id'] ] = $t;
            }
            $rows = array();
            foreach ( $tires as $t ) {
                $rows[] = $by_id[ (string) $t['tire_id'] ] ?? $t;
            }
            $req  = self::build_compare_request( $rows, RTG_Database::get_vehicle_size_map(), RTG_Fitment::floors() );
            $call = self::call( $req['system'], $req['user'], $req['schema'], 1024, 'low' );
            if ( ! $call['ok'] ) {
                return array( 'ok' => false, 'error' => $call['error'] );
            }
            $paragraph = self::clean_text( $call['data']['paragraph'] ?? '', 900 );
            if ( '' === $paragraph ) {
                return array( 'ok' => false, 'error' => 'Empty paragraph.' );
            }
            $result = array( 'ok' => true, 'paragraph' => $paragraph, 'ids' => $ids );
            set_transient( $key, $result, DAY_IN_SECONDS );
            return $result;
        } finally {
            RTG_Lock::release( $lock );
        }
    }

    // ------------------------------------------------------------------
    // The client
    // ------------------------------------------------------------------

    /**
     * The Messages API body. Structured JSON output through
     * output_config.format, so the answer is schema-valid by construction.
     * Effort and the server-side refusal fallback are sent only to models
     * that take them: Haiku 4.5 rejects effort, and the fallback parameter
     * belongs to Opus 5. The system prompt carries a cache breakpoint; it
     * is the stable prefix every call shares.
     */
    public static function request_body( $model, $system, $user, $schema, $max_tokens, $effort ) {
        $body = array(
            'model'      => $model,
            'max_tokens' => (int) $max_tokens,
            'system'     => array(
                array( 'type' => 'text', 'text' => $system, 'cache_control' => array( 'type' => 'ephemeral' ) ),
            ),
            'messages'   => array(
                array( 'role' => 'user', 'content' => $user ),
            ),
            'output_config' => array(
                'format' => array( 'type' => 'json_schema', 'schema' => $schema ),
            ),
        );
        if ( self::supports_effort( $model ) ) {
            $body['output_config']['effort'] = $effort;
        }
        if ( self::supports_fallbacks( $model ) ) {
            $body['fallbacks'] = 'default';
        }
        return $body;
    }

    public static function supports_effort( $model ) {
        return in_array( $model, array( 'claude-opus-5', 'claude-sonnet-5' ), true );
    }

    public static function supports_fallbacks( $model ) {
        return 'claude-opus-5' === $model;
    }

    public static function request_headers( $api_key, $model ) {
        $headers = array(
            'Content-Type'      => 'application/json',
            'x-api-key'         => $api_key,
            'anthropic-version' => self::API_VERSION,
        );
        if ( self::supports_fallbacks( $model ) ) {
            $headers['anthropic-beta'] = self::FALLBACK_BETA;
        }
        return $headers;
    }

    /**
     * Turn the HTTP status and raw body into the one result shape:
     * ok + data (the decoded JSON the schema promised), or ok=false + error.
     */
    public static function parse_response( $code, $raw ) {
        $decoded = json_decode( (string) $raw, true );
        if ( 401 === $code || 403 === $code ) {
            return array( 'ok' => false, 'error' => 'The API key was rejected (HTTP ' . $code . ').' );
        }
        if ( 429 === $code ) {
            return array( 'ok' => false, 'error' => 'The API is rate limiting us right now (HTTP 429).' );
        }
        if ( 200 !== $code ) {
            $detail = is_array( $decoded ) && isset( $decoded['error']['message'] ) ? ': ' . self::clean_text( $decoded['error']['message'], 200 ) : '';
            return array( 'ok' => false, 'error' => 'HTTP ' . $code . $detail );
        }
        if ( ! is_array( $decoded ) ) {
            return array( 'ok' => false, 'error' => 'The response was not valid JSON.' );
        }
        if ( ( $decoded['stop_reason'] ?? '' ) === 'refusal' ) {
            return array( 'ok' => false, 'error' => 'The model declined this request.' );
        }
        if ( ( $decoded['stop_reason'] ?? '' ) === 'max_tokens' ) {
            return array( 'ok' => false, 'error' => 'The answer was cut off (max_tokens).' );
        }
        $text = '';
        foreach ( (array) ( $decoded['content'] ?? array() ) as $block ) {
            if ( is_array( $block ) && ( $block['type'] ?? '' ) === 'text' ) {
                $text .= (string) ( $block['text'] ?? '' );
            }
        }
        $data = json_decode( $text, true );
        if ( ! is_array( $data ) ) {
            return array( 'ok' => false, 'error' => 'The answer was not the JSON asked for.' );
        }
        return array(
            'ok'    => true,
            'data'  => $data,
            'model' => (string) ( $decoded['model'] ?? '' ),
            'usage' => array(
                'input'       => (int) ( $decoded['usage']['input_tokens'] ?? 0 ),
                'output'      => (int) ( $decoded['usage']['output_tokens'] ?? 0 ),
                'cache_read'  => (int) ( $decoded['usage']['cache_read_input_tokens'] ?? 0 ),
                'cache_write' => (int) ( $decoded['usage']['cache_creation_input_tokens'] ?? 0 ),
            ),
        );
    }

    /** One call to the Messages API. Never throws; records the outcome. */
    public static function call( $system, $user, $schema, $max_tokens = 2048, $effort = 'low' ) {
        $api_key = self::api_key();
        if ( '' === $api_key ) {
            return array( 'ok' => false, 'error' => 'No API key.' );
        }
        $model = self::model();
        $body  = self::request_body( $model, $system, $user, $schema, $max_tokens, $effort );

        $response = wp_remote_post( self::ENDPOINT, array(
            'timeout'   => self::REQUEST_TIMEOUT,
            'sslverify' => true,
            'headers'   => self::request_headers( $api_key, $model ),
            'body'      => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            $result = array( 'ok' => false, 'error' => $response->get_error_message() );
            self::record_state( 'error', $result['error'] );
            return $result;
        }

        $result = self::parse_response(
            (int) wp_remote_retrieve_response_code( $response ),
            wp_remote_retrieve_body( $response )
        );
        if ( $result['ok'] ) {
            self::record_state( 'ok', 'Last call succeeded.', array( 'usage' => $result['usage'], 'served_by' => $result['model'] ) );
        } else {
            self::record_state( 'error', $result['error'] );
        }
        return $result;
    }
}
