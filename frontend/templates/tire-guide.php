<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<button id="toggleFilters" class="toggle-filters-btn">Show Filters</button>
<div id="filterTop"></div>
<div class="filter-wrapper">
  <div class="filter-header">Filter, Sort, and Compare</div>
  <div class="filter-search">
    <div class="search-container">
      <input id="searchInput" type="text" placeholder="Search" class="search-input"/>
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
    </div>
  </div>
  <div id="mobileFilterContent" class="mobile-filter-content">
    <div class="filter-container">
      <div class="filter-group">
        <select id="filterSize">
          <option value="">All Sizes</option>
        </select>
      </div>
      <div class="filter-group">
        <select id="filterBrand">
          <option value="">All Brands</option>
        </select>
      </div>
      <div class="filter-group">
        <select id="filterCategory">
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
              <i class="fa-solid fa-circle-info"></i>
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
              <i class="fa-solid fa-circle-info"></i>
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
              <i class="fa-solid fa-circle-info"></i>
            </button>
          </div>
        </span>
        <input type="checkbox" id="filterStudded" aria-label="Studded Available"/>
        <span class="switch-slider" onclick="document.getElementById('filterStudded').click()"></span>
      </div>
      <label class="switch-label reset-white" onclick="resetFilters()" role="button" tabindex="0">
        <span class="switch-text">
          <i class="fa-solid fa-rotate-left" style="margin-right: 6px;"></i>Clear All
        </span>
      </label>
    </div>
    <div id="wheelDrawerContainer">
      <button id="wheelDrawerTrigger" class="wheel-trigger">
        <i class="fa-solid fa-circle-info"></i>
        Not sure which Rivian tire you need?
      </button>
      <div id="wheelDrawer" class="wheel-drawer">
        <p class="wheel-drawer-heading">Rivian Stock Wheel Guide</p>
        <div class="wheel-items">
          <div class="wheel-item">
            <img src="https://riviantrackr.com/assets/tire-guide/images/stock/20_All-Terrain.jpg" alt="20-inch All-Terrain" />
            <div>
              <strong>20" All-Terrain / Dark</strong><br />Stock: <code>275/65R20</code><br />Alt: <code>275/60R20</code>
            </div>
          </div>
          <div class="wheel-item">
            <img src="https://riviantrackr.com/assets/tire-guide/images/stock/20_All-Season.jpg" alt="20-inch All Season" />
            <div>
              <strong>20" All-Season</strong><br />Stock: <code>275/60R20</code><br />Alt: <code>275/65R20</code>
            </div>
          </div>
          <div class="wheel-item">
            <img src="https://riviantrackr.com/assets/tire-guide/images/stock/21_Aero.jpg" alt="21-inch Aero" />
            <div>
              <strong>21" Aero</strong><br />Stock: <code>275/55R21</code>
            </div>
          </div>
          <div class="wheel-item">
            <img src="https://riviantrackr.com/assets/tire-guide/images/stock/22_Range.jpg" alt="22-inch Range" />
            <div>
              <strong>22" Range</strong><br />Stock: <code>275/50R22</code><br />Alt: <code>285/50R22</code> <code>305/45R22</code>
            </div>
          </div>
          <div class="wheel-item">
            <img src="https://riviantrackr.com/assets/tire-guide/images/stock/22_Sport-Bright.jpg" alt="22-inch Sport" />
            <div>
              <strong>22" Sport / Dark</strong><br />Stock: <code>275/50R22</code><br />Alt: <code>285/50R22</code> <code>305/45R22</code>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="sort-wrapper">
  <span id="tireCount" class="tire-count">Showing 0 tires</span>
  <div style="flex: 1;"></div>
  <select id="sortBy" onchange="filterAndRender()">
    <option value="alpha">Brand: A → Z</option>
    <option value="alpha-desc">Brand: Z → A</option>
    <option value="efficiencyGrade">Efficiency Grade</option>
    <option value="price-asc">Price: Low → High</option>
    <option value="price-desc">Price: High → Low</option>
    <option value="rating-desc" selected>Rating: High → Low</option>
    <option value="warranty-desc">Warranty: High → Low</option>
    <option value="weight-asc">Weight: Light → Heavy</option>
    <option value="weight-desc">Weight: Heavy → Light</option>
  </select>
</div>
<div id="tireSection">
  <div id="tireCards"></div>
</div>
<div id="noResults" style="display: none; text-align: center; padding: 40px 20px; color: #9ca3af; font-size: 18px;">
  No tires match your current filters.<br />Try adjusting the filters to see more options.
</div>
<div id="paginationControls" style="display: flex; justify-content: center; gap: 12px; margin-top: 20px;"></div>
<div id="compareBar" class="compare-bar">
  <span id="compareCount" class="compare-count"></span>
  <button onclick="openComparison()" style="background-color: var(--rtg-accent, #5ec095); color: #000; padding: 12px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; flex: 1; min-width: 120px;">Compare</button>
  <button onclick="clearCompare()" style="background-color: #ee383a; color: #fff; padding: 12px 16px; font-size: 16px; border: none; border-radius: 8px; cursor: pointer; flex: 1; min-width: 100px;">Clear</button>
</div>
<div id="imageModal">
  <div class="modal-content">
    <img id="modalImage" src="" alt="Full Size" />
  </div>
</div>
