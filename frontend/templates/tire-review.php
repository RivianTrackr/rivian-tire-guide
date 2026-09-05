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
<style>
    .rv-root {
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
      <?php if ( $rtg_css_vars ) echo $rtg_css_vars; ?>
      /* Empty stars paint with this: the empty color lifted toward muted text (see rivian-tires.css). */
      --rtg-star-empty-visible: color-mix(in srgb, var(--rtg-star-empty) 35%, var(--rtg-text-muted));
    }

    .rv-root *, .rv-root *::before, .rv-root *::after { box-sizing: border-box; margin: 0; padding: 0; }

    .rv-root {
      /* Inherit the theme font — matches the main guide, which does the same. */
      font-family: inherit;
      /* No panel background: sit on the theme page bg like the tire pages. */
      color: var(--rtg-text-primary);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* --- Breadcrumb (matches the tire page pattern) --- */
    .rv-breadcrumb {
      font-size: 13px;
      color: var(--rtg-text-muted);
      margin: 0 0 20px;
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      align-items: center;
    }
    .rv-breadcrumb a { color: var(--rtg-text-muted); text-decoration: none; }
    .rv-breadcrumb a:hover { color: var(--rtg-accent); }
    .rv-breadcrumb span[aria-current] { color: var(--rtg-text-primary); }

    /* --- Page container --- */
    /* No width/padding overrides: the .rv-root wrapper is sized natively by
       the theme's constrained entry-content (gutters included). */
    .rv-page {
      padding: 32px 0 80px;
    }
    .rv-title {
      font-size: 28px;
      font-weight: 700;
      letter-spacing: -0.5px;
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
      border-bottom: 1px solid rgba(52,56,63,0.3);
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
    .rv-tire-category-legacy {
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
      color: var(--rtg-star-empty-visible);
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
      color: var(--rtg-star-empty-visible);
      cursor: pointer;
      transition: color .15s, transform .15s, filter .15s;
      user-select: none;
      display: inline-flex;
      line-height: 0;
    }
    .rv-star .star-bg { opacity: 0.8; }
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
      background: rgba(14, 16, 20, 0.6);
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
      color: #15130e;
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
      color: #15130e;
    }
    .rv-btn-primary:hover { background: var(--rtg-accent-hover); border-color: var(--rtg-accent-hover); color: #15130e; text-decoration: none; }

    /* --- Footer --- */

    /* --- Redesign: landing, sections, details, banners (2.2.0) --- */
    .rv-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    .rv-eyebrow { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--rtg-text-muted); }
    .rv-link-btn { background: none; border: none; padding: 0; margin: 0; color: var(--rtg-accent); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .rv-link-btn:hover { color: var(--rtg-accent-hover); }
    .rv-link-muted { color: var(--rtg-text-muted); font-weight: 500; }
    .rv-link-muted:hover { color: var(--rtg-accent); }

    .rv-landing { margin-bottom: 24px; }
    .rv-landing[hidden] { display: none; }
    .rv-landing-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
    .rv-seg { display: flex; gap: 0; background: var(--rtg-bg-deep); border-radius: 10px; padding: 4px; width: fit-content; box-sizing: border-box; }
    .rv-seg:empty { display: none; }
    .rv-seg-btn { padding: 6px 16px; border: none; border-radius: 8px; background: transparent; color: var(--rtg-text-muted); font-size: 14px; font-weight: 600; font-family: inherit; line-height: 20px; cursor: pointer; transition: background .2s, color .2s; }
    .rv-seg-btn:hover { color: var(--rtg-text-heading); }
    .rv-seg-btn.is-active { background: var(--rtg-accent); color: #15130e; }
    .rv-seg-btn.is-active:hover { background: var(--rtg-accent-hover); }
    .rv-seg-btn:focus-visible { outline: 2px solid var(--rtg-accent); outline-offset: 2px; }
    .rv-seg-full { width: 100%; }
    .rv-seg-full .rv-seg-btn { flex: 1; text-align: center; padding-inline: 8px; }
    .rv-popular { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .rv-popular:empty { display: none; }
    .rv-popular-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: var(--rtg-bg-card); border: 1px solid var(--rtg-border); border-radius: 10px; cursor: pointer; text-align: left; font-family: inherit; color: inherit; transition: border-color .15s; min-height: 64px; }
    .rv-popular-item:hover { border-color: color-mix(in srgb, var(--rtg-accent) 50%, var(--rtg-border)); }
    .rv-popular-item:focus-visible { outline: 2px solid var(--rtg-accent); outline-offset: 2px; }
    .rv-popular-thumb { width: 44px; height: 44px; flex-shrink: 0; background: #fff; border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .rv-popular-thumb img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .rv-popular-text { flex: 1; min-width: 0; }
    .rv-popular-name { font-size: 14px; font-weight: 600; color: var(--rtg-text-heading); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rv-popular-meta { font-size: 12px; color: var(--rtg-text-muted); }
    .rv-popular-cta { font-size: 13px; font-weight: 600; color: var(--rtg-accent); white-space: nowrap; }
    .rv-welcome { display: flex; align-items: center; gap: 10px; margin-top: 12px; padding: 12px 14px; background: var(--rtg-bg-card); border: 1px dashed var(--rtg-border); border-radius: 10px; font-size: 13px; color: var(--rtg-text-muted); }
    .rv-welcome[hidden] { display: none; }
    .rv-welcome i { color: var(--rtg-text-muted); }
    .rv-welcome span { flex: 1; }
    .rv-welcome strong { color: var(--rtg-text-primary); font-weight: 600; }

    .rv-tire-chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 4px 0 8px; }
    .rv-chip { display: inline-flex; align-items: center; gap: 6px; height: 30px; font-size: 12px; font-weight: 600; line-height: 1; padding: 0 12px; border-radius: 20px; white-space: nowrap; color: var(--rtg-text-primary); background: color-mix(in srgb, var(--rtg-accent) 12%, var(--rtg-bg-deep)); border: 1px solid color-mix(in srgb, var(--rtg-accent) 25%, transparent); }
    .rv-chip:empty { display: none; }
    .rv-chip-size { background: var(--rtg-bg-deep); border-color: var(--rtg-border); color: var(--rtg-text-heading); font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace; }
    .rv-tire-change { display: flex; gap: 14px; align-items: center; }
    .rv-tire-change a[hidden] { display: none; }

    .rv-form-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .rv-form-time { font-size: 12px; color: var(--rtg-text-muted); white-space: nowrap; }
    .rv-banner { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 12px 24px; border-bottom: 1px solid var(--rtg-border); font-size: 13px; color: var(--rtg-text-primary); line-height: 1.5; }
    .rv-banner[hidden] { display: none; }
    .rv-banner-signed { background: color-mix(in srgb, var(--rtg-accent) 8%, var(--rtg-bg-card)); }
    .rv-banner-aside { color: var(--rtg-text-muted); white-space: nowrap; }
    .rv-banner-existing { flex-direction: column; gap: 6px; }
    .rv-banner-existing.is-block { background: color-mix(in srgb, #60a5fa 8%, var(--rtg-bg-card)); }
    .rv-banner-existing .rv-banner-actions { display: flex; gap: 14px; flex-wrap: wrap; }
    .rv-banner-existing .rv-link-danger { color: #f87171; }
    .rv-banner-existing .rv-link-danger:hover { color: #ef4444; }

    .rv-section { padding: 18px 24px; border-bottom: 1px solid var(--rtg-border); }
    .rv-section:last-of-type { border-bottom: none; }
    .rv-section-head { display: flex; align-items: baseline; gap: 10px; margin: 0 0 12px; flex-wrap: wrap; }
    .rv-step { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: var(--rtg-bg-deep); border: 1px solid var(--rtg-border); font-size: 11px; font-weight: 700; color: var(--rtg-text-muted); flex-shrink: 0; align-self: center; }
    .rv-section-title { font-size: 15px; font-weight: 700; color: var(--rtg-text-heading); }
    .rv-section-hint { font-size: 12px; color: var(--rtg-text-muted); }
    .rv-section .rv-stars-section { padding: 6px 0 0; }
    .rv-section .rv-field { padding: 0; }
    .rv-section .rv-field + .rv-field { margin-top: 14px; }
    .rv-label { display: block; font-size: 13px; font-weight: 600; color: var(--rtg-text-muted); margin-bottom: 6px; }
    .rv-label-hint { font-weight: 500; }
    .rv-hint { display: block; font-size: 12px; color: var(--rtg-text-muted); margin-top: 4px; line-height: 1.4; }
    .rv-stars-section .rv-hint { margin-top: 2px; }
    .rv-under { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-top: 6px; }
    .rv-under .rv-hint, .rv-under .rv-char-count { margin: 0; }
    .rv-guest-under { margin-top: 12px; }
    .rv-guest-under a { color: var(--rtg-accent); font-weight: 600; text-decoration: none; }
    .rv-guest-under a:hover { color: var(--rtg-accent-hover); }
    .rv-guest-section[hidden] { display: none; }
    .rv-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .rv-grid-2 .rv-field + .rv-field { margin-top: 0; }
    .rv-field-error { font-size: 12px; color: #f87171; margin-top: 6px; min-height: 0; }
    .rv-field-error:empty { display: none; }
    .rv-input.is-invalid, .rv-textarea.is-invalid { border-color: #ef4444; }
    .rv-star-text.is-invalid { color: #f87171; }

    .rv-axes { display: flex; flex-direction: column; }
    .rv-axis { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid rgba(52, 56, 63, 0.4); }
    .rv-axis:last-child { border-bottom: none; }
    .rv-axis-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .rv-axis-name { font-size: 14px; font-weight: 600; color: var(--rtg-text-primary); }
    .rv-axis-note { font-size: 12px; color: var(--rtg-text-muted); }
    .rv-axis-stars { display: inline-flex; gap: 2px; flex-shrink: 0; }
    .rv-axis-star { background: none; border: none; padding: 2px; margin: 0; color: var(--rtg-star-empty-visible); cursor: pointer; line-height: 0; display: inline-flex; border-radius: 4px; transition: color .15s, transform .15s; }
    .rv-axis-star .star-bg { opacity: 0.8; }
    .rv-axis-star .star-fill, .rv-axis-star .star-half { opacity: 0; transition: opacity .15s; }
    .rv-axis-star.selected { color: var(--rtg-star-user); }
    .rv-axis-star.selected .star-fill { opacity: 1; }
    .rv-axis-star.hovered { color: var(--rtg-star-filled); }
    .rv-axis-star.hovered .star-fill { opacity: 1; }
    .rv-axis-star:hover { transform: scale(1.15); }
    .rv-axis-star:focus-visible { outline: 2px solid var(--rtg-accent); outline-offset: 1px; }

    .rv-owner { display: flex; align-items: center; gap: 12px; margin-top: 14px; padding: 12px 14px; background: var(--rtg-bg-deep); border: 1px solid var(--rtg-border); border-radius: 10px; cursor: pointer; }
    .rv-owner:hover { border-color: color-mix(in srgb, var(--rtg-accent) 45%, var(--rtg-border)); }
    .rv-toggle { position: relative; flex-shrink: 0; display: inline-flex; }
    .rv-toggle input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .rv-toggle-track { display: block; width: 44px; height: 24px; border-radius: 34px; background: var(--rtg-bg-input); transition: background .2s; }
    .rv-toggle-knob { position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.15); transition: transform .2s; }
    .rv-toggle input:checked + .rv-toggle-track { background: #34c759; }
    .rv-toggle input:checked + .rv-toggle-track .rv-toggle-knob { transform: translateX(20px); }
    .rv-toggle input:focus-visible + .rv-toggle-track { outline: 2px solid var(--rtg-accent); outline-offset: 2px; }
    .rv-owner-text { display: flex; flex-direction: column; gap: 2px; }
    .rv-owner-title { font-size: 14px; font-weight: 600; color: var(--rtg-text-primary); display: inline-flex; align-items: center; gap: 6px; }
    .rv-owner-title i { color: #34d399; font-size: 13px; }
    .rv-owner .rv-hint { margin: 0; }

    .rv-footer-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
    .rv-footer-note { font-size: 13px; color: var(--rtg-text-muted); line-height: 1.4; }
    .rv-error:empty { display: none; }
    .rv-btn-submit { min-height: 44px; }

    .rv-success-recap { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: center; margin: -8px auto 20px; padding: 10px 14px; background: var(--rtg-bg-deep); border: 1px solid var(--rtg-border); border-radius: 10px; font-size: 12px; color: var(--rtg-text-muted); }
    .rv-success-recap[hidden] { display: none; }
    .rv-success-recap strong { color: var(--rtg-text-heading); font-weight: 600; }
    /* Small read-only stars: the success recap and both "already reviewed" banners. */
    .rv-recap-stars { display: inline-flex; gap: 1px; color: var(--rtg-star-filled); vertical-align: -2px; }
    .rv-recap-stars .star-bg { opacity: 0.8; }
    .rv-recap-stars .star-half { opacity: 0; }
    .rv-recap-stars .star-empty { color: var(--rtg-star-empty-visible); }
    .rv-recap-stars .star-empty .star-fill { opacity: 0; }
    .rv-hint[hidden] { display: none; }
    .rv-btn[hidden] { display: none; }

    @media (max-width: 640px) {
      .rv-popular { grid-template-columns: 1fr; }
      .rv-grid-2 { grid-template-columns: 1fr; }
      .rv-grid-2 .rv-field + .rv-field { margin-top: 14px; }
      .rv-section { padding: 16px; }
      .rv-banner { padding: 12px 16px; flex-direction: column; }
      .rv-landing-head .rv-seg { width: 100%; }
      .rv-landing-head .rv-seg-btn { flex: 1; text-align: center; }
      .rv-axis-star svg { width: 24px; height: 24px; }
      .rv-under { flex-direction: column; gap: 4px; }
      .rv-form-footer { flex-direction: column; align-items: stretch; }
      .rv-btn-submit { width: 100%; }
    }

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
    .rv-toast-success { border-left: 3px solid #34c759; }
    .rv-toast-success .rv-toast-icon { color: #34c759; }
    .rv-toast-info { border-left: 3px solid #60a5fa; }
    .rv-toast-info .rv-toast-icon { color: #60a5fa; }

    /* --- Responsive --- */
    @media (max-width: 640px) {
      .rv-page { padding: 20px 0 60px; }
      .rv-title { font-size: 23px; }
      .rv-tire-card { flex-direction: column; text-align: center; padding: 16px; }
      .rv-tire-img { width: 90px; height: 90px; }
      .rv-tire-rating-row { justify-content: center; }
      .rv-field { padding: 0 16px 14px; }
      .rv-stars-section { padding: 16px; }
      .rv-form-header { padding: 16px; }
      .rv-form-footer { padding: 14px 16px; }
      .rv-toast-container { top: 10px; right: 10px; left: 10px; }
    }
  </style>

<div class="rtg-embed rv-root">
  <!-- Content -->
  <div class="rv-page">
    <nav class="rv-breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
      <span aria-hidden="true">&rsaquo;</span>
      <a href="<?php echo esc_url( $tire_guide_url ); ?>">Tire Guide</a>
      <span aria-hidden="true">&rsaquo;</span>
      <span aria-current="page">Write a Review</span>
    </nav>

    <h1 class="rv-title">Review a Tire</h1>
    <p class="rv-subtitle" id="rvSubtitle">No account needed. Pick the tire you drove on, rate it, and it goes live after a quick check.</p>

    <!-- Tire search -->
    <div class="rv-search-wrap">
      <svg class="rv-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <label for="rvTireSearch" class="rv-sr-only">Search for a tire</label>
      <input type="text" class="rv-search" id="rvTireSearch" placeholder="Search by brand, model, or size..." autocomplete="off" />
      <div class="rv-dropdown" id="rvDropdown"></div>
    </div>

    <!-- Landing: a way in that is not a blank search box -->
    <div class="rv-landing" id="rvLanding">
      <div class="rv-landing-head">
        <span class="rv-eyebrow">Or start from your Rivian</span>
        <div class="rv-seg" id="rvVehicleSwitch" role="radiogroup" aria-label="Your Rivian"></div>
      </div>
      <div class="rv-popular" id="rvPopular"></div>
      <div class="rv-welcome" id="rvWelcome" hidden>
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span id="rvWelcomeText"></span>
        <button type="button" class="rv-link-btn" id="rvWelcomeClear">Not you? Clear</button>
      </div>
    </div>

    <!-- Selected tire display -->
    <div class="rv-tire-card" id="rvTireCard">
      <div class="rv-tire-img" id="rvTireImg"></div>
      <div class="rv-tire-info">
        <div class="rv-tire-brand" id="rvTireBrand"></div>
        <div class="rv-tire-model" id="rvTireModel"></div>
        <div class="rv-tire-chips">
          <span class="rv-chip rv-chip-size" id="rvTireSize"></span>
          <span class="rv-chip" id="rvTireCategory"></span>
        </div>
        <div class="rv-tire-rating-row">
          <div class="rv-tire-stars" id="rvTireStars"></div>
          <span class="rv-tire-rating-text" id="rvTireRatingText"></span>
        </div>
        <div class="rv-tire-change">
          <button type="button" class="rv-link-btn rv-link-muted" id="rvChangeTire">Change tire</button>
          <a class="rv-link-btn" id="rvTirePageLink" href="#" hidden>See its page</a>
        </div>
      </div>
    </div>

    <!-- Review form -->
    <form class="rv-form" id="rvForm" novalidate>
      <div class="rv-form-header">
        <h2 id="rvFormTitle">Write Your Review</h2>
        <span class="rv-form-time">About 2 minutes</span>
      </div>

      <div class="rv-banner rv-banner-signed" id="rvSignedBanner" hidden>
        <span id="rvSignedText"></span>
        <span class="rv-banner-aside">No name or email to type</span>
      </div>
      <div class="rv-banner rv-banner-existing" id="rvExistingBanner" role="status" aria-live="polite" hidden></div>

      <!-- 1. Overall rating -->
      <section class="rv-section">
        <div class="rv-section-head"><span class="rv-step">1</span><span class="rv-section-title">Overall rating</span></div>
        <div class="rv-stars-section">
          <div class="rv-stars-select" id="rvStarsSelect" role="radiogroup" aria-label="Overall rating"></div>
          <span class="rv-star-text" id="rvStarText">Select a rating</span>
          <span class="rv-hint">Tap a star. This is the only required part.</span>
          <div class="rv-field-error" id="rvStarError" role="alert"></div>
        </div>
      </section>

      <!-- 2. Detail ratings -->
      <section class="rv-section">
        <div class="rv-section-head"><span class="rv-step">2</span><span class="rv-section-title">Rate the details</span><span class="rv-section-hint">optional, anything you skip is left blank</span></div>
        <div class="rv-axes" id="rvAxes"></div>
      </section>

      <!-- 3. Setup -->
      <section class="rv-section">
        <div class="rv-section-head"><span class="rv-step">3</span><span class="rv-section-title">Your setup</span><span class="rv-section-hint">optional, but it makes the review count for more</span></div>
        <div class="rv-grid-2">
          <div class="rv-field">
            <span class="rv-label" id="rvVehicleLabel">Which Rivian</span>
            <div class="rv-seg rv-seg-full" id="rvVehiclePick" role="radiogroup" aria-labelledby="rvVehicleLabel"></div>
          </div>
          <div class="rv-field">
            <label class="rv-label" for="rvMiles">Miles on this set</label>
            <input type="text" inputmode="numeric" id="rvMiles" class="rv-input" placeholder="e.g. 6,400" maxlength="9" autocomplete="off" />
          </div>
        </div>
        <label class="rv-owner" for="rvOwner">
          <span class="rv-toggle"><input type="checkbox" id="rvOwner" /><span class="rv-toggle-track" aria-hidden="true"><span class="rv-toggle-knob"></span></span></span>
          <span class="rv-owner-text">
            <span class="rv-owner-title">I own this tire <i class="fa-solid fa-certificate" aria-hidden="true"></i></span>
            <span class="rv-hint" id="rvOwnerHint">Adds a verified-owner badge to your review. Tied to your email, no account needed.</span>
          </span>
        </label>
      </section>

      <!-- 4. Words -->
      <section class="rv-section">
        <div class="rv-section-head"><span class="rv-step">4</span><span class="rv-section-title">In your words</span></div>
        <div class="rv-field">
          <label class="rv-label" for="rvReviewTitle">Title <span class="rv-label-hint" id="rvTitleHint">optional</span></label>
          <input type="text" id="rvReviewTitle" class="rv-input" placeholder="Sum up your experience..." maxlength="200" />
        </div>
        <div class="rv-field">
          <label class="rv-label" for="rvReviewText">Review <span class="rv-label-hint" id="rvTextHint">optional</span></label>
          <textarea id="rvReviewText" class="rv-textarea" placeholder="How does it handle, how loud is it, how is it wearing, did your range change?" maxlength="5000" rows="5"></textarea>
          <div class="rv-under">
            <span class="rv-hint">Useful to mention: what you replaced, how many miles, what surprised you.</span>
            <span class="rv-char-count" id="rvCharCount">0/5000</span>
          </div>
          <div class="rv-field-error" id="rvWordsError" role="alert"></div>
        </div>
      </section>

      <!-- 5. About you (guests) -->
      <section class="rv-section rv-guest-section" id="rvGuestSection">
        <div class="rv-section-head"><span class="rv-step">5</span><span class="rv-section-title">About you</span></div>
        <div class="rv-grid-2">
          <div class="rv-field">
            <label class="rv-label" for="rvGuestName">Name <span class="rv-label-hint">shown with your review</span></label>
            <input type="text" id="rvGuestName" class="rv-input" placeholder="How it appears on the review" maxlength="100" autocomplete="name" />
            <div class="rv-field-error" id="rvNameError" role="alert"></div>
          </div>
          <div class="rv-field">
            <label class="rv-label" for="rvGuestEmail">Email</label>
            <input type="email" id="rvGuestEmail" class="rv-input" placeholder="you@example.com" maxlength="254" autocomplete="email" />
            <span class="rv-hint">Only used to tell you when the review is live. Never shown.</span>
            <div class="rv-field-error" id="rvEmailError" role="alert"></div>
          </div>
        </div>
        <div class="rv-under rv-guest-under">
          <span class="rv-hint" id="rvRememberNote" hidden>Remembered from your last review on this device.</span>
          <span class="rv-hint">Have an account? <a id="rvSignIn" href="<?php echo esc_url( wp_login_url() ); ?>">Sign in</a></span>
        </div>
      </section>

      <input type="text" name="website" class="rv-hp" tabindex="-1" autocomplete="off" />

      <!-- Submit -->
      <div class="rv-form-footer">
        <div class="rv-footer-text">
          <span class="rv-footer-note" id="rvFooterNote">Goes live after a quick check, usually the same day. We email you when it is up.</span>
          <div class="rv-error" id="rvError" role="alert"></div>
        </div>
        <button type="submit" class="rv-btn-submit" id="rvSubmitBtn">Submit Review</button>
      </div>
    </form>

    <!-- Success state -->
    <div class="rv-success" id="rvSuccess">
      <div class="rv-success-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
      <div class="rv-success-title" id="rvSuccessTitle">Review Submitted!</div>
      <div class="rv-success-text" id="rvSuccessText">Thanks for sharing your experience.</div>
      <div class="rv-success-recap" id="rvSuccessRecap" hidden></div>
      <div class="rv-success-actions">
        <a href="#" class="rv-btn rv-btn-primary" id="rvSuccessTireLink" hidden>See the tire page</a>
        <button type="button" class="rv-btn" id="rvReviewAnother">Review another tire</button>
        <a href="<?php echo esc_url( $tire_guide_url ); ?>" class="rv-btn">Browse tires</a>
      </div>
    </div>
  </div>
</div>
<?php
// End tire review content partial.
