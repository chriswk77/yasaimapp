/**
 * The „Entfernen" tool's engine: LaMa in the browser (the maintainer's „Weg 1",
 * 10.11.2026).
 *
 * WHAT IT IS. A neural network that fills a painted hole in a picture with
 * what plausibly lay behind it — a blade of grass across a cap, a twig, a
 * crumb of soil. It runs ON THE DEVICE: three files from our own server
 * (assets/vendor/lama, LICENSE.txt there says where each comes from and how
 * the model was made), kept in a store of their own, and the picture never
 * goes anywhere to be worked on.
 *
 * NOTHING DOWNLOADS UNASKED (the project's offline rule, and the maintainer's
 * condition for this tool: „bevor wir so ein Tool anwenden, muss der Nutzer
 * natürlich informiert werden … wie groß das ungefähr ist, und dem
 * zustimmen"). ensure() is the one door: it asks, and only a „Laden" in
 * its window fetches anything. Offline-Daten lists what lies here and
 * removes it (administration.js, drawInpaint) — the same store, the same
 * three functions.
 *
 * WHY A STORE OF ITS OWN AND NOT THE SERVICE WORKER'S. The worker keeps
 * code by version and throws versions away; 95 MB the user agreed to fetch
 * once must survive every deployment (sw.js keeps the name 'inpaint' on
 * activate and leaves assets/vendor/lama alone in isApp). And only here can
 * a size be named, a download be counted and the whole thing be removed in
 * one piece.
 *
 * WHY THE FILES ARE STARTED FROM BLOBS. onnxruntime is loaded with import()
 * from a blob of the stored module, its wasm handed over as a second blob,
 * the model as bytes. Fetched by their addresses they would go past this
 * store to the network — the very download one agreed to make only once —
 * and offline they would not come at all.
 *
 *   await inpaint.stored()   -> { bytes, complete }
 *   await inpaint.ensure()   -> true when ready to run (asked, fetched)
 *   await inpaint.fill(image, hole) -> the filled 512×512 ImageData
 *   await inpaint.remove()
 */

import { t } from './i18n.js';

const STORE = 'inpaint';
const BASE = new URL('../vendor/lama/', import.meta.url);

/* THE SIZES ARE PART OF THE CONTRACT. A file is „here" only when its
   stored copy has exactly this many bytes — a cut download (the train
   enters a tunnel) is then a missing file and is fetched again, and a
   model replaced on the server one day changes the number here and
   thereby makes every device's copy outdated. They also drive the bar:
   with DEFLATE on the wasm (.htaccess) the server's Content-Length is
   the packed size, while the reader counts unpacked bytes. */
const FILES = [
    { name: 'lama_small.onnx',                       bytes: 66444911, type: 'application/octet-stream' },
    { name: 'ort-wasm-simd-threaded.asyncify.wasm',  bytes: 28747738, type: 'application/wasm' },
    { name: 'ort.webgpu.bundle.min.mjs',             bytes:   122491, type: 'text/javascript' },
];

export const TOTAL_BYTES = FILES.reduce((sum, f) => sum + f.bytes, 0);

/* THE STRONG MODEL'S THREE FILES (14.11.2026, moebius.js): a second bundle
   in the same store, fetched only by whoever asks for it — „unser
   Lama-Modell, was ja recht schnell zu laden ist, und das Möbius noch als
   extra" (the maintainer). They need the runtime of the bundle above and bring
   none of their own.

   NOT IN THE REPOSITORY: 455 MB is more than a file may weigh on its way
   through the deployment (100 MB), and in parts they would lie 620 MB
   heavy in every working copy. The maintainer lays them on the server by hand,
   once (assets/vendor/moebius, LICENSE.txt there); the server says whether
   they are there (inpaint_strong_offered, the page's `strongModel`), and
   only then is the model offered at all. The sizes are the contract here
   as above. */
const STRONG_BASE = new URL('../vendor/moebius/', import.meta.url);
export const STRONG_FILES = [
    { name: 'moebius_encoder.onnx', bytes:  68498193, type: 'application/octet-stream', base: STRONG_BASE },
    { name: 'moebius_decoder.onnx', bytes:  99187044, type: 'application/octet-stream', base: STRONG_BASE },
    { name: 'moebius_unet.onnx',    bytes: 455522843, type: 'application/octet-stream', base: STRONG_BASE },
];
export const STRONG_BYTES = STRONG_FILES.reduce((sum, f) => sum + f.bytes, 0);

/** The side the model works at. Everything goes in and comes out at 512. */
export const SIDE = 512;

export const fileUrl = (file) => new URL(file.name, file.base ?? BASE).href;
/** The store's name — the strong model's worker opens the same one. */
export const STORE_NAME = STORE;
/** The address of one of the tool's own files — the strong model runs on the same runtime. */
export const runtimeUrl = (name) => fileUrl(FILES.find((f) => f.name === name));

/** A failure told in our words — the only kind the window shows as is. */
export class Problem extends Error {}

/** Megabytes, rounded — the way one says a size to a neighbour. */
export function megabytes(bytes) {
    return t('%s MB', Math.max(1, Math.round(bytes / 1e6)));
}

async function openStore() {
    if (!globalThis.caches) throw new Problem(t('Dieser Browser kann keine Dateien für später aufbewahren.'));
    return caches.open(STORE);
}

/* A LARGE FILE IS KEPT IN PARTS (14.11.2026). The strong model's denoiser
   weighs 455 MB, and a browser's store may refuse one entry of that size —
   measured in Chromium 156: 200 MB went in, 300 MB failed with „Unexpected
   internal error", though five gigabytes were free. So a file above PART is
   cut on its way in and lies as several entries, `…?part=0`, `…?part=1`, …;
   a file up to PART lies whole under its own address, as it always did —
   the quick model's 66 MB is not touched by this and is not fetched again.
   And a cut download resumes at the PART it broke off in, not at the file. */
const PART = 96 * 1024 * 1024;
const partCount = (file) => Math.max(1, Math.ceil(file.bytes / PART));
const partUrl = (file, i) => (partCount(file) === 1 ? fileUrl(file) : fileUrl(file) + '?part=' + i);
const partBytes = (file, i) => Math.min(PART, file.bytes - i * PART);
/** Where a file's bytes lie in the store, in order — for whoever builds a model from them. */
export const fileParts = (file) => ({
    bytes: file.bytes,
    urls: Array.from({ length: partCount(file) }, (_, i) => partUrl(file, i)),
});

/** Is this part here, whole? */
async function partHere(store, file, i) {
    const answer = await store.match(partUrl(file, i));
    return Number(answer?.headers.get('X-Bytes') ?? 0) === partBytes(file, i);
}

/**
 * What lies on this device: the bytes of the copies that are complete,
 * and whether all of them are. `files`: the bundle asked about — the
 * tool's own, or the strong model's (storedStrong).
 */
export async function stored(files = FILES) {
    let bytes = 0;
    let complete = true;
    try {
        if (!globalThis.caches || !(await caches.has(STORE))) return { bytes: 0, complete: false };
        const store = await caches.open(STORE);
        for (const file of files) {
            for (let i = 0; i < partCount(file); i++) {
                if (await partHere(store, file, i)) bytes += partBytes(file, i);
                else complete = false;
            }
        }
    } catch {
        return { bytes: 0, complete: false };
    }
    return { bytes, complete };
}

/**
 * Fetch whatever is missing. report(done, total) runs as bytes arrive;
 * signal stops it. What is already complete is not fetched again, so a
 * download cut halfway resumes at the part it broke off in.
 */
export async function download(report = () => {}, signal, files = FILES) {
    const store = await openStore();
    const total = files.reduce((sum, f) => sum + f.bytes, 0);

    /* What lies here and is no longer one of ours — an earlier model the
       server replaced — goes first: it is dead weight and counted nowhere.
       BOTH bundles are ours: fetching the one must not throw out the other. */
    const ours = new Set([...FILES, ...STRONG_FILES].flatMap((f) => fileParts(f).urls.map((u) => new URL(u).href)));
    for (const request of await store.keys()) {
        if (!ours.has(request.url)) await store.delete(request);
    }

    let done = 0;
    report(done, total);
    for (const file of files) {
        // The parts at the front that lie here already; the first missing one is where the fetch begins.
        let first = 0;
        while (first < partCount(file) && await partHere(store, file, first)) first++;
        done += first === partCount(file) ? file.bytes : first * PART;
        report(done, total);
        if (first === partCount(file)) continue;

        /* THE BROWSER'S OWN WORDS NEVER REACH THE WINDOW. A cut line
           comes as „Failed to fetch", a full disk as a QuotaExceededError
           — English, and meaningless to whoever reads it (seen in the
           draft, 11.11.2026). Each is turned into a sentence here; an
           abort stays an abort, the window knows it asked for it. */
        const put = async (i, bytes) => {
            try {
                await store.put(partUrl(file, i), new Response(new Blob([bytes], { type: file.type }), {
                    headers: { 'Content-Type': file.type, 'X-Bytes': String(bytes.byteLength) },
                }));
            } catch {
                throw new Problem(t('Auf diesem Gerät ist nicht genug Platz frei.'));
            }
        };
        let at = first;                    // the part being filled
        let into = new Uint8Array(partBytes(file, at));
        let filled = 0;
        try {
            /* no-store: the browser's own HTTP cache would otherwise keep
               a second copy beside ours, for nothing. FROM WHERE IT BROKE
               OFF: the rest is asked for by its byte range. A server that
               does not know ranges answers with the whole file (200) — then
               what lies here already is read past and not stored twice. */
            const from = first * PART;
            const answer = await fetch(fileUrl(file), { cache: 'no-store', signal, headers: from > 0 ? { Range: `bytes=${from}-` } : {} });
            if (!answer.ok || !answer.body) {
                throw new Problem(t('Der Server hat die Datei %s nicht geliefert.', file.name));
            }
            let skip = from > 0 && answer.status !== 206 ? from : 0;
            const reader = answer.body.getReader();
            for (;;) {
                const { done: end, value } = await reader.read();
                if (end) break;
                let chunk = value;
                if (skip > 0) {
                    const cut = Math.min(skip, chunk.byteLength);
                    skip -= cut;
                    chunk = chunk.subarray(cut);
                }
                while (chunk.byteLength > 0) {
                    if (at >= partCount(file)) throw new Problem(t('Die Datei %s kam unvollständig an. Versuche es noch einmal.', file.name));
                    const take = Math.min(chunk.byteLength, into.byteLength - filled);
                    into.set(chunk.subarray(0, take), filled);
                    filled += take;
                    chunk = chunk.subarray(take);
                    done += take;
                    if (filled === into.byteLength) {
                        await put(at, into);
                        at++;
                        filled = 0;
                        into = at < partCount(file) ? new Uint8Array(partBytes(file, at)) : new Uint8Array(0);
                    }
                }
                report(Math.min(done, total), total);
            }
        } catch (error) {
            if (error instanceof Problem || signal?.aborted) throw error;
            throw new Problem(t('Die Verbindung ist abgebrochen. Versuche es noch einmal — was schon geladen ist, bleibt.'));
        }
        if (at !== partCount(file)) {
            throw new Problem(t('Die Datei %s kam unvollständig an. Versuche es noch einmal.', file.name));
        }
    }

    /* Ask the browser not to clear it when space runs short — what the
       user waited for should not vanish quietly. A refusal changes
       nothing; the browser then keeps it as long as it can. */
    try { await navigator.storage?.persist?.(); } catch { /* no harm */ }
    report(total, total);
}

/** Forget it all on this device — the strong model with it. The tool can be fetched again later. */
export async function remove() {
    engine = null;
    for (const fn of leaving) {
        try { fn(); } catch { /* its own fault */ }
    }
    if (globalThis.caches) await caches.delete(STORE);
}

/** Who must let go when the files leave the device — the strong model's engine (inpaintstrong.js). */
const leaving = new Set();
export function onRemove(fn) {
    leaving.add(fn);
}

/** What of the strong model lies on this device. */
export const storedStrong = () => stored(STRONG_FILES);

/** Forget the strong model alone; the quick one stays. */
export async function removeStrong() {
    for (const fn of leaving) {
        try { fn(); } catch { /* its own fault */ }
    }
    if (!globalThis.caches || !(await caches.has(STORE))) return;
    const store = await caches.open(STORE);
    for (const file of STRONG_FILES) for (const url of fileParts(file).urls) await store.delete(url);
}

// ---------------------------------------------------------------------------
// The question first
// ---------------------------------------------------------------------------

/**
 * Ready to run? Asks first when the files are not here — a window that
 * says what will be fetched, how much, and that the pictures stay on the
 * device — and fetches while it stays open, with a bar and „Abbrechen".
 * Yields true once everything lies here, false when the user said no or
 * stopped it.
 */
export async function ensure() {
    if ((await stored()).complete) return true;
    return consent();
}

/**
 * The same for the strong model: its three files, asked for in the same
 * window with its own words. The tool's own files come first — its runtime
 * is theirs — and are asked for in their own window where they are missing.
 */
export async function ensureStrong() {
    if (!await ensure()) return false;
    if ((await storedStrong()).complete) return true;
    return consent(true);
}

function consent(strong = false) {
    const files = strong ? STRONG_FILES : FILES;
    const total = strong ? STRONG_BYTES : TOTAL_BYTES;
    return new Promise((resolve) => {
        const box = document.createElement('dialog');
        box.className = 'dialog inpaint-consent';
        const form = document.createElement('form');
        form.method = 'dialog';

        const title = document.createElement('h2');
        title.className = 'dialog-title';
        title.textContent = strong ? t('Starkes Modell laden?') : t('KI-Werkzeug laden?');

        /* PLAIN WORDS (CLAUDE.md, „für absolute Anfänger"): what it is,
           what it costs, where it runs, how to get rid of it — one
           thought per sentence. */
        const text = document.createElement('p');
        text.className = 'dialog-text';
        /* THE STRONG MODEL'S WORDS say what one gets for the wait: it is
           large and slow, and it is for the large places. The time is
           what was measured on an M1 Pro (Chromium 28 s, Firefox 73 s). */
        text.textContent = (strong
            ? t('Das starke Modell füllt große Flächen besser: Es erfindet passende Strukturen, wo das schnelle Modell nur eine weiche Fläche liefert. Dafür lädt dieses Gerät einmalig rund %s herunter und hebt sie auf. Am besten im WLAN.', megabytes(total))
                + '\n\n' + t('Eine Stelle braucht damit etwa eine halbe bis ganze Minute.')
            : t('„Entfernen“ arbeitet mit einer künstlichen Intelligenz. Dafür lädt dieses Gerät einmalig rund %s herunter und hebt sie auf. Am besten im WLAN.', megabytes(total)))
            + '\n\n' + t('Die KI rechnet hier auf deinem Gerät. Deine Bilder schickt sie nirgendwohin.')
            + '\n\n' + t('Unter Verwaltung → Offline → Offline-Daten kannst du die Dateien jederzeit wieder löschen.');

        const progress = document.createElement('div');
        progress.className = 'area-progress';
        progress.hidden = true;
        const bar = document.createElement('div');
        bar.className = 'area-bar';
        const barInner = document.createElement('div');
        bar.append(barInner);
        const count = document.createElement('span');
        progress.append(bar, count);

        /* A failure stands INSIDE the window, in red — a toast would lie
           behind the modal (CLAUDE.md, „A question left open"). */
        const problem = document.createElement('p');
        problem.className = 'dialog-text inpaint-problem';
        problem.hidden = true;

        const footer = document.createElement('div');
        footer.className = 'dialog-footer';
        const no = document.createElement('button');
        no.type = 'button';
        no.className = 'button subtle';
        no.textContent = t('Abbrechen');
        const yes = document.createElement('button');
        yes.type = 'submit';
        yes.className = 'button';
        yes.textContent = t('Laden');
        footer.append(no, yes);

        form.append(title, text, progress, problem, footer);
        box.append(form);

        let control = null;
        let ready = false;

        no.addEventListener('click', () => box.close());
        box.addEventListener('close', () => {
            control?.abort();
            box.remove();
            resolve(ready);
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (control) return;
            control = new AbortController();
            yes.disabled = true;
            problem.hidden = true;
            progress.hidden = false;
            try {
                await download((done, all) => {
                    barInner.style.width = (done / all * 100).toFixed(1) + '%';
                    count.textContent = t('%s von %s', megabytes(done), megabytes(all));
                }, control.signal, files);
                ready = true;
                box.close();
            } catch (error) {
                if (control.signal.aborted) return;
                control = null;
                progress.hidden = true;
                problem.textContent = navigator.onLine === false
                    ? t('Keine Verbindung. Versuche es mit Netz noch einmal — was schon geladen ist, bleibt.')
                    : error instanceof Problem ? error.message : t('Das Laden hat nicht geklappt.');
                problem.hidden = false;
                yes.textContent = t('Nochmal');
                yes.disabled = false;
            }
        });

        document.body.append(box);
        box.showModal();
        yes.focus();
    });
}

// ---------------------------------------------------------------------------
// Running it
// ---------------------------------------------------------------------------

/** The started engine — { kind: 'worker'|'page', backend, run } — or its promise (running, below). */
let engine = null;

/**
 * Start onnxruntime and the model from the stored copies.
 *
 * THE GRAPHICS CARD FIRST, the processor as the fallback — measured in
 * Chromium, 10.11.2026: about 14 s to start once per visit, then 1.2 s per
 * hole on WebGPU; 15 s per hole without it.
 *
 * FIREFOX TOO, since 14.11.2026 (the maintainer works in Firefox, and there
 * every hole took 16 seconds): its WebGPU refused the shaders onnxruntime
 * writes for tensors of five dimensions, which each of LaMa's 36 Fourier
 * units used. The model file was rewritten so that no tensor has more than
 * four (LICENSE.txt, flat.py) — the same arithmetic, bit for bit on the
 * processor, 0.013 of 255 tone values at most on the card over sixteen
 * spots. Measured in Firefox 159 on an M1 Pro: 2.4 s per hole. A run that
 * fails on the card all the same still drops to wasm (fill()).
 *
 * disable_quant_qdq IS NOT AN OPTION: the file keeps its large weights as
 * int8, and only this switch makes onnxruntime fold them back to float
 * once at load instead of computing in integers, which LaMa does not
 * survive (lama_small.onnx, LICENSE.txt).
 *
 * WHY 66 MB AND NOT 37 (14.11.2026: „wir wollen keine
 * Verschlechterung durch die Verkleinerung der Dateien" — and he saw it
 * himself, in a large area: other colour values than the full model's).
 * The first copy kept the large layers as int4 and the rest as int8, and
 * on large holes its fill was tinted against the fp32 model's by 2.7 tone
 * values on average, 7.8 at the worst. Measured layer group by layer
 * group, the difference came from the SMALL group — spectral transforms,
 * down- and up-sampling, first and last layers —, not from the large one.
 * Those 34 MB are kept as half floats now and the large layers as int8:
 * 0.2 on average, 0.6 at the worst, the same as half floats throughout
 * (106 MB, more than a file may weigh on its way through the deployment).
 */
function start(backend) {
    return (async () => {
        const store = await openStore();
        const file = async (name) => {
            const f = FILES.find(x => x.name === name);
            const answer = await store.match(fileUrl(f));
            if (!answer) throw new Error(t('Das KI-Werkzeug ist nicht auf diesem Gerät.'));
            return answer.blob();
        };
        const [code, wasm, model] = await Promise.all([
            file('ort.webgpu.bundle.min.mjs'),
            file('ort-wasm-simd-threaded.asyncify.wasm'),
            file('lama_small.onnx'),
        ]);
        const ort = await import(URL.createObjectURL(new Blob([code], { type: 'text/javascript' })));
        /* AN OBJECT, NOT A FOLDER: given a string, the bundle goes looking
           for a companion .mjs beside the wasm and fails (measured). */
        ort.env.wasm.wasmPaths = { wasm: URL.createObjectURL(new Blob([wasm], { type: 'application/wasm' })) };
        /* One thread: several need cross-origin isolation, which this
           site does not send — and on the graphics card they buy nothing. */
        ort.env.wasm.numThreads = 1;
        /* Its warnings arrive as red console errors — „some nodes not
           assigned to the preferred provider" on every start, which is
           how onnxruntime always works and nothing to act on. */
        ort.env.logLevel = 'error';
        const session = await ort.InferenceSession.create(new Uint8Array(await model.arrayBuffer()), {
            executionProviders: [backend],
            logSeverityLevel: 3,   // only errors — inpaintworker.js says why
            extra: { session: { disable_quant_qdq: '1' } },
        });
        return { ort, session, backend };
    })();
}

/* --- THE ENGINE IN A WORKER (11.11.2026: „wenn ich auf
   Entfernen klicke … hängt zeitweise der Browser an sich. Ich würde
   vorschlagen, so etwas wie einen Ladebalken einzublenden") -------------
   Building the model is about fifteen seconds of WebAssembly, and a hole
   another second or so; done in the page, it held the page's one thread —
   no click answered, no bar could have moved. inpaintworker.js does it
   on a thread of its own, and the page shows that it waits.
   THE PAGE'S OWN WAY STAYS for a browser that offers the graphics card in
   the page but not in a worker: there the worker would compute on the
   processor, fifteen seconds a hole instead of one — the freeze of the
   start is the smaller loss. And for a browser that cannot make the
   worker at all. */

/** Who wants to know how the engine stands: 'starting', 'ready', 'failed'. */
const watchers = new Set();
let engineState = 'idle';
function stateSet(next) {
    engineState = next;
    for (const fn of watchers) {
        try { fn(next); } catch { /* a watcher's fault is its own */ }
    }
}
/** Be told whenever the engine starts, is ready or failed; returns the way to stop listening. */
export function onEngine(fn) {
    watchers.add(fn);
    return () => watchers.delete(fn);
}
/** How the engine stands now: 'idle', 'starting', 'ready' or 'failed'. */
export const engineNow = () => engineState;

/** The worker's address — through the import map, so it carries its version tag like every module. */
function workerUrl() {
    try {
        return import.meta.resolve('./inpaintworker.js');
    } catch {
        return new URL('./inpaintworker.js', import.meta.url).href;
    }
}

function workerStart() {
    return new Promise((done, failed) => {
        let worker;
        try {
            worker = new Worker(workerUrl(), { type: 'module' });
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
                    kind: 'worker', backend: m.backend, gpu: m.gpu, worker,
                    run(pixels, mask) {
                        return new Promise((ok, no) => {
                            const id = ++next;
                            waiting.set(id, { ok, no });
                            worker.postMessage({ type: 'fill', id, pixels, mask, side: SIDE }, [pixels.buffer, mask.buffer]);
                        });
                    },
                });
            } else if (m.type === 'failed') {
                worker.terminate();
                failed(m.problem === 'missing' ? new Problem(t('Das KI-Werkzeug ist nicht auf diesem Gerät.'))
                    : m.problem === 'cache' ? new Problem(t('Dieser Browser kann keine Dateien für später aufbewahren.'))
                    : new Error(m.message));
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
        worker.postMessage({
            type: 'start', store: STORE,
            urls: Object.fromEntries([['code', 'ort.webgpu.bundle.min.mjs'], ['wasm', 'ort-wasm-simd-threaded.asyncify.wasm'],
                ['model', 'lama_small.onnx']].map(([k, name]) => [k, fileUrl(FILES.find((f) => f.name === name))])),
        });
    });
}

/** The page's own way (start above), dressed as the worker's: run(pixels, mask) → the output. */
async function pageEngine(backend) {
    const eng = await start(backend);
    return {
        kind: 'page', backend: eng.backend,
        async run(pixels, mask) {
            const out = await eng.session.run({
                image: new eng.ort.Tensor('float32', pixels, [1, 3, SIDE, SIDE]),
                mask:  new eng.ort.Tensor('float32', mask,   [1, 1, SIDE, SIDE]),
            });
            return out[eng.session.outputNames[0]].data;
        },
    };
}

async function engineMake() {
    const pageGpu = !!navigator.gpu;
    try {
        const eng = await workerStart();
        if (pageGpu && eng.backend !== 'webgpu') {
            // The graphics card in the page but not in the worker: the page's way is the faster one.
            eng.worker.terminate();
            return pageEngine('webgpu').catch(() => pageEngine('wasm'));
        }
        return eng;
    } catch (error) {
        if (error instanceof Problem) throw error;
        return pageEngine(pageGpu ? 'webgpu' : 'wasm').catch(() => pageEngine('wasm'));
    }
}

async function running() {
    if (!engine) {
        stateSet('starting');
        engine = engineMake().then((eng) => {
            stateSet('ready');
            return eng;
        }, (error) => {
            engine = null;
            stateSet('failed');
            throw error;
        });
    }
    return engine;
}

/** Start it ahead of the first hole, so the wait comes while one paints. */
export function warm() {
    running().catch(() => {});
}

/**
 * The model's WHOLE answer for a square — three planes, 0 … 255 —, for a
 * caller that blends it in by itself (expand.js). `pixels` three planes
 * 0 … 1, `mask` SIDE × SIDE with 1 where to fill. `dead`: () => true when
 * the caller no longer wants it — asked when its turn comes (below).
 */
export function fillPlanes(pixels, mask, dead = null) {
    const mine = fillTurn.then(() => {
        if (dead?.()) throw new Skipped();
        return fillPlanesNow(pixels, mask);
    });
    fillTurn = mine.then(() => {}, () => {});
    return mine;
}

/* ONE FILL AT A TIME (15.11.2026, with „Erweitern": „wenn ich
   ein bisschen drehe, verstelle, loslasse, fängt sie an zu rechnen, dann
   drehe ich weiter, sie fängt wieder an zu rechnen. Und irgendwann hängt
   sie … ich bekomme die ganze Zeit die blinkenden Ecken aber nichts geht
   mehr"). Reproduced with four short turns in a row: the border still
   pulsed forty seconds after the last. Every frame that came to rest
   started a fill while the one before was still in the model, and one
   session was asked to run several squares at once — which it cannot:
   the second call fails or never comes back, and a failure on the
   graphics card sends the engine to the processor for the rest of the
   visit (inpaintworker.js), where a square takes many times as long. The
   places of „Entfernen" never did that, they run one after the other
   anyway (photowindow.js, aiChain); the border's preview was the first
   caller that did not.
   So the fills stand in ONE line here, whoever asks. `dead()` is asked
   when a fill's turn comes: a caller that has been overtaken meanwhile —
   the frame was moved again — says so, and its square is not computed at
   all (Skipped) instead of holding up the one that matters. */
let fillTurn = Promise.resolve();
/** Thrown by fillPlanes for a fill whose caller said it is no longer wanted when its turn came. */
export class Skipped extends Error {}

async function fillPlanesNow(pixels, mask) {
    const eng = await running();
    // The worker drops to the processor by itself where the graphics card refuses.
    if (eng.kind === 'worker') return eng.run(pixels, mask);
    try {
        return await eng.run(pixels, mask);
    } catch (error) {
        if (eng.backend !== 'webgpu') throw error;
        // A card that refuses a step at the first run: the processor, for the rest of the visit.
        engine = pageEngine('wasm');
        return (await engine).run(pixels, mask);
    }
}

/**
 * Fill the hole. image: ImageData of SIDE×SIDE, hole: Uint8Array of
 * SIDE×SIDE, non-zero where the picture is to be replaced. Yields a new
 * ImageData in which only the hole changed — everything else stays the
 * picture's own pixels, the model's version of them is not taken.
 */
export async function fill(image, hole) {
    const area = SIDE * SIDE;
    const pixels = new Float32Array(3 * area);
    const mask = new Float32Array(area);
    for (let i = 0; i < area; i++) {
        pixels[i]            = image.data[i * 4]     / 255;
        pixels[area + i]     = image.data[i * 4 + 1] / 255;
        pixels[2 * area + i] = image.data[i * 4 + 2] / 255;
        mask[i] = hole[i] ? 1 : 0;
    }
    const data = await fillPlanes(pixels, mask);

    const result = new ImageData(new Uint8ClampedArray(image.data), SIDE, SIDE);
    for (let i = 0; i < area; i++) {
        if (!hole[i]) continue;
        result.data[i * 4]     = data[i];
        result.data[i * 4 + 1] = data[area + i];
        result.data[i * 4 + 2] = data[2 * area + i];
    }
    return result;
}
