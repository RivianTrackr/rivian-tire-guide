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

    // --- Change detection (what decides whether the sync writes at all) ---

    /**
     * MySQL hands numbers back as strings ("12345.00"); an unchanged feed
     * must not read as a change, or every five-minute run rewrites every
     * matched tire — which is the churn this check exists to stop.
     */
    public function test_identical_data_in_mysql_string_form_is_not_a_change() {
        $current = array(
            'roamer_efficiency'        => '2.50',
            'roamer_total_km'          => '12345.00',
            'roamer_vehicle_count'     => '4',
            'roamer_vehicle_breakdown' => '[["Gen 1 R1T",4]]',
        );
        $update = array(
            'roamer_efficiency'        => 2.5,
            'roamer_total_km'          => 12345.0,
            'roamer_vehicle_count'     => 4,
            'roamer_vehicle_breakdown' => '[["Gen 1 R1T",4]]',
        );

        $this->assertFalse( RTG_Roamer_Sync::roamer_data_changed( $current, $update ) );
    }

    public function test_a_moved_number_or_new_breakdown_is_a_change() {
        $current = array(
            'roamer_efficiency'        => '2.50',
            'roamer_vehicle_breakdown' => '[["Gen 1 R1T",4]]',
        );

        $this->assertTrue( RTG_Roamer_Sync::roamer_data_changed( $current, array( 'roamer_efficiency' => 2.51 ) ) );
        $this->assertTrue( RTG_Roamer_Sync::roamer_data_changed( $current, array( 'roamer_vehicle_breakdown' => '[["Gen 1 R1T",5]]' ) ) );
        $this->assertTrue( RTG_Roamer_Sync::roamer_data_changed( array(), array( 'roamer_tire_id' => 'r-1' ) ) );
    }

    // --- The targeted writer (feed data must not look like a human edit) ---

    /**
     * update_roamer_data() writes the feed columns without bumping
     * updated_at — that column tracks human edits, and the stale-price
     * report reads it. update_tire() would have fired the schema's
     * ON UPDATE CURRENT_TIMESTAMP.
     */
    public function test_roamer_write_leaves_updated_at_alone() {
        RTG_Activator::activate();
        RTG_Database::insert_tire( array(
            'tire_id' => 'roamer-write-001',
            'brand'   => 'TestBrand',
            'model'   => 'TestModel',
            'size'    => '275/65R20',
        ) );

        $before = RTG_Database::get_tire( 'roamer-write-001' );

        // updated_at has one-second resolution; cross a boundary so a bump
        // would be visible.
        sleep( 1 );

        RTG_Database::update_roamer_data( 'roamer-write-001', array(
            'roamer_efficiency' => 2.31,
            'roamer_synced_at'  => current_time( 'mysql' ),
        ) );

        $after = RTG_Database::get_tire( 'roamer-write-001' );
        $this->assertEquals( 2.31, floatval( $after['roamer_efficiency'] ) );
        $this->assertSame( $before['updated_at'], $after['updated_at'] );
    }

    public function test_roamer_write_supports_null_and_ignores_foreign_columns() {
        RTG_Activator::activate();
        RTG_Database::insert_tire( array(
            'tire_id'          => 'roamer-write-002',
            'brand'            => 'TestBrand',
            'model'            => 'TestModel',
            'size'             => '275/65R20',
            'price'            => 199.99,
            'roamer_synced_at' => '2026-01-01 00:00:00',
        ) );

        RTG_Database::update_roamer_data( 'roamer-write-002', array(
            'roamer_synced_at' => null,
            'price'            => 1.00, // not a roamer column — must be ignored
        ) );

        $tire = RTG_Database::get_tire( 'roamer-write-002' );
        $this->assertNull( $tire['roamer_synced_at'] );
        $this->assertEquals( 199.99, floatval( $tire['price'] ) );
    }
}
