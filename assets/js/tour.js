/**
 * The active tour — a named outing whose name every new point receives.
 *
 * A TOUR IS A LABEL, NOT A RECORD. It lives in the settings bundle of the
 * device ({name, day}) and on the points it stamped (point.tour, migration
 * 0071); there is no tour table. That is the offline trade written out at
 * the migration: a table would need provisional ids for tours begun in a
 * dead zone, the label needs nothing — it travels inside the point like the
 * title does.
 *
 * A TOUR CROSSES MIDNIGHT ONLY IN MOTION (23.08.2026 — night
 * moth monitoring): on its own day it runs unconditionally, but on the
 * following one only while the LAST POINT lies no more than six hours
 * back. The mushroom day is untouched by this — nobody maps at three in
 * the morning —, and a moth night at the light runs through without a
 * button. This is the old six-hour boundary of the retired gap detection
 * (touren.js, gone 14.08.2026), but shrunk to the one honest question it
 * can answer: does the RUNNING tour still run? Nothing is guessed from
 * stored points any more. Several tours ON one day stay the point of the
 * exercise — and only there does nextName() number them.
 *
 * WHERE THE TOUR IS CHOSEN is the list-selection window before creating a
 * point (selectLists in map.js): the name stands at the top, editable, with
 * "Neue Tour" next to it. This module only keeps the books.
 */

import { settings } from './settings.js';

/** Today as YYYY-MM-DD — LOCAL time. toISOString would answer in UTC and
 *  begin the new tour an hour or two early. */
export function today(now = new Date()) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * The proposed name — „Tagestour", plain and editable
 * (24.08.2026). It used to be the date, and that was a SECOND copy of
 * something the points already carry: every find has its find time, the
 * tour row shows the day beneath the name, and the filter sorts by it.
 * A name that repeats the date says nothing the row does not — and a
 * word one overwrites („Nordhang", „Pilzwanderung") says everything.
 *
 * $day is still taken: nextName counts with it, and a caller that
 * some day proposes something date-bound again finds its argument here.
 */
export function autoName(day) {
    return 'Tagestour';
}

/**
 * The next free name FOR THIS DAY: „Tagestour", then „Tagestour #2".
 *
 * THE COUNTER REACHES ONLY ACROSS THE DAY (27.08.2026: „Der
 * Standardtext soll nicht eine Nummer tragen, sondern jeden Tag nur
 * Tagestour heißen … Nur wenn man an einem Tag eine zweite Tour macht,
 * sollte man das im Namen dann kennzeichnen").
 *
 * It used to count across ALL days, and that made every outing after the
 * first one in the whole stock a numbered one: „Tagestour (7)" on a
 * Tuesday said nothing except that six outings had gone before. The
 * number is only an answer to the question it can actually answer —
 * WHICH of today's two — and on every other day the plain word is right.
 *
 * WHAT MADE THE OLD COUNTER NECESSARY IS GONE. Back then two identical
 * names were one outing, so the number was the only thing keeping them
 * apart; since migration 0079 a tour carries an id, and the browser
 * groups by it (collectTours, and the same in organize.js). Two days both
 * called „Tagestour" are two tours because they are two ids — the name
 * has to tell nobody apart any more.
 *
 * WHY „#2" AND NOT „(2)" — the maintainer's spelling. It reads as a serial
 * number rather than an aside, which is what it is: the second outing of
 * this day, not a remark about the first.
 *
 * @param {string} day the day being named, YYYY-MM-DD
 * @param {Iterable<string>} takenToday the names in use ON THAT DAY —
 *        NOT the whole stock's. Handing in everything silently brings the
 *        old across-all-days behaviour back, and nothing would look wrong
 *        about it; the caller filters (takenToday in map.js).
 */
export function nextName(day, takenToday) {
    const used = new Set([...takenToday].map((n) => String(n).trim()));
    const base = autoName(day);
    if (!used.has(base)) return base;
    for (let n = 2; ; n++) {
        const candidate = `${base} #${n}`;
        if (!used.has(candidate)) return candidate;
    }
}

/**
 * A fresh identity for a tour — twelve hex characters, minted HERE on the
 * device (24.08.2026: „vergib doch Tour-IDs").
 *
 * WHY NOT THE SERVER. A tour begins in the woods, and the woods have no
 * reception: an id fetched from the server would need a provisional one
 * beside it and a rewrite on sync — a second identity mechanism next to
 * the one the points already carry (client_id, migration 0020). Minted
 * here, the tour is complete at birth, and the sixteen bytes of chance
 * make a collision the least of anyone's worries.
 */
export function mintTourId() {
    const bytes = new Uint8Array(6);
    (globalThis.crypto ?? {}).getRandomValues?.(bytes);
    // Without a crypto source (ancient browser) chance still beats a
    // counter — two devices would hand out the same numbers.
    if (!globalThis.crypto?.getRandomValues) {
        for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    }
    return [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** Six hours — how long a tour may rest across the day boundary. */
export const TOUR_GAP_MS = 6 * 60 * 60 * 1000;

/**
 * Does the stored record still describe a running tour?
 *
 * On its own day always; on a later day only while the last point lies no
 * more than TOUR_GAP_MS back. A record without 'last' (written before the
 * rule existed) simply ends at midnight, as it did then.
 */
export function stillRunning(record, now = new Date()) {
    if (!record || typeof record !== 'object') return false;
    if (String(record.name ?? '').trim() === '') return false;
    if (record.day === today(now)) return true;
    const last = Number(record.last);
    return Number.isFinite(last) && now.getTime() - last <= TOUR_GAP_MS;
}

/** The running tour {name, day, id}, or null — day is the START day, so
 *  the six-hour rule keeps naming the evening the outing began. The id is
 *  the identity every point of this tour receives. */
export function activeTour() {
    const t = settings.get('tour', null);
    if (!stillRunning(t)) return null;
    return { name: String(t.name).trim(), day: t.day, id: t.id ?? null };
}

/**
 * From now on this is the outing every new point belongs to.
 *
 * @param {string} day the outing's START day — the caller passes the
 *        running tour's day when continuing or renaming it, so a night
 *        tour keeps the evening it began on; a fresh tour gets today.
 */
export function rememberTour(name, day = today(), id = null) {
    // 'last' is the six-hour rule's clock: stamped on every point, read by
    // stillRunning() across midnight.
    //
    // WITHOUT AN ID GIVEN THE RUNNING ONE KEEPS ITS OWN — renaming an
    // outing must not make it a different one; only „Neue Tour" mints
    // (map.js). A record from before migration 0079 has none: then one is
    // minted now, so the tour running across the update keeps its points
    // together from this moment on.
    const before = settings.get('tour', null);
    settings.set('tour', {
        name: String(name).trim(),
        day,
        id: id ?? before?.id ?? mintTourId(),
        last: Date.now(),
    });
}
