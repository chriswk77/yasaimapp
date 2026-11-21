/**
 * What is REALLY visible — as CSS variables, on every page.
 *
 * ON THE PHONE THE KEYBOARD SLIDES OVER THE BOTTOM EDGE without the
 * layout viewport getting any smaller: 100dvh stays 100dvh, and a save
 * button anchored at the bottom sits underneath it, unreachable. What is
 * actually visible only visualViewport knows — hence --view-height, and
 * trap 3 in CLAUDE.md says the same for anyone who reaches for dvh.
 *
 * offsetTop comes along because the visible viewport can also be SHIFTED
 * — for instance when the browser scrolls down by itself while typing
 * into a field, which is exactly what a tablet does when its autofill
 * bar appears above the keyboard (the maintainer's Xiaomi, 24.08.2026).
 *
 * IT LIVED IN map.js UNTIL TODAY, and therefore only the map knew its
 * own height: the full-screen windows of the administration — creating
 * an excursion, editing it — kept measuring in dvh and pushed their
 * footers under the keyboard. A module of its own, attached once in
 * page_footer() like autoheight.js and toast.js, is the way this project
 * gives every page the same behaviour without a second copy.
 */

function report() {
    const v = window.visualViewport;
    if (!v) return;
    const root = document.documentElement;
    root.style.setProperty('--view-height', v.height + 'px');
    root.style.setProperty('--view-top', v.offsetTop + 'px');
}

if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', report);
    window.visualViewport.addEventListener('scroll', report);
    report();
}
