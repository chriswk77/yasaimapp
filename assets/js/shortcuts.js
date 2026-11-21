/**
 * THE KEYS OF THE PAGE, and the one key that lists them
 * (11.11.2026).
 *
 * THE RULES HE SET, for every shortcut in the program:
 *   - the LEFT hand: the right one holds the mouse, so what is used often
 *     lies in the left lower corner of the keyboard (Y, X, C, V, B, A, S …,
 *     the digits 1–5, Space, the modifiers) — „nur Sachen, die man oft
 *     braucht"; a rarer key may carry its word's letter (H for hide, O for
 *     the original);
 *   - SINGLE PRESSES: „Shortcuts mit 2 Tasten finde ich nicht praktisch,
 *     da muss man immer auf die Tastatur gucken". A modifier held while the
 *     wheel turns is no such pair — the hand does not look for it;
 *   - ONE MEANING ACROSS THE PAGES:
 *       Y              the picture fitted into its frame, everywhere
 *       Space held     moving the picture
 *       Shift + wheel  the tool's size
 *       Cmd + wheel    the tool's soft edge
 *       Option held    the partner tool for as long as it is held
 *                      (the brush becomes the eraser)
 *       X              the partner tool for good
 *       B / E / G      brush, eraser, fill — Photoshop's letters, which he
 *                      knows
 *       H              hide the drawings (lines, frames, masks) and back
 *       O held         the original, untouched
 *       1–5, Q W R T A the tools of the mode one is in — measuring tools in
 *                      „Messen", kinds of mending in „Ausbessern" …; a digit
 *                      means what the panel in front of one offers
 *       <              this overview — the key left of Y, under the hand
 *
 * WHO KNOWS WHICH KEYS COUNT: each part of the page that has keys says so
 * itself (shortcutsProvide) — the photo window, the layers, the plates,
 * the map — and says it only while it is in front, so the overview shows
 * what works HERE, not a manual of everything.
 */

import { t } from './i18n.js';

const providers = [];

/**
 * A part of the page names its keys: `fn()` answers, while that part is in
 * front, a list of sections — { title, rows: [[keys, what], …] } — and
 * otherwise null or [].
 */
export function shortcutsProvide(fn) {
    providers.push(fn);
}

/**
 * Is the key for a field? A field that takes letters keeps them: a „y" in a
 * species name is a letter. A slider is a field too, and it keeps the focus
 * after it was dragged — there the keys must work, or they would fail
 * exactly where one has just been working.
 */
export function typingIn(target) {
    return !!(target?.isContentEditable || /^(TEXTAREA|SELECT)$/.test(target?.tagName ?? '')
        || (target?.tagName === 'INPUT' && !/^(range|checkbox|radio|button|submit|reset|color|file)$/.test(target.type)));
}

/* --- THE WHEEL THAT SIZES: TRACKPAD AND MOUSE APART (11.11.2026) ---
   Shift + wheel sizes a brush, Cmd + wheel softens its edge. He wanted
   the TRACKPAD turned round — two fingers pushed away make it larger —
   „auf der Maus nicht umdrehen, nur auf dem Trackpad", and the trackpad
   slower: „die Größe ändert sich sehr schnell".

   WHICH ONE TURNED. No browser says it outright; the traces:
     - FIREFOX reports a mouse wheel in LINES (deltaMode 1) to a page that
       asks for the unit before the amount, a trackpad always in pixels. So
       the unit is read FIRST — and only for a turn that is handled here: a
       Firefox event read that way stays in lines for every listener after
       it, and the picture's zoom would crawl.
     - CHROME AND SAFARI keep a trackpad's old wheelDelta at exactly −3 ×
       the pixels; a mouse notch does not (measured in the app's own
       Chromium: deltaY 200, wheelDeltaY −120).
   On a Mac with „natürliches Scrollen" fingers pushed away arrive as a
   positive deltaY, a wheel rolled away as one too — so the two get
   opposite signs here: the mouse kept its way (rolled towards oneself is
   larger), the trackpad its own.

   SLOWER ON THE TRACKPAD: it sends dozens of small events per stroke, and
   each used to be a whole step. Now its pixels are gathered — at most
   WHEEL_PAD_CAP of one event count, so a quick flick does not race — and
   every WHEEL_PAD_PX make one step: two to six times slower than the raw
   stream, as he asked on 11.11.2026. (Doubled for an hour on 13.11.2026
   by a misreading and set back: what he wanted quicker was the MOUSE
   wheel with Shift — „du kannst doch nicht einfach das Trackpad schneller
   machen".) Only Shift and Ctrl with the wheel come here; the pictures'
   zoom — wheel, two fingers, pinch — never does. */
const WHEEL_PAD_PX = 16, WHEEL_PAD_CAP = 8;
let padRest = 0, padLast = 0;

/* CTRL WITH THE WHEEL SETS THE SOFT EDGE, AND ONLY THE REAL KEY DOES
   (13.11.2026: „Command-Mausrad habe ich leider beim Mac schon
   belegt für den ganzen Bildschirm ranzoomen … ob wir die Steuerungstaste
   … statt der Command-Taste nehmen für eine weiche Kante"). The browser
   hands a trackpad's pinch over as a wheel with ctrlKey set — the very
   gesture the pictures zoom by (imagezoom.js, blend.js). So the key itself
   is watched: ctrlKey on a wheel counts as Ctrl only while Control is
   really down; otherwise it is the pinch, and zooms as before. */
let controlDown = false;
if (typeof window !== 'undefined') {
    window.addEventListener('keydown', (e) => { if (e.key === 'Control') controlDown = true; }, true);
    window.addEventListener('keyup', (e) => { if (e.key === 'Control') controlDown = false; }, true);
    window.addEventListener('blur', () => { controlDown = false; });
}
/** A wheel with the Control key really held — not a trackpad's pinch, which carries ctrlKey too. */
export function wheelWithCtrl(e) {
    return !!e.ctrlKey && controlDown;
}

/**
 * The step a turn of the wheel gives a size or a soft edge: positive is
 * more. Ask it BEFORE anything reads e.deltaY (Firefox, above). 0 where a
 * trackpad has not travelled far enough yet.
 */
export function wheelStep(e) {
    const mode = e.deltaMode;
    const raw = e.deltaY || e.deltaX;
    if (!raw) return 0;
    let pad;
    if (mode !== 0) pad = false;
    else if (/Firefox\//.test(navigator.userAgent)) pad = true;
    else {
        const old = e.deltaY ? e.wheelDeltaY : e.wheelDeltaX;
        pad = typeof old === 'number' && old !== 0 ? old === -3 * raw : !Number.isInteger(raw);
    }
    if (!pad) {
        /* THE MOUSE TWICE AS QUICK (13.11.2026: „wenn ich … Shift
           drücke und das Mausrad dabei drehe, ändert sich die Stiftgröße zu
           langsam … das musst du schneller machen"): a notch of 100 px six
           steps instead of three, a fast turn ten at most instead of six. */
        const px = raw * (mode === 1 ? 16 : mode === 2 ? 400 : 1);
        return -Math.sign(px) * Math.max(1, Math.min(10, Math.abs(px) * .06));
    }
    const now = performance.now();
    // A pause or a turn of direction starts the gathering anew.
    if (now - padLast > 400 || (padRest && Math.sign(padRest) !== Math.sign(raw))) padRest = 0;
    padLast = now;
    padRest += Math.sign(raw) * Math.min(WHEEL_PAD_CAP, Math.abs(raw));
    const step = Math.trunc(padRest / WHEEL_PAD_PX);
    padRest -= step * WHEEL_PAD_PX;
    return step;
}

let box = null;

function boxGet() {
    if (box) return box;
    box = document.createElement('dialog');
    box.className = 'dialog shortcuts-window';
    box.innerHTML = `<h2 class="dialog-title"></h2><div class="shortcuts-body"></div>
        <div class="dialog-footer"><button type="button" class="button subtle"></button></div>`;
    box.querySelector('.dialog-title').textContent = t('Tasten');
    const close = box.querySelector('.dialog-footer .button');
    close.textContent = t('Schließen');
    close.addEventListener('click', () => box.close());
    // A click on the backdrop closes, as every window does.
    box.addEventListener('click', (e) => { if (e.target === box) box.close(); });
    document.body.append(box);
    return box;
}

function overviewShow() {
    const win = boxGet();
    const body = win.querySelector('.shortcuts-body');
    body.textContent = '';
    const sections = providers.flatMap((fn) => {
        try {
            return fn() ?? [];
        } catch {
            return [];
        }
    });
    sections.push({ title: t('Überall'), rows: [['<', t('diese Übersicht')], ['Esc', t('Werkzeug beenden, zurück')],
        ['^', t('im Vollbild statt Esc')]] });
    for (const s of sections) {
        if (!s?.rows?.length) continue;
        body.append(Object.assign(document.createElement('h3'), { className: 'list-label', textContent: s.title }));
        const list = document.createElement('dl');
        list.className = 'shortcuts-list';
        for (const [keys, what] of s.rows) {
            const dt = document.createElement('dt');
            for (const [i, k] of String(keys).split(' + ').entries()) {
                if (i) dt.append(' + ');
                dt.append(Object.assign(document.createElement('kbd'), { textContent: k }));
            }
            list.append(dt, Object.assign(document.createElement('dd'), { textContent: what }));
        }
        body.append(list);
    }
    // Over whatever is open — a picture shown in the page or in its own window.
    if (!win.open) win.showModal();
}

/* „<" — the key left of Y on a German keyboard (IntlBackslash: the same
   place whatever the layout prints on it). In the capture phase and before
   anybody else, so a window that stops its keys does not swallow this one;
   pressed again, it closes. */
window.addEventListener('keydown', (e) => {
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    /* NOT A DEAD KEY: on a Mac's ISO keyboard the browsers report „^" (the
       key under Escape) as IntlBackslash — the two places are swapped — and
       „^" is full screen's Escape (fullscreen.js), never this overview. */
    if (e.key === 'Dead' || e.key === '^') return;
    if (e.key !== '<' && e.key !== '>' && e.code !== 'IntlBackslash') return;
    if (typingIn(e.target)) return;
    e.preventDefault();
    e.stopPropagation();
    if (e.repeat) return;
    if (box?.open) box.close();
    else overviewShow();
}, true);
