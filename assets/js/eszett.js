/**
 * THE CAPITAL SHARP S where the stylesheet sets capitals
 * (07.10.2026). A heading with `text-transform: uppercase` turns „ß" into
 * „SS" — Unicode's default, and CSS knows no switch for it — so the
 * statistics' „Teilmaße" read as „TEILMASSE", and a subject or a layer
 * named by the user fares the same in every capital heading: the plate's
 * strip, its side, the gallery's groups, the tables' heads.
 *
 * German has a capital of its own since 2017, „ẞ" (U+1E9E), and a text
 * node that holds it stays as it is under uppercase. So this module
 * watches the document and, in every text node that holds a „ß" AND
 * stands in an element the stylesheet sets in capitals, writes „ẞ"
 * instead. Nothing to register: it attaches itself, on every page — via
 * page_footer() and via a side-effect import in map.js, the same two
 * paths as autoheight.js.
 *
 * WHAT IT COSTS: a text node without „ß" is passed by one string search;
 * the computed style is asked only for the rare one that holds it.
 *
 * THE TRAP IT SETS: the node's text really changes. Whoever copies the
 * words of a capital heading into a place without capitals carries the
 * „ẞ" along — tables.js, which hands each column's heading to its cells
 * for the phone, turns it back. A heading that becomes uppercase only
 * later (a class added, a container query) is not looked at again.
 */

const SMALL = 'ß';
const CAPITAL = 'ẞ';

function fix(text) {
    if (!text.data.includes(SMALL)) return;
    const el = text.parentElement;
    if (!el || getComputedStyle(el).textTransform !== 'uppercase') return;
    text.data = text.data.replaceAll(SMALL, CAPITAL);
}

function scan(root) {
    if (root.nodeType === Node.TEXT_NODE) {
        fix(root);
        return;
    }
    if (root.nodeType !== Node.ELEMENT_NODE || !root.textContent.includes(SMALL)) return;
    const walk = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    for (let node = walk.nextNode(); node; node = walk.nextNode()) fix(node);
}

scan(document.body);
// Writing the capital changes the node again — but then without „ß", so
// the second round ends at the string search.
new MutationObserver((records) => {
    for (const record of records) {
        if (record.type === 'characterData') fix(record.target);
        else for (const node of record.addedNodes) scan(node);
    }
}).observe(document.body, { childList: true, subtree: true, characterData: true });
