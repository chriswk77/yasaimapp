/**
 * The measuring overlay's strokes and lettering, as SVG markup.
 *
 * ONE DRAWING FOR TWO PLACES (26.09.2026): the photo window draws its
 * measurements with these (photowindow.js), and the live preview in Verwaltung →
 * Einstellungen → Fotos → Messen draws its example with the very same
 * (measurepreview.js) — the maintainer asked for a preview „so wie man es auf
 * dem Bild dann sieht", and a second copy of the drawing would have
 * started to lie the day one of the two changed.
 *
 * Pure strings, no state: the caller hands in the account's style
 * (colours, font size — MEASURE_STYLE_DEFAULTS in includes/photos.php
 * says what they are) and the hairline, the reciprocal of the on-screen
 * scale. The preview draws at 1:1 and passes 1.
 */

/** Everything drawn twice: a seam under the line, so it reads on any
 *  photograph.
 *
 *  THE WIDTH IS RECKONED AGAINST THE ZOOM, like the label size — and NOT
 *  left to vector-effect="non-scaling-stroke", which once stood here:
 *  that attribute only compensates the viewBox mapping, while the zoom
 *  scales the whole overlay by CSS transform, so every line FATTENED
 *  with the magnification (28.08.2026: „Die sollte natürlich
 *  schön fein bleiben"). The hairline is the reciprocal of the current
 *  on-screen scale; the photo window refreshes it on every draw.
 *
 *  Thin on the word (28.08.2026, „eine dünne, feine Linie"):
 *  the drawing must not cover what it measures — a spore under a fat
 *  rectangle is a spore one cannot judge any more. */
export function seamed(shape, attrs, style, hair = 1) {
    return `<${shape} ${attrs} fill="none"
              stroke="${style.seam_color}" stroke-width="${2 * hair}"/>`
         + `<${shape} ${attrs} fill="none"
              stroke="${style.line_color}" stroke-width="${hair}"/>`;
}

/** The dot at either end of a line. */
export function mark(x, y, style, hair = 1) {
    return seamed('circle', `cx="${x}" cy="${y}" r="${4 * hair}"`, style, hair);
}

/* THE VIEW'S TURN (stage 2 of „Bearbeiten", 07.11.2026): the viewer may show
   the picture turned, its measures drawn in the original's pixels inside a
   turned group. A label is read on the SCREEN, so it stands upright there:
   a plain one turns back by the view's turn, one along an edge is kept off
   its head in the view's sense, not the picture's. 0 everywhere else. */
let viewTurn = 0;
export function setViewTurn(deg) {
    viewTurn = Number(deg) || 0;
}
/** An edge's angle, turned so its words read left to right on the screen. */
function upright(ang) {
    let a = ((ang + viewTurn) % 360 + 540) % 360 - 180;
    if (a > 90 || a <= -90) a += 180;
    a = ((a % 360) + 540) % 360 - 180;
    return a - viewTurn;
}

/** A label standing upright. fontSize/8, not /5 — the old seam swallowed
 *  the letterforms (28.08.2026: „etwas dünner"), and its colour
 *  is the account's choice like everything the overlay wears. */
export function label(x, y, text, fontSize, style) {
    return `<text x="${x}" y="${y}" font-size="${fontSize}" font-weight="600"
                  fill="${style.line_color}" stroke="${style.seam_color}"
                  stroke-width="${fontSize / 8}"
                  paint-order="stroke" text-anchor="middle"${viewTurn ? ` transform="rotate(${-viewTurn} ${x} ${y})"` : ''}>${text}</text>`;
}

/**
 * A label lying PARALLEL to its edge (28.08.2026: „dass sich
 * der Text der Linie angleicht … parallel zur Linie lesbar").
 *
 * NEVER UPSIDE DOWN: an edge steeper than the vertical gets its angle
 * flipped by 180°, so the reading direction always runs left to right —
 * the price is that "along the line" can mean either of its two
 * directions, and nobody reads a measurement standing on its head.
 *
 * awayX/awayY says which side of the edge the label floats off to —
 * the rectangle pushes outward from its centre; a bare line, given
 * nothing, floats "above" its own reading direction.
 */
export function edgeLabel(x1, y1, x2, y2, text, fontSize, style, awayX = null, awayY = null) {
    const ang = upright(Math.atan2(y2 - y1, x2 - x1) * 180 / Math.PI);
    const rad = ang * Math.PI / 180;
    if (awayX === null) {
        awayX = Math.sin(rad);
        awayY = -Math.cos(rad);
    }
    const norm = Math.hypot(awayX, awayY) || 1;
    const px = (x1 + x2) / 2 + awayX / norm * fontSize * 0.8;
    const py = (y1 + y2) / 2 + awayY / norm * fontSize * 0.8;
    return `<text x="${px}" y="${py}" font-size="${fontSize}" font-weight="600"
                  fill="${style.line_color}" stroke="${style.seam_color}"
                  stroke-width="${fontSize / 8}" paint-order="stroke"
                  text-anchor="middle" dominant-baseline="middle"
                  transform="rotate(${ang} ${px} ${py})">${text}</text>`;
}

/* --- Labels that do not lie on one another ------------------------------------ */

/*
 * EVERY NUMBER IN ITS OWN PLACE, AND STILL BY ITS OWN EDGE
 * (07.10.2026: „dass sich Maßangaben nicht überlagern, aber man trotzdem
 * erkennen kann, zu welchem Rechteck oder Strecke oder Polygon sie
 * gehören"). Eighteen spores side by side wrote their lengths across each
 * other. The photo window now collects every edge label of a drawing and
 * places them together (placedEdgeLabels), greedily, in the order handed
 * in — the chosen measure's first, so its numbers never move for others:
 *
 *   1. where edgeLabel puts it: the edge's middle, just outside;
 *   2. slid along the same edge, a quarter and nearly half of it either
 *      way — still beside its own edge, so it needs nothing more;
 *   3. a bare line's number may also change to the line's other side
 *      (a rectangle's never goes inside: it would cover the spore);
 *   4. only then further out, a line height at a time, up to eight — and
 *      from there a THIN LEADER, seamed like every line, runs back to the
 *      point of the edge it belongs to.
 *   If nothing is free, it takes the place that meets the fewest others
 *   (the nearest of them on a tie). A crowd that dense clears up when
 *   one zooms in: the numbers keep their size on the screen, the
 *   picture grows.
 *
 * Only labels are kept apart, not labels from lines: a number over a
 * neighbour's outline is still readable, two numbers over each other are
 * not. The text's width is measured once per string on a canvas, in the
 * overlay's own font.
 */

let ruler = null;
const rulerWidths = new Map();
function textWidth(text, fontSize, family) {
    const key = family + '\n' + text;
    if (!rulerWidths.has(key)) {
        ruler ??= document.createElement('canvas').getContext('2d');
        ruler.font = `600 100px ${family}`;
        // The tags a caller may have escaped count as their one character.
        const plain = String(text).replace(/&(amp|lt|gt);/g, 'x');
        rulerWidths.set(key, ruler.measureText(plain).width / 100);
    }
    return rulerWidths.get(key) * fontSize;
}

/** Do two turned boxes {cx, cy, ux, uy, hw, hh} overlap? Separating axes. */
function boxesMeet(a, b, gap) {
    const dx = b.cx - a.cx, dy = b.cy - a.cy;
    for (const [ax, ay] of [[a.ux, a.uy], [-a.uy, a.ux], [b.ux, b.uy], [-b.uy, b.ux]]) {
        const reach = (box) => box.hw * Math.abs(box.ux * ax + box.uy * ay)
                             + box.hh * Math.abs(-box.uy * ax + box.ux * ay);
        if (Math.abs(dx * ax + dy * ay) > reach(a) + reach(b) + gap) return false;
    }
    return true;
}

/**
 * Edge labels placed together. Each request is what edgeLabel takes —
 * {x1, y1, x2, y2, text, fontSize, awayX, awayY} — plus an optional
 * opacity; returns the SVG of all of them, leaders included.
 */
export function placedEdgeLabels(requests, style, hair = 1, family = 'sans-serif') {
    const placed = [];
    let out = '';
    for (const r of requests) {
        const { x1, y1, x2, y2, text, fontSize } = r;
        const ang = upright(Math.atan2(y2 - y1, x2 - x1) * 180 / Math.PI);
        const rad = ang * Math.PI / 180;
        const ux = Math.cos(rad), uy = Math.sin(rad);
        const free = r.awayX == null;
        let ax = free ? Math.sin(rad) : r.awayX, ay = free ? -Math.cos(rad) : r.awayY;
        const norm = Math.hypot(ax, ay) || 1;
        ax /= norm; ay /= norm;
        const mx = (x1 + x2) / 2, my = (y1 + y2) / 2;
        const len = Math.hypot(x2 - x1, y2 - y1);
        const hw = textWidth(text, fontSize, family) / 2 + fontSize * .15;
        const hh = fontSize * .6;
        const step = hh * 2;
        // Along the edge: the middle, then a quarter and nearly half its length either way.
        const slides = [0, -.25, .25, -.45, .45].map(f => f * len);
        const sides = free ? [1, -1] : [1];
        const tries = [];
        for (const side of sides) for (const s of slides) tries.push({ side, s, out: 0 });
        for (let k = 1; k <= 8; k++) {
            for (const side of sides) for (const s of [0, -.3 * len, .3 * len]) tries.push({ side, s, out: k });
        }
        const boxAt = ({ side, s, out: k }) => ({
            cx: mx + ux * s + ax * side * (fontSize * .8 + k * step),
            cy: my + uy * s + ay * side * (fontSize * .8 + k * step),
            ux, uy, hw, hh,
        });
        let pick = null, fewest = Infinity;
        for (const c of tries) {
            const box = boxAt(c);
            let met = 0;
            for (const p of placed) if (boxesMeet(p, box, fontSize * .1) && ++met >= fewest) break;
            if (met < fewest) { pick = c; fewest = met; }
            if (met === 0) break;
        }
        const box = boxAt(pick);
        placed.push(box);
        let svg = '';
        if (pick.out > 0) {
            // The leader: from the edge's point to the near side of the box.
            const ex = mx + ux * pick.s, ey = my + uy * pick.s;
            const lx = box.cx - ax * pick.side * hh, ly = box.cy - ay * pick.side * hh;
            svg += seamed('line', `x1="${ex}" y1="${ey}" x2="${lx}" y2="${ly}"`, style, hair * .75);
        }
        svg += `<text x="${box.cx}" y="${box.cy}" font-size="${fontSize}" font-weight="600"
                  fill="${style.line_color}" stroke="${style.seam_color}"
                  stroke-width="${fontSize / 8}" paint-order="stroke"
                  text-anchor="middle" dominant-baseline="middle"
                  transform="rotate(${ang} ${box.cx} ${box.cy})">${text}</text>`;
        out += r.opacity != null && r.opacity < 1 ? `<g opacity="${r.opacity}">${svg}</g>` : svg;
    }
    return out;
}

/* --- What the viewer and the plates draw alike -------------------------------
   The writing, the arrow and the counting box of a picture are drawn by the
   photo window over the photograph AND by the plates over a cell's cutout
   (plateannot.js, 08.10.2026). Their numbers live here, once, so a plate's
   arrow is the viewer's arrow and a box says its size the way it was typed. */

/** The leading every text, its box and its drawing reckon with. */
export const TEXT_LEADING = 1.2;

/* THE TEXT SIZES COME IN STEPS (05.10.2026: „das
   Schriftgrößen-Verändern in Stufen machen und dazu eine Schriftgröße
   anzeigen, damit man es reproduzieren kann, falls man mehr Texte in
   einer Serie hat"). The number is the letter height in thousandths of
   the picture's width — the same number gives the same writing on the
   preview and on the original, on this camera's pictures and the next.
   The steps run like a type case: fine where small sizes differ, coarse
   where large ones do. Here since 15.11.2026, when the plate learnt to
   size an arrow's words as the viewer does. */
export const TEXT_SIZES = [8, 10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 40, 48,
                           56, 64, 72, 80, 96, 120, 150, 200];

/** The step nearest to a wished size — by ratio, as type sizes go. */
export function textStep(shown) {
    let best = TEXT_SIZES[0];
    for (const step of TEXT_SIZES) {
        if (Math.abs(Math.log(step / shown)) < Math.abs(Math.log(best / shown))) best = step;
    }
    return best;
}

/* THE ARROW'S STRENGTH, in steps (05.10.2026: „den Pfeil würde ich
   auch gerne stufenweise dicker machen können"). Shaft and head in thousandths
   of the picture's width, like the text sizes, so an arrow belongs to the
   picture; „Stärke 1" to „Stärke 8" is what one reads and sets again. Two is
   where a new arrow begins. */
export const ARROW_WEIGHTS = [
    { shaft: 1,   head: 10 }, { shaft: 1.5, head: 13 }, { shaft: 2.5, head: 16 },
    { shaft: 4,   head: 20 }, { shaft: 6,   head: 26 }, { shaft: 8,   head: 32 },
    { shaft: 11,  head: 40 }, { shaft: 15,  head: 50 },
];

/** An arrow's strength, 1 to 8 — two when it has none. */
export function arrowWeightOf(m) {
    const w = Number(m.weight);
    return Number.isInteger(w) && w >= 1 && w <= ARROW_WEIGHTS.length ? w : 2;
}

/**
 * An arrow's outline — shaft and head as ONE shape, so a strong arrow is a
 * solid arrow and not a thick line with a triangle on it. `tail` and `tip`
 * in the drawing's own units, `per` one thousandth of the picture's width in
 * those units. The head never outgrows the arrow: on a short one it takes at
 * most six tenths of the length.
 */
export function arrowOutline(tail, tip, weight, per) {
    const step = ARROW_WEIGHTS[weight - 1];
    const len = Math.hypot(tip[0] - tail[0], tip[1] - tail[1]) || 1;
    const u = [(tip[0] - tail[0]) / len, (tip[1] - tail[1]) / len];
    const n = [-u[1], u[0]];
    const half = step.shaft * per / 2;
    const head = Math.min(step.head * per, len * 0.6);
    const wing = Math.max(head * 0.45, half * 1.6);
    const base = [tip[0] - u[0] * head, tip[1] - u[1] * head];
    const off = (p, k) => [p[0] + n[0] * k, p[1] + n[1] * k];
    return {
        n, half, off,
        middle: [(tail[0] + base[0]) / 2, (tail[1] + base[1]) / 2],
        outline: [off(tail, half), off(base, half), off(base, wing), tip,
                  off(base, -wing), off(base, -half), off(tail, -half)],
    };
}

/*
 * AN ARROW CARRIES ITS OWN TEXT (30.10.2026: „Pfeil und Text
 * würden eine Einheit bilden … Lösche ich eins, lösche ich das andere mit.
 * Verschiebe ich eins, verschiebe ich das andere mit"). Not a text bound to
 * an arrow but one record: the words sit at the arrow's TAIL and grow AWAY
 * from it, so a larger size on the plate never runs over the arrow. A
 * separate text would have to be guessed into belonging — „dumm ist es
 * natürlich auch erstmal erkennen, ob Text und Pfeil zusammengehören".
 *
 * WHERE THE WORDS LIE: BESIDE THE TAIL, CENTRED ON IT — never a corner
 * (30.10.2026: „wenn der Text links vom Pfeil sitzt, hätte ich
 * gerne, dass der Text … Mitte vertikal zum Pfeil-Endpunkt ausgerichtet
 * ist. Also immer die Mitte des Textes. Mitte horizontal, Mitte vertikal.
 * Niemals mehr ein Eckpunkt"). Two drafts went before it the same day: a
 * corner of the words at the tail, then their middle on the arrow's line
 * drawn on — on a slanting arrow that again left a corner nearest the
 * tail. Now the arrow's main direction decides one of four sides: an
 * arrow that runs more across than up or down has its words left or
 * right of its tail, their height's middle at the tail's height; one that
 * runs more up or down has them above or below, their width's middle over
 * the tail. The side follows the arrow, so an arrow turned later takes
 * its words round with it. Nothing to choose by hand („Wir machen nur noch
 * automatisch"); a side the first draft stored is ignored and dropped by
 * the server at the next save.
 */

/**
 * AN ARROW'S WORDS GROW WITH ITS STRENGTH (30.10.2026: „mit
 * Stärke des Pfeils auch ein bisschen das Schriftgewicht und ein wenig die
 * Schriftgröße anpassen"). Around the strength a new arrow begins with
 * (two), which keeps the words as they were: larger with every step above
 * it, normal letters at one, and from three on the letters thicken by a
 * stroke in their own colour — the plate prints in
 * Arial, which knows only normal and bold, and a stroke thickens any font
 * alike, so viewer, plate and print show the same. `thicken` is a share of
 * the letter height.
 */
/* SINCE 15.11.2026 the strength no longer GROWS the words when it is
   pulled: arrowWeightSet takes the share below back out of the stored
   size, so what the words show stays. The share stays in the drawing, so
   the arrows set before look as they did. */
export function arrowTextLook(m) {
    const w = arrowWeightOf(m);
    /* THE WEIGHT GOES INTO THE SIZE (the same evening, after
       two steps of „gewichtiger": „Die Schriftgröße muss mitwachsen"). A
       quarter of the letter height of stroke at eight closed the letters
       up — „Hut" was a black block. So the size now grows with the square
       of the steps above two,
       the font's own weight steps up, and the stroke stays modest: a
       strong arrow carries big, bold, still open letters. */
    /* LIVE, ON REAL PICTURES, STILL TOO HEAVY for the arrow beside it
       (later that evening: „die Schriftdicke zu dick zur
       Pfeilgröße"): the stroke halved to 3.6 % of the letter height at
       eight, and the heaviest cut stays bold (700). */
    const above = Math.max(0, w - 2);
    return {
        // „Und auch die Schriftgröße" (the same evening): eight writes half
        // again as large as two, no longer almost twice.
        scale: 1 + .06 * (w - 2) + .005 * above * above,
        weight: w <= 1 ? 400 : w <= 4 ? 600 : 700,
        thicken: .006 * above,
    };
}

/**
 * A NEW STRENGTH, THE WORDS KEEP THEIR SIZE (15.11.2026: „was
 * extrem stört, ist, wenn ich den Pfeil vergrößere, dass sich dann die
 * Schrift mit vergrößert. Da wir jetzt unabhängig sind"). The words have
 * their own grip now; the strength's share in their size (arrowTextLook,
 * `scale`) stays in the drawing, so every arrow set before looks as it
 * did, and pulling the strength takes it back out of the stored size —
 * the words show the same size at every step. `base` is the arrow as the
 * pull began ({weight, size}), so the steps do not round away the size.
 * Their letters still turn bolder with a strong arrow (`weight`,
 * `thicken`).
 */
export function arrowWeightSet(m, base, weight) {
    m.weight = weight;
    if (base.size == null) return;
    const before = arrowTextLook({ weight: base.weight }).scale;
    m.size = Math.round(base.size * before / arrowTextLook(m).scale * 10) / 10;
}

/**
 * Where an arrow's words are written: x their middle (text-anchor middle),
 * y the FIRST line's baseline, for lines of letter height `fs` whose widest
 * is `width` — in the units tail and tip are given in. Four tenths of a
 * letter height of air between the words' box and the tail.
 */
export function arrowTextAt(tail, tip, fs, lines, width) {
    const dx = tail[0] - tip[0], dy = tail[1] - tip[1];
    const height = fs * .8 + (lines - 1) * fs * TEXT_LEADING + fs * .25;
    const air = fs * .4;
    const across = Math.abs(dx) >= Math.abs(dy);
    const x = across ? tail[0] + Math.sign(dx || 1) * (width / 2 + air) : tail[0];
    const cy = across ? tail[1] : tail[1] + Math.sign(dy) * (height / 2 + air);
    const top = cy - height / 2;
    return { x, y: top + fs * .8, anchor: 'middle', top, height };
}

/**
 * WHERE THE SIZE GRIP OF AN ARROW'S WORDS STANDS: on the side of the words
 * AWAY from the tail, in its middle (15.11.2026: „Wäre schön,
 * wenn die Schriftgröße immer irgendwie vom Pfeilende weg ist, dass man
 * größer oder kleiner vom Pfeilende, von seiner festen Position aus ziehen
 * kann"). The words grow away from the tail (arrowTextAt), so the grip
 * rides on their growing edge, and the pointer's distance from the tail is
 * their size — the tail is the one point that stays. A corner was tried
 * first: next to the tail, a few pixels there were half the size. `box`
 * is the words' {left, top, right, bottom}; `away` the unit direction the
 * grip's number stands in, beyond it.
 */
export function arrowWordsGrip(tail, tip, box) {
    const dx = tail[0] - tip[0], dy = tail[1] - tip[1];
    if (Math.abs(dx) >= Math.abs(dy)) {
        const right = dx >= 0;
        return { pos: [right ? box.right : box.left, (box.top + box.bottom) / 2], away: [right ? 1 : -1, 0] };
    }
    const below = dy >= 0;
    return { pos: [(box.left + box.right) / 2, below ? box.bottom : box.top], away: [0, below ? 1 : -1] };
}

/** The unit a box is told in: the one it was typed in, or — for one that
 *  carries none — millimetres from a millimetre on. */
export function boxUnit(m) {
    if (m.unit === 'mm' || m.unit === 'um') return m.unit;
    return Math.min(m.um[0], m.um[1]) >= 1000 ? 'mm' : 'um';
}

/** One side as the field and the label say it — in the box's unit, with the
 *  German comma, no trailing zeros. */
export function boxNumber(um, unit) {
    return (unit === 'mm' ? um / 1000 : um).toLocaleString('de-DE', {
        maximumFractionDigits: unit === 'mm' ? 3 : 1, useGrouping: false,
    });
}

/** „1 × 1 mm", „10 × 2 mm", „50 × 50 µm" — as it was typed. */
export function boxLabel(m) {
    const unit = boxUnit(m);
    return boxNumber(m.um[0], unit) + ' × ' + boxNumber(m.um[1], unit) + (unit === 'mm' ? ' mm' : ' µm');
}
