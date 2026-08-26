<?php
/**
 * Tests for RTG_Tire_Images.
 *
 * The image URL being imported is external data from the affiliate feed, so
 * most of what's pinned here is refusal: a server that lies about its content
 * type, a body that isn't an image, an error status, a URL that isn't HTTP.
 * The reuse rule matters just as much — a hand-placed file must always win
 * over a download.
 */
class Test_RTG_Tire_Images extends WP_UnitTestCase {

    /** 1x1 transparent GIF — the smallest real image bytes get. */
    const GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    private $dir;

    public function setUp(): void {
        parent::setUp();

        $this->dir = untrailingslashit( get_temp_dir() ) . '/rtg-images-' . uniqid();
        mkdir( $this->dir );

        add_filter( 'rtg_tire_images_dir', function () {
            return $this->dir;
        } );
    }

    public function tearDown(): void {
        foreach ( glob( $this->dir . '/*' ) ?: array() as $file ) {
            unlink( $file );
        }
        if ( is_dir( $this->dir ) ) {
            rmdir( $this->dir );
        }

        parent::tearDown();
    }

    /** Fake the remote server's answer for the next request. */
    private function remote_responds( $body, $type, $code = 200 ) {
        add_filter( 'pre_http_request', function () use ( $body, $type, $code ) {
            return array(
                'headers'  => array( 'content-type' => $type ),
                'body'     => $body,
                'response' => array( 'code' => $code, 'message' => '' ),
            );
        } );
    }

    public function test_the_filename_is_the_brand_and_model_slugified() {
        $this->assertSame( 'nitto-ridge-grappler', RTG_Tire_Images::basename_for( 'Nitto', 'Ridge Grappler' ) );
        $this->assertSame( 'michelin-defender-ltx-m-s2', RTG_Tire_Images::basename_for( ' Michelin ', 'Defender LTX M/S2' ) );
        $this->assertSame( '', RTG_Tire_Images::basename_for( '', '' ) );
    }

    // --- The refusals ---

    /**
     * A hand-placed file always wins: when one exists for this brand and
     * model, it is returned without a single network request. (The suite
     * blocks HTTP, so reaching for the network here would return '' — the
     * filename coming back proves no request was made.)
     */
    public function test_an_existing_file_is_reused_without_downloading() {
        file_put_contents( $this->dir . '/nitto-ridge-grappler.webp', 'existing' );

        $filename = RTG_Tire_Images::import_from_url( 'https://img.example/x.jpg', 'Nitto', 'Ridge Grappler' );

        $this->assertSame( 'nitto-ridge-grappler.webp', $filename );
        $this->assertSame( 'existing', file_get_contents( $this->dir . '/nitto-ridge-grappler.webp' ) );
    }

    public function test_a_server_that_is_not_serving_an_image_is_refused() {
        $this->remote_responds( '<html>Not found, but politely</html>', 'text/html' );

        $this->assertSame( '', RTG_Tire_Images::import_from_url( 'https://img.example/x.jpg', 'Nitto', 'Ridge Grappler' ) );
        $this->assertSame( array(), glob( $this->dir . '/*' ) ?: array() );
    }

    /**
     * The content type said image; the bytes disagreed. The bytes win.
     */
    public function test_a_body_that_is_not_an_image_is_refused_whatever_the_header_says() {
        $this->remote_responds( 'this is not an image at all', 'image/png' );

        $this->assertSame( '', RTG_Tire_Images::import_from_url( 'https://img.example/x.png', 'Nitto', 'Ridge Grappler' ) );
        $this->assertSame( array(), glob( $this->dir . '/*' ) ?: array() );
    }

    public function test_an_error_status_is_refused() {
        $this->remote_responds( self::GIF, 'image/gif', 404 );

        $this->assertSame( '', RTG_Tire_Images::import_from_url( 'https://img.example/x.gif', 'Nitto', 'Ridge Grappler' ) );
    }

    public function test_only_http_urls_are_fetched() {
        $this->assertSame( '', RTG_Tire_Images::import_from_url( 'ftp://img.example/x.jpg', 'Nitto', 'Ridge Grappler' ) );
        $this->assertSame( '', RTG_Tire_Images::import_from_url( '', 'Nitto', 'Ridge Grappler' ) );
    }

    /**
     * A body that reaches the size cap exactly was almost certainly cut off
     * mid-file by limit_response_size. Saving it would store a broken image
     * that looks fine in a directory listing — refuse it instead.
     */
    public function test_a_body_that_hits_the_cap_is_treated_as_truncated() {
        $this->remote_responds( str_repeat( 'x', RTG_Tire_Images::MAX_BYTES ), 'image/jpeg' );

        $this->assertSame( '', RTG_Tire_Images::import_from_url( 'https://img.example/big.jpg', 'Nitto', 'Ridge Grappler' ) );
        $this->assertStringContainsString( 'cap', RTG_Tire_Images::get_last_error() );
    }

    /**
     * The caller falls back silently, so the reason IS the product: every
     * refusal must name its failing step, and the last attempt must be
     * readable later for the admin notice.
     */
    public function test_every_refusal_names_its_reason() {
        $this->remote_responds( self::GIF, 'image/gif', 404 );
        RTG_Tire_Images::import_from_url( 'https://img.example/x.gif', 'Nitto', 'Ridge Grappler' );
        $this->assertStringContainsString( 'HTTP 404', RTG_Tire_Images::get_last_error() );

        $last = RTG_Tire_Images::get_last();
        $this->assertSame( 'https://img.example/x.gif', $last['url'] );
        $this->assertStringContainsString( 'HTTP 404', $last['error'] );

        // And success clears it.
        remove_all_filters( 'pre_http_request' );
        add_filter( 'pre_http_request', function () {
            return array(
                'headers'  => array( 'content-type' => 'image/gif' ),
                'body'     => self::GIF,
                'response' => array( 'code' => 200, 'message' => '' ),
            );
        } );
        RTG_Tire_Images::import_from_url( 'https://img.example/x.gif', 'Nitto', 'Ridge Grappler' );
        $this->assertSame( '', RTG_Tire_Images::get_last_error() );
        $this->assertSame( 'nitto-ridge-grappler.gif', RTG_Tire_Images::get_last()['filename'] );
    }

    /**
     * The Fetch-from-catalog button finds the freshest candidate image by the
     * same keys everything else matches on — rows without images are skipped,
     * the latest sighting wins (stale rows can point at reshuffled CDNs), and
     * a tire the catalog has never seen honestly yields nothing.
     */
    public function test_the_catalog_image_is_the_freshest_sighting() {
        RTG_Activator::activate();

        $key = RTG_Catalog_Sync::match_key( 'Nitto', 'Ridge Grappler', '275/65R20' );
        foreach ( array(
            array( 'external_id' => 'img-1', 'image' => '', 'last_seen_at' => '2026-08-25 00:00:00' ),
            array( 'external_id' => 'img-2', 'image' => 'https://img.example/old.jpg', 'last_seen_at' => '2026-08-20 00:00:00' ),
            array( 'external_id' => 'img-3', 'image' => 'https://img.example/fresh.jpg', 'last_seen_at' => '2026-08-25 00:00:00' ),
        ) as $row ) {
            RTG_Candidates::upsert( array_merge( array(
                'source'          => 'cj',
                'advertiser_id'   => '1463221',
                'advertiser_name' => 'The Tire Rack',
                'brand'           => 'Nitto',
                'model'           => 'Ridge Grappler',
                'size'            => '275/65R20',
                'match_key'       => $key,
                'qualifies'       => 1,
            ), $row ) );
        }
        // upsert stamps last_seen_at itself, so pin the fixtures' timestamps.
        global $wpdb;
        foreach ( array( 'img-2' => '2026-08-20 00:00:00', 'img-3' => '2026-08-25 00:00:00' ) as $ext => $seen ) {
            $wpdb->update( $wpdb->prefix . 'rtg_tire_candidates', array( 'last_seen_at' => $seen ), array( 'external_id' => $ext ) );
        }

        $tire = array( 'brand' => 'Nitto', 'model' => 'Ridge Grappler', 'size' => '275/65R20' );
        $this->assertSame( 'https://img.example/fresh.jpg', RTG_Tire_Images::catalog_image_for( $tire ) );

        $this->assertSame( '', RTG_Tire_Images::catalog_image_for(
            array( 'brand' => 'Michelin', 'model' => 'Defender', 'size' => '275/65R20' )
        ) );
    }

    // --- The write ---

    public function test_a_product_image_is_downloaded_and_named_by_convention() {
        $this->remote_responds( self::GIF, 'image/gif' );

        $filename = RTG_Tire_Images::import_from_url( 'https://img.example/tr-99.gif', 'Nitto', 'Ridge Grappler' );

        $this->assertSame( 'nitto-ridge-grappler.gif', $filename );
        $this->assertSame( self::GIF, file_get_contents( $this->dir . '/' . $filename ) );
    }

    /**
     * The extension comes from the content type, not the URL — and a charset
     * suffix on the header must not confuse it.
     */
    public function test_the_extension_follows_the_content_type() {
        $this->remote_responds( self::GIF, 'image/jpeg; charset=binary' );

        $filename = RTG_Tire_Images::import_from_url( 'https://img.example/no-extension?id=99', 'Nitto', 'Ridge Grappler' );

        $this->assertSame( 'nitto-ridge-grappler.jpg', $filename );
    }
}
