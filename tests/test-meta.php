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
