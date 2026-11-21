/**
 * A save button is quiet until there is something to save.
 *
 * 25.09.2026, about Konto: „die beiden Buttons Speichern und
 * Passwort ändern leuchten wie ein Weihnachtsbaum … nur bei Änderungen
 * sollen sie in der aktiven Farbe markiert sein". The rule from CLAUDE.md —
 * loud only where there is something to do — for the one kind of button
 * whose „something to do" appears under the user's own fingers.
 *
 * HOW: a submit button with data-quiet-until-changed wears `subtle` while
 * its form stands as it was, drops it at the first real difference, and
 * wears it again when the difference is undone. ONLY THE COLOUR MOVES —
 * the button stays pressable; a disabled save would be a second decision
 * nobody asked for. The markup already carries `subtle`, so the button
 * does not flash in the accent before this module arrives.
 *
 * „AS IT WAS" HAS TWO MEANINGS. A form the server filled was as it was
 * when it arrived — the button compares with that. A WINDOW A SCRIPT FILLS
 * ON OPENING (the user window, Organisieren's windows; „mach
 * die Fenster auch") holds nothing worth comparing at load: its button
 * says data-quiet-until-changed="open" and takes its measure every time
 * the window opens. Every opener fills first and opens after, so the
 * measure sees the filled window; the `open` attribute is the moment.
 * A server-filled window keeps the first meaning on purpose: whoever
 * cancels with edits standing and opens again should still see them as
 * changes.
 *
 * WHAT COUNTS: every enabled control of the form, in document order —
 * named or not (Organisieren's windows go by id), a checkbox with its
 * third state (data-state where the box keeps one, else indeterminate), a
 * row that carries its key (data-key) with that key, so reordering the
 * columns counts too. A field marked
 * data-not-a-change stays out — the current password, which a password
 * manager may fill without anybody meaning to change a thing.
 *
 * WHEN: on 'input' and 'change', and after every click in the form — the
 * three-state boxes of the list window and the arrows of the column window
 * change things without either event. Measured AFTER the click has been
 * handled everywhere (setTimeout), since the handlers that move rows may
 * sit anywhere above the form.
 *
 * SELF-INSTALLING, like autoheight.js: the settings arrive as a fragment
 * after the page, so a MutationObserver binds what comes later. Included
 * once per page, through page_footer() in includes/page.php. Code that
 * sets a field from outside without any of those events dispatches
 * 'change' on the form, or the button never hears of it.
 */

/** The form's values as one string — equal strings, nothing to save. */
function state(form) {
    const parts = [];
    for (const field of form.elements) {
        if (field.disabled || 'notAChange' in field.dataset) continue;
        if (['submit', 'button', 'reset', 'image', 'fieldset', 'output'].includes(field.type)) continue;
        const key = field.name || field.id || field.closest('[data-key]')?.dataset.key || '';
        let value;
        if ('state' in field.dataset) {
            // A three-state box keeps its truth in data-state (Organisieren's
            // list window) and only mirrors it into checked afterwards, in a
            // microtask a real click partly undoes — read the truth.
            value = field.dataset.state;
        } else if (field.type === 'checkbox' || field.type === 'radio') {
            value = (field.checked ? '1' : '0') + (field.indeterminate ? '~' : '');
        } else if (field.type === 'select-multiple') {
            value = [...field.selectedOptions].map(o => o.value).join(',');
        } else if (field.type === 'file') {
            value = String(field.files.length);
        } else {
            value = field.value;
        }
        parts.push(key + '=' + value);
    }
    return parts.join('\u0000');
}

/** button -> { form, initial }; weak, so a fragment fetched anew lets its
    old buttons go. */
const watched = new WeakMap();

function update(button) {
    const w = watched.get(button);
    if (!w || !w.form) return;
    button.classList.toggle('subtle', state(w.form) === w.initial);
}

/** Take the measure anew — the window was just filled and opened. */
function rebase(button) {
    const w = watched.get(button);
    if (!w || !w.form) return;
    w.initial = state(w.form);
    update(button);
}

function bind(button) {
    if (watched.has(button)) return;
    const form = button.form;
    if (!form) return;
    watched.set(button, { form, initial: state(form) });
    const now = () => update(button);
    const later = () => setTimeout(now);
    form.addEventListener('input', now);
    form.addEventListener('change', now);
    form.addEventListener('click', later);
    // 'reset' fires BEFORE the fields go back — look once they have.
    form.addEventListener('reset', later);
    now();
}

/** Bind every marked button below an element. */
export function quietSaveAll(root = document) {
    if (root.matches?.('[data-quiet-until-changed]')) bind(root);
    for (const button of root.querySelectorAll?.('[data-quiet-until-changed]') ?? []) bind(button);
}

/** A window opened: its "open" buttons measure the filled state. */
function opened(dialog) {
    for (const button of dialog.querySelectorAll('[data-quiet-until-changed="open"]')) {
        bind(button);
        rebase(button);
    }
}

new MutationObserver((mutations) => {
    for (const m of mutations) {
        if (m.type === 'attributes') {
            if (m.target.tagName === 'DIALOG' && m.target.open) opened(m.target);
            continue;
        }
        for (const node of m.addedNodes) {
            if (node.nodeType === 1) quietSaveAll(node);
        }
    }
}).observe(document.documentElement, {
    childList: true, subtree: true, attributes: true, attributeFilter: ['open'],
});

quietSaveAll();
