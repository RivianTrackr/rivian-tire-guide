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

if ( $message && isset( $notices[ $message ] ) ) {
    printf(
        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
        esc_attr( $notices[ $message ][0] ),
        esc_html( $notices[ $message ][1] )
    );
}

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

<div class="wrap">
    <h1 class="wp-heading-inline">Tire Guide</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit' ) ); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="rtg-tires">
        <p class="search-box">
            <label class="screen-reader-text" for="tire-search">Search Tires:</label>
            <input type="search" id="tire-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by brand, model, or ID...">
            <input type="submit" class="button" value="Search">
        </p>
    </form>

    <form method="post">
        <?php wp_nonce_field( 'rtg_bulk_action', 'rtg_bulk_nonce' ); ?>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="rtg_bulk_action">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete</option>
                </select>
                <input type="submit" class="button action" value="Apply">
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html( $total ); ?> tire<?php echo $total !== 1 ? 's' : ''; ?></span>
                <?php if ( $total_pages > 1 ) : ?>
                    <span class="pagination-links">
                        <?php if ( $paged > 1 ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>" class="prev-page button">&lsaquo;</a>
                        <?php endif; ?>
                        <span class="paging-input"><?php echo esc_html( $paged ); ?> of <?php echo esc_html( $total_pages ); ?></span>
                        <?php if ( $paged < $total_pages ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>" class="next-page button">&rsaquo;</a>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all"></td>
                    <th class="manage-column" style="width:60px;">Image</th>
                    <th class="manage-column column-primary sortable <?php echo $orderby === 'tire_id' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url( $sort_url( 'tire_id' ) ); ?>">Tire ID<?php echo $sort_indicator( 'tire_id' ); ?></a>
                    </th>
                    <th class="manage-column sortable <?php echo $orderby === 'brand' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url( $sort_url( 'brand' ) ); ?>">Brand<?php echo $sort_indicator( 'brand' ); ?></a>
                    </th>
                    <th class="manage-column sortable <?php echo $orderby === 'model' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url( $sort_url( 'model' ) ); ?>">Model<?php echo $sort_indicator( 'model' ); ?></a>
                    </th>
                    <th class="manage-column">Size</th>
                    <th class="manage-column">Category</th>
                    <th class="manage-column sortable <?php echo $orderby === 'price' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url( $sort_url( 'price' ) ); ?>">Price<?php echo $sort_indicator( 'price' ); ?></a>
                    </th>
                    <th class="manage-column sortable <?php echo $orderby === 'efficiency_grade' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url( $sort_url( 'efficiency_grade' ) ); ?>">Grade<?php echo $sort_indicator( 'efficiency_grade' ); ?></a>
                    </th>
                    <th class="manage-column">Tags</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $tires ) ) : ?>
                    <tr>
                        <td colspan="10">No tires found. <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-import' ) ); ?>">Import from CSV</a> or <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tire-edit' ) ); ?>">add one manually</a>.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $tires as $tire ) : ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="tire_ids[]" value="<?php echo esc_attr( $tire['tire_id'] ); ?>">
                            </th>
                            <td>
                                <?php if ( ! empty( $tire['image'] ) ) : ?>
                                    <img src="<?php echo esc_url( $tire['image'] ); ?>" alt="" style="width:50px;height:35px;object-fit:cover;border-radius:4px;background:#fff;">
                                <?php else : ?>
                                    <span style="display:inline-block;width:50px;height:35px;background:#ddd;border-radius:4px;"></span>
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
                                $colors = array( 'A' => '#5ec095', 'B' => '#a3e635', 'C' => '#facc15', 'D' => '#f97316', 'E' => '#ef4444', 'F' => '#b91c1c' );
                                $color = $colors[ $grade ] ?? '#94a3b8';
                                ?>
                                <span style="background:<?php echo esc_attr( $color ); ?>;color:#1a1a1a;padding:2px 8px;border-radius:4px;font-weight:700;font-size:13px;">
                                    <?php echo esc_html( $grade ?: '-' ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $tire['tags'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>
