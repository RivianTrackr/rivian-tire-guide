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

$categories = array( 'All-Season', 'All-Terrain', 'Highway', 'Mud-Terrain', 'Performance', 'Rugged Terrain', 'Winter' );
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
                        <input type="text" id="brand" name="brand" value="<?php echo esc_attr( $v['brand'] ); ?>" required>
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
                        <input type="text" id="size" name="size" value="<?php echo esc_attr( $v['size'] ); ?>" placeholder="e.g. 275/60R20">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="diameter">Diameter</label>
                        </div>
                        <input type="text" id="diameter" name="diameter" value="<?php echo esc_attr( $v['diameter'] ); ?>" placeholder='e.g. 33"'>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="category">Category</label>
                        </div>
                        <select id="category" name="category">
                            <option value="">Select...</option>
                            <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $v['category'], $cat ); ?>><?php echo esc_html( $cat ); ?></option>
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
                        <input type="text" id="load_index" name="load_index" value="<?php echo esc_attr( $v['load_index'] ); ?>" class="rtg-input-small" placeholder="e.g. 116">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="max_load_lb">Max Load (lb)</label>
                        </div>
                        <input type="number" id="max_load_lb" name="max_load_lb" value="<?php echo esc_attr( $v['max_load_lb'] ); ?>" min="0" class="rtg-input-small">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="load_range">Load Range</label>
                        </div>
                        <input type="text" id="load_range" name="load_range" value="<?php echo esc_attr( $v['load_range'] ); ?>" class="rtg-input-small" placeholder="e.g. XL">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="speed_rating">Speed Rating</label>
                        </div>
                        <input type="text" id="speed_rating" name="speed_rating" value="<?php echo esc_attr( $v['speed_rating'] ); ?>" placeholder="e.g. T (118)">
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
                            <label class="rtg-field-label" for="efficiency_score">Efficiency Score</label>
                        </div>
                        <input type="number" id="efficiency_score" name="efficiency_score" value="<?php echo esc_attr( $v['efficiency_score'] ); ?>" min="0" max="100" class="rtg-input-small">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="efficiency_grade">Efficiency Grade</label>
                        </div>
                        <select id="efficiency_grade" name="efficiency_grade">
                            <option value="">Select...</option>
                            <?php foreach ( array( 'A', 'B', 'C', 'D', 'E', 'F' ) as $g ) : ?>
                                <option value="<?php echo esc_attr( $g ); ?>" <?php selected( strtoupper( $v['efficiency_grade'] ), $g ); ?>><?php echo esc_html( $g ); ?></option>
                            <?php endforeach; ?>
                        </select>
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
