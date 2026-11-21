/**
 * Einstellungen → Arten → Arbeitsnamen (includes/working_names_content.php).
 *
 * ONE WINDOW FOR NEW AND CHANGE: „Neu" opens it empty, the pencil fills it
 * from its row. Under the fields stands the name as a title will carry it,
 * written the way working_name_compose() writes it — the server composes
 * it again, this is only what one sees before saving.
 *
 * Bound by administration.js for every fragment that arrives (onTabContent).
 */

const STATUS = { ad_int: 'ad int.', nom_prov: 'nom. prov.', sp_nov: 'sp. nov.', ined: 'ined.' };

export function mountWorkingNames(root) {
    const win = root.querySelector('#working-name-window');
    if (!win || win.dataset.bound) return;
    win.dataset.bound = '1';
    const form = win.querySelector('form');
    const title = win.querySelector('.dialog-title');
    const preview = win.querySelector('[data-working-name-preview]');

    const show = () => {
        const genus = form.genus.value.trim();
        const label = form.label.value.trim().replace(/\s+/g, ' ');
        const status = STATUS[form.querySelector('input[name="status"]:checked')?.value ?? ''] ?? '';
        preview.textContent = [genus, label, status].filter(Boolean).join(' ') || '…';
    };
    form.addEventListener('input', show);
    form.addEventListener('change', show);

    const open = (values) => {
        form.reset();
        // elements.id, not form.id — that is the form's own id attribute.
        form.elements.id.value = values.id ?? '';
        form.genus.value = values.genus ?? '';
        form.label.value = values.label ?? '';
        form.note.value = values.note ?? '';
        const radio = form.querySelector(`input[name="status"][value="${values.status ?? ''}"]`);
        if (radio) radio.checked = true;
        // The ticks of whom it is shared with, from the pencil's row.
        const users = new Set(String(values.users ?? '').split(',').filter(Boolean));
        const groups = new Set(String(values.groups ?? '').split(',').filter(Boolean));
        for (const box of form.querySelectorAll('input[name="share_users[]"]')) box.checked = users.has(box.value);
        for (const box of form.querySelectorAll('input[name="share_groups[]"]')) box.checked = groups.has(box.value);
        title.textContent = values.id ? title.dataset.edit : title.dataset.new;
        show();
        win.showModal();
        (values.id ? form.label : form.genus).focus();
    };

    root.querySelector('[data-working-name-new]')?.addEventListener('click', () => open({}));
    for (const button of root.querySelectorAll('[data-working-name-edit]')) {
        button.addEventListener('click', () => open({ ...button.dataset }));
    }

    // „Veröffentlicht als …": the window names the working name and starts with its genus.
    const publish = root.querySelector('#working-name-publish-window');
    for (const button of root.querySelectorAll('[data-working-name-publish]')) {
        button.addEventListener('click', () => {
            const f = publish.querySelector('form');
            f.reset();
            f.elements.id.value = button.dataset.id;
            const text = publish.querySelector('[data-publish-text]');
            text.textContent = text.dataset.one.replace('%1$s', button.dataset.name);
            f.elements.published.value = button.dataset.genus + ' ';
            publish.showModal();
            f.elements.published.focus();
        });
    }
}
