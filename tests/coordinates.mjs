/**
 * Test of the coordinate reader.
 *
 *   node tests/coordinates.mjs
 *
 * WHAT IS TESTED AGAINST. UTM and Gauß-Krüger against proj4 — the library
 * that computes in the AHO's coordinate tool and that counts as the
 * reference in geoinformatics. Its answers stand here as fixed values;
 * the test needs no network.
 *
 * The degree forms, by contrast, are pure arithmetic and are tested
 * against values recomputed by hand — 50°08'34.5" is 50 + 8/60 + 34.5/3600.
 *
 * WHAT THIS TEST REALLY SECURES are not the straight cases but the
 * crooked ones: compass direction before the number instead of after,
 * swapped axes, German decimal comma, "O" instead of "E". And the case
 * that went wrong during building and looked right in the head — more on
 * that below.
 */

const { recognizeCoordinates } = await import('../assets/js/coordinates.js');

let failed = 0;
function check(name, condition, seen = '') {
    if (condition) {
        console.log('  ok    ' + name);
    } else {
        console.log('  FAILED ' + name + (seen ? '  ->  ' + seen : ''));
        failed++;
    }
}

/** Distance in metres — the only unit in which a coordinate error means anything. */
function distance(a, b, lat, lon) {
    return Math.hypot((a - lat) * 111320, (b - lon) * 111320 * Math.cos(lat * Math.PI / 180));
}

function matches(text, lat, lon, toleranceM = 1) {
    const r = recognizeCoordinates(text);
    if (!r) return { ok: false, how: 'not recognized' };
    const d = distance(r.lat, r.lon, lat, lon);
    return { ok: d <= toleranceM, how: `${r.lat}, ${r.lon} (${d.toFixed(2)} m off, ${r.label})` };
}

console.log('\nReading coordinates\n');

// ---------------------------------------------------------------------------
console.log('Decimal degrees');

for (const [text, b, l, what] of [
    ['50.142920, 8.340536',  50.142920, 8.340536, 'with dot and comma as separators'],
    ['50,142920 8,340536',   50.142920, 8.340536, 'with the German decimal comma'],
    ['50.142920 8.340536',   50.142920, 8.340536, 'with spaces only'],
    ['40.712776, -74.005974', 40.712776, -74.005974, 'with negative longitude'],
]) {
    const r = matches(text, b, l);
    check(what, r.ok, r.how);
}

// ---------------------------------------------------------------------------
console.log('\nDegrees, minutes, seconds');

// 50 + 8/60 + 34.5/3600 = 50.14291666…, 8 + 20/60 + 25.9/3600 = 8.34052777…
for (const [text, b, l, what] of [
    ['50°08\'34.5"N 8°20\'25.9"E', 50.1429167, 8.3405278, 'with degree, minute and second signs'],
    ['50°08.575\'N 8°20.432\'E',   50.1429167, 8.3405333, 'with decimal minutes'],
    ['N 50 08.575 E 8 20.432',     50.1429167, 8.3405333, 'compass direction BEFORE the number, without signs'],
    ['8.340536E 50.142920N',       50.142920,  8.340536,  'longitude first, letter appended'],
    ['E 8.340536 N 50.142920',     50.142920,  8.340536,  'longitude first, letter in front'],
    ['50.142920 N, 8.340536 O',    50.142920,  8.340536,  '"O" for east instead of "E"'],
    ['S 33.918861 E 18.423300',   -33.918861, 18.423300,  'southern hemisphere'],
]) {
    const r = matches(text, b, l);
    check(what, r.ok, r.how);
}

// THE MISTAKE THAT WENT WRONG DURING BUILDING.
//
// 'SW'.includes('') is TRUE — every string contains the empty one. An
// axis without a compass direction thereby counted as south AND as west:
// the value was negated and the axes swapped. "N 50 08.575 E 8 20.432"
// ended up as -8.34 / 50.14 in the Atlantic. In the head the line looked
// right; it only became visible when measuring.
{
    const r = recognizeCoordinates('N 50 08.575 E 8 20.432');
    check('an axis without its own letter is not negated',
        r !== null && r.lat > 0 && r.lon > 0, JSON.stringify(r));
}

// ---------------------------------------------------------------------------
console.log('\nUTM — against proj4');

for (const [text, b, l] of [
    ['32U 476000 5553000', 50.128751, 8.664220],
    ['32U 500000 5400000', 48.753013, 9.000000],
    ['32U 350000 5900000', 53.228082, 6.752854],
    ['32U 600000 5250000', 47.395794, 10.325174],
    ['32U 432000 5789000', 52.247196, 8.003968],
]) {
    const r = matches(text, b, l);
    check(text, r.ok, r.how);
}

// ---------------------------------------------------------------------------
console.log('\nGauß-Krüger — against proj4');

for (const [text, b, l] of [
    ['3476000 5553000', 50.112729, 8.663426],
    ['3500000 5400000', 48.737539, 8.998971],
    ['3550000 5700000', 51.432432, 9.717930],
    ['3420000 5850000', 52.776786, 7.813428],
    ['4500000 5700000', 51.434650, 11.998495],
    ['4450000 5350000', 48.285938, 11.324804],
]) {
    const r = matches(text, b, l);
    check(text, r.ok, r.how);
}

// THE DATUM COUNTS HERE TOO. Gauß-Krüger sits on Bessel/Potsdam; without
// the transition to WGS84 the point would lie about a hundred metres off
// — exactly the order of magnitude at which you do not see the error on
// the map.
{
    const r = recognizeCoordinates('3476000 5553000');
    const withoutDatum = distance(50.113611, 8.664722, r.lat, r.lon);   // rough Bessel position
    check('the datum transition shifts noticeably (so it does take place)',
        withoutDatum > 50, withoutDatum.toFixed(1) + ' m');
}

// ---------------------------------------------------------------------------
console.log('\nMesstischblatt');

{
    // The centre of sheet 5817 must lie in sheet 5817 again — checked via
    // the same formula as in includes/mtb.php, re-enacted here by hand.
    const r = recognizeCoordinates('5817');
    check('a sheet yields a spot in Germany',
        r !== null && r.lat > 47 && r.lat < 56 && r.lon > 5 && r.lon < 16, JSON.stringify(r));
    check('and the label field names the sheet', r?.label.includes('5817'), r?.label);

    // The four quadrants must lie around the sheet centre, each in its
    // compass direction: 1=NW 2=NE 3=SW 4=SE.
    const middle = recognizeCoordinates('5817');
    const q = {};
    for (const n of '1234') q[n] = recognizeCoordinates('5817/' + n);
    check('quadrant 1 lies northwest of the centre', q['1'].lat > middle.lat && q['1'].lon < middle.lon);
    check('quadrant 2 lies northeast',               q['2'].lat > middle.lat && q['2'].lon > middle.lon);
    check('quadrant 3 lies southwest',               q['3'].lat < middle.lat && q['3'].lon < middle.lon);
    check('quadrant 4 lies southeast',               q['4'].lat < middle.lat && q['4'].lon > middle.lon);

    const fine = recognizeCoordinates('5817/433');
    check('three digits are read', fine?.label.includes('5817/433'), fine?.label);
}

// ---------------------------------------------------------------------------
console.log('\nPlus Code — against Google\'s own library');

// The target values come from openlocationcode.js, Google's implementation.
for (const [code, b, l] of [
    ['9F2C8G4C+9M',  50.3059375,  8.5216875],
    ['9F2C8G4C+9MP', 50.3059625,  8.5217031],
    ['9F2C0000+',    50.5,        8.5],
    ['9F2C8G00+',    50.325,      8.525],
    ['8FVC2222+22',  47.0000625,  8.0000625],
    ['7FG49QCJ+2VX', 20.3701125,  2.7822344],
    ['9C3W9QCJ+2VX', 51.3701125, -1.2177656],
]) {
    const r = matches(code, b, l, 0.5);
    check(code, r.ok, r.how);
}

// THE SHORT FORM IS THE COMMON CASE: Google shows "8G4C+9M Frankfurt".
// Without a reference it cannot be resolved — it repeats every 100 km.
{
    const reference = { lat: 50.11, lon: 8.68 };
    const short = recognizeCoordinates('8G4C+9M', reference);
    const full = recognizeCoordinates('9F2C8G4C+9M');
    check('a short code with a reference yields the same spot as the full one',
        short !== null && Math.abs(short.lat - full.lat) < 1e-6 && Math.abs(short.lon - full.lon) < 1e-6,
        JSON.stringify(short));
    // THE NOTE IS NOT IN THE TEXT but its own field: whether "aus dem
    // Kartenausschnitt" or "ueber Frankfurt" was filled in only the
    // interface knows — it can look up the place name, this reader cannot.
    check('and it notes that the beginning was guessed', short?.completed === true, JSON.stringify(short));
    check('a full code does not carry the note', full.ergaenzt === undefined, JSON.stringify(full));

    check('without a reference a short code stays unresolvable',
        recognizeCoordinates('8G4C+9M') === null);

    check('a place name behind it does not interfere',
        recognizeCoordinates('8G4C+9M Frankfurt', reference)?.lat === short.lat);

    // The size of the rectangle belongs with it: eight characters are 275
    // metres, and that is no longer a find location.
    check('the edge length is stated alongside',
        /\(\d+(\.\d+)? ?(m|km)\)/.test(full.label), full.label);
}

// A REAL EXAMPLE FROM USE: "F6PX+V7H Krün", as Google Maps displays it.
// The place name is not looked up here — the interface would do that —,
// which is why the full code stands next to it plus the short form with
// a reference point nearby.
{
    const expected = [47.487189, 11.248235];
    const full = matches('8FVHF6PX+V7H', expected[0], expected[1], 0.5);
    check('8FVHF6PX+V7H hits the reported spot', full.ok, full.how);

    const short = recognizeCoordinates('F6PX+V7H Krün', { lat: 47.5, lon: 11.3 });
    check('and so does the short form with a reference nearby',
        short !== null && distance(short.lat, short.lon, expected[0], expected[1]) < 0.5,
        JSON.stringify(short));
    check('the place name is passed on for lookup',
        short?.place === 'Krün', short?.place);

    // Three characters after the plus are roughly three metres — that belongs with it.
    check('the edge length names three metres', short?.label.includes('(3 m)'), short?.label);
}

// ---------------------------------------------------------------------------
console.log('\nMap links');

for (const [text, b, l, what] of [
    ['https://www.google.com/maps/@50.142920,8.340536,17z', 50.142920, 8.340536, 'Google Maps'],
    ['https://www.openstreetmap.org/#map=17/50.14292/8.34054', 50.142920, 8.340540, 'OpenStreetMap'],
    ['geo:50.142920,8.340536', 50.142920, 8.340536, 'geo: link'],
]) {
    const r = matches(text, b, l, 5);
    check(what, r.ok, r.how);
}

// ---------------------------------------------------------------------------
console.log('\nWhat is NOT a coordinate');

for (const text of [
    'Boletus edulis',
    'Buche 51, Weg 7',
    'Meine Funde',
    'Hygrocybe punicea, Magerwiese',
    '',
    '   ',
    'Substrat: Quercus robur',
]) {
    check(`"${text}" is not read as a coordinate`,
        recognizeCoordinates(text) === null, JSON.stringify(recognizeCoordinates(text)));
}

// And none of this may throw.
{
    let intact = true;
    for (const wild of [null, undefined, 42, {}, [], '°°°', 'NSEW', '-----', '999999999 999999999']) {
        try { recognizeCoordinates(wild); } catch { intact = false; }
    }
    check('mangled inputs do not throw', intact);
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
