/* jshint esversion: 11 */

/**
 * Tooltip system — info tooltips and modal tooltips.
 */

import { state } from './state.js';
import { rtgColor, rtgIcon } from './helpers.js';

export const TOOLTIP_DATA = {
  'Load Index': {
    title: 'Load Index',
    content: 'Rivian vehicles require tires with a minimum load index of 116 to safely carry the vehicle\'s weight. Using a lower load index can affect safety, handling, and durability.'
  },
  '3PMS Rated': {
    title: '3PMS Rating',
    content: '3PMS (Three-Peak Mountain Snowflake) symbol indicates the tire meets winter traction requirements and is rated for severe snow service according to industry standards.'
  },
  'UTQG': {
    title: 'UTQG Rating',
    content: 'UTQG (Uniform Tire Quality Grading) provides standardized ratings for treadwear, temperature resistance (A, B, C), and traction performance (AA, A, B, C) to help compare tire quality.'
  },
  '3PMS Filter': {
    title: '3PMS Rating Filter',
    content: '3PMS (Three-Peak Mountain Snowflake) means the tire meets winter traction requirements and is rated for severe snow service.'
  },
  'EV Rated Filter': {
    title: 'EV Rated Filter',
    content: 'Filters for tires labeled as EV Rated in their specs or marketing. These are typically optimized for electric vehicles.'
  },
  'Studded Available Filter': {
    title: 'Studded Available Filter',
    content: 'Filters for tires marked as "Studded Available" — these can be fitted with studs for enhanced traction on ice.'
  },
  'Officially Reviewed Filter': {
    title: 'Officially Reviewed Filter',
    content: 'Filters for tires that have an official review from RivianTrackr — either a written article or video review.'
  },
  'Efficiency Score': {
    title: 'Efficiency Score',
    content: 'This Efficiency Score is a calculated score RivianTrackr created to help assist Rivian owners in identifying which tires are likely to be the most range-friendly. It uses a custom formula that factors in weight, tread depth, tire width, load range, speed rating, UTQG, tire category, and winter certification (3PMS). Lighter tires with shallower tread and less aggressive construction typically score higher. <br><br> The score is an estimate only and does not reflect real-world testing. It should not be viewed as a measure of tire quality, safety, or brand reputation.'
  }
};

export function createInfoTooltip(label, tooltipKey) {
  const container = document.createElement('div');
  container.style.cssText = `display: flex; align-items: center; gap: 6px;`;

  const labelText = document.createElement('span');
  labelText.className = 'tire-card-spec-label';
  labelText.textContent = label;

  const infoButton = document.createElement('button');
  infoButton.innerHTML = '' + rtgIcon('circle-info', 14) + '';
  infoButton.className = 'info-tooltip-trigger';
  infoButton.dataset.tooltipKey = tooltipKey;
  infoButton.setAttribute('aria-label', `More info about ${label}`);
  infoButton.setAttribute('type', 'button');
  infoButton.style.cssText = `
    background: none;
    border: none;
    color: var(--rtg-text-muted);
    font-size: 14px;
    cursor: pointer;
    padding: 2px;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
  `;

  infoButton.addEventListener('mouseenter', () => {
    infoButton.style.color = rtgColor('accent');
    infoButton.style.backgroundColor = `color-mix(in srgb, ${rtgColor('accent')} 10%, transparent)`;
  });

  infoButton.addEventListener('mouseleave', () => {
    infoButton.style.color = rtgColor('text-muted');
    infoButton.style.backgroundColor = 'transparent';
  });

  container.appendChild(labelText);
  container.appendChild(infoButton);

  return container;
}

export function createFilterTooltip(labelText, tooltipKey) {
  const container = document.createElement('div');
  container.style.cssText = `display: flex; align-items: center; gap: 6px;`;

  const label = document.createElement('span');
  label.textContent = labelText;

  const infoButton = document.createElement('button');
  infoButton.innerHTML = '' + rtgIcon('circle-info', 14) + '';
  infoButton.className = 'info-tooltip-trigger';
  infoButton.dataset.tooltipKey = tooltipKey;
  infoButton.setAttribute('aria-label', `More info about ${labelText}`);
  infoButton.setAttribute('type', 'button');
  infoButton.style.cssText = `
    background: none;
    border: none;
    color: var(--rtg-text-muted);
    font-size: 14px;
    cursor: pointer;
    padding: 2px;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
  `;

  infoButton.addEventListener('mouseenter', () => {
    infoButton.style.color = rtgColor('accent');
    infoButton.style.backgroundColor = `color-mix(in srgb, ${rtgColor('accent')} 10%, transparent)`;
  });

  infoButton.addEventListener('mouseleave', () => {
    infoButton.style.color = rtgColor('text-muted');
    infoButton.style.backgroundColor = 'transparent';
  });

  container.appendChild(label);
  container.appendChild(infoButton);

  return container;
}

export function showTooltipModal(tooltipKey) {
  closeTooltipModal();

  const tooltipData = TOOLTIP_DATA[tooltipKey];
  if (!tooltipData) return;

  const overlay = document.createElement('div');
  overlay.className = 'tooltip-modal-overlay';
  overlay.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.2s ease;
    backdrop-filter: blur(2px);
  `;

  const modal = document.createElement('div');
  modal.className = 'tooltip-modal';
  modal.style.cssText = `
    background: ${rtgColor('bg-primary')};
    border-radius: 12px;
    padding: 20px;
    max-width: 400px;
    width: 100%;
    color: ${rtgColor('text-light')};
    border: 1px solid #475569;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    animation: slideUp 0.2s ease;
    position: relative;
  `;

  const title = document.createElement('h3');
  title.textContent = tooltipData.title;
  title.style.cssText = `
    margin: 0 0 12px 0;
    font-size: 18px;
    font-weight: 700;
    color: ${rtgColor('accent')};
  `;

  const content = document.createElement('p');
  content.innerHTML = tooltipData.content;
  content.style.cssText = `
    margin: 0 0 16px 0;
    line-height: 1.5;
    font-size: 14px;
    color: #e2e8f0;
  `;

  const closeButton = document.createElement('button');
  closeButton.innerHTML = rtgIcon('xmark', 18);
  closeButton.style.cssText = `
    position: absolute;
    top: 16px;
    right: 16px;
    background: none;
    border: none;
    color: var(--rtg-text-muted);
    font-size: 16px;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
  `;

  closeButton.addEventListener('mouseenter', () => {
    closeButton.style.color = rtgColor('text-light');
    closeButton.style.backgroundColor = 'rgba(148, 163, 184, 0.2)';
  });

  closeButton.addEventListener('mouseleave', () => {
    closeButton.style.color = rtgColor('text-muted');
    closeButton.style.backgroundColor = 'transparent';
  });

  const gotItButton = document.createElement('button');
  gotItButton.textContent = 'Got it';
  gotItButton.style.cssText = `
    background: ${rtgColor('accent')};
    color: #1a1a1a;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s ease;
    width: 100%;
  `;

  gotItButton.addEventListener('mouseenter', () => {
    gotItButton.style.backgroundColor = rtgColor('accent-hover');
  });

  gotItButton.addEventListener('mouseleave', () => {
    gotItButton.style.backgroundColor = rtgColor('accent');
  });

  const closeModal = () => closeTooltipModal();
  closeButton.addEventListener('click', closeModal);
  gotItButton.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });

  const handleEscape = (e) => {
    if (e.key === 'Escape') {
      closeModal();
      document.removeEventListener('keydown', handleEscape);
    }
  };
  document.addEventListener('keydown', handleEscape);

  modal.appendChild(title);
  modal.appendChild(content);
  modal.appendChild(closeButton);
  modal.appendChild(gotItButton);
  overlay.appendChild(modal);

  document.body.appendChild(overlay);
  state.activeTooltip = overlay;

  document.body.style.overflow = 'hidden';

  gotItButton.focus();
}

export function closeTooltipModal() {
  if (state.activeTooltip) {
    state.activeTooltip.style.animation = 'fadeOut 0.2s ease';
    setTimeout(() => {
      if (state.activeTooltip && state.activeTooltip.parentNode) {
        state.activeTooltip.parentNode.removeChild(state.activeTooltip);
      }
      state.activeTooltip = null;
      document.body.style.overflow = '';
    }, 200);
  }
}
