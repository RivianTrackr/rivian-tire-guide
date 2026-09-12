<?php
/**
 * Dashboard: what needs doing today, then how the catalog looks.
 *
 * The page leads with the attention list because that is what an admin
 * opens it for. Everything below it is context.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Fetch all dashboard stats in one call.
$stats = RTG_Database::get_dashboard_stats();

$core            = $stats['core'];
$total_tires     = (int) ( $core['total_tires'] ?? 0 );
$avg_price       = floatval( $core['avg_price'] ?? 0 );
$total_reviews   = (int) ( $stats['ratings']['total_ratings'] ?? 0 );
$avg_rating      = floatval( $stats['ratings']['avg_rating'] ?? 0 );

// Price range.
$min_price = floatval( $core['min_price'] ?? 0 );
$max_price = floatval( $core['max_price'] ?? 0 );

// Weight range.
$min_weight = floatval( $core['min_weight'] ?? 0 );
$max_weight = floatval( $core['max_weight'] ?? 0 );
$avg_weight = floatval( $core['avg_weight'] ?? 0 );

// Missing data counts.
$missing_images = (int) ( $core['missing_images'] ?? 0 );
$missing_links  = (int) ( $core['missing_links'] ?? 0 );

// Roamer data.
$roamer_linked       = (int) ( $core['roamer_linked'] ?? 0 );
$avg_roamer_eff      = floatval( $core['avg_roamer_efficiency'] ?? 0 );
$max_roamer_eff      = floatval( $core['max_roamer_efficiency'] ?? 0 );
$min_roamer_eff      = floatval( $core['min_roamer_efficiency'] ?? 0 );
$total_roamer_km     = floatval( $core['total_roamer_km'] ?? 0 );
$total_roamer_veh    = (int) ( $core['total_roamer_vehicles'] ?? 0 );
$roamer_pct          = $total_tires > 0 ? round( ( $roamer_linked / $total_tires ) * 100 ) : 0;
$roamer_sync_stats   = RTG_Roamer_Sync::get_stats();

// Affiliate link coverage.
$affiliate_count = (int) ( $stats['affiliate_count'] ?? 0 );
$affiliate_pct   = $total_tires > 0 ? round( ( $affiliate_count / $total_tires ) * 100 ) : 0;

// Pending reviews.
$pending_reviews = (int) ( $stats['pending_reviews'] ?? 0 );

// Broken affiliate links.
$broken_links       = RTG_Link_Checker::get_broken_tire_ids();
$broken_link_count  = count( $broken_links );
$link_check_results = RTG_Link_Checker::get_results();
$last_link_check    = ! empty( $link_check_results['checked_at'] ) ? $link_check_results['checked_at'] : '';

// The discovery queue and the health of the run that feeds it.
$candidate_counts    = RTG_Candidates::get_counts();
$awaiting_candidates = (int) ( $candidate_counts[ RTG_Candidates::STATUS_NEW ] ?? 0 );
$discovery_stats     = RTG_Catalog_Sync::get_stats();
$discovery_issues    = RTG_Health::evaluate(
    $discovery_stats,
    RTG_Admin::get_dropdown_options( 'sizes' ),
    current_time( 'timestamp' )
);
$roamer_failed = $roamer_sync_stats && 'error' === ( $roamer_sync_stats['status'] ?? '' );

// Helper: find max count in a grouped result for bar widths.
$max_of = function ( $rows ) {
    $max = 0;
    foreach ( $rows as $row ) {
        if ( (int) $row['count'] > $max ) {
            $max = (int) $row['count'];
        }
    }
    return max( $max, 1 );
};

// Everything that wants a decision, most urgent first. Each item is a
// label, a sentence, a severity and a button. Items that are fine are
// listed after, dimmed, so "all clear" is visibly true rather than assumed.
$attention = array();

if ( ! empty( $discovery_issues ) ) {
    $attention[] = array(
        'level'  => 'error',
        'icon'   => 'dashicons-warning',
        'title'  => 'Tire Discovery needs a look',
        'text'   => reset( $discovery_issues ),
        'action' => array( 'Open Discovery', admin_url( 'admin.php?page=rtg-tire-discovery' ) ),
    );
}
if ( $roamer_failed ) {
    $attention[] = array(
        'level'  => 'error',
        'icon'   => 'dashicons-warning',
        'title'  => 'The last Roamer sync failed',
        'text'   => (string) ( $roamer_sync_stats['message'] ?? 'No reason was recorded.' ),
        'action' => array( 'Open Roamer Data', admin_url( 'admin.php?page=rtg-roamer-sync' ) ),
    );
}
if ( $pending_reviews > 0 ) {
    $attention[] = array(
        'level'  => 'warning',
        'icon'   => 'dashicons-clock',
        'title'  => sprintf( '%d review%s waiting for moderation', $pending_reviews, 1 === $pending_reviews ? '' : 's' ),
        'text'   => 'Owners see their review on the tire page once it is approved.',
        'action' => array( 'Moderate', admin_url( 'admin.php?page=rtg-reviews&status=pending' ) ),
    );
}
if ( $awaiting_candidates > 0 ) {
    $attention[] = array(
        'level'  => 'info',
        'icon'   => 'dashicons-search',
        'title'  => sprintf( '%d discovered tire%s to review', $awaiting_candidates, 1 === $awaiting_candidates ? '' : 's' ),
        'text'   => 'Retailer listings in a Rivian size that are not in the guide yet.',
        'action' => array( 'Review queue', admin_url( 'admin.php?page=rtg-tire-discovery' ) ),
    );
}
if ( $broken_link_count > 0 ) {
    $attention[] = array(
        'level'  => 'error',
        'icon'   => 'dashicons-admin-links',
        'title'  => sprintf( '%d broken affiliate link%s', $broken_link_count, 1 === $broken_link_count ? '' : 's' ),
        'text'   => 'Links that land on a retailer homepage instead of the product.',
        'action' => array( 'Fix links', admin_url( 'admin.php?page=rtg-affiliate-links&link_filter=broken' ) ),
    );
}
if ( $missing_links > 0 ) {
    $attention[] = array(
        'level'  => 'warning',
        'icon'   => 'dashicons-admin-links',
        'title'  => sprintf( '%d tire%s with no purchase link', $missing_links, 1 === $missing_links ? '' : 's' ),
        'text'   => 'A card with no link earns nothing when a shopper decides.',
        'action' => array( 'Add links', admin_url( 'admin.php?page=rtg-affiliate-links&link_filter=missing' ) ),
    );
}
if ( $missing_images > 0 ) {
    $attention[] = array(
        'level'  => 'warning',
        'icon'   => 'dashicons-format-image',
        'title'  => sprintf( '%d tire%s with no image', $missing_images, 1 === $missing_images ? '' : 's' ),
        'text'   => 'The card shows a blank where the tire photo should be.',
        'action' => array( 'View tires', admin_url( 'admin.php?page=rtg-tires' ) ),
    );
}

$all_clear = array(
    array( 'Reviews', 0 === $pending_reviews, 'All reviews moderated.' ),
    array( 'Discovery queue', 0 === $awaiting_candidates, 'Nothing waiting for review.' ),
    array( 'Affiliate links', 0 === $broken_link_count && 0 === $missing_links, $last_link_check ? 'Every tire has a link and the last check found them all working.' : 'Every tire has a link. No link check has run yet.' ),
    array( 'Images', 0 === $missing_images, 'Every tire has an image.' ),
);
?>

<div class="rtg-wrap">

    <div class="rtg-page-header">
        <div class="rtg-page-heading">
            <h1 class="rtg-page-title">Dashboard</h1>
            <p class="rtg-page-subtitle">
                <?php echo esc_html( number_format( $total_tires ) ); ?> tires in the guide,
                <?php echo esc_html( number_format( $total_reviews ) ); ?> owner reviews.
            </p>
        </div>
        <div class="rtg-page-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit' ) ); ?>" class="rtg-btn rtg-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span> Add tire
            </a>
        </div>
    </div>

    <!-- ================================================================
         Needs attention
         ================================================================ -->
    <div class="rtg-card">
        <div class="rtg-card-header is-split">
            <div>
                <h2>Needs attention</h2>
                <p>Everything that wants a decision, most urgent first.</p>
            </div>
            <?php if ( empty( $attention ) ) : ?>
                <span class="rtg-badge rtg-badge-success">All clear</span>
            <?php else : ?>
                <span class="rtg-badge rtg-badge-warning"><?php echo count( $attention ); ?> item<?php echo 1 === count( $attention ) ? '' : 's'; ?></span>
            <?php endif; ?>
        </div>
        <div class="rtg-card-body">
            <?php foreach ( $attention as $item ) : ?>
                <div class="rtg-health-item">
                    <span class="rtg-health-icon rtg-health-icon-<?php echo esc_attr( $item['level'] ); ?>">
                        <span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
                    </span>
                    <span class="rtg-health-content">
                        <strong><?php echo esc_html( $item['title'] ); ?></strong>
                        <p><?php echo esc_html( $item['text'] ); ?></p>
                    </span>
                    <span class="rtg-health-action">
                        <a href="<?php echo esc_url( $item['action'][1] ); ?>" class="rtg-btn rtg-btn-secondary rtg-btn-sm"><?php echo esc_html( $item['action'][0] ); ?></a>
                    </span>
                </div>
            <?php endforeach; ?>

            <?php foreach ( $all_clear as $ok ) : ?>
                <?php if ( ! $ok[1] ) { continue; } ?>
                <div class="rtg-health-item is-ok">
                    <span class="rtg-health-icon rtg-health-icon-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </span>
                    <span class="rtg-health-content">
                        <strong><?php echo esc_html( $ok[0] ); ?></strong>
                        <p><?php echo esc_html( $ok[2] ); ?></p>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================================================================
         Overview
         ================================================================ -->
    <div class="rtg-stats-grid">
        <a class="rtg-stat-card" href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires' ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( number_format( $total_tires ) ); ?></div>
            <div class="rtg-stat-label">Tires</div>
        </a>
        <a class="rtg-stat-card" href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-reviews' ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( number_format( $total_reviews ) ); ?><?php if ( $avg_rating > 0 ) : ?> <span class="rtg-stat-sub"><?php echo esc_html( number_format( $avg_rating, 1 ) ); ?>★</span><?php endif; ?></div>
            <div class="rtg-stat-label">Owner reviews</div>
        </a>
        <a class="rtg-stat-card <?php echo $affiliate_pct < 100 ? 'is-warning' : 'is-success'; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-affiliate-links' ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( $affiliate_pct ); ?>%</div>
            <div class="rtg-stat-label">Affiliate coverage</div>
        </a>
        <a class="rtg-stat-card" href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-roamer-sync' ) ); ?>">
            <div class="rtg-stat-value"><?php echo esc_html( $roamer_pct ); ?>%</div>
            <div class="rtg-stat-label">Roamer linked</div>
        </a>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value">$<?php echo esc_html( number_format( $avg_price, 0 ) ); ?></div>
            <div class="rtg-stat-label">Average price</div>
        </div>
    </div>

    <!-- ================================================================
         Catalog breakdowns
         ================================================================ -->
    <div class="rtg-dashboard-grid is-thirds">

        <?php
        $breakdowns = array(
            array( 'Tires by category', 'by_category', 'category' ),
            array( 'Top brands', 'by_brand', 'brand' ),
            array( 'Tires by size', 'by_size', 'size' ),
        );
        foreach ( $breakdowns as $bd ) :
            list( $bd_title, $bd_key, $bd_field ) = $bd;
            $rows = $stats[ $bd_key ] ?? array();
        ?>
            <div class="rtg-card">
                <div class="rtg-card-header"><h2><?php echo esc_html( $bd_title ); ?></h2></div>
                <div class="rtg-card-body">
                    <?php if ( empty( $rows ) ) : ?>
                        <p class="rtg-empty-line">No data yet.</p>
                    <?php else : ?>
                        <?php $bd_max = $max_of( $rows ); ?>
                        <ul class="rtg-bar-list">
                            <?php foreach ( $rows as $row ) : ?>
                                <li class="rtg-bar-item">
                                    <span class="rtg-bar-label" title="<?php echo esc_attr( $row[ $bd_field ] ); ?>"><?php echo esc_html( $row[ $bd_field ] ); ?></span>
                                    <span class="rtg-bar-track">
                                        <span class="rtg-bar-fill" style="width: <?php echo esc_attr( round( ( (int) $row['count'] / $bd_max ) * 100 ) ); ?>%;"></span>
                                    </span>
                                    <span class="rtg-bar-count"><?php echo esc_html( $row['count'] ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- ================================================================
         Community and price / weight
         ================================================================ -->
    <div class="rtg-dashboard-grid is-thirds">

        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Top rated</h2></div>
            <div class="rtg-card-body">
                <?php if ( empty( $stats['top_rated'] ) ) : ?>
                    <p class="rtg-empty-line">No reviews yet.</p>
                <?php else : ?>
                    <ul class="rtg-mini-list">
                        <?php foreach ( $stats['top_rated'] as $i => $tire ) : ?>
                            <li class="rtg-mini-list-item">
                                <span class="rtg-mini-list-rank"><?php echo esc_html( $i + 1 ); ?></span>
                                <?php if ( ! empty( $tire['image'] ) ) : ?>
                                    <img src="<?php echo esc_url( $tire['image'] ); ?>" alt="" class="rtg-mini-list-thumb">
                                <?php endif; ?>
                                <span class="rtg-mini-list-info">
                                    <span class="rtg-mini-list-name">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&tire_id=' . rawurlencode( $tire['tire_id'] ) ) ); ?>"><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></a>
                                    </span>
                                    <span class="rtg-mini-list-meta"><?php echo esc_html( $tire['rating_count'] ); ?> review<?php echo (int) $tire['rating_count'] !== 1 ? 's' : ''; ?></span>
                                </span>
                                <span class="rtg-mini-list-value"><?php echo esc_html( $tire['avg_rating'] ); ?> / 5</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Most reviewed</h2></div>
            <div class="rtg-card-body">
                <?php if ( empty( $stats['most_reviewed'] ) ) : ?>
                    <p class="rtg-empty-line">No reviews yet.</p>
                <?php else : ?>
                    <ul class="rtg-mini-list">
                        <?php foreach ( $stats['most_reviewed'] as $i => $tire ) : ?>
                            <li class="rtg-mini-list-item">
                                <span class="rtg-mini-list-rank"><?php echo esc_html( $i + 1 ); ?></span>
                                <?php if ( ! empty( $tire['image'] ) ) : ?>
                                    <img src="<?php echo esc_url( $tire['image'] ); ?>" alt="" class="rtg-mini-list-thumb">
                                <?php endif; ?>
                                <span class="rtg-mini-list-info">
                                    <span class="rtg-mini-list-name">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-reviews&s=' . urlencode( $tire['tire_id'] ) ) ); ?>"><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></a>
                                    </span>
                                    <span class="rtg-mini-list-meta">Avg <?php echo esc_html( $tire['avg_rating'] ); ?> / 5</span>
                                </span>
                                <span class="rtg-mini-list-value"><?php echo esc_html( $tire['review_count'] ); ?> review<?php echo (int) $tire['review_count'] !== 1 ? 's' : ''; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Price and weight</h2></div>
            <div class="rtg-card-body">
                <div class="rtg-kv-grid">
                    <div>
                        <span class="rtg-kv-label">Lowest price</span>
                        <div class="rtg-kv-value">$<?php echo esc_html( number_format( $min_price, 0 ) ); ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Average</span>
                        <div class="rtg-kv-value">$<?php echo esc_html( number_format( $avg_price, 0 ) ); ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Highest</span>
                        <div class="rtg-kv-value">$<?php echo esc_html( number_format( $max_price, 0 ) ); ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Lightest</span>
                        <div class="rtg-kv-value"><?php echo esc_html( $min_weight ); ?> <span class="rtg-stat-sub">lb</span></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Average weight</span>
                        <div class="rtg-kv-value"><?php echo esc_html( $avg_weight ); ?> <span class="rtg-stat-sub">lb</span></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Heaviest</span>
                        <div class="rtg-kv-value"><?php echo esc_html( $max_weight ); ?> <span class="rtg-stat-sub">lb</span></div>
                    </div>
                </div>
                <p class="rtg-help">
                    Affiliate links on <?php echo esc_html( $affiliate_count ); ?> of <?php echo esc_html( $total_tires ); ?> tires.
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-affiliate-links' ) ); ?>">Manage links</a>
                </p>
            </div>
        </div>

    </div>

    <!-- ================================================================
         Rivian Roamer: real-world efficiency
         ================================================================ -->
    <div class="rtg-dashboard-grid">

        <div class="rtg-card">
            <div class="rtg-card-header is-split">
                <div>
                    <h2>Real-world efficiency</h2>
                    <p>Miles per kWh reported by Rivian Roamer owners, matched to the guide's tires.</p>
                </div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-roamer-sync' ) ); ?>" class="rtg-btn rtg-btn-secondary rtg-btn-sm">Manage</a>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-kv-grid">
                    <div>
                        <span class="rtg-kv-label">Tires linked</span>
                        <div class="rtg-kv-value"><?php echo esc_html( $roamer_linked ); ?> <span class="rtg-stat-sub">/ <?php echo esc_html( $total_tires ); ?></span></div>
                        <div class="rtg-kv-note"><?php echo esc_html( $roamer_pct ); ?>% of the guide</div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Average mi/kWh</span>
                        <div class="rtg-kv-value"><?php echo $avg_roamer_eff > 0 ? esc_html( number_format( $avg_roamer_eff, 2 ) ) : '&mdash;'; ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Best</span>
                        <div class="rtg-kv-value is-success"><?php echo $max_roamer_eff > 0 ? esc_html( number_format( $max_roamer_eff, 2 ) ) : '&mdash;'; ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Worst</span>
                        <div class="rtg-kv-value is-muted"><?php echo $min_roamer_eff > 0 ? esc_html( number_format( $min_roamer_eff, 2 ) ) : '&mdash;'; ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Miles tracked</span>
                        <div class="rtg-kv-value"><?php echo esc_html( number_format( $total_roamer_km * 0.621371, 0 ) ); ?></div>
                    </div>
                    <div>
                        <span class="rtg-kv-label">Vehicles</span>
                        <div class="rtg-kv-value"><?php echo esc_html( number_format( $total_roamer_veh ) ); ?></div>
                    </div>
                </div>
                <?php if ( $roamer_sync_stats && ! empty( $roamer_sync_stats['time'] ) ) : ?>
                    <p class="rtg-help">
                        Last sync <?php echo esc_html( human_time_diff( strtotime( $roamer_sync_stats['time'] ), current_time( 'timestamp' ) ) ); ?> ago<?php
                        if ( 'success' === $roamer_sync_stats['status'] ) {
                            printf(
                                ': %d matched, %d ambiguous, %d unmatched.',
                                intval( $roamer_sync_stats['matched'] ),
                                intval( $roamer_sync_stats['skipped'] ),
                                intval( $roamer_sync_stats['unmatched'] )
                            );
                        } else {
                            echo '.';
                        }
                        ?>
                    </p>
                <?php else : ?>
                    <p class="rtg-help">No sync has run yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-roamer-sync' ) ); ?>">Run the first sync</a>.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Most efficient in the real world</h2></div>
            <div class="rtg-card-body">
                <?php if ( empty( $stats['top_roamer'] ) ) : ?>
                    <p class="rtg-empty-line">No Roamer data linked yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-roamer-sync' ) ); ?>">Sync now</a>.</p>
                <?php else : ?>
                    <ul class="rtg-mini-list">
                        <?php foreach ( $stats['top_roamer'] as $i => $tire ) : ?>
                            <li class="rtg-mini-list-item">
                                <span class="rtg-mini-list-rank"><?php echo esc_html( $i + 1 ); ?></span>
                                <?php if ( ! empty( $tire['image'] ) ) : ?>
                                    <img src="<?php echo esc_url( $tire['image'] ); ?>" alt="" class="rtg-mini-list-thumb">
                                <?php endif; ?>
                                <span class="rtg-mini-list-info">
                                    <span class="rtg-mini-list-name">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&tire_id=' . rawurlencode( $tire['tire_id'] ) ) ); ?>"><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></a>
                                    </span>
                                    <span class="rtg-mini-list-meta"><?php echo esc_html( $tire['size'] ); ?> · <?php echo esc_html( number_format( floatval( $tire['roamer_total_km'] ?? 0 ) * 0.621371, 0 ) ); ?> mi tracked</span>
                                </span>
                                <span class="rtg-mini-list-value is-info"><?php echo esc_html( number_format( $tire['roamer_efficiency'], 2 ) ); ?> mi/kWh</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ================================================================
         Recently added
         ================================================================ -->
    <div class="rtg-card">
        <div class="rtg-card-header is-split">
            <div><h2>Recently added</h2></div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires' ) ); ?>" class="rtg-btn rtg-btn-secondary rtg-btn-sm">All tires</a>
        </div>
        <div class="rtg-card-body">
            <?php if ( empty( $stats['recent_tires'] ) ) : ?>
                <p class="rtg-empty-line">No tires added yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit' ) ); ?>">Add your first tire</a>.</p>
            <?php else : ?>
                <ul class="rtg-mini-list">
                    <?php foreach ( $stats['recent_tires'] as $tire ) : ?>
                        <li class="rtg-mini-list-item">
                            <?php if ( ! empty( $tire['image'] ) ) : ?>
                                <img src="<?php echo esc_url( $tire['image'] ); ?>" alt="" class="rtg-mini-list-thumb">
                            <?php else : ?>
                                <span class="rtg-mini-list-thumb"></span>
                            <?php endif; ?>
                            <span class="rtg-mini-list-info">
                                <span class="rtg-mini-list-name">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&tire_id=' . rawurlencode( $tire['tire_id'] ) ) ); ?>"><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></a>
                                </span>
                                <span class="rtg-mini-list-meta"><?php echo esc_html( $tire['category'] ); ?></span>
                            </span>
                            <span class="rtg-mini-list-value is-muted">
                                <?php echo esc_html( date( 'M j, Y', strtotime( $tire['created_at'] ) ) ); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

</div>
