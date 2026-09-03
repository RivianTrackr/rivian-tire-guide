<?php
/**
 * Tests for RTG_Advisor inside WordPress: the routes, the caching, the
 * fallback to rules, the settings and the analytics event. The pure
 * ranking, prompt and parsing logic is pinned in tests/contract/advisor.php.
 *
 * The network is stubbed through pre_http_request (the suite blocks real
 * HTTP at priority 999; a mock at the default priority wins).
 */
class Test_RTG_Advisor extends WP_UnitTestCase {

    private $calls = 0;
    private $last_request = null;
    private $last_headers = array();

    public function setUp(): void {
        parent::setUp();
        RTG_Activator::activate();
        RTG_Database::flush_cache();
        delete_option( RTG_Advisor::STATE_OPTION );
        remove_all_filters( 'pre_http_request' );
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rtg_ai_%' OR option_name LIKE '_transient_timeout_rtg_ai_%'" );
        $this->calls = 0;

        RTG_Database::insert_wheel( array( 'name' => '20" AT', 'stock_size' => '275/65R20', 'alt_sizes' => '', 'vehicles' => 'R1T,R1S' ) );
        RTG_Database::insert_wheel( array( 'name' => '21" AS', 'stock_size' => '255/45R21', 'alt_sizes' => '', 'vehicles' => 'R2' ) );
        RTG_Database::insert_tire( $this->tire( 'adv-eff', array( 'roamer_efficiency' => 2.81, 'roamer_total_km' => 100000, 'roamer_vehicle_count' => 65, 'price' => 320 ) ) );
        RTG_Database::insert_tire( $this->tire( 'adv-cheap', array( 'price' => 210 ) ) );
        RTG_Database::insert_tire( $this->tire( 'adv-weak', array( 'load_index' => '112', 'price' => 150 ) ) );
        RTG_Database::flush_cache();
    }

    private function tire( $id, $over = array() ) {
        return array_merge( array(
            'tire_id' => $id, 'size' => '275/65R20', 'diameter' => '20"', 'brand' => 'Brand', 'model' => 'Model ' . $id,
            'category' => 'All-Season', 'price' => 300, 'mileage_warranty' => 60000, 'weight_lb' => 40, 'three_pms' => 'No',
            'tread' => '10/32', 'load_index' => '116', 'max_load_lb' => 2756, 'load_range' => 'SL', 'speed_rating' => 'T',
            'psi' => '51', 'utqg' => '620 A B', 'tags' => '', 'link' => 'https://example.com/t', 'image' => '',
            'bundle_link' => '', 'sort_order' => 0, 'efficiency_score' => 75, 'efficiency_grade' => 'B',
        ), $over );
    }

    private function settings( $over ) {
        $settings = get_option( 'rtg_settings', array() );
        update_option( 'rtg_settings', array_merge( is_array( $settings ) ? $settings : array(), $over ) );
    }

    private function mock( $body, $code = 200 ) {
        add_filter( 'pre_http_request', function ( $preempt, $args, $url ) use ( $body, $code ) {
            $this->calls++;
            $this->last_request = json_decode( $args['body'], true );
            $this->last_headers = $args['headers'];
            return array( 'response' => array( 'code' => $code ), 'body' => is_string( $body ) ? $body : wp_json_encode( $body ) );
        }, 10, 3 );
    }

    private function answer( $data ) {
        return array(
            'model' => 'claude-opus-5', 'stop_reason' => 'end_turn',
            'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $data ) ) ),
            'usage' => array( 'input_tokens' => 1000, 'output_tokens' => 200, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 800 ),
        );
    }

    private function advise( $body ) {
        $request = new WP_REST_Request( 'POST', '/' . RTG_REST_API::NAMESPACE . '/advise' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( $body ) );
        return rest_get_server()->dispatch( $request );
    }

    // --- Settings ------------------------------------------------------

    public function test_defaults_enabled_without_a_key_and_opus_5() {
        $this->assertTrue( RTG_Advisor::is_enabled() );
        $this->assertFalse( RTG_Advisor::has_key() );
        $this->assertFalse( RTG_Advisor::is_live() );
        $this->assertSame( 'claude-opus-5', RTG_Advisor::model() );
        $this->assertSame( RTG_Advisor::DEFAULT_RATE_LIMIT, RTG_Advisor::rate_limit() );
    }

    public function test_settings_are_read_and_unknown_models_fall_back() {
        $this->settings( array( 'ai_enabled' => false, 'ai_api_key' => 'sk-x', 'ai_model' => 'claude-nope', 'ai_rate_limit' => 500 ) );
        $this->assertFalse( RTG_Advisor::is_enabled() );
        $this->assertTrue( RTG_Advisor::has_key() );
        $this->assertFalse( RTG_Advisor::is_live(), 'a key without the toggle is not live' );
        $this->assertSame( 'claude-opus-5', RTG_Advisor::model() );
        $this->assertSame( 60, RTG_Advisor::rate_limit() );
    }

    public function test_the_settings_form_saves_the_key_only_when_given_and_clears_on_request() {
        wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
        $admin = new RTG_Admin();

        $_POST = array( 'rows_per_page' => 12, 'ai_enabled' => '1', 'ai_api_key' => 'sk-first', 'ai_model' => 'claude-sonnet-5', 'ai_rate_limit' => '5' );
        $admin->save_settings_from_post();
        $s = get_option( 'rtg_settings' );
        $this->assertSame( 'sk-first', $s['ai_api_key'] );
        $this->assertSame( 'claude-sonnet-5', $s['ai_model'] );
        $this->assertSame( 5, $s['ai_rate_limit'] );
        $this->assertTrue( $s['ai_enabled'] );

        $_POST = array( 'rows_per_page' => 12, 'ai_enabled' => '1', 'ai_api_key' => '', 'ai_model' => 'claude-sonnet-5', 'ai_rate_limit' => '5' );
        $admin->save_settings_from_post();
        $this->assertSame( 'sk-first', get_option( 'rtg_settings' )['ai_api_key'], 'an empty field leaves the key alone' );

        $_POST = array( 'rows_per_page' => 12, 'ai_api_key' => '', 'ai_api_key_clear' => '1', 'ai_model' => 'claude-sonnet-5', 'ai_rate_limit' => '5' );
        $admin->save_settings_from_post();
        $s = get_option( 'rtg_settings' );
        $this->assertSame( '', $s['ai_api_key'], 'clearing is explicit' );
        $this->assertFalse( $s['ai_enabled'], 'an unchecked toggle turns the advisor off' );
        $_POST = array();
    }

    // --- Help me choose ------------------------------------------------

    public function test_without_a_key_the_route_answers_with_the_rules_and_logs_an_ai_event() {
        $response = $this->advise( array( 'vehicle' => 'R1', 'priorities' => array( 'efficiency' ), 'budget' => '' ) );
        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( 'rules', $data['source'] );
        $this->assertSame( 'adv-eff', $data['picks'][0]['tire_id'], 'the efficient tire leads on efficiency' );
        $ids = array_column( $data['picks'], 'tire_id' );
        $this->assertNotContains( 'adv-weak', $ids, 'a tire below the R1 floor is never picked' );
        $this->assertStringContainsString( '/tires/', $data['picks'][0]['tire']['url'] );
        $this->assertSame( 0, $this->calls, 'no HTTP without a key' );

        global $wpdb;
        $row = $wpdb->get_row( "SELECT search_query, search_type, result_count FROM {$wpdb->prefix}rtg_search_events ORDER BY id DESC LIMIT 1", ARRAY_A );
        $this->assertSame( 'ai', $row['search_type'] );
        $this->assertSame( 'R1 · range', $row['search_query'] );
        $this->assertSame( count( $data['picks'] ), (int) $row['result_count'] );
    }

    public function test_with_a_key_the_model_answers_and_the_answer_is_cached() {
        $this->settings( array( 'ai_api_key' => 'sk-test' ) );
        $this->mock( $this->answer( array(
            'summary' => 'Range first.',
            'picks'   => array(
                array( 'tire_id' => 'adv-cheap', 'headline' => 'Easy on the wallet', 'reason' => '$210 per tire.', 'tradeoff' => 'No efficiency data.' ),
                array( 'tire_id' => 'ghost-tire', 'headline' => 'Made up', 'reason' => 'x', 'tradeoff' => '' ),
            ),
        ) ) );

        $first = $this->advise( array( 'vehicle' => 'R1', 'priorities' => array( 'price' ) ) )->get_data();
        $this->assertSame( 'claude', $first['source'] );
        $this->assertSame( 'Range first.', $first['summary'] );
        $this->assertCount( 1, $first['picks'], 'the invented tire is dropped' );
        $this->assertSame( 'adv-cheap', $first['picks'][0]['tire_id'] );
        $this->assertSame( 210.0, (float) $first['picks'][0]['tire']['price'] );
        $this->assertArrayNotHasKey( 'cached', $first );

        $this->assertSame( 'claude-opus-5', $this->last_request['model'] );
        $this->assertSame( 'json_schema', $this->last_request['output_config']['format']['type'] );
        $this->assertSame( 'default', $this->last_request['fallbacks'] );
        $this->assertSame( 'sk-test', $this->last_headers['x-api-key'] );
        $payload = json_decode( $this->last_request['messages'][0]['content'], true );
        $this->assertSame( 116, $payload['owner']['minimum_load_index'] );
        $this->assertNotContains( 'adv-weak', array_column( $payload['candidates'], 'tire_id' ), 'the model never sees a tire that does not fit' );

        $second = $this->advise( array( 'vehicle' => 'R1', 'priorities' => array( 'price' ) ) )->get_data();
        $this->assertTrue( $second['cached'] );
        $this->assertSame( 1, $this->calls, 'the same question is served from the cache' );

        $state = RTG_Advisor::state();
        $this->assertSame( 'ok', $state['status'] );
        $this->assertSame( 1000, $state['usage']['input'] );
    }

    public function test_a_failed_call_falls_back_to_the_rules_and_records_the_error() {
        $this->settings( array( 'ai_api_key' => 'sk-bad' ) );
        $this->mock( array( 'error' => array( 'message' => 'invalid x-api-key' ) ), 401 );

        $data = $this->advise( array( 'vehicle' => 'R1' ) )->get_data();
        $this->assertTrue( $data['ok'] );
        $this->assertSame( 'rules', $data['source'] );
        $this->assertNotEmpty( $data['picks'] );

        $state = RTG_Advisor::state();
        $this->assertSame( 'error', $state['status'] );
        $this->assertStringContainsString( '401', $state['message'] );
    }

    public function test_the_route_is_off_when_the_advisor_is_off() {
        $this->settings( array( 'ai_enabled' => false ) );
        $this->assertSame( 404, $this->advise( array( 'vehicle' => 'R1' ) )->get_status() );
    }

    public function test_nothing_fits_is_an_honest_empty_answer() {
        $data = $this->advise( array( 'vehicle' => 'R1', 'budget' => '250', 'size' => '255/45R21' ) )->get_data();
        $this->assertTrue( $data['ok'] );
        $this->assertSame( array(), $data['picks'] );
        $this->assertStringContainsString( 'Nothing in the guide fits', $data['summary'] );
    }

    // --- What owners say -----------------------------------------------

    public function test_the_review_summary_needs_a_key_and_enough_written_reviews() {
        $route = '/' . RTG_REST_API::NAMESPACE . '/tires/adv-eff/review-summary';
        $this->assertSame( 404, rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) )->get_status(), 'no key: not available' );

        $this->settings( array( 'ai_api_key' => 'sk-test' ) );
        // Reviews with text are pending unless the reviewer is an admin; the summary reads approved ones only.
        RTG_Database::set_rating( 'adv-eff', $this->factory->user->create( array( 'role' => 'administrator' ) ), 5, 'Great', 'Quiet and efficient.' );
        $response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) );
        $this->assertSame( 200, $response->get_status() );
        $this->assertFalse( $response->get_data()['ok'], 'one written review is not enough' );
        $this->assertSame( 0, $this->calls );

        RTG_Database::set_rating( 'adv-eff', $this->factory->user->create( array( 'role' => 'administrator' ) ), 3, 'Meh', 'Range dropped a bit.' );
        $this->mock( $this->answer( array( 'summary' => 'Owners like the quiet; one saw less range.', 'pros' => array( 'Quiet' ), 'cons' => array( 'Some range loss' ) ) ) );
        $data = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) )->get_data();
        $this->assertTrue( $data['ok'] );
        $this->assertSame( 2, $data['based_on'] );
        $this->assertSame( array( 'Quiet' ), $data['pros'] );

        $again = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) )->get_data();
        $this->assertTrue( $again['cached'] );
        $this->assertSame( 1, $this->calls );
    }

    public function test_the_review_summary_prompt_never_carries_a_reviewer_name() {
        $this->settings( array( 'ai_api_key' => 'sk-test' ) );
        RTG_Database::set_rating( 'adv-eff', $this->factory->user->create( array( 'role' => 'administrator', 'display_name' => 'Jose Secret' ) ), 5, 'Great', 'Quiet.' );
        RTG_Database::set_rating( 'adv-eff', $this->factory->user->create( array( 'role' => 'administrator' ) ), 4, 'Fine', 'Fine tire.' );
        $this->mock( $this->answer( array( 'summary' => 's', 'pros' => array(), 'cons' => array() ) ) );
        rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/' . RTG_REST_API::NAMESPACE . '/tires/adv-eff/review-summary' ) );
        $this->assertStringNotContainsString( 'Jose Secret', $this->last_request['messages'][0]['content'] );
    }

    // --- In plain words ------------------------------------------------

    public function test_the_compare_summary_wants_two_to_four_known_tires() {
        $this->settings( array( 'ai_api_key' => 'sk-test' ) );
        $one = new WP_REST_Request( 'GET', '/' . RTG_REST_API::NAMESPACE . '/compare-summary' );
        $one->set_param( 'ids', 'adv-eff' );
        $this->assertSame( 400, rest_get_server()->dispatch( $one )->get_status() );

        $unknown = new WP_REST_Request( 'GET', '/' . RTG_REST_API::NAMESPACE . '/compare-summary' );
        $unknown->set_param( 'ids', 'nope-1,nope-2' );
        $this->assertSame( 404, rest_get_server()->dispatch( $unknown )->get_status() );

        $this->mock( $this->answer( array( 'paragraph' => 'The efficient one wins on range; the cheap one on price.' ) ) );
        $two = new WP_REST_Request( 'GET', '/' . RTG_REST_API::NAMESPACE . '/compare-summary' );
        $two->set_param( 'ids', 'adv-eff,adv-cheap' );
        $data = rest_get_server()->dispatch( $two )->get_data();
        $this->assertTrue( $data['ok'] );
        $this->assertStringContainsString( 'wins on range', $data['paragraph'] );

        $payload = json_decode( $this->last_request['messages'][0]['content'], true );
        $this->assertCount( 2, $payload['tires'] );
        $this->assertSame( 2.81, (float) $payload['tires'][0]['efficiency'] );
    }

    // --- Localization ---------------------------------------------------

    public function test_the_guide_and_compare_page_are_told_about_the_advisor() {
        $frontend = new RTG_Frontend();
        $frontend->render_shortcode( array() );
        $data = wp_scripts()->get_data( 'rtg-tire-guide', 'data' );
        $this->assertStringContainsString( '"advisor":{"enabled":true,"live":false,"url":"', $data );
        $this->assertStringContainsString( '"priorities":{"efficiency":"Range and efficiency"', $data );
        $this->assertStringContainsString( '"budgets":[{"value":"","label":"Any budget"}', $data );
    }
}
