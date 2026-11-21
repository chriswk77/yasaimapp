/**
 * „EXPORTIEREN" FOR A PICTURE ARCHIVE'S OLDER PICTURE
 * (13.11.2026: „Original öffnen, wenn damit gemeint ist, Datei downloaden,
 * dann … genauso wie bei dem Bildexport beim Bildbetrachter, dass man da
 * noch ein Wasserzeichen drüber setzen kann, dass man sich die Größe, die
 * Koordinaten … einstellen kann. Beim Verkleinern einschärfen").
 *
 * The same window as a find's picture (exportwindow.js), the same steps on
 * the canvas (exportmark.js) and the same stamp in the file (exifstamp.js) —
 * only the source differs: the archive's ORIGINAL through old_picture.php,
 * which already went through the fence and, where the owner hides his find
 * spots from this viewer, lost its GPS on the server. Nothing is developed;
 * an archive picture has no „Bearbeiten".
 *
 *   picture   the figure window's picture: original, text (its file name),
 *             label, species, date, place, lat, lon, owner (null = mine)
 *   context   {base, style, saveStyle, me} — the app's base address, the
 *             account's export style and how to keep a changed name
 *             pattern, the viewer's own name for his own archive
 */

import { t } from './i18n.js';
import { toast } from './toast.js';
import { openExport, webpCan, exportName, exportDownload } from './exportwindow.js';
import { drawMark, loadLogo, exportSize, scaleCanvas, flatCanvas, sharpenCanvas } from './exportmark.js';
import { readCamera, stampJpeg, stampWebp } from './exifstamp.js';
import { readableDate } from './dates.js';
import { request } from './api.js';

let busy = false;

/**
 * The exporter a page hands its figure window (exportPicture): the account's
 * export style and name from the page's start data (measure_style, user),
 * a changed name pattern kept as the account's, as the find window keeps it.
 */
export function archiveExporter() {
    const start = window.START_DATA ?? {};
    const measureStyle = start.measure_style ?? {};
    const style = () => measureStyle.export ?? { file_name: '', marks: [] };
    const saveStyle = (pattern) => {
        if (!measureStyle.export) return;
        const next = { ...measureStyle.export, file_name: pattern };
        measureStyle.export = next;
        request('measure_style.php', { method: 'POST', body: JSON.stringify({ export: next }) })
            .then((answer) => { if (answer.measure_style?.export) measureStyle.export = answer.measure_style.export; })
            .catch(() => toast(t('Der Dateiname ließ sich nicht merken.'), 'error'));
    };
    const me = String(start.user?.finder || start.user?.name || '').trim();
    return (p) => exportArchivePicture(p, { base: start.baseUrl ?? '', style, saveStyle, me });
}

/** The picture's own name, cleaned as a file name — {Motiv}. */
function motif(p) {
    return String(p.text ?? '').replace(/\.[a-z0-9]{2,5}$/i, '').replace(/[\/\\:*?"<>|\x00-\x1f]+/g, ' ')
        .replace(/\s+/g, ' ').trim().slice(0, 180) || t('Bild');
}

/** What the placeholders stand for. FOR THE FILE NAME the day as 2026-08-14, as every picture's name in the cloud writes it. */
function values(p, me, forFile) {
    return {
        // The archive's owner took the pictures; my own archive is mine.
        Urheber: String(p.owner ?? me ?? '').trim(),
        Jahr: String(new Date().getFullYear()),
        Art: String(p.species ?? ''),
        Datum: p.date ? (forFile ? p.date : readableDate(p.date).split(' ')[0]) : '',
        Fundnummer: '',
        Motiv: motif(p),
        Ort: String(p.place ?? ''),
    };
}

export function exportArchivePicture(p, { base, style, saveStyle, me }) {
    if (busy) return;
    const canPlace = p.lat != null && p.lon != null;
    const opened = openExport({
        style,
        saveFileName: saveStyle,
        // Unknown until the original has come — the window then says „Volle Größe" without numbers.
        source: () => null,
        values: (forFile) => values(p, me, forFile),
        canPlace,
        run: (choice) => make(p, choice, { base, style, me, canPlace }),
    });
    if (!opened) toast(t('Das Exportfenster fehlt auf dieser Seite.'), 'error');
}

async function make(p, { format, quality, size, sharpen, markId, pattern, carry }, { base, style, me, canPlace }) {
    busy = true;
    const webp = format === 'webp' && webpCan();
    const type = webp ? 'image/webp' : 'image/jpeg';
    const mark = (style().marks ?? []).find((m) => m.id === markId) ?? null;
    toast(t('Wird berechnet …'));
    try {
        // THE ORIGINAL, whatever the window shows — a preview would make a small file.
        const answer = await fetch(p.original, { credentials: 'same-origin' });
        if (!answer.ok) throw new Error(t('Das Original kam nicht an.'));
        const original = await answer.blob();
        const { make: camMake, model, shot, taken } = await readCamera(original);
        const img = new Image();
        const at = URL.createObjectURL(original);
        // The load event and not decode(), which a page in the background may leave hanging.
        const ok = await new Promise((resolve) => {
            img.addEventListener('load', () => resolve(true), { once: true });
            img.addEventListener('error', () => resolve(false), { once: true });
            img.src = at;
        });
        setTimeout(() => URL.revokeObjectURL(at), 0);
        if (!ok) throw new Error(t('Das Original ließ sich nicht öffnen.'));

        const full = document.createElement('canvas');
        full.width = img.naturalWidth;
        full.height = img.naturalHeight;
        full.getContext('2d').drawImage(img, 0, 0);
        /* THE LAST STEPS as for a find's picture (photowindow.js, developExport):
           smaller in halving steps, sharpened where it was made smaller, the
           watermark over it at the file's own size. */
        const { w, h, k } = exportSize(full.width, full.height, size);
        let out = full;
        if (k < 1) out = sharpenCanvas(scaleCanvas(full, w, h), sharpen);
        if (mark) {
            if (out === full) out = flatCanvas(full);
            const logo = mark.logo?.on ? await loadLogo(base, mark.logo.file) : null;
            drawMark(out.getContext('2d'), out.width, out.height, mark, values(p, me, false), logo);
        }
        const blob = await new Promise((resolve) => out.toBlob(resolve, type, quality / 100));
        if (!blob) throw new Error(t('Das Bild ließ sich nicht schreiben.'));

        const name = exportName(pattern, values(p, me, true), webp ? 'webp' : 'jpg');
        const gps = carry.place && canPlace ? { lat: Number(p.lat), lon: Number(p.lon), alt: null } : null;
        const owner = values(p, me, false).Urheber;
        const bytes = (webp ? stampWebp : stampJpeg)(new Uint8Array(await blob.arrayBuffer()), {
            from: { w: img.naturalWidth, h: img.naturalHeight },
            to: { w: out.width, h: out.height },
            originalName: String(p.text ?? name),
            when: new Date(),
            developed: false,
            ...(carry.camera ? { make: camMake, model, shot } : {}),
            ...(carry.taken ? { taken } : {}),
            gps,
            // Without the place, no name that could carry it (exifstamp.js, noteText).
            hideName: !carry.place,
            // The author, always, and only the name (photowindow.js says why).
            ...(owner ? { artist: owner, copyright: owner } : {}),
        });
        exportDownload(new File([bytes], name, { type }));
        toast(t('„%s" wird heruntergeladen.', name));
    } catch (e) {
        console.warn('Archivbild exportieren:', e);
        toast(t('Das Bild ließ sich nicht exportieren: %s', e.message || ''), 'error');
    } finally {
        busy = false;
    }
}
