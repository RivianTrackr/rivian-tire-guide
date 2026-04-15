<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI-powered tire recommendation engine using Anthropic's Claude API.
 *
 * Handles building tire context, calling the Messages API, parsing structured
 * responses, rate limiting per IP, and response caching.
 */
class RTG_AI {

    /**
     * Default rate limit: requests per window per visitor.
     */
    const DEFAULT_RATE_LIMIT = 10;

    /**
     * Rate limit window in seconds.
     */
    const RATE_LIMIT_WINDOW = 60;

    /**
     * Response cache TTL in seconds (1 hour).
     */
    const CACHE_TTL = HOUR_IN_SECONDS;

    /**
     * Maximum query length in characters.
     */
    const MAX_QUERY_LENGTH = 500;

    /**
     * API request timeout in seconds.
     */
    const API_TIMEOUT = 30;

    /**
     * Anthropic Messages API endpoint.
     */
    const API_URL = 'https://api.anthropic.com/v1/messages';

    /**
     * Anthropic Models API endpoint (list).
     */
    const MODELS_API_URL = 'https://api.anthropic.com/v1/models';

    /**
     * Option key where the refreshed model list is cached.
     * Shape: [ 'fetched_at' => int, 'models' => [ [id => string, display_name => string], ... ] ]
     */
    const MODELS_OPTION = 'rtg_ai_models_cache';

    /**
     * Default model to use.
     */
    const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';

    /**
     * Fallback model list used when Anthropic has never been queried
     * (or the refresh failed). Order is the same order they'll render in
     * the admin dropdown.
     *
     * @return array[] Each entry has id and display_name.
     */
    public static function get_fallback_models() {
        return array(
            array( 'id' => 'claude-haiku-4-5-20251001',  'display_name' => 'Claude Haiku 4.5 (Fast, Low Cost)' ),
            array( 'id' => 'claude-sonnet-4-20250514',   'display_name' => 'Claude Sonnet 4 (More Capable)' ),
        );
    }

    /**
     * Return the currently cached model list, or the fallback if none.
     * Used by the admin settings view and the save-handler allowlist.
     *
     * @return array { models: array[], fetched_at: int|null, source: 'api'|'fallback' }
     */
    public static function get_available_models() {
        $cache = get_option( self::MODELS_OPTION, array() );
        if ( is_array( $cache ) && ! empty( $cache['models'] ) ) {
            return array(
                'models'     => $cache['models'],
                'fetched_at' => intval( $cache['fetched_at'] ?? 0 ),
                'source'     => 'api',
            );
        }
        return array(
            'models'     => self::get_fallback_models(),
            'fetched_at' => null,
            'source'     => 'fallback',
        );
    }

    /**
     * Return just the list of valid model IDs, used as an allowlist
     * when saving the settings form.
     *
     * @return string[]
     */
    public static function get_allowed_model_ids() {
        $data = self::get_available_models();
        return array_values( array_filter( array_map(
            static function ( $m ) {
                return isset( $m['id'] ) ? (string) $m['id'] : '';
            },
            $data['models']
        ) ) );
    }

    /**
     * Fetch the current model list from Anthropic's /v1/models endpoint
     * and persist it to the cache option. Only models whose ID starts with
     * "claude-" are kept (the endpoint also returns deprecated/legacy entries).
     *
     * @return array|WP_Error Result array matching get_available_models(), or WP_Error.
     */
    public static function refresh_available_models() {
        $api_key = self::get_api_key();
        if ( '' === $api_key ) {
            return new WP_Error( 'no_api_key', 'Set an Anthropic API key before refreshing the model list.' );
        }

        $response = wp_remote_get( self::MODELS_API_URL . '?limit=100', array(
            'timeout' => 15,
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'RTG AI models refresh error: ' . $response->get_error_message() );
            return new WP_Error( 'api_error', 'Unable to reach Anthropic. ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( 401 === $status ) {
            return new WP_Error( 'api_auth', 'Anthropic rejected the API key. Check the key in settings.' );
        }
        if ( 200 !== $status || ! is_array( $data ) || empty( $data['data'] ) ) {
            error_log( 'RTG AI models refresh HTTP ' . $status . ': ' . $body );
            return new WP_Error( 'api_error', 'Anthropic returned an unexpected response (HTTP ' . intval( $status ) . ').' );
        }

        $models = array();
        foreach ( $data['data'] as $entry ) {
            if ( empty( $entry['id'] ) || strpos( $entry['id'], 'claude-' ) !== 0 ) {
                continue;
            }
            $models[] = array(
                'id'           => sanitize_text_field( $entry['id'] ),
                'display_name' => sanitize_text_field( $entry['display_name'] ?? $entry['id'] ),
                'created_at'   => sanitize_text_field( $entry['created_at'] ?? '' ),
            );
        }

        if ( empty( $models ) ) {
            return new WP_Error( 'empty_list', 'Anthropic returned no Claude models.' );
        }

        // Sort newest first using the created_at ISO timestamp Anthropic returns.
        usort( $models, static function ( $a, $b ) {
            return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
        } );

        $payload = array(
            'models'     => $models,
            'fetched_at' => time(),
        );
        update_option( self::MODELS_OPTION, $payload, false );

        return array(
            'models'     => $models,
            'fetched_at' => $payload['fetched_at'],
            'source'     => 'api',
        );
    }

    /**
     * Check whether AI recommendations are enabled and configured.
     *
     * @return bool
     */
    public static function is_enabled() {
        $settings = get_option( 'rtg_settings', array() );
        return ! empty( $settings['ai_enabled'] ) && '' !== self::get_api_key();
    }

    /**
     * Resolve the Anthropic API key, preferring the wp-config constant
     * RTG_ANTHROPIC_API_KEY over the plugin-settings value so credentials
     * can live outside the database.
     *
     * @return string API key or empty string.
     */
    public static function get_api_key() {
        if ( defined( 'RTG_ANTHROPIC_API_KEY' ) && '' !== RTG_ANTHROPIC_API_KEY ) {
            return (string) RTG_ANTHROPIC_API_KEY;
        }
        $settings = get_option( 'rtg_settings', array() );
        return (string) ( $settings['ai_api_key'] ?? '' );
    }

    /**
     * Get tire recommendations for a natural-language query.
     *
     * @param string $query User's search query.
     * @return array|WP_Error Structured recommendations or error.
     */
    public static function get_recommendations( $query ) {
        $settings = get_option( 'rtg_settings', array() );
        $api_key  = self::get_api_key();

        if ( '' === $api_key ) {
            return new WP_Error( 'no_api_key', 'AI recommendations are not configured.' );
        }

        // Sanitize and validate query.
        $query = sanitize_text_field( $query );
        $query = mb_substr( $query, 0, self::MAX_QUERY_LENGTH );

        if ( empty( trim( $query ) ) ) {
            return new WP_Error( 'empty_query', 'Please enter a search query.' );
        }

        // Check rate limit.
        if ( self::is_rate_limited() ) {
            return new WP_Error( 'rate_limited', 'Too many requests. Please wait a moment and try again.' );
        }

        // Check cache first.
        $cache_key = 'rtg_ai_' . md5( strtolower( trim( $query ) ) );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        // Record rate limit hit.
        self::record_rate_limit();

        // Build context and call API.
        $tire_context = self::build_tire_context();
        $model        = $settings['ai_model'] ?? self::DEFAULT_MODEL;

        $result = self::call_anthropic_api( $api_key, $model, $tire_context, $query );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Cache successful response.
        set_transient( $cache_key, $result, self::CACHE_TTL );

        return $result;
    }

    /**
     * Build a compact text representation of all tires for Claude's context.
     *
     * @return string Formatted tire catalog.
     */
    private static function build_tire_context() {
        // Single JOIN query instead of get_all_tires() + get_tire_ratings() (eliminates N+1).
        $tires = RTG_Database::get_tires_with_ratings();

        $lines = array();
        foreach ( $tires as $tire ) {
            $tid          = $tire['tire_id'];
            $avg_rating   = ! empty( $tire['rating_count'] ) ? round( floatval( $tire['rating_average'] ), 1 ) : 'N/A';
            $review_count = (int) ( $tire['rating_count'] ?? 0 );

            $roamer_eff  = ! empty( $tire['roamer_efficiency'] ) ? round( $tire['roamer_efficiency'], 2 ) . ' mi/kWh (' . number_format( floatval( $tire['roamer_total_km'] ?? 0 ) * 0.621371, 0 ) . ' mi tracked)' : 'N/A';

            $parts = array(
                'ID: ' . $tid,
                'Brand: ' . $tire['brand'],
                'Model: ' . $tire['model'],
                'Size: ' . $tire['size'],
                'Diameter: ' . $tire['diameter'] . '"',
                'Category: ' . $tire['category'],
                'Price: $' . $tire['price'],
                'Warranty: ' . ( $tire['mileage_warranty'] ? $tire['mileage_warranty'] . 'mi' : 'N/A' ),
                'Weight: ' . $tire['weight_lb'] . 'lb',
                '3PMS: ' . ( $tire['three_pms'] === 'Yes' ? 'Yes' : 'No' ),
                'Tread: ' . $tire['tread'] . '/32"',
                'Load: ' . $tire['load_index'] . ' (' . $tire['max_load_lb'] . 'lb)',
                'Speed: ' . $tire['speed_rating'],
                'UTQG: ' . $tire['utqg'],
                'Efficiency: ' . $tire['efficiency_grade'] . ' (' . $tire['efficiency_score'] . ')',
                'Real-World: ' . $roamer_eff,
                'Rating: ' . $avg_rating . '/5 (' . $review_count . ' reviews)',
                'Tags: ' . ( $tire['tags'] ?: 'None' ),
            );

            $lines[] = implode( ' | ', $parts );
        }

        return implode( "\n", $lines );
    }

    /**
     * Call the Anthropic Messages API.
     *
     * @param string $api_key      Anthropic API key.
     * @param string $model        Model ID.
     * @param string $tire_context Formatted tire catalog.
     * @param string $query        User query.
     * @return array|WP_Error Parsed recommendations or error.
     */
    private static function call_anthropic_api( $api_key, $model, $tire_context, $query ) {
        $system_prompt = self::build_system_prompt( $tire_context );

        $body = array(
            'model'      => $model,
            'max_tokens' => 1024,
            'system'     => $system_prompt,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $query,
                ),
            ),
        );

        $response = wp_remote_post( self::API_URL, array(
            'timeout' => self::API_TIMEOUT,
            'headers' => array(
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body'    => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'RTG AI API error: ' . $response->get_error_message() );
            return new WP_Error( 'api_error', 'Unable to reach the AI service. Please try again later.' );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            error_log( 'RTG AI API HTTP ' . $status_code . ': ' . $body_raw );

            if ( $status_code === 401 ) {
                return new WP_Error( 'api_auth', 'AI service authentication failed. Please check the API key in settings.' );
            }
            if ( $status_code === 429 ) {
                return new WP_Error( 'api_rate_limit', 'AI service rate limit exceeded. Please try again in a moment.' );
            }

            return new WP_Error( 'api_error', 'AI service returned an error. Please try again later.' );
        }

        $data = json_decode( $body_raw, true );

        if ( ! $data || empty( $data['content'][0]['text'] ) ) {
            error_log( 'RTG AI API: unexpected response format: ' . $body_raw );
            return new WP_Error( 'parse_error', 'Received an unexpected response from the AI service.' );
        }

        return self::parse_response( $data['content'][0]['text'] );
    }

    /**
     * Build the system prompt that instructs Claude how to respond.
     *
     * @param string $tire_context Formatted tire catalog data.
     * @return string System prompt.
     */
    private static function build_system_prompt( $tire_context ) {
        return 'You are a Rivian tire recommendation expert. You help Rivian vehicle owners find the best tires based on their needs.

You have access to the following tire catalog data. Each line represents one tire with its specifications:

' . $tire_context . '

IMPORTANT CONTEXT:
- All tires in this catalog are compatible with Rivian vehicles (R1T, R1S, R2, R3).
- The "Size" field (e.g., 275/55R20) indicates the tire dimensions. The last two digits indicate the wheel diameter (20 = 20" wheels, 21 = 21" wheels, 22 = 22" wheels).
- "3PMS" (Three-Peak Mountain Snowflake) means the tire is certified for severe snow conditions.
- "EV Rated" in tags means the tire is specifically designed for electric vehicles.
- "Efficiency Grade" rates how well the tire preserves range/efficiency (A is best, F is worst).
- "Real-World" is real-world efficiency data from Rivian Roamer, measured in mi/kWh by actual Rivian owners. Higher mi/kWh means better real-world range. The session count indicates how much data backs the measurement. When a user asks about range or efficiency, factor in the Real-World mi/kWh data alongside the Efficiency Grade — real-world data is especially valuable because it reflects actual driving conditions. If a tire has no Real-World data (N/A), note that in your reason.
- Category types: All-Season, Winter, Performance, All-Terrain, Highway, Rugged Terrain, Mud-Terrain.

INSTRUCTIONS:
1. Analyze the user\'s query to understand their needs (season, terrain, budget, wheel size, priorities).
2. Select the most relevant tires from the catalog (up to 6 recommendations, ranked best to worst).
3. Only recommend tires that match the user\'s criteria. If they specify a wheel size, only include tires with that size.
4. Provide a brief reason for each recommendation.
5. In your summary, always include a note reminding the user to verify the recommended tire size matches the size currently on their vehicle (e.g., "Make sure the tire size matches what\'s currently on your vehicle, or consult your owner\'s manual for compatible sizes.").

You MUST respond with valid JSON in this exact format and nothing else (no markdown, no code fences):
{"recommendations":[{"tire_id":"<actual tire_id>","reason":"<1-2 sentence explanation>"}],"summary":"<1-2 sentence overview that mentions each recommended tire by Brand and Model name>"}

If no tires match the user\'s criteria, respond with:
{"recommendations":[],"summary":"<explanation of why no tires match>"}';
    }

    /**
     * Parse Claude's JSON response into a structured array.
     *
     * @param string $text Raw text response from Claude.
     * @return array|WP_Error Parsed recommendations.
     */
    private static function parse_response( $text ) {
        // Strip any markdown code fences if present.
        $text = trim( $text );
        $text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
        $text = preg_replace( '/\s*```$/i', '', $text );
        $text = trim( $text );

        $data = json_decode( $text, true );

        if ( ! is_array( $data ) || ! isset( $data['recommendations'] ) ) {
            error_log( 'RTG AI: failed to parse response: ' . $text );
            return new WP_Error( 'parse_error', 'Unable to parse AI recommendations. Please try rephrasing your query.' );
        }

        // Validate tire IDs exist in the database.
        $all_tires   = RTG_Database::get_all_tires();
        $valid_ids   = array_column( $all_tires, 'tire_id' );
        $valid_recs  = array();

        foreach ( $data['recommendations'] as $rec ) {
            if ( ! empty( $rec['tire_id'] ) && in_array( $rec['tire_id'], $valid_ids, true ) ) {
                $valid_recs[] = array(
                    'tire_id' => sanitize_text_field( $rec['tire_id'] ),
                    'reason'  => sanitize_text_field( $rec['reason'] ?? '' ),
                );
            }
        }

        return array(
            'recommendations' => $valid_recs,
            'summary'         => sanitize_text_field( $data['summary'] ?? '' ),
        );
    }

    /**
     * Check whether the current visitor is rate-limited.
     *
     * @return bool
     */
    private static function is_rate_limited() {
        $settings  = get_option( 'rtg_settings', array() );
        $max       = intval( $settings['ai_rate_limit'] ?? self::DEFAULT_RATE_LIMIT );
        $ip_hash   = md5( self::get_client_ip() );
        $key       = 'rtg_ai_rl_' . $ip_hash;
        $attempts  = get_transient( $key );

        if ( false === $attempts ) {
            return false;
        }

        return (int) $attempts >= $max;
    }

    /**
     * Record a rate limit hit for the current visitor.
     */
    private static function record_rate_limit() {
        $ip_hash  = md5( self::get_client_ip() );
        $key      = 'rtg_ai_rl_' . $ip_hash;
        $attempts = get_transient( $key );

        if ( false === $attempts ) {
            set_transient( $key, 1, self::RATE_LIMIT_WINDOW );
        } else {
            set_transient( $key, (int) $attempts + 1, self::RATE_LIMIT_WINDOW );
        }
    }

    /**
     * Get the client's IP address.
     *
     * Uses REMOTE_ADDR as the primary source since it cannot be spoofed
     * at the HTTP level. Only falls back to proxy headers when REMOTE_ADDR
     * is a private/reserved IP (indicating the server is behind a reverse proxy).
     *
     * @return string IP address.
     */
    private static function get_client_ip() {
        $remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( $_SERVER['REMOTE_ADDR'] ) : '';

        if ( ! empty( $remote_addr ) && filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
            // Only trust proxy headers when REMOTE_ADDR is a private/reserved IP,
            // which indicates the request came through a trusted reverse proxy.
            $is_proxied = ! filter_var(
                $remote_addr,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ( $is_proxied ) {
                $proxy_headers = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' );
                foreach ( $proxy_headers as $header ) {
                    if ( ! empty( $_SERVER[ $header ] ) ) {
                        // X-Forwarded-For can contain multiple IPs; take the first.
                        $ip = strtok( $_SERVER[ $header ], ',' );
                        $ip = trim( $ip );
                        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                            return $ip;
                        }
                    }
                }
            }

            return $remote_addr;
        }

        return '0.0.0.0';
    }

    /**
     * Flush all AI response caches.
     * Called when tire data changes (add/edit/delete).
     */
    public static function flush_cache() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rtg_ai_%' OR option_name LIKE '_transient_timeout_rtg_ai_%'"
        );
    }
}
