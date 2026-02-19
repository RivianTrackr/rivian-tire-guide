<?php
/**
 * Tests for RTG_Database class.
 */
class Test_RTG_Database extends WP_UnitTestCase {

    /**
     * Run the activator to ensure tables exist before each test.
     */
    public function setUp(): void {
        parent::setUp();
        RTG_Activator::activate();
    }

    /**
     * Helper to create a sample tire.
     */
    private function sample_tire( $overrides = array() ) {
        return array_merge( array(
            'tire_id'          => 'test-tire-' . wp_rand( 1000, 9999 ),
            'size'             => '275/65R20',
            'diameter'         => '20"',
            'brand'            => 'TestBrand',
            'model'            => 'TestModel',
            'category'         => 'All-Season',
            'price'            => 299.99,
            'mileage_warranty' => 60000,
            'weight_lb'        => 38.5,
            'three_pms'        => 'No',
            'tread'            => '10/32',
            'load_index'       => '116',
            'max_load_lb'      => 2756,
            'load_range'       => 'SL',
            'speed_rating'     => 'T',
            'psi'              => '51',
            'utqg'             => '620 A B',
            'tags'             => '',
            'link'             => 'https://example.com/tire',
            'image'            => 'https://riviantrackr.com/images/tire.jpg',
            'bundle_link'      => '',
            'sort_order'       => 0,
            'efficiency_score' => 75,
            'efficiency_grade' => 'B',
        ), $overrides );
    }

    public function test_insert_and_get_tire() {
        $data = $this->sample_tire( array( 'tire_id' => 'insert-test-001' ) );
        $id = RTG_Database::insert_tire( $data );

        $this->assertIsInt( $id );
        $this->assertGreaterThan( 0, $id );

        $tire = RTG_Database::get_tire( 'insert-test-001' );
        $this->assertIsArray( $tire );
        $this->assertEquals( 'TestBrand', $tire['brand'] );
        $this->assertEquals( 'TestModel', $tire['model'] );
        $this->assertEquals( '275/65R20', $tire['size'] );
    }

    public function test_update_tire() {
        $data = $this->sample_tire( array( 'tire_id' => 'update-test-001' ) );
        RTG_Database::insert_tire( $data );

        RTG_Database::update_tire( 'update-test-001', array( 'brand' => 'UpdatedBrand' ) );

        $tire = RTG_Database::get_tire( 'update-test-001' );
        $this->assertEquals( 'UpdatedBrand', $tire['brand'] );
    }

    public function test_delete_tire_removes_ratings() {
        $data = $this->sample_tire( array( 'tire_id' => 'del-test-001' ) );
        RTG_Database::insert_tire( $data );

        // Insert a rating.
        RTG_Database::set_rating( 'del-test-001', 1, 4 );

        // Verify rating exists.
        $ratings = RTG_Database::get_tire_ratings( array( 'del-test-001' ) );
        $this->assertArrayHasKey( 'del-test-001', $ratings );

        // Delete tire.
        RTG_Database::delete_tire( 'del-test-001' );

        // Tire should be gone.
        $tire = RTG_Database::get_tire( 'del-test-001' );
        $this->assertNull( $tire );

        // Ratings should also be gone (cascade delete).
        $ratings_after = RTG_Database::get_tire_ratings( array( 'del-test-001' ) );
        $this->assertArrayNotHasKey( 'del-test-001', $ratings_after );
    }

    public function test_tire_id_exists() {
        $data = $this->sample_tire( array( 'tire_id' => 'exists-test-001' ) );
        RTG_Database::insert_tire( $data );

        $this->assertTrue( RTG_Database::tire_id_exists( 'exists-test-001' ) );
        $this->assertFalse( RTG_Database::tire_id_exists( 'nonexistent-tire' ) );
    }

    public function test_get_tire_count() {
        $initial = RTG_Database::get_tire_count();

        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'count-001' ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'count-002' ) ) );

        $this->assertEquals( $initial + 2, RTG_Database::get_tire_count() );
    }

    public function test_get_tire_count_with_search() {
        RTG_Database::insert_tire( $this->sample_tire( array(
            'tire_id' => 'search-unique-xyz',
            'brand'   => 'UniqueBrandXyz',
        ) ) );

        $count = RTG_Database::get_tire_count( 'UniqueBrandXyz' );
        $this->assertEquals( 1, $count );
    }

    public function test_get_tires_as_array_format() {
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'array-fmt-001' ) ) );

        $tires = RTG_Database::get_tires_as_array();
        $this->assertIsArray( $tires );
        $this->assertNotEmpty( $tires );

        // Each row should be a numerically-indexed array of strings.
        $row = $tires[0];
        $this->assertIsArray( $row );
        $this->assertGreaterThanOrEqual( 23, count( $row ) );
        foreach ( $row as $val ) {
            $this->assertIsString( $val );
        }
    }

    public function test_get_filtered_tires_basic() {
        RTG_Database::insert_tire( $this->sample_tire( array(
            'tire_id'  => 'filter-test-001',
            'brand'    => 'FilterBrand',
            'category' => 'Winter',
        ) ) );

        $result = RTG_Database::get_filtered_tires( array( 'brand' => 'FilterBrand' ) );
        $this->assertArrayHasKey( 'rows', $result );
        $this->assertArrayHasKey( 'total', $result );
        $this->assertGreaterThanOrEqual( 1, $result['total'] );
    }

    public function test_get_filtered_tires_pagination() {
        for ( $i = 1; $i <= 5; $i++ ) {
            RTG_Database::insert_tire( $this->sample_tire( array(
                'tire_id' => "page-test-{$i}",
                'brand'   => 'PageTestBrand',
            ) ) );
        }

        $page1 = RTG_Database::get_filtered_tires( array( 'brand' => 'PageTestBrand' ), 'alpha', 1, 2 );
        $this->assertCount( 2, $page1['rows'] );
        $this->assertEquals( 5, $page1['total'] );

        $page3 = RTG_Database::get_filtered_tires( array( 'brand' => 'PageTestBrand' ), 'alpha', 3, 2 );
        $this->assertCount( 1, $page3['rows'] );
    }

    public function test_set_and_get_rating() {
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'rate-test-001' ) ) );

        RTG_Database::set_rating( 'rate-test-001', 1, 5 );

        $ratings = RTG_Database::get_tire_ratings( array( 'rate-test-001' ) );
        $this->assertArrayHasKey( 'rate-test-001', $ratings );
        $this->assertEquals( 5, (int) $ratings['rate-test-001']['average'] );
        $this->assertEquals( 1, (int) $ratings['rate-test-001']['count'] );
    }

    public function test_set_rating_upsert() {
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'upsert-test' ) ) );

        RTG_Database::set_rating( 'upsert-test', 1, 3 );
        RTG_Database::set_rating( 'upsert-test', 1, 5 ); // Update same user.

        $ratings = RTG_Database::get_tire_ratings( array( 'upsert-test' ) );
        $this->assertEquals( 5, (int) $ratings['upsert-test']['average'] );
        $this->assertEquals( 1, (int) $ratings['upsert-test']['count'] );
    }

    public function test_get_user_ratings() {
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'user-rate-001' ) ) );
        RTG_Database::set_rating( 'user-rate-001', 42, 4 );

        $user_ratings = RTG_Database::get_user_ratings( array( 'user-rate-001' ), 42 );
        $this->assertArrayHasKey( 'user-rate-001', $user_ratings );
        $this->assertEquals( 4, $user_ratings['user-rate-001']['rating'] );
    }

    public function test_cache_invalidation_on_insert() {
        // First call populates cache.
        $before = RTG_Database::get_all_tires();
        $count_before = count( $before );

        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'cache-test-001' ) ) );

        // Should get fresh data, not cached.
        $after = RTG_Database::get_all_tires();
        $this->assertEquals( $count_before + 1, count( $after ) );
    }

    public function test_efficiency_calculation() {
        $data = $this->sample_tire( array(
            'weight_lb'  => 35,
            'tread'      => '10/32',
            'load_range' => 'SL',
        ) );

        $result = RTG_Database::calculate_efficiency( $data );
        $this->assertArrayHasKey( 'efficiency_score', $result );
        $this->assertArrayHasKey( 'efficiency_grade', $result );
        $this->assertGreaterThan( 0, $result['efficiency_score'] );
        $this->assertContains( $result['efficiency_grade'], array( 'A', 'B', 'C', 'D', 'F' ) );
    }

    public function test_bulk_delete_removes_ratings() {
        $ids = array( 'bulk-del-001', 'bulk-del-002' );
        foreach ( $ids as $id ) {
            RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $id ) ) );
            RTG_Database::set_rating( $id, 1, 3 );
        }

        RTG_Database::delete_tires( $ids );

        foreach ( $ids as $id ) {
            $this->assertNull( RTG_Database::get_tire( $id ) );
        }

        $ratings = RTG_Database::get_tire_ratings( $ids );
        $this->assertEmpty( $ratings );
    }
}
