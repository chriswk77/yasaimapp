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

function full() {
    return !!document.fullscreenElement;
}

function sync() {
    const on = full();
    for (const b of document.querySelectorAll('[data-fullscreen]')) {
        b.hidden = !document.fullscreenEnabled;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', String(on));
        const word = on ? t('Vollbild beenden') : t('Vollbild (im Vollbild: ^ statt Esc)');
        b.title = word;
        b.setAttribute('aria-label', word);
    }
}

document.addEventListener('click', (e) => {
    if (!e.target.closest?.('[data-fullscreen]')) return;
    if (full()) document.exitFullscreen?.().catch(() => {});
    else document.documentElement.requestFullscreen?.().catch(() => {});
});
document.addEventListener('fullscreenchange', sync);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sync);
else sync();

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
