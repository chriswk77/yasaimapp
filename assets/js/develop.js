/**
 * THE PICTURE DEVELOPED, NEVER CHANGED (06.11.2026: „ähnlich wie
 * Lightroom … als live gerechneten Layer drüberlegen … nicht das
 * Originalbild editieren"). The viewer's second mode, „Bearbeiten", keeps
 * a picture's settings as JSON at the picture (migration 0190,
 * photo_develop_clean in includes/photos.php checks their shape) and this
 * module renders them over the untouched original, live, in WebGL:
 *
 * - the sliders of the whole picture — light, colour, details;
 * - the ellipses laid over it („diese Rosette … mit der man dann Stellen
 *   verdunkeln oder erhellen kann. Und auch diese Ellipse invertieren"),
 *   each with its own few sliders, a soft edge, inside or outside.
 *
 * WHY WEBGL: a slider is dragged, and every step redraws a picture of
 * several million pixels. On the graphics card that is a frame; in a 2D
 * canvas loop it is a second. Clarity and sharpening need the picture
 * blurred (a detail is the difference to its blur), so the renderer keeps
 * two blurred copies, made only where a slider asks for them.
 *
 * THE NUMBERS ARE LOOKS, NOT PHYSICS: every slider runs -100 … 100 (the
 * picture's sharpening 0 … 100, a mask's -100 … 100) and is mapped to a gentle effect — the long track in
 * the sidebar is for fine steps, so the ends must not be a cliff. The
 * mapping lives once, here; the server only checks the ranges.
 */

import { t } from './i18n.js';

/** The sidebar's groups and sliders. Colour stands on top (07.11.2026 — „Farben oben,
 *  also über Licht"), then light and details. KONTRAST BELONGS TO „FARBE" (11.11.2026:
 *  „Temperatur, Tönung, Kontrast, Dynamik, Sättigung") — only where it stands; it is the same
 *  setting it always was. min 0 where a slider has no negative side. */
export const DEVELOP_GROUPS = [
    { key: 'color', name: () => t('Farbe'), sliders: [
        { key: 'temp', name: () => t('Temperatur') },
        { key: 'tint', name: () => t('Tönung') },
        { key: 'contrast', name: () => t('Kontrast') },
        { key: 'vibrance', name: () => t('Dynamik') },
        { key: 'saturation', name: () => t('Sättigung') },
    ] },
    { key: 'light', name: () => t('Licht'), sliders: [
        { key: 'exposure', name: () => t('Belichtung') },
        { key: 'highlights', name: () => t('Lichter') },
        { key: 'shadows', name: () => t('Tiefen') },
        { key: 'whites', name: () => t('Weiß') },
        { key: 'blacks', name: () => t('Schwarz') },
    ] },
    { key: 'details', name: () => t('Details'), sliders: [
        { key: 'clarity', name: () => t('Klarheit') },
        { key: 'dehaze', name: () => t('Dunst entfernen') },
        { key: 'sharpen', name: () => t('Schärfen'), min: 0, maskMin: -100 },
    ] },
];
/* SCHÄRFEN GOES BELOW ZERO IN A MASK (14.11.2026: „manche Flächen
   oder manche Pinselstriche einfach unscharf zu machen") — Lightroom's local
   sharpness: minus softens what the mask covers, down to the wide blur
   „Klarheit" reads (about a 400th of the picture) at -100. The picture's own
   slider keeps 0 … 100: blurring the WHOLE picture is no development
   anybody asks for, and its server check says 0 … 100 (photo_develop_clean),
   while a mask's sliders were always checked -100 … 100. maskMin is read by
   developGroupsDraw (photowindow.js) for the masks' panel only. */

/**
 * WHAT A SLIDER DOES, PAINTED ON ITS LINE (08.11.2026: „bei
 * Temperatur … so einen Verlauf … von blau nach gelb … bei der Tönung
 * eben von grün nach lila, bei der Belichtung von dunkel nach hell und so
 * weiter, dort wo es Sinn macht"): left is minus, right is plus, so the
 * line shows where the knob takes the picture before it is moved. Literal
 * colours on purpose — they depict what happens to the picture, the way a
 * tree species' colour depicts the tree (CLAUDE.md), and must not change
 * with the theme. Only where a direction can be seen: Klarheit, Dunst and
 * Schärfen change detail, not a colour or a brightness, and keep the plain
 * line. KONTRAST IS A DOUBLE LINE (the same day: „links grau,
 * … die obere wird nach rechts schwarz, die untere wird nach rechts weiß.
 * Sodass sie am Anfang quasi beide grau sind"): one grey at minus that
 * parts into black above and white below — the darks darker and the
 * lights lighter at once, which no gradient in one direction can say (a
 * flat half beside a black-against-white half read as two things, and
 * dark → light only as „brighter").
 * SÄTTIGUNG AND DYNAMIK carry the whole rainbow end to end with a grey veil
 * over it that thins out to the right („dass der Regenbogen ganz durchlaufen
 * würde und … dieses Grau-Overlay bekommen könnte") — colourless at minus,
 * full colour at plus; Dynamik's colours stay softer throughout.
 */
const RAINBOW = 'linear-gradient(90deg, #e53935, #fdd835 20%, #43a047 40%, #00acc1 60%, #1e88e5 80%, #8e24aa)';
const DARK_LIGHT = 'linear-gradient(90deg, #111, #eee)';
export const DEVELOP_TRACKS = {
    temp: 'linear-gradient(90deg, #2f6fd6, #a9b4c0 50%, #e6c229)',
    tint: 'linear-gradient(90deg, #2fa84f, #a9b4a9 50%, #c03fc0)',
    saturation: `linear-gradient(90deg, #8a8a8a, rgb(138 138 138 / 0)), ${RAINBOW}`,
    vibrance: `linear-gradient(90deg, #8a8a8a, rgb(138 138 138 / .35)), ${RAINBOW}`,
    exposure: DARK_LIGHT,
    contrast: 'linear-gradient(90deg, #808080, #000) top / 100% 50% no-repeat, linear-gradient(90deg, #808080, #fff) bottom / 100% 50% no-repeat',
    highlights: DARK_LIGHT,
    shadows: DARK_LIGHT,
    whites: DARK_LIGHT,
    blacks: DARK_LIGHT,
};

/* --- „FARBMISCHER" (22.11.2026) --------------------------------
   „Bei Lightroom gibt es ja diese Farbkanalregler … Farbmischer nennt sich
   das. Farbton, Sättigung, Luminanz … Kann man hier die einzelnen
   Farbkanäle leicht verschieben." Asked against a yellow cast on white
   caps that Temperatur and Tönung did not take out: they turn every tone
   alike, while the cast sat in the yellows and oranges only — Gelb and
   Orange with less Sättigung take it from the caps and leave the moss and
   the sky as they were.

   EIGHT COLOURS AT LIGHTROOM'S PLACES on the colour wheel (MIX_COLOURS,
   degrees), each with Farbton, Sättigung and Luminanz, -100 … 100. A
   pixel's hue lies between two of them, and its adjustment is blended
   between those two (smoothly), so every hue belongs to exactly two and
   all eight at the same value are one setting for the whole picture — no
   hue falls between the chairs and none is changed twice.
   - Farbton turns the hue up to 30 degrees towards its neighbours.
   - Sättigung scales the colour about the pixel's luminance, as „Farbe"
     does: -100 is the grey of the same brightness, +100 twice as strong.
     NOT WEIGHTED BY HOW COLOURFUL A PIXEL IS: a nearly white cap with a
     yellow breath is exactly what „Gelb −" is meant to reach, and scaling
     a small saturation changes little where there is little.
   - Luminanz brightens or darkens up to 1.2 stops on linear light, all
     three channels alike, so hue and saturation stay — WEIGHTED by the
     saturation (full from 25 per cent), or every grey whose noise happens
     to lean yellow would flicker brighter.
   After „Farbe" (Dynamik, Sättigung) and before the masks, which work on
   what it has made. develop.mix = { h: { red: v, … }, s: {…}, l: {…} },
   zeros left out; photo_develop_clean (includes/photos.php) holds the
   same keys in PHOTO_DEVELOP_MIX. */
export const MIX_COLOURS = [
    { key: 'red', hue: 0, name: () => t('Rot') },
    { key: 'orange', hue: 30, name: () => t('Orange') },
    { key: 'yellow', hue: 60, name: () => t('Gelb') },
    { key: 'green', hue: 120, name: () => t('Grün') },
    { key: 'aqua', hue: 180, name: () => t('Aquamarin') },
    { key: 'blue', hue: 240, name: () => t('Blau') },
    { key: 'purple', hue: 270, name: () => t('Lila') },
    { key: 'magenta', hue: 300, name: () => t('Magenta') },
];
export const MIX_KINDS = [
    { key: 'h', name: () => t('Farbton') },
    { key: 's', name: () => t('Sättigung') },
    { key: 'l', name: () => t('Luminanz') },
];
/** A mixer slider's painted line: where the knob takes that colour (DEVELOP_TRACKS' idea). */
export function mixTrack(kind, colour) {
    const h = MIX_COLOURS.find((c) => c.key === colour)?.hue ?? 0;
    if (kind === 'h') return `linear-gradient(90deg, hsl(${h - 30} 75% 52%), hsl(${h} 75% 52%) 50%, hsl(${h + 30} 75% 52%))`;
    if (kind === 's') return `linear-gradient(90deg, hsl(${h} 0% 52%), hsl(${h} 85% 52%))`;
    return `linear-gradient(90deg, hsl(${h} 70% 18%), hsl(${h} 70% 50%) 50%, hsl(${h} 70% 82%))`;
}
/* --- „GRADATIONSKURVE" (22.11.2026) ---------------------------
   „Was ist mit den Gradationskurven, die es bei Lightroom noch gibt?
   Manchmal ist es gar nicht schlecht, diese zu benutzen." Lightroom's
   point curve: one curve for all three channels (RGB) and one each for
   red, green and blue, every curve a few points through which a smooth
   line runs, input along the bottom, output up the side, 0 … 255 on the
   display-encoded picture.

   THE LINE THROUGH THE POINTS IS A MONOTONE CUBIC (Fritsch–Carlson):
   smooth like Lightroom's, but it never swings beyond its points — a
   free spline between a lifted and a lowered point overshoots, and an
   overshooting tone curve turns a tone round (brighter input, darker
   output), which reads as a solarised band. Beyond the outermost points
   the line runs flat, so moving the black point up lifts black itself.

   ONE TABLE FOR THE SHADER (curveLut): 256 entries per channel, the RGB
   curve first and the channel's own after it, laid into a 256 × 1
   texture that the shader reads with linear filtering — the picture's
   values are floats by then, and the filtering is the interpolation.
   After „Farbe" and before the mixer, as in Lightroom; not in the masks.
   develop.curve = { rgb: [[x, y], …], r, g, b }, whole numbers 0 … 255,
   x rising, 2 to CURVE_POINTS_MAX points; a channel at its diagonal
   ([[0, 0], [255, 255]]) is left out. The server's twin is
   photo_develop_clean (includes/photos.php). */
export const CURVE_CHANNELS = [
    { key: 'rgb', name: () => t('RGB') },
    { key: 'r', name: () => t('Rot') },
    { key: 'g', name: () => t('Grün') },
    { key: 'b', name: () => t('Blau') },
];
export const CURVE_POINTS_MAX = 16;
/** A channel's points cleaned: whole numbers in 0 … 255, x rising and each x once, at most CURVE_POINTS_MAX; null for the diagonal. */
export function curvePoints(list) {
    if (!Array.isArray(list)) return null;
    const byX = new Map();
    for (const p of list) {
        if (!Array.isArray(p) || !Number.isFinite(Number(p[0])) || !Number.isFinite(Number(p[1]))) continue;
        const x = Math.round(Math.min(255, Math.max(0, Number(p[0])))), y = Math.round(Math.min(255, Math.max(0, Number(p[1]))));
        byX.set(x, y);
    }
    const pts = [...byX.entries()].sort((a, b) => a[0] - b[0]).slice(0, CURVE_POINTS_MAX);
    if (pts.length < 2) return null;
    if (pts.length === 2 && pts[0][0] === 0 && pts[0][1] === 0 && pts[1][0] === 255 && pts[1][1] === 255) return null;
    return pts;
}
/** Whether any curve is bent. */
export function curveSet(d) {
    return CURVE_CHANNELS.some((c) => curvePoints(d?.curve?.[c.key]));
}
/** The line through a channel's points (monotone cubic), as a function of 0 … 255; the diagonal without points. */
export function curveFunction(list) {
    const pts = curvePoints(list);
    if (!pts) return (x) => x;
    const n = pts.length;
    const xs = pts.map((p) => p[0]), ys = pts.map((p) => p[1]);
    const d = [];
    for (let i = 0; i < n - 1; i++) d.push((ys[i + 1] - ys[i]) / (xs[i + 1] - xs[i]));
    const m = new Array(n);
    m[0] = d[0];
    m[n - 1] = d[n - 2];
    for (let i = 1; i < n - 1; i++) m[i] = d[i - 1] * d[i] <= 0 ? 0 : (d[i - 1] + d[i]) / 2;
    for (let i = 0; i < n - 1; i++) {
        if (d[i] === 0) { m[i] = 0; m[i + 1] = 0; continue; }
        const a = m[i] / d[i], b = m[i + 1] / d[i], h = a * a + b * b;
        if (h > 9) { const t3 = 3 / Math.sqrt(h); m[i] = t3 * a * d[i]; m[i + 1] = t3 * b * d[i]; }
    }
    return (x) => {
        if (x <= xs[0]) return ys[0];
        if (x >= xs[n - 1]) return ys[n - 1];
        let i = 0;
        while (xs[i + 1] < x) i++;
        const h = xs[i + 1] - xs[i], t = (x - xs[i]) / h, t2 = t * t, t3 = t2 * t;
        return (2 * t3 - 3 * t2 + 1) * ys[i] + (t3 - 2 * t2 + t) * h * m[i] + (-2 * t3 + 3 * t2) * ys[i + 1] + (t3 - t2) * h * m[i + 1];
    };
}
/** The table the shader reads: per input 0 … 255 the output of RGB then the channel, as RGBA bytes; null where no curve is bent. */
export function curveLut(d) {
    if (!curveSet(d)) return null;
    const all = curveFunction(d.curve.rgb), f = ['r', 'g', 'b'].map((k) => curveFunction(d.curve[k]));
    const out = new Uint8Array(256 * 4);
    for (let i = 0; i < 256; i++) {
        const v = all(i);
        for (let c = 0; c < 3; c++) out[i * 4 + c] = Math.round(Math.min(255, Math.max(0, f[c](v))));
        out[i * 4 + 3] = 255;
    }
    return out;
}

/* --- „FARBKORREKTUR NACH HELLIGKEIT" (22.11.2026) ------------
   Step two against the same yellow cast: measured on his pictures, the
   greys lean yellow-orange in the middle and dark tones and the brightest
   parts of the white caps slightly blue — a cast that changes with the
   brightness, which Temperatur and Tönung (every tone alike) cannot
   follow. Lightroom calls it colour grading; here it is three ranges —
   Lichter, Mitteltöne, Tiefen — each with
   - Farbton 0 … 360: the direction the range is pushed in (the colour
     wheel's degrees, the opposite of a cast takes it out),
   - Stärke 0 … 100: how far — at 100 the channel furthest along that
     direction gains a stop on linear light, the others in proportion,
     and the pixel's luminance is put back, so it tints and never
     brightens,
   - Luminanz -100 … 100: the range brighter or darker, up to a stop;
   and Abgleich -100 … 100 below them, which moves the border between the
   ranges: plus gives the highlights more of the picture, minus the
   shadows.
   THE RANGES ARE WEIGHTS THAT ADD UP TO ONE, read off the pixel's
   brightness after everything before (display luma): Tiefen from black
   down to nothing at the pivot, Lichter from nothing at the pivot up to
   white, Mitteltöne what is left — greatest at the pivot. So the three
   at the same setting are one setting for the whole picture. Gains on
   linear light, as the white balance's: black stays black.
   A BURNED-OUT WHITE STAYS WHITE: where a channel reaches the last four
   per cent, the push fades out — a clipped spot of a Helicon stack holds
   no colour, and pushed blue it came out light blue (measured: 255 white
   to 240/255/255 at Lichter 220°/40). The Luminanz still reaches it.
   After the mixer, before the masks. develop.grade = { highlights: { h,
   s, l }, midtones: {…}, shadows: {…}, balance }, zeros left out; the
   server's twin is photo_develop_clean (includes/photos.php). */
export const GRADE_RANGES = [
    { key: 'highlights', name: () => t('Lichter') },
    { key: 'midtones', name: () => t('Mitteltöne') },
    { key: 'shadows', name: () => t('Tiefen') },
];
export const HUE_TRACK = 'linear-gradient(90deg, hsl(0 75% 52%), hsl(60 75% 52%), hsl(120 75% 52%), hsl(180 75% 52%), hsl(240 75% 52%), hsl(300 75% 52%), hsl(360 75% 52%))';
/** Whether a range or the whole of the grading holds anything. */
export function gradeSet(d) {
    const g = d?.grade;
    return !!g && GRADE_RANGES.some((r) => ['h', 's', 'l'].some((k) => Number(g[r.key]?.[k]) || 0));
}
// The direction of a hue: its fully saturated colour less its luminance, the largest channel at ±1.
function hueDirection(h) {
    const k = (n) => {
        const x = (n + h / 60) % 6;
        return 1 - Math.max(0, Math.min(x, 4 - x, 1));
    };
    const c = [k(5), k(3), k(1)];
    const y = .2126 * c[0] + .7152 * c[1] + .0722 * c[2];
    const dir = c.map((v) => v - y);
    const top = Math.max(...dir.map(Math.abs)) || 1;
    return dir.map((v) => v / top);
}
/**
 * The grading as the shader takes it: per range (shadows, midtones,
 * highlights — the shader's order) the push in stops per channel and the
 * luminance in stops; the balance -1 … 1. Null where nothing is set.
 */
export function gradeUniform(d) {
    if (!gradeSet(d)) return null;
    const ranges = new Float32Array(12);
    ['shadows', 'midtones', 'highlights'].forEach((key, i) => {
        const r = d.grade[key] ?? {};
        const s = Math.max(0, Math.min(100, Number(r.s) || 0)) / 100;
        const dir = hueDirection(((Number(r.h) || 0) % 360 + 360) % 360);
        ranges.set([dir[0] * s, dir[1] * s, dir[2] * s, Math.max(-100, Math.min(100, Number(r.l) || 0)) / 100], i * 4);
    });
    return { ranges, balance: Math.max(-100, Math.min(100, Number(d.grade.balance) || 0)) / 100 };
}

/** Whether anything of the mixer is set. */
export function mixSet(d) {
    const m = d?.mix;
    return !!m && MIX_KINDS.some((k) => MIX_COLOURS.some((c) => Number(m[k.key]?.[c.key]) || 0));
}
/** The mixer as the shader takes it: per colour hue, saturation, luminance as -1 … 1; null where nothing is set. */
export function mixUniform(d) {
    if (!mixSet(d)) return null;
    const out = new Float32Array(MIX_COLOURS.length * 3);
    MIX_COLOURS.forEach((c, i) => MIX_KINDS.forEach((k, j) => {
        out[i * 3 + j] = Math.max(-100, Math.min(100, Number(d.mix[k.key]?.[c.key]) || 0)) / 100;
    }));
    return out;
}

/**
 * A mask's own sliders — the twin of PHOTO_DEVELOP_RADIAL in includes/photos.php.
 * ALL OF THE PICTURE'S, IN ITS GROUPS (11.11.2026: first
 * „Dunst entfernen" on a brush, then „die anderen Detaileinstellungen",
 * then „nimm alle dazu und gruppiere sie auch so"): a mask is a small
 * development of its own behind its shape, and the panel draws it with the
 * same heads Farbe, Licht, Details (photowindow.js, developGroupsDraw).
 */
export const RADIAL_SLIDERS = DEVELOP_GROUPS.flatMap((g) => g.sliders);
/** The masks in all, ellipses and gradients together — one set of uniform arrays in the shader. */
export const RADIALS_MAX = 12;

/** A new ellipse in the middle of the picture: a quarter of its width across, its edge soft. */
export function radialNew(x = .5, y = .5, rx = .2, ry = .14) {
    return { x, y, rx, ry, angle: 0, feather: 50, invert: false };
}

/* THE GRADIENT („Verlauf", 07.11.2026), Lightroom's linear one:
   the full effect up to the line through (x1|y1), none from the line
   through (x2|y2), both lines square to the way from the one point to the
   other, and between them the soft passage. Points as shares of width and
   height, like an ellipse's centre. */
export function linearNew(x1 = .5, y1 = .2, x2 = .5, y2 = .45) {
    return { x1, y1, x2, y2, invert: false };
}

/* THE BRUSH („Pinsel", 10.11.2026: „Bei den Masken fehlt noch
   ein Stiftwerkzeug"), Lightroom's painted mask: { strokes: [{ path,
   radius, feather, flow, erase? }], invert, … its own sliders }. Each
   stroke is a path as the spots have it (develop.js, spotsApply) — points
   as shares of width and height, the radius a share of the width —, so a
   painted mask is a description too and not a picture. A stroke lays its
   flow (0 … 100) on what was painted before, a soft rim over its outer
   feather per cent; an erasing stroke takes away as much. AT MOST FOUR:
   the renderer keeps them in the four channels of one texture. */
export const BRUSHES_MAX = 4;
export function brushNew() {
    return { strokes: [], invert: false };
}

/** All masks of a development that act, ellipses first, then gradients, then brushes — the order the renderer packs them in.
 *  A mask switched off by its eye (off: true, 09.11.2026) stays in the list and does nothing. */
export function masksOf(d) {
    return [
        ...(d?.radials ?? []).map((m) => ({ kind: 'radial', m })),
        ...(d?.linears ?? []).map((m) => ({ kind: 'linear', m })),
        ...(d?.brushes ?? []).slice(0, BRUSHES_MAX).map((m) => ({ kind: 'brush', m })),
    // An EMPTY mask — made by a click, not yet drawn (photowindow.js, maskCreate) — acts nowhere.
    ].filter(({ m }) => !m.off && !m.empty).slice(0, RADIALS_MAX);
}

/* --- Turning and cropping (stage 2, 07.11.2026) -----------------
   develop.geo = { turn: 0|90|180|270, angle: -45 … 45, crop: {x, y, w, h} }.
   The picture is turned by turn + angle (clockwise, as one sees it) about
   the crop's centre; what shows is the crop: an upright rectangle w × h
   around that centre. x and y are shares of the ORIGINAL's width and
   height, w and h both shares of its WIDTH, like the ellipses' radii —
   so every number speaks in the original's pixels, and the measures,
   stored there, never move. Without a crop the whole turned picture shows,
   cut to the largest upright rectangle inside it once it is straightened.

   MIRRORED (09.11.2026: „horizontal spiegeln, vertikal
   spiegeln"): flip 'h' | 'v' | 'hv' mirrors the ORIGINAL about the crop's
   centre BEFORE it is turned — view = turn ∘ mirror. Mirrored first, every
   turning tool keeps working as one sees it: a quarter turn to the right
   still goes right, a drag on a corner still follows the hand, a line
   drawn to straighten still means what it shows. A button that mirrors
   what one SEES (geoMirrorShown) therefore also turns the other way: a
   mirror and a turn swap places as mirror ∘ turn(R) = turn(−R) ∘ mirror. */
const flipOf = (g) => ({ fx: String(g?.flip ?? '').includes('h') ? -1 : 1, fy: String(g?.flip ?? '').includes('v') ? -1 : 1 });

/** The geo mirrored as the picture is SEEN — 'h' left to right, 'v' top to bottom. */
export function geoMirrorShown(g, axis) {
    const has = new Set(String(g.flip ?? '').split('').filter((c) => c === 'h' || c === 'v'));
    if (has.has(axis)) has.delete(axis); else has.add(axis);
    const flip = ['h', 'v'].filter((c) => has.has(c)).join('');
    const out = { ...g, turn: (360 - ((Number(g.turn) || 0) % 360)) % 360, angle: -(Number(g.angle) || 0) || 0 };
    if (flip) out.flip = flip; else delete out.flip;
    return out;
}

/** The largest upright rectangle of the turned picture's shape inside it, turned by `angle` more. */
export function cropFit(turn, angle, W, H) {
    const side = turn % 180 !== 0;
    const tw = side ? H : W, th = side ? W : H;
    const a = Math.abs(angle) * Math.PI / 180;
    const k = Math.min(tw / (tw * Math.cos(a) + th * Math.sin(a)), th / (tw * Math.sin(a) + th * Math.cos(a)));
    return { x: .5, y: .5, w: tw * k / W, h: th * k / W };
}

/** The whole turned picture, nothing cut — what the crop is drawn over while one crops. `room`: air
 *  round it as a share of its width and height on every side, for a crop that may reach past it („Erweitern"). */
export function cropWhole(turn, angle, W, H, room = 0) {
    const r = (turn + angle) * Math.PI / 180;
    const c = Math.abs(Math.cos(r)), s = Math.abs(Math.sin(r));
    return { x: .5, y: .5, w: (W * c + H * s) / W * (1 + 2 * room), h: (W * s + H * c) / W * (1 + 2 * room) };
}

/**
 * The view of a picture W × H under its settings: the output's size (cw ×
 * ch, original pixels), the turn R in degrees, the centre, and the two
 * ways between original and view. `whole` shows the whole turned picture
 * (while cropping) — true, or a number: with that much air round it
 * (cropWhole's `room`).
 */
export function geoView(d, W, H, whole = false) {
    const g = d?.geo ?? null;
    const turn = ((Number(g?.turn) || 0) % 360 + 360) % 360;
    const angle = Number(g?.angle) || 0;
    const crop = whole !== false ? cropWhole(turn, angle, W, H, whole === true ? 0 : Number(whole) || 0) : (g?.crop ?? cropFit(turn, angle, W, H));
    const R = turn + angle;
    const cx = crop.x * W, cy = crop.y * H, cw = crop.w * W, ch = crop.h * W;
    const rad = R * Math.PI / 180, cos = Math.cos(rad), sin = Math.sin(rad);
    const { fx, fy } = flipOf(g);
    const identity = R === 0 && fx === 1 && fy === 1 && Math.abs(cx - W / 2) < .5 && Math.abs(cy - H / 2) < .5
        && Math.abs(cw - W) < .5 && Math.abs(ch - H) < .5;
    return {
        /* fx, fy: the mirror (−1 on a mirrored axis); a drawing that must
           stay readable — a word — mirrors itself back about its own place
           by the same (photowindow.js, viewUnmirror). */
        identity, R, cx, cy, cw, ch, W, H, fx, fy,
        /** Original pixels to the view's (top left = 0): mirrored, then turned. */
        toView: ([x, y]) => {
            const dx = (x - cx) * fx, dy = (y - cy) * fy;
            return [dx * cos - dy * sin + cw / 2, dx * sin + dy * cos + ch / 2];
        },
        /** The view's pixels back to the original's. */
        toNat: ([x, y]) => {
            const dx = x - cw / 2, dy = y - ch / 2;
            return [(dx * cos + dy * sin) * fx + cx, (-dx * sin + dy * cos) * fy + cy];
        },
        /** The same as an SVG transform, for a group drawn in original pixels. */
        svg: `translate(${cw / 2} ${ch / 2}) rotate(${R})${fx < 0 || fy < 0 ? ` scale(${fx} ${fy})` : ''} translate(${-cx} ${-cy})`,
    };
}

/** Turned or cut at all. */
export function geoSet(d) {
    const g = d?.geo;
    return !!g && ((Number(g.turn) || 0) % 360 !== 0 || (Number(g.angle) || 0) !== 0 || !!g.crop || !!g.flip);
}

/* --- „Erweitern": a crop past the picture, its border filled (15.11.2026) ---
   After a day with a test page for it: „Super, bau das in unser
   Zuschneiden, Drehen, Werkzeug ein." With „Erweitern" the crop may reach
   past the picture, and what lies outside is invented by the models
   (expand.js says how). What they made is

     geo.fill = { w, h, pieces: [{ id, c, r }], at, fine?, pick? }

   ONE IMAGE OVER THE CROP: w × h pixels standing for the crop's whole
   rectangle, whatever size the picture is drawn at — opaque where the crop
   shows no picture, fading out over a few pixels into the picture beside
   it, transparent everywhere else. It is drawn OVER the turned picture and
   UNDER every slider and mask: the border was invented to continue the
   original, so it is developed with it.

   STORED AS THE PATCHES OF „ENTFERNEN" ARE: cut into squares of 512 from
   its top left corner — column c, row r —, each a PNG in the picture's
   cloud under its id; an empty square is not stored. So everything that
   carries those patches carries these: the save, the fetch, the tidying,
   the move to another cloud (aiPatchIds; includes/develop_patches.php).

   `at` IS THE CROP IT WAS MADE FOR (geoSig). A fill fits one cut exactly;
   the tool takes it out when the cut changes (photowindow.js), and nothing
   draws a fill whose `at` is not the geo it stands in — a border laid over
   another cut would be plainly wrong, an empty one is only empty.

   EVERY AREA OF THE BORDER CHOOSES ITS MODEL (15.11.2026: „ich
   glaube, das starke Modell ist manchmal gar nicht die bessere Wahl … es
   wäre besser, wenn man pro Fläche selbst entscheiden kann, welche Version
   man will. Es wird natürlich erstmal mit dem schnellen Modell gerechnet
   und dann kann man ja nochmal testen, ob beim Drehen zum Beispiel
   irgendein Eck doch mit der anderen KI besser kommt"). So the image above
   is always the QUICK model's, whole, and beside it stand

     fill.fine = [{ area, w, h, pieces, level, strength }]
     fill.pick = [area, …]

   `fine`: what the strong model made of ONE area — an image over the same
   rectangle, at its own size (`level` stages, `strength` per cent of
   „Freiheit beim Verfeinern"), holding that area alone; at most one per
   area, a new run replaces it. `pick`: the areas shown from `fine`; all
   others show the quick model's. Both fills of an area stay stored, as
   both fills of a place of „Entfernen" do, so one goes back and forth
   without computing anything.

   AN AREA IS A PIECE OF THE BORDER THAT HANGS TOGETHER — the four corners
   a turn leaves, the strip a dragged edge leaves — and its NUMBER comes
   from the quick model's image: wherever that is not transparent, counted
   from the top left, row by row (fillAreas). Nothing else names an area,
   so the image is the truth and every browser counts alike: it is stored
   without loss, and no scaling goes into the count.

   `strong: true` on the fill itself is the form of the first day, when the
   strong model REPLACED the border: such a fill is drawn as it stands and
   has no areas to choose (photowindow.js offers to fill it anew).

   A fill under way or just computed carries `image` — its canvas as it
   stands — and `rev`, which grows with every change; neither is ever
   stored. */

/** A fill may be cut into this many patches — a crop twice the picture's size at the finest stage stays below it. */
export const FILL_PIECES_MAX = 400;
/** A fill's longest side: the largest stage of a crop that reaches as far as the tool lets it. */
const FILL_EDGE_MAX = 16384;
/** A border falls into this many areas at the most; what hangs together beyond them stays the quick model's. */
export const FILL_AREAS_MAX = 12;
/** Fewer pixels of the quick model's image than this are no area: a sliver along an edge, left by rounding. */
const FILL_AREA_LEAST = 64;

/** The cut a fill belongs to, as one word — rounded as the settings are stored (developClean). */
export function geoSig(g) {
    if (!g?.crop) return '';
    const n = (v) => String(Math.round((Number(v) || 0) * 10000) / 10000);
    return [((Number(g.turn) || 0) % 360 + 360) % 360, String(Math.round((Number(g.angle) || 0) * 10) / 10),
        ['h', 'v', 'hv'].includes(g.flip) ? g.flip : '', n(g.crop.x), n(g.crop.y), n(g.crop.w), n(g.crop.h)].join('|');
}

/* Does the crop reach past the picture — by more than four thousandths of
   its width? NOT BY HALF A PIXEL, which is what the crop tool allows a crop
   it pulled inside: stored, a crop is rounded to a ten-thousandth, and
   every picture cut before „Erweitern" existed would count as reaching out
   by a hair and be handed to a model for a border one pixel wide. Below
   this measure the tool pulls a crop inside as it always did
   (photowindow.js, cropHeld); above it there is a border worth filling. */
export function cropOutside(g, W, H) {
    if (!g?.crop || !W || !H) return false;
    const view = geoView({ geo: g }, W, H), by = Math.max(4, W * .004);
    return [[0, 0], [view.cw, 0], [0, view.ch], [view.cw, view.ch]].some((q) => {
        const [x, y] = view.toNat(q);
        return x < -by || y < -by || x > W + by || y > H + by;
    });
}

/** The fill of these settings, where it belongs to the cut as it stands — else null. */
export function geoFill(d) {
    const g = d?.geo, f = g?.fill;
    if (!f || !g.crop || f.at !== geoSig(g)) return null;
    if (!f.image && !(f.pieces ?? []).length) return null;
    return f;
}

/** The patches of one image of a fill, as stored: valid ids, each square once, inside w × h. */
function fillPiecesClean(list, w, h) {
    const pieces = [], seen = new Set();
    for (const p of Array.isArray(list) ? list : []) {
        const c = Math.round(Number(p?.c)), r = Math.round(Number(p?.r));
        if (!p || !AI_PATCH_ID.test(String(p.id ?? '')) || !(c >= 0) || !(r >= 0) || c * AI_SIDE >= w || r * AI_SIDE >= h || seen.has(c + ',' + r)) continue;
        seen.add(c + ',' + r);
        pieces.push({ id: String(p.id), c, r });
    }
    return pieces;
}

/** The fill as stored: its size, its patches by column and row, the cut it was made for — and what the strong model made of single areas. */
function fillClean(f, g, room) {
    if (!f || typeof f !== 'object' || !g.crop) return null;
    const size = (o) => {
        const w = Math.round(Number(o?.w) || 0), h = Math.round(Number(o?.h) || 0);
        return w >= 1 && h >= 1 && w <= FILL_EDGE_MAX && h <= FILL_EDGE_MAX ? [w, h] : null;
    };
    const wh = size(f);
    if (!wh || f.at !== geoSig(g)) return null;
    let left = Math.min(room, FILL_PIECES_MAX);
    const pieces = fillPiecesClean(f.pieces, ...wh).slice(0, Math.max(0, left));
    if (!pieces.length) return null;
    left -= pieces.length;
    const out = { w: wh[0], h: wh[1], pieces, at: f.at };
    const made = (o) => ({ level: Math.min(4, Math.max(1, Math.round(Number(o.level) || 1))), strength: Math.min(100, Math.max(0, Math.round(Number(o.strength) || 0))) });
    if (f.strong) Object.assign(out, { strong: true }, made(f));
    // An area's strong fill is whole or not there: half of it would be a border with holes.
    const fine = [];
    for (const l of !f.strong && Array.isArray(f.fine) ? f.fine : []) {
        const area = Math.round(Number(l?.area)), lwh = size(l);
        if (!(area >= 1 && area <= FILL_AREAS_MAX) || !lwh || fine.some((x) => x.area === area)) continue;
        const lp = fillPiecesClean(l.pieces, ...lwh);
        if (!lp.length || lp.length > left) continue;
        left -= lp.length;
        fine.push({ area, w: lwh[0], h: lwh[1], pieces: lp, ...made(l) });
    }
    if (fine.length) {
        out.fine = fine;
        const pick = [...new Set((Array.isArray(f.pick) ? f.pick : []).map((n) => Math.round(Number(n))))]
            .filter((n) => fine.some((x) => x.area === n)).sort((a, b) => a - b);
        if (pick.length) out.pick = pick;
    }
    return out;
}

/** What the strong model made of the areas that show it (fill.pick) — nothing for a fill without. */
const fillShown = (f) => (f.fine ?? []).filter((l) => (f.pick ?? []).includes(l.area));

const piecesWord = (o) => o.w + 'x' + o.h + ':' + o.pieces.map((p) => p.id + '@' + p.c + ',' + p.r).join(' ');
/** The fill as it is drawn, as one word: its patches, and those of every area shown from the strong model. */
export function fillKey(f) {
    return [piecesWord(f), ...fillShown(f).map((l) => l.area + '=' + piecesWord(l))].join('|');
}

/** One image of a fill put together from its patches — a new canvas; null while a patch is still on its way. */
function piecesImage(o) {
    if (!o.pieces.every((p) => aiPatches.has(p.id))) return null;
    const canvas = document.createElement('canvas');
    canvas.width = o.w;
    canvas.height = o.h;
    const ctx = canvas.getContext('2d');
    for (const p of o.pieces) ctx.drawImage(aiPatches.get(p.id), p.c * AI_SIDE, p.r * AI_SIDE);
    return canvas;
}

/** `make()`'s canvas under `key`, kept for the last few asked for — a thumbnail, the viewer and a plate ask for the same one in turn. */
function kept(store, key, make) {
    const had = store.get(key);
    if (had) {
        store.delete(key);
        store.set(key, had);
        return had;
    }
    const canvas = make();
    if (!canvas) return null;
    store.set(key, canvas);
    for (const old of [...store.keys()].slice(0, -3)) {
        store.get(old).width = 0;
        store.delete(old);
    }
    return canvas;
}

/* The quick model's image of a fill, whole — what the areas are counted on
   (fillAreas) and what every area shows that is not picked. Null while a
   patch is still on its way (aiPatchesFor). */
const fillBases = new Map();
export function fillBase(f) {
    return f.image ?? kept(fillBases, piecesWord(f), () => piecesImage(f));
}

/* THE AREAS OF A BORDER: what hangs together of the quick model's image,
   numbered from 1 in the order their first pixel is met from the top left,
   row by row. Side by side counts, corner to corner does not. A sliver
   (FILL_AREA_LEAST) is no area, and neither is what comes after the
   twelfth — both keep the quick model's fill and are never offered.

   @returns {{ w, h, labels: Uint8Array, areas: { n, count, cx, cy, x0, y0, x1, y1 }[] }|null}
       labels: per pixel of the image the area's number, 0 where there is
       none; cx, cy: an area's middle, x0 … y1 its box — all as shares of
       the image's width and height. Null while the image is not here. */
const fillAreasOf = new WeakMap();
export function fillAreas(f) {
    const base = f ? fillBase(f) : null;
    if (!base || !base.width) return null;
    const had = fillAreasOf.get(base);
    if (had) return had;
    const w = base.width, h = base.height;
    const a = base.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, w, h).data;
    const labels = new Uint8Array(w * h), stack = new Int32Array(w * h), areas = [];
    const NONE = 255;
    for (let start = 0; start < labels.length; start++) {
        if (!a[start * 4 + 3] || labels[start]) continue;
        const n = areas.length < FILL_AREAS_MAX ? areas.length + 1 : NONE;
        let top = 0, count = 0, sx = 0, sy = 0, x0 = w, y0 = h, x1 = 0, y1 = 0;
        stack[top++] = start;
        labels[start] = n;
        while (top) {
            const p = stack[--top], x = p % w, y = (p - x) / w;
            count++;
            sx += x;
            sy += y;
            if (x < x0) x0 = x;
            if (x > x1) x1 = x;
            if (y < y0) y0 = y;
            if (y > y1) y1 = y;
            for (const q of [x ? p - 1 : -1, x + 1 < w ? p + 1 : -1, p - w, p + w]) {
                if (q < 0 || q >= labels.length || labels[q] || !a[q * 4 + 3]) continue;
                labels[q] = n;
                stack[top++] = q;
            }
        }
        if (n !== NONE && count >= FILL_AREA_LEAST) {
            areas.push({ n, count, cx: (sx / count + .5) / w, cy: (sy / count + .5) / h, x0: x0 / w, y0: y0 / h, x1: (x1 + 1) / w, y1: (y1 + 1) / h });
        } else if (n !== NONE) {
            // The number goes back for the next one: the sliver's pixels belong to no area.
            for (let y = y0; y <= y1; y++) for (let x = x0; x <= x1; x++) if (labels[y * w + x] === n) labels[y * w + x] = NONE;
        }
    }
    for (let p = 0; p < labels.length; p++) if (labels[p] === NONE) labels[p] = 0;
    const out = { w, h, labels, areas };
    fillAreasOf.set(base, out);
    return out;
}

/** One area as a canvas of the areas' own size: `rgba` where the area is, nothing elsewhere — to cut by, or to show it. */
export function fillAreaMask(found, n, rgba = [0, 0, 0, 255]) {
    const data = new Uint8ClampedArray(found.w * found.h * 4);
    for (let p = 0; p < found.labels.length; p++) {
        if (found.labels[p] !== n) continue;
        data[p * 4] = rgba[0];
        data[p * 4 + 1] = rgba[1];
        data[p * 4 + 2] = rgba[2];
        data[p * 4 + 3] = rgba[3];
    }
    const canvas = document.createElement('canvas');
    canvas.width = found.w;
    canvas.height = found.h;
    canvas.getContext('2d').putImageData(new ImageData(data, found.w, found.h), 0, 0);
    return canvas;
}

/**
 * A fill's image WITHOUT the areas `out` — a new canvas of w × h (the
 * image's own size where none is given): what stands round an area while
 * the strong model fills it, and what a picked area's strong fill is laid
 * into. `image`: the fill as drawn so far; `found`: its areas (fillAreas).
 */
export function fillWithout(image, found, out, w = image.width, h = image.height) {
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(image, 0, 0, w, h);
    ctx.globalCompositeOperation = 'destination-out';
    for (const n of out) {
        const mask = fillAreaMask(found, n);
        ctx.drawImage(mask, 0, 0, w, h);
        mask.width = 0;
    }
    ctx.globalCompositeOperation = 'source-over';
    return canvas;
}

/* The fill as it is DRAWN: the quick model's image, and in every picked
   area the strong model's in its place — at the size of the finest of
   them. The area is taken OUT of the quick image first, not painted over:
   the quick image fades into the picture over a few of ITS pixels, and
   under a finer fill that band would stay as a soft seam. Kept for the
   last few fills asked for; null while a patch is still on its way. */
const fillImages = new Map();
export function fillImage(f) {
    if (f.image) return f.image;
    const base = fillBase(f);
    if (!base) return null;
    const shown = fillShown(f);
    if (!shown.length) return base;
    return kept(fillImages, fillKey(f), () => {
        const found = fillAreas(f), layers = shown.map(piecesImage);
        if (!found || layers.some((l) => !l)) {
            for (const l of layers) if (l) l.width = 0;
            return null;
        }
        const w = Math.max(base.width, ...shown.map((l) => l.w)), h = Math.max(1, Math.round(w * base.height / base.width));
        const canvas = fillWithout(base, found, shown.map((l) => l.area), w, h), ctx = canvas.getContext('2d');
        for (const l of layers) {
            ctx.drawImage(l, 0, 0, w, h);
            l.width = 0;
        }
        return canvas;
    });
}

/** Nothing set: no slider off 0, no ellipse, not turned or cut — the original as it is. */
export function developEmpty(d) {
    if (!d) return true;
    for (const g of DEVELOP_GROUPS) for (const s of g.sliders) if (Number(d[s.key]) || 0) return false;
    if (geoSet(d) || d.wb || d.cast || mixSet(d) || gradeSet(d) || curveSet(d) || (d.spots ?? []).length) return false;
    return !(d.radials ?? []).length && !(d.linears ?? []).length && !(d.brushes ?? []).length;
}

/* --- „Farbstich entfernen" (14.11.2026) -----------------------
   „Ich habe oft das Phänomen, dass ich einen Blaustich … bei bewölktem
   Wetter habe … Aber das muss natürlich ein professioneller Algorithmus
   sein. Der nicht einfach das Blau wegnimmt." Temperatur and Tönung turn
   every channel as a whole, the same in every tone; a cast from the light
   is often not like that — under clouds the shadows are lit by the blue
   sky and the bright parts by the grey cloud base, so a white balance
   that mends the one spoils the other.

   WHAT IS MEASURED: the colour of the light, separately in five ranges of
   brightness (CAST_BANDS), off the places that should be grey. In each
   range the least coloured 15 per cent of the pixels are taken, their mean
   is the guess, and the choice is made again with that guess taken out —
   four rounds, so a grey that only looked coloured under the cast is
   found in the end. A green leaf or a red cap is never among them, so the
   green of the moss is not mistaken for a cast (measured on his
   Olympus picture of 13.11.2026: it found the dung ball, the wood and the
   blurred stem — the „Wo es Grau gefunden hat" view of the probe).
   NEAR-WHITE IS LEFT OUT (the same day: „bei der
   Stacking-Methode von Helicon Focus werden Lichter manchmal überbelichtet
   … ausgebrannte Bereiche sind ja nur rein weiß"): a pixel with any
   channel at 240 or more says nothing of the light — one channel clipped
   while the others still read looks like a colour and is a wrong one. The
   darkest pixels go too: there the noise decides the colour. A range with
   too few grey pixels takes the whole picture's measurement.

   HOW IT IS TAKEN OUT: not a channel turned down. Each range's light is
   adapted to neutral as the eye adapts to a light — a Bradford chromatic
   adaptation on linear light, keeping the grey's brightness —, and a
   pixel's correction is blended between the ranges beside it by its
   brightness (log scale), so no step shows. Applied on the picture as it
   came, right after the spots and before Temperatur and Tönung, which
   still work on top of it.

   develop.cast = { n: [[r, b] × 5], amount, shadows, highlights }: n the
   grey's red and blue per range against its green (linear light), the
   three sliders 0 … 100 — the whole, the dark two ranges and the bright
   two; the middle one follows both by half. Measured once and kept, so
   the picture looks the same on every device; „Neu messen" measures
   again. photo_develop_clean in includes/photos.php checks the shape. */
export const CAST_BANDS = [[.003, .02], [.02, .06], [.06, .18], [.18, .5], [.5, 1]];
// Each range's centre on a log scale — the shader holds the same five numbers (castFix).
const CAST_CENTRES = CAST_BANDS.map(([lo, hi]) => Math.log(Math.sqrt(lo * hi)));
const CAST_SHADOW_SHARE = [1, 1, .5, 0, 0];

/**
 * The grey's colour per brightness range, off RGBA pixels (a copy of the
 * picture some 1000 pixels across is plenty). Null where the picture holds
 * too little grey to say anything.
 * @returns {{ n: number[][] } | null}
 */
export function castMeasure(data, w, h) {
    const N = w * h;
    const lut = new Float32Array(256).map((_, i) => (i / 255) ** 2.2);
    const R = new Float32Array(N), G = new Float32Array(N), B = new Float32Array(N), Y = new Float32Array(N);
    const ok = new Uint8Array(N);
    for (let p = 0, i = 0; p < N; p++, i += 4) {
        R[p] = lut[data[i]]; G[p] = lut[data[i + 1]]; B[p] = lut[data[i + 2]];
        Y[p] = .2126 * R[p] + .7152 * G[p] + .0722 * B[p];
        ok[p] = data[i] < 240 && data[i + 1] < 240 && data[i + 2] < 240 && data[i + 3] > 250 && Y[p] > .003 ? 1 : 0;
    }
    const sat = new Float32Array(N);
    const estimate = (lo, hi) => {
        const idx = [];
        for (let p = 0; p < N; p++) if (ok[p] && Y[p] >= lo && Y[p] < hi) idx.push(p);
        if (idx.length < 400) return null;
        let er = 1, eb = 1;
        for (let round = 0; round < 4; round++) {
            // The least coloured 15 per cent, with the guess so far taken out — found by a histogram, not a sort.
            const bins = new Uint32Array(512);
            for (const p of idx) {
                const r = R[p] / er, g = G[p], b = B[p] / eb;
                const mx = Math.max(r, g, b);
                const v = mx > 0 ? (mx - Math.min(r, g, b)) / mx : 0;
                sat[p] = v;
                bins[Math.min(511, (v * 511) | 0)]++;
            }
            const want = Math.max(1, Math.round(idx.length * .15));
            let cut = 0, got = 0;
            while (cut < 511 && got + bins[cut] < want) got += bins[cut++];
            const limit = (cut + 1) / 511;
            let sr = 0, sg = 0, sb = 0;
            for (const p of idx) if (sat[p] <= limit) { sr += R[p]; sg += G[p]; sb += B[p]; }
            if (sg <= 0) return null;
            er = sr / sg;
            eb = sb / sg;
        }
        return [er, eb];
    };
    const whole = estimate(0, 1.01);
    if (!whole) return null;
    const n = CAST_BANDS.map(([lo, hi], i) => estimate(lo, i === CAST_BANDS.length - 1 ? 1.01 : hi) ?? whole);
    return { n: n.map(([r, b]) => [castRound(r), castRound(b)]) };
}
const castRound = (v) => Math.round(Math.min(4, Math.max(.25, v)) * 10000) / 10000;

/* THE PIPETTE (22.11.2026, step three against the yellow cast on white
   caps: „Du klickst auf etwas, das weiß oder grau sein soll, und der Stich
   wird genau für diese Helligkeit herausgenommen"). The measurement above
   takes the least coloured pixels of each range for grey — in a forest
   picture of brown wood and moss they are often not grey at all, and the
   result is off. A click says it outright.

   EVERY CLICK IS A POINT: where it was (px, py — shares of the
   original's width and height), its brightness lum (linear luma) and its
   grey's red and blue against green (r, b), as castMeasure gives them. The points make a curve over the
   brightness — log-linear between them, flat beyond the outermost — and
   the five ranges' corrections are read off that curve at their centres.
   ONE POINT IS THEREFORE A WHITE BALANCE for the whole picture; a second
   on a dark grey bends the curve so that the shadows get their own.
   BESIDE A MEASUREMENT the points win near their own brightness and
   leave the measurement standing far from it (a Gaussian over the log
   brightness, one unit wide — about one range): a click on the white cap
   mends the bright ranges and the shadows keep what was measured. The
   measurement is kept as `base` then, so points can come and go and the
   ranges are always worked out anew (castFromPicks); n stays what the
   shader reads. CAST_PICKS_MAX caps the list — photo_develop_clean holds
   the same eight. */
export const CAST_PICKS_MAX = 8;

/**
 * The grey a click names: the mean colour in linear light of a small
 * square around (px, py) of RGBA pixels w × h. Null with a reason where
 * the place says nothing: burned out (any channel at 240 or more in most
 * of it) or too dark for its colour to be more than noise.
 * @returns {{ lum: number, r: number, b: number } | { none: 'bright' | 'dark' }}
 */
export function castPickRead(data, w, h, px, py, radius = 3) {
    const lut = (v) => (v / 255) ** 2.2;
    let sr = 0, sg = 0, sb = 0, used = 0, all = 0;
    for (let y = Math.max(0, py - radius); y <= Math.min(h - 1, py + radius); y++) {
        for (let x = Math.max(0, px - radius); x <= Math.min(w - 1, px + radius); x++) {
            const i = (y * w + x) * 4;
            if (data[i + 3] < 250) continue;
            all++;
            if (data[i] >= 240 || data[i + 1] >= 240 || data[i + 2] >= 240) continue;
            sr += lut(data[i]); sg += lut(data[i + 1]); sb += lut(data[i + 2]);
            used++;
        }
    }
    if (!all || used < all / 2) return { none: 'bright' };
    const r = sr / used, g = sg / used, b = sb / used;
    const lum = .2126 * r + .7152 * g + .0722 * b;
    if (lum < .003 || g <= 0) return { none: 'dark' };
    return { lum: Math.round(lum * 100000) / 100000, r: castRound(r / g), b: castRound(b / g) };
}

/** The five ranges' greys worked out from the points and, where there is one, the measurement beneath them. */
export function castFromPicks(cast) {
    const picks = (cast.picks ?? []).filter((p) => p.lum > 0).map((p) => ({ t: Math.log(p.lum), r: Math.log(p.r), b: Math.log(p.b) }))
        .sort((a, b) => a.t - b.t);
    const base = Array.isArray(cast.base) && cast.base.length === CAST_BANDS.length ? cast.base : null;
    if (!picks.length) return (base ?? cast.n).map(([r, b]) => [castRound(r), castRound(b)]);
    const curve = (t) => {
        if (t <= picks[0].t) return picks[0];
        const last = picks[picks.length - 1];
        if (t >= last.t) return last;
        let i = 0;
        while (picks[i + 1].t < t) i++;
        const a = picks[i], z = picks[i + 1], f = (t - a.t) / Math.max(1e-6, z.t - a.t);
        return { r: a.r + (z.r - a.r) * f, b: a.b + (z.b - a.b) * f };
    };
    return CAST_CENTRES.map((c, i) => {
        const on = curve(c);
        if (!base) return [castRound(Math.exp(on.r)), castRound(Math.exp(on.b))];
        const near = Math.min(...picks.map((p) => Math.abs(p.t - c)));
        const w = Math.exp(-near * near);
        const [br, bb] = base[i];
        return [castRound(Math.exp(Math.log(br) * (1 - w) + on.r * w)), castRound(Math.exp(Math.log(bb) * (1 - w) + on.b * w))];
    });
}

const BRADFORD = [[0.8951, 0.2664, -0.1614], [-0.7502, 1.7135, 0.0367], [0.0389, -0.0685, 1.0296]];
const RGB_XYZ = [[0.4124, 0.3576, 0.1805], [0.2126, 0.7152, 0.0722], [0.0193, 0.1192, 0.9505]];
const m3mul = (a, b) => a.map((row) => [0, 1, 2].map((j) => row[0] * b[0][j] + row[1] * b[1][j] + row[2] * b[2][j]));
const m3vec = (a, v) => a.map((row) => row[0] * v[0] + row[1] * v[1] + row[2] * v[2]);
function m3inv(m) {
    const [[a, b, c], [d, e, f], [g, h, i]] = m;
    const A = e * i - f * h, B2 = -(d * i - f * g), C = d * h - e * g, det = a * A + b * B2 + c * C;
    return [[A, -(b * i - c * h), b * f - c * e], [B2, a * i - c * g, -(a * f - c * d)], [C, -(a * h - b * g), a * e - b * d]]
        .map((row) => row.map((x) => x / det));
}
const CAST_LMS = m3mul(BRADFORD, RGB_XYZ), CAST_LMS_INV = m3inv(CAST_LMS);

/**
 * The five corrections as the shader takes them — nine numbers each,
 * column by column, already blended towards „none" by the sliders. Null
 * where nothing is to be done.
 */
export function castMatrices(d) {
    const c = d?.cast;
    if (!c || !Array.isArray(c.n) || c.n.length !== CAST_BANDS.length) return null;
    const share = (k) => Math.min(100, Math.max(0, Number(c[k] ?? 100))) / 100;
    const amount = share('amount'), dark = share('shadows'), bright = share('highlights');
    if (!amount) return null;
    const out = new Float32Array(9 * CAST_BANDS.length);
    c.n.forEach(([r, b], i) => {
        const grey = [Number(r) || 1, 1, Number(b) || 1];
        const y = .2126 * grey[0] + .7152 + .0722 * grey[2];
        const from = m3vec(CAST_LMS, grey), to = m3vec(CAST_LMS, [y, y, y]);
        const m = m3mul(CAST_LMS_INV, m3mul([[to[0] / from[0], 0, 0], [0, to[1] / from[1], 0], [0, 0, to[2] / from[2]]], CAST_LMS));
        const s = amount * (CAST_SHADOW_SHARE[i] * dark + (1 - CAST_SHADOW_SHARE[i]) * bright);
        for (let col = 0; col < 3; col++) for (let row = 0; row < 3; row++) {
            const id = row === col ? 1 : 0;
            out[i * 9 + col * 3 + row] = id + (m[row][col] - id) * s;
        }
    });
    return out;
}

/* --- The white balance („Mikrofotos", 09.11.2026) --------------
   develop.wb = { r, g, b }: a gain per channel, applied to the picture as
   it came, before every other step — so „Farbe" still works on top of it.
   Measured, never typed: from the brightest parts of the picture (an empty
   bright field is the neutral thing a micrograph holds) or from a frame the
   user draws over a neutral spot (wbFromPixels). Gains keep the brightness:
   their mean is 1. */
export const WB_MIN = .25, WB_MAX = 4;

/* TEMPERATUR AND TÖNUNG ARE THE WHITE BALANCE'S OWN SLIDERS
   (12.11.2026: „lässt der sich nicht gleich bei den Schiebereglern darunter
   anzeigen. Sodass man ihn auch wieder rückgängig machen kann"). Until then
   „Weißabgleich" set hidden gains of its own and „Farbe" had two sliders
   that did the same thing a second time, later in the chain, too weakly to
   reach a micrograph's cast (±18 per cent). Now the measurement WRITES
   Temperatur and Tönung, as Lightroom's pipette does: one sees what it did,
   corrects it by hand, and a double click on the slider takes it back.

   For that both became gains applied FIRST, on the picture as it came —
   where the white balance always stood, so Licht and Kontrast work on
   neutral colours — and they are counted in STOPS per channel:
   Temperatur moves red up and blue down by s, Tönung moves green down by
   2n/3 and red and blue up by n/3, so the brightness stays. The slider's
   path is a cubic, .25 v + .75 v³ (v = slider / 100): near 0 as fine as the
   old linear sliders were (about a quarter stop at the end), at the end a
   whole stop per channel — red to blue as 4 : 1, enough for a halogen
   bright field. A cast beyond that stops at the end (wbToSliders says so).
   The OLD develop.wb is still rendered as it was, and folded into the two
   sliders as soon as the picture is opened in „Bearbeiten" (developWBFold).
   THE MASKS TOO SINCE 14.11.2026 („Masken und Pinsel mit
   umstellen", after „Farbstich entfernen" — the forest case, a green
   shadow under the leaves beside sunlit spots, wants a white balance
   painted onto the shadow). Until then a mask kept its own linear temp
   and tint late in its chain, ±18 per cent at the end, „a local nudge";
   now the same gains in stops as the picture's, on what reaches the mask,
   before its light (uRWB) — Temperatur +20 in a brush does there what
   Temperatur +20 does to the whole picture. Masks edited before look
   stronger in Temperatur and Tönung, as he chose. */
const WB_LIN = .25, WB_CUBE = .75;
function wbStops(v) {
    const x = Math.max(-1, Math.min(1, (Number(v) || 0) / 100));
    return WB_LIN * x + WB_CUBE * x * x * x;
}
/** The slider value (−100 … 100, whole) for a number of stops; clipped: whether it lay beyond the end. */
function wbSlider(stops) {
    const top = WB_LIN + WB_CUBE;
    if (Math.abs(stops) >= top) return { v: Math.sign(stops) * 100, clipped: Math.abs(stops) > top + .01 };
    let lo = -1, hi = 1;
    for (let i = 0; i < 40; i++) {
        const mid = (lo + hi) / 2;
        if (WB_LIN * mid + WB_CUBE * mid * mid * mid < stops) lo = mid; else hi = mid;
    }
    return { v: Math.round((lo + hi) * 50), clipped: false };
}
/** The gains as the renderer wants them — the old wb times Temperatur and Tönung; [1, 1, 1] for neither. */
export function developWB(d) {
    const w = d?.wb;
    const g = w ? ['r', 'g', 'b'].map((k) => Math.min(WB_MAX, Math.max(WB_MIN, Number(w[k]) || 1))) : [1, 1, 1];
    const s = wbStops(d?.temp), n = wbStops(d?.tint);
    return [g[0] * 2 ** (s + n / 3), g[1] * 2 ** (-2 * n / 3), g[2] * 2 ** (-s + n / 3)];
}
/**
 * Temperatur and Tönung for the given gains { r, g, b } (wbFromPixels), on
 * top of an earlier wb where `base` gives one. clipped: the cast reaches
 * beyond a slider's end, and the end is what is set.
 */
export function wbToSliders(gains, base = null) {
    const b = base ? ['r', 'g', 'b'].map((k) => Number(base[k]) || 1) : [1, 1, 1];
    const [r, g, bl] = ['r', 'g', 'b'].map((k, i) => Math.log2(Math.max(1e-4, (Number(gains[k]) || 1) * b[i])));
    const temp = wbSlider((r - bl) / 2), tint = wbSlider((r + bl) / 2 - g);
    return { temp: temp.v, tint: tint.v, clipped: temp.clipped || tint.clipped };
}
/**
 * An old develop.wb folded into Temperatur and Tönung (what was set there
 * before is kept on top). Changes `d` in place; true where it did.
 */
export function developWBFold(d) {
    if (!d?.wb) return false;
    const now = { r: 2 ** (wbStops(d.temp) + wbStops(d.tint) / 3), g: 2 ** (-2 * wbStops(d.tint) / 3), b: 2 ** (-wbStops(d.temp) + wbStops(d.tint) / 3) };
    const { temp, tint } = wbToSliders(now, d.wb);
    delete d.wb;
    if (temp) d.temp = temp; else delete d.temp;
    if (tint) d.tint = tint; else delete d.tint;
    return true;
}
/**
 * The gains that make the given colours neutral: RGBA pixels, every `step`th
 * one. bright: take only the brightest few per cent (the bright field), else
 * all. Clipped pixels (a channel at 250 or more) say nothing of the colour
 * and are left out. Null where nothing usable is left.
 */
export function wbFromPixels(data, bright = true, step = 1) {
    const px = [];
    for (let i = 0; i < data.length; i += 4 * step) {
        const r = data[i], g = data[i + 1], b = data[i + 2];
        if (r >= 250 || g >= 250 || b >= 250 || data[i + 3] < 250) continue;
        px.push([r, g, b, .2126 * r + .7152 * g + .0722 * b]);
    }
    if (px.length < 8) return null;
    let use = px;
    if (bright) {
        px.sort((a, b) => b[3] - a[3]);
        use = px.slice(0, Math.max(8, Math.round(px.length * .05)));
    }
    const mean = [0, 1, 2].map((c) => use.reduce((sum, p) => sum + p[c], 0) / use.length);
    if (mean.some((m) => m < 4)) return null;
    const grey = (mean[0] + mean[1] + mean[2]) / 3;
    const gains = mean.map((m) => grey / m);
    const k = 3 / (gains[0] + gains[1] + gains[2]);
    const [r, g, b] = gains.map((x) => Math.round(Math.min(WB_MAX, Math.max(WB_MIN, x * k)) * 10000) / 10000);
    return { r, g, b };
}

/* --- Retouching („Ausbessern", 10.11.2026) ----------------------
   develop.spots = [{ mode, path, radius, feather, opacity, dx, dy, off? }]:
   places mended from another part of the SAME picture, as Lightroom's spot
   removal does. AS DESCRIPTIONS, NOT AS PIXELS — he first thought of a
   transparent PNG layer and chose this when asked: every spot stays
   movable, switchable and deletable on its own, there is no second file to
   keep in a cloud (and to copy to every co-owner's), and the sidecar can
   say what was done. A painted pixel layer is left for the one thing that
   cannot be described: what a model invents (the planned AI tool).

   A spot is a PATH — one point for a click, a run of points for a brush
   stroke along a blade of grass — with a radius; the region is everything
   within the radius of the path. Points as shares of the ORIGINAL's width
   and height, the radius as a share of its WIDTH (like the ellipses). The
   source is the same region moved by (dx, dy), shares of width and height.
   Because they live in the original's pixels, turning, mirroring and
   cutting never move them, and they are applied FIRST, before the white
   balance: the picture is mended, then developed.

   TWO KINDS (his „mit beiden Arten"):
   - clone („Kopieren"): the source as it is.
   - heal („Reparieren"): the source's texture, its brightness and colour
     made to fit the surroundings — the difference between target and
     source along the region's rim, carried smoothly inside (push-pull, a
     cheap membrane), added to the source. A blade on forest litter, dust
     in a bright field.
   feather 0 … 100: the outer share of the radius over which the mended
   picture passes into the old; opacity 0 … 100.

   ON A 2D CANVAS, NOT IN THE SHADER: a stroke is a path of any length, the
   heal needs a pass over its region, and both are cheap on the few
   thousand pixels a spot covers. The renderer does it once per change of
   the spots and hands the result to the graphics card as the picture
   itself — a slider moved does not mend anew. */
/* TENFOLD SINCE 11.11.2026 (after reaching the old hundred
   with many small dabs: „wir sollten das etwas erhöhen, alles"). The
   column is MEDIUMTEXT since migration 0193; before it the server refuses
   what would not fit the old 64 KB with a word that names the migration. */
export const SPOTS_MAX = 1000;
/** All points together, the spots' and the brush strokes' — a point is about seventeen bytes. */
export const PATH_POINTS_MAX = 20000;
export const SPOT_RADIUS_MIN = .0005, SPOT_RADIUS_MAX = .15;
/** The size slider (0 … 100) and the radius, logarithmic: fine at the small end, where dust is. */
export const spotRadiusOf = (v) => SPOT_RADIUS_MIN * Math.pow(SPOT_RADIUS_MAX / SPOT_RADIUS_MIN, Math.min(100, Math.max(0, v)) / 100);
export const spotSizeOf = (r) => Math.round(Math.log(Math.min(SPOT_RADIUS_MAX, Math.max(SPOT_RADIUS_MIN, r)) / SPOT_RADIUS_MIN)
    / Math.log(SPOT_RADIUS_MAX / SPOT_RADIUS_MIN) * 100);

/* THE BRUSH AS THE HAND THINKS OF IT: A SHARP CORE AND A SOFT EDGE BEYOND
   (13.11.2026: „Die Pinselgröße ist für mich der scharfe
   Bereich, der innere Kreis. Nicht die Gesamtgröße … Ich will mit Shift
   die komplette Größe ändern des Pinsels. Mit Steuerung die weiche Kante.
   Und diese soll dann den äußeren, den weichen Kantenkreis … vergrößern
   oder verkleinern. Der innere, der scharfe bleibt").

   What is STORED stays as it was — a heal, a clone and a painted stroke
   keep { radius: the OUTER circle, feather: the outer share of it that
   fades } (spotsApply, brushField, the sidecar, the server's clamp at
   0.15) — so nothing kept anywhere changes its look. Only the tools speak
   the other language, through these two:
     brushCore(radius, share)  → { core, soft }: the core's radius and the
                                 soft edge as 0 … 100,
     brushOuter(core, soft)    → { radius, feather } to store.
   SOFT 100 REACHES `reach` CORE RADII BEYOND THE CORE — linear, so a turn
   of the wheel moves the outer circle by the same piece each time. (A
   first try the same day kept the share's own scale: towards 100 it ran
   to infinity, a stored 100 had no core left to keep, and the size slider
   moved with the edge.) reach 2 puts today's usual share of 50 at soft 50.
   A stored share above what soft 100 gives (two thirds at reach 2) reads
   as soft 100 with the core that goes with it — an old brush without any
   core gets one the moment it is touched. Where core and edge together
   would pass `max`, the outer circle stops there and the core stays.
   „Entfernen" needs neither: its radius IS its core and its feather its
   soft edge — since 22.11.2026 reaching as far as the brush's (aiReach,
   `wide`), before that half a radius at the most. */
export const SOFT_REACH = 2;
export function brushCore(radius, share, reach = SOFT_REACH) {
    const s = Math.min(reach / (1 + reach), Math.max(0, (Number(share) || 0) / 100));
    return { core: radius * (1 - s), soft: s / (1 - s) / reach * 100 };
}
export function brushOuter(core, soft, max = Infinity, reach = SOFT_REACH) {
    const c = Math.min(max, Math.max(0, Number(core) || 0));
    const f = Math.min(100, Math.max(0, Number(soft) || 0));
    const radius = Math.min(max, c * (1 + reach * f / 100));
    return { radius, feather: radius > 0 ? (1 - c / radius) * 100 : 0 };
}

/** The spots that act, in their order — a later one mends over an earlier one. */
export function spotsOf(d) {
    return (d?.spots ?? []).filter((s) => s && !s.off && Array.isArray(s.path) && s.path.length > 0
        && (s.mode !== 'ai' || (s.pieces ?? []).length > 0));
}

/** Distance of (x, y) to the path a (flat [x0, y0, x1, y1, …]), and the squared helper per segment. */
function segDist(x, y, ax, ay, bx, by) {
    const vx = bx - ax, vy = by - ay;
    const len = vx * vx + vy * vy;
    const t = len > 0 ? Math.max(0, Math.min(1, ((x - ax) * vx + (y - ay) * vy) / len)) : 0;
    return Math.hypot(x - ax - t * vx, y - ay - t * vy);
}
function pathDist(x, y, pts) {
    if (pts.length === 2) return Math.hypot(x - pts[0], y - pts[1]);
    let best = Infinity;
    for (let i = 0; i + 3 < pts.length; i += 2) best = Math.min(best, segDist(x, y, pts[i], pts[i + 1], pts[i + 2], pts[i + 3]));
    return best;
}

/** The path in pixels, no segment longer than `step` — so a long stroke can be cut into pieces. */
function pathDense(path, W, H, step) {
    const out = [];
    path.forEach(([x, y], i) => {
        const px = x * W, py = y * H;
        if (i > 0) {
            const lx = out[out.length - 2], ly = out[out.length - 1];
            const n = Math.floor(Math.hypot(px - lx, py - ly) / step);
            for (let k = 1; k <= n; k++) out.push(lx + (px - lx) * k / (n + 1), ly + (py - ly) * k / (n + 1));
        }
        out.push(px, py);
    });
    return out;
}

/**
 * FILL THE UNKNOWN BY PUSH-PULL: values (3 per pixel) where weight is 1,
 * guessed where it is 0 — averaged down a pyramid, then each level's gaps
 * taken from the coarser one, bilinearly. A few relaxation passes after it
 * smooth the blocks a pyramid leaves. Works in place on `val`.
 */
function pushPull(val, wt, w, h) {
    const levels = [{ v: val, k: Float32Array.from(wt), w, h }];
    while (levels[levels.length - 1].w > 1 || levels[levels.length - 1].h > 1) {
        const f = levels[levels.length - 1];
        const cw = Math.ceil(f.w / 2), ch = Math.ceil(f.h / 2);
        const v = new Float32Array(cw * ch * 3), k = new Float32Array(cw * ch);
        for (let j = 0; j < ch; j++) for (let i = 0; i < cw; i++) {
            let s = 0, r = 0, g = 0, b = 0;
            for (let dj = 0; dj < 2; dj++) for (let di = 0; di < 2; di++) {
                const x = 2 * i + di, y = 2 * j + dj;
                if (x >= f.w || y >= f.h) continue;
                const n = y * f.w + x, kk = f.k[n];
                if (kk <= 0) continue;
                s += kk; r += f.v[n * 3] * kk; g += f.v[n * 3 + 1] * kk; b += f.v[n * 3 + 2] * kk;
            }
            const n = j * cw + i;
            if (s > 0) { v[n * 3] = r / s; v[n * 3 + 1] = g / s; v[n * 3 + 2] = b / s; }
            k[n] = Math.min(1, s);
        }
        levels.push({ v, k, w: cw, h: ch });
    }
    for (let L = levels.length - 2; L >= 0; L--) {
        const f = levels[L], c = levels[L + 1];
        for (let y = 0; y < f.h; y++) for (let x = 0; x < f.w; x++) {
            const n = y * f.w + x, kk = f.k[n];
            if (kk >= 1) continue;
            const cx = Math.min(c.w - 1, Math.max(0, (x + .5) / 2 - .5)), cy = Math.min(c.h - 1, Math.max(0, (y + .5) / 2 - .5));
            const x0 = Math.floor(cx), y0 = Math.floor(cy), x1 = Math.min(c.w - 1, x0 + 1), y1 = Math.min(c.h - 1, y0 + 1);
            const fx = cx - x0, fy = cy - y0;
            for (let ch = 0; ch < 3; ch++) {
                const a = c.v[(y0 * c.w + x0) * 3 + ch] * (1 - fx) + c.v[(y0 * c.w + x1) * 3 + ch] * fx;
                const b = c.v[(y1 * c.w + x0) * 3 + ch] * (1 - fx) + c.v[(y1 * c.w + x1) * 3 + ch] * fx;
                f.v[n * 3 + ch] = kk * f.v[n * 3 + ch] + (1 - kk) * (a * (1 - fy) + b * fy);
            }
            f.k[n] = 1;
        }
    }
    // Relaxation: each guessed pixel the mean of its four neighbours, the known ones fixed.
    for (let pass = 0; pass < 6; pass++) {
        for (let y = 1; y < h - 1; y++) for (let x = 1; x < w - 1; x++) {
            const n = y * w + x;
            if (wt[n] >= 1) continue;
            for (let ch = 0; ch < 3; ch++) {
                val[n * 3 + ch] = (val[(n - 1) * 3 + ch] + val[(n + 1) * 3 + ch] + val[(n - w) * 3 + ch] + val[(n + w) * 3 + ch]) / 4;
            }
        }
    }
}

/**
 * The soft region of a path in the box (x0, y0, tw × th): full inside
 * (1 − feather) × r, none beyond r, smooth between; a hard edge keeps a
 * pixel of softness. `pts` flat pixels in the box's own coordinates.
 */
function pathMask(pts, r, feather, x0, y0, tw, th) {
    const x1 = x0 + tw, y1 = y0 + th;
    const inner = Math.min(r * (1 - feather / 100), r - .75);
    const m = new Float32Array(tw * th);
    for (let i = 0; i < pts.length; i += 2) {
        const ax = pts[i], ay = pts[i + 1];
        const bx = i + 3 < pts.length ? pts[i + 2] : ax, by = i + 3 < pts.length ? pts[i + 3] : ay;
        const bx0 = Math.max(x0, Math.floor(Math.min(ax, bx) - r)), bx1 = Math.min(x1, Math.ceil(Math.max(ax, bx) + r));
        const by0 = Math.max(y0, Math.floor(Math.min(ay, by) - r)), by1 = Math.min(y1, Math.ceil(Math.max(ay, by) + r));
        for (let y = by0; y < by1; y++) for (let x = bx0; x < bx1; x++) {
            const d = segDist(x + .5, y + .5, ax, ay, bx, by);
            if (d >= r) continue;
            const t = d <= inner ? 0 : (d - inner) / (r - inner);
            const v = 1 - t * t * (3 - 2 * t);
            const n = (y - y0) * tw + (x - x0);
            if (v > m[n]) m[n] = v;
        }
    }
    return m;
}

/**
 * The inside of a closed path laid into a mask at full strength — the
 * „Entfernen" spot that was drawn round its thing (`fill`). Even-odd, row
 * by row through each pixel's middle; `pts` flat, in the box's coordinates
 * like pathMask's, the path closing from its last point to its first.
 */
function polygonFill(m, pts, x0, y0, tw, th) {
    const n = pts.length / 2;
    if (n < 3) return;
    const xs = [];
    for (let y = 0; y < th; y++) {
        const py = y0 + y + .5;
        xs.length = 0;
        for (let i = 0, j = n - 1; i < n; j = i++) {
            const ay = pts[i * 2 + 1], by = pts[j * 2 + 1];
            if ((ay > py) === (by > py)) continue;
            const ax = pts[i * 2], bx = pts[j * 2];
            xs.push(ax + (py - ay) / (by - ay) * (bx - ax));
        }
        xs.sort((a, b) => a - b);
        for (let k = 0; k + 1 < xs.length; k += 2) {
            const from = Math.max(0, Math.ceil(xs[k] - x0 - .5)), to = Math.min(tw - 1, Math.floor(xs[k + 1] - x0 - .5));
            for (let x = from; x <= to; x++) m[y * tw + x] = 1;
        }
    }
}

/** Is (x, y) inside the closed path `pts` (flat pairs)? Even-odd, as polygonFill fills. */
export function pointInPath(x, y, pts) {
    let inside = false;
    for (let i = 0, n = pts.length / 2, j = n - 1; i < n; j = i++) {
        const ax = pts[i * 2], ay = pts[i * 2 + 1], bx = pts[j * 2], by = pts[j * 2 + 1];
        if ((ay > y) !== (by > y) && x < ax + (y - ay) / (by - ay) * (bx - ax)) inside = !inside;
    }
    return inside;
}

/**
 * DRAWN ROUND, NOT OVER (11.11.2026: „bei Photoshop wird das
 * automatisch gemacht … dass man quasi nur die Kontur umfährt"; for
 * „Reparieren" too the same evening). A stroke of „Entfernen" or
 * „Reparieren" — and „Kopieren", asked for the same day — that ends where
 * it began encloses what it went round:
 * the end lies within three brush radii of the start, or an eighth of the
 * way (a hand going round rarely meets its start: a ring left open by a
 * twentieth did not count at first, measured 11.11.2026, while three
 * quarters of a ring must stay a stroke), the way is at least four times
 * that gap long, and
 * the enclosed area is more than the brush itself covers — otherwise a
 * hook or a dab would turn into a fill. `pts` as pairs in pixels.
 */
export function spotLoopCloses(pts, r) {
    if (pts.length < 4) return false;
    let length = 0, area = 0;
    for (let i = 1; i < pts.length; i++) length += Math.hypot(pts[i][0] - pts[i - 1][0], pts[i][1] - pts[i - 1][1]);
    for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) area += (pts[j][0] + pts[i][0]) * (pts[j][1] - pts[i][1]);
    const gap = Math.hypot(pts.at(-1)[0] - pts[0][0], pts.at(-1)[1] - pts[0][1]);
    return gap <= Math.max(3 * r, length / 8) && length >= 4 * gap && Math.abs(area) / 2 > Math.PI * r * r;
}

/**
 * A SOURCE TURNED (11.11.2026: „beim Kopieren … dass ich das
 * kopierte Bild drehe. Das ist manchmal wichtig, wenn man einen Hutrand oder
 * einen Stiel ausbessern will — man nimmt irgendwo ein Stückchen Stiel, das
 * aber in einem anderen Winkel steht"). `rot` in degrees on a heal or clone
 * spot: what is copied appears turned by it, clockwise, about the middle of
 * the place (its box's centre, `c`); the source's own outline therefore
 * stands turned the other way. One mapping for drawing and for sampling:
 * the target point p takes its pixel from c + offset + R(−rot)·(p − c).
 *
 * @returns {(x: number, y: number) => [number, number]}  target pixel → source pixel
 */
export function spotSourceMap(path, W, H, dx, dy, rot) {
    let minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
    for (const [x, y] of path) {
        minx = Math.min(minx, x * W); maxx = Math.max(maxx, x * W);
        miny = Math.min(miny, y * H); maxy = Math.max(maxy, y * H);
    }
    const cx = (minx + maxx) / 2, cy = (miny + maxy) / 2;
    const ox = (Number(dx) || 0) * W, oy = (Number(dy) || 0) * H;
    const a = (Number(rot) || 0) * Math.PI / 180, cos = Math.cos(a), sin = Math.sin(a);
    if (!a) return (x, y) => [x + ox, y + oy];
    return (x, y) => {
        const u = x - cx, v = y - cy;
        return [cx + ox + cos * u + sin * v, cy + oy - sin * u + cos * v];
    };
}

/** Mend one piece of a spot: `pts` flat pixels, the rest in pixels as well; `map` turns the source (spotSourceMap). */
function spotPiece(ctx, W, H, pts, r, ox, oy, heal, feather, opacity, fill = false, map = null) {
    const margin = heal ? Math.ceil(Math.max(3, r * .3)) : 1;
    let minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
    for (let i = 0; i < pts.length; i += 2) {
        minx = Math.min(minx, pts[i]); maxx = Math.max(maxx, pts[i]);
        miny = Math.min(miny, pts[i + 1]); maxy = Math.max(maxy, pts[i + 1]);
    }
    const x0 = Math.max(0, Math.floor(minx - r - margin)), y0 = Math.max(0, Math.floor(miny - r - margin));
    const x1 = Math.min(W, Math.ceil(maxx + r + margin)), y1 = Math.min(H, Math.ceil(maxy + r + margin));
    const tw = x1 - x0, th = y1 - y0;
    if (tw <= 0 || th <= 0) return;
    /* The source's rectangle, cut to the picture; a source past the edge
       repeats the edge. Turned, it is the box around the turned corners. */
    let sx0 = x0 + ox, sy0 = y0 + oy, sx1 = x1 + ox, sy1 = y1 + oy;
    if (map) {
        const corners = [map(x0, y0), map(x1, y0), map(x0, y1), map(x1, y1)];
        sx0 = Math.floor(Math.min(...corners.map((c) => c[0]))) - 1; sx1 = Math.ceil(Math.max(...corners.map((c) => c[0]))) + 2;
        sy0 = Math.floor(Math.min(...corners.map((c) => c[1]))) - 1; sy1 = Math.ceil(Math.max(...corners.map((c) => c[1]))) + 2;
    }
    sx0 = Math.max(0, sx0); sy0 = Math.max(0, sy0); sx1 = Math.min(W, sx1); sy1 = Math.min(H, sy1);
    if (sx1 <= sx0 || sy1 <= sy0) return;
    const T = ctx.getImageData(x0, y0, tw, th);
    const S = ctx.getImageData(sx0, sy0, sx1 - sx0, sy1 - sy0);
    const sw = sx1 - sx0, sh = sy1 - sy0, sd = S.data;
    /* One source pixel into `px` for the target pixel (x, y) of the box:
       straight, the pixel itself; turned, between its four neighbours —
       taken nearest, a turned edge would come out as steps. */
    const px = [0, 0, 0];
    const sAt = map ? (x, y) => {
        const [fx, fy] = map(x0 + x + .5, y0 + y + .5);
        const gx = Math.min(sw - 1.001, Math.max(0, fx - .5 - sx0)), gy = Math.min(sh - 1.001, Math.max(0, fy - .5 - sy0));
        const ix = gx | 0, iy = gy | 0, tx = gx - ix, ty = gy - iy;
        const i00 = (iy * sw + ix) * 4, i10 = i00 + 4, i01 = i00 + sw * 4, i11 = i01 + 4;
        for (let c = 0; c < 3; c++) {
            px[c] = (sd[i00 + c] * (1 - tx) + sd[i10 + c] * tx) * (1 - ty) + (sd[i01 + c] * (1 - tx) + sd[i11 + c] * tx) * ty;
        }
        return px;
    } : (x, y) => {
        const X = Math.min(sw - 1, Math.max(0, x0 + x + ox - sx0)), Y = Math.min(sh - 1, Math.max(0, y0 + y + oy - sy0));
        const i = (Y * sw + X) * 4;
        px[0] = sd[i]; px[1] = sd[i + 1]; px[2] = sd[i + 2];
        return px;
    };
    const m = pathMask(pts, r, feather, x0, y0, tw, th);
    // Drawn round (spotLoopCloses): the inside is mended too, fully.
    if (fill) polygonFill(m, pts, x0, y0, tw, th);
    const a = opacity / 100;
    const out = T.data;
    if (!heal) {
        for (let n = 0; n < m.length; n++) {
            if (m[n] <= 0) continue;
            const k = m[n] * a, s = sAt(n % tw, (n / tw) | 0);
            for (let c = 0; c < 3; c++) out[n * 4 + c] += (s[c] - out[n * 4 + c]) * k;
        }
        ctx.putImageData(T, x0, y0);
        return;
    }
    // Heal: target minus source, known only outside the region (its rim), carried inside.
    const diff = new Float32Array(tw * th * 3), wt = new Float32Array(tw * th);
    for (let n = 0; n < m.length; n++) {
        if (m[n] > 0) continue;
        const s = sAt(n % tw, (n / tw) | 0);
        for (let c = 0; c < 3; c++) diff[n * 3 + c] = out[n * 4 + c] - s[c];
        wt[n] = 1;
    }
    pushPull(diff, wt, tw, th);
    for (let n = 0; n < m.length; n++) {
        if (m[n] <= 0) continue;
        const k = m[n] * a, s = sAt(n % tw, (n / tw) | 0);
        for (let c = 0; c < 3; c++) out[n * 4 + c] += (s[c] + diff[n * 3 + c] - out[n * 4 + c]) * k;
    }
    ctx.putImageData(T, x0, y0);
}

/**
 * Mend the spots into a 2D context holding the picture at W × H. A long
 * stroke is mended in pieces of at most a few megapixels' box, each
 * overlapping the last by a point, so a diagonal blade across the whole
 * picture never asks for its whole box at once.
 */
export function spotsApply(ctx, W, H, spots) {
    for (const s of spots) {
        if (s.mode === 'ai') {
            for (const piece of s.pieces ?? []) aiPieceApply(ctx, W, H, s, piece);
            continue;
        }
        const r = Math.max(.75, (Number(s.radius) || .01) * W);
        const ox = Math.round((Number(s.dx) || 0) * W), oy = Math.round((Number(s.dy) || 0) * H);
        const rot = Number(s.rot) || 0;
        if (!ox && !oy && !rot) continue;
        const heal = s.mode !== 'clone';
        const feather = Math.min(100, Math.max(0, Number(s.feather ?? 50)));
        const opacity = Math.min(100, Math.max(0, Number(s.opacity ?? 100)));
        if (!opacity) continue;
        // Turned about the middle of the WHOLE place, not of each piece it is cut into.
        const map = rot ? spotSourceMap(s.path, W, H, ox / W, oy / H, rot) : null;
        const pts = pathDense(s.path, W, H, Math.max(2 * r, 32));
        /* A place drawn round is mended whole: cut into pieces, its inside
           would belong to none of them. Its box is its outline's, which a
           hand going round a thing keeps within reason. */
        if (s.fill) {
            spotPiece(ctx, W, H, pts, r, ox, oy, heal, feather, opacity, true, map);
            continue;
        }
        const LIMIT = 2.5e6;
        let piece = [pts[0], pts[1]], box = [pts[0], pts[1], pts[0], pts[1]];
        for (let i = 2; i < pts.length; i += 2) {
            const nb = [Math.min(box[0], pts[i]), Math.min(box[1], pts[i + 1]), Math.max(box[2], pts[i]), Math.max(box[3], pts[i + 1])];
            if ((nb[2] - nb[0] + 2 * r) * (nb[3] - nb[1] + 2 * r) > LIMIT && piece.length >= 4) {
                spotPiece(ctx, W, H, piece, r, ox, oy, heal, feather, opacity, false, map);
                const lx = piece[piece.length - 2], ly = piece[piece.length - 1];
                piece = [lx, ly];
                box = [lx, ly, lx, ly];
                i -= 2;
                continue;
            }
            piece.push(pts[i], pts[i + 1]);
            box = nb;
        }
        spotPiece(ctx, W, H, piece, r, ox, oy, heal, feather, opacity, false, map);
    }
}

/* --- „Entfernen": the AI's spots (11.11.2026, inpaint.js) ------------------
   develop.spots gets a third kind, { mode: 'ai', path, radius, feather,
   opacity, pieces: [{ id, box: [x, y, w, h], a, b }], fill? }. The path and the
   radius are the painted place as with the other two; what fills it is no
   other part of the picture but what a model invented, and THAT cannot be
   described — so here, and only here, a spot carries pixels after all
   (the paragraph above foresaw it): one PATCH per piece, the model's
   512 × 512 answer for the square `box` (shares of width and height) of
   the picture it saw. `id` names the patch; where it is kept is not this
   file's business (aiPatchKeep).

   PIECES, because the model sees 512 pixels. A blade of grass across the
   whole picture squeezed into one 512 square would come back as mush; cut
   along its length (a and b: shares of the path's length) each piece gets
   a square of its own — at least 512 of the original's pixels, so a small
   place is filled at the picture's own sharpness, about 2.2 times the
   piece's extent beyond that, so the model sees enough around the hole.
   Each piece was computed on the picture WITH the pieces before it, and a
   later one lays over an earlier one where they meet.

   NOTHING MOVES. The patch was invented for exactly that place: an AI
   spot cannot be dragged, nor its size changed afterwards — only its soft
   edge and its opacity, which are applied here, on drawing. The hole the
   model filled reaches as far as the soft edge did when it was made
   (aiReach); one made softer afterwards reaches past it, where the patch
   is transparent (aiPatchCut) — there the picture keeps its own pixels,
   and the edge blends it with itself. */

/* TWO MODELS, ONE PLACE (14.11.2026: „Erst wird LaMa angezeigt
   und gerechnet, dann bekommt man die Option, wenn einem das nicht gefällt,
   das starke Modell zu nehmen. Beide Pixelbilder werden gespeichert, sodass
   man evtl. doch wieder zurück zu LaMa springen kann … ohne neu zu
   rechnen"). The quick model fills every place first (inpaint.js); the
   strong one (moebius.js) may fill it again, for the same squares. So a
   spot may carry two sets of pieces:
     pieces   the ones SHOWN — everything that draws reads these, as before
     strong   true when the shown ones are the strong model's
     other    the other model's pieces, kept: their patches lie in the cloud
              beside the shown ones, and switching (aiSpotSwitch) only
              changes places, it computes nothing
   A spot without `other` has been filled once, by the quick model.

   ONE TOOL, THREE METHODS (the same day: „Reparieren und Entfernen
   zusammenlegen in ein Pinselwerkzeug"). `mode` is the method a place is
   SHOWN with, and it may change at any time (photowindow.js,
   spotMethodSet). So a heal or a clone may carry `pieces`, `other` and
   `strong` as well — the fills it had as „Entfernen", kept and not drawn
   —, and an AI spot `dx`, `dy` and `rot`, the source it had before. Only
   what `mode` says is drawn. */

/* `fill: true` — DRAWN ROUND (spotLoopCloses): the path is closed, and
   everything it encloses belongs to the place as well; one piece covers
   it all (aiPieces), and the masks fill the inside (polygonFill). */

/** The model's side; a patch is always this square. */
export const AI_SIDE = 512;
/** All AI pieces of a picture together — a piece is about ninety bytes (tenfold since 11.11.2026, migration 0193). */
export const AI_PIECES_MAX = 1000;
/** A patch's name: what aiPatchKeep is given and the settings carry. */
export const AI_PATCH_ID = /^[a-z0-9]{8,32}$/;

/** The pieces as stored: a known id, a box inside the picture, a stretch of the path. */
function aiPiecesClean(pieces, room) {
    const out = [];
    const share = (x) => Math.round(Math.min(1, Math.max(0, Number(x) || 0)) * 100000) / 100000;
    for (const p of Array.isArray(pieces) ? pieces : []) {
        if (out.length >= room) break;
        if (!p || !AI_PATCH_ID.test(String(p.id ?? '')) || !Array.isArray(p.box) || p.box.length !== 4) continue;
        const box = p.box.map(share);
        if (box[2] <= 0 || box[3] <= 0) continue;
        out.push({ id: String(p.id), box, a: share(p.a), b: share(p.b ?? 1) });
    }
    return out;
}

/* WHERE THE PATCHES LIVE (step 3, 11.11.2026): in the picture's cloud,
   in „Bearbeitung" beside the sidecar (includes/develop_patches.php). Here
   they are only held for drawing — the ones made on this visit, and the
   ones fetched for the pictures one looks at (aiPatchesLoad). `stored`
   says which of them the cloud holds already: what is not, rides with the
   next save of the settings (photowindow.js, developQueue).

   A PATCH IS TRANSPARENT outside the place it fills (aiPatchCut) — that
   keeps the PNG small, and aiPieceApply takes the picture's own pixels
   wherever the patch has none. */
const aiPatches = new Map();         // id -> a canvas or bitmap of AI_SIDE × AI_SIDE
const aiStored = new Set();          // ids the cloud holds
const aiLoading = new Map();         // id -> the fetch under way
let aiGeneration = 0;                // grows with every patch kept — part of the renderer's key

/** Keep the patch `id` (a canvas or ImageBitmap) for drawing; `stored` when it came from the cloud. */
export function aiPatchKeep(id, image, stored = false) {
    aiPatches.set(id, image);
    if (stored) aiStored.add(id);
    aiGeneration++;
}
/** Is the patch `id` here to be drawn? */
export const aiPatchHas = (id) => aiPatches.has(id);
/** The patch `id`, or undefined. */
export const aiPatchOf = (id) => aiPatches.get(id);
/** The cloud holds the patch `id` now. */
export const aiPatchStored = (id) => aiStored.add(id);
/** Does the cloud hold the patch `id`? */
export const aiPatchIsStored = (id) => aiStored.has(id);

/** Every patch id the settings SHOW — what a drawing needs. */
export function aiPatchIds(develop) {
    const ids = [];
    for (const s of develop?.spots ?? []) if (s?.mode === 'ai') for (const p of s.pieces ?? []) if (p?.id) ids.push(p.id);
    // The filled border of a crop past the picture is stored the same way (geo.fill).
    const fill = geoFill(develop);
    for (const p of fill?.pieces ?? []) if (p?.id) ids.push(p.id);
    // … and of the areas that show the strong model's fill, that one too (fill.pick).
    if (fill) for (const l of fillShown(fill)) for (const p of l.pieces) if (p?.id) ids.push(p.id);
    return ids;
}

/** Every patch id the settings NAME — the shown ones, the other model's kept beside them, and the
 *  fills a place keeps while it is shown as „Angleichen" or „Kopieren" (one tool, 14.11.2026). */
export function aiPatchIdsAll(develop) {
    const ids = [];
    for (const s of develop?.spots ?? []) for (const p of [...(s?.pieces ?? []), ...(s?.other ?? [])]) if (p?.id) ids.push(p.id);
    const fill = develop?.geo?.fill;
    for (const o of [fill, ...(fill?.fine ?? [])]) for (const p of o?.pieces ?? []) if (p?.id) ids.push(p.id);
    return ids;
}

/** The patches these settings name that are here but not yet in the cloud — what the next save brings.
 *  The kept ones too: the other model's fill must lie in the cloud to be come back to. */
export function aiPatchesUnstored(develop) {
    return aiPatchIdsAll(develop).filter((id) => aiPatches.has(id) && !aiStored.has(id));
}

/**
 * The other model's fill in the place of the shown one — nothing is
 * computed, the two sets change places. False when the spot has no other.
 */
export function aiSpotSwitch(spot) {
    if (spot?.mode !== 'ai' || !(spot.other ?? []).length) return false;
    [spot.pieces, spot.other] = [spot.other, spot.pieces];
    if (spot.strong) delete spot.strong; else spot.strong = true;
    aiGeneration++;
    return true;
}

/** One patch as a PNG, base64 — for the save. */
export async function aiPatchPng(id) {
    const image = aiPatches.get(id);
    if (!image) return null;
    let canvas = image;
    if (!(image instanceof HTMLCanvasElement)) {
        canvas = document.createElement('canvas');
        canvas.width = canvas.height = AI_SIDE;
        canvas.getContext('2d').drawImage(image, 0, 0);
    }
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    if (!blob) return null;
    const bytes = new Uint8Array(await blob.arrayBuffer());
    let text = '';
    for (let i = 0; i < bytes.length; i += 0x8000) text += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
    return btoa(text);
}

/**
 * Fetch the patches these settings name and are not here yet; `urlOf(id)`
 * names the address (pages/develop_patch.php). Resolves to true when at
 * least one came — the caller draws again. A patch that does not come
 * leaves its place unfilled, and the next look asks again.
 */
export async function aiPatchesLoad(develop, urlOf) {
    return aiPatchesFetch(aiPatchIds(develop), urlOf);
}

/** The same for a list of ids — the kept fill of one spot, before it is switched to (aiSpotSwitch). */
export async function aiPatchesFetch(ids, urlOf) {
    const wanted = ids.filter((id) => !aiPatches.has(id));
    if (!wanted.length) return false;
    const came = await Promise.all(wanted.map((id) => {
        if (!aiLoading.has(id)) {
            aiLoading.set(id, (async () => {
                try {
                    const answer = await fetch(urlOf(id), { credentials: 'same-origin' });
                    if (!answer.ok) return false;
                    const bitmap = await createImageBitmap(await answer.blob());
                    if (bitmap.width !== AI_SIDE || bitmap.height !== AI_SIDE) return false;
                    aiPatchKeep(id, bitmap, true);
                    return true;
                } catch {
                    return false;
                } finally {
                    aiLoading.delete(id);
                }
            })());
        }
        return aiLoading.get(id);
    }));
    return came.some(Boolean);
}

/* EVERY PLACE THAT DRAWS A PICTURE AS EDITED asks for its patches first
   (step 4, 11.11.2026): the viewer, the thumbnails, the plates, the
   export. They all know the picture as `of` — 'photo:12' or 'cloud:34',
   the key the thumbnails and the plate records carry anyway — and the
   address comes from where this file lies, so no page has to hand down
   its base. */
const PATCH_PAGE = new URL('../../pages/develop_patch.php', import.meta.url);

/** The address of one patch of the picture `of` ('photo:12', 'cloud:34'), as a function of its id. */
export function aiPatchUrl(of) {
    const [kind, id] = String(of ?? '').split(':');
    return (patch) => {
        const u = new URL(PATCH_PAGE);
        u.search = new URLSearchParams({ kind, id, patch }).toString();
        return u.href;
    };
}

/**
 * Fetch whatever patches these settings name for the picture `of`, and say
 * whether all of them are here now. False means a place stays unfilled in
 * what is drawn next — the caller keeps no copy of that for long.
 */
export async function aiPatchesFor(d, of) {
    const ids = aiPatchIds(d);
    if (!ids.length) return true;
    if (/^(photo|cloud):\d+$/.test(String(of ?? ''))) await aiPatchesLoad(d, aiPatchUrl(of));
    return ids.every((id) => aiPatches.has(id));
}

/** Make a fresh patch transparent outside `keep` (aiHole, grown a little) — what is stored and drawn alike. */
export function aiPatchCut(image, keep) {
    const d = image.data;
    for (let n = 0; n < keep.length; n++) if (!keep[n]) d[n * 4] = d[n * 4 + 1] = d[n * 4 + 2] = d[n * 4 + 3] = 0;
    return image;
}

/** The path's points in pixels (W, H) as pairs, and the length along it up to each. */
function pathRun(path, W, H) {
    const pts = path.map(([x, y]) => [x * W, y * H]);
    const at = [0];
    for (let i = 1; i < pts.length; i++) at.push(at[i - 1] + Math.hypot(pts[i][0] - pts[i - 1][0], pts[i][1] - pts[i - 1][1]));
    return { pts, at, length: at[at.length - 1] };
}

/** The part of a path between the shares a and b of its length, as flat pixels. */
function pathCut(path, W, H, a, b) {
    const { pts, at, length } = pathRun(path, W, H);
    if (pts.length === 1 || length === 0) return [pts[0][0], pts[0][1]];
    const s0 = a * length, s1 = b * length;
    const point = (s) => {
        let i = 1;
        while (i < at.length - 1 && at[i] < s) i++;
        const span = at[i] - at[i - 1] || 1, t = Math.min(1, Math.max(0, (s - at[i - 1]) / span));
        return [pts[i - 1][0] + (pts[i][0] - pts[i - 1][0]) * t, pts[i - 1][1] + (pts[i][1] - pts[i - 1][1]) * t];
    };
    const out = [...point(s0)];
    for (let i = 0; i < pts.length; i++) if (at[i] > s0 && at[i] < s1) out.push(pts[i][0], pts[i][1]);
    out.push(...point(s1));
    return out;
}

/**
 * Cut an AI spot's path into the pieces the model gets, each with its
 * square, for a picture of NW × NH. Yields [{ a, b, box }], box in shares.
 */
export function aiPieces(path, radius, NW, NH, fill = false) {
    const r = Math.max(1, radius * NW);
    /* A PLACE DRAWN ROUND is one piece: its inside is no stretch of the
       way, and cut along it each square would see a slice of the hole.
       One square about the whole of it, with less room around than a
       stroke's (1.8 times) — a large one is computed smaller than the
       picture's own pixels, which the maintainer was told before it was built. */
    if (fill) {
        const xs = path.map((p) => p[0] * NW), ys = path.map((p) => p[1] * NH);
        const minx = Math.min(...xs), maxx = Math.max(...xs), miny = Math.min(...ys), maxy = Math.max(...ys);
        const side = Math.max(AI_SIDE, Math.ceil((Math.max(maxx - minx, maxy - miny) + 2 * r + 4) * 1.8));
        const w = Math.min(side, NW), h = Math.min(side, NH);
        const x = Math.min(NW - w, Math.max(0, (minx + maxx) / 2 - w / 2));
        const y = Math.min(NH - h, Math.max(0, (miny + maxy) / 2 - h / 2));
        const four = (v) => Math.round(v * 100000) / 100000;
        return [{ a: 0, b: 1, box: [four(x / NW), four(y / NH), four(w / NW), four(h / NH)] }];
    }
    const { length } = pathRun(path, NW, NH);
    // A piece reaches along the path so far that the square stays sharp:
    // 200 pixels, or a little more than the stroke is wide.
    const reach = Math.max(200, 1.6 * r);
    const n = Math.max(1, Math.ceil(length / reach));
    const four = (x) => Math.round(x * 100000) / 100000;
    const pieces = [];
    for (let k = 0; k < n; k++) {
        const a = k / n, b = (k + 1) / n;
        const cut = pathCut(path, NW, NH, a, b);
        let minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
        for (let i = 0; i < cut.length; i += 2) {
            minx = Math.min(minx, cut[i]); maxx = Math.max(maxx, cut[i]);
            miny = Math.min(miny, cut[i + 1]); maxy = Math.max(maxy, cut[i + 1]);
        }
        const extent = Math.max(maxx - minx, maxy - miny) + 2 * r + 4;
        const side = Math.max(AI_SIDE, Math.ceil(extent * 2.2));
        // The square about the piece's middle, pushed back inside the picture; a picture smaller than it is taken whole that way.
        const w = Math.min(side, NW), h = Math.min(side, NH);
        const x = Math.min(NW - w, Math.max(0, (minx + maxx) / 2 - w / 2));
        const y = Math.min(NH - h, Math.max(0, (miny + maxy) / 2 - h / 2));
        pieces.push({ a: four(a), b: four(b), box: [four(x / NW), four(y / NH), four(w / NW), four(h / NH)] });
    }
    return pieces;
}

/**
 * THE SOFT EDGE LIES OUTSIDE what was painted, for this kind alone. With a
 * heal it lies inside the radius, and that is right there: the edge fades
 * into the picture around. Removing, the picture inside the painted line
 * IS the thing to go — faded into, it came back as a pale ghost of the
 * stem along both sides (measured, 11.11.2026). So what one paints goes
 * whole, and the soft edge reaches beyond it. The model's hole covers that
 * reach too: it invents everything up to the outer circle, and the fade
 * lays its invention over the picture's own pixels from the core outwards.
 *
 * AS FAR AS THE BRUSH'S OUTER CIRCLE (22.11.2026: „Die KI-Tools nutzen
 * aber nur den inneren Kreis … Können wir es so machen, dass das
 * KI-Werkzeug die volle Pinselgröße bekommt und die Pixel dann bis zum
 * Rand hin verlaufen? … Wenn, dann aber bitte den äußeren Kreis nehmen").
 * Until then the edge reached half a radius at the most, while the same
 * brush under „Angleichen" and „Kopieren" fades over SOFT_REACH radii —
 * a place switched to „Entfernen" shrank to nearly its core. A place
 * filled since carries `wide: true` and reaches as far as the brush. The
 * places filled before keep their half radius: their patches end there
 * (aiPatchCut), and a fade reaching further would cut off hard at that
 * line.
 */
export function aiReachShare(spot) {
    return spot?.wide ? SOFT_REACH : .5;
}
function aiReach(spot, r) {
    const feather = Math.min(100, Math.max(0, Number(spot.feather ?? 50)));
    const reach = r * (1 + aiReachShare(spot) * feather / 100);
    return { reach, feather: (1 - r / reach) * 100 };
}
/** The outer circle of an AI spot as a share of the width — where its fill has faded out. */
export function aiOuterRadius(spot) {
    return aiReach(spot, Number(spot.radius) || .01).reach;
}

/**
 * The mask of the place a piece fills, in the AI_SIDE square of its box: 1
 * where the model must invent. `grow` widens it by that many of the
 * square's pixels — the part of the patch that is kept (aiPatchCut).
 */
export function aiHole(spot, piece, NW, NH, grow = 0) {
    const [bx, by, bw, bh] = piece.box;
    const sx = AI_SIDE / (bw * NW), sy = AI_SIDE / (bh * NH);
    const cut = pathCut(spot.path, NW, NH, piece.a, piece.b);
    for (let i = 0; i < cut.length; i += 2) { cut[i] = (cut[i] - bx * NW) * sx; cut[i + 1] = (cut[i + 1] - by * NH) * sy; }
    // The soft edge's reach and a pixel and a half: the edge drawn later must find filled pixels under it.
    const r = aiReach(spot, Math.max(1, Number(spot.radius) * NW * sx)).reach + 1.5 + grow;
    const m = pathMask(cut, r, 0, 0, 0, AI_SIDE, AI_SIDE);
    if (spot.fill) polygonFill(m, cut, 0, 0, AI_SIDE, AI_SIDE);
    const hole = new Uint8Array(AI_SIDE * AI_SIDE);
    for (let n = 0; n < m.length; n++) hole[n] = m[n] > 0 ? 1 : 0;
    return hole;
}

/**
 * Lay one piece's patch into a 2D context that holds the picture at
 * W × H, its top left corner at (−ox, −oy) of the context — the whole
 * picture (spotsApply), or one square of it (the viewer, making the next
 * piece's input). Softly, within the radius of the piece's own stretch.
 */
export function aiPieceApply(ctx, W, H, spot, piece, ox = 0, oy = 0) {
    const patch = aiPatches.get(piece.id);
    const opacity = Math.min(100, Math.max(0, Number(spot.opacity ?? 100))) / 100;
    if (!patch || !opacity) return;
    const [bx, by, bw, bh] = piece.box;
    const X0 = bx * W - ox, Y0 = by * H - oy, BW = bw * W, BH = bh * H;
    const x0 = Math.max(0, Math.floor(X0)), y0 = Math.max(0, Math.floor(Y0));
    const x1 = Math.min(ctx.canvas.width, Math.ceil(X0 + BW)), y1 = Math.min(ctx.canvas.height, Math.ceil(Y0 + BH));
    const tw = x1 - x0, th = y1 - y0;
    if (tw <= 0 || th <= 0) return;
    const cut = pathCut(spot.path, W, H, piece.a, piece.b);
    for (let i = 0; i < cut.length; i += 2) { cut[i] -= ox; cut[i + 1] -= oy; }
    const { reach, feather } = aiReach(spot, Math.max(.75, (Number(spot.radius) || .01) * W));
    const m = pathMask(cut, reach, feather, x0, y0, tw, th);
    if (spot.fill) polygonFill(m, cut, x0, y0, tw, th);
    const T = ctx.getImageData(x0, y0, tw, th);
    const layer = document.createElement('canvas');
    layer.width = tw;
    layer.height = th;
    const lc = layer.getContext('2d', { willReadFrequently: true });
    lc.imageSmoothingQuality = 'high';
    lc.drawImage(patch, X0 - x0, Y0 - y0, BW, BH);
    const P = lc.getImageData(0, 0, tw, th).data;
    const out = T.data;
    for (let n = 0; n < m.length; n++) {
        if (m[n] <= 0) continue;
        // Where the patch is transparent (aiPatchCut) the picture keeps its own pixels.
        const k = m[n] * opacity * P[n * 4 + 3] / 255;
        if (k <= 0) continue;
        for (let c = 0; c < 3; c++) out[n * 4 + c] += (P[n * 4 + c] - out[n * 4 + c]) * k;
    }
    ctx.putImageData(T, x0, y0);
}

/**
 * WHERE THE SOURCE COMES FROM, chosen as Lightroom does: nearby, not
 * overlapping the region, and where the ring around it looks most like the
 * ring around the region — what borders the mended place should border
 * its patch too. `px` is a small copy of the original (ImageData-like, a
 * few hundred pixels across), `path` and `radius` as stored. Returns
 * { dx, dy } as shares of width and height.
 */
/* `within` (flat pairs, shares of width and height): THE CUT — the part of
   the picture that is shown once it is turned and cropped
   (11.11.2026: „Ich hatte ein Bild zugeschnitten und danach eine Retusche
   gemacht. Die Quelle lag dort dann außerhalb des Zuschnitts … ich komme
   gar nicht mehr an die Quelle"). The source is sought wholly inside it
   first; only where nothing fits there does the search go beyond. */
export function spotSourceFind(px, path, radius, fill = false, within = null) {
    const w = px.width, h = px.height, data = px.data;
    const r = Math.max(1.5, radius * w);
    const pts = pathDense(path, w, h, Math.max(1, r / 2));
    let minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
    for (let i = 0; i < pts.length; i += 2) {
        minx = Math.min(minx, pts[i]); maxx = Math.max(maxx, pts[i]);
        miny = Math.min(miny, pts[i + 1]); maxy = Math.max(maxy, pts[i + 1]);
    }
    /* A PLACE DRAWN ROUND (fill) is a whole area: its ring is only what lies
       OUTSIDE it — inside, beyond the stroke's reach, is the thing to go,
       not its surroundings — and the source must clear the whole area,
       so the steps outwards go by its size, not by the brush's. */
    const inside = (x, y) => fill && pointInPath(x, y, pts);
    const unit = fill ? Math.max(r, Math.hypot(maxx - minx, maxy - miny) / 2) : r;
    const band = Math.max(2, r * .5);
    let ring = [];
    for (let y = Math.max(0, Math.floor(miny - r - band)); y < Math.min(h, Math.ceil(maxy + r + band)); y++) {
        for (let x = Math.max(0, Math.floor(minx - r - band)); x < Math.min(w, Math.ceil(maxx + r + band)); x++) {
            const d = pathDist(x + .5, y + .5, pts);
            if (d > r * 1.05 && d <= r * 1.05 + band && !inside(x + .5, y + .5)) ring.push(x, y);
        }
    }
    if (ring.length > 3000) {
        const step = Math.ceil(ring.length / 3000);
        ring = ring.filter((_, i) => Math.floor(i / 2) % step === 0);
    }
    const pix = (x, y) => {
        const X = Math.min(w - 1, Math.max(0, Math.round(x))), Y = Math.min(h - 1, Math.max(0, Math.round(y)));
        return (Y * w + X) * 4;
    };
    const check = pts.filter((_, i) => i % 2 === 0).length > 60
        ? pts.filter((_, i) => Math.floor(i / 2) % Math.ceil(pts.length / 120) === 0) : pts;
    const cut = within && within.length >= 6 ? within.map((v, i) => v * (i % 2 ? h : w)) : null;
    // The whole source inside the cut: the four corners of its box, the cut being a (turned) rectangle.
    const inCut = (x0, y0, x1, y1) => !cut || [[x0, y0], [x1, y0], [x0, y1], [x1, y1]].every(([x, y]) => pointInPath(x, y, cut));
    const search = (limited) => {
        let best = null;
        for (const k of [2.3, 2.9, 3.6, 4.5, 5.6, 7, 9]) {
            for (let a = 0; a < 24; a++) {
                const ang = a / 24 * 2 * Math.PI;
                const ox = Math.cos(ang) * k * unit, oy = Math.sin(ang) * k * unit;
                // Inside the picture, the whole source.
                if (minx + ox - r < 0 || maxx + ox + r > w || miny + oy - r < 0 || maxy + oy + r > h) continue;
                if (limited && !inCut(minx + ox - r, miny + oy - r, maxx + ox + r, maxy + oy + r)) continue;
                // Clear of the region: every point of the moved path at least two radii from the path.
                let clear = true;
                for (let i = 0; i < check.length && clear; i += 2) {
                    if (pathDist(check[i] + ox, check[i + 1] + oy, pts) < 2 * r) clear = false;
                    // An area must not lie on the other: neither outline inside the other's.
                    else if (fill && (inside(check[i] + ox, check[i + 1] + oy) || inside(check[i] - ox, check[i + 1] - oy))) clear = false;
                }
                if (!clear) continue;
                let sum = 0;
                for (let i = 0; i < ring.length; i += 2) {
                    const p = pix(ring[i], ring[i + 1]), q = pix(ring[i] + ox, ring[i + 1] + oy);
                    const dr = data[p] - data[q], dg = data[p + 1] - data[q + 1], db = data[p + 2] - data[q + 2];
                    sum += dr * dr + dg * dg + db * db;
                }
                // A slight preference for the near: the same light, the same depth of field.
                const score = (sum / Math.max(1, ring.length / 2)) * (1 + .04 * k);
                if (!best || score < best.score) best = { score, ox, oy };
            }
        }
        return best;
    };
    let best = (cut && search(true)) || search(false);
    if (!best) {
        // Nothing fits wholly: the side with the most room.
        const room = [[w - maxx, 1, 0], [minx, -1, 0], [h - maxy, 0, 1], [miny, 0, -1]].sort((p, q) => q[0] - p[0])[0];
        const k = Math.max(2.3 * r, (room[1] ? maxx - minx : maxy - miny) + 2.3 * r);
        best = { ox: room[1] * k, oy: room[2] * k };
    }
    return { dx: Math.round(best.ox / w * 10000) / 10000, dy: Math.round(best.oy / h * 10000) / 10000 };
}

/** Lay one brush stroke into a mask field of mw × mh (values 0 … 1). */
function brushStroke(f, st, mw, mh) {
    const path = st?.path ?? [];
    if (!path.length) return;
    const r = Math.max(.75, (Number(st.radius) || .01) * mw);
    const inner = Math.min(r * (1 - Math.min(100, Math.max(0, Number(st.feather ?? 50))) / 100), r - .75);
    const flow = Math.min(100, Math.max(0, Number(st.flow ?? 100))) / 100;
    if (!flow) return;
    const pts = [];
    for (const [x, y] of path) pts.push(x * mw, y * mh);
    let minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
    for (let i = 0; i < pts.length; i += 2) {
        minx = Math.min(minx, pts[i]); maxx = Math.max(maxx, pts[i]);
        miny = Math.min(miny, pts[i + 1]); maxy = Math.max(maxy, pts[i + 1]);
    }
    const x0 = Math.max(0, Math.floor(minx - r)), y0 = Math.max(0, Math.floor(miny - r));
    const x1 = Math.min(mw, Math.ceil(maxx + r)), y1 = Math.min(mh, Math.ceil(maxy + r));
    const bw = x1 - x0, bh = y1 - y0;
    if (bw <= 0 || bh <= 0) return;
    // The stroke on its own first — the highest of its segments, so a stroke never builds up on itself.
    const s = new Float32Array(bw * bh);
    for (let i = 0; i < pts.length; i += 2) {
        const ax = pts[i], ay = pts[i + 1];
        const bx = i + 3 < pts.length ? pts[i + 2] : ax, by = i + 3 < pts.length ? pts[i + 3] : ay;
        const sx0 = Math.max(x0, Math.floor(Math.min(ax, bx) - r)), sx1 = Math.min(x1, Math.ceil(Math.max(ax, bx) + r));
        const sy0 = Math.max(y0, Math.floor(Math.min(ay, by) - r)), sy1 = Math.min(y1, Math.ceil(Math.max(ay, by) + r));
        for (let y = sy0; y < sy1; y++) for (let x = sx0; x < sx1; x++) {
            const d = segDist(x + .5, y + .5, ax, ay, bx, by);
            if (d >= r) continue;
            const t = d <= inner ? 0 : (d - inner) / (r - inner);
            const v = (1 - t * t * (3 - 2 * t)) * flow;
            const n = (y - y0) * bw + (x - x0);
            if (v > s[n]) s[n] = v;
        }
    }
    for (let y = 0; y < bh; y++) for (let x = 0; x < bw; x++) {
        const v = s[y * bw + x];
        if (!v) continue;
        const n = (y + y0) * mw + x + x0;
        f[n] = st.erase ? f[n] * (1 - v) : f[n] + v * (1 - f[n]);
    }
}

/** A brush mask's field at mw × mh, its strokes from `from` on laid into `into` (or a new field). */
export function brushField(strokes, mw, mh, into = null, from = 0) {
    const f = into ?? new Float32Array(mw * mh);
    for (let k = from; k < (strokes ?? []).length; k++) brushStroke(f, strokes[k], mw, mh);
    return f;
}

/** The settings without the zeros, as the server keeps them (null for nothing). */
export function developClean(d) {
    if (!d) return null;
    const out = {};
    for (const g of DEVELOP_GROUPS) for (const s of g.sliders) {
        const v = Math.round((Number(d[s.key]) || 0) * 100) / 100;
        if (v) out[s.key] = v;
    }
    /* NO EMPTY MASK IS KEPT (13.11.2026: „ich brauche keine leere
       Maske, keinen Platzhalter"): an ellipse or gradient made by a click and
       never drawn (empty), a brush without a stroke. */
    const radials = (d.radials ?? []).filter((r) => !r.empty).slice(0, RADIALS_MAX).map((r) => {
        const one = { x: r.x, y: r.y, rx: r.rx, ry: r.ry, angle: r.angle ?? 0, feather: r.feather ?? 50, invert: !!r.invert };
        if (r.off) one.off = true;
        for (const s of RADIAL_SLIDERS) if (Number(r[s.key])) one[s.key] = Math.round(Number(r[s.key]) * 100) / 100;
        return one;
    });
    if (radials.length) out.radials = radials;
    const linears = (d.linears ?? []).filter((r) => !r.empty).slice(0, RADIALS_MAX - radials.length).map((r) => {
        const one = { x1: r.x1, y1: r.y1, x2: r.x2, y2: r.y2, invert: !!r.invert };
        if (r.off) one.off = true;
        for (const k of ['x1', 'y1', 'x2', 'y2']) one[k] = Math.round(Number(one[k]) * 10000) / 10000;
        for (const s of RADIAL_SLIDERS) if (Number(r[s.key])) one[s.key] = Math.round(Number(r[s.key]) * 100) / 100;
        return one;
    });
    if (linears.length) out.linears = linears;
    const spots = [];
    let points = 0;
    let piecesUsed = 0;
    for (const sp of (d.spots ?? []).slice(0, SPOTS_MAX)) {
        const path = (sp.path ?? []).slice(0, PATH_POINTS_MAX - points)
            .map(([x, y]) => [Math.round(Number(x) * 10000) / 10000, Math.round(Number(y) * 10000) / 10000]);
        if (!path.length) break;
        const radius = Math.round(Math.min(SPOT_RADIUS_MAX, Math.max(SPOT_RADIUS_MIN, Number(sp.radius) || .01)) * 100000) / 100000;
        const feather = Math.round(Math.min(100, Math.max(0, Number(sp.feather ?? 50))));
        const opacity = Math.round(Math.min(100, Math.max(0, Number(sp.opacity ?? 100))));
        /* AN AI SPOT IS KEPT ONLY WITH ITS PIECES: one still being
           computed has none yet, and stored without them it would be a
           place that says „Entfernen" and removes nothing. */
        if (sp.mode === 'ai') {
            const pieces = aiPiecesClean(sp.pieces, AI_PIECES_MAX - piecesUsed);
            if (!pieces.length) continue;
            piecesUsed += pieces.length;
            points += path.length;
            const one = { mode: 'ai', path, radius, feather, opacity, pieces };
            if (sp.wide) one.wide = true;
            // The other model's fill, kept beside the shown one — from the same budget of pieces.
            const other = aiPiecesClean(sp.other, AI_PIECES_MAX - piecesUsed);
            piecesUsed += other.length;
            if (other.length) one.other = other;
            if (sp.strong) one.strong = true;
            if (sp.fill) one.fill = true;
            /* ONE TOOL (14.11.2026, photowindow.js spotMethodSet): a place
               switched to „Entfernen" keeps the source and the turn it had
               as „Angleichen" or „Kopieren" — to come back to. */
            const dx = Math.round((Number(sp.dx) || 0) * 10000) / 10000, dy = Math.round((Number(sp.dy) || 0) * 10000) / 10000;
            if (dx || dy) { one.dx = dx; one.dy = dy; }
            const turn = Math.round(Math.min(180, Math.max(-180, Number(sp.rot) || 0)) * 10) / 10;
            if (turn) one.rot = turn;
            if (sp.off) one.off = true;
            spots.push(one);
            continue;
        }
        points += path.length;
        const one = { mode: sp.mode === 'clone' ? 'clone' : 'heal', path, radius, feather, opacity,
            dx: Math.round((Number(sp.dx) || 0) * 10000) / 10000, dy: Math.round((Number(sp.dy) || 0) * 10000) / 10000 };
        if (sp.fill) one.fill = true;
        // The source turned (spotSourceMap), in tenths of a degree.
        const rot = Math.round(Math.min(180, Math.max(-180, Number(sp.rot) || 0)) * 10) / 10;
        if (rot) one.rot = rot;
        /* AND THE OTHER WAY: a place switched back from „Entfernen" keeps the
           KI's fills beside it — not shown, not drawn, only kept, so coming
           back computes nothing. From the same budget of pieces. */
        const kept = aiPiecesClean(sp.pieces, AI_PIECES_MAX - piecesUsed);
        if (kept.length) {
            piecesUsed += kept.length;
            one.pieces = kept;
            const other = aiPiecesClean(sp.other, AI_PIECES_MAX - piecesUsed);
            piecesUsed += other.length;
            if (other.length) one.other = other;
            if (sp.strong) one.strong = true;
            if (sp.wide) one.wide = true;
        }
        if (sp.off) one.off = true;
        spots.push(one);
    }
    if (spots.length) out.spots = spots;
    const brushes = (d.brushes ?? []).filter((b) => (b.strokes ?? []).length).slice(0, Math.min(BRUSHES_MAX, RADIALS_MAX - radials.length - linears.length)).map((b) => {
        const strokes = [];
        for (const st of b.strokes ?? []) {
            const path = (st.path ?? []).slice(0, PATH_POINTS_MAX - points)
                .map(([x, y]) => [Math.round(Number(x) * 10000) / 10000, Math.round(Number(y) * 10000) / 10000]);
            if (!path.length) continue;
            points += path.length;
            const one = { path,
                radius: Math.round(Math.min(SPOT_RADIUS_MAX, Math.max(SPOT_RADIUS_MIN, Number(st.radius) || .01)) * 100000) / 100000,
                feather: Math.round(Math.min(100, Math.max(0, Number(st.feather ?? 50)))),
                flow: Math.round(Math.min(100, Math.max(0, Number(st.flow ?? 100)))) };
            if (st.erase) one.erase = true;
            strokes.push(one);
        }
        const one = { strokes, invert: !!b.invert };
        if (b.off) one.off = true;
        for (const s of RADIAL_SLIDERS) if (Number(b[s.key])) one[s.key] = Math.round(Number(b[s.key]) * 100) / 100;
        return one;
    });
    if (brushes.length) out.brushes = brushes;
    if (d.wb) {
        // The old wb alone — developWB would multiply Temperatur and Tönung in a second time.
        const [r, g, b] = developWB({ wb: d.wb }).map((x) => Math.round(x * 10000) / 10000);
        if (r !== 1 || g !== 1 || b !== 1) out.wb = { r, g, b };
    }
    // „Farbstich entfernen": the measured greys and the three sliders (castMeasure).
    if (d.cast && Array.isArray(d.cast.n) && d.cast.n.length === CAST_BANDS.length) {
        const k = (v) => Math.round(Math.min(100, Math.max(0, Number(v ?? 100))));
        out.cast = { n: d.cast.n.map(([r, b]) => [castRound(Number(r) || 1), castRound(Number(b) || 1)]),
            amount: k(d.cast.amount), shadows: k(d.cast.shadows), highlights: k(d.cast.highlights) };
        // The pipette's points and the measurement beneath them (castFromPicks) — kept so the points can change later.
        if (Array.isArray(d.cast.base) && d.cast.base.length === CAST_BANDS.length) {
            out.cast.base = d.cast.base.map(([r, b]) => [castRound(Number(r) || 1), castRound(Number(b) || 1)]);
        }
        const share = (v) => Math.round(Math.min(1, Math.max(0, Number(v) || 0)) * 10000) / 10000;
        const picks = (d.cast.picks ?? []).slice(0, CAST_PICKS_MAX).filter((p) => Number(p.lum) > 0).map((p) => ({
            px: share(p.px), py: share(p.py), lum: Math.round(Math.min(1, Number(p.lum)) * 100000) / 100000,
            r: castRound(Number(p.r) || 1), b: castRound(Number(p.b) || 1),
        }));
        if (picks.length) out.cast.picks = picks;
    }
    // „Farbmischer": per kind the colours that are set, whole numbers.
    if (mixSet(d)) {
        const mix = {};
        for (const k of MIX_KINDS) {
            const row = {};
            for (const c of MIX_COLOURS) {
                const v = Math.round(Math.max(-100, Math.min(100, Number(d.mix[k.key]?.[c.key]) || 0)));
                if (v) row[c.key] = v;
            }
            if (Object.keys(row).length) mix[k.key] = row;
        }
        out.mix = mix;
    }
    // „Gradationskurve": the bent channels' points.
    if (curveSet(d)) {
        const curve = {};
        for (const c of CURVE_CHANNELS) {
            const pts = curvePoints(d.curve[c.key]);
            if (pts) curve[c.key] = pts;
        }
        out.curve = curve;
    }
    // „Farbkorrektur nach Helligkeit": per range what is set, the balance only beside a range.
    if (gradeSet(d)) {
        const grade = {};
        for (const r of GRADE_RANGES) {
            const g = d.grade[r.key] ?? {};
            const one = {};
            const h = Math.round(((Number(g.h) || 0) % 360 + 360) % 360);
            const sv = Math.round(Math.max(0, Math.min(100, Number(g.s) || 0)));
            const l = Math.round(Math.max(-100, Math.min(100, Number(g.l) || 0)));
            if (h) one.h = h;
            if (sv) one.s = sv;
            if (l) one.l = l;
            if (Object.keys(one).length) grade[r.key] = one;
        }
        const b = Math.round(Math.max(-100, Math.min(100, Number(d.grade.balance) || 0)));
        if (b) grade.balance = b;
        out.grade = grade;
    }
    if (geoSet(d)) {
        const g = d.geo;
        const geo = { turn: ((Number(g.turn) || 0) % 360 + 360) % 360, angle: Math.round((Number(g.angle) || 0) * 10) / 10 };
        if (g.crop) geo.crop = Object.fromEntries(['x', 'y', 'w', 'h'].map((k) => [k, Math.round(Number(g.crop[k]) * 10000) / 10000]));
        if (['h', 'v', 'hv'].includes(g.flip)) geo.flip = g.flip;
        // The filled border („Erweitern"), where it belongs to this cut — against the one budget of patches.
        const fill = fillClean(g.fill, geo, AI_PIECES_MAX - piecesUsed);
        if (fill) geo.fill = fill;
        out.geo = geo;
    }
    return Object.keys(out).length ? out : null;
}

// The longest edge the renderer works at — enough for every screen, and a
// texture every graphics card takes.
const EDGE_MAX = 4096;
/* THE FILE'S LONGEST EDGE („Bild exportieren", stage 3): the original's own
   pixels up to here. 24 megapixels are 6000 on the long side; a picture
   beyond 8192 would ask the graphics card for four textures of a quarter
   gigabyte each, and some cards hand back a black square instead of
   saying no — so the file is drawn at most this large. */
const EXPORT_EDGE = 8192;

const VERTEX = `
attribute vec2 aPos;
varying vec2 vUv;
void main() {
    vUv = aPos * .5 + .5;
    gl_Position = vec4(aPos, 0., 1.);
}`;

/* A separable gaussian of nine taps in five fetches (the linear-sampling
   trick), its spread scaled by uStep — a step of one blurs by about two
   pixels, the clarity's wide blur takes a larger one. */
const BLUR = `
precision highp float;
uniform sampler2D uTex;
uniform vec2 uDir;
varying vec2 vUv;
void main() {
    vec4 c = texture2D(uTex, vUv) * .2270270270;
    vec2 o1 = uDir * 1.3846153846, o2 = uDir * 3.2307692308;
    c += (texture2D(uTex, vUv + o1) + texture2D(uTex, vUv - o1)) * .3162162162;
    c += (texture2D(uTex, vUv + o2) + texture2D(uTex, vUv - o2)) * .0702702703;
    gl_FragColor = c;
}`;

const DEVELOP = `
precision highp float;
uniform sampler2D uImg, uBlurS, uBlurL, uBrush, uCurve;
uniform float uCurveOn; // „Gradationskurve": uCurve holds the table (curveLut)
uniform vec2 uSize;    // the source, in pixels
uniform vec2 uOut;     // the view (turned and cut), in source pixels
uniform vec4 uGeo;     // centre x, y in source pixels; cos, sin of the turn
uniform vec2 uFlip;    // the mirror, ±1 per axis — undone after the turn (geoView)
uniform float uComp;   // 1: uImg and its blurred copies hold the VIEW already — the turned picture with its filled border (geo.fill)
uniform vec4 uLight;   // exposure, contrast, highlights, shadows
uniform vec4 uEnds;    // whites, blacks, dehaze, clarity
uniform vec4 uColour;  // 0, 0, vibrance, saturation — temp and tint went into uWB
uniform float uSharp;
uniform vec3 uWB;      // the white balance's gains per channel — old wb times Temperatur and Tönung (developWB)
uniform mat3 uCast[5]; // „Farbstich entfernen": a correction per brightness range on linear light (castMatrices)
uniform float uCastOn;
uniform vec3 uMix[${MIX_COLOURS.length}]; // „Farbmischer": per colour hue, saturation, luminance, -1 … 1 (mixUniform)
uniform float uMixOn;
uniform vec4 uGrade[3]; // „Farbkorrektur nach Helligkeit": shadows, midtones, highlights — push in stops per channel, luminance in stops (gradeUniform)
uniform float uGradeBal;
uniform float uGradeOn;
uniform int uN;
uniform vec4 uRPos[${RADIALS_MAX}];    // ellipse: centre x, y and radii x, y; gradient: x1, y1, x2, y2 — in pixels
uniform vec4 uRShape[${RADIALS_MAX}];  // angle (radians), feather 0 … 1, invert 0/1, kind (0 ellipse, 1 gradient, 2 brush)
// A brush's uRPos is the one channel of uBrush it lives in (1 0 0 0, 0 1 0 0, …).
uniform int uShow;     // the mask laid over the picture in red while it is painted (-1: none)
uniform vec4 uRLight[${RADIALS_MAX}];  // exposure, contrast, highlights, shadows
uniform vec4 uRMore[${RADIALS_MAX}];   // temp, saturation, clarity, dehaze
uniform vec4 uRColour[${RADIALS_MAX}]; // tint, vibrance, whites, blacks
uniform float uRSharp[${RADIALS_MAX}]; // sharpen
uniform vec3 uRWB[${RADIALS_MAX}];     // temp and tint as white-balance gains, as the picture's (developWB) — uRMore.x and uRColour.x go unread
varying vec2 vUv;

float luma(vec3 c) { return dot(c, vec3(.2126, .7152, .0722)); }

/* Light: exposure, shadows and highlights in stops on LINEAR light,
   contrast and clarity on what one sees.

   SHADOWS AND HIGHLIGHTS MULTIPLY, THEY DO NOT ADD (11.11.2026:
   „Bei sehr dunklen Bildern ist Tiefenaufhellen ganz schnell ein
   Grauschleier drüber … Gibt es da was Intelligenteres?" — „Ja, bau es so
   und stell einfach um"). They added a fixed amount to red, green and
   blue alike, the most to the darkest: black became grey, and an equal
   amount on all three drew the colours together — a dark moss turned
   grey-green, a very dark picture milky at once. Now a dark pixel is
   brightened by a GAIN on linear light, all three channels in the same
   ratio: zero times anything stays zero, so black stays black, and a
   colour keeps its saturation. Up to three stops in the deepest
   shadows, a stop and a half off the brightest highlights.

   AND BY THE SURROUNDINGS, NOT BY THE PIXEL ALONE: whether a place is
   shadow is read half off the pixel and half off the picture without its
   fine detail (c − detail — the same wide blur „Klarheit" uses), so a
   dark AREA is lifted as a whole and the lamellae and the moss in it keep
   their texture; by the pixel alone the darkest specks were lifted most
   and the texture went flat. Half and not all: wholly by the
   surroundings, a dark rim beside a bright cap would stay dark in a
   band. Every picture and every mask computes this way since that day —
   pictures edited before look different, as he chose.

   WHITES AND BLACKS THE SAME WAY (12.11.2026: „für Weiß und
   Schwarz haben wir das noch nicht gemacht" — „Ja, bau es so und stell
   einfach um"). They still added a fixed amount to all three channels at
   the very ends — Schwarz to the right turned pure black grey, and dark
   and bright colours paled. Now they are gains on linear light too, in
   the same breath as shadows and highlights, only narrower: weighted by
   the FOURTH power of the darkness or brightness (ends: x whites, y
   blacks). Whites up to a stop at the brightest, blacks up to two stops
   at the darkest; a black pixel stays black whichever way, so „Schwarz"
   to the right opens the drawing in the near-black and „Schwarz" to the
   left deepens it. */
vec3 light(vec3 c, vec4 l, vec2 ends, vec3 detail, float clarity) {
    float gain = exp2(l.x * 3.);
    vec3 lin = pow(max(c, 0.), vec3(2.2)) * gain;
    vec3 base = pow(max(c - detail, 0.), vec3(2.2)) * gain;
    float y = mix(clamp(luma(pow(lin, vec3(1. / 2.2))), 0., 1.), clamp(luma(pow(base, vec3(1. / 2.2))), 0., 1.), .5);
    float dark = (1. - y) * (1. - y), bright = y * y;
    lin *= exp2(l.w * 3. * dark + l.z * 1.5 * bright + ends.x * bright * bright + ends.y * 2. * dark * dark);
    c = pow(lin, vec3(1. / 2.2));
    /* KONTRAST PLUS IS A GENTLE S-CURVE (22.11.2026: „Der
       Kontrastregler ist zu stark … bei 100% … absolut unbrauchbar … das
       Maximum … was wir jetzt bei 15 ungefähr haben. 15 bis 20 … eher
       20"). It was a straight stretch about middle grey, 1.8 times as
       steep at +100, which clipped the lights and the darks hard. Now a
       blend towards smoothstep: at +100 as steep in the middle as the old
       +20 (1.16), and black and white stay where they are, so nothing
       clips. Pictures edited before look softer, as he chose. Minus
       flattens as before. */
    if (l.y > 0.) {
        vec3 s = clamp(c, 0., 1.);
        c = mix(c, s * s * (3. - 2. * s), l.y * .32);
    } else {
        c = (c - .5) * (1. + l.y * .6) + .5;
    }
    float mid = 1. - abs(clamp(luma(c), 0., 1.) - .5) * 2.;
    c += detail * clarity * 1.2 * clamp(mid * 1.5, 0., 1.);
    return c;
}

// Temperatur and Tönung are white-balance gains since 12.11.2026 (uWB), in the masks since 14.11.2026 (uRWB).
vec3 colour(vec3 c, float vib, float sat) {
    float y = luma(c);
    float s = max(max(c.r, c.g), c.b) - min(min(c.r, c.g), c.b);
    c = mix(vec3(y), c, 1. + vib * (1. - clamp(s * 1.5, 0., 1.)));
    return mix(vec3(y), c, 1. + sat);
}

/* „Farbstich entfernen" (castMeasure, castMatrices): the pixel's correction
   blended between the two ranges beside its brightness, on a log scale. The
   five centres are CAST_CENTRES. */
vec3 castFix(vec3 c) {
    if (uCastOn < .5) return c;
    vec3 lin = pow(max(c, 0.), vec3(2.2));
    float t = log(max(dot(lin, vec3(.2126, .7152, .0722)), 1e-4));
    mat3 m;
    if (t <= ${CAST_CENTRES[0].toFixed(6)}) m = uCast[0];
    else if (t <= ${CAST_CENTRES[1].toFixed(6)}) m = uCast[0] + (uCast[1] - uCast[0]) * ((t - ${CAST_CENTRES[0].toFixed(6)}) / ${(CAST_CENTRES[1] - CAST_CENTRES[0]).toFixed(6)});
    else if (t <= ${CAST_CENTRES[2].toFixed(6)}) m = uCast[1] + (uCast[2] - uCast[1]) * ((t - ${CAST_CENTRES[1].toFixed(6)}) / ${(CAST_CENTRES[2] - CAST_CENTRES[1]).toFixed(6)});
    else if (t <= ${CAST_CENTRES[3].toFixed(6)}) m = uCast[2] + (uCast[3] - uCast[2]) * ((t - ${CAST_CENTRES[2].toFixed(6)}) / ${(CAST_CENTRES[3] - CAST_CENTRES[2]).toFixed(6)});
    else if (t <= ${CAST_CENTRES[4].toFixed(6)}) m = uCast[3] + (uCast[4] - uCast[3]) * ((t - ${CAST_CENTRES[3].toFixed(6)}) / ${(CAST_CENTRES[4] - CAST_CENTRES[3]).toFixed(6)});
    else m = uCast[4];
    return pow(max(m * lin, 0.), vec3(1. / 2.2));
}

/* „Farbmischer" (MIX_COLOURS says what each slider does). HSV, because its
   hue is the colour wheel the eight colours sit on. */
vec3 rgb2hsv(vec3 c) {
    vec4 K = vec4(0., -1. / 3., 2. / 3., -1.);
    vec4 p = mix(vec4(c.bg, K.wz), vec4(c.gb, K.xy), step(c.b, c.g));
    vec4 q = mix(vec4(p.xyw, c.r), vec4(c.r, p.yzx), step(p.x, c.r));
    float d = q.x - min(q.w, q.y);
    return vec3(abs(q.z + (q.w - q.y) / (6. * d + 1e-10)), d / (q.x + 1e-10), q.x);
}
vec3 hsv2rgb(vec3 c) {
    vec3 p = abs(fract(c.xxx + vec3(1., 2. / 3., 1. / 3.)) * 6. - 3.);
    return c.z * mix(vec3(1.), clamp(p - 1., 0., 1.), c.y);
}
vec3 mixer(vec3 c) {
    if (uMixOn < .5) return c;
    vec3 hsv = rgb2hsv(clamp(c, 0., 1.));
    float h = hsv.x * 360.;
    vec3 a, b;
    float t;
${MIX_COLOURS.map((col, i) => {
        const next = MIX_COLOURS[(i + 1) % MIX_COLOURS.length];
        const to = i + 1 < MIX_COLOURS.length ? next.hue : 360;
        const cond = i + 1 < MIX_COLOURS.length ? `if (h < ${to.toFixed(1)}) ` : '';
        return `    ${i ? 'else ' : ''}${cond}{ a = uMix[${i}]; b = uMix[${(i + 1) % MIX_COLOURS.length}]; t = (h - ${col.hue.toFixed(1)}) / ${(to - col.hue).toFixed(1)}; }`;
    }).join('\n')}
    vec3 adj = mix(a, b, smoothstep(0., 1., t));
    float w = smoothstep(0., .25, hsv.y);
    hsv.x = fract(hsv.x + adj.x * 30. / 360. + 1.);
    c = hsv2rgb(hsv);
    // Saturation about the pixel's own luminance, as „Farbe" does it — in HSV a red taken to grey came out light grey.
    c = max(mix(vec3(luma(c)), c, 1. + adj.y), 0.);
    vec3 lin = pow(c, vec3(2.2)) * exp2(adj.z * 1.2 * w);
    return pow(lin, vec3(1. / 2.2));
}

/* „Gradationskurve" (curveLut): each channel looked up in its row of 256, the texel's middle at (i + .5) / 256. */
vec3 curve(vec3 c) {
    if (uCurveOn < .5) return c;
    vec3 u = (clamp(c, 0., 1.) * 255. + .5) / 256.;
    return vec3(texture2D(uCurve, vec2(u.r, .5)).r, texture2D(uCurve, vec2(u.g, .5)).g, texture2D(uCurve, vec2(u.b, .5)).b);
}

/* „Farbkorrektur nach Helligkeit" (GRADE_RANGES says what it does). */
vec3 grade(vec3 c) {
    if (uGradeOn < .5) return c;
    float y = clamp(luma(c), 0., 1.);
    float pivot = .5 - .3 * uGradeBal;
    float ws = 1. - smoothstep(0., pivot, y);
    float wh = smoothstep(pivot, 1., y);
    vec4 g = uGrade[0] * ws + uGrade[1] * (1. - ws - wh) + uGrade[2] * wh;
    // Burned-out white holds no colour to correct: the push fades out over the last four per cent (GRADE_RANGES).
    g.rgb *= 1. - smoothstep(.96, 1., max(max(c.r, c.g), c.b));
    vec3 lin = pow(max(c, 0.), vec3(2.2));
    float before = luma(lin);
    lin *= exp2(g.rgb);
    lin *= before / max(luma(lin), 1e-6);
    lin *= exp2(g.a);
    return pow(lin, vec3(1. / 2.2));
}

void main() {
    // Where this pixel of the view lies in the source: turned back about the crop's centre.
    vec2 q = vec2(vUv.x, 1. - vUv.y) * uOut - uOut * .5;
    vec2 p = uGeo.xy + vec2(q.x * uGeo.z + q.y * uGeo.w, -q.x * uGeo.w + q.y * uGeo.z) * uFlip;
    vec2 uv = vec2(p.x / uSize.x, 1. - p.y / uSize.y);
    bool inside = !(uv.x < 0. || uv.x > 1. || uv.y < 0. || uv.y > 1.);
    /* WHERE THE PIXEL IS READ. As a rule in the source, at uv, and beyond
       the source there is nothing. With a filled border (uComp) the
       textures hold the view itself — the picture turned and cut on a
       canvas, the fill laid over it —, so they are read at the view's own
       place, the border is sharpened, cleared and developed like the
       picture it continues, and the masks still go by p, the place in the
       source. */
    vec2 at = uv;
    float alpha = 1.;
    if (uComp > .5) {
        at = vUv;
        alpha = texture2D(uImg, at).a;
        if (alpha <= 0.) {
            gl_FragColor = vec4(0.);
            return;
        }
    } else if (!inside) {
        gl_FragColor = vec4(0.);
        return;
    }
    // The white balance FIRST, on the picture as it came: every other step works on its neutral colours.
    // „Farbstich entfernen" before it (castFix) — and on the blurred copies too, or detail would carry the cast's difference.
    vec3 c = castFix(texture2D(uImg, at).rgb) * uWB;
    vec3 detail = c - castFix(texture2D(uBlurL, at).rgb) * uWB;
    // Sharpening first, on the picture as it came: an unsharp mask. Its fine detail is the masks' too.
    vec3 fine = c - castFix(texture2D(uBlurS, at).rgb) * uWB;
    c += fine * uSharp * 2.;
    // Whites and blacks inside light() since 12.11.2026 — gains like the shadows', not amounts added.
    c = light(c, uLight, uEnds.xy, detail, uEnds.w);
    // Dehaze: the haze's grey taken off (or laid on), with the colour it hid.
    float h = uEnds.z;
    c = h > 0. ? (c - .12 * h) / (1. - .12 * h) : mix(c, vec3(.82), -h * .35);
    c = colour(c, uColour.z, uColour.w + max(h, 0.) * .2);
    c = curve(c);
    c = mixer(c);
    c = grade(c);

    // The ellipses, each its own small development behind its mask — in source pixels (p, above).
    float shown = 0.;
    for (int i = 0; i < ${RADIALS_MAX}; i++) {
        if (i >= uN) break;
        float m;
        if (uRShape[i].w > 1.5) {
            // A painted mask ends with the picture it was painted on: beyond it (a filled border) it is empty.
            m = inside ? dot(texture2D(uBrush, uv), uRPos[i]) : 0.;
        } else if (uRShape[i].w > .5) {
            // The gradient: how far along the way from the full line to the empty one.
            vec2 ab = uRPos[i].zw - uRPos[i].xy;
            float t = dot(p - uRPos[i].xy, ab) / max(dot(ab, ab), 1.);
            m = 1. - smoothstep(0., 1., t);
        } else {
            vec2 d = p - uRPos[i].xy;
            float a = -uRShape[i].x;
            d = vec2(d.x * cos(a) - d.y * sin(a), d.x * sin(a) + d.y * cos(a)) / uRPos[i].zw;
            float r = length(d);
            float edge = max(uRShape[i].y, .001);
            m = 1. - smoothstep(1. - edge, 1., r);
        }
        if (uRShape[i].z > .5) m = 1. - m;
        if (i == uShow) shown = m;
        if (m <= 0.) continue;
        /* THE MASK SCALES ITS SETTINGS, IT DOES NOT BLEND TWO PICTURES
           (22.11.2026, after the inverted ellipse round the subject: „diese
           Abdunklung nicht so cool … wie in Lightroom … Vielleicht ist der
           Verlauf nicht gleichmäßig"). Until then the mask's whole
           development was computed and laid over the picture by m — and a
           darkening blended that way is not even in stops: measured on a
           flat grey at -3 EV, half the mask gave -1.2 EV, not -1.5, so the
           fall came late and steep at the outer line. Now every setting is
           multiplied by m before it is applied, as Lightroom applies a
           local adjustment: half the mask is half the exposure in stops,
           the fall even all the way. Every picture with masks looks a
           little different since, as he chose. And the mask's Belichtung
           reaches four stops instead of three (the same day: „dass sie
           etwas dunkler werden kann") — the picture's own keeps three. */
        // The mask's own development, in the picture's order: white balance, light (with the ends), dehaze, colour, then sharpening.
        vec3 local = light(c * pow(uRWB[i], vec3(m)), uRLight[i] * vec4(m * 4. / 3., m, m, m), uRColour[i].zw * m, detail, uRMore[i].z * m);
        float hz = uRMore[i].w * m;
        local = hz > 0. ? (local - .12 * hz) / (1. - .12 * hz) : mix(local, vec3(.82), -hz * .35);
        local = colour(local, uRColour[i].y * m, uRMore[i].y * m + max(hz, 0.) * .2);
        // The mask's own sharpening: the same unsharp mask, laid on what the mask has made.
        // Below zero it softens instead: the detail taken off, down to the wide blur at -100 (DEVELOP_GROUPS).
        float sr = uRSharp[i] * m;
        local += sr > 0. ? fine * sr * 2. : detail * sr;
        c = local;
    }
    // Where the brush has painted, while one paints: Lightroom's red veil.
    c = mix(clamp(c, 0., 1.), vec3(.92, .12, .1), shown * .5);
    gl_FragColor = vec4(clamp(c, 0., 1.), alpha);
}`;

/**
 * A renderer on a canvas. setImage() takes the picture (an <img> that has
 * loaded, same origin), render() draws the settings over it. Null where
 * the browser has no WebGL — the viewer then shows the original.
 */
export function developRenderer(canvas, { edge = EDGE_MAX } = {}) {
    // preserveDrawingBuffer: the canvas is read again — the plates draw it, the export saves it.
    const gl = canvas.getContext('webgl', { premultipliedAlpha: false, preserveDrawingBuffer: true, antialias: false });
    if (!gl) return null;

    const compile = (type, src) => {
        const s = gl.createShader(type);
        gl.shaderSource(s, src);
        gl.compileShader(s);
        if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(s));
        return s;
    };
    const program = (frag) => {
        const p = gl.createProgram();
        gl.attachShader(p, compile(gl.VERTEX_SHADER, VERTEX));
        gl.attachShader(p, compile(gl.FRAGMENT_SHADER, frag));
        gl.linkProgram(p);
        if (!gl.getProgramParameter(p, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(p));
        return p;
    };
    let blurProg, devProg;
    try {
        blurProg = program(BLUR);
        devProg = program(DEVELOP);
    } catch (e) {
        console.warn('develop.js: no shader', e);
        return null;
    }
    const quad = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, quad);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);

    const texture = () => {
        const tex = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, tex);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        return tex;
    };
    const target = (w, h) => {
        const tex = texture();
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, w, h, 0, gl.RGBA, gl.UNSIGNED_BYTE, null);
        const fb = gl.createFramebuffer();
        gl.bindFramebuffer(gl.FRAMEBUFFER, fb);
        gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, tex, 0);
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        return { tex, fb };
    };

    let src = null;           // the picture's texture
    let W = 0, H = 0;         // the size the renderer works at
    let NW = 0, NH = 0;       // the picture's own size
    let scale = 1;            // its pixels per picture pixel
    let tmp = null, blurS = null, blurL = null;
    let made = { s: false, l: false };
    let plain = null;         // what setImage drew: the picture at W × H, unmended
    let mended = null;        // a 2D canvas for the spots (spotsApply), made when the first one comes
    let spotsKey = '';        // the spots `mended` carries now
    let srcKey = '';          // what the texture holds: 'n#' + the spots (the picture) or 'c#' + … (the view with its filled border)
    let TW = 0, TH = 0;       // the texture's size: the picture's (W × H), or the view's while it holds that
    let comp = null;          // the 2D canvas the view with a filled border is put together on
    /* THE BRUSHES' TEXTURE: up to four painted masks, one per channel, at
       most 2048 on the long side (a mask is soft; the picture's own 4096
       would quadruple the work on every move of the brush for nothing).
       Each brush keeps its field without its last stroke (base) and with
       it (full): while one paints, only the stroke in hand is laid anew. */
    let brushTex = null;
    let brushKey = '';
    // „Gradationskurve": its table as a 256 × 1 texture, made anew when the curves change (curveLut).
    let curveTex = null;
    let curveKey = '';
    let brushCache = [];
    let MW = 0, MH = 0;
    const upload = (source, tw = W, th = H) => {
        gl.bindTexture(gl.TEXTURE_2D, src);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, source);
        // The blurred copies were made of the old picture — and at its size.
        made = { s: false, l: false };
        if (tw !== TW || th !== TH) {
            for (const x of [tmp, blurS, blurL]) if (x) { gl.deleteTexture(x.tex); gl.deleteFramebuffer(x.fb); }
            tmp = blurS = blurL = null;
            TW = tw;
            TH = th;
        }
    };

    const draw = (prog, fb, bind, vw = TW, vh = TH) => {
        gl.useProgram(prog);
        gl.bindFramebuffer(gl.FRAMEBUFFER, fb);
        gl.viewport(0, 0, vw, vh);
        gl.bindBuffer(gl.ARRAY_BUFFER, quad);
        const at = gl.getAttribLocation(prog, 'aPos');
        gl.enableVertexAttribArray(at);
        gl.vertexAttribPointer(at, 2, gl.FLOAT, false, 0, 0);
        bind();
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    };
    const blur = (out, step) => {
        const u = (n) => gl.getUniformLocation(blurProg, n);
        draw(blurProg, tmp.fb, () => {
            gl.activeTexture(gl.TEXTURE0);
            gl.bindTexture(gl.TEXTURE_2D, src);
            gl.uniform1i(u('uTex'), 0);
            gl.uniform2f(u('uDir'), step / TW, 0);
        });
        draw(blurProg, out.fb, () => {
            gl.activeTexture(gl.TEXTURE0);
            gl.bindTexture(gl.TEXTURE_2D, tmp.tex);
            gl.uniform1i(u('uTex'), 0);
            gl.uniform2f(u('uDir'), 0, step / TH);
        });
    };

    return {
        /** Take a loaded picture; false where it has no size yet. */
        setImage(img) {
            const nw = img.naturalWidth || img.width, nh = img.naturalHeight || img.height;
            if (!nw || !nh) return false;
            const cap = Math.min(edge, gl.getParameter(gl.MAX_TEXTURE_SIZE));
            scale = Math.min(1, cap / Math.max(nw, nh));
            W = Math.round(nw * scale);
            H = Math.round(nh * scale);
            NW = nw;
            NH = nh;
            /* ALWAYS THROUGH A 2D CANVAS, never the picture straight onto the
               card (16.11.2026, in Firefox: „erst das Original
               geladen wird und dann sehe ich, wie die Retuschen reinkommen
               und es die Farbe verliert"). A picture in a wide colour space
               (Adobe RGB, P3) carries an ICC profile; drawn into a 2D canvas
               every browser converts it to the canvas's sRGB — measured in
               Chromium and Firefox. Handed to texImage2D as it is, Firefox
               on the graphics card left the profile unread: the edited
               picture came out duller than the same picture undeveloped.
               Until the previews carried their profile (thumbs.php,
               16.11.2026) nobody could see it — they had none to lose; an
               original larger than the card's edge always went through a
               canvas to be shrunk, which is why „Bearbeiten" was right. One
               copy at the picture's size is the price. */
            const source = document.createElement('canvas');
            source.width = W;
            source.height = H;
            source.getContext('2d').drawImage(img, 0, 0, W, H);
            src ??= texture();
            plain = source;
            spotsKey = '';
            srcKey = 'n#';
            brushKey = '';
            brushCache = [];
            /* THE BLURRED COPIES ARE MADE WHEN A SLIDER ASKS FOR THEM, not
               here: each is the picture's size again on the graphics card,
               and a file drawn at 6000 pixels must not hold three of them
               for nothing. */
            TW = TH = 0;
            upload(source);
            return true;
        },

        /** The picture as the spots left it at the last render, W × H — what an AI piece is cut from when a spot lies under it. */
        working() {
            return plain ? { canvas: spotsKey && mended ? mended : plain, W, H } : null;
        },

        /** Give the graphics card back what this renderer holds — a renderer made for one file. */
        free() {
            if (mended) mended.width = mended.height = 0;
            if (comp) comp.width = comp.height = 0;
            gl.getExtension('WEBGL_lose_context')?.loseContext();
        },

        /**
         * Draw the settings over the picture (all zeros: the picture itself),
         * turned and cut as its geo says — `whole` shows the whole turned
         * picture (while cropping). The canvas takes the view's size.
         */
        render(d = {}, whole = false, show = -1) {
            if (!src) return;
            const view = geoView(d, NW, NH, whole);
            const ow = Math.max(1, Math.round(view.cw * scale)), oh = Math.max(1, Math.round(view.ch * scale));
            if (canvas.width !== ow) canvas.width = ow;
            if (canvas.height !== oh) canvas.height = oh;
            /* THE SPOTS FIRST, ONCE PER CHANGE: the picture mended on a 2D
               canvas and handed to the card as the picture itself — a
               slider moved afterwards does not mend anew. */
            const spots = spotsOf(d);
            // The patches' generation too: an AI patch that arrives later must be drawn in.
            const key = spots.length ? JSON.stringify(spots) + (spots.some((x) => x.mode === 'ai') ? '#' + aiGeneration : '') : '';
            if (key !== spotsKey) {
                if (key) {
                    mended ??= document.createElement('canvas');
                    if (mended.width !== W || mended.height !== H) { mended.width = W; mended.height = H; }
                    const ctx = mended.getContext('2d', { willReadFrequently: true });
                    ctx.clearRect(0, 0, W, H);
                    ctx.drawImage(plain, 0, 0, W, H);
                    spotsApply(ctx, W, H, spots);
                }
                spotsKey = key;
            }
            /* A FILLED BORDER („Erweitern", geo.fill): the view is put
               together on a 2D canvas — the picture turned and cut as the
               shader would, the fill over it on the crop's rectangle — and
               THAT is handed to the card as the picture; the shader then
               reads it where it stands (uComp). Only while a fill belongs
               to the cut as it stands and its patches are here: a crop
               being dragged has none, and goes the quick way, by uniforms
               alone. */
            const fill = geoFill(d), fillOf = fill ? fillImage(fill) : null;
            const want = fillOf
                ? ['c', key, fill.at, fill.image ? 'live' + (fill.rev ?? 0) : fillKey(fill), String(whole), ow, oh].join('#')
                : 'n#' + key;
            if (want !== srcKey) {
                const picture = key ? mended : plain;
                if (!fillOf) upload(picture);
                else {
                    comp ??= document.createElement('canvas');
                    if (comp.width !== ow || comp.height !== oh) { comp.width = ow; comp.height = oh; }
                    const ctx = comp.getContext('2d');
                    ctx.clearRect(0, 0, ow, oh);
                    ctx.imageSmoothingQuality = 'high';
                    ctx.save();
                    ctx.scale(ow / view.cw, oh / view.ch);
                    ctx.translate(view.cw / 2, view.ch / 2);
                    ctx.rotate(view.R * Math.PI / 180);
                    ctx.scale(view.fx, view.fy);
                    ctx.translate(-view.cx, -view.cy);
                    ctx.drawImage(picture, 0, 0, NW, NH);
                    ctx.restore();
                    // The crop's rectangle in this view: the whole of it, or — while one crops — where the frame lies.
                    const crop = d.geo.crop, [qx, qy] = view.toView([crop.x * NW, crop.y * NH]);
                    const fw = crop.w * NW, fh = crop.h * NW;
                    ctx.drawImage(fillOf, (qx - fw / 2) * ow / view.cw, (qy - fh / 2) * oh / view.ch, fw * ow / view.cw, fh * oh / view.ch);
                    upload(comp, ow, oh);
                }
                srcKey = want;
            }
            const v = (o, k) => (Number(o?.[k]) || 0) / 100;
            const masks = masksOf(d);
            // The wide blur: for clarity, and since 11.11.2026 for shadows and highlights, which read the surroundings (light).
            // Whites and blacks read the surroundings too since 12.11.2026 (light()).
            // A mask's Schärfen below zero softens with it too (DEVELOP_GROUPS).
            const wantL = ['clarity', 'shadows', 'highlights', 'whites', 'blacks'].some((k) => v(d, k) !== 0 || masks.some(({ m }) => v(m, k) !== 0))
                || masks.some(({ m }) => v(m, 'sharpen') < 0);
            const wantS = v(d, 'sharpen') !== 0 || masks.some(({ m }) => v(m, 'sharpen') > 0);
            // The wide blur for clarity: about a hundredth of the picture.
            if ((wantL && !made.l) || (wantS && !made.s)) tmp ??= target(TW, TH);
            if (wantL && !made.l) { blurL ??= target(TW, TH); blur(blurL, Math.max(2, Math.max(W, H) / 400)); made.l = true; }
            if (wantS && !made.s) { blurS ??= target(TW, TH); blur(blurS, .7); made.s = true; }
            const brushes = masks.filter(({ kind }) => kind === 'brush').map(({ m }) => m);
            if (brushes.length) {
                const key = JSON.stringify(brushes.map((b) => b.strokes));
                if (key !== brushKey) {
                    const k = Math.min(1, 2048 / Math.max(W, H));
                    const mw = Math.max(1, Math.round(W * k)), mh = Math.max(1, Math.round(H * k));
                    if (mw !== MW || mh !== MH) { MW = mw; MH = mh; brushCache = []; }
                    const data = new Uint8Array(mw * mh * 4);
                    brushes.forEach((b, ch) => {
                        const strokes = b.strokes ?? [];
                        const fullKey = JSON.stringify(strokes);
                        const baseKey = JSON.stringify(strokes.slice(0, -1));
                        let c = brushCache[ch];
                        if (!c || c.fullKey !== fullKey) {
                            if (!c || c.baseKey !== baseKey) {
                                c = { baseKey, base: brushField(strokes.slice(0, -1), mw, mh) };
                            }
                            c.fullKey = fullKey;
                            c.full = strokes.length ? brushField(strokes, mw, mh, c.base.slice(), strokes.length - 1) : c.base;
                            brushCache[ch] = c;
                        }
                        // Rows bottom up, as the texture's v runs.
                        const f = c.full;
                        for (let y = 0; y < mh; y++) {
                            const to = (mh - 1 - y) * mw * 4 + ch, row = y * mw;
                            for (let x = 0; x < mw; x++) data[to + x * 4] = Math.round(f[row + x] * 255);
                        }
                    });
                    brushTex ??= texture();
                    gl.bindTexture(gl.TEXTURE_2D, brushTex);
                    gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
                    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, mw, mh, 0, gl.RGBA, gl.UNSIGNED_BYTE, data);
                    brushKey = key;
                }
            }
            const lut = curveLut(d);
            if (lut) {
                const key = JSON.stringify(d.curve);
                if (key !== curveKey) {
                    curveTex ??= texture();
                    gl.bindTexture(gl.TEXTURE_2D, curveTex);
                    gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
                    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 256, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, lut);
                    curveKey = key;
                }
            }
            const u = (n) => gl.getUniformLocation(devProg, n);
            draw(devProg, null, () => {
                const tex = [[src, 'uImg'], [made.s ? blurS.tex : src, 'uBlurS'], [made.l ? blurL.tex : src, 'uBlurL'],
                    [brushes.length && brushTex ? brushTex : src, 'uBrush'], [lut && curveTex ? curveTex : src, 'uCurve']];
                tex.forEach(([tx, name], i) => {
                    gl.activeTexture(gl.TEXTURE0 + i);
                    gl.bindTexture(gl.TEXTURE_2D, tx);
                    gl.uniform1i(u(name), i);
                });
                gl.uniform1f(u('uCurveOn'), lut ? 1 : 0);
                gl.uniform2f(u('uSize'), W, H);
                gl.uniform2f(u('uOut'), view.cw * scale, view.ch * scale);
                const rad = view.R * Math.PI / 180;
                gl.uniform4f(u('uGeo'), view.cx * scale, view.cy * scale, Math.cos(rad), Math.sin(rad));
                gl.uniform2f(u('uFlip'), view.fx, view.fy);
                gl.uniform1f(u('uComp'), fillOf ? 1 : 0);
                gl.uniform4f(u('uLight'), v(d, 'exposure'), v(d, 'contrast'), v(d, 'highlights'), v(d, 'shadows'));
                gl.uniform4f(u('uEnds'), v(d, 'whites'), v(d, 'blacks'), v(d, 'dehaze'), wantL ? v(d, 'clarity') : 0);
                // Temperatur and Tönung live in uWB (developWB), first in the chain — not here.
                gl.uniform4f(u('uColour'), 0, 0, v(d, 'vibrance'), v(d, 'saturation'));
                gl.uniform1f(u('uSharp'), wantS ? v(d, 'sharpen') : 0);
                const wb = developWB(d);
                gl.uniform3f(u('uWB'), wb[0], wb[1], wb[2]);
                const cast = castMatrices(d);
                gl.uniform1f(u('uCastOn'), cast ? 1 : 0);
                if (cast) gl.uniformMatrix3fv(u('uCast'), false, cast);
                const mixed = mixUniform(d);
                gl.uniform1f(u('uMixOn'), mixed ? 1 : 0);
                if (mixed) gl.uniform3fv(u('uMix'), mixed);
                const graded = gradeUniform(d);
                gl.uniform1f(u('uGradeOn'), graded ? 1 : 0);
                if (graded) {
                    gl.uniform4fv(u('uGrade'), graded.ranges);
                    gl.uniform1f(u('uGradeBal'), graded.balance);
                }
                gl.uniform1i(u('uN'), masks.length);
                const pos = new Float32Array(RADIALS_MAX * 4), shape = new Float32Array(RADIALS_MAX * 4);
                const lightR = new Float32Array(RADIALS_MAX * 4), more = new Float32Array(RADIALS_MAX * 4), sharpR = new Float32Array(RADIALS_MAX);
                const colourR = new Float32Array(RADIALS_MAX * 4);
                const wbR = new Float32Array(RADIALS_MAX * 3).fill(1);
                let channel = 0;
                masks.forEach(({ kind, m: r }, i) => {
                    if (kind === 'brush') {
                        const one = [0, 0, 0, 0];
                        one[channel++] = 1;
                        pos.set(one, i * 4);
                        shape.set([0, 0, r.invert ? 1 : 0, 2], i * 4);
                    } else if (kind === 'linear') {
                        pos.set([r.x1 * W, r.y1 * H, r.x2 * W, r.y2 * H], i * 4);
                        shape.set([0, 0, r.invert ? 1 : 0, 1], i * 4);
                    } else {
                        // Centre as shares of width and height, both radii as shares of the WIDTH.
                        pos.set([r.x * W, r.y * H, Math.max(1, r.rx * W), Math.max(1, r.ry * W)], i * 4);
                        shape.set([(r.angle ?? 0) * Math.PI / 180, (r.feather ?? 50) / 100, r.invert ? 1 : 0, 0], i * 4);
                    }
                    lightR.set([v(r, 'exposure'), v(r, 'contrast'), v(r, 'highlights'), v(r, 'shadows')], i * 4);
                    more.set([v(r, 'temp'), v(r, 'saturation'), wantL ? v(r, 'clarity') : 0, v(r, 'dehaze')], i * 4);
                    const sr = v(r, 'sharpen');
                    sharpR[i] = sr > 0 ? (wantS ? sr : 0) : sr;
                    colourR.set([v(r, 'tint'), v(r, 'vibrance'), v(r, 'whites'), v(r, 'blacks')], i * 4);
                    // Temperatur and Tönung of the mask as the picture's own (developWB): stops per channel.
                    const ts = wbStops(r.temp), tn = wbStops(r.tint);
                    wbR.set([2 ** (ts + tn / 3), 2 ** (-2 * tn / 3), 2 ** (-ts + tn / 3)], i * 3);
                });
                gl.uniform4fv(u('uRPos'), pos);
                gl.uniform4fv(u('uRShape'), shape);
                gl.uniform4fv(u('uRLight'), lightR);
                gl.uniform4fv(u('uRMore'), more);
                gl.uniform1fv(u('uRSharp'), sharpR);
                gl.uniform4fv(u('uRColour'), colourR);
                gl.uniform3fv(u('uRWB'), wbR);
                gl.uniform1i(u('uShow'), show);
            }, ow, oh);
            // False where the browser gave the canvas a smaller drawing buffer than asked for.
            return gl.drawingBufferWidth === ow && gl.drawingBufferHeight === oh;
        },
    };
}

/**
 * THE DEVELOPED PICTURE AS A FILE (stage 3, 06.11.2026: „dass
 * man dann ein Bild erzeugen kann mit den angewendeten Filtern und
 * Einstellungen"). The same renderer as on the screen, so the file is what
 * one saw — only at the original's own pixels instead of the screen's
 * 4096, on a canvas of its own that is given back the moment the JPEG is
 * out. A browser that hands the canvas a smaller drawing buffer than asked
 * for (some limit it below the texture size) is asked again at the size it
 * really drew.
 *
 * THE LAST STEPS BEFORE THE FILE are the caller's (`finish`): it gets the
 * rendered canvas while the renderer still holds it and hands back the
 * canvas to encode — the export makes it smaller, sharpens it and lays the
 * watermark over it there (photowindow.js, exportFinish).
 *
 * @param {HTMLImageElement} img  the ORIGINAL, loaded
 * @param {object} d  the develop settings
 * @param {{quality?: number, type?: string, finish?: (canvas: HTMLCanvasElement) => Promise<HTMLCanvasElement>|HTMLCanvasElement}} options
 * @returns {Promise<{blob: Blob, width: number, height: number}>}
 */
export async function developFile(img, d, { quality = .92, type = 'image/jpeg', finish = null, of = null } = {}) {
    /* The removed places must be in the file: their patches first. One
       that cannot be had stops the export — a file that brings back what
       the user removed would be worse than none. */
    if (!await aiPatchesFor(d, of)) throw new Error(t('Die entfernten Stellen ließen sich nicht laden.'));
    let edge = EXPORT_EDGE;
    for (let tries = 0; tries < 3; tries++) {
        const canvas = document.createElement('canvas');
        const r = developRenderer(canvas, { edge });
        if (!r) throw new Error(t('Dieser Browser kann das Bild nicht berechnen.'));
        try {
            if (!r.setImage(img)) throw new Error(t('Das Original ließ sich nicht öffnen.'));
            if (!r.render(d)) {
                // The buffer it really got, as the new long edge — with a little room.
                const gl = canvas.getContext('webgl');
                const share = Math.min(gl.drawingBufferWidth / canvas.width, gl.drawingBufferHeight / canvas.height);
                const long = Math.min(edge, Math.max(img.naturalWidth, img.naturalHeight));
                edge = Math.max(512, Math.floor(long * share * .98));
                continue;
            }
            const out = finish ? await finish(canvas) : canvas;
            const blob = await new Promise((resolve) => out.toBlob(resolve, type, quality));
            // A browser that cannot write the type hands back a PNG — not what was asked for.
            if (!blob || blob.type !== type) throw new Error(t('Das Bild ließ sich nicht speichern.'));
            return { blob, width: out.width, height: out.height };
        } finally {
            r.free();
        }
    }
    throw new Error(t('Das Bild ist für diesen Browser zu groß.'));
}

/* ---------------------------------------------------------------------------
 * THE THUMBNAILS FOLLOW THE PICTURE (07.11.2026: „den Thumbnails
 * … die jeweiligen Einstellungen mitgeben. Dass sie sich eben an das Bild
 * anpassen, das ich verändert habe"). A thumbnail is the server's small
 * preview of the ORIGINAL; with settings it is drawn once more through the
 * same renderer, at its own small size — a few milliseconds — and the <img>
 * shows the result. One renderer for all of them, the results kept by
 * preview address and settings, so a list drawn again costs nothing.
 *
 * EVERY THUMBNAIL SAYS WHOSE IT IS (data-develop-of = 'photo:12' or
 * 'cloud:34'), so the viewer, having stored new settings, refreshes every
 * thumbnail of that picture on the page at once (developThumbsRefresh) —
 * the strip, the Fundbrowser's tiles, the gallery — without knowing who
 * drew them.
 * ------------------------------------------------------------------------ */

let thumbDrawer;                     // undefined: not tried; null: no WebGL here
const thumbsMade = new Map();        // preview address + settings -> Promise<blob address | null>
const THUMBS_KEPT = 400;

function thumbDrawerGet() {
    if (thumbDrawer === undefined) {
        const c = document.createElement('canvas');
        const r = developRenderer(c, { edge: 1024 });
        thumbDrawer = r ? { r, c } : null;
    }
    return thumbDrawer;
}

async function thumbMake(src, d, of, key) {
    /* THE LOAD EVENT, NOT decode(): a page in a background tab may leave
       decode() unsettled until it is shown again, and the thumbnails would
       wait for that; the load event comes either way. */
    const base = new Image();
    const loaded = await new Promise((resolve) => {
        base.addEventListener('load', () => resolve(true), { once: true });
        base.addEventListener('error', () => resolve(false), { once: true });
        base.src = src;
    });
    if (!loaded || !base.naturalWidth) return null;
    /* The AI's patches before drawing (step 4) — a tile without them shows
       the removed thing again. Missing ones leave this tile drawn without
       them, and it is not kept: the next drawing asks again. */
    if (!await aiPatchesFor(d, of)) setTimeout(() => thumbsMade.delete(key), 0);
    const drawer = thumbDrawerGet();
    if (!drawer || !drawer.r.setImage(base)) return null;
    drawer.r.render(d);
    // toBlob takes the canvas as it stands NOW — the next thumbnail may render the moment this returns.
    const blob = await new Promise((resolve) => drawer.c.toBlob(resolve, 'image/jpeg', .9));
    return blob ? URL.createObjectURL(blob) : null;
}

/**
 * Show a thumbnail of a picture as its settings say: `src` is the plain
 * preview, `d` the picture's develop (null or empty: the preview as it is),
 * `of` whose it is ('photo:12', 'cloud:34'). The plain preview stands
 * until the developed one is there, and stays where it cannot be made.
 */
export function developThumb(img, src, d, of) {
    if (!img || !src) return;
    img.dataset.plain = src;
    if (of) img.dataset.developOf = of;
    const want = d && !developEmpty(d) ? JSON.stringify(developClean(d) ?? {}) : '';
    img.dataset.developed = want;
    if (want === '') {
        if (img.getAttribute('src') !== src) img.src = src;
        return;
    }
    const key = src + '\n' + want;
    let job = thumbsMade.get(key);
    if (!job) {
        job = thumbMake(src, d, of, key);
        thumbsMade.set(key, job);
        // The oldest go — their addresses are released once nothing shows them any more.
        while (thumbsMade.size > THUMBS_KEPT) {
            const [old, made] = thumbsMade.entries().next().value;
            thumbsMade.delete(old);
            made.then((url) => url && setTimeout(() => URL.revokeObjectURL(url), 60_000));
        }
    }
    if (!img.getAttribute('src')) img.src = src;
    job.then((url) => {
        // Only if the thumbnail still wants these settings of this preview.
        if (img.dataset.developed !== want || img.dataset.plain !== src) return;
        if (url) img.src = url;
        else if (img.getAttribute('src') !== src) img.src = src;
    });
}

/** Every thumbnail of one picture on the page, shown anew with its new settings. */
export function developThumbsRefresh(of, d) {
    for (const img of document.querySelectorAll(`img[data-develop-of="${CSS.escape(of)}"]`)) {
        developThumb(img, img.dataset.plain, d, of);
    }
}
