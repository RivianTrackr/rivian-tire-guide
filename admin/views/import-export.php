<?php
/**
 * Import / Export panel of the Tools page. Rendered inside tools.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message  = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
$imported = isset( $_GET['imported'] ) ? intval( $_GET['imported'] ) : 0;
$updated  = isset( $_GET['updated'] ) ? intval( $_GET['updated'] ) : 0;
$skipped  = isset( $_GET['skipped'] ) ? intval( $_GET['skipped'] ) : 0;

$import_errors = array(
    'no_file'         => 'No file was uploaded.',
    'invalid_type'    => 'That is not a CSV file. Upload a .csv export.',
    'too_large'       => 'The file is too large. The limit is 2 MB.',
    'read_error'      => 'The uploaded file could not be read.',
    'empty_file'      => 'The file is empty or has no header row.',
    'missing_columns' => 'The CSV is missing required columns. At minimum it needs a brand column and a model column.',
);

$total = RTG_Database::get_tire_count();
?>

<?php if ( $message === 'imported' ) : ?>
    <div class="rtg-notice rtg-notice-success">
        <span>Import complete: <?php echo esc_html( $imported ); ?> added, <?php echo esc_html( $updated ); ?> updated, <?php echo esc_html( $skipped ); ?> skipped.</span>
        <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
    </div>
<?php elseif ( isset( $import_errors[ $message ] ) ) : ?>
    <div class="rtg-notice rtg-notice-error">
        <span><?php echo esc_html( $import_errors[ $message ] ); ?></span>
        <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
    </div>
<?php endif; ?>

<div class="rtg-edit-grid">

    <!-- Import -->
    <div class="rtg-card">
        <div class="rtg-card-header">
            <h2>Import from CSV</h2>
            <p>Add or update tires in bulk from a spreadsheet. The first row must be column names; the reference below lists them.</p>
        </div>
        <div class="rtg-card-body">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'rtg_csv_import', 'rtg_import_nonce' ); ?>
                <input type="hidden" name="rtg_csv_import" value="1">

                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <label class="rtg-field-label" for="rtg_csv_file">CSV file</label>
                    </div>
                    <p class="rtg-field-description">Up to 2 MB. Required columns: <code>brand</code> and <code>model</code>. Everything else is optional.</p>
                    <input type="file" id="rtg_csv_file" name="rtg_csv_file" accept=".csv" required>
                </div>

                <div class="rtg-field-row">
                    <div class="rtg-field-label-row">
                        <span class="rtg-field-label">When a tire already exists</span>
                    </div>
                    <p class="rtg-field-description">Decided by the <code>tire_id</code> column.</p>
                    <div class="rtg-choice-list is-inline">
                        <label class="rtg-choice">
                            <input type="radio" name="import_mode" value="skip" checked>
                            <span>Skip it</span>
                        </label>
                        <label class="rtg-choice">
                            <input type="radio" name="import_mode" value="update">
                            <span>Update it with the CSV's values</span>
                        </label>
                    </div>
                </div>

                <div class="rtg-field-row">
                    <button type="submit" class="rtg-btn rtg-btn-primary">Import CSV</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Export -->
    <div class="rtg-card">
        <div class="rtg-card-header">
            <h2>Export to CSV</h2>
            <p>Download the whole catalog as a CSV file. It can be re-imported, edited in a spreadsheet, or kept as a backup.</p>
        </div>
        <div class="rtg-card-body">
            <div class="rtg-field-row">
                <div class="rtg-kv-grid">
                    <div>
                        <span class="rtg-kv-label">Tires in the export</span>
                        <div class="rtg-kv-value"><?php echo esc_html( number_format( $total ) ); ?></div>
                    </div>
                </div>
            </div>
            <div class="rtg-field-row">
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-import&rtg_export=csv' ), 'rtg_csv_export' ) ); ?>" class="rtg-btn rtg-btn-primary">
                    <span class="dashicons dashicons-download"></span> Download CSV
                </a>
            </div>
        </div>
    </div>

</div>

<!-- Column Reference -->
<div class="rtg-card">
    <div class="rtg-card-header">
        <h2>CSV column reference</h2>
        <p>Use these names in the header row. Columns can appear in any order.</p>
    </div>
    <div class="rtg-card-body is-flush">
        <div class="rtg-table-wrapper">
            <table class="rtg-table rtg-table-compact">
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
                    <tr><td><code>model_aliases</code></td><td>No</td><td>Text (one alias per line, quoted)</td><td>Defender LTX MS2</td></tr>
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
                    <tr><td><code>bundle_link</code></td><td>No</td><td>URL</td><td>https://example.com/tire-bundle</td></tr>
                    <tr><td><code>image</code></td><td>No</td><td>URL</td><td>https://example.com/tire.jpg</td></tr>
                    <tr><td><code>review_link</code></td><td>No</td><td>URL</td><td>https://youtube.com/watch?v=…</td></tr>
                    <tr><td><code>sort_order</code></td><td>No</td><td>Integer</td><td>0</td></tr>
                    <tr><td><code>slug</code></td><td>No (auto-generated)</td><td>Text</td><td>michelin-defender-ltx-ms-2-275-65r20</td></tr>
                    <tr><td><code>roamer_tire_id</code></td><td>No</td><td>Text</td><td>Rivian Roamer tire identifier, as assigned on the Roamer Data page</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
