/**
 * The live preview in Verwaltung → Einstellungen → Fotos → Messen
 * (26.09.2026: „dass man so eine Live-Vorschau hat"): a
 * measuring line with its length and the scale bar, on a light, a grey
 * and a dark ground — neither pure white nor pure black, because no
 * photograph is — redrawn on every change of colour, edge or size, before
 * anything is saved.
 *
 * NOTHING HERE DRAWS ITS OWN WAY. The line, its dots and its label come
 * from measuredraw.js, the builder the photo window draws with; the bar
 * is the photo window's own markup, dressed by .photo-scalebar in app.css.
 * Whatever changes there shows here.
 */

import * as measureDraw from './measuredraw.js';

/* Self-attaching with a watcher, like calibrate.js: the administration
   renders its tab panels lazily, so the form does not exist when this
   module runs. */
function setUp(form) {
    if (form.dataset.previewBound) return;
    form.dataset.previewBound = '1';
    const tiles = [...form.querySelectorAll('.measure-tile')];
    const value = (name) => form.elements[name]?.value ?? '';

    function draw() {
        // What the server would store — out-of-range sizes are refused
        // there, the preview shows the nearest allowed one meanwhile.
        const font = Math.min(36, Math.max(8, Number(value('font_px')) || 16));
        const style = {
            scale_color: value('scale_color'), scale_seam_color: value('scale_seam_color'),
            line_color: value('line_color'), seam_color: value('seam_color'),
        };
        for (const tile of tiles) {
            const { width: w, height: h } = tile.getBoundingClientRect();
            // A hidden tab has no size yet; the observer below draws
            // as soon as it opens.
            if (!w) continue;
            // A fixed, gentle slope: stretched to the tile, the line
            // stood nearly upright in a phone's narrow tiles, and so did
            // its label.
            const length = Math.min(w * 0.72, 130);
            const x1 = w * 0.12, y1 = h * 0.6;
            const x2 = x1 + length * Math.cos(0.42), y2 = y1 - length * Math.sin(0.42);
            // Numbers as the photo window writes them — with the German
            // comma, whatever the language (formatUm in map.js).
            tile.innerHTML = '<svg>'
                + measureDraw.seamed('line', `x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"`, style)
                + measureDraw.mark(x1, y1, style) + measureDraw.mark(x2, y2, style)
                + measureDraw.edgeLabel(x1, y1, x2, y2, '12,4 µm', font, style)
                + '</svg>'
                + `<div class="photo-scalebar" style="color: ${style.scale_color};`
                + ` font-size: ${Math.round(font * 0.9)}px; --scalebar-seam: ${style.scale_seam_color}">`
                + `<span class="photo-scalebar-line" style="width: 56px; background: ${style.scale_color}"></span>`
                + '<span>10 µm</span></div>';
        }
    }

    form.addEventListener('input', draw);
    // Drawn in the tiles' own pixels, 1:1 like the photo — so again on
    // every change of width, and first when the hidden tab opens.
    new ResizeObserver(draw).observe(form.querySelector('.measure-preview'));
    draw();
}

const present = document.getElementById('measure-look-form');
if (present !== null) setUp(present);
new MutationObserver(() => {
    const form = document.getElementById('measure-look-form');
    if (form !== null) setUp(form);
}).observe(document.body, { childList: true, subtree: true });
