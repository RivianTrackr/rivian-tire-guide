<?php
/**
 * Tests for RTG_Price_Sync.
 *
 * A tire carries one price and one purchase link, shown together on the page,
 * so the rule these cover is narrow and deliberate: a price may only come from
 * the retailer the link actually points to. Taking the cheaper figure from the
 * other retailer would put a price on the site that doesn't match what the
 * reader sees on click.
 *
 * Nothing here performs a network request.
 */
class Test_RTG_Price_Sync extends WP_UnitTestCase {

    /**
     * Build a candidate row as the sync would see it.
     */
    private function candidate( $advertiser, $price ) {
        return array( 'advertiser_name' => $advertiser, 'price' => $price );
    }

    // --- Link resolution ---

    /**
     * A plain retailer URL resolves from its hostname.
     */
    public function test_resolves_a_plain_retailer_link() {
        $this->assertSame(
            'Tire Rack',
            RTG_Price_Sync::resolve_link_retailer( 'https://www.tirerack.com/tires/detail?tireModel=x' )
        );
        $this->assertSame(
            'SimpleTire',
            RTG_Price_Sync::resolve_link_retailer( 'https://simpletire.com/products/sample' )
        );
    }

    /**
     * A CJ deep link carries the destination in a query parameter, so the
     * hostname is a tracking domain and says nothing about the retailer.
     */
    public function test_resolves_a_link_through_an_affiliate_redirect() {
        $this->assertSame(
            'Tire Rack',
            RTG_Price_Sync::resolve_link_retailer(
                'https://www.anrdoezrs.net/click-1234567-15559262?url=https%3A%2F%2Fwww.tirerack.com%2Ftires%2Fx'
            )
        );
        $this->assertSame(
            'SimpleTire',
            RTG_Price_Sync::resolve_link_retailer(
                'https://www.kqzyfj.com/click-1-2?url=https%3A%2F%2Fsimpletire.com%2Fproducts%2Fy'
            )
        );
    }

    /**
     * Networks sometimes encode the destination twice.
     */
    public function test_resolves_a_double_encoded_destination() {
        $this->assertSame(
            'Tire Rack',
            RTG_Price_Sync::resolve_link_retailer(
                'https://www.dpbolvw.net/click-1-2?url=https%253A%252F%252Fwww.tirerack.com%252Fa'
            )
        );
    }

    /**
     * A link to anywhere else resolves to nothing.
     *
     * This is the guard that stops one retailer's price being attached to
     * another's link — the failure the whole rule exists to prevent.
     */
    public function test_a_link_elsewhere_resolves_to_nothing() {
        foreach ( array(
            'https://www.amazon.com/dp/B01',
            'https://amzn.to/3abc',
            'https://www.discounttire.com/fitment/x',
            'https://www.michelinman.com/tires/x',
            '',
        ) as $link ) {
            $this->assertSame( '', RTG_Price_Sync::resolve_link_retailer( $link ), "Link should not resolve: {$link}" );
        }
    }

    // --- Decisions ---

    /**
     * The price comes from the linked retailer even when the other is cheaper.
     */
    public function test_takes_the_linked_retailers_price_not_the_lowest() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 300.00 ),
            array( $this->candidate( 'Tire Rack', 289.99 ), $this->candidate( 'SimpleTire', 249.99 ) )
        );

        $this->assertTrue( $decision['update'] );
        $this->assertEquals( 289.99, round( $decision['price'], 2 ) );
        $this->assertSame( 'Tire Rack', $decision['retailer'] );
    }

    /**
     * The same tire linked to the other retailer takes that one's price, which
     * is the point: the figure follows the link, not the market.
     */
    public function test_follows_the_link_when_it_points_at_the_other_retailer() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => 'https://simpletire.com/products/y', 'price' => 300.00 ),
            array( $this->candidate( 'Tire Rack', 289.99 ), $this->candidate( 'SimpleTire', 249.99 ) )
        );

        $this->assertEquals( 249.99, round( $decision['price'], 2 ) );
        $this->assertSame( 'SimpleTire', $decision['retailer'] );
    }

    /**
     * Nothing is written when the linked retailer isn't listing the tire.
     */
    public function test_does_not_update_when_the_linked_retailer_is_absent() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 300.00 ),
            array( $this->candidate( 'SimpleTire', 249.99 ) )
        );

        $this->assertFalse( $decision['update'] );
        $this->assertSame( 'retailer_not_carrying', $decision['code'] );
    }

    /**
     * A tire linked somewhere unpriced is left alone and says so.
     */
    public function test_leaves_a_tire_linked_elsewhere_alone() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.amazon.com/dp/B01', 'price' => 300.00 ),
            array( $this->candidate( 'Tire Rack', 289.99 ) )
        );

        $this->assertFalse( $decision['update'] );
        $this->assertSame( 'link_not_priced', $decision['code'] );
    }

    /**
     * A tire with no link has no retailer to price against.
     */
    public function test_reports_a_tire_with_no_link() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => '', 'price' => 300.00 ),
            array( $this->candidate( 'Tire Rack', 289.99 ) )
        );

        $this->assertSame( 'no_link', $decision['code'] );
    }

    /**
     * An unchanged price is a no-op, not a write.
     */
    public function test_an_unchanged_price_is_a_no_op() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 289.99 ),
            array( $this->candidate( 'Tire Rack', 289.99 ) )
        );

        $this->assertFalse( $decision['update'] );
        $this->assertSame( 'unchanged', $decision['code'] );
    }

    /**
     * A wild swing is reported rather than written.
     *
     * Match keys are brand + model + size and can collide across load ratings,
     * so a price that moves by more than half is more likely to be the wrong
     * tire than a real sale.
     */
    public function test_guards_an_implausible_swing_but_allows_a_normal_sale() {
        $wild = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 300.00 ),
            array( $this->candidate( 'Tire Rack', 99.00 ) )
        );
        $this->assertFalse( $wild['update'] );
        $this->assertSame( 'change_implausible', $wild['code'] );

        $sale = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 300.00 ),
            array( $this->candidate( 'Tire Rack', 255.00 ) )
        );
        $this->assertTrue( $sale['update'] );
    }

    /**
     * A tire with no price yet takes one, with no swing to measure against.
     */
    public function test_a_tire_with_no_price_gets_one() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 0 ),
            array( $this->candidate( 'Tire Rack', 289.99 ) )
        );

        $this->assertTrue( $decision['update'] );
        $this->assertEquals( 289.99, round( $decision['price'], 2 ) );
    }

    /**
     * A zero or missing price is not a quote.
     */
    public function test_ignores_a_zero_price() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 300.00 ),
            array( $this->candidate( 'Tire Rack', 0 ) )
        );

        $this->assertSame( 'retailer_not_carrying', $decision['code'] );
    }

    /**
     * With several listings from the linked retailer, the cheapest wins —
     * the reader can reach that one through the same link.
     */
    public function test_takes_the_cheapest_listing_from_the_linked_retailer() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 300.00 ),
            array( $this->candidate( 'Tire Rack', 289.99 ), $this->candidate( 'Tire Rack', 275.50 ) )
        );

        $this->assertEquals( 275.50, round( $decision['price'], 2 ) );
    }

    /**
     * The swing guard is configurable, and a tighter one rejects more.
     */
    public function test_the_swing_guard_is_configurable() {
        $tire      = array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 300.00 );
        $candidate = array( $this->candidate( 'Tire Rack', 255.00 ) );

        $this->assertTrue( RTG_Price_Sync::decide( $tire, $candidate, 0.5 )['update'] );
        $this->assertFalse( RTG_Price_Sync::decide( $tire, $candidate, 0.05 )['update'] );
    }

    // --- Retailer name matching ---

    /**
     * CJ's advertiser name and the resolver's name for the same retailer
     * disagree, and the price sync has to see through it.
     *
     * The first live run refreshed nothing: CJ calls the advertiser "The Tire
     * Rack" while a link resolves to "Tire Rack", and an exact comparison
     * judged every Tire Rack tire as "that retailer isn't listing this". 103
     * covered tires, none priced.
     */
    public function test_the_tire_rack_and_tire_rack_are_the_same_retailer() {
        $this->assertSame(
            RTG_Price_Sync::normalize_retailer( 'Tire Rack' ),
            RTG_Price_Sync::normalize_retailer( 'The Tire Rack' )
        );
        $this->assertSame(
            RTG_Price_Sync::normalize_retailer( 'SimpleTire' ),
            RTG_Price_Sync::normalize_retailer( 'Simple Tire' )
        );
        $this->assertSame(
            RTG_Price_Sync::normalize_retailer( 'Tire Rack' ),
            RTG_Price_Sync::normalize_retailer( 'TIRE RACK' )
        );
    }

    /**
     * Loosening the comparison must not make two retailers interchangeable —
     * that would attach one's price to the other's link, the failure the whole
     * rule exists to prevent.
     */
    public function test_distinct_retailers_still_differ() {
        $this->assertNotSame(
            RTG_Price_Sync::normalize_retailer( 'Tire Rack' ),
            RTG_Price_Sync::normalize_retailer( 'SimpleTire' )
        );
    }

    /**
     * "The" is dropped only as a leading word, never from inside a name.
     */
    public function test_the_is_stripped_only_as_a_prefix() {
        $this->assertSame( 'otires', RTG_Price_Sync::normalize_retailer( 'Theo Tires' ) );
    }

    /**
     * The live case, end to end: a real CJ deep link and CJ's own advertiser
     * spelling now price the tire.
     */
    public function test_a_real_affiliate_link_prices_from_cjs_advertiser_name() {
        $link = 'https://www.tkqlhce.com/click-101098512-13697786?url=https%3A%2F%2Fwww.tirerack.com%2Ftires%2Ftires.jsp%3FtireModel%3DDefender';

        $this->assertSame( 'Tire Rack', RTG_Price_Sync::resolve_link_retailer( $link ) );

        $decision = RTG_Price_Sync::decide(
            array( 'link' => $link, 'price' => 299.00 ),
            array( array( 'advertiser_name' => 'The Tire Rack', 'price' => 274.50 ) )
        );

        $this->assertTrue( $decision['update'] );
        $this->assertEquals( 274.50, round( $decision['price'], 2 ) );
    }

    /**
     * A tire linked to one retailer is still not priced from the other.
     */
    public function test_the_other_retailer_is_still_rejected() {
        $decision = RTG_Price_Sync::decide(
            array( 'link' => 'https://www.tirerack.com/tires/x', 'price' => 299.00 ),
            array( array( 'advertiser_name' => 'SimpleTire', 'price' => 274.50 ) )
        );

        $this->assertSame( 'retailer_not_carrying', $decision['code'] );
    }
}
