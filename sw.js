/**
 * Service worker — the map even without network.
 *
 * It sits between the application and the network and answers requests
 * from the cache when nothing gets through. Three kinds of request, three
 * answers:
 *
 *   THE PAGE ITSELF    Network first, then the cache. It contains the
 *                      current state of the points, which you want fresh —
 *                      but better yesterday's than an error page.
 *
 *   CODE AND STYLES    Cache first, then the network. They carry a version
 *                      tag in the address; what was fetched once never
 *                      changes under the same address.
 *
 *   MAP TILES          Cache first, then the network — and what was newly
 *                      fetched stays. With that, everything is available
 *                      offline wherever you have looked before. For a
 *                      patch of forest never seen, there is the targeted
 *                      preloading in the application.
 *
 * WHAT NEVER COMES FROM THE CACHE: the interface under api/. Points and
 * lists change, and an old answer from there would be worse than none —
 * you would take an outdated state for the current one. Without network
 * the call fails, and the queue in the application catches it. That one
 * knows what to do; this worker does not.
 */

const VERSION   = 'v2';
const PAGES    = `pages-${VERSION}`;
const PROGRAM  = `program-${VERSION}`;

/**
 * TWO KINDS OF TILE CACHE, and the separation is the core.
 *
 *   TRANSIENT  What was loaded while passing by. A convenience nobody
 *              asked for — so kept small and expendable at any time.
 *              800 tiles are around 20 MB.
 *
 *   AREAS      What someone fetched expressly for a tour, per area its
 *              own cache named 'gebiet-<nr>'. These tiles are NEVER
 *              cleaned away automatically.
 *
 * Without this separation one eats the other: An afternoon of scrolling
 * around displaces the area loaded for tomorrow — and you notice only in
 * the forest, where it is too late.
 */
/*
 * THE NAME CARRIES A 2, and that is no cosmetics: What lay in the old
 * cache up to here was fetched without CORS and is thereby opaque — and
 * since the tiles in layers.js are fetched with crossOrigin, an opaque
 * response is not even handed to a CORS request anymore (see getTile
 * further down). So these entries are not merely counted unfavorably,
 * they have become plainly unusable.
 *
 * By themselves they never disappear: The 800 limit only ever cleans the
 * cache currently being written to — after the switch, nobody touched the
 * old one anymore. They would lie there until the site data is deleted
 * and would be carried in the storage account with the usual random
 * padding of on average 7.7 MB apiece (see the long paragraph in
 * layers.js).
 *
 * The renaming suffices: activate() deletes every cache not on the list.
 * With that, the casually collected tiles are gone once; the areas remain
 * untouched.
 */
const VOLATILE = 'tiles-volatile-2';
const VOLATILE_LIMIT = 800;

/**
 * VOUCHER PHOTOS. Their own cache, and for two reasons.
 *
 * The first is a mistake to be avoided: photo.php ends in .php and does
 * not live under api/ — without its own rule, a JPEG would pass as a
 * "page" and land among the HTML documents. An image would then displace
 * the map from the cache, and the next time without network you would
 * stand before "Kein Netz" because someone browsed photos beforehand.
 *
 * The second is the actual purpose: A photo you have taken should be
 * viewable in the dead zone too. And it pays off — the address of an
 * image points to the same bytes forever (the file name is random and is
 * never reused), hence the cache first and the network only when it is
 * missing.
 *
 * THE LIMIT is generous, because mostly preview images land in it —
 * those are around 20 kB. A few big ones in between carry no weight, and
 * cleanup goes in order: What has lain longest goes first.
 */
const PHOTOS = 'photos';
const PHOTOS_LIMIT = 300;

/**
 * THE FULL PICTURES THE DESK KEEPS (assets/js/originalcache.js, which owns
 * this store, its index, its budget and its tick in Offline-Daten — the
 * name here MUST stay the one there). The worker never writes into it; it
 * only keeps it alive across a deployment and answers from it (below).
 *
 * KEPT ACROSS A NEW WORKER since 26.11.2026: activate() deletes every store
 * not on its list, and this one was not on it — the first change to this
 * file after 18.11.2026 would have emptied two gigabytes of pictures the
 * device had chosen to keep, unnoticed, because nothing had changed here
 * since. Whoever adds a store a page owns adds it to that list too.
 */
const ORIGINALS = 'yasaimapp-originals-v1';

/**
 * THE VECTOR MAP'S OWN FILES FROM OPENFREEMAP (30.09.2026): its style, the
 * sprites of its symbols, the fonts of its lettering and the list of its
 * tiles. Without them MapLibre cannot draw a single line — and without the
 * style it could not even start: map.js then falls back to the Leaflet map,
 * so a terrain loaded for the woods would never be seen there.
 *
 * They change rarely and under the same address (the style is simply
 * "liberty"), so getAppFile's rule for bare addresses: the stored copy at
 * once, a fresh one fetched alongside. A cache of their own, because they
 * belong to no code version and must outlive every one.
 */
const VECTOR_ASSETS = 'vector-assets';

self.addEventListener('install', (e) => {
    // No preloading: The addresses of the code files carry a version tag
    // not known here. They land in the cache by themselves on first
    // request.
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil((async () => {
        // Clean away old versions — but keep the tiles, they were dearly
        // bought and are not tied to the code version. And keep 'inpaint',
        // the „Entfernen" tool's 66 MB (inpaint.js): the user agreed to
        // fetch them once, and a deployment must not make it twice.
        const names = await caches.keys();
        await Promise.all(names
            .filter(n => n !== PAGES && n !== PROGRAM && n !== VOLATILE && n !== PHOTOS && n !== VECTOR_ASSETS && n !== ORIGINALS
                      && n !== 'arten' && n !== 'species' && n !== 'inpaint' && !n.startsWith('gebiet-') && !n.startsWith('area-'))
            .map(n => caches.delete(n)));
        await self.clients.claim();
        await pagesWithoutPictures();
    })());
});

/**
 * PICTURES OUT OF THE PAGE STORE — what isPage() let in before it learned to
 * tell a picture by how it is asked for (see isScriptPicture). Measured in
 * the code on 26.11.2026: every picture of the identification keys ever
 * looked at lay among the pages, each retry under an address of its own,
 * and that store is never trimmed — on a phone too. Looking costs one
 * match per entry, once per new worker; afterwards there is nothing left
 * to find.
 */
async function pagesWithoutPictures() {
    try {
        const store = await caches.open(PAGES);
        for (const key of await store.keys()) {
            const hit = await store.match(key);
            if ((hit?.headers.get('Content-Type') ?? '').startsWith('image/')) await store.delete(key);
        }
    } catch { /* the next new worker looks again */ }
}

/** Does the address belong to a map tile? */
function isTile(url) {
    if (/\/assets\/vendor\//.test(url.pathname)) return false;
    return /^(a|b|c|server|services|sgx|www|geodaten|geoportal|gis|inspire|api)?\.?/.test(url.hostname)
        && (url.searchParams.has('bbox') || url.searchParams.has('BBOX')
            || /\/\d+\/\d+\/\d+(\.\w+)?$/.test(url.pathname)
            || /tile|wmts|\/wms/i.test(url.pathname));
}

/* .mjs TOO: MapLibre comes as ES modules (assets/vendor/maplibre), and
   without them the vector map could not start without a line. */
/* BUT NOT THE „ENTFERNEN" TOOL (assets/vendor/lama, 11.11.2026). Its
   module would match the line below, and then 120 KB of it lay twice on
   the device — once here, once in the store inpaint.js keeps for it.
   Twice would be harmless if both went away together; they do not:
   „Vom Gerät löschen" under Offline-Daten empties that store and knows
   nothing of this one. The tool's three files are fetched by inpaint.js
   alone, once, after the user said yes, and kept by it alone.
   THE SAME FOR ITS STRONG MODEL (assets/vendor/moebius, 14.11.2026): three
   files of 620 MB together. Its files are .onnx and would not match the
   line below today; the folder is named all the same, so that a file
   added there one day is never kept twice either. */
function isApp(url) {
    return url.origin === self.location.origin
        && !url.pathname.includes('/assets/vendor/lama/')
        && !url.pathname.includes('/assets/vendor/moebius/')
        && /\/assets\/.*\.(js|mjs|css|png|json)$/.test(url.pathname);
}

/** A file of the vector map's own at OpenFreeMap — see VECTOR_ASSETS. */
function isVectorAsset(url) {
    return url.hostname === 'tiles.openfreemap.org'
        && /^\/(styles|sprites|fonts)\/|^\/planet\/?$/.test(url.pathname);
}

/**
 * A voucher photo? Must be asked BEFORE isPage() — see PHOTOS.
 *
 * The cloud previews count as well, and for the same reason: they are
 * pictures of a find, they never change under their address, and in the
 * woods one wants to see them without reception. That they lie in the
 * user's own storage is a matter for the server — from here they are one
 * more picture from our own origin.
 */
function isPhoto(url) {
    if (url.origin !== self.location.origin) return false;
    if (url.pathname.endsWith('/photo.php')) return true;
    /* THE PATCHES OF „ENTFERNEN" (11.11.2026, pages/develop_patch.php) are
       pieces of a picture: a few kilobytes, never changed under their
       address (the id is random and written once), and wanted wherever
       the picture is looked at. Without this line they ended in .php on
       our origin and outside api/ — a PAGE to isPage(), the very trap the
       paragraph at isCloudFile() describes. */
    if (url.pathname.endsWith('/develop_patch.php')) return true;
    if (!isCloudFile(url)) return false;

    // BUT NOT THE ORIGINALS. A preview is a few dozen kilobytes and there
    // are hundreds of them; an original is whatever the camera made of it —
    // a stack of nine megabytes, a raw file of fifty. The photo store
    // counts entries, not bytes, so a handful of them would quietly take
    // more room than everything else together. And whoever asked for one
    // asked to LOOK at it, not to carry it around: what stays on the device
    // is the user's decision (see FLAECHEN), and this one was never made.
    return !url.searchParams.has('original');
}

/**
 * Anything cloud_thumb.php delivers — preview or original alike.
 *
 * SEPARATE FROM isPhoto() ON PURPOSE, and the reason is a trap I walked
 * into while writing the line above. isPage() asks isPhoto() to keep
 * pictures out of the page cache. The moment isPhoto() said "no" to the
 * originals, they became PAGES to that question — it ends in .php, it lies
 * on our origin, it is not under api/ — and a nine megabyte picture would
 * have gone into the cache that holds the map itself. Which is exactly the
 * mistake the paragraph at PHOTOS warns about, one file further along.
 *
 * "Not a photo to be kept" and "not a page" are two different statements.
 * Now each has its own question.
 */
function isCloudFile(url) {
    return url.origin === self.location.origin
        && url.pathname.endsWith('/cloud_thumb.php');
}

/**
 * A layer's mask of „Ebenen" (pages/layer_mask.php, 11.11.2026) — work in
 * progress that changes under its address with every save. Neither a
 * picture to keep nor a page: it always goes to the network, and without
 * this question it would have been a PAGE to the one below, like the
 * originals above once were.
 */
function isLayerMask(url) {
    return url.origin === self.location.origin
        && url.pathname.endsWith('/layer_mask.php');
}

/**
 * A picture a script of ours delivers to an <img> — a page's own picture
 * (the identification keys' figures, an archive's) that has no rule of its
 * own above. Told by HOW it is asked for, not by its address: the core
 * cannot know every page a module brings, and every one of them ends in
 * .php, lies on our origin and outside api/ — a PAGE to isPage() until
 * 26.11.2026, the trap the paragraph at isCloudFile() describes, walked
 * into once more.
 */
function isScriptPicture(request, url) {
    return request.destination === 'image'
        && url.origin === self.location.origin && /\.php$/.test(url.pathname)
        && !isCloudFile(url) && !isLayerMask(url);
}

/**
 * Such a picture from the desk's store of full pictures where a page laid
 * it down under this very address — else from the network, as before.
 *
 * WHY THE WORKER ANSWERS AND NOT THE PAGE: an <img> starts asking the
 * moment its markup is written, before any script of the page could look
 * into the store, and the identification keys write their pictures in a
 * dozen templates. Here the question comes before the network, for every
 * one of them. Only an address that never changes its bytes may be laid
 * down so (originalKeepAddress says it); keeping, the budget and the order
 * of use stay the page's business.
 */
async function getKeptPicture(request) {
    try {
        const kept = await (await caches.open(ORIGINALS)).match(request.url);
        if (kept) return kept;
    } catch { /* no store: the network */ }
    return fetch(request);
}

function isPage(request, url) {
    return request.mode === 'navigate'
        || (url.origin === self.location.origin && /\.php$/.test(url.pathname)
            && !url.pathname.includes('/api/') && !isPhoto(url) && !isCloudFile(url) && !isLayerMask(url));
}

self.addEventListener('fetch', (e) => {
    const { request } = e;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // The interface always goes to the network. See above: an old answer
    // would be worse than none at all.
    if (url.origin === self.location.origin && url.pathname.includes('/api/')) return;

    if (isPhoto(url)) {
        e.respondWith(getPhoto(request));
    } else if (isScriptPicture(request, url)) {
        e.respondWith(getKeptPicture(request));
    } else if (isPage(request, url)) {
        e.respondWith(getPage(request));
    } else if (isApp(url)) {
        e.respondWith(getAppFile(e));
    } else if (isVectorAsset(url)) {
        e.respondWith(getAppFile(e, VECTOR_ASSETS));
    } else if (isTile(url)) {
        // ONLY for drawing the map. Preloading an area calls the same
        // addresses, but via fetch() instead of through an <img> —
        // recognizable by destination. It wants the network and stores by
        // itself; serving it from the cache would mean loading an area
        // from itself.
        if (request.destination !== 'image') return;
        e.respondWith(getTile(request));
    }
});

/** Network first, then the cache — with storing for the next time. */
async function getPage(request) {
    const store = await caches.open(PAGES);
    try {
        const answer = await fetch(request);
        // A picture fetched by a script (the store of full pictures keeping one) asks as no <img> does — not a page all the same.
        if (answer.ok && !(answer.headers.get('Content-Type') ?? '').startsWith('image/')) store.put(request, answer.clone());
        return answer;
    } catch {
        const stored = await store.match(request);
        if (stored) return stored;

        // Nothing there. An empty network error would be the worst answer;
        // at least say what is going on.
        return new Response(
            '<!DOCTYPE html><meta charset="utf-8"><title>Kein Netz</title>'
            + '<body style="font:16px/1.6 system-ui,sans-serif;background:#14181c;color:#e6ebf0;padding:2rem">'
            + '<h1 style="font-size:1.3rem">Kein Netz</h1>'
            + '<p>Diese Seite war noch nie geöffnet und liegt deshalb nicht im Speicher. '
            + 'Die Karte selbst ist offline verfügbar, sobald sie einmal geladen wurde.</p>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

/**
 * A code file — and the rule depends on whether its address carries a
 * version.
 *
 * THE TRAP THIS CLOSES, and it lay open for a long time. index.php loads
 * map.js as map.js?v=1234, so a new deployment means a new address and the
 * cache lets it through. But map.js IMPORTS its twenty-odd modules by their
 * bare name — "./photos.js", without a version, because there is no build
 * step that could write one in. Their address therefore never changes, and
 * cache-first meant: whatever was fetched once stays for ever.
 *
 * Nobody noticed, because renaming the caches during the switch to English
 * (seiten-v1 → pages-v1) emptied everything once by accident. Otherwise the
 * photo fix below this comment would never have reached a single phone —
 * the new map.js would have arrived and gone on importing the old
 * photos.js.
 *
 * SO: an address with ?v= is immutable by construction — cache, done. An
 * address without one may change at any time — hand out the stored copy
 * immediately (the woods have no network) and fetch a fresh one alongside
 * for the next start. One reload late, but never stuck.
 */
async function getAppFile(event, name = PROGRAM) {
    const store = await caches.open(name);
    const stored = await store.match(event.request);

    if (stored && new URL(event.request.url).searchParams.has('v')) return stored;

    const fresh = fetch(event.request).then(async (answer) => {
        if (answer.ok) {
            await store.put(event.request, answer.clone());
            await evictOlderVersions(store, event.request.url);
        }
        return answer;
    });

    if (!stored) return fresh;

    // Let it run to the end even though the answer is already out —
    // without this the browser may stop the worker mid-fetch, and the
    // update would be lost exactly when it was almost there.
    event.waitUntil(fresh.catch(() => { /* no network: the copy stands */ }));
    return stored;
}

/**
 * Of a code file, only the just-fetched version remains.
 *
 * Each carries its version in the address (…/map.js?v=1234), and a new
 * version is thus a new key — the old one would lie there forever. On the
 * server that would be another set of dead files after every deployment;
 * here in development, where the version is the time of day, it was
 * fourteen copies of the same file after one morning.
 *
 * And worse than the space: If the PAGE is served from the cache, it
 * calls its old version number — and promptly gets the old state from
 * this cache. You change something and do not see it.
 */
async function evictOlderVersions(store, url) {
    const path = new URL(url).pathname;
    for (const request of await store.keys()) {
        if (request.url !== url && new URL(request.url).pathname === path) {
            await store.delete(request);
        }
    }
}

/**
 * Tiles: cache first, then the network.
 *
 * A tile fetched once practically never changes — an aerial image is
 * flown anew every few years. So fetching it again every time would be
 * wasted bandwidth; on the phone even expensive bandwidth.
 */
/**
 * Counter: How many tiles came from the cache, how many from the network?
 *
 * Not ornament. "Does that really come from the cache?" can hardly be
 * answered from outside — the browser has its own intermediate cache, and
 * a fast answer proves nothing at all. In here it is one line.
 * To be queried via postMessage({type:'tile-counter'}). Nothing in the
 * application sends this any more — it is a console tool for measuring,
 * which is also why renaming the message was free.
 */
let fromCache = 0;
let fromNetwork = 0;

async function getTile(request) {
    // Without a name, caches.match searches ALL caches — the areas
    // included. That is exactly how it should be: Where a tile comes from
    // does not matter when displaying; it only has to be there.
    const stored = await caches.match(request);

    // An opaque response is only good for a request that was itself made
    // without CORS. Handed to a cors request, the browser rejects it —
    // namely as "Failed to fetch" on the page, where nobody suspects this
    // worker of it. The filtered layers fetch their tiles exactly that
    // way: with crossOrigin, because they send them through a canvas.
    if (stored && !(request.mode === 'cors' && stored.type === 'opaque')) {
        fromCache++;
        return stored;
    }

    fromNetwork++;
    const answer = await fetch(request);
    if (answer.ok || answer.type === 'opaque') {
        // Newly fetched things land in the transient cache, never in an
        // area. Areas are filled only by whoever creates them expressly.
        const store = await caches.open(VOLATILE);
        store.put(request, answer.clone()).then(cleanUp);
    }
    return answer;
}

/**
 * A voucher photo — cache first, then the network.
 *
 * The same address delivers the same image forever, so the old answer is
 * never stale. And whoever wants to check in the forest which find they
 * photographed a moment ago gets it even without reception.
 *
 * NO SUBSTITUTE ANSWER ON FAILURE. An <img> that gets nothing shows its
 * broken symbol — that is understandable. Delivering the HTML page
 * "Kein Netz" to an image element would not be.
 */
async function getPhoto(request) {
    const store = await caches.open(PHOTOS);

    const stored = await store.match(request);
    if (stored) return stored;

    const answer = await fetch(request);
    // Store only successful images. Caching a 401 along would mean that
    // after the next sign-in, "not allowed" would still come back.
    if (answer.ok) {
        store.put(request, answer.clone()).then(cleanUpPhotos);
    }
    return answer;
}

let cleaningPhotos = false;
async function cleanUpPhotos() {
    if (cleaningPhotos) return;
    cleaningPhotos = true;
    try {
        const store = await caches.open(PHOTOS);
        const all = await store.keys();
        if (all.length <= PHOTOS_LIMIT) return;
        // In order, the oldest stand in front.
        for (const key of all.slice(0, all.length - PHOTOS_LIMIT)) {
            await store.delete(key);
        }
    } catch (e) {
        console.warn('Fotospeicher lässt sich nicht aufräumen:', e);
    } finally {
        cleaningPhotos = false;
    }
}

/**
 * Keep the transient cache within bounds.
 *
 * Only this one — loaded areas remain untouched. The order of the keys
 * is that of insertion, so the oldest stand in front.
 */
let cleaning = false;
async function cleanUp() {
    if (cleaning) return;
    cleaning = true;
    try {
        const store = await caches.open(VOLATILE);
        const all = await store.keys();
        if (all.length <= VOLATILE_LIMIT) return;
        for (const k of all.slice(0, all.length - VOLATILE_LIMIT)) {
            await store.delete(k);
        }
    } finally {
        cleaning = false;
    }
}

/*
 * 'fluechtig-leeren' USED TO LISTEN HERE TOO — the receiver of the button
 * "Zwischenspeicher leeren" that existed in the administration and in the
 * map's areas window. Both buttons are gone: They advertised with a number
 * from navigator.storage.estimate(), and with opaque tiles that number is
 * made up (see layers.js). A button that promises to free 800 MB that
 * never existed is worse than none.
 *
 * Cleanup continues, just without anyone's doing: cleanUp() holds the
 * transient cache at VOLATILE_LIMIT entries, oldest first.
 */
self.addEventListener('message', (e) => {
    if (e.data?.type === 'tile-counter') {
        e.source?.postMessage({ type: 'tile-counter', fromCache, fromNetwork });
    }
});
