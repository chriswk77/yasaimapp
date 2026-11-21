/**
 * Test of the tile computation for offline areas.
 *
 * Both computations tested here have already been wrong once, and both
 * times it was not noticed by reading but only by measuring against the
 * real download:
 *
 *   tileList           determines WHAT is loaded. If a level is missing
 *                         here, offline it is the overview of all things
 *                         that is empty.
 *   tileCountAtSize  determines what it COSTS. The flat factor 1/4 for
 *                         512 tiles predicted 3.5 requests, needed were
 *                         8 — on the heaviest layer of all.
 *
 *   node tests/areas.mjs
 */

// areas.js pulls in the page's start data via api.js. What is tested
// here is pure computation that wants to know nothing of that — so put up
// the bare minimum before the module is loaded.
globalThis.window = { START_DATA: { baseUrl: '/', csrf: 'pruefung' } };
globalThis.localStorage = {
    _d: new Map(),
    getItem(k) { return this._d.get(k) ?? null; },
    setItem(k, v) { this._d.set(k, String(v)); },
    removeItem(k) { this._d.delete(k); },
};

const { tileList, tileCountAtSize, readableSize, ZOOM_FROM } =
    await import('../assets/js/areas.js');

let failed = 0;

function check(name, condition, seen = '') {
    if (condition) {
        console.log('  ok    ' + name);
    } else {
        console.log('  FAILED ' + name + (seen ? '  ->  ' + seen : ''));
        failed++;
    }
}

// A square of roughly 300 m in the Taunus.
const SQUARE = [
    [50.2300, 8.4400],
    [50.2300, 8.4440],
    [50.2270, 8.4440],
    [50.2270, 8.4400],
];

console.log('\nTile list');

const list = tileList(SQUARE, 18);
const levels = [...new Set(list.map(k => k.z))].sort((a, b) => a - b);

check('starts at ZOOM_FROM', levels[0] === ZOOM_FROM, 'first level ' + levels[0]);
check('reaches the requested level', levels.at(-1) === 18, 'last level ' + levels.at(-1));
check('skips no level',
    levels.every((z, i) => i === 0 || z === levels[i - 1] + 1), levels.join(','));

/* One more level quadruples the tile count — that is the warning shown in
   the window, so it also has to be true.
 *
 * MEASURED ON A LARGE AREA, and it has to be: on the 300 m square above
 * the law cannot show, because at those counts the EDGE is the whole
 * story. Its grid boxes run 2×2, 3×3, 4×5 — nine tiles become twenty,
 * not thirty-six, and the check stood red for it (24.08.2026; recomputed
 * by hand, the counting code is right). On the 5 km square the numbers
 * are big enough for the rounding to lose its weight — measured on a
 * real area the same day: 362 → 1.262 → 4.802 → 18.608, so 3.5 to 3.9
 * times per level. Hence 3.4 as the bound, and a fixture of its own
 * instead of stretching the one the structural checks above use. */
const BIG_SQUARE = [
    [50.2300, 8.4400],
    [50.2300, 8.5100],
    [50.1800, 8.5100],
    [50.1800, 8.4400],
];
const upTo17 = tileList(BIG_SQUARE, 17).length;
const upTo18 = tileList(BIG_SQUARE, 18).length;
const only18 = upTo18 - upTo17;
const only17 = upTo17 - tileList(BIG_SQUARE, 16).length;
check('the finest level grows roughly fourfold',
    only18 >= only17 * 3.4, `level 17: ${only17}, level 18: ${only18}`);

check('every tile has integer coordinates',
    list.every(k => Number.isInteger(k.x) && Number.isInteger(k.y) && Number.isInteger(k.z)));

check('no tile appears twice',
    new Set(list.map(k => `${k.z}/${k.x}/${k.y}`)).size === list.length);

console.log('\nLarger tiles (512 instead of 256)');

// Exactly the case from the measurement: few fields on a coarse level.
// Four single fields that do NOT coincide need four 512 tiles.
const scattered = [{ x: 0, y: 0 }, { x: 2, y: 0 }, { x: 0, y: 2 }, { x: 2, y: 2 }];
check('scattered fields share no tile',
    tileCountAtSize(scattered, 2) === 4,
    String(tileCountAtSize(scattered, 2)));

// Four fields that coincide in the grid share exactly one.
const bundled = [{ x: 4, y: 6 }, { x: 5, y: 6 }, { x: 4, y: 7 }, { x: 5, y: 7 }];
check('coinciding fields share one tile',
    tileCountAtSize(bundled, 2) === 1,
    String(tileCountAtSize(bundled, 2)));

check('at equal size the field count stands',
    tileCountAtSize(scattered, 1) === 4);

// The old flat factor would have said 1 here, correct is 4. Exactly this
// mix-up was the bug.
check('the flat factor would have been off fourfold here',
    tileCountAtSize(scattered, 2) !== Math.round(scattered.length / 4));

check('empty input yields zero tiles', tileCountAtSize([], 2) === 0);

console.log('\nSize display');

check('below one megabyte in kB', readableSize(500 * 1024) === '500 kB', readableSize(500 * 1024));
check('above that in MB', readableSize(5 * 1048576) === '5.0 MB', readableSize(5 * 1048576));
check('very large in GB', readableSize(3 * 1073741824) === '3.00 GB', readableSize(3 * 1073741824));

/* ---------------------------------------------------------------------------
 * THE DOWNLOAD THAT COUNTS ITSELF (27.09.2026).
 *
 * The maintainer's 350 MB at level 19 were fetched and stored in full, and then
 * the count at the end threw: Chromium refuses cache.keys() once the list
 * would pass about 10 MB — measured in the browser at between 12,000 and
 * 16,000 entries with the long WMS addresses. The row caught it, said 0 and
 * offered the whole download again. So the fake storage below refuses
 * keys() past a small limit exactly as Chromium does past its big one, and
 * everything the row needs must come from the run's own count.
 * ------------------------------------------------------------------------- */
const { loadArea, tileCount, loadedCompletely, writeDeviceState, forgetDeviceState, areaCache } =
    await import('../assets/js/areas.js');
const { deviceTakeover } = await import('../assets/js/legacy.js');
await deviceTakeover;

class FakeCache {
    constructor() { this.entries = new Map(); this.refuse = null; }
    async match(url) { return this.entries.get(String(url))?.clone(); }
    async put(url, answer) {
        const refusal = this.refuse?.(this.entries.size);
        if (refusal) throw refusal;
        this.entries.set(String(url), answer);
    }
    async delete(url) { return this.entries.delete(String(url)); }
    async keys() {
        if (this.entries.size > KEYS_LIMIT) {
            throw new DOMException("Failed to execute 'keys' on 'Cache': Operation too large.", 'AbortError');
        }
        return [...this.entries.keys()].map((url) => ({ url }));
    }
}
const KEYS_LIMIT = 10;
const stores = new Map();
globalThis.caches = {
    async open(name) { if (!stores.has(name)) stores.set(name, new FakeCache()); return stores.get(name); },
    async has(name) { return stores.has(name); },
    async delete(name) { return stores.delete(name); },
    async keys() { return [...stores.keys()]; },
};

// What each service answers: a tile, a clean „not here", a server in
// trouble, or no connection at all.
let answers = () => 'tile';
globalThis.fetch = async (url) => {
    const what = answers(String(url));
    if (what === 'offline') throw new TypeError('Failed to fetch');
    if (typeof what === 'number') return new Response('', { status: what });
    return new Response(new Uint8Array(100), { status: 200, headers: { 'Content-Length': '100' } });
};

// Two entries per spot: a plain one and a chain with a fallback.
const addresses = (k) => [
    `https://a.test/${k.z}/${k.x}/${k.y}.png`,
    [`https://b.test/${k.z}/${k.x}/${k.y}.png`, `https://c.test/${k.z}/${k.x}/${k.y}.png`],
];
const spots = tileList(SQUARE, 17).length;
const run = async (id, control = { cancel: false }, onReport = () => {}) => {
    const reports = [];
    const result = await loadArea({ id, corners: SQUARE }, 17, addresses,
        (done, total, counts) => { reports.push([done, total, counts]); onReport(done, total, control); }, control);
    return { result, reports };
};

console.log('\nThe download counts itself');

{
    const { result, reports } = await run('first');
    check('the first report comes before any tile, with the total', reports[0][0] === 0 && reports[0][1] === spots,
        JSON.stringify(reports[0].slice(0, 2)));
    check('every spot is reported once more as done', reports.length === spots + 1 && result.done === spots,
        `${reports.length} reports, done ${result.done} of ${spots}`);
    check('a fresh run stores both entries of every spot', result.stored === 2 * spots && result.present === 0,
        JSON.stringify(result));
    check('what the area holds is counted along, not listed', result.tiles === 2 * spots
        && (await caches.open(areaCache('first'))).entries.size === 2 * spots, String(result.tiles));
    check('the bytes come from the answers', result.bytes === 2 * spots * 100, String(result.bytes));
    check('more than the listing limit — the fake refuses keys() like Chromium',
        2 * spots > KEYS_LIMIT, `${2 * spots} entries, limit ${KEYS_LIMIT}`);

    const again = (await run('first')).result;
    check('a second run fetches nothing and counts what is there', again.present === 2 * spots && again.stored === 0
        && again.tiles === 2 * spots, JSON.stringify(again));
    check('only what a run stored is new weight', result.newBytes === 2 * spots * 100 && again.newBytes === 0,
        `${result.newBytes} then ${again.newBytes}`);

    // TopPlus sends no Content-Length (measured 27.09.2026): a new tile is
    // weighed by its body, a tile already there cannot be — and says so.
    answers = () => 'bare';
    const bareFetch = globalThis.fetch;
    globalThis.fetch = async (url) => answers(String(url)) === 'bare'
        ? new Response(new Uint8Array(70), { status: 200 }) : bareFetch(url);
    const bare = (await run('bare')).result;
    const bareAgain = (await run('bare')).result;
    check('without Content-Length a new tile is weighed by its body', bare.newBytes === 2 * spots * 70,
        String(bare.newBytes));
    check('and a tile already there is counted as unmeasured, not as nothing',
        bareAgain.presentUnmeasured === 2 * spots && bareAgain.bytes === 0, JSON.stringify(bareAgain));
    globalThis.fetch = bareFetch;
    answers = () => 'tile';
}

console.log('\nCounting an area');

{
    check('no cache, no tiles', await tileCount('never') === 0);
    await caches.open(areaCache('big'));
    for (let i = 0; i < KEYS_LIMIT + 5; i++) await (await caches.open(areaCache('big'))).put('https://x.test/' + i, new Response('x'));
    check('too big to list and nothing counted: UNKNOWN, not nothing', await tileCount('big') === null,
        String(await tileCount('big')));
    writeDeviceState('big', { tiles: 15 });
    check('the run\'s own count answers where keys() cannot', await tileCount('big') === 15);
    forgetDeviceState('big');
    await caches.open(areaCache('small'));
    await (await caches.open(areaCache('small'))).put('https://x.test/1', new Response('x'));
    check('a small area loaded before the count is still listed', await tileCount('small') === 1);
}

console.log('\nTiles nobody owns any more');

{
    const { evictOrphanedTiles, removeTiles } = await import('../assets/js/areas.js');
    // A clean device — the stores of the checks above are orphans too.
    stores.clear();
    // An area deleted elsewhere, under today's name and under the name
    // before 19.08.2026 — a store too big for the takeover to copy stays
    // under the old one.
    for (const name of ['area-77', 'gebiet-78', 'gebiet-12', 'area-12', 'area-borrowed']) await caches.open(name);
    writeDeviceState('borrowed', { borrowed: true });
    const removed = await evictOrphanedTiles([{ id: 12 }]);
    const left = [...stores.keys()].filter((n) => /^(area|gebiet)-/.test(n)).sort();
    check('the sweep takes the deleted area under both names and nothing else',
        removed === 2 && !left.includes('area-77') && !left.includes('gebiet-78')
        && left.includes('area-12') && left.includes('gebiet-12') && left.includes('area-borrowed'),
        `${removed} removed, left ${left.join(', ')}`);
    await caches.delete('area-12');
    check('an area known only under the old name is there but not countable', await tileCount(12) === null,
        String(await tileCount(12)));
    await caches.open('area-12');
    await removeTiles(12);
    check('„Vom Gerät löschen" takes both names', !stores.has('area-12') && !stores.has('gebiet-12'),
        [...stores.keys()].join(', '));
    forgetDeviceState('borrowed');
    await caches.delete('area-borrowed');
}

console.log('\nCancel, a full storage, a dropped connection');

{
    // One tile from yesterday stays whatever this run does.
    const yesterday = `https://a.test/13/${tileList(SQUARE, 13)[0].x}/${tileList(SQUARE, 13)[0].y}.png`;
    await (await caches.open(areaCache('cancel'))).put(yesterday, new Response('old'));
    const { result } = await run('cancel', { cancel: false }, (done, total, control) => { if (done === 2) control.cancel = true; });
    const left = [...(await caches.open(areaCache('cancel'))).entries.keys()];
    check('a cancellation takes back exactly what this run added', result.cancelled && result.removed > 0
        && left.length === 1 && left[0] === yesterday, JSON.stringify({ removed: result.removed, left }));

    const full = await caches.open(areaCache('full'));
    full.refuse = (size) => size >= 5 ? new DOMException('full', 'QuotaExceededError') : null;
    const quota = (await run('full')).result;
    check('a full storage stops the run and says so', quota.stopped === 'quota' && quota.refusal === 'QuotaExceededError'
        && quota.done < quota.total, JSON.stringify({ stopped: quota.stopped, done: quota.done, total: quota.total }));
    check('what arrived before stays for „Weiterladen"', quota.stored === 5 && full.entries.size === 5
        && !quota.cancelled, `${quota.stored} counted, ${full.entries.size} stored`);

    const broken = await caches.open(areaCache('broken'));
    broken.refuse = () => new DOMException('odd', 'UnknownError');
    const storage = (await run('broken')).result;
    // Six workers fetch at once: when the twentieth refusal stops the run,
    // the others may each have one put in flight — at most five more.
    check('twenty refusals in a row stop it too', storage.stopped === 'storage'
        && storage.unsaved >= 20 && storage.unsaved <= 25,
        JSON.stringify({ stopped: storage.stopped, unsaved: storage.unsaved }));

    answers = (url) => url.startsWith('https://a.') ? 'offline' : url.startsWith('https://b.') ? 404 : 'tile';
    const patchy = (await run('patchy')).result;
    check('a dropped connection is unreachable, a clean 404 falls through to the chain\'s fallback',
        patchy.unreachable === spots && patchy.missing === 0 && patchy.stored === spots,
        JSON.stringify({ unreachable: patchy.unreachable, missing: patchy.missing, stored: patchy.stored }));
    answers = (url) => url.startsWith('https://c.') ? 404 : url.startsWith('https://b.') ? 404 : 503;
    const gaps = (await run('gaps')).result;
    check('a server in trouble counts as unreachable, a gap in every source as missing',
        gaps.unreachable === spots && gaps.missing === spots && gaps.tiles === 0,
        JSON.stringify({ unreachable: gaps.unreachable, missing: gaps.missing }));
    answers = () => 'tile';
}

console.log('\nWhole or not');

check('nothing on record is not whole', loadedCompletely(null) === false);
check('a run that reached its end is whole', loadedCompletely({ run: { complete: true } }) === true);
check('a run that did not is not, whatever else stands there',
    loadedCompletely({ run: { complete: false }, bytes: 500 }) === false);
check('before the run record, the size written at the end marks a whole run', loadedCompletely({ bytes: 500 }) === true);
check('a level and a date alone are the start of a run that never ended', loadedCompletely({ zoom_to: 19 }) === false);

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
