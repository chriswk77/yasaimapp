/**
 * Settings that outlast a visit.
 *
 * Everything lives in **one** entry in local storage instead of in many
 * separate ones. That has two reasons: new settings need no new management,
 * and when cleaning up nothing gets left behind.
 *
 * Usage:
 *   settings.get('baseMap', 'Luftbild amtlich')
 *   settings.set('baseMap', name)
 *
 * Writing happens bundled shortly after the last change — while panning the
 * map, a write would otherwise occur on every frame.
 *
 * THE TAKEOVER OF OLD NAMES DOES NOT LIVE HERE: the bundle is read raw in
 * the <head> of index.php and includes/page.php before any module loads
 * (theme flash prevention), so the rename from 'karte.einstellungen' — and
 * from the even older pilzkarte entries — runs inline there, before this
 * module ever gets a chance.
 *
 * Local storage can fail: private window, full storage, script storage
 * turned off. That must never hold up the map, so everything here is
 * guarded and, at worst, without effect.
 *
 * THE BUNDLE BELONGS TO EVERY PAGE THAT IS OPEN, NOT TO THIS ONE
 * (13.11.2026: „Ich habe an meinem zweiten Laptop gestern das
 * Mikroskop eingerichtet … aber gerade sehe ich das Mikroskopiersymbol
 * nicht mehr"). Each page read the bundle once, on loading, and wrote ALL
 * of it back at every change — with what it had read then. So a page
 * opened before the microscope was set up (a second tab, or the
 * Fundbrowser one comes back to with the browser's arrow, restored as it
 * was left) wrote its old bundle over the setup at the next thing it
 * remembered: a brush size was enough. One entry for everything means
 * one page can take everything from the others.
 *
 * So a write now lays ONLY WHAT THIS PAGE CHANGED over what is stored at
 * that moment (`changed`, `forgotten`), and a page takes over what the
 * others wrote: at once where the browser says so (the `storage` event),
 * and when it is shown again out of the browser's back-forward cache
 * (`pageshow`). What this page changed and has not written yet stays its
 * own through both.
 */

const KEY = 'map.settings';

let values = load();
let writeTimer = null;
/** What this page set and what it forgot since its last write — all a write may touch. */
const changed = new Set(), forgotten = new Set();

function load() {
    try {
        const stored = JSON.parse(localStorage.getItem(KEY) || '{}');
        return (typeof stored === 'object' && stored !== null) ? stored : {};
    } catch {
        return {};
    }
}

/** What is stored right now, with this page's own unwritten changes laid over it. */
function merged() {
    const stored = load();
    for (const name of changed) stored[name] = values[name];
    for (const name of forgotten) delete stored[name];
    return stored;
}

/** Write: this page's changes over what the others stored meanwhile — never the bundle as this page once read it. */
function write() {
    clearTimeout(writeTimer);
    writeTimer = null;
    try {
        values = merged();
        localStorage.setItem(KEY, JSON.stringify(values));
        changed.clear();
        forgotten.clear();
    } catch (e) {
        // Full or locked — the map keeps working, just without memory.
        // Reporting once is enough.
        console.warn('Einstellungen lassen sich nicht speichern:', e);
    }
}

function writeSoon() {
    clearTimeout(writeTimer);
    writeTimer = setTimeout(write, 300);
}

/* What another page wrote is taken over — the moment it writes, and when
   this page comes back out of the browser's cache as it was left. Not in
   a test without a window. */
if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
    window.addEventListener('storage', (e) => { if (e.key === KEY || e.key === null) values = merged(); });
    window.addEventListener('pageshow', (e) => { if (e.persisted) values = merged(); });
}

export const settings = {
    /**
     * THE LIVE VALUE, not a copy — and for objects and arrays that
     * matters. Whoever wants to CHANGE one has to copy it first:
     *
     *     const all = { ...(settings.get('map', {}) || {}) };
     *     all[key] = value;
     *     settings.set('map', all);
     *
     * Without the copy the change lands in the stored object directly, and
     * set() then compares it against itself, finds it unchanged and writes
     * nothing (see there). Everything looks right until the next reload,
     * where the change is gone. Caught on 20.08.2026 with the looks of
     * the shared lists.
     */
    get(name, fallback = null) {
        return name in values ? values[name] : fallback;
    },

    set(name, value) {
        // Unchanged? Then do not write either.
        if (JSON.stringify(values[name]) === JSON.stringify(value)) return;
        values[name] = value;
        changed.add(name);
        forgotten.delete(name);
        writeSoon();
    },

    /**
     * Forget ONE value — for a setting whose meaning has changed.
     *
     * Not the same as setting it to null: null is a stored answer, absent
     * means "never chosen", and the two lead to different defaults. The
     * species groups need exactly that difference (see chosenGroups).
     */
    forget(name) {
        if (!(name in values)) return;
        delete values[name];
        forgotten.add(name);
        changed.delete(name);
        writeSoon();
    },

    /**
     * Write NOW instead of in a moment — before leaving the page.
     *
     * THE BUNDLED WRITE IS 300 ms LATE, and that is right while panning a
     * map: without it every frame would write. It is wrong at exactly one
     * moment, and that moment cost a drawn area on 23.08.2026: the map
     * stored a filter area and navigated straight back to the
     * administration — the navigation won, the timer never fired, and the
     * area was gone without a word. Whoever hands over to another PAGE
     * flushes first.
     *
     * Not a substitute for set(): it writes what set() collected, it does
     * not collect anything itself.
     */
    flush() {
        write();
    },

    /** Forget everything — for a "Ansicht zurücksetzen" button. */
    reset() {
        values = {};
        changed.clear();
        forgotten.clear();
        clearTimeout(writeTimer);
        try {
            localStorage.removeItem(KEY);
        } catch { /* no harm done */ }
    },

    /** For debugging in the console. */
    all() {
        return { ...values };
    },
};
