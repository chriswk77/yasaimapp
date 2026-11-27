/**
 * THE ORDER OF THE CHAT FLYOUT — the list under the speech bubble, on the
 * map (bindFriendChat in map.js) and in the header of the other pages
 * (headerchat.js). Both render the same parts (chat_flyout_parts() in
 * includes/page.php): a search field, the rows, a „nobody found" line and
 * the plus button; this file decides which of them stand in sight.
 *
 * TWO STATES (27.11.2026: „Aktive nur anzeigen, wenn es Aktive gibt, also
 * wenn der Button grün leuchtet … ansonsten zeigen wir alle an"):
 *
 * - SOMEBODY WROTE — the bubble is green. Then the list shows only those
 *   conversations, the ones one opened the list for, and below them
 *   „+ Chat beginnen", which turns the list into the second state. With
 *   many friends the one who wrote would otherwise hide among fifty names.
 * - NOBODY WROTE — every friend, as before: the list stays the one door on
 *   the map through which a NEW conversation starts (22.08.2026).
 *
 * „Active" is the GREEN dot only, a line somebody wrote. A grey dot — only
 * the map's own status lines wait — does not light the bubble either
 * (friendUnreadAny in chat.js), so it does not narrow the list.
 *
 * THE SEARCH FIELD ONLY WHERE THERE IS SOMETHING TO SEARCH. Over three
 * names it is a field to look past; from SEARCH_FROM friends on it stands
 * on top of the full list. Enter opens the first name that is left.
 *
 * THE ROWS SAY ONLY THE NAME. They used to read „Chat mit Anna",
 * „Chat mit Ben" … — the bubble that opened the list already said what a
 * row does, and the same two words in front of every name made the names
 * the hardest thing to read. The symbol in front says something else: a
 * bubble with lines where a conversation exists, an empty one where
 * nothing was said yet.
 */

import { icon } from './icons.js';

const SEARCH_FROM = 7;

/**
 * The symbol before a name: a bubble holding lines where a conversation
 * exists, an empty, quieter one where nothing was said yet
 * (27.11.2026). The same two as chat_flyout_symbol() in page.php.
 */
export function chatFlyoutSymbol(started) {
    const el = icon(started ? 'speech-bubble-text' : 'speech-bubble');
    el.classList.add('chat-flyout-symbol');
    el.classList.toggle('is-empty', !started);
    return el;
}

const bound = new WeakSet();
const changed = new WeakMap();

function parts(flyout) {
    return {
        find: flyout.querySelector('.chat-flyout-find'),
        search: flyout.querySelector('.chat-flyout-search'),
        none: flyout.querySelector('.chat-flyout-none'),
        more: flyout.querySelector('.chat-flyout-more'),
        rows: [...flyout.querySelectorAll('.chat-flyout-list .chat-flyout-row')],
    };
}

function show(flyout, mode) {
    const { find, search, none, more, rows } = parts(flyout);
    const all = mode === 'all';
    flyout.dataset.mode = mode;
    find.hidden = !all || rows.length < SEARCH_FROM;
    more.hidden = all;
    const query = find.hidden ? '' : search.value.trim().toLocaleLowerCase();
    let shown = 0;
    for (const row of rows) {
        const on = all
            ? (row.dataset.name ?? row.textContent).toLocaleLowerCase().includes(query)
            : row.classList.contains('has-unread');
        row.hidden = !on;
        if (on) shown++;
    }
    none.hidden = !all || shown > 0;
    changed.get(flyout)?.();
}

function bind(flyout) {
    if (bound.has(flyout)) return;
    bound.add(flyout);
    const { search, more } = parts(flyout);
    search.addEventListener('input', () => show(flyout, 'all'));
    search.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        parts(flyout).rows.find((r) => !r.hidden)?.click();
    });
    more.addEventListener('click', () => {
        show(flyout, 'all');
        // The search field where there is one, else the first name — the
        // keyboard goes on where the list went.
        const { find, search: field, rows } = parts(flyout);
        (find.hidden ? rows.find((r) => !r.hidden) : field)?.focus();
    });
}

/**
 * Put the flyout into its state for an opening — or, with `keep`, for a
 * redraw while it stands open (the quiet poll brought a dot): the full list
 * and what was typed into the search stay then. `onChange` hears every
 * change of shape, so the map can place the list under its bubble again.
 */
export function arrangeChatFlyout(flyout, { keep = false, onChange = null } = {}) {
    bind(flyout);
    if (onChange) changed.set(flyout, onChange);
    const { search, rows } = parts(flyout);
    if (!keep) search.value = '';
    const anyActive = rows.some((r) => r.classList.contains('has-unread'));
    const mode = (keep && flyout.dataset.mode === 'all') || !anyActive ? 'all' : 'active';
    show(flyout, mode);
}
