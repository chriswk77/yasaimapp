/**
 * A file may be DRAGGED onto a drop area, not only picked in a dialog
 * (25.08.2026).
 *
 * THE CLICK NEEDS NO SCRIPT: the area is a <label> pointing at the file
 * input, and a label opens its input by itself — with the keyboard too,
 * and with a screen reader saying what it is for. What cannot be done in
 * markup is the dragging, and that is all this file does.
 *
 * IT ATTACHES ITSELF, like autoheight.js and toast.js: page_footer()
 * includes it once, and every .drop-area on the page works. Nothing to
 * call, nothing to forget at a call site.
 *
 * THE WHOLE PAGE HAS TO SWALLOW THE DEFAULT. A browser opens a file
 * dropped anywhere in the window — the import page would vanish and the
 * .zip would stand there as a download. So the document refuses what it
 * did not aim at; only the area itself takes one.
 */

import { t } from './i18n.js';

function areas() {
    return [...document.querySelectorAll('.drop-area')];
}

/** The input a drop area belongs to — the one its 'for' names. */
function inputOf(area) {
    return document.getElementById(area.getAttribute('for'));
}

/** What is lying there now — one name, or how many. */
function show(area, files) {
    const name = area.querySelector('.drop-area-file');
    if (!name) return;
    const list = [...(files ?? [])];
    name.textContent = list.length === 0 ? ''
        : (list.length === 1 ? list[0].name : t('%s Dateien: ', list.length)
            + list.map((f) => f.name).join(', '));
    name.hidden = list.length === 0;
}

document.addEventListener('dragover', (e) => {
    // Without this the browser navigates to the file on drop — and the
    // page with its unsaved state is gone.
    e.preventDefault();
    const area = e.target.closest?.('.drop-area');
    for (const other of areas()) other.classList.toggle('is-over', other === area);
});

document.addEventListener('dragleave', (e) => {
    // Only when the pointer really left the window: dragleave also fires
    // while moving from a child to its parent, and the area would then
    // flicker under the finger.
    if (e.relatedTarget) return;
    for (const area of areas()) area.classList.remove('is-over');
});

document.addEventListener('drop', (e) => {
    e.preventDefault();
    for (const area of areas()) area.classList.remove('is-over');

    const area = e.target.closest?.('.drop-area');
    const input = area ? inputOf(area) : null;
    const dropped = [...(e.dataTransfer?.files ?? [])];
    if (!input || dropped.length === 0) return;

    /* AS MANY AS THE INPUT ALLOWS — several where it says 'multiple'
       (the import takes a stack since 25.08.2026), the first one where
       it does not. Taking five into a single-file field would look like
       it worked and send one.

       THE FILES GO INTO THE INPUT, not into a variable of our own: the
       form submits classically, so what the server receives is whatever
       the input holds. DataTransfer is the only way to put a file there
       — an input's files list is read-only otherwise. */
    const taking = input.multiple ? dropped : dropped.slice(0, 1);
    const box = new DataTransfer();
    for (const file of taking) box.items.add(file);
    input.files = box.files;
    show(area, taking);
    // The form may be listening (validation, an enabled button).
    input.dispatchEvent(new Event('change', { bubbles: true }));
    send(area, input);
});

// The picked file's name, for the ordinary way through the dialog.
document.addEventListener('change', (e) => {
    const input = e.target;
    if (!input.classList?.contains('drop-area-input')) return;
    const area = areas().find((a) => inputOf(a) === input);
    if (area) show(area, input.files);
    if (area) send(area, input);
});

/**
 * Off it goes, without asking again (25.08.2026: „Beim
 * Drop-in von Dateien soll er sofort hochgeladen werden. Kein
 * Zwischenfragen").
 *
 * DROPPING IS ALREADY THE DECISION. One aimed at a marked area and let
 * go there; a second button below it asks the same question a second
 * time, and the answer is always yes. The same holds for the picker —
 * whoever chose a file in the dialog and pressed „Öffnen" has said it
 * too.
 *
 * WHAT REPLACES THE BUTTON is the button itself: it stays in the markup
 * as the way out for a browser without JS, and here it is put into the
 * state it would enter anyway — disabled, with a word that says
 * something is under way. Nothing has to switch it back; the answer is
 * a whole new page.
 *
 * MARKED WITH data-send-on-drop, not done for every area: an area
 * elsewhere may well want to be looked at before it is sent.
 */
function send(area, input) {
    if (area.dataset.sendOnDrop === undefined) return;
    const form = input.form;
    if (!form || input.files.length === 0) return;

    const button = form.querySelector('button[type="submit"]:not([form])');
    if (button) {
        button.disabled = true;
        button.textContent = t('lädt hoch …');
    }
    // requestSubmit, not submit(): it runs the form's own validation and
    // fires the submit event, so a listener on the form still sees it.
    // Not every browser has it — then the plain way.
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.submit();
}
