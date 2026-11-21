/**
 * How far magnetic north lies from true north here — the declination, in
 * degrees east (positive) or west (negative).
 *
 * WHY (03.10.2026, walking through town: the compass „zeigt mir
 * manchmal leicht falsche Richtungen an"). A phone's compass points to
 * MAGNETIC north — Android's rotation vector, which the browser hands on as
 * deviceorientationabsolute, and iOS's webkitCompassHeading alike — while
 * the map's up is TRUE north. No browser corrects for the difference: it
 * does not know where the phone is. Around Wiesbaden it is 3.7 degrees
 * east in autumn 2026 (tests/declination.mjs) — small, but the same in
 * every direction, so it
 * tilts the cone off every street by the same amount. map.js adds it to
 * every compass reading once the location is known.
 *
 * THE WORLD MAGNETIC MODEL 2025 (NOAA / British Geological Survey, valid
 * 2025.0–2030.0), the model phones and aircraft use. Its coefficients
 * stand below as published in WMM.COF (December 2024; downloaded on
 * the word, 04.10.2026), public domain as a work of the US
 * government. The reckoning is the classic one of NOAA's geomag program —
 * geodetic to geocentric, Schmidt-normalised Legendre functions to degree
 * twelve, the field rotated back — and tests/declination.mjs holds it to
 * the model's own published test values. After 2030 the coefficients need
 * the next model (WMM2030); until then the secular variation in the last
 * two columns carries them forward year by year.
 *
 * WHAT IT DOES NOT MEND: a magnet at the phone (a magnetic charging plug,
 * a holder's plate) and an uncalibrated sensor. Those errors depend on the
 * direction one faces; the figure eight mends them, this does not.
 */

const EPOCH = 2025.0;
const MAX = 12;

/* n, m, g, h, g-dot, h-dot (nT and nT/year), one row per term — WMM.COF. */
const COF = [
    1,0,-29351.8,0,12,0,
    1,1,-1410.8,4545.4,9.7,-21.5,
    2,0,-2556.6,0,-11.6,0,
    2,1,2951.1,-3133.6,-5.2,-27.7,
    2,2,1649.3,-815.1,-8,-12.1,
    3,0,1361,0,-1.3,0,
    3,1,-2404.1,-56.6,-4.2,4,
    3,2,1243.8,237.5,0.4,-0.3,
    3,3,453.6,-549.5,-15.6,-4.1,
    4,0,895,0,-1.6,0,
    4,1,799.5,278.6,-2.4,-1.1,
    4,2,55.7,-133.9,-6,4.1,
    4,3,-281.1,212,5.6,1.6,
    4,4,12.1,-375.6,-7,-4.4,
    5,0,-233.2,0,0.6,0,
    5,1,368.9,45.4,1.4,-0.5,
    5,2,187.2,220.2,0,2.2,
    5,3,-138.7,-122.9,0.6,0.4,
    5,4,-142,43,2.2,1.7,
    5,5,20.9,106.1,0.9,1.9,
    6,0,64.4,0,-0.2,0,
    6,1,63.8,-18.4,-0.4,0.3,
    6,2,76.9,16.8,0.9,-1.6,
    6,3,-115.7,48.8,1.2,-0.4,
    6,4,-40.9,-59.8,-0.9,0.9,
    6,5,14.9,10.9,0.3,0.7,
    6,6,-60.7,72.7,0.9,0.9,
    7,0,79.5,0,-0,0,
    7,1,-77,-48.9,-0.1,0.6,
    7,2,-8.8,-14.4,-0.1,0.5,
    7,3,59.3,-1,0.5,-0.8,
    7,4,15.8,23.4,-0.1,0,
    7,5,2.5,-7.4,-0.8,-1,
    7,6,-11.1,-25.1,-0.8,0.6,
    7,7,14.2,-2.3,0.8,-0.2,
    8,0,23.2,0,-0.1,0,
    8,1,10.8,7.1,0.2,-0.2,
    8,2,-17.5,-12.6,0,0.5,
    8,3,2,11.4,0.5,-0.4,
    8,4,-21.7,-9.7,-0.1,0.4,
    8,5,16.9,12.7,0.3,-0.5,
    8,6,15,0.7,0.2,-0.6,
    8,7,-16.8,-5.2,-0,0.3,
    8,8,0.9,3.9,0.2,0.2,
    9,0,4.6,0,-0,0,
    9,1,7.8,-24.8,-0.1,-0.3,
    9,2,3,12.2,0.1,0.3,
    9,3,-0.2,8.3,0.3,-0.3,
    9,4,-2.5,-3.3,-0.3,0.3,
    9,5,-13.1,-5.2,0,0.2,
    9,6,2.4,7.2,0.3,-0.1,
    9,7,8.6,-0.6,-0.1,-0.2,
    9,8,-8.7,0.8,0.1,0.4,
    9,9,-12.9,10,-0.1,0.1,
    10,0,-1.3,0,0.1,0,
    10,1,-6.4,3.3,0,0,
    10,2,0.2,0,0.1,-0,
    10,3,2,2.4,0.1,-0.2,
    10,4,-1,5.3,-0,0.1,
    10,5,-0.6,-9.1,-0.3,-0.1,
    10,6,-0.9,0.4,0,0.1,
    10,7,1.5,-4.2,-0.1,0,
    10,8,0.9,-3.8,-0.1,-0.1,
    10,9,-2.7,0.9,-0,0.2,
    10,10,-3.9,-9.1,-0,-0,
    11,0,2.9,0,0,0,
    11,1,-1.5,0,-0,-0,
    11,2,-2.5,2.9,0,0.1,
    11,3,2.4,-0.6,0,-0,
    11,4,-0.6,0.2,0,0.1,
    11,5,-0.1,0.5,-0.1,-0,
    11,6,-0.6,-0.3,0,-0,
    11,7,-0.1,-1.2,-0,0.1,
    11,8,1.1,-1.7,-0.1,-0,
    11,9,-1,-2.9,-0.1,0,
    11,10,-0.2,-1.8,-0.1,0,
    11,11,2.6,-2.3,-0.1,0,
    12,0,-2,0,0,0,
    12,1,-0.2,-1.3,0,-0,
    12,2,0.3,0.7,-0,0,
    12,3,1.2,1,-0,-0.1,
    12,4,-1.3,-1.4,-0,0.1,
    12,5,0.6,-0,-0,-0,
    12,6,0.6,0.6,0.1,-0,
    12,7,0.5,-0.1,-0,-0,
    12,8,-0.1,0.8,0,0,
    12,9,-0.4,0.1,0,-0,
    12,10,-0.2,-1,-0.1,-0,
    12,11,-1.3,0.1,-0,0,
    12,12,-0.7,0.2,-0.1,-0.1,
];

/* The model's constants, prepared once: Schmidt-normalised coefficients and
   their yearly change, and the recursion factors of the Legendre functions.
   c[m][n] holds the g terms, c[n][m-1] the h terms — the packing of the
   original program, kept so the reckoning below reads like it. */
const c  = Array.from({ length: MAX + 1 }, () => new Float64Array(MAX + 1));
const cd = Array.from({ length: MAX + 1 }, () => new Float64Array(MAX + 1));
const k  = Array.from({ length: MAX + 1 }, () => new Float64Array(MAX + 1));
const fn = new Float64Array(MAX + 1);
const fm = new Float64Array(MAX + 1);
{
    const snorm = Array.from({ length: MAX + 1 }, () => new Float64Array(MAX + 1));
    for (let i = 0; i < COF.length; i += 6) {
        const [n, m, g, h, gd, hd] = COF.slice(i, i + 6);
        c[m][n] = g;
        cd[m][n] = gd;
        if (m !== 0) {
            c[n][m - 1] = h;
            cd[n][m - 1] = hd;
        }
    }
    snorm[0][0] = 1;
    for (let n = 1; n <= MAX; n++) {
        snorm[0][n] = snorm[0][n - 1] * (2 * n - 1) / n;
        let j = 2;
        for (let m = 0; m <= n; m++) {
            k[m][n] = ((n - 1) * (n - 1) - m * m) / ((2 * n - 1) * (2 * n - 3));
            if (m > 0) {
                const flnmj = ((n - m + 1) * j) / (n + m);
                snorm[m][n] = snorm[m - 1][n] * Math.sqrt(flnmj);
                j = 1;
                c[n][m - 1] *= snorm[m][n];
                cd[n][m - 1] *= snorm[m][n];
            }
            c[m][n] *= snorm[m][n];
            cd[m][n] *= snorm[m][n];
        }
        fn[n] = n + 1;
        fm[n] = n;
    }
    k[1][1] = 0;
}

/**
 * @param {number} lat     geodetic latitude, degrees
 * @param {number} lon     longitude, degrees east
 * @param {number} altKm   height above the ellipsoid, km (0 is close enough on foot)
 * @param {number} year    decimal year, e.g. 2026.75
 * @returns {number} declination, degrees east of true north
 */
export function declination(lat, lon, altKm = 0, year = decimalYear(new Date())) {
    const r0 = Math.PI / 180;
    const a = 6378.137, b = 6356.7523142, re = 6371.2;
    const a2 = a * a, b2 = b * b, c2 = a2 - b2, a4 = a2 * a2, b4 = b2 * b2, c4 = a4 - b4;
    const dt = year - EPOCH;

    const rlat = lat * r0, rlon = lon * r0;
    const srlat = Math.sin(rlat), crlat = Math.cos(rlat);
    const srlat2 = srlat * srlat, crlat2 = crlat * crlat;

    // Geodetic to geocentric: the model lives on a sphere, the map on the ellipsoid.
    const q  = Math.sqrt(a2 - c2 * srlat2);
    const q1 = altKm * q;
    const q2 = ((q1 + a2) / (q1 + b2)) ** 2;
    const ct = srlat / Math.sqrt(q2 * crlat2 + srlat2);
    const st = Math.sqrt(1 - ct * ct);
    const r  = Math.sqrt(altKm * altKm + 2 * q1 + (a4 - c4 * srlat2) / (q * q));
    const d  = Math.sqrt(a2 * crlat2 + b2 * srlat2);
    const ca = (altKm + d) / r;
    const sa = c2 * crlat * srlat / (r * d);

    const sp = new Float64Array(MAX + 1), cp = new Float64Array(MAX + 1);
    sp[0] = 0; cp[0] = 1;
    sp[1] = Math.sin(rlon); cp[1] = Math.cos(rlon);
    for (let m = 2; m <= MAX; m++) {
        sp[m] = sp[1] * cp[m - 1] + cp[1] * sp[m - 1];
        cp[m] = cp[1] * cp[m - 1] - sp[1] * sp[m - 1];
    }

    const p  = Array.from({ length: MAX + 1 }, () => new Float64Array(MAX + 1));
    const dp = Array.from({ length: MAX + 1 }, () => new Float64Array(MAX + 1));
    const pp = new Float64Array(MAX + 1);
    p[0][0] = 1; pp[0] = 1;

    const aor = re / r;
    let ar = aor * aor;
    let br = 0, bt = 0, bp = 0, bpp = 0;

    for (let n = 1; n <= MAX; n++) {
        ar *= aor;
        for (let m = 0; m <= n; m++) {
            // The Legendre functions and their derivative, by recursion.
            if (n === m) {
                p[m][n]  = st * p[m - 1][n - 1];
                dp[m][n] = st * dp[m - 1][n - 1] + ct * p[m - 1][n - 1];
            } else if (n === 1 && m === 0) {
                p[m][n]  = ct * p[m][n - 1];
                dp[m][n] = ct * dp[m][n - 1] - st * p[m][n - 1];
            } else {
                if (m > n - 2) { p[m][n - 2] = 0; dp[m][n - 2] = 0; }
                p[m][n]  = ct * p[m][n - 1] - k[m][n] * p[m][n - 2];
                dp[m][n] = ct * dp[m][n - 1] - st * p[m][n - 1] - k[m][n] * dp[m][n - 2];
            }
            // The coefficients carried to the year.
            const g = c[m][n] + dt * cd[m][n];
            const h = m === 0 ? 0 : c[n][m - 1] + dt * cd[n][m - 1];

            const par = ar * p[m][n];
            const temp1 = m === 0 ? g * cp[m] : g * cp[m] + h * sp[m];
            const temp2 = m === 0 ? g * sp[m] : g * sp[m] - h * cp[m];
            bt -= ar * temp1 * dp[m][n];
            bp += fm[m] * temp2 * par;
            br += fn[n] * temp1 * par;
            // At the geographic poles the east component needs its own series.
            if (st === 0 && m === 1) {
                pp[n] = n === 1 ? pp[n - 1] : ct * pp[n - 1] - k[m][n] * pp[n - 2];
                bpp += fm[m] * temp2 * ar * pp[n];
            }
        }
    }
    bp = st === 0 ? bpp : bp / st;

    // Back from the sphere to north and east on the ground.
    const bx = -bt * ca - br * sa;
    const by = bp;
    return Math.atan2(by, bx) / r0;
}

/** 2026.75 for the first of October 2026 — the model's clock. */
export function decimalYear(date) {
    const y = date.getUTCFullYear();
    const start = Date.UTC(y, 0, 1), end = Date.UTC(y + 1, 0, 1);
    return y + (date.getTime() - start) / (end - start);
}
