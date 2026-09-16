<?php
/**
 * Tests for RTG_Stock_Sync.
 *
 * A tire's stock is its linked retailer's word, read from the listings the
 * nightly sweep matched to it. Only that retailer can say "out of stock",
 * for the same reason only it can set the price: the button names it. The
 * other retailer is never a verdict, only somewhere to send the reader
 * when it has the tire in stock on a tracked link.
 *
 * decide() and note() are pure; nothing here touches the network.
 */
class Test_RTG_Stock_Sync extends WP_UnitTestCase {

    private $domains = array( 'anrdoezrs.net', 'tkqlhce.com' );
    private $now;

    public function set_up() {
        parent::set_up();
        $this->now = strtotime( '2026-09-16 09:00:00' );
    }

    /**
     * Build a candidate row as the sync would see it: seen this morning
     * unless told otherwise.
     */
    private function listing( $advertiser, $availability, $overrides = array() ) {
        return array_merge( array(
            'advertiser_name' => $advertiser,
            'availability'    => $availability,
            'price'           => 289.99,
            'link'            => 'https://www.anrdoezrs.net/click-123?url=https%3A%2F%2Fsimpletire.com%2Fx',
            'last_seen_at'    => '2026-09-16 03:00:00',
        ), $overrides );
    }

    private function tire() {
        return array( 'tire_id' => 't1', 'link' => 'https://www.tirerack.com/tires/x' );
    }

    // --- decide() ---

    public function test_a_fresh_in_stock_listing_from_the_linked_retailer_is_in_stock() {
        $d = RTG_Stock_Sync::decide( $this->tire(), array( $this->listing( 'Tire Rack', 'in stock' ) ), $this->domains, $this->now );

        $this->assertSame( RTG_Stock_Sync::IN_STOCK, $d['status'] );
        $this->assertSame( 'Tire Rack', $d['retailer'] );
        $this->assertSame( '', $d['alt_retailer'] );
    }

    public function test_a_fresh_out_of_stock_listing_from_the_linked_retailer_is_out_of_stock() {
        $d = RTG_Stock_Sync::decide( $this->tire(), array( $this->listing( 'Tire Rack', 'out of stock' ) ), $this->domains, $this->now );

        $this->assertSame( RTG_Stock_Sync::OUT_OF_STOCK, $d['status'] );
        $this->assertSame( 'out_of_stock', $d['code'] );
        $this->assertSame( '', $d['alt_retailer'] );
    }

    /**
     * The same tire is often listed twice; one the retailer can sell
     * outranks one it cannot.
     */
    public function test_one_sellable_listing_outranks_a_sold_out_one() {
        $d = RTG_Stock_Sync::decide( $this->tire(), array(
            $this->listing( 'Tire Rack', 'out of stock' ),
            $this->listing( 'Tire Rack', 'in stock' ),
        ), $this->domains, $this->now );

        $this->assertSame( RTG_Stock_Sync::IN_STOCK, $d['status'] );
    }

    /**
     * Out of stock at the linked retailer, in stock on a tracked link at
     * the other: the other is offered, cheapest first.
     */
    public function test_the_other_retailers_in_stock_tracked_listing_is_offered() {
        $d = RTG_Stock_Sync::decide( $this->tire(), array(
            $this->listing( 'Tire Rack', 'out of stock' ),
            $this->listing( 'SimpleTire', 'in stock', array( 'price' => 299.00, 'link' => 'https://www.tkqlhce.com/click-1?url=a' ) ),
            $this->listing( 'SimpleTire', 'in stock', array( 'price' => 279.00, 'link' => 'https://www.tkqlhce.com/click-2?url=b' ) ),
        ), $this->domains, $this->now );

        $this->assertSame( RTG_Stock_Sync::OUT_OF_STOCK, $d['status'] );
        $this->assertSame( 'SimpleTire', $d['alt_retailer'] );
        $this->assertSame( 'https://www.tkqlhce.com/click-2?url=b', $d['alt_link'] );
        $this->assertStringContainsString( 'in stock at SimpleTire', $d['label'] );
    }

    /**
     * An alternative has to be sellable and earn the click: sold out,
     * untracked or stale listings at the other retailer are not offered.
     */
    public function test_an_alternative_must_be_in_stock_tracked_and_fresh() {
        $sold_out  = $this->listing( 'SimpleTire', 'out of stock' );
        $untracked = $this->listing( 'SimpleTire', 'in stock', array( 'link' => 'https://simpletire.com/x' ) );
        $stale     = $this->listing( 'SimpleTire', 'in stock', array( 'last_seen_at' => '2026-09-01 03:00:00' ) );

        foreach ( array( $sold_out, $untracked, $stale ) as $other ) {
            $d = RTG_Stock_Sync::decide( $this->tire(), array( $this->listing( 'Tire Rack', 'out of stock' ), $other ), $this->domains, $this->now );
            $this->assertSame( RTG_Stock_Sync::OUT_OF_STOCK, $d['status'] );
            $this->assertSame( '', $d['alt_retailer'] );
        }
    }

    /**
     * The other retailer's stock is never a verdict on the tire.
     */
    public function test_stock_at_the_other_retailer_alone_decides_nothing() {
        $d = RTG_Stock_Sync::decide( $this->tire(), array( $this->listing( 'SimpleTire', 'out of stock' ) ), $this->domains, $this->now );

        $this->assertSame( '', $d['status'] );
        $this->assertSame( 'no_wording', $d['code'] );
    }

    /**
     * A listing older than the window, or one that says nothing about
     * stock, has no opinion; a link discovery cannot read stock for has none.
     */
    public function test_stale_or_silent_listings_and_untracked_links_give_no_verdict() {
        $stale = RTG_Stock_Sync::decide( $this->tire(), array( $this->listing( 'Tire Rack', 'out of stock', array( 'last_seen_at' => '2026-09-01 03:00:00' ) ) ), $this->domains, $this->now );
        $this->assertSame( '', $stale['status'] );

        $silent = RTG_Stock_Sync::decide( $this->tire(), array( $this->listing( 'Tire Rack', '' ) ), $this->domains, $this->now );
        $this->assertSame( '', $silent['status'] );
        $this->assertSame( 'no_wording', $silent['code'] );

        $elsewhere = RTG_Stock_Sync::decide( array( 'link' => 'https://www.amazon.com/dp/x' ), array( $this->listing( 'Tire Rack', 'out of stock' ) ), $this->domains, $this->now );
        $this->assertSame( '', $elsewhere['status'] );
        $this->assertSame( 'link_not_tracked', $elsewhere['code'] );
    }

    // --- note() ---

    public function test_a_fresh_out_of_stock_verdict_earns_a_line_naming_the_retailer() {
        $note = RTG_Stock_Sync::note( array(
            'link'               => 'https://www.tirerack.com/tires/x',
            'stock_status'       => RTG_Stock_Sync::OUT_OF_STOCK,
            'stock_checked_at'   => '2026-09-15 03:00:00',
            'stock_alt_retailer' => 'SimpleTire',
            'stock_alt_link'     => 'https://www.tkqlhce.com/click-2?url=b',
        ), $this->now );

        $this->assertTrue( $note['show'] );
        $this->assertSame( 'Out of stock at Tire Rack', $note['label'] );
        $this->assertStringContainsString( 'Sep 15', $note['title'] );
        $this->assertSame( 'SimpleTire', $note['alt_retailer'] );
    }

    /**
     * In stock is the normal state and says nothing; a verdict older than
     * the window is withheld rather than shown stale.
     */
    public function test_in_stock_and_stale_verdicts_say_nothing() {
        $in_stock = RTG_Stock_Sync::note( array( 'stock_status' => RTG_Stock_Sync::IN_STOCK, 'stock_checked_at' => '2026-09-15 03:00:00' ), $this->now );
        $this->assertFalse( $in_stock['show'] );

        $stale = RTG_Stock_Sync::note( array( 'stock_status' => RTG_Stock_Sync::OUT_OF_STOCK, 'stock_checked_at' => '2026-09-01 03:00:00' ), $this->now );
        $this->assertFalse( $stale['show'] );
    }

    // --- run() against the database ---

    /**
     * The nightly write holds updated_at where it was: a stock check is not
     * an edit, and must not make a hand-typed price look freshly reviewed.
     */
    public function test_writing_stock_does_not_touch_updated_at() {
        global $wpdb;
        RTG_Database::insert_tire( array(
            'tire_id' => 'stock-001', 'brand' => 'Bridgestone', 'model' => 'Dueler', 'size' => '275/65R20',
            'link'    => 'https://www.tirerack.com/tires/x', 'price' => 385,
        ) );
        $wpdb->update( $wpdb->prefix . 'rtg_tires', array( 'updated_at' => '2026-01-01 00:00:00' ), array( 'tire_id' => 'stock-001' ) );

        RTG_Database::update_stock_data( 'stock-001', array(
            'stock_status'       => RTG_Stock_Sync::OUT_OF_STOCK,
            'stock_checked_at'   => '2026-09-16 03:00:00',
            'stock_alt_retailer' => 'SimpleTire',
            'stock_alt_link'     => 'https://www.tkqlhce.com/click-2?url=b',
        ) );
        RTG_Database::flush_cache();

        $tire = RTG_Database::get_tire( 'stock-001' );
        $this->assertSame( RTG_Stock_Sync::OUT_OF_STOCK, $tire['stock_status'] );
        $this->assertSame( 'SimpleTire', $tire['stock_alt_retailer'] );
        $this->assertSame( '2026-01-01 00:00:00', $tire['updated_at'] );

        RTG_Database::update_stock_data( 'stock-001', array( 'stock_status' => '', 'stock_checked_at' => null ) );
        RTG_Database::flush_cache();
        $this->assertNull( RTG_Database::get_tire( 'stock-001' )['stock_checked_at'] );
    }
}
