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

    public function setUp(): void {
        parent::setUp();
        RTG_Activator::activate();
    }

    /**
     * Build a candidate row for upsert().
     */
    private function candidate( $overrides = array() ) {
        return array_merge( array(
            'source'          => 'test',
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

    // --- The interactive budget cap ---

    /**
     * A browser-started run is capped under the ~100 seconds a fronting proxy
     * waits before answering 524, whatever the configured budget says — and
     * the stats say which budget actually applied, so the status line can't
     * show a ceiling the run never had. A cap above the configured budget
     * never raises it: it is a ceiling, not a request.
     */
    public function test_a_browser_run_is_capped_under_the_proxy_limit() {
        RTG_Activator::activate();

        $capped = RTG_Catalog_Sync::run( RTG_Catalog_Sync::INTERACTIVE_BUDGET );
        $this->assertSame( RTG_Catalog_Sync::INTERACTIVE_BUDGET, $capped['run_budget'] );
        $this->assertTrue( $capped['budget_capped'] );

        $cron = RTG_Catalog_Sync::run();
        $this->assertSame( RTG_Catalog_Sync::RUN_BUDGET, $cron['run_budget'] );
        $this->assertFalse( $cron['budget_capped'] );

        $roomy = RTG_Catalog_Sync::run( 900 );
        $this->assertSame( RTG_Catalog_Sync::RUN_BUDGET, $roomy['run_budget'] );
        $this->assertFalse( $roomy['budget_capped'] );
    }

    // --- find_guide_match(): the duplicate-add guard ---

    /**
     * A hand-typed tire that already exists — under different punctuation —
     * is recognized. The tire ID check alone can't see this: IDs
     * auto-generate, so the same physical tire would happily get a second one.
     */
    public function test_a_hand_typed_duplicate_is_found_across_punctuation() {
        $guide = array(
            array( 'tire_id' => 'MICH-001', 'brand' => 'Michelin', 'model' => 'Defender LTX M/S2', 'size' => '275/65R18' ),
        );

        $this->assertSame( 'MICH-001', RTG_Catalog_Sync::find_guide_match(
            array( 'brand' => 'Michelin', 'model' => 'Defender LTX M/S 2', 'size' => '275/65 R18' ),
            $guide
        ) );
    }

    /**
     * Aliases count on both sides: typing a retailer's spelling collides with
     * the guide tire that carries it as an alias, and a proposed tire's own
     * alias collides with the model it aliases.
     */
    public function test_the_duplicate_guard_sees_through_aliases() {
        $guide = array(
            array( 'tire_id' => 'NITTO-001', 'brand' => 'Nitto', 'model' => 'Ridge Grappler',
                'size' => '275/65R20', 'model_aliases' => "Ridge Grappler LT" ),
        );

        $this->assertSame( 'NITTO-001', RTG_Catalog_Sync::find_guide_match(
            array( 'brand' => 'Nitto', 'model' => 'Ridge Grappler LT', 'size' => '275/65R20' ),
            $guide
        ) );
        $this->assertSame( 'NITTO-001', RTG_Catalog_Sync::find_guide_match(
            array( 'brand' => 'Nitto', 'model' => 'Trail Grappler', 'size' => '275/65R20',
                'model_aliases' => "Ridge Grappler" ),
            $guide
        ) );
    }

    /**
     * A different size of the same model is a different guide entry, not a
     * duplicate — and a tire that can't be keyed can't be blocked.
     */
    public function test_a_different_size_or_unkeyable_tire_is_not_a_duplicate() {
        $guide = array(
            array( 'tire_id' => 'MICH-001', 'brand' => 'Michelin', 'model' => 'Defender LTX M/S2', 'size' => '275/65R18' ),
        );

        $this->assertSame( '', RTG_Catalog_Sync::find_guide_match(
            array( 'brand' => 'Michelin', 'model' => 'Defender LTX M/S2', 'size' => '275/60R20' ),
            $guide
        ) );
        $this->assertSame( '', RTG_Catalog_Sync::find_guide_match(
            array( 'brand' => '', 'model' => 'Defender LTX M/S2', 'size' => '275/65R18' ),
            $guide
        ) );
    }

    // --- The name-drift pass: one tire, two spellings of its name ---

    /**
     * The reported failure, both halves of it. A retailer drops the words the
     * manufacturer puts on the box — "with Kevlar", "All Season" — and the
     * exact key built from the shorter name misses the guide tire that already
     * carries it, so a tire already stocked arrives as a new option to review.
     */
    public function test_a_listing_under_a_shorter_name_matches_the_guide_tire() {
        $guide = $this->guide();
        $index = $this->exact_index( $guide );
        $vars  = RTG_Catalog_Sync::build_variant_index( $guide );

        $this->assertSame( 'GY-001', RTG_Catalog_Sync::resolve_guide_match(
            'Goodyear', 'Wrangler All-Terrain Adventure', '255/65R19', $index, $vars
        ) );
        $this->assertSame( 'PIR-001', RTG_Catalog_Sync::resolve_guide_match(
            'Pirelli', 'Scorpion Zero', '255/65R19', $index, $vars
        ) );
    }

    /**
     * Exact keys settle it before any name is compared, and settle it with no
     * variant index at all — the drift pass is a fallback, never the path.
     */
    public function test_an_exact_key_needs_no_name_comparison() {
        $guide = $this->guide();

        $this->assertSame( 'MICH-001', RTG_Catalog_Sync::resolve_guide_match(
            'Michelin', 'Defender LTX M/S 2', '275/65 R18', $this->exact_index( $guide )
        ) );
    }

    /**
     * The pass only runs inside one brand and one fitment, and only on names
     * that resemble each other. Everything else is still a new tire.
     */
    public function test_the_name_pass_stays_inside_one_brand_and_fitment() {
        $guide = $this->guide();
        $index = $this->exact_index( $guide );
        $vars  = RTG_Catalog_Sync::build_variant_index( $guide );

        foreach ( array(
            'another fitment' => array( 'Goodyear', 'Wrangler All-Terrain Adventure', '275/65R20' ),
            'another brand'   => array( 'Falken', 'Wrangler All-Terrain Adventure', '255/65R19' ),
            'another model'   => array( 'Goodyear', 'Eagle Exhilarate', '255/65R19' ),
            'no model at all' => array( 'Goodyear', '', '255/65R19' ),
        ) as $label => $listing ) {
            $this->assertSame( '', RTG_Catalog_Sync::resolve_guide_match(
                $listing[0], $listing[1], $listing[2], $index, $vars
            ), $label . ' should not match' );
        }
    }

    /**
     * Two guide tires whose names both contain the listing's is not a match,
     * it is a guess — and a wrong guess hides a real find behind a tire it
     * isn't. Ambiguity resolves to "new", which costs one dismissal.
     */
    public function test_two_claimants_mean_no_match() {
        $guide   = array_merge( $this->guide(), array(
            array( 'id' => 4, 'tire_id' => 'PIR-002', 'brand' => 'Pirelli',
                'model' => 'Scorpion Zero Winter', 'size' => '255/65R19' ),
        ) );

        $this->assertSame( '', RTG_Catalog_Sync::variant_match(
            'Pirelli', 'Scorpion Zero', '255/65R19', RTG_Catalog_Sync::build_variant_index( $guide )
        ) );
    }

    /**
     * Aliases are spellings too, so the pass reads them as well — a listing
     * shorter than an alias matches the tire carrying it.
     */
    public function test_the_name_pass_reads_aliases() {
        $guide = array(
            array( 'id' => 5, 'tire_id' => 'NIT-001', 'brand' => 'Nitto', 'model' => 'Ridge Grappler',
                'size' => '275/65R20', 'model_aliases' => "Ridge Grappler LT Special" ),
        );

        $this->assertSame( 'NIT-001', RTG_Catalog_Sync::variant_match(
            'Nitto', 'Ridge Grappler LT', '275/65R20', RTG_Catalog_Sync::build_variant_index( $guide )
        ) );
    }

    /**
     * The hand-add guard and the queue agree about what counts as the same
     * tire — otherwise the queue would file a listing under Existing while the
     * form happily took a second entry for it.
     */
    public function test_the_duplicate_guard_sees_the_same_collision() {
        $guide = $this->guide();

        $this->assertSame( 'GY-001', RTG_Catalog_Sync::find_guide_match(
            array( 'brand' => 'Goodyear', 'model' => 'Wrangler All-Terrain Adventure', 'size' => '255/65R19' ),
            $guide
        ) );
        $this->assertSame( '', RTG_Catalog_Sync::find_guide_match(
            array( 'brand' => 'Goodyear', 'model' => 'Eagle Exhilarate', 'size' => '255/65R19' ),
            $guide
        ) );
    }

    /**
     * A name too far apart to match but close enough to be worth a look is
     * offered against the row, with the guide tire it resembles. One word in
     * common is not a resemblance.
     */
    public function test_a_near_name_is_offered_but_never_matched() {
        $vars = RTG_Catalog_Sync::build_variant_index( $this->guide() );

        $near = RTG_Catalog_Sync::nearest_guide_variant(
            'Goodyear', 'Wrangler All Terrain Adventure Kevlar', '255/65R19', $vars
        );

        $this->assertSame( 'Wrangler All-Terrain Adventure with Kevlar', $near['model'] );
        $this->assertSame( 1, $near['id'] );
        $this->assertLessThan( RTG_Coverage::VARIANT_THRESHOLD, $near['similarity'] );

        $this->assertNull( RTG_Catalog_Sync::nearest_guide_variant(
            'Goodyear', 'Wrangler Territory AT', '255/65R19', $vars
        ) );
    }

    /**
     * Sharing a family and a type word is not a resemblance. "Wrangler AT/S"
     * against "Wrangler TrailRunner AT" scored just over the old floor, so the
     * queue proposed aliasing two different Goodyears into one tire — worse
     * than saying nothing, because acting on it merges them.
     */
    public function test_two_different_tires_of_one_family_are_not_offered() {
        $guide = array(
            array( 'id' => 9, 'tire_id' => 'GY-TR', 'brand' => 'Goodyear',
                'model' => 'Wrangler TrailRunner AT', 'size' => '275/65R20' ),
        );

        $this->assertNull( RTG_Catalog_Sync::nearest_guide_variant(
            'Goodyear', 'Wrangler AT/S', '275/65R20',
            RTG_Catalog_Sync::build_variant_index( $guide )
        ) );
    }

    /**
     * Nor is one character of a model code. KO2 and KO3 are different tires
     * and scored 0.640, under the same floor.
     */
    public function test_a_model_code_one_digit_apart_is_not_offered() {
        $guide = array(
            array( 'id' => 10, 'tire_id' => 'BFG-KO2', 'brand' => 'BFGoodrich',
                'model' => 'All Terrain T/A KO2', 'size' => '275/65R20' ),
        );

        $this->assertNull( RTG_Catalog_Sync::nearest_guide_variant(
            'BFGoodrich', 'All Terrain T/A KO3', '275/65R20',
            RTG_Catalog_Sync::build_variant_index( $guide )
        ) );
    }

    /**
     * A guide the matcher can't key on contributes nothing rather than a
     * bucket everything falls into.
     */
    public function test_unkeyable_guide_rows_are_left_out_of_the_index() {
        $index = RTG_Catalog_Sync::build_variant_index( array(
            array( 'id' => 1, 'tire_id' => 'A', 'brand' => '', 'model' => 'Mystery', 'size' => '255/65R19' ),
            array( 'id' => 2, 'tire_id' => 'B', 'brand' => 'Goodyear', 'model' => 'Mystery', 'size' => 'n/a' ),
            array( 'id' => 3, 'tire_id' => 'C', 'brand' => 'Goodyear', 'model' => '', 'size' => '255/65R19' ),
        ) );

        $this->assertSame( array(), $index );
    }

    /**
     * Guide tires used by the name-drift tests: two the retailers spell
     * shorter than the guide does, one that keys exactly.
     */
    private function guide() {
        return array(
            array( 'id' => 1, 'tire_id' => 'GY-001', 'brand' => 'Goodyear',
                'model' => 'Wrangler All-Terrain Adventure with Kevlar', 'size' => '255/65R19' ),
            array( 'id' => 2, 'tire_id' => 'PIR-001', 'brand' => 'Pirelli',
                'model' => 'Scorpion Zero All Season', 'size' => '255/65R19' ),
            array( 'id' => 3, 'tire_id' => 'MICH-001', 'brand' => 'Michelin',
                'model' => 'Defender LTX M/S2', 'size' => '275/65R18' ),
        );
    }

    /**
     * The exact key => tire_id map build_guide_index() builds from the guide,
     * without needing the guide in the database.
     */
    private function exact_index( $tires ) {
        $index = array();

        foreach ( $tires as $tire ) {
            foreach ( RTG_Catalog_Sync::match_keys_for_tire( $tire ) as $key ) {
                $index[ $key ] = $tire['tire_id'];
            }
        }

        return $index;
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

    // --- Bulk decisions ---

    private function seed_candidate( $overrides = array() ) {
        return RTG_Candidates::upsert( array_merge( array(
            'source'          => 'cj',
            'advertiser_id'   => '1',
            'advertiser_name' => 'The Tire Rack',
            'external_id'     => 'bulk-' . wp_rand( 1, 999999 ),
            'brand'           => 'Winrun',
            'model'           => 'KF997',
            'size'            => '305/45R22',
            'qualifies'       => 1,
        ), $overrides ) );
    }

    /**
     * Bulk dismiss acts on what the filter matches in the database, and only
     * that — a different brand in the same tab stays put.
     */
    public function test_bulk_dismiss_moves_only_the_filtered_brand() {
        $this->seed_candidate();
        $this->seed_candidate();
        $keep = $this->seed_candidate( array( 'brand' => 'Michelin', 'model' => 'Defender' ) );

        $changed = RTG_Candidates::bulk_set_status(
            array( 'status' => RTG_Candidates::STATUS_NEW, 'brand' => 'Winrun' ),
            RTG_Candidates::STATUS_DISMISSED
        );

        $this->assertSame( 2, $changed );
        $this->assertSame( RTG_Candidates::STATUS_NEW, RTG_Candidates::get( $keep['id'] )['status'] );
    }

    /**
     * Bulk can only write dismissed or new. An import is a human record of a
     * tire actually saved to the guide; no bulk sweep may produce or destroy
     * one.
     */
    public function test_bulk_cannot_touch_imported_rows() {
        $row = $this->seed_candidate();
        RTG_Candidates::set_status( $row['id'], RTG_Candidates::STATUS_IMPORTED );

        $changed = RTG_Candidates::bulk_set_status(
            array( 'status' => RTG_Candidates::STATUS_IMPORTED ),
            RTG_Candidates::STATUS_DISMISSED
        );

        $this->assertSame( 0, $changed );
        $this->assertSame( RTG_Candidates::STATUS_IMPORTED, RTG_Candidates::get( $row['id'] )['status'] );
    }

    /**
     * A mistaken bulk dismiss is recoverable by the same route.
     */
    public function test_bulk_dismiss_is_reversible_in_bulk() {
        $this->seed_candidate();

        RTG_Candidates::bulk_set_status(
            array( 'status' => RTG_Candidates::STATUS_NEW, 'brand' => 'Winrun' ),
            RTG_Candidates::STATUS_DISMISSED
        );
        $restored = RTG_Candidates::bulk_set_status(
            array( 'status' => RTG_Candidates::STATUS_DISMISSED, 'brand' => 'Winrun' ),
            RTG_Candidates::STATUS_NEW
        );

        $this->assertSame( 1, $restored );
    }

    /**
     * The brand facet counts what the queue actually holds.
     */
    public function test_brand_counts_reflect_the_queue() {
        $this->seed_candidate();
        $this->seed_candidate();
        $this->seed_candidate( array( 'brand' => 'Michelin', 'model' => 'Defender' ) );

        $counts = RTG_Candidates::get_brand_counts( RTG_Candidates::STATUS_NEW );

        $this->assertSame( 2, $counts['Winrun'] );
        $this->assertSame( 1, $counts['Michelin'] );
    }

    // --- An alias carrying the brand, and load ratings that disagree ---

    /**
     * The queue tells you to add "this listing's name" as an alias, and the
     * name it shows carries the brand — so pasting it put the brand in twice
     * and the alias matched nothing. That trap was ours; both forms key now.
     */
    public function test_an_alias_typed_with_the_brand_still_matches() {
        $guide = array(
            array(
                'tire_id'       => 'tire096',
                'brand'         => 'Toyo',
                'model'         => 'Open Country A/T III EV',
                'size'          => '275/60R20',
                'load_index'    => '123',
                'model_aliases' => 'Toyo Open Country A/T III EV All Terrain',
            ),
            // The non-EV of the same fitment: its name sits inside the
            // listing's too, so without the alias this is ambiguous.
            array(
                'tire_id'    => 'tireOTHER',
                'brand'      => 'Toyo',
                'model'      => 'Open Country A/T III',
                'size'       => '275/60R20',
                'load_index' => '123',
            ),
        );

        $this->assertSame( 'tire096', RTG_Catalog_Sync::resolve_guide_match(
            'Toyo', 'Open Country A/T III EV All Terrain', '275/60R20',
            $this->exact_index( $guide ), RTG_Catalog_Sync::build_variant_index( $guide ), '123'
        ) );
    }

    /**
     * A brand on its own leaves nothing to key on, so it adds no spelling —
     * a tire answering to its bare brand would collide with every sibling.
     */
    public function test_a_brand_only_alias_adds_no_spelling() {
        $spellings = RTG_Catalog_Sync::model_spellings( array(
            'brand'         => 'Toyo',
            'model'         => 'Open Country A/T III EV',
            'model_aliases' => 'Toyo',
        ) );

        $this->assertSame( array( 'Open Country A/T III EV', 'Toyo' ), $spellings );
        $this->assertNotContains( '', $spellings );
    }

    /**
     * The reported wrong-tire link. "Scorpion XTM AT" sits inside "Scorpion
     * XTM AT Elect All Terrain", so a name comparison alone files the EV
     * listing under the non-EV tire. Their load ratings say otherwise, and
     * that is the ordinary shape of a variant — so it stays for review.
     */
    public function test_a_load_rating_that_disagrees_blocks_a_name_match() {
        $guide = array(
            array(
                'tire_id'    => 'PIR-116',
                'brand'      => 'Pirelli',
                'model'      => 'Scorpion XTM AT',
                'size'       => '275/50R22',
                'load_index' => '116',
            ),
        );

        $this->assertSame( '', RTG_Catalog_Sync::resolve_guide_match(
            'Pirelli', 'Scorpion XTM AT Elect All Terrain', '275/50R22',
            $this->exact_index( $guide ), RTG_Catalog_Sync::build_variant_index( $guide ), '119'
        ) );
    }

    /**
     * And with the right tire in the guide, the same listing finds it —
     * the rating narrows the field rather than emptying it.
     */
    public function test_the_rating_picks_the_variant_the_listing_actually_is() {
        $guide = array(
            array( 'tire_id' => 'PIR-116', 'brand' => 'Pirelli', 'model' => 'Scorpion XTM AT',
                'size' => '275/50R22', 'load_index' => '116' ),
            array( 'tire_id' => 'PIR-ELECT', 'brand' => 'Pirelli', 'model' => 'Scorpion XTM AT Elect',
                'size' => '275/50R22', 'load_index' => '119' ),
        );

        $this->assertSame( 'PIR-ELECT', RTG_Catalog_Sync::resolve_guide_match(
            'Pirelli', 'Scorpion XTM AT Elect All Terrain', '275/50R22',
            $this->exact_index( $guide ), RTG_Catalog_Sync::build_variant_index( $guide ), '119'
        ) );
    }

    /**
     * A rating only counts against a match when both sides have one, and an
     * exact name is never overruled by one — a feed reporting a rating
     * loosely must not un-match a tire the guide names exactly.
     */
    public function test_an_unknown_or_exact_match_is_not_overruled_by_a_rating() {
        $guide = array(
            array( 'tire_id' => 'PIR-116', 'brand' => 'Pirelli', 'model' => 'Scorpion XTM AT',
                'size' => '275/50R22', 'load_index' => '116' ),
        );
        $index    = $this->exact_index( $guide );
        $variants = RTG_Catalog_Sync::build_variant_index( $guide );

        // The listing never says what it carries.
        $this->assertSame( 'PIR-116', RTG_Catalog_Sync::resolve_guide_match(
            'Pirelli', 'Scorpion XTM AT All Terrain', '275/50R22', $index, $variants, ''
        ) );

        // The name is exactly the guide's, and the rating disagrees.
        $this->assertSame( 'PIR-116', RTG_Catalog_Sync::resolve_guide_match(
            'Pirelli', 'Scorpion XTM AT', '275/50R22', $index, $variants, '119'
        ) );
    }

    /**
     * What counts as disagreement: both readable, and different. A dual
     * rating reads as its single-wheel figure, the one the guide compares.
     */
    public function test_when_two_load_ratings_disagree() {
        $this->assertTrue( RTG_Catalog_Sync::load_ratings_disagree( '119', '116' ) );
        $this->assertFalse( RTG_Catalog_Sync::load_ratings_disagree( '119', '119' ) );
        $this->assertFalse( RTG_Catalog_Sync::load_ratings_disagree( '119', '' ) );
        $this->assertFalse( RTG_Catalog_Sync::load_ratings_disagree( '', '116' ) );
        $this->assertFalse( RTG_Catalog_Sync::load_ratings_disagree( '0', '116' ) );
        $this->assertFalse( RTG_Catalog_Sync::load_ratings_disagree( '119/116', ' 119 ' ) );
        $this->assertTrue( RTG_Catalog_Sync::load_ratings_disagree( '119/116', '116' ) );
    }

    // --- Reconciling the queue with the guide ---

    /**
     * Add the tire to the guide by hand and its listings stop being offered as
     * new options — without waiting for the nightly sweep to come back around
     * to that fitment, which is what left already-stocked tires sitting in the
     * queue for days.
     */
    public function test_adding_a_tire_settles_its_queued_listings() {
        $queued = RTG_Candidates::upsert( $this->candidate( array(
            'external_id' => 'drift-1',
            'brand'       => 'Goodyear',
            'model'       => 'Wrangler All-Terrain Adventure',
            'size'        => '255/65R19',
            'match_key'   => RTG_Catalog_Sync::match_key( 'Goodyear', 'Wrangler All-Terrain Adventure', '255/65R19' ),
        ) ) );

        $this->assertSame( RTG_Candidates::STATUS_NEW, $queued['status'] );

        RTG_Database::insert_tire( array(
            'tire_id' => 'GY-001',
            'brand'   => 'Goodyear',
            'model'   => 'Wrangler All-Terrain Adventure with Kevlar',
            'size'    => '255/65R19',
        ) );

        RTG_Candidates::reconcile_with_guide();

        $settled = RTG_Candidates::get( $queued['id'] );
        $this->assertSame( RTG_Candidates::STATUS_EXISTING, $settled['status'] );
        $this->assertSame( 'GY-001', $settled['matched_tire_id'] );
    }

    /**
     * Reconciling re-runs the machine's own conclusion, never a person's. A
     * dismissed listing stays dismissed even once its tire joins the guide.
     */
    public function test_reconciling_leaves_human_decisions_alone() {
        $queued = RTG_Candidates::upsert( $this->candidate( array(
            'external_id' => 'drift-2',
            'brand'       => 'Goodyear',
            'model'       => 'Wrangler All-Terrain Adventure',
            'size'        => '255/65R19',
            'match_key'   => RTG_Catalog_Sync::match_key( 'Goodyear', 'Wrangler All-Terrain Adventure', '255/65R19' ),
        ) ) );

        RTG_Candidates::set_status( $queued['id'], RTG_Candidates::STATUS_DISMISSED );

        RTG_Database::insert_tire( array(
            'tire_id' => 'GY-002',
            'brand'   => 'Goodyear',
            'model'   => 'Wrangler All-Terrain Adventure with Kevlar',
            'size'    => '255/65R19',
        ) );

        RTG_Candidates::reconcile_with_guide();

        $this->assertSame(
            RTG_Candidates::STATUS_DISMISSED,
            RTG_Candidates::get( $queued['id'] )['status']
        );
    }

    /**
     * The other end of the same match: a retailer carrying the tire under the
     * shorter name counts as carrying it, so coverage says so and its price
     * has somewhere to come from.
     */
    public function test_a_drifted_name_still_counts_as_retailer_coverage() {
        RTG_Candidates::upsert( $this->candidate( array(
            'external_id'     => 'drift-3',
            'advertiser_name' => 'SimpleTire',
            'brand'           => 'Pirelli',
            'model'           => 'Scorpion Zero',
            'size'            => '255/65R19',
            'match_key'       => RTG_Catalog_Sync::match_key( 'Pirelli', 'Scorpion Zero', '255/65R19' ),
        ) ) );

        RTG_Database::insert_tire( array(
            'tire_id' => 'PIR-001',
            'brand'   => 'Pirelli',
            'model'   => 'Scorpion Zero All Season',
            'size'    => '255/65R19',
        ) );

        $by_tire = RTG_Candidates::get_matched_by_tire();

        $this->assertArrayHasKey( 'PIR-001', $by_tire );
        $this->assertSame( 'Scorpion Zero', $by_tire['PIR-001'][0]['model'] );
        $this->assertSame( array( 'SimpleTire' ), RTG_Candidates::get_retailer_coverage()['PIR-001'] );
    }

    // --- A tire removed from the guide ---

    /**
     * Add a listing to the guide, then delete that tire, and the listing has
     * to come back to the queue. Otherwise it is in neither place — the one
     * way a tire can go missing with nothing saying so.
     */
    public function test_deleting_a_tire_returns_its_listing_to_the_queue() {
        $queued = RTG_Candidates::upsert( $this->candidate( array( 'external_id' => 'gone-1' ) ) );

        RTG_Database::insert_tire( array(
            'tire_id' => 'MICH-001',
            'brand'   => 'Michelin',
            'model'   => 'Defender LTX M/S2',
            'size'    => '275/65R18',
        ) );
        RTG_Candidates::set_status( $queued['id'], RTG_Candidates::STATUS_IMPORTED, 'MICH-001' );

        // Still stocked: reconciling leaves it alone.
        RTG_Candidates::reconcile_with_guide();
        $this->assertSame(
            RTG_Candidates::STATUS_IMPORTED,
            RTG_Candidates::get( $queued['id'] )['status']
        );

        RTG_Database::delete_tire( 'MICH-001' );
        RTG_Candidates::reconcile_with_guide();

        $back = RTG_Candidates::get( $queued['id'] );
        $this->assertSame( RTG_Candidates::STATUS_NEW, $back['status'] );
        $this->assertSame( '', $back['matched_tire_id'] );
    }

    /**
     * A listing that wouldn't qualify goes back to near misses rather than to
     * the review queue — deleting a tire undoes the import, it doesn't excuse
     * the listing from the rules.
     */
    public function test_a_returned_listing_still_answers_to_the_rules() {
        $queued = RTG_Candidates::upsert( $this->candidate( array(
            'external_id' => 'gone-2',
            'qualifies'   => 0,
        ) ) );

        RTG_Database::insert_tire( array(
            'tire_id' => 'MICH-002',
            'brand'   => 'Michelin',
            'model'   => 'Defender LTX M/S2',
            'size'    => '275/65R18',
        ) );
        RTG_Candidates::set_status( $queued['id'], RTG_Candidates::STATUS_IMPORTED, 'MICH-002' );

        RTG_Database::delete_tire( 'MICH-002' );
        RTG_Candidates::reconcile_with_guide();

        $this->assertSame(
            RTG_Candidates::STATUS_REJECTED,
            RTG_Candidates::get( $queued['id'] )['status']
        );
    }

    /**
     * Renaming a tire on the way into the guide is routine and is not a
     * deletion. Only the recorded tire_id decides, so a listing whose model
     * was edited stays imported however far the names have drifted.
     */
    public function test_renaming_the_tire_does_not_resurface_the_listing() {
        $queued = RTG_Candidates::upsert( $this->candidate( array( 'external_id' => 'gone-3' ) ) );

        RTG_Database::insert_tire( array(
            'tire_id' => 'MICH-003',
            'brand'   => 'Michelin',
            'model'   => 'Something Else Entirely',
            'size'    => '275/65R18',
        ) );
        RTG_Candidates::set_status( $queued['id'], RTG_Candidates::STATUS_IMPORTED, 'MICH-003' );

        RTG_Candidates::reconcile_with_guide();

        $this->assertSame(
            RTG_Candidates::STATUS_IMPORTED,
            RTG_Candidates::get( $queued['id'] )['status']
        );
    }

    /**
     * A dismissal is a standing judgement about the listing, not a claim about
     * the guide, so nothing in the guide changes it.
     */
    public function test_a_dismissal_survives_a_deletion() {
        $queued = RTG_Candidates::upsert( $this->candidate( array( 'external_id' => 'gone-4' ) ) );
        RTG_Candidates::set_status( $queued['id'], RTG_Candidates::STATUS_DISMISSED );

        RTG_Database::insert_tire( array(
            'tire_id' => 'MICH-004',
            'brand'   => 'Michelin',
            'model'   => 'Defender LTX M/S2',
            'size'    => '275/65R18',
        ) );
        RTG_Database::delete_tire( 'MICH-004' );
        RTG_Candidates::reconcile_with_guide();

        $this->assertSame(
            RTG_Candidates::STATUS_DISMISSED,
            RTG_Candidates::get( $queued['id'] )['status']
        );
    }

    /**
     * Rows imported before the tire_id was recorded have nothing to go on, so
     * they are left where they are — but the tire they still match is noted,
     * which is what covers them the next time round.
     */
    public function test_an_older_import_records_the_tire_it_matches() {
        $queued = RTG_Candidates::upsert( $this->candidate( array( 'external_id' => 'gone-5' ) ) );
        RTG_Candidates::set_status( $queued['id'], RTG_Candidates::STATUS_IMPORTED );

        RTG_Database::insert_tire( array(
            'tire_id' => 'MICH-005',
            'brand'   => 'Michelin',
            'model'   => 'Defender LTX M/S2',
            'size'    => '275/65R18',
        ) );

        RTG_Candidates::reconcile_with_guide();

        $backfilled = RTG_Candidates::get( $queued['id'] );
        $this->assertSame( RTG_Candidates::STATUS_IMPORTED, $backfilled['status'] );
        $this->assertSame( 'MICH-005', $backfilled['matched_tire_id'] );

        // And now that it is recorded, the deletion is recognizable.
        RTG_Database::delete_tire( 'MICH-005' );
        RTG_Candidates::reconcile_with_guide();

        $this->assertSame(
            RTG_Candidates::STATUS_NEW,
            RTG_Candidates::get( $queued['id'] )['status']
        );
    }

    // --- Retention ---

    /**
     * A rejected row in a fitment the guide doesn't stock can never qualify,
     * so it goes; a rejected row in a stocked fitment stays.
     */
    public function test_prune_deletes_off_fitment_near_misses_only() {
        $off  = $this->seed_candidate( array( 'size' => '215/45R17', 'qualifies' => 0 ) );
        $near = $this->seed_candidate( array( 'size' => '305/45R22', 'qualifies' => 0 ) );

        $result = RTG_Candidates::prune( array( '305/45R22' ) );

        $this->assertSame( 1, $result['off_fitment'] );
        $this->assertNull( RTG_Candidates::get( $off['id'] ) );
        $this->assertNotNull( RTG_Candidates::get( $near['id'] ) );
    }

    /**
     * Human decisions are never pruned, whatever their fitment: dismissed
     * rows are the memory that stops things resurfacing.
     */
    public function test_prune_never_touches_human_decisions() {
        $row = $this->seed_candidate( array( 'size' => '215/45R17', 'qualifies' => 1 ) );
        RTG_Candidates::set_status( $row['id'], RTG_Candidates::STATUS_DISMISSED );

        RTG_Candidates::prune( array( '305/45R22' ) );

        $this->assertNotNull( RTG_Candidates::get( $row['id'] ) );
    }

    /**
     * Queue rows are awaiting a decision, not eligible for housekeeping.
     */
    public function test_prune_never_touches_the_review_queue() {
        $row = $this->seed_candidate( array( 'size' => '215/45R17', 'qualifies' => 1 ) );

        RTG_Candidates::prune( array( '305/45R22' ) );

        $this->assertNotNull( RTG_Candidates::get( $row['id'] ) );
    }

    /**
     * An empty size list means "off-fitment" is undefined, and nothing is
     * deleted on that basis — the destructive version of a silent failure.
     */
    public function test_prune_deletes_nothing_when_the_size_list_is_empty() {
        $row = $this->seed_candidate( array( 'size' => '215/45R17', 'qualifies' => 0 ) );

        $result = RTG_Candidates::prune( array() );

        $this->assertSame( 0, $result['off_fitment'] );
        $this->assertNotNull( RTG_Candidates::get( $row['id'] ) );
    }

    /**
     * A rejected listing the catalog itself dropped two months ago goes; a
     * recently seen one stays.
     */
    public function test_prune_deletes_long_unseen_near_misses() {
        global $wpdb;

        $old  = $this->seed_candidate( array( 'size' => '305/45R22', 'qualifies' => 0 ) );
        $new  = $this->seed_candidate( array( 'size' => '305/45R22', 'qualifies' => 0 ) );

        $wpdb->update(
            RTG_Candidates::table(),
            array( 'last_seen_at' => gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ),
            array( 'id' => $old['id'] )
        );

        $result = RTG_Candidates::prune( array( '305/45R22' ), 60 );

        $this->assertSame( 1, $result['stale'] );
        $this->assertNull( RTG_Candidates::get( $old['id'] ) );
        $this->assertNotNull( RTG_Candidates::get( $new['id'] ) );
    }
}
