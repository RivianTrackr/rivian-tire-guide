/* jshint esversion: 11 */

/**
 * The vehicle toggle, remembered between visits.
 *
 * An owner has one Rivian. Pressing R1 on every visit — and on every tire
 * page and comparison that can't see the guide's toggle — was the tax on
 * everything that depends on knowing the vehicle (the load-index warning
 * above all). localStorage: it's a preference of this browser, not an
 * account setting, and guests have it too.
 *
 * Every read and write is guarded: a private window or a browser set to
 * block site data throws on the accessor itself, and the guide must render
 * exactly as before when it does.
 */

export const VEHICLE_STORAGE_KEY = 'rtg_vehicle';

/**
 * Store the chosen vehicle. An empty string is a choice too — "All" —
 * and is kept, so clearing the toggle isn't undone on the next visit.
 *
 * @param {string} vehicle
 */
export function rememberVehicle(vehicle) {
  try {
    window.localStorage.setItem(VEHICLE_STORAGE_KEY, String(vehicle || ''));
  } catch (e) {
    // No storage: nothing to remember, nothing to break.
  }
}

/**
 * The remembered vehicle, or '' when none (or none reachable).
 *
 * @return {string}
 */
export function rememberedVehicle() {
  try {
    const value = window.localStorage.getItem(VEHICLE_STORAGE_KEY);
    return typeof value === 'string' ? value.trim().slice(0, 20) : '';
  } catch (e) {
    return '';
  }
}
