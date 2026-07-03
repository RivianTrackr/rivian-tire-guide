<?php
/**
 * Bulk edit form — applies field changes to a selection of tires.
 * Reached from the tire list's "Edit" bulk action. Blank fields keep
 * each tire's current value.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$rtg_ids  = array_filter( array_map( 'sanitize_text_field', explode( ',', wp_unslash( $_GET['ids'] ?? '' ) ) ), array( 'RTG_Database', 'validate_tire_id' ) );
$rtg_sel  = array();
foreach ( $rtg_ids as $rtg_id ) {
    $rtg_tire = RTG_Database::get_tire( $rtg_id );
    if ( $rtg_tire ) {
        $rtg_sel[] = $rtg_tire;
    }
}
$rtg_categories = RTG_Admin::get_dropdown_options( 'categories' );
?>
<div class="rtg-wrap">
    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Bulk Edit — <?php echo count( $rtg_sel ); ?> tire<?php echo count( $rtg_sel ) !== 1 ? 's' : ''; ?></h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires' ) ); ?>" class="rtg-page-title-action" style="background:#f5f5f7;color:#1d1d1f;">Cancel</a>
    </div>

    <?php if ( empty( $rtg_sel ) ) : ?>
        <div class="rtg-card"><div class="rtg-card-body"><p>No valid tires selected. <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires' ) ); ?>">Back to the list</a>.</p></div></div>
    <?php else : ?>

    <form method="post">
        <?php wp_nonce_field( 'rtg_bulk_edit', 'rtg_bulk_edit_nonce' ); ?>
        <input type="hidden" name="rtg_bulk_edit_save" value="1">
        <?php foreach ( $rtg_sel as $rtg_tire ) : ?>
            <input type="hidden" name="tire_ids[]" value="<?php echo esc_attr( $rtg_tire['tire_id'] ); ?>">
        <?php endforeach; ?>

        <div class="rtg-card" style="max-width:640px;">
            <div class="rtg-card-header"><h2>Selected Tires</h2></div>
            <div class="rtg-card-body">
                <p style="color:#6e6e73;font-size:13px;line-height:1.6;margin:0;">
                    <?php
                    $rtg_names = array_map(
                        function ( $t ) {
                            return esc_html( trim( $t['brand'] . ' ' . $t['model'] . ' (' . $t['size'] . ')' ) );
                        },
                        $rtg_sel
                    );
                    echo implode( ', ', $rtg_names ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- items escaped above
                    ?>
                </p>
            </div>
        </div>

        <div class="rtg-card" style="max-width:640px;">
            <div class="rtg-card-header">
                <h2>Changes</h2>
            </div>
            <div class="rtg-card-body">
                <p class="rtg-field-description" style="margin-top:0;">Fields left blank keep each tire's current value.</p>

                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="bulk_category">Category</label>
                    </div>
                    <select id="bulk_category" name="bulk_category">
                        <option value="">— Keep current —</option>
                        <?php foreach ( $rtg_categories as $rtg_cat ) : ?>
                            <option value="<?php echo esc_attr( $rtg_cat ); ?>"><?php echo esc_html( $rtg_cat ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="bulk_price">Average Price ($)</label>
                    </div>
                    <input type="number" id="bulk_price" name="bulk_price" step="0.01" min="0" placeholder="Keep current" class="rtg-input-small">
                </div>

                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="bulk_warranty">Mileage Warranty</label>
                    </div>
                    <input type="number" id="bulk_warranty" name="bulk_warranty" step="1000" min="0" placeholder="Keep current" class="rtg-input-small">
                </div>

                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="bulk_tags">Tags</label>
                    </div>
                    <p class="rtg-field-description">Comma-separated. Choose whether to append to or replace each tire's tags.</p>
                    <input type="text" id="bulk_tags" name="bulk_tags" placeholder="e.g. EV Rated, Reviewed" class="rtg-input-wide">
                    <div style="margin-top:8px;display:flex;gap:16px;font-size:13px;">
                        <label><input type="radio" name="bulk_tags_mode" value="append" checked> Append</label>
                        <label><input type="radio" name="bulk_tags_mode" value="replace"> Replace</label>
                    </div>
                </div>
            </div>
        </div>

        <p>
            <button type="submit" class="rtg-btn rtg-btn-primary" onclick="return confirm('Apply these changes to <?php echo count( $rtg_sel ); ?> tire<?php echo count( $rtg_sel ) !== 1 ? 's' : ''; ?>?');">Apply to <?php echo count( $rtg_sel ); ?> tire<?php echo count( $rtg_sel ) !== 1 ? 's' : ''; ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires' ) ); ?>" class="rtg-btn rtg-btn-secondary">Cancel</a>
        </p>
    </form>

    <?php endif; ?>
</div>
