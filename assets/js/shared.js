/**
 * Friends & groups — the hand moves that work without reloading.
 *
 * Everything that CHANGES data remains a form and reloads the page — the
 * same arrangement as everywhere in the administration. Only what happens
 * before that lives here: the request window with the name search, the
 * groups' create and admit windows, and the switch of which group shows its
 * members on the right.
 */

import { shapePoint } from './shapes.js';
import { t } from './i18n.js';

/* ONCE AT LOAD, AND AGAIN WHEN A TAB ARRIVES (25.08.2026).
 *
 * The administration loads this script on every view — without the
 * respective tab (migration missing, or a page entirely without it) there
 * is nothing to wire up, and the guards say so. Since the tabs fetch
 * their content on demand, "not there" is no longer the same as "will
 * never be there": at load the friends window does not exist yet, and it
 * appears the moment somebody opens the tab. Hence a function instead of
 * three bare ifs, and administration.js calls it after every fragment.
 *
 * THE THREE WINDOWS STAY MODULE-WIDE, and that is not tidiness — the
 * three wirings USE them, nine times over, and they are not merely
 * guards. Making them local to this function cost the maintainer every tab of
 * „Mein Bereich" on 25.08.2026: wireContainers() reached for a name that
 * no longer existed, the ReferenceError tore administration.js down with
 * it, and with it the loader that fills the tabs. node --check does not
 * see that — it reads syntax, not names.
 *
 * WIRED AT MOST ONCE PER WINDOW. This runs after every fragment, and a
 * tab already standing must not collect its handlers a second time. The
 * element itself is the key: a fragment fetched anew brings a NEW window
 * along, and that one is wired again, rightly. */
let searchWindow = null;
let newWindow = null;
let containerNewWindow = null;
const wired = new WeakSet();

export function mountShared() {
    searchWindow       = document.getElementById('friend-search-window');
    newWindow          = document.getElementById('group-new-window');
    containerNewWindow = document.getElementById('container-new-window');

    for (const [window, wire] of [[searchWindow, wireFriends],
                                  [newWindow, wireGroups],
                                  [containerNewWindow, wireContainers]]) {
        if (!window || wired.has(window)) continue;
        wired.add(window);
        wire();
    }
}

mountShared();

// ---------------------------------------------------------------------------
// Requests: the search window
// ---------------------------------------------------------------------------

function wireFriends() {
    const field       = document.getElementById('friend-search');
    const suggestions = document.getElementById('friend-suggestions');
    const whoField    = document.getElementById('friend-who');
    const form        = searchWindow.querySelector('form');

    document.getElementById('friend-request-plus').addEventListener('click', () => {
        field.value = '';
        whoField.value = '';
        // EVERYBODY RIGHT AWAY, not an empty box. Requesting used to work
        // only by typing — which presumes you know how the other person is
        // registered here. The list is short; showing it is the friendlier
        // entry, and typing narrows it down from there.
        suggestions.replaceChildren(hint(t('wird geholt …')));
        searchWindow.showModal();
        field.focus();
        load('');
    });
    document.getElementById('friend-search-cancel').addEventListener('click', () => searchWindow.close());

    /* THE REPORT WINDOW (23.11.2026): the flag in a friend's row says whom,
       the window asks what happened. It stands in the same fragment as the
       search window, so it is wired here, once with it. */
    const reportWindow = document.getElementById('friend-report-window');
    if (reportWindow) {
        const reportText = document.getElementById('friend-report-text');
        for (const button of document.querySelectorAll('.friend-report')) {
            button.addEventListener('click', () => {
                document.getElementById('friend-report-id').value = button.dataset.id;
                document.getElementById('friend-report-title').textContent = t('%s melden', button.dataset.name);
                reportText.value = '';
                reportWindow.showModal();
                reportText.focus();
            });
        }
        document.getElementById('friend-report-cancel').addEventListener('click', () => reportWindow.close());
    }

    // Enter in the search field used to submit the form — with an empty
    // who field. Submitting happens only via a suggestion.
    field.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') e.preventDefault();
    });

    // Search while typing, calmly: only after a quarter second of quiet.
    // The counter lets late answers lapse — otherwise the slow answer to
    // "th" would overwrite the fast one to "tho".
    let debounce = 0;
    let run = 0;

    /* FIFTEEN AT A TIME, THE NEXT FIFTEEN WHEN THE END COMES INTO VIEW
       (02.10.2026: „dass die Liste lang sein kann … und man beim
       Runterscrollen dann weitere nachlädt"). The last row of a page that
       has a successor is a quiet line; once it scrolls into sight it
       fetches the next page and steps aside for it.

       ROOT null, NOT THE LIST: the list has no scroll box of its own
       (CLAUDE.md, no scroll area inside a scroll area) — the window
       scrolls, on the desk the dialog, on the phone its form. An observer
       on the viewport sees through either, because intersection is
       clipped by every scrolling ancestor on the way. */
    const PAGE = 15;
    const tail = hint(t('weitere werden geholt …'));
    let next = null;   // { word, offset } of the page the tail stands for
    const watcher = new IntersectionObserver((entries) => {
        if (!next || !entries.some((e) => e.isIntersecting)) return;
        const { word, offset } = next;
        next = null;
        watcher.unobserve(tail);
        load(word, offset);
    });

    /* ONE WAY FOR BOTH. The full list and the search differ only in the
       word that goes along — the answer has the same shape, and so does
       what is drawn from it. Two copies would drift apart at the first
       change to the row. A NEW WORD starts a new run; a later page belongs
       to the run it was asked for, and is dropped if the word changed
       meanwhile. */
    async function load(word, offset = 0) {
        const thisRun = offset === 0 ? ++run : run;
        if (offset === 0) {
            next = null;
            watcher.unobserve(tail);
        }

        let hits;
        let more;
        try {
            const response = await fetch(
                (window.START_DATA?.baseUrl ?? '') + 'pages/administration.php?friend_search='
                    + encodeURIComponent(word) + '&offset=' + offset,
                { headers: { 'Accept': 'application/json' } }
            );
            // 'hits' is the wire's word (friends_content.php) — and this
            // line read 'treffer' until 22.08.2026, three days after the
            // server had moved. Nothing broke visibly: an undefined list
            // is an empty list, and an empty list says „Niemand
            // gefunden." So the request window claimed there was nobody
            // to befriend, in an application whose whole point is
            // sharing, and it looked like a decision instead of a defect
            // (the maintainer found it).
            ({ hits, more } = await response.json());
        } catch {
            if (thisRun !== run) return;
            const failed = hint(t('Die Suche geht gerade nicht. Versuch es gleich noch einmal.'));
            if (offset === 0) suggestions.replaceChildren(failed);
            else tail.replaceWith(failed);
            return;
        }
        if (thisRun !== run) return;

        // The likeliest reason, said along (02.10.2026): the person has no
        // account here yet.
        if (offset === 0 && !hits?.length) {
            suggestions.replaceChildren(hint(t('Niemand gefunden. Hat die Person schon ein Konto bei YASAIMAPP?')));
            return;
        }

        const rows = (hits ?? []).map(suggestionRow);
        if (offset === 0) suggestions.replaceChildren(...rows);
        else {
            tail.remove();
            suggestions.append(...rows);
        }
        if (more) {
            next = { word, offset: offset + PAGE };
            suggestions.append(tail);
            watcher.observe(tail);
        }
    }

    function suggestionRow(hit) {
        // 'hit', not 't' — that name would shadow t() from i18n.js.
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'suggestion';

        const name = document.createElement('span');
        name.textContent = hit.display_name;
        const data = document.createElement('span');
        data.className = 'item-data';
        // The address comes only where it is already known (friends.php).
        data.textContent = [
            hit.email ?? '',
            hit.state === 'friend' ? t('schon befreundet') : '',
            hit.state === 'open'   ? t('Anfrage läuft schon') : '',
        ].filter(Boolean).join(' · ');
        button.append(name, data);

        if (hit.state === 'free') {
            button.addEventListener('click', () => {
                /* hit.id — NOT t.id. The rename to 'hit' (see above)
                   left this one line behind: t is the translator, has
                   no id, and the who field went out empty — the click
                   did nothing at all. 12.09.2026: „Diese
                   Person kann ich anklicken, aber eine
                   Freundschaftsanfrage wird dadurch nicht aktiviert." */
                whoField.value = hit.id;
                form.requestSubmit();
            });
        } else {
            button.disabled = true;
        }
        return button;
    }

    field.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            const word = field.value.trim();
            // A SINGLE LETTER IS NOT A SEARCH — but an empty field is the
            // full list again, not an empty box: whoever deletes what they
            // typed wants to be back at the start, not at nothing.
            if (word.length === 1) return;
            load(word);
        }, 250);
    });
}

// ---------------------------------------------------------------------------
// Groups: creating, showing members, admitting
// ---------------------------------------------------------------------------

function wireGroups() {
    document.getElementById('group-plus').addEventListener('click', () => newWindow.showModal());
    document.getElementById('group-new-cancel').addEventListener('click', () => newWindow.close());

    // Which group stands on the right: state only in the DOM, nothing to
    // remember — after a form submit the server preselects
    // (groups_content.php).
    for (const selection of document.querySelectorAll('.group-selection')) {
        selection.addEventListener('click', () => {
            for (const other of document.querySelectorAll('.group-selection')) {
                other.classList.toggle('is-active', other === selection);
            }
            for (const block of document.querySelectorAll('#group-detail .gr-members')) {
                block.hidden = block.dataset.group !== selection.dataset.group;
            }
        });
    }

    const admitWindow = document.getElementById('group-admit-window');
    if (!admitWindow) return;
    const data = JSON.parse(document.getElementById('shared-data').textContent);

    document.getElementById('group-admit-cancel').addEventListener('click', () => admitWindow.close());

    for (const plus of document.querySelectorAll('.gr-admit')) {
        plus.addEventListener('click', () => {
            document.getElementById('group-admit-title').textContent = t('In „%s“ aufnehmen', plus.dataset.name);
            document.getElementById('group-admit-group').value = plus.dataset.group;

            const inside = data.groups[plus.dataset.group] ?? [];
            const candidates = data.friends.filter((f) => !inside.includes(f.id));

            const list = document.getElementById('group-admit-candidates');
            if (!candidates.length) {
                // The button stays put regardless: a plus that vanishes
                // depending on the situation looks like a bug. The window
                // says instead why it has nothing to offer.
                list.replaceChildren(hint(data.friends.length
                    ? t('Alle deine Freunde sind schon in dieser Gruppe.')
                    : t('Du hast noch keine Freunde — erst anfragen, dann aufnehmen.')));
            } else {
                list.replaceChildren(...candidates.map((f) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'suggestion';
                    button.textContent = f.name;
                    button.addEventListener('click', () => {
                        document.getElementById('group-admit-who').value = f.id;
                        admitWindow.querySelector('form').requestSubmit();
                    });
                    return button;
                }));
            }
            admitWindow.showModal();
        });
    }
}

// ---------------------------------------------------------------------------
// Containers: creating, admitting members, mounting lists
// ---------------------------------------------------------------------------

function wireContainers() {
    document.getElementById('container-plus').addEventListener('click', () => containerNewWindow.showModal());
    document.getElementById('container-new-cancel').addEventListener('click', () => containerNewWindow.close());

    // Collapsing containers: delivered collapsed by default. When you open a
    // container, the browser remembers its ID in local storage
    // (map.openContainers), so the state survives page loads and form
    // actions. legacy.js carries the old German key over.
    const KEY = 'map.openContainers';
    let openIds = new Set();
    try {
        openIds = new Set(JSON.parse(localStorage.getItem(KEY)) || []);
    } catch { /* then just empty */ }

    for (const header of document.querySelectorAll('.ct-block > .column-header')) {
        const block = header.closest('.ct-block');
        if (!block) continue;
        const id = block.dataset.container;

        if (id && openIds.has(id)) {
            block.classList.remove('is-collapsed');
        } else {
            block.classList.add('is-collapsed');
        }

        header.addEventListener('click', (e) => {
            if (e.target.closest('button, form, a, input')) return;
            const isClosed = block.classList.toggle('is-collapsed');
            if (id) {
                if (isClosed) {
                    openIds.delete(id);
                } else {
                    openIds.add(id);
                }
                localStorage.setItem(KEY, JSON.stringify([...openIds]));
            }
        });
    }

    // The title rules of the birth filter: one row stands there from the
    // start, more come via the button. Getting rid of one = empty the text
    // field; empty rules do not count server-side (share_content.php
    // explains why there is no bin here).
    const rules = document.getElementById('container-rules');
    const ruleRow = (kindValue = 'contains', textValue = '') => {
        const row = document.createElement('div');
        row.className = 'ct-rule';
        const kind = document.createElement('select');
        kind.name = 'begriff_art[]';
        for (const [value, caption] of [['contains', t('enthält')], ['contains_not', t('enthält nicht')]]) {
            const o = document.createElement('option');
            o.value = value;
            o.textContent = caption;
            kind.append(o);
        }
        kind.value = kindValue;
        const text = document.createElement('input');
        text.type = 'text';
        text.name = 'begriff_text[]';
        text.maxLength = 100;
        text.placeholder = t('Begriff im Titel');
        text.value = textValue;
        row.append(kind, text);
        return row;
    };
    if (rules) {
        rules.append(ruleRow());
        document.getElementById('container-rule-add').addEventListener('click', () => {
            rules.append(ruleRow());
            rules.lastElementChild.querySelector('input').focus();
        });
    }

    // The area: drawing happens on the BIG map (map.js,
    // window=filter-area). The form state travels there and back via
    // sessionStorage — the stash lives only until the decision: create or
    // cancel clears it away, otherwise a ghost state would stand in the
    // window at the next plus.
    // See the note in map.js: this name is shared by three files.
    const STASH        = 'map.containerFilter';
    const cornersField = document.getElementById('container-filter-corners');
    if (cornersField) {
        const areaStatus  = document.getElementById('container-area-status');
        const areaDiscard = document.getElementById('container-area-discard');

        const showArea = (corners) => {
            const good = Array.isArray(corners) && corners.length >= 3;
            cornersField.value = good ? JSON.stringify(corners) : '';
            areaStatus.textContent = good ? t('%s Ecken umrandet.', corners.length) : t('Keins umrandet.');
            areaDiscard.hidden = !good;
        };

        document.getElementById('container-outline').addEventListener('click', () => {
            sessionStorage.setItem(STASH, JSON.stringify({
                name: document.getElementById('container-name').value,
                from:  document.getElementById('container-filter-from').value,
                to:  document.getElementById('container-filter-to').value,
                // The stash speaks the same English as everything else; the map writes into
                // the same object (map.containerFilter in map.js).
                rules: [...rules.querySelectorAll('.ct-rule')].map((z) => ({
                    type:  z.querySelector('select').value,
                    text: z.querySelector('input').value,
                })).filter((r) => r.text.trim() !== ''),
                corners: cornersField.value ? JSON.parse(cornersField.value) : null,
            }));
            location.href = (window.START_DATA?.baseUrl ?? '') + 'index.php?window=filter-area';
        });
        areaDiscard.addEventListener('click', () => showArea(null));

        // Return from the map: read in the state and open the window right
        // back up — the user was in the middle of creating.
        let stash = null;
        try { stash = JSON.parse(sessionStorage.getItem(STASH)); } catch { /* then just not */ }
        if (stash) {
            document.getElementById('container-name').value        = stash.name ?? '';
            document.getElementById('container-filter-from').value = stash.from ?? '';
            document.getElementById('container-filter-to').value   = stash.to ?? '';
            if (rules && Array.isArray(stash.rules) && stash.rules.length) {
                rules.replaceChildren(...stash.rules.map((r) => ruleRow(r.type, r.text)));
            }
            showArea(stash.corners);
            containerNewWindow.showModal();
        }

        containerNewWindow.querySelector('form').addEventListener('submit', () => sessionStorage.removeItem(STASH));
        document.getElementById('container-new-cancel').addEventListener('click', () => sessionStorage.removeItem(STASH));
    }

    const data = JSON.parse(document.getElementById('container-data').textContent);

    // The admit window: friends and own groups in ONE list, values as in
    // the form field ("f:12" / "g:3") — the processing separates them.
    const admitWindow = document.getElementById('container-admit-window');
    document.getElementById('container-admit-cancel').addEventListener('click', () => admitWindow.close());

    for (const plus of document.querySelectorAll('.ct-aufnehmen')) {
        plus.addEventListener('click', () => {
            const inside = data.container[plus.dataset.container] ?? { insideF: [], insideG: [] };
            const candidates = [
                ...data.friends.filter((f) => !inside.insideF.includes(f.id))
                    .map((f) => ({ value: 'f:' + f.id, name: f.name })),
                ...data.groups.filter((g) => !inside.insideG.includes(g.id))
                    .map((g) => ({ value: 'g:' + g.id, name: g.name + t(' (Gruppe)') })),
            ];

            document.getElementById('container-admit-title').textContent = t('In „%s“ aufnehmen', plus.dataset.name);
            document.getElementById('container-admit-container').value = plus.dataset.container;

            const list = document.getElementById('container-admit-candidates');
            if (!candidates.length) {
                list.replaceChildren(hint(data.friends.length || data.groups.length
                    ? t('Alle deine Freunde und Gruppen sind schon in dieser Freigabe.')
                    : t('Du hast noch keine Freunde — erst anfragen, dann aufnehmen.')));
            } else {
                list.replaceChildren(...candidates.map((k) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'suggestion';
                    button.textContent = k.name;
                    button.addEventListener('click', () => {
                        document.getElementById('container-admit-who').value = k.value;
                        admitWindow.querySelector('form').requestSubmit();
                    });
                    return button;
                }));
            }
            admitWindow.showModal();
        });
    }

    // The mount window: one's own lists in tree order with their symbol,
    // what already hangs hidden. WITH CHECKBOXES instead of instant click:
    // whoever stocks a mapping survey often mounts several lists at once,
    // and one separate trip through the window per list would be busywork.
    const mountWindow = document.getElementById('container-mount-window');
    const mountOk     = document.getElementById('container-mount-ok');
    document.getElementById('container-mount-cancel').addEventListener('click', () => mountWindow.close());

    // On submit the checkmarks move into the one lists field — the server
    // gets a comma list, as with Organisieren too.
    mountWindow.querySelector('form').addEventListener('submit', (e) => {
        const chosen = [...mountWindow.querySelectorAll('.ct-hang-haken:checked')]
            .map((b) => b.value);
        if (chosen.length === 0) {
            e.preventDefault();
            return;
        }
        document.getElementById('container-mount-selected').value = chosen.join(',');
    });

    for (const plus of document.querySelectorAll('.ct-einhaengen')) {
        plus.addEventListener('click', () => {
            const inside = data.container[plus.dataset.container]?.myLists ?? [];
            const choice = data.lists.filter((l) => !inside.includes(l.id));

            document.getElementById('container-mount-title').textContent = t('In „%s“ einhängen', plus.dataset.name);
            document.getElementById('container-mount-container').value = plus.dataset.container;
            mountOk.disabled = true;

            const list = document.getElementById('container-mount-lists');
            if (!choice.length) {
                list.replaceChildren(hint(data.lists.length
                    ? t('Alle deine Listen hängen schon in dieser Freigabe.')
                    : t('Du hast noch keine Listen.')));
            } else {
                list.replaceChildren(...choice.map((l) => {
                    const row = document.createElement('label');
                    row.className = 'suggestion';
                    // The indent per tree level sits as padding BEFORE the
                    // checkbox — it used to be a "— " prefix in the name,
                    // but that would now stand between symbol and name.
                    if (l.depth > 0) {
                        row.style.paddingLeft = `calc(.5rem + ${l.depth} * .9rem)`;
                    }

                    const check = document.createElement('input');
                    check.type = 'checkbox';
                    check.className = 'ct-hang-haken';
                    check.value = l.id;
                    check.addEventListener('change', () => {
                        mountOk.disabled = !mountWindow.querySelector('.ct-hang-haken:checked');
                    });
                    row.append(check);

                    // The list's symbol, as in the sidebar and on the tiles'
                    // mounted lists — here you are choosing precisely WHICH
                    // list to share, and you recognize it by shape and color
                    // faster than by name.
                    row.insertAdjacentHTML('beforeend', shapePoint(l.color, l.symbol));
                    row.append(l.name);
                    if (l.auto) {
                        // Nothing tacit when sharing: an auto list grows
                        // with its filter — whoever mounts it should read
                        // that HERE, not notice it later.
                        const auto = document.createElement('span');
                        auto.className = 'item-data';
                        auto.textContent = t('· automatisch — wächst mit ihrem Filter');
                        row.append(auto);
                    }
                    return row;
                }));
            }
            mountWindow.showModal();
            // Take the focus off the first checkbox — its ring would look
            // like a preselection (see tabindex on the window in the
            // markup).
            mountWindow.focus();
        });
    }
}

function hint(text) {
    const p = document.createElement('p');
    p.className = 'hint-text';
    p.textContent = text;
    return p;
}
