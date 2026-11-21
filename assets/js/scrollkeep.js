/**
 * EVERY AREA KEEPS ITS SCROLL POSITION WHEN IT IS DRAWN AGAIN
 * (20.11.2026, at the Bestimmung's taxonomy sidebar, which jumped to its top
 * at every species chosen in it: „generell sollte sich auf der ganzen Seite
 * jeder Bereich seine Scrollposition merken. Eigentlich im kompletten
 * Projekt, vielleicht machen wir da eine gemeinsame Funktion").
 *
 * The project draws by innerHTML: a click redraws a whole region, and
 * every scrolling area inside it is a new element at its top. Here is the
 * one way back. An area that wants its place kept names WHAT IT SHOWS with
 * data-scroll-key — a Finder column by its path, the taxonomy by genus and
 * book. Drawn again with the same key, it gets its position back; a new
 * key is new content and starts at the top, as it should: the next
 * species' portrait is not to be opened half way down because the last
 * one was read there. Without a key nothing is kept — on purpose, because
 * only the drawing knows whether the content is still the same.
 *
 *   keepScroll(box, () => { box.innerHTML = html; });
 *
 * or, where the drawing is spread out, scrollMarks() before and
 * scrollRestore() after. NO IMPORTS: any page may load it — and since the
 * second half below, every page does.
 */

/** Where every keyed area under root (root included) stands now. */
export function scrollMarks(root) {
    const marks = new Map();
    if (!root) return marks;
    const areas = [...(root.dataset?.scrollKey ? [root] : []), ...root.querySelectorAll('[data-scroll-key]')];
    for (const el of areas) {
        if (el.scrollTop || el.scrollLeft) marks.set(el.dataset.scrollKey, [el.scrollTop, el.scrollLeft]);
    }
    return marks;
}

/** Give every keyed area under root its position back, where its key was marked. */
export function scrollRestore(root, marks) {
    if (!root || !marks?.size) return;
    const areas = [...(root.dataset?.scrollKey ? [root] : []), ...root.querySelectorAll('[data-scroll-key]')];
    for (const el of areas) {
        const at = marks.get(el.dataset.scrollKey);
        if (!at) continue;
        el.scrollTop = at[0];
        el.scrollLeft = at[1];
    }
}

/** Draw root anew with draw(), its keyed areas keeping their places. Returns what draw() returns. */
export function keepScroll(root, draw) {
    const marks = scrollMarks(root);
    const out = draw();
    scrollRestore(root, marks);
    return out;
}

/*
 * AND A PLACE OUTLIVES THE PAGE (20.11.2026: „lange Listen wie
 * die Seitenleiste in der Karte bei den Funden oder im Tab Listen … beim
 * Fundbrowser … bei den Funden selbst die beiden Seitenleisten, egal in
 * welchem Modus"). Karte and Fundbrowser are two documents: going from one
 * to the other and back is a new page, and every list stood at its top
 * again. An area marked data-scroll-remember="<name>" writes its place into
 * sessionStorage while it is scrolled and finds it again — after a reload,
 * after the way to the other page and back, when it is drawn anew, when it
 * comes out of hiding (a tab, a mode: display:none forgets the place).
 *
 * SESSIONSTORAGE, NOT THE SETTINGS BUNDLE: a place belongs to this visit in
 * this tab. Opening the map tomorrow half way down yesterday's points list
 * would be a riddle; a new tab starts at the top as well.
 *
 * THE CONTENT COMES LATE. The points list, the tiles, the rail are drawn
 * after a fetch, so at first there is nothing to scroll and the browser
 * clamps the place to 0. The place therefore stays WANTED until the content
 * is long enough — tried again at every change in the document — and is
 * given up at the first touch of the user (wheel, pointer, finger, key) or
 * after a few seconds: a list that jumps by itself while one reads it is
 * worse than one that starts at the top. While it is wanted, the area's
 * own scroll events (our setting, clamped) are not written down — they
 * would overwrite the place with the clamped one.
 *
 * THE NAME SAYS WHAT IS SHOWN, as with data-scroll-key: one name per
 * content. The points list of the map is one content; the rail of a find
 * is one per find (finds.js renames it with scrollRemember() when another
 * find opens, and a find never seen before starts at the top).
 *
 * SELF-INSTALLING like autoheight.js — page_footer() loads it everywhere,
 * map.js imports it: a new area only needs the attribute.
 */

const STORE = 'scrollPlaces';
const STORE_MAX = 300;          // names kept; the oldest go first
const WANTED_MS = 6000;         // how long a place waits for its content
const places = new Map();       // name → [top, left, at]
const wanted = new Map();       // element → since
let writeTimer = 0;

function storeRead() {
    try {
        const raw = JSON.parse(sessionStorage.getItem(STORE) ?? '{}');
        for (const [name, at] of Object.entries(raw)) {
            if (Array.isArray(at)) places.set(name, at);
        }
    } catch { /* a private window or blocked storage: nothing remembered */ }
}

function storeWrite() {
    writeTimer = 0;
    try {
        const list = [...places].sort((a, b) => b[1][2] - a[1][2]).slice(0, STORE_MAX);
        sessionStorage.setItem(STORE, JSON.stringify(Object.fromEntries(list)));
    } catch { /* see storeRead */ }
}

function remember(el) {
    const name = el.dataset.scrollRemember;
    if (!name) return;
    places.set(name, [Math.round(el.scrollTop), Math.round(el.scrollLeft), Date.now()]);
    if (!writeTimer) writeTimer = setTimeout(storeWrite, 250);
}

/** Try to give el its place; true when there is nothing more to wait for. */
function settle(el) {
    if (!el.isConnected) return true;
    const at = places.get(el.dataset.scrollRemember ?? '');
    if (!at) return true;
    if (!el.clientHeight && !el.clientWidth) return false;   // hidden: its showing arms it again
    el.scrollTop = at[0];
    el.scrollLeft = at[1];
    return Math.abs(el.scrollTop - at[0]) <= 1 && Math.abs(el.scrollLeft - at[1]) <= 1;
}

function want(el) {
    if (settle(el)) wanted.delete(el);
    else wanted.set(el, Date.now());
}

function retryWanted() {
    const now = Date.now();
    for (const [el, since] of wanted) {
        if (settle(el) || now - since > WANTED_MS) wanted.delete(el);
    }
}

/* Coming out of hiding: a box that measured 0 and now has a size. */
const sizes = new WeakMap();
const shown = typeof ResizeObserver === 'function' ? new ResizeObserver((entries) => {
    for (const { target } of entries) {
        const visible = target.clientHeight > 0 || target.clientWidth > 0;
        if (visible && !sizes.get(target)) want(target);
        sizes.set(target, visible);
    }
}) : null;

const watched = new WeakSet();
function adopt(el) {
    if (watched.has(el)) return;
    watched.add(el);
    shown?.observe(el);
    want(el);
}

function adoptUnder(node) {
    if (node.nodeType !== 1) return;
    if (node.dataset.scrollRemember) adopt(node);
    if (node.firstElementChild) node.querySelectorAll('[data-scroll-remember]').forEach(adopt);
}

/**
 * Give el the name of what it shows now (a find's rail when another find
 * opens). A name never seen before is new content and starts at the top.
 */
export function scrollRemember(el, name) {
    if (!el || el.dataset.scrollRemember === name) return;
    el.dataset.scrollRemember = name;
    if (!places.has(name)) {
        wanted.delete(el);
        el.scrollTop = 0;
        el.scrollLeft = 0;
    }
    if (watched.has(el)) want(el);
    else adopt(el);
}

if (typeof document !== 'undefined' && !window.scrollRememberInstalled) {
    window.scrollRememberInstalled = true;
    storeRead();
    document.addEventListener('scroll', (e) => {
        const el = e.target;
        if (el?.dataset?.scrollRemember && !wanted.has(el)) remember(el);
    }, { capture: true, passive: true });
    // The user's own hand ends every waiting: from here on the place is his.
    for (const type of ['wheel', 'pointerdown', 'touchstart', 'keydown']) {
        document.addEventListener(type, () => wanted.clear(), { capture: true, passive: true });
    }
    window.addEventListener('pagehide', () => { if (writeTimer) { clearTimeout(writeTimer); storeWrite(); } });
    const start = () => {
        adoptUnder(document.body);
        new MutationObserver((records) => {
            for (const r of records) r.addedNodes.forEach(adoptUnder);
            if (wanted.size) retryWanted();
        }).observe(document.body, { childList: true, subtree: true });
    };
    if (document.body) start();
    else document.addEventListener('DOMContentLoaded', start, { once: true });
}
