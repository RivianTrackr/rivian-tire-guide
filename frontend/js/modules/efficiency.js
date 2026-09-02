/* jshint esversion: 11 */

/**
 * How much to trust a real-world efficiency figure.
 *
 * A mi/kWh number from one vehicle over a few hundred miles reads with the
 * same confidence as one from sixty vehicles over sixty thousand. The card
 * now shows the sample; this says when it is too small to lean on.
 *
 * Dependency-free so the node tests can import it.
 */

/** Fewer vehicles than this is a limited sample. */
export const MIN_VEHICLES = 3;

/** Fewer tracked miles than this is a limited sample. */
export const MIN_MILES = 2000;

/**
 * @param {*} totalMiles   Miles tracked behind the figure.
 * @param {*} vehicleCount Vehicles behind the figure.
 * @return {boolean} True when either side of the sample is thin. An unknown
 *                   sample (no miles and no vehicles reported) is not judged.
 */
export function isLimitedSample(totalMiles, vehicleCount) {
  const miles = parseFloat(totalMiles);
  const vehicles = parseInt(vehicleCount, 10);
  const hasMiles = Number.isFinite(miles) && miles > 0;
  const hasVehicles = Number.isFinite(vehicles) && vehicles > 0;
  if (!hasMiles && !hasVehicles) return false;
  return (hasVehicles && vehicles < MIN_VEHICLES) || (hasMiles && miles < MIN_MILES);
}
