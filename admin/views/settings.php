<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

$settings = get_option( 'rtg_settings', array() );
$rows_per_page = $settings['rows_per_page'] ?? 12;
$cdn_prefix = $settings['cdn_prefix'] ?? '';
$compare_slug = $settings['compare_slug'] ?? 'tire-compare';

// Theme color defaults.
$default_colors = array(
    'accent'       => '#5ec095',
    'accent_hover' => '#4ade80',
    'bg_primary'   => '#1e293b',
    'bg_card'      => '#121e2b',
    'bg_input'     => '#374151',
    'bg_deep'      => '#111827',
    'text_primary' => '#e5e5e5',
    'text_light'   => '#f1f5f9',
    'text_muted'   => '#94a3b8',
    'text_heading' => '#ffffff',
    'border'       => '#334155',
);
$theme_colors = wp_parse_args( $settings['theme_colors'] ?? array(), $default_colors );

// Load dropdown options.
$dd_brands        = RTG_Admin::get_dropdown_options( 'brands' );
$dd_categories    = RTG_Admin::get_dropdown_options( 'categories' );
$dd_sizes         = RTG_Admin::get_dropdown_options( 'sizes' );
$dd_diameters     = RTG_Admin::get_dropdown_options( 'diameters' );
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
                        <label class="rtg-field-label" for="compare_slug">Compare Page Slug</label>
                    </div>
                    <p class="rtg-field-description">URL slug for the comparison page. Default: <code>tire-compare</code> (accessible at <code><?php echo esc_html( home_url( '/' . $compare_slug . '/' ) ); ?></code>)</p>
                    <input type="text" id="compare_slug" name="compare_slug" value="<?php echo esc_attr( $compare_slug ); ?>">
                </div>
            </div>
        </div>

        <!-- Theme Colors -->
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Theme Colors</h2>
                <p>Customize the frontend color scheme. Click any swatch to change it, or use the reset button to restore defaults.</p>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-edit-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
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
                    );
                    foreach ( $color_labels as $key => $label ) :
                    ?>
                        <div class="rtg-field-row" style="border-bottom: none; padding-bottom: 0;">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="rtg_color_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="color" id="rtg_color_<?php echo esc_attr( $key ); ?>" name="rtg_colors[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $theme_colors[ $key ] ); ?>" style="width: 44px; height: 36px; padding: 2px; border: 1px solid var(--rtg-border); border-radius: 6px; cursor: pointer; background: var(--rtg-surface);">
                                <code style="font-size: 13px; color: var(--rtg-text-muted);" id="rtg_color_hex_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $theme_colors[ $key ] ); ?></code>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 16px;">
                    <button type="button" id="rtg-reset-colors" class="rtg-btn" style="font-size: 13px;">Reset to Defaults</button>
                </div>
            </div>
        </div>

        <script>
        (function(){
            // Update hex display when color changes.
            document.querySelectorAll('input[type="color"][id^="rtg_color_"]').forEach(function(input) {
                input.addEventListener('input', function() {
                    var hexEl = document.getElementById(input.id + '_hex');
                    if (hexEl) hexEl.textContent = input.value;
                });
            });

            // Reset to defaults.
            var defaults = <?php echo wp_json_encode( $default_colors ); ?>;
            document.getElementById('rtg-reset-colors').addEventListener('click', function() {
                Object.keys(defaults).forEach(function(key) {
                    var input = document.getElementById('rtg_color_' + key);
                    var hexEl = document.getElementById('rtg_color_hex_' + key);
                    if (input) input.value = defaults[key];
                    if (hexEl) hexEl.textContent = defaults[key];
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
                                <label class="rtg-field-label" for="rtg_dd_diameters">Diameters</label>
                            </div>
                            <textarea id="rtg_dd_diameters" name="rtg_dd_diameters" rows="4" class="rtg-input-wide" style="max-width:100%;font-size:14px;font-family:var(--rtg-font-stack);padding:10px 12px;border:1px solid var(--rtg-border);border-radius:8px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $dd_diameters ) ); ?></textarea>
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
