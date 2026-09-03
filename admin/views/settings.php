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
$tire_review_slug = $settings['tire_review_slug'] ?? 'tire-review';

// Theme color defaults.
$default_colors = array(
    'accent'       => '#fba919',
    'accent_hover' => '#fba919',
    'bg_primary'   => '#16191e',
    'bg_card'      => '#16191e',
    'bg_input'     => '#3a3e45',
    'bg_deep'      => '#121418',
    'text_primary' => '#ece9e4',
    'text_light'   => '#ece9e4',
    'text_muted'   => '#ece9e4',
    'text_heading' => '#ece9e4',
    'border'       => '#3a3e45',
    'star_filled'  => '#fba919',
    'star_user'    => '#4ade80',
    'star_empty'   => '#2c2f34',
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
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="tire_review_slug">Tire Review Page Slug</label>
                    </div>
                    <p class="rtg-field-description">URL slug for the standalone tire review page. Default: <code>tire-review</code> (accessible at <code><?php echo esc_html( home_url( '/' . $tire_review_slug . '/' ) ); ?></code>)</p>
                    <input type="text" id="tire_review_slug" name="tire_review_slug" value="<?php echo esc_attr( $tire_review_slug ); ?>">
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="share_image">Share Image</label>
                    </div>
                    <p class="rtg-field-description">The preview image social cards show for tire pages and the guide (1200×630 works best). Leave empty for the default: <code><?php echo esc_html( RTG_Meta::DEFAULT_SHARE_IMAGE ); ?></code></p>
                    <input type="url" id="share_image" name="share_image" value="<?php echo esc_attr( $settings['share_image'] ?? '' ); ?>" class="rtg-input-wide" placeholder="https://…/og-image.jpg">
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

        <!-- Affiliate Link Domains -->
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Affiliate Link Domains</h2>
                <p>Domains used to identify affiliate links on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-affiliate-links' ) ); ?>">Affiliate Links</a> dashboard. One domain per line. Any tire link containing one of these domains will be classified as an affiliate link.</p>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-field-row" style="border-bottom:none;">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="rtg_affiliate_domains">Affiliate Network Domains</label>
                    </div>
                    <p class="rtg-field-description">Enter the base domain only (e.g. <code>anrdoezrs.net</code> or <code>tkqlhce.com</code>). Protocols and www prefixes are stripped automatically.</p>
                    <?php $aff_domains = RTG_Admin::get_affiliate_domains(); ?>
                    <textarea id="rtg_affiliate_domains" name="rtg_affiliate_domains" rows="10" class="rtg-input-wide" style="max-width:500px;font-size:14px;font-family:var(--rtg-font-mono);padding:10px 12px;border:1px solid var(--rtg-border);border-radius:8px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $aff_domains ) ); ?></textarea>
                </div>
            </div>
        </div>

        <!-- AI Tire Advisor -->
        <?php
        $ai_state        = RTG_Advisor::state();
        $ai_key_constant = RTG_Advisor::key_is_constant();
        $ai_has_key      = RTG_Advisor::has_key();
        ?>
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>AI Tire Advisor</h2>
                <p>"Help me choose" on the guide, "What owners say" on tire pages, and the plain-words paragraph on the compare page. Answers are grounded in the catalog: the model only ever picks from tires that fit, and every number it cites comes from the guide. Without a key, Help me choose runs on the guide's own rules and the other two stay off.</p>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="ai_enabled">Advisor</label>
                    </div>
                    <p class="rtg-field-description">Turn off to hide the "Help me choose" button and stop every model call.</p>
                    <label class="rtg-toggle">
                        <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" <?php checked( RTG_Advisor::is_enabled() ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Enable the advisor</span>
                    </label>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="ai_api_key">Anthropic API key</label>
                    </div>
                    <?php if ( $ai_key_constant ) : ?>
                        <p class="rtg-field-description">Set by the <code>RTG_ANTHROPIC_API_KEY</code> constant in <code>wp-config.php</code>; the field below is ignored while it is defined.</p>
                    <?php else : ?>
                        <p class="rtg-field-description">From <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>. Stored in this option; define <code>RTG_ANTHROPIC_API_KEY</code> in <code>wp-config.php</code> to keep it out of the database instead. <?php echo $ai_has_key ? '<strong>A key is saved.</strong> Leave the field empty to keep it.' : 'No key saved yet.'; ?></p>
                        <input type="password" id="ai_api_key" name="ai_api_key" value="" class="rtg-input-wide" autocomplete="off" placeholder="<?php echo $ai_has_key ? '••••••••••••' : 'sk-ant-…'; ?>">
                        <?php if ( $ai_has_key ) : ?>
                        <label style="display:block;margin-top:8px;"><input type="checkbox" name="ai_api_key_clear" value="1"> Clear the saved key</label>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="ai_model">Model</label>
                    </div>
                    <p class="rtg-field-description">Opus 5 writes the best advice. Sonnet 5 and Haiku 4.5 answer faster for less; the numbers it cites are the same either way, since they come from the catalog.</p>
                    <select id="ai_model" name="ai_model" class="rtg-input-small" style="width:auto;min-width:260px;">
                        <?php foreach ( RTG_Advisor::MODELS as $model_id => $model_label ) : ?>
                        <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( RTG_Advisor::model(), $model_id ); ?>><?php echo esc_html( $model_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="ai_rate_limit">Requests per visitor per minute</label>
                    </div>
                    <p class="rtg-field-description">A ceiling on "Help me choose" calls from one visitor, so a script cannot run up the bill. Answers to the same questions are cached for a day. Default: 10.</p>
                    <input type="number" id="ai_rate_limit" name="ai_rate_limit" value="<?php echo esc_attr( RTG_Advisor::rate_limit() ); ?>" min="1" max="60" step="1" class="rtg-input-small">
                </div>
                <div class="rtg-field-row" style="border-bottom:none;">
                    <div class="rtg-field-label-row">
                        <span class="rtg-field-label">Last call</span>
                    </div>
                    <?php if ( empty( $ai_state ) ) : ?>
                        <p class="rtg-field-description">No calls yet.</p>
                    <?php else : ?>
                        <p class="rtg-field-description">
                            <strong><?php echo 'ok' === ( $ai_state['status'] ?? '' ) ? 'Succeeded' : 'Failed'; ?></strong>
                            at <?php echo esc_html( $ai_state['time'] ?? '' ); ?>
                            on <?php echo esc_html( $ai_state['served_by'] ?? $ai_state['model'] ?? '' ); ?>.
                            <?php if ( 'ok' !== ( $ai_state['status'] ?? '' ) ) : ?>
                                <?php echo esc_html( $ai_state['message'] ?? '' ); ?>
                            <?php elseif ( ! empty( $ai_state['usage'] ) ) : ?>
                                <?php echo (int) $ai_state['usage']['input']; ?> tokens in (<?php echo (int) $ai_state['usage']['cache_read']; ?> from cache), <?php echo (int) $ai_state['usage']['output']; ?> out.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Analytics Settings -->
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Analytics</h2>
                <p>Settings for affiliate click tracking and search analytics. View analytics data on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-analytics' ) ); ?>">Analytics</a> dashboard.</p>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-field-row" style="border-bottom:none;">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="analytics_retention_days">Data Retention Period (days)</label>
                    </div>
                    <p class="rtg-field-description">Analytics events older than this many days are automatically deleted. Min: 7, Max: 365. Default: 90.</p>
                    <?php $retention_days = $settings['analytics_retention_days'] ?? 90; ?>
                    <input type="number" id="analytics_retention_days" name="analytics_retention_days" value="<?php echo esc_attr( $retention_days ); ?>" min="7" max="365" step="1" class="rtg-input-small">
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
