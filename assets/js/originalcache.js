/**
 * THE FULL PICTURES FROM THE CLOUD, KEPT AT THE DESK (18.11.2026:
 * „in der Desktop-Version … einen Cache anlegen für die vollen Bilder, die
 * man aus der Cloud geladen hat. Und zwar ein Cache, der ältere automatisch
 * verwirft. Man arbeitet ja meistens an einem Fund … dann irgendwann ist die
 * Bearbeitung abgehakt und man ist am nächsten Fund … und dies jedes Mal
 * übers Netz laden, bei jedem Öffnen. Ist halt schon viel Traffic").
 *
 * A recording's original comes through our server from the photographer's
 * cloud (pages/cloud_thumb.php?original=1) — megabytes each time, and the
 * browser's own HTTP cache lets go of files that size soon. So the window
 * keeps them here, in the Cache Storage of the origin, where the protected
 * areas and the inpainting models live too.
 *
 * WHAT A PICTURE IS: its kind, its id and its size in bytes — a recording
 * (map_cloud_files, size) or, since the same evening, a voucher photo
 * (map_photos, bytes: „Ja, die Belegfotos auch mit reinnehmen" — many lie
 * in the cloud by now, photo.php fetches them from there). The bytes change
 * only when a file is replaced or a stamp is written into it
 * (picture_stamps.php), and either changes the size; a new size is a new
 * entry, and the old one of the same picture goes at once.
 *
 * WHICH GO: the ones used longest ago, once the whole passes the budget —
 * two gigabytes unless the device was told otherwise, and never more than
 * half of what the browser grants the origin. A find one works on stays;
 * the finds of last month make room.
 *
 * THE DEVICE'S OWN CHOICE (the same day: „bei den Offline-Daten
 * … die Größe einstellen … reduzieren von Hand, beziehungsweise ihn ganz
 * ausschalten für Leute, die gar nicht mehr viel Platz auf dem Rechner
 * haben. Also 3 Sachen leeren, Größe ändern und gar nicht benutzen", then
 * „wenn man die Größe auf 0 setzt, wird er nicht mehr benutzt, aber ich
 * würde trotzdem meinen eigenen Haken machen"): Verwaltung → Offline →
 * Offline-Daten (administration.js) keeps, per device in the settings
 * bundle, a tick — keep at all — and the size. A smaller size makes room at
 * once; the tick taken off empties the store. What lies on a device is its
 * user's decision (CLAUDE.md, Offline).
 * Used means opened in the window, exported, healed — every way through
 * originalBlob.
 *
 * THE DESK ONLY: a phone's storage is small and its window shows the
 * preview anyway (photowindow.js). There, and wherever the Cache Storage is
 * missing (no secure context), everything goes over the network as before.
 *
 * The index (size and last use per entry) lives in localStorage, read and
 * written in try/catch as every browser storage here; a lost index only
 * makes the cache forget its order — an entry the index does not know is
 * found anyway, and one the cache lost is dropped from the index on the
 * next miss.
 */

import { settings } from './settings.js';

const STORE = 'yasaimapp-originals-v1';
const INDEX_KEY = 'yasaimapp.originals.v1';
// Decimal, as the sizes are said on the page — 500 MB, not 512 (administration.js, bytesSaid).
const GB = 1e9;
/** The sizes a device may choose, in bytes. */
export const ORIGINAL_BUDGETS = [GB / 2, GB, 2 * GB, 5 * GB];
const BUDGET_DEFAULT = 2 * GB;
const desk = window.matchMedia('(min-width: 861px)');

/** The budget this device chose, in bytes. */
export function originalBudget() {
    const v = Number(settings.get('originalsBudget', BUDGET_DEFAULT));
    return ORIGINAL_BUDGETS.includes(v) ? v : BUDGET_DEFAULT;
}

/** Whether this device's user lets it keep originals (the tick in Offline-Daten). */
export const originalKeeping = () => settings.get('originalsKeep', true) !== false;

/** Whether this device CAN keep originals: the desk, and a store to keep them in. */
export const originalCacheUsable = () => desk.matches && typeof caches !== 'undefined' && window.isSecureContext;
const usable = () => originalCacheUsable() && originalKeeping();

/** Whether a picture opened now would be kept or taken from the store. */
export const originalKeepsHere = () => usable();

/** The cache's address for a picture of a kind ('cloud', 'photo') — or null where its size is unknown, which would make a stale picture look current. */
function keyOf(f, kind) {
    const size = Number(kind === 'photo' ? f?.bytes : f?.size);
    if (!f?.id || !(size > 0)) return null;
    return `${location.origin}/__originals/${kind}/${f.id}/${size}`;
}

function indexRead() {
    try {
        const raw = JSON.parse(localStorage.getItem(INDEX_KEY) ?? '{}');
        return raw && typeof raw === 'object' ? raw : {};
    } catch {
        return {};
    }
}

function indexWrite(index) {
    try {
        localStorage.setItem(INDEX_KEY, JSON.stringify(index));
    } catch { /* full or blocked: the order is forgotten, nothing else */ }
}

function touch(key, size) {
    const index = indexRead();
    index[key] = { size, used: Date.now() };
    indexWrite(index);
}

async function budget() {
    const chosen = originalBudget();
    try {
        const { quota } = await navigator.storage.estimate();
        return quota > 0 ? Math.min(chosen, quota * .5) : chosen;
    } catch {
        return chosen;
    }
}

/** Take the least used out until the whole, with `extra` bytes still to come, fits `room`. */
async function evict(store, index, room, extra = 0, spare = null) {
    let total = extra + Object.entries(index).reduce((sum, [k, v]) => sum + (k === spare ? 0 : v.size || 0), 0);
    const oldest = Object.entries(index).filter(([k]) => k !== spare).sort((a, b) => (a[1].used || 0) - (b[1].used || 0));
    while (total > room && oldest.length > 0) {
        const [k, v] = oldest.shift();
        await store.delete(k);
        delete index[k];
        total -= v.size || 0;
    }
}

/** What lies here: bytes and pictures, by the index. */
export function originalCacheState() {
    const entries = Object.values(indexRead());
    return { bytes: entries.reduce((sum, v) => sum + (v.size || 0), 0), count: entries.length, budget: originalBudget() };
}

/** Empty the store on this device. */
export async function originalCacheClear() {
    try {
        await caches.delete(STORE);
    } catch { /* nothing to take */ }
    indexWrite({});
}

/** Keep originals on this device, or not — not empties the store. */
export async function originalKeepingSet(on) {
    settings.set('originalsKeep', !!on);
    if (!on) await originalCacheClear();
}

/** Choose this device's size; a smaller one makes room at once. */
export async function originalBudgetSet(bytes) {
    if (!ORIGINAL_BUDGETS.includes(bytes)) return;
    settings.set('originalsBudget', bytes);
    try {
        const store = await caches.open(STORE);
        const index = indexRead();
        await evict(store, index, await budget());
        indexWrite(index);
    } catch { /* the store will make room on its next keeping */ }
}

/**
 * Lay a fetched original down, the older one of the same recording out, and
 * the least used out until the whole fits. A picture kept by its ADDRESS
 * has no older self to replace (`replaces` false): its address names one
 * content for ever, and the part before the last slash is no recording but
 * a path of whatever page asked.
 */
async function keep(key, blob, type, replaces = true) {
    const store = await caches.open(STORE);
    const index = indexRead();
    const prefix = key.slice(0, key.lastIndexOf('/') + 1);
    for (const other of replaces ? Object.keys(index) : []) {
        if (other !== key && other.startsWith(prefix)) {
            await store.delete(other);
            delete index[other];
        }
    }
    const room = await budget();
    if (blob.size > room) {
        indexWrite(index);
        return;
    }
    await evict(store, index, room, blob.size, key);
    try {
        await store.put(key, new Response(blob, { headers: { 'Content-Type': type || blob.type || 'application/octet-stream' } }));
    } catch {
        indexWrite(index);
        return;   // the browser said no — the picture was shown all the same
    }
    index[key] = { size: blob.size, used: Date.now() };
    indexWrite(index);
}

/** The kept original of a picture, or null — no network. */
export async function originalPeek(f, kind = 'cloud') {
    const key = keyOf(f, kind);
    if (!key || !usable()) return null;
    try {
        const hit = await (await caches.open(STORE)).match(key);
        if (!hit) return null;
        const blob = await hit.blob();
        touch(key, blob.size);
        return blob;
    } catch {
        return null;
    }
}

/**
 * A picture's original as a Blob: from the cache where it lies there,
 * else from `url` — and then kept for the next time. Throws where the
 * network does not answer.
 */
/* ONE DOWNLOAD PER PICTURE AT A TIME: the window and its statistics' tiles
   ask for the same voucher photo in the same moment — measured, twice the
   megabytes on the first opening. A second asking waits for the first. */
const pending = new Map();   // url -> Promise<Blob>

export function originalBlob(f, url, kind = 'cloud') {
    if (!pending.has(url)) {
        pending.set(url, originalFetch(f, url, kind).finally(() => pending.delete(url)));
    }
    return pending.get(url);
}

async function originalFetch(f, url, kind) {
    const kept = await originalPeek(f, kind);
    if (kept) return kept;
    const answer = await fetch(url, { credentials: 'same-origin' });
    if (!answer.ok) throw new Error(String(answer.status));
    const blob = await answer.blob();
    const key = keyOf(f, kind);
    if (key && usable()) {
        // In the background: the picture is wanted now, the bookkeeping can follow.
        keep(key, blob, answer.headers.get('Content-Type')).catch(() => {});
    }
    return blob;
}

/*
 * PICTURES KNOWN BY THEIR ADDRESS (26.11.2026: „Können wir diesen Speicher
 * auch nutzen für Schlüsselbilder? … Und dass wir die auch erst mal
 * cachen"). A page whose pictures sit in plain <img> tags — the
 * identification keys' figures, a dozen templates of them — cannot hand
 * each one through originalBlob: the <img> asks before any script could.
 * So such a page lets the picture load as it always did and, once it has
 * come, lays it down HERE under its own address; the service worker
 * answers the next asking for that address out of this store before the
 * network (sw.js, getKeptPicture). The budget, the tick and the order of
 * use are the same as for every original — one store, one size, one
 * decision in Offline-Daten.
 *
 * ONLY FOR AN ADDRESS THAT NEVER CHANGES ITS BYTES: nothing here asks
 * again, and the worker answers from the store for as long as the picture
 * lies there. A picture that can change must carry a version in its
 * address (the keys' figures do, since 17.10.2026).
 */

/** Whether a picture lies here under this address — and if so, it was used now. No network, synchronous: the index alone. */
export function originalTouchAddress(url) {
    if (!usable()) return false;
    const index = indexRead();
    if (!index[url]) return false;
    touch(url, index[url].size);
    return true;
}

/**
 * Lay the picture at `url` down for the next time. It has just been shown,
 * so the browser's own cache answers this asking (force-cache) and the
 * network is not asked twice. Quietly nothing where the store is off or
 * the picture does not come.
 */
export function originalKeepAddress(url) {
    if (!usable() || pending.has(url)) return pending.get(url) ?? Promise.resolve();
    const job = (async () => {
        const answer = await fetch(url, { credentials: 'same-origin', cache: 'force-cache' });
        if (!answer.ok) return;
        const blob = await answer.blob();
        if (!blob.type.startsWith('image/')) return;
        await keep(url, blob, answer.headers.get('Content-Type'), false);
    })().catch(() => {}).finally(() => pending.delete(url));
    pending.set(url, job);
    return job;
}
