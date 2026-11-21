/**
 * Read coordinates — in whatever form they happen to arrive.
 *
 * WHAT FOR. A find spot rarely reaches you as a clean decimal number. It
 * stands in a message as degrees-minutes-seconds, on an old mapping sheet as
 * Gauß-Krüger, in the field book as UTM, in a report as a Messtischblatt,
 * or you simply get sent a map link. Whoever converts that by hand every
 * time gives up at some point and taps the spot by eye — and that is exactly
 * what this application does not exist for.
 *
 * Hence: one input, everything allowed, recognition comes by itself.
 *
 * WHAT IS READ, checked in this order:
 *
 *   Map link         …/@50.142920,8.340536,17z   also ?q= and /place/
 *   Messtischblatt   5817 · 5817/4 · 5817/433
 *   UTM              32U 476000 5553000
 *   Gauß-Krüger      3476000 5553000
 *   Deg/min/sec      50°08'34.5"N 8°20'25.9"E
 *   Deg/decimal min  50°08.575'N 8°20.432'E
 *   Decimal degrees  50.142920, 8.340536 · also with comma as separator
 *
 * THE ORDER IS NOT ARBITRARY. Almost every one of these forms consists of
 * numbers, and whoever checks first wins. "3476000 5553000" would be
 * nonsense as decimal degrees, correct as Gauß-Krüger; "5817" makes sense as
 * a map sheet and not as a coordinate. Checking therefore runs from the most
 * distinctive form to the most general.
 *
 * WHAT COMES BACK is always WGS84 — plus WHAT was recognized. That belongs
 * on display: whoever throws in "3476000 5553000" and is offered a point in
 * Hesse should see that the numbers were read as Gauß-Krüger. A silent
 * conversion that lands off the mark is worse than a follow-up question.
 */

import { t } from './i18n.js';

// ---------------------------------------------------------------------------
// Ellipsoids and datum transition
// ---------------------------------------------------------------------------

const WGS84  = { a: 6378137.0,    f: 1 / 298.257223563 };
const BESSEL = { a: 6377397.155,  f: 1 / 299.1528128   };

/**
 * The seven parameters from Potsdam to WGS84.
 *
 * The same numbers as in includes/mtb.php, there in the opposite direction.
 * They belong together: if one moves on without the other, a point's sheet
 * number computes differently from the search that found it.
 */
const POTSDAM = { dx: 598.1, dy: 73.7, dz: 418.2, rx: 0.202, ry: 0.045, rz: -2.455, ppm: 6.7 };

const toRadians = (b) => b * Math.PI / 180;
const toDegrees = (b) => b * 180 / Math.PI;

function toGeocentric(lat, lon, ell) {
    const e2 = ell.f * (2 - ell.f);
    const b = toRadians(lat), l = toRadians(lon);
    const n = ell.a / Math.sqrt(1 - e2 * Math.sin(b) ** 2);
    return [
        n * Math.cos(b) * Math.cos(l),
        n * Math.cos(b) * Math.sin(l),
        n * (1 - e2) * Math.sin(b),
    ];
}

function fromGeocentric(x, y, z, ell) {
    const e2 = ell.f * (2 - ell.f);
    const p = Math.hypot(x, y);
    let b = Math.atan2(z, p * (1 - e2));
    for (let i = 0; i < 5; i++) {
        const n = ell.a / Math.sqrt(1 - e2 * Math.sin(b) ** 2);
        const h = p / Math.cos(b) - n;
        b = Math.atan2(z, p * (1 - e2 * n / (n + h)));
    }
    return [toDegrees(b), toDegrees(Math.atan2(y, x))];
}

/** Potsdam/Bessel to WGS84. */
function potsdamToWgs(lat, lon) {
    const [x, y, z] = toGeocentric(lat, lon, BESSEL);
    const rx = toRadians(POTSDAM.rx / 3600), ry = toRadians(POTSDAM.ry / 3600), rz = toRadians(POTSDAM.rz / 3600);
    const m = 1 + POTSDAM.ppm * 1e-6;
    return fromGeocentric(
        m * (x - rz * y + ry * z) + POTSDAM.dx,
        m * (rz * x + y - rx * z) + POTSDAM.dy,
        m * (-ry * x + rx * y + z) + POTSDAM.dz,
        WGS84
    );
}

/**
 * Transverse Mercator backwards: from easting and northing back to degrees.
 *
 * A series expansion as found in every surveying formula collection. For
 * Germany it is accurate to centimeters — and a search that opens a map view
 * needs no more than that.
 */
function tmInverse(east, north, centralMeridian, scale, ellipsoid, falseEasting) {
    const { a, f } = ellipsoid;
    const e2 = f * (2 - f);
    const e_2 = e2 / (1 - e2);

    const x = east - falseEasting;
    const y = north / scale;

    const m = y;
    const mu = m / (a * (1 - e2 / 4 - 3 * e2 ** 2 / 64 - 5 * e2 ** 3 / 256));
    const e1 = (1 - Math.sqrt(1 - e2)) / (1 + Math.sqrt(1 - e2));

    const b1 = mu
        + (3 * e1 / 2 - 27 * e1 ** 3 / 32) * Math.sin(2 * mu)
        + (21 * e1 ** 2 / 16 - 55 * e1 ** 4 / 32) * Math.sin(4 * mu)
        + (151 * e1 ** 3 / 96) * Math.sin(6 * mu)
        + (1097 * e1 ** 4 / 512) * Math.sin(8 * mu);

    const c1 = e_2 * Math.cos(b1) ** 2;
    const t1 = Math.tan(b1) ** 2;
    const n1 = a / Math.sqrt(1 - e2 * Math.sin(b1) ** 2);
    const r1 = a * (1 - e2) / (1 - e2 * Math.sin(b1) ** 2) ** 1.5;
    const d = x / (n1 * scale);

    const lat = b1 - (n1 * Math.tan(b1) / r1) * (
        d ** 2 / 2
        - (5 + 3 * t1 + 10 * c1 - 4 * c1 ** 2 - 9 * e_2) * d ** 4 / 24
        + (61 + 90 * t1 + 298 * c1 + 45 * t1 ** 2 - 252 * e_2 - 3 * c1 ** 2) * d ** 6 / 720
    );
    const lon = (
        d
        - (1 + 2 * t1 + c1) * d ** 3 / 6
        + (5 - 2 * c1 + 28 * t1 - 3 * c1 ** 2 + 8 * e_2 + 24 * t1 ** 2) * d ** 5 / 120
    ) / Math.cos(b1);

    return [toDegrees(lat), centralMeridian + toDegrees(lon)];
}

// ---------------------------------------------------------------------------
// The individual forms
// ---------------------------------------------------------------------------

const IN_RANGE = (b, l) => Number.isFinite(b) && Number.isFinite(l)
    && Math.abs(b) <= 90 && Math.abs(l) <= 180;

/** …/@50.142920,8.340536,17z — and the other usual map links. */
function fromLink(text) {
    // 'raw', not 't' — the local text once sat in 't' and would shadow t() from i18n.js.
    const raw = text.trim();
    if (!/^https?:\/\/|maps\.|geo:/i.test(raw)) return null;

    const patterns = [
        /@(-?\d+\.\d+),\s*(-?\d+\.\d+)/,          // Google: /@lat,lon,zoom
        /[?&](?:q|ll|mlat|daddr)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/i,
        /geo:(-?\d+\.\d+),(-?\d+\.\d+)/i,
        /#map=\d+\/(-?\d+\.\d+)\/(-?\d+\.\d+)/,   // OpenStreetMap
    ];
    for (const m of patterns) {
        const t2 = raw.match(m);
        if (t2) {
            const b = parseFloat(t2[1]), l = parseFloat(t2[2]);
            if (IN_RANGE(b, l)) return { lat: b, lon: l, label: t('Kartenlink') };
        }
    }
    return null;
}

/**
 * Messtischblatt: 5817, 5817/4, 5817/433.
 *
 * What comes back is the CENTER of the cell, not its corner — whoever enters
 * a sheet wants to look there, and the center is the only spot that stands
 * for the whole cell. For a full sheet that is around 5 km of uncertainty;
 * that is not imprecision, it is the statement itself.
 */
function fromMtb(text) {
    const raw = text.trim().replace(/\s+/g, '');
    const m = raw.match(/^(\d{4})(?:[/\-.]?([1-4]{1,3}))?$/);
    if (!m) return null;

    const row = parseInt(m[1].slice(0, 2), 10);
    const column = parseInt(m[1].slice(2), 10);
    if (row < 1 || column < 1) return null;

    // The inverse of includes/mtb.php:
    //   row  = floor(560 - bp*10)   →  bp in ((559-row)/10 , (560-row)/10]
    //   column = floor(lp*6 - 34)     →  lp in [(column+34)/6 , (column+35)/6)
    let latBottom = (559 - row) / 10, latTop = (560 - row) / 10;
    let lonLeft = (column + 34) / 6,  lonRight = (column + 35) / 6;
    if (latTop < 45 || latTop > 57 || lonLeft < 4 || lonLeft > 17) return null;

    // Each digit quarters further: 1=NW 2=NE 3=SW 4=SE.
    for (const digit of (m[2] ?? '')) {
        const latMid = (latBottom + latTop) / 2;
        const lonMid = (lonLeft + lonRight) / 2;
        const north = digit === '1' || digit === '2';
        const east  = digit === '2' || digit === '4';
        if (north) latBottom = latMid; else latTop = latMid;
        if (east)  lonLeft = lonMid; else lonRight = lonMid;
    }

    const [lat, lon] = potsdamToWgs((latBottom + latTop) / 2, (lonLeft + lonRight) / 2);
    if (!IN_RANGE(lat, lon)) return null;

    const cellCenter = [t('Blattmitte'), t('Quadrantmitte'), t('Sechzehntelmitte'),
                        t('Vierundsechzigstelmitte')][(m[2] ?? '').length];
    return { lat, lon, label: t('Messtischblatt %s (%s)', m[1] + (m[2] ? '/' + m[2] : ''), cellCenter) };
}

/** UTM: 32U 476000 5553000 — zone with band letter or N/S. */
function fromUtm(text) {
    const raw = text.trim().toUpperCase().replace(/[,;]/g, ' ');
    const m = raw.match(/^(\d{1,2})\s*([C-HJ-NP-X])\s+(\d{5,7}(?:\.\d+)?)\s+(\d{6,8}(?:\.\d+)?)$/)
           || raw.match(/^(\d{1,2})\s*([NS])\s+(\d{5,7}(?:\.\d+)?)\s+(\d{6,8}(?:\.\d+)?)$/);
    if (!m) return null;

    const zone = parseInt(m[1], 10);
    if (zone < 1 || zone > 60) return null;
    const band = m[2];
    const east = parseFloat(m[3]), north = parseFloat(m[4]);

    // Band letters from 'N' on lie in the northern hemisphere; 'S' as a band
    // exists (South Africa), 'S' as a hemisphere too — which is why the band
    // letter comes before the hemisphere abbreviation in the list.
    const south = /^[C-M]$/.test(band) || band === 'S' && !/^[C-HJ-NP-X]$/.test(band);
    const northValue = south ? north - 10000000 : north;

    const [lat, lon] = tmInverse(east, northValue, zone * 6 - 183, 0.9996, WGS84, 500000);
    if (!IN_RANGE(lat, lon)) return null;
    return { lat, lon, label: t('UTM Zone %s%s', zone, band) };
}

/**
 * Gauß-Krüger: 3476000 5553000.
 *
 * The first digit of the easting is the zone — 3 means central meridian 9°,
 * 4 means 12°. That is also how the form is recognized: seven digits whose
 * first lies between 2 and 5, and a northing with seven digits.
 *
 * The result sits on Bessel/Potsdam and still has to go to WGS84 — otherwise
 * the point lands about a hundred meters off.
 */
function fromGaussKrueger(text) {
    const raw = text.trim().replace(/[,;]/g, ' ');
    const m = raw.match(/^([2-5])(\d{6}(?:\.\d+)?)\s+(\d{7}(?:\.\d+)?)$/);
    if (!m) return null;

    const zone = parseInt(m[1], 10);
    const east = parseFloat(m[1] + m[2]) - zone * 1000000;
    const north = parseFloat(m[3]);

    const [bp, lp] = tmInverse(east, north, zone * 3, 1.0, BESSEL, 500000);
    const [lat, lon] = potsdamToWgs(bp, lp);
    if (!IN_RANGE(lat, lon)) return null;
    return { lat, lon, label: t('Gauß-Krüger, Streifen %s (%s°)', zone, zone * 3) };
}

/**
 * Degrees, minutes, seconds — and everything in between.
 *
 * Covers three forms with one computation, because they differ only in how
 * many numbers stand per axis:
 *
 *   50°08'34.5"N     degrees, minutes, seconds
 *   50°08.575'N      degrees, decimal minutes
 *   50.1429°N        decimal degrees with compass direction
 */
function fromDegreesMinutes(text) {
    // "O" as in Ost means the same as "E" — in the German-speaking world it
    // stands on every second mapping sheet.
    const raw = text.trim().toUpperCase().replace(/\bO\b|O(?=\s*\d)|(?<=\d\s*)O/g, 'E');

    // Without a compass direction and without a degree sign it is no such
    // statement — otherwise this reader swallows every row of numbers.
    if (!/[NSEW]/.test(raw) && !/[°º′″]/.test(raw)) return null;

    // TOKENIZE INSTEAD OF BUILDING ONE BIG EXPRESSION. The forms differ only
    // in how many numbers stand per axis and where the letter sits — before
    // the number ("N 50 08.575") or after it ("50°08.575'N"). A single
    // pattern for everything became unreadable and missed; a breakdown into
    // tokens is longer and hits.
    const tokens = raw
        .replace(/[°º′″"'`]/g, ' ')
        .replace(/([NSEW])/g, ' $1 ')
        .split(/[\s,;]+/)
        .filter(Boolean);

    const groups = [];
    let openGroup = null;
    for (const token of tokens) {
        if (/^[NSEW]$/.test(token)) {
            if (openGroup && openGroup.numbers.length > 0) {
                openGroup.direction = openGroup.direction || token;   // stood after
                openGroup = null;
            } else {
                openGroup = { direction: token, numbers: [] };    // stands before
                groups.push(openGroup);
            }
            continue;
        }
        const number = parseFloat(token.replace(',', '.'));
        if (!Number.isFinite(number)) return null;
        if (!openGroup) {
            openGroup = { direction: '', numbers: [] };
            groups.push(openGroup);
        }
        openGroup.numbers.push(number);
    }

    if (groups.length !== 2) return null;

    // DO NOT CHECK WITH includes() whether a letter is present.
    //
    // 'SW'.includes('') is TRUE — every string contains the empty one. A
    // group without a compass direction thus counted as south AND as west:
    // the value got negated and the axes swapped. "N 50 08.575 E 8 20.432"
    // came out as -8.34 / 50.14, i.e. mirrored into the Atlantic. Measured,
    // not reasoned — in your head the line looks right.
    const isIn = (direction, set) => direction !== '' && set.includes(direction);

    const value = (g) => {
        const [d = 0, m = 0, s = 0] = g.numbers;
        const magnitude = Math.abs(d) + m / 60 + s / 3600;
        return (d < 0 || isIn(g.direction, 'SW')) ? -magnitude : magnitude;
    };

    let [a, b] = groups;
    // Whoever writes the longitude first should land correctly all the same.
    // The letters decide; if they are missing, the usual order applies.
    if (isIn(a.direction, 'EW') || isIn(b.direction, 'NS')) [a, b] = [b, a];

    const lat = value(a), lon = value(b);
    if (!IN_RANGE(lat, lon) || Math.abs(lat) > 90) return null;

    const depth = Math.max(a.numbers.length, b.numbers.length);
    return { lat, lon, label: ['', t('Dezimalgrad'), t('Grad/Dezimalminuten'), t('Grad/Minuten/Sekunden')][depth] || t('Gradangabe') };
}

// ---------------------------------------------------------------------------
// Plus Code (Open Location Code)
//
// This is the short code Google Maps displays under "Plus Code": 9F2C8G4C+9M.
// It describes not a point but a rectangle — at full length around 14 × 14
// meters, with one more digit finer still. What comes back is the center.
//
// THE SHORT FORM IS THE ACTUAL CASE. Google mostly shows "8G4C+9M
// Frankfurt": the first four characters are omitted, and a place name stands
// next to it instead. Without a reference point such a code cannot be
// resolved — it repeats every 100 kilometers.
//
// The place name we could only resolve through a third-party service. That
// would be a request to the outside carrying what someone is searching for
// right now — with find spots, the very piece of information this
// application keeps to itself most carefully. So we take the map view as
// reference instead: what you are looking at is almost always the right
// region, and if not, the answer says so ("aus dem Kartenausschnitt
// ergänzt").
// ---------------------------------------------------------------------------

const OLC_CHARS = '23456789CFGHJMPQRVWX';
const OLC_SEPARATOR = 8;          // position of the '+'
const OLC_GRID_ROWS = 5;
const OLC_GRID_COLS = 4;

/** Turn a location into a full code — for completing short codes. */
function writeOlc(lat, lon, digits = 10) {
    let b = Math.min(Math.max(lat, -90), 89.999999) + 90;
    let l = ((lon + 180) % 360 + 360) % 360;

    let code = '';
    let resolution = 20;
    for (let i = 0; i < 5; i++) {
        code += OLC_CHARS[Math.floor(b / resolution) % 20];
        code += OLC_CHARS[Math.floor(l / resolution) % 20];
        b %= resolution; l %= resolution;
        resolution /= 20;
    }
    return code.slice(0, digits);
}

/**
 * Resolve a Plus Code.
 *
 * @param {string} code   full or short form
 * @param {{lat:number, lon:number}|null} reference  for the short form
 */
function readOlc(code, reference) {
    const raw = code.toUpperCase();
    const plus = raw.indexOf('+');
    if (plus < 0) return null;

    const front = raw.slice(0, plus);
    const back = raw.slice(plus + 1);
    if (!/^[23456789CFGHJMPQRVWX]*0*$/.test(front) || !/^[23456789CFGHJMPQRVWX]*$/.test(back)) return null;
    if (front.length % 2 !== 0 || front.length > 8) return null;
    if (back.length > 0 && back.length < 2) return null;

    let full = front + back;
    let completed = false;

    // SHORT FORM: 2, 4 or 6 characters are missing in front. They come from
    // the reference.
    if (front.length < OLC_SEPARATOR) {
        if (!reference) return null;
        const missing = OLC_SEPARATOR - front.length;
        full = writeOlc(reference.lat, reference.lon, missing) + front + back;
        completed = true;
    }

    const clean = full.replace(/0+$/, '');
    if (clean.length < 2) return null;

    let latBottom = -90, lonLeft = -180;
    let resolution = 20, cellLat = 20, cellLon = 20;

    for (let i = 0; i + 1 < clean.length && i < 10; i += 2) {
        const latIdx = OLC_CHARS.indexOf(clean[i]);
        const lonIdx = OLC_CHARS.indexOf(clean[i + 1]);
        if (latIdx < 0 || lonIdx < 0) return null;
        latBottom += latIdx * resolution;
        lonLeft += lonIdx * resolution;
        cellLat = cellLon = resolution;
        resolution /= 20;
    }

    // From the eleventh digit a 5×4 grid refines instead of a pair.
    for (let j = 10; j < Math.min(clean.length, 15); j++) {
        const idx = OLC_CHARS.indexOf(clean[j]);
        if (idx < 0) return null;
        cellLat /= OLC_GRID_ROWS;
        cellLon /= OLC_GRID_COLS;
        latBottom += Math.floor(idx / OLC_GRID_COLS) * cellLat;
        lonLeft += (idx % OLC_GRID_COLS) * cellLon;
    }

    const lat = latBottom + cellLat / 2;
    const lon = lonLeft + cellLon / 2;
    if (!IN_RANGE(lat, lon)) return null;

    // How large the rectangle is belongs with it — at eight digits it is
    // 275 meters, and that is no longer a find-spot statement.
    const meters = Math.round(cellLat * 111320);
    const accuracy = meters >= 1000 ? Math.round(meters / 100) / 10 + ' km' : meters + ' m';

    return {
        lat, lon,
        label: t('Plus Code %s+%s (%s)', full.slice(0, 8), full.slice(8), accuracy),
        // Whether the reference was guessed, the interface decides: it can
        // look up the place name and ask once more. Here it only says THAT a
        // guess was made — this reader computes and does not go online.
        completed,
    };
}

function fromPlusCode(text, reference) {
    const raw = text.trim();
    const m = raw.match(/([23456789CFGHJMPQRVWX]{2,8}0*\+[23456789CFGHJMPQRVWX]{0,7})/i);
    if (!m) return null;

    const hit = readOlc(m[1], reference);
    if (!hit) return null;

    // WHAT STANDS AFTER THE CODE is, with Google, the place name: "8G4C+9M
    // Frankfurt". It is not resolved here — that would be a request to the
    // outside, and that has no place in a reader that must also run in a
    // dead zone. It is passed along; whoever wants to can look it up.
    const rest = (raw.slice(0, m.index) + ' ' + raw.slice(m.index + m[1].length))
        .replace(/[,;]/g, ' ').trim();

    return { ...hit, place: rest.length >= 2 ? rest : null };
}

/** Decimal degrees, the plain case — last, because it devours the most. */
function fromDecimal(text) {
    const numbers = String(text).match(/-?\d+(?:[.,]\d+)?/g);
    if (!numbers || numbers.length < 2) return null;

    const lat = parseFloat(numbers[0].replace(',', '.'));
    const lon = parseFloat(numbers[1].replace(',', '.'));

    // AT LEAST ONE DECIMAL PLACE PER NUMBER. Without this hurdle every
    // enumeration ("Buche 51, Weg 7") becomes a find spot.
    if (!/[.,]\d/.test(numbers[0]) || !/[.,]\d/.test(numbers[1])) return null;
    if (!IN_RANGE(lat, lon)) return null;
    return { lat, lon, label: t('Dezimalgrad') };
}

// ---------------------------------------------------------------------------

/**
 * Whatever stands there, as WGS84 — or null.
 *
 * @param {string} text
 * @param {{lat:number, lon:number}|null} reference  For short Plus Codes: the
 *        region they refer to. Usually the map center.
 * @returns {{lat:number, lon:number, label:string}|null}
 */
export function recognizeCoordinates(text, reference = null) {
    if (typeof text !== 'string' || text.trim() === '') return null;

    // BEFORE fromDecimal, but AFTER the distinctive forms: a Plus Code
    // contains a '+' that none of the other forms knows — so it can be
    // recognized early without taking anything from the others.
    for (const reader of [fromLink, fromPlusCode, fromMtb, fromUtm, fromGaussKrueger, fromDegreesMinutes, fromDecimal]) {
        try {
            const hit = reader(text, reference);
            if (hit) {
                return {
                    lat: Math.round(hit.lat * 1e6) / 1e6,
                    lon: Math.round(hit.lon * 1e6) / 1e6,
                    label: hit.label,
                    ...(hit.completed ? { completed: true } : {}),
                    ...(hit.place ? { place: hit.place } : {}),
                };
            }
        } catch {
            // A reader that fails on a mangled input must not take the
            // others down with it.
        }
    }
    return null;
}
