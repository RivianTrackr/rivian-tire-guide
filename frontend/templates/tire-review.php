<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$rtg_settings    = get_option( 'rtg_settings', array() );
$rtg_theme       = $rtg_settings['theme_colors'] ?? array();
$review_slug     = $rtg_settings['tire_review_slug'] ?? 'tire-review';
$rtg_var_map     = array(
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

// OG meta — tire-specific when deep-linked.
$og_title       = 'Review a Tire — Rivian Tire Guide';
$og_description = 'Share your experience with tires on your Rivian. Select a tire and write a review to help fellow Rivian owners.';
$og_image       = '';
$og_url         = home_url( '/' . sanitize_title( $review_slug ) . '/' );

$preselected_id = isset( $_GET['tire'] ) ? sanitize_text_field( wp_unslash( $_GET['tire'] ) ) : '';
if ( $preselected_id && preg_match( '/^[A-Za-z0-9_-]+$/', $preselected_id ) ) {
    $og_tire = RTG_Database::get_tire( $preselected_id );
    if ( $og_tire ) {
        $brand = $og_tire['brand'] ?? '';
        $model = $og_tire['model'] ?? '';
        $size  = $og_tire['size'] ?? '';
        $og_title = 'Review ' . trim( "$brand $model" );
        if ( $size ) {
            $og_title .= " ($size)";
        }
        $og_title .= ' — Rivian Tire Guide';
        $og_description = "Share your experience with the $brand $model" . ( $size ? " ($size)" : '' ) . ' on your Rivian.';
        $og_image = ! empty( $og_tire['image'] ) ? esc_url( $og_tire['image'] ) : '';
        $og_url = add_query_arg( 'tire', rawurlencode( $preselected_id ), $og_url );
    }
}

// Find the tire guide page URL for back links.
$tire_guide_url = home_url( '/' );
$guide_pages = get_posts( array(
    'post_type'   => 'page',
    'post_status' => 'publish',
    's'           => '[rivian_tire_guide]',
    'numberposts' => 1,
    'fields'      => 'ids',
) );
if ( ! empty( $guide_pages ) ) {
    $tire_guide_url = get_permalink( $guide_pages[0] );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo esc_html( $og_title ); ?></title>

  <!-- Open Graph / Twitter Card -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?php echo esc_attr( $og_title ); ?>" />
  <meta property="og:description" content="<?php echo esc_attr( $og_description ); ?>" />
  <meta property="og:url" content="<?php echo esc_url( $og_url ); ?>" />
  <?php if ( $og_image ) : ?>
  <meta property="og:image" content="<?php echo esc_url( $og_image ); ?>" />
  <?php endif; ?>
  <meta name="twitter:card" content="<?php echo $og_image ? 'summary_large_image' : 'summary'; ?>" />
  <meta name="twitter:title" content="<?php echo esc_attr( $og_title ); ?>" />
  <meta name="twitter:description" content="<?php echo esc_attr( $og_description ); ?>" />
  <?php if ( $og_image ) : ?>
  <meta name="twitter:image" content="<?php echo esc_url( $og_image ); ?>" />
  <?php endif; ?>

  <?php wp_head(); ?>
  <style>
    :root {
      --rtg-accent: #5ec095;
      --rtg-accent-hover: #4ade80;
      --rtg-bg-primary: #1e293b;
      --rtg-bg-card: #121e2b;
      --rtg-bg-input: #374151;
      --rtg-bg-deep: #111827;
      --rtg-text-primary: #e5e5e5;
      --rtg-text-light: #f1f5f9;
      --rtg-text-muted: #94a3b8;
      --rtg-text-heading: #ffffff;
      --rtg-border: #334155;
      --rtg-star-filled: #fba919;
      --rtg-star-user: #4ade80;
      --rtg-star-empty: #2d3a49;
      <?php if ( $rtg_css_vars ) echo $rtg_css_vars; ?>
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--rtg-bg-deep);
      color: var(--rtg-text-primary);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    /* --- Top bar --- */
    .rv-topbar {
      background: var(--rtg-bg-primary);
      border-bottom: 1px solid var(--rtg-border);
      padding: 12px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      position: sticky;
      top: 0;
      z-index: 50;
    }
    .rv-topbar-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .rv-logo { height: 32px; width: auto; }
    .rv-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--rtg-text-muted);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: color .15s;
    }
    .rv-back:hover { color: var(--rtg-accent); text-decoration: none; }

    /* --- Page container --- */
    .rv-page {
      max-width: 640px;
      margin: 0 auto;
      padding: 32px 20px 80px;
    }
    .rv-title {
      font-size: 26px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      margin-bottom: 6px;
    }
    .rv-subtitle {
      font-size: 14px;
      color: var(--rtg-text-muted);
      margin-bottom: 28px;
    }

    /* --- Tire search --- */
    .rv-search-wrap {
      position: relative;
      margin-bottom: 24px;
    }
    .rv-search-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--rtg-text-muted);
      font-size: 16px;
      pointer-events: none;
    }
    .rv-search {
      width: 100%;
      padding: 12px 14px 12px 42px !important;
      background: var(--rtg-bg-card);
      border: 1px solid var(--rtg-border);
      border-radius: 10px;
      color: var(--rtg-text-primary);
      font-size: 15px;
      font-family: inherit;
      transition: border-color .2s;
    }
    .rv-search:focus {
      outline: none;
      border-color: var(--rtg-accent);
    }
    .rv-search::placeholder {
      color: var(--rtg-text-muted);
    }
    .rv-dropdown {
      display: none;
      position: absolute;
      top: calc(100% + 4px);
      left: 0;
      right: 0;
      max-height: 320px;
      overflow-y: auto;
      background: var(--rtg-bg-card);
      border: 1px solid var(--rtg-border);
      border-radius: 10px;
      box-shadow: 0 12px 32px rgba(0,0,0,0.4);
      z-index: 40;
    }
    .rv-dropdown.open { display: block; }
    .rv-dropdown-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      cursor: pointer;
      transition: background .1s;
      border-bottom: 1px solid rgba(51,65,85,0.3);
    }
    .rv-dropdown-item:last-child { border-bottom: none; }
    .rv-dropdown-item:hover,
    .rv-dropdown-item.focused { background: var(--rtg-bg-primary); }
    .rv-dropdown-thumb {
      width: 44px;
      height: 44px;
      flex-shrink: 0;
      background: #fff;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .rv-dropdown-thumb img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }
    .rv-dropdown-text { flex: 1; min-width: 0; }
    .rv-dropdown-name {
      font-size: 14px;
      font-weight: 600;
      color: var(--rtg-text-light);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .rv-dropdown-size {
      font-size: 12px;
      color: var(--rtg-text-muted);
    }
    .rv-dropdown-empty {
      padding: 20px;
      text-align: center;
      color: var(--rtg-text-muted);
      font-size: 14px;
    }

    /* --- Selected tire card --- */
    .rv-tire-card {
      display: none;
      background: var(--rtg-bg-card);
      border: 1px solid var(--rtg-border);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 28px;
      align-items: center;
      gap: 20px;
    }
    .rv-tire-card.visible { display: flex; }
    .rv-tire-img {
      width: 110px;
      height: 110px;
      flex-shrink: 0;
      background: #fff;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .rv-tire-img img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }
    .rv-tire-info { flex: 1; min-width: 0; }
    .rv-tire-brand {
      font-size: 12px;
      font-weight: 600;
      color: var(--rtg-accent);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }
    .rv-tire-model {
      font-size: 20px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      margin-bottom: 2px;
    }
    .rv-tire-size {
      font-size: 14px;
      color: var(--rtg-text-muted);
      margin-bottom: 6px;
    }
    .rv-tire-category {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 4px;
      background: var(--rtg-border);
      color: var(--rtg-text-light);
      margin-bottom: 8px;
    }
    .rv-tire-rating-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .rv-tire-stars {
      display: flex;
      gap: 2px;
      color: var(--rtg-star-empty);
    }
    .rv-tire-stars .star-active { color: var(--rtg-star-filled); }
    .rv-tire-stars .star-half-active { color: var(--rtg-star-filled); }
    .rv-tire-stars svg { display: block; }
    .rv-tire-rating-text {
      font-size: 13px;
      color: var(--rtg-text-muted);
    }
    .rv-tire-change {
      margin-top: 10px;
    }
    .rv-tire-change button {
      background: none;
      border: none;
      color: var(--rtg-text-muted);
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      padding: 0;
      text-decoration: underline;
      text-underline-offset: 2px;
      font-family: inherit;
    }
    .rv-tire-change button:hover { color: var(--rtg-accent); }

    /* --- Review form --- */
    .rv-form {
      display: none;
      background: var(--rtg-bg-card);
      border: 1px solid var(--rtg-border);
      border-radius: 12px;
      overflow: hidden;
    }
    .rv-form.visible { display: block; }
    .rv-form-header {
      padding: 18px 24px 14px;
      border-bottom: 1px solid var(--rtg-border);
    }
    .rv-form-header h2 {
      font-size: 18px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      margin: 0;
    }

    /* Star selector */
    .rv-stars-section {
      padding: 18px 24px;
      text-align: center;
    }
    .rv-stars-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--rtg-text-muted);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .rv-stars-select {
      display: flex;
      justify-content: center;
      gap: 8px;
    }
    .rv-star {
      color: var(--rtg-star-empty);
      cursor: pointer;
      transition: color .15s, transform .15s, filter .15s;
      user-select: none;
      display: inline-flex;
      line-height: 0;
    }
    .rv-star .star-bg { opacity: 0.35; }
    .rv-star .star-fill,
    .rv-star .star-half { opacity: 0; transition: opacity .15s; }
    .rv-star.selected {
      color: var(--rtg-star-user);
      filter: drop-shadow(0 2px 6px color-mix(in srgb, var(--rtg-star-user) 40%, transparent));
    }
    .rv-star.selected .star-fill { opacity: 1; }
    .rv-star.hovered {
      color: var(--rtg-star-filled);
      transform: scale(1.15);
    }
    .rv-star.hovered .star-fill { opacity: 1; }
    .rv-star:hover { transform: scale(1.15); }
    .rv-star-text {
      display: block;
      margin-top: 8px;
      font-size: 14px;
      font-weight: 600;
      color: var(--rtg-text-light);
      min-height: 21px;
    }

    /* Form fields */
    .rv-field {
      padding: 0 24px 16px;
    }
    .rv-field label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--rtg-text-muted);
      margin-bottom: 6px;
    }
    .rv-input {
      width: 100%;
      padding: 10px 14px;
      background: var(--rtg-bg-input);
      border: 1px solid var(--rtg-border);
      border-radius: 8px;
      color: var(--rtg-text-primary);
      font-size: 14px;
      font-family: inherit;
      transition: border-color .2s;
    }
    .rv-input:focus {
      outline: none;
      border-color: var(--rtg-accent);
    }
    .rv-textarea {
      width: 100%;
      padding: 10px 14px;
      background: var(--rtg-bg-input);
      border: 1px solid var(--rtg-border);
      border-radius: 8px;
      color: var(--rtg-text-primary);
      font-size: 14px;
      font-family: inherit;
      resize: vertical;
      min-height: 100px;
      transition: border-color .2s;
    }
    .rv-textarea:focus {
      outline: none;
      border-color: var(--rtg-accent);
    }
    .rv-char-count {
      text-align: right;
      font-size: 12px;
      color: var(--rtg-text-muted);
      margin-top: 4px;
    }
    .rv-guest-notice {
      font-size: 13px;
      color: var(--rtg-text-muted);
      background: rgba(13, 27, 42, 0.6);
      border-radius: 6px;
      padding: 10px 12px;
      margin-top: 10px;
      line-height: 1.4;
    }
    .rv-email-note {
      font-size: 12px;
      color: var(--rtg-text-muted);
      margin-top: 4px;
    }

    /* Login banner */
    .rv-login-banner {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      color: var(--rtg-text-muted);
      background: color-mix(in srgb, var(--rtg-accent) 6%, rgba(13,27,42,0.8));
      border: 1px solid color-mix(in srgb, var(--rtg-accent) 20%, transparent);
      border-radius: 8px;
      padding: 12px 14px;
      margin: 6px 24px 16px;
      line-height: 1.4;
    }
    .rv-login-banner-icon {
      color: var(--rtg-accent);
      flex-shrink: 0;
      font-size: 18px;
    }
    .rv-login-banner-content {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .rv-login-banner-content p { margin: 0; font-size: 13px; }
    .rv-login-banner-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .rv-login-link {
      color: var(--rtg-accent);
      text-decoration: none;
      font-weight: 600;
    }
    .rv-login-link:hover { text-decoration: underline; }
    .rv-login-or { color: var(--rtg-text-muted); font-size: 13px; }

    /* Honeypot */
    .rv-hp { position: absolute; left: -9999px; top: -9999px; opacity: 0; height: 0; width: 0; }

    /* Footer actions */
    .rv-form-footer {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      padding: 16px 24px;
      border-top: 1px solid var(--rtg-border);
    }
    .rv-error {
      flex: 1;
      font-size: 13px;
      color: #ef4444;
    }
    .rv-btn-submit {
      background: var(--rtg-accent);
      border: none;
      border-radius: 8px;
      padding: 10px 24px;
      color: #0f172a;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
      font-family: inherit;
    }
    .rv-btn-submit:hover { background: var(--rtg-accent-hover); }
    .rv-btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

    /* Success state */
    .rv-success {
      display: none;
      text-align: center;
      padding: 48px 24px;
      background: var(--rtg-bg-card);
      border: 1px solid var(--rtg-border);
      border-radius: 12px;
    }
    .rv-success.visible { display: block; }
    .rv-success-icon {
      font-size: 48px;
      margin-bottom: 16px;
      color: var(--rtg-accent);
    }
    .rv-success-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      margin-bottom: 8px;
    }
    .rv-success-text {
      font-size: 14px;
      color: var(--rtg-text-muted);
      margin-bottom: 24px;
      line-height: 1.5;
    }
    .rv-success-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
      flex-wrap: wrap;
    }
    .rv-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 10px 18px;
      border-radius: 8px;
      border: 1px solid var(--rtg-border);
      background: var(--rtg-bg-primary);
      color: var(--rtg-text-primary);
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all .15s;
      text-decoration: none;
      font-family: inherit;
    }
    .rv-btn:hover { border-color: var(--rtg-accent); color: var(--rtg-accent); }
    .rv-btn-primary {
      background: var(--rtg-accent);
      border-color: var(--rtg-accent);
      color: #0f172a;
    }
    .rv-btn-primary:hover { background: var(--rtg-accent-hover); border-color: var(--rtg-accent-hover); }

    /* --- Footer --- */
    .rv-footer {
      text-align: center;
      padding: 32px 20px;
      font-size: 13px;
      color: var(--rtg-text-muted);
    }
    .rv-footer a {
      color: var(--rtg-accent);
      text-decoration: none;
      font-weight: 500;
    }
    .rv-footer a:hover { text-decoration: underline; }

    /* --- Toast notifications --- */
    .rv-toast-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 100001;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .rv-toast {
      background: var(--rtg-bg-card);
      border: 1px solid var(--rtg-border);
      border-radius: 10px;
      padding: 12px 18px;
      color: var(--rtg-text-primary);
      font-size: 14px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      gap: 8px;
      opacity: 0;
      transform: translateX(20px);
      transition: opacity .3s, transform .3s;
    }
    .rv-toast.visible { opacity: 1; transform: translateX(0); }
    .rv-toast-icon { flex-shrink: 0; }
    .rv-toast-success { border-left: 3px solid var(--rtg-accent); }
    .rv-toast-success .rv-toast-icon { color: var(--rtg-accent); }
    .rv-toast-info { border-left: 3px solid #3b82f6; }
    .rv-toast-info .rv-toast-icon { color: #3b82f6; }

    /* --- Responsive --- */
    @media (max-width: 640px) {
      .rv-topbar { padding: 10px 16px; }
      .rv-topbar .rv-back span { display: none; }
      .rv-page { padding: 20px 14px 60px; }
      .rv-title { font-size: 22px; }
      .rv-tire-card { flex-direction: column; text-align: center; padding: 16px; }
      .rv-tire-img { width: 90px; height: 90px; }
      .rv-tire-rating-row { justify-content: center; }
      .rv-field { padding: 0 16px 14px; }
      .rv-stars-section { padding: 16px; }
      .rv-form-header { padding: 16px; }
      .rv-form-footer { padding: 14px 16px; }
      .rv-login-banner { margin: 6px 16px 14px; flex-direction: column; align-items: flex-start; }
      .rv-toast-container { top: 10px; right: 10px; left: 10px; }
    }
  </style>
</head>
<body>

  <!-- Top bar -->
  <div class="rv-topbar">
    <div class="rv-topbar-left">
      <a href="<?php echo esc_url( $tire_guide_url ); ?>">
        <img src="https://riviantrackr.com/wp-content/uploads/2024/01/RivianTrackrLogo.webp" class="rv-logo" alt="RivianTrackr" />
      </a>
      <a href="<?php echo esc_url( $tire_guide_url ); ?>" class="rv-back">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        <span>Back to Tire Guide</span>
      </a>
    </div>
  </div>

  <!-- Content -->
  <div class="rv-page">
    <h1 class="rv-title">Review a Tire</h1>
    <p class="rv-subtitle">Select a tire and share your experience to help fellow Rivian owners.</p>

    <!-- Tire search -->
    <div class="rv-search-wrap">
      <svg class="rv-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" class="rv-search" id="rvTireSearch" placeholder="Search by brand, model, or size..." autocomplete="off" />
      <div class="rv-dropdown" id="rvDropdown"></div>
    </div>

    <!-- Selected tire display -->
    <div class="rv-tire-card" id="rvTireCard">
      <div class="rv-tire-img" id="rvTireImg"></div>
      <div class="rv-tire-info">
        <div class="rv-tire-brand" id="rvTireBrand"></div>
        <div class="rv-tire-model" id="rvTireModel"></div>
        <div class="rv-tire-size" id="rvTireSize"></div>
        <div class="rv-tire-category" id="rvTireCategory"></div>
        <div class="rv-tire-rating-row">
          <div class="rv-tire-stars" id="rvTireStars"></div>
          <span class="rv-tire-rating-text" id="rvTireRatingText"></span>
        </div>
        <div class="rv-tire-change">
          <button type="button" id="rvChangeTire">Change tire</button>
        </div>
      </div>
    </div>

    <!-- Review form -->
    <form class="rv-form" id="rvForm" novalidate>
      <div class="rv-form-header">
        <h2 id="rvFormTitle">Write Your Review</h2>
      </div>

      <!-- Star rating -->
      <div class="rv-stars-section">
        <span class="rv-stars-label">Your Rating</span>
        <div class="rv-stars-select" id="rvStarsSelect" role="radiogroup" aria-label="Select rating"></div>
        <span class="rv-star-text" id="rvStarText">Select a rating</span>
      </div>

      <!-- Guest fields -->
      <div class="rv-field rv-guest-field" id="rvGuestNameField" style="display:none">
        <label for="rvGuestName">Your Name</label>
        <input type="text" id="rvGuestName" class="rv-input" placeholder="John Doe" maxlength="100" required />
      </div>
      <div class="rv-field rv-guest-field" id="rvGuestEmailField" style="display:none">
        <label for="rvGuestEmail">Your Email</label>
        <input type="email" id="rvGuestEmail" class="rv-input" placeholder="you@example.com" maxlength="254" required />
        <div class="rv-email-note">Your email will not be displayed publicly.</div>
      </div>
      <input type="text" name="website" class="rv-hp" tabindex="-1" autocomplete="off" />

      <!-- Review title -->
      <div class="rv-field">
        <label for="rvReviewTitle" id="rvTitleLabel">Review Title (optional)</label>
        <input type="text" id="rvReviewTitle" class="rv-input" placeholder="Sum up your experience..." maxlength="200" />
      </div>

      <!-- Review text -->
      <div class="rv-field">
        <label for="rvReviewText" id="rvTextLabel">Your Review (optional)</label>
        <textarea id="rvReviewText" class="rv-textarea" placeholder="Share your experience with this tire... How does it handle, ride comfort, noise level, tread wear?" maxlength="5000" rows="5"></textarea>
        <div class="rv-char-count" id="rvCharCount">0/5000</div>
      </div>

      <!-- Guest notice -->
      <div class="rv-field" id="rvGuestNotice" style="display:none">
        <div class="rv-guest-notice">Your review will be visible after admin approval.</div>
      </div>

      <!-- Login banner for guests -->
      <div class="rv-login-banner" id="rvLoginBanner" style="display:none">
        <div class="rv-login-banner-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></div>
        <div class="rv-login-banner-content">
          <p>Create an account to edit reviews and favorite tires.</p>
          <div class="rv-login-banner-actions">
            <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="rv-login-link">Sign up</a>
            <span class="rv-login-or">or</span>
            <a href="<?php echo esc_url( wp_login_url( home_url( '/' ) ) ); ?>" class="rv-login-link">Log in</a>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="rv-form-footer">
        <div class="rv-error" id="rvError"></div>
        <button type="submit" class="rv-btn-submit" id="rvSubmitBtn">Submit Review</button>
      </div>
    </form>

    <!-- Success state -->
    <div class="rv-success" id="rvSuccess">
      <div class="rv-success-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
      <div class="rv-success-title" id="rvSuccessTitle">Review Submitted!</div>
      <div class="rv-success-text" id="rvSuccessText">Thanks for sharing your experience. Your review helps fellow Rivian owners make better tire choices.</div>
      <div class="rv-success-actions">
        <button type="button" class="rv-btn" id="rvReviewAnother">Review Another Tire</button>
        <a href="<?php echo esc_url( $tire_guide_url ); ?>" class="rv-btn rv-btn-primary">
          <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Browse Tires
        </a>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="rv-footer">
    <a href="<?php echo esc_url( $tire_guide_url ); ?>">Rivian Tire Guide</a> &mdash; Powered by RivianTrackr
  </div>

  <?php wp_footer(); ?>
</body>
</html>
