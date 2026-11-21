/**
 * A note inside a shrunk picture — what happened to it, readable by any
 * program (26.09.2026: „dann würde in jedem Bild eben ein fester
 * Stempel stehen, eine Notiz, was mit dem Bild geschehen ist").
 *
 * WHY THE PICTURE HAS TO SAY IT ITSELF. YASAIMAPP remembers a shrunk
 * picture's original size in its own database (cloudupload.js, migration
 * 0095), and that is all it needs. But the picture lives in the user's
 * cloud, and whoever leaves the app one day and copies his pictures onto a
 * disk carries files that are 2048 pixels wide without knowing they ever
 * were 6000 — and every measurement in them would be off by that factor.
 * So the file carries a note in plain words, in the user's language:
 * „Verkleinert von 6000 × 4000 auf 2048 × 1365 Pixel (Faktor 2,93) durch
 * YASAIMAPP am 26.09.2026. Originaldatei: DSC_0417.JPG."
 *
 * AND THE CAMERA'S OWN DATA COMES BACK. Shrinking draws the picture anew
 * on a canvas, and the fresh JPEG carries nothing — no camera, no time.
 * Found while building this; the file lost everything until now. Camera
 * and moment of the shot are read from the original and written again.
 *
 * WHERE IT STANDS — in the places other programs read:
 *   EXIF ImageDescription   the one field nearly everything shows
 *   EXIF UserComment        the same note in Unicode, the standard's way
 *   EXIF XPComment          what the Windows Explorer shows as „Kommentare"
 *   XMP dc:description      what photo programs (Lightroom, digiKam) read,
 *                           beside it the original size and the factor as
 *                           numbers, for a program to compute with
 *   Make, Model, DateTimeOriginal   carried over from the camera
 *   Orientation 1           the picture is turned already — the canvas
 *                           burnt the camera's rotation in, and a second
 *                           rotation by a viewer would tip it over
 *
 * WRITTEN BY HAND, NO LIBRARY: a TIFF block of a dozen entries and an XML
 * packet are a few bytes, and this project has no build step to bring a
 * library along — the same reasoning as exif.js, whose reading this uses.
 * tests/exifstamp.mjs holds the writing to exiftool's reading.
 */

import { t, lang } from './i18n.js';
import * as exif from './exif.js';

/* --- Reading: the few things worth carrying over ------------------------ */

/**
 * Camera and moment of the shot, from the original — through exif.js, the
 * reader the voucher photos already trust, not a second one beside it. The
 * first 256 KB are enough: EXIF stands before the picture data. Anything
 * not a JPEG, or a JPEG without EXIF, simply gives nothing.
 *
 * @returns {Promise<{make?: string, model?: string, taken?: string}>}
 */
export async function readCamera(file) {
    try {
        const buffer = await file.slice(0, 256 * 1024).arrayBuffer();
        return { ...cameraOf(exif.read(buffer)), ...shotOf(exif.shot(buffer)) };
    } catch {
        return {};
    }
}

/**
 * HOW IT WAS TAKEN, carried over too (10.11.2026: „bau beide
 * Schritte, dass die Belichtungsdaten erhalten bleiben"). Until then a shrunk
 * picture kept camera and moment and lost aperture, time, ISO, focal length
 * and lens — the info tab of „Bearbeiten" found nothing in it. exif.shot()
 * reads them, stampJpeg() writes them back. Nothing comes when the original
 * says nothing, so a stamp without them stays the stamp it was.
 *
 * @returns {{shot?: object}}
 */
export function shotOf(shot) {
    if (!shot) return {};
    const kept = ['exposure', 'fnumber', 'iso', 'focal', 'focal35', 'bias', 'flashCode', 'program', 'metering', 'whiteBalance', 'lens'];
    return kept.some((k) => shot[k] !== null && shot[k] !== undefined) ? { shot } : {};
}

/**
 * What exif.read() found, in the shape stampJpeg() writes — for a caller
 * that has read the original already (photos.js reads it anyway, before
 * it shrinks).
 *
 * @returns {{make?: string, model?: string, taken?: string}}
 */
export function cameraOf(found) {
    const out = {};
    if (found?.make) out.make = found.make;
    if (found?.model) out.model = found.model;
    // Back from exif.js's „2026-09-20 14:03:11" to EXIF's own colons.
    if (found?.taken_at) out.taken = found.taken_at.replace(/^(\d{4})-(\d{2})-(\d{2})/, '$1:$2:$3');
    return out;
}

/* --- Writing: the note ---------------------------------------------------- */

/**
 * The note in the user's language — the German sentence is the catalog
 * key, as everywhere (i18n.js); the numbers and the date in the user's
 * own notation.
 *
 * @param {{from: {w: number, h: number}, to: {w: number, h: number},
 *          originalName: string, when: Date}} info
 */
export function noteText(info) {
    const day = info.when.toLocaleDateString(lang, { day: '2-digit', month: '2-digit', year: 'numeric' });
    /* A DEVELOPED PICTURE („Bild exportieren", 07.11.2026) is a new picture
       made from the original, not a smaller copy of it: its size says
       nothing about a factor — it may be cut — so the note names the work
       and not a shrinking. */
    /* A PICTURE MADE OF SEVERAL („Ebenen", 11.11.2026, blend.js): renderings
       of one shot painted into one. Its size is the originals'; what the
       note must say is that it is no camera's file, and what it rests on. */
    /* WITHOUT THE ORIGINAL'S NAME where the export leaves the find's place
       out (12.11.2026): a cloud recording's name is „<Art>
       <Datum> <Ort>" since the renaming of 04.10.2026, and a picture for a
       forum without coordinates would still say „Arenshausen" here. */
    const named = !info.hideName && info.originalName;
    /* TAKEN HERE (Mikroskopieren, 13.11.2026): a webcam's still is drawn on
       a canvas the moment it is taken — there is no original it was made
       from, only the camera it came through. */
    if (info.captured) {
        return t('Aufgenommen mit YASAIMAPP am %s (%d × %d Pixel).', day, info.to.w, info.to.h);
    }
    if (info.layers) {
        return named
            ? t('Zusammengesetzt durch YASAIMAPP am %s aus %d Aufnahmen desselben Motivs (%d × %d Pixel). Grundlage: %s.',
                day, info.layers, info.to.w, info.to.h, info.originalName)
            : t('Zusammengesetzt durch YASAIMAPP am %s aus %d Aufnahmen desselben Motivs (%d × %d Pixel).',
                day, info.layers, info.to.w, info.to.h);
    }
    if (info.developed) {
        return named
            ? t('Bearbeitet durch YASAIMAPP am %s: Regler, Drehung und Ausschnitt aus „Bearbeiten" sind eingerechnet (%d × %d Pixel). Originaldatei: %s.',
                day, info.to.w, info.to.h, info.originalName)
            : t('Bearbeitet durch YASAIMAPP am %s: Regler, Drehung und Ausschnitt aus „Bearbeiten" sind eingerechnet (%d × %d Pixel).',
                day, info.to.w, info.to.h);
    }
    /* A picture that was already small enough is saved anew all the same —
       the canvas is the only way through — and must not claim a shrinking
       by the factor 1. */
    if (info.from.w === info.to.w && info.from.h === info.to.h) {
        return t('Neu gespeichert durch YASAIMAPP am %s, ohne Verkleinerung (%d × %d Pixel). Originaldatei: %s.',
            day, info.to.w, info.to.h, info.originalName);
    }
    const factor = info.from.w / info.to.w;
    return t('Verkleinert von %d × %d auf %d × %d Pixel (Faktor %s) durch YASAIMAPP am %s. Originaldatei: %s.',
        info.from.w, info.from.h, info.to.w, info.to.h,
        factor.toLocaleString(lang, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
        day, info.originalName);
}

/**
 * The shrunk JPEG with the note and the camera's data in it: a fresh EXIF
 * segment and an XMP packet, set in after the start and the JFIF header —
 * the order exiftool writes, and every reader expects.
 *
 * @param {Uint8Array} jpeg  the canvas's JPEG
 * @param {{from: {w: number, h: number}, to: {w: number, h: number},
 *          originalName: string, when: Date, developed?: boolean,
 *          make?: string, model?: string, taken?: string,
 *          icc?: Uint8Array}} info   icc: whole APP2 segments (jpegProfile)
 * @returns {Uint8Array}
 */
export function stampJpeg(jpeg, info) {
    if (jpeg[0] !== 0xFF || jpeg[1] !== 0xD8) return jpeg;
    const safe = { ...info, originalName: String(info.originalName ?? '').slice(0, 200),
        artist: String(info.artist ?? '').slice(0, 200), copyright: String(info.copyright ?? '').slice(0, 200) };
    const note = noteText(safe);
    const exif = segment(0xE1, concat(ascii('Exif\0\0', false), tiffBlock(safe, note)));
    const xmp  = segment(0xE1, concat(ascii('http://ns.adobe.com/xap/1.0/\0', false), utf8(xmpPacket(safe, note))));
    /* THE COLOUR PROFILE, where the caller hands one on (jpegProfile). It
       REPLACES the canvas's own: Chrome writes an sRGB profile into every
       JPEG it encodes (measured, 474 bytes), and a file with two profiles
       is read by whichever the reader meets first. */
    const icc = info.icc instanceof Uint8Array ? info.icc : new Uint8Array(0);
    if (icc.length) jpeg = profileCut(jpeg);
    let at = 2;
    if (jpeg[2] === 0xFF && jpeg[3] === 0xE0) at = 4 + ((jpeg[4] << 8) | jpeg[5]);
    return concat(jpeg.subarray(0, at), exif, xmp, icc, jpeg.subarray(at));
}

/**
 * THE COLOUR PROFILE OF A JPEG, as the whole APP2 segments it stands in
 * („ICC_PROFILE\0", a large one split over several) — ready to be set into
 * another JPEG by stampJpeg's info.icc. Null where the file carries none,
 * or is no JPEG.
 *
 * WHY IT IS NEEDED (14.11.2026, hard steps in the soft
 * backgrounds of a merged picture): a picture in Adobe RGB is converted to
 * sRGB by the browser while decoding, IN EIGHT BITS, and the dark tones
 * stretch — measured, a smooth dark ramp went from steps of 2 to steps of
 * 6. Whoever reads the numbers unconverted (createImageBitmap with
 * colorSpaceConversion 'none') and writes them back WITH this profile
 * loses nothing: the colours stay the file's, exactly.
 * @param {Uint8Array} jpeg
 * @returns {Uint8Array|null}
 */
export function jpegProfile(jpeg) {
    if (jpeg[0] !== 0xFF || jpeg[1] !== 0xD8) return null;
    const parts = profileSegments(jpeg).map(([from, to]) => jpeg.subarray(from, to));
    return parts.length ? concat(...parts) : null;
}

/** Where a JPEG's profile stands: [from, to) of each of its APP2 segments, in the header only. */
function profileSegments(jpeg) {
    const found = [];
    for (let at = 2; at + 4 <= jpeg.length;) {
        if (jpeg[at] !== 0xFF) break;
        const marker = jpeg[at + 1];
        if (marker === 0xFF) { at++; continue; }          // fill byte
        if (marker === 0xDA || marker === 0xD9) break;    // the picture data begins: no header after it
        const length = (jpeg[at + 2] << 8) | jpeg[at + 3];
        if (marker === 0xE2 && length >= 16 && at + 2 + length <= jpeg.length
            && String.fromCharCode(...jpeg.subarray(at + 4, at + 16)) === 'ICC_PROFILE\0') {
            found.push([at, at + 2 + length]);
        }
        at += 2 + length;
    }
    return found;
}

/** The JPEG without its profile segments — the rest byte for byte. */
function profileCut(jpeg) {
    const cuts = profileSegments(jpeg);
    if (!cuts.length) return jpeg;
    const kept = [];
    let from = 0;
    for (const [a, b] of cuts) { kept.push(jpeg.subarray(from, a)); from = b; }
    kept.push(jpeg.subarray(from));
    return concat(...kept);
}

/**
 * THE SAME NOTE IN A WEBP (08.11.2026: „Webdev-Bildformat ist auch
 * ziemlich interessant, weil es platzsparend ist") — a developed picture may
 * be exported as WebP, and it must not come out poorer than the JPEG: camera,
 * moment of the shot and the note travel along.
 *
 * A WebP is a RIFF file of chunks. The canvas writes the simple form, one
 * picture chunk („VP8 " lossy, „VP8L" lossless); metadata needs the extended
 * form: a „VP8X" chunk first, whose flags announce EXIF and XMP and which
 * carries the canvas size, then the picture's own chunks unchanged and in
 * their order, then „EXIF" (the bare TIFF block — no „Exif\0\0" before it,
 * as the container's specification wants) and „XMP ". Chunks are padded to
 * an even length. An extended file the canvas wrote already (with an alpha
 * chunk) keeps its flags; earlier EXIF and XMP are replaced, not doubled.
 * Anything that is no WebP comes back as it was.
 * @param {Uint8Array} webp  the canvas's WebP
 * @param {object} info      as stampJpeg's; info.to is the canvas size
 * @returns {Uint8Array}
 */
export function stampWebp(webp, info) {
    const tag = (i) => String.fromCharCode(webp[i], webp[i + 1], webp[i + 2], webp[i + 3]);
    const u32 = (i) => (webp[i] | (webp[i + 1] << 8) | (webp[i + 2] << 16) | (webp[i + 3] << 24)) >>> 0;
    if (webp.length < 20 || tag(0) !== 'RIFF' || tag(8) !== 'WEBP') return webp;
    const chunks = [];
    for (let at = 12; at + 8 <= webp.length;) {
        const size = u32(at + 4);
        chunks.push({ id: tag(at), body: webp.subarray(at + 8, at + 8 + size) });
        at += 8 + size + (size & 1);
    }
    const old = chunks.find((c) => c.id === 'VP8X');
    const kept = chunks.filter((c) => !['VP8X', 'EXIF', 'XMP '].includes(c.id));
    if (!kept.some((c) => c.id === 'VP8 ' || c.id === 'VP8L')) return webp;
    // Alpha: announced by the old head, an alpha chunk, or the lossless head's own bit.
    const lossless = kept.find((c) => c.id === 'VP8L')?.body;
    const alpha = (old && (old.body[0] & 0x10) !== 0) || kept.some((c) => c.id === 'ALPH')
        || (lossless !== undefined && lossless.length >= 5 && ((lossless[4] >> 4) & 1) === 1);
    const safe = { ...info, originalName: String(info.originalName ?? '').slice(0, 200),
        artist: String(info.artist ?? '').slice(0, 200), copyright: String(info.copyright ?? '').slice(0, 200) };
    const note = noteText(safe);
    const head = new Uint8Array(10);
    head[0] = (old ? old.body[0] : 0) | (alpha ? 0x10 : 0) | 0x08 | 0x04;   // alpha, EXIF, XMP
    const w = info.to.w - 1, h = info.to.h - 1;
    head.set([w & 0xFF, (w >> 8) & 0xFF, (w >> 16) & 0xFF, h & 0xFF, (h >> 8) & 0xFF, (h >> 16) & 0xFF], 4);
    const chunk = (id, body) => concat(ascii(id, false), long(body.length), body, body.length % 2 ? Uint8Array.of(0) : new Uint8Array(0));
    const inner = concat(
        chunk('VP8X', head),
        ...kept.map((c) => chunk(c.id, c.body)),
        chunk('EXIF', tiffBlock(safe, note)),
        chunk('XMP ', utf8(xmpPacket(safe, note))),
    );
    return concat(ascii('RIFF', false), long(inner.length + 4), ascii('WEBP', false), inner);
}

/** One JPEG segment: marker, length (its own two bytes included), body. */
function segment(marker, body) {
    if (body.length + 2 > 0xFFFF) throw new Error('segment too long');
    return concat(Uint8Array.of(0xFF, marker, (body.length + 2) >> 8, (body.length + 2) & 0xFF), body);
}

/* The TIFF types used here. */
const BYTE = 1, ASCII = 2, SHORT = 3, LONG = 4, RATIONAL = 5, UNDEFINED = 7, SRATIONAL = 10;

/**
 * The EXIF block, little-endian: IFD0 with the note, the camera and the
 * orientation, and behind it the Exif IFD with the moment of the shot, the
 * note in Unicode and the new pixel size. Entries stand sorted by tag, as
 * the standard demands; values longer than four bytes follow their IFD on
 * even offsets.
 */
function tiffBlock(info, note) {
    const gps = gpsEntries(info.gps);
    const ifd0 = [
        [0x010E, ASCII, ascii(note)],
        ...(info.make  ? [[0x010F, ASCII, ascii(info.make)]] : []),
        ...(info.model ? [[0x0110, ASCII, ascii(info.model)]] : []),
        [0x0112, SHORT, short(1)],
        [0x0131, ASCII, ascii('YASAIMAPP')],
        [0x0132, ASCII, ascii(exifDate(info.when))],
        /* AUTHOR AND RIGHTS where the export asks for them
           (08.11.2026: „Urheber mit rein") — in the file's own data, so they
           stay when a visible notice is cut off. Artist and Copyright are
           what every photo program shows under these names. */
        ...(info.artist ? [[0x013B, ASCII, ascii(info.artist)]] : []),
        ...(info.copyright ? [[0x8298, ASCII, ascii(info.copyright)]] : []),
        [0x8769, LONG, long(0)],   // the Exif IFD's offset, filled in below
        ...(gps.length ? [[0x8825, LONG, long(0)]] : []),   // the GPS IFD's offset, likewise
        [0x9C9C, BYTE, utf16le(note + '\0')],
    ];
    const exif = [
        ...(info.taken ? [[0x9003, ASCII, ascii(info.taken)]] : []),
        [0x9286, UNDEFINED, concat(ascii('UNICODE\0', false), utf16le(note))],
        [0xA002, LONG, long(info.to.w)],
        [0xA003, LONG, long(info.to.h)],
        ...shotEntries(info.shot),
    ].sort((a, b) => a[0] - b[0]);

    const ifdSize = (list) => 2 + 12 * list.length + 4;
    const dataSize = (list) => list.reduce((sum, [, , bytes]) => sum + (bytes.length > 4 ? even(bytes.length) : 0), 0);
    const ifd0At = 8;
    const exifAt = ifd0At + ifdSize(ifd0) + dataSize(ifd0);
    ifd0.find(([tag]) => tag === 0x8769)[2] = long(exifAt);
    const gpsAt = exifAt + ifdSize(exif) + dataSize(exif);
    if (gps.length) ifd0.find(([tag]) => tag === 0x8825)[2] = long(gpsAt);
    const total = gpsAt + (gps.length ? ifdSize(gps) + dataSize(gps) : 0);

    const out = new Uint8Array(total);
    const view = new DataView(out.buffer);
    out.set([0x49, 0x49], 0);
    view.setUint16(2, 42, true);
    view.setUint32(4, ifd0At, true);

    const write = (list, at) => {
        view.setUint16(at, list.length, true);
        let data = at + ifdSize(list);
        list.forEach(([tag, type, bytes], k) => {
            const e = at + 2 + 12 * k;
            const size = type === SHORT ? 2 : type === LONG ? 4 : type === RATIONAL || type === SRATIONAL ? 8 : 1;
            view.setUint16(e, tag, true);
            view.setUint16(e + 2, type, true);
            view.setUint32(e + 4, bytes.length / size, true);
            if (bytes.length <= 4) {
                out.set(bytes, e + 8);
            } else {
                view.setUint32(e + 8, data, true);
                out.set(bytes, data);
                data += even(bytes.length);
            }
        });
        view.setUint32(at + 2 + 12 * list.length, 0, true);   // no next IFD
    };
    write(ifd0, ifd0At);
    write(exif, exifAt);
    if (gps.length) write(gps, gpsAt);
    return out;
}

/**
 * THE FIND'S PLACE as a GPS IFD, where the export is asked for it
 * (12.11.2026: „ob auch andere Exif-Daten entfernt werden
 * sollen" — the place became a choice, off unless ticked). The find's
 * coordinates, not the camera's — the same rule as the stamps in the cloud
 * (includes/picture_stamps.php): a micrograph taken at home says where its
 * material grew. Degrees, minutes and seconds as three fractions, the
 * seconds to a thousandth (about 3 cm); the height above sea level where
 * the find has one. Nothing where lat or lon is missing.
 * @param {{lat: number, lon: number, alt?: number}|null|undefined} at
 */
function gpsEntries(at) {
    const lat = Number(at?.lat), lon = Number(at?.lon);
    if (!at || !Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) return [];
    const dms = (x) => {
        const v = Math.abs(x);
        let d = Math.floor(v), m = Math.floor((v - d) * 60), ms = Math.round(((v - d) * 60 - m) * 60000);
        if (ms >= 60000) { ms -= 60000; m += 1; }
        if (m >= 60) { m -= 60; d += 1; }
        return concat(long(d), long(1), long(m), long(1), long(ms), long(1000));
    };
    const out = [
        [0x0000, BYTE, Uint8Array.of(2, 3, 0, 0)],
        [0x0001, ASCII, ascii(lat < 0 ? 'S' : 'N')],
        [0x0002, RATIONAL, dms(lat)],
        [0x0003, ASCII, ascii(lon < 0 ? 'W' : 'E')],
        [0x0004, RATIONAL, dms(lon)],
    ];
    const alt = at.alt === null || at.alt === undefined || at.alt === '' ? NaN : Number(at.alt);
    if (Number.isFinite(alt)) {
        out.push([0x0005, BYTE, Uint8Array.of(alt < 0 ? 1 : 0)]);
        out.push([0x0006, RATIONAL, concat(long(Math.round(Math.abs(alt) * 10)), long(10))]);
    }
    return out;
}

/**
 * The exposure as Exif IFD entries, in the types the standard gives them:
 * time, f-number, focal length as fractions, the compensation as a signed
 * one, the rest as small whole numbers. A value the original did not carry
 * is not written — no zero standing in for „unknown".
 */
function shotEntries(shot) {
    if (!shot) return [];
    const out = [];
    const whole = (x) => Number.isFinite(x) && x >= 0 && x <= 0xFFFF;
    if (shot.exposure > 0) out.push([0x829A, RATIONAL, fraction(shot.exposure, false)]);
    if (shot.fnumber > 0) out.push([0x829D, RATIONAL, fraction(shot.fnumber, false)]);
    if (whole(shot.program)) out.push([0x8822, SHORT, short(shot.program)]);
    if (shot.iso > 0) out.push([0x8827, SHORT, short(Math.min(0xFFFF, Math.round(shot.iso)))]);
    if (Number.isFinite(shot.bias)) out.push([0x9204, SRATIONAL, fraction(shot.bias, true)]);
    if (whole(shot.metering)) out.push([0x9207, SHORT, short(shot.metering)]);
    if (whole(shot.flashCode)) out.push([0x9209, SHORT, short(shot.flashCode)]);
    if (shot.focal > 0) out.push([0x920A, RATIONAL, fraction(shot.focal, false)]);
    if (whole(shot.whiteBalance)) out.push([0xA403, SHORT, short(shot.whiteBalance)]);
    if (whole(shot.focal35) && shot.focal35 > 0) out.push([0xA405, SHORT, short(Math.round(shot.focal35))]);
    if (shot.lens) out.push([0xA434, ASCII, ascii(String(shot.lens).slice(0, 200))]);
    return out;
}

/**
 * A number back into numerator and denominator. exif.js handed over the
 * quotient, so the fraction is found again: the SMALLEST denominator from
 * the list that gives a whole numerator — 1/125 s comes back as 8/1000 or
 * better, −2/3 LW as −2/3, a phone's 200339/1000000000 s exactly as it was.
 * Where none fits, the finest that still fits into 32 bits.
 */
function fraction(x, signed) {
    const limit = signed ? 0x7FFFFFFF : 0xFFFFFFFF;
    let best = null;
    for (const den of [1, 2, 3, 4, 5, 6, 8, 10, 100, 1000, 1e4, 1e5, 1e6, 1e7, 1e8, 1e9]) {
        const num = Math.round(x * den);
        if (Math.abs(num) > limit) break;
        best = [num, den];
        if (Math.abs(x * den - num) < 1e-6) break;
    }
    const [num, den] = best ?? [Math.sign(x) * limit, 1];
    const out = new Uint8Array(8);
    const view = new DataView(out.buffer);
    if (signed) view.setInt32(0, num, true); else view.setUint32(0, num, true);
    view.setUint32(4, den, true);
    return out;
}

/**
 * The XMP packet — the note as dc:description, and the numbers a program
 * could compute with under YASAIMAPP's own namespace. A URN and not an
 * address: the namespace only has to be unique, and no installation of
 * this app owns a domain to speak for all of them.
 */
function xmpPacket(info, note) {
    const factor = (info.from.w / info.to.w).toFixed(4);
    return '<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>\n'
        + '<x:xmpmeta xmlns:x="adobe:ns:meta/">\n'
        + ' <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">\n'
        + '  <rdf:Description rdf:about=""\n'
        + '    xmlns:dc="http://purl.org/dc/elements/1.1/"\n'
        + '    xmlns:tiff="http://ns.adobe.com/tiff/1.0/"\n'
        + '    xmlns:exif="http://ns.adobe.com/exif/1.0/"\n'
        + '    xmlns:yasaimapp="urn:yasaimapp:xmp:1.0/"\n'
        + '    tiff:Orientation="1"\n'
        + `    exif:PixelXDimension="${info.to.w}"\n`
        + `    exif:PixelYDimension="${info.to.h}"\n`
        + `    yasaimapp:OriginalWidth="${info.from.w}"\n`
        + `    yasaimapp:OriginalHeight="${info.from.h}"\n`
        // A developed picture may be cut — its size is no factor of the original's.
        + (info.developed ? '' : `    yasaimapp:ShrinkFactor="${factor}"\n`)
        + (info.hideName ? '' : `    yasaimapp:OriginalName="${xml(info.originalName)}"\n`)
        + '    >\n'
        + (info.artist ? '   <dc:creator>\n    <rdf:Seq>\n'
            + `     <rdf:li>${xml(info.artist)}</rdf:li>\n`
            + '    </rdf:Seq>\n   </dc:creator>\n' : '')
        + (info.copyright ? '   <dc:rights>\n    <rdf:Alt>\n'
            + `     <rdf:li xml:lang="x-default">${xml(info.copyright)}</rdf:li>\n`
            + '    </rdf:Alt>\n   </dc:rights>\n' : '')
        + '   <dc:description>\n'
        + '    <rdf:Alt>\n'
        + `     <rdf:li xml:lang="x-default">${xml(note)}</rdf:li>\n`
        + '    </rdf:Alt>\n'
        + '   </dc:description>\n'
        + '  </rdf:Description>\n'
        + ' </rdf:RDF>\n'
        + '</x:xmpmeta>\n'
        + '<?xpacket end="w"?>';
}

/* --- Small bytes ---------------------------------------------------------- */

/** EXIF's ASCII fields carry UTF-8 here — what exiftool writes too, and a
    note in the user's language would lose its umlauts otherwise. With the
    terminating zero the standard wants, unless asked not to. */
function ascii(text, terminated = true) {
    return utf8(terminated ? text + '\0' : text);
}
function utf8(text) {
    return new TextEncoder().encode(text);
}
function utf16le(text) {
    const out = new Uint8Array(text.length * 2);
    for (let i = 0; i < text.length; i++) {
        const c = text.charCodeAt(i);
        out[2 * i] = c & 0xFF;
        out[2 * i + 1] = c >> 8;
    }
    return out;
}
function short(n) {
    return Uint8Array.of(n & 0xFF, (n >> 8) & 0xFF);
}
function long(n) {
    return Uint8Array.of(n & 0xFF, (n >> 8) & 0xFF, (n >> 16) & 0xFF, (n >>> 24) & 0xFF);
}
function even(n) {
    return n + (n % 2);
}
function concat(...parts) {
    const out = new Uint8Array(parts.reduce((sum, p) => sum + p.length, 0));
    let at = 0;
    for (const p of parts) {
        out.set(p, at);
        at += p.length;
    }
    return out;
}
function xml(text) {
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
/** EXIF's own date notation, local time: „2026:09:26 23:41:07". */
function exifDate(when) {
    const two = (n) => String(n).padStart(2, '0');
    return `${when.getFullYear()}:${two(when.getMonth() + 1)}:${two(when.getDate())} `
        + `${two(when.getHours())}:${two(when.getMinutes())}:${two(when.getSeconds())}`;
}
