/**
 * TWO PICTURES OF ONE SUBJECT LAID EXACTLY OVER EACH OTHER
 * (11.11.2026, for „Ebenen" — assets/js/blend.js).
 *
 * WHAT IT IS FOR. He renders a focus stack twice, once a little brighter
 * and once a little darker, to paint the darker one into the places the
 * brighter one burns out — a hand-made HDR. Helicon Focus crops every
 * rendering by itself, and a stack with a few more frames comes out cut a
 * little differently: the two renderings show the same subject, a few
 * pixels shifted, a little larger or smaller, at worst turned by a fraction
 * of a degree. „Beide natürlich scharf, niemals aus der Hand."
 *
 * HOW, in the textbook's four steps and in our own code — no library is
 * fetched for it (the project writes its own):
 *   1. CORNERS in each picture (Harris): places where the picture changes
 *      in two directions at once, spread over the whole picture by a grid,
 *      so one busy corner does not take every point.
 *   2. A FINGERPRINT for each: the 8 × 8 neighbourhood around it, made
 *      alike in mean and spread — two renderings at different exposure
 *      then look alike where they show the same thing. The pictures are
 *      first EQUALIZED (each grey value replaced by its rank): exposure
 *      turns grey values monotonically, and ranks undo that.
 *   3. PAIRS, each the other's best and clearly better than the second
 *      best, and among them the CONSENSUS (RANSAC): two pairs say how far
 *      the picture is shifted, turned and scaled; the guess most pairs
 *      agree with wins, and the pairs that disagree are dropped — a dew
 *      drop that sparkles in one rendering only.
 *   4. FINE MEASURING at twice the size: every point looked for again in
 *      a small window around where the guess puts it, to a fraction of a
 *      pixel, and an AFFINE map (shift, turn, scale, and a slight shear
 *      should a rendering be stretched) fitted to all of them by least
 *      squares, dropping what still does not fit.
 *
 * Everything here works on grey pictures as plain arrays — nothing of the
 * page — so tests/align.mjs can hold it to a picture moved by known
 * amounts.
 *
 * COORDINATES are pixel indices of the arrays handed in; a map
 * [a, b, c, d, e, f] takes (x, y) of the first picture to
 * (a·x + b·y + c, d·x + e·y + f) of the second.
 *
 * A MAP MAY BE PROJECTIVE (13.11.2026: „automatisch geht auch
 * schon die perspektivische Anpassung"): EIGHT numbers, the two more the
 * denominator — (x, y) goes to the affine place divided by g·x + h·y + 1.
 * Two shots of one subject from a camera that moved a little between
 * them differ by exactly that (as far as the subject is flat or far), and
 * no shift, turn or stretch brings their corners together. The fine step
 * measures both kinds and TAKES THE PROJECTIVE ONE ONLY WHERE IT FITS
 * CLEARLY BETTER: two more numbers always fit a little better, and on two
 * renderings of one stack — where the truth is affine — they would only
 * follow the noise into the corners.
 *
 * „NACHJUSTIEREN" (the same day) replaces aligning by hand: he names
 * places that are the same in both pictures (refinePoint measures each
 * to a fraction of a pixel around his click), mapFromPairs makes the map
 * they ask for, and the fine step takes it from there — as a guess that
 * is right where the consensus was not.
 */

/** A picture's grey values (0 … 255) from RGBA bytes. */
export function greyOf(rgba, w, h) {
    const g = new Float32Array(w * h);
    for (let n = 0, i = 0; n < g.length; n++, i += 4) g[n] = .299 * rgba[i] + .587 * rgba[i + 1] + .114 * rgba[i + 2];
    return g;
}

/** Each grey value replaced by its rank in the picture, 0 … 1 — the same subject at two exposures comes out alike. */
function equalize(g) {
    const bins = new Float64Array(1024);
    for (let n = 0; n < g.length; n++) bins[Math.min(1023, Math.max(0, (g[n] * 4) | 0))]++;
    let run = 0;
    for (let i = 0; i < 1024; i++) {
        const c = bins[i];
        bins[i] = (run + c / 2) / g.length;
        run += c;
    }
    const out = new Float32Array(g.length);
    for (let n = 0; n < g.length; n++) out[n] = bins[Math.min(1023, Math.max(0, (g[n] * 4) | 0))];
    return out;
}

/** A box blur of radius r, rows then columns, into a new array. */
function blurred(f, w, h, r) {
    const out = Float32Array.from(f);
    if (r < 1) return out;
    const tmp = new Float32Array(Math.max(w, h));
    const pass = (start, step, n) => {
        let sum = 0;
        for (let i = -r; i <= r; i++) sum += out[start + Math.min(n - 1, Math.max(0, i)) * step];
        for (let i = 0; i < n; i++) {
            tmp[i] = sum / (2 * r + 1);
            sum += out[start + Math.min(n - 1, i + r + 1) * step] - out[start + Math.max(0, i - r) * step];
        }
        for (let i = 0; i < n; i++) out[start + i * step] = tmp[i];
    };
    for (let y = 0; y < h; y++) pass(y * w, 1, w);
    for (let x = 0; x < w; x++) pass(x, w, h);
    return out;
}

/**
 * Harris corners, at most `perCell` of the strongest in each cell of a
 * `cols` × `rows` grid, and none within `border` of the edge.
 * @returns {Array<[number, number]>}
 */
function corners(g, w, h, { cols = 16, rows = 12, perCell = 4, border = 14 } = {}) {
    const s = blurred(g, w, h, 1);
    const xx = new Float32Array(w * h), yy = new Float32Array(w * h), xy = new Float32Array(w * h);
    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const n = y * w + x;
            const gx = s[n + 1] - s[n - 1], gy = s[n + w] - s[n - w];
            xx[n] = gx * gx; yy[n] = gy * gy; xy[n] = gx * gy;
        }
    }
    const a = blurred(xx, w, h, 2), b = blurred(yy, w, h, 2), c = blurred(xy, w, h, 2);
    const R = new Float32Array(w * h);
    for (let n = 0; n < R.length; n++) {
        const tr = a[n] + b[n];
        R[n] = a[n] * b[n] - c[n] * c[n] - .04 * tr * tr;
    }
    const cells = Array.from({ length: cols * rows }, () => []);
    const NMS = 4;
    for (let y = border; y < h - border; y++) {
        for (let x = border; x < w - border; x++) {
            const n = y * w + x, v = R[n];
            if (v <= 0) continue;
            let top = true;
            for (let dy = -NMS; dy <= NMS && top; dy++) {
                for (let dx = -NMS; dx <= NMS; dx++) {
                    if ((dx || dy) && R[n + dy * w + dx] > v) { top = false; break; }
                }
            }
            if (!top) continue;
            const cell = Math.min(rows - 1, (y * rows / h) | 0) * cols + Math.min(cols - 1, (x * cols / w) | 0);
            cells[cell].push([v, x, y]);
        }
    }
    // Only corners that stand out against the picture's own: a flat sky's noise is no corner.
    let top = 0;
    for (const list of cells) for (const [v] of list) top = Math.max(top, v);
    if (!top) return [];
    const floor = top * 1e-4;
    const out = [];
    for (const list of cells) {
        list.sort((p, q) => q[0] - p[0]);
        for (const [v, x, y] of list.slice(0, perCell)) if (v > floor) out.push([x, y]);
    }
    return out;
}

/** The fingerprint: 8 × 8 samples three pixels apart, mean 0 and length 1. */
const SIDE = 8, STEP = 3;
function describe(s, w, h, pts) {
    const out = new Float32Array(pts.length * SIDE * SIDE);
    pts.forEach(([x, y], k) => {
        const o = k * SIDE * SIDE;
        let mean = 0;
        for (let j = 0; j < SIDE; j++) {
            for (let i = 0; i < SIDE; i++) {
                const sx = Math.round(x + (i - (SIDE - 1) / 2) * STEP), sy = Math.round(y + (j - (SIDE - 1) / 2) * STEP);
                const v = s[Math.min(h - 1, Math.max(0, sy)) * w + Math.min(w - 1, Math.max(0, sx))];
                out[o + j * SIDE + i] = v;
                mean += v;
            }
        }
        mean /= SIDE * SIDE;
        let len = 0;
        for (let i = 0; i < SIDE * SIDE; i++) { out[o + i] -= mean; len += out[o + i] * out[o + i]; }
        len = Math.sqrt(len) || 1;
        for (let i = 0; i < SIDE * SIDE; i++) out[o + i] /= len;
    });
    return out;
}

/** Pairs: each the other's best, and clearly better than its second best. */
function pairs(da, na, db, nb) {
    const D = SIDE * SIDE;
    const bestA = new Int32Array(na).fill(-1), scoreA = new Float32Array(na).fill(-2), secondA = new Float32Array(na).fill(-2);
    const bestB = new Int32Array(nb).fill(-1), scoreB = new Float32Array(nb).fill(-2);
    for (let i = 0; i < na; i++) {
        for (let j = 0; j < nb; j++) {
            let dot = 0;
            for (let k = 0; k < D; k++) dot += da[i * D + k] * db[j * D + k];
            if (dot > scoreA[i]) { secondA[i] = scoreA[i]; scoreA[i] = dot; bestA[i] = j; } else if (dot > secondA[i]) secondA[i] = dot;
            if (dot > scoreB[j]) { scoreB[j] = dot; bestB[j] = i; }
        }
    }
    const out = [];
    for (let i = 0; i < na; i++) {
        const j = bestA[i];
        if (j < 0 || bestB[j] !== i || scoreA[i] < .6) continue;
        // As distances between unit vectors: the best clearly nearer than the second.
        const d1 = Math.sqrt(Math.max(0, 2 - 2 * scoreA[i])), d2 = Math.sqrt(Math.max(0, 2 - 2 * secondA[i]));
        if (d1 < .85 * d2) out.push([i, j]);
    }
    return out;
}

/** A small generator of the same numbers each time — the same pictures align the same way. */
function random(seed) {
    let s = seed >>> 0;
    return () => {
        s = (s * 1664525 + 1013904223) >>> 0;
        return s / 4294967296;
    };
}

/** The affine map that fits the pairs best (least squares), or null where they say too little. */
export function affineFit(P, Q) {
    if (P.length < 3) return null;
    // Normal equations for a·x + b·y + c, once for each coordinate.
    let sxx = 0, sxy = 0, sx = 0, syy = 0, sy = 0, n = 0;
    let ux = 0, uy = 0, u = 0, vx = 0, vy = 0, v = 0;
    for (let k = 0; k < P.length; k++) {
        const [x, y] = P[k], [qx, qy] = Q[k];
        sxx += x * x; sxy += x * y; sx += x; syy += y * y; sy += y; n++;
        ux += qx * x; uy += qx * y; u += qx;
        vx += qy * x; vy += qy * y; v += qy;
    }
    const M = [[sxx, sxy, sx], [sxy, syy, sy], [sx, sy, n]];
    const r1 = solve3(M, [ux, uy, u]), r2 = solve3(M, [vx, vy, v]);
    return r1 && r2 ? [...r1, ...r2] : null;
}

function solve3(M, r) {
    const [[a, b, c], [d, e, f], [g, h, i]] = M;
    const det = a * (e * i - f * h) - b * (d * i - f * g) + c * (d * h - e * g);
    if (Math.abs(det) < 1e-9) return null;
    const x = (r[0] * (e * i - f * h) - b * (r[1] * i - f * r[2]) + c * (r[1] * h - e * r[2])) / det;
    const y = (a * (r[1] * i - f * r[2]) - r[0] * (d * i - f * g) + c * (d * r[2] - r[1] * g)) / det;
    const z = (a * (e * r[2] - r[1] * h) - b * (d * r[2] - r[1] * g) + r[0] * (d * h - e * g)) / det;
    return [x, y, z];
}

/** Where a map puts a point — affine (six numbers) or projective (eight). */
export function mapPoint(m, x, y) {
    const u = m[0] * x + m[1] * y + m[2], v = m[3] * x + m[4] * y + m[5];
    if (m.length < 8) return [u, v];
    const w = m[6] * x + m[7] * y + 1;
    return [u / w, v / w];
}

/** A map as its 3 × 3 matrix (rows), and back: eight numbers, or six where the last row says nothing. */
export const mapMat = (m) => [[m[0], m[1], m[2]], [m[3], m[4], m[5]], [m[6] ?? 0, m[7] ?? 0, 1]];
export function matMap(M) {
    const k = M[2][2];
    if (!Number.isFinite(k) || Math.abs(k) < 1e-12) return null;
    const m = [M[0][0] / k, M[0][1] / k, M[0][2] / k, M[1][0] / k, M[1][1] / k, M[1][2] / k, M[2][0] / k, M[2][1] / k];
    if (!m.every(Number.isFinite)) return null;
    return Math.abs(m[6]) < 1e-12 && Math.abs(m[7]) < 1e-12 ? m.slice(0, 6) : m;
}
export function matMul(A, B) {
    const out = [[0, 0, 0], [0, 0, 0], [0, 0, 0]];
    for (let i = 0; i < 3; i++) for (let j = 0; j < 3; j++) out[i][j] = A[i][0] * B[0][j] + A[i][1] * B[1][j] + A[i][2] * B[2][j];
    return out;
}
export function matInv(M) {
    const [[a, b, c], [d, e, f], [g, h, i]] = M;
    const det = a * (e * i - f * h) - b * (d * i - f * g) + c * (d * h - e * g);
    if (Math.abs(det) < 1e-15) return null;
    return [
        [(e * i - f * h) / det, (c * h - b * i) / det, (b * f - c * e) / det],
        [(f * g - d * i) / det, (a * i - c * g) / det, (c * d - a * f) / det],
        [(d * h - e * g) / det, (b * g - a * h) / det, (a * e - b * d) / det],
    ];
}
/** Two maps one after the other: first `first`, then `then`. */
export const mapThen = (first, then) => matMap(matMul(mapMat(then), mapMat(first)));
/** A map turned round — where the second picture's points lie in the first. */
export function mapInverse(m) {
    const inv = matInv(mapMat(m));
    return inv ? matMap(inv) : null;
}

/** n equations in n unknowns, by elimination with the largest pivot; null where they do not decide. */
function solveN(M, r) {
    const n = r.length;
    const A = M.map((row, i) => [...row, r[i]]);
    for (let c = 0; c < n; c++) {
        let p = c;
        for (let i = c + 1; i < n; i++) if (Math.abs(A[i][c]) > Math.abs(A[p][c])) p = i;
        if (Math.abs(A[p][c]) < 1e-12) return null;
        [A[c], A[p]] = [A[p], A[c]];
        for (let i = c + 1; i < n; i++) {
            const k = A[i][c] / A[c][c];
            if (k === 0) continue;
            for (let j = c; j <= n; j++) A[i][j] -= k * A[c][j];
        }
    }
    const x = new Array(n).fill(0);
    for (let i = n - 1; i >= 0; i--) {
        let sum = A[i][n];
        for (let j = i + 1; j < n; j++) sum -= A[i][j] * x[j];
        x[i] = sum / A[i][i];
    }
    return x.every(Number.isFinite) ? x : null;
}

/**
 * THE PROJECTIVE MAP that fits the pairs best, or null where they say too
 * little (fewer than four, or all on one line).
 *
 * Each pair gives two equations that are linear in the eight numbers once
 * multiplied through by the denominator — which weighs a pair by its own
 * denominator. So it is solved a few times over, each time dividing by
 * the denominators of the round before: what is made small in the end is
 * the distance in pixels, as the affine fit makes it. BOTH POINT SETS ARE
 * FIRST MOVED TO THEIR MIDDLE AND SCALED TO ABOUT ONE: pixel numbers in
 * the thousands beside the 1 of the last row would leave the elimination
 * nothing to hold on to.
 */
export function projectiveFit(P, Q) {
    if (P.length < 4) return null;
    const norm = (pts) => {
        let cx = 0, cy = 0;
        for (const [x, y] of pts) { cx += x; cy += y; }
        cx /= pts.length; cy /= pts.length;
        let d = 0;
        for (const [x, y] of pts) d += Math.hypot(x - cx, y - cy);
        const k = d > 0 ? Math.SQRT2 * pts.length / d : 1;
        return { T: [[k, 0, -k * cx], [0, k, -k * cy], [0, 0, 1]], pts: pts.map(([x, y]) => [(x - cx) * k, (y - cy) * k]) };
    };
    const a = norm(P), b = norm(Q);
    let h = null;
    for (let round = 0; round < 4; round++) {
        const M = Array.from({ length: 8 }, () => new Array(8).fill(0)), r = new Array(8).fill(0);
        for (let k = 0; k < P.length; k++) {
            const [x, y] = a.pts[k], [u, v] = b.pts[k];
            const w = h ? 1 / Math.max(1e-6, Math.abs(h[6] * x + h[7] * y + 1)) : 1;
            const rows = [[x, y, 1, 0, 0, 0, -u * x, -u * y, u], [0, 0, 0, x, y, 1, -v * x, -v * y, v]];
            for (const row of rows) {
                for (let i = 0; i < 8; i++) {
                    const wi = w * w * row[i];
                    if (wi === 0) continue;
                    for (let j = 0; j < 8; j++) M[i][j] += wi * row[j];
                    r[i] += wi * row[8];
                }
            }
        }
        const next = solveN(M, r);
        if (!next) return h ? null : null;
        h = next;
    }
    const inv = matInv(b.T);
    return inv ? matMap(matMul(inv, matMul(mapMat(h), a.T))) : null;
}

/**
 * THE COARSE STEP — corners, fingerprints, pairs, consensus — on two grey
 * pictures of about a thousand pixels. Null where too few pairs agree:
 * the two pictures are not of the same thing, or not at this scale.
 *
 * @returns {?{ m: number[], inliers: number, pairs: number }}
 */
export function alignCoarse(A, B) {
    const ea = equalize(A.grey), eb = equalize(B.grey);
    const pa = corners(ea, A.w, A.h), pb = corners(eb, B.w, B.h);
    if (pa.length < 8 || pb.length < 8) return null;
    const sa = blurred(ea, A.w, A.h, 1), sb = blurred(eb, B.w, B.h, 1);
    const found = pairs(describe(sa, A.w, A.h, pa), pa.length, describe(sb, B.w, B.h, pb), pb.length);
    if (found.length < 6) return null;
    const P = found.map(([i]) => pa[i]), Q = found.map(([, j]) => pb[j]);
    // Consensus over shift, turn and scale: two pairs say all three.
    const rnd = random(7);
    const tol = Math.max(2, Math.max(A.w, A.h) / 400);
    let best = null, bestCount = 0;
    for (let it = 0; it < 3000; it++) {
        const i = (rnd() * P.length) | 0, j = (rnd() * P.length) | 0;
        if (i === j) continue;
        const px = P[j][0] - P[i][0], py = P[j][1] - P[i][1], qx = Q[j][0] - Q[i][0], qy = Q[j][1] - Q[i][1];
        const den = px * px + py * py;
        if (den < 100) continue;
        // (qx + i·qy) / (px + i·py) — the turn and the scale as one complex number.
        const re = (qx * px + qy * py) / den, im = (qy * px - qx * py) / den;
        const scale = Math.hypot(re, im);
        if (scale < .5 || scale > 2) continue;
        const m = [re, -im, Q[i][0] - (re * P[i][0] - im * P[i][1]), im, re, Q[i][1] - (im * P[i][0] + re * P[i][1])];
        let count = 0;
        for (let k = 0; k < P.length; k++) {
            const [x, y] = mapPoint(m, P[k][0], P[k][1]);
            if (Math.hypot(x - Q[k][0], y - Q[k][1]) < tol) count++;
        }
        if (count > bestCount) { bestCount = count; best = m; }
    }
    const count = (m) => {
        let c = 0;
        for (let k = 0; k < P.length; k++) {
            const [x, y] = mapPoint(m, P[k][0], P[k][1]);
            if (Math.hypot(x - Q[k][0], y - Q[k][1]) < tol) c++;
        }
        return c;
    };
    const agreeing = (m) => {
        const inP = [], inQ = [];
        for (let k = 0; k < P.length; k++) {
            const [x, y] = mapPoint(m, P[k][0], P[k][1]);
            if (Math.hypot(x - Q[k][0], y - Q[k][1]) < tol) { inP.push(P[k]); inQ.push(Q[k]); }
        }
        return [inP, inQ];
    };
    // A projective map that could be two shots of one subject: the denominator near one over the whole picture, no fold.
    const sane = (m) => m.length < 8 || (Math.abs(m[6]) * A.w + Math.abs(m[7]) * A.h < .5 && m[0] * m[4] - m[1] * m[3] > .2);
    /* A TILTED SHOT HAS NO CONSENSUS OVER SHIFT, TURN AND SCALE (13.11.2026):
       its pairs agree with such a map only in one part of the picture.
       Where fewer than six in ten agree, a second consensus is sought over
       projective maps — four pairs say one — and the one more pairs agree
       with wins. Tried only then: on two renderings of one stack the first
       consensus holds nearly every pair, and four pairs fit a map to any
       four points. */
    if (bestCount < found.length * .6 && P.length >= 12) {
        for (let it = 0; it < 2500; it++) {
            const pick = [0, 0, 0, 0].map(() => (rnd() * P.length) | 0);
            if (new Set(pick).size < 4) continue;
            const m = projectiveFit(pick.map((k) => P[k]), pick.map((k) => Q[k]));
            if (!m || !sane(m)) continue;
            const c = count(m);
            if (c > bestCount) { bestCount = c; best = m; }
        }
    }
    if (!best || bestCount < Math.max(8, found.length * .25)) return null;
    // Refitted on what agreed, twice — the second time with what the first fit agrees with.
    let m = best;
    for (let round = 0; round < 2; round++) {
        const [inP, inQ] = agreeing(m);
        m = (m.length === 8 ? projectiveFit(inP, inQ) : affineFit(inP, inQ)) ?? m;
        bestCount = inP.length;
    }
    // And once projective, kept where clearly more pairs agree with it — a slight tilt the first consensus rode out.
    if (m.length === 6 && bestCount >= 30) {
        let tilted = m;
        for (let round = 0; round < 3; round++) {
            const [inP, inQ] = agreeing(tilted);
            const next = projectiveFit(inP, inQ);
            if (!next || !sane(next)) { tilted = null; break; }
            tilted = next;
        }
        if (tilted && tilted.length === 8 && count(tilted) > bestCount * 1.15) {
            m = tilted;
            bestCount = count(tilted);
        }
    }
    return { m, inliers: bestCount, pairs: found.length };
}

/** A grey picture made ready for the fine measuring — equalized and smoothed once, kept on it. */
export function alignReady(X) {
    if (!X.e) X.e = blurred(equalize(X.grey), X.w, X.h, 1);
    return X;
}

/** A fit made robust: what lies off (three times the middle distance, half a pixel at least) is dropped and the fit made again. */
function robustFit(fitOf, P, Q, least) {
    let fit = fitOf(P, Q);
    if (!fit) return null;
    let keep = P.map((_, k) => k);
    const distances = (m, ks) => ks.map((k) => { const [x, y] = mapPoint(m, P[k][0], P[k][1]); return Math.hypot(x - Q[k][0], y - Q[k][1]); });
    for (let round = 0; round < 3; round++) {
        const res = distances(fit, keep);
        const sorted = res.slice().sort((p, q) => p - q);
        const limit = Math.max(.5, 3 * sorted[(sorted.length / 2) | 0]);
        const next = keep.filter((_, i) => res[i] <= limit);
        if (next.length < least) break;
        keep = next;
        fit = fitOf(keep.map((k) => P[k]), keep.map((k) => Q[k])) ?? fit;
    }
    const res = distances(fit, keep);
    let sq = 0;
    for (const d of res) sq += d * d;
    // The middle distance over ALL points: what the two kinds of map are compared by.
    const all = distances(fit, P.map((_, k) => k)).sort((p, q) => p - q);
    return { m: fit, points: keep.length, rms: Math.sqrt(sq / keep.length), middle: all[(all.length / 2) | 0] };
}

/**
 * THE FINE STEP: the coarse map `m` (for these two pictures' pixels) made
 * exact. Every corner of A is looked for in B in a window of ±`reach`
 * pixels around where `m` puts it, by the likeness of a 15 × 15
 * neighbourhood, to a fraction of a pixel (a parabola through the best
 * and its neighbours). A map is fitted to the points found, and the ones
 * that still lie off are dropped and the fit made again.
 *
 * AFFINE OR PROJECTIVE (13.11.2026): both are fitted, and the projective
 * one is taken only where it has enough points spread over the picture
 * AND brings the middle distance down by a fifth — on an affine truth its
 * two more numbers gain a few per cent at most (measured in
 * tests/align.mjs), on a tilted one a multiple.
 *
 * TWICE: the first round measures around the coarse map, which under a
 * tilt lies off toward the corners and loses the points there; the second
 * measures around the first round's map and finds them.
 *
 * @returns {?{ m: number[], points: number, rms: number, corners: number }}  `corners`: how many places were looked for
 */
export function alignFine(A, B, m, reach = 4) {
    alignReady(A);
    alignReady(B);
    const ea = A.e, eb = B.e;
    const R = 7, S = 2 * R + 1;
    const pa = A.corners ?? (A.corners = corners(ea, A.w, A.h, { cols: 24, rows: 18, perCell: 3, border: R + 2 }));
    const patch = new Float32Array(S * S);
    const measure = (guess, reach) => {
        const P = [], Q = [];
        const side = 2 * reach + 1;
        const score = new Float32Array(side * side);
        for (const [x, y] of pa) {
            let mean = 0;
            for (let j = 0; j < S; j++) for (let i = 0; i < S; i++) { const v = ea[(y + j - R) * A.w + x + i - R]; patch[j * S + i] = v; mean += v; }
            mean /= S * S;
            let len = 0;
            for (let k = 0; k < S * S; k++) { patch[k] -= mean; len += patch[k] * patch[k]; }
            len = Math.sqrt(len);
            if (len < 1e-3) continue;
            const [fx, fy] = mapPoint(guess, x, y);
            if (!Number.isFinite(fx) || !Number.isFinite(fy)) continue;
            const cx = Math.round(fx), cy = Math.round(fy);
            if (cx - reach - R - 1 < 0 || cy - reach - R - 1 < 0 || cx + reach + R + 1 >= B.w || cy + reach + R + 1 >= B.h) continue;
            let best = -2, bi = 0, bj = 0;
            for (let oy = -reach; oy <= reach; oy++) {
                for (let ox = -reach; ox <= reach; ox++) {
                    let sum = 0, dot = 0, sq = 0;
                    for (let j = 0; j < S; j++) {
                        const row = (cy + oy + j - R) * B.w + cx + ox - R;
                        for (let i = 0; i < S; i++) { const v = eb[row + i]; sum += v; sq += v * v; dot += v * patch[j * S + i]; }
                    }
                    const varB = sq - sum * sum / (S * S);
                    const ncc = varB > 1e-9 ? dot / (len * Math.sqrt(varB)) : -1;
                    score[(oy + reach) * side + ox + reach] = ncc;
                    if (ncc > best) { best = ncc; bi = ox; bj = oy; }
                }
            }
            // Only a clear peak inside the window: at its rim the true place may lie beyond.
            if (best < .8 || Math.abs(bi) === reach || Math.abs(bj) === reach) continue;
            const at = (i, j) => score[(j + reach) * side + i + reach];
            const sub = (l, c, r) => { const den = l - 2 * c + r; return den < 0 ? Math.max(-.5, Math.min(.5, (l - r) / (2 * den))) : 0; };
            P.push([x, y]);
            Q.push([cx + bi + sub(at(bi - 1, bj), best, at(bi + 1, bj)), cy + bj + sub(at(bi, bj - 1), best, at(bi, bj + 1))]);
        }
        return { P, Q };
    };
    const fitted = ({ P, Q }) => {
        if (P.length < 12) return null;
        const affine = robustFit(affineFit, P, Q, 12);
        if (!affine) return null;
        // Enough points, and spread over more than half the picture each way — or the tilt is read off a corner.
        let x0 = Infinity, x1 = -Infinity, y0 = Infinity, y1 = -Infinity;
        for (const [x, y] of P) { x0 = Math.min(x0, x); x1 = Math.max(x1, x); y0 = Math.min(y0, y); y1 = Math.max(y1, y); }
        const spread = x1 - x0 > A.w * .5 && y1 - y0 > A.h * .5;
        const tilted = P.length >= 40 && spread ? robustFit(projectiveFit, P, Q, 30) : null;
        /* Sane: a denominator near one over the whole picture, and most of
           the points with it. NOT „as many points as the affine fit kept":
           under a strong tilt the affine fit lies off everywhere, its
           middle distance is large, and so it drops nothing at all. */
        const sane = tilted && tilted.m.length === 8
            && Math.abs(tilted.m[6]) * A.w + Math.abs(tilted.m[7]) * A.h < .5
            && tilted.points >= P.length * .5;
        return sane && tilted.middle < .8 * affine.middle ? tilted : affine;
    };
    const first = fitted(measure(m, reach));
    if (!first) return null;
    // Around a first map that still lies off by more than a third of a pixel, the second round looks as far as the first.
    const second = fitted(measure(first.m, first.middle > .35 ? reach : Math.max(3, Math.ceil(reach / 2))));
    const out = second && second.points >= first.points * .9 ? second : first;
    return { m: out.m, points: out.points, rms: out.rms, corners: pa.length };
}

/**
 * ONE PLACE NAMED BY HAND, MEASURED („Nachjustieren"): `p` in A and the
 * click `q` in B that is meant to be the same place. The neighbourhood of
 * p (21 × 21) is looked for in B around q — B read through how the map
 * `m` lies there (turned, scaled as it is), so a layer of another scale
 * is compared like with like — and the best place to a fraction of a
 * pixel comes back. Null where nothing clear is found: then the click
 * itself is the best there is.
 */
export function refinePoint(A, B, m, p, q, reach = 10) {
    alignReady(A);
    alignReady(B);
    // 21 × 21 and not larger: measured with larger ones (33, 49), a map still wrong in turn and tilt matched worse, not better.
    const R = 10, S = 2 * R + 1;
    const sample = (e, w, h, x, y) => {
        if (x < 0 || y < 0 || x > w - 1.001 || y > h - 1.001) return NaN;
        const x0 = Math.floor(x), y0 = Math.floor(y), fx = x - x0, fy = y - y0, n = y0 * w + x0;
        return (e[n] * (1 - fx) + e[n + 1] * fx) * (1 - fy) + (e[n + w] * (1 - fx) + e[n + w + 1] * fx) * fy;
    };
    const patch = new Float32Array(S * S);
    let mean = 0;
    for (let j = 0; j < S; j++) {
        for (let i = 0; i < S; i++) {
            const v = sample(A.e, A.w, A.h, p[0] + i - R, p[1] + j - R);
            if (Number.isNaN(v)) return null;
            patch[j * S + i] = v;
            mean += v;
        }
    }
    mean /= S * S;
    let len = 0;
    for (let k = 0; k < S * S; k++) { patch[k] -= mean; len += patch[k] * patch[k]; }
    len = Math.sqrt(len);
    if (len < 1e-3) return null;
    // How the map lies at p: one step right and one step down in A, in B's pixels.
    const o = mapPoint(m, p[0], p[1]), ex = mapPoint(m, p[0] + 1, p[1]), ey = mapPoint(m, p[0], p[1] + 1);
    const j11 = ex[0] - o[0], j21 = ex[1] - o[1], j12 = ey[0] - o[0], j22 = ey[1] - o[1];
    const side = 2 * reach + 1;
    const score = new Float32Array(side * side).fill(-2);
    let best = -2, bi = 0, bj = 0;
    for (let oy = -reach; oy <= reach; oy++) {
        for (let ox = -reach; ox <= reach; ox++) {
            let sum = 0, dot = 0, sq = 0, off = false;
            for (let j = 0; j < S && !off; j++) {
                for (let i = 0; i < S; i++) {
                    const dx = i - R, dy = j - R;
                    const v = sample(B.e, B.w, B.h, q[0] + ox + j11 * dx + j12 * dy, q[1] + oy + j21 * dx + j22 * dy);
                    if (Number.isNaN(v)) { off = true; break; }
                    sum += v; sq += v * v; dot += v * patch[j * S + i];
                }
            }
            if (off) continue;
            const varB = sq - sum * sum / (S * S);
            const ncc = varB > 1e-9 ? dot / (len * Math.sqrt(varB)) : -1;
            score[(oy + reach) * side + ox + reach] = ncc;
            if (ncc > best) { best = ncc; bi = ox; bj = oy; }
        }
    }
    if (best < .6 || Math.abs(bi) === reach || Math.abs(bj) === reach) return null;
    const at = (i, j) => score[(j + reach) * side + i + reach];
    const sub = (l, c, r) => { const den = l - 2 * c + r; return l > -2 && r > -2 && den < 0 ? Math.max(-.5, Math.min(.5, (l - r) / (2 * den))) : 0; };
    return [q[0] + bi + sub(at(bi - 1, bj), best, at(bi + 1, bj)), q[1] + bj + sub(at(bi, bj - 1), best, at(bi, bj + 1))];
}

/**
 * THE MAP THE NAMED PAIRS ASK FOR („Nachjustieren"), laid over the map
 * the layer had (`base`): the pairs are the truth, and what they do not
 * say stays as it was.
 *   one pair      the layer is moved so that this place fits;
 *   two           moved, turned and scaled;
 *   three         and sheared — an affine map through all three;
 *   four and more a projective map through them (by least squares),
 *                 unless they lie too much on one line — then as three.
 * P in A's pixels, Q in B's; pixels and not shares, so a turn is a turn.
 */
export function mapFromPairs(base, P, Q) {
    if (P.length === 0) return base;
    if (P.length >= 4) {
        const tilted = projectiveFit(P, Q);
        if (tilted) return tilted;
    }
    const at = P.map(([x, y]) => mapPoint(base, x, y));
    let over = null;
    if (P.length >= 3) over = affineFit(at, Q);
    if (!over && P.length >= 2) {
        const px = at[1][0] - at[0][0], py = at[1][1] - at[0][1], qx = Q[1][0] - Q[0][0], qy = Q[1][1] - Q[0][1];
        const den = px * px + py * py;
        if (den > 1e-6) {
            const re = (qx * px + qy * py) / den, im = (qy * px - qx * py) / den;
            over = [re, -im, Q[0][0] - (re * at[0][0] - im * at[0][1]), im, re, Q[0][1] - (im * at[0][0] + re * at[0][1])];
        }
    }
    if (!over) over = [1, 0, Q[0][0] - at[0][0], 0, 1, Q[0][1] - at[0][1]];
    return mapThen(base, over) ?? base;
}
