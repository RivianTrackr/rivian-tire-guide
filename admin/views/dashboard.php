<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Fetch all dashboard stats in one call.
$stats = RTG_Database::get_dashboard_stats();

$core            = $stats['core'];
$total_tires     = (int) ( $core['total_tires'] ?? 0 );
$avg_price       = floatval( $core['avg_price'] ?? 0 );
$avg_efficiency  = (int) ( $core['avg_efficiency'] ?? 0 );
$total_reviews   = (int) ( $stats['ratings']['total_ratings'] ?? 0 );

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

// Affiliate link coverage.
$affiliate_count = (int) ( $stats['affiliate_count'] ?? 0 );
$affiliate_pct   = $total_tires > 0 ? round( ( $affiliate_count / $total_tires ) * 100 ) : 0;

// Pending reviews.
$pending_reviews = (int) ( $stats['pending_reviews'] ?? 0 );

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

// Grade colors map.
$grade_colors = array(
    'A' => '#34c759',
    'B' => '#7dc734',
    'C' => '#facc15',
    'D' => '#f97316',
    'E' => '#ef4444',
    'F' => '#b91c1c',
);
?>

<div class="rtg-wrap">

    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Dashboard</h1>
    </div>

    <!-- ================================================================
         Overview Cards
         ================================================================ -->
    <div class="rtg-stats-grid">
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $total_tires ); ?></div>
            <div class="rtg-stat-label">Total Tires</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value">$<?php echo esc_html( number_format( $avg_price, 2 ) ); ?></div>
            <div class="rtg-stat-label">Average Price</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $avg_efficiency ); ?></div>
            <div class="rtg-stat-label">Avg Efficiency Score</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $total_reviews ); ?></div>
            <div class="rtg-stat-label">Total Reviews</div>
        </div>
    </div>

    <!-- ================================================================
         Breakdowns: Two-Column Grid
         ================================================================ -->
    <div class="rtg-dashboard-grid">

        <!-- Tires by Category -->
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Tires by Category</h2></div>
            <div class="rtg-card-body">
                <?php if ( empty( $stats['by_category'] ) ) : ?>
                    <p style="color: var(--rtg-text-muted);">No category data available.</p>
                <?php else : ?>
                    <?php $max_cat = $max_of( $stats['by_category'] ); ?>
                    <ul class="rtg-bar-list">
                        <?php foreach ( $stats['by_category'] as $row ) : ?>
                            <li class="rtg-bar-item">
                                <span class="rtg-bar-label"><?php echo esc_html( $row['category'] ); ?></span>
                                <span class="rtg-bar-track">
                                    <span class="rtg-bar-fill" style="width: <?php echo esc_attr( round( ( (int) $row['count'] / $max_cat ) * 100 ) ); ?>%;"></span>
                                </span>
                                <span class="rtg-bar-count"><?php echo esc_html( $row['count'] ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tires by Brand (Top 10) -->
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Top Brands</h2></div>
            <div class="rtg-card-body">
                <?php if ( empty( $stats['by_brand'] ) ) : ?>
                    <p style="color: var(--rtg-text-muted);">No brand data available.</p>
                <?php else : ?>
                    <?php $max_brand = $max_of( $stats['by_brand'] ); ?>
                    <ul class="rtg-bar-list">
                        <?php foreach ( $stats['by_brand'] as $row ) : ?>
                            <li class="rtg-bar-item">
                                <span class="rtg-bar-label"><?php echo esc_html( $row['brand'] ); ?></span>
                                <span class="rtg-bar-track">
                                    <span class="rtg-bar-fill" style="width: <?php echo esc_attr( round( ( (int) $row['count'] / $max_brand ) * 100 ) ); ?>%;"></span>
                                </span>
                                <span class="rtg-bar-count"><?php echo esc_html( $row['count'] ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tires by Size -->
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Tires by Size</h2></div>
            <div class="rtg-card-body">
                <?php if ( empty( $stats['by_size'] ) ) : ?>
                    <p style="color: var(--rtg-text-muted);">No size data available.</p>
                <?php else : ?>
                    <?php $max_size = $max_of( $stats['by_size'] ); ?>
                    <ul class="rtg-bar-list">
                        <?php foreach ( $stats['by_size'] as $row ) : ?>
                            <li class="rtg-bar-item">
                                <span class="rtg-bar-label"><?php echo esc_html( $row['size'] ); ?></span>
                                <span class="rtg-bar-track">
                                    <span class="rtg-bar-fill" style="width: <?php echo esc_attr( round( ( (int) $row['count'] / $max_size ) * 100 ) ); ?>%;"></span>
                                </span>
                                <span class="rtg-bar-count"><?php echo esc_html( $row['count'] ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Efficiency Grade Distribution -->
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Efficiency Grade Distribution</h2></div>
            <div class="rtg-card-body">
                <?php if ( empty( $stats['by_grade'] ) ) : ?>
                    <p style="color: var(--rtg-text-muted);">No grade data available.</p>
                <?php else : ?>
                    <?php $max_grade = $max_of( $stats['by_grade'] ); ?>
                    <ul class="rtg-bar-list rtg-grade-bar-list">
                        <?php foreach ( $stats['by_grade'] as $row ) :
                            $g     = strtoupper( $row['efficiency_grade'] );
                            $color = $grade_colors[ $g ] ?? '#d2d2d7';
                        ?>
                            <li class="rtg-bar-item">
                                <span class="rtg-bar-label">
                                    <span class="rtg-badge rtg-grade-<?php echo esc_attr( strtolower( $g ) ); ?>"><?php echo esc_html( $g ); ?></span>
                                </span>
                                <span class="rtg-bar-track">
                                    <span class="rtg-bar-fill" style="width: <?php echo esc_attr( round( ( (int) $row['count'] / $max_grade ) * 100 ) ); ?>%; background: <?php echo esc_attr( $color ); ?>;"></span>
                                </span>
                                <span class="rtg-bar-count"><?php echo esc_html( $row['count'] ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- .rtg-dashboard-grid -->

    <!-- ================================================================
         Key Insights: Two-Column Grid
         ================================================================ -->
    <div class="rtg-dashboard-grid">

        <!-- Price & Weight Ranges -->
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Key Insights</h2></div>
            <div class="rtg-card-body">
                <div class="rtg-stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 16px;">
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value">$<?php echo esc_html( number_format( $min_price, 0 ) ); ?></div>
                        <div class="rtg-stat-label">Lowest Price</div>
                    </div>
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value">$<?php echo esc_html( number_format( $avg_price, 0 ) ); ?></div>
                        <div class="rtg-stat-label">Avg Price</div>
                    </div>
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value">$<?php echo esc_html( number_format( $max_price, 0 ) ); ?></div>
                        <div class="rtg-stat-label">Highest Price</div>
                    </div>
                </div>
                <div class="rtg-stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 16px;">
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value"><?php echo esc_html( $min_weight ); ?> lb</div>
                        <div class="rtg-stat-label">Lightest</div>
                    </div>
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value"><?php echo esc_html( $avg_weight ); ?> lb</div>
                        <div class="rtg-stat-label">Avg Weight</div>
                    </div>
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value"><?php echo esc_html( $max_weight ); ?> lb</div>
                        <div class="rtg-stat-label">Heaviest</div>
                    </div>
                </div>
                <div class="rtg-stats-grid" style="grid-template-columns: 1fr; margin-bottom: 0;">
                    <div class="rtg-stat-card">
                        <div class="rtg-stat-value"><?php echo esc_html( $affiliate_pct ); ?>%</div>
                        <div class="rtg-stat-label">Affiliate Link Coverage (<?php echo esc_html( $affiliate_count ); ?>/<?php echo esc_html( $total_tires ); ?> tires)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Rated Tires -->
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Top Rated Tires</h2></div>
            <div class="rtg-card-body">
                <?php if ( empty( $stats['top_rated'] ) ) : ?>
                    <p style="color: var(--rtg-text-muted);">No reviews yet.</p>
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
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires&s=' . urlencode( $tire['tire_id'] ) ) ); ?>"><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></a>
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

        <!-- Most Reviewed Tires -->
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Most Reviewed</h2></div>
            <div class="rtg-card-body">
                <?php if ( empty( $stats['most_reviewed'] ) ) : ?>
                    <p style="color: var(--rtg-text-muted);">No reviews yet.</p>
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
                                    <span class="rtg-mini-list-meta">Avg: <?php echo esc_html( $tire['avg_rating'] ); ?> / 5</span>
                                </span>
                                <span class="rtg-mini-list-value"><?php echo esc_html( $tire['review_count'] ); ?> review<?php echo (int) $tire['review_count'] !== 1 ? 's' : ''; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content Health -->
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Content Health</h2></div>
            <div class="rtg-card-body">
                <!-- Pending Reviews -->
                <div class="rtg-health-item">
                    <span class="rtg-health-icon <?php echo $pending_reviews > 0 ? 'rtg-health-icon-warning' : 'rtg-health-icon-success'; ?>">
                        <span class="dashicons <?php echo $pending_reviews > 0 ? 'dashicons-clock' : 'dashicons-yes-alt'; ?>"></span>
                    </span>
                    <span class="rtg-health-content">
                        <strong><?php echo esc_html( $pending_reviews ); ?> Pending Review<?php echo $pending_reviews !== 1 ? 's' : ''; ?></strong>
                        <p><?php echo $pending_reviews > 0 ? 'Reviews awaiting moderation.' : 'All reviews moderated.'; ?></p>
                    </span>
                    <?php if ( $pending_reviews > 0 ) : ?>
                        <span class="rtg-health-action">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-reviews&status=pending' ) ); ?>" class="rtg-btn rtg-btn-secondary" style="text-decoration:none;">Review</a>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Missing Images -->
                <div class="rtg-health-item">
                    <span class="rtg-health-icon <?php echo $missing_images > 0 ? 'rtg-health-icon-error' : 'rtg-health-icon-success'; ?>">
                        <span class="dashicons <?php echo $missing_images > 0 ? 'dashicons-format-image' : 'dashicons-yes-alt'; ?>"></span>
                    </span>
                    <span class="rtg-health-content">
                        <strong><?php echo esc_html( $missing_images ); ?> Tire<?php echo $missing_images !== 1 ? 's' : ''; ?> Missing Images</strong>
                        <p><?php echo $missing_images > 0 ? 'Add images to improve the visual guide.' : 'All tires have images.'; ?></p>
                    </span>
                    <?php if ( $missing_images > 0 ) : ?>
                        <span class="rtg-health-action">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires' ) ); ?>" class="rtg-btn rtg-btn-secondary" style="text-decoration:none;">View Tires</a>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Missing Links -->
                <div class="rtg-health-item">
                    <span class="rtg-health-icon <?php echo $missing_links > 0 ? 'rtg-health-icon-error' : 'rtg-health-icon-success'; ?>">
                        <span class="dashicons <?php echo $missing_links > 0 ? 'dashicons-admin-links' : 'dashicons-yes-alt'; ?>"></span>
                    </span>
                    <span class="rtg-health-content">
                        <strong><?php echo esc_html( $missing_links ); ?> Tire<?php echo $missing_links !== 1 ? 's' : ''; ?> Missing Links</strong>
                        <p><?php echo $missing_links > 0 ? 'Add purchase links to monetize.' : 'All tires have purchase links.'; ?></p>
                    </span>
                    <?php if ( $missing_links > 0 ) : ?>
                        <span class="rtg-health-action">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-affiliate-links&link_filter=missing' ) ); ?>" class="rtg-btn rtg-btn-secondary" style="text-decoration:none;">Fix Links</a>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- .rtg-dashboard-grid -->

    <!-- ================================================================
         Recently Added Tires (full-width)
         ================================================================ -->
    <div class="rtg-card">
        <div class="rtg-card-header"><h2>Recently Added</h2></div>
        <div class="rtg-card-body">
            <?php if ( empty( $stats['recent_tires'] ) ) : ?>
                <p style="color: var(--rtg-text-muted);">No tires added yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit' ) ); ?>">Add your first tire</a>.</p>
            <?php else : ?>
                <ul class="rtg-mini-list">
                    <?php foreach ( $stats['recent_tires'] as $tire ) : ?>
                        <li class="rtg-mini-list-item">
                            <?php if ( ! empty( $tire['image'] ) ) : ?>
                                <img src="<?php echo esc_url( $tire['image'] ); ?>" alt="" class="rtg-mini-list-thumb">
                            <?php endif; ?>
                            <span class="rtg-mini-list-info">
                                <span class="rtg-mini-list-name">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires&s=' . urlencode( $tire['tire_id'] ) ) ); ?>"><?php echo esc_html( $tire['brand'] . ' ' . $tire['model'] ); ?></a>
                                </span>
                                <span class="rtg-mini-list-meta"><?php echo esc_html( $tire['category'] ); ?></span>
                            </span>
                            <span class="rtg-mini-list-value" style="color: var(--rtg-text-muted); font-weight: 400; font-size: 13px;">
                                <?php echo esc_html( date( 'M j, Y', strtotime( $tire['created_at'] ) ) ); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

</div>
