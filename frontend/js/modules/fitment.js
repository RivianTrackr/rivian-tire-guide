/* jshint esversion: 11 */

/**
 * Load-index fitment — does this tire carry a Rivian?
 *
 * Every Rivian has a load-index floor (R1: 116, R2: 112, both configurable
 * in Tire Discovery). The guide has always shown the load index and let a
 * tooltip explain the rule; this is the first place anything applies it.
 *
 * Dependency-free on purpose: the guide bundle, the compare page and the
 * node tests all import it, and none of them should need a DOM or the
 * shared state to ask the question.
 */

/**
 * The single-tire load index from whatever the column holds.
 *
 * Catalog data writes it many ways: "116", "116T", "116/113" (the LT
 * dual/single pair — the first figure is the single-tire rating that
 * matters here), "116 (2756 lb)". The first two- or three-digit run is it.
 *
 * @param {*} raw The stored load_index value.
 * @return {number} The load index, or 0 when the value has none.
 */
export function parseLoadIndex(raw) {
  if (raw === null || raw === undefined) return 0;
  const match = String(raw).match(/\d{2,3}/);
  if (!match) return 0;
  const value = parseInt(match[0], 10);
  return value >= 60 && value <= 200 ? value : 0;
}

/**
 * Which vehicles this tire falls short for.
 *
 * With a vehicle chosen, only that vehicle is judged. Without one, every
 * vehicle the size fits is — a tire in an R1 size with a load index of 110
 * is a problem whichever toggle is pressed, and the tire page and compare
 * page have no toggle at all.
 *
 * A vehicle whose size list doesn't include this size is skipped: "below the
 * R2 minimum" is noise on a tire no R2 takes.
 *
 * @param {Object} tire            { loadIndex, size }
 * @param {Object} vehicleSizeMap  vehicle => [sizes]
 * @param {Object} floors          vehicle => minimum load index
 * @param {string} [vehicle]       The chosen vehicle, or '' for all.
 * @return {Array<{vehicle: string, floor: number}>} Shortfalls, in map order.
 */
export function fitmentShortfalls(tire, vehicleSizeMap, floors, vehicle = '') {
  const loadIndex = parseLoadIndex(tire && tire.loadIndex);
  if (!loadIndex) return [];

  const size = String((tire && tire.size) || '').trim().toLowerCase();
  const map = vehicleSizeMap && typeof vehicleSizeMap === 'object' ? vehicleSizeMap : {};
  const mins = floors && typeof floors === 'object' ? floors : {};

  const vehicles = vehicle ? [vehicle] : Object.keys(mins);
  const out = [];

  vehicles.forEach(name => {
    const floor = parseInt(mins[name], 10);
    if (!Number.isFinite(floor) || floor <= 0) return;

    // A chosen vehicle is judged even when its size list is unknown: the
    // toggle already narrowed the listing to its sizes.
    if (!vehicle) {
      const sizes = Array.isArray(map[name]) ? map[name] : [];
      const fits = sizes.some(s => String(s).trim().toLowerCase() === size);
      if (!fits) return;
    }

    if (loadIndex < floor) {
      out.push({ vehicle: name, floor });
    }
  });

  return out;
}

/**
 * One sentence for a warning row.
 *
 *   "Load index 110 is below the R1 minimum of 116."
 *   "Load index 110 is below the R1 (116) and R2 (112) minimums."
 *
 * @param {number} loadIndex
 * @param {Array<{vehicle: string, floor: number}>} shortfalls
 * @return {string} Empty when there is nothing to say.
 */
export function describeShortfalls(loadIndex, shortfalls) {
  if (!Array.isArray(shortfalls) || shortfalls.length === 0) return '';
  const li = parseLoadIndex(loadIndex);
  if (shortfalls.length === 1) {
    return `Load index ${li} is below the ${shortfalls[0].vehicle} minimum of ${shortfalls[0].floor}.`;
  }
  const parts = shortfalls.map(s => `${s.vehicle} (${s.floor})`);
  const last = parts.pop();
  return `Load index ${li} is below the ${parts.join(', ')} and ${last} minimums.`;
}
