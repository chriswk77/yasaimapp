/**
 * Focus stacking in the browser — many live frames through the focus, one
 * picture sharp from top to bottom (26.10.2026: „wenn man länger
 * auf Space drückt, automatisch ein Stack"). microscope.js collects the
 * frames while the space bar is held and the fine focus turned; this file
 * makes one picture of them.
 *
 * NO OpenCV.js (8–10 MB) for what a few dozen lines do. The method, frame
 * after frame and never with all frames in memory:
 *
 *   sharpness    the squared Laplacian of the grey picture, smoothed a
 *                little first — how much a place differs from its
 *                neighbours —, averaged twice over a 7 × 7 square, a soft
 *                hill, so a structure's sharpness fades out at its edge
 *   thinness     that sharpness against the contrast around it: in focus a
 *                structure is as thin as it gets, a seam of defocus beside
 *                a contour is broader however strong it is — it counts
 *                less, and the frame holding the thin structure nearby
 *                wins the calm ground beside it (13.11.2026, in the worker)
 *   weight       how far the sharpness stands above the frame's own noise,
 *                to the fourth power: the clearly sharper frame of a place
 *                outweighs the others there almost alone, and where nothing
 *                stands above the noise all frames weigh exactly alike
 *   picture      per pixel the weighted mean of all frames — detail and
 *                the broad ground joined apart and added —; where none is
 *                sharp, all count alike, and the ground stays calm
 *
 * NOT ALIGNED (the call: „erst ohne Ausrichten"). With the stage
 * fixed, turning the focus barely moves the picture; whether the slight
 * breathing of the magnification needs aligning is decided on real
 * slides. Neither does anything here mend what MOVED between frames — a
 * spore drifting in the mount stands twice or smeared; the full-screen
 * preview with its zoom is there to see it.
 *
 * THE WORK RUNS IN A WORKER, so the live picture and the counter keep
 * moving while it computes. Inline, made from the function below: a worker
 * file would need its own version tag (CLAUDE.md, trap five — the import
 * map does not reach into a worker), and this way it has the one its
 * module has.
 */

/* The worker's whole code. Plain JavaScript without imports, run as the
   body of a Blob worker. */
function stackWorker() {
    let w = 0, h = 0, n = 0;
    let w2 = 0, h2 = 0, n2 = 0;   // the half grid: thinness, claims and the base band live there
    let weightSum = null;   // Float32: the frames' weights added, per pixel
    let colourSum = null;   // Float32: their DETAIL (colour minus base), each times its weight, RGB
    let baseWeight = null;  // Float32, half grid: the weights of the base band
    let baseSum = null;     // Float32, half grid: the frames' base band times its weight, RGB
    let ix0 = null, ix1 = null, itx = null;   // per column: the half grid's two neighbours and the share
    let frames = 0;
    const RADIUS = 3;       // the square of each of the two box passes: 7 × 7
    const POWER = 4;        // how strongly the sharper frame outweighs the softer
    /* What every frame weighs, sharp or not. NOT A TRIFLE: in pure noise
       the frames' sharpness still differs by a tenth or so, and with a
       small base the fourth power turned that back into an either/or —
       measured, 0.05 left grain at a spread of 28 where the true mean has
       7. At 1 the noise moves a weight by a factor of two at most, while
       real detail, many times above the noise, still outweighs a hundred
       fold. */
    const CALM = 1;
    /* How far above the noise a sharpness has to stand before it counts at
       all, in the noise's own spread (see noiseLevel). Below it a frame's
       weight is exactly CALM, so pure noise no longer moves a weight even by
       the factor of two the comment above allows: measured with the two
       flat frames of grey 80 and 160, the result's 9 × 9 means scatter by
       0.8 — what plain averaging gives — where the old measure left 3.2. */
    const MARGIN = 5;
    /* THE FRINGES BESIDE A CONTOUR ARE NOT THE CONTOUR
       (13.11.2026: „durch die Lichtbrechung beim Mikroskop gibt es oft
       außerhalb der Hyphen scharfe Bereiche … die gar nicht die Kontur
       darstellen. Genau darauf springt momentan das Stacking an. Es stackt
       eigentlich die Fehlstellen außerhalb der Kontur"). A hypha in water is
       a lens more than a shadow: out of focus it glows and wears dark and
       bright seams beside its wall, and those carry MORE contrast than the
       wall does in focus. A measure of contrast — the squared Laplacian
       alone — therefore prefers them. Three things answer it, all on a grid
       of half the size, where they cost little:

       THINNESS, NOT CONTRAST. In focus a structure is as thin as it gets;
       a seam of defocus is broader. The sharpness divided by the contrast
       around it (the variance of the grey in the same hill) no longer
       knows how dark a line is, only how thin — for a line of width σ it
       goes with 1/σ⁴. KAPPA keeps what barely stands above the noise from
       counting as thin.

       A STRUCTURE COUNTS BY THE THINNEST NEAR IT. Within one frame a seam
       beside sharp warts is still a seam: `rel` damps what is broader than
       the thinnest structure of its own neighbourhood (to the power KT).
       And BETWEEN frames the calm ground beside a contour has nothing to
       say for itself — there the frame that holds the thin structure
       NEARBY wins (`claim`, to the power K). Where no frame has anything
       near, FLOOR makes them all weigh alike, and the ground stays the
       calm mean it was.

       THE CLAIM REACHES ONLY A FEW PIXELS (REACH 3 on the half grid: it
       fades out twelve pixels from a structure). Further, and a faint wart
       on top of a spore lost against the spore's outline in another plane
       wherever the two came near in the picture — measured: at REACH 6 two
       of seven warts were gone. A seam hugs its contour, so the short
       reach is where it pays.

       DETAIL AND GROUND ARE JOINED SEPARATELY. A frame that wins a wart
       must not bring the glow of its plane along — it stood as a bright
       patch around every wart, and a hypha's inside took the glow of all
       its unsharp planes. Each frame is split into its base band (the half
       grid, smoothed) and the detail above it; the detail is joined with
       the weights above, the base with a claim of its own that reaches
       four times GROUND far — smooth, so it leaves no patches —, and the
       two are added at the end.

       MEASURED on drawn focus series through 31 planes (tests/
       focusstack.mjs: a hypha with wall, septum and the seams of a phase
       object; a spore with faint warts on top), against the measure
       before: the error beside the hypha 3.4 → 1.2 grey steps, inside it
       21 → 5.4, the worst band of the seam 10.4 → 2.2; the warts keep 0.85
       of their contrast instead of 0.32, the spore's inside is off by 1.0
       instead of 8.5. NOT YET MEASURED ON A REAL SLIDE — the numbers of
       reach and power are a drawn model's, and real seams decide. */
    const KAPPA = 8;        // contrast, in its noise's units, below which thinness fades
    const KT = 1;           // how hard a structure broader than its neighbourhood's thinnest is damped
    const K = 4;            // how strongly the frame with thin structure nearby wins the calm ground
    const FLOOR = 0.0005;   // the claim every frame has where none has structure near
    const REACH = 3;        // the claim's hill on the half grid (two box passes of 7 × 7)
    const GROUND = 6;       // the base band's smoothing on the half grid; its claim reaches four times as far

    /* A box mean along rows, then columns — a running window, so the cost
       does not grow with the radius. Run twice it is a soft hill rather
       than a square: the sharpness fades out at a structure's edge instead
       of stopping at it, and so does the passage between two frames. */
    function boxMean(src, bw = w, bh = h, radius = RADIUS) {
        const count = bw * bh;
        const tmp = new Float32Array(count);
        const out = new Float32Array(count);
        const share = 1 / (2 * radius + 1);
        /* THE RUNNING WINDOW IN THREE STRETCHES — the left edge, where the
           pixel leaving is the first one repeated, the middle, the right
           edge — so the long middle runs without a comparison, and the
           columns are swept ROW BY ROW with one running sum per column:
           walking down a column jumps a whole row in memory at every step.
           Since 13.11.2026, when the thinness more than doubled the number
           of passes: measured at 2048 × 1536, a frame fell from 440 ms to
           about 280 with it (160 before the thinness). */
        const left = Math.min(radius, bw), right = Math.max(left, bw - radius - 1);
        for (let y = 0; y < bh; y++) {
            const row = y * bw, first = src[row], last = src[row + bw - 1];
            let acc = 0;
            for (let x = -radius; x <= radius; x++) acc += src[row + Math.min(bw - 1, Math.max(0, x))];
            let x = 0;
            for (; x < left; x++) {
                tmp[row + x] = acc * share;
                acc += src[row + Math.min(bw - 1, x + radius + 1)] - first;
            }
            for (; x < right; x++) {
                tmp[row + x] = acc * share;
                acc += src[row + x + radius + 1] - src[row + x - radius];
            }
            for (; x < bw; x++) {
                tmp[row + x] = acc * share;
                acc += last - src[row + Math.max(0, x - radius)];
            }
        }
        const sums = new Float32Array(bw);
        for (let y = -radius; y <= radius; y++) {
            const row = Math.min(bh - 1, Math.max(0, y)) * bw;
            for (let x = 0; x < bw; x++) sums[x] += tmp[row + x];
        }
        for (let y = 0; y < bh; y++) {
            const row = y * bw;
            const coming = Math.min(bh - 1, y + radius + 1) * bw, going = Math.max(0, y - radius) * bw;
            for (let x = 0; x < bw; x++) {
                out[row + x] = sums[x] * share;
                sums[x] += tmp[coming + x] - tmp[going + x];
            }
        }
        return out;
    }
    const hill = (src, bw, bh, radius) => boxMean(boxMean(src, bw, bh, radius), bw, bh, radius);

    /* The frame's noise as it shows in a measure: its middle and its
       spread, read from the LOWER part of a sample — what most of the
       pixels show is the grain of the sensor, real detail stands above it
       and must not raise the measure that is to tell it apart. The 10th
       and 40th percentile lie 1.03 spreads apart in a bell curve. */
    function noiseLevel(measure) {
        const count = measure.length;
        const step = Math.max(1, Math.floor(count / 4000));
        const sample = [];
        for (let i = 0; i < count; i += step) sample.push(measure[i]);
        sample.sort((a, b) => a - b);
        const low = sample[Math.floor(sample.length * 0.1)];
        const mid = sample[Math.floor(sample.length * 0.4)];
        const spread = Math.max(1e-3, (mid - low) / 1.03);
        return { level: Math.max(0.5, mid + 0.25 * spread), spread };
    }

    /* Three neighbours averaged along the rows, then along the columns —
       the light smoothing BEFORE the Laplacian. The edge pixel repeats
       itself: left raw, the image's border was the one place still rough
       and stood out as a band of one frame. */
    function smooth3(src, bw = w, bh = h) {
        const count = bw * bh;
        const tmp = new Float32Array(count);
        const out = new Float32Array(count);
        const third = 1 / 3;
        for (let y = 0; y < bh; y++) {
            const row = y * bw, end = row + bw - 1;
            tmp[row] = (2 * src[row] + src[Math.min(end, row + 1)]) * third;
            for (let i = row + 1; i < end; i++) tmp[i] = (src[i - 1] + src[i] + src[i + 1]) * third;
            if (bw > 1) tmp[end] = (src[end - 1] + 2 * src[end]) * third;
        }
        for (let y = 0; y < bh; y++) {
            const up = Math.max(0, y - 1) * bw, row = y * bw, down = Math.min(bh - 1, y + 1) * bw;
            for (let x = 0; x < bw; x++) out[row + x] = (tmp[up + x] + tmp[row + x] + tmp[down + x]) * third;
        }
        return out;
    }

    /** The squared Laplacian of a smoothed picture, its border left at nought. */
    function laplaceSquared(soft, bw, bh) {
        const out = new Float32Array(bw * bh);
        for (let y = 1; y < bh - 1; y++) {
            for (let x = 1; x < bw - 1; x++) {
                const i = y * bw + x;
                const l = 4 * soft[i] - soft[i - 1] - soft[i + 1] - soft[i - bw] - soft[i + bw];
                out[i] = l * l;
            }
        }
        return out;
    }

    /** A full-size field made half as large: each cell the mean of its 2 × 2 pixels. */
    function halve(src) {
        const out = new Float32Array(n2);
        for (let y = 0; y < h2; y++) {
            const ya = Math.min(h - 1, 2 * y) * w, yb = Math.min(h - 1, 2 * y + 1) * w;
            for (let x = 0; x < w2; x++) {
                const xa = Math.min(w - 1, 2 * x), xb = Math.min(w - 1, 2 * x + 1);
                out[y * w2 + x] = (src[ya + xa] + src[ya + xb] + src[yb + xa] + src[yb + xb]) / 4;
            }
        }
        return out;
    }

    /* THE HALF GRID, per frame: how thin the structure is at a place
       (`rel`, 0…1 against the thinnest nearby), which claim the frame has
       on the place (`claim`), and the base band's own claim (`far`). */
    function thinness(grey) {
        const soft = smooth3(halve(grey), w2, h2);
        const focus = hill(laplaceSquared(soft, w2, h2), w2, h2, RADIUS);
        const { level, spread } = noiseLevel(focus);
        const start = level + MARGIN * spread;
        const square = new Float32Array(n2);
        for (let i = 0; i < n2; i++) square[i] = soft[i] * soft[i];
        const mean = hill(soft, w2, h2, RADIUS), mean2 = hill(square, w2, h2, RADIUS);
        const contrast = new Float32Array(n2);
        for (let i = 0; i < n2; i++) contrast[i] = Math.max(0, mean2[i] - mean[i] * mean[i]);
        const ground = noiseLevel(contrast).level;
        const thin = new Float32Array(n2), thin2 = new Float32Array(n2);
        for (let i = 0; i < n2; i++) {
            const above = Math.max(0, (focus[i] - start) / level);
            const q = above / (Math.max(0, (contrast[i] - ground) / ground) + KAPPA);
            thin[i] = q;
            thin2[i] = q * q;
        }
        // The thinnest nearby, softly: the mean of the squares over the mean leans to the large values.
        const sum1 = hill(thin, w2, h2, REACH), sum2 = hill(thin2, w2, h2, REACH);
        const rel = new Float32Array(n2), score = new Float32Array(n2);
        for (let i = 0; i < n2; i++) {
            const best = sum2[i] / (sum1[i] + 1e-6);
            const t = Math.min(1, thin[i] / (best + 1e-6));
            rel[i] = t ** KT;
            score[i] = thin[i] * rel[i];
        }
        const claim = hill(score, w2, h2, REACH);
        const far = hill(score, w2, h2, 4 * GROUND);
        return { rel, claim, far };
    }

    /* A WEIGHTED MEAN, NOT A CHOICE (27.10.2026, with a pen laid
       over the lens and taken away: speckled patches, and the sharp parts
       with a hard outline). Choosing per pixel the one sharpest frame
       decides on noise where there is no detail — it jumped between a
       shadowed and a bright frame and left blotches — and where the winner
       changed, an edge stood. Now every frame counts with the sharpness it
       has ABOVE ITS OWN NOISE, to the fourth power: a frame clearly sharper
       at a place outweighs the others there almost alone, where none is
       sharp all count alike and the result is their calm mean, and between
       the two it passes over softly. Still one frame at a time, a few sums
       per pixel, whatever the number of frames.

       THE MEASURE ITSELF WAS BLIND TO FAINT DETAIL (07.11.2026:
       stacked half a spore's width, „die obersten Ornamente verblassen zu
       schnell"). The bare Laplacian of single pixels is mostly sensor
       grain, and a low wart on top of a spore added a few per cent to it —
       the frame where it stood sharp weighed hardly more than the thirty
       where it did not, and the mean washed it out. Measured on a drawn
       spore through forty planes: a wart kept a fifth of its contrast;
       even the outline was gone. Three changes, each needed: the picture
       smoothed lightly before the Laplacian (the grain falls, a wart of
       four pixels stays), the Laplacian SQUARED (a real edge outgrows the
       grain by far more), and the hill 7 × 7 instead of 13 × 13 (a wart is
       no longer one dot in a field of noise). Together: the warts keep
       their full contrast, the outline is back, and the halo beside it is
       smaller than before (mean error 1.6 instead of 2.9 grey steps). The
       smaller hill alone made the calm ground blotchy again; MARGIN is what
       pays for it. */
    function add(rgba) {
        const grey = new Float32Array(n);
        for (let i = 0, p = 0; i < n; i++, p += 4) {
            grey[i] = 0.299 * rgba[p] + 0.587 * rgba[p + 1] + 0.114 * rgba[p + 2];
        }
        const focus = hill(laplaceSquared(smooth3(smooth3(grey)), w, h), w, h, RADIUS);
        const { level, spread } = noiseLevel(focus);
        const start = level + MARGIN * spread;
        const { rel, claim, far } = thinness(grey);

        // The base band: the frame at half size, smoothed over GROUND.
        const base = halveColour(rgba).map((channel) => hill(channel, w2, h2, GROUND));
        for (let i = 0, q = 0; i < n2; i++, q += 3) {
            const weight = raise(far[i] + FLOOR, K);
            baseWeight[i] += weight;
            baseSum[q]     += weight * base[0][i];
            baseSum[q + 1] += weight * base[1][i];
            baseSum[q + 2] += weight * base[2][i];
        }

        /* The detail above it. THE COST IS HERE — every pixel of every
           frame —, so the half grid is read as cheaply as it can be: the
           weights' two fields straight from the pixel's cell (they are
           hills, a step of two pixels in a weight shows nowhere), the base
           between its cells — along the columns once per row into `line`,
           along the row per pixel. Four taps for all five fields cost
           150 ms a frame more at 2048 × 1536. */
        const line = new Float32Array(w2 * 3);
        for (let y = 0; y < h; y++) {
            baseLine(line, base[0], base[1], base[2], y);
            const cells = Math.min(h2 - 1, y >> 1) * w2;
            for (let x = 0; x < w; x++) {
                const i = y * w + x, p = i * 4, q = i * 3;
                const cell = cells + Math.min(w2 - 1, x >> 1);
                const above = Math.max(0, (focus[i] - start) / level);
                const weight = raise(above * rel[cell] + CALM, POWER) * raise(claim[cell] + FLOOR, K);
                const l = ix0[x] * 3, r = ix1[x] * 3, tx = itx[x];
                weightSum[i] += weight;
                colourSum[q]     += weight * (rgba[p]     - (line[l]     + tx * (line[r]     - line[l])));
                colourSum[q + 1] += weight * (rgba[p + 1] - (line[l + 1] + tx * (line[r + 1] - line[l + 1])));
                colourSum[q + 2] += weight * (rgba[p + 2] - (line[l + 2] + tx * (line[r + 2] - line[l + 2])));
            }
        }
        frames++;
    }

    /** v to the power e — the fourth by two multiplications, which is what both powers are. */
    function raise(v, e) {
        if (e !== 4) return v ** e;
        const square = v * v;
        return square * square;
    }

    /** A frame's three colours at half size, in one pass. */
    function halveColour(rgba) {
        const out = [new Float32Array(n2), new Float32Array(n2), new Float32Array(n2)];
        for (let y = 0; y < h2; y++) {
            const ya = Math.min(h - 1, 2 * y) * w, yb = Math.min(h - 1, 2 * y + 1) * w;
            for (let x = 0; x < w2; x++) {
                const xa = Math.min(w - 1, 2 * x), xb = Math.min(w - 1, 2 * x + 1);
                const a = (ya + xa) * 4, b = (ya + xb) * 4, c = (yb + xa) * 4, d = (yb + xb) * 4;
                const i = y * w2 + x;
                out[0][i] = (rgba[a] + rgba[b] + rgba[c] + rgba[d]) / 4;
                out[1][i] = (rgba[a + 1] + rgba[b + 1] + rgba[c + 1] + rgba[d + 1]) / 4;
                out[2][i] = (rgba[a + 2] + rgba[b + 2] + rgba[c + 2] + rgba[d + 2]) / 4;
            }
        }
        return out;
    }

    /** One row of the half grid's three colours, taken between the two rows the pixel row y lies between. */
    function baseLine(line, red, green, blue, y) {
        const fy = Math.max(0, Math.min(h2 - 1, (y + 0.5) / 2 - 0.5));
        const y0 = Math.floor(fy), y1 = Math.min(h2 - 1, y0 + 1), ty = fy - y0;
        const r0 = y0 * w2, r1 = y1 * w2;
        for (let x = 0, q = 0; x < w2; x++, q += 3) {
            line[q]     = red[r0 + x]   + ty * (red[r1 + x]   - red[r0 + x]);
            line[q + 1] = green[r0 + x] + ty * (green[r1 + x] - green[r0 + x]);
            line[q + 2] = blue[r0 + x]  + ty * (blue[r1 + x]  - blue[r0 + x]);
        }
    }

    function finish() {
        const out = new Uint8ClampedArray(n * 4);
        const ground = [new Float32Array(n2), new Float32Array(n2), new Float32Array(n2)];
        for (let i = 0, q = 0; i < n2; i++, q += 3) {
            const total = baseWeight[i] || 1;
            ground[0][i] = baseSum[q] / total;
            ground[1][i] = baseSum[q + 1] / total;
            ground[2][i] = baseSum[q + 2] / total;
        }
        const line = new Float32Array(w2 * 3);
        for (let y = 0; y < h; y++) {
            baseLine(line, ground[0], ground[1], ground[2], y);
            for (let x = 0; x < w; x++) {
                const i = y * w + x, p = i * 4, q = i * 3;
                const total = weightSum[i] || 1;
                const l = ix0[x] * 3, r = ix1[x] * 3, tx = itx[x];
                out[p]     = colourSum[q] / total     + line[l]     + tx * (line[r]     - line[l]);
                out[p + 1] = colourSum[q + 1] / total + line[l + 1] + tx * (line[r + 1] - line[l + 1]);
                out[p + 2] = colourSum[q + 2] / total + line[l + 2] + tx * (line[r + 2] - line[l + 2]);
                out[p + 3] = 255;
            }
        }
        return out;
    }

    self.onmessage = (e) => {
        const m = e.data;
        if (m.type === 'start') {
            w = m.width;
            h = m.height;
            n = w * h;
            w2 = Math.ceil(w / 2);
            h2 = Math.ceil(h / 2);
            n2 = w2 * h2;
            weightSum = new Float32Array(n);
            colourSum = new Float32Array(n * 3);
            baseWeight = new Float32Array(n2);
            baseSum = new Float32Array(n2 * 3);
            ix0 = new Int32Array(w);
            ix1 = new Int32Array(w);
            itx = new Float32Array(w);
            for (let x = 0; x < w; x++) {
                const fx = Math.max(0, Math.min(w2 - 1, (x + 0.5) / 2 - 0.5));
                ix0[x] = Math.floor(fx);
                ix1[x] = Math.min(w2 - 1, ix0[x] + 1);
                itx[x] = fx - ix0[x];
            }
            frames = 0;
        } else if (m.type === 'frame') {
            add(new Uint8ClampedArray(m.buffer));
            /* THE STACK SO FAR comes back with every frame when asked for —
               the running mean is already there, dividing it out costs a
               few milliseconds (measured: 14 at the live picture's size). */
            if (m.preview) {
                const out = finish();
                self.postMessage({ type: 'added', frames, buffer: out.buffer }, [out.buffer]);
            } else {
                self.postMessage({ type: 'added', frames });
            }
        } else if (m.type === 'finish') {
            const out = finish();
            self.postMessage({ type: 'done', buffer: out.buffer, width: w, height: h, frames }, [out.buffer]);
        }
    };
}

/**
 * A stack under way.
 *
 * @param {number} width   the frames' size — every frame the same
 * @param {number} height
 * @param {{onPreview?: (picture: ImageData) => void}} options  onPreview
 *        gets the stack as it stands after every frame — the live preview
 *        of the microscope window, which shows when the last part of a
 *        spore has turned sharp
 * @returns {{add: (data: ImageData) => boolean, busy: () => boolean,
 *            frames: () => number, finish: () => Promise<ImageData>, cancel: () => void}}
 *          add() refuses (false) while the worker still works on the last
 *          frame: frames come faster than they are reckoned, and a queue
 *          would only fill the memory with near-duplicates
 */
export function startStack(width, height, { onPreview = null } = {}) {
    const code = URL.createObjectURL(new Blob([`(${stackWorker.toString()})()`], { type: 'text/javascript' }));
    const worker = new Worker(code);
    URL.revokeObjectURL(code);
    let busy = false;
    let count = 0;
    let finishing = null;

    worker.onmessage = (e) => {
        const m = e.data;
        if (m.type === 'added') {
            busy = false;
            count = m.frames;
            if (m.buffer && onPreview) onPreview(new ImageData(new Uint8ClampedArray(m.buffer), width, height));
        } else if (m.type === 'done') {
            worker.terminate();
            finishing?.resolve(new ImageData(new Uint8ClampedArray(m.buffer), m.width, m.height));
        }
    };
    worker.onerror = (e) => {
        worker.terminate();
        finishing?.reject(new Error(e.message || 'stack worker'));
    };
    worker.postMessage({ type: 'start', width, height });

    return {
        add(data) {
            if (busy || finishing || data.width !== width || data.height !== height) return false;
            busy = true;
            worker.postMessage({ type: 'frame', buffer: data.data.buffer, preview: onPreview !== null }, [data.data.buffer]);
            return true;
        },
        busy: () => busy,
        frames: () => count + (busy ? 1 : 0),
        /** The picture — once the frame in work is done too. */
        finish() {
            return new Promise((resolve, reject) => {
                finishing = { resolve, reject };
                // Messages are worked in order: the finish waits behind a
                // frame still being reckoned.
                worker.postMessage({ type: 'finish' });
            });
        },
        cancel() {
            worker.terminate();
        },
    };
}
