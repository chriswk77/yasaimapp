/**
 * Originals that wait in the phone's gallery — the Android app's half of
 * „Aufnehmen" (03.10.2026, variant A: „dass die Fotos in die
 * Galerie des Handys wandern, und bei entsprechendem Netz nachladen auf die
 * Cloud").
 *
 * IN THE APP every camera shot lands whole in the gallery (album
 * „YASAIMAPP"), whatever happens next — the original can no longer be lost.
 * The page decides when it goes into the cloud, by the same rule as the
 * browser's full-size road (map.js, fullSizeNetwork): on Wi-Fi always, over
 * mobile data only with the tick in Mein Bereich → Cloud.
 *
 * THE PAGE OPENS THE WAY, THE APP CARRIES THE BYTES. The page asks our
 * server for the target with ITS session (cloud.php action=target), hands it
 * to the app, and the app uploads from the gallery in the background — on
 * through a dark screen, which the page itself does not survive. A device
 * token never reaches the cloud (the rule of 23.09.2026). When the
 * app reports the picture up, the page records it, and the recording
 * REPLACES the shrunk copy that went up meanwhile (cloud.php, replaces_photo).
 *
 * THE NOTE PER SHOT lives here, keyed by the file name the app handed the
 * page — the app knows which gallery picture that name is. It must outlive
 * the shrunk copy's queue entry (photos.js deletes that on upload), so it
 * has a store of its own in localStorage, like the points' queue
 * (map.queue). A provisional point id is rewritten here as there
 * (swapPoint), and a note whose point has no server id yet waits.
 *
 * THE BRIDGE, agreed with the app session (03.10.2026):
 *   window.yasaimapp.sendPhoto(name, targetJson, optionsJson) → job | null
 *       optionsJson {network: 'any' | 'unmetered'}; null = picture not in
 *       the gallery any more
 *   window.yasaimapp.photosSent() → array of finished jobs (a parsed
 *       array; a JSON string is read too)
 *   window.yasaimapp.photoSeen(job, {recorded?: true}) — recorded: the find
 *       knows the cloud file, the app deletes its gallery original
 *   window.yasaimapp.isUnmetered?.() → whether the phone is on Wi-Fi now
 *   event 'yasaimapp:photo-sent' {job, name, ok, fileid, cloudName, size,
 *       width, height, error, gone}
 */

import { request } from './api.js';

const KEY = 'map.gallery-originals';

/** The app's bridge — or null in a plain browser. */
export function bridge() {
    const b = window.yasaimapp;
    return b && typeof b.sendPhoto === 'function' ? b : null;
}

function read() {
    try {
        const list = JSON.parse(localStorage.getItem(KEY) || '[]');
        return Array.isArray(list) ? list : [];
    } catch {
        return [];
    }
}

function write(list) {
    try {
        localStorage.setItem(KEY, JSON.stringify(list));
    } catch {
        // A full or blocked storage: the original stays in the gallery, and
        // „Auswählen" still reaches it. Nothing is lost by a missing note.
    }
}

/**
 * Note a shot whose original waits in the gallery.
 *
 * @param {{name: string, size: number, type: string, mtime: number,
 *          pointId: number, title: ?string, entryId: ?string}} shot
 *        entryId: the shrunk copy's queue entry, if one went into the queue
 */
export function note(shot) {
    const list = read().filter((n) => n.name !== shot.name);
    list.push({ ...shot, photoId: null, job: null, phase: 'waiting', since: Date.now() });
    write(list);
}

/** How many originals wait for this point — for the gallery's hint. */
export function waitingFor(pointId) {
    return read().filter((n) => n.pointId === pointId).length;
}

/** A point received its real id (map.js, swapPointKey). */
export function swapPoint(oldId, newId) {
    const list = read();
    let changed = false;
    for (const n of list) {
        if (n.pointId === oldId) { n.pointId = newId; changed = true; }
    }
    if (changed) write(list);
}

/** A point is gone — its notes go too; the originals stay in the gallery. */
export function forgetPoint(pointId) {
    write(read().filter((n) => n.pointId !== pointId));
}

/** The shrunk copy reached the server (photos.js 'transferred'). */
export function copyArrived(entryId, photoId) {
    const list = read();
    const n = list.find((x) => x.entryId === entryId);
    if (!n) return;
    n.photoId = photoId;
    write(list);
}

/**
 * Hand one original to the app now.
 *
 * @param {object} n  the note
 * @param {'any'|'unmetered'} network
 * @returns {Promise<boolean>} false when the app no longer has the picture
 */
async function hand(n, network) {
    const app = bridge();
    if (!app || !(n.pointId > 0)) return true;
    const body = new FormData();
    body.append('name', n.name);
    body.append('size', String(n.size ?? 0));
    body.append('type', n.type || 'image/jpeg');
    if (n.mtime) body.append('mtime', String(Math.round(n.mtime / 1000)));
    const target = await request(`cloud.php?id=${n.pointId}&action=target`, { method: 'POST', body });
    const job = app.sendPhoto(n.name, JSON.stringify(target), JSON.stringify({ network }));
    const list = read();
    const mine = list.find((x) => x.name === n.name);
    if (!mine) return true;
    if (!job) {
        // Not in the gallery any more: nothing to carry, the note goes.
        write(list.filter((x) => x.name !== n.name));
        return false;
    }
    Object.assign(mine, { job: String(job), phase: 'sent', folderid: target.folderid, path: target.path });
    write(list);
    return true;
}

/**
 * A shot taken with the right network at hand: handed over at once, and
 * then no shrunk copy is needed — the original is in the gallery.
 *
 * @returns {Promise<'sent'|'gone'|'failed'>} 'gone': the app has no such
 *          picture, the caller keeps a copy and no note; 'failed': the
 *          target could not be had right now (the network, the server) —
 *          the caller keeps a copy AND notes the original for later, so
 *          the find shows a picture at once either way
 */
export async function sendNow(shot, network) {
    note({ ...shot, entryId: null });
    try {
        return await hand(read().find((n) => n.name === shot.name), network) ? 'sent' : 'gone';
    } catch (e) {
        console.warn('Original not handed to the app now:', e);
        return 'failed';
    }
}

/** Hand every waiting original over that may go now. */
export async function sendWaiting(network) {
    if (!bridge() || !network || !navigator.onLine) return;
    for (const n of read()) {
        if (n.phase !== 'waiting' || !(n.pointId > 0)) continue;
        try {
            await hand(n, network);
        } catch (e) {
            console.warn('Original not handed to the app yet:', n.name, e);
            return;   // the network or the server — the rest fails alike
        }
    }
}

/**
 * The app has finished a job: record the picture, let it replace its copy,
 * give it its name, and tell the app it was seen.
 *
 * @param {object} d  the event's detail
 * @param {{dropCopy: (entryId: string) => Promise<void>, title: (pointId: number, file: object, title: string) => Promise<void>}} hooks
 * @returns {Promise<?{pointId: number, replaced: ?number}>} what changed, or null
 */
const settling = new Set();   // jobs being recorded right now — event and list may race

export async function settle(d, hooks) {
    if (settling.has(String(d.job))) return null;
    settling.add(String(d.job));
    try {
        return await settleOne(d, hooks);
    } finally {
        settling.delete(String(d.job));
    }
}

async function settleOne(d, hooks) {
    const app = bridge();
    const list = read();
    const n = list.find((x) => x.job === String(d.job)) ?? list.find((x) => x.name === d.name);
    /* `recorded` tells the app the find knows the cloud file now — and only
       then does the app delete its gallery original (03.10.2026:
       „wenn das Original in der Cloud ist, vom Handy löschen, damit das
       Handy nicht vollläuft"). Every other farewell — a failure, a picture
       gone, a job without a note — is the plain one: the app forgets the
       job and the picture stays. An older app ignores the second argument. */
    const seen = (options) => { try { app?.photoSeen?.(d.job, options); } catch { /* the app forgets later */ } };
    if (!n) { seen(); return null; }

    if (!d.ok) {
        if (d.gone) {
            write(list.filter((x) => x !== n));
        } else {
            // Try again in a later round — the target may have expired.
            Object.assign(n, { phase: 'waiting', job: null });
            write(list);
        }
        seen();
        return null;
    }

    const body = new FormData();
    body.append('fileid', String(d.fileid));
    body.append('folderid', String(n.folderid ?? ''));
    body.append('name', d.cloudName || n.name);
    body.append('path', n.path ?? '');
    body.append('size', String(d.size ?? ''));
    if (d.width && d.height) {
        body.append('width', String(d.width));
        body.append('height', String(d.height));
    }
    if (n.photoId) body.append('replaces_photo', String(n.photoId));
    const answer = await request(`cloud.php?id=${n.pointId}&action=record`, { method: 'POST', body });

    // The shrunk copy that never went up has nothing to do any more.
    if (!n.photoId && n.entryId) await hooks.dropCopy(n.entryId);

    const fresh = (answer.files || []).find((f) => String(f.fileid) === String(d.fileid));
    if (fresh && n.title && !fresh.title) {
        try { await hooks.title(n.pointId, fresh, n.title); } catch (e) { console.warn('Subject name not set:', e); }
    }

    write(read().filter((x) => x.name !== n.name));
    // Recorded at the server: the original may leave the phone.
    seen({ recorded: true });
    return { pointId: n.pointId, replaced: answer.replaced ?? null };
}

/** Whatever the app finished while no page was running — the same records. */
export async function settleFinished(hooks) {
    const app = bridge();
    if (!app?.photosSent) return [];
    /* AN ARRAY OR ITS TEXT — the app's shim hands a parsed array (like its
       recording() and positions()); the first version here parsed it a
       second time, and JSON.parse of an array threw for every content, so
       nothing finished while the page was away was ever recorded — the
       screen-off case of all cases (found by the app session, 03.10.2026).
       Both forms are taken now. */
    let done = [];
    try {
        const raw = app.photosSent();
        done = Array.isArray(raw) ? raw : JSON.parse(raw || '[]');
    } catch { done = []; }
    const changed = [];
    for (const d of Array.isArray(done) ? done : []) {
        try {
            const result = await settle(d, hooks);
            if (result) changed.push(result);
        } catch (e) {
            console.warn('Finished upload not recorded yet:', d?.name, e);
        }
    }
    return changed;
}
