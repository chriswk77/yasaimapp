import { deviceTakeover } from './legacy.js';
import { t } from './i18n.js';

/**
 * Photos still waiting for network.
 *
 * WHY NOT INTO THE QUEUE. That one lives in localStorage: five megabytes,
 * synchronous, and it can only hold strings. An image would have to go in
 * as Base64 and would thereby be a third bigger — by the eighth photo the
 * storage would be full, and then it hits not just the photos but the
 * points along with them. A day in the field is more like fifty pictures.
 *
 * SO HERE THEY LIE AS BLOBS IN IndexedDB. No Base64, no re-encoding, and
 * the browser puts the data on disk itself instead of into RAM. Fifty
 * downscaled shots are around 25 MB; an origin usually gets a substantial
 * share of the free disk space on a phone. That is not the limit where
 * something fails.
 *
 * THE QUEUE REMAINS IN CHARGE OF THE ORDER of the points; only the images
 * lie here. The two meet at the point id: A photo belonging to a point not
 * yet transferred carries its provisional negative id and is only uploaded
 * once that has become a real one (see rewritePoint).
 */

const DB_NAME = 'map-photos';
const STORE     = 'pending';

let connection = null;

function open() {
    if (connection) return connection;

    // The takeover from 'karte-fotos' may still be copying blobs when the
    // page boots — opening early would find an empty store and believe it.
    connection = deviceTakeover.then(() => new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);

        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(STORE)) {
                const store = db.createObjectStore(STORE, { keyPath: 'id' });
                // For rewriting when a point receives its real id.
                store.createIndex('point', 'pointId', { unique: false });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror   = () => reject(request.error);
        // A second tab holds on to the old version. Does not occur with one
        // version number, but a hanging promise would be the more
        // unpleasant failure.
        request.onblocked = () => reject(new Error(t('Fotospeicher ist von einem anderen Tab belegt.')));
    })).catch((e) => {
        connection = null;                 // try afresh next time
        throw e;
    });

    return connection;
}

/** A transaction, as a promise. */
async function withTransaction(mode, work) {
    const db = await open();
    return new Promise((resolve, reject) => {
        // 'tx', not 't' — that name would shadow t() from i18n.js.
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

        // Wait for the transaction, not for the single request: Only its
        // completion means something really sits on the disk.
        tx.oncomplete = () => resolve(result?.result ?? result);
        tx.onerror    = () => reject(tx.error);
        tx.onabort    = () => reject(tx.error || new Error(t('Fotospeicher abgebrochen.')));
    });
}

/**
 * Ask for persistence.
 *
 * Without this pledge, the browser may, when space runs short, throw away
 * everything this origin has stored — and that would then be the photos of
 * a whole day. Chrome grants it to an installed application as a rule
 * without asking back. If it fails, that is no reason to abort: Then the
 * images simply lie there subject to revocation, which is still better
 * than not taking them in at all.
 *
 * IT APPLIES TO THE ORIGIN, NOT TO THIS STORE — and that is why map.js
 * calls it too, as soon as something sits in the queue for the first time.
 * That one lives in localStorage and falls under the same cleanup
 * permission; but there lies the only copy of the points not yet with the
 * server. That the function lives here is provenance, not responsibility.
 */
export async function requestPersistence() {
    try {
        if (!navigator.storage?.persist) return false;
        if (await navigator.storage.persisted?.()) return true;
        return await navigator.storage.persist();
    } catch {
        return false;
    }
}

/** How much space there is. For display, not for decisions. */
export async function space() {
    try {
        const { usage = 0, quota = 0 } = await navigator.storage?.estimate?.() ?? {};
        return { used: usage, total: quota };
    } catch {
        return { used: 0, total: 0 };
    }
}

/* Records stored before the move to English still carry 'bild', 'vorschau'
   and 'eingereiht'. A pending photo lives only until the next successful
   upload, so renaming on READ is enough — no database version bump, no
   rewrite pass. Deletable once every device has emptied its store once. */
function adopt(entry) {
    const out = { ...entry };
    if ('bild' in out)       { out.image    = out.bild;       delete out.bild; }
    if ('vorschau' in out)   { out.preview  = out.vorschau;   delete out.vorschau; }
    if ('eingereiht' in out) { out.enqueued = out.eingereiht; delete out.eingereiht; }
    return out;
}

/** Put a photo into storage. */
export function remember(entry) {
    return withTransaction('readwrite', (store) => store.put({ enqueued: Date.now(), ...entry }));
}

/** Everything waiting — in the order it came into being. */
export async function all() {
    const out = await withTransaction('readonly', (store) => store.getAll());
    return (out || []).map(adopt).sort((a, b) => (a.enqueued || 0) - (b.enqueued || 0));
}

/** What waits for a particular point. */
export async function forPoint(pointId) {
    const out = await withTransaction('readonly', (store) => store.index('point').getAll(pointId));
    return (out || []).map(adopt).sort((a, b) => (a.enqueued || 0) - (b.enqueued || 0));
}

export function remove(id) {
    return withTransaction('readwrite', (store) => store.delete(id));
}

export async function count() {
    try {
        return await withTransaction('readonly', (store) => store.count());
    } catch {
        return 0;
    }
}

/**
 * Replace a provisional point id with the real one.
 *
 * The counterpart to replaceKey() in the queue — the same thing,
 * different storage. Whoever sets a point in the forest and adds three
 * photos has three entries with a negative id standing here. Without this
 * step they would wait for a point that will never exist.
 */
export async function rewritePoint(oldId, newId) {
    const affected = await forPoint(oldId);
    if (affected.length === 0) return 0;

    await withTransaction('readwrite', (store) => {
        for (const entry of affected) {
            store.put({ ...entry, pointId: newId });
        }
    });
    return affected.length;
}

/**
 * Forget everything about a point.
 *
 * For the case that a point is deleted before its photos were up. Leaving
 * them standing would mean they get uploaded, at the next network, to a
 * point that no longer exists — or, worse, to a foreign one that has
 * received the number in the meantime.
 */
export async function forgetPoint(pointId) {
    const affected = await forPoint(pointId);
    if (affected.length === 0) return 0;

    await withTransaction('readwrite', (store) => {
        for (const entry of affected) store.delete(entry.id);
    });
    return affected.length;
}
