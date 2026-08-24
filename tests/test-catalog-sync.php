<?php
/**
 * Tests for the affiliate catalog discovery pipeline.
 *
 * Two invariants matter more than anything else here:
 *
 * 1. A dismissed candidate never comes back. If it does, the review queue
 *    fills with rejects and stops being read, which defeats the feature.
 * 2. One physical tire is recognized as itself across retailers, whose
 *    punctuation of a model name is not consistent.
 */
class Test_RTG_Catalog_Sync extends WP_UnitTestCase {

    /**
     * Build a candidate row for upsert().
     */
    private function candidate( $overrides = array() ) {
        return array_merge( array(
            'source'          => 'fixture',
            'advertiser_id'   => '1234',
            'advertiser_name' => 'Test Retailer',
            'external_id'     => 'TEST-1',
            'brand'           => 'Michelin',
            'model'           => 'Defender LTX M/S2',
            'size'            => '275/65R18',
            'load_index'      => '116',
            'load_range'      => '',
            'speed_rating'    => 'T',
            'price'           => 289.99,
            'link'            => 'https://www.tirerack.com/tires/test-1',
            'image'           => '',
            'match_key'       => 'michelin|defenderltxms2|275/65R18',
            'qualifies'       => true,
            'fail_reasons'    => array(),
            'matched_tire_id' => '',
            'raw'             => array(),
        ), $overrides );
    }

    // --- match_key() ---

    /**
     * Retailers punctuate model names inconsistently; the key has to see
     * through that or the same tire looks new at every retailer.
     */
    public function test_match_key_ignores_model_punctuation_and_case() {
        $a = RTG_Catalog_Sync::match_key( 'Michelin', 'Defender LTX M/S 2', '275/65R18' );
        $b = RTG_Catalog_Sync::match_key( 'michelin', 'Defender LTX M/S2', 'LT275/65R18' );
        $c = RTG_Catalog_Sync::match_key( 'MICHELIN', 'Defender-LTX-M/S2', '275/65 R18' );

        $this->assertSame( $a, $b );
        $this->assertSame( $b, $c );
    }

    /**
     * Different tires must not collide onto one key.
     */
    public function test_match_key_separates_distinct_tires() {
        $defender  = RTG_Catalog_Sync::match_key( 'Michelin', 'Defender LTX M/S2', '275/65R18' );
        $primacy   = RTG_Catalog_Sync::match_key( 'Michelin', 'Primacy Tour', '275/65R18' );
        $other_size = RTG_Catalog_Sync::match_key( 'Michelin', 'Defender LTX M/S2', '275/60R20' );

        $this->assertNotSame( $defender, $primacy );
        $this->assertNotSame( $defender, $other_size );
    }

    /**
     * Without a brand or a readable size there is nothing to key on, and a
     * blank key must not be handed out — every keyless row would match.
     */
    public function test_match_key_is_empty_without_brand_or_size() {
        $this->assertSame( '', RTG_Catalog_Sync::match_key( '', 'Defender', '275/65R18' ) );
        $this->assertSame( '', RTG_Catalog_Sync::match_key( 'Michelin', 'Defender', 'not-a-size' ) );
    }

    // --- RTG_Candidates upsert semantics ---

    /**
     * A product seen for the first time is queued for review.
     */
    public function test_first_sighting_is_queued_and_flagged_as_newly_surfaced() {
        $result = RTG_Candidates::upsert( $this->candidate() );

        $this->assertTrue( $result['is_new'] );
        $this->assertTrue( $result['newly_surfaced'] );
        $this->assertSame( RTG_Candidates::STATUS_NEW, $result['status'] );
    }

    /**
     * Seeing the same product again updates the row instead of duplicating it,
     * and does not re-announce it as newly surfaced.
     */
    public function test_second_sighting_updates_rather_than_duplicating() {
        $first = RTG_Candidates::upsert( $this->candidate() );

        $second = RTG_Candidates::upsert( $this->candidate( array( 'price' => 249.99 ) ) );

        $this->assertSame( $first['id'], $second['id'] );
        $this->assertFalse( $second['is_new'] );
        $this->assertFalse( $second['newly_surfaced'] );

        $stored = RTG_Candidates::get( $first['id'] );
        $this->assertEquals( 249.99, $stored['price'] );
    }

    /**
     * The invariant the whole queue depends on: once dismissed, a candidate
     * stays dismissed no matter how many times the sync sees it again.
     */
    public function test_a_dismissed_candidate_never_resurfaces() {
        $created = RTG_Candidates::upsert( $this->candidate() );
        RTG_Candidates::set_status( $created['id'], RTG_Candidates::STATUS_DISMISSED );

        $reseen = RTG_Candidates::upsert( $this->candidate() );

        $this->assertSame( RTG_Candidates::STATUS_DISMISSED, $reseen['status'] );
        $this->assertFalse( $reseen['newly_surfaced'] );

        $queue = RTG_Candidates::query( array( 'status' => RTG_Candidates::STATUS_NEW ) );
        $this->assertSame( array(), wp_list_pluck( $queue, 'id' ) );
    }

    /**
     * An imported candidate likewise keeps its status, so a tire already added
     * to the guide can't reappear as something to add.
     */
    public function test_an_imported_candidate_keeps_its_status() {
        $created = RTG_Candidates::upsert( $this->candidate() );
        RTG_Candidates::set_status( $created['id'], RTG_Candidates::STATUS_IMPORTED );

        $reseen = RTG_Candidates::upsert( $this->candidate() );

        $this->assertSame( RTG_Candidates::STATUS_IMPORTED, $reseen['status'] );
    }

    /**
     * A machine-assigned status, by contrast, is recomputed — a tire that was
     * rejected under a stricter floor surfaces once the floor is lowered.
     */
    public function test_a_rejected_candidate_surfaces_when_it_starts_qualifying() {
        $rejected = RTG_Candidates::upsert( $this->candidate( array(
            'qualifies'    => false,
            'fail_reasons' => array( array( 'code' => 'load_index_low', 'label' => 'too low' ) ),
        ) ) );

        $this->assertSame( RTG_Candidates::STATUS_REJECTED, $rejected['status'] );
        $this->assertFalse( $rejected['newly_surfaced'] );

        $now_qualifying = RTG_Candidates::upsert( $this->candidate() );

        $this->assertSame( RTG_Candidates::STATUS_NEW, $now_qualifying['status'] );
        $this->assertTrue( $now_qualifying['newly_surfaced'] );
    }

    /**
     * A qualifying tire already in the guide is filed as existing, not queued.
     */
    public function test_a_tire_already_in_the_guide_is_not_queued() {
        $result = RTG_Candidates::upsert( $this->candidate( array( 'matched_tire_id' => 'tire42' ) ) );

        $this->assertSame( RTG_Candidates::STATUS_EXISTING, $result['status'] );
        $this->assertFalse( $result['newly_surfaced'] );
    }

    /**
     * A product with no external ID can't be recognized on a later run, so it
     * is refused rather than stored as an unmatchable duplicate magnet.
     */
    public function test_a_product_without_an_external_id_is_refused() {
        $result = RTG_Candidates::upsert( $this->candidate( array( 'external_id' => '' ) ) );

        $this->assertSame( 0, $result['id'] );
        $this->assertFalse( $result['is_new'] );
    }

    // --- Fixture source ---

    /**
     * The bundled sample parses, so the "run it and see" path in the admin UI
     * works on a fresh install with nothing configured.
     */
    public function test_the_bundled_sample_fixture_loads() {
        $source   = new RTG_Catalog_Source_Fixture();
        $products = $source->fetch( array( '275/65R18' ) );

        $this->assertSame( '', $source->get_last_error() );
        $this->assertNotEmpty( $products );
        $this->assertArrayHasKey( 'external_id', $products[0] );
        $this->assertArrayHasKey( 'title', $products[0] );
    }

    /**
     * Document-level advertiser details apply to products that don't carry
     * their own, so a single-retailer file needn't repeat them per row.
     */
    public function test_fixture_products_inherit_the_document_advertiser() {
        $path = wp_tempnam( 'rtg-fixture' );
        file_put_contents( $path, wp_json_encode( array(
            'advertiser_name' => 'Tire Rack',
            'advertiser_id'   => '5555',
            'products'        => array(
                array( 'external_id' => 'A-1', 'title' => 'Michelin Defender LTX M/S2 275/65R18 116T' ),
                array( 'external_id' => 'A-2', 'title' => 'Toyo Open Country 275/60R20 115T', 'advertiser_name' => 'SimpleTire' ),
            ),
        ) ) );

        $source   = new RTG_Catalog_Source_Fixture( $path );
        $products = $source->fetch( array() );

        $this->assertCount( 2, $products );
        $this->assertSame( 'Tire Rack', $products[0]['advertiser_name'] );
        $this->assertSame( '5555', $products[0]['advertiser_id'] );
        $this->assertSame( 'SimpleTire', $products[1]['advertiser_name'] );

        unlink( $path );
    }

    /**
     * A product with no external ID is dropped at the source, before it can
     * reach the queue.
     */
    public function test_fixture_skips_products_without_an_external_id() {
        $path = wp_tempnam( 'rtg-fixture' );
        file_put_contents( $path, wp_json_encode( array(
            array( 'title' => 'Nameless tire 275/65R18 116T' ),
            array( 'external_id' => 'B-1', 'title' => 'Michelin Defender LTX M/S2 275/65R18 116T' ),
        ) ) );

        $source   = new RTG_Catalog_Source_Fixture( $path );
        $products = $source->fetch( array() );

        $this->assertCount( 1, $products );
        $this->assertSame( 'B-1', $products[0]['external_id'] );

        unlink( $path );
    }

    /**
     * A broken fixture reports the failure instead of throwing, so one bad
     * source can't take the whole run down.
     */
    public function test_a_missing_fixture_reports_an_error_without_throwing() {
        $source   = new RTG_Catalog_Source_Fixture( '/nonexistent/path/to/fixture.json' );
        $products = $source->fetch( array() );

        $this->assertSame( array(), $products );
        $this->assertNotSame( '', $source->get_last_error() );
    }
}
