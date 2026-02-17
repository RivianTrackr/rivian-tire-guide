<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
$notices = array(
    'added'   => array( 'success', 'Wheel added successfully.' ),
    'updated' => array( 'success', 'Wheel updated successfully.' ),
    'deleted' => array( 'success', 'Wheel deleted successfully.' ),
    'error'   => array( 'error', 'An error occurred.' ),
);

$wheels = RTG_Database::get_all_wheels();
?>

<div class="rtg-wrap">

    <?php if ( $message && isset( $notices[ $message ] ) ) : ?>
        <div class="rtg-notice rtg-notice-<?php echo esc_attr( $notices[ $message ][0] ); ?>">
            <span><?php echo esc_html( $notices[ $message ][1] ); ?></span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Stock Wheels</h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-wheel-edit' ) ); ?>" class="rtg-page-title-action">Add New</a>
    </div>

    <p style="color: var(--rtg-text-secondary); font-size: 14px; margin-bottom: 20px;">
        Manage the stock wheel configurations shown in the "Not sure which Rivian tire you need?" guide on the frontend.
    </p>

    <div class="rtg-card">
        <div class="rtg-table-wrapper">
            <table class="rtg-table">
                <thead>
                    <tr>
                        <th class="column-image">Image</th>
                        <th>Name</th>
                        <th>Stock Size</th>
                        <th>Alt Sizes</th>
                        <th>Vehicles</th>
                        <th>Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $wheels ) ) : ?>
                        <tr>
                            <td colspan="6">
                                <div class="rtg-empty-state">
                                    <span class="dashicons dashicons-car"></span>
                                    <h3>No stock wheels configured</h3>
                                    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-wheel-edit' ) ); ?>">Add your first wheel configuration</a>.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $wheels as $wheel ) : ?>
                            <tr>
                                <td class="column-image">
                                    <?php if ( ! empty( $wheel['image'] ) ) : ?>
                                        <img src="<?php echo esc_url( $wheel['image'] ); ?>" alt="" class="tire-thumb">
                                    <?php else : ?>
                                        <span class="tire-thumb-placeholder"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-primary">
                                    <strong>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-wheel-edit&id=' . $wheel['id'] ) ); ?>">
                                            <?php echo esc_html( $wheel['name'] ); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-wheel-edit&id=' . $wheel['id'] ) ); ?>">Edit</a> |
                                        </span>
                                        <span class="delete">
                                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-wheels&action=delete_wheel&wheel_id=' . $wheel['id'] ), 'rtg_delete_wheel_' . $wheel['id'] ) ); ?>" class="submitdelete" onclick="return confirm('Delete this wheel?');">Delete</a>
                                        </span>
                                    </div>
                                </td>
                                <td><code><?php echo esc_html( $wheel['stock_size'] ); ?></code></td>
                                <td>
                                    <?php
                                    $alts = array_filter( array_map( 'trim', explode( ',', $wheel['alt_sizes'] ) ) );
                                    foreach ( $alts as $alt ) {
                                        echo '<code>' . esc_html( $alt ) . '</code> ';
                                    }
                                    if ( empty( $alts ) ) {
                                        echo '<span style="color: var(--rtg-text-muted);">&mdash;</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $vehicles = array_filter( array_map( 'trim', explode( ',', $wheel['vehicles'] ) ) );
                                    foreach ( $vehicles as $vehicle ) {
                                        echo '<span class="rtg-badge rtg-badge-info" style="margin-right:4px;">' . esc_html( $vehicle ) . '</span>';
                                    }
                                    if ( empty( $vehicles ) ) {
                                        echo '<span style="color: var(--rtg-text-muted);">&mdash;</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html( $wheel['sort_order'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
