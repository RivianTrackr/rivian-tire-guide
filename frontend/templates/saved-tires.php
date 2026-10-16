<?php
/**
 * Saved tires.
 *
 * The signed-in member's shortlist. Same shell as the tire page and the
 * reviews page: breadcrumb, 32px title, a count line, then one card per
 * tire. saved-tires.js fills the list; the IDs below are its hooks.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$rtg_settings = get_option( 'rtg_settings', array() );
$rtg_theme    = $rtg_settings['theme_colors'] ?? array();
$rtg_var_map  = array(
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

$tire_guide_url = RTG_Tire_Page::guide_url();
?>
<?php if ( $rtg_css_vars ) : ?>
<style>.st-root{<?php echo $rtg_css_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized hex above ?>}</style>
<?php endif; ?>

<div class="rtg-embed st-root">
  <div class="st-page" id="rtg-saved-tires">
    <nav class="st-breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
      <span aria-hidden="true">&rsaquo;</span>
      <a href="<?php echo esc_url( $tire_guide_url ); ?>">Tire Guide</a>
      <span aria-hidden="true">&rsaquo;</span>
      <span aria-current="page">Saved tires</span>
    </nav>

    <div class="st-head">
      <div>
        <h1 class="st-title">Saved tires</h1>
        <p class="st-subtitle" id="rtg-saved-tires-count" aria-live="polite">Tires you save from the guide collect here.</p>
      </div>
      <div class="st-head-actions" id="rtg-saved-tires-actions" hidden>
        <a class="st-cta st-cta-secondary" id="rtg-saved-tires-compare" href="#"><i class="fa-solid fa-plus" aria-hidden="true"></i> <span>Compare all</span></a>
      </div>
    </div>

    <div class="st-list" id="rtg-saved-tires-list" aria-busy="true">
      <?php for ( $rtg_st_i = 0; $rtg_st_i < 3; $rtg_st_i++ ) : ?>
      <div class="st-skeleton" aria-hidden="true">
        <div class="st-skeleton-line st-skeleton-thumb"></div>
        <div class="st-skeleton-lines"><div class="st-skeleton-line" style="max-width: 90px;"></div><div class="st-skeleton-line" style="max-width: 260px;"></div><div class="st-skeleton-line" style="max-width: 180px;"></div></div>
      </div>
      <?php endfor; ?>
      <span class="st-sr-only" role="status">Loading saved tires</span>
    </div>
  </div>
</div>
