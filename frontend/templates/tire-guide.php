<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php if ( RTG_AI::is_enabled() ) : ?>
<div id="rtgAiWrapper" class="rtg-ai-wrapper">
  <div class="rtg-ai-header">
    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
    <span>AI Tire Advisor</span>
  </div>
  <div class="rtg-ai-search">
    <label for="rtgAiInput" class="screen-reader-text">Ask AI for tire recommendations</label>
    <input id="rtgAiInput" type="text" class="rtg-ai-input" placeholder="Try: &quot;Best winter tire for my Rivian with 20 inch wheels&quot;" maxlength="500" aria-label="Ask AI for tire recommendations" />
    <button id="rtgAiSubmit" class="rtg-ai-submit" type="button" aria-label="Get AI recommendations">
      <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
    </button>
  </div>
  <div id="rtgAiStatus" class="rtg-ai-status" style="display: none;" role="status" aria-live="polite"></div>
  <div id="rtgAiSummary" class="rtg-ai-summary" style="display: none;" role="region" aria-label="AI recommendations summary"></div>
</div>
<?php endif; ?>
<button id="toggleFilters" class="toggle-filters-btn" aria-expanded="false" aria-controls="mobileFilterContent">
  <i class="fa-solid fa-sliders" aria-hidden="true"></i>&nbsp; Show Filters
</button>
<div id="filterTop"></div>
<div class="filter-wrapper">
  <div class="filter-header">
    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
    Filter, Sort, and Compare
  </div>
  <div class="filter-body">
    <div class="filter-search">
      <div class="search-container">
        <label for="searchInput" class="screen-reader-text">Search tires</label>
        <input id="searchInput" type="text" placeholder="Search tires..." class="search-input" aria-label="Search tires"/>
        <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
      </div>
    </div>
    <div id="mobileFilterContent" class="mobile-filter-content">
      <div class="filter-container">
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
        <div class="slider-row">
          <div class="filter-group slider-wrapper">
            <label for="priceMax">Average Price: <span id="priceVal">$600</span></label>
            <input id="priceMax" class="range-slider" type="range" min="0" max="600" value="600" step="10" aria-label="Maximum Price"/>
          </div>
          <div class="filter-group slider-wrapper">
            <label for="warrantyMax">Warranty: <span id="warrantyVal">80,000 miles</span></label>
            <input id="warrantyMax" class="range-slider" type="range" min="0" max="80000" value="80000" step="1000" aria-label="Maximum Warranty in miles"/>
          </div>
          <div class="filter-group slider-wrapper">
            <label for="weightMax">Weight: <span id="weightVal">70</span></label>
            <input id="weightMax" class="range-slider" type="range" min="0" max="70" value="70" step="1" aria-label="Maximum Weight in pounds"/>
          </div>
        </div>
      </div>
      <div class="switch-row">
        <div class="switch-label">
          <span class="switch-text">
            <div style="display: flex; align-items: center; gap: 6px;">
              <span>3PMS Rated</span>
              <button class="info-tooltip-trigger" data-tooltip-key="3PMS Filter" style="background: none; border: none; color: #94a3b8; font-size: 14px; cursor: pointer; padding: 2px; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;" onmouseenter="this.style.color='var(--rtg-accent, #5ec095)'; this.style.backgroundColor='color-mix(in srgb, var(--rtg-accent, #5ec095) 10%, transparent)'" onmouseleave="this.style.color='#94a3b8'; this.style.backgroundColor='transparent'">
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
              <span>EV Rated</span>
              <button class="info-tooltip-trigger" data-tooltip-key="EV Rated Filter" style="background: none; border: none; color: #94a3b8; font-size: 14px; cursor: pointer; padding: 2px; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;" onmouseenter="this.style.color='var(--rtg-accent, #5ec095)'; this.style.backgroundColor='color-mix(in srgb, var(--rtg-accent, #5ec095) 10%, transparent)'" onmouseleave="this.style.color='#94a3b8'; this.style.backgroundColor='transparent'">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
              </button>
            </div>
          </span>
          <input type="checkbox" id="filterEVRated" aria-label="EV Rated" />
          <span class="switch-slider" onclick="document.getElementById('filterEVRated').click()"></span>
        </div>
        <div class="switch-label">
          <span class="switch-text">
            <div style="display: flex; align-items: center; gap: 6px;">
              <span>Studded Available</span>
              <button class="info-tooltip-trigger" data-tooltip-key="Studded Available Filter" style="background: none; border: none; color: #94a3b8; font-size: 14px; cursor: pointer; padding: 2px; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;" onmouseenter="this.style.color='var(--rtg-accent, #5ec095)'; this.style.backgroundColor='color-mix(in srgb, var(--rtg-accent, #5ec095) 10%, transparent)'" onmouseleave="this.style.color='#94a3b8'; this.style.backgroundColor='transparent'">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
              </button>
            </div>
          </span>
          <input type="checkbox" id="filterStudded" aria-label="Studded Available"/>
          <span class="switch-slider" onclick="document.getElementById('filterStudded').click()"></span>
        </div>
        <div class="switch-label">
          <span class="switch-text">
            <div style="display: flex; align-items: center; gap: 6px;">
              <span>Officially Reviewed</span>
              <button class="info-tooltip-trigger" data-tooltip-key="Officially Reviewed Filter" style="background: none; border: none; color: #94a3b8; font-size: 14px; cursor: pointer; padding: 2px; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;" onmouseenter="this.style.color='var(--rtg-accent, #5ec095)'; this.style.backgroundColor='color-mix(in srgb, var(--rtg-accent, #5ec095) 10%, transparent)'" onmouseleave="this.style.color='#94a3b8'; this.style.backgroundColor='transparent'">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
              </button>
            </div>
          </span>
          <input type="checkbox" id="filterReviewed" aria-label="Officially Reviewed"/>
          <span class="switch-slider" onclick="document.getElementById('filterReviewed').click()"></span>
        </div>
        <?php if ( is_user_logged_in() ) : ?>
        <div class="switch-label favorites-filter-wrapper">
          <span class="switch-text">
            <div style="display: flex; align-items: center; gap: 6px;">
              <i class="fa-solid fa-heart" aria-hidden="true"></i>
              <span>My Favorites</span>
            </div>
          </span>
          <span id="favoritesCount" class="favorites-count-badge" style="display: none;"></span>
          <input type="checkbox" id="filterFavorites" aria-label="My Favorites"/>
          <span class="switch-slider" onclick="document.getElementById('filterFavorites').click()"></span>
        </div>
        <?php endif; ?>
        <label class="switch-label reset-white" onclick="resetFilters()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();resetFilters()}" role="button" tabindex="0">
          <span class="switch-text">
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Clear All
          </span>
        </label>
      </div>
      <?php
      $rtg_wheels = RTG_Database::get_all_wheels();
      if ( ! empty( $rtg_wheels ) ) :
      ?>
      <div id="wheelDrawerContainer">
        <button id="wheelDrawerTrigger" class="wheel-trigger">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          Not sure which Rivian tire you need?
        </button>
        <div id="wheelDrawer" class="wheel-drawer">
          <p class="wheel-drawer-heading">Rivian Stock Wheel Guide</p>
          <div class="wheel-items">
            <?php foreach ( $rtg_wheels as $rtg_wheel ) :
              $alt_list = array_filter( array_map( 'trim', explode( ',', $rtg_wheel['alt_sizes'] ) ) );
              $vehicle_list = array_filter( array_map( 'trim', explode( ',', $rtg_wheel['vehicles'] ) ) );
            ?>
            <div class="wheel-item">
              <?php if ( ! empty( $rtg_wheel['image'] ) ) : ?>
                <img src="<?php echo esc_url( $rtg_wheel['image'] ); ?>" alt="<?php echo esc_attr( $rtg_wheel['name'] ); ?>" />
              <?php endif; ?>
              <div>
                <strong><?php echo esc_html( $rtg_wheel['name'] ); ?></strong>
                <?php if ( ! empty( $vehicle_list ) ) : ?>
                  <span class="wheel-vehicle-badges">
                    <?php foreach ( $vehicle_list as $vehicle ) : ?>
                      <span class="wheel-vehicle-badge"><?php echo esc_html( $vehicle ); ?></span>
                    <?php endforeach; ?>
                  </span>
                <?php endif; ?>
                <br />Stock: <code><?php echo esc_html( $rtg_wheel['stock_size'] ); ?></code>
                <?php if ( ! empty( $alt_list ) ) : ?>
                  <br />Alt:
                  <?php foreach ( $alt_list as $alt ) : ?>
                    <code><?php echo esc_html( $alt ); ?></code>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<div class="sort-wrapper">
  <span id="tireCount" class="tire-count" aria-live="polite">Showing 0 tires</span>
  <div style="flex: 1;"></div>
  <label for="sortBy" class="screen-reader-text">Sort tires by</label>
  <select id="sortBy" aria-label="Sort tires by" onchange="filterAndRender()">
    <option value="efficiencyGrade">Efficiency Grade</option>
    <option value="most-reviewed">Most Reviewed</option>
    <option value="newest">Newest Added</option>
    <option value="price-asc">Price: Low → High</option>
    <option value="price-desc">Price: High → Low</option>
    <option value="rating-desc" selected>Rating: High → Low</option>
    <option value="warranty-desc">Warranty: High → Low</option>
    <option value="weight-asc">Weight: Light → Heavy</option>
  </select>
</div>
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
