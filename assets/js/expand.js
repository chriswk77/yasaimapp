/**
 * „Erweitern" — the border of a crop that reaches past the picture, filled
 * by the models (15.11.2026, after a day with a test page:
 * „Super, bau das in unser Zuschneiden, Drehen, Werkzeug ein").
 *
 * WHAT IT IS FOR. A picture turned by a few degrees loses its corners, and a
 * subject too near an edge wants more room on that side. Until now the crop
 * was pulled inside the picture; with „Erweitern" it may reach past it, and
 * what lies outside is invented: by the quick model at once, coarse, so the
 * cut can be judged — and by the strong one on request, which takes minutes.
 *
 * HOW A BORDER OF A 5000-PIXEL PICTURE IS FILLED BY A MODEL THAT SEES 512:
 *   stage 1   the whole crop small (short side 512). Squares of 512 are
 *             filled from nothing, as „Entfernen" does — the model sees the
 *             whole height of the picture and decides WHAT stands in the
 *             border.
 *   stage 2…  the crop twice as large each time. The border holds the stage
 *             before, enlarged; every square is noised up part of the way
 *             and denoised again (moebius.js, `from`) — the model works
 *             detail out and keeps the draft's shapes. „Freiheit beim
 *             Verfeinern" is how far it is noised up.
 * The crop is cut into cells of 384; each cell with something to fill gets
 * one square of 512 about it, of whose fill only the cell is kept — the rim
 * of 64 belongs to the neighbours, who see this cell's fill as picture when
 * their turn comes (nextTile). A lattice and not „the square with the most
 * hole next": that left slivers, and a square for each.
 *
 * WHAT WAS MEASURED ON THE TEST PAGE (14.11.2026, two of his pictures at
 * 5182 × 3886): corners of a turn by 15° and 30° fill well; a shift by 35 %
 * brings artificial shapes; more stages sharpen edges but make no real fine
 * structure; a blurred background comes cleaner from the quick model than
 * from the strong one, which likes to set a thing of its own into it. A
 * square takes about 20 seconds in Chrome and about 100 in Firefox.
 *
 * NO DETECTOR OF SHARP AND BLURRED. The test page had one, to hand blurred
 * parts to the quick model and spare the strong one work. It took bright,
 * dark and plain-coloured sharp places for blurred (14.11.2026:
 * „So ein automatischer Scharf-Unscharf-Filter, der eine KI steuert, ist
 * gefährlich") — it stayed on the test page.
 *
 * This module only computes. What a fill is in the settings, how it is
 * stored and drawn, stands in develop.js (geo.fill); the tool is
 * photowindow.js.
 */

import * as inpaint from './inpaint.js';
import { strongPlanes } from './inpaintstrong.js';
import { SIDE, RUNS, runsFor } from './moebius.js';

const PLANE = SIDE * SIDE;
/** The rim of a square that belongs to its neighbours, and how far a fill fades into the picture inside a square. */
const MARGIN = 64, FEATHER = 8;
/** How far the finished fill fades into the picture beside it, in its own pixels (expandImage). */
const RIM = 4;
/* A CELL WITH NEXT TO NOTHING TO FILL IS LEFT AS IT IS in the stages that
   work a draft out: the tip of a wedge, a pixel of rim. Its pixels hold
   the stage before, enlarged — a little softer, and a whole square of
   twelve steps saved for each. In the first stage every pixel must be
   filled, there is no draft under it. */
const LEAST = Math.round(.015 * (SIDE - 2 * MARGIN) ** 2);

/** The finenesses on offer — how many stages run; the words are the tool's. */
export const EXPAND_LEVELS = [1, 2, 3, 4];
export const EXPAND_LEVEL_DEFAULT = 2;
/** „Freiheit beim Verfeinern", in per cent: how far a later stage is noised up. */
export const EXPAND_STRENGTH = { min: 30, max: 90, step: 5, default: 60 };

const canvasOf = (w, h) => {
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    return c;
};

/**
 * What a fill is computed from: the picture as it stands (with its
 * retouching, any size) and the crop's view of it — geoView(d, NW, NH) of
 * develop.js: cw × ch in the picture's own pixels, the turn, the centre,
 * the mirror.
 */
export function expandJob(picture, view) {
    const pw = picture.width, ph = picture.height;
    /* HALVED COPIES for making small: a picture drawn at a sixth of its
       size in one step keeps every sixth pixel in some browsers and
       glitters; the smallest copy that is still at least as large as
       needed is taken instead. */
    const mips = [{ image: picture, k: 1 }];
    for (let k = .5; Math.min(pw, ph) * k >= SIDE / 2; k /= 2) {
        const before = mips.at(-1).image;
        const c = canvasOf(Math.max(1, Math.round(pw * k)), Math.max(1, Math.round(ph * k)));
        const ctx = c.getContext('2d');
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(before, 0, 0, c.width, c.height);
        mips.push({ image: c, k });
    }
    /* THE CROP IS SCALED TO THE STAGE PER DIRECTION, before the picture is
       laid in: a stage's height is a rounded number, and one scale for both
       left the picture a fraction of a pixel short of the crop's edge — a
       row of „hole" one pixel high along the whole width, and a square for
       every 384 pixels of it (found on the test page). */
    const place = (ctx, w, h, draw) => {
        ctx.save();
        ctx.scale(w / view.cw, h / view.ch);
        ctx.translate(view.cw / 2, view.ch / 2);
        ctx.rotate(view.R * Math.PI / 180);
        ctx.scale(view.fx, view.fy);
        ctx.translate(-view.cx, -view.cy);
        draw(ctx);
        ctx.restore();
    };
    return {
        view,
        /** The picture into a stage of w × h. */
        picture(ctx, w, h) {
            // Stage pixels per pixel of `picture`.
            const need = w / view.cw * view.W / pw;
            let m = mips[0];
            for (const x of mips) if (x.k >= need) m = x;
            ctx.imageSmoothingQuality = 'high';
            place(ctx, w, h, (c) => c.drawImage(m.image, 0, 0, view.W, view.H));
        },
        /** 1 where a stage of w × h shows no picture, and how many such pixels there are. */
        hole(w, h) {
            const c = canvasOf(w, h), ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.fillStyle = '#fff';
            place(ctx, w, h, (x) => x.fillRect(0, 0, view.W, view.H));
            const a = ctx.getImageData(0, 0, w, h).data, hole = new Uint8Array(w * h);
            let n = 0;
            // Not < 255: a picture lying on the crop's edge exactly may come a rounding short of it.
            for (let p = 0; p < hole.length; p++) if (a[p * 4 + 3] < 250) { hole[p] = 1; n++; }
            c.width = 0;
            return { hole, n };
        },
        /** The size of stage L: the crop with its short side at 512 · 2^(L−1), never larger than the crop itself. */
        size(L) {
            const k = Math.min(1, SIDE * 2 ** (L - 1) / Math.min(view.cw, view.ch));
            return { w: Math.max(SIDE, Math.round(view.cw * k)), h: Math.max(SIDE, Math.round(view.ch * k)), k };
        },
        /** How many stages a wish for `want` comes to — none beyond the crop's own size. */
        levels(want) {
            let L = 1;
            while (L < want && this.size(L).k < 1) L++;
            return L;
        },
        free() {
            for (const m of mips.slice(1)) m.image.width = 0;
        },
    };
}

function cellsOf(n) {
    const CELL = SIDE - 2 * MARGIN;
    // A stage no larger than one square in a direction is one cell there.
    if (n <= SIDE) return [{ from: 0, to: n, at: 0 }];
    const out = [];
    for (let v = 0; v < n; v += CELL) out.push({ from: v, to: Math.min(n, v + CELL), at: Math.max(0, Math.min(n - SIDE, v - MARGIN)) });
    // The last cell reaches the edge; a thin rest joins the cell before it when the square still holds both.
    const last = out.at(-1), before = out.at(-2);
    if (before && last.to - before.from <= SIDE - MARGIN) {
        out.pop();
        before.to = n;
        before.at = Math.max(0, Math.min(n - SIDE, before.from - MARGIN));
    }
    return out;
}

/** The cells of a stage that still hold at least `least` pixels to fill, each with the square about it. */
function cellsLeft(hole, w, h, least, sums) {
    const I = sums.length === (w + 1) * (h + 1) ? sums : new Uint32Array((w + 1) * (h + 1));
    for (let y = 0; y < h; y++) {
        let row = 0;
        for (let x = 0; x < w; x++) {
            row += hole[y * w + x];
            I[(y + 1) * (w + 1) + x + 1] = I[y * (w + 1) + x + 1] + row;
        }
    }
    const sum = (x0, y0, x1, y1) => I[y1 * (w + 1) + x1] - I[y0 * (w + 1) + x1] - I[y1 * (w + 1) + x0] + I[y0 * (w + 1) + x0];
    const out = [];
    for (const cy of cellsOf(h)) {
        for (const cx of cellsOf(w)) {
            const inside = sum(cx.from, cy.from, cx.to, cy.to);
            if (inside < least) continue;
            out.push({ x: cx.at, y: cy.at, all: sum(cx.at, cy.at, cx.at + SIDE, cy.at + SIDE), keep: { l: cx.from, t: cy.from, r: cx.to, b: cy.to } });
        }
    }
    return out;
}

/** The next square: of the cells left, the one whose square holds the least hole — it sees the most picture. */
function nextTile(left) {
    let best = null;
    for (const c of left) if (!best || c.all < best.all) best = c;
    return best;
}

/**
 * One square: the model's answer laid into the stage — fully in the hole,
 * fading over a few pixels into what stands beside it —, and only inside
 * the square's own cell. The hole's pixels there are filled afterwards.
 */
async function tileRun(ctx, hole, w, tile, { quick, seed, from, strength, progress, halted }) {
    const image = ctx.getImageData(tile.x, tile.y, SIDE, SIDE), d = image.data;
    const pixels = new Float32Array(3 * PLANE), mask = new Uint8Array(PLANE), maskF = new Float32Array(PLANE);
    for (let y = 0; y < SIDE; y++) {
        for (let x = 0; x < SIDE; x++) {
            const p = y * SIDE + x;
            mask[p] = hole[(tile.y + y) * w + tile.x + x];
            maskF[p] = mask[p];
            for (let c = 0; c < 3; c++) pixels[c * PLANE + p] = d[p * 4 + c] / 255;
        }
    }
    // Both hand their buffers to a worker: `mask` is the copy that stays.
    // The quick model's fills stand in one line; one overtaken while it waited is not computed (inpaint.js, fillPlanes).
    const out = quick ? await inpaint.fillPlanes(pixels, maskF, halted).catch((error) => { throw error instanceof inpaint.Skipped ? new Halted() : error; })
        : await strongPlanes(pixels, maskF, seed, progress, { from, strength });
    // How far a kept pixel lies from the hole, up to FEATHER: there the fill fades into the picture.
    const dist = new Uint8Array(PLANE).fill(255);
    for (let p = 0; p < PLANE; p++) if (mask[p]) dist[p] = 0;
    for (let pass = 1; pass <= FEATHER; pass++) {
        for (let y = 0; y < SIDE; y++) {
            for (let x = 0; x < SIDE; x++) {
                const p = y * SIDE + x;
                if (dist[p] !== 255) continue;
                if ((x && dist[p - 1] === pass - 1) || (x + 1 < SIDE && dist[p + 1] === pass - 1)
                    || (y && dist[p - SIDE] === pass - 1) || (y + 1 < SIDE && dist[p + SIDE] === pass - 1)) dist[p] = pass;
            }
        }
    }
    const k = tile.keep;
    for (let y = Math.max(0, k.t - tile.y); y < Math.min(SIDE, k.b - tile.y); y++) {
        for (let x = Math.max(0, k.l - tile.x); x < Math.min(SIDE, k.r - tile.x); x++) {
            const p = y * SIDE + x;
            if (dist[p] === 255) continue;
            const weight = dist[p] === 0 ? 1 : 1 - dist[p] / (FEATHER + 1);
            for (let c = 0; c < 3; c++) d[p * 4 + c] = Math.round(weight * out[c * PLANE + p] + (1 - weight) * d[p * 4 + c]);
            d[p * 4 + 3] = 255;
            if (dist[p] === 0) hole[(tile.y + y) * w + tile.x + x] = 0;
        }
    }
    ctx.putImageData(image, tile.x, tile.y);
}

/* --- ONE AREA OF THE BORDER (15.11.2026) ---------------------
   „… es wäre besser, wenn man pro Fläche selbst entscheiden kann, welche
   Version man will." The quick model fills the whole border; the strong
   one is asked for area by area (develop.js, fillAreas, says what an area
   is and how it gets its number). `found` is that function's answer: the
   areas' numbers per pixel of the quick model's image. A stage has another
   size, so a pixel of it asks the nearest one there. */

/** Per column and row of a stage of w × h: the column, and the row's first index, in the areas' own image. */
function nearest(found, w, h) {
    const col = new Int32Array(w), row = new Int32Array(h);
    for (let x = 0; x < w; x++) col[x] = Math.min(found.w - 1, Math.floor((x + .5) * found.w / w));
    for (let y = 0; y < h; y++) row[y] = Math.min(found.h - 1, Math.floor((y + .5) * found.h / h)) * found.w;
    return { col, row };
}

/**
 * The job for ONE area: its hole is that area alone, and round it stands
 * the border as it is — `under`, the fill as drawn with this area taken
 * out (develop.js, fillWithout) —, so the model sees the other areas as
 * picture and continues them rather than a black corner.
 */
export function expandJobArea(job, found, n, under) {
    return {
        ...job,
        picture(ctx, w, h) {
            job.picture(ctx, w, h);
            if (!under) return;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(under, 0, 0, w, h);
        },
        hole(w, h) {
            const { hole } = job.hole(w, h), { col, row } = nearest(found, w, h);
            let count = 0;
            for (let y = 0; y < h; y++) {
                for (let x = 0; x < w; x++) {
                    const p = y * w + x;
                    if (!hole[p]) continue;
                    if (found.labels[row[y] + col[x]] === n) count++;
                    else hole[p] = 0;
                }
            }
            return { hole, n: count };
        },
    };
}

/**
 * What the strong model would take for EACH area, at EVERY fineness, before
 * anything runs — its squares per stage, up to the finest stage the picture
 * has. The first two stages are looked through pixel by pixel; the finer
 * ones are read off the second stage's hole, cell by cell at their own
 * lattice: the finest is twenty million pixels, too many to walk for a
 * number in a tooltip every time a frame comes to rest, and a hole is the
 * same shape at every size.
 *
 * @returns {Map<number, number[]>} per area: its squares in stage 1, 2, …
 */
export function expandCountAreas(job, found) {
    const levels = job.levels(EXPAND_LEVELS.at(-1));
    const out = new Map(found.areas.map((a) => [a.n, []]));
    const most = Math.max(0, ...found.areas.map((a) => a.n));
    let seen = null;
    for (let L = 1; L <= levels; L++) {
        const { w, h } = job.size(L);
        if (L <= 2) seen = { w, h, hole: job.hole(w, h).hole, ...nearest(found, w, h) };
        const xs = cellsOf(w), ys = cellsOf(h), cells = xs.length * ys.length;
        // The stage's cells, laid over the hole as it was looked through.
        const cellX = new Uint16Array(seen.w), cellY = new Uint16Array(seen.h);
        xs.forEach((c, i) => cellX.fill(i, Math.floor(c.from * seen.w / w), i + 1 < xs.length ? Math.floor(xs[i + 1].from * seen.w / w) : seen.w));
        ys.forEach((c, i) => cellY.fill(i, Math.floor(c.from * seen.h / h), i + 1 < ys.length ? Math.floor(ys[i + 1].from * seen.h / h) : seen.h));
        const counts = new Uint32Array((most + 1) * cells);
        for (let y = 0; y < seen.h; y++) {
            const at = cellY[y] * xs.length;
            for (let x = 0; x < seen.w; x++) {
                if (!seen.hole[y * seen.w + x]) continue;
                const n = found.labels[seen.row[y] + seen.col[x]];
                if (n) counts[n * cells + at + cellX[x]]++;
            }
        }
        const least = L > 1 ? Math.max(1, LEAST * (seen.w / w) * (seen.h / h)) : 1;
        for (const [n, stages] of out) {
            let tiles = 0;
            for (let c = 0; c < cells; c++) if (counts[n * cells + c] >= least) tiles++;
            stages.push(tiles);
        }
    }
    return out;
}

/** The steps a fill of `level` stages runs, from its squares per stage (expandCountAreas): the first stage
 *  from nothing, the later ones as far as the freedom says (`strength` 0 … 1). */
export function expandSteps(stages, level, strength) {
    let steps = (stages[0] ?? 0) * RUNS;
    for (let L = 2; L <= Math.min(level, stages.length); L++) steps += stages[L - 1] * runsFor(strength);
    return steps;
}

/** Thrown by expandFill when `halted()` said so. */
export class Halted extends Error {}

/**
 * Fill the border.
 *
 * @param {object} job    expandJob(…)
 * @param {object} opt
 *   quick     the quick model, one stage — the coarse fill
 *   levels    how many stages the strong model runs (job.levels(want))
 *   strength  0 … 1, „Freiheit beim Verfeinern"
 *   seed      the strong model's — another seed, another border
 *   pace      seconds a step, as measured before (for the first estimate)
 *   report    ({ stage, stages, tile, tiles, step, of, left, fresh, canvas, w, h, hole, pending }) — with every
 *             step, when a stage begins and after every square (`fresh`: the stage's pixels changed); `canvas` is
 *             the stage as it stands, `hole` the stage's hole as it was before anything was filled, `pending`
 *             the cells still to fill (l, t, r, b in the stage's pixels), `left` the seconds still to go
 *   halted    () => true stops after the square under way
 * @returns {Promise<{canvas, w, h, hole, tiles, pace}|null>} the last stage, opaque, and its hole — null where there is nothing to fill
 */
export async function expandFill(job, { quick = false, levels = 1, strength = .6, seed = 1, pace = 1.5, report = () => {}, halted = () => false } = {}) {
    if (quick) levels = 1;
    const stages = [];
    for (let L = 1; L <= levels; L++) {
        const { w, h } = job.size(L), { hole, n } = job.hole(w, h), least = L > 1 ? LEAST : 1;
        if (!n) return null;
        // The hole as it was before anything was filled: what the finished fill is cut by.
        stages.push({ L, w, h, hole, whole: hole.slice(), least, count: cellsLeft(hole, w, h, least, []).length,
            steps: quick ? 1 : L > 1 ? runsFor(strength) : RUNS });
    }
    /* THE WHOLE WAY IS KNOWN BEFORE IT STARTS (14.11.2026: „bin
       bei Kachel 6, Schritt 12 von 12. Aber der Fortschrittsbalken steht
       ungefähr bei einem Viertel"): every stage's squares are counted
       first, so the line says „Kachel 6 von 23" and how long it still takes
       at the speed measured so far. */
    const allTiles = stages.reduce((n, x) => n + x.count, 0), allSteps = stages.reduce((n, x) => n + x.count * x.steps, 0);
    let tiles = 0, stepsDone = 0, spent = 0, last = null;
    for (const stage of stages) {
        const { L, w, h, hole, whole, least, steps } = stage;
        // The sums of the hole, counted anew after every square — one buffer for the stage.
        const sums = new Uint32Array((w + 1) * (h + 1));
        const canvas = canvasOf(w, h), ctx = canvas.getContext('2d', { willReadFrequently: true });
        // The stage before, enlarged, under the picture: the draft this stage works out.
        if (last) {
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(last, 0, 0, w, h);
            last.width = 0;
        }
        job.picture(ctx, w, h);
        let left = cellsLeft(hole, w, h, least, sums);
        const say = (step, of, fresh = false) => {
            const now = stepsDone + Math.max(0, step - 1);
            report({ stage: L, stages: levels, tile: tiles, tiles: allTiles, step, of, left: (allSteps - now) * (stepsDone ? spent / stepsDone : pace),
                fresh, canvas, w, h, hole: whole, pending: left.map((c) => c.keep) });
        };
        say(0, steps, true);
        let count = 0;
        for (let tile = nextTile(left); tile; tile = nextTile(left)) {
            if (halted()) throw new Halted();
            count++;
            tiles++;
            const began = performance.now();
            await tileRun(ctx, hole, w, tile, { quick, seed: (seed * 1000 + L * 100 + count) >>> 0, from: L > 1, strength, halted,
                progress: (step, of) => say(step, of) });
            stepsDone += steps;
            spent += (performance.now() - began) / 1000;
            left = cellsLeft(hole, w, h, least, sums);
            say(steps, steps, true);
        }
        last = canvas;
        stage.hole = null;
    }
    if (halted()) throw new Halted();
    const end = stages.at(-1);
    return { canvas: last, w: end.w, h: end.h, hole: end.whole, tiles, pace: !quick && stepsDone ? spent / stepsDone : pace };
}

/**
 * Where a stage is FILL: a canvas opaque where the stage had its hole,
 * fading out over a few pixels into the picture beside it, transparent
 * everywhere else. Made once per stage — a run shows the stage after every
 * square (expandImage).
 */
export function expandMask(hole, w, h) {
    const alpha = new Uint8ClampedArray(w * h * 4);
    // Breadth first from the hole into the picture, RIM pixels deep.
    let front = [];
    const depth = new Uint8Array(w * h).fill(255);
    for (let p = 0; p < hole.length; p++) {
        if (!hole[p]) continue;
        depth[p] = 0;
        alpha[p * 4 + 3] = 255;
        const x = p % w;
        if ((x && !hole[p - 1]) || (x + 1 < w && !hole[p + 1]) || (p >= w && !hole[p - w]) || (p + w < hole.length && !hole[p + w])) front.push(p);
    }
    for (let d = 1; d <= RIM && front.length; d++) {
        const next = [];
        for (const p of front) {
            const x = p % w;
            for (const q of [x ? p - 1 : -1, x + 1 < w ? p + 1 : -1, p - w, p + w]) {
                if (q < 0 || q >= hole.length || depth[q] !== 255) continue;
                depth[q] = d;
                alpha[q * 4 + 3] = Math.round(255 * (1 - d / (RIM + 1)));
                next.push(q);
            }
        }
        front = next;
    }
    const mask = canvasOf(w, h);
    mask.getContext('2d').putImageData(new ImageData(alpha, w, h), 0, 0);
    return mask;
}

/**
 * A stage as a FILL: its pixels where the mask says so (expandMask). That
 * is what is stored and what is drawn — the picture first, this over it
 * (develop.js) —, so the seam is soft, the picture itself stays the
 * original's own pixels, and the patches stay small.
 */
export function expandImage(canvas, mask) {
    const out = canvasOf(mask.width, mask.height), ctx = out.getContext('2d');
    ctx.drawImage(canvas, 0, 0, out.width, out.height);
    ctx.globalCompositeOperation = 'destination-in';
    ctx.drawImage(mask, 0, 0);
    return out;
}

/**
 * A fill cut into the patches it is stored as: squares of 512 in rows and
 * columns from its top left corner, each a canvas — and only those that
 * hold anything. `id()` names a patch (photowindow.js, aiId).
 *
 * @returns {{ w: number, h: number, pieces: {id: string, c: number, r: number}[], patches: Map<string, HTMLCanvasElement> }}
 */
export function expandPieces(image, id) {
    const w = image.width, h = image.height, pieces = [], patches = new Map();
    for (let r = 0; r * SIDE < h; r++) {
        for (let c = 0; c * SIDE < w; c++) {
            const patch = canvasOf(SIDE, SIDE), ctx = patch.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(image, -c * SIDE, -r * SIDE);
            const a = ctx.getImageData(0, 0, SIDE, SIDE).data;
            let any = false;
            for (let p = 3; p < a.length; p += 4) if (a[p]) { any = true; break; }
            if (!any) { patch.width = 0; continue; }
            const name = id();
            pieces.push({ id: name, c, r });
            patches.set(name, patch);
        }
    }
    return { w, h, pieces, patches };
}
