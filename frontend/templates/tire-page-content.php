<?php
/**
 * Individual tire page — CONTENT partial, rendered inside the active theme
 * (via RTG_Theme_Render, injected into the_content). No <html>/<head>/<body>
 * and no global CSS resets: everything is scoped under .rtg-tp so it doesn't
 * fight the theme. SEO tags (title/meta/canonical/JSON-LD) are handled by
 * RTG_Tire_Page + the SEO plugin, not here.
 *
 * Layout: product-detail hero (image + info column with chips, key stats,
 * CTAs), grouped specification cards, and an owner-reviews section with a
 * proper empty state — matching the main guide's ink & brass design system.
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

// --- Chips: category, 3PMS, OEM. ---
$chips = array();
if ( $category ) {
    $chips[] = $category;
}
if ( strtolower( trim( $tire['three_pms'] ?? '' ) ) === 'yes' ) {
    $chips[] = '3PMS Rated';
}
if ( false !== stripos( (string) ( $tire['tags'] ?? '' ), 'oem' ) ) {
    $chips[] = 'OEM';
}

// --- Hero key stats (skip empties). ---
$roamer_eff = (float) ( $tire['roamer_efficiency'] ?? 0 );
$hero_stats = array();
if ( $roamer_eff > 0 ) {
    $r_mi  = round( ( (float) ( $tire['roamer_total_km'] ?? 0 ) ) * 0.621371 );
    $r_veh = (int) ( $tire['roamer_vehicle_count'] ?? 0 );
    $meta  = array();
    if ( $r_mi > 0 ) {
        $meta[] = number_format( $r_mi ) . ' mi tracked';
    }
    if ( $r_veh > 0 ) {
        $meta[] = $r_veh . ' vehicle' . ( 1 === $r_veh ? '' : 's' );
    }
    $hero_stats[] = array(
        'label' => 'Real-World Efficiency',
        'value' => number_format( $roamer_eff, 2 ) . ' mi/kWh',
        'meta'  => implode( ' · ', $meta ),
        'class' => 'rtg-tp-stat-roamer',
    );
}
if ( $tire['price'] > 0 ) {
    $hero_stats[] = array(
        'label' => 'Average Price',
        'value' => '$' . number_format( (float) $tire['price'], 0 ),
        'meta'  => 'per tire',
        'class' => '',
    );
}
if ( $tire['mileage_warranty'] > 0 ) {
    $hero_stats[] = array(
        'label' => 'Mileage Warranty',
        'value' => number_format( (int) $tire['mileage_warranty'] ),
        'meta'  => 'miles',
        'class' => '',
    );
}
if ( $tire['weight_lb'] > 0 ) {
    $hero_stats[] = array(
        'label' => 'Weight',
        'value' => $tire['weight_lb'] . ' lb',
        'meta'  => 'per tire',
        'class' => '',
    );
}

// --- Grouped specifications (skip empty rows). Price lives in the hero. ---
$spec_groups = array(
    array(
        'icon'  => 'fa-ruler-combined',
        'title' => 'Size & Fitment',
        'rows'  => array(
            'Size'         => $size . ( $diameter ? " ($diameter)" : '' ),
            'Load Index'   => $tire['load_index'] ?? '',
            'Load Range'   => $tire['load_range'] ?? '',
            'Max Load'     => ( $tire['max_load_lb'] > 0 ) ? number_format( (int) $tire['max_load_lb'] ) . ' lb' : '',
            'Speed Rating' => $tire['speed_rating'] ?? '',
            'Max PSI'      => $tire['psi'] ?? '',
        ),
    ),
    array(
        'icon'  => 'fa-gauge-high',
        'title' => 'Construction & Performance',
        'rows'  => array(
            'Category'         => $category,
            'Weight'           => ( $tire['weight_lb'] > 0 ) ? $tire['weight_lb'] . ' lb' : '',
            'Tread Depth'      => $tire['tread'] ?? '',
            'UTQG'             => ( strtolower( trim( $tire['utqg'] ?? '' ) ) === 'none' ) ? '' : ( $tire['utqg'] ?? '' ),
            '3PMS Rated'       => $tire['three_pms'] ?? '',
            'Mileage Warranty' => ( $tire['mileage_warranty'] > 0 ) ? number_format( (int) $tire['mileage_warranty'] ) . ' miles' : '',
        ),
    ),
);
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
    --rtg-tp-divider: color-mix(in srgb, var(--rtg-tp-border) 55%, transparent);
    <?php if ( $rtg_tp_css_vars ) echo $rtg_tp_css_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized hex above ?>
    /* Full content width — matches the main guide. */
    width: 100%;
    color: var(--rtg-tp-text);
    font-size: 15px;
    line-height: 1.6;
  }
  .rtg-tp *, .rtg-tp *::before, .rtg-tp *::after { box-sizing: border-box; }
  .rtg-tp a { color: var(--rtg-tp-accent); text-decoration: none; }
  .rtg-tp a:hover { color: var(--rtg-tp-accent-hover); }

  /* --- Breadcrumb --- */
  .rtg-tp .rtg-tp-breadcrumb { font-size: 13px; color: var(--rtg-tp-muted); margin: 0 0 24px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
  .rtg-tp .rtg-tp-breadcrumb a { color: var(--rtg-tp-muted); }
  .rtg-tp .rtg-tp-breadcrumb a:hover { color: var(--rtg-tp-accent); }
  .rtg-tp .rtg-tp-breadcrumb span[aria-current] { color: var(--rtg-tp-text); }

  /* --- Hero --- */
  .rtg-tp .rtg-tp-hero { display: flex; gap: 32px; flex-wrap: wrap; margin: 0 0 28px; }
  .rtg-tp .rtg-tp-img {
    flex: 0 0 380px; max-width: 100%;
    background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border);
    border-radius: 12px; overflow: hidden; aspect-ratio: 1 / 1;
    display: flex; align-items: center; justify-content: center;
    align-self: flex-start;
    transition: border-color 0.15s ease;
  }
  .rtg-tp .rtg-tp-img:hover { border-color: color-mix(in srgb, var(--rtg-tp-accent) 35%, var(--rtg-tp-border)); }
  .rtg-tp .rtg-tp-img img { width: 100%; height: 100%; object-fit: cover; margin: 0; display: block; }
  .rtg-tp .rtg-tp-info { flex: 1 1 380px; min-width: 280px; }
  .rtg-tp .rtg-tp-brand { font-size: 13px; font-weight: 700; color: var(--rtg-tp-accent); text-transform: uppercase; letter-spacing: .8px; margin: 0 0 2px; }
  .rtg-tp h1.rtg-tp-title { font-size: 32px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2; color: var(--rtg-tp-heading); margin: 0 0 10px; padding: 0; }

  .rtg-tp .rtg-tp-rating { display: flex; align-items: center; gap: 8px; margin: 0 0 14px; flex-wrap: wrap; }
  .rtg-tp .rtg-tp-star { font-size: 17px; color: var(--rtg-tp-star-empty); }
  .rtg-tp .rtg-tp-star-full { color: var(--rtg-tp-accent); filter: drop-shadow(0 1px 3px color-mix(in srgb, var(--rtg-tp-accent) 35%, transparent)); }
  .rtg-tp .rtg-tp-star-half { background: linear-gradient(90deg, var(--rtg-tp-accent) 50%, var(--rtg-tp-star-empty) 50%); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .rtg-tp .rtg-tp-rating-meta { font-size: 13px; color: var(--rtg-tp-muted); }
  .rtg-tp .rtg-tp-rating-meta a { color: var(--rtg-tp-muted); text-decoration: underline; text-underline-offset: 2px; }
  .rtg-tp .rtg-tp-rating-meta a:hover { color: var(--rtg-tp-accent); }

  /* --- Chips --- */
  .rtg-tp .rtg-tp-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 20px; }
  .rtg-tp .rtg-tp-chip {
    display: inline-flex; align-items: center;
    font-size: 12px; font-weight: 600; line-height: 1;
    padding: 7px 12px; border-radius: 20px;
    color: var(--rtg-tp-text);
    background: color-mix(in srgb, var(--rtg-tp-accent) 12%, var(--rtg-tp-deep));
    border: 1px solid color-mix(in srgb, var(--rtg-tp-accent) 25%, transparent);
  }

  /* --- Hero key stats --- */
  .rtg-tp .rtg-tp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 0 0 22px; }
  .rtg-tp .rtg-tp-stat { background: var(--rtg-tp-deep); border: 1px solid var(--rtg-tp-divider); border-radius: 10px; padding: 12px 14px; }
  .rtg-tp .rtg-tp-stat-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--rtg-tp-muted); margin: 0 0 4px; }
  .rtg-tp .rtg-tp-stat-value { font-size: 20px; font-weight: 600; line-height: 1.2; color: var(--rtg-tp-heading); font-variant-numeric: tabular-nums; }
  .rtg-tp .rtg-tp-stat-meta { font-size: 12px; color: var(--rtg-tp-muted); margin-top: 2px; }
  .rtg-tp .rtg-tp-stat-roamer .rtg-tp-stat-value { color: #60a5fa; }

  /* --- CTAs --- */
  .rtg-tp .rtg-tp-ctas { display: flex; flex-wrap: wrap; gap: 10px; }
  .rtg-tp .rtg-tp-cta {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    min-height: 44px; padding: 10px 22px; border-radius: 8px;
    font-size: 15px; font-weight: 600;
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
  }
  .rtg-tp .rtg-tp-cta:active { transform: scale(0.97); }
  .rtg-tp .rtg-tp-cta-primary { background: var(--rtg-tp-accent); color: #15130e; }
  .rtg-tp .rtg-tp-cta-primary:hover { background: var(--rtg-tp-accent-hover); color: #15130e; }
  .rtg-tp .rtg-tp-cta-secondary { background: transparent; color: var(--rtg-tp-text); border: 1px solid var(--rtg-tp-border); }
  .rtg-tp .rtg-tp-cta-secondary:hover { color: var(--rtg-tp-accent); border-color: color-mix(in srgb, var(--rtg-tp-accent) 45%, var(--rtg-tp-border)); }

  /* --- Section titles --- */
  .rtg-tp h2.rtg-tp-section { font-size: 20px; font-weight: 700; color: var(--rtg-tp-heading); margin: 32px 0 14px; padding: 0; }

  /* --- Spec cards --- */
  .rtg-tp .rtg-tp-spec-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
  .rtg-tp .rtg-tp-spec-card { background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border); border-radius: 12px; overflow: hidden; }
  .rtg-tp .rtg-tp-spec-head {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 18px; border-bottom: 1px solid var(--rtg-tp-border);
    font-size: 14px; font-weight: 700; color: var(--rtg-tp-heading);
  }
  .rtg-tp .rtg-tp-spec-head i { color: var(--rtg-tp-accent); font-size: 14px; }
  .rtg-tp .rtg-tp-spec-row { display: flex; justify-content: space-between; gap: 16px; padding: 10px 18px; border-bottom: 1px solid var(--rtg-tp-divider); }
  .rtg-tp .rtg-tp-spec-row:last-child { border-bottom: none; }
  .rtg-tp .rtg-tp-spec-label { color: var(--rtg-tp-muted); font-size: 14px; }
  /* Monospace values — matches the main guide's tire-card spec styling. */
  .rtg-tp .rtg-tp-spec-value { color: var(--rtg-tp-text); font-size: 14px; font-weight: 500; text-align: right; font-family: monospace; }

  /* --- Reviews --- */
  .rtg-tp .rtg-tp-reviews-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 32px 0 14px; }
  .rtg-tp .rtg-tp-reviews-head h2 { margin: 0; }
  .rtg-tp .rtg-tp-review { background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border); border-radius: 12px; padding: 16px 18px; margin: 0 0 12px; }
  .rtg-tp .rtg-tp-review-head { display: flex; justify-content: space-between; gap: 12px; margin: 0 0 6px; }
  .rtg-tp .rtg-tp-review-author { font-weight: 600; color: var(--rtg-tp-heading); font-size: 14px; }
  .rtg-tp .rtg-tp-review-title { font-weight: 600; font-size: 14px; margin: 4px 0; }
  .rtg-tp .rtg-tp-review-body { font-size: 14px; color: var(--rtg-tp-text); margin: 0; }
  .rtg-tp .rtg-tp-reviews-empty {
    text-align: center; padding: 48px 20px;
    background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border); border-radius: 12px;
  }
  .rtg-tp .rtg-tp-reviews-empty i { font-size: 40px; color: var(--rtg-tp-muted); opacity: .5; margin-bottom: 14px; display: block; }
  .rtg-tp .rtg-tp-reviews-empty-title { font-size: 20px; font-weight: 700; color: var(--rtg-tp-heading); margin: 0 0 6px; }
  .rtg-tp .rtg-tp-reviews-empty-text { font-size: 14px; color: var(--rtg-tp-muted); max-width: 460px; margin: 0 auto 20px; line-height: 1.6; }

  /* The theme's bare page template has no horizontal gutter below its content
     max-width — provide our own side padding so content never sits flush
     against the screen edge (matches the guide page's gutters). */
  @media (max-width: 1024px) {
    .rtg-tp { padding-left: 16px; padding-right: 16px; }
  }
  @media (max-width: 700px) {
    .rtg-tp .rtg-tp-hero { gap: 20px; }
    .rtg-tp .rtg-tp-img { flex-basis: 100%; }
    .rtg-tp h1.rtg-tp-title { font-size: 24px; }
    .rtg-tp .rtg-tp-stats { grid-template-columns: repeat(2, 1fr); }
    .rtg-tp .rtg-tp-ctas .rtg-tp-cta { flex: 1 1 100%; }
  }
  @media (prefers-reduced-motion: reduce) {
    .rtg-tp * { transition: none !important; }
  }
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
          if ( $rating_cnt > 0 ) {
              echo esc_html( number_format( $avg, 1 ) ) . ' &middot; <a href="#rtg-tp-reviews">' . esc_html( $rating_cnt . ' rating' . ( 1 === $rating_cnt ? '' : 's' ) ) . '</a>';
          } else {
              echo 'No ratings yet &middot; <a href="' . esc_url( $review_url ) . '">be the first</a>';
          }
          ?>
        </span>
      </div>

      <?php if ( ! empty( $chips ) ) : ?>
      <div class="rtg-tp-chips">
        <?php foreach ( $chips as $chip ) : ?>
        <span class="rtg-tp-chip"><?php echo esc_html( $chip ); ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ( ! empty( $hero_stats ) ) : ?>
      <div class="rtg-tp-stats">
        <?php foreach ( $hero_stats as $stat ) : ?>
        <div class="rtg-tp-stat <?php echo esc_attr( $stat['class'] ); ?>">
          <div class="rtg-tp-stat-label"><?php echo esc_html( $stat['label'] ); ?></div>
          <div class="rtg-tp-stat-value"><?php echo esc_html( $stat['value'] ); ?></div>
          <?php if ( $stat['meta'] ) : ?><div class="rtg-tp-stat-meta"><?php echo esc_html( $stat['meta'] ); ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="rtg-tp-ctas">
        <?php if ( $link ) : ?>
        <a class="rtg-tp-cta rtg-tp-cta-primary" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="nofollow sponsored noopener">View Tire&nbsp;<i class="fa-solid fa-up-right-from-square" aria-hidden="true" style="font-size:13px"></i></a>
        <?php endif; ?>
        <a class="rtg-tp-cta rtg-tp-cta-secondary" href="<?php echo esc_url( $review_url ); ?>">Write a Review</a>
      </div>
    </div>
  </div>

  <h2 class="rtg-tp-section">Specifications</h2>
  <div class="rtg-tp-spec-grid">
    <?php foreach ( $spec_groups as $group ) : ?>
      <?php
      $group_rows = array_filter( $group['rows'], function ( $v ) {
          return '' !== trim( (string) $v );
      } );
      if ( empty( $group_rows ) ) {
          continue;
      }
      ?>
    <div class="rtg-tp-spec-card">
      <div class="rtg-tp-spec-head">
        <i class="fa-solid <?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></i>
        <?php echo esc_html( $group['title'] ); ?>
      </div>
      <?php foreach ( $group_rows as $label => $value ) : ?>
      <div class="rtg-tp-spec-row">
        <span class="rtg-tp-spec-label"><?php echo esc_html( $label ); ?></span>
        <span class="rtg-tp-spec-value"><?php echo esc_html( $value ); ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="rtg-tp-reviews-head" id="rtg-tp-reviews">
    <h2 class="rtg-tp-section">Owner Reviews<?php echo $rating_cnt > 0 ? ' (' . (int) $rating_cnt . ')' : ''; ?></h2>
    <?php if ( ! empty( $reviews ) ) : ?>
    <a class="rtg-tp-cta rtg-tp-cta-secondary" href="<?php echo esc_url( $review_url ); ?>">Write a Review</a>
    <?php endif; ?>
  </div>

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
    <div class="rtg-tp-reviews-empty">
      <i class="fa-solid fa-comment-dots" aria-hidden="true"></i>
      <div class="rtg-tp-reviews-empty-title">No reviews yet</div>
      <p class="rtg-tp-reviews-empty-text">Be the first to share how this tire performs on your Rivian — range impact, road noise, traction, and wear all help fellow owners choose.</p>
      <a class="rtg-tp-cta rtg-tp-cta-primary" href="<?php echo esc_url( $review_url ); ?>">Write a Review</a>
    </div>
  <?php endif; ?>
</div>
<?php
// End tire page content partial.
