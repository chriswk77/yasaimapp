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
 *   its own page; a page that is no area at all (the help, the login
 *   after the session ran out) → the whole window goes there. Slower than
 *   a message, never wrong — so no place in the pages has to know about
 *   this one to stay correct.
 *
 * - A TOOL IS NOT KEPT (the administration; 'tool' in the areas' list,
 *   pages/shell.php says why it is in the frame at all). Its frame is made
 *   when it is asked for — never ahead — and removed when one leaves it;
 *   the next visit loads it anew. And leaving it makes every area OLD
 *   (stale): the one one goes to is loaded anew at once, the others behind
 *   it, one after the other. That is what the change of page did before
 *   this page existed — whoever came back from the administration got a
 *   fresh map —, and it is the only rule that is never wrong: what a tool
 *   changes reaches the areas by many roads (a form, a request, the
 *   browser's storage, its cache of tiles and outlines), and watching them
 *   all to save one loading would miss one some day.
 *
 * - THE DESK'S ENTRY (includes/shell.php tells the whole of it). A window
 *   that asked for an area's page arrives here in one of two shapes: sent
 *   by the server as ?area=<key>&at=<the page's query>, the # of the
 *   address still behind it — or sent by the page itself, bare, with the
 *   address already in this tab's store. Either way the area is shown at
 *   that address and this page's own address is tidied to '#<area>'.
 *   AND THE WAY BACK OUT: on a device that is no desk (no mouse, a narrow
 *   window, the recorder app, a tab that said 'shell.off') nothing is
 *   loaded here at all — the area's own page is asked for through
 *   ?leave=…, which also takes the session's mark back, or the server
 *   would send the page's window straight here again.
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
const stale = new Set();    // areas loaded before a tool was used: to be loaded anew
let active = null;

/** A tool (the administration): loaded when asked for, let go when left — the head says why. */
const isTool = (key) => AREAS[key]?.tool === true;

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
    stale.delete(key);
    box.append(frame);
    return frame;
}

/** One leaves a tool: its frame goes, and whatever stands loaded beside it is old from here on. */
function leaveTool(key) {
    frames.get(key)?.remove();
    frames.delete(key);
    ready.delete(key);
    home.delete(key);
    // Not where it stood: the next visit is a new one. (A reload WHILE in it finds its address — pagehide below.)
    try { sessionStorage.removeItem(STORE + key); } catch { /* then it was never kept */ }
    for (const k of frames.keys()) stale.add(k);
}

/**
 * Load an old area anew — at the address asked for, else where it stands.
 *
 * NEVER BY reload() ALONE WHERE AN ADDRESS IS ASKED FOR, and never by
 * replace() alone: an address that differs only behind the # is a step
 * inside the document and loads nothing, so the hash is set first and the
 * page loaded anew after it; an address with another query is a loading by
 * itself.
 */
function freshen(key, address = null) {
    stale.delete(key);
    ready.delete(key);
    const frame = frames.get(key);
    const url = address ? new URL(address, areaUrl(key)) : null;
    try {
        const loc = frame.contentWindow.location;
        if (url && plain(url.pathname) + url.search !== plain(loc.pathname) + loc.search) {
            loc.replace(url.href);
            return;
        }
        if (url && url.hash !== loc.hash) loc.replace(url.href);
        loc.reload();
    } catch {
        frame.src = url?.href ?? home.get(key) ?? AREAS[key].url;   // a frame that strayed out of reach
    }
}

/** The frame has loaded something — its own page, or it strayed (the head says how that happens). */
function guard(key) {
    const frame = frames.get(key);
    let loc;
    try {
        loc = frame.contentWindow.location;
        if (loc.href === 'about:blank') return;
    } catch {
        /* ANOTHER ORIGIN IN THE FRAME — a form whose answer sends the
           browser to a foreign service (a cloud's consent page), and such
           pages refuse to stand in a frame: one would look at an empty
           area. Which address it was cannot be read from here, so the area
           goes back to its own page. The forms that lead out say so
           themselves (target="_top", includes/cloud_content.php) and never
           get here; this is the net under the ones nobody thought of. */
        ready.delete(key);
        frame.src = home.get(key) ?? AREAS[key].url;
        return;
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
    // A tool is let go by show() anyway; an area goes back to its own page.
    if (!isTool(key)) frame.contentWindow.location.replace(home.get(key) ?? AREAS[key].url);
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
    if (active && active !== key && isTool(active)) leaveTool(active);
    if (!frames.has(key)) {
        make(key, address ? new URL(address, areaUrl(key)).href : remembered(key) ?? AREAS[key].url);
    } else if (stale.has(key)) {
        freshen(key, address);
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
   starting at once would each be slower. First the areas that are old (a
   tool was used), then the ones not loaded yet.

   NOTHING BEHIND A TOOL: while the administration is in sight, whatever
   was loaded now would be old the moment one leaves it. And a tool itself
   is never loaded ahead. */
let resting = null;
function loadTheRest() {
    clearTimeout(resting);
    resting = setTimeout(() => {
        if (!ready.has(active) || isTool(active)) return;
        const old = [...stale].find((key) => frames.has(key) && key !== active);
        if (old) {
            freshen(old);
            return;
        }
        const next = KEYS.find((key) => !isTool(key) && !frames.has(key));
        if (next) make(next, remembered(next) ?? AREAS[next].url);
    }, 1200);
}

window.addEventListener('popstate', () => {
    const key = location.hash.slice(1);
    if (AREAS[key]) show(key, null, false);
});
window.addEventListener('pagehide', () => { for (const key of frames.keys()) remember(key); });

/** Is this a desk — the question includes/shell.php explains, asked with its words. */
function atDesk() {
    try {
        if (sessionStorage.getItem('shell.off')) return false;
    } catch { /* no store: the media query decides alone */ }
    return !window.yasaimapp && matchMedia(box.dataset.desk).matches;
}

{
    const asked = new URLSearchParams(location.search);
    let key = location.hash.slice(1);
    let address = null;
    if (AREAS[asked.get('area')]) {
        // Sent on by the server: the area's own page, with the query it was
        // asked with and the # the browser carried over the redirect.
        key = asked.get('area');
        const url = areaUrl(key);
        url.search = asked.get('at') ?? '';
        url.hash = location.hash;
        address = url.pathname + url.search + url.hash;
    }
    if (!AREAS[key]) key = KEYS[0];
    if (atDesk()) {
        // '#map' alone would keep the query, and a reload would ask for that address again.
        if (location.search) history.replaceState(null, '', location.pathname + '#' + key);
        show(key, address, false);
    } else {
        const to = new URL(address ?? remembered(key) ?? AREAS[key].url, location.href);
        location.replace(location.pathname + '?leave=' + encodeURIComponent(to.pathname + to.search) + to.hash);
    }
}
