<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'rtg_settings', array() );

// Handle settings save.
if ( isset( $_POST['rtg_catalog_settings_save'] ) ) {
    check_admin_referer( 'rtg_catalog_settings', 'rtg_catalog_settings_nonce' );

    $settings['catalog_sync_enabled']   = ! empty( $_POST['catalog_sync_enabled'] );
    $settings['catalog_notify_enabled'] = ! empty( $_POST['catalog_notify_enabled'] );
    $settings['health_alerts_enabled']  = ! empty( $_POST['health_alerts_enabled'] );
    $settings['stale_price_report_enabled'] = ! empty( $_POST['stale_price_report_enabled'] );

    // --- CJ credentials and query ---
    $settings['cj_enabled']     = ! empty( $_POST['cj_enabled'] );
    $settings['cj_company_id']  = preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['cj_company_id'] ?? '' ) );
    $settings['cj_advertisers'] = sanitize_textarea_field( wp_unslash( $_POST['cj_advertisers'] ?? '' ) );
    $settings['cj_website_id']  = preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['cj_website_id'] ?? '' ) );
    $settings['cj_limit']        = max( 1, min( 1000, intval( $_POST['cj_limit'] ?? RTG_Catalog_Source_CJ::DEFAULT_LIMIT ) ) );
    $settings['cj_sweep_budget'] = max( 15, min( 600, intval( $_POST['cj_sweep_budget'] ?? RTG_Catalog_Source_CJ::SWEEP_BUDGET ) ) );
    $settings['cj_max_pages']    = max( 1, min( 50, intval( $_POST['cj_max_pages'] ?? RTG_Catalog_Source_CJ::DEFAULT_MAX_PAGES ) ) );
    $settings['catalog_run_budget']  = max( 30, min( 900, intval( $_POST['catalog_run_budget'] ?? RTG_Catalog_Sync::RUN_BUDGET ) ) );

    // The query document is GraphQL, so it must not be run through a sanitizer
    // that mangles braces or quotes; it is never output unescaped.
    $settings['cj_query'] = trim( (string) wp_unslash( $_POST['cj_query'] ?? '' ) );

    // The token field renders empty, so an empty submission means "leave it
    // alone" rather than "clear it" — otherwise every unrelated settings save
    // would wipe the credential. Clearing is explicit, via the checkbox.
    $posted_pat = trim( (string) wp_unslash( $_POST['cj_pat'] ?? '' ) );
    if ( ! empty( $_POST['cj_pat_clear'] ) ) {
        $settings['cj_pat'] = '';
    } elseif ( '' !== $posted_pat ) {
        $settings['cj_pat'] = $posted_pat;
    }

    // Clamp to the range the load index table actually covers, so a typo can't
    // silently disqualify every tire or admit every tire.
    $posted_index = intval( $_POST['catalog_min_load_index'] ?? RTG_Tire_Qualifier::DEFAULT_MIN_LOAD_INDEX );
    $settings['catalog_min_load_index'] = max( 100, min( 126, $posted_index ) );

    // Per-vehicle load index floors. A blank field means "use the built-in
    // figure for this platform" rather than "no minimum", so it is dropped
    // instead of being stored as zero.
    $posted_minimums = isset( $_POST['catalog_vehicle_min_load_index'] ) && is_array( $_POST['catalog_vehicle_min_load_index'] )
        ? wp_unslash( $_POST['catalog_vehicle_min_load_index'] )
        : array();

    $vehicle_minimums = array();
    foreach ( $posted_minimums as $vehicle => $value ) {
        $value = intval( $value );
        if ( $value > 0 ) {
            $vehicle_minimums[ sanitize_text_field( $vehicle ) ] = max( 100, min( 126, $value ) );
        }
    }
    $settings['catalog_vehicle_min_load_index'] = $vehicle_minimums;

    $settings['price_sync_enabled']    = ! empty( $_POST['price_sync_enabled'] );
    $settings['link_sync_enabled']     = ! empty( $_POST['link_sync_enabled'] );
    $settings['price_sync_max_change'] = max( 1, min( 100, intval( $_POST['price_sync_max_change'] ?? 50 ) ) );

    $posted_policy = sanitize_text_field( wp_unslash( $_POST['catalog_brand_policy'] ?? '' ) );
    $settings['catalog_brand_policy'] = in_array( $posted_policy, array(
        RTG_Tire_Qualifier::BRAND_POLICY_OFF,
        RTG_Tire_Qualifier::BRAND_POLICY_WARN,
        RTG_Tire_Qualifier::BRAND_POLICY_REJECT,
    ), true ) ? $posted_policy : RTG_Tire_Qualifier::DEFAULT_BRAND_POLICY;

    update_option( 'rtg_settings', $settings );
    echo '<div class="notice notice-success is-dismissible"><p>Tire Discovery settings saved.</p></div>';
}

$sync_enabled   = $settings['catalog_sync_enabled'] ?? true;
$notify_enabled = $settings['catalog_notify_enabled'] ?? true;
$health_alerts  = $settings['health_alerts_enabled'] ?? true;
$stale_price_report = $settings['stale_price_report_enabled'] ?? true;
$min_load_index = isset( $settings['catalog_min_load_index'] )
    ? intval( $settings['catalog_min_load_index'] )
    : RTG_Tire_Qualifier::DEFAULT_MIN_LOAD_INDEX;

// Retailer coverage: which guide tires a retailer actually carries. Tires with
// no match are expected while affiliate links are still being filled in, so
// they are listed plainly rather than treated as a fault.
$retailer_coverage = RTG_Candidates::get_retailer_coverage();
$guide_tires       = RTG_Database::get_all_tires();

$covered_tires   = array();
$uncovered_tires = array();
foreach ( $guide_tires as $guide_tire ) {
    if ( ! empty( $retailer_coverage[ $guide_tire['tire_id'] ] ) ) {
        $covered_tires[] = $guide_tire;
    } else {
        $uncovered_tires[] = $guide_tire;
    }
}

// "No retailer match" covers several different situations and only one of
// them is fixable, so each uncovered tire is asked why rather than listed flat.
$coverage_reasons = RTG_Coverage::diagnose( $uncovered_tires );
$coverage_summary = RTG_Coverage::summarize( $coverage_reasons );

$price_sync_enabled    = $settings['price_sync_enabled'] ?? true;
$link_sync_enabled     = $settings['link_sync_enabled'] ?? true;
$cj_website_id         = $settings['cj_website_id'] ?? '';
$price_sync_max_change = intval( $settings['price_sync_max_change'] ?? 50 );
$price_results         = RTG_Price_Sync::get_results();

$vehicle_size_map = RTG_Database::get_vehicle_size_map();
$vehicle_minimums = RTG_Tire_Qualifier::get_vehicle_minimums();
$vehicle_counts   = RTG_Candidates::get_vehicle_counts( $status_filter );

$brand_policy = isset( $settings['catalog_brand_policy'] )
    ? (string) $settings['catalog_brand_policy']
    : RTG_Tire_Qualifier::DEFAULT_BRAND_POLICY;
$covered_brands = RTG_Admin::get_dropdown_options( 'brands' );

$cj_enabled      = $settings['cj_enabled'] ?? true;
$cj_company_id   = RTG_Catalog_Source_CJ::get_company_id();
$cj_advertisers  = $settings['cj_advertisers'] ?? '';
$cj_limit        = intval( $settings['cj_limit'] ?? RTG_Catalog_Source_CJ::DEFAULT_LIMIT );
$cj_sweep_budget = intval( $settings['cj_sweep_budget'] ?? RTG_Catalog_Source_CJ::SWEEP_BUDGET );
$cj_max_pages    = intval( $settings['cj_max_pages'] ?? RTG_Catalog_Source_CJ::DEFAULT_MAX_PAGES );
$catalog_run_budget  = intval( $settings['catalog_run_budget'] ?? RTG_Catalog_Sync::RUN_BUDGET );
$cj_query        = $settings['cj_query'] ?? '';
$cj_has_pat      = '' !== RTG_Catalog_Source_CJ::get_pat();
$cj_pat_constant = RTG_Catalog_Source_CJ::pat_is_constant();
$cj_configured   = RTG_Catalog_Source_CJ::is_configured();

// Default advertiser list, shown as the placeholder so the expected shape is
// obvious without pre-filling the field.
$cj_advertiser_placeholder = '';
foreach ( RTG_Catalog_Source_CJ::DEFAULT_ADVERTISERS as $adv_id => $adv_name ) {
    $cj_advertiser_placeholder .= $adv_id . '|' . $adv_name . "\n";
}

$stats  = RTG_Catalog_Sync::get_stats();
$counts = RTG_Candidates::get_counts();

$status_filter = isset( $_GET['candidate_status'] )
    ? sanitize_text_field( wp_unslash( $_GET['candidate_status'] ) )
    : RTG_Candidates::STATUS_NEW;
$size_filter = isset( $_GET['candidate_size'] )
    ? sanitize_text_field( wp_unslash( $_GET['candidate_size'] ) )
    : '';
$vehicle_filter = isset( $_GET['candidate_vehicle'] )
    ? sanitize_text_field( wp_unslash( $_GET['candidate_vehicle'] ) )
    : '';
$brand_filter = isset( $_GET['candidate_brand'] )
    ? sanitize_text_field( wp_unslash( $_GET['candidate_brand'] ) )
    : '';

$candidates = RTG_Candidates::query( array(
    'status'  => $status_filter,
    'size'    => $size_filter,
    'vehicle' => $vehicle_filter,
    'brand'   => $brand_filter,
) );

// Volume in the queue clusters by brand — a page of one budget brand is one
// decision, not sixty — so brands come with counts, and brands outside the
// curated list are tallied toward the policy hint below.
$brand_counts    = RTG_Candidates::get_brand_counts( $status_filter );
$curated_brands  = array();
foreach ( RTG_Admin::get_dropdown_options( 'brands' ) as $curated ) {
    $curated_brands[ RTG_Tire_Qualifier::normalize_brand( $curated ) ] = true;
}
$uncovered_brand_total = 0;
foreach ( $brand_counts as $brand_name => $brand_total ) {
    if ( ! isset( $curated_brands[ RTG_Tire_Qualifier::normalize_brand( $brand_name ) ] ) ) {
        $uncovered_brand_total += $brand_total;
    }
}

$dd_sizes = RTG_Admin::get_dropdown_options( 'sizes' );

$next_run = wp_next_scheduled( RTG_Catalog_Sync::CRON_HOOK );
?>

<div class="rtg-wrap">

    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Tire Discovery</h1>
    </div>

    <p style="margin:0 0 20px;color:var(--rtg-text-muted);max-width:820px;">
        Watches affiliate catalogs for tires in Rivian fitments that aren't in the guide yet, so new
        arrivals surface on their own instead of waiting on a manual search. Every product seen is
        remembered — dismiss one and it won't come back.
    </p>

    <!-- Counts -->
    <div class="rtg-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" style="color: var(--rtg-success);"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_NEW ] ); ?></div>
            <div class="rtg-stat-label">Awaiting Review</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" style="color: var(--rtg-warning-text);"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_REJECTED ] ); ?></div>
            <div class="rtg-stat-label">Near Misses</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_EXISTING ] ); ?></div>
            <div class="rtg-stat-label">Already in Guide</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_IMPORTED ] ); ?></div>
            <div class="rtg-stat-label">Added from Queue</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" style="color: var(--rtg-text-muted);"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_DISMISSED ] ); ?></div>
            <div class="rtg-stat-label">Dismissed</div>
        </div>
    </div>

    <!-- Sync status -->
    <div class="rtg-card" style="margin-bottom:20px;">
        <div class="rtg-card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h2>Discovery Status</h2>
            <button type="button" id="rtg-catalog-sync-btn" class="rtg-btn rtg-btn-primary">Run Discovery Now</button>
        </div>
        <div class="rtg-card-body">
            <div id="rtg-catalog-sync-status" style="display:none;margin-bottom:12px;"></div>

            <?php if ( $stats && isset( $stats['status'] ) ) : ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;">
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Status</strong><br>
                        <span style="font-size:18px;font-weight:600;color:<?php echo 'success' === $stats['status'] ? 'var(--rtg-success)' : 'var(--rtg-error)'; ?>;">
                            <?php echo esc_html( ucfirst( $stats['status'] ) ); ?>
                        </span>
                    </div>
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Last Run</strong><br>
                        <?php
                        $run_time = $stats['time'] ?? '';
                        $relative = $run_time
                            ? human_time_diff( strtotime( $run_time ), current_time( 'timestamp' ) ) . ' ago'
                            : 'N/A';
                        ?>
                        <span style="font-size:18px;font-weight:600;" title="<?php echo esc_attr( $run_time ); ?>"><?php echo esc_html( $relative ); ?></span>
                    </div>
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Products Seen</strong><br>
                        <span style="font-size:18px;font-weight:600;"><?php echo esc_html( intval( $stats['fetched'] ?? 0 ) ); ?></span>
                    </div>
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Newly Surfaced</strong><br>
                        <span style="font-size:18px;font-weight:600;color:var(--rtg-success);"><?php echo esc_html( intval( $stats['newly_surfaced'] ?? 0 ) ); ?></span>
                    </div>
                    <div>
                        <strong style="color:var(--rtg-text-muted);font-size:12px;text-transform:uppercase;">Next Run</strong><br>
                        <span style="font-size:18px;font-weight:600;">
                            <?php echo $next_run ? esc_html( 'in ' . human_time_diff( time(), $next_run ) ) : 'Not scheduled'; ?>
                        </span>
                    </div>
                </div>

                <?php
                $sweep_coverage = array();
                foreach ( $stats['sources'] ?? array() as $source_stat ) {
                    foreach ( $source_stat['coverage'] ?? array() as $cov_size => $cov ) {
                        $sweep_coverage[ $cov_size ] = $cov;
                    }
                }
                ?>
                <?php if ( ! empty( $sweep_coverage ) ) : ?>
                    <details style="margin-top:14px;">
                        <summary style="cursor:pointer;font-weight:600;">
                            Fitment coverage &mdash; how much of each size's match set has been read
                        </summary>
                        <p class="description" style="max-width:860px;margin:8px 0;">
                            This is the number that decides whether "no retailer is carrying it" can be believed. A
                            fitment read completely means the guide's tires in that size either arrived or genuinely
                            are not in the feed. A fitment read partially means neither conclusion is available yet.
                            Each run resumes where the last stopped.
                            <br><br>
                            <strong>Read the Distinct column first.</strong> Records read counts what came back;
                            distinct counts what was new. If distinct is far lower, the pages overlapped &mdash; the
                            sweep re-read the same products rather than going deeper &mdash; and "complete" describes
                            a re-read, not coverage. Nothing else on this page can be trusted while that is true.
                        </p>
                        <table class="rtg-table" style="margin-top:8px;">
                            <thead><tr><th>Size</th><th>Read</th><th>Distinct</th><th>Matches</th><th>Coverage</th></tr></thead>
                            <tbody>
                            <?php foreach ( $sweep_coverage as $cov_size => $cov ) : ?>
                                <?php
                                $cov_total = $cov['total'];
                                $cov_read  = intval( $cov['received'] );
                                $cov_pct   = ( null !== $cov_total && $cov_total > 0 )
                                    ? min( 100, round( ( $cov_read / $cov_total ) * 100 ) )
                                    : null;
                                ?>
                                <tr>
                                    <td style="font-family:var(--rtg-font-mono, monospace);"><?php echo esc_html( $cov_size ); ?></td>
                                    <td><?php echo esc_html( number_format( $cov_read ) ); ?></td>
                                    <td>
                                        <?php if ( ! isset( $cov['unique'] ) ) : ?>
                                            &mdash;
                                        <?php else : ?>
                                            <?php
                                            // Far fewer distinct products than records read means the
                                            // pages overlapped, so "complete" describes a re-read rather
                                            // than a deeper one.
                                            $cov_unique = intval( $cov['unique'] );
                                            $cov_thin   = $cov_read > 0 && $cov_unique < ( $cov_read * 0.9 );
                                            ?>
                                            <span<?php echo $cov_thin ? ' style="color:var(--rtg-error);font-weight:600;"' : ''; ?>>
                                                <?php echo esc_html( number_format( $cov_unique ) ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo null === $cov_total ? '&mdash;' : esc_html( number_format( $cov_total ) ); ?></td>
                                    <td>
                                        <?php if ( null === $cov_pct ) : ?>
                                            &mdash;
                                        <?php elseif ( $cov_pct >= 100 ) : ?>
                                            <span style="color:var(--rtg-success);font-weight:600;">complete</span>
                                        <?php else : ?>
                                            <span style="color:var(--rtg-text-muted);"><?php echo esc_html( $cov_pct ); ?>%</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>

                <?php if ( ! empty( $stats['elapsed'] ) ) : ?>
                    <p class="description" style="margin:12px 0 0;">
                        The whole run took
                        <?php echo esc_html( number_format( (float) $stats['elapsed'], 1 ) ); ?>s
                        of its <?php echo esc_html( intval( $catalog_run_budget ) ); ?>s budget.
                    </p>
                <?php endif; ?>

                <?php
                $pruned_total = intval( $stats['pruned']['off_fitment'] ?? 0 ) + intval( $stats['pruned']['stale'] ?? 0 );
                ?>
                <?php if ( $pruned_total > 0 ) : ?>
                    <p class="description" style="margin:10px 0 0;">
                        Housekeeping: <?php echo esc_html( number_format( $pruned_total ) ); ?> near miss(es)
                        deleted &mdash; <?php echo esc_html( number_format( intval( $stats['pruned']['off_fitment'] ?? 0 ) ) ); ?>
                        in fitments the guide doesn't stock,
                        <?php echo esc_html( number_format( intval( $stats['pruned']['stale'] ?? 0 ) ) ); ?>
                        unseen for 60+ days. A pruned product that reappears is simply re-filed by the next sweep.
                    </p>
                <?php endif; ?>

                <?php if ( ! empty( $stats['errors'] ) ) : ?>
                    <div class="rtg-notice rtg-notice-warning" style="margin-top:16px;">
                        <span>
                            <?php foreach ( $stats['errors'] as $error ) : ?>
                                <strong><?php echo esc_html( $error['source'] ); ?>:</strong> <?php echo esc_html( $error['message'] ); ?><br>
                            <?php endforeach; ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <p style="color:var(--rtg-text-muted);margin:0;">Discovery hasn't run yet. Use <strong>Run Discovery Now</strong> to try it against the configured source.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter tabs -->
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:16px;">
        <?php
        $tabs = array(
            RTG_Candidates::STATUS_NEW       => 'Awaiting Review (' . $counts[ RTG_Candidates::STATUS_NEW ] . ')',
            RTG_Candidates::STATUS_REJECTED  => 'Near Misses (' . $counts[ RTG_Candidates::STATUS_REJECTED ] . ')',
            RTG_Candidates::STATUS_EXISTING  => 'Already in Guide (' . $counts[ RTG_Candidates::STATUS_EXISTING ] . ')',
            RTG_Candidates::STATUS_DISMISSED => 'Dismissed (' . $counts[ RTG_Candidates::STATUS_DISMISSED ] . ')',
            RTG_Candidates::STATUS_IMPORTED  => 'Added (' . $counts[ RTG_Candidates::STATUS_IMPORTED ] . ')',
        );
        foreach ( $tabs as $key => $label ) :
            $url = add_query_arg(
                array(
                    'page'             => 'rtg-tire-discovery',
                    'candidate_status'  => $key,
                    'candidate_size'    => $size_filter,
                    'candidate_vehicle' => $vehicle_filter,
                ),
                admin_url( 'admin.php' )
            );
            $class = $status_filter === $key ? 'rtg-btn rtg-btn-primary' : 'rtg-btn rtg-btn-secondary';
        ?>
            <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>" style="text-decoration:none;"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" style="margin-bottom:20px;">
        <input type="hidden" name="page" value="rtg-tire-discovery">
        <input type="hidden" name="candidate_status" value="<?php echo esc_attr( $status_filter ); ?>">
        <div class="rtg-search-box" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <?php if ( ! empty( $vehicle_size_map ) ) : ?>
                <label for="candidate_vehicle" style="font-size:13px;color:var(--rtg-text-muted);">Vehicle</label>
                <select name="candidate_vehicle" id="candidate_vehicle" class="rtg-select">
                    <option value="">All vehicles</option>
                    <?php foreach ( array_keys( $vehicle_size_map ) as $vehicle_option ) : ?>
                        <option value="<?php echo esc_attr( $vehicle_option ); ?>" <?php selected( $vehicle_filter, $vehicle_option ); ?>>
                            <?php
                            echo esc_html( $vehicle_option );
                            if ( isset( $vehicle_counts[ $vehicle_option ] ) ) {
                                echo ' (' . intval( $vehicle_counts[ $vehicle_option ] ) . ')';
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label for="candidate_brand" style="font-size:13px;color:var(--rtg-text-muted);">Brand</label>
            <select name="candidate_brand" id="candidate_brand" class="rtg-select">
                <option value="">All brands</option>
                <?php foreach ( $brand_counts as $brand_option => $brand_total ) : ?>
                    <?php if ( '' === $brand_option ) { continue; } ?>
                    <option value="<?php echo esc_attr( $brand_option ); ?>" <?php selected( $brand_filter, $brand_option ); ?>>
                        <?php echo esc_html( $brand_option . ' (' . $brand_total . ')' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="candidate_size" style="font-size:13px;color:var(--rtg-text-muted);">Size</label>
            <select name="candidate_size" id="candidate_size" class="rtg-select">
                <option value="">All sizes</option>
                <?php foreach ( $dd_sizes as $size_option ) : ?>
                    <option value="<?php echo esc_attr( $size_option ); ?>" <?php selected( $size_filter, $size_option ); ?>><?php echo esc_html( $size_option ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rtg-btn rtg-btn-secondary">Filter</button>

            <?php if ( in_array( $status_filter, array( RTG_Candidates::STATUS_NEW, RTG_Candidates::STATUS_DISMISSED ), true ) && ! empty( $candidates ) ) : ?>
                <button type="button" id="rtg-bulk-candidates" class="rtg-btn rtg-btn-danger"
                    data-status="<?php echo esc_attr( $status_filter ); ?>"
                    data-brand="<?php echo esc_attr( $brand_filter ); ?>"
                    data-size="<?php echo esc_attr( $size_filter ); ?>"
                    data-vehicle="<?php echo esc_attr( $vehicle_filter ); ?>"
                    data-to="<?php echo esc_attr( RTG_Candidates::STATUS_NEW === $status_filter ? RTG_Candidates::STATUS_DISMISSED : RTG_Candidates::STATUS_NEW ); ?>">
                    <?php echo RTG_Candidates::STATUS_NEW === $status_filter ? 'Dismiss everything this filter matches' : 'Restore everything this filter matches'; ?>
                </button>
            <?php endif; ?>

            <?php if ( '' !== $vehicle_filter ) : ?>
                <span style="font-size:12px;color:var(--rtg-text-muted);">
                    Showing tires legal on <strong><?php echo esc_html( $vehicle_filter ); ?></strong>
                    (size <em>and</em> load index
                    <?php echo isset( $vehicle_minimums[ $vehicle_filter ] ) ? '&ge; ' . intval( $vehicle_minimums[ $vehicle_filter ] ) : ''; ?>).
                    A tire legal on more than one platform appears under each.
                </span>
            <?php endif; ?>
        </div>
    </form>

    <?php if ( RTG_Candidates::STATUS_NEW === $status_filter && $uncovered_brand_total > 0 ) : ?>
        <div class="rtg-notice rtg-notice-info" style="margin-bottom:16px;">
            <span>
                <strong><?php echo esc_html( number_format( $uncovered_brand_total ) ); ?></strong> of the
                <?php echo esc_html( number_format( $counts[ RTG_Candidates::STATUS_NEW ] ) ); ?> tire(s)
                awaiting review are from brands outside your curated list. Setting the
                <strong>brand policy</strong> below to <em>reject</em> files those as near misses automatically
                on the next run — the brand filter above shows who they are, and the bulk button clears any
                brand in one click either way.
            </span>
        </div>
    <?php endif; ?>

    <!-- Retailer coverage and price refresh -->
    <div class="rtg-card" style="margin-bottom:20px;">
        <div class="rtg-card-header">
            <h2>Retailer Coverage &amp; Prices</h2>
        </div>
        <div class="rtg-card-body">
            <div class="rtg-stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:16px;">
                <div class="rtg-stat-card">
                    <div class="rtg-stat-value" style="color:var(--rtg-success);"><?php echo count( $covered_tires ); ?></div>
                    <div class="rtg-stat-label">Tires Covered</div>
                </div>
                <div class="rtg-stat-card">
                    <div class="rtg-stat-value" style="color:var(--rtg-text-muted);"><?php echo count( $uncovered_tires ); ?></div>
                    <div class="rtg-stat-label">No Retailer Match</div>
                </div>
                <?php if ( $price_results && isset( $price_results['updated'] ) ) : ?>
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value"><?php echo intval( $price_results['updated'] ); ?></div>
                        <div class="rtg-stat-label">Prices Updated</div>
                    </div>
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value" style="color:var(--rtg-text-muted);"><?php echo intval( $price_results['skipped'] ); ?></div>
                        <div class="rtg-stat-label">Left Unchanged</div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $uncovered_tires ) ) : ?>
                <details>
                    <summary style="cursor:pointer;font-weight:600;margin-bottom:8px;">
                        <?php echo count( $uncovered_tires ); ?> tire(s) no retailer is carrying
                    </summary>
                    <p class="description" style="max-width:820px;margin:8px 0;">
                        Nothing in the queue keys to these tires, so their prices can't refresh on their own.
                        Only <strong>likely listed under another name</strong> is worth acting on — there a
                        listing's name really does resemble the guide's, and aligning the two matches the tire
                        on the next run. Every other row means the sweep has not seen the tire: the listings
                        shown beneath each one are what <em>did</em> arrive in that brand and fitment, offered
                        as evidence of how thin the coverage is rather than as the same tire under a
                        different name.
                    </p>

                    <?php if ( ! empty( $coverage_summary ) ) : ?>
                        <p style="margin:8px 0;font-size:13px;">
                            <?php
                            $gap_labels = array(
                                RTG_Coverage::GAP_MODEL_VARIANT   => 'likely listed under another name',
                                RTG_Coverage::GAP_MODEL_ABSENT    => 'brand and fitment carried, this model not',
                                RTG_Coverage::GAP_BRAND_ABSENT    => 'fitment carried, brand not',
                                RTG_Coverage::GAP_SIZE_ABSENT     => 'fitment never reached the queue',
                                RTG_Coverage::GAP_BRAND_MISSING   => 'guide row has no brand',
                                RTG_Coverage::GAP_SIZE_UNREADABLE => 'guide size unreadable',
                            );
                            $summary_parts = array();
                            foreach ( $coverage_summary as $gap_code => $gap_count ) {
                                $summary_parts[] = '<strong>' . intval( $gap_count ) . '</strong> '
                                    . esc_html( $gap_labels[ $gap_code ] ?? $gap_code );
                            }
                            echo wp_kses_post( implode( ' &middot; ', $summary_parts ) );
                            ?>
                        </p>
                    <?php endif; ?>

                    <table class="rtg-table" style="margin-top:8px;">
                        <thead>
                            <tr><th>Tire</th><th>Size</th><th>Price</th><th>Price age</th><th>Link</th><th>Why it isn't matched</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $uncovered_tires as $uncovered ) : ?>
                            <?php $reason = $coverage_reasons[ (string) $uncovered['tire_id'] ] ?? array(); ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&id=' . intval( $uncovered['id'] ) ) ); ?>">
                                        <?php echo esc_html( trim( $uncovered['brand'] . ' ' . $uncovered['model'] ) ); ?>
                                    </a>
                                </td>
                                <td style="font-family:var(--rtg-font-mono, monospace);"><?php echo esc_html( $uncovered['size'] ); ?></td>
                                <td><?php echo $uncovered['price'] > 0 ? '$' . esc_html( number_format( (float) $uncovered['price'], 2 ) ) : '&mdash;'; ?></td>
                                <td style="font-size:12px;color:var(--rtg-text-muted);">
                                    <?php
                                    // These prices only move when a person moves them, so
                                    // their age is the number that matters.
                                    $price_touch = RTG_Stale_Prices::last_price_touch( $uncovered );
                                    if ( $price_touch > 0 ) {
                                        $price_age_days = ( current_time( 'timestamp' ) - $price_touch ) / DAY_IN_SECONDS;
                                        echo '<span' . ( $price_age_days > RTG_Stale_Prices::DEFAULT_STALE_DAYS ? ' style="color:var(--rtg-error);font-weight:600;"' : '' ) . '>'
                                            . esc_html( human_time_diff( $price_touch, current_time( 'timestamp' ) ) ) . ' ago</span>';
                                    } else {
                                        echo '&mdash;';
                                    }
                                    ?>
                                </td>
                                <td style="font-size:12px;color:var(--rtg-text-muted);">
                                    <?php
                                    $link_retailer = RTG_Price_Sync::resolve_link_retailer( $uncovered['link'] ?? '' );
                                    if ( empty( $uncovered['link'] ) ) {
                                        echo 'No link';
                                    } elseif ( '' !== $link_retailer ) {
                                        echo esc_html( $link_retailer );
                                    } else {
                                        echo 'Elsewhere';
                                    }
                                    ?>
                                </td>
                                <td style="font-size:12px;">
                                    <?php echo esc_html( $reason['label'] ?? '' ); ?>
                                    <?php if ( ! empty( $reason['near'] ) ) : ?>
                                        <ul style="margin:6px 0 0;padding-left:16px;color:var(--rtg-text-muted);">
                                        <?php foreach ( $reason['near'] as $near ) : ?>
                                            <li>
                                                <code><?php echo esc_html( $near['model'] ?: '(no model parsed)' ); ?></code>
                                                <?php if ( ! empty( $near['advertisers'] ) ) : ?>
                                                    &mdash; <?php echo esc_html( implode( ', ', $near['advertisers'] ) ); ?>
                                                <?php endif; ?>
                                                <?php if ( RTG_Coverage::GAP_MODEL_VARIANT === ( $reason['code'] ?? '' ) && '' !== $near['model'] ) : ?>
                                                    <button type="button" class="rtg-btn rtg-btn-secondary rtg-adopt-alias"
                                                        style="padding:2px 8px;font-size:11px;margin-left:6px;"
                                                        data-tire-id="<?php echo esc_attr( $uncovered['tire_id'] ); ?>"
                                                        data-alias="<?php echo esc_attr( $near['model'] ); ?>">
                                                        Adopt as alias
                                                    </button>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endif; ?>

            <?php
            // Why a covered tire's price didn't move. Everything the run
            // decided is recorded, so this never needs a re-run to answer.
            $unchanged = array();
            if ( $price_results && ! empty( $price_results['outcomes'] ) ) {
                foreach ( $price_results['outcomes'] as $outcome_tire_id => $outcome ) {
                    if ( 'updated' !== $outcome['code'] && 'unchanged' !== $outcome['code'] ) {
                        $unchanged[ $outcome_tire_id ] = $outcome;
                    }
                }
            }
            ?>
            <?php if ( ! empty( $unchanged ) ) : ?>
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;">
                        <?php echo count( $unchanged ); ?> covered tire(s) whose price was not refreshed
                    </summary>
                    <table class="rtg-table" style="margin-top:8px;">
                        <thead>
                            <tr><th>Tire</th><th>Size</th><th>Retailer</th><th>Reason</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $unchanged as $outcome ) : ?>
                            <tr>
                                <td><?php echo esc_html( trim( $outcome['brand'] . ' ' . $outcome['model'] ) ); ?></td>
                                <td style="font-family:var(--rtg-font-mono, monospace);"><?php echo esc_html( $outcome['size'] ); ?></td>
                                <td><?php echo esc_html( $outcome['retailer'] ?: '&mdash;' ); ?></td>
                                <td style="font-size:12px;color:var(--rtg-text-muted);"><?php echo esc_html( $outcome['label'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endif; ?>

            <?php if ( ! $price_results ) : ?>
                <p class="description" style="margin-top:12px;">
                    Prices refresh on the next discovery run.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Candidates table -->
    <div class="rtg-card">
        <div class="rtg-table-wrapper">
            <?php if ( empty( $candidates ) ) : ?>
                <div class="rtg-empty-state" style="padding:60px 20px;text-align:center;">
                    <h2 style="font-size:20px;font-weight:600;margin:0 0 8px;">Nothing here</h2>
                    <p style="color:var(--rtg-text-muted);max-width:500px;margin:0 auto;line-height:1.6;">
                        <?php if ( RTG_Candidates::STATUS_NEW === $status_filter ) : ?>
                            No tires are waiting for review. New arrivals show up here after a discovery run.
                        <?php else : ?>
                            No candidates match this view.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else : ?>
                <table class="rtg-table rtg-table-compact">
                    <thead>
                        <tr>
                            <th>Tire</th>
                            <th>Size</th>
                            <th>Fits</th>
                            <th>Load</th>
                            <th>Speed</th>
                            <th>Price</th>
                            <th>Retailer</th>
                            <th>First Seen</th>
                            <?php if ( RTG_Candidates::STATUS_REJECTED === $status_filter ) : ?>
                                <th>Why Not</th>
                            <?php endif; ?>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $candidates as $candidate ) :
                        $add_url = add_query_arg(
                            array(
                                'page'           => 'rtg-tire-edit',
                                'from_candidate' => $candidate['id'],
                            ),
                            admin_url( 'admin.php' )
                        );
                    ?>
                        <tr data-candidate-id="<?php echo esc_attr( $candidate['id'] ); ?>">
                            <td>
                                <strong><?php echo esc_html( trim( $candidate['brand'] . ' ' . $candidate['model'] ) ); ?></strong>
                                <?php if ( ! empty( $candidate['matched_tire_id'] ) ) : ?>
                                    <br><span class="rtg-badge rtg-badge-muted">in guide as <?php echo esc_html( $candidate['matched_tire_id'] ); ?></span>
                                <?php endif; ?>

                                <?php
                                // Warnings ride along with a qualifying row: things
                                // to confirm before adding, not reasons to hide it.
                                foreach ( (array) ( $candidate['warnings'] ?? array() ) as $warning ) :
                                    if ( empty( $warning['label'] ) ) {
                                        continue;
                                    }
                                    ?>
                                    <br><span style="font-size:11px;color:var(--rtg-warning-text);">⚠ <?php echo esc_html( $warning['label'] ); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td style="font-family:var(--rtg-font-mono, monospace);"><?php echo esc_html( $candidate['size'] ); ?></td>
                            <td>
                                <?php
                                // Blank for a row that qualified before platform
                                // fitment was recorded; it fills in on the next run.
                                $fits = (array) ( $candidate['fits_vehicles'] ?? array() );
                                if ( empty( $fits ) ) {
                                    echo '<span style="color:var(--rtg-text-muted);">—</span>';
                                } else {
                                    foreach ( $fits as $fit_vehicle ) {
                                        printf(
                                            '<span class="rtg-badge" style="margin-right:4px;">%s</span>',
                                            esc_html( $fit_vehicle )
                                        );
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html( $candidate['load_index'] ?: '—' ); ?></td>
                            <td><?php echo esc_html( $candidate['speed_rating'] ?: '—' ); ?></td>
                            <td><?php echo $candidate['price'] > 0 ? '$' . esc_html( number_format( $candidate['price'], 2 ) ) : '—'; ?></td>
                            <td><?php echo esc_html( $candidate['advertiser_name'] ?: $candidate['source'] ); ?></td>
                            <td title="<?php echo esc_attr( $candidate['first_seen_at'] ); ?>">
                                <?php echo esc_html( human_time_diff( strtotime( $candidate['first_seen_at'] ), current_time( 'timestamp' ) ) ); ?> ago
                            </td>
                            <?php if ( RTG_Candidates::STATUS_REJECTED === $status_filter ) : ?>
                                <td style="font-size:12px;color:var(--rtg-text-muted);">
                                    <?php
                                    $labels = array();
                                    foreach ( (array) $candidate['fail_reasons'] as $reason ) {
                                        $labels[] = $reason['label'] ?? '';
                                    }
                                    echo esc_html( implode( '; ', array_filter( $labels ) ) );
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td style="text-align:right;white-space:nowrap;">
                                <?php if ( ! empty( $candidate['link'] ) ) : ?>
                                    <a href="<?php echo esc_url( $candidate['link'] ); ?>" target="_blank" rel="noopener noreferrer nofollow" class="rtg-btn rtg-btn-secondary" style="text-decoration:none;">Listing</a>
                                <?php endif; ?>

                                <?php if ( RTG_Candidates::STATUS_IMPORTED !== $status_filter && RTG_Candidates::STATUS_EXISTING !== $status_filter ) : ?>
                                    <a href="<?php echo esc_url( $add_url ); ?>" class="rtg-btn rtg-btn-primary" style="text-decoration:none;">Add to Guide</a>
                                <?php endif; ?>

                                <?php if ( RTG_Candidates::STATUS_DISMISSED === $status_filter ) : ?>
                                    <button type="button" class="rtg-btn rtg-btn-secondary rtg-candidate-action" data-status="<?php echo esc_attr( RTG_Candidates::STATUS_NEW ); ?>">Restore</button>
                                <?php elseif ( RTG_Candidates::STATUS_IMPORTED !== $status_filter ) : ?>
                                    <button type="button" class="rtg-btn rtg-btn-danger rtg-candidate-action" data-status="<?php echo esc_attr( RTG_Candidates::STATUS_DISMISSED ); ?>">Dismiss</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Settings -->
    <div class="rtg-card" style="margin-top:20px;">
        <div class="rtg-card-header">
            <h2>Discovery Settings</h2>
        </div>
        <div class="rtg-card-body">
            <form method="post">
                <?php wp_nonce_field( 'rtg_catalog_settings', 'rtg_catalog_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="catalog_sync_enabled">Enable Discovery</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="catalog_sync_enabled" id="catalog_sync_enabled" value="1" <?php checked( $sync_enabled ); ?>>
                                Check affiliate catalogs once a day
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="catalog_notify_enabled">Email Digest</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="catalog_notify_enabled" id="catalog_notify_enabled" value="1" <?php checked( $notify_enabled ); ?>>
                                Email me when a qualifying tire is found
                            </label>
                            <p class="description">Only newly surfaced tires are included — a run that finds nothing new sends nothing.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="health_alerts_enabled">Health Alerts</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="health_alerts_enabled" id="health_alerts_enabled" value="1" <?php checked( $health_alerts ); ?>>
                                Email me when discovery breaks, recovers, or a tire is dropped from the catalog
                            </label>
                            <p class="description" style="max-width:680px;">
                                The digest only fires on success, so without this every failure is silent: a rotated
                                CJ token failing each run with a 401, the daily schedule not firing, a fitment no
                                longer being read completely. Each problem emails once when it appears and once when
                                it clears — a week-long outage is two emails, not seven. Delistings email as they are
                                detected.
                                <br><br>
                                <strong>For a schedule that cannot silently die:</strong> WP-Cron only fires when the
                                site gets traffic. The reliable setup is a real server cron hitting
                                <code>wp-cron.php</code> every few minutes with <code>DISABLE_WP_CRON</code> set —
                                most hosts have a checkbox for this. Until then, any wp-admin visit also checks and
                                will flag a schedule that has gone quiet.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stale_price_report_enabled">Stale Price Report</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="stale_price_report_enabled" id="stale_price_report_enabled" value="1" <?php checked( $stale_price_report ); ?>>
                                Email me monthly about prices only a person can refresh
                            </label>
                            <p class="description" style="max-width:680px;">
                                Covered tires re-price themselves daily. The rest update only when someone edits them,
                                and a stale price is neither a broken link nor a failed run, so nothing else would
                                mention it. Monthly, listing tires untouched for
                                <?php echo esc_html( RTG_Stale_Prices::DEFAULT_STALE_DAYS ); ?>+ days, oldest first.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Minimum Load Index</th>
                        <td>
                            <?php if ( ! empty( $vehicle_size_map ) ) : ?>
                                <p class="description" style="max-width:680px;margin:0 0 10px;">
                                    Size and load index are judged together, per vehicle: a tire has to be one of a
                                    platform's sizes <em>and</em> carry enough load for it. A tire that clears no
                                    platform is filed under Near Misses naming the one it came closest on.
                                </p>
                                <table style="border-collapse:collapse;">
                                    <?php foreach ( $vehicle_size_map as $vehicle => $vehicle_sizes ) : ?>
                    <tr>
                                            <td style="padding:4px 12px 4px 0;">
                                                <label for="min_li_<?php echo esc_attr( $vehicle ); ?>"><strong><?php echo esc_html( $vehicle ); ?></strong></label>
                                            </td>
                                            <td style="padding:4px 12px 4px 0;">
                                                <input type="number"
                                                    name="catalog_vehicle_min_load_index[<?php echo esc_attr( $vehicle ); ?>]"
                                                    id="min_li_<?php echo esc_attr( $vehicle ); ?>"
                                                    value="<?php echo esc_attr( $vehicle_minimums[ $vehicle ] ?? '' ); ?>"
                                                    min="100" max="126" class="small-text">
                                            </td>
                                            <td style="padding:4px 0;font-size:12px;color:var(--rtg-text-muted);">
                                                <?php echo esc_html( implode( ', ', $vehicle_sizes ) ); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                                <p class="description" style="margin-top:8px;">
                                    Sizes come from <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-wheels' ) ); ?>">Stock Wheels</a>,
                                    so a platform added there appears here on its own. Blank restores the built-in figure
                                    (R1 116, R2 112).
                                </p>
                            <?php else : ?>
                                <input type="number" name="catalog_min_load_index" id="catalog_min_load_index" value="<?php echo esc_attr( $min_load_index ); ?>" min="100" max="126" class="small-text">
                                <p class="description" style="max-width:680px;">
                                    No stock wheels are configured, so there is no vehicle map to judge against and this
                                    single floor applies to every size. Add wheels under
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-wheels' ) ); ?>">Stock Wheels</a>
                                    to get per-platform rules.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="price_sync_enabled">Price Refresh</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="price_sync_enabled" id="price_sync_enabled" value="1" <?php checked( $price_sync_enabled ); ?>>
                                Refresh guide prices on each discovery run
                            </label>
                            <p class="description" style="max-width:680px;">
                                A price is taken only from the retailer the tire's own purchase link points to, so the
                                figure on the page always matches what a reader sees on click. A tire linked somewhere
                                discovery doesn't price — Amazon, a manufacturer — is left alone and listed below.
                            </p>
                            <p style="margin-top:8px;">
                                <label for="price_sync_max_change">Ignore changes larger than</label>
                                <input type="number" name="price_sync_max_change" id="price_sync_max_change" value="<?php echo esc_attr( $price_sync_max_change ); ?>" min="1" max="100" class="small-text">%
                            </p>
                            <p class="description" style="max-width:680px;">
                                Tires are matched on brand, model and size, which can collide across load ratings. A
                                price that moves further than this is more likely to be that collision than a real sale,
                                so it is reported rather than written.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="link_sync_enabled">Link Sync</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="link_sync_enabled" id="link_sync_enabled" value="1" <?php checked( $link_sync_enabled ); ?>>
                                Fill and upgrade purchase links from the catalog daily
                            </label>
                            <p class="description" style="max-width:680px;">
                                A tire with <strong>no link</strong> gets the cheapest fresh tracked listing — and
                                price sync then follows that retailer, so the price shown and the page clicked stay
                                consistent. A tire with a <strong>plain retailer link</strong> is upgraded to a
                                tracked link for the <em>same</em> retailer only — where the reader lands was already
                                chosen; monetizing it is mechanical, switching retailers is not. A link that is
                                <strong>already affiliate is never touched</strong>. Only listings the sweep has seen
                                in the last <?php echo esc_html( RTG_Link_Sync::FRESH_DAYS ); ?> days qualify, so a
                                delisted product's link can't be applied. Every decision is reported on the
                                Affiliate Links page.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="catalog_brand_policy">Brands Outside Your List</label></th>
                        <td>
                            <select name="catalog_brand_policy" id="catalog_brand_policy">
                                <option value="<?php echo esc_attr( RTG_Tire_Qualifier::BRAND_POLICY_WARN ); ?>" <?php selected( $brand_policy, RTG_Tire_Qualifier::BRAND_POLICY_WARN ); ?>>
                                    Surface them, flagged
                                </option>
                                <option value="<?php echo esc_attr( RTG_Tire_Qualifier::BRAND_POLICY_REJECT ); ?>" <?php selected( $brand_policy, RTG_Tire_Qualifier::BRAND_POLICY_REJECT ); ?>>
                                    File them under Near Misses
                                </option>
                                <option value="<?php echo esc_attr( RTG_Tire_Qualifier::BRAND_POLICY_OFF ); ?>" <?php selected( $brand_policy, RTG_Tire_Qualifier::BRAND_POLICY_OFF ); ?>>
                                    Don't judge brand at all
                                </option>
                            </select>
                            <p class="description" style="max-width:640px;">
                                Retailer catalogs carry far more brands than the guide covers, and most of a first
                                run is usually marques you'd never list. <strong>Surface them, flagged</strong> keeps
                                everything reviewable but marks an uncovered brand, so a newcomer worth covering
                                still reaches you. <strong>File them under Near Misses</strong> keeps the queue
                                tight, at the cost of never seeing a new brand until you add it to the list below.
                            </p>
                            <p class="description" style="margin-top:6px;">
                                <?php if ( ! empty( $covered_brands ) ) : ?>
                                    Currently covering <strong><?php echo count( $covered_brands ); ?></strong> brands:
                                    <?php echo esc_html( implode( ', ', $covered_brands ) ); ?>.
                                    Edit the list under <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-settings' ) ); ?>">Settings &rarr; Dropdown Options</a>.
                                <?php else : ?>
                                    No brand list is configured, so this rule stays silent whatever it is set to.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin:24px 0 8px;">CJ Affiliate</h3>
                <p class="description" style="max-width:820px;margin-bottom:8px;">
                    Both Tire Rack and SimpleTire run their affiliate programs on CJ, so one connection
                    covers both. Discovery sends one request per tire size, scoped to the advertisers below.
                    <?php if ( $cj_configured ) : ?>
                        <strong style="color:var(--rtg-success);">Configured.</strong>
                    <?php else : ?>
                        <strong style="color:var(--rtg-warning-text);">Not configured — discovery has no source until it is.</strong>
                    <?php endif; ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cj_enabled">Use CJ</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="cj_enabled" id="cj_enabled" value="1" <?php checked( $cj_enabled ); ?>>
                                Pull candidates from the CJ Product Search API
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_company_id">Company ID (CID)</label></th>
                        <td>
                            <input type="text" name="cj_company_id" id="cj_company_id" value="<?php echo esc_attr( $cj_company_id ); ?>" class="regular-text" inputmode="numeric">
                            <p class="description">From CJ &rarr; Account &rarr; Account Information.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_website_id">Website ID (PID)</label></th>
                        <td>
                            <input type="text" name="cj_website_id" id="cj_website_id" value="<?php echo esc_attr( $cj_website_id ); ?>" class="regular-text" inputmode="numeric">
                            <p class="description" style="max-width:680px;">
                                The property your deep links are minted for — the <strong>first number</strong> in one
                                of your existing CJ links: <code>click-<u>101098512</u>-13697786</code>. With it set,
                                every product the sweep fetches carries a ready-made <em>tracked</em> click URL, which
                                is what lets link sync below fill and upgrade purchase links automatically. Without
                                it, candidates only carry the retailer's plain URL, which pays nothing. After setting
                                it, use <strong>Test Connection</strong> — the sample product's link should show a
                                tracking domain (tkqlhce.com or similar), not the retailer's.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_pat">Personal Access Token</label></th>
                        <td>
                            <?php if ( $cj_pat_constant ) : ?>
                                <p style="margin:0;color:var(--rtg-success);"><strong>Set in wp-config.php</strong> via <code>RTG_CJ_PAT</code>. This field is ignored while that constant is defined.</p>
                            <?php else : ?>
                                <input type="password" name="cj_pat" id="cj_pat" value="" class="regular-text" autocomplete="off"
                                    placeholder="<?php echo $cj_has_pat ? esc_attr( 'Saved — leave blank to keep' ) : esc_attr( 'Paste your CJ token' ); ?>">
                                <?php if ( $cj_has_pat ) : ?>
                                    <label style="margin-left:12px;">
                                        <input type="checkbox" name="cj_pat_clear" value="1"> Clear saved token
                                    </label>
                                <?php endif; ?>
                                <p class="description">
                                    Never displayed once saved. Better still, keep it out of the database entirely by adding
                                    <code>define( 'RTG_CJ_PAT', '...' );</code> to <code>wp-config.php</code> — that takes precedence over this field.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_advertisers">Advertisers</label></th>
                        <td>
                            <textarea name="cj_advertisers" id="cj_advertisers" rows="3" class="large-text code" placeholder="<?php echo esc_attr( $cj_advertiser_placeholder ); ?>"><?php echo esc_textarea( $cj_advertisers ); ?></textarea>
                            <p class="description">One per line, as <code>advertiserId|Name</code>. Leave blank for the defaults shown. Only advertisers you have joined return products.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_limit">Records per size</label></th>
                        <td>
                            <input type="number" name="cj_limit" id="cj_limit" value="<?php echo esc_attr( $cj_limit ); ?>" min="1" max="1000" class="small-text">
                            <p class="description" style="max-width:680px;">
                                How many products to request per tire size — one request each. A popular fitment can
                                carry several hundred, and anything beyond this is discarded by the retailer before it
                                reaches the queue, so a tire that plainly exists can look like nobody stocks it. When a
                                run comes back capped, the status above says so and names the sizes.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_max_pages">Pages per size</label></th>
                        <td>
                            <input type="number" name="cj_max_pages" id="cj_max_pages" value="<?php echo esc_attr( $cj_max_pages ); ?>" min="1" max="50" class="small-text">
                            <p class="description" style="max-width:680px;">
                                How far to page through one size's matches before moving on — pages of "Records per
                                size" each. Paging to the end of an unfiltered search would spend the whole budget on a
                                single size, so a size stops here and the status says how much it left behind.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="catalog_run_budget">Whole-run budget</label></th>
                        <td>
                            <input type="number" name="catalog_run_budget" id="catalog_run_budget" value="<?php echo esc_attr( $catalog_run_budget ); ?>" min="30" max="900" class="small-text"> seconds
                            <p class="description" style="max-width:680px;">
                                A ceiling on the run as a whole, which the sweep and the direct lookups share. Each
                                pass honouring only its own budget is not the same as the run having one: together
                                they could outlive the request and return nothing at all. <strong>Lower this first
                                if Run Discovery Now fails with no reply.</strong> A shorter run costs
                                time-to-complete, never coverage &mdash; both passes resume where they stopped.
                            </p>
                        </td>
                    </tr>
                                                            <tr>
                        <th scope="row"><label for="cj_sweep_budget">Time budget</label></th>
                        <td>
                            <input type="number" name="cj_sweep_budget" id="cj_sweep_budget" value="<?php echo esc_attr( $cj_sweep_budget ); ?>" min="15" max="600" class="small-text"> seconds
                            <p class="description" style="max-width:680px;">
                                How long a sweep may spend fetching before it stops and reports the sizes it didn't
                                reach. Lower it if your host has a tight PHP execution limit; raise it if the status
                                above says sizes went unchecked.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cj_query">GraphQL query</label></th>
                        <td>
                            <textarea name="cj_query" id="cj_query" rows="10" class="large-text code" spellcheck="false" placeholder="<?php echo esc_attr( RTG_Catalog_Source_CJ::DEFAULT_QUERY ); ?>"><?php echo esc_textarea( $cj_query ); ?></textarea>
                            <p class="description">
                                Leave blank to use the shipped query. If Test Connection reports a GraphQL error naming a field,
                                correct it here rather than waiting on a plugin update — the response mapping accepts several
                                field spellings, so only the query itself usually needs changing.
                            </p>
                            <p style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <input type="text" id="rtg-cj-test-keyword" class="regular-text"
                                    placeholder="Michelin Defender LTX M/S2 305/45R22"
                                    style="flex:1 1 340px;min-width:240px;">
                                <input type="number" id="rtg-cj-test-offset" min="0" step="1000" value="0"
                                    class="small-text" placeholder="offset" title="Records to skip">
                                <button type="button" id="rtg-cj-test-btn" class="rtg-btn rtg-btn-secondary">Test Connection</button>
                            </p>
                            <p class="description" style="max-width:680px;">
                                Any keyword may be probed here, and the reply lists the titles it returned. That is how to
                                tell whether CJ is <em>matching</em> a term or merely ranking against it: type a tire's
                                full name and see whether that tire is anywhere in the answer. Blank uses the first
                                guide size, which tests the connection itself.
                                <br><br>
                                <strong>The offset is how to check that paging works.</strong> Probe a size at 0, then
                                the same size at 1000. Different titles mean the sweep really is reading deeper each
                                page. <em>The same titles mean it is re-reading page one</em> &mdash; and a sweep that
                                counts what came back rather than what was new would still call that fitment complete.
                            </p>
                            <div id="rtg-cj-test-result" style="display:none;margin-top:10px;"></div>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="rtg_catalog_settings_save" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
    </div>

</div>
