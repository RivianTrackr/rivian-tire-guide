<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
if ( $message === 'saved' ) {
    echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
}

$settings = get_option( 'rtg_settings', array() );
$rows_per_page = $settings['rows_per_page'] ?? 12;
$cdn_prefix = $settings['cdn_prefix'] ?? '';
$compare_slug = $settings['compare_slug'] ?? 'tire-compare';
?>

<div class="wrap">
    <h1>Tire Guide Settings</h1>

    <form method="post">
        <?php wp_nonce_field( 'rtg_save_settings', 'rtg_settings_nonce' ); ?>
        <input type="hidden" name="rtg_save_settings" value="1">

        <table class="form-table">
            <tr>
                <th><label for="rows_per_page">Tires Per Page</label></th>
                <td>
                    <input type="number" id="rows_per_page" name="rows_per_page" value="<?php echo esc_attr( $rows_per_page ); ?>" min="4" max="48" step="4" class="small-text">
                    <p class="description">Number of tire cards shown per page on the frontend (default: 12).</p>
                </td>
            </tr>
            <tr>
                <th><label for="cdn_prefix">CDN Image Prefix</label></th>
                <td>
                    <input type="text" id="cdn_prefix" name="cdn_prefix" value="<?php echo esc_attr( $cdn_prefix ); ?>" class="large-text" placeholder="e.g. https://cdn.riviantrackr.com/spio/w_600+q_auto+ret_img+to_webp/">
                    <p class="description">Optional. CDN URL prefix for image optimization (leave blank to use original image URLs).</p>
                </td>
            </tr>
            <tr>
                <th><label for="compare_slug">Compare Page Slug</label></th>
                <td>
                    <input type="text" id="compare_slug" name="compare_slug" value="<?php echo esc_attr( $compare_slug ); ?>" class="regular-text">
                    <p class="description">URL slug for the comparison page. Default: <code>tire-compare</code> (accessible at <code><?php echo esc_html( home_url( '/' . $compare_slug . '/' ) ); ?></code>)</p>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Save Settings' ); ?>
    </form>

    <hr>
    <h2>Shortcode</h2>
    <p>Add the tire guide to any page or post using this shortcode:</p>
    <code style="display:inline-block;padding:8px 16px;background:#f0f0f1;border-radius:4px;font-size:14px;">[rivian_tire_guide]</code>
</div>
