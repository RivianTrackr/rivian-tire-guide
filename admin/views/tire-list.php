<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Display admin notices.
$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
$notices = array(
    'added'        => array( 'success', 'Tire added successfully.' ),
    'updated'      => array( 'success', 'Tire updated successfully.' ),
    'deleted'      => array( 'success', 'Tire deleted successfully.' ),
    'bulk_deleted' => array( 'success', 'Selected tires deleted successfully.' ),
    'error'        => array( 'error', 'An error occurred.' ),
);

// Search.
$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'id';
$order = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'ASC';
$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;

$tires = RTG_Database::search_tires( $search, $per_page, $paged, $orderby, $order );
$total = RTG_Database::get_tire_count( $search );
$total_pages = ceil( $total / $per_page );

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
    </div>

    <!-- Search -->
    <form method="get">
        <input type="hidden" name="page" value="rtg-tires">
        <div class="rtg-search-box">
            <input type="search" id="tire-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by brand, model, or ID...">
            <button type="submit" class="rtg-btn rtg-btn-secondary">Search</button>
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
                                <td colspan="10">
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
                                            <span class="delete">
                                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-tires&action=delete&tire_id=' . $tire['tire_id'] ), 'rtg_delete_' . $tire['tire_id'] ) ); ?>" class="submitdelete" onclick="return confirm('Delete this tire?');">Delete</a>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html( $tire['brand'] ); ?></td>
                                    <td><?php echo esc_html( $tire['model'] ); ?></td>
                                    <td><?php echo esc_html( $tire['size'] ); ?></td>
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
