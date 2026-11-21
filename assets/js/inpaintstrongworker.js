/**
 * The strong model of „Entfernen" computing beside the page (14.11.2026) —
 * the twin of inpaintworker.js for Moebius (moebius.js says what it is).
 *
 * WHY A WORKER, AGAIN. Building three graphs out of 620 MB takes seconds,
 * a hole half a minute and more; in the page nothing would be drawn and no
 * click answered meanwhile. Here it holds a thread of its own.
 *
 * ONLY ON THE GRAPHICS CARD. On the processor one hole takes about three
 * minutes (measured, 9 s a step) — that is no tool. A worker that is not
 * offered WebGPU says so ('nogpu'), and the page tries its own way, where
 * some browsers offer the card and the worker does not (inpaintstrong.js).
 *
 * NO IMPORTS OF ITS OWN: a worker has no import map. The loop (moebius.js)
 * comes by the address the page hands over, which carries its version tag.
 *
 * Messages in:
 *   { type: 'start', urls: { code, wasm, loop, enc, dec, unet }, store } — enc, dec, unet each { bytes, urls: [parts] }
 *   { type: 'fill', id, pixels: Float32Array, mask: Float32Array, seed }
 * Messages out:
 *   { type: 'started' }   or   { type: 'failed', problem, message }
 *   { type: 'step', id, step, of }
 *   { type: 'filled', id, data: Float32Array }   or   { type: 'error', id, message }
 */

let ort = null;
let sessions = null;
let loop = null;

async function start({ urls, store }) {
    if (!self.navigator?.gpu) throw Object.assign(new Error('no WebGPU in the worker'), { problem: 'nogpu' });
    if (!self.caches) throw Object.assign(new Error('no cache'), { problem: 'cache' });
    const box = await caches.open(store);
    const one = async (url) => {
        const answer = await box.match(url);
        if (!answer) throw Object.assign(new Error('missing ' + url), { problem: 'missing' });
        return answer.blob();
    };
    loop = await import(urls.loop);
    const [code, wasm] = await Promise.all([one(urls.code), one(urls.wasm)]);
    ort = await import(URL.createObjectURL(new Blob([code], { type: 'text/javascript' })));
    // The same switches as the quick model's worker: the wasm by an object, one thread, warnings kept quiet.
    ort.env.wasm.wasmPaths = { wasm: URL.createObjectURL(new Blob([wasm], { type: 'application/wasm' })) };
    ort.env.wasm.numThreads = 1;
    ort.env.logLevel = 'error';
    /* ONE AFTER THE OTHER, the largest last: each file is read whole into
       memory to build its graph, and three at once would hold 620 MB twice. */
    const made = {};
    for (const key of ['enc', 'dec', 'unet']) {
        // A large file lies in the store in parts (inpaint.js, PART): put together into one buffer of its size.
        const bytes = new Uint8Array(urls[key].bytes);
        let at = 0;
        for (const url of urls[key].urls) {
            const part = new Uint8Array(await (await one(url)).arrayBuffer());
            bytes.set(part, at);
            at += part.byteLength;
        }
        made[key] = await ort.InferenceSession.create(bytes, { executionProviders: ['webgpu'], graphOptimizationLevel: 'all', logSeverityLevel: 3 });
    }
    sessions = made;
}

self.addEventListener('message', async (e) => {
    const m = e.data;
    if (m.type === 'start') {
        try {
            await start(m);
            self.postMessage({ type: 'started' });
        } catch (error) {
            self.postMessage({ type: 'failed', problem: error.problem ?? '', message: String(error?.message ?? error) });
        }
        return;
    }
    if (m.type === 'fill') {
        try {
            const data = await loop.moebiusFill(ort, sessions, m.pixels, m.mask, m.seed,
                (step, of) => self.postMessage({ type: 'step', id: m.id, step, of }), m.how ?? {});
            self.postMessage({ type: 'filled', id: m.id, data }, [data.buffer]);
        } catch (error) {
            self.postMessage({ type: 'error', id: m.id, message: String(error?.message ?? error) });
        }
    }
});
