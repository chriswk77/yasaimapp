/**
 * „Entfernen" — the AI computing beside the page, not in it
 * (11.11.2026: „wenn ich auf Entfernen klicke … hängt zeitweise der Browser
 * an sich").
 *
 * WHY A WORKER. onnxruntime builds the model once per visit — about fifteen
 * seconds of work in WebAssembly — and runs it per hole. In the page that
 * work held the page's one thread: nothing was drawn, no click answered,
 * no bar could move. Here it holds a thread of its own, and the page goes
 * on (inpaint.js speaks to it; a browser that has no graphics card IN A
 * WORKER, though it has one in the page, keeps the old way there).
 *
 * NO IMPORTS: a worker has no import map, so a module it imported would come
 * without its version tag; it needs nothing but what it is sent.
 *
 * Messages in:
 *   { type: 'start', urls: { code, wasm, model }, store, backend }
 *   { type: 'fill', id, pixels: Float32Array, mask: Float32Array, side }
 * Messages out:
 *   { type: 'started', backend, gpu }   or   { type: 'failed', problem, message }
 *   { type: 'filled', id, data: Float32Array }   or   { type: 'error', id, message }
 */

let ort = null;
let session = null;
let backend = null;
let sources = null;     // { code, wasm, model } as blobs, kept to start again on the processor

async function load(urls, store) {
    if (!self.caches) throw Object.assign(new Error('no cache'), { problem: 'cache' });
    const box = await caches.open(store);
    const one = async (url) => {
        const answer = await box.match(url);
        if (!answer) throw Object.assign(new Error('missing ' + url), { problem: 'missing' });
        return answer.blob();
    };
    const [code, wasm, model] = await Promise.all([one(urls.code), one(urls.wasm), one(urls.model)]);
    return { code, wasm, model };
}

/* The same switches as in the page (inpaint.js, start): the wasm by an
   object and not a folder, one thread, the warnings kept quiet, and
   disable_quant_qdq — without it the int8 weights are computed in
   integers, which LaMa does not survive. */
async function begin(wanted) {
    if (!ort) {
        ort = await import(URL.createObjectURL(new Blob([sources.code], { type: 'text/javascript' })));
        ort.env.wasm.wasmPaths = { wasm: URL.createObjectURL(new Blob([sources.wasm], { type: 'application/wasm' })) };
        ort.env.wasm.numThreads = 1;
        ort.env.logLevel = 'error';
    }
    session = await ort.InferenceSession.create(new Uint8Array(await sources.model.arrayBuffer()), {
        executionProviders: [wanted],
        /* logSeverityLevel 3 (errors only): env.logLevel does not reach the session's own warnings — „some nodes were not assigned to the preferred execution providers" came yellow into the console on every start (15.11.2026), and it is how onnxruntime always works. */
        logSeverityLevel: 3,
        extra: { session: { disable_quant_qdq: '1' } },
    });
    backend = wanted;
}

async function start({ urls, store, backend: wanted }) {
    sources = await load(urls, store);
    const first = wanted ?? (self.navigator?.gpu ? 'webgpu' : 'wasm');
    try {
        await begin(first);
    } catch (error) {
        if (first !== 'webgpu') throw error;
        await begin('wasm');
    }
}

/* ONE MESSAGE AFTER THE OTHER: this handler waits — for the session to
   start, for a square to be filled —, and a message that arrived
   meanwhile would otherwise begin beside it and ask the one session for a
   second square at once (inpaint.js, fillPlanes, has what that led to).
   The page already sends its fills in one line; this is the same rule at
   the door it guards. */
let turn = Promise.resolve();
self.addEventListener('message', (e) => {
    turn = turn.then(() => handle(e.data)).catch(() => {});
});

async function handle(m) {
    if (m.type === 'start') {
        try {
            await start(m);
            self.postMessage({ type: 'started', backend, gpu: !!self.navigator?.gpu });
        } catch (error) {
            self.postMessage({ type: 'failed', problem: error.problem ?? '', message: String(error?.message ?? error) });
        }
        return;
    }
    if (m.type === 'fill') {
        const run = async () => {
            const out = await session.run({
                image: new ort.Tensor('float32', m.pixels, [1, 3, m.side, m.side]),
                mask:  new ort.Tensor('float32', m.mask,   [1, 1, m.side, m.side]),
            });
            return out[session.outputNames[0]].data;
        };
        try {
            let data;
            try {
                data = await run();
            } catch (error) {
                /* A card that refuses a step at the first run (Firefox did,
                   until the model lost its five-dimensional steps on
                   14.11.2026): then the processor, for the rest of the visit. */
                if (backend !== 'webgpu') throw error;
                await begin('wasm');
                data = await run();
            }
            const copy = new Float32Array(data);
            self.postMessage({ type: 'filled', id: m.id, data: copy, backend }, [copy.buffer]);
        } catch (error) {
            self.postMessage({ type: 'error', id: m.id, message: String(error?.message ?? error) });
        }
    }
}
