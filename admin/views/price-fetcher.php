<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

// Get summary data.
global $wpdb;
$table = RTG_Database::tires_table_public();

$total_tires     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
$tires_with_link = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE link != ''" );
$tires_fetched   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE fetched_price > 0" );
$tires_failed    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE price_fetch_status = 'failed'" );

// Get all tires with their price fetch status.
$tires = $wpdb->get_results(
    "SELECT tire_id, brand, model, price, fetched_price, price_updated_at, price_fetch_status, link
     FROM {$table}
     WHERE link != ''
     ORDER BY price_fetch_status DESC, price_updated_at DESC",
    ARRAY_A
);

// Get next scheduled run.
$next_run = RTG_Price_Fetcher::get_next_run();

// Get fetch log.
$logs = RTG_Price_Fetcher::get_log();
?>

<div class="rtg-wrap">

    <?php if ( $message === 'prices_refreshed' ) : ?>
        <div class="rtg-notice rtg-notice-success">
            <span>
                Price fetch complete.
                <?php
                $updated = isset( $_GET['updated'] ) ? intval( $_GET['updated'] ) : 0;
                $failed  = isset( $_GET['failed'] ) ? intval( $_GET['failed'] ) : 0;
                $skipped = isset( $_GET['skipped'] ) ? intval( $_GET['skipped'] ) : 0;
                echo esc_html( "{$updated} updated, {$failed} failed, {$skipped} skipped." );
                ?>
            </span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php elseif ( $message === 'single_refreshed' ) : ?>
        <div class="rtg-notice rtg-notice-success">
            <span>Price fetched for <?php echo esc_html( sanitize_text_field( $_GET['tire'] ?? '' ) ); ?>.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php elseif ( $message === 'single_failed' ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span>Price fetch failed for <?php echo esc_html( sanitize_text_field( $_GET['tire'] ?? '' ) ); ?>: <?php echo esc_html( sanitize_text_field( $_GET['error'] ?? 'Unknown error' ) ); ?></span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Price Fetcher</h1>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline;">
            <?php wp_nonce_field( 'rtg_refresh_prices', 'rtg_price_nonce' ); ?>
            <input type="hidden" name="rtg_refresh_all_prices" value="1">
            <button type="submit" class="rtg-page-title-action" onclick="this.textContent='Fetching...'; this.disabled=true; this.form.submit();">
                Refresh All Prices
            </button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="rtg-card" style="text-align: center;">
            <div class="rtg-card-body" style="padding: 20px;">
                <div style="font-size: 32px; font-weight: 700; color: var(--rtg-accent, #5ec095);"><?php echo esc_html( $tires_with_link ); ?></div>
                <div style="font-size: 13px; color: var(--rtg-text-muted, #86868b); margin-top: 4px;">Tires with Links</div>
            </div>
        </div>
        <div class="rtg-card" style="text-align: center;">
            <div class="rtg-card-body" style="padding: 20px;">
                <div style="font-size: 32px; font-weight: 700; color: #34d399;"><?php echo esc_html( $tires_fetched ); ?></div>
                <div style="font-size: 13px; color: var(--rtg-text-muted, #86868b); margin-top: 4px;">Prices Fetched</div>
            </div>
        </div>
        <div class="rtg-card" style="text-align: center;">
            <div class="rtg-card-body" style="padding: 20px;">
                <div style="font-size: 32px; font-weight: 700; color: #f87171;"><?php echo esc_html( $tires_failed ); ?></div>
                <div style="font-size: 13px; color: var(--rtg-text-muted, #86868b); margin-top: 4px;">Failed</div>
            </div>
        </div>
        <div class="rtg-card" style="text-align: center;">
            <div class="rtg-card-body" style="padding: 20px;">
                <div style="font-size: 13px; font-weight: 600; color: var(--rtg-text-primary, #1d1d1f);">
                    <?php
                    if ( $next_run ) {
                        $diff = $next_run - time();
                        if ( $diff > 0 ) {
                            $days  = floor( $diff / DAY_IN_SECONDS );
                            $hours = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
                            echo esc_html( $days > 0 ? "{$days}d {$hours}h" : "{$hours}h" );
                        } else {
                            echo 'Pending...';
                        }
                    } else {
                        echo 'Not scheduled';
                    }
                    ?>
                </div>
                <div style="font-size: 13px; color: var(--rtg-text-muted, #86868b); margin-top: 4px;">Next Auto-Run</div>
            </div>
        </div>
    </div>

    <!-- How It Works -->
    <div class="rtg-card" style="margin-bottom: 24px;">
        <div class="rtg-card-header">
            <h2>How It Works</h2>
        </div>
        <div class="rtg-card-body">
            <p style="margin: 0 0 8px; color: var(--rtg-text-muted, #86868b); font-size: 14px;">
                The price fetcher visits each tire's affiliate link and attempts to extract the current price from the page.
                It checks structured data (JSON-LD, meta tags) and known retailer page patterns. Prices are updated automatically once per week via WP-Cron.
            </p>
            <ul style="margin: 0; padding-left: 20px; color: var(--rtg-text-muted, #86868b); font-size: 14px;">
                <li><strong>Fetched price</strong> takes priority over the manually set fallback price.</li>
                <li>If a fetch fails, the <strong>fallback price</strong> (set in the tire editor) is used instead.</li>
                <li>Some sites (especially Amazon) may block automated requests — expect some failures.</li>
                <li>Sites that render prices with JavaScript only may not yield results.</li>
            </ul>
        </div>
    </div>

    <!-- Tire Price Status Table -->
    <div class="rtg-card">
        <div class="rtg-card-header">
            <h2>Tire Price Status</h2>
        </div>

        <div class="rtg-table-wrapper">
            <table class="rtg-table">
                <thead>
                    <tr>
                        <th>Tire ID</th>
                        <th>Brand / Model</th>
                        <th>Fallback Price</th>
                        <th>Fetched Price</th>
                        <th>Status</th>
                        <th>Last Fetched</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $tires ) ) : ?>
                        <tr>
                            <td colspan="7">
                                <div class="rtg-empty-state">
                                    <span class="dashicons dashicons-admin-links"></span>
                                    <h3>No tires with affiliate links</h3>
                                    <p>Add affiliate links to your tires to enable price fetching.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $tires as $tire ) :
                            $status = $tire['price_fetch_status'];
                            $fetched = floatval( $tire['fetched_price'] );
                            $manual  = floatval( $tire['price'] );

                            if ( $status === 'success' && $fetched > 0 ) {
                                $status_class = 'rtg-badge-success';
                                $status_label = 'Success';
                            } elseif ( $status === 'failed' ) {
                                $status_class = 'rtg-badge-error';
                                $status_label = 'Failed';
                            } else {
                                $status_class = 'rtg-badge-info';
                                $status_label = 'Pending';
                            }
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&id=' . $tire['tire_id'] ) ); ?>">
                                        <?php // tire_id column isn't the DB id — we need a lookup ?>
                                    </a>
                                    <strong><?php echo esc_html( $tire['tire_id'] ); ?></strong>
                                </td>
                                <td><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></td>
                                <td>$<?php echo esc_html( number_format( $manual, 2 ) ); ?></td>
                                <td>
                                    <?php if ( $fetched > 0 ) : ?>
                                        <strong style="color: #34d399;">$<?php echo esc_html( number_format( $fetched, 2 ) ); ?></strong>
                                    <?php else : ?>
                                        <span style="color: var(--rtg-text-muted, #86868b);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="rtg-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                                </td>
                                <td>
                                    <?php if ( ! empty( $tire['price_updated_at'] ) ) : ?>
                                        <span title="<?php echo esc_attr( $tire['price_updated_at'] ); ?>">
                                            <?php echo esc_html( human_time_diff( strtotime( $tire['price_updated_at'] ), current_time( 'timestamp' ) ) ); ?> ago
                                        </span>
                                    <?php else : ?>
                                        <span style="color: var(--rtg-text-muted, #86868b);">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-prices&action=refresh_single&tire_id=' . urlencode( $tire['tire_id'] ) ), 'rtg_refresh_single_' . $tire['tire_id'] ) ); ?>"
                                       class="rtg-btn rtg-btn-secondary" style="font-size: 12px; padding: 4px 10px;">
                                        Refresh
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Fetch Log -->
    <?php if ( ! empty( $logs ) ) : ?>
    <div class="rtg-card" style="margin-top: 24px;">
        <div class="rtg-card-header">
            <h2>Recent Fetch Runs</h2>
        </div>
        <div class="rtg-table-wrapper">
            <table class="rtg-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Updated</th>
                        <th>Failed</th>
                        <th>Skipped</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td><?php echo esc_html( $log['run_at'] ?? '—' ); ?></td>
                            <td style="color: #34d399; font-weight: 600;"><?php echo esc_html( $log['updated'] ?? 0 ); ?></td>
                            <td style="color: #f87171; font-weight: 600;"><?php echo esc_html( $log['failed'] ?? 0 ); ?></td>
                            <td style="color: var(--rtg-text-muted, #86868b);"><?php echo esc_html( $log['skipped'] ?? 0 ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
