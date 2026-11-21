/**
 * Words or signs — a row of buttons that share its width shows their signs
 * and words while they fit, the words alone while those fit, and the signs
 * alone once one word does not.
 *
 * ONE ROW, NEVER TWO (14.11.2026, first for „Hochladen /
 * Mikroskop" over the Fundbrowser's picture rail, the same evening for
 * „Angleichen / Kopieren / Entfernen" in „Ausbessern"). The buttons stand
 * flex 1 1 0 with min-width 0 and overflow hidden (each place's
 * stylesheet), so a word too long for its share overflows instead of
 * wrapping — and that overflow is what is measured here.
 *
 * MEASURED, NOT A CONTAINER QUERY: where the words stop fitting depends on
 * how many buttons are there (the microscope's appears only with a camera)
 * and on the language. So the observer takes is-signs off, asks whether a
 * button overflows, and puts it back if one does — inside the observer,
 * before the browser paints, so nothing flickers. The buttons are watched
 * too: one appearing halves the others' share without the row changing
 * size. The stylesheet decides what is-words and is-signs hide.
 *
 * TRAP FOR WHOEVER TESTS IT: an observer fires only where the page is drawn.
 * In a hidden browser pane nothing happens until something forces a frame
 * (a screenshot does).
 *
 * @param {HTMLElement} row     the row; its direct `.button` children count
 * @param {() => void} [after]  called after every measure (the Fundbrowser
 *                              hands its rail the row's height there)
 */
export function wordsOrSigns(row, after) {
    /* THREE STEPS, NOT TWO: sign and word, then THE WORD ALONE (is-words),
       then the sign alone (is-signs). Measured in „Ausbessern" the same
       evening: at the sidebar's usual width the three words with their
       signs missed by 14 pixels, the words alone fitted with room to spare
       — and a word says more than a sign, so it is the sign that goes
       first. */
    const tight = () => [...row.querySelectorAll(':scope > .button')]
        .some((b) => b.offsetWidth && b.scrollWidth > b.clientWidth);
    const fit = () => {
        row.classList.remove('is-words', 'is-signs');
        if (tight()) {
            row.classList.add('is-words');
            if (tight()) row.classList.replace('is-words', 'is-signs');
        }
        after?.();
    };
    if (!('ResizeObserver' in window)) return;
    const watch = new ResizeObserver(fit);
    watch.observe(row);
    for (const b of row.querySelectorAll(':scope > .button')) watch.observe(b);
}
