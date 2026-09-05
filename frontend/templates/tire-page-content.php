<?php
/**
 * Individual tire page — CONTENT partial, rendered inside the active theme
 * (via RTG_Theme_Render, injected into the_content). No <html>/<head>/<body>
 * and no global CSS resets: everything is scoped under .rtg-tp so it doesn't
 * fight the theme. SEO tags (title/meta/canonical/JSON-LD) are handled by
 * RTG_Tire_Page + the SEO plugin, not here.
 *
 * Layout (1.91.0): a product-detail hero — photo, brand eyebrow, the model
 * as the H1, size + fitment + category chips, one "at a glance" sentence,
 * four key-stat tiles and one CTA row — then a two-column body with the
 * specifications on the left and the related tires on the right, and the
 * owner reviews across the bottom. On phones everything stacks and a buy
 * bar pins to the bottom once the hero's CTA scrolls away. Ink & brass
 * design system throughout, matching the guide.
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

// The full "Brand Model (Size)" stays the page title, the breadcrumb and
// the JSON-LD name; on the page the brand is the eyebrow, the model the H1
// and the size a chip, so nothing is said twice.
$heading = trim( "$brand $model" ) ?: 'Tire';
$heading .= $size ? " ($size)" : '';
$h1_text  = trim( (string) $model ) ?: $heading;
$diameter_display = $diameter ? ( false === strpos( $diameter, '"' ) ? $diameter . '"' : $diameter ) : '';

// Official review link (article or video) — shown as a secondary CTA.
$review_link_url = ! empty( $tire['review_link'] ) ? esc_url( $tire['review_link'] ) : '';
$review_is_video = false;
if ( $review_link_url ) {
    $review_host     = strtolower( (string) wp_parse_url( $review_link_url, PHP_URL_HOST ) );
    $review_is_video = '' !== RTG_Tire_Page::youtube_id_from_url( $review_link_url )
        || 'tiktok.com' === $review_host
        || substr( $review_host, -11 ) === '.tiktok.com';
}

$ratings_map  = RTG_Database::get_tire_ratings( array( $tire_id ) );
$rating       = $ratings_map[ $tire_id ] ?? array( 'average' => 0, 'count' => 0 );
$avg          = (float) ( $rating['average'] ?? 0 );
$rating_cnt   = (int) ( $rating['count'] ?? 0 );
$reviews      = RTG_Database::get_tire_reviews( $tire_id, RTG_Tire_Page::REVIEWS_PER_PAGE );
$review_total = RTG_Database::get_tire_review_count( $tire_id );
// Sort and star filter only earn their room once there is something to sort.
$review_tools = $review_total > 1;
$star_counts  = $review_tools ? RTG_Database::get_tire_review_star_counts( $tire_id ) : array();

// Load-index fitment: every vehicle this size fits, pass or fail. A visitor
// from a search has pressed no vehicle toggle, so the page answers for all.
$rtg_tp_vehicle_map = RTG_Database::get_vehicle_size_map();
$rtg_tp_floors      = RTG_Fitment::floors();
$rtg_tp_verdicts    = RTG_Fitment::verdicts( $tire, $rtg_tp_vehicle_map, $rtg_tp_floors );
$rtg_tp_load_index  = RTG_Fitment::parse_load_index( $tire['load_index'] ?? '' );
$rtg_tp_fit_ok      = array_values( array_filter( $rtg_tp_verdicts, function ( $v ) {
    return $v['ok'];
} ) );
$rtg_tp_fit_fails   = array_values( array_filter( $rtg_tp_verdicts, function ( $v ) {
    return ! $v['ok'];
} ) );

// Price presentation: the set-of-four figure and how fresh the price is.
$rtg_tp_price     = (float) ( $tire['price'] ?? 0 );
$rtg_tp_set_price = $rtg_tp_price > 0 ? (int) round( $rtg_tp_price * 4 ) : 0;
$rtg_tp_freshness = RTG_Stale_Prices::freshness( $tire, current_time( 'timestamp' ), RTG_Stale_Prices::stale_days() );

// Real-world efficiency and the sample behind it.
$roamer_eff = (float) ( $tire['roamer_efficiency'] ?? 0 );
$r_mi       = (int) round( ( (float) ( $tire['roamer_total_km'] ?? 0 ) ) * 0.621371 );
$r_veh      = (int) ( $tire['roamer_vehicle_count'] ?? 0 );
$r_meta     = array();
if ( $r_mi > 0 ) {
    $r_meta[] = number_format( $r_mi ) . ' mi tracked';
}
if ( $r_veh > 0 ) {
    $r_meta[] = $r_veh . ' vehicle' . ( 1 === $r_veh ? '' : 's' );
}
// Mirrors isLimitedSample() in frontend/js/modules/efficiency.js.
$r_limited = ( $r_mi > 0 || $r_veh > 0 ) && ( ( $r_veh > 0 && $r_veh < 3 ) || ( $r_mi > 0 && $r_mi < 2000 ) );

// Internal links: the model's other sizes, and this size's neighbours.
$rtg_tp_other_sizes = RTG_Database::get_other_sizes( $tire );
$rtg_tp_similar     = RTG_Database::get_similar_tires( $tire );

$guide_url   = RTG_Tire_Page::guide_url();
$rtg_set     = get_option( 'rtg_settings', array() );
$review_slug = sanitize_title( $rtg_set['tire_review_slug'] ?? 'tire-review' );
$review_url  = add_query_arg( 'tire', rawurlencode( $tire_id ), home_url( '/' . $review_slug . '/' ) );
$rtg_tp_retailer = RTG_Retailer::label( $tire );
$compare_url = add_query_arg( 'compare', rawurlencode( $tire_id ), home_url( '/' . sanitize_title( $rtg_set['compare_slug'] ?? 'tire-compare' ) . '/' ) );

// --- The "at a glance" sentence: the line a search snippet quotes. ---
$rtg_tp_glance = '';
{
    $cat = strtolower( trim( (string) $category ) );
    $lead = $cat
        ? ( ( preg_match( '/^[aeiou]/', $cat ) ? 'An ' : 'A ' ) . $cat . ' tire' )
        : 'A tire';

    $fit_names = array_column( $rtg_tp_fit_ok, 'vehicle' );
    $fail_names = array_column( $rtg_tp_fit_fails, 'vehicle' );
    $fit_clause = '';
    if ( $fit_names ) {
        $fit_clause = ' that fits the ' . ( count( $fit_names ) > 1
            ? implode( ', ', array_slice( $fit_names, 0, -1 ) ) . ' and ' . end( $fit_names )
            : $fit_names[0] );
        if ( $fail_names ) {
            $fit_clause .= ' but not the ' . implode( ' or ', $fail_names );
        }
    } elseif ( $fail_names ) {
        $fit_clause = ' that falls below the ' . implode( ' and ', $fail_names ) . ' load-index minimum';
    }

    $clauses = array();
    if ( $rtg_tp_price > 0 ) {
        $clauses[] = 'around $' . number_format( $rtg_tp_price, 0 ) . ' per tire ($' . number_format( $rtg_tp_set_price ) . ' a set)';
    }
    if ( $roamer_eff > 0 ) {
        $eff = 'returning ' . number_format( $roamer_eff, 2 ) . ' mi/kWh';
        $eff .= $r_veh > 0
            ? ' across ' . $r_veh . ' owner vehicle' . ( 1 === $r_veh ? '' : 's' ) . ' on Rivian Roamer'
            : ' in owner data from Rivian Roamer';
        $clauses[] = $eff;
    }

    $rtg_tp_glance = $lead . $fit_clause . ( $clauses ? ', ' . implode( ', ', $clauses ) : '' ) . '.';
}

// --- Chips: size, fitment, category, 3PMS, OEM. ---
$rtg_tp_chips = array();
if ( $size ) {
    $rtg_tp_chips[] = array( 'text' => $size . ( $diameter_display ? ' · ' . $diameter_display : '' ), 'class' => 'rtg-tp-chip-size' );
}
foreach ( $rtg_tp_verdicts as $rtg_tp_v ) {
    $rtg_tp_chips[] = array(
        // A pass is two words — the load index tile carries the numbers. A
        // failure keeps them, because there the detail is the warning.
        'text'  => $rtg_tp_v['ok']
            ? 'Fits ' . $rtg_tp_v['vehicle']
            : $rtg_tp_v['vehicle'] . ' · load index ' . (int) $rtg_tp_load_index . ' is below the ' . (int) $rtg_tp_v['floor'] . ' minimum',
        'class' => $rtg_tp_v['ok'] ? 'rtg-tp-chip-fit' : 'rtg-tp-chip-fit-bad',
        'icon'  => $rtg_tp_v['ok'] ? 'fa-check' : 'fa-triangle-exclamation',
    );
}
if ( $category ) {
    $rtg_tp_chips[] = array( 'text' => $category, 'class' => '' );
}
if ( strtolower( trim( $tire['three_pms'] ?? '' ) ) === 'yes' ) {
    $rtg_tp_chips[] = array( 'text' => '3PMS Rated', 'class' => 'rtg-tp-chip-3pms', 'icon' => 'fa-snowflake' );
}
if ( false !== stripos( (string) ( $tire['tags'] ?? '' ), 'oem' ) ) {
    $rtg_tp_chips[] = array( 'text' => 'OEM', 'class' => '' );
}

// --- Four key-stat tiles. Always four, so the row reads the same on every
// tire; a missing value is a muted "Not listed" rather than a missing tile.
$rtg_tp_price_note = '';
if ( $rtg_tp_price > 0 && ! empty( $rtg_tp_freshness['show'] ) ) {
    $rtg_tp_price_note = $rtg_tp_freshness['label'] . ( $rtg_tp_freshness['stale'] ? ' · may be outdated' : '' );
}
$rtg_tp_load_meta = 'per-tire rating';
if ( $rtg_tp_verdicts ) {
    $rtg_tp_load_meta = 1 === count( $rtg_tp_verdicts )
        ? $rtg_tp_verdicts[0]['vehicle'] . ' minimum is ' . (int) $rtg_tp_verdicts[0]['floor']
        : implode( ' · ', array_map( function ( $v ) {
            return $v['vehicle'] . ' min ' . (int) $v['floor'];
        }, $rtg_tp_verdicts ) );
}
$rtg_tp_tiles = array(
    array(
        'label'         => 'Real-world efficiency',
        'value'         => $roamer_eff > 0 ? number_format( $roamer_eff, 2 ) . ' mi/kWh' : 'No data yet',
        'meta'          => $roamer_eff > 0 ? implode( ' · ', $r_meta ) : 'from Rivian Roamer owners',
        'class'         => 'rtg-tp-stat-roamer' . ( $roamer_eff > 0 ? '' : ' is-empty' ) . ( $r_limited ? ' is-limited' : '' ),
        'tooltip'       => 'Real-World Efficiency',
        'tooltip_extra' => implode( ' · ', $r_meta ) . ( $r_limited ? ( $r_meta ? ' · ' : '' ) . 'limited data so far' : '' ),
        'note'          => $r_limited ? 'Limited data' : '',
        'note_class'    => 'is-stale',
        'note_title'    => $r_limited ? 'Too few vehicles or miles behind this figure to rely on it yet.' : '',
    ),
    array(
        'label'      => 'Average price',
        'value'      => $rtg_tp_price > 0 ? '$' . number_format( $rtg_tp_price, 0 ) : 'Not listed',
        'meta'       => $rtg_tp_price > 0 ? 'per tire · $' . number_format( $rtg_tp_set_price ) . ' / set of 4' : 'check the retailer',
        'class'      => $rtg_tp_price > 0 ? '' : 'is-empty',
        'note'       => $rtg_tp_price_note,
        'note_class' => ! empty( $rtg_tp_freshness['stale'] ) ? 'is-stale' : '',
        'note_title' => ! empty( $rtg_tp_freshness['stale'] )
            ? 'This price hasn\'t been updated in over ' . RTG_Stale_Prices::stale_days() . ' days. Check the retailer for the current price.'
            : '',
    ),
    array(
        'label' => 'Mileage warranty',
        'value' => $tire['mileage_warranty'] > 0 ? number_format( (int) $tire['mileage_warranty'] ) : 'Not listed',
        'meta'  => $tire['mileage_warranty'] > 0 ? 'miles' : 'by the manufacturer',
        'class' => $tire['mileage_warranty'] > 0 ? '' : 'is-empty',
    ),
    array(
        'label'   => 'Load index',
        'value'   => $rtg_tp_load_index > 0 ? (string) $rtg_tp_load_index : 'Not listed',
        'meta'    => $rtg_tp_load_index > 0 ? $rtg_tp_load_meta : 'check the sidewall',
        'class'   => ( $rtg_tp_load_index > 0 ? '' : 'is-empty' ) . ( $rtg_tp_fit_fails ? ' is-warning' : '' ),
        'tooltip' => 'Load Index',
    ),
);

// --- Grouped specifications (skip empty rows). Price lives in the hero. ---
$spec_groups = array(
    array(
        'icon'  => 'fa-ruler-combined',
        'title' => 'Size & Fitment',
        'rows'  => array(
            'Size'         => $size . ( $diameter_display ? " ($diameter_display)" : '' ),
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
    /**
     * Five stars, plus the rating as text for anyone who can't see them.
     * Kept in step with renderStars() in frontend/js/tire-page.js, which
     * builds the same markup for reviews loaded by "Show more".
     */
    function rtg_tire_page_stars( $avg ) {
        $out = '<span aria-hidden="true">';
        for ( $i = 1; $i <= 5; $i++ ) {
            $fill = $avg >= $i ? 'full' : ( $avg >= $i - 0.5 ? 'half' : 'empty' );
            $out .= '<span class="rtg-tp-star rtg-tp-star-' . $fill . '">&#9733;</span>';
        }
        $out .= '</span>';
        $out .= '<span class="rtg-tp-sr-only">Rated ' . esc_html( rtrim( rtrim( number_format( (float) $avg, 1 ), '0' ), '.' ) ) . ' out of 5</span>';
        return $out;
    }
}

if ( ! function_exists( 'rtg_tire_page_related_row' ) ) {
    /**
     * One related-tire row: thumbnail, name, size · price · efficiency, and
     * which Rivian it fits. The two things this page is built on ride every
     * link out of it.
     *
     * @param array $row         Tire row.
     * @param array $vehicle_map Vehicle => sizes.
     */
    function rtg_tire_page_related_row( $row, $vehicle_map ) {
        $url  = RTG_Tire_Page::tire_url( ! empty( $row['slug'] ) ? $row['slug'] : $row['tire_id'] );
        $img  = ! empty( $row['image'] ) ? esc_url( $row['image'] ) : '';
        $meta = array();
        if ( ! empty( $row['size'] ) ) {
            $meta[] = esc_html( $row['size'] );
        }
        if ( (float) ( $row['price'] ?? 0 ) > 0 ) {
            $meta[] = '$' . number_format( (float) $row['price'], 0 );
        }
        $meta[] = (float) ( $row['roamer_efficiency'] ?? 0 ) > 0
            ? '<span class="rtg-tp-related-eff">' . number_format( (float) $row['roamer_efficiency'], 2 ) . ' mi/kWh</span>'
            : '<span class="rtg-tp-related-noeff">No efficiency data</span>';

        $fits = array();
        $row_size = strtolower( trim( (string) ( $row['size'] ?? '' ) ) );
        foreach ( (array) $vehicle_map as $vehicle => $sizes ) {
            if ( in_array( $row_size, array_map( 'strtolower', array_map( 'trim', (array) $sizes ) ), true ) ) {
                $fits[] = $vehicle;
            }
        }
        ?>
        <a class="rtg-tp-related" href="<?php echo esc_url( $url ); ?>">
          <span class="rtg-tp-related-img">
            <?php if ( $img ) : ?>
            <img src="<?php echo esc_url( $img ); ?>" alt="" loading="lazy" decoding="async" />
            <?php else : ?>
            <i class="fa-solid fa-image" aria-hidden="true"></i>
            <?php endif; ?>
          </span>
          <span class="rtg-tp-related-body">
            <span class="rtg-tp-related-name"><span class="rtg-tp-related-brand"><?php echo esc_html( $row['brand'] ?? '' ); ?></span> <?php echo esc_html( $row['model'] ?? '' ); ?></span>
            <span class="rtg-tp-related-meta"><?php echo implode( ' &middot; ', $meta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above ?></span>
          </span>
          <?php if ( $fits ) : ?>
          <span class="rtg-tp-related-fits">
            <?php foreach ( $fits as $vehicle ) : ?>
            <span class="rtg-tp-chip rtg-tp-chip-vehicle"><i class="fa-solid fa-car" aria-hidden="true"></i><?php echo esc_html( 'Fits ' . $vehicle ); ?></span>
            <?php endforeach; ?>
          </span>
          <?php endif; ?>
        </a>
        <?php
    }
}
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
    --rtg-tp-mono: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
    <?php if ( $rtg_tp_css_vars ) echo $rtg_tp_css_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized hex above ?>
    /* No width/padding overrides: as a direct child of the theme's constrained
       entry-content, Blocksy sizes this natively (container width incl.
       responsive edge gutters, centered, capped at the block max-width) —
       identical to every other piece of content on the site. */
    color: var(--rtg-tp-text);
    font-size: 15px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }
  .rtg-tp *, .rtg-tp *::before, .rtg-tp *::after { box-sizing: border-box; }
  .rtg-tp a { color: var(--rtg-tp-accent); text-decoration: none; }
  .rtg-tp a:hover { color: var(--rtg-tp-accent-hover); }
  .rtg-tp .rtg-tp-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

  /* --- Admin bar (only rendered for users who can edit) --- */
  .rtg-tp .rtg-tp-admin {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
    margin: 0 0 16px; padding: 8px 12px;
    border: 1px dashed color-mix(in srgb, var(--rtg-tp-accent) 45%, var(--rtg-tp-border));
    border-radius: 10px;
    background: color-mix(in srgb, var(--rtg-tp-accent) 8%, var(--rtg-tp-deep));
    font-size: 13px;
  }
  .rtg-tp .rtg-tp-admin-badge { font-size: 11px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: var(--rtg-tp-accent); }
  .rtg-tp .rtg-tp-admin-link { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--rtg-tp-accent); }
  .rtg-tp .rtg-tp-admin-note { color: var(--rtg-tp-muted); font-size: 12px; }

  /* --- Breadcrumb --- */
  .rtg-tp .rtg-tp-breadcrumb { font-size: 13px; color: var(--rtg-tp-muted); margin: 0 0 24px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
  .rtg-tp .rtg-tp-breadcrumb a { color: var(--rtg-tp-muted); }
  .rtg-tp .rtg-tp-breadcrumb a:hover { color: var(--rtg-tp-accent); }
  .rtg-tp .rtg-tp-breadcrumb span[aria-current] { color: var(--rtg-tp-text); }

  /* --- Hero --- */
  /* The photo box stretches to the info column's height, so the two sides
     of the hero always share one bottom edge; the photo sits centered in
     whatever that height is. */
  .rtg-tp .rtg-tp-hero { display: flex; gap: 32px; flex-wrap: wrap; align-items: stretch; margin: 0 0 32px; }
  .rtg-tp .rtg-tp-img {
    flex: 0 0 380px; max-width: 100%; min-height: 320px; align-self: stretch;
    position: relative;
    background: #fff; border: 1px solid var(--rtg-tp-border);
    border-radius: 12px; overflow: hidden;
    transition: border-color 0.15s ease;
  }
  .rtg-tp .rtg-tp-img:hover { border-color: color-mix(in srgb, var(--rtg-tp-accent) 35%, var(--rtg-tp-border)); }
  /* Absolutely positioned so the photo's own height never sets the row:
     the box is as tall as the info column, and the photo fits inside it. */
  .rtg-tp .rtg-tp-img img { position: absolute; inset: 20px; width: calc(100% - 40px); height: calc(100% - 40px); object-fit: contain; margin: 0; display: block; }
  .rtg-tp .rtg-tp-info { flex: 1 1 420px; min-width: 280px; display: flex; flex-direction: column; gap: 12px; }
  .rtg-tp .rtg-tp-brand { font-size: 13px; font-weight: 700; color: var(--rtg-tp-accent); text-transform: uppercase; letter-spacing: .8px; margin: 0; }
  .rtg-tp h1.rtg-tp-title { font-size: 34px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.15; color: var(--rtg-tp-heading); margin: -6px 0 0; padding: 0; }
  .rtg-tp .rtg-tp-glance { font-size: 15px; line-height: 1.55; color: var(--rtg-tp-text); margin: 0; max-width: 640px; }
  .rtg-tp .rtg-tp-glance strong { color: var(--rtg-tp-heading); font-weight: 600; }
  .rtg-tp .rtg-tp-glance .rtg-tp-glance-eff { color: #60a5fa; font-weight: 600; }

  .rtg-tp .rtg-tp-rating { display: flex; align-items: center; gap: 8px; margin: 0; flex-wrap: wrap; }
  .rtg-tp .rtg-tp-star { font-size: 17px; color: var(--rtg-tp-star-empty); }
  .rtg-tp .rtg-tp-star-full { color: var(--rtg-tp-accent); filter: drop-shadow(0 1px 3px color-mix(in srgb, var(--rtg-tp-accent) 35%, transparent)); }
  .rtg-tp .rtg-tp-star-half { background: linear-gradient(90deg, var(--rtg-tp-accent) 50%, var(--rtg-tp-star-empty) 50%); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .rtg-tp .rtg-tp-rating-meta { font-size: 13px; color: var(--rtg-tp-muted); }
  .rtg-tp .rtg-tp-rating-meta a { color: var(--rtg-tp-muted); text-decoration: underline; text-underline-offset: 2px; }
  .rtg-tp .rtg-tp-rating-meta a:hover { color: var(--rtg-tp-accent); }

  /* --- Chips --- */
  .rtg-tp .rtg-tp-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; }
  .rtg-tp .rtg-tp-chip {
    display: inline-flex; align-items: center; gap: 6px;
    height: 30px; font-size: 12px; font-weight: 600; line-height: 1;
    padding: 0 12px; border-radius: 20px; white-space: nowrap;
    color: var(--rtg-tp-text);
    background: color-mix(in srgb, var(--rtg-tp-accent) 12%, var(--rtg-tp-deep));
    border: 1px solid color-mix(in srgb, var(--rtg-tp-accent) 25%, transparent);
  }
  .rtg-tp .rtg-tp-chip i { font-size: 11px; line-height: 1; color: var(--rtg-tp-accent); }
  .rtg-tp .rtg-tp-chip-size { background: var(--rtg-tp-deep); border-color: var(--rtg-tp-border); color: var(--rtg-tp-heading); font-family: var(--rtg-tp-mono); }
  .rtg-tp .rtg-tp-chip-fit { background: color-mix(in srgb, #4ade80 12%, var(--rtg-tp-deep)); border-color: color-mix(in srgb, #4ade80 35%, transparent); }
  .rtg-tp .rtg-tp-chip-fit i { color: #4ade80; }
  .rtg-tp .rtg-tp-chip-fit-bad { background: color-mix(in srgb, #ef4444 12%, var(--rtg-tp-deep)); border-color: color-mix(in srgb, #ef4444 40%, transparent); }
  .rtg-tp .rtg-tp-chip-fit-bad i { color: #ef4444; }
  .rtg-tp .rtg-tp-chip-3pms { background: color-mix(in srgb, #60a5fa 14%, var(--rtg-tp-deep)); border-color: color-mix(in srgb, #60a5fa 40%, transparent); }
  .rtg-tp .rtg-tp-chip-3pms i { color: #60a5fa; }
  .rtg-tp .rtg-tp-chip-vehicle { background: color-mix(in srgb, var(--rtg-tp-accent) 22%, var(--rtg-tp-deep)); border-color: color-mix(in srgb, var(--rtg-tp-accent) 55%, transparent); color: var(--rtg-tp-heading); }

  .rtg-tp .rtg-tp-fitment-warning {
    display: flex; align-items: flex-start; gap: 10px;
    margin: 0; padding: 12px 14px; border-radius: 10px;
    background: color-mix(in srgb, #ef4444 10%, var(--rtg-tp-deep));
    border: 1px solid color-mix(in srgb, #ef4444 40%, transparent);
    font-size: 14px; line-height: 1.5;
  }
  .rtg-tp .rtg-tp-fitment-warning i { color: #ef4444; margin-top: 3px; }

  /* --- Key-stat tiles --- */
  .rtg-tp .rtg-tp-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin: 2px 0 0; }
  .rtg-tp .rtg-tp-stat { background: var(--rtg-tp-deep); border: 1px solid var(--rtg-tp-divider); border-radius: 10px; padding: 12px 14px; min-width: 0; }
  .rtg-tp .rtg-tp-stat-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--rtg-tp-muted); margin: 0 0 4px; display: flex; align-items: center; gap: 5px; }
  .rtg-tp .info-tooltip-trigger {
    background: none; border: none; padding: 2px; margin: 0;
    color: var(--rtg-tp-muted); font-size: 13px; line-height: 1;
    cursor: pointer; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    transition: color 0.15s ease;
  }
  .rtg-tp .info-tooltip-trigger:hover { color: var(--rtg-tp-accent); }
  .rtg-tp .info-tooltip-trigger:focus-visible { outline: 2px solid var(--rtg-tp-accent); outline-offset: 2px; }
  .rtg-tp .rtg-tp-stat-value { font-size: 20px; font-weight: 600; line-height: 1.2; color: var(--rtg-tp-heading); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
  .rtg-tp .rtg-tp-stat-meta { font-size: 12px; color: var(--rtg-tp-muted); margin-top: 2px; }
  .rtg-tp .rtg-tp-stat-note { font-size: 12px; color: var(--rtg-tp-muted); margin-top: 2px; }
  .rtg-tp .rtg-tp-stat-note.is-stale { color: #f0b429; font-weight: 600; }
  .rtg-tp .rtg-tp-stat-roamer .rtg-tp-stat-value { color: #60a5fa; }
  .rtg-tp .rtg-tp-stat-roamer.is-limited .rtg-tp-stat-value { color: color-mix(in srgb, #60a5fa 55%, var(--rtg-tp-muted)); }
  .rtg-tp .rtg-tp-stat.is-empty .rtg-tp-stat-value { color: var(--rtg-tp-muted); }
  .rtg-tp .rtg-tp-stat.is-warning { border-color: color-mix(in srgb, #ef4444 45%, transparent); }
  .rtg-tp .rtg-tp-stat.is-warning .rtg-tp-stat-value { color: #f87171; }

  /* --- CTAs --- */
  .rtg-tp .rtg-tp-ctas { display: flex; flex-wrap: wrap; gap: 10px; margin: 4px 0 0; }
  .rtg-tp .rtg-tp-cta {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    min-height: 44px; padding: 10px 20px; border-radius: 8px;
    font-size: 15px; font-weight: 600; cursor: pointer; font-family: inherit;
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    text-decoration: none;
  }
  .rtg-tp .rtg-tp-cta i { font-size: 13px; }
  .rtg-tp .rtg-tp-cta:active { transform: scale(0.97); }
  .rtg-tp .rtg-tp-cta:focus-visible { outline: 2px solid var(--rtg-tp-accent); outline-offset: 2px; }
  .rtg-tp .rtg-tp-cta-primary { background: var(--rtg-tp-accent); color: #15130e; border: 1px solid var(--rtg-tp-accent); }
  .rtg-tp .rtg-tp-cta-primary:hover { background: var(--rtg-tp-accent-hover); border-color: var(--rtg-tp-accent-hover); color: #15130e; }
  .rtg-tp .rtg-tp-cta-secondary { background: transparent; color: var(--rtg-tp-text); border: 1px solid var(--rtg-tp-border); }
  .rtg-tp .rtg-tp-cta-secondary:hover { color: var(--rtg-tp-accent); border-color: color-mix(in srgb, var(--rtg-tp-accent) 45%, var(--rtg-tp-border)); }
  .rtg-tp .rtg-tp-cta-secondary.copied { color: #4ade80; border-color: color-mix(in srgb, #4ade80 45%, var(--rtg-tp-border)); }

  /* --- Section titles --- */
  .rtg-tp h2.rtg-tp-section { font-size: 20px; font-weight: 700; color: var(--rtg-tp-heading); margin: 0; padding: 0; }
  .rtg-tp .rtg-tp-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 0 0 14px; }
  .rtg-tp .rtg-tp-section-caption { font-size: 12px; color: var(--rtg-tp-muted); margin: -8px 0 12px; }

  /* --- Two-column body: specs left, related right --- */
  .rtg-tp .rtg-tp-body { display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); gap: 28px; align-items: start; margin: 0 0 32px; }

  /* --- Specs: one card, values in a fixed left-aligned column --- */
  .rtg-tp .rtg-tp-spec-card { background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border); border-radius: 12px; overflow: hidden; }
  .rtg-tp .rtg-tp-spec-head {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px; border-bottom: 1px solid var(--rtg-tp-border);
    background: var(--rtg-tp-deep);
    font-size: 14px; font-weight: 700; color: var(--rtg-tp-heading);
  }
  .rtg-tp .rtg-tp-spec-head i { color: var(--rtg-tp-accent); font-size: 14px; }
  .rtg-tp .rtg-tp-spec-row { display: grid; grid-template-columns: 170px minmax(0, 1fr); gap: 12px; padding: 9px 18px; border-bottom: 1px solid var(--rtg-tp-divider); }
  .rtg-tp .rtg-tp-spec-group:last-child .rtg-tp-spec-row:last-child { border-bottom: none; }
  .rtg-tp .rtg-tp-spec-label { color: var(--rtg-tp-muted); font-size: 14px; display: inline-flex; align-items: center; gap: 5px; }
  .rtg-tp .rtg-tp-spec-value { color: var(--rtg-tp-text); font-size: 14px; font-weight: 500; font-family: var(--rtg-tp-mono); }

  /* --- Related tires (other sizes / similar in this size) --- */
  .rtg-tp .rtg-tp-related-list { display: flex; flex-direction: column; gap: 10px; margin: 0 0 24px; }
  .rtg-tp .rtg-tp-related {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border-radius: 12px;
    background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border);
    color: var(--rtg-tp-text); text-decoration: none;
    transition: border-color 0.15s ease;
  }
  .rtg-tp .rtg-tp-related:hover { border-color: color-mix(in srgb, var(--rtg-tp-accent) 45%, var(--rtg-tp-border)); color: var(--rtg-tp-text); }
  .rtg-tp .rtg-tp-related-img { flex: 0 0 52px; width: 52px; height: 52px; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 4px; }
  .rtg-tp .rtg-tp-related-img img { width: 100%; height: 100%; object-fit: contain; margin: 0; }
  .rtg-tp .rtg-tp-related-img i { color: var(--rtg-tp-border); font-size: 20px; }
  .rtg-tp .rtg-tp-related-body { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 3px; }
  .rtg-tp .rtg-tp-related-name { font-size: 14px; font-weight: 600; color: var(--rtg-tp-heading); line-height: 1.3; }
  .rtg-tp .rtg-tp-related-name .rtg-tp-related-brand { color: var(--rtg-tp-accent); }
  .rtg-tp .rtg-tp-related-meta { font-size: 12px; color: var(--rtg-tp-muted); font-variant-numeric: tabular-nums; }
  .rtg-tp .rtg-tp-related-meta .rtg-tp-related-eff { color: #60a5fa; font-weight: 600; }
  .rtg-tp .rtg-tp-related-fits { display: flex; gap: 6px; flex-shrink: 0; }

  /* --- Reviews --- */
  .rtg-tp .rtg-tp-reviews-list { display: flex; flex-direction: column; gap: 12px; }
  .rtg-tp .rtg-tp-owners-say { background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border); border-left: 3px solid var(--rtg-tp-accent); border-radius: 12px; padding: 16px 18px; margin: 0 0 12px; }
  .rtg-tp .rtg-tp-owners-say-label { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; color: var(--rtg-tp-accent); margin: 0 0 8px; }
  .rtg-tp .rtg-tp-owners-say-label i { font-size: 12px; }
  .rtg-tp .rtg-tp-owners-say-text { font-size: 15px; line-height: 1.55; color: var(--rtg-tp-heading); margin: 0; }
  .rtg-tp .rtg-tp-owners-say-lists { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
  .rtg-tp .rtg-tp-owners-say-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 4px; }
  .rtg-tp .rtg-tp-owners-say-list li { position: relative; padding-left: 20px; font-size: 13px; color: var(--rtg-tp-text); line-height: 1.4; }
  .rtg-tp .rtg-tp-owners-say-list li i { position: absolute; left: 0; top: 3px; font-size: 12px; }
  .rtg-tp .rtg-tp-owners-say-list.is-pro li i { color: #4ade80; }
  .rtg-tp .rtg-tp-owners-say-list.is-con li i { color: #f97316; }
  .rtg-tp .rtg-tp-owners-say-foot { font-size: 12px; color: var(--rtg-tp-muted); margin: 10px 0 0; }
  @media (max-width: 600px) { .rtg-tp .rtg-tp-owners-say-lists { grid-template-columns: 1fr; } }
  .rtg-tp .rtg-tp-review { background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border); border-radius: 12px; padding: 16px 18px; margin: 0; }
  .rtg-tp .rtg-tp-review-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 0 0 6px; }
  .rtg-tp .rtg-tp-review-who { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .rtg-tp .rtg-tp-review-author { font-weight: 600; color: var(--rtg-tp-heading); font-size: 14px; }
  .rtg-tp .rtg-tp-review-date { font-size: 12px; color: var(--rtg-tp-muted); }
  .rtg-tp .rtg-tp-review-title { font-weight: 600; font-size: 14px; margin: 4px 0; }
  .rtg-tp .rtg-tp-review-body { font-size: 14px; color: var(--rtg-tp-text); margin: 0; }
  .rtg-tp .rtg-tp-reviews-more { display: flex; justify-content: center; margin: 12px 0 0; }
  .rtg-tp .rtg-tp-reviews-more[hidden] { display: none; }
  /* Sort control: the guide's segmented vehicle toggle, at the head's height. */
  .rtg-tp .rtg-tp-reviews-head-tools { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .rtg-tp .rtg-tp-sort { display: flex; gap: 0; background: var(--rtg-tp-deep); border-radius: 10px; padding: 4px; height: 40px; box-sizing: border-box; width: fit-content; }
  .rtg-tp .rtg-tp-sort-btn { padding: 6px 20px; border: none; border-radius: 8px; background: transparent; color: var(--rtg-tp-muted); font-size: 14px; font-weight: 600; font-family: inherit; line-height: 20px; cursor: pointer; transition: background 0.2s ease, color 0.2s ease; }
  .rtg-tp .rtg-tp-sort-btn:hover { color: var(--rtg-tp-heading); }
  .rtg-tp .rtg-tp-sort-btn.is-active { background: var(--rtg-tp-accent); color: #15130e; }
  .rtg-tp .rtg-tp-sort-btn.is-active:hover { background: var(--rtg-tp-accent-hover); }
  .rtg-tp .rtg-tp-sort-btn:focus-visible { outline: 2px solid var(--rtg-tp-accent); outline-offset: 2px; }
  /* Star filter chips: the hero's chips, pressable, with a count. */
  .rtg-tp .rtg-tp-review-filters { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; }
  .rtg-tp button.rtg-tp-chip { cursor: pointer; font-family: inherit; transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease; }
  .rtg-tp button.rtg-tp-chip:not([disabled]):hover { border-color: color-mix(in srgb, var(--rtg-tp-accent) 55%, transparent); }
  .rtg-tp button.rtg-tp-chip:focus-visible { outline: 2px solid var(--rtg-tp-accent); outline-offset: 2px; }
  .rtg-tp .rtg-tp-chip.is-active { background: color-mix(in srgb, var(--rtg-tp-accent) 22%, var(--rtg-tp-deep)); border-color: color-mix(in srgb, var(--rtg-tp-accent) 55%, transparent); color: var(--rtg-tp-heading); }
  .rtg-tp .rtg-tp-chip[disabled] { opacity: 0.5; cursor: not-allowed; background: var(--rtg-tp-deep); border-color: var(--rtg-tp-border); color: var(--rtg-tp-muted); }
  .rtg-tp .rtg-tp-chip-count { color: var(--rtg-tp-muted); font-weight: 500; }
  .rtg-tp .rtg-tp-chip.is-active .rtg-tp-chip-count { color: var(--rtg-tp-text); }
  .rtg-tp .rtg-tp-reviews-caption { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12px; color: var(--rtg-tp-muted); margin: -6px 0 12px; }
  .rtg-tp .rtg-tp-reviews-caption[hidden] { display: none; }
  .rtg-tp .rtg-tp-reviews-caption button { background: none; border: none; padding: 0; margin: 0; color: var(--rtg-tp-accent); font: inherit; font-weight: 600; cursor: pointer; }
  .rtg-tp .rtg-tp-reviews-caption button:hover { color: var(--rtg-tp-accent-hover); }
  .rtg-tp .rtg-tp-reviews-list[aria-busy="true"] { opacity: 0.6; transition: opacity 0.15s ease; }
  @media (max-width: 600px) {
    .rtg-tp .rtg-tp-reviews-head-tools { width: 100%; }
    .rtg-tp .rtg-tp-sort { width: 100%; }
    .rtg-tp .rtg-tp-sort-btn { flex: 1; text-align: center; padding-inline: 8px; }
  }
  .rtg-tp .rtg-tp-reviews-more .rtg-tp-cta[disabled] { opacity: .5; cursor: wait; }
  .rtg-tp .rtg-tp-reviews-empty {
    text-align: center; padding: 48px 20px;
    background: var(--rtg-tp-card); border: 1px solid var(--rtg-tp-border); border-radius: 12px;
  }
  .rtg-tp .rtg-tp-reviews-empty i { font-size: 40px; color: var(--rtg-tp-muted); opacity: .5; margin-bottom: 14px; display: block; }
  .rtg-tp .rtg-tp-reviews-empty-title { font-size: 20px; font-weight: 700; color: var(--rtg-tp-heading); margin: 0 0 6px; }
  .rtg-tp .rtg-tp-reviews-empty-text { font-size: 14px; color: var(--rtg-tp-muted); max-width: 460px; margin: 0 auto 20px; line-height: 1.6; }

  /* --- Phone buy bar: pinned once the hero's CTA scrolls away --- */
  .rtg-tp .rtg-tp-buybar { display: none; }

  @media (max-width: 900px) {
    .rtg-tp .rtg-tp-body { grid-template-columns: 1fr; gap: 24px; }
  }
  @media (max-width: 700px) {
    .rtg-tp .rtg-tp-hero { gap: 16px; margin-bottom: 24px; }
    .rtg-tp .rtg-tp-img { flex-basis: 100%; min-height: 0; height: 220px; align-self: auto; }
    .rtg-tp h1.rtg-tp-title { font-size: 26px; }
    .rtg-tp .rtg-tp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .rtg-tp .rtg-tp-stat { padding: 10px 12px; }
    .rtg-tp .rtg-tp-stat-value { font-size: 18px; }
    .rtg-tp .rtg-tp-ctas .rtg-tp-cta { flex: 1 1 calc(50% - 5px); }
    .rtg-tp .rtg-tp-ctas .rtg-tp-cta-primary { flex-basis: 100%; }
    .rtg-tp .rtg-tp-spec-row { grid-template-columns: 130px minmax(0, 1fr); padding: 9px 14px; }
    .rtg-tp .rtg-tp-related { flex-wrap: wrap; }
    .rtg-tp .rtg-tp-related-fits { width: 100%; padding-left: 64px; }
    .rtg-tp .rtg-tp-buybar {
      position: fixed; left: 0; right: 0; bottom: 0; z-index: 9990;
      display: flex; align-items: center; gap: 12px;
      padding: 10px 16px calc(10px + env(safe-area-inset-bottom));
      background: color-mix(in srgb, var(--rtg-tp-card) 96%, transparent);
      backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
      border-top: 1px solid var(--rtg-tp-border);
      box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.4);
      transform: translateY(110%); transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
    }
    .rtg-tp .rtg-tp-buybar.is-visible { transform: translateY(0); }
    .rtg-tp .rtg-tp-buybar-price { display: flex; flex-direction: column; line-height: 1.15; }
    .rtg-tp .rtg-tp-buybar-price strong { font-size: 18px; font-weight: 700; color: var(--rtg-tp-heading); }
    .rtg-tp .rtg-tp-buybar-price strong span { font-size: 12px; font-weight: 600; color: var(--rtg-tp-muted); }
    .rtg-tp .rtg-tp-buybar-price small { font-size: 11px; color: var(--rtg-tp-muted); }
    .rtg-tp .rtg-tp-buybar .rtg-tp-cta { flex: 1; }
    .rtg-tp.has-buybar { padding-bottom: 76px; }
  }
  @media (prefers-reduced-motion: reduce) {
    .rtg-tp * { transition: none !important; }
  }
</style>

<div class="rtg-tp<?php echo $link ? ' has-buybar' : ''; ?>">
  <?php if ( RTG_Admin::can_edit_tires() ) : ?>
  <div class="rtg-tp-admin">
    <span class="rtg-tp-admin-badge">Admin</span>
    <a class="rtg-tp-admin-link" href="<?php echo esc_url( RTG_Admin::tire_edit_url( $tire_id ) ); ?>">
      <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Edit this tire
    </a>
    <span class="rtg-tp-admin-note">Only you can see this row.</span>
  </div>
  <?php endif; ?>

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
      <h1 class="rtg-tp-title"><?php echo esc_html( $h1_text ); ?></h1>

      <?php if ( ! empty( $rtg_tp_chips ) ) : ?>
      <div class="rtg-tp-chips">
        <?php foreach ( $rtg_tp_chips as $rtg_tp_chip ) : ?>
        <span class="rtg-tp-chip <?php echo esc_attr( $rtg_tp_chip['class'] ); ?>"><?php if ( ! empty( $rtg_tp_chip['icon'] ) ) : ?><i class="fa-solid <?php echo esc_attr( $rtg_tp_chip['icon'] ); ?>" aria-hidden="true"></i><?php endif; ?><?php echo esc_html( $rtg_tp_chip['text'] ); ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ( ! empty( $rtg_tp_fit_fails ) ) : ?>
      <div class="rtg-tp-fitment-warning" role="note">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span><?php echo esc_html( RTG_Fitment::describe( $rtg_tp_load_index, $rtg_tp_fit_fails ) ); ?> A tire below the minimum can't safely carry the vehicle's weight; check the load index before buying.</span>
      </div>
      <?php endif; ?>

      <p class="rtg-tp-glance"><?php echo esc_html( $rtg_tp_glance ); ?></p>

      <div class="rtg-tp-rating">
        <?php echo rtg_tire_page_stars( $avg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup ?>
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

      <div class="rtg-tp-stats">
        <?php foreach ( $rtg_tp_tiles as $stat ) : ?>
        <div class="rtg-tp-stat <?php echo esc_attr( $stat['class'] ); ?>">
          <div class="rtg-tp-stat-label">
            <?php echo esc_html( $stat['label'] ); ?>
            <?php if ( ! empty( $stat['tooltip'] ) ) : ?>
            <button type="button" class="info-tooltip-trigger" data-tooltip-key="<?php echo esc_attr( $stat['tooltip'] ); ?>"<?php echo ! empty( $stat['tooltip_extra'] ) ? ' data-tooltip-extra="' . esc_attr( $stat['tooltip_extra'] ) . '"' : ''; ?> aria-label="More info about <?php echo esc_attr( $stat['label'] ); ?>">
              <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            </button>
            <?php endif; ?>
          </div>
          <div class="rtg-tp-stat-value"><?php echo esc_html( $stat['value'] ); ?></div>
          <?php if ( ! empty( $stat['meta'] ) ) : ?><div class="rtg-tp-stat-meta"><?php echo esc_html( $stat['meta'] ); ?></div><?php endif; ?>
          <?php if ( ! empty( $stat['note'] ) ) : ?><div class="rtg-tp-stat-note <?php echo esc_attr( $stat['note_class'] ?? '' ); ?>"<?php echo ! empty( $stat['note_title'] ) ? ' title="' . esc_attr( $stat['note_title'] ) . '"' : ''; ?>><?php echo esc_html( $stat['note'] ); ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="rtg-tp-ctas">
        <?php if ( $link ) : ?>
        <a class="rtg-tp-cta rtg-tp-cta-primary" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo $rtg_tp_retailer ? 'View at ' . esc_html( $rtg_tp_retailer ) : 'View Tire'; ?><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i></a>
        <?php endif; ?>
        <?php if ( $review_link_url ) : ?>
        <a class="rtg-tp-cta rtg-tp-cta-secondary rtg-tp-review-link" href="<?php echo esc_url( $review_link_url ); ?>" target="_blank" rel="noopener noreferrer">
          <i class="fa-solid <?php echo $review_is_video ? 'fa-circle-play' : 'fa-newspaper'; ?>" aria-hidden="true"></i><?php echo $review_is_video ? 'Watch Review' : 'Read Review'; ?>
        </a>
        <?php endif; ?>
        <a class="rtg-tp-cta rtg-tp-cta-secondary" id="rtgTpCompare" href="<?php echo esc_url( $compare_url ); ?>">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>Compare
        </a>
        <button type="button" class="rtg-tp-cta rtg-tp-cta-secondary" id="rtgTpShare">
          <i class="fa-solid fa-share-nodes" aria-hidden="true"></i><span>Share</span>
        </button>
      </div>
    </div>
  </div>

  <div class="rtg-tp-body">
    <div class="rtg-tp-col">
      <div class="rtg-tp-section-head"><h2 class="rtg-tp-section">Specifications</h2></div>
      <div class="rtg-tp-spec-card">
        <?php foreach ( $spec_groups as $group ) : ?>
          <?php
          $group_rows = array_filter( $group['rows'], function ( $v ) {
              return '' !== trim( (string) $v );
          } );
          if ( empty( $group_rows ) ) {
              continue;
          }
          ?>
        <div class="rtg-tp-spec-group">
          <div class="rtg-tp-spec-head">
            <i class="fa-solid <?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></i>
            <?php echo esc_html( $group['title'] ); ?>
          </div>
          <?php foreach ( $group_rows as $label => $value ) : ?>
          <div class="rtg-tp-spec-row">
            <span class="rtg-tp-spec-label">
              <?php echo esc_html( $label ); ?>
              <?php if ( in_array( $label, array( 'Load Index', '3PMS Rated', 'UTQG' ), true ) ) : ?>
              <button type="button" class="info-tooltip-trigger" data-tooltip-key="<?php echo esc_attr( $label ); ?>" aria-label="More info about <?php echo esc_attr( $label ); ?>">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
              </button>
              <?php endif; ?>
            </span>
            <span class="rtg-tp-spec-value"><?php echo esc_html( $value ); ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="rtg-tp-col">
      <?php if ( ! empty( $rtg_tp_other_sizes ) ) : ?>
      <div class="rtg-tp-section-head"><h2 class="rtg-tp-section">Other sizes of this tire</h2></div>
      <div class="rtg-tp-related-list">
        <?php foreach ( $rtg_tp_other_sizes as $rtg_tp_row ) {
            rtg_tire_page_related_row( $rtg_tp_row, $rtg_tp_vehicle_map );
        } ?>
      </div>
      <?php endif; ?>

      <?php if ( ! empty( $rtg_tp_similar ) ) : ?>
      <div class="rtg-tp-section-head"><h2 class="rtg-tp-section">Similar tires in <?php echo esc_html( $size ); ?></h2></div>
      <div class="rtg-tp-section-caption">Best real-world efficiency first.</div>
      <div class="rtg-tp-related-list">
        <?php foreach ( $rtg_tp_similar as $rtg_tp_row ) {
            rtg_tire_page_related_row( $rtg_tp_row, $rtg_tp_vehicle_map );
        } ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="rtg-tp-section-head" id="rtg-tp-reviews">
    <h2 class="rtg-tp-section">Owner Reviews<?php echo $rating_cnt > 0 ? ' (' . (int) $rating_cnt . ')' : ''; ?></h2>
    <?php if ( ! empty( $reviews ) ) : ?>
    <div class="rtg-tp-reviews-head-tools">
      <?php if ( $review_tools ) : ?>
      <div class="rtg-tp-sort" id="rtgTpReviewSort" role="radiogroup" aria-label="Sort reviews">
        <button type="button" class="rtg-tp-sort-btn is-active" role="radio" aria-checked="true" data-sort="recent">Recent</button>
        <button type="button" class="rtg-tp-sort-btn" role="radio" aria-checked="false" tabindex="-1" data-sort="highest">Highest</button>
        <button type="button" class="rtg-tp-sort-btn" role="radio" aria-checked="false" tabindex="-1" data-sort="lowest">Lowest</button>
      </div>
      <?php endif; ?>
      <a class="rtg-tp-cta rtg-tp-cta-secondary" href="<?php echo esc_url( $review_url ); ?>">Write a Review</a>
    </div>
    <?php endif; ?>
  </div>

  <?php if ( ! empty( $reviews ) ) : ?>
    <?php if ( $review_tools ) : ?>
    <div class="rtg-tp-review-filters" id="rtgTpReviewFilters" role="group" aria-label="Filter reviews by rating">
      <button type="button" class="rtg-tp-chip rtg-tp-filter-chip is-active" aria-pressed="true" data-rating="0">All <span class="rtg-tp-chip-count"><?php echo (int) $review_total; ?></span></button>
      <?php for ( $rtg_tp_star = 5; $rtg_tp_star >= 1; $rtg_tp_star-- ) :
          $rtg_tp_n = (int) ( $star_counts[ $rtg_tp_star ] ?? 0 ); ?>
      <button type="button" class="rtg-tp-chip rtg-tp-filter-chip" aria-pressed="false" data-rating="<?php echo (int) $rtg_tp_star; ?>"<?php echo 0 === $rtg_tp_n ? ' disabled' : ''; ?> aria-label="<?php echo (int) $rtg_tp_star; ?> star reviews, <?php echo (int) $rtg_tp_n; ?>"><i class="fa-solid fa-star" aria-hidden="true"></i><?php echo (int) $rtg_tp_star; ?> <span class="rtg-tp-chip-count"><?php echo (int) $rtg_tp_n; ?></span></button>
      <?php endfor; ?>
    </div>
    <div class="rtg-tp-reviews-caption" id="rtgTpReviewCaption" role="status" aria-live="polite" hidden></div>
    <?php endif; ?>
    <div class="rtg-tp-owners-say" id="rtgTpOwnersSay" hidden></div>
    <div class="rtg-tp-reviews-list" id="rtgTpReviewList">
    <?php foreach ( $reviews as $review ) : ?>
    <div class="rtg-tp-review">
      <div class="rtg-tp-review-head">
        <span class="rtg-tp-review-who">
          <span class="rtg-tp-review-author"><?php echo esc_html( $review['display_name'] ); ?></span>
          <?php if ( ! empty( $review['created_at'] ) ) : ?>
          <span class="rtg-tp-review-date"><?php echo esc_html( date_i18n( 'M Y', strtotime( $review['created_at'] ) ) ); ?></span>
          <?php endif; ?>
        </span>
        <span class="rtg-tp-review-stars"><?php echo rtg_tire_page_stars( (float) $review['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup ?></span>
      </div>
      <?php if ( ! empty( $review['review_title'] ) ) : ?>
      <div class="rtg-tp-review-title"><?php echo esc_html( $review['review_title'] ); ?></div>
      <?php endif; ?>
      <?php if ( ! empty( $review['review_text'] ) ) : ?>
      <div class="rtg-tp-review-body"><?php echo esc_html( $review['review_text'] ); ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php // The button stays in the DOM (hidden) once every review is shown, so a
          // sort or filter change can bring it back without rebuilding it. ?>
    <div class="rtg-tp-reviews-more"<?php echo $review_total > count( $reviews ) ? '' : ' hidden'; ?>>
      <button type="button" class="rtg-tp-cta rtg-tp-cta-secondary" id="rtgTpMoreReviews" data-page="1" data-total="<?php echo (int) $review_total; ?>" data-loaded="<?php echo (int) count( $reviews ); ?>">
        Show more reviews (<?php echo (int) max( 0, $review_total - count( $reviews ) ); ?> more)
      </button>
    </div>
  <?php else : ?>
    <div class="rtg-tp-reviews-empty">
      <i class="fa-solid fa-comment-dots" aria-hidden="true"></i>
      <div class="rtg-tp-reviews-empty-title">No reviews yet</div>
      <p class="rtg-tp-reviews-empty-text">Be the first to share how this tire performs on your Rivian — range impact, road noise, traction, and wear all help fellow owners choose.</p>
      <a class="rtg-tp-cta rtg-tp-cta-primary" href="<?php echo esc_url( $review_url ); ?>">Write a Review</a>
    </div>
  <?php endif; ?>

  <?php if ( $link ) : ?>
  <div class="rtg-tp-buybar" id="rtgTpBuyBar" aria-hidden="true">
    <div class="rtg-tp-buybar-price">
      <?php if ( $rtg_tp_price > 0 ) : ?>
      <strong>$<?php echo esc_html( number_format( $rtg_tp_price, 0 ) ); ?> <span>ea</span></strong>
      <small>$<?php echo esc_html( number_format( $rtg_tp_set_price ) ); ?> / set of 4</small>
      <?php else : ?>
      <strong><?php echo esc_html( $h1_text ); ?></strong>
      <?php endif; ?>
    </div>
    <a class="rtg-tp-cta rtg-tp-cta-primary" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="nofollow sponsored noopener" tabindex="-1"><?php echo $rtg_tp_retailer ? 'View at ' . esc_html( $rtg_tp_retailer ) : 'View Tire'; ?><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i></a>
  </div>
  <?php endif; ?>
</div>
<?php
// End tire page content partial.
