/**
 * Load range: the shared rules for the guide's load range filter.
 *
 * Load range is a tire's construction rating. In the order shoppers see it
 * on a sidewall, from lightest to heaviest: SL (standard), XL (extra load),
 * then the light-truck letters C, D, E and F. The R2 needs XL at minimum,
 * so the filter offers "XL or higher" as one choice beside the exact
 * ratings: an R2 owner should not have to run the filter four times to see
 * everything that qualifies.
 *
 * Pure functions with no DOM, so the same rules can be tested on their own
 * and the PHP side (RTG_Database::LOAD_RANGES_XL_PLUS) mirrors XL_PLUS_SET.
 */

/** Sidewall order, lightest first. */
export const LOAD_RANGE_ORDER = ['SL', 'XL', 'C', 'D', 'E', 'F'];

/** The synthetic filter value meaning "XL and everything above it". */
export const XL_PLUS = 'xl+';

/** What "XL or higher" resolves to. Mirrored in PHP; keep them in step. */
export const XL_PLUS_SET = ['XL', 'C', 'D', 'E', 'F'];

/** The label the "XL or higher" option shows. */
export const XL_PLUS_LABEL = 'XL or higher (R2 minimum)';

/** A load range as stored, normalized for comparison: trimmed, uppercased. */
export function normalizeLoadRange(value) {
  return String(value == null ? '' : value).trim().toUpperCase();
}

/**
 * Position of a load range in sidewall order, or -1 for one we don't know
 * (a blank, or an odd spelling), which sorts after the known ones.
 */
export function loadRangeRank(value) {
  return LOAD_RANGE_ORDER.indexOf(normalizeLoadRange(value));
}

/**
 * Whether a tire's load range satisfies a filter choice.
 *
 * @param {string} rowValue    The tire's stored load range.
 * @param {string} filterValue A rating ("XL"), XL_PLUS, or '' for no filter.
 */
export function loadRangeMatches(rowValue, filterValue) {
  const wanted = String(filterValue == null ? '' : filterValue).trim();
  if (!wanted) return true;
  const have = normalizeLoadRange(rowValue);
  if (wanted === XL_PLUS) return XL_PLUS_SET.includes(have);
  return have === wanted.toUpperCase();
}

/**
 * The options the filter should offer for a catalog: the distinct ratings
 * present, in sidewall order (unknown spellings last, alphabetically), with
 * "XL or higher" slotted in after XL whenever anything at or above XL exists.
 *
 * @param {Iterable<string>} values Load ranges as stored on the tires.
 * @returns {{value: string, label: string}[]}
 */
export function loadRangeOptions(values) {
  const present = new Set();
  for (const v of values) {
    const n = normalizeLoadRange(v);
    if (n && n.length <= 10) present.add(n);
  }
  const known = LOAD_RANGE_ORDER.filter(r => present.has(r));
  const other = [...present].filter(r => !LOAD_RANGE_ORDER.includes(r)).sort();
  const hasXlPlus = [...present].some(r => XL_PLUS_SET.includes(r));

  const options = [];
  known.concat(other).forEach(r => {
    options.push({ value: r, label: r });
    if (r === 'XL' && hasXlPlus) options.push({ value: XL_PLUS, label: XL_PLUS_LABEL });
  });
  // XL itself may be absent while C/D/E are present; the choice still belongs.
  if (hasXlPlus && !known.includes('XL')) {
    options.splice(known.includes('SL') ? 1 : 0, 0, { value: XL_PLUS, label: XL_PLUS_LABEL });
  }
  return options;
}

/**
 * Count, per option value, how many rows each filter choice would return.
 * Exact ratings count themselves; XL_PLUS counts everything at or above XL.
 *
 * @param {Iterable<string>} values Load ranges of the rows being counted.
 * @returns {Map<string, number>}
 */
export function loadRangeCounts(values) {
  const counts = new Map();
  for (const v of values) {
    const n = normalizeLoadRange(v);
    if (!n) continue;
    counts.set(n, (counts.get(n) || 0) + 1);
    if (XL_PLUS_SET.includes(n)) counts.set(XL_PLUS, (counts.get(XL_PLUS) || 0) + 1);
  }
  return counts;
}
