/**
 * The strong model of „Entfernen": Moebius, and how a hole is filled with it.
 *
 * WHAT IT IS. A small diffusion model for filling holes (hustvl/Moebius,
 * ECCV 2026, Apache-2.0; assets/vendor/moebius/LICENSE.txt says where the
 * three files come from and how they were made). Where LaMa — the quick
 * model, inpaint.js — smooths a large area into a soft patch, this one
 * INVENTS what could be there: structure, grain, more of what surrounds
 * the hole. That is why it is offered for the large places
 * (14.11.2026: „für große Stellen ist es natürlich deutlich besser, weil es
 * eben Sachen erfindet"), and why it is the second step, not the first: it
 * takes half a minute where LaMa takes a second.
 *
 * THREE GRAPHS, ONE LOOP. The encoder turns the picture with the hole
 * blanked into a small latent (64 × 64 for 512 × 512), the denoiser is
 * asked nineteen times what noise it sees in a noisy latent — given the
 * hole and that picture latent —, and the decoder turns the cleaned latent
 * back into a picture. The loop between them is no part of the files; it is
 * this module, written from the model's published numbers:
 *
 *   - 512 × 512, fixed: the model's attention is tied to that size.
 *   - DDIM, eta 0: betas „scaled linear" from .00085 to .012 over 1000
 *     steps; 20 evenly spaced, of which the first is dropped (the reference
 *     starts at strength .99) — 19 run.
 *   - Guidance 2: every step asks the denoiser twice in one batch, without
 *     and with its learned condition (ids 10 … 19 and 0 … 9 of an embedding
 *     table — there is no text), and takes without + 2 × (with − without).
 *   - The VAE's scaling factor is 0.13025, NOT the usual 0.18215.
 *   - The start is noise plus a little of one more draw per channel
 *     (noise offset .0357).
 *
 * NOTHING HERE IS FASTER THAN IT LOOKS (measured 14.11.2026, twelve large
 * holes): ten steps instead of nineteen smear, six smear more, and without
 * guidance the model set a small thing of its own into a smooth background.
 * So the numbers above stay.
 *
 * A FILL MAY START FROM A PICTURE (15.11.2026, expand.js): `how.from` with
 * a `how.strength` below 1. The square as it stands — its hole holding a
 * coarse fill computed smaller — is turned into a latent, noised up to a
 * step in the middle of the schedule, and only the steps from there on run.
 * The model then works detail out and keeps the draft's shapes; that is how
 * a border of a large picture is filled by a model that sees 512 pixels.
 * Without `how` everything is as it was: nineteen steps from pure noise.
 *
 * THE SEED IS THE FILL. The same seed gives the same picture; another gives
 * another, often quite different one — which is what „Noch einmal" in the
 * photo window is for.
 *
 * NO IMPORTS: the worker loads this module by its address (inpaintstrong.js
 * hands it over), and a module of its own would come without a version tag.
 */

/** The side the model works at, and its latent's. */
export const SIDE = 512;
const LAT = 64;
const SCALING = 0.13025;
const NOISE_OFFSET = 0.0357;
const STEPS = 20;
const GUIDANCE = 2;

/** How many times the denoiser runs for one hole — for whoever shows the progress. */
export const RUNS = Math.min(Math.floor(STEPS * .99), STEPS);

/** How many steps a fill runs: all of them from noise, the last share of them from a picture (`strength` 0 … 1). */
export function runsFor(strength = 1) {
    return strength >= 1 ? RUNS : Math.max(1, Math.min(RUNS, Math.round(strength * STEPS)));
}

/** Normal numbers from a seed (mulberry32, Box–Muller): the same seed, the same fill. */
function normalSource(seed) {
    let s = seed >>> 0;
    const uniform = () => {
        s = (s + 0x6d2b79f5) | 0;
        let t = Math.imul(s ^ (s >>> 15), 1 | s);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
    return () => {
        let u = 0, v = 0;
        while (u === 0) u = uniform();
        while (v === 0) v = uniform();
        return Math.sqrt(-2 * Math.log(u)) * Math.cos(2 * Math.PI * v);
    };
}

/** The cumulative alphas of the 1000 training steps, and the steps that run, highest first. */
function schedule() {
    const a = Math.sqrt(0.00085), b = Math.sqrt(0.012), cum = new Float64Array(1000);
    let acc = 1;
    for (let i = 0; i < 1000; i++) {
        const s = a + (b - a) * (i / 999);
        acc *= 1 - s * s;
        cum[i] = acc;
    }
    const ratio = Math.floor(1000 / STEPS), all = [];
    for (let i = STEPS - 1; i >= 0; i--) all.push(i * ratio);
    return { cum, list: all.slice(STEPS - RUNS) };
}

/* Between steps the thread breathes — by a message, not a timer: a tab in
   the background gets its timers once a second, and nineteen steps would
   wait nineteen seconds for nothing. */
const breathe = () => new Promise((resolve) => {
    const c = new MessageChannel();
    c.port1.onmessage = () => resolve();
    c.port2.postMessage(0);
});

/**
 * Fill one hole.
 *
 * @param {object} ort       onnxruntime-web
 * @param {{enc: object, dec: object, unet: object}} sessions
 * @param {Float32Array} pixels  the square as three planes, 0 … 1 (as inpaint.js hands LaMa)
 * @param {Float32Array} mask    SIDE × SIDE, 1 where the picture is to be replaced
 * @param {number} seed
 * @param {(step: number, of: number) => void} [progress]
 * @param {{from?: boolean, strength?: number}} [how]  `from`: start from `pixels` as they are — the hole holds a coarse fill —, `strength` of the way noised up
 * @returns {Promise<Float32Array>} three planes, 0 … 255 — the model's whole answer; the caller takes the hole from it
 */
export async function moebiusFill(ort, sessions, pixels, mask, seed, progress = () => {}, how = {}) {
    const plane = SIDE * SIDE, lp = LAT * LAT;
    // The picture in −1 … 1 with the hole at zero — the model's mid grey.
    const masked = new Float32Array(3 * plane);
    for (let p = 0; p < plane; p++) {
        if (mask[p]) continue;
        for (let c = 0; c < 3; c++) masked[c * plane + p] = pixels[c * plane + p] * 2 - 1;
    }
    const encode = async (data) => {
        const moments = (await sessions.enc.run({ image: new ort.Tensor('float32', data, [1, 3, SIDE, SIDE]) })).moments.data;
        // The first four of its eight channels are the mean; the rest (the spread) is not used.
        const out = new Float32Array(4 * lp);
        for (let i = 0; i < out.length; i++) out[i] = moments[i] * SCALING;
        return out;
    };
    const pictureLatent = await encode(masked);
    /* THE HOLE AT THE LATENT'S SIZE: a block of 8 × 8 pixels counts as hole
       when ANY of its pixels is. The reference takes each block's top-left
       pixel; a hole's rim must not fall between those samples and be told
       „keep" where half the block is to be replaced. */
    const hole = new Float32Array(lp);
    for (let y = 0; y < LAT; y++) {
        for (let x = 0; x < LAT; x++) {
            let any = 0;
            for (let j = 0; j < 8 && !any; j++) {
                for (let i = 0; i < 8; i++) {
                    if (mask[(y * 8 + j) * SIDE + x * 8 + i]) { any = 1; break; }
                }
            }
            hole[y * LAT + x] = any;
        }
    }
    const normal = normalSource(seed);
    let latent = new Float32Array(4 * lp);
    for (let i = 0; i < latent.length; i++) latent[i] = normal();
    for (let c = 0; c < 4; c++) {
        const off = NOISE_OFFSET * normal();
        for (let p = 0; p < lp; p++) latent[c * lp + p] += off;
    }
    const { cum, list: whole } = schedule();
    const from = !!how.from && (how.strength ?? 1) < 1;
    const list = from ? whole.slice(whole.length - runsFor(how.strength)) : whole;
    if (from) {
        // The square as it is, noised up to where the remaining steps begin.
        const full = new Float32Array(3 * plane);
        for (let i = 0; i < full.length; i++) full[i] = pixels[i] * 2 - 1;
        const x0 = await encode(full);
        const sa = Math.sqrt(cum[list[0]]), sb = Math.sqrt(1 - cum[list[0]]);
        for (let i = 0; i < latent.length; i++) latent[i] = sa * x0[i] + sb * latent[i];
    }
    // Row 0 without the condition, row 1 with it.
    const ids = new BigInt64Array(20);
    for (let i = 0; i < 10; i++) { ids[i] = BigInt(10 + i); ids[10 + i] = BigInt(i); }
    for (const [k, t] of list.entries()) {
        progress(k + 1, list.length);
        // Nine channels per row: the noisy latent, the hole, the picture's latent.
        const input = new Float32Array(2 * 9 * lp);
        for (const row of [0, 1]) {
            input.set(latent, row * 9 * lp);
            input.set(hole, row * 9 * lp + 4 * lp);
            input.set(pictureLatent, row * 9 * lp + 5 * lp);
        }
        const noise = (await sessions.unet.run({
            latent: new ort.Tensor('float32', input, [2, 9, LAT, LAT]),
            timesteps: new ort.Tensor('int64', new BigInt64Array([BigInt(t), BigInt(t)]), [2]),
            input_ids: new ort.Tensor('int64', ids, [2, 10]),
        })).noise.data;
        const n = 4 * lp;
        // After the last step nothing of the noise is left: alpha 1.
        const before = k + 1 < list.length ? cum[list[k + 1]] : 1;
        const sa = Math.sqrt(cum[t]), sb = Math.sqrt(1 - cum[t]), pa = Math.sqrt(before), pb = Math.sqrt(1 - before);
        const next = new Float32Array(n);
        for (let i = 0; i < n; i++) {
            const eps = noise[i] + GUIDANCE * (noise[n + i] - noise[i]);
            next[i] = pa * (latent[i] - sb * eps) / sa + pb * eps;
        }
        latent = next;
        await breathe();
    }
    const scaled = new Float32Array(latent.length);
    for (let i = 0; i < scaled.length; i++) scaled[i] = latent[i] / SCALING;
    const image = (await sessions.dec.run({ latent: new ort.Tensor('float32', scaled, [1, 4, LAT, LAT]) })).image.data;
    const out = new Float32Array(3 * plane);
    for (let i = 0; i < out.length; i++) out[i] = Math.max(0, Math.min(255, (image[i] + 1) / 2 * 255));
    return out;
}
