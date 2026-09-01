<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Addressed by row number, or by tire_id — which is what everything outside
// the admin carries. A frontend card, a tire page and a REST payload all know
// the public identifier and none of them know the database row.
$edit_target = RTG_Admin::resolve_edit_target( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen resolution.
$editing_id  = $edit_target['id'];
$tire        = $edit_target['tire'];

$is_edit = (bool) $tire;
$page_title = $is_edit ? 'Edit Tire' : 'Add New Tire';

// Default values.
$defaults = array(
    'tire_id'          => '',
    'size'             => '',
    'diameter'         => '',
    'brand'            => '',
    'model'            => '',
    'model_aliases'    => '',
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
    'bundle_link'      => '',
    'image'            => '',
    'efficiency_score' => '',
    'efficiency_grade' => '',
    'review_link'      => '',
    'sort_order'       => 0,
);
$v = $tire ? wp_parse_args( $tire, $defaults ) : $defaults;

// Prefill a new tire from a Tire Discovery candidate. Only the fields the
// affiliate feed actually knows are filled — category, warranty, weight and
// tread still need a human, so they are deliberately left blank rather than
// guessed at.
$from_candidate       = ( ! $is_edit && isset( $_GET['from_candidate'] ) ) ? intval( $_GET['from_candidate'] ) : 0;
$from_candidate_image = '';
if ( $from_candidate > 0 ) {
    $candidate = RTG_Candidates::get( $from_candidate );
    if ( $candidate ) {
        $from_candidate_image = trim( (string) $candidate['image'] );
        $v['brand']        = $candidate['brand'];
        $v['model']        = $candidate['model'];
        $v['size']         = $candidate['size'];
        $v['price']        = $candidate['price'] > 0 ? $candidate['price'] : '';
        $v['load_index']   = $candidate['load_index'];
        $v['load_range']   = $candidate['load_range'];
        $v['speed_rating'] = $candidate['speed_rating'];
        $v['link']         = $candidate['link'];

        // Derive the fields the guide computes from a size / load index.
        $diameter_map = RTG_Admin::get_size_diameter_map();
        if ( isset( $diameter_map[ $candidate['size'] ] ) ) {
            $v['diameter'] = $diameter_map[ $candidate['size'] ];
        }
        $load_map = RTG_Admin::get_load_index_map();
        if ( '' !== $candidate['load_index'] && isset( $load_map[ intval( $candidate['load_index'] ) ] ) ) {
            $v['max_load_lb'] = $load_map[ intval( $candidate['load_index'] ) ];
        }

        $page_title = 'Add New Tire — from discovery';
    } else {
        $from_candidate = 0;
    }
}

// Notices.
$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

// A blocked duplicate: the save collided with a tire already in the guide.
// The whole submission was stashed on the way out, so the form comes back
// exactly as the admin left it — price, links, specs, tags and all — not
// just the three identity fields the redirect URL carries.
$duplicate_of      = 'duplicate_tire' === $message ? sanitize_text_field( wp_unslash( $_GET['duplicate_of'] ?? '' ) ) : '';
$duplicate_of_tire = '' !== $duplicate_of ? RTG_Database::get_tire( $duplicate_of ) : null;
// The same applies when an edit collided: the form comes back with the
// edited values, not the stored ones the admin was trying to change.
if ( in_array( $message, array( 'duplicate_tire', 'duplicate_id' ), true ) ) {
    foreach ( RTG_Admin::take_blocked_save() as $blocked_field => $blocked_value ) {
        if ( 'tire_id' === $blocked_field && $is_edit ) {
            continue; // The stored ID is read-only on the edit form.
        }
        if ( array_key_exists( $blocked_field, $v ) && '' !== $blocked_value && null !== $blocked_value ) {
            $v[ $blocked_field ] = $blocked_value;
        }
    }

    // Fallback for a redirect with no stash (an old bookmark, an expired
    // transient): the identity fields still ride on the URL.
    if ( 'duplicate_tire' === $message ) {
        foreach ( array( 'brand', 'model', 'size' ) as $identity_field ) {
            $returned = sanitize_text_field( wp_unslash( $_GET[ $identity_field ] ?? '' ) );
            if ( '' !== $returned && '' === $v[ $identity_field ] ) {
                $v[ $identity_field ] = $returned;
            }
        }
    }
}

// Load managed dropdown options.
$dd_brands        = RTG_Admin::get_dropdown_options( 'brands' );
$dd_categories    = RTG_Admin::get_dropdown_options( 'categories' );
$dd_sizes         = RTG_Admin::get_dropdown_options( 'sizes' );
$dd_size_diameter_map = RTG_Admin::get_size_diameter_map();
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

    <?php if ( $message === 'duplicate_tire' ) : ?>
        <div class="rtg-notice rtg-notice-warning">
            <span>
                <strong>This tire is already in the guide<?php echo '' !== $duplicate_of ? ' as ' . esc_html( $duplicate_of ) : ''; ?>.</strong>
                Same brand and size, under a model name the matcher reads as this tire &mdash;
                its own, one of its aliases, or a longer or shorter spelling of it.
                <?php if ( $duplicate_of_tire && ! empty( $duplicate_of_tire['id'] ) ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rtg-tire-edit', 'id' => intval( $duplicate_of_tire['id'] ) ), admin_url( 'admin.php' ) ) ); ?>">Edit the existing tire</a> instead &mdash;
                <?php endif; ?>
                or, if this is deliberately a separate entry, tick &ldquo;<?php echo $is_edit ? 'Save anyway' : 'Add anyway'; ?>&rdquo; below the save button and save again.
            </span>
        </div>
    <?php endif; ?>

    <?php if ( $message === 'duplicated' ) : ?>
        <div class="rtg-notice rtg-notice-success">
            <span>Tire duplicated successfully. You are now editing the copy.</span>
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
        <?php if ( $from_candidate > 0 ) : ?>
            <input type="hidden" name="from_candidate" value="<?php echo esc_attr( $from_candidate ); ?>">
        <?php endif; ?>

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
                    <?php if ( $is_edit ) : ?>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="slug">URL Slug</label>
                        </div>
                        <p class="rtg-field-description">
                            <?php if ( ! empty( $v['slug'] ) ) : ?>
                                Public page: <a href="<?php echo esc_url( RTG_Tire_Page::tire_url( $v['slug'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( RTG_Tire_Page::tire_url( $v['slug'] ) ); ?></a>.
                            <?php endif; ?>
                            Editing creates a 301 redirect from the old slug. Regenerates automatically when brand, model, or size change.
                        </p>
                        <input type="text" id="slug" name="slug" value="<?php echo esc_attr( $v['slug'] ?? '' ); ?>" style="font-family:monospace;">
                    </div>
                    <?php endif; ?>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="brand">Brand <span class="rtg-badge-required">Required</span></label>
                        </div>
                        <?php
                        $brand_options = $dd_brands;
                        if ( ! empty( $v['brand'] ) && ! in_array( $v['brand'], $brand_options, true ) ) {
                            $brand_options[] = $v['brand'];
                        }
                        ?>
                        <select id="brand" name="brand" required>
                            <option value="">Select...</option>
                            <?php foreach ( $brand_options as $opt ) : ?>
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
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="model_aliases">Retailer also lists this as</label>
                        </div>
                        <textarea id="model_aliases" name="model_aliases" rows="2" placeholder="Ridge Grappler LT"><?php echo esc_textarea( $v['model_aliases'] ?? '' ); ?></textarea>
                        <p class="description">
                            One name per line. Retailers spell a model their own way, and matching, pricing and
                            delisting all key on the model — an alias lets those accept the retailer's spelling
                            without changing the name readers see.
                        </p>
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
                        <?php
                        $size_options = $dd_sizes;
                        if ( ! empty( $v['size'] ) && ! in_array( $v['size'], $size_options, true ) ) {
                            $size_options[] = $v['size'];
                        }
                        ?>
                        <select id="size" name="size">
                            <option value="">Select...</option>
                            <?php foreach ( $size_options as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" data-diameter="<?php echo esc_attr( $dd_size_diameter_map[ $opt ] ?? '' ); ?>" <?php selected( $v['size'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="diameter">Tire Diameter</label>
                            <span class="rtg-badge rtg-badge-info">Auto-filled</span>
                        </div>
                        <p class="rtg-field-description">Auto-filled from the size selection. Configured in Settings &rarr; Size &rarr; Tire Diameter map.</p>
                        <input type="text" id="diameter" name="diameter" value="<?php echo esc_attr( $v['diameter'] ); ?>" readonly style="background:#f5f5f7;color:#86868b;">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="category">Category</label>
                        </div>
                        <?php
                        $category_options = $dd_categories;
                        if ( ! empty( $v['category'] ) && ! in_array( $v['category'], $category_options, true ) ) {
                            $category_options[] = $v['category'];
                        }
                        ?>
                        <select id="category" name="category">
                            <option value="">Select...</option>
                            <?php foreach ( $category_options as $opt ) : ?>
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
                        <?php
                        $load_range_options = $dd_load_ranges;
                        if ( ! empty( $v['load_range'] ) && ! in_array( $v['load_range'], $load_range_options, true ) ) {
                            $load_range_options[] = $v['load_range'];
                        }
                        ?>
                        <select id="load_range" name="load_range">
                            <option value="">Select...</option>
                            <?php foreach ( $load_range_options as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v['load_range'], $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="speed_rating">Speed Rating</label>
                        </div>
                        <?php
                        $speed_rating_options = $dd_speed_ratings;
                        if ( ! empty( $v['speed_rating'] ) && ! in_array( $v['speed_rating'], $speed_rating_options, true ) ) {
                            $speed_rating_options[] = $v['speed_rating'];
                        }
                        ?>
                        <select id="speed_rating" name="speed_rating">
                            <option value="">Select...</option>
                            <?php foreach ( $speed_rating_options as $opt ) : ?>
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
                        <p class="rtg-field-description">Optional link to a set-of-four or bundle offer. Shown on the Affiliate Links page alongside the purchase link.</p>
                        <input type="url" id="bundle_link" name="bundle_link" value="<?php echo esc_attr( $v['bundle_link'] ); ?>" class="rtg-input-wide">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="review_link">Review Link</label>
                        </div>
                        <p class="rtg-field-description">Link to your article or video review (YouTube, RivianTrackr, etc.).</p>
                        <input type="url" id="review_link" name="review_link" value="<?php echo esc_attr( $v['review_link'] ); ?>" class="rtg-input-wide">
                    </div>
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="image">Image URL</label>
                        </div>
                        <?php
                        $image_prefix = 'https://riviantrackr.com/assets/tire-guide/images/';
                        $image_display = $v['image'];
                        // Strip the prefix for display so users only see the filename.
                        if ( ! empty( $image_display ) && strpos( $image_display, $image_prefix ) === 0 ) {
                            $image_display = substr( $image_display, strlen( $image_prefix ) );
                        }
                        ?>
                        <div style="display:flex;align-items:center;gap:0;">
                            <span style="background:#f5f5f7;border:1px solid var(--rtg-border,#d2d2d7);border-right:none;border-radius:8px 0 0 8px;padding:8px 10px;font-size:13px;color:#86868b;white-space:nowrap;"><?php echo esc_html( $image_prefix ); ?></span>
                            <input type="text" id="image" name="image" value="<?php echo esc_attr( $image_display ); ?>" class="rtg-input-wide" style="border-radius:0 8px 8px 0;" placeholder="filename.webp">
                        </div>
                        <input type="hidden" id="image_prefix" value="<?php echo esc_attr( $image_prefix ); ?>">
                        <p style="margin:8px 0 0;display:flex;align-items:center;gap:10px;">
                            <button type="button" id="rtg-fetch-image-btn" class="rtg-btn rtg-btn-secondary">
                                <i class="fa-solid fa-cloud-arrow-down"></i> Fetch from catalog
                            </button>
                            <span id="rtg-fetch-image-msg" style="font-size:13px;"></span>
                        </p>
                        <script>
                        jQuery(function ($) {
                            $('#rtg-fetch-image-btn').on('click', function () {
                                var $btn = $(this), $msg = $('#rtg-fetch-image-msg');
                                $btn.prop('disabled', true);
                                $msg.css('color', '').text('Fetching…');

                                $.post(ajaxurl, {
                                    action: 'rtg_fetch_tire_image',
                                    nonce: '<?php echo esc_js( wp_create_nonce( 'rtg_admin_nonce' ) ); ?>',
                                    brand: $('#brand').val() || '',
                                    model: $('#model').val() || '',
                                    size: $('#size').val() || '',
                                    model_aliases: $('#model_aliases').val() || ''
                                }, function (r) {
                                    $btn.prop('disabled', false);
                                    if (r && r.success) {
                                        $('#image').val(r.data.filename);
                                        $('#image-preview').attr('src', r.data.url);
                                        $('#image-preview').closest('.rtg-image-preview, #image-preview-container').show();
                                        $msg.css('color', 'var(--rtg-success, #34c759)')
                                            .text(r.data.filename + ' saved to the images folder — save the tire to keep it.');
                                    } else {
                                        $msg.css('color', 'var(--rtg-error, #ff3b30)')
                                            .text((r && r.data) ? r.data : 'The request failed.');
                                    }
                                }).fail(function () {
                                    $btn.prop('disabled', false);
                                    $msg.css('color', 'var(--rtg-error, #ff3b30)').text('The request failed.');
                                });
                            });
                        });
                        </script>
                        <?php
                        $full_image_url = $v['image'];
                        ?>
                        <?php if ( ! empty( $full_image_url ) ) : ?>
                            <div class="rtg-image-preview">
                                <img id="image-preview" src="<?php echo esc_url( $full_image_url ); ?>" alt="Preview">
                            </div>
                        <?php elseif ( '' !== $from_candidate_image ) : ?>
                            <p class="rtg-field-description" style="margin-top:8px;">
                                <i class="fa-solid fa-cloud-arrow-down"></i>
                                The catalog has a product image for this tire. Leave this field blank and saving
                                will download it into your images folder automatically &mdash; or type a filename
                                to use your own.
                            </p>
                            <div class="rtg-image-preview">
                                <img id="image-preview" src="<?php echo esc_url( $from_candidate_image ); ?>" alt="Catalog product image">
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
                        <p class="rtg-field-description">Comma-separated tags. Click a tag below to add it.</p>
                        <input type="text" id="tags" name="tags" value="<?php echo esc_attr( $v['tags'] ); ?>" class="rtg-input-wide" placeholder="e.g. EV Rated, RIV">
                        <?php
                        $existing_tags = RTG_Database::get_all_tags();
                        if ( ! empty( $existing_tags ) ) :
                        ?>
                        <div id="rtg-tag-suggestions" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                            <?php foreach ( $existing_tags as $tag ) : ?>
                                <button type="button" class="rtg-tag-suggestion" data-tag="<?php echo esc_attr( $tag ); ?>" style="background:#f5f5f7;border:1px solid var(--rtg-border,#d2d2d7);border-radius:12px;padding:4px 12px;font-size:12px;color:#1d1d1f;cursor:pointer;transition:background 0.15s;"><?php echo esc_html( $tag ); ?></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php // Efficiency score/grade still auto-calculate on save (kept in the
                          // DB for potential future use) but are no longer surfaced here —
                          // the metric was removed from the frontend in 1.51.0. ?>
                    <input type="hidden" name="sort_order" value="<?php echo esc_attr( $v['sort_order'] ); ?>">
                </div>
            </div>

            <!-- Roamer Real-World Data -->
            <div class="rtg-card">
                <div class="rtg-card-header">
                    <h2>Rivian Roamer — Real-World Data</h2>
                </div>
                <div class="rtg-card-body">
                    <div class="rtg-field-row">
                        <div class="rtg-field-label-row">
                            <label class="rtg-field-label" for="roamer_tire_id">Roamer Tire ID</label>
                        </div>
                        <p class="rtg-field-description">Slug from Rivian Roamer (e.g. michelin-defender-ltx-275-65r20). Set manually or auto-assigned during sync.</p>
                        <input type="text" id="roamer_tire_id" name="roamer_tire_id" value="<?php echo esc_attr( $v['roamer_tire_id'] ?? '' ); ?>" class="rtg-input-wide" placeholder="e.g. michelin-defender-ltx-275-65r20">
                    </div>
                    <?php
                    $r_eff     = floatval( $v['roamer_efficiency'] ?? 0 );
                    $r_km      = floatval( $v['roamer_total_km'] ?? 0 );
                    $r_veh     = intval( $v['roamer_vehicle_count'] ?? 0 );
                    $r_bd_raw  = $v['roamer_vehicle_breakdown'] ?? '';
                    $r_bd      = ! empty( $r_bd_raw ) ? json_decode( $r_bd_raw, true ) : array();
                    $r_synced  = $v['roamer_synced_at'] ?? '';
                    ?>
                    <?php if ( $r_eff > 0 ) : ?>
                        <div class="rtg-field-row">
                            <div class="rtg-field-label-row">
                                <label class="rtg-field-label">Real-World Efficiency</label>
                                <span class="rtg-badge rtg-badge-info">From Roamer</span>
                            </div>
                            <div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:8px;">
                                <div>
                                    <span style="font-size:24px;font-weight:700;color:#1d1d1f;"><?php echo esc_html( number_format( $r_eff, 2 ) ); ?></span>
                                    <span style="font-size:14px;color:#86868b;">mi/kWh</span>
                                </div>
                                <div>
                                    <span style="font-size:18px;font-weight:600;color:#1d1d1f;"><?php echo esc_html( number_format( $r_km * 0.621371, 0 ) ); ?></span>
                                    <span style="font-size:14px;color:#86868b;">mi tracked</span>
                                </div>
                                <div>
                                    <span style="font-size:18px;font-weight:600;color:#1d1d1f;"><?php echo intval( $r_veh ); ?></span>
                                    <span style="font-size:14px;color:#86868b;">vehicles</span>
                                </div>
                            </div>
                            <?php if ( ! empty( $r_bd ) && is_array( $r_bd ) ) : ?>
                                <div style="margin-top:10px;">
                                    <span style="font-size:12px;font-weight:600;color:#86868b;text-transform:uppercase;">Vehicle Breakdown</span>
                                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">
                                        <?php foreach ( $r_bd as $entry ) :
                                            // Feed format: array of [name, count] pairs.
                                            if ( ! is_array( $entry ) || count( $entry ) < 2 ) continue;
                                            $drivetrain = $entry[0];
                                            $count      = $entry[1];
                                        ?>
                                            <span style="font-size:13px;padding:2px 8px;background:rgba(59,130,246,0.1);border-radius:4px;color:#3b82f6;">
                                                <?php echo esc_html( $drivetrain ); ?>: <?php echo intval( $count ); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ( $r_synced ) : ?>
                                <p style="margin-top:8px;font-size:12px;color:#86868b;">Last synced: <?php echo esc_html( $r_synced ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <p style="color:#86868b;margin-top:8px;">No Roamer data linked. Set a Roamer Tire ID above or run a sync from the <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-roamer-sync' ) ); ?>">Roamer Sync</a> page.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="rtg-footer-actions">
            <button type="submit" class="rtg-btn rtg-btn-primary"><?php echo $is_edit ? 'Update Tire' : 'Add Tire'; ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-tires' ) ); ?>" class="rtg-btn rtg-btn-secondary">Cancel</a>
            <?php if ( 'duplicate_tire' === $message ) : ?>
                <label style="display:inline-flex;align-items:center;gap:6px;margin-left:12px;font-size:13px;color:#6e6e73;">
                    <input type="checkbox" name="allow_duplicate" value="1">
                    <?php echo $is_edit ? 'Save anyway' : 'Add anyway'; ?> &mdash; this is deliberately a separate entry
                </label>
            <?php endif; ?>
        </div>
    </form>
</div>
