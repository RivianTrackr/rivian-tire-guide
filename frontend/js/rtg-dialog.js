/* jshint esversion: 11 */

/**
 * The dialog shell as a standalone script, for pages that do not load the
 * guide bundle (the tire page). esbuild bundles this to rtg-dialog.min.js
 * and exposes it as window.RTG_DIALOG; the guide bundle imports the same
 * module directly, so there is one implementation.
 */

import { openDialog, dialogButton } from './modules/dialog.js';

export { openDialog, dialogButton };
