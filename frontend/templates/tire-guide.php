<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// The review page, for the header's Write a Review button.
$rtg_guide_settings   = get_option( 'rtg_settings', array() );
$rtg_write_review_url = home_url( '/' . sanitize_title( $rtg_guide_settings['tire_review_slug'] ?? 'tire-review' ) . '/' );
?>
<div id="filterTop"></div>
<div class="filter-wrapper" id="rtgFilterBar">
  <div class="filter-body">

    <div class="rtg-search-row">
      <div class="search-container">
        <label for="searchInput" class="screen-reader-text">Search tires</label>
        <i class="fa-solid fa-magnifying-glass rtg-search-icon" aria-hidden="true"></i>
        <input id="searchInput" type="search" class="search-input" placeholder="Search by brand, model, or size" maxlength="500" aria-label="Search tires" autocomplete="off" />
      </div>
      <?php if ( RTG_Advisor::is_enabled() ) : ?>
      <button id="rtgAdvisorOpen" class="rtg-advisor-btn" type="button">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Help me choose
      </button>
      <?php endif; ?>
    </div>

    <div class="rtg-filter-row">
      <div id="vehicleToggle" class="rtg-vehicle-toggle" role="radiogroup" aria-label="Filter by vehicle">
        <button type="button" class="rtg-vehicle-btn active" data-vehicle="" aria-pressed="true">All</button>
      </div>
      <span class="rtg-filter-divider" aria-hidden="true"></span>

      <div class="rtg-filter-chips" id="rtgFilterChips">
        <div class="rtg-sheet-head">
          <span class="rtg-sheet-title">Filters</span>
          <button type="button" class="rtg-sheet-close" data-sheet-close aria-label="Close filters"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>

        <button type="button" id="rtgAllFilters" class="rtg-fchip rtg-fchip-all" aria-haspopup="dialog" aria-expanded="false" aria-controls="rtgFilterChips">
          <i class="fa-solid fa-sliders" aria-hidden="true"></i> Filters <span class="rtg-fchip-badge" hidden></span>
        </button>

        <div class="rtg-fitem" data-key="size">
          <button type="button" class="rtg-fchip" data-pop aria-expanded="false" aria-controls="rtgPopSize">
            <span class="rtg-fchip-label">Size</span><span class="rtg-fchip-value"></span>
            <i class="fa-solid fa-chevron-down rtg-fchip-caret" aria-hidden="true"></i>
          </button>
          <div class="rtg-fpop" id="rtgPopSize">
            <div class="rtg-fpop-head"><span>Tire size</span></div>
            <div class="rtg-fopts" data-for="filterSize" role="listbox" aria-label="Tire size"></div>
            <label for="filterSize" class="screen-reader-text">Filter by tire size</label>
            <select id="filterSize" class="rtg-fselect" aria-label="Filter by tire size">
              <option value="">All Sizes</option>
            </select>
          </div>
        </div>

        <div class="rtg-fitem" data-key="brand">
          <button type="button" class="rtg-fchip" data-pop aria-expanded="false" aria-controls="rtgPopBrand">
            <span class="rtg-fchip-label">Brand</span><span class="rtg-fchip-value"></span>
            <i class="fa-solid fa-chevron-down rtg-fchip-caret" aria-hidden="true"></i>
          </button>
          <div class="rtg-fpop" id="rtgPopBrand">
            <div class="rtg-fpop-head"><span>Brand</span></div>
            <div class="rtg-fopts" data-for="filterBrand" role="listbox" aria-label="Brand"></div>
            <label for="filterBrand" class="screen-reader-text">Filter by brand</label>
            <select id="filterBrand" class="rtg-fselect" aria-label="Filter by brand">
              <option value="">All Brands</option>
            </select>
          </div>
        </div>

        <div class="rtg-fitem" data-key="category">
          <button type="button" class="rtg-fchip" data-pop aria-expanded="false" aria-controls="rtgPopCategory">
            <span class="rtg-fchip-label">Category</span><span class="rtg-fchip-value"></span>
            <i class="fa-solid fa-chevron-down rtg-fchip-caret" aria-hidden="true"></i>
          </button>
          <div class="rtg-fpop" id="rtgPopCategory">
            <div class="rtg-fpop-head"><span>Category</span></div>
            <div class="rtg-fopts" data-for="filterCategory" role="listbox" aria-label="Category"></div>
            <label for="filterCategory" class="screen-reader-text">Filter by category</label>
            <select id="filterCategory" class="rtg-fselect" aria-label="Filter by category">
              <option value="">All Categories</option>
            </select>
          </div>
        </div>

        <div class="rtg-fitem" data-key="price">
          <button type="button" class="rtg-fchip" data-pop aria-expanded="false" aria-controls="rtgPopPrice">
            <span class="rtg-fchip-label">Price</span><span class="rtg-fchip-value"></span>
            <i class="fa-solid fa-chevron-down rtg-fchip-caret" aria-hidden="true"></i>
          </button>
          <div class="rtg-fpop" id="rtgPopPrice">
            <div class="rtg-fpop-head"><label for="priceMax">Max price</label><span id="priceVal" class="rtg-fpop-val">&le; $600</span></div>
            <input id="priceMax" class="range-slider" type="range" min="0" max="600" value="600" step="10" aria-label="Maximum Price"/>
            <div class="rtg-fpresets" data-for="priceMax" data-kind="price">
              <button type="button" class="rtg-fpreset" data-value="250">Under $250</button>
              <button type="button" class="rtg-fpreset" data-value="350">Under $350</button>
              <button type="button" class="rtg-fpreset" data-value="450">Under $450</button>
              <button type="button" class="rtg-fpreset" data-value="max">Any</button>
            </div>
            <div class="rtg-fpop-foot">
              <button type="button" class="rtg-fpop-reset" data-reset="priceMax">Reset</button>
              <button type="button" class="rtg-fpop-done" data-pop-close>Done</button>
            </div>
          </div>
        </div>

        <div class="rtg-fitem" data-key="warranty">
          <button type="button" class="rtg-fchip" data-pop aria-expanded="false" aria-controls="rtgPopWarranty">
            <span class="rtg-fchip-label">Warranty</span><span class="rtg-fchip-value"></span>
            <i class="fa-solid fa-chevron-down rtg-fchip-caret" aria-hidden="true"></i>
          </button>
          <div class="rtg-fpop" id="rtgPopWarranty">
            <div class="rtg-fpop-head"><label for="warrantyMin">Minimum mileage warranty</label><span id="warrantyVal" class="rtg-fpop-val">&ge; 0 miles</span></div>
            <input id="warrantyMin" class="range-slider" type="range" min="0" max="80000" value="0" step="1000" aria-label="Minimum Warranty in miles"/>
            <div class="rtg-fpresets" data-for="warrantyMin" data-kind="warranty">
              <button type="button" class="rtg-fpreset" data-value="min">Any</button>
              <button type="button" class="rtg-fpreset" data-value="40000">40k+</button>
              <button type="button" class="rtg-fpreset" data-value="50000">50k+</button>
              <button type="button" class="rtg-fpreset" data-value="60000">60k+</button>
              <button type="button" class="rtg-fpreset" data-value="70000">70k+</button>
            </div>
            <div class="rtg-fpop-foot">
              <button type="button" class="rtg-fpop-reset" data-reset="warrantyMin">Reset</button>
              <button type="button" class="rtg-fpop-done" data-pop-close>Done</button>
            </div>
          </div>
        </div>

        <div class="rtg-fitem rtg-fitem-toggle" data-key="3pms">
          <button type="button" class="rtg-fchip rtg-fchip-toggle" data-toggle="filter3pms" aria-pressed="false">
            <span class="rtg-fchip-label">3PMS</span>
            <span class="rtg-fchip-check"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
            <span class="rtg-fswitch" aria-hidden="true"></span>
          </button>
          <button type="button" class="info-tooltip-trigger rtg-fchip-info" data-tooltip-key="3PMS Filter" aria-label="More info about 3PMS">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          </button>
          <input type="checkbox" id="filter3pms" class="rtg-fcheckbox" aria-label="3PMS Rated" />
        </div>

        <div class="rtg-fitem rtg-fitem-toggle" data-key="oem">
          <button type="button" class="rtg-fchip rtg-fchip-toggle" data-toggle="filterOEM" aria-pressed="false">
            <span class="rtg-fchip-label">OEM</span>
            <span class="rtg-fchip-check"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
            <span class="rtg-fswitch" aria-hidden="true"></span>
          </button>
          <button type="button" class="info-tooltip-trigger rtg-fchip-info" data-tooltip-key="OEM Filter" aria-label="More info about OEM">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          </button>
          <input type="checkbox" id="filterOEM" class="rtg-fcheckbox" aria-label="OEM" />
        </div>

        <div class="rtg-sheet-foot">
          <button type="button" class="rtg-sheet-clear" data-clear-all>Clear all</button>
          <button type="button" class="rtg-sheet-done" data-sheet-close>Show <span id="rtgSheetCount">0</span> tires</button>
        </div>
      </div>
      <div class="rtg-sheet-backdrop" id="rtgSheetBackdrop" hidden></div>
    </div>

    <div class="sort-wrapper">
      <div class="rtg-results-summary">
        <span id="tireCount" class="tire-count" aria-live="polite">0 tires</span>
        <span id="rtgFilterTally" class="rtg-filter-tally" hidden></span>
        <button type="button" id="rtgClearAll" class="rtg-clear-filters-btn" data-clear-all hidden>
          <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear all
        </button>
      </div>
      <div class="rtg-sort-actions">
        <a class="rtg-write-review-btn" href="<?php echo esc_url( $rtg_write_review_url ); ?>" aria-label="Write a review">
          <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span class="rtg-write-review-label">Write a review</span>
        </a>
        <div class="rtg-sort">
          <i class="fa-solid fa-arrow-down-wide-short" aria-hidden="true"></i>
          <label for="sortBy" class="rtg-sort-label">Sort</label>
          <select id="sortBy" aria-label="Sort tires by">
            <option value="roamer-efficiency" selected>Real-World Efficiency</option>
            <option value="most-reviewed">Most Reviewed</option>
            <option value="newest">Newest Added</option>
            <option value="price-asc">Price: Low → High</option>
            <option value="price-desc">Price: High → Low</option>
            <option value="rating-desc">Rating: High → Low</option>
            <option value="warranty-desc">Warranty: High → Low</option>
            <option value="weight-asc">Weight: Light → Heavy</option>
          </select>
          <i class="fa-solid fa-chevron-down rtg-sort-caret" aria-hidden="true"></i>
        </div>
      </div>
    </div>
    <div id="rtgFilterNotice" class="rtg-filter-notice" role="status" aria-live="polite"></div>
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
    <p class="wheel-drawer-heading">Rivian Wheel Guide</p>
    <?php
    // Build vehicle groups from wheel data: factory wheels first, then the
    // aftermarket setups the guide lists, each under its own heading.
    $rtg_vehicle_groups = array();
    foreach ( $rtg_wheels as $rtg_wheel ) {
      $vehicle_list = array_filter( array_map( 'trim', explode( ',', $rtg_wheel['vehicles'] ) ) );
      $rtg_source   = RTG_Database::is_third_party_wheel( $rtg_wheel ) ? 'third_party' : 'oem';
      foreach ( $vehicle_list as $vehicle ) {
        if ( ! isset( $rtg_vehicle_groups[ $vehicle ] ) ) {
          $rtg_vehicle_groups[ $vehicle ] = array( 'oem' => array(), 'third_party' => array() );
        }
        $rtg_vehicle_groups[ $vehicle ][ $rtg_source ][] = $rtg_wheel;
      }
    }
    $rtg_vehicle_names = array_keys( $rtg_vehicle_groups );
    $rtg_wheel_sections = array(
      'oem'         => 'Factory wheels',
      'third_party' => 'Aftermarket setups',
    );
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
      <?php
      // Only vehicles with both kinds get the section headings; a tab of
      // factory wheels alone reads as it always has.
      $rtg_show_sections = ! empty( $rtg_vehicle_groups[ $vehicle_name ]['third_party'] );
      foreach ( $rtg_wheel_sections as $rtg_section => $rtg_section_label ) :
        $rtg_section_wheels = $rtg_vehicle_groups[ $vehicle_name ][ $rtg_section ];
        if ( empty( $rtg_section_wheels ) ) {
          continue;
        }
        $rtg_is_third = 'third_party' === $rtg_section;
      ?>
      <?php if ( $rtg_show_sections ) : ?>
      <p class="wheel-section-heading<?php echo $rtg_is_third ? ' is-third-party' : ''; ?>"><?php echo esc_html( $rtg_section_label ); ?></p>
      <?php endif; ?>
      <div class="wheel-card-grid">
        <?php foreach ( $rtg_section_wheels as $rtg_wheel ) :
          $alt_list = array_filter( array_map( 'trim', explode( ',', $rtg_wheel['alt_sizes'] ) ) );
        ?>
        <div class="wheel-card<?php echo $rtg_is_third ? ' is-third-party' : ''; ?>">
          <?php if ( $rtg_is_third ) : ?>
            <span class="wheel-card-badge">3rd-party</span>
          <?php endif; ?>
          <?php if ( ! empty( $rtg_wheel['image'] ) ) : ?>
            <img class="wheel-card-img" src="<?php echo esc_url( $rtg_wheel['image'] ); ?>" alt="<?php echo esc_attr( $rtg_wheel['name'] ); ?>" />
          <?php else : ?>
            <?php // No photo: a wheel glyph keeps the card the height of its neighbors without implying a product. ?>
            <div class="wheel-card-img wheel-card-img-placeholder" aria-hidden="true">
              <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <circle cx="32" cy="32" r="28" />
                <circle cx="32" cy="32" r="19" />
                <circle cx="32" cy="32" r="5" />
                <path d="M32 13v14M32 37v14M13 32h14M37 32h14M18.6 18.6l9.9 9.9M35.5 35.5l9.9 9.9M45.4 18.6l-9.9 9.9M28.5 35.5l-9.9 9.9" />
              </svg>
            </div>
          <?php endif; ?>
          <div class="wheel-card-body">
            <strong class="wheel-card-name"><?php echo esc_html( $rtg_wheel['name'] ); ?></strong>
            <?php if ( $rtg_is_third ) : ?>
            <div class="wheel-card-sizes">
              <span class="wheel-card-label">Fits</span>
              <code><?php echo esc_html( $rtg_wheel['stock_size'] ); ?></code>
              <?php foreach ( $alt_list as $alt ) : ?>
                <code><?php echo esc_html( $alt ); ?></code>
              <?php endforeach; ?>
            </div>
            <p class="wheel-card-note">Not offered by Rivian.<?php echo ! empty( $rtg_wheel['fitment_note'] ) ? ' ' . esc_html( $rtg_wheel['fitment_note'] ) : ''; ?> Fitment may vary.</p>
            <?php else : ?>
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
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<div id="tireSection">
  <div id="tireCards"></div>
</div>
<div id="noResults" role="status" aria-live="polite" style="display: none;">
  No tires match your current filters.<br />Try adjusting the filters to see more options.
</div>
<div id="paginationControls"></div>
<div class="rtg-guide-footer">
  <span class="rtg-guide-footer-version">Tire Guide <?php echo esc_html( RTG_VERSION ); ?> &middot;
    <button id="rtgWhatsNew" class="rtg-whats-new-btn rtg-whats-new-link" type="button" aria-label="Changelog"><span class="rtg-whats-new-label">Changelog</span><span class="rtg-whats-new-dot" hidden></span></button>
  </span>
  <span class="rtg-guide-footer-credit">Real-world efficiency from <a href="https://rivianroamer.com/join?with=riviantrackr" target="_blank" rel="noopener noreferrer">Rivian Roamer</a> owners</span>
</div>
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
