/* jshint esversion: 11 */

/**
 * Shared mutable state for the Rivian Tire Guide.
 *
 * Every module that needs access to global state imports from here.
 * Values are mutated in-place so all consumers see the same data.
 */

export const ROWS_PER_PAGE = (typeof rtgData !== 'undefined' && rtgData.settings && rtgData.settings.rowsPerPage) ? rtgData.settings.rowsPerPage : 12;

export const state = {
  filteredRows: [],
  allRows: [],
  currentPage: 1,
  compareList: [],

  // Ratings
  tireRatings: {},
  userRatings: {},
  userReviews: {},
  isLoggedIn: false,

  // Favorites
  userFavorites: new Set(),

  // Valid filter values (populated after data loads)
  VALID_SIZES: [],
  VALID_BRANDS: [],
  VALID_CATEGORIES: [],

  // DOM cache
  domCache: {},

  // Card rendering
  cardCache: new Map(),
  cardContainer: null,

  // Performance flags
  isRendering: false,
  pendingRender: false,
  lastFilterState: null,
  initialRenderDone: false,
  renderAnimationFrame: null,

  // Event delegation
  eventDelegationSetup: false,

  // Rating batching
  ratingRequestQueue: [],
  ratingRequestTimeout: null,

  // Tooltip
  activeTooltip: null,

  // Server-side pagination
  serverSideMode: false,
  serverSideTotal: 0,
  serverSideFetchController: null,
};

// Pre-computed filter indexes for ultra-fast filtering
export const filterIndexes = {
  sizeIndex: new Map(),
  brandIndex: new Map(),
  categoryIndex: new Map(),
  priceIndex: [],
  warrantyIndex: [],
  weightIndex: [],
};
