/**
 * Verwaltung → Bildarchive → Freunde (includes/archives_content.php,
 * archives_friends, says what stands there and why).
 *
 * EVERY TICK SAVES AT ONCE, and so does every name the filter window adds
 * or crosses out (02.11.2026: „bei solchen Haken würde ich ein
 * Autosave machen") — one change per call through
 * api/v1/archive_viewers.php, whose answer is the friend's answer as
 * stored; the row is drawn from that, never from what was clicked.
 *
 * Bound by administration.js for every fragment that arrives (onTabContent).
 */

import { request } from './api.js';
import { toast } from './toast.js';
import { t } from './i18n.js';
import { icon } from './icons.js';

export function mountArchiveViewers(root) {
    for (const row of root.querySelectorAll('tr[data-archive-friend]')) {
        if (row.dataset.bound) continue;
        row.dataset.bound = '1';
        for (const input of row.querySelectorAll('input[data-what]')) {
            input.addEventListener('change', () => tick(row, input));
        }
        row.querySelector('[data-archive-filter]')?.addEventListener('click', () => openFilter(row));
    }
    const win = root.querySelector('#archive-filter-window');
    if (win && !win.dataset.bound) {
        win.dataset.bound = '1';
        for (const button of win.querySelectorAll('[data-archive-add]')) {
            const field = win.querySelector(`input[data-kind="${button.dataset.archiveAdd}"]`);
            button.addEventListener('click', () => add(win, field));
            field.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    add(win, field);
                }
            });
        }
    }
}

/** One call, one change; the row then shows what the server stored. */
async function save(row, change) {
    const answer = await request('archive_viewers.php', { method: 'POST',
        body: JSON.stringify({ friend: Number(row.dataset.archiveFriend), ...change }) });
    draw(row, answer.answer);
    return answer.answer;
}

async function tick(row, input) {
    const on = input.checked;
    input.disabled = true;
    try {
        await save(row, { [input.dataset.what]: on });
        toast(t('Gespeichert.'));
    } catch (e) {
        input.checked = !on;
        toast(e?.message ?? t('Speichern fehlgeschlagen.'), 'error');
    }
    input.disabled = false;
    // „Fundorte" and the filter mean nothing for a friend who sees nothing.
    const sees = row.querySelector('input[data-what="sees"]').checked;
    row.querySelector('input[data-what="spots"]').disabled = !sees;
    row.querySelector('[data-archive-filter]').disabled = !sees;
}

function draw(row, answer) {
    row.querySelector('input[data-what="sees"]').checked = answer.sees;
    row.querySelector('input[data-what="spots"]').checked = answer.spots;
    row.dataset.hidden = JSON.stringify(answer.hidden);
    const names = [...answer.hidden.genera, ...answer.hidden.species];
    const cell = row.querySelector('.archive-hidden-summary');
    if (names.length === 0) {
        const quiet = document.createElement('span');
        quiet.className = 'hint-text';
        quiet.textContent = t('nichts');
        cell.replaceChildren(quiet);
    } else {
        cell.textContent = names.join(', ');
    }
}

let filterRow = null;

function openFilter(row) {
    const win = document.getElementById('archive-filter-window');
    filterRow = row;
    win.querySelector('.dialog-title').textContent = Number(row.dataset.archiveFriend) === 0
        ? t('Filter für neue Freunde')
        : t('Filter für %s', row.dataset.name);
    for (const field of win.querySelectorAll('input[data-kind]')) field.value = '';
    fault(win, '');
    lists(win);
    win.showModal();
}

/** The two lists in the window, from the row's stored answer. */
function lists(win) {
    const hidden = JSON.parse(filterRow.dataset.hidden || '{"genera":[],"species":[]}');
    for (const [kind, names] of [['genus', hidden.genera], ['species', hidden.species]]) {
        const list = win.querySelector(`.archive-filter-list[data-kind="${kind}"]`);
        list.replaceChildren(...names.map(name => nameRow(win, kind, name)));
        if (names.length === 0) {
            const none = document.createElement('p');
            none.className = 'hint-text';
            none.textContent = t('Keine.');
            list.append(none);
        }
    }
}

/* The DGfM rules' row (app.css .rule-row.is-excluded): the name in the
   light red of „this stays out", the cross at its end. */
function nameRow(win, kind, name) {
    const row = document.createElement('div');
    row.className = 'item-row rule-row is-excluded';
    const label = document.createElement('span');
    label.className = 'rule-name';
    label.textContent = name;
    const cross = document.createElement('button');
    cross.type = 'button';
    cross.className = 'symbol-button';
    cross.title = t('Entfernen');
    cross.setAttribute('aria-label', t('„%s" entfernen', name));
    cross.append(icon('close'));
    cross.addEventListener('click', async () => {
        cross.disabled = true;
        try {
            await save(filterRow, { remove: { kind, name } });
            fault(win, '');
            lists(win);
        } catch (e) {
            cross.disabled = false;
            fault(win, e?.message ?? t('Speichern fehlgeschlagen.'));
        }
    });
    row.append(label, cross);
    return row;
}

async function add(win, field) {
    const name = field.value.trim();
    if (name === '' || !filterRow) return;
    field.disabled = true;
    try {
        await save(filterRow, { add: { kind: field.dataset.kind, name } });
        fault(win, '');
        field.value = '';
        lists(win);
    } catch (e) {
        fault(win, e?.message ?? t('Speichern fehlgeschlagen.'));
    }
    field.disabled = false;
    field.focus();
}

/* WHAT WENT WRONG STANDS IN THE WINDOW, red — a toast would lie behind the
   modal (CLAUDE.md, „A question left open … turns red INSIDE its
   window"). What went right needs no word here: the name appears in its
   list, or leaves it. */
function fault(win, text) {
    const line = win.querySelector('.archive-filter-fault');
    line.textContent = text;
    line.hidden = text === '';
}
