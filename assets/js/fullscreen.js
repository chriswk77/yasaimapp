/**
 * FULL SCREEN, AND „^" FOR ESCAPE INSIDE IT (20.11.2026: „die
 * Seite randlos, also ohne das Browserfenster außenrum, so ähnlich wie ein
 * Film im Vollbildmodus" — and, told that Escape ends it: „im Vollbildmodus
 * nur im Vollbildmodus eine Alternativtaste … für Abbrechen").
 *
 * THE BUTTON: every element with [data-fullscreen] (the map's bar and the
 * header of the pages with the app's buttons) switches the whole document
 * into full screen and out again. It wears is-active while full, and the
 * two drawings inside it are swapped by that class (app.css). A browser
 * that cannot do it (an iPhone's Safari) gets no button.
 *
 * ESCAPE BELONGS TO THE BROWSER IN FULL SCREEN: the first press ends it,
 * and no page can change that (only Chrome's Keyboard Lock could, Firefox
 * has none). Escape is the program's „one step back" everywhere — a measure
 * point, a tool, a menu, a window. So while full, the key right under it,
 * „^", does what Escape does: it sends the page an Escape, and where no part
 * of the page claims it, it asks the topmost window to close as the
 * browser's own Escape would (its cancel event first, so a window that
 * refuses — the photo window, the plates — stays). Outside full screen „^"
 * is an ordinary key, and in a field it always types its character.
 *
 * THE KEY BY ITS PLACE AND ITS NATURE: on a German keyboard „^" is a dead
 * key (e.key 'Dead'), and on a Mac's ISO keyboard the browsers report its
 * place as IntlBackslash, while the key left of Y („<", the overview in
 * shortcuts.js) comes as Backquote — the two are swapped. So: a dead key or
 * „^" at either of the two places. The overview leaves dead keys alone for
 * the same reason.
 *
 * FULL SCREEN OUTLIVES A CHANGE OF PAGE AS FAR AS A BROWSER LETS IT
 * (24.11.2026: „wenn ich beim Fund aus dem Vollbildmodus … auf Fundpunkt
 * klicke, also dass die Karte geladen wird, wird die Karte nicht im
 * Vollbildmodus geladen, sondern es wird umgeschaltet zur normalen
 * Ansicht"). Two rules of every browser stand against it and no page can
 * lift them: loading another page ENDS full screen, and a page may only
 * ASK for it inside a click or a key press of the user — never by itself
 * on loading. So the wish is carried over (sessionStorage, this tab only):
 * the page that loads next takes the FIRST click or key press, whatever
 * it is for, as the moment to go full again. One sees the browser's frame
 * for as long as one does not touch the page, and not longer.
 *
 * The wish ends when full screen is left on purpose — the button, Escape —
 * and it must NOT end when the browser ends full screen because the page
 * is going: that exit is told apart by the page's own farewell
 * (beforeunload, pagehide) just before it. Only the top document
 * remembers: a page in a frame (the key beside a find) loads this file
 * too, is never the full-screen document itself and would otherwise ask
 * in vain and forget the wish for everybody.
 *
 * Loaded on every page: page_footer() and a side-effect import in map.js.
 */

import { t } from './i18n.js';

/** The windows in the order they opened — the last is the one in front. */
const opened = [];
new MutationObserver((changes) => {
    for (const c of changes) {
        const d = c.target;
        if (!(d instanceof HTMLDialogElement)) continue;
        const at = opened.indexOf(d);
        if (at >= 0) opened.splice(at, 1);
        if (d.open) opened.push(d);
    }
}).observe(document.documentElement, { attributes: true, attributeFilter: ['open'], subtree: true });

/* THE DOCUMENT THAT GOES FULL. Usually this one. Inside the desk's frame
   page (pages/shell.php) it is the page AROUND: every area there is a frame
   with its own header and its own button, and a frame gone full by itself
   would cover the others — one change of area later the screen would show
   a hidden frame, that is, nothing. So the button in a frame switches the
   frame page, and reads its state from there. (A click in a frame counts
   for the pages around it too; the browser lets the request through.) */
const host = (() => {
    try {
        if (window.top !== window && window.top.document.getElementById('shell')) return window.top.document;
    } catch { /* a foreign page around us */ }
    return document;
})();

function full() {
    return !!host.fullscreenElement;
}

function sync() {
    const on = full();
    for (const b of document.querySelectorAll('[data-fullscreen]')) {
        b.hidden = !host.fullscreenEnabled;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', String(on));
        const word = on ? t('Vollbild beenden') : t('Vollbild (im Vollbild: ^ statt Esc)');
        b.title = word;
        b.setAttribute('aria-label', word);
    }
}

document.addEventListener('click', (e) => {
    if (!e.target.closest?.('[data-fullscreen]')) return;
    if (full()) host.exitFullscreen?.().catch(() => {});
    else host.documentElement.requestFullscreen?.().catch(() => {});
});
host.addEventListener('fullscreenchange', sync);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sync);
else sync();

// --- The wish outlives the page (the head says why) ----------------------------

const WISH = 'fullscreenWanted';
const wish = {
    get: () => { try { return sessionStorage.getItem(WISH) === '1'; } catch { return false; } },
    set: (on) => {
        try {
            if (on) sessionStorage.setItem(WISH, '1');
            else sessionStorage.removeItem(WISH);
        } catch { /* no storage: full screen ends with the page, as it always did */ }
    },
};
if (window.parent === window) {
    let leaving = 0;   // when the page last said farewell
    const farewell = () => { leaving = Date.now(); };
    window.addEventListener('beforeunload', farewell);
    window.addEventListener('pagehide', farewell);
    document.addEventListener('fullscreenchange', () => {
        if (full()) wish.set(true);
        /* Left on purpose — unless the page is going: a farewell a moment
           ago means the browser took full screen away, not the user. (A
           farewell that was called off — „Seite verlassen?" answered with
           no — is old by the time anybody presses Escape.) */
        else if (Date.now() - leaving > 1500) wish.set(false);
    });
    if (wish.get() && !full() && document.fullscreenEnabled) {
        /* The first click or key press on the new page. In the capture
           phase, so a part of the page that stops its events cannot keep
           it away; the click itself goes on to whatever it was meant for.
           Escape is no wish for full screen, and browsers do not count it
           as one either. */
        const again = (e) => {
            if (e.type === 'keydown' && (e.key === 'Escape' || e.metaKey || e.ctrlKey || e.altKey)) return;
            if (full() || !wish.get()) return done();
            // Refused (a key the browser does not count)? The next one asks again.
            document.documentElement.requestFullscreen?.().catch(() => {});
        };
        const done = () => {
            window.removeEventListener('click', again, true);
            window.removeEventListener('keydown', again, true);
            document.removeEventListener('fullscreenchange', done);
        };
        window.addEventListener('click', again, true);
        window.addEventListener('keydown', again, true);
        document.addEventListener('fullscreenchange', done);
    }
}

/** The key under Escape — „^", dead or not, at either of its two reported places. */
export function caretKey(e) {
    return (e.key === '^' || e.key === 'Dead') && (e.code === 'Backquote' || e.code === 'IntlBackslash')
        && !e.metaKey && !e.ctrlKey && !e.altKey;
}

function typing(target) {
    return !!(target?.isContentEditable || /^(TEXTAREA|SELECT)$/.test(target?.tagName ?? '')
        || (target?.tagName === 'INPUT' && !/^(range|checkbox|radio|button|submit|reset|color|file)$/.test(target.type)));
}

/* In the capture phase and before anybody else, like the overview's key:
   a window that stops its keys must not swallow this one. */
window.addEventListener('keydown', (e) => {
    if (!full() || !caretKey(e) || typing(e.target)) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    if (e.repeat) return;
    const escape = new KeyboardEvent('keydown', {
        key: 'Escape', code: 'Escape', keyCode: 27, bubbles: true, cancelable: true, composed: true,
    });
    if (!(e.target ?? document.body).dispatchEvent(escape)) return;   // a part of the page took it
    // The browser's own share: the window in front is asked to close.
    const front = opened.filter((d) => d.open && d.isConnected).pop();
    if (!front || !front.matches(':modal')) return;
    if (front.dispatchEvent(new Event('cancel', { cancelable: true }))) front.close();
}, true);
