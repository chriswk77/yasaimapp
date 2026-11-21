/**
 * Tests the aligning of two renderings (assets/js/align.js).
 *
 *   node tests/align.mjs
 *
 * WHAT IS TESTED HERE, and why exactly that: the case of
 * 11.11.2026 — two renderings of one stack, one brighter and one darker,
 * cut a little differently by Helicon. So a made-up picture with texture
 * in it is moved by KNOWN amounts — shifted, scaled by a few per cent,
 * turned by a fraction of a degree, cut to another size — and darkened
 * the way a shorter exposure darkens (a curve, the brights clipped in the
 * other). What the aligning says must be what was done, to a fraction of
 * a pixel at the picture's corners; a picture of something else must not
 * be aligned at all.
 */

import { alignCoarse, alignFine, mapPoint, mapThen, mapInverse, mapFromPairs, refinePoint, projectiveFit, affineFit } from '../assets/js/align.js';

let failed = 0;
function check(b, what) {
    if (b) { console.log(`  ok    ${what}`); } else { failed++; console.log(`  FAILED: ${what}`); }
}

/** A field with texture at several scales — blobs, edges, grain — the same each run. */
function scene(W, H, seed) {
    let s = seed >>> 0;
    const rnd = () => ((s = (s * 1664525 + 1013904223) >>> 0) / 4294967296);
    const f = new Float32Array(W * H);
    const blobs = Array.from({ length: 260 }, () => [rnd() * W, rnd() * H, 4 + rnd() * 40, rnd() * 140 - 50]);
    for (let y = 0; y < H; y++) {
        for (let x = 0; x < W; x++) {
            let v = 110 + 40 * Math.sin(x / 37) * Math.cos(y / 53);
            for (const [bx, by, r, a] of blobs) {
                const d = (x - bx) ** 2 + (y - by) ** 2;
                if (d < 9 * r * r) v += a * Math.exp(-d / (r * r));
            }
            f[y * W + x] = v;
        }
    }
    for (let n = 0; n < f.length; n++) f[n] = Math.min(255, Math.max(0, f[n] + (rnd() - .5) * 6));
    return f;
}

/** The picture `src` (W × H) seen through the map `m` (target pixel → source pixel), bilinear, with a tone curve. */
function warp(src, W, H, w, h, m, tone) {
    const out = new Float32Array(w * h);
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const [sx, sy] = mapPoint(m, x, y);
            const x0 = Math.min(W - 2, Math.max(0, Math.floor(sx))), y0 = Math.min(H - 2, Math.max(0, Math.floor(sy)));
            const fx = Math.min(1, Math.max(0, sx - x0)), fy = Math.min(1, Math.max(0, sy - y0));
            const v = (src[y0 * W + x0] * (1 - fx) + src[y0 * W + x0 + 1] * fx) * (1 - fy)
                + (src[(y0 + 1) * W + x0] * (1 - fx) + src[(y0 + 1) * W + x0 + 1] * fx) * fy;
            out[y * w + x] = tone(v);
        }
    }
    return out;
}

/** How far apart two maps put the four corners of a w × h picture, at most. */
function cornerError(m1, m2, w, h) {
    let worst = 0;
    for (const [x, y] of [[0, 0], [w, 0], [0, h], [w, h]]) {
        const [a, b] = mapPoint(m1, x, y), [c, d] = mapPoint(m2, x, y);
        worst = Math.max(worst, Math.hypot(a - c, b - d));
    }
    return worst;
}

console.log('\nAligning two renderings\n');

const W = 900, H = 680;
const world = scene(W, H, 11);
// A: the brighter rendering, the world cut at (40, 30).
const wa = 800, ha = 600;
const A = { grey: warp(world, W, H, wa, ha, [1, 0, 40, 0, 1, 30], (v) => Math.min(255, v * 1.15 + 10)), w: wa, h: ha };
// B: darker (a curve), cut otherwise, 3 % larger in the picture and turned by 0.4°.
const k = 1 / 1.03, t = .4 * Math.PI / 180;
const toWorldB = [k * Math.cos(t), -k * Math.sin(t), 62, k * Math.sin(t), k * Math.cos(t), 21];
const wb = 790, hb = 610;
const B = { grey: warp(world, W, H, wb, hb, toWorldB, (v) => 255 * Math.pow(v / 255, 1.4) * .85), w: wb, h: hb };
// The truth, A pixel → B pixel: into the world, then out of it into B.
const toWorldA = [1, 0, 40, 0, 1, 30];
const det = toWorldB[0] * toWorldB[4] - toWorldB[1] * toWorldB[3];
const fromWorldB = [toWorldB[4] / det, -toWorldB[1] / det, 0, -toWorldB[3] / det, toWorldB[0] / det, 0];
fromWorldB[2] = -(fromWorldB[0] * toWorldB[2] + fromWorldB[1] * toWorldB[5]);
fromWorldB[5] = -(fromWorldB[3] * toWorldB[2] + fromWorldB[4] * toWorldB[5]);
const truth = [
    fromWorldB[0] * toWorldA[0], fromWorldB[1] * toWorldA[4], fromWorldB[0] * toWorldA[2] + fromWorldB[1] * toWorldA[5] + fromWorldB[2],
    fromWorldB[3] * toWorldA[0], fromWorldB[4] * toWorldA[4], fromWorldB[3] * toWorldA[2] + fromWorldB[4] * toWorldA[5] + fromWorldB[5],
];

const coarse = alignCoarse(A, B);
check(coarse !== null, `the coarse step finds the pair (${coarse?.inliers ?? 0} of ${coarse?.pairs ?? 0} pairs agree)`);
const coarseError = coarse ? cornerError(coarse.m, truth, wa, ha) : Infinity;
check(coarseError < 3, `the coarse step lies within 3 pixels at the corners (${coarseError.toFixed(2)})`);
const fine = coarse ? alignFine(A, B, coarse.m) : null;
const fineError = fine ? cornerError(fine.m, truth, wa, ha) : Infinity;
check(fine !== null && fineError < .35, `the fine step lies within a third of a pixel at the corners (${fineError.toFixed(3)}, ${fine?.points ?? 0} points, rms ${fine?.rms.toFixed(3)})`);

check(fine !== null && fine.m.length === 6, 'two renderings of one stack stay affine — the two more numbers are not taken for a few per cent');

/* A TILTED SHOT (13.11.2026): the camera moved a little between the two —
   the same world through a projective map, the corners up to a dozen
   pixels off what any affine map could reach. The fine step must take
   the projective map and bring the corners together. */
console.log('\nA tilted shot\n');
const toWorldC = [1.01, .012, 48, -.008, 1.0, 26, 3.2e-5, -2.1e-5];
const wc = 800, hc = 600;
const C = { grey: warp(world, W, H, wc, hc, toWorldC, (v) => 255 * Math.pow(v / 255, 1.25) * .9), w: wc, h: hc };
const truthC = mapThen(toWorldA, mapInverse(toWorldC));
const coarseC = alignCoarse(A, C);
check(coarseC !== null, `the coarse step finds the tilted pair (${coarseC?.inliers ?? 0} of ${coarseC?.pairs ?? 0} pairs agree)`);
const fineC = coarseC ? alignFine(A, C, coarseC.m, 6) : null;
const fineErrorC = fineC ? cornerError(fineC.m, truthC, wa, ha) : Infinity;
check(fineC !== null && fineC.m.length === 8, 'the fine step takes the projective map');
check(fineErrorC < .5, `and lies within half a pixel at the corners (${fineErrorC.toFixed(3)}, ${fineC?.points ?? 0} points, rms ${fineC?.rms.toFixed(3)})`);
// What an affine map could do at best, for the record: the tilt it cannot follow.
{
    const P = [], Q = [];
    for (let y = 0; y <= ha; y += 60) for (let x = 0; x <= wa; x += 80) { P.push([x, y]); Q.push(mapPoint(truthC, x, y)); }
    const exact = projectiveFit(P, Q);
    check(exact !== null && cornerError(exact, truthC, wa, ha) < 1e-6, 'projectiveFit finds a projective map from its own points exactly');
    const flat = affineFit(P, Q);
    const tilt = cornerError(flat, truthC, wa, ha);
    check(tilt > 4, `the best affine map misses the corners by ${tilt.toFixed(1)} pixels — that much tilt is in the test`);
}

/* „NACHJUSTIEREN": places named by hand. Clicks a few pixels off are
   measured to the true place; the pairs make the map they ask for. */
console.log('\nPlaces named by hand\n');
{
    const spots = [[150, 120], [640, 140], [600, 470], [170, 450], [400, 300]];
    const wrong = [1, 0, 9, 0, 1, -7];   // the map the layer had: shifted off, no turn, no tilt
    const clicks = spots.map(([x, y], i) => { const [u, v] = mapPoint(truthC, x, y); return [u + [3, -4, 2, -3, 4][i], v + [-2, 3, 4, -3, 1][i]]; });
    const measured = spots.map((sp, i) => refinePoint(A, C, wrong, sp, clicks[i]));
    const worst = Math.max(...measured.map((q, i) => (q ? Math.hypot(q[0] - mapPoint(truthC, ...spots[i])[0], q[1] - mapPoint(truthC, ...spots[i])[1]) : Infinity)));
    check(worst < .5, `a click a few pixels off is measured to the place, to half a pixel (${worst.toFixed(3)} at worst)`);
    const one = mapFromPairs(wrong, spots.slice(0, 1), measured.slice(0, 1));
    const at1 = mapPoint(one, ...spots[0]);
    check(Math.hypot(at1[0] - measured[0][0], at1[1] - measured[0][1]) < 1e-6 && Math.abs(one[0] - 1) < 1e-9 && Math.abs(one[1]) < 1e-9, 'one pair moves the layer, and only moves it');
    const two = mapFromPairs(wrong, spots.slice(0, 2), measured.slice(0, 2));
    check(spots.slice(0, 2).every((sp, i) => Math.hypot(mapPoint(two, ...sp)[0] - measured[i][0], mapPoint(two, ...sp)[1] - measured[i][1]) < 1e-6)
        && Math.abs(two[0] - two[4]) < 1e-9 && Math.abs(two[1] + two[3]) < 1e-9, 'two pairs move, turn and scale it — no shear');
    const three = mapFromPairs(wrong, spots.slice(0, 3), measured.slice(0, 3));
    check(spots.slice(0, 3).every((sp, i) => Math.hypot(mapPoint(three, ...sp)[0] - measured[i][0], mapPoint(three, ...sp)[1] - measured[i][1]) < 1e-6) && three.length === 6, 'three pairs: an affine map through all three');
    const five = mapFromPairs(wrong, spots, measured);
    const fiveError = cornerError(five, truthC, wa, ha);
    check(five.length === 8 && fiveError < 2.5, `five pairs: a projective map, the corners within 2.5 pixels (${fiveError.toFixed(2)})`);
    const after = alignFine(A, C, five, 4);
    const afterError = after ? cornerError(after.m, truthC, wa, ha) : Infinity;
    check(afterError < .5, `and the fine step takes it from there to half a pixel (${afterError.toFixed(3)})`);
    const line = mapFromPairs(wrong, [[100, 100], [200, 200], [300, 300], [400, 400]], [[110, 95], [210, 195], [310, 295], [410, 395]]);
    check(line !== null && line.every(Number.isFinite), 'four pairs on one line still give a map');
}

const other = { grey: warp(scene(W, H, 99), W, H, wb, hb, toWorldB, (v) => v), w: wb, h: hb };
check(alignCoarse(A, other) === null, 'a picture of something else is not aligned');

const same = alignCoarse(A, A);
check(same !== null && cornerError(same.m, [1, 0, 0, 0, 1, 0], wa, ha) < .5, 'a picture with itself: no move');

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
