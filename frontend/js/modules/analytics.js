/* jshint esversion: 11 */

/**
 * Analytics tracking module.
 */

// --- Analytics Tracking ---
export const RTG_ANALYTICS = {
  nonce: null,
  ajaxurl: null,

  init() {
    if (typeof rtgData !== 'undefined' && rtgData.settings) {
      this.nonce = rtgData.settings.analyticsNonce || null;
      this.ajaxurl = rtgData.settings.ajaxurl || null;
    }
  },

  /**
   * Send a tracking beacon. Uses sendBeacon primary, fetch+keepalive fallback.
   */
  track(action, data) {
    if (!this.ajaxurl || !this.nonce) return;

    const payload = new FormData();
    payload.append('action', action);
    payload.append('nonce', this.nonce);
    Object.entries(data).forEach(([k, v]) => payload.append(k, v));

    if (navigator.sendBeacon) {
      const sent = navigator.sendBeacon(this.ajaxurl, payload);
      if (sent) return;
    }

    try {
      fetch(this.ajaxurl, {
        method: 'POST',
        body: payload,
        keepalive: true,
      }).catch(() => {});
    } catch (e) {
      // Analytics should never break the page.
    }
  },

  trackClick(tireId, linkType) {
    this.track('rtg_track_click', {
      tire_id: tireId,
      link_type: linkType,
    });
  },

  _searchDebounceTimer: null,
  trackSearch(query, filters, sortBy, resultCount) {
    clearTimeout(this._searchDebounceTimer);
    this._searchDebounceTimer = setTimeout(() => {
      this.track('rtg_track_search', {
        search_query: query,
        filters_json: JSON.stringify(filters),
        sort_by: sortBy,
        result_count: String(resultCount),
      });
    }, 2000);
  },
};
