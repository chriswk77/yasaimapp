/**
 * Areas for offline use — outline a patch of forest and fetch the tiles ahead.
 *
 * WHY THIS CANNOT HAPPEN ON THE SIDE
 * Tiles are expensive. A square of 5 × 5 km needs around 3,500 tiles and 86 MB
 * up to zoom level 18; include 19 and it is 13,900 and 340 MB. Whoever loads
 * that unasked fills other people's phones. So nothing here happens by itself:
 * you draw an area, see count and size BEFOREHAND, and decide.
 *
 * THE POLYGON IS NOT COSMETIC. A patch of forest is rarely rectangular;
 * loading via the enclosing rectangle quickly fetches twice as many tiles as
 * necessary. Each tile is therefore checked for whether it TOUCHES the
 * polygon — not for where its middle lies, which is a different question and
 * was the wrong one; see tileTouches().
 *
 * TWO WORK STEPS, and that is not cosmetic either. Outlining is done at the
 * desk: where does the patch of forest lie? Loading is done before the trip,
 * on the device that comes along: which layers, up to which level, keep how
 * long? Asking both in one window means presenting someone, while outlining,
 * with numbers that do not interest them there.
 *
 * EXPIRY DATE — for the tiles, not for the outline. A patch of forest does not
 * cease to exist; the maps for it, though, you want rid of after the vacation,
 * and nobody thinks of clearing them away. The deadline therefore lives in the
 * device's storage, next to the tiles it applies to.
 *
 * ONE CACHE PER AREA. Deleting is thereby a single call instead of bookkeeping
 * over which tile belongs to which area — and two overlapping areas cannot
 * take tiles away from each other. The price is tiles stored twice in the
 * overlap; that is the better trade than bookkeeping that can drift apart.
 *
 * OUTLINE ON THE SERVER, TILES ON THE DEVICE. An area is drawn at the desk
 * with the mouse; it has to be loaded where you will later state: on the
 * phone. If both lived in the browser — as in the first version — the area
 * would be known only on the device where it was created.
 *
 * How a device recognizes whether it has already loaded an area follows from
 * that by itself: it looks in its own storage. No bookkeeping that would have
 * to be reconciled between devices.
 */

import { api } from './api.js';
import { deviceTakeover } from './legacy.js';

const SNAPSHOT_KEY = 'map.areas.snapshot';

/** Zoom levels that always come along — below them you see nothing anyway. */
export const ZOOM_FROM = 13;

// ---------------------------------------------------------------------------
// Tile arithmetic
// ---------------------------------------------------------------------------

/** Longitude → tile column. */
function column(lon, z) {
    return Math.floor((lon + 180) / 360 * 2 ** z);
}

/** Latitude → tile row (Mercator). */
function row(lat, z) {
    const r = lat * Math.PI / 180;
    return Math.floor((1 - Math.log(Math.tan(r) + 1 / Math.cos(r)) / Math.PI) / 2 * 2 ** z);
}

/**
 * Does the point lie in the polygon? Ray casting.
 *
 * The same computation as for the state borders of the aerial imagery — there
 * it decides which official service is responsible, and here, which tile is
 * needed.
 */
function inPolygon(lat, lon, corners) {
    let inside = false;
    for (let i = 0, j = corners.length - 1; i < corners.length; j = i++) {
        const [ai, bi] = corners[i];
        const [aj, bj] = corners[j];
        if ((bi > lon) !== (bj > lon)
            && lat < (aj - ai) * (lon - bi) / (bj - bi) + ai) {
            inside = !inside;
        }
    }
    return inside;
}

/** The geographic edges of a tile: [north, south, west, east]. */
function tileBounds(x, y, z) {
    const n = 2 ** z;
    const lat = (v) => {
        const k = Math.PI - 2 * Math.PI * v / n;
        return 180 / Math.PI * Math.atan(0.5 * (Math.exp(k) - Math.exp(-k)));
    };
    return [lat(y), lat(y + 1), x / n * 360 - 180, (x + 1) / n * 360 - 180];
}

/** Do the two segments cross? Standard orientation test. */
function segmentsCross(p1, p2, p3, p4) {
    const dir = (a, b, c) => (c[1] - a[1]) * (b[0] - a[0]) - (b[1] - a[1]) * (c[0] - a[0]);
    const d1 = dir(p3, p4, p1), d2 = dir(p3, p4, p2);
    const d3 = dir(p1, p2, p3), d4 = dir(p1, p2, p4);
    return ((d1 > 0) !== (d2 > 0)) && ((d3 > 0) !== (d4 > 0));
}

/**
 * Does the tile touch the area — anywhere, not just with its middle?
 *
 * THE MIDDLE WAS THE WRONG QUESTION, and it cost half the tiles. It stood
 * here as "from level 15 on the check pays off", and the reasoning was
 * sound: at coarse levels a tile is bigger than the whole area, so its
 * centre lies outside and it is needed all the same. What the reasoning
 * missed is that the same is true at the EDGE of every area, at every
 * level — a tile that covers a good part of the area but whose middle
 * happens to fall just outside was not fetched.
 *
 * Measured on a real area of the maintainer's ("Goldsteintal", 7 corners), share
 * of touching tiles that were missing:
 *
 *   level 15   50 %      level 17   20 %
 *   level 16   43 %      level 18   11 %
 *
 * Which is exactly what he saw: below 15 the map was complete, from 15 on
 * it was full of holes, and worst where the tiles are largest.
 *
 * Three questions, and the third is the one that is easy to forget: a
 * corner of the tile inside, a corner of the area inside the tile, or an
 * edge of the area crossing the tile without either. A long thin strip
 * running through a tile satisfies only the last.
 */
function tileTouches(x, y, z, corners) {
    const [north, south, west, east] = tileBounds(x, y, z);

    const tileCorners = [[north, west], [north, east], [south, east], [south, west]];
    if (tileCorners.some(([la, lo]) => inPolygon(la, lo, corners))) return true;
    if (corners.some(([la, lo]) => la <= north && la >= south && lo >= west && lo <= east)) return true;

    for (let i = 0, j = tileCorners.length - 1; i < tileCorners.length; j = i++) {
        for (let m = 0, n = corners.length - 1; m < corners.length; n = m++) {
            if (segmentsCross(tileCorners[j], tileCorners[i], corners[n], corners[m])) return true;
        }
    }
    return false;
}

/**
 * All tiles a polygon touches.
 *
 * @returns {Array<{x:number,y:number,z:number}>}
 */
export function tileList(corners, zoomTo) {
    const latitudes = corners.map(e => e[0]);
    const longitudes = corners.map(e => e[1]);
    const north = Math.max(...latitudes), south = Math.min(...latitudes);
    const west = Math.min(...longitudes), east = Math.max(...longitudes);

    const list = [];
    for (let z = ZOOM_FROM; z <= zoomTo; z++) {
        const x1 = column(west, z), x2 = column(east, z);
        const y1 = row(north, z),  y2 = row(south, z);

        for (let x = x1; x <= x2; x++) {
            for (let y = y1; y <= y2; y++) {
                // The same question at every level now — see tileTouches().
                // The old threshold of 15 was an admission that the middle
                // is the wrong test: below it everything in the box was
                // taken, above it half the edge was thrown away.
                if (!tileTouches(x, y, z, corners)) continue;
                list.push({ x, y, z });
            }
        }
    }
    return list;
}

/**
 * How many tiles of edge length `factor × 256` cover these cells?
 *
 * Not `fields / factor²` — that is the mistake that put the size figure for
 * the terrain hillshade off by more than a factor of two: four cells share a
 * 512 tile only if all four of them lie in the area and coincide in the grid.
 * At the coarse levels and along the edge hardly any does. The estimate had
 * computed 3.5 requests; 8 were needed.
 */
export function tileCountAtSize(fields, factor) {
    if (factor === 1) return fields.length;

    const seen = new Set();
    for (const k of fields) seen.add(Math.floor(k.x / factor) + '/' + Math.floor(k.y / factor));
    return seen.size;
}

/** Rough size estimate. Measured, aerial imagery tiles run 20 to 30 kB. */
export function estimateSize(total) {
    return total * 25 * 1024;
}

export function readableSize(bytes) {
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' kB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / 1048576).toFixed(bytes > 10485760 ? 0 : 1) + ' MB';
    return (bytes / 1073741824).toFixed(2) + ' GB';
}

// ---------------------------------------------------------------------------
// Directory of the areas
// ---------------------------------------------------------------------------

/**
 * Fetch the areas from the server.
 *
 * The local mirror is only the fallback for the dead zone: without a network
 * you should at least SEE which areas exist and which of them lie on this
 * device — creating and loading is impossible then anyway.
 */
export async function getAreas() {
    try {
        return { list: await areasFromServer(), fromServer: true };
    } catch (e) {
        if (!e?.isNetworkError) throw e;
        return { list: areasFromSnapshot(), fromServer: false };
    }
}

/**
 * From the server only, without fallback.
 *
 * Separate, because for some decisions the provenance is what tips the
 * scales: what is no longer in the directory counts as deleted elsewhere and
 * its tiles get cleared away. If this directory came from the local mirror,
 * an empty mirror — after clearing the site data, say — would sweep every
 * loaded area off the device. Without a network nothing is therefore
 * cleared; this is nothing that is urgent.
 */
export async function areasFromServer() {
    const answer = await api.get('areas');
    writeSnapshot(answer.areas);
    return answer.areas;
}

/** The most recently fetched directory, without network access. */
export function areasFromSnapshot() {
    try {
        const d = JSON.parse(localStorage.getItem(SNAPSHOT_KEY) || '[]');
        return Array.isArray(d) ? d : [];
    } catch {
        return [];
    }
}

/**
 * Keep the mirror up to date.
 *
 * IT IS NOT JUST A CACHE, but what the overview displays in the dead zone —
 * and since the administration first draws from it and then asks the server,
 * also what you get to see first. Whoever fails to maintain it shows deleted
 * things.
 */
function writeSnapshot(list) {
    try { localStorage.setItem(SNAPSHOT_KEY, JSON.stringify(list)); } catch { /* doesn't matter */ }
}

export async function createArea(area) {
    // Only name and outline. Zoom level and deadline belong to loading, not
    // to creating — they live in the storage of the device that loads.
    /* ONE NAME, 'created', throughout. The declaration used to say 'neu'
       while every reader below said 'fresh' — half of a rename. That was
       not cosmetic: 'fresh' was declared nowhere, so the first read threw
       a ReferenceError AFTER the POST had succeeded. The area existed on
       the server, and the browser reported the creation as failed. */
    const created = await api.post('areas', { name: area.name, corners: area.corners });

    // WITHOUT A NETWORK IT STILL HAS TO BE THERE. In the dead zone the
    // overview reads from the local mirror — and that does not know an area
    // that has only just come into being. Whoever outlined a patch of forest
    // in the woods used to see a confirmation and then an empty list.
    if (created?.pending) {
        const snapshot = areasFromSnapshot();
        snapshot.push({ id: created.id, name: created.name, corners: created.corners, pending: true,
                      measurements: null, measured_on: null });
        writeSnapshot(snapshot);
    }
    return created;
}

/**
 * Change the outline (or the name).
 *
 * The mirror is kept up to date as when fetching — the overview in the
 * administration draws from it FIRST, and a just-changed outline that still
 * looks old there would make you doubt the change. With new corners the
 * server nulls the measurements itself (gebiet_aendern): they quantify the
 * old surface.
 *
 * No queue fallback as when creating: changes are made at the desk with a
 * network. Without a connection the call fails honestly, the handles stay in
 * place, and Save can be pressed again.
 */
export async function updateArea(id, change) {
    const answer = await api.patch('areas', id, change);
    if (Array.isArray(answer?.areas)) {
        writeSnapshot(answer.areas);
    }
    return answer;
}

/** Store the measured sizes so that no other device has to measure anew. */
export async function rememberMeasurements(id, values) {
    try {
        await api.patch('areas', id, { measurements: values });
    } catch (e) {
        // If that fails, measuring just happens again next time. It is a
        // shortcut, not a prerequisite.
        if (!e?.isNetworkError) console.warn('Measurements not stored:', e);
    }
}

// ---------------------------------------------------------------------------
// What this device knows about an area
// ---------------------------------------------------------------------------

const DEVICE = 'map.areas.device';

/**
 * Zoom level and deadline of the loaded tiles — per area, only here.
 *
 * As shared values on the server they were wrong: on the phone you may take
 * only up to level 17 and for two weeks, on the tablet everything and
 * permanently. One device would have overwritten the other's decision.
 */
function deviceState() {
    try {
        const d = JSON.parse(localStorage.getItem(DEVICE) || '{}');
        return d && typeof d === 'object' ? d : {};
    } catch {
        return {};
    }
}

export function readDeviceState(id) {
    return deviceState()[id] ?? null;
}

export function writeDeviceState(id, state) {
    const all = deviceState();
    all[id] = state;
    try { localStorage.setItem(DEVICE, JSON.stringify(all)); } catch { /* then just not */ }
}

export function forgetDeviceState(id) {
    const all = deviceState();
    delete all[id];
    try { localStorage.setItem(DEVICE, JSON.stringify(all)); } catch { /* doesn't matter */ }
}

/**
 * Clear away expired tiles — on this device, for this device.
 *
 * The outline stays: a patch of forest does not cease to exist just because
 * the maps for it have grown old. Reloading is one press of a button.
 */
export async function evictExpiredTiles() {
    const today = new Date().toISOString().slice(0, 10);
    const all = deviceState();
    let removed = 0;

    for (const [id, state] of Object.entries(all)) {
        if (!state?.valid_until || state.valid_until >= today) continue;
        await deleteTileStores(id);
        forgetDeviceState(id);
        removed++;
    }
    return removed;
}

/**
 * Remove an area entirely: the outline on the server, the tiles here.
 *
 * On the other devices their tiles stay put for now — on the next opening
 * they notice that the area no longer exists, and clean up themselves.
 * Anything else would not work anyway: nobody can reach foreign storage.
 */
export async function removeArea(id) {
    await removeTiles(id);
    // ALSO FROM THE MIRROR, and before the server call at that: in the dead
    // zone the call can land in the queue and would arrive hours later. If
    // the area stayed in the mirror meanwhile, the overview would show an
    // area the user has just deleted — and the administration draws its list
    // from the mirror first.
    writeSnapshot(areasFromSnapshot().filter(g => String(g.id) !== String(id)));
    await api.remove('areas', id);
}

/** Only this device's tiles; the outline stays. */
export async function removeTiles(id) {
    await deleteTileStores(id);
    forgetDeviceState(id);
}

/* THE NAME BEFORE 19.08.2026 was 'gebiet-<id>', and legacy.js copies such a
   store over to 'area-<id>' — by listing it with keys(). A store too big
   for that list (see tileCount) can never be copied: it stays under the
   old name, still serves its tiles through caches.match(), and nothing
   that counts, expires or removes looked for it (27.09.2026, the maintainer
   asking whether the tiles of an area he deleted weeks ago still haunt his
   phone). So every removal takes both names. Our own old word, kept as
   data like the ledger's file names. */
const LEGACY_AREA_CACHE = 'gebiet-';

async function deleteTileStores(id) {
    const gone = await caches.delete(areaCache(id));
    const old = await caches.delete(LEGACY_AREA_CACHE + id);
    return gone || old;
}

/**
 * How many tiles this device has for an area — or null when nobody can say.
 *
 * First has(), then open(): open() CREATES what it does not find. Called
 * merely for counting, it would thus leave behind an empty cache for every
 * never-loaded area — and "Vom Gerät nehmen" would look as if it had not
 * worked, because the name promptly reappeared.
 *
 * THE DOWNLOAD'S OWN COUNT COMES FIRST (27.09.2026). Counting by keys()
 * has a ceiling: Chromium refuses the list once it would pass about 10 MB
 * („Operation too large") — measured at between 12,000 and 16,000 entries
 * with the long WMS addresses, and 40,000 short ones fail as well.
 * The maintainer's area at level 19 held well over 100,000; the count threw,
 * this function caught it and said 0, and the row offered „Herunterladen"
 * for 350 MB lying right there. So loadArea() keeps the number itself
 * (device state `tiles`), keys() is asked only for areas loaded before
 * that, and a count that fails is UNKNOWN, never nothing.
 */
export async function tileCount(id) {
    await deviceTakeover;
    try {
        if (!(await caches.has(areaCache(id)))) {
            // Only a store under the old name — one too big for the
            // takeover to copy (see LEGACY_AREA_CACHE): there, but not
            // countable.
            return (await caches.has(LEGACY_AREA_CACHE + id)) ? null : 0;
        }
    } catch {
        return null;
    }
    const counted = readDeviceState(id)?.tiles;
    if (Number.isFinite(counted)) return counted;
    try {
        const c = await caches.open(areaCache(id));
        return (await c.keys()).length;
    } catch {
        return null;
    }
}

/**
 * Whether the last download of an area went through to the end: every
 * spot visited, nothing cancelled, the storage never refused, and no tile
 * lost to a dropped connection. Areas loaded before the run record
 * (27.09.2026) count as whole where their end wrote the size — that is
 * the one mark a finished run left back then; the run that broke at the
 * count never got that far.
 */
export function loadedCompletely(state) {
    if (!state) return false;
    if (state.run) return state.run.complete === true;
    return state.bytes !== undefined;
}

/**
 * Clear away tiles of areas that no longer exist.
 *
 * This is the way a device learns of a deletion on another one: what lies in
 * the storage but is no longer in the directory has become superfluous.
 */
/**
 * The prefix of an area's own tile cache — ONE place.
 *
 * It stood written out eight times, and after the rename not all eight said
 * the same thing any more. Two of them cost the whole feature: the
 * downloading wrote under the old name, and a length was taken from the old
 * word. Neither showed as an error — the tiles simply were not there.
 */
export const AREA_CACHE = 'area-';
export function areaCache(id) { return AREA_CACHE + id; }

export async function evictOrphanedTiles(areas) {
    // The takeover first: it may still be copying an old store over.
    await deviceTakeover;
    const known = new Set(areas.map(g => String(g.id)));
    let removed = 0;
    for (const name of await caches.keys()) {
        // Both names — see LEGACY_AREA_CACHE. An old store of an area that
        // still exists stays: it serves its tiles, and „Vom Gerät löschen"
        // takes it along.
        const prefix = [AREA_CACHE, LEGACY_AREA_CACHE].find((p) => name.startsWith(p));
        if (!prefix) continue;
        // The length comes from the prefix itself and not from a word typed
        // out again. 'gebiet-' stood here, and it is two characters longer
        // than 'area-' — so 'area-12' did not yield '12' but the EMPTY
        // STRING, and that is in no directory. Every area therefore counted
        // as orphaned, and this function threw away exactly the tiles it
        // exists to keep. Measured, not reasoned: slice(7) on a name of
        // seven characters gives ''.
        const id = name.slice(prefix.length);
        if (known.has(id)) continue;
        /* A BORROWED AREA IS NO ORPHAN (09.09.2026). An excursion's area
           belongs to its LEADER, and this directory says what the reader
           OWNS — so a participant's freshly downloaded tiles were gone
           within seconds of arriving, and the offer at the excursion's
           door was a lie (measured: the startup sweep runs 3 s after
           load, the handover starts the download after 2). The mark sits
           in the device state, so both sweepers — the map at startup and
           the administration's Offline tab — respect it without either
           having to know what an excursion is. It comes with a
           valid_until of the excursion's last day, so the loan ends by
           itself through the expiry above. */
        if (readDeviceState(id)?.borrowed) continue;
        await caches.delete(name);
        forgetDeviceState(id);
        removed++;
    }
    return removed;
}


/**
 * How much space an area's tiles really occupy.
 *
 * Content-Length first: the header is readable on CORS responses and costs
 * nothing. Only where it is missing — on opaque responses from services
 * without the clearance — must the body be read, and with thousands of tiles
 * that is the expensive way.
 *
 * Why not simply count along while loading: whoever extends an area fetches
 * only the missing tiles, and the sum would then be just the top-up instead
 * of the holdings.
 */
export async function areaSize(id) {
    await deviceTakeover;
    if (!(await caches.has(areaCache(id)))) return 0;

    const store = await caches.open(areaCache(id));
    let bytes = 0;
    for (const request of await store.keys()) {
        const answer = await store.match(request);
        if (!answer) continue;

        const length = Number(answer.headers.get('Content-Length'));
        if (length > 0) { bytes += length; continue; }
        try { bytes += (await answer.blob()).size; } catch { /* opaque */ }
    }
    return bytes;
}

/**
 * Clear away tiles of services that must not be preloaded.
 *
 * They can only date from the time when preloading still treated every layer
 * alike. Leaving them there would be wrong in two respects: they should never
 * have been fetched, and they are precisely the ones that can contain a block
 * notice.
 *
 * THIS IS THE TREACHEROUS PART. Whoever loads too much gets from
 * OpenStreetMap not a 403 as status code but a perfectly normal response with
 * 200 — and in it an image that reads "Access Blocked". To every cache that
 * looks like a valid tile. Once stored, the map keeps showing the text even
 * when the block has long been lifted.
 *
 * @param {string[]} hosts  host names that do not belong in the area
 */
export async function evictForeignTiles(hosts) {
    if (!hosts.length) return 0;
    let removed = 0;

    for (const name of await caches.keys()) {
        if (!name.startsWith(AREA_CACHE)) continue;
        const store = await caches.open(name);
        /* A CACHE TOO BIG TO LIST IS LEFT ALONE — keys() refuses past
           about 10 MB (see tileCount). Such an area was loaded after the
           volunteer services were barred from prefetching, so it holds
           none of their tiles; a throw here would only stop the sweep for
           every smaller area behind it. */
        let requests;
        try { requests = await store.keys(); } catch { continue; }
        for (const request of requests) {
            const host = new URL(request.url).hostname;
            if (!hosts.some(h => host === h || host.endsWith('.' + h))) continue;
            await store.delete(request);
            removed++;
        }
    }
    return removed;
}

/*
 * HERE STOOD speicherstand(), which passed navigator.storage.estimate() on.
 *
 * "Without this number you guess, and guessing is the wrong answer where
 * storage space is concerned" stood next to it as justification — the thought
 * was right, the number just was not fit for it. It counts opaque tiles with
 * a deliberate random surcharge of 7.7 MB apiece on average (measured, see
 * layers.js) and did not come back down after deleting entire caches either.
 * So it was the one guessing, not us.
 *
 * What the device really holds is told by tileCount(id) per area and its
 * size in real bytes — both from our own storage and our own count, never
 * from the browser's estimate. That is what everyone asks in this window
 * anyway: not "how full is the device" but "do I have THIS area". Since
 * 27.09.2026 the download counts both along (loadArea); reading them back
 * from the storage stays only for areas loaded before, and only as far as
 * keys() still lists them.
 */

// ---------------------------------------------------------------------------
// Loading
// ---------------------------------------------------------------------------

/**
 * Fetch and store an area's tiles.
 *
 * @param {object} area
 * @param {number} zoomTo  up to where THIS device loads
 * @param {(tile:object) => Array<string|string[]>} addresses  yields all
 *        URLs of a tile. Only the map layer knows that: the aerial imagery
 *        is a stack of several official services, and these are WMS
 *        requests with bounds conversion, not ready-made tile addresses. So
 *        Leaflet itself is asked — what it returns is by definition exactly
 *        what the map will later request. The same tile gives the same
 *        addresses every time, which the cancellation below relies on.
 * @param {(done:number, total:number, counts:object) => void} report
 *        called once before the first spot (done 0) and after every spot
 * @param {{cancel:boolean}} control  settable to true from outside
 */
export async function loadArea(area, zoomTo, addresses, report, control) {
    /* THE NAME COMES FROM ONE PLACE — see AREA_CACHE. Downloading wrote into
       'area-<id>' while everything that reads, counts and deletes looked
       under 'area-<id>'. The tiles were fetched, paid for in the user's
       time and data, and stored where nobody would ever look for them: the
       area kept showing "nothing on the device" however long one waited. */
    const store = await caches.open(areaCache(area.id));
    const tiles = tileList(area.corners, zoomTo);

    /* THE RUN COUNTS ITSELF, AND NOTHING COUNTS AFTERWARDS (27.09.2026).
       Afterwards is where it broke: the last line of this function used to
       be keys() over the whole area, and past about 10 MB of addresses
       Chromium refuses that list (see tileCount). The maintainer's 350 MB at
       level 19 were fetched and stored in full — and then the count threw,
       the run was reported as broken off, and the row offered the whole
       download again. `present` were there already, `stored` came now;
       together they are what this area holds on the device. The bytes come
       from the answers themselves — Content-Length where the service grants
       CORS; an opaque tile weighs nothing anybody could read. */
    const counts = {
        present: 0, stored: 0, missing: 0, unreachable: 0, unsaved: 0,
        // bytes: every size that could be read; newBytes: those of the
        // tiles this run stored; presentUnmeasured: tiles already here
        // whose size nothing says — the services send no Content-Length
        // (measured on TopPlus: cache-control and content-type, nothing
        // more), and reading every stored body back is the pass that was
        // just taken out. The caller decides what the size can honestly be.
        bytes: 0, newBytes: 0, presentUnmeasured: 0,
    };
    let done = 0;
    let stopped = null;   // 'quota' | 'storage' — the storage refused, see below
    let refusal = null;   // the name of the last refusal, for the record
    let refusedInARow = 0;

    /* WHAT THIS RUN ADDED, so that a cancellation can take exactly that
       back (22.08.2026). Not the whole cache: whoever extends
       an already loaded area to a finer level and then cancels would
       otherwise lose the maps they walked with yesterday.

       KEPT AS NUMBERS, NOT AS ADDRESSES. A hundred thousand WMS addresses
       of 250 characters are some 80 MB of strings in a phone's renderer,
       and a renderer that dies takes the app's recorder with it (the app
       session, 27.09.2026). Spot, entry and link of the chain say the same
       — addresses() hands out the same address for them again. */
    let added = new Uint32Array(8192);
    let addedCount = 0;
    const remember = (spot, entry, link) => {
        if (addedCount * 2 + 2 > added.length) {
            const bigger = new Uint32Array(added.length * 2);
            bigger.set(added);
            added = bigger;
        }
        added[addedCount * 2] = spot;
        added[addedCount * 2 + 1] = (entry << 8) | link;
        addedCount++;
    };

    // Few at a time: the official services are slow, and they belong to
    // someone else. Six in parallel is brisk without flattening anyone.
    const atOnce = 6;
    let next = 0;
    const running = () => !control.cancel && stopped === null;

    /* WHAT A WORKER IS FETCHING RIGHT NOW, by address. A 512 tile — the
       vector map's terrain and base map — covers four fields of the 256
       grid, and six workers walk neighbouring fields at once: each found
       the tile not yet stored and fetched it again. Measured 01.10.2026 on
       a three-tile area of the Europe file: every tile went two or three
       times through the relay to pCloud, and counted twice in the bytes.
       Now the second waits for the first and finds the tile stored. */
    const inFlight = new Map();
    const countPresent = (response) => {
        counts.present++;
        const known = Number(response.headers.get('Content-Length')) || 0;
        if (known > 0) counts.bytes += known;
        else counts.presentUnmeasured++;
    };

    report(0, tiles.length, { ...counts });

    async function workers() {
        while (next < tiles.length && running()) {
            const spot = next++;
            const entries = addresses(tiles[spot]);

            for (let e = 0; e < entries.length && running(); e++) {
                // AN ENTRY CAN BE A CHAIN. The worldwide aerial imagery has
                // two sources: the one with the better colors first, the
                // complete one as safety net. Stored is exactly the one that
                // answered — not both. Otherwise every tile would occupy
                // double the space on the device, and only one would be
                // visible.
                const chain = Array.isArray(entries[e]) ? entries[e] : [entries[e]];
                let outcome = 'missing';

                for (let link = 0; link < chain.length && running(); link++) {
                    // A LINK IS AN ADDRESS — or an address with its own way to
                    // the bytes ({ url, get }): the vector map's terrain is
                    // stored under its tile-server address but read out of
                    // Mapterhorn's archive (vectorlayers.js).
                    const source = chain[link];
                    const url = typeof source === 'string' ? source : source.url;

                    // Already there? Then do not fetch again — when extending
                    // an area or going on after a break this saves almost
                    // everything.
                    const there = await store.match(url);
                    if (there) {
                        countPresent(there);
                        outcome = 'there';
                        break;
                    }
                    if (inFlight.has(url)) {
                        await inFlight.get(url);
                        const arrived = await store.match(url);
                        if (arrived) {
                            countPresent(arrived);
                            outcome = 'there';
                            break;
                        }
                        // The other one got nothing — then this one asks itself.
                    }

                    let settle;
                    inFlight.set(url, new Promise((resolve) => { settle = resolve; }));
                    try {
                        const got = typeof source === 'string' ? await fetchTile(url) : await fetchEntry(source);
                        if (!got.answer) {
                            // Maybe the next source has something.
                            if (got.unreachable) outcome = 'offline';
                            continue;
                        }

                        const length = Number(got.answer.headers.get('Content-Length')) || 0;
                        const copy = length === 0 && got.answer.type !== 'opaque' ? got.answer.clone() : null;
                        try {
                            await store.put(url, got.answer);
                        } catch (error) {
                            /* THE STORAGE SAID NO. Until now this was counted as
                               a tile the service did not have, and the bar ran on
                               to the end while nothing was stored any more. A
                               full storage stops the run and says so; any other
                               refusal is counted, and twenty in a row stop it too
                               — a storage that refuses everything is broken, not
                               unlucky. What is stored stays: „Weiterladen" goes
                               on from there once there is room. */
                            counts.unsaved++;
                            refusal = error?.name || 'Error';
                            if (refusal === 'QuotaExceededError') stopped = 'quota';
                            else if (++refusedInARow >= 20) stopped = 'storage';
                            outcome = 'refused';
                            break;
                        }
                        refusedInARow = 0;
                        remember(spot, e, link);
                        counts.stored++;
                        let size = length;
                        if (size === 0 && copy) { try { size = (await copy.blob()).size; } catch { /* unreadable */ } }
                        counts.bytes += size;
                        counts.newBytes += size;
                        outcome = 'there';
                        break;
                    } finally {
                        inFlight.delete(url);
                        settle();
                    }
                }

                // A missing tile is no reason to abort: at the edge of a
                // state service one delivers nothing and the other
                // everything. A dropped connection is another matter — that
                // tile is still to be had, and the run is not whole.
                if (outcome === 'missing') counts.missing++;
                else if (outcome === 'offline') counts.unreachable++;
            }

            // A spot counts as done only when it was gone through; one cut
            // short by a cancellation or a refusal is still to come.
            if (running()) report(++done, tiles.length, { ...counts });
        }
    }

    await Promise.all(Array.from({ length: atOnce }, workers));

    /* CANCEL MEANS CANCEL. Until now the half-fetched tiles stayed put:
       the row then said "on this device", and in the woods a patch of
       forest ended in a grey edge exactly where one had stopped. A half
       area that calls itself whole is worse than none, because you only
       find out where there is no reception (22.08.2026).
       Removed is only what THIS run added — see `added`. A storage that
       refused is no cancellation: what arrived stays for „Weiterladen". */
    let removed = 0;
    if (control.cancel) {
        for (let i = 0; i < addedCount; i++) {
            const slot = added[i * 2 + 1];
            const entry = addresses(tiles[added[i * 2]])[slot >> 8];
            const link = Array.isArray(entry) ? entry[slot & 0xff] : entry;
            const url = typeof link === 'string' ? link : link?.url;
            if (url && await store.delete(url)) removed++;
        }
        counts.stored -= removed;
    }

    return {
        ...counts,
        removed,
        done,
        total: tiles.length,
        cancelled: control.cancel,
        stopped,
        refusal,
        // What the area holds on this device after this run, counted
        // along — see above why not by keys().
        tiles: counts.present + counts.stored,
    };
}

/**
 * Fetch one tile — its answer, or why there is none.
 *
 * WITH CORS, because only then is the size measurable and because the
 * filtered layers — Veneto's black borders, basemap.at's white fill areas —
 * send their tiles through a canvas and need a readable response for that.
 * An opaque one would not do there.
 *
 * WITHOUT CORS as fallback, for not every official service grants the
 * clearance. An <img> draws an opaque tile without complaint; it just cannot
 * be weighed. Better a tile of unknown size in the woods than none.
 *
 * WHY THERE IS NONE decides whether a run is whole (27.09.2026): `missing`
 * when the service answered that it has nothing there, `unreachable` when
 * no answer came at all or the server was in trouble. A gap at the edge of
 * a service stays a gap; a tile lost to a dropped connection is still to
 * be had, and „Weiterladen" fetches exactly those.
 *
 * @returns {Promise<{answer?: Response, missing?: true, unreachable?: true}>}
 */
async function fetchTile(url) {
    /* 'same-origin', NOT 'omit': for a foreign service the two are the same
       — no cookie goes along —, but our own relay for the states without
       CORS (includes/tile_relay.php) answers only a visitor it knows. With
       'omit' every tile of Schleswig-Holstein and Sachsen-Anhalt came back
       401 and was counted as a spot without imagery (01.10.2026). */
    try {
        const answer = await fetch(url, { mode: 'cors', credentials: 'same-origin' });
        if (answer.ok) return { answer };
        // A clean 404 is an answer: the service does not cover this spot.
        // Then a second attempt has nothing to fetch either. A refusal of
        // who asks (401, 403 — the session ran out) is none: „Weiterladen"
        // must try again.
        if (answer.status > 0) {
            return answer.status >= 500 || [401, 403, 429].includes(answer.status)
                ? { unreachable: true } : { missing: true };
        }
    } catch {
        // No clearance or no network — the second attempt decides.
    }

    try {
        const answer = await fetch(url, { mode: 'no-cors', credentials: 'same-origin' });
        return answer.type === 'opaque' ? { answer } : { missing: true };
    } catch {
        return { unreachable: true };
    }
}

/** An entry with its own way to the bytes — its answer in fetchTile's form. */
async function fetchEntry(entry) {
    try {
        return await entry.get();
    } catch {
        return { unreachable: true };
    }
}

/** One tile's answer, or null — for the weighing of a layer (map.js). */
export async function getTile(url) {
    return (typeof url === 'string' ? await fetchTile(url) : await fetchEntry(url)).answer ?? null;
}
