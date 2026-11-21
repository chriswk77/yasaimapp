/**
 * The marker shapes, browser side.
 *
 * THE DRAWINGS ARE NOT HERE — they live in includes/shapes.php, once for
 * the whole project, and come into the document via a <template> block
 * (shape_stock()). This module fetches them from there, exactly as
 * icon.js does with the icon library. A second copy of the path data
 * here would be the very state both libraries set out against: With the
 * icons, two clocks had already drifted apart before anyone noticed.
 *
 * Until 14.08.2026 they stood as FORMEN in map.js. They moved because
 * the containers in the administration now show their attached lists with
 * those lists' own symbol — and that is rendered by PHP, not the map.
 *
 * WHAT STAYED IN map.js: FORM_ANKER (where the point really lies under
 * the figure — that is Leaflet's business) and FORMNAMEN (the labels of
 * the picker). Both are map, not drawing.
 */

/**
 * Fetch from the stock and remember — looked up only on first call.
 *
 * Without the block, the page is one without a map and without list rows;
 * then there is nothing to draw, and an empty stock is no error. An
 * unknown shape falls back silently to the circle, and for the same
 * reason as in form_geometrie() over there: The name lives in the DATA
 * and can come from a device that knows a shape that does not exist here.
 */
const stock = new Map();
let wasRead = false;

function readStock() {
    if (wasRead) return;
    for (const t of document.querySelectorAll('#shape-stock template[data-form]')) {
        stock.set(t.dataset.form, t.content.firstElementChild.innerHTML);
    }
    // ONLY REMEMBERED ONCE SOMETHING WAS THERE. A module does run only
    // after the document is parsed, but FORMREIHE in map.js reads at
    // load time already — should the block ever come later, a flag stuck
    // on "read" would keep the stock empty forever, and every list would
    // be a circle.
    wasRead = stock.size > 0;
}

/* The inside of the SVG mark for a shape — without color, without size,
   without border. (The mark is not spelled out here: tests/icon.php
   counts every occurrence as its own drawing, even in a comment.) */
export function shapeGeometry(shape = 'circle') {
    readStock();
    return stock.get(shape) ?? stock.get('circle') ?? '';
}

/** All shapes there are — for the picker in the list window. */
export function allShapes() {
    readStock();
    return stock;
}

/**
 * The small shape symbol for a row — the counterpart to form_punkt() in
 * includes/shapes.php. Both build the same markup.
 *
 * WITHOUT FIXED DIMENSIONS: The size hangs on the CSS and there on the
 * font size of the row the symbol stands in. Before, 11 pixels stood here,
 * and next to it stood text in 14.7 — the symbol was three quarters the
 * size of its own label.
 */
export function shapePoint(color, shape = 'circle') {
    return `<span class="color-point"><svg viewBox="0 0 24 24" aria-hidden="true">`
         + `<g fill="${color || '#8a97a4'}">${shapeGeometry(shape)}</g></svg></span>`;
}
