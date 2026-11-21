/**
 * What a JPEG says, before you touch it.
 *
 * TWO THINGS ARE NEEDED HERE, AND BOTH BEFORE DOWNSCALING:
 *
 *   The dimensions. To downscale an image gently on memory, you have to
 *   know beforehand how big it is — then the browser can scale down while
 *   decoding, instead of first building 50 megapixels in RAM. They sit in
 *   the SOF segment, a few hundred bytes into the file.
 *
 *   The capture data. Time, camera, coordinates. The route through the
 *   canvas throws them away — what is not read out here is gone
 *   afterwards.
 *
 * WHY HAND-WRITTEN. It is six values from three directories. Pulling in a
 * library for that would mean carrying along, for six values, everything
 * else EXIF knows — and that in an application that must also start
 * offline.
 *
 * WHAT DOES NOT HAPPEN HERE: Trust. The file comes from someone else's
 * camera app. Every pointer is checked against the length, every loop is
 * bounded, and everything sits inside a try — a broken EXIF may at most
 * lead to a photo arriving without a date. Never to it not arriving at
 * all.
 */

/** The tags that matter. */
const TAG = {
    MAKE:        0x010F,
    MODEL:      0x0110,
    ARTIST:     0x013B,
    ORIENTATION: 0x0112,
    EXIF_POINTER: 0x8769,
    GPS_POINTER:  0x8825,
    TIME_TAKEN: 0x9003,
    TIME_DIGITIZED: 0x9004,
    GPS_LAT_REF: 0x0001,
    GPS_LAT:     0x0002,
    GPS_LON_REF: 0x0003,
    GPS_LON:     0x0004,
    // Only for shot(): how the picture was taken.
    SOFTWARE:        0x0131,
    COPYRIGHT:       0x8298,
    EXPOSURE_TIME:   0x829A,
    F_NUMBER:        0x829D,
    PROGRAM:         0x8822,
    ISO:             0x8827,
    ISO_RECOMMENDED: 0x8832,
    SHUTTER:         0x9201,
    APERTURE:        0x9202,
    EXPOSURE_BIAS:   0x9204,
    METERING:        0x9207,
    FLASH:           0x9209,
    FOCAL_LENGTH:    0x920A,
    WHITE_BALANCE:   0xA403,
    FOCAL_35:        0xA405,
    LENS_MAKE:       0xA433,
    LENS_MODEL:      0xA434,
};

/** Bytes per field type, by the type's index. */
const TYPE_SIZES = [0, 1, 1, 2, 4, 8, 1, 1, 2, 4, 8];

/**
 * The image dimensions from the SOF segment.
 *
 * Cheaper than any decoding: Only the chain of segments is walked until one
 * of them is called "Start of Frame". Height and width sit there in four
 * bytes.
 *
 * @returns {{width:number, height:number}|null}
 */
/**
 * The size a picture will HAVE once the browser has applied its
 * orientation — width and height swapped on the quarter turns (5…8).
 *
 * IT BELONGS HERE, next to dimensions(): that one reads the JPEG frame
 * as it lies in the file, and for a phone's portrait shot that is a
 * LANDSCAPE pair plus orientation 6. Whoever uses the one without the
 * other gets the ratio wrong — which is exactly what happened to the
 * upload's downscale (photos.js, fixed 24.08.2026: portrait photos
 * arrived squashed into landscape).
 */
export function orientedSize(raw, orientation) {
    if (!raw) return null;
    const turned = orientation >= 5 && orientation <= 8;
    return turned
        ? { width: raw.height, height: raw.width }
        : { width: raw.width,  height: raw.height };
}

export function dimensions(buffer) {
    try {
        const d = new DataView(buffer);
        if (d.byteLength < 4 || d.getUint16(0) !== 0xFFD8) return null;

        let p = 2;
        while (p + 4 < d.byteLength) {
            if (d.getUint8(p) !== 0xFF) break;
            const marker = d.getUint8(p + 1);
            const length  = d.getUint16(p + 2);

            // SOF0 through SOF15, without DHT (C4), DNL (C8) and DAC (CC) —
            // those lie in the same number range but are not frames.
            if (marker >= 0xC0 && marker <= 0xCF
                && marker !== 0xC4 && marker !== 0xC8 && marker !== 0xCC) {
                if (p + 9 > d.byteLength) return null;
                return { height: d.getUint16(p + 5), width: d.getUint16(p + 7) };
            }

            if (length < 2) break;   // otherwise the loop marks time in place
            p += 2 + length;
        }
    } catch { /* then not */ }
    return null;
}

/**
 * The capture data.
 *
 * make and model stand beside camera since 26.09.2026, as the camera wrote
 * them: the note a shrunk micrograph carries (exifstamp.js) writes them back
 * into their own two fields, where camera merged them for reading.
 *
 * THE ARTIST since 08.11.2026 — who took it, where the camera was told
 * (many let the owner set a name). The cloud upload proposes it as the
 * picture's author (photogallery.js, askAuthor); it is only a proposal,
 * the person decides.
 *
 * @returns {{taken_at:string|null, camera:string|null, make:string|null,
 *            model:string|null, artist:string|null, lat:number|null, lon:number|null,
 *            orientation:number}}
 */
export function read(buffer) {
    const empty = { taken_at: null, camera: null, make: null, model: null, artist: null, lat: null, lon: null, orientation: 1 };

    try {
        const found = directories(buffer);
        if (found === null) return empty;
        const { values, gps } = found;

        return {
            taken_at: readTime(values[TAG.TIME_TAKEN] ?? values[TAG.TIME_DIGITIZED]),
            camera:         readCamera(values[TAG.MAKE], values[TAG.MODEL]),
            make:           typeof values[TAG.MAKE] === 'string' ? values[TAG.MAKE].trim() || null : null,
            model:          typeof values[TAG.MODEL] === 'string' ? values[TAG.MODEL].trim() || null : null,
            artist:         typeof values[TAG.ARTIST] === 'string' ? values[TAG.ARTIST].replace(/\0+$/, '').trim() || null : null,
            lat:            readDegrees(gps[TAG.GPS_LAT], gps[TAG.GPS_LAT_REF], 90),
            lon:            readDegrees(gps[TAG.GPS_LON], gps[TAG.GPS_LON_REF], 180),
            orientation:    Number(values[TAG.ORIENTATION]) || 1,
        };
    } catch {
        return empty;
    }
}

/**
 * HOW THE PICTURE WAS TAKEN — aperture, time, ISO and the rest, for the
 * info tab of „Bearbeiten" (10.11.2026: „einen Info-Tab, der
 * mir von dem Bild eben die EXIF-Daten anzeigt. Also Blende,
 * Belichtungszeit und so weiter").
 *
 * A SECOND FUNCTION AND NOT MORE FIELDS IN read(): read() feeds the upload
 * and the stamp, and its shape is what they store; these values are only
 * ever shown. Raw numbers come out — seconds, f-number, millimetres —, and
 * the window writes them in the viewer's notation. Whatever the camera did
 * not write is null; an enum the standard does not know is null too,
 * rather than a number nobody can read.
 *
 * @returns {{exposure:number|null, fnumber:number|null, iso:number|null,
 *            focal:number|null, focal35:number|null, bias:number|null,
 *            flash:boolean|null, flashCode:number|null, program:number|null, metering:number|null,
 *            whiteBalance:number|null, lens:string|null, software:string|null,
 *            copyright:string|null}|null}  null when the file has no EXIF at all
 */
export function shot(buffer) {
    try {
        const found = directories(buffer);
        if (found === null) return null;
        const v = found.values;
        const number = (x) => (typeof x === 'number' && Number.isFinite(x) && x > 0 ? x : null);
        const text = (x) => (typeof x === 'string' ? x.replace(/\0+$/, '').trim() || null : null);
        // APEX values where the plain ones are missing: f = 2^(Av/2), t = 2^-Tv.
        const av = typeof v[TAG.APERTURE] === 'number' ? 2 ** (v[TAG.APERTURE] / 2) : null;
        const tv = typeof v[TAG.SHUTTER] === 'number' ? 2 ** -v[TAG.SHUTTER] : null;
        const lens = text(v[TAG.LENS_MODEL]);
        const lensMake = text(v[TAG.LENS_MAKE]);
        return {
            exposure:     number(v[TAG.EXPOSURE_TIME]) ?? number(tv),
            fnumber:      number(v[TAG.F_NUMBER]) ?? number(av),
            iso:          number(v[TAG.ISO]) ?? number(v[TAG.ISO_RECOMMENDED]),
            focal:        number(v[TAG.FOCAL_LENGTH]),
            focal35:      number(v[TAG.FOCAL_35]),
            bias:         typeof v[TAG.EXPOSURE_BIAS] === 'number' && Number.isFinite(v[TAG.EXPOSURE_BIAS]) ? v[TAG.EXPOSURE_BIAS] : null,
            flash:        typeof v[TAG.FLASH] === 'number' ? (v[TAG.FLASH] & 1) === 1 : null,
            // The whole flash word as the camera wrote it, for exifstamp.js to write back.
            flashCode:    typeof v[TAG.FLASH] === 'number' ? v[TAG.FLASH] : null,
            program:      v[TAG.PROGRAM] >= 1 && v[TAG.PROGRAM] <= 8 ? v[TAG.PROGRAM] : null,
            metering:     v[TAG.METERING] >= 1 && v[TAG.METERING] <= 6 ? v[TAG.METERING] : null,
            whiteBalance: v[TAG.WHITE_BALANCE] === 0 || v[TAG.WHITE_BALANCE] === 1 ? v[TAG.WHITE_BALANCE] : null,
            // „Canon" + „EF100mm f/2.8L" — the make only where the model does not say it.
            lens:         lens && lensMake && !lens.toLowerCase().startsWith(lensMake.toLowerCase()) ? `${lensMake} ${lens}` : lens,
            software:     text(v[TAG.SOFTWARE]),
            copyright:    text(v[TAG.COPYRIGHT]),
        };
    } catch {
        return null;
    }
}

/**
 * The values of IFD0 plus the Exif directory, and the GPS directory apart —
 * the walk read() and shot() share. Null where the file is no JPEG or
 * carries no EXIF.
 */
function directories(buffer) {
    const d = new DataView(buffer);
    if (d.byteLength < 4 || d.getUint16(0) !== 0xFFD8) return null;

    // Find the APP1 segment that begins with "Exif\0\0". There are
    // JPEGs with several APP1 — XMP sits there too —, which is why the
    // content is checked and not just the marker.
    let p = 2;
    let tiff = -1;
    while (p + 4 < d.byteLength) {
        if (d.getUint8(p) !== 0xFF) break;
        const marker = d.getUint8(p + 1);
        const length  = d.getUint16(p + 2);
        if (length < 2) break;

        if (marker === 0xE1 && p + 10 < d.byteLength
            && d.getUint32(p + 4) === 0x45786966 && d.getUint16(p + 8) === 0x0000) {
            tiff = p + 10;
            break;
        }
        if (marker === 0xDA) break;   // from here on comes the image data
        p += 2 + length;
    }
    if (tiff < 0 || tiff + 8 > d.byteLength) return null;

    // Endianness: "II" is Intel, so little endian.
    const order = d.getUint16(tiff);
    if (order !== 0x4949 && order !== 0x4D4D) return null;
    const little = order === 0x4949;

    if (d.getUint16(tiff + 2, little) !== 0x002A) return null;
    const ifd0 = tiff + d.getUint32(tiff + 4, little);

    const values = {};
    const gps   = {};

    readIFD(d, tiff, ifd0, little, values);

    if (values[TAG.EXIF_POINTER] != null) {
        readIFD(d, tiff, tiff + values[TAG.EXIF_POINTER], little, values);
    }
    if (values[TAG.GPS_POINTER] != null) {
        readIFD(d, tiff, tiff + values[TAG.GPS_POINTER], little, gps);
    }
    return { values, gps };
}

/**
 * Read in one directory.
 *
 * All pointers are relative to the start of the TIFF header, not to the
 * start of the file — the most common source of misread values.
 */
function readIFD(d, tiff, start, little, out) {
    if (start < 0 || start + 2 > d.byteLength) return;

    const entryCount = d.getUint16(start, little);
    // An upper bound, so a bent counter does not run through half the
    // file. Real directories have a few dozen entries.
    for (let i = 0; i < Math.min(entryCount, 256); i++) {
        const entry = start + 2 + i * 12;
        if (entry + 12 > d.byteLength) return;

        const tag  = d.getUint16(entry, little);
        const type    = d.getUint16(entry + 2, little);
        const count  = d.getUint32(entry + 4, little);
        const size = TYPE_SIZES[type] || 0;
        if (size === 0 || count > 0xFFFF) continue;

        const bytes = size * count;
        // Up to four bytes, the data sits in the entry itself; above that, a
        // pointer sits there.
        const offset = bytes <= 4 ? entry + 8 : tiff + d.getUint32(entry + 8, little);
        if (offset < 0 || offset + bytes > d.byteLength) continue;

        out[tag] = readField(d, offset, type, count, little);
    }
}

function readField(d, offset, type, count, little) {
    switch (type) {
        case 2: {                                   // ASCII
            const bytes = [];
            for (let i = 0; i < count; i++) {
                const c = d.getUint8(offset + i);
                if (c === 0) break;                 // the first NUL is the end
                bytes.push(c);
            }
            /* UTF-8 WHERE IT IS UTF-8 (08.11.2026): the standard says ASCII,
               but a name with an umlaut — the artist „Sebastian Schröder" —
               comes as UTF-8 from every program that writes one, exiftool
               and exifstamp.js included. Read byte by byte it became
               „SchrÃ¶der". Bytes that are no valid UTF-8 stay as they were. */
            try {
                return new TextDecoder('utf-8', { fatal: true }).decode(Uint8Array.from(bytes)).trim();
            } catch {
                return String.fromCharCode(...bytes).trim();
            }
        }
        case 1: return d.getUint8(offset);
        case 3: return d.getUint16(offset, little);
        case 4: return d.getUint32(offset, little);
        case 9: return d.getInt32(offset, little);
        case 5:                                     // RATIONAL
        case 10: {                                  // SRATIONAL
            const nums = [];
            for (let i = 0; i < count; i++) {
                const z = type === 5 ? d.getUint32(offset + i * 8, little)     : d.getInt32(offset + i * 8, little);
                const n = type === 5 ? d.getUint32(offset + i * 8 + 4, little) : d.getInt32(offset + i * 8 + 4, little);
                nums.push(n === 0 ? 0 : z / n);
            }
            return count === 1 ? nums[0] : nums;
        }
        default: return null;
    }
}

/** "2026:08:09 14:23:07" — with colons in the date, as the standard wants. */
function readTime(raw) {
    if (typeof raw !== 'string') return null;
    const t = raw.match(/^(\d{4}):(\d{2}):(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
    if (!t) return null;

    const [, y, mo, day, h, min, s] = t;
    // A point in time without a zone. The camera notes local time, and it
    // should stay local time — turning it into UTC would shift every shot.
    return `${y}-${mo}-${day} ${h}:${min}:${s}`;
}

function readCamera(make, model) {
    const a = typeof make === 'string' ? make.trim()  : '';
    const b = typeof model === 'string' ? model.trim() : '';
    if (!a && !b) return null;
    // "Apple" + "Apple iPhone 15" must not yield "Apple Apple iPhone 15".
    if (b && a && b.toLowerCase().startsWith(a.toLowerCase())) return b;
    return [a, b].filter(Boolean).join(' ');
}

/** Degrees, minutes, seconds to a decimal number. */
function readDegrees(parts, direction, limit) {
    if (!Array.isArray(parts) || parts.length < 3) return null;

    const [g, m, s] = parts.map(Number);
    if (![g, m, s].every(Number.isFinite)) return null;

    let value = g + m / 60 + s / 3600;
    if (direction === 'S' || direction === 'W') value = -value;
    if (!Number.isFinite(value) || Math.abs(value) > limit) return null;

    return Math.round(value * 1e6) / 1e6;
}
