/**
 * Tables on the phone: every row becomes a block.
 *
 * WHY THIS IS NOT PURE CSS. Below 860 px a table with five columns has no
 * honest layout: 320 px of screen against 540 px of table means scrolling
 * sideways, and a value whose column heading has scrolled out of sight
 * says nothing. So the row is stacked instead — but then every value needs
 * to carry the heading it lost. CSS cannot reach from a cell to its column
 * heading; this hands it over as a custom property, and the stylesheet
 * prints it via content: var(--label).
 *
 * WHY NOT data-label IN THE MARKUP. That is the usual recipe, and it would
 * mean touching seventeen files and remembering it in the eighteenth. Here
 * every table is served, including the ones a script builds later — the
 * observer sees those too, exactly as autoheight.js does for textareas.
 *
 * A TABLE THAT DOES NOT WANT THIS says so with class="no-stack": tables of
 * two short columns read fine as a table even on the phone, and stacking
 * them would double their height for nothing.
 */

/** Copy every column heading into its cells, as --label. */
function labelCells(table) {
    // A capital heading may already carry „ẞ" (eszett.js); the cells' label is not set in capitals.
    const headings = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim().replaceAll('ẞ', 'ß'));
    if (headings.length === 0) return;

    for (const row of table.querySelectorAll('tbody tr')) {
        // colspan rows (the "… and 37 more" line) are not records — they
        // carry no heading and must not get one.
        const cells = row.children;
        if (cells.length !== headings.length) continue;

        for (let i = 0; i < cells.length; i++) {
            const text = headings[i];
            // An empty heading means the column has none (checkbox, buttons).
            // Then the cell stays without a label instead of getting a
            // colon in front of nothing.
            if (text === '') {
                cells[i].style.removeProperty('--label');
                cells[i].classList.add('cell-without-label');
            } else {
                cells[i].style.setProperty('--label', JSON.stringify(text));
                cells[i].classList.remove('cell-without-label');
            }
        }
    }
}

function labelAll(root = document) {
    for (const t of root.querySelectorAll('table.list:not(.no-stack)')) labelCells(t);
}

labelAll();

/* Tables that a script builds — Organisieren redraws its whole table on every
   filter change. Watching the document costs nothing and spares every
   call site from having to remember. */
new MutationObserver((records) => {
    for (const r of records) {
        for (const node of r.addedNodes) {
            if (node.nodeType !== 1) continue;
            if (node.matches?.('table.list:not(.no-stack)')) labelCells(node);
            else labelAll(node);
            // A redrawn tbody arrives as a child of an existing table.
            const table = node.closest?.('table.list:not(.no-stack)');
            if (table) labelCells(table);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });
