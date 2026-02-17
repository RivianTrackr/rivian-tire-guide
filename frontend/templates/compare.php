<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RivianTrackr Tire Comparison</title>
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
  $compare_slug = $rtg_settings['compare_slug'] ?? 'tire-compare';
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
    .cmp-topbar {
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
    .cmp-topbar-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .cmp-logo { height: 32px; width: auto; }
    .cmp-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--rtg-text-muted);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: color .15s;
    }
    .cmp-back:hover { color: var(--rtg-accent); text-decoration: none; }
    .cmp-topbar-actions { display: flex; gap: 8px; }
    .cmp-btn {
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
    .cmp-btn:hover { border-color: var(--rtg-accent); color: var(--rtg-accent); }
    .cmp-btn-primary {
      background: var(--rtg-accent);
      border-color: var(--rtg-accent);
      color: #0f172a;
    }
    .cmp-btn-primary:hover { background: var(--rtg-accent-hover); border-color: var(--rtg-accent-hover); }

    /* --- Page --- */
    .cmp-page { max-width: 1200px; margin: 0 auto; padding: 24px 20px 60px; }
    .cmp-title {
      font-size: 24px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      margin-bottom: 24px;
    }
    .cmp-subtitle {
      font-size: 14px;
      color: var(--rtg-text-muted);
      margin-top: -18px;
      margin-bottom: 24px;
    }

    /* --- Tire header cards --- */
    .cmp-tire-headers {
      display: grid;
      gap: 16px;
      margin-bottom: 24px;
    }
    .cmp-tire-header {
      background: var(--rtg-bg-card);
      border: 1px solid var(--rtg-border);
      border-radius: 12px;
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .cmp-tire-img-wrap {
      width: 120px;
      height: 120px;
      flex-shrink: 0;
      background: #fff;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .cmp-tire-img-wrap img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }
    .cmp-tire-info { flex: 1; min-width: 0; }
    .cmp-tire-brand {
      font-size: 13px;
      font-weight: 600;
      color: var(--rtg-accent);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }
    .cmp-tire-model {
      font-size: 20px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      margin-bottom: 4px;
    }
    .cmp-tire-size {
      font-size: 14px;
      color: var(--rtg-text-muted);
      margin-bottom: 10px;
    }
    .cmp-tire-meta {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
    }
    .cmp-tire-meta-item {
      display: flex;
      flex-direction: column;
    }
    .cmp-tire-meta-label {
      font-size: 11px;
      font-weight: 600;
      color: var(--rtg-text-muted);
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .cmp-tire-meta-value {
      font-size: 16px;
      font-weight: 700;
      color: var(--rtg-text-heading);
    }

    /* --- Efficiency badge --- */
    .cmp-eff-badge {
      display: inline-flex;
      align-items: center;
      font-size: 13px;
      border-radius: 6px;
      overflow: hidden;
      line-height: 1;
    }
    .cmp-eff-grade {
      padding: 4px 10px;
      font-weight: 800;
      color: #0f172a;
    }
    .cmp-eff-score {
      padding: 4px 10px;
      font-weight: 600;
      background: var(--rtg-bg-primary);
      color: var(--rtg-text-light);
    }

    /* --- Spec sections --- */
    .cmp-section {
      background: var(--rtg-bg-card);
      border: 1px solid var(--rtg-border);
      border-radius: 12px;
      margin-bottom: 16px;
      overflow: hidden;
    }
    .cmp-section-header {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 14px 20px;
      background: var(--rtg-bg-primary);
      border-bottom: 1px solid var(--rtg-border);
      cursor: pointer;
      user-select: none;
    }
    .cmp-section-header:hover { background: #1a2537; }
    .cmp-section-icon {
      color: var(--rtg-accent);
      font-size: 16px;
      width: 20px;
      text-align: center;
    }
    .cmp-section-title {
      font-size: 15px;
      font-weight: 600;
      color: var(--rtg-text-heading);
      flex: 1;
    }
    .cmp-section-chevron {
      color: var(--rtg-text-muted);
      transition: transform .2s;
      font-size: 14px;
    }
    .cmp-section.collapsed .cmp-section-chevron { transform: rotate(-90deg); }
    .cmp-section.collapsed .cmp-section-body { display: none; }

    /* --- Spec rows --- */
    .cmp-row {
      display: grid;
      align-items: center;
      padding: 0;
      border-bottom: 1px solid rgba(51, 65, 85, 0.4);
    }
    .cmp-row:last-child { border-bottom: none; }
    .cmp-row-label {
      padding: 12px 20px;
      font-size: 13px;
      font-weight: 600;
      color: var(--rtg-text-muted);
      background: rgba(30, 41, 59, 0.3);
    }
    .cmp-row-values {
      display: grid;
      gap: 0;
    }
    .cmp-row-value {
      padding: 12px 20px;
      font-size: 14px;
      font-weight: 500;
      color: var(--rtg-text-primary);
      border-left: 1px solid rgba(51, 65, 85, 0.4);
      font-family: monospace;
    }
    .cmp-row-value.is-best {
      color: var(--rtg-accent);
      font-weight: 700;
    }

    /* --- Tags --- */
    .cmp-tags { display: flex; flex-wrap: wrap; gap: 5px; }
    .cmp-tag {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 4px;
      background: var(--rtg-border);
      color: var(--rtg-text-light);
      white-space: nowrap;
    }
    .cmp-tag-ev { background: #2563eb; color: #fff; }
    .cmp-tag-3pms { background: #5ec095; color: #0f172a; }
    .cmp-tag-studded { background: #7c3aed; color: #fff; }

    /* --- CTA buttons --- */
    .cmp-cta-wrap {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .cmp-cta {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 10px 18px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
      transition: all .15s;
      white-space: nowrap;
    }
    .cmp-cta-primary {
      background: var(--rtg-accent);
      color: #0f172a;
    }
    .cmp-cta-primary:hover { background: var(--rtg-accent-hover); }
    .cmp-cta-bundle {
      background: #2563eb;
      color: #fff;
    }
    .cmp-cta-bundle:hover { background: #1d4ed8; }

    /* --- Empty state --- */
    .cmp-empty {
      text-align: center;
      padding: 80px 20px;
    }
    .cmp-empty-icon {
      font-size: 48px;
      color: var(--rtg-border);
      margin-bottom: 16px;
    }
    .cmp-empty-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--rtg-text-heading);
      margin-bottom: 8px;
    }
    .cmp-empty-text {
      font-size: 14px;
      color: var(--rtg-text-muted);
      margin-bottom: 20px;
    }

    /* --- Responsive: desktop grid sizing --- */
    @media (min-width: 769px) {
      .cmp-tire-headers { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
      .cmp-row {
        grid-template-columns: 180px 1fr;
      }
      .cmp-row-values {
        grid-template-columns: repeat(var(--cmp-cols, 2), 1fr);
      }
    }

    /* --- Responsive: mobile --- */
    @media (max-width: 768px) {
      .cmp-topbar { padding: 10px 16px; }
      .cmp-topbar .cmp-back span { display: none; }
      .cmp-page { padding: 16px 12px 40px; }
      .cmp-title { font-size: 20px; margin-bottom: 16px; }
      .cmp-subtitle { margin-bottom: 16px; }

      .cmp-tire-header {
        flex-direction: column;
        text-align: center;
        padding: 16px;
      }
      .cmp-tire-img-wrap { width: 100px; height: 100px; }
      .cmp-tire-meta { justify-content: center; }

      .cmp-row {
        grid-template-columns: 1fr;
      }
      .cmp-row-label {
        padding: 10px 16px;
        font-size: 12px;
      }
      .cmp-row-values {
        grid-template-columns: repeat(var(--cmp-cols, 2), 1fr);
      }
      .cmp-row-value {
        padding: 10px 16px;
        font-size: 13px;
        text-align: center;
      }
      .cmp-row-value:first-child { border-left: none; }

      .cmp-section-header { padding: 12px 16px; }

      .cmp-cta { padding: 8px 12px; font-size: 13px; flex: 1; justify-content: center; }
    }

    @media (max-width: 480px) {
      .cmp-topbar-actions .cmp-btn span { display: none; }
      .cmp-tire-meta { gap: 12px; }
      .cmp-tire-meta-value { font-size: 15px; }
    }

    /* --- Print --- */
    @media print {
      body { background: #fff; color: #1a1a1a; }
      .cmp-topbar { display: none; }
      .cmp-section, .cmp-tire-header { border-color: #ddd; }
      .cmp-section-header { background: #f5f5f5; }
      .cmp-row-label { background: #fafafa; }
      .cmp-row-value { color: #1a1a1a; border-color: #ddd; }
      .cmp-tire-brand { color: #333; }
      .cmp-tire-model, .cmp-tire-meta-value, .cmp-section-title { color: #1a1a1a; }
      .cmp-tire-size, .cmp-tire-meta-label, .cmp-row-label { color: #666; }
      .cmp-cta-wrap { display: none; }
      .cmp-btn { display: none !important; }
    }
  </style>
</head>
<body>

  <!-- Top bar -->
  <div class="cmp-topbar">
    <div class="cmp-topbar-left">
      <a href="<?php echo esc_url( home_url( '/rivian-tire-guide/' ) ); ?>">
        <img src="https://riviantrackr.com/wp-content/uploads/2024/01/RivianTrackrLogo.webp" class="cmp-logo" alt="RivianTrackr" />
      </a>
      <a href="<?php echo esc_url( home_url( '/rivian-tire-guide/' ) ); ?>" class="cmp-back">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to Tire Guide</span>
      </a>
    </div>
    <div class="cmp-topbar-actions">
      <button type="button" class="cmp-btn" id="shareBtn">
        <i class="fa-solid fa-share-nodes"></i>
        <span>Share</span>
      </button>
      <button type="button" class="cmp-btn" onclick="window.print()">
        <i class="fa-solid fa-print"></i>
        <span>Print</span>
      </button>
    </div>
  </div>

  <!-- Content -->
  <div class="cmp-page">
    <h1 class="cmp-title">Tire Comparison</h1>
    <div id="comparisonContent">
      <div class="cmp-empty">
        <div class="cmp-empty-icon"><i class="fa-solid fa-scale-balanced"></i></div>
        <div class="cmp-empty-title">No tires selected</div>
        <div class="cmp-empty-text">Head back to the tire guide and select tires to compare.</div>
        <a href="<?php echo esc_url( home_url( '/rivian-tire-guide/' ) ); ?>" class="cmp-btn cmp-btn-primary">
          <i class="fa-solid fa-arrow-left"></i> Browse Tires
        </a>
      </div>
    </div>
  </div>

  <?php wp_footer(); ?>
</body>
</html>
