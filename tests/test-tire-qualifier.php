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
     *
     * It warns rather than disqualifies. The first live run against CJ showed
     * why: Tire Rack listings routinely omit the load index, and treating that
     * as a failure filed genuinely new tires under near misses where they were
     * never seen — defeating the point of watching the catalog.
     */
    public function test_qualify_warns_about_a_missing_load_index_without_rejecting() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Michelin Pilot Sport EV 275/65R18', 'brand' => 'Michelin' ),
            $this->context()
        );

        $this->assertTrue( $result['qualifies'] );
        $this->assertSame( array(), $result['reasons'] );
        $this->assertContains( 'load_index_unknown', array_column( $result['warnings'], 'code' ) );
    }

    /**
     * Every speed rating in industry use passes, even when the site's saved
     * dropdown is dirty.
     *
     * The list is edited as free text, so it can easily end up carrying stray
     * line endings — which is what happened on the first live CJ run, where a
     * strict comparison flagged every ordinary "V", "W", "H" and "T" as
     * unrecognized. The configured list is now unioned with the canonical one.
     */
    public function test_qualify_accepts_valid_speed_ratings_despite_a_dirty_dropdown() {
        $context = $this->context();

        // Saved values carrying carriage returns, as a textarea round-trip leaves them.
        $context['speed_ratings'] = array( "T\r", "H\r", "V\r", "W\r" );

        foreach ( array( 'T', 'H', 'V', 'W', 'Q', 'Y' ) as $rating ) {
            $result = RTG_Tire_Qualifier::evaluate(
                array( 'title' => "Pirelli Scorpion 275/65R18 116{$rating}", 'brand' => 'Pirelli' ),
                $context
            );

            $codes = array_merge(
                array_column( $result['reasons'], 'code' ),
                array_column( $result['warnings'], 'code' )
            );

            $this->assertNotContains( 'speed_rating_unknown', $codes, "Speed rating {$rating} should be accepted" );
        }
    }

    /**
     * A warning never disqualifies, so a rule that only warns can't hide a row.
     */
    public function test_warnings_do_not_disqualify() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'BFGoodrich All-Terrain T/A KO3 275/65R18', 'brand' => 'BFGoodrich' ),
            $this->context()
        );

        $this->assertTrue( $result['qualifies'] );
        $this->assertNotEmpty( $result['warnings'] );
    }

    /**
     * The rules that should still reject, do. Loosening the soft ones must not
     * have loosened the fitment and safety gates with them.
     */
    public function test_hard_rules_still_reject() {
        $wrong_size = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Atlas Force 285/45R22 114V', 'brand' => 'Atlas' ),
            $this->context()
        );
        $this->assertFalse( $wrong_size['qualifies'] );
        $this->assertContains( 'size_not_stocked', $this->codes( $wrong_size ) );

        $low_index = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Fuzion Highway 275/65R18 110H', 'brand' => 'Fuzion' ),
            $this->context()
        );
        $this->assertFalse( $low_index['qualifies'] );
        $this->assertContains( 'load_index_low', $this->codes( $low_index ) );
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

    // --- Brand coverage policy ---

    /**
     * Context with the guide's curated brand list attached.
     *
     * @param string $policy Brand policy to apply.
     * @return array Rule context.
     */
    private function brand_context( $policy ) {
        $context                 = $this->context();
        $context['brands']       = array( 'BFGoodrich', 'Continental', 'Goodyear', 'Michelin', 'Toyo' );
        $context['brand_policy'] = $policy;

        return $context;
    }

    /**
     * Under "warn", an uncovered brand still reaches the queue carrying a flag.
     *
     * The point of the setting is that a brand worth covering can still be
     * discovered; hiding it by default would make the guide unable to grow.
     */
    public function test_warn_policy_flags_an_uncovered_brand_without_hiding_it() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Dcenti DC88 A/T 275/60R20 115T', 'brand' => 'Dcenti' ),
            $this->brand_context( RTG_Tire_Qualifier::BRAND_POLICY_WARN )
        );

        $this->assertTrue( $result['qualifies'] );
        $this->assertContains( 'brand_not_covered', array_column( $result['warnings'], 'code' ) );
    }

    /**
     * Under "reject", the same tire is filed as a near miss with a reason.
     */
    public function test_reject_policy_files_an_uncovered_brand_as_a_near_miss() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Dcenti DC88 A/T 275/60R20 115T', 'brand' => 'Dcenti' ),
            $this->brand_context( RTG_Tire_Qualifier::BRAND_POLICY_REJECT )
        );

        $this->assertFalse( $result['qualifies'] );
        $this->assertContains( 'brand_not_covered', $this->codes( $result ) );
    }

    /**
     * Under "off", brand isn't judged at all.
     */
    public function test_off_policy_ignores_brand_entirely() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Dcenti DC88 A/T 275/60R20 115T', 'brand' => 'Dcenti' ),
            $this->brand_context( RTG_Tire_Qualifier::BRAND_POLICY_OFF )
        );

        $this->assertTrue( $result['qualifies'] );
        $this->assertSame( array(), $result['warnings'] );
    }

    /**
     * A covered brand passes cleanly under every policy.
     */
    public function test_a_covered_brand_is_never_flagged() {
        foreach ( array(
            RTG_Tire_Qualifier::BRAND_POLICY_WARN,
            RTG_Tire_Qualifier::BRAND_POLICY_REJECT,
            RTG_Tire_Qualifier::BRAND_POLICY_OFF,
        ) as $policy ) {
            $result = RTG_Tire_Qualifier::evaluate(
                array( 'title' => 'Continental TerrainContact A/T 275/60R20 115S', 'brand' => 'Continental' ),
                $this->brand_context( $policy )
            );

            $this->assertTrue( $result['qualifies'], "Covered brand should qualify under {$policy}" );
            $this->assertNotContains( 'brand_not_covered', array_column( $result['warnings'], 'code' ) );
        }
    }

    /**
     * Spelling variants of a covered brand are recognized.
     *
     * Retailers punctuate brands inconsistently, and the strictest policy is
     * the one where getting this wrong is most costly — a rejected tire is
     * never seen — so the comparison ignores everything but letters and digits.
     */
    public function test_brand_variants_are_recognized_under_the_strictest_policy() {
        foreach ( array( 'BFGoodrich', 'BF Goodrich', 'BF-Goodrich', 'bfgoodrich' ) as $variant ) {
            $result = RTG_Tire_Qualifier::evaluate(
                array( 'title' => "{$variant} All-Terrain T/A KO3 275/65R18 116T", 'brand' => $variant ),
                $this->brand_context( RTG_Tire_Qualifier::BRAND_POLICY_REJECT )
            );

            $this->assertTrue( $result['qualifies'], "Variant {$variant} should be recognized" );
        }
    }

    /**
     * With no curated list configured the rule stays silent, whatever the
     * policy — otherwise a site that never set brands up would reject its
     * entire catalog.
     */
    public function test_an_empty_brand_list_keeps_the_rule_silent() {
        $context                 = $this->context();
        $context['brands']       = array();
        $context['brand_policy'] = RTG_Tire_Qualifier::BRAND_POLICY_REJECT;

        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Dcenti DC88 A/T 275/60R20 115T', 'brand' => 'Dcenti' ),
            $context
        );

        $this->assertTrue( $result['qualifies'] );
    }

    /**
     * An unset policy must not reject: a rule nobody configured should never
     * silently hide tires.
     */
    public function test_an_unset_policy_never_rejects() {
        $context = $this->context();
        $context['brands'] = array( 'Michelin' );
        unset( $context['brand_policy'] );

        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Dcenti DC88 A/T 275/60R20 115T', 'brand' => 'Dcenti' ),
            $context
        );

        $this->assertTrue( $result['qualifies'] );
    }

    // --- Per-vehicle fitment ---

    /**
     * Context with a two-platform vehicle map, sharing one size deliberately.
     *
     * @return array Rule context.
     */
    private function vehicle_context() {
        return array(
            'sizes'                  => array(),
            'min_load_index'         => 112,
            'load_ranges'            => array(),
            'speed_ratings'          => array( 'T', 'S', 'V', 'H' ),
            'brands'                 => array(),
            'brand_policy'           => RTG_Tire_Qualifier::BRAND_POLICY_OFF,
            'vehicle_sizes'          => array(
                'R1' => array( '275/65R18', '275/60R20', '275/55R22' ),
                'R2' => array( '255/60R20', '255/55R21', '275/55R22' ),
            ),
            'vehicle_min_load_index' => array( 'R1' => 116, 'R2' => 112 ),
        );
    }

    /**
     * A tire is reported against the platforms it is actually legal on.
     */
    public function test_a_qualifying_tire_names_the_platform_it_fits() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Michelin Defender LTX M/S2 275/65R18 116T', 'brand' => 'Michelin' ),
            $this->vehicle_context()
        );

        $this->assertTrue( $result['qualifies'] );
        $this->assertSame( array( 'R1' ), $result['fits_vehicles'] );
    }

    /**
     * Size and load index are judged together, not as independent gates.
     *
     * A 275/65R18 at load index 114 clears a global floor of 112 while being
     * illegal on the R1 that is the only platform taking that size. Judged
     * separately it would have qualified; judged per vehicle it does not.
     */
    public function test_a_tire_below_its_platforms_floor_is_rejected_despite_clearing_the_global_one() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Toyo Open Country A/T III 275/65R18 114T', 'brand' => 'Toyo' ),
            $this->vehicle_context()
        );

        $this->assertFalse( $result['qualifies'] );
        $this->assertContains( 'load_index_low', $this->codes( $result ) );
        $this->assertStringContainsString( 'R1 needs 116', $result['reasons'][0]['label'] );
        $this->assertSame( array(), $result['fits_vehicles'] );
    }

    /**
     * A size both platforms take, with load enough for both, fits both.
     */
    public function test_a_shared_size_can_fit_every_platform() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Continental CrossContact 275/55R22 118V', 'brand' => 'Continental' ),
            $this->vehicle_context()
        );

        $this->assertSame( array( 'R1', 'R2' ), $result['fits_vehicles'] );
    }

    /**
     * The same shared size with less load qualifies on the platform that
     * accepts it and is reported as fitting only that one.
     *
     * This is the case a single global floor obscured entirely: it passed, with
     * nothing to say it was legal on one platform and not the other.
     */
    public function test_a_shared_size_can_fit_only_the_lower_floor_platform() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Continental CrossContact 275/55R22 114V', 'brand' => 'Continental' ),
            $this->vehicle_context()
        );

        $this->assertTrue( $result['qualifies'] );
        $this->assertSame( array( 'R2' ), $result['fits_vehicles'] );
    }

    /**
     * An unlisted load index doesn't rule a platform out — it is confirmed by
     * hand — and the warning names the floor to confirm against.
     */
    public function test_an_unlisted_load_index_still_fits_but_names_the_floor() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'BFGoodrich All-Terrain T/A KO3 275/65R18', 'brand' => 'BFGoodrich' ),
            $this->vehicle_context()
        );

        $this->assertTrue( $result['qualifies'] );
        $this->assertSame( array( 'R1' ), $result['fits_vehicles'] );
        $this->assertStringContainsString( '116', $result['warnings'][0]['label'] );
    }

    /**
     * A size no platform takes fits nothing and is rejected on fitment.
     */
    public function test_a_size_no_platform_takes_fits_nothing() {
        $result = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Atlas Force 285/45R22 114V', 'brand' => 'Atlas' ),
            $this->vehicle_context()
        );

        $this->assertFalse( $result['qualifies'] );
        $this->assertContains( 'size_not_stocked', $this->codes( $result ) );
        $this->assertSame( array(), $result['fits_vehicles'] );
    }

    /**
     * With no stock wheels configured there is no vehicle map to judge
     * against, so the flat size list and single global floor still apply.
     * A site that never set wheels up must not have its catalog rejected.
     */
    public function test_without_a_vehicle_map_the_global_floor_still_applies() {
        $context                  = $this->vehicle_context();
        $context['vehicle_sizes'] = array();
        $context['sizes']         = array( '275/65R18' );

        $passes = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Toyo Open Country A/T III 275/65R18 114T', 'brand' => 'Toyo' ),
            $context
        );
        $this->assertTrue( $passes['qualifies'] );

        $fails = RTG_Tire_Qualifier::evaluate(
            array( 'title' => 'Toyo Open Country A/T III 275/65R18 109T', 'brand' => 'Toyo' ),
            $context
        );
        $this->assertFalse( $fails['qualifies'] );
        $this->assertContains( 'load_index_low', $this->codes( $fails ) );
    }
}
