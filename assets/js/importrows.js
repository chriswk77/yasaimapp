/**
 * The import preview's table — every entry of the file, what happens to
 * it, and the way out of a conflict beside it (includes/import_content.php,
 * import_screen_render; the verdicts come from includes/import_screen.php).
 *
 * WHAT HAPPENS HERE, all of it in the browser and none of it stored: the
 * filters show one kind at a time; „alle einspielen" ticks a finder's
 * whole lot; a question that follows another (the number of a find one
 * determined, the number of a renamed one) appears with its answer; the
 * headline counts along; and „Einspielen" is stopped while „vielleicht
 * umbenannt" has no answer (26.09.2026: „wenn man dann
 * einspielen klickt und nichts ist ausgewählt, soll das erstmal blockieren
 * und der Fehler gezeigt werden"). The server refuses the same case once
 * more (import_process) — this is the half one sees.
 *
 * Its own module and not more lines in administration.js: the import is a
 * thing of its own, and that file is long enough.
 */

import { t } from './i18n.js';
import { toast } from './toast.js';
import { findNumberKey } from './findnumber.js';

export function mountImportRows(root) {
    const box = root.querySelector('.import-rows');
    const count = root.querySelector('.import-count[data-count]');
    // Meine Pilze's boxes, ticked from the start: unticking takes away.
    const fungi = [...root.querySelectorAll('[data-import-take]')];
    if (!box) {
        wireCount(root, count, fungi);
        return;
    }
    const rows = [...box.querySelectorAll('tbody tr')];
    // The numbers given, keyed by the number in the series (findNumberKey,
    // find_number_key on the server) — „wk-1" typed is taken when
    // „wk-00001" is.
    const given = new Set(JSON.parse(box.dataset.given || '[]'));
    const pattern = box.dataset.pattern || null;

    // --- the filters ------------------------------------------------------
    const filters = [...box.querySelectorAll('[data-filter]')];
    const show = (filter) => {
        for (const button of filters) button.classList.toggle('is-active', button.dataset.filter === filter);
        for (const row of rows) {
            const kinds = row.dataset.kinds.split(' ').filter(Boolean);
            const verdict = row.dataset.verdict;
            row.hidden = !(filter === 'all'
                || (filter === 'conflict' && kinds.length > 0)
                || kinds.includes(filter)
                || (filter === 'there' && (verdict === 'there' || verdict === 'filled'))
                || (kinds.length === 0 && verdict === filter));
        }
        for (const bar of box.querySelectorAll('[data-bar]')) bar.hidden = bar.dataset.bar !== filter;
        // The spelling hint belongs to the other finders' entries.
        const spelling = box.querySelector('.import-spelling');
        if (spelling) spelling.hidden = !['conflict', 'finder', 'all'].includes(filter);
    };
    for (const button of filters) button.addEventListener('click', () => show(button.dataset.filter));
    show(box.dataset.start || 'all');

    // --- bringing in: one find, or a finder's whole lot --------------------
    const takes = [...box.querySelectorAll('[data-take]')];
    const lot = (button) => takes.filter((b) => b.dataset.take === button.dataset.takeAll
        && (button.dataset.takeAll !== 'finder' || b.dataset.finder === button.dataset.finder));
    const label = () => {
        for (const button of box.querySelectorAll('[data-take-all]')) {
            const all = lot(button).every((b) => b.checked);
            button.textContent = all ? button.dataset.off : button.dataset.on;
        }
    };
    const follow = (take) => {
        const next = take.closest('tr').querySelector('[data-follows="take"]');
        if (next) next.hidden = !take.checked;
    };
    for (const take of takes) {
        take.addEventListener('change', () => { follow(take); label(); draw(); });
    }
    for (const button of box.querySelectorAll('[data-take-all]')) {
        button.addEventListener('click', () => {
            const boxes = lot(button);
            const on = !boxes.every((b) => b.checked);
            for (const b of boxes) { b.checked = on; follow(b); }
            label();
            draw();
        });
    }

    // --- a number another find carries ------------------------------------
    const twice = [...box.querySelectorAll('[data-twice]')];
    const way = (block) => block.querySelector('input[type=radio]:checked')?.value ?? 'none';
    const syncTwice = (block) => {
        const same = block.querySelector('[data-twice-same]');
        if (!same) return;
        same.hidden = way(block) === 'own';
        same.textContent = way(block) === 'next' ? same.dataset.next : same.dataset.none;
    };
    const setAll = (chosen) => {
        for (const block of twice) {
            // A number somebody typed stays — „bei allen" means the others.
            const typed = block.querySelector('.import-number')?.value.trim() ?? '';
            if (way(block) === 'own' && typed !== '') continue;
            const radio = block.querySelector(`input[type=radio][value="${chosen}"]`);
            if (radio) radio.checked = true;
            syncTwice(block);
        }
    };
    for (const block of twice) {
        for (const radio of block.querySelectorAll('input[type=radio]')) {
            radio.addEventListener('change', () => syncTwice(block));
        }
        block.querySelector('[data-twice-same]')?.addEventListener('click', () => setAll(way(block)));
        const field = block.querySelector('.import-number');
        const state = block.querySelector('.import-number-state');
        field?.addEventListener('input', () => {
            const own = block.querySelector('input[type=radio][value="own"]');
            if (own) own.checked = true;
            const value = field.value.trim() === '' ? '' : findNumberKey(pattern, field.value);
            state.textContent = value === '' ? '' : (given.has(value) ? field.dataset.taken : field.dataset.free);
            state.classList.toggle('is-taken', value !== '' && given.has(value));
            syncTwice(block);
        });
        syncTwice(block);
    }
    for (const button of box.querySelectorAll('[data-twice-all]')) {
        button.addEventListener('click', () => setAll(button.dataset.twiceAll));
    }

    // --- perhaps renamed: the answer opens the next question ---------------
    const renamed = [...box.querySelectorAll('[data-renamed]')];
    for (const block of renamed) {
        const row = block.closest('tr');
        for (const radio of block.querySelectorAll('input[type=radio]')) {
            radio.addEventListener('change', () => {
                const same = radio.value === 'rename' || radio.value === 'keep';
                for (const next of row.querySelectorAll('[data-follows="same"]')) next.hidden = !same;
                for (const next of row.querySelectorAll('[data-follows="new"]')) next.hidden = radio.value !== 'new';
                row.classList.remove('is-missing');
                block.querySelector('.import-missing').hidden = true;
                draw();
            });
        }
    }

    // --- the headline counts along -----------------------------------------
    const draw = wireCount(root, count, fungi, () =>
        takes.filter((b) => b.checked).length
        + renamed.filter((block) => block.querySelector('input[value="new"]')?.checked).length);

    // --- „Einspielen" waits for every answer --------------------------------
    const form = document.getElementById('import-apply-form');
    form?.addEventListener('submit', (event) => {
        const open = renamed.filter((block) => !block.querySelector('input[type=radio]:checked'));
        if (open.length === 0) return;
        // Before the handler that greys the buttons out — nothing is sent.
        event.preventDefault();
        event.stopImmediatePropagation();
        for (const block of open) {
            block.closest('tr').classList.add('is-missing');
            block.querySelector('.import-missing').hidden = false;
        }
        show('renamed');
        open[0].closest('tr').scrollIntoView({ block: 'center', behavior: 'smooth' });
        toast(open.length === 1
            ? t('Bei einem Fund fehlt noch deine Entscheidung. Er ist rot markiert.')
            : t('Bei %d Funden fehlt noch deine Entscheidung. Sie sind rot markiert.', open.length), 'error');
    }, { capture: true });
}

/**
 * The count in the headline: what the dry run creates, less Meine Pilze's
 * unticked boxes, plus whatever the table brings in on top ($extra).
 * The two wordings come from the server — German does not accept
 * „1 Funde".
 */
function wireCount(root, count, fungi, extra = () => 0) {
    const draw = () => {
        if (!count) return;
        const n = Math.max(0, Number(count.dataset.count) - fungi.filter((b) => !b.checked).length + extra());
        count.textContent = n === 1 ? count.dataset.one : count.dataset.many.replace('%d', String(n));
    };
    for (const b of fungi) b.addEventListener('change', draw);
    return draw;
}
