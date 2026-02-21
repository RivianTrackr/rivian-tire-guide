/* jshint esversion: 11 */

/**
 * AI Tire Recommendation module.
 *
 * Handles the AI search bar interaction, AJAX calls to the backend,
 * and rendering of AI-recommended tires.
 *
 * In unified mode the shared #searchInput field is used for both local
 * filtering and AI queries.  The AI submit button (#rtgAiSubmit) and the
 * "Ask AI" suggestion in the search dropdown trigger AI explicitly.
 */

import { state } from './state.js';
import { getDOMElement, debounce, escapeHTML, safeString, rtgIcon } from './helpers.js';
import { filterAndRender } from './filters.js';
import { isServerSide, serverSideFilterAndRender } from './server.js';
import { RTG_ANALYTICS } from './analytics.js';
import { loadTireRatings } from './ratings.js';

let aiActive = false;
let lastQuery = '';

/**
 * Turn tire brand/model names in the AI summary into clickable links.
 * Clicking a link scrolls to and highlights the corresponding tire card.
 *
 * @param {string} escapedSummary HTML-escaped summary text.
 * @param {Array}  orderedRows    Tire rows returned by AI (same order as cards).
 * @returns {string} Summary HTML with tire names wrapped in links.
 */
function linkifyTireNames(escapedSummary, orderedRows) {
  // Build tire info list sorted by name length descending (match longer names first).
  const tires = orderedRows
    .map(row => ({
      id: row[0],
      brand: safeString(row[3]).trim(),
      model: safeString(row[4]).trim(),
    }))
    .filter(t => t.model)
    .sort((a, b) => (b.brand + b.model).length - (a.brand + a.model).length);

  // Find match positions in the text.
  const matches = [];
  const linked = new Set();

  for (const tire of tires) {
    if (linked.has(tire.id)) continue;

    const fullName = tire.brand + ' ' + tire.model;
    const textLower = escapedSummary.toLowerCase();

    // Try brand+model first, then model only.
    let idx = textLower.indexOf(fullName.toLowerCase());
    let matchLen = fullName.length;

    if (idx === -1) {
      idx = textLower.indexOf(tire.model.toLowerCase());
      matchLen = tire.model.length;
    }

    if (idx === -1) continue;

    matches.push({ idx, len: matchLen, tireId: tire.id });
    linked.add(tire.id);
  }

  if (!matches.length) return escapedSummary;

  // Replace right-to-left so earlier positions stay valid.
  matches.sort((a, b) => b.idx - a.idx);

  let result = escapedSummary;
  for (const m of matches) {
    const original = result.substring(m.idx, m.idx + m.len);
    const link = '<a href="#" class="rtg-ai-tire-link" data-tire-id="' + escapeHTML(m.tireId) + '">' + original + '</a>';
    result = result.substring(0, m.idx) + link + result.substring(m.idx + m.len);
  }

  return result;
}

/**
 * Scroll to a tire card and briefly highlight it.
 *
 * @param {string} tireId The tire ID to scroll to.
 */
function scrollToTireCard(tireId) {
  const card = document.querySelector('.tire-card[data-tire-id="' + CSS.escape(tireId) + '"]');
  if (!card) return;

  card.scrollIntoView({ behavior: 'smooth', block: 'center' });
  card.classList.remove('rtg-ai-highlight');
  // Force reflow to restart animation if already applied.
  void card.offsetWidth;
  card.classList.add('rtg-ai-highlight');
  setTimeout(() => card.classList.remove('rtg-ai-highlight'), 2500);
}

/**
 * Initialize the AI recommendation UI if enabled.
 */
export function initAiRecommend() {
  if (typeof rtgData === 'undefined' || !rtgData.settings || !rtgData.settings.aiEnabled) {
    return;
  }

  const submitBtn = getDOMElement('rtgAiSubmit');
  if (!submitBtn) return;

  // Submit on button click.
  submitBtn.addEventListener('click', handleAiSubmit);

  // When the user starts typing while AI results are displayed, auto-clear
  // the AI state so the local search takes over immediately.
  const input = getDOMElement('searchInput');
  if (input) {
    input.addEventListener('input', () => {
      if (aiActive) {
        clearAiRecommendations(true);
      }
    });
  }
}

/**
 * Handle AI search form submission.
 */
function handleAiSubmit() {
  const input = getDOMElement('searchInput');
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
export function submitAiQuery(query) {
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

      const { recommendations, summary, tire_rows } = result.data;

      if (!recommendations || recommendations.length === 0) {
        showAiError(summary || 'No matching tires found. Try a different query.');
        return;
      }

      // Track the AI query.
      RTG_ANALYTICS.trackAiSearch(query, recommendations.length);

      // Apply AI recommendations using the full tire row data from the server.
      applyAiRecommendations(recommendations, summary, tire_rows || []);
    })
    .catch(() => {
      if (submitBtn) submitBtn.disabled = false;
      showAiError('Unable to reach the AI service. Please check your connection and try again.');
    });
}

/**
 * Display an error/info message in the AI status area.
 * When a query was provided, includes a link to search RivianTrackr.
 *
 * @param {string} message Error message.
 */
function showAiError(message) {
  const statusEl = getDOMElement('rtgAiStatus');
  if (statusEl) {
    let html = '';

    // If a query was submitted, skip the error text and show the RivianTrackr redirect instead.
    if (lastQuery) {
      html =
        '<div class="rtg-ai-riviantrackr">' +
          '<span>Not looking for tires?</span> ' +
          '<a href="https://riviantrackr.com/?s=' + encodeURIComponent(lastQuery) + '" target="_blank" rel="noopener noreferrer">' +
            rtgIcon('magnifying-glass', 13) + ' Search RivianTrackr for "' + escapeHTML(safeString(lastQuery, 30)) + '"' +
            ' ' + rtgIcon('arrow-up-right', 11) +
          '</a>' +
        '</div>';
    } else {
      html = '<div class="rtg-ai-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> ' + escapeHTML(message) + '</div>';
    }

    statusEl.style.display = 'block';
    statusEl.innerHTML = html;
  }
}

/**
 * Apply AI recommendations: reorder and filter the tire grid.
 *
 * @param {Array}  recommendations Array of {tire_id, reason}.
 * @param {string} summary AI summary text.
 * @param {Array}  tireRows Full tire row data from the server for recommended tires.
 */
function applyAiRecommendations(recommendations, summary, tireRows) {
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

  // Build a lookup from the server-provided tire rows so we can find tires
  // regardless of whether they are already in state.allRows.
  const serverRowMap = new Map();
  tireRows.forEach(row => {
    if (row && row[0]) {
      serverRowMap.set(row[0], row);
    }
  });

  // Build ordered rows: prefer state.allRows (already loaded), fall back to
  // server-provided tire rows for tires not yet loaded (server-side mode).
  const orderedRows = [];
  recOrder.forEach(tireId => {
    const localRow = state.allRows.find(r => r[0] === tireId);
    if (localRow) {
      orderedRows.push(localRow);
    } else if (serverRowMap.has(tireId)) {
      orderedRows.push(serverRowMap.get(tireId));
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

  // Show summary with clear button. Tire names become clickable links.
  if (summaryEl) {
    const linkedSummary = escapeHTML(summary);

    // Build clickable tire chips that scroll to the corresponding card below.
    let tireChipsHtml = '<div class="rtg-ai-tire-chips">';
    orderedRows.forEach(row => {
      const tireId = row[0];
      const brand = safeString(row[3]).trim();
      const model = safeString(row[4]).trim();
      if (!model) return;
      tireChipsHtml +=
        '<a href="#" class="rtg-ai-tire-link rtg-ai-tire-chip" data-tire-id="' + escapeHTML(tireId) + '">' +
          escapeHTML(brand + ' ' + model) +
        '</a>';
    });
    tireChipsHtml += '</div>';

    summaryEl.style.display = 'block';
    summaryEl.innerHTML =
      '<div class="rtg-ai-summary-content">' +
        '<div class="rtg-ai-summary-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></div>' +
        '<div class="rtg-ai-summary-text">' +
          '<strong>AI Recommendation</strong>' +
          '<p>' + linkedSummary + '</p>' +
          tireChipsHtml +
        '</div>' +
        '<button id="rtgAiClear" class="rtg-ai-clear" type="button" aria-label="Clear AI recommendations">' +
          '<i class="fa-solid fa-xmark" aria-hidden="true"></i> Clear' +
        '</button>' +
      '</div>';

    // Attach clear button handler.
    const clearBtn = document.getElementById('rtgAiClear');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => clearAiRecommendations());
    }

    // Attach tire link click handlers — all links scroll to the card below.
    summaryEl.addEventListener('click', (e) => {
      const link = e.target.closest('.rtg-ai-tire-link');
      if (!link) return;

      e.preventDefault();
      scrollToTireCard(link.dataset.tireId);
    });
  }

  // Re-render cards with AI results.
  renderAiResults();
}

/**
 * Render the AI-filtered tire cards and update the count.
 */
function renderAiResults() {
  // Load ratings for the AI-recommended tires, then render cards.
  const tireIds = state.filteredRows.map(row => row[0]).filter(Boolean);

  // Import renderCards dynamically to avoid circular dependency.
  import('./cards.js').then(({ renderCards }) => loadTireRatings(tireIds).then(() => {
    renderCards(state.filteredRows);

    // Update tire count.
    const countDisplay = getDOMElement('tireCount');
    if (countDisplay) {
      countDisplay.textContent = `AI found ${state.filteredRows.length} recommended tire${state.filteredRows.length !== 1 ? 's' : ''}`;
    }

    // Hide no-results if we have results.
    const noResults = getDOMElement('noResults');
    const tireCards = getDOMElement('tireCards');
    if (state.filteredRows.length > 0) {
      if (noResults) noResults.style.display = 'none';
      if (tireCards) tireCards.style.display = 'grid';
    } else {
      if (noResults) noResults.style.display = '';
      if (tireCards) tireCards.style.display = 'none';
    }

    // Hide normal pagination since AI results show all at once.
    const paginationControls = getDOMElement('paginationControls');
    if (paginationControls) paginationControls.innerHTML = '';
  }));
}

/**
 * Clear AI recommendations and restore the normal tire view.
 *
 * @param {boolean} [keepInput=false] When true the input value is preserved
 *   (used when auto-clearing as the user types a new query).
 */
export function clearAiRecommendations(keepInput) {
  aiActive = false;
  lastQuery = '';
  state.aiRecommendations = null;

  const summaryEl = getDOMElement('rtgAiSummary');
  const statusEl = getDOMElement('rtgAiStatus');
  const input = getDOMElement('searchInput');

  if (summaryEl) {
    summaryEl.style.display = 'none';
    summaryEl.innerHTML = '';
  }
  if (statusEl) {
    statusEl.style.display = 'none';
    statusEl.innerHTML = '';
  }
  if (input && !keepInput) {
    input.value = '';
  }

  // Restore normal filtering — use the appropriate mode.
  if (isServerSide()) {
    serverSideFilterAndRender();
  } else {
    filterAndRender();
  }
}

/**
 * Check if AI mode is currently active.
 *
 * @returns {boolean}
 */
export function isAiActive() {
  return aiActive;
}
