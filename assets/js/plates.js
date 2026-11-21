/**
 * PLATES — A4 compositions of a find's pictures (06.10.2026, migration
 * 0155; what is stored and who may touch it: includes/plates.php).
 *
 * THE GOAL THE PHOTO VIEWER WORKS TOWARDS (with two plates of
 * the late Matthias Reul in hand: „Solche Tafeln sind quasi das Ziel, auf
 * das ich hinarbeite mit dem Bildbetrachter"). Pictures cut to rectangles,
 * edge to edge with a fine line between them, each with its own scale bar,
 * a few texts, a field with the find's details wherever he puts it — one
 * A4 page, sometimes two. Built after a draft in the browser pane; his
 * answers to it are the rules below.
 *
 * THE PAGE IS A TREE (plates.php says why): a cell splits side by side or
 * one above the other, and dropping a picture on a cell's EDGE does the
 * splitting by itself — „ein bisschen komfortabel das Ganze vorbauen".
 * The middle replaces. The mouse wheel zooms into the picture, dragging
 * moves the cutout, the line between two cells drags their share.
 *
 * THE SCALE BAR IS NEVER DRAWN BY HAND. It comes from the picture's own
 * calibration through the cutout's zoom: a round length (Reul's 20, 30,
 * 50, 200 µm) near a fifth of the cell's width, black on a light ground
 * and white on a dark one. Every size on the page is in millimetres of
 * A4, so what the screen shows is what the printer draws — and because
 * every picture carries its bar, a plate printed smaller stays true
 * („Rand erstmal ohne, kann man immer noch im Drucker
 * verkleinern. Durch den Maßstab … ist das ja relativ egal").
 *
 * NO FOOTER, A FIELD („nicht so wie bei einem Word-Dokument
 * … sondern wirklich ein eigenes Feld, das ich irgendwo hinsetzen kann"):
 * „Fundangaben" lies on a picture as a box or fills a cell of its own,
 * and its ticks choose what it shows. The values are read from the find
 * every time the plate is drawn — a determination corrected tomorrow
 * stands corrected on the plate.
 *
 * AT THE DESK ONLY, like the picture strip: dragging and dropping onto a
 * page of A4 wants a mouse and room. Whoever is not the maker gets the
 * same window to look at, without the tools.
 */

import { t } from './i18n.js';
import { iconHtml } from './icons.js';
import { shortcutsProvide, typingIn } from './shortcuts.js';
import { confirm, ask } from './dialogs.js';
import { toast } from './toast.js';
import { settings } from './settings.js';
import { subjectGroups } from './gallerysort.js';
import {
    detailLabels, DETAIL_GAP, notePtOf, PT_MM, LINE_ITEM, LINE_TILE, BOX_PAD_X, BOX_PAD_Y, TILE_PAD, clamp, esc,
    load, loadFor, picView, pictureOf, measureBack, aspectOf, inkAt, umPerShownPx, cutout, barGeometry, mediaGeometry, labelGeometry, pictureLabel, pictureMarks, rowMediaLabel, rowLabelSubs, rowLabel, drawMedia, detailsHtml,
    layersOf, partsOf, cropsOf, countsOf, blockName, rowsOf, rowsFor, marksOf, straightOf, valuesRows, keyingRows, blockPlotInner, plotGroundRect, rowGeometry, drawSporeRow, drawBar, rowLook, rowInk, rowBarOver, inkOnCanvas, tileTextAt, ROW_PAD_THIN, EDGE_MM,
    inkFor, softRect, softWanted, writeOnCanvas, rowWriting, SOFT_FEATHER, SOFT_FEATHER_Y, SOFT_BLUR,
} from './plateparts.js';
import { valueLines, lengthStraight } from './measurestats.js';
import { mediaLabel } from './media.js';
import { developThumb } from './develop.js';
import { renderPage, jpegBytes, pdfBlob, fileBase } from './plateprint.js';
import { noteLayers, measureLayers, noteShapes, noteSvg } from './plateannot.js';
import { ARROW_WEIGHTS, arrowWeightOf, arrowWeightSet, arrowTextLook, arrowWordsGrip, textStep } from './measuredraw.js';
import { subjectIcon } from './subjecticons.js';
import { hoverPick } from './hoverpick.js';
import { personName } from './names.js';

// The place with land and state, as he always writes it (08.10.2026) —
// the municipality alone stays a tick away.
const DETAILS_DEFAULT = ['species', 'number', 'date', 'place_full', 'finder', 'determiner'];

// Text sizes in steps, in points — what a printer and a reader both know.
const PT_STEPS = [6, 7, 8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 40, 48];
// The tiles in „Belege": a page this many pixels wide.
const MINI_W = 64;

/* --- The tree ------------------------------------------------------------
   Cells carry an id while the editor holds them, so a drag can name its
   source; bare() takes the ids off again for saving. */
let seq = 0;
// med: the corner of the picture's medium (plateparts.js, mediaGeometry) — off until asked for;
// medFull its words written out (media.js, mediaLabel); label the same for its name, labelMarks the marks shown behind it (pictureLabel).
// ink the colour of each of that writing, {bar, med, label} → 'black' / 'white', absent read off the picture; soft {bar, med, label} → true where the picture beneath is blurred (plateparts.js, softOnCanvas).
const cellNew = (pic = null, rest = {}) => ({ t: 'cell', id: ++seq, pic, z: 1, cx: .5, cy: .5, bar: 'br', barOver: false, med: 'off', medFull: false, label: 'off', labelMarks: [], ink: {}, soft: {}, info: null, block: null, items: [], ...rest });
const splitNew = (dir, kids, sizes) => ({ t: 'split', dir, kids, sizes: sizes ?? kids.map(() => 1 / kids.length) });
const detailsNew = (pt = 9) => ({ fields: [...DETAILS_DEFAULT], labels: true, flow: 'lines', pt, ground: 'none' });

function cellsOf(node, out = []) {
    if (node.t === 'cell') out.push(node); else node.kids.forEach(k => cellsOf(k, out));
    return out;
}
/** A stored tree made editable: ids on the cells, every field present. */
/** The cells of a tree and the cells inside their insets — where a
 *  cutout may be open. */
function cellsWithInsets(node) {
    return cellsOf(node).flatMap(c => [c, ...c.items.filter(i => i.kind === 'inset').map(i => i.cell)]);
}

function adopt(node) {
    if (node?.t === 'split') {
        node.kids.forEach(adopt);
        return node;
    }
    /* NO FREE TEXT ON A PLATE since 09.11.2026 („die Tafel ist
       einfach nur … eine Zusammenstellung, und trägt keine weiteren
       Informationen" — free words belong to the find). A text stored
       before goes when the plate is read; its next save forgets it, and
       the server drops one as well (plates.php, plate_node). */
    // A row stored with the old „schmal" (2 mm) takes today's (ROW_PAD_THIN).
    if (node.block?.kind === 'row' && node.block.pad === 2) node.block.pad = ROW_PAD_THIN;
    return Object.assign(node, cellNew(node.pic ?? null, {
        z: node.z ?? 1, cx: node.cx ?? .5, cy: node.cy ?? .5, bar: node.bar ?? 'br', barOver: node.barOver ?? false, med: node.med ?? 'off', medFull: node.medFull ?? false,
        label: node.label ?? 'off', labelMarks: node.labelMarks ?? [], ink: writingOf(node.ink, v => v !== 'auto' && v), soft: writingOf(node.soft, v => typeof v === 'boolean'),
        info: node.info ?? null, block: node.block ?? null, items: (node.items ?? []).filter(i => i.kind !== 'text'), notes: node.notes ?? [],
        measures: node.measures ?? [],
    }));
}
/* THE WRITING'S INK AND SOFT GROUND PER PIECE (09.11.2026: „für
   jeden Text einzeln … unten rechts trifft Auto zu, unten links für das
   Medium nicht"). The first build of the same day held ONE value for the
   whole cell — a plate saved meanwhile carries a word or a bool, and it
   counts for all three. */
function writingOf(v, keep) {
    const all = typeof v === 'string' || typeof v === 'boolean' ? { bar: v, med: v, label: v } : (v && typeof v === 'object' ? v : {});
    const out = {};
    for (const key of ['bar', 'med', 'label']) {
        if (keep(all[key])) out[key] = all[key];
    }
    return out;
}
/* THE AUTOSAVE COMPARES THIS AS TEXT, so one shape per thing. A cell's
   ink and soft come back from the server as [] when nothing is chosen —
   PHP decodes the stored {} as an empty array and writes it out so — while
   the editor holds {}; the two never matched, and every answer of the
   server set off the next save, every few seconds (09.11.2026). */
const bare = (layout) => JSON.parse(JSON.stringify(layout, (k, v) => (k === 'id' ? undefined
    : (k === 'ink' || k === 'soft') && Array.isArray(v) ? {} : v)));
const content = c => ({ pic: c.pic, z: c.z, cx: c.cx, cy: c.cy, bar: c.bar, barOver: !!c.barOver, med: c.med ?? 'off', medFull: !!c.medFull, label: c.label ?? 'off', labelMarks: c.labelMarks ?? [], ink: c.ink ?? {}, soft: c.soft ?? {}, info: c.info, block: c.block, items: c.items, notes: c.notes ?? [], measures: c.measures ?? [] });
// All the lines a values text may show — and since 07.10.2026 all of them
// when it is laid down, the average too („bei den Werten ist der
// Durchschnitt initial nicht angehakt … dass der angehakt ist, jedenfalls
// wenn ich es nicht geändert habe").
/* 'spread' — count, spread and outliers (measurestats.js, spreadText,
   09.11.2026) — is a fourth line one ticks; a new values text starts
   with the classic three, as the books print them. */
const VALUE_KEYS = ['range', 'qn', 'mean', 'spread'];
const valuesNew = (layer, pt = 9) => ({ kind: 'values', layer, lines: ['range', 'qn', 'mean'], name: true, pt, ground: 'none' });

/**
 * @param {{request: Function, point: (id: number) => ?object,
 *          pictures: (id: number) => object[], details: (id: number) => object,
 *          mayMake: (id: number) => boolean, photoTitles: () => ?string[],
 *          inPage?: () => boolean, showPlate?: (plate: object) => void}} host
 *   inPage     OPTIONAL: true where the editor stands IN the page — the
 *              Fundbrowser's tab „Tafeln" (12.10.2026); it then opens with
 *              show() and stays open between plates
 *   showPlate  OPTIONAL: where a plate is opened from outside its row (the
 *              find's face beside the texts) — the host's own way to it
 */
/* Device pixels a strip picture may span before its 320-px preview gives
   way to the larger file — a third of upscaling, then it shows. */
const STRIP_SHARP = 440;

export function mountPlates(host) {
    /* The pictures as they are seen — turned and cut as developed (plateparts.js, picView). */
    const picturesOf = (id) => host.pictures(id).map(picView);

    const win = document.getElementById('plate-window');
    const row = document.getElementById('d-plates');
    if (!win || !row) return { show() {} };

    const stage    = document.getElementById('plate-stage');
    const pageEl   = document.getElementById('plate-page');
    const strip    = document.getElementById('plate-strip');
    const stripEdge = document.getElementById('plate-strip-edge');
    const mainEl   = win.querySelector('.plate-main');
    const pagesBox = document.getElementById('plate-pages');
    const selBox   = document.getElementById('plate-selection');
    const note     = document.getElementById('plate-note');
    const savedLine = document.getElementById('plate-saved');
    const deleteBtn = document.getElementById('plate-delete');
    const desk = window.matchMedia('(min-width: 861px)');

    let pointId = 0;
    let plates = [];          // the find's plates as the server gave them
    let ready = false;        // the migration is in
    let cloudReady = false;   // a drawn page has a cloud to go to (plates.php GET, plate_images.php)

    // The open plate.
    let plate = null;         // {id, mine, maker, …} or a new one's {id: null}
    let layout = null;        // the working copy, with ids
    let pageAt = 0;
    let editable = false;
    let savedJson = '';
    let selected = null;      // {cell, item} — item null for a details tile
    /* THE CUTOUT MOVES ONLY ON PURPOSE (06.10.2026: „dass ich
       gerade mit dem Touchpad viel zu oft die Bilder aus Versehen verschiebe
       oder zoome. Hier sollte es einen eigenen Button für geben. Ansonsten
       sind die Bilder fix"). A touchpad's two-finger scroll is a wheel, and
       every pass over a picture zoomed it. Now a DOUBLE CLICK on the picture
       opens it for the wheel and the drag (08.11.2026 — before,
       a button „Ausschnitt ändern" did, and the double click opened the
       viewer); a second double click, a click beside it, Enter or Escape
       closes it again. One cell at a time. */
    let framing = null;       // the cell whose cutout is open, or null
    let stripGroup = null;    // the group the strip's menu shows, by its name
    let stripWatch = null;    // the ResizeObserver on the strip's list
    let view = 1;             // the page's size on the screen, 1 = it fits the stage
    let toolsHeld = null;     // a cell whose row stays while the pointer is on a line beside it
    let pics = new Map();     // key -> picture of this find
    let values = {};          // the find's details

    /* THE ACCOUNT'S HAND (measure_style 'plate', includes/photos.php says
       what it holds and what not): a new plate starts with its lines, a new
       text, details field or values text with its size — and whatever the
       editor sets there becomes the hand for the next. Sent a moment after
       the last change, a run of A+ clicks being one wish. */
    const STYLE_DEFAULTS = { gutter: .6, gutter_color: '#ffffff', text_pt: 10, details_pt: 9, values_pt: 9 };
    const plateStyle = () => ({ ...STYLE_DEFAULTS, ...(host.style()?.plate ?? {}) });
    let styleWish = {};
    let styleTimer = null;
    function remember(key, value) {
        const style = host.style();
        if (style) style.plate = { ...plateStyle(), [key]: value };
        styleWish[key] = value;
        clearTimeout(styleTimer);
        styleTimer = setTimeout(() => {
            const plate = styleWish;
            styleWish = {};
            host.request('measure_style.php', { method: 'POST', body: JSON.stringify({ plate }) }).catch(() => {});
        }, 800);
    }
    const sizeKey = (kind) => ({ text: 'text_pt', info: 'details_pt', values: 'values_pt', keying: 'details_pt' })[kind];

    /* THE HAND PER KIND (08.11.2026: „können sich die ganzen
       Tafeleinstellungen auch ihre Einstellungen merken, dass man immer
       ziemlich gleiche Tafeln baut … die Fundangaben, wie ich die auswähle,
       ob ich die einzeilig will … bei den Reihen, ob ich da einen Rand will
       … ob ich den Maßstab im Bild haben will oder als Zeile drunter").
       What the panel last set for a kind is what the next one of that kind
       starts with — on the account, beside the sizes, so the phone builds
       the same plate as the desk. Only the HOW is kept, never the WHAT: the
       details' fields, never a values text's layer; a row's lines and
       margin, never its layer or its spores' air (that one is the find's,
       migration 0156). ONE STAYS OUT ON PURPOSE, his own call of 06.10.2026
       (includes/photos.php, 'plate'): the bar's corner ON A PICTURE
       („ziemlich individuell pro Bild" — where it stands depends on what
       the picture shows there). A row's bar is kept: it stands on the
       row's own ground. A values text's lines were out too („individuell
       pro Messreihe") until he asked for them the same evening („Die
       Zeilen der Werte auch merken"): the three of every layer — range,
       Q and count, average —; the part lines stay ticked, they are each
       layer's own.
       HAND_KEYS names what each kind keeps. Untouched, nothing changes:
       the defaults stand until the first setting is made. */
    const HAND_KEYS = {
        info: ['fields', 'labels', 'flow', 'ground', 'groundOpacity'],
        values: ['lines', 'name', 'ground', 'groundOpacity'],
        keying: ['show', 'label', 'ground', 'groundOpacity'],
        row: ['gap', 'gapColor', 'pad'],
        // The cell around a row, and a picture's cell: where its bar, medium and label stand.
        rowCell: ['bar', 'barOver', 'med', 'medFull', 'label', 'labelSub', 'ink', 'soft'],
        plot: ['ground', 'groundOpacity', 'spread'],
        inset: ['frame'],
        pic: ['med', 'medFull', 'label', 'ink', 'soft'],
    };
    const handOf = (kind) => ({ ...(plateStyle().hand?.[kind] ?? {}) });
    function handTake(kind, from) {
        if (!from || !HAND_KEYS[kind]) return;
        /* A ROW'S BRACKETS ARE REMEMBERED AS A YES, not by their words: the
           next row may narrow to Ornament where this one showed
           Chrysozystide — what carries over is „say what it narrows to". */
        if (kind === 'rowCell') from = { ...from, labelSub: (from.labelMarks ?? []).length > 0 };
        const now = {};
        for (const key of HAND_KEYS[kind]) {
            if (from[key] !== undefined && from[key] !== null) now[key] = from[key];
        }
        // A values text keeps which of the three lines it shows — its parts are its layer's.
        if (kind === 'values' && Array.isArray(now.lines)) now.lines = now.lines.filter(k => VALUE_KEYS.includes(k));
        const hand = plateStyle().hand ?? {};
        if (JSON.stringify(hand[kind] ?? {}) === JSON.stringify(now)) return;
        remember('hand', { ...hand, [kind]: now });
    }
    /** What a new picture's cell starts with. */
    const cellHand = () => handOf('pic');
    /** What a new row's cell starts with — its brackets filled from its own rows. */
    function rowHand(block) {
        const { labelSub, ...hand } = handOf('rowCell');
        return labelSub ? { ...hand, labelMarks: rowLabelSubs(block, rowsFor(pics, block, styleNow())) } : hand;
    }
    /* WHAT THE PANEL SHOWS, for the hand to read after every change in it —
       one listener on the panel instead of a call in each of its thirty
       controls, so a control added later is remembered without being told.
       The controls change their thing first (their own listeners run before
       the event reaches the panel), so the snapshot is the new state. */
    let handAt = null;   // {kind, d, cell} — set by panelFill
    function handSoon() {
        if (!handAt) return;
        const { kind, d, cell } = handAt;
        if (kind === 'pic') return handTake('pic', cell);
        if (kind === 'row') {
            handTake('row', d);
            if (cell) handTake('rowCell', cell);
            return;
        }
        handTake(kind, d);
    }
    selBox.addEventListener('change', handSoon);
    selBox.addEventListener('click', handSoon);

    /* THE FIND'S OWN MARGINS (migration 0156, 07.10.2026) lie over the
       account's per layer, as in the photo window: a row without its own
       air takes the find's — an Ascobolus row its 250 %, the next find's
       spores their snug 118. Fetched with the find's plates; every drawing
       that asks a style asks this one. */
    let findAir = {};
    const styleNow = () => {
        const style = host.style();
        return style ? { ...style, spore_air: { ...(style.spore_air ?? {}), ...findAir } } : style;
    };

    /* --- The row in „Belege" ---------------------------------------------
       ABOVE THE HAUPTMOTIV („im Punktefenster noch über dem
       Hauptmotiv diese Kacheln anbieten"): one tile per plate, its first
       page drawn small from the thumbnails. The new one is a button among
       Aufnehmen and Auswählen — what one DOES stands at the top. */
    async function show(id) {
        /* ANOTHER FIND EMPTIES THE ROW AT ONCE; the same one keeps its tiles
           until the answer — the gallery redraws after every upload, and a
           row that blinked each time would look like it lost something. */
        if (id !== pointId) {
            plates = [];
            findAir = {};
            row.textContent = '';
            row.hidden = true;
        }
        pointId = id;
        if (!id || id < 0 || !navigator.onLine) return;
        // The margins come beside the plates; the tiles redraw with them.
        host.request(`measure_style.php?point=${id}`)
            .then((air) => {
                if (id !== pointId) return;
                findAir = air?.air ?? {};
                if (plates.length > 0) drawRow();
            })
            .catch(() => {});
        let answer;
        try {
            answer = await host.request(`plates.php?point=${id}`);
        } catch {
            return;
        }
        if (id !== pointId) return;
        ready = answer?.ready === true;
        cloudReady = answer?.cloud === true;
        plates = answer?.plates ?? [];
        drawRow();
    }

    /* ONE PLACE FOR THE PLATES (06.10.2026: „das Label Tafel
       anzeigen, darunter einen Knopf Neue Tafel erstellen, falls keine Tafel
       erstellt wurde. Wenn eine da ist, einfach nur die Vorschau der
       Tafel"). The button beside Aufnehmen and Auswählen, the heading and
       the tile were three things for one; now the heading stands over the
       viewer's own plate — or the offer to make it —, and after it the
       other co-owners' plates, „von …" (each his own, plates.php says why).
       A tile is the way into its plate. The offer only where it can be
       taken: at the desk, for the holder and the co-owners, once there is
       a picture to lay out — on the phone without a plate the block stays
       away rather than stand as a heading over nothing. */
    function drawRow() {
        row.textContent = '';
        const pictures = picturesOf(pointId);
        /* ONE PICTURE IS ENOUGH (12.10.2026, first „mindestens zwei
           Bilder", then the same evening: „man kann ja auch auf einem
           einzelnen Foto seine Fundangaben einblenden wollen. Oder vielleicht
           sogar irgendwas gemessen haben mit einer Statistik"). */
        const offer = ready && !plates.some(p => p.mine) && desk.matches && host.mayMake(pointId) && pictures.length > 0;
        row.hidden = plates.length === 0 && !offer;
        if (row.hidden) return;
        const head = document.createElement('h4');
        head.className = 'photo-group-title';
        head.textContent = plates.length > 1 ? t('Tafeln') : t('Tafel');
        row.append(head);
        if (offer) {
            const make = document.createElement('button');
            make.type = 'button';
            make.className = 'button subtle small plate-make';
            make.innerHTML = iconHtml('plate') + esc(t('Neue Tafel erstellen'));
            make.addEventListener('click', () => openPlate(null));
            row.append(make);
        }
        const byKey = new Map(pictures.map(p => [p.key, p]));
        const details = host.details(pointId);
        for (const p of plates) {
            const tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'plate-tile';
            const pages = p.layout.pages?.length ?? 0;
            tile.title = pages === 1 ? t('Tafel, eine Seite') : t('Tafel, %d Seiten', pages);
            // The one in the editor is drawn live — its picture in the cloud may lag a few seconds behind.
            miniPage(tile, p, MINI_W, byKey, details, win.open && plate?.id === p.id);
            if (pages > 1) {
                const count = document.createElement('span');
                count.className = 'plate-tile-count';
                count.textContent = String(pages);
                tile.append(count);
            }
            if (!p.mine) {
                const by = document.createElement('span');
                by.className = 'plate-tile-by';
                by.textContent = t('von %s', personName(p.maker));
                tile.append(by);
            }
            // The plate standing in the editor is marked — in the Fundbrowser's tab it stays open beside the row.
            tile.classList.toggle('is-current', win.open && plate !== null && plate.id !== null && plate.id === p.id);
            tile.addEventListener('click', () => openPlate(p));
            row.append(tile);
        }
    }

    /**
     * A plate's first page, small: THE PICTURE KEPT IN THE CLOUD where
     * there is one (plate_images.php, 13.10.2026: „damit man da schnell eine
     * Vorschau hat und nicht die Tafel jedes Mal neu bauen muss"), else —
     * or when it will not load, or $live — drawn here from the pictures'
     * previews, as before.
     */
    function miniPage(into, p, width, byKey, details, live = false) {
        const mini = document.createElement('div');
        mini.className = 'plate-page plate-mini';
        mini.style.width = width + 'px';
        mini.style.height = width * 297 / 210 + 'px';
        into.append(mini);
        if (!p.layout.pages?.[0]) return mini;
        const draw = () => {
            mini.textContent = '';
            /* The face draws from the pictures' large files, the row's tiles from their previews (below). */
            const ctx = { layout: p.layout, page: p.layout.pages[0], mm: width / 210, byKey, details, style: styleNow(), thumbs: width < 160, editable: false };
            /* A find's face is a page to read (face, below) and is set
               large like the reading page; the strip's tiles, 64 px wide,
               show no readable word anyway and stay as they are — a page
               of 1260 px per tile would be work for nothing. */
            const drawn = width >= 160 ? drawPageShown(mini, ctx, width, width * 297 / 210) : (drawPage(mini, ctx), { root: mini, ctx });
            /* PLACED AGAIN WHEN IT COMES INTO SIGHT: the row is drawn while
               „Belege" may still be a hidden tab, and a cell that is not
               laid out measures nothing — the pictures would stand at 0 × 0
               until the next drawing. */
            new ResizeObserver(() => layoutAll(drawn.root, drawn.ctx)).observe(mini);
        };
        const kept = (p.images ?? []).find(x => x.page === 1);
        if (live || !kept) {
            draw();
            return mini;
        }
        const img = document.createElement('img');
        img.className = 'plate-mini-image';
        img.alt = '';
        img.draggable = false;
        img.addEventListener('error', draw, { once: true });
        const address = `${window.START_DATA.baseUrl}pages/plate_image.php?id=${p.id}&page=1&v=${kept.hash}`;
        img.src = address;
        mini.append(img);
        /* THE FACE IN FULL (18.11.2026: „bei der Übersicht, bei
           der Tafelansicht immer die hochauflösende Tafel laden? Nicht nur
           das Vorschaubild"). The kept preview is 640 px on its long edge
           (plate_images.php) and stood soft at the face's size. It still
           comes first, at once; the page from the maker's cloud follows and
           takes its place once it is whole — where the cloud does not
           answer, the preview simply stays. The row's tiles keep the
           preview: 64 px show no more of a large one. */
        if (width >= 160) {
            const large = new Image();
            large.addEventListener('load', () => {
                if (img.isConnected) img.src = large.src;
            }, { once: true });
            large.src = address + '&large=1';
        }
        return mini;
    }

    /* --- Opening and closing -------------------------------------------- */

    /**
     * A PICTURE IN THE VIEWER, AND BACK (08.10.2026: „wenn ich in
     * der Tafel auf ein Bild doppelt klicke, sollte es im Bildbetrachter
     * aufgehen, und wenn ich den Bildbetrachter dann schließe, sollte ich
     * automatisch wieder in der Tafel landen"). The plate stays open under
     * the viewer — it is a window over a window —, and what the viewer
     * changed (a measurement, a text, a scale) is read again when it closes:
     * the pictures, the details, the strip, the page. Since 08.11.2026 the
     * way in is the button in the cell's tools; the double click opens the
     * cutout instead (render).
     */
    function viewPicture(key) {
        if (!key || !host.openPicture?.(pointId, key)) return;
        document.getElementById('photo-window').addEventListener('close', () => {
            if (!win.open) return;
            pics = new Map(picturesOf(pointId).map(x => [x.key, x]));
            values = host.details(pointId);
            stripDraw();
            render();
            imagesSoon();
        }, { once: true });
    }

    function open(p) {
        pics = new Map(picturesOf(pointId).map(x => [x.key, x]));
        values = host.details(pointId);
        plate = p ?? { id: null, mine: true, may_edit: true, maker: '' };
        const hand = plateStyle();
        layout = p ? JSON.parse(JSON.stringify(p.layout))
            : { v: 1, gutter: hand.gutter, gutterColor: hand.gutter_color, pages: [{ root: cellNew() }] };
        layout.pages.forEach(page => adopt(page.root));
        // A new plate is „saved" as its empty page: quiet until something lands on it.
        savedJson = JSON.stringify(bare(layout));
        // Each co-owner's plate is his own: the others look, print, take the PDF.
        editable = plate.may_edit === true && desk.matches;
        pageAt = 0;
        selected = null;
        sideShown = null;
        drafts.clear();
        chosenDraft = null;
        // A fresh plate starts with a clean autosave slate.
        clearTimeout(autosaveTimer);
        failedJson = null;
        savedSay('');
        sideTab('general');
        framing = null;
        view = 1;
        zoomSync();
        win.classList.toggle('is-reading', !editable);
        deleteBtn.hidden = !editable || plate.id === null;
        note.textContent = plate.may_edit ? '' : t('Tafel von %s — nur zum Ansehen.', personName(plate.maker));
        stripDraw();
        /* IN THE PAGE OR OVER IT (host.inPage, the Fundbrowser's tab
           „Tafeln", 12.10.2026): opened with show(), the editor is
           a box of the page beside the tabs, without a backdrop. A dialog
           that is open already stays as it is — show() and showModal()
           refuse each other. */
        if (!win.open) {
            if (host.inPage?.()) win.show();
            else win.showModal();
            /* THE WINDOW ITSELF TAKES THE FOCUS (30.10.2026: after
               a reload „+ Seite" stood ringed blue and white, as if pressed,
               though nobody had touched it). show() and showModal() hand the
               focus to the first control they find — the close cross is
               hidden in the page, the page buttons come next, then „+ Seite"
               — and a focus nobody gave by mouse is drawn as the keyboard's
               ring. The photo window does the same (photowindow.js). */
            win.focus({ preventScroll: true });
        }
        render();
        drawRow();
        // A measurement made since the pages were drawn makes them old — they follow at rest.
        imagesSoon();
    }

    /**
     * Open a plate where one may already stand: the one open is closed first
     * — saved, or asked about when that fails — and only then the next
     * opened. In the Fundbrowser's tab the editor stays open between plates;
     * over the map the window is shut whenever another is opened anyway.
     * True when the plate came.
     */
    async function openPlate(p) {
        if (win.open && !(await close())) return false;
        open(p);
        return true;
    }

    const dirty = () => editable && JSON.stringify(bare(layout)) !== savedJson;

    /* CLOSING SAVES WHAT IS STILL OPEN — the autosave's last second is
       not waited for. Only a save that fails asks: the changes would be
       lost, so Verwerfen is a choice and „Offen lassen" the other (the
       point window's question, map.js leaveOk, in the same spirit). */
    async function close() {
        if (!win.open) return true;
        clearTimeout(autosaveTimer);
        if (dirty() && !await save()) {
            const answer = await ask({
                title: t('Nicht gespeichert'),
                text: t('Die Tafel ließ sich gerade nicht speichern. Die letzten Änderungen gehen verloren, wenn du sie jetzt schließt.'),
                buttons: [
                    { label: t('Verwerfen'), value: 'discard', danger: true },
                    { label: t('Offen lassen'), value: 'stay' },
                ],
            });
            if (answer !== 'discard') return false;
        }
        imagesNow();
        win.close();
        drawRow();
        return true;
    }
    document.getElementById('plate-close-top').addEventListener('click', close);
    /* ESCAPE NEVER CLOSES THE PLATE (07.10.2026: „ein Escape zu
       viel würde den Bildbetrachter und das Tafelfenster ebenso schließen").
       The key's own default goes in the capture phase, so no close request
       comes at all — refusing the cancel would not hold, Chrome closes on a
       second Escape in a row regardless. What Escape still does (fold the
       export menu, close a cutout) the keydown handler below does; the
       window closes by its buttons, unsaved work asked about there. */
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && win.open
            && (win.contains(e.target) || e.target === document.body || e.target === document.documentElement)) {
            e.preventDefault();
        }
    }, true);
    // What still arrives as a cancel is no key: the phone's back gesture.
    win.addEventListener('cancel', (e) => {
        e.preventDefault();
        if (framing) { framing = null; render(); return; }
        close();
    });

    /* AUTOSAVE (07.10.2026: „bei der Tafel genauso wie beim
       Messen ein Auto-Speichern"). A second after the last change the plate
       goes to the server by itself — every change passes saveSync, which
       (re)starts the clock, so a drag or a slider saves once, at rest. A
       new plate comes into being with the first thing laid on it; an empty
       one is never stored. One save at a time: what changes while one is on
       its way goes with the next. A save that fails is not repeated every
       second — the next change tries again, and closing asks. */
    /* THE DRAWN PAGES INTO THE CLOUD (13.10.2026, includes/plate_images.php).
       Each page of one's own plate is drawn as the export draws it and laid
       into one's cloud as „… 00 Tafel.jpg", on top of the find's pictures —
       the row's tile and the Fundbrowser show that picture instead of
       building the page from every picture again. AT REST, NOT AT EVERY
       SAVE: fifteen seconds after the last save, and at once when the
       editor closes; drawing a page loads the originals of a row and takes
       seconds. A page whose hash the server already holds is not drawn at
       all — the hash is what the page is drawn FROM (pageHash), so a
       measurement made in the viewer makes the page old although its
       layout stayed. One run at a time, in the background; it says
       nothing when it works and nothing when it fails — the page is a
       convenience, made anew at the next rest. */
    const IMAGE_DPI = 200;
    const IMAGE_REST_MS = 15000;
    let imagesTimer = null;
    let imaging = null;
    let imagesAgain = false;  // asked while a run was on its way — one more run after it
    function imagesSoon() {
        clearTimeout(imagesTimer);
        if (cloudReady && editable && plate?.id) imagesTimer = setTimeout(imagesNow, IMAGE_REST_MS);
    }

    /** What a page is drawn from, as a short hash: the page, the plate's settings, every picture's measures and scale, the details, the drawing's style. */
    function pageHash(lay, i, from) {
        const pictures = [...from.pics.values()].map(p => [p.key, p.w, p.h, p.scale ?? null, p.measures ?? []]);
        const text = JSON.stringify([lay.pages[i], lay.pages.length, lay.gutter, lay.gutterColor, lay.notePt ?? null,
            pictures, from.values, from.style]);
        // cyrb53 — enough to tell one drawing from the next, not a secret.
        let h1 = 0xdeadbeef, h2 = 0x41c6ce57;
        for (let k = 0; k < text.length; k++) {
            const c = text.charCodeAt(k);
            h1 = Math.imul(h1 ^ c, 2654435761);
            h2 = Math.imul(h2 ^ c, 1597334677);
        }
        h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
        h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);
        return (4294967296 * (2097151 & h2) + (h1 >>> 0)).toString(36);
    }

    function imagesNow() {
        clearTimeout(imagesTimer);
        if (imaging) {
            imagesAgain = true;
            return imaging;
        }
        if (!cloudReady || !editable || !plate?.id) return null;
        // What the SERVER holds — the page count the store checks against — with the pictures as they are now.
        const from = { id: plate.id, layout: JSON.parse(savedJson), pics, values, style: styleNow(),
                       stored: new Map((plate.images ?? []).map(x => [x.page, x.hash])) };
        imaging = imagesDraw(from).catch((e) => console.warn('Tafel nicht in die Cloud gelegt:', e))
            .finally(imagesDone);
        return imaging;
    }

    function imagesDone() {
        imaging = null;
        if (imagesAgain) {
            imagesAgain = false;
            imagesNow();
        }
    }

    /**
     * A PAGE MISSING OR OLD, MENDED WHERE IT IS SEEN (18.11.2026:
     * „wenn dort eine fehlt und du sie eh neu zeichnest, kannst du sie dann
     * auch in die Cloud speichern, weil dann war man wohl beim Tafelerstellen
     * irgendwo zu schnell"). The editor lays its pages down only at rest and
     * on closing; whoever left before that, or measured anew in the viewer
     * since, finds the face drawn live or the old page. So the face asks
     * here, for a plate of ONE'S OWN — the page goes into the maker's cloud,
     * nobody else's —, at the desk, with a cloud: the same drawing and the
     * same hash as the editor's (pageHash), so a page that is current is
     * never drawn twice. True when a page was laid down, and the face is
     * drawn again from it. The plate open in the editor is the editor's.
     */
    /* ONE RUN PER PLATE, AND WHAT IT LAID REMEMBERED: the Fundbrowser draws
       its face several times while it loads, partly from plate records
       fetched before the page was laid — each call asked anew and the same
       page went up five times (measured 18.11.2026). Calls while a run is
       on its way wait for it; the pages laid in this visit count as held. */
    const ensuring = new Map();   // plate id -> its run
    const laidPages = new Map();  // `${plate id}/${page}` -> the kept page laid in this visit
    /** A plate's kept pages with those laid in this visit — a page that was missing among them too. */
    const imagesOf = (p) => {
        const out = new Map((p.images ?? []).map(x => [x.page, x]));
        for (const [key, kept] of laidPages) if (key.startsWith(p.id + '/')) out.set(kept.page, kept);
        return [...out.values()];
    };
    function imagesEnsure(p) {
        if (!cloudReady || p?.may_edit !== true || !p.id || !desk.matches || !p.layout?.pages) return Promise.resolve(false);
        if (win.open && plate?.id === p.id) return Promise.resolve(false);
        if (ensuring.has(p.id)) return ensuring.get(p.id).then(() => false);
        const run = imagesEnsureRun(p).finally(() => ensuring.delete(p.id));
        ensuring.set(p.id, run);
        return run;
    }
    async function imagesEnsureRun(p) {
        while (imaging) await imaging;
        /* THE LAYOUT AS THE EDITOR HASHES IT — adopted (its defaults filled
           in) and bare (its ids taken off), the way open() and saveNow make
           savedJson. Hashed raw, as the server sent it, the same page gave
           another hash, and the editor and the face drew it over each other
           in turn (measured 18.11.2026). */
        const lay = JSON.parse(JSON.stringify(p.layout));
        lay.pages.forEach(page => adopt(page.root));
        const from = { id: p.id, layout: bare(lay), pics: new Map(picturesOf(pointId).map(x => [x.key, x])),
                       values: host.details(pointId), style: styleNow(),
                       stored: new Map(imagesOf(p).map(x => [x.page, x.hash])) };
        if (from.layout.pages.every((_, i) => from.stored.get(i + 1) === pageHash(from.layout, i, from))) return false;
        let laid = 0;
        imaging = imagesDraw(from).then((n) => { laid = n; }).catch((e) => console.warn('Tafel nicht in die Cloud gelegt:', e))
            .finally(imagesDone);
        await imaging;
        return laid > 0;
    }

    /** Draw the pages of `from` whose hash the server does not hold and lay them into the cloud; how many were laid. */
    async function imagesDraw(from) {
        let laid = 0;
        for (let i = 0; i < from.layout.pages.length; i++) {
            const hash = pageHash(from.layout, i, from);
            if (from.stored.get(i + 1) === hash) continue;
            const drawn = await renderPage({ layout: from.layout, page: from.layout.pages[i], pics: from.pics,
                values: from.values, style: from.style, dpi: IMAGE_DPI });
            // A picture that would not load makes a page with a hole — that one is not kept.
            if (drawn.missing > 0) continue;
            const { bytes } = await jpegBytes(drawn.canvas, IMAGE_DPI);
            const form = new FormData();
            form.append('action', 'image');
            form.append('id', String(from.id));
            form.append('page', String(i + 1));
            form.append('hash', hash);
            form.append('image', new Blob([bytes], { type: 'image/jpeg' }), 'tafel.jpg');
            const answer = await host.request('plates.php', { method: 'POST', body: form });
            if (answer?.stored !== true) {
                cloudReady = false;
                return laid;
            }
            laid++;
            const kept = { page: answer.page, hash: answer.hash, width: answer.width, height: answer.height };
            laidPages.set(`${from.id}/${kept.page}`, kept);
            for (const p of [plate, ...plates]) {
                if (p?.id !== from.id) continue;
                p.images = [...(p.images ?? []).filter(x => x.page !== kept.page), kept];
            }
        }
        if (!win.open || plate?.id !== from.id) drawRow();
        return laid;
    }

    const AUTOSAVE_MS = 1000;
    let autosaveTimer = null;
    let saving = null;        // the save on its way, a promise
    let failedJson = null;    // what the last failed save sent
    /* „GESPEICHERT" GOES AGAIN after a few seconds (30.10.2026:
       the line „nicht automatisch nach einer Zeit verschwindet"). It answers
       the change just made and has said so; standing on, it reads like a
       state one has to mind. „Wird gespeichert …" and „Nicht gespeichert"
       stay — the first ends by itself, the second waits for the next try. */
    const SAVED_SHOWN_MS = 3000;
    let savedFade = null;
    function savedSay(state) {
        clearTimeout(savedFade);
        savedLine.textContent = { saving: t('Wird gespeichert …'), saved: t('Gespeichert'),
            failed: t('Nicht gespeichert'), '': '' }[state];
        savedLine.classList.toggle('is-failed', state === 'failed');
        if (state === 'saved') savedFade = setTimeout(() => savedSay(''), SAVED_SHOWN_MS);
    }

    /** Save the plate; true when it went through. */
    async function save() {
        clearTimeout(autosaveTimer);
        autosaveTimer = null;
        if (saving) {
            await saving;
            if (!dirty()) return true;
        }
        const sent = JSON.stringify(bare(layout));
        savedSay('saving');
        saving = (async () => {
            try {
                const answer = await host.request('plates.php', {
                    method: 'POST',
                    // The version this editor began from: the same plate saved on
                    // another device meanwhile refuses rather than be overwritten.
                    body: JSON.stringify({ point_id: pointId, id: plate.id ?? 0, since: plate.updated_at ?? null, layout: JSON.parse(sent) }),
                });
                const saved = answer.plate;
                plate = saved;
                savedJson = JSON.stringify(bare(saved.layout));
                failedJson = null;
                /* THE SERVER'S SHAPE IS THE TRUTH from here: it dropped what it
                   does not keep (a picture deleted meanwhile). The working copy
                   takes it over only where it differs — and only if nothing was
                   changed while the save was on its way; otherwise the newer
                   work stays and goes with the next save. */
                if (savedJson !== sent && JSON.stringify(bare(layout)) === sent) {
                    /* WHAT WAS CHOSEN STAYS CHOSEN. The server fills in its
                       defaults, so the first save of a new tile already comes
                       back different — and the tile one had just dropped and
                       was setting lost its panel a second later. The cells
                       keep their order, so the chosen one and an open cutout
                       are found again by their place on the page. */
                    const cellsNow = cellsOf(layout.pages[pageAt].root);
                    const kept = {
                        cell: selected ? cellsNow.indexOf(selected.cell) : -1,
                        item: selected?.item ? selected.cell.items.indexOf(selected.item) : -1,
                        framing: framing ? cellsNow.indexOf(framing) : -1,
                    };
                    layout = JSON.parse(savedJson);
                    layout.pages.forEach(page => adopt(page.root));
                    const cellsNew = cellsOf(layout.pages[pageAt].root);
                    const cell = cellsNew[kept.cell] ?? null;
                    selected = cell ? { cell, item: kept.item >= 0 ? cell.items[kept.item] ?? null : null } : null;
                    if (selected && !selected.item && !selected.cell.info && !selected.cell.block && !selected.cell.pic) selected = null;
                    framing = cellsNew[kept.framing] ?? null;
                    render();
                }
                const at = plates.findIndex(x => x.id === saved.id);
                // One's own plate stands first in the row.
                if (at === -1) plates.unshift(saved); else plates[at] = saved;
                deleteBtn.hidden = false;
                drawRow();
                savedSay('saved');
                imagesSoon();
                return true;
            } catch (e) {
                failedJson = sent;
                savedSay('failed');
                toast(e.message || t('Speichern ging nicht.'), 'error');
                return false;
            }
        })();
        try {
            return await saving;
        } finally {
            saving = null;
            saveSync();
        }
    }

    deleteBtn.addEventListener('click', async () => {
        if (plate.id === null) return;
        // No save may bring it back while the question stands.
        clearTimeout(autosaveTimer);
        const yes = await confirm({
            title: t('Tafel löschen?'),
            text: t('Die Tafel ist dann weg. Die Bilder darin bleiben, wo sie sind.'),
            button: t('Löschen'), danger: true,
        });
        if (!yes) return;
        try {
            await host.request('plates.php', { method: 'POST', body: JSON.stringify({ action: 'delete', id: plate.id }) });
            plates = plates.filter(x => x.id !== plate.id);
            savedJson = JSON.stringify(bare(layout));   // nothing left to lose
            win.close();
            drawRow();
            toast(t('Tafel gelöscht.'));
        } catch (e) {
            toast(e.message || t('Löschen ging nicht.'), 'error');
        }
    });

    /** Every change passes here (render, the sliders): the autosave's
     *  clock starts again while there is something to save. */
    function saveSync() {
        if (!editable || !dirty() || saving) return;
        const now = JSON.stringify(bare(layout));
        if (now === failedJson) return;
        if (savedLine.textContent === t('Gespeichert')) savedSay('');
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(() => save(), AUTOSAVE_MS);
    }

    /* --- Pages ------------------------------------------------------------ */
    document.getElementById('plate-page-add').addEventListener('click', () => {
        layout.pages.push({ root: cellNew() });
        pageAt = layout.pages.length - 1;
        selected = null;
        render();
    });
    document.getElementById('plate-page-remove').addEventListener('click', async () => {
        if (layout.pages.length < 2) return;
        const used = cellsOf(layout.pages[pageAt].root).some(c => c.pic || c.info || c.items.length);
        if (used && !await confirm({
            title: t('Seite entfernen?'),
            text: t('Die Seite %d und was auf ihr steht, gehen von der Tafel. Die Bilder selbst bleiben.', pageAt + 1),
            button: t('Entfernen'), danger: true,
        })) return;
        layout.pages.splice(pageAt, 1);
        pageAt = Math.min(pageAt, layout.pages.length - 1);
        selected = null;
        render();
    });
    /* --- Out of the window: PDF and picture, under „Export" ------------------
       THE PAGE IN WORK IS WHAT GOES OUT, saved or not — the plate one looks
       at is the plate one gets. Drawn page by page at 300 dpi
       (plateprint.js); the line at the foot says where the drawing is,
       because a page with originals to fetch takes its seconds.
       NO PRINTER OF ITS OWN since 07.10.2026 („wir bieten kein
       Drucken an") — the PDF is the way onto paper, its line says so. */
    const exportToggle = document.getElementById('plate-export');
    const exportMenu = document.getElementById('plate-export-menu');
    const exportButtons = [exportToggle, ...exportMenu.querySelectorAll('button')];
    let exportPinned = false;   // opened by a click: it stays until a click or a press elsewhere
    let exportLeaveTimer = null;
    function exportMenuShow(open) {
        exportMenu.hidden = !open;
        exportToggle.setAttribute('aria-expanded', String(open));
        if (!open) exportPinned = false;
    }
    /* OPEN UNDER THE POINTER (09.11.2026: „das Exportmenü soll
       automatisch aufklappen beim Überfahren"): the mouse over „Export"
       opens it, and it folds a moment after the pointer has left button and
       menu — the gap between them is crossed within that moment. A click
       pins it open (a second click, or a press elsewhere, folds it), so the
       click that often follows the hover does not shut what just opened. */
    const exportBox = exportToggle.closest('.plate-export');
    exportBox.addEventListener('pointerenter', (e) => {
        if (e.pointerType !== 'mouse') return;
        clearTimeout(exportLeaveTimer);
        if (exportMenu.hidden) exportMenuShow(true);
    });
    exportBox.addEventListener('pointerleave', (e) => {
        if (e.pointerType !== 'mouse' || exportPinned) return;
        clearTimeout(exportLeaveTimer);
        exportLeaveTimer = setTimeout(() => { if (!exportPinned) exportMenuShow(false); }, 250);
    });
    exportToggle.addEventListener('click', () => {
        clearTimeout(exportLeaveTimer);
        if (exportMenu.hidden) { exportMenuShow(true); exportPinned = true; }
        else if (exportPinned) exportMenuShow(false);
        else exportPinned = true;
    });
    // A press anywhere else folds it, as every menu does.
    win.addEventListener('pointerdown', (e) => {
        if (!exportMenu.hidden && !e.target.closest('.plate-export')) exportMenuShow(false);
    });
    win.addEventListener('close', () => exportMenuShow(false));

    async function pagesOut(indexes) {
        exportButtons.forEach(b => { b.disabled = true; });
        const before = note.textContent;
        const out = [];
        let missing = 0;
        try {
            for (const [n, i] of indexes.entries()) {
                const say = (done, total) => {
                    note.textContent = indexes.length > 1
                        ? t('Seite %d von %d, Kachel %d von %d …', n + 1, indexes.length, done + 1, total)
                        : t('Kachel %d von %d wird gezeichnet …', done + 1, total);
                };
                const drawn = await renderPage({ layout, page: layout.pages[i], pics, values, style: styleNow(), say });
                missing += drawn.missing;
                out.push(await jpegBytes(drawn.canvas));
                // Thirty-five megabytes of pixels per page — let go at once.
                drawn.canvas.width = drawn.canvas.height = 0;
            }
        } finally {
            exportButtons.forEach(b => { b.disabled = false; });
            note.textContent = before;
        }
        if (missing > 0) {
            toast(missing === 1
                ? t('Ein Bild ließ sich nicht laden — seine Kachel blieb weiß.')
                : t('%d Bilder ließen sich nicht laden — ihre Kacheln blieben weiß.', missing), 'error');
        }
        return out;
    }
    /** Which plate of the find this is, for the file's name. */
    function plateNumber() {
        const at = plates.findIndex(p => p.id === plate.id);
        return at === -1 ? plates.length + 1 : at + 1;
    }
    function hand(blob, name) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 60000);
    }
    const allPages = () => layout.pages.map((_, i) => i);
    document.getElementById('plate-pdf').addEventListener('click', async () => {
        exportMenuShow(false);
        try {
            const base = fileBase(values, plateNumber());
            hand(pdfBlob(await pagesOut(allPages()), base), base + '.pdf');
        } catch (e) {
            console.warn('Tafel nicht gerechnet:', e);
            toast(t('Die Tafel ließ sich nicht rechnen.'), 'error');
        }
    });
    document.getElementById('plate-image').addEventListener('click', async () => {
        exportMenuShow(false);
        try {
            const [page] = await pagesOut([pageAt]);
            const name = fileBase(values, plateNumber()) + (layout.pages.length > 1 ? ' ' + t('Seite %d', pageAt + 1) : '');
            hand(new Blob([page.bytes], { type: 'image/jpeg' }), name + '.jpg');
        } catch (e) {
            console.warn('Tafel nicht gerechnet:', e);
            toast(t('Die Tafel ließ sich nicht rechnen.'), 'error');
        }
    });
    for (const input of win.querySelectorAll('input[name="plate-gutter"], input[name="plate-gutter-color"]')) {
        input.addEventListener('change', () => {
            if (input.name === 'plate-gutter') {
                layout.gutter = Number(input.value);
                remember('gutter', layout.gutter);
            } else {
                layout.gutterColor = input.value;
                remember('gutter_color', layout.gutterColor);
            }
            render();
        });
    }

    /* THE WRITING'S SIZE, one for the whole plate (08.10.2026), in the same
       steps as every other text; unset, it follows the account's text size. */
    const noteSize = document.getElementById('plate-note-size');
    for (const [id, dir] of [['plate-note-smaller', -1], ['plate-note-bigger', 1]]) {
        document.getElementById(id).addEventListener('click', () => {
            const now = notePtOf(layout, host.style());
            const at = PT_STEPS.findIndex(p => p >= now);
            layout.notePt = PT_STEPS[clamp((at === -1 ? PT_STEPS.length - 1 : at) + dir, 0, PT_STEPS.length - 1)];
            render();
        });
    }

    /* --- Drawing ---------------------------------------------------------- */
    /** The page's height on the screen: as large as the stage lets an A4
     *  stand, times the zoom. */
    function pageHeight() {
        // The stage's padding is the stylesheet's — wider on top, for the cells' tools.
        const pad = getComputedStyle(stage);
        /* THE STAGE'S WHOLE BOX, SCROLLBARS INCLUDED (08.11.2026:
           „springt mir komischerweise die Tafel immer mal ein Stück kleiner,
           dann wieder ein Stück größer", at a zoom where the page about
           filled the width, on every A+). clientWidth leaves the scrollbars
           out, and whether they stand depends on the page drawn LAST: page
           a bit too large → scrollbar → stage narrower → page smaller → no
           scrollbar → page larger again, one step per redraw. The box
           itself (offsetWidth/Height, the stage has no border) does not
           change with what lies in it, so the page's size depends on the
           zoom alone. */
        const availH = stage.offsetHeight - parseFloat(pad.paddingTop) - parseFloat(pad.paddingBottom);
        const availW = stage.offsetWidth - parseFloat(pad.paddingLeft) - parseFloat(pad.paddingRight);
        return Math.max(200, Math.min(availH, availW * 297 / 210)) * view;
    }

    function render() {
        if (!win.open) return;
        if (framing && !cellsWithInsets(layout.pages[pageAt].root).includes(framing)) framing = null;
        const h = pageHeight();
        pageEl.style.width = h * 210 / 297 + 'px';
        pageEl.style.height = h + 'px';
        pageMm = drawPageShown(pageEl, { layout, page: layout.pages[pageAt], mm: h / 297, byKey: pics, details: values, style: styleNow(), thumbs: false, editable },
            h * 210 / 297, h).ctx.mm;
        markSelected();
        const opened = pageEl.querySelector('.plate-cell.is-framing');
        if (opened) fitTools(opened);
        // A redraw puts new cells under a pointer that did not move — no
        // pointerenter tells the one under it to place its tools.
        const under = lastPointer && document.elementFromPoint(...lastPointer)?.closest('#plate-page .plate-cell');
        toolsHeld = null;
        if (under && editable) {
            under.classList.add('is-tools');
            fitTools(under);
        }
        pagesDraw();
        panelSync();
        stripUsed();
        saveSync();
    }
    window.addEventListener('resize', () => { if (win.open) render(); });

    /*
     * AN ARROW IS SHAPED ON THE PLATE TOO (15.11.2026: „bei den
     * Tafeln ist es, glaube ich, sinnvoll … dass man dort dann solche Pfeile
     * und Texte auch editieren kann. Also … Größe ändern, Position
     * verschieben. Weil erst in der Tafel sieht man ja den Ausschnitt, den
     * man zeigen will" — and, asked whether that should hold for this plate
     * alone: „Überall gleich"). A click on an arrow chooses it, and it
     * carries the viewer's grips: a circle at each end, the square beside
     * the shaft for its strength („Stärke N"), the square at its words'
     * corner for their size („Größe N", the viewer's steps). What changes is
     * the PICTURE'S arrow, saved as the move below saves — the viewer and
     * every other plate showing that layer follow.
     *
     * A PLAIN TEXT HAS NO SIZE GRIP HERE, on purpose: on a plate its size is
     * the plate's own („Schriftgröße der Beschriftungen" under „Allgemein",
     * plateannot.js), and a text set larger in the viewer stays larger only
     * beside a smaller one — a grip would do nothing on a lone label. It
     * moves, as before.
     */
    let noteChosen = null;   // the chosen arrow (its measure object)
    function noteGrips(el, shapes, W, H) {
        const i = shapes.findIndex((sh) => sh.kind === 'arrow' && sh.ref === noteChosen);
        if (i < 0) return;
        const a = shapes[i];
        const words = shapes.findIndex((sh) => sh.kind === 'text' && sh.ref === noteChosen && sh.words);
        const ink = '#000000', seam = '#ffffff';
        const label = (x, y, text) => `<text x="${x}" y="${y}" font-size="11" font-weight="600" fill="${ink}"
            stroke="${seam}" stroke-width="2.5" paint-order="stroke" font-family="Arial, Helvetica, sans-serif">${esc(text)}</text>`;
        const square = (x, y, grip, at, turn = 0) => `<g class="plate-note-grip" data-i="${at}" data-grip="${grip}">
            <rect x="${x - 7}" y="${y - 7}" width="14" height="14" fill="transparent"/>
            <rect x="${x - 4.5}" y="${y - 4.5}" width="9" height="9" fill="${ink}" stroke="${seam}" stroke-width="1.5"
                  transform="rotate(${turn} ${x} ${y})"/></g>`;
        const circle = ([x, y], grip) => `<g class="plate-note-grip" data-i="${i}" data-grip="${grip}">
            <circle cx="${x}" cy="${y}" r="8" fill="transparent"/>
            <circle cx="${x}" cy="${y}" r="5" fill="${ink}" stroke="${seam}" stroke-width="1.5"/></g>`;
        // The strength square beside the shaft's middle, a little off it, as in the viewer.
        const off = a.half + 14;
        const [hx, hy] = [a.middle[0] + a.normal[0] * off, a.middle[1] + a.normal[1] * off];
        let inner = circle(a.tail, 'tail') + circle(a.tip, 'tip')
            + square(hx, hy, 'weight', i, Math.atan2(a.normal[1], a.normal[0]) * 180 / Math.PI)
            + label(hx + 9, hy + 4, t('Stärke %s', arrowWeightOf(a.ref)));
        if (words >= 0) {
            const w = shapes[words];
            const r = w.words;
            inner += `<rect x="${r.left - 3}" y="${r.top - 3}" width="${r.right - r.left + 6}" height="${r.bottom - r.top + 6}"
                    fill="none" stroke="${seam}" stroke-width="2"/>`
                + `<rect x="${r.left - 3}" y="${r.top - 3}" width="${r.right - r.left + 6}" height="${r.bottom - r.top + 6}"
                    fill="none" stroke="${ink}" stroke-width="1" stroke-dasharray="4 3"/>`
                + (() => {
                    // On the words' side away from the tail (measuredraw.js, arrowWordsGrip), its number beyond it.
                    const grip = arrowWordsGrip(a.tail, a.tip, { left: r.left - 3, top: r.top - 3, right: r.right + 3, bottom: r.bottom + 3 });
                    const [gx, gy] = grip.pos, [ax, ay] = grip.away;
                    // Under the grip (above it on the words' upper side) — beside it, the cell's edge cut it off.
                    const text = t('Größe %s', Math.round(w.px / w.per / (el.picture.pic.fullW ?? (a.ref.width || el.picture.pic.w)) * 1000));
                    return square(gx, gy, 'size', words)
                        + label(gx, gy + (ay < 0 ? -10 : 19), text).replace('<text ', '<text text-anchor="middle" ');
                })();
        }
        el.querySelector('.plate-notes').insertAdjacentHTML('afterend',
            `<svg class="plate-note-grips" viewBox="0 0 ${W} ${H}" aria-hidden="true">${inner}</svg>`);
    }
    /* Anything else clicked lets the arrow go — after the click has done
       its own work, so the page drawn anew does not take its target away. */
    pageEl.addEventListener('pointerdown', (e) => {
        if (noteChosen === null || e.target.closest?.('.plate-note-grip, .plate-note-shape')) return;
        noteChosen = null;
        setTimeout(() => render(), 0);
    }, true);

    /*
     * THE WRITING MOVES ON THE PLATE (30.10.2026: „wenn ich
     * direkt in der Tafel einen Pfeil, also eine Beschriftungsebene, noch
     * etwas verschieben könnte, weil ich erst dort sehe, welchen Ausschnitt
     * ich eigentlich zeige"). A text, an arrow or a counting box of a ticked
     * layer is taken by the pointer and moved whole; it is the PICTURE'S
     * writing that moves — one record, as every measure — so the viewer and
     * every other plate showing that layer see it moved too. Whoever wants
     * the same words placed twice, for two cutouts of one picture, puts
     * them on a second labelling layer (the same day: „legen wir
     * einfach eine zweite Beschriftungsebene an"). Only one's own pictures:
     * somebody else's writing is his. Saved as the viewer saves it, once,
     * when the pointer lets go.
     */
    function noteDrag(e) {
        const grip = e.target.closest?.('.plate-note-grip');
        const hit = grip ?? e.target.closest?.('.plate-note-shape');
        const el = hit?.closest('.plate-cell');
        const sh = el?.noteShapes?.[Number(hit.dataset.i)];
        if (!sh?.ref || e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();
        const svg = hit.ownerSVGElement;
        const box = svg.getBoundingClientRect();
        const onScreen = box.width / (svg.viewBox.baseVal.width || 1);
        // The pointer in the cell's own pixels, where the shapes are reckoned.
        const inCell = (ev) => [(ev.clientX - box.left) / onScreen, (ev.clientY - box.top) / onScreen];
        const m = sh.ref;
        const pic = el.picture.pic;
        const from = m.points.map(p => [...p]);
        const x0 = e.clientX, y0 = e.clientY;
        const kind = grip?.dataset.grip ?? 'move';
        const start = inCell(e);
        const baseWeight = arrowWeightOf(m);
        const baseSize = m.size ?? null;
        // The words' size: measured from the arrow's tail, which stays, as the viewer measures (photowindow.js, 'arrow-size').
        const tailShape = el.noteShapes.find((x) => x.kind === 'arrow' && x.ref === m);
        const far = sh.words && tailShape ? tailShape.tail : null;
        const startDist = far ? Math.max(1, Math.hypot(start[0] - far[0], start[1] - far[1])) : 1;
        // Sizes are thousandths of the WHOLE picture's width, as the viewer counts them (picView fullW).
        const width = pic.fullW ?? (m.width || pic.w);
        const baseShown = sh.words ? sh.px / sh.per / width * 1000 : 0;
        let moved = false;
        let frame = 0;
        const move = (ev) => {
            const dx = (ev.clientX - x0) / onScreen / sh.per;
            const dy = (ev.clientY - y0) / onScreen / sh.per;
            if (!moved && Math.hypot(ev.clientX - x0, ev.clientY - y0) < 3) return;
            moved = true;
            if (kind === 'tail' || kind === 'tip') {
                const k = kind === 'tail' ? 0 : 1;
                m.points = from.map((p, i) => (i === k ? [p[0] + dx, p[1] + dy] : [...p]));
            } else if (kind === 'weight') {
                // A step for every fourteen pixels away from the shaft, as in the viewer.
                const [px, py] = inCell(ev);
                const along = (px - start[0]) * sh.normal[0] + (py - start[1]) * sh.normal[1];
                // The words keep their size (measuredraw.js, arrowWeightSet).
                arrowWeightSet(m, { weight: baseWeight, size: baseSize },
                    Math.min(Math.max(baseWeight + Math.round(along / 14), 1), ARROW_WEIGHTS.length));
            } else if (kind === 'size') {
                const [px, py] = inCell(ev);
                const step = textStep(baseShown * Math.hypot(px - far[0], py - far[1]) / startDist);
                m.size = Math.round(step / 1000 * width / arrowTextLook(m).scale * 10) / 10;
            } else {
                m.points = from.map(([x, y]) => [x + dx, y + dy]);
            }
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => { if (el.isConnected) layoutCell(el, el.noteCtx); });
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);
            /* A CLICK WITHOUT MOVING CHOOSES AN ARROW: its grips come (noteGrips). */
            if (!moved) {
                if (m.type === 'arrow' && noteChosen !== m) {
                    noteChosen = m;
                    if (el.isConnected) layoutCell(el, el.noteCtx);
                }
                return;
            }
            cancelAnimationFrame(frame);
            /* The page is drawn anew — at a zoom the stage keeps its place,
               or the writing just set would jump out of sight. */
            const [left, top] = [stage.scrollLeft, stage.scrollTop];
            render();
            stage.scrollLeft = left;
            stage.scrollTop = top;
            /* On a cut picture the plate worked on the view's copy: the
               change goes back into the picture's own measure first, in its
               own pixels, and the picture record is what is saved — never
               the view's numbers (plateparts.js, measureBack). */
            measureBack(m);
            writingSave(pictureOf(pic));
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
    }
    // In the capture phase: a grip on the writing must reach nothing of the cell beneath it.
    pageEl.addEventListener('pointerdown', noteDrag, true);

    /** A picture's measures written back, by the viewer's own two ways. */
    function writingSave(pic) {
        const [kind, id] = pic.key.split(':');
        const sent = kind === 'cloud'
            ? host.request(`cloud.php?id=${pointId}&action=scale`, {
                method: 'POST', body: JSON.stringify({ file: Number(id), measures: pic.measures ?? [] }),
            })
            : host.request('photos.php', {
                method: 'POST', body: JSON.stringify({ id: Number(id), measures: pic.measures ?? [], _methode: 'PATCH' }),
            });
        sent.catch(() => toast(t('Die Beschriftung ließ sich nicht speichern.'), 'error'));
    }

    let lastPointer = null;
    stage.addEventListener('pointermove', (e) => { lastPointer = [e.clientX, e.clientY]; });

    /* --- The page's size on the screen ---------------------------------------
       Slider and pinch set it; the spot under the pointer (or the middle of
       the stage, for the slider) stays where it was. Everything on the page
       is in millimetres, so the page simply draws larger — the printed page
       does not change. */
    const zoomSlider = document.getElementById('plate-zoom');
    const zoomValue = document.getElementById('plate-zoom-value');
    const zoomFit = document.getElementById('plate-zoom-fit');
    function zoomSync() {
        const percent = Math.round(view * 100);
        zoomSlider.value = String(percent);
        zoomValue.textContent = percent + ' %';
        zoomFit.hidden = view <= 1;
    }
    function zoomTo(next, anchorX, anchorY) {
        next = clamp(next, 1, 4);
        if (Math.abs(next - view) < .001) return;
        const r = stage.getBoundingClientRect();
        const ax = anchorX ?? r.left + r.width / 2, ay = anchorY ?? r.top + r.height / 2;
        // Where the anchor lies on the page, as a share of it — before and after.
        const page = pageEl.getBoundingClientRect();
        const u = (ax - page.left) / page.width, v = (ay - page.top) / page.height;
        view = next;
        zoomSync();
        render();
        const after = pageEl.getBoundingClientRect();
        stage.scrollLeft += after.left + u * after.width - ax;
        stage.scrollTop += after.top + v * after.height - ay;
    }
    zoomSlider.addEventListener('input', () => zoomTo(Number(zoomSlider.value) / 100));
    zoomFit.addEventListener('click', () => zoomTo(1));
    /* THE WHEEL AND THE TOUCHPAD ZOOM THE PAGE, as they zoom a picture in
       the viewer (30.10.2026: „wenn ich nicht in diesem
       Kachelbearbeitungsmodus bin, ich auch rein und raus zoomen kann.
       Über Mausrad oder über Touchpad-Geste. Ohne über diesen
       Schieberegler zu gehen"). Until then only the pinch did (Strg +
       wheel to the browser) and the plain wheel scrolled a page larger
       than its room. The step follows the movement, with the viewer's
       numbers (imagezoom.js, onWheel): a pixel of scrolling 0.002, of
       pinching ten times that, a line 0.05, never more than doubling or
       halving at once. A cutout being set takes the wheel first (its cell
       prevents the default). A stroke sideways alone is no zoom and still
       scrolls. Moving a zoomed page: drag in the grey around it, or hold
       the space bar and drag anywhere (stagePan below). */
    stage.addEventListener('wheel', (e) => {
        if (e.defaultPrevented || (!e.ctrlKey && Math.abs(e.deltaY) < 1)) return;
        e.preventDefault();
        const unit = e.deltaMode === 1 ? .05 : e.deltaMode === 2 ? 1 : .002;
        const step = Math.min(1, Math.max(-1, -e.deltaY * unit * (e.ctrlKey ? 10 : 1)));
        zoomTo(view * 2 ** step, e.clientX, e.clientY);
    }, { passive: false });

    /* THE HAND THAT MOVES A ZOOMED PAGE, since the wheel no longer scrolls
       it: a drag that begins in the grey around the page, the middle
       button, or anywhere while the space bar is held — the viewer's old
       graphics-tool reflex (photowindow.js, spacePan). In the capture
       phase, so a held space bar reaches nothing on the page beneath. A
       plain drag on the page itself follows below. */
    let spaceHeld = false;
    const typing = (el) => /^(INPUT|TEXTAREA|SELECT)$/.test(el?.tagName ?? '') || el?.isContentEditable;
    window.addEventListener('keydown', (e) => {
        if (e.code !== 'Space' || e.repeat || !win.open || typing(e.target)) return;
        if (!win.contains(e.target) && e.target !== document.body) return;
        spaceHeld = true;
        stage.classList.add('is-hand');
        e.preventDefault();
    });
    window.addEventListener('keyup', (e) => {
        if (e.code !== 'Space') return;
        spaceHeld = false;
        stage.classList.remove('is-hand');
    });
    stage.addEventListener('pointerdown', (e) => {
        const grey = e.target === stage && e.button === 0;
        if (!grey && !(spaceHeld && e.button === 0) && e.button !== 1) return;
        e.preventDefault();
        e.stopPropagation();
        const x0 = e.clientX, y0 = e.clientY, left = stage.scrollLeft, top = stage.scrollTop;
        stage.classList.add('is-panning');
        const move = (ev) => {
            stage.scrollLeft = left - (ev.clientX - x0);
            stage.scrollTop = top - (ev.clientY - y0);
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);
            stage.classList.remove('is-panning');
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
    }, true);

    /* AND A PLAIN CLICK-AND-DRAG pans too (30.10.2026: „Pan,
       also verschieben, wäre klicken und rumschieben"), wherever the press
       lands on nothing that drags by itself. Everything on the page that
       does — the line between two cells, words and values on a picture,
       an inset and its corner, an open cutout, the writing (noteDrag) —
       says so by preventing the default or stopping the press; this
       listens after them, in the bubble, and lets those be. A click that
       does not move still selects as before: the hand takes over only past
       four pixels, and the click that ends a pan is swallowed. */
    let panSwallowClick = false;
    stage.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || e.defaultPrevented || spaceHeld) return;
        if (e.target.closest?.('button, input, select, textarea, a, label, [contenteditable="true"]')) return;
        const x0 = e.clientX, y0 = e.clientY, left = stage.scrollLeft, top = stage.scrollTop;
        let panning = false;
        const move = (ev) => {
            if (!panning) {
                if (Math.hypot(ev.clientX - x0, ev.clientY - y0) < 4) return;
                panning = true;
                stage.classList.add('is-panning');
            }
            stage.scrollLeft = left - (ev.clientX - x0);
            stage.scrollTop = top - (ev.clientY - y0);
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);
            if (!panning) return;
            stage.classList.remove('is-panning');
            panSwallowClick = true;
            setTimeout(() => { panSwallowClick = false; }, 0);
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
    });
    stage.addEventListener('click', (e) => {
        if (!panSwallowClick) return;
        panSwallowClick = false;
        e.stopPropagation();
        e.preventDefault();
    }, true);

    function pagesDraw() {
        pagesBox.textContent = '';
        layout.pages.forEach((_, i) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small' + (i === pageAt ? ' is-active' : '');
            b.setAttribute('aria-pressed', String(i === pageAt));
            b.textContent = t('Seite %d', i + 1);
            b.addEventListener('click', () => { pageAt = i; selected = null; render(); });
            pagesBox.append(b);
        });
        document.getElementById('plate-page-remove').hidden = !editable || layout.pages.length < 2;
        for (const input of win.querySelectorAll('input[name="plate-gutter"]')) {
            input.checked = Number(input.value) === Number(layout.gutter);
        }
        for (const input of win.querySelectorAll('input[name="plate-gutter-color"]')) {
            input.checked = input.value === layout.gutterColor;
        }
        noteSize.textContent = t('%d pt', notePtOf(layout, host.style()));
    }

    /**
     * Draw one page into `el` — the editor's big one and the row's small
     * ones alike, so the tile shows what the page is.
     */
    /* THE PAGE SET LARGE AND SHOWN SMALL (08.11.2026: the values
       text „ragte auf dem Handy dann hinten über den Kachelausschnitt
       hinaus, während sie auf dem Desktop … richtig platziert war", the
       distribution sat elsewhere too). Measured: on a phone the page is
       about 140 px wide, a 6 pt values text 2 px tall, the plot's numbers
       under 2 px. A phone browser does not draw type that small as asked —
       with page zoom or a larger system font a MINIMUM SIZE takes over
       (Chrome 6 px, Safari 9), the words grow and the pictures do not, and
       the text runs out of its cell. So a page shown with fewer than
       REF_MM pixels to the millimetre is laid out at REF_MM, where every
       type of the plate is above any minimum, and the whole of it is
       scaled down as one picture: the proportions are the desktop's to the
       pixel. Only where nobody edits — a drag reads screen pixels against
       the cells' own, and the two would no longer agree; at the desk the
       page stands large enough anyway. */
    const REF_MM = 6;
    function drawPageShown(el, ctx, w, h) {
        if (ctx.editable || ctx.mm >= REF_MM) {
            el.classList.remove('is-scaled');
            drawPage(el, ctx);
            return { root: el, ctx };
        }
        el.classList.add('is-scaled');
        el.textContent = '';
        const inner = document.createElement('div');
        inner.className = 'plate-page-scaled';
        inner.style.width = 210 * REF_MM + 'px';
        inner.style.height = 297 * REF_MM + 'px';
        inner.style.transform = `scale(${w / (210 * REF_MM)}, ${h / (297 * REF_MM)})`;
        el.append(inner);
        const big = { ...ctx, mm: REF_MM };
        drawPage(inner, big);
        return { root: inner, ctx: big };
    }

    function drawPage(el, ctx) {
        el.textContent = '';
        const body = document.createElement('div');
        body.className = 'plate-body-area';
        body.style.background = ctx.layout.gutterColor ?? '#ffffff';
        body.append(nodeEl(ctx.page.root, ctx));
        el.append(body);
        layoutAll(el, ctx);
    }

    function nodeEl(node, ctx) {
        if (node.t === 'cell') return cellEl(node, ctx);
        const box = document.createElement('div');
        box.className = 'plate-split is-' + node.dir;
        node.kids.forEach((kid, i) => {
            if (i > 0) box.append(dividerEl(node, i, box, ctx));
            const slot = document.createElement('div');
            slot.className = 'plate-slot';
            slot.style.flexGrow = node.sizes[i];
            slot.append(nodeEl(kid, ctx));
            box.append(slot);
        });
        return box;
    }

    function dividerEl(node, i, box, ctx) {
        const d = document.createElement('div');
        d.className = 'plate-divider';
        d.style[node.dir === 'row' ? 'width' : 'height'] = (ctx.layout.gutter ?? 0) * ctx.mm + 'px';
        if (!ctx.editable) return d;
        // Off the line to anywhere but the cell it held: that cell's row goes.
        d.addEventListener('pointerleave', (e) => {
            if (!toolsHeld || toolsHeld.contains(e.relatedTarget) || e.relatedTarget?.closest?.('.plate-divider')) return;
            toolsHeld.classList.remove('is-tools');
            toolsHeld = null;
        });
        d.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            d.setPointerCapture(e.pointerId);
            const across = node.dir === 'row';
            const slots = [...box.children].filter(c => c.classList.contains('plate-slot'));
            const total = slots.reduce((s, el) => s + (across ? el.offsetWidth : el.offsetHeight), 0);
            const start = across ? e.clientX : e.clientY;
            const a0 = node.sizes[i - 1], sum = a0 + node.sizes[i];
            const move = (ev) => {
                let a = clamp(a0 + ((across ? ev.clientX : ev.clientY) - start) / total, .03, sum - .03);
                // Two neighbours alike is what one usually wants — it snaps.
                if (Math.abs(a - sum / 2) < .012) a = sum / 2;
                node.sizes[i - 1] = a;
                node.sizes[i] = sum - a;
                slots[i - 1].style.flexGrow = a;
                slots[i].style.flexGrow = sum - a;
                layoutAll(pageEl, ctx);
            };
            const up = () => {
                d.removeEventListener('pointermove', move);
                d.removeEventListener('pointerup', up);
                saveSync();
            };
            d.addEventListener('pointermove', move);
            d.addEventListener('pointerup', up);
        });
        return d;
    }

    /* A CELL IS TWO LAYERS: the clip, which cuts picture, words and bar to
       the cell, and the tools beside it, which must NOT be cut
       (06.10.2026: in a flat footer „die Buttons … werden abgeschnitten, weil
       die Fußzeile nur eine minimale Höhe hat. Was passiert, wenn die Breite
       nicht passt?"). They were cut, neither wrapped nor overflowing. Now they
       float over the neighbours (fitTools places them) and stay one row: a
       stack of three rows over a small cell would be the worse answer. As a
       child of the cell the row keeps the cell hovered while the pointer
       travels onto it, even where it stands outside the cell. */
    function cellEl(cell, ctx) {
        const el = document.createElement('div');
        el.className = 'plate-cell';
        el.cell = cell;
        const clip = document.createElement('div');
        clip.className = 'plate-clip';
        el.append(clip);
        el.clip = clip;
        const pic = cell.pic ? ctx.byKey.get(cell.pic) : null;
        if (cell.info) {
            el.classList.add('is-filled');
            clip.append(detailsEl(cell.info, null, cell, ctx));
        } else if (cell.block) {
            el.classList.add('is-filled');
            clip.append(blockEl(cell, ctx));
        } else if (pic) {
            el.classList.add('is-filled');
            const src = ctx.thumbs ? pic.thumb : pic.src;
            const img = document.createElement('img');
            img.className = 'plate-picture';
            // As developed in the viewer (0190): the original stands until its development has been rendered.
            const e = loadFor(pic, src, () => {
                if (e.ready && e.img) img.src = e.img.src;
                relayoutSoon(el, ctx);
            });
            el.picture = { pic, e };
            img.src = e.ready && e.img ? e.img.src : src;
            img.alt = '';
            img.draggable = false;
            clip.append(img);
            /* A DOUBLE CLICK OPENS THE CUTOUT (08.11.2026: „Ausschnitt
               ändern … aufrufen, wenn ich doppelt auf das Bild klicke. Momentan
               öffnet es das Bild im Bildbetrachter, das will ich nicht mehr").
               A second one closes it again. The viewer has its button in the
               cell's tools now, where „Ausschnitt ändern" stood. A plate one
               may not change has no cutout to open — there the double click
               still leads into the viewer. The words on the picture keep their
               own double click (writing). */
            if (!ctx.thumbs) {
                clip.addEventListener('dblclick', (e) => {
                    if (e.target.closest('.plate-item, .plate-tools, .plate-note-grips')) return;
                    e.stopPropagation();
                    if (!ctx.editable) {
                        viewPicture(cell.pic);
                        return;
                    }
                    framing = framing === cell ? null : cell;
                    render();
                });
            }
            /* A CLICK CHOOSES THE PICTURE, so its settings stand at the side
               (the writing it shows). While a cutout is open the click only
               closes it (the page's own handler). */
            if (ctx.editable && !ctx.inset) {
                clip.addEventListener('click', (e) => {
                    if (framing || e.target.closest('.plate-item, .plate-inset, .plate-tools')) return;
                    e.stopPropagation();
                    select(cell, null);
                });
            }
            if (ctx.editable) panAndZoom(el, cell, ctx);
            if (ctx.editable && framing === cell) el.classList.add('is-framing');
        } else if (cell.pic) {
            // Named, but the find holds it no more (or not for this viewer).
            clip.insertAdjacentHTML('beforeend', `<div class="plate-empty">${esc(t('Bild nicht verfügbar'))}</div>`);
        } else if (cell.items.length > 0) {
            // Words on paper — a cell of values or text is a page of its own.
            el.classList.add('is-filled', 'is-paper');
        } else if (ctx.editable) {
            clip.insertAdjacentHTML('beforeend', `<div class="plate-empty">${esc(t('Bild hierher ziehen'))}</div>`);
        }
        for (const item of cell.items) {
            // Each kind by name; one this page does not know (stored by a newer one) is passed over, not misread.
            const node = item.kind === 'inset' ? insetEl(item, cell, ctx)
                : item.kind === 'info' ? detailsEl(item, item, cell, ctx)
                : item.kind === 'keying' ? keyingEl(item, cell, ctx)
                : item.kind === 'values' ? valuesEl(item, cell, ctx) : null;
            if (node) clip.append(node);
        }
        // An inset's own cell is moved and set as its inset (insetEl, the panel).
        if (ctx.editable && !ctx.inset) {
            /* THE ROW OF A FLAT CELL STANDS OUTSIDE IT, above the line to its
               neighbour (06.10.2026: sitting on the footer it hid
               the footer — „so können wir das auch nicht machen"; beside it
               without more, the line took the pointer: „weil wir zuerst auf
               die Trennlinie stoßen"). Three things make the way there:
               the row clears the line's grip, so the line stays free to
               drag; a LINE IS NEUTRAL GROUND — leaving the cell onto one
               keeps its row (toolsHeld); and an invisible strip of the
               cell's own spans the row's band over the cell's width, from
               the line to the row. No waiting anywhere. */
            const bridge = document.createElement('div');
            bridge.className = 'plate-tools-bridge';
            el.append(bridge, toolsEl(cell));
            el.addEventListener('pointerenter', () => {
                if (toolsHeld && toolsHeld !== el) toolsHeld.classList.remove('is-tools');
                toolsHeld = null;
                el.classList.add('is-tools');
                requestAnimationFrame(() => fitTools(el));
            });
            el.addEventListener('pointerleave', (e) => {
                if (e.relatedTarget?.closest?.('.plate-divider')) {
                    toolsHeld = el;
                    return;
                }
                el.classList.remove('is-tools');
            });
            dropTarget(el, cell);
        }
        return el;
    }

    /**
     * Put a cell's tools where they can be seen whole: over the cell's top
     * where it is tall enough, else above it — or below, for a strip at the
     * page's head —, and shifted sideways so the row never leaves the page.
     * Measured once it shows (on hover), so it knows its own width.
     */
    function fitTools(el) {
        const tools = el.querySelector(':scope > .plate-tools');
        if (!tools || getComputedStyle(tools).display === 'none') return;
        tools.style.cssText = '';
        const page = pageEl.getBoundingClientRect();
        const cell = el.getBoundingClientRect();
        const row = tools.getBoundingClientRect();
        const gap = 4;
        /* ALWAYS OUTSIDE THE CELL (06.10.2026: „die Knopfleiste
           bei den Kacheln generell immer außerhalb des Inhalts. Egal, ob es
           nur eine Kopfzeile ist — es ist einfach schöner, alles zu sehen").
           Above the cell, beyond the line to its neighbour and the line's
           grip (4 px each side) — or, for a cell at the page's head, above
           the page in the stage's wider top margin; below only where there
           is no room above at all. */
        const stageTop = stage.getBoundingClientRect().top;
        const line = (layout?.gutter ?? 0) * (pageEl.clientHeight / 297);
        const reachUp = cell.top - page.top < 1 ? 0 : line + 4;
        const reachDown = page.bottom - cell.bottom < 1 ? 0 : line + 4;
        const above = cell.top - reachUp - stageTop >= row.height + 2;
        const top = above ? -(row.height + reachUp) : cell.height + reachDown;
        let left = (cell.width - row.width) / 2;
        left = Math.max(page.left + gap - cell.left, Math.min(page.right - gap - row.width - cell.left, left));
        Object.assign(tools.style, { top: top + 'px', left: left + 'px', transform: 'none' });
        // The bridge: the row's band, over the cell's width and the row's own,
        // one pixel into the line's grip so no seam of neighbour lies between.
        const bridge = el.querySelector(':scope > .plate-tools-bridge');
        if (bridge) {
            const from = Math.min(0, left), to = Math.max(cell.width, left + row.width);
            Object.assign(bridge.style, {
                left: from + 'px', width: to - from + 'px',
                top: (above ? top : top - 1) + 'px', height: row.height + 1 + 'px',
            });
        }
    }

    /** A cell placed again once its picture is there — once per frame,
     *  however many pictures arrive within it. */
    const relayoutPending = new Set();
    function relayoutSoon(el, ctx) {
        if (relayoutPending.has(el)) return;
        relayoutPending.add(el);
        requestAnimationFrame(() => {
            relayoutPending.delete(el);
            if (el.isConnected) layoutCell(el, ctx);
        });
    }

    function layoutAll(root, ctx) {
        root.querySelectorAll('.plate-cell').forEach(el => layoutCell(el, ctx));
    }

    /** Place the picture, the items and the bar of one cell. */
    function layoutCell(el, ctx) {
        const cell = el.cell;
        const W = el.clientWidth, H = el.clientHeight;
        const box = el.clip;
        box.querySelector(':scope > .plate-bar')?.remove();
        box.querySelectorAll(':scope > .plate-med, :scope > .plate-soft').forEach(n => n.remove());
        const g = el.picture ? cutout(cell, W, H, aspectOf(el.picture.pic, el.picture.e)) : null;
        if (g) {
            Object.assign(el.querySelector('.plate-picture').style, {
                width: g.pw + 'px', height: g.ph + 'px', left: g.left + 'px', top: g.top + 'px',
            });
        }
        const e = el.picture?.e;
        // Display pixels of the cell to pixels of the loaded file.
        const toFile = (x, y) => (g && e?.ready ? [(x - g.left) / g.pw * e.w, (y - g.top) / g.ph * e.h] : [0, 0]);

        /* THE WRITING ON THE PICTURE (08.10.2026): the layers this cell ticks,
           in the cell's pixels — the plate's size for the letters, the
           picture's places for where they stand (plateannot.js). */
        box.querySelector(':scope > .plate-notes')?.remove();
        if (g && (cell.notes?.length || cell.measures?.length)) {
            const px = notePtOf(ctx.layout, ctx.style) * PT_MM * ctx.mm;
            const shapes = noteShapes(el.picture.pic, cell.notes, g, umPerShownPx(el.picture.pic, g.pw, e), px, cell.measures);
            if (shapes.length > 0) {
                const style = { line_color: '#000000', seam_color: '#ffffff', ...(ctx.style ?? {}) };
                // Only one's own picture's writing moves, and only on the page one edits (noteDrag).
                const movable = ctx.editable && !ctx.thumbs && !el.picture.pic.by;
                el.querySelector('.plate-picture').insertAdjacentHTML('afterend',
                    noteSvg(shapes, style, Math.max(1, .15 * ctx.mm), W, H, movable));
                el.noteShapes = shapes;
                el.noteCtx = ctx;
                if (movable) noteGrips(el, shapes, W, H);
            }
        }

        for (const node of box.querySelectorAll(':scope > .plate-item')) {
            const item = node.item;
            node.style.fontSize = item.pt * PT_MM * ctx.mm + 'px';
            node.style.lineHeight = LINE_ITEM;
            node.style.left = item.x * W + 'px';
            node.style.top = item.y * H + 'px';
            // A keying way's long remark wraps where the cell ends, less the margin the bar keeps.
            if (item.kind === 'keying') node.style.maxWidth = Math.max(40, (1 - item.x) * W - EDGE_MM * ctx.mm) + 'px';
            groundStyle(node, item.ground, ctx, item.groundOpacity);
            if (item.ground === 'none') {
                const x = item.x * W, y = item.y * H;
                node.style.color = g ? inkAt(e, ...toFile(x, y), ...toFile(x + node.offsetWidth, y + node.offsetHeight)) : '#000';
            }
        }
        // The insets: their boxes, their frame in the colour and width of
        // the page's lines, and their own cells inside.
        const frameW = (ctx.layout?.gutter ?? 0) * ctx.mm;
        for (const node of box.querySelectorAll(':scope > .plate-inset')) {
            const it = node.item;
            Object.assign(node.style, {
                left: it.x * W + 'px', top: it.y * H + 'px', width: it.w * W + 'px', height: it.h * H + 'px',
                boxShadow: frameW > 0 && it.frame !== false ? `0 0 0 ${frameW}px ${ctx.layout.gutterColor ?? '#ffffff'}` : 'none',
            });
            const inner = node.querySelector(':scope > .plate-cell');
            if (inner) layoutCell(inner, ctx);
        }
        // The chosen thing's controls follow it while it is moved or sized (removeMark).
        el.querySelectorAll(':scope > .plate-controls').forEach(controlsFit);
        const block = box.querySelector(':scope > .plate-block');
        if (block) layoutBlock(el, block, cell, W, H, ctx);
        const tile = box.querySelector(':scope > .plate-details-tile');
        if (tile) {
            const dark = cell.info.ground === 'black';
            tile.style.background = dark ? '#000' : '#fff';
            tile.style.color = dark ? '#fff' : '#000';
            const words = tile.firstElementChild;
            const at = tileTextAt(cell.info, W, H, ctx.mm);
            Object.assign(words.style, {
                fontSize: cell.info.pt * PT_MM * ctx.mm + 'px', lineHeight: LINE_TILE,
                left: at.left + 'px', top: at.top + 'px', width: at.width + 'px',
            });
        }

        // THE BAR — plateparts.js measures it, the printed page draws the same.
        const b = g ? barGeometry(cell, W, H, ctx.mm, umPerShownPx(el.picture.pic, g.pw, e)) : null;
        // THE MEDIUM, in the bar's letters and ink (plateparts.js, mediaGeometry).
        const m = g ? mediaGeometry(cell, W, H, ctx.mm, mediaLabel(el.picture.pic.media, cell.medFull), b) : null;
        // THE LABEL, the same letters, stacked beyond bar and medium in a shared corner.
        const lab = g ? labelGeometry(cell, W, H, ctx.mm, pictureLabel(el.picture.pic, cell), b, m) : null;
        /* Each one's ink, its own choice or the picture's (plateparts.js,
           inkFor), and every soft ground BEFORE any word — a ground laid
           later would blur the words already standing (softOnCanvas). */
        const inkOf = (key, x, y, w, h) => inkFor(cell.ink?.[key], inkAt(e, ...toFile(x, y), ...toFile(x + w, y + h)));
        const words = [
            m && { ...m, key: 'med', ink: inkOf('med', m.x, m.y, m.w, m.h) },
            lab && { ...lab, key: 'label', ink: inkOf('label', lab.x, lab.y, lab.w, lab.h) },
        ];
        const barInk = b ? inkOf('bar', b.x, b.y, b.len, b.boxH) : null;
        for (const r of [b && { key: 'bar', x: b.x, y: b.y, w: b.len, h: b.boxH }, ...words]) {
            if (r && softWanted(cell, r.key)) box.append(softGround(r, ctx.mm));
        }
        for (const w of words) {
            if (!w) continue;
            const node = document.createElement('div');
            node.className = 'plate-med';
            node.textContent = w.label;
            Object.assign(node.style, { left: w.x + 'px', top: w.y + 'px', fontSize: w.font + 'px', color: w.ink });
            box.append(node);
        }
        if (!b) return;
        const ink = barInk;
        const bar = document.createElement('div');
        bar.className = 'plate-bar';
        Object.assign(bar.style, { left: b.x + 'px', top: b.y + 'px', width: b.len + 'px', fontSize: b.font + 'px', color: ink });
        const label = document.createElement('span');
        label.textContent = b.label;
        const line = document.createElement('span');
        line.className = 'plate-bar-line';
        Object.assign(line.style, { height: b.thick + 'px', marginTop: b.gap + 'px', background: ink });
        bar.append(label, line);
        box.append(bar);
    }

    /* A SOFT GROUND on a picture in the editor — the browser's own blur of
       what lies beneath, faded out by a mask (plates.css, .plate-soft). No
       colour of its own: the picture, only unsharp. The same numbers as
       the print's softOnCanvas, so what one sets here is what the page gets. */
    function softGround(r, mm) {
        const at = softRect(r.x, r.y, r.w, r.h, mm);
        const node = document.createElement('div');
        node.className = 'plate-soft';
        const blur = `blur(${SOFT_BLUR * mm}px)`;
        Object.assign(node.style, {
            left: at.x + 'px', top: at.y + 'px', width: at.w + 'px', height: at.h + 'px',
            backdropFilter: blur, webkitBackdropFilter: blur,
        });
        node.style.setProperty('--soft-feather', Math.min(SOFT_FEATHER * mm, at.w / 2) + 'px');
        node.style.setProperty('--soft-feather-y', Math.min(SOFT_FEATHER_Y * mm, at.h / 2) + 'px');
        return node;
    }

    function groundStyle(node, ground, ctx, opacity = 100) {
        // The ground may let the picture through (opacityControl); the words keep their ink.
        const a = Math.min(100, Math.max(10, opacity ?? 100)) / 100;
        node.style.background = ground === 'white' ? `rgba(255,255,255,${a})` : ground === 'black' ? `rgba(0,0,0,${a})` : 'transparent';
        node.style.padding = ground === 'none' ? '0' : BOX_PAD_Y * ctx.mm + 'px ' + BOX_PAD_X * ctx.mm + 'px';
        if (ground !== 'none') node.style.color = ground === 'black' ? '#fff' : '#000';
    }

    /* --- The measures' blocks ----------------------------------------------
       „Verteilung" and „in Reihe" fill a cell; „Werte" is a text that lies on
       one. All three are reckoned from the find's measures each time they are
       drawn (plateparts.js, measurestats.js) — what the photo window shows. */
    function blockEl(cell, ctx) {
        const node = cell.block.kind === 'plot'
            ? document.createElementNS('http://www.w3.org/2000/svg', 'svg')
            : document.createElement('canvas');
        node.classList.add('plate-block');
        // Inside an inset a click chooses the inset (insetEl), not its block.
        if (ctx.editable && !ctx.inset) node.addEventListener('click', (e) => { e.stopPropagation(); select(cell, null); });
        return node;
    }

    function layoutBlock(el, node, cell, W, H, ctx) {
        const rows = rowsFor(ctx.byKey, cell.block, ctx.style);
        const empty = (words) => (ctx.editable ? words : '');
        if (cell.block.kind === 'plot') {
            const drawn = blockPlotInner(cell.block, rows, W, H, ctx.mm);
            node.setAttribute('viewBox', `0 0 ${W} ${H}`);
            node.setAttribute('width', String(W));
            node.setAttribute('height', String(H));
            node.innerHTML = plotGroundRect(cell.block, W, H) + (drawn !== ''
                ? drawn
                : `<text x="${W / 2}" y="${H / 2}" text-anchor="middle" font-size="${3 * ctx.mm}" fill="#6f7674"
                     font-family="Arial, Helvetica, sans-serif">${esc(empty(t('Zu wenige Messungen für eine Verteilung')))}</text>`);
            return;
        }
        const dpr = window.devicePixelRatio || 1;
        node.width = Math.max(1, Math.round(W * dpr));
        node.height = Math.max(1, Math.round(H * dpr));
        node.style.width = W + 'px';
        node.style.height = H + 'px';
        const g = node.getContext('2d');
        g.setTransform(dpr, 0, 0, dpr, 0, 0);
        const look = rowLook(cell.block, ctx.style);
        g.fillStyle = look.ground;
        g.fillRect(0, 0, W, H);
        // Cutouts without a common scale stand alike and carry no bar.
        const unscaled = rows[0]?.unscaled === true;
        const geo = rowGeometry(rows, W, H, look, ctx.mm, unscaled || cell.barOver ? 'off' : cell.bar);
        if (!geo) {
            g.fillStyle = '#6f7674';
            g.font = `${3 * ctx.mm}px Arial, Helvetica, sans-serif`;
            g.textAlign = 'center';
            g.fillText(empty(t('Noch nichts gemessen')), W / 2, H / 2);
            return;
        }
        /* ONE CALL PER PICTURE, NOT PER SPORE: thirteen spores of one photo
           once registered thirteen redraws for its arrival, each redraw
           registered thirteen more for the photos still on their way, and
           a row of 25 drew 16 465 times while its pictures came in —
           the maintainer could not scroll the strip meanwhile (06.10.2026). */
        const asked = new Set();
        drawSporeRow(g, rows, cell.block.sort, geo, (r) => {
            const src = ctx.thumbs ? r.obj.thumb : r.obj.src;
            const e = loadFor(r.obj, src, asked.has(src) ? undefined : () => relayoutSoon(el, ctx));
            asked.add(src);
            return e.ready ? e.img : null;
        });
        const b = unscaled ? null : cell.barOver ? rowBarOver(cell, geo, rows.length, ctx.mm)
            : barGeometry(cell, W, H, ctx.mm, 1 / geo.pxPerUm);
        rowWords(g, cell, rows, W, H, ctx.mm, b, look);
    }

    /** A row's bar, medium and label on its canvas, their ink read off what
     *  lies beneath — a frame's picture or the row's ground (inkOnCanvas) —
     *  unless the cell sets it, and a soft ground under each where it asks
     *  for one (plateparts.js, writeOnCanvas). The print draws the same. */
    function rowWords(g, cell, rows, W, H, mm, b, look) {
        const m = mediaGeometry(cell, W, H, mm, rowMediaLabel(rows, cell.medFull), b);
        const lab = labelGeometry(cell, W, H, mm, rowLabel(cell.block, rows, cell), b, m);
        writeOnCanvas(g, cell, mm, rowWriting(g, cell, b, m, lab, look));
    }

    /* --- Insets: a smaller window lying on a filled cell ---------------------
       07.10.2026: „wenn ich ein Bild in die Mitte einer Kachel
       ziehe, dass es ein Inline-Bild wird … die Ascusspitzen in ein
       größeres Bild noch nebenbei einfügen, das einen Ascus zeigt". A
       picture or a block dropped in the middle of a filled cell lies on it
       — a cell of its own, drawn as every cell is, in a box of shares of
       its cell; it moves like a text, eight grips size it (insetResize),
       and its frame is the page's lines. */
    function insetEl(item, cell, ctx) {
        const wrap = document.createElement('div');
        wrap.className = 'plate-inset';
        wrap.item = item;
        wrap.append(cellEl(item.cell, { ...ctx, inset: true }));
        if (ctx.editable) {
            movable(wrap, item, cell);
            /* A DOUBLE CLICK OPENS THE INSET'S OWN CUTOUT
               (20.11.2026: „bei den eingesetzten Bildern … wenn man doppelt
               draufklickt, dort auch reinzoomen und das Bild verschieben").
               The picture's own double click (cellEl) never heard it:
               movable captures the press to the inset, the clicks follow the
               capture, and the double click landed on this wrap and bubbled
               on to the picture BENEATH — whose cutout opened instead. So the
               wrap answers it itself. An inset holding a block keeps it from
               the picture beneath as well. Once open, the second double click
               goes to the cutout's own handler (panAndZoom) and closes it. */
            wrap.addEventListener('dblclick', (e) => {
                if (e.target.closest('.plate-inset-grip')) return;
                e.stopPropagation();
                if (!item.cell.pic || !ctx.byKey.get(item.cell.pic)) return;
                framing = framing === item.cell ? null : item.cell;
                render();
            });
            // Corners and the middles of the sides, named by where they pull: n e s w.
            for (const at of ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w']) {
                const grip = document.createElement('span');
                grip.className = 'plate-inset-grip is-' + at;
                grip.title = t('Größe ändern – mit Umschalt bleibt das Seitenverhältnis');
                grip.addEventListener('pointerdown', (e) => insetResize(e, wrap, item, at));
                wrap.append(grip);
            }
        }
        return wrap;
    }
    /**
     * EIGHT GRIPS (08.11.2026: „mehr Anfasspunkte … die Ecken und
     * die Mitten der Seiten, sodass man in eine Seite erweitern kann, oder
     * ein Eck großziehen kann"): until then only the lower right corner
     * sized an inset. A grip moves the sides it names and the opposite ones
     * stay where they are — the left middle widens to the left, the upper
     * left corner grows up and to the left.
     *
     * EVERY BOX IS FREE (20.11.2026, once an inset's cutout
     * could be zoomed and moved: „das ursprüngliche Bildformat auflösen …
     * das muss nicht mehr im Verhältnis sein. Ich kann frei einen Rahmen
     * ziehen … und darin dann frei das Detail zeigen"). Until then a
     * PICTURE kept its proportions, because a stretched box could only
     * crop it and nothing could choose which part; now the cutout chooses
     * (double click, wheel, drag — cutout() always covers the box). A side
     * grip changes one measure, a corner both. WITH SHIFT the box keeps the
     * proportions it had when the pull began — a side grip then changes
     * both measures, the other axis growing evenly about its middle, and a
     * corner follows the sideways pull. No box leaves its cell and none
     * shrinks below a few per cent.
     */
    function insetResize(e, wrap, item, at = 'se') {
        e.preventDefault();
        e.stopPropagation();
        const grip = e.currentTarget;
        grip.setPointerCapture(e.pointerId);
        const host = wrap.closest('.plate-cell');
        const W = host.clientWidth, H = host.clientHeight;
        const ratio = (item.h * H) / (item.w * W);
        const sx = at.includes('e') ? 1 : at.includes('w') ? -1 : 0;
        const sy = at.includes('s') ? 1 : at.includes('n') ? -1 : 0;
        const start = { x: e.clientX, y: e.clientY, bx: item.x, by: item.y, w: item.w, h: item.h };
        // How far a side may go with its opposite side (or, without a pull on that axis, its middle) held.
        const room = (s, pos, size) => (s < 0 ? pos + size : s > 0 ? 1 - pos : 2 * Math.min(pos + size / 2, 1 - pos - size / 2));
        const maxW = room(sx, start.bx, start.w), maxH = room(sy, start.by, start.h);
        const place = (s, pos, size, now) => (s < 0 ? pos + size - now : s > 0 ? pos : pos + (size - now) / 2);
        // Within its room and its least size; with Shift in the proportions it began with, the sideways pull deciding.
        const fit = (w, h, keep) => {
            if (keep) {
                if (sx === 0) w = h * H / ratio / W;
                w = clamp(w, .08, Math.min(maxW, maxH * H / ratio / W));
                return [w, w * W * ratio / H];
            }
            return [sx === 0 ? start.w : clamp(w, .08, maxW), sy === 0 ? start.h : clamp(h, .05, maxH)];
        };
        const snap = snapResize(wrap, host, W, H);
        const move = (ev) => {
            const keep = ev.shiftKey;
            let [w, h] = fit(start.w + sx * (ev.clientX - start.x) / W, start.h + sy * (ev.clientY - start.y) / H, keep);
            /* THE PULLED SIDES CATCH (snapResize): the size follows the
               side that caught; with Shift the other axis follows the
               proportions again. */
            const x = place(sx, start.bx, start.w, w) * W, y = place(sy, start.by, start.h, h) * H;
            const at = snap({ l: x, t: y, r: x + w * W, b: y + h * H }, sx, sy);
            const w2 = (at.r - at.l) / W, h2 = (at.b - at.t) / H;
            [w, h] = keep
                ? (sx !== 0 ? fit(w2, w2 * W * ratio / H, true) : fit(h2 * H / ratio / W, h2, true))
                : fit(w2, h2, false);
            item.w = w;
            item.h = h;
            item.x = clamp(place(sx, start.bx, start.w, w), 0, 1 - w);
            item.y = clamp(place(sy, start.by, start.h, h), 0, 1 - h);
            layoutCell(host, ctxNow());
        };
        const up = () => {
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', up);
            saveSync();
        };
        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', up);
    }
    /** What a picture or a block dropped as an inset holds, and where it
     *  lands: centred on the drop, a good third of the cell wide, a picture
     *  in its own proportions. */
    function insetFrom(data, el, x, y) {
        let inner = null, aspect = 4 / 3;
        if (data.startsWith('pic:') && pics.has(data.slice(4))) {
            const pic = pics.get(data.slice(4));
            inner = { t: 'cell', pic: data.slice(4), z: 1, cx: .5, cy: .5, bar: 'br', info: null, block: null, items: [], ...cellHand() };
            aspect = aspectOf(pic, load(pic.src));
        } else if (/^(plot|row|crop|count):/.test(data)) {
            const block = presetCopy(data);
            inner = { t: 'cell', pic: null, z: 1, cx: .5, cy: .5, bar: 'br', info: null, block, items: [],
                ...(block.kind === 'row' ? rowHand(block) : {}) };
            aspect = data.startsWith('plot:') ? 220 / 150 : 4 / 3;
        }
        if (inner === null) return null;
        const W = el.clientWidth || 1, H = el.clientHeight || 1;
        let w = .36, h = (w * W / aspect) / H;
        if (h > .8) { h = .8; w = h * H * aspect / W; }
        return { kind: 'inset', x: clamp(x - w / 2, 0, 1 - w), y: clamp(y - h / 2, 0, 1 - h), w, h, cell: inner, ...handOf('inset') };
    }

    /** A keying way on the page (plateparts.js, keyingRows): a row per
     *  line, the name in bold; the words wrap at the cell's edge (the width
     *  is set in layoutCell). A way that is gone says so where one edits. */
    function keyingEl(item, cell, ctx) {
        const node = document.createElement('div');
        node.className = 'plate-keying plate-item';
        node.item = item;
        const rows = keyingRows(item, ctx.details?.keyings);
        if (rows === null) {
            if (ctx.editable) {
                node.textContent = t('Dieser Schlüsselweg ist nicht mehr bestätigt.');
                node.classList.add('is-empty');
            }
        } else {
            node.replaceChildren(...rows.map((row) => {
                const line = document.createElement('div');
                for (const run of row) {
                    const span = document.createElement(run.bold ? 'b' : 'span');
                    span.textContent = run.s;
                    line.append(span);
                }
                if (row.gap) line.style.marginTop = `${row.gap * LINE_ITEM}em`;
                return line;
            }));
        }
        if (ctx.editable) movable(node, item, cell);
        return node;
    }

    function valuesEl(item, cell, ctx) {
        const node = document.createElement('div');
        node.className = 'plate-values plate-item';
        node.item = item;
        const rows = rowsFor(ctx.byKey, item, ctx.style);
        const lines = valuesRows(item, rows, ctx.byKey);
        /* One block per row, so the air before a part (row.gap, in lines)
           can stand. Where rows carry a name (valuesRows, lead), a grid of
           two: the names in the first column, every row in the second —
           an empty name beside the rows that have none. */
        const leads = lines.some(row => row.lead);
        node.classList.toggle('plate-has-lead', leads);
        node.replaceChildren(...lines.flatMap((row) => {
            const line = document.createElement('div');
            line.textContent = row.map(r => r.s).join('');
            const out = [line];
            if (leads) {
                const lead = document.createElement('div');
                lead.className = 'plate-values-lead';
                lead.textContent = row.lead ?? '';
                out.unshift(lead);
            }
            if (row.gap) out.forEach(n => { n.style.marginTop = `${row.gap * LINE_ITEM}em`; });
            return out;
        }));
        if (lines.length === 0 && ctx.editable) {
            const some = valueLines(rows).length > 0 || partsOf(ctx.byKey, item.layer).length > 0 || countsOf(ctx.byKey, item.layer).length > 0;
            node.textContent = some ? t('Nichts angehakt') : t('Noch nichts gemessen');
            node.classList.add('is-empty');
        }
        if (ctx.editable) movable(node, item, cell);
        return node;
    }

    /* --- Texts and details ------------------------------------------------ */
    function detailsEl(d, item, cell, ctx) {
        const node = document.createElement('div');
        node.className = 'plate-details ' + (item ? 'plate-item' : 'plate-details-text');
        node.item = item;
        node.innerHTML = detailsHtml(d, ctx.details);
        if (!node.innerHTML && ctx.editable) node.innerHTML = `<span class="plate-details-none">${esc(t('Nichts angehakt'))}</span>`;
        if (item) {
            if (ctx.editable) movable(node, item, cell);
            return node;
        }
        // A TILE: its ground fills the cell, its words move on it like a box.
        const tile = document.createElement('div');
        tile.className = 'plate-details-tile';
        tile.append(node);
        if (ctx.editable) {
            movable(node, d, cell, null);
            tile.addEventListener('click', (e) => { e.stopPropagation(); select(cell, null); });
        }
        return tile;
    }

    /** Drag an item over its picture; a click without moving selects it. */
    /** Drag words over their cell; a click without moving selects them.
     *  `pos` carries x/y, `item` is what gets selected (null: the cell's tile). */
    function movable(node, pos, cell, item = pos) {
        node.addEventListener('pointerdown', (e) => {
            if (node.isContentEditable) return;
            /* An inset whose cutout is open: the press pans the picture
               (panAndZoom). Its OWN cell — the picture beneath may have its
               cutout open while the inset is moved over it. */
            if (node.classList.contains('plate-inset') && e.target.closest('.plate-cell')?.classList.contains('is-framing')) return;
            e.preventDefault();
            e.stopPropagation();
            node.setPointerCapture(e.pointerId);
            const box = node.closest('.plate-cell');
            const W = box.clientWidth, H = box.clientHeight;
            // A tile's words that never moved stand at its padding — start from there.
            if (pos.x == null || pos.y == null) {
                const at = tileTextAt(pos, W, H, ctxNow().mm);
                pos.x = at.left / W;
                pos.y = at.top / H;
            }
            const sx = e.clientX, sy = e.clientY, x0 = pos.x, y0 = pos.y;
            const snap = snapMove(node, box, W, H);
            let moved = false;
            const move = (ev) => {
                moved = true;
                /* From where the press began, not step by step: a snapped
                   place must not become the start of the next step, or the
                   snap would hold on and never let go. */
                const at = snap(x0 * W + ev.clientX - sx, y0 * H + ev.clientY - sy);
                // An inset stays wholly in its cell; words may reach the edge.
                pos.x = clamp(at.x / W, 0, pos.w != null ? 1 - pos.w : .98);
                pos.y = clamp(at.y / H, 0, pos.h != null ? 1 - pos.h : .97);
                layoutCell(box, ctxNow());
            };
            const up = () => {
                node.removeEventListener('pointermove', move);
                node.removeEventListener('pointerup', up);
                if (moved) saveSync(); else select(cell, item);
            };
            node.addEventListener('pointermove', move);
            node.addEventListener('pointerup', up);
        });
    }

    /** What else lies in the cell, as boxes in its pixels (snapMove, snapResize). */
    function othersIn(node, box) {
        const cellAt = box.getBoundingClientRect();
        return [...box.querySelectorAll('.plate-item, .plate-inset')]
            .filter(o => o !== node && !node.contains(o) && !o.contains(node) && o.closest('.plate-cell') === box)
            .map(o => {
                const r = o.getBoundingClientRect();
                return { l: r.left - cellAt.left, t: r.top - cellAt.top, r: r.right - cellAt.left, b: r.bottom - cellAt.top };
            });
    }
    /** The place nearest to v within SNAP_PX, or v itself. */
    function snapNearest(v, places) {
        let best = v, gap = SNAP_PX;
        for (const p of places) {
            if (Math.abs(p - v) < gap) { gap = Math.abs(p - v); best = p; }
        }
        return best;
    }
    /**
     * A WEAK SNAP WHILE MOVING (09.11.2026: „die Abstände
     * einrasten, dass das gleich aussieht … bei den Labels, bei den Medium
     * und beim Maßstabbalken feste Abstände vom Rand … und diesen Abstand
     * auch zueinander … Ich will aber trotzdem ein Bild auch ganz in die
     * Ecke schieben können … nur eine schwache Einrastung"). Whatever moves
     * in a cell — a text, values, details, an inset — catches on:
     *
     *   the cell's edges at EDGE_MM, the distance the bar, the medium and
     *   the label keep, so all of them line up on one margin;
     *   the other things in the cell at the same distance, beside or
     *   above one another — only where they face each other, a text far
     *   below gives no reason to stop;
     *   FLUSH with the other things (the same day: „bündig
     *   ausrichten auch einbauen"): left on left, right on right, top on
     *   top, bottom on bottom — wherever they stand in the cell, so two
     *   texts far apart still line up.
     *
     * WEAK: it catches within SNAP_PX screen pixels of a place and lets go
     * beyond, whatever the zoom. The pointer is followed from where the
     * press began, so pulling on past the margin reaches the edge itself
     * and the corner — the clamp still holds it there.
     * Returns (x, y) → {x, y}, the node's top left in the cell's pixels.
     */
    function snapMove(node, box, W, H) {
        const edge = EDGE_MM * ctxNow().mm;
        const w = node.offsetWidth, h = node.offsetHeight;
        const others = othersIn(node, box);
        const nearest = snapNearest;
        return (x, y) => {
            const xs = [edge, W - edge - w], ys = [edge, H - edge - h];
            for (const o of others) {
                // Side by side where their heights overlap, one over the other where their widths do.
                if (y < o.b && y + h > o.t) xs.push(o.r + edge, o.l - edge - w);
                if (x < o.r && x + w > o.l) ys.push(o.b + edge, o.t - edge - h);
                // Flush edges, everywhere.
                xs.push(o.l, o.r - w);
                ys.push(o.t, o.b - h);
            }
            return { x: nearest(x, xs), y: nearest(y, ys) };
        };
    }
    /**
     * THE SAME WEAK SNAP WHILE SIZING (09.11.2026, to the
     * question left open with the snap for moving: „Dass man Elemente immer
     * mit gleicher Größe untereinander positionieren kann, man sie quasi in
     * der Länge dann verschiebt … oder vergrößert und sie dann kurz
     * einrasten dort, wo das andere Element auch die gleiche Breite hat").
     * Only the sides the grip pulls catch, each on:
     *
     *   the cell's margin at EDGE_MM, as when moving;
     *   the other things' edges, flush, wherever they stand;
     *   the margin's distance before a neighbour it faces;
     *   THE SAME SIZE as another thing — its width for a side pulled
     *   sideways, its height for one pulled up or down — so two insets
     *   one under the other come out equally wide.
     *
     * Weak as the moving: within SNAP_PX, and it lets go beyond.
     * Returns ({l, t, r, b}, sx, sy) → the box with its pulled sides caught,
     * in the cell's pixels.
     */
    function snapResize(node, box, W, H) {
        const edge = EDGE_MM * ctxNow().mm;
        const others = othersIn(node, box);
        return (at, sx, sy) => {
            const out = { ...at };
            if (sx !== 0) {
                const xs = sx > 0 ? [W - edge] : [edge];
                for (const o of others) {
                    xs.push(o.l, o.r);
                    // The width of the other, from the side that stays.
                    xs.push(sx > 0 ? at.l + (o.r - o.l) : at.r - (o.r - o.l));
                    if (at.t < o.b && at.b > o.t) xs.push(sx > 0 ? o.l - edge : o.r + edge);
                }
                out[sx > 0 ? 'r' : 'l'] = snapNearest(sx > 0 ? at.r : at.l, xs);
            }
            if (sy !== 0) {
                const ys = sy > 0 ? [H - edge] : [edge];
                for (const o of others) {
                    ys.push(o.t, o.b);
                    ys.push(sy > 0 ? at.t + (o.b - o.t) : at.b - (o.b - o.t));
                    if (at.l < o.r && at.r > o.l) ys.push(sy > 0 ? o.t - edge : o.b + edge);
                }
                out[sy > 0 ? 'b' : 't'] = snapNearest(sy > 0 ? at.b : at.t, ys);
            }
            return out;
        };
    }
    const SNAP_PX = 6;

    // The millimetre the page was laid out with — REF_MM where it is shown scaled (drawPageShown).
    let pageMm = 1;
    const ctxNow = () => ({ layout, page: layout.pages[pageAt], mm: pageMm, byKey: pics, details: values, style: styleNow(), thumbs: false, editable });

    /* SELECTING DOES NOT REDRAW: the page's nodes stay, only the marks
       and the side panel change — a text's double click is two clicks on
       the SAME node, and a redraw after the first put the second on a new
       one, so the double click never arrived. */
    function select(cell, item) {
        selected = cell ? { cell, item } : null;
        chosenDraft = null;
        markSelected();
        // A click on it asks for its settings — even when it was chosen before.
        if (cell && editable) sideTab('cell');
        panelSync();
    }
    function markSelected() {
        for (const n of pageEl.querySelectorAll('.plate-item, .plate-inset')) n.classList.toggle('is-selected', selected?.item === n.item);
        for (const n of pageEl.querySelectorAll('.plate-cell')) {
            n.classList.toggle('is-chosen', !!n.cell.pic && selected != null && selected.item === null && selected.cell === n.cell);
        }
        for (const n of pageEl.querySelectorAll('.plate-details-tile, .plate-block')) {
            n.classList.toggle('is-selected', selected != null && selected.item === null && selected.cell === n.closest('.plate-cell').cell);
        }
        removeMark();
    }

    /* THE RED × ON WHAT LIES IN A CELL (09.11.2026: „wird
       momentan ja nur über einen Button ganz unten in der Sidebar gelöscht.
       Das ist ziemlich schlecht zu sehen … Können wir diesen
       Inline-Elementen nicht einfach oben rechts ein X geben, so wie wir es
       bei den Maßen schon haben?"). The chosen text, values, keying way,
       distribution or inset carries it off its upper right corner — the
       measure's dress from the photo window (photowindow.js, drawRemoveAt):
       a round button on the ground, red ring and cross, red because
       removing is the danger. The sidebar's remove buttons went with it; a
       cell's whole content keeps its bin among the cell's tools. Chrome,
       never printed: it lives in the controls' layer below. */
    /* THE CONTROLS ON TOP (09.11.2026: „Das X muss ganz nach
       vorne rücken. In der Z-Position … alle Steuerelemente müssen
       natürlich beim aktiven Element immer in die höchste Z-Position").
       The × once lived in the chosen thing's node and so stood at that
       node's height — under a text lying over an inset. Now the chosen
       thing's controls — its frame, its ×, an inset's eight grips — lie in
       a layer of their own over everything in the cell (.plate-controls),
       laid over the thing's box (controlsFit). The thing itself keeps its
       height, so the page looks as it prints. The grips are moved there
       while it is chosen and back when it is not; their handlers hold the
       inset itself, not their parent. */
    function removeMark() {
        for (const layer of pageEl.querySelectorAll('.plate-controls')) {
            layer.querySelectorAll('.plate-inset-grip').forEach(g => layer.home.append(g));
            layer.remove();
        }
        if (!editable || !selected?.item) return;
        const node = [...pageEl.querySelectorAll('.plate-item, .plate-inset')].find(n => n.item === selected.item);
        const clip = node?.closest('.plate-clip');
        if (!clip) return;
        const layer = document.createElement('div');
        layer.className = 'plate-controls';
        const inset = node.classList.contains('plate-inset');
        layer.classList.toggle('is-inset', inset);
        layer.home = node;
        const words = {
            info: t('Fundangaben entfernen'), values: t('Werte entfernen'), keying: t('Schlüsselweg entfernen'),
            plot: t('Verteilung entfernen'), row: t('Reihe entfernen'), inset: t('Einsatz entfernen'),
        }[selected.item.kind] ?? t('Entfernen');
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'plate-remove';
        b.title = words;
        b.setAttribute('aria-label', words);
        b.innerHTML = iconHtml('close');
        // Not the press that moves the thing it sits on.
        b.addEventListener('pointerdown', (e) => e.stopPropagation());
        b.addEventListener('click', (e) => { e.stopPropagation(); removeSelected(); });
        layer.append(...node.querySelectorAll(':scope > .plate-inset-grip'), b);
        /* A click on a control stays there. Inside the inset a grip's click
           met the inset on its way up; from this layer it would reach the
           cell, which takes it for choosing the picture — and the inset
           just sized lost its choice. */
        layer.addEventListener('click', (e) => e.stopPropagation());
        /* BESIDE THE CLIP, NOT IN IT (20.11.2026): an inset's grips stand
           outside its box now (plates.css), and an inset snapped to its
           cell's edge would have them cut off by the clip. The cell is
           the clip's size (inset: 0), so the inset's offsets place the
           layer the same. */
        clip.parentElement.append(layer);
        controlsFit(layer);
    }
    /** The controls' layer over its thing's box, and the × within the cell:
     *  off the corner where there is room, pulled back in where the cell's
     *  edge would cut it — on an inset into its corner, clear of the grips
     *  (plates.css, .plate-remove). */
    function controlsFit(layer) {
        const node = layer.home, clip = layer.parentElement;   // the cell, the clip's size
        if (!node?.isConnected || !clip) return;
        Object.assign(layer.style, {
            left: node.offsetLeft + 'px', top: node.offsetTop + 'px',
            width: node.offsetWidth + 'px', height: node.offsetHeight + 'px',
        });
        const b = layer.querySelector(':scope > .plate-remove');
        b.classList.remove('is-inside');
        b.style.top = b.style.right = '';
        const r = b.getBoundingClientRect(), c = clip.getBoundingClientRect();
        const over = { top: c.top + 2 - r.top, right: r.right - (c.right - 2) };
        if (over.top <= 0 && over.right <= 0) return;
        if (layer.classList.contains('is-inset')) {
            b.classList.add('is-inside');
            return;
        }
        const cs = getComputedStyle(b);
        if (over.top > 0) b.style.top = parseFloat(cs.top) + over.top + 'px';
        if (over.right > 0) b.style.right = parseFloat(cs.right) + over.right + 'px';
    }
    // A click on the page's empty ground lets go of the selection — and
    // a click beside the open cutout closes it.
    stage.addEventListener('click', (e) => {
        if (framing && !e.target.closest('.plate-cell.is-framing')) {
            framing = null;
            render();
        }
        if ((selected || chosenDraft !== null) && !e.target.closest('.plate-item, .plate-inset, .plate-details-tile, .plate-block, .plate-tools')) select(null);
    });
    win.addEventListener('keydown', (e) => {
        // Escape folds the export menu first.
        if (e.key === 'Escape' && !exportMenu.hidden) {
            e.preventDefault();
            exportMenuShow(false);
            exportToggle.focus();
            return;
        }
        if (e.target.isContentEditable || e.target.closest('input, select, textarea')) return;
        // Enter or Escape closes an open cutout.
        if (framing && (e.key === 'Enter' || e.key === 'Escape')) {
            e.preventDefault();
            framing = null;
            render();
            return;
        }
        if (!selected?.item || !editable) return;
        if (e.key === 'Delete' || e.key === 'Backspace') {
            e.preventDefault();
            removeSelected();
        }
    });
    /* Y FITS THE PAGE into its stage, as it fits a picture everywhere
       (shortcuts.js; 11.11.2026: „bei den Tafeln sollte man Y
       auch zum Einpassen benutzen können"). On the window and not on the
       plate's own: in the Fundbrowser the plate stands in the page, and a
       click on its grey ground leaves the focus on the page. Claimed, held
       repeats too — Firefox's search and a Mac's accent menu would take
       the letter. */
    const plateInFront = () => win.open && (win.checkVisibility ? win.checkVisibility() : win.offsetParent !== null)
        && ![...document.querySelectorAll('dialog[open]')].some((d) => d !== win && d.matches(':modal') && !d.contains(win));
    window.addEventListener('keydown', (e) => {
        if ((e.key !== 'y' && e.key !== 'Y') || e.metaKey || e.ctrlKey || e.altKey || !plateInFront() || typingIn(e.target)) return;
        e.preventDefault();
        if (!e.repeat) zoomTo(1);
    });
    shortcutsProvide(() => (plateInFront() ? [{ title: t('Tafel'), rows: [
        ['Y', t('Seite einpassen')], [t('Leertaste halten'), t('Seite verschieben')], [t('Entf'), t('Gewähltes löschen')],
        ['Enter', t('Ausschnitt schließen')],
    ] }] : []));

    function removeSelected() {
        if (!selected) return;
        const { cell, item } = selected;
        if (item) cell.items.splice(cell.items.indexOf(item), 1);
        else if (cell.info) cell.info = null;
        else cell.block = null;
        selected = null;
        render();
    }

    /* --- The cell's tools ------------------------------------------------- */
    function toolsEl(cell) {
        const box = document.createElement('div');
        box.className = 'plate-tools';
        const add = (iconName, title, fn) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small';
            b.title = title;
            b.setAttribute('aria-label', title);
            b.innerHTML = iconHtml(iconName);
            b.addEventListener('click', (e) => { e.stopPropagation(); fn(); });
            box.append(b);
            return b;
        };
        /* NO BUTTONS FOR SPLITTING (08.10.2026: „die vertikal und
           horizontal teilen Buttons kannst du entfernen") — a cell splits
           where something is dragged onto its edge. And no button for a text
           either: „Text" is a tile in the strip, dragged on like the details. */
        if (cell.pic) {
            // The cutout opens by a double click on the picture (render, above).
            add('photo', t('Im Bildbetrachter öffnen'), () => viewPicture(cell.pic));
            /* NO BUTTON FOR THE DETAILS (06.10.2026: „das ließe sich
               ja auch über Drag and Drop regeln … dafür bräuchte ich keinen
               Button mehr"): „Fundangaben" dragged onto the picture lies on it.
               NOR FOR THE BAR AND THE MEDIUM any more (08.11.2026:
               „Maßstab und Medium nur in der Sidebar") — a button that cycled
               through five states had to be pressed blind until the corner
               came round; the panel's four corners name the one wanted
               (cornerControl). */
        }
        /* NO BUTTON TO MOVE A WHOLE CELL (08.10.2026: „Ich brauche
           den Verschieben-Button nicht") — what is on a cell is taken off
           and dragged in again from the strip. The four arrows went to the
           cutout above, which is a moving too. The bin, because the cross
           read as „close this window" and the cell is not that. */
        const filled = cell.pic || cell.info || cell.block;
        add('delete', filled ? t('Inhalt entfernen') : t('Kachel entfernen'), () => {
            if (filled) Object.assign(cell, { pic: null, info: null, block: null, items: [], notes: [], measures: [], barOver: false, med: 'off', medFull: false, label: 'off', labelMarks: [], ink: {}, soft: {}, z: 1, cx: .5, cy: .5 });
            else removeCell(cell);
            selected = null;
            render();
        });
        return box;
    }

    /* --- The cutout: wheel zooms, dragging moves ----------------------- */
    function panAndZoom(el, cell, ctx) {
        el.addEventListener('wheel', (e) => {
            // Shut, the wheel is the page's, not the picture's.
            if (framing !== cell) return;
            e.preventDefault();
            const r = el.getBoundingClientRect();
            const mx = e.clientX - r.left, my = e.clientY - r.top;
            const aspect = aspectOf(el.picture.pic, el.picture.e);
            const g = cutout(cell, r.width, r.height, aspect);
            const u = (mx - g.left) / g.pw, v = (my - g.top) / g.ph;
            cell.z = clamp(cell.z * Math.exp(-e.deltaY * .0015), 1, 40);
            const g2 = cutout({ ...cell, cx: .5, cy: .5 }, r.width, r.height, aspect);
            // The spot under the pointer stays under the pointer.
            cell.cx = (g2.W / 2 - (mx - u * g2.pw)) / g2.pw;
            cell.cy = (g2.H / 2 - (my - v * g2.ph)) / g2.ph;
            settle(cell, r.width, r.height, aspect);
            layoutCell(el, ctx);
            saveSync();
        }, { passive: false });
        /* THE SECOND DOUBLE CLICK, the one that closes the open cutout, lands
           HERE and not on the picture's own (render): the drag below captures
           the pointer to the cell, and the click events follow the capture. */
        el.addEventListener('dblclick', (e) => {
            if (framing !== cell || e.target.closest('.plate-item, .plate-tools')) return;
            e.stopPropagation();
            framing = null;
            render();
        });
        el.addEventListener('pointerdown', (e) => {
            // Its own picture only: an inset's lying on it is the inset's (movable).
            if (framing !== cell || !e.target.classList.contains('plate-picture') || e.target.closest('.plate-cell') !== el || e.button !== 0) return;
            e.preventDefault();
            el.setPointerCapture(e.pointerId);
            el.classList.add('is-panning');
            const aspect = aspectOf(el.picture.pic, el.picture.e);
            let lx = e.clientX, ly = e.clientY;
            const move = (ev) => {
                const g = cutout(cell, el.clientWidth, el.clientHeight, aspect);
                cell.cx = g.cx - (ev.clientX - lx) / g.pw;
                cell.cy = g.cy - (ev.clientY - ly) / g.ph;
                lx = ev.clientX; ly = ev.clientY;
                settle(cell, el.clientWidth, el.clientHeight, aspect);
                layoutCell(el, ctx);
            };
            const up = () => {
                el.classList.remove('is-panning');
                el.removeEventListener('pointermove', move);
                el.removeEventListener('pointerup', up);
                saveSync();
            };
            el.addEventListener('pointermove', move);
            el.addEventListener('pointerup', up);
        });
    }
    /** Write the clamped centre back — only where the person moved it. */
    function settle(cell, W, H, aspect) {
        const g = cutout(cell, W, H, aspect);
        cell.cx = Math.round(g.cx * 1e4) / 1e4;
        cell.cy = Math.round(g.cy * 1e4) / 1e4;
        cell.z = Math.round(cell.z * 1e4) / 1e4;
    }

    /* --- Splitting and removing ------------------------------------------- */
    function parentOf(node, target, parent = null) {
        if (node === target) return { parent, index: parent ? parent.kids.indexOf(target) : -1 };
        if (node.t !== 'split') return null;
        for (const k of node.kids) {
            const r = parentOf(k, target, node);
            if (r) return r;
        }
        return null;
    }
    function replaceNode(oldNode, newNode) {
        const { parent, index } = parentOf(layout.pages[pageAt].root, oldNode);
        if (!parent) layout.pages[pageAt].root = newNode; else parent.kids[index] = newNode;
    }
    /** A cell splits in two; a split that already runs that way takes a third. */
    function splitCell(target, dir, after, fresh) {
        const { parent, index } = parentOf(layout.pages[pageAt].root, target);
        if (parent && parent.dir === dir) {
            const half = parent.sizes[index] / 2;
            parent.sizes[index] = half;
            parent.kids.splice(index + (after ? 1 : 0), 0, fresh);
            parent.sizes.splice(index + (after ? 1 : 0), 0, half);
        } else {
            replaceNode(target, splitNew(dir, after ? [target, fresh] : [fresh, target]));
        }
    }
    /** A cell goes; its share goes to the neighbour, a lone survivor moves up. */
    function removeCell(target) {
        const root = layout.pages[pageAt].root;
        const { parent, index } = parentOf(root, target);
        if (!parent) {
            Object.assign(target, { pic: null, info: null, items: [] });
            return;
        }
        const freed = parent.sizes[index];
        parent.kids.splice(index, 1);
        parent.sizes.splice(index, 1);
        parent.sizes[Math.min(index, parent.sizes.length - 1)] += freed;
        if (parent.kids.length > 1) return;
        const only = parent.kids[0];
        const up = parentOf(layout.pages[pageAt].root, parent);
        // A lone split running the same way as its new parent melts into it.
        if (only.t === 'split' && up.parent && up.parent.dir === only.dir) {
            const share = up.parent.sizes[up.index];
            up.parent.kids.splice(up.index, 1, ...only.kids);
            up.parent.sizes.splice(up.index, 1, ...only.sizes.map(s => s * share));
        } else {
            replaceNode(parent, only);
        }
    }

    /* --- Dragging in: the middle replaces, an edge splits ------------------
       WORDS ARE THE EXCEPTION (06.10.2026, on the values: „ob dann
       eine neue leere Kachel entsteht oder ob es tatsächlich im Bild der Text
       entsteht"). Text belongs at a picture's edge — Reul wrote „Sporen: …"
       along the top —, and the quarter-wide edge band that splits for a
       picture split the cell under the very spot the text was meant for. For
       details and values the band is a thin strip; everywhere else they lie
       where they are let go, and a dashed frame at the pointer says so
       before the drop instead of the whole cell lighting up. The data of a
       drag cannot be read until the drop, so its kind is noted at its start. */
    let dragging = '';        // 'pic' | 'block' | 'info' | 'values'
    function dragFrom(node, data, kind) {
        node.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', data);
            e.dataTransfer.effectAllowed = 'copy';
            if (node.closest('.plate-strip')) dragGhost(node, e);
            dragging = kind;
            win.classList.add('is-dragging');
        });
        node.addEventListener('dragend', dragEnd);
    }
    /* A SMALL GHOST, UP AND TO THE LEFT OF THE POINTER
       (07.10.2026: „wenn ich den Text mit der Maus ins Bild ziehe, ziehe ich
       natürlich das Bild mit … der Browser nimmt das Bild und macht das
       halb transparent … das überdeckt dann das gestrichelte Kästchen, das
       anzeigt, wo der Text platziert werden soll"). Since the strip's tiles
       are a picture's size, the browser's own drag image was a pale copy
       of the whole tile, lying exactly where the dashed landing frame
       opens — to the right of and below the pointer. Now a strip tile
       drags a name tag (its heading), a picture a thumbnail of 72 px,
       and both hang with their lower right corner just above and left of
       the pointer: the frame, the edge zones and the page stay in sight.
       Drawn on a canvas, so it is ready at once; built in the window
       (the top layer) for the browser to snapshot, then gone. */
    function dragGhost(node, e) {
        const ghost = document.createElement('div');
        ghost.className = 'plate-drag-ghost';
        const img = node.querySelector('img');
        if (img?.complete && img.naturalWidth > 0) {
            const w = 72, h = Math.max(1, Math.round(w * img.naturalHeight / img.naturalWidth));
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
            ghost.append(canvas);
        } else {
            // A picture still on its way says what it is, at least.
            ghost.textContent = node.querySelector('b')?.textContent || t('Bild');
        }
        win.append(ghost);
        e.dataTransfer.setDragImage(ghost, ghost.offsetWidth + 10, ghost.offsetHeight + 10);
        setTimeout(() => ghost.remove(), 0);
    }
    function dragEnd() {
        dragging = '';
        win.classList.remove('is-dragging');
        win.querySelectorAll('.plate-zone').forEach(z => z.remove());
    }
    const words = () => dragging === 'info' || dragging === 'values';
    /** Does a word dropped in this cell's middle lie ON it? On anything but
     *  an empty cell, yes — and values lie on an empty one too (as paper);
     *  details there become a tile of their own. */
    const liesOn = (cell) => dragging === 'values' || cell.pic || cell.block || cell.info || cell.items.length > 0;
    function zoneOf(el, e) {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
        const [side, dist] = Object.entries({ left: x, right: 1 - x, top: y, bottom: 1 - y })
            .sort((a, b) => a[1] - b[1])[0];
        return { side: dist < (words() ? .03 : .24) ? side : 'center', x, y };
    }
    const ZONE_CSS = {
        left: 'left:0;top:0;bottom:0;width:50%', right: 'right:0;top:0;bottom:0;width:50%',
        top: 'left:0;right:0;top:0;height:50%', bottom: 'left:0;right:0;bottom:0;height:50%', center: 'inset:0',
    };
    function dropTarget(el, cell) {
        el.addEventListener('dragover', (e) => {
            e.preventDefault();
            let z = el.querySelector(':scope > .plate-zone');
            if (!z) {
                z = document.createElement('div');
                z.className = 'plate-zone';
                el.append(z);
            }
            const { side, x, y } = zoneOf(el, e);
            const landing = side === 'center' && words() && liesOn(cell);
            // A picture or a block over the middle of a filled cell lands as an inset.
            const inset = side === 'center' && (dragging === 'pic' || dragging === 'block') && (cell.pic || cell.block || cell.info);
            z.classList.toggle('is-landing', landing || inset);
            // Where the words' top left corner will stand — about a line of them;
            // an inset's box, centred on the pointer.
            z.style.cssText = landing
                ? `left:${x * 100}%;top:${y * 100}%;width:${dragging === 'info' ? 34 : 46}%;height:${dragging === 'info' ? 18 : 7}%`
                : inset
                    ? `left:${clamp(x - .18, 0, .64) * 100}%;top:${clamp(y - .15, 0, .7) * 100}%;width:36%;height:30%`
                    : ZONE_CSS[side];
        });
        el.addEventListener('dragleave', (e) => {
            if (!el.contains(e.relatedTarget)) el.querySelector(':scope > .plate-zone')?.remove();
        });
        el.addEventListener('drop', (e) => {
            e.preventDefault();
            const data = e.dataTransfer.getData('text/plain');
            const { side, x, y } = zoneOf(el, e);
            dragEnd();
            if (side === 'center') {
                // ON a filled cell the details lie where they were dropped;
                // on an empty one they are a tile (freshFrom, below).
                if (data === 'info' && liesOn(cell)) {
                    const item = { kind: 'info', x: clamp(x, 0, .9), y: clamp(y, 0, .9), ...presetCopy('info') };
                    cell.items.push(item);
                    selected = { cell, item };
                    render();
                    return;
                }
                // Values are words: ON a cell they lie where dropped, at an edge they get a cell of paper.
                if (data.startsWith('values:') || data.startsWith('keying:')) {
                    const item = Object.assign(presetCopy(data), { x: clamp(x, 0, .9), y: clamp(y, 0, .9) });
                    cell.items.push(item);
                    selected = { cell, item };
                    render();
                    return;
                }
                /* A PICTURE OR A BLOCK IN THE MIDDLE OF A FILLED CELL lies on it
                   as an inset (07.10.2026) — no longer replacing what is there
                   and the words on it; to exchange a picture, remove it first. */
                if ((cell.pic || cell.block || cell.info) && /^(pic|plot|row|crop|count):/.test(data)) {
                    const item = insetFrom(data, el, x, y);
                    if (!item) return;
                    cell.items.push(item);
                    selected = { cell, item };
                    render();
                    return;
                }
            }
            const made = freshFrom(data, cell);
            if (!made) return;
            const { fresh } = made;
            if (side === 'center') Object.assign(cell, content(fresh));
            else splitCell(cell, side === 'left' || side === 'right' ? 'row' : 'col', side === 'right' || side === 'bottom', fresh);
            selected = fresh.info || fresh.block ? { cell: side === 'center' ? cell : fresh, item: null } : null;
            render();
        });
    }

    /** A new values text of a layer — its part measures ticked from the
     *  start: ONE values text says the structure and its parts (07.10.2026). */
    function valuesFor(layer) {
        const { lines, ...hand } = handOf('values');
        const item = { ...valuesNew(layer, plateStyle().values_pt), ...hand };
        // The three lines as last ticked, in their order; the parts always — they are this layer's.
        const three = Array.isArray(lines) ? VALUE_KEYS.filter(k => lines.includes(k)) : item.lines;
        item.lines = [...three, ...partsOf(pics, layer).map(p => 'part:' + p)];
        return item;
    }

    /** The cell a drop makes — a picture, a block, the details or a values
     *  text on paper. Null for what is no drop. */
    function freshFrom(data) {
        if (data === 'info') return { fresh: cellNew(null, { info: presetCopy('info') }) };
        if (data.startsWith('values:') || data.startsWith('keying:')) {
            const item = Object.assign(presetCopy(data), { x: .05, y: .05 });
            return { fresh: cellNew(null, { items: [item] }) };
        }
        if (data.startsWith('plot:') || data.startsWith('row:') || data.startsWith('crop:') || data.startsWith('count:')) {
            const block = presetCopy(data);
            return { fresh: cellNew(null, { block, ...(block.kind === 'row' ? rowHand(block) : {}) }) };
        }
        if (data.startsWith('pic:')) return pics.has(data.slice(4)) ? { fresh: cellNew(data.slice(4), cellHand()) } : null;
        return null;
    }

    /* --- Dragging beside the page: a cell across the whole page -----------
       07.10.2026: „wenn ich einmal geteilt habe, vertikal, dann
       kann ich darunter keine ganze Kachel mehr platzieren. Also zum
       Beispiel eine Fußzeile." A cell's edge splits that cell alone, and
       the page's root split had no edge of its own. THE STAGE AROUND THE
       PAGE is that edge: dropped under the page, a row runs across its
       whole width at the foot; over it, at the head; beside it, a column
       its whole height — a quarter of the page to begin with, the line
       between them drags as any other. */
    const PAGE_SHARE = .25;
    function pageSideOf(e) {
        const r = pageEl.getBoundingClientRect();
        const out = { top: r.top - e.clientY, bottom: e.clientY - r.bottom, left: r.left - e.clientX, right: e.clientX - r.right };
        const [side, by] = Object.entries(out).sort((a, b) => b[1] - a[1])[0];
        return by > 0 ? side : null;
    }
    const PAGE_ZONE_CSS = {
        top: 'left:0;right:0;top:0;height:25%', bottom: 'left:0;right:0;bottom:0;height:25%',
        left: 'left:0;top:0;bottom:0;width:25%', right: 'right:0;top:0;bottom:0;width:25%',
    };
    function pageZone(side) {
        let z = pageEl.querySelector(':scope > .plate-zone.is-page');
        if (side === null) { z?.remove(); return; }
        if (!z) {
            z = document.createElement('div');
            z.className = 'plate-zone is-page';
            pageEl.append(z);
        }
        z.style.cssText = PAGE_ZONE_CSS[side];
    }
    stage.addEventListener('dragover', (e) => {
        if (!editable || !dragging) return;
        // Over the page the cells answer; only the stage around it is the page's edge.
        if (pageEl.contains(e.target)) { pageZone(null); return; }
        const side = pageSideOf(e);
        pageZone(side);
        if (side !== null) e.preventDefault();
    });
    stage.addEventListener('dragleave', (e) => {
        if (!stage.contains(e.relatedTarget)) pageZone(null);
    });
    stage.addEventListener('drop', (e) => {
        if (!editable || pageEl.contains(e.target)) return;
        const side = pageSideOf(e);
        if (side === null) return;
        e.preventDefault();
        const data = e.dataTransfer.getData('text/plain');
        dragEnd();
        const made = freshFrom(data);
        if (!made) return;
        const { fresh } = made;
        const page = layout.pages[pageAt];
        const dir = side === 'top' || side === 'bottom' ? 'col' : 'row';
        const after = side === 'bottom' || side === 'right';
        // A root already running that way takes one more; else it is wrapped.
        if (page.root.t === 'split' && page.root.dir === dir) {
            page.root.sizes = page.root.sizes.map(v => v * (1 - PAGE_SHARE));
            if (after) { page.root.kids.push(fresh); page.root.sizes.push(PAGE_SHARE); }
            else { page.root.kids.unshift(fresh); page.root.sizes.unshift(PAGE_SHARE); }
        } else {
            page.root = splitNew(dir, after ? [page.root, fresh] : [fresh, page.root],
                after ? [1 - PAGE_SHARE, PAGE_SHARE] : [PAGE_SHARE, 1 - PAGE_SHARE]);
        }
        selected = fresh.info || fresh.block ? { cell: fresh, item: null } : null;
        render();
    });

    /* THE ROW, ROUGHLY (06.10.2026: „die Reihe in den
       Vorschaubildern ganz grob zu laden … oder frisst das Ressourcen
       ohne Ende?"): drawn from the small previews the strip loads
       anyway, at the strip's width — a few hundred pixels, redrawn
       once per frame while the previews arrive. A stored snapshot was
       the alternative and the worse one: it would need a place to
       live and be wrong the moment one measures again. */
    function rowPreview(d) {
        const canvas = document.createElement('canvas');
        canvas.className = 'plate-strip-row';
        let queued = false;
        const later = () => {
            if (queued) return;
            queued = true;
            requestAnimationFrame(() => { queued = false; if (canvas.isConnected) draw(); });
        };
        const draw = () => {
            // A hidden group's row waits — stripGroupShow draws it on showing.
            if (canvas.clientWidth === 0 && canvas.offsetParent === null) return;
            const W = canvas.clientWidth || 140, H = Math.round(W * .62);
            const dpr = window.devicePixelRatio || 1;
            canvas.width = Math.round(W * dpr);
            canvas.height = Math.round(H * dpr);
            canvas.style.height = H + 'px';
            const g = canvas.getContext('2d');
            g.setTransform(dpr, 0, 0, dpr, 0, 0);
            g.fillStyle = '#fff';
            g.fillRect(0, 0, W, H);
            const rows = rowsFor(pics, d, styleNow());
            const geo = rowGeometry(rows, W, H, rowLook(d, styleNow()), .6, 'off');
            if (!geo) return;
            const asked = new Set();
            drawSporeRow(g, rows, 'L', geo, (r) => {
                const e = loadFor(r.obj, r.obj.thumb, asked.has(r.obj.thumb) ? undefined : later);
                asked.add(r.obj.thumb);
                return e.ready ? e.img : null;
            });
        };
        // Drawn once it stands in the strip, so it knows its width —
        // and again whenever that width changes (stripWatch).
        requestAnimationFrame(() => { if (canvas.isConnected) draw(); });
        canvas.redraw = later;
        return canvas;
    }

    /* PRESETS FROM THE STRIP (07.10.2026: „wenn ich in der
       Sidebar auf eine Information klicke, die ich ins Bild ziehen kann …
       sollte das in der rechten Bar gleich angezeigt werden, zum Beispiel
       wenn ich auf Fundangaben klicke, dass ich da schon selektieren kann,
       was ich rüberziehen werde"). A click on a words' or measures' tile
       lets go of whatever is chosen on the page and opens the tile's
       settings under „Kachel"; what is set there is what the next drag
       lays down, and the tile in the strip shows it. One preset per tile,
       for as long as the plate is open — untouched, a tile keeps
       following the defaults (plateStyle), so a size changed elsewhere
       still reaches it. */
    const drafts = new Map();   // strip data ('info', 'values:Sporen', 'row:Sporen\nKeimporus' …) → preset
    let chosenDraft = null;     // the strip tile whose preset the side shows

    /** What a strip tile lays down when nobody set it: the defaults. */
    function defaultFor(data) {
        if (data === 'info') return { ...detailsNew(plateStyle().details_pt), ...handOf('info') };
        // The words of the photo kind: a line of one's own, written once it lies.
        if (data.startsWith('values:')) return valuesFor(data.slice(7));
        // A keying way: named by the key it ends in (plateparts.js, keyingRows).
        if (data.startsWith('keying:')) {
            return { kind: 'keying', key_id: data.slice(7), show: 'both', label: 'both',
                pt: plateStyle().details_pt, ground: 'none', ...handOf('keying') };
        }
        // A row of counts: each frame and sector of the layer as photographed (countRows).
        if (data.startsWith('count:')) return { kind: 'row', layer: data.slice(6), sort: 'L', count: true, ...handOf('row') };
        // A row of picture cutouts: the layer, then the cutouts' name.
        if (data.startsWith('crop:')) {
            const [layer, name = ''] = data.slice(5).split('\n');
            return { kind: 'row', layer, sort: 'L', crop: name, ...handOf('row') };
        }
        const at = data.indexOf(':');
        // A part's block names its part after a line break: „Sporen\nKeimporus".
        const [layer, part] = data.slice(at + 1).split('\n');
        const block = { kind: data.slice(0, at), layer, sort: 'L',
            ...(data.startsWith('row:') ? handOf('row') : data.startsWith('plot:') ? handOf('plot') : {}) };
        if (part) block.part = part;
        return block;
    }
    const draftOf = (data) => drafts.get(data) ?? defaultFor(data);
    // What a drop lays down: a copy, so the preset stays the strip's.
    const presetCopy = (data) => JSON.parse(JSON.stringify(draftOf(data)));

    function chooseDraft(data) {
        if (!editable) return;
        if (!drafts.has(data)) drafts.set(data, defaultFor(data));
        selected = null;
        chosenDraft = data;
        markSelected();
        sideTab('cell');
        panelSync();
    }

    /** A strip tile's paper: its preset drawn small, as it would land. */
    function tileBody(data) {
        const d = draftOf(data);
        const span = document.createElement('span');
        /* THE TWO WORD TILES IN THE WINDOW'S OWN DRESS
           (30.10.2026: „die Fundangaben und der Text, die haben weißen
           Hintergrund … Fundnummer und unten drunter die Fundnummer.
           Eigentlich kriegen wir das auch schön in eine Zeile … vorne
           Label, hinten Wert"). They stood as paper, and the heading's
           rule reached the bold labels inside too — each label became a
           dark bar with its value on the next line. Now a list as the
           point window writes it: the label quiet in front, the value
           behind it, one line each. What the plate prints keeps its own
           look; the tile only says WHICH details it will lay down. */
        /* EVERYTHING THE FIND HOLDS, NOT WHAT WAS LAST TICKED
           (09.11.2026: „Diese Fundangaben zeigen mir genau die Inhalte an,
           die ich dann in der rechten Sidebar angeklickt und ausgewählt
           habe. Das ist aber Schwachsinn. Zeig mir da bitte alles Mögliche
           an, was ich auswählen kann … Und das dann reinziehen und dann
           erst rechts auswählen"). Every detail with a value, in the
           list's order, as though all were ticked; a long note or remark
           ends in „…" on its one line (plates.css, .plate-strip-fact-value)
           and stands whole in its title. What lands is still the last
           choice (handOf('info')) — the ticks are set on the page. */
        if (data === 'info') {
            span.className = 'plate-strip-facts';
            for (const [key, label, printed = label] of detailLabels()) {
                const v = values[key];
                if (v == null || v === '') continue;
                // The place once, in its fuller form — the short one is part of it.
                if (key === 'place' && values.place_full) continue;
                const name = document.createElement('span');
                name.className = 'plate-strip-fact-label';
                name.textContent = printed;
                name.title = label;
                const value = document.createElement(key === 'species' ? 'i' : 'span');
                value.className = 'plate-strip-fact-value';
                // The spore print's colour before its words, as on the plate (detailsRows).
                if (key === 'spore_print' && values.spore_print_color) {
                    const sw = document.createElement('span');
                    sw.className = 'plate-swatch';
                    sw.style.background = values.spore_print_color;
                    value.append(sw);
                }
                value.append(String(v));
                value.title = String(v);
                span.append(name, value);
            }
            if (!span.childElementCount) span.textContent = t('Fundangaben');
            return span;
        }
        if (d.kind === 'keying') {
            // Line for line, as the plate will lay it down (keyingRows).
            const rows = keyingRows(d, values.keyings) ?? [];
            span.className = 'plate-strip-words-keying';
            span.innerHTML = rows.map(row => `<span class="plate-strip-line"${row.gap ? ` style="margin-top: ${row.gap * 1.35}em"` : ''}>${esc(row.map(r => r.s).join(''))}</span>`).join('');
            return span;
        }
        if (d.kind === 'values') {
            // Line for line, with the air before the parts (valuesRows).
            const lines = valuesRows(d, rowsFor(pics, d, styleNow()), pics);
            // The names in a column of their own, as on the plate.
            const leads = lines.some(row => row.lead);
            span.classList.toggle('plate-has-lead', leads);
            span.innerHTML = lines.map(row => {
                const air = row.gap ? ` style="margin-top: ${row.gap * 1.35}em"` : '';
                return (leads ? `<span class="plate-values-lead"${air}>${esc(row.lead ?? '')}</span>` : '')
                    + `<span class="plate-strip-line"${air}>${esc(row.map(r => r.s).join(''))}</span>`;
            }).join('');
            return span;
        }
        if (d.kind === 'plot') {
            // The paper is the tile's own white; the plot alone draws into the box.
            const H = d.part ? 110 : 150;
            const tpl = document.createElement('template');
            tpl.innerHTML = `<svg viewBox="0 0 220 ${H}">${blockPlotInner(d, rowsFor(pics, d, styleNow()), 220, H, 5)}</svg>`;
            return tpl.content.firstElementChild;
        }
        return rowPreview(d);
    }
    /** Draws a strip tile anew from its preset, after the preset changed. */
    function stripTileSync(data) {
        for (const tile of strip.querySelectorAll('.plate-strip-tile[data-use]')) {
            if (tile.dataset.use === data) tile.lastElementChild.replaceWith(tileBody(data));
        }
    }
    function stripChosenSync() {
        for (const tile of strip.querySelectorAll('.plate-strip-tile[data-use]')) {
            tile.classList.toggle('is-chosen', tile.dataset.use === chosenDraft);
        }
    }

    /* --- The strip: the find's pictures, grouped like the gallery --------- */
    function stripDraw() {
        stripWatch?.disconnect();
        strip.textContent = '';
        if (!editable) return;
        /* EVERY GROUP TWICE OVER: a section in the list, under its heading,
           and a name in the menu that stands beside the list once the strip
           is wide enough (plates.css, @container plate-strip). Built once —
           which of the two one sees is the stylesheet's to say, so dragging
           the edge across the threshold rebuilds nothing. */
        const menu = document.createElement('nav');
        menu.className = 'plate-strip-menu';
        const list = document.createElement('div');
        list.className = 'plate-strip-list';
        /* THE GROUP ONE IS IN, OVER THE WHOLE WIDTH (13.11.2026: „wir
           brauchen in der Sidebar bei den Symbolen dann quasi noch eine
           Sticky-Überschrift, wo wir gerade sind. In voller Sidebar-Breite wie wir
           das bereits bei Bearbeiten haben … gerade bei den Mikrofotos, wenn sie
           etwas verkleinert sind, kann man nicht sehen, ob man gerade bei den
           Sporen oder bei irgendwelchen Askos ist. Wenn alles in Wasser ist und
           nicht eingefärbt"):
           the chosen group's symbol and name over menu and list alike
           (plates.css); stripGroupShow writes it. */
        const now = document.createElement('p');
        now.className = 'list-label plate-strip-now';
        strip.append(now, menu, list);
        let into = null;
        /* THREE WIDTHS (13.11.2026), as at the Fundbrowser's
           rail: the list; a column of symbols alone; symbol and name
           (plates.css, @container plate-strip). Pointing switches, with the
           guard against grazing (hoverpick.js). */
        hoverPick(menu, (item) => item.dataset.group === stripGroup, (item) => stripGroupShow(item.dataset.group));
        // menuText: the name in the menu when it differs from the list's heading; sub: indented under the one before;
        // symbol: the icon of a group that carries no picture name.
        const group = (name, menuText = name, sub = false, symbol = null) => {
            into = document.createElement('section');
            into.className = 'plate-strip-group';
            into.dataset.group = name;
            const head = document.createElement('h4');
            head.className = 'plate-strip-title';
            head.textContent = name;
            into.append(head);
            list.append(into);
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'plate-strip-menu-item' + (sub ? ' is-sub' : '');
            item.dataset.group = name;
            item.title = name;
            const word = document.createElement('span');
            word.className = 'plate-strip-menu-word';
            word.textContent = menuText;
            item.append(subjectIcon(menuText, symbol), word);
            item.addEventListener('click', () => stripGroupShow(name));
            menu.append(item);
            return { section: into, item };
        };
        /* THE FIND'S DETAILS FIRST (07.10.2026: „die Fundangaben,
           also den Text, das würde ich als erste Kachel in der Seitenleiste
           nehmen") — one tile every plate wants, above the pictures it
           would otherwise follow at the very end. */
        /* „TEXT" AND NO LONGER „AUS DEM FUND" (30.10.2026) —
           the pictures come from the find as well; what sets this group
           apart is that it holds words. */
        group(t('Text'), t('Text'), false, 'subject-text');
        const details = document.createElement('div');
        details.className = 'plate-strip-tile plate-strip-details';
        details.draggable = true;
        // The name as a heading over the paper, as on every measures' tile.
        details.innerHTML = `<b>${esc(t('Fundangaben'))}</b>`;
        details.append(tileBody('info'));
        details.dataset.use = 'info';
        dragFrom(details, 'info', 'info');
        /* No click to set it up beforehand any more: the tile shows all
           there is, and the choice is made on the page after the drop
           (09.11.2026; tileBody). */
        into.append(details);

        /* THE KEYING WAYS, one's own confirmed ones, one tile each
           (finddetails.js, keyings; 09.11.2026, with the taxonomy session).
           Dragged like a values text: on a picture it lies where it is let
           go, on an empty cell or at an edge it is a cell of paper. UNDER
           „TEXT", beside the details, with no heading of their own
           (09.11.2026: „mach mir kein eigenes Label Schlüssel,
           sondern nimm die Schlüssel mit zum Textlabel … Das ist alles
           Text"). */
        const ways = values.keyings ?? [];
        if (ways.length > 0) {
            for (const way of ways) {
                const data = 'keying:' + way.key_id;
                const tile = document.createElement('div');
                tile.className = 'plate-strip-tile plate-strip-details plate-strip-keying';
                tile.draggable = true;
                tile.dataset.use = data;
                // The book and the whole chain of keys, as the plate heads it (keyingRows).
                const chain = (way.keys?.length ? way.keys : [way.key]).filter(Boolean).join(' → ');
                const head = [way.book, chain].filter(Boolean).join(' · ') || way.key_id;
                /* ONLY THE HEADING (09.11.2026: „gerade beim Gröger
                   ist die Überschrift der Schlüssel extrem lang, deshalb …
                   zeigen gar nicht den Inhalt an, sondern nur die Überschrift
                   zum Reinziehen … und dann erscheint der Schlüsselweg als
                   Text"): no way drawn small beneath it, at most three lines
                   of the heading (plates.css), the whole in the tooltip; set up
                   on the plate after the drop, as the details are. */
                tile.innerHTML = `<b>${esc(head)}</b>`;
                tile.title = head;
                dragFrom(tile, data, 'values');
                into.append(tile);
            }
        }

        const entries = [...pics.values()];
        /* WHAT THE MEASURES ADD UP TO, per layer: its numbers as words, its
           distribution, its structures in a row — each a tile to drag like a
           picture. A layer shows from its first measure; the distribution
           wants two with a breadth. */
        const layers = layersOf(pics);
        const placed = new Set();
        const measureTile = (data, kind, label, words = false) => {
            const tile = document.createElement('div');
            tile.className = 'plate-strip-tile plate-strip-measure' + (words ? ' plate-strip-words' : '');
            tile.dataset.use = data;
            tile.draggable = true;
            tile.append(tileBody(data));
            tile.addEventListener('click', () => chooseDraft(data));
            const name = document.createElement('b');
            name.textContent = label;
            /* THE NAME IS A HEADING, over what it names (07.10.2026:
               „das Label … würde ich über das Bild schreiben und nicht unter
               das Bild, also als Überschrift"). Below, it read as the
               caption of whatever came next in the column. */
            tile.prepend(name);
            dragFrom(tile, data, kind);
            into.append(tile);
        };
        const measureTiles = (layer) => {
            const rows = rowsOf(pics, layer, lengthStraight(host.style(), layer));
            const parts = partsOf(pics, layer);
            /* EACH TILE NAMES ITS LAYER (07.10.2026: „wenn man
               viele Bilder hat, weiß man nicht auf den ersten Blick, ob das
               jetzt von den Sporen ist oder von der Ascus-Messung") — the
               same words the side panel uses once one is laid down. And
               the values show what a drop lays down, line for line, with
               the air before the parts — the tile has a picture's room
               for them now. */
            /* A LAYER'S COUNTS (18.11.2026: „Bei den Tafeln kann
               ich noch nicht die Sektorenauswertung einfügen. Also
               Bildausschnitte und Text"): their numbers join the layer's
               values, and the frames and sectors stand in a row of their
               own, as photographed. */
            const counts = countsOf(pics, layer);
            // A layer of picture cutouts alone has no numbers to show.
            if (rows.length > 0 || parts.length > 0 || counts.length > 0) measureTile('values:' + layer, 'values', t('Werte: %s', layer), true);
            if (counts.length > 0) measureTile('count:' + layer, 'block', t('%s in Reihe (%d)', blockName({ layer, count: true }), counts.length));
            if (rows.filter(r => r.B != null).length >= 2) measureTile('plot:' + layer, 'block', t('Verteilung: %s', layer));
            if (rows.length > 0) measureTile('row:' + layer, 'block', t('%s in Reihe (%d)', layer, rows.length));
            /* EACH PART MEASURE ITS DISTRIBUTION AND ITS ROW
               (07.10.2026: „für die Teilmessungen haben wir ja auch eine
               Verteilungsgrafik, ebenso eine Reihe … und ausrichten schon
               bei der Messung") — the curve of its tab, its tiles turned
               as „Alle drehen" and „Umdrehen" left them. */
            for (const part of parts) {
                const d = { layer, part };
                const partRows = rowsFor(pics, d, styleNow());
                if (partRows.length >= 2) measureTile('plot:' + layer + '\n' + part, 'block', t('Verteilung: %s', blockName(d)));
                measureTile('row:' + layer + '\n' + part, 'block', t('%s in Reihe (%d)', blockName(d), partRows.length));
            }
            /* EACH NAME OF PICTURE CUTOUTS ITS ROW (07.10.2026:
               „ein paar angefärbte Ascusspitzen … eine Bildausschnittreihe,
               ohne Messen") — cut in the photo window, standing as they
               were cut there, base down and tip up. */
            for (const name of cropsOf(pics, layer)) {
                const d = { layer, crop: name };
                measureTile('crop:' + layer + '\n' + name, 'block',
                    t('%s in Reihe (%d)', blockName(d), rowsFor(pics, d, styleNow()).length));
            }
        };
        for (const { name, items } of subjectGroups(entries, host.photoTitles())) {
            group(name ?? t('Ohne Motivnamen'), name ?? t('Ohne Motivnamen'), false, name ? null : 'label');
            for (const p of items) {
                const tile = document.createElement('div');
                tile.className = 'plate-strip-tile';
                tile.draggable = true;
                tile.dataset.key = p.key;
                const img = document.createElement('img');
                img.alt = '';
                img.loading = 'lazy';
                /* AS DEVELOPED, NOT AS IT CAME (12.11.2026: „dass
                   dort die Vorschaubilder nicht die Bearbeitung zeigen … bei
                   der Drehung"): the same rendering the Fundbrowser's tiles
                   use (develop.js, developThumb), keyed as they are, so an
                   edit in the viewer reaches the strip at once
                   (developThumbsRefresh). The plate's cells always were. */
                img.develop = p.develop ?? null;
                developThumb(img, p.thumb, img.develop, p.key);
                if (p.src && p.src !== p.thumb) img.dataset.large = p.src;
                img.draggable = false;
                tile.append(img);
                if (p.by) {
                    const by = document.createElement('span');
                    by.className = 'plate-strip-by';
                    by.textContent = t('von %s', personName(p.by));
                    tile.append(by);
                }
                dragFrom(tile, 'pic:' + p.key, 'pic');
                tile.addEventListener('dblclick', () => viewPicture(p.key));
                into.append(tile);
            }
            /* THE GROUP'S MEASURES STAND WITH ITS PICTURES
               (06.10.2026: „Wir haben ja das Label Sporen und die Messung
               Sporen … Warum gruppieren wir das nicht in der Sidebar, genau
               dorthin?"): under „Sporen" the spores' pictures, then their
               values, distribution and row. */
            const layer = name === null ? undefined : layers.find(l => l.toLowerCase() === name.trim().toLowerCase());
            if (layer !== undefined) {
                /* ITS OWN POINT UNDER THE PICTURES (09.11.2026: „wir
                   haben ja das Label Sporen und … darunter … fallen einfach nur
                   Sporenbilder. Dann haben wir bei den Sporen aber eben auch die
                   Statistik, die Reihen, die Verteilung, Ornamenthöhe etc. Das
                   würde ich in der Sidebar … unter einen eigenen Punkt packen.
                   Ebenso natürlich die Statistiken und Reihen bei anderen
                   Messungen"): „Auswertung", indented under the pictures' name
                   in the menu, „Sporen · Auswertung" over its tiles in the
                   list. Gone again where the layer yields no tile. */
                const sub = group(`${name.trim()} · ${t('Auswertung')}`, t('Auswertung'), true, 'bars');
                measureTiles(layer);
                if (!sub.section.querySelector('.plate-strip-tile')) { sub.section.remove(); sub.item.remove(); }
                placed.add(layer);
            }
        }
        // Layers whose pictures carry another name — or none — keep a group of their own.
        const rest = layers.filter(l => !placed.has(l));
        if (rest.length > 0) {
            group(t('Aus den Messungen'), t('Aus den Messungen'), false, 'ruler');
            // No name over each layer any more: its tiles say it themselves.
            for (const layer of rest) measureTiles(layer);
        }

        stripChosenSync();
        // The group chosen before stays chosen while the find has it.
        const names = [...menu.children].map(b => b.dataset.group);
        stripGroupShow(names.includes(stripGroup) ? stripGroup : names[0], false);

        /* AS THE STRIP GROWS, THE PICTURES GROW („sieht man die
           Spalte weiter größer, vergrößern sich die Bilder weiter"). The
           rows of measures are drawn at their width and are drawn anew;
           a picture outgrows its 320-px preview at some point and then
           takes the larger file the page draws from — once, for good, and
           still lazily, so a group out of sight loads nothing. */
        let seen = 0;
        stripWatch = new ResizeObserver(() => {
            if (list.clientWidth === seen) return;
            seen = list.clientWidth;
            for (const c of list.querySelectorAll('canvas.plate-strip-row')) c.redraw?.();
            if (list.clientWidth * (window.devicePixelRatio || 1) < STRIP_SHARP) return;
            for (const img of list.querySelectorAll('img[data-large]')) {
                const large = img.dataset.large;
                delete img.dataset.large;
                // Developed as the preview was — the plain larger file would undo the turn.
                developThumb(img, large, img.develop, img.dataset.developOf);
            }
        });
        stripWatch.observe(list);
    }

    /** Shows one group beside the menu — the list itself only listens when
     *  the strip is wide enough for the two columns. */
    function stripGroupShow(name, scroll = true) {
        stripGroup = name;
        const now = strip.querySelector('.plate-strip-now');
        const sign = strip.querySelector(`.plate-strip-menu-item[data-group="${CSS.escape(name ?? '')}"] > svg`);
        now?.replaceChildren(...(name === null ? [] : [...(sign ? [sign.cloneNode(true)] : []), name]));
        for (const el of strip.querySelectorAll('[data-group]')) {
            const on = el.dataset.group === name;
            el.classList.toggle('is-active', on);
            if (el.tagName === 'BUTTON') el.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
        // Its rows were hidden until now and know their width only from here.
        for (const c of strip.querySelectorAll('.plate-strip-group.is-active canvas.plate-strip-row')) c.redraw?.();
        if (scroll) strip.querySelector('.plate-strip-list').scrollTop = 0;
    }

    /* THE STRIP'S WIDTH IS THE USER'S (07.10.2026: „dass man
       deren Größe ziehen kann … Gespeichert wird das in Local Storage, die
       Größe") — dragged at the edge on its rule and kept per device like
       the photo window's strip. The stylesheet clamps it between the width
       it always had and half the window; the page beside it is laid out
       anew only when its own size really changes, not at every pixel. */
    {
        // The widths of its first hour went when the untouched strip became
        // wide enough for the menu (plates.css) — otherwise the one device
        // that had tried the edge would never see the new start. Can go.
        settings.forget('plateStripWidth');
        const saved = Number(settings.get('plateStripPx', 0));
        if (saved > 0) mainEl.style.setProperty('--plate-strip-width', saved + 'px');
    }
    let stripDrag = null;   // {x, width}
    let pageFitQueued = false;
    const pageFitSoon = () => {
        if (pageFitQueued) return;
        pageFitQueued = true;
        requestAnimationFrame(() => {
            pageFitQueued = false;
            if (Math.abs(pageHeight() - pageEl.offsetHeight) > .5) render();
        });
    };
    stripEdge.addEventListener('pointerdown', (e) => {
        e.preventDefault();   // no text selection riding along
        stripDrag = { x: e.clientX, width: strip.offsetWidth };
        mainEl.classList.add('is-resizing');
        try { stripEdge.setPointerCapture(e.pointerId); } catch { /* egal */ }
    });
    stripEdge.addEventListener('pointermove', (e) => {
        if (stripDrag === null) return;
        mainEl.style.setProperty('--plate-strip-width', Math.max(0, Math.round(stripDrag.width + e.clientX - stripDrag.x)) + 'px');
        pageFitSoon();
    });
    const stripDragEnd = () => {
        if (stripDrag === null) return;
        stripDrag = null;
        mainEl.classList.remove('is-resizing');
        // What the clamp let through, so the next drag starts from what one sees.
        const width = strip.offsetWidth;
        if (width > 0) {
            mainEl.style.setProperty('--plate-strip-width', width + 'px');
            settings.set('plateStripPx', width);
        }
        pageFitSoon();
    };
    stripEdge.addEventListener('pointerup', stripDragEnd);
    stripEdge.addEventListener('pointercancel', stripDragEnd);

    /* THE STRIP FOLDS AS EVERY SIDE DOES (09.11.2026: „Bei den
       Tafeln können wir die linke Sidebar ja auch einklappbar machen" — the
       find's head, the picture rail and the photo window's sides wear the
       same since that day): a small sign in the middle of its rule folds
       it; folded, a thin bar at the left stays, and the band beside it to
       the window's edge counts as the bar — the pointer there lays the
       strip OVER the page (is-strip-peek, the page keeps its place), it
       folds again once the pointer has left, a click keeps it open. Per
       device (settings plateStripFolded). */
    const stripFoldBtn = document.createElement('button');
    stripFoldBtn.type = 'button';
    stripFoldBtn.className = 'plate-strip-fold';
    stripFoldBtn.title = t('Kachelleiste einklappen');
    stripFoldBtn.setAttribute('aria-label', t('Kachelleiste einklappen'));
    stripFoldBtn.innerHTML = iconHtml('fold-triangle');
    const stripHandle = document.createElement('button');
    stripHandle.type = 'button';
    stripHandle.className = 'plate-strip-handle';
    stripHandle.title = t('Kachelleiste ausklappen');
    stripHandle.setAttribute('aria-label', t('Kachelleiste ausklappen'));
    stripHandle.innerHTML = iconHtml('fold-triangle');
    mainEl.append(stripFoldBtn, stripHandle);
    let stripPeekTimer = null;
    function stripFold(on, keep = true) {
        mainEl.classList.toggle('is-strip-folded', on);
        mainEl.classList.remove('is-strip-peek');
        if (keep) settings.set('plateStripFolded', on);
        // The page beside it is laid out anew for the room that came or went.
        render();
    }
    function stripPeek() {
        clearTimeout(stripPeekTimer);
        if (mainEl.classList.contains('is-strip-folded')) mainEl.classList.add('is-strip-peek');
    }
    stripHandle.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') stripPeek(); });
    for (const n of [stripHandle, strip]) {
        n.addEventListener('pointerenter', () => clearTimeout(stripPeekTimer));
        n.addEventListener('pointerleave', (e) => {
            if ([stripHandle, strip].some((m) => m.contains(e.relatedTarget))) return;
            clearTimeout(stripPeekTimer);
            stripPeekTimer = setTimeout(() => mainEl.classList.remove('is-strip-peek'), 250);
        });
    }
    // The band left of the bar, to the window's edge, counts as the bar.
    document.addEventListener('pointermove', (e) => {
        if (e.pointerType !== 'mouse' || e.buttons || !mainEl.classList.contains('is-strip-folded')) return;
        const r = stripHandle.getBoundingClientRect();
        if (r.width > 0 && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom) stripPeek();
    });
    stripHandle.addEventListener('click', () => stripFold(false));
    stripFoldBtn.addEventListener('click', () => stripFold(true));
    if (settings.get('plateStripFolded', false)) mainEl.classList.add('is-strip-folded');

    /* THE SETTINGS ON THE RIGHT, THE SAME (09.11.2026: „und mach
       die Rechte auch einklappbar und größenveränderbar. Wie die linke
       Sidebar"): its width dragged at the edge on its rule and kept per
       device (plateSidePx), folded to a thin bar at the right edge that
       STAYS there while the panel opens to its left over the page — the
       band to the window's edge counting as the bar — a click keeps it
       open (plateSideFolded). */
    const sideEl = mainEl.querySelector('.plate-side');
    const sideEdge = document.createElement('span');
    sideEdge.className = 'plate-side-edge';
    sideEdge.setAttribute('aria-hidden', 'true');
    const sideFoldBtn = document.createElement('button');
    sideFoldBtn.type = 'button';
    sideFoldBtn.className = 'plate-side-fold';
    sideFoldBtn.title = t('Seitenleiste einklappen');
    sideFoldBtn.setAttribute('aria-label', t('Seitenleiste einklappen'));
    sideFoldBtn.innerHTML = iconHtml('fold-triangle');
    const sideHandle = document.createElement('button');
    sideHandle.type = 'button';
    sideHandle.className = 'plate-side-handle';
    sideHandle.title = t('Seitenleiste ausklappen');
    sideHandle.setAttribute('aria-label', t('Seitenleiste ausklappen'));
    sideHandle.innerHTML = iconHtml('fold-triangle');
    mainEl.append(sideEdge, sideFoldBtn, sideHandle);
    {
        const saved = Number(settings.get('plateSidePx', 0));
        if (saved > 0) mainEl.style.setProperty('--plate-side-width', saved + 'px');
    }
    let sideDrag = null;   // {x, width}
    sideEdge.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        sideDrag = { x: e.clientX, width: sideEl.offsetWidth };
        mainEl.classList.add('is-resizing');
        try { sideEdge.setPointerCapture(e.pointerId); } catch { /* without, then */ }
    });
    sideEdge.addEventListener('pointermove', (e) => {
        if (sideDrag === null) return;
        // Leftwards widens: the panel stands at the right.
        mainEl.style.setProperty('--plate-side-width', Math.max(0, Math.round(sideDrag.width - (e.clientX - sideDrag.x))) + 'px');
        pageFitSoon();
    });
    const sideDragEnd = () => {
        if (sideDrag === null) return;
        sideDrag = null;
        mainEl.classList.remove('is-resizing');
        const width = sideEl.offsetWidth;
        if (width > 0) {
            mainEl.style.setProperty('--plate-side-width', width + 'px');
            settings.set('plateSidePx', width);
        }
        pageFitSoon();
    };
    sideEdge.addEventListener('pointerup', sideDragEnd);
    sideEdge.addEventListener('pointercancel', sideDragEnd);
    let sidePeekTimer = null;
    function sideFold(on, keep = true) {
        mainEl.classList.toggle('is-side-folded', on);
        mainEl.classList.remove('is-side-peek');
        if (keep) settings.set('plateSideFolded', on);
        render();
    }
    function sidePeek() {
        clearTimeout(sidePeekTimer);
        if (mainEl.classList.contains('is-side-folded')) mainEl.classList.add('is-side-peek');
    }
    sideHandle.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') sidePeek(); });
    for (const n of [sideHandle, sideEl]) {
        n.addEventListener('pointerenter', () => clearTimeout(sidePeekTimer));
        n.addEventListener('pointerleave', (e) => {
            if ([sideHandle, sideEl].some((m) => m.contains(e.relatedTarget))) return;
            clearTimeout(sidePeekTimer);
            sidePeekTimer = setTimeout(() => mainEl.classList.remove('is-side-peek'), 250);
        });
    }
    // The band right of the bar, to the window's edge, counts as the bar.
    document.addEventListener('pointermove', (e) => {
        if (e.pointerType !== 'mouse' || e.buttons || !mainEl.classList.contains('is-side-folded')) return;
        const r = sideHandle.getBoundingClientRect();
        if (r.width > 0 && e.clientX >= r.left && e.clientY >= r.top && e.clientY <= r.bottom) sidePeek();
    });
    sideHandle.addEventListener('click', () => sideFold(false));
    sideFoldBtn.addEventListener('click', () => sideFold(true));
    if (settings.get('plateSideFolded', false)) mainEl.classList.add('is-side-folded');
    /* WHAT IS ON THE PLATE WEARS A TICK — the pictures first, and since
       07.10.2026 the find's details, the values, the distributions and the
       rows too („die Verteilungen und die Reihen werden nicht
       abgehakt … bei den Werten würde ich auch einen Haken machen, aber
       Werte kann man eben mehrmals reinziehen … dennoch, sobald sie einmal
       drin ist"). A measures' tile says what it drops (data-use), and the
       plate is asked for the same words — on any of its pages. */
    /** The strip tile a block was laid down from (its data-use). */
    const blockUse = (b) => (b.count === true ? `count:${b.layer}`
        : typeof b.crop === 'string' ? `crop:${b.layer}\n${b.crop}`
        : `${b.kind}:${b.layer}${b.part ? '\n' + b.part : ''}`);
    function stripUsed() {
        const cells = layout.pages.flatMap(p => cellsOf(p.root));
        const used = new Set(cells.map(c => c.pic));
        for (const c of cells) {
            if (c.block) {
                used.add(blockUse(c.block));
            }
            if (c.info) used.add('info');
            for (const item of c.items) {
                if (item.kind === 'values') used.add('values:' + item.layer);
                if (item.kind === 'keying') used.add('keying:' + item.key_id);
                if (item.kind === 'info') used.add('info');
                // An inset's picture or block is on the plate too.
                if (item.kind === 'inset') {
                    const b = item.cell.block;
                    if (item.cell.pic) used.add(item.cell.pic);
                    if (b) used.add(blockUse(b));
                }
            }
        }
        strip.querySelectorAll('.plate-strip-tile[data-key]').forEach(tile => tile.classList.toggle('is-used', used.has(tile.dataset.key)));
        strip.querySelectorAll('.plate-strip-tile[data-use]').forEach(tile => tile.classList.toggle('is-used', used.has(tile.dataset.use)));
    }

    /* --- The side: what is selected, then the plate ---------------------- */
    /* THE SIDE'S TWO TABS — „Kachel" for what is chosen on the page,
       „Allgemein" for the plate and how it works. A plate opens on
       „Allgemein", where the help stands; whatever gets chosen turns to
       „Kachel" by itself, a click or a drop alike. Letting go of it leaves
       the tab where it is — the panel then says what to click. */
    const sideTabs = { cell: document.getElementById('plate-tab-cell'), general: document.getElementById('plate-tab-general') };
    const sidePanels = { cell: document.getElementById('plate-panel-cell'), general: document.getElementById('plate-panel-general') };
    const selNone = document.getElementById('plate-selection-none');
    let sideShown = null;     // what the „Kachel" tab last showed
    function sideTab(name) {
        for (const key of Object.keys(sideTabs)) {
            const on = key === name;
            sideTabs[key].classList.toggle('is-active', on);
            sideTabs[key].setAttribute('aria-selected', String(on));
            sidePanels[key].hidden = !on;
        }
    }
    for (const [key, tab] of Object.entries(sideTabs)) tab.addEventListener('click', () => sideTab(key));

    function panelSync() {
        // What is chosen on the page wins over a preset from the strip.
        if (selected) chosenDraft = null;
        panelFill();
        const now = selBox.hidden ? null : selected ? (selected.item ?? selected.cell) : 'draft:' + chosenDraft;
        if (now !== null && now !== sideShown) sideTab('cell');
        sideShown = now;
        selNone.hidden = now !== null;
        if (chosenDraft !== null) stripTileSync(chosenDraft);
        stripChosenSync();
    }

    function panelFill() {
        selBox.textContent = '';
        const draft = !selected && chosenDraft !== null ? drafts.get(chosenDraft) ?? null : null;
        selBox.hidden = !editable || (!selected && !draft);
        if (selBox.hidden) return;
        const { item } = selected ?? { item: null };
        let cell = selected?.cell ?? null;
        let d = draft ?? item ?? cell.info ?? cell.block ?? (cell.pic ? cell : null);
        if (!d) { selected = null; selBox.hidden = true; return; }
        // text | info | values — the words; plot | row — the blocks; pic — a picture.
        let kind = draft ? (chosenDraft === 'info' ? 'info' : d.kind)
            : item ? item.kind : cell.info ? 'info' : cell.block ? d.kind : 'pic';
        handAt = { kind, d, cell };
        if (kind === 'pic') {
            picturePanel(cell);
            return;
        }
        /* AN INSET'S PANEL, and where it holds a block, that block's own
           settings beneath it (07.10.2026: „die Einstellungen
           auch für das Einsatzbild anbieten") — the same questions as for
           a block in a cell, written into the inset's block, its cell the
           one redrawn. Its own tools close the panel, as the remove button
           closes a cell's. */
        let inset = null;
        if (kind === 'inset') {
            const tools = insetPanel(item);
            if (!item.cell.block) {
                handAt = { kind: 'pic', d: null, cell: item.cell };
                selBox.append(measuresControl(item.cell), notesControl(item.cell), ...cornerControls(item.cell), tools);
                return;
            }
            inset = tools;
            cell = item.cell;
            d = item.cell.block;
            kind = d.kind;
            handAt = { kind, d, cell };
        }
        if (!inset) {
            const title = document.createElement('h3');
            title.className = 'plate-side-title';
            title.textContent = {
                info: t('Fundangaben'), values: t('Werte: %s', d.layer),
                keying: t('Schlüsselweg: %s', (values.keyings ?? []).find(w => w.key_id === d.key_id)?.book || d.key_id),
                plot: t('Verteilung: %s', blockName(d)), row: t('%s in Reihe', blockName(d)),
            }[kind];
            selBox.append(title);
        }
        const hint = (words) => {
            const p = document.createElement('p');
            p.className = 'hint-text small';
            p.textContent = words;
            selBox.append(p);
        };
        if (draft) hint(t('So kommt es auf die Tafel, wenn du es hineinziehst.'));
        const ticks = (legendText, entries, isOn, onToggle, quietIf = () => false) => {
            const list = document.createElement('fieldset');
            list.className = 'field question plate-ticks';
            const legend = document.createElement('legend');
            legend.textContent = legendText;
            list.append(legend);
            for (const [key, label] of entries) {
                const l = document.createElement('label');
                l.className = 'choice-row';
                const box = document.createElement('input');
                box.type = 'checkbox';
                box.checked = isOn(key);
                box.addEventListener('change', () => { onToggle(key, box.checked); render(); });
                const words = document.createElement('span');
                words.textContent = label;
                // A detail the find does not carry shows nothing — say so.
                if (quietIf(key)) {
                    words.classList.add('is-empty');
                    l.title = t('Dieser Fund hat dazu keine Angabe.');
                }
                l.append(box, words);
                list.append(l);
            }
            selBox.append(list);
        };

        /* THE DETAILS IN THEIR OWN ORDER (09.11.2026: „dass man
           die Reihenfolge sortieren kann. Vielleicht per Drag and Drop. Das
           sollte dann auch so gespeichert werden … für alle"). The ticked
           ones stand first, in the field's order, the rest after them in
           the list's; the grip at each row's front moves it. The order of
           the list IS the order of the field: a tick keeps the row where
           it stands. The hand takes d.fields as it is, so the next field
           comes in the same order. */
        const detailTicks = (d) => {
            /* A LEERZEILE is a row of its own (plateparts.js, DETAIL_GAP), as
               often as wanted: ticked like the others and moved by its grip;
               its tick taken off, it goes. „Leerzeile hinzufügen" puts one
               under the ticked rows. */
            // Named „Abstand", not „Leerzeile" (09.11.2026): in one line it widens a gap and breaks nothing.
            const all = [...detailLabels(), [DETAIL_GAP, t('Abstand')]];
            const keys = [...d.fields.filter(k => all.some(([key]) => key === k)),
                ...all.map(([key]) => key).filter(k => k !== DETAIL_GAP && !d.fields.includes(k))];
            const list = document.createElement('fieldset');
            list.className = 'field question plate-ticks is-sortable';
            const legend = document.createElement('legend');
            legend.textContent = t('Was im Feld steht');
            list.append(legend);
            const fieldsNow = () => [...list.querySelectorAll('.choice-row')]
                .filter(r => r.querySelector('input').checked).map(r => r.dataset.key);
            for (const key of keys) {
                const label = all.find(([k]) => k === key)[1];
                const l = document.createElement('label');
                l.className = 'choice-row';
                l.dataset.key = key;
                const grip = document.createElement('span');
                grip.className = 'plate-grip';
                grip.title = t('Ziehen ändert die Reihenfolge');
                grip.innerHTML = iconHtml('grip');
                const box = document.createElement('input');
                box.type = 'checkbox';
                box.checked = key === DETAIL_GAP || d.fields.includes(key);
                box.addEventListener('change', () => { d.fields = fieldsNow(); render(); });
                const words = document.createElement('span');
                words.textContent = label;
                if (key === DETAIL_GAP) l.classList.add('is-gap');
                else if (!values[key]) {
                    words.classList.add('is-empty');
                    l.title = t('Dieser Fund hat dazu keine Angabe.');
                }
                grip.addEventListener('pointerdown', (e) => {
                    e.preventDefault();
                    /* THE WINDOW LISTENS, NOT THE GRIP: moving the row in the
                       list takes the grip out of the document for a moment,
                       and that lets go of a pointer capture — the release
                       then went elsewhere and the new order was never kept. */
                    l.classList.add('is-lifted');
                    const move = (ev) => {
                        // The row goes before the first row whose middle lies below the pointer.
                        const rows = [...list.querySelectorAll('.choice-row')].filter(r => r !== l);
                        const before = rows.find(r => { const b = r.getBoundingClientRect(); return ev.clientY < b.top + b.height / 2; });
                        if (before) list.insertBefore(l, before); else list.append(l);
                    };
                    const up = () => {
                        window.removeEventListener('pointermove', move);
                        window.removeEventListener('pointerup', up);
                        window.removeEventListener('pointercancel', up);
                        l.classList.remove('is-lifted');
                        const now = fieldsNow();
                        if (JSON.stringify(now) === JSON.stringify(d.fields)) return;
                        d.fields = now;
                        render();
                        handSoon();
                    };
                    window.addEventListener('pointermove', move);
                    window.addEventListener('pointerup', up);
                    window.addEventListener('pointercancel', up);
                });
                // A click on the grip moves; it must not tick the box of its label.
                grip.addEventListener('click', (e) => e.preventDefault());
                l.append(grip, box, words);
                list.append(l);
            }
            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'button subtle small';
            add.innerHTML = iconHtml('plus');
            add.append(t('Abstand hinzufügen'));
            add.addEventListener('click', () => {
                // Under the ticked rows; the grip takes it where it belongs.
                d.fields = [...d.fields, DETAIL_GAP];
                render();
                handSoon();
            });
            list.append(add);
            selBox.append(list);
        };

        if (kind === 'info') {
            detailTicks(d);
            /* THREE ANSWERS since 09.11.2026 („eine dritte Option
               … Symbole"): the word, the sign (plateparts.js, DETAIL_ICONS),
               or nothing. The question names what stands in front, so the
               answers can be plain nouns. */
            selBox.append(choiceLine(t('Vor jeder Angabe'),
                [['1', t('Bezeichnung')], ['icons', t('Symbol')], ['0', t('nichts')]],
                d.labels === 'icons' ? 'icons' : d.labels ? '1' : '0',
                v => { d.labels = v === 'icons' ? 'icons' : v === '1'; }));
            selBox.append(choiceLine(t('Anordnung'),
                [['lines', t('untereinander')], ['one', t('in einer Zeile')]], d.flow, v => { d.flow = v; }));
        }
        /* A KEYING WAY: what of it, and under which name (plateparts.js,
           keyingRows). Written and confirmed in Bestimmung; here only shown. */
        if (kind === 'keying') {
            hint(t('Der Weg und die Bemerkung kommen aus der Bestimmung dieses Fundes. Änderst du dort etwas, steht hier das Neue.'));
            /* Short answers, one line (20.11.2026): the question names what
               is chosen, so „Schlüsselweg" says „nur den Weg". */
            selBox.append(choiceLine(t('Was zeigen'),
                [['both', t('beides')], ['path', t('Schlüsselweg')], ['remark', t('Bemerkung')]],
                d.show ?? 'both', v => { d.show = v; }));
            selBox.append(choiceLine(t('Beschriftet mit'),
                [['both', t('Buch und Schlüssel')], ['book', t('Buch')], ['key', t('Schlüssel')]],
                ['book', 'key'].includes(d.label) ? d.label : 'both', v => { d.label = v; }));
        }
        if (kind === 'values') {
            hint(t('Die Zahlen kommen aus allen Messungen dieser Ebene am Fund, wie im Bildbetrachter. Misst du später nach, stehen hier die neuen.'));
            // The structure's three lines, then one per part measure of the layer.
            const partKeys = partsOf(pics, d.layer).map(p => 'part:' + p);
            const order = [...VALUE_KEYS, ...partKeys];
            // A layer that is only counted has no lengths to choose among: its lines are the counts' (countLines).
            if (rowsOf(pics, d.layer).length > 0 || partKeys.length > 0) ticks(t('Welche Zeilen'),
                [['range', t('Länge × Breite, mit Bereich')], ['qn', t('Q und Anzahl')], ['mean', t('Durchschnitt')],
                 ['spread', t('Streuung und Ausreißer')],
                 ...partKeys.map(k => [k, k.slice(5)])],
                key => d.lines.includes(key),
                (key, on) => { d.lines = order.filter(k => (k === key ? on : d.lines.includes(k))); });
            selBox.append(switchQuestion(t('Mit dem Namen davor'), !!d.name, on => { d.name = on; }));
        }
        if (kind === 'plot') {
            /* See-through only as an inset: there a picture lies behind it.
               A distribution filling its cell has nothing behind but paper. */
            selBox.append(choiceLine(t('Hintergrund'),
                [['white', t('weiß'), 'white'], ['black', t('schwarz'), 'black']], d.ground === 'black' ? 'black' : 'white', v => { d.ground = v; },
                inset ? opacityControl(cell, d) : null));
            // The line under the cloud (plateparts.js, blockPlotInner) — a part's curve has none.
            if (!d.part) {
                selBox.append(switchQuestion(t('Anzahl, Streuung und Ausreißer darunter'), d.spread !== false, on => { d.spread = on; }));
            }
            hint(d.part
                ? t('Jeder Punkt ist eine Messung. Die Kurve zeigt, wo die meisten liegen; die gestrichelte Linie ist der Durchschnitt.')
                : t('Jeder Punkt ist eine Messung. Die grüne Ellipse umschließt den dichten Kern, etwa 68 % der Messungen. Die gestrichelte zeigt, wie weit die Messungen gewöhnlich reichen. Ab zehn Messungen verschieben einzelne Ausreißer die Ellipsen nicht.'));
        }
        // A part measure is one length: no bend to read, no mark, one order;
        // a row of picture cutouts measures nothing at all.
        const cropRow = typeof d.crop === 'string';
        const whole = !d.part && !cropRow && d.count !== true;
        /* THE LENGTH ALONG THE BEND OR STRAIGHT, as in the photo window
           (07.10.2026) — only where the layer holds a length traced as a
           polyline. It starts at the window's choice for the layer; picked
           here, it is this plate's own (straightOf). */
        if (whole && (kind === 'values' || kind === 'plot') && rowsOf(pics, d.layer).some(r => r.bent)) {
            selBox.append(choiceLine(t('Länge'),
                [['bent', t('gebogen')], ['straight', t('klassisch')]],
                straightOf(d, host.style()) ? 'straight' : 'bent', v => { d.straight = v === 'straight'; }));
        }
        /* WHICH OF THE LAYER'S MEASURES, by mark — as the photo window's
           „Zeigen" (07.10.2026): all, one kind, or the unmarked. Only where
           the layer's measures carry a mark; the row of spores too, so a
           plate can line up the chrysocystidia alone. */
        const marks = whole && (kind === 'values' || kind === 'plot' || kind === 'row') ? marksOf(pics, d.layer) : [];
        if (marks.length > 0) {
            selBox.append(choiceLine(t('Zeigen'),
                [['*', t('Alle')], ...marks.map(mark => [mark, mark]), ['', t('ohne Merkmal')]],
                typeof d.mark === 'string' ? d.mark : '*', v => { d.mark = v === '*' ? null : v; }));
        }
        if (kind === 'row') {
            if (d.count === true) {
                hint(t('Jeder Rahmen und jede Ellipse so, wie das Foto ausgerichtet ist, mit den gezählten Punkten. Haben alle Bilder einen Maßstab, stehen sie im echten Größenverhältnis mit einem Balken.'));
            } else if (cropRow) {
                hint(t('Die Ausschnitte stehen so, wie du sie im Bildbetrachter gesetzt hast: in dieser Reihenfolge, die Basis unten, die Spitze oben. Haben alle Bilder einen Maßstab, stehen sie im echten Größenverhältnis mit einem Balken, sonst alle gleich groß.'));
            } else if (whole) {
                hint(t('Alle in einem Maßstab, mit einem Balken für die ganze Kachel. Spiegeln geht im Bildbetrachter, an der einzelnen Messung.'));
                selBox.append(choiceLine(t('Sortiert nach'),
                    [['L', t('Länge')], ['B', t('Breite')], ['Q', 'Q']], d.sort, v => { d.sort = v; }));
            } else {
                hint(t('Alle in einem Maßstab, nach Länge sortiert. Ausrichten geht im Bildbetrachter: „Alle drehen" und „Umdrehen".'));
            }
            selBox.append(airControl(cell, d));
            const look = rowLook(d, styleNow());
            selBox.append(choiceLine(t('Linien zwischen den Ausschnitten'),
                [['0', t('keine')], ['0.6', t('fein')], ['1.5', t('breiter')]], String(look.gap), v => { d.gap = Number(v); }));
            /* „HINTERGRUND", not „Farbe der Linien" (09.11.2026):
               the same colour fills the margin and the empty frames at the
               end of a last line that is not full — it is the row's ground. */
            selBox.append(choiceLine(t('Hintergrund'),
                [['#ffffff', t('weiß'), 'white'], ['#000000', t('schwarz'), 'black']], look.ground, v => { d.gapColor = v; }));
            selBox.append(choiceLine(t('Rand außen herum'),
                [['0', t('keiner')], [String(ROW_PAD_THIN), t('schmal')], ['4', t('breit')]], String(look.pad), v => { d.pad = Number(v); }));
            if (cell) selBox.append(...cornerControls(cell));
        }

        if (kind === 'info' || kind === 'values' || kind === 'keying') {
            const size = document.createElement('div');
            size.className = 'field question plate-inline';
            size.innerHTML = `<p class="plate-question">${esc(t('Schriftgröße'))}</p>`;
            const stepper = document.createElement('div');
            stepper.className = 'plate-stepper';
            const smaller = document.createElement('button');
            smaller.type = 'button';
            smaller.className = 'button subtle small';
            smaller.textContent = 'A−';
            smaller.title = t('Kleiner');
            const shown = document.createElement('output');
            shown.textContent = t('%d pt', d.pt);
            const bigger = document.createElement('button');
            bigger.type = 'button';
            bigger.className = 'button subtle small';
            bigger.textContent = 'A+';
            bigger.title = t('Größer');
            const step = (dir) => {
                const at = PT_STEPS.findIndex(p => p >= d.pt);
                d.pt = PT_STEPS[clamp((at === -1 ? PT_STEPS.length - 1 : at) + dir, 0, PT_STEPS.length - 1)];
                remember(sizeKey(kind), d.pt);
                render();
            };
            smaller.addEventListener('click', () => step(-1));
            bigger.addEventListener('click', () => step(1));
            stepper.append(smaller, shown, bigger);
            size.append(stepper);
            selBox.append(size);

            // On a picture words may stand on no ground; a tile is paper. A
            // preset may land either way, so it offers all three.
            const free = item || draft;
            /* „HINTERGRUND" LIKE EVERY OTHER (20.11.2026: „Grund.
               Keiner weiß schwarz. Als erstes würde ich das als Hintergrund
               mal bezeichnen, dass wir einheitliche Titel haben. Und dann …
               auch einen Einzeiler"): it was „Grund" with three radios.
               „keiner" is a square crossed out, and the opacity stands in
               the same group, under it. */
            const grounds = free ? [['none', t('keiner'), 'none'], ['white', t('weiß'), 'white'], ['black', t('schwarz'), 'black']]
                : [['white', t('weiß'), 'white'], ['black', t('schwarz'), 'black']];
            selBox.append(choiceLine(t('Hintergrund'), grounds,
                free ? d.ground : (d.ground === 'black' ? 'black' : 'white'), v => { d.ground = v; },
                free && d.ground !== 'none' ? opacityControl(cell, d) : null));
        }

        // A preset lies nowhere yet — there is nothing to take off the page.
        if (draft) return;
        if (inset) {
            selBox.append(inset);
            return;
        }
        // Removing is the red × on the thing itself (removeMark), or the cell's bin.
    }

    /** A chosen picture's panel: its writing — which measuring layers' texts,
     *  arrows and counting boxes it shows — and the way into the viewer. */
    function picturePanel(cell) {
        const title = document.createElement('h3');
        title.className = 'plate-side-title';
        title.textContent = pics.get(cell.pic)?.title || t('Bild');
        selBox.append(title, measuresControl(cell), notesControl(cell), ...cornerControls(cell), viewerButton(cell.pic));
    }

    function viewerButton(key) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'button subtle small';
        b.innerHTML = iconHtml('photo') + esc(t('Im Bildbetrachter öffnen'));
        b.addEventListener('click', () => viewPicture(key));
        return b;
    }

    /**
     * WHICH LAYERS' WRITING THE PICTURE SHOWS (08.10.2026: „Eine
     * Schrift sitzt auf einer Messebene. Und ich kann mehrere Beschriftungen
     * auf mehrere Messebenen pro Bild legen. Die müsste man dann in den
     * Kacheleinstellungen auswählen können."). A tick per layer that holds a
     * text, an arrow or a counting box on this picture; none ticked, none
     * shown — the plate says what it wants of the picture.
     */
    function notesControl(cell) {
        const layers = noteLayers(pics.get(cell.pic));
        if (layers.length === 0) {
            const p = document.createElement('p');
            p.className = 'hint-text small';
            p.textContent = t('Dieses Bild hat noch keine Beschriftung. Text, Pfeil und Zählrechteck setzt du im Bildbetrachter, auf einer Messebene.');
            return p;
        }
        const list = document.createElement('fieldset');
        list.className = 'field question plate-ticks';
        const legend = document.createElement('legend');
        legend.textContent = t('Beschriftung zeigen');
        list.append(legend);
        for (const { layer } of layers) {
            const l = document.createElement('label');
            l.className = 'choice-row';
            const tick = document.createElement('input');
            tick.type = 'checkbox';
            tick.checked = (cell.notes ?? []).includes(layer);
            tick.addEventListener('change', () => {
                const others = (cell.notes ?? []).filter(n => n !== layer);
                cell.notes = tick.checked ? [...others, layer] : others;
                render();
            });
            // The layer's name alone (08.10.2026) — what it holds is in the viewer.
            const words = document.createElement('span');
            words.textContent = layer;
            l.append(tick, words);
            list.append(l);
        }
        const hint = document.createElement('p');
        hint.className = 'hint-text small';
        hint.textContent = t('Die Größe der Schrift stellst du unter „Allgemein“ ein.');
        const box = document.createElement('div');
        box.append(list, hint);
        return box;
    }

    /**
     * WHICH LAYERS' MEASURES THE PICTURE SHOWS (30.10.2026: „wenn
     * ich die Maße wirklich dort anzeigen will, die Ebene eben der Zystide
     * auswählen"). A tick per layer that holds a line, a bent length or a
     * length × breadth on this picture, as „Beschriftung zeigen" ticks the
     * writing; the same picture twice on the plate shows one layer here,
     * another there. Nothing at all where the picture holds no measure —
     * an empty question would only stand in the way of the writing's.
     */
    function measuresControl(cell) {
        const layers = measureLayers(pics.get(cell.pic));
        const box = document.createElement('div');
        if (layers.length === 0) return box;
        const list = document.createElement('fieldset');
        list.className = 'field question plate-ticks';
        const legend = document.createElement('legend');
        legend.textContent = t('Messungen zeigen');
        list.append(legend);
        for (const layer of layers) {
            const l = document.createElement('label');
            l.className = 'choice-row';
            const tick = document.createElement('input');
            tick.type = 'checkbox';
            tick.checked = (cell.measures ?? []).includes(layer);
            tick.addEventListener('change', () => {
                const others = (cell.measures ?? []).filter(n => n !== layer);
                cell.measures = tick.checked ? [...others, layer] : others;
                render();
            });
            const words = document.createElement('span');
            words.textContent = layer;
            l.append(tick, words);
            list.append(l);
        }
        box.append(list);
        return box;
    }

    /**
     * WHERE THE BAR AND THE MEDIUM STAND, in the panel only
     * (08.11.2026: „Maßstab und Medium … nur in der Sidebar anzeigen, um die
     * ein- und auszublenden und auch entsprechend die Position dort setzen
     * … 4 Schaltflächen mit jeweils 1 Pfeil ins Eck"). A question each: a
     * tick in its heading to show it (headTick), and under it, while it is
     * on, a block of four buttons laid out as the corners they point into
     * — the chosen one lit. The tick brings it back where it stands by
     * default (the bar bottom right, the medium top left, which the bar
     * leaves free, the label top right). Only what the cell can show:
     * the bar where the picture has a scale or the cell holds a row, the
     * medium where the picture names one, the label where it has a name.
     */
    function cornerControls(cell) {
        const pic = cell.pic ? pics.get(cell.pic) : null;
        /* A ROW names its medium and its layer as a picture does
           (plateparts.js, rowMediaLabel, rowLabel, 09.11.2026). */
        const row = cell.block?.kind === 'row' ? rowsFor(pics, cell.block, styleNow()) : null;
        /* LABEL, MEDIUM, MASSSTAB from the top (20.11.2026: „ganz
           oben Label, dann Medium, dann Maßstab") — each built where its
           data is at hand, the later ones laid in front. */
        const out = [];
        if (pic?.scale || cell.block?.kind === 'row') {
            const set = cornerControl(cell, 'bar', t('Maßstab'), 'br');
            /* A ROW'S BAR in a line of its own or over the pictures
               (plateparts.js, rowBarOver) — then in the frame that stands
               in the chosen corner. */
            /* ONE TICK, „in eigener Zeile" (20.11.2026: „In
               eigener Zeile Haken rein, in eigener Zeile ohne Haken über
               dem Bild") — before, two radios with „über dem Bild". */
            if (set.on && cell.block?.kind === 'row') {
                const l = document.createElement('label');
                l.className = 'choice-row plate-corner-sub';
                const tick = document.createElement('input');
                tick.type = 'checkbox';
                tick.checked = !cell.barOver;
                tick.addEventListener('change', () => { cell.barOver = !tick.checked; render(); });
                const span = document.createElement('span');
                span.textContent = t('in eigener Zeile');
                l.append(tick, span);
                set.subs.append(l);
            }
            out.push(set);
        }
        const label = row ? rowMediaLabel(row, cell.medFull) : mediaLabel(pic?.media, cell.medFull);
        if (label) {
            const set = cornerControl(cell, 'med', t('Medium'), 'tl');
            /* Short or written out (media.js, mediaLabel) — SV or
               Sulfovanillin. ONE TICK, „abgekürzt" (20.11.2026:
               „Haken dran, ist es abgekürzt, ist der Haken raus, ist es
               ausgeschrieben, brauchen wir keine 2 Zeilen für") — before,
               two radios „abgekürzt" and „ausgeschrieben". */
            if (set.on) {
                const l = document.createElement('label');
                l.className = 'choice-row plate-corner-sub';
                const tick = document.createElement('input');
                tick.type = 'checkbox';
                tick.checked = !cell.medFull;
                tick.addEventListener('change', () => { cell.medFull = !tick.checked; render(); });
                const span = document.createElement('span');
                span.textContent = t('abgekürzt');
                l.append(tick, span);
                set.subs.append(l);
            }
            out.unshift(set);
        }
        const name = row ? rowLabel(cell.block, row, { labelMarks: [] }) : String(pic?.title ?? '').trim();
        if (name) {
            const set = cornerControl(cell, 'label', t('Label'), 'tr');
            /* THE MARKS BEHIND THE NAME — the kinds the picture's measures
               were given (Chrysozystide …): a tick each, shown in brackets
               after the name (plateparts.js, pictureLabel). */
            if (set.on) for (const mark of row ? rowLabelSubs(cell.block, row) : pictureMarks(pic)) {
                const l = document.createElement('label');
                l.className = 'choice-row plate-corner-sub';
                const tick = document.createElement('input');
                tick.type = 'checkbox';
                tick.checked = (cell.labelMarks ?? []).includes(mark);
                tick.addEventListener('change', () => {
                    const others = (cell.labelMarks ?? []).filter(m => m !== mark);
                    cell.labelMarks = tick.checked ? [...others, mark] : others;
                    render();
                });
                const words = document.createElement('span');
                words.textContent = t('mit %s', mark);
                l.append(tick, words);
                set.subs.append(l);
            }
            out.unshift(set);
        }
        return out;
    }

    /* EACH PIECE OF WRITING ITS OWN INK AND SOFT GROUND
       (09.11.2026: „für jeden Text einzeln … unten rechts trifft Auto zu,
       unten links für das Medium nicht") — under its corners, in the
       fieldset of the bar, the medium or the label (plateparts.js, inkFor,
       softOnCanvas). The soft ground is a tick for all three since
       20.11.2026 — on by default for medium and label, off for the bar
       (softWanted). */
    function writingControls(cell, key) {
        const box = document.createElement('div');
        box.className = 'plate-writing';
        /* ONE LINE (20.11.2026: „aus Schriftfarbe, was wir
           momentan in 3 Zeilen untereinander haben, eine Zeile. Es reicht
           ja auch ein schwarzes und ein weißes Kästchen und ein
           Autokästchen"). The caption, then three squares: „Auto", one
           black, one white — the swatch IS the answer, its word stays in
           the title. Before, three radios stood one per line under the
           caption, because written out side by side they did not fit. */
        const inks = document.createElement('div');
        inks.className = 'plate-ink-row';
        const lead = document.createElement('span');
        lead.textContent = t('Schriftfarbe');
        inks.append(lead);
        const now = cell.ink?.[key] ?? 'auto';
        for (const [ink, words] of [['auto', t('automatisch')], ['black', t('schwarz')], ['white', t('weiß')]]) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'plate-ink is-' + ink;
            b.classList.toggle('is-active', now === ink);
            b.title = words;
            b.setAttribute('aria-label', words);
            b.setAttribute('aria-pressed', String(now === ink));
            if (ink === 'auto') b.textContent = t('Auto');
            b.addEventListener('click', () => {
                const { [key]: _, ...rest } = cell.ink ?? {};
                cell.ink = ink === 'auto' ? rest : { ...rest, [key]: ink };
                render();
            });
            inks.append(b);
        }
        const l = document.createElement('label');
        l.className = 'choice-row';
        const tick = document.createElement('input');
        tick.type = 'checkbox';
        tick.checked = softWanted(cell, key);
        /* Only what differs from the default is stored: the bar's true,
           the medium's or the label's false (plateparts.js, softWanted). */
        tick.addEventListener('change', () => {
            const { [key]: _, ...rest } = cell.soft ?? {};
            const differs = key === 'bar' ? tick.checked : !tick.checked;
            cell.soft = differs ? { ...rest, [key]: tick.checked } : rest;
            render();
        });
        const span = document.createElement('span');
        /* „Untergrund weichzeichnen" (20.11.2026) — it was „Bild
           unter der Schrift weichzeichnen", and on a row's own strip there
           is no picture under it. */
        span.textContent = t('Untergrund weichzeichnen');
        l.append(tick, span);
        box.append(inks, l);
        return box;
    }

    function cornerControl(cell, key, title, start) {
        const set = document.createElement('fieldset');
        set.className = 'field question plate-corner-question';
        const on = (cell[key] ?? 'off') !== 'off';
        const legend = headTick(title, on, (now) => {
            cell[key] = now ? start : 'off';
            render();
        });
        set.on = on;
        set.append(legend);
        /* ITS OPTIONS ONLY WHILE IT IS ON (20.11.2026: „erst
           wenn es aktiv ist, werden die Optionen unten drunter
           eingeblendet"). Until then the corners stood faint under an
           unticked heading, and a corner pressed switched it on there;
           now the tick switches it on in its default corner and the
           corners follow. The callers add their own sub-options only on
           `set.on` as well. */
        if (!on) return set;
        /* THE CORNERS IN A COLUMN OF THEIR OWN AT THE RIGHT EDGE, where the
           panel is wide enough, else last under the options
           (20.11.2026: „die 4 Positionspfeile … wenn genug Platz ist, noch
           irgendwie rechts außen an den Rand. Also eine zweite Spalte …
           und wenn nicht genügend Platz ist, dann als unterster Eintrag").
           The options — the callers' own (set.subs) and the ink — stand
           together on the left; plates.css decides the rest by a container
           query on the body, so the panel's width alone chooses. */
        const body = document.createElement('div');
        body.className = 'plate-corner-body';
        const grid = document.createElement('div');
        grid.className = 'plate-corner-grid';
        const opts = document.createElement('div');
        opts.className = 'plate-corner-options';
        set.subs = document.createElement('div');
        opts.append(set.subs, writingControls(cell, key));
        // In reading order, so the grid of 2 × 2 lays them out as the corners.
        const corners = document.createElement('div');
        corners.className = 'plate-corners';
        for (const [at, iconName, name] of [
            ['tl', 'arrow-up-left', t('oben links')], ['tr', 'arrow-up-right', t('oben rechts')],
            ['bl', 'arrow-down-left', t('unten links')], ['br', 'arrow-down-right', t('unten rechts')],
        ]) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small';
            b.classList.toggle('is-active', cell[key] === at);
            b.title = name;
            b.setAttribute('aria-label', name);
            b.setAttribute('aria-pressed', String(cell[key] === at));
            b.innerHTML = iconHtml(iconName);
            b.addEventListener('click', () => { cell[key] = at; render(); });
            corners.append(b);
        }
        grid.append(opts, corners);
        body.append(grid);
        set.append(body);
        return set;
    }

    /**
     * THE TICK STANDS IN THE HEADING (20.11.2026: „Rahmen, ja,
     * nein, Label, Label anzeigen, Maßstab, Maßstab anzeigen … Das ist
     * einfach doppelt gemoppelt. Ich würde hinter jeder Überschrift einfach
     * nur eine Möglichkeit setzen, diese anzuhaken, und damit wird sie
     * angezeigt"). Wherever a question in the panel is only on or off, the
     * heading carries the switch IN FRONT of its words (the same
     * day: „vorne dran, nicht hinten dran" — first it stood behind them,
     * where it wandered with the word's length), and no line of
     * answers follows — before, a heading „Medium" stood over a line
     * „Medium zeigen: H₂O", and „Rahmen" over „ja" and „nein". What the
     * medium or the label will read is not repeated here either: the plate
     * shows it. The whole heading is the tick's label, so a click on the
     * word switches as well.
     */
    function headTick(text, on, onSet) {
        const legend = document.createElement('legend');
        const l = document.createElement('label');
        l.className = 'plate-head-tick';
        const words = document.createElement('span');
        words.textContent = text;
        const tick = document.createElement('input');
        tick.type = 'checkbox';
        tick.checked = on;
        tick.addEventListener('change', () => onSet(tick.checked));
        l.append(tick, words);
        legend.append(l);
        return legend;
    }

    /** An on-or-off question: its heading and the tick behind it, nothing else (headTick). */
    function switchQuestion(text, on, onSet) {
        const set = document.createElement('fieldset');
        set.className = 'field question';
        set.append(headTick(text, on, (now) => { onSet(now); render(); }));
        return set;
    }

    /** A chosen inset's panel: what it holds and how it is handled, and
     *  — handed back for the caller to place last — its own few tools: the
     *  viewer, taking it off. Bar and medium are corner questions above them. */
    function insetPanel(item) {
        const inner = item.cell;
        const title = document.createElement('h3');
        title.className = 'plate-side-title';
        title.textContent = inner.pic ? t('Einsatzbild') : t('Einsatz: %s', blockName(inner.block));
        const hint = document.createElement('p');
        hint.className = 'hint-text small';
        hint.textContent = t('Ziehen verschiebt es. Die Punkte an den Ecken und Seiten ändern die Größe. Das rote Kreuz oben rechts entfernt es. Der Rahmen hat die Farbe und Stärke der Linien zwischen den Bildern.');
        const tools = document.createElement('div');
        tools.className = 'button-row';
        const add = (iconName, words, fn) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'button subtle small';
            b.innerHTML = iconHtml(iconName);
            b.append(words);
            b.addEventListener('click', fn);
            tools.append(b);
            return b;
        };
        if (inner.pic) add('photo', t('Im Bildbetrachter öffnen'), () => viewPicture(inner.pic));
        selBox.append(title, hint);
        /* THE FRAME CAN GO (09.11.2026: „bekommt jetzt
           automatisch einen schwarzen Rahmen. Das würde ich noch als
           Einstellung ausschalten wollen"). Kept by default — an inset
           without one can melt into the picture beneath. */
        selBox.append(switchQuestion(t('Rahmen'), item.frame !== false, on => { item.frame = on; handTake('inset', item); }));
        return tools;
    }

    /**
     * The air around each spore of a row, as the photo window's slider
     * (100–300 %): it starts at the photo window's value for the layer on
     * this find (styleNow) and moves this plate's alone; „Wie im
     * Bildbetrachter" gives it back.
     */
    function airControl(cell, d) {
        const box = document.createElement('div');
        box.className = 'field question';
        const cropRow = typeof d.crop === 'string';
        /* „RAND UM MARKIERUNG" (09.11.2026): the margin is added
           around what was marked in the photo window — a measured rectangle
           or a cutout —, not around the frame it ends up in. „Spore"
           stopped fitting the day a row lined up chrysocystidia; „Bild"
           and „Ausschnitt" named the frame, which is what the margin MAKES.
           „GRÖSSE" SINCE 20.11.2026 („den Schieberegler Rand um
           Markierung … einfach Größe nennen") — what one sees move on the
           plate is how large the mark stands in its frame. The heading and
           its slider share one line, as every short question here. */
        box.className = 'field question plate-inline';
        box.innerHTML = `<p class="plate-question">${esc(t('Größe'))}</p>`;
        const line = document.createElement('div');
        line.className = 'plate-slider';
        const slider = document.createElement('input');
        slider.type = 'range';
        slider.min = '100';
        slider.max = '300';
        slider.step = '1';
        slider.value = String(Math.round(rowLook(d, styleNow()).air * 100));
        /* THE MARGIN IS SAID AS WHAT IS ADDED (08.11.2026: „bei
           100 % ist der Rand 0 % groß. Und bei 104 % ist der Rand 4 %
           größer"): the stored value stays the frame's share of the
           structure (100–300, as the photo window keeps it), the reading
           takes the hundred off — 0 % no margin, 4 % a margin of 4 %. */
        const say = () => `${Number(slider.value) - 100} %`;
        const shown = document.createElement('output');
        shown.textContent = say();
        line.append(slider, shown);
        const back = document.createElement('button');
        back.type = 'button';
        back.className = 'button subtle small';
        // A cutout starts without margin; a spore at the photo window's.
        back.textContent = cropRow ? t('Ohne Rand') : d.count === true ? t('Zurücksetzen') : t('Wie im Bildbetrachter');
        back.hidden = d.air == null;
        slider.addEventListener('input', () => {
            d.air = Number(slider.value);
            shown.textContent = say();
            back.hidden = false;
            const el = [...pageEl.querySelectorAll('.plate-cell')].find(e => e.cell === cell);
            if (el) relayoutSoon(el, ctxNow());
            // A preset's row is drawn in the strip.
            if (!cell && chosenDraft !== null) stripTileSync(chosenDraft);
            saveSync();
        });
        back.addEventListener('click', () => { d.air = null; render(); });
        box.append(line, back);
        return box;
    }

    /**
     * HOW MUCH OF THE PICTURE SHOWS THROUGH THE GROUND
     * (08.11.2026: „eine entsprechende Transparenz für den weißen oder
     * schwarzen Hintergrund … mit einem Schieberegler … und einem Wert, dass
     * man das bei anderen Bildern wiederholen kann"). 100 % is the paper as
     * it was, the slider goes down to 10 % in steps of five — round numbers
     * one can set again on the next plate, and the hand remembers the last
     * anyway. Only for words lying ON a picture: a tile is paper, there is
     * nothing behind it to show. The words keep their full ink.
     */
    function opacityControl(cell, d) {
        // In the background's group (choiceLine): a quiet caption in the slider's line, no heading of its own.
        const line = document.createElement('div');
        line.className = 'plate-slider plate-opacity';
        const caption = document.createElement('span');
        caption.textContent = t('Deckkraft');
        line.append(caption);
        const slider = document.createElement('input');
        slider.type = 'range';
        slider.min = '10';
        slider.max = '100';
        slider.step = '5';
        slider.value = String(d.groundOpacity ?? 100);
        slider.setAttribute('aria-label', t('Deckkraft des Hintergrunds'));
        const shown = document.createElement('output');
        shown.textContent = `${slider.value} %`;
        line.append(slider, shown);
        slider.addEventListener('input', () => {
            d.groundOpacity = Number(slider.value);
            shown.textContent = `${slider.value} %`;
            const el = cell && [...pageEl.querySelectorAll('.plate-cell')].find(e => e.cell === cell);
            if (el) relayoutSoon(el, ctxNow());
            if (!cell && chosenDraft !== null) stripTileSync(chosenDraft);
            saveSync();
        });
        return line;
    }

    /**
     * A QUESTION OF WHITE OR BLACK IN ONE LINE (20.11.2026, after
     * the ink: „Hintergrund weiß, schwarz. Auch ein Einzeiler draus machen.
     * Mit einem weißen und einem schwarzen Kästchen zum Wählen"). The
     * heading in its own weight, then a square per answer — the squares of
     * the ink (.plate-ink, writingControls), the colour itself the answer,
     * its word in the title. `answers` are [value, words, swatch], the
     * swatch 'white', 'black' or 'none' (crossed out).
     *
     * WORDS TOO (the same day: „Sortiere nach Länge, Breite, Q,
     * ein Einzeiler daraus. Linien zwischen den Ausschnitten … Rand außen
     * herum … ein Einzeiler"): an answer without a swatch is a small button
     * with its word in it, in the same line. The answers keep together: where
     * heading and answers do not fit the panel's width, the answers go under
     * the heading as one line — never „Länge Breite" beside it and „Q" alone
     * below.
     *
     * THE BACKGROUND AND ITS OPACITY ARE ONE GROUP (the same
     * day: „den Hintergrund und die Deckkraft immer optisch als eine Gruppe
     * zusammenfassen"): `extra`, the opacity's slider where there is one,
     * stands inside the group, right under the squares, instead of as a
     * question of its own with a heading of its own.
     *
     * EVERY CHOICE OF THE PANEL SINCE (the same day: „Ich will
     * einfach Platz sparen. Vieles geht als Einzeiler. Das Design ebenso
     * anpassen. Auch bei den Stellen, die ich jetzt noch nicht genannt
     * habe"): the old question() with one radio per line is gone. The
     * general tab's two choices (plate_window.php) are radios dressed the
     * same way, because their wiring reads the radios.
     */
    function choiceLine(text, answers, value, onPick, extra = null) {
        const box = document.createElement('div');
        box.className = 'field question plate-swatch-group';
        box.setAttribute('role', 'group');
        box.setAttribute('aria-label', text);
        const line = document.createElement('div');
        line.className = 'plate-swatch-question';
        const words = document.createElement('span');
        words.className = 'plate-question';
        words.textContent = text;
        const choices = document.createElement('span');
        choices.className = 'plate-choices';
        line.append(words, choices);
        box.append(line);
        for (const [v, name, swatch] of answers) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = swatch ? 'plate-ink is-' + swatch : 'plate-ink plate-word';
            b.classList.toggle('is-active', v === value);
            if (swatch) {
                b.title = name;
                b.setAttribute('aria-label', name);
            } else {
                b.textContent = name;
            }
            b.setAttribute('aria-pressed', String(v === value));
            b.addEventListener('click', () => { onPick(v); render(); });
            choices.append(b);
        }
        if (extra) box.append(extra);
        return box;
    }

    /**
     * THE FIND'S FACE (12.10.2026): the first plate's first page,
     * drawn into el at its width — in the Fundbrowser beside the texts, so
     * one sees the find while writing about it. False where the find has no
     * plate yet; the page then shows its Hauptmotiv instead. A click leads
     * into the plate, as a tile of the row does.
     */
    let faceAt = null;   // the face last drawn: {el, width, p}
    function face(el, width, p = plates[0]) {
        el.textContent = '';
        if (!p?.layout.pages?.[0]) return false;
        const pictures = picturesOf(pointId);
        miniPage(el, p, width, new Map(pictures.map(x => [x.key, x])), host.details(pointId));
        /* A page missing or old is drawn and laid down now, and the face
           shows it (imagesEnsure) — the face AS IT STANDS THEN: the
           Fundbrowser draws its overview more than once while it loads,
           and the element of the first call is gone by the time the page
           is laid. */
        faceAt = { el, width, p };
        imagesEnsure(p).then((laid) => {
            if (!laid || !faceAt?.el.isConnected || faceAt.p.id !== p.id) return;
            // The face's record may be one fetched before the page was laid: it learns the page first.
            const q = faceAt.p;
            q.images = imagesOf(q);
            face(faceAt.el, faceAt.width, q);
        });
        /* Into the plate — where the host has a place for it (the Fundbrowser's
           tab), there. On the box and not the page: .plate-mini takes no
           pointer (plates.css), it is a picture of a page. */
        el.onclick = () => (host.showPlate ? host.showPlate(p) : openPlate(p));
        return true;
    }

    /**
     * What the viewer may have changed since the page was drawn — a
     * measurement, a name, a scale —, read again: in the Fundbrowser the
     * viewer is another tab and never closes over the plate, so this is
     * asked when the tab „Tafeln" comes back into sight (viewPicture does
     * the same when the viewer closes over it).
     */
    function refresh() {
        if (!win.open) return;
        pics = new Map(picturesOf(pointId).map(x => [x.key, x]));
        values = host.details(pointId);
        stripDraw();
        render();
        imagesSoon();
    }

    return {
        show, face, openPlate, refresh,
        /** Close the open plate, saving it; false when the user keeps it open over a failed save. */
        close,
        /** The find's plates as the server gave them. */
        list: () => plates,
        /** The plate open in the editor ({id: null} for a new one not yet saved), or null. */
        current: () => (win.open ? plate : null),
        redraw: () => { if (pointId) drawRow(); },
    };
}

