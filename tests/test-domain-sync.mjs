/**
 * Holds rtg-shared.js's URL allowlists to the canonical ones in
 * frontend/js/modules/allowed-domains.js.
 *
 * rtg-shared.js is a classic script and can't import the canonical module,
 * so it carries a copy — and copies drift: at one point five retailers
 * rendered on the guide but vanished on the compare page, and vice versa
 * for two more. This test makes that drift a CI failure instead of a
 * silently missing buy button.
 *
 * Run with:  node tests/test-domain-sync.mjs
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

import {
  ALLOWED_LINK_DOMAINS,
  ALLOWED_REVIEW_DOMAINS,
  ALLOWED_IMAGE_HOSTNAMES,
} from '../frontend/js/modules/allowed-domains.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

// rtg-shared.js is an IIFE assigning `var RTG_SHARED` — evaluate it and take
// the object it builds, so the test sees exactly what the browser sees.
const sharedSource = readFileSync(join(root, 'frontend/js/rtg-shared.js'), 'utf8');
const RTG_SHARED = new Function(`${sharedSource}; return RTG_SHARED;`)();

let failures = 0;

function assertSameSet(name, canonical, copy) {
  const a = [...canonical].sort();
  const b = [...(copy || [])].sort();
  const missing = a.filter((d) => !b.includes(d));
  const extra = b.filter((d) => !a.includes(d));

  if (missing.length === 0 && extra.length === 0) {
    console.log(`  ok   ${name} (${a.length} entries)`);
    return;
  }

  failures++;
  console.error(`  FAIL ${name}`);
  if (missing.length) console.error(`       rtg-shared.js is missing: ${missing.join(', ')}`);
  if (extra.length) console.error(`       rtg-shared.js has extra:   ${extra.join(', ')}`);
}

console.log('rtg-shared.js allowlists match allowed-domains.js');
assertSameSet('ALLOWED_LINK_DOMAINS', ALLOWED_LINK_DOMAINS, RTG_SHARED.ALLOWED_LINK_DOMAINS);
assertSameSet('ALLOWED_REVIEW_DOMAINS', ALLOWED_REVIEW_DOMAINS, RTG_SHARED.ALLOWED_REVIEW_DOMAINS);
assertSameSet('ALLOWED_IMAGE_HOSTNAMES', ALLOWED_IMAGE_HOSTNAMES, RTG_SHARED.ALLOWED_IMAGE_HOSTNAMES);

// Both validators must also agree a link that passes one passes the other.
const sampleLink = 'https://www.tirerack.com/tires/some-tire';
if (RTG_SHARED.safeLinkURL(sampleLink) !== sampleLink) {
  failures++;
  console.error('  FAIL rtg-shared safeLinkURL rejects a canonical-list domain');
} else {
  console.log('  ok   rtg-shared safeLinkURL accepts a canonical-list domain');
}

if (failures > 0) {
  console.error(`\n${failures} domain-sync failure(s)`);
  process.exit(1);
}
console.log('\nall passed');
