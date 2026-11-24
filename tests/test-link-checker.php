<?php
/**
 * Tests for RTG_Link_Checker's choice of what to check.
 *
 * A discontinued tire can't be bought, so its old link going dead is
 * expected and must not land in the broken links email.
 */
class Test_RTG_Link_Checker extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        RTG_Activator::activate();
        RTG_Database::flush_cache();
    }

    private function tire( $id, $over = array() ) {
        return array_merge( array(
            'tire_id' => $id, 'size' => '275/65R20', 'diameter' => '20"', 'brand' => 'Brand', 'model' => 'Model ' . $id,
            'category' => 'All-Season', 'price' => 300, 'mileage_warranty' => 60000, 'weight_lb' => 40, 'three_pms' => 'No',
            'tread' => '10/32', 'load_index' => '116', 'max_load_lb' => 2756, 'load_range' => 'SL', 'speed_rating' => 'T',
            'psi' => '51', 'utqg' => '620 A B', 'tags' => '', 'link' => 'https://example.com/' . $id, 'image' => '',
            'bundle_link' => '', 'sort_order' => 0,
        ), $over );
    }

    public function test_a_discontinued_tire_is_not_link_checked() {
        RTG_Database::insert_tire( $this->tire( 'lc-live' ) );
        RTG_Database::insert_tire( $this->tire( 'lc-gone', array( 'discontinued' => 1 ) ) );
        RTG_Database::insert_tire( $this->tire( 'lc-nolink', array( 'link' => '' ) ) );
        RTG_Database::flush_cache();

        $ids = wp_list_pluck( RTG_Link_Checker::get_linkable_tires(), 'tire_id' );

        $this->assertContains( 'lc-live', $ids );
        $this->assertNotContains( 'lc-gone', $ids );
        $this->assertNotContains( 'lc-nolink', $ids );
    }
}
