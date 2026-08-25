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
