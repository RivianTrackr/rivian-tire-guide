<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RivianTrackr Tire Comparison</title>
  <?php wp_head(); ?>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #0c131c;
      color: #e5e5e5;
      padding: 40px;
      margin: 0;
    }

    h1 {
      color: #ffffff;
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
      background-color: #121e2b;
      border-radius: 12px;
      overflow: hidden;
      table-layout: fixed;
    }

    th, td {
      padding: 16px;
      border: 1px solid #1f2937;
      text-align: left;
      vertical-align: top;
      background-color: #121e2b;
      word-wrap: break-word;
    }

    th {
      background-color: #1e293b;
      color: #f1f5f9;
      font-weight: 700;
      font-size: 15px;
    }

    td {
      color: #e5e5e5;
      font-size: 14px;
    }

    img {
      max-height: 100px;
      border-radius: 6px;
      display: block;
      margin: 0 auto;
    }

    a {
      color: #5ec095;
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
      background: #334155;
      color: #f1f5f9;
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
