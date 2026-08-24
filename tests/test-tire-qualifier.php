<?php
/**
 * Tests for RTG_Tire_Qualifier.
 *
 * Affiliate feeds describe tires as free text, so the parser is the part most
 * likely to quietly regress: a size notation nobody anticipated turns a real
 * find into a rejected row, and nobody notices because the queue just looks
 * empty. These tests pin the notations the two retailers actually publish.
 */
class Test_RTG_Tire_Qualifier extends WP_UnitTestCase {

    /**
     * Rule context matching the plugin's default fitments, kept local so the
     * tests don't move when the site's dropdown options are edited.
     */
    private function context() {
        return array(
            'sizes'          => array( '255/60R20', '255/55R21', '275/65R18', '275/60R20', '275/55R22' ),
            'min_load_index' => 112,
            'load_ranges'    => array( 'SL', 'HL', 'XL', 'RF', 'D', 'E', 'F' ),
            'speed_ratings'  => array( 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'H', 'V', 'W', 'Y', 'Z' ),
        );
    }

    /**
     * Collect the failure codes from a qualify() result.
     */
    private function codes( $result ) {
        return array_column( $result['reasons'], 'code' );
    }

    // --- normalize_size() ---

    /**
     * The notations retailers use for one fitment all collapse to one form.
     */
    public function test_normalize_size_collapses_equivalent_notations() {
        $expected = '275/65R18';

        $this->assertSame( $expected, RTG_Tire_Qualifier::normalize_size( '275/65R18' ) );
        $this->assertSame( $expected, RTG_Tire_Qualifier::normalize_size( 'LT275/65R18' ) );
        $this->assertSame( $expected, RTG_Tire_Qualifier::normalize_size( 'P275/65R18' ) );
        $this->assertSame( $expected, RTG_Tire_Qualifier::normalize_size( '275/65 R18' ) );
        $this->assertSame( $expected, RTG_Tire_Qualifier::normalize_size( '275/65ZR18' ) );
        $this->assertSame( $expected, RTG_Tire_Qualifier::normalize_size( '  275/65r18  ' ) );
    }

    /**
     * Anything that isn't a tire size yields an empty string rather than a
     * half-parsed one, so a junk row can't accidentally match a real fitment.
     */
    public function test_normalize_size_rejects_non_sizes() {
        $this->assertSame( '', RTG_Tire_Qualifier::normalize_size( '' ) );
        $this->assertSame( '', RTG_Tire_Qualifier::normalize_size( 'all-season' ) );
        $this->assertSame( '', RTG_Tire_Qualifier::normalize_size( '18 inch' ) );
    }

    // --- parse_specs() ---

    /**
     * The common case: brand, model, size, load index and speed rating all
     * read out of a single title string.
     */
    public function test_parse_specs_reads_a_standard_title() {
        $specs = RTG_Tire_Qualifier::parse_specs( array(
            'title' => 'Michelin Defender LTX M/S2 275/65R18 116T',
            'brand' => 'Michelin',
        ) );

        $this->assertSame( '275/65R18', $specs['size'] );
        $this->assertSame( '116', $specs['load_index'] );
        $this->assertSame( 'T', $specs['speed_rating'] );
        $this->assertSame( 'Defender LTX M/S2', $specs['model'] );
    }

    /**
     * Light-truck tires carry a dual load index and a spelled-out load range.
     * The single-vehicle (first) index is the one that governs.
     */
    public function test_parse_specs_reads_dual_load_index_and_load_range() {
        $specs = RTG_Tire_Qualifier::parse_specs( array(
            'title' => 'BFGoodrich All-Terrain T/A KO2 LT275/65R18 123/120S Load Range E',
            'brand' => 'BFGoodrich',
        ) );

        $this->assertSame( '275/65R18', $specs['size'] );
        $this->assertSame( '123', $specs['load_index'] );
        $this->assertSame( 'S', $specs['speed_rating'] );
        $this->assertSame( 'E', $specs['load_range'] );
    }

    /**
     * A size written with a space before the R still parses, and the load
     * index that follows it is still found.
     */
    public function test_parse_specs_handles_spaced_size_notation() {
        $specs = RTG_Tire_Qualifier::parse_specs( array(
            'title' => 'Goodyear Wrangler Territory HT 275/60 R20 115H',
            'brand' => 'Goodyear',
        ) );

        $this->assertSame( '275/60R20', $specs['size'] );
        $this->assertSame( '115', $specs['load_index'] );
        $this->assertSame( 'Wrangler Territory HT', $specs['model'] );
    }

    /**
     * Fields the feed states explicitly are trusted over anything parsed, so
     * a retailer with real structured data isn't second-guessed.
     */
    public function test_parse_specs_prefers_explicit_fields_over_the_title() {
        $specs = RTG_Tire_Qualifier::parse_specs( array(
            'title'        => 'Pirelli Scorpion MS 255/55R21 110H XL',
            'brand'        => 'Pirelli',
            'load_index'   => '113',
            'speed_rating' => 'V',
        ) );

        $this->assertSame( '113', $specs['load_index'] );
        $this->assertSame( 'V', $specs['speed_rating'] );
    }

    /**
     * The digits inside a size must never be mistaken for a load index. This
     * is the specific failure the "look only after the size" rule prevents.
     */
    public function test_parse_specs_does_not_read_a_load_index_out_of_the_size() {
        $specs = RTG_Tire_Qualifier::parse_specs( array(
            'title' => 'Michelin Pilot Sport EV 275/65R18',
            'brand' => 'Michelin',
        ) );

        $this->assertSame( '275/65R18', $specs['size'] );
        $this->assertSame( '', $specs['load_index'] );
    }

    // --- qualify() ---

    /**
     * A tire in a stocked size that clears the load index floor qualifies.
     */
    public function test_qualify_accepts_an_eligible_tire() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Michelin Defender LTX M/S2 275/65R18 116T', 'brand' => 'Michelin' ),
            $this->context()
        );

        $this->assertTrue( $result['qualifies'] );
        $this->assertSame( array(), $result['reasons'] );
    }

    /**
     * A load index below the floor is rejected, and the reason names both the
     * actual value and the threshold so the near-miss view is readable.
     */
    public function test_qualify_rejects_a_low_load_index() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Toyo Open Country A/T III 275/65R18 109T', 'brand' => 'Toyo' ),
            $this->context()
        );

        $this->assertFalse( $result['qualifies'] );
        $this->assertContains( 'load_index_low', $this->codes( $result ) );
        $this->assertStringContainsString( '109', $result['reasons'][0]['label'] );
        $this->assertStringContainsString( '112', $result['reasons'][0]['label'] );
    }

    /**
     * A size Rivians don't take is rejected however good the tire is.
     */
    public function test_qualify_rejects_a_size_that_is_not_a_rivian_fitment() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Continental CrossContact LX25 235/65R17 120H', 'brand' => 'Continental' ),
            $this->context()
        );

        $this->assertFalse( $result['qualifies'] );
        $this->assertContains( 'size_not_stocked', $this->codes( $result ) );
    }

    /**
     * A listing with no load index is held back for confirmation rather than
     * admitted on the assumption it's fine.
     */
    public function test_qualify_flags_a_missing_load_index() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Michelin Pilot Sport EV 275/65R18', 'brand' => 'Michelin' ),
            $this->context()
        );

        $this->assertFalse( $result['qualifies'] );
        $this->assertContains( 'load_index_unknown', $this->codes( $result ) );
    }

    /**
     * Raising the floor to the R1 requirement excludes a tire that only met
     * the R2 one — the setting has to actually move the boundary.
     */
    public function test_qualify_respects_a_raised_load_index_floor() {
        $product = array( 'title' => 'Goodyear Wrangler Territory HT 275/60R20 115H', 'brand' => 'Goodyear' );

        $r2 = RTG_Tire_Qualifier::evaluate( $product, $this->context() );
        $this->assertTrue( $r2['qualifies'] );

        $context = $this->context();
        $context['min_load_index'] = 116;

        $r1 = RTG_Tire_Qualifier::evaluate( $product, $context );
        $this->assertFalse( $r1['qualifies'] );
        $this->assertContains( 'load_index_low', $this->codes( $r1 ) );
    }

    /**
     * A product with no readable identity is rejected rather than queued as a
     * blank row a human can't act on.
     */
    public function test_qualify_rejects_an_unidentifiable_product() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => '275/65R18 116T' ),
            $this->context()
        );

        $this->assertFalse( $result['qualifies'] );
        $codes = $this->codes( $result );
        $this->assertContains( 'brand_missing', $codes );
        $this->assertContains( 'model_missing', $codes );
    }

    /**
     * Every failed rule is reported, not just the first, so one fix doesn't
     * reveal a second problem on the next run.
     */
    public function test_qualify_reports_every_failure_at_once() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Continental CrossContact LX25 235/65R17 104H', 'brand' => 'Continental' ),
            $this->context()
        );

        $codes = $this->codes( $result );
        $this->assertContains( 'size_not_stocked', $codes );
        $this->assertContains( 'load_index_low', $codes );
    }
}
