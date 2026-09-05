<?php
/**
 * Tests for the social preview image: RTG_Meta::share_image() and the
 * theme renderer handing it to every SEO path.
 */
class Test_RTG_Meta extends WP_UnitTestCase {

    public function test_the_share_image_defaults_and_honors_the_setting() {
        update_option( 'rtg_settings', array() );
        $this->assertSame( RTG_Meta::DEFAULT_SHARE_IMAGE, RTG_Meta::share_image() );

        update_option( 'rtg_settings', array( 'share_image' => 'https://example.org/custom.jpg ' ) );
        $this->assertSame( 'https://example.org/custom.jpg', RTG_Meta::share_image() );

        update_option( 'rtg_settings', array( 'share_image' => '' ) );
        $this->assertSame( RTG_Meta::DEFAULT_SHARE_IMAGE, RTG_Meta::share_image() );
    }

    /**
     * A published page at the review slug takes over from the built-in
     * route, so the owner can hold the review page in WordPress and give
     * it SEO meta. No page: the route serves as before.
     */
    public function test_a_real_page_at_the_review_slug_wins_over_the_route() {
        $review = new RTG_Tire_Review();
        $vars   = array( 'rtg_tire_review' => '1' );

        $this->assertSame( $vars, $review->prefer_real_page( $vars ), 'no page: the route keeps the request' );

        $draft = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'tire-review', 'post_status' => 'draft', 'post_content' => '[rivian_tire_review]' ) );
        $this->assertSame( $vars, $review->prefer_real_page( $vars ), 'a draft does not count' );
        wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish' ) );

        $this->assertSame( array( 'pagename' => 'tire-review' ), $review->prefer_real_page( $vars ), 'a published page is served instead' );
        $this->assertSame( array( 'p' => 5 ), $review->prefer_real_page( array( 'p' => 5 ) ), 'other requests pass through' );
    }

    /**
     * Every tire-related page opens with Mediavine's blocklist element, so
     * the ad script serves nothing there; the filter turns it back off.
     */
    public function test_tire_pages_carry_the_mediavine_blocklist() {
        $tag = '<div id="mediavine-settings" data-blocklist-all="1"></div>';
        $this->assertSame( $tag, RTG_Theme_Render::ad_blocklist_markup() );

        RTG_Theme_Render::render( array(
            'title'   => 'Test',
            'slug'    => 'test',
            'content' => function () { echo '<p>body</p>'; },
        ) );
        $this->assertSame( $tag . '<p>body</p>', RTG_Theme_Render::filter_the_content( '' ), 'routed pages lead with it' );

        $guide = do_shortcode( '[rivian_tire_guide]' );
        $this->assertStringStartsWith( $tag, $guide, 'the guide shortcode leads with it' );

        add_filter( 'rtg_block_ads', '__return_false' );
        $this->assertSame( '', RTG_Theme_Render::ad_blocklist_markup(), 'the filter keeps ads' );
        remove_filter( 'rtg_block_ads', '__return_false' );
    }

    public function test_the_renderer_hands_its_image_to_every_seo_path_and_prints_it_itself() {
        RTG_Theme_Render::render( array(
            'title'       => 'Some Tire — Rivian Tire Guide',
            'slug'        => 'some-tire',
            'canonical'   => 'https://example.org/tires/some-tire/',
            'description' => 'A tire.',
            'image'       => 'https://example.org/og.jpg',
            'content'     => function () {},
        ) );

        $fb = RTG_Theme_Render::filter_aioseo_facebook_tags( array( 'og:image' => 'https://example.org/site.jpg', 'og:image:width' => 100 ) );
        $this->assertSame( 'https://example.org/og.jpg', $fb['og:image'] );
        $this->assertSame( 'https://example.org/og.jpg', $fb['og:image:secure_url'] );
        $this->assertArrayNotHasKey( 'og:image:width', $fb );

        $tw = RTG_Theme_Render::filter_aioseo_twitter_tags( array( 'twitter:card' => 'summary' ) );
        $this->assertSame( 'https://example.org/og.jpg', $tw['twitter:image'] );
        $this->assertSame( 'summary_large_image', $tw['twitter:card'] );

        $this->assertSame( 'https://example.org/og.jpg', RTG_Theme_Render::filter_image( 'https://example.org/site.jpg' ) );
        $this->assertSame( 'https://example.org/og.jpg', apply_filters( 'wpseo_opengraph_image', 'x' ) );
        $this->assertSame( 'https://example.org/og.jpg', apply_filters( 'rank_math/opengraph/facebook/image', 'x' ) );

        // No SEO plugin in the test suite: the renderer prints the tags.
        ob_start();
        RTG_Theme_Render::output_head();
        $head = ob_get_clean();
        $this->assertStringContainsString( '<meta property="og:image" content="https://example.org/og.jpg" />', $head );
        $this->assertStringContainsString( '<meta name="twitter:image" content="https://example.org/og.jpg" />', $head );
        $this->assertStringContainsString( '<meta name="twitter:card" content="summary_large_image" />', $head );
        $this->assertStringContainsString( '<meta property="og:url" content="https://example.org/tires/some-tire/" />', $head );
    }
}
