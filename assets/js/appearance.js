/**
 * Theme and accent — the wiring, ONCE, for everyone who may choose.
 *
 * The browser's twin of includes/appearance.php: that one draws the
 * tiles, this one makes them work. Since the excursion guest may choose
 * too (24.08.2026) there are two places, and two copies of
 * this would be two places to forget a retired name in.
 *
 * IT TAKES EFFECT IMMEDIATELY, not only after a reload: the value goes
 * into the settings bundle (which the preamble in page.php and index.php
 * reads before the first paint) and at the same moment onto <html>.
 */

import { settings } from './settings.js';

/* CARRY OLD VALUES OVER: until 15.08.2026 the theme was only called
   'dunkel' or 'hell'; on 22.08.2026 Papier and Mitternacht were dropped,
   four accents renamed with their colours, and the German value names
   moved to English with the rest of the identifiers. The same maps stand
   in the preamble of page.php and index.php, because reading happens
   there first — and here they additionally HEAL the storage: what is
   applied gets written back, so the old value disappears after one visit
   instead of being translated forever. */
const THEME_RETIRED  = { dunkel: 'charcoal', hell: 'warmlight',
                         papier: 'warmlight', mitternacht: 'charcoal',
                         kohle: 'charcoal', warmlicht: 'warmlight',
                         bernstein: 'amber', schiefer: 'slate' };
const ACCENT_RETIRED = { gold: 'orange', pink: 'purple',
                         moosgruen: 'steelblue', jade: 'red',
                         stahlblau: 'steelblue', rot: 'red',
                         minze: 'mint', lila: 'purple' };

/**
 * Bind one picker pair — the elements appearance_picker() draws.
 *
 * @param {object} o
 * @param {string} o.themeDefault   THEME_DEFAULT of the server
 * @param {string} o.accentDefault  ACCENT_DEFAULT of the server
 */
export function wireAppearance({ themeDefault, accentDefault }) {
    bind({
        box: document.getElementById('theme-selection'),
        key: 'theme',
        fallback: themeDefault,
        retired: THEME_RETIRED,
        attribute: 'theme',
    });
    bind({
        box: document.getElementById('accent-selection'),
        key: 'accent',
        fallback: accentDefault,
        retired: ACCENT_RETIRED,
        attribute: 'accent',
    });
}

function bind({ box, key, fallback, retired, attribute }) {
    if (!box) return;

    const stored  = settings.get(key, fallback);
    const current = retired[stored] ?? stored;
    if (current !== stored) settings.set(key, current);

    /* WITH ?. AND NOT WITHOUT. A value that fits no tile is not
       impossible — a theme retired later, a bundle from another device, a
       typo in the storage. Without the question mark this line throws,
       and everything after it stops: that is exactly how the
       administration went down on 20.08.2026, and it is one character to
       prevent. */
    const field = box.querySelector(`input[value="${current}"]`);
    if (field) field.checked = true;

    box.addEventListener('change', (e) => {
        const value = e.target.value;
        settings.set(key, value);
        // The default is the ABSENCE of the attribute — see app.css,
        // where the bare :root carries it.
        if (value === fallback) delete document.documentElement.dataset[attribute];
        else document.documentElement.dataset[attribute] = value;
    });
}
