<?php
/**
 * A member's reviews.
 *
 * "My reviews" for the signed-in visitor on the bare page, or everything one
 * member has written when their name is followed from a tire page
 * (?reviewer=ID). Markup and copy match the tire page: breadcrumb, page
 * title, then one card per review with the tire on top. user-reviews.js
 * fills the list; the IDs below are its hooks.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$rtg_settings  = get_option( 'rtg_settings', array() );
$rtg_theme     = $rtg_settings['theme_colors'] ?? array();
$rtg_var_map   = array(
    'accent'       => '--rtg-accent',
    'accent_hover' => '--rtg-accent-hover',
    'bg_primary'   => '--rtg-bg-primary',
    'bg_card'      => '--rtg-bg-card',
    'bg_input'     => '--rtg-bg-input',
    'bg_deep'      => '--rtg-bg-deep',
    'text_primary' => '--rtg-text-primary',
    'text_light'   => '--rtg-text-light',
    'text_muted'   => '--rtg-text-muted',
    'text_heading' => '--rtg-text-heading',
    'border'       => '--rtg-border',
    'star_filled'  => '--rtg-star-filled',
    'star_user'    => '--rtg-star-user',
    'star_empty'   => '--rtg-star-empty',
);
$rtg_css_vars = '';
foreach ( $rtg_var_map as $key => $prop ) {
    if ( ! empty( $rtg_theme[ $key ] ) ) {
        $safe_color = sanitize_hex_color( $rtg_theme[ $key ] );
        if ( $safe_color ) {
            $rtg_css_vars .= $prop . ':' . $safe_color . ';';
        }
    }
}

$tire_guide_url  = RTG_Tire_Page::guide_url();
$tire_review_url = home_url( '/' . sanitize_title( $rtg_settings['tire_review_slug'] ?? 'tire-review' ) . '/' );
?>
<?php if ( $rtg_css_vars ) : ?>
<style>.ur-root{<?php echo $rtg_css_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized hex above ?>}</style>
<?php endif; ?>

<div class="rtg-embed ur-root">
  <div class="ur-page" id="rtg-user-reviews">
    <nav class="ur-breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
      <span aria-hidden="true">&rsaquo;</span>
      <a href="<?php echo esc_url( $tire_guide_url ); ?>">Tire Guide</a>
      <span aria-hidden="true">&rsaquo;</span>
      <span aria-current="page" id="rtg-user-reviews-crumb">Reviews</span>
    </nav>

    <div class="ur-head">
      <div>
        <h1 class="ur-title" id="rtg-user-reviews-title">Reviews</h1>
        <p class="ur-subtitle" id="rtg-user-reviews-count" aria-live="polite"></p>
      </div>
      <a class="ur-cta ur-cta-secondary" id="rtg-user-reviews-write" href="<?php echo esc_url( $tire_review_url ); ?>" hidden><i class="fa-solid fa-pen" aria-hidden="true"></i> Write a review</a>
    </div>

    <div class="ur-list" id="rtg-user-reviews-list" aria-busy="true">
      <?php for ( $rtg_ur_i = 0; $rtg_ur_i < 3; $rtg_ur_i++ ) : ?>
      <div class="ur-skeleton" aria-hidden="true">
        <div class="ur-skeleton-tire"><div class="ur-skeleton-line ur-skeleton-thumb"></div><div class="ur-skeleton-line" style="max-width: 240px;"></div></div>
        <div class="ur-skeleton-line" style="max-width: 120px;"></div>
        <div class="ur-skeleton-line" style="max-width: 70%;"></div>
        <div class="ur-skeleton-line"></div>
      </div>
      <?php endfor; ?>
      <span class="ur-sr-only" role="status">Loading reviews</span>
    </div>

    <nav class="ur-pagination" id="rtg-user-reviews-pagination" aria-label="Review pages"></nav>
  </div>
</div>
