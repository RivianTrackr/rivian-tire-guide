<?php
/**
 * Tests for RTG_Stale_Prices.
 *
 * The rule being pinned: a stale price is one nothing has touched — not
 * synced, not edited — in the window. Either kind of touch resets the clock,
 * because the point is a checklist of what a person needs to look at, not a
 * nag about tires someone just handled.
 */
class Test_RTG_Stale_Prices extends WP_UnitTestCase {

    private $now;

    public function setUp(): void {
        parent::setUp();
        $this->now = strtotime( '2026-08-25 09:00:00' );
    }

    private function tire( $overrides = array() ) {
        return array_merge( array(
            'tire_id' => 't1',
            'brand'   => 'Nokian',
            'model'   => 'One H/T',
            'size'    => '275/50R22',
            'price'   => 227.00,
        ), $overrides );
    }

    private function ago( $days ) {
        return gmdate( 'Y-m-d H:i:s', $this->now - $days * DAY_IN_SECONDS );
    }

    public function test_an_untouched_old_price_is_stale() {
        $stale = RTG_Stale_Prices::find_stale(
            array( $this->tire( array( 'updated_at' => $this->ago( 120 ) ) ) ),
            $this->now
        );

        $this->assertCount( 1, $stale );
    }

    public function test_a_recent_manual_edit_resets_the_clock() {
        $stale = RTG_Stale_Prices::find_stale(
            array( $this->tire( array( 'updated_at' => $this->ago( 10 ) ) ) ),
            $this->now
        );

        $this->assertSame( array(), $stale );
    }

    public function test_a_recent_sync_resets_the_clock_even_over_an_old_edit() {
        $stale = RTG_Stale_Prices::find_stale(
            array( $this->tire( array(
                'updated_at'      => $this->ago( 200 ),
                'price_synced_at' => $this->ago( 1 ),
            ) ) ),
            $this->now
        );

        $this->assertSame( array(), $stale );
    }

    public function test_a_tire_without_a_price_has_nothing_to_go_stale() {
        $stale = RTG_Stale_Prices::find_stale(
            array( $this->tire( array( 'price' => 0, 'updated_at' => $this->ago( 300 ) ) ) ),
            $this->now
        );

        $this->assertSame( array(), $stale );
    }

    public function test_the_list_leads_with_the_oldest() {
        $stale = RTG_Stale_Prices::find_stale(
            array(
                $this->tire( array( 'tire_id' => 'newer', 'updated_at' => $this->ago( 100 ) ) ),
                $this->tire( array( 'tire_id' => 'oldest', 'updated_at' => $this->ago( 300 ) ) ),
            ),
            $this->now
        );

        $this->assertSame( 'oldest', $stale[0]['tire_id'] );
    }
}
