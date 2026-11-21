/**
 * Floating short messages for the pages outside the map.
 *
 * WHY THIS FILE EXISTS. A confirmation used to be a block in the document
 * flow: „Gespeichert." appeared above the content, pushed everything down
 * by its height and stayed until the next page load. Two things wrong at
 * once — the page jumped under the reading eye, and a message about a
 * finished action sat there for minutes as if something still needed
 * doing (22.08.2026: "Eine Toast-Anzeige ist immer ein
 * schwebendes Element über dem Text und verschwindet dann").
 *
 * WHAT FLOATS AND WHAT DOES NOT — and the line does NOT run along the
 * kind of message. It runs along the question the message answers.
 *
 * Everything that answers "how did my click go" floats: „Gespeichert.",
 * „Zwei Punkte verschoben.", and equally „Diese Gruppe gibt es nicht
 * mehr." That last one is an error and still belongs in the corner — the
 * deed is over, the answer is read in passing. The first cut of this file
 * sorted by kind and left exactly that error hanging in the page;
 * the maintainer found it there (22.08.2026).
 *
 * Everything that describes a STATE stays in the flow: „Freunde brauchen
 * die Migration 0041 — sie ist noch nicht eingespielt." It has to stand
 * until somebody applies it, because it is the reason a whole tab looks
 * empty, and a message one has to catch before it fades is no help there.
 *
 * The server says which is which: notice(..., float: true) marks a block
 * as an answer to a deed, and every *_process() result is marked.
 *
 * The map has its own bar (status() in map.js) and keeps it: it sits over
 * the map, has to dodge the sidebar and knows where a message came from.
 * Two mechanisms for two surfaces — one of them would have to know about
 * both, and that is how a single line ends up with two meanings.
 */

const DURATIONS = { success: 3200, hint: 4500, error: 6000, warning: 6000 };

/* A LONG SENTENCE STAYS LONGER (04.10.2026). The times above
   are the FLOOR per kind and fit „Gespeichert."; a result with numbers in
   it was gone before it was read — „Aufgeräumt: 16 Messwert-Dateien ohne
   Bild und … leere Ordner entfernt." stood 3.2 seconds, and the second
   number was lost. So a message stands at least as long as reading it
   takes: a moment to find it, then 70 ms a character — about fourteen
   characters a second, slow on purpose, because many who use this are
   older and not quick on a screen. That sentence gets about 7 seconds.
   The ceiling keeps a long error list from covering the page's foot for
   half a minute; a tap still closes every message at once. */
const READ_START = 1500;
const READ_PER_CHAR = 70;
const READ_MAX = 15000;

function duration(text, kind) {
    const floor = DURATIONS[kind] ?? DURATIONS.success;
    return Math.max(floor, Math.min(READ_MAX, READ_START + text.length * READ_PER_CHAR));
}

let area = null;

function toastArea() {
    if (area?.isConnected) return area;

    area = document.createElement('div');
    area.className = 'toast-area';
    /* POLITE, not assertive: a confirmation must not cut into what a
       screen reader is currently saying. The region stands empty from the
       start — announced is only what is put INTO an existing live region;
       a region that arrives together with its text often stays silent. */
    area.setAttribute('aria-live', 'polite');
    document.body.append(area);
    return area;
}

/**
 * Show a floating message. Kind: 'success' (the rule), 'hint', 'error'.
 *
 * @param {string} text
 * @param {string} [kind]
 * @returns {HTMLElement} the message, in case a caller wants to remove it early
 */
export function toast(text, kind = 'success') {
    const el = document.createElement('div');
    el.className = 'toast toast-' + kind;
    el.textContent = text;

    /* DISMISSABLE BY TAP. It covers the lower edge of the page, and on a
       phone that is where the thumb lives — whoever wants to get on with
       it should not have to wait out the timer. */
    el.addEventListener('click', () => remove(el));

    const box = toastArea();
    box.append(el);

    /* THREE AT MOST, oldest first. A stack of messages growing up the
       screen hides exactly the content one is working on, and nobody
       reads the fourth confirmation anyway. */
    while (box.children.length > 3) remove(box.firstElementChild, true);

    setTimeout(() => remove(el), duration(text, kind));
    return el;
}

/** Fade out and take out. Immediately when something else needs the room. */
function remove(el, atOnce = false) {
    if (!el?.isConnected) return;
    if (atOnce) { el.remove(); return; }

    el.classList.add('is-going');
    // Just longer than the animation in app.css — and it comes off even
    // if no animation runs at all (reduced motion, background tab).
    setTimeout(() => el.remove(), 300);
}

/* THE SERVER-SIDE ANSWERS FLOAT TOO — they are the ones the maintainer was
   seeing hang in the page. Marked with data-toast by notice(), so the
   decision is made where the message is written and not guessed from its
   colour here.

   A block can carry several sentences (a list of errors); each becomes
   its own message, because three sentences in one box are read as one
   and dismissed as one.

   AT LOAD AND WHENEVER A TAB ARRIVES (25.08.2026). It used to run once
   and the note here said that was enough, because the page does not
   reload afterwards. Since the administration fetches its tabs on
   demand that is no longer true: a fragment can bring a server-rendered
   answer with it — the import's „3 Punkte angelegt" travels through the
   session across its redirect and is written by the fragment, not by
   the page. Unlifted it would stand in the flow as a block, which is
   exactly what this file exists to prevent. */
export function liftNotices(root = document) {
    for (const block of root.querySelectorAll('.notice[data-toast]')) {
        const kind = ['error', 'success', 'warning', 'hint']
            .find(k => block.classList.contains('notice-' + k)) ?? 'success';

        const items = block.querySelectorAll('li');
        const texts = items.length > 0
            ? [...items].map(li => li.textContent.trim())
            : [block.textContent.trim()];

        block.remove();
        for (const text of texts) if (text !== '') toast(text, kind);
    }
}

liftNotices();
