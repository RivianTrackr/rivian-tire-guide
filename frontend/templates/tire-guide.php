<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<button id="toggleFilters" class="toggle-filters-btn" aria-expanded="false" aria-controls="mobileFilterContent">
  <i class="fa-solid fa-sliders" aria-hidden="true"></i>&nbsp; Show Filters
</button>
<div id="filterTop"></div>
<div class="filter-wrapper">
  <div class="filter-header">
    <span class="filter-header-title">
      <i class="fa-solid fa-sliders" aria-hidden="true"></i>
      Filter, Sort, and Compare
    </span>
    <div class="filter-header-actions">
      <button class="rtg-clear-filters-btn" onclick="resetFilters()" type="button" aria-label="Clear all filters">
        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear All
      </button>
      <?php if ( is_user_logged_in() ) : ?>
      <input type="checkbox" id="filterFavorites" class="rtg-fav-heart-input" aria-label="Filter to my favorites"/>
      <label for="filterFavorites" class="rtg-fav-heart-btn" role="button" aria-label="Toggle favorites filter" title="Show only my favorites">
        <i class="fa-regular fa-heart rtg-fav-heart-outline" aria-hidden="true"></i>
        <i class="fa-solid fa-heart rtg-fav-heart-filled" aria-hidden="true"></i>
        <span id="favoritesCount" class="rtg-fav-heart-badge" style="display: none;"></span>
      </label>
      <?php endif; ?>
    </div>
  </div>
  <div class="filter-body">
    <div class="rtg-search-section">
      <div class="rtg-search-row">
        <div class="search-container">
          <label for="searchInput" class="screen-reader-text">Search tires</label>
          <input id="searchInput" type="text" class="search-input" placeholder="Search tires..." maxlength="500" aria-label="Search tires" />
        </div>
        <button id="rtgSearchSubmit" class="rtg-search-btn" type="button" aria-label="Search tires">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search
        </button>
      </div>
    </div>
    <div id="mobileFilterContent" class="mobile-filter-content">
      <div class="filter-container">
        <div id="vehicleToggle" class="rtg-vehicle-toggle" role="radiogroup" aria-label="Filter by vehicle">
          <button type="button" class="rtg-vehicle-btn active" data-vehicle="" aria-pressed="true">All</button>
        </div>
        <div class="filter-group">
          <label for="filterSize" class="screen-reader-text">Filter by tire size</label>
          <select id="filterSize" aria-label="Filter by tire size">
            <option value="">All Sizes</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="filterBrand" class="screen-reader-text">Filter by brand</label>
          <select id="filterBrand" aria-label="Filter by brand">
            <option value="">All Brands</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="filterCategory" class="screen-reader-text">Filter by category</label>
          <select id="filterCategory" aria-label="Filter by category">
            <option value="">All Categories</option>
          </select>
        </div>
      </div>
      <div class="rtg-extended-filters">
        <div class="filter-group slider-wrapper">
          <label for="priceMax">Max Price: <span id="priceVal">&le; $600</span></label>
          <input id="priceMax" class="range-slider" type="range" min="0" max="600" value="600" step="10" aria-label="Maximum Price"/>
        </div>
        <div class="filter-group slider-wrapper">
          <label for="warrantyMin">Min Warranty: <span id="warrantyVal">&ge; 0 miles</span></label>
          <input id="warrantyMin" class="range-slider" type="range" min="0" max="80000" value="0" step="1000" aria-label="Minimum Warranty in miles"/>
        </div>
        <div class="switch-label">
          <span class="switch-text">
            <div style="display: flex; align-items: center; gap: 6px;">
              <span>3PMS</span>
              <button class="info-tooltip-trigger" data-tooltip-key="3PMS Filter">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
              </button>
            </div>
          </span>
          <input type="checkbox" id="filter3pms" aria-label="3PMS Rated" />
          <span class="switch-slider" onclick="document.getElementById('filter3pms').click()"></span>
        </div>
        <div class="switch-label">
          <span class="switch-text">
            <div style="display: flex; align-items: center; gap: 6px;">
              <span>OEM</span>
              <button class="info-tooltip-trigger" data-tooltip-key="OEM Filter">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
              </button>
            </div>
          </span>
          <input type="checkbox" id="filterOEM" aria-label="OEM"/>
          <span class="switch-slider" onclick="document.getElementById('filterOEM').click()"></span>
        </div>
      </div>
    </div>
    <div class="sort-wrapper">
      <span id="tireCount" class="tire-count" aria-live="polite">Showing 0 tires</span>
      <div class="rtg-sort-actions">
        <label for="sortBy" class="screen-reader-text">Sort tires by</label>
        <select id="sortBy" aria-label="Sort tires by">
        <option value="efficiencyGrade">Efficiency Grade</option>
        <option value="roamer-efficiency" selected>Real-World Efficiency</option>
        <option value="most-reviewed">Most Reviewed</option>
        <option value="newest">Newest Added</option>
        <option value="price-asc">Price: Low → High</option>
        <option value="price-desc">Price: High → Low</option>
        <option value="rating-desc">Rating: High → Low</option>
        <option value="warranty-desc">Warranty: High → Low</option>
        <option value="weight-asc">Weight: Light → Heavy</option>
      </select>
      </div>
    </div>
    <div id="filterResultCount" class="filter-result-count" aria-live="polite"></div>
  </div>
</div>
<?php
$rtg_wheels = RTG_Database::get_all_wheels();
if ( ! empty( $rtg_wheels ) ) :
?>
<div id="wheelDrawerContainer" class="rtg-wheel-callout">
  <button id="wheelDrawerTrigger" class="rtg-wheel-callout-trigger" aria-expanded="false" aria-controls="wheelDrawer">
    <span class="rtg-wheel-callout-label">
      <i class="fa-solid fa-truck-monster" aria-hidden="true"></i>
      Not sure which tire fits your Rivian?
    </span>
    <i class="fa-solid fa-chevron-down rtg-wheel-callout-chevron" aria-hidden="true"></i>
  </button>
  <div id="wheelDrawer" class="wheel-drawer">
    <p class="wheel-drawer-heading">Rivian Stock Wheel Guide</p>
    <?php
    // Build vehicle groups from wheel data.
    $rtg_vehicle_groups = array();
    foreach ( $rtg_wheels as $rtg_wheel ) {
      $vehicle_list = array_filter( array_map( 'trim', explode( ',', $rtg_wheel['vehicles'] ) ) );
      foreach ( $vehicle_list as $vehicle ) {
        if ( ! isset( $rtg_vehicle_groups[ $vehicle ] ) ) {
          $rtg_vehicle_groups[ $vehicle ] = array();
        }
        $rtg_vehicle_groups[ $vehicle ][] = $rtg_wheel;
      }
    }
    $rtg_vehicle_names = array_keys( $rtg_vehicle_groups );
    ?>
    <div class="wheel-tabs" role="tablist" aria-label="Filter wheels by vehicle">
      <?php foreach ( $rtg_vehicle_names as $idx => $vehicle_name ) : ?>
        <button
          class="wheel-tab<?php echo 0 === $idx ? ' active' : ''; ?>"
          role="tab"
          aria-selected="<?php echo 0 === $idx ? 'true' : 'false'; ?>"
          aria-controls="wheelPanel-<?php echo esc_attr( sanitize_title( $vehicle_name ) ); ?>"
          id="wheelTab-<?php echo esc_attr( sanitize_title( $vehicle_name ) ); ?>"
          data-vehicle="<?php echo esc_attr( sanitize_title( $vehicle_name ) ); ?>"
        ><?php echo esc_html( $vehicle_name ); ?></button>
      <?php endforeach; ?>
    </div>
    <?php foreach ( $rtg_vehicle_names as $idx => $vehicle_name ) :
      $slug = sanitize_title( $vehicle_name );
    ?>
    <div
      class="wheel-tab-panel<?php echo 0 === $idx ? ' active' : ''; ?>"
      role="tabpanel"
      id="wheelPanel-<?php echo esc_attr( $slug ); ?>"
      aria-labelledby="wheelTab-<?php echo esc_attr( $slug ); ?>"
      <?php echo 0 !== $idx ? 'hidden' : ''; ?>
    >
      <div class="wheel-card-grid">
        <?php foreach ( $rtg_vehicle_groups[ $vehicle_name ] as $rtg_wheel ) :
          $alt_list = array_filter( array_map( 'trim', explode( ',', $rtg_wheel['alt_sizes'] ) ) );
        ?>
        <div class="wheel-card">
          <?php if ( ! empty( $rtg_wheel['image'] ) ) : ?>
            <img class="wheel-card-img" src="<?php echo esc_url( $rtg_wheel['image'] ); ?>" alt="<?php echo esc_attr( $rtg_wheel['name'] ); ?>" />
          <?php endif; ?>
          <div class="wheel-card-body">
            <strong class="wheel-card-name"><?php echo esc_html( $rtg_wheel['name'] ); ?></strong>
            <div class="wheel-card-sizes">
              <span class="wheel-card-label">Stock</span>
              <code><?php echo esc_html( $rtg_wheel['stock_size'] ); ?></code>
            </div>
            <?php if ( ! empty( $alt_list ) ) : ?>
            <div class="wheel-card-sizes">
              <span class="wheel-card-label">Alt</span>
              <?php foreach ( $alt_list as $alt ) : ?>
                <code><?php echo esc_html( $alt ); ?></code>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<div id="activeFilters" class="active-filters" aria-label="Active filters" role="region"></div>
<div id="tireSection">
  <div id="tireCards"></div>
</div>
<div id="noResults" role="status" aria-live="polite" style="display: none;">
  No tires match your current filters.<br />Try adjusting the filters to see more options.
</div>
<div id="paginationControls"></div>
<div id="compareBar" class="compare-bar" role="region" aria-label="Tire comparison selection">
  <span id="compareCount" class="compare-count"></span>
  <button class="compare-bar-btn compare-bar-btn-go" onclick="openComparison()">Compare</button>
  <button class="compare-bar-btn compare-bar-btn-clear" onclick="clearCompare()">Clear</button>
</div>
<div id="imageModal" role="dialog" aria-label="Full size tire image" aria-modal="true">
  <div class="modal-content">
    <img id="modalImage" src="" alt="Full size tire image" />
  </div>
</div>
