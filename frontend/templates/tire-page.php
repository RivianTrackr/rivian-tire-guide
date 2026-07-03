<?php
/**
 * Standalone, server-rendered individual tire page.
 *
 * Rendered by RTG_Tire_Page for /{slug}/{tire-slug}/. All tire content is in
 * the initial HTML (no JS required) for crawlability. The tire row is passed
 * via $GLOBALS['rtg_tire_page_tire'].
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tire = $GLOBALS['rtg_tire_page_tire'] ?? null;
if ( ! is_array( $tire ) ) {
    return;
}

// --- Theme colour variables (match the other standalone templates). ---
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

// --- Tire fields. ---
$tire_id  = $tire['tire_id'];
$brand    = $tire['brand'] ?? '';
$model    = $tire['model'] ?? '';
$size     = $tire['size'] ?? '';
$diameter = $tire['diameter'] ?? '';
$category = $tire['category'] ?? '';
$image    = ! empty( $tire['image'] ) ? esc_url( $tire['image'] ) : '';
$link     = ! empty( $tire['link'] ) ? esc_url( $tire['link'] ) : '';

$name_full = trim( "$brand $model" ) ?: 'Tire';
$heading   = $name_full . ( $size ? " ($size)" : '' );

// --- SEO strings. ---
$page_title  = $heading . ' — Rivian Tire Guide';
$canonical   = RTG_Tire_Page::tire_url( $tire['slug'] ?? $tire_id );
$description  = RTG_Meta::build_description( $tire );

// --- Ratings + reviews (server-rendered). ---
$ratings_map = RTG_Database::get_tire_ratings( array( $tire_id ) );
$rating      = $ratings_map[ $tire_id ] ?? array( 'average' => 0, 'count' => 0, 'review_count' => 0 );
$avg         = (float) ( $rating['average'] ?? 0 );
$rating_cnt  = (int) ( $rating['count'] ?? 0 );
$reviews     = RTG_Database::get_tire_reviews( $tire_id, 10 );

// --- Related URLs. ---
$tire_guide_url = home_url( '/' );
$guide_pages    = get_posts( array(
    'post_type'   => 'page',
    'post_status' => 'publish',
    's'           => '[rivian_tire_guide]',
    'numberposts' => 1,
    'fields'      => 'ids',
) );
if ( ! empty( $guide_pages ) ) {
    $tire_guide_url = get_permalink( $guide_pages[0] );
}
$review_slug = sanitize_title( $rtg_settings['tire_review_slug'] ?? 'tire-review' );
$review_url  = add_query_arg( 'tire', rawurlencode( $tire_id ), home_url( '/' . $review_slug . '/' ) );

// --- Structured data: Product + BreadcrumbList. ---
$product_schema = RTG_Schema::build_single_product( $tire );

$breadcrumb_items = array(
    array( 'name' => 'Home', 'url' => home_url( '/' ) ),
    array( 'name' => 'Tire Guide', 'url' => $tire_guide_url ),
);
if ( $category ) {
    $breadcrumb_items[] = array(
        'name' => $category,
        'url'  => add_query_arg( 'category', rawurlencode( $category ), $tire_guide_url ),
    );
}
$breadcrumb_items[] = array( 'name' => $heading, 'url' => $canonical );

$breadcrumb_schema = array(
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => array(),
);
foreach ( $breadcrumb_items as $i => $crumb ) {
    $breadcrumb_schema['itemListElement'][] = array(
        '@type'    => 'ListItem',
        'position' => $i + 1,
        'name'     => $crumb['name'],
        'item'     => esc_url_raw( $crumb['url'] ),
    );
}

/**
 * Render a row of five stars for a 0–5 average.
 */
if ( ! function_exists( 'rtg_tire_page_stars' ) ) {
    function rtg_tire_page_stars( $avg ) {
        $out = '';
        for ( $i = 1; $i <= 5; $i++ ) {
            $fill = $avg >= $i ? 'full' : ( $avg >= $i - 0.5 ? 'half' : 'empty' );
            $out .= '<span class="rtp-star rtp-star-' . $fill . '">&#9733;</span>';
        }
        return $out;
    }
}

// Full spec sheet (efficiency score intentionally omitted — discontinued).
$specs = array(
    'Size'            => $size . ( $diameter ? " ($diameter)" : '' ),
    'Category'        => $category,
    'Average Price'   => ( $tire['price'] > 0 ) ? '$' . number_format( (float) $tire['price'], 2 ) : '',
    'Mileage Warranty' => ( $tire['mileage_warranty'] > 0 ) ? number_format( (int) $tire['mileage_warranty'] ) . ' miles' : '',
    'Weight'          => ( $tire['weight_lb'] > 0 ) ? $tire['weight_lb'] . ' lb' : '',
    '3PMS Rated'      => $tire['three_pms'] ?? '',
    'Load Index'      => $tire['load_index'] ?? '',
    'Load Range'      => $tire['load_range'] ?? '',
    'Max Load'        => ( $tire['max_load_lb'] > 0 ) ? number_format( (int) $tire['max_load_lb'] ) . ' lb' : '',
    'Speed Rating'    => $tire['speed_rating'] ?? '',
    'Tread Depth'     => $tire['tread'] ?? '',
    'Max PSI'         => $tire['psi'] ?? '',
    'UTQG'            => ( strtolower( trim( $tire['utqg'] ?? '' ) ) === 'none' ) ? '' : ( $tire['utqg'] ?? '' ),
);

$roamer_eff = (float) ( $tire['roamer_efficiency'] ?? 0 );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo esc_html( $page_title ); ?></title>
  <meta name="description" content="<?php echo esc_attr( $description ); ?>" />
  <link rel="canonical" href="<?php echo esc_url( $canonical ); ?>" />

  <!-- Open Graph / Twitter Card -->
  <meta property="og:type" content="product" />
  <meta property="og:title" content="<?php echo esc_attr( $page_title ); ?>" />
  <meta property="og:description" content="<?php echo esc_attr( $description ); ?>" />
  <meta property="og:url" content="<?php echo esc_url( $canonical ); ?>" />
  <?php if ( $image ) : ?>
  <meta property="og:image" content="<?php echo esc_url( $image ); ?>" />
  <?php endif; ?>
  <meta name="twitter:card" content="<?php echo $image ? 'summary_large_image' : 'summary'; ?>" />
  <meta name="twitter:title" content="<?php echo esc_attr( $page_title ); ?>" />
  <meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>" />
  <?php if ( $image ) : ?>
  <meta name="twitter:image" content="<?php echo esc_url( $image ); ?>" />
  <?php endif; ?>

  <script type="application/ld+json"><?php echo wp_json_encode( $product_schema, JSON_UNESCAPED_SLASHES ); ?></script>
  <script type="application/ld+json"><?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_SLASHES ); ?></script>

  <?php wp_head(); ?>
  <style>
    :root {
      --rtg-accent: #fba919;
      --rtg-accent-hover: #ffbe4a;
      --rtg-bg-primary: #16191e;
      --rtg-bg-card: #16191e;
      --rtg-bg-input: #3a3e45;
      --rtg-bg-deep: #121418;
      --rtg-text-primary: #ece9e4;
      --rtg-text-light: #f6f4f0;
      --rtg-text-muted: #a19e97;
      --rtg-text-heading: #f6f4f0;
      --rtg-border: #3a3e45;
      --rtg-star-filled: #fba919;
      --rtg-star-user: #4ade80;
      --rtg-star-empty: #2c2f34;
      <?php if ( $rtg_css_vars ) echo $rtg_css_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized hex above ?>
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--rtg-bg-deep);
      color: var(--rtg-text-primary);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
    a { color: var(--rtg-accent); }
    .rtp-topbar {
      background: var(--rtg-bg-primary);
      border-bottom: 1px solid var(--rtg-border);
      padding: 12px 24px;
      position: sticky; top: 0; z-index: 50;
    }
    .rtp-back {
      display: inline-flex; align-items: center; gap: 6px;
      color: var(--rtg-text-muted); text-decoration: none;
      font-size: 14px; font-weight: 500;
    }
    .rtp-back:hover { color: var(--rtg-accent); }
    .rtp-page { max-width: 860px; margin: 0 auto; padding: 24px 20px 80px; }
    .rtp-breadcrumb {
      font-size: 13px; color: var(--rtg-text-muted); margin-bottom: 20px;
      display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
    }
    .rtp-breadcrumb a { color: var(--rtg-text-muted); text-decoration: none; }
    .rtp-breadcrumb a:hover { color: var(--rtg-accent); }
    .rtp-breadcrumb span[aria-current] { color: var(--rtg-text-primary); }
    .rtp-hero { display: flex; gap: 28px; flex-wrap: wrap; margin-bottom: 32px; }
    .rtp-hero-img {
      flex: 0 0 320px; max-width: 100%;
      background: var(--rtg-bg-card); border: 1px solid var(--rtg-border);
      border-radius: 12px; overflow: hidden; aspect-ratio: 1 / 1;
      display: flex; align-items: center; justify-content: center;
    }
    .rtp-hero-img img { width: 100%; height: 100%; object-fit: cover; }
    .rtp-hero-info { flex: 1 1 320px; min-width: 260px; }
    .rtp-brand { font-size: 14px; font-weight: 600; color: var(--rtg-text-muted); text-transform: uppercase; letter-spacing: .5px; }
    .rtp-title { font-size: 28px; font-weight: 700; letter-spacing: -0.5px; color: var(--rtg-text-heading); margin: 4px 0 10px; }
    .rtp-rating { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
    .rtp-star { font-size: 18px; color: var(--rtg-star-empty); }
    .rtp-star-full { color: var(--rtg-star-filled); }
    .rtp-star-half { background: linear-gradient(90deg, var(--rtg-star-filled) 50%, var(--rtg-star-empty) 50%); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .rtp-rating-meta { font-size: 13px; color: var(--rtg-text-muted); }
    .rtp-cta {
      display: inline-flex; align-items: center; justify-content: center;
      background: var(--rtg-accent); color: #15130e; font-weight: 600;
      padding: 12px 22px; border-radius: 8px; text-decoration: none; font-size: 15px;
    }
    .rtp-cta:hover { background: var(--rtg-accent-hover); color: #15130e; }
    .rtp-roamer {
      margin: 12px 0 20px; padding: 12px 16px; border-radius: 10px;
      background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.3);
      font-size: 14px; color: var(--rtg-text-primary);
    }
    .rtp-roamer strong { color: #60a5fa; }
    .rtp-section-title { font-size: 20px; font-weight: 700; color: var(--rtg-text-heading); margin: 36px 0 14px; }
    .rtp-specs { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1px; background: var(--rtg-border); border: 1px solid var(--rtg-border); border-radius: 12px; overflow: hidden; }
    .rtp-spec { background: var(--rtg-bg-card); padding: 12px 16px; display: flex; justify-content: space-between; gap: 12px; }
    .rtp-spec-label { color: var(--rtg-text-muted); font-size: 14px; }
    .rtp-spec-value { color: var(--rtg-text-primary); font-size: 14px; font-weight: 600; text-align: right; }
    .rtp-review { background: var(--rtg-bg-card); border: 1px solid var(--rtg-border); border-radius: 10px; padding: 16px; margin-bottom: 12px; }
    .rtp-review-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
    .rtp-review-author { font-weight: 600; color: var(--rtg-text-heading); font-size: 14px; }
    .rtp-review-date { font-size: 12px; color: var(--rtg-text-muted); }
    .rtp-review-title { font-weight: 600; font-size: 14px; margin: 4px 0; }
    .rtp-review-body { font-size: 14px; color: var(--rtg-text-primary); }
    .rtp-empty { color: var(--rtg-text-muted); font-size: 14px; }
    @media (max-width: 600px) { .rtp-title { font-size: 23px; } }
  </style>
</head>
<body>
  <div class="rtp-topbar">
    <a class="rtp-back" href="<?php echo esc_url( $tire_guide_url ); ?>">&larr; Back to Tire Guide</a>
  </div>

  <div class="rtp-page">
    <nav class="rtp-breadcrumb" aria-label="Breadcrumb">
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

    <div class="rtp-hero">
      <?php if ( $image ) : ?>
      <div class="rtp-hero-img">
        <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $heading ); ?>" />
      </div>
      <?php endif; ?>

      <div class="rtp-hero-info">
        <?php if ( $brand ) : ?><div class="rtp-brand"><?php echo esc_html( $brand ); ?></div><?php endif; ?>
        <h1 class="rtp-title"><?php echo esc_html( $heading ); ?></h1>

        <div class="rtp-rating">
          <span aria-hidden="true"><?php echo rtg_tire_page_stars( $avg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup ?></span>
          <span class="rtp-rating-meta">
            <?php
            if ( $rating_cnt > 0 ) {
                echo esc_html( number_format( $avg, 1 ) . ' (' . $rating_cnt . ' rating' . ( 1 === $rating_cnt ? '' : 's' ) . ')' );
            } else {
                echo 'No ratings yet';
            }
            ?>
          </span>
        </div>

        <?php if ( $roamer_eff > 0 ) : ?>
        <div class="rtp-roamer">
          <strong><?php echo esc_html( number_format( $roamer_eff, 2 ) ); ?> mi/kWh</strong> real-world efficiency
          <?php
          $r_mi  = round( ( (float) ( $tire['roamer_total_km'] ?? 0 ) ) * 0.621371 );
          $r_veh = (int) ( $tire['roamer_vehicle_count'] ?? 0 );
          if ( $r_mi > 0 || $r_veh > 0 ) {
              echo ' &middot; <span style="color:var(--rtg-text-muted)">' . esc_html( number_format( $r_mi ) . ' mi tracked across ' . $r_veh . ' vehicle' . ( 1 === $r_veh ? '' : 's' ) ) . '</span>';
          }
          ?>
        </div>
        <?php endif; ?>

        <?php if ( $link ) : ?>
        <a class="rtp-cta" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="nofollow sponsored noopener">View Tire &rarr;</a>
        <?php endif; ?>
      </div>
    </div>

    <h2 class="rtp-section-title">Specifications</h2>
    <div class="rtp-specs">
      <?php foreach ( $specs as $label => $value ) : ?>
        <?php if ( '' !== trim( (string) $value ) ) : ?>
        <div class="rtp-spec">
          <span class="rtp-spec-label"><?php echo esc_html( $label ); ?></span>
          <span class="rtp-spec-value"><?php echo esc_html( $value ); ?></span>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <h2 class="rtp-section-title">Owner Reviews</h2>
    <?php if ( ! empty( $reviews ) ) : ?>
      <?php foreach ( $reviews as $review ) : ?>
      <div class="rtp-review">
        <div class="rtp-review-head">
          <span class="rtp-review-author"><?php echo esc_html( $review['display_name'] ); ?></span>
          <span aria-hidden="true"><?php echo rtg_tire_page_stars( (float) $review['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup ?></span>
        </div>
        <?php if ( ! empty( $review['review_title'] ) ) : ?>
        <div class="rtp-review-title"><?php echo esc_html( $review['review_title'] ); ?></div>
        <?php endif; ?>
        <?php if ( ! empty( $review['review_text'] ) ) : ?>
        <div class="rtp-review-body"><?php echo esc_html( $review['review_text'] ); ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php else : ?>
      <p class="rtp-empty">No reviews yet. <a href="<?php echo esc_url( $review_url ); ?>">Be the first to review this tire.</a></p>
    <?php endif; ?>

    <p style="margin-top:20px;"><a href="<?php echo esc_url( $review_url ); ?>">Write a review &rarr;</a></p>
  </div>

  <?php wp_footer(); ?>
</body>
</html>
<?php
// End of standalone tire page.
