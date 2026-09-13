/* jshint esversion: 11 */

/**
 * Tooltip system — info tooltips and modal tooltips.
 */

import { state } from './state.js';
import { rtgColor, rtgIcon, escapeHTML } from './helpers.js';
import { openDialog, dialogButton } from './dialog.js';

export const TOOLTIP_DATA = {
  'Load Index': {
    title: 'Load Index',
    content: 'Rivian vehicles require tires with a high enough load index to safely carry the vehicle\'s weight. R1 vehicles (R1T, R1S) require a minimum load index of 116, while R2 vehicles require a minimum of 112. Using a lower load index can affect safety, handling, and durability.'
  },
  '3rd-party wheels': {
    title: '3rd-party wheels',
    content: 'Rivian doesn\'t sell this vehicle with wheels in this size. The size fits only on aftermarket wheels, and whether it clears depends on the wheel\'s width and offset, so check with the wheel maker before buying. The load-index rule still applies.'
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
  'OEM Filter': {
    title: 'OEM Tire Filter',
    content: 'Filters for tires that come as Original Equipment from the factory on Rivian vehicles.'
  },
  'Real-World Efficiency': {
    title: 'Real-World Efficiency (mi/kWh)',
    content: 'This is real-world energy efficiency data collected from Rivian owners via <a href="https://rivianroamer.com/join?with=riviantrackr" target="_blank" rel="noopener noreferrer" style="color:#60a5fa;text-decoration:underline;">Rivian Roamer</a>. It measures how many miles the vehicle travels per kilowatt-hour of battery energy while using these tires. <br><br> Higher values mean better range efficiency. The data is based on actual driving sessions and updates regularly.'
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

export function showTooltipModal(tooltipKey, triggerEl) {
  closeTooltipModal();

  const tooltipData = TOOLTIP_DATA[tooltipKey];
  if (!tooltipData) return;

  // Append dynamic extra content from the trigger element (e.g. per-tire Roamer stats).
  let extraContent = '';
  if (triggerEl && triggerEl.dataset && triggerEl.dataset.tooltipExtra) {
    extraContent = '<br><br><strong style="color:#60a5fa;">This tire:</strong> ' + triggerEl.dataset.tooltipExtra;
  }
  // The admin's fitment note for a 3rd-party wheel, under the wheel's name.
  // Both are admin text and land here escaped.
  if (triggerEl && triggerEl.dataset && triggerEl.dataset.tooltipNote) {
    const who = triggerEl.dataset.tooltipWheel || 'This wheel';
    extraContent += '<br><br><strong style="color:#a78bfa;">' + escapeHTML(who) + ':</strong> ' + escapeHTML(triggerEl.dataset.tooltipNote);
  }

  const body = document.createElement('p');
  body.className = 'rtg-dialog-text';
  body.innerHTML = tooltipData.content + extraContent;

  const dlg = openDialog({
    title: tooltipData.title,
    size: 'sm',
    className: 'rtg-tooltip-dialog',
    returnFocus: triggerEl,
    onClose: () => {
      if (state.activeTooltip === dlg.overlay) state.activeTooltip = null;
    },
  });
  dlg.body.appendChild(body);
  const gotIt = dialogButton('Got it', { primary: true });
  gotIt.addEventListener('click', dlg.close);
  dlg.footer.appendChild(gotIt);
  gotIt.focus({ preventScroll: true });

  state.activeTooltip = dlg.overlay;
}

export function closeTooltipModal() {
  const overlay = state.activeTooltip;
  if (!overlay) return;
  state.activeTooltip = null;
  if (overlay._rtgDialogClose) overlay._rtgDialogClose();
  else overlay.remove();
}
