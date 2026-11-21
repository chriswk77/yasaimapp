/**
 * Handing the full-resolution recordings to one's own cloud.
 *
 * WHAT GOES HERE AND WHAT DOES NOT. The voucher photo of the phone stays on
 * the server, shrunk to 2048 px — that is the picture one sees on the go
 * (photos.js). Here goes everything that weighs: the original from the
 * camera, micrographs, stacks, raw files.
 *
 * THE FILE NEVER TOUCHES OUR SERVER. 'upload_max_filesize' on this hosting is
 * 2 MB, and these pictures are many times that. So the browser talks to the
 * user's cloud directly — each provider its own way, since 26.09.2026 at
 * all four (cloudsend.js).
 *
 * TWO CALLS TO OUR SERVER PER FILE, and nothing in between:
 *
 *   target      which folder does this find belong into? The server names it,
 *               creates it, rearranges the letter if it has tipped over, and
 *               opens the way in for exactly this file — which is why the
 *               file is described first (name, size, type).
 *   record      it is up there, here is its id. Only that is kept here.
 *
 * ONE FILE AT A TIME, deliberately. Parallel uploads share the same uplink
 * and finish later in total, and on a weak connection they all fail together
 * instead of the first ones getting through.
 */

import { request } from './api.js';
import { t } from './i18n.js';
import { sendToCloud, sendWhole, describe, shrunk, SHRINK_EDGE } from './cloudsend.js';
import { cpuCanvas, canvasJpeg, cameOutBlack } from './cpucanvas.js';

/** Bytes the way a human reads them. */
function readableSize(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1).replace('.', ',') + ' GB';
    if (bytes >= 1048576)    return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
    return Math.round(bytes / 1024).toLocaleString('de-DE') + ' kB';
}

/**
 * Upload the chosen files for one point, one after another.
 *
 * THE TARGET IS ASKED FOR EVERY FILE, not once for all of them. It costs one
 * quick call and buys correctness: between the first and the tenth file the
 * letter may tip over, and then the tenth belongs somewhere else than the
 * first. Asking again is the only way to notice.
 *
 * @param {number} pointId
 * @param {FileList|File[]} files
 * @param {(text: string, progress: number|null) => void} report
 * @returns {Promise<{uploaded: number, files: object[], moved: number, shrunk: number}>}
 *          shrunk: how many went up smaller than they came — the message
 *          after the upload must not call such a picture full size
 */
/**
 * @param {?{title: ?string, shrink: boolean, scale?: object}[]} wishes  one per file, in
 *        the same order — asked BEFORE the upload since 10.09.2026
 *        (map.js, askSubject): the subject's name, and whether the file is
 *        shrunk first (micrograph, the switch under Einstellungen → Fotos
 *        → Maßstab-Vorlagen).
 *        The name is set through the same request the picture window's
 *        name field uses — one way, no second version.
 */
export async function uploadForPoint(pointId, files, report, wishes = []) {
    const list = [...files];
    let uploaded = 0;
    let rearranged = 0;
    let shrunkCount = 0;
    let latest = [];

    for (const [i, file] of list.entries()) {
        const which = list.length > 1 ? `${i + 1}/${list.length} ` : '';

        /* THE CAMERA'S MEASURES FIRST, SHRINKING ONLY AFTER — the whole
           scale rests on this. A preset is µm per pixel OF THE CAMERA and
           remembers the width it was calibrated at; all measuring works
           through the ratio „shown width to remembered width" (0095, built
           for the 2048 preview). Shrunk here, the uploaded file is
           technically just one more preview of the same shot: the
           ORIGINAL's measures are remembered, the small bytes uploaded, and
           the conversion does what it always does. Were the small width
           remembered instead, every preset would be off by the factor —
           exactly what „Bildbreite weicht ab" warns about.

           Best effort as before: a RAW file the browser cannot read goes
           up unchanged and without measures; „Volle Größe" adds them the
           first time the picture is looked at. */
        const wish = wishes[i] ?? {};
        let bitmap = null;
        try { bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' }); }
        catch { /* no picture the browser can read */ }
        const dims = bitmap ? { width: bitmap.width, height: bitmap.height } : null;

        let payload = file;
        if (wish.shrink && bitmap && Math.max(bitmap.width, bitmap.height) > SHRINK_EDGE) {
            payload = await shrunk(bitmap, file);
            // The same file back: the shrinking came out black and the whole one goes (cloudsend.js).
            if (payload !== file) {
                shrunkCount++;
                report(t('%s%s → %s statt %s', which, file.name, readableSize(payload.size), readableSize(file.size)), 0);
            }
        }

        /* THE TARGET ONLY NOW, with the file as it will go up (26.09.2026):
           Drive and OneDrive open their session for exactly this name and
           size, S3 signs exactly this key — so the shrinking comes first. */
        report(t('%s%s — Ziel wird bestimmt …', which, payload.name), null);
        let target;
        try {
            target = await request(`cloud.php?id=${pointId}&action=target`,
                { method: 'POST', body: describe(payload) });
        } catch (e) {
            bitmap?.close();
            throw e;
        }
        rearranged += target.moved || 0;

        // NAME THE TARGET BEFORE SENDING. Where a recording lands follows
        // from the genus, and for an open determination the genus is
        // guessed from the name alone — whoever sees the folder here
        // notices a wrong guess now, instead of finding it in the cloud
        // weeks later.
        report(`${which}${payload.name} → ${target.path}`, 0);

        let up;
        try {
            up = await sendToCloud(target, payload, (p) =>
                report(`${which}${payload.name} (${readableSize(payload.size)}) …`, p));
            /* S3 DRAWS NO PREVIEWS — the browser lays its own beside the
               picture, at the two edges the app shows (the server handed
               out their addresses). Best effort: without them the tile
               loads the picture itself. */
            if (target.previews && bitmap) {
                await previewsUp(bitmap, target.previews);
            }
        } finally {
            bitmap?.close();
        }

        // Only now does our server learn of it — and only the reference.
        // FormData and not JSON: the endpoint reads $_POST, and PHP only
        // fills that for form-encoded bodies. request() then leaves the
        // Content-Type to the browser, which is the only one that knows its
        // own boundary.
        const note = new FormData();
        note.append('fileid',   String(up.fileid));
        note.append('folderid', String(target.folderid));
        note.append('name',     up.name);
        note.append('path',     target.path);
        note.append('size',  String(up.size ?? ''));
        /* The picture's ORIGINAL dimensions (migration 0095) — measured
           ABOVE, before any shrinking, and that order is the whole point:
           they let a scale preset stamp onto the preview later, and onto a
           shrunk file just the same. */
        if (dims) {
            note.append('width',  String(dims.width));
            note.append('height', String(dims.height));
        }
        // Who took it, where it was not the uploader (photogallery.js, askAuthor).
        if (wish.takenBy) note.append('taken_by', String(wish.takenBy));

        const answer = await request(`cloud.php?id=${pointId}&action=record`,
            { method: 'POST', body: note });
        latest = answer.files || [];

        // The name, right after. Only now is there the file's id it hangs
        // on — and should it fail, the picture is up regardless: a missing
        // name is a nuisance, a lost picture a damage.
        /* FROM THE WISH, NOT FROM A LIST THAT NO LONGER EXISTS
           (12.09.2026: „der Motivname wird nicht angewendet — die Bilder
           werden trotzdem ohne Motivname geladen"). When the list of names
           became the wishes (macro or micro, with the shrinking),
           `titles[i]` stayed behind here — a variable nobody has filled
           since. Bytes up, row written, then this line threw, and the
           picture stood there without a name. The loop further up reads
           the same wish for the shrinking. */
        const title = wish.title ?? null;
        /* AND THE SCALE IN THE SAME REQUEST, where the picture came with
           one (the microscope's still and its objective, microscope.js) —
           the action takes name and scale together, and both are notes of
           ours about the picture. */
        const scale = wish.scale ?? null;
        /* And the media the slide lay in (0187), the same way — ticked in
           the microscope window (media.js). */
        const media = Array.isArray(wish.media) && wish.media.length > 0 ? wish.media : null;
        if (title || scale || media) {
            const fresh = (answer.files || []).find(f => String(f.fileid) === String(up.fileid));
            if (fresh) {
                try {
                    const set = await request(`cloud.php?id=${pointId}&action=scale`, {
                        method: 'POST',
                        body: JSON.stringify({ file: fresh.id, ...(title ? { title } : {}), ...(scale ? { scale } : {}),
                            ...(media ? { media } : {}) }),
                    });
                    /* The answer carries the one file, not the list — so
                       the name goes into the list handed on, here.
                       Otherwise the gallery showed the picture under
                       „Ohne Motivnamen" until the next fetch. */
                    if (title) fresh.title = title;
                    if (scale) fresh.scale = set.scale ?? scale;
                    if (media) fresh.media = set.media ?? media;
                } catch (e) {
                    console.warn('Subject name or scale not set:', e);
                }
            }
        }
        uploaded++;
    }

    return { uploaded, files: latest, moved: rearranged, shrunk: shrunkCount };
}

/**
 * The two previews S3 cannot draw itself: 320 for the tile, 2048 for the
 * viewer, as JPEG like every preview. A failed one is only logged — and so
 * is a black one (cpucanvas.js): without its preview the tile loads the
 * picture itself, which is better than a black tile.
 */
async function previewsUp(bitmap, addresses) {
    for (const [edge, url] of [[320, addresses.small], [2048, addresses.large]]) {
        if (!url) continue;
        try {
            const scale = Math.min(1, edge / Math.max(bitmap.width, bitmap.height));
            const { canvas, g } = cpuCanvas(Math.max(1, Math.round(bitmap.width * scale)), Math.max(1, Math.round(bitmap.height * scale)));
            g.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            const blob = await canvasJpeg(canvas, 0.85);
            if (blob && await cameOutBlack(blob, bitmap)) {
                console.warn('Preview came out black, not laid down:', edge);
                continue;
            }
            if (blob) await sendWhole(url, blob, () => {}, () => null, 'text');
        } catch (e) {
            console.warn('Preview not laid down:', e);
        }
    }
}


/* SHRINK_EDGE handed on, for the message after an upload: a picture that
   went up smaller is named so (photogallery.js). */
export { readableSize, SHRINK_EDGE };
