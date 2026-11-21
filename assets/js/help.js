import { t } from './i18n.js';

/**
 * The help's contents column marks the section under the eye.
 *
 * ONE OBSERVER, NO SCROLL HANDLER: every section heading reports when it
 * enters the upper part of the window, the last one that did is the
 * current section, and its entry in the contents is marked. Cheap, and
 * it also follows a jump through a link. Without the contents column —
 * on the phone, where the list stands above the text — nothing is done.
 */
const toc = document.querySelector('.help-toc');
if (toc) {
    const links = new Map();
    for (const a of toc.querySelectorAll('a[href^="#"]')) {
        links.set(a.getAttribute('href').slice(1), a);
    }
    const headings = [...document.querySelectorAll('.help-mantra > h2, .help-section > h2, .help-section > h3, .help-section > h4, .help-section > h5, .help-section > h6')]
        .map(h => { if (!h.id) h.id = h.parentElement.id; return h; })
        .filter(h => links.has(h.id));
    let current = null;
    const mark = (id) => {
        if (id === current) return;
        links.get(current)?.classList.remove('is-active');
        links.get(id)?.classList.add('is-active');
        current = id;
        // Keep the marked entry in view inside the column, without moving the page.
        const a = links.get(id);
        if (a) {
            const r = a.getBoundingClientRect(), t = toc.getBoundingClientRect();
            if (r.top < t.top || r.bottom > t.bottom) a.scrollIntoView({ block: 'nearest' });
        }
    };
    const seen = new Map();
    const observer = new IntersectionObserver((entries) => {
        for (const e of entries) seen.set(e.target.id, e.isIntersecting);
        // The topmost heading within the upper window wins; if none is
        // there, the last one that scrolled past above.
        let pick = null;
        for (const h of headings) {
            if (seen.get(h.id)) { pick = h.id; break; }
        }
        if (pick === null) {
            for (const h of headings) {
                if (h.getBoundingClientRect().top < 0) pick = h.id;
            }
        }
        if (pick !== null) mark(pick);
    }, { rootMargin: '0px 0px -60% 0px', threshold: 0 });
    for (const h of headings) observer.observe(h);
}

/* A WAY BACK AT EVERY JUMP (16.09.2026: „jeder Sprungpunkt in
   die Verwaltung oder sonst wohin braucht einen Link, um wieder
   zurückzukommen — einen Rücksprungpunkt an der entgegengesetzten
   Stelle"). Whoever follows a cross reference in the text lands under
   the target heading with a line „zurück zu 1.6.2.1 Listen" that leads
   to the section the link stood in. The browser's own back button does
   the same, but nobody looks for it in the middle of a page. Only for
   links inside the text — the contents column is always in view. */
(() => {
    const body = document.querySelector('.help-body');
    if (!body) return;
    let back = null;
    const sectionOf = (el) => el.closest('.help-section')?.querySelector(':scope > h2, :scope > h3, :scope > h4, :scope > h5, :scope > h6') ?? null;
    body.addEventListener('click', (e) => {
        const a = e.target.closest('a[href^="#"]');
        if (!a || a.classList.contains('help-back')) return;
        const from = sectionOf(a);
        const to = document.getElementById(a.getAttribute('href').slice(1));
        if (!from || !to || from.id === to.id) return;
        const heading = to.matches('h2, h3, h4, h5, h6') ? to : to.querySelector(':scope > h2, :scope > h3, :scope > h4, :scope > h5, :scope > h6');
        if (!heading) return;
        back?.remove();
        back = document.createElement('a');
        back.className = 'help-back';
        back.href = '#' + from.id;
        back.textContent = '\u21a9 ' + t('zurück zu %s', from.textContent.trim());
        heading.after(back);
        back.addEventListener('click', () => { back.remove(); back = null; });
    });
})();
