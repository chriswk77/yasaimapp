/**
 * Test of the EXIF reader.
 *
 *   node tests/exif.mjs
 *
 * WHY THIS NEEDS A TEST OF ITS OWN. assets/js/exif.js reads the capture
 * data from the bytes by hand: byte order, directory pointers, field types,
 * fractions of numerator and denominator. Such code looks right and is
 * still off — a pointer relative to the start of the file instead of the
 * TIFF header, and out come coordinates that seem plausible and point into
 * the neighbouring district.
 *
 * Measurement is therefore not against my expectation but against a foreign
 * implementation: tests/exifimages.php builds the images and lets
 * PHP's exif_read_data() say what is in them. Both must read the same.
 *
 * AND THE CASE THAT COUNTS IN THE FOREST: an image with no EXIF at all.
 * That is the normal case, not the exception — many cameras write no
 * coordinates, and whoever switches location off, all the more. It must
 * cost nothing and abort nothing.
 */

import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const exif = await import('../assets/js/exif.js');

let failed = 0;
function check(name, condition, seen = '') {
    if (condition) {
        console.log('  ok    ' + name);
    } else {
        console.log('  FAILED ' + name + (seen ? '  ->  ' + seen : ''));
        failed++;
    }
}

const directory = mkdtempSync(join(tmpdir(), 'exiftest-'));

let reference;
try {
    reference = JSON.parse(execFileSync('php', [join(here, 'exifimages.php'), directory], { encoding: 'utf8' }));
} catch (e) {
    console.error('The test images cannot be created:', e.message);
    process.exit(1);
}

/** A file as an ArrayBuffer, the way it would arrive in the browser. */
function load(file) {
    const raw = readFileSync(join(directory, file));
    return raw.buffer.slice(raw.byteOffset, raw.byteOffset + raw.byteLength);
}

console.log('\nEXIF reader\n');

for (const [name, expected] of Object.entries(reference)) {
    if (name === 'none') continue;

    console.log(`Image "${name}" (${name === 'small' ? 'Intel' : 'Motorola'} byte order)`);
    const buffer = load(expected.file);
    const parsed = exif.read(buffer);

    // PHP writes the date with colons, as the standard prescribes; we
    // return it in database format. The same value, different separation.
    const expectedTime = expected.time.replace(/^(\d{4}):(\d{2}):(\d{2})/, '$1-$2-$3');
    check('capture time as PHP', parsed.taken_at === expectedTime,
        `${parsed.taken_at} instead of ${expectedTime}`);

    check('latitude as PHP', parsed.lat === expected.lat,
        `${parsed.lat} instead of ${expected.lat}`);
    check('longitude as PHP', parsed.lon === expected.lon,
        `${parsed.lon} instead of ${expected.lon}`);
    check('orientation as PHP', parsed.orientation === expected.orientation,
        `${parsed.orientation} instead of ${expected.orientation}`);

    // The camera name is assembled from maker and model — and the maker is
    // not named twice when the model already contains it.
    const expectedCamera = expected.model.toLowerCase().startsWith(expected.make.toLowerCase())
        ? expected.model
        : `${expected.make} ${expected.model}`;
    check('camera as PHP', parsed.camera === expectedCamera,
        `${parsed.camera} instead of ${expectedCamera}`);

    const m = exif.dimensions(buffer);
    check('dimensions from the file header', m?.width === 640 && m?.height === 480,
        JSON.stringify(m));
    console.log('');
}

console.log('An image without EXIF');
{
    const buffer = load(reference.none.file);
    const parsed = exif.read(buffer);
    check('no capture time', parsed.taken_at === null);
    check('no camera',       parsed.camera === null);
    check('no coordinates',  parsed.lat === null && parsed.lon === null);
    check('orientation is 1', parsed.orientation === 1);

    const m = exif.dimensions(buffer);
    check('the dimensions still come', m?.width === 800 && m?.height === 600, JSON.stringify(m));
    check('no exposure data either (shot is null)', exif.shot(buffer) === null);
}

/* HOW IT WAS TAKEN (shot(), the info tab of „Bearbeiten", 10.11.2026). The
   test images carry no exposure tags, so here shot() must find an EXIF
   block and still invent nothing. The values themselves were measured once
   against exiftool on three real camera files (Olympus E-M5 II with two
   lenses, Xiaomi 15): f-number, time, ISO, focal length, 35-mm focal
   length, compensation, program, metering and lens alike. */
console.log('\nExposure data (shot)');
for (const [name, expected] of Object.entries(reference)) {
    if (name === 'none') continue;
    const s = exif.shot(load(expected.file));
    check(`"${name}": an EXIF block is found`, s !== null);
    check(`"${name}": no aperture, time or ISO invented`,
        s?.fnumber === null && s?.exposure === null && s?.iso === null && s?.lens === null, JSON.stringify(s));
}

console.log('\nBroken and foreign material');
{
    // NONE OF THIS MAY THROW. A photo without readable EXIF should arrive
    // without a date — not not at all.
    const cases = {
        'Empty buffer':          new ArrayBuffer(0),
        'Two bytes':             new Uint8Array([0xFF, 0xD8]).buffer,
        'Not a JPEG':            new Uint8Array([0x89, 0x50, 0x4E, 0x47, 0, 0, 0, 0]).buffer,
        'Truncated APP1':        new Uint8Array([0xFF, 0xD8, 0xFF, 0xE1, 0x00, 0x40, 0x45, 0x78, 0x69, 0x66]).buffer,
        'Zero-length segment':   new Uint8Array([0xFF, 0xD8, 0xFF, 0xE1, 0x00, 0x00, 0xFF, 0xE1, 0x00, 0x00]).buffer,
    };

    for (const [name, buffer] of Object.entries(cases)) {
        let threw = false;
        let result = null;
        try {
            result = exif.read(buffer);
            exif.dimensions(buffer);
            exif.shot(buffer);
        } catch {
            threw = true;
        }
        check(name + ' does not throw', !threw);
        check(name + ' yields empty data', result?.taken_at === null && result?.lat === null);
    }

    // A pointer that points out of the file. The case a reader without a
    // length check answers with a crash — or, worse, with invented values
    // from neighbouring memory.
    const malicious = new Uint8Array(64);
    malicious.set([0xFF, 0xD8, 0xFF, 0xE1, 0x00, 0x3A, 0x45, 0x78, 0x69, 0x66, 0x00, 0x00], 0);
    malicious.set([0x49, 0x49, 0x2A, 0x00], 12);           // "II", 0x002A
    malicious.set([0xFF, 0xFF, 0xFF, 0x7F], 16);           // pointer far beyond the end of the file
    let intact = true;
    try { exif.read(malicious.buffer); exif.shot(malicious.buffer); } catch { intact = false; }
    check('A pointer past the end of the file does not throw', intact);
}

/* THE QUARTER TURNS — the whole of the bug of 24.08.2026 in six lines.
   A phone's portrait shot lies in the file as a LANDSCAPE frame plus
   orientation 6; whoever hands those raw numbers to the downscale gets
   two fixed edges of the wrong ratio, and the picture arrives squashed.
   1 and 3 leave the pair alone (upright and upside down), 5 to 8 swap
   it — and null stays null, because a JPEG whose frame could not be read
   must not silently become a square. */
{
    const raw = { width: 4000, height: 3000 };
    const upright = exif.orientedSize(raw, 1);
    check('an upright picture keeps its pair',
        upright.width === 4000 && upright.height === 3000, JSON.stringify(upright));
    check('upside down likewise (3)',
        exif.orientedSize(raw, 3).width === 4000);
    for (const turn of [5, 6, 7, 8]) {
        const turned = exif.orientedSize(raw, turn);
        check(`orientation ${turn} swaps width and height`,
            turned.width === 3000 && turned.height === 4000, JSON.stringify(turned));
    }
    check('a missing frame stays missing', exif.orientedSize(null, 6) === null);
}

rmSync(directory, { recursive: true, force: true });

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
