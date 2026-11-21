/**
 * Foldable page sections that remember their state.
 *
 * A <details class="section-fold"> with an id keeps open or closed across
 * visits — in the settings bundle, like everything that outlives a visit
 * (settings.js). Whoever opened the password block yesterday finds it
 * open today; whoever never touches it keeps a tab that says in two lines
 * what can be done here.
 *
 * THE ID IS THE KEY, and it is required. A section without one folds
 * perfectly well, it just forgets — better than a key derived from the
 * heading text, which would silently lose the state the day somebody
 * rewords the heading.
 *
 * THE MARKUP DECIDES THE FIRST TIME. Whatever the page ships with stands
 * until somebody folds it themselves: an <details open> stays open on the
 * first visit. So a section that has a reason to be open initially — the
 * cloud instructions before the setup — keeps that reason, and this file
 * needs to know nothing about it.
 *
 * It attaches itself, like autoheight.js and toast.js; nothing to call.
 */

import { settings } from './settings.js';

const KEY = 'folds';

function stored() {
    return settings.get(KEY, {}) || {};
}

function remember(id, open) {
    // A COPY, not the live object — see settings.get(): changing the
    // stored object in place makes set() compare it against itself, find
    // nothing changed, and write nothing.
    const all = { ...stored() };
    all[id] = open;
    settings.set(KEY, all);
}

function attach(el) {
    if (!el.id || el.dataset.foldBound === '1') return;
    el.dataset.foldBound = '1';

    const state = stored()[el.id];
    if (typeof state === 'boolean') el.open = state;

    el.addEventListener('toggle', () => remember(el.id, el.open));
}

function attachAll(root = document) {
    for (const el of root.querySelectorAll('details.section-fold')) attach(el);
}

attachAll();

/* Sections that a script builds later are caught too — the administration
   draws whole tabs from JavaScript, and one of them will grow a folding
   section sooner or later. The observer costs nothing while nothing
   happens. */
new MutationObserver((records) => {
    for (const record of records) {
        for (const node of record.addedNodes) {
            if (node.nodeType !== 1) continue;
            if (node.matches?.('details.section-fold')) attach(node);
            attachAll(node);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });
