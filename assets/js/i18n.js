/**
 * Languages in the browser — the twin of includes/i18n.php.
 *
 * SAME IDEA, SAME KEY: the German sentence in the code is the catalog
 * key, t() without a catalog is the identity, and a missing translation
 * falls back to German instead of breaking anything. The catalog rides
 * in on the page — the map puts it into START_DATA.lang_catalog,
 * page_footer() gives every other page window.LANG_DATA — and it is
 * simply ABSENT for German, which keeps the German app at zero cost and
 * zero risk. Offline the map serves its cached page, catalog included.
 *
 * Placeholders are %s/%d filled in order; a translation that needs to
 * reorder writes %1$s style indices.
 */

/* globalThis.window, not bare window: modules that import t() (queue.js,
   coordinates.js, timerange.js …) are also loaded by the Node tests, and
   there a bare `window` is a ReferenceError before the first check runs.
   In the browser globalThis.window IS window — same reading, no cost. */
const catalog = globalThis.window?.START_DATA?.lang_catalog
    ?? globalThis.window?.LANG_DATA ?? {};

export function t(text, ...args) {
    let out = catalog[text] ?? text;
    if (args.length === 0) return out;
    let auto = 0;
    /* „%%" IS ONE PERCENT SIGN, as sprintf has it on the PHP side — the two
       sides share their catalogs, so a sentence carries the same escapes
       in both. Without this a scale's „auf 18 %% verkleinert" stood on
       screen with both signs (30.10.2026). */
    return out.replace(/%%|%(?:(\d+)\$)?[sd]/g, (whole, index) =>
        (whole === '%%' ? '%' : String(args[index ? Number(index) - 1 : auto++] ?? '')));
}

/** The language the page was delivered in — for markup decisions. */
export const lang = globalThis.document?.documentElement?.lang || 'de';
