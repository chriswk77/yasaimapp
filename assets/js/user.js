/**
 * The dialogs of the user administration.
 *
 * WHY A DIALOG AND NOT FIELDS IN THE ROW
 * In the row, first name, last name, nick, role and quota stood side by
 * side — five input fields in one table cell, squeezed on top of each other
 * on the phone, and the table so wide you had to drag it. What you rarely
 * do does not belong permanently in view.
 *
 * ONE DIALOG FOR ALL ROWS. With twenty members there would be twenty forms
 * in the source, nineteen of which nobody ever sees. What belongs inside
 * sits on the row's button; here it gets filled in.
 *
 * BLOCKING AND DELETING ASK FIRST — but not via the forms' data-confirm:
 * That hangs on the form, and here three buttons share one. "Speichern"
 * should not ask, the other two should.
 */

import { confirm } from './dialogs.js';
import { t } from './i18n.js';

const dialog = document.getElementById('user-window');

if (dialog) {
    const fields = {
        id:        document.getElementById('user-id'),
        action:    document.getElementById('user-action'),
        heading:   document.getElementById('user-heading'),
        firstName: document.getElementById('user-first-name'),
        lastName:  document.getElementById('user-last-name'),
        role:      document.getElementById('user-role'),
        quota:     document.getElementById('user-quota'),
        danger:    document.getElementById('user-danger'),
        lock:      document.getElementById('user-lock'),
        remove:    document.getElementById('user-delete'),
    };
    const form = dialog.querySelector('form');
    let current = null;                 // the data of the button being edited

    for (const button of document.querySelectorAll('.user-edit')) {
        button.addEventListener('click', () => {
            const d = button.dataset;
            current = d;

            fields.id.value         = d.id;
            fields.action.value     = 'save';
            fields.firstName.value    = d.first_name;
            fields.lastName.value   = d.last_name;
            fields.role.value      = d.role;
            fields.quota.value = d.quota;
            fields.heading.textContent = d.name;

            // None of this works on your own account: Locking yourself out
            // is the one mistake you cannot take back.
            const isSelf = d.self === '1';
            fields.role.disabled = isSelf;
            fields.danger.hidden  = isSelf;
            fields.lock.textContent = d.locked === '1' ? t('Entsperren') : t('Sperren');

            dialog.showModal();
            fields.firstName.focus();
        });
    }

    document.getElementById('user-cancel').addEventListener('click', () => dialog.close());

    fields.lock.addEventListener('click', async (e) => {
        e.preventDefault();
        const unlocking = current?.locked === '1';
        const ok = await confirm({
            title: unlocking ? t('„%s" wieder freigeben?', current.name) : t('„%s" sperren?', current.name),
            text:  unlocking
                ? t('Er kommt danach wieder herein und findet alles vor, wie er es verlassen hat.')
                : t('Er kommt dann nicht mehr herein, behält aber alles. Auch die angemeldeten '
                  + 'Geräte werden abgemeldet. Rückgängig zu machen.'),
            button: unlocking ? t('Freigeben') : t('Sperren'),
            danger: !unlocking,
        });
        if (!ok) return;
        fields.action.value = unlocking ? 'unlock' : 'lock';
        form.submit();
    });

    fields.remove.addEventListener('click', async (e) => {
        e.preventDefault();
        // WHAT GETS LOST BELONGS IN THE QUESTION. Nobody answers "Wirklich
        // löschen?" reliably; "145 Punkte und 6 Listen" they do.
        const ok = await confirm({
            title: t('„%s" endgültig löschen?', current.name),
            text:  t('%s Punkte, %s Listen, alle Gebiete und alle '
                 + 'Dateien dieses Kontos verschwinden mit. Das lässt sich nicht zurückholen — '
                 + 'auch nicht über den Papierkorb.', current.points, current.lists),
            button: t('Endgültig löschen'),
            danger: true,
        });
        if (!ok) return;
        fields.action.value = 'delete';
        form.submit();
    });
}

/* --- New invitation ----------------------------------------------------- */

const invitationDialog = document.getElementById('invitation-window');
if (invitationDialog) {
    document.getElementById('invitation-new')?.addEventListener('click', () => {
        invitationDialog.showModal();
        document.getElementById('invitation-note')?.focus();
    });
    document.getElementById('invitation-cancel')?.addEventListener('click', () => invitationDialog.close());
}
