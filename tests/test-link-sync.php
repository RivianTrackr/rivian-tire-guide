<?php
/**
 * Tests for RTG_Link_Sync.
 *
 * The rules being pinned are the refusals as much as the writes: a link the
 * admin made affiliate is never touched, a plain link is only upgraded within
 * its own retailer, a stale listing's link is never applied, and an untracked
 * candidate link is recognized as the problem rather than offered as the fix.
 */
class Test_RTG_Link_Sync extends WP_UnitTestCase {

    private $now;
    private $domains;

    public function setUp(): void {
        parent::setUp();
        $this->now     = strtotime( '2026-08-25 09:00:00' );
        $this->domains = array( 'anrdoezrs.net', 'tkqlhce.com' );
    }

    private function tire( $link ) {
        return array( 'tire_id' => 't1', 'link' => $link );
    }

    private function listing( $overrides = array() ) {
        return array_merge( array(
            'link'            => 'https://www.tkqlhce.com/click-101098512-13697786?url=https%3A%2F%2Fwww.tirerack.com%2Fx',
            'advertiser_name' => 'The Tire Rack',
            'price'           => 350.00,
            'last_seen_at'    => gmdate( 'Y-m-d H:i:s', $this->now - HOUR_IN_SECONDS ),
        ), $overrides );
    }

    // --- The refusals ---

    /**
     * An existing affiliate link may carry a hand-chosen campaign or a
     * preferred retailer. It is never overwritten, whatever the catalog holds.
     */
    public function test_an_affiliate_link_is_never_touched() {
        $decision = RTG_Link_Sync::decide(
            $this->tire( 'https://www.anrdoezrs.net/links/101098512/type/dlg/https://simpletire.com/y' ),
            array( $this->listing( array( 'price' => 1.00 ) ) ),
            $this->domains,
            $this->now
        );

        $this->assertFalse( $decision['update'] );
        $this->assertSame( 'already_affiliate', $decision['code'] );
    }

    /**
     * A listing the sweep hasn't seen in days may describe a delisted product;
     * its link is not applied.
     */
    public function test_a_stale_listings_link_is_not_applied() {
        $decision = RTG_Link_Sync::decide(
            $this->tire( '' ),
            array( $this->listing( array( 'last_seen_at' => gmdate( 'Y-m-d H:i:s', $this->now - 10 * DAY_IN_SECONDS ) ) ) ),
            $this->domains,
            $this->now
        );

        $this->assertFalse( $decision['update'] );
        $this->assertSame( 'no_tracked_link', $decision['code'] );
    }

    /**
     * An untracked candidate link is the problem this sync fixes, not a fix —
     * and the reason names the missing website ID, since that is the cause.
     */
    public function test_an_untracked_candidate_link_is_not_a_fix() {
        $decision = RTG_Link_Sync::decide(
            $this->tire( '' ),
            array( $this->listing( array( 'link' => 'https://www.tirerack.com/tires/x' ) ) ),
            $this->domains,
            $this->now
        );

        $this->assertFalse( $decision['update'] );
        $this->assertStringContainsString( 'website ID', $decision['label'] );
    }

    /**
     * A regular link to a retailer the catalog doesn't cover is an editorial
     * choice; switching retailers is not this sync's call.
     */
    public function test_a_link_elsewhere_is_left_alone() {
        $decision = RTG_Link_Sync::decide(
            $this->tire( 'https://www.discounttire.com/p/x' ),
            array( $this->listing() ),
            $this->domains,
            $this->now
        );

        $this->assertFalse( $decision['update'] );
        $this->assertSame( 'link_elsewhere', $decision['code'] );
    }

    /**
     * A plain Tire Rack link is not upgraded with a SimpleTire tracked link —
     * same reason: where the reader lands was already chosen.
     */
    public function test_an_upgrade_never_switches_retailers() {
        $decision = RTG_Link_Sync::decide(
            $this->tire( 'https://www.tirerack.com/tires/x' ),
            array( $this->listing( array( 'advertiser_name' => 'SimpleTire',
                'link' => 'https://www.tkqlhce.com/click-101098512-1?url=https%3A%2F%2Fsimpletire.com%2Fy' ) ) ),
            $this->domains,
            $this->now
        );

        $this->assertFalse( $decision['update'] );
        $this->assertSame( 'no_tracked_link_same_retailer', $decision['code'] );
    }

    // --- The writes ---

    /**
     * A missing link gets the cheapest fresh tracked listing.
     */
    public function test_a_missing_link_gets_the_cheapest_tracked_listing() {
        $decision = RTG_Link_Sync::decide(
            $this->tire( '' ),
            array(
                $this->listing( array( 'price' => 400.00 ) ),
                $this->listing( array( 'advertiser_name' => 'SimpleTire', 'price' => 320.00,
                    'link' => 'https://www.tkqlhce.com/click-101098512-2?url=https%3A%2F%2Fsimpletire.com%2Fy' ) ),
            ),
            $this->domains,
            $this->now
        );

        $this->assertTrue( $decision['update'] );
        $this->assertSame( 'link_set', $decision['code'] );
        $this->assertSame( 'SimpleTire', $decision['retailer'] );
    }

    /**
     * A plain retailer link is upgraded in place: same retailer, now tracked.
     */
    public function test_a_regular_link_is_upgraded_within_its_retailer() {
        $decision = RTG_Link_Sync::decide(
            $this->tire( 'https://www.tirerack.com/tires/x' ),
            array( $this->listing() ),
            $this->domains,
            $this->now
        );

        $this->assertTrue( $decision['update'] );
        $this->assertSame( 'link_upgraded', $decision['code'] );
        $this->assertStringContainsString( 'tkqlhce.com', $decision['link'] );
    }

    /**
     * CJ spells the advertiser "The Tire Rack"; the resolver says "Tire Rack".
     * The upgrade must meet them in the middle, exactly as pricing does — the
     * mismatch that once kept every Tire Rack price frozen must not be
     * reintroduced here.
     */
    public function test_the_retailer_comparison_survives_cjs_spelling() {
        $decision = RTG_Link_Sync::decide(
            $this->tire( 'https://www.tirerack.com/tires/x' ),
            array( $this->listing( array( 'advertiser_name' => 'The Tire Rack' ) ) ),
            $this->domains,
            $this->now
        );

        $this->assertTrue( $decision['update'] );
    }

    // --- End to end ---

    /**
     * The whole loop against the database: a linkless tire, a matched
     * candidate with a tracked link, one run — and the tire both carries the
     * link and now classifies as affiliate on the page.
     */
    public function test_run_fills_a_linkless_tire_from_the_catalog() {
        RTG_Activator::activate();

        RTG_Database::insert_tire( array(
            'tire_id' => 'linkless-001',
            'brand'   => 'Nitto',
            'model'   => 'Ridge Grappler',
            'size'    => '275/65R20',
            'price'   => 390.00,
            'link'    => '',
        ) );

        RTG_Candidates::upsert( array(
            'source'          => 'cj',
            'advertiser_id'   => '1463221',
            'advertiser_name' => 'The Tire Rack',
            'external_id'     => 'link-cand-1',
            'brand'           => 'Nitto',
            'model'           => 'Ridge Grappler',
            'size'            => '275/65R20',
            'price'           => 401.00,
            'link'            => 'https://www.tkqlhce.com/click-101098512-13697786?url=https%3A%2F%2Fwww.tirerack.com%2Fx',
            'match_key'       => RTG_Catalog_Sync::match_key( 'Nitto', 'Ridge Grappler', '275/65R20' ),
            'qualifies'       => 1,
        ) );

        $results = RTG_Link_Sync::run();

        $this->assertSame( 1, $results['set'] );

        $stored = RTG_Database::get_tire( 'linkless-001' );
        $this->assertStringContainsString( 'tkqlhce.com', $stored['link'] );
        $this->assertSame(
            'affiliate',
            RTG_Link_Sync::classify( $stored['link'], RTG_Admin::get_affiliate_domains() )
        );
    }
}
