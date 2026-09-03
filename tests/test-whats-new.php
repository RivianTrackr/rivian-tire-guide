<?php
/**
 * Tests for RTG_Whats_New inside WordPress: the cache, the REST route and
 * the localized settings the guide's pill reads. The parser and renderer
 * are pinned on plain PHP in tests/contract/whats-new.php.
 */
class Test_RTG_Whats_New extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        delete_transient( RTG_Whats_New::TRANSIENT );
    }

    public function test_the_shipped_notes_parse_and_are_cached_by_version_and_mtime() {
        $releases = RTG_Whats_New::get_releases();
        $this->assertNotEmpty( $releases );
        $this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $releases[0]['version'] );

        $cached = get_transient( RTG_Whats_New::TRANSIENT );
        $this->assertSame( RTG_VERSION . ':' . filemtime( RTG_Whats_New::file_path() ), $cached['key'] );
        $this->assertSame( $releases, $cached['releases'] );
    }

    public function test_a_cache_from_another_version_is_ignored() {
        set_transient( RTG_Whats_New::TRANSIENT, array( 'key' => '0.0.0:1', 'releases' => array( array( 'version' => '0.0.0', 'date' => '2000-01-01', 'intro' => '', 'items' => array() ) ) ) );
        $this->assertNotSame( '0.0.0', RTG_Whats_New::latest_version() );
    }

    public function test_the_rest_route_serves_rendered_releases() {
        $request  = new WP_REST_Request( 'GET', '/' . RTG_REST_API::NAMESPACE . RTG_Whats_New::REST_ROUTE );
        $response = rest_get_server()->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertSame( RTG_Whats_New::latest_version(), $data['latest'] );
        $this->assertSame( RTG_Whats_New::url(), $data['url'] );
        $this->assertTrue( $data['releases'][0]['latest'] );
        $this->assertArrayHasKey( 'lead', $data['releases'][0]['items'][0] );
        $this->assertArrayHasKey( 'date_display', $data['releases'][0] );
    }

    public function test_the_page_url_and_query_var_are_registered() {
        $this->assertSame( home_url( '/tire-guide/whats-new/' ), RTG_Whats_New::url() );
        $this->assertContains( RTG_Whats_New::QUERY_VAR, apply_filters( 'query_vars', array() ) );
    }

    public function test_the_guide_localizes_the_pill_settings() {
        $frontend = new RTG_Frontend();
        $frontend->render_shortcode( array() );
        $data = wp_scripts()->get_data( 'rtg-tire-guide', 'data' );

        $this->assertStringContainsString( '"whatsNewUrl":"' . RTG_Whats_New::url() . '"', $data );
        $this->assertStringContainsString( '"whatsNewVersion":"' . RTG_Whats_New::latest_version() . '"', $data );
        $this->assertStringContainsString( '"whatsNewRest":', $data );
    }
}
