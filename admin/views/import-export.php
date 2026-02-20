<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message  = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
$imported = isset( $_GET['imported'] ) ? intval( $_GET['imported'] ) : 0;
$updated  = isset( $_GET['updated'] ) ? intval( $_GET['updated'] ) : 0;
$skipped  = isset( $_GET['skipped'] ) ? intval( $_GET['skipped'] ) : 0;
?>

<div class="rtg-wrap">

    <?php if ( $message === 'imported' ) : ?>
        <div class="rtg-notice rtg-notice-success">
            <span>Import complete: <?php echo esc_html( $imported ); ?> added, <?php echo esc_html( $updated ); ?> updated, <?php echo esc_html( $skipped ); ?> skipped.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php elseif ( $message === 'no_file' ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span>No file was uploaded.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php elseif ( $message === 'invalid_type' ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span>Invalid file type. Please upload a CSV file.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php elseif ( $message === 'too_large' ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span>File is too large. Maximum size is 2 MB.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php elseif ( $message === 'read_error' ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span>Could not read the uploaded file.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php elseif ( $message === 'empty_file' ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span>The uploaded file is empty or has no header row.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php elseif ( $message === 'missing_columns' ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span>CSV is missing required columns. At minimum, <code>brand</code> and <code>model</code> columns must be present.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <h1 class="rtg-page-title">Import / Export</h1>
    </div>

    <div class="rtg-edit-grid">

        <!-- Import -->
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Import from CSV</h2>
                <p>Upload a CSV file to bulk-import tires. The CSV must include a header row with column names.</p>
            </div>
            <div class="rtg-card-body">
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'rtg_csv_import', 'rtg_import_nonce' ); ?>
                    <input type="hidden" name="rtg_csv_import" value="1">

                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="rtg_csv_file">CSV File</label>
                        </div>
                        <p class="rtg-field-description">Maximum 2 MB. Required columns: <code>brand</code>, <code>model</code>. All other columns are optional.</p>
                        <input type="file" id="rtg_csv_file" name="rtg_csv_file" accept=".csv" required>
                    </div>

                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label">Duplicate Handling</label>
                        </div>
                        <p class="rtg-field-description">What to do when a <code>tire_id</code> in the CSV already exists in the database.</p>
                        <div style="display: flex; gap: 20px; margin-top: 4px;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="radio" name="import_mode" value="skip" checked>
                                <span>Skip duplicates</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="radio" name="import_mode" value="update">
                                <span>Update existing tires</span>
                            </label>
                        </div>
                    </div>

                    <div class="rtg-field-row" style="border-bottom: none;">
                        <button type="submit" class="rtg-btn rtg-btn-primary">Import CSV</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Export -->
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Export to CSV</h2>
                <p>Download all tire data as a CSV file. This file can be re-imported or used as a backup.</p>
            </div>
            <div class="rtg-card-body">
                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label">Tire Count</label>
                    </div>
                    <?php $total = RTG_Database::get_tire_count(); ?>
                    <p style="font-size: 14px; color: var(--rtg-text-primary, #1d1d1f);"><?php echo esc_html( $total ); ?> tire<?php echo $total !== 1 ? 's' : ''; ?> will be exported.</p>
                </div>
                <div class="rtg-field-row" style="border-bottom: none;">
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-import&rtg_export=csv' ), 'rtg_csv_export' ) ); ?>" class="rtg-btn rtg-btn-primary">Download CSV</a>
                </div>
            </div>
        </div>

    </div>

    <!-- Column Reference -->
    <div class="rtg-card" style="margin-top: 20px;">
        <div class="rtg-card-header">
            <h2>CSV Column Reference</h2>
            <p>Use these column names in the header row of your CSV file. Columns can appear in any order.</p>
        </div>
        <div class="rtg-card-body">
            <div class="rtg-table-wrapper">
                <table class="rtg-table">
                    <thead>
                        <tr>
                            <th>Column</th>
                            <th>Required</th>
                            <th>Type</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>tire_id</code></td><td>No (auto-generated)</td><td>Text</td><td>tire001</td></tr>
                        <tr><td><code>brand</code></td><td><strong>Yes</strong></td><td>Text</td><td>Michelin</td></tr>
                        <tr><td><code>model</code></td><td><strong>Yes</strong></td><td>Text</td><td>Defender LTX M/S 2</td></tr>
                        <tr><td><code>size</code></td><td>No</td><td>Text</td><td>275/65R20</td></tr>
                        <tr><td><code>diameter</code></td><td>No</td><td>Text</td><td>20"</td></tr>
                        <tr><td><code>category</code></td><td>No</td><td>Text</td><td>All-Season</td></tr>
                        <tr><td><code>price</code></td><td>No</td><td>Number</td><td>285.99</td></tr>
                        <tr><td><code>mileage_warranty</code></td><td>No</td><td>Integer</td><td>70000</td></tr>
                        <tr><td><code>weight_lb</code></td><td>No</td><td>Number</td><td>38.5</td></tr>
                        <tr><td><code>three_pms</code></td><td>No</td><td>Yes/No</td><td>No</td></tr>
                        <tr><td><code>tread</code></td><td>No</td><td>Text</td><td>10/32</td></tr>
                        <tr><td><code>load_index</code></td><td>No</td><td>Text</td><td>116</td></tr>
                        <tr><td><code>max_load_lb</code></td><td>No</td><td>Integer</td><td>2756</td></tr>
                        <tr><td><code>load_range</code></td><td>No</td><td>Text</td><td>SL</td></tr>
                        <tr><td><code>speed_rating</code></td><td>No</td><td>Text</td><td>T</td></tr>
                        <tr><td><code>psi</code></td><td>No</td><td>Text</td><td>51</td></tr>
                        <tr><td><code>utqg</code></td><td>No</td><td>Text</td><td>620 A B</td></tr>
                        <tr><td><code>tags</code></td><td>No</td><td>Text</td><td>EV Rated, RIV</td></tr>
                        <tr><td><code>link</code></td><td>No</td><td>URL</td><td>https://example.com/tire</td></tr>
                        <tr><td><code>image</code></td><td>No</td><td>URL</td><td>https://example.com/tire.jpg</td></tr>
                        <tr><td><code>sort_order</code></td><td>No</td><td>Integer</td><td>0</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
