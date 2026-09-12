<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'rtg_settings', array() );

// Saved through RTG_Admin::handle_catalog_settings_save() (post-redirect-get),
// so a refresh of this page can't re-submit the form. The page reopens on
// the settings tab so the admin sees the notice where they were working.
$settings_saved = isset( $_GET['message'] ) && 'settings_saved' === $_GET['message'];
$default_tab    = $settings_saved ? 'settings' : 'queue';

$sync_enabled   = $settings['catalog_sync_enabled'] ?? true;
$notify_enabled = $settings['catalog_notify_enabled'] ?? true;
$health_alerts  = $settings['health_alerts_enabled'] ?? true;
$stale_price_report = $settings['stale_price_report_enabled'] ?? true;
$min_load_index = isset( $settings['catalog_min_load_index'] )
    ? intval( $settings['catalog_min_load_index'] )
    : RTG_Tire_Qualifier::DEFAULT_MIN_LOAD_INDEX;

// The queue answers to the guide as it is now, not as the last nightly sweep
// left it — a tire added or renamed since then must not still be offered here
// as something new. Re-keying reads every candidate and can write per row,
// so it's throttled like RTG_Health::admin_probe rather than run on every
// page view: a recent pass (here or the nightly sweep) is current enough,
// and a tire edit flushes the throttle so its rename shows immediately.
if ( false === get_transient( 'rtg_discovery_reconciled' ) ) {
    RTG_Candidates::reconcile_with_guide();
    set_transient( 'rtg_discovery_reconciled', 1, 10 * MINUTE_IN_SECONDS );
}

// The candidate listing filters, parsed before anything below reads them.
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

// Retailer coverage: which guide tires a retailer actually carries. Tires with
// no match are expected while affiliate links are still being filled in, so
// they are listed plainly rather than treated as a fault.
$retailer_coverage = RTG_Candidates::get_retailer_coverage();
$guide_tires       = RTG_Database::get_all_tires();

// Guide tires bucketed by brand and fitment. The matcher uses it to recognize
// a tire the retailer spells differently; the queue uses it below to say when
// a row it is still calling new looks like a tire already stocked.
$guide_variants = RTG_Catalog_Sync::build_variant_index( $guide_tires );

// The exact keys, kept beside it so a row can say which of the two matched it.
// A name-drift match is a judgement about two spellings and has to be visibly
// one, with the way to overrule it left open.
$guide_key_index = RTG_Catalog_Sync::build_guide_index();

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
// Which of those sizes fit only on 3rd-party wheels, for the fits column.
$third_party_map  = RTG_Database::get_third_party_size_map();
$vehicle_minimums = RTG_Tire_Qualifier::get_vehicle_minimums();
// What each platform has saved for its load range floor: a rating, "none",
// or nothing (the built-in figure applies).
$vehicle_range_saved = isset( $settings['catalog_vehicle_min_load_range'] ) && is_array( $settings['catalog_vehicle_min_load_range'] )
    ? $settings['catalog_vehicle_min_load_range']
    : array();
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

$candidate_filters = array(
    'status'  => $status_filter,
    'size'    => $size_filter,
    'vehicle' => $vehicle_filter,
    'brand'   => $brand_filter,
);

$candidates      = RTG_Candidates::query( $candidate_filters );
$candidate_total = RTG_Candidates::count_matching( $candidate_filters );

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

    <?php if ( $settings_saved ) : ?>
        <div class="rtg-notice rtg-notice-success">
            <span>Tire Discovery settings saved.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <div class="rtg-page-heading">
            <h1 class="rtg-page-title">Tire Discovery</h1>
            <p class="rtg-page-subtitle">
                Watches affiliate catalogs for tires in a Rivian size that are not in the guide yet, refreshes prices, and fills in purchase links. Dismissed products stay dismissed.
            </p>
        </div>
        <div class="rtg-page-actions">
            <button type="button" id="rtg-catalog-sync-btn" class="rtg-btn rtg-btn-primary">
                <span class="dashicons dashicons-update"></span> Run discovery now
            </button>
        </div>
    </div>

    <div data-rtg-tabs data-default="<?php echo esc_attr( $default_tab ); ?>">

    <nav class="rtg-tabs" aria-label="Tire Discovery sections">
        <button type="button" class="rtg-tab" data-tab="queue">
            Review queue
            <?php if ( $counts[ RTG_Candidates::STATUS_NEW ] > 0 ) : ?>
                <span class="rtg-tab-count is-alert"><?php echo esc_html( $counts[ RTG_Candidates::STATUS_NEW ] ); ?></span>
            <?php endif; ?>
        </button>
        <button type="button" class="rtg-tab" data-tab="coverage">
            Retailer coverage &amp; prices
            <?php if ( ! empty( $uncovered_tires ) ) : ?>
                <span class="rtg-tab-count"><?php echo count( $uncovered_tires ); ?></span>
            <?php endif; ?>
        </button>
        <button type="button" class="rtg-tab" data-tab="settings">Settings</button>
    </nav>

    <!-- ================================================================
         Review queue
         ================================================================ -->
    <div class="rtg-tab-panel" data-tab-panel="queue">

    <!-- Sync status -->
    <div class="rtg-card">
        <div class="rtg-card-header is-split">
            <div><h2>Last run</h2></div>
            <?php if ( $stats && isset( $stats['status'] ) ) : ?>
                <?php if ( 'success' === $stats['status'] ) : ?>
                    <span class="rtg-badge rtg-badge-success">Succeeded</span>
                <?php else : ?>
                    <span class="rtg-badge rtg-badge-error">Failed</span>
                <?php endif; ?>
            <?php elseif ( ! $cj_configured ) : ?>
                <span class="rtg-badge rtg-badge-warning">No source configured</span>
            <?php endif; ?>
        </div>
        <div class="rtg-card-body">
            <div id="rtg-catalog-sync-status" style="display:none;"></div>

            <?php if ( $stats && isset( $stats['status'] ) ) : ?>
                <div class="rtg-kv-grid">
                    <div>
                        <span class="rtg-kv-label">Last run</span>
                        <?php
                        $run_time = $stats['time'] ?? '';
                        $relative = $run_time
                            ? human_time_diff( strtotime( $run_time ), current_time( 'timestamp' ) ) . ' ago'
                            : 'N/A';
                        ?>
                        <div class="rtg-kv-value" title="<?php echo esc_attr( $run_time ); ?>"><?php echo esc_html( $relative ); ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Products seen</span>
                        <div class="rtg-kv-value"><?php echo esc_html( number_format( intval( $stats['fetched'] ?? 0 ) ) ); ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Newly surfaced</span>
                        <div class="rtg-kv-value is-success"><?php echo esc_html( intval( $stats['newly_surfaced'] ?? 0 ) ); ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Next run</span>
                        <div class="rtg-kv-value <?php echo $next_run ? '' : 'is-warning'; ?>">
                            <?php echo $next_run ? esc_html( 'in ' . human_time_diff( time(), $next_run ) ) : 'Not scheduled'; ?>
                        </div>
                    </div>
                    <?php if ( ! empty( $stats['elapsed'] ) ) : ?>
                    <div>
                        <span class="rtg-kv-label">Run time</span>
                        <div class="rtg-kv-value is-muted"><?php echo esc_html( number_format( (float) $stats['elapsed'], 1 ) ); ?>s <span class="rtg-stat-sub">of <?php echo esc_html( intval( $stats['run_budget'] ?? $catalog_run_budget ) ); ?>s</span></div>
                        <?php if ( ! empty( $stats['budget_capped'] ) ) : ?>
                            <div class="rtg-kv-note">Browser cap. The nightly run gets <?php echo intval( $catalog_run_budget ); ?>s.</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
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
                    <details class="rtg-details">
                        <summary>Fitment coverage: how much of each size's match set has been read</summary>
                        <div class="rtg-details-body">
                        <p class="rtg-help">
                            <strong>Complete</strong> means an absence from that fitment is real, not a sweep gap.
                            If <strong>Distinct</strong> falls far below Read (shown in red), the pages overlapped
                            and "complete" means re-read. Distrust it.
                        </p>
                        <div class="rtg-table-wrapper">
                        <table class="rtg-table rtg-table-compact">
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
                                    <td class="rtg-mono"><?php echo esc_html( $cov_size ); ?></td>
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
                                            <span<?php echo $cov_thin ? ' class="rtg-text-error rtg-strong"' : ''; ?>>
                                                <?php echo esc_html( number_format( $cov_unique ) ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo null === $cov_total ? '&mdash;' : esc_html( number_format( $cov_total ) ); ?></td>
                                    <td>
                                        <?php if ( null === $cov_pct ) : ?>
                                            &mdash;
                                        <?php elseif ( $cov_pct >= 100 ) : ?>
                                            <span class="rtg-badge rtg-badge-success rtg-badge-sm">complete</span>
                                        <?php else : ?>
                                            <span class="rtg-muted"><?php echo esc_html( $cov_pct ); ?>%</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        </div>
                    </details>
                <?php endif; ?>

                <?php
                $pruned_total = intval( $stats['pruned']['off_fitment'] ?? 0 ) + intval( $stats['pruned']['stale'] ?? 0 );
                ?>
                <?php if ( $pruned_total > 0 ) : ?>
                    <p class="rtg-help">
                        Pruned <?php echo esc_html( number_format( $pruned_total ) ); ?> near misses
                        (<?php echo esc_html( number_format( intval( $stats['pruned']['off_fitment'] ?? 0 ) ) ); ?> off-fitment,
                        <?php echo esc_html( number_format( intval( $stats['pruned']['stale'] ?? 0 ) ) ); ?> unseen 60+ days).
                    </p>
                <?php endif; ?>

                <?php if ( ! empty( $stats['errors'] ) ) : ?>
                    <div class="rtg-notice rtg-notice-warning is-after">
                        <span>
                            <?php foreach ( $stats['errors'] as $error ) : ?>
                                <strong><?php echo esc_html( $error['source'] ); ?>:</strong> <?php echo esc_html( $error['message'] ); ?><br>
                            <?php endforeach; ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <p class="rtg-empty-line">Discovery has not run yet. Use <strong>Run discovery now</strong> to try it against the configured source<?php echo $cj_configured ? '' : ', once CJ is set up on the Settings tab'; ?>.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status filter -->
    <div class="rtg-pills">
        <?php
        $tabs = array(
            RTG_Candidates::STATUS_NEW       => 'Awaiting review',
            RTG_Candidates::STATUS_REJECTED  => 'Near misses',
            RTG_Candidates::STATUS_EXISTING  => 'Already in guide',
            RTG_Candidates::STATUS_DISMISSED => 'Dismissed',
            RTG_Candidates::STATUS_IMPORTED  => 'Added',
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
            $is_active = $status_filter === $key;
        ?>
            <a href="<?php echo esc_url( $url ); ?>" class="rtg-pill <?php echo $is_active ? 'is-active' : ''; ?>" <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html( $label ); ?>
                <span class="rtg-pill-count"><?php echo intval( $counts[ $key ] ); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="rtg-tire-discovery">
        <input type="hidden" name="candidate_status" value="<?php echo esc_attr( $status_filter ); ?>">
        <div class="rtg-toolbar">
            <?php if ( ! empty( $vehicle_size_map ) ) : ?>
                <label for="candidate_vehicle" class="rtg-toolbar-label">Vehicle</label>
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

            <label for="candidate_brand" class="rtg-toolbar-label">Brand</label>
            <select name="candidate_brand" id="candidate_brand" class="rtg-select">
                <option value="">All brands</option>
                <?php foreach ( $brand_counts as $brand_option => $brand_total ) : ?>
                    <?php if ( '' === $brand_option ) { continue; } ?>
                    <option value="<?php echo esc_attr( $brand_option ); ?>" <?php selected( $brand_filter, $brand_option ); ?>>
                        <?php echo esc_html( $brand_option . ' (' . $brand_total . ')' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="candidate_size" class="rtg-toolbar-label">Size</label>
            <select name="candidate_size" id="candidate_size" class="rtg-select">
                <option value="">All sizes</option>
                <?php foreach ( $dd_sizes as $size_option ) : ?>
                    <option value="<?php echo esc_attr( $size_option ); ?>" <?php selected( $size_filter, $size_option ); ?>><?php echo esc_html( $size_option ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rtg-btn rtg-btn-secondary">Filter</button>

            <?php if ( in_array( $status_filter, array( RTG_Candidates::STATUS_NEW, RTG_Candidates::STATUS_DISMISSED ), true ) && ! empty( $candidates ) ) : ?>
                <span class="rtg-toolbar-spacer"></span>
                <button type="button" id="rtg-bulk-candidates" class="rtg-btn <?php echo RTG_Candidates::STATUS_NEW === $status_filter ? 'rtg-btn-danger-quiet' : 'rtg-btn-secondary'; ?>"
                    data-status="<?php echo esc_attr( $status_filter ); ?>"
                    data-brand="<?php echo esc_attr( $brand_filter ); ?>"
                    data-size="<?php echo esc_attr( $size_filter ); ?>"
                    data-vehicle="<?php echo esc_attr( $vehicle_filter ); ?>"
                    data-to="<?php echo esc_attr( RTG_Candidates::STATUS_NEW === $status_filter ? RTG_Candidates::STATUS_DISMISSED : RTG_Candidates::STATUS_NEW ); ?>">
                    <?php echo RTG_Candidates::STATUS_NEW === $status_filter ? 'Dismiss everything this filter matches' : 'Restore everything this filter matches'; ?>
                </button>
            <?php endif; ?>

            <?php if ( '' !== $vehicle_filter ) : ?>
                <span class="rtg-toolbar-note">
                    Showing tires legal on <strong><?php echo esc_html( $vehicle_filter ); ?></strong>
                    (size <em>and</em> load index
                    <?php echo isset( $vehicle_minimums[ $vehicle_filter ] ) ? '&ge; ' . intval( $vehicle_minimums[ $vehicle_filter ] ) : ''; ?>).
                    A tire legal on more than one platform appears under each.
                </span>
            <?php endif; ?>
        </div>
    </form>

    <?php if ( RTG_Candidates::STATUS_NEW === $status_filter && $uncovered_brand_total > 0 ) : ?>
        <div class="rtg-notice rtg-notice-info">
            <span>
                <strong><?php echo esc_html( number_format( $uncovered_brand_total ) ); ?></strong> of the
                <?php echo esc_html( number_format( $counts[ RTG_Candidates::STATUS_NEW ] ) ); ?> tires
                awaiting review are from brands outside your list. Set the
                <strong>brand policy</strong> on the Settings tab to file them automatically, or clear a brand
                with the filter and the dismiss button.
            </span>
        </div>
    <?php endif; ?>

    <!-- Candidates table -->
    <div class="rtg-card">
        <div class="rtg-table-wrapper">
            <?php if ( empty( $candidates ) ) : ?>
                <div class="rtg-empty-state">
                    <span class="dashicons dashicons-search"></span>
                    <h2>Nothing here</h2>
                    <p>
                        <?php if ( RTG_Candidates::STATUS_NEW === $status_filter ) : ?>
                            No tires are waiting for review. New arrivals show up here after a discovery run.
                        <?php else : ?>
                            No candidates match this view.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else : ?>
                <?php if ( $candidate_total > count( $candidates ) ) : ?>
                    <div class="rtg-table-note">
                        Showing the <?php echo esc_html( count( $candidates ) ); ?> most recent of
                        <?php echo esc_html( $candidate_total ); ?> rows in this view.
                        Narrow with the size, vehicle, or brand filters to reach the rest.
                    </div>
                <?php endif; ?>
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
                            <th>First seen</th>
                            <?php if ( RTG_Candidates::STATUS_REJECTED === $status_filter ) : ?>
                                <th>Why not</th>
                            <?php endif; ?>
                            <th class="is-right">Actions</th>
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

                        // Matched on the key the guide spells the tire with, or
                        // on a reading of two names as one tire? The second is
                        // a judgement, so it says so and stays overrulable.
                        $candidate_key = RTG_Catalog_Sync::match_key(
                            $candidate['brand'],
                            $candidate['model'],
                            $candidate['size']
                        );
                        $matched_by_name = ! empty( $candidate['matched_tire_id'] )
                            && ( '' === $candidate_key || ! isset( $guide_key_index[ $candidate_key ] ) );

                        // Marked as added, yet nothing in the guide answers to
                        // it. Re-keying above has already backfilled every
                        // imported row that still matches a tire, and returned
                        // every one whose recorded tire was deleted — so what
                        // is left here was imported before that id was kept,
                        // and its tire has since been removed or renamed.
                        // Undecidable from here, and a click to settle.
                        $imported_orphan = RTG_Candidates::STATUS_IMPORTED === $candidate['status']
                            && empty( $candidate['matched_tire_id'] );
                    ?>
                        <tr data-candidate-id="<?php echo esc_attr( $candidate['id'] ); ?>">
                            <td>
                                <?php
                                // The plain product page, not the tracked link —
                                // reviewing a tire shouldn't register affiliate clicks.
                                $product_url  = RTG_Price_Sync::destination_url( $candidate['link'] ?? '' );
                                $product_name = trim( $candidate['brand'] . ' ' . $candidate['model'] );
                                ?>
                                <?php if ( '' !== $product_url ) : ?>
                                    <a href="<?php echo esc_url( $product_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtg-row-title" title="View on the retailer's site">
                                        <?php echo esc_html( $product_name ); ?>
                                        <span class="dashicons dashicons-external rtg-external-icon"></span>
                                    </a>
                                <?php else : ?>
                                    <strong><?php echo esc_html( $product_name ); ?></strong>
                                <?php endif; ?>
                                <?php if ( ! empty( $candidate['matched_tire_id'] ) ) : ?>
                                    <span class="rtg-row-meta"><span class="rtg-badge rtg-badge-muted rtg-badge-sm">in guide as <?php echo esc_html( $candidate['matched_tire_id'] ); ?></span></span>
                                    <?php if ( $matched_by_name ) : ?>
                                        <span class="rtg-row-note">
                                            Matched on the name, not the guide's own spelling. Add it anyway if it is a different tire.
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ( $imported_orphan ) : ?>
                                    <span class="rtg-row-note is-warning">
                                        No guide tire matches this any more. It was removed, or renamed since it was added.
                                    </span>
                                <?php elseif ( RTG_Candidates::STATUS_NEW === $candidate['status'] ) :
                                    // Same brand, same fitment, a name that shares
                                    // something with a tire already in the guide.
                                    // Not close enough for the matcher to call it
                                    // the same tire, close enough that a human
                                    // should look before adding a second entry.
                                    //
                                    // Only on a row still being offered as new: on
                                    // one already added, or dismissed, "same tire?
                                    // add an alias" is advice about a decision that
                                    // has been taken.
                                    $near_tire = RTG_Catalog_Sync::nearest_guide_variant(
                                        $candidate['brand'],
                                        $candidate['model'],
                                        $candidate['size'],
                                        $guide_variants
                                    );
                                    if ( $near_tire ) :
                                        $near_url = add_query_arg(
                                            array( 'page' => 'rtg-tire-edit', 'id' => intval( $near_tire['id'] ) ),
                                            admin_url( 'admin.php' )
                                        );

                                        // A rating that disagrees is the ordinary shape of a
                                        // variant, not a spelling difference, so the row says
                                        // so instead of proposing an alias that would file
                                        // this listing under a tire it isn't.
                                        $near_differs = RTG_Catalog_Sync::load_ratings_disagree(
                                            $candidate['load_index'],
                                            $near_tire['load_index'] ?? ''
                                        );
                                        ?>
                                        <span class="rtg-row-note">
                                            Guide already has
                                            <a href="<?php echo esc_url( $near_url ); ?>"><?php echo esc_html( $near_tire['model'] ); ?></a>
                                            in this size
                                            <?php if ( $near_differs ) : ?>
                                                at load <?php echo esc_html( $near_tire['load_index'] ); ?>, where this listing is
                                                <?php echo esc_html( $candidate['load_index'] ); ?> &mdash; usually a different tire.
                                                Alias it only if it isn't.
                                            <?php else : ?>
                                                &mdash; same tire? Add
                                                <code><?php echo esc_html( $candidate['model'] ); ?></code>
                                                as a model alias on it.
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php
                                // Warnings ride along with a qualifying row: things
                                // to confirm before adding, not reasons to hide it.
                                foreach ( (array) ( $candidate['warnings'] ?? array() ) as $warning ) :
                                    if ( empty( $warning['label'] ) ) {
                                        continue;
                                    }
                                    ?>
                                    <span class="rtg-row-note is-warning">⚠ <?php echo esc_html( $warning['label'] ); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td class="rtg-mono"><?php echo esc_html( $candidate['size'] ); ?></td>
                            <td>
                                <?php
                                // Blank for a row that qualified before platform
                                // fitment was recorded; it fills in on the next run.
                                $fits = (array) ( $candidate['fits_vehicles'] ?? array() );
                                if ( empty( $fits ) ) {
                                    echo '<span class="rtg-empty">—</span>';
                                } else {
                                    echo '<span class="rtg-badge-list">';
                                    foreach ( $fits as $fit_vehicle ) {
                                        // A fit on wheels Rivian never sold reads as such,
                                        // so an 18" R2 candidate isn't mistaken for a factory size.
                                        $fit_third = RTG_Fitment::third_party_entry( $candidate['size'] ?? '', $fit_vehicle, $third_party_map );
                                        printf(
                                            '<span class="rtg-badge%s"%s>%s</span>',
                                            $fit_third ? ' rtg-badge-third-party' : ' rtg-badge-info',
                                            $fit_third ? ' title="Fits only on 3rd-party wheels"' : '',
                                            esc_html( $fit_vehicle . ( $fit_third ? ' · 3rd-party' : '' ) )
                                        );
                                    }
                                    echo '</span>';
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
                                <td class="rtg-muted rtg-small">
                                    <?php
                                    $labels = array();
                                    foreach ( (array) $candidate['fail_reasons'] as $reason ) {
                                        $labels[] = $reason['label'] ?? '';
                                    }
                                    echo esc_html( implode( '; ', array_filter( $labels ) ) );
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td class="is-right">
                                <div class="rtg-table-actions">
                                <?php if ( ! empty( $candidate['link'] ) ) : ?>
                                    <a href="<?php echo esc_url( $candidate['link'] ); ?>" target="_blank" rel="noopener noreferrer nofollow" class="rtg-btn rtg-btn-secondary rtg-btn-sm">Listing</a>
                                <?php endif; ?>

                                <?php if ( RTG_Candidates::STATUS_IMPORTED !== $status_filter
                                    && ( RTG_Candidates::STATUS_EXISTING !== $status_filter || $matched_by_name ) ) : ?>
                                    <a href="<?php echo esc_url( $add_url ); ?>" class="rtg-btn rtg-btn-primary rtg-btn-sm">Add to guide</a>
                                <?php endif; ?>

                                <?php if ( $imported_orphan ) : ?>
                                    <button type="button" class="rtg-btn rtg-btn-secondary rtg-btn-sm rtg-candidate-action" data-status="<?php echo esc_attr( RTG_Candidates::STATUS_NEW ); ?>">Return to review</button>
                                <?php endif; ?>

                                <?php if ( RTG_Candidates::STATUS_DISMISSED === $status_filter ) : ?>
                                    <button type="button" class="rtg-btn rtg-btn-secondary rtg-btn-sm rtg-candidate-action" data-status="<?php echo esc_attr( RTG_Candidates::STATUS_NEW ); ?>">Restore</button>
                                <?php elseif ( RTG_Candidates::STATUS_IMPORTED !== $status_filter ) : ?>
                                    <button type="button" class="rtg-btn rtg-btn-danger-quiet rtg-btn-sm rtg-candidate-action" data-status="<?php echo esc_attr( RTG_Candidates::STATUS_DISMISSED ); ?>">Dismiss</button>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    </div><!-- queue panel -->

    <!-- ================================================================
         Retailer coverage and price refresh
         ================================================================ -->
    <div class="rtg-tab-panel" data-tab-panel="coverage" hidden>

    <div class="rtg-card">
        <div class="rtg-card-header">
            <h2>Retailer coverage and prices</h2>
            <p>A covered tire is one a retailer's catalog lists, so its price refreshes on every run. The rest only change when a person edits them.</p>
        </div>
        <div class="rtg-card-body">
            <div class="rtg-stats-grid is-compact is-inset">
                <div class="rtg-stat-card is-success">
                    <div class="rtg-stat-value"><?php echo count( $covered_tires ); ?></div>
                    <div class="rtg-stat-label">Tires covered</div>
                </div>
                <div class="rtg-stat-card is-muted">
                    <div class="rtg-stat-value"><?php echo count( $uncovered_tires ); ?></div>
                    <div class="rtg-stat-label">No retailer match</div>
                </div>
                <?php if ( $price_results && isset( $price_results['updated'] ) ) : ?>
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value"><?php echo intval( $price_results['updated'] ); ?></div>
                        <div class="rtg-stat-label">Prices updated</div>
                    </div>
                    <div class="rtg-stat-card is-muted">
                        <div class="rtg-stat-value"><?php echo intval( $price_results['skipped'] ); ?></div>
                        <div class="rtg-stat-label">Left unchanged</div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $uncovered_tires ) ) : ?>
                <details class="rtg-details" open>
                    <summary><?php echo count( $uncovered_tires ); ?> tire<?php echo 1 === count( $uncovered_tires ) ? '' : 's'; ?> no retailer is carrying</summary>
                    <div class="rtg-details-body">
                    <p class="rtg-help">
                        These have no catalog match, so their prices do not refresh on their own. Only
                        <strong>likely listed under another name</strong> is actionable: adopt the alias and
                        the next run matches it. Other rows show what <em>did</em> arrive in that brand
                        and fitment.
                    </p>

                    <?php if ( ! empty( $coverage_summary ) ) : ?>
                        <p class="rtg-help">
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

                    <div class="rtg-table-wrapper">
                    <table class="rtg-table rtg-table-compact">
                        <thead>
                            <tr><th>Tire</th><th>Size</th><th>Price</th><th>Price age</th><th>Link</th><th>Why it is not matched</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $uncovered_tires as $uncovered ) : ?>
                            <?php $reason = $coverage_reasons[ (string) $uncovered['tire_id'] ] ?? array(); ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&id=' . intval( $uncovered['id'] ) ) ); ?>" class="rtg-row-title">
                                        <?php echo esc_html( trim( $uncovered['brand'] . ' ' . $uncovered['model'] ) ); ?>
                                    </a>
                                </td>
                                <td class="rtg-mono"><?php echo esc_html( $uncovered['size'] ); ?></td>
                                <td><?php echo $uncovered['price'] > 0 ? '$' . esc_html( number_format( (float) $uncovered['price'], 2 ) ) : '&mdash;'; ?></td>
                                <td class="rtg-muted rtg-small">
                                    <?php
                                    // These prices only move when a person moves them, so
                                    // their age is the number that matters.
                                    $price_touch = RTG_Stale_Prices::last_price_touch( $uncovered );
                                    if ( $price_touch > 0 ) {
                                        $price_age_days = ( current_time( 'timestamp' ) - $price_touch ) / DAY_IN_SECONDS;
                                        echo '<span' . ( $price_age_days > RTG_Stale_Prices::DEFAULT_STALE_DAYS ? ' class="rtg-text-error rtg-strong"' : '' ) . '>'
                                            . esc_html( human_time_diff( $price_touch, current_time( 'timestamp' ) ) ) . ' ago</span>';
                                    } else {
                                        echo '&mdash;';
                                    }
                                    ?>
                                </td>
                                <td class="rtg-muted rtg-small">
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
                                <td class="rtg-small rtg-row-note-cell">
                                    <?php echo esc_html( $reason['label'] ?? '' ); ?>
                                    <?php if ( ! empty( $reason['near'] ) ) : ?>
                                        <ul class="rtg-row-note">
                                        <?php foreach ( $reason['near'] as $near ) : ?>
                                            <li>
                                                <code><?php echo esc_html( $near['model'] ?: '(no model parsed)' ); ?></code>
                                                <?php if ( ! empty( $near['advertisers'] ) ) : ?>
                                                    &mdash; <?php echo esc_html( implode( ', ', $near['advertisers'] ) ); ?>
                                                <?php endif; ?>
                                                <?php if ( RTG_Coverage::GAP_MODEL_VARIANT === ( $reason['code'] ?? '' ) && '' !== $near['model'] ) : ?>
                                                    <button type="button" class="rtg-btn rtg-btn-secondary rtg-btn-xs rtg-adopt-alias"
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
                    </div>
                    </div>
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
                <details class="rtg-details">
                    <summary><?php echo count( $unchanged ); ?> covered tire<?php echo 1 === count( $unchanged ) ? '' : 's'; ?> whose price was not refreshed</summary>
                    <div class="rtg-details-body">
                    <div class="rtg-table-wrapper">
                    <table class="rtg-table rtg-table-compact">
                        <thead>
                            <tr><th>Tire</th><th>Size</th><th>Retailer</th><th>Reason</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $unchanged as $outcome ) : ?>
                            <tr>
                                <td><?php echo esc_html( trim( $outcome['brand'] . ' ' . $outcome['model'] ) ); ?></td>
                                <td class="rtg-mono"><?php echo esc_html( $outcome['size'] ); ?></td>
                                <td><?php echo esc_html( $outcome['retailer'] ?: '—' ); ?></td>
                                <td class="rtg-muted rtg-small"><?php echo esc_html( $outcome['label'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ( ! $price_results ) : ?>
                <p class="rtg-help">Prices refresh on the next discovery run.</p>
            <?php endif; ?>
        </div>
    </div>

    </div><!-- coverage panel -->

    <!-- ================================================================
         Settings
         ================================================================ -->
    <div class="rtg-tab-panel" data-tab-panel="settings" hidden>

    <form method="post">
        <?php wp_nonce_field( 'rtg_catalog_settings', 'rtg_catalog_settings_nonce' ); ?>

        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Discovery</h2>
                <p>What the daily run does and who hears about it.</p>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="catalog_sync_enabled">Daily check</label>
                    </div>
                    <label class="rtg-toggle is-small">
                        <input type="checkbox" name="catalog_sync_enabled" id="catalog_sync_enabled" value="1" <?php checked( $sync_enabled ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Check affiliate catalogs once a day</span>
                    </label>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="catalog_notify_enabled">Email digest</label>
                    </div>
                    <p class="rtg-field-description">Only newly surfaced tires are included. A run that finds nothing new sends nothing.</p>
                    <label class="rtg-toggle is-small">
                        <input type="checkbox" name="catalog_notify_enabled" id="catalog_notify_enabled" value="1" <?php checked( $notify_enabled ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Email me when a qualifying tire is found</span>
                    </label>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="health_alerts_enabled">Health alerts</label>
                    </div>
                    <p class="rtg-field-description">
                        The digest only fires on success, so without this every failure is silent: a rotated
                        CJ token failing each run, the daily schedule not firing, a fitment no longer being read
                        completely. Each problem emails once when it appears and once when it clears. Delistings
                        email as they are detected.
                    </p>
                    <label class="rtg-toggle is-small">
                        <input type="checkbox" name="health_alerts_enabled" id="health_alerts_enabled" value="1" <?php checked( $health_alerts ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Email me when discovery breaks, recovers, or a tire is dropped from the catalog</span>
                    </label>
                    <p class="rtg-help">
                        <strong>For a schedule that cannot silently die:</strong> WP-Cron only fires when the
                        site gets traffic. The reliable setup is a real server cron hitting
                        <code>wp-cron.php</code> every few minutes with <code>DISABLE_WP_CRON</code> set.
                        Most hosts have a checkbox for this. Until then, any wp-admin visit also checks and
                        will flag a schedule that has gone quiet.
                    </p>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="stale_price_report_enabled">Stale price report</label>
                    </div>
                    <p class="rtg-field-description">
                        Covered tires re-price themselves daily. The rest update only when someone edits them,
                        and a stale price is neither a broken link nor a failed run, so nothing else would
                        mention it. Monthly, listing tires untouched for
                        <?php echo esc_html( RTG_Stale_Prices::DEFAULT_STALE_DAYS ); ?>+ days, oldest first.
                    </p>
                    <label class="rtg-toggle is-small">
                        <input type="checkbox" name="stale_price_report_enabled" id="stale_price_report_enabled" value="1" <?php checked( $stale_price_report ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Email me monthly about prices only a person can refresh</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>What qualifies</h2>
                <p>Size, load index and load range are judged together, per vehicle: a tire has to be one of a platform's sizes, carry enough load for it, and be built to at least the platform's load range. A tire that clears no platform is filed under Near misses, naming what it fell short on.</p>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <span class="rtg-field-label">Minimums per vehicle</span>
                    </div>
                    <?php if ( ! empty( $vehicle_size_map ) ) : ?>
                        <div class="rtg-table-wrapper">
                            <table class="rtg-table rtg-table-compact is-inset">
                                <thead>
                                    <tr><th>Vehicle</th><th>Load index</th><th>Load range</th><th>Sizes</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ( $vehicle_size_map as $vehicle => $vehicle_sizes ) : ?>
                                    <?php
                                    $range_saved   = strtoupper( (string) ( $vehicle_range_saved[ $vehicle ] ?? '' ) );
                                    $range_default = RTG_Tire_Qualifier::VEHICLE_MIN_LOAD_RANGE[ $vehicle ] ?? '';
                                    ?>
                                    <tr>
                                        <td><label for="min_li_<?php echo esc_attr( $vehicle ); ?>"><strong><?php echo esc_html( $vehicle ); ?></strong></label></td>
                                        <td>
                                            <input type="number"
                                                name="catalog_vehicle_min_load_index[<?php echo esc_attr( $vehicle ); ?>]"
                                                id="min_li_<?php echo esc_attr( $vehicle ); ?>"
                                                value="<?php echo esc_attr( $vehicle_minimums[ $vehicle ] ?? '' ); ?>"
                                                min="100" max="126" class="rtg-input-tiny">
                                        </td>
                                        <td>
                                            <select name="catalog_vehicle_min_load_range[<?php echo esc_attr( $vehicle ); ?>]"
                                                id="min_lr_<?php echo esc_attr( $vehicle ); ?>"
                                                class="rtg-input-small"
                                                aria-label="<?php echo esc_attr( $vehicle ); ?> minimum load range">
                                                <option value="" <?php selected( '' === $range_saved ); ?>>Default (<?php echo $range_default ? esc_html( $range_default . ' or higher' ) : 'none'; ?>)</option>
                                                <option value="<?php echo esc_attr( RTG_Tire_Qualifier::LOAD_RANGE_NONE ); ?>" <?php selected( strtoupper( RTG_Tire_Qualifier::LOAD_RANGE_NONE ) === $range_saved ); ?>>No minimum</option>
                                                <?php foreach ( RTG_Tire_Qualifier::LOAD_RANGE_ORDER as $range_option ) : ?>
                                                    <option value="<?php echo esc_attr( $range_option ); ?>" <?php selected( $range_option === $range_saved ); ?>><?php echo esc_html( $range_option ); ?> or higher</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="rtg-muted rtg-small rtg-mono"><?php echo esc_html( implode( ', ', $vehicle_sizes ) ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="rtg-help">
                            Sizes come from <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-wheels' ) ); ?>">Wheels</a>,
                            so a platform added there appears here on its own. A blank load index restores the built-in figure
                            (R1 116, R2 112). Load range runs SL, XL, HL, then C to F; the R2 needs XL or higher, so an SL tire in an
                            R2 size is filed as a near miss. A listing that states no load range is surfaced with a note to confirm it.
                        </p>
                    <?php else : ?>
                        <input type="number" name="catalog_min_load_index" id="catalog_min_load_index" value="<?php echo esc_attr( $min_load_index ); ?>" min="100" max="126" class="rtg-input-small">
                        <p class="rtg-help">
                            No wheels are configured, so there is no vehicle map to judge against and this
                            single floor applies to every size. Add wheels under
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-wheels' ) ); ?>">Wheels</a>
                            to get per-platform rules.
                        </p>
                    <?php endif; ?>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="catalog_brand_policy">Brands outside your list</label>
                    </div>
                    <p class="rtg-field-description">
                        Retailer catalogs carry far more brands than the guide covers, and most of a first
                        run is usually marques you would never list. <strong>Surface them, flagged</strong> keeps
                        everything reviewable but marks an uncovered brand, so a newcomer worth covering
                        still reaches you. <strong>File them under Near misses</strong> keeps the queue
                        tight, at the cost of never seeing a new brand until you add it to the list.
                    </p>
                    <select name="catalog_brand_policy" id="catalog_brand_policy" class="rtg-input-medium">
                        <option value="<?php echo esc_attr( RTG_Tire_Qualifier::BRAND_POLICY_WARN ); ?>" <?php selected( $brand_policy, RTG_Tire_Qualifier::BRAND_POLICY_WARN ); ?>>
                            Surface them, flagged
                        </option>
                        <option value="<?php echo esc_attr( RTG_Tire_Qualifier::BRAND_POLICY_REJECT ); ?>" <?php selected( $brand_policy, RTG_Tire_Qualifier::BRAND_POLICY_REJECT ); ?>>
                            File them under Near misses
                        </option>
                        <option value="<?php echo esc_attr( RTG_Tire_Qualifier::BRAND_POLICY_OFF ); ?>" <?php selected( $brand_policy, RTG_Tire_Qualifier::BRAND_POLICY_OFF ); ?>>
                            Do not judge brand at all
                        </option>
                    </select>
                    <p class="rtg-help">
                        <?php if ( ! empty( $covered_brands ) ) : ?>
                            Currently covering <strong><?php echo count( $covered_brands ); ?></strong> brands:
                            <?php echo esc_html( implode( ', ', $covered_brands ) ); ?>.
                            Edit the list under <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-settings#tab-options' ) ); ?>">Settings, Dropdown options</a>.
                        <?php else : ?>
                            No brand list is configured, so this rule stays silent whatever it is set to.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Prices and links</h2>
                <p>What each run is allowed to change on tires already in the guide. Every decision is reported on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-affiliate-links' ) ); ?>">Affiliate Links</a> page.</p>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="price_sync_enabled">Price refresh</label>
                    </div>
                    <p class="rtg-field-description">
                        A price is taken only from the retailer the tire's own purchase link points to, so the
                        figure on the page always matches what a reader sees on click. A tire linked somewhere
                        discovery does not price, such as Amazon or a manufacturer, is left alone and listed on the coverage tab.
                    </p>
                    <label class="rtg-toggle is-small">
                        <input type="checkbox" name="price_sync_enabled" id="price_sync_enabled" value="1" <?php checked( $price_sync_enabled ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Refresh guide prices on each discovery run</span>
                    </label>
                    <div class="rtg-field-inline">
                        <label for="price_sync_max_change">Ignore changes larger than</label>
                        <input type="number" name="price_sync_max_change" id="price_sync_max_change" value="<?php echo esc_attr( $price_sync_max_change ); ?>" min="1" max="100" class="rtg-input-tiny">
                        <span>%</span>
                    </div>
                    <p class="rtg-help">
                        Tires are matched on brand, model and size, which can collide across load ratings. A
                        price that moves further than this is more likely to be that collision than a real sale,
                        so it is reported rather than written.
                    </p>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="link_sync_enabled">Link sync</label>
                    </div>
                    <p class="rtg-field-description">
                        A tire with <strong>no link</strong> gets the cheapest fresh tracked listing, and
                        price sync then follows that retailer. A tire with a <strong>plain retailer link</strong>
                        is upgraded to a tracked link for the <em>same</em> retailer only. A link that is
                        <strong>already affiliate is never touched</strong>, with one exception: when its
                        retailer has <strong>delisted the tire</strong> (unseen for
                        <?php echo esc_html( RTG_Link_Sync::FRESH_DAYS ); ?>+ days in a completely read
                        fitment) while another retailer still lists it with a tracked link, the link moves
                        to the retailer that carries the product. Only listings seen in the last
                        <?php echo esc_html( RTG_Link_Sync::FRESH_DAYS ); ?> days qualify as sources.
                    </p>
                    <label class="rtg-toggle is-small">
                        <input type="checkbox" name="link_sync_enabled" id="link_sync_enabled" value="1" <?php checked( $link_sync_enabled ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Fill and upgrade purchase links from the catalog daily</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="rtg-card">
            <div class="rtg-card-header is-split">
                <div>
                    <h2>CJ Affiliate</h2>
                    <p>Tire Rack and SimpleTire both run their affiliate programs on CJ, so one connection covers both. Discovery sends one request per tire size, scoped to the advertisers below.</p>
                </div>
                <?php if ( $cj_configured ) : ?>
                    <span class="rtg-badge rtg-badge-success">Configured</span>
                <?php else : ?>
                    <span class="rtg-badge rtg-badge-warning">Not configured</span>
                <?php endif; ?>
            </div>
            <div class="rtg-card-body">
                <?php if ( ! $cj_configured ) : ?>
                    <div class="rtg-notice rtg-notice-warning">
                        <span>Discovery has no source until CJ is configured. It needs the company ID, the website ID and a personal access token.</span>
                    </div>
                <?php endif; ?>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="cj_enabled">Use CJ</label>
                    </div>
                    <label class="rtg-toggle is-small">
                        <input type="checkbox" name="cj_enabled" id="cj_enabled" value="1" <?php checked( $cj_enabled ); ?>>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Pull candidates from the CJ Product Search API</span>
                    </label>
                </div>
                <div class="rtg-field-grid">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="cj_company_id">Company ID (CID)</label>
                        </div>
                        <p class="rtg-field-description">From CJ: Account, then Account Information.</p>
                        <input type="text" name="cj_company_id" id="cj_company_id" value="<?php echo esc_attr( $cj_company_id ); ?>" class="rtg-input-medium is-code" inputmode="numeric">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="cj_website_id">Website ID (PID)</label>
                        </div>
                        <p class="rtg-field-description">The first number in one of your CJ links: <code>click-<u>101098512</u>-13697786</code>.</p>
                        <input type="text" name="cj_website_id" id="cj_website_id" value="<?php echo esc_attr( $cj_website_id ); ?>" class="rtg-input-medium is-code" inputmode="numeric">
                    </div>
                </div>
                <p class="rtg-help">
                    With the website ID set, every product the sweep fetches carries a ready-made <em>tracked</em> click URL, which
                    is what lets link sync fill and upgrade purchase links automatically. Without
                    it, candidates only carry the retailer's plain URL, which pays nothing. After setting
                    it, use <strong>Test connection</strong>: the sample product's link should show a
                    tracking domain (tkqlhce.com or similar), not the retailer's.
                </p>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="cj_pat">Personal access token</label>
                        <?php if ( $cj_pat_constant ) : ?>
                            <span class="rtg-badge rtg-badge-info">Set in wp-config.php</span>
                        <?php elseif ( $cj_has_pat ) : ?>
                            <span class="rtg-badge rtg-badge-success">Saved</span>
                        <?php endif; ?>
                    </div>
                    <?php if ( $cj_pat_constant ) : ?>
                        <p class="rtg-field-description">Set via <code>RTG_CJ_PAT</code> in <code>wp-config.php</code>. This field is ignored while that constant is defined.</p>
                    <?php else : ?>
                        <p class="rtg-field-description">
                            Never displayed once saved. Better still, keep it out of the database entirely by adding
                            <code>define( 'RTG_CJ_PAT', '...' );</code> to <code>wp-config.php</code>, which takes precedence over this field.
                        </p>
                        <input type="password" name="cj_pat" id="cj_pat" value="" class="rtg-input-wide" autocomplete="off"
                            placeholder="<?php echo $cj_has_pat ? esc_attr( 'Saved. Leave blank to keep it.' ) : esc_attr( 'Paste your CJ token' ); ?>">
                        <?php if ( $cj_has_pat ) : ?>
                            <div class="rtg-choice-list">
                                <label class="rtg-choice"><input type="checkbox" name="cj_pat_clear" value="1"> Clear the saved token</label>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="cj_advertisers">Advertisers</label>
                    </div>
                    <p class="rtg-field-description">One per line, as <code>advertiserId|Name</code>. Leave blank for the defaults shown. Only advertisers you have joined return products.</p>
                    <textarea name="cj_advertisers" id="cj_advertisers" rows="3" class="rtg-input-wide is-code" placeholder="<?php echo esc_attr( $cj_advertiser_placeholder ); ?>"><?php echo esc_textarea( $cj_advertisers ); ?></textarea>
                </div>

                <h3 class="rtg-subsection-title">Run budget</h3>
                <p class="rtg-help">How much each run may read, and how long it may take. A shorter run costs time to complete, never coverage: both passes resume where they stopped.</p>

                <div class="rtg-field-grid">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="cj_limit">Records per size</label>
                        </div>
                        <p class="rtg-field-description">
                            How many products to request per tire size, one request each. A popular fitment can
                            carry several hundred, and anything beyond this is discarded by the retailer before it
                            reaches the queue, so a tire that plainly exists can look like nobody stocks it. When a
                            run comes back capped, the status says so and names the sizes.
                        </p>
                        <input type="number" name="cj_limit" id="cj_limit" value="<?php echo esc_attr( $cj_limit ); ?>" min="1" max="1000" class="rtg-input-small">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="cj_max_pages">Pages per size</label>
                        </div>
                        <p class="rtg-field-description">
                            How far to page through one size's matches before moving on, in pages of "Records per
                            size" each. Paging to the end of an unfiltered search would spend the whole budget on a
                            single size, so a size stops here and the status says how much it left behind.
                        </p>
                        <input type="number" name="cj_max_pages" id="cj_max_pages" value="<?php echo esc_attr( $cj_max_pages ); ?>" min="1" max="50" class="rtg-input-small">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="catalog_run_budget">Whole-run budget</label>
                        </div>
                        <p class="rtg-field-description">
                            A ceiling on the run as a whole, which the sweep and the direct lookups share.
                            <strong>Lower this first if Run discovery now fails with no reply.</strong>
                            This full budget applies to the nightly cron run. Run discovery now caps itself at
                            <?php echo esc_html( RTG_Catalog_Sync::INTERACTIVE_BUDGET ); ?>s regardless, because the
                            proxy in front of the site stops waiting for a browser request after about 100 seconds.
                        </p>
                        <div class="rtg-field-inline">
                            <input type="number" name="catalog_run_budget" id="catalog_run_budget" value="<?php echo esc_attr( $catalog_run_budget ); ?>" min="30" max="900" class="rtg-input-small">
                            <span>seconds</span>
                        </div>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="cj_sweep_budget">Sweep time budget</label>
                        </div>
                        <p class="rtg-field-description">
                            How long a sweep may spend fetching before it stops and reports the sizes it did not
                            reach. Lower it if your host has a tight PHP execution limit; raise it if the status
                            says sizes went unchecked.
                        </p>
                        <div class="rtg-field-inline">
                            <input type="number" name="cj_sweep_budget" id="cj_sweep_budget" value="<?php echo esc_attr( $cj_sweep_budget ); ?>" min="15" max="600" class="rtg-input-small">
                            <span>seconds</span>
                        </div>
                    </div>
                </div>

                <h3 class="rtg-subsection-title">Query and connection test</h3>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="cj_query">GraphQL query</label>
                    </div>
                    <p class="rtg-field-description">
                        Leave blank to use the shipped query. If Test connection reports a GraphQL error naming a field,
                        correct it here rather than waiting on a plugin update. The response mapping accepts several
                        field spellings, so only the query itself usually needs changing.
                    </p>
                    <textarea name="cj_query" id="cj_query" rows="10" class="rtg-input-wide is-code" spellcheck="false" placeholder="<?php echo esc_attr( RTG_Catalog_Source_CJ::DEFAULT_QUERY ); ?>"><?php echo esc_textarea( $cj_query ); ?></textarea>
                </div>
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="rtg-cj-test-keyword">Test connection</label>
                    </div>
                    <p class="rtg-field-description">
                        Any keyword may be probed here, and the reply lists the titles it returned. That is how to
                        tell whether CJ is <em>matching</em> a term or merely ranking against it: type a tire's
                        full name and see whether that tire is anywhere in the answer. Blank uses the first
                        guide size, which tests the connection itself.
                    </p>
                    <div class="rtg-input-row">
                        <input type="text" id="rtg-cj-test-keyword" placeholder="Michelin Defender LTX M/S2 305/45R22" aria-label="Keyword to probe">
                        <input type="number" id="rtg-cj-test-offset" min="0" step="1000" value="0" class="rtg-input-small" placeholder="offset" title="Records to skip" aria-label="Offset">
                        <button type="button" id="rtg-cj-test-btn" class="rtg-btn rtg-btn-secondary">Test connection</button>
                    </div>
                    <p class="rtg-help">
                        <strong>The offset is how to check that paging works.</strong> Probe a size at 0, then
                        the same size at 1000. Different titles mean the sweep really is reading deeper each
                        page. <em>The same titles mean it is re-reading page one</em>, and a sweep that
                        counts what came back rather than what was new would still call that fitment complete.
                    </p>
                    <div id="rtg-cj-test-result" class="rtg-test-result" style="display:none;"></div>
                </div>
            </div>
        </div>

        <div class="rtg-footer-actions is-sticky">
            <button type="submit" name="rtg_catalog_settings_save" value="1" class="rtg-btn rtg-btn-primary">Save settings</button>
            <span class="rtg-footer-end">
                <span class="rtg-unsaved">Unsaved changes</span>
            </span>
        </div>
    </form>

    </div><!-- settings panel -->

    </div><!-- tabs -->

</div>
