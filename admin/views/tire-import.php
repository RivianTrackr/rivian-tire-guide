<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

// Show import results.
$import_result = get_transient( 'rtg_import_result' );
if ( $message === 'imported' && $import_result ) {
    delete_transient( 'rtg_import_result' );
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo sprintf(
        'Import complete: <strong>%d inserted</strong>, <strong>%d updated</strong>, <strong>%d skipped</strong>.',
        $import_result['inserted'],
        $import_result['updated'],
        $import_result['skipped']
    );
    echo '</p></div>';

    if ( ! empty( $import_result['errors'] ) ) {
        echo '<div class="notice notice-warning is-dismissible"><p><strong>Errors:</strong></p><ul>';
        foreach ( $import_result['errors'] as $error ) {
            echo '<li>' . esc_html( $error ) . '</li>';
        }
        echo '</ul></div>';
    }
}

$error_messages = array(
    'no_file'      => 'No file was uploaded.',
    'invalid_type' => 'Invalid file type. Please upload a .csv file.',
    'too_large'    => 'File is too large. Maximum size is 2MB.',
);

if ( $message && isset( $error_messages[ $message ] ) ) {
    printf(
        '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
        esc_html( $error_messages[ $message ] )
    );
}

$tire_count = RTG_Database::get_tire_count();
?>

<div class="wrap">
    <h1>Import Tires from CSV</h1>

    <div class="card" style="max-width:700px;">
        <h2>CSV Import</h2>

        <?php if ( $tire_count > 0 ) : ?>
            <p>Your database currently contains <strong><?php echo esc_html( $tire_count ); ?></strong> tires.</p>
        <?php else : ?>
            <p>Your database is empty. Import your tire data from a CSV file to get started.</p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'rtg_import_csv', 'rtg_import_nonce' ); ?>
            <input type="hidden" name="rtg_import_csv" value="1">

            <table class="form-table">
                <tr>
                    <th><label for="csv_file">CSV File</label></th>
                    <td>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                        <p class="description">Maximum file size: 2MB. Must be a .csv file.</p>
                    </td>
                </tr>
                <tr>
                    <th>Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="update_existing" value="1" checked>
                            Update existing tires (matched by Tire ID)
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Import CSV' ); ?>
        </form>
    </div>

    <div class="card" style="max-width:700px;margin-top:20px;">
        <h2>Expected CSV Format</h2>
        <p>Your CSV should have a header row with these columns (order doesn't matter):</p>
        <code style="display:block;padding:10px;background:#f0f0f1;border-radius:4px;font-size:12px;line-height:1.8;word-break:break-all;">
            Tire ID, Size, Diameter, Brand, Model, Category, Price, Mileage Warranty, Weight (lb), 3PMS, Tread, Load Index, Max Load (lb), Load Range, Speed (mph), PSI, UTQG, Tags, Link, Image, Efficiency Score, Efficiency Grade, Bundle Link
        </code>
    </div>
</div>
