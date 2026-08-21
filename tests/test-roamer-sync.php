<?php
/**
 * Tests for RTG_Roamer_Sync::merge_entries().
 *
 * Roamer occasionally publishes the same physical tire under more than one
 * tire_id, so a guide tire can be linked to several IDs at once. These tests
 * cover the aggregation used both by the sync and by manual assignment.
 */
class Test_RTG_Roamer_Sync extends WP_UnitTestCase {

    /**
     * Helper to build a feed-shaped entry.
     */
    private function entry( $efficiency, $total_km, $vehicle_count, $breakdown = '' ) {
        return array(
            'efficiency'        => $efficiency,
            'total_km'          => $total_km,
            'vehicle_count'     => $vehicle_count,
            'vehicle_breakdown' => $breakdown,
        );
    }

    /**
     * A single entry passes through with its own values untouched.
     */
    public function test_single_entry_passes_through() {
        $result = RTG_Roamer_Sync::merge_entries( array(
            $this->entry( 2.5, 1000, 4, '[["Gen 1 R1T Dual",4]]' ),
        ) );

        $this->assertEquals( 2.5, $result['roamer_efficiency'] );
        $this->assertEquals( 1000, $result['roamer_total_km'] );
        $this->assertEquals( 4, $result['roamer_vehicle_count'] );
        $this->assertSame( '[["Gen 1 R1T Dual",4]]', $result['roamer_vehicle_breakdown'] );
    }

    /**
     * Two entries average efficiency weighted by distance, sum the counts,
     * and merge the vehicle breakdown per vehicle name.
     */
    public function test_two_entries_merge_weighted_by_distance() {
        $result = RTG_Roamer_Sync::merge_entries( array(
            $this->entry( 2.0, 1000, 4, '[["Gen 1 R1T Dual",4]]' ),
            $this->entry( 3.0, 3000, 2, '[["Gen 1 R1T Dual",1],["Gen 2 R1S",1]]' ),
        ) );

        // ( 2.0 * 1000 + 3.0 * 3000 ) / 4000 === 2.75
        $this->assertEquals( 2.75, $result['roamer_efficiency'] );
        $this->assertEquals( 4000, $result['roamer_total_km'] );
        $this->assertEquals( 6, $result['roamer_vehicle_count'] );

        $breakdown = json_decode( $result['roamer_vehicle_breakdown'], true );
        $this->assertContains( array( 'Gen 1 R1T Dual', 5 ), $breakdown );
        $this->assertContains( array( 'Gen 2 R1S', 1 ), $breakdown );
    }

    /**
     * Merging is order independent.
     */
    public function test_merge_is_order_independent() {
        $entries = array(
            $this->entry( 2.0, 1000, 1, '[["A",1]]' ),
            $this->entry( 3.0, 500, 2, '[["B",2]]' ),
        );

        $forward = RTG_Roamer_Sync::merge_entries( $entries );
        $reverse = RTG_Roamer_Sync::merge_entries( array_reverse( $entries ) );

        $this->assertSame( $forward['roamer_efficiency'], $reverse['roamer_efficiency'] );
        $this->assertSame( $forward['roamer_total_km'], $reverse['roamer_total_km'] );
        $this->assertSame( $forward['roamer_vehicle_count'], $reverse['roamer_vehicle_count'] );
    }

    /**
     * With no logged distance there is nothing to weight by, so efficiency
     * falls back to a plain mean rather than collapsing to zero.
     */
    public function test_zero_distance_falls_back_to_plain_mean() {
        $result = RTG_Roamer_Sync::merge_entries( array(
            $this->entry( 2.4, 0, 1 ),
            $this->entry( 2.6, 0, 1 ),
        ) );

        $this->assertEquals( 2.5, $result['roamer_efficiency'] );
        $this->assertEquals( 0, $result['roamer_total_km'] );
        $this->assertEquals( 2, $result['roamer_vehicle_count'] );
    }

    /**
     * An empty list yields zeroed columns rather than a division by zero.
     */
    public function test_empty_list_yields_zeroes() {
        $result = RTG_Roamer_Sync::merge_entries( array() );

        $this->assertEquals( 0, $result['roamer_efficiency'] );
        $this->assertEquals( 0, $result['roamer_total_km'] );
        $this->assertEquals( 0, $result['roamer_vehicle_count'] );
        $this->assertSame( '', $result['roamer_vehicle_breakdown'] );
    }

    /**
     * Entries missing keys entirely are tolerated.
     */
    public function test_missing_keys_are_tolerated() {
        $result = RTG_Roamer_Sync::merge_entries( array( array() ) );

        $this->assertEquals( 0, $result['roamer_efficiency'] );
        $this->assertSame( '', $result['roamer_vehicle_breakdown'] );
    }

    /**
     * The breakdown may arrive already decoded rather than as JSON.
     */
    public function test_breakdown_accepts_decoded_array() {
        $result = RTG_Roamer_Sync::merge_entries( array(
            $this->entry( 2.0, 100, 1, array( array( 'Gen 2 R1S', 3 ) ) ),
        ) );

        $breakdown = json_decode( $result['roamer_vehicle_breakdown'], true );
        $this->assertContains( array( 'Gen 2 R1S', 3 ), $breakdown );
    }
}
