/**
 * The picture viewer — the full-screen window a find's voucher photos and
 * cloud recordings open in, with naming, scale, measuring, the strip and the
 * statistics sidebar (the maintainer's measuring tool since 28.08.2026).
 *
 * OUT OF map.js ON 30.09.2026, as step one of ONE viewer for the map AND
 * the administration (that evening, after finding the two were
 * different windows: „Das wäre ja irgendwie sinnvoll"). Moved as it stood;
 * the only change is that everything it used to take from map.js's own
 * scope now comes from its HOST.
 *
 * A MOUNT FUNCTION AND NOT A MODULE THAT RUNS ON IMPORT. The viewer reads
 * its host at load — the account's measuring style, the saved scale
 * presets — and an imported module is evaluated before the page's own
 * script has built anything. map.js calls mountPhotoWindow() exactly
 * where this code used to stand, so every listener it wires and every
 * element it looks up happens in the old order.
 *
 * THE HOST, all the viewer asks of the page around it:
 *
 *   start            baseUrl, user.id, measure_style, photo_scales — the
 *                    map hands in its START_DATA
 *   guest            true for an excursion guest: no measuring row at all
 *   pointId()        the find whose pictures stand in the window
 *   point(id)        a find by id, with its .photos — own or shared
 *   drawGallery(p)   draw that find's gallery again after a change here
 *                    (a name, a deletion)
 *   mayEditPhotos()  whether the viewer may tend this find's pictures
 *   foreign()        the foreign find in the window, or null for one's own
 *   sharedRights(f)  the rights a share grants on that foreign find
 *   status(text, isError)   a message about a deed
 *   mayDelete(photo)  OPTIONAL: whether a voucher photo may be deleted here,
 *                    in place of the viewer's own rule (Organisieren)
 *   inPage()         OPTIONAL: true where the window stands IN the page
 *                    instead of over it — the Fundbrowser's tab „Bilder"
 *                    (12.10.2026: „zwei Bildbetrachter
 *                    hintereinander gebaut. Was schwachsinnig ist"). It
 *                    is then opened with show() and not showModal(): no
 *                    backdrop, no top layer, the page's tabs and the find's
 *                    window stay reachable. Asked at every opening, so the
 *                    host can still ask for the modal window where
 *                    something modal already lies over the page (a plate).
 *
 * THE MARKUP stays in index.php for now (the <dialog id="photo-window">
 * and its question windows); it has to be in the page before the mount.
 */

import { t, lang } from './i18n.js';
import { api, request } from './api.js';
import { settings } from './settings.js';
import { choose, confirm, prompt } from './dialogs.js';
import * as imageZoom from './imagezoom.js';
import { shortcutsProvide, typingIn, wheelStep, wheelWithCtrl } from './shortcuts.js';
import { tickBox } from './tickbox.js';
import * as measureDraw from './measuredraw.js';
import { ARROW_WEIGHTS, TEXT_LEADING, TEXT_SIZES, textStep, arrowWordsGrip, arrowWeightSet, arrowWeightOf, arrowTextAt, arrowTextLook, boxLabel, boxNumber } from './measuredraw.js';
import { icon, iconHtml } from './icons.js';
import { readableDate } from './dates.js';
import { MEDIA, mediaLabel, mediaAlphabetical } from './media.js';
import { DEVELOP_GROUPS, DEVELOP_TRACKS, MIX_COLOURS, MIX_KINDS, mixTrack, mixSet, GRADE_RANGES, HUE_TRACK, gradeSet, RADIAL_SLIDERS, RADIALS_MAX, radialNew, developEmpty, developClean, developRenderer,
    castMeasure, castPickRead, castFromPicks, CAST_PICKS_MAX, geoView, geoSet, geoMirrorShown, wbFromPixels, wbToSliders, developWBFold, cropFit, cropWhole, developFile, linearNew, developThumb, developThumbsRefresh,
    SPOTS_MAX, PATH_POINTS_MAX, spotRadiusOf, spotSizeOf, spotSourceFind, BRUSHES_MAX, brushNew, brushField, masksOf,
    spotsOf, AI_SIDE, AI_PIECES_MAX, aiPieces, aiHole, aiPieceApply, aiPatchKeep, aiPatchCut,
    aiPatchesUnstored, aiPatchPng, aiPatchStored, aiPatchesFor, spotLoopCloses, pointInPath, spotSourceMap, brushCore, brushOuter, SPOT_RADIUS_MAX,
    aiSpotSwitch, aiPatchesFetch, aiPatchUrl, aiPatchHas, aiPatchIsStored, geoSig, geoFill, cropOutside, FILL_PIECES_MAX,
    fillAreas, fillAreaMask, fillWithout, fillImage, fillKey
} from './develop.js';
import { editSavesStart, editSaveFailed, editSaveDone, editSaveWaiting, editSavesWaiting, editSavesIds, editSavesRetryNow, editSavesHint } from './editsaves.js';
import * as inpaint from './inpaint.js';
import { strongFill, strongSeed } from './inpaintstrong.js';
import { expandJob, expandJobArea, expandFill, expandCountAreas, expandSteps, expandMask, expandImage, expandPieces, Halted as ExpandHalted,
    EXPAND_LEVELS, EXPAND_LEVEL_DEFAULT, EXPAND_STRENGTH } from './expand.js';
import { readCamera, stampJpeg, stampWebp } from './exifstamp.js';
import { originalBlob, originalPeek, originalKeepsHere } from './originalcache.js';
import * as exif from './exif.js';
import { drawMark, loadLogo, exportSize, scaleCanvas, flatCanvas, sharpenCanvas } from './exportmark.js';
import { openExport, webpCan, exportName, exportDownload } from './exportwindow.js';
import { findDetails, nameDay, namePlace } from './finddetails.js';
import { subjectGroups } from './gallerysort.js';
import { hoverPick } from './hoverpick.js';
import { personName } from './names.js';
import { wordsOrSigns } from './wordsorsigns.js';
import {
    measureLabel, measureMark, measurePart, measureRows, partRows, partLines, rowsMarked, valueLines, plotSvg, spreadText,
    sporeAir, sporeDraw, sporeOrder, lengthStraight, partValueLines, curveSvg, lineDraw, partAir, partAirKey,
    partDirection, measureCrop, cropNames, cropRows, scaleBarLength, scaleLabel, CYSTID_PROFILES, measureUmPerPx,
    SECTOR_KINDS, sectorShape, sectorArc, sectorInside, sectorSlicePath, sectorSummary, boxLine, sectorStemOf, sectorRimT, sectorWhole,
    sectorRimArc, sectorRimPath,
} from './measurestats.js';

/**
 * Wire the viewer into the page and hand back what the host itself uses:
 * the two openers, the two cloud helpers its gallery tiles need, the
 * account's measuring style (the gallery orders by its subject names) and
 * the object the gallery writes its cloud answer into.
 */
export function mountPhotoWindow(host) {
    /** Which cloud recording stands in the window — for its delete button. */
    let cloudInWindow = null;
    /* The gallery's last cloud answer, kept for the statistics sidebar: the
       photo window is only ever opened FROM the gallery, so this list is warm
       whenever the sidebar wants to reckon across all pictures of the find —
       no second fetch, no spinner in a pane that is mostly numbers. */
    /* ONE OBJECT FOR THE WHOLE LIFE OF THE WINDOW, filled in place and
       never replaced: the host's gallery writes its cloud answer into it
       (Object.assign), and a binding the host reassigned would no longer
       be the one this module reads. */
    const cloudFilesShown = { pointId: 0, files: [] };
    // Whether the ORIGINAL of the shown cloud recording is loaded — presets
    // may only bind to original pixels (scaleTopbarSync says why).
    let cloudOriginalLoaded = false;

    /** The viewer's address for a recording — the big preview, from our server. */
    function cloudPhotoUrl(f) {
        return `${host.start.baseUrl}pages/cloud_thumb.php?id=${f.id}&c=1&large=1`;
    }

    /**
     * A cloud recording in the same window the voucher photos use.
     *
     * THE SAME WINDOW AND NOT A SECOND ONE: it is a picture of the same find,
     * and it should not feel like another application just because the bytes
     * lie elsewhere. Deleting deletes it where it lies, in the photographer's
     * own cloud — the question before it says what that cloud does with it.
     */
    function showCloudPhoto(f) {
        // Whatever the previous picture still owes the server goes out first —
        // the pending save captured ITS subject, switching cannot lose it.
        measureSaveFlush();
        /* THE LIST'S RECORD, where the list knows this picture (15.11.2026).
           Whoever opens a recording hands over the object HE holds — a
           gallery tile the one of its drawing, the page the one of its list.
           Two objects for one picture are two lists of measures: what is
           measured into the one is missing in the other, the statistics
           stand still, and the next save from the poorer one writes over
           the richer. The ids are the table's, so the list's object of the
           same id IS this picture. */
        f = cloudFilesShown.files.find((x) => x.id === f.id) ?? f;
        // No photo record behind this one — the delete button acts on
        // photoInWindow, and it must find nothing here.
        photoInWindow = null;
        cloudInWindow = f;
        photoZoom.reset();
        document.getElementById('photo-image').src = cloudPhotoUrl(f);
        pictureSwitching();

        cloudDetails(f);

        document.getElementById('photo-delete').hidden = true;

        /* Measuring works here too since migration 0090 — these full-
           resolution recordings are the pictures the tool EXISTS for
           (28.08.2026). The calibration stores the width it was
           drawn on, so preview and original convert exactly; only the preset
           options wait for the original (see scaleTopbarSync). */
        const tool = armedTool();
        calibrateEnd();
        measureEnd();
        /* The persisted measures of THIS recording (migration 0092) — the
           global aliases the subject's own array, so every push and splice
           edits the one truth. The overlay stays blank until the new image's
           load event: drawing the new measures over the OLD picture, which is
           still standing there, would be a wrong statement for a moment. */
        measures = (f.measures ??= []);
        freeMeasures = [];
        layerAutoPick();
        measureSelected = null;
        setMeasureFocus(null);
        removeAnchor = null;
        photoOverlay.innerHTML = '';
        scaleBar.hidden = true;
        cloudOriginalLoaded = false;
        // Opened anew, it may be asked for its original anew (developOriginalFetch).
        developOriginalAsked = null;
        scaleTopbarSync();
        // The strip carries BOTH worlds since 29.08.2026 — the recordings
        // are exactly the pictures one measures a spore print across.
        fillPhotoStrip(f);
        statsAutoReset();
        statsRefresh();

        /* WHICH QUALITY STANDS THERE (28.08.2026: „das wird
           momentan nicht richtig kommuniziert") — the badge says it instead
           of leaving the viewer to guess off the sharpness. „Original" takes
           over in the Volle-Größe handler. */
        qualityShow(f, 'preview');
        /* AT THE DESK THE ORIGINAL COMES BY ITSELF (18.11.2026:
           „wir könnten dann zum Beispiel auch beim Messen standardmäßig das
           volle Bild laden. So wie wir es beim Bearbeiten schon haben, dass
           wir erst kurz das Vorschaubild zeigen und dann das volle Bild
           nachladen und ersetzen") — the preview first, at once, then the
           badge pressed for the hand; the desk keeps what it loaded
           (originalcache.js), so the next opening costs no network. The
           phone keeps the preview and its badge. */
        if (desk.matches) {
            queueMicrotask(() => {
                if (cloudInWindow === f && !cloudOriginalLoaded) document.getElementById('photo-quality')?.click();
            });
        }

        /* SOMEBODY ELSE'S RECORDING — a co-owner's, the holder's on a shared
           find: no delete button (the server refuses by photographer, and a
           button that leads into a refusal is worse than none, 11.09.2026)
           and no folder link either, since 14.09.2026: it opens the
           PHOTOGRAPHER's folder in MY cloud, a place that does not exist.
           The original stays reachable — it comes through our own server
           like the preview. */
        const own = f.mine !== false;
        const away = document.getElementById('photo-cloud-delete');
        /* …unless the share grants deleting the others' pictures — cloud
           recordings are pictures like the voucher photos (08.10.2026); the
           server deletes in the photographer's name (cloud_file_actor). */
        const foreignFind = host.foreign();
        if (away) away.hidden = !own && !(foreignFind !== null && host.sharedRights(foreignFind).has('delete_photos'));

        // INTO THE FOLDER, not onto the provider's start page. Whoever wants to
        // look at what lies around the recording — the other shots of the same
        // find — would otherwise have to click their way down through the whole
        // tree that this application built for them.
        // THROUGH OUR SERVER since every cloud takes pictures (26.09.2026):
        // OneDrive's folder address only Microsoft can name, and S3 has none a
        // browser could open — pages/cloud_folder.php says both.
        const outward = document.getElementById('photo-external');
        if (outward) {
            outward.href = `${host.start.baseUrl}pages/cloud_folder.php?id=${f.id}`;
            outward.hidden = !own || f.provider === 's3';
        }

        photoOpen();
        toolKeep(tool);
    }

    /**
     * NAME, SIZE AND RESOLUTION (28.08.2026: „den kompletten Pfad
     * nicht mit anzeigen … das reicht"; 29.10.2026: „noch die Auflösung des
     * Bildes"). The path is seven folder levels of taxonomy and ate the
     * whole line.
     *
     * THE RESOLUTION IS THE FILE'S, NOT THE CAMERA'S (20.11.2026:
     * „Ich würde das ursprüngliche Maß nur für die interne Rechnung
     * verwenden und in der Statuszeile wirklich zeigen, was die Datei an
     * Größe hat … das echte Maß … des Bildes, das wir gerade anzeigen").
     * Until then the line showed f.width × f.height — the dimensions taken
     * BEFORE a shrinking at the upload (cloudupload.js, 0095), which the
     * scale needs and keeps needing: presets are µm per camera pixel. A
     * picture shrunk to 2000 px showed 5472 there while „Info" read 2000
     * from the file. Now the line shows f.fileW × f.fileH, what the loaded
     * original measures (the quality button's load, below) — so nothing
     * until it is loaded; at the desk it comes by itself, on the phone with
     * „Volle Größe". The preview's 2048 would answer a question nobody asked.
     */
    function cloudDetails(f) {
        const parts = [f.name];
        if (f.size > 0) parts.push(cloudBytes(f.size));
        if (f.fileW > 0 && f.fileH > 0) parts.push(`${f.fileW} × ${f.fileH} px`);
        document.getElementById('photo-details').textContent = parts.join(' · ');
        authorSay(f, true);
    }

    /* THE AUTHOR IN THE FACT LINE (08.11.2026): who took the
       picture — a voucher photo's is always the one whose phone took it, a
       recording's is the uploader unless the upload named somebody else
       (taken_by, asked in photogallery.js). It is what an export writes
       into the file. Whoever uploaded a recording may change it here, a
       pencil beside the name; nobody else — a picture is its maker's. */
    const myName = () => String(host.start.user?.finder || host.start.user?.name || '').trim();
    function authorOf(f) {
        return String(f?.photographer || f?.taken_by || '').trim() || myName();
    }
    function authorSay(f, cloud) {
        const line = document.getElementById('photo-details');
        const who = authorOf(f);
        if (!line || !who) return;
        const piece = document.createElement('span');
        piece.className = 'photo-author';
        /* „©" AND NOT „Urheber:" (20.11.2026: „Urheber können
           wir das ersetzen durch ein Copyright-Zeichen") — the sign says it
           in one character; the word stays as its title under the pointer. */
        piece.textContent = (line.textContent ? ' · ' : '') + '© ' + who;
        piece.title = t('Urheber');
        line.append(piece);
        if (!cloud || f.mine !== true) return;
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'photo-author-edit';
        edit.title = t('Urheber ändern');
        edit.setAttribute('aria-label', t('Urheber ändern'));
        edit.innerHTML = iconHtml('pencil');
        edit.addEventListener('click', async () => {
            const typed = await prompt({
                title: t('Urheber ändern'),
                text: t('Wer hat dieses Foto gemacht? Lass das Feld leer, wenn du es selbst warst.'),
                value: f.taken_by ?? '',
                placeholder: t('Vor- und Nachname'),
                button: t('Speichern'),
            });
            if (typed === null || typed === undefined) return;
            try {
                const answer = await request(`cloud.php?id=${host.pointId()}&action=scale`, {
                    method: 'POST', body: JSON.stringify({ file: f.id, taken_by: String(typed).trim() }),
                });
                f.taken_by = answer.taken_by ?? null;
                f.photographer = f.taken_by ?? myName();
                if (cloudInWindow === f) cloudDetails(f);
                host.drawGallery?.(host.point(host.pointId()));
            } catch (e) {
                host.status(e.message || t('Der Urheber ließ sich nicht speichern.'), true);
            }
        });
        piece.append(edit);
    }

    /** The badge over the picture: 'preview' (a way to the original), 'loading', 'original', 'retry'.
     *  ONCE THE ORIGINAL STANDS, THE BADGE GOES (08.11.2026: „wenn das
     *  Originalbild geladen ist, braucht er nicht mehr Original stehen … das hat
     *  man dann aktiv durch einen Klick gemacht"): the badge is a warning that
     *  one looks at a preview and the way out of it; with the full picture
     *  there is nothing left to warn of. The resolution in the line below the
     *  picture still says what one has. */
    function qualityShow(f, state) {
        const badge = document.getElementById('photo-quality');
        // Whose badge it is — developOriginalFetch must not press the last recording's.
        badge.dataset.of = String(f.id);
        if (state === 'original') {
            badge.hidden = true;
            return;
        }
        const swap = state === 'preview' || state === 'retry';
        badge.innerHTML = '';
        if (swap) badge.insertAdjacentHTML('beforeend', iconHtml('refresh'));
        badge.append(state === 'loading' ? t('Original wird geladen …')
            : state === 'retry' ? t('Original laden — noch einmal versuchen')
            : t('Vorschaubild'));
        badge.disabled = !swap;
        badge.title = swap
            ? (f.size > 0 ? t('Original laden · %s', cloudBytes(f.size)) : t('Original laden'))
            : '';
        badge.hidden = false;
    }

    /** Bytes as one reads them out loud. */
    function cloudBytes(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
        if (bytes >= 1024)    return Math.round(bytes / 1024) + ' KB';
        return bytes + ' Bytes';
    }

    /** A photo in large, with the option to remove it. */
    const photoWindow = document.getElementById('photo-window');

    /* THE SUGGESTION LIST OF THE NAME FIELD, CLOSED FROM OUTSIDE. It is drawn
       in a block of its own further down (search for „Motivname"), and only
       this handle reaches out of it — photoOpened() needs it, and nothing
       else does. A function and not the <ul>: closing is three steps, and
       whoever writes them out a second time writes one of them differently. */
    let photoTitleListClose = () => {};

    /**
     * Show the picture window — and leave the focus on the WINDOW.
     *
     * 11.09.2026: „Wenn ich auf dem Desktop den Bildbetrachter
     * öffne, dann liegt mir der Fokus gleich auf Motivname. Und das Dropdown
     * wird angezeigt, auch wenn schon ein Motivname gewählt wurde. Das bitte
     * abschalten."
     *
     * showModal() gives the focus to the first focusable element of the
     * dialog, and since the measuring row moved in on top that is the name
     * field — which answers a focus with its list of suggestions, over the
     * picture one came to look at, and over a name that has long been
     * chosen. The same cure as the list window's (see its tabindex note):
     * the window carries tabindex="-1" and takes the focus itself. Whoever
     * wants to name the picture taps into the field; then the list belongs
     * there, and only then.
     *
     * THE LIST IS CLOSED AFTERWARDS, NOT PREVENTED. The field does get the
     * focus for a moment — that happens inside showModal(), before any line
     * of ours can run — and opens its list on it. Both steps stand in the
     * same turn, before the browser draws, so nothing of it is seen. A focus
     * listener that first asks whether the user meant it would be a second
     * rule about the same thing, and the two would drift apart.
     *
     * ON THE PHONE IT IS THE SAME, and that is no side effect: a focused
     * text field calls up the keyboard, which takes half the picture and
     * shrinks --view-height with it. Whoever opens a picture wants to see it.
     */
    function photoOpen() {
        /* ALREADY OPEN STAYS AS IT IS: a dialog opened with show() refuses
           showModal() with an error, and a modal one ignores a second
           call anyway. */
        if (!photoWindow.open) {
            if (host.inPage?.()) photoWindow.show();
            else photoWindow.showModal();
        }
        photoWindow.focus({ preventScroll: true });
        photoTitleListClose();
    }

    /* THE KEYS ARE THE WINDOW'S ONLY WHILE ONE SEES IT. Over the page a
       modal window is always seen; in the page (host.inPage) it stays open
       behind another tab of the Fundbrowser, and there a space bar or an
       Escape belongs to the text one is writing, not to a picture nobody
       looks at. */
    const viewing = () => photoWindow.open && photoWindow.checkVisibility?.() !== false;
    let photoInWindow = null;

    // Two fingers to zoom in, one to pan, double tap for full or close.
    // Attached once, for all images — the frame stays the same after all.
    // The overlay mirrors every transform (measuring plane, stage one of the
    // measuring tool), and the scale bar recomputes on every change.
    const photoFrame   = document.querySelector('.photo-window-image');
    const photoImageEl = document.getElementById('photo-image');
    const photoOverlay = document.getElementById('photo-overlay');
    // The developed picture and the ellipses' grips ride the zoom as the measuring plane does.
    const developCanvas = document.getElementById('photo-develop-canvas');
    const developSvg = document.getElementById('photo-develop-overlay');
    const photoZoom = imageZoom.bind(photoFrame, photoImageEl, {
        mirror:  [photoOverlay, developCanvas, developSvg].filter(Boolean),
        // Both lean against the magnification: the bar picks a new round
        // length, the overlay recomputes its label sizes.
        // The ellipses' grips keep their size on the screen, as the measures' do.
        changed: () => { drawScaleBar(); drawOverlay(); spotSightKeep(); developHandles(); },
        // The viewer zoomed or moved the picture by hand (the Fundbrowser keeps it per picture).
        moved: () => host.zoomMoved?.(),
        /* SMALLER THAN THE WINDOW while editing (07.11.2026): an
           ellipse that only darkens the edges reaches far past the picture,
           and its line must be within reach — down to a seventh. */
        minScale: () => (developMode ? .15 : 1),
    });

    /* THE PICTURE WANDERS WHEN THE POINTER WAITS AT ITS EDGE
       (13.11.2026, first for „Bearbeiten": „wenn man etwas reingezoomt hat, können wir dann das Bild
       verschieben, wenn wir uns außen am Rand nähern … leicht zeitversetzt
       … dass wenn man schnell in die Werkzeugleiste oder sonst wohin fährt,
       es nicht anspringt. Aber wenn man langsam zum Rand fährt … dass das
       Bild dann doch in die Richtung wandert"). Magnified:
       within EDGE_PAN_ZONE of the frame's side the pointer starts a clock;
       after EDGE_PAN_DELAY still there, the picture moves towards that
       side, the faster the nearer the edge (EDGE_PAN_SPEED at the very
       rim), until the pointer leaves the band or the picture's border
       arrives. MEASURING TOO since the same day („kannst du auch beim
       Messen einbauen") — a line laid towards the edge runs out of picture
       just as a brush stroke does. Passing through on the way to the tools or the sidebar is
       quicker than the delay and moves nothing. Never with a button held
       (below, since the same evening). Over a button inside the frame (the
       quality badge, the sidebar's fold) it rests. */
    const EDGE_PAN_ZONE = 56, EDGE_PAN_DELAY = 500, EDGE_PAN_SPEED = 400;
    let edgePan = null;   // { since, dirX, dirY, depth, last } while the pointer is in the band
    let edgePanFrame = 0;
    let edgePanMove = null;   // the last real pointermove — replayed after each step (edgePanReplay)
    /* THE TOOLS FOLLOW THE POINTER, NOT THE PICTURE (the same
       day: „Verschieben bleibt oft der Pinselkreis hängen und wandert
       mit"). The brush ring, a stroke, a mask's grip — each takes its
       place in the picture from a pointermove, and while the picture
       wanders under a resting pointer none comes: the ring stayed on its
       spot of the picture and rode away with it, and a stroke stood still.
       After every step the last move is told again at the same place on
       the screen, so every tool finds the picture's new spot under the
       pointer in its own way — one replay for all, instead of a second
       path into each tool. */
    function edgePanReplay() {
        const e = edgePanMove;
        if (!e) return;
        const target = e.target instanceof Element && e.target.isConnected && photoFrame.contains(e.target) ? e.target : photoFrame;
        const again = new PointerEvent('pointermove', {
            bubbles: true, cancelable: true, composed: true,
            clientX: e.clientX, clientY: e.clientY, screenX: e.screenX, screenY: e.screenY,
            pointerId: e.pointerId, pointerType: e.pointerType, isPrimary: e.isPrimary,
            buttons: e.buttons, button: -1,
            altKey: e.altKey, shiftKey: e.shiftKey, ctrlKey: e.ctrlKey, metaKey: e.metaKey,
            pressure: e.pressure, width: e.width, height: e.height,
        });
        again.edgePanReplay = true;
        target.dispatchEvent(again);
    }
    function edgePanWhere(e) {
        if (!photoWindow.open || photoZoom.stufe() <= 1.01) return null;
        /* Resting over a control: the buttons and fields in the frame, the
           scale bar one drags, the tool rail whose words unfold over the
           picture's left edge, an open menu. */
        if (e.target?.closest?.('button, a, input, select, textarea, .photo-develop-fold, .photo-tool-rail, #photo-scalebar, .photo-pick-menu, [role="listbox"]')) return null;
        const r = photoFrame.getBoundingClientRect();
        const x = e.clientX - r.left, y = e.clientY - r.top;
        if (x < 0 || y < 0 || x > r.width || y > r.height) return null;
        const zone = Math.min(EDGE_PAN_ZONE, r.width / 4, r.height / 4);
        const share = (d) => (d < zone ? 1 - d / zone : 0);
        const left = share(x), right = share(r.width - x), top = share(y), bottom = share(r.height - y);
        const dirX = right - left, dirY = bottom - top;
        if (dirX === 0 && dirY === 0) return null;
        return { dirX, dirY };
    }
    function edgePanStop() {
        edgePan = null;
        edgePanMove = null;
        if (edgePanFrame) cancelAnimationFrame(edgePanFrame);
        edgePanFrame = 0;
    }
    function edgePanStep(now) {
        edgePanFrame = 0;
        if (!edgePan || !photoWindow.open || photoZoom.stufe() <= 1.01) { edgePanStop(); return; }
        if (now - edgePan.since >= EDGE_PAN_DELAY) {
            const dt = Math.min(.05, (now - (edgePan.last ?? now)) / 1000);
            // Eased: a touch inside the band creeps, the rim itself runs.
            const speed = (d) => Math.sign(d) * EDGE_PAN_SPEED * d * d;
            // Towards the side one points at: the picture moves the other way.
            if (photoZoom.panBy(-speed(edgePan.dirX) * dt, -speed(edgePan.dirY) * dt)) edgePanReplay();
            edgePan.last = now;
        } else {
            edgePan.last = now;
        }
        edgePanFrame = requestAnimationFrame(edgePanStep);
    }
    /* NOT WITH A BUTTON HELD (13.11.2026: „Ich habe gerade mit
       einem Pinselwerkzeug außen am Rand gemalt. Dabei hat dann das Bild
       angefangen, sich zu verschieben. Das müsste natürlich sofort gestoppt
       werden beim ersten Klick mit irgendeinem Werkzeug"). Until then a
       stroke towards the edge was meant to carry the picture along; now a
       press stops a wandering picture at once and none starts while any
       button is down — a stroke, a measure, a grip stays where the hand
       puts it. Let go and rest at the edge again, and it wanders anew. */
    window.addEventListener('pointerdown', () => { if (edgePan) edgePanStop(); }, true);
    window.addEventListener('pointermove', (e) => {
        if (e.pointerType === 'touch' || e.edgePanReplay) return;
        if (e.buttons) { if (edgePan) edgePanStop(); return; }
        edgePanMove = e;
        const where = edgePanWhere(e);
        if (!where) { if (edgePan) edgePanStop(); return; }
        if (!edgePan) edgePan = { since: performance.now(), last: null };
        edgePan.dirX = where.dirX;
        edgePan.dirY = where.dirY;
        if (!edgePanFrame) edgePanFrame = requestAnimationFrame(edgePanStep);
    }, { passive: true });
    document.addEventListener('pointerleave', edgePanStop);
    window.addEventListener('blur', edgePanStop);

    /** The fact line under the picture — its own function since the
     *  calibration hint borrows the spot and has to give it back. */
    function fillPhotoDetails(f) {
        const parts = [];
        if (f.taken_at) parts.push(t('Aufgenommen %s', readableDate(f.taken_at)));
        if (f.camera) parts.push(f.camera);
        if (f.width && f.height) parts.push(`${f.width} × ${f.height}`);
        document.getElementById('photo-details').textContent = parts.join(' · ');
        authorSay(f, false);
    }

    function showPhoto(f) {
        // Flush what the previous picture still owes (see showCloudPhoto).
        measureSaveFlush();
        photoInWindow = f;
        // Reset BEFORE setting the source. Otherwise the next image would
        // appear at the previous one's magnification — at a spot that has
        // nothing to do with it.
        photoZoom.reset();
        const address = `${host.start.baseUrl}pages/photo.php?id=${f.id}`;
        /* AT THE DESK THROUGH ITS STORE (originalcache.js, 18.11.2026:
           „Ja, die Belegfotos auch mit reinnehmen"): a voucher photo kept
           from an earlier opening comes without the network; one not kept
           is fetched as before and kept. Meanwhile the window says a new
           picture is on its way (pictureSwitching) — its <img> still holds
           the old one, and the load of the new one ends the waiting. */
        if (originalKeepsHere()) {
            photoFrame.classList.add('is-switching');
            originalBlob(f, address, 'photo')
                .then((blob) => {
                    if (photoInWindow !== f) return;
                    const shown = URL.createObjectURL(blob);
                    originalShown(shown);
                    photoImageEl.src = shown;
                })
                .catch(() => { if (photoInWindow === f) photoImageEl.src = address; });
        } else {
            photoImageEl.src = address;
            pictureSwitching();
        }

        fillPhotoDetails(f);

        // ON A FOREIGN POINT ONLY WITH THE RIGHT 'delete_photos'. The right
        // already existed (SHARE_RIGHTS in container.php), but there was no
        // way to it: the sharing feature's old point window showed no photos at
        // all. Since a foreign point stands in the detail window, there is one
        // — and it needs its button. The server checks it once more
        // (foto_fremdrechte).
        const foreign = host.foreign();
        document.getElementById('photo-delete').hidden =
            foreign !== null && !host.sharedRights(foreign).has('delete_photos');
        // Somebody else's picture: no delete button — the server refuses by
        // photographer, and a button that leads into a refusal is worse than
        // none (11.09.2026). Unless the share grants deleting the others'
        // pictures too („Bilder löschen, auch deine", 08.10.2026).
        if (!pictureMine() && !pictureRight('delete_photos')) document.getElementById('photo-delete').hidden = true;
        /* A HOST MAY ANSWER IT ITSELF (30.09.2026). Organisieren is the
           leader's sorting-out place for a guest's excursion finds: the
           picture is the guest's, the right is the leader's, and the server
           grants it (api/v1/photos.php, „THE LEADER SORTS OUT"). The rule
           above would hide the button exactly there. The map asks no such
           thing and keeps the rule. */
        if (host.mayDelete) document.getElementById('photo-delete').hidden = !host.mayDelete(f);

        // The way to the provider and the deletion in the cloud belong to a
        // cloud recording alone. Without these lines they would stay standing
        // under the next voucher photo — the window is one and the same.
        cloudInWindow = null;
        for (const id of ['photo-external', 'photo-cloud-delete']) {
            const e = document.getElementById(id);
            if (e) e.hidden = true;
        }

        // The measuring tool: modes off, THIS photo's persisted measures in
        // (aliased — see showCloudPhoto), the bar waits for the image to
        // load, topbar and strip follow the photo — and the tool one had
        // comes back on (toolKeep).
        const tool = armedTool();
        calibrateEnd();
        measureEnd();
        measures = (f.measures ??= []);
        freeMeasures = [];
        layerAutoPick();
        measureSelected = null;
        setMeasureFocus(null);
        removeAnchor = null;
        photoOverlay.innerHTML = '';
        scaleBar.hidden = true;
        scaleTopbarSync();
        fillPhotoStrip(f);
        statsAutoReset();
        statsRefresh();
        // A voucher photo has only the one fassung — no quality to announce.
        document.getElementById('photo-quality').hidden = true;

        photoOpen();
        toolKeep(tool);
    }

    /* THE TOOL STAYS ON ACROSS PICTURES (07.10.2026: „wenn ich im
       Bildbetrachter ein Bild wechsle, kannst du auch die Messmethode, also
       das Werkzeug, beibehalten") — a spore series runs over several
       micrographs, and picking the tool again on each was the step nobody
       wants. The switch still ends everything picture-bound (a polyline
       half drawn, a cutout half measured: measureEnd); only the CHOICE of
       tool comes back. A calibration is no tool and does not. */

    /** The tool the dropdown shows right now — a cutout being measured into
     *  is still the cutout tool (toolChoiceSync says the same). */
    function armedTool() {
        if (frameRect !== null) return frameRect.armed ? frameRect.mode : null;
        return measureMode;
    }

    /** Arm it again on the picture just shown — only while the window is
     *  open: the first picture of a window starts with no tool. */
    function toolKeep(tool) {
        if (tool && photoWindow.open && measureMode === null) measureStart(tool);
    }

    /* The magnification controls.
     *
     * "1:1" MEANS ONE PICTURE PIXEL PER SCREEN POINT, and the factor for that
     * cannot be written down — it follows from how large the picture is being
     * shown right now, which depends on the window. offsetWidth and not the
     * bounding rectangle: the latter already carries the zoom in it, and the
     * calculation would chase its own tail.
     */
    function photoNaturalScale() {
        const image = document.getElementById('photo-image');
        const shown = image?.offsetWidth ?? 0;
        if (!shown || !image.naturalWidth) return 1;
        return image.naturalWidth / shown;
    }

    /* EACH BUTTON DOES ONE THING AND KEEPS ITS NAME. "1:1" and "Einpassen" were
       one button that renamed itself to whichever came next. That is one width
       too many: the word changes, the button changes with it, and everything to
       its right moves out from under the finger that was aiming at it. Nothing
       here reacts to the state any more — no label, no disabled — so the row
       stands still while one works in it. */
    document.getElementById('photo-zoom-full')?.addEventListener('click',
        () => photoZoom.scaleTo(photoNaturalScale()));
    document.getElementById('photo-zoom-fit')?.addEventListener('click',
        () => photoZoom.scaleTo(1));

    // ---------------------------------------------------------------------------
    // The scale (stage one of the measuring tool, 28.08.2026)
    // ---------------------------------------------------------------------------
    //
    // A photo carries at most one calibration (migration 0088): a line in
    // ORIGINAL image pixels plus the real length of that line in micrometres.
    // It travels to every viewer — the bar shows for everyone; only setting it
    // needs the same trust as deleting a photo.

    /* How the overlay dresses — the account's choice (Verwaltung →
       Einstellungen → Fotos → Messen, migration 0091), completed with defaults by
       the server. DECLARED BEFORE ITS FIRST USE, which is the bar styling
       right below — a const in the temporal dead zone took the whole module
       down on 28.08.2026, and with it every point on the map. */
    const measureStyle = host.start.measure_style
        ?? { scale_color: '#000000', scale_seam_color: '#ffffff',
             line_color: '#000000', seam_color: '#ffffff', font_px: 16 };
    // Styles stored before a seam joined (the lines' 28.08.2026, the bar's
    // 26.09.2026 — both migration-free) arrive complete from the server;
    // only a guest's fallback and a stale cached payload need the default
    // filled in.
    measureStyle.seam_color ??= '#ffffff';
    measureStyle.scale_seam_color ??= '#ffffff';

    const scaleBar      = document.getElementById('photo-scalebar');
    const scaleBarLine  = scaleBar.querySelector('.photo-scalebar-line');
    const scaleBarLabel = document.getElementById('photo-scalebar-label');

    /* The bar wears the account's dress (Einstellungen → Fotos → Messen) —
       set once: the style cannot change while the map is open, it arrives
       with the page. Colour, EDGE and size are choices; how the edge is
       drawn stays CSS (.photo-scalebar in app.css, which the preview in the
       administration shares). The bar has an edge colour of its own since
       26.09.2026 („Dadurch muss der Rand immer zur Hauptfarbe
       passen") — before, it was always black. */
    scaleBar.style.color = measureStyle.scale_color;
    scaleBar.style.fontSize = Math.round(measureStyle.font_px * 0.9) + 'px';
    scaleBar.style.setProperty('--scalebar-seam', measureStyle.scale_seam_color);
    scaleBarLine.style.background = measureStyle.scale_color;
    const scaleWindow   = document.getElementById('photo-scale-window');

    /** Whatever picture the window shows — voucher photo or cloud recording.
     *  ONE subject for the whole measuring tool, so nothing below has to ask
     *  twice which of the two worlds it is in. */
    function measureSubject() {
        return cloudInWindow ?? photoInWindow;
    }

    /** Who may calibrate THIS picture. A voucher photo: the same circle that
     *  may delete it. A cloud recording: always — it lives in the viewer's
     *  OWN storage, nobody else is ever shown it. Since migration 0090 the
     *  recordings are IN, preview and all: the calibration carries its
     *  reference width, so a line drawn on the 2048-px preview measures
     *  truly on the 6000-px original and back. */
    /** Is the picture in the window the viewer's own? Cloud recordings say
     *  so themselves (mine), voucher photos through their photographer. */
    function pictureMine() {
        if (cloudInWindow !== null) return cloudInWindow.mine !== false;
        if (photoInWindow !== null) {
            return !photoInWindow.photographer_id || photoInWindow.photographer_id === host.start.user.id;
        }
        return true;
    }

    /** May I do `right` to the picture in the window? My own always; somebody
     *  else's when the share I see the find through grants it
     *  (08.10.2026: „Bilder sind Bilder" — delete_photos and measure reach
     *  voucher photos and cloud recordings alike; the server asks the
     *  same, picture_actor in point_owners.php). */
    function pictureRight(right) {
        if (pictureMine()) return true;
        const foreign = host.foreign();
        return foreign !== null && host.sharedRights(foreign).has(right);
    }

    function scaleMayEdit() {
        // The guest never may — the measure bar is gone for him anyway
        // (scaleTopbarSync); once more here, so that no second path offers
        // it to him.
        if (host.guest) return false;
        // Somebody else's picture: look, do not touch — naming and
        // measuring stay with the photographer (11.09.2026), unless the
        // share grants „deine Bilder bearbeiten" (`measure`, 08.10.2026).
        if (!pictureMine()) return pictureRight('measure');
        if (cloudInWindow !== null) return true;
        if (photoInWindow === null) return false;
        return host.mayEditPhotos();
    }

    /** Lay the overlay exactly over the image's resting box. Only JS knows
     *  where the centered image came to rest, hence pixels, not CSS. */
    /* THE VIEW OF THE PICTURE (stage 2 of „Bearbeiten", 07.11.2026: turned
       and cut). pictureView is develop.js's geoView of the picture in the
       window, or null for the original as it is. Everything in this window
       is stored and drawn in the ORIGINAL's pixels — measures, writing,
       ellipses, the scale —, and a turned or cut picture only changes how
       they come to the screen: the image box takes the view's shape (the
       <img> itself goes invisible, the developed canvas draws the view),
       the overlays draw inside a group turned and moved by the view, and
       a pointer finds its picture pixel through viewToNatural(). */
    let pictureView = null;
    const viewW = () => (pictureView ? pictureView.cw : photoImageEl.naturalWidth);
    const viewH = () => (pictureView ? pictureView.ch : photoImageEl.naturalHeight);
    /** Screen pixels at rest per picture pixel. */
    const viewScale = () => (photoImageEl.offsetWidth / (viewW() || 1)) || 1;
    /** A point on the screen as picture pixels, and whether it lies on the view. */
    function clientNatural(x, y) {
        const r = photoImageEl.getBoundingClientRect();
        if (!r.width || !r.height) return null;
        const q = [(x - r.left) / r.width * viewW(), (y - r.top) / r.height * viewH()];
        return { p: pictureView ? pictureView.toNat(q) : q,
                 inside: q[0] >= 0 && q[1] >= 0 && q[0] <= viewW() && q[1] <= viewH() };
    }
    /** A picture pixel as a point on the screen. */
    function naturalClient(p) {
        const r = photoImageEl.getBoundingClientRect();
        const q = pictureView ? pictureView.toView(p) : p;
        return [r.left + q[0] / (viewW() || 1) * r.width, r.top + q[1] / (viewH() || 1) * r.height];
    }
    /** Markup drawn in picture pixels, inside the view's turn and cut. */
    const viewWrap = (inner) => (pictureView ? `<g transform="${pictureView.svg}">${inner}</g>` : inner);
    /** −1 where the view is mirrored on one axis (a word would read backwards), else 1. */
    const viewMirrorSign = () => (pictureView ? pictureView.fx * pictureView.fy : 1);
    /* WORDS STAY READABLE ON A MIRRORED VIEW (09.11.2026): the overlay is
       drawn in the original's pixels inside the view's group, and with a
       mirror in it every label would come out as mirror writing. Each
       written thing is mirrored back by the same axes — a label about its
       letters' middle, a written text with its caret where writtenSvg says
       (data-at). Together with the view's mirror that leaves
       the words as an unmirrored view at the same turn would show them,
       and measuredraw's upright() is told the turn counted backwards
       (drawOverlay), so a label along a line still reads left to right.
       Lines, arrows, boxes keep the mirror: they are the picture's. */
    function viewUnmirror(root) {
        if (!pictureView || (pictureView.fx > 0 && pictureView.fy > 0)) return;
        const back = (x, y) => ` translate(${x} ${y}) scale(${pictureView.fx} ${pictureView.fy}) translate(${-x} ${-y})`;
        for (const g of root.querySelectorAll('g[data-at]')) {
            const [x, y] = g.dataset.at.split(' ').map(Number);
            g.setAttribute('transform', (g.getAttribute('transform') ?? '') + back(x, y));
        }
        for (const text of root.querySelectorAll('text')) {
            if (text.closest('g[data-at]')) continue;
            const x = parseFloat(text.getAttribute('x')), y = parseFloat(text.getAttribute('y'));
            if (!Number.isFinite(x) || !Number.isFinite(y)) continue;
            // A label is one line, mostly centred: its letters' middle lies a third of the size above the baseline.
            const size = parseFloat(text.getAttribute('font-size')) || 0;
            const anchor = text.getAttribute('text-anchor') ?? 'start';
            const half = anchor === 'middle' ? 0 : (text.getComputedTextLength?.() ?? 0) / 2 * (anchor === 'end' ? -1 : 1);
            text.setAttribute('transform', (text.getAttribute('transform') ?? '') + back(x + half, y - size * .3));
        }
    }

    function syncOverlay() {
        /* The box takes the view's shape: fitted into the frame, as large as
           it goes (a cut-out detail fills the window, as in Lightroom). */
        const s = photoImageEl.style;
        if (pictureView) {
            /* AIR ROUND THE PICTURE while turning or cropping: the frame's
               grips sit on its corners, and fitted to the edge they were
               cut in half by the window. */
            const air = cropEditing ? 2 * 18 : 0;
            // The black stage keeps a band at the top for the crop's bar (padding, photowindow.css).
            const cs = getComputedStyle(photoFrame);
            const padX = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
            const padY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom);
            const k = Math.min((photoFrame.clientWidth - padX - air) / pictureView.cw,
                               (photoFrame.clientHeight - padY - air) / pictureView.ch) || 1;
            Object.assign(s, { width: pictureView.cw * k + 'px', height: pictureView.ch * k + 'px',
                maxWidth: 'none', maxHeight: 'none', opacity: '0' });
        } else if (s.opacity === '0') {
            Object.assign(s, { width: '', height: '', maxWidth: '', maxHeight: '', opacity: '' });
        }
        for (const el of [photoOverlay, developCanvas, developSvg]) {
            if (!el) continue;
            const s = el.style;
            s.left   = photoImageEl.offsetLeft + 'px';
            s.top    = photoImageEl.offsetTop + 'px';
            s.width  = photoImageEl.offsetWidth + 'px';
            s.height = photoImageEl.offsetHeight + 'px';
        }
        if (photoImageEl.naturalWidth) {
            for (const svg of [photoOverlay, developSvg]) {
                if (!svg) continue;
                svg.setAttribute('viewBox', `0 0 ${viewW()} ${viewH()}`);
                svg.setAttribute('preserveAspectRatio', 'none');
            }
        }
    }

    /** Put the bar into its corner and forget any drag position. */
    function scaleBarCorner(corner) {
        scaleBar.classList.remove('is-tl', 'is-tr', 'is-bl', 'is-br');
        scaleBar.classList.add('is-' + corner);
        scaleBar.style.left = scaleBar.style.top = '';
    }

    /**
     * The bar itself: the largest round length that fits the budget, at the
     * CURRENT zoom — zoomed in, the same bar reads "200 µm" where it read
     * "1 mm". Runs on every zoom change (imagezoom's 'changed'), on load and
     * on resize; cheap enough for all three.
     */
    function drawScaleBar() {
        const scale = measureSubject()?.scale;
        const umPerShown = photoUmPerPx();
        if (!photoWindow.open || !scale || umPerShown === null) {
            scaleBar.hidden = true;
            return;
        }
        const pxPerUm = viewScale() * photoZoom.stufe() / umPerShown;

        /* THE BAR SITS ON THE PICTURE, NOT ON THE WINDOW
           (29.10.2026: zoomed out, a 4:3 picture on a 16:9 screen left the
           bar in the black beside it). The corners are the corners of the
           part of the picture in sight — the picture's box as drawn, zoom
           and pan included, cut to the frame — handed to the stylesheet as
           insets. Zoomed in, the picture covers the frame and they are 0. */
        const fr = photoFrame.getBoundingClientRect();
        const ir = photoImageEl.getBoundingClientRect();
        const inset = {
            l: Math.max(0, ir.left - fr.left), t: Math.max(0, ir.top - fr.top),
            r: Math.max(0, fr.right - ir.right), b: Math.max(0, fr.bottom - ir.bottom),
        };
        for (const [k, v] of Object.entries(inset)) scaleBar.style.setProperty('--pic-' + k, Math.round(v) + 'px');
        const shownWidth = Math.max(0, fr.width - inset.l - inset.r) || photoFrame.clientWidth;

        // The largest 1-2-5 length whose bar stays under 40 % of the picture in
        // sight — wide enough to judge against, never in the way.
        const best = scaleBarLength(pxPerUm, Math.min(shownWidth * 0.4, 280));
        if (best === null) {
            scaleBar.hidden = true;
            return;
        }
        scaleBarLine.style.width = Math.round(best.px) + 'px';
        scaleBarLabel.textContent = scaleLabel(best.um);
        if (scaleDrag === null) scaleBarCorner(scale.corner ?? 'br');
        scaleBar.hidden = false;
    }

    /* Dragging the bar: free under the finger, and on release it snaps into
       the nearest of the four corners — corners are resting places, not a
       canvas (28.08.2026). stopPropagation keeps the frame from
       reading the same pointer as a pan. */
    let scaleDrag = null;
    scaleBar.addEventListener('pointerdown', (e) => {
        scaleDrag = e.pointerId;
        scaleBar.setPointerCapture(e.pointerId);
        e.preventDefault();
        e.stopPropagation();
    });
    scaleBar.addEventListener('pointermove', (e) => {
        if (scaleDrag !== e.pointerId) return;
        const r = photoFrame.getBoundingClientRect();
        scaleBar.classList.remove('is-tl', 'is-tr', 'is-bl', 'is-br');
        scaleBar.style.left = (e.clientX - r.left - scaleBar.offsetWidth / 2) + 'px';
        scaleBar.style.top  = (e.clientY - r.top - scaleBar.offsetHeight / 2) + 'px';
        e.stopPropagation();
    });
    scaleBar.addEventListener('pointerup', (e) => {
        if (scaleDrag !== e.pointerId) return;
        scaleDrag = null;
        const r = photoFrame.getBoundingClientRect();
        const corner = (e.clientY - r.top < r.height / 2 ? 't' : 'b')
                     + (e.clientX - r.left < r.width / 2 ? 'l' : 'r');
        scaleBarCorner(corner);
        const subject = measureSubject();
        if (subject?.scale) {
            subject.scale.corner = corner;
            /* The corner is part of the picture's presentation and persists —
               but only for whoever may calibrate; a viewer's drag is their own
               window arrangement and stays local. Fire and forget: a corner
               that does not stick is an inconvenience, not a loss. */
            if (scaleMayEdit()) {
                (cloudInWindow !== null
                    ? request(`cloud.php?id=${host.pointId()}&action=scale`, {
                        method: 'POST',
                        body: JSON.stringify({ file: subject.id, scale: subject.scale }),
                    })
                    : request('photos.php', {
                        method: 'POST',
                        body: JSON.stringify({ id: subject.id,
                            scale: subject.scale, _methode: 'PATCH' }),
                    })
                ).catch(() => {});
            }
        }
        e.stopPropagation();
    });

    /* --- „Bearbeiten": the picture developed (migration 0190) ----------------

       06.11.2026: „einen zweiten Modus … der klassische
       Bildbearbeiter … ein paar Regler für Temperatur, Farbe, Sättigung,
       Kontrast … diese Rosette, also eine Ellipse, übers Bild legen, mit
       der man dann Stellen verdunkeln oder erhellen kann. Und auch diese
       Ellipse invertieren … als live gerechneten Layer drüberlegen".

       The window switches between „Messen" and „Bearbeiten" (#photo-mode,
       desk only, the choice kept on the device). In „Bearbeiten" the tool
       rail, the measures and the statistics step aside, and the sidebar in
       the statistics' place carries long sliders (develop.js, the groups)
       and the ellipses. The picture is drawn developed in BOTH modes — one
       measures what one sees; the original under it never changes. The
       settings are the subject's `develop`, stored at the picture
       (cloud.php action=scale, photos.php PATCH) a moment after the last
       change, and only where one may calibrate it (scaleMayEdit). */
    const developAside   = document.getElementById('photo-develop');
    const developSliders = document.getElementById('photo-develop-sliders');
    const radialListEl   = document.getElementById('photo-develop-radial-list');
    const radialBox      = document.getElementById('photo-develop-radial');
    const developAdd     = document.getElementById('photo-develop-add');
    const developAddLinear = document.getElementById('photo-develop-add-linear');
    const developAddBrush = document.getElementById('photo-develop-add-brush');
    const developTabs    = document.getElementById('photo-develop-tabs');
    /* THE TAB ROW'S HEIGHT FOR EVERYTHING THAT STICKS BENEATH IT — the rails
       and „Stacking | HDR" of „Ebenen" (14.11.2026). Measured
       whenever it changes, on the sidebar itself, so every page inherits it:
       the rails set it on their own when they are built, but the panel of
       „Ebenen" is built while the sidebar is still hidden and saw 0. */
    if (developTabs && window.ResizeObserver) {
        new ResizeObserver(() => {
            if (developTabs.offsetHeight) developTabs.parentElement.style.setProperty('--develop-tabs-height', developTabs.offsetHeight + 'px');
        }).observe(developTabs);
    }
    const masksSection   = document.getElementById('photo-develop-masks');
    const desk = window.matchMedia('(min-width: 861px)');

    /* THE SETTINGS SIDEBAR FOLDS AWAY (09.11.2026, after the
       find's head: „Sowas dann bitte auch … im Bearbeiten-Modus für die
       rechte Sidebar mit den Einstellungen"). The small sign in the middle of
       its left rule folds it; folded, a thin bar at the right edge stays —
       the pointer over it lays the sidebar OVER the picture (is-develop-peek:
       its box keeps the bar's width in the row through a negative margin,
       so the picture is not fitted anew for a look), and it folds again
       once the pointer has left it; a click on the bar keeps it open. Per
       device (settings developFolded). */
    const developFoldBtn = document.createElement('button');
    developFoldBtn.type = 'button';
    developFoldBtn.className = 'photo-develop-fold';
    developFoldBtn.title = t('Seitenleiste einklappen');
    developFoldBtn.setAttribute('aria-label', t('Seitenleiste einklappen'));
    developFoldBtn.innerHTML = iconHtml('fold-triangle');
    developAside?.closest('.photo-window-middle')?.append(developFoldBtn);
    const developHandleBtn = document.createElement('button');
    developHandleBtn.type = 'button';
    developHandleBtn.className = 'photo-develop-handle';
    developHandleBtn.title = t('Seitenleiste ausklappen');
    developHandleBtn.setAttribute('aria-label', t('Seitenleiste ausklappen'));
    developHandleBtn.innerHTML = iconHtml('fold-triangle');
    developAside?.prepend(developHandleBtn);
    let developPeekTimer = null;
    function developFold(on, keep = true) {
        photoWindow.classList.toggle('is-develop-folded', on);
        photoWindow.classList.remove('is-develop-peek');
        if (keep) settings.set('developSideFolded', on);
        // The picture takes the room that came or went.
        window.dispatchEvent(new Event('resize'));
    }
    function developPeek() {
        clearTimeout(developPeekTimer);
        if (photoWindow.classList.contains('is-develop-folded')) photoWindow.classList.add('is-develop-peek');
    }
    developHandleBtn.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') developPeek(); });
    /* THE BAND RIGHT OF A FOLDED SIDE'S BAR, to the window's edge, counts as
       the bar (09.11.2026: „diesen Außenbereich noch mit dazu
       nehmen … von Handle bis Fensterrand") — a pointer that overshoots the
       thin bar opens the side and keeps it open. finds.js does the same for
       the find's head and its picture rail. */
    const besideBar = (bar, e) => {
        const r = bar.getBoundingClientRect();
        return r.width > 0 && e.clientX >= r.left && e.clientY >= r.top && e.clientY <= r.bottom;
    };
    document.addEventListener('pointermove', (e) => {
        if (e.pointerType !== 'mouse' || e.buttons) return;
        if (photoWindow.classList.contains('is-develop-folded') && developMode && besideBar(developHandleBtn, e)) developPeek();
        if (!statsPane.hidden && statsPane.classList.contains('is-collapsed') && besideBar(statsToggle, e)) statsPeek();
    });
    developAside?.addEventListener('pointerenter', () => clearTimeout(developPeekTimer));
    developAside?.addEventListener('pointerleave', () => {
        clearTimeout(developPeekTimer);
        developPeekTimer = setTimeout(() => photoWindow.classList.remove('is-develop-peek'), 250);
    });
    developHandleBtn.addEventListener('click', () => developFold(false));
    developFoldBtn.addEventListener('click', () => developFold(true));
    /* ITS OWN NAME, developSideFolded: for an hour on 09.11.2026 the side's
       yes/no was kept under developFolded — the very name under which the
       folded groups of „Einstellungen" keep their LIST (developPanelDraw).
       The two overwrote each other, and a yes there broke the panel
       (new Set(true) throws). A yes/no found there is taken over and the
       list given back. */
    {
        const old = settings.get('developFolded', []);
        if (typeof old === 'boolean') {
            settings.set('developSideFolded', old);
            settings.set('developFolded', []);
        }
    }
    if (settings.get('developSideFolded', false) === true) developFold(true, false);

    /* THE SIDEBAR'S WIDTH, DRAGGED AT ITS LEFT EDGE (12.11.2026:
       „so wie wir das auf der linken Seite auch haben" — finds.js, the
       picture rail's edge, in the same make). Wider means longer sliders,
       and a longer slider is a finer one. Between DEVELOP_MIN and what
       leaves the picture DEVELOP_PICTURE_KEEP; dragged below DEVELOP_FOLD
       the side folds, as the sign on its rule folds it. Kept per device
       (settings developSideWidth), as --develop-width on the window — the
       sidebar, the fold sign and the peeking side all read it. */
    const DEVELOP_MIN = 288, DEVELOP_FOLD = 160, DEVELOP_PICTURE_KEEP = 320;
    const developMiddle = developAside?.closest('.photo-window-middle');
    const developMax = () => Math.max(DEVELOP_MIN, Math.min(720, (developMiddle?.clientWidth ?? 1200) - DEVELOP_PICTURE_KEEP));
    const developWidthApply = (w) => photoWindow.style.setProperty('--develop-width', `${Math.round(w)}px`);
    {
        const kept = Number(settings.get('developSideWidth', 0));
        if (kept >= DEVELOP_MIN) developWidthApply(Math.min(kept, 720));
    }
    const developEdge = document.createElement('div');
    developEdge.className = 'photo-develop-edge';
    developEdge.title = t('Breite der Seitenleiste ziehen');
    developMiddle?.append(developEdge);
    {
        let drag = null;   // {x, width, w}
        let refit = 0;
        const fitSoon = () => {
            // The picture takes the room that came or went — once a frame, not once a pixel.
            if (refit) return;
            refit = requestAnimationFrame(() => { refit = 0; window.dispatchEvent(new Event('resize')); });
        };
        developEdge.addEventListener('pointerdown', (e) => {
            if (e.button !== 0) return;
            e.preventDefault();   // no text selection riding along
            drag = { x: e.clientX, width: developAside.offsetWidth, w: developAside.offsetWidth };
            photoWindow.classList.add('is-develop-resizing');
            try { developEdge.setPointerCapture(e.pointerId); } catch { /* without, then */ }
        });
        developEdge.addEventListener('pointermove', (e) => {
            if (drag === null) return;
            // The edge is the sidebar's LEFT one: to the left is wider.
            const wanted = drag.width - (e.clientX - drag.x);
            const fold = wanted < DEVELOP_FOLD;
            if (fold !== photoWindow.classList.contains('is-develop-folded')) {
                photoWindow.classList.toggle('is-develop-folded', fold);
                fitSoon();
            }
            if (!fold) {
                drag.w = Math.min(Math.max(wanted, DEVELOP_MIN), developMax());
                developWidthApply(drag.w);
                fitSoon();
            }
        });
        const developDragEnd = () => {
            if (drag === null) return;
            const w = drag.w;
            drag = null;
            photoWindow.classList.remove('is-develop-resizing');
            const folded = photoWindow.classList.contains('is-develop-folded');
            developFold(folded);
            if (!folded) settings.set('developSideWidth', Math.round(w));
        };
        developEdge.addEventListener('pointerup', developDragEnd);
        developEdge.addEventListener('pointercancel', developDragEnd);
    }
    let developMode = false;
    let developRenderer_ = undefined;   // undefined: not tried yet; null: no WebGL here
    let developSrc = null;              // the picture the renderer holds
    let develop = { radials: [] };      // the working copy of the subject's settings
    let developOf = null;               // the subject it belongs to
    let versionOpen = 0;                // which of its edits the working copy is (versionsOf)
    let foreign = null;                 // the others' edits of developOf (foreignFetch), or null
    let foreignOpen = null;             // the id of the others' edit the working copy is, or null for the photographer's
    let foreignAsked = null;            // the picture foreignFetch last asked about ('photo:12')
    let maskChosen = null;              // {kind: 'radial'|'linear', i} — the mask whose lines stand on the picture
    let maskPlacing = null;             // 'radial' | 'linear': the next press on the picture lays one
    let developTab = settings.get('developTab', 'adjust');   // Einstellungen · Werkzeuge · Masken · Ausbessern · Info
    let developTabForced = false;      // „Info" only because this picture may not be edited (developPanelDraw)
    let maskBare = false;              // a click on empty ground laid the chosen mask's lines aside (maskBareSet)
    let maskListOpen = false;          // the masks' fold stands open (masksPanelDraw)
    let maskListTop = null;            // the mask whose row stood on top when the fold opened
    let developOriginalAsked = null;   // the recording whose original „Bearbeiten" asked for (developOriginalFetch)
    /* „EBENEN" — THE SIXTH TAB, AND NOT THIS FILE'S (11.11.2026:
       „im Bearbeitungsmodus in der Sidebar einfach einen neuen Reiter … der
       Bearbeitungsmodus ist eigentlich genau der Ort, wo das hingehört").
       Several pictures of the find laid over each other and painted into
       one is assets/js/blend.js; the page that mounts both hands the tool
       in as host.layers. This window gives it its two places — the
       sidebar's panel while the tab is open, and the picture's frame for
       the stage that lies over the picture while one works on a set — and
       tells it when the tab opens and closes and when the viewer shows
       another picture. Where no tool is handed in (the map's window), or
       it is not offered for this find, the tab stays hidden.
       THE TAB IS NOT KEPT ON THE DEVICE like the others: opened on the
       next picture by itself, it would lay tick boxes over the rail and
       fetch a begun set nobody asked for. */
    const layersTool = host.layers ?? null;
    if (layersTool) photoFrame.append(layersTool.stage);
    function layersSync() {
        if (!layersTool) return;
        const may = layersTool.may();
        const tabButton = developTabs?.querySelector('[data-tab="layers"]');
        if (tabButton) tabButton.hidden = !may;
        if (developTab === 'layers' && !may) developTab = settings.get('developTab', 'adjust');
        layersTool.setActive(may && developMode && developTab === 'layers' && photoWindow.open);
    }
    // The retouching's state (the tab itself is built in spotsPanel).
    const SPOT_BRUSH_HOME = { mode: 'heal', radius: .012, feather: 50, opacity: 100 };
    let spotBrush = (() => {
        const kept = settings.get('spotBrush', null);
        /* A NEW PLACE IS ALWAYS „ANGLEICHEN" (14.11.2026, asked
           what a stroke should begin as): the brush carries size, soft edge
           and opacity, never a method — that is chosen per place afterwards
           (spotMethodSet). A brush kept as „Kopieren" or „Entfernen" by an
           earlier version starts over as a heal. */
        return { ...SPOT_BRUSH_HOME, ...(kept && typeof kept === 'object' ? kept : {}), mode: 'heal' };
    })();
    const spotsList = () => (develop.spots ??= []);
    let spotChosen = null;          // index of the spot whose region and source stand on the picture
    /* JUST PAINTED IS NOT CHOSEN (11.11.2026: „wenn ich beim
       nächsten Punkt die Pinselgröße verändern will, wird mir bei dem
       letzten Punkt der Ausschnitt mit vergrößert … Der letzte Punkt soll
       seine Pinselgröße behalten. Nur wenn ich ihn aktiv anklicke, dann
       will ich die Größe verändern können"). A stroke ends with its spot
       shown — outline and source — but the brush's settings stay the
       BRUSH's: size, soft edge, opacity and kind go to the next stroke.
       Only a spot taken in hand — pressed on the picture, its row clicked —
       takes them (spotHeld). Its source's turn is the one setting a brush
       has no part in, so that one reaches the fresh spot too. */
    /* THE SIDEBAR'S SLIDERS DO REACH IT (13.11.2026, once the
       sliders' area showed the chosen place's frame: „das kam daher, dass
       ich das letzte Mal … Shift und Mausrad benutzt [habe] … weil die
       nächste Stelle … eine andere Größe hatte … Vielleicht müssen wir da
       unterscheiden. Mit der Tastenkombination und Mausrad wird nicht …
       die letzte Ausbesserung verändert. Gehe ich aber in der Sidebar an
       die Regler, dann wird die letzte Auswahl benutzt"). So the rule above
       holds for the WHEEL over the picture — the brush at the pointer, for
       the next place — and the panel speaks of the last place even when
       it was only just painted (spotSetting with `panel`, spotsPanel). */
    let spotFresh = false;
    /** The spot the brush's settings speak of: the chosen one, unless it was only just painted. */
    const spotHeld = () => (spotFresh ? null : spotOf(spotChosen));
    let spotPaint = null;           // {id, path: [[px, py], …]}: a stroke being painted, in original pixels
    let spotDrag = null;            // {id, i, part: 'source'|'target', start, from, moved}
    let spotGrab = false;           // the pointer rests on something a press would take hold of — no ring then
    let spotOut = false;            // the pointer is too far beside the picture to paint (spotReaches) — no ring then
    let spotPeek = null;            // the place whose white pin the pointer rests on — shown whole for as long (spotPinAt)
    let spotReveal = false;         // drawings hidden (M), the pointer on the chosen place or its source — that place shows
    let spotHover = null;           // the pointer over the picture, in original pixels — the brush's ring
    let spotRing = null;            // the ring's circles, moved without drawing everything anew
    let spotPixels = null;          // {src, px}: the small copy of the original the source is chosen on
    const spotsOn = () => photoWindow.open && developMode && developTab === 'spots' && !developBefore;
    const spotOf = (i) => (i === null ? null : spotsList()[i] ?? null);
    /* THE BRUSH'S TOOL („Pinsel", 10.11.2026): size, soft edge,
       flow and painting or erasing — for the next stroke, kept on the
       device (settings brushTool). The strokes already laid keep theirs. */
    const BRUSH_TOOL_HOME = { radius: .03, feather: 60, flow: 100, erase: false };
    let brushTool = (() => {
        const kept = settings.get('brushTool', null);
        return { ...BRUSH_TOOL_HOME, ...(kept && typeof kept === 'object' ? kept : {}) };
    })();
    let brushPaint = null;          // {id, stroke}: the stroke in hand, already in its mask's list
    let maskShown = -1;             // the mask the renderer last laid red over the picture (masksOf's index)
    /** A brush mask chosen in „Masken": presses on the picture paint into it. */
    const brushOn = () => photoWindow.open && developMode && developTab === 'masks' && !developBefore
        && maskChosen?.kind === 'brush' && maskPlacing === null;
    /** The brush's radius in use: the spots' in „Ausbessern", the mask brush's in „Masken". */
    const ringRadius = () => (spotsOn() ? spotBrush.radius : brushOn() ? brushTool.radius : null);
    let developBefore = false;
    /* THE MASK'S LINES STEP ASIDE WHILE ITS SLIDERS WORK
       (09.11.2026: „sobald ein Schieberegler betätigt wird … die Linien im
       Bild ausblenden, dass man sich voll auf den Effekt konzentrieren kann
       … wieder einblenden, wenn man mit der Maus dann über das Bild geht").
       True from a mask slider's first move until the pointer crosses the
       picture again (developHandles hides the masks' plane meanwhile).
       „AUSBESSERN" STEPS ASIDE THE SAME WAY (10.11.2026: „wenn
       ich in der Sidebar zum Beispiel einen Regler bediene oder dort
       irgendwo hinklicke auf eine freie Fläche … dass ich mir das Bild
       erstmal ohne die Rahmen anschauen kann"): a slider, a kind, an eye,
       or a press anywhere in the sidebar but on a spot's row — choosing a
       row is wanting to see that spot. */
    let maskLinesQuiet = false;
    // A slider of „Ausbessern" in hand: the chosen place steps aside as well (spotSlideStart).
    let spotSliding = false;
    let spotPanelHover = false;   // the pointer over the sliders of „Ausbessern" — the chosen place shows there (spotPanelShow)
    let spotSlideHeld = false;
    let spotSlideTimer = 0;
    // The source of the chosen place in hand: its frame fades out, and in again after ({out, at}; spotTargetFadeStyle).
    let spotTargetFade = null;
    let developQueued = false;
    let developSave = null;             // the pending store, kept until it ran
    let cropEditing = false;            // „Zuschneiden" open: the whole turned picture, the crop drawn over it
    let cropBar = null;                 // the shapes and „Fertig" floating over the picture while cropping (cropBarSync)
    let cropBefore = null;              // the geo as it stood when „Zuschneiden" opened — Escape goes back to it
    let cropRatio = null;               // the crop's fixed shape (width ÷ height), null for free
    let geoTool = 'crop';               // what the open overlay serves: 'crop' (cut and turn) | 'level' (a line)
    /* „ERWEITERN" (15.11.2026) — the tool's state; what it does stands at expandSync. */
    let cropExpand = false;             // „Erweitern" in the crop's bar: the frame may reach past the picture
    let cropRoom = 0;                   // the air round the whole turned picture while one crops that way (cropRoomFit)
    let expandPreview = null;           // the quick model's border for the cut as it stands WHILE one crops — „Fertig" keeps it
    let expandRun = null;               // the strong model's run: { subject, sig, level, strength, seed, stopped, live, mask, maskOf }
    let expandBusy = null;              // what is computed now, for the picture: { sig, quick, words, pending, w, h }
    let expandTimer = 0, expandToken = 0, expandRev = 0;
    let expandFailed = '';              // the cut the quick model failed at — not tried again by itself
    let expandPlate = null;             // the ring and the words in the middle of the picture while a border is computed
    let expandGuess = null;             // { key, areas }: the strong model's squares per area and stage, for the cut as it stands (expandGuessAsk)
    let expandGuessWanted = '';         // the cut and fineness that count is under way for (expandGuessAsk)
    let expandAreasAsked = '';          // the border whose patches were asked for, to count its areas (expandPicksDraw)
    let expandQuickMissing = false;     // the quick model's files are not on this device: the bar offers „Grob füllen" (cropBarExpandRow)
    /** What the view shows of the whole turned picture: not it (false), it (true), or it with air round it. */
    const wholeNow = () => (!cropEditing ? false : cropExpand && geoTool === 'crop' ? cropRoom : true);

    /* THE VIEW FOLLOWS THE SETTINGS: turned and cut as develop.geo says, the
       whole turned picture while one crops, the original while „Vorher" is
       held. Everything that draws is told (the box, the overlays, the bar). */
    function viewUpdate() {
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        const turned = W > 0 && !developBefore && (geoSet(develop) || cropEditing);
        const view = turned ? geoView(develop, W, H, wholeNow()) : null;
        pictureView = view && !(view.identity && !cropEditing) ? view : null;
        // The filled border follows the cut: let go where it no longer fits, asked for where it is missing.
        expandSync();
        cropBarSync();
        syncOverlay();
        drawScaleBar();
        drawOverlay();
        developRedraw();
    }

    /* WHO MAY CHANGE WHAT IS OPEN. The photographer's own edits: he, and
       nobody through a share — `measure` reaches scale, measures and name,
       never his edits (11.11.2026: „Alles beim Besitzer"; the
       server says the same, api/v1/photos.php and cloud.php). An edit of
       somebody else's (foreignOpen): only the one who made it. */
    const developOwnMay = () => measureSubject() !== null && pictureMine() && scaleMayEdit();
    const developMay = () => (foreignOpen !== null ? foreignEdit()?.user_id === host.start.user.id : developOwnMay());
    /* NO EDITING WITHOUT A CLOUD (10.11.2026: „Cloud ist
       Grundvoraussetzung für Bildbearbeitung … die einfachen kleinen
       pixeligen Belegbilder können gar nichts"). A recording lies in a
       cloud by nature; a voucher photo says so itself (in_cloud, photos.php)
       — one still on this server, of somebody without a cloud or waiting
       for its way there, shows no „Bearbeiten" at all, and the API refuses
       it too. The mode kept on the device is left alone: the next picture
       in a cloud opens in it again. Measuring is untouched. */
    const developHere = () => cloudInWindow !== null || photoInWindow?.in_cloud === true;

    function developSwitcherSync() {
        const switcher = document.getElementById('photo-mode');
        if (switcher) switcher.hidden = !developHere();
    }

    function developModeSet(mode) {
        developSwitcherSync();
        developMode = mode === 'develop' && desk.matches && developHere();
        photoWindow.classList.toggle('is-developing', developMode);
        // The whole page goes grey and quiet around the picture (app.css, is-editing-picture).
        document.documentElement.classList.toggle('is-editing-picture', developMode && photoWindow.open);
        for (const b of document.querySelectorAll('#photo-mode [data-mode]')) {
            const on = b.dataset.mode === (developMode ? 'develop' : 'measure');
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', String(on));
        }
        if (developMode) {
            foreignFetch(developOf);
            // A tool armed for measuring would take the next click on the picture.
            pathFinish();
            calibrateEnd();
            measureEnd();
        }
        maskPlacing = null;
        // The brush's cursor belongs to „Ausbessern" alone (developPanelDraw sets it there).
        if (!developMode) photoFrame.classList.remove('is-spotting');
        if (!developMode) castPickEnd();
        if (!developMode && cropEditing) cropEditing = false;
        // Below „fitted" only while editing — measuring starts from the whole picture again.
        if (!developMode && photoZoom.stufe() < 1) photoZoom.reset();
        developAside.hidden = !developMode;
        developPanelDraw();
        exportSync();
        viewUpdate();
    }
    document.getElementById('photo-mode')?.addEventListener('click', (e) => {
        const b = e.target.closest('[data-mode]');
        if (!b) return;
        settings.set('photoMode', b.dataset.mode);
        developModeSet(b.dataset.mode);
    });

    /** The picture in the window changed: its settings become the working copy; a pending store goes out first. */
    function developSubjectSync() {
        const subject = measureSubject();
        if (subject === developOf) return;
        developFlush();
        aiJobsLeave();
        developOf = subject;
        /* KEPT FROM THE LAST VISIT (editsaves.js): the edit as it stood when
           the cloud refused it, not the older one the server still has.
           Once per picture and visit — later saves of this visit are newer
           in the page already. */
        const waiting = subject ? editSaveWaiting(developKey(subject)) : null;
        if (waiting?.restored && waiting.kind === 'own') {
            subject.develop = waiting.body.develop;
            if (waiting.body.versions !== undefined && 'develop_versions' in subject) subject.develop_versions = waiting.body.versions;
            waiting.restored = false;
        }
        // The VALID edit opens — the one the tiles show (versionsOf).
        versionOpen = versionValid(subject);
        foreign = null;
        foreignOpen = null;
        foreignAsked = null;
        develop = structuredClone(versionEdit(subject, versionOpen) ?? {});
        develop.radials ??= [];
        aiJobsEnter(subject);
        maskChosen = null;
        maskPlacing = null;
        spotChosen = null;
        spotPaint = null;
        spotDrag = null;
        spotPixels = null;
        cropEditing = false;
        // The last picture's development must not stand over the next one while it loads.
        developCanvas.hidden = true;
        developSrc = null;
        pictureView = null;
        if (photoWindow.open) developModeSet(settings.get('photoMode', 'measure'));
        // „Ebenen": a layer's tile shows that layer alone, another picture steps out of the set (blend.js).
        layersTool?.pictureShown();
        // The AI's patches come from the picture's cloud (develop_patch.php) — drawn in as they arrive.
        if (subject) aiPatchesFor(develop, developKey(subject)).then(() => { if (developOf === subject) developSoon(); });
    }

    /** The picture's key as the thumbnails and plates know it — 'photo:12' or 'cloud:34'. */
    function developKey(subject) {
        return (subject === cloudInWindow ? 'cloud:' : 'photo:') + subject.id;
    }

    /* --- SEVERAL EDITS OF ONE PICTURE (11.11.2026) -------------
       „dass man zwei Bearbeitungen auf ein Foto legen kann … Bearbeitung 1
       und dann noch ein Plus dahinter … wo man dann vergleichen kann". His
       decisions: a new edit is a COPY of the open one, one compares by
       switching (V), ONE edit is valid and wears a star, and only that one
       counts outside this window — the tiles, the plates, the report; the
       edits are only numbered.

       THE VALID ONE STAYS IN subject.develop, where every other part of the
       page reads it; the others lie beside it in subject.develop_versions =
       { at, list } (migration 0192, includes/photos.php) — list holds every
       edit in its numbered order, `at` the valid one's slot, which stays
       null there. A slot is an edit's number less one and does not change
       when another becomes valid (the two only trade places); deleting one
       closes the gap. `versionOpen` is the slot in the working copy.

       SLOT -1 IS THE ORIGINAL, shown as „0" (15.11.2026: „bei
       der Bearbeitung noch den Platz 0 machen. Um das Original
       anzuschauen … das Original dann gültig machen … die Position 1
       löschen und 0 ist dann gültig. Das wäre quasi das gleiche wie
       zurücksetzen"). It replaced the buttons „Original" (held, now only
       the key O) and „Zurücksetzen". The original valid is `at` = -1:
       subject.develop null, every edit in the list. A picture without a
       single edit — develop empty, no list — is the same state, and so is
       an only edit pushed back to nothing: the Original is valid there
       too. The Original holds nothing to change; whatever is changed on
       it becomes a NEW edit (versionFork) — the first one of a picture
       valid at once, as before, any further one beside the valid. */
    const VERSIONS_MAX = 9;
    function versionsOf(subject) {
        const v = subject?.develop_versions;
        return v && Array.isArray(v.list) && v.at < v.list.length
            && (v.at === -1 ? v.list.length >= 1 : v.at >= 0 && v.list.length >= 2) ? v : null;
    }
    /** How many edits there are — the Original not counted. */
    const versionCount = (subject) => versionsOf(subject)?.list.length ?? (developEmpty(subject?.develop) ? 0 : 1);
    /** The valid edit's slot — -1 for the Original. */
    const versionValid = (subject) => versionsOf(subject)?.at ?? (versionCount(subject) ? 0 : -1);
    /** An edit's settings by its slot — the Original's are none. */
    function versionEdit(subject, slot) {
        if (slot === -1) return null;
        const v = versionsOf(subject);
        return !v || slot === v.at ? subject?.develop ?? null : v.list[slot] ?? null;
    }
    /** Settings into an edit's slot — the valid one's go to subject.develop; the Original takes none. */
    function versionPut(subject, slot, d) {
        if (slot === -1) return;
        const v = versionsOf(subject);
        if (!v || slot === v.at) subject.develop = d;
        else v.list[slot] = d;
    }
    /** Every edit in its numbered order, the valid one's settings in its place. */
    function versionsAll(subject) {
        const v = versionsOf(subject);
        if (v) return v.list.map((d, i) => (i === v.at ? subject.develop ?? null : d ?? null));
        return developEmpty(subject?.develop) ? [] : [subject.develop];
    }
    /** The edits written back with `at` valid — into the shape versionsOf reads (and the server, photo_develop_versions_clean). */
    function versionsSet(subject, at, all) {
        subject.develop = at >= 0 ? all[at] ?? null : null;
        subject.develop_versions = (at === -1 ? all.length >= 1 : all.length >= 2)
            ? { at, list: all.map((d, i) => (i === at ? null : d ?? null)) } : null;
    }
    /**
     * Something was changed while the Original was open: that becomes an
     * edit of its own, and the working copy goes on in it — not reloaded,
     * so a slider or a brush keeps going under the finger. False where
     * there is no room for another edit; the change is then taken back.
     */
    function versionFork(subject) {
        const all = versionsAll(subject);
        if (all.length >= VERSIONS_MAX) {
            host.status(t('Höchstens %d Bearbeitungen — lösche erst eine.', VERSIONS_MAX), true);
            editLoad(null);
            return false;
        }
        // The change is in it before the bar counts it — an empty slot would read as no edit at all (versionCount).
        all.push(developClean(develop));
        const slot = all.length - 1;
        // The first edit of a picture counts at once, as it always did; a further one stands beside the valid.
        versionsSet(subject, slot === 0 ? 0 : versionValid(subject), all);
        versionOpen = slot;
        for (const job of aiJobs) if (job.subject === subject && job.edit === null && job.slot === -1) job.slot = slot;
        if (slot > 0) host.status(t('Bearbeitung %d angelegt — sie beginnt beim Original.', slot + 1));
        versionsDraw();
        return true;
    }
    /** What the server is sent beside `develop`: null for „one edit only", the valid slot empty. */
    function versionsWire(subject) {
        const v = versionsOf(subject);
        return v ? { at: v.at, list: v.list.map((d, i) => (i === v.at ? null : structuredClone(d ?? null))) } : null;
    }

    /** The working copy becomes another edit of the same picture — nothing is stored here. */
    function versionLoad(slot) {
        versionOpen = slot;
        foreignOpen = null;
        editLoad(versionEdit(developOf, slot));
    }
    /** The working copy becomes these settings — one of the photographer's edits or somebody else's. */
    function editLoad(d) {
        const subject = developOf;
        develop = structuredClone(d ?? {});
        develop.radials ??= [];
        aiJobsEnter(subject);
        maskChosen = null;
        maskPlacing = null;
        spotChosen = null;
        spotFresh = false;
        spotPaint = null;
        spotDrag = null;
        spotPixels = null;
        if (cropEditing) { cropEditing = false; cropBefore = null; }
        // Another picture, another edit: a border under way was this one's.
        expandLeave();
        if (subject) aiPatchesFor(develop, developKey(subject)).then(() => { if (developOf === subject) developSoon(); });
        developPanelDraw();
        developSoon();
        viewUpdate();
    }

    /** Open another edit — the open one's last change goes out first. The zoom stays, to compare the same place. */
    function versionOpenSet(slot) {
        const subject = developOf;
        if (!subject || (slot === versionOpen && foreignOpen === null) || slot < -1 || slot >= versionCount(subject)) return;
        developFlush();
        aiJobsLeave();
        versionLoad(slot);
        host.status(slot === -1 ? t('Das Original') : foreign?.role === 'editor' ? t('Bearbeitung von %s', personName(foreign.owner_name)) : t('Bearbeitung %d', slot + 1));
    }

    /**
     * „+": a new edit, opened at once — a copy of the open one, or with
     * `fresh` an empty one. `valid`: it counts at once (the photographer's
     * „Gültig machen" on somebody else's edit). `quiet`: the caller says
     * what happened.
     */
    function versionAdd(quiet = false, fresh = false, valid = false) {
        const subject = developOf;
        if (!subject || !developOwnMay() || versionCount(subject) >= VERSIONS_MAX) return;
        developFlush();
        const from = versionOpen;
        const fromForeign = foreignEdit();
        const now = developClean(develop);
        // Somebody else's edit open: it is copied, and none of mine is written over.
        if (!fromForeign) versionPut(subject, versionOpen, now);
        const all = versionsAll(subject);
        // Places the AI is still filling stay with the edit they were drawn on (developClean leaves them out).
        all.push(now && !fresh ? structuredClone(now) : null);
        const slot = all.length - 1;
        versionsSet(subject, valid ? slot : versionValid(subject), all);
        aiJobsLeave();
        versionLoad(slot);
        developQueue();
        if (!quiet && (fresh || (from === -1 && !fromForeign))) host.status(t('Bearbeitung %d angelegt — sie beginnt beim Original.', slot + 1));
        else if (!quiet) host.status(fromForeign ? t('Bearbeitung %d angelegt — eine Kopie der Bearbeitung von %s.', slot + 1, personName(fromForeign.name))
            : t('Bearbeitung %d angelegt — eine Kopie von Bearbeitung %d.', slot + 1, from + 1));
    }

    /** „Gültig machen": the open edit — or the Original — becomes the valid one; the numbers stay. */
    function versionValidSet() {
        const subject = developOf;
        if (!subject || versionValid(subject) === versionOpen || foreignOpen !== null || !developMay()) return;
        versionPut(subject, versionOpen, developClean(develop));
        versionsSet(subject, versionOpen, versionsAll(subject));
        developQueue();
        developPanelDraw();
        host.status(versionOpen === -1 ? t('Das Original gilt jetzt überall.') : t('Bearbeitung %d gilt jetzt überall.', versionOpen + 1));
    }

    /** The bin: the open edit goes — never the valid one; the valid one opens. */
    async function versionDelete() {
        const subject = developOf;
        if (!subject || versionOpen === -1 || versionValid(subject) === versionOpen || foreignOpen !== null || !developMay()) return;
        const gone = versionOpen;
        if (!await confirm({
            title: t('Bearbeitung %d löschen', gone + 1),
            text: t('Die Einstellungen von Bearbeitung %d gehen verloren. Das Original und die anderen Bearbeitungen bleiben.', gone + 1),
            button: t('Löschen'),
            danger: true,
        })) return;
        if (developOf !== subject || versionOpen !== gone || foreignOpen !== null) return;
        clearTimeout(developSave?.timer);
        developSave = null;
        // Places the AI still fills for it are not wanted; those of later edits move one number down.
        for (const job of aiJobs) {
            if (job.subject !== subject) continue;
            if (job.slot === gone) job.dropped = true;
            else if (job.slot > gone) job.slot--;
        }
        const all = versionsAll(subject);
        const before = versionValid(subject);
        all.splice(gone, 1);
        const valid = before > gone ? before - 1 : before;
        versionsSet(subject, valid, all);
        versionLoad(valid);
        developQueue();
        host.status(t('Bearbeitung %d gelöscht.', gone + 1));
    }

    /* --- THE OTHERS' EDITS OF THE PICTURE (11.11.2026,
       „Fremdbearbeitungen", stage 2; includes/picture_edits.php) ---------
       A friend who may edit the picture — the share's tick „Bilder
       bearbeiten" or the picture's switch „Andere dürfen bearbeiten" — lays
       HIS OWN edits beside the photographer's. They stand in the same bar,
       one group per person with his name; only the photographer makes one
       valid, and then it is COPIED into his own edits and is his („Gültig
       machen" on it = „+" and „Gültig machen" in one).

       ONLY THE DATABASE: they never go into subject.develop or
       develop_versions, and never into the sidecar in the cloud — fetched
       per picture (foreignFetch), stored per edit (foreignQueue).

       WHAT THE FRIEND SEES: the photographer's VALID edit, never his
       others (they are his drafts), his own edits, and everybody else's on
       the picture. The photographer's is only to look at; „+" copies
       whatever is open into a new edit of the friend's own.

       „ENTFERNEN" TOO (stage 4, 12.11.2026): the model runs on the
       friend's device, and the patches go with the save of his edit into
       the PHOTOGRAPHER's cloud („ein Foto, eine Bearbeitung, eine Cloud.
       Alles beim Besitzer") — the friend needs none. A place still being
       filled belongs to the edit it was drawn on (job.edit), as the
       photographer's belong to their slot. */
    const FOREIGN_MAX = 9;   // PICTURE_EDITS_PER_EDITOR
    const foreignEdit = () => (foreignOpen === null ? null : foreign?.edits.find((e) => e.id === foreignOpen) ?? null);
    const foreignMine = () => (foreign?.edits ?? []).filter((e) => e.user_id === host.start.user.id);
    /** An edit's number in the bar's ONE count (editsInOrder; the Original is 0) — „4", with „Finn" small beside it. */
    const foreignNumber = (e) => editsInOrder().findIndex((x) => x.edit === e.id);
    /**
     * Keep one of the friend's edits — `e.develop` as it stands now, with
     * the patches it names that the cloud does not hold yet (developSend
     * does the same for the photographer). `key` names the picture, so a
     * place the AI finished after the window moved on goes to ITS picture.
     */
    async function foreignSend(key, e) {
        const [kind, id] = key.split(':');
        const body = { action: 'save', kind, id: Number(id), edit: e.id, develop: e.develop };
        await editSave({ key: 'edit:' + e.id, kind: 'foreign', gen: ++editGen, body }, true);
    }

    /** Ask once per picture what the others laid on it — only in „Bearbeiten", and only where the picture came with its edits. */
    async function foreignFetch(subject) {
        if (!subject || !developMode || !('develop_versions' in subject)) return;
        const key = developKey(subject);
        if (foreignAsked === key && developOf === subject) return;
        foreignAsked = key;
        const [kind, id] = key.split(':');
        let data = null;
        try {
            data = await api.get('picture_edits', { kind, id });
        } catch {
            // Not to be edited by me, or the server away: the bar stays the photographer's.
            data = null;
        }
        if (developOf !== subject) return;
        foreign = data?.ready ? { key, role: data.role, open: data.open === true, owner_name: data.owner_name || '', edits: data.edits ?? [] } : null;
        // One's own edit that waits for the cloud since the last visit: its newer state, not the stored one.
        for (const e of foreign?.edits ?? []) {
            const waiting = editSaveWaiting('edit:' + e.id);
            if (waiting?.restored) {
                e.develop = waiting.body.develop;
                waiting.restored = false;
            }
        }
        developPanelDraw();
    }

    /** Open somebody else's edit — or, for the friend, one of his own. `quiet`: the caller says what happened. */
    function foreignOpenSet(id, quiet = false) {
        const e = foreign?.edits.find((x) => x.id === id);
        if (!developOf || !e || id === foreignOpen) return;
        developFlush();
        aiJobsLeave();
        foreignOpen = id;
        editLoad(e.develop);
        if (!quiet) host.status(e.user_id === host.start.user.id ? t('Deine Bearbeitung %d', foreignNumber(e))
            : t('Bearbeitung %d von %s', foreignNumber(e), personName(e.name)));
    }

    /** Store the friend's open edit a moment after the last change — the edit captured now. */
    function foreignQueue() {
        const e = foreignEdit();
        if (!e || e.user_id !== host.start.user.id || !foreign) return;
        e.develop = developClean(develop);
        const key = foreign.key;
        clearTimeout(developSave?.timer);
        const run = () => {
            developSave = null;
            return foreignSend(key, e);
        };
        developSave = { run, timer: setTimeout(run, 700) };
    }

    /** The friend's „+": a new edit of his own, a copy of whatever is open. */
    async function foreignAdd(fresh = false) {
        const subject = developOf;
        if (!subject || foreign?.role !== 'editor' || foreignMine().length >= FOREIGN_MAX) return;
        developFlush();
        const key = foreign.key;
        const [kind, id] = key.split(':');
        let answer;
        try {
            const body = { action: 'save', kind, id: Number(id), develop: fresh ? null : developClean(develop) };
            // A copy of an edit whose newest place is not in the cloud yet takes its patch along.
            const sending = body.develop ? aiPatchesUnstored(body.develop).slice(0, 24) : [];
            if (sending.length) {
                body.patches = {};
                for (const pid of sending) {
                    const png = await aiPatchPng(pid);
                    if (png) body.patches[pid] = png;
                }
            }
            answer = await request('picture_edits.php', { method: 'POST', body: JSON.stringify(body) });
            for (const pid of Object.keys(body.patches ?? {})) aiPatchStored(pid);
        } catch (error) {
            host.status(error?.message || t('Die Bearbeitung ließ sich nicht anlegen.'), true);
            return;
        }
        if (developOf !== subject || foreign?.key !== key) return;
        foreign.edits = answer.edits ?? foreign.edits;
        foreignOpen = null;
        foreignOpenSet(answer.edit, true);
        const e = foreignEdit();
        if (e) host.status(fresh ? t('Deine Bearbeitung %d angelegt — sie beginnt von vorne.', foreignNumber(e))
            : t('Deine Bearbeitung %d angelegt — eine Kopie der offenen.', foreignNumber(e)));
    }

    /** The friend's bin: one of his own edits goes; the photographer's opens. */
    async function foreignDelete() {
        const subject = developOf;
        const e = foreignEdit();
        if (!subject || !e || e.user_id !== host.start.user.id) return;
        const n = foreignNumber(e);
        if (!await confirm({
            title: t('Deine Bearbeitung %d löschen', n),
            text: t('Die Einstellungen deiner Bearbeitung %d gehen verloren. Das Bild und die anderen Bearbeitungen bleiben.', n),
            button: t('Löschen'),
            danger: true,
        })) return;
        if (developOf !== subject || foreignOpen !== e.id) return;
        clearTimeout(developSave?.timer);
        developSave = null;
        try {
            await request('picture_edits.php', { method: 'POST', body: JSON.stringify({ action: 'delete', edit: e.id }) });
        } catch (error) {
            host.status(error?.message || t('Die Bearbeitung ließ sich nicht löschen.'), true);
            return;
        }
        if (developOf !== subject || !foreign) return;
        // Places the AI still fills for it are not wanted any more.
        for (const job of aiJobs) if (job.edit === e.id) job.dropped = true;
        foreign.edits = foreign.edits.filter((x) => x.id !== e.id);
        versionLoad(versionValid(subject));
        host.status(t('Deine Bearbeitung %d gelöscht.', n));
    }

    /** The photographer's „Gültig machen" on somebody else's edit: copied into his own, and that one made valid. */
    function foreignAdopt() {
        const e = foreignEdit();
        const subject = developOf;
        if (!e || !subject || !developOwnMay()) return;
        if (versionCount(subject) >= VERSIONS_MAX) {
            host.status(t('Höchstens %d Bearbeitungen — lösche erst eine von deinen.', VERSIONS_MAX), true);
            return;
        }
        versionAdd(true, false, true);
        host.status(t('Die Bearbeitung von %s gilt jetzt überall — als deine Bearbeitung %d.', personName(e.name), versionOpen + 1));
    }

    /** The photographer's switch „Andere dürfen bearbeiten". */
    async function foreignOpenToggle() {
        const subject = developOf;
        if (!subject || foreign?.role !== 'owner') return;
        const key = foreign.key;
        const [kind, id] = key.split(':');
        const want = !foreign.open;
        try {
            await request('picture_edits.php', { method: 'POST', body: JSON.stringify({ action: 'open', kind, id: Number(id), open: want }) });
        } catch (error) {
            host.status(error?.message || t('Das ließ sich nicht umstellen.'), true);
            return;
        }
        if (foreign?.key !== key) return;
        foreign.open = want;
        versionsDraw();
        host.status(want ? t('Wer dieses Bild sehen darf, darf es jetzt auch bearbeiten.')
            : t('Andere dürfen dieses Bild nicht mehr bearbeiten. Ihre Bearbeitungen bleiben.'));
    }

    /**
     * Every edit the bar offers, in its order — what „V" walks through.
     * First the Original (slot -1, „0"). The photographer: his own, then
     * the others'. A friend: the photographer's valid one, his own, then
     * everybody else's.
     */
    function editsInOrder() {
        const subject = developOf;
        const out = [{ slot: -1 }];
        if (foreign?.role === 'editor') {
            if (versionValid(subject) >= 0) out.push({ slot: versionValid(subject) });
        } else for (let i = 0; i < versionCount(subject); i++) out.push({ slot: i });
        const mine = foreign?.role === 'editor' ? foreignMine() : [];
        for (const e of mine) out.push({ edit: e.id });
        for (const e of foreign?.edits ?? []) if (!mine.includes(e)) out.push({ edit: e.id });
        return out;
    }
    const editIsOpen = (x) => (x.edit !== undefined ? foreignOpen === x.edit : foreignOpen === null && versionOpen === x.slot);
    function editOpen(x) {
        if (x.edit !== undefined) foreignOpenSet(x.edit);
        else versionOpenSet(x.slot);
    }

    /* THE HEAD'S PART: „Bearbeitung", the numbers as one split button
       (the open one in the accent, the valid one with a star), „+", and —
       only while an edit that is not the valid one is open — „Gültig
       machen" and the bin. Only for whoever may edit the picture, and not
       in „Ebenen", which speaks of several pictures.
       ONE COUNT FOR EVERYBODY'S EDITS (15.11.2026: „ich hätte
       gerne einfach eine Zeile Bearbeitung, die dann hochzählt, 1, 2, 3, 4.
       Und bei der Nummer selbst würde dann dabei stehen, in ganz klein, von
       wem sie ist … bei den eigenen steht nichts dabei … nicht mal das
       ‚von', sondern einfach nur der Name"). Until then every person had a
       split button of his own behind his name, and every one counted from 1
       — „Deine 1 2 · Finn 1". Now one split button in the order „V" walks
       (editsInOrder), the number continuing through all of them, and a
       somebody else's edit carries his first name small inside its button. */
    const versionsEl = document.getElementById('photo-versions');
    function versionsDraw() {
        if (!versionsEl) return;
        const subject = developOf;
        const editor = foreign?.role === 'editor';
        // Only where the picture came with its edits (photo_prepare, cloud_files_rows) — a page that never heard of them must not write over them.
        const show = developMode && !!subject && 'develop_versions' in subject && (developOwnMay() || editor) && developTab !== 'layers';
        versionsEl.hidden = !show;
        if (!show) return;
        versionsEl.textContent = '';
        const n = versionCount(subject);
        const valid = versionValid(subject);
        const others = (foreign?.edits ?? []).filter((e) => e.user_id !== host.start.user.id);
        const mine = editor ? foreignMine() : [];
        const label = document.createElement('span');
        label.className = 'photo-versions-label';
        label.textContent = t('Bearbeitung');
        versionsEl.append(label);
        // The open edit waits for the cloud — also what no place in a list shows (a slider, a mask).
        const openKey = editKeyOpen();
        if (openKey && editSaveWaiting(openKey)) versionsEl.append(editUnsavedMark());
        /* THE NAME AS EVERY PLACE SHOWS IT (names.js, personName): the first
           name, and a letter of the surname where the circle holds two —
           the whole one in the tooltip. The head carries the mode, the
           picture's name and the zoom as well;
           full names squeezed the mode switch to nothing at 1440 px. */
        const short = (name) => personName(name);
        const row = (entries, add = null) => {
            const list = document.createElement('span');
            list.className = 'photo-versions-list';
            list.setAttribute('role', 'group');
            list.setAttribute('aria-label', t('Bearbeitung'));
            for (const x of entries) {
                const b = document.createElement('button');
                b.type = 'button';
                const on = editIsOpen(x);
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', String(on));
                if (x.star) b.append(icon('star', { class: 'photo-versions-star' }));
                if (x.text) b.append(x.text);
                if (x.by) {
                    const by = document.createElement('span');
                    by.className = 'photo-versions-by';
                    by.textContent = short(x.by);
                    b.append(by);
                }
                b.title = x.title;
                if (!x.text) b.setAttribute('aria-label', x.title);
                b.addEventListener('click', () => editOpen(x));
                list.append(b);
            }
            if (add) list.append(add);
            versionsEl.append(list);
        };
        /* „+" OPENS A SMALL MENU (14.11.2026: „wenn man über das
           Plus fährt, klappt ein kleines gestyltes CSS-Menü auf. Und dort
           kann man dann auswählen, Kopie oder von vorne anfangen"). Until
           then the plus copied the open edit at once. The menu stands
           beside the split button and not in it — the button clips what
           leaves its rounded box — and opens by CSS on resting over the
           plus or the menu (.photo-versions-menu); a click on the plus
           opens it too, for whoever clicks first and for a finger. */
        let addMenu = null;
        const plus = (full, add) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'photo-versions-add';
            b.append(icon('plus'));
            b.setAttribute('aria-label', t('Neue Bearbeitung'));
            b.disabled = !!full;
            if (full) {
                b.title = full;
                return b;
            }
            b.setAttribute('aria-haspopup', 'menu');
            const menu = document.createElement('div');
            menu.className = 'popup-menu photo-versions-menu';
            menu.setAttribute('role', 'menu');
            // On the Original a copy and a fresh start are the same thing — one entry.
            const onOriginal = foreignOpen === null && versionOpen === -1;
            for (const [words, fresh] of onOriginal ? [[t('Von vorne anfangen'), true]]
                : [[t('Kopie %s', openName()), false], [t('Von vorne anfangen'), true]]) {
                const entry = document.createElement('button');
                entry.type = 'button';
                entry.className = 'popup-menu-entry';
                entry.setAttribute('role', 'menuitem');
                entry.textContent = words;
                entry.addEventListener('click', () => {
                    menu.classList.remove('is-open');
                    add(fresh);
                });
                menu.append(entry);
            }
            // Under the plus, wherever the bar has put it (the bar is the menu's frame of reference, .photo-versions).
            const place = () => { menu.style.left = `${b.offsetLeft}px`; };
            b.addEventListener('pointerenter', place);
            b.addEventListener('focus', place);
            b.addEventListener('click', () => {
                place();
                menu.classList.toggle('is-open');
            });
            menu.addEventListener('pointerleave', () => menu.classList.remove('is-open'));
            addMenu = menu;
            return b;
        };
        const openName = () => {
            const e = foreignEdit();
            return e ? (e.user_id === host.start.user.id ? t('von Bearbeitung %d', foreignNumber(e)) : t('der Bearbeitung %d von %s', foreignNumber(e), personName(e.name)))
                : editor ? t('der Bearbeitung von %s', personName(foreign.owner_name)) : t('von Bearbeitung %d', versionOpen + 1);
        };
        // Every edit in the order „V" walks: one number each, the maker's first name on a foreign one.
        const edits = new Map((foreign?.edits ?? []).map((e) => [e.id, e]));
        const entries = editsInOrder().map((x, nr) => {
            if (x.slot === -1) {
                return { ...x, star: valid === -1, text: '0',
                    title: valid === -1 ? t('Das Original — gilt überall (Taste V wechselt)') : t('Das Original (Taste V wechselt)') };
            }
            if (x.edit === undefined && editor) {
                // A friend's view: the photographer's valid edit, to look at.
                return { ...x, star: true, text: String(nr), by: foreign.owner_name,
                    title: t('Bearbeitung %d von %s — gilt überall (Taste V wechselt)', nr, personName(foreign.owner_name)) };
            }
            if (x.edit === undefined) {
                return { ...x, star: x.slot === valid, text: String(nr),
                    title: x.slot === valid ? t('Bearbeitung %d — gilt überall (Taste V wechselt)', nr) : t('Bearbeitung %d (Taste V wechselt)', nr) };
            }
            const e = edits.get(x.edit);
            const own = e?.user_id === host.start.user.id;
            return { ...x, text: String(nr), by: own ? null : e?.name,
                title: own ? t('Deine Bearbeitung %d (Taste V wechselt)', nr) : t('Bearbeitung %d von %s (Taste V wechselt)', nr, personName(e?.name)) };
        });
        row(entries, editor
            ? plus(mine.length >= FOREIGN_MAX && t('Höchstens %d Bearbeitungen', FOREIGN_MAX), foreignAdd)
            // Before the first edit there is nothing to add beside: whatever is changed on the Original becomes edit 1 (versionFork).
            : plus(n === 0 ? t('Ändere etwas am Bild — daraus wird Bearbeitung 1')
                : n >= VERSIONS_MAX && t('Höchstens %d Bearbeitungen', VERSIONS_MAX), (fresh) => versionAdd(false, fresh)));
        if (addMenu) versionsEl.append(addMenu);
        /* THE PHOTOGRAPHER'S SWITCH „Andere dürfen bearbeiten": everybody
           who may see the picture may then add edits of his own. A sign
           that is pressed or not, like the measure tools' — it is a state
           of the picture, so it says which in its title. */
        if (foreign?.role === 'owner') {
            const sw = document.createElement('button');
            sw.type = 'button';
            sw.className = 'button subtle small square photo-versions-open';
            sw.append(icon('people'));
            sw.classList.toggle('is-active', foreign.open);
            sw.setAttribute('aria-pressed', String(foreign.open));
            sw.title = foreign.open ? t('Andere dürfen dieses Bild bearbeiten — klicken schaltet es aus')
                : t('Andere dürfen dieses Bild bearbeiten: aus — klicken schaltet es ein');
            sw.setAttribute('aria-label', t('Andere dürfen bearbeiten'));
            sw.addEventListener('click', foreignOpenToggle);
            versionsEl.append(sw);
        }
        /* THE TWO KEEP THEIR PLACE (11.11.2026: „wenn ich
           zwischen den Versionen wechsle, springt mir die Position der
           Nummern, weil dahinter die zwei Buttons eingeblendet werden"). The
           head stands at the right edge, so whatever comes and goes behind
           the numbers pushes them sideways under the pointer. With two edits
           or more the two are always there; where they have nothing to do
           they are only held invisible (is-reserved) — no click, no tab
           stop, but their width stays. The friend has no „Gültig machen". */
        const openE = foreignEdit();
        // With the Original in the row one edit is already a choice between two.
        const several = editor ? mine.length > 0 : n > 0 || others.length > 0;
        if (!several) return;
        const buttons = [];
        if (!editor) {
            const make = document.createElement('button');
            make.type = 'button';
            make.className = 'button subtle small';
            make.append(icon('star'), t('Gültig machen'));
            make.title = openE ? t('Die Bearbeitung von %s wird deine und gilt dann überall: Kacheln, Tafeln, Fundbericht', personName(openE.name))
                : versionOpen === -1 ? t('Das Original gilt dann überall: Kacheln, Tafeln, Fundbericht')
                : t('Bearbeitung %d gilt dann überall: Kacheln, Tafeln, Fundbericht', versionOpen + 1);
            make.addEventListener('click', openE ? foreignAdopt : () => versionValidSet());
            buttons.push([make, openE === null && versionOpen === valid]);
        }
        const bin = document.createElement('button');
        bin.type = 'button';
        bin.className = 'button subtle small danger square';
        bin.append(icon('delete'));
        bin.title = openE ? t('Deine Bearbeitung %d löschen', foreignNumber(openE)) : t('Bearbeitung %d löschen', versionOpen + 1);
        bin.setAttribute('aria-label', bin.title);
        bin.addEventListener('click', openE ? foreignDelete : versionDelete);
        // Only one's own goes in the bin — never the valid one, never somebody else's, never the Original.
        buttons.push([bin, openE ? openE.user_id !== host.start.user.id : editor || versionOpen === valid || versionOpen === -1]);
        for (const [b, reserved] of buttons) {
            b.classList.toggle('is-reserved', reserved);
            b.disabled = reserved;
            if (reserved) b.setAttribute('aria-hidden', 'true');
            versionsEl.append(b);
        }
    }

    function developLoaded() {
        developSubjectSync();
        // The switch follows the picture even where the mode stays as it was.
        developSwitcherSync();
        // The mode kept on the device — the window may have opened after the picture was chosen.
        const want = settings.get('photoMode', 'measure') === 'develop' && desk.matches && developHere();
        if (want !== developMode) developModeSet(want ? 'develop' : 'measure');
        else {
            // Closing took the grey off the page while the mode stayed: reopened, it comes back.
            document.documentElement.classList.toggle('is-editing-picture', developMode && photoWindow.open);
            developPanelDraw();
            viewUpdate();
        }
    }

    function developRedraw() {
        // What the red veil shows now — also where nothing is drawn, so developHandles never asks again in a loop.
        maskShown = maskOverlay();
        const empty = developEmpty(develop) && !cropEditing;
        const ready = photoWindow.open && photoImageEl.complete && photoImageEl.naturalWidth > 0;
        if (!ready || empty || developBefore) {
            developCanvas.hidden = true;
            developHandles();
            histogramSoon();
            return;
        }
        developRenderer_ ??= developRenderer(developCanvas);
        const r = developRenderer_;
        if (!r) {
            developCanvas.hidden = true;
            // Without WebGL nothing draws the view: the original as it is, unturned.
            if (pictureView) {
                pictureView = null;
                syncOverlay();
                drawOverlay();
            }
            return;
        }
        const src = photoImageEl.currentSrc || photoImageEl.src;
        if (developSrc !== src) {
            if (!r.setImage(photoImageEl)) return;
            developSrc = src;
        }
        r.render(developShown(), wholeNow(), maskShown);
        developCanvas.hidden = false;
        developHandles();
        histogramSoon();
    }
    function developSoon() {
        if (developQueued) return;
        developQueued = true;
        requestAnimationFrame(() => { developQueued = false; developRedraw(); });
    }

    /** Store a moment after the last change — whose settings they are is captured now. */
    function developQueue() {
        developTabsMark();
        const subject = developOf;
        exportSync();
        if (subject === null || !developMay()) return;
        if (foreignOpen !== null) {
            foreignQueue();
            return;
        }
        // Changed on the Original: from here on a new edit (versionFork). Nothing changed yet — only the bar's state goes out.
        if (versionOpen === -1 && !developEmpty(develop) && !versionFork(subject)) return;
        // Into the open edit's slot — the valid one's is subject.develop (versionPut).
        versionPut(subject, versionOpen, developClean(develop));
        // The only edit pushed back to nothing is the Original again — the bar shows „0" open.
        if (versionOpen >= 0 && versionCount(subject) === 0) {
            versionOpen = -1;
            versionsDraw();
        }
        const to = { subject, cloud: cloudInWindow !== null, pointId: host.pointId() };
        const d = subject.develop;
        // The other edits ride along — only where the picture came with them, so a page that never heard of them cannot wipe them.
        const versions = 'develop_versions' in subject ? versionsWire(subject) : undefined;
        clearTimeout(developSave?.timer);
        const run = () => {
            developSave = null;
            return developSend(to, d, versions);
        };
        developSave = { run, timer: setTimeout(run, 700) };
    }

    /**
     * Store settings for a picture — `to` names it ({subject, cloud,
     * pointId}), so a place the AI finished after the window moved on is
     * stored to ITS picture (aiAway).
     */
    async function developSend({ subject, cloud, pointId }, d, versions = undefined) {
        const key = (cloud ? 'cloud:' : 'photo:') + subject.id;
        // Every thumbnail of this picture on the page follows — the strip, the tiles, the gallery. The VALID edit: `d`.
        developThumbsRefresh(key, d);
        host.developed?.(key, d);
        const body = { cloud, pointId, id: subject.id, develop: d };
        if (versions !== undefined) body.versions = versions;
        await editSave({ key, kind: 'own', gen: ++editGen, body }, true);
    }

    /* --- WHAT THE CLOUD REFUSED IS KEPT AND TRIED AGAIN
       (14.11.2026, editsaves.js says why and how) ----------------------------
       Every save of an edit — the photographer's own (developSend) or a
       friend's (foreignSend) — is an ENTRY: which edit, the whole of it as it
       stands, and a number that grows with every save. editSave sends it;
       what the cloud refuses for a passing reason is handed to editsaves.js,
       which keeps it on the device and tries again until it goes. A refusal
       that will not pass by trying (a right that is missing, a picture that
       is gone) is told once, as before, and not kept.

       ONE AFTER THE OTHER PER EDIT (editChain): an old entry tried again
       must never land after a newer save of the same edit and turn the
       cloud's copy back. */
    /* THE NUMBER STARTS AT THE CLOCK, not at nought: an entry kept from the
       last visit carries that visit's number, and a save of this visit must
       count as newer — begun at 1 again, the first save after a reload
       passed for the kept one refused once more, and the kept, older state
       stood in for the newer (found in the test, 14.11.2026). */
    let editGen = Date.now();
    const editChains = new Map();
    function editChain(key, work) {
        const next = (editChains.get(key) ?? Promise.resolve()).then(work, work);
        editChains.set(key, next.catch(() => {}));
        return next;
    }
    /** The patch ids an entry names — the valid edit's and the others' beside it. */
    function editEntryEdits(entry) {
        return [entry.body.develop, ...(entry.body.versions?.list ?? [])].filter(Boolean);
    }
    /** May a refusal pass by trying again? No answer, a session run out, a server or a cloud that stumbled. */
    const editRetryable = (error) => !!error?.isNetworkError || [401, 408, 429].includes(error?.status) || (error?.status ?? 0) >= 500;

    /**
     * Send one entry. `first`: a save just made (a refusal is told);
     * otherwise a retry (only its success is told). Resolves to true when
     * the entry reached the cloud, or will never be sent again.
     */
    function editSave(entry, first) {
        return editChain(entry.key, async () => {
            // A newer save of the same edit went or waits meanwhile: this one has nothing left to say.
            const waiting = editSaveWaiting(entry.key);
            if (!first && waiting !== entry) return true;
            const edits = editEntryEdits(entry);
            /* THE PATCHES RIDE ALONG (step 3, 11.11.2026): every one these
               settings name that the cloud does not hold yet. The server
               writes them before the settings, so a stored develop never
               names a patch that is not there. At most 24 a save — what is
               left goes in the next round, at once. */
            for (;;) {
                const unstored = [...new Set(edits.flatMap((x) => aiPatchesUnstored(x)))];
                const sending = unstored.slice(0, 24);
                let url, body;
                if (entry.kind === 'foreign') {
                    url = 'picture_edits.php';
                    body = { ...entry.body };
                } else {
                    const b = entry.body;
                    url = b.cloud ? `cloud.php?id=${b.pointId}&action=scale` : 'photos.php';
                    body = b.cloud ? { file: b.id, develop: b.develop } : { id: b.id, develop: b.develop, _methode: 'PATCH' };
                    if (b.versions !== undefined) body.develop_versions = b.versions;
                }
                if (sending.length) {
                    body.patches = {};
                    for (const id of sending) {
                        const png = await aiPatchPng(id);
                        if (png) body.patches[id] = png;
                    }
                }
                try {
                    await request(url, { method: 'POST', body: JSON.stringify(body) });
                    for (const id of Object.keys(body.patches ?? {})) aiPatchStored(id);
                } catch (error) {
                    if (!editRetryable(error)) {
                        /* The server's own word where it gave one on purpose
                           (422: too many places before migration 0193; 502:
                           its sentence about the cloud). Not kept: trying
                           again would only be refused again. */
                        if (first || waiting) host.status((error?.status === 422 || error?.status === 502) && error.message ? error.message : t('Die Bearbeitung ließ sich nicht speichern.'), true);
                        editSaveDone(entry.key, entry.gen);
                        return true;
                    }
                    const pngs = {};
                    for (const id of unstored) {
                        const png = body.patches?.[id] ?? await aiPatchPng(id);
                        if (png) pngs[id] = png;
                    }
                    // Said once — not again with every slider moved while the cloud is away.
                    if (first && !editSaveWaiting(entry.key)) host.status(t('Die Bearbeitung ließ sich nicht in der Cloud speichern. Sie bleibt auf diesem Gerät, und es wird von selbst wieder versucht.'), true);
                    editSaveFailed({ ...entry, ids: unstored }, pngs);
                    return false;
                }
                if (unstored.length <= sending.length) break;
            }
            const was = editSaveWaiting(entry.key);
            editSaveDone(entry.key, entry.gen);
            // A retry that went, or a new save that took a kept one along: said once, as a deed.
            if (was && was.gen <= entry.gen) host.status(t('Die Bearbeitung ist jetzt in der Cloud gespeichert.'));
            return true;
        });
    }

    /* THE MARKS (point 2): a place of „Entfernen" whose patch waits for
       the cloud, and the edit as a whole in the bar „Bearbeitung" — a press
       on either tries at once. Drawn anew whenever what waits changes. */
    function editKeyOpen() {
        if (!developOf) return null;
        return foreignOpen !== null ? 'edit:' + foreignOpen : developKey(developOf);
    }
    /** Does this place of „Entfernen" wait for the cloud? */
    function spotUnsaved(s) {
        if (s?.mode !== 'ai') return false;
        const waiting = editSavesIds();
        if (!waiting.size) return false;
        return [...(s.pieces ?? []), ...(s.other ?? [])].some((piece) => waiting.has(piece.id) && !aiPatchIsStored(piece.id));
    }
    /** The cloud with the exclamation mark, as a button that tries again at once. */
    function editUnsavedMark(extra = '') {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'button subtle small square photo-unsaved' + (extra ? ' ' + extra : '');
        b.title = editSavesHint();
        b.setAttribute('aria-label', b.title);
        b.innerHTML = iconHtml('cloud-unsaved');
        b.addEventListener('click', (e) => {
            e.stopPropagation();
            editSavesRetryNow();
        });
        return b;
    }

    function developFlush() {
        if (!developSave) return;
        clearTimeout(developSave.timer);
        developSave.run();
    }
    /* „BILD EXPORTIEREN" (stage 3, 07.11.2026; renamed and reshaped
       08.11.2026): the picture as one sees it in „Bearbeiten", as a file of
       its own, from the ORIGINAL at full size.

       THE NAME: it was „Bild erzeugen", and the maintainer found it misleading —
       „man hat ja ein Bild. Warum soll man dann noch ein weiteres Bild
       erzeugen? Wie wäre es mit exportieren?".

       IT IS A DOWNLOAD, NOTHING ELSE (the same day: „gar nicht in
       die Cloud schreiben, weil das ist ja irgendwie sinnlos, weil man dann
       erst seine Cloud öffnen muss, um es dann runterzuladen"). Until then
       the file went into the find's „Dateien" › „Bearbeitet" in the cloud;
       whoever wanted it on the computer had to fetch it back from there.
       The settings themselves stay stored with the picture as before — the
       export is the copy one takes away.

       FORMAT AND QUALITY are asked in a small window before (the export
       window in photo_window.php) and kept on the device: JPEG, which every
       program opens, or WebP, a smaller file at the same look („weil es
       platzsparend ist und wirklich kaum Verluste zeigt"), where the browser
       can write it — Safari long could not, and a canvas asked for WebP then
       hands back a PNG; the choice is offered only where a test canvas
       really comes back as WebP. Camera, moment of the shot and a note
       what was done go into either file (exifstamp.js, stampJpeg and
       stampWebp).

       Only where one may develop the picture at all, as before. */
    const exportButton = document.getElementById('photo-develop-export');
    let exportBusy = false;

    /* EVERYONE WHO MAY SEE THE PICTURE MAY EXPORT IT (08.11.2026:
       „jeder, der das Foto sehen darf, darf exportieren") — a friend's
       picture too, with the friend's name in the file. Until then only
       one's own, because the file went into one's own cloud. */
    const exportMay = () => measureSubject() !== null;

    function exportSync() {
        if (!exportButton) return;
        // In „Bearbeiten" only — while measuring the footer keeps its own buttons.
        exportButton.hidden = !developMode || !exportMay();
        // Unchanged too: smaller, with a watermark and the author in it, a plain copy is worth a download.
        exportButton.disabled = exportBusy;
        exportButton.title = t('Lädt das Bild so herunter, wie du es hier siehst. Das Original bleibt, wie es ist.');
    }

    function exportSay(text) {
        exportButton.querySelector('span').textContent = text;
    }

    /** The picture's own name, cleaned as the server cleans a file name — {Motiv}. */
    function exportMotif(subject) {
        const own = cloudInWindow !== null ? String(subject.name ?? '') : String(subject.title ?? '');
        return own.replace(/\.[a-z0-9]{2,5}$/i, '').replace(/[\/\\:*?"<>|\x00-\x1f]+/g, ' ')
            .replace(/\s+/g, ' ').trim().slice(0, 180) || `Bild ${subject.id}`;
    }

    /** What the placeholders stand for in this export (exportmark.js, fillPlaceholders).
     *  FOR THE FILE NAME, day and place as YASAIMAPP writes them in every
     *  picture's name in the cloud — 2026-08-14 and „DE,THU, Arenshausen"
     *  (finddetails.js, nameDay/namePlace; 08.11.2026). On the
     *  picture itself, in a watermark, they stay as one reads them. */
    function exportValues(subject, style, forFile = false) {
        const point = host.point(host.pointId());
        const details = findDetails(point, true);
        if (forFile) {
            return { ...exportValues(subject, style), Datum: nameDay(point), Ort: namePlace(point) };
        }
        const user = host.start.user ?? {};
        return {
            // The picture's own author, never the exporter's (authorOf above).
            Urheber: authorOf(subject),
            Jahr: String(new Date().getFullYear()),
            Art: details.species ?? '',
            Datum: details.date ?? '',
            Fundnummer: details.number ?? '',
            Motiv: exportMotif(subject),
            Ort: details.place ?? '',
        };
    }

    /** The account's export style (includes/export_style.php) — author, file name, templates. */
    const exportStyle = () => measureStyle.export ?? { artist: '', copyright: '', owner_in_file: false, file_name: '', marks: [] };

    /** The original's size as the export will see it: turned and cut as „Bearbeiten" says. */
    function exportSourceSize(subject) {
        const W = Number(subject.width) || photoImageEl.naturalWidth, H = Number(subject.height) || photoImageEl.naturalHeight;
        if (!W || !H) return null;
        const view = geoSet(develop) ? geoView(develop, W, H) : null;
        return view ? { w: Math.round(view.cw), h: Math.round(view.ch) } : { w: W, h: H };
    }

    /** The window's questions (exportwindow.js), then developExport with the answers. */
    function exportOpen() {
        if (exportBusy || !exportMay()) return;
        const subject = measureSubject();
        openExport({
            style: exportStyle,
            // A pattern changed in the window is the account's from now on.
            saveFileName: (pattern) => {
                const style = exportStyle();
                if (!measureStyle.export) return;
                request('measure_style.php', { method: 'POST', body: JSON.stringify({ export: { ...style, file_name: pattern } }) })
                    .then((answer) => { if (answer.measure_style?.export) measureStyle.export = answer.measure_style.export; })
                    .catch(() => host.status(t('Der Dateiname ließ sich nicht merken.'), true));
                measureStyle.export = { ...style, file_name: pattern };
            },
            source: () => exportSourceSize(subject),
            values: (forFile) => exportValues(subject, exportStyle(), forFile),
            canPlace: true,
            run: developExport,
        });
    }

    async function developExport({ format, quality, size, sharpen, markId, pattern, carry = { place: false, camera: true, taken: true } }) {
        const subject = measureSubject();
        if (exportBusy || subject === null || !exportMay()) return;
        developFlush();
        const settingsNow = developClean(develop);
        const cloud = cloudInWindow !== null;
        const webp = format === 'webp' && webpCan();
        const style = exportStyle();
        const values = exportValues(subject, style);
        const mark = (style.marks ?? []).find((m) => m.id === markId) ?? null;
        exportBusy = true;
        exportSync();
        exportSay(t('Wird berechnet …'));
        try {
            // THE ORIGINAL, whatever the window shows — a preview would make a small file.
            // A recording through the desk's cache (originalcache.js), a voucher photo from our server.
            let original;
            if (cloud) {
                original = await originalBlob(subject, `${host.start.baseUrl}pages/cloud_thumb.php?id=${subject.id}&original=1`)
                    .catch(() => { throw new Error(t('Das Original kam nicht an.')); });
            } else {
                original = await originalBlob(subject, `${host.start.baseUrl}pages/photo.php?id=${subject.id}`, 'photo')
                    .catch(() => { throw new Error(t('Das Original kam nicht an.')); });
            }
            /* WHAT THE FILE CARRIES, as the window's ticks say: camera and
               exposure, the moment of the shot, and the find's place. */
            const { make, model, shot, taken } = await readCamera(original);
            const camera = {
                ...(carry.camera ? { make, model, shot } : {}),
                ...(carry.taken ? { taken } : {}),
            };
            const point = carry.place ? host.point(host.pointId()) : null;
            const gps = point && point.lat != null && point.lon != null
                ? { lat: Number(point.lat), lon: Number(point.lon), alt: point.elevation_m ?? null } : null;
            const logo = mark?.logo?.on ? await loadLogo(host.start.baseUrl, mark.logo.file) : null;
            const img = new Image();
            const at = URL.createObjectURL(original);
            // The load event and not decode(), which a page in the background may leave hanging.
            const opened = await new Promise((resolve) => {
                img.addEventListener('load', () => resolve(true), { once: true });
                img.addEventListener('error', () => resolve(false), { once: true });
                img.src = at;
            });
            setTimeout(() => URL.revokeObjectURL(at), 0);
            if (!opened) throw new Error(t('Das Original ließ sich nicht öffnen.'));
            const type = webp ? 'image/webp' : 'image/jpeg';
            /* THE LAST STEPS on the rendered canvas: smaller (in halving
               steps), sharpened where it was made smaller, the watermark over
               it — at the size the file will have, so its share of the long
               edge is what the template says. */
            const finish = (canvas) => {
                const { w, h, k } = exportSize(canvas.width, canvas.height, size);
                let out = canvas;
                if (k < 1) out = sharpenCanvas(scaleCanvas(canvas, w, h), sharpen);
                if (mark) {
                    if (out === canvas) out = flatCanvas(canvas);
                    drawMark(out.getContext('2d'), out.width, out.height, mark, values, logo);
                }
                return out;
            };
            const made = await developFile(img, settingsNow ?? {}, { quality: quality / 100, type, finish, of: developKey(subject) });
            const name = exportName(pattern, exportValues(subject, style, true), webp ? 'webp' : 'jpg');
            const stamp = webp ? stampWebp : stampJpeg;
            const bytes = stamp(new Uint8Array(await made.blob.arrayBuffer()), {
                from: { w: img.naturalWidth, h: img.naturalHeight },
                to: { w: made.width, h: made.height },
                originalName: cloud ? String(subject.name ?? '') : String(subject.title ?? name),
                when: new Date(),
                developed: true,
                ...camera,
                gps,
                // Without the place, no name that could carry it (exifstamp.js, noteText).
                hideName: !carry.place,
                /* THE AUTHOR, ALWAYS, AND ONLY THE NAME (08.11.2026:
                   „ein Export geht nicht ohne den Urheber", „das sollte nur der
                   Name sein … einfach nur der Urhebername. Was in die Datei
                   kommt"): Artist and Copyright both carry the author's name;
                   whether it also lies on the picture is the template's. */
                ...(values.Urheber ? { artist: values.Urheber, copyright: values.Urheber } : {}),
            });
            exportDownload(new File([bytes], name, { type }));
            host.status(t('„%s" wird heruntergeladen.', name));
        } catch (e) {
            console.warn('Bild exportieren:', e);
            host.status(t('Das Bild ließ sich nicht exportieren: %s', e.message || ''), true);
        } finally {
            exportBusy = false;
            exportSay(t('Bild exportieren'));
            exportSync();
        }
    }
    exportButton?.addEventListener('click', exportOpen);

    photoWindow.addEventListener('close', () => {
        document.documentElement.classList.remove('is-editing-picture');
        castPickEnd();
        developFlush();
        aiJobsLeave();
        expandLeave();
        layersTool?.setActive(false);
        developOf = null;
        developCanvas.hidden = true;
        developSrc = null;
    });

    /** One slider: its name and value above a long track; a double click on the name sets it back to 0. */
    function developSlider(def, value, change, may) {
        const row = document.createElement('label');
        row.className = 'photo-develop-row';
        const head = document.createElement('span');
        head.className = 'photo-develop-row-head';
        const name = document.createElement('span');
        name.textContent = def.name();
        const out = document.createElement('span');
        out.className = 'photo-develop-value';
        const input = document.createElement('input');
        input.type = 'range';
        input.min = String(def.min ?? -100);
        input.max = String(def.max ?? 100);
        input.step = String(def.step ?? 1);
        input.value = String(def.step ? Number(value ?? 0) : Math.round(value ?? 0));
        // Which slider it is — „Ausbessern" lets the size slider's frame stand a moment longer (spotSlideStart).
        input.dataset.key = def.key;
        input.disabled = !may;
        // The line painted with what the slider does (develop.js, DEVELOP_TRACKS).
        const track = def.track ?? DEVELOP_TRACKS[def.key];
        if (track) {
            input.style.setProperty('--track', track);
            row.classList.add('has-track');
        }
        const say = () => {
            const v = Number(input.value);
            const shown = def.unit ? v.toFixed(1).replace('.', ',') + def.unit : String(v) + (def.suffix ?? '');
            out.textContent = v > 0 && (def.min ?? -100) < 0 ? '+' + shown : shown.replace('-', '−');
            row.classList.toggle('is-set', v !== 0);
        };
        say();
        input.addEventListener('input', () => { say(); change(Number(input.value)); });
        /* A DOUBLE CLICK ON THE NAME OR ON THE TRACK ITSELF sets it back to 0
           — the middle (07.11.2026: „ein Doppelklick auf einen
           Schieberegler setzt ihn wieder zurück in die Mitte"). The first
           click of the two has moved the knob already; the second puts it
           home, so nothing of the stray step stays. */
        const reset = (e) => {
            e.preventDefault();
            if (!may) return;
            input.value = String(def.home ?? 0);
            say();
            change(def.home ?? 0);
        };
        const resetWords = def.home ? t('Doppelklick setzt zurück') : t('Doppelklick setzt auf 0 zurück');
        head.title = resetWords;
        input.title = resetWords;
        head.addEventListener('dblclick', reset);
        input.addEventListener('dblclick', reset);
        /* WHILE „ORIGINAL" IS HELD THE KNOB STANDS AT HOME
           (14.11.2026: „wenn ich mir das Original anschaue … kannst du dann
           einfach die Regler zurücksetzen"): the picture shows nothing of
           the edit, so the slider says the same — and gives its value back
           on letting go. Only in place, the panel is not drawn anew, and
           locked meanwhile: a slider moved now would change what cannot be
           seen. Called by developBeforeSet for the rows that carry
           data-before (developGroupsDraw: the picture's and a mask's
           effects); a brush's size or a cast's strength is no edit to undo. */
        let kept = null;
        row.homeShow = (on) => {
            if (on && kept === null) {
                kept = input.value;
                input.value = String(def.home ?? 0);
            } else if (!on && kept !== null) {
                input.value = kept;
                kept = null;
            } else return;
            say();
            input.disabled = on || !may;
        };
        head.append(name, out);
        row.append(head, input);
        return row;
    }

    /* „MIKROFOTOS" — A GROUP OF ITS OWN, NOT IN „FARBE"
       (09.11.2026: „was ich plane, ist einfach nur ein Weißabgleich. Aber ich
       würde das nicht bei Farbe mit reinbringen … dass ich irgendwo drüber
       einen Rahmen ziehe … Oder wollen wir es mal versuchen, über das ganze
       Bild? Bei den Mikrofotos klappt das ganz gut"; his „Ja, bau das so"
       to: both). „Weißabgleich" measures the brightest parts of the whole
       picture — in a micrograph the empty bright field, which ought to be
       neutral; a plain mean would count a Congo red or Melzer stain in and
       tint the picture against it. „Bereich wählen" lets a frame be drawn
       over a neutral spot instead (wbPick). SINCE 12.11.2026 THE RESULT
       STANDS IN „FARBE" (his „bei den Schiebereglern darunter anzeigen.
       Sodass man ihn auch wieder rückgängig machen kann"): the measurement
       sets Temperatur and Tönung (develop.js, wbToSliders), which are the
       white balance's gains now, first in the chain — so it can be seen,
       corrected by hand and taken back by a double click, and this group
       needs no bin and no dot of its own. Folds like the other groups (key
       'micro'). */
    function microGroup(may, folded, folding = true) {
        const group = document.createElement('div');
        group.className = 'photo-develop-group';
        group.dataset.group = 'micro';
        group.classList.toggle('is-collapsed', folding && folded.has('micro'));
        const head = document.createElement(folding ? 'button' : 'h3');
        if (folding) head.type = 'button';
        head.className = 'list-label photo-develop-group-head' + (folding ? '' : ' is-plain');
        if (folding) head.setAttribute('aria-expanded', String(!folded.has('micro')));
        head.innerHTML = folding ? iconHtml('fold-triangle') : headSign('micro');
        head.append(t('Mikrofotos'));
        if (folding) head.addEventListener('click', () => {
            const kept = settings.get('developFolded', []);
            const now = new Set(Array.isArray(kept) ? kept : []);
            if (now.has('micro')) now.delete('micro'); else now.add('micro');
            settings.set('developFolded', [...now]);
            group.classList.toggle('is-collapsed', now.has('micro'));
            head.setAttribute('aria-expanded', String(!now.has('micro')));
        });
        const row = document.createElement('div');
        row.className = 'button-row';
        const button = (label, title, act, active = false) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small' + (active ? ' is-active' : '');
            b.textContent = label;
            b.title = title;
            b.disabled = !may;
            b.addEventListener('click', act);
            row.append(b);
            return b;
        };
        button(t('Weißabgleich'), t('Aus den hellsten Stellen des Bildes, meist dem leeren Hellfeld'), () => {
            wbPickEnd();
            wbApply(wbFromPixels(wbPixels()?.data ?? [], true));
        }, false);
        button(t('Bereich wählen'), t('Einen Rahmen über eine neutrale Stelle ziehen, etwa leeren Objektträger'), () => {
            wbPicking = !wbPicking;
            photoFrame.classList.toggle('is-wb-picking', wbPicking);
            developPanelDraw();
        }, wbPicking);
        group.append(head, row);
        return group;
    }

    /** The original's pixels, at most 800 on the long side — what a white balance is measured on. */
    function wbPixels() {
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        if (!W || !H) return null;
        const k = Math.min(1, 800 / Math.max(W, H));
        const c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(W * k));
        c.height = Math.max(1, Math.round(H * k));
        const g = c.getContext('2d', { willReadFrequently: true });
        g.drawImage(photoImageEl, 0, 0, c.width, c.height);
        try {
            return { k, w: c.width, h: c.height, data: g.getImageData(0, 0, c.width, c.height).data };
        } catch {
            return null;
        }
    }
    function wbApply(wb) {
        if (!wb) {
            host.status(t('Daraus ließ sich kein Weißabgleich bestimmen.'), true);
            return;
        }
        // Measured on the original, so it REPLACES what Temperatur and Tönung held.
        delete develop.wb;
        const { temp, tint, clipped } = wbToSliders(wb);
        if (temp) develop.temp = temp; else delete develop.temp;
        if (tint) develop.tint = tint; else delete develop.tint;
        if (clipped) host.status(t('Der Farbstich ist stärker, als die Regler reichen. Sie stehen am Ende.'), true);
        /* The sliders it set are shown: „Farbe" opens if it was shut — the
           result is what one wants to see, and to take back. */
        const kept = settings.get('developFolded', []);
        if (Array.isArray(kept) && kept.includes('color')) settings.set('developFolded', kept.filter((k) => k !== 'color'));
        developSoon();
        developQueue();
        developPanelDraw();
    }

    /* „BEREICH WÄHLEN": a frame drawn on the picture (in the window's own
       pixels, .photo-wb-rect); on letting go its inside is sampled through
       the view's turn and cut back to the original (clientNatural) and
       made neutral as a whole — no brightest few here, the frame is the
       choice. Escape lets the tool go. */
    let wbPicking = false;
    let wbDrag = null;
    const wbRect = document.createElement('div');
    wbRect.className = 'photo-wb-rect';
    wbRect.hidden = true;
    photoFrame.append(wbRect);
    function wbPickEnd() {
        wbPicking = false;
        wbDrag = null;
        wbRect.hidden = true;
        photoFrame.classList.remove('is-wb-picking');
    }
    function wbRectShow(x0, y0, x1, y1) {
        const f = photoFrame.getBoundingClientRect();
        Object.assign(wbRect.style, {
            left: Math.min(x0, x1) - f.left + 'px', top: Math.min(y0, y1) - f.top + 'px',
            width: Math.abs(x1 - x0) + 'px', height: Math.abs(y1 - y0) + 'px',
        });
        wbRect.hidden = false;
    }
    photoFrame.addEventListener('pointerdown', (e) => {
        if (!wbPicking || !developMode || e.button !== 0 || e.target.closest?.('button')) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        wbDrag = { id: e.pointerId, x: e.clientX, y: e.clientY };
        try { photoFrame.setPointerCapture(e.pointerId); } catch { /* without, then */ }
        wbRectShow(e.clientX, e.clientY, e.clientX, e.clientY);
    }, true);
    photoFrame.addEventListener('pointermove', (e) => {
        if (!wbDrag || wbDrag.id !== e.pointerId) return;
        e.stopImmediatePropagation();
        wbRectShow(wbDrag.x, wbDrag.y, e.clientX, e.clientY);
    }, true);
    const wbDragEnd = (e) => {
        if (!wbDrag || wbDrag.id !== e.pointerId) return;
        e.stopImmediatePropagation();
        const { x, y } = wbDrag;
        wbPickEnd();
        swallowClick();
        const x0 = Math.min(x, e.clientX), x1 = Math.max(x, e.clientX), y0 = Math.min(y, e.clientY), y1 = Math.max(y, e.clientY);
        const p = wbPixels();
        if (!p || x1 - x0 < 4 || y1 - y0 < 4) { developPanelDraw(); return; }
        // A grid of up to 80 × 80 samples over the frame, each read in the original.
        const nx = Math.min(80, Math.round(x1 - x0)), ny = Math.min(80, Math.round(y1 - y0));
        const out = [];
        for (let j = 0; j < ny; j++) {
            for (let i = 0; i < nx; i++) {
                const at = clientNatural(x0 + (i + .5) / nx * (x1 - x0), y0 + (j + .5) / ny * (y1 - y0));
                if (!at?.inside) continue;
                const px = Math.floor(at.p[0] * p.k), py = Math.floor(at.p[1] * p.k);
                if (px < 0 || py < 0 || px >= p.w || py >= p.h) continue;
                const k = (py * p.w + px) * 4;
                out.push(p.data[k], p.data[k + 1], p.data[k + 2], p.data[k + 3]);
            }
        }
        wbApply(wbFromPixels(out, false));
    };
    photoFrame.addEventListener('pointerup', wbDragEnd, true);
    photoFrame.addEventListener('pointercancel', wbDragEnd, true);

    function developPanelDraw() {
        // The crop that R opened has ended: back to the tab it came from, which draws the panel itself (cropKey).
        if (cropReturn()) return;
        // An old white balance of its own becomes Temperatur and Tönung, where it can be seen (develop.js, developWBFold).
        developWBFold(develop);
        // Asked before anything else: leaving „Bearbeiten" closes the tab of „Ebenen" too.
        layersSync();
        // The others' edits of the picture, once per picture (the guard is foreignFetch's own).
        foreignFetch(developOf);
        versionsDraw();
        if (!developAside || !developMode) return;
        developOriginalFetch();
        const may = developMay();
        /* WHO MAY NOT EDIT SEES ONLY THE INFO (12.11.2026:
           „eigentlich ist es Quatsch, dass wir überhaupt die Regler anzeigen
           und die ganzen Tools. Man könnte auch nur die Info anzeigen"):
           sliders, tools, masks and retouching that cannot be moved are a
           promise the window cannot keep — the frame rule of the point
           window, here for a whole sidebar. The tab bar goes with them (one
           tab alone would name its content twice), and so does
           „Zurücksetzen"; „Original" stays, comparing is looking. The tab
           one had chosen is not forgotten: the device keeps it (settings
           developTab), and the next picture one may edit opens on it. */
        if (!may && developTab !== 'info') {
            developTab = 'info';
            maskChosen = null;
            spotChosen = null;
            spotHover = null;
        } else if (may && developTab === 'info' && developTabForced) {
            developTab = settings.get('developTab', 'adjust');
        }
        developTabForced = !may;
        if (developTabs) developTabs.hidden = !may;
        /* WHY ONE ONLY LOOKS, said only where there is something to do —
           somebody else's edit one may make valid, or the photographer's
           for a friend who may add his own. The plain „not yours" is said
           once already, in the bar above the picture. */
        const readonly = document.getElementById('photo-develop-readonly');
        const openE = foreignEdit();
        readonly.hidden = may || !(openE || foreign?.role === 'editor');
        readonly.textContent = foreign?.role === 'editor' ? t('Nur zum Anschauen. Mit + legst du eine eigene Bearbeitung an.')
            : openE ? t('Die Bearbeitung von %s ist nur zum Anschauen. „Gültig machen" übernimmt sie als deine.', personName(openE.name)) : '';
        exportSync();
        for (const b of developTabs?.querySelectorAll('[data-tab]') ?? []) {
            const on = b.dataset.tab === developTab;
            b.setAttribute('aria-selected', String(on));
            b.classList.toggle('is-active', on);
        }
        developTabsMark();
        developSliders.textContent = '';
        developSliders.hidden = developTab === 'masks';
        if (masksSection) masksSection.hidden = developTab !== 'masks';
        if (developTab === 'tools') developSliders.append(geoPanel(may));
        if (developTab === 'spots') developSliders.append(spotsPanel(may));
        if (developTab === 'info') developSliders.append(infoPanel());
        if (developTab === 'layers' && layersTool) developSliders.append(layersTool.panel);
        photoFrame.classList.toggle('is-spotting', (spotsOn() || brushOn()) && may);
        if (developTab === 'adjust') {
            /* FARBE, LICHT, DETAILS FOLD (08.11.2026: „würde Farbe,
               Licht und Details einklappbar machen. Und das soll auch
               persistiert werden"): the head is a button with the folding
               triangle; which are shut is kept on the device (settings
               developFolded), as the tab is. A shut group whose sliders are
               not all at 0 shows a dot in the accent — closed and something
               set inside means lit, the sidebar's filter rule. */
            developRailDraw(may);
        }
        if (developTab === 'masks') masksPanelDraw(may);
    }

    /* A DOT WHERE SOMETHING IS SET (11.11.2026: „dass man auf dem
       Laufenden ist und quasi sieht, wo etwas drin ist … einen Punkt in der
       Akzentfarbe rechts oben … Ebenso ganz oben die Tabs"). Each tab of
       „Bearbeiten" that holds something for this picture wears the accent's
       dot in its upper right corner — the same dot a shut group of
       „Einstellungen" shows, and the same buttons inside (the kinds of
       „Ausbessern", the masks' „+", the tools of „Werkzeuge"). Info sets
       nothing and never has one; „Ebenen" has one when a begun set holds the
       picture. Read, never written: develop.spots is not created here, as
       spotsList() would. Run on every change (developQueue) and every
       drawing of the panel. */
    function developTabsMark() {
        if (!developTabs) return;
        const set = {
            adjust: !!develop.wb || DEVELOP_GROUPS.some((g) => g.sliders.some((s) => (Number(develop[s.key]) || 0) !== 0)),
            tools: geoSet(develop),
            masks: masksCount() > 0,
            spots: (develop.spots?.length ?? 0) > 0,
            layers: !!layersTool?.begun(() => developTabsMark()),
        };
        for (const b of developTabs.querySelectorAll('[data-tab]')) b.classList.toggle('is-set', !!set[b.dataset.tab]);
    }

    /* THE TABS (07.11.2026): Einstellungen · Werkzeuge · Masken
       (· Ausbessern · Info since 10.11.2026).
       Leaving „Werkzeuge" while cropping keeps the crop, as „Fertig" does —
       a tab is a place to look, not a way to undo (that is Escape). Leaving
       „Masken" takes the masks' lines off the picture. */
    /** A geometry tool taken up or laid down — its button, and R for „Zuschneiden, Drehen" (photoKeys). */
    function geoToolToggle(name) {
        if (cropEditing && geoTool === name) {
            cropEditing = false;
            cropBefore = null;
        } else {
            if (!cropEditing) cropBefore = structuredClone(develop.geo ?? null);
            // Entering „Zuschneiden": the shape it starts in (cropRatioStart).
            if (name === 'crop' && !(cropEditing && geoTool === 'crop')) cropRatio = cropRatioStart();
            cropEditing = true;
            geoTool = name;
            // „Erweitern" starts as the picture stands: on where its crop reaches past it already.
            cropExpand = name === 'crop' && cropOutside(develop.geo, ...naturalSize());
        }
        cropRoomFit();
        photoZoom.reset();
        developPanelDraw();
        viewUpdate();
    }

    /* R — „ZUSCHNEIDEN, DREHEN" FROM ANY TAB (14.11.2026, as
       Lightroom has it): the tool in hand at once; pressed again, laid down.
       A JUMP THERE AND BACK, NOT A CHANGE OF TAB (the same day:
       „soll sich die rechte Toolleiste, die Reiter, nicht ändern … ein
       temporärer Sprung hin und wieder zurück"): the crop lives in
       „Werkzeuge", so R goes there, and the tab it came from is noted;
       however the crop ends — R, „Fertig", „Abbrechen", Escape, another
       picture — developPanelDraw goes back to it (cropReturn). Choosing a
       tab by hand meanwhile forgets the way back (developTabSet). */
    let cropReturnTab = null;
    function cropKey() {
        if (!developMay()) return;
        if (developTab !== 'tools') {
            const from = developTab;
            developTabSet('tools');
            cropReturnTab = from;
            if (cropEditing && geoTool === 'crop') return;
        }
        geoToolToggle('crop');
    }
    /** Back to the tab R came from, once the crop has ended. True where it went. */
    function cropReturn() {
        if (cropReturnTab === null || cropEditing) return false;
        const back = cropReturnTab;
        cropReturnTab = null;
        developTabSet(back);
        return true;
    }

    function developTabSet(tab) {
        // A tab chosen — by hand, or by cropReturn itself — forgets the way back.
        cropReturnTab = null;
        if (tab === developTab || !['adjust', 'tools', 'masks', 'spots', 'info', 'layers'].includes(tab)) return;
        if (tab === 'layers' && !layersTool?.may()) return;
        if (developTab === 'tools' && cropEditing) {
            cropEditing = false;
            cropBefore = null;
            photoZoom.reset();
        }
        developTab = tab;
        if (tab !== 'layers') settings.set('developTab', tab);
        maskPlacing = null;
        if (tab !== 'masks') maskChosen = null;
        if (tab !== 'spots') {
            spotChosen = null;
            spotHover = null;
        }
        developPanelDraw();
        viewUpdate();
        developHandles();
    }
    developTabs?.addEventListener('click', (e) => {
        const b = e.target.closest('[data-tab]');
        if (b) developTabSet(b.dataset.tab);
    });

    /* When a mask was made on this visit — for the list's order, newest on
       top (masksPanelDraw). Not stored: masks that came with the picture
       have no such number and stand below, in their own order turned. */
    const maskBorn = new WeakMap();
    let maskBirth = 0;
    const maskBornNow = (m) => { maskBorn.set(m, ++maskBirth); return m; };

    /** The masks of one kind — made where missing, so a push always has a list. */
    const maskList = (kind) => (kind === 'linear' ? (develop.linears ??= [])
        : kind === 'brush' ? (develop.brushes ??= []) : (develop.radials ??= []));
    const maskOf = (c) => (c ? maskList(c.kind)[c.i] ?? null : null);
    const masksCount = () => (develop.radials?.length ?? 0) + (develop.linears?.length ?? 0) + (develop.brushes?.length ?? 0);
    /** Where an ellipse's size grips stand, as a share of its outer radii: on the inner line (the
     *  full effect's edge), at least a fifth out. Drawing, dragging and the turning zone share it. */
    const radialGripK = (r) => Math.max(.2, radialInnerShown(r));
    /* THE LINES SIT A LITTLE INSIDE THE PASSAGE (10.11.2026:
       „bei Lightroom ist das alles etwas eingerückt … mir fällt oft auf,
       dass es doch noch weiter innen etwas abdunkelt. Aber halt so schwach
       … 5 % vielleicht, nach innen"). The soft passage is a smoothstep, and
       its two tails are so flat that the eye puts the ends well inside the
       mathematical ones: the outer line stood where the effect is 0 and
       seemed too far out, the inner one where it is 100 and seemed too far
       in. Now each is drawn where the effect has reached LINE_SHOWN of its
       way — 95 % at the inner line, 5 % at the outer — for the ellipse and
       the gradient alike. Only the DRAWING moves: the mask is stored as
       before, and every grip and line drag converts back (radialOuterShown,
       linShown, linFrom), so a line stays under the pointer.
       LINE_T is where the smoothstep reaches that share (its inverse). */
    const LINE_SHOWN = .05;
    const LINE_T = .5 - Math.sin(Math.asin(1 - 2 * LINE_SHOWN) / 3);   // ≈ 0.135 of the passage
    /** The drawn inner and outer line of an ellipse, as shares of its outer radii. */
    function radialInnerShown(r) {
        const k = 1 - (r.feather ?? 50) / 100;
        return k + (1 - k) * LINE_T;
    }
    function radialOuterShown(r) {
        const k = 1 - (r.feather ?? 50) / 100;
        return k + (1 - k) * (1 - LINE_T);
    }
    /** The ellipse whose DRAWN lines stand at di and do (shares of the old outer radii): its new
     *  outer radii as a factor of the old, and its feather. The other line held where it was drawn. */
    function radialFromShown(di, d0) {
        const dout = Math.max(di, d0);
        const span = (dout - di) / (1 - 2 * LINE_T);
        let inner = di - span * LINE_T, outer = inner + span;
        if (inner < 0) { inner = 0; outer = dout / (1 - LINE_T); }
        outer = Math.max(outer, 1e-3);
        return { k: outer, feather: Math.round(Math.max(0, Math.min(100, (1 - inner / outer) * 100)) * 100) / 100 };
    }
    /** A gradient's drawn lines: where the effect is 95 % (d1) and 5 % (d2), in original pixels. */
    function linShown(r, nw, nh) {
        const ax = r.x1 * nw, ay = r.y1 * nh, vx = r.x2 * nw - ax, vy = r.y2 * nh - ay;
        return [[ax + vx * LINE_T, ay + vy * LINE_T], [ax + vx * (1 - LINE_T), ay + vy * (1 - LINE_T)]];
    }
    /** The stored points back from the drawn lines. */
    function linFrom(d1, d2, nw, nh) {
        const k = 1 - 2 * LINE_T;
        const vx = (d2[0] - d1[0]) / k, vy = (d2[1] - d1[1]) / k;
        const ax = d1[0] - vx * LINE_T, ay = d1[1] - vy * LINE_T;
        return { x1: ax / nw, y1: ay / nh, x2: (ax + vx) / nw, y2: (ay + vy) / nh };
    }
    const maskIs = (kind, i) => maskChosen !== null && maskChosen.kind === kind && maskChosen.i === i;

    /** The tab „Masken": the two ways to add one, the list, the chosen one's own settings. */
    function masksPanelDraw(may) {
        const full = masksCount() >= RADIALS_MAX;
        developAdd.disabled = !may || full;
        if (developAddLinear) developAddLinear.disabled = !may || full;
        if (developAddBrush) developAddBrush.disabled = !may || full || (develop.brushes?.length ?? 0) >= BRUSHES_MAX;
        developAdd.classList.toggle('is-active', maskPlacing === 'radial');
        developAddLinear?.classList.toggle('is-active', maskPlacing === 'linear');
        const masksPage = document.getElementById('photo-develop-masks');
        if (developTabs && masksPage) masksPage.style.setProperty('--develop-tabs-height', developTabs.offsetHeight + 'px');
        /* NO DOT on Ellipse, Verlauf, Pinsel (13.11.2026: „Bei den
           Masken brauchen wir keinen grünen Punkt, wenn da was enthalten
           ist") — the masks of each kind stand in the list below anyway. */
        radialListEl.textContent = '';
        /* ONE ROW PER MASK, AS IN LIGHTROOM (09.11.2026: „die
           ganzen Verläufe in eine Zeile … mit hinten dran einem Auge, dass
           wir die einzelnen ein- und ausblenden können. Wenn ich so einen
           Verlauf anklicke, soll er eingerahmt sein und unten drunter …
           kommt dann die Einstellung für die aktive"): the mask's little
           picture, its name, and at the end the eye. A click on the row
           chooses it (framed); its settings stand under the whole list
           (maskDraw). The eye switches the mask off without losing it —
           off: true, which the renderer, the plates and the export skip
           (develop.js, masksOf) and the server keeps (photo_develop_clean). */
        /* THE NEWEST ON TOP, as in „Ausbessern" (11.11.2026: „dreh
           die Liste bei den Masken auch um"). The masks lie in three lists by
           kind and carry no time of their own, so: the ones made on this
           visit first, the last made topmost (maskBorn); below them those
           that came stored, turned round as well — the brushes before the
           gradients before the ellipses, the higher number first. Only the
           showing turns; the masks keep their order and numbers. */
        const rows = [];
        for (const kind of ['radial', 'linear', 'brush']) {
            maskList(kind).forEach((r, i) => {
                const row = document.createElement('div');
                rows.push({ row, r, born: maskBorn.get(r) ?? 0, at: rows.length });
                row.className = 'photo-mask-row' + (maskIs(kind, i) ? ' is-active' : '') + (r.off ? ' is-off' : '');
                row.setAttribute('role', 'button');
                row.tabIndex = 0;
                const words = kind === 'radial' ? t('Ellipse %d', i + 1) : kind === 'brush' ? t('Pinsel %d', i + 1) : t('Verlauf %d', i + 1);
                const thumb = document.createElement('canvas');
                thumb.className = 'photo-mask-thumb';
                thumb.dataset.kind = kind;
                thumb.dataset.i = String(i);
                const name = document.createElement('span');
                name.className = 'photo-mask-name';
                name.textContent = words;
                const eye = document.createElement('button');
                eye.type = 'button';
                eye.className = 'button subtle small square photo-mask-eye';
                eye.disabled = !may;
                const eyeWords = r.off ? t('Einblenden') : t('Ausblenden');
                eye.title = eyeWords;
                eye.setAttribute('aria-label', eyeWords + ': ' + words);
                eye.setAttribute('aria-pressed', String(!r.off));
                eye.innerHTML = iconHtml(r.off ? 'eye-off' : 'eye');
                eye.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (r.off) delete r.off; else r.off = true;
                    developSoon();
                    developQueue();
                    developPanelDraw();
                });
                /* „UMKEHREN" IN THE ROW, BESIDE THE EYE (09.11.2026:
                   „Das Invertieren des Filters würde ich auch genau da oben
                   beim Filter in der Zeile machen … Ein Symbol für
                   Invertieren"): a filled field with the oval cut out — the
                   mask acting outside (icons.php, invert). Pressed while it is
                   inverted; the little picture turns white on black with it. */
                const flip = document.createElement('button');
                flip.type = 'button';
                flip.className = 'button subtle small square photo-mask-eye' + (r.invert ? ' is-active' : '');
                flip.disabled = !may;
                const flipWords = kind === 'radial' ? t('Umkehren — außerhalb der Ellipse ändern')
                    : kind === 'brush' ? t('Umkehren — außerhalb des Gemalten ändern') : t('Umkehren — die andere Seite ändern');
                flip.title = flipWords;
                flip.setAttribute('aria-label', flipWords + ': ' + words);
                flip.setAttribute('aria-pressed', String(!!r.invert));
                flip.innerHTML = iconHtml('invert');
                flip.addEventListener('click', (e) => {
                    e.stopPropagation();
                    r.invert = !r.invert;
                    developSoon();
                    developQueue();
                    developPanelDraw();
                    developHandles();
                });
                const choose = () => {
                    // Another mask chosen: the empty one in hand goes (maskDropEmpty) — and with it the indexes may shift.
                    if (!maskIs(kind, i) && maskChosen && maskEmpty(maskChosen.kind, maskOf(maskChosen))) {
                        const target = r;
                        maskDropEmpty();
                        const at = maskList(kind).indexOf(target);
                        maskChosen = at < 0 ? null : { kind, i: at };
                        maskLinesQuiet = false;
                        developSoon();
                        developPanelDraw();
                        developHandles();
                        return;
                    }
                    maskBare = false;
                    maskChosen = maskIs(kind, i) ? null : { kind, i };
                    maskLinesQuiet = false;
                    developPanelDraw();
                    developHandles();
                };
                row.addEventListener('click', choose);
                row.addEventListener('keydown', (e) => {
                    if (e.target === row && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); choose(); }
                });
                /* THE BIN, LAST IN THE ROW (09.11.2026: „bei dem Auge
                   und beim Invertieren brauche ich noch ein Symbol für diesen
                   Filter löschen"): it replaced „Ellipse löschen" / „Verlauf
                   löschen" under the settings. Red, as every bin that takes
                   something away; „Zurücksetzen" in the head still undoes all. */
                const bin = document.createElement('button');
                bin.type = 'button';
                bin.className = 'button subtle small square danger photo-mask-eye';
                bin.disabled = !may;
                const binWords = kind === 'radial' ? t('Ellipse löschen') : kind === 'brush' ? t('Pinsel löschen') : t('Verlauf löschen');
                bin.title = binWords;
                bin.setAttribute('aria-label', binWords + ': ' + words);
                bin.innerHTML = iconHtml('delete');
                bin.addEventListener('click', (e) => {
                    e.stopPropagation();
                    maskList(kind).splice(i, 1);
                    // The chosen one keeps its place in the list, or goes when it was this one.
                    if (maskChosen?.kind === kind) {
                        if (maskChosen.i === i) maskChosen = null;
                        else if (maskChosen.i > i) maskChosen = { kind, i: maskChosen.i - 1 };
                    }
                    developSoon();
                    developQueue();
                    developPanelDraw();
                    developHandles();
                });
                row.append(thumb, name, flip, eye, bin);
                maskThumbDraw(thumb);
            });
        }
        rows.sort((a, b) => b.born - a.born || b.at - a.at);
        /* THE FOLD (13.11.2026: „mir wird nur die aktive angezeigt.
           Diese steht dann immer ganz oben. Und wenn ich auf die Maske gehe,
           klappen unten drunter alle Masken auf. Dort kann ich dann
           auswählen … Es klappt nicht automatisch zu. Ich kann quasi
           durchklicken … Es klappt erst zu … wenn ich … den Maskenbereich
           verlasse"). Shut, the list shows one row: the chosen mask's, or
           the newest where none is chosen. The pointer on it opens all of
           them beneath; while open, the row that stood on top STAYS on top
           (maskListTop) — clicking through must not move the rows under the
           pointer —, and leaving the list shuts it, the chosen one alone. */
        const chosenMask = maskOf(maskChosen);
        const keep = maskListOpen && rows.some((x) => x.r === maskListTop) ? maskListTop : null;
        const top = rows.find((x) => x.r === (keep ?? chosenMask)) ?? rows[0];
        if (maskListOpen) maskListTop = top?.r ?? null;
        for (const x of rows) x.row.classList.toggle('is-top', x === top);
        radialListEl.classList.toggle('is-folded', !maskListOpen);
        radialListEl.append(...(top ? [top.row] : []), ...rows.filter((x) => x !== top).map((x) => x.row));
        maskDraw(may);
    }
    radialListEl.addEventListener('pointerenter', (e) => {
        if (e.pointerType === 'touch' || maskListOpen || radialListEl.children.length < 2) return;
        maskListOpen = true;
        maskListTop = maskOf(maskChosen);
        radialListEl.classList.remove('is-folded');
        developPanelDraw();
    });
    radialListEl.addEventListener('pointerleave', () => {
        if (!maskListOpen) return;
        maskListOpen = false;
        maskListTop = null;
        developPanelDraw();
    });

    /* THE MASK AS LIGHTROOM SHOWS IT (09.11.2026: „eine kleine
       Vorschau … einfach die Maske in schwarz auf einem weißen Hintergrund
       … eine Ellipse, die schwarz ist, und dann außen ins Weiße überläuft
       … Statt dahin zu schreiben Ellipse 1 außen, könnten wir einfach
       dieses Symbol machen. Und dann würden wir auch sehen, dass die
       Ellipse invertiert ist"). Each mask's button carries its mask in the
       picture's shape as one sees it (turned and cut, geoView): black where
       it acts, white where it does not, the soft rim between — so an
       inverted one is white inside on black. Reckoned with the shader's own
       formula (develop.js, main(): smoothstep over the rim, along the way
       for a gradient), in the original's pixels like the masks themselves.
       Redrawn with the handles, so it follows a drag. */
    const MASK_THUMB_H = 32;
    function maskThumbDraw(canvas) {
        const r = maskList(canvas.dataset.kind)[Number(canvas.dataset.i)];
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        if (!r || !W || !H) return;
        const view = geoSet(develop) ? geoView(develop, W, H) : null;
        const vw = view ? view.cw : W, vh = view ? view.ch : H;
        const h = MASK_THUMB_H, w = Math.max(12, Math.min(3 * h, Math.round(h * vw / vh)));
        if (canvas.width !== w * 2) { canvas.width = w * 2; canvas.height = h * 2; }
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';
        const g = canvas.getContext('2d');
        const img = g.createImageData(canvas.width, canvas.height);
        const smooth = (a, b, x) => { const t = Math.min(1, Math.max(0, (x - a) / (b - a))); return t * t * (3 - 2 * t); };
        let at;
        // Not drawn yet (maskCreate): acts nowhere, so the little picture is white all over.
        if (r.empty) at = () => 0;
        else if (canvas.dataset.kind === 'brush') {
            // The painted mask at a small size of the original, read through the view like the others.
            const fw = 120, fh = Math.max(1, Math.round(120 * H / W));
            const field = brushField(r.strokes ?? [], fw, fh);
            at = (x, y) => {
                const i = Math.floor(x / W * fw), j = Math.floor(y / H * fh);
                return i < 0 || j < 0 || i >= fw || j >= fh ? 0 : field[j * fw + i];
            };
        } else if (canvas.dataset.kind === 'linear') {
            const ax = r.x1 * W, ay = r.y1 * H, bx = r.x2 * W - ax, by = r.y2 * H - ay;
            const len = Math.max(bx * bx + by * by, 1);
            at = (x, y) => 1 - smooth(0, 1, ((x - ax) * bx + (y - ay) * by) / len);
        } else {
            const cx = r.x * W, cy = r.y * H, rx = Math.max(1, r.rx * W), ry = Math.max(1, r.ry * W);
            const a = -(r.angle ?? 0) * Math.PI / 180, cos = Math.cos(a), sin = Math.sin(a);
            const edge = Math.max((r.feather ?? 50) / 100, .001);
            at = (x, y) => {
                const dx = x - cx, dy = y - cy;
                const ex = (dx * cos - dy * sin) / rx, ey = (dx * sin + dy * cos) / ry;
                return 1 - smooth(1 - edge, 1, Math.hypot(ex, ey));
            };
        }
        for (let j = 0; j < canvas.height; j++) {
            for (let i = 0; i < canvas.width; i++) {
                const q = [(i + .5) / canvas.width * vw, (j + .5) / canvas.height * vh];
                const [x, y] = view ? view.toNat(q) : q;
                let m = at(x, y);
                if (r.invert) m = 1 - m;
                const v = Math.round(255 * (1 - m)), k = (j * canvas.width + i) * 4;
                img.data[k] = img.data[k + 1] = img.data[k + 2] = v;
                img.data[k + 3] = 255;
            }
        }
        g.putImageData(img, 0, 0);
    }

    /**
     * Farbe, Licht, Details, each a group that folds, its sliders under it —
     * for the picture as a whole and, since 11.11.2026, for a mask: the same
     * groups and the same sliders („nimm alle dazu und gruppiere
     * sie auch so"). `prefix` keeps a mask's folds apart from the picture's
     * in the one kept list (developFolded): one may want „Licht" shut on the
     * picture and open on a brush.
     */
    /* THE GROUPS ON A RAIL (13.11.2026: „für Mikrofotos, Farbe,
       Licht und Details auf der rechten Seite der Sidebar jeweils ein
       Symbol … Wenn ich über das Symbol fahre, möchte ich dann quasi auf
       der linken Seite die Einträge haben. So könnte ich außen am Rand
       erstmal selektieren, dann mit der Maus rüber in die Mitte fahren,
       dort Einstellungen machen und mir würde das Menü auch offen bleiben,
       wenn ich quasi weiter nach links aufs Bild gehe"). „Einstellungen"
       shows ONE group at a time: a column of signs at the sidebar's right
       edge chooses it by the pointer resting on a sign (a click and the
       keyboard's focus do too), and the choice stays until another sign is
       touched — leaving towards the sliders or the picture changes
       nothing, and the device keeps it (settings developGroup). Moving
       left from a sign crosses no other sign, so the way to the sliders is
       free. The folds of 08.11.2026 went here — a group is either shown or
       not —; the masks keep theirs (maskDraw), where all groups stand
       under one mask. A sign whose group holds a setting carries the
       accent's dot, as the tabs above do. Mikrofotos has none of its own:
       what it measures lands in Farbe. */
    /* THE RAIL'S SIGN BEFORE THE GROUP'S NAME in its plain head
       (13.11.2026: „links setzt du das Symbol nochmal vor der Überschrift
       ganz oben. Rechts machst du das nicht. Ich würde es rechts auch
       machen. Einheitliches Design") — the same sign the rail beside it
       carries, so head and chosen sign say one thing. The folding heads
       keep their triangle. */
    const HEAD_SIGNS = { micro: 'microscope', color: 'palette', mix: 'colour-mix', grade: 'colour-grade', light: 'sun', details: 'details', brush: 'brush' };
    const headSign = (key) => (HEAD_SIGNS[key] ? iconHtml(HEAD_SIGNS[key], { class: 'photo-develop-head-sign' }) : '');
    /* THE RAIL'S ORDER (22.11.2026: „Ganz oben Farbe, dann … Licht. Dann
       Farbmischer, dann Farbkorrektur, dann Details, dann Mikroskop"): the
       two broad groups first, the colour's finer hands after them —
       „Farbmischer" (mixGroup) and „Farbkorrektur" by brightness
       (gradeGroup) —, „Mikrofotos" last, the one group a macro picture
       never needs. The groups stand in the page in the same order. */
    const DEVELOP_RAIL = [
        { key: 'color', icon: 'palette' },
        { key: 'light', icon: 'sun' },
        { key: 'mix', icon: 'colour-mix', name: () => t('Farbmischer') },
        { key: 'grade', icon: 'colour-grade', name: () => t('Farbkorrektur') },
        { key: 'details', icon: 'details' },
        { key: 'micro', icon: 'microscope', name: () => t('Mikrofotos') },
    ];
    /**
     * A page with the rail: `body` for what the chosen sign shows, the signs
     * in a column at the sidebar's right edge. items: [{ key, icon, name,
     * set?, data? }]; choose(key) is called when a sign is rested on,
     * clicked or focused and the key is not the current one. Returns { wrap,
     * body, mark(key, on), active(key) } — the page is appended by the caller.
     */
    function railPage(items, current, choose, label, { hover = true } = {}) {
        const wrap = document.createElement('div');
        wrap.className = 'photo-develop-adjust';
        const body = document.createElement('div');
        body.className = 'photo-develop-adjust-body';
        const rail = document.createElement('div');
        rail.className = 'photo-develop-rail';
        // The column runs the page's height with its line; the signs inside it stick under the tabs.
        const signs = document.createElement('div');
        signs.className = 'photo-develop-rail-signs';
        signs.setAttribute('role', 'tablist');
        signs.setAttribute('aria-orientation', 'vertical');
        signs.setAttribute('aria-label', label);
        rail.append(signs);
        const buttons = new Map();
        let now = current;
        const active = (key) => {
            now = key;
            for (const [k, b] of buttons) {
                b.classList.toggle('is-active', k === key);
                b.setAttribute('aria-selected', String(k === key));
                b.tabIndex = k === key || (key === null && k === items[0].key) ? 0 : -1;
            }
        };
        const pick = (key) => { if (key !== now) { active(key); choose(key); } };
        const keys = items.map((r) => r.key);
        for (const r of items) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'photo-develop-rail-sign';
            b.setAttribute('role', 'tab');
            b.setAttribute('aria-label', r.name);
            b.title = r.name;
            b.dataset.railKey = r.key;
            for (const [k, v] of Object.entries(r.data ?? {})) b.dataset[k] = v;
            /* THE WORD BESIDE THE SIGN once the sidebar is pulled wide
               (13.11.2026: „zieht man etwas breiter, bekommt man
               noch den Labeltext dazu"); the stylesheet shows it
               (@container develop-side). `word` is the name without the
               key's hint the tools carry in their title. */
            b.innerHTML = iconHtml(r.icon);
            const word = document.createElement('span');
            word.className = 'photo-develop-rail-word';
            word.textContent = r.word ?? r.name;
            b.append(word);
            b.classList.toggle('is-set', !!r.set);
            // A click and the keyboard's focus choose; resting the pointer on it too (below).
            b.addEventListener('click', () => pick(r.key));
            b.addEventListener('focus', () => pick(r.key));
            b.addEventListener('keydown', (e) => {
                if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
                e.preventDefault();
                const i = Math.max(0, keys.indexOf(now)) + (e.key === 'ArrowDown' ? 1 : -1);
                buttons.get(keys[(i + keys.length) % keys.length])?.focus();
            });
            buttons.set(r.key, b);
            signs.append(b);
        }
        active(current);
        /* RESTING ON A SIGN CHOOSES, with the guard against grazing
           (hoverpick.js): wide, with their words, the signs reach far
           enough left that a stroke out to the sliders crosses the one
           above or below — that one waits, and is dropped when left. */
        /* A TOOL IS TAKEN BY A CLICK ONLY (14.11.2026: „die Tools
           per Klick auswählbar. Nicht mehr per Überfahren"): a group of
           sliders shown by resting on it changes nothing, but a tool changes
           what the next press in the picture does — „Ausbessern" passes
           hover: false. */
        if (hover) hoverPick(signs, (b) => b.dataset.railKey === now, (b) => pick(b.dataset.railKey), '.photo-develop-rail-sign', 'left');
        wrap.append(body, rail);
        // Sticky under the tab row: its height, measured, so the rail starts where the tabs end.
        if (developTabs) wrap.style.setProperty('--develop-tabs-height', developTabs.offsetHeight + 'px');
        return { wrap, body, active, mark: (key, on) => buttons.get(key)?.classList.toggle('is-set', !!on) };
    }

    /* THE HISTOGRAM OVER „EINSTELLUNGEN" (14.11.2026: „oberhalb
       … der durchlaufenden Überschrift noch ein Histogramm. Das man aber
       auch dezent zuklappen kann"). It counts what the viewer SHOWS — the
       developed canvas, or the original while nothing is set — turned and
       cut as one sees it, so every slider step moves it (developRedraw
       asks after each frame). Read off a copy of about 360 pixels: plenty
       for 256 bins, and a frame's work on any machine. Red, green and blue
       lie over each other, added: where all three agree the curve is
       white. ON A DARK PLATE IN EVERY THEME — the curves are the picture's
       own colours, and added colours need a dark ground to show; on a
       light theme their white would vanish. Its fold is the device's
       (settings developHistogram). Only drawn while it is in sight. */
    let histoCanvas = null;
    let histoSample = null;
    let histoQueued = false;
    const histogramOpen = () => settings.get('developHistogram', true) !== false;

    function histogramSoon() {
        if (histoQueued || !histoCanvas?.isConnected || !histogramOpen()) return;
        histoQueued = true;
        requestAnimationFrame(() => { histoQueued = false; histogramDraw(); });
    }

    function histogramDraw() {
        const out = histoCanvas;
        if (!out?.isConnected || !histogramOpen()) return;
        const dpr = window.devicePixelRatio || 1;
        const W = Math.max(1, Math.round(out.clientWidth * dpr)), H = Math.max(1, Math.round(out.clientHeight * dpr));
        if (out.width !== W || out.height !== H) { out.width = W; out.height = H; }
        const g = out.getContext('2d');
        g.clearRect(0, 0, W, H);
        const fromCanvas = !developCanvas.hidden;
        const src = fromCanvas ? developCanvas
            : (photoImageEl.complete && photoImageEl.naturalWidth > 0 ? photoImageEl : null);
        if (!src) return;
        const sw = fromCanvas ? src.width : src.naturalWidth, sh = fromCanvas ? src.height : src.naturalHeight;
        const k = Math.min(1, 360 / Math.max(sw, sh));
        const w = Math.max(1, Math.round(sw * k)), h = Math.max(1, Math.round(sh * k));
        histoSample ??= document.createElement('canvas');
        if (histoSample.width !== w || histoSample.height !== h) { histoSample.width = w; histoSample.height = h; }
        const c = histoSample.getContext('2d', { willReadFrequently: true });
        c.clearRect(0, 0, w, h);
        let px;
        try {
            c.drawImage(src, 0, 0, w, h);
            px = c.getImageData(0, 0, w, h).data;
        } catch {
            return;
        }
        const bins = [new Float32Array(256), new Float32Array(256), new Float32Array(256)];
        // Transparent where the turned picture leaves the view empty (develop.js) — not black, so not counted.
        for (let i = 0; i < px.length; i += 4) {
            if (px[i + 3] < 128) continue;
            bins[0][px[i]]++;
            bins[1][px[i + 1]]++;
            bins[2][px[i + 2]]++;
        }
        // A light smoothing, so the comb of an edited 8-bit picture does not read as noise.
        const smooth = bins.map((b) => b.map((v, i) => (b[Math.max(0, i - 1)] + 2 * v + b[Math.min(255, i + 1)]) / 4));
        // Scaled by the inner bins: a clipped end (all black, all white) would flatten the rest to a line.
        let top = 1;
        for (const b of smooth) for (let i = 1; i < 255; i++) if (b[i] > top) top = b[i];
        g.globalCompositeOperation = 'lighter';
        ['rgb(230 70 70 / .75)', 'rgb(70 200 90 / .75)', 'rgb(80 120 240 / .75)'].forEach((colour, ch) => {
            g.beginPath();
            g.moveTo(0, H);
            for (let i = 0; i < 256; i++) g.lineTo(i / 255 * W, H - Math.min(1, smooth[ch][i] / top) * H * .95);
            g.lineTo(W, H);
            g.closePath();
            g.fillStyle = colour;
            g.fill();
        });
        g.globalCompositeOperation = 'source-over';
    }

    /** The histogram's box with its quiet fold, for the top of „Einstellungen". */
    function histogramBox() {
        const box = document.createElement('div');
        box.className = 'photo-histogram' + (histogramOpen() ? '' : ' is-collapsed');
        const head = document.createElement('button');
        head.type = 'button';
        head.className = 'photo-histogram-head';
        head.setAttribute('aria-expanded', String(histogramOpen()));
        head.innerHTML = iconHtml('fold-triangle');
        head.append(t('Histogramm'));
        head.addEventListener('click', () => {
            const open = !histogramOpen();
            settings.set('developHistogram', open);
            box.classList.toggle('is-collapsed', !open);
            head.setAttribute('aria-expanded', String(open));
            histogramSoon();
        });
        histoCanvas = document.createElement('canvas');
        histoCanvas.className = 'photo-histogram-canvas';
        histoCanvas.setAttribute('role', 'img');
        histoCanvas.setAttribute('aria-label', t('Histogramm'));
        // A click on the curves folds it too (14.11.2026) — the head is small, the curves are where the hand is.
        histoCanvas.title = t('Zuklappen');
        histoCanvas.addEventListener('click', () => head.click());
        box.append(head, histoCanvas);
        return box;
    }

    /* „FARBSTICH ENTFERNEN" at the foot of „Farbe" (14.11.2026;
       develop.js castMeasure says what is measured and how it is taken
       out). One press measures the picture and sets the correction; then
       three sliders — the whole, the dark tones, the bright ones — and
       „Neu messen" and the cross beside the button. Measured once, on a
       copy of the original some 1000 pixels across, and kept with the
       edit, so every device shows the same. */
    function castBlock(may) {
        const box = document.createElement('div');
        box.className = 'photo-cast';
        const row = document.createElement('div');
        row.className = 'photo-cast-row';
        const name = document.createElement('span');
        name.className = 'photo-cast-name';
        name.textContent = t('Farbstich');
        const go = document.createElement('button');
        go.type = 'button';
        go.className = 'button subtle small';
        // Points of the pipette alone have never been measured: there the button measures beneath them for the first time.
        const measured = develop.cast && (develop.cast.base || !develop.cast.picks?.length);
        go.textContent = measured ? t('Neu messen') : develop.cast ? t('Messen') : t('Entfernen');
        go.title = t('Sucht die grauen Stellen im Bild und nimmt ihnen den Farbstich.');
        go.disabled = !may;
        go.addEventListener('click', () => castMeasureNow());
        /* THE PIPETTE beside it (22.11.2026; develop.js castFromPicks):
           pressed, every click on the picture names a place that should be
           grey or white; pressed again, or Escape, lets it go. */
        const pick = document.createElement('button');
        pick.type = 'button';
        pick.className = 'button subtle small square' + (castPicking ? ' is-active' : '');
        pick.innerHTML = iconHtml('eyedropper');
        pick.title = t('Pipette: Klicke im Bild auf etwas, das weiß oder grau sein soll.');
        pick.setAttribute('aria-label', pick.title);
        pick.setAttribute('aria-pressed', String(castPicking));
        pick.disabled = !may;
        pick.addEventListener('click', () => {
            const on = !castPicking;
            wbPickEnd();
            castPickEnd();
            if (on) {
                castPicking = true;
                photoFrame.classList.add('is-cast-picking');
            }
            developPanelDraw();
        });
        row.append(name, pick, go);
        if (develop.cast) {
            const off = document.createElement('button');
            off.type = 'button';
            off.className = 'button subtle small square';
            off.innerHTML = iconHtml('close');
            off.title = t('Den Farbstich wieder lassen');
            off.setAttribute('aria-label', off.title);
            off.disabled = !may;
            off.addEventListener('click', () => {
                delete develop.cast;
                castChanged();
            });
            row.append(off);
        }
        box.append(row);
        if (develop.cast) {
            for (const [key, words] of [['amount', t('Stärke')], ['shadows', t('Tiefen')], ['highlights', t('Lichter')]]) {
                box.append(developSlider({ key: 'cast-' + key, name: () => words, min: 0, max: 100, home: 100 },
                    develop.cast[key] ?? 100, (v) => {
                        if (!develop.cast) return;
                        develop.cast[key] = v;
                        developSoon();
                        developQueue();
                    }, may));
            }
        }
        // The pipette's points, each with the colour it found and its brightness; the cross takes one away.
        const picks = develop.cast?.picks ?? [];
        if (picks.length) {
            const list = document.createElement('div');
            list.className = 'photo-cast-picks';
            picks.forEach((p, i) => {
                const chip = document.createElement('span');
                chip.className = 'photo-cast-pick';
                const swatch = document.createElement('span');
                swatch.className = 'photo-cast-swatch';
                const y = .2126 * p.r + .7152 + .0722 * p.b;
                const enc = (v) => Math.round(255 * Math.min(1, Math.max(0, v * p.lum / y)) ** (1 / 2.2));
                swatch.style.background = `rgb(${enc(p.r)} ${enc(1)} ${enc(p.b)})`;
                const words = document.createElement('span');
                words.textContent = t('Punkt %d', i + 1) + ' · ' + (p.lum < .06 ? t('dunkel') : p.lum < .3 ? t('mittel') : t('hell'));
                const off = document.createElement('button');
                off.type = 'button';
                off.className = 'button subtle small square';
                off.innerHTML = iconHtml('close');
                off.title = t('Diesen Punkt entfernen');
                off.setAttribute('aria-label', off.title);
                off.disabled = !may;
                off.addEventListener('click', () => castPickRemove(i));
                chip.append(swatch, words, off);
                list.append(chip);
            });
            box.append(list);
        }
        return box;
    }

    let castPicking = false;
    function castPickEnd() {
        castPicking = false;
        photoFrame.classList.remove('is-cast-picking');
    }
    /** A point of the pipette taken away: the ranges worked out anew, or the measurement back, or no cast at all. */
    function castPickRemove(i) {
        const c = develop.cast;
        if (!c?.picks) return;
        c.picks.splice(i, 1);
        if (!c.picks.length) {
            delete c.picks;
            if (c.base) {
                c.n = c.base;
                delete c.base;
            } else {
                delete develop.cast;
            }
        } else {
            c.n = castFromPicks(c);
        }
        castChanged();
    }
    // A click on the picture while the pipette is pressed: the grey it names becomes a point.
    photoFrame.addEventListener('pointerdown', (e) => {
        if (!castPicking || !developMode || e.button !== 0 || e.target.closest?.('button')) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        swallowClick();
        if (!developMay()) return;
        const at = clientNatural(e.clientX, e.clientY);
        const px = wbPixels();
        if (!at?.inside || !px) return;
        const read = castPickRead(px.data, px.w, px.h, Math.floor(at.p[0] * px.k), Math.floor(at.p[1] * px.k));
        if (read.none === 'bright') {
            host.status(t('Diese Stelle ist ausgebrannt. Klicke auf ein helles Grau, das noch Zeichnung hat.'), true);
            return;
        }
        if (read.none === 'dark') {
            host.status(t('Diese Stelle ist zu dunkel, um ihre Farbe zu messen.'), true);
            return;
        }
        const c = develop.cast ?? { amount: 100, shadows: 100, highlights: 100 };
        c.picks ??= [];
        if (c.picks.length >= CAST_PICKS_MAX) {
            host.status(t('Mehr als %d Punkte gehen nicht. Nimm erst einen weg.', CAST_PICKS_MAX), true);
            return;
        }
        // A measurement made before the first point stays beneath the points (develop.js castFromPicks).
        if (!c.picks.length && c.n && !c.base) c.base = c.n;
        c.picks.push({ px: at.p[0] / photoImageEl.naturalWidth, py: at.p[1] / photoImageEl.naturalHeight, lum: read.lum, r: read.r, b: read.b });
        c.n = castFromPicks(c);
        develop.cast = c;
        castChanged();
    }, true);

    /* „FARBMISCHER" (22.11.2026; develop.js MIX_COLOURS says what the
       sliders do). Lightroom's arrangement: the three kinds as a switch on
       top — Farbton, Sättigung, Luminanz —, beneath it the eight colours
       of the chosen kind, each line painted with where it takes its colour.
       The kind is the device's (settings developMixKind), like the rail's
       group; a kind that holds a setting carries the group's dot. */
    function mixGroup(may, mark) {
        const group = document.createElement('div');
        group.className = 'photo-develop-group';
        group.dataset.group = 'mix';
        const head = document.createElement('h3');
        head.className = 'list-label photo-develop-group-head is-plain';
        head.innerHTML = headSign('mix');
        head.append(t('Farbmischer'));
        const kinds = document.createElement('div');
        kinds.className = 'blend-kinds-switch photo-mix-kinds';
        kinds.setAttribute('role', 'tablist');
        const rows = document.createElement('div');
        rows.className = 'photo-mix-rows';
        const kindSet = (k) => MIX_COLOURS.some((c) => (Number(develop.mix?.[k]?.[c.key]) || 0) !== 0);
        let chosen = MIX_KINDS.some((k) => k.key === settings.get('developMixKind')) ? settings.get('developMixKind') : 's';
        const buttons = MIX_KINDS.map((k) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'blend-kind';
            b.setAttribute('role', 'tab');
            b.dataset.kind = k.key;
            b.textContent = k.name();
            b.addEventListener('click', () => {
                if (chosen === k.key) return;
                chosen = k.key;
                settings.set('developMixKind', chosen);
                draw();
            });
            kinds.append(b);
            return b;
        });
        const marks = () => {
            for (const b of buttons) {
                b.classList.toggle('is-active', b.dataset.kind === chosen);
                b.setAttribute('aria-selected', String(b.dataset.kind === chosen));
                b.classList.toggle('is-set', kindSet(b.dataset.kind));
            }
        };
        const draw = () => {
            marks();
            rows.textContent = '';
            for (const c of MIX_COLOURS) {
                const kind = chosen;
                rows.append(beforeRow(developSlider({ key: `mix-${kind}-${c.key}`, name: c.name, track: mixTrack(kind, c.key) },
                    develop.mix?.[kind]?.[c.key] ?? 0, (v) => {
                        develop.mix ??= {};
                        develop.mix[kind] ??= {};
                        if (v) develop.mix[kind][c.key] = v; else delete develop.mix[kind][c.key];
                        if (!Object.keys(develop.mix[kind]).length) delete develop.mix[kind];
                        if (!Object.keys(develop.mix).length) delete develop.mix;
                        developSoon();
                        developQueue();
                        marks();
                        mark();
                    }, may)));
            }
        };
        draw();
        group.append(head, kinds, rows);
        return group;
    }

    /* „FARBKORREKTUR NACH HELLIGKEIT" (22.11.2026; develop.js
       GRADE_RANGES says what the sliders do). All three ranges in sight,
       bright to dark as „Licht" orders them, each under its small head
       with Farbton, Stärke and Luminanz; Abgleich last, because it moves
       the border between them. Stärke's line ends in the colour its range
       is pushed towards and follows the Farbton slider while it moves. */
    function gradeGroup(may, mark) {
        const group = document.createElement('div');
        group.className = 'photo-develop-group';
        group.dataset.group = 'grade';
        const head = document.createElement('h3');
        head.className = 'list-label photo-develop-group-head is-plain';
        head.innerHTML = headSign('grade');
        head.append(t('Farbkorrektur'));
        group.append(head);
        const changed = () => {
            developSoon();
            developQueue();
            mark();
        };
        const set = (range, k, v) => {
            develop.grade ??= {};
            develop.grade[range] ??= {};
            if (v) develop.grade[range][k] = v; else delete develop.grade[range][k];
            if (!Object.keys(develop.grade[range]).length) delete develop.grade[range];
            if (!Object.keys(develop.grade).length) delete develop.grade;
        };
        const strengthTrack = (h) => `linear-gradient(90deg, hsl(${h} 0% 52%), hsl(${h} 80% 52%))`;
        for (const r of GRADE_RANGES) {
            const now = develop.grade?.[r.key] ?? {};
            const sub = document.createElement('div');
            sub.className = 'photo-grade-range';
            const name = document.createElement('span');
            name.className = 'photo-grade-name';
            name.textContent = r.name();
            const strength = beforeRow(developSlider({ key: `grade-${r.key}-s`, name: () => t('Stärke'), min: 0, max: 100,
                track: strengthTrack(now.h ?? 0) }, now.s ?? 0, (v) => { set(r.key, 's', v); changed(); }, may));
            const hue = beforeRow(developSlider({ key: `grade-${r.key}-h`, name: () => t('Farbton'), min: 0, max: 359, suffix: '°',
                track: HUE_TRACK }, now.h ?? 0, (v) => {
                set(r.key, 'h', v);
                strength.querySelector('input')?.style.setProperty('--track', strengthTrack(v));
                changed();
            }, may));
            const lum = beforeRow(developSlider({ key: `grade-${r.key}-l`, name: () => t('Luminanz'), track: DEVELOP_TRACKS.exposure },
                now.l ?? 0, (v) => { set(r.key, 'l', v); changed(); }, may));
            sub.append(name, hue, strength, lum);
            group.append(sub);
        }
        group.append(beforeRow(developSlider({ key: 'grade-balance', name: () => t('Abgleich'),
            track: 'linear-gradient(90deg, #333, #ccc)' }, develop.grade?.balance ?? 0, (v) => {
            develop.grade ??= {};
            if (v) develop.grade.balance = v; else delete develop.grade.balance;
            if (!Object.keys(develop.grade).length) delete develop.grade;
            changed();
        }, may)));
        return group;
    }

    function castChanged() {
        developSoon();
        developQueue();
        developPanelDraw();
    }

    /** Measures the open picture's cast and sets its correction — the sliders kept where they stood. */
    function castMeasureNow() {
        if (!developMay()) return;
        const img = photoImageEl;
        if (!img.complete || !img.naturalWidth) return;
        const k = Math.min(1, 1000 / Math.max(img.naturalWidth, img.naturalHeight));
        const w = Math.max(1, Math.round(img.naturalWidth * k)), h = Math.max(1, Math.round(img.naturalHeight * k));
        const c = document.createElement('canvas');
        c.width = w;
        c.height = h;
        const g = c.getContext('2d', { willReadFrequently: true });
        let found = null;
        try {
            g.drawImage(img, 0, 0, w, h);
            found = castMeasure(g.getImageData(0, 0, w, h).data, w, h);
        } catch {
            host.status(t('Das Bild ließ sich nicht messen.'), true);
            return;
        }
        if (!found) {
            host.status(t('Im Bild ist zu wenig Graues, um einen Farbstich zu messen.'), true);
            return;
        }
        const was = develop.cast ?? {};
        develop.cast = { n: found.n, amount: was.amount ?? 100, shadows: was.shadows ?? 100, highlights: was.highlights ?? 100 };
        // The pipette's points stay, now on the new measurement (develop.js castFromPicks).
        if (was.picks?.length) {
            develop.cast.picks = was.picks;
            develop.cast.base = found.n;
            develop.cast.n = castFromPicks(develop.cast);
        }
        castChanged();
    }

    function developRailDraw(may) {
        const groupName = (key) => DEVELOP_GROUPS.find((g) => g.key === key)?.name() ?? key;
        const known = DEVELOP_RAIL.map((r) => r.key);
        const chosen = known.includes(settings.get('developGroup')) ? settings.get('developGroup') : 'color';
        const show = (key) => {
            for (const el of page.body.querySelectorAll('.photo-develop-group')) el.hidden = el.dataset.group !== key;
        };
        const page = railPage(DEVELOP_RAIL.map((r) => ({ ...r, name: r.name ? r.name() : groupName(r.key) })), chosen, (key) => {
            settings.set('developGroup', key);
            show(key);
        }, t('Einstellungen'));
        const mark = () => {
            for (const g of DEVELOP_GROUPS) {
                page.mark(g.key, g.sliders.some((s) => (Number(develop[s.key]) || 0) !== 0) || (g.key === 'color' && !!develop.cast));
            }
            page.mark('mix', mixSet(develop));
            page.mark('grade', gradeSet(develop));
        };
        developGroupsDraw(page.body, develop, '', (s, v) => {
            develop[s.key] = v;
            developSoon();
            developQueue();
            mark();
        }, may, false);
        page.body.querySelector('.photo-develop-group[data-group="light"]')?.after(mixGroup(may, mark), gradeGroup(may, mark));
        page.body.append(microGroup(may, new Set(), false));
        page.body.querySelector('.photo-develop-group[data-group="color"]')?.append(castBlock(may));
        mark();
        show(chosen);
        developSliders.append(histogramBox(), page.wrap);
        histogramSoon();
    }

    /** A slider that goes home while „Original" is held (developSlider, homeShow) — at once if it is held now. */
    function beforeRow(row) {
        row.dataset.before = '';
        if (developBefore) row.homeShow(true);
        return row;
    }

    function developGroupsDraw(into, values, prefix, change, may, folding = true) {
        const keptFolds = settings.get('developFolded', []);
        const folded = new Set(Array.isArray(keptFolds) ? keptFolds : []);
        // A mask's sliders may reach further down than the picture's — Schärfen softens there (develop.js, maskMin).
        const own = (s) => (prefix === 'mask:' && s.maskMin !== undefined ? { ...s, min: s.maskMin } : s);
        for (const g of DEVELOP_GROUPS) {
            const fold = prefix + g.key;
            const group = document.createElement('div');
            group.className = 'photo-develop-group';
            group.dataset.group = g.key;
            if (!folding) {
                // On the rail's page (developRailDraw): a plain head that names the group, nothing folds.
                const head = document.createElement('h3');
                head.className = 'list-label photo-develop-group-head is-plain';
                head.innerHTML = headSign(g.key);
                head.append(g.name());
                group.append(head);
                for (const s of g.sliders) group.append(beforeRow(developSlider(own(s), values[s.key], (v) => change(s, v), may)));
                into.append(group);
                continue;
            }
            group.classList.toggle('is-collapsed', folded.has(fold));
            const head = document.createElement('button');
            head.type = 'button';
            head.className = 'list-label photo-develop-group-head';
            head.setAttribute('aria-expanded', String(!folded.has(fold)));
            head.innerHTML = iconHtml('fold-triangle');
            head.append(g.name());
            if (g.sliders.some((s) => (values[s.key] ?? 0) !== 0)) {
                const dot = document.createElement('span');
                dot.className = 'photo-develop-group-set';
                dot.title = t('Hier ist etwas eingestellt.');
                head.append(dot);
            }
            head.addEventListener('click', () => {
                const keptNow = settings.get('developFolded', []);
                const now = new Set(Array.isArray(keptNow) ? keptNow : []);
                if (now.has(fold)) now.delete(fold); else now.add(fold);
                settings.set('developFolded', [...now]);
                group.classList.toggle('is-collapsed', now.has(fold));
                head.setAttribute('aria-expanded', String(!now.has(fold)));
            });
            group.append(head);
            for (const s of g.sliders) group.append(beforeRow(developSlider(own(s), values[s.key], (v) => change(s, v), may)));
            into.append(group);
        }
    }

    /** The chosen mask's own sliders, an ellipse's soft edge, which side it changes, and the bucket. */
    function maskDraw(may) {
        const r = maskOf(maskChosen);
        radialBox.hidden = !r;
        /* The settings with their rail only where there is a mask in hand
           (photo_window.php) — and not while the masks' fold stands open
           (13.11.2026: „Die Schieberegler werden ausgeblendet,
           wenn die Masken aufklappen"): the rows open over the place the
           sliders held instead of pushing them down, and come back when the
           pointer leaves the list. */
        const maskSettings = document.getElementById('photo-mask-settings');
        if (maskSettings) maskSettings.hidden = !r || maskListOpen;
        // The brush's sign on the rail only on a brush (photo_window.php).
        const brushSign = document.querySelector('#photo-mask-rail [data-mask-group="brush"]');
        if (brushSign) brushSign.hidden = !(r && maskChosen?.kind === 'brush');
        radialBox.textContent = '';
        if (!r) return;
        const kind = maskChosen.kind;
        const changed = () => {
            developSoon();
            developQueue();
        };
        /* „Umkehren" moved into the mask's own row, beside the eye (masksPanelDraw).
           NO „WEICHE KANTE" SLIDER (09.11.2026: „das machen wir
           direkt im Bild bei den Ellipsen"): the soft rim is the gap between
           the two lines, set by dragging the inner one (gripDrag, 'inner'). */
        /* THE BRUSH'S OWN SETTINGS ARE A GROUP OF THE RAIL
           (13.11.2026, „die Pinseleinstellung nehmen wir doch mit in die
           Schnellauswahl"): Malen/Radieren, size, soft edge, flow, „Maske
           zeigen" under the head „Pinsel", stacked with the others. */
        if (kind === 'brush') {
            const group = document.createElement('div');
            group.className = 'photo-develop-group';
            group.dataset.group = 'brush';
            const head = document.createElement('h3');
            head.className = 'list-label photo-develop-group-head is-plain';
            head.innerHTML = headSign('brush');
            head.append(t('Pinsel'));
            group.append(head, brushToolPanel(may));
            radialBox.append(group);
        }
        /* EVERY SLIDER OF THE PICTURE, IN ITS GROUPS (11.11.2026:
           „nimm alle dazu und gruppiere sie auch so") — a mask is a small
           development of its own; RADIAL_SLIDERS is the groups' sliders
           in one list. */
        /* ONE GROUP AT A TIME, CHOSEN ON THE RAIL (13.11.2026:
           „für jede Maske quasi die gleichen Einstellungen. Farbe, Licht,
           Details … ähnlich per Schnellauswahl öffnen") — the same rail as
           „Einstellungen", its signs in the markup (photo_window.php); the
           choice is the device's (settings developMaskGroup) and the same
           for every mask. The brush's own settings above stay always
           („die Pinseleinstellungen … immer anzeigen"). */
        // „Pinsel" is a choice only on a brush; elsewhere a kept „Pinsel" falls back to Farbe.
        const known = kind === 'brush' ? ['brush', 'color', 'light', 'details'] : ['color', 'light', 'details'];
        const chosen = known.includes(settings.get('developMaskGroup')) ? settings.get('developMaskGroup') : 'color';
        const signs = [...document.querySelectorAll('#photo-mask-rail [data-mask-group]')];
        const mark = () => {
            for (const b of signs) {
                const g = DEVELOP_GROUPS.find((x) => x.key === b.dataset.maskGroup);
                b.classList.toggle('is-set', !!g?.sliders.some((s) => (Number(r[s.key]) || 0) !== 0));
            }
        };
        developGroupsDraw(radialBox, r, 'mask:', (s, v) => {
            r[s.key] = v;
            changed();
            mark();
            if (!maskLinesQuiet) { maskLinesQuiet = true; developHandles(); }
        }, may, false);
        maskGroupShow(chosen);
        mark();
        // Deleting moved into the mask's own row, its bin last (masksPanelDraw, 09.11.2026).
    }
    /** The chosen mask's group on the rail: its sliders shown, the others hidden, the sign marked. */
    function maskGroupShow(key) {
        /* ONE HEIGHT FOR ALL THREE (13.11.2026: „damit unten die
           Masken nicht zu sehr in der Höhe die ganze Zeit verspringen …
           das Schnellauswahlmenü in einer festen Höhe"): the groups lie on
           top of each other in one grid cell and the others are only
           invisible (is-off, photowindow.css), so the box keeps the tallest
           one's height and the masks' rows below stay where they are. */
        for (const el of radialBox.querySelectorAll('.photo-develop-group[data-group]')) el.classList.toggle('is-off', el.dataset.group !== key);
        for (const b of document.querySelectorAll('#photo-mask-rail [data-mask-group]')) {
            const on = b.dataset.maskGroup === key;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', String(on));
            b.tabIndex = on ? 0 : -1;
        }
    }
    /* THE VEIL STEPS ASIDE WHILE AN EFFECT SLIDER MOVES
       (13.11.2026, „nur wenn die Schieberegler dann verwendet werden, würde
       ich sie ausblenden. Damit man das Ergebnis der Schieberegler sieht"):
       a slider of Farbe, Licht or Details of the chosen mask — not the
       brush's own size, soft edge and flow, which change no result — lays
       it aside from the press, and SPOT_SLIDE_REST after letting go (or the
       last value by key or wheel) it comes back, as the spots' frame does. */
    const maskEffectSlider = (e) => e.target instanceof HTMLInputElement && e.target.type === 'range'
        && radialBox.contains(e.target) && e.target.closest('.photo-develop-group')?.dataset.group !== 'brush';
    /* AND THE MASK IN HAND STAYS BESIDE THE PICTURE (13.11.2026:
       „Wähle ich eine Maske … und sie wird aktiv … gehe ich … nach links ins
       Bild, um die Masken zusammenzuklappen und an die Regler zu kommen.
       Wenn ich dann zurück zu den Reglern fahre, wird mir die aktive Maske
       ausgeblendet. Das würde ich nicht machen. Ich würde sie erst
       ausblenden, wenn man einen Regler bedient"). Leaving the picture lays
       only the other masks' pins aside (maskLinesQuiet); the chosen mask's
       lines stand until a slider of it moves, and come back
       SPOT_SLIDE_REST after — the veil of a brush the same (maskOverlay). */
    function maskSlideStart() {
        clearTimeout(maskSlideTimer);
        if (maskSliding) return;
        maskSliding = true;
        developSoon();
        developHandles();
    }
    function maskSlideRest() {
        clearTimeout(maskSlideTimer);
        maskSlideTimer = setTimeout(() => {
            if (maskSlideHeld || !maskSliding) return;
            maskSliding = false;
            developSoon();
            developHandles();
        }, SPOT_SLIDE_REST);
    }
    radialBox.addEventListener('pointerdown', (e) => {
        if (!maskEffectSlider(e)) return;
        maskSlideHeld = true;
        maskSlideStart();
    });
    radialBox.addEventListener('input', (e) => {
        if (!maskEffectSlider(e)) return;
        maskSlideStart();
        if (!maskSlideHeld) maskSlideRest();
    });
    for (const type of ['pointerup', 'pointercancel']) {
        document.addEventListener(type, () => {
            if (!maskSlideHeld) return;
            maskSlideHeld = false;
            maskSlideRest();
        });
    }
    {
        const maskSigns = document.querySelector('#photo-mask-rail .photo-develop-rail-signs');
        if (maskSigns) hoverPick(maskSigns,
            (b) => settings.get('developMaskGroup') === b.dataset.maskGroup && b.classList.contains('is-active'),
            (b) => { settings.set('developMaskGroup', b.dataset.maskGroup); maskGroupShow(b.dataset.maskGroup); },
            '[data-mask-group]', 'left');
    }
    for (const b of document.querySelectorAll('#photo-mask-rail [data-mask-group]')) {
        const pick = () => {
            if (settings.get('developMaskGroup') === b.dataset.maskGroup && b.classList.contains('is-active')) return;
            settings.set('developMaskGroup', b.dataset.maskGroup);
            maskGroupShow(b.dataset.maskGroup);
        };
        b.addEventListener('click', pick);
        b.addEventListener('focus', pick);
        b.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            e.preventDefault();
            const all = [...document.querySelectorAll('#photo-mask-rail [data-mask-group]:not([hidden])')];
            const i = all.indexOf(b) + (e.key === 'ArrowDown' ? 1 : -1);
            all[(i + all.length) % all.length].focus();
        });
    }

    /* --- „AUSBESSERN" (10.11.2026) ---------------------------------
       The fourth tab: places mended from elsewhere in the picture, as
       Lightroom's spot removal (develop.js, spotsApply, says how and why as
       descriptions and not as a pixel layer). A click lays a round spot, a
       drag paints a stroke along a blade of grass; the source is chosen
       nearby (spotSourceFind) and can be dragged elsewhere, the spot itself
       moved by dragging inside it. One tool „Reparieren" with two kinds,
       „Angleichen" and „Kopieren" (spotKindRow), and the brush's size, soft edge and opacity — they act on the chosen
       spot and are kept on the device for the next one (settings
       spotBrush). Every spot a row, with its eye and its bin, as the masks
       have. [ and ] change the size, Delete takes the chosen spot away,
       Escape lets it go; the space bar still pans. */

    /** The kind, size, soft edge or opacity: on the chosen spot, and kept for the next. */
    /** A setting of „Ausbessern"; `panel`: from the sidebar's sliders, which reach a just-painted spot too. */
    /* THE TOOLS SPEAK OF A CORE AND A SOFT EDGE (13.11.2026: „Die
       Pinselgröße ist für mich der scharfe Bereich, der innere Kreis. Nicht
       die Gesamtgröße" — develop.js, brushCore, says the whole of it). A
       brush or a place is read as { core, soft } and written back into what
       is stored; „Größe" and Shift with the wheel set the core, „Weiche
       Kante" and Ctrl with the wheel the soft edge, and neither moves the
       other's slider. „Entfernen" is stored that way already. */
    function toolOf(x) {
        return x.mode === 'ai' ? { core: Number(x.radius) || 0, soft: Number(x.feather ?? 50) } : brushCore(Number(x.radius) || 0, x.feather ?? 50);
    }
    function toolInto(x, core, soft) {
        if (x.mode === 'ai') {
            x.radius = core;
            x.feather = Math.round(soft);
            return;
        }
        const o = brushOuter(core, soft, SPOT_RADIUS_MAX);
        x.radius = o.radius;
        x.feather = o.feather;
    }
    /* A brush kept with more soft edge than the tools can say — a share of
       100 had no core at all, and came from „Entfernen", whose 100 means
       something else (the brush is one for all three kinds) — gets its core
       back at the start: the outer circle stays, the edge reads 100. */
    for (const x of [spotBrush, brushTool]) {
        if (x.mode === 'ai') continue;
        const now = toolOf(x);
        toolInto(x, now.core, now.soft);
    }
    /**
     * A setting of „Ausbessern". 'radius' is the CORE's radius and 'feather'
     * the soft edge 0 … 100, in the tools' language (toolOf). `panel`: from
     * the sidebar's sliders, which reach a just-painted spot too; 'brush':
     * from the wheel, which reaches no spot at all — only the brush.
     */
    function spotSetting(key, v, panel = false) {
        // The method of a place is not the brush's business (spotMethodSet).
        if (key === 'radius') toolInto(spotBrush, v, toolOf(spotBrush).soft);
        else if (key === 'feather') toolInto(spotBrush, toolOf(spotBrush).core, v);
        else spotBrush[key] = v;
        settings.set('spotBrush', spotBrush);
        maskLinesQuiet = true;
        /* THE WHEEL SIZES THE BRUSH, NEVER A PLACE (13.11.2026:
           „sobald ich mit den Tasten, also mit Shift und … Control, und dem
           Mausrad die Größe ändere, nicht das zuletzt Gesetzte editieren,
           sondern nur die Pinselgröße verstellen. Wenn ich allerdings die
           Schieberegler verstelle, dann kann ich das zuletzt Gesetzte
           editieren"). Until then the wheel reached a place taken in hand. */
        let s = panel === 'brush' ? null : panel ? spotOf(spotChosen) : spotHeld();
        const shape = key === 'radius' || key === 'feather';
        // While the KI works on the place, its shape waits with it (aiFillCompute); the brush takes the value.
        if (s && shape && s.busy) s = null;
        if (s) {
            /* SIZE AND SOFT EDGE END „ENTFERNEN" (14.11.2026: „lösche
               ich irgendwas an dem, oder verändere ich irgendwas an der Maske,
               löschen sich die entfernten Pixel automatisch und wir springen
               automatisch in den Modus Angleichen. Damit wir das Live-Ergebnis
               sehen … Das Einzige, was man natürlich verstellen kann, ohne dass
               die Pixel gelöscht werden, ist die Deckkraft"). Until then a place
               shown as „Entfernen" kept its size and the slider sized only the
               brush — and the fills of a place sized under „Angleichen" were
               kept for coming back to. A fill was invented for exactly the
               place it covers; one cannot slide a size back onto the very
               number it had, so a changed place is computed anew when one goes
               back to „Entfernen" (spotMethodSet) — bigger, softer, as it now is.
               The opacity only lays the result over the picture and keeps them. */
            const left = shape && spotLeaveAi(s);
            if (key === 'radius') toolInto(s, v, toolOf(s).soft);
            else if (key === 'feather') toolInto(s, toolOf(s).core, v);
            else s[key] = v;
            if (shape) spotAiForget(s);
            developSoon();
            developQueue();
            if (left) spotPanelRedraw();
        }
        developHandles();
    }

    /** A place shown as „Entfernen" turned into „Angleichen" — sized, softened or moved (spotSetting). True when it was. */
    function spotLeaveAi(s) {
        if (s.mode !== 'ai') return false;
        const now = toolOf(s);
        s.mode = 'heal';
        toolInto(s, now.core, now.soft);
        // A place that never had a source gets one sought, as a new stroke does (spotMethodSet).
        if (!Number(s.dx) && !Number(s.dy)) Object.assign(s, spotSourceFor(s.path, s.radius, !!s.fill));
        spotAiForget(s);
        return true;
    }

    /* THE PANEL TELLS THE NEW METHOD — but not under a slider still held:
       drawn anew, the slider in hand would be another element and the drag
       would end. Held, the method's buttons are told at once and the panel
       is drawn when the button lets go. */
    function spotPanelRedraw() {
        if (!document.querySelector('.photo-spot-sliders input:active')) {
            developPanelDraw();
            return;
        }
        for (const b of document.querySelectorAll('.photo-spot-methods [data-spot-mode]')) {
            const on = b.dataset.spotMode === 'heal';
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
        for (const row of document.querySelectorAll('.photo-spot-model')) row.remove();
        window.addEventListener('pointerup', () => developPanelDraw(), { once: true });
    }

    /* THE INFO TAB (10.11.2026: „einen Info-Tab, der mir von dem
       Bild eben die EXIF-Daten anzeigt. Also Blende, Belichtungszeit und so
       weiter"). The database keeps only time, camera and place of a picture
       (photos.php); aperture, time, ISO and lens stand only in the FILE. So
       the tab reads the original's head — the first half megabyte, where
       EXIF and the frame size stand before the picture data — and breaks
       the download off there: a 20-MB original is not fetched to show
       twelve numbers. Kept per picture for the visit.

       WHAT IT CAN AND CANNOT FIND: a recording uploaded whole carries
       everything its camera wrote. A voucher photo is drawn anew on a
       canvas when it is shrunk (photos.js), and so is a recording shrunk on
       its way to the cloud (cloudsend.js); since 10.11.2026 the stamp
       writes aperture, time, ISO, focal length and lens back
       (exifstamp.js, shotOf). Pictures shrunk BEFORE that carry camera and
       moment at most, and the tab says that it has nothing more, instead
       of standing half empty without a word. */
    const infoKept = new Map();

    /* The PROMISE is kept, not its answer: the panel is drawn twice when the
       picture changes, and both drawings asked for the file while the first
       read was still under way. A failed read is forgotten, so the next
       visit tries again. */
    function infoRead(subject, cloud) {
        const key = (cloud ? 'c' : 'p') + subject.id;
        if (!infoKept.has(key)) {
            infoKept.set(key, infoFetch(subject, cloud).catch((e) => {
                infoKept.delete(key);
                throw e;
            }));
        }
        return infoKept.get(key);
    }

    async function infoFetch(subject, cloud) {
        // A recording the desk has kept (originalcache.js) is read from there — its head, without the network.
        const kept = await originalPeek(subject, cloud ? 'cloud' : 'photo');
        if (kept) {
            const buffer = await kept.slice(0, 512 * 1024).arrayBuffer();
            const meta = exif.read(buffer);
            return { meta, shot: exif.shot(buffer), size: exif.orientedSize(exif.dimensions(buffer), meta.orientation), bytes: kept.size };
        }
        const stop = new AbortController();
        const answer = await fetch(cloud
            ? `${host.start.baseUrl}pages/cloud_thumb.php?id=${subject.id}&original=1`
            : `${host.start.baseUrl}pages/photo.php?id=${subject.id}`, { credentials: 'same-origin', signal: stop.signal });
        if (!answer.ok) throw new Error(String(answer.status));
        const length = Number(answer.headers.get('Content-Length')) || null;
        const head = new Uint8Array(512 * 1024);
        let got = 0;
        const reader = answer.body.getReader();
        try {
            while (got < head.length) {
                const { done, value } = await reader.read();
                if (done) break;
                const take = Math.min(value.length, head.length - got);
                head.set(value.subarray(0, take), got);
                got += take;
            }
        } finally {
            stop.abort();
        }
        const buffer = head.buffer.slice(0, got);
        const meta = exif.read(buffer);
        return {
            meta,
            shot: exif.shot(buffer),
            size: exif.orientedSize(exif.dimensions(buffer), meta.orientation),
            bytes: length,
        };
    }

    function infoPanel() {
        const box = document.createElement('div');
        box.className = 'photo-develop-info';
        const subject = measureSubject();
        if (subject === null) return box;
        const cloud = cloudInWindow !== null;
        const draw = (info, failed) => {
            box.textContent = '';
            const num = (x, digits = 1) => x.toLocaleString(lang, { maximumFractionDigits: digits });
            const s = info?.shot ?? null;
            const m = info?.meta ?? null;

            /* THE FOUR THAT MAKE THE EXPOSURE stand large, as tiles, the
               way a camera's display shows them; everything else is a list. */
            const four = [];
            if (s?.fnumber) four.push([`f/${num(s.fnumber)}`, t('Blende')]);
            if (s?.exposure) {
                const e = s.exposure;
                four.push([e >= 1 || 1 / e < 2 ? `${num(e)} s` : `1/${Math.round(1 / e)} s`, t('Belichtungszeit')]);
            }
            if (s?.iso) four.push([String(Math.round(s.iso)), 'ISO']);
            if (s?.focal) four.push([`${num(s.focal)} mm`, t('Brennweite')]);
            if (four.length) {
                const tiles = document.createElement('div');
                tiles.className = 'photo-info-tiles';
                for (const [value, caption] of four) {
                    const tile = document.createElement('div');
                    const b = document.createElement('strong');
                    b.textContent = value;
                    const c = document.createElement('span');
                    c.textContent = caption;
                    tile.append(b, c);
                    tiles.append(tile);
                }
                box.append(tiles);
            }

            const rows = [];
            const row = (label, value) => { if (value) rows.push([label, value]); };
            row(t('Kamera'), m?.camera ?? (cloud ? null : subject.camera));
            row(t('Objektiv'), s?.lens);
            if (s?.focal35 && Math.round(s.focal35) !== Math.round(s.focal ?? 0)) row(t('Brennweite (Kleinbild)'), `${num(s.focal35, 0)} mm`);
            if (s && s.bias !== null) row(t('Belichtungskorrektur'), `${s.bias > 0 ? '+' : s.bias < 0 ? '−' : ''}${num(Math.abs(s.bias))} LW`);
            row(t('Programm'), s?.program ? [t('Manuell'), t('Programmautomatik'), t('Zeitautomatik'), t('Blendenautomatik'),
                t('Kreativprogramm'), t('Actionprogramm'), t('Porträt'), t('Landschaft')][s.program - 1] : null);
            row(t('Messung'), s?.metering ? [t('Mittelwert'), t('Mittenbetont'), t('Spot'), t('Mehrfach-Spot'),
                t('Mehrfeld'), t('Selektiv')][s.metering - 1] : null);
            row(t('Blitz'), s && s.flash !== null ? (s.flash ? t('ausgelöst') : t('nicht ausgelöst')) : null);
            row(t('Weißabgleich'), s && s.whiteBalance !== null ? (s.whiteBalance === 0 ? t('automatisch') : t('manuell')) : null);
            const taken = m?.taken_at ?? subject.taken_at ?? null;
            if (taken) {
                const d = new Date(taken.replace(' ', 'T'));
                row(t('Aufgenommen'), Number.isNaN(d.getTime()) ? taken
                    : d.toLocaleString(lang, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }));
            }
            const lat = m?.lat ?? subject.exif_lat ?? null, lon = m?.lon ?? subject.exif_lon ?? null;
            if (lat !== null && lon !== null) row(t('Koordinaten'), `${num(lat, 5)}, ${num(lon, 5)}`);
            row(t('Fotograf'), m?.artist);
            row(t('Copyright'), s?.copyright);
            row(t('Software'), s?.software);
            const w = info?.size?.width ?? subject.width, h = info?.size?.height ?? subject.height;
            if (w > 0 && h > 0) row(t('Abmessungen'), `${w} × ${h} px (${num(w * h / 1e6)} MP)`);
            const bytes = info?.bytes ?? (cloud ? subject.size : null);
            if (bytes > 0) row(t('Dateigröße'), cloudBytes(bytes));
            if (cloud) row(t('Datei'), subject.name);

            if (rows.length) {
                const list = document.createElement('dl');
                list.className = 'photo-info-list';
                for (const [label, value] of rows) {
                    const dt = document.createElement('dt');
                    dt.textContent = label;
                    const dd = document.createElement('dd');
                    dd.textContent = value;
                    list.append(dt, dd);
                }
                box.append(list);
            }
            /* The state, not an explanation: why the tiles are missing. */
            const say = info === null && !failed ? t('Wird gelesen …')
                : failed ? t('Die Datei konnte nicht gelesen werden.')
                : four.length === 0 ? t('In der Datei stehen keine Belichtungsdaten.') : '';
            if (say) {
                const p = document.createElement('p');
                p.className = 'hint-text small';
                p.textContent = say;
                box.append(p);
            }
        };
        draw(null, false);
        infoRead(subject, cloud).then(
            (info) => { if (box.isConnected && measureSubject() === subject) draw(info, false); },
            () => { if (box.isConnected && measureSubject() === subject) draw(null, true); },
        );
        return box;
    }

    function spotsPanel(may) {
        /* A chosen place shows its own size under every method — sized
           under „Entfernen" it turns into „Angleichen" (spotSetting). */
        const chosen = spotOf(spotChosen);
        // The sliders show what they change: the last place, just painted or taken in hand, else the brush.
        const held = spotOf(spotChosen);
        const cur = held ?? spotBrush;
        // In the tools' language: the core, and the soft edge beyond it (toolOf).
        const curTool = toolOf(cur);
        const curSize = curTool.core;
        const homeTool = brushCore(SPOT_BRUSH_HOME.radius, SPOT_BRUSH_HOME.feather);
        /* ONE TOOL (14.11.2026: „Reparieren und Entfernen
           zusammenlegen in ein Pinselwerkzeug. Man markiert eine Stelle,
           entscheidet zwischen den drei Methoden Angleichen, Kopieren oder
           Entfernen … Dann hätten wir nur noch ein Werkzeug"). Until then
           the rail carried two tools — „Reparieren" with its two kinds and
           „Entfernen" —, and a place was what its tool had made it. Now one
           paints, the place is mended at once as „Angleichen" („damit kann
           man schon viel ausbessern"), and the method is a choice at the
           place (spotKindRow): the KI is switched in where the mending does
           not do. So there is no rail here, and every place is drawn
           whatever its method (spotShown). The head names the tab — its
           sign on the tab bar carries no word. */
        const wrap = document.createElement('div');
        wrap.className = 'photo-develop-adjust is-single';
        const body = document.createElement('div');
        body.className = 'photo-develop-adjust-body photo-develop-spots';
        wrap.append(body);
        const page = { wrap, body };
        const toolHead = document.createElement('h3');
        toolHead.className = 'list-label photo-develop-group-head is-plain';
        toolHead.innerHTML = iconHtml('bandage', { class: 'photo-develop-head-sign' });
        toolHead.append(t('Ausbessern'));
        page.body.append(toolHead);
        const inner = page.body;
        /* THE SLIDERS IN A BOX OF THEIR OWN: only the pointer over THEM shows
           the chosen place (spotPanelShow) — not the rail, the list or the
           empty ground of the sidebar (13.11.2026: „wenn man im
           Reglerbereich ist, also wirklich nur den kleinen Bereich Regler,
           die 4 Regler, wenn man da drin ist, soll er erstmal wieder
           eingeblendet werden"). */
        const sliderBox = document.createElement('div');
        sliderBox.className = 'photo-spot-sliders';
        sliderBox.addEventListener('pointermove', () => {
            if (!spotPanelHover && developMode && developTab === 'spots' && spotOf(spotChosen) !== null) spotPanelShow(true);
        });
        sliderBox.addEventListener('pointerleave', () => spotPanelShow(false));
        inner.append(sliderBox);
        // The method — of the place chosen; without one there is nothing to choose it for.
        if (held) sliderBox.append(spotKindRow(held, may));
        sliderBox.append(
            developSlider({ key: 'size', name: () => t('Größe'), min: 0, max: 100, home: spotSizeOf(homeTool.core) },
                spotSizeOf(curSize || homeTool.core), (v) => spotSetting('radius', spotRadiusOf(v), true), may),
            developSlider({ key: 'feather', name: () => t('Weiche Kante'), min: 0, max: 100, home: Math.round(homeTool.soft) },
                Math.round(curTool.soft), (v) => spotSetting('feather', v, true), may),
            developSlider({ key: 'opacity', name: () => t('Deckkraft'), min: 0, max: 100, home: 100 },
                cur.opacity ?? 100, (v) => spotSetting('opacity', v, true), may));
        if (chosen && chosen.mode === 'ai' && may
            && (host.start.strongModel || (chosen.other ?? []).length || chosen.busy)) {
            inner.append(spotModelRow(chosen));
        }
        /* „DREHUNG" — the chosen heal's or clone's source turned
           (11.11.2026; develop.js spotSourceMap): a piece of stem taken where
           it stands at another angle. Only for a chosen spot — a new one
           starts straight —, and R held with the wheel does the same over the
           picture. A double click on its name sets it straight again. */
        /* WHILE THE AI STARTS, A BAR THAT MOVES (11.11.2026: „da
           hängt zeitweise der Browser … ich würde vorschlagen, so etwas wie
           einen Ladebalken einzublenden"). Once per visit, about fifteen
           seconds; the engine builds itself in a worker now (inpaint.js), so
           the page stays free and the bar really moves. It cannot say how far
           — onnxruntime tells nothing while it builds —, so it runs, and the
           words say what for and how long. */
        if (inpaint.engineNow() === 'starting') {
            const wait = document.createElement('div');
            wait.className = 'photo-ai-starting';
            wait.setAttribute('role', 'status');
            const bar = document.createElement('span');
            bar.className = 'photo-ai-starting-bar';
            const words = document.createElement('span');
            words.textContent = t('Die KI wird gestartet … (einmal je Besuch, etwa 15 Sekunden)');
            wait.append(bar, words);
            inner.append(wait);
        }
        if (chosen && chosen.mode !== 'ai') {
            sliderBox.append(developSlider({ key: 'rot', name: () => t('Drehung'), min: -180, max: 180, home: 0 },
                Number(chosen.rot) || 0, (v) => spotTurn(v), may));
        }
        const list = document.createElement('div');
        list.className = 'photo-develop-radial-list photo-spot-list';
        /* THE LIST THINS OUT AS THE PICTURE DOES (11.11.2026:
           „vielleicht machen wir es auch ebenso mit der Liste unten, dass wir
           die ausdünnen je nach Werkzeug"): the rows of the tool in hand, the
           chosen one and one still computing (spotShown). And so NUMBERED PER
           KIND — „Reparieren 1, 2 …", „Kopieren 1 …" —, or a thinned list
           would read „Reparieren 1, Reparieren 3". */
        // Counted per TOOL since „Angleichen" and „Kopieren" are one: a place switched keeps its number.
        spotsList().forEach((s, i) => {
            // ONE COUNT (14.11.2026): a place keeps its number through every method — „Angleichen 15" becomes „Entfernen 15".
            const n = i + 1;
            if (!spotShown(s, i)) return;
            const row = document.createElement('div');
            row.className = 'photo-mask-row' + (spotChosen === i ? ' is-active' : '') + (s.off ? ' is-off' : '');
            row.setAttribute('role', 'button');
            row.tabIndex = 0;
            const words = s.mode === 'clone' ? t('Kopieren %d', n)
                : s.mode === 'ai' ? t('Entfernen %d', n) : t('Angleichen %d', n);
            const name = document.createElement('span');
            name.className = 'photo-mask-name';
            name.textContent = words;
            if (s.busy) {
                const busy = document.createElement('span');
                busy.className = 'photo-spot-busy';
                busy.textContent = aiStarting ? t('KI startet …') : t('wird berechnet …');
                // On a line of its own under the name (photowindow.css) — behind the number it broke the row.
                name.append(busy);
            }
            const eye = document.createElement('button');
            eye.type = 'button';
            eye.className = 'button subtle small square photo-mask-eye';
            eye.disabled = !may;
            const eyeWords = s.off ? t('Einblenden') : t('Ausblenden');
            eye.title = eyeWords;
            eye.setAttribute('aria-label', eyeWords + ': ' + words);
            eye.setAttribute('aria-pressed', String(!s.off));
            eye.innerHTML = iconHtml(s.off ? 'eye-off' : 'eye');
            eye.addEventListener('click', (e) => {
                e.stopPropagation();
                if (s.off) delete s.off; else s.off = true;
                maskLinesQuiet = true;
                developSoon();
                developQueue();
                developPanelDraw();
                developHandles();
            });
            const bin = document.createElement('button');
            bin.type = 'button';
            bin.className = 'button subtle small square danger photo-mask-eye';
            bin.disabled = !may;
            bin.title = t('Stelle löschen');
            bin.setAttribute('aria-label', t('Stelle löschen') + ': ' + words);
            bin.innerHTML = iconHtml('delete');
            bin.addEventListener('click', (e) => {
                e.stopPropagation();
                spotDelete(i);
            });
            const choose = () => {
                spotChosen = spotChosen === i && !spotFresh ? null : i;
                spotFresh = false;
                maskLinesQuiet = false;
                /* Chosen in the list while zoomed elsewhere: the picture is
                   fitted, so the place one is about to change stands in
                   sight (spotSightKeep would otherwise let it go at the
                   next move, and until then it changed unseen). */
                const s = spotOf(spotChosen);
                if (s && !spotInSight(spotMiddle(spotPx(s).pts))) photoZoom.reset();
                developPanelDraw();
                developHandles();
            };
            row.addEventListener('click', choose);
            row.addEventListener('keydown', (e) => {
                if (e.target === row && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); choose(); }
            });
            // Not in the cloud yet (editsaves.js): the cloud with the mark, a press tries at once.
            if (spotUnsaved(s)) row.append(name, editUnsavedMark(), eye, bin);
            else row.append(name, eye, bin);
            /* THE NEWEST ON TOP (11.11.2026: „die Liste wird ja
               ziemlich lang. Wollen wir die Liste umkehren, dass die neuesten
               oben stehen?"): the place one is working on stands right under
               the sliders. Only the showing turns — the spots keep their order
               (a later one mends over an earlier one) and their numbers. */
            list.prepend(row);
        });
        inner.append(list);
        return page.wrap;
    }

    /* A PRESS ANYWHERE BESIDE THE PICTURE clears it of the masks' lines and
       the spots' frames (maskLinesQuiet) — the sidebar, the head, the rail
       (10.11.2026, first for „Ausbessern", then: „Die Ellipsen …
       Verschwinden noch nicht, wenn ich irgendwo auf einer leere Fläche
       außerhalb des Bildbereichs klicke … Nicht nur bei Reglern"). Not on
       the picture's own area, where the lines are worked; not on a row (to
       choose a mask or spot is to want to see it), a tab or the buttons
       that lay a new mask. */
    document.addEventListener('pointerdown', (e) => {
        if (!developMode || maskLinesQuiet || (developTab !== 'masks' && developTab !== 'spots')) return;
        if (photoFrame.contains(e.target)) return;
        if (e.target.closest?.('.photo-mask-row, [role="tab"], .photo-develop-handle, #photo-develop-add, #photo-develop-add-linear, #photo-develop-add-brush')) return;
        maskLinesQuiet = true;
        developHandles();
    }, true);

    /* A PRESS BESIDE THE PICTURE LETS THE CHOSEN SPOT GO
       (12.11.2026: „ich würde eine Auswahl aufheben, wenn man irgendwo
       außerhalb des Bildes in einen leeren Bereich … klickt. Also natürlich
       nicht auf einen Schieberegler, der jetzt genau das verstellt, aber
       überall woanders"). Kept only where the press acts on the chosen
       place: a slider's row, the kinds above them (they turn the chosen
       place into another), the rows of the list with their eye and bin, and
       a window that opens over the page (the bin's question). */
    document.addEventListener('pointerdown', (e) => {
        if (!developMode || developTab !== 'spots' || spotChosen === null || spotDrag || spotPaint) return;
        if (photoFrame.contains(e.target)) return;
        /* What belongs to the place chosen must not let it go: its sliders,
           the strong model's row (spotModelRow) — and the switch „Angleichen
           / Kopieren" (spotKindRow) WHOLE, its words and its row, not only
           the round button (14.11.2026: „ich tippe … Angleichen 15
           an … dann kann ich oben umschalten auf Kopieren, und dann ist der
           Angleichen 15 dann Kopieren 15"). A press on the word let the place
           go before the switch was told, and the place stayed what it was. */
        if (e.target.closest?.('.photo-develop-row, [data-spot-mode], .photo-mask-row, .photo-spot-model, .photo-spot-kind')) return;
        const over = e.target.closest?.('dialog');
        if (over && over !== photoWindow) return;
        spotChosen = null;
        spotFresh = false;
        developPanelDraw();
        developHandles();
    }, true);

    /* … AND THE CHOSEN MASK (13.11.2026: „die aktive Maske aus
       dem Bild dann doch ausblenden, wenn ich irgendwo außerhalb in einen
       leeren Raum klicke … egal wo man klickt. Lass sie verschwinden. Außer
       natürlich auf irgendwelche Schaltelemente"). Since the same day the
       mask in hand stands beside the picture too; a press on empty ground
       outside the picture's frame clears its lines away (maskBareSet,
       below) — inside the frame the click without a drag does the same
       (placeEnd, the turning zone, brushDown for a brush beyond its
       reach). Kept on every control: buttons, fields, sliders and their
       rows, tabs, the mask rows, the rail, the sidebar's edge, a window
       over the page. */
    document.addEventListener('pointerdown', (e) => {
        if (!developMode || developTab !== 'masks' || maskChosen === null) return;
        if (placeDrag || gripDrag || turnDrag || brushPaint) return;
        if (photoFrame.contains(e.target)) return;
        if (e.target.closest?.('button, a, input, select, textarea, label, [role="tab"], [role="button"], [contenteditable], .photo-develop-row, .photo-mask-row, .photo-develop-rail, .photo-develop-handle, .photo-develop-edge, .photo-develop-fold')) return;
        const over = e.target.closest?.('dialog');
        if (over && over !== photoWindow) return;
        maskBareSet(true);
    }, true);
    /* A CLICK ON EMPTY GROUND ONLY CLEARS THE VIEW (the same
       evening, after the press had let the mask go: „Der Klick soll nicht die
       Aktivität der Maske aufheben. Also ich will weiter die Schieberegler
       sehen … ich will nur, dass ich mal einen freien Blick auf das Bild
       bekomme. Ohne die Maske. Also ohne die Linien der Maske"). The mask
       stays chosen and its sliders stand; its lines and a brush's veil go
       (maskBare) — on empty ground beside the picture, on the black bars,
       and a click without a drag on the picture itself. They come back
       when the pointer comes onto the picture from outside, or a mask is
       chosen or made. */
    function maskBareSet(on) {
        if (maskBare === on) return;
        maskBare = on;
        developSoon();
        developHandles();
    }
    photoFrame.addEventListener('pointerenter', () => maskBareSet(false));

    function spotDelete(i) {
        if (!developMay() || !spotOf(i)) return;
        spotsList().splice(i, 1);
        if (spotChosen === i) spotChosen = null;
        else if (spotChosen !== null && spotChosen > i) spotChosen--;
        developSoon();
        developQueue();
        developPanelDraw();
        developHandles();
    }

    /** A spot's path and source offset in original pixels. */
    function spotPx(s) {
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        return { pts: s.path.map(([x, y]) => [x * W, y * H]), r: (s.radius ?? .01) * W, ox: (s.dx ?? 0) * W, oy: (s.dy ?? 0) * H };
    }
    const nearPath = (p, pts, r, ox = 0, oy = 0) => {
        for (let k = 0; k < pts.length; k++) {
            const a = pts[k], b = pts[Math.min(k + 1, pts.length - 1)];
            const ax = a[0] + ox, ay = a[1] + oy, vx = b[0] - a[0], vy = b[1] - a[1];
            const len = vx * vx + vy * vy;
            const t0 = len > 0 ? Math.max(0, Math.min(1, ((p[0] - ax) * vx + (p[1] - ay) * vy) / len)) : 0;
            if (Math.hypot(p[0] - ax - t0 * vx, p[1] - ay - t0 * vy) <= r) return true;
        }
        return false;
    };
    /** The kind „Reparieren" comes back with — the last one chosen, „Angleichen" at first. */
    /**
     * THE METHOD OF THE PLACE CHOSEN: three buttons at the head of its
     * sliders — „Angleichen", „Kopieren", „Entfernen" (a few options stand
     * in sight, CLAUDE.md). The digits 1, 2 and 3 press them (photoKeys).
     * What „Entfernen" adds — which of the two models, „Noch einmal" —
     * stands under the sliders (spotModelRow).
     */
    function spotKindRow(spot, may) {
        /* BUTTONS, NOT RADIOS (14.11.2026: „genauso als Button
           machen. Den aktiven umranden. Und auch einzeilig und immer volle
           Breite"), as „Hochladen / Mikroskop" over the Fundbrowser's
           picture rail: three buttons sharing the sidebar's width, the
           chosen one framed in the accent as the rails mark theirs, the
           sign and word, the word alone, the sign alone as room allows
           (wordsorsigns.js) — the signs the tool rail gave the methods. */
        const box = document.createElement('div');
        box.className = 'photo-spot-kind photo-spot-methods';
        box.setAttribute('role', 'group');
        box.setAttribute('aria-label', t('Methode'));
        for (const [value, sign, words, hint] of [
            ['heal', 'bandage', t('Angleichen'), t('Struktur der Quelle, Helligkeit und Farbe der Umgebung')],
            ['clone', 'copy', t('Kopieren'), t('Die Quelle genau so übernehmen')],
            ['ai', 'wand', t('Entfernen'), t('Die KI füllt die Stelle mit dem, was dahinter liegen könnte')],
        ]) {
            const b = document.createElement('button');
            b.type = 'button';
            const on = spot.mode === value;
            b.className = 'button subtle small' + (on ? ' is-active' : '');
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            b.title = `${words} — ${hint}`;
            b.innerHTML = iconHtml(sign);
            const span = document.createElement('span');
            span.className = 'photo-spot-method-word';
            span.textContent = words;
            b.append(span);
            // While the KI works on the place its method waits (aiFillCompute).
            b.disabled = !may || !!spot.busy;
            b.dataset.spotMode = value;
            b.addEventListener('click', () => { if (!on) spotMethodSet(spot, value); });
            box.append(b);
        }
        wordsOrSigns(box);
        return box;
    }

    /* A PLACE CHANGES ITS METHOD (14.11.2026: „manchmal
       funktioniert Angleichen, manchmal funktioniert Kopieren besser", and
       where neither does, the KI). What it keeps through every change: its
       path, what one sees of its size — the core and the soft edge, in each
       method's own numbers (toolOf, toolInto) —, its opacity, its source and
       turn, and THE KI'S FILLS: a place switched back to „Angleichen" keeps
       the patches of „Entfernen" beside it, so coming back computes nothing
       (his rule of the same morning for the two models: „ohne neu zu
       rechnen"). They go as soon as the place is moved, sized or softened,
       under any method — a fill was invented for exactly its place
       (spotAiForget); under „Entfernen" such a change turns the place into
       „Angleichen" (spotSetting, spotLeaveAi). The opacity keeps them.
         heal <-> clone   at once, as before.
         -> ai            with fills kept: at once (fetched first where they
                          are not here). Without: the quick model fills it —
                          the place stays what it is, pulsing, until the
                          fill is there (aiFillQueue), so a refusal or a
                          „Abbrechen" at the KI's question leaves it as it was.
         ai ->            at once; a place that never had a source gets one
                          sought, as a new stroke does. */
    function spotMethodSet(spot, mode) {
        if (!spot || spot.mode === mode || spot.busy || !developMay()) return;
        const done = () => {
            maskLinesQuiet = false;
            developSoon();
            developQueue();
            developPanelDraw();
            developHandles();
        };
        const shape = (to) => {
            const now = toolOf(spot);
            spot.mode = to;
            toolInto(spot, now.core, now.soft);
        };
        if (mode !== 'ai' && spot.mode !== 'ai') {
            spot.mode = mode;
            done();
            return;
        }
        if (mode !== 'ai') {
            shape(mode);
            if (!Number(spot.dx) && !Number(spot.dy)) Object.assign(spot, spotSourceFor(spot.path, spot.radius, !!spot.fill));
            done();
            return;
        }
        if (!(spot.pieces ?? []).length) {
            aiFillQueue(spot, false);
            return;
        }
        const subject = developOf;
        const ids = spot.pieces.map((piece) => piece.id);
        (ids.every((id) => aiPatchHas(id)) ? Promise.resolve() : aiPatchesFetch(ids, aiPatchUrl(developKey(subject)))).then(() => {
            if (developOf !== subject || !spotsList().includes(spot) || spot.mode === 'ai') return;
            if (!ids.every((id) => aiPatchHas(id))) {
                // The kept fill is gone from the cloud: made anew.
                spotAiForget(spot);
                aiFillQueue(spot, false);
                return;
            }
            shape('ai');
            done();
        });
    }

    /** The KI's fills of a place let go — it was moved or sized, and they were invented for where it lay. */
    function spotAiForget(spot) {
        if (spot.mode === 'ai') return;
        delete spot.pieces;
        delete spot.other;
        delete spot.strong;
    }

    /** A source for a place, sought as for a new stroke: a like piece nearby, inside what is shown. */
    function spotSourceFor(path, radius, fill) {
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        const px = spotPixelsGet();
        const wide = fill ? Math.max(...path.map((q) => q[0])) - Math.min(...path.map((q) => q[0])) : 0;
        // The cut, so the source is sought where it can be seen and taken (spotSourceFind, `within`).
        const view = pictureView ? [[0, 0], [pictureView.cw, 0], [pictureView.cw, pictureView.ch], [0, pictureView.ch]]
            .flatMap((q) => { const [x, y] = pictureView.toNat(q); return [x / W, y / H]; }) : null;
        return px ? spotSourceFind(px, path, radius, fill, view)
            : { dx: Math.round((wide + radius * 2.5) * 10000) / 10000, dy: 0 };
    }

    /* THE STRONG MODEL, FOR THE PLACE CHOSEN (14.11.2026, asked
       how the two models should be offered: „Ein Werkzeug. Erst wird LaMa
       angezeigt und gerechnet, dann bekommt man die Option, wenn einem das
       nicht gefällt, das starke Modell zu nehmen. Beide Pixelbilder werden
       gespeichert, sodass man evtl. doch wieder zurück zu LaMa springen
       kann, wenn man will, ohne neu zu rechnen"). So „Entfernen" stays one
       tool and always fills with the quick model first; this row stands
       under the sliders of the place chosen and says the rest:
         - never filled by the strong model: ONE button that does it;
         - filled by both: the two as a choice in sight — switching only
           changes which fill is shown (develop.js, aiSpotSwitch) —, and
           „Noch einmal", because the strong model's fill is another one
           every time and now and then invents a thing nobody wants;
         - while it computes: how far it is. Half a minute and more is too
           long for a ring alone.
       Offered only where the server has the model's files (the page's
       strongModel); a place that already carries both fills keeps its
       choice even where it does not. NO EXPLANATION IN THE SIDEBAR
       (09.11.2026): labels, and the rest in the tooltips. */
    function spotModelRow(spot) {
        const box = document.createElement('div');
        box.className = 'photo-spot-model';
        if (spot.busy) {
            const words = document.createElement('span');
            words.className = 'photo-spot-model-busy';
            words.setAttribute('role', 'status');
            words.dataset.strongBusy = '1';
            words.textContent = spotBusyWords(spot);
            box.append(words);
            return box;
        }
        const both = (spot.other ?? []).length > 0;
        if (both) {
            const choice = document.createElement('fieldset');
            choice.className = 'field photo-spot-kind';
            choice.setAttribute('aria-label', t('Füllung'));
            for (const [strong, words, hint] of [
                [false, t('Schnell'), t('Die Füllung des schnellen Modells zeigen')],
                [true, t('Stark'), t('Die Füllung des starken Modells zeigen')],
            ]) {
                const row = document.createElement('label');
                row.className = 'choice-row';
                row.title = hint;
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'photo-spot-model';
                input.checked = !!spot.strong === strong;
                input.addEventListener('change', () => { if (input.checked) spotModelSwitch(spot, strong); });
                const span = document.createElement('span');
                span.textContent = words;
                row.append(input, span);
                choice.append(row);
            }
            box.append(choice);
        }
        if (host.start.strongModel) {
            const go = document.createElement('button');
            go.type = 'button';
            go.className = 'button subtle small' + (both ? ' square' : '');
            const words = both ? t('Noch einmal') : t('Mit starkem Modell füllen');
            go.title = both
                ? t('Das starke Modell füllt die Stelle noch einmal – jedes Mal anders. Die schnelle Füllung bleibt aufgehoben.')
                : t('Füllt die Stelle noch einmal mit dem starken Modell. Das dauert etwa eine halbe bis ganze Minute. Die schnelle Füllung bleibt aufgehoben.');
            go.setAttribute('aria-label', words);
            go.innerHTML = iconHtml(both ? 'refresh' : 'wand');
            if (!both) go.append(words);
            go.addEventListener('click', () => aiFillQueue(spot, true));
            box.append(go);
        }
        return box;
    }

    /** How far the strong model is with a place — the words under its sliders. */
    function spotBusyWords(spot) {
        const b = spot.busy ?? {};
        if (!(b.of > 0)) return t('Starkes Modell wird gestartet …');
        /* THE SQUARE AND THE STEP APART (14.11.2026, after 171
           steps where he remembered 19: „genau das wäre das Richtige"). A
           long stroke is cut into several squares (develop.js, aiPieces),
           each of them denoised in 19 steps (moebius.js, RUNS); one count
           over all of them read as if the model had grown slower. The 19
           stay for long strokes too („dann dauert es eben entsprechend
           länger"). A place of one square keeps the short words. */
        return b.pieces > 1
            ? t('Starkes Modell rechnet … Ausschnitt %d von %d · Schritt %d von %d', b.piece, b.pieces, b.step, b.of)
            : t('Starkes Modell rechnet … Schritt %d von %d', b.step, b.of);
    }

    /** The other fill of a place in the shown one's stead — fetched first where it is not here yet. */
    async function spotModelSwitch(spot, strong) {
        if (!!spot.strong === strong || !(spot.other ?? []).length || spot.busy) return;
        const subject = developOf;
        const ids = spot.other.map((piece) => piece.id);
        if (!ids.every((id) => aiPatchHas(id))) await aiPatchesFetch(ids, aiPatchUrl(developKey(subject)));
        if (developOf !== subject || !spotsList().includes(spot)) return;
        if (!ids.every((id) => aiPatchHas(id))) {
            host.status(t('Die andere Füllung ließ sich nicht laden.'), true);
            developPanelDraw();
            return;
        }
        aiSpotSwitch(spot);
        developSoon();
        developQueue();
        developPanelDraw();
        developHandles();
    }

    /** What a press at p takes hold of: the chosen spot's source or region, or another spot's pin. */
    /* ONLY THE PLACES OF THE TOOL IN HAND ARE DRAWN (11.11.2026:
       „bin ich im Modus Entfernen, sehe ich auch die Ursprungspunkte anderer
       Retuschierarbeiten … man zeigt hier immer nur die Punkte des Werkzeugs,
       in dem man sich befindet"): in „Entfernen" the AI's places, in
       „Reparieren" the heals and the clones together (one tool since
       14.11.2026). The tool in hand is
       the spot taken in hand's kind, else the brush's. Always drawn: the
       chosen place, and a place the AI is still computing — its ring says
       the work goes on. The list beside keeps them all. */
    function spotShown() {
        /* EVERY PLACE, since „Ausbessern" is one tool (14.11.2026). Until
           then only the places of the tool in hand were drawn
           (11.11.2026: in „Entfernen" the others' pins were in the way) —
           there is no other tool any more for a place to belong to. */
        return true;
    }

    /** The chosen spot's source turned to `deg` degrees — from the slider or from R and the wheel. */
    function spotTurn(deg) {
        const s = spotOf(spotChosen);
        if (!s || s.mode === 'ai' || !developMay()) return;
        const v = Math.round(((deg + 540) % 360 - 180) * 10) / 10;
        if (v) s.rot = v; else delete s.rot;
        developSoon();
        developQueue();
        developHandles();
    }

    /** Where a heal's or clone's source lies, point for point, in picture pixels — turned when the spot is (spotSourceMap). */
    function spotSourcePts(s) {
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        const map = spotSourceMap(s.path, W, H, s.dx, s.dy, s.rot);
        return s.path.map(([x, y]) => map(x * W, y * H));
    }

    /* --- IN SIGHT (11.11.2026) -------------------------------------
       What the eye can check is what may be changed. The part of the frame
       the picture covers — the cut, and zoomed in only what the frame still
       shows — in screen pixels; null before the picture has a size. */
    function spotSight() {
        const a = photoImageEl.getBoundingClientRect(), b = photoFrame.getBoundingClientRect();
        const r = { left: Math.max(a.left, b.left), top: Math.max(a.top, b.top), right: Math.min(a.right, b.right), bottom: Math.min(a.bottom, b.bottom) };
        return r.right - r.left > 1 && r.bottom - r.top > 1 ? r : null;
    }
    /** The middle of a run of points' box. */
    const spotMiddle = (pts) => {
        const xs = pts.map((q) => q[0]), ys = pts.map((q) => q[1]);
        return [(Math.min(...xs) + Math.max(...xs)) / 2, (Math.min(...ys) + Math.max(...ys)) / 2];
    };
    function spotInSight(p, sight = spotSight()) {
        if (!sight) return true;
        const [x, y] = naturalClient(p);
        return x >= sight.left && x <= sight.right && y >= sight.top && y <= sight.bottom;
    }

    /* THE CHOSEN PLACE IS LET GO WHEN IT LEAVES THE SIGHT
       (11.11.2026: „wenn ich das Bild vergrößert anschaue, und ich zur Seite
       wegschiebe, ich gar nicht sehe, wenn ich einen Regler verschiebe …
       diese Auswahl … beenden, wenn sie nicht mehr im sichtbaren Bereich
       ist"). Taken in hand, a place takes every slider; zoomed and moved
       away, a slider set for the next place would change one nobody sees.
       So the moment its MIDDLE leaves what is shown, it is let go and the
       sliders are the brush's again — the middle and not the last sliver,
       which would still change unseen. Only on a move of the view, never
       on the way back: a place that comes into sight again stays let go
       until it is clicked. Its source does not count — it may lie far. */
    function spotSightKeep() {
        if (!photoWindow.open || !developMode || developTab !== 'spots' || spotChosen === null || spotDrag || spotPaint) return;
        const s = spotOf(spotChosen);
        if (!s || spotInSight(spotMiddle(spotPx(s).pts))) return;
        spotChosen = null;
        spotFresh = false;
        developPanelDraw();
    }

    /* A SOURCE OUT OF SIGHT IS REACHED BY A MARK AT THE RIM
       (11.11.2026, after a crop had left a source outside: „ich komme gar
       nicht mehr an die Quelle, um sie zu verschieben"). Where the chosen
       place's source lies beyond what is shown — outside the cut, or
       zoomed past —, a pink mark stands at the rim of the sight, on the
       line from the place towards its source. Taken, it pulls the source
       under the pointer, and the drag goes on as on the source itself;
       left alone, nothing changes. Null where there is no such source;
       else the mark's place and the source's middle, in picture pixels. */
    function spotSourceMark() {
        const s = spotOf(spotChosen);
        if (!s || s.mode === 'ai' || !developMode || developTab !== 'spots') return null;
        const sight = spotSight();
        if (!sight) return null;
        const src = spotMiddle(spotSourcePts(s));
        if (spotInSight(src, sight)) return null;
        const [sx, sy] = naturalClient(src);
        let [ox, oy] = naturalClient(spotMiddle(spotPx(s).pts));
        const inset = 18;
        const box = { l: sight.left + inset, t: sight.top + inset, r: sight.right - inset, b: sight.bottom - inset };
        if (box.r <= box.l || box.b <= box.t) return null;
        // From the place (or, should it be out of sight too, the middle of the sight) to where that line leaves the sight.
        if (ox < box.l || ox > box.r || oy < box.t || oy > box.b) { ox = (box.l + box.r) / 2; oy = (box.t + box.b) / 2; }
        const dx = sx - ox, dy = sy - oy;
        const tx = dx > 0 ? (box.r - ox) / dx : dx < 0 ? (box.l - ox) / dx : Infinity;
        const ty = dy > 0 ? (box.b - oy) / dy : dy < 0 ? (box.t - oy) / dy : Infinity;
        const k = Math.min(tx, ty, 1);
        const at = clientNatural(ox + dx * k, oy + dy * k);
        return at ? { p: at.p, src } : null;
    }

    /* NO LONGER: THE CHOSEN PLACE STAYED. On 13.11.2026 the maintainer took it
       back („das gefällt mir doch nicht … sobald ich außerhalb des Bildes
       bin, diese auch mit ausblenden … ich weiß gar nicht, warum ich die
       angezeigt haben wollte … wir machen wieder einen ganz normalen
       Modus, ähnlich der … kleinen weißen Punkte"): the chosen place — the
       one just painted or picked — shows its outline, source and line
       while the pointer is over the picture, and steps aside with every
       other line (maskLinesQuiet) the moment it leaves. Only a place the AI
       is still filling stays. The fade while a slider moves (below) is
       thereby mostly idle — a hand on a slider is a pointer beside the
       picture — and kept for a value that comes by key or wheel.
       What the rule said until then: */
    /* THE CHOSEN PLACE STAYS (spotChosenStays — 12.11.2026,
       the same afternoon: „Ich bin im Bild. Klicke … auf den kleinen
       weißen Punkt. Die Änderung ist aktiv. Ich sehe deren Umriss. Wenn
       ich jetzt in die Sidebar gehe, sollen mir alle weißen Punkte
       ausgeblendet werden, aber diese aktive Änderung bleibt
       eingeblendet"). With the lines stepped aside, the place in hand
       keeps its outline, source, line and soft edge — and the mark of a
       source out of sight —, because the sidebar's sliders now change
       exactly that one. The other places' pins and the brush's ring go.

       WHAT SHOWS FOLLOWS THE RULE OF THE MASKS again
       (12.11.2026: „Wenn ich in der Sidebar bin, sollen sie ausgeblendet
       werden. Wenn ich im Bild bin, sollen sie eingeblendet sein"). On
       11.11.2026 „Ausbessern" had it turned round — everything standing
       while the pointer was beside the picture, stepping aside on it and
       coming back only where the pointer was (spotRevealAt). That went
       again: the places, their sources, the pins and the mark of a source
       out of sight stand while the pointer is over the picture, and step
       aside with maskLinesQuiet like the masks' lines. A place the AI is
       still filling stays in either case. */

    /* WHILE A SLIDER MOVES, THE CHOSEN PLACE STEPS ASIDE TOO
       (12.11.2026, right after spotChosenStays: „sobald ich einen Regler
       schiebe, soll sie temporär solange ich den Regler schiebe, plus noch
       eine kleine Verzögerung ausgeblendet werden … ich will die aktive
       Markierung sehen und will sie aber auch gleichzeitig nicht sehen, um
       einen freien Blick auf das Bild zu haben"). From the press on a
       slider of „Ausbessern" — or a value it takes by key or wheel — the
       place fades out; SPOT_SLIDE_REST after the slider was let go (or
       took its last value) it fades in again. Held still with the button
       down, it stays hidden: the hand is still on the slider.
       A FADE, NOT A CUT (the same hour: „Du kannst ja so ein
       schnelles Ausfaden machen und ein Einfaden danach"): the chosen
       place is drawn in a group of its own (.photo-spot-chosen, spotsDraw),
       and only a class on the overlay (is-spot-sliding) moves — no redraw,
       so the stylesheet's transition runs undisturbed (photowindow.css). */
    // 1.5 s, not 0.7: „wird zu kurz sein. Locker mal ein, 2 Sekunden" (12.11.2026).
    const SPOT_SLIDE_REST = 1500;

    /* THE FRAME ROUND THE PLACE FADES WHILE ITS SOURCE IS DRAGGED
       (12.11.2026: „Wenn ich die Quelle anfasse und verschiebe, soll der
       Rahmen um das Ziel auch ausgeblendet werden. Und danach beim
       Loslassen wieder eingeblendet") — so one sees what the source lays
       there. Out from the first move (0.25 s), in on letting go (0.7 s, like
       the slider's fade). The overlay is drawn anew on every move, so a
       transition would start over each time; an ANIMATION with a negative
       delay — the time already gone — carries on across the redraws
       instead (photo-spot-fade-out/-in in photowindow.css). */
    /* Out quicker than in (12.11.2026: „den Rahmen etwas
       schneller ausblenden am Anfang. Da ist die Verzögerung zu lang"). */
    const SPOT_TARGET_FADE_OUT = 250;
    const SPOT_TARGET_FADE_IN = 700;
    function spotTargetFadeStyle() {
        if (!spotTargetFade) return {};
        const gone = performance.now() - spotTargetFade.at;
        const span = spotTargetFade.out ? SPOT_TARGET_FADE_OUT : SPOT_TARGET_FADE_IN;
        if (!spotTargetFade.out && gone >= span) {
            spotTargetFade = null;
            return {};
        }
        // ease-in-out, because it is symmetric: let go mid-fade, the way in starts exactly where the way out stood.
        return { style: `animation: photo-spot-fade-${spotTargetFade.out ? 'out' : 'in'} ${span}ms ease-in-out ${-Math.round(gone)}ms both` };
    }
    /* THE SIZE SLIDER LETS THE FRAME STAND A MOMENT (13.11.2026:
       „das Ausblenden des Rahmens, während ich den Größe-Schieberegler
       verstelle, sollte etwas langsamer sein, dass man erstmal so ein
       bisschen schauen kann, ein Verhältnis hat. Aber danach wird er
       trotzdem ausgeblendet, wenn ich länger am Schieberegler bin"). The
       size is the one slider whose effect IS the frame: it grows and
       shrinks with it. So its fade waits SPOT_SIZE_LOOK, and runs slower
       (is-spot-slow, photowindow.css); let go before, and it never went.
       Every other slider fades at once, as before. */
    const SPOT_SIZE_LOOK = 1000;
    let spotSizeTimer = 0;
    function spotSlideRest() {
        clearTimeout(spotSlideTimer);
        clearTimeout(spotSizeTimer);
        spotSlideTimer = setTimeout(() => {
            if (spotSlideHeld || !spotSliding) return;
            spotSliding = false;
            developSvg.classList.remove('is-spot-sliding', 'is-spot-slow');
        }, SPOT_SLIDE_REST);
    }
    function spotSlideStart(key) {
        if (!developMode || developTab !== 'spots') return;
        clearTimeout(spotSlideTimer);
        if (spotSliding) return;
        spotSliding = true;
        if (key === 'size') {
            clearTimeout(spotSizeTimer);
            spotSizeTimer = setTimeout(() => {
                if (spotSliding) developSvg.classList.add('is-spot-sliding', 'is-spot-slow');
            }, SPOT_SIZE_LOOK);
            return;
        }
        developSvg.classList.add('is-spot-sliding');
    }
    /* OVER THE SLIDERS THE CHOSEN PLACE COMES BACK (13.11.2026,
       an hour after taking spotChosenStays back: „Jetzt weiß ich, warum
       ich diesen Rahmen überhaupt haben wollte … wenn er jetzt in der
       Sidebar ausgeblendet ist und ich zu einem Regler navigiere, sehe ich
       gar nicht, wo dieser Rahmen überhaupt sitzt … für die Fläche der
       Schieberegler den aktiven Rahmen wieder kurz einblenden und dann …
       beim Schieberegler verstellen, wieder ausblenden … und dann, wenn
       man aber weiter mit der Maus auf der Schiebereglerfläche ist, wird
       der Rahmen wieder eingeblendet, sanft"). Anywhere else beside the
       picture the place steps aside with every line; over the panel of
       „Ausbessern" it fades in — outline, source, line, the mark of a
       source out of sight, nothing of the other places —, steps aside
       while a slider moves (spotSliding, as before) and fades in again
       SPOT_SLIDE_REST after, as long as the pointer stays there. The fade
       in is the same transition: the group starts hidden by the sliding
       class and loses it two frames later. */
    function spotPanelShow(on) {
        if (on === spotPanelHover) return;
        spotPanelHover = on;
        // Still standing from the picture (the grace above): it stays as it is, no fade out and in again.
        if (on && !maskLinesQuiet) {
            clearTimeout(linesQuietTimer);
            maskLinesQuiet = true;
            developHandles();
            return;
        }
        if (on && !spotSliding) {
            developSvg.classList.add('is-spot-sliding');
            developHandles();
            requestAnimationFrame(() => requestAnimationFrame(() => {
                if (spotPanelHover && !spotSliding) developSvg.classList.remove('is-spot-sliding');
            }));
            return;
        }
        developHandles();
    }
    // On every move, not only on entering: a place chosen from the panel's list shows at once.
    // The pointer over the sliders' box itself shows it (spotsPanel, .photo-spot-sliders).
    const spotSliderOf = (e) => e.target instanceof HTMLInputElement && e.target.type === 'range' && developSliders.contains(e.target);
    developSliders.addEventListener('pointerdown', (e) => {
        if (!spotSliderOf(e)) return;
        spotSlideHeld = true;
        spotSlideStart(e.target.dataset.key);
    });
    document.addEventListener('pointerup', () => {
        if (!spotSlideHeld) return;
        spotSlideHeld = false;
        spotSlideRest();
    });
    document.addEventListener('pointercancel', () => {
        if (!spotSlideHeld) return;
        spotSlideHeld = false;
        spotSlideRest();
    });
    developSliders.addEventListener('input', (e) => {
        if (!spotSliderOf(e)) return;
        spotSlideStart(e.target.dataset.key);
        if (!spotSlideHeld) spotSlideRest();
    });


    function spotHit(p) {
        const s = spotOf(spotChosen);
        // The mark of a source out of sight first: it lies at the rim, over whatever is there.
        const mark = spotSourceMark();
        if (mark && Math.hypot(p[0] - mark.p[0], p[1] - mark.p[1]) <= screenUnits(14)) return { i: spotChosen, part: 'source', pull: mark };
        if (s) {
            const { pts, r } = spotPx(s);
            /* An AI spot has no source and stays where its patch belongs:
               a press on it paints the next stroke — over a place the AI
               left imperfect is exactly where one paints again. */
            if (s.mode !== 'ai') {
                // A place drawn round is grabbed anywhere on its area, not only on its outline.
                const src = spotSourcePts(s);
                const within = (shape) => s.fill && pointInPath(p[0], p[1], shape.flat());
                if (nearPath(p, src, r) || within(src)) return { i: spotChosen, part: 'source' };
                if (nearPath(p, pts, r) || within(pts)) return { i: spotChosen, part: 'target' };
            }
        }
        const i = spotPinAt(p);
        return i === null ? null : { i, part: 'target' };
    }

    /** The place whose white pin lies under p — the chosen one has no pin. */
    function spotPinAt(p) {
        const pin = screenUnits(8);
        for (let i = spotsList().length - 1; i >= 0; i--) {
            // Only what is drawn can be taken: a hidden pin is no place to press.
            if (i === spotChosen || !spotShown(spotsList()[i], i)) continue;
            const { pts } = spotPx(spotsList()[i]);
            if (Math.hypot(p[0] - pts[0][0], p[1] - pts[0][1]) <= pin) return i;
        }
        return null;
    }

    /** The small copy of the original the source is chosen on — made once per picture. */
    function spotPixelsGet() {
        const src = photoImageEl.currentSrc || photoImageEl.src;
        if (spotPixels?.src !== src) {
            const got = wbPixels();
            spotPixels = { src, px: got ? { data: got.data, width: got.w, height: got.h } : null };
        }
        return spotPixels.px;
    }

    /** A painted path made lean: the points that change its course, no closer than a fifth of the radius. */
    function spotSimplify(pts, tol) {
        if (pts.length < 3) return pts;
        const keep = new Array(pts.length).fill(false);
        keep[0] = keep[pts.length - 1] = true;
        const stack = [[0, pts.length - 1]];
        while (stack.length) {
            const [a, b] = stack.pop();
            let far = -1, at = -1;
            const [ax, ay] = pts[a], [bx, by] = pts[b];
            const len = Math.hypot(bx - ax, by - ay) || 1;
            for (let k = a + 1; k < b; k++) {
                const d = Math.abs((bx - ax) * (ay - pts[k][1]) - (ax - pts[k][0]) * (by - ay)) / len;
                if (d > far) { far = d; at = k; }
            }
            if (far > tol) {
                keep[at] = true;
                stack.push([a, at], [at, b]);
            }
        }
        return pts.filter((_, k) => keep[k]);
    }

    function spotFinish() {
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        const r = spotBrush.radius * W;
        let pts = spotPaint.path;
        spotPaint = null;
        // A short wobble is a click: one point.
        const reach = pts.reduce((m, p) => Math.max(m, Math.hypot(p[0] - pts[0][0], p[1] - pts[0][1])), 0);
        pts = reach < Math.max(r * .3, screenUnits(4)) ? [pts[0]] : spotSimplify(pts, Math.max(.5, r * .15));
        const used = spotsList().reduce((n, s) => n + s.path.length, 0);
        if (spotsList().length >= SPOTS_MAX || used + pts.length > PATH_POINTS_MAX) {
            host.status(t('Mehr Stellen lassen sich an diesem Bild nicht ausbessern.'), true);
            developHandles();
            return;
        }
        const path = pts.map(([x, y]) => [Math.round(x / W * 10000) / 10000, Math.round(y / H * 10000) / 10000]);
        /* „Reparieren" drawn round its thing mends the inside too
           (11.11.2026, after „Entfernen" learnt it) — closed like there, and
           its source sought clear of the whole area. „KOPIEREN" THE SAME
           (11.11.2026: „Der Pinsel beim Kopieren schließt nicht
           automatisch bei einer Umrandung") — first left a stroke because
           nobody had asked; a hand going round a thing means the thing, in
           all three kinds alike. */
        const fill = spotLoopCloses(pts, spotBrush.radius * W);
        if (fill) path.push([...path[0]]);
        const source = spotSourceFor(path, spotBrush.radius, fill);
        spotsList().push({ mode: 'heal', path, radius: spotBrush.radius, feather: spotBrush.feather,
            opacity: spotBrush.opacity, ...source, ...(fill ? { fill: true } : {}) });
        spotChosen = spotsList().length - 1;
            spotFresh = true;
        developSoon();
        developQueue();
        developPanelDraw();
        developHandles();
    }

    /* --- „ENTFERNEN" — the AI fills the place (11.11.2026) ----------------
       The third kind beside „Reparieren" and „Kopieren": the painted place
       goes to the model (inpaint.js) and comes back filled with what could
       lie behind it. develop.js says what an AI spot is and why it carries
       pixels; here it is made.

       ONE AFTER THE OTHER (aiChain): a second stroke painted while the
       first is still computing waits for it, because it may lie on what
       the first one fills, and the model must see that filled.

       THE ORIGINAL, NOT THE PREVIEW. A patch is made once and kept; cut
       from a preview it would stay a preview's sharpness for ever. So a
       cloud picture still shown as its preview loads its original first —
       the same press as on the badge, made for the user. */
    /* IT GOES ON WHILE ONE WORKS ELSEWHERE (11.11.2026: „man
       wartet doch sehr, sehr lange … dass ich weiter navigieren kann und
       die KI trotzdem weiterrechnet — zum Beispiel mit dem Stempel in
       einem anderen Bild"). Every place is a JOB that carries what it
       needs itself: its picture (whose original it fetches on its own
       when the window shows another), the find and kind to store it to,
       and the places that lay before it. Leaving the picture, closing
       the window, opening another find in the Fundbrowser — the job goes
       on; finished away it is added to its picture's stored settings and
       saved (aiAway), and the thumbnails follow. Coming back while it
       runs, the place stands there again with its ring (aiJobsEnter).
       Only leaving the PAGE ends it (aiBusy below). */
    let aiChain = Promise.resolve();
    let aiRan = false;              // has the engine answered once on this visit
    let aiStarting = false;         // the first place waits for the engine to start (about 14 s)
    const aiJobs = new Set();       // {spot, subject, cloud, pointId, key, dropped?} — queued or computing

    // The bar in „Ausbessern" comes and goes with the engine's start (spotsPanel).
    inpaint.onEngine(() => { if (developMode && developTab === 'spots') developPanelDraw(); });

    /* „BEARBEITEN" WORKS ON THE ORIGINAL, SO IT FETCHES IT
       (13.11.2026: „Wenn man bei den Bildern in den Bearbeitungsmodus
       wechselt, soll automatisch das Originalbild geladen werden"). A
       recording opens on its preview — quick for looking and measuring —
       and its badge offered the original only on a click; in „Bearbeiten"
       one judges grain, sharpness and colour, and a preview misleads all
       three. Pressed once per recording: a failed load leaves the badge's
       „noch einmal versuchen" to the user, so a redraw does not keep
       asking a cloud that just refused. Voucher photos come whole anyway.
       NOT ONLY ON THE SWITCH (his second word the same day: „Bearbeiten
       schon vorausgewählt sein kann … wenn überhaupt der Bildbetrachter
       geladen wird"): it runs from developPanelDraw, and every recording
       opened or stepped to in „Bearbeiten" passes there — on the subject's
       change and again on the picture's load. Early in an opening the
       badge may still be the last recording's; it waits for its own. */
    function developOriginalFetch() {
        const f = cloudInWindow;
        if (f === null || cloudOriginalLoaded || developOriginalAsked === f) return;
        const badge = document.getElementById('photo-quality');
        if (!badge || badge.disabled || badge.hidden || badge.dataset.of !== String(f.id)) return;
        developOriginalAsked = f;
        badge.click();
    }

    /** The original in the window, if it can be had; false when it did not come. */
    async function aiOriginal() {
        const f = cloudInWindow;
        const shown = () => photoImageEl.complete && photoImageEl.naturalWidth > 0;
        if (f === null || cloudOriginalLoaded) return shown();
        const badge = document.getElementById('photo-quality');
        if (badge && !badge.disabled && !badge.hidden) badge.click();
        const until = Date.now() + 180000;
        while (cloudInWindow === f && Date.now() < until) {
            if (cloudOriginalLoaded && shown()) return true;
            // The badge offering itself again means the original failed („noch einmal versuchen").
            if (!cloudOriginalLoaded && badge && !badge.disabled && !badge.hidden) return false;
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        return false;
    }

    /** A patch's name — random, so two devices never pick the same one. */
    function aiId() {
        const bytes = crypto.getRandomValues(new Uint8Array(8));
        return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    }

    /* LEAVING THE PAGE ENDS THE WORK: the model runs in this page, and a
       place still computing is in no store yet — it would be gone. So
       while one is, the browser asks before the page goes (its own
       „Seite verlassen?", the only question a page may put there). */
    let aiBusy = 0;
    window.addEventListener('beforeunload', (e) => {
        /* AND WHILE AN EDIT IS NOT IN THE CLOUD (14.11.2026,
           point 3): it is kept on the device (editsaves.js) — but a browser
           without that store keeps it only as long as the page, and a save
           still waiting its moment (developSave) is in no store at all. */
        if (aiBusy > 0 || editSavesWaiting() || developSave) { e.preventDefault(); e.returnValue = ''; }
    });

    /* POINT 4 — WHAT THE LAST VISIT LEFT comes back: its patches into the
       page (unstored, so the next try takes them along), the thumbnails show
       the newer state, and the trying starts. */
    if (host.start?.user?.id) {
        editSavesStart({
            user: host.start.user.id,
            send: (entry) => editSave(entry, false),
            async restorePatch(id, png) {
                try {
                    const bytes = Uint8Array.from(atob(png), (c) => c.charCodeAt(0));
                    aiPatchKeep(id, await createImageBitmap(new Blob([bytes], { type: 'image/png' })), false);
                } catch {
                    // A patch that cannot be read again: its place stays unfilled until the cloud has it.
                }
            },
            changed() {
                // In the Fundbrowser the viewer stands in the page, not in an open dialog: the picture open is what counts.
                if (!developOf || !developMode) return;
                developPanelDraw();
                versionsDraw();
            },
        }).then((back) => {
            for (const entry of back) {
                entry.restored = true;
                if (entry.kind === 'own') {
                    developThumbsRefresh(entry.key, entry.body.develop);
                    host.developed?.(entry.key, entry.body.develop);
                }
            }
            /* THE PICTURE MAY BE OPEN ALREADY: the device's store answers
               after the page has opened the window on its stored state (a
               reload in „Bearbeiten"). Then the kept edit takes its place now. */
            const subject = developOf;
            const own = subject ? back.find((x) => x.kind === 'own' && x.restored && x.key === developKey(subject)) : null;
            if (own) {
                subject.develop = own.body.develop;
                if (own.body.versions !== undefined && 'develop_versions' in subject) subject.develop_versions = own.body.versions;
                own.restored = false;
                if (foreignOpen === null) {
                    versionOpen = versionValid(subject);
                    editLoad(versionEdit(subject, versionOpen));
                }
            }
            for (const e of foreign?.edits ?? []) {
                const kept = back.find((x) => x.key === 'edit:' + e.id && x.restored);
                if (!kept) continue;
                e.develop = kept.body.develop;
                kept.restored = false;
                if (foreignOpen === e.id) editLoad(e.develop);
            }
            versionsDraw();
        }).catch((e) => console.warn('Aufgehobene Bearbeitungen:', e));
    }

    /** Is the edit the job was drawn on the one open in the window — the photographer's slot, or a friend's edit? */
    const aiJobOpen = (job) => (job.edit !== null ? foreignOpen === job.edit : foreignOpen === null && versionOpen === job.slot);

    /** The window leaves the picture: a place taken out while it was shown is not wanted any more. */
    function aiJobsLeave() {
        for (const job of aiJobs) if (job.subject === developOf && aiJobOpen(job) && !spotsList().includes(job.spot)) job.dropped = true;
    }
    /** The window shows a picture: its places still computing stand there again, with their ring. */
    function aiJobsEnter(subject) {
        for (const job of aiJobs) {
            /* A place the KI is still filling is IN the stored settings
               already, as a copy: the job's own spot takes its seat again,
               so the veil shows and it finishes in sight. */
            if (job.subject === subject && aiJobOpen(job) && !job.dropped && !spotsList().includes(job.spot)) {
                const i = aiJobIndex(spotsList(), job);
                if (i >= 0) spotsList()[i] = job.spot;
            }
        }
    }

    /**
     * The job's picture as a copy of its own — the window may show another
     * by the time the next piece is cut. The window's original while it
     * shows this picture, else fetched (the same addresses the window uses).
     */
    async function aiSource(job) {
        /* „Shows it" asked twice, and of the picture chosen as well: after a
           click on another thumbnail the window holds the old settings until
           the new picture has loaded, while its <img> already changes. */
        const shows = () => developOf === job.subject && measureSubject() === job.subject;
        // The picture itself is the same in every edit — only the places before differ (aiFillCompute).
        if (shows() && await aiOriginal() && shows()) {
            // An <img> caught mid-change refuses the copy — then it is fetched like any other.
            const copy = await createImageBitmap(photoImageEl).catch(() => null);
            if (copy) return copy;
        }
        if (job.cloud) {
            // Through the desk's cache (originalcache.js).
            const blob = await originalBlob(job.subject, `${host.start.baseUrl}pages/cloud_thumb.php?id=${job.subject.id}&original=1`).catch(() => null);
            return blob ? createImageBitmap(blob) : null;
        }
        const blob = await originalBlob(job.subject, `${host.start.baseUrl}pages/photo.php?id=${job.subject.id}`, 'photo').catch(() => null);
        return blob ? createImageBitmap(blob) : null;
    }

    /**
     * The model's input for one piece: its square of the picture as it
     * stands before this place, at AI_SIDE. `img` is the job's original,
     * `before` its earlier places, `working()` the picture with those
     * mended (made only when a heal or clone lies in the square).
     */
    function aiInput(spot, piece, done, img, before, working) {
        const NW = img.width, NH = img.height;
        const [bx, by, bw, bh] = piece.box;
        const c = document.createElement('canvas');
        c.width = c.height = AI_SIDE;
        const ctx = c.getContext('2d', { willReadFrequently: true });
        ctx.imageSmoothingQuality = 'high';
        const inBox = (x) => {
            const r = (x.radius ?? .01) * NW;
            const xs = x.path.map((q) => q[0] * NW), ys = x.path.map((q) => q[1] * NH);
            return Math.max(...xs) + r > bx * NW && Math.min(...xs) - r < (bx + bw) * NW
                && Math.max(...ys) + r > by * NH && Math.min(...ys) - r < (by + bh) * NH;
        };
        // The whole picture in this square's pixels, and where the square sits in it.
        const W2 = AI_SIDE / bw, H2 = AI_SIDE / bh, ox = bx * W2, oy = by * H2;
        /* FROM THE ORIGINAL, at its own sharpness — unless a mended place
           lies in the square. A heal or a clone can only be redone on the
           whole picture, so then a mended copy is cut instead (at most
           4096 pixels on the long side). An earlier AI place is simply
           laid in again: its patch is here. */
        const work = before.some((x) => x.mode !== 'ai' && inBox(x)) ? working() : null;
        if (work) {
            ctx.drawImage(work.canvas, bx * work.W, by * work.H, bw * work.W, bh * work.H, 0, 0, AI_SIDE, AI_SIDE);
        } else {
            ctx.drawImage(img, bx * NW, by * NH, bw * NW, bh * NH, 0, 0, AI_SIDE, AI_SIDE);
            for (const x of before) if (x.mode === 'ai' && inBox(x)) for (const pc of x.pieces) aiPieceApply(ctx, W2, H2, x, pc, ox, oy);
        }
        // This place's pieces so far, filled whole — the model sees the stretch before as done, not half.
        const whole = { ...spot, opacity: 100, feather: 0 };
        for (const pc of done) aiPieceApply(ctx, W2, H2, whole, pc, ox, oy);
        return ctx.getImageData(0, 0, AI_SIDE, AI_SIDE);
    }

    /* --- THE KI FILLS A PLACE (14.11.2026: one job for both models) --------
       „Entfernen" is a method of a place that EXISTS (spotMethodSet), so a
       job never makes a place any more: it fills one — with the quick model
       when the place is switched to „Entfernen" for the first time, with
       the strong one when asked („Mit starkem Modell füllen", „Noch
       einmal"). While it runs, the place stays what it was and pulses
       (`busy`, never stored); only when the fill is there does it change:
         quick    the place becomes „Entfernen", its fill the quick model's;
         strong   the strong fill is shown, the quick one kept (develop.js).
       The squares are cut for the place as „Entfernen" will have it — the
       same core and soft edge in that method's numbers (toolOf) —, and the
       strong model fills the very squares the quick one filled.
       One after the other (aiChain), and going on while one works elsewhere,
       as before: finished away, the fill goes into the place in its
       picture's stored settings (aiFillAway). */
    function aiFillQueue(spot, strong) {
        if (spot.busy) return;
        const subject = developOf;
        const job = { strong, spot, subject, slot: versionOpen, edit: foreignOpen, fe: foreignEdit(), cloud: cloudInWindow !== null,
            pointId: host.pointId(), key: developKey(subject),
            // By its patches and its path the place is found again in settings loaded anew.
            ids: new Set([...(spot.pieces ?? []), ...(spot.other ?? [])].map((piece) => piece.id)),
            sig: JSON.stringify(spot.path) };
        spot.busy = { step: 0, of: 0 };
        aiJobs.add(job);
        aiBusy++;
        developPanelDraw();
        developHandles();
        aiChain = aiChain.then(() => aiFillCompute(job)).catch(() => {})
            .finally(() => { aiBusy--; aiJobs.delete(job); });
    }

    /** Where the job's place sits in a list of spots — itself, or the copy with its patches or its path. */
    function aiJobIndex(spots, job) {
        const own = spots.indexOf(job.spot);
        if (own >= 0) return own;
        const byPatch = job.ids.size ? spots.findIndex((x) => [...(x?.pieces ?? []), ...(x?.other ?? [])].some((piece) => job.ids.has(piece.id))) : -1;
        return byPatch >= 0 ? byPatch : spots.findIndex((x) => x && JSON.stringify(x.path) === job.sig);
    }

    async function aiFillCompute(job) {
        const { spot, subject, strong } = job;
        const live = () => developOf === subject && aiJobOpen(job) && spotsList().includes(spot);
        const wanted = () => !job.dropped && (developOf !== subject || !aiJobOpen(job) || spotsList().includes(spot));
        const redraw = () => {
            if (!live()) return;
            developPanelDraw();
            developHandles();
        };
        let img = null, renderer = null, work;
        try {
            if (!wanted()) return;
            // The files first — asked for once, in their own window (inpaint.js). A „no" leaves the place as it is.
            if (!await (strong ? inpaint.ensureStrong() : inpaint.ensure())) return;
            img = await aiSource(job).catch(() => null);
            if (!img) { if (wanted()) host.status(t('Das Original kam nicht an.'), true); return; }
            if (!wanted()) return;
            const NW = img.width, NH = img.height;
            // The places before this one: in the window while it shows it, else out of the stored settings.
            let before;
            if (live()) before = spotsOf({ spots: spotsList().slice(0, spotsList().indexOf(spot)) });
            else {
                const all = (job.fe ? job.fe.develop : versionEdit(job.subject, job.slot))?.spots ?? [];
                const i = aiJobIndex(all, job);
                if (i < 0) return;
                before = spotsOf({ spots: all.slice(0, i) });
            }
            // The place as „Entfernen" has it: the same core and soft edge, in that method's numbers.
            const as = { ...spot, mode: 'ai' };
            if (spot.mode !== 'ai') {
                const now = toolOf(spot);
                toolInto(as, now.core, now.soft);
            }
            const quick = strong ? (spot.strong ? spot.other : spot.pieces) : null;
            const boxes = strong ? quick.map(({ box, a, b }) => ({ box, a, b })) : aiPieces(as.path, as.radius, NW, NH, as.fill);
            if (!strong) {
                const used = before.reduce((n, x) => n + (x.pieces?.length ?? 0) + (x.other?.length ?? 0), 0);
                if (used + boxes.length > AI_PIECES_MAX) {
                    host.status(t('Mehr Stellen lassen sich an diesem Bild nicht entfernen.'), true);
                    return;
                }
            }
            // The picture with the earlier heals and clones, made once and only if a square needs it.
            const working = () => {
                if (work === undefined) {
                    renderer = developRenderer(document.createElement('canvas'));
                    work = renderer && renderer.setImage(img) && renderer.render({ spots: before }) !== false
                        ? renderer.working() : null;
                }
                return work;
            };
            const seed = strong ? strongSeed() : 0;
            const done = [];
            for (const [k, piece] of boxes.entries()) {
                const input = aiInput(as, piece, done, img, before, working);
                const hole = aiHole(as, piece, NW, NH);
                let out;
                if (strong) {
                    out = await strongFill(input, hole, seed + k, (step, of) => {
                        spot.busy = { piece: k + 1, pieces: boxes.length, step, of };
                        // Only the words change: a redraw of the panel would take a slider out of the hand.
                        if (live()) for (const el of document.querySelectorAll('[data-strong-busy]')) el.textContent = spotBusyWords(spot);
                    });
                } else {
                    // The engine's first start of the visit takes its seconds — the panel's bar says so (spotsPanel).
                    if (!aiRan && !aiStarting) { aiStarting = true; redraw(); }
                    out = await inpaint.fill(input, hole);
                    if (aiStarting) { aiStarting = false; redraw(); }
                    aiRan = true;
                }
                if (!wanted()) return;
                // Only the filled place and a few pixels around it are kept — the PNG in the cloud stays small.
                const patch = document.createElement('canvas');
                patch.width = patch.height = AI_SIDE;
                patch.getContext('2d').putImageData(aiPatchCut(out, aiHole(as, piece, NW, NH, 4)), 0, 0);
                piece.id = aiId();
                aiPatchKeep(piece.id, patch);
                done.push(piece);
            }
            if (strong) {
                // The strong fill shown, the quick one kept. An earlier strong fill („Noch einmal") is let go.
                spot.pieces = done;
                spot.other = quick;
                spot.strong = true;
            } else {
                spot.mode = 'ai';
                spot.radius = as.radius;
                spot.feather = as.feather;
                spot.pieces = done;
                delete spot.other;
                delete spot.strong;
            }
            delete spot.busy;
            if (live()) {
                developSoon();
                developQueue();
                redraw();
            } else {
                aiFillAway(job);
            }
        } catch (error) {
            aiStarting = false;
            console.warn('Entfernen:', error);
            if (wanted()) host.status(error instanceof inpaint.Problem ? error.message
                : strong ? t('Das starke Modell konnte die Stelle nicht füllen.') : t('Die KI konnte die Stelle nicht füllen.'), true);
        } finally {
            if (spot.busy) { delete spot.busy; redraw(); }
            renderer?.free();
            img?.close?.();
        }
    }

    /** A fill finished while its picture is not in the window: into the place in its stored settings, and saved. */
    function aiFillAway(job) {
        const put = (d) => {
            const spots = [...(d?.spots ?? [])];
            const i = aiJobIndex(spots, job);
            if (i < 0) return null;     // the place was taken out meanwhile
            const { busy, ...filled } = job.spot;
            spots[i] = filled;
            return developClean({ ...d, spots });
        };
        if (job.fe) {
            const d = put(job.fe.develop ?? {});
            if (!d) return;
            job.fe.develop = d;
            foreignSend(job.key, job.fe);
        } else {
            const d = put(versionEdit(job.subject, job.slot) ?? {});
            if (!d) return;
            versionPut(job.subject, job.slot, d);
            developSend(job, job.subject.develop, 'develop_versions' in job.subject ? versionsWire(job.subject) : undefined);
        }
        host.status(job.strong ? t('Eine Stelle ist mit dem starken Modell fertig.') : t('Eine entfernte Stelle ist fertig.'));
    }

    /* THE SPOTS' INK IS THE MASKS' PINK (11.11.2026: „statt der
       weißen Farbe für die Striche und für die markierten Flächen die
       gleiche Farbe … wie bei den Masken, also so ein Violett rosa") — the
       veil of „Ebenen" (blend.js, FRAGMENT: 1, .1, .45). White stood out
       against nothing on a pale cap or a bright sky; the pink says
       „marked" on every picture, a colour no fungus has. Lines keep their
       dark band under it; the pins and the brush's ring stay white — a
       pin is a place to click, the ring the pointer, and both belong to
       the mask brush as much. */
    const SPOT_INK = '#ff1a73';
    /** The spots on the picture: a pin for each, the chosen one's region and source, the brush's ring. */
    function spotsDraw(el, twice, u) {
        spotRing = null;
        const pathD = (pts, ox = 0, oy = 0) => pts.map(([x, y], k) => `${k ? 'L' : 'M'}${x + ox} ${y + oy}`).join(' ')
            + (pts.length === 1 ? ' l0 0' : '');
        // The outline of everything within r of a path: a wide stroke minus a narrower one, as a mask.
        const outline = (pts, r, ox, oy, faint, id, fill = false, thin = false) => {
            const xs = pts.map((p) => p[0] + ox), ys = pts.map((p) => p[1] + oy);
            const pad = r + 4 * u;
            const box = { x: Math.min(...xs) - pad, y: Math.min(...ys) - pad,
                width: Math.max(...xs) - Math.min(...xs) + 2 * pad, height: Math.max(...ys) - Math.min(...ys) + 2 * pad };
            for (const [colour, half] of [['#000', 1.5 * u], [SPOT_INK, .75 * u]]) {
                const mask = el('mask', { id: id + colour.slice(1), maskUnits: 'userSpaceOnUse', ...box });
                const line = (stroke, w) => {
                    const n = document.createElementNS(SVGNS, 'path');
                    for (const [k, v] of Object.entries({ d: pathD(pts, ox, oy), fill: 'none', stroke, 'stroke-width': Math.max(0, w),
                        'stroke-linecap': 'round', 'stroke-linejoin': 'round' })) n.setAttribute(k, String(v));
                    mask.append(n);
                };
                /* A place drawn round: its inside is part of it, so only
                   the outer edge is drawn — the inside laid in on both
                   layers, and the inner rim of the stroke goes under it. */
                const inside = (c) => {
                    if (!fill) return;
                    const n = document.createElementNS(SVGNS, 'path');
                    n.setAttribute('d', pathD(pts, ox, oy) + ' Z');
                    n.setAttribute('fill', c);
                    mask.append(n);
                };
                inside('#fff');
                line('#fff', 2 * (r + half));
                line('#000', 2 * (r - half));
                inside('#000');
                el('rect', { ...box, fill: colour, 'fill-opacity': thin ? .45 : faint ? .6 : 1, mask: `url(#${id + colour.slice(1)})` });
            }
        };
        /* The soft edge's other line on the chosen place, paler than its outline:
           inside the outline where a heal or clone stops being whole, outside
           it where „Entfernen" has faded out (spotSoftRadii). */
        const softLine = (s, pts, r, id) => {
            const { full, edge } = spotSoftRadii(s.mode, r, s.feather);
            const other = s.mode === 'ai' ? edge : full;
            if (Math.abs(other - r) < 2 * u || other < u) return;
            outline(pts, other, 0, 0, false, id, !!s.fill, true);
        };
        /* THE VEIL OF A PLACE: everything it mends, in the ink, fading over
           its soft edge as the mending does (below). The stroke being
           painted wears it, and the place whose pin the pointer rests on. */
        /* … AND AS STRONG AS THE PLACE IS (14.11.2026: „dass die
           pinke Fläche immer genau den Bereich widerspiegelt, der wirklich
           betroffen ist … wie stark der Verlauf nach außen ist. Und wie viel
           Deckkraft die Fläche hat"): the veil's strength is the place's
           opacity — a place laid in at 30 per cent wears a veil of 30 per
           cent of the full one. Until then every veil stood at one strength,
           and the pulse of a place the KI works on was a flat band without
           the fade (spotDrawOne). */
        const veil = (path, mode, r, feather, round, id, opacity = 100) => {
            const { full, edge } = spotSoftRadii(mode, r, feather);
            const xs = path.map((p) => p[0]), ys = path.map((p) => p[1]);
            const box = { x: Math.min(...xs) - edge - 2, y: Math.min(...ys) - edge - 2,
                width: Math.max(...xs) - Math.min(...xs) + 2 * edge + 4, height: Math.max(...ys) - Math.min(...ys) + 2 * edge + 4 };
            const mask = el('mask', { id, maskUnits: 'userSpaceOnUse', ...box });
            const into = (attrs) => {
                const n = document.createElementNS(SVGNS, 'path');
                for (const [k, v] of Object.entries({ d: pathD(path), fill: 'none', 'stroke-linecap': 'round',
                    'stroke-linejoin': 'round', ...attrs })) n.setAttribute(k, String(v));
                mask.append(n);
            };
            const STEPS = edge - full > 1 ? 10 : 0;
            for (let k = 0; k < STEPS; k++) {
                const t = (STEPS - k - .5) / STEPS;
                const grey = Math.round(Math.sqrt(1 - t) * 255);
                into({ stroke: `rgb(${grey},${grey},${grey})`, 'stroke-width': 2 * (edge - (edge - full) * k / STEPS) });
            }
            if (full > 0) into({ stroke: '#fff', 'stroke-width': 2 * full });
            if (round) into({ d: pathD(path) + ' Z', fill: '#fff' });
            el('rect', { ...box, fill: SPOT_INK, opacity: .5 * Math.min(100, Math.max(0, Number(opacity ?? 100))) / 100, mask: `url(#${id})` });
        };
        /* THE CHOSEN PLACE IN A GROUP OF ITS OWN, so it can fade (spotSlideStart):
           while it is drawn — and its mark below — `el` lays into the group. */
        const base = el;
        const into = (g) => (name, attrs) => {
            const n = document.createElementNS(SVGNS, name);
            for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, String(v));
            g.append(n);
            return n;
        };
        // The chosen place lifts itself through the hidden drawings while the pointer rests on it (spotReveal).
        const chosenEl = into(el('g', { class: 'photo-spot-chosen' + (spotReveal ? ' photo-spot-reveal' : '') }));
        spotsList().forEach((s, i) => {
            if (i !== spotChosen) {
                spotDrawOne(s, i);
                return;
            }
            el = chosenEl;
            spotDrawOne(s, i);
            el = base;
        });
        function spotDrawOne(s, i) {
            if (!spotShown(s, i)) return;
            const { pts, r, ox, oy } = spotPx(s);
            /* A PLACE THE AI IS STILL WORKING ON keeps the painted veil, so
               one sees what is coming — and that something is. Alone of
               all the spots' drawings it stays when the lines step aside. */
            /* THE STRONG MODEL AT WORK on a filled place wears the same
               pulsing veil as a place the quick model is filling
               (14.11.2026: „würde ich auch wieder die Fläche anzeigen und sie
               so leicht pulsieren lassen … das stärkere Modell rechnet ja
               wirklich mindestens eine halbe Minute"). The quick fill stays
               under it; like the pending veil it stays when the lines step aside. */
            if (maskLinesQuiet && !s.busy && !(spotPanelHover && i === spotChosen)) return;
            if (s.busy) {
                /* EVEN WITH THE DRAWINGS HIDDEN (M) — 14.11.2026:
                   „wenn ich eine KI-Bearbeitung gerade aktuell habe, also dass
                   die Maske dann blinkt und das Laderad angezeigt wird, das
                   bitte auch da anzeigen". The pulse and the ring are no
                   drawing one hides to judge the picture; they say that
                   something is still coming. photo-spot-reveal lifts them. */
                /* THE PULSE COVERS WHAT THE KI WILL FILL, the same for both
                   models (14.11.2026: „die markierte Fläche, die
                   blinkt. Wird beim schwachen Modell größer angezeigt …
                   beim starken Modell kleiner"). It was drawn at the place's
                   stored radius, and that radius means another circle per
                   method: under „Angleichen" — where the quick model starts —
                   the outer one with two core radii of soft edge, under
                   „Entfernen" — where the strong one starts — the core alone.
                   Now both read the place as „Entfernen" has it (as
                   aiFillCompute cuts its squares) and pulse out to where its
                   fill fades away: the core and half a core radius of soft
                   edge at most (spotSoftRadii). */
                const as = { ...s, mode: 'ai' };
                if (s.mode !== 'ai') {
                    const now = toolOf(s);
                    toolInto(as, now.core, now.soft);
                }
                /* … and it is the place's VEIL, not a flat band: whole over the
                   core, fading over the soft edge, as strong as the place's
                   opacity (veil, above) — what will change, and how much. The
                   pulse lies on the group, so the veil keeps its own strength. */
                const back = el;
                el = into(el('g', { class: 'photo-spot-reveal' }));
                const whole = el;
                el = into(el('g', { class: 'photo-spot-pending' }));
                veil(pts, 'ai', (Number(as.radius) || 0) * photoImageEl.naturalWidth, as.feather, !!s.fill, `spot-busy-veil-${i}`, s.opacity);
                el = whole;
                spotSpinner(el, pts, u, !!s.fill);
                el = back;
                return;
            }
            if (s.mode === 'ai') {
                if (i === spotChosen) {
                    outline(pts, r, 0, 0, false, `spot-dst-${i}-`, !!s.fill);
                    softLine(s, pts, r, `spot-soft-${i}-`);
                }
                else el('circle', { cx: pts[0][0], cy: pts[0][1], r: 4.5 * u, fill: s.off ? '#000' : '#fff', 'fill-opacity': .85,
                    stroke: s.off ? '#fff' : '#000', 'stroke-width': 1.5 * u });
                return;
            }
            if (i !== spotChosen) {
                el('circle', { cx: pts[0][0], cy: pts[0][1], r: 4.5 * u, fill: s.off ? '#000' : '#fff', 'fill-opacity': .85,
                    stroke: s.off ? '#fff' : '#000', 'stroke-width': 1.5 * u });
                return;
            }
            // The way from the source to the place it mends — the source turned with the spot (spotSourcePts).
            const src = spotSourcePts(s);
            for (const [stroke, width] of [['#000', 3], [SPOT_INK, 1.5]]) {
                el('line', { x1: src[0][0], y1: src[0][1], x2: pts[0][0], y2: pts[0][1], fill: 'none', stroke,
                    'stroke-width': width * u, 'stroke-dasharray': `${4 * u} ${3 * u}` });
            }
            outline(src, r, 0, 0, true, `spot-src-${i}-`, !!s.fill);
            // The frame round the place, in a group of its own: it fades while the source is dragged (spotTargetFade).
            const back = el;
            el = into(el('g', { class: 'photo-spot-target', ...spotTargetFadeStyle() }));
            outline(pts, r, 0, 0, false, `spot-dst-${i}-`, !!s.fill);
            softLine(s, pts, r, `spot-soft-${i}-`);
            el = back;
        }
        /* The mark of a source out of sight (spotSourceMark): a pink disc on
           a dark ring, an arrow on it pointing where the source lies. It
           belongs to the chosen place and steps aside with it. */
        const mark = maskLinesQuiet && !spotPanelHover ? null : spotSourceMark();
        if (mark) {
            el = chosenEl;
            const [mx, my] = mark.p;
            const a = Math.atan2(mark.src[1] - my, mark.src[0] - mx);
            el('circle', { cx: mx, cy: my, r: 10 * u, fill: SPOT_INK, stroke: '#000', 'stroke-width': 1.5 * u });
            const tip = (d, turn) => [mx + Math.cos(a + turn) * d * u, my + Math.sin(a + turn) * d * u];
            const pts3 = [tip(6, 0), tip(5, 2.4), tip(5, -2.4)];
            el('path', { d: `M${pts3.map((q) => q.join(' ')).join(' L')} Z`, fill: '#fff' });
            el = base;
        }
        const peek = spotPeek !== null && spotPeek !== spotChosen && !spotDrag && !spotPaint ? spotOf(spotPeek) : null;
        if (peek && !peek.busy) {
            // In a group that shows through hidden drawings too (M): the peek is asked for by the pointer.
            const back = el;
            el = into(el('g', { class: 'photo-spot-reveal' }));
            const { pts, r } = spotPx(peek);
            if (peek.mode !== 'ai') {
                // Where it took from: the source's outline and the dashed way, as on the place chosen.
                const src = spotSourcePts(peek);
                for (const [stroke, width] of [['#000', 3], [SPOT_INK, 1.5]]) {
                    el('line', { x1: src[0][0], y1: src[0][1], x2: pts[0][0], y2: pts[0][1], fill: 'none', stroke,
                        'stroke-width': width * u, 'stroke-dasharray': `${4 * u} ${3 * u}` });
                }
                outline(src, r, 0, 0, true, `spot-peek-src-`, !!peek.fill);
            }
            veil(pts, peek.mode, r, peek.feather, !!peek.fill, 'spot-peek-veil', peek.opacity);
            outline(pts, r, 0, 0, false, `spot-peek-dst-`, !!peek.fill);
            el = back;
        }
        if (spotPaint) {
            const r = spotBrush.radius * photoImageEl.naturalWidth;
            /* While painting, the stroke that has come round
               shows its inside too — one sees before letting go that the
               whole of it will go. The veil's thinness on the whole element,
               so stroke and inside overlap without a lighter seam. */
            const round = spotLoopCloses(spotPaint.path, r);
            /* THE VEIL FADES AS THE PLACE WILL (11.11.2026: „Alle
               haben sie ja eine weiche Kante. Der Pinsel bzw. die Markierung
               auf dem Bild zeigt mir das aber nicht an … Könntest du da den
               Verlauf mit einzeichnen?"). Whole up to `full`, gone at
               `edge`, the same smooth step between as pathMask: ten strokes
               in a mask, the widest and faintest first, each narrower one
               brighter over it. The inside of a round stroke is whole. */
            /* FADING SO ONE SEES IT TO THE DASHED RING (11.11.2026:
               „was dann quasi als Markierung gezeichnet wird … weicht stark
               von dem Cursor ab"). The smooth step itself, at half the
               veil's strength, was gone to the eye halfway between the two
               rings — measured: a band 94 pixels high under a dashed ring
               of 144. So the veil fades by the ROOT of what is left: still
               whole at the solid ring, still faintly there just inside the
               dashed one — a picture of where the brush reaches, not of how
               strongly; the strength is the ring's to say. */
            veil(spotPaint.path, spotBrush.mode, r, spotBrush.feather, round, 'spot-paint-veil', spotBrush.opacity);
        }
        if (!spotDrag && !maskLinesQuiet) brushRingDraw(el, u);
    }

    /* A TURNING RING ON THE PLACE while the AI works on it
       (11.11.2026: „es rechnet wirklich lange … so eine kleine Animation
       wäre da schön") — the words „wird berechnet" stand in the panel,
       far from where one looks. At the middle of the stroke, a constant
       size on screen, on a dark disc so it reads on any picture.

       THE TURN COMES FROM THE CLOCK: this layer is drawn anew with every
       move of the pointer (the brush ring follows it), and a CSS animation
       starts over with each new element — it would stand still exactly
       while one moves the mouse. A negative delay set from the clock puts
       every new ring where the last one was. */
    function spotSpinner(el, pts, u, fill = false) {
        const [cx, cy] = spotSpinnerAt(pts, fill);
        const R = 11 * u, C = 2 * Math.PI * R;
        el('circle', { cx, cy, r: 17 * u, fill: '#000', 'fill-opacity': .55 });
        el('circle', { cx, cy, r: R, fill: 'none', stroke: '#fff', 'stroke-opacity': .25, 'stroke-width': 3 * u });
        const arc = el('circle', { cx, cy, r: R, fill: 'none', stroke: '#fff', 'stroke-width': 3 * u, 'stroke-linecap': 'round',
            'stroke-dasharray': `${C * .3} ${C}`, class: 'photo-spot-spinner' });
        arc?.style.setProperty('animation-delay', `-${Math.round(performance.now() % 1000)}ms`);
    }

    /* THE WAITING RING SITS ON THE PULSING AREA (14.11.2026:
       „kann man vielleicht den Wartekreis immer auf die blinkende Fläche
       setzen, wenn man … mit dem Pinsel einen Bogen zeichnet … Dann ist der
       drehende Kreis eben nicht mehr auf der blinkenden Fläche, sondern im
       Zentrum der gesamten Markierung"). It stood in the middle of the
       stroke's bounding box — inside a dot, but beside an arc, in the hollow
       the arc curves around. Now: halfway along the stroke, measured by its
       length, which always lies on it; a painted-round area keeps the middle
       of its box while that lies inside it (a closed blob), and goes onto
       its rim like a stroke where it does not (a C-shaped lasso). */
    function spotSpinnerAt(pts, fill) {
        const xs = pts.map((p) => p[0]), ys = pts.map((p) => p[1]);
        const mid = [(Math.min(...xs) + Math.max(...xs)) / 2, (Math.min(...ys) + Math.max(...ys)) / 2];
        if (pts.length < 2 || (fill && pts.length > 2 && pointInPath(mid[0], mid[1], pts.flat()))) return pts.length < 2 ? pts[0] : mid;
        const steps = pts.slice(1).map((p, k) => Math.hypot(p[0] - pts[k][0], p[1] - pts[k][1]));
        let left = steps.reduce((a, b) => a + b, 0) / 2;
        for (let k = 0; k < steps.length; k++) {
            if (left <= steps[k] && steps[k] > 0) {
                const f = left / steps[k];
                return [pts[k][0] + f * (pts[k + 1][0] - pts[k][0]), pts[k][1] + f * (pts[k + 1][1] - pts[k][1])];
            }
            left -= steps[k];
        }
        return pts[pts.length - 1];
    }

    /** The brush's ring under the pointer — the spots' and the mask brush's alike (ringRadius). */
    function brushRingDraw(el, u) {
        const r = (ringRadius() ?? 0) * photoImageEl.naturalWidth;
        if (!spotHover || !r || spotOut || (spotsOn() && (spotGrab || spotDrag))) return;
        /* TWO RINGS FOR THE SOFT EDGE (11.11.2026, „den Verlauf
           mit einzeichnen"), in the gradient's language (developHandles):
           SOLID up to where the brush acts whole, DASHED where it has faded
           to nothing. A heal, a clone and the mask brush fade inside their
           size, „Entfernen" beyond it (develop.js, aiReach). A hard edge
           brings the two together, and only the solid ring stays. */
        const spots = spotsOn();
        const { full, edge } = spotSoftRadii(spots ? spotBrush.mode : 'brush', r, spots ? spotBrush.feather : brushTool.feather);
        const soft = edge - full >= 2 * u;
        const ring = (radius, dash) => [['#000', 3], ['#fff', 1.5]].map(([stroke, w]) => el('circle', {
            cx: spotHover[0], cy: spotHover[1], r: radius, fill: 'none', stroke, 'stroke-width': w * u,
            'stroke-dasharray': dash ? `${5 * u} ${4 * u}` : 'none', 'pointer-events': 'none', class: 'photo-brush-ring' }));
        spotRing = [...(full >= u || !soft ? ring(soft ? full : edge, false) : []), ...(soft ? ring(edge, true) : [])];
    }

    /**
     * Where a brush of radius `r` (picture pixels) and soft edge `feather`
     * acts whole (`full`) and where it has faded out (`edge`) — as
     * develop.js paints it: pathMask fades a heal, a clone and the mask
     * brush over the outer `feather` per cent of the radius, aiReach lays
     * „Entfernen"'s fade up to half a radius beyond it.
     */
    function spotSoftRadii(mode, r, feather) {
        const f = Math.min(100, Math.max(0, Number(feather ?? 50))) / 100;
        return mode === 'ai' ? { full: r, edge: r * (1 + .5 * f) } : { full: r * (1 - f), edge: r };
    }

    /* NOT ON THE BLACK BESIDE THE PICTURE (12.11.2026: „wenn ich
       das Bild klein gezoomt habe, oder eingepasst ist … dann kann ich auf
       diesen Balken auch Ausbesserungen anwenden … dass wir gerade noch so
       ein bisschen Bild erwischen, also weniger als die Hälfte der
       Pinselbreite außenrum"). A stroke begins only where the brush still
       touches the picture: its middle at most one radius — half the
       brush's width — beyond the edge of what is shown (the cut). Further
       out the ring goes, the pointer is the plain one, a press does
       nothing, and a stroke drawn out there runs along the limit. */
    function spotOutside(e) {
        const r = photoImageEl.getBoundingClientRect();
        if (!r.width || !r.height) return 0;
        const W = viewW(), H = viewH();
        const x = (e.clientX - r.left) / r.width * W, y = (e.clientY - r.top) / r.height * H;
        return Math.hypot(Math.max(0, -x, x - W), Math.max(0, -y, y - H));
    }
    /* The same for the mask brush of „Masken" (the same day:
       „bei den Masken … den Pinsel bitte auch. Außerhalb des Bildes
       verbieten") — the brush in hand, whichever it is (ringRadius). */
    const spotReaches = (e) => spotOutside(e) < (ringRadius() ?? 0) * photoImageEl.naturalWidth;
    /* A STROKE RUNS ALONG THE LIMIT instead of stopping (the
       same day: „wenn ich … an die Grenze stoße, bleibt mir der Pinselring
       hängen. Ich würde ihn einfach in der Höhe oder in der Breite
       mitlaufen lassen an der Grenze. Aber natürlich nicht weiter nach
       außen"). The pointer's place, drawn back onto the limit where it
       lies beyond — the nearest point of the band one radius round the
       shown picture —, in picture pixels: pulled straight out past a side
       it slides along that side, past a corner it rounds it. */
    function spotClampPx(e) {
        const box = photoImageEl.getBoundingClientRect();
        const W = viewW(), H = viewH();
        if (!box.width || !box.height) return picturePx(e);
        let x = (e.clientX - box.left) / box.width * W, y = (e.clientY - box.top) / box.height * H;
        const cx = Math.min(W, Math.max(0, x)), cy = Math.min(H, Math.max(0, y));
        const dx = x - cx, dy = y - cy, d = Math.hypot(dx, dy);
        const r = (ringRadius() ?? 0) * photoImageEl.naturalWidth * .999;
        if (d > r && d > 0) {
            x = cx + dx / d * r;
            y = cy + dy / d * r;
        }
        return pictureView ? pictureView.toNat([x, y]) : [x, y];
    }

    photoFrame.addEventListener('pointerdown', (e) => {
        if (!spotsOn() || e.button !== 0 || spacePan || !developMay()) return;
        if (e.target.closest?.('button, .photo-scalebar')) return;
        const p = picturePx(e);
        const hit = spotHit(p);
        /* Nothing to take hold of, and too far out to paint: the press lets
           the chosen spot go, as a press beside the picture does
           (12.11.2026: „Kannst dann diesen Bereich außerhalb genauso nutzen,
           um die Auswahl aufzuheben"), and nothing else. Handed on to the
           zoom it would zoom the picture in — measured, a click on the
           black bar did. */
        if (!hit && !spotReaches(e)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            swallowClick();
            if (spotChosen !== null) {
                spotChosen = null;
                spotFresh = false;
                developPanelDraw();
                developHandles();
            }
            return;
        }
        e.preventDefault();
        // Before the zoom's own listener on this element: no pan under the brush.
        e.stopImmediatePropagation();
        photoFrame.setPointerCapture(e.pointerId);
        if (hit) {
            const s = spotsList()[hit.i];
            spotChosen = hit.i;
            spotFresh = false;
            // The mark taken: the source comes under the pointer, and is dragged on from there.
            if (hit.pull) {
                const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
                s.dx = Math.round(((s.dx ?? 0) + (p[0] - hit.pull.src[0]) / W) * 10000) / 10000;
                s.dy = Math.round(((s.dy ?? 0) + (p[1] - hit.pull.src[1]) / H) * 10000) / 10000;
                developSoon();
                developQueue();
            }
            // A place still being filled by the KI stays where it is (aiFillCompute).
            spotDrag = { id: e.pointerId, i: hit.i, part: s.busy ? 'none' : hit.part, start: p, x: e.clientX, y: e.clientY,
                from: { path: s.path.map((q) => [...q]), dx: s.dx ?? 0, dy: s.dy ?? 0 }, moved: false };
            photoFrame.classList.add('is-spot-dragging');
            developPanelDraw();
        } else {
            spotPaint = { id: e.pointerId, path: [p] };
        }
        developHandles();
    }, true);
    photoFrame.addEventListener('pointermove', (e) => {
        if (spotPaint && spotPaint.id === e.pointerId) {
            e.stopImmediatePropagation();
            // Out past the limit, the stroke runs along it (spotClampPx).
            const p = spotClampPx(e), last = spotPaint.path[spotPaint.path.length - 1];
            if (Math.hypot(p[0] - last[0], p[1] - last[1]) >= Math.max(spotBrush.radius * photoImageEl.naturalWidth * .2, screenUnits(2))) {
                spotPaint.path.push(p);
                spotHover = p;
                developHandles();
            }
            return;
        }
        if (spotDrag && spotDrag.id === e.pointerId) {
            e.stopImmediatePropagation();
            if (spotDrag.part === 'none') return;
            if (!spotDrag.moved && Math.hypot(e.clientX - spotDrag.x, e.clientY - spotDrag.y) < 4) return;
            if (!spotDrag.moved && spotDrag.part === 'source') spotTargetFade = { out: true, at: performance.now() };
            spotDrag.moved = true;
            const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
            const s = spotsList()[spotDrag.i];
            const p = picturePx(e);
            const mx = (p[0] - spotDrag.start[0]) / W, my = (p[1] - spotDrag.start[1]) / H;
            const four = (x) => Math.round(x * 10000) / 10000;
            if (spotDrag.part === 'source') {
                s.dx = four(spotDrag.from.dx + mx);
                s.dy = four(spotDrag.from.dy + my);
            } else {
                /* MOVED, A PLACE SHOWN AS „ENTFERNEN" TURNS INTO „ANGLEICHEN"
                   at the first step, as when it is sized (spotSetting) — its
                   fill belonged to where it lay. Taken by its white pin: the
                   chosen one's area is where the next stroke begins (spotHit). */
                if (spotLeaveAi(s)) {
                    spotDrag.from.dx = s.dx ?? 0;
                    spotDrag.from.dy = s.dy ?? 0;
                    developPanelDraw();
                }
                // The place moves, its source stays where it was — and the KI's kept fills go (spotAiForget).
                s.path = spotDrag.from.path.map(([x, y]) => [four(x + mx), four(y + my)]);
                spotAiForget(s);
                s.dx = four(spotDrag.from.dx - mx);
                s.dy = four(spotDrag.from.dy - my);
            }
            developSoon();
            developHandles();
            return;
        }
        if ((spotsOn() || brushOn()) && !e.buttons) {
            spotHover = picturePx(e);
            /* WHAT A PRESS HERE WOULD DO, said by the pointer
               (11.11.2026: „es ist unklar anhand des Mauszeigers, wann ich eine
               bereits vorhandene Ausbesserung editiere, sprich verschiebe,
               und wann ich neu zeichne. Mir wird quasi immer der Pinselkreis
               angezeigt"). Over something a press takes hold of — the chosen
               spot's place or source, another spot's pin — the ring goes and
               the pointer becomes the four arrows of moving; anywhere else the
               ring and its dot say: here a stroke begins. The question is the
               press's own (spotHit), so pointer and press cannot disagree. */
            const hit = spotsOn() ? spotHit(spotHover) : null;
            const grab = developMay() && hit !== null;
            /* WITH THE DRAWINGS HIDDEN (M), WHAT A PRESS WOULD TAKE SHOWS
               ITSELF (14.11.2026: „beim Ausbessern arbeite ich oft
               mit ausgeschalteter Maske … ich sehe halt nicht die Quelle.
               Wenn der nächste Punkt, den ich entfernen will, quasi die Quelle
               ist, kommt das zu Missverständnissen … klicke ich ja auf die
               Quelle und würde die Quelle verschieben … Wenn man auf einer mit
               der Maus zeigt, soll sie eingeblendet werden. Probieren wir mal
               aus"). The pointer on the chosen place or on its source: that
               place stands there — its frame, its source, the dashed way —
               for as long as the pointer rests on it. On another place's pin
               its peek does the same (spotPeek). Everything else stays
               hidden; the class photo-spot-reveal lifts only these through
               the hiding (photowindow.css). */
            const reveal = drawingsHidden && hit !== null && hit.i === spotChosen;
            if (reveal !== spotReveal) {
                spotReveal = reveal;
                developHandles();
            }
            const out = !grab && !spotReaches(e);
            /* A PLACE'S PIN SHOWS ITS PLACE (14.11.2026: „wenn ich
               … über die Punkte fahre, über die weißen, also quasi die nicht
               aktiven … will ich das genauso wie bei den Masken, dass mir da
               kurz der Ausschnitt eingeblendet wird. Die Größe, und dass ich
               sehe, was da alles bearbeitet wurde"): the twin of maskPeek. The
               place whose white pin the pointer rests on stands there whole
               — its veil over all it mends, its outline, for a heal or a copy
               the source it took from — and goes when the pointer leaves the
               pin. Looking needs no write right: a read-only viewer sees it too. */
            const peek = spotsOn() && !maskLinesQuiet ? spotPinAt(spotHover) : null;
            if (peek !== spotPeek) {
                spotPeek = peek;
                developHandles();
            }
            if (grab !== spotGrab || out !== spotOut) {
                spotGrab = grab;
                spotOut = out;
                photoFrame.classList.toggle('is-spot-grab', grab);
                photoFrame.classList.toggle('is-spot-out', out);
                developHandles();
            } else if (spotRing) for (const c of spotRing) { c.setAttribute('cx', String(spotHover[0])); c.setAttribute('cy', String(spotHover[1])); }
            else if (!grab) developHandles();
        }
    }, true);
    /* THE POINTER LEAVING THE PICTURE takes the lines with it
       (10.11.2026: „die Maskenlinien und die Ausbesserlinien werden ja
       wieder eingeblendet, wenn ich mit der Maus über das Bild fahre. Wenn
       ich mit der Maus wieder das Bild verlasse, sollen sie wieder
       ausgeblendet werden"): in „Masken" and „Ausbessern" the lines, frames
       and the brush's red veil stand only while the pointer is over the
       picture (maskLinesQuiet), and the brush's ring goes with them. Not
       mid-gesture: a drag that ends outside lets go first, and the leave
       that follows the release does the rest. */
    photoFrame.addEventListener('pointerleave', () => {
        if (spotPeek !== null || spotReveal) { spotPeek = null; spotReveal = false; developHandles(); }
        if (spotPaint || spotDrag || brushPaint || gripDrag || placeDrag || turnDrag) return;
        spotGrab = false;
        spotOut = false;
        photoFrame.classList.remove('is-spot-grab', 'is-spot-out');
        const quiet = developMode && (developTab === 'masks' || developTab === 'spots') && !cropEditing;
        if (!spotHover && (!quiet || maskLinesQuiet)) return;
        spotHover = null;
        developHandles();
        /* A MOMENT'S GRACE (13.11.2026: „genau dort, wo ich die
           Sidebar in der Größe verschieben kann … Momentan wird mir da mal
           kurz die Kontur ausgeblendet"): on the way from the picture to the
           sliders the pointer crosses the sidebar's edge and its padding —
           the lines went there and came back over the sliders, a blink. They
           now go LINES_QUIET_GRACE after the pointer left, and not at all
           where it reached the picture again or the sliders' box first
           (spotPanelShow). */
        if (!quiet) return;
        clearTimeout(linesQuietTimer);
        linesQuietTimer = setTimeout(() => {
            if (photoFrame.matches(':hover')) return;
            maskLinesQuiet = true;
            developHandles();
        }, LINES_QUIET_GRACE);
    });
    const LINES_QUIET_GRACE = 300;
    let linesQuietTimer = 0;
    const spotUp = (e) => {
        if (spotPaint && spotPaint.id === e.pointerId) {
            e.stopImmediatePropagation();
            swallowClick();
            spotFinish();
        }
        if (spotDrag && spotDrag.id === e.pointerId) {
            e.stopImmediatePropagation();
            if (spotDrag.moved) {
                developQueue();
                swallowClick();
            }
            // The frame comes back from where its fade-out got to: the same share of the way, on the slower way in.
            if (spotTargetFade?.out) {
                const share = Math.min(1, (performance.now() - spotTargetFade.at) / SPOT_TARGET_FADE_OUT);
                spotTargetFade = { out: false, at: performance.now() - (1 - share) * SPOT_TARGET_FADE_IN };
            }
            spotDrag = null;
            photoFrame.classList.remove('is-spot-dragging');
            developPanelDraw();
            developHandles();
        }
    };
    photoFrame.addEventListener('pointerup', spotUp, true);
    photoFrame.addEventListener('pointercancel', (e) => {
        if (spotPaint?.id === e.pointerId) spotPaint = null;
        spotUp(e);
        developHandles();
    }, true);
    /* Delete for the chosen spot. The size went from [ and ] to Shift and
       the wheel (11.11.2026): on a German Mac keyboard the brackets are
       Option+5 and Option+6 — two keys, and the right hand's. */
    window.addEventListener('keydown', (e) => {
        if (!spotsOn() || !viewing() || e.metaKey || e.ctrlKey || e.altKey) return;
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target?.tagName ?? '') || e.target?.isContentEditable) return;
        if ((e.key === 'Delete' || e.key === 'Backspace') && spotChosen !== null) {
            e.preventDefault();
            spotDelete(spotChosen);
        }
    });

    /* --- „PINSEL" — the painted mask (10.11.2026: „Bei den
       Masken fehlt noch ein Stiftwerkzeug") --------------------------------
       „+ Pinsel" lays an empty one and chooses it; while it is chosen every
       press on the picture paints into it, Alt held erases, and its red veil
       shows where it acts until the lines step aside (maskLinesQuiet) — the
       effect is judged without it. The tool's size, soft edge, flow and
       painting or erasing stand above the mask's own sliders. */
    /* THE VEIL ONLY WHILE THE MASK DOES NOTHING YET: a new brush with all
       its sliders at 0 would paint unseen, so the red shows where it acts;
       once a slider is set the effect itself shows it, and painting on is
       judged by the effect, live, not under a red film. */
    /* „MASKE ZEIGEN" KEEPS IT THERE (11.11.2026: „eine Option,
       wo ich mir das Gemalte anschauen kann und noch ein bisschen weiter
       bearbeiten kann … in diesem pink-lila Ton"): with the tick on, the
       veil stands over the chosen painted mask whatever its sliders say and
       wherever the pointer is — to see what is painted, and to paint on at
       its edges. The device keeps the choice; H switches it, as it switches
       the mask in „Ebenen". */
    /* THE VEIL STANDS ALWAYS — NO TICK (13.11.2026: „ich würde
       die Maske auch dauerhaft zeigen. Um daran weiterzumalen oder zu
       retuschieren. Nur wenn die Schieberegler dann verwendet werden, würde
       ich sie ausblenden", and then: „Maske anzeigen brauche ich ja gar
       nicht mehr, wenn wir die Maske immer anzeigen"). The chosen brush
       wears its veil wherever the pointer is; only a moving effect slider
       lays it aside (maskSliding). The tick „Maske zeigen" of 11.11.2026
       and its key H went with it. */
    let maskSliding = false;
    let maskSlideTimer = 0;
    let maskSlideHeld = false;
    /* A MASK'S PIN SHOWS ITS MASK (13.11.2026: „Wenn ich im Bild
       über eine Maske fahre, also über den kleinen weißen Punkt, eine nicht
       aktive Maske, dann soll mir diese Maske im Bild kurz angezeigt
       werden. Und wenn ich den Punkt wieder verlasse, wieder ausblenden"):
       the pointer on the white pin of a mask not in hand lays that mask's
       red veil over the picture — the renderer's one shown mask (uShow),
       which takes any kind —, and the veil goes when the pointer leaves the
       pin. It wins over the chosen brush's veil for as long as it lasts. */
    let maskPeek = null;   // the mask whose pin the pointer rests on
    photoFrame.addEventListener('pointermove', (e) => {
        const pin = !e.buttons && developMode && developTab === 'masks' ? e.target.closest?.('[data-grip="move"]') : null;
        const m = pin ? maskList(pin.dataset.kind)?.[Number(pin.dataset.i)] ?? null : null;
        if (m === maskPeek) return;
        maskPeek = m;
        developSoon();
    }, { capture: true, passive: true });
    photoFrame.addEventListener('pointerleave', () => { if (maskPeek) { maskPeek = null; developSoon(); } });
    photoFrame.addEventListener('pointerdown', () => { if (maskPeek) { maskPeek = null; developSoon(); } }, true);
    // A press on a pin chooses that place — its peek has done its work (spotPeek).
    photoFrame.addEventListener('pointerdown', () => { spotPeek = null; }, true);
    function maskOverlay() {
        if (maskPeek && developMode && developTab === 'masks') {
            const at = masksOf(develop).findIndex((x) => x.m === maskPeek);
            if (at >= 0) return at;
        }
        if (!brushOn()) return -1;
        const m = maskOf(maskChosen);
        if (!m) return -1;
        // While an effect slider moves, the result shows bare (maskSlideStart); after a click on empty ground too.
        if (maskSliding || maskBare) return -1;
        return masksOf(develop).findIndex((x) => x.m === m);
    }

    function brushToolSet(key, v) {
        // 'radius' is the core's and 'feather' the soft edge beyond it, as in „Ausbessern" (toolOf).
        if (key === 'radius') toolInto(brushTool, v, toolOf(brushTool).soft);
        else if (key === 'feather') toolInto(brushTool, toolOf(brushTool).core, v);
        else brushTool[key] = v;
        settings.set('brushTool', brushTool);
        developHandles();
    }

    /* OPTION HOLDS THE PARTNER, BOTH WAYS (13.11.2026: the
       eraser „mit genau den gleichen Einstellungen … wie der Pinsel", and
       from the eraser back to the brush the same). The mask brush has ONE
       set of settings for painting and erasing, so the partner always
       carries size, soft edge and flow along; until now Option only turned
       the brush into the eraser, and in the eraser it did nothing. The
       stroke asks e.altKey itself — this only lights the button that is in
       force while the key is held. */
    let brushAltHeld = false;
    const brushErasing = () => !!brushTool.erase !== brushAltHeld;
    function brushAltSet(on) {
        if (on === brushAltHeld) return;
        brushAltHeld = on;
        document.querySelectorAll('.photo-develop-spots button[data-erase]').forEach((b) => {
            const lit = String(brushErasing()) === b.dataset.erase;
            b.classList.toggle('is-active', lit);
            b.setAttribute('aria-pressed', String(lit));
        });
    }

    function brushToolPanel(may) {
        const box = document.createElement('div');
        box.className = 'photo-develop-spots';
        const modes = document.createElement('div');
        modes.className = 'button-row';
        for (const [erase, label, title] of [[false, t('Malen'), t('Malen (Taste B)')],
            [true, t('Radieren'), t('Radieren (Taste E, Option halten, X wechselt)')]]) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small' + (brushErasing() === erase ? ' is-active' : '');
            b.dataset.erase = String(erase);
            b.textContent = label;
            if (title) b.title = title;
            b.disabled = !may;
            b.setAttribute('aria-pressed', String(brushErasing() === erase));
            b.addEventListener('click', () => {
                brushToolSet('erase', erase);
                developPanelDraw();
            });
            modes.append(b);
        }
        box.append(modes,
            developSlider({ key: 'size', name: () => t('Größe'), min: 0, max: 100, home: spotSizeOf(toolOf(BRUSH_TOOL_HOME).core) },
                spotSizeOf(toolOf(brushTool).core), (v) => brushToolSet('radius', spotRadiusOf(v)), may),
            developSlider({ key: 'feather', name: () => t('Weiche Kante'), min: 0, max: 100, home: Math.round(toolOf(BRUSH_TOOL_HOME).soft) },
                Math.round(toolOf(brushTool).soft), (v) => brushToolSet('feather', v), may),
            developSlider({ key: 'flow', name: () => t('Fluss'), min: 0, max: 100, home: 100 },
                brushTool.flow, (v) => brushToolSet('flow', v), may));
        return box;
    }


    developAddBrush?.addEventListener('click', () => maskCreate('brush'));

    photoFrame.addEventListener('pointerdown', (e) => {
        if (!brushOn() || e.button !== 0 || spacePan || !developMay()) return;
        // A pin of another mask chooses that one (the grips' own listener).
        if (e.target.closest?.('button, .photo-scalebar, [data-grip]')) return;
        const b = maskOf(maskChosen);
        if (!b) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        // Too far beside the picture to touch it (spotReaches): no stroke, no zoom — the press clears the view
        // of the brush's veil, as a press on empty ground does everywhere (maskBareSet, 13.11.2026).
        if (!spotReaches(e)) {
            swallowClick();
            maskBareSet(true);
            return;
        }
        photoFrame.setPointerCapture(e.pointerId);
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        const p = picturePx(e);
        const stroke = { path: [[p[0] / W, p[1] / H]], radius: brushTool.radius, feather: brushTool.feather, flow: brushTool.flow };
        // Option held: the partner — the eraser from the brush, the brush from the eraser (brushAltHeld).
        if (!!brushTool.erase !== e.altKey) stroke.erase = true;
        (b.strokes ??= []).push(stroke);
        brushPaint = { id: e.pointerId, b, stroke, px: [p] };
        // Painting on brings the veil back after a click had cleared the view (maskBareSet).
        maskBare = false;
        spotHover = p;
        developSoon();
        developHandles();
    }, true);
    photoFrame.addEventListener('pointermove', (e) => {
        if (!brushPaint || brushPaint.id !== e.pointerId) return;
        e.stopImmediatePropagation();
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        // Out past the limit, the stroke and its ring run along it (spotClampPx).
        const p = spotClampPx(e), last = brushPaint.px[brushPaint.px.length - 1];
        spotHover = p;
        if (Math.hypot(p[0] - last[0], p[1] - last[1]) < Math.max(brushTool.radius * W * .2, screenUnits(2))) {
            if (spotRing) for (const c of spotRing) { c.setAttribute('cx', String(p[0])); c.setAttribute('cy', String(p[1])); }
            return;
        }
        brushPaint.px.push(p);
        brushPaint.stroke.path.push([p[0] / W, p[1] / H]);
        developSoon();
        developHandles();
    }, true);
    const brushUp = (e) => {
        if (!brushPaint || brushPaint.id !== e.pointerId) return;
        e.stopImmediatePropagation();
        swallowClick();
        const { b, stroke, px } = brushPaint;
        brushPaint = null;
        const W = photoImageEl.naturalWidth, H = photoImageEl.naturalHeight;
        // Lean, as the spots are: the points that change the stroke's course.
        const lean = spotSimplify(px, Math.max(.5, stroke.radius * W * .1));
        stroke.path = lean.map(([x, y]) => [Math.round(x / W * 10000) / 10000, Math.round(y / H * 10000) / 10000]);
        const used = spotsList().reduce((n, sp) => n + sp.path.length, 0)
            + (develop.brushes ?? []).reduce((n, br) => n + (br.strokes ?? []).reduce((m, st) => m + st.path.length, 0), 0);
        if (used > PATH_POINTS_MAX) {
            b.strokes.splice(b.strokes.indexOf(stroke), 1);
            host.status(t('Mehr Pinselstriche passen an diesem Bild nicht mehr.'), true);
        }
        developSoon();
        developQueue();
        developPanelDraw();
        developHandles();
    };
    photoFrame.addEventListener('pointerup', brushUp, true);
    photoFrame.addEventListener('pointercancel', brushUp, true);

    /* SHIFT AND THE WHEEL SIZE THE BRUSH, wherever one is in hand
       (11.11.2026, after „Ebenen" had it: „schau doch mal, wo wir noch
       überall Pinselwerkzeuge haben … sollten wir dort überall umsetzen").
       Here that is „Ausbessern" — Reparieren, Kopieren, Entfernen — and the
       painted mask of „Masken"; the same step the keys [ and ] take, in the
       same places. Asked on the window and in the capture phase: the
       picture's frame zooms on every turn of the wheel (imagezoom.js), and
       this turn must not reach it. With Shift held a Mac hands the turn
       over as a sideways one, so whichever of the two came is taken
       (shortcuts.js, wheelStep: trackpad and mouse apart). The layers' stage lies in the same frame and sizes its own
       brush (blend.js) — neither of the two is on while it is. */
    /* AND CMD WITH THE WHEEL SETS THE SOFT EDGE (11.11.2026,
       the keys made one system: „weiche Kante mit Command") — the thumb on
       Cmd instead of the little finger on Shift, Option staying free for
       the partner tool. Firefox would zoom the whole page on Cmd and the
       wheel; the turn is taken before it can. */
    photoWindow.addEventListener('wheel', (e) => {
        /* R HELD WITH THE WHEEL TURNS THE CHOSEN SOURCE (11.11.2026)
           — a degree per notch, more for a fast turn; up turns clockwise. */
        if (turnKeyHeld && spotsOn() && viewing() && photoFrame.contains(e.target) && !e.ctrlKey) {
            const s = spotOf(spotChosen);
            if (s && s.mode !== 'ai') {
                e.preventDefault();
                e.stopPropagation();
                turnKeyTurned = true;
                /* THE LINES STEP ASIDE WHILE THE SOURCE TURNS
                   (14.11.2026: „bin ich gerade an der Quelle oder irgendwo am
                   Markierten und drücke R und drehe am Mausrad, sollten mir
                   die Auswahl-Umrisslinien ausgeblendet werden"). The turn is
                   judged by what it mends, and the outline, the source's
                   frame and the dashed way lie right over that. As with a
                   slider in hand (spotSlideStart): out with the first notch,
                   away for as long as R is held, back a moment after it is
                   let go (the keyup below). */
                spotSlideHeld = true;
                spotSlideStart('rot');
                const turn = e.deltaY || e.deltaX;
                if (turn) {
                    spotTurn((Number(s.rot) || 0) - Math.sign(turn) * Math.max(1, Math.min(10, Math.abs(turn) * .05)));
                    developPanelDraw();
                }
                return;
            }
        }
        // Ctrl, not Cmd, for the soft edge since 13.11.2026 — the real key, not a pinch (shortcuts.js, wheelWithCtrl).
        const ctrl = wheelWithCtrl(e);
        const size = e.shiftKey && !e.metaKey && !e.ctrlKey, edge = ctrl && !e.shiftKey;
        if ((!size && !edge) || (e.ctrlKey && !ctrl) || !viewing() || !photoFrame.contains(e.target)) return;
        const spots = spotsOn(), brush = !spots && brushOn();
        if (!spots && !brush) return;
        e.preventDefault();
        e.stopPropagation();
        /* ON THE TRACKPAD AWAY FROM ONESELF IS MORE, the mouse as it was,
           the trackpad slower (11.11.2026; shortcuts.js,
           wheelStep, says how the two are told apart). */
        const step = wheelStep(e);
        if (!step) return;
        if (edge) {
            // The soft edge beyond the core, 0 … 100 — the core stays (toolOf). The brush's only, never a place's.
            const now = toolOf(spots ? spotBrush : brushTool).soft;
            const next = Math.round(Math.min(100, Math.max(0, now + step)));
            if (spots) spotSetting('feather', next, 'brush');
            else brushToolSet('feather', next);
        } else {
            // The core's size; the soft edge keeps its share and grows along — the whole brush scales.
            const sized = (core) => spotRadiusOf(Math.min(100, Math.max(0, spotSizeOf(core) + step)));
            if (spots) spotSetting('radius', sized(toolOf(spotBrush).core), 'brush');
            else brushToolSet('radius', sized(toolOf(brushTool).core));
        }
        developPanelDraw();
        ringFollows(picturePx(e));
    }, { capture: true, passive: false });

    /* THE RING STAYS AND GROWS WITH IT (11.11.2026: „der
       Schieberegler regelt sich, allerdings die Anzeige am Cursor wird
       ausgeblendet und wird nicht angepasst in der Größe"). A size set
       from the sidebar lays the lines aside, so the picture can be seen
       bare while one slides (maskLinesQuiet) — and the wheel and the keys
       [ and ] went the same way. But they are turned and pressed with the
       pointer ON the picture, where the ring is the one thing that shows
       the new size: so it is drawn again there, at once. (The keys [ and ]
       went on 11.11.2026; the wheel stayed.) */
    function ringFollows(at) {
        if (!at) return;
        spotHover = at;
        maskLinesQuiet = false;
        developHandles();
    }

    /* --- THE KEYS (11.11.2026; the system in shortcuts.js) ------
       Single presses under the left hand, a digit meaning what the panel in
       front of one offers:
         „Messen"      1–5 the measuring tools, Q text, W arrow, R frame,
                       T cut for the plate, A pick — the rail's order, its
                       buttons name them
         „Ausbessern"  1 Angleichen, 2 Kopieren, 3 Entfernen; R held with
                       the wheel turns the chosen source, R tapped is R below
         „Masken"      1 ellipse, 2 gradient, 3 brush; with the brush in hand
                       B paints, E erases, X switches, Option held erases
         „Bearbeiten"  O held shows the original, R „Zuschneiden, Drehen"
         both          M hides the drawings and brings them back (H until
                       14.11.2026 — „auf M legen"; „Ebenen"
                       took M for its mask the same day)
       NO KEY FOR THE ELLIPSE ALONE: Lightroom's Shift+M was asked about
       and let go („Vielleicht lassen wir die Ellipse einfach komplett
       weg") — M is the drawings, and the digits in „Masken" reach it.
       „Ebenen" keeps its own keys (blend.js) and this file is quiet there.
       Not while one types, not with Cmd or Ctrl (the browser's), and only
       while the picture is in sight. */
    const RAIL_KEYS = { 1: 'line', 2: 'path', 3: 'rect', 4: 'cutout', 5: 'cystid', 6: 'sector', q: 'text', w: 'arrow', r: 'box', t: 'crop', a: '' };
    let drawingsHidden = false;
    function drawingsHide(on) {
        drawingsHidden = on;
        photoFrame.classList.toggle('is-drawings-hidden', on);
        host.status(on ? t('Zeichnungen ausgeblendet — M zeigt sie wieder.') : t('Zeichnungen wieder eingeblendet.'));
    }
    function photoKeys(e) {
        if (!viewing() || e.metaKey || e.ctrlKey || typingIn(e.target)) return;
        if (document.querySelector('dialog.dialog[open]')) return;
        if (developMode && developTab === 'layers') return;
        const k = e.key.length === 1 ? e.key.toLowerCase() : e.key;
        const claim = () => { e.preventDefault(); return !e.repeat; };
        if (k === 'Alt' && brushOn()) { e.preventDefault(); brushAltSet(true); return; }
        if (e.altKey) return;
        if (k === 'm') {
            // The drawings (the brush's veil has no switch since 13.11.2026 — it always stands).
            if (claim()) drawingsHide(!drawingsHidden);
            return;
        }
        if (!developMode) {
            const mode = RAIL_KEYS[k];
            if (mode === undefined) return;
            const rail = document.getElementById('photo-tool-rail');
            const b = rail?.querySelector(`button[data-tool="${mode}"]`);
            if (!b || rail.hidden || b.offsetParent === null) return;
            if (claim()) b.click();
            return;
        }
        if (k === 'o') {
            e.preventDefault();
            developBeforeSet(true);
            return;
        }
        /* V — THE NEXT EDIT, after the last the first again
           (11.11.2026): with O held for the original, both comparisons under
           the left hand. Claimed even with one edit, so the browser's
           type-ahead never takes it. */
        if (k === 'v') {
            if (!claim() || versionsEl?.hidden) return;
            // Through the others' edits too, in the bar's order (editsInOrder).
            const all = editsInOrder();
            if (all.length > 1) editOpen(all[(all.findIndex(editIsOpen) + 1) % all.length]);
            return;
        }
        if (developTab === 'spots' && k === 'r') {
            /* Held, it turns the chosen source with the wheel (above); claimed, repeats too.
               TAPPED — let go without a turn — it is „Zuschneiden, Drehen" as in every other
               tab (keyup below): the one key keeps both, nothing of the old use is lost. */
            e.preventDefault();
            if (!e.repeat) turnKeyTurned = false;
            turnKeyHeld = true;
            return;
        }
        if (k === 'r') {
            if (claim()) cropKey();
            return;
        }
        if (developTab === 'spots' && /^[123]$/.test(k)) {
            // 1 Angleichen, 2 Kopieren — from „Entfernen" first back to „Reparieren", then its switch.
            const mode = ['heal', 'clone', 'ai'][Number(k) - 1];
            // The method of the place chosen (spotKindRow) — without one the digits have nothing to switch.
            const b = developSliders.querySelector(`[data-spot-mode="${mode}"]`);
            if (b && !b.disabled && claim() && b.getAttribute('aria-pressed') !== 'true') b.click();
            return;
        }
        if (developTab === 'masks' && /^[123]$/.test(k)) {
            const b = document.getElementById(['photo-develop-add', 'photo-develop-add-linear', 'photo-develop-add-brush'][Number(k) - 1]);
            if (b && !b.disabled && b.offsetParent !== null && claim()) b.click();
            return;
        }
        if (brushOn() && (k === 'b' || k === 'e' || k === 'x')) {
            if (!claim() || !developMay()) return;
            brushToolSet('erase', k === 'x' ? !brushTool.erase : k === 'e');
            developPanelDraw();
        }
    }
    window.addEventListener('keydown', photoKeys);
    let turnKeyHeld = false;
    let turnKeyTurned = false;
    window.addEventListener('keyup', (e) => {
        if (e.key.toLowerCase() === 'o' && developBefore) developBeforeSet(false);
        if (e.key.toLowerCase() === 'r') {
            const tapped = turnKeyHeld && !turnKeyTurned;
            // Turned with the wheel: the chosen place's lines come back after their rest (the wheel handler above).
            if (turnKeyHeld && turnKeyTurned && spotSlideHeld) { spotSlideHeld = false; spotSlideRest(); }
            turnKeyHeld = false;
            if (tapped && developMode && developTab === 'spots' && viewing() && !typingIn(e.target)) cropKey();
        }
        if (e.key === 'Alt') brushAltSet(false);
    });
    window.addEventListener('blur', () => {
        if (developBefore) developBeforeSet(false);
        brushAltSet(false);
        if (turnKeyHeld && turnKeyTurned && spotSlideHeld) { spotSlideHeld = false; spotSlideRest(); }
        turnKeyHeld = false;
    });

    shortcutsProvide(() => {
        if (!viewing() || (developMode && developTab === 'layers')) return [];
        const out = [{ title: t('Bild'), rows: [['Y', t('einpassen')], [t('Leertaste halten'), t('Bild verschieben')],
            ['M', t('Zeichnungen aus- und einblenden')]] }];
        if (!developMode) {
            // Only the tools the layer offers (layerTools) — a key whose button is hidden does nothing.
            const offered = ([key]) => {
                const mode = RAIL_KEYS[key.toLowerCase()];
                return mode === '' || !document.querySelector(`#photo-tool-rail button[data-tool="${mode}"]`)?.hidden;
            };
            out.push({ title: t('Messen'), rows: [['1', t('Strecke')], ['2', t('Gebogene Länge')], ['3', t('Länge × Breite')],
                ['4', t('Länge × Breite, gebogen')], ['5', t('Zystide in Teilen')], ['Q', t('Text')], ['W', t('Pfeil')],
                ['R', t('Poren zählen')], ['6', t('Lamellen zählen')], ['T', t('Bildausschnitt')], ['A', t('Auswählen')]].filter(offered) });
            return out;
        }
        out.push({ title: t('Bearbeiten'), rows: [[t('O halten'), t('das Original zeigen')], ['R', t('Zuschneiden, Drehen')],
            ...(versionsEl && !versionsEl.hidden ? [['V', t('nächste Bearbeitung')]] : [])] });
        const brushRows = [[t('Shift + Mausrad'), t('Pinselgröße')], [t('Ctrl + Mausrad'), t('weiche Kante')]];
        if (developTab === 'spots') {
            out.push({ title: t('Ausbessern'), rows: [['1', t('Angleichen')], ['2', t('Kopieren')], ['3', t('Entfernen')],
                ...brushRows, [t('R halten + Mausrad'), t('Quelle der gewählten Stelle drehen')], [t('Entf'), t('gewählte Stelle löschen')]] });
        }
        if (developTab === 'masks') {
            out.push({ title: t('Masken'), rows: [['1', t('neue Ellipse')], ['2', t('neuer Verlauf')], ['3', t('neuer Pinsel')]] });
            if (brushOn()) {
                out.push({ title: t('Pinsel'), rows: [['B', t('Malen')], ['E', t('Radieren')], ['X', t('Malen und Radieren wechseln')],
                    [t('Option halten'), t('Malen und Radieren wechseln, solange gehalten')], ...brushRows] });
            }
        }
        return out;
    });

    /* THE KEY O HELD shows the original (photoKeys); let go, the development
       again. Until 15.11.2026 a button „Original" in the head did the same
       when held — it went for the Original's place „0" in the bar
       „Bearbeitung" (versionsOf), which shows it without holding. */
    function developBeforeSet(on) {
        if (developBefore === on) return;
        developBefore = on;
        // The sliders say what the picture shows: home while it is held, their values again after (developSlider).
        for (const row of photoWindow.querySelectorAll('.photo-develop-row[data-before]')) row.homeShow?.(on);
        viewUpdate();
    }

    /* --- Turning and cropping (stage 2, 07.11.2026) ---------------
       „Foto drehen, zuschneiden": a quarter turn either way, straightening
       by degrees, and the crop — opened with „Zuschneiden", which shows the
       whole turned picture with the crop over it, eight grips at its edges
       and corners, the inside to move it; „Fertig" closes it. A shape can
       be fixed (Original, 1:1, 4:3, 3:2, 16:9) or left free. A quarter turn
       starts the crop afresh; straightening keeps it and pulls it inside
       the picture. The view (viewUpdate) does the rest. */
    const geoOfNow = () => (develop.geo ??= { turn: 0, angle: 0 });
    const naturalSize = () => [photoImageEl.naturalWidth, photoImageEl.naturalHeight];

    /** The crop pulled in about its centre until its corners lie on the picture. */
    function cropInside(crop) {
        const [W, H] = naturalSize();
        const g = develop.geo;
        const fits = (k) => {
            const view = geoView({ geo: { ...g, crop: { ...crop, w: crop.w * k, h: crop.h * k } } }, W, H);
            return [[0, 0], [view.cw, 0], [0, view.ch], [view.cw, view.ch]].every((q) => {
                const [x, y] = view.toNat(q);
                return x >= -.5 && y >= -.5 && x <= W + .5 && y <= H + .5;
            });
        };
        if (fits(1)) return crop;
        let lo = 0, hi = 1;
        for (let i = 0; i < 20; i++) { const mid = (lo + hi) / 2; if (fits(mid)) lo = mid; else hi = mid; }
        return { ...crop, w: crop.w * lo, h: crop.h * lo };
    }

    /* WITH „ERWEITERN" THE CROP STAYS WHERE IT IS PUT
       (15.11.2026). Everywhere a crop used to be pulled inside the picture —
       when a drag ends, a shape is chosen, the picture is turned or
       mirrored — it is left alone while „Erweitern" is on in the crop's
       bar; outside „Zuschneiden" a picture that carries a filled border
       keeps reaching out the same way, so a mirror or a line of
       „Ausrichten" does not quietly take the border away (the border
       itself is computed anew, expandSync). A crop that reaches out by a
       hair is no border: it is pulled inside as ever (develop.js,
       cropOutside). */
    const cropKeepsOutside = () => (cropEditing && geoTool === 'crop' ? cropExpand : !!develop.geo?.fill);
    function cropHeld(crop) {
        if (!cropKeepsOutside()) return cropInside(crop);
        return cropOutside({ ...develop.geo, crop }, ...naturalSize()) ? crop : cropInside(crop);
    }

    /* AIR ROUND THE PICTURE FOR A FRAME THAT REACHES OUT: while one crops
       with „Erweitern", the view is the whole turned picture and a margin
       round it — a share of its width and height on every side —, and the
       frame goes as far as the view does. The margin is fitted when a drag
       ENDS, never while a hand moves: at least CROP_ROOM_MIN, a little
       more than the frame reaches out, CROP_ROOM_MAX at the most — two
       fifths more picture on a side is past what the models fill well
       (expand.js), and the stored crop has its own bounds
       (includes/photos.php). True where the margin changed. */
    const CROP_ROOM_MIN = .12, CROP_ROOM_MAX = .4;
    function cropRoomFit() {
        const was = cropRoom;
        const [W, H] = naturalSize();
        if (!(cropEditing && geoTool === 'crop' && cropExpand) || !W) cropRoom = 0;
        else {
            const g = geoOfNow();
            const crop = g.crop ?? cropFit(g.turn ?? 0, g.angle ?? 0, W, H);
            const whole = geoView(develop, W, H, true);
            const [qx, qy] = whole.toView([crop.x * W, crop.y * H]);
            const hw = crop.w * W / 2, hh = crop.h * W / 2;
            const over = Math.max(0, (hw - qx) / whole.cw, (qx + hw - whole.cw) / whole.cw, (hh - qy) / whole.ch, (qy + hh - whole.ch) / whole.ch);
            // In steps of a twentieth, so the view does not breathe with every drag. A frame out beyond the most stays in sight.
            cropRoom = Math.max(CROP_ROOM_MIN, Math.min(Math.max(CROP_ROOM_MAX, over), Math.ceil((over + .06) * 20) / 20));
        }
        return cropRoom !== was;
    }

    function geoChanged({ fresh = false } = {}) {
        const g = geoOfNow();
        if (fresh) delete g.crop;
        else if (g.crop) g.crop = cropHeld(g.crop);
        if (!g.turn && !g.angle && !g.crop && !g.flip) delete develop.geo;
        cropRoomFit();
        photoZoom.reset();
        developQueue();
        developPanelDraw();
        viewUpdate();
    }

    /** The ratio a chip stands for, in the turned picture's own orientation for „Original". */
    function ratioOf(name) {
        const [W, H] = naturalSize();
        const side = (geoOfNow().turn ?? 0) % 180 !== 0;
        return { original: side ? H / W : W / H, '1:1': 1, '4:3': 4 / 3, '3:2': 3 / 2, '16:9': 16 / 9 }[name] ?? null;
    }

    /* „ZUSCHNEIDEN" STARTS IN THE ORIGINAL'S SHAPE (11.11.2026:
       „Das Zuschneiden-Werkzeug soll initial immer auf originales Verhältnis
       stehen"). Until then it kept whatever shape was chosen last — for
       every picture after it too, „Frei" at first. A picture not yet cut is
       the original's shape, so „Original" is chosen and a drag keeps it.
       A picture already cut keeps ITS shape: the chip that matches it
       (Original first; upright or wide, as a drag takes it either way), and
       „Frei" where none does — „Original" over a square frame would snap it
       to another shape at the first drag. */
    function cropRatioStart() {
        const crop = develop.geo?.crop;
        if (!crop) return 'original';
        const shape = crop.w / (crop.h || 1);
        for (const key of ['original', '1:1', '4:3', '3:2', '16:9']) {
            const r = ratioOf(key);
            if (r && (Math.abs(shape / r - 1) < .01 || Math.abs(shape * r - 1) < .01)) return key;
        }
        return null;
    }

    /** The crop given a fixed shape: the largest of that shape inside the crop as it stands, about its centre. */
    function cropToRatio(ratio) {
        const [W, H] = naturalSize();
        const g = geoOfNow();
        const crop = g.crop ?? cropFit(g.turn ?? 0, g.angle ?? 0, W, H);
        // A shape keeps the crop's lie: wide stays wide, tall stays tall.
        const r = crop.w >= crop.h ? Math.max(ratio, 1 / ratio) : Math.min(ratio, 1 / ratio);
        const w = Math.min(crop.w, crop.h * r);
        g.crop = cropHeld({ ...crop, w, h: w / r });
    }

    /* The straightening in degrees, on the „Zuschneiden, Drehen" button — kept current
       while a corner is dragged, without drawing the panel anew. */
    let turnDegrees = null;
    let turnDegreesBar = null;          // the same number in the bar over the picture while turning (cropBarSync)
    function turnDegreesSay() {
        /* THE WHOLE TURN, quarter turns and straightening together, between
           −180° and 180°: since a hand turns the picture all the way round
           (the turn drag below), the straightening alone would run to 45°
           and jump to −45°. */
        const g = develop.geo;
        let a = ((Number(g?.turn) || 0) + (Number(g?.angle) || 0) + 540) % 360 - 180;
        if (a === -180) a = 180;
        a = Math.round(a * 10) / 10;
        const said = a.toLocaleString('de-DE', { maximumFractionDigits: 1 }) + '°';
        if (turnDegreesBar) turnDegreesBar.textContent = said;
        if (!turnDegrees) return;
        turnDegrees.textContent = a ? ' ' + said : '';
    }

    function geoPanel(may) {
        const box = document.createElement('div');
        box.className = 'photo-develop-geo';
        const head = document.createElement('div');
        head.className = 'list-label';
        head.textContent = t('Drehen und zuschneiden');
        const row = document.createElement('div');
        row.className = 'button-row';
        /* `set`: the tool's dot (developTabsMark) — what it did stands on the
           picture. The angle wears it on „Zuschneiden, Drehen" alone:
           „Ausrichten" sets the same angle, and two dots for one value would
           count it twice.
           A half turn lights both quarter turns, since either pressed twice
           made it. */
        const button = (label, act, { active = false, title = '', icon: sign = null, set = false } = {}) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small' + (active ? ' is-active' : '') + (set ? ' is-set' : '');
            if (sign) b.innerHTML = iconHtml(sign);
            b.append(label);
            if (title) b.title = title;
            b.disabled = !may;
            b.addEventListener('click', act);
            return b;
        };
        const g = develop.geo ?? { turn: 0, angle: 0 };
        const quarter = ((Number(g.turn) || 0) % 360 + 360) % 360;
        const flipped = String(g.flip ?? '');
        /* THREE TOOLS ON ONE OVERLAY (08.11.2026: „beim Drehen
           beziehungsweise gerade ausrichten hätte ich gerne einfach nur eine
           Schaltfläche, und an den Ecken Anfasser … für ein gerade
           Ausrichten-Werkzeug … eine Linie, die entweder senkrecht oder
           waagrecht ist, und danach richtet sich dann das Bild aus"). The
           slider of degrees went: „Drehen" shows the whole picture with a
           round grip at each corner of the frame, „Ausrichten" (until 09.11.2026 „Gerade ausrichten") takes
           one line drawn along what should stand straight, „Zuschneiden"
           the frame's edges. All three open the same view, Escape undoes
           each, „Fertig" keeps it. A tool's button pressed again closes it. */
        const tool = (name, label, title, sign = null, set = false) => button(label, () => geoToolToggle(name),
            { active: cropEditing && geoTool === name, title, icon: sign, set });
        /* MIRRORED AS ONE SEES IT (09.11.2026: „Dann brauchen
           wir bei den Werkzeugen noch horizontal spiegeln, vertikal
           spiegeln. Hier gibt es ja schon Symbole für") — the statistics'
           two mirror signs. Horizontal swaps left and right of what the
           window shows, whatever the turn (develop.js, geoMirrorShown);
           the crop stays where it is, since a mirror about its middle keeps
           the same piece of the picture in it. */
        const mirror = (axis) => () => {
            develop.geo = geoMirrorShown(geoOfNow(), axis);
            geoChanged();
        };
        /* THREE GROUPS (09.11.2026: „links drehen, rechts
           drehen, drehen, ausrichten. Zusammen dann die Spiegelwerkzeuge
           zusammen. Dann zuschneiden, extra"): everything that turns, the
           two mirrors, the crop — each a row of its own, apart from the
           next (.photo-develop-group), and the open tool's hint, shapes and
           „Fertig" right under its own group. */
        row.append(
            button(t('↺ 90° links drehen'), () => { const x = geoOfNow(); x.turn = ((x.turn ?? 0) + 270) % 360; geoChanged({ fresh: true }); },
                { set: quarter === 270 || quarter === 180 }),
            button(t('↻ 90° rechts drehen'), () => { const x = geoOfNow(); x.turn = ((x.turn ?? 0) + 90) % 360; geoChanged({ fresh: true }); },
                { set: quarter === 90 || quarter === 180 }),
        );
        const mirrors = document.createElement('div');
        mirrors.className = 'button-row';
        mirrors.append(
            button(t('Horizontal spiegeln'), mirror('h'), { icon: 'flip-h', title: t('Links und rechts tauschen'), set: flipped.includes('h') }),
            button(t('Vertikal spiegeln'), mirror('v'), { icon: 'flip-v', title: t('Oben und unten tauschen'), set: flipped.includes('v') }),
        );
        const tools = row;
        tools.append(
            /* „AUSRICHTEN", NOT „GERADE AUSRICHTEN" (09.11.2026),
               with Photoshop's spirit level beside it (icons.php,
               straighten); „Zuschneiden" wears the crop's corners. */
            tool('level', t('Ausrichten'), t('Zieh eine Linie an etwas entlang, das waagrecht oder senkrecht stehen soll.'), 'straighten'),
        );
        /* THE BIN BEHIND THE TWO (09.11.2026: „Drehen und
           Ausrichten. Beides ist ja nur eine Entweder-oder-Möglichkeit …
           hintendran noch einen Mülleimer-Button … um das eben wieder
           zurückzusetzen"). Both set the one straightening angle, so one
           button takes it back to 0° — the quarter turns, the mirror and
           the crop stay (cropInside keeps the crop on the picture). It
           stands only while there is an angle to take back. */
        const bin = (words, act) => {
            const b = button('', act, { icon: 'delete', title: words });
            b.classList.add('square');
            b.setAttribute('aria-label', words);
            return b;
        };
        if (g.angle) {
            tools.append(bin(t('Drehen und Ausrichten zurücksetzen'), () => {
                geoOfNow().angle = 0;
                geoChanged();
            }));
        }
        /* THE CROP HAS ITS OWN BIN (the same day: „drehen ist
           ein eigener Punkt und Ausschnitt ist ein eigener Punkt. Und beides
           sollte gesondert zurücksetzbar sein"). It replaced the one button
           „Drehen und Ausschnitt zurücksetzen" under the panel, which threw
           away turn, mirror, angle and crop together. Quarter turns and
           mirrors need none — their own buttons take them back. */
        const crops = document.createElement('div');
        crops.className = 'button-row';
        /* „ZUSCHNEIDEN, DREHEN" — ONE TOOL (11.11.2026:
           „zuschneiden und drehen, die beiden Werkzeuge, könnten wir
           eigentlich kombinieren … außerhalb des Bildes klicken, das Drehen
           ermöglichen … den Drehen-Button oben … entfernen und dafür den
           Zuschneiden-Button in Zuschneiden, Drehen umbenennen"), as
           Lightroom has it: the frame's edges and corners cut, its inside
           moves it, everything outside it turns the picture beneath it
           (turnZoneAt). The tool „Drehen" with its four round corner grips
           went; its angle and its dot moved onto this button. */
        const cropTool = tool('crop', t('Zuschneiden, Drehen'), t('Zieh an Kanten und Ecken, um zuzuschneiden, und außerhalb des Rahmens, um zu drehen.'),
            'crop', !!g.crop || !!Number(g.angle));
        const degrees = document.createElement('span');
        degrees.className = 'photo-develop-degrees';
        turnDegrees = degrees;
        turnDegreesSay();
        cropTool.append(degrees);
        crops.append(cropTool);
        if (g.crop) {
            crops.append(bin(t('Zuschneiden zurücksetzen'), () => {
                delete geoOfNow().crop;
                cropRatio = null;
                geoChanged();
            }));
        }
        const group = (rowEl) => {
            const el = document.createElement('div');
            el.className = 'photo-develop-group';
            el.append(rowEl);
            return el;
        };
        const turning = group(row), mirroring = group(mirrors), cropping = group(crops);
        box.append(head, turning, mirroring, cropping);
        // The open tool's words and buttons go under its own group.
        const editAt = geoTool === 'crop' ? cropping : turning;
        if (cropEditing) {
            const say = document.createElement('p');
            say.className = 'hint-text small';
            say.textContent = {
                level: t('Zieh eine Linie an etwas entlang, das waagrecht oder senkrecht stehen soll. Das Bild richtet sich danach aus.'),
                crop: t('Zieh an den Kanten und Ecken. Innen verschiebst du den Rahmen, außerhalb drehst du das Bild. Mit gedrückter Umschalttaste bleiben die Form und die Mitte gleich.'),
            }[geoTool];
            const done = document.createElement('div');
            done.className = 'button-row';
            done.append(button(t('Fertig'), () => {
                cropEditing = false;
                cropBefore = null;
                photoZoom.reset();
                developPanelDraw();
                viewUpdate();
            }, { title: t('Esc bricht ab und lässt alles, wie es vorher war') }));
            editAt.append(say);
            // Cropping and turning keep „Fertig" in the bar over the picture (cropBarSync).
            if (geoTool === 'level') editAt.append(done);
        }
        cropBarSync();
        return box;
    }

    /* THE CROP'S BAR OVER THE PICTURE (09.11.2026: „Nur oben
       wird eingeblendet frei, original und halt die Verhältnisse … und
       bekommt auch oben hinter den Verhältnissen noch den Button fertig.
       Also das ist quasi dann nicht mehr in der Sidebar, sondern als
       eigene fliegende Buttonleiste im oberen Bereich"). While „Zuschneiden"
       is open the shapes and „Fertig" float at the top of the picture, in
       the dress of the aiming hint (.photo-frame-hint) — the eye stays on
       the picture, which is all black round the crop (cropDraw). Escape
       still undoes it all. */
    function cropBarSync() {
        /* „DREHEN" GETS THE SAME DARK ROOM (11.11.2026: „für
           Drehen genauso wie fürs Zuschneiden so einen dunklen neutralen
           Bildschirm … so ein eigenes Modal"): the whole window black, the
           picture, its frame — and in the bar the angle as it is turned,
           „Abbrechen" and „Fertig". „Ausrichten" closes itself after its one
           line and keeps the page. */
        const on = cropEditing && geoTool === 'crop' && developMode;
        /* THE WHOLE PAGE BLACK WHILE CROPPING (09.11.2026: „ich
           meine die komplette Seite soll schwarz sein … keine Sidebar mehr
           sehen, keine Buttons mehr, gar nichts. Nur noch einen schwarzen
           Bildschirm. Und darin das Bild … den Inhalt von YASAIMAPP schwarz
           machen"). The picture's frame lies over the whole window
           (.is-crop-stage in photowindow.css), holding only the
           picture, its crop and this bar; the browser's own bars stay, a
           web page cannot reach them. When the frame changes its size the
           view is fitted anew — once, after the class has taken effect. */
        if (photoFrame.classList.contains('is-crop-stage') !== on) {
            photoFrame.classList.toggle('is-crop-stage', on);
            queueMicrotask(viewUpdate);
        }
        if (!on) {
            if (cropBar) cropBar.hidden = true;
            return;
        }
        if (!cropBar) {
            const hint = document.getElementById('photo-frame-hint');
            cropBar = document.createElement('div');
            cropBar.className = 'photo-frame-hint photo-crop-bar';
            hint.after(cropBar);
        }
        cropBar.hidden = false;
        cropBar.textContent = '';
        turnDegreesBar = null;
        // The bar is rows one under the other; everything goes into the row begun last (cropBarExpandRow begins the second).
        let line = null;
        const row = () => {
            line = document.createElement('div');
            line.className = 'photo-crop-row';
            cropBar.append(line);
            return line;
        };
        row();
        const add = (label, act, active = false) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = label;
            b.classList.toggle('is-active', active);
            b.addEventListener('click', (e) => { e.stopPropagation(); act(); });
            line.append(b);
            return b;
        };
        // The angle, kept current while a hand turns (turnDegreesSay) — the button it stands on in the sidebar lies under the room.
        turnDegreesBar = document.createElement('span');
        turnDegreesBar.className = 'photo-crop-degrees';
        turnDegreesBar.title = t('Drehen');
        line.append(turnDegreesBar);
        turnDegreesSay();
        for (const [key, label] of [[null, t('Frei')], ['original', t('Original')], ['1:1', '1:1'], ['4:3', '4:3'], ['3:2', '3:2'], ['16:9', '16:9']]) {
            add(label, () => {
                cropRatio = key;
                if (key) cropToRatio(ratioOf(key));
                developQueue();
                developPanelDraw();
                viewUpdate();
            }, cropRatio === key);
        }
        /* „ERWEITERN" (15.11.2026: „bau das in unser Zuschneiden,
           Drehen, Werkzeug ein. Die Bedienung von Zuschneiden drehen würde
           ich genauso lassen, wie wir sie jetzt haben"). One switch more in
           the bar, and nothing else changes in the hand: with it on, the
           frame is no longer pulled inside the picture, and what it shows
           beyond the picture is filled (expandSync). Off, everything is as
           it was. */
        const wide = add(t('Erweitern'), () => cropExpandSet(!cropExpand), cropExpand);
        wide.classList.add('photo-crop-expand');
        wide.title = t('Der Rahmen darf über das Bild hinausragen. Was außerhalb liegt, füllt die KI.');
        wide.setAttribute('aria-pressed', String(cropExpand));
        /* „ABBRECHEN" BESIDE „FERTIG" (09.11.2026: „Falls man
           sich irgendwie vertan hat, zum Beispiel im Verhältnis verkleinern
           wollte, aber im Freimodus war"): what Escape does, as a button —
           turn, mirror and crop back to what they were when „Zuschneiden"
           opened (escapeStep). */
        const cancel = add(t('Abbrechen'), () => escapeStep());
        cancel.className = 'photo-crop-cancel';
        cancel.title = t('Alles bleibt, wie es vor dem Zuschneiden war');
        const done = add(t('Fertig'), () => {
            cropEditing = false;
            cropBefore = null;
            photoZoom.reset();
            developPanelDraw();
            viewUpdate();
        });
        done.className = 'photo-crop-done';
        done.title = t('Esc bricht ab und lässt alles, wie es vorher war');
        cropBarExpandRow(add, row);
    }

    /* The crop over the whole turned picture: the outside darkened, the edges
       and corners to drag, the inside to move. In the view's own pixels. */
    /** How dark the black lies over what the frame cuts off (cropDraw). */
    const CROP_REST_VEIL = .75;
    function cropDraw(el) {
        if (!cropEditing || !pictureView) return;
        const [W, H] = naturalSize();
        const g = develop.geo ?? {};
        const crop = g.crop ?? cropFit(g.turn ?? 0, g.angle ?? 0, W, H);
        const [qx, qy] = pictureView.toView([crop.x * W, crop.y * H]);
        const w = crop.w * W, h = crop.h * W;
        const x0 = qx - w / 2, y0 = qy - h / 2;
        const u = screenUnits(1);
        /* BLACK ROUND THE CROP while cropping (09.11.2026: „Lightroom
           … kann beim Zuschneiden den Außenbereich komplett abdunkeln. Das
           hilft manchmal extrem, um den Bildausschnitt zu finden"): only
           what will remain is seen, against the frame's own black. „DREHEN"
           THE SAME (11.11.2026: „für Drehen genauso wie fürs
           Zuschneiden so einen dunklen neutralen Bildschirm"): whether the
           picture stands straight is judged inside the frame, and the
           turned corners around it only distract. „Ausrichten" keeps the
           veil — its line may run along something beyond the frame. */
        /* THE REST SHINES THROUGH, FAINTLY, ALWAYS (15.11.2026:
           „eine Option, dass ich doch das Bild, das drunter liegt und was
           abgeschnitten ist, leicht sehen kann, stark ausgegraut" — built as
           a switch „Rest zeigen" in the bar, and an hour later: „so schwach,
           wie du den Rest zeigst, können wir das dauerhaft machen. Da
           brauchen wir keinen eigenen Schalter für"). So the all-black of
           09.11.2026 above is three quarters black now: the frame still
           reads as the picture, and one sees what it leaves out — how far
           a cap still runs, where the picture ends under a frame that
           reaches past it. The PAGE round the picture stays black. */
        el('path', { d: `M0 0H${pictureView.cw}V${pictureView.ch}H0Z M${x0} ${y0}h${w}v${h}h${-w}Z`,
            'fill-rule': 'evenodd', fill: `rgba(0,0,0,${geoTool === 'level' ? .55 : CROP_REST_VEIL})` });
        /* „ERWEITERN": WHAT OF THE FRAME SHOWS NO PICTURE wears the pink of
           the brush tools — the area that will be changed
           (14.11.2026) — until the quick model's border stands in it, and
           pulses while that is computed. */
        if (geoTool === 'crop' && cropExpand && cropOutside(g, W, H) && !geoFill(developShown())) {
            const corners = [[0, 0], [W, 0], [W, H], [0, H]].map((c) => pictureView.toView(c).join(' '));
            const clip = document.createElementNS(SVGNS, 'rect');
            for (const [k, v] of Object.entries({ x: x0, y: y0, width: w, height: h })) clip.setAttribute(k, String(v));
            el('clipPath', { id: 'photo-expand-frame' }).append(clip);
            el('path', { d: `M${x0} ${y0}h${w}v${h}h${-w}Z M${corners.join('L')}Z`, 'fill-rule': 'evenodd', fill: SPOT_INK, 'fill-opacity': .5,
                'clip-path': 'url(#photo-expand-frame)', 'pointer-events': 'none', class: expandBusy ? 'photo-spot-pending' : 'photo-expand-empty' });
        }
        /* „GERADE AUSRICHTEN": the whole view takes the line — a press
           anywhere starts it, the line follows the pointer, letting go
           turns the picture (cropEnd). */
        if (geoTool === 'level') {
            el('rect', { x: 0, y: 0, width: pictureView.cw, height: pictureView.ch, fill: 'transparent',
                class: 'photo-develop-grip', 'data-crop': 'level', style: 'cursor: crosshair' });
        }
        el('rect', { x: x0, y: y0, width: w, height: h, fill: 'transparent', stroke: '#fff', 'stroke-width': 1.5 * u,
            ...(geoTool === 'crop' ? { class: 'photo-develop-grip', 'data-crop': 'move', style: 'cursor: move' } : {}) });
        /* The thirds, as a camera's grid — while a hand turns a finer one,
           eight by eight, so an edge in the picture can be held against a
           line. The lines let a press through to the frame beneath. */
        const fractions = cropDrag?.kind === 'turn' ? [1, 2, 3, 4, 5, 6, 7].map(i => i / 8) : [1 / 3, 2 / 3];
        for (const f of fractions) {
            el('line', { x1: x0 + w * f, y1: y0, x2: x0 + w * f, y2: y0 + h, stroke: 'rgba(255,255,255,.45)', 'stroke-width': u, 'pointer-events': 'none' });
            el('line', { x1: x0, y1: y0 + h * f, x2: x0 + w, y2: y0 + h * f, stroke: 'rgba(255,255,255,.45)', 'stroke-width': u, 'pointer-events': 'none' });
        }
        if (geoTool === 'level') {
            const line = cropDrag?.kind === 'level' ? cropDrag : null;
            if (line) {
                for (const [stroke, width] of [['#000', 3], ['#fff', 1.5]]) {
                    el('line', { x1: line.start[0], y1: line.start[1], x2: line.end[0], y2: line.end[1], stroke, 'stroke-width': width * u });
                }
            }
            return;
        }
        /* THE WHOLE EDGE TAKES HOLD, not only its middle grip
           (11.11.2026: „was ich noch anfassbar machen würde, wäre komplett
           die Linie … also quasi eine Seite ziehen"). An invisible band a
           dozen screen pixels wide along each side, under the eight grips,
           so a corner still wins where the two meet. */
        const sides = { n: [x0, y0, x0 + w, y0], s: [x0, y0 + h, x0 + w, y0 + h], w: [x0, y0, x0, y0 + h], e: [x0 + w, y0, x0 + w, y0 + h] };
        for (const [k, [ax, ay, bx, by]] of Object.entries(sides)) {
            el('line', { x1: ax, y1: ay, x2: bx, y2: by, stroke: 'transparent', 'stroke-width': 12 * u,
                class: 'photo-develop-grip', 'data-crop': k, style: `cursor: ${k === 'n' || k === 's' ? 'ns' : 'ew'}-resize` });
        }
        // A run of the strong model asked for in this window: what it has still to fill pulses in the frame (expandDraw).
        if (cropExpand && expandBusy && !expandBusy.quick) expandDraw(el, { x: x0, y: y0, w, h });
        const grips = { nw: [x0, y0], n: [x0 + w / 2, y0], ne: [x0 + w, y0], e: [x0 + w, y0 + h / 2],
            se: [x0 + w, y0 + h], s: [x0 + w / 2, y0 + h], sw: [x0, y0 + h], w: [x0, y0 + h / 2] };
        const cursor = { nw: 'nwse', se: 'nwse', ne: 'nesw', sw: 'nesw', n: 'ns', s: 'ns', e: 'ew', w: 'ew' };
        for (const [k, [x, y]] of Object.entries(grips)) {
            el('rect', { x: x - 6 * u, y: y - 6 * u, width: 12 * u, height: 12 * u, fill: '#fff', stroke: '#000',
                'stroke-width': 1.5 * u, class: 'photo-develop-grip', 'data-crop': k, style: `cursor: ${cursor[k]}-resize` });
        }
        // „1" and „2" at every area of the border, over everything else of the frame (expandPicksDraw).
        if (cropExpand) expandPicksDraw(el, { x: x0, y: y0, w, h });
    }

    let cropDrag = null;
    function clientView(e) {
        const r = photoImageEl.getBoundingClientRect();
        return [(e.clientX - r.left) / r.width * viewW(), (e.clientY - r.top) / r.height * viewH()];
    }
    developSvg?.addEventListener('pointerdown', (e) => {
        const grip = e.target.closest('[data-crop]');
        if (!grip || !developMay()) return;
        e.preventDefault();
        e.stopPropagation();
        const [W, H] = naturalSize();
        const g = geoOfNow();
        const crop = g.crop ?? cropFit(g.turn ?? 0, g.angle ?? 0, W, H);
        const [qx, qy] = pictureView.toView([crop.x * W, crop.y * H]);
        if (grip.dataset.crop === 'level') {
            const at = clientView(e);
            cropDrag = { id: e.pointerId, kind: 'level', start: at, end: at };
            developSvg.setPointerCapture(e.pointerId);
            return;
        }
        cropDrag = { id: e.pointerId, kind: grip.dataset.crop, start: clientView(e),
            box: { x0: qx - crop.w * W / 2, y0: qy - crop.h * W / 2, x1: qx + crop.w * W / 2, y1: qy + crop.h * W / 2 } };
        developSvg.setPointerCapture(e.pointerId);
    }, true);
    /* TURNING TAKES EVERYTHING OUTSIDE THE FRAME (11.11.2026:
       „außerhalb des Bildes alles zum Drehen machen … Wir haben ja 4 Punkte
       an den Ecken, aber warum sollen wir es nicht auch überall außerhalb
       machen?"), as Lightroom does: a press anywhere outside the crop frame
       — on the picture beyond it under the veil, or on the black round the
       picture — turns it exactly as a corner grip does, about the frame's
       middle. Since „Drehen" went into „Zuschneiden, Drehen" the same
       evening, the inside moves the frame and its edges and corners cut —
       whatever carries a grip (data-crop) is the crop's, everything beyond
       turns. The angle is read about the frame's middle AS IT STOOD ON THE
       SCREEN at the press: the view refits while it turns, and a middle that
       wandered with it would make the pointer's angle jump under a still
       hand. Space held still moves the view, and a button stays a button.
       Null where the pointer is not in that zone, else that middle. */
    function turnZoneAt(e) {
        if (!cropEditing || geoTool !== 'crop' || !developMode || !pictureView || spacePan || !developMay()) return null;
        if (e.target.closest?.('button, input, select, a, .photo-crop-bar, [data-crop], .photo-expand-pick')) return null;
        const [W, H] = naturalSize();
        const g = develop.geo ?? {};
        const crop = g.crop ?? cropFit(g.turn ?? 0, g.angle ?? 0, W, H);
        const [qx, qy] = pictureView.toView([crop.x * W, crop.y * H]);
        const r = photoImageEl.getBoundingClientRect();
        const sx = r.width / viewW(), sy = r.height / viewH();
        const mid = [r.left + qx * sx, r.top + qy * sy];
        const inside = Math.abs(e.clientX - mid[0]) < crop.w * W / 2 * sx && Math.abs(e.clientY - mid[1]) < crop.h * W / 2 * sy;
        return inside ? null : mid;
    }
    photoFrame.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || cropDrag) return;
        const mid = turnZoneAt(e);
        if (!mid) return;
        e.preventDefault();
        e.stopPropagation();
        /* WITH „ERWEITERN" A TURN KEEPS THE FRAME'S SIZE
           (15.11.2026: „wenn ich Erweitern angeschaltet habe, und das Bild
           drehe, wird mir der Ausschnitt verkleinert. Hier wäre es
           komfortabel … dass der Ausschnitt natürlich gleich groß bleibt").
           A picture not yet cut has no crop of its own: its frame is the
           largest that fits the turned picture (cropFit), and that one
           shrinks with every degree — right where the frame must stay
           inside, wrong where the border is filled anyway. So the frame as
           it stands is written down before the turn begins, and from then
           on it is a crop like one dragged by hand (cropHeld keeps it). A
           turn that ends where it began takes the note back (cropEnd), or
           an untouched picture would count as cut. */
        const g = geoOfNow();
        const pinned = cropExpand && !g.crop;
        if (pinned) g.crop = cropFit(g.turn ?? 0, g.angle ?? 0, ...naturalSize());
        cropDrag = { id: e.pointerId, kind: 'turn', mid, from: Math.atan2(e.clientY - mid[1], e.clientX - mid[0]), angle: g.angle ?? 0, turn: g.turn ?? 0, pinned };
        developSvg.setPointerCapture(e.pointerId);
    }, true);
    developSvg?.addEventListener('pointermove', (e) => {
        if (!cropDrag || cropDrag.id !== e.pointerId) return;
        e.stopPropagation();
        if (cropDrag.kind === 'turn') {
            const now = Math.atan2(e.clientY - cropDrag.mid[1], e.clientX - cropDrag.mid[0]);
            let turned = (now - cropDrag.from) * 180 / Math.PI;
            turned = ((turned + 540) % 360) - 180;
            /* A HAND TURNS THE PICTURE ALL THE WAY ROUND
               (15.11.2026: „Ich sehe gerade bei 45 Grad Drehung ist Schluss.
               Warum denn? Warum kann ich nicht 360 Grad drehen, wenn ich
               anfasse?"). It stopped there because a turn is stored as
               quarter turns plus a straightening of at most 45° either way
               — every angle can be said that way, and the buttons for the
               quarters stand in the sidebar —, and the drag set the
               straightening alone. Now it carries over: past 45° a quarter
               turn is added and the straightening starts again at −45°, so
               the picture goes on turning and what is stored stays the two
               numbers everything else reads (geoView adds them up). A
               picture not yet cut changes its frame's lie there, wide to
               tall: the largest frame of the picture as it then stands
               (cropFit). */
            const g = geoOfNow();
            let turn = ((Number(g.turn) || 0) % 360 + 360) % 360;
            let angle = ((cropDrag.turn + cropDrag.angle + turned - turn) % 360 + 540) % 360 - 180;
            while (angle > 45) { angle -= 90; turn += 90; }
            while (angle < -45) { angle += 90; turn -= 90; }
            g.turn = (turn % 360 + 360) % 360;
            g.angle = Math.round(angle * 10) / 10;
            turnDegreesSay();
            // Only the view follows while the hand moves; the crop is pulled inside when it lets go.
            viewUpdate();
            developQueue();
            return;
        }
        if (cropDrag.kind === 'level') {
            cropDrag.end = clientView(e);
            developHandles();
            return;
        }
        const [W, H] = naturalSize();
        const [vx, vy] = clientView(e);
        const dx = vx - cropDrag.start[0], dy = vy - cropDrag.start[1];
        const vw = viewW(), vh = viewH(), least = W * .02;
        let { x0, y0, x1, y1 } = cropDrag.box;
        const k = cropDrag.kind;
        if (k === 'move') {
            const mx = Math.max(-x0, Math.min(vw - x1, dx)), my = Math.max(-y0, Math.min(vh - y1, dy));
            x0 += mx; x1 += mx; y0 += my; y1 += my;
        } else if (e.shiftKey) {
            /* SHIFT GROWS AND SHRINKS FROM THE MIDDLE (11.11.2026:
               „mit gehaltener Shift-Taste und irgendeiner Größenänderung aus
               dem Zentrum vergrößern"): the frame's middle stays where it
               was, and the opposite side moves as far as the dragged one —
               at an edge and at a corner alike. Shift keeps the shape as
               well (the paragraph below): under „Frei" the frame's own,
               under a fixed shape that one. Where the frame would reach
               past the picture it stops, evenly on both sides. */
            const box = cropDrag.box;
            const ratio = cropRatio ? ratioOf(cropRatio) : null;
            const r = ratio ? ((box.x1 - box.x0) >= (box.y1 - box.y0) ? Math.max(ratio, 1 / ratio) : Math.min(ratio, 1 / ratio))
                : (box.x1 - box.x0) / ((box.y1 - box.y0) || 1);
            const mx = (box.x0 + box.x1) / 2, my = (box.y0 + box.y1) / 2;
            let hw = (box.x1 - box.x0) / 2, hh = (box.y1 - box.y0) / 2;
            if (k.includes('e')) hw += dx;
            if (k.includes('w')) hw -= dx;
            if (k.includes('s')) hh += dy;
            if (k.includes('n')) hh -= dy;
            if (k === 'n' || k === 's') hw = hh * r; else hh = hw / r;
            // Not smaller than the least frame, not past the picture — the shape kept by shrinking both alike.
            const grow = Math.max(least / 2 / Math.max(1e-6, Math.min(hw, hh)), 1);
            hw *= grow; hh *= grow;
            const fit = Math.min(1, Math.min(mx, vw - mx) / hw, Math.min(my, vh - my) / hh);
            hw *= fit; hh *= fit;
            x0 = mx - hw; x1 = mx + hw; y0 = my - hh; y1 = my + hh;
        } else {
            if (k.includes('w')) x0 = Math.max(0, Math.min(x1 - least, x0 + dx));
            if (k.includes('e')) x1 = Math.min(vw, Math.max(x0 + least, x1 + dx));
            if (k.includes('n')) y0 = Math.max(0, Math.min(y1 - least, y0 + dy));
            if (k.includes('s')) y1 = Math.min(vh, Math.max(y0 + least, y1 + dy));
            /* A fixed shape: the dragged side leads, the other follows from
               the opposite edge or corner. SHIFT KEEPS THE SHAPE THE FRAME
               HAS, also under „Frei" (09.11.2026: „selbst wenn
               man auf frei steht, und beim Ziehen einer Kante Shift drückt,
               bleibt das Verhältnis erhalten") — as in every drawing
               program; let go of it and the side is free again. Since
               11.11.2026 Shift also holds the middle (the branch above). */
            const box = cropDrag.box;
            const ratio = cropRatio ? ratioOf(cropRatio) : null;
            const own = e.shiftKey && !ratio ? (box.x1 - box.x0) / ((box.y1 - box.y0) || 1) : null;
            if (ratio || own) {
                const r = own ?? ((box.x1 - box.x0) >= (box.y1 - box.y0) ? Math.max(ratio, 1 / ratio) : Math.min(ratio, 1 / ratio));
                if (k === 'n' || k === 's') {
                    const w = (y1 - y0) * r, cx = (x0 + x1) / 2;
                    x0 = cx - w / 2; x1 = cx + w / 2;
                } else {
                    const h = (x1 - x0) / r;
                    if (k.includes('n')) y0 = y1 - h; else if (k.includes('s')) y1 = y0 + h;
                    else { const cy = (y0 + y1) / 2; y0 = cy - h / 2; y1 = cy + h / 2; }
                }
            }
        }
        const [cx, cy] = pictureView.toNat([(x0 + x1) / 2, (y0 + y1) / 2]);
        geoOfNow().crop = { x: cx / W, y: cy / H, w: (x1 - x0) / W, h: (y1 - y0) / W };
        developHandles();
    }, true);
    const cropEnd = (e) => {
        if (!cropDrag || cropDrag.id !== e.pointerId) return;
        e.stopPropagation();
        const drag = cropDrag;
        cropDrag = null;
        if (drag.kind === 'turn') {
            // The frame written down for this turn alone goes where nothing was turned (the pointerdown above).
            if (drag.pinned && (geoOfNow().angle ?? 0) === drag.angle && (geoOfNow().turn ?? 0) === drag.turn) delete geoOfNow().crop;
            geoChanged();
            return;
        }
        /* THE LINE DECIDES: whichever of waagrecht and senkrecht it lies
           nearer becomes true — a line 3° off the horizontal turns the
           picture 3° back, one 87° off it turns it the other 3° to the
           vertical. In the view's pixels, which carry the turn so far, so
           a second line corrects the first. Too short a line (a click) is
           no direction and changes nothing. */
        if (drag.kind === 'level') {
            const dx = drag.end[0] - drag.start[0], dy = drag.end[1] - drag.start[1];
            if (Math.hypot(dx, dy) < screenUnits(12)) { developHandles(); return; }
            const slope = Math.atan2(dy, dx) * 180 / Math.PI;
            const off = ((slope + 45) % 90 + 90) % 90 - 45;
            const g = geoOfNow();
            g.angle = Math.max(-45, Math.min(45, Math.round(((g.angle ?? 0) - off) * 10) / 10));
            // Done in one line, as a camera's level: the picture shows straightened, the tool closes.
            cropEditing = false;
            cropBefore = null;
            geoChanged();
            return;
        }
        const g = geoOfNow();
        if (g.crop) g.crop = cropHeld(g.crop);
        developQueue();
        // The margin round the picture follows the frame once the hand lets go (cropRoomFit); the border follows the cut.
        if (cropRoomFit()) viewUpdate();
        else {
            expandSync();
            developHandles();
        }
    };
    developSvg?.addEventListener('pointerup', cropEnd, true);
    developSvg?.addEventListener('pointercancel', cropEnd, true);

    /* --- „ERWEITERN": THE BORDER OF A CROP PAST THE PICTURE (15.11.2026) -----
       After a day with a test page for it: „Super, bau das in
       unser Zuschneiden, Drehen, Werkzeug ein. Die Bedienung von
       Zuschneiden drehen würde ich genauso lassen, wie wir sie jetzt haben.
       Die Feinheitsgrad Steuerung sowie die Einstellung der Stärke … also
       Freiheit beim Verfeinern, diese zwei Regler kannst du noch mit dazu
       nehmen." How a border is computed stands in expand.js, what it is in
       the settings and how it is drawn in develop.js (geo.fill).

       THE WAY THROUGH IT:
         1. „Erweitern" in the crop's bar lets the frame reach past the
            picture (cropHeld, cropRoomFit).
         2. Half a second after the frame rests, the QUICK model fills what
            it shows beyond the picture — a few seconds, coarse: enough to
            judge angle and cut (his words on the test page, 14.11.2026:
            „eine ganz grobe Vorschau … dann kann ich dort schon schauen,
            habe ich denn eigentlich im richtigen Winkel gedreht?"). While
            one crops it is a preview and belongs to nothing (expandPreview);
            a frame moved again lets it go.
         3. „Fertig" KEEPS it: the coarse border becomes the picture's
            (geo.fill) and is stored like the patches of „Entfernen" — a
            picture is never left with an empty border where the quick
            model is on the device.
         4. Where the server offers the strong model, EVERY AREA of the
            border wears two small buttons, „1" and „2", in that same
            window: 1 is the quick model's fill, 2 the STRONG model's —
            pressed the first time, it lets that model fill the area anew
            (minutes; the part still to do pulses, a ring with the state
            stands in the picture's middle), and from then on the two
            change places at a press. „Feinheit" and „Freiheit beim
            Verfeinern" stand in a second row of the crop's bar.

       HOW POINT 4 CAME TO BE, in three sayings of one morning
       (15.11.2026). First the strong model replaced the whole border, from
       a button in „Werkzeuge". Then: „ich glaube, das starke Modell ist
       manchmal gar nicht die bessere Wahl. Jetzt haben wir ja quasi
       zwangsläufig zum Schluss das starke Modell. Ich denke, es wäre
       besser, wenn man pro Fläche selbst entscheiden kann, welche Version
       man will. Es wird natürlich erstmal mit dem schnellen Modell
       gerechnet und dann kann man ja nochmal testen, ob beim Drehen zum
       Beispiel irgendein Eck doch mit der anderen KI besser kommt." That
       was built as a row per area in the sidebar, and he saw it: „So wie
       du es jetzt gemacht hast mit den Ecke oben rechts, oben links … in
       der Sidebar, würde ich es überhaupt nicht machen. Ich würde immer
       nur, wenn man ins Drehen-Modal kommt, dort kann man die
       KI-Einstellung ändern", and: „… einfach Variante 1, 2 machen. Ans
       Eck oder an die Fläche irgendwo dran. Wenn eben außen schwarzer Rand
       ist, dann die Schaltflächen außerhalb. Wenn nicht … dann die Flächen
       nach innen rücken", „… klicken dann auf Fertig und kommen zurück zum
       Fundbrowser. Zur Bearbeitungsansicht." So NOTHING ABOUT THE BORDER
       STANDS IN THE SIDEBAR: an area is chosen where it is seen, in the
       window with the black round it, and „Fertig" leaves with what was
       chosen. The quick model's border always stays whole beneath; the
       strong model's fill of an area lies beside it (geo.fill.fine), and
       the choice is a list of numbers (geo.fill.pick; develop.js says what
       an area is and how it gets its number).

       A BORDER BELONGS TO ONE CUT. Whatever changes the cut afterwards —
       a drag, a turn, a mirror, a line of „Ausrichten" — makes the border
       wrong: it is taken out here (expandSync, called by everything that
       changes the view), a run under way is stopped, and the quick model
       is asked again.

       WHAT IT DOES NOT DO, on purpose or not yet: no detector that hands
       blurred parts to the quick model (expand.js says why); no three
       variants to choose from as on the test page; the generated border
       cannot be retouched in „Ausbessern" (the places live in the
       original's pixels); a run does not go on when the window leaves the
       picture — it stops, and the coarse border stays. */

    /** The settings as they are DRAWN: with the border under way laid in — a run's stage, or the preview while one crops. */
    function developShown() {
        const g = develop.geo;
        if (!g?.crop) return develop;
        const live = expandRun && !expandRun.stopped && expandRun.live ? expandRun.live : cropEditing ? expandPreview : null;
        if (!live || live.at !== geoSig(g)) return develop;
        return { ...develop, geo: { ...g, fill: live } };
    }

    /** Is there a border to fill, and may this window fill it? */
    function expandOpen() {
        const g = develop.geo;
        return developMode && developOf !== null && !developBefore && developMay() && !!g?.crop && cropOutside(g, ...naturalSize());
    }

    /* THE BORDER FOLLOWS THE CUT — called whenever the view may have changed
       (viewUpdate, and where a drag of the frame ends). Cheap where nothing
       is to do: it compares one word. */
    function expandSync() {
        clearTimeout(expandTimer);
        if (developOf === null || !developMay()) return;
        const g = develop.geo, sig = geoSig(g);
        let changed = false;
        // A border made for another cut is none.
        if (g?.fill && g.fill.at !== sig) { delete g.fill; changed = true; }
        if (expandPreview && expandPreview.at !== sig) expandPreview = null;
        if (expandRun && !expandRun.stopped && expandRun.sig !== sig) { expandStop(); changed = true; }
        if (expandFailed && expandFailed !== sig) expandFailed = '';
        // „Fertig" keeps what the quick model made while one cropped.
        if (!cropEditing && expandPreview) {
            const kept = expandPreview;
            expandPreview = null;
            if (!g?.fill) { expandKeep(kept); changed = true; }
        }
        if (expandGuess && expandGuess.key !== sig) expandGuess = null;
        // The panel was drawn before this ran: the mark on its crop button follows.
        if (changed) queueMicrotask(() => { if (developTab === 'tools') developPanelDraw(); });
        if (expandWanted()) expandTimer = setTimeout(expandQuick, 500);
    }

    /** Should the quick model fill the border now? Only where nothing stands in it and nothing is on its way. */
    function expandWanted() {
        if (!expandOpen() || (cropEditing && !(cropExpand && geoTool === 'crop'))) return false;
        const sig = geoSig(develop.geo);
        if (geoFill(develop) || expandPreview?.at === sig || expandFailed === sig) return false;
        if (expandRun && !expandRun.stopped) return false;
        return !(expandBusy?.quick && expandBusy.sig === sig);
    }

    /** The picture as it stands now (its retouching in it) and the crop's view of it — what a border is computed from. */
    function expandSource() {
        const work = developRenderer_?.working();
        const [W, H] = naturalSize();
        if (!work || !W) return null;
        // A copy: the renderer's canvas is drawn anew with every place retouched, and with every other picture.
        const copy = document.createElement('canvas');
        copy.width = work.canvas.naturalWidth || work.canvas.width;
        copy.height = work.canvas.naturalHeight || work.canvas.height;
        copy.getContext('2d').drawImage(work.canvas, 0, 0, copy.width, copy.height);
        const job = expandJob(copy, geoView(develop, W, H));
        return { job, free() { job.free(); copy.width = 0; } };
    }

    /** Redraw what shows a border being computed: the picture, the pulsing area, the plate. */
    function expandShow() {
        developSoon();
        developHandles();
    }

    /** The words about the border, on the plate in the picture. */
    function expandSay(words) {
        if (expandBusy) expandBusy.words = words;
        if (expandPlate && !expandPlate.hidden) expandPlate.lastChild.textContent = words;
    }

    async function expandQuick() {
        if (!expandWanted()) return;
        const subject = developOf, sig = geoSig(develop.geo), token = ++expandToken;
        const fits = () => token === expandToken && developOf === subject && geoSig(develop.geo) === sig;
        let source = null, busy = null;
        try {
            /* NEVER ASKED FOR FROM HERE: the files' question belongs to a
               deed — the tick „Erweitern", the button „Grob füllen" —, not
               to a frame that came to rest (inpaint.js, ensure). */
            const here = (await inpaint.stored()).complete;
            if (!fits()) return;
            if (expandQuickMissing === here) {
                expandQuickMissing = !here;
                cropBarSync();
            }
            if (!here) return;
            source = expandSource();
            if (!source) return;
            busy = expandBusy = { sig, quick: true, words: t('Der Rand wird grob gefüllt …'), pending: null, w: 1, h: 1 };
            expandShow();
            if (developTab === 'tools' && !cropEditing) developPanelDraw();
            const made = await expandFill(source.job, { quick: true, halted: () => !fits(), report: (p) => {
                if (expandBusy !== busy) return;
                Object.assign(busy, { pending: p.pending, w: p.w, h: p.h });
                developHandles();
            } });
            if (!made || !fits()) return;
            const mask = expandMask(made.hole, made.w, made.h);
            const fill = { w: made.w, h: made.h, image: expandImage(made.canvas, mask), at: sig, rev: ++expandRev };
            mask.width = made.canvas.width = 0;
            if (cropEditing) expandPreview = fill;
            else expandKeep(fill);
        } catch (error) {
            if (error instanceof ExpandHalted) return;
            expandFailed = sig;
            console.warn('Erweitern:', error);
            if (fits()) host.status(error instanceof inpaint.Problem ? error.message : t('Der Rand ließ sich nicht füllen.'), true);
        } finally {
            source?.free();
            if (busy && expandBusy === busy) expandBusy = null;
            if (developOf === subject) {
                expandShow();
                if (developTab === 'tools' && !cropEditing) developPanelDraw();
            }
        }
    }

    /**
     * A computed border becomes the picture's: cut into its patches, named
     * in the settings, stored with the next save. False where it would be
     * more patches than a picture may carry.
     */
    function expandKeep(fill) {
        const cut = expandPieces(fill.image, aiId);
        fill.image.width = 0;
        const used = spotsList().reduce((n, x) => n + (x.pieces?.length ?? 0) + (x.other?.length ?? 0), 0);
        if (cut.pieces.length > FILL_PIECES_MAX || used + cut.pieces.length > AI_PIECES_MAX) {
            host.status(t('Der Rand ist für diese Feinheit zu groß.'), true);
            return false;
        }
        for (const [id, patch] of cut.patches) aiPatchKeep(id, patch);
        const g = geoOfNow();
        g.fill = { w: cut.w, h: cut.h, pieces: cut.pieces, at: fill.at };
        developQueue();
        return true;
    }

    /** Every patch the border names — the quick model's image and what the strong model made of single areas. */
    const fillPiecesCount = (f, but = 0) => (f?.pieces?.length ?? 0) + (f?.fine ?? []).reduce((n, l) => n + (l.area === but ? 0 : l.pieces.length), 0);

    /**
     * What the strong model made of ONE area becomes the picture's: cut
     * into its patches, laid beside the quick model's border (geo.fill.fine)
     * in the place of an earlier one for that area, and shown (pick). False
     * where it would be more patches than a picture may carry.
     */
    function expandKeepFine(area, image, level, strength) {
        const fill = geoFill(develop);
        if (!fill) { image.width = 0; return false; }
        const cut = expandPieces(image, aiId);
        image.width = 0;
        const used = spotsList().reduce((n, x) => n + (x.pieces?.length ?? 0) + (x.other?.length ?? 0), 0);
        const border = fillPiecesCount(fill, area) + cut.pieces.length;
        if (!cut.pieces.length || border > FILL_PIECES_MAX || used + border > AI_PIECES_MAX) {
            host.status(t('Der Rand ist für diese Feinheit zu groß.'), true);
            return false;
        }
        for (const [id, patch] of cut.patches) aiPatchKeep(id, patch);
        fill.fine = [...(fill.fine ?? []).filter((l) => l.area !== area), { area, w: cut.w, h: cut.h, pieces: cut.pieces, level, strength }]
            .sort((a, b) => a.area - b.area);
        fill.pick = [...new Set([...(fill.pick ?? []), area])].sort((a, b) => a - b);
        developQueue();
        return true;
    }

    /* „1 / 2" FOR ONE AREA: nothing is computed, the list of the
       areas that show the strong model's fill changes (geo.fill.pick). The
       strong fill's patches are fetched first where a picture opened with
       the quick one showing never asked for them — as a place of
       „Entfernen" does (spotModelSwitch). */
    async function expandPick(area, strong) {
        const fill = geoFill(develop), layer = fill?.fine?.find((l) => l.area === area);
        if (!layer || (fill.pick ?? []).includes(area) === strong || !developMay()) return;
        const subject = developOf, ids = layer.pieces.map((piece) => piece.id);
        if (strong && !ids.every((id) => aiPatchHas(id))) {
            await aiPatchesFetch(ids, aiPatchUrl(developKey(subject)));
            if (developOf !== subject || geoFill(develop) !== fill) return;
            if (!ids.every((id) => aiPatchHas(id))) {
                host.status(t('Die andere Füllung ließ sich nicht laden.'), true);
                developPanelDraw();
                return;
            }
        }
        const pick = (fill.pick ?? []).filter((n) => n !== area);
        if (strong) pick.push(area);
        if (pick.length) fill.pick = pick.sort((a, b) => a - b); else delete fill.pick;
        developSoon();
        developQueue();
        developPanelDraw();
        developHandles();
    }

    /** „Erweitern" switched in the crop's bar. Off pulls the frame back inside, as cropping always did. */
    function cropExpandSet(on) {
        cropExpand = on;
        const g = geoOfNow();
        if (!on && g.crop) g.crop = cropInside(g.crop);
        // The quick model's files are asked for here, once — a „no" leaves the border empty, the frame still reaches out.
        if (on) inpaint.ensure().then((ok) => { if (ok && cropExpand) expandSync(); }).catch(() => {});
        cropRoomFit();
        developQueue();
        developPanelDraw();
        viewUpdate();
    }

    const expandLevel = () => {
        const v = Number(settings.get('expandLevel', EXPAND_LEVEL_DEFAULT));
        return EXPAND_LEVELS.includes(v) ? v : EXPAND_LEVEL_DEFAULT;
    };
    const expandStrength = () => {
        const v = Number(settings.get('expandStrength', EXPAND_STRENGTH.default));
        return v >= EXPAND_STRENGTH.min && v <= EXPAND_STRENGTH.max ? v : EXPAND_STRENGTH.default;
    };
    /** Seconds a step of the strong model took on this device the last time — a guess until it ran once. */
    const expandPace = () => Math.min(60, Math.max(.2, Number(settings.get('expandPace', 1.5)) || 1.5));
    const expandLasts = (seconds) => (seconds < 90 ? t('%d Sekunden', Math.max(1, Math.round(seconds))) : t('%d Minuten', Math.round(seconds / 60)));

    /* THE STRONG MODEL FILLS ONE AREA — asked for by that area's button, with
       the fineness and the freedom as they stand. One after the other with
       the places of „Entfernen" (aiChain): two runs at once would share one
       graphics card and both take twice as long. AN AREA ASKED FOR WHILE
       ANOTHER IS COMPUTED WAITS ITS TURN in the same run (run.queue) — four
       corners are four presses and one walk to the coffee machine, not
       four. While it runs, every stage is shown as it grows (run.live), the
       cells still to do pulse, and the area keeps what it showed until the
       new fill is whole — a run stopped or failed leaves it as it was. */
    function expandStrongStart(area) {
        /* THE QUICK MODEL'S BORDER BECOMES THE PICTURE'S BEFORE THE STRONG
           ONE WORKS ON IT: while one crops it is a preview and belongs to
           nothing (expandPreview) — but an area's strong fill lies BESIDE
           the quick one and is counted on it (geo.fill.fine), so there has
           to be one to lie beside. */
        const sig = geoSig(develop.geo);
        if (!geoFill(develop) && expandPreview?.at === sig) {
            const kept = expandPreview;
            expandPreview = null;
            if (!expandKeep(kept)) return;
        }
        if (!expandOpen() || !geoFill(develop)) return;
        const want = { area, level: expandLevel(), strength: expandStrength(), seed: strongSeed() };
        if (expandRun && !expandRun.stopped) {
            if (expandRun.area !== area && !expandRun.queue.some((q) => q.area === area)) expandRun.queue.push(want);
            // Its „2" turns pale: it waits.
            expandShow();
            return;
        }
        const run = { subject: developOf, sig: geoSig(develop.geo), queue: [want], area: 0, stopped: false,
            live: null, mask: null, maskOf: null, under: null };
        expandRun = run;
        clearTimeout(expandTimer);
        expandToken++;
        expandBusy = run.busy = { sig: run.sig, quick: false, words: t('Starkes Modell wird gestartet …'), pending: null, w: 1, h: 1 };
        aiBusy++;
        developPanelDraw();
        // „Füllen abbrechen" comes into the crop's bar, and goes again (cropBarExpandRow).
        cropBarSync();
        expandShow();
        aiChain = aiChain.then(() => expandStrongCompute(run)).catch(() => {}).finally(() => {
            aiBusy--;
            run.stopped = true;
            run.live = null;
            run.queue.length = 0;
            run.area = 0;
            if (run.mask) run.mask.width = 0;
            if (run.under) run.under.width = 0;
            if (expandRun === run) expandRun = null;
            if (expandBusy === run.busy) expandBusy = null;
            if (developOf === run.subject) {
                expandShow();
                if (developTab === 'tools') developPanelDraw();
                cropBarSync();
                // A cut changed meanwhile has no border yet.
                expandSync();
            }
        });
    }

    /** Stop the strong model's run, and what waits behind it: at once for the eye, after the square under way for the graphics card. */
    function expandStop() {
        const run = expandRun;
        if (!run || run.stopped) return;
        run.stopped = true;
        run.live = null;
        run.queue.length = 0;
        run.area = 0;
        if (expandBusy === run.busy) expandBusy = null;
        expandShow();
        if (developTab === 'tools') developPanelDraw();
        cropBarSync();
    }

    async function expandStrongCompute(run) {
        const live = () => !run.stopped && developOf === run.subject && geoSig(develop.geo) === run.sig;
        let source = null;
        try {
            if (!live()) return;
            if (!await inpaint.ensureStrong() || !live()) return;
            // The original, not the smaller copy a recording opens with: the finer stages need its pixels.
            if (!await aiOriginal()) { if (live()) host.status(t('Das Original kam nicht an.'), true); return; }
            if (!live()) return;
            // The renderer takes the original where it has just arrived, so its working copy is that.
            developRedraw();
            source = expandSource();
            if (!source) return;
            while (run.queue.length && live()) {
                const want = run.queue.shift();
                run.area = want.area;
                if (developTab === 'tools') developPanelDraw();
                await expandStrongArea(run, source.job, want, live);
                run.area = 0;
                run.live = null;
                if (live()) {
                    expandShow();
                    if (developTab === 'tools') developPanelDraw();
                }
            }
        } catch (error) {
            if (error instanceof ExpandHalted) return;
            console.warn('Erweitern:', error);
            if (live()) host.status(error instanceof inpaint.Problem ? error.message : t('Der Rand ließ sich nicht füllen.'), true);
        } finally {
            source?.free();
        }
    }

    /* One area of the run. What stands round it — the border as it is drawn
       now, this area taken out — is laid into every stage as if it were
       picture (expand.js, expandJobArea): the model continues the
       neighbouring corner instead of a black one, and its own old fill does
       not lie over the draft of the stage before. */
    async function expandStrongArea(run, job, want, live) {
        const fill = geoFill(develop), found = fill && !fill.strong ? fillAreas(fill) : null;
        if (!found?.areas.some((a) => a.n === want.area)) return;
        // Every patch the border shows, before it is read as the surroundings.
        await aiPatchesFor(develop, developKey(run.subject));
        if (!live() || geoFill(develop) !== fill) return;
        const shown = fillImage(fill);
        if (!shown) { host.status(t('Der Rand ließ sich nicht füllen.'), true); return; }
        if (run.under) run.under.width = 0;
        run.under = fillWithout(shown, found, [want.area]);
        const areaJob = expandJobArea(job, found, want.area, run.under);
        const levels = areaJob.levels(want.level);
        // The stage as the picture shows it while it grows: the other areas as they are, this one as far as it is.
        const stageImage = (canvas, w, h) => {
            const whole = document.createElement('canvas');
            whole.width = w;
            whole.height = h;
            const ctx = whole.getContext('2d'), part = expandImage(canvas, run.mask);
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(run.under, 0, 0, w, h);
            ctx.drawImage(part, 0, 0);
            part.width = 0;
            return whole;
        };
        const maskFor = (hole, w, h) => {
            if (run.maskOf === hole) return;
            if (run.mask) run.mask.width = 0;
            run.mask = expandMask(hole, w, h);
            run.maskOf = hole;
        };
        const made = await expandFill(areaJob, { levels, strength: want.strength / 100, seed: want.seed, pace: expandPace(),
            halted: () => !live(), report: (p) => {
                if (!live()) return;
                Object.assign(run.busy, { pending: p.pending, w: p.w, h: p.h });
                expandSay(p.step > 0
                    ? t('Starkes Modell rechnet … Stufe %d von %d · Kachel %d von %d · Schritt %d von %d · noch etwa %s',
                        p.stage, p.stages, Math.max(1, p.tile), p.tiles, p.step, p.of, expandLasts(p.left))
                    : t('Starkes Modell rechnet … Stufe %d von %d', p.stage, p.stages));
                // The stage as it stands, after every square and when a stage begins — not with every step.
                if (!p.fresh) return;
                maskFor(p.hole, p.w, p.h);
                const before = run.live?.image;
                run.live = { w: p.w, h: p.h, image: stageImage(p.canvas, p.w, p.h), at: run.sig, rev: ++expandRev };
                if (before) before.width = 0;
                expandShow();
            } });
        if (!made || !live() || geoFill(develop) !== fill) return;
        settings.set('expandPace', Math.round(made.pace * 100) / 100);
        maskFor(made.hole, made.w, made.h);
        const image = expandImage(made.canvas, run.mask);
        made.canvas.width = 0;
        run.live = null;
        if (expandKeepFine(want.area, image, levels, want.strength)) host.status(t('Die Fläche ist gefüllt.'));
    }

    /** The window leaves the picture or the edit: what was under way for it is let go. */
    function expandLeave() {
        clearTimeout(expandTimer);
        expandToken++;
        expandPreview = null;
        expandFailed = '';
        expandGuess = null;
        if (expandRun && !expandRun.stopped) {
            expandRun.stopped = true;
            expandRun.live = null;
        }
        expandBusy = null;
        expandPlateSync();
    }

    /* THE RING AND THE STATE IN THE MIDDLE OF THE PICTURE
       (14.11.2026, for the test page: „mach mir eine Sanduhr … so wie wir das
       bei den KI-Tools haben, beim Pinselwerkzeug. Wo wir eine pulsierende
       Markierung haben, und eine Sanduhr … und natürlich eine
       Statusanzeige … mitten ins Bild"). A plate over the picture's frame,
       in the dress of the aiming hint; it takes no click. */
    function expandPlateSync() {
        const on = expandBusy !== null && developMode && developOf !== null && !developBefore && expandBusy.sig === geoSig(develop.geo);
        if (!on) {
            if (expandPlate) expandPlate.hidden = true;
            return;
        }
        if (!expandPlate) {
            expandPlate = document.createElement('div');
            expandPlate.className = 'photo-expand-plate';
            expandPlate.setAttribute('role', 'status');
            const ring = document.createElement('span');
            ring.className = 'photo-expand-ring';
            expandPlate.append(ring, document.createElement('span'));
            document.getElementById('photo-frame-hint').after(expandPlate);
        }
        expandPlate.hidden = false;
        expandPlate.lastChild.textContent = expandBusy.words;
    }

    /** One area as an image address, in the colour `ink` ('#rrggbb') — kept for the last few asked for. */
    const expandAreaVeils = new Map();
    function expandAreaVeil(fill, found, n, ink) {
        const key = fillKey({ ...fill, pick: [] }) + '#' + n + ink;
        if (!expandAreaVeils.has(key)) {
            if (expandAreaVeils.size > 8) expandAreaVeils.clear();
            const mask = fillAreaMask(found, n, [...[1, 3, 5].map((i) => parseInt(ink.slice(i, i + 2), 16)), 255]);
            expandAreaVeils.set(key, mask.toDataURL('image/png'));
            mask.width = 0;
        }
        return expandAreaVeils.get(key);
    }

    /* WHAT IS STILL TO FILL PULSES: the cells the run has not reached yet,
       cut to where the crop shows no picture. Before the run has said
       which cells, all of it. `at`: the crop's rectangle in the view's
       pixels — the whole view outside „Zuschneiden", the frame while one
       crops (cropDraw), where the strong model is asked for. */
    function expandDraw(el, at = null) {
        const b = expandBusy;
        if (!b || !pictureView || b.sig !== geoSig(develop.geo)) return;
        const [W, H] = naturalSize();
        const { x: rx, y: ry, w: cw, h: ch } = at ?? { x: 0, y: 0, w: pictureView.cw, h: pictureView.ch };
        const corners = [[0, 0], [W, 0], [W, H], [0, H]].map((c) => pictureView.toView(c).join(' '));
        const hole = document.createElementNS(SVGNS, 'path');
        hole.setAttribute('d', `M${rx} ${ry}h${cw}v${ch}h${-cw}Z M${corners.join('L')}Z`);
        hole.setAttribute('clip-rule', 'evenodd');
        el('clipPath', { id: 'photo-expand-hole' }).append(hole);
        const group = el('g', { 'clip-path': 'url(#photo-expand-hole)', class: 'photo-spot-pending', 'pointer-events': 'none' });
        /* ONLY THE AREA THAT IS COMPUTED PULSES. A cell is a rectangle of
           the stage, and a stage as high as one square has cells from top
           to bottom: the cell of the corner top left holds the corner
           bottom left too, and that one pulsed along though nothing was
           done to it (seen on the first run for a single area). So the
           cells are cut by the area's own image as well. */
        const area = expandRun && !expandRun.stopped ? expandRun.area : 0;
        const fill = area ? geoFill(develop) : null, found = fill && !fill.strong ? fillAreas(fill) : null;
        if (found) {
            const veil = expandAreaVeil(fill, found, area, '#ffffff');
            const image = document.createElementNS(SVGNS, 'image');
            for (const [k, v] of Object.entries({ href: veil, x: rx, y: ry, width: cw, height: ch, preserveAspectRatio: 'none' })) image.setAttribute(k, String(v));
            el('mask', { id: 'photo-expand-area', maskUnits: 'userSpaceOnUse', x: rx, y: ry, width: cw, height: ch }).append(image);
            group.setAttribute('mask', 'url(#photo-expand-area)');
        }
        for (const c of b.pending ?? [{ l: 0, t: 0, r: b.w, b: b.h }]) {
            const rect = document.createElementNS(SVGNS, 'rect');
            for (const [k, v] of Object.entries({ x: rx + c.l / b.w * cw, y: ry + c.t / b.h * ch, width: (c.r - c.l) / b.w * cw, height: (c.b - c.t) / b.h * ch,
                fill: SPOT_INK, 'fill-opacity': .5 })) rect.setAttribute(k, String(v));
            group.append(rect);
        }
    }

    /* „1" AND „2" AT EVERY AREA, IN THE CROP'S WINDOW (the header of this
       section has his words). Two small buttons per area, in the dress of
       the bar over the picture — white on black, the chosen one filled —,
       because they lie over a photograph or over the black round it:
         1   the quick model's fill;
         2   the strong model's. Not computed yet, a press computes it for
             THAT area, with the fineness and freedom of the bar; while it
             is computed the button pulses, while it waits its turn it is
             pale;
         ↻   once 2 exists: fill it again — the strong model's fill is
             another one every time. The one before stays until the new
             one is whole.
       WHERE THEY LIE („Wenn eben außen schwarzer Rand ist, dann die
       Schaltflächen außerhalb. Wenn nicht … nach innen rücken"): outside
       the frame, beside the corner or the strip the area is — above or
       below it first, beside it where there is no room for that —, and
       inside the frame only where the window leaves no room outside at
       all. Room is measured on the SCREEN, under the bar: the frame may
       lie anywhere in the window. They are drawn at the size of a button
       whatever the zoom (screenUnits), and not at all while a hand drags
       the frame or turns the picture.
       A press is taken at pointerdown, before the turn zone round the
       frame can read it as the start of a turn (turnZoneAt lets them be). */
    function expandPicksDraw(el, frame) {
        if (!developMay() || cropDrag || !pictureView) return;
        const sig = geoSig(develop.geo);
        const fill = geoFill(develop) ?? (expandPreview?.at === sig ? expandPreview : null);
        if (!fill || fill.strong) return;
        const found = fillAreas(fill);
        if (!found) {
            // The quick model's image is still on its way from the cloud: its areas are counted on it, so the buttons follow it.
            if (!fill.image && expandAreasAsked !== fillKey(fill)) {
                const subject = developOf;
                expandAreasAsked = fillKey(fill);
                aiPatchesFor(develop, developKey(subject)).then((all) => { if (all && developOf === subject) developHandles(); });
            }
            return;
        }
        const offered = !!host.start.strongModel;
        const run = expandRun && !expandRun.stopped ? expandRun : null;
        const u = screenUnits(1), S = 24 * u, G = 3 * u, M = 10 * u;
        const img = photoImageEl.getBoundingClientRect(), stage = photoFrame.getBoundingClientRect();
        const kx = img.width / viewW(), ky = img.height / viewH();
        const top = cropBar && !cropBar.hidden ? Math.max(stage.top, cropBar.getBoundingClientRect().bottom) : stage.top;
        // Screen pixels between the frame and the window's edge, on each side.
        const room = { t: img.top + frame.y * ky - top, b: stage.bottom - (img.top + (frame.y + frame.h) * ky),
            l: img.left + frame.x * kx - stage.left, r: stage.right - (img.left + (frame.x + frame.w) * kx) };
        const guess = expandGuess?.key === sig ? expandGuess.areas : null;
        if (offered && !guess) expandGuessAsk();
        const make = (parent, name, attrs) => {
            const n = document.createElementNS(SVGNS, name);
            for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, String(v));
            parent.append(n);
            return n;
        };
        for (const a of found.areas) {
            const layer = (fill.fine ?? []).find((l) => l.area === a.n) ?? null;
            if (!layer && !offered) continue;
            const busy = run?.area === a.n, waits = !!run?.queue.some((q) => q.area === a.n), strong = (fill.pick ?? []).includes(a.n);
            const lasts = () => {
                const steps = expandSteps(guess?.get(a.n) ?? [], expandLevel(), expandStrength() / 100);
                return steps ? expandLasts(steps * expandPace()) : '';
            };
            const buttons = [{ sign: '1', on: !strong, title: t('Variante 1: schnelles Modell'), act: () => expandPick(a.n, false) }];
            if (busy) buttons.push({ sign: '2', pulse: true, title: t('wird berechnet …'), act: null });
            else if (waits) buttons.push({ sign: '2', pale: true, title: t('wartet'), act: null });
            else if (layer) buttons.push({ sign: '2', on: strong, title: t('Variante 2: starkes Modell'), act: () => expandPick(a.n, true) });
            else {
                const time = lasts();
                buttons.push({ sign: '2', title: time ? t('Variante 2: mit dem starken Modell füllen – etwa %s', time) : t('Variante 2: mit dem starken Modell füllen'),
                    act: () => expandStrongStart(a.n) });
            }
            if (layer && offered && !busy && !waits) {
                buttons.push({ sign: '↻', title: t('Variante 2 noch einmal füllen – jedes Mal anders'), act: () => expandStrongStart(a.n) });
            }
            // Where the group lies: by the side or corner the area is, outside the frame where the window has room.
            const W = buttons.length * S + (buttons.length - 1) * G;
            const wide = a.x1 - a.x0 > .6, tall = a.y1 - a.y0 > .6, up = a.cy < .5, left = a.cx < .5;
            const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
            const cornerX = left ? frame.x + M : frame.x + frame.w - M - W, cornerY = up ? frame.y + M : frame.y + frame.h - M - S;
            const midX = clamp(frame.x + (a.x0 + a.x1) / 2 * frame.w - W / 2, frame.x + M, frame.x + frame.w - M - W);
            const midY = clamp(frame.y + (a.y0 + a.y1) / 2 * frame.h - S / 2, frame.y + M, frame.y + frame.h - M - S);
            const roomV = (up ? room.t : room.b) >= 44, roomH = (left ? room.l : room.r) >= W / u + 20;
            const outY = up ? frame.y - M - S : frame.y + frame.h + M, outX = left ? frame.x - M - W : frame.x + frame.w + M;
            let x, y;
            if (tall && !wide) [x, y] = roomH ? [outX, midY] : [cornerX, midY];
            else if (roomV) [x, y] = [wide ? midX : cornerX, outY];
            else if (!wide && roomH) [x, y] = [outX, cornerY];
            else [x, y] = [wide ? midX : cornerX, cornerY];
            buttons.forEach((b, i) => {
                const g = el('g', { class: 'photo-expand-pick' + (b.on ? ' is-active' : '') + (b.pulse ? ' photo-spot-pending' : '') + (b.pale ? ' is-waiting' : ''),
                    transform: `translate(${x + i * (S + G)} ${y})`, role: 'button', 'aria-label': b.title, ...(b.on ? { 'aria-pressed': 'true' } : {}) });
                make(g, 'title', {}).textContent = b.title;
                make(g, 'rect', { width: S, height: S, rx: 5 * u, fill: b.on ? '#fff' : 'rgba(0,0,0,.65)', stroke: 'rgba(255,255,255,.6)', 'stroke-width': u });
                make(g, 'text', { x: S / 2, y: S / 2, 'text-anchor': 'middle', 'dominant-baseline': 'central', 'font-size': 13 * u, 'font-weight': 700,
                    fill: b.on ? '#000' : '#fff' }).textContent = b.sign;
                g.addEventListener('pointerdown', (e) => {
                    if (e.button !== 0) return;
                    e.preventDefault();
                    e.stopPropagation();
                    b.act?.();
                });
            });
        }
    }

    /* HOW LONG THE STRONG MODEL WOULD TAKE, said before it starts — so „Sehr
       fein" is not chosen blind: in the tooltip of an area's „2", and in
       the tooltips of „Feinheit" for the whole border against „Grob"
       (cropBarExpandRow). Minutes at the speed this device showed the last
       time. Counted a moment after the buttons stand, for all areas and
       all finenesses in one go (expand.js, expandCountAreas), and kept for
       the cut it was counted for. */
    function expandGuessAsk() {
        const sig = geoSig(develop.geo);
        if (expandGuessWanted === sig) return;
        expandGuessWanted = sig;
        setTimeout(() => {
            if (geoSig(develop.geo) !== sig || !expandOpen()) { expandGuessWanted = ''; return; }
            const fill = geoFill(develop) ?? (expandPreview?.at === sig ? expandPreview : null);
            const found = fill && !fill.strong ? fillAreas(fill) : null;
            const source = found ? expandSource() : null;
            if (!source) { expandGuessWanted = ''; return; }
            try {
                expandGuess = { key: sig, areas: expandCountAreas(source.job, found) };
            } finally {
                source.free();
            }
            developHandles();
            // The tooltips of „Feinheit" say what each would take for this border.
            cropBarSync();
        }, 60);
    }

    /* THE STRONG MODEL'S TWO CONTROLS, IN A SECOND ROW OF THE CROP'S BAR —
       „Feinheit" and „Freiheit beim Verfeinern", where the areas are chosen
       („… wenn man ins Drehen-Modal kommt, dort kann man die KI-Einstellung
       ändern"). Only with „Erweitern" on and where the server offers the
       strong model; they say how the NEXT press of a „2" fills. Beside
       them, each only when it has something to do: stop the run under way,
       fill an empty border by hand, fill a border of the first day anew.
       NO EXPLANATION ON THE BAR: words, the rest in the tooltips. */
    function cropBarExpandRow(add, row) {
        if (!cropExpand || !cropOutside(develop.geo, ...naturalSize())) return;
        const offered = !!host.start.strongModel;
        const sig = geoSig(develop.geo), fill = geoFill(develop);
        const empty = !fill && expandPreview?.at !== sig && !expandBusy && expandQuickMissing;
        const running = !!expandRun && !expandRun.stopped;
        if (!offered && !empty && !fill?.strong) return;
        const line = row();
        const word = (text, title = '') => {
            const w = document.createElement('span');
            w.className = 'photo-crop-word';
            w.textContent = text;
            w.title = title;
            line.append(w);
            return w;
        };
        if (offered) {
            const level = expandLevel();
            word(t('Feinheit'));
            /* WHAT A FINENESS COSTS, IN ITS TOOLTIP (15.11.2026:
               „Schreibt das bitte in den Tooltip mit dazu. 4-mal so lang
               wie grob, 8-mal so lang wie grob oder sowas"). Not a fixed
               factor — it depends on the border: a thin strip grows by
               two per stage, a wide one by four, and the freedom sets how
               many steps a finer stage runs. So it is counted for THIS
               border (expandGuessAsk) and said against „Grob", with the
               minutes for all areas together; until the count is there,
               and for a border not yet filled, the tooltip says what the
               fineness is and no more. */
            const guess = expandGuess?.key === sig ? [...expandGuess.areas.values()] : null;
            if (!guess) expandGuessAsk();
            const levels = [];
            const cost = () => {
                const all = (L) => (guess ?? []).reduce((n, stages) => n + expandSteps(stages, L, expandStrength() / 100), 0);
                const coarse = all(1), most = Math.max(0, ...(guess ?? []).map((stages) => stages.length));
                for (const { b, value, hint } of levels) {
                    if (!coarse) b.title = hint;
                    else if (value > most) b.title = hint + ' ' + t('Dieses Bild ist dafür zu klein: Es wird nicht feiner als bei der Stufe davor.');
                    else if (value === 1) b.title = hint + ' ' + t('Alle Flächen zusammen etwa %s.', expandLasts(coarse * expandPace()));
                    else {
                        const times = all(value) / coarse;
                        b.title = hint + ' ' + t('Dauert etwa %s-mal so lange wie „Grob“ – alle Flächen zusammen etwa %s.',
                            (times < 4 ? Math.round(times * 2) / 2 : Math.round(times)).toLocaleString('de-DE'), expandLasts(all(value) * expandPace()));
                    }
                }
            };
            for (const [value, words, hint] of [
                [1, t('Grob'), t('Eine Stufe: schnell, aber unscharf.')],
                [2, t('Mittel'), t('Einmal in doppelter Größe nachgearbeitet.')],
                [3, t('Fein'), t('Zweimal nachgearbeitet, jedes Mal in doppelter Größe.')],
                [4, t('Sehr fein'), t('Bis zur vollen Größe des Bildes nachgearbeitet.')],
            ]) {
                const b = add(words, () => {
                    settings.set('expandLevel', value);
                    cropBarSync();
                    developHandles();
                }, value === level);
                b.setAttribute('aria-pressed', String(value === level));
                levels.push({ b, value, hint });
            }
            cost();
            const hint = t('Wie weit das starke Modell beim Nacharbeiten vom Entwurf abweichen darf. Niedrig: bleibt nah am Entwurf und wird weniger scharf. Hoch: erfindet mehr Einzelheiten und kann vom Entwurf abweichen – und rechnet länger, weil jede Kachel mehr Schritte braucht. Doppelklick setzt zurück.');
            word(t('Freiheit'), hint).classList.add('photo-crop-apart');
            const range = document.createElement('input');
            range.type = 'range';
            range.className = 'photo-crop-range';
            range.min = String(EXPAND_STRENGTH.min);
            range.max = String(EXPAND_STRENGTH.max);
            range.step = String(EXPAND_STRENGTH.step);
            range.value = String(expandStrength());
            range.title = hint;
            range.setAttribute('aria-label', t('Freiheit beim Verfeinern'));
            // The first stage is made from nothing: there is no draft to keep near to.
            range.disabled = level < 2;
            const value = word(String(expandStrength()));
            value.classList.add('photo-crop-degrees', 'photo-crop-value');
            const set = (v) => {
                settings.set('expandStrength', v);
                range.value = String(v);
                value.textContent = String(v);
                // What the finenesses cost follows the freedom — without drawing the bar anew under the hand on the slider.
                cost();
                developHandles();
            };
            range.addEventListener('input', () => set(Number(range.value)));
            range.addEventListener('dblclick', () => set(EXPAND_STRENGTH.default));
            for (const type of ['pointerdown', 'click']) range.addEventListener(type, (e) => e.stopPropagation());
            line.insertBefore(range, value);
        }
        const apart = (b) => { b.classList.add('photo-crop-apart'); return b; };
        if (running) apart(add(t('Füllen abbrechen'), () => expandStop())).title = t('Die Fläche bleibt, wie sie vorher war');
        if (empty) {
            apart(add(t('Grob füllen'), async () => {
                if (!await inpaint.ensure().catch(() => false)) return;
                expandQuickMissing = false;
                expandFailed = '';
                cropBarSync();
                expandSync();
            })).title = t('Das schnelle Modell füllt den Rand in wenigen Sekunden.');
        }
        /* A BORDER OF THE FIRST DAY, which the strong model filled in the
           quick one's place: it has no second fill to go back to and no
           areas. One button takes it out and lets the quick model fill the
           border again — after that every area has its „1" and „2". */
        if (fill?.strong && !expandBusy) {
            apart(add(t('Grob neu füllen'), async () => {
                if (!await inpaint.ensure().catch(() => false) || geoFill(develop) !== fill) return;
                delete develop.geo.fill;
                expandFailed = '';
                developQueue();
                viewUpdate();
            })).title = t('Das schnelle Modell füllt den Rand neu. Danach wählst du für jede Fläche zwischen Variante 1 und 2. Die jetzige Füllung geht dabei verloren.');
        }
    }

    /* --- The ellipses on the picture: grips, and laying a new one ---------- */

    /** A pointer as picture pixels — off the picture too (a grip may be dragged beyond its edge). */
    function picturePx(e) {
        return clientNatural(e.clientX, e.clientY)?.p ?? [0, 0];
    }

    const SVGNS = 'http://www.w3.org/2000/svg';
    /* THE MASKS ON THE PICTURE (07.11.2026, after Lightroom):
       only in the tab „Masken", and only the CHOSEN mask shows its lines —
       the others are a small pin each, to pick one; a click beside them
       on the picture, Escape or another tab takes the lines away, so the
       finished picture never stays under a dashed line.

       AN ELLIPSE SHOWS TWO: the outer one, where its effect ends, and the
       inner one, from where it acts in full — the ring between them is the
       soft passage („den Verlaufsbereich gleich deutlich sieht"). Grabbing
       the outer line makes it larger or smaller as a whole, grabbing the
       inner one widens or narrows the passage; the squares still pull one
       axis, the dark dot above turns it, the middle moves it.

       A GRADIENT SHOWS THREE LINES across the picture: the full effect up
       to the solid one, none from the dashed one, the thin one between
       marks the middle. Its two dots set where each line goes (and so the
       slope), a line itself is pushed parallel, the middle moves all. */
    function developHandles() {
        if (!developSvg) return;
        spotRing = null;
        // The red veil follows the lines: a brush chosen and the lines not stepped aside (maskOverlay).
        if (maskOverlay() !== maskShown) developSoon();
        // The masks' little pictures follow every drag (maskThumbDraw).
        radialListEl?.querySelectorAll('canvas').forEach(maskThumbDraw);
        const masksOn = developTab === 'masks';
        const open = photoWindow.open && developMode && !developBefore && photoImageEl.naturalWidth > 0;
        const own = open
            && (cropEditing || ((masksOn || developTab === 'spots') && !maskLinesQuiet)
                // A place the AI is filling shows even with the lines stepped aside: it is a state, not a line.
                || (developTab === 'spots' && spotsList().some((x) => x.busy))
                // And the chosen place while the pointer is over its sliders (spotPanelShow).
                || (developTab === 'spots' && spotPanelHover && spotOf(spotChosen) !== null)
                // And the mask in hand, beside the picture too, until a slider of it moves (maskSliding).
                || (masksOn && maskChosen !== null && !maskSliding && !maskBare));
        // A border the AI is filling shows in every tab, like a place it fills: a state, not a line (expandDraw).
        const show = own || (open && expandBusy !== null);
        expandPlateSync();
        // An SVG element has no `hidden` property of its own (it is no HTMLElement) — the attribute it is.
        developSvg.toggleAttribute('hidden', !show);
        developSvg.textContent = '';
        if (!show) return;
        const nw = photoImageEl.naturalWidth, nh = photoImageEl.naturalHeight;
        const u = screenUnits(1);
        const may = developMay();
        // The masks live in the picture's pixels: inside the view's turn and cut.
        const group = document.createElementNS(SVGNS, 'g');
        if (pictureView) group.setAttribute('transform', pictureView.svg);
        developSvg.append(group);
        const into = (parent) => (name, attrs) => {
            const n = document.createElementNS(SVGNS, name);
            for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, String(v));
            parent.append(n);
            return n;
        };
        const el = into(group);
        if (cropEditing) {
            cropDraw(into(developSvg));
            return;
        }
        expandDraw(into(developSvg));
        if (!own) return;
        // A line drawn twice — dark under light — reads on every picture.
        const twice = (name, attrs, dash = 'none') => {
            for (const [stroke, width] of [['#000', 3], ['#fff', 1.5]]) {
                el(name, { ...attrs, fill: 'none', stroke, 'stroke-width': width * u, 'stroke-dasharray': dash });
            }
        };
        const grip = (name, attrs, g, kind, i) => el(name, { ...attrs, class: 'photo-develop-grip',
            'data-grip': g, 'data-kind': kind, 'data-i': i });
        // A line one can take hold of: wide and invisible over the drawn one.
        const hold = (name, attrs, g, kind, i) => el(name, { ...attrs, fill: 'none', stroke: 'transparent',
            'stroke-width': 12 * u, class: 'photo-develop-line', 'data-grip': g, 'data-kind': kind, 'data-i': i });
        const dash = `${6 * u} ${4 * u}`;
        if (developTab === 'spots') {
            spotsDraw(el, twice, u);
            return;
        }

        for (const kind of ['radial', 'linear', 'brush']) {
            maskList(kind).forEach((r, i) => {
                if (kind === 'brush') {
                    /* A painted mask has no lines: its red veil shows it while it
                       is chosen (the renderer's `show`), and otherwise a pin at
                       where its first stroke began — to choose it, or carry it. */
                    const first = r.strokes?.[0]?.path?.[0];
                    // Beside the picture only the mask in hand stands — the others' pins go (maskLinesQuiet).
                    if (!maskIs(kind, i) && first && !maskLinesQuiet) {
                        grip('circle', { cx: first[0] * nw, cy: first[1] * nh, r: 5 * u, fill: '#fff', 'fill-opacity': .85,
                            stroke: '#000', 'stroke-width': 1.5 * u }, 'move', kind, i);
                    }
                    return;
                }
                // Not drawn yet (maskCreate): no pin, no lines — the drag in the picture makes them.
                if (r.empty) return;
                const centre = kind === 'radial'
                    ? [r.x * nw, r.y * nh]
                    : [(r.x1 + r.x2) / 2 * nw, (r.y1 + r.y2) / 2 * nh];
                if (!maskIs(kind, i)) {
                    if (maskLinesQuiet) return;
                    grip('circle', { cx: centre[0], cy: centre[1], r: 5 * u, fill: '#fff', 'fill-opacity': .85,
                        stroke: '#000', 'stroke-width': 1.5 * u }, 'move', kind, i);
                    return;
                }
                // The mask in hand steps aside only while one of its sliders moves, or after a click on empty ground.
                if (maskSliding || maskBare) return;
                if (kind === 'radial') {
                    const [cx, cy] = centre, rx = r.rx * nw, ry = r.ry * nw, a = r.angle ?? 0;
                    // Both lines a little inside the passage (LINE_SHOWN).
                    const inner = radialInnerShown(r), outer = radialOuterShown(r);
                    const turn = `rotate(${a} ${cx} ${cy})`;
                    twice('ellipse', { cx, cy, rx: rx * outer, ry: ry * outer, transform: turn });
                    if (inner > .01) twice('ellipse', { cx, cy, rx: rx * inner, ry: ry * inner, transform: turn }, dash);
                    if (!may) return;
                    hold('ellipse', { cx, cy, rx: rx * outer, ry: ry * outer, transform: turn }, 'outer', kind, i);
                    /* CARRIED FROM ANYWHERE INSIDE THE INNER LINE
                       (09.11.2026: „Ein Klick irgendwo innerhalb der inneren
                       Ellipse verschiebt die Ellipse … den Zentrumspunkt …
                       können wir entfernen"): an invisible filled ellipse as
                       the move grip, under the inner line's band and the size
                       grips, which keep their own work. */
                    const mk = radialGripK(r);
                    grip('ellipse', { cx, cy, rx: rx * mk, ry: ry * mk, transform: turn, fill: 'transparent', stroke: 'none',
                        style: 'cursor: move' }, 'move', kind, i);
                    if (inner > .01) hold('ellipse', { cx, cy, rx: rx * inner, ry: ry * inner, transform: turn }, 'inner', kind, i);
                    const rad = a * Math.PI / 180, cos = Math.cos(rad), sin = Math.sin(rad);
                    const at = (lx, ly) => [cx + lx * cos - ly * sin, cy + lx * sin + ly * cos];
                    /* THE INNER ELLIPSE STEERS (09.11.2026: „die innere
                       Ellipse ist eigentlich die Steuerellipse … bekommt die
                       Anfasser für die Größe … außerhalb der inneren Ellipse
                       kann ich auch drehen … die äußere Ellipse [hat] noch einen
                       Anfasser fürs Drehen … Den brauchen wir gar nicht mehr").
                       The four size grips sit on the inner line (radialGripK —
                       never closer in than a fifth, so a soft rim of 100 does
                       not pile them on the centre); turning is every drag that
                       starts outside it (turnTarget); the dark dot above is gone. */
                    const k = radialGripK(r);
                    for (const [g, [x, y]] of [['rx', at(rx * k, 0)], ['rx', at(-rx * k, 0)], ['ry', at(0, ry * k)], ['ry', at(0, -ry * k)]]) {
                        grip('rect', { x: x - 5 * u, y: y - 5 * u, width: 10 * u, height: 10 * u, fill: '#fff', stroke: '#000',
                            'stroke-width': 1.5 * u }, g, kind, i);
                    }
                    return;
                }
                // The gradient: three lines square to the way from the full point to the empty one.
                // The two lines where the effect is 95 % and 5 % (LINE_SHOWN), their grips with them.
                const [p1, p2] = linShown(r, nw, nh);
                let dx = p2[0] - p1[0], dy = p2[1] - p1[1];
                const len = Math.hypot(dx, dy) || 1;
                dx /= len;
                dy /= len;
                const L = Math.hypot(nw, nh) * 3;   // far past every edge, the picture zoomed out included
                const across = ([x, y]) => ({ x1: x + dy * L, y1: y - dx * L, x2: x - dy * L, y2: y + dx * L });
                twice('line', across(p1));
                el('line', { ...across(centre), stroke: '#fff', 'stroke-width': u, 'stroke-opacity': .6 });
                twice('line', across(p2), dash);
                if (!may) return;
                hold('line', across(p1), 'line1', kind, i);
                hold('line', across(p2), 'line2', kind, i);
                grip('circle', { cx: centre[0], cy: centre[1], r: 6 * u, fill: '#fff', stroke: '#000', 'stroke-width': 1.5 * u }, 'move', kind, i);
                grip('circle', { cx: p1[0], cy: p1[1], r: 6 * u, fill: '#fff', stroke: '#000', 'stroke-width': 1.5 * u }, 'p1', kind, i);
                grip('circle', { cx: p2[0], cy: p2[1], r: 6 * u, fill: '#000', stroke: '#fff', 'stroke-width': 1.5 * u }, 'p2', kind, i);
            });
        }
        if (brushOn()) brushRingDraw(el, u);
    }

    /* A DRAG ENDS IN A CLICK, and the zoom counts clicks: two drags on a
       mask within a third of a second — a grip pulled twice, a turn after
       laying one — read as a double tap and jumped the picture to
       threefold (measured 07.11.2026). After a drag that moved something,
       the click it brings is swallowed; one that never comes is not
       waited for. */
    function swallowClick() {
        const eat = (e) => e.stopImmediatePropagation();
        photoFrame.addEventListener('click', eat, true);
        setTimeout(() => photoFrame.removeEventListener('click', eat, true), 350);
    }

    let gripDrag = null;
    developSvg?.addEventListener('pointerdown', (e) => {
        const grip = e.target.closest('[data-grip]');
        if (!grip) return;
        e.preventDefault();
        e.stopPropagation();
        const chosen = { kind: ['linear', 'brush'].includes(grip.dataset.kind) ? grip.dataset.kind : 'radial', i: Number(grip.dataset.i) };
        if (!maskIs(chosen.kind, chosen.i)) {
            maskChosen = chosen;
            developPanelDraw();
        }
        if (!developMay()) { developHandles(); return; }
        const r = maskOf(chosen);
        if (!r) return;
        gripDrag = { id: e.pointerId, kind: grip.dataset.grip, mask: chosen.kind, r, start: picturePx(e), from: { ...r } };
        developSvg.setPointerCapture(e.pointerId);
        developHandles();
    });
    developSvg?.addEventListener('pointermove', (e) => {
        if (!gripDrag || gripDrag.id !== e.pointerId) return;
        e.stopPropagation();
        const nw = photoImageEl.naturalWidth, nh = photoImageEl.naturalHeight;
        const [px, py] = picturePx(e);
        const { r, from, kind, start, mask } = gripDrag;
        const mx = (px - start[0]) / nw, my = (py - start[1]) / nh;
        if (mask === 'brush') {
            // A painted mask carried whole by its pin: every stroke moves with it.
            const four = (x) => Math.round(x * 10000) / 10000;
            r.strokes = from.strokes.map((st) => ({ ...st, path: st.path.map(([x, y]) => [four(x + mx), four(y + my)]) }));
            developSoon();
            developHandles();
            return;
        }
        if (mask === 'linear') {
            // Worked on the DRAWN lines (linShown) and stored back (linFrom), so a line follows the pointer.
            let [d1, d2] = linShown(from, nw, nh);
            const ox = px - start[0], oy = py - start[1];
            if (kind === 'move') {
                d1 = [d1[0] + ox, d1[1] + oy];
                d2 = [d2[0] + ox, d2[1] + oy];
            } else if (kind === 'p1') d1 = [d1[0] + ox, d1[1] + oy];
            else if (kind === 'p2') d2 = [d2[0] + ox, d2[1] + oy];
            else {
                // A line pushed parallel: only the part of the move along the gradient counts.
                let dx = d2[0] - d1[0], dy = d2[1] - d1[1];
                const len = Math.hypot(dx, dy) || 1;
                dx /= len;
                dy /= len;
                const along = ox * dx + oy * dy;
                if (kind === 'line1') d1 = [d1[0] + along * dx, d1[1] + along * dy];
                else d2 = [d2[0] + along * dx, d2[1] + along * dy];
            }
            if (Math.hypot(d2[0] - d1[0], d2[1] - d1[1]) > .5) Object.assign(r, linFrom(d1, d2, nw, nh));
            developSoon();
            developHandles();
            return;
        }
        const cx = from.x * nw, cy = from.y * nh;
        const rad = (from.angle ?? 0) * Math.PI / 180;
        // The pointer in the ellipse's own turned axes, and how far out on its own scale (1 = the outer line).
        const local = ([x, y]) => [(x - cx) * Math.cos(rad) + (y - cy) * Math.sin(rad), -(x - cx) * Math.sin(rad) + (y - cy) * Math.cos(rad)];
        const [lx, ly] = local([px, py]);
        const out = ([ax, ay]) => Math.hypot(ax / (from.rx * nw), ay / (from.ry * nw));
        if (kind === 'move') {
            r.x = from.x + mx;
            r.y = from.y + my;
        } else if ((kind === 'rx' || kind === 'ry') && e.shiftKey) {
            /* SHIFT KEEPS THE SHAPE (07.11.2026: „wenn ich die
               Größe ändere und dabei Shift halte, die Größe skalieren"): the
               square pulls both axes by the same factor. */
            const gk = radialGripK(from);
            const k = kind === 'rx' ? Math.abs(lx) / (from.rx * nw * gk) : Math.abs(ly) / (from.ry * nw * gk);
            r.rx = Math.min(5, Math.max(.005, from.rx * k));
            r.ry = Math.min(5, Math.max(.005, from.ry * k));
        } else if (kind === 'rx') {
            // The grip sits on the inner line: the pointer sets that, the outer follows at the same soft rim.
            r.rx = Math.min(5, Math.max(.005, Math.abs(lx) / radialGripK(from) / nw));
        } else if (kind === 'ry') {
            r.ry = Math.min(5, Math.max(.005, Math.abs(ly) / radialGripK(from) / nw));
        } else if (kind === 'outer') {
            /* THE OUTER LINE SETS THE SOFT RIM, THE INNER ONE STAYS
               (09.11.2026: „mit der äußeren Ellipse lege ich quasi
               den weichen Bereich, also den Verlauf fest. Die innere bleibt
               starr und die äußere legt die Verlaufweite fest"). Until then
               the outer line scaled the whole ellipse, the inner one with it.
               Now the outer radii grow by the pull, the inner ones are held —
               the feather is what lies between: 1 − inner ÷ outer. Never
               inside the inner line: there the rim is 0 and the two meet. */
            /* The pointer sets the DRAWN outer line (LINE_SHOWN): with the
               inner radius held at keep, the outer one is whatever puts
               the drawn line under the pointer. */
            const want = out([lx, ly]) / (out(local(start)) || 1) * radialOuterShown(from);
            const { k, feather } = radialFromShown(radialInnerShown(from), want);
            r.rx = Math.min(5, Math.max(.005, from.rx * k));
            r.ry = Math.min(5, Math.max(.005, from.ry * k));
            // To hundredths, as the server keeps it — whole numbers let the inner line creep.
            r.feather = feather;
        } else if (kind === 'inner') {
            // Where the full effect begins, as a share of the outer line: the rest is the soft passage.
            // The pointer sets the DRAWN inner line, the drawn outer one stays where it was.
            const d0 = radialOuterShown(from);
            const { k, feather } = radialFromShown(Math.max(0, Math.min(d0, out([lx, ly]))), d0);
            r.rx = Math.min(5, Math.max(.005, from.rx * k));
            r.ry = Math.min(5, Math.max(.005, from.ry * k));
            r.feather = feather;
        }
        developSoon();
        if (kind === 'inner' || kind === 'outer') developHandles();
    });
    const gripEnd = (e) => {
        if (!gripDrag || gripDrag.id !== e.pointerId) return;
        const wasFeather = gripDrag.kind === 'inner';
        gripDrag = null;
        swallowClick();
        developQueue();
        // The soft edge's slider follows the inner line.
        if (wasFeather) developPanelDraw();
        developHandles();
    };
    developSvg?.addEventListener('pointerup', gripEnd);
    developSvg?.addEventListener('pointercancel', gripEnd);

    /* „+ Ellipse" / „+ Verlauf": the next press on the picture lays one —
       an ellipse dragged from its centre out to the size, a gradient from
       where it acts in full to where it ends; a click alone lays one of the
       usual size. Outside the picture too, zoomed out: a large ellipse that
       only darkens the edges has its centre in the picture and its line far
       beyond. */
    /* A CLICK MAKES THE MASK AT ONCE (13.11.2026: „bei jedem
       Klick auf Ellipse, Verlauf oder Pinsel wird mir sofort eine Maske
       erstellt. Diese ist dann oben als neue Maske und halt am Anfang
       leer"), as „Pinsel" always did: an ellipse of the usual size in the
       middle of what the window shows, a gradient a quarter of the picture
       deep through that middle — both chosen, every slider at 0, their
       lines on the picture to be pulled where they belong. Laying one by
       a drag in the picture (maskPlacing) stays for whoever readies it. */
    /* … AND EMPTY UNTIL IT IS DRAWN (his next word: „ich zeichne aber keine
       Ellipse in die Fläche, sondern ich klicke weiter auf Verlauf, dann
       wird mir die leere Maske automatisch gelöscht. Weil ich brauche keine
       leere Maske, keinen Platzhalter"). An ellipse or gradient made by the
       click carries `empty`: it stands chosen at the top of the list, acts
       nowhere and draws no lines (masksOf, developHandles), and the next
       drag in the picture draws IT (the placement below takes the empty
       one instead of laying a second). A brush is empty while it has no
       stroke. Whatever empty mask is in hand goes the moment another is
       made or chosen (maskDropEmpty), and none is ever stored
       (develop.js, developClean). */
    const maskEmpty = (kind, m) => !!m && (kind === 'brush' ? !(m.strokes ?? []).length : m.empty === true);
    function maskDropEmpty() {
        if (!maskChosen) return false;
        const { kind, i } = maskChosen;
        if (!maskEmpty(kind, maskList(kind)[i])) return false;
        maskList(kind).splice(i, 1);
        maskChosen = null;
        if (maskPlacing) maskPlacing = null;
        return true;
    }
    function maskCreate(kind) {
        if (!developMay()) return;
        maskDropEmpty();
        if (masksCount() >= RADIALS_MAX || (kind === 'brush' && (develop.brushes?.length ?? 0) >= BRUSHES_MAX)) {
            developPanelDraw();
            developHandles();
            return;
        }
        const r = kind === 'radial' ? radialNew() : kind === 'linear' ? linearNew() : brushNew();
        if (kind !== 'brush') r.empty = true;
        maskList(kind).push(maskBornNow(r));
        maskChosen = { kind, i: maskList(kind).length - 1 };
        maskBare = false;
        // The next drag in the picture draws the empty ellipse or gradient (the placement below).
        maskPlacing = kind === 'brush' ? null : kind;
        maskLinesQuiet = false;
        developQueue();
        developPanelDraw();
        developHandles();
    }
    developAdd?.addEventListener('click', () => maskCreate('radial'));
    developAddLinear?.addEventListener('click', () => maskCreate('linear'));
    let placeDrag = null;
    let frameDown = null;   // where a press on the picture began — a click without a move lets the chosen mask go

    /* TURNING FROM ANYWHERE OUTSIDE (07.11.2026: „wenn ich
       außerhalb irgendeines Eckpunktes bin, dahinter überall das Drehen der
       Ellipse ermöglichen"): with an ellipse chosen, a drag that begins
       beyond its INNER line (since 09.11.2026, before: its outer) turns it
       about its centre — the angle follows
       the pointer round, as it does at the dark dot. The cursor says so
       there (the library's turning arrows, drawn light on dark). A click
       out there without a drag still lets the ellipse go. */
    const turnTarget = (e) => {
        if (!developMode || developTab !== 'masks' || maskPlacing || maskChosen?.kind !== 'radial' || !developMay()) return null;
        const r = maskOf(maskChosen);
        const nw = photoImageEl.naturalWidth;
        if (!r || !nw) return null;
        const [px, py] = picturePx(e);
        const cx = r.x * nw, cy = r.y * photoImageEl.naturalHeight;
        const rad = (r.angle ?? 0) * Math.PI / 180;
        const lx = (px - cx) * Math.cos(rad) + (py - cy) * Math.sin(rad);
        const ly = -(px - cx) * Math.sin(rad) + (py - cy) * Math.cos(rad);
        // Outside the inner line, the steering one (radialGripK) — between the two lines too (09.11.2026).
        return Math.hypot(lx / (r.rx * nw), ly / (r.ry * nw)) > radialGripK(r) ? { r, cx, cy, px, py } : null;
    };
    {
        const arrows = icon('refresh');
        const lines = [...arrows.children];
        arrows.textContent = '';
        for (const [stroke, width] of [['#000', 4.5], ['#fff', 2]]) {
            const g = document.createElementNS(SVGNS, 'g');
            g.setAttribute('stroke', stroke);
            g.setAttribute('stroke-width', String(width));
            for (const l of lines) g.append(l.cloneNode(true));
            arrows.append(g);
        }
        arrows.removeAttribute('class');
        arrows.setAttribute('width', '22');
        arrows.setAttribute('height', '22');
        const url = 'data:image/svg+xml,' + encodeURIComponent(new XMLSerializer().serializeToString(arrows));
        photoFrame.style.setProperty('--turn-cursor', `url("${url}") 11 11, alias`);
    }
    // The pointer over the picture brings the masks' lines back (maskLinesQuiet).
    photoFrame.addEventListener('pointermove', () => {
        clearTimeout(linesQuietTimer);
        if (!maskLinesQuiet) return;
        maskLinesQuiet = false;
        developHandles();
    });
    photoFrame.addEventListener('pointermove', (e) => {
        if (e.buttons) return;
        photoFrame.classList.toggle('is-turn-zone', turnZoneAt(e) !== null
            || (!e.target.closest?.('[data-grip], button') && turnTarget(e) !== null));
    });
    let turnDrag = null;
    photoFrame.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || e.target.closest?.('[data-grip], .photo-scalebar, button')) return;
        const at = turnTarget(e);
        if (!at) return;
        e.preventDefault();
        // Before the zoom's own listener on this same element: no pan under a turn.
        e.stopImmediatePropagation();
        turnDrag = { id: e.pointerId, ...at, x: e.clientX, y: e.clientY, from: at.r.angle ?? 0,
                     a0: Math.atan2(at.py - at.cy, at.px - at.cx), moved: false };
        photoFrame.setPointerCapture(e.pointerId);
    }, true);
    photoFrame.addEventListener('pointermove', (e) => {
        if (!turnDrag || turnDrag.id !== e.pointerId) return;
        e.stopImmediatePropagation();
        if (!turnDrag.moved && Math.hypot(e.clientX - turnDrag.x, e.clientY - turnDrag.y) < 4) return;
        turnDrag.moved = true;
        const [px, py] = picturePx(e);
        const deg = turnDrag.from + (Math.atan2(py - turnDrag.cy, px - turnDrag.cx) - turnDrag.a0) * 180 / Math.PI;
        turnDrag.r.angle = Math.round(((deg % 360) + 540) % 360 - 180);
        developSoon();
        developHandles();
    }, true);
    const turnEnd = (e) => {
        if (!turnDrag || turnDrag.id !== e.pointerId) return;
        e.stopImmediatePropagation();
        const moved = turnDrag.moved;
        turnDrag = null;
        if (moved) { developQueue(); swallowClick(); }
        else maskBareSet(true);   // a click out there: the view cleared of its lines, the ellipse still in hand (13.11.2026)
        photoFrame.classList.remove('is-turn-zone');
        developPanelDraw();
        developHandles();
    };
    photoFrame.addEventListener('pointerup', turnEnd, true);
    photoFrame.addEventListener('pointercancel', turnEnd, true);

    photoFrame.addEventListener('pointerdown', (e) => {
        if (!developMode || e.button !== 0) return;
        if (e.target.closest?.('[data-grip], .photo-scalebar, button')) return;
        // A press with a brush in hand paints (brushDown) — it never lets the brush go.
        if (!maskPlacing && maskChosen?.kind === 'brush') return;
        if (!maskPlacing) {
            frameDown = developTab === 'masks' ? { id: e.pointerId, x: e.clientX, y: e.clientY } : null;
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const nw = photoImageEl.naturalWidth, nh = photoImageEl.naturalHeight;
        const [px, py] = picturePx(e);
        const kind = maskPlacing;
        const fresh = kind === 'radial' ? radialNew(px / nw, py / nh, .005, .005) : linearNew(px / nw, py / nh, px / nw, py / nh);
        // The empty mask the button made is the one drawn now (maskCreate); else a new one is laid.
        const held = maskChosen?.kind === kind ? maskOf(maskChosen) : null;
        let r = fresh;
        if (held?.empty) {
            delete held.empty;
            for (const k of Object.keys(fresh)) if (k !== 'invert') held[k] = fresh[k];
            r = held;
        } else {
            maskList(kind).push(maskBornNow(r));
            maskChosen = { kind, i: maskList(kind).length - 1 };
        }
        maskPlacing = null;
        placeDrag = { id: e.pointerId, r, kind, start: [px, py] };
        photoFrame.setPointerCapture(e.pointerId);
        developPanelDraw();
    }, true);
    photoFrame.addEventListener('pointermove', (e) => {
        if (!placeDrag || placeDrag.id !== e.pointerId) return;
        e.stopPropagation();
        const nw = photoImageEl.naturalWidth, nh = photoImageEl.naturalHeight;
        const [px, py] = picturePx(e);
        // Laid by its DRAWN lines: the press is where the effect is 95 %, the pointer where it is 5 % (LINE_SHOWN).
        if (placeDrag.kind === 'linear') {
            if (Math.hypot(px - placeDrag.start[0], py - placeDrag.start[1]) > .5) Object.assign(placeDrag.r, linFrom(placeDrag.start, [px, py], nw, nh));
        } else {
            const k = radialOuterShown(placeDrag.r);
            placeDrag.r.rx = Math.max(.005, Math.abs(px - placeDrag.start[0]) / nw / k);
            placeDrag.r.ry = Math.max(.005, Math.abs(py - placeDrag.start[1]) / nw / k);
        }
        developSoon();
        developHandles();
    }, true);
    const placeEnd = (e) => {
        if (frameDown && frameDown.id === e.pointerId) {
            const still = Math.hypot(e.clientX - frameDown.x, e.clientY - frameDown.y) < 4;
            frameDown = null;
            // A click without a drag: the view cleared of the mask's lines, the mask still in hand (maskBareSet).
            if (still && maskChosen !== null && !e.target.closest?.('[data-grip]')) maskBareSet(true);
        }
        if (!placeDrag || placeDrag.id !== e.pointerId) return;
        e.stopPropagation();
        const { r, kind } = placeDrag;
        placeDrag = null;
        swallowClick();
        // A click without a drag: the usual size — a gradient a quarter of the picture deep, downwards.
        if (kind === 'radial' && r.rx < .02 && r.ry < .02) Object.assign(r, { rx: radialNew().rx, ry: radialNew().ry });
        if (kind === 'linear' && Math.hypot((r.x2 - r.x1) * photoImageEl.naturalWidth, (r.y2 - r.y1) * photoImageEl.naturalHeight) < 10) {
            // The drawn full line at the click, the drawn empty one a quarter of the picture below (LINE_SHOWN).
            const nw = photoImageEl.naturalWidth, nh = photoImageEl.naturalHeight;
            const d1 = [r.x1 * nw, r.y1 * nh];
            Object.assign(r, linFrom(d1, [d1[0], d1[1] + .25 * nh], nw, nh));
        }
        developQueue();
        developPanelDraw();
        developRedraw();
        developHandles();
    };
    photoFrame.addEventListener('pointerup', placeEnd, true);
    photoFrame.addEventListener('pointercancel', placeEnd, true);

    /* --- Drawing into the measuring plane ---------------------------------- */

    /* THE DRAWING ITSELF LIVES IN measuredraw.js since 26.09.2026 — the live
       preview in Einstellungen → Fotos → Messen draws with it too, and a
       second copy would drift. These wrappers hand in the account's style
       and the current hairline, so the calls below stay as they were.

       overlayHair is the reciprocal of the current on-screen scale,
       refreshed at the top of every drawOverlay (measuredraw.js says why the
       width is reckoned against the zoom). */
    let overlayHair = 1;
    /* The CHOSEN measure is drawn twice as strong (drawOverlay) — its
       lines, its end dots, its rectangle; the labels keep their size. */
    let lineWeight = 1;
    function svgSeamed(shape, attrs) {
        return measureDraw.seamed(shape, attrs, measureStyle, overlayHair * lineWeight);
    }
    /** User words into SVG markup — the labels are numbers, THIS is not. */
    function escapeXml(x) {
        return String(x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    /** The rectangle being set, as far as it goes: the axis (or the line to
     *  the pointer before it), the side through the pointer, then the whole
     *  frame from the third tap's side to the pointer's (measureHover). */
    function fourClickGuide(points, hover) {
        const line = ([x1, y1], [x2, y2]) => svgSeamed('line', `x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"`);
        if (points.length === 1) return hover ? line(points[0], hover) : '';
        const [a, b] = points;
        let out = line(a, b);
        const len = Math.hypot(b[0] - a[0], b[1] - a[1]);
        if (!len) return out;
        const nx = -(b[1] - a[1]) / len, ny = (b[0] - a[0]) / len;
        const offset = (p) => (p[0] - a[0]) * nx + (p[1] - a[1]) * ny;
        const at = (p, o) => [p[0] + nx * o, p[1] + ny * o];
        if (points.length === 2) {
            if (hover) out += line(at(a, offset(hover)), at(b, offset(hover)));
            return out;
        }
        const o1 = offset(points[2]);
        out += line(at(a, o1), at(b, o1));
        if (hover) {
            const o2 = offset(hover);
            out += line(at(b, o1), at(b, o2)) + line(at(b, o2), at(a, o2)) + line(at(a, o2), at(a, o1));
        }
        return out;
    }
    /* ONLY THE GUIDE IS REDRAWN WHEN THE POINTER MOVES, NEVER THE OVERLAY
       (17.11.2026 — the day after the guide went live a friend of
       the maintainer's reported: „er kann nicht mehr messen", rectangles on the
       layer, no statistics). The first build called drawOverlay() at every
       move, which writes the whole overlay anew — and with it the element
       lying under the pointer. A hand moves a little between pressing and
       letting go; the element the press landed on was gone by the release,
       and a browser that needs both on one element makes no click of it:
       the taps of the rectangle went nowhere, no measure was finished, and
       so the statistics never opened. Now the guide lives in a group of its
       own that takes no pointer (photo-four-guide, drawOverlay), and a move
       rewrites that group alone.

       A FINGER HAS NO HOVER: on a touch screen nothing rides along — the
       finger is off the glass between the taps, and its tremble while it
       taps would be the only „movement". The taps and the axis stand as
       they always did. */
    function guideDraw() {
        const layer = photoOverlay.querySelector('.photo-four-guide');
        // In the layer's colour, as the overlay around it (inLayerColour).
        if (layer) layer.innerHTML = fourClicking() ? inLayerColour(() => fourClickGuide(measurePoints, measureHover)) : '';
    }
    let hoverQueued = false;
    photoFrame.addEventListener('pointermove', (e) => {
        if (e.pointerType === 'touch' || !fourClicking()) {
            measureHover = null;
            return;
        }
        const at = clientNatural(e.clientX, e.clientY);
        if (!at) return;
        measureHover = at.p;
        if (hoverQueued) return;
        hoverQueued = true;
        requestAnimationFrame(() => { hoverQueued = false; guideDraw(); });
    });
    photoFrame.addEventListener('pointerleave', () => {
        if (measureHover === null) return;
        measureHover = null;
        guideDraw();
    });

    function svgMark(x, y) {
        return measureDraw.mark(x, y, measureStyle, overlayHair * lineWeight);
    }
    function svgText(x, y, text, fontSize) {
        return measureDraw.label(x, y, text, fontSize, measureStyle);
    }
    /* WHILE drawOverlay LAYS OUT THE MEASURES, their labels are only
       collected (labelQueue) and placed together at the end, so no two
       numbers lie on one another (measureDraw.placedEdgeLabels says how).
       labelOpacity carries a pale measure's .35 over to its numbers,
       which no longer stand inside its group. */
    let labelQueue = null;
    let labelOpacity = 1;
    function svgEdgeLabel(x1, y1, x2, y2, text, fontSize, awayX = null, awayY = null) {
        if (labelQueue !== null) {
            labelQueue.push({ x1, y1, x2, y2, text, fontSize, awayX, awayY, opacity: labelOpacity });
            return '';
        }
        return measureDraw.edgeLabel(x1, y1, x2, y2, text, fontSize, measureStyle, awayX, awayY);
    }

    /** µm for people: „84,2 µm", „1,3 mm", „2 cm" — German comma included. */
    function formatUm(um) {
        const say = (v, unit, digits) => v.toLocaleString('de-DE', {
            maximumFractionDigits: digits,
        }) + ' ' + unit;
        if (um < 1000)   return say(um, 'µm', um < 100 ? 1 : 0);
        if (um < 10000)  return say(um / 1000, 'mm', 2);
        if (um < 1000000) return say(um / 10000, 'cm', 1);
        return say(um / 1000000, 'm', 2);
    }

    /** µm per DISPLAYED pixel of the open picture, or null without a scale.
     *
     *  THE REFERENCE WIDTH DOES THE CONVERTING (migration 0090): the line was
     *  drawn on an image scale.width pixels wide; what is displayed may be
     *  the 2048-px preview of a 6000-px original or the other way round.
     *  Widths relate linearly (the aspect survives every scaling), so one
     *  factor carries the whole truth. Absent width — the first day's
     *  calibrations — means: drawn on what is displayed, factor one. */
    function photoUmPerPx() {
        const scale = measureSubject()?.scale;
        if (!scale || !photoImageEl.naturalWidth) return null;
        const [a, b] = scale.line;
        const lineShown = Math.hypot(b[0] - a[0], b[1] - a[1])
            * (photoImageEl.naturalWidth / (scale.width ?? photoImageEl.naturalWidth));
        return scale.um / lineShown;
    }

    /* --- Calibrating and measuring: the shared tap machinery ---------------- */

    let calibrating   = null;   // null = off, else the points tapped so far
    let measureMode   = null;   // null | 'line' | 'rect' | 'path' | 'text' | 'arrow' | 'box' | 'cutout' | 'cystid'
    /* The cutout being measured into — {m, free, armed}, or null. `armed`
       says HOW it came about: true out of the running cutout tool (finishing
       returns to the next frame), false off a SELECTED cutout that still
       lacks its breadth (finishing returns to the selection). Up here and
       not with the cutout's code, because toolChoiceSync() reads it and
       runs before that code is reached (frameOffer says the rest). */
    let frameRect     = null;
    /* The cutout just completed out of the running tool, {m, free} — so
       Escape can still take back its LAST inner measure while no new frame
       has begun (escapeStep). The first tap of the next frame forgets it. */
    let frameJustDone = null;
    /* THE TEXT BEING WRITTEN, straight into the picture
       (05.10.2026: „können wir den Text direkt ins Bild schreiben"):
       {m, free, isNew, before} — the record the keys type into, whether it
       is new (and not yet in any list), and the words it had before, for
       Escape. Up here for the same reason as frameRect: drawOverlay() reads
       it, and that runs long before the writing's own code is reached. */
    let textEdit      = null;
    // When a tap beside the text ended the writing — its click is spent.
    let textCommitAt  = 0;
    // The selected text's handles, natural pixels: {size, rotate, corners}.
    let textHandles   = null;
    // Did the selected text put its hint over the picture? Then it clears it.
    let textHintShown = false;
    /* The arrow just set while the tool stays armed — its words can be
       written right away (arrowHintSync); the next tap, another tool or
       none ends the offer. */
    let arrowJustSet = null;
    let arrowPanelFor = null;
    // What the layer quick switches last showed (layerChipsSync), and the
    // layer the picture was arrived on (layerAutoPick) — up here, because
    // drawOverlay() asks them, and that runs long before their code.
    let layerChipsKey = '';
    let layerArrived  = '';
    /* THE TEXT SIZES COME IN STEPS — TEXT_SIZES and textStep live in
       measuredraw.js since the plate sizes an arrow's words too. At
       „Einpassen" on a common screen a step comes out close to the screen
       pixels one would guess. */
    // THE ARROW'S STRENGTH STEPS live in measuredraw.js (the plates draw the same arrow).
    // The chosen arrow's strength handle, natural pixels: {pos, normal}.
    let arrowHandle   = null;
    // …and the size handle of its words, natural pixels: {pos, tail}.
    let arrowSizeHandle = null;
    /* THE BOX OF A FIXED SIZE (06.10.2026: „ein Rechteck, das
       man zeichnen kann und wo man die Maße angeben kann, wie groß es sein
       soll" — pores per square millimetre, the lamellae along a centimetre
       of a Gloeophyllum's cap margin). Its size is TYPED, in µm or mm, and
       stands true to scale; the hand only moves and turns it. The chosen
       box's handles, natural pixels: {rotate, corners}. */
    let boxHandles    = null;
    /* What the size fields over the picture stand for: 'armed' (the next
       box), the chosen box itself, or null — so they are built once per
       target and not under the typing finger at every redraw. */
    let sectorAim     = null;   // a slice being set on the chosen sector: {m, free, stage: 'from'|'to', t, sweep} (sectorClick)
    let sectorHover   = null;   // the pointer over the picture while a slice is set, natural pixels
    let boxDraw       = null;   // a frame being pulled open: {id, start, now, moved} in natural pixels (boxDrawStart)
    let boxDrawnAt    = 0;      // when the last one was let go — its trailing click is no click
    // A metre is no box on a photograph — the same cap the server sets.
    const BOX_MAX_UM  = 1000000;
    /* THE PHOTOGRAPHER'S PRESETS, per photographer, for his pictures one
       may only look at (06.10.2026, api/v1/scales.php): a list once it
       came, 'loading' while it comes. */
    const foreignScales = new Map();
    // Measures a text's width the way the overlay will draw it.
    const textMeasure = document.createElement('canvas').getContext('2d');
    let overlayFont   = null;
    let measurePoints = [];     // the taps of the measure in progress
    /* THE POINTER WHILE A RECTANGLE IS SET (16.11.2026, after
       the microscope's quick measuring: „Was mir gefällt, ist, wie du das
       Rechteck machst. Mit der Linie, die dann erstmal mitfährt beim
       dritten Klick … bei allen Werkzeugen, bei denen man mit 4 Klicks ein
       Rechteck aufzieht, ob es jetzt zum Messen oder für einen
       Bildausschnitt ist"). Before, only the taps stood there, and after
       the second the axis; where the sides would come lay in one's head.
       Now the line runs from the first tap to the pointer, after the axis
       a line parallel to it rides with the pointer — the first side —, and
       after the third tap the whole rectangle follows it to the second
       side (fourClickGuide). In original pixels, like the taps. */
    let measureHover = null;
    const FOUR_CLICKS = ['rect', 'cutout', 'cystid', 'crop'];
    const fourClicking = () => FOUR_CLICKS.includes(measureMode) && frameRect === null
        && measurePoints.length > 0 && measurePoints.length < 4;
    let measures      = [];     // finished measures on THIS photo (aliases the
                                // subject's own persisted list)
    /* The FREE measures (28.08.2026: „Frei wäre dann quasi
       ‚speichern wir nicht' … um mal ein Maß zu erfahren"): drawn like the
       others, saved never, counted nowhere — they die with the photo
       switch, and that is their whole point. */
    let freeMeasures  = [];

    /** What the CURRENT layer holds — the Messung chooser decides: „Frei"
     *  shows the free list, every label its own slice of the persisted
     *  ones. Drawing, hit test, the clear button and its clearing all go
     *  through here, so no two of them can disagree about a layer. */
    function layerMeasures() {
        return statsLabel === MEASURE_FREE
            ? freeMeasures
            : measures.filter(m => measureLabel(m) === statsLabel);
    }

    /** What the picture SHOWS of the layer — the statistics' tab decides
     *  (07.10.2026): „Alles" draws the structure and every
     *  label on it but no part line, a part's tab only that part's lines.
     *  Drawing and hit test go through here; the clear button keeps the
     *  whole layer (layerMeasures), and says so by its name. */
    function shownMeasures() {
        const all = layerMeasures();
        if (statsLabel === MEASURE_FREE) return all;
        const tab = statsTabs.get(statsLabel) ?? null;
        // A cutout name's tab shows its cutouts alone; „Alles" and a part's
        // tab show none — each name has its own tab (statsTabsSync).
        const crop = cropOfTab(tab);
        if (crop !== null) return all.filter(m => measureCrop(m) === crop);
        return all.filter(m => measureCrop(m) === null && measurePart(m) === (tab ?? ''));
    }

    /* FLOATING OVER THE IMAGE, never in the footer — measured 28.08.2026: a
       hint down there rewraps the footer, the image area changes height and
       the picture JUMPS between the first and the second tap. The very first
       calibrations measured 36 % short because of it, consistently and
       silently. An absolutely positioned line moves no layout. */
    function calibrationHint(text) {
        const hint = document.getElementById('photo-frame-hint');
        hint.textContent = text;
        hint.hidden = text === '';
    }

    /* FREE MEASURING WEARS A COLOUR OF ITS OWN (17.11.2026: „was
       bei meinem Freund passiert ist und was mir auch ständig passiert, ist,
       dass ich die Messebene vergesse zu wählen und im Freimessmodus
       anfange. Wir sollten den Freimessmodus deshalb … in einem auffälligen
       Rot machen. Oder blau oder grün, egal, auf jeden Fall andersfarbig und
       abgehoben von dem, wie sie normal aussehen"). On „Frei" nothing is
       kept and nothing counted — and it looked exactly like a layer that
       does both, until the statistics stayed away. Now every line, tap and
       number drawn on „Frei" is red; whoever's own line colour is a red
       already gets blue, so it stands apart for him too. The seam stays the
       account's: it is what makes any line read on any slide.

       THE ACCOUNT'S COLOUR IS SWAPPED FOR THE LENGTH OF ONE DRAWING and put
       back — the overlay's helpers all read measureStyle.line_color, and a
       drawing runs in one go, so nothing else ever sees the other colour.
       „Frei" shows only free measures (the layer switch), so the whole
       overlay is theirs. */
    function freeColour() {
        const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(String(measureStyle.line_color ?? ''));
        const [r, g, b] = m ? m.slice(1).map((h) => parseInt(h, 16)) : [0, 0, 0];
        return r > 150 && g < 110 && b < 110 ? '#1f6fff' : '#ff1f1f';
    }
    function inLayerColour(draw) {
        if (statsLabel !== MEASURE_FREE) return draw();
        const own = measureStyle.line_color;
        measureStyle.line_color = freeColour();
        try {
            return draw();
        } finally {
            measureStyle.line_color = own;
        }
    }

    /** The whole overlay, from state — called after every change AND on every
     *  zoom step, because the label size leans against the magnification. */
    function drawOverlay() {
        inLayerColour(drawOverlayNow);
    }
    function drawOverlayNow() {
        // One shrink factor for label and line alike — screen pixels stated
        // in viewBox units at the current magnification.
        overlayHair = 1 / (viewScale() * photoZoom.stufe());
        // Its labels stand upright on the screen, however the view is turned —
        // against a mirrored view the turn counts backwards (viewUnmirror).
        measureDraw.setViewTurn((pictureView?.R ?? 0) * viewMirrorSign());
        const fontSize = measureStyle.font_px * overlayHair;
        let inner = '';

        const umPerPx = photoUmPerPx();
        /* ONLY THE CURRENT LAYER (28.08.2026: „sollen nur die
           Maße angezeigt werden, die sich auch auf dieser Ebene befinden")
           — the Messung chooser is a layer switch: Sporen shows spores,
           Frei shows the free ones, nothing bleeds through. */
        const drawn = textEdit?.isNew ? [...shownMeasures(), textEdit.m] : shownMeasures();
        /* THE CHOSEN MEASURE STANDS OUT BY STRENGTH, NOT BY COLOUR
           (07.10.2026: „die Akzentfarbe Grün im Bild ist oftmals
           nicht erkennbar, gerade dieses Limettgrün … im Bild brauche ich
           eine andere Lösung"; of four drafts on bright field, Melzer,
           Congo red and a dark ground he chose this one). Since 04.10.2026
           the chosen one — focused from a tile, selected in the picture,
           being written — had worn the accent; on a pale bright field a
           lime line was all but gone. Now it keeps the user's own line
           colours, which read on any ground, at twice the strength, and
           every other measure steps back to a third. The statistics and
           the tiles keep the accent: on the theme's own ground it works. */
        const isChosen = m => m === measureFocus || m === measureSelected?.m || m === textEdit?.m;
        const someChosen = drawn.some(isChosen);
        // The chosen one's numbers are placed first: they keep their spot.
        const chosenLabels = [], otherLabels = [];
        try {
            for (const m of drawn) {
                if (isChosen(m)) {
                    labelQueue = chosenLabels;
                    lineWeight = 2;
                    inner += drawMeasure(m, umPerPx, fontSize);
                    lineWeight = 1;
                } else {
                    labelQueue = otherLabels;
                    labelOpacity = someChosen ? .35 : 1;
                    const drawing = drawMeasure(m, umPerPx, fontSize);
                    inner += someChosen ? `<g opacity=".35">${drawing}</g>` : drawing;
                    labelOpacity = 1;
                }
            }
        } finally {
            labelQueue = null;
            lineWeight = 1;
            labelOpacity = 1;
        }
        overlayFont ??= getComputedStyle(photoOverlay).fontFamily || 'sans-serif';
        inner += measureDraw.placedEdgeLabels([...chosenLabels, ...otherLabels], measureStyle, overlayHair, overlayFont);
        /* THE MEASURE BEING SET, in a group of its own: it stays in sight
           while M hides the drawings (17.11.2026: „bei mehreren
           Maßen wird das irgendwann unübersichtlich. Man sieht nicht mehr
           genau die Konturen auf dem Bild, was man überhaupt messen will.
           Ich bräuchte sowas wie alle Maße ausblenden"). M did hide them
           already — and with them the taps one was just setting, so the next
           spore would have been measured blind. Now the finished measures
           step aside and the one in hand is seen (photowindow.css,
           .photo-measure-setting). */
        let setting = '';
        for (const p of measurePoints) setting += svgMark(...p);
        /* THE GUIDE IN A LAYER OF ITS OWN that takes no pointer — the
           pointer's moves rewrite only this group (guideDraw), never the
           overlay around it. */
        setting += `<g class="photo-four-guide" pointer-events="none">${fourClicking() ? fourClickGuide(measurePoints, measureHover) : ''}</g>`;
        if (measureMode === 'path' && measurePoints.length >= 2) {
            setting += svgSeamed('polyline',
                `points="${measurePoints.map(p => p[0] + ',' + p[1]).join(' ')}"`);
        }
        setting += boxDrawSvg(fontSize);
        setting += sectorSettingSvg();
        inner += `<g class="photo-measure-setting">${setting}</g>`;

        if (calibrating !== null) {
            if (calibrating.length >= 1) inner += svgMark(...calibrating[0]);
            if (calibrating.length >= 2) {
                const [[x1, y1], [x2, y2]] = calibrating;
                inner = svgSeamed('line', `x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"`)
                      + inner + svgMark(...calibrating[1]);
            }
        }
        // The selection's handles and × on top of everything.
        inner += drawSelection();
        photoOverlay.innerHTML = viewWrap(inner);
        viewUnmirror(photoOverlay);
        textCaretPlace();
        textHintSync();
        arrowHintSync();
        boxHintSync();
        sectorHintSync();
        markHintSync();
        /* THE STRIP IS PART OF THE LAYER VIEW, so it is redrawn here and
           nowhere else: every path that adds, drags, deletes or clears a
           measure — and the layer switch too — ends in drawOverlay(). A
           separate call at each of those sites would be forgotten by the
           next tool added (the polyline and the text tool already skip
           statsRefresh); here nothing can. */
        photoStripSync();
        // And the quick switches — a measure added or deleted may open or
        // close a layer of this picture.
        layerChipsSync();
        // The same reason for the tiles' mark: a selection or a focus
        // changed — the tile of the measure says so (thumbsFocusSync).
        thumbsFocusSync();
    }

    /**
     * The rectangle out of the four taps (28.08.2026): taps 1+2
     * are the LONG AXIS, taps 3+4 the width — and the width is NEVER their
     * diagonal but their distance ACROSS the axis: both points are projected
     * onto the axis normal, „wie wenn ich ein Rechteck um die Spore ziehe".
     * The two may sit at different heights and on opposite flanks, so the
     * axis may run off-centre; the rectangle spans from the one flank to the
     * other regardless. Shared by the drawing and the click-to-delete hit
     * test, so the two can never disagree about where an edge lies.
     */
    function rectGeometry(points) {
        const [a, b, c, d] = points;
        const ax = b[0] - a[0], ay = b[1] - a[1];
        const len = Math.hypot(ax, ay);
        const nx = -ay / len, ny = ax / len;           // unit normal
        const o1 = (c[0] - a[0]) * nx + (c[1] - a[1]) * ny;
        const o2 = (d[0] - a[0]) * nx + (d[1] - a[1]) * ny;
        return {
            len,
            breadth: Math.abs(o2 - o1),
            corners: [
                [a[0] + nx * o1, a[1] + ny * o1],
                [b[0] + nx * o1, b[1] + ny * o1],
                [b[0] + nx * o2, b[1] + ny * o2],
                [a[0] + nx * o2, a[1] + ny * o2],
            ],
        };
    }

    /** Distance from a point to a segment — the hit test's yardstick. */
    function segmentDistance(p, a, b) {
        const dx = b[0] - a[0], dy = b[1] - a[1];
        const lenSq = dx * dx + dy * dy;
        const t = lenSq === 0 ? 0 : Math.max(0, Math.min(1,
            ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / lenSq));
        return Math.hypot(p[0] - (a[0] + t * dx), p[1] - (a[1] + t * dy));
    }

    /** Whether a point lies inside a convex polygon (a turned box). */
    function insidePolygon(p, corners) {
        let sign = 0;
        for (let i = 0; i < corners.length; i++) {
            const a = corners[i], b = corners[(i + 1) % corners.length];
            const cross = (b[0] - a[0]) * (p[1] - a[1]) - (b[1] - a[1]) * (p[0] - a[0]);
            if (cross === 0) continue;
            if (sign === 0) sign = Math.sign(cross);
            else if (Math.sign(cross) !== sign) return false;
        }
        return true;
    }

    /**
     * A text's letter height in natural pixels of the image now shown.
     *
     * THE SIZE BELONGS TO THE PICTURE since the text can be sized by its
     * box (05.10.2026: „mit einer Box versehen, um die
     * Schriftgröße zu ziehen") — stored beside the points in the measure's
     * own space, so it grows under the zoom like everything written on a
     * photograph. A text from before carries none and keeps the size it
     * had at „Einpassen": the account's font size, restated in the
     * picture's pixels at the fitted view. Touched once, it gets its own.
     */
    function textSizeOf(m) {
        if (m.size != null) return m.size * measureAcross(m);
        return measureStyle.font_px / viewScale();
    }

    /**
     * The box around a text and the places of its handles, natural pixels.
     *
     * ONE ANSWER FOR THE DRAWING, THE HIT TEST, THE HANDLES AND THE CURSOR,
     * as rectGeometry is for the rectangle. The anchor is where the line of
     * writing begins (its baseline), the text turns about it by `angle`; the
     * width is measured with the very font the overlay draws in. The size
     * handle sits at the box's lower right corner, the turning handle on a
     * short stem above its middle — where every drawing program has them.
     */
    function textBox(m) {
        const across = measureAcross(m);
        const ax = m.points[0][0] * across, ay = m.points[0][1] * across;
        const fs = textSizeOf(m);
        overlayFont ??= getComputedStyle(photoOverlay).fontFamily || 'sans-serif';
        textMeasure.font = `600 ${fs}px ${overlayFont}`;
        // SEVERAL LINES since 07.10.2026: the widest line is the box's width,
        // every further line adds one line height below the first.
        const lines = textLines(m);
        const w = Math.max(...lines.map(l => textMeasure.measureText(l).width));
        const pad = fs * 0.15 + screenUnits(3);
        const x0 = -pad, x1 = Math.max(w, fs * 0.4) + pad;
        const y0 = -fs * 0.8 - pad, y1 = (lines.length - 1) * fs * TEXT_LEADING + fs * 0.25 + pad;
        const angle = m.angle ?? 0;
        const cos = Math.cos(angle * Math.PI / 180), sin = Math.sin(angle * Math.PI / 180);
        /* On a mirrored view the words are mirrored back about their anchor
           (viewUnmirror, writtenSvg) — the box and its handles with them, so
           the size handle stays lower right and the stem on top as one sees it. */
        const [mx, my] = pictureView ? [pictureView.fx, pictureView.fy] : [1, 1];
        const at = (lx, ly) => [ax + lx * mx * cos - ly * my * sin, ay + lx * mx * sin + ly * my * cos];
        return {
            anchor: [ax, ay], fs, angle,
            corners: [at(x0, y0), at(x1, y0), at(x1, y1), at(x0, y1)],
            centre: at((x0 + x1) / 2, (y0 + y1) / 2),
            sizeHandle: at(x1, y1),
            stemFoot: at((x0 + x1) / 2, y0),
            rotateHandle: at((x0 + x1) / 2, y0 - screenUnits(24)),
        };
    }

    /* A TEXT'S LINES (07.10.2026: „weil man mehrzeilig schreiben
       soll. Also Enter wäre dann der Zeilenumbruch"). Stored as one string
       with line breaks; drawn a line under the other at 1.2 letter heights,
       the leading every drawing and every box here reckons with. */
    function textLines(m) {
        return String(m.text ?? '').split('\n');
    }

    /**
     * An arrow's outline in natural pixels — shaft and head as ONE shape,
     * so a strong arrow is a solid arrow and not a thick line with a
     * triangle on it. Shared by the drawing, the hit test and the strength
     * handle. The head never outgrows the arrow: on a short one it takes
     * at most six tenths of the length.
     */
    function arrowGeometry(m) {
        const across = measureAcross(m);
        const [tail, tip] = m.points.map(([x, y]) => [x * across, y * across]);
        const per = (photoImageEl.naturalWidth || 1) / 1000;
        const g = measureDraw.arrowOutline(tail, tip, arrowWeightOf(m), per);
        return {
            tail, tip, n: g.n, half: g.half, outline: g.outline,
            handle: g.off(g.middle, g.half + screenUnits(16)),
        };
    }

    /** Which part of the selected text a point lands on: 'rotate', 'size',
     *  'move' (anywhere inside the box), or null. */
    function textGrabAt(p) {
        if (textHandles === null) return null;
        const near = (q) => Math.hypot(p[0] - q[0], p[1] - q[1]) <= screenUnits(12);
        if (near(textHandles.rotate)) return 'rotate';
        if (near(textHandles.size)) return 'size';
        return insidePolygon(p, textHandles.corners) ? 'move' : null;
    }

    /**
     * A box's corners and its turning handle, natural pixels — or null
     * without a scale.
     *
     * THE SIZE COMES FROM THE SCALE AT EVERY DRAWING, not from pixels
     * stored at the tap: the box IS its micrometres, and a picture
     * calibrated anew carries it at the new truth. The other side of that
     * coin: a picture whose scale was taken away has no size for it, and
     * the box waits unseen until a scale comes back — drawing it at some
     * remembered pixel size would be a measure that lies. Only the centre
     * is stored in pixels (the measure's own space, like every point).
     * Turned about that centre by `angle`, in degrees; the turning handle
     * sits on a stem above the top edge, as on a text — the label goes
     * under the bottom edge, where the stem does not cross it.
     */
    function boxGeometry(m) {
        const umPerPx = photoUmPerPx();
        if (!umPerPx || !Array.isArray(m.um)) return null;
        const across = measureAcross(m);
        const cx = m.points[0][0] * across, cy = m.points[0][1] * across;
        const hw = m.um[0] / umPerPx / 2, hh = m.um[1] / umPerPx / 2;
        const angle = m.angle ?? 0;
        const cos = Math.cos(angle * Math.PI / 180), sin = Math.sin(angle * Math.PI / 180);
        // The stem stays above the box on a mirrored view too (textBox).
        const [mx, my] = pictureView ? [pictureView.fx, pictureView.fy] : [1, 1];
        const at = (lx, ly) => [cx + lx * mx * cos - ly * my * sin, cy + lx * mx * sin + ly * my * cos];
        return {
            centre: [cx, cy], angle, half: [hw, hh],
            corners: [at(-hw, -hh), at(hw, -hh), at(hw, hh), at(-hw, hh)],
            stemFoot: at(0, -hh),
            rotateHandle: at(0, -hh - screenUnits(24)),
            /* THE FRAME'S OWN COORDINATES, in µm from its middle along its
               sides — where its counted points are kept (boxCount): they
               go along when the frame is moved or turned, and need no
               picture to be drawn small in the statistics. */
            toLocal: ([px, py]) => {
                const dx = px - cx, dy = py - cy;
                return [(dx * cos + dy * sin) * mx * umPerPx, (-dx * sin + dy * cos) * my * umPerPx];
            },
            fromLocal: ([u, v]) => at(u / umPerPx, v / umPerPx),
        };
    }

    /** Which part of the chosen box a point lands on: 'rotate' at the
     *  circle, 'move' inside or on the rim, or null. */
    function boxGrabAt(p) {
        if (boxHandles === null) return null;
        const [rx, ry] = boxHandles.rotate;
        if (Math.hypot(p[0] - rx, p[1] - ry) <= screenUnits(12)) return 'rotate';
        const c = boxHandles.corners;
        if (insidePolygon(p, c)) return 'move';
        for (let i = 0; i < 4; i++) {
            if (segmentDistance(p, c[i], c[(i + 1) % 4]) <= screenUnits(8)) return 'move';
        }
        return null;
    }

    /** A turn in degrees brought into (-180, 180] — and within four
     *  degrees of upright, of lying on its side or of upside down settled
     *  there: „straight" should not need a steady hand. Text and box. */
    function angleSnapped(raw) {
        let angle = ((raw % 360) + 540) % 360 - 180;
        for (const right of [-180, -90, 0, 90, 180]) {
            if (Math.abs(angle - right) < 4) angle = right === -180 ? 180 : right;
        }
        return angle;
    }

    /**
     * Which measure a tap landed on (28.08.2026: „jede einzelne
     * Maß im Bild anklicken … um es einzeln zu löschen") — the NEAREST one
     * whose stroke lies within reach, or null. Deliberately only the
     * STROKES, not the rectangle's inside: the inside is the spore, and
     * whoever double-taps it wants the zoom, not a dialog.
     */
    function measureAt(p, reach) {
        let best = null;
        const consider = (m, free, index) => {
            const across = m.width && photoImageEl.naturalWidth
                ? photoImageEl.naturalWidth / m.width : 1;
            const pts = m.points.map(([x, y]) => [x * across, y * across]);
            /* A TEXT IS HIT ANYWHERE IN ITS BOX, not only on its rim — the
               box is what one sees and grabs (textBox), and the words are
               small. */
            if (m.type === 'text') {
                const c = textBox(m).corners;
                if (insidePolygon(p, c) && (best === null || best.d > 0)) {
                    best = { m, free, index, d: 0 };
                    return;
                }
            }
            // An arrow's own words select the arrow, anywhere in their box — they are one.
            const words = arrowWords(m);
            if (words && insidePolygon(p, words.corners) && (best === null || best.d > 0)) {
                best = { m, free, index, d: 0, onWords: true };
                return;
            }
            /* A SECTOR IS HIT AT ITS SLICE, and only there
               (18.11.2026: „so einen Sektor soll man schon aktivieren können,
               wenn man auf das Kuchenstück klickt … beziehungsweise nur") —
               the slice is the thing one looks at; the thin ellipse is a
               line one has to aim for. Without a slice yet there is nothing
               else to take, so then the ellipse. Once chosen, anywhere
               inside, so a tap in it keeps it chosen (sectorClick). */
            if (m.type === 'sector') {
                const g = sectorGeometry(m);
                if (g === null) return;
                const { t: at, r } = g.at(p);
                if (m === measureSelected?.m && r <= 1.02) {
                    if (best === null || best.d > 0) best = { m, free, index, d: 0 };
                    return;
                }
                const arc = sectorArc(m);
                if (arc !== null) {
                    if (r <= 1.02 && sectorInside(at, arc[0], arc[1]) && (best === null || best.d > 0)) {
                        best = { m, free, index, d: 0 };
                    }
                    return;
                }
                const d = Math.abs(r - 1) * Math.min(g.a, g.b);
                if (d <= reach && (best === null || d < best.d)) best = { m, free, index, d };
                return;
            }
            /* A BOX IS HIT AT ITS RIM, like a rectangle — its inside is what
               one counts, and a double tap there is the zoom's. Only the
               CHOSEN box is hit anywhere inside, so a tap in it keeps it
               chosen instead of letting it go. Without a scale it is not
               drawn, and what is not drawn is not hit. */
            if (m.type === 'box') {
                const g = boxGeometry(m);
                if (g === null) return;
                if (m === measureSelected?.m && insidePolygon(p, g.corners)) {
                    if (best === null || best.d > 0) best = { m, free, index, d: 0 };
                    return;
                }
                for (let i = 0; i < 4; i++) {
                    const d = segmentDistance(p, g.corners[i], g.corners[(i + 1) % 4]);
                    if (d <= reach && (best === null || d < best.d)) best = { m, free, index, d };
                }
                return;
            }
            const segments = m.type === 'line' || m.type === 'arrow'
                ? [[pts[0], pts[1]]]
                : m.type === 'path'
                    ? pts.slice(1).map((p, i) => [pts[i], p])
                    : m.type === 'text'
                        ? (() => { const c = textBox(m).corners;
                            return [0, 1, 2, 3].map(i => [c[i], c[(i + 1) % 4]]); })()
                        : (() => { const c = rectGeometry(pts).corners;
                            const segs = [0, 1, 2, 3].map(i => [c[i], c[(i + 1) % 4]]);
                            // A cutout's inner measures select the UNIT —
                            // tapping the paraphysis grabs its rectangle.
                            for (const mi of (m.inner ?? [])) {
                                const ip = mi.points.map(([x, y]) => [x * across, y * across]);
                                for (let i = 1; i < ip.length; i++) segs.push([ip[i - 1], ip[i]]);
                            }
                            return segs; })();
            // A strong arrow is hit on its body, not only on its middle line.
            const body = m.type === 'arrow' ? arrowGeometry(m).half : 0;
            for (const [a, b] of segments) {
                const d = Math.max(0, segmentDistance(p, a, b) - body);
                if (d <= reach && (best === null || d < best.d)) {
                    best = { m, free, index, d };
                }
            }
        };
        // Only what is DRAWN can be hit — the other layers' measures are
        // not on screen, and an invisible thing must not swallow a tap.
        const free = statsLabel === MEASURE_FREE;
        shownMeasures().forEach((m, i) => consider(m, free, i));
        return best;
    }

    /* --- Selecting, editing and deleting a single measure -------------------
     *
     * The maintainer's second word on it (28.08.2026): NOT tap-and-dialog — a tap
     * SELECTS the measure (highlight plus an × beside it), the × deletes,
     * and the selected measure's defining points become DRAGGABLE — „dass
     * man ein Rechteck etwas größer ziehen kann". The handles sit on the
     * four original taps (two for a line): the flank handles lie on their
     * long sides, the axis handles on the short ends, so grabbing a side
     * does what the hand expects, and dragging an axis point may also
     * re-aim the whole axis. */

    let measureSelected = null;   // {m, free} — by reference, like everything
    /* THE MEASURE ONE CAME FOR from the statistics' thumbnails
       (04.10.2026: „wenn ich auf eine Rechteckmessung klicke … komme ich ja
       direkt zu dem Bild. Allerdings wird mir die Spore an sich nicht
       hervorgehoben … diese Messung in der Akzentfarbe darstellen"). Drawn
       in the accent until one turns to something else in the picture — a
       tap, a selection, a tool, another picture; zooming and panning to
       look closer keep it, that is what one came to do. */
    let measureFocus    = null;
    /* Which measure each statistics tile shows — so the tile of the focused
       measure can say so (thumbsFocusSync). A WeakMap: tiles are rebuilt
       with every refresh, and nothing has to tidy up after them. */
    const thumbMeasures = new WeakMap();
    /* The same for the dots of the distribution plot (plotFocusSync). */
    const plotMeasures  = new WeakMap();

    /** Focus a measure (or none) and let its tile wear it: framed in the
     *  accent, and without hover its × and mirror buttons shown. ONE truth
     *  for both, so the frame on the tile is always the measure standing
     *  out in the picture (04.10.2026: „der Kachel beim
     *  Überfahren oder beim Draufklicken noch einen Rahmen"). */
    function setMeasureFocus(m) {
        measureFocus = m;
        thumbsFocusSync();
    }
    /* AND THE OTHER WAY ROUND (04.10.2026: „wenn ich gerade
       per Pfeil ein Maß, ein Rechteck oder irgendwas antippe, dass es mir
       dann auch in der Sidebar hervorgehoben wird"): a measure SELECTED in
       the picture marks its tile the same way, and the statistics roll to
       it when it lies out of sight — once per new choice, not on every
       redraw. Run from drawOverlay(), which every change of either ends
       in, so the two can never disagree. */
    let thumbChosen = null;
    function thumbChoice() {
        return measureFocus ?? measureSelected?.m ?? null;
    }
    function thumbsFocusSync() {
        const chosen = thumbChoice();
        let tile = null;
        for (const t of document.querySelectorAll('#photo-stats-thumbs .photo-stats-thumb')) {
            const on = chosen !== null && thumbMeasures.get(t) === chosen;
            t.classList.toggle('is-open', on);
            if (on) tile = t;
        }
        if (tile !== null && chosen !== thumbChosen) {
            tile.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
        thumbChosen = chosen;
        plotFocusSync();
    }
    /* AND IN THE DISTRIBUTION (04.10.2026: „eine Spore dort in
       den Kacheln anklicken und markieren, dass wir sie auch im
       Verteilungsdiagramm hervorheben, wo sie dort liegt" — and for every
       layer, not only spores). The chosen measure's dot grows, takes a
       ring in the text colour and moves to the front; the others step
       back, so the one stands out in a cloud of thirty without a legend.
       Attributes, not a class: the dot's size is the SVG attribute r, and
       Safari does not let a stylesheet change it everywhere. A measure
       without a dot — a cutout with one value — leaves the cloud alone. */
    function plotFocusSync() {
        const plot = document.getElementById('photo-stats-plot');
        if (!plot) return;
        const chosen = thumbChoice();
        const dots = [...plot.querySelectorAll('circle.photo-stats-dot')];
        const hit = chosen === null ? null
            : dots.find(d => plotMeasures.get(d) === chosen) ?? null;
        for (const d of dots) {
            const on = d === hit;
            d.setAttribute('r', on ? '5' : '3');
            d.setAttribute('fill-opacity', on ? '1' : (hit ? '.35' : '.7'));
            if (on) {
                d.setAttribute('stroke', 'var(--text)');
                d.setAttribute('stroke-width', '1.5');
            } else {
                d.removeAttribute('stroke');
                d.removeAttribute('stroke-width');
            }
        }
        if (hit) plot.append(hit);
    }
    let removeAnchor    = null;   // the ×'s centre in CURRENT natural pixels
    let rotateHandles   = null;   // the corner squares' centres, natural pixels
    let pointHandles    = null;   // [{pos, index}] — the round movers
    let measureDrag     = null;   // point drag: {kind:'point', m, index, across,
                                  //   free, moved} — rotation: {kind:'rotate',
                                  //   m, across, centre, base, startAngle, …}
    let suppressClick   = false;  // the click that trails a finished drag
    let pressAt         = null;   // where the last press on the picture began, to tell a pan's click

    /** The current-image factor for a measure's stored space. */
    function measureAcross(m) {
        return m.width && photoImageEl.naturalWidth
            ? photoImageEl.naturalWidth / m.width : 1;
    }

    /** A pointer event as ORIGINAL image pixels, or null off the image. */
    function naturalPoint(e) {
        const at = clientNatural(e.clientX, e.clientY);
        return at && at.inside ? at.p : null;
    }

    /** Screen pixels restated in natural units at the current zoom. */
    function screenUnits(px) {
        return px / (viewScale() * photoZoom.stufe());
    }

    function measureDeselect() {
        if (measureSelected === null) return;
        measureSelected = null;
        removeAnchor = null;
        // The cutout offer rode on the selection — it leaves with it.
        frameClose();
        drawOverlay();
    }

    /** The selection's dress: round handles on the defining points, square
     *  ROTATION handles on a rectangle's corners (turning happens about the
     *  centre) — in the LINE COLOUR with its seam again since 07.10.2026,
     *  like the measure they belong to (the accent of 04.10.2026 was lost
     *  on a pale bright field, see drawOverlay) — and a RED ×
     *  beside the measure, red, because deleting is the danger, and the
     *  meaning colours say so everywhere in this application. */
    function drawSelection() {
        const m = measureSelected?.m;
        textHandles = null;
        arrowHandle = null;
        arrowSizeHandle = null;
        boxHandles = null;
        if (!m) { removeAnchor = null; rotateHandles = null; pointHandles = null; return ''; }
        if (m.type === 'text') return drawTextSelection(m);
        if (m.type === 'box') return drawBoxSelection(m);
        if (m.type === 'sector') return drawSectorSelection(m);
        const across = measureAcross(m);
        const pts = m.points.map(([x, y]) => [x * across, y * across]);
        const r = screenUnits(5);
        let inner = '';
        /* The corner squares FIRST, under the circles: a flank tap can sit
           close to a corner, and the mover should win the overlap. */
        rotateHandles = null;
        // A picture cutout is moved, stretched and turned like a rectangle.
        const rectLike = m.type === 'rect' || m.type === 'crop';
        if (rectLike) {
            rotateHandles = rectGeometry(pts).corners;
            const half = screenUnits(4.5);
            for (const [x, y] of rotateHandles) {
                inner += `<rect x="${x - half}" y="${y - half}"
                        width="${2 * half}" height="${2 * half}"
                        fill="${measureStyle.line_color}"
                        stroke="${measureStyle.seam_color}"
                        stroke-width="${screenUnits(1)}"/>`;
            }
        }
        /* The movers sit at the EDGE MIDPOINTS of a rectangle, not at the
           original taps (28.08.2026: „in die Mitte … nicht da wo
           ich ursprünglich geklickt habe") — each midpoint drags the point
           that defines its side: the long sides their flank tap, the short
           ends their axis tap. A line's movers stay its two ends. */
        if (rectLike && rotateHandles !== null) {
            const mid = (i, j) => [
                (rotateHandles[i][0] + rotateHandles[j][0]) / 2,
                (rotateHandles[i][1] + rotateHandles[j][1]) / 2,
            ];
            pointHandles = [
                { pos: mid(3, 0), index: 0 },   // short end at the axis start
                { pos: mid(1, 2), index: 1 },   // short end at the axis end
                { pos: mid(0, 1), index: 2 },   // the o1 flank side
                { pos: mid(2, 3), index: 3 },   // the o2 flank side
            ];
            /* A cutout's inner measures get their OWN handles, every point
               free — a traced paraphysis is corrected point by point, no
               rails apply. The frame's movers above keep resizing the frame;
               the whole unit still turns together at the corner squares. */
            (m.inner ?? []).forEach((mi, i) => {
                for (const [j, [x, y]] of mi.points.entries()) {
                    pointHandles.push({ pos: [x * across, y * across], index: j, inner: i });
                }
            });
        } else {
            pointHandles = pts.map((pos, index) => ({ pos, index }));
        }
        /* AN ARROW'S STRENGTH is pulled at a square beside its shaft — away
           from the arrow stronger, toward it weaker, a step at a time — and
           the step stands beside it, to set the next arrow of a series
           alike. */
        arrowHandle = null;
        arrowSizeHandle = null;
        if (m.type === 'arrow') {
            const g = arrowGeometry(m);
            const [hx, hy] = g.handle, half = screenUnits(4.5);
            const deg = Math.atan2(g.n[1], g.n[0]) * 180 / Math.PI;
            inner += `<rect x="${hx - half}" y="${hy - half}" width="${2 * half}" height="${2 * half}"
                    fill="${measureStyle.line_color}" stroke="${measureStyle.seam_color}"
                    stroke-width="${screenUnits(1)}" transform="rotate(${deg} ${hx} ${hy})"/>`
                + `<text x="${hx + screenUnits(9)}" y="${hy + screenUnits(4)}"
                    font-size="${screenUnits(11)}" font-weight="600" fill="${measureStyle.line_color}"
                    stroke="${measureStyle.seam_color}" stroke-width="${screenUnits(2.5)}"
                    paint-order="stroke">${escapeXml(t('Stärke %s', arrowWeightOf(m)))}</text>`;
            arrowHandle = { pos: g.handle, normal: g.n };
            // Its words are part of it: framed with it, dashed as a chosen text is.
            const words = arrowWords(m);
            arrowSizeHandle = null;
            if (words) {
                const ring = words.corners.map(q => q.join(',')).join(' ');
                inner += `<polygon points="${ring}" fill="none" stroke="${measureStyle.seam_color}" stroke-width="${2 * screenUnits(1)}"/>`
                    + `<polygon points="${ring}" fill="none" stroke="${measureStyle.line_color}" stroke-width="${screenUnits(1)}"
                        stroke-dasharray="${screenUnits(4)} ${screenUnits(3)}"/>`;
                /* THE WORDS HAVE THEIR OWN SIZE (15.11.2026:
                   „Dort ändert sich ja Schriftgröße und Pfeildicke zusammen.
                   Kann man das ändern, also einen kleineren Text zum
                   Beispiel, dafür einen dicken Pfeil"). The square at the
                   words' lower right corner sizes them alone, in the steps
                   of a text, as a text's square does; the strength still
                   carries them along when it is pulled afterwards
                   (arrowTextLook) — what the square sets is the size they
                   show NOW. Stored in the arrow's own `size`, which the
                   plates already read (plateannot.js), so nothing new
                   travels to the server. */
                if (textEdit?.m !== m) {
                    const [[l, top], [r, , ], [, bottom]] = [words.corners[0], words.corners[1], words.corners[2]];
                    const grip = arrowWordsGrip(g.tail, g.tip, { left: l, top, right: r, bottom });
                    const [sx, sy] = grip.pos, [, ay] = grip.away;
                    // The number under the grip (above it on the words' upper side), as on the plate.
                    const anchor = 'middle';
                    const nx = sx, ny = sy + screenUnits(ay < 0 ? -10 : 19);
                    inner += `<rect x="${sx - half}" y="${sy - half}" width="${2 * half}" height="${2 * half}"
                            fill="${measureStyle.line_color}" stroke="${measureStyle.seam_color}"
                            stroke-width="${screenUnits(1)}"/>`
                        + `<text x="${nx}" y="${ny}" text-anchor="${anchor}"
                            font-size="${screenUnits(11)}" font-weight="600" fill="${measureStyle.line_color}"
                            stroke="${measureStyle.seam_color}" stroke-width="${screenUnits(2.5)}"
                            paint-order="stroke">${escapeXml(t('Größe %s', arrowWordsShown(words)))}</text>`;
                    arrowSizeHandle = { pos: [sx, sy], tail: g.tail };
                }
            }
        }
        for (const { pos: [x, y] } of pointHandles) {
            inner += `<circle cx="${x}" cy="${y}" r="${r}"
                    fill="${measureStyle.line_color}"
                    stroke="${measureStyle.seam_color}"
                    stroke-width="${screenUnits(1)}"/>`;
        }
        /* The × sits OFF A CORNER, pushed outward and clear of the numbers
           (28.08.2026: „mitten in der Zahl … an irgendeiner Ecke
           und ein bisschen davon entfernt"): the labels ride the length and
           width edges around corner 1, so the rectangle's corner 3 — across
           from both — is the quiet one; a line gets it just past its second
           end. Drawn IN the overlay, clamped into the picture, so it rides
           every zoom and pan without a line of positioning code. */
        const off = screenUnits(22);
        let anchor;
        if ((m.type === 'rect' || m.type === 'crop') && rotateHandles !== null) {
            const corner = rotateHandles[3];
            const cx0 = (rotateHandles[0][0] + rotateHandles[2][0]) / 2;
            const cy0 = (rotateHandles[0][1] + rotateHandles[2][1]) / 2;
            const d = Math.hypot(corner[0] - cx0, corner[1] - cy0) || 1;
            anchor = [corner[0] + (corner[0] - cx0) / d * off,
                      corner[1] + (corner[1] - cy0) / d * off];
        } else if (pts.length >= 2) {
            // Past the run's last point, in its own direction.
            const a = pts[pts.length - 2], b = pts[pts.length - 1];
            const d = Math.hypot(b[0] - a[0], b[1] - a[1]) || 1;
            anchor = [b[0] + (b[0] - a[0]) / d * off,
                      b[1] + (b[1] - a[1]) / d * off];
        } else {
            anchor = [pts[0][0], pts[0][1] - off];
        }
        return inner + drawRemoveAt(anchor);
    }

    /**
     * The selected TEXT's dress (05.10.2026: „mit einer Box
     * versehen, um die Schriftgröße zu ziehen, und Rotation setzen"): a
     * dashed box in the accent, a square at its lower right corner that
     * sizes the letters, a round handle on a stem above it that turns
     * them, and the red × off its upper right corner. Inside the box a
     * drag moves the text and a second tap opens it for writing. While it
     * is being written only the box stands — the handles would be a second
     * meaning for the same finger.
     */
    function drawTextSelection(m) {
        rotateHandles = null;
        pointHandles = null;
        const box = textBox(m);
        const hair = screenUnits(1);
        const ring = box.corners.map(p => p.join(',')).join(' ');
        let inner = `<polygon points="${ring}" fill="none"
                stroke="${measureStyle.seam_color}" stroke-width="${2 * hair}"/>`
            + `<polygon points="${ring}" fill="none" stroke="${measureStyle.line_color}" stroke-width="${hair}"
                stroke-dasharray="${screenUnits(4)} ${screenUnits(3)}"/>`;
        if (textEdit !== null) {
            removeAnchor = null;
            return inner;
        }
        const r = screenUnits(5), half = screenUnits(4.5);
        const [sx, sy] = box.sizeHandle, [rx, ry] = box.rotateHandle, [fx, fy] = box.stemFoot;
        inner += svgSeamed('line', `x1="${fx}" y1="${fy}" x2="${rx}" y2="${ry}"`)
            + `<circle cx="${rx}" cy="${ry}" r="${r}" fill="${measureStyle.line_color}"
                    stroke="${measureStyle.seam_color}" stroke-width="${hair}"/>`
            + `<rect x="${sx - half}" y="${sy - half}" width="${2 * half}" height="${2 * half}"
                    fill="${measureStyle.line_color}" stroke="${measureStyle.seam_color}" stroke-width="${hair}"
                    transform="rotate(${box.angle} ${sx} ${sy})"/>`;
        textHandles = { size: box.sizeHandle, rotate: box.rotateHandle, corners: box.corners };
        /* THE SIZE STANDS BESIDE ITS HANDLE, as the number one sets the next
           text of a series to — upright and the same size on screen
           whatever the text's own size and turn. */
        inner += `<text x="${sx + screenUnits(9)}" y="${sy + screenUnits(17)}"
                font-size="${screenUnits(11)}" font-weight="600" fill="${measureStyle.line_color}"
                stroke="${measureStyle.seam_color}" stroke-width="${screenUnits(2.5)}"
                paint-order="stroke">${escapeXml(t('Größe %s', textShown(m)))}</text>`;
        // The × off the upper right corner, pushed outward from the middle.
        const [kx, ky] = box.corners[1], off = screenUnits(16);
        const d = Math.hypot(kx - box.centre[0], ky - box.centre[1]) || 1;
        return inner + drawRemoveAt([kx + (kx - box.centre[0]) / d * off,
                                     ky + (ky - box.centre[1]) / d * off]);
    }

    /** The chosen BOX's dress: the turning circle on its stem above the
     *  top edge, as on a text, and the red × off the upper right corner.
     *  No size handle — the size is typed in the line over the picture. */
    function drawBoxSelection(m) {
        rotateHandles = null;
        pointHandles = null;
        const g = boxGeometry(m);
        if (g === null) { removeAnchor = null; return ''; }
        const hair = screenUnits(1), r = screenUnits(5);
        const [rx, ry] = g.rotateHandle, [fx, fy] = g.stemFoot;
        const inner = svgSeamed('line', `x1="${fx}" y1="${fy}" x2="${rx}" y2="${ry}"`)
            + `<circle cx="${rx}" cy="${ry}" r="${r}" fill="${measureStyle.line_color}"
                    stroke="${measureStyle.seam_color}" stroke-width="${hair}"/>`;
        boxHandles = { rotate: g.rotateHandle, corners: g.corners };
        const [kx, ky] = g.corners[1], off = screenUnits(16);
        const d = Math.hypot(kx - g.centre[0], ky - g.centre[1]) || 1;
        return inner + drawRemoveAt([kx + (kx - g.centre[0]) / d * off,
                                     ky + (ky - g.centre[1]) / d * off]);
    }

    /** The red × at a place, clamped into the picture — and where it came
     *  to stand, for the click that deletes. */
    function drawRemoveAt(anchor) {
        const off = screenUnits(22);
        removeAnchor = [
            Math.min(Math.max(anchor[0], off / 2), photoImageEl.naturalWidth - off / 2),
            Math.min(Math.max(anchor[1], off / 2), photoImageEl.naturalHeight - off / 2),
        ];
        let inner = '';
        const [cx, cy] = removeAnchor;
        const cr = screenUnits(9), arm = screenUnits(4);
        inner += `<circle cx="${cx}" cy="${cy}" r="${cr}" fill="var(--ground)"
                stroke="var(--error)" stroke-width="${screenUnits(1.5)}"/>`
            + `<line x1="${cx - arm}" y1="${cy - arm}" x2="${cx + arm}" y2="${cy + arm}"
                stroke="var(--error)" stroke-width="${screenUnits(1.5)}"/>`
            + `<line x1="${cx - arm}" y1="${cy + arm}" x2="${cx + arm}" y2="${cy - arm}"
                stroke="var(--error)" stroke-width="${screenUnits(1.5)}"/>`;
        return inner;
    }

    /** Which corner square a point lands on, or null. */
    function rotateHandleAt(p, reach) {
        if (rotateHandles === null) return null;
        let best = null, bestD = reach;
        rotateHandles.forEach(([x, y], i) => {
            const d = Math.hypot(p[0] - x, p[1] - y);
            if (d <= bestD) { best = i; bestD = d; }
        });
        return best;
    }

    /** Which mover a point lands on — by the DRAWN handle positions, which
     *  for a rectangle are its edge midpoints, not the raw taps. */
    /** The nearest grabbable handle — the WHOLE entry, because since the
     *  cutouts a handle may address an inner measure's point (`inner` set)
     *  instead of the selected measure's own. */
    function handleAt(p, reach) {
        if (pointHandles === null) return null;
        let best = null, bestD = reach;
        for (const h of pointHandles) {
            const d = Math.hypot(p[0] - h.pos[0], p[1] - h.pos[1]);
            if (d <= bestD) { best = h; bestD = d; }
        }
        return best;
    }

    function measureSelectedDelete() {
        const sel = measureSelected;
        measureSelected = null;
        removeAnchor = null;
        // The × may take the very rectangle the open offer furnishes.
        if (frameRect !== null && frameRect.m === sel.m) frameClose();
        if (sel.free) {
            const at = freeMeasures.indexOf(sel.m);
            if (at >= 0) freeMeasures.splice(at, 1);
        } else {
            const at = measures.indexOf(sel.m);
            if (at >= 0) measures.splice(at, 1);
            measureSaveQueue();
            statsRefresh();
        }
        scaleTopbarSync();
        drawOverlay();
    }

    /**
     * Written words as SVG — a text's, or an arrow's own (30.10.2026). One
     * tspan per line, each back at x and one leading lower; an empty line
     * keeps a space, or it would take no room and the lines under it would
     * move up. `anchor` says which end of each line stands at x. The text
     * being written carries the caret, placed after drawing by
     * textCaretPlace(), because only the drawn letters know how wide they
     * came out. Spaces are kept as typed.
     */
    function writtenSvg(m, x, y, anchor, angle, fs = textSizeOf(m), look = { weight: 600, thicken: 0 }) {
        const writing = textEdit?.m === m;
        const turn = angle ? ` transform="rotate(${angle} ${x} ${y})"` : '';
        const lines = textLines(m).map((line, i) =>
            `<tspan x="${x}" dy="${i === 0 ? 0 : fs * TEXT_LEADING}">${escapeXml(line) || ' '}</tspan>`).join('');
        /* A STRONG ARROW'S WORDS THICKEN (arrowTextLook): the seam widens by
           the same amount, and a second copy strokes the letters in their
           own colour over it. */
        const thick = look.thicken * fs;
        const attrs = `x="${x}" y="${y}" font-size="${fs}" font-weight="${look.weight}" xml:space="preserve" text-anchor="${anchor}"`;
        /* Where a mirrored view mirrors the words back (viewUnmirror). A
           TEXT about its anchor: its line still begins at the mirrored
           place and runs on readable, as the plate writes it at the place
           picView maps the anchor to (plateparts.js) — and textBox, built
           about the same anchor, keeps hit test and handles on it. An
           ARROW'S WORDS about the middle of their box, so they fill the
           box beyond the mirrored tail — the plate reckons them in the
           mirrored picture to begin with (plateannot.js, arrowTextAt). */
        let at = `${x} ${y}`;
        if (anchor === 'middle') {
            const my = y + ((textLines(m).length - 1) * fs * TEXT_LEADING - fs * .55) / 2;
            at = `${x} ${my}`;
        }
        return `<g${turn} data-at="${at}"${writing ? ' class="photo-text-writing"' : ''}>`
            + `<text ${attrs} fill="${measureStyle.line_color}" stroke="${measureStyle.seam_color}"
              stroke-width="${overlayHair * 2 + thick}" paint-order="stroke">${lines}</text>`
            + (thick > 0 ? `<text ${attrs} fill="${measureStyle.line_color}" stroke="${measureStyle.line_color}"
              stroke-width="${thick}" stroke-linejoin="round">${lines}</text>` : '')
            + (writing ? `<g class="photo-text-caret">`
              + `<line x1="${x}" y1="${y - fs * 0.8}" x2="${x}" y2="${y + fs * 0.2}"
              stroke="${measureStyle.seam_color}" stroke-width="${Math.max(fs / 14, overlayHair * 1.5) * 2.5}"/>`
              + `<line x1="${x}" y1="${y - fs * 0.8}" x2="${x}" y2="${y + fs * 0.2}"
              stroke="${measureStyle.line_color}" stroke-width="${Math.max(fs / 14, overlayHair * 1.5)}"/>`
              + '</g>' : '')
            + '</g>';
    }

    /**
     * WHERE AN ARROW'S WORDS STAND, in natural pixels of the image shown:
     * their middle beyond the tail, on the arrow's line (measuredraw.js,
     * arrowTextAt — the plate asks the same). Null for an arrow without
     * words, unless they are being written right now. `corners` is their
     * box, measured with the overlay's own font, for the hit test and the
     * selection's frame.
     */
    function arrowWords(m) {
        if (m.type !== 'arrow' || (!m.text && textEdit?.m !== m)) return null;
        const g = arrowGeometry(m);
        const look = arrowTextLook(m);
        const fs = textSizeOf(m) * look.scale;
        const lines = textLines(m);
        overlayFont ??= getComputedStyle(photoOverlay).fontFamily || 'sans-serif';
        textMeasure.font = `${look.weight} ${fs}px ${overlayFont}`;
        const w = Math.max(fs * .4, ...lines.map(l => textMeasure.measureText(l).width));
        const at = arrowTextAt(g.tail, g.tip, fs, lines.length, w);
        const x0 = at.x - w / 2;
        const pad = fs * 0.15 + screenUnits(3);
        const a = x0 - pad, b = x0 + w + pad, top = at.top - pad, bottom = at.top + at.height + pad;
        return { ...at, fs, look, corners: [[a, top], [b, top], [b, bottom], [a, bottom]] };
    }

    /** One finished measure as SVG — the label carries real micrometres.
     *
     *  THE REFERENCE WIDTH CONVERTS FIRST (migration 0092): the points were
     *  tapped on an image m.width pixels wide, drawn is onto whatever stands
     *  in the window now — preview or original, the factor carries them over
     *  exactly like photoUmPerPx carries the calibration. */
    function drawMeasure(m, umPerPx, fontSize) {
        if (m.type === 'sector') return sectorSvg(m);
        const across = m.width && photoImageEl.naturalWidth
            ? photoImageEl.naturalWidth / m.width : 1;
        const points = across === 1 ? m.points
            : m.points.map(([x, y]) => [x * across, y * across]);
        if (m.type === 'line') {
            const [[x1, y1], [x2, y2]] = points;
            const um = Math.hypot(x2 - x1, y2 - y1) * umPerPx;
            /* NO PART NAME BESIDE THE NUMBER (07.10.2026: „das
               müllt bei ein paar Messungen dann komplett die Seite zu"):
               a part's lines stand only in its own tab, and the tab says
               what they are (shownMeasures). */
            return svgSeamed('line', `x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"`)
                 + svgEdgeLabel(x1, y1, x2, y2, formatUm(um), fontSize);
        }

        /* THE CONNECTED POLYLINE (29.08.2026): a bent paraphysis
           or an ascus is no straight line — the label carries the SUM of the
           segments and lies along the last one. */
        if (m.type === 'path') {
            let sum = 0;
            for (let i = 1; i < points.length; i++) {
                sum += Math.hypot(points[i][0] - points[i - 1][0],
                                  points[i][1] - points[i - 1][1]);
            }
            const a = points[points.length - 2], b = points[points.length - 1];
            return svgSeamed('polyline',
                    `points="${points.map(p => p[0] + ',' + p[1]).join(' ')}"`)
                 + svgEdgeLabel(a[0], a[1], b[0], b[1], formatUm(sum * umPerPx), fontSize);
        }

        /* THE SEAM KEEPS ITS WIDTH ON SCREEN, as the arrow's and every
           line's do (06.10.2026: „beim Vergrößern wächst die
           Umrandung gleichermaßen mit der Schriftgröße. Das würde ich nicht
           machen. Beim Pfeil geschieht das ja auch nicht"). It was an eighth
           of the letters' height — at the account's font size the same two
           pixels as now, at a large size a black band round every letter.

           WRITING ON THE PICTURE: one anchor, the user's own words — escaped,
           because unlike every label here they are not numbers. In its own
           size and turned about its anchor since 05.10.2026 (textBox says
           where its box lies). The text being written carries the caret,
           placed after drawing by textCaretPlace(), because only the drawn
           letters know how wide they came out. Spaces are kept as typed. */
        if (m.type === 'text') {
            const [[x, y]] = points;
            return writtenSvg(m, x, y, 'start', m.angle ?? 0);
        }

        /* THE ARROW (05.10.2026: „ein extra Tool, das Pfeile
           zeichnet"): from the tail to the tip, one solid shape in the line
           colour with the seam round it, so it reads on any ground — in its
           own strength (arrowGeometry). Its OWN WORDS at the tail since
           30.10.2026, growing away from it (arrowWords). */
        if (m.type === 'arrow') {
            const g = arrowGeometry(m);
            const words = arrowWords(m);
            return `<polygon points="${g.outline.map(q => q.join(',')).join(' ')}"
                    fill="${measureStyle.line_color}" stroke="${measureStyle.seam_color}"
                    stroke-width="${overlayHair * 2 * lineWeight}" stroke-linejoin="round" paint-order="stroke"/>`
                + (words ? writtenSvg(m, words.x, words.y, words.anchor, 0, words.fs, words.look) : '');
        }

        /* THE BOX OF A FIXED SIZE: a fine line with its seam, as every line
           here — it lies over the very structures one counts, and a fat
           frame would hide the pores at its rim. Its size stands under it,
           as typed („1 × 1 mm"), parallel to the bottom edge and outside. */
        if (m.type === 'box') {
            const g = boxGeometry(m);
            if (g === null) return '';
            const [, , c, d] = g.corners;
            /* THE COUNTED POINTS, lightly (18.11.2026: „Die werden
               mir dann leicht markiert. Dass ich sehe, was ich schon gezählt
               habe. Zum Beispiel bei den Poren ist das ja oft
               unübersichtlich"): a small dot in the line colour, half see-
               through, so the pore under it still shows; the count stands
               beside the size under the frame. */
            const r = screenUnits(4.5);
            const dots = (m.count ?? []).map((q) => {
                const [x, y] = g.fromLocal(q);
                return `<circle cx="${x}" cy="${y}" r="${r}" fill="${measureStyle.line_color}" fill-opacity=".55"
                        stroke="${measureStyle.seam_color}" stroke-width="${overlayHair}" pointer-events="none"/>`;
            }).join('');
            const n = (m.count ?? []).length;
            return svgSeamed('polygon', `points="${g.corners.map(q => q.join(',')).join(' ')}"`) + dots
                 + svgEdgeLabel(d[0], d[1], c[0], c[1], n > 0 ? t('%s · %d gezählt', boxLabel(m), n) : boxLabel(m), fontSize,
                     (c[0] + d[0]) / 2 - g.centre[0], (c[1] + d[1]) / 2 - g.centre[1]);
        }

        /* A PICTURE CUTOUT (07.10.2026) measures nothing, so it carries no
           numbers: a fine dashed frame, its name along a long side, and a
           small wedge beyond the end of the second tap — that end stands
           up in the plate's row, and the wedge says so before one looks
           at the plate. */
        if (m.type === 'crop') {
            const c = rectGeometry(points).corners;
            const [a, b] = points;
            const len = Math.hypot(b[0] - a[0], b[1] - a[1]) || 1;
            const ux = (b[0] - a[0]) / len, uy = (b[1] - a[1]) / len;
            const tx = (c[1][0] + c[2][0]) / 2, ty = (c[1][1] + c[2][1]) / 2;
            const w = screenUnits(6);
            const wedge = [[tx + uy * w, ty - ux * w], [tx + ux * w * 1.3, ty + uy * w * 1.3], [tx - uy * w, ty + ux * w]];
            const mx = (c[0][0] + c[2][0]) / 2, my = (c[0][1] + c[2][1]) / 2;
            const name = measureCrop(m);
            return `<polygon points="${c.map(q => q[0] + ',' + q[1]).join(' ')}"
                  fill="none" stroke="${measureStyle.seam_color}" stroke-width="${overlayHair * 2 * lineWeight}" opacity=".6"/>`
                + `<polygon points="${c.map(q => q[0] + ',' + q[1]).join(' ')}"
                  fill="none" stroke="${measureStyle.line_color}" stroke-width="${overlayHair * lineWeight}"
                  stroke-dasharray="${overlayHair * 5} ${overlayHair * 4}"/>`
                + svgSeamed('polyline', `points="${wedge.map(q => q[0] + ',' + q[1]).join(' ')}"`)
                + (name ? svgEdgeLabel(c[0][0], c[0][1], c[1][0], c[1][1], escapeXml(name), fontSize,
                    (c[0][0] + c[1][0]) / 2 - mx, (c[0][1] + c[1][1]) / 2 - my) : '');
        }

        const { len, breadth, corners } = rectGeometry(points);
        /* ITS MARK, short („Chryso"), along the side across from the
           length's number and pushed outward like it — the picture says
           which of the measured ones is which kind (measureMark). */
        const mark = measureMark(m);
        const markLabel = mark === '' ? '' : (() => {
            const mx = (corners[0][0] + corners[2][0]) / 2, my = (corners[0][1] + corners[2][1]) / 2;
            return svgEdgeLabel(corners[3][0], corners[3][1], corners[2][0], corners[2][1],
                escapeXml(markShort(mark)), fontSize,
                (corners[2][0] + corners[3][0]) / 2 - mx, (corners[2][1] + corners[3][1]) / 2 - my);
        })();
        /* THE CUTOUT BEING MEASURED INTO STEPS ASIDE (07.10.2026:
           „den Ausschnitt lege ich natürlich sehr knapp um das Objekt, wenn
           ich dann allerdings beim weiteren Messen die Enden sehen will und
           die Breite, dann stört diese Linie … fast ausblenden … oder
           einfach etwas größer machen, in alle Richtungen etwas mehr
           Platz"). While its length and breadth are taken, the frame is
           drawn faint, dashed, ten screen pixels outside its own edges and
           without numbers — the structure's ends and flanks lie bare. Its
           inner measures in their plain strength, though the frame is the
           chosen one: a strong line over an end is the same trouble. Done,
           it is the dashed cutout below again. */
        if (frameRect !== null && m === frameRect.m) {
            /* A CYSTIDIUM'S FRAME STAYS IN SIGHT (05.11.2026: „das
               Rechteck kannst du etwas deutlicher machen"): four measures
               are taken in it one after the other, and the frame is where
               the eye returns between them — clearer than the cutout's
               near-hidden one, still outside the edges. */
            const clear = Boolean(m.profile);
            const d = screenUnits(10);
            const unit = (a, b) => {
                const l = Math.hypot(b[0] - a[0], b[1] - a[1]) || 1;
                return [(b[0] - a[0]) / l, (b[1] - a[1]) / l];
            };
            const [ux, uy] = unit(corners[0], corners[1]);
            const [vx, vy] = unit(corners[1], corners[2]);
            const wide = [[-1, -1], [1, -1], [1, 1], [-1, 1]].map(([su, sv], i) => [
                corners[i][0] + (su * ux + sv * vx) * d,
                corners[i][1] + (su * uy + sv * vy) * d,
            ]);
            let drawn = `<polygon points="${wide.map(p => p[0] + ',' + p[1]).join(' ')}"
              fill="none" stroke="${measureStyle.line_color}" stroke-width="${overlayHair * (clear ? 1.6 : 1)}"
              stroke-dasharray="${overlayHair * (clear ? 7 : 5)} ${overlayHair * (clear ? 3 : 4)}" opacity="${clear ? '.85' : '.3'}"/>`;
            const weight = lineWeight;
            lineWeight = 1;
            try {
                for (const mi of m.inner ?? []) drawn += drawMeasure({ ...mi, width: m.width }, umPerPx, fontSize);
            } finally {
                lineWeight = weight;
            }
            return drawn;
        }
        /* A RECTANGLE WITH INNER MEASURES IS A CUTOUT
           (30.08.2026: „Das Rechteck ist dann einfach nur der Ausschnitt") —
           its own edges measure nothing any more, so they carry no numbers
           and go DASHED, the drawing convention for a viewport. The inner
           measures draw through the same code as their free-standing
           siblings, in the parent's stored space: one width for the whole
           unit, or preview and original would disagree per member. */
        if (Array.isArray(m.inner) && m.inner.length > 0) {
            // A cystidium's frame a little stronger, as while it is measured.
            const strong = m.profile ? 1.6 : 1;
            let drawn = `<polygon points="${corners.map(p => p[0] + ',' + p[1]).join(' ')}"
              fill="none" stroke="${measureStyle.line_color}"
              stroke-width="${overlayHair * lineWeight * strong}"
              stroke-dasharray="${overlayHair * (m.profile ? 7 : 5)} ${overlayHair * (m.profile ? 3 : 4)}"/>`;
            for (const mi of m.inner) {
                drawn += drawMeasure({ ...mi, width: m.width }, umPerPx, fontSize);
            }
            return drawn + markLabel;
        }
        /* EACH MEASURE ON ITS OWN SIDE (28.08.2026: „nicht als ein
           langer Text … pro Seite das Maß"): the length lies along a length
           side, the width along a width side, each parallel to its edge and
           pushed OUTWARD from the rectangle's centre — inside it would cover
           the very thing being measured. */
        const cx = (corners[0][0] + corners[2][0]) / 2;
        const cy = (corners[0][1] + corners[2][1]) / 2;
        const outward = (edgeA, edgeB) => [
            (edgeA[0] + edgeB[0]) / 2 - cx,
            (edgeA[1] + edgeB[1]) / 2 - cy,
        ];
        const [lenAwayX, lenAwayY] = outward(corners[0], corners[1]);
        const [widAwayX, widAwayY] = outward(corners[1], corners[2]);
        /* A SINGLE line, no seam (28.08.2026: „nur als
           Einzellinie") — the rectangle hugs the measured thing, and even the
           thin double stroke read as a frame covering its edges. */
        return `<polygon points="${corners.map(p => p[0] + ',' + p[1]).join(' ')}"
              fill="none" stroke="${measureStyle.line_color}"
              stroke-width="${overlayHair * lineWeight}"/>`
             + svgEdgeLabel(corners[0][0], corners[0][1], corners[1][0], corners[1][1],
                 formatUm(len * umPerPx), fontSize, lenAwayX, lenAwayY)
             + svgEdgeLabel(corners[1][0], corners[1][1], corners[2][0], corners[2][1],
                 formatUm(breadth * umPerPx), fontSize, widAwayX, widAwayY)
             + markLabel;
    }

    /* The four modes and their buttons — one table, so arming and
       disarming can never miss one. */
    const MEASURE_BUTTONS = {
        line: 'photo-measure-line',
        rect: 'photo-measure-rect',
        path: 'photo-measure-path',
        text: 'photo-annotate',
        arrow: 'photo-measure-arrow',
        box: 'photo-annotate-box',
    };

    /* THE PHONE'S TOOL CHOOSER SHOWS WHAT IS ARMED.
     *
     * It is TOLD, never asked: measureMode is the truth, and this writes it
     * into the chooser. So the chooser cannot drift away from the mode —
     * which matters because the mode also changes where the chooser is not
     * involved: Escape, the right click, another picture, the cutout offer.
     * The empty value is „Auswählen", the state in which a tap selects
     * instead of setting. While a cutout is being measured into, the inner
     * line or polyline is armed — and the chooser still says „Ausschnitt
     * mit Messung", because that is the tool the user picked.
     *
     * A CALIBRATION IS NOT A TOOL and has no line of its own: measureMode is
     * null while it runs, so the chooser says „Auswählen". The aiming hint on
     * the picture says what is really going on, and reaching for a tool ends
     * the calibration anyway (measureStart calls calibrateEnd first).
     *
     * Looked up per call and not held in a constant: this runs from
     * measureEnd(), and that is reached long before the phone bar's wiring
     * further down would have finished declaring anything. */
    /* THE TOOLS, IN ONE TABLE — the trigger's face, the menu's rows and the
     * answer to „which mode does this row arm" all read from here. Two lists
     * with the same tools would be the pair that drifts apart; the hidden
     * measuring row (MEASURE_BUTTONS above) is already the second place.
     *
     * IN GROUPS SINCE 04.10.2026 („die Bezeichnungen … das sind
     * ja Messungen. Das muss eben besser rüberkommen, dass das Messwerkzeuge
     * sind, während Zeigen kein Messwerkzeug ist und Text eben auch nichts
     * zum Messen ist"). „Auswählen" alone on top — the empty mode, in which
     * a tap selects an existing measure instead of setting one; then
     * „Messen" with the four that measure; then „Beschriften". Each row
     * carries a quiet second line, an example rather than a definition.
     *
     * THE RECTANGLE BECAME TWO the same day: „Länge × Breite" measures what
     * its four taps enclose, „Ausschnitt mit Messung" frames something bent
     * — a curved ascus, an allantoid spore — and measures INSIDE the frame
     * (frameOffer). Both store a 'rect'; a cutout is the one with inner
     * measures. CALLED „Länge × Breite, gebogen" since 07.10.2026: the four
     * measuring tools are then a square of two questions — straight or
     * bent, length alone or length × breadth (the maintainer picked the name
     * from that grid). */
    const PHONE_TOOLS = [
        { mode: '',       icon: 'pointer',        name: () => t('Auswählen'),
          hint: () => t('Messen beenden. Dann eine Messung anklicken, um sie zu ändern.') },
        /* THE LENGTHS FIRST, then length × breadth (07.10.2026)
           — the square of the four reads row by row: straight, bent. */
        { mode: 'line',   icon: 'measure-line',   group: 'measure', name: () => t('Strecke'),
          hint: () => t('gerade Länge') },
        { mode: 'path',   icon: 'measure-path',   group: 'measure', name: () => t('Gebogene Länge'),
          hint: () => t('für Gebogenes, entlang der Mittellinie') },
        { mode: 'rect',   icon: 'measure-rect',   group: 'measure', name: () => t('Länge × Breite'),
          hint: () => t('z. B. Sporen') },
        { mode: 'cutout', icon: 'measure-cutout', group: 'measure', name: () => t('Länge × Breite, gebogen'),
          hint: () => t('z. B. gebogener Ascus, allantoide Spore') },
        /* THE THIRD RECTANGLE (05.11.2026: „ein drittes
           Rechteck-Werkzeug, bei dem man beide Messmethoden auswählen
           kann"): a frame around one cystidium, and four measures inside
           it in the order of its method — Hebeloma's l · A · M · B, or
           Conocybe's length, belly, head and neck (CYSTID_PROFILES in
           measurestats.js). The method is chosen in the line over the
           picture and kept (cystidRow). */
        { mode: 'cystid', icon: 'measure-cystid', group: 'measure', name: () => t('Zystide in Teilen'),
          hint: () => t('Hebeloma: Kopf, Mitte, unten · Conocybe: Bauch, Köpfchen, Hals') },
        /* THE FRAME COUNTS (18.11.2026) — among the measuring
           tools since it does, at their end: pulled open, then counted in (boxCount). */
        /* „PORENZÄHLEN" AND „LAMELLEN ZÄHLEN" (18.11.2026: „Unter
           Sektor kann sich niemand was vorstellen. Und unter Rahmen kann sich
           eigentlich auch niemand was vorstellen … Man kann natürlich auch
           etwas anderes damit zählen. Aber darunter kann man sich irgendwie
           was vorstellen") — named for what they are mostly for; the frame
           a tool draws is still called a frame in its hints. */
        { mode: 'box',    icon: 'annotate-box',   group: 'measure', name: () => t('Poren zählen'),
          hint: () => t('aufziehen, dann darin zählen') },
        /* THE SECTOR (18.11.2026): the lamellae that reach the
           stipe, counted in a slice and taken round the whole cap — an
           ellipse first, for a cap photographed askew (sectorGeometry). */
        { mode: 'sector', icon: 'measure-sector', group: 'measure', name: () => t('Lamellen zählen'),
          hint: () => t('Hut als Ellipse, dann den ganzen Hut oder ein Teilstück zählen') },
        { mode: 'text',   icon: 'annotate',       group: 'label',   name: () => t('Text'),
          hint: () => t('direkt ins Bild schreiben') },
        { mode: 'arrow',  icon: 'annotate-arrow', group: 'label',   name: () => t('Pfeil'),
          hint: () => t('auf etwas zeigen') },
        /* A CUTOUT OF THE PICTURE, no measure (07.10.2026: „ein
           paar angefärbte Ascusspitzen … ein Ausschnitt … ausrichten, also
           drehen … eine Bildausschnittreihe, ohne Messen"). Cut in the layer
           on view, named in the line over the picture; the plate lines the
           cutouts of one name up in a row (plateparts.js, cropRows). */
        { mode: 'crop',   icon: 'crop',           group: 'cut',     name: () => t('Bildausschnitt'),
          hint: () => t('für eine Bildreihe auf der Tafel, ohne Messen') },
    ];
    const TOOL_GROUPS = { measure: () => t('Messen'), label: () => t('Beschriften'), cut: () => t('Für die Tafel') };

    function toolChoiceSync() {
        // Inside a cutout the inner tool is armed, but the cutout is the tool
        // — out of the tool as off a selected cutout taking its breadth.
        const mode = frameRect !== null && (measureMode === 'line' || measureMode === 'path')
            ? frameRect.mode : (frameRect?.armed ? frameRect.mode : (measureMode ?? ''));
        const current = PHONE_TOOLS.find(x => x.mode === mode) ?? PHONE_TOOLS[0];
        const trigger = document.getElementById('photo-phone-tool');
        /* ONLY THE DRAWING (09.09.2026: „ein Buttons messen und
           Werkzeuge nicht beschriften"). The word moves into the title and
           the screen-reader label — in the menu below it stands at every row
           anyway, and the row is the place where one READS it. The trigger
           only has to SAY what is on right now, and the drawing does that. */
        trigger.innerHTML = iconHtml(current.icon);
        trigger.title = current.name();
        trigger.setAttribute('aria-label', current.name());
        for (const row of document.querySelectorAll('#photo-phone-menu button, #photo-tool-rail button[data-tool]')) {
            row.classList.toggle('is-active', row.dataset.tool === current.mode);
        }
    }

    /** Open or close the tool menu. */
    function toolMenu(open) {
        /* „MESSUNGEN LEEREN" HANGS AT THE FOOT OF THE MENU
           (09.09.2026: „Dieser kann eigentlich noch zu den Messwerkzeugen
           dazu"), and whether it stands there is decided by the button in
           the measure bar: that one carries its [hidden] as long as there is
           nothing to clear. Copied over on unfolding instead of kept up
           alongside — then the row in the menu can never claim anything
           other than the button itself. */
        const clearRow = document.getElementById('photo-phone-menu-clear');
        if (clearRow) clearRow.hidden = document.getElementById('photo-measure-clear').hidden;
        if (open) photoFlap(false);
        document.getElementById('photo-phone-menu').hidden = !open;
        document.getElementById('photo-phone-tool')
            .setAttribute('aria-expanded', String(open));
    }

    function measureEnd() {
        // Words being written are kept — they were work (textEditEnd).
        textEditEnd(true);
        arrowJustSet = null;
        frameJustDone = null;
        frameLeave();
        measureMode = null;
        measurePoints = [];
        photoFrame.classList.remove('is-calibrating');
        for (const id of Object.values(MEASURE_BUTTONS)) {
            document.getElementById(id).classList.remove('is-active');
        }
        calibrationHint('');
        toolChoiceSync();
        drawOverlay();
    }

    /** Close the polyline in progress — from the double tap or any mode
     *  change (Escape takes back its last point instead, escapeStep).
     *  Fewer than two points is no run and falls away quietly; the MODE
     *  stays armed, runs come in series. */
    function pathFinish() {
        if (measureMode !== 'path') return;
        // A polyline drawn INTO a cutout belongs to its rectangle, not to
        // the picture's own list.
        if (frameRect !== null) {
            if (measurePoints.length >= 2) frameInnerAdd({ type: 'path', points: measurePoints });
            measurePoints = [];
            drawOverlay();
            return;
        }
        if (measurePoints.length >= 2) {
            const done = { type: 'path', points: measurePoints,
                width: photoImageEl.naturalWidth };
            if (statsLabel === MEASURE_FREE) {
                freeMeasures.push(done);
            } else {
                done.label = statsLabel;
                if (!scaleMayEdit()) done.temporary = true;
                measures.push(done);
                measureSaveQueue();
            }
            document.getElementById('photo-measure-clear').hidden = false;
        }
        measurePoints = [];
        drawOverlay();
    }

    /* --- The cutout: a frame, and the measures inside it ----------------------
       30.08.2026: „erst das Rechteck … dann kommen die zwei
       Schaltflächen Linie und Polylinie … Das Rechteck ist dann einfach nur
       der Ausschnitt, den wir anzeigen." Up to TWO measures go inside — a
       bent structure traced with the polyline, its breadth as a straight
       line — and THOSE are what the statistics count; the rectangle only
       frames what the sidebar's thumbnail shows.

       A TOOL OF ITS OWN SINCE 04.10.2026 („wie wir das Rechteck
       in zwei Werkzeuge teilen können … das eine ist wirklich ein Rechteck,
       Länge mal Breite … das andere ist einen Askus, der gebogen ist, eine
       allantoide Spore … messen"). Until then every rectangle offered to
       become a cutout afterwards, and the offer stood between every two
       spores of a series. Now „Länge × Breite" is a rectangle and nothing
       else, and „Länge × Breite, gebogen" (until 07.10.2026 „Ausschnitt mit
       Messung" — what comes out is a length and a breadth, as from its
       straight sibling) LEADS through its steps:

         1. the cutout, four taps as for „Länge × Breite" — „dann hat man
            immer ein präzises Rechteck". It counts as no measure; it is the
            part of the picture the tile shows, and its first two taps decide
            how the tile stands — the reason it stays a step of its own
            (07.10.2026, on a spore rolled into a spiral);
         2. the length, ALWAYS the polyline — two points and the double
            click are a straight one; „Abbrechen" throws the cutout away;
         3. the breadth, a straight line at the thickest place. No „Ohne
            Breite fertig" since 07.10.2026: the breadth belongs to it.

       NEVER TWO POLYLINES („entweder zwei Gerade oder eine Gerade
       und ein Polygon … mit zwei Polygonen ließe sich schlecht rechnen, und
       mir fällt kein Fall ein"): the second step offers the straight line
       only. */

    /* frameRect — the cutout being measured into — is declared with the
       other measuring state further up: toolChoiceSync() reads it, and
       that runs before this block is reached. */

    /** The step the cutout stands at, said in the hint bar with its buttons
     *  — the instruction and the way out in one line, where the eye already
     *  is between two taps. */
    function frameOffer() {
        const fr = frameRect;
        const count = fr.m.inner?.length ?? 0;
        const hint = document.getElementById('photo-frame-hint');
        hint.textContent = '';
        const say = (text) => {
            const span = document.createElement('span');
            span.textContent = text;
            hint.append(span);
        };
        const offer = (label, act) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = label;
            b.addEventListener('click', (e) => { e.stopPropagation(); act(); });
            hint.append(b);
        };

        const profile = CYSTID_PROFILES[fr.m.profile];
        if (profile) {
            /* A CYSTIDIUM'S STEPS, one after the other, each said in the
               line — off a selection (a cystidium left half measured) the
               missing step is offered, never armed unasked. */
            const step = cystidSteps(fr.m.profile)[count];
            if (!fr.armed && measureMode === null) {
                say(t('Hier fehlt noch: %s', step.word));
                offer(t('Weiter messen'), () => { frameArm(step.mode); frameOffer(); });
            } else {
                frameArm(step.mode);
                say(step.say);
                if (count === 0) offer(t('Abbrechen'), () => frameDiscard());
            }
            hint.hidden = false;
            toolChoiceSync();
            return;
        }
        if (!fr.armed && measureMode === null) {
            /* OFF A SELECTION nothing is armed unasked: the tap that selected
               may want the handles, and an armed line would swallow them.
               No „Fertig" beside it any more (07.10.2026) — without a
               breadth the measure is not done, and a click beside it closes
               the offer anyway (measureDeselect). */
            say(t('Hier fehlt noch die Breite.'));
            offer(t('Breite messen'), () => { frameArm('line'); frameOffer(); });
        } else if (count === 0) {
            /* THE LENGTH IS ALWAYS THE POLYLINE (07.10.2026: „wenn
               ich die erste Länge gerade haben will, dann setze ich nur zwei
               Punkte") — two points and the double click are a straight
               length, so the switch to a straight line went. */
            frameArm('path');
            say(t('Jetzt die Länge: Klicke in kleinen Schritten entlang der gedachten Mittellinie. Am Ende ein Doppelklick.'));
            offer(t('Abbrechen'), () => frameDiscard());
        } else {
            /* THE BREADTH BELONGS TO IT — the tool is „Länge × Breite,
               gebogen", and „Ohne Breite fertig" went (07.10.2026). Whoever
               walks away keeps the length; choosing the cutout again offers
               the breadth. */
            frameArm('line');
            say(t('Jetzt die Breite: Klicke an der dicksten Stelle auf beide Ränder.'));
        }
        hint.hidden = false;
        toolChoiceSync();
    }

    /** Arm one inner tool. The MODE machinery is reused wholesale — tap
     *  accumulation, previews, the polyline's double tap — only the finish
     *  lands in frameRect.inner instead of the picture's list. The hint is
     *  frameOffer's to write. */
    function frameArm(mode) {
        // Aiming taps and selection handles are two meanings for one finger
        // — the same rule measureStart states.
        measureSelected = null;
        removeAnchor = null;
        measureMode = mode;
        measurePoints = [];
        photoFrame.classList.add('is-calibrating');
        drawOverlay();
    }

    /** One inner measure done — store it, then the next step: the breadth
     *  after the length, or the next frame once both are in. */
    function frameInnerAdd(inner) {
        const fr = frameRect;
        (fr.m.inner ??= []).push(inner);
        if (!fr.free) {
            measureSaveQueue();
            // The row's values just changed shape (geometry → inner lengths).
            statsRefresh();
            // …and with them its place in the sorted tiles: roll it into
            // sight again.
            if (thumbChoice() === fr.m) {
                thumbChosen = null;
                thumbsFocusSync();
            }
        }
        if (fr.m.inner.length >= (CYSTID_PROFILES[fr.m.profile] ? cystidSteps(fr.m.profile).length : 2)) {
            frameJustDone = fr.armed ? { m: fr.m, free: fr.free } : null;
            frameClose();
            // Off a selection, the unit is handed back selected — the fresh
            // points get their handles right away, ready for a correction.
            if (!fr.armed) measureSelected = { m: fr.m, free: fr.free };
        } else {
            frameOffer();
        }
    }

    /** The cutout concluded — back to the cutout tool for the next frame,
     *  or, off a selection, to nothing armed. */
    function frameClose() {
        if (frameRect === null) return;
        const { armed, mode } = frameRect;
        frameRect = null;
        measurePoints = [];
        if (armed) {
            measureMode = mode;
            calibrationHint(measureModeHint(mode));
        } else {
            measureMode = null;
            photoFrame.classList.remove('is-calibrating');
            calibrationHint('');
        }
        toolChoiceSync();
        drawOverlay();
    }

    /** „Abbrechen" before anything was measured inside: the frame goes —
     *  a cutout without a measure in it is nothing the user asked for, and
     *  left standing it would count as „Länge × Breite". */
    function frameDiscard() {
        const fr = frameRect;
        if (fr === null) return;
        const list = fr.free ? freeMeasures : measures;
        const at = list.indexOf(fr.m);
        if (at >= 0) list.splice(at, 1);
        if (!fr.free) {
            measureSaveQueue();
            statsRefresh();
        }
        frameClose();
    }

    /** Leaving the cutout tool — another tool, „Auswählen", Escape: a frame
     *  with nothing measured in it yet goes, one with a measure stays. */
    function frameLeave() {
        if (frameRect !== null && frameRect.armed && (frameRect.m.inner?.length ?? 0) === 0) {
            frameDiscard();
        }
        frameRect = null;
    }

    /** Walk away from the cutout mid-flight — the layer switched, or the
     *  very rectangle was cleared under it. Unfinished inner taps are dropped
     *  WITHOUT committing: their rectangle may be gone. */
    function frameAbort() {
        if (frameRect === null) return;
        const fr = frameRect;
        // An empty frame still standing goes, as on „Abbrechen" — unless
        // the clearing took it already.
        if (fr.armed && (fr.m.inner?.length ?? 0) === 0) {
            const list = fr.free ? freeMeasures : measures;
            const at = list.indexOf(fr.m);
            if (at >= 0) {
                list.splice(at, 1);
                if (!fr.free) { measureSaveQueue(); statsRefresh(); }
            }
        }
        const armed = fr.armed;
        frameRect = null;
        measurePoints = [];
        if (armed) {
            measureMode = fr.mode;
            calibrationHint(measureModeHint(fr.mode));
        } else {
            measureMode = null;
            photoFrame.classList.remove('is-calibrating');
            calibrationHint('');
        }
        toolChoiceSync();
    }

    function calibrateEnd() {
        calibrating = null;
        photoFrame.classList.remove('is-calibrating');
        calibrationHint('');
        toolChoiceSync();
        drawOverlay();
    }

    /** One mode's aiming instruction — shared by measureStart and every spot
     *  that has to RESTORE the hint after the cutout offer covered it. */
    function measureModeHint(mode) {
        return {
            line: t('Klicke auf den Anfang, dann auf das Ende.'),
            rect: t('Vier Punkte: erst die Längsachse (2), dann die Breite (2) – je den äußersten Punkt links und rechts der Achse.'),
            cutout: t('Zuerst der Ausschnitt: vier Punkte, erst die Längsachse (2), dann die Breite (2) – je den äußersten Punkt links und rechts der Achse.'),
            cystid: t('Zuerst der Rahmen um eine Zystide: vier Punkte, erst die Längsachse (2), dann die Breite (2). Danach führt die Zeile hier Schritt für Schritt durch die Maße.'),
            path: t('Klicke in kleinen Schritten entlang der gedachten Mittellinie. Am Ende ein Doppelklick.'),
            text: t('Klicke dorthin, wo der Text stehen soll.'),
            arrow: t('Klicke dorthin, wo der Pfeil hinzeigen soll. Dann klicke dorthin, wo er beginnt.'),
            box: t('Ziehe den Rahmen auf. Er rastet in runden Maßen ein.'),
            sector: t('Drei Punkte: die Mitte des Hutes, dann der Hutrand in der langen Richtung, dann der Rand in der kurzen.'),
            crop: t('Vier Punkte: erst von unten nach oben (2) – in der Reihe steht der zweite Punkt oben –, dann die Breite (2).'),
        }[mode];
    }

    /** Toggle one of the two measuring modes. The mode STAYS armed after a
     *  finished measure — spores come in series, and re-pressing a button
     *  between every one of thirty would be the tool nobody uses. */
    function measureStart(mode) {
        arrowJustSet = null;
        /* THE FLAP GOES AWAY FIRST. On the phone the tools live in it, and
           whoever has just chosen one is about to aim at the picture — with
           the flap still up, at a picture two thirds covered. Arming and
           disarming both end here, so both close it. */
        photoFlapsClose();
        calibrateEnd();
        // Words being written are kept, a run still open is finished — the
        // keys and the taps were work.
        textEditEnd(true);
        pathFinish();
        // Reaching for the toolbar walks away from the cutout — a frame with
        // a measure in it stays, an empty one goes (frameLeave).
        frameLeave();
        // Arming a mode drops any selection — handles under aiming taps
        // would be two meanings for one finger. And the thumbnail's focus.
        measureSelected = null;
        setMeasureFocus(null);
        removeAnchor = null;
        if (measureMode === mode) { measureEnd(); return; }
        /* Every tool but the line measures something a part's tab does not
           show — back to „Alles", or the rectangle just drawn would vanish. */
        /* THE CUTOUT TOOL works in a cutout name's tab — the one on view,
           else the name last cut in this layer — so what it cuts stays in
           sight; the line keeps a part's tab; every other tool goes back to
           „Alles". */
        const tabNow = statsTabs.get(statsLabel) ?? null;
        if (mode === 'crop' && statsLabel !== MEASURE_FREE) {
            if (cropOfTab(tabNow) === null) {
                statsTabs.set(statsLabel, cropTab(cropNames(statsSources(), statsLabel).at(-1) ?? ''));
                statsRefresh();
            }
        } else if (tabNow !== null && (mode !== 'line' || cropOfTab(tabNow) !== null)) {
            statsTabs.delete(statsLabel);
            statsRefresh();
        }
        measureMode = mode;
        measurePoints = [];
        photoFrame.classList.add('is-calibrating');
        for (const [name, id] of Object.entries(MEASURE_BUTTONS)) {
            document.getElementById(id).classList.toggle('is-active', mode === name);
        }
        /* On a picture one may only look at, the tool says once that its
           measures stay here (06.10.2026) — the flap's line says it too,
           but on the phone the flap is closed while one aims. */
        calibrationHint(!scaleMayEdit() && statsLabel !== MEASURE_FREE
            ? measureModeHint(mode) + ' ' + t('Das gilt nur für dich und wird nicht gespeichert.')
            : measureModeHint(mode));
        toolChoiceSync();
        // The box's size fields take the line over (boxPanelBuild), and
        // so does the mark of a layer that offers marks (markPanelBuild).
        boxHintSync();
        sectorHintSync();
        markHintSync();
    }

    for (const [name, id] of Object.entries(MEASURE_BUTTONS)) {
        document.getElementById(id).addEventListener('click', () => measureStart(name));
    }
    /* Since the measures persist, clearing them is a LOSS and asks first —
       the one confirmation the tool has, precisely because it is the one
       step that cannot be undone (house rule: no question before the
       reversible). length = 0, not a fresh array: the global aliases the
       subject's own list, and the truth must empty WITH it. */
    document.getElementById('photo-measure-clear').addEventListener('click', async () => {
        /* THE LAYER, NOT EVERYTHING (28.08.2026: „Dann löschen
           wir einfach nur den einzelnen Layer") — the button empties what
           the chooser shows. The free layer goes without a question:
           nothing there was ever saved. A label layer is a loss and asks,
           BY NAME, so nobody mistakes it for the whole picture again. */
        if (statsLabel === MEASURE_FREE) {
            freeMeasures = [];
            // The cutout offer may point at a rectangle just wiped.
            frameAbort();
            measureSelected = null;
            removeAnchor = null;
            document.getElementById('photo-measure-clear').hidden = true;
            drawOverlay();
            return;
        }
        // On a picture one may only look at, the button takes one's own
        // measures — the photographer's are not one's own to clear — and
        // asks nothing, since nothing of them was ever saved.
        if (!scaleMayEdit()) {
            for (let i = measures.length - 1; i >= 0; i--) {
                if (measures[i].temporary && measureLabel(measures[i]) === statsLabel) measures.splice(i, 1);
            }
            frameAbort();
            measureSelected = null;
            removeAnchor = null;
            document.getElementById('photo-measure-clear').hidden = true;
            drawOverlay();
            statsRefresh();
            return;
        }
        if (layerMeasures().length === 0) return;
        if (!await confirm({
            title: t('Messungen löschen?'),
            text:  t('Alle Messungen der Ebene „%s" dieses Bildes werden entfernt — auch aus der Statistik. Das lässt sich nicht rückgängig machen. Einzelne wählt ein Tipp im Bild aus — das × daneben löscht sie.', statsLabel),
            button: t('Löschen'),
            danger: true,
        })) return;
        // In place, back to front — the array aliases the subject's own.
        for (let i = measures.length - 1; i >= 0; i--) {
            if (measureLabel(measures[i]) === statsLabel) measures.splice(i, 1);
        }
        // The cutout offer may point at a rectangle just wiped.
        frameAbort();
        measureSelected = null;
        removeAnchor = null;
        document.getElementById('photo-measure-clear').hidden = true;
        measureSaveQueue();
        drawOverlay();
        statsRefresh();
    });

    function calibrateStart() {
        measureEnd();
        measureSelected = null;
        removeAnchor = null;
        calibrating = [];
        photoFrame.classList.add('is-calibrating');
        calibrationHint(CALIBRATE_HINT());
        toolChoiceSync();
    }
    const CALIBRATE_HINT = () => t('Klicke auf Anfang und Ende einer Strecke, deren echte Länge du kennst – zum Beispiel auf den Maßstab im Bild. Du kannst dabei vergrößern.');

    /* --- The measuring row ------------------------------------------------- */

    const photoTopbar = document.getElementById('photo-topbar');
    const scaleSelect = document.getElementById('photo-scale-select');

    /** The chooser and the measure buttons, from the current photo's state.
     *  The first option narrates the state („10x", „eigene Eichung", „ohne"),
     *  everything below it is an action; after every action the narration
     *  option is selected again. */
    function scaleTopbarSync() {
        const subject = measureSubject();
        /* FOR GUESTS THERE IS NO MEASURE BAR (10.09.2026, asked
           what guests should be able to do with the Motivname: „nichts
           fragen, Fenster sperren"). Until then a guest saw Motivname,
           Maßstab and the measuring tools on his own voucher — and every
           save answered with 405: the guest API knows fetching, uploading
           and deleting, it dates from stage 6a and so from before the scale
           (0088) and the picture title (0097). A field that promises
           something no tap delivers is worse than none. A guest photo is a
           voucher; naming and measuring is done by the leadership, which may
           do it. Everything that hangs on photoTopbar.hidden below — the
           flap button, the „Messung" chooser, the tool choice — goes along
           with it. */
        photoTopbar.hidden = subject === null || host.guest;
        /* AND THE BAR'S BUTTON WITH IT. The flap is the topbar; a button that
           unfolds an empty one is a promise the window cannot keep. Looked up
           here instead of held in a constant — the bar's wiring lives a
           thousand lines further down, and a `const` there would be in its
           dead zone for this call. */
        document.getElementById('photo-phone-measure').hidden = photoTopbar.hidden;
        /* AND THE „MESSUNG" CHOOSER, which on the phone stands in the header
           and therefore no longer vanishes with the measure bar. Without this
           line it would stand above a cloud recording that is not measured. */
        document.getElementById('photo-stats-label').closest('.photo-topbar-scale')
            .hidden = photoTopbar.hidden;
        /* AND THE TOOL CHOICE. If the measure bar is gone, there is nothing
           to measure on this picture (measureSubject() is null) — a tool
           button still standing there then promises something no tap can
           deliver. The whole box, so that the menu goes along. */
        document.querySelector('.photo-phone-tools').hidden = photoTopbar.hidden;
        document.getElementById('photo-tool-rail').hidden = photoTopbar.hidden;
        if (photoTopbar.hidden) return;

        const scale  = subject.scale ?? null;
        const mayEdit = scaleMayEdit();

        /* THE SCALE STANDS FOR EVERYONE (06.10.2026). On a find
           shared with him to read: „Ich sehe nicht, welchen Maßstab Eric
           gewählt hat" — the chooser was hidden without the right to
           calibrate, and the flap stood empty. Now it says the picture's
           scale, and a reader may pick another to look with — „meinetwegen
           den könnte ich ändern, solange das alles bei mir nur temporär
           läuft": scaleStore() keeps it in this window then. */
        scaleSelect.closest('.photo-topbar-scale').hidden = false;
        /* The picture's NAME (migration 0097) — same gate as calibrating:
           whoever may calibrate may name. The field always shows the
           SUBJECT's stored word, so switching pictures can never leak a
           title across. Without the right it stands as plain text, and
           where there is none, not at all (the frame means writable). */
        const titleField = document.getElementById('photo-title');
        const titleText  = document.getElementById('photo-title-text');
        const title = (subject.title ?? '').trim();
        titleField.closest('.photo-topbar-scale').hidden = !mayEdit && title === '';
        (titleField.closest('.clear-shell') ?? titleField).hidden = !mayEdit;
        titleText.hidden = mayEdit;
        titleText.textContent = title;
        if (titleField.value !== (subject.title ?? '')) {
            titleField.value = subject.title ?? '';
        }
        document.getElementById('photo-readonly-note').hidden = mayEdit;
        {
            scaleSelect.textContent = '';
            // 'narration', and NOT a variable called state: the map's whole
            // state object lives under that name one scope up, and shadowing
            // it here would be the next reader's half hour.
            const narration = document.createElement('option');
            narration.value = '';
            narration.disabled = true;
            narration.selected = true;
            narration.textContent = scale === null ? t('ohne')
                : (scale.name ?? t('eigene Eichung'));
            scaleSelect.append(narration);

            // The presets — minted per objective through „merken", applied
            // with one pick. DOM-built: the names are the user's own words.
            /* ON A CLOUD PREVIEW THEY WAIT — UNLESS THE ORIGINAL'S WIDTH IS
               ON RECORD. A preset is µm per pixel OF THE CAMERA — original
               pixels — and stamping it against preview pixels would measure
               wrong by the shrink factor, silently. Since migration 0095 the
               recording may KNOW its original dimensions (measured at upload,
               or filed the first time „Volle Größe" ran): then the stamp
               binds to that width and the preview converts, like always.
               Only a recording that has never told stays locked until the
               original is loaded once. */
            const presetsWait = cloudInWindow !== null && !cloudOriginalLoaded
                && !(cloudInWindow.width > 0);
            // On a foreign picture its photographer's presets, under his name —
            // of the device the device field shows (presetDevices below).
            const presets = presetDevicesSync(subject, scale);
            const subjectOf = cloudInWindow ?? photoInWindow;
            // Under the photographer's name wherever the picture is not one's own — whether one may stamp it or only look.
            const group = !pictureMine() && presets.length > 0 ? document.createElement('optgroup') : null;
            if (group) {
                group.label = subjectOf?.photographer
                    ? t('Vorlagen von %s', personName(subjectOf.photographer)) : t('Vorlagen des Fotografen');
                scaleSelect.append(group);
            }
            for (const p of presets) {
                const option = document.createElement('option');
                option.value = 'preset:' + p.id;
                option.textContent = p.name
                    + (presetsWait ? ' — ' + t('erst das Original laden (oben links auf „Vorschaubild" klicken)') : '');
                option.disabled = presetsWait;
                (group ?? scaleSelect).append(option);
            }
            const free = document.createElement('option');
            free.value = 'free';
            free.textContent = t('Frei wählen …');
            scaleSelect.append(free);
            if (scale !== null) {
                const clear = document.createElement('option');
                clear.value = 'clear';
                clear.textContent = t('Maßstab entfernen');
                scaleSelect.append(clear);
            }

        }

        // Writing needs no scale — 'photo-annotate' stays out of this loop;
        // the box of a fixed size is its millimetres and needs one.
        for (const id of ['photo-measure-line', 'photo-measure-rect', 'photo-measure-path',
                          'photo-annotate-box']) {
            const b = document.getElementById(id);
            b.disabled = scale === null;
            /* Icon buttons since 29.08.2026: the title IS the label now —
               enabled it says the gesture (the aria-label's words), locked
               it says why. An empty title would leave a wordless button. */
            b.title = scale === null
                ? t('Dafür braucht das Bild zuerst einen Maßstab.')
                : b.getAttribute('aria-label');
        }
        // On a picture one may only look at, only one's own measures are
        // there to clear (the click handler above).
        document.getElementById('photo-measure-clear').hidden =
            (mayEdit || statsLabel === MEASURE_FREE ? layerMeasures()
                : layerMeasures().filter(m => m.temporary)).length === 0;
        // The desk's menus say what the fields now hold (set above without events).
        titlePickLabel();
        scalePickLabel();
        mediumPickLabel();
        developSubjectSync();
    }

    /* THE DEVICE FIELD (migration 0183). Which device it shows, in this
       order: the one picked in this window for THIS picture; the device
       of the picture's own scale (the stamp keeps it since 02.11.2026,
       and by its preset an older stamp of the same device is found too);
       on one's own picture the device last picked on this computer — the
       laptop that travels keeps the travel microscope; else the first.
       Picking a device changes nothing on the picture: only an objective
       stamps it. */
    const deviceSelect = document.getElementById('photo-device-select');
    let devicePicked = null;   // { subject, id } — a pick holds for its picture

    /** The devices among a preset list, in the order the presets come. */
    function presetDevices(presets) {
        const seen = new Map();
        for (const p of presets) {
            if (p.device_id > 0 && !seen.has(p.device_id)) seen.set(p.device_id, p.device ?? '');
        }
        return [...seen].map(([id, name]) => ({ id, name }))
            .sort((a, b) => a.name.localeCompare(b.name, 'de', { numeric: true }));
    }

    /**
     * Draw the device field and answer the presets of the device it shows
     * — all of them where there are fewer than two devices.
     */
    function presetDevicesSync(subject, scale) {
        const presets = presetList();
        const devices = presetDevices(presets);
        const field = document.getElementById('photo-device-field');
        field.hidden = devices.length < 2;
        if (devices.length < 2) return presets;
        const known = (id) => devices.some((d) => d.id === id);
        const byPreset = presets.find((p) => p.id === scale?.preset_id)?.device_id;
        const remembered = pictureMine() ? Number(settings.get('scaleDevice', 0)) : 0;
        const id = [
            devicePicked?.subject === subject ? devicePicked.id : 0,
            scale?.device_id, byPreset, remembered,
        ].find((d) => d > 0 && known(d)) ?? devices[0].id;
        deviceSelect.textContent = '';
        for (const d of devices) {
            const option = document.createElement('option');
            option.value = String(d.id);
            option.textContent = d.name;
            option.selected = d.id === id;
            deviceSelect.append(option);
        }
        return presets.filter((p) => p.device_id === id);
    }

    deviceSelect.addEventListener('change', () => {
        const id = Number(deviceSelect.value);
        devicePicked = { subject: measureSubject(), id };
        // Remembered for one's own pictures only — a photographer's device
        // ids are his and would mean nothing among one's own.
        if (pictureMine()) settings.set('scaleDevice', id);
        scaleTopbarSync();
    });

    /**
     * How a preset meets a picture of the given original size (the stamp in
     * the chooser's change handler says why): the reference width to stamp
     * and what was assumed — 'same', 'shrunk' (same shape, smaller or
     * larger), 'crop' (a few per cent off one side at most), or 'mixed'.
     * A preset without a calibration size has nothing to compare: its
     * µm per pixel are taken for this picture's pixels.
     */
    function presetFit(preset, w, h) {
        if (!preset.width || !(w > 0)) return { kind: 'same', width: w || undefined };
        const sw = w / preset.width;
        const sh = preset.height && h > 0 ? h / preset.height : sw;
        if (Math.abs(sw - 1) < 0.0005 && Math.abs(sh - 1) < 0.0005) return { kind: 'same', width: w };
        if (Math.abs(sw - sh) <= 0.005 * Math.max(sw, sh)) return { kind: 'shrunk', width: preset.width, factor: sw };
        const factor = Math.max(sw, sh);
        if (factor <= 1.0005 && factor >= 0.97) return { kind: 'crop', width: w };
        return { kind: 'mixed', width: Math.round(w / factor), factor };
    }

    /**
     * The presets the chooser offers — one's own on one's own picture, the
     * PHOTOGRAPHER'S on anybody else's (06.10.2026: „Sinn machen
     * würde es natürlich, Finns zu sehen. Weil er ja mit seinem Mikroskop
     * aufgenommen hat"). One's own objectives would be another camera's
     * pixels there. His are fetched once per photographer; until they come
     * the list is empty, and the chooser is drawn again when they are there.
     *
     * BY WHOSE PICTURE, NOT BY THE RIGHT TO MEASURE (27.10.2026, the maintainer
     * asked whether his „40x" could ever land on somebody else's 40x
     * picture): it could — a share that grants „deine Bilder bearbeiten"
     * made scaleMayEdit() true on a foreign picture, and the chooser then
     * offered one's OWN presets for it. Measuring there is right; with
     * one's own camera's numbers it is wrong.
     */
    function presetList() {
        if (pictureMine()) return host.start.photo_scales ?? [];
        const subject = cloudInWindow ?? photoInWindow;
        const photographer = Number(subject?.photographer_id ?? subject?.user_id ?? 0);
        const pointId = cloudInWindow !== null ? host.pointId() : photoInWindow?.point_id;
        if (!(photographer > 0) || !(pointId > 0)) return [];
        const known = foreignScales.get(photographer);
        if (Array.isArray(known)) return known;
        if (known === undefined) {
            foreignScales.set(photographer, 'loading');
            request(`scales.php?point=${pointId}&user=${photographer}`)
                .then((answer) => { foreignScales.set(photographer, answer.scales ?? []); })
                .catch(() => { foreignScales.set(photographer, []); })
                .finally(() => { if (photoWindow.open) scaleTopbarSync(); });
        }
        return [];
    }

    scaleSelect.addEventListener('change', async () => {
        const chosen = scaleSelect.value;
        scaleTopbarSync();   // back to the narration option either way

        // The same reasoning as in measureStart: the calibration is aimed at
        // the picture, so the flap that offered it steps aside.
        if (chosen === 'free') { photoFlapsClose(); calibrateStart(); return; }
        try {
            if (chosen === 'clear') {
                await scaleStore(null);
                return;
            }
            if (chosen.startsWith('preset:')) {
                const preset = presetList()
                    .find(p => 'preset:' + p.id === chosen);
                if (!preset) return;
                /* THE SAME CAMERA, A DIFFERENT SIZE (28.08.2026,
                   migration 0093; 27.10.2026). A preset is µm per pixel of
                   ONE camera at the width of its calibration image. A
                   picture of another width came one of three ways, and each
                   needs its own reference width (the stamp's `width`, which
                   measureUmPerPx divides by the shown width):

                   - SHRUNK, the same shape (a focus stack of MicroCam's
                     half-size live frames, 1296 × 972 against 2592 × 1944;
                     an export made smaller): every pixel covers more, so
                     the reference is the PRESET's width.
                   - CROPPED a little (a stacking program trims its edges):
                     the pixels are the camera's, the reference is the
                     picture's own width.
                   - both: the larger of the two side ratios is the
                     shrinking, the reference the width divided by it — and
                     because that is a guess, it is asked.

                   Until 27.10.2026 every other width was taken for a crop:
                   Finn's stacks of 26.10.2026, stamped „40x" and „100x" in
                   this window, measured at half their size. The original's
                   width as ever — for a cloud recording the one on record
                   (0095); a preview converts through it. */
                const imageWidth = (cloudInWindow !== null && cloudInWindow.width > 0)
                    ? cloudInWindow.width
                    : photoImageEl.naturalWidth;
                const imageHeight = (cloudInWindow !== null && cloudInWindow.height > 0)
                    ? cloudInWindow.height
                    : photoImageEl.naturalHeight;
                const fit = presetFit(preset, imageWidth, imageHeight);
                if (fit.kind === 'mixed' && !await confirm({
                    title: t('Bildgröße weicht ab'),
                    text:  t('Das Eichbild von „%s" ist %d × %d px groß, dieses Bild %d × %d px. Es ist kleiner und anders beschnitten. YASAIMAPP rechnet so, als wäre es auf %d %% verkleinert. Das stimmt nur, wenn es mit derselben Kamera aufgenommen ist.',
                        preset.name, preset.width, preset.height ?? 0, imageWidth, imageHeight, Math.round(fit.factor * 100)),
                    button: t('Trotzdem anwenden'),
                    danger: true,
                })) {
                    return;
                }
                /* MATERIALISED as a synthetic line of 1000 px: the picture's
                   scale keeps ONE stored shape whether it was calibrated here
                   or stamped from a preset — and it travels to viewers who do
                   not know the preset. The name rides along as provenance. */
                /* Which preset and device stamped it ride along unseen
                   (photos.php, photo_scale_write): a calibration found wrong
                   later can find every picture it measured. */
                await scaleStore({
                    line:   [[0, 0], [1000, 0]],
                    um:     1000 * preset.um_per_px,
                    corner: measureSubject()?.scale?.corner ?? 'br',
                    name:   preset.name,
                    width:  fit.width,
                    preset_id: preset.id,
                    device_id: preset.device_id ?? undefined,
                });
                if (fit.kind === 'shrunk') {
                    host.status(t('Das Bild ist auf %d %% des Eichbilds verkleinert. Der Maßstab ist entsprechend umgerechnet.',
                        Math.round(fit.factor * 100)));
                }
            }
        } catch (err) {
            host.status(err.message || t('Das ging nicht.'), true);
        }
    });

    /* --- The strip: every picture of this find ------------------------------ */

    /* Every thumbnail with the picture it stands for — the dot below needs
       to reach the same objects the statistics count from, and rebuilding
       the strip for a mark would reload every thumbnail. */
    const photoStripEntries = [];

    function fillPhotoStrip(f) {
        const strip = document.getElementById('photo-strip');
        strip.textContent = '';
        photoStripEntries.length = 0;
        if (f === null) return;
        /* BOTH WORLDS IN ONE STRIP (29.08.2026: the strip was
           missing exactly where he measures — on the cloud recordings): the
           voucher photos out of the point baggage and the recordings out of
           the gallery's last cloud answer. One find, one row of pictures,
           wherever the bytes live. */
        const pointId = photoInWindow !== null ? photoInWindow.point_id : host.pointId();
        const point = host.point(pointId);
        // Photos first, then the recordings; photoStripLayout() puts them
        // in the gallery's order.
        const pictures = [
            ...(point?.photos ?? []).map((g) => ({ g, cloud: false })),
            ...(cloudFilesShown.pointId === pointId ? cloudFilesShown.files : []).map((g) => ({ g, cloud: true })),
        ];
        for (const { g, cloud } of pictures) {
            const b = document.createElement('button');
            b.type = 'button';
            if (cloud ? g === cloudInWindow : (photoInWindow !== null && g.id === photoInWindow.id)) {
                b.classList.add('is-active');
            }
            const img = document.createElement('img');
            img.alt = '';
            img.loading = 'lazy';
            // As the picture is edited („Bearbeiten"), turned and cut included.
            developThumb(img, cloud
                ? `${host.start.baseUrl}pages/cloud_thumb.php?id=${g.id}&c=1`
                : `${host.start.baseUrl}pages/photo.php?id=${g.id}&k=1`, g.develop, (cloud ? 'cloud:' : 'photo:') + g.id);
            b.append(img);
            b.addEventListener('click', () => {
                // A strip slid out for this one choice has done its job.
                stripPeekEnd();
                if (cloud) { if (g !== cloudInWindow) showCloudPhoto(g); }
                else if (g !== photoInWindow) showPhoto(g);
            });
            photoStripEntries.push({ button: b, obj: g });
        }
        photoStripLayout();
        photoStripSync();
    }

    /**
     * The strip's buttons IN THE GALLERY'S ORDER, the subject names over
     * their groups (05.10.2026: „gruppiert, so wie in der
     * Punkte-Sidebar") — subjectGroups() is the gallery's own rule, so a
     * picture stands in the same place in both. Until 05.10.2026 the strip
     * went by sort_order alone, and a find with its names set stood in
     * another order here than in the point window.
     *
     * THE HEADINGS ARE ALWAYS IN THE DOCUMENT and the stylesheet shows them
     * only where the strip has room (a container query): widening it must
     * not reorder anything, only put the names over what is already there.
     * Headings only when anything is named — over a single unnamed group
     * „Ohne Motivnamen" would say nothing.
     *
     * THE SAME BUTTONS, MOVED: rebuilding them for a renamed picture would
     * load every thumbnail again; a re-appended <img> keeps its pixels.
     */
    function photoStripLayout() {
        const strip = document.getElementById('photo-strip');
        const groups = subjectGroups(
            photoStripEntries.map(e => ({ title: e.obj.title, order: e.obj.sort_order, e })),
            measureStyle.photo_titles);
        strip.textContent = '';
        const withHeadings = groups.some(g => g.name !== null);
        for (const { name, items } of groups) {
            if (withHeadings) {
                const head = document.createElement('div');
                head.className = 'photo-strip-head';
                head.textContent = name ?? t('Ohne Motivnamen');
                // A long name ends in an ellipsis — whole on hover.
                head.title = head.textContent;
                strip.append(head);
            }
            for (const { e } of items) strip.append(e.button);
        }
    }

    /* --- The strip folds, widens and slides out (desk only) -------------------
       05.10.2026, twice the same day. First: fold and widen like
       the statistics. Then the grip went to the OUTER edge („ansonsten
       würde das Handle ja immer mitwandern"), and the folded strip learned
       to slide out under the resting mouse — „diese Vorschaubilder braucht
       man ja nur, um zu einem anderen Motiv zu wechseln. Und nicht die
       ganze Zeit offen beziehungsweise kann man das dann so einstellen".

       So three gestures, three places:
         · a CLICK on the grip folds or pins — kept per device, open to
           begin with, as the strip has always stood there;
         · the mouse RESTING on the folded grip slides the strip out over
           the picture; leaving it, or choosing a picture, ends that —
           nothing is remembered, it was one look;
         · a DRAG at the strip's rule toward the picture sets the width,
           kept per device. The minimum is the stylesheet's (min-width) — a
           drag below it simply stops there, and what is saved is the width
           the strip really has.
       On the phone neither grip nor edge is drawn, and none of this can be
       reached. */
    const stripRail    = document.getElementById('photo-rail');
    const stripToggle  = document.getElementById('photo-strip-toggle');
    const stripResizer = document.getElementById('photo-strip-resize');

    // DECLARED BEFORE THE FOLD BELOW, which ends any slide at load — a let
    // in the temporal dead zone would take the whole module down.
    let stripPeekTimer = null;
    let stripLeaveTimer = null;
    function stripPeekEnd() {
        clearTimeout(stripPeekTimer);
        clearTimeout(stripLeaveTimer);
        stripRail.classList.remove('is-peeking');
    }

    function stripWidthApply(px) {
        stripRail.style.setProperty('--strip-width', Math.round(px) + 'px');
    }
    function stripFold(collapsed, remember) {
        stripPeekEnd();
        stripRail.classList.toggle('is-collapsed', collapsed);
        stripToggle.textContent = collapsed ? '›' : '‹';
        if (remember) settings.set('photoStripOpen', !collapsed);
    }
    {
        const savedWidth = Number(settings.get('photoStripWidth', 0));
        if (savedWidth > 0) stripWidthApply(savedWidth);
        if (settings.get('photoStripOpen', true) === false) stripFold(true, false);
    }
    // A click while the strip is slid out PINS it — it is folded, so the
    // fold turns it open, and the slide's end lets it stand in the flow.
    stripToggle.addEventListener('click', () => {
        stripFold(!stripRail.classList.contains('is-collapsed'), true);
    });

    /* THE SLIDE. A fifth of a second's rest before it comes, so a mouse
       crossing the edge on its way elsewhere does not set it flashing; a
       third of a second's grace after leaving, so a hand that slips off
       the strip for a moment does not lose it. A finger has no resting
       pointer — mouse only. */
    stripToggle.addEventListener('pointerenter', (e) => {
        if (e.pointerType !== 'mouse' || !stripRail.classList.contains('is-collapsed')) return;
        clearTimeout(stripLeaveTimer);
        clearTimeout(stripPeekTimer);
        stripPeekTimer = setTimeout(() => stripRail.classList.add('is-peeking'), 200);
    });
    // The slid-out strip is the rail's child: moving from the grip onto it
    // never leaves the rail.
    stripRail.addEventListener('pointerenter', () => clearTimeout(stripLeaveTimer));
    stripRail.addEventListener('pointerleave', () => {
        clearTimeout(stripPeekTimer);
        if (!stripRail.classList.contains('is-peeking')) return;
        stripLeaveTimer = setTimeout(stripPeekEnd, 300);
    });
    photoWindow.addEventListener('close', stripPeekEnd);

    let stripResize = null;   // {startX, startWidth}
    stripResizer.addEventListener('pointerdown', (e) => {
        e.preventDefault();   // no text selection riding along
        stripResize = {
            startX: e.clientX,
            startWidth: document.getElementById('photo-strip').offsetWidth,
        };
        stripRail.classList.add('is-resizing');
        try { stripResizer.setPointerCapture(e.pointerId); } catch { /* egal */ }
    });
    stripResizer.addEventListener('pointermove', (e) => {
        if (stripResize === null) return;
        // Wide enough for pictures one can judge, never so wide that the
        // picture being measured turns into the narrow part.
        stripWidthApply(Math.min(Math.max(stripResize.startWidth + e.clientX - stripResize.startX, 0),
            Math.min(400, window.innerWidth * 0.4)));
    });
    const stripResizeEnd = () => {
        if (stripResize === null) return;
        stripResize = null;
        stripRail.classList.remove('is-resizing');
        const width = document.getElementById('photo-strip').offsetWidth;
        if (width > 0) {
            settings.set('photoStripWidth', width);
            // Below the minimum the variable said less than the strip
            // shows; the next drag starts from what one sees.
            stripWidthApply(width);
        }
    };
    stripResizer.addEventListener('pointerup', stripResizeEnd);
    stripResizer.addEventListener('pointercancel', stripResizeEnd);

    /**
     * A RED DOT WHERE THIS LAYER ALREADY HOLDS SOMETHING
     * (29.08.2026: „wenn ich bei Sporen bin, sollen mir alle Thumbnails
     * einen roten Punkt bekommen, wo ich schon Sporen gemessen habe").
     *
     * A spore series runs across the pictures of one find, and without the
     * marks one opens every recording in turn just to find out where one
     * left off. It costs no request: the measures ride in the point baggage
     * and in the gallery's answer, the very lists sporeRows() counts from.
     *
     * Under „Frei" nothing is marked — free measuring saves nothing, so no
     * picture can carry it. And EVERY kind counts, not just the rectangles
     * the statistics use: the dot answers „is there something of mine in
     * here", and a text note or a run of the polyline is exactly that.
     */
    function photoStripSync() {
        const free = statsLabel === MEASURE_FREE;
        for (const { button, obj } of photoStripEntries) {
            const has = !free
                && (obj.measures ?? []).some(m => measureLabel(m) === statsLabel);
            button.classList.toggle('is-measured', has);
            if (has) {
                button.title = t('Hier ist schon unter „%s" gemessen.', statsLabel);
            } else {
                button.removeAttribute('title');
            }
        }
    }

    /* --- Naming the picture (migration 0097) ---------------------------------
       30.08.2026: „dass man die Bilder benennt, also Hauptmotiv,
       Habitat, Schnittbild … für die Mikrobilder Sporen, Cheilozystiden …"
       — groundwork for renaming the files into a fixed pattern later. The
       datalist carries the account's suggestion list (Einstellungen →
       Messen); saved like the measures: debounced, and the pending save
       remembers WHOSE title it owes, so a quick photo switch cannot write
       the words onto the wrong picture. */
    {
        const field = document.getElementById('photo-title');
        const list  = document.getElementById('photo-title-list');

        /* --- The suggestions, drawn ourselves -------------------------------
         *
         * 10.09.2026: „In der Mobilversion, wenn ich auf Motivname
         * klicke, kommt ja auch eine Vorschlagliste. Diese schließt aber
         * augenblicklich wieder."
         *
         * A <datalist> STOOD HERE, and its drop-down is browser equipment: it
         * lies outside the page, and what closes it is not decided by this
         * application. On the phone it vanishes at the moment the keyboard
         * shrinks the visible viewport — the same movement for which this
         * project keeps --view-height of its own (CLAUDE.md, trap 3). It
         * cannot be reproduced here: a datalist's drop-down comes in another
         * shape in desktop Chrome. Instead of tinkering on a guess, the field
         * gets the same list as the species names — one that we draw and
         * close.
         *
         * IT CLOSES ON A TAP BESIDE IT, not on blur. The reason is spelled
         * out at closeSpecies(): whoever reaches into the list loses the
         * caret in the field, and the list would be gone before the finger
         * arrived. That is also why the choice is made on pointerdown.
         */
        const words = () => (measureStyle.photo_titles ?? []);
        const closeList = () => {
            list.hidden = true;
            list.innerHTML = '';
            field.setAttribute('aria-expanded', 'false');
        };
        photoTitleListClose = closeList;
        const openList = () => {
            const typed = field.value.trim().toLowerCase();
            // Typing filters, empty shows everything: the field is mostly
            // empty, and then the whole list IS the answer.
            const hits = words().filter(w => typed === '' || w.toLowerCase().includes(typed));
            list.innerHTML = '';
            if (hits.length === 0) { closeList(); return; }
            for (const w of hits) {
                const row = document.createElement('li');
                row.textContent = w;
                row.addEventListener('pointerdown', (e) => {
                    e.preventDefault();          // the caret stays in the field
                    field.value = w;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    closeList();
                });
                list.append(row);
            }
            list.hidden = false;
            field.setAttribute('aria-expanded', 'true');
            place();
        };
        /* WHERE IT COMES TO LIE, in screen coordinates — it is fixed and
           so hangs on no scroll box (see the rule in map.css). Below the
           field when there is room for it there, otherwise above: on the
           phone the measure bar stands at the BOTTOM edge, and downwards
           there would be no screen left. Measured ONLY AFTER making it
           visible — a hidden list has no height. */
        const place = () => {
            const f = field.getBoundingClientRect();
            const gap = 4;
            list.style.left  = f.left + 'px';
            list.style.width = f.width + 'px';
            list.style.top = 'auto';
            list.style.bottom = 'auto';
            const height = list.getBoundingClientRect().height;
            if (f.bottom + gap + height <= window.innerHeight) list.style.top = (f.bottom + gap) + 'px';
            else list.style.bottom = (window.innerHeight - f.top + gap) + 'px';
        };
        /* ON THE TAP TOO AND NOT ONLY ON FOCUS, and that is not caution but
           measured: the field stands in a <dialog> that showModal() opens,
           and a modal dialog brings the focus back to its first field by
           itself. On the first tap there is therefore NO focus event AT ALL
           — in the experiment activeElement stayed on the Motivname field
           throughout, and not a single focus listener fired, not even one
           on the document. A field whose list listens only for the change
           of focus would thus stay mute exactly at the first touch. */
        field.addEventListener('focus', openList);
        field.addEventListener('click', openList);
        field.addEventListener('input', openList);
        field.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !list.hidden) { e.stopPropagation(); closeList(); } });
        document.addEventListener('pointerdown', (e) => {
            if (list.hidden) return;
            if (e.target === field || list.contains(e.target)) return;
            closeList();
        });
        // Closing the window clears it away, and so does a change of picture
        // (photoOpened) — the suggestions belong to the field, not to the window.
        photoWindow.addEventListener('close', closeList);
        let timer = null;
        const queue = () => {
            const subject = measureSubject();
            if (subject === null) return;
            const cleaned = field.value.trim();
            /* EVERY PICTURE HAS A NAME (15.11.2026: „es gibt
               Motivnamenzwang … jedes Bild braucht einen Motivnamen"). An
               emptied field is a name being retyped, not a name taken
               away: nothing is stored, and on leaving the field the name
               it had comes back (below). */
            if (cleaned === '') {
                clearTimeout(timer);
                return;
            }
            subject.title = cleaned.slice(0, 120);
            // A micro name brings the medium's button, a macro name takes it away.
            mediumPickLabel();
            const ref = { kind: cloudInWindow !== null ? 'cloud' : 'photo',
                obj: subject, pointId: statsPointId() };
            clearTimeout(timer);
            timer = setTimeout(() => {
                (ref.kind === 'cloud'
                    ? request(`cloud.php?id=${ref.pointId}&action=scale`, {
                        method: 'POST',
                        body: JSON.stringify({ file: ref.obj.id, title: ref.obj.title }),
                    })
                    : request('photos.php', {
                        method: 'POST',
                        body: JSON.stringify({ id: ref.obj.id, title: ref.obj.title,
                            _methode: 'PATCH' }),
                    })
                ).then(() => {
                    /* AND THE GALLERY CATCHES UP (12.09.2026: „wenn
                       ich den Motivnamen gesetzt habe, aktualisiert sich nicht
                       automatisch die Fotodarstellung im Fotos-Tab — ich muss
                       den Punkt neu aufrufen"). It groups by the name, but
                       only when it is drawn; the name had long been on the
                       picture and in the database, only the tiles still
                       stood in the old group. It is redrawn only after the
                       save has succeeded, and only for the find that is
                       still open — not on every keystroke, and never into a
                       window not its own. */
                    if (ref.pointId !== null && ref.pointId === host.pointId()) {
                        host.drawGallery(host.point(ref.pointId));
                    }
                    // The strip groups by the same name — while it still
                    // shows this picture's find.
                    if (photoStripEntries.some(e => e.obj === ref.obj)) photoStripLayout();
                }).catch(() => host.status(t('Der Bildtitel ließ sich nicht speichern.'), true));
            }, 800);
        };
        // 'input' catches typing; 'change' what a browser
        // fills in without an input event (an autofill — once the datalist pick).
        field.addEventListener('input', queue);
        field.addEventListener('change', queue);
        // Left empty: the picture's name stands there again — there is no „ohne".
        field.addEventListener('blur', () => {
            if (field.value.trim() !== '') return;
            const subject = measureSubject();
            if (!subject?.title) return;
            field.value = subject.title;
            titlePickLabel();
        });
    }

    /* --- Persisting the measures (migration 0092) --------------------------- */

    /* DEBOUNCED, WITH THE SUBJECT CAPTURED: a spore series is thirty saves in
       ten minutes, and each tap-burst of four should cost one request, not
       four. The pending save remembers WHOSE measures it owes — flushing
       after a photo switch must write the old picture's list, never the new
       one's. Fire and forget would be wrong here: a lost calibration was an
       inconvenience, a lost half hour of spore measuring is a loss worth a
       toast. */
    let measureSaveTimer   = null;
    let measureSavePending = null;   // {kind, obj, pointId}

    function measureSaveQueue() {
        if (!scaleMayEdit()) return;
        const ref = cloudInWindow !== null
            ? { kind: 'cloud', obj: cloudInWindow, pointId: host.pointId() }
            : (photoInWindow !== null
                ? { kind: 'photo', obj: photoInWindow, pointId: photoInWindow.point_id }
                : null);
        if (ref === null) return;
        measuresShare(ref);
        clearTimeout(measureSaveTimer);
        measureSavePending = ref;
        measureSaveTimer = setTimeout(measureSaveFlush, 800);
    }

    /* ONE LIST OF MEASURES PER PICTURE, whatever object holds it
       (15.11.2026). Should the host's list hold another object of the
       picture being measured — fetched anew while it stood in the window —,
       that one takes THIS list: the same array, not a copy. Opened from
       there later, the picture shows what was measured here, and its next
       save cannot write an older list over this one. */
    function measuresShare(ref) {
        const list = ref.kind === 'cloud' ? cloudFilesShown.files : (host.point(ref.pointId)?.photos ?? []);
        const twin = list.find((x) => x.id === ref.obj.id);
        if (twin && twin !== ref.obj) twin.measures = ref.obj.measures;
    }

    function measureSaveFlush() {
        clearTimeout(measureSaveTimer);
        const ref = measureSavePending;
        measureSavePending = null;
        if (ref !== null) measureSaveStore(ref);
    }

    /** The write itself — also called directly by the sidebar's pruning,
     *  which may target a picture that is NOT the one in the window. */
    function measureSaveStore(ref) {
        return (ref.kind === 'cloud'
            ? request(`cloud.php?id=${ref.pointId}&action=scale`, {
                method: 'POST',
                body: JSON.stringify({ file: ref.obj.id,
                    measures: ref.obj.measures ?? [] }),
            })
            : request('photos.php', {
                method: 'POST',
                body: JSON.stringify({ id: ref.obj.id,
                    measures: ref.obj.measures ?? [], _methode: 'PATCH' }),
            })
        ).catch(() => host.status(t('Die Messungen ließen sich nicht speichern.'), true));
    }

    /* --- The statistics sidebar (stage two, 28.08.2026) -----------
     *
     * „Das Statistikfenster … als Sidebar, auf der rechten Seite.
     * Einklappbar. … die kleinen Vorschaubilder der Sporen … Ohne Maße, nur
     * die Bilder. Darunter wird dann eben der Wert errechnet und darunter
     * ein Verteilungsfenster."
     *
     * It reckons ACROSS every picture of the open find — voucher photos out
     * of the point baggage, cloud recordings out of the gallery's last fetch
     * — because a spore print is measured over several micrographs. Each
     * picture converts through ITS OWN calibration; a picture without one
     * contributes nothing, and cannot, since measuring is locked without a
     * scale anyway. */

    const statsPane   = document.getElementById('photo-stats');
    const statsToggle = document.getElementById('photo-stats-toggle');

    /* The margin around the cut-outs, PER LAYER (28.08.2026:
       „Natürlich pro Ebene") — ornamented ascospores want 250 %, basidia do
       not. It lives in the ACCOUNT's measure_style (map_users, migration
       0091), so the phone draws like the desk; the slider edits the current
       layer's entry and saves debounced through its own small endpoint. */
    measureStyle.spore_air ??= {};

    /* PER FIND SINCE 07.10.2026 (migration 0156, „die bessere
       Lösung wäre individuell … geräteübergreifend"): an Ascobolus' spores
       at 250 % no longer set every other find's spores to 250 %. The open
       find's own margins come once per find and window life from
       api/v1/measure_style.php and lie OVER the account's per layer, which
       stay the start for every find without its own — so nothing set
       before moves. Until the migration is applied the server says so
       (ready false), and the slider writes to the account as it always
       did. */
    const findAir = new Map();   // point id → {label: percent}, or 'loading'
    let findAirReady = false;

    function findAirLoad() {
        const pid = statsPointId();
        if (pid == null || pid <= 0 || findAir.has(pid)) return;
        findAir.set(pid, 'loading');
        request(`measure_style.php?point=${pid}`)
            .then((answer) => {
                findAirReady = answer.ready === true;
                findAir.set(pid, answer.air ?? {});
                statsRefresh();
            })
            .catch(() => findAir.set(pid, {}));
    }

    /** The open find's margins, or null while they come (or never will). */
    function findAirOf(pid) {
        const own = pid == null ? null : findAir.get(pid);
        return own && own !== 'loading' ? own : null;
    }

    /** The style the cut-outs are drawn with: the account's, its margins
     *  overlaid by the open find's own. */
    function airStyle() {
        const own = findAirOf(statsPointId());
        return own ? { ...measureStyle, spore_air: { ...measureStyle.spore_air, ...own } } : measureStyle;
    }

    function sporeAirFor(label) {
        return sporeAir(airStyle(), label);
    }

    const airSlider = document.getElementById('photo-stats-air');
    let airSaveTimer = null;
    /* A PART'S TAB HAS ITS OWN AIR (07.10.2026) — starting at the same
       118 % as the spores (partAir), stored beside the layers under
       „Sporen · Keimporus". */
    const airKey = () => {
        const tab = statsTabs.get(statsLabel) ?? null;
        return tab === null ? statsLabel : partAirKey(statsLabel, tab);
    };
    airSlider.addEventListener('input', () => {
        if (statsLabel === MEASURE_FREE) return;
        const label = airKey();
        const value = Number(airSlider.value);
        // This find's own, where the server keeps finds' margins — else the account's.
        const pid = statsPointId();
        const own = findAirReady ? findAirOf(pid) : null;
        if (own) own[label] = value;
        else measureStyle.spore_air[label] = value;
        statsRefresh();
        clearTimeout(airSaveTimer);
        airSaveTimer = setTimeout(() => {
            const body = new FormData();
            body.append('label', label);
            body.append('spore_air', String(value));
            if (own) body.append('point_id', String(pid));
            // A margin that does not stick is an inconvenience, not a loss.
            request('measure_style.php', { method: 'POST', body }).catch(() => {});
        }, 600);
    });

    /* THE LENGTH ALONG THE BEND OR STRAIGHT, per layer and on the account
       like the margin above (07.10.2026 — the allantoid spore
       the books measure from end to end). The switch only reads: every
       cutout holds both lengths (frameValues), nothing is measured anew.
       Saved at once — a click is one decision, not a slider's stream. */
    measureStyle.straight_length ??= [];

    function lengthChoiceSync(rows) {
        const row = document.getElementById('photo-stats-length');
        row.hidden = !rows.some(r => r.bent);
        const straight = lengthStraight(measureStyle, statsLabel);
        for (const b of row.querySelectorAll('[data-length]')) {
            const on = (b.dataset.length === 'straight') === straight;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', String(on));
        }
    }
    for (const b of document.querySelectorAll('#photo-stats-length [data-length]')) {
        b.addEventListener('click', () => {
            if (statsLabel === MEASURE_FREE) return;
            const label = statsLabel;
            const straight = b.dataset.length === 'straight';
            const others = measureStyle.straight_length.filter(l => l !== label);
            measureStyle.straight_length = straight ? [...others, label] : others;
            statsRefresh();
            const body = new FormData();
            body.append('label', label);
            body.append('straight', straight ? '1' : '0');
            // A reading that does not stick is an inconvenience, not a loss.
            request('measure_style.php', { method: 'POST', body }).catch(() => {});
        });
    }

    /* THE STATISTICS BY MARK (07.10.2026): „Alle" counts every
       measure of the layer, a mark only its own kind, „ohne Merkmal" the
       rest — the same measure counts in „Alle" and in its mark, nobody has
       to choose. Shown where the layer's rows carry a mark; a mark that
       left the data takes its filter with it. */
    function markFilterSync(rows) {
        const row = document.getElementById('photo-stats-mark');
        const marks = [...new Set(rows.map(r => r.mark).filter(Boolean))];
        row.hidden = marks.length === 0;
        row.textContent = '';
        if (row.hidden) return;
        let current = markFilters.get(statsLabel) ?? null;
        if (current !== null && !rows.some(r => r.mark === current)) {
            current = null;
            markFilters.delete(statsLabel);
        }
        const caption = document.createElement('span');
        caption.textContent = t('Zeigen');
        row.append(caption);
        const unmarked = rows.filter(r => !r.mark).length;
        const choices = [[null, t('Alle %d', rows.length)],
            ...marks.map(mark => [mark, mark + ' ' + rows.filter(r => r.mark === mark).length]),
            ...(unmarked > 0 ? [['', t('ohne Merkmal %d', unmarked)]] : [])];
        for (const [value, words] of choices) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small';
            b.setAttribute('role', 'radio');
            b.textContent = words;
            b.classList.toggle('is-active', value === current);
            b.setAttribute('aria-checked', String(value === current));
            b.addEventListener('click', () => {
                if (value === null) markFilters.delete(statsLabel);
                else markFilters.set(statsLabel, value);
                statsRefresh();
            });
            row.append(b);
        }
    }

    /** The slider shows the CURRENT layer's margin — synced on every
     *  refresh, hidden on the free layer, which has no cut-outs to hem. */
    function airSliderSync() {
        const row = airSlider.closest('.photo-stats-air');
        // A cutout name's tab shows what was cut: no margin to set there —
        // its tiles' size instead.
        const inCrops = statsLabel !== MEASURE_FREE && cropOfTab(statsTabs.get(statsLabel)) !== null;
        row.hidden = statsLabel === MEASURE_FREE || inCrops;
        // The tiles' size stands wherever there are tiles — each kind remembers its own.
        const size = document.getElementById('photo-stats-size-row');
        size.hidden = statsLabel === MEASURE_FREE;
        if (!size.hidden) {
            document.getElementById('photo-stats-size').value = String(inCrops ? cropTileHeight() : sporeTileHeight());
        }
        if (!row.hidden) {
            const tab = statsTabs.get(statsLabel) ?? null;
            airSlider.value = String(Math.round((tab === null
                ? sporeAirFor(statsLabel) : partAir(airStyle(), statsLabel, tab)) * 100));
        }
    }
    /* WHAT is being measured (28.08.2026): the label every new
       measure is stamped with, and at the same time the statistics' filter —
       „dass man genau die Messungen und die kleinen Bildchen aus mehreren
       Bildern zusammentragen kann". The vocabulary is his dictation plus the
       structures every key asks about; the strings are USER CONTENT and
       therefore German, stored verbatim on the measure. „Frei" is the one
       non-label: measuring without saving, for the quick look at a
       dimension (own sentinel value — no real label may collide with it). */
    /* The capillitium since 30.09.2026 — its width is measured, and the
       picture names carry it too (PHOTO_TITLES_MICRO in photos.php says why
       they stand together). The three kinds of hyphae that came with it on
       that day were layers until 18.11.2026 and are marks now
       (MEASURE_COLUMNS, 'hyphae').

       NO „CHRYSOZYSTIDEN" LAYER: it stood here for a day (07.10.2026) and
       went again on the word, once the MARK came — a layer is the
       PLACE a structure sits, and a chrysocystidium sits at the edge or on
       the face. Measures someone put into it meanwhile keep their layer;
       statsLabelSync lists every label the find's data holds.

       RANDHAARE AND EXCIPULUM since 07.10.2026, with their picture names
       („mach mir für die Randhaare eine Messebene. Und für
       Excipulum … man misst da oftmals auch die Schichtdicken"): a rim
       hair is measured like a cystidium, length by width, bent ones with
       „Länge × Breite, gebogen"; the excipulum's layers by their
       thickness, as part measures (MEASURE_PARTS), and its cells, where a
       key asks for them, as rectangles.

       VELUM since 12.11.2026 („runde Velum-Zellen. Das gibt es
       öfters mal. Die würde ich gerne vermessen und eine Statistik dafür
       machen … da müsste noch Velum dazu"): its cells measured with the
       rectangle like any structure, length by width and Q — the picture
       names have carried „Velum" all along (photos.php).

       KONIDIEN since 18.11.2026, a LAYER OF ITS OWN and not a mark under
       „Sporen" (asked which, chose the layer: „dann machen wir
       einen eigenen Layer Konidien"). Some fungi show both in the same
       preparation, often on the same picture — but a mark only divides a
       layer, and „Sporen" would have summed the conidia into the spore size
       a key is read against, in the statistics, on the plate under „Alle"
       and in the find report's headline. The layer is chosen per measure,
       so both are measured on one picture all the same. The picture name
       „Konidien" stood in photos.php before. */
    /* IN COLUMNS SINCE 18.11.2026 („das Mess-Dropdown auch in
       Spalten aufteilen? In Makro, in Basidiomycota und in Ascomycota …
       Und die Spalten dann jeweils alphabetisch sortieren"), as the
       picture names stand in Makro and Mikro. Spores and conidia belong to
       both groups and are what one measures most: they stand above the
       columns, in that order. Makro is what is counted on the cap, not
       measured — the frame's and the sector's layers (boxCount,
       statsCountView). A layer the find's data holds that stands in no
       column comes under „Weitere". The phone keeps its <select>, the same
       groups as optgroups. */
    const MEASURE_SHARED = ['Sporen', 'Konidien'];
    const MEASURE_COLUMNS = [
        ['macro', () => t('Makro'), ['Lamellen', 'Poren']],
        ['basidio', () => t('Basidiomycota'), ['Basidien', 'Cheilozystiden', 'Pleurozystiden', 'Kaulozystiden',
            'Pileozystiden', 'Velum', 'Capillitium']],
        ['asco', () => t('Ascomycota'), ['Asci', 'Paraphysen', 'Randhaare', 'Excipulum']],
        /* THE HYPHAE BY THEIR PLACE, THEIR KIND AS THE MARK
           (18.11.2026, after sorting it out: „Hyphenbreite in der Lamelle,
           Hyphenbreite an der Hutdeckschicht, Hyphen am Stiel, dann Hyphen,
           die zum Myzel gehören … Bei Ramaria ist das ja irgendwie wichtig",
           and to the proposal „Passt so, Myzel eigenständig"). The cystidia's
           rule: a layer is the PLACE, the mark the KIND. Until then the
           layers were the kinds — „Generative Hyphen", „Skeletthyphen",
           „Bindehyphen" (30.09.2026) — and a hypha of the stem and one of
           the gill had no way to stand apart. Now each place its own
           statistics, and generative, skeletal or binding hyphae as marks
           under it (HYPHA_KINDS), as Chrysozystide under a cystidium's place.
           Unmarked is a hypha as agarics and ascomycetes have them. Both
           groups have hyphae, hence a column of their own. Clamps are SHOWN,
           not measured: the picture name „Schnallen". Measures already in
           the three old layers keep them and stand under „Weitere". */
        /* „HYPHEN HYMENIAL", NOT „HYPHEN TRAMA" (the same
           evening: „Hyphen Trama und Hyphen Kontext, das ist doch im Prinzip
           genau das Gleiche … zwischen Kontext, also dem Hutfleisch, und
           den hymenialen Hyphen, die direkt hinter den Basidien sitzen").
           Trama said gill tissue to the book and flesh to the ear; the new
           word says where. Measures stored under the old word in its few
           hours stand under „Weitere". The picture names carry the same
           words (photos.php, PHOTO_TITLES_MICRO). */
        ['hyphae', () => t('Hyphen'), ['Hyphen hymenial', 'Hyphen Hutdeckschicht', 'Hyphen Stiel', 'Hyphen Kontext', 'Myzel']],
    ];
    const MEASURE_STRUCTURES = [...MEASURE_SHARED, ...MEASURE_COLUMNS.flatMap(([, , names]) => names)];
    /* THE MAKRO COLUMN AND THE REST, BY THE PICTURE (20.11.2026:
       „beim Makrofoto nur die Makromessmethoden … außer vielleicht die
       Randhaare kann man noch in einem Makrofoto messen bei Ascomycota …
       Und Mikrofotos auch umgekehrt, dort brauche ich … Lamellen und Poren
       nicht"). The menu and the quick switches offer what fits the picture's
       scale (pictureScale, layerFits): a macro picture the „Makro" column,
       a micrograph everything else. The marginal hairs of a cup stand on
       both — under a lens they are a fringe one can measure on the macro
       picture as well as under the microscope. */
    const MEASURE_MACRO = MEASURE_COLUMNS.find(([key]) => key === 'macro')[2];
    const MEASURE_BOTH_SCALES = ['Randhaare'];
    /* THE MARKS each layer offers — what KIND of its structure a measure
       is, beside where it sits (07.10.2026; measureMark in
       measurestats.js says what a mark does). The cystidia's layers offer
       the kinds a key asks about; a word of one's own joins them with
       „+ eigenes" and is offered again wherever the find already holds it.
       User content like the layer names: German, stored verbatim. */
    const CYSTIDIA_KINDS = ['Chrysozystide', 'Gloeozystide', 'Lamprozystide'];
    // The hyphal system's three kinds, offered at every place of hyphae (MEASURE_COLUMNS, 'hyphae').
    const HYPHA_KINDS = ['Generative Hyphe', 'Skeletthyphe', 'Bindehyphe'];
    const MEASURE_MARKS = {
        Cheilozystiden: CYSTIDIA_KINDS, Pleurozystiden: CYSTIDIA_KINDS,
        Kaulozystiden: CYSTIDIA_KINDS, Pileozystiden: CYSTIDIA_KINDS,
        'Hyphen hymenial': HYPHA_KINDS, 'Hyphen Hutdeckschicht': HYPHA_KINDS, 'Hyphen Stiel': HYPHA_KINDS,
        'Hyphen Kontext': HYPHA_KINDS, Myzel: HYPHA_KINDS,
    };
    /* THE PARTS each layer offers its plain lines (07.10.2026) — what a
       line measures, counted under that name beneath the structure. Not
       the wall's thickness: „das ist nirgends ein relevantes Merkmal". */
    const CYSTIDIUM_PARTS = ['Kopfbreite', 'Halsbreite'];
    /* The excipulum's two layers, outer and inner (07.10.2026:
       „eine äußere Schicht, eine innere Schicht, bei Peziza gibt es das
       zum Beispiel, wo verschiedene Schichten unterschiedlich aufgebaut
       sind. Wo man dann die Dicken misst") — a line across each is its
       thickness. The keys' own words; a third layer is „Eigenes Teilmaß". */
    // Each layer here needs the line tool too (layerTools) — its parts are measured with it.
    const MEASURE_PARTS = {
        Sporen: ['Keimporus', 'Ornamenthöhe', 'Öltropfen'],
        Basidien: ['Sterigmenlänge'],
        Cheilozystiden: CYSTIDIUM_PARTS, Pleurozystiden: CYSTIDIUM_PARTS,
        Kaulozystiden: CYSTIDIUM_PARTS, Pileozystiden: CYSTIDIUM_PARTS,
        Excipulum: ['Ektalexcipulum', 'Medullarexcipulum'],
    };
    /* The mark the next measure gets, per layer — for the window's life,
       like the layer itself: a series of chrysocystidia comes out alike. */
    const markStamps  = new Map();
    /* THE STATISTICS' TAB per layer: null = „Alles", else a part's name
       (07.10.2026: „in der Statistik-Sidebar oben Tabs …
       nebendran die Tabs mit den Teilmaßen"). It decides more than the
       pane: the picture shows only that part's lines, and the line tool
       measures that part — ONE state for all three, so a line just drawn
       never vanishes into a tab one is not looking at (statsTabSet). */
    const statsTabs   = new Map();
    /* A CUTOUT NAME'S TAB (07.10.2026) lives in the same map as a part's,
       marked so the two cannot meet: a line opens on its line break, which
       no single-line name can hold. cropOfTab gives the name back, or null
       for a part's tab and „Alles". */
    const cropTab = name => '\ncrop:' + name;
    const cropOfTab = tab => (typeof tab === 'string' && tab.startsWith('\ncrop:') ? tab.slice(6) : null);
    // The statistics' choice per layer: null = all, '' = unmarked, a mark.
    const markFilters = new Map();
    // What the mark row over the picture stands for (markHintSync).
    let markPanelFor  = null;
    const MEASURE_FREE = '__free__';
    /* LAYERS FOR WRITING ALONE (30.10.2026). A text or an arrow
       sits on a layer as every measure does, and the plate ticks per
       picture which layers' writing it shows (plateannot.js) — but the
       layers were the structures', and an arrow at a twig in a habitat
       picture had to lie on „Sporen". „Beschriftung" is always offered,
       and „+ Neue Beschriftungsebene …" names another: „zwei Sachen an
       einem Bild zeigen … und diese unterschiedlich einblenden", or the
       same words placed anew for a second, closer cutout of the picture on
       the plate. A layer exists by what lies on it — a name chosen here
       and left empty is gone with the window (ownLabelLayers); one with
       writing on it is listed from the data, like every layer.
       A user's word like the structures: German, stored verbatim. */
    const LABEL_LAYER = 'Beschriftung';
    const LABEL_LAYER_NEW = '__new_label__';
    const NOTE_KINDS = ['text', 'arrow', 'box'];
    const ownLabelLayers = new Set();
    /* FREE IS THE DEFAULT (28.08.2026: „Nicht, dass man aus
       Versehen irgendwo rein speichert"): whoever just measures has said
       nothing yet, and nothing said stores nothing. The choice then HOLDS
       for the session — a spore series across several pictures must not
       fall back to Frei between two photos and silently stop saving. */
    let statsLabel = MEASURE_FREE;
    /* Whether the person picked the layer himself since the window opened —
       then a picture switch leaves it alone (layerAutoPick). */
    let layerChosen = false;
    const statsLabelSelect = document.getElementById('photo-stats-label');

    let statsAutoOpened = false;
    /* …AND ONCE PER FIND where the window never closes (15.11.2026). In the
       Fundbrowser the viewer stands in the page and is open for the page's
       whole life: „once per window" was once per visit there, so after the
       first series the pane stayed folded at every further find —
       „ging sie gar nicht auf, als ich gemessen habe", and after a reload it
       did. A picture of another find begins a new series. */
    let statsAutoFor = null;
    function statsAutoReset() {
        const pid = statsPointId();
        if (!photoWindow.open || pid !== statsAutoFor) statsAutoOpened = false;
        statsAutoFor = pid;
    }
    /* The loaded source images for the cut-outs, and a run counter that
       keeps a slow load from painting into a newer rendering. Cleared when
       the window closes — at 2048 px these are megabytes each. */
    const statsImages = new Map();
    let statsRun = 0;

    function statsPointId() {
        if (photoInWindow !== null) return photoInWindow.point_id;
        if (cloudInWindow !== null) {
            /* The find whose list holds the open recording — Organisieren
               opens the pictures of a find that need not be the one chosen
               on the map, and without a chosen one there was no find at all
               and nothing was counted. */
            if (cloudFilesShown.pointId > 0 && cloudFilesShown.files.some((f) => f.id === cloudInWindow.id)) {
                return cloudFilesShown.pointId;
            }
            return host.pointId();
        }
        return null;
    }

    /**
     * Every picture of the current find that can carry measures.
     *
     * THE OPEN PICTURE ALWAYS COUNTS, AND AS THE OBJECT THE WINDOW HOLDS
     * (15.11.2026, twice: „ich messe, aber die Statistik in der
     * Sidebar aktualisiert sich nicht" — after a reload every measure was
     * there). The measures go into the record the window opened; the lists
     * here are the host's and are fetched anew behind the window's back — a
     * gallery drawn again, a point taken in afresh after a save. Wherever a
     * list holds ANOTHER object of the same id, the open one stands in for
     * it; where the list does not hold it at all (an answer that came back
     * empty, another find's list), it is added. Voucher photos and
     * recordings alike.
     */
    function statsSources() {
        const pid = statsPointId();
        const out = [];
        if (pid != null) {
            const point = host.point(pid);
            for (const f of (point?.photos ?? [])) {
                out.push({ kind: 'photo', obj: photoInWindow !== null && f.id === photoInWindow.id ? photoInWindow : f });
            }
            if (cloudFilesShown.pointId === pid) {
                for (const f of cloudFilesShown.files) {
                    out.push({ kind: 'cloud', obj: cloudInWindow !== null && f.id === cloudInWindow.id ? cloudInWindow : f });
                }
            }
        }
        if (photoInWindow !== null && !out.some((s) => s.obj === photoInWindow)) out.push({ kind: 'photo', obj: photoInWindow });
        if (cloudInWindow !== null && !out.some((s) => s.obj === cloudInWindow)) out.push({ kind: 'cloud', obj: cloudInWindow });
        return out;
    }

    /**
     * THE GALLERY'S TILES MARKED where the picture holds something of the
     * layer the window stands in (18.11.2026: „Hatten wir da
     * eigentlich auch nicht mal eine Markierung drauf, wo überall schon
     * gemessen wurde bei den Bildern in dem Messlayer, in dem ich mich
     * gerade befinde?"). Anything of the layer counts — a rectangle, a
     * line, a frame, a sector, a word on a writing layer: what one asks is
     * „was I here already". „Frei" keeps nothing and marks nothing; a closed
     * window marks nothing either. The tiles carry their picture's key
     * (photogallery.js, tileFor); a gallery drawn anew asks again
     * (gallerydrawn), as does every refresh of the statistics.
     */
    function measuredMarks() {
        const keys = new Set();
        if (photoWindow.open && statsLabel !== MEASURE_FREE) {
            for (const s of statsSources()) {
                if ((s.obj.measures ?? []).some((m) => measureLabel(m) === statsLabel)) keys.add(`${s.kind}:${s.obj.id}`);
            }
        }
        for (const tile of document.querySelectorAll('[data-picture]')) tile.classList.toggle('is-measured', keys.has(tile.dataset.picture));
    }
    document.addEventListener('gallerydrawn', measuredMarks);
    photoWindow.addEventListener('close', measuredMarks);

    /**
     * WHERE ONE STOOD IN THE GALLERY, taken before a picture is deleted
     * (18.11.2026: „Wenn ich das lösche, weiß ich aber nicht mehr,
     * wo ich stand, da ich natürlich die Sporenbilder von oben nach unten
     * durcharbeite in der Sidebar"): the tile that follows the picture among
     * those in sight — the one before it where it was the last —, and how
     * far its list was scrolled. galleryResume goes there once the gallery
     * stands again.
     */
    function galleryPlace(key) {
        const tile = key ? document.querySelector(`[data-picture="${CSS.escape(key)}"]`) : null;
        if (!tile) return null;
        const list = tile.closest('.photo-gallery') ?? tile.parentElement;
        const shown = [...list.querySelectorAll('[data-picture]')].filter((t) => t.getClientRects().length > 0);
        const at = shown.indexOf(tile);
        const next = shown[at + 1] ?? shown[at - 1] ?? null;
        let scroller = null;
        for (let n = list; n && n !== document.body; n = n.parentElement) {
            const y = getComputedStyle(n).overflowY;
            if ((y === 'auto' || y === 'scroll') && n.scrollHeight > n.clientHeight) { scroller = n; break; }
        }
        return { next: next?.dataset.picture ?? null, scroller, top: scroller?.scrollTop ?? 0 };
    }

    /** Back to the place galleryPlace took: the list scrolled as it was, the following picture open. The cloud's tiles may come with a second drawing — waited for, a while. */
    function galleryResume(place) {
        if (!place) return;
        const go = () => {
            if (place.scroller?.isConnected) place.scroller.scrollTop = place.top;
            if (!place.next) return true;
            // The host may have opened it already (the Fundbrowser, told by host.pictureNext).
            if (photoWindow.open && pictureKey() === place.next) return true;
            const tile = document.querySelector(`[data-picture="${CSS.escape(place.next)}"]`);
            if (!tile) return false;
            // The tile's own click opens it, as a hand would: the picture's <img>, or the recording's link.
            tile.querySelector('img, a')?.click();
            return true;
        };
        if (go()) return;
        const on = () => { if (go()) document.removeEventListener('gallerydrawn', on); };
        document.addEventListener('gallerydrawn', on);
        setTimeout(() => document.removeEventListener('gallerydrawn', on), 15000);
    }

    /** All measured structures of the find under the CURRENT label —
     *  measureRows() in measurestats.js, which the plates count from too —
     *  their bent lengths read as the layer's switch says. */
    function sporeRowsAll() {
        return measureRows(statsSources(), statsLabel, lengthStraight(measureStyle, statsLabel));
    }
    /** …and the ones the mark filter keeps (markFilterSync). */
    function sporeRows() {
        return rowsMarked(sporeRowsAll(), markFilters.get(statsLabel) ?? null);
    }

    /** Rebuild the label chooser — every structure by its plain name (no
     *  counts in here, 28.08.2026: „Die haben nichts im Dropdown
     *  zu suchen" — the N stands in the statistics), „Frei" at the end.
     *  Labels someone typed into the data by other means keep their place
     *  too, which is why this rebuilds at all. */
    function statsLabelSync() {
        const names = new Set(MEASURE_STRUCTURES);
        const labels = new Set([LABEL_LAYER]);
        const measured = new Set();
        for (const s of statsSources()) {
            for (const m of (s.obj.measures ?? [])) {
                if (m.type === 'rect' || m.type === 'crop') names.add(measureLabel(m));
                if (NOTE_KINDS.includes(m.type)) labels.add(measureLabel(m));
                else measured.add(measureLabel(m));
            }
        }
        // A layer holding nothing but writing is a writing layer, wherever it was named.
        for (const name of [...labels]) if (names.has(name) || measured.has(name)) labels.delete(name);
        labels.add(LABEL_LAYER);
        for (const name of ownLabelLayers) labels.add(name);
        statsLabelSelect.textContent = '';
        // „Frei" FIRST — it is the preselection, and the first row of a
        // chooser should be the state one is actually in.
        const free = document.createElement('option');
        free.value = MEASURE_FREE;
        free.textContent = t('Frei — ohne Speichern');
        statsLabelSelect.append(free);
        const groupOf = (title, list, column = null, inline = false) => {
            const g = document.createElement('optgroup');
            g.label = title;
            // A column of the desk's menu (measurePick), or a band side by side — the phone reads either as a group like any.
            if (column) g.dataset.column = column;
            if (inline) g.dataset.inline = '1';
            for (const name of list) {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                g.append(option);
            }
            statsLabelSelect.append(g);
            return g;
        };
        // Spores and conidia above, then the columns, each by the alphabet, then what stands in none.
        const abc = (list) => [...list].sort((a, b) => a.localeCompare(b, 'de', { sensitivity: 'base', numeric: true }));
        groupOf(t('Messen'), MEASURE_SHARED, null, true);
        for (const [key, head, list] of MEASURE_COLUMNS) groupOf(head(), abc(list), key);
        /* NOT A WRITING LAYER: „Beschriftung" and one's own writing layers
           stand under „Beschriften" alone. A cutout set while writing carries
           the writing layer's name, which put it into the measures' names
           as well — once among the eighteen it went unseen, alone under
           „Weitere" it stood twice (18.11.2026: „Beschriftung hast
           du jetzt aber unter der letzten Spalte weitere und nochmal ganz
           unten"). */
        const further = [...names].filter((name) => !MEASURE_STRUCTURES.includes(name) && !labels.has(name));
        if (further.length > 0) groupOf(t('Weitere'), abc(further), 'further');
        const sorted = [...labels].sort((a, b) => (a === LABEL_LAYER ? -1 : b === LABEL_LAYER ? 1
            : a.localeCompare(b, 'de', { numeric: true })));
        const own = document.createElement('option');
        own.value = LABEL_LAYER_NEW;
        own.textContent = t('+ Neue Beschriftungsebene …');
        // Marked, so the quick switches know a writing layer from a measuring one (layerFits).
        const writing = groupOf(t('Beschriften'), sorted);
        writing.dataset.writing = '1';
        writing.append(own);
        statsLabelSelect.value = statsLabel;
        measureOptionsFit();
    }

    /**
     * Does the statistics pane have anything at all to show for this layer?
     *
     * WHAT FOR (09.09.2026: „Das Sidebar Handle braucht natürlich
     * nur angezeigt werden, wenn's auch einen Inhalt bietet. Also zum
     * Beispiel bei Menüpunkt frei brauch es gar nicht angezeigt werden"). A
     * handle that pulls open an empty drawer is a promise the drawer does
     * not keep — and on the phone it costs 28 px of the picture for good on
     * top of that.
     *
     * „FREI" NEVER HAS ANYTHING: there one measures without saving and
     * without counting. That is not an empty layer, it is doing without one.
     *
     * OTHERWISE WHAT THE DRAWER WOULD SHOW DECIDES, and that is why this
     * asks sporeRows() and not the raw measure list: a measure without
     * usable dimensions drops out there, and what drops out is no content.
     * A second, cheaper answer beside it would be the one that some day
     * says something other than the drawer itself.
     */
    function statsHasContent() {
        if (statsLabel === MEASURE_FREE) return false;
        // All of the layer — a mark filter narrows the drawer, never closes
        // it — and a layer of part measures alone (sterigmata) has content too.
        return sporeRowsAll().length > 0 || partRows(statsSources(), statsLabel).length > 0
            || cropNames(statsSources(), statsLabel).length > 0 || countRows().length > 0;
    }

    /** The frames of the layer on view, across the find's pictures — what „Lamellen" and „Poren" count in (statsCountView). */
    function countRows() {
        const rows = [];
        for (const s of statsSources()) {
            for (const m of (s.obj.measures ?? [])) {
                if ((m.type === 'box' || m.type === 'sector') && measureLabel(m) === statsLabel) rows.push({ ...s, m });
            }
        }
        return rows;
    }

    function statsRefresh() {
        /* THE CHOOSER AND THE SLIDER FIRST — they stand (on the phone) in
           the header and (everywhere) outside the question whether the
           drawer shows anything. Behind the hidden exit they would have
           stayed stuck as soon as a layer was empty once. */
        statsLabelSync();
        measuredMarks();
        // The open find's margins, once — they redraw the pane when they come.
        findAirLoad();
        airSliderSync();
        statsPane.hidden = !statsHasContent();
        if (statsPane.hidden) return;
        /* Folded, nothing is drawn — but laid over the picture under the
           pointer (is-peek, statsPeek) it is open to the eye and draws
           (09.11.2026: after a reload the folded pane came up
           EMPTY on hover, its content never having been drawn). */
        if (statsPane.classList.contains('is-collapsed') && !statsPane.classList.contains('is-peek')) return;
        if (statsLabel === MEASURE_FREE) {
            const box = document.getElementById('photo-stats-values');
            box.textContent = '';
            const p = document.createElement('p');
            p.className = 'hint-text small';
            p.textContent = t('Frei messen: Hier wird nichts gespeichert und nichts gezählt.');
            box.append(p);
            document.getElementById('photo-stats-thumbs').textContent = '';
            sporeSortSync(0);
            partTurnSync(null);
            lengthChoiceSync([]);
            markFilterSync([]);
            statsTabsSync([]);
            statsParts([]);
            statsPlot([]);
            return;
        }
        // A layer that counts in frames shows its frames (statsCountView), and nothing measured beside them.
        const counted = countRows();
        if (counted.length > 0 && sporeRowsAll().length === 0) {
            statsCountView(counted);
            return;
        }
        const all = sporeRowsAll();
        const parts = partRows(statsSources(), statsLabel);
        const tab = statsTabsSync(parts);
        if (cropOfTab(tab) !== null) {
            statsCropView(cropOfTab(tab));
            return;
        }
        if (tab !== null) {
            statsPartView(tab, parts.filter(r => r.part === tab));
            return;
        }
        partTurnSync(null);
        lengthChoiceSync(all);
        markFilterSync(all);
        const rows = rowsMarked(all, markFilters.get(statsLabel) ?? null);
        statsValues(rows, parts.length > 0);
        statsParts(parts);
        statsThumbs(rows);
        // The scatter needs both axes — a cutout with a single measure has
        // no second and stays a number in the text above.
        statsPlot(rows.filter(r => r.B != null));
    }

    /** Switch the layer — from the chooser or a quick switch. */
    function layerApply(name) {
        statsLabel = name;
        statsLabelSelect.value = name;
        layerChosen = true;
        // The overlay follows the layer switch; a selection on a measure
        // that just vanished from view has nothing left to hold — and the
        // cutout offer's rectangle belongs to the layer that was just left.
        frameAbort();
        measureSelected = null;
        removeAnchor = null;
        scaleTopbarSync();
        drawOverlay();
        statsRefresh();
    }
    statsLabelSelect.addEventListener('change', async () => {
        if (statsLabelSelect.value !== LABEL_LAYER_NEW) {
            layerApply(statsLabelSelect.value);
            return;
        }
        // The chooser goes back to where it stood while the name is asked.
        statsLabelSelect.value = statsLabel;
        const taken = new Set([...statsLabelSelect.options].map(o => o.value));
        let n = 2;
        while (taken.has(`${LABEL_LAYER} ${n}`)) n++;
        const name = (await prompt({
            title: t('Neue Beschriftungsebene'),
            text: t('Wie soll die Ebene heißen? Auf jeder Ebene liegen eigene Texte, Pfeile und Rahmen. In der Tafel wählst du später, welche Ebene ein Bild zeigt.'),
            value: `${LABEL_LAYER} ${n}`,
            button: t('Anlegen'),
        }))?.trim();
        if (!name || name === MEASURE_FREE || name === LABEL_LAYER_NEW) return;
        if (!taken.has(name)) ownLabelLayers.add(name);
        statsLabelSync();
        layerApply(name);
    });
    photoWindow.addEventListener('close', () => { layerChosen = false; ownLabelLayers.clear(); });

    /* THE DESK'S MENUS OF OUR OWN — „Motivname", „Maßstab", „Messung"
       (04.11.2026: „ein eigenes CSS-Dropdown, das sich
       automatisch beim Überfahren öffnet", then the other two alike). Each
       is a button that says what is chosen and a list under it, open while
       the pointer is over them; a short grace on leaving, so the way down
       from the button into the list does not close it. A click opens and
       closes it too, Escape and a click beside it close it. The fields they
       stand for stay the truth and go on as hidden wiring at the desk (the
       phone keeps them): a pick goes into the field and its event, so every
       place that reads or sets them goes on speaking to the field. */
    /* data-multi on a pick: its rows are ticks (the medium) — the list stays
       open while one ticks and is drawn anew after each, and draw marks the
       ticked rows itself. A list that draws nothing does not open (a
       picture one may not change, with nothing to show). */
    /* WIDER, NOT A SCROLLBAR (20.11.2026: „bei kleineren
       Bildschirmen zum Beispiel die Mikrospalte zweispaltig … dass man den
       Scrollbalken, den vertikalen, versucht zu vermeiden, dass es eher in
       die Breite wächst … zwischen den Spalten, wenn die umbrechen, eine
       kleine, dezente Trennlinie"). A menu ends at the window's lower edge
       (pickMount) and scrolled beyond it — on a laptop the micro names ran
       past the edge. Measured with the menu open: while it still scrolls,
       the tallest list of words (.photo-pick-rows — the Motivname's Makro
       and Mikro) takes one column more, up to four; its head stays one
       across them, the group's ground holds them together, a hairline
       stands between (photowindow.css). Where four do not suffice, it
       scrolls after all. Undone at each opening, so a taller window gets
       its single columns back. */
    function pickFold(menu) {
        const lists = [...menu.querySelectorAll('.photo-pick-rows')];
        for (const list of lists) list.style.columnCount = '';
        const count = (list) => Number(list.style.columnCount) || 1;
        while (menu.scrollHeight > menu.clientHeight + 1) {
            const tallest = lists.filter((list) => count(list) < 4).sort((a, b) => b.offsetHeight - a.offsetHeight)[0];
            if (!tallest) break;
            tallest.style.columnCount = String(count(tallest) + 1);
        }
    }
    function pickMount(pick, { draw, choose }) {
        const multi = pick.hasAttribute('data-multi');
        const button = pick.querySelector('.photo-pick-button');
        const menu = pick.querySelector('.photo-pick-menu');
        pick._draw = draw;
        let timer = null;
        const open = (yes) => {
            clearTimeout(timer);
            if (menu.hidden === !yes) return;
            if (yes) {
                menu.textContent = '';
                draw(menu);
                pickWidth(pick, menu);
                if (!menu.querySelector('[data-value]')) return;
                if (!multi) pickMarkActive(pick);
                // To the window's lower edge and no further — a long list scrolls inside.
                menu.style.maxHeight = Math.max(160, window.innerHeight - button.getBoundingClientRect().bottom - 16) + 'px';
            }
            menu.hidden = !yes;
            button.setAttribute('aria-expanded', String(yes));
            if (yes) pickFold(menu);
            if (yes) menu.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' });
        };
        pick.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') open(true); });
        pick.addEventListener('pointerleave', (e) => {
            if (e.pointerType !== 'mouse') return;
            clearTimeout(timer);
            timer = setTimeout(() => open(false), 250);
        });
        button.addEventListener('click', (e) => {
            e.preventDefault();   // inside a <label>: no click passed on to its field
            open(menu.hidden);
        });
        menu.addEventListener('click', (e) => {
            e.preventDefault();
            const row = e.target.closest('[data-value]');
            if (!row || row.disabled) return;
            if (!multi) {
                open(false);
                choose(row.dataset.value);
                return;
            }
            choose(row.dataset.value);
            const scroll = menu.scrollTop;
            menu.textContent = '';
            draw(menu);
            menu.scrollTop = scroll;
        });
        pick.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !menu.hidden) {
                e.stopPropagation();
                e.preventDefault();
                open(false);
                button.focus();
            }
        });
        document.addEventListener('pointerdown', (e) => {
            if (!menu.hidden && !pick.contains(e.target)) open(false);
        });
        photoWindow.addEventListener('close', () => open(false));
    }

    /**
     * EVERY MENU AS WIDE AS ITS LONGEST ENTRY (18.11.2026:
     * „Können die Dropdowns so breit sein wie ihr längster Text, damit ich
     * bei einem Wechsel nicht einen Versprung der danach stehenden Einträge
     * habe?"). The button took the width of what stood chosen, so a switch
     * from „Sporen" to „Cheilozystiden" pushed the quick switches, the
     * medium and everything after it sideways under the pointer. Now its
     * words take at least the width of the widest entry one can choose,
     * measured in the button's own font. What only leads somewhere is left
     * out — „Eigener Name …", „+ Neue Beschriftungsebene …", the red
     * „Maßstab entfernen", a row that waits for the original —, or one long
     * action would widen the bar for good. Taken when the button's words
     * change and whenever the list opens (`drawn`, the list just drawn);
     * a name typed longer than all of them still widens it, it never cuts.
     */
    function pickWidth(pick, drawn = null) {
        const button = pick.querySelector('.photo-pick-button');
        const words = button?.querySelector('.photo-pick-text');
        /* NOT THE MEDIUM (data-multi): its words are the ticked list, not one
           entry, and it stands last in the bar — nothing after it jumps, and
           its width was what pushed the quick switches into two rows at a
           narrower desk (measured at 1400 px with the gallery open). */
        if (pick.hasAttribute('data-multi')) {
            pick._wordsWidth = '';
            return;
        }
        if (!words || !pick._draw) return;
        let list = drawn;
        if (list === null) {
            list = document.createElement('div');
            try { pick._draw(list); } catch { return; }
        }
        const texts = [...list.querySelectorAll('[data-value]')]
            .filter((r) => !r.disabled && !r.classList.contains('is-danger') && !r.textContent.trim().endsWith('…'))
            .map((r) => r.textContent.trim());
        const style = getComputedStyle(words);
        // On the function, not a const beside it: pickSay may run before the lines around it (see there).
        const ruler = (pickWidth.ruler ??= document.createElement('canvas').getContext('2d'));
        ruler.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
        const widest = Math.max(0, ...texts.map((x) => ruler.measureText(x).width));
        pick._wordsWidth = widest > 0 ? Math.ceil(widest) + 'px' : '';
        words.style.minWidth = pick._wordsWidth;
    }

    /** A row of a menu (draw), or a group's head when value is null. */
    function pickRow(menu, value, text, { disabled = false, danger = false } = {}) {
        const el = document.createElement(value === null ? 'span' : 'button');
        el.textContent = text;
        if (value === null) {
            el.className = 'photo-pick-head';
        } else {
            el.type = 'button';
            el.className = 'photo-pick-row' + (danger ? ' is-danger' : '');
            el.setAttribute('role', 'option');
            el.dataset.value = value;
            el.disabled = disabled;
        }
        menu.append(el);
        return el;
    }

    /* The button's words, and the row of what stands chosen (data-chosen on
       the pick). Function declarations that look their elements up
       themselves: layerChipsSync and scaleTopbarSync, declared earlier,
       call them, possibly before the lines below have run. */
    function pickSay(id, text, chosen = null, lead = null) {
        const pick = document.getElementById(id);
        if (!pick) return;
        const button = pick.querySelector('.photo-pick-button');
        if (button.dataset.text !== text || pick._wordsWidth === undefined) {
            button.dataset.text = text;
            const words = document.createElement('span');
            words.className = 'photo-pick-text';
            words.textContent = text;
            // A small sign in front where the bar has no word for the menu (Motivname, Maßstab).
            button.replaceChildren(...(lead ? [icon(lead, { class: 'photo-pick-lead' })] : []), words,
                icon('fold-triangle', { class: 'photo-pick-arrow' }));
            // As wide as the widest entry (pickWidth) — measured anew, the list may have changed with the words.
            pickWidth(pick);
        }
        pick.dataset.chosen = chosen ?? '';
        pickMarkActive(pick);
    }
    function pickMarkActive(pick) {
        if (pick.hasAttribute('data-multi')) return;
        for (const row of pick.querySelectorAll('.photo-pick-menu [data-value]')) {
            const on = row.dataset.value === pick.dataset.chosen;
            row.classList.toggle('is-active', on);
            row.setAttribute('aria-selected', String(on));
        }
    }

    /* „MESSUNG": drawn from the select's options and groups — „+ Neue
       Beschriftungsebene …" included, which asks for the name in the
       select's change handler. */
    function measurePickLabel() {
        pickSay('photo-stats-pick', statsLabelSelect.selectedOptions[0]?.textContent ?? '', statsLabelSelect.value);
    }
    {
        const pick = document.getElementById('photo-stats-pick');
        if (pick) {
            pickMount(pick, {
                draw(menu) {
                    // The groups that are columns stand side by side (statsLabelSync, MEASURE_COLUMNS).
                    // Only what fits the picture (measureOptionsFit) — asked anew at each opening.
                    measureOptionsFit();
                    let columns = null;
                    for (const child of statsLabelSelect.children) {
                        if (child.hidden) continue;
                        /* „FREI" A BUTTON, AND SPORES AND CONIDIA TWO BESIDE IT
                           BELOW (20.11.2026: „den Bereich, der überall
                           gilt … mach mir den mal etwas größer … den übersieht man
                           leicht … nicht eine Überschrift, sondern zwei … Buttons
                           … rechts, links geteilt über die komplette Breite …
                           und frei auch als Button gestalten"). What one measures
                           most stood as two small words under a head and was read
                           past; now two halves of the menu's width with a button's
                           surface, „Frei" the same across the top. Before that
                           (18.11.2026) the two had already moved from a list to a
                           band side by side. The phone's <select> keeps them as
                           its group „Messen". */
                        if (child.tagName !== 'OPTGROUP') {
                            pickRow(menu, child.value, child.textContent).classList.add('photo-pick-tile');
                            continue;
                        }
                        let into = menu;
                        if (child.dataset.inline) {
                            const pair = document.createElement('span');
                            pair.className = 'photo-pick-pair';
                            menu.append(pair);
                            for (const o of child.children) {
                                if (!o.hidden) pickRow(pair, o.value, o.textContent).classList.add('photo-pick-tile');
                            }
                            continue;
                        }
                        if (child.dataset.column) {
                            if (columns === null) {
                                columns = document.createElement('span');
                                columns.className = 'photo-pick-columns';
                                menu.append(columns);
                            }
                            into = document.createElement('span');
                            into.className = 'photo-pick-column';
                            columns.append(into);
                        } else {
                            /* „BESCHRIFTEN" A TILE OF ITS OWN (20.11.2026:
                               „ohne Trennlinie, mit Hintergrundfarbe und Label sowie
                               Makro und Askomykota") — the columns' ground and head,
                               across the menu's width below them. */
                            into = document.createElement('span');
                            into.className = 'photo-pick-column photo-pick-block';
                            menu.append(into);
                        }
                        pickRow(into, null, child.label);
                        for (const o of child.children) if (!o.hidden) pickRow(into, o.value, o.textContent);
                    }
                },
                choose(value) {
                    if (value === statsLabelSelect.value) return;
                    statsLabelSelect.value = value;
                    statsLabelSelect.dispatchEvent(new Event('change', { bubbles: true }));
                },
            });
            new MutationObserver(measurePickLabel).observe(statsLabelSelect, { childList: true, subtree: true });
            statsLabelSelect.addEventListener('change', measurePickLabel);
        }
    }

    /* „MOTIVNAME": the stock names (photos.php, PHOTO_TITLES_MACRO / _MICRO)
       to pick, „Eigener Name …" for a word not among them (asked in a
       window — a field inside a list that closes when the pointer leaves
       would lose what was being typed) — no way to none: every picture
       has a name (15.11.2026). The field takes the pick with its input event, which saves
       it as typing does. */
    function titlePickLabel() {
        const field = document.getElementById('photo-title');
        const pick = document.getElementById('photo-title-pick');
        if (!field || !pick) return;
        // Without the right to name, the name stands as text (scaleTopbarSync) — and so does no menu.
        pick.hidden = (field.closest('.clear-shell') ?? field).hidden;
        pickSay('photo-title-pick', field.value.trim() || t('ohne'), field.value.trim(), 'label');
    }
    {
        const pick = document.getElementById('photo-title-pick');
        const field = document.getElementById('photo-title');
        const setTitle = (word) => {
            field.value = word;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            photoTitleListClose();
            titlePickLabel();
        };
        /* ONE'S OWN NAMES CAN GO AGAIN (15.11.2026: „Kann ja
           sein, dass man sich da verschrieben hat … dann wäre der sonst
           immer bei einem in der Liste"). Own is every name of the account's
           lists that the stock (photos.php, PHOTO_TITLES_MACRO/MICRO —
           handed out as *_known) does not hold; the stock names stay, as
           there is no other place left to edit the lists (the tab
           „Motivnamen" went on 14.11.2026). The BUCKET, not a cross: the
           name ceases to exist in the list (CLAUDE.md, three farewells).
           Pictures that carry it keep it. */
        const titleOwn = (w) => {
            const stock = [...(measureStyle.photo_titles_macro_known ?? []), ...(measureStyle.photo_titles_micro_known ?? [])];
            return stock.length > 0 && !stock.includes(w);
        };
        const titleBin = (row, word) => {
            const wrap = document.createElement('span');
            wrap.className = 'photo-pick-own';
            row.replaceWith(wrap);
            const bin = document.createElement('button');
            bin.type = 'button';
            bin.className = 'photo-pick-bin';
            bin.append(icon('delete'));
            bin.title = t('„%s" aus deinen Motivnamen löschen', word);
            bin.setAttribute('aria-label', bin.title);
            bin.addEventListener('click', async (e) => {
                // Not the row's click: the menu would take the name for the picture.
                e.preventDefault();
                e.stopPropagation();
                if (!await confirm({
                    title: t('Motivnamen löschen'),
                    text: t('„%s" steht dann nicht mehr in deinen Motivnamen. Bilder, die so heißen, behalten ihren Namen.', word),
                    button: t('Löschen'),
                    danger: true,
                })) return;
                try {
                    const answer = await request('measure_style.php', { method: 'POST', body: JSON.stringify({ photo_title: { name: word, remove: true } }) });
                    for (const key of ['photo_titles', 'photo_titles_macro', 'photo_titles_micro']) {
                        if (answer.measure_style?.[key]) measureStyle[key] = answer.measure_style[key];
                    }
                    host.status(t('„%s" ist aus deinen Motivnamen gelöscht.', word));
                } catch (error) {
                    host.status(error?.message || t('Der Name ließ sich nicht löschen.'), true);
                }
            });
            wrap.append(row, bin);
        };
        if (pick && field) {
            pickMount(pick, {
                draw(menu) {
                    /* TWO COLUMNS, MAKRO AND MIKRO, EACH ALPHABETICAL
                       (15.11.2026: „zweispaltig … mit Makromotivnamen und
                       Mikromotivnamen. Und die Reihenfolge dort bitte
                       alphabetisch"). Until then one column in the order of
                       the settings' lists — that order still sorts the
                       gallery's groups (subjectGroups); only this menu, where
                       one looks a name up, goes by the alphabet. An account
                       from before the two lists has the one, in one column. */
                    const abc = (words) => [...words].sort((a, b) => a.localeCompare(b, 'de', { sensitivity: 'base', numeric: true }));
                    const macro = measureStyle.photo_titles_macro;
                    const micro = measureStyle.photo_titles_micro;
                    const groups = macro || micro ? [[t('Makro'), macro ?? []], [t('Mikro'), micro ?? []]]
                        : [[null, measureStyle.photo_titles ?? []]];
                    const columns = document.createElement('span');
                    columns.className = 'photo-pick-columns';
                    for (const [head, words] of groups) {
                        if (!words.length) continue;
                        const column = document.createElement('span');
                        column.className = 'photo-pick-column';
                        if (head) pickRow(column, null, head);
                        // The words in a box of their own, which pickFold may break into columns below the head.
                        const rows = document.createElement('span');
                        rows.className = 'photo-pick-rows';
                        column.append(rows);
                        for (const w of abc(words)) {
                            const row = pickRow(rows, 'word:' + w, w);
                            if (titleOwn(w)) titleBin(row, w);
                        }
                        columns.append(column);
                    }
                    if (columns.childElementCount) menu.append(columns);
                    /* NO „NAMEN ENTFERNEN" (15.11.2026: „ein Bild
                       kann nicht ohne haben … jedes Bild braucht einen
                       Motivnamen"): the menu only ever swaps a name. */
                    pickRow(menu, null, '');
                    pickRow(menu, 'own', t('Eigener Name …'));
                    // The words' rows answer to the name as it stands.
                    pick.dataset.chosen = 'word:' + field.value.trim();
                },
                async choose(value) {
                    if (value.startsWith('word:')) return setTitle(value.slice(5));
                    const word = (await prompt({
                        title: t('Motivname'),
                        text: t('Was zeigt das Bild? Zum Beispiel „Hauptmotiv", „Sporen" oder „Schnittbild".'),
                        value: field.value.trim(),
                        button: t('Übernehmen'),
                    }))?.trim().slice(0, 120);
                    if (word === undefined || word === '') return;
                    /* A NAME OF ONE'S OWN NAMES ITS COLUMN
                       (15.11.2026: „wer einen eigenen Namen angibt, muss
                       entscheiden, ob Makro oder Mikro"). It joins that list
                       of the account (Einstellungen → Messen) and stands in
                       the menu from then on. Without the answer the name is
                       not taken — the decision is the point. A name one of
                       the lists already holds asks nothing. */
                    const listed = [...(measureStyle.photo_titles_macro ?? []), ...(measureStyle.photo_titles_micro ?? [])]
                        .some((w) => w.toLowerCase() === word.toLowerCase());
                    if (!listed && (measureStyle.photo_titles_macro || measureStyle.photo_titles_micro)) {
                        const kind = await choose({
                            title: t('Makro oder Mikro?'),
                            text: t('„%s" kommt in deine Motivnamen. In welche Spalte gehört er?', word),
                            options: [
                                { value: 'macro', label: t('Makro'), hint: t('Ein Foto vom Pilz oder von seinen Teilen, ohne Mikroskop.') },
                                { value: 'micro', label: t('Mikro'), hint: t('Ein Bild durch das Mikroskop.') },
                            ],
                        });
                        if (!kind) return;
                        try {
                            const answer = await request('measure_style.php', { method: 'POST', body: JSON.stringify({ photo_title: { name: word, kind } }) });
                            for (const key of ['photo_titles', 'photo_titles_macro', 'photo_titles_micro']) {
                                if (answer.measure_style?.[key]) measureStyle[key] = answer.measure_style[key];
                            }
                        } catch (error) {
                            // The picture keeps its name all the same; only the list did not learn it.
                            host.status(error?.message || t('Der Name ließ sich nicht in deine Motivnamen aufnehmen.'), true);
                        }
                    }
                    setTitle(word);
                },
            });
            field.addEventListener('input', titlePickLabel);
        }
    }

    /* „MASSSTAB", WITH THE DEVICE IN IT (04.11.2026: „mit Gerät
       und Maßstab, wo man beides wählen kann"): every device a head, its
       objectives under it — one pick sets the device field and the scale
       field together. With one device or none, no heads. A preset that has
       to wait for the original (presetsWait in scaleTopbarSync) is offered
       greyed with the same reason. „Frei wählen …" and „Maßstab entfernen"
       go through the scale field as ever. */
    function scalePickLabel() {
        const select = document.getElementById('photo-scale-select');
        if (!select || !document.getElementById('photo-scale-pick')) return;
        // Closed it says the scale alone, never the device (04.11.2026) — the device is the open list's heads.
        // Short — the ruler in front says what it is („langer Text ist in der Zeile nicht cool").
        const scaleNow = measureSubject()?.scale ?? null;
        pickSay('photo-scale-pick', select.options[0]?.textContent ?? '',
            scaleNow?.preset_id ? 'preset:' + scaleNow.preset_id : '', 'ruler');
    }
    {
        const pick = document.getElementById('photo-scale-pick');
        if (pick) {
            pickMount(pick, {
                draw(menu) {
                    const presets = presetList();
                    const devices = presetDevices(presets);
                    const waits = cloudInWindow !== null && !cloudOriginalLoaded && !(cloudInWindow.width > 0);
                    const subjectOf = cloudInWindow ?? photoInWindow;
                    if (!pictureMine() && presets.length > 0) {
                        pickRow(menu, null, subjectOf?.photographer
                            ? t('Vorlagen von %s', personName(subjectOf.photographer)) : t('Vorlagen des Fotografen'));
                    }
                    const rows = (list) => {
                        for (const p of list) {
                            pickRow(menu, 'preset:' + p.id, p.name
                                + (waits ? ' — ' + t('erst das Original laden (oben links auf „Vorschaubild" klicken)') : ''),
                                { disabled: waits });
                        }
                    };
                    if (devices.length < 2) {
                        rows(presets);
                    } else {
                        for (const d of devices) {
                            pickRow(menu, null, d.name || t('Gerät'));
                            rows(presets.filter((p) => p.device_id === d.id));
                        }
                    }
                    pickRow(menu, null, '');
                    for (const o of scaleSelect.options) {
                        if (o.value === 'free' || o.value === 'clear') {
                            pickRow(menu, o.value, o.textContent, { danger: o.value === 'clear' });
                        }
                    }
                },
                choose(value) {
                    if (value.startsWith('preset:')) {
                        const preset = presetList().find((p) => 'preset:' + p.id === value);
                        // Another device's objective: the device first, which draws its presets into the field.
                        if (preset?.device_id > 0 && !document.getElementById('photo-device-field').hidden
                            && Number(deviceSelect.value) !== preset.device_id) {
                            deviceSelect.value = String(preset.device_id);
                            deviceSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                    if (![...scaleSelect.options].some((o) => o.value === value)) return;
                    scaleSelect.value = value;
                    scaleSelect.dispatchEvent(new Event('change', { bubbles: true }));
                },
            });
            new MutationObserver(scalePickLabel).observe(scaleSelect, { childList: true, subtree: true });
        }
    }

    /* „MEDIUM" IN THE HEAD (06.11.2026, instead of the label on
       the picture he had first asked for): closed it says the picture's
       media by their short labels, open it ticks them — the common ones,
       then „Weitere", as the microscope's sidebar lists them (media.js).
       Each tick is stored at once at the picture (cloud.php action=scale,
       photos.php PATCH — the same `media` the microscope writes), and only
       where one may calibrate it (scaleMayEdit): elsewhere the button says
       the media and opens nothing. It stands where there is something to
       say or to do: a picture with media, or one that may be given some. */
    /* ONLY ON A MICROGRAPH (07.11.2026: „nur dort angezeigt, wo
       ich ein Mikrofoto-Label genommen habe — beim Makrofoto-Label brauche
       ich kein Medium"). Micro is what the account files under its micro
       names (photos.php, PHOTO_TITLES_MICRO). A picture without a name
       that already carries media — the microscope writes them — keeps the
       button, so what was set never goes out of sight. */
    function pictureIsMicro(subject) {
        const name = String(subject?.title ?? '').trim().toLowerCase();
        if (name === '') return (subject?.media ?? []).length > 0;
        // A retired micro word (photos.php, PHOTO_TITLES_RETIRED) still names a micrograph.
        return [...(measureStyle.photo_titles_micro ?? []), ...(measureStyle.photo_titles_micro_retired ?? [])]
            .some((w) => String(w).trim().toLowerCase() === name);
    }

    function mediumPickLabel() {
        const pick = document.getElementById('photo-medium-pick');
        if (!pick) return;
        const subject = measureSubject();
        const label = mediaLabel(subject?.media);
        const may = subject !== null && scaleMayEdit();
        pick.hidden = subject === null || (!label && !may) || !pictureIsMicro(subject);
        pick.classList.toggle('is-read', !may);
        pickSay('photo-medium-pick', label || t('Medium'), null, 'microscope');
        pick.querySelector('.photo-pick-button').classList.toggle('is-empty', !label);
    }
    {
        const pick = document.getElementById('photo-medium-pick');
        if (pick) {
            pickMount(pick, {
                draw(menu) {
                    const subject = measureSubject();
                    if (subject === null || !scaleMayEdit()) return;
                    const on = new Set(subject.media ?? []);
                    const row = (m) => {
                        const r = pickRow(menu, m.key, m.short);
                        r.title = m.name();
                        r.classList.toggle('is-active', on.has(m.key));
                        r.setAttribute('aria-selected', String(on.has(m.key)));
                    };
                    // Each group alphabetically by its label (media.js, mediaAlphabetical).
                    for (const m of MEDIA.filter((x) => x.common).sort(mediaAlphabetical)) row(m);
                    pickRow(menu, null, t('Weitere'));
                    for (const m of MEDIA.filter((x) => !x.common).sort(mediaAlphabetical)) row(m);
                },
                choose(key) {
                    const subject = measureSubject();
                    if (subject === null || !scaleMayEdit()) return;
                    const now = new Set(subject.media ?? []);
                    if (now.has(key)) now.delete(key); else now.add(key);
                    // In the list's order, as the microscope stores them.
                    const media = MEDIA.map((m) => m.key).filter((k) => now.has(k));
                    subject.media = media.length ? media : null;
                    mediumPickLabel();
                    (cloudInWindow !== null
                        ? request(`cloud.php?id=${host.pointId()}&action=scale`, {
                            method: 'POST', body: JSON.stringify({ file: subject.id, media: subject.media }),
                        })
                        : request('photos.php', {
                            method: 'POST', body: JSON.stringify({ id: subject.id, media: subject.media, _methode: 'PATCH' }),
                        })
                    ).catch(() => host.status(t('Das Medium ließ sich nicht speichern.'), true));
                },
            });
        }
    }

    /** The layers THIS picture holds measures or text in, in the chooser's
     *  order — names it does not list behind, alphabetically. With
     *  `withShown` the layer on view and the one the picture was arrived on
     *  join them even where it has nothing in them yet (the quick
     *  switches). */
    function pictureLayers(withShown = false, wholeFind = false) {
        const there = new Set(measures.map(m => measureLabel(m)));
        // Every picture of the find too — what the quick switches offer (layerChipsSync).
        if (wholeFind) for (const s of statsSources()) for (const m of (s.obj.measures ?? [])) there.add(measureLabel(m));
        if (withShown) there.add(statsLabel).add(layerArrived);
        there.delete(MEASURE_FREE);
        there.delete('');
        const listed = [...statsLabelSelect.options].map(o => o.value).filter(v => there.has(v));
        const rest = [...there].filter(v => !listed.includes(v)).sort((a, b) => a.localeCompare(b, 'de'));
        return [...listed, ...rest];
    }

    /**
     * A PICTURE WITH ONE LAYER OPENS ON IT (06.10.2026: „ob,
     * wenn man zu einem Bild wechselt, auf dem nur eine Messung liegt …
     * man dann gleich automatisch diesen auswählt"). Only while nothing
     * was chosen — the window opens on „Frei", and that is no choice; a
     * layer picked by hand stays through every picture switch, or a series
     * of spores across pictures would be thrown onto the Basidien of the
     * one picture that has nothing else. Only the layer is set here: the
     * new picture is drawn when it has loaded, as always.
     *
     * Whatever it lands on is remembered as the layer this picture was
     * ARRIVED ON: it keeps its quick switch until the next picture, or a
     * click from Sporen over to Basidien would take the Sporen switch away
     * with it on a picture that has no spores yet — the way back the
     * switches are there for.
     */
    function layerAutoPick() {
        if (!layerChosen && statsLabel === MEASURE_FREE) {
            const layers = pictureLayers();
            if (layers.length === 1) {
                statsLabel = layers[0];
                statsLabelSelect.value = layers[0];
            }
        }
        layerArrived = statsLabel;
    }

    /**
     * THE QUICK SWITCHES behind the chooser — one per layer THE FIND holds
     * on any of its pictures, the one shown marked; only at the desk (the
     * stylesheet). UNTIL 18.11.2026 ONLY THIS PICTURE'S („bei
     * Cheilozystiden sehe ich nicht die zwei Buttons. Wechsle ich aber zu
     * Sporen, sehe ich plötzlich diese zwei Buttons"): on a picture of
     * cheilocystids alone there was nothing to switch to, though the find
     * had its spores measured on the next picture — the switches are for
     * going between what was already measured, and the statistics count
     * the whole find as well. The rule below, born of one picture, holds
     * on for the find.
     * They stand WHENEVER THE PICTURE HOLDS A LAYER OTHER THAN THE ONE
     * SHOWN (06.10.2026: in Sporen, on a picture that only has
     * Basidien — „wird mir dann der Schnellspring-Button Basidien auch
     * angezeigt?"). Two layers on the picture was the first rule and left
     * exactly that case to the chooser. The layer on view stands in the
     * row even where this picture has nothing in it yet, so the row says
     * where one is, and so does the one the picture was arrived on, so it
     * leads back; „Frei" never does — it is the absence of a choice, not a
     * place. Redrawn only when the layers or the choice
     * changed — drawOverlay() calls this on every zoom step.
     */
    /**
     * MAKRO OR MIKRO, BY THE PICTURE'S NAME (18.11.2026: „ein
     * Sporenbild brauche ich nicht die Schnellauswahl Lamellen und bei einer
     * Hauptansicht brauche ich nicht die Schnellauswahl Sporen"). The
     * picture says it with its subject name — one of the account's micro
     * names or one of its macro names (photos.php); a name in neither list,
     * or none, says nothing, and every layer stays offered.
     */
    function pictureScale(subject) {
        const name = String(subject?.title ?? '').trim().toLowerCase();
        if (name === '') return null;
        const has = (list) => (list ?? []).some((w) => String(w).trim().toLowerCase() === name);
        if (has(measureStyle.photo_titles_micro) || has(measureStyle.photo_titles_micro_retired)) return 'micro';
        if (has(measureStyle.photo_titles_macro)) return 'macro';
        return null;
    }
    /** Does a layer belong on a picture of that scale? The menu's „Makro" column is macro, a writing layer and the marginal hairs fit both, every other layer is micro. */
    function layerFits(name, scale) {
        if (scale === null || MEASURE_BOTH_SCALES.includes(name)) return true;
        const group = [...statsLabelSelect.options].find((o) => o.value === name)?.closest('optgroup');
        if (group?.dataset.writing) return true;
        return MEASURE_MACRO.includes(name) === (scale === 'macro');
    }
    /* THE CHOOSER FOLLOWS THE PICTURE TOO (MEASURE_BOTH_SCALES says whose
       wish): what does not fit its scale is hidden — the options of the
       phone's <select> and, as the desk's menu is drawn from them, its rows.
       Hidden, not left out: layerFits and the quick switches ask the select
       for a writing layer, and a picture renamed from macro to micro needs
       them back without a rebuild. The layer on view stays whatever it is,
       or the chooser would say nothing. Called before each drawing of the
       menu and with the quick switches, which follow every change of name. */
    function measureOptionsFit() {
        const scale = pictureScale(measureSubject());
        for (const group of statsLabelSelect.querySelectorAll('optgroup')) {
            let any = false;
            for (const o of group.children) {
                o.hidden = o.value !== statsLabelSelect.value && !layerFits(o.value, scale);
                any ||= !o.hidden;
            }
            group.hidden = !any;
        }
    }

    /**
     * THE TOOLS FOLLOW THE LAYER (18.11.2026, layer by layer:
     * „wenn ich Lamellen messe, bekomme ich nur das Werkzeug Lamellen
     * zählen und Auswahl … Wenn ich das Capillitium messe, bekomme ich nur
     * die Strecke … Capillitium hat ja keine Septen", and „bei der Messung
     * Beschriftung bekomme ich die Werkzeuge Text, Pfeil und
     * Bildausschnitt"). The rail and the phone's menu offer only what the
     * layer is measured with — a cystidium has no business with
     * „Lamellen zählen", a gill count none with „Strecke". „Auswählen" is
     * always there. A writing layer takes the three tools that write; a
     * layer he named no tools for — what stands under „Weitere", „Frei" —
     * keeps every tool, as before.
     *
     * The table as a function, not a const beside it: layerChipsSync, which
     * asks it, may run before the lines around it (see pickSay).
     * Modes: line Strecke · path Gebogene Länge · rect Länge × Breite ·
     * cutout Länge × Breite, gebogen · cystid Zystide in Teilen · box Poren
     * zählen · sector Lamellen zählen · text · arrow · crop Bildausschnitt.
     */
    function layerTools(name) {
        const hyphae = ['line', 'cutout'];   // a hypha's breadth, and its length to the septum where it can be seen
        /* THE PARTS NEED THE LINE (18.11.2026: „bei Sporen haben
           wir ja noch die Linie … für Keimporus, Ornamenthöhe etc. … schau
           doch mal, was da irgendwie noch so zusammenhängen könnte"): every
           layer with part measures (MEASURE_PARTS) measures them with the
           plain line — the spores' germ pore, ornament and oil drop, the
           cystidia's head and neck width, the sterigmata, the excipulum's two
           layers. Whoever gives a layer parts there gives it 'line' here. */
        const table = {
            // Spores and conidia: straight and bent — the bent one for the allantoid spore (18.11.2026).
            Sporen: ['rect', 'cutout', 'line'],
            Konidien: ['rect', 'cutout'],
            Lamellen: ['sector'],
            Poren: ['box'],
            Basidien: ['cutout', 'line'],    // the straight line for the sterigmata
            Capillitium: ['line'],
            Cheilozystiden: ['rect', 'cutout', 'cystid', 'line'],
            Kaulozystiden: ['cutout', 'cystid', 'line'],
            Pileozystiden: ['cutout', 'line'],
            Pleurozystiden: ['cutout', 'line'],
            Velum: ['rect'],
            Asci: ['cutout'],
            Excipulum: ['line'],
            Paraphysen: ['cutout', 'cystid'],   // „Zystide in Teilen" with the method „Paraphyse": length, breadth, head
            Randhaare: ['cutout', 'line'],
            'Hyphen hymenial': hyphae, 'Hyphen Hutdeckschicht': hyphae, 'Hyphen Stiel': hyphae,
            'Hyphen Kontext': hyphae, Myzel: hyphae,
        };
        if (table[name]) return table[name];
        const group = [...statsLabelSelect.options].find((o) => o.value === name)?.closest('optgroup');
        if (group?.dataset.writing || name === LABEL_LAYER) return ['text', 'arrow', 'crop'];
        return null;
    }
    /** Show only the layer's tools (layerTools), and lay down an armed one it does not offer. */
    function layerToolsSync() {
        const allowed = layerTools(statsLabel);
        const key = allowed ? allowed.join(',') : '*';
        if (key === layerToolsSync.key) return;
        layerToolsSync.key = key;
        for (const b of document.querySelectorAll('#photo-phone-menu button[data-tool], #photo-tool-rail button[data-tool]')) {
            b.hidden = allowed !== null && b.dataset.tool !== '' && !allowed.includes(b.dataset.tool);
        }
        // A rail line between two groups of which one is wholly gone, and a menu head over nothing, go too.
        for (const holder of [document.getElementById('photo-tool-rail'), document.getElementById('photo-phone-menu')]) {
            if (!holder) continue;
            // Walked backwards: each divider learns whether a tool still stands between it and the next one.
            let toolAfter = false;
            for (const el of [...holder.children].reverse()) {
                if (el.matches('.photo-tool-rail-line, .photo-phone-menu-head')) {
                    el.hidden = !toolAfter;
                    toolAfter = false;
                } else if (el.matches('button[data-tool]') && !el.hidden) {
                    toolAfter = true;
                }
            }
        }
        if (allowed === null) return;
        const armed = frameRect !== null && (measureMode === 'line' || measureMode === 'path')
            ? frameRect.mode : (frameRect?.armed ? frameRect.mode : measureMode);
        if (armed && !allowed.includes(armed)) {
            frameAbort();
            pathFinish();
            measureEnd();
        }
    }

    /**
     * THE QUICK SWITCHES GO TO A ROW OF THEIR OWN ONLY WHEN THE BAR IS FULL
     * (18.11.2026, the stylesheet at .is-chips-below says it).
     * Asked plainly: taken back into the row, does the right side — Maßstab,
     * Medium, the zoom jumps — still stand on the first line? If not, the
     * switches go below. Run when the switches are drawn and when the bar
     * or a menu in it changes its size (the observer below); a state that
     * does not change touches nothing, so the observer is not set off again.
     */
    function chipsRowSync() {
        const bar = document.querySelector('.photo-phone-bar');
        const chips = document.getElementById('photo-layer-chips');
        const right = document.querySelector('.photo-phone-bar > .photo-window-zoom');
        const left = document.querySelector('.photo-phone-bar > .photo-mode');
        if (!bar || !chips || !right || !left) return;
        bar.classList.remove('is-chips-below');
        // No switches, or no row to measure (the phone): nothing goes below.
        if (chips.hidden || !right.getClientRects().length || !left.getClientRects().length) return;
        const wraps = () => right.getBoundingClientRect().top > left.getBoundingClientRect().bottom - 1;
        if (!wraps()) return;
        bar.classList.add('is-chips-below');
        // Full even without them (a narrow window): below they would only make a third row — they stay.
        if (wraps()) bar.classList.remove('is-chips-below');
    }
    {
        const bar = document.querySelector('.photo-phone-bar');
        if (bar && 'ResizeObserver' in window) {
            /* A TIMER AND NOT requestAnimationFrame: a tab in the background
               holds its frames back, and the flag below would have stayed up
               for good — every later change unheard (measured 18.11.2026). */
            let queued = false;
            const soon = () => {
                if (queued) return;
                queued = true;
                setTimeout(() => { queued = false; chipsRowSync(); }, 0);
            };
            const watch = new ResizeObserver(soon);
            // And the window's own resize — a size changed while the tab was hidden reaches the observer late or never.
            window.addEventListener('resize', soon);
            watch.observe(bar);
            for (const el of bar.querySelectorAll('.photo-pick-button, .photo-window-zoom')) watch.observe(el);
        }
    }

    function layerChipsSync() {
        // The desk's menu shows the layer too — set in places that fire no change.
        measurePickLabel();
        // And the tools follow it (layerToolsSync) — before the early return below, which only knows the switches.
        layerToolsSync();
        measureOptionsFit();
        const box = document.getElementById('photo-layer-chips');
        if (!box) return;
        const subject = measureSubject();
        const shown = subject !== null && !statsLabelSelect.closest('.photo-topbar-scale').hidden;
        // Only the layers of the picture's scale (pictureScale) — the one on view always.
        const scale = pictureScale(subject);
        const layers = shown ? pictureLayers(true, true).filter((name) => name === statsLabel || layerFits(name, scale)) : [];
        const key = layers.join('|') + '#' + statsLabel;
        if (key === layerChipsKey) return;
        layerChipsKey = key;
        box.textContent = '';
        box.hidden = !layers.some(name => name !== statsLabel);
        for (const name of layers) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small';
            b.textContent = name;
            b.classList.toggle('is-active', name === statsLabel);
            b.setAttribute('aria-pressed', String(name === statsLabel));
            b.addEventListener('click', () => layerApply(name));
            box.append(b);
        }
        // In the row or below it (chipsRowSync) — the switches may have grown or gone.
        chipsRowSync();
    }

    /* The numbers: the average front and centre („Maße rechnen
       wir nur den Durchschnitt"), and from a handful on the mycological
       range the literature writes — (smallest) 5 % – 95 % (largest), the
       Piximètre notation. Q is the length-to-breadth ratio every key asks
       about. */
    function statsValues(rows, hasParts = false) {
        const box = document.getElementById('photo-stats-values');
        box.textContent = '';
        // Part measures alone are a statistic of their own — no plea for rectangles then.
        if (rows.length === 0 && hasParts) return;
        if (rows.length === 0) {
            const p = document.createElement('p');
            p.className = 'hint-text small';
            p.textContent = t('Unter „%s" ist noch nichts gemessen. Miss mit „Länge × Breite". Gezählt werden alle Bilder dieses Fundes.', statsLabel);
            box.append(p);
            return;
        }
        /* The lines are valueLines() in measurestats.js — the standard
           notation first, Q and N, the plain average last; a plate's
           „Werte" prints the same three. */
        for (const { text, quiet } of valueLines(rows)) {
            const e = document.createElement('div');
            if (quiet) e.className = 'hint-text small';
            e.textContent = text;
            box.append(e);
        }
    }

    /** Switch the statistics' tab — „Alles" with '' or null, else a part.
     *  The picture, the pane and the line tool follow (statsTabs). `keep`
     *  holds the chosen measure: a line renamed into its new part. */
    function statsTabSet(part, keep = false) {
        if (statsLabel === MEASURE_FREE) return;
        if (part) statsTabs.set(statsLabel, part);
        else statsTabs.delete(statsLabel);
        if (!keep) {
            measureSelected = null;
            removeAnchor = null;
        }
        setMeasureFocus(null);
        markPanelFor = null;
        statsRefresh();
        drawOverlay();
    }

    /* THE TABS ON TOP OF THE PANE (07.10.2026: „mit einmal
       alles, wo wir die Anzeige so wie jetzt haben … nebendran die Tabs
       mit den Teilmaßen"). Only where the layer holds a part measure — or
       one is being measured; the layer's own parts first in their stock
       order, any other after. Returns the tab on view. */
    function statsTabsSync(parts) {
        const bar = document.getElementById('photo-stats-tabs');
        const tab = statsLabel === MEASURE_FREE ? null : (statsTabs.get(statsLabel) ?? null);
        const there = new Set(parts.map(r => r.part));
        if (tab !== null) there.add(tab);
        const stock = MEASURE_PARTS[statsLabel] ?? [];
        /* THE CUTOUT NAMES AFTER THE PARTS (07.10.2026: „Wo sehe
           ich diese Ausschnitte? Ich dachte, es gibt einen extra Tab") —
           each name of the layer its tab, with the cutout drawing in front
           so it reads as pictures and not as a measure. */
        if (cropOfTab(tab) !== null) there.delete(tab);
        const crops = cropNames(statsSources(), statsLabel);
        if (cropOfTab(tab) !== null && !crops.includes(cropOfTab(tab))) crops.push(cropOfTab(tab));
        const names = [...stock.filter(p => there.has(p)), ...[...there].filter(p => !stock.includes(p))];
        bar.textContent = '';
        bar.hidden = names.length === 0 && crops.length === 0;
        if (bar.hidden) return null;
        for (const [value, words] of [[null, t('Alles')], ...names.map(n => [n, n]),
            ...crops.map(n => [cropTab(n), n !== '' ? n : t('ohne Namen')])]) {
            const b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('role', 'tab');
            if (cropOfTab(value) !== null) b.innerHTML = iconHtml('crop');
            b.append(words);
            b.classList.toggle('is-active', value === tab);
            b.setAttribute('aria-selected', String(value === tab));
            b.addEventListener('click', () => { if (value !== tab) statsTabSet(value); });
            bar.append(b);
        }
        return tab;
    }

    /** A part's own tab: its numbers, its curve, its cut-outs — the
     *  structure's rows, marks, length switch and sum-up stand aside. */
    function statsPartView(part, rows) {
        document.getElementById('photo-stats-mark').hidden = true;
        document.getElementById('photo-stats-length').hidden = true;
        document.getElementById('photo-stats-parts').hidden = true;
        const box = document.getElementById('photo-stats-values');
        box.textContent = '';
        if (rows.length === 0) {
            const p = document.createElement('p');
            p.className = 'hint-text small';
            p.textContent = t('Noch kein „%s" gemessen. Wähle die Strecke und klicke auf Anfang und Ende.', part);
            box.append(p);
        }
        for (const { text, quiet } of partValueLines(rows)) {
            const e = document.createElement('div');
            if (quiet) e.className = 'hint-text small';
            e.textContent = text;
            box.append(e);
        }
        statsCurve(rows);
        statsPartThumbs(part, rows);
    }

    /* HOW TALL A CUTOUT'S TILE STANDS in the pane — the slider in its tab,
       remembered on this device; larger, one sees whether a row of ascus
       bases lies at one height before it goes onto a plate. */
    const cropTileHeight = () => Math.min(320, Math.max(64, Number(settings.get('photoCropTile', 96)) || 96));
    /* THE MEASURED CUT-OUTS TOO (15.11.2026: „für die kleinen
       Ausschnittsbilder noch einen Schieberegler, um sie etwas zu
       vergrößern") — the spores' tiles and a part's, 96 px as they always
       were until one draws them larger. Its own number beside the picture
       cutouts': a row of ascus bases wants to be large, thirty spores
       small. */
    const sporeTileHeight = () => Math.min(320, Math.max(64, Number(settings.get('photoSporeTile', 96)) || 96));
    document.getElementById('photo-stats-size').addEventListener('input', (e) => {
        const name = cropOfTab(statsTabs.get(statsLabel));
        if (name !== null) {
            settings.set('photoCropTile', Number(e.target.value));
            statsThumbs(cropRows(statsSources(), statsLabel, name), true);
            return;
        }
        settings.set('photoSporeTile', Number(e.target.value));
        statsRefresh();
    });

    /** A cutout name's tab: its cutouts as tiles, upright as the plate
     *  stands them, in the order cut; no numbers, no plot — they measure
     *  nothing. A tile leads to its picture, the × deletes the cutout,
     *  the foot mirrors it, as on a spore. */
    function statsCropView(name) {
        for (const id of ['photo-stats-mark', 'photo-stats-length', 'photo-stats-parts']) {
            document.getElementById(id).hidden = true;
        }
        partTurnSync(null);
        const plot = document.getElementById('photo-stats-plot');
        plot.toggleAttribute('hidden', true);
        document.getElementById('photo-stats-plot-title').hidden = true;
        statsSpread([]);
        const rows = cropRows(statsSources(), statsLabel, name);
        const box = document.getElementById('photo-stats-values');
        box.textContent = '';
        const p = document.createElement('p');
        p.className = 'hint-text small';
        p.textContent = rows.length === 0
            ? t('Noch kein Ausschnitt mit diesem Namen. Wähle „Bildausschnitt" und setze vier Punkte.')
            : t('Diese Ausschnitte stehen auf der Tafel als eine Reihe, genau so wie hier.');
        box.append(p);
        statsThumbs(rows, true);
    }

    /* THE COUNT IN THE STATISTICS (18.11.2026: „Das muss dann auch
       als Messungsstatistik mit aufgenommen werden. Bild und also den
       Ausschnitt und die gezählten Punkte … Da steht dann Rechteckgröße,
       quadratisch 1 mal 1 Zentimeter, Anzahl 15 oder Rechteck so und so
       groß, Anzahl so und so viel"). One line per frame of the layer,
       across the find's pictures, and under the lines each frame as a
       tile: the cutout standing upright, its points on it. A tile leads to
       its picture and chooses the frame there. No curve, no cloud — a
       count is one number per frame. */
    function statsCountView(rows) {
        for (const id of ['photo-stats-mark', 'photo-stats-length', 'photo-stats-parts', 'photo-stats-tabs']) {
            document.getElementById(id).hidden = true;
        }
        partTurnSync(null);
        sporeSortSync(0);
        // The air round a spore means nothing to a frame: its tile is the frame.
        const air = airSlider.closest('.photo-stats-air');
        if (air) air.hidden = true;
        const plot = document.getElementById('photo-stats-plot');
        plot.toggleAttribute('hidden', true);
        document.getElementById('photo-stats-plot-title').hidden = true;
        statsSpread([]);
        const box = document.getElementById('photo-stats-values');
        box.textContent = '';
        /* SEVERAL CAPS, ONE NUMBER, AND ONLY THAT (18.11.2026:
           „in der Statistik brauche ich nicht eine Einzelauflistung … nur
           Durchschnittswerte"): the sectors' means per kind and their ratio,
           as measurestats.js reckons them for the plate too (sectorSummary)
           — then a line per counting frame, and last, quietly, where a cap
           lies very askew. Each sector's tile below leads to it. */
        const { lines, askew } = sectorSummary(rows.filter((r) => r.m.type === 'sector').map((r) => r.m));
        for (const words of lines) {
            const line = document.createElement('div');
            line.textContent = words;
            box.append(line);
        }
        for (const r of rows) {
            if (r.m.type !== 'box') continue;
            const line = document.createElement('div');
            line.textContent = boxLine(r.m, (r.m.count ?? []).length);
            box.append(line);
        }
        if (askew > 0) {
            const line = document.createElement('div');
            line.className = 'photo-stats-note';
            line.textContent = askew === 1
                ? t('Ein Hut liegt sehr schräg, sein Wert ist ungenau.')
                : t('%d Hüte liegen sehr schräg, ihre Werte sind ungenau.', askew);
            box.append(line);
        }
        statsCountThumbs(rows);
    }

    /** The frames as tiles: each cutout upright at the tiles' height, its counted points on it. */
    function statsCountThumbs(rows) {
        const run = ++statsRun;
        const holderBox = document.getElementById('photo-stats-thumbs');
        holderBox.textContent = '';
        const H = sporeTileHeight();
        for (const r of rows) {
            const holder = document.createElement('span');
            holder.className = 'photo-stats-thumb';
            thumbMeasures.set(holder, r.m);
            holder.classList.toggle('is-open', r.m === thumbChoice());
            holderBox.append(holder);
            statsImage(r).then((img) => {
                if (run !== statsRun) return;
                if (r.m.type === 'sector') {
                    const canvas = sectorTile(r, img, H);
                    if (canvas) {
                        canvas.addEventListener('click', () => measureJump(r));
                        holder.append(canvas);
                    }
                    return;
                }
                const scale = measureUmPerPx(r.m, r.obj.scale ?? null);
                if (scale === null || !img.naturalWidth) return;
                /* THE PICTURE AS IT LIES (18.11.2026: „die
                   Detailausschnitte in der Statistik würde ich nicht drehen,
                   diese immer so, wie das Foto ausgerichtet ist … weil dann
                   passen Schatten und Lichter nicht mehr"). The cut is the
                   box about the frame in the picture's own directions; a
                   turned frame stands turned on it, as in the picture. */
                const k = r.m.width ? img.naturalWidth / r.m.width : 1;
                const umPx = scale / k;
                const [cx, cy] = [r.m.points[0][0] * k, r.m.points[0][1] * k];
                const hw = r.m.um[0] / umPx / 2, hh = r.m.um[1] / umPx / 2;
                const a = (r.m.angle ?? 0) * Math.PI / 180, cos = Math.cos(a), sin = Math.sin(a);
                const local = (u, v) => [cx + u * cos - v * sin, cy + u * sin + v * cos];
                const ex = Math.abs(hw * cos) + Math.abs(hh * sin), ey = Math.abs(hw * sin) + Math.abs(hh * cos);
                const air = 1.08;
                const room = Math.max(H, (holderBox.clientWidth || H * 3) - 4);
                const ratio = ex / ey;
                const W = Math.max(24, Math.min(room, Math.round(H * ratio)));
                const Ht = Math.max(24, Math.min(H, Math.round(W / ratio)));
                const s = Math.min(W / (2 * ex * air), Ht / (2 * ey * air));
                const canvas = document.createElement('canvas');
                const dpr = window.devicePixelRatio || 1;
                canvas.width = Math.round(W * dpr);
                canvas.height = Math.round(Ht * dpr);
                canvas.style.width = W + 'px';
                canvas.style.height = Ht + 'px';
                const ctx = canvas.getContext('2d');
                ctx.scale(dpr, dpr);
                ctx.translate(W / 2, Ht / 2);
                ctx.scale(s, s);
                ctx.translate(-cx, -cy);
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0);
                ctx.lineWidth = 1.5 / s;
                ctx.strokeStyle = measureStyle.line_color;
                ctx.beginPath();
                for (const [i, [u, v]] of [[-hw, -hh], [hw, -hh], [hw, hh], [-hw, hh]].entries()) {
                    const [x, y] = local(u, v);
                    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                }
                ctx.closePath();
                ctx.stroke();
                ctx.fillStyle = measureStyle.line_color;
                ctx.globalAlpha = .6;
                for (const [u, v] of (r.m.count ?? [])) {
                    const [x, y] = local(u / umPx, v / umPx);
                    ctx.beginPath();
                    ctx.arc(x, y, 3 / s, 0, Math.PI * 2);
                    ctx.fill();
                }
                ctx.globalAlpha = 1;
                canvas.addEventListener('click', () => measureJump(r));
                holder.append(canvas);
            }).catch(() => {});
        }
    }

    /** The one-dimensional distribution into the pane's plot (curveSvg). */
    function statsCurve(rows) {
        const plot  = document.getElementById('photo-stats-plot');
        const title = document.getElementById('photo-stats-plot-title');
        const show = rows.length >= 2;
        plot.toggleAttribute('hidden', !show);
        title.hidden = !show;
        // A part's curve has one axis — no cloud to count.
        statsSpread([]);
        if (!show) return;
        plot.setAttribute('viewBox', '0 0 220 110');
        plot.innerHTML = curveSvg(rows, { axis: 'var(--border)', quiet: 'var(--text-quiet)', accent: 'var(--accent)' });
        plot.querySelectorAll('circle.photo-stats-dot').forEach((d, i) => plotMeasures.set(d, rows[i].m));
        plotFocusSync();
    }

    /** A part's cut-outs: square tiles, the line lying in the middle, all
     *  at one µm scale sized by the longest — small to large. A tile leads
     *  to its picture like a spore's; the × prunes it. */
    function statsPartThumbs(part, rows) {
        const run = ++statsRun;
        const box = document.getElementById('photo-stats-thumbs');
        box.textContent = '';
        sporeSortSync(0);
        // „Alle drehen" stands over tiles, so only where there are some.
        partTurnSync(rows.length > 0 ? part : null);
        if (rows.length === 0) return;
        const air = partAir(airStyle(), statsLabel, part), S = sporeTileHeight();
        const probe = document.createElement('span');
        probe.className = 'photo-stats-thumb';
        box.append(probe);
        const tileMin = Math.ceil(parseFloat(getComputedStyle(probe).minWidth) || 0);
        probe.remove();
        const frame = { W: Math.max(S, tileMin), H: S, pxPerUm: (S / air) / Math.max(...rows.map(r => r.um)) };
        const direction = partDirection(measureStyle, statsLabel, part);
        for (const r of [...rows].sort((a, b) => a.um - b.um)) {
            const holder = document.createElement('span');
            holder.className = 'photo-stats-thumb';
            thumbMeasures.set(holder, r.m);
            holder.classList.toggle('is-open', r.m === thumbChoice());
            box.append(holder);
            statsImage(r).then(img => {
                if (run !== statsRun) return;
                const draw = () => {
                    const canvas = document.createElement('canvas');
                    const dpr = window.devicePixelRatio || 1;
                    canvas.width = Math.round(frame.W * dpr);
                    canvas.height = Math.round(frame.H * dpr);
                    canvas.style.width = frame.W + 'px';
                    canvas.style.height = frame.H + 'px';
                    const ctx = canvas.getContext('2d');
                    ctx.scale(dpr, dpr);
                    ctx.translate(frame.W / 2, frame.H / 2);
                    lineDraw(ctx, img, r, frame.pxPerUm, frame, direction);
                    canvas.addEventListener('click', () => measureJump(r));
                    return canvas;
                };
                let canvas = draw();
                holder.append(canvas);
                if (r.kind === 'cloud' ? r.obj.mine !== false : host.mayEditPhotos()) {
                    const prune = document.createElement('button');
                    prune.type = 'button';
                    prune.className = 'photo-stats-remove';
                    prune.textContent = '×';
                    prune.title = t('Diese Messung löschen');
                    prune.addEventListener('click', () => sporeRemove(r));
                    holder.append(prune);
                    /* ONE BUTTON, NOT TWO (07.10.2026: „da es ja
                       nur eine Strecke ist"): „Umdrehen" turns the tile half
                       round — a line clicked the other way about points the
                       part's way again, a pore's spore lies on the right
                       side, and nothing is shown back to front, as a
                       mirror would. Stored as 'hv', both mirrors, which IS
                       the half turn (lineDraw). */
                    const foot = document.createElement('span');
                    foot.className = 'photo-stats-thumb-foot';
                    const flip = document.createElement('button');
                    flip.type = 'button';
                    flip.className = 'photo-stats-flip';
                    flip.title = t('Umdrehen');
                    flip.setAttribute('aria-label', t('Umdrehen'));
                    /* THE MIRROR'S DRAWING, not the turning arrow
                       (07.10.2026) — the axis the line lies along: a line
                       lying across the tile swaps its ends left and right,
                       a standing one top and bottom. */
                    flip.innerHTML = iconHtml(direction === 'up' || direction === 'down' ? 'flip-v' : 'flip-h');
                    flip.addEventListener('click', () => {
                        if (r.m.mirror === 'hv') delete r.m.mirror;
                        else r.m.mirror = 'hv';
                        measureSaveStore({ kind: r.kind, obj: r.obj, pointId: statsPointId() });
                        const drawn = draw();
                        canvas.replaceWith(drawn);
                        canvas = drawn;
                    });
                    foot.append(flip);
                    holder.append(foot);
                }
            }).catch(() => { if (run === statsRun) holder.remove(); });
        }
    }

    /* „ALLE DREHEN" (07.10.2026: „einen Button, wo ich einfach
       die ganzen Kacheln mal um neunzig Grad immer stückchenweise drehen
       kann"): a quarter turn clockwise for every tile of the part at each
       press — the part's direction, on the account like its margin, so the
       Ornamenthöhe stands upright on every find. Over the tiles, where the
       order row stands for the spores. */
    const PART_TURNS = ['right', 'down', 'left', 'up'];
    function partTurnSync(part) {
        const row = document.getElementById('photo-stats-turn');
        row.hidden = part === null;
        row.dataset.part = part ?? '';
    }
    document.getElementById('photo-stats-turn-button').addEventListener('click', () => {
        const part = document.getElementById('photo-stats-turn').dataset.part;
        if (!part || statsLabel === MEASURE_FREE) return;
        const key = partAirKey(statsLabel, part);
        const now = partDirection(measureStyle, statsLabel, part);
        const next = PART_TURNS[(PART_TURNS.indexOf(now) + 1) % PART_TURNS.length];
        measureStyle.part_direction = { ...(measureStyle.part_direction ?? {}), [key]: next };
        statsRefresh();
        const body = new FormData();
        body.append('label', key);
        body.append('part_direction', next);
        // A turn that does not stick is an inconvenience, not a loss.
        request('measure_style.php', { method: 'POST', body }).catch(() => {});
    });

    /** From a tile to its measure: its picture, and the measure in the
     *  accent there (measureFocus) — the focus set AFTER the switch, which
     *  forgets any focus of the picture before. */
    function measureJump(r) {
        if (r.obj !== measureSubject()) {
            if (r.kind === 'cloud') showCloudPhoto(r.obj);
            else showPhoto(r.obj);
            // The new picture's load draws the overlay — drawn now, its
            // measures would stand over the old one.
            setMeasureFocus(r.m);
            return;
        }
        setMeasureFocus(r.m);
        drawOverlay();
    }

    /* THE PART MEASURES under the structure's numbers (07.10.2026) — one
       line per part, the range notation and the count, in the order first
       met (partLines in measurestats.js). A line without a name is not
       among them; it counts nowhere, as before.
       NO HEADING OVER THEM (the same day): the pane's headings
       are set in capitals, and capitals have no ß — „TEILMASSE" read as
       „Teilmasse". Each line names its part anyway; a gap sets them off
       from the structure's numbers, as the blank line does on a plate. */
    function statsParts(rows) {
        const box = document.getElementById('photo-stats-parts');
        box.textContent = '';
        box.hidden = rows.length === 0;
        if (box.hidden) return;
        for (const { part, text } of partLines(rows)) {
            const line = document.createElement('div');
            const name = document.createElement('b');
            name.textContent = part + ': ';
            line.append(name, text);
            box.append(line);
        }
    }

    /** The source image of one row, loaded once per picture and cached.
     *  ALWAYS THE PREVIEW for cloud recordings — plenty for a thumbnail, and
     *  the original stays the download the user asked for, never a side
     *  effect of a statistics pane. */
    function statsImage(r) {
        const key = r.kind + ':' + r.obj.id;
        if (!statsImages.has(key)) {
            statsImages.set(key, new Promise((resolve, reject) => {
                const img = new Image();
                img.addEventListener('load', () => resolve(img), { once: true });
                img.addEventListener('error', reject, { once: true });
                if (r.kind === 'cloud') {
                    img.src = `${host.start.baseUrl}pages/cloud_thumb.php?id=${r.obj.id}&c=1&large=1`;
                    return;
                }
                // A voucher photo through the desk's store, as the window opens it (originalcache.js).
                const address = `${host.start.baseUrl}pages/photo.php?id=${r.obj.id}`;
                originalBlob(r.obj, address, 'photo')
                    .then((blob) => { img.src = URL.createObjectURL(blob); })
                    .catch(() => { img.src = address; });
            }));
        }
        return statsImages.get(key);
    }

    /* The cut-outs, top of the pane: each spore rotated into its long axis
       and cropped to its rectangle plus air — no numbers on them. Holders go
       in synchronously so the ORDER never depends on which image loads
       first; the canvases arrive when their picture does. */
    /** The tiles' order — length, breadth or Q, the device's last choice,
     *  length to begin with (the markup says why, and why no „as
     *  measured"). */
    const SPORE_SORTS = ['L', 'B', 'Q'];
    function sporeSortKey() {
        const key = settings.get('sporeSort', 'L');
        return SPORE_SORTS.includes(key) ? key : 'L';
    }
    /** The order row: shown from two tiles on, the chosen one marked. */
    function sporeSortSync(count) {
        const row = document.getElementById('photo-stats-sort');
        row.hidden = count < 2;
        const key = sporeSortKey();
        for (const b of row.querySelectorAll('[data-sort]')) {
            const on = b.dataset.sort === key;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', String(on));
        }
    }
    for (const b of document.querySelectorAll('#photo-stats-sort [data-sort]')) {
        b.addEventListener('click', () => {
            settings.set('sporeSort', b.dataset.sort);
            statsRefresh();
        });
    }

    function statsThumbs(rows, cutouts = false) {
        const run = ++statsRun;
        const box = document.getElementById('photo-stats-thumbs');
        box.textContent = '';
        // Small to large by the chosen value — sporeOrder() in measurestats.js,
        // which a plate's row sorts by too. A copy: the plot takes the rows as they are.
        // Picture cutouts keep the order they were cut in, as on the plate.
        const ordered = cutouts ? rows : sporeOrder(rows, sporeSortKey());
        sporeSortSync(cutouts ? 0 : rows.length);
        /* ONE BOX FOR ALL, sized by the LARGEST spore (28.08.2026:
           „bei allen gleich … nach der größten Spore, sodass wir da keine
           Treppung drin haben") — and because the box is shared, so is the
           µm scale inside it: every cut-out is drawn at the same
           micrometres-per-pixel, and a small spore rightly LOOKS small
           beside a large one, whichever photo either came from. */
        const frame = rows.length === 0 ? null : (() => {
            /* The AIR is the user's slider (28.08.2026): an
               ascomycete's ornament reaches well past the measured
               rectangle, and a fixed margin cut it off — per layer, out of
               the account's style. */
            // A cutout shows what was cut, with a hair of air in the pane.
            const air = cutouts ? 1.06 : sporeAirFor(statsLabel), H = cutouts ? cropTileHeight() : sporeTileHeight();
            /* By the CROP dims, not the values: a cutout's rectangle is what
               the thumbnail shows, and its paraphysis may be twice as long
               as the frame is tall — the shared µm scale has to fit the
               frames, or every plain spore beside a cutout would shrink. */
            const pxPerUm = (H / air) / Math.max(...rows.map(r => r.cropL));
            /* NEVER NARROWER THAN THE TILE (04.10.2026: „in der
               Vorschau sieht es so aus, als ob die Kachel erst breiter wird,
               wenn ich sie aktiviere"). The tile has a minimum width for its
               buttons (3 rem, 4.2 rem without hover) — a slender spore's
               picture stood in its middle with empty strips beside it, and
               only the frame showed how wide the tile was. Drawn across the
               whole tile, the spore keeps its size and its place in the
               middle, and beside it one sees more of the picture around.
               The width is the stylesheet's, read off a probe: written here
               a second time, the two would part company. */
            const probe = document.createElement('span');
            probe.className = 'photo-stats-thumb';
            box.append(probe);
            const tileMin = Math.ceil(parseFloat(getComputedStyle(probe).minWidth) || 0);
            probe.remove();
            const W = Math.max(24, tileMin,
                Math.ceil(Math.max(...rows.map(r => r.cropB)) * pxPerUm * air));
            return { W, H, pxPerUm };
        })();
        for (const r of ordered) {
            const holder = document.createElement('span');
            holder.className = 'photo-stats-thumb';
            thumbMeasures.set(holder, r.m);
            holder.classList.toggle('is-open', r.m === thumbChoice());
            box.append(holder);
            statsImage(r).then(img => {
                if (run !== statsRun) return;
                let canvas = sporeCanvas(img, r, frame);
                // The cut-out leads to its picture — for checking one
                // suspicious spore among thirty — and stands out there in
                // the accent; its tile is framed and, without hover, shows
                // its tools (measureJump).
                const jump = () => measureJump(r);
                canvas.addEventListener('click', jump);
                holder.append(canvas);
                // A foreign recording's measures are the photographer's: look,
                // do not prune (14.09.2026 — the server refuses by photographer).
                if (r.kind === 'cloud' ? r.obj.mine !== false : host.mayEditPhotos()) {
                    const prune = document.createElement('button');
                    prune.type = 'button';
                    prune.className = 'photo-stats-remove';
                    prune.textContent = '×';
                    prune.title = t('Diese Messung löschen');
                    prune.addEventListener('click', () => sporeRemove(r));
                    holder.append(prune);
                    holder.append(tileFlips(r, () => {
                        const drawn = sporeCanvas(img, r, frame);
                        drawn.addEventListener('click', jump);
                        canvas.replaceWith(drawn);
                        canvas = drawn;
                    }));
                }
            }).catch(() => { if (run === statsRun) holder.remove(); });
        }
    }

    /**
     * MIRRORING, in the tile's foot (04.10.2026: „ein
     * horizontales und vertikales Spiegeln … in der Kachel eher in der
     * Fußzeile"). Whether one tapped apiculus or apex first decides how a
     * spore stands here; two buttons set every tile alike afterwards —
     * stored on the measure, so it holds. The words say what happens, not
     * which axis: „horizontal spiegeln" is read both ways. A PART MEASURE'S
     * TILE has the same two since 07.10.2026 („genau den gleichen Komfort
     * wie bei der normalen Messung"); `redraw` puts the tile's new picture
     * in place.
     */
    function tileFlips(r, redraw) {
        const foot = document.createElement('span');
        foot.className = 'photo-stats-thumb-foot';
        for (const [axis, iconName, label] of [
            ['h', 'flip-h', t('Links und rechts tauschen')],
            ['v', 'flip-v', t('Oben und unten tauschen')],
        ]) {
            const flip = document.createElement('button');
            flip.type = 'button';
            flip.className = 'photo-stats-flip';
            flip.title = label;
            flip.setAttribute('aria-label', label);
            flip.innerHTML = iconHtml(iconName);
            /* The state for a screen reader only — on screen the picture
               shows it, and the button stays alike. */
            const sync = () => {
                flip.setAttribute('aria-pressed', String(String(r.m.mirror ?? '').includes(axis)));
            };
            sync();
            flip.addEventListener('click', () => {
                const now = String(r.m.mirror ?? '');
                const next = (now.includes('h') !== (axis === 'h') ? 'h' : '')
                           + (now.includes('v') !== (axis === 'v') ? 'v' : '');
                if (next === '') delete r.m.mirror; else r.m.mirror = next;
                measureSaveStore({ kind: r.kind, obj: r.obj, pointId: statsPointId() });
                redraw();
                sync();
            });
            foot.append(flip);
        }
        return foot;
    }

    /** One spore as a rotated canvas crop — UPRIGHT, the long axis vertical
     *  (28.08.2026: „hochkant, die lange Seite vertikal"), in the
     *  SHARED frame statsThumbs reckoned: every box the same size, every
     *  spore at the same µm scale. The zoom therefore comes from this
     *  spore's real length, not from its box. */
    function sporeCanvas(img, spore, frame) {
        const canvas = document.createElement('canvas');
        const dpr = window.devicePixelRatio || 1;
        canvas.width  = Math.round(frame.W * dpr);
        canvas.height = Math.round(frame.H * dpr);
        canvas.style.width  = frame.W + 'px';
        canvas.style.height = frame.H + 'px';
        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        ctx.translate(frame.W / 2, frame.H / 2);
        sporeDraw(ctx, img, spore, frame.pxPerUm, frame);
        return canvas;
    }

    /** Prune one spore — the reason the × exists: a mis-clicked measure
     *  would sit in the average for ever. Saved straight away, no debounce:
     *  pruning is deliberate, not part of a tap burst. */
    function sporeRemove(r) {
        const list = r.obj.measures ?? [];
        const at = list.indexOf(r.m);
        if (at >= 0) list.splice(at, 1);
        measureSaveStore({ kind: r.kind, obj: r.obj, pointId: statsPointId() });
        if (r.obj === measureSubject()) {
            drawOverlay();
            scaleTopbarSync();
        }
        statsRefresh();
    }

    /* The distribution: length against breadth, every spore a dot, the mean
       as a dashed cross — the picture Piximètre made the habit of, because a
       cloud shows two species where an average hides them. Drawn into the
       page's own SVG, so the colours come from the theme. */
    function statsPlot(rows) {
        const plot  = document.getElementById('photo-stats-plot');
        const title = document.getElementById('photo-stats-plot-title');
        const show = rows.length >= 2;
        /* toggleAttribute AND NOT .hidden — the plot is an SVG element, and the
           hidden IDL property lives on HTMLElement only: on an SVG element
           `plot.hidden = false` writes a meaningless expando while the
           markup attribute keeps hiding it. Measured 28.08.2026: two spores,
           a full plot in the DOM, and 0 × 0 pixels of it on screen. */
        plot.toggleAttribute('hidden', !show);
        title.hidden = !show;
        statsSpread(show ? rows : []);
        if (!show) return;
        // The picture is plotSvg() in measurestats.js; here in the theme's colours
        // — in its own box again, after a part's flatter curve (statsCurve).
        plot.setAttribute('viewBox', '0 0 220 150');
        const inner = plotSvg(rows, { axis: 'var(--border)', quiet: 'var(--text-quiet)', accent: 'var(--accent)' });
        plot.innerHTML = inner;
        // The dots in the order of the rows — that is how they were written.
        plot.querySelectorAll('circle.photo-stats-dot')
            .forEach((d, i) => plotMeasures.set(d, rows[i].m));
        plotFocusSync();
    }

    /** Count, spread and outliers under the cloud (spreadText); gone without one. */
    function statsSpread(rows) {
        const line = document.getElementById('photo-stats-spread');
        if (!line) return;
        line.textContent = spreadText(rows);
        line.hidden = line.textContent === '';
    }

    /* WIDTH AND FOLD STATE ARE THE DEVICE'S (28.08.2026: „deren
       Größe ziehen … pro Gerät, also im Local Storage. Und auch der Status
       … generell nicht pro Fund"): both live in the settings bundle and are
       applied at load — the pane stands as one left it, on this machine,
       whatever find is open. The toggle strip serves both gestures: a CLICK
       folds, a DRAG resizes; four pixels of movement draw the line, and the
       click that trails a finished drag is swallowed once. */

    function statsPaneWidthApply(px) {
        statsPane.style.setProperty('--stats-width', Math.round(px) + 'px');
    }

    function statsPaneFold(collapsed, remember) {
        statsPane.classList.toggle('is-collapsed', collapsed);
        statsPane.classList.remove('is-peek');
        if (remember) settings.set('statsPaneOpen', !collapsed);
        if (!collapsed) statsRefresh();
    }

    /* THE SAME DRESS AS EVERY FOLDING SIDE (09.11.2026: „Beim
       Messen für die Statistikleiste können wir dann das gleiche Design
       nehmen … dass wir überall das gleiche Design haben … Das Handle ist
       nicht zu groß zusammengeklappt und aufgeklappt ist ein kleiner
       dezenter Pfeil nur da"). Open, the toggle is only the thin edge one
       drags the width at, and a small arrow in the middle of the rule folds
       the pane; folded, the toggle is the thin bar — the pointer over it
       lays the pane over the picture (is-peek, the picture keeps its room),
       a click keeps it open, a drag still opens and sizes it. */
    statsToggle.textContent = '';
    statsToggle.innerHTML = iconHtml('fold-triangle');
    const statsFoldBtn = document.createElement('button');
    statsFoldBtn.type = 'button';
    statsFoldBtn.className = 'photo-stats-fold';
    statsFoldBtn.title = t('Statistik einklappen');
    statsFoldBtn.setAttribute('aria-label', t('Statistik einklappen'));
    statsFoldBtn.innerHTML = iconHtml('fold-triangle');
    statsFoldBtn.addEventListener('click', () => statsPaneFold(true, true));
    statsPane.append(statsFoldBtn);
    let statsPeekTimer = null;
    function statsPeek() {
        clearTimeout(statsPeekTimer);
        if (!statsPane.classList.contains('is-collapsed') || statsPane.classList.contains('is-peek')) return;
        statsPane.classList.add('is-peek');
        statsRefresh();
    }
    statsToggle.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse' && !e.buttons) statsPeek(); });
    statsPane.addEventListener('pointerenter', () => clearTimeout(statsPeekTimer));
    statsPane.addEventListener('pointerleave', () => {
        clearTimeout(statsPeekTimer);
        statsPeekTimer = setTimeout(() => statsPane.classList.remove('is-peek'), 250);
    });

    {
        const savedWidth = Number(settings.get('statsPaneWidth', 0));
        if (savedWidth >= 160) statsPaneWidthApply(savedWidth);
        if (settings.get('statsPaneOpen', false) === true) {
            statsPaneFold(false, false);
        }
    }

    let statsResize = null;   // {startX, startWidth, moved}
    statsToggle.addEventListener('pointerdown', (e) => {
        statsResize = {
            startX: e.clientX,
            startWidth: statsPane.querySelector('.photo-stats-body').offsetWidth
                || Number(settings.get('statsPaneWidth', 240)) || 240,
            moved: false,
        };
        try { statsToggle.setPointerCapture(e.pointerId); } catch { /* egal */ }
    });
    statsToggle.addEventListener('pointermove', (e) => {
        if (statsResize === null) return;
        const delta = statsResize.startX - e.clientX;   // leftwards widens
        if (!statsResize.moved && Math.abs(delta) < 4) return;
        if (!statsResize.moved) {
            statsResize.moved = true;
            // Dragging a folded pane unfolds it — one gesture, not two.
            if (statsPane.classList.contains('is-collapsed')) {
                statsPaneFold(false, true);
            }
        }
        /* UP TO SEVEN TENTHS OF THE WINDOW (17.11.2026: „die
           Statistik lässt sich nur auf eine bestimmte Breite ziehen … da ich
           manchmal ja die kleinen Ausschnittsbilder unten habe und diese
           gerne in groß sehen will und nicht die ganze Zeit hoch und runter
           scrollen will"). A fixed 520 px stood here beside the share; on a
           wide screen that was a quarter of it. The share alone leaves the
           picture its three tenths at every window size — and the
           stylesheet holds a width kept from a larger window to the same
           share (photowindow.css, .photo-stats-body). */
        const width = Math.min(Math.max(statsResize.startWidth + delta, 160), window.innerWidth * 0.7);
        statsPaneWidthApply(width);
    });
    statsToggle.addEventListener('pointerup', () => {
        if (statsResize === null) return;
        const wasDrag = statsResize.moved;
        statsResize = null;
        if (wasDrag) {
            const width = statsPane.querySelector('.photo-stats-body').offsetWidth;
            if (width >= 160) settings.set('statsPaneWidth', width);
            suppressStatsClick = true;
        }
    });

    /* THE DISTRIBUTION'S HEIGHT (20.11.2026: „Unterhalb der
       Verteilung, oberhalb des Reglers Rand, hätte ich gerne ein Handle,
       mit dem ich die Höhe der Verteilung schieben kann. Also sie braucht
       nicht immer die volle Breite der Sidebar haben. So dass ich mir die
       Reihenausschnitte unten besser anschauen kann"). The grip under it
       sets the plot's height, and its box narrows with it (is-sized, the
       viewBox's proportions) and stands centred — not a full-width frame
       with the drawing small in its middle. Never taller than
       the full width allows — pulled there it is full again, unset —, never
       below 60 px. Per device, like the pane's width; a double click gives
       the full size back, and the arrow keys move it in steps of 10 px. */
    const plotEl = document.getElementById('photo-stats-plot');
    const plotGrip = document.getElementById('photo-stats-plot-grip');
    const PLOT_MIN = 60;
    // The full height is the one the pane's width gives — the parent's, since a sized plot is narrower itself.
    const plotFull = () => {
        const vb = plotEl.viewBox.baseVal;
        const box = plotEl.parentElement, cs = getComputedStyle(box);
        const width = box.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
        return vb && vb.width > 0 ? width * vb.height / vb.width : Infinity;
    };
    function plotHeightApply(px, remember = false) {
        const h = px > 0 && px < plotFull() - 1 ? Math.max(PLOT_MIN, Math.round(px)) : 0;
        plotEl.style.height = h > 0 ? h + 'px' : '';
        plotEl.classList.toggle('is-sized', h > 0);
        if (remember) settings.set('statsPlotHeight', h);
    }
    {
        const kept = Number(settings.get('statsPlotHeight', 0));
        plotEl.style.height = kept > 0 ? kept + 'px' : '';
        plotEl.classList.toggle('is-sized', kept > 0);
    }
    let plotDrag = null;   // {y, h}
    plotGrip.addEventListener('pointerdown', (e) => {
        if (e.button !== 0) return;
        e.preventDefault();
        plotDrag = { y: e.clientY, h: plotEl.getBoundingClientRect().height };
        plotGrip.classList.add('is-dragging');
        try { plotGrip.setPointerCapture(e.pointerId); } catch { /* the drag still works without */ }
    });
    plotGrip.addEventListener('pointermove', (e) => {
        if (plotDrag) plotHeightApply(plotDrag.h + e.clientY - plotDrag.y);
    });
    const plotDragEnd = () => {
        if (!plotDrag) return;
        plotDrag = null;
        plotGrip.classList.remove('is-dragging');
        plotHeightApply(parseFloat(plotEl.style.height) || 0, true);
    };
    plotGrip.addEventListener('pointerup', plotDragEnd);
    plotGrip.addEventListener('pointercancel', plotDragEnd);
    plotGrip.addEventListener('dblclick', () => plotHeightApply(0, true));
    plotGrip.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
        e.preventDefault();
        plotHeightApply(plotEl.getBoundingClientRect().height + (e.key === 'ArrowDown' ? 10 : -10), true);
    });

    let suppressStatsClick = false;
    statsToggle.addEventListener('click', () => {
        if (suppressStatsClick) { suppressStatsClick = false; return; }
        statsPaneFold(!statsPane.classList.contains('is-collapsed'), true);
    });

    /* --- The phone's bar over the picture ------------------------------------
     *
     * 08.09.2026: „das Bild vollflächig anzeigen, so wie wir das
     * bei der Karte machen und nur oben zwei, drei Schaltflächen". The
     * measuring row and the footer became flaps that slide up from the lower
     * edge (map.css); these four buttons are the only chrome the picture
     * carries below 860.
     *
     * THE BUTTONS DO NO WORK OF THEIR OWN, and that is the whole design here:
     * the cross closes the window, the sigma calls statsPaneFold(). Both
     * already exist, both are more than they look — the fold also remembers
     * and refreshes — and a second implementation beside them would be the
     * pair that drifts apart. Only the flaps are new state, and they are two
     * classes on the window.
     *
     * NO isPhone() ANYWHERE IN HERE. Whether a flap is a flap is the
     * stylesheet's word, and the classes mean nothing above 860; setting them
     * at the desk costs a class name on an element nobody can see. A width
     * asked in JavaScript would be a second answer to a question CSS already
     * answers, and the two would part company at the next breakpoint.
     */

    /** Unfold the measuring flap, or fold it — passing false always folds.
     *
     *  THERE WAS A SECOND FLAP UNTIL 09.09.2026, the „⋯" one over the footer.
     *  It held four zoom buttons that a gesture does better (pinch, and the
     *  double tap imagezoom.js has always had) and a second „Schließen"; what
     *  was left of it — deleting and the cloud's two ways — now stands in the
     *  footer permanently, which on the phone is the bottom bar. One flap
     *  needs no juggling of two. */
    function photoFlap(open) {
        // One menu at a time — the tool list hangs right beside this one.
        if (open) toolMenu(false);
        photoWindow.classList.toggle('is-measure-open', open);
        const button = document.getElementById('photo-phone-measure');
        button.classList.toggle('is-active', open);
        button.setAttribute('aria-expanded', String(open));
    }

    function photoFlapsClose() { photoFlap(false); toolMenu(false); }

    /* CHOOSING A TOOL — and „Auswählen" (once „Zeiger") is the way OUT of one, which a touch
       device had none of until 08.09.2026: at the desk the right click
       disarms, a finger has no right click.

       „Auswählen" takes exactly the three steps that right click takes: an open
       polyline is CLOSED, not thrown away — its taps were work.

       THE GUARD AGAINST THE SAME MODE is not theory: measureStart() TOGGLES,
       so calling it with the mode already armed would disarm it — and the
       chooser would then stand on a tool that is off. */
    {
        /* ONE WAY TO PICK, for the phone's menu and the desk's tool rail
           alike. „Auswählen" takes exactly the three steps the desk's right
           click takes: an open polyline is CLOSED, not thrown away — its
           taps were work.

           THE GUARD AGAINST THE SAME MODE is not theory: measureStart()
           TOGGLES, so calling it with the mode already armed would disarm it
           — and the trigger would then show a tool that is off. */
        function toolPick(tool) {
            if (tool.mode === '') {
                pathFinish();
                calibrateEnd();
                measureEnd();
                return;
            }
            const current = frameRect?.armed ? frameRect.mode : measureMode;
            if (tool.mode !== current) measureStart(tool.mode);
            else toolChoiceSync();
        }

        /* The menu is BUILT here and not written into index.php, out of the
           table above — see there why one list and not two. */
        const menu = document.getElementById('photo-phone-menu');
        let group = null;
        for (const tool of PHONE_TOOLS) {
            // A line between „Auswählen" and the first group, a head over
            // each group.
            if ((tool.group ?? null) !== group) {
                group = tool.group ?? null;
                if (group !== null) {
                    const head = document.createElement('div');
                    head.className = 'photo-phone-menu-head';
                    head.textContent = TOOL_GROUPS[group]();
                    menu.append(head);
                }
            }
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'button subtle';
            row.dataset.tool = tool.mode;
            row.innerHTML = iconHtml(tool.icon)
                + '<span class="photo-tool-text"><span class="photo-tool-name"></span></span>';
            row.querySelector('.photo-tool-name').textContent = tool.name();
            if (tool.hint) {
                const hint = document.createElement('span');
                hint.className = 'photo-tool-hint';
                hint.textContent = tool.hint();
                row.querySelector('.photo-tool-text').append(hint);
            }
            row.addEventListener('click', () => {
                toolMenu(false);
                toolPick(tool);
            });
            menu.append(row);
        }

        /* AND AT THE VERY BOTTOM THE CLEARING, set apart and red. It is NOT
           a tool — it brings nothing into focus, it takes something away —
           and that is why it stands behind a divider and not in the row of
           the five. At the desk it stays where it was: in the measure bar.

           IT DOES NOTHING ITSELF, but presses the button in the measure bar.
           That one asks first, clears exactly the chosen layer and tidies up
           the statistics — writing all of that a second time would be the
           pair that drifts apart. */
        const dangerRow = document.createElement('div');
        dangerRow.className = 'photo-phone-menu-danger';
        dangerRow.id = 'photo-phone-menu-clear';
        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.className = 'button subtle';
        clearButton.innerHTML = iconHtml('delete') + '<span></span>';
        clearButton.querySelector('span').textContent = t('Messungen leeren');
        clearButton.addEventListener('click', () => {
            toolMenu(false);
            document.getElementById('photo-measure-clear').click();
        });
        dangerRow.append(clearButton);
        menu.append(dangerRow);

        document.getElementById('photo-phone-tool').addEventListener('click', () => {
            toolMenu(menu.hidden);
        });

        /* AT THE DESK THE TOOLS STAND IN A RAIL beside the picture strip
           (30.10.2026: „die Mess- und Beschriftungstools links
           als Sidebar hin, allerdings nur die Symbole. Mit einem direkten
           Hover-Ausklapp-Beschreibungstext"). The dropdown asked for two
           clicks and hid what was on offer; at the desk there is room for
           all nine drawings at once, and the armed one shows by itself.

           Built from the same table as the menu, with the same two lines —
           only they unfold beside the symbol under the pointer instead of
           standing there. The groups part at a line; their names would not
           fit into a rail one symbol wide, and the drawings say enough. The
           phone keeps the dropdown: a rail would take a seventh of its
           picture for good (the stylesheet decides which one shows). */
        const rail = document.getElementById('photo-tool-rail');
        let railGroup = null;
        for (const tool of PHONE_TOOLS) {
            if ((tool.group ?? null) !== railGroup) {
                railGroup = tool.group ?? null;
                const line = document.createElement('span');
                line.className = 'photo-tool-rail-line';
                line.setAttribute('aria-hidden', 'true');
                rail.append(line);
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button subtle';
            button.dataset.tool = tool.mode;
            button.setAttribute('aria-label', tool.name());
            button.innerHTML = iconHtml(tool.icon)
                + '<span class="photo-tool-text" aria-hidden="true"><span class="photo-tool-name"></span></span>';
            button.querySelector('.photo-tool-name').textContent = tool.name();
            // Its key, beside its name — the digits and letters of photoKeys.
            const key = Object.entries(RAIL_KEYS).find(([, mode]) => mode === tool.mode)?.[0];
            if (key) {
                const k = document.createElement('kbd');
                k.className = 'photo-tool-key';
                k.textContent = key.toUpperCase();
                button.querySelector('.photo-tool-name').append(' ', k);
            }
            if (tool.hint) {
                const hint = document.createElement('span');
                hint.className = 'photo-tool-hint';
                hint.textContent = tool.hint();
                button.querySelector('.photo-tool-text').append(hint);
            }
            button.addEventListener('click', () => toolPick(tool));
            rail.append(button);
        }
        /* THE CLEARING AT THE FOOT, pushed down and apart — the same
           distance that protects it in the menu. It presses the hidden
           button too, and stands exactly while that one does: watched
           instead of copied, because eight places in this file show and
           hide that button, and a ninth line in each would be the pair that
           drifts apart. */
        const railClear = document.createElement('button');
        railClear.type = 'button';
        railClear.className = 'button subtle photo-tool-rail-clear';
        railClear.setAttribute('aria-label', t('Messungen leeren'));
        railClear.innerHTML = iconHtml('delete')
            + '<span class="photo-tool-text" aria-hidden="true"><span class="photo-tool-name"></span></span>';
        railClear.querySelector('.photo-tool-name').textContent = t('Messungen leeren');
        railClear.addEventListener('click', () => document.getElementById('photo-measure-clear').click());
        rail.append(railClear);
        const measureClear = document.getElementById('photo-measure-clear');
        const railClearSync = () => { railClear.hidden = measureClear.hidden; };
        new MutationObserver(railClearSync).observe(measureClear, { attributes: true, attributeFilter: ['hidden'] });
        railClearSync();
        /* A tap into the picture walks away from the menu, exactly as it does
           from the peek on the map. The frame's own handlers are untouched by
           this — it only listens. */
        /* THE PRESETS FOLD THE SAME WAY since they drop down over the
           picture (04.10.2026): a tap into it means one is done setting —
           and an aiming tap of the calibration must not land under them. */
        photoFrame.addEventListener('pointerdown', () => photoFlapsClose());
    }
    document.getElementById('photo-phone-measure').addEventListener('click', () =>
        photoFlap(!photoWindow.classList.contains('is-measure-open')));

    /* „MESSUNG" STANDS IN THE TOOL ROW, AT EVERY WIDTH (09.09.2026
     * on the phone, and 04.10.2026 for the desk: „Messung bleibt draußen,
     * kommt nicht in die Vorauswahl — das ist etwas, was häufig genutzt wird,
     * während man den Motivnamen und den Maßstab nur einmal setzt"). Until
     * then a matchMedia moved the one chooser between the phone's row and the
     * desk's measuring row; since the desk has the same row it simply stands
     * there in the markup (includes/photo_window.php). */
    /* THE RESTING STATE, said once at the start: nothing is armed, so the
       chooser stands on „Auswählen". Every later change runs through
       toolChoiceSync() from the four paths that arm and disarm. */
    toolChoiceSync();
    /* A window that opens shows the picture, not what was last unfolded over
     * it — and a closed one keeps no state for the next find. */
    photoWindow.addEventListener('close', photoFlapsClose);

    /* SPACE HOLDS THE HAND (29.08.2026): while the bar is down,
       every pointer gesture in the photo window is PANNING — no aiming tap,
       no selecting, no handle grab — and letting go re-arms whatever mode
       was on. The old graphics-tool reflex, and exactly what a long
       measuring session needs: reposition without disarming. Ignored while
       typing (a space in the calibration name field must stay a space), and
       the page scroll the bar would trigger is swallowed only here. */
    let spacePan = false;
    window.addEventListener('keydown', (e) => {
        if (e.code !== 'Space' || e.repeat || !viewing()) return;
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target?.tagName ?? '')
            || e.target?.isContentEditable;
        if (typing) return;
        spacePan = true;
        photoFrame.classList.add('is-space-pan');
        e.preventDefault();
    });
    window.addEventListener('keyup', (e) => {
        if (e.code !== 'Space') return;
        spacePan = false;
        photoFrame.classList.remove('is-space-pan');
    });
    photoWindow.addEventListener('close', () => {
        spacePan = false;
        photoFrame.classList.remove('is-space-pan');
    });

    /* Capture phase, so a measuring tap never doubles as the zoom's
       double-tap. Zooming stays possible the whole time — pinch and wheel run
       over other events — and that is wanted: aiming happens zoomed in. */
    /* WHAT FLOATS IN THE FRAME IS NOT THE PICTURE.
     *
     * The cutout offer's buttons live INSIDE .photo-window-image, in the
     * aiming hint, so they can lie over the picture. Their taps are ANSWERS,
     * not aiming taps.
     *
     * ASKING THIS IS NOT POLITENESS, IT IS THE ONLY WAY THEY WORK AT ALL.
     * The click listener below CAPTURES, and while a mode is armed it stops
     * the event dead — so without this line a tap on a floating button never
     * reaches that button's own listener, and the picture underneath takes
     * the tap instead. The maintainer found it the day the bar shipped
     * (08.09.2026): „im Messen Modus … scheint man durch die Knöpfe zu
     * klicken, um darunter dann zu messen … man kann die Knöpfe nicht mehr
     * bedienen."
     *
     * IT WAS THE PHONE'S BAR THAT TAUGHT US THIS, and the bar itself has
     * since left: floating over a picture that carries the user's OWN movable
     * chrome — the scale bar in any corner, the hint across the top — always
     * covers something, so the bar became a row of its own (08.09.2026). What
     * stays is the question, asked now by every handler that could swallow
     * such a tap, so the next control laid over the picture inherits the
     * answer instead of repeating the bug.
     */
    function photoChrome(e) {
        return e.target instanceof Element
            && e.target.closest('.photo-frame-hint') !== null;
    }

    /* A CLICK ON AN EMPTY PLACE PUTS THE TOOL DOWN (12.11.2026:
       „das Werkzeug abbrechen, wenn man außerhalb des Bildes hinklickt.
       Also natürlich keine Bedienelemente … aber irgendwo auf leere
       Flächen, dass man zurück zum Pfeil kommt, zum Auswahlwerkzeug") —
       the same as „Auswählen" in the rail: a polyline in progress is
       closed, a calibration and a half-tapped measure are let go. EMPTY
       means: not on the picture itself (there the click is the tool's),
       nothing one operates — a button, a field, a tab, a slider, a
       thumbnail, the scale bar —, and nothing in a window laid over this
       one. A press that moved (a pan, a drag) is no click on a place. */
    const MEASURE_CONTROLS = 'button, a[href], input, select, textarea, label, summary, [contenteditable], [data-grip], '
        + '[role="button"], [role="tab"], [role="slider"], [role="menuitem"], [role="option"], [role="radio"], '
        + '[role="checkbox"], [role="switch"], [role="link"], .photo-scalebar, .photo-frame-hint';
    let measureDownAt = null;
    document.addEventListener('pointerdown', (e) => { measureDownAt = [e.clientX, e.clientY]; }, true);
    document.addEventListener('click', (e) => {
        if (!viewing() || developMode || spacePan) return;
        if (measureMode === null && calibrating === null && !frameRect?.armed) return;
        if (measureDownAt && Math.hypot(e.clientX - measureDownAt[0], e.clientY - measureDownAt[1]) > 4) return;
        const el = e.target instanceof Element ? e.target : null;
        if (!el || el.closest(MEASURE_CONTROLS)) return;
        if (photoFrame.contains(el)) {
            // In the frame only the black beside the picture is empty.
            const at = clientNatural(e.clientX, e.clientY);
            if (!at || at.inside) return;
        } else {
            // Beside the frame: the page's bare ground — no picture of the strip or the statistics, no other window.
            if (el.closest('img, canvas, video')) return;
            const over = el.closest('dialog');
            if (over && over !== photoWindow) return;
        }
        pathFinish();
        calibrateEnd();
        measureEnd();
    }, true);

    photoFrame.addEventListener('click', (e) => {
        // The held space bar makes every click the pan's business alone.
        if (spacePan) return;
        if (photoChrome(e)) return;
        /* A TAP BESIDE THE WORDS ENDS THE WRITING, and does nothing else —
           its pointerdown already kept the text (textEditEnd); acting on its
           click too would deselect what was just written, or start the
           next thing where one only meant „done". */
        if (performance.now() - textCommitAt < 700) {
            textCommitAt = 0;
            e.stopPropagation();
            return;
        }
        /* NOR DOES THE CLICK THAT TRAILS A FRAME JUST PULLED OPEN: it lands
           where the hand let go, which the frame's snapped corner may have
           left — read as a click into the void, it let the new frame go
           again at once. */
        if (performance.now() - boxDrawnAt < 400) {
            e.stopPropagation();
            return;
        }
        /* Swallowed BEFORE the bounds check while a mode is armed, exactly
           as before the delete branch moved in: an aiming tap that misses
           the image must not double as the zoom's gesture either. */
        const armed = calibrating !== null || measureMode !== null;
        if (armed) e.stopPropagation();
        // Through the rect and not through offsets: the rect already carries
        // zoom and pan, so this works mid-gesture at any magnification —
        // and through the view, so a turned or cut picture hits its pixel.
        const at = clientNatural(e.clientX, e.clientY);
        if (!at || !at.inside) return;
        const p = at.p;

        /* NO MODE ARMED: a tap near a measure's stroke SELECTS it — handles
           and the × appear (the maintainer's second word, 28.08.2026: „erstmal
           gehighlightet … und irgendwo ein X", not tap-and-dialog). The ×
           deletes, a tap into the void deselects, and a tap that never
           touched a measure stays the zoom's. Eight screen pixels of reach,
           restated in image units at the current zoom. */
        if (!armed) {
            // The click that trails a finished handle drag says nothing new.
            if (suppressClick) {
                suppressClick = false;
                e.stopPropagation();
                return;
            }
            /* NOR DOES THE CLICK THAT TRAILS A PAN (18.11.2026:
               „die Zählung wird … beendet, … wenn ich außerhalb klicke und
               das Bild verschiebe. Das sollte auch nicht passieren"). The
               pan is imagezoom's and captures the pointer, so its release
               arrives here as a click — beside a chosen sector or frame it
               let it go, inside one it counted a point where the hand
               happened to stop. Whoever moved the picture did nothing else. */
            if (pressAt !== null && Math.hypot(e.clientX - pressAt[0], e.clientY - pressAt[1]) > 6) {
                e.stopPropagation();
                return;
            }
            // The × first — it may lie farther from the strokes than reach.
            if (measureSelected !== null && removeAnchor !== null
                && Math.hypot(p[0] - removeAnchor[0], p[1] - removeAnchor[1])
                    <= screenUnits(11)) {
                e.stopPropagation();
                measureSelectedDelete();
                return;
            }
            // On the chosen sector a click sets its slice or counts in it (sectorClick).
            if (sectorClick(p)) {
                e.stopPropagation();
                return;
            }
            // Inside the chosen frame a click counts (boxCount).
            if (boxCount(p)) {
                e.stopPropagation();
                return;
            }
            const hit = measureAt(p, screenUnits(8));
            // Whatever this tap does, the thumbnail's focus has done its job.
            if (measureFocus !== null) {
                setMeasureFocus(null);
                drawOverlay();
            }
            if (!hit) {
                if (measureSelected !== null) {
                    e.stopPropagation();
                    measureDeselect();
                }
                return;
            }
            // Selecting implies the right to edit — a viewer without it
            // would be handed handles that refuse to work.
            /* A MEASURE OF ONE'S OWN ON A PICTURE ONE MAY ONLY LOOK AT
               (temporary, 06.10.2026) is one's own to select, drag and
               delete — it was never saved, and nothing of the photographer's
               is touched by it. */
            if (!hit.free && !hit.m.temporary && !scaleMayEdit()) return;
            e.stopPropagation();
            /* A SECOND TAP ON A CHOSEN TEXT WRITES IN IT — the words open
               where they stand, the caret at their end. A drag in the box
               moved it instead, and its trailing click was swallowed above. */
            if ((hit.m.type === 'text' || hit.onWords) && measureSelected?.m === hit.m) {
                textEditStart(hit.m, null, hit.free);
                return;
            }
            // An offer that belonged to the previous selection is stale now.
            frameClose();
            measureSelected = { m: hit.m, free: hit.free };
            /* A SELECTED CUTOUT STILL LACKING ITS BREADTH offers it (the
               door of 30.08.2026, „noch mal angeboten", narrowed on
               04.10.2026): whoever measured the length and walked away can
               come back for the breadth. A plain „Länge × Breite" no longer
               turns into a cutout afterwards — that is the other tool now.
               Only where measuring is possible at all: with a scale. */
            const steps = CYSTID_PROFILES[hit.m.profile] ? cystidSteps(hit.m.profile).length : 2;
            const done = hit.m.inner?.length ?? 0;
            if (hit.m.type === 'rect' && done >= 1 && done < steps
                && measureSubject()?.scale != null) {
                frameRect = { m: hit.m, free: hit.free, armed: false, mode: hit.m.profile ? 'cystid' : 'cutout' };
                frameOffer();
            }
            drawOverlay();
            return;
        }

        if (calibrating !== null) {
            if (calibrating.length >= 2) return;
            calibrating.push(p);
            drawOverlay();
            if (calibrating.length === 1) {
                calibrationHint(t('Jetzt klicke auf das Ende.'));
            } else {
                openScaleWindow();
            }
            return;
        }

        /* A NEW MEASURE BEGINS: the one finished before hands back its
           mark, so only one thing ever stands in the accent. Not inside a
           cutout — its length and breadth are still the frame's, and the
           frame keeps the mark until it is done. */
        if (measurePoints.length === 0 && frameRect === null && measureFocus !== null) {
            setMeasureFocus(null);
        }
        if (measurePoints.length === 0 && frameRect === null) frameJustDone = null;

        /* The polyline grows tap by tap until the double tap closes it;
           the text mode wants exactly one tap — where the words go — and
           the writing begins right there. */
        if (measureMode === 'path') {
            measurePoints.push(p);
            drawOverlay();
            return;
        }
        if (measureMode === 'text') {
            textEditStart(null, p);
            return;
        }
        // The frame is pulled open, not tapped (boxDrawStart); a tap with the tool on does nothing.
        if (measureMode === 'box') return;

        measurePoints.push(p);
        // The sector's three taps make its ellipse; it comes out chosen, for the slice (sectorPlace).
        if (measureMode === 'sector') {
            if (measurePoints.length >= 3) {
                const taps = measurePoints;
                measurePoints = [];
                sectorPlace(taps);
            } else {
                drawOverlay();
            }
            return;
        }
        const needed = measureMode === 'line' || measureMode === 'arrow' ? 2 : 4;
        if (measurePoints.length >= needed) {
            // A line drawn INTO a cutout belongs to its rectangle.
            if (frameRect !== null && measureMode === 'line') {
                frameInnerAdd({ type: 'line', points: measurePoints });
                measurePoints = [];
                drawOverlay();
                return;
            }
            /* width pins the taps to THESE pixels (preview or original) — the
               same reference-width idea as the calibration's, and what lets
               the measure persist and still lie right after „Volle Größe". */
            // A cutout's frame is stored as what it is: a rectangle.
            const done = { type: measureMode === 'cutout' || measureMode === 'cystid' ? 'rect' : measureMode,
                points: measurePoints, width: photoImageEl.naturalWidth };
            // A cystidium's frame says by which method its inside is measured.
            if (measureMode === 'cystid') done.profile = cystidProfile();
            /* THE TIP FIRST (30.10.2026: „Man klickt erst dorthin,
               was der Pfeil zeigen soll. Danach dorthin, wo der Pfeil quasi
               endet"). What one points at is the exact place, the tail only
               says from where — so the careful tap comes first. Stored as
               ever, tail then tip, so every arrow set before stays as it was
               and the plate and the viewer draw it unchanged. */
            if (done.type === 'arrow') {
                done.points = [...measurePoints].reverse();
                arrowJustSet = { m: done, free: statsLabel === MEASURE_FREE };
            }
            // An arrow begins in the strength of the last one set — a series
            // of arrows comes out alike (ARROW_WEIGHTS).
            if (done.type === 'arrow') {
                const kept = Number(settings.get('arrowWeight', 2));
                done.weight = Number.isInteger(kept) && kept >= 1 && kept <= ARROW_WEIGHTS.length ? kept : 2;
            }
            measurePoints = [];
            document.getElementById('photo-measure-clear').hidden = false;
            if (statsLabel === MEASURE_FREE) {
                /* Free measuring: drawn, never saved, never counted — the
                   quick look at a dimension that is nobody's statistic. */
                freeMeasures.push(done);
            } else {
                // The chooser's label rides on the measure — it is what the
                // statistics groups by, across all pictures of the find.
                done.label = statsLabel;
                // The mark chosen over the picture rides along (markHintSync).
                const stamp = markStamps.get(statsLabel);
                if (stamp && done.type === 'rect') done.mark = stamp;
                const part = statsTabs.get(statsLabel);
                if (part && cropOfTab(part) === null && done.type === 'line') done.part = part;
                // A cutout takes the name of the cutout tab on view.
                const named = cropOfTab(part ?? null);
                if (named && done.type === 'crop') done.name = named;
                // On a picture one may only look at it stays this window's.
                if (!scaleMayEdit()) done.temporary = true;
                measures.push(done);
                measureSaveQueue();
                /* The first finished measure opens the sidebar by itself —
                   whoever measures wants to see the numbers grow; ONCE per
                   window, so a deliberate collapse is respected for the rest
                   of the series. */
                if ((measureMode === 'rect' || measureMode === 'cutout' || measureMode === 'cystid') && !statsAutoOpened) {
                    statsAutoOpened = true;
                    if (statsPane.classList.contains('is-collapsed')) {
                        // Transient courtesy, NOT remembered: the saved fold
                        // state is what the user chose at the strip.
                        statsPaneFold(false, false);
                    }
                }
                statsRefresh();
                /* THE NEW ONE IS THE CHOSEN ONE (04.10.2026:
                   „wenn ich ein neues Maß setze … wird mir das nicht
                   automatisch in der Verteilung angezeigt, und auch nicht
                   … in den Vorschaubildern markiert"): a finished rectangle
                   or cutout frame takes the focus, so its tile is framed
                   and rolled into sight, its dot stands out, and the
                   picture draws it in the accent — where the sorting has
                   put it among thirty is not something to search for.
                   After the refresh, or the tile would not exist yet. A
                   line, a polyline or a text makes no row and gets none. */
                if (done.type === 'rect') setMeasureFocus(done);
            }
            /* The cutout tool's frame leads straight on into its inside —
               the length first (frameOffer). In the free layer too, where a
               quick look at a bent structure is what free measuring is for.
               „Länge × Breite" is done with its four taps. */
            if (measureMode === 'cutout' || measureMode === 'cystid') {
                frameRect = { m: done, free: statsLabel === MEASURE_FREE, armed: true, mode: measureMode };
                frameOffer();
            }
        }
        drawOverlay();
    }, true);

    /* --- The cursor says what a press would do ------------------------------
       04.10.2026: „wenn ich über Maße fahre, zum Beispiel über
       das Rechteck beim Sporenmessen, bleibt der Cursor als Hand … wenn ich
       aber an den Rand klicke, kann ich ja hier bearbeiten … hier sollte
       sich natürlich dann auch der Cursor dementsprechend ändern."

       The same hit tests as the press below and the click above, in the
       same order: the ×, the round movers, the corner squares, a stroke.
       A rectangle's mover runs on a rail (along its axis or across it), so
       its cursor is a double arrow turned to EXACTLY that direction, drawn
       from the icon library's „resize" (drawnCursor); a free point (a
       line's end, a cutout's inner point) gets the four arrows; a corner
       square, which turns, a curved arrow drawn from „refresh"; the × and
       a stroke one may select, the pointing hand. Over nothing the inline cursor is
       cleared, and the frame's own rules come back — the hand of a zoomed
       picture, the crosshair while aiming. Mouse only: a finger has no
       hover, and a drag keeps the cursor it began with. */

    const drawnCursors = new Map();

    /**
     * A cursor DRAWN from the icon library, turned by `degrees` — black on
     * a white halo, so it reads on a dark micrograph and a pale one alike.
     * The browser's own cursors know only four resize directions, 45
     * degrees apart; a drawn one points exactly where the side runs
     * (04.10.2026: „die Cursor an sich kannst du vermutlich
     * nicht drehen … um genau den Winkel zu treffen"). The price: a drawn
     * cursor keeps its 24 pixels when the system's pointer is enlarged.
     * Built once per name and whole degree; `fallback` is what a browser
     * that refuses the picture shows instead.
     */
    function drawnCursor(name, degrees, fallback) {
        const key = name + ':' + degrees;
        if (drawnCursors.has(key)) return drawnCursors.get(key);
        let value = fallback;
        try {
            const drawn = icon(name);
            const ns = 'http://www.w3.org/2000/svg';
            const svg = document.createElementNS(ns, 'svg');
            svg.setAttribute('xmlns', ns);
            svg.setAttribute('viewBox', drawn.getAttribute('viewBox') || '0 0 24 24');
            svg.setAttribute('width', '24');
            svg.setAttribute('height', '24');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke-linecap', 'round');
            svg.setAttribute('stroke-linejoin', 'round');
            const turned = document.createElementNS(ns, 'g');
            if (degrees !== 0) turned.setAttribute('transform', `rotate(${degrees} 12 12)`);
            for (const [colour, width] of [['#fff', '4.5'], ['#000', '2']]) {
                const g = document.createElementNS(ns, 'g');
                g.setAttribute('stroke', colour);
                g.setAttribute('stroke-width', width);
                for (const part of drawn.children) g.append(part.cloneNode(true));
                turned.append(g);
            }
            svg.append(turned);
            value = 'url("data:image/svg+xml,'
                + encodeURIComponent(new XMLSerializer().serializeToString(svg)) + '") 12 12, ' + fallback;
        } catch {
            // The browser's own cursor stands in.
        }
        drawnCursors.set(key, value);
        return value;
    }

    /** The turning cursor at a rectangle's corners. */
    function rotateCursor() {
        return drawnCursor('refresh', 0, 'alias');
    }

    /** The resize arrow along a direction on screen (y grows downward) —
     *  drawn at its exact angle, with the nearest of the browser's four as
     *  the fallback. */
    function resizeCursor(x, y) {
        const deg = Math.round(((Math.atan2(y, x) * 180 / Math.PI) % 180 + 180) % 180) % 180;
        const fallback = deg < 22.5 || deg >= 157.5 ? 'ew-resize'
            : deg < 67.5 ? 'nwse-resize'
            : deg < 112.5 ? 'ns-resize'
            : 'nesw-resize';
        return drawnCursor('resize', deg, fallback);
    }

    function measureCursor(e) {
        if (e.pointerType !== 'mouse' || spacePan || calibrating !== null || measureMode !== null) return '';
        if (photoChrome(e)) return '';
        const p = naturalPoint(e);
        if (p === null) return '';
        if (measureSelected !== null) {
            if (removeAnchor !== null
                && Math.hypot(p[0] - removeAnchor[0], p[1] - removeAnchor[1]) <= screenUnits(11)) {
                return 'pointer';
            }
            if (measureSelected.m.type === 'arrow' && arrowHandle !== null
                && Math.hypot(p[0] - arrowHandle.pos[0], p[1] - arrowHandle.pos[1]) <= screenUnits(12)) {
                return resizeCursor(arrowHandle.normal[0], arrowHandle.normal[1]);
            }
            /* A chosen text: the turning handle turns, the corner sizes —
               its arrow along the box's diagonal —, the inside moves. */
            if (measureSelected.m.type === 'text' && textEdit === null) {
                const part = textGrabAt(p);
                if (part === 'rotate') return rotateCursor();
                if (part === 'size') {
                    const box = textBox(measureSelected.m);
                    return resizeCursor(box.sizeHandle[0] - box.corners[0][0],
                                        box.sizeHandle[1] - box.corners[0][1]);
                }
                if (part === 'move') return 'move';
            }
            // A chosen sector: the circle turns it, beside the slice it is carried, its points are grabbed.
            if (measureSelected.m.type === 'sector' && sectorAim === null) {
                const part = sectorGrabAt(p);
                if (part === 'rotate') return rotateCursor();
                if (part === 'move' || part === 'middle') return 'move';
                if (part !== null) return 'grab';
            }
            // A chosen box: the circle turns it, inside and rim carry it.
            if (measureSelected.m.type === 'box') {
                const part = boxGrabAt(p);
                if (part === 'rotate') return rotateCursor();
                if (part === 'move') return 'move';
            }
            const grab = handleAt(p, screenUnits(14));
            if (grab !== null) {
                const m = measureSelected.m;
                if ((m.type !== 'rect' && m.type !== 'crop') || grab.inner != null) return 'move';
                const [a, b] = m.points;
                const ux = b[0] - a[0], uy = b[1] - a[1];
                return grab.index <= 1 ? resizeCursor(ux, uy) : resizeCursor(-uy, ux);
            }
            if (rotateHandleAt(p, screenUnits(12)) !== null) return rotateCursor();
        }
        const hit = measureAt(p, screenUnits(8));
        return hit && (hit.free || hit.m.temporary || scaleMayEdit()) ? 'pointer' : '';
    }

    photoFrame.addEventListener('pointermove', (e) => {
        if (measureDrag !== null) return;
        const cursor = measureCursor(e);
        if (photoFrame.style.cursor !== cursor) photoFrame.style.cursor = cursor;
    });
    photoFrame.addEventListener('pointerleave', () => {
        if (measureDrag === null) photoFrame.style.cursor = '';
    });

    /* --- Dragging a selected measure's handles ------------------------------
       Capture phase with pointer capture, the scale bar's pattern: the frame
       is imagezoom's pan surface, and a grabbed handle must win against the
       pan without the zoom ever noticing the pointer. The drag edits the
       point IN ITS OWN stored space (divided back by the width factor), so
       preview and original keep agreeing; saved once on release, not per
       twitch. */

    photoFrame.addEventListener('pointerdown', (e) => {
        if (e.isPrimary) pressAt = [e.clientX, e.clientY];
    }, true);

    photoFrame.addEventListener('pointerdown', (e) => {
        if (photoChrome(e)) return;
        /* WHILE WORDS ARE BEING WRITTEN, a press in the picture beside them
           TAKES THEM BACK (07.10.2026: „abbrechen … außerhalb
           klicken") — „Fertig" over the picture is the way to keep them.
           Its click is swallowed (textCommitAt), and the press goes on to
           be whatever else it is — a pan, a handle. */
        if (textEdit !== null) {
            textEditEnd(false);
            textCommitAt = performance.now();
        }
        if (spacePan) return;   // the hand outranks the handles
        if (e.button !== 0) return;   // middle and right drag stay the pan's
        if (measureSelected === null || calibrating !== null || measureMode !== null) return;
        const p = naturalPoint(e);
        if (p === null) return;
        const m = measureSelected.m;
        const across = measureAcross(m);
        /* A CHOSEN TEXT is moved inside its box, sized at its corner and
           turned at its stem (drawTextSelection). Its size is read here
           and written only on the first movement, so a text from before
           the sizes keeps its stored shape until it is really changed. */
        if (m.type === 'text') {
            const part = textGrabAt(p);
            if (part === null) return;
            const box = textBox(m);
            measureDrag = { kind: 'text-' + part, m, across,
                free: measureSelected.free, moved: false, start: p,
                anchor: box.anchor, centre: box.centre,
                baseSize: box.fs / across, baseAngle: m.angle ?? 0,
                basePoint: [m.points[0][0], m.points[0][1]],
                startDist: Math.hypot(p[0] - box.anchor[0], p[1] - box.anchor[1]) || 1,
                startAngle: Math.atan2(p[1] - box.centre[1], p[0] - box.centre[0]) };
            try { photoFrame.setPointerCapture(e.pointerId); } catch { /* egal */ }
            e.stopPropagation();
            e.preventDefault();
            return;
        }
        /* A CHOSEN SECTOR: the middle and a drag beside the slice carry it,
           the two rim points reshape it, the circle turns it, the edge points
           move the slice's angles (sectorGrabAt). Not while its slice is
           being set: then every click is the slice's (sectorClick). A press
           that never moves stays a click — on the middle it starts the
           slice, in the slice it counts. */
        if (m.type === 'sector') {
            if (sectorAim !== null) return;
            const part = sectorGrabAt(p);
            if (part === null) return;
            const baseCount = {};
            for (const { key } of SECTOR_KINDS) if (m[key]) baseCount[key] = m[key].map(([x, y]) => [x, y]);
            const arc = sectorArc(m), g = sectorGeometry(m);
            const edge = part === 'from' ? 0 : 1;
            measureDrag = { kind: 'sector-' + part, m, across, free: measureSelected.free, moved: false,
                start: p, base: m.points.map(([x, y]) => [x, y]), baseCount, baseArc: arc,
                baseStem: Array.isArray(m.stem) ? [m.stem[0], m.stem[1]] : null,
                // The whole rim counted: its edge points move the rim that carries lamellae, the count goes along.
                wasWhole: sectorWhole(m) && Array.isArray(m.rim),
                value: arc?.[edge], last: arc !== null && g !== null ? sectorWrap(arc[edge]) : 0 };
            try { photoFrame.setPointerCapture(e.pointerId); } catch { /* egal */ }
            e.stopPropagation();
            e.preventDefault();
            return;
        }
        // A chosen box is carried from inside or its rim, turned at its circle.
        if (m.type === 'box') {
            const part = boxGrabAt(p);
            if (part === null) return;
            const g = boxGeometry(m);
            measureDrag = { kind: 'box-' + part, m, across,
                free: measureSelected.free, moved: false, start: p, centre: g.centre,
                baseAngle: m.angle ?? 0, basePoint: [m.points[0][0], m.points[0][1]],
                startAngle: Math.atan2(p[1] - g.centre[1], p[0] - g.centre[0]) };
            try { photoFrame.setPointerCapture(e.pointerId); } catch { /* egal */ }
            e.stopPropagation();
            e.preventDefault();
            return;
        }
        // Its words' size square, before the rest.
        if (m.type === 'arrow' && arrowSizeHandle !== null
            && Math.hypot(p[0] - arrowSizeHandle.pos[0], p[1] - arrowSizeHandle.pos[1]) <= screenUnits(12)) {
            const words = arrowWords(m);
            const [tx, ty] = arrowSizeHandle.tail;
            measureDrag = { kind: 'arrow-size', m, across, free: measureSelected.free,
                moved: false, start: p, tail: arrowSizeHandle.tail, baseShown: arrowWordsShown(words),
                startDist: Math.max(1, Math.hypot(p[0] - tx, p[1] - ty)) };
            try { photoFrame.setPointerCapture(e.pointerId); } catch { /* egal */ }
            e.stopPropagation();
            e.preventDefault();
            return;
        }
        // An arrow's strength square, before its two ends.
        if (m.type === 'arrow' && arrowHandle !== null
            && Math.hypot(p[0] - arrowHandle.pos[0], p[1] - arrowHandle.pos[1]) <= screenUnits(12)) {
            measureDrag = { kind: 'arrow-weight', m, across, free: measureSelected.free,
                moved: false, start: p, normal: arrowHandle.normal, base: arrowWeightOf(m), baseSize: m.size ?? null };
            try { photoFrame.setPointerCapture(e.pointerId); } catch { /* egal */ }
            e.stopPropagation();
            e.preventDefault();
            return;
        }
        /* The round handles WIN the overlap with the corner squares: a
           flank tap can sit right on an edge, and moving is the commoner
           intent — the squares keep the corners to themselves. */
        const grab = handleAt(p, screenUnits(14));
        if (grab !== null) {
            // A handle with `inner` set moves a point of the cutout's inner
            // measure; the rest address the measure's own taps.
            const target = grab.inner != null ? m.inner[grab.inner].points : m.points;
            const index = grab.index;
            /* BY OFFSET, not by jump: the mover may sit away from its point
               (an edge midpoint), and the point must follow the drag's
               MOVEMENT — snapping it under the finger would first yank the
               side to wherever the handle happened to be grabbed.

               A RECTANGLE'S MOVERS RUN ON RAILS (28.08.2026:
               „eine Rotation nur über die Eckpunkte … die Linienanfasser in
               der Mitte einfach nur die Länge ändern"): the short-end
               movers slide their axis point only ALONG the axis — length,
               never re-aiming — the flank movers only ACROSS it. Turning
               belongs to the corner squares alone. A line's two ends stay
               free, as asked before. */
            let rail = null;
            if ((m.type === 'rect' || m.type === 'crop') && grab.inner == null) {
                const [a, b] = m.points;
                const len = Math.hypot(b[0] - a[0], b[1] - a[1]) || 1;
                const u = [(b[0] - a[0]) / len, (b[1] - a[1]) / len];
                rail = index <= 1 ? u : [-u[1], u[0]];
            }
            measureDrag = { kind: 'point', m, points: target, index, across,
                free: measureSelected.free, moved: false, rail,
                base: [target[index][0], target[index][1]],
                offset: [target[index][0] - p[0] / across,
                         target[index][1] - p[1] / across] };
        } else {
            const corner = rotateHandleAt(p, screenUnits(12));
            if (corner === null) return;
            /* Rotation about the CENTRE (28.08.2026: „Gedreht
               natürlich übers Zentrum") — the four taps swing together
               around the rectangle's midpoint, lengths untouched. Base
               copy plus total angle from the drag's start, so a jittery
               pointer cannot accumulate drift. */
            const c = rectGeometry(m.points).corners;
            const centre = [(c[0][0] + c[2][0]) / 2, (c[0][1] + c[2][1]) / 2];
            measureDrag = { kind: 'rotate', m, across,
                free: measureSelected.free, moved: false, centre,
                base: m.points.map(([x, y]) => [x, y]),
                // A cutout turns as a UNIT — the inner measures swing along,
                // or the frame would rotate away from what it frames.
                baseInner: (m.inner ?? []).map(mi => mi.points.map(([x, y]) => [x, y])),
                startAngle: Math.atan2(p[1] / across - centre[1],
                                       p[0] / across - centre[0]) };
        }
        // Best effort: the capture keeps a fast finger on the handle, but a
        // drag must not die on a pointer the browser will not pin.
        try { photoFrame.setPointerCapture(e.pointerId); } catch { /* egal */ }
        e.stopPropagation();
        e.preventDefault();
    }, true);

    photoFrame.addEventListener('pointermove', (e) => {
        if (measureDrag === null) return;
        const p = naturalPoint(e);
        if (p === null) return;
        if (measureDrag.kind.startsWith('text-')) {
            textDragTo(measureDrag, p);
        } else if (measureDrag.kind.startsWith('sector-')) {
            // Not yet a drag: the click on the middle that starts the slice must stay a click.
            if (sectorDragTo(measureDrag, p, e.shiftKey) === false) return;
        } else if (measureDrag.kind.startsWith('box-')) {
            // Not yet a carry: a counting click's tremble moves nothing, and its click still counts.
            if (boxDragTo(measureDrag, p) === false) return;
        } else if (measureDrag.kind === 'arrow-size') {
            /* Measured from the tail, the one point that stays while the
               words grow away from it (arrowWordsGrip). A step of
               TEXT_SIZES at a time, stored without the strength's share,
               which arrowTextLook adds back. */
            const d = measureDrag;
            const k = Math.hypot(p[0] - d.tail[0], p[1] - d.tail[1]) / d.startDist;
            const step = textStep(d.baseShown * k);
            const width = d.m.width || photoImageEl.naturalWidth / d.across;
            d.m.size = Math.round(step / 1000 * width / arrowTextLook(d.m).scale * 10) / 10;
        } else if (measureDrag.kind === 'arrow-weight') {
            /* One step for every fourteen screen pixels away from the shaft
               (or back toward it) — steps one can count with the hand. */
            const d = measureDrag;
            const along = (p[0] - d.start[0]) * d.normal[0] + (p[1] - d.start[1]) * d.normal[1];
            const weight = Math.min(Math.max(d.base + Math.round(along / screenUnits(14)), 1),
                ARROW_WEIGHTS.length);
            // The words keep their size (measuredraw.js, arrowWeightSet).
            arrowWeightSet(d.m, { weight: d.base, size: d.baseSize }, weight);
            settings.set('arrowWeight', weight);
        } else if (measureDrag.kind === 'point') {
            const raw = [
                p[0] / measureDrag.across + measureDrag.offset[0],
                p[1] / measureDrag.across + measureDrag.offset[1],
            ];
            if (measureDrag.rail !== null) {
                // Projected onto the rail: only the allowed direction of
                // the drag reaches the point, the rest falls away.
                const [rx, ry] = measureDrag.rail;
                const k = (raw[0] - measureDrag.base[0]) * rx
                        + (raw[1] - measureDrag.base[1]) * ry;
                measureDrag.points[measureDrag.index] = [
                    measureDrag.base[0] + k * rx,
                    measureDrag.base[1] + k * ry,
                ];
            } else {
                measureDrag.points[measureDrag.index] = raw;
            }
        } else {
            const { m, across, centre, base, baseInner, startAngle } = measureDrag;
            const delta = Math.atan2(p[1] / across - centre[1],
                                     p[0] / across - centre[0]) - startAngle;
            const sin = Math.sin(delta), cos = Math.cos(delta);
            const turn = ([x, y]) => [
                centre[0] + (x - centre[0]) * cos - (y - centre[1]) * sin,
                centre[1] + (x - centre[0]) * sin + (y - centre[1]) * cos,
            ];
            m.points = base.map(turn);
            (m.inner ?? []).forEach((mi, i) => { mi.points = baseInner[i].map(turn); });
        }
        measureDrag.moved = true;
        drawOverlay();
        e.stopPropagation();
    }, true);

    photoFrame.addEventListener('pointerup', (e) => {
        if (measureDrag === null) return;
        const drag = measureDrag;
        measureDrag = null;
        if (drag.moved) {
            // The trailing click must not read as "deselect".
            suppressClick = true;
            // A reshaped ellipse or a moved edge may leave points outside the slice: they go now, when the hand lets go.
            if (drag.kind.startsWith('sector-')) {
                sectorPrune(drag.m);
                drawOverlay();
            }
            if (!drag.free) {
                measureSaveQueue();
                statsRefresh();
            }
        }
        e.stopPropagation();
    }, true);

    /* RIGHT-CLICK ENDS THE TOOL (29.08.2026) — the old CAD
       gesture: whatever is armed, one right click disarms it, and the
       browser menu stays away only then. An open polyline is CLOSED first,
       not thrown away — its taps were work. When nothing is armed the
       click stays the browser's. */
    photoFrame.addEventListener('contextmenu', (e) => {
        if (photoChrome(e)) return;
        if (calibrating === null && measureMode === null) return;
        e.preventDefault();
        pathFinish();
        calibrateEnd();
        measureEnd();
    });

    /* The double tap closes the polyline. Its two single clicks have
       already appended the same point twice — the duplicate is dropped
       before finishing, or every run would end in a zero-length tail. */
    photoFrame.addEventListener('dblclick', (e) => {
        if (photoChrome(e)) return;
        if (measureMode !== 'path') return;
        e.stopPropagation();
        const n = measurePoints.length;
        if (n >= 2) {
            const [ax, ay] = measurePoints[n - 2], [bx, by] = measurePoints[n - 1];
            if (Math.hypot(ax - bx, ay - by) < screenUnits(6)) measurePoints.pop();
        }
        pathFinish();
    }, true);

    /* --- Writing onto the picture ------------------------------------------- *
     *
     * STRAIGHT INTO THE PICTURE since 05.10.2026 („Das Text-Tool
     * gefällt mir noch nicht, können wir den Text direkt ins Bild schreiben
     * und ihn dann mit einer Box versehen, um die Schriftgröße zu ziehen,
     * und Rotation setzen?"). Until then a tap set the anchor and a window
     * asked for the words, which then appeared somewhere one was not
     * looking.
     *
     * THE LETTERS ARE DRAWN BY THE OVERLAY ITSELF, exactly as they will
     * stand — size, turn, colour, seam, zoom and pan all come for free —
     * and an invisible field holds the keys. It is a real <textarea> since
     * 07.10.2026 (several lines; before, an <input>), so the phone's
     * keyboard comes up, umlauts compose, Enter breaks the line and the
     * caret can be moved; textCaretPlace() draws the caret where the field
     * says it is. The field sits over the words, so a phone does not
     * scroll the page to find it.
     *
     * „Fertig" over the picture keeps the words, so does a step away to
     * another tool or picture. „Abbrechen", Escape and a press in the
     * picture beside them throw a new text away and give an old one its
     * words back (07.10.2026). A text written anew comes out chosen, its box ready for
     * size and turn, and the tool is put down — a label is seldom one of a
     * series the way spores are, and its box is the next thing one wants.
     */
    const textInput = document.getElementById('photo-text-input');

    /** An arrow's words' letter height as the number beside their square —
     *  what they show, the strength's share included. */
    function arrowWordsShown(words) {
        return Math.round(words.fs / (photoImageEl.naturalWidth || 1) * 1000);
    }

    /** The letter height as the number the box shows (TEXT_SIZES). */
    function textShown(m) {
        return Math.round(textSizeOf(m) / (photoImageEl.naturalWidth || 1) * 1000);
    }

    /** Begin writing: a new text at `at` (natural pixels), or into `m`. */
    function textEditStart(m, at, free = false) {
        textEditEnd(true);
        if (m === null) {
            /* THE SIZE OF THE LAST ONE SET, so a series of labels comes out
               alike without a second thought; the first ever takes the
               account's font size as it looks right now, put on its step. */
            const width = photoImageEl.naturalWidth;
            const kept = Number(settings.get('textSize', 0));
            const shown = TEXT_SIZES.includes(kept) ? kept
                : textStep(measureStyle.font_px * overlayHair / width * 1000);
            textEdit = { m: { type: 'text', points: [at], text: '', width,
                              size: Math.round(shown / 1000 * width * 10) / 10,
                              // Upright on the screen, however the view is turned.
                              // Mirrored, the turn counts backwards (viewUnmirror).
                              ...(pictureView?.R ? { angle: -pictureView.R * viewMirrorSign() } : {}) },
                         free: statsLabel === MEASURE_FREE, isNew: true, before: '' };
        } else {
            textEdit = { m, free, isNew: false, before: m.text ?? '', sizeBefore: m.size };
            /* AN ARROW'S FIRST WORDS take the size of the last text set,
               as a new text does — in the arrow's own pixel space. */
            if (m.type === 'arrow' && m.size == null) {
                const width = m.width || photoImageEl.naturalWidth;
                const kept = Number(settings.get('textSize', 0));
                const shown = TEXT_SIZES.includes(kept) ? kept
                    : textStep(measureStyle.font_px * overlayHair / photoImageEl.naturalWidth * 1000);
                m.size = Math.round(shown / 1000 * width * 10) / 10;
            }
        }
        textInput.value = textEdit.m.text ?? '';
        const frame = photoFrame.getBoundingClientRect();
        const [x, y] = textEdit.m.type === 'arrow'
            ? (() => { const w = arrowWords(textEdit.m); return [w.x, w.y]; })()
            : textBox(textEdit.m).anchor;
        const [cx, cy] = naturalClient([x, y]);
        textInput.style.left = (cx - frame.left) + 'px';
        textInput.style.top = (cy - frame.top) + 'px';
        textInput.hidden = false;
        textInput.focus({ preventScroll: true });
        textInput.setSelectionRange(textInput.value.length, textInput.value.length);
        textWritingHint();
        drawOverlay();
    }

    /* WHILE WRITING, THE LINE OVER THE PICTURE HOLDS THE WAY OUT
       (07.10.2026: „mach mir doch einen Fertig-Button … Mit Enter beenden
       will ich eigentlich nicht, weil man mehrzeilig schreiben soll …
       abbrechen über den Button oder außerhalb klicken"). Enter makes a
       new line; „Fertig" keeps the words, „Abbrechen" — or a click in the
       picture beside them — takes them back. */
    function textWritingHint() {
        const hint = document.getElementById('photo-frame-hint');
        hint.textContent = '';
        const say = document.createElement('span');
        say.textContent = t('Schreibe jetzt. Mit Enter beginnt eine neue Zeile.');
        hint.append(say);
        for (const [words, keep] of [[t('Fertig'), true], [t('Abbrechen'), false]]) {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = words;
            // The press must not take the focus from the words first.
            b.addEventListener('pointerdown', (e) => e.preventDefault());
            b.addEventListener('click', (e) => { e.stopPropagation(); textEditEnd(keep); });
            hint.append(b);
        }
        hint.hidden = false;
    }

    /** End the writing — keeping the words, or (keep false) undoing them. */
    function textEditEnd(keep) {
        const ed = textEdit;
        if (ed === null) return;
        textEdit = null;   // first: hiding the field blurs it, and blur ends here too
        textInput.hidden = true;
        textInput.blur();
        const written = (ed.m.text ?? '').trim();
        if (ed.isNew) {
            if (keep && written !== '') {
                ed.m.text = written;
                if (ed.free) {
                    freeMeasures.push(ed.m);
                } else {
                    ed.m.label = statsLabel;
                    if (!scaleMayEdit()) ed.m.temporary = true;
                    measures.push(ed.m);
                    measureSaveQueue();
                }
                document.getElementById('photo-measure-clear').hidden = false;
                measureEnd();
                measureSelected = { m: ed.m, free: ed.free };
            }
        } else if (ed.m.type === 'arrow') {
            /* AN ARROW'S WORDS: emptied, the arrow is an arrow again — its
               size and side go with them; taken back, all as it was. */
            if (keep) {
                if (written !== '') ed.m.text = written;
                else { delete ed.m.text; delete ed.m.size; }
                if (written !== ed.before && !ed.free) measureSaveQueue();
            } else {
                if (ed.before !== '') ed.m.text = ed.before;
                else delete ed.m.text;
                if (ed.sizeBefore == null) delete ed.m.size;
                else ed.m.size = ed.sizeBefore;
            }
        } else if (keep && written !== '') {
            ed.m.text = written;
            if (written !== ed.before && !ed.free) measureSaveQueue();
        } else {
            ed.m.text = ed.before;
        }
        calibrationHint(measureMode !== null ? measureModeHint(measureMode) : '');
        drawOverlay();
    }

    /** The caret where the field has it — measured on the drawn letters. */
    function textCaretPlace() {
        if (textEdit === null) return;
        const g = photoOverlay.querySelector('.photo-text-writing');
        const text = g?.querySelector('text');
        const caret = g?.querySelector('.photo-text-caret');
        if (!text || !caret) return;
        // Which line the caret stands in, and how far into it.
        const at = Math.min(textInput.selectionStart ?? textInput.value.length, textInput.value.length);
        const before = textInput.value.slice(0, at).split('\n');
        const row = before.length - 1, column = before[row].length;
        const span = text.querySelectorAll('tspan')[row];
        let dx = 0;
        try { dx = span && column > 0 ? span.getSubStringLength(0, column) : 0; } catch { /* the end, then */ }
        /* A LINE THAT ENDS OR CENTRES AT x (an arrow's words, 30.10.2026)
           begins left of it by its own length, or half of it. */
        const anchor = text.getAttribute('text-anchor');
        if (span && anchor !== 'start') {
            let len = 0;
            try { len = span.getComputedTextLength(); } catch { /* none, then */ }
            dx -= anchor === 'end' ? len : len / 2;
        }
        const fs = Number(text.getAttribute('font-size'));
        const x = Number(text.getAttribute('x')) + dx;
        const y = Number(text.getAttribute('y')) + row * fs * TEXT_LEADING;
        /* BOTH LINES OF IT, seam and stroke — the caret is a group, and an
           x1 on a <g> moves nothing (30.10.2026: „ein blinkender
           Cursor, der aber immer ganz links steht und nicht hinter dem
           Text"). Until then it stood where drawMeasure drew it, at the
           start of the line, whatever was typed. */
        for (const line of caret.querySelectorAll('line')) {
            line.setAttribute('x1', x);
            line.setAttribute('x2', x);
            line.setAttribute('y1', y - fs * 0.8);
            line.setAttribute('y2', y + fs * 0.2);
        }
    }

    /*
     * AN ARROW'S WORDS, OFFERED IN THE LINE OVER THE PICTURE (30.10.2026):
     * right after an arrow is set — the tool stays armed for the next — and
     * whenever an arrow is chosen. „Text dazu" opens the writing at its
     * tail, „Text ändern" opens its words again. Where they stand is not
     * asked: always on the arrow's line (measuredraw.js, arrowTextAt).
     * Rebuilt only when what it shows changed. It clears only what it wrote
     * itself, and gives the armed tool its sentence back.
     */
    function arrowHintSync() {
        const hint = document.getElementById('photo-frame-hint');
        const shown = hint.querySelector('.photo-arrow-words') !== null;
        const quiet = textEdit === null && measureMode === null && calibrating === null && frameRect === null;
        const armed = textEdit === null && measureMode === 'arrow' && measurePoints.length === 0
            && arrowJustSet !== null;
        const target = quiet && measureSelected?.m.type === 'arrow' ? measureSelected : armed ? arrowJustSet : null;
        if (target === null) {
            if (shown) calibrationHint(measureMode !== null && textEdit === null ? measureModeHint(measureMode) : '');
            arrowPanelFor = null;
            return;
        }
        const state = `${armed}|${target.m.text ?? ''}`;
        if (shown && arrowPanelFor?.m === target.m && arrowPanelFor.state === state) return;
        arrowPanelFor = { m: target.m, state };
        hint.textContent = '';
        const row = document.createElement('span');
        row.className = 'photo-arrow-words';
        if (armed) {
            const say = document.createElement('span');
            say.textContent = t('Pfeil gesetzt.');
            row.append(say);
        }
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = target.m.text ? t('Text ändern') : t('Text dazu');
        b.addEventListener('click', (e) => { e.stopPropagation(); textEditStart(target.m, null, target.free); });
        row.append(b);
        hint.append(row);
        hint.hidden = false;
    }

    /** The short word over the picture while a text is chosen: what its
     *  handles do. It clears only what it wrote itself. */
    function textHintSync() {
        const quiet = textEdit === null && measureMode === null && calibrating === null && frameRect === null;
        if (quiet && measureSelected?.m.type === 'text') {
            calibrationHint(t('Größer oder kleiner: am Quadrat ziehen. Drehen: am Kreis ziehen. Ändern: noch einmal auf den Text klicken.'));
            textHintShown = true;
        } else if (textHintShown) {
            textHintShown = false;
            if (quiet) calibrationHint('');
        }
    }

    /** One step of a drag on a chosen text: move, size (by steps) or turn. */
    function textDragTo(d, p) {
        const m = d.m;
        if (d.kind === 'text-move') {
            m.points[0] = [d.basePoint[0] + (p[0] - d.start[0]) / d.across,
                           d.basePoint[1] + (p[1] - d.start[1]) / d.across];
        } else if (d.kind === 'text-size') {
            // The start of the line stays put; the letters grow away from it.
            const width = m.width || photoImageEl.naturalWidth / d.across;
            const k = Math.hypot(p[0] - d.anchor[0], p[1] - d.anchor[1]) / d.startDist;
            const step = textStep(d.baseSize * k / width * 1000);
            m.size = Math.round(step / 1000 * width * 10) / 10;
            settings.set('textSize', step);
        } else {
            /* TURNED ABOUT THE MIDDLE OF ITS BOX, as the hand expects — the
               anchor swings round the middle by the same turn, so the box
               stays where it stood. Near the right angles it settles
               (angleSnapped). */
            const angle = angleSnapped(d.baseAngle + (Math.atan2(p[1] - d.centre[1], p[0] - d.centre[0])
                - d.startAngle) * 180 / Math.PI);
            const turn = (angle - d.baseAngle) * Math.PI / 180;
            const cos = Math.cos(turn), sin = Math.sin(turn);
            const [cx, cy] = d.centre, [ax, ay] = d.anchor;
            m.points[0] = [(cx + (ax - cx) * cos - (ay - cy) * sin) / d.across,
                           (cy + (ax - cx) * sin + (ay - cy) * cos) / d.across];
            if (angle === 0) delete m.angle;
            else m.angle = Math.round(angle * 10) / 10;
        }
    }

    /* --- The frame: pulled open, snapping, then counted in -------------------
     *
     * 06.10.2026: „ein Rechteck, das man zeichnen kann und wo man
     * die Maße angeben kann, wie groß es sein soll" — to count pores in a
     * square millimetre, or the lamellae along a centimetre of a cap margin.
     * Until 18.11.2026 the size was TYPED and a tap only said where.
     *
     * PULLED OPEN SINCE (18.11.2026: „einen frei aufziehbaren
     * Rahmen …, der aber einrastet. In entsprechenden Größen. Je kleiner,
     * desto kleiner das Raster, wenn man aufzieht. Wird er etwas größer,
     * können die Rasterschritte natürlich auch größer werden. Er schaut sich
     * dazu den Maßstab an … sieht … in der Nähe von der Maus die beiden
     * Längen … oder es wird einem auch angezeigt, wenn man ein Quadrat hat,
     * weil das Quadrat brauchen wir ja für Porenmessen. Ein Rechteck brauchen
     * wir eher für das Zählen der Lamellen"). The hand pulls, the sides
     * settle on round lengths — a step of a tenth of the longer side, from
     * the 1-2-5 series (boxSnap) —, and sides within one step of each other
     * become a square. Both lengths stand at the pointer while it pulls,
     * „Quadrat" with them. So the frame IS a round size, as the typed one
     * was, without typing.
     *
     * THEN COUNTED IN („erstmal den Rahmen definieren und dann im nächsten
     * Schritt darin Punkte anklicken, um zu zählen"): let go, the frame
     * comes out chosen and the tool goes down; a click inside it sets a
     * point, a click on a point takes it back (boxCount). A drag inside
     * still carries the frame, the circle above still turns it — along the
     * cut through half a cap, photographed a little askew. The points are
     * kept in the frame's own µm (boxGeometry, toLocal), so they go along.
     *
     * A FRAME NEEDS A SCALE — its millimetres are nothing without one. The
     * desk's button is locked without (scaleTopbarSync), and the phone's
     * menu, which locks nothing, arms a tool that says so instead of
     * drawing. */

    /** The round step for a side of `raw` µm: from 1, 2, 5 times a power of ten. */
    function boxNiceStep(raw) {
        const e = Math.pow(10, Math.floor(Math.log10(Math.max(raw, .1))));
        const f = raw / e;
        return (f < 1.5 ? 1 : f < 3.5 ? 2 : f < 7.5 ? 5 : 10) * e;
    }

    /** Two pulled sides in µm, settled: the step is a tenth of the longer, a near square is one. */
    function boxSnap(wRaw, hRaw) {
        const step = boxNiceStep(Math.max(wRaw, hRaw, 1) / 10);
        const round = (v) => Math.round(Math.min(BOX_MAX_UM, Math.max(step, Math.round(v / step) * step)) * 10) / 10;
        let w = round(wRaw), h = round(hRaw);
        const square = Math.abs(w - h) <= step + .05;
        if (square) w = h = round((w + h) / 2);
        return { w, h, square };
    }

    /** The unit a pulled frame is told in: millimetres once a side reaches one. */
    const boxUnitFor = (w, h) => (Math.max(w, h) >= 1000 ? 'mm' : 'um');

    /** The frame the pull so far stands for, or null: its middle (natural pixels), its sides in µm, its turn. */
    function boxPulled() {
        const umPerPx = photoUmPerPx();
        if (boxDraw === null || !boxDraw.moved || umPerPx === null) return null;
        /* UPRIGHT ON THE SCREEN, however the picture is turned or mirrored:
           the pull is read in the view's pixels (the same scale as the
           picture's), and the frame turned back by the view's turn. */
        const toView = pictureView ? pictureView.toView : (q) => q;
        const toNat = pictureView ? pictureView.toNat : (q) => q;
        const a = toView(boxDraw.start), b = toView(boxDraw.now);
        const dx = b[0] - a[0], dy = b[1] - a[1];
        const { w, h, square } = boxSnap(Math.abs(dx) * umPerPx, Math.abs(dy) * umPerPx);
        // The corner the pull began at stays; the frame grows from it the way the hand went.
        const centre = toNat([a[0] + Math.sign(dx || 1) * w / umPerPx / 2, a[1] + Math.sign(dy || 1) * h / umPerPx / 2]);
        const R = pictureView?.R ?? 0, sign = pictureView ? pictureView.fx * pictureView.fy : 1;
        let angle = Math.round(((-R * sign) % 360 + 540) % 360 * 10) / 10 - 180;
        if (Math.abs(angle) < .05) angle = 0;
        return { centre, um: [w, h], square, angle };
    }

    /** The frame being pulled, and its two lengths at the pointer — „Quadrat" with them where it is one. */
    function boxDrawSvg(fontSize) {
        const f = boxPulled();
        if (f === null) return '';
        const ghost = { type: 'box', points: [f.centre], um: f.um, width: photoImageEl.naturalWidth };
        if (f.angle) ghost.angle = f.angle;
        const g = boxGeometry(ghost);
        if (g === null) return '';
        const unit = boxUnitFor(...f.um);
        const words = boxNumber(f.um[0], unit) + ' × ' + boxNumber(f.um[1], unit) + (unit === 'mm' ? ' mm' : ' µm')
            + (f.square ? ' · ' + t('Quadrat') : '');
        const off = screenUnits(18);
        return svgSeamed('polygon', `points="${g.corners.map(q => q.join(',')).join(' ')}"`)
            + svgText(boxDraw.now[0] + off, boxDraw.now[1] + off * 1.4, words, fontSize);
    }

    /* PULLED WITH THE PRIMARY BUTTON, in the capture phase and before the
       zoom: with the tool on, a drag on the picture opens a frame instead
       of panning it — the held space bar and the middle button still pan. */
    photoFrame.addEventListener('pointerdown', (e) => {
        if (measureMode !== 'box' || e.button !== 0 || spacePan || photoChrome(e)) return;
        if (photoUmPerPx() === null) return;   // the line over the picture says why
        const p = naturalPoint(e);
        if (p === null) return;
        boxDraw = { id: e.pointerId, start: p, now: p, moved: false, x: e.clientX, y: e.clientY };
        try { photoFrame.setPointerCapture(e.pointerId); } catch { /* without, then */ }
        e.stopImmediatePropagation();
        e.preventDefault();
    }, true);
    photoFrame.addEventListener('pointermove', (e) => {
        if (boxDraw === null || boxDraw.id !== e.pointerId) return;
        const at = clientNatural(e.clientX, e.clientY);
        if (!at) return;
        if (!boxDraw.moved && Math.hypot(e.clientX - boxDraw.x, e.clientY - boxDraw.y) < 4) return;
        boxDraw.moved = true;
        boxDraw.now = at.p;
        drawOverlay();
        e.stopPropagation();
    }, true);
    const boxDrawEnd = (e) => {
        if (boxDraw === null || boxDraw.id !== e.pointerId) return;
        const f = e.type === 'pointerup' ? boxPulled() : null;
        boxDraw = null;
        boxDrawnAt = performance.now();
        if (f !== null) boxPlace(f);
        else drawOverlay();
        e.stopPropagation();
    };
    photoFrame.addEventListener('pointerup', boxDrawEnd, true);
    photoFrame.addEventListener('pointercancel', boxDrawEnd, true);

    /** A pulled frame becomes a measure of the layer on view: it comes out
     *  chosen and the tool goes down — the next thing one does is count in
     *  it, turn it into place or carry it, and those want the tool off. */
    function boxPlace(f) {
        const done = { type: 'box', points: [f.centre], um: f.um, unit: boxUnitFor(...f.um),
            width: photoImageEl.naturalWidth };
        if (f.angle) done.angle = f.angle;
        const free = statsLabel === MEASURE_FREE;
        if (free) {
            freeMeasures.push(done);
        } else {
            done.label = statsLabel;
            if (!scaleMayEdit()) done.temporary = true;
            measures.push(done);
            measureSaveQueue();
        }
        document.getElementById('photo-measure-clear').hidden = false;
        measureEnd();
        measureSelected = { m: done, free };
        drawOverlay();
        statsRefresh();
    }

    /**
     * A click at p inside the CHOSEN frame counts: a new point there, or —
     * on a point already set — that one taken back. False where the click
     * is not the frame's to count (no frame chosen, outside it, on its
     * turning circle or its ×, a frame one may not change).
     */
    function boxCount(p) {
        const sel = measureSelected;
        if (sel === null || sel.m.type !== 'box' || boxGrabAt(p) === 'rotate') return false;
        if (!sel.free && !sel.m.temporary && !scaleMayEdit()) return false;
        const g = boxGeometry(sel.m);
        if (g === null || !insidePolygon(p, g.corners)) return false;
        const list = sel.m.count ??= [];
        const reach = screenUnits(8);
        const near = list.findIndex((q) => {
            const [x, y] = g.fromLocal(q);
            return Math.hypot(x - p[0], y - p[1]) <= reach;
        });
        if (near >= 0) list.splice(near, 1);
        else list.push(g.toLocal(p).map((v) => Math.round(v * 10) / 10));
        if (list.length === 0) delete sel.m.count;
        if (!sel.free) {
            measureSaveQueue();
            statsRefresh();
        }
        drawOverlay();
        return true;
    }

    /** One step of a drag on a chosen box: carried, or turned about its
     *  middle. A carry starts only past a few screen pixels — below that
     *  it is a counting click (boxCount) — and says so with false. */
    function boxDragTo(d, p) {
        const m = d.m;
        if (d.kind === 'box-move') {
            if (!d.carrying && Math.hypot(p[0] - d.start[0], p[1] - d.start[1]) < screenUnits(4)) return false;
            d.carrying = true;
            m.points[0] = [d.basePoint[0] + (p[0] - d.start[0]) / d.across,
                           d.basePoint[1] + (p[1] - d.start[1]) / d.across];
            return true;
        }
        const angle = angleSnapped(d.baseAngle + (Math.atan2(p[1] - d.centre[1], p[0] - d.centre[0])
            - d.startAngle) * 180 / Math.PI);
        if (angle === 0) delete m.angle;
        else m.angle = Math.round(angle * 10) / 10;
        return true;
    }

    /** The line over the picture while the tool is on or a frame is chosen:
     *  what the hand does next, and — chosen — the frame's size and count. */
    function boxHintSync() {
        const hint = document.getElementById('photo-frame-hint');
        const shown = hint.querySelector('.photo-box-say') !== null;
        const quiet = textEdit === null && measureMode === null && calibrating === null && frameRect === null;
        const chosen = quiet && measureSelected?.m.type === 'box' ? measureSelected.m : null;
        if (measureMode !== 'box' && chosen === null) {
            if (shown) calibrationHint('');
            return;
        }
        /* ONLY HOW, NEVER HOW MANY (18.11.2026: „Eine Statistik in
           dem Overlay über dem Bild brauche ich nicht. Dort kommen nur
           Hinweise rein, wie man mit dem Tool umgeht"). Size, count and
           density stand in the statistics. */
        const words = chosen !== null
            ? t('Klicke in den Rahmen, um zu zählen; ein Klick auf einen Punkt nimmt ihn zurück. Drehen am Kreis, verschieben durch Ziehen.')
            : photoUmPerPx() === null
                ? t('Für den Rahmen braucht das Bild zuerst einen Maßstab.')
                : !scaleMayEdit() && statsLabel !== MEASURE_FREE
                    ? t('Ziehe den Rahmen auf. Er rastet in runden Maßen ein.') + ' ' + t('Das gilt nur für dich und wird nicht gespeichert.')
                    : t('Ziehe den Rahmen auf. Er rastet in runden Maßen ein.');
        hint.textContent = '';
        const say = document.createElement('span');
        say.className = 'photo-box-say';
        say.textContent = words;
        hint.append(say);
        hint.hidden = false;
    }

    /* --- The sector: the lamellae that reach the stipe ----------------------
     *
     * 18.11.2026, after the frame: „Zentrum, lange Seite, kurze
     * Seite … Dann zeigt Yasai Map das Rechteck an. Also das komplette
     * Rechteck, was sich daraus ergibt. Dadurch kann man dann nochmal
     * kontrollieren, wie genau man geklickt hat … Das Rechteck selbst lässt
     * sich dann anfassen. Ein Klick auf die Mitte, aufs Zentrum, aktiviert
     * das eigentliche Sektorenzeichnen … eine Linie, die ich an eine Stelle
     * an den Außenrand des Hutes setze und ziehe dann ein Tortenstück auf.
     * Mit dem zweiten Klick setze ich das Tortenstück. Dann kann ich in
     * diesem Tortenstück zählen."
     *
     * THE CAP IS AN ELLIPSE. Photographed from straight above its rim is a
     * circle, and a slice of it is a true share of the round; photographed
     * askew the circle is an ellipse, and an angle in the picture is
     * squeezed along the short axis and stretched along the long one — at
     * 30° tilt the count taken round would be up to 9 % off. So the three
     * taps — the middle, the rim in the long direction, the rim in the
     * short one — make the ellipse, drawn whole with the rectangle about
     * it to check the taps against the cap, and every angle is read as it
     * would be seen from above: the ellipse stretched back into a circle
     * (param, rim). The slice and its count live in that „from above" too
     * (arc, in degrees), so taken round it is the cap's whole.
     *
     * WHAT IT CANNOT MEND: a strong perspective (the far half of the cap
     * smaller than the near one) and a domed cap are no ellipse; past some
     * 35° of tilt the statistics say the number is rough (statsCountView).
     *
     * The handles: the middle carries the whole ellipse, the two rim points
     * reshape it, the circle beyond the far end of the long axis turns it,
     * and a drag inside the ellipse beside the slice (or anywhere in it
     * before there is a slice) carries it — the counted points travel
     * along. 18.11.2026: „die Ellipse braucht dann aber noch
     * einen Rotationsgriff … verschieben … auf dem negativen Tortenstück
     * … wenn man noch kein Tortenstück ausgewählt hat, dann überall". The
     * two points on the slice's edges move its angles afterwards („fein
     * justieren … Punkte, die dann beim Korrigieren rausfallen, werden
     * gelöscht") — dropped when the hand lets go, not on the way, so a
     * slice pulled too narrow and back loses nothing (sectorDragTo,
     * sectorPrune). A click on the middle starts the slice:
     * the line to the rim follows the pointer, a click sets where the slice
     * begins, the slice follows the pointer round, a second click sets it.
     * Then a click inside counts, a click on a point takes it back; a click
     * on the middle sets the slice anew, and the points outside the new one
     * fall away. */

    /** The measure's taps in this picture's pixels. */
    const sectorTaps = (m) => {
        const across = measureAcross(m);
        return m.points.map(([x, y]) => [x * across, y * across]);
    };

    /** The ellipse of a sector in this picture's pixels (measurestats.js, sectorShape). */
    const sectorGeometry = (m, taps = sectorTaps(m)) => {
        const g = sectorShape(taps);
        if (g === null) return null;
        /* The stem, and where a point lies as the lamellae see it: the rim's
           angle on the line from the stem through it, and how far out it is
           on the cap (measurestats.js, sectorRimT). */
        g.stem = sectorStemOf(m, g, measureAcross(m));
        g.at = (q) => ({ t: sectorRimT(g, g.stem, q), r: g.param(q).r });
        return g;
    };

    /* The two kinds of points, their names and colours, the slice's
       arithmetic and the means live in measurestats.js since 18.11.2026 —
       the plate draws and sums the same sectors (plateparts.js). */
    const sectorKind = () => (settings.get('sectorKind', 'count') === 'lamellulae' ? 'lamellulae' : 'count');

    /* A sector as the overlay draws it: the ellipse, the slice, the counted
       points. NO WORDS IN THE PICTURE and NO RECTANGLE, NOT EVEN WHILE
       CHOSEN (18.11.2026, first „ich will nur noch eine Ellipse
       sehen und darin das Tortenstück" for the sector at rest, then the
       same day „mach mir komplett das Rechteck außenrum weg … Auch bei
       aktiven"; the dashed half axes had gone before, „die stört nur").
       The handles on the ellipse show where one tapped; the numbers stand
       in the line over the picture and in the statistics. */
    function sectorSvg(m) {
        const g = sectorGeometry(m);
        if (g === null) return '';
        const deg = g.theta * 180 / Math.PI;
        const rim = sectorRimArc(m);
        /* The rim that carries lamellae drawn as the ellipse is; the stretch
           where none arrive — where an oyster mushroom sits on its wood —
           dashed and faint: part of the outline, no rim (sectorRimArc). */
        let out = rim === null
            ? svgSeamed('ellipse', `cx="${g.c[0]}" cy="${g.c[1]}" rx="${g.a}" ry="${g.b}" transform="rotate(${deg} ${g.c[0]} ${g.c[1]})" fill="none"`)
            : svgSeamed('path', `d="${sectorRimPath(g, rim[0], rim[1])}" fill="none"`)
                + `<path d="${sectorRimPath(g, rim[1], rim[1] >= rim[0] ? rim[0] + 2 * Math.PI : rim[0] - 2 * Math.PI)}" fill="none"
                    stroke="${measureStyle.line_color}" stroke-width="${overlayHair}" stroke-dasharray="${4 * overlayHair} ${3 * overlayHair}" opacity=".6" pointer-events="none"/>`;
        const arc = sectorArc(m);
        if (arc !== null) {
            // The whole cap counted (18.11.2026): no slice — the ellipse is it.
            if (!sectorWhole(m)) {
                out += `<path d="${sectorSlicePath(g, arc[0], arc[1])}" fill="${measureStyle.line_color}" fill-opacity=".14"
                        stroke="none" pointer-events="none"/>`
                    + svgSeamed('path', `d="${sectorSlicePath(g, arc[0], arc[1])}" fill="none"`);
            }
            const across = measureAcross(m), r = screenUnits(4.5);
            for (const kind of SECTOR_KINDS) {
                for (const [x, y] of (m[kind.key] ?? [])) {
                    out += `<circle cx="${x * across}" cy="${y * across}" r="${r}" fill="${kind.colour}" fill-opacity=".95"
                            stroke="rgba(0, 0, 0, .55)" stroke-width="${screenUnits(1.2)}" pointer-events="none"/>`;
                }
            }
        }
        return out;
    }

    /** The taps set so far while the tool is on: the ellipse grows with them. */
    function sectorSettingSvg() {
        if (measureMode !== 'sector' || measurePoints.length === 0) return '';
        const hover = measureHover;
        if (measurePoints.length === 1 && hover) {
            const [c] = measurePoints;
            return svgSeamed('line', `x1="${c[0]}" y1="${c[1]}" x2="${hover[0]}" y2="${hover[1]}"`);
        }
        if (measurePoints.length === 2 && hover) {
            const g = sectorGeometry({ points: [...measurePoints, hover] }, [...measurePoints, hover]);
            if (g === null) return '';
            return svgSeamed('ellipse', `cx="${g.c[0]}" cy="${g.c[1]}" rx="${g.a}" ry="${g.b}" transform="rotate(${g.theta * 180 / Math.PI} ${g.c[0]} ${g.c[1]})" fill="none"`);
        }
        return '';
    }

    /** The three taps become a sector of the layer on view; it comes out chosen, the tool goes down. */
    function sectorPlace(taps) {
        const done = { type: 'sector', points: taps, width: photoImageEl.naturalWidth };
        if (sectorGeometry(done) === null) { drawOverlay(); return; }
        const free = statsLabel === MEASURE_FREE;
        if (free) {
            freeMeasures.push(done);
        } else {
            done.label = statsLabel;
            if (!scaleMayEdit()) done.temporary = true;
            measures.push(done);
            measureSaveQueue();
        }
        document.getElementById('photo-measure-clear').hidden = false;
        measureEnd();
        measureSelected = { m: done, free };
        sectorAim = null;
        drawOverlay();
        statsRefresh();
    }

    /** The chosen sector's dress: its three handles, its ×, and while a slice is set the slice following the pointer. */
    function drawSectorSelection(m) {
        rotateHandles = null;
        const g = sectorGeometry(m);
        if (g === null) { pointHandles = null; removeAnchor = null; return ''; }
        pointHandles = null;
        const r = screenUnits(5.5), hair = screenUnits(1);
        const h = sectorHandles(m, g);
        let inner = svgSeamed('line', `x1="${h.foot[0]}" y1="${h.foot[1]}" x2="${h.rotate[0]}" y2="${h.rotate[1]}"`);
        // The stem's handle where the middle's stood: it starts the slice, and dragged it goes where the lamellae meet.
        for (const [x, y] of [g.stem, g.rim(0), g.short, h.rotate]) {
            inner += `<circle cx="${x}" cy="${y}" r="${r}" fill="${measureStyle.line_color}"
                    stroke="${measureStyle.seam_color}" stroke-width="${hair}"/>`;
        }
        // The slice's edge points hollow: they change the angle, not the cap.
        if (sectorAim === null) {
            for (const q of [h.from, h.to]) {
                if (!q) continue;
                inner += `<circle cx="${q[0]}" cy="${q[1]}" r="${screenUnits(4.5)}" fill="${measureStyle.seam_color}"
                        stroke="${measureStyle.line_color}" stroke-width="${screenUnits(2)}"/>`;
            }
        }
        if (sectorAim !== null && sectorAim.m === m && sectorHover !== null) {
            const { t: now } = g.at(sectorHover);
            if (sectorAim.stage === 'rim-from') {
                const [x, y] = g.rim(now);
                inner += `<circle cx="${x}" cy="${y}" r="${screenUnits(5)}" fill="none" stroke="${measureStyle.line_color}" stroke-width="${screenUnits(2)}"/>`;
            } else if (sectorAim.stage === 'rim-to') {
                inner += svgSeamed('path', `d="${sectorRimPath(g, sectorAim.from, sectorAim.from + sectorAim.sweep)}" fill="none"`);
            } else if (sectorAim.stage === 'stem') {
                // The stem follows the hand until it is set.
            } else if (sectorAim.stage === 'from') {
                const [x, y] = g.rim(now);
                inner += svgSeamed('line', `x1="${g.stem[0]}" y1="${g.stem[1]}" x2="${x}" y2="${y}"`);
            } else {
                const to = sectorAim.from + sectorAim.sweep;
                inner += `<path d="${sectorSlicePath(g, sectorAim.from, to)}" fill="${measureStyle.line_color}" fill-opacity=".14" stroke="none"/>`
                    + svgSeamed('path', `d="${sectorSlicePath(g, sectorAim.from, to)}" fill="none"`);
            }
        }
        return inner + drawRemoveAt(g.corners[1]);
    }

    /* Where the chosen sector's handles stand: the turning circle on a stem
       beyond the FAR end of the long axis (the near end is the long rim's
       own handle, and the × sits off that corner), the slice's two edge
       points at four fifths of the way to the rim — on the edge line, clear
       of the rim handles, which may lie on the same angle. */
    const SECTOR_EDGE_AT = .8;
    function sectorHandles(m, g) {
        const out = { foot: g.rim(Math.PI),
            rotate: [g.c[0] - g.u[0] * (g.a + screenUnits(26)), g.c[1] - g.u[1] * (g.a + screenUnits(26))] };
        const arc = sectorArc(m);
        if (arc !== null && Math.abs(arc[1] - arc[0]) < 2 * Math.PI - 1e-6) {
            // On the edge lines, which run from the stem.
            const along = (t) => { const q = g.rim(t); return [g.stem[0] + (q[0] - g.stem[0]) * SECTOR_EDGE_AT, g.stem[1] + (q[1] - g.stem[1]) * SECTOR_EDGE_AT]; };
            out.from = along(arc[0]);
            out.to = along(arc[1]);
        }
        return out;
    }

    /** Which part of the chosen sector a point lands on: 'rotate', 'stem', 'long', 'short', 'from', 'to', 'move' — or null. */
    function sectorGrabAt(p) {
        const m = measureSelected?.m;
        if (m?.type !== 'sector') return null;
        const g = sectorGeometry(m);
        if (g === null) return null;
        const reach = screenUnits(10);
        const near = (q) => q !== undefined && Math.hypot(p[0] - q[0], p[1] - q[1]) <= reach;
        const h = sectorHandles(m, g);
        if (near(h.rotate)) return 'rotate';
        if (near(g.stem)) return 'stem';
        if (near(g.rim(0))) return 'long';
        if (near(g.short)) return 'short';
        if (near(h.from)) return 'from';
        if (near(h.to)) return 'to';
        /* Inside the ellipse beside the slice — or anywhere in it before
           there is one, or when the whole cap is counted — the hand
           carries it. There a click counts and a drag moves. */
        const { t: at, r } = g.at(p);
        const arc = sectorArc(m);
        if (r <= 1.02 && (arc === null || sectorWhole(m) || !sectorInside(at, arc[0], arc[1]))) return 'move';
        return null;
    }

    /** An angle brought into (-π, π]. */
    const sectorWrap = (v) => Math.atan2(Math.sin(v), Math.cos(v));

    /** One step of a drag on a sector's handle; false while it is still a click (a few screen pixels). */
    function sectorDragTo(d, p, whole = false) {
        if (!d.dragging && Math.hypot(p[0] - d.start[0], p[1] - d.start[1]) < screenUnits(4)) return false;
        d.dragging = true;
        const dx = (p[0] - d.start[0]) / d.across, dy = (p[1] - d.start[1]) / d.across;
        const [c, a, b] = d.base;
        // The counted points belong to the cap: they travel and turn with it.
        const carry = (f) => {
            for (const { key } of SECTOR_KINDS) {
                if (d.baseCount[key]) d.m[key] = d.baseCount[key].map(f);
            }
        };
        if (d.kind === 'sector-stem') {
            // Where the lamellae run together — inside the cap or at its rim (Crepidotus); the ellipse stays.
            const s0 = d.baseStem ?? c;
            d.m.stem = [Math.round((s0[0] + dx) * 10) / 10, Math.round((s0[1] + dy) * 10) / 10];
        } else if (d.kind === 'sector-middle' || d.kind === 'sector-move') {
            const shift = ([x, y]) => [Math.round((x + dx) * 10) / 10, Math.round((y + dy) * 10) / 10];
            d.m.points = [[c[0] + dx, c[1] + dy], [a[0] + dx, a[1] + dy], [b[0] + dx, b[1] + dy]];
            carry(shift);
            if (d.baseStem) d.m.stem = shift(d.baseStem);
        } else if (d.kind === 'sector-rotate') {
            // Round the middle, by the angle the hand has gone round it; the slice lives in the cap's own angles and turns along.
            const cx = c[0] * d.across, cy = c[1] * d.across;
            const delta = Math.atan2(p[1] - cy, p[0] - cx) - Math.atan2(d.start[1] - cy, d.start[0] - cx);
            const cos = Math.cos(delta), sin = Math.sin(delta);
            const turn = ([x, y]) => [c[0] + (x - c[0]) * cos - (y - c[1]) * sin, c[1] + (x - c[0]) * sin + (y - c[1]) * cos];
            d.m.points = [[c[0], c[1]], turn(a), turn(b)];
            carry((q) => turn(q).map((v) => Math.round(v * 10) / 10));
            if (d.baseStem) d.m.stem = turn(d.baseStem).map((v) => Math.round(v * 10) / 10);
        } else if (d.kind === 'sector-from' || d.kind === 'sector-to') {
            /* An edge of the slice follows the hand round, counted on from
               where it was so a pass over the seam at ±180° is no jump. The
               slice keeps the way it was swept, at least a degree, at most
               the whole round. */
            const g = sectorGeometry(d.m);
            if (g === null) return true;
            const { t: now } = g.at(p);
            d.value += sectorWrap(now - d.last);
            d.last = now;
            let [from, to] = d.baseArc;
            const sign = to >= from ? 1 : -1, least = Math.PI / 180;
            if (d.kind === 'sector-from') {
                const span = Math.min(2 * Math.PI, Math.max(least, (to - d.value) * sign));
                from = to - span * sign;
            } else {
                const span = Math.min(2 * Math.PI, Math.max(least, (d.value - from) * sign));
                to = from + span * sign;
            }
            d.m.arc = [from, to].map((v) => Math.round(v * 180 / Math.PI * 100) / 100);
            if (d.wasWhole) d.m.rim = [...d.m.arc];
        } else {
            /* THE SHORT HANDLE MOVES WHERE IT IS DRAWN (18.11.2026:
               „Die Anfasspunkte der Ellipse gehen genau spiegelverkehrt. Fahre
               ich zum Zentrum der Ellipse, wird die Ellipse größer"). The
               short axis is the third tap's distance ACROSS the long one,
               without a side (sectorShape), and its handle is drawn on one
               side of it; a tap that lay on the other side was dragged the
               other way. Now the handle's own place is what moves.

               WITH SHIFT THE WHOLE ELLIPSE (the same day: „bei gehaltener
               Shift-Taste beide Achsen vergrößern und verkleinern, also die
               komplette Ellipse skalieren"): either rim handle scales both
               axes by how far the hand has gone from the middle — the shape
               and the turn stay. The counted points stay where they lie on
               the cap; those the smaller ellipse leaves go on letting go. */
            const g = sectorShape(d.base);
            const sb = g === null ? b : g.short;
            if (whole && g !== null) {
                const cx = c[0] * d.across, cy = c[1] * d.across;
                const from = Math.hypot(d.start[0] - cx, d.start[1] - cy) || 1;
                const k = Math.max(.05, Math.hypot(p[0] - cx, p[1] - cy) / from);
                const scale = (q) => [c[0] + (q[0] - c[0]) * k, c[1] + (q[1] - c[1]) * k];
                d.m.points = [[c[0], c[1]], scale(a), scale(sb)];
            } else if (d.kind === 'sector-long') {
                d.m.points = [[c[0], c[1]], [a[0] + dx, a[1] + dy], [sb[0], sb[1]]];
            } else {
                d.m.points = [[c[0], c[1]], [a[0], a[1]], [sb[0] + dx, sb[1] + dy]];
            }
        }
        return true;
    }

    /**
     * A click with a sector chosen: on its middle it starts the slice; while
     * the slice is set, it sets where it begins and then where it ends;
     * with the slice set, inside it a click counts — a new point, or one
     * taken back. False where the click is not the sector's.
     */
    function sectorClick(p) {
        const sel = measureSelected;
        if (sel === null || sel.m.type !== 'sector') return false;
        if (!sel.free && !sel.m.temporary && !scaleMayEdit()) return false;
        const m = sel.m, g = sectorGeometry(m);
        if (g === null) return false;
        const { t: at, r } = g.at(p);
        // A click on the turning circle turns nothing — but must not let the sector go either.
        if (sectorAim === null && sectorGrabAt(p) === 'rotate') return true;
        if (sectorAim !== null && sectorAim.m === m) {
            /* THE ECCENTRIC STEM, set in three clicks (sectorModes): where the
               lamellae meet, then the rim that carries them, from where they
               begin round to where they end. */
            if (sectorAim.stage === 'stem') {
                const across = measureAcross(m);
                m.stem = [Math.round(p[0] / across * 10) / 10, Math.round(p[1] / across * 10) / 10];
                sectorAim = { m, free: sel.free, stage: 'rim-from' };
                drawOverlay();
                return true;
            }
            if (sectorAim.stage === 'rim-from') {
                sectorAim = { m, free: sel.free, stage: 'rim-to', from: at, last: at, sweep: 0 };
                drawOverlay();
                return true;
            }
            if (sectorAim.stage === 'rim-to') {
                const from = sectorAim.from, to = from + sectorAim.sweep;
                sectorAim = null;
                if (Math.abs(to - from) < .01) { drawOverlay(); return true; }
                m.rim = [from, to].map((v) => Math.round(v * 180 / Math.PI * 100) / 100);
                sectorPrune(m);
                sectorSave(sel);
                drawOverlay();
                return true;
            }
            if (sectorAim.stage === 'from') {
                sectorAim = { m, free: sel.free, stage: 'to', from: at, last: at, sweep: 0 };
            } else {
                const from = sectorAim.from, to = from + sectorAim.sweep;
                sectorAim = null;
                if (Math.abs(to - from) < .01) { drawOverlay(); return true; }
                m.arc = [from, to].map((v) => Math.round(v * 180 / Math.PI * 100) / 100);
                sectorPrune(m);
                sectorSave(sel);
            }
            drawOverlay();
            return true;
        }
        if (Math.hypot(p[0] - g.stem[0], p[1] - g.stem[1]) <= screenUnits(10)) {
            sectorAim = { m, free: sel.free, stage: 'from' };
            sectorHover = p;
            drawOverlay();
            return true;
        }
        if (!sectorHolds(m, g, p)) return false;
        const across = measureAcross(m), reach = screenUnits(8);
        const hitAt = (list) => (list ?? []).findIndex(([x, y]) => Math.hypot(x * across - p[0], y * across - p[1]) <= reach);
        // A click on a point of either kind takes it back; elsewhere a new one of the kind chosen over the picture.
        const taken = SECTOR_KINDS.find(({ key }) => hitAt(m[key]) >= 0);
        if (taken) {
            m[taken.key].splice(hitAt(m[taken.key]), 1);
            if (m[taken.key].length === 0) delete m[taken.key];
        } else {
            (m[sectorKind()] ??= []).push([Math.round(p[0] / across * 10) / 10, Math.round(p[1] / across * 10) / 10]);
        }
        sectorSave(sel);
        drawOverlay();
        return true;
    }

    /** Does the sector count at q: on the cap, within the slice, and on the rim that carries lamellae where one was set. */
    function sectorHolds(m, g, q) {
        const arc = sectorArc(m), rim = sectorRimArc(m);
        if (arc === null) return false;
        const { t: at, r } = g.at(q);
        return r <= 1.02 && sectorInside(at, arc[0], arc[1]) && (rim === null || sectorInside(at, rim[0], rim[1]));
    }

    /** What the slice no longer holds is no longer counted: points outside its angles or past the rim go. */
    function sectorPrune(m) {
        const g = sectorGeometry(m);
        if (g === null || sectorArc(m) === null) return;
        const across = measureAcross(m);
        for (const { key } of SECTOR_KINDS) {
            if (!m[key]) continue;
            m[key] = m[key].filter(([x, y]) => sectorHolds(m, g, [x * across, y * across]));
            if (m[key].length === 0) delete m[key];
        }
    }

    function sectorSave(sel) {
        if (sel.free) return;
        measureSaveQueue();
        statsRefresh();
    }

    /* WHILE THE TAPS ARE SET the ellipse follows the pointer: after the
       middle a line to it, after the long side the whole ellipse with the
       pointer as its short side (sectorSettingSvg). The four-click tools'
       own pointer is only kept for them, so the sector keeps its own. */
    let sectorQueued = false;
    photoFrame.addEventListener('pointermove', (e) => {
        if (measureMode !== 'sector' || measurePoints.length === 0 || e.pointerType === 'touch') return;
        const at = clientNatural(e.clientX, e.clientY);
        if (!at) return;
        measureHover = at.p;
        if (sectorQueued) return;
        sectorQueued = true;
        requestAnimationFrame(() => { sectorQueued = false; drawOverlay(); });
    });

    /* THE SLICE FOLLOWS THE POINTER while it is set: round the middle, the
       way the hand goes and as far — past half the cap, all the way round —,
       read as seen from above. */
    photoFrame.addEventListener('pointermove', (e) => {
        if (sectorAim === null) return;
        const at = clientNatural(e.clientX, e.clientY);
        if (!at) return;
        sectorHover = at.p;
        if (sectorAim.stage === 'to' || sectorAim.stage === 'rim-to') {
            const g = sectorGeometry(sectorAim.m);
            if (g !== null) {
                const now = g.at(at.p).t;
                let step = now - sectorAim.last;
                if (step > Math.PI) step -= 2 * Math.PI;
                if (step < -Math.PI) step += 2 * Math.PI;
                sectorAim.sweep = Math.max(-2 * Math.PI, Math.min(2 * Math.PI, sectorAim.sweep + step));
                sectorAim.last = now;
            }
        }
        drawOverlay();
    });

    /** The line over the picture while a sector is chosen: what the hand does next, and what it has counted. */
    function sectorHintSync() {
        const hint = document.getElementById('photo-frame-hint');
        const shown = hint.querySelector('.photo-sector-say') !== null;
        const quiet = textEdit === null && measureMode === null && calibrating === null && frameRect === null;
        const m = quiet && measureSelected?.m.type === 'sector' ? measureSelected.m : null;
        if (m === null) {
            if (sectorAim !== null) sectorAim = null;
            if (shown) calibrationHint('');
            return;
        }
        const counting = sectorAim === null && Array.isArray(m.arc);
        const whole = counting && sectorWhole(m);
        const eccentric = Array.isArray(m.stem) || Array.isArray(m.rim);
        const stage = sectorAim?.stage;
        const words = stage === 'stem'
            ? t('Klicke auf den Stielansatz, dort wo die Lamellen zusammenlaufen.')
            : stage === 'rim-from'
                ? t('Klicke auf den Hutrand, wo die Lamellen anfangen.')
                : stage === 'rim-to'
                    ? t('Fahre am Rand entlang bis dorthin, wo die Lamellen aufhören, und klicke.')
                    : stage === 'from'
            ? t('Klicke auf den Hutrand, wo das Teilstück beginnt.')
            : stage === 'to'
                ? t('Fahre am Rand entlang bis zum Ende des Teilstücks und klicke.')
                : !counting
                    ? t('Prüfe die Ellipse am Hutrand und verschiebe die Punkte, wenn nötig; mit gedrückter Umschalttaste wächst oder schrumpft die ganze Ellipse. Drehen am Kreis, verschieben durch Ziehen in der Ellipse.')
                    : whole
                        ? t('Klicke in den Hut, um zu zählen; ein Klick auf einen Punkt nimmt ihn zurück. Drehen am Kreis, verschieben durch Ziehen in der Ellipse.')
                        : t('Klicke in das Teilstück, um zu zählen; ein Klick auf einen Punkt nimmt ihn zurück. Die Kanten des Teilstücks lassen sich an ihren Punkten ziehen. Drehen am Kreis, verschieben durch Ziehen neben dem Teilstück. Ein Klick auf den Stielansatz setzt das Teilstück neu.');
        hint.textContent = '';
        const say = document.createElement('span');
        say.className = 'photo-sector-say';
        /* THE WHOLE CAP OR A PART OF IT (18.11.2026: „Ganzer Hut
           würde bedeuten, wir lassen das Tortenstück Zeichnen weg … auch bei
           einem normalen Hut … wo wir den Stiel abgeschnitten haben. Dann
           haben wir ja auch den ganzen Hut, wo wir zählen können" — and the
           words: „Teilstück zählen oder komplett zählen"). The whole cap is a
           slice of the full round, counted as it is, taken round by nothing;
           a part is set from the stem as before. The one in use is filled;
           „Teilstück zählen" pressed again sets the part anew. */
        const sel = measureSelected;
        if (sectorAim === null && (sel.free || sel.m.temporary || scaleMayEdit())) {
            const modes = document.createElement('span');
            modes.className = 'photo-sector-kinds';
            const stems = document.createElement('span');
            stems.className = 'photo-sector-kinds';
            const modeButton = (words, on, act, into = modes) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.textContent = words;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', String(on));
                b.addEventListener('click', (e) => {
                    e.stopPropagation();
                    act();
                    drawOverlay();
                    sectorHintSync();
                });
                into.append(b);
            };
            /* THE STEM IN THE MIDDLE OR OFF IT (18.11.2026:
               „Ellipse aufziehen, dann wählen Stiel exzentrisch oder Stiel
               zentral. Bei zentral machen wir unseren normalen Ablauf. Bei
               Stiel exzentrisch setzen wir Stielansatz. Und Hutrand von wo
               bis wo. So dass wir eben ein Stück aussparen"). Eccentric asks
               three clicks (sectorClick: stem, rim-from, rim-to); central
               takes stem and rim off again, and what they held is pruned. */
            modeButton(t('Stiel zentral'), !eccentric, () => {
                // The whole rim counted stays the whole cap; a part stays a part, and what it no longer holds goes.
                const wasWhole = sectorWhole(m);
                delete m.stem;
                delete m.rim;
                if (wasWhole) m.arc = [0, 360];
                else sectorPrune(m);
                sectorSave(sel);
            }, stems);
            modeButton(t('Stiel exzentrisch'), eccentric, () => {
                sectorAim = { m, free: sel.free, stage: 'stem' };
                sectorHover = null;
            }, stems);
            hint.append(stems);
            /* The whole cap is the slice that is the rim — the full round
               where the stem is central; counted as it is, taken round by
               nothing. A part is set from the stem as before. */
            if (!eccentric || Array.isArray(m.rim)) {
                modeButton(t('Ganzen Hut zählen'), whole, () => {
                    m.arc = Array.isArray(m.rim) ? [...m.rim] : [0, 360];
                    sectorPrune(m);
                    sectorSave(sel);
                });
                modeButton(t('Teilstück zählen'), counting && !whole, () => {
                    sectorAim = { m, free: sel.free, stage: 'from' };
                    sectorHover = null;
                });
                hint.append(modes);
            }
        }
        /* WHAT THE NEXT CLICK COUNTS, in front while one counts: two words
           as the hint's other buttons are, the chosen one filled; the
           choice is kept on this device for the next sector. */
        if (counting) {
            const row = document.createElement('span');
            row.className = 'photo-sector-kinds';
            for (const kind of SECTOR_KINDS) {
                const b = document.createElement('button');
                b.type = 'button';
                const dot = document.createElement('span');
                dot.className = 'photo-sector-dot';
                dot.style.background = kind.colour;
                b.append(dot, kind.name());
                const on = kind.key === sectorKind();
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', String(on));
                b.addEventListener('click', (e) => {
                    e.stopPropagation();
                    settings.set('sectorKind', kind.key);
                    sectorHintSync();
                });
                row.append(b);
            }
            hint.append(row);
        }
        say.append(words);
        hint.append(say);
        hint.hidden = false;
    }

    /** A sector's tile in the statistics: the cap upright, its ellipse, the slice and the points. */
    function sectorTile(r, img, H) {
        const m = r.m;
        const k = m.width ? img.naturalWidth / m.width : 1;
        const taps = m.points.map(([x, y]) => [x * k, y * k]);
        const g = sectorGeometry(m, taps);
        if (g === null || !img.naturalWidth) return null;
        // The picture as it lies, as the frames' tiles (statsCountThumbs): the box about the ellipse in its directions.
        const ex = Math.hypot(g.a * g.u[0], g.b * g.n[0]), ey = Math.hypot(g.a * g.u[1], g.b * g.n[1]);
        const W = Math.max(24, Math.min(H * 3, Math.round(H * ex / ey)));
        const s = Math.min(W / (2 * ex * 1.06), H / (2 * ey * 1.06));
        const canvas = document.createElement('canvas');
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.round(W * dpr);
        canvas.height = Math.round(H * dpr);
        canvas.style.width = W + 'px';
        canvas.style.height = H + 'px';
        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        ctx.translate(W / 2, H / 2);
        ctx.scale(s, s);
        ctx.translate(-g.c[0], -g.c[1]);
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, 0, 0);
        ctx.lineWidth = 1.5 / s;
        ctx.strokeStyle = measureStyle.line_color;
        // The tile's own pixels: the stem in them too, and no slice where the whole cap was counted.
        g.stem = sectorStemOf(m, g, k);
        ctx.stroke(new Path2D(sectorSlicePath(g, ...(sectorArc(m) ?? [0, 2 * Math.PI]))));
        ctx.lineWidth = 1 / s;
        ctx.strokeStyle = 'rgba(0, 0, 0, .55)';
        for (const kind of SECTOR_KINDS) {
            ctx.fillStyle = kind.colour;
            for (const [x, y] of (m[kind.key] ?? [])) {
                ctx.beginPath();
                ctx.arc(x * k, y * k, 3 / s, 0, Math.PI * 2);
                ctx.fill();
                ctx.stroke();
            }
        }
        return canvas;
    }

    /* --- The mark of a measure -------------------------------------------------
     *
     * 07.10.2026: a chrysocystidium is a pleurocystidium AND a
     * chrysocystidium, and with the layer alone he had to choose which
     * statistic it counts in. Now the layer stays the place and the MARK
     * says the kind (measureMark in measurestats.js).
     *
     * ONE PLACE FOR IT, THE LINE OVER THE PICTURE (his word on the draft:
     * „als Overlay dauerhaft bei der Messung ins Bild legen … sonst haben
     * wir genau das gleiche an zwei Stellen"). While the rectangle or the
     * cutout is armed, the line sets the mark of the NEXT measure; while a
     * rectangle is chosen, the same line shows and changes ITS mark. Only
     * in layers that offer marks, or where the find already holds one —
     * a spore layer keeps its plain instruction. */

    /* THE SAME LINE NAMES A PART MEASURE (07.10.2026: „die
     * Sterigmenlänge … den Keimporus … die Ornamentationshöhe … auch nur
     * eine einfache Längenmessung"). With „Strecke" on, the line asks what
     * the next line measures; a chosen line shows what it measures. The
     * part stays in the structure's own layer, so one spore is measured in
     * one place and its parts stand under it in the statistics
     * (partRows in measurestats.js). Two kinds, one row — the table says
     * what differs between them. */
    const LABEL_KINDS = {
        /* A PICTURE CUTOUT'S NAME (07.10.2026): no stock — „da wird es
           wahrscheinlich noch einige geben, die können wir nicht alle
           abdecken" —, only the names the find already holds in this
           layer and one's own. */
        name: {
            /* The name of the next cutout IS the tab on view, as a line's
               part is: picking one switches the pane and the picture to it. */
            key: 'name', of: m => measureCrop(m) ?? '', stock: {},
            stamps: { get: (label) => cropOfTab(statsTabs.get(label)) ?? '', set: (label, name) => statsTabSet(cropTab(name)) },
            say: () => t('Der Ausschnitt heißt:'), sayChosen: () => t('Dieser Ausschnitt heißt:'),
            none: () => t('ohne Namen'), example: () => t('z. B. Ascusspitzen Jod'), own: () => t('Eigener Name'),
            add: () => t('+ eigener Name'),
        },
        mark: {
            key: 'mark', of: measureMark, stock: MEASURE_MARKS, stamps: markStamps,
            say: () => t('Merkmal:'), sayChosen: () => t('Merkmal dieser Messung:'),
            none: () => t('ohne'), example: () => t('z. B. Makrozystide'), own: () => t('Eigenes Merkmal'),
        },
        part: {
            /* The part of the next line IS the tab on view — picking one
               here switches the statistics to it (statsTabSet). */
            key: 'part', of: measurePart, stock: MEASURE_PARTS,
            stamps: { get: (label) => (cropOfTab(statsTabs.get(label)) === null ? statsTabs.get(label) ?? '' : ''),
                set: (label, part) => statsTabSet(part) },
            say: () => t('Die Linie misst:'), sayChosen: () => t('Diese Linie misst:'),
            none: () => t('nichts Bestimmtes'), example: () => t('z. B. Apikulus'), own: () => t('Eigenes Teilmaß'),
        },
    };

    /** What one may give in a layer, of one kind: its stock, then every
     *  word the find already holds there (one's own included). */
    function labelOptions(kind, label) {
        const k = LABEL_KINDS[kind];
        const out = [...(k.stock[label] ?? [])];
        for (const s of statsSources()) {
            for (const m of (s.obj.measures ?? [])) {
                // A cutout's name is no mark of a rectangle, nor the other way round.
                if ((kind === 'name') !== (m.type === 'crop')) continue;
                const word = k.of(m);
                if (word !== '' && measureLabel(m) === label && !out.includes(word)) out.push(word);
            }
        }
        return out;
    }

    /** A mark as the picture writes it: „Chryso" for „Chrysozystide" —
     *  the layer already says it is a cystidium. */
    function markShort(mark) {
        return mark.replace(/zystide$/i, '') || mark;
    }

    function markHintSync() {
        const hint = document.getElementById('photo-frame-hint');
        const shown = hint.querySelector('.photo-mark-row') !== null;
        // A line drawn INTO a cutout is the cutout's, never a part.
        const aimKind = measureMode === 'rect' || measureMode === 'cutout' || measureMode === 'cystid' ? 'mark'
            : measureMode === 'line' ? 'part' : measureMode === 'crop' ? 'name' : null;
        const aiming = aimKind !== null && frameRect === null && calibrating === null && textEdit === null;
        const quiet = textEdit === null && measureMode === null && calibrating === null && frameRect === null;
        const pickedKind = !quiet ? null
            : measureSelected?.m.type === 'rect' ? 'mark'
            : measureSelected?.m.type === 'line' ? 'part'
            : measureSelected?.m.type === 'crop' ? 'name' : null;
        const kind = aiming ? aimKind : pickedKind;
        const chosen = !aiming && pickedKind !== null ? measureSelected : null;
        const target = statsLabel === MEASURE_FREE || kind === null ? null
            : aiming ? 'aim:' + measureMode + ':' + statsLabel
            : chosen.m;
        // A cutout's name is asked for even before the find holds any.
        if (target === null || (kind !== 'name' && labelOptions(kind, statsLabel).length === 0)) {
            // The cystidium tool asks its method even where the layer offers no marks.
            if (aiming && measureMode === 'cystid') {
                const key = 'cystid:' + cystidProfile();
                if (shown && markPanelFor === key) return;
                markPanelFor = key;
                hint.textContent = '';
                hint.append(cystidRow());
                const text = document.createElement('span');
                text.textContent = measureModeHint('cystid');
                hint.append(text);
                hint.hidden = false;
                return;
            }
            if (shown) calibrationHint(aiming ? measureModeHint(measureMode) : '');
            markPanelFor = null;
            return;
        }
        const key = aiming && measureMode === 'cystid' ? target + ':' + cystidProfile() : target;
        if (shown && markPanelFor === key) return;
        markPanelFor = key;
        markPanelBuild(hint, kind, chosen, aiming);
        if (aiming && measureMode === 'cystid') hint.prepend(cystidRow());
    }

    /* THE CYSTIDIUM'S METHOD, chosen in the line over the picture and kept
       for the next visit (settings): Hebeloma, Conocybe or — since
       18.11.2026 — Paraphyse. A frame drawn
       carries it (m.profile); changing it later changes only the frames to
       come — the measured ones keep the method they were measured by. */
    function cystidProfile() {
        const kept = settings.get('cystidProfile', 'hebeloma');
        return CYSTID_PROFILES[kept] ? kept : 'hebeloma';
    }

    function cystidRow() {
        const row = document.createElement('span');
        row.className = 'photo-mark-row';
        const say = document.createElement('b');
        say.textContent = t('Messart:');
        row.append(say);
        for (const [value, words] of [['hebeloma', 'Hebeloma'], ['conocybe', 'Conocybe'], ['paraphysis', t('Paraphyse')]]) {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = words;
            b.classList.toggle('is-active', value === cystidProfile());
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                settings.set('cystidProfile', value);
                markPanelFor = null;
                markHintSync();
            });
            row.append(b);
        }
        return row;
    }

    /** The steps inside a cystidium's frame, in the order of its method: what
     *  to tap, said in plain words, and with which tool. THE LENGTH IS
     *  STRAIGHT (05.11.2026: „bei der Messung der Längen brauche
     *  ich keine Polylinie") — base and tip, two taps, as the books measure
     *  a cystidium; the cutout tool keeps its bent length for the ascus. */
    function cystidSteps(profile) {
        const length = { mode: 'line', word: t('die Länge'),
            say: t('Jetzt die Länge: Klicke auf die Basis, dann auf die Spitze.') };
        /* THE PARAPHYSIS'S LENGTH IS BENT (18.11.2026: „Länge mal Breite
           gebogen … plus Kopfdurchmesser") — it curves as an ascus does, so
           it is traced, unlike a cystidium's straight base-to-tip. */
        if (profile === 'paraphysis') return [
            { mode: 'path', word: t('die Länge'),
              say: t('Jetzt die Länge: Klicke in kleinen Schritten entlang der gedachten Mittellinie. Am Ende ein Doppelklick.') },
            { mode: 'line', word: t('die Breite'), say: t('Jetzt die Breite: Klicke unterhalb des Kopfs auf beide Ränder.') },
            { mode: 'line', word: t('der Kopf'), say: t('Jetzt der Kopf: Klicke an seiner breitesten Stelle auf beide Ränder.') },
        ];
        return profile === 'conocybe' ? [
            { ...length, word: t('die Gesamtlänge'),
              say: t('Jetzt die Gesamtlänge: Klicke auf die Basis, dann auf das Ende des Köpfchens.') },
            { mode: 'line', word: t('der Bauch'), say: t('Jetzt der Bauch: Klicke an seiner dicksten Stelle auf beide Ränder.') },
            { mode: 'line', word: t('das Köpfchen'), say: t('Jetzt das Köpfchen: Klicke an seiner breitesten Stelle auf beide Ränder.') },
            { mode: 'line', word: t('der Hals'), say: t('Jetzt der Hals: Klicke dort, wo er aus dem Bauch kommt, dann dort, wo das Köpfchen beginnt.') },
        ] : [
            length,
            { mode: 'line', word: 'A', say: t('Jetzt A: Klicke an der breitesten Stelle des Kopfs, nahe der Spitze, auf beide Ränder.') },
            { mode: 'line', word: 'M', say: t('Jetzt M: Klicke an der schmalsten Stelle in der Mitte auf beide Ränder.') },
            { mode: 'line', word: 'B', say: t('Jetzt B: Klicke an der breitesten Stelle im unteren Drittel auf beide Ränder.') },
        ];
    }

    /** The words as buttons, the chosen one filled; „+ eigenes" opens a
     *  field in the line itself. Aiming, the tool's instruction follows. */
    function markPanelBuild(hint, kind, chosen, aiming) {
        const k = LABEL_KINDS[kind];
        const m = chosen?.m ?? null;
        const current = m ? k.of(m) : (k.stamps.get(statsLabel) ?? '');
        hint.textContent = '';
        const row = document.createElement('span');
        row.className = 'photo-mark-row';
        const say = document.createElement('b');
        say.textContent = m ? k.sayChosen() : k.say();
        row.append(say);
        const set = (word) => {
            if (m) {
                if (word === '') delete m[k.key];
                else m[k.key] = word;
                if (!chosen.free) {
                    measureSaveQueue();
                    statsRefresh();
                }
                // A line renamed follows into its part's tab, still chosen —
                // left in the old one it would vanish under the finger.
                if (kind === 'part') statsTabSet(word, true);
                // A cutout renamed follows into its name's tab, still chosen.
                if (kind === 'name') statsTabSet(cropTab(word), true);
            } else {
                k.stamps.set(statsLabel, word);
            }
            markPanelFor = null;
            drawOverlay();
        };
        // A word just typed stands among them before anything carries it.
        const options = labelOptions(kind, statsLabel);
        if (current !== '' && !options.includes(current)) options.push(current);
        for (const [value, words] of [['', k.none()], ...options.map(o => [o, o])]) {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = words;
            b.classList.toggle('is-active', value === current);
            b.addEventListener('click', (e) => { e.stopPropagation(); set(value); });
            row.append(b);
        }
        const own = document.createElement('button');
        own.type = 'button';
        own.textContent = k.add?.() ?? t('+ eigenes');
        own.addEventListener('click', (e) => {
            e.stopPropagation();
            const field = document.createElement('input');
            field.type = 'text';
            field.maxLength = 40;
            field.dataset.noClear = '';
            field.className = 'photo-mark-own';
            field.placeholder = k.example();
            field.setAttribute('aria-label', k.own());
            field.addEventListener('keydown', (e2) => {
                if (e2.key === 'Enter') {
                    e2.preventDefault();
                    const word = field.value.trim();
                    if (word !== '') set(word);
                }
            });
            own.replaceWith(field);
            field.focus();
        });
        row.append(own);
        /* NO „UMDREHEN" for a chosen cutout here any more: its tile in the
           statistics' tab mirrors it, top and bottom or left and right
           (07.10.2026: „durch die Vorschaubildchen, die ja alle
           ihre Spiegel-Buttons haben, brauche ich den Hauptumdreh-Button
           nicht mehr"). */
        hint.append(row);
        if (aiming) {
            const text = document.createElement('span');
            text.textContent = !scaleMayEdit()
                ? measureModeHint(measureMode) + ' ' + t('Das gilt nur für dich und wird nicht gespeichert.')
                : measureModeHint(measureMode);
            hint.append(text);
        }
        hint.hidden = false;
    }

    textInput.addEventListener('input', () => {
        if (textEdit === null) return;
        textEdit.m.text = textInput.value;
        drawOverlay();
    });
    textInput.addEventListener('keydown', () => {
        if (textEdit === null) return;
        // Enter is a new line now (textWritingHint). The caret moves after
        // the key has done its work.
        requestAnimationFrame(textCaretPlace);
    });
    /* NO END ON BLUR any more: the focus leaves for the „Fertig" button,
       for another window, for the phone's keyboard closing — none of them
       is a decision about the words. The buttons, Escape, a press in the
       picture and every other tool are. */

    /* --- The question window ----------------------------------------------- */

    function openScaleWindow() {
        const valueField = document.getElementById('photo-scale-value');
        const unitField  = document.getElementById('photo-scale-unit');
        const existing   = photoInWindow?.scale ?? null;

        // A fresh line answers afresh; only the unit is worth guessing, and
        // the previous calibration is the best guess there is.
        valueField.value = '';
        if (existing) {
            unitField.value = existing.um >= 10000 ? '10000'
                            : existing.um >= 1000 ? '1000' : '1';
        }
        scaleUnitSync();
        scaleObjectsDraw();
        scaleHintReset();
        document.getElementById('photo-scale-remove').hidden = existing === null;
        scaleWindow.showModal();
        valueField.focus();
    }

    /** The unit buttons show the hidden field's factor as the chosen one. */
    function scaleUnitSync() {
        const unit = document.getElementById('photo-scale-unit').value;
        for (const b of document.querySelectorAll('#photo-scale-window [data-unit]')) {
            const on = b.dataset.unit === unit;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', String(on));
        }
    }
    for (const b of document.querySelectorAll('#photo-scale-window [data-unit]')) {
        b.addEventListener('click', () => {
            document.getElementById('photo-scale-unit').value = b.dataset.unit;
            scaleUnitSync();
        });
    }
    // A usual length fills the number in; Übernehmen stays the one that saves.
    for (const b of document.querySelectorAll('#photo-scale-window [data-value]')) {
        b.addEventListener('click', () => {
            document.getElementById('photo-scale-value').value = b.dataset.value;
        });
    }
    // Numbers into the field the German way — „23,25", not „23.25".
    const scaleFieldNumber = v => String(v).replace('.', ',');
    // A coin is its diameter, in millimetres.
    for (const b of document.querySelectorAll('#photo-scale-window [data-coin]')) {
        b.addEventListener('click', () => {
            document.getElementById('photo-scale-value').value = scaleFieldNumber(b.dataset.coin);
            document.getElementById('photo-scale-unit').value = '1000';
            scaleUnitSync();
        });
    }

    /* ONE'S OWN THINGS OF A KNOWN LENGTH (07.10.2026: „zwei,
       drei Werte, die man selbst setzen kann und benennen kann. Wie zum
       Beispiel Feuerzeug"). Made right here, where they are used: the
       length goes into the field as for any line, „Merken" asks for a
       name, and from then on a button fills it in — in the unit it reads
       best in. Kept on the account (measure_style, scale_objects), the
       whole list sent at once; the bucket beside one forgets it after a
       question. At most six: a row of buttons, not a catalogue. */
    const SCALE_OBJECTS_MAX = 6;
    function scaleObjectsDraw() {
        const box = document.getElementById('photo-scale-own');
        box.textContent = '';
        const objects = measureStyle.scale_objects ?? [];
        objects.forEach((o, i) => {
            const unit = o.um >= 10000 ? 10000 : o.um >= 1000 ? 1000 : 1;
            const shown = (o.um / unit).toLocaleString('de-DE', { maximumFractionDigits: 3 })
                + ' ' + { 10000: 'cm', 1000: 'mm', 1: 'µm' }[unit];
            const pick = document.createElement('button');
            pick.type = 'button';
            pick.className = 'button subtle small';
            pick.textContent = o.name;
            pick.title = shown;
            pick.addEventListener('click', () => {
                document.getElementById('photo-scale-value').value = scaleFieldNumber(+(o.um / unit).toFixed(3));
                document.getElementById('photo-scale-unit').value = String(unit);
                scaleUnitSync();
            });
            const forget = document.createElement('button');
            forget.type = 'button';
            forget.className = 'button subtle small photo-scale-forget';
            forget.innerHTML = iconHtml('delete');
            forget.title = t('„%s" vergessen', o.name);
            forget.setAttribute('aria-label', forget.title);
            forget.addEventListener('click', async () => {
                const sure = await confirm({
                    title: t('„%s" vergessen?', o.name),
                    text: t('Der Knopf verschwindet. Maßstäbe, die du damit gesetzt hast, bleiben, wie sie sind.'),
                    button: t('Vergessen'),
                    danger: true,
                });
                if (sure) scaleObjectsStore(objects.filter((_, j) => j !== i));
            });
            const pair = document.createElement('span');
            pair.className = 'photo-scale-own';
            pair.append(pick, forget);
            box.append(pair);
        });
        if (objects.length < SCALE_OBJECTS_MAX) {
            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'button subtle small';
            add.innerHTML = iconHtml('plus');
            add.append(t('Diese Länge merken'));
            add.addEventListener('click', scaleObjectAdd);
            box.append(add);
        }
    }
    async function scaleObjectAdd() {
        const hint = document.getElementById('photo-scale-hint');
        const value = parseFloat(String(document.getElementById('photo-scale-value').value).replace(',', '.'));
        const um = value * parseFloat(document.getElementById('photo-scale-unit').value);
        // A question left open turns red INSIDE its window (CLAUDE.md).
        if (!Number.isFinite(um) || um <= 0) {
            hint.textContent = t('Erst die Länge eintragen, dann merken.');
            hint.classList.add('is-error');
            document.getElementById('photo-scale-value').focus();
            return;
        }
        const name = (await prompt({
            title: t('Gegenstand merken'),
            text: t('Wie heißt der Gegenstand? Zum Beispiel „Feuerzeug".'),
            button: t('Merken'),
        }))?.trim();
        if (!name) return;
        scaleObjectsStore([...(measureStyle.scale_objects ?? []), { name, um }]);
    }
    /** The window's question as it stands when nothing is wrong. */
    function scaleHintReset() {
        const hint = document.getElementById('photo-scale-hint');
        hint.textContent = t('Wie lang ist die gezogene Strecke wirklich?');
        hint.classList.remove('is-error');
    }
    document.getElementById('photo-scale-value').addEventListener('input', scaleHintReset);
    async function scaleObjectsStore(list) {
        try {
            const answer = await request('measure_style.php', {
                method: 'POST',
                body: JSON.stringify({ scale_objects: list }),
            });
            measureStyle.scale_objects = answer.measure_style?.scale_objects ?? list;
            scaleHintReset();
        } catch (err) {
            // Said in the window itself — a toast would lie behind it.
            const hint = document.getElementById('photo-scale-hint');
            hint.textContent = err.message || t('Das Speichern ist fehlgeschlagen.');
            hint.classList.add('is-error');
        }
        scaleObjectsDraw();
    }

    async function scaleStore(scale) {
        /* Two worlds, one shape: a voucher photo saves through photos.php
           PATCH, a cloud recording through its own endpoint — but the scale
           JSON, the gate and the answer are the same. A scale set here is
           THIS PHOTO's alone: presets are minted only in the administration,
           from a calibration image (26.09.2026, index.php says why). */
        const subject = measureSubject();
        /* A PICTURE ONE MAY ONLY LOOK AT keeps the scale in this window
           (06.10.2026): tried out, never sent — the photographer's stays
           his, and the next opening shows it again. */
        if (!scaleMayEdit()) {
            subject.scale = scale === null ? null : { ...scale };
            scaleWindow.close();
            calibrateEnd();
            scaleTopbarSync();
            drawScaleBar();
            drawOverlay();
            statsRefresh();
            return;
        }
        const answer = cloudInWindow !== null
            ? await request(`cloud.php?id=${host.pointId()}&action=scale`, {
                method: 'POST',
                body: JSON.stringify({ file: subject.id, scale }),
            })
            : await request('photos.php', {
                method: 'POST',
                body: JSON.stringify({ id: subject.id, scale, _methode: 'PATCH' }),
            });
        subject.scale = answer.scale;
        scaleWindow.close();
        calibrateEnd();
        scaleTopbarSync();
        drawScaleBar();
        drawOverlay();
        // A changed calibration changes every µm in the sidebar.
        statsRefresh();
    }

    document.getElementById('photo-scale-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const value = parseFloat(String(
            document.getElementById('photo-scale-value').value).replace(',', '.'));
        const um = value * parseFloat(document.getElementById('photo-scale-unit').value);
        if (!Number.isFinite(um) || um <= 0) return;
        try {
            await scaleStore({
                line:   calibrating,
                um,
                corner: measureSubject()?.scale?.corner ?? 'br',
                // The width the line was drawn on — what makes the same
                // calibration read truly on preview AND original (0090).
                width:  photoImageEl.naturalWidth,
            });
        } catch (err) {
            document.getElementById('photo-scale-hint').textContent =
                err.message || t('Das Speichern ist fehlgeschlagen.');
        }
    });
    document.getElementById('photo-scale-remove').addEventListener('click', async () => {
        try {
            await scaleStore(null);
        } catch (err) {
            document.getElementById('photo-scale-hint').textContent =
                err.message || t('Das Entfernen ist fehlgeschlagen.');
        }
    });
    document.getElementById('photo-scale-cancel').addEventListener('click', () => {
        scaleWindow.close();
        calibrateEnd();
    });

    /* --- Wiring into the window's life ------------------------------------- */

    /* drawOverlay is part of the load ritual since the measures persist: the
       stored ones of the fresh picture have to appear — and only NOW, when
       naturalWidth speaks for the new image and the width conversion lands
       where it should (showPhoto blanked the plane until here). */
    photoImageEl.addEventListener('load', () => { pictureSwitching(); syncOverlay(); drawScaleBar(); drawOverlay(); developLoaded(); });
    photoImageEl.addEventListener('error', () => pictureSwitching());

    /* THE OLD PICTURE GOES AT ONCE (18.11.2026: „Wenn ich beim
       Messen ins Bild reingezoomt habe und dann das Bild in der Sidebar
       wechseln möchte, ist der erste Klick in die Sidebar nicht der
       Wechsel, sondern das Bild wird erstmal eingepasst und der zweite
       Klick ist dann erst der Bildwechsel … Ich hätte gerne sofort einen
       Bildwechsel"). A new picture resets the zoom before its source is set
       (showPhoto says why) — but a browser keeps drawing the OLD picture
       until the new one has arrived, and a cloud recording comes from the
       photographer's cloud through this server, a second and more. For
       that moment the old picture stood there fitted: it looked as if the
       click had only fitted it, and the picture came while one was already
       clicking again. Not reproduced with the demo's voucher photos, which
       come at once. So while the new source loads, the old picture and its
       development are not shown; the frame stays dark until the new one
       stands. A picture already in the cache is never hidden. */
    function pictureSwitching() {
        photoFrame.classList.toggle('is-switching', !photoImageEl.complete);
    }
    /* A ResizeObserver AND NOT (ONLY) window resize: the statistics sidebar
       folding open narrows the image without any window event — measured
       28.08.2026: the first spore auto-opened the pane mid-look, the image
       slid left and the freshly drawn rectangle stayed put, hanging in the
       air beside its ellipse.

       THE FRAME IS WATCHED TOO, NOT ONLY THE IMAGE — the maintainer found the
       hole on the live server the same day: with a WIDE window the image is
       height-constrained, so the narrowing frame does not resize it at all,
       it only re-centres it — the image slides left, its size never changes,
       and an observer on the image alone sleeps through the whole move. The
       frame, by contrast, resizes EVERY time the pane folds; between the
       two of them no relayout escapes. */
    const overlayFollow = new ResizeObserver(() => {
        if (photoWindow.open) { syncOverlay(); drawScaleBar(); drawOverlay(); }
    });
    overlayFollow.observe(photoImageEl);
    overlayFollow.observe(photoFrame);
    /* The window listener STAYS beside the observer: a growing window can
       move a centred image without resizing it or its frame, and an unmoved
       size never wakes the observer. */
    window.addEventListener('resize', () => {
        if (photoWindow.open) { syncOverlay(); drawScaleBar(); drawOverlay(); }
    });
    photoWindow.addEventListener('close', () => {
        measureSaveFlush();
        calibrateEnd();
        measureEnd();
        measures = [];
        freeMeasures = [];
        measureSelected = null;
        removeAnchor = null;
        scaleBar.hidden = true;
        statsPane.hidden = true;
        // The cut-out sources are megabytes each — they go with the window.
        statsImages.clear();
        statsRun++;
    });

    /* Escape while aiming ends the MODE, not the window — whoever misclicked
       the third of four spore points wants out of the gesture, not out of the
       picture. One step per Escape; true when there was one to take. */
    function escapeStep() {
        // The cast's pipette lets go first, like the white balance's frame (wbPick).
        if (castPicking) {
            castPickEnd();
            developPanelDraw();
            return true;
        }
        if (wbPicking) {
            wbPickEnd();
            developPanelDraw();
            return true;
        }
        /* ESCAPE CANCELS „ZUSCHNEIDEN" (07.11.2026): the turn,
           straightening and cut go back to what they were when it opened,
           and the crop closes — „Fertig" is the way to keep them. */
        if (cropEditing) {
            // A run of the strong model asked for in this window ends with it: its border goes back with the rest.
            expandStop();
            if (cropBefore) develop.geo = structuredClone(cropBefore);
            else delete develop.geo;
            cropEditing = false;
            cropBefore = null;
            photoZoom.reset();
            developQueue();
            developPanelDraw();
            viewUpdate();
            return true;
        }
        // A spot chosen or being painted: Escape lets it go.
        if (developMode && developTab === 'spots' && (spotPaint !== null || spotChosen !== null)) {
            spotPaint = null;
            spotChosen = null;
            developPanelDraw();
            developHandles();
            return true;
        }
        // A mask about to be laid, or one whose lines stand: Escape lets it go — the lines leave the picture.
        if (developMode && (maskPlacing !== null || maskChosen !== null)) {
            maskPlacing = null;
            maskChosen = null;
            developPanelDraw();
            developHandles();
            return true;
        }
        // Escape while writing takes the words back (textEditEnd), and only
        // that — a second Escape ends the tool.
        if (textEdit !== null) {
            textEditEnd(false);
            return true;
        }
        /* A POINT SET BY MISTAKE GOES, THE TOOL STAYS
           (07.10.2026: „wenn man aus Versehen an einer falschen Stelle einen
           Punkt gesetzt hat, nur diesen Punkt entfernen … und im Werkzeug
           bleiben"). Each Escape takes back the LAST point of the measure in
           progress — of the scale line too —, so one press undoes the slip
           and a few undo the whole attempt; only with nothing in progress
           does Escape end the tool. An open polyline loses its last point
           as well: Escape used to close the run, but that kept exactly the
           point one wanted gone, and the double click closes it anyway. */
        if (calibrating !== null && calibrating.length > 0 && !scaleWindow.open) {
            calibrating.pop();
            calibrationHint(calibrating.length === 0 ? CALIBRATE_HINT() : t('Jetzt klicke auf das Ende.'));
            drawOverlay();
            return true;
        }
        if (measureMode !== null && measurePoints.length > 0) {
            measurePoints.pop();
            drawOverlay();
            return true;
        }
        /* INSIDE A FRAME, ESCAPE GOES BACK ONE MEASURE
           (06.11.2026: „wenn ich bei Hebeloma bin … und erst das Köpfchen
           gemessen habe, will ich mit einmal Escape dieses Maß
           zurückhaben"). With no point of the current step set, the last
           finished measure inside the frame goes and its step is asked
           again — the order of a cystidium's parts is easily confused.
           Right after the LAST step it still reaches back into the frame
           just completed, until the next frame's first tap. With nothing
           measured inside, the frame itself goes and the tool stays for a
           new one; only the Escape after that ends the tool. */
        if (frameRect === null && frameJustDone !== null && measurePoints.length === 0
            && (measureMode === 'cutout' || measureMode === 'cystid')) {
            const { m, free } = frameJustDone;
            frameJustDone = null;
            if ((free ? freeMeasures : measures).includes(m) && (m.inner?.length ?? 0) > 0) {
                frameRect = { m, free, armed: true, mode: measureMode };
            }
        }
        if (frameRect !== null && frameRect.armed && measurePoints.length === 0) {
            const fr = frameRect;
            if ((fr.m.inner?.length ?? 0) === 0) {
                frameDiscard();
                return true;
            }
            fr.m.inner.pop();
            if (!fr.free) {
                measureSaveQueue();
                statsRefresh();
            }
            frameOffer();
            drawOverlay();
            return true;
        }
        if (calibrating !== null || measureMode !== null) {
            calibrateEnd();
            measureEnd();
            return true;
        }
        // Escape on a selected measure drops the selection, not the window.
        if (measureSelected !== null) {
            measureDeselect();
            return true;
        }
        return false;
    }
    /* ESCAPE NEVER CLOSES THE PICTURE (07.10.2026: „weil wir die
       Werkzeuge ja mit Escape abwählen. Und ein Escape zu viel würde den
       Bildbetrachter schließen"). Until then the steps above ran in the
       dialog's cancel event, and with nothing left to undo the next Escape
       closed the window — and Chrome lets a SECOND Escape in a row close a
       dialog even when its cancel is refused, unless the user clicked in
       between. So the key's own default is prevented, in the capture phase
       before anything else, and the close request never comes; the window
       closes by its buttons. The listener asks whether the key is this
       window's — a confirmation opened over it keeps its Escape. */
    const escapeHere = (e) => e.key === 'Escape' && viewing()
        && (photoWindow.contains(e.target) || e.target === document.body || e.target === document.documentElement);
    window.addEventListener('keydown', (e) => { if (escapeHere(e)) e.preventDefault(); }, true);
    // The step after the fields had their say — a list that folds on
    // Escape stops the event, and then there is no step to take.
    window.addEventListener('keydown', (e) => { if (escapeHere(e)) escapeStep(); });
    /* What still arrives as a cancel is no key: the phone's back gesture.
       It takes a step if there is one, and closes when there is none. */
    photoWindow.addEventListener('cancel', (e) => {
        if (escapeStep()) e.preventDefault();
    });

    document.getElementById('photo-close')?.addEventListener('click', () => photoWindow.close());
    // The same door in the top left corner (index.php, .photo-window-head).
    document.getElementById('photo-close-top')?.addEventListener('click', () => photoWindow.close());

    // Release the image as soon as the window is closed. An <img> with a set
    // source otherwise keeps the data in memory, even invisible — at 2048 px
    // that is a few megabytes per shot.
    photoWindow?.addEventListener('close', () => {
        document.getElementById('photo-image').removeAttribute('src');
        photoZoom.reset();
        photoInWindow = null;
        cloudInWindow = null;
    });

    /**
     * Delete a cloud recording — in the storage and here.
     *
     * THE QUESTION SAYS WHAT HAPPENS, not merely "really?". And it can say
     * something reassuring, because it was measured (19.08.2026): pCloud
     * answers deletefile with isdeleted:true and keeps the file in its own
     * trash. So this is not the one-way street that deleting a voucher photo
     * is — and the two questions must not sound alike, or the harmless one
     * teaches you to click the other away too.
     *
     * PER CLOUD since every cloud takes pictures (26.09.2026): Drive and
     * OneDrive keep a trash as pCloud does (storage_file_delete), S3 has
     * none — there the question must not promise one.
     */
    const CLOUD_TRASH_KEEPERS = { pcloud: 'pCloud', gdrive: 'Google Drive', onedrive: 'OneDrive' };

    /**
     * Fetch the recording itself into the window.
     *
     * The preview is there in an instant and covers most looking; this is for
     * the moment when it does not — the spores at full magnification, the grain
     * of a stack. It replaces the picture in place, so the zoom that is already
     * set stays where it was.
     */
    document.getElementById('photo-quality')?.addEventListener('click', () => {
        const f = cloudInWindow;
        if (!f || cloudOriginalLoaded) return;
        qualityShow(f, 'loading');

        const image = document.getElementById('photo-image');
        const full = new Image();
        full.addEventListener('load', () => {
            // Only if the same recording still stands there. Whoever clicked on
            // through in the meantime must not have a foreign picture pushed in
            // front of them.
            if (cloudInWindow === f) {
                // Taps of a measure in progress, carried from the preview's pixels into the original's.
                const k = image.naturalWidth > 0 ? full.naturalWidth / image.naturalWidth : 1;
                const taps = measurePoints.map(([x, y]) => [x * k, y * k]);
                originalShown(full.src);
                image.src = full.src;
                /* The original is what a PRESET may bind to (its factor is µm
                   per camera pixel) — the chooser's options unlock with this.
                   The bar and the overlay re-reckon on the image's own load
                   event; the chooser has no such event, hence here. */
                cloudOriginalLoaded = true;
                /* FILE THE DIMENSIONS the first time this original stands
                   here (migration 0095): from now on the presets unlock on
                   the preview, and nobody loads megabytes just to teach the
                   chooser a number that never changes. Fire and forget —
                   a miss only means the badge once more. */
                if (!(f.width > 0)) {
                    f.width  = full.naturalWidth;
                    f.height = full.naturalHeight;
                    const body = new FormData();
                    body.append('file', String(f.id));
                    body.append('width', String(full.naturalWidth));
                    body.append('height', String(full.naturalHeight));
                    request(`cloud.php?id=${host.pointId()}&action=size`,
                        { method: 'POST', body }).catch(() => {});
                }
                // The file's own measure, for the fact line — the camera's above stays the scale's (cloudDetails).
                f.fileW = full.naturalWidth;
                f.fileH = full.naturalHeight;
                cloudDetails(f);
                /* The finished measures SURVIVE the swap since migration
                   0092: each one carries the width it was tapped on and
                   converts, exactly like the calibration. The taps of a
                   measure in PROGRESS have no width yet — they were dropped
                   until 18.11.2026, which was rare while the original came
                   only on a click. Now it comes by itself while one may
                   already be tapping, so they are carried over by the
                   ratio of the two widths instead. */
                measurePoints = taps;
                qualityShow(f, 'original');
                scaleTopbarSync();
            }
        }, { once: true });
        full.addEventListener('error', () => {
            if (cloudInWindow === f) qualityShow(f, 'retry');
        }, { once: true });
        // Through the desk's cache (originalcache.js): kept from an earlier opening, or fetched and kept now.
        originalBlob(f, `${host.start.baseUrl}pages/cloud_thumb.php?id=${f.id}&original=1`)
            .then((blob) => { full.src = URL.createObjectURL(blob); })
            .catch(() => { if (cloudInWindow === f) qualityShow(f, 'retry'); });
    });

    /* The object address of the original standing in the window — let go
       when the next one comes or the window closes, or every picture
       opened would stay in memory for the page's life. */
    let originalAddress = null;
    function originalShown(address) {
        if (originalAddress && originalAddress !== address) URL.revokeObjectURL(originalAddress);
        originalAddress = address;
    }
    photoWindow.addEventListener('close', () => originalShown(null));

    document.getElementById('photo-cloud-delete')?.addEventListener('click', async () => {
        const f = cloudInWindow;
        if (!f) return;

        const keeper = CLOUD_TRASH_KEEPERS[f.provider || 'pcloud'];
        if (!await confirm({
            title: t('Aufnahme löschen?'),
            text:  keeper
                ? t('„%s" wird in der Cloud gelöscht. %s legt sie in seinen Papierkorb, dort lässt sie sich noch zurückholen — hier ist sie sofort weg.', f.name, keeper)
                : t('„%s" wird in deinem S3-Speicher gelöscht. Dort gibt es keinen Papierkorb — sie ist danach für immer weg, dort und hier.', f.name),
            button: t('Löschen'),
            danger: true,
        })) return;

        const pointId = host.pointId();
        const place = galleryPlace(`cloud:${f.id}`);
        host.pictureNext?.(place?.next);
        photoWindow.close();

        try {
            const body = new FormData();
            body.append('file', String(f.id));
            await request(`cloud.php?id=${pointId}&action=remove`, { method: 'POST', body });

            host.status(t('Aufnahme gelöscht.'));
            if (host.pointId() === pointId) {
                await host.drawGallery(host.point(pointId));
                galleryResume(place);
            }
        } catch (e) {
            host.status(e.message || t('Die Aufnahme ließ sich nicht löschen.'), true);
        }
    });

    document.getElementById('photo-delete')?.addEventListener('click', async () => {
        const f = photoInWindow;
        if (!f) return;

        // THERE IS NO TRASH FOR PHOTOS, and that is why the question stands
        // here. An image in the trash keeps occupying space; a trash that does
        // not free the quota would be none. So gone means gone.
        if (!await confirm({
            title: t('Foto löschen?'),
            text:  t('Das Bild wird sofort entfernt. Es gibt dafür keinen Papierkorb.'),
            button: t('Löschen'),
            danger: true,
        })) return;

        const place = galleryPlace(`photo:${f.id}`);
        host.pictureNext?.(place?.next);
        photoWindow.close();
        try {
            await api.remove('photos', f.id);
            const whose = f.point_id ?? host.pointId();
            const point = host.point(whose);
            if (point) {
                point.photos = (point.photos ?? []).filter(x => x.id !== f.id);
                await host.drawGallery(point);
                galleryResume(place);
            }
            host.status(t('Foto gelöscht.'));
        } catch (e) {
            host.status(e.message || t('Das Foto lässt sich nicht löschen.'), true);
        }
    });

    /** The picture standing in the window as the gallery keys it („photo:63", „cloud:17"), or null. */
    const pictureKey = () => (photoInWindow ? `photo:${photoInWindow.id}`
        : cloudInWindow ? `cloud:${cloudInWindow.id}` : null);

    /* The zoom of the picture standing, out and back in — the Fundbrowser
       keeps it per find with the picture (finds.js, keptPicture). */
    const zoomView = () => photoZoom.view();
    const zoomSet = (v) => photoZoom.setView(v);

    return { showPhoto, showCloudPhoto, cloudPhotoUrl, cloudBytes, measureStyle, cloudFilesShown, pictureKey, zoomView, zoomSet };
}
