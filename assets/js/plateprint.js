/**
 * THE PLATE ON PAPER — a page drawn at 300 dpi, as JPEG and as PDF
 * (06.10.2026, step 2 of the plates; the editor is plates.js).
 *
 * THE SAME PAGE, NOT A SECOND ONE: every cutout, bar and line of details
 * comes from plateparts.js, the arithmetic the screen draws from — only
 * the target differs, a canvas of 2480 × 3508 pixels instead of the DOM.
 * What one laid out is what the printer gets.
 *
 * THE BEST PIXELS A CELL NEEDS, AND NO MORE. A cloud recording is shown
 * from its 2048-pixel preview; a half page at 300 dpi is 1240 pixels
 * wide, so the preview carries most cells — but a cutout zoomed in four
 * times wants the original. The original is fetched only where the
 * preview would have to be blown up, and let go again as soon as its cell
 * is drawn: a 6000-pixel stack decodes to about a hundred megabytes, and
 * ten of them held at once would bring the tab down.
 *
 * THE PDF IS WRITTEN HERE, by hand — one JPEG per A4 page, a few hundred
 * bytes of structure around them. A library for that would be a
 * dependency the project does not want (no build step, no package.json),
 * and the format for „a picture filling a page" is small and old.
 */

import { t } from './i18n.js';
import {
    notePtOf, PT_MM, LINE_ITEM, LINE_TILE, BOX_PAD_X, BOX_PAD_Y, PAGE_FONT,
    loadForDone, developImage, aspectOf, inkAt, umPerShownPx, cutout, barGeometry, mediaGeometry, labelGeometry, pictureLabel, rowMediaLabel, rowLabel, drawMedia, detailsRows,
    rowsFor, valuesRows, keyingRows, blockPlotInner, plotGroundRect, plotGroundOpacity, rowGeometry, rowStripsOf, rowTexts, drawSporeRow, drawBar, rowLook, rowInk, rowBarOver, inkOnCanvas, tileTextAt, writeOnCanvas, rowWriting,
    DETAIL_ICONS, EDGE_MM,
} from './plateparts.js';
import { icon } from './icons.js';
import { sporeImagePxPerUm } from './measurestats.js';
import { edgePatch, edgeFillsDone } from './edgefill.js';
import { mediaLabel } from './media.js';
import { noteShapes, drawNotes } from './plateannot.js';

export const DPI = 300;
const A4_PT = [595.28, 841.89];   // A4 in PDF points

/**
 * Draw one page into a canvas at `dpi`.
 * @param {{layout: object, page: object, pics: Map, values: object, style: ?object,
 *          say?: (done: number, total: number) => void}} o
 * @returns {Promise<{canvas: HTMLCanvasElement, missing: number}>}
 */
export async function renderPage({ layout, page, pics, values, style = null, dpi = DPI, say = () => {} }) {
    const mm = dpi / 25.4;
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(210 * mm);
    canvas.height = Math.round(297 * mm);
    const g = canvas.getContext('2d');
    g.imageSmoothingEnabled = true;
    g.imageSmoothingQuality = 'high';
    g.textBaseline = 'middle';
    // The lines between the cells are the page's ground showing through.
    g.fillStyle = layout.gutterColor ?? '#ffffff';
    g.fillRect(0, 0, canvas.width, canvas.height);

    const boxes = [];
    place(page.root, 0, 0, canvas.width, canvas.height, (layout.gutter ?? 0) * mm, boxes);
    await detailIconsReady();
    let missing = 0;
    // An inset's frame: the colour and width of the lines between the cells.
    const frame = { w: (layout.gutter ?? 0) * mm, color: layout.gutterColor ?? '#ffffff' };
    for (const [i, box] of boxes.entries()) {
        say(i, boxes.length);
        if (!await drawCell(g, box, { pics, values, style, mm, frame, notePt: notePtOf(layout, style) })) missing++;
    }
    return { canvas, missing };
}

/**
 * The cells' rectangles — the flexbox of the screen in numbers: a split
 * shares what its gaps leave over by its shares. Each edge is rounded
 * ONCE, where two neighbours meet, so no hairline of the ground shows
 * between two cells that touch.
 */
function place(node, x, y, w, h, gap, out) {
    if (node.t === 'cell') {
        const x0 = Math.round(x), y0 = Math.round(y);
        out.push({ cell: node, x: x0, y: y0, w: Math.round(x + w) - x0, h: Math.round(y + h) - y0 });
        return;
    }
    const across = node.dir === 'row';
    const sum = node.sizes.reduce((a, b) => a + b, 0) || 1;
    const room = (across ? w : h) - gap * (node.kids.length - 1);
    let at = across ? x : y;
    node.kids.forEach((kid, i) => {
        const size = room * node.sizes[i] / sum;
        if (across) place(kid, at, y, size, h, gap, out);
        else place(kid, x, at, w, size, gap, out);
        at += size + gap;
    });
}

/** One cell: paper, picture, bar, items. False when its picture would not load. */
async function drawCell(g, box, at) {
    const { pics, values, mm } = at;
    const { cell, x, y, w, h } = box;
    let ok = true;
    g.save();
    g.beginPath();
    g.rect(x, y, w, h);
    g.clip();
    // Paper — except under a distribution whose own ground lets the picture through (plotGroundOpacity).
    if (!(cell.block?.kind === 'plot' && plotGroundOpacity(cell.block) < 1)) {
        g.fillStyle = '#fff';
        g.fillRect(x, y, w, h);
    }

    let picture = null;
    let geo = null;
    if (cell.info) {
        detailsTile(g, cell.info, values, box, mm);
    } else if (cell.block) {
        ok = await drawBlock(g, cell, box, at);
    } else if (cell.pic && pics.has(cell.pic)) {
        const pic = pics.get(cell.pic);
        picture = await sharpest(pic, w, h, cell);
        if (picture) {
            geo = cutout(cell, w, h, aspectOf(pic, picture));
            g.drawImage(picture.img, x + geo.left, y + geo.top, geo.pw, geo.ph);
            const toFile = (px, py) => [(px - geo.left) / geo.pw * picture.w, (py - geo.top) / geo.ph * picture.h];
            const b = barGeometry(cell, w, h, mm, umPerShownPx(pic, geo.pw, picture));
            if (cell.notes?.length || cell.measures?.length) {
                // The measures and the writing of the ticked layers: the plate's size, the picture's places.
                const shapes = noteShapes(pic, cell.notes, geo, umPerShownPx(pic, geo.pw, picture), at.notePt * PT_MM * mm, cell.measures);
                drawNotes(g, shapes, { line_color: '#000000', seam_color: '#ffffff', ...(at.style ?? {}) }, Math.max(1, .15 * mm), x, y);
            }
            // The medium as the page draws it (plates.js, layoutCell).
            const m = mediaGeometry(cell, w, h, mm, mediaLabel(pic.media, cell.medFull), b);
            // The label the same way, beyond bar and medium where it shares their corner.
            const lab = labelGeometry(cell, w, h, mm, pictureLabel(pic, cell), b, m);
            /* Their ink the cell's or the picture's, a soft ground under each
               where the cell asks for one (plateparts.js, writeOnCanvas) —
               in the page's places, so the cell's offset joins the boxes. */
            const auto = (bx, by, bw, bh) => () => inkAt(picture, ...toFile(bx, by), ...toFile(bx + bw, by + bh));
            const parts = [];
            if (b) parts.push({ key: 'bar', x: x + b.x, y: y + b.y, w: b.len, h: b.boxH, auto: auto(b.x, b.y, b.len, b.boxH), draw: ink => drawBar(g, b, ink, x, y) });
            for (const [key, tx] of [['med', m], ['label', lab]]) {
                if (tx) parts.push({ key, x: x + tx.x, y: y + tx.y, w: tx.w, h: tx.h, auto: auto(tx.x, tx.y, tx.w, tx.h), draw: ink => drawMedia(g, tx, ink, x, y) });
            }
            writeOnCanvas(g, cell, mm, parts);
            if (!await drawInsets(g, box, at)) ok = false;
            for (const item of cell.items) {
                if (item.kind === 'inset') continue;
                drawItem(g, item, at, box, (bx, by, bw, bh) =>
                    inkAt(picture, ...toFile(bx - x, by - y), ...toFile(bx - x + bw, by - y + bh)));
            }
        } else {
            ok = false;
        }
    }
    if (!picture) {
        if (!await drawInsets(g, box, at)) ok = false;
        // Items stand on paper where there is no picture to read.
        for (const item of cell.items) if (item.kind !== 'inset') drawItem(g, item, at, box, () => '#000');
    }
    // An original fetched for this cell alone is let go at once.
    if (picture?.temporary) {
        picture.img.src = '';
        picture.img = null;
    }
    g.restore();
    return ok;
}

/**
 * A cell's INSETS (07.10.2026): each a cell of its own in a smaller box,
 * drawn by drawCell itself, on a frame in the colour and width of the
 * page's lines — over the picture, under the words. False where one of
 * their pictures would not load.
 */
async function drawInsets(g, box, at) {
    let ok = true;
    for (const item of box.cell.items) {
        if (item.kind !== 'inset') continue;
        const inner = {
            cell: item.cell,
            x: Math.round(box.x + item.x * box.w), y: Math.round(box.y + item.y * box.h),
            w: Math.round(item.w * box.w), h: Math.round(item.h * box.h),
        };
        // Its frame where it keeps one (item.frame, 09.11.2026).
        if (at.frame?.w > 0 && item.frame !== false) {
            g.fillStyle = at.frame.color;
            g.fillRect(inner.x - at.frame.w, inner.y - at.frame.w, inner.w + 2 * at.frame.w, inner.h + 2 * at.frame.w);
        }
        if (!await drawCell(g, inner, at)) ok = false;
    }
    return ok;
}

/**
 * The preview, or the original where the preview would be blown up —
 * the cutout's full width at this resolution against the file's pixels.
 */
async function sharpest(pic, w, h, cell) {
    // As developed (0190) — the preview from the page's store, the original rendered for this drawing.
    const preview = await loadForDone(pic, pic.src);
    const usable = preview.ready ? preview : null;
    if (!pic.original) return usable;
    const need = cutout(cell, w, h, aspectOf(pic, usable)).pw;
    if (usable && usable.w >= need * .95) return usable;
    const original = await developedOnce(pic, await loadOnce(pic.original));
    return original ?? usable;
}

/** A picture loaded for one drawing, its development laid over it where it has one. */
async function developedOnce(pic, loaded) {
    if (!loaded || !pic.develop) return loaded;
    const img = await developImage(loaded.img, pic.develop, pic.key);
    return img ? { img, ready: true, w: img.naturalWidth, h: img.naturalHeight, temporary: true } : loaded;
}

/** A picture loaded for one drawing only — not kept in the page's store. */
function loadOnce(src) {
    return new Promise((resolve) => {
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => resolve({ img, ready: true, w: img.naturalWidth, h: img.naturalHeight, temporary: true });
        img.onerror = () => resolve(null);
        img.src = src;
    });
}

/**
 * A measures' block: the distribution as its SVG drawn into the page, or
 * the row with each picture as sharp as the row's scale asks — the
 * original only where the preview would be blown up, let go afterwards.
 */
async function drawBlock(g, cell, box, { pics, style, mm }) {
    const { x, y, w, h } = box;
    const rows = rowsFor(pics, cell.block, style);
    if (cell.block.kind === 'plot') {
        // A structure's cloud, or a part's curve (blockPlotInner).
        const drawn = blockPlotInner(cell.block, rows, w, h, mm);
        if (drawn === '') return true;
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">`
            + `${plotGroundRect(cell.block, w, h)}${drawn}</svg>`;
        const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
        try {
            const img = new Image();
            img.src = url;
            await img.decode();
            g.drawImage(img, x, y, w, h);
            return true;
        } catch {
            return false;
        } finally {
            URL.revokeObjectURL(url);
        }
    }
    const look = rowLook(cell.block, style);
    g.fillStyle = look.ground;
    g.fillRect(x, y, w, h);
    // Cutouts without a common scale stand alike and carry no bar.
    const unscaled = rows[0]?.unscaled === true;
    const barOwn = !unscaled && !cell.barOver;
    const strips = rowStripsOf(cell, rows, mm, barOwn);
    const geo = rowGeometry(rows, w, h, look, mm, barOwn ? cell.bar : 'off', strips);
    if (!geo) return true;
    const images = new Map();   // picture key -> {img, temporary}
    let ok = true;
    for (const r of rows) {
        const key = r.obj.key;
        if (images.has(key)) continue;
        const preview = await loadForDone(r.obj, r.obj.src);
        let use = preview.ready ? preview : null;
        if (r.obj.original && (!use || sporeImagePxPerUm(use.img, r) < geo.pxPerUm * .95)) {
            use = (await developedOnce(r.obj, await loadOnce(r.obj.original))) ?? use;
        }
        if (!use) ok = false;
        images.set(key, use);
    }
    /* THE EDGE FILLED (edgefill.js): a first pass on a canvas of no
       account asks for every patch the row needs, the page waits for them,
       and the real pass finds them all — a printed page draws once. */
    const imageOf = r => images.get(r.obj.key)?.img ?? null;
    drawSporeRow(document.createElement('canvas').getContext('2d'), rows, cell.block.sort, geo, imageOf, edgePatch);
    await edgeFillsDone();
    g.save();
    g.translate(x, y);
    drawSporeRow(g, rows, cell.block.sort, geo, imageOf, edgePatch);
    const b = unscaled ? null : cell.barOver ? rowBarOver(cell, geo, rows.length, mm)
        : barGeometry(cell, w, h, mm, 1 / geo.pxPerUm);
    // Bar, medium and label as the page draws them (plates.js, rowWords; plateparts.js, rowWriting).
    const { m, lab } = rowTexts(cell, w, h, mm, rows, b, strips);
    writeOnCanvas(g, cell, mm, rowWriting(g, cell, b, m, lab, look));
    g.restore();
    for (const e of images.values()) {
        if (e?.temporary) e.img.src = '';
    }
    return ok;
}

/* --- Words on the page ------------------------------------------------------ */
const fontOf = (run, px) => `${run.italic ? 'italic ' : ''}${run.bold ? 700 : 400} ${px}px ${PAGE_FONT}`;

/* THE DETAILS' SIGNS ON PAPER (plateparts.js, DETAIL_ICONS): each drawn
   from the page's own icon library into a picture, once per ink — black
   and white are all the words take — before the first cell is drawn, so
   drawRow stays a plain sequence of strokes. 1 em wide, .3 em of air. */
const ICON_EM = 1, ICON_AIR = .3;
const iconImages = new Map();
async function detailIconsReady() {
    const jobs = [];
    for (const name of new Set(Object.values(DETAIL_ICONS))) {
        for (const ink of ['#000000', '#ffffff']) {
            const key = name + ink;
            if (iconImages.has(key)) continue;
            // The library's own drawing, sized for the picture and inked — no second drawing here.
            const node = icon(name);
            node.setAttribute('width', '96');
            node.setAttribute('height', '96');
            node.setAttribute('color', ink);
            // Bold, as the page draws it (plates.css, .plate-detail-icon).
            node.setAttribute('stroke-width', '2.7');
            const markup = new XMLSerializer().serializeToString(node).replace(/currentColor/g, ink);
            const img = new Image();
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(markup);
            iconImages.set(key, img);
            jobs.push(img.decode().catch(() => {}));
        }
    }
    await Promise.all(jobs);
}

function rowWidth(g, row, px) {
    let w = 0;
    for (const run of row) {
        if (run.icon || run.swatch) { w += px * (ICON_EM + ICON_AIR); continue; }
        g.font = fontOf(run, px);
        w += g.measureText(run.s).width;
    }
    return w;
}

function drawRow(g, row, x, yMiddle, px) {
    let at = x;
    for (const run of row) {
        if (run.swatch) {
            // The spore print's colour, as the page draws it (plates.css, .plate-swatch).
            const ink = g.fillStyle;
            const s = px * .9;
            g.fillStyle = run.swatch;
            g.fillRect(at, yMiddle - s / 2, s, s);
            g.lineWidth = Math.max(1, px * .06);
            g.strokeStyle = 'rgba(0, 0, 0, .35)';
            g.strokeRect(at, yMiddle - s / 2, s, s);
            g.fillStyle = ink;
            at += px * (ICON_EM + ICON_AIR);
            continue;
        }
        if (run.icon) {
            const ink = String(g.fillStyle).toLowerCase() === '#ffffff' ? '#ffffff' : '#000000';
            const img = iconImages.get(run.icon + ink);
            if (img?.complete && img.naturalWidth) g.drawImage(img, at, yMiddle - px * ICON_EM * .55, px * ICON_EM, px * ICON_EM);
            at += px * (ICON_EM + ICON_AIR);
            continue;
        }
        g.font = fontOf(run, px);
        g.fillText(run.s, at, yMiddle);
        at += g.measureText(run.s).width;
    }
}

/** A details box or a values text lying on the cell, its ground behind it. */
function drawItem(g, item, { pics, values, style, mm }, box, inkFor) {
    const px = item.pt * PT_MM * mm;
    const lh = px * LINE_ITEM;
    const padX = item.ground === 'none' ? 0 : BOX_PAD_X * mm;
    const padY = item.ground === 'none' ? 0 : BOX_PAD_Y * mm;
    // No free text since 09.11.2026 — the editor and the server let none through (plates.js, adopt).
    let rows = item.kind === 'info' ? detailsRows(item, values)
        : item.kind === 'values' ? valuesRows(item, rowsFor(pics, item, style), pics)
            : item.kind === 'keying' ? keyingRows(item, values?.keyings) ?? [] : [];
    /* A keying way wraps where the cell ends, less the bar's margin — as
       the editor's max-width does (plates.js, layoutCell); a row's air goes
       before the first of its wrapped lines. */
    if (item.kind === 'keying') {
        g.font = `400 ${px}px ${PAGE_FONT}`;
        const width = Math.max(40, (1 - item.x) * box.w - EDGE_MM * mm) - 2 * padX;
        rows = rows.flatMap(row => wrap(g, row, px, width)
            .map((line, i) => (i === 0 && row.gap ? Object.assign(line, { gap: row.gap }) : line)));
    }
    if (rows.length === 0) return;
    const x = box.x + item.x * box.w, y = box.y + item.y * box.h;
    // A values text's names stand as a column beside its rows (valuesRows, lead).
    const leadW = Math.max(0, ...rows.map(r => (r.lead ? rowWidth(g, [{ s: r.lead }], px) : 0)));
    const w = leadW + Math.max(...rows.map(r => rowWidth(g, r, px))) + 2 * padX;
    // A values text's rows may carry air before them (valuesRows: gap in lines).
    const tops = [];
    let used = 0;
    for (const row of rows) {
        used += (row.gap ?? 0) * lh;
        tops.push(used);
        used += lh;
    }
    const h = used + 2 * padY;
    let ink;
    if (item.ground === 'none') {
        ink = inkFor(x, y, w, h);
    } else {
        // The ground may let the picture through (plates.js, opacityControl); the words keep their ink.
        g.save();
        g.globalAlpha = Math.min(100, Math.max(10, item.groundOpacity ?? 100)) / 100;
        g.fillStyle = item.ground === 'black' ? '#000' : '#fff';
        g.fillRect(x, y, w, h);
        g.restore();
        ink = item.ground === 'black' ? '#fff' : '#000';
    }
    g.fillStyle = ink;
    rows.forEach((row, i) => {
        if (row.lead) drawRow(g, [{ s: row.lead }], x + padX, y + padY + tops[i] + lh / 2, px);
        drawRow(g, row, x + padX + leadW, y + padY + tops[i] + lh / 2, px);
    });
}

/** A cell of details: its ground, its rows wrapped at the cell's width. */
function detailsTile(g, d, values, box, mm) {
    const dark = d.ground === 'black';
    g.fillStyle = dark ? '#000' : '#fff';
    g.fillRect(box.x, box.y, box.w, box.h);
    const px = d.pt * PT_MM * mm;
    const lh = px * LINE_TILE;
    const at = tileTextAt(d, box.w, box.h, mm);
    // A row's air (detailsRows, GAP_LINES) goes before the first of its wrapped lines.
    const lines = detailsRows(d, values).flatMap(row => wrap(g, row, px, at.width)
        .map((line, i) => (i === 0 && row.gap ? Object.assign(line, { gap: row.gap }) : line)));
    g.fillStyle = dark ? '#fff' : '#000';
    let top = 0;
    for (const row of lines) {
        top += (row.gap ?? 0) * lh;
        drawRow(g, row, box.x + at.left, box.y + at.top + top + lh / 2, px);
        top += lh;
    }
}

/** A row of runs broken into lines no wider than `width`, at the spaces. */
function wrap(g, row, px, width) {
    const lines = [[]];
    let used = 0;
    for (const run of row) {
        /* A sign or a colour square is one piece, never split — it had no
           words to split, and a details tile with signs broke on it. */
        if (run.icon || run.swatch) {
            const w = px * (ICON_EM + ICON_AIR);
            if (used + w > width && used > 0) {
                lines.push([]);
                used = 0;
            }
            lines[lines.length - 1].push(run);
            used += w;
            continue;
        }
        for (const word of run.s.split(/(\s+)/)) {
            if (word === '') continue;
            g.font = fontOf(run, px);
            const w = g.measureText(word).width;
            const space = /^\s+$/.test(word);
            if (used + w > width && used > 0 && !space) {
                lines.push([]);
                used = 0;
            }
            if (space && used === 0) continue;
            lines[lines.length - 1].push({ ...run, s: word });
            used += w;
        }
    }
    return lines;
}

/* --- Files -------------------------------------------------------------------- */

/**
 * The canvas as JPEG bytes, marked 300 dpi. The browser's encoder writes a
 * JFIF header without a density unit, and an image program then prints
 * the page at 72 dpi — four times A4. The header's unit and densities are
 * set where the encoder put them.
 */
export async function jpegBytes(canvas, dpi = DPI) {
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', .92));
    const bytes = new Uint8Array(await blob.arrayBuffer());
    const jfif = bytes[2] === 0xFF && bytes[3] === 0xE0
        && bytes[6] === 0x4A && bytes[7] === 0x46 && bytes[8] === 0x49 && bytes[9] === 0x46;
    if (jfif) {
        bytes[13] = 1;                              // dots per inch
        bytes[14] = dpi >> 8; bytes[15] = dpi & 255;
        bytes[16] = dpi >> 8; bytes[17] = dpi & 255;
    }
    return { bytes, w: canvas.width, h: canvas.height };
}

/** A PDF string that carries any letter: UTF-16 with its byte-order mark, as hex. */
function pdfText(s) {
    let hex = 'FEFF';
    for (let i = 0; i < s.length; i++) hex += s.charCodeAt(i).toString(16).padStart(4, '0').toUpperCase();
    return `<${hex}>`;
}

/**
 * The pages as one PDF — each JPEG filling an A4 page.
 * @param {{bytes: Uint8Array, w: number, h: number}[]} pages
 */
export function pdfBlob(pages, title) {
    const enc = new TextEncoder();
    const parts = [];
    const offsets = [];
    let length = 0;
    const push = (x) => {
        const b = typeof x === 'string' ? enc.encode(x) : x;
        parts.push(b);
        length += b.length;
    };
    const obj = (n, body) => {
        offsets[n] = length;
        push(`${n} 0 obj\n${body}\nendobj\n`);
    };
    // The header, and a comment of high bytes that tells a reader the file is binary.
    push('%PDF-1.4\n');
    push(new Uint8Array([0x25, 0xE2, 0xE3, 0xCF, 0xD3, 0x0A]));

    const now = new Date();
    const two = (n) => String(n).padStart(2, '0');
    const stamp = `${now.getFullYear()}${two(now.getMonth() + 1)}${two(now.getDate())}${two(now.getHours())}${two(now.getMinutes())}${two(now.getSeconds())}`;
    obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
    obj(2, `<< /Type /Pages /Kids [${pages.map((_, i) => `${4 + 3 * i} 0 R`).join(' ')}] /Count ${pages.length} >>`);
    obj(3, `<< /Title ${pdfText(title)} /Producer (YASAIMAPP) /CreationDate (D:${stamp}) >>`);
    const [pw, ph] = A4_PT;
    pages.forEach((p, i) => {
        const n = 4 + 3 * i;
        obj(n, `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pw} ${ph}] `
            + `/Resources << /XObject << /Im${i} ${n + 1} 0 R >> >> /Contents ${n + 2} 0 R >>`);
        offsets[n + 1] = length;
        push(`${n + 1} 0 obj\n<< /Type /XObject /Subtype /Image /Width ${p.w} /Height ${p.h} `
            + `/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${p.bytes.length} >>\nstream\n`);
        push(p.bytes);
        push('\nendstream\nendobj\n');
        const draw = `q ${pw} 0 0 ${ph} 0 0 cm /Im${i} Do Q`;
        obj(n + 2, `<< /Length ${draw.length} >>\nstream\n${draw}\nendstream`);
    });
    const count = 4 + 3 * pages.length;
    const xref = length;
    push(`xref\n0 ${count}\n0000000000 65535 f \n`);
    for (let n = 1; n < count; n++) push(`${String(offsets[n]).padStart(10, '0')} 00000 n \n`);
    push(`trailer\n<< /Size ${count} /Root 1 0 R /Info 3 0 R >>\nstartxref\n${xref}\n%%EOF\n`);
    return new Blob(parts, { type: 'application/pdf' });
}

/** A file name from the find: „Boletus edulis Y-3111 Tafel 2". */
export function fileBase(values, number) {
    const parts = [values.species, values.number, t('Tafel') + (number > 1 ? ' ' + number : '')]
        .filter(Boolean).join(' ');
    return parts.replace(/[\\/:*?"<>|]+/g, '-').replace(/\s+/g, ' ').trim();
}
