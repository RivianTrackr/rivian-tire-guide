<?php
/**
 * Tools: the utilities that are not part of day-to-day catalog work.
 *
 * CSV import and export, the social share image, and the JSON data feed
 * used to be spread across two menu items and the dashboard. They share
 * one page now, in tabs. The rendering method sets $rtg_tools_default_tab
 * so the old Share Image URL still opens on that tab.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$rtg_tools_default_tab = isset( $rtg_tools_default_tab ) ? $rtg_tools_default_tab : 'import';

// An import result is only ever shown on the import tab.
if ( ! empty( $_GET['message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice routing only.
    $rtg_tools_default_tab = 'import';
}
?>

<div class="rtg-wrap">

    <div class="rtg-page-header">
        <div class="rtg-page-heading">
            <h1 class="rtg-page-title">Tools</h1>
            <p class="rtg-page-subtitle">Move tire data in and out of the guide, and share it.</p>
        </div>
    </div>

    <div data-rtg-tabs data-default="<?php echo esc_attr( $rtg_tools_default_tab ); ?>">

        <nav class="rtg-tabs" aria-label="Tools">
            <button type="button" class="rtg-tab" data-tab="import">Import / Export</button>
            <button type="button" class="rtg-tab" data-tab="share">Share image</button>
            <button type="button" class="rtg-tab" data-tab="feed">Data feed</button>
        </nav>

        <div class="rtg-tab-panel" data-tab-panel="import">
            <?php require RTG_PLUGIN_DIR . 'admin/views/import-export.php'; ?>
        </div>

        <div class="rtg-tab-panel" data-tab-panel="share" hidden>
            <?php require RTG_PLUGIN_DIR . 'admin/views/share-image.php'; ?>
        </div>

        <div class="rtg-tab-panel" data-tab-panel="feed" hidden>
            <?php $feed_url = rest_url( 'rtg/v1/feed' ); ?>
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>JSON data feed</h2>
                    <p>A live, read-only copy of the tire catalog. Share this URL to give others access to the data. It updates on its own whenever a tire is added or edited.</p>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-input-row">
                        <input
                            type="text"
                            id="rtg-feed-url"
                            value="<?php echo esc_url( $feed_url ); ?>"
                            readonly
                            class="rtg-input is-code"
                            onclick="this.select();"
                            aria-label="Feed URL"
                        />
                        <button type="button" id="rtg-copy-feed-url" class="rtg-btn rtg-btn-primary" data-rtg-copy="#rtg-feed-url" data-rtg-copy-status="#rtg-feed-copy-status">Copy URL</button>
                        <a href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtg-btn rtg-btn-secondary">
                            <span class="dashicons dashicons-external"></span> Preview
                        </a>
                    </div>
                    <p id="rtg-feed-copy-status" class="rtg-inline-status is-success" style="display: none; margin-top: 8px;">
                        Copied to clipboard.
                    </p>
                </div>
            </div>
        </div>

    </div>

</div>
