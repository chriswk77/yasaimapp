/**
 * Test of the outline computation.
 *
 * WHY THIS IS TESTED. The area is not an ornament: it later appears in a
 * report. And the centre point even less so — from it come the
 * Messtischblatt and the terrain height of the point. If it lies next to
 * the area, the report names a sheet nobody was ever in.
 *
 * WHAT IS TESTED AGAINST. Not against one's own computation with other
 * numbers, but against two independent routes:
 *
 *   The closed form. For a degree rectangle the spherical area is exactly
 *   R²·Δλ·(sin φ₂ − sin φ₁). That is a completely different derivation
 *   than the edge sum in areaM2 — if both agree, both are right.
 *
 *   The plane. Across a hundred metres the earth is flat enough that
 *   length times width holds. A square that was built metrically must
 *   yield 10 000 m².
 *
 *   node tests/polygon.mjs
 */

const {
    areaM2, perimeterM, distanceM, inOutline, center, bounds, isUsable,
    readableArea, readableLength,
} = await import('../assets/js/polygon.js');

const R = 6371007.1809;
const ARC = Math.PI / 180;

let failed = 0;

function check(name, condition, extra = '') {
    if (condition) return;
    failed++;
    console.log(`  FAILED  ${name}${extra ? ' — ' + extra : ''}`);
}

function near(name, actual, expected, toleranceShare) {
    const diff = Math.abs(actual - expected);
    const share = expected === 0 ? diff : diff / Math.abs(expected);
    check(name, share <= toleranceShare,
        `${actual} instead of ${expected} (${(share * 100).toFixed(4)} % off, allowed ${(toleranceShare * 100).toFixed(4)} %)`);
}

// ---------------------------------------------------------------------------
console.log('\nArea against the closed form for degree rectangles');
// ---------------------------------------------------------------------------

/** Exact spherical area of a rectangle of latitude and longitude degrees. */
function exactRectangle(lat1, lat2, lon1, lon2) {
    return R * R * Math.abs(lon2 - lon1) * ARC
         * Math.abs(Math.sin(lat2 * ARC) - Math.sin(lat1 * ARC));
}

const rectangles = [
    ['at the equator, 1°',           0,  1,  0,  1],
    ['at 50° north, 1°',            50, 51,  8,  9],
    ['at 70° north, 1°',            70, 71, 20, 21],
    ['on the southern hemisphere', -34, -33, 18, 19],
    ['across the equator',          -1,  1, -1,  1],
    ['narrow and tall',             49, 55, 8, 8.01],
    ['flat and wide',               49, 49.01, 6, 15],
    ['small, at 50°',               50, 50.001, 8, 8.001],
];

for (const [name, lat1, lat2, lon1, lon2] of rectangles) {
    const corners = [[lat1, lon1], [lat1, lon2], [lat2, lon2], [lat2, lon1]];
    // The edge sum computes the edges as great-circle pieces, the closed
    // form takes circles of latitude — at one degree of height a difference
    // in the per-mille range, otherwise the test would be worthless.
    near(name, areaM2(corners), exactRectangle(lat1, lat2, lon1, lon2), 3e-3);
}

// Winding direction must change nothing: an outline is an outline.
{
    const corners = [[50, 8], [50, 9], [51, 9], [51, 8]];
    const backwards = [...corners].reverse();
    near('winding direction reversed', areaM2(backwards), areaM2(corners), 1e-12);
}

// And neither must the starting point.
{
    const corners = [[50, 8], [50.02, 8.03], [50.05, 8.01], [50.03, 7.98]];
    const rotated = [...corners.slice(2), ...corners.slice(0, 2)];
    near('different starting point', areaM2(rotated), areaM2(corners), 1e-12);
}

// ---------------------------------------------------------------------------
console.log('Area against the plane');
// ---------------------------------------------------------------------------

/** A metrically built square with edge length m, centred at lat/lon. */
function metricSquare(lat, lon, m) {
    const dLat = (m / 2) / (R * ARC);
    const dLon = dLat / Math.cos(lat * ARC);
    return [
        [lat - dLat, lon - dLon], [lat - dLat, lon + dLon],
        [lat + dLat, lon + dLon], [lat + dLat, lon - dLon],
    ];
}

for (const lat of [0, 25, 50, 60, 70]) {
    // One hectare: 100 m by 100 m. Exactly what gets paced off on an area
    // in the forest.
    near(`100 m square at ${lat}°`, areaM2(metricSquare(lat, 8, 100)), 10000, 1e-4);
}
near('1 km square at 50°', areaM2(metricSquare(50, 8, 1000)), 1e6, 1e-4);

// THE ACTUAL MISUNDERSTANDING this file is written against: length times
// width in degrees, without the cosine. At 50° it would come out 36 % too
// high.
{
    const corners = metricSquare(50, 8, 1000);
    const [[sLat, sLon], [nLat, nLon]] = bounds(corners);
    const naive = (nLat - sLat) * (nLon - sLon) * (111319.49 ** 2);
    check('computed flat it would be far too much',
        naive / areaM2(corners) > 1.5,
        `factor ${(naive / areaM2(corners)).toFixed(3)}`);
}

// ---------------------------------------------------------------------------
console.log('Perimeter and distance');
// ---------------------------------------------------------------------------

near('100 m square, perimeter', perimeterM(metricSquare(50, 8, 100)), 400, 1e-4);
near('1 km square, perimeter',  perimeterM(metricSquare(50, 8, 1000)), 4000, 1e-4);

// A degree of latitude is equally long everywhere.
near('one degree of latitude', distanceM([50, 8], [51, 8]), R * ARC, 1e-12);
// A degree of longitude shrinks with the cosine.
near('one degree of longitude at 60°', distanceM([60, 8], [60, 9]), R * ARC * Math.cos(60 * ARC), 1e-4);

// Across the date line: 2° and not 358°.
near('across the 180th meridian', distanceM([0, 179], [0, -179]), R * ARC * 2, 1e-9);

// ---------------------------------------------------------------------------
console.log('Does the point lie inside');
// ---------------------------------------------------------------------------

const square = [[50, 8], [50, 9], [51, 9], [51, 8]];
check('the centre lies inside',       inOutline([50.5, 8.5], square));
check('left of it is out',           !inOutline([50.5, 7.5], square));
check('right of it is out',          !inOutline([50.5, 9.5], square));
check('above it is out',             !inOutline([51.5, 8.5], square));
check('below it is out',             !inOutline([49.5, 8.5], square));

// THE CLASSIC MISTAKE in ray casting: the ray hits exactly one corner.
// If both adjacent edges count, the state flips twice and the point counts
// as outside although it lies inside.
const spike = [[50, 8], [50.5, 9], [51, 8], [51, 7], [50, 7]];
check('ray exactly through a corner', inOutline([50.5, 7.5], spike));
check('ray through a corner, outside', !inOutline([50.5, 9.5], spike));

// Horizontal edges at the height of the point.
const stairs = [[50, 8], [50, 9], [50.5, 9], [50.5, 9.5], [51, 9.5], [51, 8]];
check('horizontal edge, inside',   inOutline([50.5, 8.5], stairs));
check('horizontal edge, outside', !inOutline([50.5, 9.7], stairs));

// A hole does not exist — a self-touching outline, however, does.
check('below three corners there is nothing', !inOutline([50, 8], [[50, 8], [51, 9]]));

// ---------------------------------------------------------------------------
console.log('The centre point lies within the area');
// ---------------------------------------------------------------------------

// For all four the centroid lies outside. That is exactly the case
// center() exists for: a marker next to its own area would be wrong,
// and a Messtischblatt derived from it would be a wrong statement.
const nasty = {
    'L-shaped':  [[50, 8], [50, 9], [50.3, 9], [50.3, 8.3], [51, 8.3], [51, 8]],
    'Horseshoe': [[50, 8], [50, 9], [50.2, 9], [50.2, 8.2], [50.8, 8.2], [50.8, 9],
                  [51, 9], [51, 8]],
    'C-shaped':  [[50, 9], [50, 8], [51, 8], [51, 9], [50.9, 9], [50.9, 8.1],
                  [50.1, 8.1], [50.1, 9]],
    'Sickle':    [[50, 8], [50.5, 8.9], [51, 8], [50.9, 8], [50.5, 8.55], [50.1, 8]],
    'Zigzag':    [[50, 8], [50.1, 8.9], [50.2, 8.05], [50.3, 8.9], [50.4, 8.05],
                  [50.5, 8.9], [50.5, 8]],
};

for (const [name, corners] of Object.entries(nasty)) {
    const middle = center(corners);
    check(`${name}: centre point lies inside`, inOutline(middle, corners),
        `${middle[0].toFixed(5)}, ${middle[1].toFixed(5)}`);
}

// For the rectangle it must be the true centre, not just some spot inside.
{
    const middle = center(square);
    near('rectangle: centre in latitude',  middle[0], 50.5, 1e-9);
    near('rectangle: centre in longitude', middle[1], 8.5, 1e-9);
}

// And for the triangle the centroid.
{
    const middle = center([[50, 8], [50, 9], [51, 8]]);
    near('triangle: centroid in latitude',  middle[0], 50 + 1 / 3, 1e-4);
    near('triangle: centroid in longitude', middle[1], 8 + 1 / 3, 1e-4);
}

// A BROAD TEST instead of individual hand-picked cases.
//
// Stars are no good for this, obvious as they are: a regular spiked shape
// is star-shaped around its centre, and its centroid therefore ALWAYS lies
// inside, no matter how deep the spikes are. Only the count below showed
// that — 190 shapes, not a single fallback case.
//
// What really throws the centroid out are curved bands and deep notches:
// the strip of forest around a knoll, the shore around a pond, the stand
// along the bend of a brook. Exactly the shapes one outlines in the field.
{
    const toRadians = (r, angle) =>
        [50 + r * Math.cos(angle), 8 + r * Math.sin(angle) / Math.cos(50 * ARC)];

    let out = 0, total = 0;

    // Arc bands: a strip along a circular arc. The further it closes and
    // the thinner it is, the more surely the centre lies in the hole.
    for (let span = 100; span <= 340; span += 20) {
        for (let thickness = 10; thickness <= 70; thickness += 15) {
            const ra = 0.3, ri = ra * (1 - thickness / 100);
            const corners = [];
            for (let i = 0; i <= 24; i++) corners.push(toRadians(ra, span * ARC * i / 24));
            for (let i = 24; i >= 0; i--) corners.push(toRadians(ri, span * ARC * i / 24));

            total++;
            const middle = center(corners);
            check(`arc band ${span}°, ${thickness} % thick`, inOutline(middle, corners),
                `${middle[0].toFixed(5)}, ${middle[1].toFixed(5)}`);
            if (!inOutline(rawCentroid(corners), corners)) out++;
        }
    }

    // U shapes with legs of different widths — the second way out.
    for (let thickness = 5; thickness <= 45; thickness += 5) {
        for (let depth = 30; depth <= 100; depth += 10) {
            const d = thickness / 100, t = depth / 100;
            const corners = [
                [50, 8], [50 + t, 8], [50 + t, 8 + d], [50 + d, 8 + d],
                [50 + d, 8 + 1 - d], [50 + t, 8 + 1 - d], [50 + t, 8 + 1], [50, 8 + 1],
            ];

            total++;
            const middle = center(corners);
            check(`U, legs ${thickness} %, depth ${depth} %`, inOutline(middle, corners),
                `${middle[0].toFixed(5)}, ${middle[1].toFixed(5)}`);
            if (!inOutline(rawCentroid(corners), corners)) out++;
        }
    }

    // If not a single one of these shapes throws the centroid out, the loop
    // does not test the fallback path at all — then the test is broken, not
    // the code. Exactly that was the case on the first attempt.
    check('the shapes really do demand the fallback path', out > total / 4,
        `only ${out} of ${total} shapes with the centroid outside`);
    console.log(`  (${out} of ${total} shapes need the fallback path)`);
}

/** The pure area centroid, without the rescue — only for the test above. */
function rawCentroid(corners) {
    const meanLat = corners.reduce((s, e) => s + e[0], 0) / corners.length;
    const k = Math.cos(meanLat * ARC) || 1;
    let f = 0, x = 0, y = 0;
    for (let i = 0; i < corners.length; i++) {
        const [lat1, lon1] = corners[i];
        const [lat2, lon2] = corners[(i + 1) % corners.length];
        const x1 = lon1 * k, x2 = lon2 * k;
        const cross = x1 * lat2 - x2 * lat1;
        f += cross; x += (x1 + x2) * cross; y += (lat1 + lat2) * cross;
    }
    return [y / (3 * f), x / (3 * f) / k];
}

// ---------------------------------------------------------------------------
console.log('What is unusable returns nothing');
// ---------------------------------------------------------------------------

for (const broken of [null, undefined, [], [[50, 8]], [[50, 8], [51, 9]],
                      [[50, 8], [51, 9], [NaN, 8]], [[50, 8], [51, 9], ['a', 'b']],
                      'irgendwas', 42]) {
    check(`isUsable(${JSON.stringify(broken)}) is false`, !isUsable(broken));
    check(`areaM2 of it is null`, areaM2(broken) === null);
    check(`center of it is null`, center(broken) === null);
}

// ---------------------------------------------------------------------------
console.log('Labelling');
// ---------------------------------------------------------------------------

const labels = [
    [0, '0 m²'], [1, '1 m²'], [820, '820 m²'], [999, '999 m²'],
    [1234, '1.230 m²'], [9999, '10.000 m²'],
    [10000, '1,00 ha'], [32400, '3,24 ha'], [999999, '100,00 ha'],
    [1000000, '1,00 km²'], [12500000, '12,50 km²'],
];
for (const [value, expected] of labels) {
    check(`readableArea(${value})`, readableArea(value) === expected,
        `"${readableArea(value)}" instead of "${expected}"`);
}
for (const [value, expected] of [[0, '0 m'], [480, '480 m'], [999, '999 m'],
                                 [1000, '1,00 km'], [1250, '1,25 km']]) {
    check(`readableLength(${value})`, readableLength(value) === expected,
        `"${readableLength(value)}" instead of "${expected}"`);
}

// ---------------------------------------------------------------------------
console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
