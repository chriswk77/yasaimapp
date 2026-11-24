/**
 * A PAGE INSIDE THE DESK'S FRAME PAGE (pages/shell.php, assets/js/shell.js
 * — they say what that page is and why). Loaded on every page; outside the
 * frame page nothing here does anything.
 *
 * What a page gets from it:
 *
 *   inShell()                 am I shown inside the frame page
 *   shellOpen(area, address)  show another area there — the map's pencil
 *                             opens a find, the find's „Zeigen" the map —
 *                             instead of leaving this page; false when
 *                             there is no frame page around, and the caller
 *                             goes there by its address as it always did
 *   onShell('go', fn)         the frame page leads THIS area to an address
 *                             (fn gets the query and hash); a page that
 *                             does not listen is simply loaded there
 *   onShell('shown', fn)      this area came into sight again — the moment
 *                             to ask the server what changed meanwhile
 *
 * AND THE LINKS, WITHOUT ANY PAGE'S DOING. Every area keeps its own header
 * with the same buttons as when it is a page of its own; they are ordinary
 * links. A click on a link that leads to ANOTHER AREA is handed up as
 * shellOpen — that is what makes the header's buttons change the view
 * instead of the page. A link to a page that is no area (the
 * administration, the help) takes the whole window, and so does a form
 * posted to one (the door). Links within the page itself, new tabs and
 * downloads are left alone, and so is a click somebody already answered.
 *
 * A PAGE IN A FRAME OF A FRAME counts too — the key beside a find stands in
 * the Fundbrowser's frame, which stands in the frame page: its links to a
 * find change the Fundbrowser's area, they do not load a page into the
 * key's frame. The 'go' and 'shown' words, though, are only for the area's
 * own page, the frame page's direct child.
 */

const TYPE = 'yasaimapp-shell';

/** The frame page's window, when this page stands in it — at any depth. */
function shell() {
    try {
        const top = window.top;
        return top !== window && top.document.getElementById('shell') ? top : null;
    } catch {
        return null;   // a foreign page around us
    }
}

export function inShell() {
    return shell() !== null;
}

export function shellOpen(area, address = '') {
    const page = shell();
    if (!page) return false;
    page.postMessage({ type: TYPE, event: 'open', area, address }, location.origin);
    return true;
}

const listeners = { go: null, shown: [] };
export function onShell(event, fn) {
    if (event === 'go') listeners.go = fn;
    else listeners[event]?.push(fn);
}

const plain = (pathname) => pathname.replace(/index\.php$/, '');

if (inShell()) {
    const page = shell();
    const AREAS = JSON.parse(page.document.getElementById('shell').dataset.areas);
    const areaOf = (url) => Object.keys(AREAS)
        .find((key) => plain(new URL(AREAS[key].url, location.href).pathname) === plain(url.pathname)) ?? null;
    const here = (url) => plain(url.pathname) === plain(location.pathname);

    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const link = e.target.closest?.('a[href]');
        if (!link || link.hasAttribute('download') || link.target === '_blank') return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
        const area = areaOf(url);
        /* Within this page: the browser's own business — unless this page
           is not an area's own (the key in the Fundbrowser's frame) and the
           link means the area around it. */
        if (here(url) && (area === null || window.parent === page)) return;
        e.preventDefault();
        if (area !== null) shellOpen(area, url.search + url.hash);
        else page.location.href = url.href;
    });

    // A form posted to a page that is no area (the door) takes the window along.
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.method === 'dialog') return;
        const url = new URL(form.action || location.href, location.href);
        if (!here(url) && areaOf(url) === null) form.target = '_top';
    }, true);

    if (window.parent === page) {
        window.addEventListener('message', (e) => {
            if (e.origin !== location.origin || e.source !== page || e.data?.type !== TYPE) return;
            const d = e.data;
            if (d.event === 'go') {
                if (listeners.go) listeners.go(String(d.address ?? ''));
                else location.assign(d.href);
            } else if (d.event === 'shown') {
                for (const fn of listeners.shown) fn();
            }
        });
        /* „I have run": from here on the frame page may speak to this page
           instead of loading it anew. On load — every module of the page
           has run by then, and with it every onShell() above. */
        const say = () => page.postMessage({ type: TYPE, event: 'ready' }, location.origin);
        if (document.readyState === 'complete') say();
        else window.addEventListener('load', say);
    }
}
