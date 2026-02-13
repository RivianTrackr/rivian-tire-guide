<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

$settings = get_option( 'rtg_settings', array() );
$rows_per_page = $settings['rows_per_page'] ?? 12;
$cdn_prefix = $settings['cdn_prefix'] ?? '';
$compare_slug = $settings['compare_slug'] ?? 'tire-compare';

// Load dropdown options.
$dd_brands        = RTG_Admin::get_dropdown_options( 'brands' );
$dd_categories    = RTG_Admin::get_dropdown_options( 'categories' );
$dd_sizes         = RTG_Admin::get_dropdown_options( 'sizes' );
$dd_diameters     = RTG_Admin::get_dropdown_options( 'diameters' );
$dd_load_ranges   = RTG_Admin::get_dropdown_options( 'load_ranges' );
$dd_speed_ratings = RTG_Admin::get_dropdown_options( 'speed_ratings' );
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
