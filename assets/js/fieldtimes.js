/**
 * Field times, device side: Which fields did this save really touch?
 *
 * WHAT FOR. Until now the form sent the WHOLE record on every save — the
 * seven fields nobody touched included. As long as there was only one
 * device, that did not matter. With two it is the mistake par excellence:
 * In the evening the phone submits its forest change and in doing so
 * overwrites the note written at the desktop in the afternoon — with its
 * own old state of it.
 *
 * That is why comparing happens here: Only what changed against the known
 * state goes to the server, and with two entries per field —
 *
 *   field_times[field]   when this device changed the field (full map, the
 *                      untouched times travel along too; the server only
 *                      reads the ones for fields that were sent)
 *   field_seen[field]  which state the device knew at the time
 *
 * The decision of who wins is made EXCLUSIVELY on the server
 * (includes/field_times.php) — here we only report honestly. The rules
 * stand there once, not twice.
 *
 * WHEN IN DOUBT, IT IS TOUCHED. Sending one field too many costs nothing —
 * the server discards equal values itself. Sending one field too FEW loses
 * an input. Every comparator here is therefore built so that uncertainty
 * counts as a change.
 *
 * PURE FUNCTIONS, no application, no DOM — so tests/fieldtimes.mjs can
 * pin down each comparator individually. The clock comes in as a parameter,
 * for the same reason.
 */

/** Text fields: differences beyond edge whitespace count. */
function textEqual(a, b) {
    return String(a ?? '').trim() === String(b ?? '').trim();
}

/**
 * Find time: The field delivers "2026-08-10T14:23", the server stores
 * "2026-08-10 14:23:11". Compared to the minute — the field can neither
 * show nor change the seconds, so they cannot be what was meant either.
 */
function findTimeEqual(a, b) {
    const norm = (w) => {
        const t = String(w ?? '').trim().replace(' ', 'T');
        return t === '' ? '' : t.slice(0, 16);
    };
    return norm(a) === norm(b);
}

/** Location: eleven centimeters (1e-6 degrees) is the column's resolution — as server-side. */
function placeEqual(lat1, lon1, lat2, lon2) {
    if (lat1 == null || lon1 == null || lat2 == null || lon2 == null) {
        return lat1 == null && lat2 == null && lon1 == null && lon2 == null;
    }
    return Math.abs(Number(lat1) - Number(lat2)) <= 1e-6
        && Math.abs(Number(lon1) - Number(lon2)) <= 1e-6;
}

/** Lists: the same ids, order irrelevant. */
function listsEqual(a, b) {
    const norm = (l) => [...new Set((l ?? []).map(Number))].sort((x, y) => x - y).join(',');
    return norm(a) === norm(b);
}

/**
 * Outline: corner by corner, rounded to the sixth place — the server rounds
 * the same way when writing. Without the rounding, every second comparison
 * reported a change, because the dragged corner carries more decimal places
 * than the stored one.
 */
/* Exported since saveForeign (map.js) sends along the outline of
   shared points: It must compare EXACTLY LIKE differences() here, or over
   there something would count as changed that would be no change here. */
export function outlineEqual(a, b) {
    const norm = (u) => {
        if (!Array.isArray(u) || u.length === 0) return null;
        return JSON.stringify(u.map((e) => [
            Math.round(Number(e[0]) * 1e6) / 1e6,
            Math.round(Number(e[1]) * 1e6) / 1e6,
        ]));
    };
    return norm(a) === norm(b);
}

/**
 * Sift through the one save.
 *
 * @param {object} before     the point as this device knows it
 * @param {object} change  the full form, as it used to be sent
 * @param {string} now      ISO time of this save (passed in, testable)
 * @returns {{data: ?object, times: ?object}}
 *   data   what goes to the server — only what was touched, along with
 *           field_times/field_seen; null if nothing changed (then there is
 *           nothing to send)
 *   times  the carried-forward time map for the local point
 */
export function differences(before, change, now) {
    const data    = {};
    const seen  = {};
    const times   = { ...(before.field_times ?? {}) };
    const touch = (field) => {
        times[field]  = now;
        seen[field] = before.field_times?.[field] ?? null;
    };

    if ('title' in change && !textEqual(change.title, before.title)) {
        data.title = change.title;
        touch('title');
    }
    if ('note' in change && !textEqual(change.note, before.note)) {
        data.note = change.note;
        touch('note');
    }
    if ('found_at' in change && !findTimeEqual(change.found_at, before.found_at)) {
        data.found_at = change.found_at;
        touch('found_at');
    }
    // The find number by hand (the window's field, one's own finds only):
    // a field like the title — two devices, the later edit wins, the other
    // lands in the conflict list.
    if ('find_number' in change && !textEqual(change.find_number, before.find_number)) {
        data.find_number = change.find_number;
        touch('find_number');
    }
    if (('lat' in change || 'lon' in change)
            && !placeEqual(change.lat, change.lon, before.lat, before.lon)) {
        data.lat = change.lat;
        data.lon = change.lon;
        touch('position');
    }
    // The Aufsammlung's days (0174) — plain days, compared as text.
    for (const key of ['cultured_at', 'culture_ended_at', 'identified_at']) {
        if (key in change && (change[key] ?? '') !== (before[key] ?? '')) {
            data[key] = change[key];
            touch(key);
        }
    }
    if ('lists' in change && !listsEqual(change.lists, before.lists)) {
        data.lists = change.lists;
        touch('lists');
    }
    if ('outline' in change && !outlineEqual(change.outline, before.outline)) {
        data.outline = change.outline;
        touch('outline');
    }

    // Extra fields one by one — substrate and accompanying vegetation are,
    // for whoever fills them in, two fields. Over the UNION of both sides,
    // so that emptying a field that no longer appears in the form at all is
    // noticed too.
    if ('extra' in change) {
        const next  = change.extra ?? {};
        const prev  = before.extra ?? {};
        const part = {};
        const sourcesPart = {};

        for (const name of new Set([...Object.keys(next), ...Object.keys(prev)])) {
            const changed = !textEqual(next[name], prev[name]);
            // Touched even with the SAME text, if a source was chosen for it
            // in this session: The same name, freshly taken over from a
            // different name list, is a change of the reference that is not
            // visible in the text.
            const source = change.extra_sources?.[name];
            if (!changed && !source) continue;

            part[name] = String(next[name] ?? '').trim();   // '' means delete
            if (source) sourcesPart[name] = source;
            touch('extra.' + name);
        }

        if (Object.keys(part).length > 0) {
            data.extra = part;
            if (Object.keys(sourcesPart).length > 0) data.extra_sources = sourcesPart;
        }
    }

    if (Object.keys(data).length === 0) {
        return { data: null, times: null };
    }

    // The FULL time map, not just the touched ones: That way it survives
    // the spreading of the response into the local point (offline too, via
    // the queue and its replay) without losing the times of
    // the untouched fields. The server reads only the entries for fields
    // that really came along anyway.
    data.field_times  = times;
    data.field_seen = seen;
    return { data, times };
}
