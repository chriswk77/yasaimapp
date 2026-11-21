/**
 * Calibrating from a photographed stage micrometer
 * (Verwaltung → Einstellungen → Fotos → Maßstab-Vorlagen,
 * 28.08.2026) — in a window over the whole screen since 26.09.2026,
 * built like the photo window: name and true length on top, the picture
 * in the middle, zoom and the concluding buttons below.
 *
 * THE MEASURING HAPPENS BEFORE THE UPLOAD, while the picture is still
 * local in the browser — that is the whole trick: upload_max_filesize is
 * 2 MB on this hosting, and a full-resolution micrograph could never ride
 * the settings form. What the form submits is only numbers: the drawn
 * line in image pixels, the true length, the image's dimensions.
 *
 * The picture itself then goes to the user's cloud DIRECTLY, browser to
 * provider, into the „Kalibrierung" folder — the same road every recording
 * takes, through the same sender (cloudsend.js), each cloud its own way. ONLY AN ARRIVED
 * PICTURE MAKES A PRESET since 26.09.2026 („Ein
 * fehlgeschlagener Upload legt keine Vorlage an"): the form is sent once
 * the cloud has answered with the picture's id, never before.
 *
 * Zoom and pan come from imagezoom.js — the same machinery as the photo
 * window, because aiming at a micrometer line happens zoomed in.
 */

import * as imageZoom from './imagezoom.js';
import { t } from './i18n.js';
import { sendToCloud, describe, shrunk, SHRINK_EDGE } from './cloudsend.js';
import { prompt } from './dialogs.js';

/* SELF-ATTACHING WITH A WATCHER, like autoheight.js — and here it is not
   politeness but survival: the administration renders its tab panels
   LAZILY (tab_content() emits only a <div data-tab-src> placeholder, the
   content arrives by fetch). At module evaluation #calibrate-window does
   not exist yet — measured 28.08.2026: the module ran, found nothing,
   and the finished form sat there stone dead. The observer inits
   whatever injection brings, once per node. */
function setUp(win) {
    if (win.dataset.bound) return;
    win.dataset.bound = '1';

    const form    = document.getElementById('calibrate-form');
    const file    = document.getElementById('calibrate-file');
    const stage   = document.getElementById('calibrate-stage');
    const image   = document.getElementById('calibrate-image');
    const overlay = document.getElementById('calibrate-overlay');
    const hintEl  = document.getElementById('calibrate-hint');
    const save    = document.getElementById('calibrate-save');
    const empty   = document.getElementById('calibrate-empty');
    const other   = document.getElementById('calibrate-other');

    let points  = [];      // the calibration line's taps, in the SHOWN picture's pixels
    let ownUrl  = null;    // the object URL, revoked on replacement
    let chosen  = null;    // the picked File, for the archive upload
    /* The camera's pixels of a STORED calibration image — its preset's
       width and height. Null for a picture just chosen: that one IS the
       camera's full picture. */
    let native  = null;

    const zoom = imageZoom.bind(stage, image, {
        mirror:  overlay,
        // Redrawn, not only re-laid: the hairline width leans against
        // the magnification.
        changed: () => { sync(); draw(); },
    });

    function hint(text) {
        hintEl.textContent = text;
        hintEl.hidden = text === '';
    }

    /** The picture on the stage — or, without one, the stage's own word
     *  and its offer of a picture. */
    function showPicture(src) {
        image.src = src;
        image.hidden = false;
        empty.hidden = true;
        other.hidden = false;
    }
    function showNoPicture() {
        image.removeAttribute('src');
        image.hidden = true;
        empty.hidden = false;
        other.hidden = true;
    }

    /* THE WINDOW OPENS BEFORE THE PICTURE IS SET: in a closed dialog the
       stage has no size, and the overlay would be laid over nothing.
       Focus goes to the window itself, not to the name field — on the
       phone that would push the keyboard over the picture one came to
       aim at. */
    function open() {
        if (win.open) return;
        win.showModal();
        win.focus({ preventScroll: true });
    }

    /** Lay the overlay over the image's resting box — the same move as
     *  the photo window's syncOverlay, for the same reason: only JS
     *  knows where the centred image came to rest. */
    function sync() {
        const s = overlay.style;
        s.left   = image.offsetLeft + 'px';
        s.top    = image.offsetTop + 'px';
        s.width  = image.offsetWidth + 'px';
        s.height = image.offsetHeight + 'px';
        if (image.naturalWidth) {
            overlay.setAttribute('viewBox',
                `0 0 ${image.naturalWidth} ${image.naturalHeight}`);
            overlay.setAttribute('preserveAspectRatio', 'none');
        }
    }

    /* White over a dark seam, like the photo window's default dress —
       the stage shows an arbitrary micrograph, and the line has to read
       on pale and dark ground alike.

       THE WIDTH IS RECKONED AGAINST THE ZOOM, not left to
       vector-effect: non-scaling-stroke only compensates the viewBox
       mapping, and the zoom scales the whole SVG by CSS transform — the
       line fattened with every wheel step (28.08.2026: „Die
       sollte natürlich schön fein bleiben"). One hairline factor, drawn
       fresh on every zoom change, same move as the photo window's label
       size. */
    function hairline() {
        return 1 / ((image.offsetWidth / image.naturalWidth || 1) * zoom.stufe());
    }

    function seamed(shape, attrs, hair) {
        // Black on a white seam like the photo window's default — the
        // calibration stage should draw as the map does (29.08.2026; red
        // from 26.09.2026, black and white since 06.10.2026,
        // MEASURE_STYLE_DEFAULTS in photos.php).
        return `<${shape} ${attrs} fill="none"
                  stroke="#fff" stroke-width="${2 * hair}"/>`
             + `<${shape} ${attrs} fill="none"
                  stroke="#000" stroke-width="${hair}"/>`;
    }

    function draw() {
        const hair = hairline();
        let inner = '';
        for (const [x, y] of points) {
            inner += seamed('circle', `cx="${x}" cy="${y}" r="${4 * hair}"`, hair);
        }
        if (points.length === 2) {
            const [[x1, y1], [x2, y2]] = points;
            inner = seamed('line', `x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"`, hair) + inner;
        }
        overlay.innerHTML = inner;
    }

    function linePx() {
        if (points.length < 2) return 0;
        const [[x1, y1], [x2, y2]] = points;
        return Math.hypot(x2 - x1, y2 - y1);
    }

    file.addEventListener('change', () => {
        chosen = file.files?.[0] ?? null;
        if (!chosen) return;
        points = [];
        draw();
        // A new local picture voids any archived one's id until the
        // upload has spoken — and it is the full picture, so its own
        // pixels are the camera's.
        document.getElementById('calibrate-fileid').value = '';
        native = null;
        if (ownUrl) URL.revokeObjectURL(ownUrl);
        ownUrl = URL.createObjectURL(chosen);
        open();
        showPicture(ownUrl);
        hint(t('Klicke Anfang und Ende einer Strecke an, deren Länge du kennst. Ein dritter Klick fängt neu an.'));
    });

    image.addEventListener('load', () => {
        zoom.reset();
        sync();
        draw();
    });

    /* Capture phase like the photo window's tap handler — a measuring
       click must never double as the zoom's double-tap. */
    stage.addEventListener('click', (e) => {
        // Without a picture the stage holds a button — its click must
        // reach it, and this handler runs first and would swallow it.
        if (image.hidden || e.target.closest('button')) return;
        e.stopPropagation();
        const r = image.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right
            || e.clientY < r.top || e.clientY > r.bottom || !r.width) return;
        const p = [
            (e.clientX - r.left) / r.width * image.naturalWidth,
            (e.clientY - r.top) / r.height * image.naturalHeight,
        ];
        // The third tap starts a fresh line — misclicked is re-aimed, not
        // trapped.
        points = points.length >= 2 ? [p] : [...points, p];
        draw();
        if (points.length === 2) {
            hint(t('Die Strecke ist %s Pixel lang. Stimmt die wahre Länge oben, speichere die Vorlage.',
                Math.round(linePx())));
        }
    }, true);

    /** The picture into the „Kalibrierung" folder, named after the
     *  preset — recalibrations line up as „10x (2).jpg" thanks to
     *  renameifexists, so the archive keeps its own history. */
    async function archive(presetName) {
        // Named after the preset — recalibrations line up as „10x (2).jpg"
        // wherever the cloud keeps both.
        const ext  = (chosen.name.match(/\.[A-Za-z0-9]+$/) ?? ['.jpg'])[0];
        let file = new File([chosen], presetName + ext,
            { type: chosen.type || 'image/jpeg', lastModified: chosen.lastModified });

        /* THE MICRO SWITCH HOLDS FOR THE CALIBRATION IMAGE TOO
           (26.09.2026: „ob unsere Vorlagen, die ja auch Mikrofotos aus der
           Kamera sind, gleich beim Hochladen schon mit verkleinert werden,
           wenn der Haken drin ist"). MEASURED BEFORE, STORED AFTER: the
           line was tapped on the full picture and the preset keeps the
           camera's pixels — only what goes into the cloud is small, with
           the very shrinking the recordings get. Measuring it again later
           carries the line back to the camera's pixels (submit below). */
        if (win.dataset.microShrink === '1') {
            let bitmap = null;
            try { bitmap = await createImageBitmap(chosen, { imageOrientation: 'from-image' }); }
            catch { /* a picture the browser cannot read goes up as it is */ }
            if (bitmap && Math.max(bitmap.width, bitmap.height) > SHRINK_EDGE) {
                file = await shrunk(bitmap, file, chosen.name);
            }
            bitmap?.close();
        }

        /* The server opens the way for exactly this file — each cloud its
           own (cloudsend.js, the same sender the recordings use). */
        const answer = await fetch(win.dataset.target + '?action=target', {
            method: 'POST',
            body: describe(file, { csrf: win.dataset.csrf }),
        });
        /* THE SERVER'S OWN REASON, not only its number (16.11.2026: a
           friend with Google Drive got „ließ sich nicht in der Cloud
           ablegen" and nothing more, and asked whether a camera had to be
           connected — it does not). „Keine Cloud verbunden", an expired
           Drive access or Drive's own refusal each say so now. */
        if (!answer.ok) {
            const why = (await answer.json().catch(() => null))?.error;
            throw new Error(why || t('Der Server hat die Ablage nicht geöffnet (%s).', answer.status));
        }
        const goal = await answer.json();

        /* The provider's id of what just went up (migration 0094, a string
           since 0138) — it is how „nochmal nacheichen" finds this very
           picture again, and where it lies. */
        const up = await sendToCloud(goal, file);
        document.getElementById('calibrate-fileid').value = up.fileid;
        document.getElementById('calibrate-provider').value = goal.provider || 'pcloud';
    }

    form.addEventListener('submit', async (e) => {
        /* IN EDIT MODE WITHOUT TAPS the submit is a plain RENAME — no
           measurement to demand, the fields ride empty and the server
           takes the branch. Everything else needs the full gesture:
           two points and a true length. */
        const editing = document.getElementById('calibrate-preset').value !== '';
        if (editing && points.length === 0) {
            for (const id of ['calibrate-line-px', 'calibrate-width', 'calibrate-height']) {
                document.getElementById(id).value = '';
            }
            return;
        }
        if (points.length < 2 || !image.naturalWidth) {
            e.preventDefault();
            hint(t('Wähle zuerst ein Eichbild und klicke darin Anfang und Ende der Strecke an.'));
            return;
        }
        if (document.getElementById('calibrate-length').value.trim() === '') {
            e.preventDefault();
            hint(t('Die wahre Länge der Strecke fehlt noch.'));
            return;
        }
        /* THE CAMERA'S PIXELS, ALWAYS — the grid a preset has been in from
           the start, and the one every recording remembers (its width is
           measured before any shrinking, cloudupload.js). A STORED
           calibration image may be the shrunk one, and a line tapped on it
           counts in its smaller pixels: carried back by the width, so the
           switch can go on and off without a preset ever measuring
           wrong (26.09.2026: „es sollte natürlich egal sein, ob
           ich den Haken rausmache oder wieder reinmache"). */
        const factor = native && image.naturalWidth ? native.w / image.naturalWidth : 1;
        document.getElementById('calibrate-line-px').value = String(linePx() * factor);
        document.getElementById('calibrate-width').value  = String(native ? native.w : image.naturalWidth);
        document.getElementById('calibrate-height').value = String(native ? native.h : image.naturalHeight);

        // Measured anew on the STORED picture: nothing to upload, the
        // form goes its ordinary way and the preset keeps its picture.
        if (!chosen) return;

        /* THE PICTURE FIRST — and when it does not arrive, nothing is
           saved: the window stays open with everything in it, and the
           next press tries again. */
        e.preventDefault();
        const label = save.innerHTML;
        save.disabled = true;
        save.textContent = t('Eichbild wird in der Cloud abgelegt …');
        try {
            await archive(document.getElementById('calibrate-name').value.trim()
                || chosen.name.replace(/\.[A-Za-z0-9]+$/, ''));
        } catch (err) {
            console.warn('Eichbild-Ablage fehlgeschlagen:', err);
            save.disabled = false;
            save.innerHTML = label;
            // The reason beside it — the server's, the cloud's or the connection's (cloudsend.js).
            hint(t('Das Eichbild ließ sich nicht in der Cloud ablegen. Die Vorlage ist nicht gespeichert. Versuch es noch einmal.')
                + (err?.message ? ' ' + t('Grund: %s', err.message) : ''));
            return;
        }
        form.submit();
    });

    /* --- New, edit, cancel ----------------------------------------------
       „Neue Vorlage …" asks for the picture first and the window opens
       once there is one (26.09.2026). The pencil opens the
       same window for one row (28.08.2026): rename alone, or tap a fresh
       line into the STORED calibration image and re-calibrate. Closing
       the window, however, forgets everything — the next opening starts
       clean. */

    const heading = document.getElementById('calibrate-heading');

    function reset() {
        points = [];
        chosen = null;
        native = null;
        if (ownUrl) { URL.revokeObjectURL(ownUrl); ownUrl = null; }
        showNoPicture();
        empty.hidden = true;
        file.value = '';
        hint('');
        draw();
        zoom.reset();
        for (const id of ['calibrate-preset', 'calibrate-device', 'calibrate-fileid', 'calibrate-provider',
                          'calibrate-line-px', 'calibrate-width', 'calibrate-height']) {
            document.getElementById(id).value = '';
        }
        document.getElementById('calibrate-name').value = '';
        document.getElementById('calibrate-length').value = '';
        heading.textContent = t('Neue Vorlage');
    }

    function editStart(d) {
        reset();
        document.getElementById('calibrate-preset').value = d.id;
        document.getElementById('calibrate-name').value = d.name;
        // An existing preset must not stand under „Neue Vorlage".
        heading.textContent = t('Vorlage „%s" bearbeiten', d.name);
        open();
        if (d.image === '1') {
            /* The stored picture, streamed through our server —
               pages/calibration_image.php says why the browser could
               not fetch it from the provider itself. Its preset's size is
               the camera's, whatever size the picture was stored in. */
            native = Number(d.width) > 0 && Number(d.height) > 0
                ? { w: Number(d.width), h: Number(d.height) } : null;
            showPicture(win.dataset.imageUrl + '?preset=' + d.id);
            hint(t('Du kannst nur den Namen ändern und speichern. '
                + 'Oder du klickst die Strecke neu an und eichst noch einmal.'));
        } else {
            // The stage says it itself, with its offer of a picture.
            showNoPicture();
        }
    }

    /* One „Neue Vorlage …" per device (migration 0183): the new preset
       joins the device whose button was pressed, and the heading says
       which, so „40x" is not saved into the wrong microscope. */
    for (const b of document.querySelectorAll('[data-calibrate-new]')) {
        if (b.dataset.bound) continue;
        b.dataset.bound = '1';
        b.addEventListener('click', () => {
            reset();
            document.getElementById('calibrate-device').value = Number(b.dataset.device) > 0 ? b.dataset.device : '';
            if (b.dataset.deviceName) heading.textContent = t('Neue Vorlage für „%s"', b.dataset.deviceName);
            file.click();
        });
    }
    other.addEventListener('click', () => file.click());
    document.getElementById('calibrate-choose').addEventListener('click', () => file.click());
    document.getElementById('calibrate-cancel').addEventListener('click', () => win.close());
    // The same step from the top left corner (settings_content.php).
    document.getElementById('calibrate-close-top')?.addEventListener('click', () => win.close());
    // Escape closes the dialog on its own; either way it starts clean.
    win.addEventListener('close', reset);

    /* Zoom as in the photo window, one button one deed. „1:1" means one
       picture pixel per screen point, which follows from how large the
       picture is shown right now. */
    const naturalScale = () => (image.offsetWidth && image.naturalWidth
        ? image.naturalWidth / image.offsetWidth : 1);
    document.getElementById('calibrate-zoom-in').addEventListener('click', () => zoom.scaleBy(1.4));
    document.getElementById('calibrate-zoom-out').addEventListener('click', () => zoom.scaleBy(1 / 1.4));
    document.getElementById('calibrate-zoom-full').addEventListener('click', () => zoom.scaleTo(naturalScale()));
    document.getElementById('calibrate-zoom-fit').addEventListener('click', () => zoom.scaleTo(1));

    for (const b of document.querySelectorAll('[data-preset-edit]')) {
        if (b.dataset.bound) continue;
        b.dataset.bound = '1';
        b.addEventListener('click', () => editStart(b.dataset));
    }
}

/* THE DEVICES (migration 0183): „Neues Gerät …" and the pencil in a
   device's head ask only for a name, and the one form below the tables
   carries it. Bound by the same watcher as the window, for the same lazy
   tab. */
function bindDevices() {
    const form = document.getElementById('scale-device-form');
    if (form === null) return;
    const ask = async (id, name) => {
        const answer = await prompt({
            title: id > 0 ? t('Gerät umbenennen') : t('Neues Gerät'),
            label: t('Name des Geräts'),
            value: name,
            placeholder: t('z. B. Reisemikroskop'),
            button: t('Speichern'),
        });
        if (answer === null || answer.trim() === '' || answer.trim() === name) return;
        form.elements.id.value = String(id);
        form.elements.name.value = answer.trim();
        form.submit();
    };
    for (const b of document.querySelectorAll('[data-device-new], [data-device-rename]')) {
        if (b.dataset.bound) continue;
        b.dataset.bound = '1';
        b.addEventListener('click', () => ask(Number(b.dataset.id ?? 0), b.dataset.name ?? ''));
    }
}

const present = document.getElementById('calibrate-window');
if (present !== null) setUp(present);
bindDevices();
new MutationObserver(() => {
    const win = document.getElementById('calibrate-window');
    if (win !== null) setUp(win);
    bindDevices();
}).observe(document.body, { childList: true, subtree: true });
