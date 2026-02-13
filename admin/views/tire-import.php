<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

// Show import results.
$import_result = get_transient( 'rtg_import_result' );

$error_messages = array(
    'no_file'      => 'No file was uploaded.',
    'invalid_type' => 'Invalid file type. Please upload a .csv file.',
    'too_large'    => 'File is too large. Maximum size is 2MB.',
);

$tire_count = RTG_Database::get_tire_count();
?>

<div class="rtg-wrap">

    <?php if ( $message === 'imported' && $import_result ) : ?>
        <?php delete_transient( 'rtg_import_result' ); ?>
        <div class="rtg-notice rtg-notice-success">
            <span>
                Import complete:
                <strong><?php echo esc_html( $import_result['inserted'] ); ?> inserted</strong>,
                <strong><?php echo esc_html( $import_result['updated'] ); ?> updated</strong>,
                <strong><?php echo esc_html( $import_result['skipped'] ); ?> skipped</strong>.
            </span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>

        <?php if ( ! empty( $import_result['errors'] ) ) : ?>
            <div class="rtg-notice rtg-notice-warning">
                <div>
                    <strong>Errors:</strong>
                    <ul style="margin:4px 0 0 16px;padding:0;">
                        <?php foreach ( $import_result['errors'] as $error ) : ?>
                            <li><?php echo esc_html( $error ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ( $message && isset( $error_messages[ $message ] ) ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span><?php echo esc_html( $error_messages[ $message ] ); ?></span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Import Tires from CSV</h1>
    </div>

    <!-- Import Card -->
    <div class="rtg-card" style="max-width: 700px;">
        <div class="rtg-card-header">
            <h2>CSV Import</h2>
            <?php if ( $tire_count > 0 ) : ?>
                <p>Your database currently contains <strong><?php echo esc_html( $tire_count ); ?></strong> tires.</p>
            <?php else : ?>
                <p>Your database is empty. Import your tire data from a CSV file to get started.</p>
            <?php endif; ?>
        </div>
        <div class="rtg-card-body">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'rtg_import_csv', 'rtg_import_nonce' ); ?>
                <input type="hidden" name="rtg_import_csv" value="1">

                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="csv_file">CSV File <span class="rtg-badge-required">Required</span></label>
                    </div>
                    <p class="rtg-field-description">Maximum file size: 2MB. Must be a .csv file.</p>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                </div>

                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label">Options</label>
                    </div>
                    <label class="rtg-toggle">
                        <input type="checkbox" name="update_existing" value="1" checked>
                        <span class="rtg-toggle-track"></span>
                        <span class="rtg-toggle-label">Update existing tires (matched by Tire ID)</span>
                    </label>
                </div>

                <div class="rtg-field-row" style="border-bottom: none;">
                    <button type="submit" class="rtg-btn rtg-btn-primary">Import CSV</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Format Reference Card -->
    <div class="rtg-card" style="max-width: 700px;">
        <div class="rtg-card-header">
            <h2>Expected CSV Format</h2>
            <p>Your CSV should have a header row with these columns (order doesn't matter):</p>
        </div>
        <div class="rtg-card-body">
            <div class="rtg-field-row" style="border-bottom: none;">
                <code class="rtg-code-block">Tire ID, Size, Diameter, Brand, Model, Category, Price, Mileage Warranty, Weight (lb), 3PMS, Tread, Load Index, Max Load (lb), Load Range, Speed (mph), PSI, UTQG, Tags, Link, Image, Efficiency Score, Efficiency Grade, Bundle Link</code>
            </div>
        </div>
    </div>
</div>
