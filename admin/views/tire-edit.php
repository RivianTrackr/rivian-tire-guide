<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$editing_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$tire = $editing_id ? RTG_Database::get_tire_by_id( $editing_id ) : null;
$is_edit = (bool) $tire;
$page_title = $is_edit ? 'Edit Tire' : 'Add New Tire';

// Default values.
$defaults = array(
    'tire_id'          => '',
    'size'             => '',
    'diameter'         => '',
    'brand'            => '',
    'model'            => '',
    'category'         => '',
    'price'            => '',
    'mileage_warranty' => '',
    'weight_lb'        => '',
    'three_pms'        => 'No',
    'tread'            => '',
    'load_index'       => '',
    'max_load_lb'      => '',
    'load_range'       => '',
    'speed_rating'     => '',
    'psi'              => '',
    'utqg'             => '',
    'tags'             => '',
    'link'             => '',
    'image'            => '',
    'efficiency_score' => '',
    'efficiency_grade' => '',
    'bundle_link'      => '',
    'sort_order'       => 0,
);
$v = $tire ? wp_parse_args( $tire, $defaults ) : $defaults;

// Notices.
$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

// Load managed dropdown options.
$dd_brands        = RTG_Admin::get_dropdown_options( 'brands' );
$dd_categories    = RTG_Admin::get_dropdown_options( 'categories' );
$dd_sizes         = RTG_Admin::get_dropdown_options( 'sizes' );
$dd_diameters     = RTG_Admin::get_dropdown_options( 'diameters' );
$dd_load_ranges   = RTG_Admin::get_dropdown_options( 'load_ranges' );
$dd_speed_ratings = RTG_Admin::get_dropdown_options( 'speed_ratings' );
$dd_load_index_map = RTG_Admin::get_load_index_map();
?>

<div class="rtg-wrap">

    <?php if ( $message === 'duplicate_id' ) : ?>
        <div class="rtg-notice rtg-notice-error">
            <span>A tire with that ID already exists.</span>
            <button type="button" class="rtg-notice-dismiss" aria-label="Dismiss">&times;</button>
        </div>
    <?php endif; ?>

    <div class="rtg-page-header">
        <h1 class="rtg-page-title"><?php echo esc_html( $page_title ); ?></h1>
    </div>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
        <?php wp_nonce_field( 'rtg_tire_save', 'rtg_tire_nonce' ); ?>
        <input type="hidden" name="rtg_tire_save" value="1">
        <input type="hidden" name="editing_id" value="<?php echo esc_attr( $editing_id ); ?>">

        <div class="rtg-edit-grid">

            <!-- Identity -->
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Identity</h2>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="tire_id">Tire ID</label>
                        </div>
                        <?php if ( $is_edit ) : ?>
                            <p class="rtg-field-description">Tire ID cannot be changed after creation.</p>
                        <?php else : ?>
                            <p class="rtg-field-description">Leave blank to auto-generate (e.g. tire166).</p>
                        <?php endif; ?>
                        <input type="text" id="tire_id" name="tire_id" value="<?php echo esc_attr( $v['tire_id'] ); ?>" placeholder="Auto-generated if blank" <?php echo $is_edit ? 'readonly' : ''; ?>>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="brand">Brand <span class="rtg-badge-required">Required</span></label>
                        </div>
                        <select id="brand" name="brand" required>
                            <option value="">Select...</option>
                            <?php foreach ( $dd_brands as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v['brand'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="model">Model <span class="rtg-badge-required">Required</span></label>
                        </div>
                        <input type="text" id="model" name="model" value="<?php echo esc_attr( $v['model'] ); ?>" required>
                    </div>
                </div>
            </div>

            <!-- Specifications -->
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Specifications</h2>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="size">Size</label>
                        </div>
                        <select id="size" name="size">
                            <option value="">Select...</option>
                            <?php foreach ( $dd_sizes as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v['size'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="diameter">Diameter</label>
                        </div>
                        <select id="diameter" name="diameter">
                            <option value="">Select...</option>
                            <?php foreach ( $dd_diameters as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v['diameter'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="category">Category</label>
                        </div>
                        <select id="category" name="category">
                            <option value="">Select...</option>
                            <?php foreach ( $dd_categories as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v['category'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="weight_lb">Weight (lb)</label>
                        </div>
                        <input type="number" id="weight_lb" name="weight_lb" value="<?php echo esc_attr( $v['weight_lb'] ); ?>" step="0.1" min="0" class="rtg-input-small">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="three_pms">3PMS Rated</label>
                        </div>
                        <select id="three_pms" name="three_pms">
                            <option value="No" <?php selected( $v['three_pms'], 'No' ); ?>>No</option>
                            <option value="Yes" <?php selected( $v['three_pms'], 'Yes' ); ?>>Yes</option>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="tread">Tread Depth</label>
                        </div>
                        <input type="text" id="tread" name="tread" value="<?php echo esc_attr( $v['tread'] ); ?>" placeholder="e.g. 10/32">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="load_index">Load Index</label>
                        </div>
                        <select id="load_index" name="load_index">
                            <option value="">Select...</option>
                            <?php foreach ( $dd_load_index_map as $idx => $lbs ) : ?>
                                <option value="<?php echo esc_attr( $idx ); ?>" data-max-load="<?php echo esc_attr( $lbs ); ?>" <?php selected( $v['load_index'], (string) $idx ); ?>><?php echo esc_html( $idx ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="max_load_lb">Max Load (lb)</label>
                            <span class="rtg-badge rtg-badge-info">Auto-filled</span>
                        </div>
                        <input type="text" id="max_load_lb" name="max_load_lb" value="<?php echo esc_attr( $v['max_load_lb'] ); ?>" readonly style="background:#f5f5f7;color:#86868b;">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="load_range">Load Range</label>
                        </div>
                        <select id="load_range" name="load_range">
                            <option value="">Select...</option>
                            <?php foreach ( $dd_load_ranges as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v['load_range'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="speed_rating">Speed Rating</label>
                        </div>
                        <select id="speed_rating" name="speed_rating">
                            <option value="">Select...</option>
                            <?php foreach ( $dd_speed_ratings as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v['speed_rating'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="psi">Max PSI</label>
                        </div>
                        <input type="text" id="psi" name="psi" value="<?php echo esc_attr( $v['psi'] ); ?>" class="rtg-input-small">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="utqg">UTQG</label>
                        </div>
                        <input type="text" id="utqg" name="utqg" value="<?php echo esc_attr( $v['utqg'] ); ?>" placeholder="e.g. 620 A B">
                    </div>
                </div>
            </div>

            <!-- Pricing & Links -->
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Pricing &amp; Links</h2>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="price">Price ($)</label>
                        </div>
                        <input type="number" id="price" name="price" value="<?php echo esc_attr( $v['price'] ); ?>" step="0.01" min="0" class="rtg-input-small">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="mileage_warranty">Mileage Warranty</label>
                        </div>
                        <input type="number" id="mileage_warranty" name="mileage_warranty" value="<?php echo esc_attr( $v['mileage_warranty'] ); ?>" min="0" step="1000">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="link">Affiliate Link</label>
                        </div>
                        <input type="url" id="link" name="link" value="<?php echo esc_attr( $v['link'] ); ?>" class="rtg-input-wide">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="bundle_link">Bundle Link</label>
                        </div>
                        <input type="url" id="bundle_link" name="bundle_link" value="<?php echo esc_attr( $v['bundle_link'] ); ?>" class="rtg-input-wide">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="image">Image URL</label>
                        </div>
                        <input type="url" id="image" name="image" value="<?php echo esc_attr( $v['image'] ); ?>" class="rtg-input-wide">
                        <?php if ( ! empty( $v['image'] ) ) : ?>
                            <div class="rtg-image-preview">
                                <img id="image-preview" src="<?php echo esc_url( $v['image'] ); ?>" alt="Preview">
                            </div>
                        <?php else : ?>
                            <div id="image-preview-container" class="rtg-image-preview" style="display:none;">
                                <img id="image-preview" src="" alt="Preview">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Classification -->
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Classification</h2>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="tags">Tags</label>
                        </div>
                        <p class="rtg-field-description">Comma-separated tags.</p>
                        <input type="text" id="tags" name="tags" value="<?php echo esc_attr( $v['tags'] ); ?>" class="rtg-input-wide" placeholder="e.g. EV Rated, RIV">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label">Efficiency Score &amp; Grade</label>
                            <span class="rtg-badge rtg-badge-info">Auto-calculated</span>
                        </div>
                        <p class="rtg-field-description">Calculated from weight, tread, width, load range, speed rating, UTQG, category, and 3PMS.</p>
                        <div id="rtg-efficiency-preview" style="display:flex;align-items:center;gap:12px;margin-top:8px;">
                            <?php
                            $current_score = intval( $v['efficiency_score'] );
                            $current_grade = strtoupper( $v['efficiency_grade'] );
                            $grade_class = $current_grade ? 'rtg-grade-' . strtolower( $current_grade ) : 'rtg-grade-none';
                            ?>
                            <span id="rtg-eff-grade" class="rtg-grade <?php echo esc_attr( $grade_class ); ?>"><?php echo esc_html( $current_grade ?: '-' ); ?></span>
                            <span id="rtg-eff-score" style="font-size:20px;font-weight:600;color:#1d1d1f;"><?php echo esc_html( $current_score ); ?></span>
                            <span style="font-size:14px;color:#86868b;">/ 100</span>
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
            <button type="submit" class="rtg-btn rtg-btn-primary"><?php echo $is_edit ? 'Update Tire' : 'Add Tire'; ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires' ) ); ?>" class="rtg-btn rtg-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
