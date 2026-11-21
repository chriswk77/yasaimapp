/**
 * Our own dialogs instead of confirm(), alert() and prompt().
 *
 * The built-in ones are unusable for an application like this: they block
 * the whole window, cannot be styled, show the bare domain above the text on
 * the phone, and the button labels differ by browser — "OK" instead of
 * "Löschen", which for a delete confirmation is precisely the wrong answer.
 * They also contradict the rest of the interface, in which we put the main
 * function on the right and Cancel next to it.
 *
 * All of them return a Promise:
 *
 *   await confirm({...})  ->  true | false
 *   await prompt({...})     ->  text | null   (null = cancelled)
 *   await report({...})       ->  undefined
 *   await choose({...})      ->  value | null  (null = cancelled)
 *   await ask({...})         ->  value | null  (null = neither, stay)
 *   await popupMenu({...})   ->  value | null  (null = clicked elsewhere)
 *
 * No setup in the page: the window comes into being on the first call and
 * is reused afterwards.
 */

import { t } from './i18n.js';

let dialogWindow = null;

function getWindow() {
    if (dialogWindow) return dialogWindow;

    dialogWindow = document.createElement('dialog');
    dialogWindow.className = 'dialog';
    dialogWindow.innerHTML = `
        <form method="dialog">
            <h2 class="dialog-title"></h2>
            <p class="dialog-text"></p>
            <div class="field dialog-field" hidden>
                <label class="dialog-label" for="dialog-input"></label>
                <input type="text" id="dialog-input">
            </div>
            <div class="dialog-footer">
                <button type="button" class="button subtle" value="no">Abbrechen</button>
                <button type="submit" class="button" value="yes">OK</button>
            </div>
        </form>`;
    document.body.append(dialogWindow);
    return dialogWindow;
}

/**
 * Shared foundation.
 *
 * Escape and a click on the backdrop count as cancel — that is the familiar
 * behavior and must never delete by accident. So the completion only yields
 * "yes" when the right-hand button was really pressed.
 */
function show({ title, text, button = 'OK', cancel = t('Abbrechen'), danger = false,
                  value = null, placeholder = '', label = '', type = 'text',
                  autocomplete = 'off' }) {
    const f = getWindow();
    const form       = f.querySelector('form');
    const titleEl    = f.querySelector('.dialog-title');
    const textEl     = f.querySelector('.dialog-text');
    const field      = f.querySelector('.dialog-field');
    const fieldLabel = f.querySelector('.dialog-label');
    const fieldInput = f.querySelector('#dialog-input');
    const [noButton, yesButton] = f.querySelectorAll('.dialog-footer .button');

    titleEl.textContent = title ?? '';
    titleEl.hidden = !title;
    textEl.textContent = text ?? '';
    textEl.hidden = !text;

    yesButton.textContent = button;
    yesButton.classList.toggle('danger', danger);
    noButton.textContent = cancel;
    noButton.hidden = cancel === null;

    const withInput = value !== null;
    field.hidden = !withInput;
    fieldLabel.textContent = label;
    fieldLabel.hidden = label === '';
    // Set type and autocomplete anew on EVERY call, not only when needed —
    // the window is one single, reused element, otherwise a password field
    // from the last prompt would remain as the type.
    fieldInput.type = type;
    fieldInput.autocomplete = autocomplete;
    fieldInput.value = withInput ? String(value) : '';
    fieldInput.placeholder = placeholder;

    return new Promise((done) => {
        const finish = () => {
            f.removeEventListener('close', finish);
            noButton.removeEventListener('click', cancel);
            const confirmed = f.returnValue === 'yes';
            done(withInput ? (confirmed ? fieldInput.value : null) : confirmed);
        };
        const cancel = () => { f.returnValue = 'no'; f.close(); };

        f.addEventListener('close', finish);
        noButton.addEventListener('click', cancel);

        f.returnValue = 'no';
        // Close ourselves instead of letting method="dialog" do the work:
        // its return value comes from the submit button and stays empty
        // when the form is submitted without a button — from a script, say.
        form.onsubmit = (e) => {
            e.preventDefault();
            f.returnValue = 'yes';
            f.close();
        };

        f.showModal();
        (withInput ? fieldInput : yesButton).focus();
        if (withInput) fieldInput.select();
    });
}

/** Confirmation with yes/no. */
export function confirm(options) {
    return show({ ...options, value: null });
}

/**
 * Prompt with text input. Yields the text, or null on cancel — 'value' is
 * the field's prefill.
 */
export function prompt(options) {
    return show({ value: '', ...options });
}

/** Pure notice — only one button. */
export function report(options) {
    return show({ button: t('Verstanden'), ...options, cancel: null, value: null })
        .then(() => undefined);
}

/**
 * Decision between named paths — for questions where yes/no would lie,
 * because both answers DO something.
 *
 * Each option is a button with a name and a hint line beneath it saying
 * what happens then — with sharing in particular, nobody should have to
 * guess what a button releases. Escape, backdrop and Cancel yield null:
 * whoever cannot see the consequences comes back without any.
 *
 *   const selection = await choose({
 *       title: '…', text: '…',
 *       options: [{ value: 'a', label: '…', hint: '…' }, …],
 *   });
 *
 * ITS OWN, ONE-TIME WINDOW per call instead of the reused one from
 * show(): that one's footer has a fixed two buttons, and the options here
 * are arbitrarily many. Cleanup happens on closing — even a window that is
 * never needed again should not be left lying in the document.
 */
export function choose({ title, text, options, cancel = t('Abbrechen') }) {
    return new Promise((resolve) => {
        const f = document.createElement('dialog');
        f.className = 'dialog';

        const form = document.createElement('form');
        form.method = 'dialog';

        if (title) {
            const h = document.createElement('h2');
            h.className = 'dialog-title';
            h.textContent = title;
            form.append(h);
        }
        if (text) {
            const p = document.createElement('p');
            p.className = 'dialog-text';
            p.textContent = text;
            form.append(p);
        }

        let selection = null;
        const list = document.createElement('div');
        list.className = 'suggestion-list';
        for (const o of options) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'suggestion';
            button.append(o.label);
            if (o.hint) {
                const h = document.createElement('span');
                h.className = 'item-data';
                h.textContent = o.hint;
                button.append(h);
            }
            button.addEventListener('click', () => {
                selection = o.value;
                f.close();
            });
            list.append(button);
        }
        form.append(list);

        const footer = document.createElement('div');
        footer.className = 'dialog-footer';
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'button subtle';
        closeButton.textContent = cancel;
        closeButton.addEventListener('click', () => f.close());
        footer.append(closeButton);
        form.append(footer);

        f.append(form);
        f.addEventListener('close', () => {
            resolve(selection);
            f.remove();
        });

        document.body.append(f);
        f.showModal();
    });
}

/**
 * A question with two ways to say yes — „Speichern" or „Verwerfen" when
 * a window is about to close over unsaved input (18.09.2026:
 * „biete statt Im Fenster bleiben Speichern an"). Yields the value of
 * the pressed button, or null: Escape and the backdrop mean „neither",
 * and the window that asked stays as it is — the one answer that must
 * never come from a stray key is „Verwerfen".
 *
 *   await ask({ title, text, buttons: [{ label, value, danger? }] })
 *
 * ITS OWN <dialog>, like choose(), and not the shared one above: that
 * one has exactly two buttons of which the second is the form's submit,
 * and every caller relies on „right means yes, left means no". A third
 * meaning bent into it would cost them that certainty.
 */
export function ask({ title, text, buttons }) {
    return new Promise((resolve) => {
        const f = document.createElement('dialog');
        f.className = 'dialog';

        const form = document.createElement('form');
        form.method = 'dialog';

        if (title) {
            const h = document.createElement('h2');
            h.className = 'dialog-title';
            h.textContent = title;
            form.append(h);
        }
        if (text) {
            const p = document.createElement('p');
            p.className = 'dialog-text';
            p.textContent = text;
            form.append(p);
        }

        let chosen = null;
        const footer = document.createElement('div');
        footer.className = 'dialog-footer';
        for (const b of buttons) {
            const button = document.createElement('button');
            button.type = 'button';
            // subtle: the standing offer beside the one that is due (CLAUDE.md,
            // „Loud only where there is something to do").
            button.className = 'button' + (b.danger ? ' danger' : '') + (b.subtle ? ' subtle' : '');
            button.textContent = b.label;
            button.addEventListener('click', () => { chosen = b.value; f.close(); });
            footer.append(button);
        }
        form.append(footer);
        // Enter must not pick a button by accident — the form has no
        // submit, and a submit from Enter would close with „neither".
        form.onsubmit = (e) => { e.preventDefault(); f.close(); };

        f.append(form);
        f.addEventListener('close', () => {
            resolve(chosen);
            f.remove();
        });

        document.body.append(f);
        f.showModal();
        // The last button is the main one, as everywhere: right means the
        // plain answer, and a stray Enter lands on it, never on Verwerfen.
        footer.lastElementChild?.focus();
    });
}

/**
 * A small drop-down menu at the anchor — for hand moves that live behind a
 * tool button (the pencil on a list row in Organisieren).
 *
 * NOT A WINDOW but a flyout: it opens AT the button, small and without
 * dimming the page — only the chosen entry then calls up its window
 * (the call, 14.08.2026). A click elsewhere, Escape, scrolling and
 * resizing close it without consequence.
 *
 *   const selection = await popupMenu({
 *       anchor: button,
 *       entries: [{ value: 'name', label: 'Umbenennen' },
 *                   { value: 'weg', label: 'Löschen', danger: true }],
 *   });
 *
 * It stands preferably BELOW the anchor at its left edge; if the room
 * downward or rightward does not suffice, it dodges upward or to the
 * window edge — a menu that sticks out of the picture is not one.
 */
export function popupMenu({ anchor, entries }) {
    /* A SECOND TAP ON THE SAME ANCHOR CLOSES (25.09.2026) — the rule of
       infoWindow below, and for the same reason: the click-elsewhere took
       the anchor's own pointerdown, closed the menu, and the click that
       followed opened it again. Now that pointerdown is not „elsewhere",
       and the click lands here, closes the menu and answers null. */
    if (openMenu) {
        const same = openMenu.anchor === anchor;
        openMenu.close();
        if (same) return Promise.resolve(null);
    }
    return new Promise((resolve) => {
        const menu = document.createElement('div');
        menu.className = 'popup-menu';
        menu.setAttribute('role', 'menu');
        menu.tabIndex = -1;

        let done;
        for (const e of entries) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'popup-menu-entry' + (e.danger ? ' danger' : '');
            button.setAttribute('role', 'menuitem');
            button.textContent = e.label;
            button.addEventListener('click', () => done(e.value));
            menu.append(button);
        }

        // Append first, then measure: before insertion every size is 0.
        document.body.append(menu);
        const a = anchor.getBoundingClientRect();
        const m = menu.getBoundingClientRect();
        let left = Math.max(8, Math.min(a.left, window.innerWidth - m.width - 8));
        let top  = a.bottom + 4;
        if (top + m.height > window.innerHeight - 8) {
            top = Math.max(8, a.top - m.height - 4);
        }
        menu.style.left = left + 'px';
        menu.style.top  = top + 'px';

        const elsewhere = (ev) => {
            if (menu.contains(ev.target) || anchor.contains(ev.target)) return;
            done(null);
        };
        const key       = (ev) => { if (ev.key === 'Escape') done(null); };
        const close     = () => done(null);

        done = (value) => {
            document.removeEventListener('pointerdown', elsewhere, true);
            document.removeEventListener('keydown', key, true);
            window.removeEventListener('scroll', close, true);
            window.removeEventListener('resize', close);
            menu.remove();
            if (openMenu?.menu === menu) openMenu = null;
            resolve(value);
        };

        // capture, so the click-elsewhere is seen BEFORE the click target —
        // otherwise a click on another pencil would first select and then
        // close.
        document.addEventListener('pointerdown', elsewhere, true);
        document.addEventListener('keydown', key, true);
        window.addEventListener('scroll', close, true);
        window.addEventListener('resize', close);
        openMenu = { anchor, menu, close };

        // Focus on the menu itself, not on the first entry — the same
        // reason as with the dialogs' tabindex: a ring around "Umbenennen"
        // would read like a preselection.
        menu.focus();
    });
}

/**
 * A small information window at the anchor — the same shell as popupMenu()
 * (position, dodging, closing via click elsewhere/Escape/scrolling), but
 * for reading instead of choosing: lines of text, no entry that does
 * anything, hence also no result a Promise would have to carry back.
 *
 *   infoWindow({
 *       anchor: button,
 *       title: c.name,
 *       rows: [{ label: 'Besitzer', text: c.owner },
 *                { label: 'Filter', text: 'ab 01.06.2026' }],
 *   });
 *
 * A call at another anchor closes the first window by itself: the same
 * document-wide click-elsewhere that also takes away an open popupMenu()
 * ensures that two are never open at once. A call at the SAME anchor while
 * its window stands closes it and opens nothing — a second tap on what
 * opened it is the way back.
 *
 * closeAfter (milliseconds) lets the window go by itself; 0 keeps it until
 * a tap elsewhere, Escape or scrolling.
 */
export function infoWindow({ anchor, title = '', rows, closeAfter = 0 }) {
    /* A SECOND TAP ON THE SAME ANCHOR CLOSES (25.09.2026, on the
       phone: „das erneute Klicken auf das Symbol schließt nicht"). It did
       close — the click-elsewhere below caught the pointerdown on the
       anchor and removed the window — and the click that follows every
       pointerdown opened it again at once. Now the anchor's own pointerdown
       is not „elsewhere", and its click lands here and closes. */
    if (openInfo) {
        const same = openInfo.anchor === anchor;
        openInfo.close();
        if (same) return;
    }

    const windowEl = document.createElement('div');
    windowEl.className = 'popup-menu info-window';
    windowEl.setAttribute('role', 'note');
    windowEl.tabIndex = -1;

    if (title) {
        const h = document.createElement('div');
        h.className = 'info-window-title';
        h.textContent = title;
        windowEl.append(h);
    }
    for (const z of rows) {
        const p = document.createElement('p');
        p.className = 'info-window-row';
        if (z.label) {
            const l = document.createElement('span');
            l.className = 'item-data';
            l.textContent = z.label;
            p.append(l);
        }
        p.append(document.createTextNode(z.text));
        windowEl.append(p);
    }

    document.body.append(windowEl);
    const a = anchor.getBoundingClientRect();
    const m = windowEl.getBoundingClientRect();
    let left = Math.max(8, Math.min(a.left, window.innerWidth - m.width - 8));
    let top  = a.bottom + 4;
    if (top + m.height > window.innerHeight - 8) {
        top = Math.max(8, a.top - m.height - 4);
    }
    windowEl.style.left = left + 'px';
    windowEl.style.top  = top + 'px';

    let timer = 0;
    const close = () => {
        clearTimeout(timer);
        document.removeEventListener('pointerdown', elsewhere, true);
        document.removeEventListener('keydown', key, true);
        window.removeEventListener('scroll', close, true);
        window.removeEventListener('resize', close);
        windowEl.remove();
        if (openInfo?.windowEl === windowEl) openInfo = null;
    };
    const elsewhere = (ev) => {
        if (windowEl.contains(ev.target) || anchor.contains(ev.target)) return;
        close();
    };
    const key = (ev) => { if (ev.key === 'Escape') close(); };

    document.addEventListener('pointerdown', elsewhere, true);
    document.addEventListener('keydown', key, true);
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
    openInfo = { anchor, windowEl, close };

    /* AND IT GOES BY ITSELF where the caller asks for it. A line like
       „Gehört Ben" is read at a glance, and on a phone the only way out
       was a tap somewhere else (25.09.2026: „drei Sekunden
       anzeigen lassen und dann automatisch schließen"). */
    if (closeAfter > 0) timer = setTimeout(close, closeAfter);

    windowEl.focus();
}

/** The menu and the info window standing right now — for the second tap on their anchor. */
let openMenu = null;
let openInfo = null;

/**
 * Forms that are to ask before submitting.
 *
 * On the simple pages — trash, invitations — this used to be
 * onsubmit="return confirm(…)". A form with data-confirm gets the same,
 * only with our window:
 *
 *   <form method="post" data-confirm="Wirklich löschen?"
 *         data-confirm-button="Löschen" data-confirm-danger>
 */
/* EXPORTED SINCE THE TABS LOAD ON DEMAND (25.08.2026). A fragment that
   arrives after load brings its own forms with data-confirm, and nothing
   would have wired them — the dangerous buttons would go through
   silently, exactly the state the comment below describes. The guard on
   dataset.confirmReady makes a second call over the whole document
   harmless, so callers need not be careful. */
export function wireForms(root = document) {
    /* THE SELECTOR WAS 'form[data-bestaetigen]' — and the markup has said
       'data-confirm' since the rename. 61 forms carry it, none was found:
       in the whole administration NOT ONE dangerous button asked back any
       more. Deleting, unfriending, emptying the trash — everything went
       through without a question, and silently at that, because a selector
       that matches nothing is not an error, it is an empty loop.

       Within this very function both stood side by side: the text came
       from dataset.confirm; title, button and danger from the German
       names. Now all five are named alike — and the markers for "already
       wired" and "already confirmed" as well, otherwise the next half
       rename would already be prepared. */
    for (const form of root.querySelectorAll('form[data-confirm]')) {
        if (form.dataset.confirmReady) continue;
        form.dataset.confirmReady = '1';

        form.addEventListener('submit', async (e) => {
            if (form.dataset.confirmed === '1') {
                form.dataset.confirmed = '';
                return;   // second pass: let through
            }
            e.preventDefault();

            const yes = await confirm({
                title:  form.dataset.confirmTitle || t('Bestätigen'),
                text:   form.dataset.confirm,
                button:  form.dataset.confirmButton || 'OK',
                danger: form.dataset.confirmDanger !== undefined,
            });
            if (!yes) return;

            form.dataset.confirmed = '1';
            // requestSubmit instead of submit(), so that any second handler
            // and the required-field validation are preserved.
            form.requestSubmit(e.submitter ?? undefined);
        });
    }
}

document.addEventListener('DOMContentLoaded', () => wireForms());

/* NO WINDOW LEAVES A FIELD FOCUSED BEHIND (24.08.2026, after
 * his tablet kept showing „Adresse auswählen" long after the window was
 * gone — „als ob das Inputfeld noch versteckt aktiv wäre". It is exactly
 * that: the element is hidden, the focus is not, and Android keeps
 * keyboard and autofill bar standing for a focus it cannot see.)
 *
 * ONE PLACE FOR ELEVEN WINDOWS. The map, Organisieren and the administration
 * together carry eleven dialogs with real input fields, and every one of
 * them closed the same way. Eleven patches would be eleven chances to
 * forget the twelfth; here every dialog of this application is covered,
 * including the ones written tomorrow.
 *
 * IN THE CAPTURE PHASE, because 'close' does not bubble: a listener on
 * the document only sees it on the way DOWN. And only where the focus
 * really still lies inside the closed window — where the browser has
 * already moved it on, there is nothing to correct.
 */
document.addEventListener('close', (e) => {
    const active = document.activeElement;
    if (active && e.target?.contains?.(active)) active.blur();
}, true);
