/**
 * The tick box at the front of a row — the sidebar's (map.js) and
 * Organisieren's (organize.js), ONE drawing for both (moved out of map.js
 * on 28.09.2026, when Organisieren took the boxes over; its styles moved
 * from map.css to app.css with it, the administration loads only that).
 */

import { icon } from './icons.js';

/**
 * THE TICK BOX AT THE FRONT OF EVERY ROW (24. and 25.09.2026).
 *
 * UNTIL HERE THE ROW ITSELF SAID „ON": a lighter ground and a hairline of
 * the accent round it. But a lighter ground also meant „a branch begins
 * here" (the heading lists' own tone) and „under the pointer", so what was
 * on had to be told from the rest by shades of one colour. The box says it
 * without comparing, in the one place the eye looks for it — and it says
 * the third thing a row never could: SOME of the branch below is on.
 *
 * ON A HEADING IT SWITCHES THE WHOLE BRANCH: every leaf below on, or, when
 * all of them already are, every one off. The heading's NAME keeps folding
 * — a heading cannot be chosen, so its big target stays the fold, and the
 * box is the one small place that acts on the branch as a whole.
 *
 * A BUTTON WITH role=checkbox AND NOT AN <input>: the third state needs
 * aria-checked="mixed", the drawings come from the icon library, and a
 * button is what foldOnRowClick already lets through untouched.
 *
 * @param {'on'|'some'|'off'} tick
 * @param {string} name   the row's list, for the screen reader
 * @param {string} title  what a click does, for the pointer
 * @param {?function} onToggle  null where there is nothing to switch — a
 *        heading whose branch holds no leaf the sidebar shows
 */
export function tickBox(tick, name, title, onToggle) {
    const box = document.createElement('button');
    box.type = 'button';
    box.className = 'list-row-check is-' + tick;
    box.setAttribute('role', 'checkbox');
    box.setAttribute('aria-checked', tick === 'on' ? 'true' : tick === 'some' ? 'mixed' : 'false');
    box.setAttribute('aria-label', name);
    box.title = title;

    const square = document.createElement('span');
    square.className = 'list-row-box';
    if (tick === 'on') square.append(icon('check'));
    if (tick === 'some') square.append(icon('minus'));
    box.append(square);

    if (onToggle) {
        box.addEventListener('click', (e) => {
            e.stopPropagation();
            onToggle();
        });
    } else {
        box.disabled = true;
    }
    return box;
}

/** How much of a branch is on — the three states of its heading's box. */
export function tickOf(chosen, leaves) {
    const on = leaves.filter(id => chosen.has(id)).length;
    return on > 0 && on === leaves.length ? 'on' : on > 0 ? 'some' : 'off';
}
