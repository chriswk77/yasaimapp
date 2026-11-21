/**
 * The icon library, browser side.
 *
 * THE DRAWINGS ARE NOT HERE — they live in includes/icon.php, once for
 * the whole project, and come into the document via a <template> block
 * (icon_stock()). This module fetches them from there. A second copy
 * of the path data here would be exactly the state the library set out
 * against: The map sheet once stood three times in the project, and the
 * two clocks had already drifted apart.
 *
 * Two ways out, because both are needed:
 *
 *   icon(name, opt)    an element for appending
 *   iconHtml(name, opt)  the same content as text, for innerHTML and
 *                           template strings
 *
 * Options: { class, title } — 'title' makes the icon visible to screen
 * readers; without it, it stays aria-hidden, because almost always the
 * button around it is already labeled.
 *
 * NO SIZE: That comes from the stylesheet and there from the font of the
 * row — '.icon' carries it on every one by itself, deviating places
 * set it via their own class. The header of includes/icon.php explains
 * why.
 */

/**
 * Fetch from the stock and dress up.
 *
 * ONLY LOOKED UP ON FIRST CALL and then remembered: The module is loaded
 * before anyone needs an icon, and one querySelector per list row would,
 * at three hundred rows, be three hundred searches through the whole
 * document.
 */
const stock = new Map();

function fromStock(name) {
    if (!stock.has(name)) {
        const t = document.querySelector(`#icon-stock template[data-icon="${name}"]`);
        if (!t) throw new Error('Unknown icon: ' + name);
        stock.set(name, t.content.firstElementChild);
    }
    return stock.get(name);
}

export function icon(name, { class: cssClass = '', title = '' } = {}) {
    const svg = fromStock(name).cloneNode(true);

    // In addition, not instead: '.icon' brings the base size along, and
    // without it an SVG would be as big as it wants to be on its own.
    if (cssClass) svg.setAttribute('class', 'icon ' + cssClass);
    if (title) {
        // From mute to speaking icon: aria-hidden has to go for that,
        // otherwise it overrules the title and the screen reader stays
        // silent.
        svg.removeAttribute('aria-hidden');
        svg.setAttribute('role', 'img');
        const t = document.createElementNS('http://www.w3.org/2000/svg', 'title');
        t.textContent = title;
        svg.prepend(t);
    }
    return svg;
}

export function iconHtml(name, options) {
    return icon(name, options).outerHTML;
}
