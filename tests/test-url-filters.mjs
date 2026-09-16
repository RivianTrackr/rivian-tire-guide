/**
 * Tests for restoring the filters from a URL.
 *
 * These drive the real applyFiltersFromURL() against the mini-DOM in
 * tests/lib/fake-dom.mjs, for two ways a shared link lost its size:
 *
 *   1. The URL sanitizer strips slashes, so `size=275/65R20` was read as
 *      `27565R20`, never matched a listed size, and was dropped on every
 *      refresh, for everyone.
 *   2. The vehicle remembered from the last visit was pressed before the size
 *      was restored. When that vehicle doesn't take the linked size, the
 *      narrowed menu had no such option and the select silently cleared.
 *
 * Run with:  node tests/test-url-filters.mjs
 * Exit code 0 = all tests passed, 1 = one or more failures.
 */

import {
  FakeSelect, fakeElement, fakeVehicleButton, installGlobals, makeChecker,
} from './lib/fake-dom.mjs';

const buttons = ['', 'R1', 'R2'].map(fakeVehicleButton);
const notice = fakeElement();
const { elements, storage } = installGlobals({
  elements: { rtgFilterNotice: notice },
  vehicleButtons: buttons,
});

const filters = await import('../frontend/js/modules/filters.js');
const { state } = await import('../frontend/js/modules/state.js');
const { applyFiltersFromURL, populateSizeDropdownGrouped, vehicleTakesSize } = filters;

const { check, failed } = makeChecker();

const ALL_SIZES = ['255/70R18', '255/65R19', '255/60R20', '275/60R20', '275/65R20', '275/50R22'];
state.VALID_SIZES = ALL_SIZES;
state.VALID_BRANDS = ['Michelin', 'Nexen'];
state.VALID_CATEGORIES = ['All-Terrain', 'Highway/Touring'];
state.vehicleSizeMap = {
  R1: ['275/65R20', '275/50R22'],
  R2: ['255/70R18', '255/65R19', '255/60R20', '275/60R20'],
};
state.VALID_VEHICLES = ['R1', 'R2'];

const pressed = () => (buttons.find(b => b.active) || {}).dataset?.vehicle ?? '';

/** A fresh page: full size menu, nothing pressed, the given URL and memory. */
function load(search, { remembered = null, restoring = false, alreadyPressed = '' } = {}) {
  const sizeSelect = new FakeSelect([]);
  elements.filterSize = sizeSelect;
  elements.filterBrand = new FakeSelect(state.VALID_BRANDS);
  elements.filterCategory = new FakeSelect(state.VALID_CATEGORIES);
  state.domCache = {};
  state.restoringFromURL = restoring;
  populateSizeDropdownGrouped('filterSize', ALL_SIZES);
  buttons.forEach(b => { b.active = (b.dataset.vehicle === alreadyPressed); });
  storage.clear();
  if (remembered !== null) storage.set('rtg_vehicle', remembered);
  notice.textContent = '';
  window.location.search = search;
  applyFiltersFromURL();
  return {
    size: sizeSelect.value,
    brand: elements.filterBrand.value,
    category: elements.filterCategory.value,
    vehicle: pressed(),
    remembered: storage.get('rtg_vehicle') ?? null,
    notice: notice.textContent,
  };
}

console.log("a size with a slash comes back from the URL");
let r = load('?size=275%2F65R20&brand=Nexen');
check('the size is the link\'s', '275/65R20', r.size);
check('so is the brand', 'Nexen', r.brand);

r = load('?category=Highway%2FTouring');
check('a category with a slash too', 'Highway/Touring', r.category);

r = load('?size=27565R20');
check('a value that isn\'t listed is not restored', '', r.size);

r = load('?size=%3Cscript%3E&brand=%3Cimg%3E');
check('nor is anything else that isn\'t on the list', ['', ''], [r.size, r.brand]);

console.log("the link's size wins over the remembered vehicle");
r = load('?size=275%2F65R20&brand=Nexen', { remembered: 'R2' });
check('the size is restored', '275/65R20', r.size);
check('the toggle steps back to All for this visit', '', r.vehicle);
check('the remembered vehicle is left alone', 'R2', r.remembered);
check('and the visitor is told why', "Showing all vehicles: the R2 doesn't come in 275/65R20.", r.notice);

r = load('?size=275%2F60R20', { remembered: 'R2' });
check('a size the remembered vehicle takes keeps the vehicle', ['R2', '275/60R20'], [r.vehicle, r.size]);
check('quietly', '', r.notice);

r = load('?brand=Nexen', { remembered: 'R2' });
check('a link with no size still opens on the remembered vehicle', 'R2', r.vehicle);

r = load('?vehicle=R2&size=275%2F65R20', { remembered: 'R1' });
check('a vehicle named in the link is the link\'s ask: it stays', 'R2', r.vehicle);
check('and a size it doesn\'t take is not restored', '', r.size);
check('the link\'s vehicle is remembered', 'R2', r.remembered);

console.log("a vehicle pressed before this pass (server mode's second pass, a shortcode)");
r = load('?size=275%2F65R20', { remembered: 'R2', alreadyPressed: 'R2' });
check('steps back too when it doesn\'t take the size', ['', '275/65R20'], [r.vehicle, r.size]);

r = load('?size=275%2F50R22', { alreadyPressed: 'R1' });
check('and stays when it does', ['R1', '275/50R22'], [r.vehicle, r.size]);

console.log("browser navigation trusts the URL alone");
r = load('?size=275%2F65R20', { remembered: 'R2', restoring: true });
check('back to a sized URL restores the size with no vehicle', ['', '275/65R20'], [r.vehicle, r.size]);
check('without a notice', '', r.notice);

console.log("vehicleTakesSize");
check('a listed size', true, vehicleTakesSize('R2', '275/60R20'));
check('an unlisted size', false, vehicleTakesSize('R2', '275/65R20'));
check('All takes everything', true, vehicleTakesSize('', '275/65R20'));
check('so does a vehicle with no fitments on file', true, vehicleTakesSize('R3', '275/65R20'));

console.log(failed() ? `\n${failed()} FAILED` : "\nall passed");
process.exit(failed() ? 1 : 0);
