<?php
/**
 * Settings, in tabs.
 *
 * One form spans every tab; the panels are hidden rather than removed, so
 * Save writes all of them at once whichever tab is open. The open tab
 * rides in the URL hash so a reload lands where the admin was.
 */
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

$color_groups = array(
    'Accent' => array(
        'accent'       => 'Primary accent',
        'accent_hover' => 'Accent hover',
    ),
    'Backgrounds' => array(
        'bg_primary' => 'Page',
        'bg_card'    => 'Card',
        'bg_input'   => 'Input',
        'bg_deep'    => 'Deep',
        'border'     => 'Border / divider',
    ),
    'Text' => array(
        'text_primary' => 'Primary',
        'text_heading' => 'Heading',
        'text_light'   => 'Light',
        'text_muted'   => 'Muted',
    ),
    'Rating stars' => array(
        'star_filled' => 'Filled',
        'star_user'   => 'Your rating',
        'star_empty'  => 'Empty',
    ),
);

// Load dropdown options.
$dd_brands        = RTG_Admin::get_dropdown_options( 'brands' );
$dd_categories    = RTG_Admin::get_dropdown_options( 'categories' );
$dd_sizes         = RTG_Admin::get_dropdown_options( 'sizes' );
$dd_size_diameter_map = RTG_Admin::get_size_diameter_map();
$dd_load_ranges   = RTG_Admin::get_dropdown_options( 'load_ranges' );
$dd_speed_ratings = RTG_Admin::get_dropdown_options( 'speed_ratings' );
$dd_load_index_map = RTG_Admin::get_load_index_map();

$sd_lines = array();
foreach ( $dd_size_diameter_map as $size => $diam ) {
    $sd_lines[] = $size . ' = ' . $diam;
}
$li_lines = array();
foreach ( $dd_load_index_map as $idx => $lbs ) {
    $li_lines[] = $idx . ' = ' . $lbs;
}

$aff_domains = RTG_Admin::get_affiliate_domains();

$ai_state        = RTG_Advisor::state();
$ai_key_constant = RTG_Advisor::key_is_constant();
$ai_has_key      = RTG_Advisor::has_key();

$retention_days = $settings['analytics_retention_days'] ?? 90;
?>

<div class="rtg-wrap">

    <?php if ( $message === 'saved' ) : ?>
        <div class="rtg-notice rtg-notice-success">
            <span>Settings saved.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <div class="rtg-page-heading">
            <h1 class="rtg-page-title">Settings</h1>
            <p class="rtg-page-subtitle">How the guide looks and behaves for shoppers. Discovery and Roamer have their own settings on their pages.</p>
        </div>
    </div>

    <form method="post" data-rtg-tabs data-default="general">
        <?php wp_nonce_field( 'rtg_save_settings', 'rtg_settings_nonce' ); ?>
        <input type="hidden" name="rtg_save_settings" value="1">

        <nav class="rtg-tabs" aria-label="Settings sections">
            <button type="button" class="rtg-tab" data-tab="general">General</button>
            <button type="button" class="rtg-tab" data-tab="colors">Theme colors</button>
            <button type="button" class="rtg-tab" data-tab="options">Dropdown options</button>
            <button type="button" class="rtg-tab" data-tab="affiliate">Affiliate domains</button>
            <button type="button" class="rtg-tab" data-tab="advisor">AI advisor</button>
            <button type="button" class="rtg-tab" data-tab="analytics">Analytics</button>
        </nav>

        <!-- General -->
        <div class="rtg-tab-panel" data-tab-panel="general">
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Shortcode</h2>
                    <p>Add the tire guide to any page or post with this shortcode.</p>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-input-row">
                        <input type="text" id="rtg-shortcode" value="[rivian_tire_guide]" readonly class="is-code rtg-input-medium" onclick="this.select();" aria-label="Shortcode">
                        <button type="button" class="rtg-btn rtg-btn-secondary" data-rtg-copy="#rtg-shortcode" data-rtg-copy-status="#rtg-shortcode-copied">Copy</button>
                        <span id="rtg-shortcode-copied" class="rtg-inline-status is-success" style="display:none;">Copied</span>
                    </div>
                </div>
            </div>

            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Display</h2>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="rows_per_page">Tires per page</label>
                        </div>
                        <p class="rtg-field-description">Tire cards shown per page in the guide. Default 12.</p>
                        <input type="number" id="rows_per_page" name="rows_per_page" value="<?php echo esc_attr( $rows_per_page ); ?>" min="4" max="48" step="4" class="rtg-input-small">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="server_side_pagination">Server-side loading</label>
                        </div>
                        <p class="rtg-field-description">Fetch tire data as the visitor pages through it instead of embedding the whole catalog in the page. Recommended past 200 tires.</p>
                        <label class="rtg-toggle">
                            <input type="checkbox" id="server_side_pagination" name="server_side_pagination" value="1" <?php checked( ! empty( $settings['server_side_pagination'] ) ); ?>>
                            <span class="rtg-toggle-track"></span>
                            <span class="rtg-toggle-label">Load tires on demand</span>
                        </label>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="cdn_prefix">CDN image prefix</label>
                        </div>
                        <p class="rtg-field-description">Optional. A CDN URL prefix for image optimization. Leave blank to serve the original image URLs.</p>
                        <input type="text" id="cdn_prefix" name="cdn_prefix" value="<?php echo esc_attr( $cdn_prefix ); ?>" class="rtg-input-wide" placeholder="e.g. https://cdn.riviantrackr.com/spio/w_600+q_auto+ret_img+to_webp/">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="share_image">Share image</label>
                        </div>
                        <p class="rtg-field-description">The preview image social cards show for tire pages and the guide. 1200 by 630 works best. Leave empty for the default: <code><?php echo esc_html( RTG_Meta::DEFAULT_SHARE_IMAGE ); ?></code></p>
                        <input type="url" id="share_image" name="share_image" value="<?php echo esc_attr( $settings['share_image'] ?? '' ); ?>" class="rtg-input-wide" placeholder="https://…/og-image.jpg">
                    </div>
                </div>
            </div>

            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Page slugs</h2>
                    <p>Where the guide's companion pages live. Each shows the address it resolves to right now.</p>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-grid">
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="compare_slug">Compare page</label>
                            </div>
                            <p class="rtg-field-description"><code><?php echo esc_html( home_url( '/' . $compare_slug . '/' ) ); ?></code></p>
                            <input type="text" id="compare_slug" name="compare_slug" value="<?php echo esc_attr( $compare_slug ); ?>" class="is-code">
                        </div>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="user_reviews_slug">User reviews page</label>
                            </div>
                            <p class="rtg-field-description">Holds the <code>[rivian_user_reviews]</code> shortcode. <code><?php echo esc_html( home_url( '/' . $user_reviews_slug . '/' ) ); ?></code></p>
                            <input type="text" id="user_reviews_slug" name="user_reviews_slug" value="<?php echo esc_attr( $user_reviews_slug ); ?>" class="is-code">
                        </div>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label" for="tire_review_slug">Review a tire page</label>
                            </div>
                            <p class="rtg-field-description"><code><?php echo esc_html( home_url( '/' . $tire_review_slug . '/' ) ); ?></code></p>
                            <input type="text" id="tire_review_slug" name="tire_review_slug" value="<?php echo esc_attr( $tire_review_slug ); ?>" class="is-code">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Theme colors -->
        <div class="rtg-tab-panel" data-tab-panel="colors" hidden>
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Theme colors</h2>
                    <p>The guide's color scheme on the site. Pick with the swatch or type a hex code such as <code>#5ec095</code>.</p>
                </div>
                <?php foreach ( $color_groups as $group_label => $group_colors ) : ?>
                    <div class="rtg-card-body">
                        <p class="rtg-meta-label rtg-group-label"><?php echo esc_html( $group_label ); ?></p>
                        <div class="rtg-color-grid">
                            <?php foreach ( $group_colors as $key => $label ) : ?>
                                <div class="rtg-color-field">
                                    <label for="rtg_color_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
                                    <div class="rtg-color-field-controls">
                                        <input type="color" value="<?php echo esc_attr( $theme_colors[ $key ] ); ?>" aria-label="<?php echo esc_attr( $label ); ?> swatch">
                                        <input type="text" id="rtg_color_<?php echo esc_attr( $key ); ?>" name="rtg_colors[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $theme_colors[ $key ] ); ?>" maxlength="7" placeholder="#000000">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Dropdown options -->
        <div class="rtg-tab-panel" data-tab-panel="options" hidden>
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Dropdown options</h2>
                    <p>The choices offered when adding or editing a tire. One option per line. The brand list also decides which discovered tires count as a known brand.</p>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-edit-grid">
                        <div>
                            <div class="rtg-field-row">
                                <div class="rtg-field-label-row">
                                    <label class="rtg-field-label" for="rtg_dd_brands">Brands</label>
                                </div>
                                <textarea id="rtg_dd_brands" name="rtg_dd_brands" rows="8" class="rtg-input-wide"><?php echo esc_textarea( implode( "\n", $dd_brands ) ); ?></textarea>
                            </div>
                            <div class="rtg-field-row">
                                <div class="rtg-field-label-row">
                                    <label class="rtg-field-label" for="rtg_dd_categories">Categories</label>
                                </div>
                                <textarea id="rtg_dd_categories" name="rtg_dd_categories" rows="5" class="rtg-input-wide"><?php echo esc_textarea( implode( "\n", $dd_categories ) ); ?></textarea>
                            </div>
                            <div class="rtg-field-row">
                                <div class="rtg-field-label-row">
                                    <label class="rtg-field-label" for="rtg_dd_sizes">Sizes</label>
                                </div>
                                <textarea id="rtg_dd_sizes" name="rtg_dd_sizes" rows="4" class="rtg-input-wide is-code"><?php echo esc_textarea( implode( "\n", $dd_sizes ) ); ?></textarea>
                            </div>
                            <div class="rtg-field-row">
                                <div class="rtg-field-label-row">
                                    <label class="rtg-field-label" for="rtg_dd_load_ranges">Load ranges</label>
                                </div>
                                <textarea id="rtg_dd_load_ranges" name="rtg_dd_load_ranges" rows="4" class="rtg-input-wide"><?php echo esc_textarea( implode( "\n", $dd_load_ranges ) ); ?></textarea>
                            </div>
                            <div class="rtg-field-row">
                                <div class="rtg-field-label-row">
                                    <label class="rtg-field-label" for="rtg_dd_speed_ratings">Speed ratings</label>
                                </div>
                                <textarea id="rtg_dd_speed_ratings" name="rtg_dd_speed_ratings" rows="5" class="rtg-input-wide"><?php echo esc_textarea( implode( "\n", $dd_speed_ratings ) ); ?></textarea>
                            </div>
                        </div>
                        <div>
                            <div class="rtg-field-row">
                                <div class="rtg-field-label-row">
                                    <label class="rtg-field-label" for="rtg_dd_size_diameters">Size to tire diameter</label>
                                </div>
                                <p class="rtg-field-description">One <code>size = diameter</code> per line. The diameter fills in automatically when a size is picked on the tire form.</p>
                                <textarea id="rtg_dd_size_diameters" name="rtg_dd_size_diameters" rows="8" class="rtg-input-wide is-code"><?php echo esc_textarea( implode( "\n", $sd_lines ) ); ?></textarea>
                            </div>
                            <div class="rtg-field-row">
                                <div class="rtg-field-label-row">
                                    <label class="rtg-field-label" for="rtg_dd_load_indexes">Load index to max load</label>
                                </div>
                                <p class="rtg-field-description">One <code>index = max load in lb</code> per line. The max load fills in when a load index is picked.</p>
                                <textarea id="rtg_dd_load_indexes" name="rtg_dd_load_indexes" rows="14" class="rtg-input-wide is-code"><?php echo esc_textarea( implode( "\n", $li_lines ) ); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Affiliate domains -->
        <div class="rtg-tab-panel" data-tab-panel="affiliate" hidden>
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Affiliate link domains</h2>
                    <p>A tire link containing one of these domains counts as an affiliate link on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-affiliate-links' ) ); ?>">Affiliate Links</a> page. One domain per line.</p>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="rtg_affiliate_domains">Affiliate network domains</label>
                        </div>
                        <p class="rtg-field-description">The base domain only, such as <code>anrdoezrs.net</code> or <code>tkqlhce.com</code>. Protocols and www prefixes are stripped automatically.</p>
                        <textarea id="rtg_affiliate_domains" name="rtg_affiliate_domains" rows="12" class="is-code" spellcheck="false"><?php echo esc_textarea( implode( "\n", $aff_domains ) ); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI advisor -->
        <div class="rtg-tab-panel" data-tab-panel="advisor" hidden>
            <div class="rtg-card">
                <div class="rtg-card-header is-split">
                    <div>
                        <h2>AI tire advisor</h2>
                        <p>"Help me choose" on the guide, "What owners say" on tire pages, and the plain-words paragraph on the compare page. Answers are grounded in the catalog: the model only ever picks from tires that fit, and every number it cites comes from the guide. Without a key, Help me choose runs on the guide's own rules and the other two stay off.</p>
                    </div>
                    <?php if ( RTG_Advisor::is_enabled() && $ai_has_key ) : ?>
                        <span class="rtg-badge rtg-badge-success">On</span>
                    <?php elseif ( RTG_Advisor::is_enabled() ) : ?>
                        <span class="rtg-badge rtg-badge-warning">Rules only, no key</span>
                    <?php else : ?>
                        <span class="rtg-badge rtg-badge-muted">Off</span>
                    <?php endif; ?>
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
                            <?php if ( $ai_key_constant ) : ?>
                                <span class="rtg-badge rtg-badge-info">Set in wp-config.php</span>
                            <?php elseif ( $ai_has_key ) : ?>
                                <span class="rtg-badge rtg-badge-success">Saved</span>
                            <?php endif; ?>
                        </div>
                        <?php if ( $ai_key_constant ) : ?>
                            <p class="rtg-field-description">Set by the <code>RTG_ANTHROPIC_API_KEY</code> constant in <code>wp-config.php</code>. This field is ignored while it is defined.</p>
                        <?php else : ?>
                            <p class="rtg-field-description">From <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>. Stored in this option; define <code>RTG_ANTHROPIC_API_KEY</code> in <code>wp-config.php</code> to keep it out of the database instead. <?php echo $ai_has_key ? 'Leave the field empty to keep the saved key.' : 'No key saved yet.'; ?></p>
                            <input type="password" id="ai_api_key" name="ai_api_key" value="" class="rtg-input-wide" autocomplete="off" placeholder="<?php echo $ai_has_key ? '••••••••••••' : 'sk-ant-…'; ?>">
                            <?php if ( $ai_has_key ) : ?>
                                <div class="rtg-choice-list">
                                    <label class="rtg-choice"><input type="checkbox" name="ai_api_key_clear" value="1"> Clear the saved key</label>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="ai_model">Model</label>
                        </div>
                        <p class="rtg-field-description">Opus 5 writes the best advice. Sonnet 5 and Haiku 4.5 answer faster for less. The numbers cited are the same either way, since they come from the catalog.</p>
                        <select id="ai_model" name="ai_model" class="rtg-input-medium">
                            <?php foreach ( RTG_Advisor::MODELS as $model_id => $model_label ) : ?>
                            <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( RTG_Advisor::model(), $model_id ); ?>><?php echo esc_html( $model_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="ai_rate_limit">Requests per visitor per minute</label>
                        </div>
                        <p class="rtg-field-description">A ceiling on "Help me choose" calls from one visitor, so a script cannot run up the bill. Answers to the same questions are cached for a day. Default 10.</p>
                        <input type="number" id="ai_rate_limit" name="ai_rate_limit" value="<?php echo esc_attr( RTG_Advisor::rate_limit() ); ?>" min="1" max="60" step="1" class="rtg-input-small">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <span class="rtg-field-label">Last call</span>
                        </div>
                        <?php if ( empty( $ai_state ) ) : ?>
                            <p class="rtg-field-description">No calls yet.</p>
                        <?php else : ?>
                            <p class="rtg-field-description">
                                <?php if ( 'ok' === ( $ai_state['status'] ?? '' ) ) : ?>
                                    <span class="rtg-badge rtg-badge-success rtg-badge-sm">Succeeded</span>
                                <?php else : ?>
                                    <span class="rtg-badge rtg-badge-error rtg-badge-sm">Failed</span>
                                <?php endif; ?>
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
        </div>

        <!-- Analytics -->
        <div class="rtg-tab-panel" data-tab-panel="analytics" hidden>
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Analytics</h2>
                    <p>Affiliate click and search tracking. The numbers are on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-analytics' ) ); ?>">Analytics</a> page.</p>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="analytics_retention_days">Keep events for</label>
                        </div>
                        <p class="rtg-field-description">Analytics events older than this are deleted daily. Between 7 and 365 days. Default 90.</p>
                        <div class="rtg-field-inline">
                            <input type="number" id="analytics_retention_days" name="analytics_retention_days" value="<?php echo esc_attr( $retention_days ); ?>" min="7" max="365" step="1" class="rtg-input-small">
                            <span>days</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rtg-footer-actions is-sticky">
            <button type="submit" class="rtg-btn rtg-btn-primary">Save settings</button>
            <span class="rtg-footer-end">
                <span class="rtg-unsaved">Unsaved changes</span>
                <span class="rtg-muted">Saves every tab at once.</span>
            </span>
        </div>
    </form>

</div>
