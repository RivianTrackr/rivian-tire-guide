<?php
/**
 * Tests for RTG_Catalog_Presence.
 *
 * A broken-link check asks whether a URL resolves, and a delisted tire passes
 * it: the retailer's page is still there, the affiliate link still redirects,
 * and the product has been dropped from the feed the commission and the price
 * come from. These cover telling that apart from a tire that was never listed
 * — and, most importantly, from a fitment our own sweep failed to read.
 *
 * Nothing here touches the network or the clock; the time is passed in.
 */
class Test_RTG_Catalog_Presence extends WP_UnitTestCase {

    private $now;

    public function setUp(): void {
        parent::setUp();
        $this->now = strtotime( '2026-08-24 12:00:00' );
    }

    private function at( $days_ago ) {
        return gmdate( 'Y-m-d H:i:s', $this->now - ( $days_ago * DAY_IN_SECONDS ) );
    }

    private function tire( $brand, $model, $size ) {
        return array( 'tire_id' => 't1', 'brand' => $brand, 'model' => $model, 'size' => $size );
    }

    private function listing( $advertiser, $days_ago ) {
        return array( 'advertiser_name' => $advertiser, 'last_seen_at' => $this->at( $days_ago ) );
    }

    private function keyed( $brand, $model, $size, $listings ) {
        return array( RTG_Catalog_Sync::match_key( $brand, $model, $size ) => $listings );
    }

    /**
     * A listing the sweep saw on its last pass is current.
     */
    public function test_a_recent_listing_is_current() {
        $result = RTG_Catalog_Presence::evaluate_one(
            $this->tire( 'Michelin', 'Defender LTX M/S2', '275/55R21' ),
            $this->keyed( 'Michelin', 'Defender LTX M/S2', '275/55R21', array( $this->listing( 'The Tire Rack', 0 ) ) ),
            array( '275/55R21' => true ),
            $this->now
        );

        $this->assertSame( RTG_Catalog_Presence::STATUS_LISTED, $result['status'] );
        $this->assertStringContainsString( 'The Tire Rack', $result['label'] );
    }

    /**
     * One missed day is ordinary — a slow run, a failed request — and must not
     * be reported as the retailer dropping the product.
     */
    public function test_a_single_missed_day_is_not_a_delisting() {
        $result = RTG_Catalog_Presence::evaluate_one(
            $this->tire( 'Michelin', 'Defender LTX M/S2', '275/55R21' ),
            $this->keyed( 'Michelin', 'Defender LTX M/S2', '275/55R21', array( $this->listing( 'The Tire Rack', 1 ) ) ),
            array( '275/55R21' => true ),
            $this->now
        );

        $this->assertSame( RTG_Catalog_Presence::STATUS_LISTED, $result['status'] );
    }

    /**
     * A listing that has stopped appearing in a fitment being read completely
     * is the retailer's decision, and is dated.
     */
    public function test_a_stale_listing_in_a_read_fitment_is_a_delisting() {
        $result = RTG_Catalog_Presence::evaluate_one(
            $this->tire( 'Nitto', 'Ridge Grappler', '275/65R20' ),
            $this->keyed( 'Nitto', 'Ridge Grappler', '275/65R20', array( $this->listing( 'The Tire Rack', 40 ) ) ),
            array( '275/65R20' => true ),
            $this->now
        );

        $this->assertSame( RTG_Catalog_Presence::STATUS_DELISTED, $result['status'] );
        $this->assertStringContainsString( 'The Tire Rack', $result['label'] );
        $this->assertGreaterThan( 0, $result['last_seen'] );
    }

    /**
     * The distinction the whole class exists for.
     *
     * A listing can go stale because the retailer dropped it, or because our
     * own sweep never read that fitment. Reporting the second as the first
     * would send someone to renegotiate a link that was never dropped.
     */
    public function test_an_unread_fitment_is_never_reported_as_a_delisting() {
        $result = RTG_Catalog_Presence::evaluate_one(
            $this->tire( 'Nitto', 'Ridge Grappler', '275/65R20' ),
            $this->keyed( 'Nitto', 'Ridge Grappler', '275/65R20', array( $this->listing( 'The Tire Rack', 40 ) ) ),
            array(), // Nothing was read completely.
            $this->now
        );

        $this->assertSame( RTG_Catalog_Presence::STATUS_UNKNOWN, $result['status'] );
        $this->assertStringContainsString( '275/65R20', $result['label'] );
    }

    /**
     * A tire no sweep has ever seen was not dropped — it was never there.
     */
    public function test_a_tire_never_seen_is_not_a_delisting() {
        $result = RTG_Catalog_Presence::evaluate_one(
            $this->tire( 'Michelin', 'Defender LTX M/S2', '305/45R22' ),
            array(),
            array( '305/45R22' => true ),
            $this->now
        );

        $this->assertSame( RTG_Catalog_Presence::STATUS_NEVER_LISTED, $result['status'] );
    }

    /**
     * A guide row that cannot be keyed can't be looked up either way.
     */
    public function test_an_unkeyable_tire_is_unknown() {
        $result = RTG_Catalog_Presence::evaluate_one(
            $this->tire( '', 'Mystery', '275/55R21' ),
            array(),
            array( '275/55R21' => true ),
            $this->now
        );

        $this->assertSame( RTG_Catalog_Presence::STATUS_UNKNOWN, $result['status'] );
    }

    /**
     * Only fitments read to completion may support a delisting claim.
     */
    public function test_only_completely_read_fitments_are_eligible() {
        $stats = array(
            'sources' => array(
                array(
                    'coverage' => array(
                        '275/65R20' => array( 'received' => 5091, 'total' => 5091 ),
                        '255/55R21' => array( 'received' => 1000, 'total' => 5643 ),
                        '305/45R22' => array( 'received' => 1994, 'total' => null ),
                    ),
                ),
            ),
        );

        $sizes = RTG_Catalog_Presence::fully_read_sizes( $stats );

        $this->assertArrayHasKey( '275/65R20', $sizes );
        $this->assertArrayNotHasKey( '255/55R21', $sizes );
        $this->assertArrayNotHasKey( '305/45R22', $sizes );
    }

    /**
     * A tire listed by two retailers, one of which dropped it, is still listed.
     */
    public function test_one_retailer_still_listing_keeps_it_current() {
        $result = RTG_Catalog_Presence::evaluate_one(
            $this->tire( 'Nitto', 'Ridge Grappler', '275/65R20' ),
            $this->keyed( 'Nitto', 'Ridge Grappler', '275/65R20', array(
                $this->listing( 'The Tire Rack', 40 ),
                $this->listing( 'SimpleTire', 0 ),
            ) ),
            array( '275/65R20' => true ),
            $this->now
        );

        $this->assertSame( RTG_Catalog_Presence::STATUS_LISTED, $result['status'] );
    }

    /**
     * And it names only the retailer still listing it.
     *
     * Naming every retailer that ever did would credit one that dropped the
     * tire a month ago as though it still carried it — and the fact that it
     * dropped is worth saying rather than hiding behind the one that didn't.
     */
    public function test_a_current_listing_names_who_still_carries_it() {
        $result = RTG_Catalog_Presence::evaluate_one(
            $this->tire( 'Nitto', 'Ridge Grappler', '275/65R20' ),
            $this->keyed( 'Nitto', 'Ridge Grappler', '275/65R20', array(
                $this->listing( 'The Tire Rack', 40 ),
                $this->listing( 'SimpleTire', 0 ),
            ) ),
            array( '275/65R20' => true ),
            $this->now
        );

        $this->assertSame( 'Listed by SimpleTire. The Tire Rack stopped listing it.', $result['label'] );
    }

    /**
     * A delisting is dated from the most recent sighting. Two retailers that
     * dropped a tire weeks apart did not both stop on the later date.
     */
    public function test_a_delisting_is_dated_from_the_last_retailer_to_drop_it() {
        $result = RTG_Catalog_Presence::evaluate_one(
            $this->tire( 'Nitto', 'Ridge Grappler', '275/65R20' ),
            $this->keyed( 'Nitto', 'Ridge Grappler', '275/65R20', array(
                $this->listing( 'SimpleTire', 55 ),
                $this->listing( 'The Tire Rack', 40 ),
            ) ),
            array( '275/65R20' => true ),
            $this->now
        );

        $this->assertSame( RTG_Catalog_Presence::STATUS_DELISTED, $result['status'] );
        $this->assertStringContainsString( 'Last listed by The Tire Rack', $result['label'] );
        $this->assertStringNotContainsString( 'SimpleTire', $result['label'] );
    }
}
