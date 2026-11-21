/**
 * Voucher photos on the points.
 *
 * WHAT THESE PICTURES ARE. Memory aids: where the mushroom stood, what the
 * surroundings looked like, what you recognize the spot by. The actual
 * voucher is made alongside with a proper camera and gets stacked. Hence
 * 2048 px long edge — that reminds reliably and costs 500 kB instead of
 * 6 MB.
 *
 * THE ROUTE THROUGH THE CAMERA APP, not through a viewfinder of our own.
 * An <input type="file" capture> opens the phone's camera, and with that
 * everything the device can do is available: all lenses, macro, HDR, night
 * mode, focus stacks — and the full sensor resolution. A home-built
 * viewfinder via getUserMedia gets almost none of that: usually a single
 * logical rear camera, a video frame instead of a capture, and none of
 * the computational photography at all.
 *
 * THE MICROSCOPE CAMERA IS THE ONE EXCEPTION, and none of the above speaks
 * against it (microscope.js, 24.10.2026). There is no camera app to hand
 * over to: it is no webcam, and no browser or system dialog can take a
 * picture with it. It has one fixed sensor, no lenses to choose and no
 * computational photography to lose. And the picture that is kept is not a
 * frame of the live video but the full-resolution still the MicroCam app
 * fetches from the sensor itself — the viewfinder only aims.
 *
 * WHAT TO WATCH OUT FOR: The page goes into the background while the
 * camera is in front, and Android may tear it down there. That is why the
 * form's state is written away before opening — see formstate.js.
 *
 * MEMORY IS THE ACTUAL DANGER. A 50-megapixel image as a bitmap is around
 * 200 MB. Two of those at once, and the tab is gone — along with
 * everything not yet transferred. Hence here: read the dimensions from the
 * file header beforehand, let the browser scale down while decoding, only
 * ever one image at a time, and close the bitmap immediately.
 */

import { api } from './api.js';
import { t } from './i18n.js';
import * as exif from './exif.js';
import { stampJpeg, cameraOf, shotOf } from './exifstamp.js';
import * as store from './photostore.js';
import { cpuCanvas, canvasJpeg, cameOutBlack } from './cpucanvas.js';
import { toast } from './toast.js';

export const EDGE          = 2048;
export const PREVIEW_EDGE = 320;
const QUALITY          = 0.82;
const PREVIEW_QUALITY = 0.7;

/**
 * WITHOUT A CLOUD THE PICTURE STAYS SMALLER — and stays here for good.
 *
 * The call of 19.08.2026. Where a storage of the user's own is
 * connected, this server is a waiting room: the 2048 px picture passes
 * through and is gone from the disk minutes later, so its half megabyte
 * costs nothing lasting. Where there is none, the picture stays — and then
 * it is a thumbnail and nothing more.
 *
 * NOT THE EDGE ALONE, and that is the point of the second number. The size
 * of a JPEG cannot be computed in advance — it depends on what is in it; a
 * patch of sky and a patch of moss at the same pixel count differ by a
 * factor of ten. So it is tried: full quality first, and only where that is
 * too big does it step down.
 *
 * WHY 1000 AND NOT LESS. 800 was tried and looked visibly worse, and the
 * reason is not the missing pixels alone. Measured on ONE hard picture
 * (fine noise, like a forest floor of moss and needles), both sizes from
 * the same source:
 *
 *   edge   pixels   at 0.82   within budget   quality   bytes/pixel
 *   1000    750k     234 kB      116 kB        0.58        0.16
 *    800    480k     212 kB      112 kB        0.46        0.24
 *
 * THE BUDGET IS ABSOLUTE, NOT PER PIXEL. A smaller picture therefore does
 * not get an easier task: it starts out more expensive per pixel — the
 * downscale packs the same high frequencies into fewer of them — and has
 * to be pushed harder to reach the same 125 kB. So 800 costs twice: a
 * third of the pixels, and a coarser quantisation of what is left.
 *
 * (Two measurements taken on separately generated noise had suggested the
 * opposite for a moment. They were not comparable — each run made its own
 * picture. Same source, or the numbers say nothing.)
 *
 * The floor at 0.45 is there so a budget can never turn a picture into
 * mush: better a note of 160 kB one recognises something on than one of
 * 125 one does not. Real photographs stay well under it — the noise is the
 * worst case, not the usual one.
 *
 * IT IS DECIDED HERE AND NOT ON THE SERVER, because here it also saves the
 * mobile connection: what is never encoded is never sent. The price is that
 * whoever connects a cloud LATER has their older pictures at 1000 px. They
 * are notes, and a note that reminds is doing its job.
 */
export const EDGE_WITHOUT_CLOUD = 1000;
const BYTES_WITHOUT_CLOUD  = 125 * 1024;
const QUALITY_MIN       = 0.45;

/**
 * What the operation is doing right now — for the display.
 *
 * THE KEYS AND THE VALUES ARE A CONTRACT with the only listener there is
 * (map.js). It has already been broken once: here stood {art, grund},
 * over there it was read as {type, reason} — the rename translated one
 * side and not the other. Nothing complained, because a listener that
 * compares against undefined simply does nothing. The result was a
 * voucher photo that hung under its hourglass for ever: the gallery was
 * never redrawn, and the rejection message was never shown.
 *
 * Everything English, values included, so no half-translation can hide
 * here a second time.
 */
const listeners = new Set();
export function onChange(f) { listeners.add(f); return () => listeners.delete(f); }
function report(payload) { for (const f of listeners) { try { f(payload); } catch { /* display */ } } }

// ---------------------------------------------------------------------------
// Downscaling
// ---------------------------------------------------------------------------

/**
 * Turn a file into two JPEGs: image and preview.
 *
 * @returns {Promise<{image:Blob, preview:Blob, width:number, height:number, meta:object}>}
 */
/** Does this installation have somewhere to move the pictures on to? */
function cloudConnected() {
    return window.START_DATA?.cloudConnected === true;
}

export async function prepare(file) {
    const buffer = await file.arrayBuffer();
    const edgeTarget = cloudConnected() ? EDGE : EDGE_WITHOUT_CLOUD;

    // Read first, then touch: What the canvas puts out has no EXIF anymore.
    // What does not come out here is gone afterwards.
    const meta = exif.read(buffer);
    const raw  = exif.dimensions(buffer);

    // Tell the browser how small it should become — then it never builds
    // the big bitmap in the first place. Only possible when the dimensions
    // are already known; otherwise the detour via the full image remains.
    const options = { imageOrientation: 'from-image' };
    /* THE TARGET MUST BE MEASURED AS THE PICTURE WILL LOOK, not as it
       lies in the file (24.08.2026: a portrait shot arrived
       squashed into landscape). dimensions() reads the JPEG frame — for
       a phone's portrait photo that is 4000×3000 WITH orientation 6, and
       'from-image' above makes the browser turn it to 3000×4000. Handing
       it the unturned pair then means: two fixed edges of the wrong
       ratio, and by the specification that is a stretch, not a crop.
       Orientations 5 to 8 are the quarter turns; for them the two swap. */
    const oriented = exif.orientedSize(raw, meta.orientation);
    if (oriented && Math.max(oriented.width, oriented.height) > edgeTarget) {
        const f = edgeTarget / Math.max(oriented.width, oriented.height);
        options.resizeWidth   = Math.max(1, Math.round(oriented.width * f));
        options.resizeHeight  = Math.max(1, Math.round(oriented.height * f));
        options.resizeQuality = 'high';
    }

    let bitmap;
    try {
        bitmap = await createImageBitmap(file, options);
    } catch {
        // Older browsers do not know the resize options and reject the whole
        // call. Then without — more expensive, but it works.
        bitmap = await createImageBitmap(file);
    }

    try {
        // In case the browser ignored the requested dimensions (it happens),
        // the math is done here once more. Belt and braces is cheaper than
        // an 8000 px wide JPEG in the upload.
        const image = cloudConnected()
            ? await draw(bitmap, EDGE, QUALITY)
            : await drawWithin(bitmap, EDGE_WITHOUT_CLOUD, QUALITY, BYTES_WITHOUT_CLOUD);
        /* A BLACK COPY IS NO COPY (cpucanvas.js, 15.11.2026: an old iMac's
           Firefox drew every redrawn photo black). Drawn on the processor
           it should never be; should it be all the same, the photo is not
           taken rather than kept black — capture() names it, and a toast
           says why. */
        if (await cameOutBlack(image, bitmap)) {
            toast(t('Dieser Rechner hat das Foto beim Verkleinern schwarz gerechnet. Es wurde nicht übernommen.'), 'error');
            throw new Error('redrawn black');
        }

        // The preview from the already downscaled bitmap, not from the
        // original: Jumping from 6000 to 320 in one step looks frayed, and
        // a second decoding of the original would be exactly the memory
        // consumption to be avoided.
        const preview = await draw(bitmap, PREVIEW_EDGE, PREVIEW_QUALITY);

        const [width, height] = edge(bitmap.width, bitmap.height, edgeTarget);

        /* THE SAME NOTE AS THE MICROGRAPHS (26.09.2026: „gib den
           Belegfotos dieselbe Notiz mit"). The canvas above wrote a bare
           JPEG — without it the picture that later moves into the user's
           cloud would not know its own camera, its moment or that it was
           ever larger (exifstamp.js says the rest). The server keeps the
           bytes as they come (move_uploaded_file) and the cloud push
           carries them on, so the note arrives wherever the picture goes.
           The original size is the one the picture HAD — measured before
           the shrinking, turned as the browser turned it; only when the
           file told nothing is it the bitmap's, which then was built at
           full size.

           NO COORDINATES IN THE FILE, deliberately: a voucher photo is
           shown to whoever may see the find, and a position written into
           the file would travel on with every copy, beyond the rules that
           guard it in the app. It stays in the database (exif_lat,
           exif_lon below). The preview needs no note — it is the tile.
           Its kilobyte and a half rides outside the budget of the way
           without a cloud: 125 kB is an aim, the server's ceiling is 4 MB
           (PHOTO_BYTES_MAX). */
        const stampedImage = await withNote(image, {
            ...cameraOf(meta),
            // Aperture, time, ISO, lens — the canvas dropped them, the stamp writes them back.
            ...shotOf(exif.shot(buffer)),
            originalName: file.name || '',
            from: oriented ? { w: oriented.width, h: oriented.height } : { w: bitmap.width, h: bitmap.height },
            to:   { w: width, h: height },
            when: new Date(),
        });

        return {
            image:     stampedImage,
            preview: preview,
            width:   width,
            height:    height,
            meta: {
                taken_at: meta.taken_at,
                camera:         meta.camera,
                exif_lat:       meta.lat,
                exif_lon:       meta.lon,
                original_name:  file.name || '',
            },
        };
    } finally {
        // Even if something went wrong above. An unclosed bitmap lies
        // around until the collector deigns to act — and until then, with
        // fifty shots, that is several gigabytes.
        bitmap.close?.();
    }
}

/** The note into a JPEG blob — or the blob as it was, when that fails:
    a missing note is never a reason to hold a photo back. */
async function withNote(blob, info) {
    try {
        return new Blob([stampJpeg(new Uint8Array(await blob.arrayBuffer()), info)], { type: 'image/jpeg' });
    } catch (e) {
        console.warn('No note written into the voucher photo:', e);
        return blob;
    }
}

function edge(width, height, target) {
    const f = Math.min(1, target / Math.max(width, height));
    return [Math.max(1, Math.round(width * f)), Math.max(1, Math.round(height * f))];
}

/**
 * Encode until it fits the budget.
 *
 * THE SIZE OF A JPEG CANNOT BE COMPUTED IN ADVANCE — it depends on what is
 * in the picture. A patch of sky and a patch of moss at the same pixel
 * count differ by a factor of ten. So it is tried: full quality first, and
 * only where that is too big does it step down.
 *
 * The floor is there so a budget can never turn a picture into mush. Better
 * a note of 160 kB that one recognises something on than one of 125 that
 * one does not.
 */
async function drawWithin(bitmap, target, quality, budget) {
    let blob = await draw(bitmap, target, quality);
    let q = quality;

    while (blob.size > budget && q > QUALITY_MIN) {
        q = Math.max(QUALITY_MIN, q - 0.12);
        blob = await draw(bitmap, target, q);
    }
    return blob;
}

/* ON THE PROCESSOR, offscreen where the browser has it (cpucanvas.js says
   why: a graphics card that draws black). */
async function draw(bitmap, target, quality) {
    const [b, h] = edge(bitmap.width, bitmap.height, target);
    const { canvas, g } = cpuCanvas(b, h, true);
    g.imageSmoothingQuality = 'high';
    g.drawImage(bitmap, 0, 0, b, h);
    const blob = await canvasJpeg(canvas, quality);
    if (!blob) throw new Error(t('Das Bild lässt sich nicht umwandeln.'));
    return blob;
}

// ---------------------------------------------------------------------------
// Capturing
// ---------------------------------------------------------------------------

/**
 * Take in files for a point.
 *
 * They ALWAYS go into our own store first and only then to the network.
 * The reverse route — try, and only stash on failure — loses something
 * precisely when the tab dies in the middle of the upload.
 *
 * One after the other, not all at once: Five parallel decodings are five
 * times the memory.
 *
 * @returns {Promise<{captured:number, errors:string[]}>}
 */
/**
 * @param {?string[]} titles  the subject's name per file, in the same
 *        order — since 10.09.2026 it is asked for at the shutter and no
 *        longer added afterwards (map.js, askSubject). Missing, the picture
 *        stays unnamed; that is the honest answer to „Ohne Namen".
 */
/**
 * @param {{hold?: boolean}} options  hold: the shrunk copies wait here and
 *        do NOT go up — the safety net while the full-size shot travels
 *        straight into the cloud (map.js). release() lets one go, drop()
 *        throws it away; out.ids names them, one per file (null for a file
 *        that could not be read).
 */
export async function capture(pointId, files, titles = [], { hold = false } = {}) {
    const out = { captured: 0, errors: [], ids: [] };
    if (!files || files.length === 0) return out;

    // Ask once whether we may stay — before the first image, not on each
    // one.
    await store.requestPersistence();

    for (const [i, file] of files.entries()) {
        try {
            const prepared = await prepare(file);
            const id = crypto.randomUUID();
            await store.remember({
                id,
                pointId,
                // Held by THIS page — see HOLD below.
                ...(hold ? { held: HOLD } : {}),
                image:     prepared.image,
                preview: prepared.preview,
                width:   prepared.width,
                height:    prepared.height,
                /* THE MOTIVNAME TRAVELS ALONG IN THE META. upload()
                   attaches everything in it as an ordinary form field,
                   and photo_create reads it there — one path, no second. */
                meta:     titles[i] ? { ...prepared.meta, title: titles[i] } : prepared.meta,
            });
            out.captured++;
            out.ids.push(id);
            report({ type: 'captured', pointId });
        } catch (e) {
            console.warn('Photo cannot be prepared:', file?.name, e);
            out.errors.push(file?.name || t('Unbenanntes Bild'));
            out.ids.push(null);
        }
    }

    // Do not wait for it: The images are stored safely, the upload may run
    // in the background. The interface should not wait on the line.
    transfer();

    return out;
}

// ---------------------------------------------------------------------------
// Transferring
// ---------------------------------------------------------------------------

let running = false;
/* A nudge that came during a run. See transfer(). */
let again = false;

/* THE SAFETY NET OF A FULL-SIZE SHOT (03.10.2026). While the
   whole picture travels straight into the cloud, its shrunk copy waits
   here, marked with THIS page's mark. Arrives the whole one, the copy is
   thrown away (drop); breaks it off — reception gone, the cloud refusing —
   the copy goes up instead (release). And if the page itself dies on the
   way, the next page carries another mark: the copy is no longer held
   and goes up like any other. The worst case is the smaller picture,
   never none — a camera shot taken through the browser is often in no
   gallery on the phone. The one price: the page dying in the very moment
   the whole picture has arrived leaves both, the whole and the copy. */
const HOLD = crypto.randomUUID();

/** Let a held copy go up after all — the full-size shot did not arrive. */
export async function release(id) {
    const entry = (await store.all()).find((e) => e.id === id);
    if (!entry) return;
    delete entry.held;
    await store.remember(entry);
    transfer();
}

/** Throw a held copy away — the full-size shot is in the cloud. */
export async function drop(id) {
    const entry = (await store.all()).find((e) => e.id === id);
    await store.remove(id);
    if (entry) report({ type: 'dropped', pointId: entry.pointId });
}

/**
 * Empty the store, as far as the network cooperates.
 *
 * ONE AFTER THE OTHER. Fifty simultaneous uploads over a bad line mean
 * fifty timeouts; one at a time, at least the first gets through. And
 * whoever loses the connection along the way then has the first twenty up
 * instead of none.
 */
export async function transfer() {
    if (!navigator.onLine) return;

    // A NUDGE DURING A RUN MUST NOT GET LOST. Here stood
    // "if (running) return;", and out in the field that left a voucher photo
    // lying:
    //
    //   page build     transfer() starts, reads the list. The photo still
    //                  carries the provisional id of its point and gets
    //                  skipped.
    //   queue          The point gets through and receives its real id.
    //                  swapPointKey() rewrites it on the photo and
    //                  calls transfer() — which turns back silently,
    //                  because the first run is still at it.
    //   first run      works its OLD list to the end and reports "done".
    //
    // The photo lay there with the hourglass afterwards until someone
    // reloaded the page. Nothing looked broken — there was just nothing
    // left to nudge it.
    //
    // A remembered nudge is the small solution for that, and catches the
    // same case also when a new photo appears during the transfer.
    if (running) { again = true; return; }
    running = true;

    try {
        do {
            again = false;
            // false means: What is missing is the network, not a nudge.
            // Then another round does not help either.
            if (!(await oneRound())) break;
        } while (again);
    } finally {
        running = false;
    }
}

/**
 * One round through the store.
 *
 * @return {Promise<boolean>} false if it was aborted and another round
 *         would bring nothing
 */
async function oneRound() {
    try {
        const waiting = await store.all();
        if (waiting.length === 0) return true;

        report({ type: 'starting', openCount: waiting.length });

        for (let i = 0; i < waiting.length; i++) {
            const entry = waiting[i];

            // The point itself is not up yet. Then this photo has nowhere
            // to go — it stays put until the queue has submitted the point
            // and rewritePoint() has swapped the id.
            if (!(entry.pointId > 0)) continue;
            // Held by this page while its whole picture travels (HOLD).
            if (entry.held === HOLD) continue;

            try {
                const response = await upload(entry);
                await store.remove(entry.id);
                report({ type: 'transferred', pointId: entry.pointId, photo: response, entryId: entry.id, openCount: waiting.length - i - 1 });
            } catch (e) {
                if (isTransient(e)) {
                    // No network anymore, session expired, server coughing:
                    // leave it standing and continue later. Abort rather
                    // than carry on — the next ones fail just the same, and
                    // fifty failed attempts only cost battery.
                    report({ type: 'waiting', reason: e?.message || '' });
                    return false;
                }

                // The server will never accept it: too big, quota full,
                // point deleted. Leaving it lying would mean trying for all
                // eternity and blocking everything behind it while at it.
                console.warn('Photo finally rejected:', entry.id, e);
                await store.remove(entry.id);
                report({ type: 'rejected', pointId: entry.pointId, reason: e?.message || t('unbekannter Grund') });
            }
        }

        report({ type: 'done', openCount: await store.count() });
        return true;
    } catch (e) {
        console.warn('Photo transfer failed:', e);
        // The error did not come from a single photo but from reading the
        // store itself. Another round would run into the same error.
        return false;
    }
}

function upload(entry) {
    const f = new FormData();
    f.append('point_id', String(entry.pointId));
    f.append('client_id', entry.id);

    // THE NAMES ARE THE CONTRACT WITH api/v1/photos.php, and they have
    // already drifted apart once: the endpoint read $_FILES['image'] while
    // this here still sent 'bild'. tests/endpoints.mjs now holds the two
    // together.
    f.append('image', entry.image, 'image.jpg');
    if (entry.preview) f.append('preview', entry.preview, 'preview.jpg');

    for (const [key, value] of Object.entries(entry.meta || {})) {
        if (value !== null && value !== undefined && value !== '') {
            f.append(key, String(value));
        }
    }

    return api.senden('photos', f);
}

/** The same distinction as in the queue — see there. */
function isTransient(e) {
    if (e?.isNetworkError === true || e instanceof TypeError || !navigator.onLine) return true;
    // An answer that is not JSON did not come from the application at all
    // — see api.js. Keeping the photo is the only safe reading: it is the
    // only copy.
    if (e?.notJson === true) return true;
    const s = e?.status;
    return s === 401 || s === 408 || s === 419 || s === 429 || (s >= 500 && s <= 599);
}

// ---------------------------------------------------------------------------
// Connection to the rest
// ---------------------------------------------------------------------------

/**
 * A point has received its real id.
 *
 * To be called from the same place that also rewrites the map. Then keep
 * transferring right away: The photos that were just waiting for this
 * point can go up now.
 */
export async function swapPointKey(oldId, newId) {
    const count = await store.rewritePoint(oldId, newId);
    if (count > 0) transfer();
    return count;
}

/** A point is gone before its photos were up. */
export function forgetPoint(pointId) {
    return store.forgetPoint(pointId);
}

/** What still waits for this point — for display next to the finished ones. */
export function pending(pointId) {
    return store.forPoint(pointId);
}

export function pendingCount() {
    return store.count();
}

// As soon as there is network again. The same signal that also wakes the
// queue — standalone here, so the two stay independent of each other.
window.addEventListener('online', () => transfer());
