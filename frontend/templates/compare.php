<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RivianTrackr Tire Comparison</title>
  <?php wp_head(); ?>
  <?php
  // Output theme color overrides for compare page.
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
          $rtg_css_vars .= $prop . ':' . $rtg_theme[ $key ] . ';';
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
    body {
      font-family: 'Inter', sans-serif;
      background-color: #0c131c;
      color: var(--rtg-text-primary);
      padding: 40px;
      margin: 0;
    }

    h1 {
      color: var(--rtg-text-heading);
      font-size: 28px;
      font-weight: 800;
      margin-bottom: 24px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    .title-group {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo {
      height: 40px;
      width: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background-color: var(--rtg-bg-card);
      border-radius: 12px;
      overflow: hidden;
      table-layout: fixed;
    }

    th, td {
      padding: 16px;
      border: 1px solid #1f2937;
      text-align: left;
      vertical-align: top;
      background-color: var(--rtg-bg-card);
      word-wrap: break-word;
    }

    th {
      background-color: var(--rtg-bg-primary);
      color: var(--rtg-text-light);
      font-weight: 700;
      font-size: 15px;
    }

    td {
      color: var(--rtg-text-primary);
      font-size: 14px;
    }

    img {
      max-height: 100px;
      border-radius: 6px;
      display: block;
      margin: 0 auto;
    }

    a {
      color: var(--rtg-accent);
      text-decoration: none;
      font-weight: 600;
    }

    a:hover {
      text-decoration: underline;
    }

    .tag-container {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 4px;
    }

    .tag-container span {
      display: inline-block;
      white-space: nowrap;
      background: var(--rtg-border);
      color: var(--rtg-text-light);
      font-size: 12px;
      padding: 4px 8px;
      border-radius: 6px;
      font-weight: 600;
      line-height: 1.2;
    }

    #comparisonContent td img {
      width: 100%;
      height: auto;
      object-fit: contain;
      background-color: #fff;
    }
  </style>
</head>
<body>
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; flex-wrap: wrap;">
    <div class="header">
      <a href="<?php echo esc_url( home_url( '/rivian-tire-guide/' ) ); ?>"><img src="https://riviantrackr.com/wp-content/uploads/2024/01/RivianTrackrLogo.webp" class="logo" alt="RivianTrackr Logo" /></a>
    </div>
  </div>
  <div id="comparisonContent"></div>
  <?php wp_footer(); ?>
</body>
</html>
