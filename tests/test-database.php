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

    // --- Frontend row format ---

    /**
     * The JS reads fixed indexes up to row[31] (retailer label), so every
     * producer of frontend rows must emit the same 32 columns.
     * get_filtered_tires() once lagged at 28, which silently dropped the
     * tire-page links whenever server-side pagination was on.
     */
    public function test_every_frontend_row_producer_emits_the_same_32_columns() {
        RTG_Database::insert_tire( $this->sample_tire( array(
            'tire_id' => 'row-format-001',
            'brand'   => 'RowBrand',
            'model'   => 'RowModel',
            'link'    => 'https://www.tirerack.com/tires/row',
        ) ) );

        $expected_slug = RTG_Database::get_tire( 'row-format-001' )['slug'];
        $this->assertNotSame( '', $expected_slug, 'insert should have derived a slug' );

        $producers = array(
            'get_tires_as_array' => RTG_Database::get_tires_as_array(),
            'get_tires_by_ids'   => RTG_Database::get_tires_by_ids( array( 'row-format-001' ) ),
            'get_filtered_tires' => RTG_Database::get_filtered_tires( array( 'search' => 'RowBrand' ) )['rows'],
        );

        foreach ( $producers as $name => $rows ) {
            $row = null;
            foreach ( $rows as $candidate ) {
                if ( 'row-format-001' === $candidate[0] ) {
                    $row = $candidate;
                }
            }
            $this->assertNotNull( $row, "$name should return the inserted tire" );
            $this->assertCount( 32, $row, "$name row width" );
            $this->assertSame( $expected_slug, $row[28], "$name slug at index 28" );
            $this->assertSame( '', $row[29], "$name price_synced_at at index 29 (never synced)" );
            $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} /', $row[30], "$name updated_at at index 30" );
            $this->assertSame( 'Tire Rack', $row[31], "$name retailer label at index 31" );
        }
    }


    /**
     * The server-side price filter applies whatever ceiling it is given. It
     * used to gate on `< 600` — a sentinel from the fixed slider — so once
     * the slider learned to raise its ceiling, "under $700" applied nothing.
     */
    public function test_price_filter_applies_above_the_old_600_sentinel() {
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'price-650', 'brand' => 'PriceBrand', 'price' => 650 ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'price-750', 'brand' => 'PriceBrand', 'price' => 750 ) ) );

        $under_700 = RTG_Database::get_filtered_tires( array( 'brand' => 'PriceBrand', 'price_max' => 700 ) );
        $this->assertSame( 1, $under_700['total'], 'a $700 ceiling must exclude the $750 tire' );
        $this->assertSame( 'price-650', $under_700['rows'][0][0] );

        $under_800 = RTG_Database::get_filtered_tires( array( 'brand' => 'PriceBrand', 'price_max' => 800 ) );
        $this->assertSame( 2, $under_800['total'], 'a ceiling above every price is a no-op' );

        $no_ceiling = RTG_Database::get_filtered_tires( array( 'brand' => 'PriceBrand', 'price_max' => 0 ) );
        $this->assertSame( 2, $no_ceiling['total'], 'zero means no price constraint' );
    }

    /**
     * A sync writing many tires flushes once at the end; the per-tire write
     * must leave the cache alone when asked to.
     */
    public function test_update_tire_can_skip_the_cache_flush() {
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'flush-001' ) ) );

        RTG_Database::get_all_tires(); // warm
        $this->assertNotFalse( get_transient( 'rtg_all_tires' ) );

        RTG_Database::update_tire( 'flush-001', array( 'price' => 123.45 ), false );
        $this->assertNotFalse( get_transient( 'rtg_all_tires' ), 'a quiet write keeps the cache warm' );

        RTG_Database::update_tire( 'flush-001', array( 'price' => 234.56 ) );
        $this->assertFalse( get_transient( 'rtg_all_tires' ), 'the default write flushes as before' );

        $this->assertSame( 234.56, (float) RTG_Database::get_tire( 'flush-001' )['price'] );
    }

    /**
     * Tire-page review list: three sorts, a star filter, and the counts that
     * feed the filter chips. Ratings written in the same second fall back to
     * insertion order (newest id first), so "recent" is still deterministic.
     */
    public function test_tire_reviews_sort_and_filter_by_star() {
        $tire_id = 'reviews-sort-001';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => $tire_id ) ) );

        // Admins auto-approve, so every row below is visible.
        $stars = array( 3, 5, 1, 3 );
        foreach ( $stars as $i => $star ) {
            $admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
            RTG_Database::set_rating( $tire_id, $admin, $star, 'Title ' . $i, 'Body ' . $i );
        }

        $recent = array_column( RTG_Database::get_tire_reviews( $tire_id ), 'review_title' );
        $this->assertSame( array( 'Title 3', 'Title 2', 'Title 1', 'Title 0' ), $recent, 'default is newest first' );

        $highest = array_map( 'intval', array_column( RTG_Database::get_tire_reviews( $tire_id, 20, 0, array( 'orderby' => 'highest' ) ), 'rating' ) );
        $this->assertSame( array( 5, 3, 3, 1 ), $highest );

        $lowest = array_map( 'intval', array_column( RTG_Database::get_tire_reviews( $tire_id, 20, 0, array( 'orderby' => 'lowest' ) ), 'rating' ) );
        $this->assertSame( array( 1, 3, 3, 5 ), $lowest );

        $threes = RTG_Database::get_tire_reviews( $tire_id, 20, 0, array( 'rating' => 3 ) );
        $this->assertCount( 2, $threes );
        $this->assertSame( array( 'Title 3', 'Title 0' ), array_column( $threes, 'review_title' ), 'a filtered list keeps the sort' );

        $this->assertSame( 4, RTG_Database::get_tire_review_count( $tire_id ) );
        $this->assertSame( 2, RTG_Database::get_tire_review_count( $tire_id, 3 ) );
        $this->assertSame( 0, RTG_Database::get_tire_review_count( $tire_id, 2 ) );

        $this->assertSame(
            array( 5 => 1, 4 => 0, 3 => 2, 2 => 0, 1 => 1 ),
            RTG_Database::get_tire_review_star_counts( $tire_id ),
            'every star is present, zeros included, five first'
        );

        // Anything unexpected falls back to the default list.
        $this->assertSame(
            array( 'orderby' => 'recent', 'rating' => 0 ),
            RTG_Database::normalize_review_args( array( 'orderby' => 'rating DESC; DROP', 'rating' => 9 ) )
        );
        $this->assertSame(
            array( 'orderby' => 'lowest', 'rating' => 2 ),
            RTG_Database::normalize_review_args( array( 'orderby' => 'LOWEST', 'rating' => '2' ) )
        );
    }

    /**
     * The review status counts feed the menu badge on every admin screen,
     * so they are cached — and every write that changes them forgets the
     * cache, so the badge never shows a stale number.
     */
    public function test_review_status_counts_are_cached_and_invalidated_by_writes() {
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'counts-001' ) ) );
        $user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

        $before = RTG_Database::get_review_status_counts();
        $this->assertNotFalse( get_transient( RTG_Database::REVIEW_COUNTS_CACHE ), 'a read fills the cache' );

        RTG_Database::set_rating( 'counts-001', $user_id, 4, 'Title', 'A review body.' );
        $this->assertFalse( get_transient( RTG_Database::REVIEW_COUNTS_CACHE ), 'a rating write forgets the cache' );

        $after = RTG_Database::get_review_status_counts();
        $this->assertSame( $before['all'] + 1, $after['all'] );

        $rating_id = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare(
            "SELECT id FROM {$GLOBALS['wpdb']->prefix}rtg_ratings WHERE tire_id = %s AND user_id = %d",
            'counts-001',
            $user_id
        ) );
        RTG_Database::update_review_status( $rating_id, 'approved' );
        $this->assertFalse( get_transient( RTG_Database::REVIEW_COUNTS_CACHE ), 'a status change forgets the cache' );
        $this->assertSame( $before['approved'] + 1, RTG_Database::get_review_status_counts()['approved'] );

        RTG_Database::delete_tire( 'counts-001' );
        $this->assertFalse( get_transient( RTG_Database::REVIEW_COUNTS_CACHE ), 'deleting a tire deletes its ratings and forgets the cache' );
        $this->assertSame( $before['all'], RTG_Database::get_review_status_counts()['all'] );
    }

    /**
     * A hand-set slug survives edits that don't change the tire's identity.
     * update_tire() used to regenerate the slug whenever brand, model or
     * size were present in the write — which every form save and CSV
     * update is — so any edit at all reverted a manual slug.
     */
    public function test_a_manual_slug_survives_edits_to_other_fields() {
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'slug-keep', 'brand' => 'Nokian', 'model' => 'Outpost' ) ) );
        RTG_Database::set_tire_slug( 'slug-keep', 'best-winter-pick' );

        RTG_Database::update_tire( 'slug-keep', array( 'brand' => 'Nokian', 'model' => 'Outpost', 'size' => '275/65R20', 'price' => 199 ) );
        $this->assertSame( 'best-winter-pick', RTG_Database::get_tire( 'slug-keep' )['slug'], 'unchanged identity keeps the manual slug' );

        RTG_Database::update_tire( 'slug-keep', array( 'brand' => 'Nokian', 'model' => 'Outpost nAT', 'size' => '275/65R20' ) );
        $this->assertSame( 'nokian-outpost-nat-275-65r20', RTG_Database::get_tire( 'slug-keep' )['slug'], 'a changed model regenerates it' );
        $this->assertSame( 'slug-keep', RTG_Database::lookup_slug_redirect( 'best-winter-pick' ), 'and the manual slug redirects' );
    }

    // --- Tire page internal linking ---

    public function test_other_sizes_are_the_same_model_in_any_other_size() {
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'os-20', 'brand' => 'Nokian', 'model' => 'One HT', 'size' => '275/65R20', 'diameter' => '20"' ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'os-22', 'brand' => 'nokian', 'model' => 'one ht', 'size' => '275/50R22', 'diameter' => '22"' ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'os-18', 'brand' => 'Nokian', 'model' => 'One HT', 'size' => '275/65R18', 'diameter' => '18"' ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'os-other', 'brand' => 'Nokian', 'model' => 'Outpost AT', 'size' => '275/50R22' ) ) );

        $ids = array_column( RTG_Database::get_other_sizes( RTG_Database::get_tire( 'os-20' ) ), 'tire_id' );

        $this->assertSame( array( 'os-18', 'os-22' ), $ids, 'same model, other sizes, smallest rim first, case-insensitive, never itself' );
    }

    public function test_similar_tires_prefer_the_category_and_widen_when_it_is_thin() {
        $size = '255/50R20';
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'sim-self', 'size' => $size, 'category' => 'All-Terrain', 'roamer_efficiency' => 2.0 ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'sim-at-1', 'size' => $size, 'category' => 'All-Terrain', 'roamer_efficiency' => 2.1 ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'sim-at-2', 'size' => $size, 'category' => 'All-Terrain', 'roamer_efficiency' => 2.4 ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'sim-at-3', 'size' => $size, 'category' => 'All-Terrain', 'roamer_efficiency' => 0 ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'sim-as-1', 'size' => $size, 'category' => 'All-Season', 'roamer_efficiency' => 2.6 ) ) );
        RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => 'sim-elsewhere', 'size' => '275/65R20', 'category' => 'All-Terrain', 'roamer_efficiency' => 3.0 ) ) );

        $ids = array_column( RTG_Database::get_similar_tires( RTG_Database::get_tire( 'sim-self' ) ), 'tire_id' );

        $this->assertSame( array( 'sim-at-2', 'sim-at-1', 'sim-at-3' ), $ids, 'same size and category, best efficiency first, unknown efficiency last, never itself' );

        // Only one other all-terrain: widen to the whole size.
        RTG_Database::delete_tire( 'sim-at-2' );
        RTG_Database::delete_tire( 'sim-at-3' );
        $ids = array_column( RTG_Database::get_similar_tires( RTG_Database::get_tire( 'sim-self' ) ), 'tire_id' );

        $this->assertSame( array( 'sim-as-1', 'sim-at-1' ), $ids );
    }

    public function test_similar_tires_respect_the_limit_and_need_a_size() {
        for ( $i = 0; $i < 8; $i++ ) {
            RTG_Database::insert_tire( $this->sample_tire( array( 'tire_id' => "lim-$i", 'size' => '275/55R21' ) ) );
        }

        $this->assertCount( 6, RTG_Database::get_similar_tires( RTG_Database::get_tire( 'lim-0' ) ) );
        $this->assertCount( 3, RTG_Database::get_similar_tires( RTG_Database::get_tire( 'lim-0' ), 3 ) );
        $this->assertSame( array(), RTG_Database::get_similar_tires( array( 'tire_id' => 'x', 'size' => '' ) ) );
        $this->assertSame( array(), RTG_Database::get_other_sizes( array( 'tire_id' => 'x', 'brand' => '', 'model' => 'M' ) ) );
    }
}
