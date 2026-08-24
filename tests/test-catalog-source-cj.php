<?php
/**
 * Tests for RTG_Catalog_Source_CJ's response handling.
 *
 * CJ's schema reference is behind a JavaScript-rendered portal, so the shipped
 * query document and field names are a best effort. That makes the mapping the
 * riskiest part of the adapter, and the part most worth pinning: these tests
 * cover the shapes a GraphQL product search plausibly returns, so a correction
 * to one spelling can't silently break another.
 *
 * Nothing here performs a network request.
 */
class Test_RTG_Catalog_Source_CJ extends WP_UnitTestCase {

    // --- extract_result_list() ---

    /**
     * The documented shape: a named list under the query field.
     */
    public function test_extract_finds_a_named_result_list() {
        $data = array(
            'shoppingProducts' => array(
                'totalCount' => 2,
                'resultList' => array(
                    array( 'id' => 'A', 'title' => 'First' ),
                    array( 'id' => 'B', 'title' => 'Second' ),
                ),
            ),
        );

        $nodes = RTG_Catalog_Source_CJ::extract_result_list( $data );

        $this->assertCount( 2, $nodes );
        $this->assertSame( 'A', $nodes[0]['id'] );
    }

    /**
     * A Relay-style connection is found just as well, since the wrapper name
     * was one of the details the portal wouldn't confirm.
     */
    public function test_extract_finds_a_relay_style_connection() {
        $data = array(
            'shoppingProducts' => array(
                'edges' => array(
                    array( 'node' => array( 'id' => 'A', 'title' => 'First' ) ),
                ),
            ),
        );

        $nodes = RTG_Catalog_Source_CJ::extract_result_list( $data );

        $this->assertCount( 1, $nodes );
        $this->assertArrayHasKey( 'node', $nodes[0] );
    }

    /**
     * An empty result set yields an empty array, not a warning.
     */
    public function test_extract_handles_an_empty_payload() {
        $this->assertSame( array(), RTG_Catalog_Source_CJ::extract_result_list( array() ) );
        $this->assertSame( array(), RTG_Catalog_Source_CJ::extract_result_list( null ) );
        $this->assertSame(
            array(),
            RTG_Catalog_Source_CJ::extract_result_list( array( 'shoppingProducts' => array( 'totalCount' => 0 ) ) )
        );
    }

    // --- pluck() ---

    /**
     * Candidate paths are tried in order and the first present one wins.
     */
    public function test_pluck_prefers_the_earlier_path() {
        $node = array( 'clickUrl' => 'https://tracked.example', 'link' => 'https://plain.example' );

        $this->assertSame(
            'https://tracked.example',
            RTG_Catalog_Source_CJ::pluck( $node, array( 'clickUrl', 'link' ) )
        );
    }

    /**
     * A later path is used when the earlier one is absent.
     */
    public function test_pluck_falls_through_to_a_later_path() {
        $node = array( 'link' => 'https://plain.example' );

        $this->assertSame(
            'https://plain.example',
            RTG_Catalog_Source_CJ::pluck( $node, array( 'clickUrl', 'link' ) )
        );
    }

    /**
     * Dotted paths reach nested values, which is how a money object is read.
     */
    public function test_pluck_reads_a_nested_path() {
        $node = array( 'price' => array( 'amount' => '289.99', 'currency' => 'USD' ) );

        $this->assertSame( '289.99', RTG_Catalog_Source_CJ::pluck( $node, array( 'price.amount' ) ) );
    }

    /**
     * Nothing matching returns the fallback rather than a partial value.
     */
    public function test_pluck_returns_the_fallback_when_nothing_matches() {
        $this->assertSame( '', RTG_Catalog_Source_CJ::pluck( array(), array( 'a', 'b.c' ) ) );
        $this->assertSame( 0, RTG_Catalog_Source_CJ::pluck( array(), array( 'price' ), 0 ) );
    }

    // --- map_product() ---

    /**
     * A product in the shape the shipped query asks for maps cleanly.
     */
    public function test_map_product_reads_the_expected_shape() {
        $mapped = RTG_Catalog_Source_CJ::map_product( array(
            'id'             => 'TR-99',
            'advertiserId'   => '1463221',
            'advertiserName' => 'Tire Rack',
            'title'          => 'Michelin Defender LTX M/S2 275/65R18 116T',
            'brand'          => 'Michelin',
            'link'           => 'https://www.tirerack.com/tires/tr-99',
            'imageLink'      => 'https://img.example/tr-99.jpg',
            'price'          => array( 'amount' => '289.99', 'currency' => 'USD' ),
        ) );

        $this->assertSame( 'TR-99', $mapped['external_id'] );
        $this->assertSame( 'Michelin', $mapped['brand'] );
        $this->assertSame( 'Tire Rack', $mapped['advertiser_name'] );
        $this->assertSame( 'https://www.tirerack.com/tires/tr-99', $mapped['link'] );
        $this->assertEquals( 289.99, $mapped['price'] );
    }

    /**
     * A trackable URL is preferred over the advertiser's own product page — an
     * untracked link earns no commission, which would quietly defeat the point.
     */
    public function test_map_product_prefers_the_trackable_link() {
        $mapped = RTG_Catalog_Source_CJ::map_product( array(
            'id'       => 'X-1',
            'title'    => 'Some tire',
            'linkCode' => array( 'clickUrl' => 'https://www.anrdoezrs.net/click-123' ),
            'link'     => 'https://www.tirerack.com/tires/x-1',
        ) );

        $this->assertSame( 'https://www.anrdoezrs.net/click-123', $mapped['link'] );
    }

    /**
     * A Relay node wrapper is unwrapped before mapping.
     */
    public function test_map_product_unwraps_a_relay_node() {
        $mapped = RTG_Catalog_Source_CJ::map_product( array(
            'node' => array( 'id' => 'N-1', 'title' => 'Wrapped tire', 'brand' => 'Toyo' ),
        ) );

        $this->assertSame( 'N-1', $mapped['external_id'] );
        $this->assertSame( 'Toyo', $mapped['brand'] );
    }

    /**
     * Alternate field spellings still map, so a schema that differs from the
     * shipped query costs a blank column at worst rather than a failed run.
     */
    public function test_map_product_accepts_alternate_field_names() {
        $mapped = RTG_Catalog_Source_CJ::map_product( array(
            'productId'    => 'ALT-1',
            'name'         => 'Alternate spelling tire',
            'manufacturer' => 'Yokohama',
            'buyUrl'       => 'https://example.test/buy',
            'imageUrl'     => 'https://example.test/img.jpg',
            'currentPrice' => '412.00',
            'partnerId'    => '5660604',
        ) );

        $this->assertSame( 'ALT-1', $mapped['external_id'] );
        $this->assertSame( 'Alternate spelling tire', $mapped['title'] );
        $this->assertSame( 'Yokohama', $mapped['brand'] );
        $this->assertSame( 'https://example.test/buy', $mapped['link'] );
        $this->assertEquals( 412.00, $mapped['price'] );
    }

    /**
     * A missing advertiser name is filled in from the configured list, so the
     * queue names a retailer even when the feed doesn't.
     */
    public function test_map_product_fills_a_missing_advertiser_name() {
        $mapped = RTG_Catalog_Source_CJ::map_product(
            array( 'id' => 'S-1', 'title' => 'Some tire', 'advertiserId' => '5660604' ),
            array( '5660604' => 'SimpleTire' )
        );

        $this->assertSame( 'SimpleTire', $mapped['advertiser_name'] );
    }

    /**
     * A node with no usable identifier maps to an empty external_id, which is
     * what fetch() filters on — such a product can't be recognized on a later
     * run, so it must not reach the queue.
     */
    public function test_map_product_yields_no_id_for_an_unidentifiable_node() {
        $mapped = RTG_Catalog_Source_CJ::map_product( array( 'title' => 'Anonymous tire' ) );

        $this->assertSame( '', $mapped['external_id'] );
    }

    /**
     * A CJ product flows through the qualifier the same as any other source —
     * the adapter's output shape is what the pipeline already understands.
     */
    public function test_a_mapped_product_qualifies_through_the_normal_rules() {
        $mapped = RTG_Catalog_Source_CJ::map_product( array(
            'id'    => 'TR-99',
            'title' => 'Michelin Defender LTX M/S2 275/65R18 116T',
            'brand' => 'Michelin',
        ) );

        $result = RTG_Tire_Qualifier::evaluate( $mapped, array(
            'sizes'          => array( '275/65R18' ),
            'min_load_index' => 112,
            'load_ranges'    => array(),
            'speed_ratings'  => array( 'T' ),
        ) );

        $this->assertTrue( $result['qualifies'] );
        $this->assertSame( '275/65R18', $result['size'] );
        $this->assertSame( '116', $result['load_index'] );
    }

    // --- describe_graphql_errors() ---

    /**
     * CJ's own error text is passed through, because it names the offending
     * field and that is what the query document needs corrected.
     */
    public function test_graphql_errors_are_reported_verbatim() {
        $message = RTG_Catalog_Source_CJ::describe_graphql_errors( array(
            array( 'message' => "Cannot query field 'imageLink' on type 'ShoppingProduct'" ),
        ) );

        $this->assertStringContainsString( 'imageLink', $message );
        $this->assertStringContainsString( 'ShoppingProduct', $message );
    }

    /**
     * A malformed errors array still yields a usable message.
     */
    public function test_graphql_errors_handles_an_unusable_payload() {
        $this->assertStringContainsString(
            'unspecified',
            RTG_Catalog_Source_CJ::describe_graphql_errors( array() )
        );
    }

    // --- Configuration ---

    /**
     * Without credentials the source reports itself unconfigured, which is how
     * the sync keeps it out of the run rather than failing every time.
     */
    public function test_is_not_configured_without_credentials() {
        update_option( 'rtg_settings', array( 'cj_enabled' => true, 'cj_company_id' => '', 'cj_pat' => '' ) );

        $this->assertFalse( RTG_Catalog_Source_CJ::is_configured() );
    }

    /**
     * Credentials alone are enough; the toggle can still switch it off.
     */
    public function test_configuration_respects_the_enable_toggle() {
        update_option( 'rtg_settings', array(
            'cj_enabled'    => true,
            'cj_company_id' => '1234567',
            'cj_pat'        => 'token',
        ) );
        $this->assertTrue( RTG_Catalog_Source_CJ::is_configured() );

        update_option( 'rtg_settings', array(
            'cj_enabled'    => false,
            'cj_company_id' => '1234567',
            'cj_pat'        => 'token',
        ) );
        $this->assertFalse( RTG_Catalog_Source_CJ::is_configured() );
    }

    /**
     * The advertiser list parses "id|Name" lines and ignores junk, falling back
     * to the defaults when nothing usable is configured.
     */
    public function test_advertisers_parse_from_settings() {
        update_option( 'rtg_settings', array(
            'cj_advertisers' => "1463221|Tire Rack\n5660604|SimpleTire\n\nnot-an-id",
        ) );

        $advertisers = RTG_Catalog_Source_CJ::get_advertisers();

        $this->assertSame( 'Tire Rack', $advertisers['1463221'] );
        $this->assertSame( 'SimpleTire', $advertisers['5660604'] );
        $this->assertCount( 2, $advertisers );

        update_option( 'rtg_settings', array( 'cj_advertisers' => '   ' ) );
        $this->assertSame( RTG_Catalog_Source_CJ::DEFAULT_ADVERTISERS, RTG_Catalog_Source_CJ::get_advertisers() );
    }

    /**
     * An unconfigured fetch fails fast with an explanation instead of issuing
     * a doomed request.
     */
    public function test_fetch_without_credentials_reports_rather_than_requesting() {
        update_option( 'rtg_settings', array( 'cj_enabled' => true, 'cj_company_id' => '', 'cj_pat' => '' ) );

        $source   = new RTG_Catalog_Source_CJ();
        $products = $source->fetch( array( '275/65R18' ) );

        $this->assertSame( array(), $products );
        $this->assertStringContainsString( 'not configured', $source->get_last_error() );
    }

    // --- Truncation detection ---

    /**
     * The match count CJ reports is read from the response.
     *
     * Without it a capped response is indistinguishable from a complete one,
     * which is how a Michelin Defender plainly listed on Tire Rack came to show
     * as "no retailer match": the size had more products than the per-request
     * limit and the remainder was discarded in silence.
     */
    public function test_reads_the_reported_match_count() {
        $this->assertSame(
            412,
            RTG_Catalog_Source_CJ::extract_total_count(
                array( 'shoppingProducts' => array( 'totalCount' => 412, 'resultList' => array( array( 'id' => 'a' ) ) ) )
            )
        );
    }

    /**
     * Alternate spellings are accepted, as elsewhere in the mapping.
     */
    public function test_reads_an_alternately_named_match_count() {
        $this->assertSame(
            7,
            RTG_Catalog_Source_CJ::extract_total_count(
                array( 'shoppingProducts' => array( 'total' => 7, 'resultList' => array() ) )
            )
        );
    }

    /**
     * A response that doesn't report a count yields null, not zero — "didn't
     * say" and "said none" must not be confused, or every complete response
     * would look truncated.
     */
    public function test_an_absent_match_count_is_null_not_zero() {
        $this->assertNull(
            RTG_Catalog_Source_CJ::extract_total_count(
                array( 'shoppingProducts' => array( 'resultList' => array( array( 'id' => 'a' ) ) ) )
            )
        );
        $this->assertNull( RTG_Catalog_Source_CJ::extract_total_count( null ) );
    }

    /**
     * A genuine zero is a count, and survives as one.
     */
    public function test_a_zero_match_count_is_preserved() {
        $this->assertSame(
            0,
            RTG_Catalog_Source_CJ::extract_total_count(
                array( 'shoppingProducts' => array( 'totalCount' => 0, 'resultList' => array() ) )
            )
        );
    }

    /**
     * The per-size limit defaults high enough to cover a real fitment.
     *
     * The original 100 was sized for an assumption about catalog depth that
     * turned out to be wrong by an order of magnitude.
     */
    public function test_the_per_size_limit_covers_a_real_fitment() {
        $this->assertGreaterThanOrEqual( 500, RTG_Catalog_Source_CJ::DEFAULT_LIMIT );
    }
}
