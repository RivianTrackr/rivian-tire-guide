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

// --- Third-party wheel sizes ---

/**
 * The third-party entry for a size on a vehicle, if it is one.
 *
 * The map (rtgData.settings.thirdPartySizes) lists, per vehicle, the sizes
 * Rivian never offered but an aftermarket wheel in the guide takes:
 * vehicle => { size => { wheel, note } }. A size a factory wheel also lists
 * is never in it, so a hit here means "only on 3rd-party wheels".
 *
 * @param {string} size
 * @param {string} vehicle
 * @param {Object} thirdPartySizes
 * @return {{wheel: string, note: string}|null}
 */
export function thirdPartyEntry(size, vehicle, thirdPartySizes) {
  const want = String(size || '').trim().toLowerCase();
  if (!want) return null;
  const sizes = thirdPartySizes && typeof thirdPartySizes === 'object' ? thirdPartySizes[vehicle] : null;
  if (!sizes || typeof sizes !== 'object') return null;
  for (const listed of Object.keys(sizes)) {
    if (String(listed).trim().toLowerCase() === want) {
      const entry = sizes[listed] || {};
      return { wheel: String(entry.wheel || ''), note: String(entry.note || '') };
    }
  }
  return null;
}

/**
 * Which vehicles take this tire only on third-party wheels.
 *
 * With a vehicle chosen, only that vehicle is judged; without one, every
 * vehicle in the map. Needs no load index: the question is the size alone.
 *
 * @param {Object} tire            { size }
 * @param {Object} thirdPartySizes vehicle => { size => { wheel, note } }
 * @param {string} [vehicle]
 * @return {Array<{vehicle: string, wheel: string, note: string}>}
 */
export function thirdPartyFits(tire, thirdPartySizes, vehicle = '') {
  const map = thirdPartySizes && typeof thirdPartySizes === 'object' ? thirdPartySizes : {};
  const vehicles = vehicle ? [vehicle] : Object.keys(map);
  const out = [];
  vehicles.forEach(name => {
    const entry = thirdPartyEntry(tire && tire.size, name, map);
    if (entry) out.push({ vehicle: name, wheel: entry.wheel, note: entry.note });
  });
  return out;
}

/**
 * The rim diameter a size names, e.g. 18 for 245/60R18; 0 when it has none.
 *
 * @param {string} size
 * @return {number}
 */
export function rimInches(size) {
  const m = String(size || '').match(/R(\d{2})/i);
  return m ? parseInt(m[1], 10) : 0;
}

/**
 * One sentence for a third-party note.
 *
 *   'Fits R2 on 3rd-party 18" wheels only. Not a factory size, so fitment may vary.'
 *
 * @param {string} size
 * @param {Array<{vehicle: string}>} fits From thirdPartyFits().
 * @return {string} Empty when there is nothing to say.
 */
export function describeThirdPartyFits(size, fits) {
  if (!Array.isArray(fits) || fits.length === 0) return '';
  const names = fits.map(f => f.vehicle);
  const last = names.pop();
  const who = names.length ? `${names.join(', ')} and ${last}` : last;
  const rim = rimInches(size);
  const on = rim ? `3rd-party ${rim}" wheels` : '3rd-party wheels';
  return `Fits ${who} on ${on} only. Not a factory size, so fitment may vary.`;
}
