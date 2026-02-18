<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

$settings = get_option( 'rtg_settings', array() );
$rows_per_page = $settings['rows_per_page'] ?? 12;
$cdn_prefix = $settings['cdn_prefix'] ?? '';
$compare_slug = $settings['compare_slug'] ?? 'tire-compare';
$user_reviews_slug = $settings['user_reviews_slug'] ?? 'user-reviews';

// Theme color defaults.
$default_colors = array(
    'accent'       => '#fba919',
    'accent_hover' => '#fdbe40',
    'bg_primary'   => '#121e2b',
    'bg_card'      => '#162231',
    'bg_input'     => '#374151',
    'bg_deep'      => '#0c1620',
    'text_primary' => '#e5e7eb',
    'text_light'   => '#f1f5f9',
    'text_muted'   => '#8493a5',
    'text_heading' => '#ffffff',
    'border'       => '#1e3044',
    'star_filled'  => '#fba919',
    'star_user'    => '#4ade80',
    'star_empty'   => '#2d3a49',
);
$theme_colors = wp_parse_args( $settings['theme_colors'] ?? array(), $default_colors );

// Load dropdown options.
$dd_brands        = RTG_Admin::get_dropdown_options( 'brands' );
$dd_categories    = RTG_Admin::get_dropdown_options( 'categories' );
$dd_sizes         = RTG_Admin::get_dropdown_options( 'sizes' );
$dd_size_diameter_map = RTG_Admin::get_size_diameter_map();
$dd_load_ranges   = RTG_Admin::get_dropdown_options( 'load_ranges' );
$dd_speed_ratings = RTG_Admin::get_dropdown_options( 'speed_ratings' );
$dd_load_index_map = RTG_Admin::get_load_index_map();
?>

<div class="rtg-wrap">

    <?php if ( $message === 'saved' ) : ?>
        <div class="rtg-notice rtg-notice-success">
            <span>Settings saved.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Tire Guide Settings</h1>
    </div>

    <form method="post">
        <?php wp_nonce_field( 'rtg_save_settings', 'rtg_settings_nonce' ); ?>
        <input type="hidden" name="rtg_save_settings" value="1">

        <!-- Display Settings -->
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Display Settings</h2>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="rows_per_page">Tires Per Page</label>
                    </div>
                    <p class="rtg-field-description">Number of tire cards shown per page on the frontend (default: 12).</p>
                    <input type="number" id="rows_per_page" name="rows_per_page" value="<?php echo esc_attr( $rows_per_page ); ?>" min="4" max="48" step="4" class="rtg-input-small">
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="cdn_prefix">CDN Image Prefix</label>
                    </div>
                    <p class="rtg-field-description">Optional. CDN URL prefix for image optimization (leave blank to use original image URLs).</p>
                    <input type="text" id="cdn_prefix" name="cdn_prefix" value="<?php echo esc_attr( $cdn_prefix ); ?>" class="rtg-input-wide" placeholder="e.g. https://cdn.riviantrackr.com/spio/w_600+q_auto+ret_img+to_webp/">
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="server_side_pagination">Server-side Pagination</label>
                    </div>
                    <p class="rtg-field-description">When enabled, tire data is fetched via AJAX instead of embedding all data in the page. Recommended for catalogs with 200+ tires.</p>
                    <label class="rtg-toggle">
                        <input type="checkbox" id="server_side_pagination" name="server_side_pagination" value="1" <?php checked( ! empty( $settings['server_side_pagination'] ) ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Enable server-side loading</span>
                    </label>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="compare_slug">Compare Page Slug</label>
                    </div>
                    <p class="rtg-field-description">URL slug for the comparison page. Default: <code>tire-compare</code> (accessible at <code><?php echo esc_html( home_url( '/' . $compare_slug . '/' ) ); ?></code>)</p>
                    <input type="text" id="compare_slug" name="compare_slug" value="<?php echo esc_attr( $compare_slug ); ?>">
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="user_reviews_slug">User Reviews Page Slug</label>
                    </div>
                    <p class="rtg-field-description">Page slug where the <code>[rivian_user_reviews]</code> shortcode is placed. Default: <code>user-reviews</code> (accessible at <code><?php echo esc_html( home_url( '/' . $user_reviews_slug . '/' ) ); ?></code>)</p>
                    <input type="text" id="user_reviews_slug" name="user_reviews_slug" value="<?php echo esc_attr( $user_reviews_slug ); ?>">
                </div>
            </div>
        </div>

        <!-- Theme Colors -->
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Theme Colors</h2>
                <p>Customize the frontend color scheme. Enter hex color codes (e.g. <code>#5ec095</code>).</p>
            </div>
            <div class="rtg-card-body">
                <div style="display: grid; grid-template-columns: repeat(4, max-content); gap: 14px 32px;">
                    <?php
                    $color_labels = array(
                        'accent'       => 'Primary Accent',
                        'accent_hover' => 'Accent Hover',
                        'bg_primary'   => 'Background (Primary)',
                        'bg_card'      => 'Background (Card)',
                        'bg_input'     => 'Background (Input)',
                        'bg_deep'      => 'Background (Deep)',
                        'text_primary' => 'Text (Primary)',
                        'text_light'   => 'Text (Light)',
                        'text_muted'   => 'Text (Muted)',
                        'text_heading' => 'Text (Heading)',
                        'border'       => 'Border / Divider',
                        'star_filled'  => 'Stars (Filled)',
                        'star_user'    => 'Stars (Your Rating)',
                        'star_empty'   => 'Stars (Empty)',
                    );
                    foreach ( $color_labels as $key => $label ) :
                    ?>
                        <div>
                            <label class="rtg-field-label" for="rtg_color_<?php echo esc_attr( $key ); ?>" style="display: block; margin-bottom: 4px; font-size: 13px;"><?php echo esc_html( $label ); ?></label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span id="rtg_color_swatch_<?php echo esc_attr( $key ); ?>" style="width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--rtg-border); background-color: <?php echo esc_attr( $theme_colors[ $key ] ); ?>; flex-shrink: 0;"></span>
                                <input type="text" id="rtg_color_<?php echo esc_attr( $key ); ?>" name="rtg_colors[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $theme_colors[ $key ] ); ?>" maxlength="7" placeholder="#000000" style="width: 84px; font-family: monospace; font-size: 13px; padding: 4px 8px;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <script>
        (function(){
            // Live swatch preview on hex input.
            document.querySelectorAll('input[type="text"][id^="rtg_color_"]').forEach(function(input) {
                input.addEventListener('input', function() {
                    var val = input.value.trim();
                    if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                        var swatch = document.getElementById(input.id.replace('rtg_color_', 'rtg_color_swatch_'));
                        if (swatch) swatch.style.backgroundColor = val;
                    }
                });
            });
        })();
        </script>

        <!-- Dropdown Options -->
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Dropdown Options</h2>
                <p>Manage the dropdown choices shown when adding or editing tires. One option per line.</p>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-edit-grid">
                    <div>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="rtg_dd_brands">Brands</label>
                            </div>
                            <textarea id="rtg_dd_brands" name="rtg_dd_brands" rows="8" class="rtg-input-wide" style="max-width:100%;font-size:14px;font-family:var(--rtg-font-stack);padding:10px 12px;border:1px solid var(--rtg-border);border-radius:8px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $dd_brands ) ); ?></textarea>
                        </div>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="rtg_dd_categories">Categories</label>
                            </div>
                            <textarea id="rtg_dd_categories" name="rtg_dd_categories" rows="5" class="rtg-input-wide" style="max-width:100%;font-size:14px;font-family:var(--rtg-font-stack);padding:10px 12px;border:1px solid var(--rtg-border);border-radius:8px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $dd_categories ) ); ?></textarea>
                        </div>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="rtg_dd_sizes">Sizes</label>
                            </div>
                            <textarea id="rtg_dd_sizes" name="rtg_dd_sizes" rows="4" class="rtg-input-wide" style="max-width:100%;font-size:14px;font-family:var(--rtg-font-stack);padding:10px 12px;border:1px solid var(--rtg-border);border-radius:8px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $dd_sizes ) ); ?></textarea>
                        </div>
                    </div>
                    <div>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="rtg_dd_size_diameters">Size &rarr; Tire Diameter</label>
                            </div>
                            <p class="rtg-field-description">Map each tire size to its overall diameter. Format: <code>size = diameter</code> per line. The diameter auto-fills when selecting a size on the tire edit form.</p>
                            <?php
                            $sd_lines = array();
                            foreach ( $dd_size_diameter_map as $size => $diam ) {
                                $sd_lines[] = $size . ' = ' . $diam;
                            }
                            ?>
                            <textarea id="rtg_dd_size_diameters" name="rtg_dd_size_diameters" rows="6" class="rtg-input-wide" style="max-width:100%;font-size:14px;font-family:var(--rtg-font-stack);padding:10px 12px;border:1px solid var(--rtg-border);border-radius:8px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $sd_lines ) ); ?></textarea>
                        </div>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="rtg_dd_load_ranges">Load Ranges</label>
                            </div>
                            <textarea id="rtg_dd_load_ranges" name="rtg_dd_load_ranges" rows="4" class="rtg-input-wide" style="max-width:100%;font-size:14px;font-family:var(--rtg-font-stack);padding:10px 12px;border:1px solid var(--rtg-border);border-radius:8px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $dd_load_ranges ) ); ?></textarea>
                        </div>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="rtg_dd_speed_ratings">Speed Ratings</label>
                            </div>
                            <textarea id="rtg_dd_speed_ratings" name="rtg_dd_speed_ratings" rows="5" class="rtg-input-wide" style="max-width:100%;font-size:14px;font-family:var(--rtg-font-stack);padding:10px 12px;border:1px solid var(--rtg-border);border-radius:8px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $dd_speed_ratings ) ); ?></textarea>
                        </div>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="rtg_dd_load_indexes">Load Indexes</label>
                            </div>
                            <p class="rtg-field-description">Paired values: <code>index = max load lbs</code> per line.</p>
                            <?php
                            $li_lines = array();
                            foreach ( $dd_load_index_map as $idx => $lbs ) {
                                $li_lines[] = $idx . ' = ' . $lbs;
                            }
                            ?>
                            <textarea id="rtg_dd_load_indexes" name="rtg_dd_load_indexes" rows="8" class="rtg-input-wide" style="max-width:100%;font-size:14px;font-family:var(--rtg-font-stack);padding:10px 12px;border:1px solid var(--rtg-border);border-radius:8px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $li_lines ) ); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rtg-footer-actions">
            <button type="submit" class="rtg-btn rtg-btn-primary">Save Settings</button>
        </div>
    </form>

    <hr class="rtg-divider">

    <!-- Shortcode Documentation -->
    <div class="rtg-card">
        <div class="rtg-card-header">
            <h2>Shortcode</h2>
            <p>Add the tire guide to any page or post using this shortcode:</p>
        </div>
        <div class="rtg-card-body">
            <div class="rtg-field-row" style="border-bottom: none;">
                <code class="rtg-inline-code">[rivian_tire_guide]</code>
            </div>
        </div>
    </div>

</div>
