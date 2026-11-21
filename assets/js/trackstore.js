/**
 * The recorded tracks on the device — the same buckets as the points'.
 *
 * WHAT FOR. The recorder app uploads walks to api/v1/tracks.php, and the
 * server bundles them by the month they began, a fingerprint per bucket
 * (includes/tracks.php, tracks_bucket_status). Here lies what this device
 * already has of that. At startup only the fingerprints are compared, and
 * only the buckets that moved are fetched — a bucket "2025-10" is loaded
 * once and never again, because a finished walk does not change.
 *
 * WHY NOT INSIDE pointstore.js, although it is the same mechanism twice:
 * the two stores have different shapes and different fates. A point
 * record is rewritten on schema changes and carries a version that
 * tests/pointstore.php guards field by field; a track is a line with a
 * few numbers beside it, and its shape is the API's, frozen in
 * tests/trackbuckets.php. Sharing one database would tie the two
 * version numbers together, and raising one for the points would empty
 * the tracks along with them — twenty megabytes fetched again for a
 * field the tracks never had.
 *
 * And the same rule as over there: what lies here is a COPY and may be
 * thrown away at any time — by the browser when space runs short. The
 * originals are on the server. (The Offline tab's „Vom Gerät löschen"
 * went on 23.09.2026: the map fetched everything back on the next
 * opening, and a smoothed walk weighs about 4 KB here.)
 *
 * VERSION 1 — the record is {key, finger, tracks, fetched}, a track inside
 * exactly as api/v1/bucket.php?tracks=1 delivers it: id, client_id, name,
 * device, started_at, ended_at, point_count, distance_m, box, created_at,
 * updated_at, positions (since version 3: drawing). Whoever changes that
 * shape on the server raises this number, and the store empties itself
 * on the next opening — a fingerprint hashes values, not names, so
 * nothing else would ever notice (pointstore.js tells that story four
 * times over).
 */

import { t } from './i18n.js';

const DB_NAME = 'map-tracks';
const STORE   = 'buckets';
/* VERSION 2: a track carries its outing — 'tour', 'tour_id' and
   'excursion_id' (migration 0099, 12.09.2026). Assigned to the stock by
   the migration WITHOUT touching updated_at, so no fingerprint moves; the
   same lesson pointstore.js learned three times over, applied before it
   could bite. tests/trackbuckets.php holds the key set to this number.

   VERSION 3: 'drawing' instead of 'positions' (migration 0100, the same
   day). The buckets no longer carry the original but a sieved and
   simplified copy for the map — the app records a fix every three
   seconds now, and the original stays on the server for the things a
   line cannot show. Built for the stock on first delivery, again
   without touching updated_at; a record of version 2 would draw nothing
   from a field it does not have, so the store empties itself.

   VERSION 4: the same keys, a different drawing (migration 0101, the day
   after). The drawing now keeps a crest on a straight stretch for the
   elevation profile in the popup, and the stock was rebuilt on the
   server — again without touching updated_at, so the fingerprints
   cannot tell a device its copy is the old one. Only the version can. */
const VERSION = 4;

let connection = null;

function open() {
    if (connection) return connection;

    connection = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, VERSION);

        request.onupgradeneeded = (event) => {
            const db = request.result;
            const store = db.objectStoreNames.contains(STORE)
                ? request.transaction.objectStore(STORE)
                : db.createObjectStore(STORE, { keyPath: 'key' });
            // The index is what keeps fingerprints() cheap — see there.
            if (!store.indexNames.contains('finger')) {
                store.createIndex('finger', 'finger', { unique: false });
            }
            // A shape change empties the store; the server has everything.
            if (event.oldVersion > 0 && event.oldVersion < VERSION) {
                store.clear();
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror   = () => reject(request.error);
        request.onblocked = () => reject(new Error(t('Der Speicher für Wegstrecken ist von einem anderen Tab belegt.')));
    }).catch((e) => {
        connection = null;                 // try afresh next time
        throw e;
    });

    return connection;
}

/** A transaction, as a promise — waits for completion, not for one request. */
async function withTransaction(mode, work) {
    const db = await open();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, mode);
        const store = tx.objectStore(STORE);

        let result;
        try {
            result = work(store);
        } catch (e) {
            tx.abort();
            reject(e);
            return;
        }

        tx.oncomplete = () => resolve(result?.result ?? result);
        tx.onerror    = () => reject(tx.error);
        tx.onabort    = () => reject(tx.error || new Error(t('Der Speicher für Wegstrecken wurde abgebrochen.')));
    });
}

/**
 * Which buckets lie here, and with which fingerprint?
 *
 * Over the index and its key cursor, so no record is unpacked: with
 * years of walks that would be megabytes read on every start only to
 * compare a few short strings — pointstore.js measured it.
 *
 * @return {Promise<Object<string,string>>}
 */
export async function fingerprints() {
    const db = await open();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const cursor = tx.objectStore(STORE).index('finger').openKeyCursor();
        const out = {};

        cursor.onsuccess = () => {
            const c = cursor.result;
            if (!c) return;
            out[c.primaryKey] = c.key;
            c.continue();
        };
        tx.oncomplete = () => resolve(out);
        tx.onerror    = () => reject(tx.error);
        tx.onabort    = () => reject(tx.error || new Error(t('Der Speicher für Wegstrecken wurde abgebrochen.')));
    });
}

/**
 * How many tracks really lie in each bucket — the belt to the braces,
 * held against the server's count (see bucketSizes() in pointstore.js
 * for the container that once showed 382 points and had none).
 *
 * @return {Promise<Object<string,number>>}
 */
export async function bucketSizes() {
    const db = await open();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const cursor = tx.objectStore(STORE).openCursor();
        const out = {};

        cursor.onsuccess = () => {
            const c = cursor.result;
            if (!c) return;
            out[String(c.primaryKey)] = Array.isArray(c.value?.tracks) ? c.value.tracks.length : 0;
            c.continue();
        };
        tx.oncomplete = () => resolve(out);
        tx.onerror    = () => reject(tx.error);
        tx.onabort    = () => reject(tx.error || new Error(t('Der Speicher für Wegstrecken wurde abgebrochen.')));
    });
}

/**
 * Store buckets — fingerprint and tracks, always together, all in ONE
 * transaction. Half a bucket under a valid fingerprint would count as
 * complete for ever; pointstore.js put() carries the full reasoning.
 *
 * @param {Object<string,{finger: string, tracks: Array}>} buckets  as from the server
 */
export function put(buckets) {
    return withTransaction('readwrite', (store) => {
        for (const [key, set] of Object.entries(buckets)) {
            store.put({
                key,
                finger:  set.finger,
                tracks:  set.tracks || [],
                fetched: Date.now(),
            });
        }
    });
}

/**
 * The tracks of certain buckets — or of all, if none are named.
 *
 * @param {string[]|null} keys
 */
export async function tracks(keys = null) {
    const sets = keys === null
        ? await withTransaction('readonly', (store) => store.getAll())
        : await withTransaction('readonly', (store) => {
            const collected = [];
            for (const k of keys) {
                const a = store.get(k);
                a.onsuccess = () => { if (a.result) collected.push(a.result); };
            }
            return { result: collected };
        });

    const out = [];
    for (const set of sets || []) {
        for (const w of set.tracks || []) out.push(w);
    }
    return out;
}

/** Throw a bucket away. */
export function throwAway(key) {
    return withTransaction('readwrite', (store) => store.delete(key));
}
