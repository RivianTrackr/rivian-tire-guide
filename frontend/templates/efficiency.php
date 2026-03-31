<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tire Efficiency Rankings — RivianTrackr</title>
  <?php wp_head(); ?>
  <?php
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
  ?>
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
    .eff-topbar {
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
    .eff-topbar-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .eff-logo { height: 32px; width: auto; }
    .eff-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--rtg-text-muted);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: color .15s;
    }
    .eff-back:hover { color: var(--rtg-accent); text-decoration: none; }
    .eff-topbar-actions { display: flex; gap: 8px; }
    .eff-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 8px;
      border: 1px solid var(--rtg-border);
      background: var(--rtg-bg-card);
      color: var(--rtg-text-primary);
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all .15s;
      text-decoration: none;
      white-space: nowrap;
    }
    .eff-btn:hover { border-color: var(--rtg-accent); color: var(--rtg-accent); }

    /* --- Page layout --- */
    .eff-page { max-width: 1200px; margin: 0 auto; padding: 24px 20px 60px; }
    .eff-title {
      font-size: 24px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      margin-bottom: 8px;
    }
    .eff-subtitle {
      font-size: 14px;
      color: var(--rtg-text-muted);
      margin-bottom: 24px;
      line-height: 1.6;
    }

    /* --- Filter rows --- */
    .eff-filters {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 24px;
    }
    .eff-filter-row {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .eff-filter-label {
      font-size: 12px;
      font-weight: 700;
      color: var(--rtg-text-muted);
      text-transform: uppercase;
      letter-spacing: 0.6px;
      min-width: 80px;
      flex-shrink: 0;
    }
    .eff-filter-btn {
      padding: 6px 16px;
      border-radius: 8px;
      border: 1px solid var(--rtg-border);
      background: var(--rtg-bg-card);
      color: var(--rtg-text-primary);
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all .15s;
    }
    .eff-filter-btn:hover {
      border-color: var(--rtg-accent);
      color: var(--rtg-accent);
    }
    .eff-filter-btn.active {
      background: var(--rtg-accent);
      border-color: var(--rtg-accent);
      color: #0f172a;
    }

    /* --- Card grid --- */
    .eff-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
    }

    /* --- Winner card --- */
    .eff-card {
      background: var(--rtg-bg-card);
      border: 1px solid var(--rtg-border);
      border-radius: 12px;
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 8px;
      transition: border-color .15s;
    }
    .eff-card:hover { border-color: var(--rtg-accent); }

    .eff-card-category {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--rtg-accent);
      background: rgba(94, 192, 149, 0.1);
      padding: 4px 12px;
      border-radius: 20px;
      margin-bottom: 4px;
    }

    .eff-card-source-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      padding: 3px 10px;
      border-radius: 12px;
    }
    .eff-source-roamer {
      color: #5ec095;
      background: rgba(94, 192, 149, 0.12);
    }
    .eff-source-calculated {
      color: #60a5fa;
      background: rgba(96, 165, 250, 0.12);
    }

    .eff-card-img-wrap {
      width: 120px;
      height: 120px;
      background: #fff;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .eff-card-img-wrap img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }
    .eff-placeholder-icon { color: var(--rtg-border); }

    .eff-card-brand {
      font-size: 12px;
      font-weight: 600;
      color: var(--rtg-text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .eff-card-model {
      font-size: 18px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      line-height: 1.2;
    }
    .eff-card-size {
      font-size: 13px;
      color: var(--rtg-text-muted);
    }

    .eff-card-efficiency {
      margin: 8px 0 4px;
      display: flex;
      align-items: baseline;
      gap: 6px;
    }
    .eff-card-efficiency-value {
      font-size: 32px;
      font-weight: 800;
      color: #60a5fa;
      line-height: 1;
    }
    .eff-card-efficiency-unit {
      font-size: 14px;
      font-weight: 600;
      color: #60a5fa;
      opacity: 0.8;
    }

    .eff-card-meta {
      display: flex;
      gap: 16px;
      font-size: 12px;
      color: var(--rtg-text-muted);
    }
    .eff-card-meta span {
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    /* --- Vehicle breakdown pills --- */
    .eff-card-variants {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      justify-content: center;
      margin: 4px 0;
    }
    .eff-variant-pill {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      font-size: 10px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 10px;
      background: var(--rtg-bg-input);
      color: var(--rtg-text-muted);
      white-space: nowrap;
    }
    .eff-variant-pill small {
      opacity: 0.7;
    }
    .eff-variant-pill.active {
      background: rgba(94, 192, 149, 0.15);
      color: var(--rtg-accent);
    }

    .eff-card-footer {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 4px;
    }
    .eff-card-grade {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 800;
      color: #0f172a;
    }
    .eff-card-price {
      font-size: 18px;
      font-weight: 700;
      color: var(--rtg-text-heading);
    }

    .eff-card-cta {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      width: 100%;
      justify-content: center;
      padding: 10px 18px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
      background: var(--rtg-accent);
      color: #0f172a;
      transition: background .15s;
      margin-top: 4px;
    }
    .eff-card-cta:hover { background: var(--rtg-accent-hover); }

    /* --- Empty state --- */
    .eff-empty {
      text-align: center;
      padding: 80px 20px;
      grid-column: 1 / -1;
    }
    .eff-empty-icon {
      font-size: 48px;
      color: var(--rtg-border);
      margin-bottom: 16px;
    }
    .eff-empty-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      margin-bottom: 8px;
    }
    .eff-empty-text {
      font-size: 14px;
      color: var(--rtg-text-muted);
    }

    /* --- Source credit --- */
    .eff-source {
      text-align: center;
      font-size: 12px;
      color: var(--rtg-text-muted);
      margin-top: 32px;
      padding-top: 16px;
      border-top: 1px solid var(--rtg-border);
    }

    /* --- Responsive --- */
    @media (max-width: 768px) {
      .eff-topbar { padding: 10px 16px; }
      .eff-topbar .eff-back span { display: none; }
      .eff-page { padding: 16px 12px 40px; }
      .eff-title { font-size: 20px; }
      .eff-grid { grid-template-columns: 1fr; gap: 16px; }
      .eff-filters { gap: 8px; }
      .eff-filter-row { gap: 6px; }
      .eff-filter-label { min-width: auto; width: 100%; font-size: 11px; margin-bottom: -2px; }
      .eff-filter-btn { padding: 6px 14px; font-size: 13px; }
      .eff-card-variants { gap: 4px; }
      .eff-variant-pill { font-size: 9px; padding: 2px 6px; }
    }

    @media (max-width: 480px) {
      .eff-topbar-actions .eff-btn span { display: none; }
      .eff-card-efficiency-value { font-size: 28px; }
      .eff-filter-btn { padding: 5px 10px; font-size: 12px; flex: 1 1 auto; text-align: center; }
    }

    /* --- Print --- */
    @media print {
      body { background: #fff; color: #1a1a1a; }
      .eff-topbar { display: none; }
      .eff-card { border-color: #ddd; }
      .eff-card-brand, .eff-card-size, .eff-card-meta { color: #666; }
      .eff-card-model, .eff-card-price, .eff-empty-title { color: #1a1a1a; }
      .eff-card-cta { display: none; }
      .eff-btn { display: none !important; }
    }
  </style>
</head>
<body>

  <!-- Top bar -->
  <div class="eff-topbar">
    <div class="eff-topbar-left">
      <a href="<?php echo esc_url( home_url( '/rivian-tire-guide/' ) ); ?>">
        <img src="https://riviantrackr.com/wp-content/uploads/2024/01/RivianTrackrLogo.webp" class="eff-logo" alt="RivianTrackr" />
      </a>
      <a href="<?php echo esc_url( home_url( '/rivian-tire-guide/' ) ); ?>" class="eff-back">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        <span>Back to Tire Guide</span>
      </a>
    </div>
    <div class="eff-topbar-actions">
      <button type="button" class="eff-btn" id="effShareBtn">
        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
        <span>Share</span>
      </button>
      <button type="button" class="eff-btn" onclick="window.print()">
        <i class="fa-solid fa-print" aria-hidden="true"></i>
        <span>Print</span>
      </button>
    </div>
  </div>

  <!-- Content -->
  <div class="eff-page">
    <h1 class="eff-title">Tire Efficiency Rankings</h1>
    <p class="eff-subtitle">
      The most energy-efficient tire in each category. Rankings use real-world mi/kWh data from
      <strong>Rivian Roamer</strong> when available, with calculated efficiency scores as a fallback.
      Select your vehicle to see the best tire for your Rivian.
    </p>

    <div class="eff-filters">
      <div id="effFilterModel" class="eff-filter-row" role="radiogroup" aria-label="Filter by vehicle type"></div>
      <div id="effFilterGen" class="eff-filter-row" role="radiogroup" aria-label="Filter by generation" style="display:none"></div>
      <div id="effFilterPack" class="eff-filter-row" role="radiogroup" aria-label="Filter by battery pack" style="display:none"></div>
    </div>

    <div id="effGrid" class="eff-grid">
      <div class="eff-empty">
        <div class="eff-empty-icon"><i class="fa-solid fa-spinner fa-spin" style="font-size:48px" aria-hidden="true"></i></div>
        <div class="eff-empty-title">Loading efficiency data&hellip;</div>
      </div>
    </div>

    <div class="eff-source">
      Real-world efficiency data provided by <strong>Rivian Roamer</strong> &mdash; collected from actual Rivian owners on real roads.
    </div>
  </div>

  <?php wp_footer(); ?>
</body>
</html>
