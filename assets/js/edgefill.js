/**
 * The plate's rows past the picture's edge, filled (22.11.2026: „Einige
 * Reihen, Bildausschnitte, Messungen et cetera, stoßen ja an den Bildrand
 * … In der Tafel sähe es natürlich schöner aus, wenn diese hart
 * abgeschnittenen Bildkanten gefüllt wären … Aber nur für die Tafel", and
 * to the question switch or not: „Automatisch").
 *
 * A tile of a row (a spore, a part, a picture cutout) shows the picture
 * round its measure with some air. Where that air reaches past the
 * picture's edge there are no pixels, and the tile stood with a hard edge
 * and the row's ground behind it. Here the quick model of „Entfernen"
 * (inpaint.js, LaMa) fills exactly that part — never a pixel the picture
 * has: the fill is drawn clipped to what lies outside the picture, on top
 * of the picture drawn as always. The measured thing itself cannot reach
 * past the edge — it was clicked on the picture.
 *
 * ONLY ON THE PLATE. The photo window, the statistics' tiles and the
 * strip's small previews draw as before; nothing is stored, nothing goes
 * into the picture or its edits.
 *
 * IN THE PICTURE'S PIXELS, NOT THE TILE'S. A patch is a square of the
 * picture round the measure's middle, its side rounded up to a few fixed
 * sizes (SIDES) — so a tile that grows or shrinks with the plate's layout
 * finds the patch it had and nothing is computed again. One per picture
 * element (a preview and the original are different pixels), kept for as
 * long as that element lives (WeakMap) — the visit, not the device.
 *
 * AUTOMATIC, BUT NEVER A DOWNLOAD: whoever has the model on the device
 * (Offline-Daten, or once used „Entfernen") gets the fill; whoever has not
 * sees the rows as before — 95 MB are not fetched because a plate was
 * opened. Fills stand in the engine's one line (inpaint.fillPlanes), one
 * after the other.
 */

import * as inpaint from './inpaint.js';

/* The patch's side in picture pixels — the next of these at or above the
   circle the tile can reach. */
const SIDES = [64, 96, 128, 192, 256, 384, 512, 768, 1024, 1536, 2048];
/* The model's seam: the hole is widened this far into the picture (in the
   model's 512 square), so it blends into a band it painted itself; that
   band is never drawn — only what lies outside the picture is. */
const SEAM = 3;
// How far the fill reaches under the picture's edge, in picture pixels (patchMake).
const TUCK = 2;

const patches = new WeakMap();   // img -> Map(key -> {canvas, x, y, side} | {waiting: Set<fn>} | {none: true})
let model = null;                // null: not asked yet; a promise of whether the files lie here

inpaint.onRemove(() => { model = null; });

/** Whether the quick model lies on this device — asked once per visit. */
function modelHere() {
    model ??= inpaint.stored().then((s) => s.complete, () => false);
    return model;
}

/**
 * The fill for the square of `img` round (cx, cy) that a tile reaching
 * `reach` picture pixels needs — or null: nothing is missing there, the
 * model is not here, or it is still being computed (then `ready` runs once
 * it is done, so the caller draws again). Synchronous, for a drawing that
 * cannot wait; `edgeFillsDone` waits for all that were asked.
 *
 * @returns {?{canvas: HTMLCanvasElement, x: number, y: number, side: number}}
 */
export function edgePatch(img, cx, cy, reach, ready = null) {
    const nw = img.naturalWidth, nh = img.naturalHeight;
    if (!nw || !nh) return null;
    const want = 2 * reach;
    const side = SIDES.find((s) => s >= want) ?? Math.ceil(want);
    const x = Math.round(cx - side / 2), y = Math.round(cy - side / 2);
    // The whole square inside the picture: nothing to fill.
    if (x >= 0 && y >= 0 && x + side <= nw && y + side <= nh) return null;
    let mine = patches.get(img);
    if (!mine) patches.set(img, mine = new Map());
    const key = `${x},${y},${side}`;
    const there = mine.get(key);
    if (there?.canvas) return there;
    if (there?.none) return null;
    if (there?.waiting) {
        if (ready) there.waiting.add(ready);
        return null;
    }
    const entry = { waiting: new Set(ready ? [ready] : []) };
    mine.set(key, entry);
    entry.done = patchMake(img, x, y, side).then((patch) => {
        mine.set(key, patch ?? { none: true });
        if (patch) for (const fn of entry.waiting) fn();
    }, () => { mine.set(key, { none: true }); });
    pending.add(entry.done);
    entry.done.finally(() => pending.delete(entry.done));
    return null;
}

const pending = new Set();
/** Wait for every fill asked for so far — the printed page draws once they are in. */
export async function edgeFillsDone() {
    while (pending.size > 0) await Promise.all([...pending]);
}

/** Compute one patch: the square into the model, its answer drawn only where the picture is not. */
async function patchMake(img, x, y, side) {
    if (!await modelHere()) return null;
    const S = inpaint.SIDE;
    const nw = img.naturalWidth, nh = img.naturalHeight;
    const square = document.createElement('canvas');
    square.width = square.height = S;
    const sg = square.getContext('2d', { willReadFrequently: true });
    const k = S / side;
    // Only the part of the square that lies on the picture — the rest stays empty for the model.
    const ix0 = Math.max(0, x), iy0 = Math.max(0, y);
    const ix1 = Math.min(nw, x + side), iy1 = Math.min(nh, y + side);
    if (ix1 <= ix0 || iy1 <= iy0) return null;
    sg.drawImage(img, ix0, iy0, ix1 - ix0, iy1 - iy0, (ix0 - x) * k, (iy0 - y) * k, (ix1 - ix0) * k, (iy1 - iy0) * k);
    const image = sg.getImageData(0, 0, S, S);
    // The picture's rectangle in the model's square, shrunk by the seam.
    const px0 = (ix0 - x) * k + (ix0 > x ? SEAM : 0), py0 = (iy0 - y) * k + (iy0 > y ? SEAM : 0);
    const px1 = (ix1 - x) * k - (ix1 < x + side ? SEAM : 0), py1 = (iy1 - y) * k - (iy1 < y + side ? SEAM : 0);
    const hole = new Uint8Array(S * S);
    for (let j = 0; j < S; j++) {
        for (let i = 0; i < S; i++) {
            if (i + .5 < px0 || i + .5 > px1 || j + .5 < py0 || j + .5 > py1) hole[j * S + i] = 1;
        }
    }
    const filled = await inpaint.fill(image, hole);
    /* OPAQUE: the model gives colour only — where the square lay past the
       picture its pixels had none, and fill() keeps a pixel's own alpha. */
    for (let i = 3; i < filled.data.length; i += 4) filled.data[i] = 255;
    sg.putImageData(filled, 0, 0);
    /* Back at the picture's scale, and only outside the picture — but
       reaching TUCK picture pixels under its edge: the picture is drawn
       turned and smoothed, its last row of pixels half transparent, and
       with the fill ending exactly there the row's ground shone through
       both as a fine light line along the edge. Under the picture the
       fill is never seen; the picture covers it. */
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = side;
    const g = canvas.getContext('2d');
    const tuck = (a, b) => (a > b ? TUCK : 0);
    const ex0 = ix0 - x + tuck(ix0, x), ey0 = iy0 - y + tuck(iy0, y);
    const ex1 = ix1 - x - tuck(x + side, ix1), ey1 = iy1 - y - tuck(y + side, iy1);
    g.beginPath();
    g.rect(0, 0, side, side);
    if (ex1 > ex0 && ey1 > ey0) g.rect(ex0, ey0, ex1 - ex0, ey1 - ey0);
    g.clip('evenodd');
    g.imageSmoothingQuality = 'high';
    g.drawImage(square, 0, 0, side, side);
    return { canvas, x, y, side };
}
