/**
 * Computing with outlines: area, perimeter, a point inside.
 *
 * WHAT FOR. A find point sometimes is none. A fairy ring, a stand on a
 * slope, a searched-through area — those are outlines, not pins. In the
 * database it still remains a point with everything attached: lists, own
 * fields, photos, map sheet. It merely carries its outline in addition,
 * and lat/lon then point to a spot *inside it*.
 *
 * ON THE SPHERE, NOT ON THE SHEET. Whoever treats longitudes and latitudes
 * like x and y gets around 36 % too much out of it here: At 50° north a
 * degree of longitude is only cos(50°) ≈ 0.64 times as wide as a degree of
 * latitude. For a hectare figure that goes into a report, that is no small
 * matter.
 *
 * WHICH EARTH RADIUS. 6,371,007.18 m — the authalic radius of the WGS84
 * ellipsoid, that is, the radius of the sphere with the same surface. With
 * it, the error against the true ellipsoid area stays well under one part
 * per thousand at our sizes; the mean radius 6,371,000 would be right for
 * distances, but the wrong one for areas.
 *
 * NOT ACROSS THE 180TH MERIDIAN. The test "does the point lie inside"
 * computes in planar coordinates and would get confused there. A mapped
 * area stretching across the Pacific does not exist — and if it did, it
 * would be the least of the problems.
 */

/** Authalic earth radius of the WGS84 ellipsoid, in meters. */
const R = 6371007.1809;

const RAD = Math.PI / 180;

/** An outline is only usable from three corners on. */
export function isUsable(corners) {
    return Array.isArray(corners) && corners.length >= 3
        && corners.every(e => Array.isArray(e) && Number.isFinite(e[0]) && Number.isFinite(e[1]));
}

/**
 * Area in square meters.
 *
 * Via the spherical excess, in the form Google Maps uses as well: For each
 * edge, the difference of the longitudes times (2 + sin φ₁ + sin φ₂)
 * contributes. That is stable even for very narrow areas, where the formula
 * via the interior angles loses digits.
 */
export function areaM2(corners) {
    if (!isUsable(corners)) return null;

    let sum = 0;
    for (let i = 0; i < corners.length; i++) {
        const [lat1, lon1] = corners[i];
        const [lat2, lon2] = corners[(i + 1) % corners.length];

        // Bring into [-180, 180]: Otherwise an edge that runs just across
        // the date line counts once around the whole earth.
        let dLon = lon2 - lon1;
        while (dLon >  180) dLon -= 360;
        while (dLon < -180) dLon += 360;

        sum += dLon * RAD * (2 + Math.sin(lat1 * RAD) + Math.sin(lat2 * RAD));
    }
    return Math.abs(sum * R * R / 2);
}

/** Length of a segment on the sphere, in meters. */
export function distanceM([lat1, lon1], [lat2, lon2]) {
    const dLat = (lat2 - lat1) * RAD;
    let dLon = (lon2 - lon1);
    while (dLon >  180) dLon -= 360;
    while (dLon < -180) dLon += 360;
    dLon *= RAD;

    const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * RAD) * Math.cos(lat2 * RAD) * Math.sin(dLon / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
}

/** Perimeter of the closed outline, in meters. */
export function perimeterM(corners) {
    if (!isUsable(corners)) return null;
    let sum = 0;
    for (let i = 0; i < corners.length; i++) {
        sum += distanceM(corners[i], corners[(i + 1) % corners.length]);
    }
    return sum;
}

/**
 * Does the point lie inside the outline?
 *
 * Ray cast to the right, counting edges. The border itself counts as
 * inside — for the question we are asking ("is this spot usable as a
 * marker?"), a corner is a serviceable place, and a coincidence in the
 * last decimal place should not flip the answer.
 */
export function inOutline([lat, lon], corners) {
    if (!isUsable(corners)) return false;

    let inside = false;
    for (let i = 0, j = corners.length - 1; i < corners.length; j = i++) {
        const [lat1, lon1] = corners[i];
        const [lat2, lon2] = corners[j];

        // Exactly at the height of a corner: Only one of the two adjoining
        // edges may count, otherwise the state flips twice and the ray
        // "overlooks" the corner. Hence > above, >= below — a common and
        // proven distribution of inequalities.
        if ((lat1 > lat) !== (lat2 > lat)) {
            const crossing = lon1 + (lat - lat1) / (lat2 - lat1) * (lon2 - lon1);
            if (lon < crossing) inside = !inside;
        }
    }
    return inside;
}

/**
 * The area centroid.
 *
 * Computed in a locally planar approximation: Longitudes are compressed by
 * the cosine of the mean latitude so the weighting is right. Over a few
 * kilometers that is accurate enough for a marker, and that is what this
 * is about — not a survey.
 */
function centroid(corners) {
    const meanLat = corners.reduce((s, e) => s + e[0], 0) / corners.length;
    const k = Math.cos(meanLat * RAD) || 1;

    let area2 = 0, x = 0, y = 0;
    for (let i = 0; i < corners.length; i++) {
        const [lat1, lon1] = corners[i];
        const [lat2, lon2] = corners[(i + 1) % corners.length];
        const x1 = lon1 * k, x2 = lon2 * k;
        const cross = x1 * lat2 - x2 * lat1;
        area2 += cross;
        x += (x1 + x2) * cross;
        y += (lat1 + lat2) * cross;
    }

    // All corners on one line: Then there is no centroid, but there is a
    // middle.
    if (Math.abs(area2) < 1e-12) {
        return [
            corners.reduce((s, e) => s + e[0], 0) / corners.length,
            corners.reduce((s, e) => s + e[1], 0) / corners.length,
        ];
    }

    return [y / (3 * area2), x / (3 * area2) / k];
}

/**
 * A spot that represents the outline — and is guaranteed to lie inside.
 *
 * WHY NOT SIMPLY THE CENTROID. For a horseshoe, a forest edge around a
 * clearing or an L-shaped cut it lies outside. The marker would then stand
 * next to its own area, and worse: a point's lat/lon are no ornament here —
 * map sheet and terrain elevation are derived from them. A sheet belonging
 * to a spot that was not mapped would be a wrong statement in a report.
 *
 * If the centroid lies outside, a cut is made across the area at its
 * latitude and the middle of the widest section lying inside is taken.
 * That is the same procedure GIS systems call "point on surface": not the
 * prettiest place, but always one that lies within.
 */
export function center(corners) {
    if (!isUsable(corners)) return null;

    const middle = centroid(corners);
    if (inOutline(middle, corners)) return middle;

    const lat = middle[0];

    // Collect all spots where the horizontal cut meets an edge.
    const cuts = [];
    for (let i = 0; i < corners.length; i++) {
        const [lat1, lon1] = corners[i];
        const [lat2, lon2] = corners[(i + 1) % corners.length];
        if ((lat1 > lat) !== (lat2 > lat)) {
            cuts.push(lon1 + (lat - lat1) / (lat2 - lat1) * (lon2 - lon1));
        }
    }
    cuts.sort((a, b) => a - b);

    // Pairwise: between the first and second intersection lies area,
    // between the second and third not, and so on.
    let bestWidth = -1, bestLon = null;
    for (let i = 0; i + 1 < cuts.length; i += 2) {
        const width = cuts[i + 1] - cuts[i];
        if (width > bestWidth) {
            bestWidth = width;
            bestLon = (cuts[i] + cuts[i + 1]) / 2;
        }
    }

    // If nothing remains — with an outline that intersects itself that can
    // happen —, then better a corner than nothing at all: At least it
    // certainly belongs to the area.
    if (bestLon === null) return [corners[0][0], corners[0][1]];

    return [lat, bestLon];
}

/** The map view the outline fits into: [[southWest], [northEast]]. */
export function bounds(corners) {
    if (!isUsable(corners)) return null;
    const lats = corners.map(e => e[0]);
    const lons = corners.map(e => e[1]);
    return [[Math.min(...lats), Math.min(...lons)], [Math.max(...lats), Math.max(...lons)]];
}

const formatNumber = (value, digits) =>
    value.toLocaleString('de-DE', { minimumFractionDigits: digits, maximumFractionDigits: digits });

/**
 * Area for humans.
 *
 * Hectare is the unit forestry and mapping speak in — not square meters
 * and not square kilometers. Hence the wide middle range: from a tenth of
 * a hectare up to a hundred.
 */
export function readableArea(m2) {
    if (m2 === null || !Number.isFinite(m2)) return '';
    if (m2 < 1000)      return `${formatNumber(Math.round(m2), 0)} m²`;
    if (m2 < 10000)     return `${formatNumber(Math.round(m2 / 10) * 10, 0)} m²`;
    if (m2 < 1000000)   return `${formatNumber(m2 / 10000, 2)} ha`;
    if (m2 < 100000000) return `${formatNumber(m2 / 1000000, 2)} km²`;
    return `${formatNumber(Math.round(m2 / 1000000), 0)} km²`;
}

/** Distance for humans. */
export function readableLength(m) {
    if (m === null || !Number.isFinite(m)) return '';
    if (m < 1000) return `${formatNumber(Math.round(m), 0)} m`;
    return `${formatNumber(m / 1000, 2)} km`;
}
