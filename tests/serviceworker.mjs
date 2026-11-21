/**
 * The rule by which a code file comes from the cache or from the network.
 *
 *   node tests/serviceworker.mjs
 *
 * WHY EXACTLY THIS FUNCTION. It decides whether a correction reaches the
 * phone at all — and it got that wrong for a long time without anyone
 * being able to see it: an imported module ("./photos.js", no version in
 * the address, because there is no build step that could write one in) was
 * served cache-first, and its address never changes. Whatever was fetched
 * once stayed for ever. A fix could be deployed, the phone would go on
 * running the old file, and nothing anywhere would say so.
 *
 * That cannot be tested in the browser without deploying — which is
 * exactly the situation one wants to avoid. So the function is lifted out
 * of sw.js here and run against stubs.
 *
 * IT READS sw.js AS TEXT, deliberately: a copy of the function in the test
 * would be the same mistake one level up — it would go on passing while
 * the real one drifted away.
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const source = readFileSync(
    join(dirname(fileURLToPath(import.meta.url)), '..', 'sw.js'), 'utf8');

/** Cut one top-level function out of the file, brace for brace. */
function lift(name) {
    let at = source.indexOf(`async function ${name}(`);
    if (at < 0) at = source.indexOf(`function ${name}(`);
    if (at < 0) throw new Error(`sw.js no longer has ${name}() — has it been renamed?`);
    let depth = 0;
    for (let i = source.indexOf('{', at); i < source.length; i++) {
        if (source[i] === '{') depth++;
        else if (source[i] === '}' && --depth === 0) return source.slice(at, i + 1);
    }
    throw new Error(`${name}() is not closed`);
}

let failed = 0;
const check = (ok, what) => {
    if (ok) console.log('  ok    ' + what);
    else { failed++; console.log('  FAILED: ' + what); }
};

// --- Stubs ----------------------------------------------------------------

function makeStore(initial = {}) {
    const held = new Map(Object.entries(initial));
    return {
        held,
        match: async (request) => held.get(request.url) ?? undefined,
        put:   async (request, answer) => { held.set(request.url, answer); },
        keys:  async () => [...held.keys()].map(url => ({ url })),
        delete: async (request) => { held.delete(request.url); },
    };
}

/** Run getAppFile() in a world we control. */
async function run({ url, cached, network, name }) {
    const store = makeStore(cached === undefined ? {} : { [url]: cached });
    const waited = [];
    const asked  = [];

    const context = {
        caches: { open: async (opened) => { store.opened = opened; return store; } },
        fetch: async (request) => { asked.push(request.url); if (network instanceof Error) throw network; return network; },
        URL,
        PROGRAM: 'program',
    };

    const body = `${lift('evictOlderVersions')}\n${lift('getAppFile')}\nreturn getAppFile;`;
    const factory = new Function(...Object.keys(context), body);
    const getAppFile = factory(...Object.values(context));

    const event = { request: { url }, waitUntil: (p) => waited.push(p) };
    const answer = await (name ? getAppFile(event, name) : getAppFile(event));
    await Promise.allSettled(waited);
    return { answer, store, asked };
}

const fresh = (text) => ({ ok: true, body: text, clone() { return this; } });

// --- The cases ------------------------------------------------------------

console.log('An address with a version is immutable — cache, done');
{
    const r = await run({
        url: 'https://x/map/assets/js/map.js?v=99',
        cached: fresh('old'),
        network: fresh('new'),
    });
    check(r.answer.body === 'old', 'the stored copy is handed out');
    check(r.asked.length === 0,    'the network is not troubled at all');
}

console.log('\nAn address without a version may have changed');
{
    const url = 'https://x/map/assets/js/photos.js';
    const r = await run({ url, cached: fresh('old'), network: fresh('new') });
    check(r.answer.body === 'old',            'the start does not wait — the stored copy goes out first');
    check(r.asked[0] === url,                 'and a fresh one is fetched alongside');
    check(r.store.held.get(url).body === 'new', 'THE CORE: the next start gets the new file');
}

console.log('\nNothing stored yet');
{
    const url = 'https://x/map/assets/js/photos.js';
    const r = await run({ url, cached: undefined, network: fresh('new') });
    check(r.answer.body === 'new',              'comes from the network');
    check(r.store.held.get(url).body === 'new', 'and stays for the next time');
}

console.log('\nNo network in the woods');
{
    const url = 'https://x/map/assets/js/photos.js';
    const r = await run({ url, cached: fresh('old'), network: new Error('offline') });
    check(r.answer.body === 'old', 'the stored copy still goes out');
    check(r.store.held.get(url).body === 'old', 'and is not thrown away by the failed attempt');
}

console.log('\nA failed fetch does not overwrite');
{
    const url = 'https://x/map/assets/js/photos.js';
    const r = await run({ url, cached: fresh('old'), network: { ok: false, body: '404 page', clone() { return this; } } });
    check(r.store.held.get(url).body === 'old', 'an error page does not become the new module');
}

console.log('\nWhich files the rules take (30.09.2026, the vector map offline)');
{
    const rules = new Function('self', 'URL', `${lift('isApp')}\n${lift('isVectorAsset')}\nreturn { isApp, isVectorAsset };`)(
        { location: { origin: 'https://x' } }, URL);
    const u = (s) => new URL(s);
    check(rules.isApp(u('https://x/map/assets/vendor/maplibre/maplibre-gl.mjs')),
        'MapLibre\'s modules are app files — .mjs, or the vector map could not start without a line');
    check(rules.isApp(u('https://x/map/assets/vendor/maplibre/maplibre-gl-worker.mjs')), 'its worker too');
    check(rules.isApp(u('https://x/map/assets/js/pmtiles.js?v=3')), 'ordinary modules as before');
    check(!rules.isApp(u('https://elsewhere/map/assets/x.mjs')), 'but only from our own origin');
    check(!rules.isApp(u('https://x/map/assets/vendor/lama/ort.webgpu.bundle.min.mjs')),
        'the Entfernen tool\'s module is not — inpaint.js keeps it in a store of its own (11.11.2026)');
    check(!rules.isApp(u('https://x/map/assets/vendor/moebius/anything.js')),
        'nor anything of its strong model\'s folder (14.11.2026)');
    for (const [url, what] of [
        ['https://tiles.openfreemap.org/styles/liberty', 'the style'],
        ['https://tiles.openfreemap.org/sprites/ofm_f384/ofm@2x.json', 'the sprite'],
        ['https://tiles.openfreemap.org/fonts/Noto%20Sans%20Bold/0-255.pbf', 'a font range'],
        ['https://tiles.openfreemap.org/planet', 'the tile list'],
    ]) check(rules.isVectorAsset(u(url)), `${what} from OpenFreeMap is a vector-map file`);
    check(!rules.isVectorAsset(u('https://tiles.openfreemap.org/planet/20260927_001001_pt/14/8611/5520.pbf')),
        'a map tile is not — tiles go their own way (isTile, the area caches)');
    check(!rules.isVectorAsset(u('https://tiles.mapterhorn.com/15/17221/11040.webp')), 'nor is a terrain tile');
}

console.log('\nA vector-map file keeps its own cache');
{
    const url = 'https://tiles.openfreemap.org/styles/liberty';
    const r = await run({ url, cached: fresh('old'), network: fresh('new'), name: 'vector-assets' });
    check(r.store.opened === 'vector-assets', 'stored under vector-assets, not among the code versions');
    check(r.answer.body === 'old' && r.store.held.get(url).body === 'new',
        'the stored style at once, the fresh one for the next start');
}

console.log('\nThe patches of „Entfernen" are pictures, not pages (11.11.2026)');
{
    const rules = new Function('self', 'URL',
        `${lift('isPhoto')}\n${lift('isCloudFile')}\n${lift('isLayerMask')}\n${lift('isPage')}\nreturn { isPhoto, isPage };`)(
        { location: { origin: 'https://x' } }, URL);
    const patch = new URL('https://x/map/pages/develop_patch.php?kind=cloud&id=12&patch=1a2b3c4d5e6f7a8b');
    check(rules.isPhoto(patch), 'a patch is kept with the pictures');
    check(!rules.isPage({ mode: 'cors' }, patch), 'and never in the page cache');
    check(rules.isPage({ mode: 'cors' }, new URL('https://x/map/pages/finds.php')), 'a page is still a page');
    const mask = new URL('https://x/map/pages/layer_mask.php?set=5&key=cloud:12');
    check(!rules.isPhoto(mask) && !rules.isPage({ mode: 'cors' }, mask),
        'a layer\'s mask of „Ebenen" is neither kept nor a page — it changes with every save');
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
