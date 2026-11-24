/**
 * The desk's frame page (pages/shell.php says what it is for and why it is
 * a page around the pages). This file holds the frames:
 *
 * - ONE <iframe> PER AREA, made when the area is first wanted, never thrown
 *   away. The area asked for loads first; the others follow in the
 *   background once it stands, so the first change to each is instant too
 *   („alles wird direkt geladen").
 *
 * - THEY ALL KEEP THEIR SIZE. The frames out of sight are hidden by
 *   visibility, not by display: a map that was laid out at no size would
 *   have to measure itself anew at every showing, and whatever fits itself
 *   to its room (the viewer, a plate) likewise. Hidden, they are inert —
 *   nothing in them takes a key or a click.
 *
 * - THE ADDRESS SAYS THE AREA (#map, #finds, a module's key), and every
 *   change is an entry in the history — the browser's back leads to the
 *   area one came from. What an area shows inside (the find open, the
 *   key's couplet) is the frame's own address; a frame's steps join the
 *   same history by themselves. For a reload the frames' addresses are
 *   kept in sessionStorage — this tab only —, so everything comes back
 *   where it stood.
 *
 * - A FRAME THAT STRAYS IS PUT RIGHT (guard). The pages change among each
 *   other in many places — a link, a form, a line of script that sets
 *   location.href. The frequent ways are handed up as messages and cost
 *   nothing (shellframe.js: the header's buttons, the map's pencil, the
 *   find's „Zeigen"). Every other way simply happens INSIDE its frame, and
 *   this file sees the result when the frame has loaded: another area's
 *   page arrived → that area takes the address, and the frame goes back to
 *   its own page; a page that is no area at all (the administration, the
 *   help, the login after the session ran out) → the whole window goes
 *   there. Slower than a message, never wrong — so no place in the pages
 *   has to know about this one to stay correct.
 *
 * THE MESSAGES, all of type 'yasaimapp-shell', same origin only:
 *   frame → here   { event: 'open', area, address }  show that area, at that
 *                    address (its query and hash; '' = as it stands)
 *                  { event: 'ready' }                the frame's page has run
 *   here → frame   { event: 'go', address, href }    take this address in
 *                  { event: 'shown' }                you are in sight again
 */

const box = document.getElementById('shell');
const AREAS = JSON.parse(box.dataset.areas);
const KEYS = Object.keys(AREAS);
const TYPE = 'yasaimapp-shell';
const STORE = 'shell.at.';

const frames = new Map();   // area → its <iframe>
const ready = new Set();    // areas whose page has run its scripts
const home = new Map();     // area → the last address of its OWN page, to go back to after straying
let active = null;

/** '/map/' and '/map/index.php' are one page. */
const plain = (pathname) => pathname.replace(/index\.php$/, '');
const areaUrl = (key) => new URL(AREAS[key].url, location.href);
const areaOf = (url) => KEYS.find((key) => plain(areaUrl(key).pathname) === plain(url.pathname)) ?? null;
const addressOf = (loc) => loc.pathname + loc.search + loc.hash;

function remembered(key) {
    try {
        const at = sessionStorage.getItem(STORE + key);
        return at && areaOf(new URL(at, location.href)) === key ? at : null;
    } catch {
        return null;
    }
}
function remember(key) {
    try {
        const loc = frames.get(key)?.contentWindow.location;
        if (loc && areaOf(loc) === key) sessionStorage.setItem(STORE + key, addressOf(loc));
    } catch { /* a frame on its way somewhere: the last address stays */ }
}

function tell(key, event, more = {}) {
    frames.get(key)?.contentWindow.postMessage({ type: TYPE, event, ...more }, location.origin);
}

function make(key, address) {
    const frame = document.createElement('iframe');
    frame.title = AREAS[key].label;
    frame.className = 'shell-frame is-aside';
    frame.inert = true;
    frame.addEventListener('load', () => guard(key));
    frame.src = address;
    frames.set(key, frame);
    box.append(frame);
    return frame;
}

/** The frame has loaded something — its own page, or it strayed (the head says how that happens). */
function guard(key) {
    const frame = frames.get(key);
    let loc;
    try {
        loc = frame.contentWindow.location;
        if (loc.href === 'about:blank') return;
    } catch {
        return;   // another origin: nothing of ours to put right
    }
    const at = areaOf(loc);
    if (at === key) {
        home.set(key, addressOf(loc));
        remember(key);
        if (key === active) title();
        return;
    }
    ready.delete(key);
    if (at === null) {
        // No area: the whole window goes there, as it would have without this page.
        location.href = loc.href;
        return;
    }
    const address = addressOf(loc);
    frame.contentWindow.location.replace(home.get(key) ?? AREAS[key].url);
    show(at, address);
}

/** Lead an area to an address — by word where its page listens already, by loading where not. */
function go(key, address) {
    const url = new URL(address, areaUrl(key));
    if (ready.has(key)) tell(key, 'go', { address: url.search + url.hash, href: url.href });
    else frames.get(key).contentWindow.location.replace(url.href);
}

function title() {
    try {
        document.title = frames.get(active)?.contentDocument.title || document.title;
    } catch { /* keeps the last one */ }
}

/**
 * Bring an area into sight.
 *
 * @param {string} key
 * @param {?string} address  where it is to stand — its query and hash, or a
 *        whole path; null = as it stands (or as it stood before a reload)
 * @param {boolean} entry    false when the history already has this step
 *        (the browser's back, the first showing)
 */
function show(key, address = null, entry = true) {
    if (!AREAS[key]) return;
    if (!frames.has(key)) {
        make(key, address ? new URL(address, areaUrl(key)).href : remembered(key) ?? AREAS[key].url);
    } else if (address) {
        go(key, address);
    }
    if (active !== key) {
        if (active) remember(active);
        active = key;
        for (const [k, frame] of frames) {
            frame.classList.toggle('is-aside', k !== key);
            frame.inert = k !== key;
        }
        if (entry) history.pushState({ area: key }, '', '#' + key);
        else history.replaceState({ area: key }, '', '#' + key);
    }
    title();
    frames.get(key).contentWindow.focus();
    if (ready.has(key)) tell(key, 'shown');
}

window.addEventListener('message', (e) => {
    if (e.origin !== location.origin || e.data?.type !== TYPE) return;
    const d = e.data;
    if (d.event === 'open') {
        show(String(d.area), d.address ? String(d.address) : null);
        return;
    }
    if (d.event === 'ready') {
        const key = [...frames].find(([, frame]) => frame.contentWindow === e.source)?.[0];
        if (!key) return;
        ready.add(key);
        if (key === active) title();
        loadTheRest();
    }
});

/* THE OTHERS IN THE BACKGROUND, one after the other and only once the area
   in sight has run: it is the one somebody waits for, and three pages
   starting at once would each be slower. */
let resting = null;
function loadTheRest() {
    clearTimeout(resting);
    resting = setTimeout(() => {
        if (!ready.has(active)) return;
        const next = KEYS.find((key) => !frames.has(key));
        if (next) make(next, remembered(next) ?? AREAS[next].url);
    }, 1200);
}

window.addEventListener('popstate', () => {
    const key = location.hash.slice(1);
    if (AREAS[key]) show(key, null, false);
});
window.addEventListener('pagehide', () => { for (const key of frames.keys()) remember(key); });

{
    const asked = location.hash.slice(1);
    show(AREAS[asked] ? asked : KEYS[0], null, false);
}
