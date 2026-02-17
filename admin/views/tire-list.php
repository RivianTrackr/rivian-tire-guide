<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Display admin notices.
$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
$recalc_count = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : 0;
$notices = array(
    'added'        => array( 'success', 'Tire added successfully.' ),
    'updated'      => array( 'success', 'Tire updated successfully.' ),
    'deleted'      => array( 'success', 'Tire deleted successfully.' ),
    'bulk_deleted' => array( 'success', 'Selected tires deleted successfully.' ),
    'duplicated'   => array( 'success', 'Tire duplicated successfully. You are now editing the copy.' ),
    'recalculated' => array( 'success', 'Efficiency scores recalculated. ' . $recalc_count . ' tire(s) updated.' ),
    'error'        => array( 'error', 'An error occurred.' ),
);

// Search and filters.
$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'id';
$order = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'ASC';
$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;

$admin_filters = array(
    'brand'    => isset( $_GET['filter_brand'] ) ? sanitize_text_field( $_GET['filter_brand'] ) : '',
    'size'     => isset( $_GET['filter_size'] ) ? sanitize_text_field( $_GET['filter_size'] ) : '',
    'category' => isset( $_GET['filter_category'] ) ? sanitize_text_field( $_GET['filter_category'] ) : '',
);

$tires = RTG_Database::search_tires( $search, $per_page, $paged, $orderby, $order, $admin_filters );
$total = RTG_Database::get_tire_count( $search, $admin_filters );

// Get distinct values for filter dropdowns.
$filter_brands     = RTG_Database::get_distinct_values( 'brand' );
$filter_sizes      = RTG_Database::get_distinct_values( 'size' );
$filter_categories = RTG_Database::get_distinct_values( 'category' );
$total_pages = ceil( $total / $per_page );

// Find the page containing the [rivian_tire_guide] shortcode for deep-link URLs.
$rtg_guide_url = '';
$rtg_pages = get_posts( array(
    's'              => '[rivian_tire_guide',
    'post_type'      => array( 'page', 'post' ),
    'post_status'    => 'publish',
    'posts_per_page' => 1,
    'fields'         => 'ids',
) );
if ( ! empty( $rtg_pages ) ) {
    $rtg_guide_url = get_permalink( $rtg_pages[0] );
}

// Sortable column helper.
$sort_url = function ( $col ) use ( $orderby, $order ) {
    $new_order = ( $orderby === $col && $order === 'ASC' ) ? 'DESC' : 'ASC';
    return add_query_arg( array( 'orderby' => $col, 'order' => $new_order ) );
};
$sort_indicator = function ( $col ) use ( $orderby, $order ) {
    if ( $orderby !== $col ) return '';
    return $order === 'ASC' ? ' <span class="dashicons dashicons-arrow-up-alt2"></span>' : ' <span class="dashicons dashicons-arrow-down-alt2"></span>';
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
        <h1 class="rtg-page-title">Tire Guide</h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit' ) ); ?>" class="rtg-page-title-action">Add New</a>
        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-tires&action=recalculate_efficiency' ), 'rtg_recalculate_efficiency' ) ); ?>" class="rtg-page-title-action" style="background:#f5f5f7;color:#1d1d1f;" onclick="return confirm('Recalculate efficiency scores for all tires?');">Recalculate Grades</a>
    </div>

    <!-- Search & Filters -->
    <form method="get">
        <input type="hidden" name="page" value="rtg-tires">
        <div class="rtg-search-box" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <input type="search" id="tire-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by brand, model, ID, or tags..." style="min-width:220px;">
            <select name="filter_brand" style="min-width:140px;">
                <option value="">All Brands</option>
                <?php foreach ( $filter_brands as $b ) : ?>
                    <option value="<?php echo esc_attr( $b ); ?>" <?php selected( $admin_filters['brand'], $b ); ?>><?php echo esc_html( $b ); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_size" style="min-width:140px;">
                <option value="">All Sizes</option>
                <?php foreach ( $filter_sizes as $sz ) : ?>
                    <option value="<?php echo esc_attr( $sz ); ?>" <?php selected( $admin_filters['size'], $sz ); ?>><?php echo esc_html( $sz ); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_category" style="min-width:140px;">
                <option value="">All Categories</option>
                <?php foreach ( $filter_categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $admin_filters['category'], $cat ); ?>><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rtg-btn rtg-btn-secondary">Filter</button>
            <?php if ( $search || $admin_filters['brand'] || $admin_filters['size'] || $admin_filters['category'] ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires' ) ); ?>" class="rtg-btn rtg-btn-secondary" style="text-decoration:none;">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table Card -->
    <form method="post">
        <?php wp_nonce_field( 'rtg_bulk_action', 'rtg_bulk_nonce' ); ?>

        <div class="rtg-card">

            <!-- Table nav (top) -->
            <div class="rtg-tablenav rtg-tablenav-top">
                <div class="rtg-bulk-actions">
                    <select name="rtg_bulk_action">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="rtg-btn rtg-btn-secondary">Apply</button>
                </div>
                <div class="rtg-pagination">
                    <span class="rtg-pagination-count"><?php echo esc_html( $total ); ?> tire<?php echo $total !== 1 ? 's' : ''; ?></span>
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
                            <th class="column-cb"><input type="checkbox" id="cb-select-all"></th>
                            <th class="column-image">Image</th>
                            <th class="sortable <?php echo $orderby === 'tire_id' ? 'sorted' : ''; ?>">
                                <a href="<?php echo esc_url( $sort_url( 'tire_id' ) ); ?>">Tire ID<?php echo $sort_indicator( 'tire_id' ); ?></a>
                            </th>
                            <th class="sortable <?php echo $orderby === 'brand' ? 'sorted' : ''; ?>">
                                <a href="<?php echo esc_url( $sort_url( 'brand' ) ); ?>">Brand<?php echo $sort_indicator( 'brand' ); ?></a>
                            </th>
                            <th class="sortable <?php echo $orderby === 'model' ? 'sorted' : ''; ?>">
                                <a href="<?php echo esc_url( $sort_url( 'model' ) ); ?>">Model<?php echo $sort_indicator( 'model' ); ?></a>
                            </th>
                            <th>Size</th>
                            <th class="sortable <?php echo $orderby === 'load_index' ? 'sorted' : ''; ?>">
                                <a href="<?php echo esc_url( $sort_url( 'load_index' ) ); ?>">Load Index<?php echo $sort_indicator( 'load_index' ); ?></a>
                            </th>
                            <th>Category</th>
                            <th class="sortable <?php echo $orderby === 'price' ? 'sorted' : ''; ?>">
                                <a href="<?php echo esc_url( $sort_url( 'price' ) ); ?>">Price<?php echo $sort_indicator( 'price' ); ?></a>
                            </th>
                            <th class="sortable <?php echo $orderby === 'efficiency_grade' ? 'sorted' : ''; ?>">
                                <a href="<?php echo esc_url( $sort_url( 'efficiency_grade' ) ); ?>">Grade<?php echo $sort_indicator( 'efficiency_grade' ); ?></a>
                            </th>
                            <th>Tags</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $tires ) ) : ?>
                            <tr>
                                <td colspan="11">
                                    <div class="rtg-empty-state">
                                        <span class="dashicons dashicons-car"></span>
                                        <h3>No tires found</h3>
                                        <p>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-import' ) ); ?>">Import from CSV</a> or
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit' ) ); ?>">add one manually</a>.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $tires as $tire ) : ?>
                                <tr>
                                    <td class="column-cb">
                                        <input type="checkbox" name="tire_ids[]" value="<?php echo esc_attr( $tire['tire_id'] ); ?>">
                                    </td>
                                    <td class="column-image">
                                        <?php if ( ! empty( $tire['image'] ) ) : ?>
                                            <img src="<?php echo esc_url( $tire['image'] ); ?>" alt="" class="tire-thumb">
                                        <?php else : ?>
                                            <span class="tire-thumb-placeholder"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-primary">
                                        <strong>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&id=' . $tire['id'] ) ); ?>">
                                                <?php echo esc_html( $tire['tire_id'] ); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit&id=' . $tire['id'] ) ); ?>">Edit</a> |
                                            </span>
                                            <span class="duplicate">
                                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-tires&action=duplicate&tire_id=' . $tire['tire_id'] ), 'rtg_duplicate_' . $tire['tire_id'] ) ); ?>">Duplicate</a> |
                                            </span>
                                            <?php if ( $rtg_guide_url ) : ?>
                                            <span class="view">
                                                <a href="<?php echo esc_url( add_query_arg( 'tire', $tire['tire_id'], $rtg_guide_url ) ); ?>" target="_blank" rel="noopener noreferrer">View</a> |
                                            </span>
                                            <?php endif; ?>
                                            <span class="delete">
                                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-tires&action=delete&tire_id=' . $tire['tire_id'] ), 'rtg_delete_' . $tire['tire_id'] ) ); ?>" class="submitdelete" onclick="return confirm('Delete this tire?');">Delete</a>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html( $tire['brand'] ); ?></td>
                                    <td><?php echo esc_html( $tire['model'] ); ?></td>
                                    <td><?php echo esc_html( $tire['size'] ); ?></td>
                                    <td><?php echo esc_html( $tire['load_index'] ); ?></td>
                                    <td><?php echo esc_html( $tire['category'] ); ?></td>
                                    <td>$<?php echo esc_html( number_format( $tire['price'], 2 ) ); ?></td>
                                    <td>
                                        <?php
                                        $grade = strtoupper( $tire['efficiency_grade'] );
                                        $grade_class = $grade ? 'rtg-grade-' . strtolower( $grade ) : 'rtg-grade-none';
                                        ?>
                                        <span class="rtg-grade <?php echo esc_attr( $grade_class ); ?>">
                                            <?php echo esc_html( $grade ?: '-' ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( $tire['tags'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>
