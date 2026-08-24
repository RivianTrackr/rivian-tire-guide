<?php
/**
 * Tests for RTG_Coverage.
 *
 * "No retailer match" was one line covering several unrelated situations, and
 * only one of them — the retailer listing the tire under a different model
 * name — is something a person can act on. These cover the classification that
 * separates them, and in particular that the label names its evidence: a count
 * that says "listings" must count listings, not retailers.
 *
 * The classifier reads a prepared index rather than the database, so nothing
 * here touches storage.
 */
class Test_RTG_Coverage extends WP_UnitTestCase {

    /**
     * Candidate rows shaped as the sweep stores them, drawn from real titles.
     */
    private function rows() {
        return array(
            array( 'brand' => 'Nitto', 'model' => 'Recon Grappler A/T', 'size' => '275/65R20', 'advertiser_name' => 'The Tire Rack' ),
            array( 'brand' => 'Nitto', 'model' => 'Terra Grappler G3', 'size' => '275/65R20', 'advertiser_name' => 'The Tire Rack' ),
            array( 'brand' => 'Michelin', 'model' => 'Defender LTX M/S 2', 'size' => '305/45R22', 'advertiser_name' => 'SimpleTire' ),
            array( 'brand' => 'Goodyear', 'model' => 'Wrangler Territory', 'size' => '305/45R22', 'advertiser_name' => 'SimpleTire' ),
        );
    }

    private function index() {
        return RTG_Coverage::index_rows( $this->rows() );
    }

    private function tire( $brand, $model, $size ) {
        return array( 'tire_id' => 't', 'brand' => $brand, 'model' => $model, 'size' => $size );
    }

    // --- Classification ---

    /**
     * A fitment no product has arrived in is the sweep's gap, not the guide's.
     */
    public function test_absent_fitment_is_reported_as_such() {
        $result = RTG_Coverage::classify( $this->tire( 'Nokian', 'One H/T', '255/55R21' ), $this->index() );

        $this->assertSame( RTG_Coverage::GAP_SIZE_ABSENT, $result['code'] );
        $this->assertStringContainsString( '255/55R21', $result['label'] );
        $this->assertSame( array(), $result['near'] );
    }

    /**
     * A carried fitment without this brand names who did list it, so it's
     * obvious whether the retailer simply doesn't stock the brand.
     */
    public function test_absent_brand_names_the_retailers_that_did_list_the_fitment() {
        $result = RTG_Coverage::classify( $this->tire( 'BFGoodrich', 'Trail-Terrain T/A', '305/45R22' ), $this->index() );

        $this->assertSame( RTG_Coverage::GAP_BRAND_ABSENT, $result['code'] );
        $this->assertStringContainsString( 'SimpleTire', $result['label'] );
        $this->assertStringContainsString( 'BFGoodrich', $result['label'] );
    }

    /**
     * The count in that label counts listings. Counting the retailers instead
     * and calling them listings would say "1 listing" where two products
     * arrived, which reads as a far emptier queue than the one that exists.
     */
    public function test_absent_brand_counts_listings_not_retailers() {
        $result = RTG_Coverage::classify( $this->tire( 'BFGoodrich', 'Trail-Terrain T/A', '305/45R22' ), $this->index() );

        $this->assertStringContainsString( '2 305/45R22 listing(s)', $result['label'] );
    }

    /**
     * The actionable case: the retailer has the tire under another name, and
     * the names it uses are shown so the guide can be aligned to them.
     */
    public function test_model_mismatch_shows_what_the_retailer_calls_it() {
        $result = RTG_Coverage::classify( $this->tire( 'Nitto', 'Trail Grappler M/T', '275/65R20' ), $this->index() );

        $this->assertSame( RTG_Coverage::GAP_MODEL_MISMATCH, $result['code'] );

        $models = wp_list_pluck( $result['near'], 'model' );
        $this->assertContains( 'Recon Grappler A/T', $models );
        $this->assertContains( 'Terra Grappler G3', $models );
    }

    /**
     * Near matches carry who lists them — the same model from both retailers
     * should read as one entry, not two.
     */
    public function test_near_matches_deduplicate_on_model() {
        $rows   = $this->rows();
        $rows[] = array( 'brand' => 'Nitto', 'model' => 'Recon Grappler A/T', 'size' => '275/65R20', 'advertiser_name' => 'SimpleTire' );

        $result = RTG_Coverage::classify(
            $this->tire( 'Nitto', 'Trail Grappler M/T', '275/65R20' ),
            RTG_Coverage::index_rows( $rows )
        );

        $models = wp_list_pluck( $result['near'], 'model' );
        $this->assertSame( array( 'Recon Grappler A/T', 'Terra Grappler G3' ), $models );
        $this->assertSame( array( 'The Tire Rack', 'SimpleTire' ), $result['near'][0]['advertisers'] );
    }

    /**
     * A guide row the matcher can't key on is the guide's own problem, and
     * saying so beats reporting it as a retailer gap.
     */
    public function test_unreadable_guide_size_is_reported_against_the_guide() {
        $result = RTG_Coverage::classify( $this->tire( 'Pirelli', 'Scorpion', 'n/a' ), $this->index() );

        $this->assertSame( RTG_Coverage::GAP_SIZE_UNREADABLE, $result['code'] );
    }

    /**
     * Same for a row with no brand: the match key needs one.
     */
    public function test_missing_brand_is_reported_against_the_guide() {
        $result = RTG_Coverage::classify( $this->tire( '', 'Mystery', '275/65R20' ), $this->index() );

        $this->assertSame( RTG_Coverage::GAP_BRAND_MISSING, $result['code'] );
    }

    // --- Indexing ---

    /**
     * Sizes are indexed canonically, so an "LT275/65R20" listing counts toward
     * the "275/65R20" the guide asks about.
     */
    public function test_index_normalizes_sizes() {
        $index = RTG_Coverage::index_rows( array(
            array( 'brand' => 'Nitto', 'model' => 'Ridge Grappler', 'size' => 'LT275/65R20', 'advertiser_name' => 'The Tire Rack' ),
        ) );

        $this->assertArrayHasKey( '275/65R20', $index['sizes'] );
        $this->assertSame( 1, $index['sizes']['275/65R20']['count'] );
    }

    /**
     * A listing whose size never parsed can't stand as evidence for any
     * fitment, so it is left out rather than counted against one.
     */
    public function test_index_drops_rows_with_no_readable_size() {
        $index = RTG_Coverage::index_rows( array(
            array( 'brand' => 'Nokian', 'model' => 'One H/T', 'size' => '', 'advertiser_name' => 'SimpleTire' ),
        ) );

        $this->assertSame( array(), $index['sizes'] );
    }

    // --- Summary ---

    /**
     * The summary orders by size so the biggest gap leads.
     */
    public function test_summary_counts_each_gap() {
        $counts = RTG_Coverage::summarize( array(
            array( 'code' => RTG_Coverage::GAP_MODEL_MISMATCH ),
            array( 'code' => RTG_Coverage::GAP_MODEL_MISMATCH ),
            array( 'code' => RTG_Coverage::GAP_SIZE_ABSENT ),
        ) );

        $this->assertSame( RTG_Coverage::GAP_MODEL_MISMATCH, array_key_first( $counts ) );
        $this->assertSame( 2, $counts[ RTG_Coverage::GAP_MODEL_MISMATCH ] );
        $this->assertSame( 1, $counts[ RTG_Coverage::GAP_SIZE_ABSENT ] );
    }
}
