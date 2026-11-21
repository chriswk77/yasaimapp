/**
 * An X in every text field, as soon as something is in it
 * (22.08.2026) — one click empties the field again.
 *
 * SELF-ATTACHING, like autoheight.js for the text areas: it binds every
 * text field on load and watches the document for new ones, so no call
 * site has to register and none can forget. Included once per page — via
 * page_footer() and via a side-effect import in map.js, the same two
 * paths as dialogs.js.
 *
 * ONLY FREE-TEXT TYPES. A date field brings its own calendar innards, a
 * number field its spinner — an X on top of either would sit on foreign
 * controls. Their emptying stays with those controls.
 *
 * THE SHELL AROUND THE FIELD is the price of the X: nothing can be
 * positioned INTO an input, so the field moves into a span that is
 * exactly as big as it. The shell carries the layout roles the field had
 * (block width, flex stretching) — see .clear-shell in app.css. Fields
 * that already sit in a .field-with-x wire themselves (the map's sidebar
 * search does) and are left alone.
 *
 * THE CLICK REPORTS AS INPUT: whoever clears the column search of the
 * Organisieren table expects the table to widen again — so the X dispatches the
 * same event typing would, and every listener reacts as if the user had
 * emptied the field by hand.
 */

import { iconHtml } from './icons.js';
import { t } from './i18n.js';

const TYPES = ['text', 'search', 'email', 'url', 'tel'];

function bind(input) {
    /* NOT ON A READ-ONLY FIELD (12.09.2026). The invitation window shows
       code and link as read-only fields with a copy button beside them,
       and each grew a cross that could not clear anything — a control
       that does nothing where it stands. What cannot be typed into has
       nothing to empty. */
    if (input.readOnly) return;
    /* NOT WHERE THE FIELD SAYS SO (data-no-clear, 05.10.2026). The photo
       window's text tool types through an invisible field lying over the
       picture (photo_window.php, #photo-text-input); wrapped, its shell
       stood in the picture frame's flex row as a block of its own and
       pushed every picture to the left edge — the maintainer saw the viewer
       stop centring. A field nobody sees has nothing to empty. */
    if (input.hasAttribute('data-no-clear')) return;
    // Bespoke shells (the map's sidebar search) wire themselves; a second
    // X in the same shell would sit on top of theirs. The check also ends
    // the recursion: our own wrapping re-triggers the observer, and the
    // wrapped field now has a shell.
    if (input.closest('.field-with-x')) return;

    /* MOVING A FIELD TAKES ITS FOCUS AWAY — and this function moves every
       field it touches. On a form that is built once, nobody notices:
       the wrapping happens before anyone types. In the map's sidebar the
       filter rows are REBUILT ON EVERY KEYSTROKE, so every letter
       produced a fresh <input>, the observer wrapped it, and the caret
       was gone. drawTermRows even restores the focus by hand — and it
       ran first, before this. „Nach jedem Buchstaben verliert das
       Inputfeld den Fokus" (25.08.2026).

       So: whoever was in the field before the move is put back into it
       afterwards, at the same place in the text. selectionStart is
       guarded because not every input type has one (number, date) — for
       those the plain focus is right. */
    const hadFocus = document.activeElement === input;
    let from = null;
    let to = null;
    if (hadFocus) {
        try { from = input.selectionStart; to = input.selectionEnd; } catch { /* no caret */ }
    }

    const shell = document.createElement('span');
    shell.className = 'field-with-x clear-shell';
    input.before(shell);
    shell.append(input);

    if (hadFocus) {
        input.focus();
        if (from !== null) {
            try { input.setSelectionRange(from, to); } catch { /* no caret */ }
        }
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'field-clear';
    button.title = t('Feld leeren');
    button.setAttribute('aria-label', t('Feld leeren'));
    button.innerHTML = iconHtml('close');

    const show = () => { button.hidden = input.value === ''; };
    show();
    input.addEventListener('input', show);
    /* CODE FILLS AND EMPTIES FIELDS BY ASSIGNMENT — dialog prefills,
       form clearing after a submit — and an assignment fires no event at
       all. So the instance gets its own value setter that reports to the
       X; the prototype's setter keeps doing the real work. Without this,
       an emptied form kept its X standing over nothing (the friend
       search showed it first). form.reset() bypasses even the setter,
       hence the extra listener; the microtask lets the reset finish
       before the check reads the value. */
    const proto = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    Object.defineProperty(input, 'value', {
        get() { return proto.get.call(this); },
        set(v) { proto.set.call(this, v); show(); },
    });
    input.form?.addEventListener('reset', () => queueMicrotask(show));

    button.addEventListener('click', () => {
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
    });
    shell.append(button);
}

function bindAll(root) {
    for (const input of root.querySelectorAll('input')) {
        if (TYPES.includes(input.type)) bind(input);
    }
}

bindAll(document);

new MutationObserver((records) => {
    for (const r of records) {
        for (const node of r.addedNodes) {
            if (node.nodeType !== 1) continue;
            if (node.matches?.('input')) {
                if (TYPES.includes(node.type)) bind(node);
            } else {
                bindAll(node);
            }
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });
