<?php
/**
 * Convert ellipsoid height to height above sea level.
 *
 * WHY AT ALL
 * A satellite receiver measures the height above the WGS84 ellipsoid — a
 * smooth computational figure, not above sea level. Between the two lies the
 * geoid, and in Germany it lies around 45 meters above the ellipsoid.
 * Whoever reports the raw number as sea-level height is thus systematically
 * about 45 meters too high here — and 100 meters too low in India. For a
 * documentation that is meant to be comparable, that is untenable.
 *
 * THE MODEL
 * EGM96 by the NGA, the global 15-arc-minute grid (1440 x 721 values,
 * 0.25 degrees), as PROJ also uses it for the transition from EPSG:4979 to
 * EPSG:5773. Here it lies as integers in centimeters; the original is
 * floating point, but one centimeter is orders of magnitude finer than
 * anything that plays a role here.
 *
 * Origin and verification are described in data/geoid/README.md.
 *
 * HOW ACCURATE
 * Two error sources that differ by two orders of magnitude:
 *
 *   The model        EGM96 deviates in Central Europe by a few decimeters
 *                    from the official German quasigeoid GCG2016.
 *   The measurement  A satellite receiver is vertically around two to three
 *                    times less accurate than horizontally. With +/-4 m in
 *                    the plane it is quickly +/-10 m vertically.
 *
 * So the conversion removes a systematic error of 45 meters and leaves a
 * random one of around 10 meters standing. It is worth it — but it does not
 * make the number accurate. That is why the measurement uncertainty is
 * stored alongside and not concealed.
 */

declare(strict_types=1);

const GEOID_FILE   = PROJECT_PATH . '/data/geoid/egm96_15.i16';
const GEOID_COLUMNS = 1440;   // 360 degrees / 0.25
const GEOID_ROWS  = 721;    // 180 degrees / 0.25, both poles included
const GEOID_STEP = 0.25;

/**
 * Geoid undulation at a spot, in meters.
 *
 * That is the distance between geoid and ellipsoid: positive where the geoid
 * lies higher. The sea-level height results as ellipsoid height minus this
 * value.
 *
 * Between the grid points, bilinear averaging is used. The geoid is a very
 * smooth surface, so that suffices; over 0.25 degrees it changes by less
 * than half a meter in Central Europe.
 *
 * Returns null if the grid is missing or the inputs are nonsensical — then
 * no conversion happens, instead of reporting an invented number.
 */
function geoid_undulation(float $lat, float $lon): ?float
{
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        return null;
    }

    static $handle = null;
    if ($handle === null) {
        $handle = @fopen(GEOID_FILE, 'rb');
        if ($handle === false) {
            $handle = false;
        }
    }
    if ($handle === false) {
        return null;
    }

    // Grid coordinates. Row 0 lies at the North Pole, column 0 at 180° West.
    $y = (90.0 - $lat) / GEOID_STEP;
    $x = ($lon + 180.0) / GEOID_STEP;

    $y0 = (int) floor($y);
    $x0 = (int) floor($x);
    $fy = $y - $y0;
    $fx = $x - $x0;

    // At the South Pole there is no row below — fall back to the last row
    // there, instead of reading past the end of the grid.
    if ($y0 >= GEOID_ROWS - 1) {
        $y0 = GEOID_ROWS - 2;
        $fy = 1.0;
    }

    $sum = 0.0;
    foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as [$dx, $dy]) {
        // In the east-west direction the grid is closed: beyond 180 degrees
        // East comes 180 degrees West again.
        $col = ($x0 + $dx) % GEOID_COLUMNS;
        $row = $y0 + $dy;

        $value = geoid_value($handle, $row, $col);
        if ($value === null) {
            return null;
        }

        $weight = ($dx ? $fx : 1 - $fx) * ($dy ? $fy : 1 - $fy);
        $sum  += $value * $weight;
    }

    return $sum;
}

/** A single grid value in meters. */
function geoid_value($handle, int $row, int $column): ?float
{
    $offset = ($row * GEOID_COLUMNS + $column) * 2;
    if (fseek($handle, $offset) !== 0) {
        return null;
    }
    $raw = fread($handle, 2);
    if ($raw === false || strlen($raw) !== 2) {
        return null;
    }

    // 'v' reads unsigned; PHP has no signed 16-bit format with a fixed byte
    // order. So the sign is applied by hand.
    $u = unpack('v', $raw)[1];
    if ($u > 32767) {
        $u -= 65536;
    }
    return $u / 100.0;
}

/**
 * Ellipsoid height to height above NHN.
 *
 * Returns null if no conversion is possible. The caller then stores no
 * sea-level height — an unconverted number in the same field would be the
 * worst case, because you cannot tell by looking that it is 45 meters off.
 */
function geoid_to_nhn(float $ellipsoidHeight, float $lat, float $lon): ?float
{
    $n = geoid_undulation($lat, $lon);
    return $n === null ? null : $ellipsoidHeight - $n;
}
