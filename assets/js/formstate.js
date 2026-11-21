/**
 * Rescue a begun input across the teardown.
 *
 * THE PROBLEM BELONGS TO THE CAMERA. Whoever takes a photo sends the page
 * to the background — the camera app comes to the front and wants memory.
 * Android may tear the page down there, and on an older phone it does.
 * On coming back, a reload happens, and a half-filled point form is gone:
 * title, note, accompanying plants, list assignment.
 *
 * This is no rare edge case. It is the usual flow: set a point, label it,
 * photograph, keep labeling.
 *
 * WHY NOT THE INPUT GUARD FROM map.js. That one compares what is in the
 * field with what was last written into it — and thereby keeps arriving
 * server data away from an input in progress. But it lives in RAM and is
 * just as gone after a reload as the input itself. The two solve
 * different problems.
 *
 * WHY localStorage. It is a small entry, it must survive a crash, and it
 * must be read before the map draws — the same reasoning as with the
 * queue. Photos live elsewhere (photostore.js); here is only text.
 *
 * WHAT MUST NOT HAPPEN: that an old state overwrites a point changed
 * elsewhere in the meantime. That is why it holds for only half an hour,
 * applies to exactly one point, and the human is told that something was
 * brought back.
 */

const KEY = 'map.formstate';

/**
 * How long a left-behind state is valid.
 *
 * Long enough for the usual flow — camera up, two shots, back — and for a
 * phone that stalls along the way. Short enough that nobody is served an
 * input the next morning that they no longer remember.
 */
const MAX_AGE = 30 * 60 * 1000;

export function persist(pointId, values) {
    if (pointId == null) return;
    try {
        localStorage.setItem(KEY, JSON.stringify({ pointId, values, when: Date.now() }));
    } catch (e) {
        // Storage full. Annoying, but nothing you would halt the flow for —
        // the whole point was to take a photo.
        console.warn('Form state cannot be saved:', e);
    }
}

/** The left-behind state, or null. Anything expired is disposed of right away. */
export function get() {
    let raw;
    try {
        raw = JSON.parse(localStorage.getItem(KEY) || 'null');
    } catch {
        return null;
    }

    if (!raw || typeof raw !== 'object' || raw.pointId == null) return null;

    if (!(typeof raw.when === 'number') || Date.now() - raw.when > MAX_AGE) {
        forget();
        return null;
    }
    return raw;
}

export function forget() {
    try { localStorage.removeItem(KEY); } catch { /* then not */ }
}
