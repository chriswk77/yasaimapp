/**
 * Changes waiting for network.
 *
 * In the forest there is no reception, and precisely there points are set.
 * Instead of reporting "Speichern fehlgeschlagen" and throwing the input
 * away, every writing request that does not get through wanders here — and
 * is submitted later, as soon as there is a connection again.
 *
 * WHAT MUST NOT HAPPEN HERE
 *
 *   Losing. An entry stays until the server has confirmed it. Not "until
 *   it was tried once" — until it is confirmed.
 *
 *   Doubling. If a request arrives and only the response gets lost, the
 *   device does not know whether it worked. That is why every create
 *   carries an id assigned by the device; on a repeat the server returns
 *   the same point instead of creating a second one. Only that makes
 *   retrying safe.
 *
 *   Reordering. The order stays as it was entered: Whoever creates a
 *   point and renames it afterwards must have both submitted in that
 *   order.
 *
 * PROVISIONAL IDS. A point created offline has no id from the server yet.
 * It gets a negative one — which can never collide with a real one — and
 * with it may stand on the map immediately, be edited and even deleted
 * again. On submission it is replaced by the real one, in all later
 * entries of the queue too.
 *
 * WHERE IT LIVES. In localStorage, as desired: These are few small
 * entries, they must survive a crash, and they must be readable
 * synchronously before the map draws.
 */

import { t } from './i18n.js';

const STORAGE_KEY = 'map.queue';
const COUNTER_KEY    = 'map.queue.counter';
const DISCARDED_KEY  = 'map.queue.rejected';

let line = load();
let running   = false;

const listeners = new Set();

function load() {
    try {
        const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        if (!Array.isArray(raw)) return [];
        /* NOTHING IS IN FLIGHT AFTER A RELOAD. 'sending' means: a caller
           is holding this entry right now and is trying to hand it in
           itself. A reload ends every such attempt — whoever was holding
           it no longer exists. Left standing, the mark would make the
           entry unsendable for ever, because drain() steps aside for it. */
        for (const e of raw) delete e.sending;
        return raw;
    } catch {
        return [];
    }
}

/* THE STORE WAS FULL, AND NOBODY GOT TO KNOW.
 *
 * "hence loud" stood here — and loud meant a console.error. Nobody
 * standing in the woods sees that. What happens then, though, is the
 * worst case this file knows: the change now lives only in RAM. As long
 * as the page stays open it goes up later and all is well. If the page is
 * reloaded — and a phone reloads a background page by itself eventually —
 * it is gone. No message, no entry in the discarded list, no trace at
 * all.
 *
 * Exactly that explains a picture that otherwise makes no sense: the
 * points of a tour are there, but many are missing their titles. The
 * point entered the queue while there was still room; the title came
 * later and found none.
 *
 * The marker is read (full()) and shown in the bar above the map. */
let fullSince = null;

/** Did the device store overflow while saving? Then loss is looming. */
export function full() {
    return fullSince;
}

function persist() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(line));
        fullSince = null;
    } catch (e) {
        fullSince = fullSince ?? Date.now();
        console.error('Queue cannot be saved:', e);
    }
    for (const f of listeners) f(line.length);
}

/** Called on every change of the length — for the display. */
export function onChange(f) {
    listeners.add(f);
    f(line.length);
}

export function openCount() {
    return line.length;
}

/**
 * Next provisional id.
 *
 * Negative and consecutive across sessions, so two points created offline
 * do not get the same one if a reload happened in between.
 */
/* THE COUNTER ALSO RUNS IN MEMORY, and that is not belt and braces.
 *
 * It used to be read from the storage, decremented and written back — and
 * the write was allowed to fail quietly ("no harm done"). It does harm.
 * When the storage is full or unwritable, the next call reads the SAME
 * number again and hands out the same provisional id a second time. Two
 * points then share one number: they overwrite each other in the stock,
 * one of them takes the other's queue entry, and the one that comes second
 * simply does not exist.
 *
 * A full storage is exactly the situation in which this happens, and it is
 * exactly the situation of a long tour. So whatever the storage does, the
 * number counts on here. */
let provisionalCounter = null;

export function provisionalKey() {
    if (provisionalCounter === null) {
        try {
            provisionalCounter = parseInt(localStorage.getItem(COUNTER_KEY) || '0', 10) || 0;
        } catch {
            provisionalCounter = 0;
        }
    }
    provisionalCounter -= 1;
    // Written out so a reload does not begin again at the same number. If
    // that fails, the count in memory carries this session on its own.
    try { localStorage.setItem(COUNTER_KEY, String(provisionalCounter)); } catch { /* memory has it */ }
    return provisionalCounter;
}

/**
 * WRITE FIRST, SEND AFTERWARDS — the promise this file makes.
 *
 * Until 21.08.2026 it was the other way round: a call was tried, and only
 * its FAILURE brought it here. That works as long as failure means a
 * refusal. In the woods it does not. There a request goes out, a few bytes
 * leave, and then the line dies without a word — no answer, no error,
 * nothing to catch. Nothing was enqueued because nothing had failed yet,
 * and the input lived solely in RAM. Whoever reloaded, closed the window
 * or ran out of patience had written for nothing (21.08.2026:
 * "mal kurz Netz, ganz schlecht, dann wieder weg — und das ständig").
 *
 * Now every writing call reserves its place here BEFORE it touches the
 * line. From that moment the input survives a crash, a reload and a phone
 * that switches itself off. What happens on the line afterwards only
 * decides whether the entry may go again:
 *
 *   settle(ticket)   it arrived, or it was refused for good — it goes
 *   release(ticket)  nobody answered — it stays and waits for the queue
 *
 * WHILE A CALLER HOLDS AN ENTRY it carries 'sending', and drain() steps
 * aside for it: not by skipping it — that would reorder the queue — but by
 * stopping in front of it. Handing the same thing in twice is what the
 * client_id protects against; sending it in the wrong order is what
 * nothing protects against.
 */
let ticketCounter = Math.max(0, ...line.map(e => Number(e.ticket) || 0));

/** Reserve a place and hold the entry — the caller sends it itself. */
export function reserve(entry) {
    const ticket = ++ticketCounter;
    line.push({ enqueued: Date.now(), ...entry, ticket, sending: true });
    persist();
    return ticket;
}

/** Done with — it arrived, or it will never arrive. */
export function settle(ticket) {
    const i = line.findIndex(e => e.ticket === ticket);
    if (i === -1) return;
    line.splice(i, 1);
    persist();
}

/** Nobody answered — hand it back to the queue. */
export function release(ticket) {
    const entry = line.find(e => e.ticket === ticket);
    if (!entry) return;
    delete entry.sending;
    persist();
}

/** Enqueue a request. */
export function enqueue(entry) {
    // With a timestamp: Only that way can it be said later how long
    // something has been waiting — and it survives a reload, unlike a
    // counter in RAM. Whoever was in the forest and only has network again
    // in the evening should not be counted from scratch on every opening
    // of the map.
    line.push({ enqueued: Date.now(), ...entry, ticket: ++ticketCounter });
    persist();
}

/** When the oldest still-waiting entry came into being, or null. */
export function oldest() {
    let oldest = null;
    for (const e of line) {
        // e.enqueued on BOTH sides. 'e.eingereiht' stood in the second
        // one — the English rename translated the reading and forgot the
        // writing, and the field does not exist under that name. It
        // yielded undefined, every later comparison against undefined is
        // false, and so the function returned undefined for ever.
        //
        // What was lost by it is exactly the nudge this is for: the bar
        // says "3 Änderungen warten auf Übertragung" and should add "seit
        // gestern". Whoever comes back from a tour and forgets to hand in
        // needs the age, not the count.
        if (typeof e.enqueued !== 'number') continue;
        if (oldest === null || e.enqueued < oldest) oldest = e.enqueued;
    }
    return oldest;
}

/**
 * Ids for which something is really still pending.
 *
 * The application keeps waiting points alive across a reload — otherwise
 * the server's response would wipe away what is not with it yet. But that
 * presupposes that something is indeed still waiting.
 *
 * If the server rejects an entry, it flies out here (see drain). The
 * point in the application would then keep its 'pending' and its
 * provisional id forever — and would be brought out again on every
 * reload. A ghost that exists only on this device and that nothing can
 * remove.
 */
export function openKeys() {
    const ids = new Set();
    for (const entry of line) {
        if (entry.provisional != null) ids.add(entry.provisional);
        if (entry.id != null) ids.add(entry.id);
    }
    return ids;
}

/**
 * Lay what still waits here over the server's state.
 *
 * WHAT FOR. A point from the dead zone lies ONLY here. Not in the buckets
 * — there stands exclusively what the server delivered —, and not in the
 * document. But it is exactly from those that the application builds its
 * stock on load.
 *
 * Without this function, the morning's tour was invisible after a restart
 * in the forest. Not lost: The queue had it, the bar counted it, and at
 * the next network everything arrived. But it did not stand on the map.
 * Whoever thereupon considers it lost creates it a second time — and THEN
 * it is doubled. The client_id only protects against the same row of the
 * queue, not against a second input by hand.
 *
 * IN THE ORDER OF THE QUEUE, and that is why the case of a point first
 * created and then renamed works out too: The create stands in front, the
 * change lays itself on top. The same order in which the server will get
 * to see it — so what stands here is exactly what will arrive there.
 *
 * AN 'aendern' ON SOMETHING UNKNOWN IS PASSED OVER. A PATCH carries only
 * fields, not the whole record; inventing one from it would mean putting
 * half a point without a coordinate onto the map.
 *
 * DOES NOT KNOW THE RECORDS. Here things are merged, not interpreted —
 * which is why the same function serves for points, lists and areas. What
 * a record must bring along so the application can draw it, the caller
 * knows; it passes that along as 'defaults'.
 *
 * @param {string} section  'points', 'lists', 'areas'
 * @param {Array}  rows   as from the server
 * @param {object} [defaults]  laid under a created one, for fields the
 *        server only sets when responding (color, symbol, counters)
 */
export function replay(section, rows, defaults = {}) {
    if (line.length === 0) return rows;

    const merged = new Map(rows.map(s => [s.id, s]));

    for (const entry of line) {
        if (entry.section !== section) continue;

        if (entry.type === 'create') {
            merged.set(entry.provisional,
                { ...defaults, ...entry.data, id: entry.provisional, pending: true });
        } else if (entry.type === 'update') {
            const old = merged.get(entry.id);
            if (old) merged.set(entry.id, { ...old, ...entry.data, pending: true });
        } else if (entry.type === 'delete') {
            merged.delete(entry.id);
        }
    }

    return [...merged.values()];
}

// ---------------------------------------------------------------------------
// Rejected entries
// ---------------------------------------------------------------------------

/**
 * What the server rejected stays kept — until someone has seen it.
 *
 * Throwing it away and only showing a message would mean: Whoever misses
 * the message never learns what they are missing. So it lies here, is
 * presented again at every start, and only disappears once someone
 * confirms it.
 */
/**
 * @param {boolean} [hoffnungslos] true where trying again cannot help —
 *        the entry has no final id, and no amount of network will give it
 *        one. The view uses it so as not to offer a button that is a
 *        promise it cannot keep.
 */
function rememberDiscarded(entry, error, hoffnungslos = false) {
    const all = discarded();
    all.push({ entry: entry, reason: error?.message || t('unbekannter Grund'),
               // The bare status goes along: the provider's sentence says
               // WHAT the server answered, the number says what KIND of
               // answer it was — and only the kind can be turned into an
               // explanation the user can act on.
               status: error?.status ?? null,
               hoffnungslos });
    try { localStorage.setItem(DISCARDED_KEY, JSON.stringify(all.slice(-20))); } catch { /* then not */ }
}

export function discarded() {
    try {
        const d = JSON.parse(localStorage.getItem(DISCARDED_KEY) || '[]');
        return Array.isArray(d) ? d : [];
    } catch {
        return [];
    }
}

export function forgetDiscarded() {
    try { localStorage.removeItem(DISCARDED_KEY); } catch { /* never mind */ }
}

/**
 * Is this a network error — or did the server answer?
 *
 * The difference decides everything: A rejected call (invalid coordinate,
 * missing permission) must NOT go into the queue, or it will try it for
 * all eternity. Only where no answer came at all is resubmitting right.
 */
/**
 * Is this an error that passes by itself?
 *
 * The distinction decides over lost inputs, and the most likely cases all
 * stand on this side:
 *
 *   401  The session has expired — after a day without network the normal case.
 *   419  The form token is stale, the same thing in green.
 *   408  The server took too long.
 *   429  Too many requests; again later is exactly the right thing.
 *   5xx  The server had a bad moment.
 *
 * All five are settled with a second try. Treating them like a rejection
 * would mean throwing the input away precisely when the queue would have
 * to fulfill its actual purpose.
 */
export function isTransient(error) {
    // Not JSON means the application never answered — a web-server error
    // page, a renamed endpoint, a captive portal. See api.js.
    if (error?.notJson === true) return true;
    const s = error?.status;
    return s === 401 || s === 408 || s === 419 || s === 429 || (s >= 500 && s <= 599);
}

/** What it last got stuck on — for the message to the user. */
let letzteHuerde = null;
export function hurdle() {
    return letzteHuerde;
}

export function isNetworkError(error) {
    return error instanceof TypeError            // fetch itself failed
        || error?.name === 'NetworkError'
        || error?.isNetworkError === true
        || !navigator.onLine;
}

/**
 * Work off the queue.
 *
 * WHAT IT GIVES BACK MATTERS, and it was missing. The caller measured its
 * success by how much shorter the queue had become — but an entry the
 * server refuses for good ALSO leaves the queue. Four changes of which one
 * got through and three were refused were therefore announced as "4
 * Änderungen nachgetragen", while the warning bar stayed standing beside
 * the message and said the opposite. Two sentences that contradict each
 * other leave the reader with nothing to go on.
 *
 * @param {(entry) => Promise<any>} send  sends an entry to the server
 * @param {(oldId:number, newId:number) => void} [rewritten] reports that a
 *        provisional id was replaced by the real one
 * @returns {Promise<{sent: number, rejected: number}>}
 */
export async function drain(send, rewritten) {
    const tally = { sent: 0, rejected: 0, settled: 0 };
    if (running || line.length === 0 || !navigator.onLine) return tally;
    running = true;

    /* A DRAIN MUST END, WHATEVER THE SENDER DOES.
     *
     * One pass settles exactly one entry — it goes through, it is put
     * aside, or the loop breaks off. The re-ordering branch below may cost
     * a second pass for the same entry, hence twice the length. More than
     * that cannot be right, and it happened: while the sender still routed
     * through the enqueueing wrappers, every failed attempt put a fresh
     * copy at the back. The queue never got shorter, and the loop hammered
     * a line that was not working — measured, 201 real requests without a
     * pause, reported as 200 successful transmissions.
     *
     * That cause is fixed (see sendEntry in api.js). This stays anyway: a
     * loop that sends is one that can eat a battery in a pocket, and no
     * future sender should be able to bring that back. */
    let round = 0;
    const atMost = line.length * 2;

    try {
        while (line.length > 0) {
            if (++round > atMost) {
                console.warn('Queue not getting shorter — stopped after',
                             round, 'rounds.');
                break;
            }
            const entry = line[0];

            /* SOMEBODY IS ALREADY HOLDING IT. The caller that reserved it
               is trying the line itself right now; it will settle or
               release it. Going past it would put what comes after in
               front — and order is the one thing this queue promises. */
            if (entry.sending) break;

            /* AN ENTRY POINTING AT A PROVISIONAL ID CANNOT EVER ARRIVE.
             *
             * A change carries the id of the record it belongs to. As long
             * as that record was only created on the device, the id is the
             * made-up negative one, and it is turned into the real one when
             * the create goes through (replaceKey below). If the create is
             * NOT in the queue any more — refused earlier, or already
             * handed in while this device kept showing the old number — the
             * negative one stays.
             *
             * The server then answers "Keine Kennung angegeben", which is
             * true and useless: it is our number that is wrong, not the
             * request. And because refusing puts the entry into the
             * discarded list and the list offers "Noch einmal versuchen",
             * the whole thing goes round for ever — that is exactly what
             * the maintainer ran into on 19.08.2026, four changes deep.
             *
             * So it is settled here, with a sentence that says what
             * happened and what it means for the input.
             */
            if (entry.type !== 'create' && !(Number(entry.id) > 0)) {
                /* IS THE CREATE MERELY IN THE WRONG PLACE? Then it is put
                 * in front instead of the change being thrown away.
                 *
                 * The order in here is our own bookkeeping, not a promise
                 * to anybody — and an entry that hangs on another must not
                 * go first. It gets out of order when a refused create is
                 * queued again later: it lands at the END, behind the
                 * changes that were waiting for it. Measured, that cost the
                 * change, although the create stood one step further on and
                 * would have given it a real id.
                 *
                 * No loop can come of it: the create moved to the front is
                 * the very next entry, and it leaves the queue when it goes
                 * through. */
                const createIndex = line.findIndex(
                    e => e.type === 'create' && e.provisional === Number(entry.id)
                );
                if (createIndex > 0) {
                    line.unshift(line.splice(createIndex, 1)[0]);
                    persist();
                    continue;
                }

                const error = new Error(t(
                    'Diese Änderung gehört zu einem Eintrag, den dieses Gerät nur '
                    + 'vorläufig kannte — seine endgültige Kennung ist hier nie '
                    + 'angekommen. Sie lässt sich deshalb nicht mehr zuordnen. '
                    + 'Der Eintrag selbst kann auf dem Server durchaus vorhanden sein.'
                ));
                console.warn('Entry without a final id, not sendable:', entry);
                rememberDiscarded(entry, error, true);
                tally.rejected++;
                line.shift();
                persist();
                continue;
            }

            let answer;
            try {
                answer = await send(entry);
            } catch (error) {
                if (isNetworkError(error)) {
                    // Still no network. Leave it standing, continue later.
                    break;
                }
                // The server answered — but "answered" does not mean
                // "rejected for good".
                //
                // A phone that spent a day in the dead zone almost
                // inevitably brings along, on resurfacing, an expired
                // session or a stale form token. Both are exactly the
                // situation this queue exists for — and both would be
                // settled with a second try after signing in. Such entries
                // stay put.
                if (isTransient(error)) {
                    console.warn('Did not get through for now, stays put:', entry, error);
                    letzteHuerde = error;
                    break;
                }

                /* THE TARGET IS GONE — THEN THE MATTER IS SETTLED, not
                 * failed.
                 *
                 * A 404 says the record no longer exists on the server. It
                 * was deleted there, deliberately and for good; the trash
                 * would still have taken the change (measured). So somebody
                 * decided this record away, and a change to it has no
                 * addressee any more. For a delete it is even the goal
                 * itself: gone is what was wanted.
                 *
                 * The call of 19.08.2026: then do not report it as
                 * an error either. A warning that offers nothing to do
                 * teaches people to click warnings away — and the next one
                 * that matters goes with it. The entry leaves quietly; the
                 * log keeps it for whoever goes looking.
                 *
                 * A withdrawn share reads the same way from here. It too is
                 * somebody's decision, and the change could not land in any
                 * case. */
                if (error?.status === 404) {
                    console.warn('Ziel gibt es nicht mehr, Eintrag erledigt:', entry);
                    tally.settled++;
                    line.shift();
                    persist();
                    continue;
                }

                // What remains is the real case: The server will never
                // accept this — a list that no longer exists, an impossible
                // coordinate. It has to leave the queue, or it blocks
                // everything after it forever. It is not thrown away for
                // that: Somebody entered something there, and without
                // keeping it there would later be nothing left to show
                // them.
                console.warn('Entry finally rejected:', entry, error);
                rememberDiscarded(entry, error);
                tally.rejected++;
                line.shift();

                /* WHAT HUNG ON IT GOES ALONG. A refused create means the
                 * record never came into being — every later change to it
                 * points at a number that will never exist, and each would
                 * fail on its own with a confusing message. They are not
                 * thrown away: they land in the same list and are shown, so
                 * one can see what was lost. */
                if (entry.provisional != null) {
                    for (const dependent of line.filter(e => e.id === entry.provisional)) {
                        rememberDiscarded(dependent, new Error(t(
                            'Gehört zu der Änderung darüber, die der Server abgelehnt hat — '
                            + 'ohne sie gibt es den Eintrag nicht, auf den sie sich bezieht.'
                        )), true);
                        tally.rejected++;
                    }
                    line = line.filter(e => e.id !== entry.provisional);
                }

                persist();
                continue;
            }

            // Created: Replace the provisional id with the real one — in
            // all still-waiting entries and in the application.
            if (entry.provisional != null && answer?.id != null) {
                replaceKey(entry.provisional, answer.id);
                rewritten?.(entry.provisional, answer.id);
            }

            letzteHuerde = null;
            tally.sent++;
            line.shift();
            persist();
        }
    } finally {
        running = false;
    }

    return tally;
}

/**
 * Replace a provisional id with the real one everywhere in the queue.
 *
 * Not only in the field 'id': Whoever creates a list offline and then
 * puts a point into it has the provisional list id standing IN the
 * point's data. If that were not replaced along, the point would, on
 * submission, end up in a list with a negative number — that is, in
 * none.
 *
 * That is why the whole entry is walked, nested parts included.
 */
export function replaceKey(oldId, newId) {
    const swap = (value) => {
        if (value === oldId) return newId;
        if (Array.isArray(value)) return value.map(swap);
        if (value && typeof value === 'object') {
            const out = {};
            for (const [k, v] of Object.entries(value)) out[k] = swap(v);
            return out;
        }
        return value;
    };

    for (let i = 0; i < line.length; i++) {
        line[i] = swap(line[i]);
    }
    persist();
}
