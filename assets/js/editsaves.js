/**
 * Edits of a picture that did not reach the cloud yet — kept, and tried
 * again until they do (14.11.2026).
 *
 * WHY. The settings of „Bearbeiten" and the patches of „Entfernen" go to
 * the picture's cloud with every save (photowindow.js, developSend), and a
 * cloud that stumbles refused some of them: „ein paar speichert es, ein
 * paar nicht". Until now a refused save was told once and then forgotten —
 * the picture in the window went on showing the mended place, nothing
 * tried again, and a reload took it all away. His four points, asked for in
 * one go:
 *   1. try again by itself, also while one works on another picture;
 *   2. a mark at a place not yet in the cloud, and a press on it tries now;
 *   3. a question before the page goes while something is not stored;
 *   4. keep it on the device, so a reload or a crash loses nothing and the
 *      next visit brings it up.
 * NOT ON THIS SERVER: it never holds a patch (includes/develop_patches.php,
 * his decision of 10.11.2026), and without the cloud one cannot open one's
 * pictures anyway — a longer outage waits on the device.
 *
 * WHAT IS KEPT is the NEWEST state of each edit — a save carries the whole
 * edit, never a difference, so the last one that failed stands for all
 * before it. One entry per edit: the photographer's own ('own', keyed by the
 * picture, 'cloud:34' or 'photo:12') or a friend's own edit beside it
 * ('foreign', keyed 'edit:7'). Every attempt carries a number (`gen`), and
 * an older one never replaces or clears a newer.
 *
 * WHERE: IndexedDB, „map-edits" — the entries and the patches they need as
 * PNG text (a few dozen kilobytes each), both under the user's id: two
 * people on one browser never send each other's edits.
 *
 * THE SENDING is not this module's: photowindow.js hands over a function
 * that tries one entry and says whether it went (editSavesStart).
 */

import { t } from './i18n.js';

const DB_NAME = 'map-edits';
const SAVES = 'saves';
const PATCHES = 'patches';

/* THE RHYTHM OF TRYING AGAIN: soon at first — a cloud that stumbled is
   usually back in seconds —, then ever rarer, at most every five minutes,
   for as long as the page is open. A visit later starts again from the top. */
const WAITS = [5, 15, 30, 60, 120, 300];

let userId = null;
let sender = null;
let onChange = () => {};
const entries = new Map();     // key -> { key, kind, gen, body, ids, tries }
let timer = 0;
let running = false;

let connection = null;
function open() {
    if (connection) return connection;
    connection = new Promise((resolve, reject) => {
        if (!globalThis.indexedDB) { reject(new Error('no IndexedDB')); return; }
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(SAVES)) db.createObjectStore(SAVES, { keyPath: 'k' });
            if (!db.objectStoreNames.contains(PATCHES)) db.createObjectStore(PATCHES, { keyPath: 'k' });
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
        request.onblocked = () => reject(new Error('blocked'));
    }).catch((e) => {
        connection = null;
        throw e;
    });
    return connection;
}

/** One transaction over both stores; resolves when it is on disk. */
async function tx(mode, work) {
    const db = await open();
    return new Promise((resolve, reject) => {
        const x = db.transaction([SAVES, PATCHES], mode);
        let result;
        try {
            result = work(x.objectStore(SAVES), x.objectStore(PATCHES));
        } catch (e) {
            x.abort();
            reject(e);
            return;
        }
        x.oncomplete = () => resolve(result?.result ?? result);
        x.onerror = () => reject(x.error);
        x.onabort = () => reject(x.error);
    });
}

const own = (key) => `${userId}|${key}`;

/* KEEPING IT ON THE DEVICE never stands in the way: a browser that has no
   IndexedDB (a private window) still tries again while the page is open —
   it only cannot keep it past a reload, and the question before leaving
   (editSavesWaiting) says so in its own way. */
function persist(entry, patches) {
    return tx('readwrite', (saves, store) => {
        saves.put({ k: own(entry.key), user: userId, key: entry.key, kind: entry.kind, gen: entry.gen, body: entry.body, ids: entry.ids });
        for (const [id, png] of Object.entries(patches)) store.put({ k: own(id), user: userId, id, png });
    }).then(() => { entry.persisted = true; }, (e) => console.warn('Bearbeitung nicht auf dem Gerät aufgehoben:', e));
}

/** The entry and the patches no other entry still needs leave the device. */
function forget(key, ids) {
    const still = new Set([...entries.values()].flatMap((e) => e.ids));
    return tx('readwrite', (saves, store) => {
        saves.delete(own(key));
        for (const id of ids) if (!still.has(id)) store.delete(own(id));
    }).catch(() => {});
}

/**
 * Start: what the last visit left on this device comes back, and the
 * trying starts. `send(entry)` resolves to true when the entry reached the
 * cloud; `restorePatch(id, png)` takes a kept patch back into the page;
 * `changed()` is told whenever what waits changes (the marks).
 * @returns {Promise<Array>} the entries brought back
 */
export async function editSavesStart({ user, send, restorePatch, changed }) {
    userId = user;
    sender = send;
    onChange = changed ?? (() => {});
    let back = [];
    try {
        // Two requests in one transaction; their results are read once it is complete.
        back = await tx('readonly', (saves, store) => ({ saves: saves.getAll(), patches: store.getAll() }));
    } catch {
        back = null;
    }
    const restored = [];
    if (back) {
        const savesAll = back.saves?.result ?? [];
        const patchesAll = back.patches?.result ?? [];
        const pngs = new Map(patchesAll.filter((p) => p.user === userId).map((p) => [p.id, p.png]));
        for (const s of savesAll) {
            if (s.user !== userId) continue;
            const entry = { key: s.key, kind: s.kind, gen: s.gen, body: s.body, ids: s.ids ?? [], tries: 0, persisted: true };
            for (const id of entry.ids) {
                const png = pngs.get(id);
                if (png) await restorePatch(id, png);
            }
            entries.set(entry.key, entry);
            restored.push(entry);
        }
    }
    globalThis.addEventListener?.('online', () => editSavesRetryNow());
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && entries.size) editSavesRetryNow(); });
    if (restored.length) {
        onChange();
        schedule(1);
    }
    return restored;
}

/**
 * A save did not reach the cloud: keep it — unless a newer one of the same
 * edit is kept already — and try again later. `patches`: { id: PNG text }
 * of every patch it needs that the cloud does not hold.
 */
export function editSaveFailed(entry, patches) {
    const was = entries.get(entry.key);
    if (was && was.gen > entry.gen) return;
    // The same entry refused again (a retry): it stays as it is, counting its tries — only what it needs is renewed.
    if (was && was.gen === entry.gen) {
        was.ids = entry.ids;
        persist(was, patches);
        return;
    }
    const kept = { ...entry, tries: was?.tries ?? 0 };
    entries.set(entry.key, kept);
    persist(kept, patches);
    onChange();
    if (!running) schedule(WAITS[Math.min(kept.tries, WAITS.length - 1)]);
}

/** A save reached the cloud: whatever of the same edit was kept and is not newer goes. */
export function editSaveDone(key, gen) {
    const was = entries.get(key);
    if (!was || was.gen > gen) return;
    entries.delete(key);
    forget(key, was.ids);
    onChange();
}

/** The kept entry of an edit, or null. */
export const editSaveWaiting = (key) => entries.get(key) ?? null;
/** Is anything waiting for the cloud? */
export const editSavesWaiting = () => entries.size > 0;
/** Every patch id a kept entry needs — the places still to reach the cloud. */
export const editSavesIds = () => new Set([...entries.values()].flatMap((e) => e.ids));

/** Is the entry still on the device — false when another tab took it off; null when the store cannot be asked. */
async function keptElsewhere(entry) {
    try {
        const k = await tx('readonly', (saves) => saves.get(own(entry.key)));
        return !!k && k.gen >= entry.gen;
    } catch {
        return null;
    }
}

function schedule(seconds) {
    clearTimeout(timer);
    timer = setTimeout(round, seconds * 1000);
}

/** One round: every entry once, one after the other; then the next round, a little later. */
async function round() {
    if (running || !sender) return;
    running = true;
    clearTimeout(timer);
    let left = 0;
    try {
        for (const entry of [...entries.values()]) {
            // Replaced or done meanwhile (a newer save of the same edit): not this one.
            if (entries.get(entry.key) !== entry) continue;
            /* ANOTHER TAB of the app may have brought it to the cloud already
               and taken it off the device — then this tab must not send its
               copy, which may be older than what that tab saved since. */
            if (entry.persisted && await keptElsewhere(entry) === false) {
                entries.delete(entry.key);
                onChange();
                continue;
            }
            let went = false;
            try {
                went = await sender(entry);
            } catch {
                went = false;
            }
            if (!went && entries.get(entry.key) === entry) entry.tries++;
        }
        left = entries.size;
    } finally {
        running = false;
    }
    if (left) {
        const tries = Math.min(...[...entries.values()].map((e) => e.tries));
        schedule(WAITS[Math.min(tries, WAITS.length - 1)]);
    }
}

/** Try everything now — the press on a mark, the network coming back. */
export function editSavesRetryNow() {
    if (!entries.size) return;
    if (running) return;
    schedule(0);
}

/** The words for the mark — how it stands and what a press does. */
export function editSavesHint() {
    return t('Noch nicht in der Cloud gespeichert. Es wird von selbst wieder versucht – klicken, um es sofort zu versuchen.');
}
