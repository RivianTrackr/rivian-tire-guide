/* jshint esversion: 11 */

/**
 * Price presentation — the set-of-four figure and how fresh the price is.
 *
 * Nobody buys one tire, and a shopper can't tell a price the affiliate feed
 * refreshed this morning from one an admin typed a year ago. Both answers
 * come from data already in the row; this module only phrases them.
 *
 * Dependency-free so the compare page and the node tests can import it.
 */

/** How many tires a set is. A Rivian has four corners and no spare. */
export const SET_QUANTITY = 4;

/**
 * Days before "as of" is worth saying. Every card carrying the same
 * recent date was noise; the date earns its line once it's old enough
 * to matter to the shopper.
 */
export const AS_OF_MIN_DAYS = 30;

/**
 * "$275" — a per-tire price to the dollar. The guide calls it an average,
 * so cents were false precision, and "$442.4" was worse than either.
 */
export function formatWholePrice(price) {
  const p = parseFloat(price);
  if (!Number.isFinite(p) || p <= 0) return '';
  return '$' + Math.round(p).toLocaleString('en-US');
}

/**
 * The price of a set, rounded to the dollar.
 *
 * @param {*} price Per-tire price.
 * @param {number} [qty]
 * @return {number} 0 when the price is unknown.
 */
export function setPrice(price, qty = SET_QUANTITY) {
  const p = parseFloat(price);
  if (!Number.isFinite(p) || p <= 0) return 0;
  return Math.round(p * qty);
}

/**
 * "$1,156" — the set price as text, or '' when there is no price.
 */
export function formatSetPrice(price, qty = SET_QUANTITY) {
  const total = setPrice(price, qty);
  return total > 0 ? '$' + total.toLocaleString('en-US') : '';
}

/**
 * Parse a MySQL DATETIME ("2026-08-28 14:03:00") as a local date.
 *
 * `new Date("2026-08-28 14:03:00")` is implementation-defined; Safari
 * returned Invalid Date for years. Only the calendar date is read — the
 * label never shows a time.
 *
 * @param {*} value
 * @return {Date|null}
 */
export function parseMysqlDate(value) {
  if (typeof value !== 'string') return null;
  const m = value.trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return null;
  const y = parseInt(m[1], 10);
  const mo = parseInt(m[2], 10);
  const d = parseInt(m[3], 10);
  if (y < 2000 || mo < 1 || mo > 12 || d < 1 || d > 31) return null;
  const date = new Date(y, mo - 1, d);
  return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * "Aug 28", or "Aug 28, 2025" when it isn't this year.
 *
 * @param {Date} date
 * @param {Date} [now]
 */
export function formatAsOf(date, now = new Date()) {
  if (!(date instanceof Date)) return '';
  const opts = { month: 'short', day: 'numeric' };
  if (date.getFullYear() !== now.getFullYear()) opts.year = 'numeric';
  return date.toLocaleDateString('en-US', opts);
}

/**
 * When the price was last touched, and whether that is too long ago.
 *
 * Mirrors RTG_Stale_Prices::last_price_touch(): a synced price carries its
 * own timestamp; a manual tire's closest proxy is updated_at, since any edit
 * implies a person looked at the row. The later of the two wins.
 *
 * @param {Object} tire       { priceSyncedAt, updatedAt } as MySQL strings.
 * @param {number} staleDays  Days without a touch before the price is stale.
 * @param {Date}   [now]
 * @return {{date: Date|null, label: string, stale: boolean, ageDays: number, show: boolean}}
 *         `show` is whether the label has earned its line (AS_OF_MIN_DAYS).
 */
export function priceFreshness(tire, staleDays, now = new Date()) {
  const synced = parseMysqlDate(tire && tire.priceSyncedAt);
  const edited = parseMysqlDate(tire && tire.updatedAt);

  let date = null;
  if (synced && edited) date = synced >= edited ? synced : edited;
  else date = synced || edited;

  if (!date) return { date: null, label: '', stale: false, ageDays: 0, show: false };

  const days = parseInt(staleDays, 10);
  const ageMs = now.getTime() - date.getTime();
  const ageDays = Math.floor(ageMs / 86400000);
  const stale = Number.isFinite(days) && days > 0 && ageMs > days * 86400000;

  return {
    date,
    label: 'as of ' + formatAsOf(date, now),
    stale,
    ageDays,
    show: stale || ageDays >= AS_OF_MIN_DAYS,
  };
}
