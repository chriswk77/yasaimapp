/**
 * Multi-line text fields grow with their content.
 *
 * WHAT FOR: A note of ten lines sat in a box of three, and the remaining
 * seven were only found by scrolling — in a field that itself already sits
 * in a scrolling window (see the fourth trap in CLAUDE.md: no scroll area
 * inside a scroll area). Whoever wants to reread something they noted down
 * themselves should see it without searching inside the field.
 *
 * SELF-INSTALLING, and deliberately so. Today there are three places with
 * a <textarea> — one in the markup, two built at runtime (the multi-line
 * extra fields of the map and the field form in Organisieren) — and there will
 * be more. If every place had to register itself, the fourth would be the
 * first to forget it, and nobody would notice why the field fails to grow
 * exactly there. So this module binds ALL existing fields on load and
 * watches the document for new ones.
 *
 * Including it: once per page. For the map via the side-effect import in
 * map.js, for everything else via fuss() in includes/page.php — the same
 * two paths that dialogs.js takes.
 *
 * THREE OCCASIONS TO REMEASURE, and all three are needed:
 *
 *   TYPING     the 'input' event, the obvious case. It also covers pasting,
 *              cutting and undo.
 *   WIDTH      a ResizeObserver. When the width changes, the text wraps
 *              differently and the line count is a different one — when
 *              rotating the phone, when widening the window, and above all
 *              on BECOMING VISIBLE: A hidden field measures 0, and whatever
 *              you compute from it is nonsense. The jump from 0 to its real
 *              width is precisely the moment it becomes measurable for the
 *              first time — AND IT COUNTS EVEN WHEN THE WIDTH IS THE SAME
 *              AS BEFORE (see the owed measurement at measure()).
 *   ASSIGNMENT setting .value from code. It fires NO event — and that is
 *              the most common case of all: The detail window fills its
 *              note that way on every point change. Without this third
 *              occasion, a short text would keep the height of the long
 *              one that was open before.
 */

/** Bring to content height. */
function measure(field) {
    // LET IT COLLAPSE FIRST. scrollHeight is never smaller than the field's
    // current height — without this step it could only grow and never
    // shrink again, and whoever deletes a long note would keep the long
    // field.
    field.style.height = 'auto';

    /* Invisible (window closed, tab behind it, [hidden]) everything measures
       0. Leave the height alone and wait for the ResizeObserver, which comes
       by itself on becoming visible.

       AND WRITE DOWN THAT A MEASUREMENT IS OWED (11.09.2026:
       „bei Fremdfunden dehnt sich das Notizfeld nicht aus"). Without the
       mark the observer may skip that very moment, because it only
       remeasures on a CHANGED width — and becoming visible does not have
       to change one. The detail window fills its fields BEFORE it shows
       itself (selectPoint: fillDetail, then detailVisible), so on every
       opening after the first the note arrives here invisible, loses its
       height to the line above, and would then be shown at the width it
       already had. On a foreign find that is where it stays: nobody types
       in a locked field, and typing is what otherwise repairs the height
       unnoticed. Desktop browsers hid the fault behind their scrollbar —
       it appears with the grown text and takes its 21 pixels off the
       width, so the width DID change and the observer measured after all.
       Where scrollbars lie over the content — the phone, and that is
       where the maintainer saw it — nothing changes and nothing gets
       measured. */
    if (field.scrollHeight === 0) {
        field.style.removeProperty('height');
        field.dataset.autoheightOwed = 'yes';
        return;
    }
    delete field.dataset.autoheightOwed;

    // box-sizing: border-box applies to the whole project (app.css), so
    // height means including padding AND border. scrollHeight only knows the
    // padding — the difference between offsetHeight and clientHeight is the
    // border and must be added, otherwise each field is short a pixel pair
    // and the text scrolls by two pixels after all.
    const border = field.offsetHeight - field.clientHeight;
    field.style.height = (field.scrollHeight + border) + 'px';
}

/**
 * The observer for the width.
 *
 * REMEASURE ONLY ON A REAL WIDTH CHANGE, otherwise it goes in circles:
 * measure() sets the height, the observer reports the height change, and it
 * measures again. The width does not change from that — comparing with the
 * last seen one breaks the circle without needing a timer or a lock
 * anywhere.
 *
 * WITH ONE EXCEPTION, AND IT IS NO LEAK IN THE CIRCLE: a measurement owed
 * from a moment of invisibility. It can only come from measure()'s early
 * exit, which sets no height at all, so nothing it triggers can report a
 * change — and the measurement it lets through clears the mark.
 */
const widthWatch = new ResizeObserver((entries) => {
    for (const e of entries) {
        const width = Math.round(e.contentRect.width);
        if (width === 0) continue;                     // invisible, will be back
        // The owed measurement beats the comparison: same width, but the
        // height standing there was never computed from this content.
        if (e.target.dataset.autoheightWidth === String(width)
            && e.target.dataset.autoheightOwed !== 'yes') continue;
        e.target.dataset.autoheightWidth = String(width);
        measure(e.target);
    }
});

/** Bind one field. Calling it more than once does no harm. */
export function autoHeight(field) {
    if (!(field instanceof HTMLTextAreaElement)) return;
    if (field.dataset.autoheight === 'yes') return;
    field.dataset.autoheight = 'yes';

    // Without this, a scrollbar would appear for the blink of an eye when
    // the text exceeds the old height — and on some systems it would stay,
    // because it changes the width and with it the wrapping.
    field.style.overflowY = 'hidden';

    field.addEventListener('input', () => measure(field));
    widthWatch.observe(field);
    measure(field);
}

/** Bind all multi-line fields below an element. */
export function autoHeightAll(root = document) {
    if (root instanceof HTMLTextAreaElement) autoHeight(root);
    for (const field of root.querySelectorAll?.('textarea') ?? []) autoHeight(field);
}

/*
 * INTERCEPT THE SETTING OF .value — the third occasion from above.
 *
 * On the prototype and not per field: An assignment goes through the class's
 * setter no matter on which field it happens, and a separate setter per
 * element would be the same work times the number of fields. The original
 * setter is called as it always was; all that is added is the remeasuring,
 * and that only on fields bound here. For all others the assignment is what
 * it was.
 *
 * The same construction as the intervention in L.TileLayer.prototype.onAdd
 * in layers.js: in one place, named and justified.
 */
const nativeValue = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value');
if (nativeValue?.set) {
    Object.defineProperty(HTMLTextAreaElement.prototype, 'value', {
        ...nativeValue,
        set(value) {
            nativeValue.set.call(this, value);
            if (this.dataset.autoheight === 'yes') measure(this);
        },
    });
}

/*
 * WHAT ARRIVES LATER. The multi-line extra fields of the map are recreated
 * on every point change, the field form of Organisieren on every opening — they
 * are not there at all when this module loads.
 *
 * subtree: true, because they sit deep inside windows and list rows;
 * childList alone suffices, since a <textarea> is always inserted as an
 * element, never converted from an existing one.
 */
new MutationObserver((mutations) => {
    for (const a of mutations) {
        for (const node of a.addedNodes) {
            if (node.nodeType === 1) autoHeightAll(node);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

autoHeightAll();
