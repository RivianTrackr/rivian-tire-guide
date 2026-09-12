<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
$notices = array(
    'approved' => array( 'success', 'Review approved.' ),
    'rejected' => array( 'success', 'Review rejected.' ),
    'deleted'  => array( 'success', 'Review deleted.' ),
    'error'    => array( 'error', 'An error occurred.' ),
);

// Current status tab.
$current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$status_counts  = RTG_Database::get_review_status_counts();
$summary        = RTG_Database::get_review_summary();

// Search & pagination.
$search   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$orderby  = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'r.updated_at';
$order    = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;

$reviews     = RTG_Database::search_reviews( $search, $current_status, $per_page, $paged, $orderby, $order );
$total       = RTG_Database::get_review_count( $search, $current_status );
$total_pages = ceil( $total / $per_page );

// Collect user IDs for display names.
$user_ids = array_unique( array_column( $reviews, 'user_id' ) );
$user_map = array();
if ( ! empty( $user_ids ) ) {
    $users = get_users( array( 'include' => $user_ids, 'fields' => array( 'ID', 'display_name' ) ) );
    foreach ( $users as $user ) {
        $user_map[ $user->ID ] = $user->display_name;
    }
}

// Sortable column helper.
$base_args = array( 'page' => 'rtg-reviews' );
if ( $current_status ) {
    $base_args['status'] = $current_status;
}
if ( $search ) {
    $base_args['s'] = $search;
}

$sort_url = function ( $col ) use ( $orderby, $order, $base_args ) {
    $new_order = ( $orderby === $col && $order === 'DESC' ) ? 'ASC' : 'DESC';
    return add_query_arg( array_merge( $base_args, array( 'orderby' => $col, 'order' => $new_order ) ), admin_url( 'admin.php' ) );
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
            ? '<span class="dashicons dashicons-star-filled is-filled"></span>'
            : '<span class="dashicons dashicons-star-empty"></span>';
    }
    return '<span class="rtg-stars" aria-label="' . esc_attr( intval( $rating ) . ' of 5 stars' ) . '">' . $out . '</span>';
};

// Status badge helper.
$status_badge = function ( $status ) {
    $map = array(
        'pending'  => array( 'warning', 'Pending' ),
        'approved' => array( 'success', 'Approved' ),
        'rejected' => array( 'error', 'Rejected' ),
    );
    $info = $map[ $status ] ?? array( 'muted', ucfirst( $status ) );
    return '<span class="rtg-badge rtg-badge-' . esc_attr( $info[0] ) . '">' . esc_html( $info[1] ) . '</span>';
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
        <div class="rtg-page-heading">
            <h1 class="rtg-page-title">Reviews</h1>
            <p class="rtg-page-subtitle">Owner reviews from the guide and the review page. A review shows on the tire page once it is approved.</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="rtg-stats-grid">
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo intval( $summary['total'] ?? 0 ); ?></div>
            <div class="rtg-stat-label">Total Reviews</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo esc_html( $summary['avg_rating'] ?? '0' ); ?></div>
            <div class="rtg-stat-label">Avg Rating</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo intval( $summary['tires_rated'] ?? 0 ); ?></div>
            <div class="rtg-stat-label">Tires Reviewed</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value"><?php echo intval( $summary['unique_users'] ?? 0 ); ?></div>
            <div class="rtg-stat-label">Unique Reviewers</div>
        </div>
    </div>

    <!-- Status filter + search -->
    <div class="rtg-pills">
        <?php
        $tabs = array(
            ''         => 'All',
            'pending'  => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        );
        foreach ( $tabs as $tab_status => $tab_label ) :
            $count = $tab_status === '' ? $status_counts['all'] : ( $status_counts[ $tab_status ] ?? 0 );
            $is_active = $current_status === $tab_status;
            $tab_url = add_query_arg( array( 'page' => 'rtg-reviews', 'status' => $tab_status ), admin_url( 'admin.php' ) );
        ?>
            <a href="<?php echo esc_url( $tab_url ); ?>" class="rtg-pill <?php echo $is_active ? 'is-active' : ''; ?>" <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html( $tab_label ); ?>
                <span class="rtg-pill-count"><?php echo intval( $count ); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="rtg-reviews">
        <?php if ( $current_status ) : ?>
            <input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
        <?php endif; ?>
        <div class="rtg-toolbar">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by tire, brand, model, or review text" aria-label="Search reviews">
            <button type="submit" class="rtg-btn rtg-btn-secondary">Search</button>
            <?php if ( $search ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-reviews', 'status' => $current_status ), admin_url( 'admin.php' ) ) ); ?>" class="rtg-btn rtg-btn-ghost">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table Card -->
    <div class="rtg-card">

        <!-- Table nav (top) -->
        <div class="rtg-tablenav rtg-tablenav-top">
            <div class="rtg-pagination">
                <span class="rtg-pagination-count"><?php echo esc_html( $total ); ?> review<?php echo $total !== 1 ? 's' : ''; ?></span>
                <?php if ( $total_pages > 1 ) : ?>
                    <span class="rtg-pagination-links">
                        <?php $rtg_page_base = remove_query_arg( 'message' ); ?>
                        <?php if ( $paged > 1 ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $rtg_page_base ) ); ?>">&lsaquo;</a>
                        <?php endif; ?>
                        <span class="current-page"><?php echo esc_html( $paged ); ?> of <?php echo esc_html( $total_pages ); ?></span>
                        <?php if ( $paged < $total_pages ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $rtg_page_base ) ); ?>">&rsaquo;</a>
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
                        <th class="sortable <?php echo $orderby === 't.brand' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url( $sort_url( 't.brand' ) ); ?>">Tire<?php echo $sort_indicator( 't.brand' ); ?></a>
                        </th>
                        <th>Author</th>
                        <th class="sortable <?php echo $orderby === 'r.rating' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url( $sort_url( 'r.rating' ) ); ?>">Rating<?php echo $sort_indicator( 'r.rating' ); ?></a>
                        </th>
                        <th>Review</th>
                        <th class="sortable <?php echo $orderby === 'r.review_status' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url( $sort_url( 'r.review_status' ) ); ?>">Status<?php echo $sort_indicator( 'r.review_status' ); ?></a>
                        </th>
                        <th class="sortable <?php echo $orderby === 'r.updated_at' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url( $sort_url( 'r.updated_at' ) ); ?>">Date<?php echo $sort_indicator( 'r.updated_at' ); ?></a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $reviews ) ) : ?>
                        <tr>
                            <td colspan="7">
                                <div class="rtg-empty-state">
                                    <span class="dashicons dashicons-format-chat"></span>
                                    <h3>No reviews found</h3>
                                    <p>
                                        <?php if ( $current_status === 'pending' ) : ?>
                                            Nothing is waiting for moderation.
                                        <?php else : ?>
                                            Reviews will appear here once users start reviewing tires on the frontend.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $reviews as $r ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&tire_id=' . rawurlencode( $r['tire_id'] ) ) ); ?>" class="rtg-row-title">
                                        <?php echo esc_html( ( $r['brand'] ?? '' ) . ' ' . ( $r['model'] ?? '' ) ); ?>
                                    </a>
                                    <span class="rtg-row-meta"><?php echo esc_html( $r['tire_id'] ); ?></span>
                                </td>
                                <td>
                                    <?php if ( (int) $r['user_id'] === 0 ) : ?>
                                        <strong><?php echo esc_html( $r['guest_name'] ?? 'Guest' ); ?></strong>
                                        <span class="rtg-row-meta"><?php echo esc_html( $r['guest_email'] ?? '' ); ?></span>
                                        <span class="rtg-row-meta"><span class="rtg-badge rtg-badge-warning rtg-badge-sm">Guest</span></span>
                                    <?php else : ?>
                                        <?php echo esc_html( $user_map[ $r['user_id'] ] ?? 'User #' . $r['user_id'] ); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="rtg-nowrap"><?php echo $render_stars( intval( $r['rating'] ) ); ?></td>
                                <td class="rtg-cell-clamp">
                                    <?php if ( ! empty( $r['review_title'] ) || ! empty( $r['review_text'] ) ) : ?>
                                        <?php if ( ! empty( $r['review_title'] ) ) : ?>
                                            <strong class="rtg-row-heading"><?php echo esc_html( $r['review_title'] ); ?></strong>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $r['review_text'] ) ) : ?>
                                            <span class="rtg-clamp" title="<?php echo esc_attr( $r['review_text'] ); ?>"><?php echo esc_html( $r['review_text'] ); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="rtg-muted rtg-small"><em>Star rating only</em></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $status_badge( $r['review_status'] ?? 'approved' ); ?></td>
                                <td class="rtg-nowrap">
                                    <span class="rtg-small"><?php echo esc_html( date( 'M j, Y', strtotime( $r['updated_at'] ?? $r['created_at'] ) ) ); ?></span>
                                    <span class="rtg-row-meta"><?php echo esc_html( date( 'g:i A', strtotime( $r['updated_at'] ?? $r['created_at'] ) ) ); ?></span>
                                </td>
                                <td class="rtg-nowrap">
                                    <div class="rtg-table-actions">
                                        <?php
                                        $review_status = $r['review_status'] ?? 'approved';
                                        $status_param  = $current_status ? '&status=' . urlencode( $current_status ) : '';
                                        ?>
                                        <?php if ( $review_status !== 'approved' ) : ?>
                                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-reviews&action=approve_review&rating_id=' . $r['id'] . $status_param ), 'rtg_review_approve_review_' . $r['id'] ) ); ?>" class="rtg-btn rtg-btn-primary rtg-btn-sm">Approve</a>
                                        <?php endif; ?>
                                        <?php if ( $review_status !== 'rejected' ) : ?>
                                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-reviews&action=reject_review&rating_id=' . $r['id'] . $status_param ), 'rtg_review_reject_review_' . $r['id'] ) ); ?>" class="rtg-btn rtg-btn-secondary rtg-btn-sm">Reject</a>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-reviews&action=delete_rating&rating_id=' . $r['id'] . $status_param ), 'rtg_delete_rating_' . $r['id'] ) ); ?>" class="rtg-btn rtg-btn-danger-quiet rtg-btn-sm" onclick="return confirm('Delete this review permanently?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
