<?php
/**
 * Individual tire page — CONTENT partial, rendered inside the active theme
 * (via RTG_Theme_Render, injected into the_content). No <html>/<head>/<body>
 * and no global CSS resets: everything is scoped under .rtg-tp so it doesn't
 * fight the theme. SEO tags (title/meta/canonical/JSON-LD) are handled by
 * RTG_Tire_Page + the SEO plugin, not here.
 *
 * Expects $GLOBALS['rtg_tire_page_tire'].
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tire = $GLOBALS['rtg_tire_page_tire'] ?? null;
if ( ! is_array( $tire ) ) {
    return;
}

$tire_id  = $tire['tire_id'];
$brand    = $tire['brand'] ?? '';
$model    = $tire['model'] ?? '';
$size     = $tire['size'] ?? '';
$diameter = $tire['diameter'] ?? '';
$category = $tire['category'] ?? '';
$image    = ! empty( $tire['image'] ) ? esc_url( $tire['image'] ) : '';
$link     = ! empty( $tire['link'] ) ? esc_url( $tire['link'] ) : '';

$heading = trim( "$brand $model" ) ?: 'Tire';
$heading .= $size ? " ($size)" : '';

$ratings_map = RTG_Database::get_tire_ratings( array( $tire_id ) );
$rating      = $ratings_map[ $tire_id ] ?? array( 'average' => 0, 'count' => 0 );
$avg         = (float) ( $rating['average'] ?? 0 );
$rating_cnt  = (int) ( $rating['count'] ?? 0 );
$reviews     = RTG_Database::get_tire_reviews( $tire_id, 10 );

$guide_url   = RTG_Tire_Page::guide_url();
$rtg_set     = get_option( 'rtg_settings', array() );
$review_slug = sanitize_title( $rtg_set['tire_review_slug'] ?? 'tire-review' );
$review_url  = add_query_arg( 'tire', rawurlencode( $tire_id ), home_url( '/' . $review_slug . '/' ) );

// Admin theme-color overrides (same mechanism as the compare/review templates).
$rtg_tp_theme   = $rtg_set['theme_colors'] ?? array();
$rtg_tp_var_map = array(
    'accent'       => '--rtg-tp-accent',
    'accent_hover' => '--rtg-tp-accent-hover',
    'bg_card'      => '--rtg-tp-card',
    'bg_deep'      => '--rtg-tp-deep',
    'text_primary' => '--rtg-tp-text',
    'text_heading' => '--rtg-tp-heading',
    'text_muted'   => '--rtg-tp-muted',
    'border'       => '--rtg-tp-border',
    'star_empty'   => '--rtg-tp-star-empty',
);
$rtg_tp_css_vars = '';
foreach ( $rtg_tp_var_map as $rtg_tp_key => $rtg_tp_prop ) {
    if ( ! empty( $rtg_tp_theme[ $rtg_tp_key ] ) ) {
        $rtg_tp_color = sanitize_hex_color( $rtg_tp_theme[ $rtg_tp_key ] );
        if ( $rtg_tp_color ) {
            $rtg_tp_css_vars .= $rtg_tp_prop . ':' . $rtg_tp_color . ';';
        }
    }
}

$breadcrumb_items = array(
    array( 'name' => 'Home', 'url' => home_url( '/' ) ),
    array( 'name' => 'Tire Guide', 'url' => $guide_url ),
);
if ( $category ) {
    $breadcrumb_items[] = array( 'name' => $category, 'url' => add_query_arg( 'category', rawurlencode( $category ), $guide_url ) );
}
$breadcrumb_items[] = array( 'name' => $heading, 'url' => RTG_Tire_Page::tire_url( $tire['slug'] ?? $tire_id ) );

if ( ! function_exists( 'rtg_tire_page_stars' ) ) {
    function rtg_tire_page_stars( $avg ) {
        $out = '';
        for ( $i = 1; $i <= 5; $i++ ) {
            $fill = $avg >= $i ? 'full' : ( $avg >= $i - 0.5 ? 'half' : 'empty' );
            $out .= '<span class="rtg-tp-star rtg-tp-star-' . $fill . '">&#9733;</span>';
        }
        return $out;
    }
}

// Full spec sheet (efficiency score intentionally omitted — discontinued).
$specs = array(
    'Size'             => $size . ( $diameter ? " ($diameter)" : '' ),
    'Category'         => $category,
    'Average Price'    => ( $tire['price'] > 0 ) ? '$' . number_format( (float) $tire['price'], 2 ) : '',
    'Mileage Warranty' => ( $tire['mileage_warranty'] > 0 ) ? number_format( (int) $tire['mileage_warranty'] ) . ' miles' : '',
    'Weight'           => ( $tire['weight_lb'] > 0 ) ? $tire['weight_lb'] . ' lb' : '',
    '3PMS Rated'       => $tire['three_pms'] ?? '',
    'Load Index'       => $tire['load_index'] ?? '',
    'Load Range'       => $tire['load_range'] ?? '',
    'Max Load'         => ( $tire['max_load_lb'] > 0 ) ? number_format( (int) $tire['max_load_lb'] ) . ' lb' : '',
    'Speed Rating'     => $tire['speed_rating'] ?? '',
    'Tread Depth'      => $tire['tread'] ?? '',
    'Max PSI'          => $tire['psi'] ?? '',
    'UTQG'             => ( strtolower( trim( $tire['utqg'] ?? '' ) ) === 'none' ) ? '' : ( $tire['utqg'] ?? '' ),
);

$roamer_eff = (float) ( $tire['roamer_efficiency'] ?? 0 );
?>
<style>
  .rtg-tp {
    --rtg-tp-accent: #fba919;
    --rtg-tp-accent-hover: #ffbe4a;
    --rtg-tp-card: #16191e;
    --rtg-tp-deep: #121418;
    --rtg-tp-text: #ece9e4;
    --rtg-tp-heading: #f6f4f0;
    --rtg-tp-muted: #a19e97;
    --rtg-tp-border: #3a3e45;
    --rtg-tp-star-empty: #2c2f34;
    <?php if ( $rtg_tp_css_vars ) echo $rtg_tp_css_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized hex above ?>
    max-width: 900px;
    margin: 0 auto;
    color: var(--rtg-tp-text);
    font-size: 15px;
    line-height: 1.6;
  }
  .rtg-tp *, .rtg-tp *::before, .rtg-tp *::after { box-sizing: border-box; }
  .rtg-tp a { color: var(--rtg-tp-accent); text-decoration: none; }
  .rtg-tp a:hover { color: var(--rtg-tp-accent-hover); }
  .rtg-tp .rtg-tp-breadcrumb { font-size: 13px; color: var(--rtg-tp-muted); margin: 0 0 20px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
  .rtg-tp .rtg-tp-breadcrumb a { color: var(--rtg-tp-muted); }
  .rtg-tp .rtg-tp-breadcrumb a:hover { color: var(--rtg-tp-accent); }
  .rtg-tp .rtg-tp-breadcrumb span[aria-current] { color: var(--rtg-tp-text); }
  .rtg-tp .rtg-tp-hero { display: flex; gap: 28px; flex-wrap: wrap; margin: 0 0 32px; }
  .rtg-tp .rtg-tp-img { flex: 0 0 320px; max-width: 100%; background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border); border-radius: 12px; overflow: hidden; aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; }
  .rtg-tp .rtg-tp-img img { width: 100%; height: 100%; object-fit: cover; margin: 0; }
  .rtg-tp .rtg-tp-info { flex: 1 1 320px; min-width: 260px; }
  .rtg-tp .rtg-tp-brand { font-size: 14px; font-weight: 600; color: var(--rtg-tp-muted); text-transform: uppercase; letter-spacing: .5px; margin: 0; }
  .rtg-tp h1.rtg-tp-title { font-size: 28px; font-weight: 700; letter-spacing: -0.5px; color: var(--rtg-tp-heading); margin: 4px 0 10px; padding: 0; }
  .rtg-tp .rtg-tp-rating { display: flex; align-items: center; gap: 8px; margin: 0 0 20px; }
  .rtg-tp .rtg-tp-star { font-size: 18px; color: var(--rtg-tp-star-empty); }
  .rtg-tp .rtg-tp-star-full { color: var(--rtg-tp-accent); }
  .rtg-tp .rtg-tp-star-half { background: linear-gradient(90deg, var(--rtg-tp-accent) 50%, var(--rtg-tp-star-empty) 50%); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .rtg-tp .rtg-tp-rating-meta { font-size: 13px; color: var(--rtg-tp-muted); }
  .rtg-tp .rtg-tp-cta { display: inline-flex; align-items: center; justify-content: center; background: var(--rtg-tp-accent); color: #15130e; font-weight: 600; padding: 12px 22px; border-radius: 8px; font-size: 15px; }
  .rtg-tp .rtg-tp-cta:hover { background: var(--rtg-tp-accent-hover); color: #15130e; }
  .rtg-tp .rtg-tp-roamer { margin: 12px 0 20px; padding: 12px 16px; border-radius: 10px; background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.3); font-size: 14px; }
  .rtg-tp .rtg-tp-roamer strong { color: #60a5fa; }
  .rtg-tp h2.rtg-tp-section { font-size: 20px; font-weight: 700; color: var(--rtg-tp-heading); margin: 36px 0 14px; padding: 0; }
  .rtg-tp .rtg-tp-specs { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1px; background: var(--rtg-tp-border); border: 1px solid var(--rtg-tp-border); border-radius: 12px; overflow: hidden; }
  .rtg-tp .rtg-tp-spec { background: var(--rtg-tp-card); padding: 12px 16px; display: flex; justify-content: space-between; gap: 12px; }
  .rtg-tp .rtg-tp-spec-label { color: var(--rtg-tp-muted); font-size: 14px; }
  .rtg-tp .rtg-tp-spec-value { color: var(--rtg-tp-text); font-size: 14px; font-weight: 600; text-align: right; }
  .rtg-tp .rtg-tp-review { background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border); border-radius: 10px; padding: 16px; margin: 0 0 12px; }
  .rtg-tp .rtg-tp-review-head { display: flex; justify-content: space-between; gap: 12px; margin: 0 0 6px; }
  .rtg-tp .rtg-tp-review-author { font-weight: 600; color: var(--rtg-tp-heading); font-size: 14px; }
  .rtg-tp .rtg-tp-review-title { font-weight: 600; font-size: 14px; margin: 4px 0; }
  .rtg-tp .rtg-tp-review-body { font-size: 14px; color: var(--rtg-tp-text); margin: 0; }
  .rtg-tp .rtg-tp-empty { color: var(--rtg-tp-muted); font-size: 14px; }
  @media (max-width: 600px) { .rtg-tp h1.rtg-tp-title { font-size: 23px; } }
</style>

<div class="rtg-tp">
  <nav class="rtg-tp-breadcrumb" aria-label="Breadcrumb">
    <?php
    $last = count( $breadcrumb_items ) - 1;
    foreach ( $breadcrumb_items as $i => $crumb ) {
        if ( $i === $last ) {
            echo '<span aria-current="page">' . esc_html( $crumb['name'] ) . '</span>';
        } else {
            echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a>';
            echo '<span aria-hidden="true">&rsaquo;</span>';
        }
    }
    ?>
  </nav>

  <div class="rtg-tp-hero">
    <?php if ( $image ) : ?>
    <div class="rtg-tp-img">
      <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $heading ); ?>" />
    </div>
    <?php endif; ?>

    <div class="rtg-tp-info">
      <?php if ( $brand ) : ?><div class="rtg-tp-brand"><?php echo esc_html( $brand ); ?></div><?php endif; ?>
      <h1 class="rtg-tp-title"><?php echo esc_html( $heading ); ?></h1>

      <div class="rtg-tp-rating">
        <span aria-hidden="true"><?php echo rtg_tire_page_stars( $avg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup ?></span>
        <span class="rtg-tp-rating-meta">
          <?php
          echo $rating_cnt > 0
              ? esc_html( number_format( $avg, 1 ) . ' (' . $rating_cnt . ' rating' . ( 1 === $rating_cnt ? '' : 's' ) . ')' )
              : 'No ratings yet';
          ?>
        </span>
      </div>

      <?php if ( $roamer_eff > 0 ) : ?>
      <div class="rtg-tp-roamer">
        <strong><?php echo esc_html( number_format( $roamer_eff, 2 ) ); ?> mi/kWh</strong> real-world efficiency
        <?php
        $r_mi  = round( ( (float) ( $tire['roamer_total_km'] ?? 0 ) ) * 0.621371 );
        $r_veh = (int) ( $tire['roamer_vehicle_count'] ?? 0 );
        if ( $r_mi > 0 || $r_veh > 0 ) {
            echo ' &middot; <span style="color:var(--rtg-tp-muted)">' . esc_html( number_format( $r_mi ) . ' mi tracked across ' . $r_veh . ' vehicle' . ( 1 === $r_veh ? '' : 's' ) ) . '</span>';
        }
        ?>
      </div>
      <?php endif; ?>

      <?php if ( $link ) : ?>
      <a class="rtg-tp-cta" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="nofollow sponsored noopener">View Tire &rarr;</a>
      <?php endif; ?>
    </div>
  </div>

  <h2 class="rtg-tp-section">Specifications</h2>
  <div class="rtg-tp-specs">
    <?php foreach ( $specs as $label => $value ) : ?>
      <?php if ( '' !== trim( (string) $value ) ) : ?>
      <div class="rtg-tp-spec">
        <span class="rtg-tp-spec-label"><?php echo esc_html( $label ); ?></span>
        <span class="rtg-tp-spec-value"><?php echo esc_html( $value ); ?></span>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <h2 class="rtg-tp-section">Owner Reviews</h2>
  <?php if ( ! empty( $reviews ) ) : ?>
    <?php foreach ( $reviews as $review ) : ?>
    <div class="rtg-tp-review">
      <div class="rtg-tp-review-head">
        <span class="rtg-tp-review-author"><?php echo esc_html( $review['display_name'] ); ?></span>
        <span aria-hidden="true"><?php echo rtg_tire_page_stars( (float) $review['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup ?></span>
      </div>
      <?php if ( ! empty( $review['review_title'] ) ) : ?>
      <div class="rtg-tp-review-title"><?php echo esc_html( $review['review_title'] ); ?></div>
      <?php endif; ?>
      <?php if ( ! empty( $review['review_text'] ) ) : ?>
      <div class="rtg-tp-review-body"><?php echo esc_html( $review['review_text'] ); ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php else : ?>
    <p class="rtg-tp-empty">No reviews yet. <a href="<?php echo esc_url( $review_url ); ?>">Be the first to review this tire.</a></p>
  <?php endif; ?>

  <p style="margin-top:20px;"><a href="<?php echo esc_url( $review_url ); ?>">Write a review &rarr;</a></p>
</div>
<?php
// End tire page content partial.
