/* jshint esversion: 11 */

/**
 * AI Tire Recommendation module.
 *
 * Handles the AI search bar interaction, AJAX calls to the backend,
 * and rendering of AI-recommended tires.
 */

import { state } from './state.js';
import { getDOMElement, debounce, escapeHTML } from './helpers.js';
import { filterAndRender } from './filters.js';

let aiActive = false;
let lastQuery = '';

/**
 * Initialize the AI recommendation UI if enabled.
 */
export function initAiRecommend() {
  if (typeof rtgData === 'undefined' || !rtgData.settings || !rtgData.settings.aiEnabled) {
    return;
  }

  const input = getDOMElement('rtgAiInput');
  const submitBtn = getDOMElement('rtgAiSubmit');

  if (!input || !submitBtn) return;

  // Submit on button click.
  submitBtn.addEventListener('click', handleAiSubmit);

  // Submit on Enter key.
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      handleAiSubmit();
    }
  });
}

/**
 * Handle AI search form submission.
 */
function handleAiSubmit() {
  const input = getDOMElement('rtgAiInput');
  if (!input) return;

  const query = input.value.trim();
  if (!query) return;

  // Don't resubmit the same query.
  if (query === lastQuery && aiActive) return;

  lastQuery = query;
  submitAiQuery(query);
}

/**
 * Submit a query to the AI recommendation endpoint.
 *
 * @param {string} query User's natural language query.
 */
function submitAiQuery(query) {
  const statusEl = getDOMElement('rtgAiStatus');
  const summaryEl = getDOMElement('rtgAiSummary');
  const submitBtn = getDOMElement('rtgAiSubmit');

  // Show loading state.
  if (statusEl) {
    statusEl.style.display = 'block';
    statusEl.innerHTML = '<div class="rtg-ai-loading"><div class="rtg-ai-spinner"></div><span>Analyzing your needs and finding the best tires...</span></div>';
  }
  if (summaryEl) {
    summaryEl.style.display = 'none';
    summaryEl.innerHTML = '';
  }
  if (submitBtn) {
    submitBtn.disabled = true;
  }

  const formData = new FormData();
  formData.append('action', 'rtg_ai_recommend');
  formData.append('nonce', rtgData.settings.aiNonce);
  formData.append('query', query);

  fetch(rtgData.settings.ajaxurl, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  })
    .then(response => response.json())
    .then(result => {
      if (submitBtn) submitBtn.disabled = false;

      if (!result.success) {
        showAiError(result.data || 'Something went wrong. Please try again.');
        return;
      }

      const { recommendations, summary } = result.data;

      if (!recommendations || recommendations.length === 0) {
        showAiError(summary || 'No matching tires found. Try a different query.');
        return;
      }

      // Apply AI recommendations: filter allRows to show only recommended tires.
      applyAiRecommendations(recommendations, summary);
    })
    .catch(() => {
      if (submitBtn) submitBtn.disabled = false;
      showAiError('Unable to reach the AI service. Please check your connection and try again.');
    });
}

/**
 * Display an error/info message in the AI status area.
 *
 * @param {string} message Error message.
 */
function showAiError(message) {
  const statusEl = getDOMElement('rtgAiStatus');
  if (statusEl) {
    statusEl.style.display = 'block';
    statusEl.innerHTML = '<div class="rtg-ai-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> ' + escapeHTML(message) + '</div>';
  }
}

/**
 * Apply AI recommendations: reorder and filter the tire grid.
 *
 * @param {Array} recommendations Array of {tire_id, reason}.
 * @param {string} summary AI summary text.
 */
function applyAiRecommendations(recommendations, summary) {
  const statusEl = getDOMElement('rtgAiStatus');
  const summaryEl = getDOMElement('rtgAiSummary');

  // Hide loading.
  if (statusEl) {
    statusEl.style.display = 'none';
    statusEl.innerHTML = '';
  }

  // Build a map of tire_id -> reason for quick lookup.
  const recMap = new Map();
  const recOrder = [];
  recommendations.forEach((rec, index) => {
    recMap.set(rec.tire_id, { reason: rec.reason, rank: index + 1 });
    recOrder.push(rec.tire_id);
  });

  // Filter state.allRows to only include recommended tires, in AI order.
  const orderedRows = [];
  recOrder.forEach(tireId => {
    const row = state.allRows.find(r => r[0] === tireId);
    if (row) {
      orderedRows.push(row);
    }
  });

  if (orderedRows.length === 0) {
    showAiError('The AI recommended tires that could not be found in the current catalog. Please try a different query.');
    return;
  }

  // Store the reasons for card badges.
  state.aiRecommendations = recMap;

  // Replace filteredRows with AI results.
  state.filteredRows = orderedRows;
  state.currentPage = 1;
  aiActive = true;

  // Show summary with clear button.
  if (summaryEl) {
    summaryEl.style.display = 'block';
    summaryEl.innerHTML =
      '<div class="rtg-ai-summary-content">' +
        '<div class="rtg-ai-summary-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></div>' +
        '<div class="rtg-ai-summary-text">' +
          '<strong>AI Recommendation</strong>' +
          '<p>' + escapeHTML(summary) + '</p>' +
        '</div>' +
        '<button id="rtgAiClear" class="rtg-ai-clear" type="button" aria-label="Clear AI recommendations">' +
          '<i class="fa-solid fa-xmark" aria-hidden="true"></i> Clear' +
        '</button>' +
      '</div>';

    // Attach clear button handler.
    const clearBtn = document.getElementById('rtgAiClear');
    if (clearBtn) {
      clearBtn.addEventListener('click', clearAiRecommendations);
    }
  }

  // Re-render cards with AI results.
  renderAiResults();
}

/**
 * Render the AI-filtered tire cards and update the count.
 */
function renderAiResults() {
  // Import renderCards dynamically to avoid circular dependency.
  import('./cards.js').then(({ renderCards }) => {
    renderCards();

    // Update tire count.
    const countDisplay = getDOMElement('tireCount');
    if (countDisplay) {
      countDisplay.textContent = `AI found ${state.filteredRows.length} recommended tire${state.filteredRows.length !== 1 ? 's' : ''}`;
    }

    // Hide no-results if we have results.
    const noResults = getDOMElement('noResults');
    if (noResults) {
      noResults.style.display = state.filteredRows.length > 0 ? 'none' : '';
    }
  });
}

/**
 * Clear AI recommendations and restore the normal tire view.
 */
export function clearAiRecommendations() {
  aiActive = false;
  lastQuery = '';
  state.aiRecommendations = null;

  const summaryEl = getDOMElement('rtgAiSummary');
  const statusEl = getDOMElement('rtgAiStatus');
  const input = getDOMElement('rtgAiInput');

  if (summaryEl) {
    summaryEl.style.display = 'none';
    summaryEl.innerHTML = '';
  }
  if (statusEl) {
    statusEl.style.display = 'none';
    statusEl.innerHTML = '';
  }
  if (input) {
    input.value = '';
  }

  // Restore normal filtering.
  filterAndRender();
}

/**
 * Check if AI mode is currently active.
 *
 * @returns {boolean}
 */
export function isAiActive() {
  return aiActive;
}
