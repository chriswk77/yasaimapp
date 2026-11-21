/**
 * THE EXPORT WINDOW (includes/export_window.php) — the questions before a
 * picture is downloaded: format and quality, size and sharpening, a
 * watermark template, what the file carries, its name.
 *
 * ONE WINDOW, TWO CALLERS (13.11.2026: „genauso wie bei dem
 * Bildexport beim Bildbetrachter"): the photo window's „Bild exportieren"
 * (photowindow.js, developExport) and the picture archives' photo stage
 * (archiveexport.js). It moved out of photowindow.js that day; what differs
 * between the two — which picture, what its placeholders stand for, how the
 * file is made — is the caller's `host`, handed in each time it opens:
 *
 *   style()            the account's export style (includes/export_style.php)
 *   saveFileName(p)    keep a changed name pattern as the account's
 *   source()           {w, h} the original will have, or null when unknown
 *   values(forFile)    what the placeholders stand for (exportmark.js)
 *   canPlace           whether the find's coordinates may go into the file
 *   run(choice)        make and download the file
 *
 * THE ANSWERS ARE KEPT ON THE DEVICE (settings: exportFormat, exportQuality,
 * exportSize, exportSharpen, exportMark, exportCarry), shared by both
 * callers, so the archive export starts where the last find export ended.
 */

import { t } from './i18n.js';
import { settings } from './settings.js';
import { fillPlaceholders, exportSize, placeholderButtons } from './exportmark.js';

/** Whether this browser writes WebP — asked once, of a canvas of one pixel. */
let webpWritable = null;
export function webpCan() {
    if (webpWritable === null) {
        const c = document.createElement('canvas');
        c.width = c.height = 1;
        webpWritable = c.toDataURL('image/webp').startsWith('data:image/webp');
    }
    return webpWritable;
}

/** The file's name: a pattern (the window's field, the account's by default), then the format's ending. */
export function exportName(pattern, values, ext) {
    const stem = fillPlaceholders(pattern || '{Motiv} bearbeitet', values)
        .replace(/[\/\\:*?"<>|\x00-\x1f]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180);
    return `${stem || values.Motiv}.${ext}`;
}

/** Hand a made file to the browser as a download. */
export function exportDownload(file) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(file);
    a.download = file.name;
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 60_000);
}

/** The window's answers as the device last gave them — format, quality, size, sharpening, template, what it carries. */
function lastChoice(style) {
    const format = settings.get('exportFormat', 'jpeg') === 'webp' && webpCan() ? 'webp' : 'jpeg';
    const quality = Math.min(100, Math.max(50, Number(settings.get('exportQuality', 92)) || 92));
    const kept = settings.get('exportSize', null);
    const size = {
        kind: ['full', 'edge', 'share'].includes(kept?.kind) ? kept.kind : 'full',
        edge: Math.min(8192, Math.max(100, Number(kept?.edge) || 2048)),
        share: Math.min(100, Math.max(5, Number(kept?.share) || 50)),
    };
    const sharpen = ['none', 'light', 'strong'].includes(settings.get('exportSharpen')) ? settings.get('exportSharpen') : 'light';
    const marks = style.marks ?? [];
    const markId = marks.some((m) => m.id === settings.get('exportMark')) ? settings.get('exportMark') : '';
    // What the file carries — the place off, camera and moment on, as before there was a choice.
    const keptCarry = settings.get('exportCarry', null);
    const carry = {
        place: keptCarry?.place === true,
        camera: keptCarry?.camera !== false,
        taken: keptCarry?.taken !== false,
    };
    return { format, quality, size, sharpen, markId, carry };
}

let wired = null;     // the window's elements, once bound
let current = null;   // the host of the export the window is open for

/** Bind the window once per page — the first open does it. */
function wire() {
    if (wired) return wired;
    const win = document.getElementById('photo-export-window');
    if (!win) return null;
    const form = document.getElementById('photo-export-form');
    const quality = document.getElementById('photo-export-quality');
    const qualityShown = document.getElementById('photo-export-quality-shown');
    const nameField = document.getElementById('photo-export-name');
    wired = { win, form, quality, qualityShown, nameField };

    /* THE FILE'S NAME IN THE WINDOW (08.11.2026: „den Dateinamen
       … machen wir gleich beim Fenster schon, im Fenster"): the pattern, a
       button per placeholder that puts it where the caret stands, and the
       name it makes for this very picture beneath. Date and place said as
       the file name writes them. */
    document.getElementById('photo-export-chips')?.append(placeholderButtons(nameField, () => nameSay(), {
        Datum: () => t('das Funddatum, als 2026-08-14'),
        Ort: () => t('der Fundort, als DE,THU, Arenshausen'),
    }));
    quality.addEventListener('input', () => { qualityShown.textContent = `${quality.value} %`; });
    form.addEventListener('input', () => { sizeSay(); nameSay(); });
    form.addEventListener('change', () => { sizeSay(); nameSay(); });
    // A number typed, or a quick button pressed, chooses its answer too.
    for (const [id, kind] of [['photo-export-edge', 'edge'], ['photo-export-share', 'share']]) {
        document.getElementById(id)?.addEventListener('focus', () => {
            form.querySelector(`input[name="photo-export-size"][value="${kind}"]`).checked = true;
            sizeSay();
        });
    }
    document.getElementById('photo-export-quick')?.addEventListener('click', (e) => {
        const b = e.target.closest('[data-edge]');
        if (!b) return;
        document.getElementById('photo-export-edge').value = b.dataset.edge;
        form.querySelector('input[name="photo-export-size"][value="edge"]').checked = true;
        sizeSay();
    });
    document.getElementById('photo-export-cancel')?.addEventListener('click', () => win.close());
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!current) return;
        const host = current;
        const pick = (name, fallback) => form.querySelector(`input[name="${name}"]:checked`)?.value ?? fallback;
        const choice = {
            format: pick('photo-export-format', 'jpeg') === 'webp' ? 'webp' : 'jpeg',
            quality: Number(quality.value) || 92,
            size: {
                kind: pick('photo-export-size', 'full'),
                edge: Math.min(8192, Math.max(100, Number(document.getElementById('photo-export-edge').value) || 2048)),
                share: Math.min(100, Math.max(5, Number(document.getElementById('photo-export-share').value) || 50)),
            },
            sharpen: pick('photo-export-sharpen', 'light'),
            markId: pick('photo-export-mark', ''),
            pattern: nameField.value.trim() || '{Motiv} bearbeitet',
            carry: Object.fromEntries(['place', 'camera', 'taken'].map((k) =>
                [k, !!form.querySelector(`input[name="photo-export-carry"][value="${k}"]`)?.checked])),
        };
        // Where the coordinates may not go, they do not — whatever an earlier export left ticked.
        if (!host.canPlace) choice.carry.place = false;
        /* A pattern changed here is the account's from now on — the same one
           Einstellungen → Fotos → Export shows; the next export starts with it. */
        if (choice.pattern !== (host.style().file_name || '{Motiv} bearbeitet')) host.saveFileName(choice.pattern);
        settings.set('exportFormat', choice.format);
        settings.set('exportQuality', choice.quality);
        settings.set('exportSize', choice.size);
        settings.set('exportSharpen', choice.sharpen);
        settings.set('exportMark', choice.markId);
        // The place's tick is kept only where it could be given: a stage without coordinates leaves it as it was.
        settings.set('exportCarry', host.canPlace ? choice.carry : { ...choice.carry, place: lastChoice(host.style()).carry.place });
        win.close();
        current = null;
        host.run(choice);
    });
    return wired;
}

/** The size the window would make, said under the size question. */
function sizeSay() {
    if (!wired || !current) return;
    const { form } = wired;
    const source = current.source();
    const kind = form.querySelector('input[name="photo-export-size"]:checked')?.value ?? 'full';
    const said = document.getElementById('photo-export-size-result');
    document.getElementById('photo-export-sharpen-field').classList.toggle('is-quiet', kind === 'full');
    if (!source) {
        said.textContent = '';
        document.getElementById('photo-export-size-full').textContent = t('Volle Größe');
        return;
    }
    document.getElementById('photo-export-size-full').textContent = t('Volle Größe (%d × %d Pixel)', source.w, source.h);
    const { w, h } = exportSize(source.w, source.h, {
        kind, edge: Number(document.getElementById('photo-export-edge').value),
        share: Number(document.getElementById('photo-export-share').value),
    });
    said.textContent = kind === 'full' ? '' : t('Das Bild wird %d × %d Pixel groß.', w, h);
}

/** The name the pattern makes for this picture, under the field. */
function nameSay() {
    if (!wired || !current) return;
    const said = document.getElementById('photo-export-name-result');
    if (!said) return;
    const webp = wired.form.querySelector('input[name="photo-export-format"]:checked')?.value === 'webp';
    said.textContent = t('Die Datei heißt: %s', exportName(wired.nameField.value.trim(), current.values(true), webp ? 'webp' : 'jpg'));
}

/**
 * Open the window for one export. Returns false where the page has no
 * export window (a page that never printed export_window.php).
 */
export function openExport(host) {
    const w = wire();
    if (!w) return false;
    current = host;
    const { win, form, quality, qualityShown, nameField } = w;
    const style = host.style();
    const { format, quality: q, size, sharpen, markId, carry } = lastChoice(style);
    for (const box of form.querySelectorAll('input[name="photo-export-carry"]')) box.checked = carry[box.value];
    // No coordinates to give — a friend's archive without its find spots, a picture without a place: no tick for them.
    const placeRow = form.querySelector('input[name="photo-export-carry"][value="place"]')?.closest('.choice-row');
    if (placeRow) placeRow.hidden = !host.canPlace;
    for (const radio of form.querySelectorAll('input[name="photo-export-format"]')) {
        radio.checked = radio.value === format;
        if (radio.value === 'webp') radio.disabled = !webpCan();
    }
    document.getElementById('photo-export-webp-none').hidden = webpCan();
    quality.value = String(q);
    qualityShown.textContent = `${q} %`;
    for (const radio of form.querySelectorAll('input[name="photo-export-size"]')) radio.checked = radio.value === size.kind;
    document.getElementById('photo-export-edge').value = String(size.edge);
    document.getElementById('photo-export-share').value = String(size.share);
    for (const radio of form.querySelectorAll('input[name="photo-export-sharpen"]')) radio.checked = radio.value === sharpen;
    // The templates as radios, „keins" first — all in sight (CLAUDE.md).
    const box = document.getElementById('photo-export-marks');
    box.textContent = '';
    for (const [id, label] of [['', t('keins')], ...(style.marks ?? []).map((m) => [m.id, m.name])]) {
        const row = document.createElement('label');
        row.className = 'choice-row';
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'photo-export-mark';
        radio.value = id;
        radio.checked = id === markId;
        const words = document.createElement('span');
        words.textContent = label;
        row.append(radio, words);
        box.append(row);
    }
    nameField.value = style.file_name || '{Motiv} bearbeitet';
    sizeSay();
    nameSay();
    win.showModal();
    return true;
}
