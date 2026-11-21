/**
 * The time buckets on the device.
 *
 * WHAT FOR. The server bundles the points by months and gives each bucket
 * a fingerprint (see includes/bucket.php). Here lies what the device
 * already has of that. At startup only the fingerprints are compared —
 * measured 66 bytes for today's stock, 2.6 kB for ten years — and only
 * the outdated buckets are fetched anew. A bucket "2019-03" is loaded
 * once and never again.
 *
 * OWN DATABASE, NO SECOND STORE NEXT TO THE PHOTOS. The two have opposite
 * life stories: A photo in photostore.js is the ONLY copy until it is
 * uploaded — if it gets lost, it is gone. A bucket is a copy and can be
 * fetched again at any time.
 *
 * So what lies here may be thrown away, and what lies there on no
 * account. As long as these are two databases, no cleanup over here can
 * ever catch a photo — including one somebody writes in two years that
 * knows nothing of the photos.
 *
 * NO requestPersistence() OF ITS OWN. The browser's pledge applies to the
 * origin, not the database; photostore.js asks for it anyway. And if
 * the browser cleans up here when space runs short, that costs one
 * request, not data.
 */

import { t } from './i18n.js';

const DB_NAME = 'map-bucket';
const STORE   = 'buckets';

/* VERSION 2 SPEAKS ENGLISH (schema move, 21.08.2026). Version 1 kept the
   records as {schluessel, finger, points, geholt} and the points inside
   as the server sent them — with German field names. The fingerprints
   survive the rename unchanged (they hash values, not names), so an old
   bucket would count as current forever while its points speak a language
   the map no longer reads. The upgrade therefore rewrites every stored
   point on the spot; nothing is thrown away, the woods keep working.

   VERSION 3 CARRIES TWO STRAGGLERS (22.08.2026). Two payload fields kept
   their German names through the schema move because no column was behind
   them — 'fotos' and 'rote_liste' are assembled per request in data.php.
   They needed the same treatment for the same reason: a fingerprint hashes
   values, not names, so a bucket written before this stays "current"
   forever, and its points would show no photos and fall through every Red
   List filter. Silently — which is the sort of loss this file exists to
   prevent. */

/* VERSION 4 THROWS THE BUCKETS AWAY, and unlike its predecessors it has
   nothing to rewrite (26.08.2026). The points gained a field they never
   had: 'taxonomy' — kingdom, phylum, class, order and family, assembled
   per request in data.php like the Red List beside it. A stored bucket cannot grow it by
   renaming — the value is simply not in it, and no amount of rewriting
   invents it.

   And it would stay missing FOREVER, for the reason this file keeps
   repeating: a fingerprint hashes values, not names, so a bucket written
   before this counts as current for good. Every point in it would fall
   through the systematic filter without a visible cause.

   The price is one full fetch per device, once. That is the cheap half of
   the bargain — the expensive half would be a filter that quietly hides
   half of somebody's finds in the woods. */
/* VERSION 5 IS THE PRICE OF AN ASSERTION (26.08.2026). The genus joined
   the taxonomy bundle a few hours after version 4, and it kept version 4
   — on the reasoning that a shape which never left this machine may still
   change under its own number. The reasoning was sound; the premise was
   not. Version 4 had gone out twice already, and I had claimed otherwise
   without looking.

   What follows from that is exactly the failure this file was written
   against: a device already on version 4 sees no upgrade, keeps its
   stored points, and those points carry a taxonomy WITHOUT a genus. The
   filter then offers „nichts im Bestand" for a stock full of genera, and
   there is nothing in the picture that says why. The maintainer found it in
   the sidebar within the hour.

   THE LESSON IS NOT „BUMP MORE OFTEN". It is that a version number is a
   claim about what is out in the world, and that claim is cheap to check
   — the deployment history says it in one query. Whoever changes the
   shape of a stored record and reasons about who already has it: look
   first. */
/* VERSION 6, AND THE THIRD TIME THE SAME WAY (26.08.2026). The points
   gained 'excursion_id' — an own find of an excursion carries it, and the
   sidebar's excursion section reads it there. It went out one deployment
   AFTER version 5, so every device kept records without it: the excursion
   rows then counted zero for a stock full of finds, and the maintainer saw it
   within the hour.

   THE RULE I WROTE HERE LAST TIME DID NOT SAVE ME, because a rule one has
   to remember is not a guard. tests/pointstore.php is: it holds the KEY
   SET of the point payload against a frozen record, together with this
   number. Add a field and the test fails until both are raised — the same
   trick tests/services.php plays with the external addresses. */
/* 7: the photos riding on a point gained 'scale' (migration 0088) — a
   SUB-shape change the frozen key record cannot see, because it reads
   only the point's own keys. The effect is the same: a bucket stored
   before it counts as current for ever, and offline photos would never
   learn their calibration. */
/* 8: the same photos gained 'measures' (migration 0092, stage two of the
   measuring tool) — the same invisible sub-shape change as 7, for the
   same reason raised by hand. */
/* 9: the points gained 'dgfm' (17.09.2026) — the DGfM's marks on the
   title species, assembled per request like the Red List and the
   taxonomy beside it. The same story as version 4: a stored bucket cannot
   grow the field, its fingerprint counts it as current for ever, and the
   Speisewert filter would hide every find on a device that kept its old
   records — measured on this machine before the bump: one checkmark, an
   empty list. */
const VERSION = 9;

/* Old point field → new one. Only what the bucket points actually carry. */
const POINT_FIELDS = {
    titel: 'title', notiz: 'note', listen: 'lists', umriss: 'outline',
    gefunden_am: 'found_at', zusatz: 'extra', zusatz_arten: 'extra_species',
    feldzeiten: 'field_times', stand: 'touched_at',
    erstellt_am: 'created_at', geaendert_am: 'updated_at', geloescht_am: 'deleted_at',
    hoehe_m: 'elevation_m', hoehe_ellipsoid_m: 'elevation_ellipsoid_m',
    hoehe_genauigkeit_m: 'elevation_accuracy_m', hoehe_quelle: 'elevation_source',
    genauigkeit_m: 'accuracy_m', gemeinde: 'municipality',
    gemeinde_quelle: 'municipality_source', landesteil: 'region', staat: 'country',
    schutzgebiet: 'protected_area', besitzer: 'owner',
    // Version 3: assembled per request, so never touched by a migration.
    fotos: 'photos', rote_liste: 'red_list',
};

/* Field-time keys are field names themselves; 'zusatz.' carries the user's
   own field name behind it and only the prefix moves. */
const TIME_KEYS = {
    titel: 'title', notiz: 'note', ort: 'position', listen: 'lists',
    umriss: 'outline', gefunden_am: 'found_at',
};

function renameTimeKey(k) {
    if (k.startsWith('zusatz.')) return 'extra.' + k.slice(7);
    return TIME_KEYS[k] ?? k;
}

function renamePoint(p) {
    if (!p || typeof p !== 'object') return p;
    const out = {};
    for (const [k, v] of Object.entries(p)) {
        out[POINT_FIELDS[k] ?? k] = v;
    }
    if (out.field_times && typeof out.field_times === 'object') {
        const times = {};
        for (const [k, v] of Object.entries(out.field_times)) times[renameTimeKey(k)] = v;
        out.field_times = times;
    }
    if (out.original && typeof out.original === 'object') {
        const original = {};
        for (const [k, v] of Object.entries(out.original)) {
            const anchor = v && typeof v === 'object'
                ? { value: v.wert ?? v.value, by: v.von ?? v.by, at: v.am ?? v.at }
                : v;
            original[renameTimeKey(k)] = anchor;
        }
        out.original = original;
    }
    return out;
}

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
            // THIS INDEX IS THE REASON THIS STAYS FAST HERE —
            // see fingerprints() further down.
            if (!store.indexNames.contains('finger')) {
                store.createIndex('finger', 'finger', { unique: false });
            }

            /* Version 2 → 3: the store keeps its shape, only the points
               inside gain two renamed fields. Rewriting in place beats
               emptying the store — whoever stands in the woods keeps every
               point they already have. renamePoint() is idempotent: a
               field already English simply passes through. */
            if (event.oldVersion === 2) {
                store.openCursor().onsuccess = (e) => {
                    const c = e.target.result;
                    if (!c) return;
                    const r = c.value;
                    c.update({ ...r, points: (r.points || []).map(renamePoint) });
                    c.continue();
                };
            }

            /* From 3 or 4 onwards: nothing to rewrite, so the store is
               emptied. What the points lack cannot be derived from what
               they have; it has to come from the server. The bound is the
               CURRENT version, not a number written out — the next shape
               change then only has to raise VERSION. */
            if (event.oldVersion > 0 && event.oldVersion < VERSION
                && db.objectStoreNames.contains(STORE)) {
                store.clear();
            }

            // Carry version 1 over: same transaction, record by record.
            if (event.oldVersion === 1 && db.objectStoreNames.contains('eimer')) {
                const old = request.transaction.objectStore('eimer');
                old.openCursor().onsuccess = (e) => {
                    const c = e.target.result;
                    if (!c) { db.deleteObjectStore('eimer'); return; }
                    const r = c.value;
                    store.put({
                        key:     r.schluessel,
                        finger:  r.finger,
                        points:  (r.points || []).map(renamePoint),
                        fetched: r.geholt,
                    });
                    c.continue();
                };
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror   = () => reject(request.error);
        request.onblocked = () => reject(new Error(t('Punktspeicher ist von einem anderen Tab belegt.')));
    }).catch((e) => {
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
        tx.onabort    = () => reject(tx.error || new Error(t('Punktspeicher abgebrochen.')));
    });
}

/**
 * Which buckets lie here, and with which fingerprint?
 *
 * VIA THE INDEX, NOT VIA getAll(). That is no subtlety but the difference
 * between usable and unusable: getAll() reads every record COMPLETELY,
 * that is, with all its points. At ten years that would be around 10 MB
 * wandering into RAM on every start — only to compare a hundred and
 * twenty short strings. Exactly what the buckets are meant to avoid, just
 * on the disk instead of on the line.
 *
 * openKeyCursor() on the index does not touch the records: It delivers
 * the index key (the fingerprint) and the primary key (the bucket name),
 * and more is not needed here.
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
        tx.onabort    = () => reject(tx.error || new Error(t('Punktspeicher abgebrochen.')));
    });
}

/**
 * How many points really lie in each bucket.
 *
 * THE COUNTERPART TO THE SERVER'S COUNT (bucket_status()). Up to here the
 * comparison looked at fingerprints only, and a bucket whose print
 * matched counted as current — no matter what actually lay in it. An
 * empty or half-lost bucket was thereby a permanent state: it looked
 * right until the end of time. Exactly that held a shared container that
 * showed 382 points and had not a single one.
 *
 * WHY NOT RECOMPUTE THE PRINT ITSELF, which suggests itself: it reads
 * MD5(COUNT | MAX(touched_at) | SUM(CRC32(id:touched_at))). Rebuilding
 * that in the browser would mean hitting MySQL's CRC32, its date format
 * and the MD5 truncation exactly — the same rule a second time, in a
 * second language. If the two versions drift apart, EVERY bucket counts
 * as outdated and the device reloads everything on every start. A number,
 * on the other hand, is a number (20.08.2026).
 *
 * The cursor walks the records and not the index — the length lives only
 * in the record. That costs more than fingerprints(), but is needed only
 * for the sync and not for drawing.
 *
 * @return {Promise<Object<string,number>>}  bucket name → count
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
            out[String(c.primaryKey)] = Array.isArray(c.value?.points) ? c.value.points.length : 0;
            c.continue();
        };
        tx.oncomplete = () => resolve(out);
        tx.onerror    = () => reject(tx.error);
        tx.onabort    = () => reject(tx.error || new Error(t('Punktspeicher abgebrochen.')));
    });
}

/**
 * Store buckets — fingerprint and points, always together.
 *
 * IN ONE SINGLE TRANSACTION, and for ALL buckets handed over. If the
 * device broke off midway and a fingerprint lay there without its points,
 * the comparison would consider the bucket current at the next start —
 * and the points would be gone, permanently, without anything looking
 * like it. The same danger as with slicing on the server side, just in a
 * different place.
 *
 * NOTHING IS ASSIGNED HERE. The server delivers the points already
 * bundled (see bucket_points() in includes/bucket.php) — precisely so the
 * rule "which point belongs in which bucket" does not stand there a third
 * time, this time in JavaScript. What arrives here is stored, not
 * computed.
 *
 * @param {Object<string,{finger: string, points: Array}>} buckets  as from the server
 */
export function put(buckets) {
    return withTransaction('readwrite', (store) => {
        for (const [key, set] of Object.entries(buckets)) {
            store.put({
                key,
                finger: set.finger,
                points: set.points || [],
                fetched: Date.now(),
            });
        }
    });
}

/**
 * The points of certain buckets — or of all, if none are named.
 *
 * TWO ROUTES, AND FOR A MEASURED REASON. The first attempt always fetched
 * getAll() and sifted in JavaScript afterwards. With 120 buckets holding
 * 20,400 points, a SINGLE bucket thus cost 21 ms — almost as much as all
 * 120 together (26 ms), because the browser unpacked 6.7 MB each time to
 * keep one hundred-and-twentieth of it. Fetched selectively it is under
 * 1 ms.
 *
 * getAll() nevertheless stays for "everything", for there it is the
 * faster route: one request instead of a hundred and twenty.
 *
 * @param {string[]|null} keys
 */
export async function points(keys = null) {
    const sets = keys === null
        ? await withTransaction('readonly', (store) => store.getAll())
        : await withTransaction('readonly', (store) => {
            const collected = [];
            for (const k of keys) {
                const a = store.get(k);
                a.onsuccess = () => { if (a.result) collected.push(a.result); };
            }
            // Not the result of the last request but the collection —
            // withTransaction() waits for the transaction, and then all are there.
            return { result: collected };
        });

    const out = [];
    for (const set of sets || []) {
        for (const p of set.points || []) out.push(p);
    }
    return out;
}

/** Throw a bucket away. */
export function throwAway(key) {
    return withTransaction('readwrite', (store) => store.delete(key));
}

/** What lies here — for the display in the administration. */
export async function perimeter() {
    const sets = await withTransaction('readonly', (store) => store.getAll());
    let pointCount = 0;
    let oldest = null;

    for (const set of sets || []) {
        pointCount += (set.points || []).length;
        if (oldest === null || set.fetched < oldest) oldest = set.fetched;
    }

    return { buckets: (sets || []).length, points: pointCount, oldest };
}
