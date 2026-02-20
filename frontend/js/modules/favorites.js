/* jshint esversion: 11 */

/**
 * Favorites system — save, load, toggle, and display user favorites.
 */

import { state } from './state.js';
import { rtgIcon } from './helpers.js';
import { VALIDATION_PATTERNS } from './validation.js';

export function loadFavorites() {
  if (!state.isLoggedIn || typeof tireRatingAjax === 'undefined') return Promise.resolve();

  const body = new FormData();
  body.append('action', 'rtg_get_favorites');
  body.append('nonce', tireRatingAjax.nonce);

  return fetch(tireRatingAjax.ajaxurl, { method: 'POST', body })
    .then(res => res.json())
    .then(json => {
      if (json.success && Array.isArray(json.data.favorites)) {
        state.userFavorites = new Set(json.data.favorites);
        updateFavoriteButtons();
        updateFavoritesFilterCount();
      }
    })
    .catch(err => console.error('Failed to load favorites:', err));
}

export function toggleFavorite(tireId) {
  if (!state.isLoggedIn) {
    if (typeof tireRatingAjax !== 'undefined' && tireRatingAjax.login_url) {
      window.location.href = tireRatingAjax.login_url;
    }
    return;
  }

  if (!VALIDATION_PATTERNS.tireId.test(tireId)) return;

  const isFav = state.userFavorites.has(tireId);
  const action = isFav ? 'rtg_remove_favorite' : 'rtg_add_favorite';

  // Optimistic update
  if (isFav) {
    state.userFavorites.delete(tireId);
  } else {
    state.userFavorites.add(tireId);
  }
  updateFavoriteButton(tireId);
  updateFavoritesFilterCount();

  const body = new FormData();
  body.append('action', action);
  body.append('tire_id', tireId);
  body.append('nonce', tireRatingAjax.nonce);

  fetch(tireRatingAjax.ajaxurl, { method: 'POST', body })
    .then(res => res.json())
    .then(json => {
      if (!json.success) {
        // Revert optimistic update
        if (isFav) {
          state.userFavorites.add(tireId);
        } else {
          state.userFavorites.delete(tireId);
        }
        updateFavoriteButton(tireId);
        updateFavoritesFilterCount();
      }
    })
    .catch(() => {
      // Revert on network error
      if (isFav) {
        state.userFavorites.add(tireId);
      } else {
        state.userFavorites.delete(tireId);
      }
      updateFavoriteButton(tireId);
      updateFavoritesFilterCount();
    });
}

export function updateFavoriteButton(tireId) {
  const btns = document.querySelectorAll(`.tire-card-fav-btn[data-tire-id="${CSS.escape(tireId)}"]`);
  btns.forEach(btn => {
    const isFav = state.userFavorites.has(tireId);
    btn.classList.toggle('is-favorite', isFav);
    btn.setAttribute('aria-label', isFav ? 'Remove from favorites' : 'Add to favorites');
    btn.innerHTML = isFav
      ? rtgIcon('heart', 16)
      : rtgIcon('heart-outline', 16);
  });
}

export function updateFavoriteButtons() {
  document.querySelectorAll('.tire-card-fav-btn').forEach(btn => {
    const tireId = btn.dataset.tireId;
    if (tireId) updateFavoriteButton(tireId);
  });
}

export function updateFavoritesFilterCount() {
  const badge = document.getElementById('favoritesCount');
  if (badge) {
    badge.textContent = state.userFavorites.size > 0 ? state.userFavorites.size : '';
    badge.style.display = state.userFavorites.size > 0 ? 'inline-flex' : 'none';
  }
}
