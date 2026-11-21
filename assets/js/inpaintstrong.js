/**
 * The strong model of „Entfernen" — its engine (14.11.2026).
 *
 * HOW IT IS MEANT (his words, asked how the two models should be offered):
 * „Ein Werkzeug. Erst wird LaMa angezeigt und gerechnet, dann bekommt man
 * die Option, wenn einem das nicht gefällt, das starke Modell zu nehmen.
 * Beide Pixelbilder werden gespeichert, sodass man evtl. doch wieder zurück
 * zu LaMa springen kann, wenn man will, ohne neu zu rechnen." So this
 * module never fills a place by itself: the photo window asks it for a
 * place the quick model has filled already (photowindow.js, aiStrongQueue).
 *
 * The files are inpaint.js's second bundle (STRONG_FILES): same store, same
 * question first, listed and removed under Offline-Daten. What the model is
 * and how a hole runs through it stands in moebius.js.
 *
 * THE GRAPHICS CARD OR NOTHING. On the processor a hole takes about three
 * minutes; that is refused with a sentence instead of offered. The worker
 * first (inpaintstrongworker.js) — the page stays free for half a minute
 * and more —, the page's own way where a browser offers the card in the
 * page but not in a worker.
 *
 *   await strongFill(image, hole, seed, progress) -> the filled ImageData
 */

import { t } from './i18n.js';
import { STRONG_FILES, STORE_NAME, fileParts, runtimeUrl, Problem, onRemove } from './inpaint.js';
import { SIDE, moebiusFill } from './moebius.js';

/** The started engine — { run(pixels, mask, seed, progress), stop() } — or its promise. */
let engine = null;

const NO_CARD = () => new Problem(t('Das starke Modell braucht die Grafikkarte, und dieser Browser gibt sie nicht frei.'));

/** An address through the import map, so it carries its version tag like every module. */
function versioned(file) {
    try {
        return import.meta.resolve(file);
    } catch {
        return new URL(file, import.meta.url).href;
    }
}

const urls = () => ({
    code: runtimeUrl('ort.webgpu.bundle.min.mjs'),
    wasm: runtimeUrl('ort-wasm-simd-threaded.asyncify.wasm'),
    // Each model as { bytes, urls }: a large file lies in the store in parts (inpaint.js, PART).
    enc:  fileParts(STRONG_FILES.find((f) => f.name === 'moebius_encoder.onnx')),
    dec:  fileParts(STRONG_FILES.find((f) => f.name === 'moebius_decoder.onnx')),
    unet: fileParts(STRONG_FILES.find((f) => f.name === 'moebius_unet.onnx')),
    loop: versioned('./moebius.js'),
});

function workerStart() {
    return new Promise((done, failed) => {
        let worker;
        try {
            worker = new Worker(versioned('./inpaintstrongworker.js'), { type: 'module' });
        } catch (error) {
            failed(error);
            return;
        }
        const waiting = new Map();
        let next = 0;
        worker.addEventListener('message', (e) => {
            const m = e.data;
            if (m.type === 'started') {
                done({
                    run(pixels, mask, seed, progress, how = {}) {
                        return new Promise((ok, no) => {
                            const id = ++next;
                            waiting.set(id, { ok, no, progress });
                            worker.postMessage({ type: 'fill', id, pixels, mask, seed, how }, [pixels.buffer, mask.buffer]);
                        });
                    },
                    stop() { worker.terminate(); },
                });
            } else if (m.type === 'failed') {
                worker.terminate();
                failed(Object.assign(m.problem === 'missing' ? new Problem(t('Das starke Modell ist nicht auf diesem Gerät.'))
                    : m.problem === 'cache' ? new Problem(t('Dieser Browser kann keine Dateien für später aufbewahren.'))
                    : new Error(m.message), { problem: m.problem }));
            } else if (m.type === 'step') {
                waiting.get(m.id)?.progress?.(m.step, m.of);
            } else if (m.type === 'filled' || m.type === 'error') {
                const w = waiting.get(m.id);
                waiting.delete(m.id);
                if (m.type === 'filled') w?.ok(m.data);
                else w?.no(new Error(m.message));
            }
        });
        worker.addEventListener('error', (e) => {
            e.preventDefault?.();
            worker.terminate();
            for (const w of waiting.values()) w.no(new Error('worker'));
            failed(new Error(e.message || 'worker'));
        });
        worker.postMessage({ type: 'start', store: STORE_NAME, urls: urls() });
    });
}

/** The page's own way: the same loop on this thread — the page waits while it runs. */
async function pageStart() {
    if (!navigator.gpu) throw NO_CARD();
    const box = await caches.open(STORE_NAME);
    const u = urls();
    const one = async (url) => {
        const answer = await box.match(url);
        if (!answer) throw new Problem(t('Das starke Modell ist nicht auf diesem Gerät.'));
        return answer.blob();
    };
    const [code, wasm] = await Promise.all([one(u.code), one(u.wasm)]);
    const ort = await import(URL.createObjectURL(new Blob([code], { type: 'text/javascript' })));
    ort.env.wasm.wasmPaths = { wasm: URL.createObjectURL(new Blob([wasm], { type: 'application/wasm' })) };
    ort.env.wasm.numThreads = 1;
    ort.env.logLevel = 'error';
    const sessions = {};
    for (const key of ['enc', 'dec', 'unet']) {
        // The file put together from its parts, into one buffer of its size.
        const bytes = new Uint8Array(u[key].bytes);
        let at = 0;
        for (const url of u[key].urls) {
            const part = new Uint8Array(await (await one(url)).arrayBuffer());
            bytes.set(part, at);
            at += part.byteLength;
        }
        sessions[key] = await ort.InferenceSession.create(bytes, { executionProviders: ['webgpu'], graphOptimizationLevel: 'all', logSeverityLevel: 3 });
    }
    return {
        run: (pixels, mask, seed, progress, how = {}) => moebiusFill(ort, sessions, pixels, mask, seed, progress, how),
        stop() { for (const s of Object.values(sessions)) s.release?.(); },
    };
}

async function engineMake() {
    try {
        return await workerStart();
    } catch (error) {
        if (error instanceof Problem) throw error;
        // No card in the worker, or no worker at all: the page's way, where it has the card.
        return pageStart();
    }
}

function running() {
    if (!engine) {
        engine = engineMake().catch((error) => {
            engine = null;
            throw error instanceof Problem ? error : (console.warn('Starkes Modell:', error), NO_CARD());
        });
    }
    return engine;
}

// The files leave the device (Offline-Daten): the engine built from them goes too.
onRemove(() => {
    const was = engine;
    engine = null;
    was?.then((eng) => eng.stop(), () => {});
});

/**
 * Fill the hole with the strong model — the same in and out as
 * inpaint.fill: image an ImageData of SIDE × SIDE, hole a Uint8Array of
 * SIDE × SIDE, non-zero where the picture is to be replaced; a new
 * ImageData in which only the hole changed. `seed` decides the fill
 * (moebius.js); `progress(step, of)` runs with every step.
 */
export async function strongFill(image, hole, seed, progress = () => {}) {
    const area = SIDE * SIDE;
    const pixels = new Float32Array(3 * area);
    const mask = new Float32Array(area);
    for (let i = 0; i < area; i++) {
        pixels[i]            = image.data[i * 4]     / 255;
        pixels[area + i]     = image.data[i * 4 + 1] / 255;
        pixels[2 * area + i] = image.data[i * 4 + 2] / 255;
        mask[i] = hole[i] ? 1 : 0;
    }
    const eng = await running();
    const data = await eng.run(pixels, mask, seed >>> 0, progress);
    const result = new ImageData(new Uint8ClampedArray(image.data), SIDE, SIDE);
    for (let i = 0; i < area; i++) {
        if (!hole[i]) continue;
        result.data[i * 4]     = data[i];
        result.data[i * 4 + 1] = data[area + i];
        result.data[i * 4 + 2] = data[2 * area + i];
    }
    return result;
}

/**
 * The model's WHOLE answer for a square — three planes, 0 … 255 —, for a
 * caller that blends it in by itself (expand.js: the fill fades a few
 * pixels into the picture beside the hole). `pixels` three planes 0 … 1,
 * `mask` SIDE × SIDE with 1 where to fill; both are handed over and gone
 * afterwards. `how` as moebius.js takes it: `{ from, strength }` works a
 * coarse fill out instead of starting from noise.
 */
export async function strongPlanes(pixels, mask, seed, progress = () => {}, how = {}) {
    const eng = await running();
    return eng.run(pixels, mask, seed >>> 0, progress, how);
}

/** A seed nobody chose — every „Noch einmal" another fill. */
export function strongSeed() {
    return crypto.getRandomValues(new Uint32Array(1))[0];
}
