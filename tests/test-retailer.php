<?php
/**
 * Tests for RTG_Retailer — the name on the "View at …" button.
 */
class Test_RTG_Retailer extends WP_UnitTestCase {

    public function test_a_synced_price_names_its_advertiser() {
        $this->assertSame( 'SimpleTire', RTG_Retailer::label( array( 'price_source' => 'SimpleTire', 'link' => 'https://www.tirerack.com/x' ) ) );
    }

    public function test_a_manual_link_names_its_host_including_subdomains() {
        $this->assertSame( 'Tire Rack', RTG_Retailer::label( array( 'price_source' => '', 'link' => 'https://www.tirerack.com/tires/x' ) ) );
        $this->assertSame( 'Discount Tire', RTG_Retailer::name_from_url( 'https://shop.discounttire.com/p/1' ) );
        $this->assertSame( 'Amazon', RTG_Retailer::name_from_url( 'https://amzn.to/abc' ) );
    }

    public function test_an_affiliate_link_is_followed_one_hop_to_its_destination() {
        $this->assertSame(
            'Tire Rack',
            RTG_Retailer::name_from_url( 'https://www.anrdoezrs.net/click-123?url=' . rawurlencode( 'https://www.tirerack.com/tires/x' ) )
        );
        $this->assertSame(
            'SimpleTire',
            RTG_Retailer::name_from_url( 'https://click.linksynergy.com/deeplink?id=1&murl=' . rawurlencode( 'https://simpletire.com/x' ) )
        );
    }

    public function test_an_unknown_host_gives_no_name() {
        $this->assertSame( '', RTG_Retailer::name_from_url( 'https://example.com/tire' ) );
        $this->assertSame( '', RTG_Retailer::name_from_url( 'https://www.anrdoezrs.net/click-123' ) );
        $this->assertSame( '', RTG_Retailer::label( array( 'price_source' => '', 'link' => '' ) ) );
        $this->assertSame( '', RTG_Retailer::name_from_url( 'https://nottirerack.com/x' ), 'a domain that merely ends in the name is not it' );
    }
}
