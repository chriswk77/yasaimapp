/**
 * THE PLATE'S ARITHMETIC — what the editor on the screen (plates.js) and
 * the page for the printer (plateprint.js) both draw from.
 *
 * ONE COPY FOR BOTH, on purpose: the printed page has to be the page one
 * laid out. A cutout, a scale bar or a line of details computed twice
 * would drift apart the first time one side is touched, and the drift
 * would show only on paper. So the pieces live here, and each side only
 * says WHERE it draws — into the DOM's pixels, or into a canvas at 300 dpi.
 * Everything takes millimetres of A4 through `mm` (pixels per millimetre).
 */

import { t } from './i18n.js';
import { mediaLabel } from './media.js';
import { iconHtml } from './icons.js';
import { developEmpty, developRenderer, geoSet, geoView, aiPatchesFor } from './develop.js';
import {
    measureLabel, measurePart, measureRows, rowsMarked, valueLines, spreadText, plotSvg, sporeDraw, sporeOrder, sporeAir, lengthStraight,
    partRows, partLines, curveSvg, lineDraw, partAir, partDirection, cropNames, cropRows, countRows, countLines, countDraw,
} from './measurestats.js';

/* THE DETAILS A FIELD CAN SHOW, in the order they stand — the twin of
   PLATE_DETAILS in includes/plates.php, which drops any key it does not
   know. A function, so the labels are translated when they are read.
   A third word is what the plate prints where the tick's own would say
   too much: the place with land and state is still „Fundort" there
   (08.10.2026 — „mit dem Land zwei Buchstaben, dem Bundesland
   drei Buchstaben und danach die Gemeinde"). */
export function detailLabels() {
    return [
        ['species', t('Art')],
        ['number', t('Fundnummer')],
        ['date', t('Funddatum')],
        ['place', t('Fundort')],
        ['place_full', t('Fundort mit Land und Bundesland'), t('Fundort')],
        ['mtb', t('MTB')],
        ['gps', t('GPS')],
        ['elevation', t('Höhe')],
        ['finder', t('Finder')],
        ['determiner', t('Bestimmer')],
        ['certainty', t('Bestimmungssicherheit')],
        ['amount', t('Anzahl / Menge')],
        ['substrate', t('Substrat / Wirt')],
        ['vegetation', t('Begleitvegetation')],
        ['odour', t('Geruch')],
        ['taste', t('Geschmack')],
        ['spore_print', t('Sporenpulverfarbe'), t('Sporenpulver')],
        ['voucher', t('Beleg-Nr.')],
        ['remark', t('Bemerkung')],
        // The find's own note (08.11.2026: „bei Fundangaben noch die Notiz dazu").
        ['note', t('Notiz')],
    ];
}

/* A SIGN FOR EACH DETAIL, the third answer to „Mit Bezeichnung davor"
   (09.11.2026: „Symbole. Wir haben wunderbare SVG-Symbole für
   all das. Die könnten wir dort auch einfach davor setzen"). The ones the
   Fundbrowser's head already uses for the same facts (finds.js,
   FACT_SIGNS: #, calendar, place, mountain, map, person), the rest from
   the same library. The species has none, as it has no label: its name
   is the heading. */
export const DETAIL_ICONS = {
    number: 'hash', date: 'calendar', place: 'place', place_full: 'place', mtb: 'map', gps: 'crosshair',
    elevation: 'mountain', finder: 'person', determiner: 'key', certainty: 'check', amount: 'bars',
    substrate: 'stack', vegetation: 'tree', voucher: 'label', remark: 'speech-bubble', note: 'sticky-note',
    odour: 'scent', taste: 'drop', spore_print: 'spore',
};

/** A Leerzeile among the details (detailsRows) — its key in d.fields, and the air it gives, in lines. */
export const DETAIL_GAP = 'gap';
export const GAP_LINES = .5;
/** …and in one line, the em spaces each Leerzeile adds between neighbours. */
export const GAP_EM = 1;

export const PT_MM = 25.4 / 72;
/** The size of the writing on the plate's pictures, in points: the plate's own, else the account's text size. */
export const notePtOf = (layout, style) => layout?.notePt ?? style?.plate?.text_pt ?? 10;
// The round lengths a bar may take, in µm (Reul's plates use 20 to 500).
export const NICE_UM = [1, 2, 5, 10, 20, 30, 50, 100, 200, 300, 500, 1000, 2000, 5000, 10000, 20000, 50000, 100000];
// The bar in millimetres of the page: distance to the cell's edge, line, label.
const BAR_EDGE = 2.6, BAR_THICK = .7, BAR_FONT = 3, BAR_GAP = .6;
/* UP AND DOWN CLOSER THAN SIDEWAYS (20.11.2026: „die
   horizontale Position gut. Die vertikale Position würde ich etwas näher
   an den Bildrand schieben. Da ist oft noch zu viel Platz"). Bar, medium
   and label keep 2.6 mm from the side and stand 1.5 mm from the top or
   the bottom; a row's own strip for the bar shrinks with it. */
const BAR_EDGE_Y = 1.5;
/** The bar's, the medium's and the label's distance from the cell's edge, in mm — what moved things snap to (plates.js, snapMove). */
export const EDGE_MM = BAR_EDGE;
// Line heights of the page's type, as multiples of the size.
export const LINE_ITEM = 1.25, LINE_TILE = 1.3;
// A box with a ground keeps this much paper around its words (mm).
export const BOX_PAD_X = 1.4, BOX_PAD_Y = 1, TILE_PAD = 3;
export const PAGE_FONT = 'Arial, Helvetica, sans-serif';

export const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
export const esc = (s) => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

/* --- Pictures: loading, shape, ink ---------------------------------------- */
const loaded = new Map();   // src -> {img, ready, failed, w, h, g, k}

/** A picture by its address, loaded once for the whole page's life. */
export function load(src, onReady) {
    let e = loaded.get(src);
    if (!e) {
        e = { img: new Image(), ready: false, failed: false, waiters: [] };
        e.img.decoding = 'async';
        e.img.onload = () => {
            Object.assign(e, { ready: true, w: e.img.naturalWidth, h: e.img.naturalHeight });
            for (const waiter of e.waiters.splice(0)) waiter();
        };
        e.img.onerror = () => {
            e.failed = true;
            for (const waiter of e.waiters.splice(0)) waiter();
        };
        e.img.src = src;
        loaded.set(src, e);
    }
    if (!e.ready && !e.failed && onReady) e.waiters.push(onReady);
    return e;
}

/* THE PICTURE AS IT IS DEVELOPED (07.11.2026, after the
   viewer's „Bearbeiten": „mach jetzt die Tafeln"). A picture with settings
   (pic.develop, migration 0190) is rendered once through the viewer's own
   renderer (develop.js) and stands on the plate as an ordinary <img> from
   then on — the screen, the printed page, the PDF and the plate's JPEG
   all draw it, measure its ink and cut it as they did the original. One
   renderer for the page, one WebGL context; the result kept per picture
   AND settings, so a change in the viewer is a new entry and the old one
   simply goes unused. Where WebGL is missing, the original. */
let developer;   // {r, c} — undefined: not tried yet, null: no WebGL
function developerGet() {
    if (developer === undefined) {
        const c = document.createElement('canvas');
        const r = developRenderer(c);
        developer = r ? { r, c } : null;
    }
    return developer;
}

const unfilled = new WeakSet();   // developed images that lack a patch — kept by no store

/**
 * A loaded picture with settings laid over it, as a new <img> — null where
 * it cannot be rendered. `of` is the picture's key ('photo:12',
 * 'cloud:34'): the patches of its removed places are fetched first
 * (develop.js, aiPatchesFor); one rendered without them all is marked in
 * `unfilled`, so no store keeps it.
 */
export async function developImage(img, develop, of = null) {
    const complete = await aiPatchesFor(develop, of);
    const out = await new Promise((resolve) => {
        const d = developerGet();
        if (!d || !d.r.setImage(img)) return resolve(null);
        d.r.render(develop);
        // toBlob takes the canvas as it is now: the next picture may render the moment this returns.
        d.c.toBlob((blob) => {
            if (!blob) return resolve(null);
            const out = new Image();
            out.decoding = 'async';
            out.onload = () => resolve(out);
            out.onerror = () => resolve(null);
            out.src = URL.createObjectURL(blob);
        }, 'image/jpeg', .94);
    });
    if (out && !complete) unfilled.add(out);
    return out;
}

const developedStore = new Map();   // src + settings -> {img, ready, failed, w, h, waiters}

/**
 * A PICTURE RECORD AS ITS VIEW (stage 2, 07.11.2026): turned and cut as its
 * development says, the record speaks in the VIEW's pixels from here on —
 * its size, its measures' points (and a text's or box's turn), its scale's
 * reference width — so every part of the plate that places, scales and
 * labels a picture works on as before, on the picture one sees. The
 * picture's own pixels come turned and cut from loadFor() (develop.js
 * renders the view). Untouched where it is neither turned nor cut, or its
 * size is not known.
 */
export function picView(pic) {
    if (!geoSet(pic?.develop) || !(pic.w > 0 && pic.h > 0)) return pic;
    const W = pic.w, H = pic.h;
    const view = geoView(pic.develop, W, H);
    const measures = (pic.measures ?? []).map((m) => {
        const width = m.width || W;
        const f = W / width;
        const out = { ...m, width: view.cw };
        if (Array.isArray(m.points)) out.points = m.points.map(([x, y]) => view.toView([x * f, y * f]));
        /* A SECTOR'S COUNTED POINTS stand in the picture's pixels like its
           taps and go along; its slice lives in the cap's own angles, which
           a mirror reads the other way round (measurestats.js, sectorShape:
           the short axis turns to the other side). */
        if (m.type === 'sector') {
            for (const key of ['count', 'lamellulae']) {
                if (Array.isArray(m[key])) out[key] = m[key].map(([x, y]) => view.toView([x * f, y * f]));
            }
            if (Array.isArray(m.arc) && view.fx * view.fy < 0) out.arc = m.arc.map((v) => -v);
        }
        if (m.size != null) out.size = m.size * f;
        /* A mirrored picture turns a word's angle backwards: the plate
           writes it readable at the place the mirror puts it, as the photo
           window does (photowindow.js, viewUnmirror; 09.11.2026). */
        if (m.angle != null || m.type === 'text' || m.type === 'box') out.angle = (m.angle ?? 0) * view.fx * view.fy + view.R;
        viewOrigins.set(out, { m, view, f });
        return out;
    });
    const sc = pic.scale;
    const scale = sc ? { ...sc, width: (sc.width ?? W) * view.cw / W } : sc;
    /* fullW: THE WHOLE PICTURE'S WIDTH in the view's units
       (15.11.2026: on a cut picture the plate's arrow stood thin beside its
       big words, in the viewer it was thick). An arrow's strength is
       thousandths of the WHOLE picture's width — the viewer draws in the
       original's pixels — and the view is only the cut; reckoned against
       the cut's width, the arrow shrank by the share cut away while its
       words, sized in pixels, kept theirs (plateannot.js, noteShapes). */
    const out = { ...pic, w: Math.round(view.cw), h: Math.round(view.ch), measures, scale, fullW: W };
    viewPictures.set(out, pic);
    return out;
}

/* THE WAY BACK, for writing changed on a plate (plates.js, noteDrag —
   moved since 30.10.2026, shaped since 15.11.2026). A cut picture's view
   carries COPIES of its measures, in the view's pixels; what the plate
   changes there has to reach the picture's own measure, in its own
   pixels, or the save would store the view's numbers and the arrow would
   stand elsewhere in the viewer. Keyed weakly: a view drawn anew makes new
   copies, the old ones go with it. */
const viewOrigins = new WeakMap();   // view's measure -> {m, view, f}
const viewPictures = new WeakMap();  // view's picture -> the picture record

/** The picture record behind a plate's picture — itself where it was not cut. */
export function pictureOf(pic) {
    return viewPictures.get(pic) ?? pic;
}

/** A view's measure written back into the picture's own — places, size,
 *  strength. Nothing to do where the picture was not cut (it IS its own). */
export function measureBack(out) {
    const origin = viewOrigins.get(out);
    if (!origin) return;
    const { m, view, f } = origin;
    if (Array.isArray(out.points)) {
        m.points = out.points.map((p) => view.toNat(p)).map(([x, y]) => [Math.round(x / f * 10) / 10, Math.round(y / f * 10) / 10]);
    }
    if (out.size != null) m.size = Math.round(out.size / f * 10) / 10;
    if (out.weight != null) m.weight = out.weight;
}

/** load() for a picture record — its development laid over it where it has one. */
export function loadFor(pic, src, onReady) {
    if (developEmpty(pic?.develop)) return load(src, onReady);
    const key = src + '#' + JSON.stringify(pic.develop);
    let e = developedStore.get(key);
    if (!e) {
        e = { img: null, ready: false, failed: false, waiters: [] };
        developedStore.set(key, e);
        const done = () => { for (const waiter of e.waiters.splice(0)) waiter(); };
        const make = async () => {
            const base = load(src);
            if (base.failed) {
                e.failed = true;
                return done();
            }
            const out = await developImage(base.img, pic.develop, pic.key);
            Object.assign(e, out
                ? { img: out, ready: true, w: out.naturalWidth, h: out.naturalHeight }
                : { img: base.img, ready: true, w: base.w, h: base.h });
            // A removed place whose patch did not come: shown so for now, not kept — the next drawing asks again.
            if (out && unfilled.has(out)) developedStore.delete(key);
            done();
        };
        const base = load(src, make);
        if (base.ready || base.failed) make();
    }
    if (!e.ready && !e.failed && onReady) e.waiters.push(onReady);
    return e;
}

/** loadFor(), awaited. */
export function loadForDone(pic, src) {
    return new Promise((resolve) => {
        const e = loadFor(pic, src, () => resolve(e));
        if (e.ready || e.failed) resolve(e);
    });
}

/** The same, awaited — for the printed page, which draws once it is all there. */
export function loadDone(src) {
    return new Promise((resolve) => {
        const e = load(src, () => resolve(e));
        if (e.ready || e.failed) resolve(e);
    });
}

/** Width over height — from the stored size, else from the loaded file. */
export function aspectOf(pic, e) {
    if (pic.w > 0 && pic.h > 0) return pic.w / pic.h;
    if (e?.ready && e.h > 0) return e.w / e.h;
    return 4 / 3;
}

/**
 * Black or white for a spot of a picture — whichever stands out more.
 * The mean RELATIVE luminance decides (above 0.179 black has the larger
 * contrast): the draft first averaged plain brightness against 150, and a
 * bar over a light ground with one dark spore beside it came out white.
 * A picture that cannot be read (a foreign origin taints the canvas)
 * gets white, the way Reul wrote on most micrographs' dark corners.
 * Coordinates are pixels of the loaded file.
 */
export function inkAt(e, x0, y0, x1, y1) {
    if (!e?.ready) return '#fff';
    try {
        if (!e.g) {
            const c = document.createElement('canvas');
            c.width = 64;
            c.height = Math.max(1, Math.round(64 * e.h / e.w));
            e.g = c.getContext('2d', { willReadFrequently: true });
            e.g.drawImage(e.img, 0, 0, c.width, c.height);
            e.k = c.width / e.w;
            e.cw = c.width; e.ch = c.height;
        }
        const a = clamp(Math.floor(Math.min(x0, x1) * e.k), 0, e.cw - 1);
        const b = clamp(Math.floor(Math.min(y0, y1) * e.k), 0, e.ch - 1);
        const w = clamp(Math.ceil(Math.abs(x1 - x0) * e.k), 1, e.cw - a);
        const h = clamp(Math.ceil(Math.abs(y1 - y0) * e.k), 1, e.ch - b);
        return inkOf(e.g.getImageData(a, b, w, h).data);
    } catch {
        return '#fff';
    }
}
/** Black or white for pixels already read — inkAt's measure. */
function inkOf(d) {
    const lin = v => ((v /= 255) <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
    let sum = 0;
    for (let i = 0; i < d.length; i += 4) sum += .2126 * lin(d[i]) + .7152 * lin(d[i + 1]) + .0722 * lin(d[i + 2]);
    return sum / (d.length / 4) > .179 ? '#000' : '#fff';
}

/**
 * inkAt for a spot of a canvas already drawn — where the bar lies over a
 * row's frames, which are many pictures cut and scaled into one canvas.
 * Coordinates in the context's current space (its transform is undone
 * here, so a translated or dpr-scaled context reads the right pixels).
 */
export function inkOnCanvas(ctx, x0, y0, x1, y1) {
    try {
        const m = ctx.getTransform();
        const c = ctx.canvas;
        const a = clamp(Math.floor(Math.min(x0, x1) * m.a + m.e), 0, c.width - 1);
        const b = clamp(Math.floor(Math.min(y0, y1) * m.d + m.f), 0, c.height - 1);
        const w = clamp(Math.ceil(Math.abs(x1 - x0) * m.a), 1, c.width - a);
        const h = clamp(Math.ceil(Math.abs(y1 - y0) * m.d), 1, c.height - b);
        return inkOf(ctx.getImageData(a, b, w, h).data);
    } catch {
        return '#fff';
    }
}

/**
 * µm per DRAWN pixel of a picture drawn `pw` pixels wide, or null
 * without a calibration. The calibration line was drawn on an image
 * scale.width pixels wide (migration 0090; absent on the first day's,
 * which were drawn on the stored size) — the same chain as photoUmPerPx
 * in photowindow.js, only towards the plate's pixels.
 */
export function umPerShownPx(pic, pw, e) {
    const sc = pic.scale;
    if (!sc?.line || !(sc.um > 0) || !(pw > 0)) return null;
    const [a, b] = sc.line;
    const len = Math.hypot(b[0] - a[0], b[1] - a[1]);
    const ref = sc.width ?? (pic.w > 0 ? pic.w : e?.w);
    if (!len || !ref) return null;
    return sc.um / len * ref / pw;
}

/** The cutout's geometry in a cell W × H — never written back while drawing. */
export function cutout(cell, W, H, aspect) {
    const pw = Math.max(W, H * aspect) * cell.z;
    const ph = pw / aspect;
    const cx = clamp(cell.cx, W / (2 * pw), 1 - W / (2 * pw));
    const cy = clamp(cell.cy, H / (2 * ph), 1 - H / (2 * ph));
    return { W, H, pw, ph, cx, cy, left: W / 2 - cx * pw, top: H / 2 - cy * ph };
}

/**
 * THE BAR: a round length near a fifth of the cell's width, in the
 * corner the cell asks for. Null where there is none to draw.
 * @returns {?{x, y, len, label, font, thick, gap, boxH}} cell pixels
 */
export function barGeometry(cell, W, H, mm, umPx, share = .2) {
    if (umPx === null || cell.bar === 'off') return null;
    const want = share * W * umPx;
    const um = NICE_UM.filter(n => n <= want).pop() ?? NICE_UM[0];
    const len = um / umPx;
    const font = BAR_FONT * mm, thick = Math.max(1, BAR_THICK * mm), gap = BAR_GAP * mm;
    const boxH = font * 1.15 + gap + thick, edge = BAR_EDGE * mm, edgeY = BAR_EDGE_Y * mm;
    return {
        x: cell.bar.endsWith('r') ? W - edge - len : edge,
        y: cell.bar.startsWith('b') ? H - edgeY - boxH : edgeY,
        len, font, thick, gap, boxH,
        label: um >= 1000 ? `${um / 1000} mm` : `${um} µm`,
    };
}

/**
 * THE MEDIUM ON THE PICTURE (06.11.2026: „das Medium dann
 * nachträglich einblenden … ähnlich wie den Maßstab in irgendeinem Eck",
 * „mit dem Kürzel"): the short labels of the media the picture lay in
 * („KOH 3 % + KR H₂O", media.js, mediaLabel), in the corner the cell asks
 * for (cell.med, off until asked) — in the bar's letters and the bar's ink.
 * Sharing the bar's corner it stands beside the bar's edge, above or below
 * it, never over it. Null where there is none to draw.
 * @returns {?{x, y, w, h, font, label}} cell pixels, x/y the top left
 */
export function mediaGeometry(cell, W, H, mm, label, bar) {
    if (!label || !cell.med || cell.med === 'off') return null;
    const font = BAR_FONT * mm, edge = BAR_EDGE * mm, edgeY = BAR_EDGE_Y * mm, h = font * 1.1;
    const w = mediaCanvas().measureText(label).width * font / 100;
    const bottom = cell.med.startsWith('b');
    let y = bottom ? H - edgeY - h : edgeY;
    /* BEYOND THE BAR AS IT STANDS, not as a picture's would: a row's bar
       over its pictures lies in a frame, a little in from the cell's edge
       (rowBarOver). On a picture both come to the same place. */
    if (bar && cell.bar === cell.med) y = bottom ? bar.y - font * .5 - h : bar.y + bar.boxH + font * .5;
    return { x: cell.med.endsWith('r') ? W - edge - w : edge, y, w, h, font, label };
}
/**
 * THE PICTURE'S LABEL ON THE PICTURE (08.11.2026: „eine dritte
 * Option … das Label. Also zum Beispiel Sporen oder Chalocystiden etc. Bei
 * den Cystiden haben wir ja sogar noch eine Unteroption … All das könnte
 * man mit anzeigen, dann braucht man das nicht als Text einfügen"). Its
 * name (the Motivname), and behind it in brackets the marks the cell
 * ticked — the kinds its measures were given in the photo window
 * („Chrysozystide"), where the picture holds any. Empty where the
 * picture has no name.
 */
export function pictureLabel(pic, cell) {
    const name = String(pic?.title ?? '').trim();
    if (!name) return '';
    const marks = pictureMarks(pic).filter(mark => (cell.labelMarks ?? []).includes(mark));
    return marks.length ? `${name} (${marks.join(', ')})` : name;
}

/** The marks the picture's measures carry, in the order they were first given. */
export function pictureMarks(pic) {
    return [...new Set((pic?.measures ?? []).map(m => (typeof m.mark === 'string' ? m.mark.trim() : '')).filter(Boolean))];
}

/**
 * A ROW'S MEDIUM AND LABEL (09.11.2026: „bei einer Reihe …
 * auch Medium anzeigen lassen und das Label und wenn es eine Messung
 * ist, die noch ein weiteres Unterlabel hat, wie zum Beispiel
 * Chrysozystide, oder Ornament"). A row gathers measures from many
 * pictures, so its medium is THEIRS: one label where they agree, joined
 * where they lay in different ones — a row must not claim KOH for a
 * spore that was measured in water. Its label is the layer's name — or a
 * cutout row's own —, and behind it in brackets what the row narrows to:
 * the part it shows (Ornament), or the marks of its measures
 * (Chrysozystide), each a tick of its own, as on a picture.
 */
export function rowMediaLabel(rows, full) {
    return [...new Set(rows.map(r => mediaLabel(r.obj?.media, full)).filter(Boolean))].join(' / ');
}
/** What may stand in brackets behind a row's name: its part, or its rows' marks. */
export function rowLabelSubs(d, rows) {
    if (typeof d.crop === 'string') return [];
    if (typeof d.part === 'string' && d.part !== '') return [d.part];
    return [...new Set(rows.map(r => (typeof r.mark === 'string' ? r.mark.trim() : '')).filter(Boolean))];
}
/** The row's name as the cell shows it, with the ticked subs behind it. */
export function rowLabel(d, rows, cell) {
    const name = typeof d.crop === 'string' ? blockName(d) : String(d.layer ?? '').trim();
    if (!name) return '';
    const subs = rowLabelSubs(d, rows).filter(sub => (cell.labelMarks ?? []).includes(sub));
    return subs.length ? `${name} (${subs.join(', ')})` : name;
}

/**
 * Where the label stands: in its corner (cell.label), in the bar's
 * letters like the medium. Sharing a corner, the three stack away from
 * the edge — the bar at the edge, the medium beside it, the label beyond
 * both — so none lies over another.
 * @returns {?{x, y, w, h, font, label}} cell pixels, as mediaGeometry's
 */
export function labelGeometry(cell, W, H, mm, label, bar, med) {
    if (!label || !cell.label || cell.label === 'off') return null;
    const font = BAR_FONT * mm, edge = BAR_EDGE * mm, edgeY = BAR_EDGE_Y * mm, h = font * 1.1;
    const w = mediaCanvas().measureText(label).width * font / 100;
    const bottom = cell.label.startsWith('b');
    let y = bottom ? H - edgeY - h : edgeY;
    // Beyond what shares the corner, the medium being already beyond the bar.
    if (bar && cell.bar === cell.label) y = bottom ? bar.y - font * .5 - h : bar.y + bar.boxH + font * .5;
    if (med && cell.med === cell.label) y = bottom ? med.y - font * .25 - h : med.y + med.h + font * .25;
    return { x: cell.label.endsWith('r') ? W - edge - w : edge, y, w, h, font, label };
}
// Measured at 100 px and scaled — one context, the page's letters.
let mediaMeasure = null;
function mediaCanvas() {
    if (!mediaMeasure) {
        mediaMeasure = document.createElement('canvas').getContext('2d');
        mediaMeasure.font = `400 100px ${PAGE_FONT}`;
    }
    return mediaMeasure;
}

/**
 * The details' lines, from the find's values: rows of runs, each run
 * {s, bold, italic}. A detail the find does not carry is left out, not
 * printed as an empty „Finder:".
 */
export function detailsRows(d, values) {
    /* IN ONE LINE THE WEIGHT TURNS ROUND (06.10.2026: „die
       Bezeichner an sich normal und die Werte fett … jedenfalls, wenn wir
       das Ganze in einer Zeile darstellen"): in a long line the eye looks
       for the values, and bold labels between them read as a list of
       headings. Under one another the bold label still heads its line. */
    const oneLine = d.flow === 'one';
    const rows = [];
    /* IN THE ORDER THE FIELD KEEPS (09.11.2026: „dass man die
       Reihenfolge sortieren kann … per Drag and Drop"): d.fields is the
       order, set by dragging in the panel — no longer the list's. */
    const named = new Map(detailLabels().map(([key, label, printed = label]) => [key, printed]));
    /* THE LEERZEILE IS THE PERSON'S OWN (09.11.2026: „so etwas
       wie eine Leerzeile … die man schieben kann … hinzufügen kann, um
       genau so etwas zu gestalten. Dann bräuchtest du es nicht automatisch
       machen"). DETAIL_GAP stands in d.fields like a detail, as often as
       wanted; each one gives the next row half a line of air (GAP_LINES —
       the air that stood under the species by itself for an hour and that
       he kept). IN ONE LINE IT STAYS ONE LINE (the same evening: „nicht
       gemeint eine wirkliche Leerzeile … sondern den Abstand zu den
       Nachbarn vergrößern, in der Zeile"): there the Leerzeilen widen the
       space to the next detail instead of its „ · " — GAP_EM em per
       Leerzeile and one more, plain space without the dot, so the line
       falls into groups. A Leerzeile at the very start or end has nothing
       to part and is left out. */
    let air = 0;
    for (const key of d.fields) {
        if (key === DETAIL_GAP) {
            air += GAP_LINES;
            continue;
        }
        if (!named.has(key)) continue;
        const v = values[key];
        if (v == null || v === '') continue;
        /* THE WEIGHT BY WHAT STANDS IN FRONT (09.11.2026: „wenn
           gar nichts angeschaltet ist, nur den Artnamen fett … Wenn die
           Symbole angeschaltet sind, die Symbole fett, dafür die Texte
           dahinter nicht fett, außer wieder der Artname. Dieser immer
           fett"). With words in front the old rule stays: in one line the
           values bold, under one another the words. */
        const species = key === 'species';
        const value = { s: String(v), italic: species, bold: species || (oneLine && d.labels === true) };
        // The spore print's colour as a small square before its words (findtraits.js, sporePrintRead).
        const swatch = key === 'spore_print' && values.spore_print_color ? { swatch: values.spore_print_color } : null;
        let row;
        if (species || !d.labels) row = [value];
        else if (d.labels === 'icons') row = DETAIL_ICONS[key] ? [{ icon: DETAIL_ICONS[key] }, value] : [value];
        else row = [{ s: named.get(key) + ':', bold: !oneLine }, { s: ' ' }, value];
        if (swatch) row.splice(row.length - 1, 0, swatch);
        // The air of the Leerzeilen before it — `gap`, in lines, as every renderer reads it.
        if (air > 0 && rows.length > 0) row.gap = air;
        air = 0;
        rows.push(row);
    }
    if (!oneLine || rows.length < 2) return rows;
    // In one line: „ · " between neighbours, wide plain space where Leerzeilen stood.
    return [rows.flatMap((row, i) => (i === 0 ? row
        : [{ s: row.gap ? '\u2003'.repeat(Math.round(row.gap / GAP_LINES) * GAP_EM + 1) : ' · ' }, ...row]))];
}

/**
 * Where the words of a details TILE stand (06.10.2026: „die
 * Fundangaben in der Fußzeile zu verschieben" — on a picture they moved,
 * in a cell of their own they were nailed to the top left). x/y are the
 * words' top left as shares of the cell, null until moved: then at the
 * tile's padding. They wrap at the cell's right edge, less the padding.
 */
export function tileTextAt(info, W, H, mm) {
    const pad = TILE_PAD * mm;
    const left = info.x == null ? pad : info.x * W;
    const top = info.y == null ? pad : info.y * H;
    return { left, top, width: Math.max(10 * mm, W - left - pad) };
}

/** The same rows as HTML, for the screen. */
export function detailsHtml(d, values) {
    // The air before a row (GAP_LINES) in lines of the words' own height.
    return detailsRows(d, values).map(row => (row.gap ? `<div style="margin-top: ${row.gap * LINE_TILE}em">` : '<div>') + row.map(r => {
        // A sign in the words' ink, 1 em, a little air behind it (plates.css).
        if (r.icon) return `<span class="plate-detail-icon">${iconHtml(r.icon)}</span>`;
        if (r.swatch) return `<span class="plate-swatch" style="background:${esc(r.swatch)}"></span>`;
        const s = esc(r.s);
        const italic = r.italic ? `<i>${s}</i>` : s;
        return r.bold ? `<b>${italic}</b>` : italic;
    }).join('') + '</div>').join('');
}

/* --- The measures' blocks (step 3) ----------------------------------------- */

/** The find's pictures as the sources measureRows() counts over. */
const sourcesOf = (pics) => [...pics.values()].map(p => ({ kind: p.key, obj: p }));

/** The layers the find holds measures in, „Sporen" first, the rest by name. */
export function layersOf(pics) {
    const there = new Set();
    for (const p of pics.values()) {
        for (const m of p.measures ?? []) {
            // Counting frames and sectors make a layer too (18.11.2026): their row and their numbers.
            if (m.type === 'rect' || m.type === 'crop' || m.type === 'box' || m.type === 'sector' || measurePart(m)) there.add(measureLabel(m));
        }
    }
    // A layer of part measures alone (a basidium's sterigmata) is a layer
    // too, and so is one holding only picture cutouts (07.10.2026).
    const names = [...there].filter(l => measureRows(sourcesOf(pics), l).length > 0
        || partRows(sourcesOf(pics), l).length > 0 || cropNames(sourcesOf(pics), l).length > 0
        || countRows(sourcesOf(pics), l).length > 0 || countLines(sourcesOf(pics), l).length > 0);
    return names.sort((a, b) => (a === 'Sporen' ? -1 : b === 'Sporen' ? 1 : a.localeCompare(b, 'de')));
}

/** The part measures a layer holds, in the order first met. */
export function partsOf(pics, layer) {
    return [...new Set(partRows(sourcesOf(pics), layer).map(r => r.part))];
}

/** A layer's counts — frames and sectors — as the rows of a plate's row (countRows). */
export function countsOf(pics, layer) {
    return countRows(sourcesOf(pics), layer);
}

/** The names of a layer's picture cutouts, in the order first met. */
export function cropsOf(pics, layer) {
    return cropNames(sourcesOf(pics), layer);
}

/** A block's or a text's name: its layer, and its part where it has one —
 *  „Sporen · Keimporus"; a row of cutouts by their own name, or „Asci ·
 *  Ausschnitte" where they have none. */
export const blockName = (d) => (d.count === true ? `${d.layer} · ${t('Zählungen')}`
    : typeof d.crop === 'string'
    ? (d.crop !== '' ? d.crop : `${d.layer} · ${t('Ausschnitte')}`)
    : d.part ? `${d.layer} · ${d.part}` : d.layer);

/** One layer's measured structures over all the find's pictures — their
 *  bent lengths read straight where `straight` says so (frameValues). */
export function rowsOf(pics, layer, straight = false) {
    return measureRows(sourcesOf(pics), layer, straight);
}

/**
 * Whether a values text or a block reads its layer's bent lengths
 * straight, as the books do: its own choice where the plate made one,
 * else the photo window's for the layer — the same give and take as the
 * row's air (rowLook). Null on the plate means „as in the photo window".
 */
export function straightOf(d, style) {
    return typeof d.straight === 'boolean' ? d.straight : lengthStraight(style, d.layer);
}

/**
 * The rows a values text or a block shows: its layer, its length reading,
 * and its MARK — null for all of the layer, '' for the unmarked, or one
 * kind („Chrysozystide"), as the photo window's „Zeigen" (07.10.2026).
 */
export function rowsFor(pics, d, style) {
    // A row of picture cutouts (07.10.2026): their rows, in the order cut.
    if (typeof d.crop === 'string') return cropRows(sourcesOf(pics), d.layer, d.crop);
    // A row of counts (18.11.2026): each frame and sector as photographed, in the order set.
    if (d.count === true) return countRows(sourcesOf(pics), d.layer);
    /* A PART'S BLOCK (07.10.2026) shows that part's lines, shaped like the
       structures' rows so the row and its scale need no second geometry:
       a square frame the line's length a side (cropL = cropB), the length
       as L for the order, and the way the photo window turns its tiles
       (partDirection) — drawSporeRow draws such a row with lineDraw. */
    if (typeof d.part === 'string' && d.part !== '') {
        const direction = partDirection(style, d.layer, d.part);
        return partRows(sourcesOf(pics), d.layer).filter(r => r.part === d.part)
            .map(r => ({ ...r, L: r.um, B: null, Q: null, cropL: r.um, cropB: r.um, direction }));
    }
    return rowsMarked(rowsOf(pics, d.layer, straightOf(d, style)), typeof d.mark === 'string' ? d.mark : null);
}

/** The marks a layer's measures carry, in the order first met. */
export function marksOf(pics, layer) {
    return [...new Set(rowsOf(pics, layer).map(r => r.mark).filter(Boolean))];
}

/** A values text's lines — the photo window's, the ones the item ticked.
 *  Its name says the mark too, where it shows only one kind.
 *
 *  THE PART MEASURES TOO (07.10.2026: „die Werte würde ich
 *  tatsächlich nur einmal machen, aber dafür noch um die Teilmessungen
 *  ergänzen") — ticked as 'part:<name>' among the lines, each one line
 *  „Keimporus: (1,4) 1,5 – 1,7 (1,8) µm · N = 5 · Ø 1,6 µm" under the
 *  structure's.
 *
 *  WITH AIR BETWEEN THEM (the same day: „mit einer Leerzeile
 *  unterhalb der eigentlichen Sporen … weil diese ja dreizeilig ist"):
 *  without it the first part read as a fourth line of the spores. A row
 *  carries `gap`, the air before it in lines — a whole one under the
 *  structure, PART_GAP between two parts, so each stands as its own.
 *  Both renderers read it, the editor's and the print's. */
export const PART_GAP = .5;

export function valuesRows(item, rows, pics = null) {
    const name = typeof item.mark === 'string'
        ? `${item.layer} (${item.mark === '' ? t('ohne Merkmal') : item.mark})` : item.layer;
    const lines = valueLines(rows).filter(l => item.lines.includes(l.key));
    // Spread and outliers after the classic lines — without N, the Q line has it (spreadText).
    const spread = item.lines.includes('spread') ? spreadText(rows, false) : '';
    if (spread) lines.push({ text: spread });
    /* THE NAME IS A COLUMN OF ITS OWN (09.11.2026: „wenn dieser
       Name eingeblendet wird, würde ich das Design der unteren Zeilen so
       machen, als ob das eine Spalte wäre … die unteren Zeilen dann auch
       einrücken"). It no longer opens the first line; it stands as the
       first row's `lead`, and every renderer sets the leads in a column of
       their own and all rows in from it by its width, so the numbers start
       on one line. A PART'S NAME IS A LEAD TOO (the same evening: „das
       Label Keimporus müsste in die erste Spalte mitwandern") — it is a
       name of its own, not part of the numbers. Without the layer's name
       there is no column, and a part keeps its name in front, as before. */
    const out = lines.map(l => row(l.text, 0));
    if (item.name && out.length > 0) out[0].lead = name + ': ';
    if (pics) {
        const parts = partLines(partRows(sourcesOf(pics), item.layer));
        let first = true;
        for (const key of item.lines.filter(k => k.startsWith('part:'))) {
            const line = parts.find(p => p.part === key.slice(5));
            if (!line) continue;
            const gap = out.length === 0 ? 0 : first ? 1 : PART_GAP;
            out.push(item.name ? Object.assign(row(line.text, gap), { lead: `${line.part}: ` })
                : row(`${line.part}: ${line.text}`, gap));
            first = false;
        }
        /* THE COUNTS' NUMBERS (18.11.2026): the sectors' means and ratio,
           a line per frame — the photo window's statistics (countLines).
           The sectors' lines name their kinds themselves („Lamellen: Ø …");
           a layer's name before them said „Lamellen: Lamellen: …". Only a
           frame's line, which says no kind, takes the name where it opens
           the text. */
        countLines(sourcesOf(pics), item.layer).forEach(({ text, named }, i) => {
            const r = row(text, i === 0 && out.length > 0 ? 1 : 0);
            if (i === 0 && out.length === 0 && item.name && !named) r.lead = name + ': ';
            out.push(r);
        });
    }
    return out;
}

/**
 * A KEYING WAY ON THE PLATE (09.11.2026, with the taxonomy
 * session: „den Schlüsselweg plus diese Bemerkung, oder nur die Bemerkung,
 * beschriftet mit Buch- oder Schlüsselnamen"). The item names its way by
 * key_id — the key the confirmed way ends in — and finds it among the
 * find's ways (finddetails.js, keyings). Its rows: the name in bold — the
 * book or the key —, the way „1a → 2b → 7a → Art" under it, and the remark
 * after half a line of air, a row per line it was written in. Null where
 * the way is no longer there (no longer confirmed, or keyed in another
 * key now): the editor says so, the print leaves it out.
 */
export function keyingRows(item, ways) {
    const way = (ways ?? []).find(w => w.key_id === item.key_id);
    if (!way) return null;
    const show = item.show ?? 'both';
    /* BOOK AND KEY BY DEFAULT (09.11.2026: in the strip it read
       „Gröger 2 · Teilschlüssel B, Schwefelköpfe in Sümpfen, Mooren und
       Heidelandschaften", on the plate only „Gröger 2" — „diese Information
       fehlt"). A book often holds many keys; the key says which way was
       walked. The book alone or the key alone remain to choose. */
    /* THE WHOLE CHAIN OF KEYS (the same evening: „Bei Gröger
       müsste es ja eigentlich noch den Hauptschlüssel geben … wenn wir bei
       Teilschlüssel B sind, sollte man noch den Hauptschlüssel wissen"):
       the keys the way passed, in order, „Hauptschlüssel → Teilschlüssel
       B, …" (findkeying.js, keys — delivered by the taxonomy session,
       1d7728bc). A way of one key reads as before. And the way itself in
       the same order, each key's stretch apart by „ | ", so a step number
       is read against the right key. */
    const chain = (way.keys?.length ? way.keys : [way.key]).filter(Boolean).join(' → ');
    const name = item.label === 'key' ? (chain || way.book)
        : item.label === 'book' ? (way.book || chain)
            : [way.book, chain].filter(Boolean).join(' · ');
    const out = [row(name, 0)];
    out[0][0].bold = true;
    const stretches = (way.path_by_key ?? []).map(p => p.path).filter(Boolean);
    const path = stretches.length > 1 ? stretches.join('  |  ') : way.path;
    if (show !== 'remark' && path) out.push(row(path, 0));
    if (show !== 'path' && way.remark) {
        let first = true;
        for (const para of way.remark.split(/\n\s*\n/)) {
            para.split('\n').map(s => s.trim()).filter(Boolean).forEach((line, i) => {
                out.push(row(line, i === 0 ? (first && out.length === 1 ? 0 : GAP_LINES) : 0));
            });
            first = false;
        }
    }
    return out;
}

/** One row of a values text: its run, and the air before it in lines. */
function row(s, gap) {
    return Object.assign([{ s }], { gap });
}

/* THE DISTRIBUTION ON PAPER: the window draws it in the theme's colours,
   a plate in ink — grey axes, the 68 % core and the dots in a green that
   still reads when printed black and white. */
const PLOT_INK = { axis: '#666', quiet: '#555', accent: '#2a7a4b', dot: '#2a7a4b' };
/* A DISTRIBUTION ON BLACK (09.11.2026: „den Hintergrund, also
   weiß-schwarz, das würde ich auch noch als Option mit reinnehmen") —
   lying on a dark micrograph a white card glares. The greys turned round;
   dots, core ellipse and „68 %" in LIGHT BLUE. The green lifted for black
   did not please him („gefällt mir das Grün nicht"); shown side by side
   in the browser — the lifted green, white, mint, yellow, light blue,
   orange — he chose the light blue the same day. */
const PLOT_INK_DARK = { axis: '#a8a8a8', quiet: '#b8b8b8', accent: '#8cc8ff', dot: '#8cc8ff' };
/** A distribution's ground, white unless it asks for black. */
export const plotGround = (block) => (block?.ground === 'black' ? '#000' : '#fff');
/* HOW MUCH THE GROUND COVERS, 10–100 % (09.11.2026: „von der
   Verteilungsstatistik die Hintergründe transparent machen … einen Regler
   für Transparenz") — offered where the distribution lies on a picture as
   an inset; the dots, rims and words keep their full ink. */
export const plotGroundOpacity = (block) => Math.min(100, Math.max(10, Number(block?.groundOpacity) || 100)) / 100;
/** The ground as an SVG rect W × H. */
export const plotGroundRect = (block, W, H) =>
    `<rect width="${W}" height="${H}" fill="${plotGround(block)}" fill-opacity="${plotGroundOpacity(block)}"/>`;

/** A block's distribution for a cell W × H — a part's curve along one
 *  axis, a structure's length-against-breadth cloud; '' while there are
 *  too few to show. */
export function blockPlotInner(block, rows, W, H, mm) {
    if (block.part) {
        return rows.length >= 2
            ? `<g font-family="${PAGE_FONT}">${curveSvg(rows, plotInk(block), { W, H, font: 2.6 * mm })}</g>` : '';
    }
    const dots = rows.filter(r => r.B != null);
    if (dots.length < 2) return '';
    /* COUNT, SPREAD AND OUTLIERS UNDER THE CLOUD (09.11.2026:
       „diese neue Zeile bei der Verteilungsgrafik mit anzeigen unten
       drunter. Dann könnte man das auch irgendwo vielleicht einzeln
       platzieren und hätte n"). On unless the block says no; the plot
       gives up a line of its height for it. Its type is the plot's, made
       smaller only where the cell is too narrow for the line. */
    const line = block.spread !== false ? spreadText(dots) : '';
    if (!line) return plotInner(dots, W, H, mm, plotInk(block));
    const ink = plotInk(block);
    const font = Math.min(2.6 * mm, .94 * W / (mediaCanvas().measureText(line).width / 100));
    const lineH = 2.6 * mm * 1.7;
    return plotInner(dots, W, H - lineH, mm, ink)
        + `<text x="${W / 2}" y="${H - lineH * .45}" font-size="${font}" fill="${ink.quiet}" text-anchor="middle"`
        + ` font-family="${PAGE_FONT}" dominant-baseline="middle">${esc(line)}</text>`;
}
const plotInk = (block) => (block?.ground === 'black' ? PLOT_INK_DARK : PLOT_INK);

/** The plot's inner SVG for a cell W × H pixels, its type in millimetres. */
export function plotInner(rows, W, H, mm, ink = PLOT_INK) {
    // The page's type for the plot too — an SVG drawn as an image knows no stylesheet.
    return `<g font-family="${PAGE_FONT}">${plotSvg(rows, ink, { W, H, font: 2.6 * mm, dot: .8 * mm })}</g>`;
}

/**
 * A row's look, its defaults filled in (06.10.2026, on the row:
 * „ob diese kleinen Vorschaubilder einen Abstand zueinander haben, ob sie
 * per Linien getrennt werden, ob diese weiß/schwarz ist, wie breit … ob
 * ich außenrum einen Rand habe … wie ist der Abstand zu dem eigentlich
 * gemessenen Rechteck"). The AIR around each spore is the photo window's
 * per layer — „sinnvoll, wenn wir erstmal die Angaben übernehmen, die ich
 * im Statistikfenster genommen habe" — until the plate sets its own
 * („ein eigener Regler bei den Tafeln wäre natürlich auch ganz nett").
 * The lines between the spores and the margin around them are the same
 * ground in one colour, as the lines between a page's pictures are.
 */
/* „SCHMAL" IS AS WIDE AS THE FINE LINES (07.10.2026: „wenn
   beides auf klein steht, hätte ich es gern so, dass der Rand außen rum
   dem Abstand der Bilder zueinander entspricht"): 0.6 mm both, so a row
   at its smallest reads as one even grid. It was 2 mm; a row stored with
   that is read as today's „schmal" (plates.js, adopt). */
export const ROW_PAD_THIN = .6;
export const ROW_DEFAULTS = { gap: .6, gapColor: '#ffffff', pad: ROW_PAD_THIN };
export function rowLook(block, style) {
    const own = Number(block?.air);
    return {
        // A cutout shows what was cut, no more: no air unless the row sets it.
        air: own >= 100 && own <= 300 ? own / 100
            : typeof block?.crop === 'string' ? 1
            // A count shows its frame or ellipse with a breath of cap around it.
            : block?.count === true ? 1.06
            : block?.part ? partAir(style, block.layer, block.part) : sporeAir(style, block?.layer),
        gap: block?.gap ?? ROW_DEFAULTS.gap,
        ground: block?.gapColor ?? ROW_DEFAULTS.gapColor,
        pad: block?.pad ?? ROW_DEFAULTS.pad,
    };
}
/** The bar's ink on a row's ground. */
export const rowInk = (look) => (look.ground === '#000000' ? '#fff' : '#000');

/**
 * THE BAR OVER THE PICTURES (08.11.2026: „dass der Maßstab
 * nicht in einer Zeile oben oder unten drüber liegt, sondern überblendet
 * wird über das Bild … über die einzelnen Reihenaufnahmen. Oder einen
 * Bildausschnitt"): with cell.barOver the row keeps no strip for the bar
 * (rowGeometry is asked with 'off'), and the bar lies in the frame that
 * stands in the cell's corner of the grid — top left the first, top
 * right the end of the first line. BOTTOM RIGHT IS THE GRID'S CORNER, NOT
 * THE LAST PICTURE (the same day: „bleibt ja hinten ein leeres
 * Feld oder zwei. Das wäre eigentlich der ideale Platz für den
 * Maßstab"): where the last line is not full, that place stands empty and
 * the bar takes it, on the row's ground, instead of covering a spore —
 * as long as fits there. Over the pictures it may cross frames (below).
 * Its ink is read off the drawn canvas (inkOnCanvas), as a picture's bar
 * is read off its picture.
 * @returns {?{x, y, len, label, font, thick, gap, boxH}} cell pixels
 */
export function rowBarOver(cell, geo, n, mm) {
    if (!geo || n === 0 || cell.bar === 'off') return null;
    const lines = Math.ceil(n / geo.cols);
    const bottom = cell.bar.startsWith('b'), right = cell.bar.endsWith('r');
    const line = bottom ? lines - 1 : 0;
    /* THE EMPTY FIELDS AT THE END take the bar as long as it FITS
       (08.11.2026: „bei 2 µ … da würden sicher 5 µ noch
       hinpassen … 2 µ ist eine falsch gewählte Ansicht"). There it covers
       nothing: the longest round length between the two edges, over ALL
       the empty fields together. Only the bottom right can be empty — the
       grid never has more columns than pictures (rowGeometry), so the
       first line is always full.

       OVER THE PICTURES IT MAY CROSS FRAMES (09.11.2026: „dieser
       Maßstab richtet sich immer … an die Größe dieses Fensters. Er könnte
       aber doch auch größer angezeigt werden, wenn er quasi über alle
       Ausschnitte gelegt wird … einen Mini-Balken von 2 µ … Man könnte aber
       auch die letzten 2, 3 Bilder nehmen und einen Balken von 10 µ
       anzeigen"). Until then it stayed inside the one frame in its corner,
       half that frame long. Now it measures itself against TWO FRAMES taken
       together — „es würde schon reichen, wenn zwei Ausschnitte zusammen
       genommen werden … nicht, dass er mir zu lang und zu groß wird" (the
       same evening, after a first try let it run to two whole frames): over
       pictures half of that window, in empty fields at most the window
       itself. Laid against the whole line, anchored in the cell's corner;
       the ink is read off what lies beneath, frame or ground (inkOnCanvas).
       A row of one column keeps its one frame. */
    const empty = bottom && right ? lines * geo.cols - n : 0;
    const lineW = geo.cols * geo.fw + (geo.cols - 1) * geo.gap;
    const k = Math.min(2, geo.cols);
    const windowW = k * geo.fw + (k - 1) * geo.gap;
    const emptyW = empty > 0 ? empty * geo.fw + (empty - 1) * geo.gap : 0;
    const want = Math.max(.5 * windowW, Math.min(emptyW, windowW) - 2 * BAR_EDGE * mm);
    const fx = geo.x0, fy = geo.y0 + line * (geo.fh + geo.gap);
    const b = barGeometry(cell, lineW, geo.fh, mm, 1 / geo.pxPerUm, Math.max(.05, want / lineW));
    if (!b) return null;
    return { ...b, x: b.x + fx, y: b.y + fy };
}

/**
 * THE ROW: every spore of a layer, upright, at ONE scale — so the tile
 * carries ONE bar, and a small spore rightly looks small beside a large
 * one, whichever photo either came from. All frames share the size of
 * the largest, as in the photo window (28.08.2026: „nach der
 * größten Spore, sodass wir da keine Treppung drin haben"); the number
 * of columns is the one that lets the spores stand largest in the cell.
 * The bar keeps a strip of its own, so it never lies on a spore — unless
 * the cell lays it over the pictures (rowBarOver).
 */
export function rowGeometry(rows, W, H, look, mm, corner) {
    if (rows.length === 0) return null;
    const tall = Math.max(...rows.map(r => r.cropL)) * look.air;
    const wide = Math.max(...rows.map(r => r.cropB)) * look.air;
    const pad = look.pad * mm, gap = look.gap * mm;
    const strip = corner === 'off' ? 0 : (BAR_FONT * 1.15 + BAR_GAP + BAR_THICK + BAR_EDGE_Y) * mm;
    const roomW = W - 2 * pad, roomH = H - 2 * pad - strip;
    let best = null;
    for (let cols = 1; cols <= rows.length; cols++) {
        const lines = Math.ceil(rows.length / cols);
        const s = Math.min((roomW - (cols - 1) * gap) / (cols * wide), (roomH - (lines - 1) * gap) / (lines * tall));
        if (s > 0 && (best === null || s > best.s)) best = { s, cols, lines };
    }
    if (best === null) return null;
    /* THE FRAMES FILL THE CELL (06.10.2026: „die oftmals nicht
       zu der Kachel passt, die ich frei ziehen kann. Könnten wir hier noch
       eine extra Breite und extra Höhe dazugeben, sodass es immer bis zum
       Rand ausgeht … das fällt nicht wirklich auf zu dem, was ich an Rand
       einstelle"). The scale stays the one that lets the spores stand
       largest; what the grid leaves over in the other direction is shared
       out among the frames, so each shows a little more picture around
       its spore and the grid reaches the margin on every side. Only a last
       line that is not full keeps its end empty. */
    const fw = (roomW - (best.cols - 1) * gap) / best.cols;
    const fh = (roomH - (best.lines - 1) * gap) / best.lines;
    const above = corner.startsWith('t') ? strip : 0;
    return { pxPerUm: best.s, cols: best.cols, fw, fh, gap, x0: pad, y0: above + pad };
}

/**
 * Draw a row into a context whose origin is the cell's corner. `imageOf`
 * gives a row's loaded picture, or null — then its frame stays a quiet
 * grey until the picture is there.
 */
export function drawSporeRow(ctx, rows, sort, geo, imageOf) {
    // Cutouts and counts stand in the order they were set — a length says nothing there.
    (rows[0]?.crop !== undefined || rows[0]?.count ? rows : sporeOrder(rows, sort)).forEach((r, i) => {
        const x = geo.x0 + (i % geo.cols) * (geo.fw + geo.gap);
        const y = geo.y0 + Math.floor(i / geo.cols) * (geo.fh + geo.gap);
        ctx.save();
        ctx.beginPath();
        ctx.rect(x, y, geo.fw, geo.fh);
        ctx.clip();
        const img = imageOf(r);
        if (img) {
            ctx.translate(x + geo.fw / 2, y + geo.fh / 2);
            // A part's line lies as its tab turns it; a structure stands upright.
            if (r.count) countDraw(ctx, img, r, geo.pxPerUm, { W: geo.fw, H: geo.fh });
            else if (r.part !== undefined) lineDraw(ctx, img, r, geo.pxPerUm, { W: geo.fw, H: geo.fh }, r.direction);
            else sporeDraw(ctx, img, r, geo.pxPerUm, { W: geo.fw, H: geo.fh });
        } else {
            ctx.fillStyle = '#e6e8e7';
            ctx.fillRect(x, y, geo.fw, geo.fh);
        }
        ctx.restore();
    });
}

/*
 * THE WRITING ON A PICTURE: ITS INK AND A SOFT GROUND
 * (09.11.2026: „Für alle diese Texte, die ich in Bildern oder Reihen setzen
 * kann, also Maßstabsbalken, Label, Texte und Medium, hätte ich gerne die
 * Funktion Autotextfarbe und manuell … Plus eine Option für einen …
 * verschwommenen einheitlicheren Hintergrund. Der aber mit einem Verlauf
 * ins Bild läuft"). EACH PIECE ON ITS OWN — „für jeden Text einzeln",
 * because auto may be right for the bar bottom right and wrong for the
 * medium bottom left:
 *
 *   cell.ink   {bar, med, label} → 'black' | 'white'; absent: read off what
 *              lies beneath, as before.
 *   cell.soft  {bar, med, label} → the picture beneath blurred or not;
 *              absent, the default (softWanted),
 *              fading into the sharp picture over SOFT_FEATHER mm on every
 *              side. NO COLOUR OF ITS OWN — the first build veiled it
 *              light or dark, and the maintainer wanted „ein verschwommenes
 *              Bild und keine Farbänderung".
 *
 * The editor draws it on pictures with the browser's backdrop blur
 * (plates.js, .plate-soft) and on rows' canvases with softOnCanvas, the
 * print with softOnCanvas everywhere — one recipe, the same numbers.
 */
/* CLOSE ROUND THE WORDS (20.11.2026: „der unscharfe
   Untergrund … spiegelt jetzt quasi die Außenlinie mit rein. Der ist etwas
   weitläufig um den Text gesetzt … Er soll ja nicht das halbe Bild
   verdecken"). It reached 2.4 mm past the writing and faded over 1.8 mm;
   once bar, medium and label moved to 1.5 mm from the top and the bottom
   (BAR_EDGE_Y), it reached past the cell's edge, and the blur took the
   white line between the pictures into itself — the browser mirrors the
   backdrop at an element's edge, the canvas reads what lies there.
   ONLY IN HEIGHT (the same minute: „nur in der Höhe etwas den unscharfen
   Bereich reduzieren. In der Breite ist es, glaube ich, ganz nett"):
   sideways it keeps its 2.4 mm and its 1.8 mm fade, where the 2.6 mm to
   the side leave room; up and down 1 mm and a fade of .8 mm, which stays
   inside the cell (1.5 − 1 > 0) and covers the words, not the picture. */
export const SOFT_PAD = 2.4, SOFT_PAD_Y = 1, SOFT_FEATHER = 1.8, SOFT_FEATHER_Y = .8, SOFT_BLUR = 1.2;
/* MEDIUM AND LABEL ALWAYS, THE BAR ON REQUEST (09.11.2026, once
   he had seen it: „für Label und Medium zeichnen wir automatisch unten
   drunter weich … nur für den Maßstabsbalken machen wir es optional").
   A word standing on spores is read better on every picture; the bar's
   line is legible by itself and may want the picture sharp to its edge.
   soft.med / soft.label from the hours before stay stored and mean
   nothing now.
   A CHOICE FOR ALL THREE SINCE (20.11.2026: „das hätte ich
   dann doch gerne noch bei Medium und Label … jetzt, wo wir so schöne
   Einzeiler haben, hätte ich es doch gerne überall als Option"). The
   defaults stay as they were — medium and label soft, the bar sharp —, so
   a plate saved before draws the same; a medium or label switched off
   stores false, a bar switched on true, and the old true of medium or
   label simply means its default. */
export const softWanted = (cell, key) => (key === 'bar' ? cell?.soft?.bar === true : cell?.soft?.[key] !== false);
/** The ink of one piece of writing: its own choice, else the one read off the picture. */
export const inkFor = (choice, auto) => (choice === 'black' ? '#000' : choice === 'white' ? '#fff' : auto);
/** The rectangle a soft ground covers around writing at x, y, w × h. */
export const softRect = (x, y, w, h, mm) => ({ x: x - SOFT_PAD * mm, y: y - SOFT_PAD_Y * mm, w: w + 2 * SOFT_PAD * mm, h: h + 2 * SOFT_PAD_Y * mm });
/**
 * A soft ground into a canvas: the canvas beneath the rectangle blurred,
 * veiled, and feathered out on all four sides — then laid back. Works in
 * the canvas' own transform (the editor's device pixels, the print's
 * page). Draw every soft ground of a cell BEFORE its writing, or a later
 * one blurs the words already there.
 */
export function softOnCanvas(ctx, r, mm) {
    const t = ctx.getTransform();
    const dx = t.a * r.x + t.e, dy = t.d * r.y + t.f, dw = t.a * r.w, dh = t.d * r.h;
    const W = Math.max(1, Math.round(dw)), H = Math.max(1, Math.round(dh));
    const off = document.createElement('canvas');
    off.width = W;
    off.height = H;
    const o = off.getContext('2d');
    o.filter = `blur(${Math.max(1, SOFT_BLUR * mm * t.a)}px)`;
    o.drawImage(ctx.canvas, dx, dy, dw, dh, 0, 0, W, H);
    o.filter = 'none';
    // Feathered on all sides: two fades laid over each other keep only where both are solid.
    o.globalCompositeOperation = 'destination-in';
    // The fade sideways and the fade up and down, each no more than half its side.
    const fx = Math.min(SOFT_FEATHER * mm * t.a, W / 2), fy = Math.min(SOFT_FEATHER_Y * mm * t.a, H / 2);
    for (const [x1, y1, size, f] of [[W, 0, W, fx], [0, H, H, fy]]) {
        const fade = o.createLinearGradient(0, 0, x1, y1);
        fade.addColorStop(0, 'rgba(0,0,0,0)');
        fade.addColorStop(f / size, '#000');
        fade.addColorStop(1 - f / size, '#000');
        fade.addColorStop(1, 'rgba(0,0,0,0)');
        o.fillStyle = fade;
        o.fillRect(0, 0, W, H);
    }
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.drawImage(off, dx, dy);
    ctx.restore();
}

/**
 * The writing of a cell into a canvas — bar, medium, label — each with its
 * own ink and soft ground, in the one order that works: every ink read
 * first (off the bare picture), then every soft ground, then every word —
 * a ground laid later would blur the words already standing.
 * parts: [{key: 'bar'|'med'|'label', x, y, w, h, auto: () => ink,
 * draw: ink => …, soft: false where no ground belongs — a row's bar in its
 * own line}].
 */
export function writeOnCanvas(g, cell, mm, parts) {
    const inks = parts.map(p => inkFor(cell?.ink?.[p.key], p.auto()));
    for (const p of parts) {
        if (p.soft !== false && softWanted(cell, p.key)) softOnCanvas(g, softRect(p.x, p.y, p.w, p.h, mm), mm);
    }
    parts.forEach((p, i) => p.draw(inks[i]));
}

/**
 * A row's writing for writeOnCanvas, in the editor and the print alike: the
 * bar — its ink off the row's ground in a line of its own (rowInk), off the
 * pictures when it lies over them, and a soft ground only there —, then
 * medium and label, their ink off whatever lies beneath (inkOnCanvas).
 */
export function rowWriting(g, cell, b, m, lab, look) {
    const parts = [];
    if (b) {
        parts.push({ key: 'bar', x: b.x, y: b.y, w: b.len, h: b.boxH, soft: !!cell.barOver,
            auto: () => (cell.barOver ? inkOnCanvas(g, b.x, b.y, b.x + b.len, b.y + b.boxH) : rowInk(look)),
            draw: ink => drawBar(g, b, ink) });
    }
    for (const [key, w] of [['med', m], ['label', lab]]) {
        if (w) parts.push({ key, x: w.x, y: w.y, w: w.w, h: w.h, auto: () => inkOnCanvas(g, w.x, w.y, w.x + w.w, w.y + w.h), draw: ink => drawMedia(g, w, ink) });
    }
    return parts;
}

/** The medium into a canvas, as .plate-med draws it in the DOM. */
export function drawMedia(ctx, m, ink, x = 0, y = 0) {
    ctx.save();
    ctx.fillStyle = ink;
    ctx.font = `400 ${m.font}px ${PAGE_FONT}`;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.fillText(m.label, x + m.x, y + m.y + m.h / 2);
    ctx.restore();
}

/** A bar into a canvas — label above the line, as .plate-bar draws it in the DOM. */
export function drawBar(ctx, b, ink, x = 0, y = 0) {
    ctx.save();
    ctx.fillStyle = ink;
    ctx.font = `400 ${b.font}px ${PAGE_FONT}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    // The label's line box is 1.1 of its size, as .plate-bar draws it.
    ctx.fillText(b.label, x + b.x + b.len / 2, y + b.y + b.font * 1.1 / 2);
    ctx.fillRect(x + b.x, y + b.y + b.font * 1.1 + b.gap, b.len, b.thick);
    ctx.restore();
}
