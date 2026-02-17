<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
$notices = array(
    'deleted' => array( 'success', 'Rating deleted successfully.' ),
    'error'   => array( 'error', 'An error occurred.' ),
    'updated' => array( 'success', 'Review updated successfully.' ),
);

// Search & pagination.
$search   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$orderby  = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'r.created_at';
$order    = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;

$ratings     = RTG_Database::search_ratings( $search, $per_page, $paged, $orderby, $order );
$total       = RTG_Database::get_rating_count( $search );
$total_pages = ceil( $total / $per_page );
$summary     = RTG_Database::get_ratings_summary();

// Collect user IDs for display names.
$user_ids  = array_unique( array_column( $ratings, 'user_id' ) );
$user_map  = array();
if ( ! empty( $user_ids ) ) {
    $users = get_users( array( 'include' => $user_ids, 'fields' => array( 'ID', 'display_name' ) ) );
    foreach ( $users as $user ) {
        $user_map[ $user->ID ] = $user->display_name;
    }
}

// Sortable column helper.
$sort_url = function ( $col ) use ( $orderby, $order ) {
    $new_order = ( $orderby === $col && $order === 'DESC' ) ? 'ASC' : 'DESC';
    return add_query_arg( array( 'orderby' => $col, 'order' => $new_order ) );
};
$sort_indicator = function ( $col ) use ( $orderby, $order ) {
    if ( $orderby !== $col ) return '';
    return $order === 'ASC' ? ' <span class="dashicons dashicons-arrow-up-alt2"></span>' : ' <span class="dashicons dashicons-arrow-down-alt2"></span>';
};

// Star rendering helper.
$render_stars = function ( $rating ) {
    $out = '';
    for ( $i = 1; $i <= 5; $i++ ) {
        $out .= $i <= $rating
            ? '<span class="dashicons dashicons-star-filled" style="color: #f59e0b; width: 18px; height: 18px; font-size: 18px;"></span>'
            : '<span class="dashicons dashicons-star-empty" style="color: #d2d2d7; width: 18px; height: 18px; font-size: 18px;"></span>';
    }
    return $out;
};
?>

<div class="rtg-wrap">

    <?php if ( $message && isset( $notices[ $message ] ) ) : ?>
        <div class="rtg-notice rtg-notice-<?php echo esc_attr( $notices[ $message ][0] ); ?>">
            <span><?php echo esc_html( $notices[ $message ][1] ); ?></span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Ratings &amp; Reviews</h1>
    </div>

    <!-- Stats -->
    <div class="rtg-stats-grid">
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo intval( $summary['total'] ?? 0 ); ?></div>
            <div class="rtg-stat-label">Total Ratings</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $summary['avg_rating'] ?? '0' ); ?></div>
            <div class="rtg-stat-label">Avg Rating</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo intval( $summary['tires_rated'] ?? 0 ); ?></div>
            <div class="rtg-stat-label">Tires Rated</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo intval( $summary['unique_users'] ?? 0 ); ?></div>
            <div class="rtg-stat-label">Unique Users</div>
        </div>
    </div>

    <!-- Search -->
    <form method="get">
        <input type="hidden" name="page" value="rtg-ratings">
        <div class="rtg-search-box">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by tire ID, brand, or model...">
            <button type="submit" class="rtg-btn rtg-btn-secondary">Search</button>
        </div>
    </form>

    <!-- Table Card -->
    <div class="rtg-card">

        <!-- Table nav (top) -->
        <div class="rtg-tablenav rtg-tablenav-top">
            <div class="rtg-pagination">
                <span class="rtg-pagination-count"><?php echo esc_html( $total ); ?> rating<?php echo $total !== 1 ? 's' : ''; ?></span>
                <?php if ( $total_pages > 1 ) : ?>
                    <span class="rtg-pagination-links">
                        <?php if ( $paged > 1 ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>">&lsaquo;</a>
                        <?php endif; ?>
                        <span class="current-page"><?php echo esc_html( $paged ); ?> of <?php echo esc_html( $total_pages ); ?></span>
                        <?php if ( $paged < $total_pages ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>">&rsaquo;</a>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table -->
        <div class="rtg-table-wrapper">
            <table class="rtg-table">
                <thead>
                    <tr>
                        <th class="sortable <?php echo $orderby === 'r.tire_id' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url( $sort_url( 'r.tire_id' ) ); ?>">Tire ID<?php echo $sort_indicator( 'r.tire_id' ); ?></a>
                        </th>
                        <th class="sortable <?php echo $orderby === 't.brand' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url( $sort_url( 't.brand' ) ); ?>">Brand<?php echo $sort_indicator( 't.brand' ); ?></a>
                        </th>
                        <th class="sortable <?php echo $orderby === 't.model' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url( $sort_url( 't.model' ) ); ?>">Model<?php echo $sort_indicator( 't.model' ); ?></a>
                        </th>
                        <th>User</th>
                        <th class="sortable <?php echo $orderby === 'r.rating' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url( $sort_url( 'r.rating' ) ); ?>">Rating<?php echo $sort_indicator( 'r.rating' ); ?></a>
                        </th>
                        <th>Review</th>
                        <th class="sortable <?php echo $orderby === 'r.created_at' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url( $sort_url( 'r.created_at' ) ); ?>">Date<?php echo $sort_indicator( 'r.created_at' ); ?></a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $ratings ) ) : ?>
                        <tr>
                            <td colspan="8">
                                <div class="rtg-empty-state">
                                    <span class="dashicons dashicons-star-half"></span>
                                    <h3>No ratings yet</h3>
                                    <p>Ratings and reviews will appear here once users start rating tires on the frontend.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $ratings as $r ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires&s=' . urlencode( $r['tire_id'] ) ) ); ?>" style="color: var(--rtg-action-primary); text-decoration: none; font-weight: 600;">
                                        <?php echo esc_html( $r['tire_id'] ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $r['brand'] ?? '—' ); ?></td>
                                <td><?php echo esc_html( $r['model'] ?? '—' ); ?></td>
                                <td><?php echo esc_html( $user_map[ $r['user_id'] ] ?? 'User #' . $r['user_id'] ); ?></td>
                                <td style="white-space: nowrap;"><?php echo $render_stars( intval( $r['rating'] ) ); ?></td>
                                <td style="max-width: 280px;">
                                    <?php if ( ! empty( $r['review_text'] ) || ! empty( $r['review_title'] ) ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-reviews&s=' . urlencode( $r['tire_id'] ) ) ); ?>" style="color: var(--rtg-action-primary); text-decoration: none; font-size: 13px;">
                                            <?php if ( ! empty( $r['review_title'] ) ) : ?>
                                                <strong style="display: block; margin-bottom: 2px;"><?php echo esc_html( $r['review_title'] ); ?></strong>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $r['review_text'] ) ) : ?>
                                                <span style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo esc_html( $r['review_text'] ); ?></span>
                                            <?php else : ?>
                                                View review &rarr;
                                            <?php endif; ?>
                                        </a>
                                    <?php else : ?>
                                        <span style="color: var(--rtg-text-muted, #64748b); font-style: italic; font-size: 13px;">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( date( 'M j, Y', strtotime( $r['updated_at'] ?? $r['created_at'] ) ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-ratings&action=delete_rating&rating_id=' . $r['id'] ), 'rtg_delete_rating_' . $r['id'] ) ); ?>" class="submitdelete" style="color: var(--rtg-error); text-decoration: none; font-size: 13px;" onclick="return confirm('Delete this rating?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
