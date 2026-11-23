/**
 * THE WRITING ON A PICTURE, AS A PLATE DRAWS IT (08.10.2026:
 * „Wir haben ja schon im Bildbetrachter ein super Text-Tool … ebenso ein
 * Pfeil-Tool … ebenso das Zählrechteck"). The texts, arrows and counting
 * boxes belong to the PICTURE and sit on a measuring layer; a plate cell
 * ticks which layers' writing it shows (cell.notes).
 *
 * ONE DRAWING FOR TWO PLACES, as measuredraw.js is for two others: the
 * screen's cell and the printed page both get the same list of shapes from
 * noteShapes() — in the cell's own pixels — and only the painting differs
 * (noteSvg for the screen, drawNotes for the canvas).
 *
 * WHERE THE WRITING STANDS FOLLOWS THE PICTURE; HOW LARGE IT IS DOES NOT
 * („dass die Schrift dann in der Tafel mit dem Zoom wächst oder
 * schrumpft, halte ich eigentlich für einen Nachteil"). The anchor of a text
 * is a point of the picture, so it moves with the cutout; its letters are
 * the plate's: one size in points for the whole plate (layout.notePt), and
 * the sizes the writer chose in the viewer count as a RATIO to the
 * SMALLEST text the cell shows of that picture — a heading set large stays
 * larger than the labels beside it, the labels themselves come out at the
 * plate's size, and the plate stays one voice. An arrow, though, is
 * a drawing and grows with the picture, as in the viewer; a counting box
 * stands for a real size and keeps it against the plate's scale. AN
 * ARROW'S OWN WORDS go with the arrow (30.10.2026): they grow with the
 * picture too, or a fine arrow on a picture laid in small would carry
 * words at the plate's full size.
 */

import { measureLabel } from './measurestats.js';
import { vocab } from './vocabulary.js';
import { TEXT_LEADING, arrowWeightOf, arrowOutline, arrowTextAt, arrowTextLook, boxLabel } from './measuredraw.js';

export const NOTE_TYPES = ['text', 'arrow', 'box'];
/* THE MEASURES THEMSELVES, drawn on the plate's picture
   (30.10.2026: „möchte ich zum Beispiel eine Sporenmessung zeigen bei einem
   Sporenbild … hat das gleiche Bild aber auch noch Zystiden … könnte ich
   das gleiche Bild noch in die Tafel ziehen und dort die Vermessung der
   Zystide zeigen"). A cell ticks which layers' measures it shows
   (cell.measures) beside which layers' writing (cell.notes); the same
   picture twice on a plate shows one layer here, another there. A cutout
   ('crop') is no measure of a size and stays out — it becomes a plate's
   row, not a line on the picture. */
export const MEASURE_TYPES = ['line', 'path', 'rect'];
// The size step (thousandths of the picture's width) of a text that carries none.
const TEXT_REF = 24;

/** The layers of a picture that carry writing, with what each holds: [{layer, text, arrow, box}]. */
export function noteLayers(pic) {
    const found = new Map();
    for (const m of pic?.measures ?? []) {
        if (!NOTE_TYPES.includes(m.type)) continue;
        const layer = measureLabel(m);
        if (!found.has(layer)) found.set(layer, { layer, text: 0, arrow: 0, box: 0 });
        found.get(layer)[m.type]++;
    }
    return [...found.values()].sort((a, b) => a.layer.localeCompare(b.layer, 'de'));
}

/** The layers of a picture that carry measures to draw, by name, sorted — by the word one reads (vocabulary.js). */
export function measureLayers(pic) {
    const found = new Set();
    for (const m of pic?.measures ?? []) if (MEASURE_TYPES.includes(m.type)) found.add(measureLabel(m));
    return [...found].sort((a, b) => vocab(a).localeCompare(vocab(b), 'de'));
}

/** µm for people, as the photo window writes them („84,2 µm", „1,3 mm"). */
function formatUm(um) {
    const say = (v, unit, digits) => v.toLocaleString('de-DE', { maximumFractionDigits: digits }) + ' ' + unit;
    if (um < 1000) return say(um, 'µm', um < 100 ? 1 : 0);
    if (um < 10000) return say(um / 1000, 'mm', 2);
    if (um < 1000000) return say(um / 10000, 'cm', 1);
    return say(um / 1000000, 'm', 2);
}

/** A number along an edge a→b, pushed to the side (awayX, awayY) points to,
 *  upright to read — never standing on its head. */
function edgeLabel(a, b, text, px, awayX, awayY) {
    const dx = b[0] - a[0], dy = b[1] - a[1];
    const len = Math.hypot(dx, dy) || 1;
    let nx = -dy / len, ny = dx / len;
    if (nx * awayX + ny * awayY < 0) { nx = -nx; ny = -ny; }
    let angle = Math.atan2(dy, dx) * 180 / Math.PI;
    if (angle > 90) angle -= 180;
    if (angle < -90) angle += 180;
    const off = px * .75;
    return { kind: 'label', text, px, angle, x: (a[0] + b[0]) / 2 + nx * off, y: (a[1] + b[1]) / 2 + ny * off };
}

/**
 * THE MEASURES OF THE TICKED LAYERS, in the cell's pixels — lines with
 * their seam, a rectangle as the single line the viewer draws, a cutout's
 * frame dashed with its inner measures, and every number in the plate's
 * one size (notePx), as the writing is. Without a scale there is no
 * number to write; the lines still stand.
 */
function measureShapes(pic, layers, geo, umPerPx, notePx) {
    const out = [];
    const um = (v) => (umPerPx > 0 ? formatUm(v * umPerPx) : null);
    const draw = (m, width) => {
        const s = geo.pw / width;
        const pts = m.points.map(([x, y]) => [geo.left + x * s, geo.top + y * s]);
        if (m.type === 'line' && pts.length >= 2) {
            const [a, b] = pts;
            out.push({ kind: 'stroke', pts: [a, b], closed: false, seam: true });
            const text = um(Math.hypot(b[0] - a[0], b[1] - a[1]));
            if (text) out.push(edgeLabel(a, b, text, notePx, 0, -1));
        } else if (m.type === 'path' && pts.length >= 2) {
            let sum = 0;
            for (let i = 1; i < pts.length; i++) sum += Math.hypot(pts[i][0] - pts[i - 1][0], pts[i][1] - pts[i - 1][1]);
            out.push({ kind: 'stroke', pts, closed: false, seam: true });
            const text = um(sum);
            if (text) out.push(edgeLabel(pts[pts.length - 2], pts[pts.length - 1], text, notePx, 0, -1));
        } else if (m.type === 'rect' && pts.length >= 3) {
            const c = rectCorners(pts);
            const cx = (c[0][0] + c[2][0]) / 2, cy = (c[0][1] + c[2][1]) / 2;
            const away = (p, q) => [(p[0] + q[0]) / 2 - cx, (p[1] + q[1]) / 2 - cy];
            if (Array.isArray(m.inner) && m.inner.length > 0) {
                out.push({ kind: 'stroke', pts: c, closed: true, seam: false, dash: true });
                for (const mi of m.inner) if (Array.isArray(mi.points)) draw(mi, width);
                return;
            }
            out.push({ kind: 'stroke', pts: c, closed: true, seam: false });
            const len = um(Math.hypot(c[1][0] - c[0][0], c[1][1] - c[0][1]));
            const breadth = um(Math.hypot(c[2][0] - c[1][0], c[2][1] - c[1][1]));
            if (len) out.push(edgeLabel(c[0], c[1], len, notePx, ...away(c[0], c[1])));
            if (breadth) out.push(edgeLabel(c[1], c[2], breadth, notePx, ...away(c[1], c[2])));
        }
    };
    for (const m of pic.measures ?? []) {
        if (!MEASURE_TYPES.includes(m.type) || !layers.includes(measureLabel(m))) continue;
        const width = m.width || pic.w;
        if (!(width > 0) || !Array.isArray(m.points) || m.points.length < 2) continue;
        draw(m, width);
    }
    return out;
}

/** A rectangle's four corners from its four taps — the length a→b, the
 *  breadth between the offsets of c and d from that line. The photo
 *  window's rectGeometry, line for line: a rect is stored as those taps. */
function rectCorners([a, b, c, d]) {
    const ax = b[0] - a[0], ay = b[1] - a[1];
    const len = Math.hypot(ax, ay) || 1;
    const nx = -ay / len, ny = ax / len;
    const o1 = (c[0] - a[0]) * nx + (c[1] - a[1]) * ny;
    const o2 = ((d ?? c)[0] - a[0]) * nx + ((d ?? c)[1] - a[1]) * ny;
    return [
        [a[0] + nx * o1, a[1] + ny * o1],
        [b[0] + nx * o1, b[1] + ny * o1],
        [b[0] + nx * o2, b[1] + ny * o2],
        [a[0] + nx * o2, a[1] + ny * o2],
    ];
}

/* THE PLATE'S LETTERS — the printed page writes in these (drawNotes), and
   since 30.10.2026 the screen's cell does too (noteSvg), so what one places
   on screen is what is printed. */
const NOTE_FONT = 'Arial, Helvetica, sans-serif';
let measureCtx = null;
/** The widest of the lines, in the plate's letters at `px`. */
function wordsWidth(lines, px, weight = 600) {
    measureCtx ??= document.createElement('canvas').getContext('2d');
    measureCtx.font = `${weight} ${px}px ${NOTE_FONT}`;
    return Math.max(px * .4, ...lines.map(l => measureCtx.measureText(l).width));
}

/**
 * The shapes of the chosen layers, in the cell's pixels.
 *
 * @param {object} pic     the picture (measures, w)
 * @param {string[]} layers the layers ticked on the cell
 * @param {{left:number, top:number, pw:number}} geo where the picture lies in the cell (cutout)
 * @param {?number} umPerPx µm per displayed pixel, or null without a scale (boxes wait)
 * @param {number} notePx   the plate's note size in pixels — what the smallest text shown measures
 */
export function noteShapes(pic, layers, geo, umPerPx, notePx, measured = []) {
    const out = [];
    // A cell that is not laid out (a hidden tab) has no size, and the cutout NaN for it — nothing to place yet.
    if ((!layers?.length && !measured?.length) || !geo || !(geo.pw > 0) || !Number.isFinite(geo.left) || !Number.isFinite(geo.top)) return out;
    // The measures first, so the writing lies over them.
    if (measured?.length) out.push(...measureShapes(pic, measured, geo, umPerPx, notePx));
    layers = layers ?? [];
    /* THE PLATE'S SIZE IS THE SIZE OF THE PLAIN LABEL
       (08.11.2026: „Schriftgröße 12 pt bei der Tafel und 12 pt bei
       Fundangaben sind unterschiedlich groß, die Beschriftung in den
       Tafelbildern ist gefühlt zu groß"). The ratio was taken to a fixed
       step (TEXT_REF, 24 thousandths of the width), but a new text in the
       viewer takes the account's font size at the zoom of the moment —
       measured on the local pictures, 36: every label came out at one and a
       half times the plate's points, 12 pt looked like 18. Now the smallest
       text shown is the unit, so the ordinary label IS the plate's size and
       only what was set larger beside it stays larger. */
    const stepOf = (m) => (m.size != null && (m.width || pic.w) > 0 ? m.size / (m.width || pic.w) * 1000 : TEXT_REF);
    const steps = (pic.measures ?? [])
        .filter((m) => m.type === 'text' && String(m.text ?? '') !== '' && layers.includes(measureLabel(m)))
        .map(stepOf).filter((x) => x > 0);
    const unit = steps.length > 0 ? Math.min(...steps) : TEXT_REF;
    for (const m of pic.measures ?? []) {
        if (!NOTE_TYPES.includes(m.type) || !layers.includes(measureLabel(m))) continue;
        // A measure's points are in the pixels of the picture's width when it was drawn.
        const width = m.width || pic.w;
        if (!(width > 0) || !Array.isArray(m.points) || m.points.length === 0) continue;
        const s = geo.pw / width;
        const at = ([x, y]) => [geo.left + x * s, geo.top + y * s];
        if (m.type === 'text') {
            const text = String(m.text ?? '');
            if (text === '') continue;
            const [x, y] = at(m.points[0]);
            out.push({ kind: 'text', x, y, px: notePx * stepOf(m) / unit, angle: m.angle ?? 0, lines: text.split('\n'), ref: m, per: s });
        } else if (m.type === 'arrow' && m.points.length >= 2) {
            const tail = at(m.points[0]), tip = at(m.points[1]);
            // Thousandths of the WHOLE picture's width, as the viewer draws it — on a cut picture more than the cut's (plateparts.js, picView fullW).
            const g = arrowOutline(tail, tip, arrowWeightOf(m), s * (pic.fullW ?? width) / 1000);
            // Ends, middle and side for the plate's grips (plates.js, noteGrips).
            out.push({ kind: 'arrow', outline: g.outline, ref: m, per: s,
                tail, tip, middle: g.middle, normal: g.n, half: g.half, width });
            /* ITS OWN WORDS (30.10.2026): beside the tail as the viewer puts
               them (measuredraw.js, arrowTextAt), measured in the letters
               the page prints in. Taken, they move the arrow.

               THEIR SIZE FOLLOWS THE PICTURE, LIKE THE ARROW — not the
               plate's one size the other writing keeps (the same
               evening: „in der Tafel finde ich dann doch die Schriftgröße …
               zur Zoom-Stufe wieder etwas zu groß … eine Schriftgröße und
               Pfeildicke ist da doch nicht das Klügste"). Arrow and words
               are one; on a picture laid in small the arrow grew fine and
               its words stayed at the plate's size beside it. Now they keep
               the proportion they have in the viewer, at every cutout. */
            const text = String(m.text ?? '');
            if (text !== '') {
                // Size and weight follow the arrow's strength (arrowTextLook).
                const look = arrowTextLook(m);
                const px = (m.size != null ? m.size : TEXT_REF / 1000 * width) * s * look.scale;
                const lines = text.split('\n');
                const wide = wordsWidth(lines, px, look.weight);
                const w = arrowTextAt(tail, tip, px, lines.length, wide);
                out.push({ kind: 'text', x: w.x, y: w.y, px, angle: 0, lines, anchor: w.anchor,
                    weight: look.weight, thicken: look.thicken * px, ref: m, per: s,
                    // The words' box, for the plate's size grip.
                    words: { left: w.x - wide / 2, top: w.top, right: w.x + wide / 2, bottom: w.top + w.height } });
            }
        } else if (m.type === 'box' && umPerPx > 0 && Array.isArray(m.um)) {
            const [cx, cy] = at(m.points[0]);
            const hw = m.um[0] / umPerPx / 2, hh = m.um[1] / umPerPx / 2;
            const a = (m.angle ?? 0) * Math.PI / 180;
            const cos = Math.cos(a), sin = Math.sin(a);
            const corner = (lx, ly) => [cx + lx * cos - ly * sin, cy + lx * sin + ly * cos];
            const corners = [corner(-hw, -hh), corner(hw, -hh), corner(hw, hh), corner(-hw, hh)];
            // The size under the bottom edge, outside — upright to read, never upside down.
            const mid = [(corners[2][0] + corners[3][0]) / 2, (corners[2][1] + corners[3][1]) / 2];
            const dx = mid[0] - cx, dy = mid[1] - cy, len = Math.hypot(dx, dy) || 1;
            let angle = m.angle ?? 0;
            angle = ((angle % 360) + 540) % 360 - 180;
            if (angle > 90) angle -= 180;
            if (angle < -90) angle += 180;
            out.push({
                kind: 'box', corners, ref: m, per: s,
                label: { text: boxLabel(m), x: mid[0] + dx / len * notePx * .8, y: mid[1] + dy / len * notePx * .8, px: notePx, angle },
            });
        }
    }
    return out;
}

/** The shapes as SVG for a cell of W × H pixels; `hair` is the line width in pixels.
 *  `movable`: the writing can be taken and moved on the plate (plates.js,
 *  noteDrag) — each text, arrow and box then stands in a group of its own
 *  that answers the pointer, an arrow and a box with a wider band to grip
 *  than their fine line. */
export function noteSvg(shapes, style, hair, W, H, movable = false) {
    const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
    // `weight` and `thicken` (pixels) for an arrow's words, which grow with its strength.
    const writing = (x, y, px, angle, lines, anchor, extra = '', weight = 600, thicken = 0) => {
        const spans = lines.map((line, i) =>
            `<tspan x="${x}" dy="${i === 0 ? 0 : px * TEXT_LEADING}">${esc(line) || ' '}</tspan>`).join('');
        const attrs = `x="${x}" y="${y}" font-family="${NOTE_FONT}" font-size="${px}" font-weight="${weight}"
                 text-anchor="${anchor}" xml:space="preserve"${angle ? ` transform="rotate(${angle} ${x} ${y})"` : ''}${extra}`;
        return `<text ${attrs} fill="${style.line_color}" stroke="${style.seam_color}" stroke-width="${px * .12 + thicken}" paint-order="stroke">${spans}</text>`
            + (thicken > 0 ? `<text ${attrs} fill="${style.line_color}" stroke="${style.line_color}" stroke-width="${thicken}" stroke-linejoin="round">${spans}</text>` : '');
    };
    let inner = '';
    shapes.forEach((sh, i) => {
        const grip = movable && sh.ref;
        if (grip) {
            inner += `<g class="plate-note-shape" data-i="${i}">`;
            const band = sh.kind === 'arrow' ? sh.outline : sh.kind === 'box' ? sh.corners : null;
            if (band) inner += `<polygon points="${band.map(q => q.join(',')).join(' ')}" fill="${sh.kind === 'arrow' ? 'transparent' : 'none'}"
                       stroke="transparent" stroke-width="${hair * 10}" stroke-linejoin="round"/>`;
        }
        draw(sh);
        if (grip) inner += '</g>';
    });
    return `<svg class="plate-notes" viewBox="0 0 ${W} ${H}" aria-hidden="true">${inner}</svg>`;

    function draw(sh) {
        if (sh.kind === 'text') {
            inner += writing(sh.x, sh.y, sh.px, sh.angle, sh.lines, sh.anchor ?? 'start', '', sh.weight, sh.thicken);
        } else if (sh.kind === 'stroke') {
            const tag = sh.closed ? 'polygon' : 'polyline';
            const pts = `points="${sh.pts.map(q => q.join(',')).join(' ')}" fill="none" stroke-linejoin="round"`;
            if (sh.seam) inner += `<${tag} ${pts} stroke="${style.seam_color}" stroke-width="${hair * 3}"/>`;
            inner += `<${tag} ${pts} stroke="${style.line_color}" stroke-width="${hair}"${sh.dash ? ` stroke-dasharray="${hair * 5} ${hair * 4}"` : ''}/>`;
        } else if (sh.kind === 'label') {
            inner += writing(sh.x, sh.y, sh.px, sh.angle, [sh.text], 'middle', ' dominant-baseline="middle"');
        } else if (sh.kind === 'arrow') {
            inner += `<polygon points="${sh.outline.map(q => q.join(',')).join(' ')}" fill="${style.line_color}"
                       stroke="${style.seam_color}" stroke-width="${hair * 2}" stroke-linejoin="round" paint-order="stroke"/>`;
        } else {
            const pts = `points="${sh.corners.map(q => q.join(',')).join(' ')}" fill="none"`;
            inner += `<polygon ${pts} stroke="${style.seam_color}" stroke-width="${hair * 3}"/>`
                + `<polygon ${pts} stroke="${style.line_color}" stroke-width="${hair}"/>`
                + writing(sh.label.x, sh.label.y, sh.label.px, sh.label.angle, [sh.label.text], 'middle',
                    ' dominant-baseline="middle"');
        }
    }
}

/** The shapes onto a canvas, `ox`/`oy` the cell's corner; the baseline is the context's (the page sets 'middle'). */
export function drawNotes(g, shapes, style, hair, ox, oy) {
    g.save();
    g.lineJoin = 'round';
    // A centred LABEL stands on its middle; written words keep their baseline (shift false).
    const write = (lines, x, y, px, angle, align, shift = align === 'center', weight = 600, thicken = 0) => {
        g.save();
        g.translate(ox + x, oy + y);
        g.rotate(angle * Math.PI / 180);
        g.font = `${weight} ${px}px ${NOTE_FONT}`;
        g.textAlign = align;
        g.textBaseline = 'alphabetic';
        g.lineJoin = 'round';
        g.fillStyle = style.line_color;
        lines.forEach((line, i) => {
            const dy = i * px * TEXT_LEADING + (shift ? px * .35 : 0);
            g.lineWidth = px * .12 + thicken;
            g.strokeStyle = style.seam_color;
            g.strokeText(line, 0, dy);
            // A strong arrow's words thicken in their own colour (noteSvg does the same).
            if (thicken > 0) {
                g.lineWidth = thicken;
                g.strokeStyle = style.line_color;
                g.strokeText(line, 0, dy);
            }
            g.fillText(line, 0, dy);
        });
        g.restore();
    };
    const path = (pts, closed = true) => {
        g.beginPath();
        pts.forEach(([x, y], i) => (i === 0 ? g.moveTo(ox + x, oy + y) : g.lineTo(ox + x, oy + y)));
        if (closed) g.closePath();
    };
    for (const sh of shapes) {
        if (sh.kind === 'text') {
            write(sh.lines, sh.x, sh.y, sh.px, sh.angle,
                { start: 'left', middle: 'center', end: 'right' }[sh.anchor ?? 'start'], false, sh.weight, sh.thicken);
        } else if (sh.kind === 'stroke') {
            path(sh.pts, sh.closed);
            if (sh.seam) {
                g.lineWidth = hair * 3;
                g.strokeStyle = style.seam_color;
                g.stroke();
            }
            g.setLineDash(sh.dash ? [hair * 5, hair * 4] : []);
            g.lineWidth = hair;
            g.strokeStyle = style.line_color;
            g.stroke();
            g.setLineDash([]);
        } else if (sh.kind === 'label') {
            write([sh.text], sh.x, sh.y, sh.px, sh.angle, 'center');
        } else if (sh.kind === 'arrow') {
            path(sh.outline);
            g.lineWidth = hair * 2;
            g.strokeStyle = style.seam_color;
            g.stroke();
            g.fillStyle = style.line_color;
            g.fill();
        } else {
            path(sh.corners);
            g.lineWidth = hair * 3;
            g.strokeStyle = style.seam_color;
            g.stroke();
            g.lineWidth = hair;
            g.strokeStyle = style.line_color;
            g.stroke();
            write([sh.label.text], sh.label.x, sh.label.y, sh.label.px, sh.label.angle, 'center');
        }
    }
    g.restore();
}
