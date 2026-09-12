<?php
/**
 * Share image panel of the Tools page. Rendered inside tools.php.
 *
 * The canvas is drawn by initShareImage() in admin-scripts.js from the
 * figures published on window.rtgShareData below.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Re-use existing dashboard stats.
$stats = RTG_Database::get_dashboard_stats();

$core           = $stats['core'];
$total_tires    = (int) ( $core['total_tires'] ?? 0 );
$avg_price      = floatval( $core['avg_price'] ?? 0 );
$total_reviews  = (int) ( $stats['ratings']['total_ratings'] ?? 0 );
$avg_rating     = floatval( $stats['ratings']['avg_rating'] ?? 0 );

// Category breakdown for chart.
$categories = array();
foreach ( ( $stats['by_category'] ?? array() ) as $row ) {
	$categories[] = array(
		'name'  => $row['category'],
		'count' => (int) $row['count'],
	);
}

// Top brands.
$brands = array();
foreach ( array_slice( $stats['by_brand'] ?? array(), 0, 5 ) as $row ) {
	$brands[] = array(
		'name'  => $row['brand'],
		'count' => (int) $row['count'],
	);
}

// Top rated tire.
$top_tire = '';
$top_rating = '';
$top_size = '';
if ( ! empty( $stats['top_rated'] ) ) {
	$top        = $stats['top_rated'][0];
	$top_tire   = $top['brand'] . ' ' . $top['model'];
	$top_rating = $top['avg_rating'];
	$top_size   = $top['size'] ?? '';
}

$site_name = get_bloginfo( 'name' );
?>

<div class="rtg-card">
	<div class="rtg-card-header">
		<h2>Stats share image</h2>
		<p>A branded 1200 by 630 image with the guide's headline numbers, ready for a social post. It redraws as you edit the text below.</p>
	</div>
	<div class="rtg-card-body">
		<canvas id="rtg-share-canvas" width="1200" height="630" class="rtg-canvas-preview"></canvas>
		<div class="rtg-toolbar">
			<button type="button" id="rtg-download-image" class="rtg-btn rtg-btn-primary">
				<span class="dashicons dashicons-download"></span>
				Download image
			</button>
			<button type="button" id="rtg-copy-image" class="rtg-btn rtg-btn-secondary">
				<span class="dashicons dashicons-clipboard"></span>
				Copy to clipboard
			</button>
			<button type="button" id="rtg-regenerate-image" class="rtg-btn rtg-btn-secondary">
				<span class="dashicons dashicons-update"></span>
				Redraw
			</button>
			<span id="rtg-share-status" class="rtg-inline-status" role="status"></span>
		</div>
	</div>
	<div class="rtg-card-body">
		<div class="rtg-field-grid">
			<div class="rtg-field-row">
				<div class="rtg-field-label-row">
					<label class="rtg-field-label" for="rtg-share-title">Title</label>
				</div>
				<input type="text" id="rtg-share-title" value="Rivian Tire Guide">
			</div>
			<div class="rtg-field-row">
				<div class="rtg-field-label-row">
					<label class="rtg-field-label" for="rtg-share-subtitle">Subtitle</label>
				</div>
				<input type="text" id="rtg-share-subtitle" value="by <?php echo esc_attr( $site_name ); ?>">
			</div>
			<div class="rtg-field-row">
				<div class="rtg-field-label-row">
					<label class="rtg-field-label" for="rtg-share-footer">Footer text</label>
				</div>
				<input type="text" id="rtg-share-footer" value="Find the perfect tires for your Rivian">
			</div>
		</div>
	</div>
</div>

<script>
window.rtgShareData = {
	totalTires: <?php echo (int) $total_tires; ?>,
	avgPrice: <?php echo round( $avg_price, 2 ); ?>,
	totalReviews: <?php echo (int) $total_reviews; ?>,
	avgRating: <?php echo round( $avg_rating, 1 ); ?>,
	categories: <?php echo wp_json_encode( $categories ); ?>,
	brands: <?php echo wp_json_encode( $brands ); ?>,
	topTire: <?php echo wp_json_encode( $top_tire ); ?>,
	topSize: <?php echo wp_json_encode( $top_size ); ?>,
	topRating: <?php echo wp_json_encode( $top_rating ); ?>,
};
</script>
