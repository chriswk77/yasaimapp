/**
 * Sending one file straight into the user's own cloud — each provider its
 * own way (26.09.2026, step three of „alle Clouds richtig nutzen").
 *
 * THE SERVER OPENS THE WAY, THIS ONLY CARRIES THE BYTES. The target comes
 * from our server (cloud_upload_target in includes/cloud.php), and the two
 * are one protocol:
 *
 *   pcloud    POST a form to uploadfile, with the token the server handed
 *             out — fenced to a private app folder, the one provider where
 *             that is acceptable (api/v1/cloud.php says why)
 *   gdrive    PUT the file to a resumable session the server opened for
 *             exactly this file; no token here
 *   onedrive  PUT byte ranges to a pre-authenticated session address — and
 *             NO token with it, Microsoft refuses a request that carries
 *             both
 *   s3        PUT the file to an address signed in advance for exactly this
 *             key; the bucket's CORS rule was made sure of by the server
 *
 * THE FILE NEVER TOUCHES OUR SERVER: upload_max_filesize on this hosting
 * is 2 MB, and these pictures are many times that.
 *
 * XMLHttpRequest AND NOT fetch, for one reason: progress. A raw file takes
 * minutes on a phone in the forest, and fetch cannot report upload progress
 * to this day.
 *
 * Used by cloudupload.js (the recordings) and calibrate.js (the calibration
 * images) — one way up, not two.
 */

import { t } from './i18n.js';
import { readCamera, stampJpeg } from './exifstamp.js';
import { cpuCanvas, canvasJpeg, cameOutBlack } from './cpucanvas.js';
import { toast } from './toast.js';

/**
 * @param {object} target  the server's answer to „target"
 * @param {File|Blob} file
 * @param {(share: number) => void} onProgress  0…1
 * @returns {Promise<{fileid: string, name: string, size: number}>}
 */
export function sendToCloud(target, file, onProgress = () => {}) {
    switch (target.provider) {
        case 'gdrive':
            return sendWhole(target.url, file, onProgress, (answer) => {
                const id = String(answer?.id ?? '');
                if (id === '') throw new Error(t('Die Cloud nennt keine Dateikennung.'));
                return { fileid: id, name: answer.name || file.name, size: Number(answer.size) || file.size };
            });
        case 'onedrive':
            return sendRanges(target.url, file, onProgress);
        case 's3':
            // S3 answers an empty 200 — the key was known before the bytes.
            return sendWhole(target.url, file, onProgress, () => ({
                fileid: String(target.key),
                name: String(target.key).split('/').pop(),
                size: file.size,
            }), 'text');
        default:
            return sendPcloud(target, file, onProgress);
    }
}

/**
 * One PUT of the whole file — Drive's session, S3's signed address. The
 * type goes along: Drive and S3 hand back what they were given, and the
 * delivery serves only image/* as a picture.
 */
export function sendWhole(url, file, onProgress, read, responseType = 'json') {
    return new Promise((done, failed) => {
        const request = new XMLHttpRequest();
        request.open('PUT', url);
        request.responseType = responseType;
        request.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
        request.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) onProgress(e.loaded / e.total);
        });
        request.addEventListener('load', () => {
            if (request.status < 200 || request.status >= 300) {
                failed(new Error(t('Die Cloud hat die Datei nicht angenommen (%s).', request.status)));
                return;
            }
            try {
                done(read(request.response));
            } catch (e) {
                failed(e);
            }
        });
        request.addEventListener('error', () => failed(new Error(t('Die Verbindung zur Cloud ist abgerissen.'))));
        request.addEventListener('abort', () => failed(new Error(t('Abgebrochen.'))));
        request.send(file);
    });
}

/* OneDrive takes a session's bytes in ranges of at most 60 MiB, each a
   multiple of 320 KiB but the last. 10 MiB is 32 of those — small enough
   that a torn line costs little, large enough that a raw file is a
   handful of requests. */
const RANGE = 32 * 320 * 1024;

/** OneDrive's session: the file in ranges, one after another. */
async function sendRanges(url, file, onProgress) {
    let item = null;
    for (let start = 0; start < file.size || (file.size === 0 && start === 0); start += RANGE) {
        const end = Math.min(start + RANGE, file.size);
        item = await new Promise((done, failed) => {
            const request = new XMLHttpRequest();
            request.open('PUT', url);
            request.responseType = 'json';
            request.setRequestHeader('Content-Range', `bytes ${start}-${Math.max(end - 1, 0)}/${file.size}`);
            request.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable && file.size > 0) onProgress((start + e.loaded) / file.size);
            });
            request.addEventListener('load', () => {
                // 202 means „go on", 200/201 carry the finished item.
                if (request.status === 202 || request.status === 200 || request.status === 201) {
                    done(request.response);
                } else {
                    failed(new Error(t('Die Cloud hat die Datei nicht angenommen (%s).', request.status)));
                }
            });
            request.addEventListener('error', () => failed(new Error(t('Die Verbindung zur Cloud ist abgerissen.'))));
            request.addEventListener('abort', () => failed(new Error(t('Abgebrochen.'))));
            request.send(file.slice(start, end));
        });
        if (file.size === 0) break;
    }
    const id = String(item?.id ?? '');
    if (id === '') throw new Error(t('Die Cloud nennt keine Dateikennung.'));
    return { fileid: id, name: item.name || file.name, size: Number(item.size) || file.size };
}

/**
 * pCloud: a form POST to uploadfile.
 *
 * renameifexists=1 SO NOTHING IS OVERWRITTEN SILENTLY. Two recordings of the
 * same evening may well be called DSC_0001.jpg — pCloud then makes a second
 * name out of it instead of replacing the first one. Losing a picture in the
 * archive because a camera restarts its counter is not acceptable.
 */
function sendPcloud(target, file, onProgress) {
    return new Promise((done, failed) => {
        const form = new FormData();
        form.append('filename', file, file.name);

        const url = target.url
            + '?access_token=' + encodeURIComponent(target.token)
            + '&folderid=' + encodeURIComponent(target.folderid)
            + '&renameifexists=1'
            // The file's own timestamp, so the archive sorts by when it was
            // taken and not by when it was uploaded.
            + (file.lastModified ? '&mtime=' + Math.floor(file.lastModified / 1000) : '');

        const request = new XMLHttpRequest();
        request.open('POST', url);
        request.responseType = 'json';

        request.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) onProgress(e.loaded / e.total);
        });

        request.addEventListener('load', () => {
            const answer = request.response;
            // pCloud answers with HTTP 200 even for errors and puts the real
            // outcome in the body — the same trap as on the server side.
            if (!answer || answer.result !== 0) {
                failed(new Error(answer?.error || t('pCloud hat die Datei nicht angenommen.')));
                return;
            }
            const meta = (answer.metadata || [])[0] || {};
            const fileid = String((answer.fileids || [])[0] || meta.fileid || '');
            if (fileid === '' || fileid === '0') {
                failed(new Error(t('pCloud nennt keine Dateikennung.')));
                return;
            }
            done({ fileid, name: meta.name || file.name, size: meta.size ?? file.size });
        });

        request.addEventListener('error', () =>
            failed(new Error(t('Die Verbindung zu pCloud ist abgerissen.'))));
        request.addEventListener('abort', () =>
            failed(new Error(t('Abgebrochen.'))));

        request.send(form);
    });
}

/* The long edge a micrograph shrinks to — the same as the cloud
   preview's, on which the maintainer measures anyway (10.09.2026, asked about
   the edge: „2048"). At 40× and some 200 µm of picture width that is
   0.1 µm per pixel — more than enough for spores.

   HERE AND NOT IN cloudupload.js since 26.09.2026: the calibration images
   follow the same switch now (calibrate.js), and one shrinking for both
   is the only way the two stay the same picture of the same thing. */
export const SHRINK_EDGE = 2048;

/**
 * Bring the picture down to the long edge and hand it back as a JPEG.
 *
 * The orientation is burnt in while decoding already
 * (imageOrientation: 'from-image') — without that step a portrait shot
 * would lie on its side. The name keeps its stem and gets .jpg: where the
 * name exists, pCloud and OneDrive add a number themselves
 * (renameifexists, conflictBehavior rename), S3 gets a free name from the
 * server (s3_free_name), and Drive keeps two files of one name apart by
 * their ids.
 *
 * THE PICTURE SAYS WHAT HAPPENED TO IT (26.09.2026): a canvas
 * writes a bare JPEG, and until now the small file lost camera, time and
 * everything else. exifstamp.js writes a note in the user's language —
 * from which size, by which factor, from which file — and carries camera
 * and moment of the shot over, so the file tells its story on any disk,
 * without YASAIMAPP. A note that cannot be written is logged, never a
 * reason to hold the picture back.
 *
 * ON THE PROCESSOR, AND CHECKED (cpucanvas.js, 15.11.2026): an old Intel
 * iMac's Firefox drew every shrunk micrograph black through its graphics
 * card. Should the result still come out black where the picture was not,
 * the picture goes up WHOLE — unshrunk, the caller sees the same file
 * back — and a toast says why.
 *
 * @param {string} originalName  the file's name as the camera gave it — the
 *                               calibration renames its file after the
 *                               preset first, and the note should not say „40x.jpg"
 */
export async function shrunk(bitmap, file, originalName = file.name) {
    const scale = SHRINK_EDGE / Math.max(bitmap.width, bitmap.height);
    const { canvas, g } = cpuCanvas(Math.round(bitmap.width * scale), Math.round(bitmap.height * scale));
    g.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    let blob = await canvasJpeg(canvas, 0.9);
    if (!blob || await cameOutBlack(blob, bitmap)) {
        console.warn('The shrunk picture came out black — the whole one goes up instead:', file.name);
        toast(t('Dieser Rechner hat das Bild beim Verkleinern schwarz gerechnet. Es geht deshalb in voller Größe hoch.'), 'warning');
        return file;
    }
    try {
        const bytes = stampJpeg(new Uint8Array(await blob.arrayBuffer()), {
            ...await readCamera(file),
            originalName,
            from: { w: bitmap.width, h: bitmap.height },
            to:   { w: canvas.width, h: canvas.height },
            when: new Date(),
        });
        blob = new Blob([bytes], { type: 'image/jpeg' });
    } catch (e) {
        console.warn('No note written into the shrunk picture:', e);
    }
    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg', lastModified: file.lastModified });
}

/**
 * The description of a file the server needs to open its way — name,
 * size, type and its own time (seconds). FormData, because the endpoints
 * read $_POST.
 */
export function describe(file, extra = {}) {
    const form = new FormData();
    form.append('name', file.name || 'Datei');
    form.append('size', String(file.size));
    form.append('type', file.type || 'application/octet-stream');
    if (file.lastModified) form.append('mtime', String(Math.floor(file.lastModified / 1000)));
    for (const [k, v] of Object.entries(extra)) form.append(k, v);
    return form;
}
