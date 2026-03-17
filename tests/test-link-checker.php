<?php
/**
 * Tests for RTG_Link_Checker class.
 */
class Test_RTG_Link_Checker extends WP_UnitTestCase {

    public function test_is_homepage_detection() {
        // Use reflection to test private method.
        $method = new ReflectionMethod( 'RTG_Link_Checker', 'is_homepage' );
        $method->setAccessible( true );

        // Redirect to homepage should be detected.
        $this->assertTrue( $method->invoke( null, 'https://example.com/', 'https://example.com/product/tire-123' ) );

        // Same path should not be flagged.
        $this->assertFalse( $method->invoke( null, 'https://example.com/product/tire-123', 'https://example.com/product/tire-123' ) );

        // Original URL at root should not be flagged.
        $this->assertFalse( $method->invoke( null, 'https://example.com/', 'https://example.com/' ) );

        // Short regional path should be flagged.
        $this->assertTrue( $method->invoke( null, 'https://example.com/en/', 'https://example.com/products/tire/12345' ) );
    }

    public function test_is_out_of_stock_detection() {
        $method = new ReflectionMethod( 'RTG_Link_Checker', 'is_out_of_stock' );
        $method->setAccessible( true );

        $this->assertTrue( $method->invoke( null, '<div class="product-status">Out of Stock</div>' ) );
        $this->assertTrue( $method->invoke( null, '<span>This item is currently unavailable</span>' ) );
        $this->assertTrue( $method->invoke( null, '<div>Sold Out</div>' ) );
        $this->assertTrue( $method->invoke( null, '<p>This product has been discontinued.</p>' ) );
        $this->assertFalse( $method->invoke( null, '<div class="product">Add to Cart - $299.99</div>' ) );
        $this->assertFalse( $method->invoke( null, '' ) );
    }

    public function test_amazon_short_links_excluded() {
        $result = RTG_Link_Checker::check_single_link( 'https://amzn.to/abc123' );
        $this->assertEquals( 'ok', $result['status'] );
    }

    public function test_get_broken_tire_ids_empty() {
        delete_option( 'rtg_link_check_results' );
        $result = RTG_Link_Checker::get_broken_tire_ids();
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_save_results() {
        $broken = array(
            array(
                'tire_id' => 'test-001',
                'brand'   => 'TestBrand',
                'model'   => 'TestModel',
                'url'     => 'https://example.com/dead-link',
                'status'  => 'http_error',
                'reason'  => 'HTTP 404',
                'http'    => 404,
            ),
        );

        $results = RTG_Link_Checker::save_results( 10, $broken );

        $this->assertEquals( 10, $results['total'] );
        $this->assertCount( 1, $results['broken'] );
        $this->assertEquals( 'test-001', $results['broken'][0]['tire_id'] );

        // Verify persisted.
        $stored = RTG_Link_Checker::get_results();
        $this->assertEquals( 10, $stored['total'] );
    }
}
