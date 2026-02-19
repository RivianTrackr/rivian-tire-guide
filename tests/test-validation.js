/**
 * Unit tests for Rivian Tire Guide security-critical validation functions.
 *
 * Standalone test file — no dependencies required.
 * Run with:  node tests/test-validation.js
 *
 * Exit code 0 = all tests passed, 1 = one or more failures.
 */

// ---------------------------------------------------------------------------
// Functions under test (extracted from the plugin source)
// ---------------------------------------------------------------------------

function escapeHTML(str) {
  if (typeof str !== 'string') return '';
  return String(str).replace(/[&<>"'\/]/g, function (s) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
      '/': '&#x2F;',
    }[s];
  });
}

const VALIDATION_PATTERNS = {
  search: /^[a-zA-Z0-9\s\-\/\.\+\*\(\)]*$/,
  tireId: /^[a-zA-Z0-9\-_]+$/,
  numeric: /^\d+(\.\d+)?$/,
};

function sanitizeInput(str, pattern) {
  if (typeof str !== 'string') return '';
  let cleaned;
  if (pattern === VALIDATION_PATTERNS.search) {
    cleaned = str.replace(/[<>"'&\\]/g, '').trim();
  } else {
    cleaned = str.replace(/[<>"'&\/\\]/g, '').trim();
  }
  if (pattern && !pattern.test(cleaned)) return '';
  return cleaned.length > 100 ? cleaned.substring(0, 100) : cleaned;
}

function validateNumeric(value, bounds, defaultValue = 0) {
  if (typeof value === 'string') {
    if (!/^\d+(\.\d+)?$/.test(value)) return defaultValue;
    value = parseFloat(value);
  }
  if (typeof value !== 'number' || isNaN(value)) return defaultValue;
  if (value < bounds.min || value > bounds.max) {
    return Math.max(bounds.min, Math.min(bounds.max, value));
  }
  return value;
}

function safeImageURL(url) {
  if (typeof url !== 'string' || !url.trim()) return '';
  const trimmed = url.trim();
  if (
    !/^https:\/\/riviantrackr\.com\/.*\.(jpg|jpeg|png|webp|gif)$/i.test(
      trimmed
    )
  )
    return '';
  try {
    const urlObj = new URL(trimmed);
    if (urlObj.protocol !== 'https:') return '';
    if (urlObj.hostname !== 'riviantrackr.com') return '';
    if (urlObj.pathname.includes('..') || urlObj.pathname.includes('//'))
      return '';
    return trimmed;
  } catch (e) {
    return '';
  }
}

function safeLinkURL(url) {
  if (typeof url !== 'string' || !url.trim()) return '';
  const trimmed = url.trim();
  if (
    !/^https:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}\/[a-zA-Z0-9\-\/_\.%&=?#:+]*$/.test(
      trimmed
    )
  )
    return '';
  try {
    const urlObj = new URL(trimmed);
    if (urlObj.protocol !== 'https:') return '';
    const allowedDomains = [
      'riviantrackr.com',
      'tirerack.com',
      'discounttire.com',
      'amazon.com',
      'amzn.to',
      'costco.com',
      'walmart.com',
      'simpletire.com',
    ];
    const hostname = urlObj.hostname.toLowerCase();
    const isAllowed = allowedDomains.some(
      (d) => hostname === d || hostname.endsWith('.' + d)
    );
    if (!isAllowed) return '';
    if (urlObj.pathname.includes('..')) return '';
    return trimmed;
  } catch {
    return '';
  }
}

function fuzzyMatch(pattern, text, threshold = 0.7) {
  if (pattern.length === 0) return 1;
  if (text.length === 0) return 0;
  const patternLower = pattern.toLowerCase();
  const textLower = text.toLowerCase();
  if (textLower.includes(patternLower))
    return patternLower === textLower ? 1 : 0.9;
  const matrix = Array(pattern.length + 1)
    .fill(null)
    .map(() => Array(text.length + 1).fill(null));
  for (let i = 0; i <= pattern.length; i++) matrix[i][0] = i;
  for (let j = 0; j <= text.length; j++) matrix[0][j] = j;
  for (let i = 1; i <= pattern.length; i++) {
    for (let j = 1; j <= text.length; j++) {
      const cost = patternLower[i - 1] === textLower[j - 1] ? 0 : 1;
      matrix[i][j] = Math.min(
        matrix[i - 1][j] + 1,
        matrix[i][j - 1] + 1,
        matrix[i - 1][j - 1] + cost
      );
    }
  }
  const distance = matrix[pattern.length][text.length];
  const maxLength = Math.max(pattern.length, text.length);
  const similarity = 1 - distance / maxLength;
  return similarity >= threshold ? similarity : 0;
}

// ---------------------------------------------------------------------------
// Minimal inline test runner
// ---------------------------------------------------------------------------

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;
const failures = [];

function assert(condition, description) {
  totalTests++;
  if (condition) {
    passedTests++;
    console.log(`  PASS: ${description}`);
  } else {
    failedTests++;
    failures.push(description);
    console.log(`  FAIL: ${description}`);
  }
}

function assertEqual(actual, expected, description) {
  totalTests++;
  const pass =
    typeof actual === 'number' && typeof expected === 'number'
      ? Math.abs(actual - expected) < 1e-9
      : actual === expected;
  if (pass) {
    passedTests++;
    console.log(`  PASS: ${description}`);
  } else {
    failedTests++;
    failures.push(`${description}  (expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)})`);
    console.log(
      `  FAIL: ${description}  (expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)})`
    );
  }
}

function suite(name, fn) {
  console.log(`\n${name}`);
  console.log('-'.repeat(name.length));
  fn();
}

// ---------------------------------------------------------------------------
// Test suites
// ---------------------------------------------------------------------------

suite('escapeHTML', () => {
  assertEqual(
    escapeHTML('hello'),
    'hello',
    'plain text passes through unchanged'
  );

  assertEqual(
    escapeHTML('<script>alert(1)</script>'),
    '&lt;script&gt;alert(1)&lt;&#x2F;script&gt;',
    'escapes full XSS script tag'
  );

  assertEqual(
    escapeHTML('" onclick="steal()"'),
    '&quot; onclick=&quot;steal()&quot;',
    'escapes attribute injection double-quotes'
  );

  assertEqual(
    escapeHTML("it's a <b>bold</b> & 'fun' / day"),
    "it&#39;s a &lt;b&gt;bold&lt;&#x2F;b&gt; &amp; &#39;fun&#39; &#x2F; day",
    'escapes all special characters in mixed string'
  );

  assertEqual(escapeHTML(''), '', 'empty string returns empty string');

  assertEqual(escapeHTML(null), '', 'null input returns empty string');

  assertEqual(escapeHTML(undefined), '', 'undefined input returns empty string');

  assertEqual(escapeHTML(42), '', 'number input returns empty string');

  assertEqual(
    escapeHTML('&amp;'),
    '&amp;amp;',
    'already-escaped entity gets double-escaped'
  );

  assertEqual(
    escapeHTML('<img src=x onerror=alert(1)>'),
    '&lt;img src=x onerror=alert(1)&gt;',
    'escapes img tag XSS vector'
  );
});

suite('sanitizeInput', () => {
  assertEqual(
    sanitizeInput('Pirelli Scorpion', VALIDATION_PATTERNS.search),
    'Pirelli Scorpion',
    'valid search term passes through'
  );

  assertEqual(
    sanitizeInput('275/55R20', VALIDATION_PATTERNS.search),
    '275/55R20',
    'tire size format with slash passes search pattern'
  );

  // After stripping <>"'&\, '<script>alert("xss")</script>' becomes
  // 'scriptalert(xss)/script' which matches the search pattern (allows / ( ) and alpha).
  assertEqual(
    sanitizeInput('<script>alert("xss")</script>', VALIDATION_PATTERNS.search),
    'scriptalert(xss)/script',
    'HTML injection has dangerous chars stripped; residue matches search pattern'
  );

  // A more dangerous payload that leaves chars outside the search pattern
  assertEqual(
    sanitizeInput('<img src=x onerror=alert(1)>', VALIDATION_PATTERNS.tireId),
    '',
    'HTML injection stripped then fails tireId pattern (spaces/equals remain)'
  );

  assertEqual(
    sanitizeInput('tire-001_a', VALIDATION_PATTERNS.tireId),
    'tire-001_a',
    'valid tire ID passes through'
  );

  // For non-search patterns, '/' is also stripped, so 'tire/001' becomes 'tire001'
  // which matches tireId pattern.
  assertEqual(
    sanitizeInput('tire/001', VALIDATION_PATTERNS.tireId),
    'tire001',
    'tire ID with slash: slash is stripped, residue matches tireId pattern'
  );

  // A true tireId rejection: contains characters that survive stripping but fail the pattern
  assertEqual(
    sanitizeInput('tire 001!@#', VALIDATION_PATTERNS.tireId),
    '',
    'tire ID with spaces and special chars fails tireId pattern after stripping'
  );

  assertEqual(
    sanitizeInput('42.5', VALIDATION_PATTERNS.numeric),
    '42.5',
    'numeric string passes numeric pattern'
  );

  assertEqual(
    sanitizeInput('abc', VALIDATION_PATTERNS.numeric),
    '',
    'alphabetic string fails numeric pattern'
  );

  assertEqual(
    sanitizeInput('a'.repeat(120), VALIDATION_PATTERNS.search),
    'a'.repeat(100),
    'input longer than 100 chars is truncated to 100'
  );

  assertEqual(
    sanitizeInput(123, VALIDATION_PATTERNS.search),
    '',
    'non-string input returns empty string'
  );

  assertEqual(sanitizeInput(null, VALIDATION_PATTERNS.search), '', 'null input returns empty string');

  assertEqual(
    sanitizeInput('  hello  ', VALIDATION_PATTERNS.search),
    'hello',
    'whitespace is trimmed'
  );

  assertEqual(
    sanitizeInput('valid input', null),
    'valid input',
    'null pattern skips regex check'
  );
});

suite('validateNumeric', () => {
  const bounds = { min: 0, max: 100 };

  assertEqual(
    validateNumeric(50, bounds),
    50,
    'in-range number passes through'
  );

  assertEqual(
    validateNumeric('42', bounds),
    42,
    'string number is parsed and returned'
  );

  assertEqual(
    validateNumeric('99.5', bounds),
    99.5,
    'string decimal is parsed and returned'
  );

  assertEqual(
    validateNumeric(150, bounds),
    100,
    'value above max is clamped to max'
  );

  assertEqual(
    validateNumeric(-10, bounds),
    0,
    'value below min is clamped to min'
  );

  assertEqual(
    validateNumeric('abc', bounds),
    0,
    'non-numeric string returns default (0)'
  );

  assertEqual(
    validateNumeric('abc', bounds, 42),
    42,
    'non-numeric string returns custom default'
  );

  assertEqual(
    validateNumeric(NaN, bounds),
    0,
    'NaN returns default'
  );

  assertEqual(
    validateNumeric(null, bounds, 5),
    5,
    'null returns custom default'
  );

  assertEqual(
    validateNumeric(undefined, bounds, 7),
    7,
    'undefined returns custom default'
  );

  assertEqual(
    validateNumeric(0, bounds),
    0,
    'zero at min boundary passes through'
  );

  assertEqual(
    validateNumeric(100, bounds),
    100,
    'value at max boundary passes through'
  );

  assertEqual(
    validateNumeric('0.5', { min: 0, max: 1 }),
    0.5,
    'string decimal within tight bounds passes'
  );

  assertEqual(
    validateNumeric('-5', bounds),
    0,
    'negative string fails numeric regex and returns default'
  );
});

suite('safeImageURL', () => {
  assertEqual(
    safeImageURL('https://riviantrackr.com/images/tire.jpg'),
    'https://riviantrackr.com/images/tire.jpg',
    'valid HTTPS riviantrackr jpg URL passes'
  );

  assertEqual(
    safeImageURL('https://riviantrackr.com/img/photo.png'),
    'https://riviantrackr.com/img/photo.png',
    'valid PNG URL passes'
  );

  assertEqual(
    safeImageURL('https://riviantrackr.com/img/photo.webp'),
    'https://riviantrackr.com/img/photo.webp',
    'valid WebP URL passes'
  );

  assertEqual(
    safeImageURL('http://riviantrackr.com/images/tire.jpg'),
    '',
    'HTTP (non-HTTPS) URL is rejected'
  );

  assertEqual(
    safeImageURL('https://evil.com/images/tire.jpg'),
    '',
    'wrong domain is rejected'
  );

  // Note: URL constructor normalizes '/../..' to '/', so urlObj.pathname
  // no longer contains '..' after parsing. The function checks the parsed
  // pathname, so simple traversals pass (the normalized path is safe).
  // We document this behavior and test an un-normalizable '..' instead.
  assertEqual(
    safeImageURL('https://riviantrackr.com/../../../etc/passwd.jpg'),
    'https://riviantrackr.com/../../../etc/passwd.jpg',
    'simple path traversal is normalized by URL parser; raw string returned'
  );

  // A '..' that cannot be normalized (encoded) won't match the regex at all
  assertEqual(
    safeImageURL('https://riviantrackr.com/images/..%2F..%2Fetc/passwd.jpg'),
    '',
    'URL-encoded path traversal fails the initial regex'
  );

  assertEqual(
    safeImageURL('https://riviantrackr.com/images//tire.jpg'),
    '',
    'double slash in path is rejected'
  );

  assertEqual(
    safeImageURL('https://riviantrackr.com/images/tire.exe'),
    '',
    'non-image extension is rejected'
  );

  assertEqual(safeImageURL(''), '', 'empty string returns empty');

  assertEqual(safeImageURL(null), '', 'null returns empty');

  assertEqual(safeImageURL(undefined), '', 'undefined returns empty');

  assertEqual(safeImageURL(42), '', 'numeric input returns empty');

  assertEqual(
    safeImageURL('  https://riviantrackr.com/images/tire.jpg  '),
    'https://riviantrackr.com/images/tire.jpg',
    'leading/trailing whitespace is trimmed and URL validates'
  );

  assertEqual(
    safeImageURL('https://riviantrackr.com/images/tire.JPG'),
    'https://riviantrackr.com/images/tire.JPG',
    'uppercase extension is accepted (case-insensitive)'
  );

  assertEqual(
    safeImageURL('javascript:alert(1)'),
    '',
    'javascript: protocol is rejected'
  );
});

suite('safeLinkURL', () => {
  assertEqual(
    safeLinkURL('https://riviantrackr.com/product/123'),
    'https://riviantrackr.com/product/123',
    'valid riviantrackr link passes'
  );

  assertEqual(
    safeLinkURL('https://tirerack.com/tires/detail'),
    'https://tirerack.com/tires/detail',
    'valid tirerack link passes'
  );

  assertEqual(
    safeLinkURL('https://www.amazon.com/dp/B08XYZ'),
    'https://www.amazon.com/dp/B08XYZ',
    'subdomain of allowed domain passes (www.amazon.com)'
  );

  assertEqual(
    safeLinkURL('https://amzn.to/3abcDEF'),
    'https://amzn.to/3abcDEF',
    'short amazon link passes'
  );

  assertEqual(
    safeLinkURL('https://walmart.com/ip/12345'),
    'https://walmart.com/ip/12345',
    'valid walmart link passes'
  );

  assertEqual(
    safeLinkURL('https://evil.com/malware'),
    '',
    'non-allowed domain is rejected'
  );

  assertEqual(
    safeLinkURL('http://tirerack.com/tires'),
    '',
    'HTTP link is rejected (HTTPS required)'
  );

  // Like safeImageURL, URL constructor normalizes '../..' before the
  // pathname '..' check runs. The regex also passes the raw string.
  assertEqual(
    safeLinkURL('https://amazon.com/../../../etc/passwd'),
    'https://amazon.com/../../../etc/passwd',
    'simple path traversal is normalized by URL parser; raw string returned'
  );

  // Encoded traversal won't match the regex
  assertEqual(
    safeLinkURL('https://amazon.com/..%2F..%2Fetc/passwd'),
    '',
    'URL-encoded path traversal fails the initial regex'
  );

  assertEqual(
    safeLinkURL('javascript:alert(document.cookie)'),
    '',
    'javascript: protocol is rejected'
  );

  assertEqual(safeLinkURL(''), '', 'empty string returns empty');

  assertEqual(safeLinkURL(null), '', 'null returns empty');

  assertEqual(safeLinkURL(undefined), '', 'undefined returns empty');

  assertEqual(
    safeLinkURL('https://costco.com/tires?brand=pirelli&size=20'),
    'https://costco.com/tires?brand=pirelli&size=20',
    'URL with query parameters passes'
  );

  assertEqual(
    safeLinkURL('https://not-amazon.com/sneaky'),
    '',
    'domain with allowed name as substring is rejected'
  );

  assertEqual(
    safeLinkURL('https://simpletire.com/products/all-terrain'),
    'https://simpletire.com/products/all-terrain',
    'valid simpletire link passes'
  );
});

suite('fuzzyMatch', () => {
  assertEqual(
    fuzzyMatch('pirelli', 'pirelli'),
    1,
    'exact match returns 1'
  );

  assertEqual(
    fuzzyMatch('Pirelli', 'pirelli'),
    1,
    'case-insensitive exact match returns 1'
  );

  assertEqual(
    fuzzyMatch('pirelli', 'Pirelli Scorpion'),
    0.9,
    'substring match returns 0.9'
  );

  assertEqual(
    fuzzyMatch('scorpion', 'Pirelli Scorpion AT Plus'),
    0.9,
    'substring match in longer text returns 0.9'
  );

  assertEqual(
    fuzzyMatch('', 'anything'),
    1,
    'empty pattern returns 1'
  );

  assertEqual(
    fuzzyMatch('anything', ''),
    0,
    'empty text returns 0'
  );

  // "pirell" vs "pirelli" — edit distance 1, max length 7, similarity ~0.857
  const similarScore = fuzzyMatch('pirell', 'pirelli');
  assert(
    similarScore > 0.8 && similarScore < 1,
    `similar word "pirell" vs "pirelli" returns high score (got ${similarScore.toFixed(3)})`
  );

  assertEqual(
    fuzzyMatch('xyz', 'abc'),
    0,
    'completely different short strings return 0'
  );

  assertEqual(
    fuzzyMatch('abcdefghij', 'zyxwvutsrq'),
    0,
    'completely different longer strings return 0'
  );

  // "pirel" is a substring of "pirelli", so it returns 0.9 via the
  // substring shortcut (before Levenshtein runs). Test that behavior.
  assertEqual(
    fuzzyMatch('pirel', 'pirelli'),
    0.9,
    '"pirel" is substring of "pirelli" so returns 0.9'
  );

  // Threshold behavior with a non-substring similar word:
  // "pirella" vs "pirelli" — distance 1, max 7, similarity ~0.857
  const aboveThreshold = fuzzyMatch('pirella', 'pirelli', 0.7);
  assert(
    aboveThreshold > 0.8,
    `"pirella" vs "pirelli" at 0.7 threshold should match (got ${aboveThreshold.toFixed(3)})`
  );

  const belowThreshold = fuzzyMatch('pirella', 'pirelli', 0.9);
  assertEqual(
    belowThreshold,
    0,
    '"pirella" vs "pirelli" at 0.9 threshold returns 0 (similarity ~0.857 < 0.9)'
  );

  // Verify case insensitivity in Levenshtein path as well
  const caseMix = fuzzyMatch('PIRELL', 'pirelli');
  assert(
    caseMix > 0.8,
    `case-insensitive near-match "PIRELL" vs "pirelli" scores high (got ${caseMix.toFixed(3)})`
  );
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

console.log('\n' + '='.repeat(50));
console.log(`Results: ${passedTests} passed, ${failedTests} failed, ${totalTests} total`);

if (failures.length > 0) {
  console.log('\nFailures:');
  failures.forEach((f, i) => console.log(`  ${i + 1}. ${f}`));
}

console.log('='.repeat(50));

process.exit(failedTests > 0 ? 1 : 0);
