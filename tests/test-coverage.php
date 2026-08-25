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
     * Sharing a brand and a fitment is not being the same tire.
     *
     * This is the flaw the first version shipped with: it reported a Michelin
     * Defender LTX M/S2 as "listed, but as Pilot Sport S 5" — a summer
     * performance tire — because both are Michelins in 305/45R22. Nothing had
     * checked the model at all.
     */
    public function test_a_different_model_from_the_same_brand_is_not_a_name_variant() {
        // Only the Pilot Sport listing — the shared fixture also carries a
        // "Defender LTX M/S 2", which is this model spelled with a space and
        // correctly classifies as a variant. First execution of this suite
        // caught the fixture contradicting the test's own name.
        $index = RTG_Coverage::index_rows( array(
            array( 'brand' => 'Michelin', 'model' => 'Pilot Sport S 5', 'size' => '305/45R22', 'advertiser_name' => 'The Tire Rack' ),
        ) );

        $result = RTG_Coverage::classify( $this->tire( 'Michelin', 'Defender LTX M/S2', '305/45R22' ), $index );

        $this->assertSame( RTG_Coverage::GAP_MODEL_ABSENT, $result['code'] );
        $this->assertStringContainsString( 'none of them', $result['label'] );
    }

    /**
     * The retailers named in that label are the ones selling this brand in
     * this fitment. Naming whoever sells the *size* instead pairs a
     * brand-specific count with a size-wide attribution, which reads as one
     * fact and is two.
     */
    public function test_absent_model_attributes_only_this_brands_listings() {
        $index = RTG_Coverage::index_rows( array(
            array( 'brand' => 'Michelin', 'model' => 'Pilot Sport S 5', 'size' => '305/45R22', 'advertiser_name' => 'The Tire Rack' ),
            array( 'brand' => 'Goodyear', 'model' => 'Wrangler Territory', 'size' => '305/45R22', 'advertiser_name' => 'SimpleTire' ),
        ) );

        $result = RTG_Coverage::classify( $this->tire( 'Michelin', 'Defender LTX M/S2', '305/45R22' ), $index );

        $this->assertStringContainsString( 'The Tire Rack', $result['label'] );
        $this->assertStringNotContainsString( 'SimpleTire', $result['label'] );
    }

    /**
     * Model codes one character apart are different tires, so no edit-distance
     * rule may promote them. NT420V and NT421Q are the real pair that ruled
     * edit distance out.
     */
    public function test_model_codes_that_differ_by_a_digit_are_not_variants() {
        $index = RTG_Coverage::index_rows( array(
            array( 'brand' => 'Nitto', 'model' => 'NT421Q', 'size' => '275/60R20', 'advertiser_name' => 'SimpleTire' ),
        ) );

        $result = RTG_Coverage::classify( $this->tire( 'Nitto', 'NT420V', '275/60R20' ), $index );

        $this->assertSame( RTG_Coverage::GAP_MODEL_ABSENT, $result['code'] );
    }

    /**
     * Sharing leading words isn't enough either — "Open Country R/T Trail" and
     * "Open Country A/T III EV" are two words alike and two different tires.
     */
    public function test_shared_words_alone_do_not_make_a_variant() {
        $index = RTG_Coverage::index_rows( array(
            array( 'brand' => 'Toyo', 'model' => 'Open Country A/T III EV', 'size' => '275/65R20', 'advertiser_name' => 'The Tire Rack' ),
        ) );

        $result = RTG_Coverage::classify( $this->tire( 'Toyo', 'Open Country R/T Trail', '275/65R20' ), $index );

        $this->assertSame( RTG_Coverage::GAP_MODEL_ABSENT, $result['code'] );
        $this->assertLessThan( RTG_Coverage::VARIANT_THRESHOLD, $result['near'][0]['similarity'] );
    }

    /**
     * The actionable case: a name that really is this model spelled longer.
     */
    public function test_a_name_containing_the_model_is_reported_as_a_variant() {
        $index = RTG_Coverage::index_rows( array(
            array( 'brand' => 'Nitto', 'model' => 'Ridge Grappler LT', 'size' => '275/65R20', 'advertiser_name' => 'The Tire Rack' ),
        ) );

        $result = RTG_Coverage::classify( $this->tire( 'Nitto', 'Ridge Grappler', '275/65R20' ), $index );

        $this->assertSame( RTG_Coverage::GAP_MODEL_VARIANT, $result['code'] );
        $this->assertStringContainsString( 'Ridge Grappler LT', $result['label'] );
    }

    /**
     * What is shown leads with the closest name, not whichever row the
     * database returned first.
     */
    public function test_near_matches_are_ordered_by_similarity() {
        $index = RTG_Coverage::index_rows( array(
            array( 'brand' => 'Toyo', 'model' => 'Proxes ST III', 'size' => '275/65R20', 'advertiser_name' => 'SimpleTire' ),
            array( 'brand' => 'Toyo', 'model' => 'Open Country A/T III', 'size' => '275/65R20', 'advertiser_name' => 'The Tire Rack' ),
        ) );

        $result = RTG_Coverage::classify( $this->tire( 'Toyo', 'Open Country R/T Trail', '275/65R20' ), $index );

        $this->assertSame( 'Open Country A/T III', $result['near'][0]['model'] );
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
        $this->assertCount( 2, $models );
        $this->assertContains( 'Recon Grappler A/T', $models );
        $this->assertContains( 'Terra Grappler G3', $models );

        $recon = $result['near'][ array_search( 'Recon Grappler A/T', $models, true ) ];
        $this->assertSame( array( 'The Tire Rack', 'SimpleTire' ), $recon['advertisers'] );
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
            array( 'code' => RTG_Coverage::GAP_MODEL_ABSENT ),
            array( 'code' => RTG_Coverage::GAP_MODEL_ABSENT ),
            array( 'code' => RTG_Coverage::GAP_SIZE_ABSENT ),
        ) );

        $this->assertSame( RTG_Coverage::GAP_MODEL_ABSENT, array_key_first( $counts ) );
        $this->assertSame( 2, $counts[ RTG_Coverage::GAP_MODEL_ABSENT ] );
        $this->assertSame( 1, $counts[ RTG_Coverage::GAP_SIZE_ABSENT ] );
    }
}
