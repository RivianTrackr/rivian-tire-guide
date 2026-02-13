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
if ( $message === 'duplicate_id' ) {
    echo '<div class="notice notice-error is-dismissible"><p>A tire with that ID already exists.</p></div>';
}

$categories = array( 'All-Season', 'All-Terrain', 'Highway', 'Mud-Terrain', 'Performance', 'Rugged Terrain', 'Winter' );
?>

<div class="wrap">
    <h1><?php echo esc_html( $page_title ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
        <?php wp_nonce_field( 'rtg_tire_save', 'rtg_tire_nonce' ); ?>
        <input type="hidden" name="rtg_tire_save" value="1">
        <input type="hidden" name="editing_id" value="<?php echo esc_attr( $editing_id ); ?>">

        <div class="rtg-edit-grid">

            <!-- Identity -->
            <div class="rtg-fieldset">
                <h2>Identity</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="tire_id">Tire ID</label></th>
                        <td>
                            <input type="text" id="tire_id" name="tire_id" value="<?php echo esc_attr( $v['tire_id'] ); ?>" class="regular-text" placeholder="Auto-generated if blank" <?php echo $is_edit ? 'readonly' : ''; ?>>
                            <?php if ( $is_edit ) : ?>
                                <p class="description">Tire ID cannot be changed after creation.</p>
                            <?php else : ?>
                                <p class="description">Leave blank to auto-generate (e.g. tire166).</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="brand">Brand <span class="required">*</span></label></th>
                        <td><input type="text" id="brand" name="brand" value="<?php echo esc_attr( $v['brand'] ); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="model">Model <span class="required">*</span></label></th>
                        <td><input type="text" id="model" name="model" value="<?php echo esc_attr( $v['model'] ); ?>" class="regular-text" required></td>
                    </tr>
                </table>
            </div>

            <!-- Specifications -->
            <div class="rtg-fieldset">
                <h2>Specifications</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="size">Size</label></th>
                        <td><input type="text" id="size" name="size" value="<?php echo esc_attr( $v['size'] ); ?>" class="regular-text" placeholder="e.g. 275/60R20"></td>
                    </tr>
                    <tr>
                        <th><label for="diameter">Diameter</label></th>
                        <td><input type="text" id="diameter" name="diameter" value="<?php echo esc_attr( $v['diameter'] ); ?>" class="regular-text" placeholder='e.g. 33"'></td>
                    </tr>
                    <tr>
                        <th><label for="category">Category</label></th>
                        <td>
                            <select id="category" name="category">
                                <option value="">Select...</option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $v['category'], $cat ); ?>><?php echo esc_html( $cat ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="weight_lb">Weight (lb)</label></th>
                        <td><input type="number" id="weight_lb" name="weight_lb" value="<?php echo esc_attr( $v['weight_lb'] ); ?>" step="0.1" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="three_pms">3PMS Rated</label></th>
                        <td>
                            <select id="three_pms" name="three_pms">
                                <option value="No" <?php selected( $v['three_pms'], 'No' ); ?>>No</option>
                                <option value="Yes" <?php selected( $v['three_pms'], 'Yes' ); ?>>Yes</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tread">Tread Depth</label></th>
                        <td><input type="text" id="tread" name="tread" value="<?php echo esc_attr( $v['tread'] ); ?>" class="regular-text" placeholder="e.g. 10/32"></td>
                    </tr>
                    <tr>
                        <th><label for="load_index">Load Index</label></th>
                        <td><input type="text" id="load_index" name="load_index" value="<?php echo esc_attr( $v['load_index'] ); ?>" class="small-text" placeholder="e.g. 116"></td>
                    </tr>
                    <tr>
                        <th><label for="max_load_lb">Max Load (lb)</label></th>
                        <td><input type="number" id="max_load_lb" name="max_load_lb" value="<?php echo esc_attr( $v['max_load_lb'] ); ?>" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="load_range">Load Range</label></th>
                        <td><input type="text" id="load_range" name="load_range" value="<?php echo esc_attr( $v['load_range'] ); ?>" class="small-text" placeholder="e.g. XL"></td>
                    </tr>
                    <tr>
                        <th><label for="speed_rating">Speed Rating</label></th>
                        <td><input type="text" id="speed_rating" name="speed_rating" value="<?php echo esc_attr( $v['speed_rating'] ); ?>" class="regular-text" placeholder="e.g. T (118)"></td>
                    </tr>
                    <tr>
                        <th><label for="psi">Max PSI</label></th>
                        <td><input type="text" id="psi" name="psi" value="<?php echo esc_attr( $v['psi'] ); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="utqg">UTQG</label></th>
                        <td><input type="text" id="utqg" name="utqg" value="<?php echo esc_attr( $v['utqg'] ); ?>" class="regular-text" placeholder="e.g. 620 A B"></td>
                    </tr>
                </table>
            </div>

            <!-- Pricing & Links -->
            <div class="rtg-fieldset">
                <h2>Pricing &amp; Links</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="price">Price ($)</label></th>
                        <td><input type="number" id="price" name="price" value="<?php echo esc_attr( $v['price'] ); ?>" step="0.01" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="mileage_warranty">Mileage Warranty</label></th>
                        <td><input type="number" id="mileage_warranty" name="mileage_warranty" value="<?php echo esc_attr( $v['mileage_warranty'] ); ?>" min="0" step="1000" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="link">Affiliate Link</label></th>
                        <td><input type="url" id="link" name="link" value="<?php echo esc_attr( $v['link'] ); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th><label for="bundle_link">Bundle Link</label></th>
                        <td><input type="url" id="bundle_link" name="bundle_link" value="<?php echo esc_attr( $v['bundle_link'] ); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th><label for="image">Image URL</label></th>
                        <td>
                            <input type="url" id="image" name="image" value="<?php echo esc_attr( $v['image'] ); ?>" class="large-text">
                            <?php if ( ! empty( $v['image'] ) ) : ?>
                                <div style="margin-top:10px;">
                                    <img id="image-preview" src="<?php echo esc_url( $v['image'] ); ?>" alt="Preview" style="max-width:200px;max-height:120px;border-radius:6px;background:#fff;padding:4px;">
                                </div>
                            <?php else : ?>
                                <div id="image-preview-container" style="margin-top:10px;display:none;">
                                    <img id="image-preview" src="" alt="Preview" style="max-width:200px;max-height:120px;border-radius:6px;background:#fff;padding:4px;">
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Classification -->
            <div class="rtg-fieldset">
                <h2>Classification</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="tags">Tags</label></th>
                        <td>
                            <input type="text" id="tags" name="tags" value="<?php echo esc_attr( $v['tags'] ); ?>" class="large-text" placeholder="e.g. EV Rated, RIV">
                            <p class="description">Comma-separated tags.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="efficiency_score">Efficiency Score</label></th>
                        <td><input type="number" id="efficiency_score" name="efficiency_score" value="<?php echo esc_attr( $v['efficiency_score'] ); ?>" min="0" max="100" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="efficiency_grade">Efficiency Grade</label></th>
                        <td>
                            <select id="efficiency_grade" name="efficiency_grade">
                                <option value="">Select...</option>
                                <?php foreach ( array( 'A', 'B', 'C', 'D', 'E', 'F' ) as $g ) : ?>
                                    <option value="<?php echo esc_attr( $g ); ?>" <?php selected( strtoupper( $v['efficiency_grade'] ), $g ); ?>><?php echo esc_html( $g ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sort_order">Sort Order</label></th>
                        <td>
                            <input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr( $v['sort_order'] ); ?>" min="0" class="small-text">
                            <p class="description">Lower numbers appear first (0 = default).</p>
                        </td>
                    </tr>
                </table>
            </div>

        </div>

        <?php submit_button( $is_edit ? 'Update Tire' : 'Add Tire' ); ?>
    </form>
</div>
