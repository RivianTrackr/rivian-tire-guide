<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$editing_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$wheel = $editing_id ? RTG_Database::get_wheel( $editing_id ) : null;
$is_edit = (bool) $wheel;
$page_title = $is_edit ? 'Edit Stock Wheel' : 'Add Stock Wheel';

$defaults = array(
    'name'       => '',
    'stock_size' => '',
    'alt_sizes'  => '',
    'image'      => '',
    'vehicles'   => '',
    'sort_order' => 0,
);
$v = $wheel ? wp_parse_args( $wheel, $defaults ) : $defaults;

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

// Available vehicle options.
$vehicle_options = array( 'R1T', 'R1S', 'R2' );
$selected_vehicles = array_filter( array_map( 'trim', explode( ',', $v['vehicles'] ) ) );

// Size dropdown options.
$dd_sizes = RTG_Admin::get_dropdown_options( 'sizes' );
?>

<div class="rtg-wrap">

    <?php if ( $message === 'error' ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span>An error occurred while saving.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <h1 class="rtg-page-title"><?php echo esc_html( $page_title ); ?></h1>
    </div>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
        <?php wp_nonce_field( 'rtg_wheel_save', 'rtg_wheel_nonce' ); ?>
        <input type="hidden" name="rtg_wheel_save" value="1">
        <input type="hidden" name="editing_id" value="<?php echo esc_attr( $editing_id ); ?>">

        <div class="rtg-edit-grid">

            <!-- Wheel Info -->
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Wheel Configuration</h2>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="wheel_name">Name <span class="rtg-badge-required">Required</span></label>
                        </div>
                        <p class="rtg-field-description">Display name shown in the wheel guide (e.g. 20" All-Terrain / Dark).</p>
                        <input type="text" id="wheel_name" name="wheel_name" value="<?php echo esc_attr( $v['name'] ); ?>" required placeholder='e.g. 20" All-Terrain / Dark'>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="stock_size">Stock Size <span class="rtg-badge-required">Required</span></label>
                        </div>
                        <p class="rtg-field-description">The factory-default tire size for this wheel.</p>
                        <?php
                        $stock_size_options = $dd_sizes;
                        if ( ! empty( $v['stock_size'] ) && ! in_array( $v['stock_size'], $stock_size_options, true ) ) {
                            $stock_size_options[] = $v['stock_size'];
                        }
                        ?>
                        <select id="stock_size" name="stock_size" required>
                            <option value="">Select...</option>
                            <?php foreach ( $stock_size_options as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v['stock_size'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="alt_sizes">Alternate Sizes</label>
                        </div>
                        <p class="rtg-field-description">Comma-separated list of alternative tire sizes (e.g. 275/60R20, 285/50R22).</p>
                        <input type="text" id="alt_sizes" name="alt_sizes" value="<?php echo esc_attr( $v['alt_sizes'] ); ?>" class="rtg-input-wide" placeholder="e.g. 275/60R20, 285/50R22">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="wheel_image">Image URL</label>
                        </div>
                        <p class="rtg-field-description">URL of the wheel image.</p>
                        <input type="url" id="wheel_image" name="wheel_image" value="<?php echo esc_attr( $v['image'] ); ?>" class="rtg-input-wide">
                        <?php if ( ! empty( $v['image'] ) ) : ?>
                            <div class="rtg-image-preview">
                                <img src="<?php echo esc_url( $v['image'] ); ?>" alt="Preview">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Vehicle & Ordering -->
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Vehicles &amp; Ordering</h2>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label">Vehicles</label>
                        </div>
                        <p class="rtg-field-description">Select which Rivian vehicles use this wheel configuration.</p>
                        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 8px;">
                            <?php foreach ( $vehicle_options as $vehicle ) : ?>
                                <label class="rtg-checkbox-label">
                                    <input type="checkbox" name="vehicles[]" value="<?php echo esc_attr( $vehicle ); ?>" <?php checked( in_array( $vehicle, $selected_vehicles, true ) ); ?>>
                                    <?php echo esc_html( $vehicle ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="sort_order">Sort Order</label>
                        </div>
                        <p class="rtg-field-description">Lower numbers appear first (0 = default).</p>
                        <input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr( $v['sort_order'] ); ?>" min="0" class="rtg-input-small">
                    </div>
                </div>
            </div>

        </div>

        <div class="rtg-footer-actions">
            <button type="submit" class="rtg-btn rtg-btn-primary"><?php echo $is_edit ? 'Update Wheel' : 'Add Wheel'; ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-wheels' ) ); ?>" class="rtg-btn rtg-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
