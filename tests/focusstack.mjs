/**
 * The focus stack against DRAWN focus series — node tests/focusstack.mjs
 *
 * WHY DRAWN. What a stack should give is only known where the truth is:
 * a picture made by hand, sent through 31 planes of defocus, with the
 * picture in focus to compare against. No slide offers that.
 *
 * WHAT IS DRAWN (13.11.2026: „es stackt eigentlich die
 * Fehlstellen außerhalb der Kontur"). A hypha in water is a lens more than
 * a shadow. Out of focus its thin wall blurs, and beside the wall stand
 * dark and bright seams that carry MORE contrast than the wall in focus;
 * its inside glows. The series models both: every structure blurred by its
 * distance from its plane, and the seam of a phase object after the
 * transport-of-intensity rule — the light changes with the defocus times
 * the curvature of the object's thickness. The planes run from 5 below to
 * 25 above the focus, lopsided as a hand turns it, so the seams of the two
 * sides do not cancel.
 *
 *   the hypha   a cylinder with a wall and a septum, in focus at plane 0
 *   the spore   an outline in focus at plane 0, seven faint warts on top
 *               at plane 12 — the ornament of 07.11.2026 that must not fade
 *   the ground  flat frames of two brightnesses with nothing but grain —
 *               the pen over the lens of 27.10.2026
 *
 * The numbers the checks hold are a little above what was measured when
 * the thinness came (focusstack.js, the worker); the measure before it is
 * quoted beside each. THE MODEL IS NOT A SLIDE: it guards the reasoning,
 * real seams are judged at the microscope.
 *
 * KNOWN AND NOT HELD: at half this size (structures two pixels thin) the
 * two warts nearest the spore's outline keep only a third of their
 * contrast — the claim of the outline's plane reaches them. At this size,
 * which is what a 2048 px frame at 40× and 100× shows, they stand.
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

let failed = 0;
function check(b, what) {
    if (b) { console.log(`  ok    ${what}`); } else { failed++; console.log(`  FAILED: ${what}`); }
}

/* The worker's code runs here as it runs in the browser: the function's
   text, with a `self` to talk to — the module itself needs Worker and Blob. */
const source = readFileSync(fileURLToPath(new URL('../assets/js/focusstack.js', import.meta.url)), 'utf8');
const from = source.indexOf('function stackWorker()');
const body = source.slice(from, source.indexOf('\n}\n', from) + 2);
function stack(frames, w, h) {
    let result = null;
    const self = { postMessage: (m) => { if (m.type === 'done') result = new Uint8ClampedArray(m.buffer); } };
    new Function('self', body + '\nstackWorker();')(self);
    self.onmessage({ data: { type: 'start', width: w, height: h } });
    for (const f of frames) self.onmessage({ data: { type: 'frame', buffer: f.buffer.slice(0), preview: false } });
    self.onmessage({ data: { type: 'finish' } });
    return result;
}

// Grain that is the same on every run.
let seed = 12345;
const random = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 4294967296; };
const grain = () => { let u = 0; while (u === 0) u = random(); return Math.sqrt(-2 * Math.log(u)) * Math.cos(2 * Math.PI * random()); };

function blur(img, w, h, sigma) {
    if (sigma < 0.3) return Float32Array.from(img);
    const r = Math.ceil(sigma * 3), kernel = [];
    let sum = 0;
    for (let i = -r; i <= r; i++) { const v = Math.exp(-i * i / (2 * sigma * sigma)); kernel.push(v); sum += v; }
    const tmp = new Float32Array(w * h), out = new Float32Array(w * h);
    for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
        let acc = 0;
        for (let i = -r; i <= r; i++) acc += kernel[i + r] * img[y * w + Math.min(w - 1, Math.max(0, x + i))];
        tmp[y * w + x] = acc / sum;
    }
    for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
        let acc = 0;
        for (let i = -r; i <= r; i++) acc += kernel[i + r] * tmp[Math.min(h - 1, Math.max(0, y + i)) * w + x];
        out[y * w + x] = acc / sum;
    }
    return out;
}
function curvature(img, w, h) {
    const out = new Float32Array(w * h);
    for (let y = 1; y < h - 1; y++) for (let x = 1; x < w - 1; x++) {
        const i = y * w + x;
        out[i] = img[i - 1] + img[i + 1] + img[i - w] + img[i + w] - 4 * img[i];
    }
    return out;
}

const GROUND = 200, GRAIN = 2;
const SIZE = 2;           // structures 2.2 px thin in focus — a 2048 px frame's
const SEAM = 3;           // how strong the seams of defocus are
const planes = [];
for (let z = -5; z <= 25; z++) planes.push(z);

/** The series: layers [{plane, dark}] blurred by their distance, times the seam of `thickness`. */
function series(w, h, layers, thickness, seam) {
    return planes.map((z) => {
        const img = new Float32Array(w * h).fill(GROUND);
        for (const layer of layers) {
            const dark = blur(layer.dark, w, h, 0.5 * SIZE * Math.abs(z - layer.plane));
            for (let i = 0; i < img.length; i++) img[i] -= dark[i];
        }
        if (seam > 0 && z !== 0) {
            const bend = curvature(blur(thickness, w, h, SIZE * Math.sqrt(1.2 * 1.2 + (0.22 * z) ** 2)), w, h);
            for (let i = 0; i < img.length; i++) img[i] *= 1 - seam * SIZE * SIZE * z * bend[i];
        }
        const out = new Uint8ClampedArray(w * h * 4);
        for (let i = 0; i < img.length; i++) {
            out[i * 4] = out[i * 4 + 1] = out[i * 4 + 2] = img[i] + GRAIN * grain();
            out[i * 4 + 3] = 255;
        }
        return out;
    });
}
function inFocus(w, h, layers) {
    const img = new Float32Array(w * h).fill(GROUND);
    for (const layer of layers) for (let i = 0; i < img.length; i++) img[i] -= layer.dark[i];
    return img;
}

// --- The hypha ---------------------------------------------------------------
function hypha(seam) {
    const w = 360 * SIZE, h = 220 * SIZE, axis = 110 * SIZE, r = 22 * SIZE, thin = 2 * 1.1 * 1.1 * SIZE * SIZE;
    const wall = new Float32Array(w * h), thickness = new Float32Array(w * h);
    for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
        const dy = Math.abs(y - axis), i = y * w + x;
        wall[i] = 35 * Math.exp(-((dy - r) ** 2) / thin);
        if (dy < r && Math.abs(x - 180 * SIZE) < 6 * SIZE) wall[i] += 25 * Math.exp(-((x - 180 * SIZE) ** 2) / thin);
        thickness[i] = dy < r ? Math.sqrt(r * r - dy * dy) / r : 0;
    }
    const layers = [{ plane: 0, dark: wall }];
    const got = stack(series(w, h, layers, thickness, seam), w, h);
    const want = inFocus(w, h, layers);
    // Beside the hypha and inside it: how far the stack is off the picture in focus.
    let beside = 0, besideN = 0, inside = 0, insideN = 0, band = 0;
    for (let y = 0; y < h; y++) {
        const d = Math.abs(y - axis) - r;
        let row = 0;
        for (let x = 20 * SIZE; x < 160 * SIZE; x++) {
            const off = got[(y * w + x) * 4] - want[y * w + x];
            row += off;
            if (d > 2 * SIZE && d <= 45 * SIZE) { beside += Math.abs(off); besideN++; }
            if (d < -2 * SIZE) { inside += Math.abs(off); insideN++; }
        }
        // A whole row off the same way is a band — grain averages out along it.
        if (d >= 7 * SIZE && d <= 45 * SIZE) band = Math.max(band, Math.abs(row / (140 * SIZE)));
    }
    // The ground far from everything: as calm as the mean of all planes.
    let s = 0, s2 = 0, c = 0;
    for (let y = h - 14; y < h - 2; y++) for (let x = 20; x < w - 20; x++) { const v = got[(y * w + x) * 4]; s += v; s2 += v * v; c++; }
    return { beside: beside / besideN, inside: inside / insideN, band, far: Math.sqrt(Math.max(0, s2 / c - (s / c) ** 2)) };
}

// --- The spore ---------------------------------------------------------------
function spore(seam) {
    const w = 240 * SIZE, h = 240 * SIZE, c = 120 * SIZE, r = 60 * SIZE;
    const spots = [[0, 0], [22, 8], [-18, 20], [10, -28], [-34, -10], [38, -22], [-8, 44]].map(([a, b]) => [a * SIZE, b * SIZE]);
    const outline = new Float32Array(w * h), warts = new Float32Array(w * h), thickness = new Float32Array(w * h);
    for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
        const d = Math.hypot(x - c, y - c), i = y * w + x;
        outline[i] = 35 * Math.exp(-((d - r) ** 2) / (2 * 1.1 * 1.1 * SIZE * SIZE));
        thickness[i] = d < r ? Math.sqrt(r * r - d * d) / r : 0;
        for (const [sx, sy] of spots) warts[i] += 12 * Math.exp(-((x - c - sx) ** 2 + (y - c - sy) ** 2) / (2 * 2 * 2 * SIZE * SIZE));
    }
    const layers = [{ plane: 0, dark: outline }, { plane: 12, dark: warts }];
    const got = stack(series(w, h, layers, thickness, seam), w, h);
    const want = inFocus(w, h, layers);
    // Each wart's darkness against the ring around it, as a share of what was drawn.
    const kept = spots.map(([sx, sy]) => {
        const x = c + sx, y = c + sy;
        let ring = 0, mid = 0;
        for (let a = 0; a < 16; a++) ring += got[(Math.round(y + 9 * SIZE * Math.sin(a * Math.PI / 8)) * w + Math.round(x + 9 * SIZE * Math.cos(a * Math.PI / 8))) * 4];
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) mid += got[((y + dy) * w + x + dx) * 4];
        return (ring / 16 - mid / 9) / 12;
    });
    let inside = 0, insideN = 0;
    for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
        if (Math.hypot(x - c, y - c) > r - 6 * SIZE) continue;
        if (spots.some(([sx, sy]) => Math.hypot(x - c - sx, y - c - sy) < 7 * SIZE)) continue;
        inside += Math.abs(got[(y * w + x) * 4] - want[y * w + x]);
        insideN++;
    }
    let band = 0;
    for (let d = 7 * SIZE; d <= 45 * SIZE; d++) {
        let ring = 0, count = 0;
        for (let a = 0; a < 360; a += 2) {
            const x = Math.round(c + (r + d) * Math.cos(a * Math.PI / 180)), y = Math.round(c + (r + d) * Math.sin(a * Math.PI / 180));
            if (x < 2 || y < 2 || x >= w - 2 || y >= h - 2) continue;
            ring += got[(y * w + x) * 4] - want[y * w + x];
            count++;
        }
        band = Math.max(band, Math.abs(ring / count));
    }
    return { kept, inside: inside / insideN, band };
}

const two = (v) => v.toFixed(2);

console.log('A hypha with the seams of defocus beside its wall');
{
    const m = hypha(SEAM);
    console.log(`        beside ${two(m.beside)}, inside ${two(m.inside)}, worst band ${two(m.band)}, far ground ${two(m.far)}`);
    check(m.beside < 2, 'beside the wall the stack is the ground, not the seam (contrast alone: 3.4 grey steps off)');
    check(m.inside < 8, 'the inside does not take the glow of the unsharp planes (contrast alone: 21)');
    check(m.band < 4, 'no band of a seam runs along the hypha (contrast alone: 10.4)');
    check(m.far < 0.6, 'far from everything the ground is the calm mean of all planes');
}

console.log('The same hypha without seams — nothing is lost where there was no trouble');
{
    const m = hypha(0);
    console.log(`        beside ${two(m.beside)}, inside ${two(m.inside)}, worst band ${two(m.band)}`);
    check(m.beside < 1 && m.inside < 1.5 && m.band < 0.6, 'ground and inside stand as in focus');
}

console.log('A spore with faint warts on top');
{
    const m = spore(SEAM);
    console.log(`        warts ${m.kept.map(two).join(' ')}, inside ${two(m.inside)}, worst band ${two(m.band)}`);
    check(m.kept.every((k) => k > 0.6), 'every wart keeps its contrast (contrast alone: 0.32 of it)');
    check(m.inside < 2, 'the spore\'s inside is even — no bright patch around a wart (contrast alone: 8.5 off)');
    check(m.band < 3, 'no seam stands around the outline (contrast alone: 10.8)');
}
{
    const m = spore(0);
    console.log(`        without seams: warts ${m.kept.map(two).join(' ')}`);
    check(m.kept.every((k) => k > 0.6), 'and without seams the warts stand too');
}

console.log('Flat frames of two brightnesses, grain only');
{
    const w = 160, h = 120;
    const flat = (grey) => {
        const out = new Uint8ClampedArray(w * h * 4);
        for (let i = 0; i < w * h; i++) { out[i * 4] = out[i * 4 + 1] = out[i * 4 + 2] = grey + GRAIN * grain(); out[i * 4 + 3] = 255; }
        return out;
    };
    const got = stack([flat(80), flat(160), flat(80), flat(160)], w, h);
    const means = [];
    for (let y = 4; y + 9 < h - 4; y += 9) for (let x = 4; x + 9 < w - 4; x += 9) {
        let s = 0;
        for (let dy = 0; dy < 9; dy++) for (let dx = 0; dx < 9; dx++) s += got[((y + dy) * w + x + dx) * 4];
        means.push(s / 81);
    }
    const mean = means.reduce((a, b) => a + b) / means.length;
    const scatter = Math.sqrt(means.reduce((a, b) => a + (b - mean) ** 2, 0) / means.length);
    console.log(`        mean ${two(mean)}, scatter of the 9 × 9 means ${two(scatter)}`);
    check(Math.abs(mean - 120) < 1, 'the result is the mean of the frames');
    check(scatter < 0.3, 'and no patch of one frame stands in it');
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
