/**
 * Thin wrapper around fetch for the REST interface.
 *
 * Takes care of the CSRF token, of the envelope for PATCH and DELETE (not
 * every hoster lets those methods through), and of making sure errors arrive
 * as an exception with readable text instead of as a silent failure.
 *
 * AND OF THE DEAD ZONE — and specifically HERE, not at every call site.
 *
 * Every writing call that does not get through goes into the queue and is
 * submitted later, as soon as there is network again. The caller still gets
 * a usable response back: when creating, the sent data together with a
 * provisional id; when changing, the change. Its success path thus keeps
 * running unchanged; the field 'pending' only tells it that the confirmation
 * is still pending.
 *
 * The reason for this layer: If the resubmitting lived at the call sites,
 * every future feature would have to remember it — and one of them forgets.
 * Down here, everything that goes through this interface is offline-capable
 * without anyone having to do anything for it.
 */

import * as queue from './queue.js';
import { t } from './i18n.js';

const base = window.START_DATA.baseUrl + 'api/v1/';
const csrf  = window.START_DATA.csrf;

/**
 * The raw call — exported, because the cloud upload needs a route past the
 * queue. Everything else goes through api.post/patch, which add a device id
 * and buffer offline; a file that is already lying at pCloud must not be
 * enqueued a second time.
 */
/**
 * HOW LONG A CALL MAY REMAIN UNANSWERED.
 *
 * fetch has no limit of its own. It waits as long as the operating system
 * keeps the connection alive — in a dead zone that can be minutes, and with
 * a captive portal that never answers, for ever.
 *
 * AND EXACTLY THERE IT COSTS INPUTS, measured on 21.08.2026: the queue only
 * receives an entry in the CATCH of a writing call. As long as fetch hangs,
 * nothing is caught, so nothing is enqueued — the input lives solely in RAM
 * and in the form field. Whoever then reloads, or gives up and closes the
 * window, has written for nothing.
 *
 * The dead zone is not the same as "offline": navigator.onLine is true out
 * there, because the phone is attached to a cell tower. Everything in this
 * application that keys off that flag therefore does not engage. This limit
 * is what turns a hanging line into a queued entry.
 *
 * TWELVE SECONDS is chosen so that a bad but working line still gets
 * through — measured, a point over slow mobile data takes far under two —
 * while nobody stands in front of a window for half a minute. Whoever is
 * cut off after all loses nothing: the entry goes into the queue and is
 * handed in later.
 */
const TIMEOUT = 12000;

/** Files may take longer: an upload is not a hanging line, it is work. */
const TIMEOUT_FILE = 120000;

export async function request(path, options = {}) {
    let response;
    /* No AbortSignal.timeout(): it exists, but the older WebViews on
       Android do not have it, and this is precisely the device that stands
       in the dead zone. The controller works everywhere. */
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(),
        options.body instanceof FormData ? TIMEOUT_FILE : TIMEOUT);
    try {
        response = await fetch(base + path, {
        credentials: 'same-origin',
        signal: controller.signal,
        ...options,
        headers: {
            'Accept': 'application/json',
            // Not for FormData: There the browser has to set the type itself,
            // because only it knows the boundary sequence it has just
            // generated. A hand-set multipart/form-data without boundary
            // arrives as an empty $_POST — no error, just empty.
            ...(options.body && !(options.body instanceof FormData)
                ? { 'Content-Type': 'application/json' } : {}),
            ...(options.method && options.method !== 'GET' ? { 'X-CSRF-Token': csrf } : {}),
            ...options.headers,
        },
        });
    } catch (e) {
        // fetch only fails when no response came at all — no network, server
        // unreachable, connection lost midway. Everything else comes back as
        // a response with a status code. This is exactly the distinction the
        // queue needs: resubmit yes, but only here.
        //
        // A run-out clock reads the same from here, and rightly so: nobody
        // answered. It gets its own sentence because it is the only one of
        // these cases in which the line APPEARS to be there.
        const error = new Error(e?.name === 'AbortError'
            ? t('Die Verbindung antwortet nicht.')
            : t('Keine Verbindung.'));
        error.isNetworkError = true;
        throw error;
    } finally {
        clearTimeout(timer);
    }

    let data = null;
    let json = true;
    try {
        data = await response.json();
    } catch {
        // No JSON returned — usually a PHP error page or an expired login.
        // Both are an error here.
        json = false;
    }

    if (!response.ok) {
        // The status code has to come along: The queue uses it to decide
        // whether an entry may remain (login expired, server briefly gone)
        // or has failed for good (invalid input). Without it, it throws both
        // into the same pot — and loses inputs that would only have needed a
        // second try.
        const error = new Error(response.status === 401
            ? t('Die Sitzung ist abgelaufen. Bitte die Seite neu laden.')
            /* 'error' is what json_error() sends (includes/helpers.php).
               This line asked for 'fehler' and therefore never found one:
               every reason the server gave — "Der Titel fehlt", "Diese
               Liste gibt es nicht mehr" — was swallowed and replaced by
               the bare status number. Found on 22.08.2026 by searching
               the whole project for one German word, after the same
               mistake had hidden the friend list. */
            : (data?.error || t('Server meldet %s.', response.status)));
        error.status = response.status;

        // NO JSON MEANS: the application never answered.
        //
        // The status code alone does not say that. A 404 from the
        // application ("point not found") is final — the caller may throw
        // its entry away. A 404 from the WEB SERVER ("File not found.",
        // as HTML) means the address does not exist right now: a renamed
        // endpoint, a deployment half through, a hoster error page, a
        // captive portal in the cafe.
        //
        // The two were indistinguishable, and it cost voucher photos:
        // after api/v1/fotos.php was renamed to photos.php, every upload
        // got the hoster's HTML 404 — and photos.js, which treats 404 as
        // final, deleted the picture from the store. Silently, because it
        // had already been taken.
        //
        // Whoever asks isTransient() gets the honest answer through this
        // flag: we did not reach the application, so try again later.
        error.notJson = !json;
        throw error;
    }

    return data;
}

/**
 * Wrap PATCH and DELETE in POST. On some shared hosters the firewall drops
 * those methods; the envelope always gets through.
 */
function write(path, method, data) {
    return request(path, {
        method: 'POST',
        body: JSON.stringify({ ...data, _methode: method }),
    });
}

/**
 * A waiting entry, off to the server.
 *
 * The queue knows no URLs, only what needs doing — that keeps it independent
 * of the interface and lets it survive when something changes here.
 */
/* PAST api.post/patch/remove, AND THAT IS THE WHOLE POINT OF THIS
   FUNCTION. Until 21.08.2026 it called exactly those — the wrappers whose
   job it is to put a failed call INTO the queue. An entry that came out of
   the queue and did not get through was therefore not reported as failed:
   it was quietly enqueued a second time, and the wrapper returned a
   made-up success ({pending: true}) for it.

   drain() believed that. It counted the entry as transmitted, took it off
   the front — and found the fresh copy at the back. The queue never got
   shorter, the loop never ended, and every pass was a REAL request on a
   line that was not working.

   Measured with a sender that behaves like api.post: 201 attempts without
   a pause, queue length unchanged, and drain's answer read {sent: 200}.
   Two hundred transmissions that never happened, on the very connection
   this queue exists for.

   Down here the raw call is used. Whatever fails, fails visibly — and
   drain() decides for itself whether it stays (transient), is settled
   (404) or is put aside (finally refused). It cannot do that if it is
   handed a lie. */
export function sendEntry(e) {
    if (e.type === 'create') {
        return request(`${e.section}.php`, { method: 'POST', body: JSON.stringify(e.data) });
    }
    if (e.type === 'update') {
        return write(`${e.section}.php?id=${encodeURIComponent(e.id)}`, 'PATCH', e.data);
    }
    if (e.type === 'delete') {
        return write(`${e.section}.php?id=${encodeURIComponent(e.id)}${e.parameter ?? ''}`, 'DELETE', {});
    }
    return Promise.reject(new Error(t('Unbekannte Art: %s', e.type)));
}

/**
 * Attach a device id when creating.
 *
 * It makes retrying safe: If the same one arrives a second time, the server
 * returns the record it already created instead of producing a second one.
 * Without it, resubmitting would be a dice game between lost and duplicated
 * entries.
 */
function withKey(data) {
    return data.client_id ? data : { ...data, client_id: crypto.randomUUID() };
}

/** Network error? Then resubmit. Everything else is a real rejection. */
function isNetworkError(e) {
    return e?.isNetworkError === true || e instanceof TypeError || !navigator.onLine;
}

/**
 * May this failure wait in the queue?
 *
 * NOT ONLY WHEN NO ANSWER CAME AT ALL. That was the sole condition until
 * now: if the server returned anything, the call counted as rejected and
 * was thrown on — and in the point window revertSave caught it, put the
 * stock back and wrote the input into the fields again. There it stood,
 * unsaved, and was gone the moment the window closed.
 *
 * But precisely the likeliest cases on a tour sit on the other side: a
 * session that expired after a day without network (401), a form token
 * gone stale (419), a sign-in portal in a hotel WLAN answering with HTML
 * instead of JSON (notJson), or a proxy that says 502 on a weak line. All
 * of them settle with a second attempt — that is what the queue is for.
 *
 * queue.isTransient() has described these cases all along and was checked
 * ONLY while draining, never while sending. An entry that never made it
 * into the queue never arrived there either.
 *
 * NO DUPLICATE CREATES CAN ARISE FROM THIS: every writing call carries a
 * client_id, and the server recognises by it what it already has.
 */
function mayWait(e) {
    return isNetworkError(e) || queue.isTransient(e);
}

export const api = {
    /**
     * Full URL for an area.
     *
     * Needed where request() is not used but fetch and the cache storage
     * directly — the species list is fetched by the browser itself, so that
     * it can store it away.
     */
    url: (section) => base + section + '.php',

    get: (section, params = {}) => {
        const query = new URLSearchParams(params).toString();
        return request(`${section}.php${query ? '?' + query : ''}`);
    },

    /**
     * @param {number} [provisional] the id the caller is ALREADY showing
     *        this record under. Passing it matters more than it looks:
     *        without it, two provisional numbers exist for one record —
     *        the caller's and the one made up down here — and a change
     *        saved while the create is still on the line carries the
     *        caller's while the create entry carries the other. The queue
     *        then finds no create for that change, and discards it as
     *        unassignable. That is a lost title, and it is exactly the
     *        case that arises on a flapping line.
     */
    async post(section, data, provisional) {
        const withId = withKey(data);
        const id = provisional ?? queue.provisionalKey();
        // RESERVED BEFORE THE LINE IS TOUCHED — see queue.reserve(). From
        // here on the input survives a crash, whatever the network does.
        const ticket = queue.reserve({ type: 'create', section, data: withId, provisional: id });
        try {
            const answer = await request(`${section}.php`, {
                method: 'POST',
                body: JSON.stringify(withId),
            });
            /* THE REAL ID INTO EVERYTHING STILL WAITING, and before the
               reservation goes. Whoever saved a change while this create
               was on the line has it standing in the queue under the
               provisional number; without this line it would keep it and
               be thrown away later as unassignable. */
            if (answer?.id != null && answer.id !== id) queue.replaceKey(id, answer.id);
            queue.settle(ticket);
            return answer;
        } catch (e) {
            if (!mayWait(e)) { queue.settle(ticket); throw e; }
            // Nobody answered. The entry stays and goes with the queue;
            // what comes back is the sent data — enough for the caller to
            // display it immediately.
            queue.release(ticket);
            return { ...withId, id, pending: true };
        }
    },

    async patch(section, id, data) {
        /* A PROVISIONAL NUMBER NEVER GOES ON THE LINE. It is ours, the
           server has never heard of it, and it would answer "Keine Kennung
           angegeben" — a refusal, and a refusal is final: the change would
           be put aside and the input lost. The record it belongs to is
           still waiting in the queue itself; the change joins it there and
           gets the real number when the create goes through (replaceKey).
           This is durable in the same breath — no line is involved. */
        if (Number(id) < 0) {
            queue.enqueue({ type: 'update', section, id, data });
            return { ...data, id, pending: true };
        }
        const ticket = queue.reserve({ type: 'update', section, id, data });
        try {
            const answer = await write(`${section}.php?id=${encodeURIComponent(id)}`, 'PATCH', data);
            queue.settle(ticket);
            return answer;
        } catch (e) {
            if (!mayWait(e)) { queue.settle(ticket); throw e; }
            queue.release(ticket);
            return { ...data, id, pending: true };
        }
    },

    async remove(section, id, params = '') {
        const ticket = queue.reserve({ type: 'delete', section, id, parameter: params });
        try {
            const answer = await write(`${section}.php?id=${encodeURIComponent(id)}${params}`, 'DELETE', {});
            queue.settle(ticket);
            return answer;
        } catch (e) {
            if (!mayWait(e)) { queue.settle(ticket); throw e; }
            queue.release(ticket);
            return { deleted: id, pending: true };
        }
    },

    /**
     * Send files.
     *
     * WITHOUT THE QUEUE, and deliberately so: An image does not fit into
     * localStorage. Photos wait in their own store (photostore.js), and
     * whoever fails here gets the error back and decides for themselves.
     * The rest of the interface remains untouched by this.
     */
    senden(section, form) {
        return request(`${section}.php`, { method: 'POST', body: form });
    },
};
