<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Re-use existing dashboard stats.
$stats = RTG_Database::get_dashboard_stats();

$core           = $stats['core'];
$total_tires    = (int) ( $core['total_tires'] ?? 0 );
$avg_price      = floatval( $core['avg_price'] ?? 0 );
$avg_efficiency = (int) ( $core['avg_efficiency'] ?? 0 );
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
if ( ! empty( $stats['top_rated'] ) ) {
	$top       = $stats['top_rated'][0];
	$top_tire  = $top['brand'] . ' ' . $top['model'];
	$top_rating = $top['avg_rating'];
}

$site_name = get_bloginfo( 'name' );
?>

<div class="rtg-wrap">

	<div class="rtg-page-header">
		<h1 class="rtg-page-title">Share Image</h1>
	</div>

	<div class="rtg-card">
		<div class="rtg-card-header">
			<h2>Stats Share Image</h2>
			<p>Generate a branded image with your tire guide stats to share on social media.</p>
		</div>
		<div class="rtg-card-body">
			<div style="margin-bottom: 20px;">
				<canvas id="rtg-share-canvas" width="1200" height="630" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid var(--rtg-border);"></canvas>
			</div>
			<div style="display: flex; gap: 12px; flex-wrap: wrap;">
				<button type="button" id="rtg-download-image" class="rtg-btn rtg-btn-primary">
					<span class="dashicons dashicons-download" style="margin-right: 4px;"></span>
					Download Image
				</button>
				<button type="button" id="rtg-copy-image" class="rtg-btn rtg-btn-secondary">
					<span class="dashicons dashicons-clipboard" style="margin-right: 4px;"></span>
					Copy to Clipboard
				</button>
				<button type="button" id="rtg-regenerate-image" class="rtg-btn rtg-btn-secondary">
					<span class="dashicons dashicons-update" style="margin-right: 4px;"></span>
					Regenerate
				</button>
			</div>
			<p id="rtg-share-status" style="margin-top: 12px; font-size: 13px; color: var(--rtg-text-secondary);"></p>
		</div>
	</div>

	<div class="rtg-card">
		<div class="rtg-card-header">
			<h2>Customize</h2>
		</div>
		<div class="rtg-card-body">
			<div class="rtg-field-row">
				<div class="rtg-field-label-row">
					<label class="rtg-field-label" for="rtg-share-title">Title</label>
				</div>
				<input type="text" id="rtg-share-title" class="rtg-text-input" value="Rivian Tire Guide" style="width: 100%; max-width: 400px;">
			</div>
			<div class="rtg-field-row">
				<div class="rtg-field-label-row">
					<label class="rtg-field-label" for="rtg-share-subtitle">Subtitle</label>
				</div>
				<input type="text" id="rtg-share-subtitle" class="rtg-text-input" value="by <?php echo esc_attr( $site_name ); ?>" style="width: 100%; max-width: 400px;">
			</div>
			<div class="rtg-field-row">
				<div class="rtg-field-label-row">
					<label class="rtg-field-label" for="rtg-share-footer">Footer Text</label>
				</div>
				<input type="text" id="rtg-share-footer" class="rtg-text-input" value="Find the perfect tires for your Rivian" style="width: 100%; max-width: 400px;">
			</div>
		</div>
	</div>

</div>

<script>
window.rtgShareData = {
	totalTires: <?php echo (int) $total_tires; ?>,
	avgPrice: <?php echo round( $avg_price, 2 ); ?>,
	avgEfficiency: <?php echo (int) $avg_efficiency; ?>,
	totalReviews: <?php echo (int) $total_reviews; ?>,
	avgRating: <?php echo round( $avg_rating, 1 ); ?>,
	categories: <?php echo wp_json_encode( $categories ); ?>,
	brands: <?php echo wp_json_encode( $brands ); ?>,
	topTire: <?php echo wp_json_encode( $top_tire ); ?>,
	topRating: <?php echo wp_json_encode( $top_rating ); ?>,
};
</script>
