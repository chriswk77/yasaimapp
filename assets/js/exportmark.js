/**
 * THE WATERMARK, DRAWN — one drawing for the settings tab's preview and the
 * exported file (08.11.2026: „ein Copyright … oder ein
 * durchsichtiges Schutzlayer, wo in fast transparenter Schrift immer wieder
 * wiederholt der Autorenname steht … so dass das Bild eigentlich unbrauchbar
 * ist, um es weiter zu bearbeiten", „ein Logo ist natürlich auch klasse").
 * The templates' shape and ranges live in includes/export_style.php.
 *
 * THREE PARTS, each switched on its own, drawn in this order so the one that
 * must be read lies on top:
 *   grid  the „Schutzgitter": a text repeated diagonally over the whole
 *         picture, nearly transparent, in a brick pattern so no clean strip
 *         stays between the rows
 *   logo  the account's logo in one of nine places
 *   hint  the copyright line in one of nine places, with a soft shadow so it
 *         reads on every ground
 *
 * SIZES ARE SHARES OF THE LONG EDGE (per cent), so a template looks alike on
 * a 1080 px post and on the full file; the margin to the edge grows with the
 * text. „auto" colour reads the picture under the spot — black or white,
 * whichever stands out, by the same measure as a plate's bar (inkAt).
 */

import { t } from './i18n.js';

export const MARK_FONTS = {
    sans: 'Arial, Helvetica, sans-serif',
    serif: 'Georgia, "Times New Roman", serif',
    mono: '"Courier New", Courier, monospace',
};

/**
 * The placeholders a text may carry, filled — German words, since the
 * person writes them into their own texts. One that has no value goes, and
 * so does a separator left hanging at either end („© Anna 2026 · " without
 * a species is „© Anna 2026").
 * @param {string} text
 * {Name} is the old word for {Urheber} (renamed 08.11.2026, „Name
 * würde ich auch Urheber nennen") and still reads as it — a text stored
 * before keeps working until it is next saved, when the server writes it
 * anew (export_style.php).
 * @param {{Urheber?, Jahr?, Art?, Datum?, Fundnummer?, Motiv?, Ort?}} values
 */
export function fillPlaceholders(text, values) {
    const out = String(text ?? '').replace(/\{(Urheber|Name|Jahr|Art|Datum|Fundnummer|Motiv|Ort)\}/g,
        (_, key) => String(values?.[key === 'Name' ? 'Urheber' : key] ?? '').trim());
    return out.replace(/\s{2,}/g, ' ')
        .replace(/^[\s·|,;–—-]+|[\s·|,;–—-]+$/g, '')
        .replace(/([·|,;–—-])\s*(?=[·|,;–—-])/g, '')
        .trim();
}

/** A place's share of the free room: tl → [0, 0], mc → [.5, .5], br → [1, 1]. */
function placeShare(place) {
    const x = { l: 0, c: .5, r: 1 }[place?.[1]] ?? 1;
    const y = { t: 0, m: .5, b: 1 }[place?.[0]] ?? 1;
    return [x, y];
}

/** The mean relative luminance of a spot, read off the canvas. */
function luminance(ctx, x, y, w, h) {
    try {
        const m = ctx.getTransform();
        const c = ctx.canvas;
        const a = Math.max(0, Math.min(c.width - 1, Math.floor(x * m.a + m.e)));
        const b = Math.max(0, Math.min(c.height - 1, Math.floor(y * m.d + m.f)));
        const ww = Math.max(1, Math.min(c.width - a, Math.ceil(w * m.a)));
        const hh = Math.max(1, Math.min(c.height - b, Math.ceil(h * m.d)));
        const d = ctx.getImageData(a, b, ww, hh).data;
        const lin = (v) => ((v /= 255) <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
        // Every fourth pixel is plenty for a mean, and keeps a large spot cheap.
        let sum = 0, n = 0;
        for (let i = 0; i < d.length; i += 16) {
            sum += .2126 * lin(d[i]) + .7152 * lin(d[i + 1]) + .0722 * lin(d[i + 2]);
            n++;
        }
        return n ? sum / n : .5;
    } catch {
        return .5;
    }
}

const inkFor = (color, lum) => (color === 'black' ? '#000' : color === 'white' ? '#fff' : lum > .179 ? '#000' : '#fff');

/**
 * Draw a template onto a canvas that already holds the picture.
 * @param {CanvasRenderingContext2D} ctx
 * @param {number} W the picture's width in the context's units
 * @param {number} H its height
 * @param {object} mark a template (export_style.php)
 * @param {object} values the placeholders' values (fillPlaceholders)
 * @param {?CanvasImageSource} logo the logo, loaded — null draws none
 */
export function drawMark(ctx, W, H, mark, values, logo = null) {
    if (!mark) return;
    const long = Math.max(W, H);
    const hintText = fillPlaceholders(mark.hint?.text ?? '', values);

    // --- The grid -------------------------------------------------------
    const grid = mark.grid ?? {};
    const gridText = fillPlaceholders(grid.text || mark.hint?.text || '', values);
    if (grid.on && gridText) {
        const size = Math.max(4, long * (grid.size ?? 4) / 100);
        ctx.save();
        ctx.font = `600 ${size}px ${MARK_FONTS[grid.font] ?? MARK_FONTS.sans}`;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'center';
        ctx.globalAlpha = (grid.opacity ?? 15) / 100;
        ctx.fillStyle = inkFor(grid.color, luminance(ctx, 0, 0, W, H));
        const tw = ctx.measureText(gridText).width;
        const space = size * (grid.gap ?? 150) / 100;
        const stepX = tw + space, stepY = size + space;
        ctx.translate(W / 2, H / 2);
        ctx.rotate((grid.angle ?? -30) * Math.PI / 180);
        // The diagonal covers the picture at any angle.
        const reach = Math.hypot(W, H) / 2 + stepX;
        let row = 0;
        for (let y = -reach; y <= reach; y += stepY, row++) {
            const shift = row % 2 ? stepX / 2 : 0;
            for (let x = -reach - shift; x <= reach; x += stepX) ctx.fillText(gridText, x, y);
        }
        ctx.restore();
    }

    const margin = (size) => Math.max(long * .015, size * .8);

    // --- The logo -------------------------------------------------------
    const lg = mark.logo ?? {};
    if (lg.on && logo && (logo.naturalWidth || logo.width)) {
        const lw0 = logo.naturalWidth || logo.width, lh0 = logo.naturalHeight || logo.height;
        const k = long * (lg.size ?? 15) / 100 / Math.max(lw0, lh0);
        const lw = lw0 * k, lh = lh0 * k;
        const m = margin(lh * .3);
        const [sx, sy] = placeShare(lg.place);
        const x = m + (W - 2 * m - lw) * sx, y = m + (H - 2 * m - lh) * sy;
        ctx.save();
        ctx.globalAlpha = (lg.opacity ?? 80) / 100;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(logo, x, y, lw, lh);
        ctx.restore();
    }

    // --- The hint -------------------------------------------------------
    const hint = mark.hint ?? {};
    if (hint.on && hintText) {
        const size = Math.max(4, long * (hint.size ?? 2.5) / 100);
        ctx.save();
        ctx.font = `${size}px ${MARK_FONTS[hint.font] ?? MARK_FONTS.sans}`;
        ctx.textBaseline = 'alphabetic';
        const metrics = ctx.measureText(hintText);
        const tw = metrics.width;
        const ascent = metrics.actualBoundingBoxAscent || size * .75;
        const descent = metrics.actualBoundingBoxDescent || size * .2;
        const th = ascent + descent;
        const m = margin(size);
        const [sx, sy] = placeShare(hint.place);
        const x = m + (W - 2 * m - tw) * sx;
        const top = m + (H - 2 * m - th) * sy;
        const ink = inkFor(hint.color, luminance(ctx, x, top, tw, th));
        ctx.globalAlpha = (hint.opacity ?? 85) / 100;
        if (hint.shadow) {
            ctx.shadowColor = ink === '#fff' ? 'rgba(0,0,0,.6)' : 'rgba(255,255,255,.6)';
            ctx.shadowBlur = size * .25;
        }
        ctx.fillStyle = ink;
        ctx.fillText(hintText, x, top + ascent);
        ctx.restore();
    }
}

/** A logo by its stored name, loaded once per page. */
const logos = new Map();
export function loadLogo(baseUrl, file) {
    if (!file) return Promise.resolve(null);
    if (!logos.has(file)) {
        logos.set(file, new Promise((resolve) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => { logos.delete(file); resolve(null); };
            img.src = `${baseUrl}pages/export_logo.php?file=${encodeURIComponent(file)}`;
        }));
    }
    return logos.get(file);
}

/* --- Size and sharpness --------------------------------------------------
   „Wenn ich den zum Beispiel für Instagram exportieren will, dass ich ihn
   verkleinern kann … %50 Größe oder lange Kante 1000 Pixel"
   (08.11.2026). */

function canvasOf(w, h) {
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w));
    c.height = Math.max(1, Math.round(h));
    return c;
}

/** The size a picture W × H leaves at: 'full', {edge: px} or {share: %} — never larger than it is. */
export function exportSize(W, H, size) {
    let k = 1;
    if (size?.kind === 'edge' && size.edge > 0) k = Math.min(1, size.edge / Math.max(W, H));
    else if (size?.kind === 'share' && size.share > 0) k = Math.min(1, size.share / 100);
    return { w: Math.max(1, Math.round(W * k)), h: Math.max(1, Math.round(H * k)), k };
}

/**
 * A canvas made smaller, in halving steps: one jump from 6000 to 1080 px
 * lets the browser skip pixels and the picture shimmers; halving until the
 * last step is less than half reads every pixel along the way.
 */
export function scaleCanvas(src, w, h) {
    let cur = src;
    while (cur.width / 2 >= w && cur.height / 2 >= h) {
        const next = canvasOf(cur.width / 2, cur.height / 2);
        const g = next.getContext('2d');
        g.imageSmoothingQuality = 'high';
        g.drawImage(cur, 0, 0, next.width, next.height);
        cur = next;
    }
    const out = canvasOf(w, h);
    const g = out.getContext('2d');
    g.imageSmoothingQuality = 'high';
    g.drawImage(cur, 0, 0, out.width, out.height);
    return out;
}

/** A canvas copied onto a 2D one of the same size — the WebGL canvas cannot take a watermark. */
export function flatCanvas(src) {
    const out = canvasOf(src.width, src.height);
    out.getContext('2d').drawImage(src, 0, 0);
    return out;
}

/**
 * SHARPENING AFTER MAKING SMALLER — an unsharp mask of one pixel's reach:
 * the picture plus `amount` times its difference to a slightly blurred copy
 * (a 3 × 3 binomial blur). A picture shrunk to a fifth looks soft; this
 * brings the edges back, as every photo program does on export for screens.
 * 'light' and 'strong' are 0.4 and 0.8.
 */
export function sharpenCanvas(canvas, strength) {
    const amount = { light: .4, strong: .8 }[strength] ?? 0;
    if (!amount) return canvas;
    const g = canvas.getContext('2d', { willReadFrequently: true });
    const { width: w, height: h } = canvas;
    const image = g.getImageData(0, 0, w, h);
    const p = image.data;
    const src = new Uint8ClampedArray(p);
    const at = (x, y) => (Math.min(h - 1, Math.max(0, y)) * w + Math.min(w - 1, Math.max(0, x))) * 4;
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = (y * w + x) * 4;
            const n = [at(x - 1, y - 1), at(x, y - 1), at(x + 1, y - 1), at(x - 1, y), at(x + 1, y),
                at(x - 1, y + 1), at(x, y + 1), at(x + 1, y + 1)];
            for (let c = 0; c < 3; c++) {
                const blur = (4 * src[i + c] + 2 * (src[n[1] + c] + src[n[3] + c] + src[n[4] + c] + src[n[6] + c])
                    + src[n[0] + c] + src[n[2] + c] + src[n[5] + c] + src[n[7] + c]) / 16;
                p[i + c] = src[i + c] + amount * (src[i + c] - blur);
            }
        }
    }
    g.putImageData(image, 0, 0);
    return canvas;
}

/* --- Buttons that put a placeholder in ------------------------------------
   08.11.2026: „Das würde ich alles als Textbutton … dazu machen,
   dass man das nicht selbst per Hand eintippen muss. Gerade die geschweifte
   Klammer ist für viele gar nicht ersichtlich, wie die überhaupt geht." So
   every field that takes placeholders gets a row of buttons beneath it,
   labelled in plain words — „Art", „Datum" — each putting its {…} where
   the caret stands. The braces are still what is stored and what one sees
   in the field; nobody has to type them. */

/** The placeholders in the order the buttons stand, each with what it stands for. */
export const PLACEHOLDERS = [
    ['Urheber', () => t('der Urheber')],
    ['Art', () => t('die Art des Funds')],
    ['Datum', () => t('das Funddatum')],
    ['Ort', () => t('die Gemeinde des Fundorts')],
    ['Fundnummer', () => t('die Nummer des Funds')],
    ['Motiv', () => t('der Motivname des Bildes')],
    ['Jahr', () => t('das Jahr des Exports')],
];

/**
 * A row of placeholder buttons for a text field.
 * @param {HTMLInputElement} field
 * @param {() => void} onChange  called after a placeholder went in
 * @param {Object<string, () => string>} says  other explanations, by key (the file name's Datum and Ort)
 */
export function placeholderButtons(field, onChange, says = {}) {
    const row = document.createElement('div');
    row.className = 'placeholder-buttons';
    for (const [key, about] of PLACEHOLDERS) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'button subtle small';
        b.textContent = '+ ' + t(key);
        b.title = t('Setzt „%s“ ein: %s', `{${key}}`, (says[key] ?? about)());
        // The field keeps its caret: a button press would otherwise take the focus first.
        b.addEventListener('mousedown', (e) => e.preventDefault());
        b.addEventListener('click', () => {
            const at = field.selectionStart ?? field.value.length, to = field.selectionEnd ?? at;
            const before = field.value.slice(0, at), after = field.value.slice(to);
            // A space between it and a word it would otherwise stick to.
            const piece = (before && !/\s$/.test(before) ? ' ' : '') + `{${key}}` + (after && !/^\s/.test(after) ? ' ' : '');
            field.value = before + piece + after;
            const caret = before.length + piece.length;
            field.focus();
            field.setSelectionRange(caret, caret);
            field.dispatchEvent(new Event('input', { bubbles: true }));
            onChange?.();
        });
        row.append(b);
    }
    return row;
}
