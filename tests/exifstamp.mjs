/**
 * Tests the note a shrunk picture carries (assets/js/exifstamp.js).
 *
 *   node tests/exifstamp.mjs
 *
 * WHAT THE FILE SAYS IS WHAT COUNTS, not what this module thinks it wrote.
 * So the stamped bytes are read three ways: by a small reader here, by the
 * project's own exif.js (which reads the voucher photos), and — where it is
 * installed — by exiftool, the tool everybody else trusts. The picture under
 * test is a real canvas JPEG, exactly as Chrome writes it when shrinking:
 * start, JFIF head, colour profile, picture data.
 */

import { stampJpeg, stampWebp, noteText, readCamera, cameraOf, shotOf, jpegProfile } from '../assets/js/exifstamp.js';
import * as exif from '../assets/js/exif.js';
import { spawnSync } from 'node:child_process';
import { writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

let failed = 0;
function check(b, what) {
    if (b) { console.log(`  ok    ${what}`); } else { failed++; console.log(`  FAILED: ${what}`); }
}

// An 8 × 6 canvas JPEG from Chrome (canvas.toBlob, quality 0.9).
const CANVAS_JPEG = Uint8Array.from(Buffer.from(
    '/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAAChnVFJDAAABZAAAAChiVFJDAAABZAAAAChjcHJ0AAABjAAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAAgAAAAcAHMAUgBHAEJYWVogAAAAAAAAb6IAADj1AAADkFhZWiAAAAAAAABimQAAt4UAABjaWFlaIAAAAAAAACSgAAAPhAAAts9YWVogAAAAAAAA9tYAAQAAAADTLXBhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAACAAAAAcAEcAbwBvAGcAbABlACAASQBuAGMALgAgADIAMAAxADb/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBT/wAARCAAGAAgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAkEAABAQQLAAAAAAAAAAAAAAAAEgMGFTERFBYXIiYyM1NVYf/EABUBAQEAAAAAAAAAAAAAAAAAAAUH/8QAGxEAAgEFAAAAAAAAAAAAAAAAAAFxAgMREkH/2gAMAwEAAhEDEQA/AK+1zrXUQOC5m7Kqsude5SvRhl5IAEmr5AReedYR/9k=',
    'base64'));

const when = new Date(2026, 8, 26, 23, 41, 7);
const info = {
    from: { w: 6000, h: 4000 }, to: { w: 2048, h: 1365 },
    originalName: 'DSC_0417.JPG', when,
    make: 'Canon', model: 'Canon EOS R6', taken: '2026:09:20 14:03:11',
};
const NOTE = 'Verkleinert von 6000 × 4000 auf 2048 × 1365 Pixel (Faktor 2,93) durch YASAIMAPP am 26.09.2026. Originaldatei: DSC_0417.JPG.';

console.log('\nThe note in a shrunk picture\n');

console.log('The sentence');
check(noteText(info) === NOTE, 'says size before and after, the factor, the day and the original file — in German without a catalog');
check(noteText({ ...info, from: { w: 800, h: 600 }, to: { w: 800, h: 600 } })
    === 'Neu gespeichert durch YASAIMAPP am 26.09.2026, ohne Verkleinerung (800 × 600 Pixel). Originaldatei: DSC_0417.JPG.',
    'a picture small enough already is not said to be shrunk by the factor 1');

console.log('Where it goes');
const stamped = stampJpeg(CANVAS_JPEG, info);
const segments = markers(stamped);
check(segments.slice(0, 5).join(' ') === 'd8 e0 e1 e1 e2',
    `after the start and the JFIF head come EXIF and XMP, then the colour profile (${segments.slice(0, 5).join(' ')})`);
check(label(stamped, segments, 2) === 'Exif' && label(stamped, segments, 3).startsWith('http://ns.adobe.com/xap/1.0/'),
    'the first APP1 is EXIF, the second XMP');
check(Buffer.from(stamped.subarray(stamped.length - (CANVAS_JPEG.length - 20))).equals(Buffer.from(CANVAS_JPEG.subarray(20))),
    'everything after the JFIF head is the canvas\'s own, byte for byte');

console.log('What the EXIF says (read here)');
const tags = readExif(stamped);
check(tags.description === NOTE, 'ImageDescription carries the note, umlauts and all');
check(tags.comment === NOTE, 'UserComment carries it in Unicode');
check(tags.xpComment === NOTE, 'XPComment carries it for the Windows Explorer');
check(tags.make === 'Canon' && tags.model === 'Canon EOS R6', 'camera maker and model come back');
check(tags.taken === '2026:09:20 14:03:11', 'the moment of the shot comes back');
check(tags.orientation === 1, 'the orientation says normal — the picture is turned already');
check(tags.software === 'YASAIMAPP' && tags.changed === '2026:09:26 23:41:07', 'the software and the moment of the shrinking');
check(tags.width === 2048 && tags.height === 1365, 'the new pixel size');

console.log('What exif.js says — the voucher photos\' own reader');
const own = exif.read(stamped.buffer.slice(stamped.byteOffset, stamped.byteOffset + stamped.byteLength));
check(own.make === 'Canon' && own.model === 'Canon EOS R6' && own.camera === 'Canon EOS R6',
    'maker and model, and camera without the maker twice');
check(own.taken_at === '2026-09-20 14:03:11' && own.orientation === 1, 'time and orientation');

console.log('What XMP says');
const xmp = Buffer.from(stamped).toString('utf8');
check(xmp.includes('yasaimapp:OriginalWidth="6000"') && xmp.includes('yasaimapp:OriginalHeight="4000"')
    && xmp.includes('yasaimapp:ShrinkFactor="2.9297"'), 'original size and factor as numbers a program can use');
check(xmp.includes(`<rdf:li xml:lang="x-default">${NOTE}</rdf:li>`), 'dc:description carries the note');
const odd = stampJpeg(CANVAS_JPEG, { ...info, originalName: 'Probe <1> & "2".jpg' });
check(Buffer.from(odd).toString('utf8').includes('yasaimapp:OriginalName="Probe &lt;1&gt; &amp; &quot;2&quot;.jpg"'),
    'a file name with < > & " is escaped, not breaking the packet');

console.log('Without camera data, and what is no JPEG');
const bare = readExif(stampJpeg(CANVAS_JPEG, { ...info, make: undefined, model: undefined, taken: undefined }));
check(bare.description === NOTE && bare.make === undefined && bare.taken === undefined,
    'the note stands, no empty camera fields are made up');
const png = Uint8Array.of(0x89, 0x50, 0x4E, 0x47);
check(stampJpeg(png, info) === png, 'what is no JPEG comes back untouched');

console.log('A picture made of several („Ebenen", 11.11.2026)');
check(noteText({ ...info, to: info.from, layers: 3 })
    === `Zusammengesetzt durch YASAIMAPP am ${info.when.toLocaleDateString('de', { day: '2-digit', month: '2-digit', year: 'numeric' })} aus 3 Aufnahmen desselben Motivs (${info.from.w} × ${info.from.h} Pixel). Grundlage: ${info.originalName}.`,
    'says that it is put together, of how many, and what it rests on');

console.log('A micrograph drawn in the microscope window (13.11.2026) names its camera');
const micro = { model: 'U3CMOS10000KPA', make: 'BMS', taken: '2026:11:13 18:20:05', when: info.when,
                originalName: '', to: { w: 3584, h: 2746 }, from: { w: 3584, h: 2746 } };
const stackTags = readExif(stampJpeg(CANVAS_JPEG, { ...micro, layers: 12 }));
check(stackTags.make === 'BMS' && stackTags.model === 'U3CMOS10000KPA' && stackTags.taken === '2026:11:13 18:20:05',
    'a stack carries the camera and the moment');
check(stackTags.description.startsWith('Zusammengesetzt durch YASAIMAPP') && !stackTags.description.includes('Grundlage'),
    'and says it is put together, without a base file it never had');
const camTags = readExif(stampJpeg(CANVAS_JPEG, { ...micro, make: undefined, model: 'USB Microscope', captured: true }));
check(camTags.model === 'USB Microscope' && camTags.make === undefined, 'a webcam still carries the name the system gives it');
check(camTags.description === `Aufgenommen mit YASAIMAPP am ${info.when.toLocaleDateString('de', { day: '2-digit', month: '2-digit', year: 'numeric' })} (3584 × 2746 Pixel).`,
    'and says it was taken, not shrunk');

console.log('A developed picture („Bild erzeugen", 07.11.2026)');
const cut = { ...info, to: { w: 3549, h: 5324 }, developed: true };
const DEVELOPED = 'Bearbeitet durch YASAIMAPP am 26.09.2026: Regler, Drehung und Ausschnitt aus „Bearbeiten" sind eingerechnet (3549 × 5324 Pixel). Originaldatei: DSC_0417.JPG.';
check(noteText(cut) === DEVELOPED, 'names the work, not a shrinking — a cut picture has no factor');
const made = stampJpeg(CANVAS_JPEG, cut);
const madeTags = readExif(made);
check(madeTags.description === DEVELOPED && madeTags.width === 3549 && madeTags.height === 5324,
    'the note and the new size stand in the file');
check(madeTags.make === 'Canon' && madeTags.taken === '2026:09:20 14:03:11', 'camera and moment of the shot come back');
check(!Buffer.from(made).toString('utf8').includes('ShrinkFactor'), 'no factor in the XMP');

console.log('Reading the camera of an original — big-endian, values behind offsets');
const original = new Blob([bigEndianJpeg()]);
const camera = await readCamera(original);
check(camera.make === 'NIKON CORPORATION' && camera.model === 'NIKON Z 6' && camera.taken === '2025:10:03 09:15:42',
    `maker, model and time read through exif.js (${JSON.stringify(camera)})`);
check(JSON.stringify(await readCamera(new Blob([png]))) === '{}', 'an original without EXIF gives nothing');
check(JSON.stringify(cameraOf({ make: 'Apple', model: 'iPhone 15', taken_at: '2026-09-21 10:22:33', lat: 51.3, lon: 9.9 }))
    === '{"make":"Apple","model":"iPhone 15","taken":"2026:09:21 10:22:33"}',
    'what photos.js read already comes over as camera and time — and no coordinates');
check(JSON.stringify(cameraOf({ taken_at: null, camera: null, make: null, model: null })) === '{}',
    'nothing found, nothing made up');

console.log('What exiftool says');
const tool = spawnSync('exiftool', ['-ver'], { encoding: 'utf8' });
if (tool.status !== 0) {
    console.log('  skip  exiftool is not installed — the readings above have to do');
} else {
    const dir = mkdtempSync(join(tmpdir(), 'exifstamp-'));
    try {
        const file = join(dir, 'stamped.jpg');
        writeFileSync(file, stamped);
        const run = spawnSync('exiftool', ['-j', '-a', '-G1', '-n', '-charset', 'exif=utf8', file], { encoding: 'utf8' });
        const got = JSON.parse(run.stdout || '[{}]')[0] ?? {};
        check(got['IFD0:ImageDescription'] === NOTE, 'ImageDescription');
        check(got['ExifIFD:UserComment'] === NOTE, 'UserComment');
        check(got['IFD0:XPComment'] === NOTE, 'XPComment');
        check(got['XMP-dc:Description'] === NOTE, 'XMP description');
        check(got['IFD0:Make'] === 'Canon' && got['IFD0:Model'] === 'Canon EOS R6', 'Make and Model');
        check(got['ExifIFD:DateTimeOriginal'] === '2026:09:20 14:03:11', 'DateTimeOriginal');
        check(got['IFD0:Orientation'] === 1, 'Orientation 1');
        check(got['ExifIFD:ExifImageWidth'] === 2048 && got['ExifIFD:ExifImageHeight'] === 1365, 'the new pixel size');
        const original = Object.entries(got).find(([k]) => k.endsWith(':OriginalWidth'));
        check(original !== undefined && Number(original[1]) === 6000, 'the original width in YASAIMAPP\'s XMP namespace');
        check(!/error/i.test(String(got['ExifTool:Error'] ?? '')) && !/warning/i.test(JSON.stringify(got['ExifTool:Warning'] ?? '')),
            `no error, no warning (${got['ExifTool:Warning'] ?? ''}${got['ExifTool:Error'] ?? ''})`);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
}

/* --- WebP (08.11.2026) ----------------------------------------------------
   An exported picture may be a WebP. Two 8 × 6 canvases from Chrome
   (toDataURL 'image/webp', quality 0.8 and 1 — lossy and lossless), each
   already in the extended form with its colour profile. */
const LOSSY_WEBP_B64 = 'UklGRjQCAABXRUJQVlA4WAoAAAAgAAAABwAABQAASUNDUMgBAAAAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAAChnVFJDAAABZAAAAChiVFJDAAABZAAAAChjcHJ0AAABjAAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAAgAAAAcAHMAUgBHAEJYWVogAAAAAAAAb6IAADj1AAADkFhZWiAAAAAAAABimQAAt4UAABjaWFlaIAAAAAAAACSgAAAPhAAAts9YWVogAAAAAAAA9tYAAQAAAADTLXBhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAACAAAAAcAEcAbwBvAGcAbABlACAASQBuAGMALgAgADIAMAAxADZWUDggRgAAABACAJ0BKggABgABQCYlsAJ0ugADHtvM4gAA9r5QS4aWglex8li5sU5SynYwW//i0OtDrQ0bMX1b+f/v9YDH3hs4xWULwAA=';
const CANVAS_WEBP = {
    lossy: Uint8Array.from(Buffer.from(LOSSY_WEBP_B64, 'base64')),
    // The same head and colour profile as the lossy one (Chrome writes one sRGB
    // profile), then the lossless picture chunk — the RIFF size set to match.
    lossless: (() => {
        const head = Buffer.from(LOSSY_WEBP_B64, 'base64').subarray(0, 494);
        const out = Buffer.concat([head, Buffer.from('VlA4TBcAAAAvB0ABAA/wRNVX3b3Ke/5jCcMR0f9wAAA=', 'base64')]);
        out.writeUInt32LE(out.length - 8, 4);
        return Uint8Array.from(out);
    })(),
};
const webpInfo = { ...info, to: { w: 8, h: 6 }, from: { w: 6000, h: 4000 }, developed: true };
const WEBP_NOTE = noteText(webpInfo);
const riffChunks = (b) => {
    const out = [];
    for (let at = 12; at + 8 <= b.length;) {
        const size = b[at + 4] | (b[at + 5] << 8) | (b[at + 6] << 16) | (b[at + 7] << 24);
        out.push({ id: Buffer.from(b.subarray(at, at + 4)).toString('latin1'), body: b.subarray(at + 8, at + 8 + size) });
        at += 8 + size + (size & 1);
    }
    return out;
};
console.log('WebP');
check(stampWebp(CANVAS_JPEG, webpInfo) === CANVAS_JPEG, 'a JPEG handed to the WebP stamp comes back untouched');
for (const [kind, bytes] of Object.entries(CANVAS_WEBP)) {
    const out = stampWebp(bytes, webpInfo);
    const before = riffChunks(bytes), after = riffChunks(out);
    const size = out[4] | (out[5] << 8) | (out[6] << 16) | (out[7] << 24);
    check(size === out.length - 8, `${kind}: the RIFF size is the file's length less eight`);
    check(after.map((c) => c.id).join(',') === [...before.map((c) => c.id), 'EXIF', 'XMP '].join(','),
        `${kind}: the canvas's chunks in their order, then EXIF and XMP (${after.map((c) => c.id).join(',')})`);
    check((after[0].body[0] & 0x0C) === 0x0C && (after[0].body[0] & 0x20) === 0x20,
        `${kind}: the head announces EXIF and XMP and keeps the colour profile's flag`);
    check(before.slice(1).every((c, i) => Buffer.from(c.body).equals(Buffer.from(after[i + 1].body))),
        `${kind}: profile and picture data byte for byte`);
    check(riffChunks(stampWebp(out, webpInfo)).filter((c) => c.id === 'EXIF').length === 1,
        `${kind}: stamped twice, still one EXIF`);
    if (tool.status === 0) {
        const dir = mkdtempSync(join(tmpdir(), 'exifstamp-'));
        try {
            const file = join(dir, 'stamped.webp');
            writeFileSync(file, out);
            const run = spawnSync('exiftool', ['-j', '-a', '-G1', '-n', '-charset', 'exif=utf8', file], { encoding: 'utf8' });
            const got = JSON.parse(run.stdout || '[{}]')[0] ?? {};
            check(got['IFD0:ImageDescription'] === WEBP_NOTE && got['XMP-dc:Description'] === WEBP_NOTE,
                `${kind}: exiftool reads the note from EXIF and XMP`);
            check(got['IFD0:Make'] === 'Canon' && got['ExifIFD:DateTimeOriginal'] === '2026:09:20 14:03:11',
                `${kind}: exiftool reads the camera and the moment of the shot`);
            check(!got['ExifTool:Error'] && !got['ExifTool:Warning'],
                `${kind}: no error, no warning (${got['ExifTool:Warning'] ?? ''}${got['ExifTool:Error'] ?? ''})`);
        } finally {
            rmSync(dir, { recursive: true, force: true });
        }
    }
}

/* --- Author and rights (08.11.2026) ---------------------------------------
   The export may write who made the picture and the rights line into the
   file — EXIF Artist and Copyright, XMP creator and rights. Umlauts and the
   © sign must survive. */
console.log('Author and rights');
const owner = { artist: 'Sebastian Schröder', copyright: '© Sebastian Schröder 2026' };
check(readExif(stampJpeg(CANVAS_JPEG, info)).description === NOTE, 'without them, the stamp is as before');
check(exif.read(stampJpeg(CANVAS_JPEG, { ...info, ...owner }).buffer).artist === owner.artist,
    'the project\'s own reader (exif.js) reads the artist back — what the upload proposes as the author');
if (tool.status === 0) {
    const dir = mkdtempSync(join(tmpdir(), 'exifstamp-'));
    try {
        for (const [kind, bytes] of [['JPEG', stampJpeg(CANVAS_JPEG, { ...info, ...owner })],
                                     ['WebP', stampWebp(CANVAS_WEBP.lossy, { ...webpInfo, ...owner })]]) {
            const file = join(dir, kind === 'JPEG' ? 'owner.jpg' : 'owner.webp');
            writeFileSync(file, bytes);
            const run = spawnSync('exiftool', ['-j', '-a', '-G1', '-n', '-charset', 'exif=utf8', file], { encoding: 'utf8' });
            const got = JSON.parse(run.stdout || '[{}]')[0] ?? {};
            check(got['IFD0:Artist'] === owner.artist && got['IFD0:Copyright'] === owner.copyright,
                `${kind}: EXIF Artist and Copyright (${got['IFD0:Artist']} / ${got['IFD0:Copyright']})`);
            check(got['XMP-dc:Creator'] === owner.artist && got['XMP-dc:Rights'] === owner.copyright,
                `${kind}: XMP creator and rights`);
            check(!got['ExifTool:Error'] && !got['ExifTool:Warning'], `${kind}: no error, no warning`);
        }
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
}

/* --- How it was taken (10.11.2026) ----------------------------------------
   Aperture, time, ISO, focal lengths, compensation, program, metering,
   flash, white balance and lens come back into the shrunk picture. The
   values are those of real cameras: an Olympus E-M5 II with the 60-mm macro
   (−0,7 LW, Zeitautomatik) and a Xiaomi 15, whose time is the odd fraction
   200339/1000000000 s — it must come back as exactly that, not rounded. */
console.log('How it was taken');
const SHOTS = {
    olympus: { exposure: 0.2, fnumber: 5.6, iso: 100, focal: 60, focal35: null, bias: -0.7, flash: false, flashCode: 16,
        program: 3, metering: 2, whiteBalance: 0, lens: 'OLYMPUS M.60mm F2.8 Macro', software: null, copyright: null },
    xiaomi: { exposure: 200339 / 1e9, fnumber: 1.62, iso: 50, focal: 6.55, focal35: 23, bias: 0, flash: false, flashCode: 0,
        program: 2, metering: 2, whiteBalance: 0, lens: null, software: null, copyright: null },
    thirds: { exposure: 1 / 3, fnumber: 8, iso: 6400, focal: 105, focal35: null, bias: -2 / 3, flash: true, flashCode: 9,
        program: 1, metering: 5, whiteBalance: 1, lens: null, software: null, copyright: null },
};
const close = (a, b) => (a === null && b === null) || Math.abs(a - b) < 1e-9 * Math.max(1, Math.abs(b));
for (const [name, shot] of Object.entries(SHOTS)) {
    const bytes = stampJpeg(CANVAS_JPEG, { ...info, shot });
    const back = exif.shot(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength));
    const keys = ['exposure', 'fnumber', 'iso', 'focal', 'focal35', 'bias', 'flashCode', 'program', 'metering', 'whiteBalance', 'lens'];
    const wrong = keys.filter((k) => (typeof shot[k] === 'number' ? !close(back?.[k], shot[k]) : back?.[k] !== shot[k]));
    check(wrong.length === 0, `${name}: every value comes back through exif.js${wrong.length ? ' — not: ' + wrong.map((k) => `${k} ${back?.[k]}`).join(', ') : ''}`);
    check(readExif(bytes).description === NOTE && readExif(bytes).make === 'Canon', `${name}: the note and the camera stay as they were`);
}
check(shotOf(null).shot === undefined && shotOf({ exposure: null, fnumber: null, iso: null, focal: null, focal35: null,
    bias: null, flashCode: null, program: null, metering: null, whiteBalance: null, lens: null }).shot === undefined,
    'an original without exposure data brings no empty shot along');
if (tool.status === 0) {
    const dir = mkdtempSync(join(tmpdir(), 'exifstamp-'));
    try {
        for (const [name, shot] of Object.entries(SHOTS)) {
            const file = join(dir, `${name}.jpg`);
            writeFileSync(file, stampJpeg(CANVAS_JPEG, { ...info, shot }));
            const run = spawnSync('exiftool', ['-j', '-a', '-G1', '-n', file], { encoding: 'utf8' });
            const got = JSON.parse(run.stdout || '[{}]')[0] ?? {};
            check(close(got['ExifIFD:ExposureTime'], shot.exposure) && close(got['ExifIFD:FNumber'], shot.fnumber)
                && got['ExifIFD:ISO'] === shot.iso && close(got['ExifIFD:FocalLength'], shot.focal),
                `${name}: exiftool reads time, aperture, ISO and focal length (${got['ExifIFD:ExposureTime']}, ${got['ExifIFD:FNumber']}, ${got['ExifIFD:ISO']}, ${got['ExifIFD:FocalLength']})`);
            check(close(got['ExifIFD:ExposureCompensation'], shot.bias) && got['ExifIFD:ExposureProgram'] === shot.program
                && got['ExifIFD:MeteringMode'] === shot.metering && got['ExifIFD:Flash'] === shot.flashCode
                && got['ExifIFD:WhiteBalance'] === shot.whiteBalance
                && (got['ExifIFD:FocalLengthIn35mmFormat'] ?? null) === shot.focal35
                && (got['ExifIFD:LensModel'] ?? null) === shot.lens,
                `${name}: and compensation, program, metering, flash, white balance, 35-mm focal length, lens`);
            check(!got['ExifTool:Error'] && !got['ExifTool:Warning'], `${name}: no error, no warning`);
        }
        const plain = spawnSync('exiftool', ['-ExposureTime', '-ExposureCompensation', '-s3', join(dir, 'xiaomi.jpg'), join(dir, 'thirds.jpg')], { encoding: 'utf8' }).stdout;
        // exiftool's own notation; it writes a third of a second as „0.3" itself.
        check(/1\/4992/.test(plain) && /-2\/3/.test(plain), `as exiftool shows them: 1/4992 s, −2/3 LW (${plain.replace(/=+ \S+/g, '').replace(/\s+/g, ' ').trim()})`);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
}

/* THE EXPORT'S CHOICES (12.11.2026): the find's place only where ticked,
   and without it no original name that could carry the place — a cloud
   recording is called „<Art> <Datum> <Ort>". */
console.log('The place, and the name that could carry it');
const forum = { ...info, originalName: 'Arrhenia 2026-11-09 Darmstadt 1.jpg', developed: true, to: { w: 1000, h: 750 } };
const hidden = stampJpeg(CANVAS_JPEG, { ...forum, hideName: true });
const hiddenText = Buffer.from(hidden).toString('utf8');
check(!hiddenText.includes('Darmstadt'), 'without the place, the name with the place in it stands nowhere in the file');
check(noteText({ ...forum, hideName: true })
    === 'Bearbeitet durch YASAIMAPP am 26.09.2026: Regler, Drehung und Ausschnitt aus „Bearbeiten" sind eingerechnet (1000 × 750 Pixel).',
    'the note then ends after the size');
check(!readExif(hidden).gps, 'and there is no GPS directory');
const placed = stampJpeg(CANVAS_JPEG, { ...forum, gps: { lat: 49.8728251, lon: -8.6511929, alt: 100 } });
const placedOwn = exif.read(placed.buffer.slice(placed.byteOffset, placed.byteOffset + placed.byteLength));
check(Math.abs(placedOwn.lat - 49.8728251) < 1e-6 && Math.abs(placedOwn.lon + 8.6511929) < 1e-6,
    `with the place, exif.js reads the find's coordinates back, west as negative (${placedOwn.lat}, ${placedOwn.lon})`);
check(Buffer.from(placed).toString('utf8').includes('Darmstadt'), 'and the original name stays in the note');
const edge = stampJpeg(CANVAS_JPEG, { ...forum, gps: { lat: 50.99999999, lon: 10, alt: null } });
const edgeOwn = exif.read(edge.buffer.slice(edge.byteOffset, edge.byteOffset + edge.byteLength));
check(Math.abs(edgeOwn.lat - 51) < 1e-6, `seconds rounding up to 60 carry into the minutes and degrees (${edgeOwn.lat})`);
if (spawnSync('exiftool', ['-ver'], { encoding: 'utf8' }).status === 0) {
    const dir = mkdtempSync(join(tmpdir(), 'exifgps-'));
    try {
        writeFileSync(join(dir, 'placed.jpg'), placed);
        writeFileSync(join(dir, 'hidden.jpg'), hidden);
        const run = spawnSync('exiftool', ['-j', '-a', '-G1', '-n', join(dir, 'placed.jpg'), join(dir, 'hidden.jpg')], { encoding: 'utf8' });
        const [p, h] = JSON.parse(run.stdout);
        check(Math.abs(p['GPS:GPSLatitude'] - 49.8728251) < 1e-6 && p['GPS:GPSLatitudeRef'] === 'N'
            && Math.abs(p['GPS:GPSLongitude'] - 8.6511929) < 1e-6 && p['GPS:GPSLongitudeRef'] === 'W' && p['GPS:GPSAltitude'] === 100,
            `exiftool reads latitude, longitude and height (${p['GPS:GPSLatitude']} ${p['GPS:GPSLatitudeRef']}, ${p['GPS:GPSLongitude']} ${p['GPS:GPSLongitudeRef']}, ${p['GPS:GPSAltitude']} m)`);
        check(!Object.keys(h).some((k) => k.startsWith('GPS:')) && !JSON.stringify(h).includes('Darmstadt'),
            'exiftool finds neither GPS nor the name in the file without the place');
        const warn = spawnSync('exiftool', ['-validate', '-warning', '-a', join(dir, 'placed.jpg')], { encoding: 'utf8' }).stdout;
        // The stamp never wrote ExifVersion, resolutions and the like; the GPS directory must add no complaint of its own.
        check(!/GPS/i.test(warn), `exiftool -validate says nothing about the GPS directory`);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
}

console.log('\nThe colour profile handed on (Ebenen, 14.11.2026)\n');
{
    // The canvas's own: Chrome writes an sRGB profile into every JPEG.
    const own = jpegProfile(CANVAS_JPEG);
    check(own !== null && own[0] === 0xFF && own[1] === 0xE2, `the canvas JPEG's own profile is found (${own?.length} bytes)`);
    // A stand-in for an Adobe RGB profile: one APP2 segment, marked as ICC.
    const body = Buffer.concat([Buffer.from('ICC_PROFILE\0', 'latin1'), Buffer.from([1, 1]), Buffer.from('adobe-rgb-stand-in'.repeat(20), 'latin1')]);
    const theirs = Uint8Array.from(Buffer.concat([Buffer.from([0xFF, 0xE2, (body.length + 2) >> 8, (body.length + 2) & 0xFF]), body]));
    const handed = stampJpeg(CANVAS_JPEG, { ...info, icc: theirs });
    const found = jpegProfile(handed);
    check(found !== null && Buffer.compare(Buffer.from(found), Buffer.from(theirs)) === 0,
        'a profile handed on REPLACES the canvas\'s — one profile, the given one');
    // From the first quantisation table on, the file is the canvas's, unchanged.
    const tail = Buffer.from(CANVAS_JPEG).subarray(Buffer.from(CANVAS_JPEG).indexOf(Buffer.from([0xFF, 0xDB])));
    check(Buffer.from(handed).subarray(handed.length - tail.length).equals(tail), 'the picture data after the header stays byte for byte');
    const plain = jpegProfile(stampJpeg(CANVAS_JPEG, info));
    check(plain !== null && Buffer.compare(Buffer.from(plain), Buffer.from(own)) === 0, 'without one, the canvas\'s own profile stays as it was');
    check(jpegProfile(Uint8Array.of(0x89, 0x50, 0x4E, 0x47)) === null, 'no JPEG, no profile');
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);

/* --- Helpers ------------------------------------------------------------- */

/** The markers of a JPEG up to the picture data, as hex. */
function markers(bytes) {
    const out = ['d8'];
    let i = 2;
    while (i + 4 <= bytes.length && bytes[i] === 0xFF) {
        out.push(bytes[i + 1].toString(16));
        if (bytes[i + 1] === 0xDA) break;
        i += 2 + ((bytes[i + 2] << 8) | bytes[i + 3]);
    }
    return out;
}

/** The first bytes of the n-th segment's body, as text. */
function label(bytes, segs, n) {
    let i = 2;
    for (let k = 1; k < n; k++) i += 2 + ((bytes[i + 2] << 8) | bytes[i + 3]);
    return Buffer.from(bytes.subarray(i + 4, i + 4 + 29)).toString('latin1').replace(/\0.*$/s, '');
}

/**
 * A reader of its own for what the stamp writes — including the fields
 * exif.js has no use for (the note in its three places, the software,
 * the new size). Both byte orders, values inline or behind an offset.
 */
function readExif(bytes) {
    let i = 2;
    let tiff = null;
    while (i + 4 <= bytes.length && bytes[i] === 0xFF && bytes[i + 1] !== 0xDA) {
        const len = (bytes[i + 2] << 8) | bytes[i + 3];
        if (bytes[i + 1] === 0xE1 && Buffer.from(bytes.subarray(i + 4, i + 10)).toString('latin1') === 'Exif\0\0') {
            tiff = bytes.subarray(i + 10, i + 2 + len);
            break;
        }
        i += 2 + len;
    }
    if (tiff === null) return {};
    const view = new DataView(tiff.buffer, tiff.byteOffset, tiff.byteLength);
    const little = tiff[0] === 0x49;
    const u16 = (o) => view.getUint16(o, little);
    const u32 = (o) => view.getUint32(o, little);
    const entries = (at) => {
        const out = new Map();
        for (let k = 0; k < u16(at); k++) {
            const e = at + 2 + 12 * k;
            out.set(u16(e), { type: u16(e + 2), count: u32(e + 4), field: e + 8 });
        }
        return out;
    };
    const raw = (e) => {
        const size = e.type === 3 ? 2 : e.type === 4 ? 4 : 1;
        const len = e.count * size;
        const start = len <= 4 ? e.field : u32(e.field);
        return tiff.subarray(start, start + len);
    };
    const text = (e) => e ? new TextDecoder().decode(raw(e)).replace(/\0+$/, '') : undefined;
    const number = (e) => !e ? undefined : e.type === 3 ? u16(e.field) : u32(e.field);

    const ifd0 = entries(u32(4));
    const out = {
        description: text(ifd0.get(0x010E)),
        make: text(ifd0.get(0x010F)),
        model: text(ifd0.get(0x0110)),
        orientation: number(ifd0.get(0x0112)),
        software: text(ifd0.get(0x0131)),
        changed: text(ifd0.get(0x0132)),
    };
    const xp = ifd0.get(0x9C9C);
    if (xp) out.xpComment = new TextDecoder('utf-16le').decode(raw(xp)).replace(/\0+$/, '');
    const pointer = ifd0.get(0x8769);
    if (pointer) {
        const sub = entries(u32(pointer.field));
        out.taken = text(sub.get(0x9003));
        out.width = number(sub.get(0xA002));
        out.height = number(sub.get(0xA003));
        const comment = sub.get(0x9286);
        if (comment) out.comment = new TextDecoder(little ? 'utf-16le' : 'utf-16be').decode(raw(comment).subarray(8));
    }
    return out;
}

/**
 * A camera original as a Nikon writes it: big-endian TIFF, maker, model
 * and time too long to stand inline — the pointers have to be followed.
 */
function bigEndianJpeg() {
    const make = Buffer.from('NIKON CORPORATION\0', 'latin1');
    const model = Buffer.from('NIKON Z 6\0', 'latin1');
    const taken = Buffer.from('2025:10:03 09:15:42\0', 'latin1');
    const ifd0At = 8, ifd0Size = 2 + 3 * 12 + 4;
    const makeAt = ifd0At + ifd0Size, modelAt = makeAt + make.length + (make.length % 2);
    const exifAt = modelAt + model.length + (model.length % 2), exifSize = 2 + 12 + 4;
    const takenAt = exifAt + exifSize;
    const tiff = Buffer.alloc(takenAt + taken.length);
    tiff.write('MM', 0, 'latin1');
    tiff.writeUInt16BE(42, 2);
    tiff.writeUInt32BE(ifd0At, 4);
    tiff.writeUInt16BE(3, ifd0At);
    const entry = (at, tag, type, count, value) => {
        tiff.writeUInt16BE(tag, at); tiff.writeUInt16BE(type, at + 2);
        tiff.writeUInt32BE(count, at + 4); tiff.writeUInt32BE(value, at + 8);
    };
    entry(ifd0At + 2, 0x010F, 2, make.length, makeAt);
    entry(ifd0At + 14, 0x0110, 2, model.length, modelAt);
    entry(ifd0At + 26, 0x8769, 4, 1, exifAt);
    make.copy(tiff, makeAt);
    model.copy(tiff, modelAt);
    tiff.writeUInt16BE(1, exifAt);
    entry(exifAt + 2, 0x9003, 2, taken.length, takenAt);
    taken.copy(tiff, takenAt);
    const body = Buffer.concat([Buffer.from('Exif\0\0', 'latin1'), tiff]);
    const head = Buffer.from([0xFF, 0xD8, 0xFF, 0xE1, (body.length + 2) >> 8, (body.length + 2) & 0xFF]);
    return Buffer.concat([head, body, Buffer.from([0xFF, 0xD9])]);
}
