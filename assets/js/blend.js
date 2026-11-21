/**
 * „EBENEN" — several renderings of one picture laid over each other, and
 * painted into one (11.11.2026).
 *
 * WHAT IT IS FOR. He renders every focus stack three times in Helicon
 * Focus, with the methods A, B and C, „diese drei Methoden haben jeweils
 * ihre eigenen Vor- und Nachteile": A gives the soft background, C the
 * sharp subject, B the bright places C burns out. So far he laid them
 * over each other in another program and painted the best of each
 * together. Here the pictures of a find that show the same thing in the
 * same cut become LAYERS: one is the ground („Grundlage" — for him A),
 * and each of the others shows only where its mask was painted.
 *
 * HOW HE WORKS, and what the tools follow: „ich fahre einmal an der
 * scharfen Grenze entlang mit einem Stiftwerkzeug und fülle dann mit einem
 * Füllwerkzeug den Rest". So there is a brush, an eraser and a fill — and
 * a stroke that comes round to where it began fills its inside by itself,
 * as „Entfernen" and „Reparieren" do (develop.js, spotLoopCloses).
 *
 * A MASK IS PIXELS, not strokes. A subject painted in is hundreds of
 * strokes plus fills, and a fill has no description but its result; so
 * each layer keeps one byte per pixel (0 = the layers below show, 255 =
 * this one), at the size the layers are drawn at.
 *
 * THE SIZE THEY ARE DRAWN AT is at most EDGE pixels on the long side: three
 * or four camera pictures at their full 24 megapixels are more than a
 * graphics card hands a page, and the masks would weigh the same again.
 * The edge one paints is soft over a few pixels anyway. The finished
 * picture is computed from the originals at their full size (a later
 * step), the masks scaled up smoothly.
 *
 * A TAB OF „BEARBEITEN", NOT A WINDOW OF ITS OWN (11.11.2026,
 * after a day with the window: „ich würde gar kein eigenes Fenster machen,
 * der Bearbeitungsmodus ist eigentlich genau der Ort, wo das hingehört").
 * It began as a dialog over the whole page with its own tiles to tick —
 * and that doubled what stands there already: the pictures in the rail at
 * the left, the picture large in the middle, the tools' panel at the
 * right. So this file hands the photo window two parts and takes its
 * place among the tabs of the sidebar: a PANEL for the sidebar, and a
 * STAGE that lies over the picture while one works on a set. The pictures
 * are ticked where they stand — a tick box on each tile of the rail
 * (`host.tiles()`).
 *
 * ITS OWN FILE all the same: the photo window shows ONE picture and
 * everything in it hangs on that; this is about several at once. They
 * share the retouching's loop rule, nothing else.
 *
 * THE WORK IS KEPT BY ITSELF (step 3; includes/layer_sets.php, migration
 * 0191): a moment after every change the masks that changed go into the
 * cloud of their pictures, into „Bearbeitung", and then the row that says
 * which pictures lie over each other and how. No button — „Speichern"
 * would be one more thing to forget after an hour's painting. Opened
 * again, a set stands as it was left; the choosing offers what was begun.
 *
 * AT THE END THE LAYERS BECOME ONE PICTURE (step 4; „wenn mir alles
 * gefällt, will ich daraus ein Bild machen … und die drei Einzelbilder
 * sollen gelöscht werden. Aber natürlich nur, wenn ich das sage"). It is
 * computed from the ORIGINALS at their full size, the masks enlarged
 * smoothly, and goes into the cloud as a recording of the find like any
 * other — with the ground's subject name, the scale of whichever layer
 * has one, and the camera's data of the ground. Only when it lies there
 * does the question come whether the single pictures may go.
 *
 * A PROPOSAL FROM WHERE A LAYER IS SHARP (step 5; „was natürlich toll
 * wäre, wenn es eine Erkennung geben würde für unscharfen Hintergrund und
 * scharfen Vordergrund. Das würde mir die Arbeit vereinfachen, den scharfen
 * Vordergrund von C nachzumalen"). A stack's subject is sharp and its
 * background is not; so the chosen layer is measured for fine detail, and
 * where there is enough of it the mask is proposed — to take or to leave,
 * and to mend with the brush. It is a proposal and says so: a smooth cap
 * is as poor in detail as the blur behind it, and what the measuring
 * cannot tell apart the hand must.
 *
 * RENDERINGS CUT DIFFERENTLY ARE ALIGNED (step 6; 11.11.2026:
 * „zwei Renderings … bei dem einen habe ich etwas mehr belichtet, bei dem
 * anderen etwas weniger … und da es aber zwei verschiedene Renderings
 * waren, hat Helicon unterschiedlich geschnitten"). Each layer carries a
 * MAP from the set's FRAME — the picture whose pixels the masks and the
 * finished picture have, the first one ticked — to its own picture
 * (`align`, in shares of width and height: six numbers, or eight where
 * the layer is another shot tilted against the frame — step 11). The
 * same size means the same cut: no map. Another size is aligned by
 * itself on opening (assets/js/align.js), and every layer can be aligned
 * again by itself. Where a layer does not reach, the layers under it
 * show.
 *
 * A PROPOSAL FROM BRIGHTNESS (step 7, the same evening): the darker
 * rendering over the brighter one, and the places proposed where the
 * picture under it is nearly burnt out — the hand-made HDR.
 *
 * EACH LAYER ITS OWN EXPOSURE (step 8, 13.11.2026, after his first HDR:
 * „dass ich die Helligkeit der einzelnen Bilder auch noch nachjustieren
 * kann … bevor ich sie zusammenrechne"). A slider per layer, the same
 * arithmetic as „Belichtung" in „Licht" (develop.js, light): ±100 are
 * ±3 stops, a gain on LINEAR light, so a darker rendering is lifted
 * toward its partner without turning grey. It acts everywhere a layer's
 * pixels are read — the stage, each layer alone, the half laid over for
 * aligning, the proposal from brightness (which reads what lies under
 * the chosen layer as one now sees it) and the finished picture — and is
 * kept with the set (`exposure`, layer_sets.php).
 *
 * TWO TABS ON TOP: „STACKING" AND „HDR" (step 9, the same evening:
 * „Vielleicht sollten wir ganz oben entscheiden, was wir machen … weil
 * momentan haben wir alles an einem Ort. Was verwirrt"). Stacking joins
 * Helicon's methods and offers the proposal from SHARPNESS; HDR joins
 * exposures and offers the per-layer exposure and the proposal from
 * BRIGHTNESS. The view, the layers, aligning, the mask's tools and the
 * merge stand in both. THE EXPOSURE ACTS ONLY UNDER HDR (his choice):
 * under Stacking it is hidden AND not applied — what one sees is what is
 * computed — but kept, and back under HDR it is there again. THE SET
 * KNOWS ITS KIND: `hdr: true` on its first layer in the row (as `frame`
 * marks one), so „Weitermachen" opens it under its tab without a
 * migration; a new set starts under the tab used last (settings
 * 'blendKind').
 *
 * THE COMMON AREA, CUT BY ITSELF (step 10, the same evening: „Kann ich bei
 * Ausrichten auch gleich einen automatischen Beschnitt bekommen? Dass wir
 * nur noch das zeigen, wo alle Ebenen quasi übereinstimmen"). A layer
 * aligned onto the frame leaves a wedge or a band where it does not
 * reach; the finished picture took that edge along, the ground showing
 * through there. Now the largest upright rectangle that EVERY SHOWN LAYER
 * covers is the picture: the stage SHOWS ONLY IT, fitted to it, and the
 * merge computes only that rectangle. It was dimmed around at first, and
 * an hour later: „Nein, schneid's gleich ab. Nicht nur abdunkeln. Das
 * stört … Wenn der Haken drin ist, ist entschieden, was ich will." The
 * masks keep the frame's pixels all the same — the canvas is clipped, not
 * made smaller —, so switching the tick off brings the edge back as it
 * was. While places are named („Nachjustieren") the whole frame shows.
 * It follows every aligning by itself. Layers of the same cut cover the
 * whole frame and nothing is cut. ALWAYS, WITHOUT A SWITCH
 * (14.11.2026: „Beschneiden ist Standard. Wir haben überhaupt keine Option,
 * das auszuschalten. Es wird immer gemacht"): the tick under the layers
 * went — its sentence was far too long for what nobody switched off. A
 * set kept with `crop: false` on its first layer (layer_sets.php still
 * takes and keeps that word) is cut all the same.
 *
 * A TILT IS ALIGNED TOO, AND „NACHJUSTIEREN" REPLACES THE HAND (step 11,
 * 13.11.2026: „Der Button von Hand ausrichten ist eigentlich unnutzbar …
 * automatisch geht auch schon die perspektivische Anpassung. Wenn man mit
 * dem Ergebnis nicht zufrieden ist, kann man auf einen Button klicken und
 * Punkte zuordnen"). The aligning measures a projective map where the
 * pictures ask for one (align.js says when) — the card draws it with the
 * map's third row (uC), the finished picture reads a tilted layer pixel
 * by pixel (warpInto; a canvas cannot draw a tilt), the common area and
 * the sharpness know it. Dragging the layer at half over the ground with
 * two sliders for turn and size is gone: one names places that are the
 * same in both pictures instead, and the measuring takes it from there
 * (adjustStart).
 */

import { t } from './i18n.js';
import { icon } from './icons.js';
import { tickBox } from './tickbox.js';
import { settings } from './settings.js';
import { spotLoopCloses, brushCore, brushOuter } from './develop.js';
import { request, api } from './api.js';
import { shortcutsProvide, typingIn, wheelStep, wheelWithCtrl } from './shortcuts.js';
import { confirm } from './dialogs.js';
import { uploadForPoint } from './cloudupload.js';
import { readCamera, stampJpeg, jpegProfile } from './exifstamp.js';
import { greyOf, alignCoarse, alignFine, alignReady, refinePoint, mapFromPairs, mapPoint, mapInverse, mapMat, matMul, matMap } from './align.js';

/** The long side the layers and their masks are drawn at, at most. */
const EDGE = 4096;
/** The ground and three more: the shader's texture units, and what one can still tell apart. */
const LAYERS_MAX = 4;
/* THE BRUSH REACHES FURTHER THAN THE RETOUCHING'S (11.11.2026:
   „der Pinsel darf ruhig noch ein bisschen größer werden. Manchmal brauche
   ich einen schön sanften Übergang, dann nehme ich einen großen Pinsel und
   zeichne mir mit 100 % weicher Kante einen sanften Übergang"). Its radius
   as a share of the picture's width, the slider's 0–100 laid over it
   evenly in ratio: fine at the small end, up to a third of the width at
   the large one — two thirds of the picture across. */
const BRUSH_MIN = .0008, BRUSH_MAX = .34;
const brushRadiusOf = (v) => BRUSH_MIN * Math.pow(BRUSH_MAX / BRUSH_MIN, Math.min(100, Math.max(0, v)) / 100);
/** A layer's exposure (−100 … 100) as the gain on linear light: ±3 stops, as „Belichtung" in develop.js. */
const exposureGain = (v) => Math.pow(2, Math.min(100, Math.max(-100, Number(v) || 0)) / 100 * 3);
/** A layer's exposure as it acts: only under HDR (step 9) — under Stacking it is kept but not applied. */
const exposureIn = (sess, layer) => (sess?.kind === 'hdr' ? Number(layer?.exposure) || 0 : 0);
/**
 * The 256 values a channel takes at that exposure — sRGB to linear, the
 * gain, back, clipped — UNROUNDED; null at 0, where nothing changes.
 *
 * ROUNDED WITH DITHER, NOT TO THE NEAREST (14.11.2026: „bau das
 * Dithern ein", after hard steps in the soft backgrounds of merged
 * pictures). A gain stretches the 256 steps of a channel: one stop up, a
 * smooth dark ramp of steps of 1 came out in steps of 2 (measured the same
 * morning), and rounded to the nearest value each missing step became an
 * edge in the background. exposureRound rounds each pixel up or down at
 * random, in proportion to its fraction — 56.4 becomes 57 four times in
 * ten —, so the steps that went missing come back as a mixture the eye
 * averages into the smooth ramp. The noise stays within one step and is
 * not seen.
 */
function exposureCurve(v) {
    if (!v) return null;
    const gain = exposureGain(v);
    const curve = new Float32Array(256);
    for (let i = 0; i < 256; i++) curve[i] = 255 * Math.min(1, Math.pow(Math.pow(i / 255, 2.2) * gain, 1 / 2.2));
    return curve;
}
/** Uniform numbers 0 … 1 for the dither — xorshift: the merge asks for some seventy million, Math.random would cost seconds. */
function ditherSource(seed = 0x2545f491) {
    let x = seed >>> 0 || 1;
    return () => {
        x ^= x << 13;
        x ^= x >>> 17;
        x ^= x << 5;
        return (x >>> 0) / 4294967296;
    };
}
/** A value 0 … 255 to a whole tone, up or down at random in proportion to its fraction (exposureCurve). */
const exposureRound = (x, rnd) => Math.min(255, Math.max(0, Math.floor(x + rnd())));
/** The size slider's place for a radius — brushRadiusOf turned round, unrounded. */
const brushSizeOf = (r) => Math.min(100, Math.max(0, Math.log(Math.max(BRUSH_MIN, r) / BRUSH_MIN) / Math.log(BRUSH_MAX / BRUSH_MIN) * 100));
/* THE BRUSH IS A SHARP CORE AND A SOFT EDGE BEYOND IT, here too
   (13.11.2026: „Die Pinselgröße ist für mich der scharfe Bereich, der innere
   Kreis. Nicht die Gesamtgröße"; develop.js, brushCore). What the brush
   keeps stays { size: the OUTER circle's slider place, feather: the share
   of it that fades } — the painting reads those —, and the sliders and the
   wheel speak of core and soft edge. Soft 100 reaches FIVE core radii
   beyond the core here: these brushes join two renderings along a passage
   and start very soft (the 82 of 11.11.2026 reads as about 91). */
const BLEND_REACH = 5;
/** A mask as a file, at most: the host takes two megabytes per upload (layer_sets.php, LAYER_MASK_MAX_BYTES). */
const MASK_BYTES = 1900 * 1024;
/** How long after the last change the work is kept — long enough not to save in the middle of a stroke's next. */
const SAVE_AFTER = 2500;
/** The finished picture's JPEG quality. High: it is saved once more, and nothing should show of that. */
const MERGE_QUALITY = .95;
/** The rows the finished picture is computed in at a time — a 24-megapixel picture is never held twice. */
const MERGE_STRIP = 256;
/** The long side a layer is measured for sharpness at — fine enough for an edge to mend, small enough to follow a slider. */
const SHARP_EDGE = 1536;
/** What the steps back may weigh in all — bytes of masks kept. */
const UNDO_BYTES = 256 * 1024 * 1024;
/** The long sides the aligning looks at: corners and pairs at the first, the fine measuring at the second. */
const ALIGN_COARSE = 1024, ALIGN_FINE = 2048;
/** A layer that lies exactly as the frame: (u, v) = (x, y). */
const ALIGN_SAME = [1, 0, 0, 0, 1, 0];
/* A MAP IS SIX NUMBERS OR EIGHT (13.11.2026, align.js): eight where the
   layer is another SHOT, tilted against the frame — the two more are the
   denominator, g·x + h·y + 1. Everything that reads a map reads both. */
const alignTilted = (al) => al.length === 8 && (Math.abs(al[6]) > 1e-9 || Math.abs(al[7]) > 1e-9);
const alignSame = (al) => ALIGN_SAME.every((v, i) => Math.abs(al[i] - v) < 1e-6) && !alignTilted(al);

/**
 * THE CANVAS'S TRANSFORM that draws a layer's own picture (iw × ih pixels)
 * into the frame drawn at OW × OH — the inverse of its map, in pixels.
 * The map takes the frame's (x, y), shares of its width and height, to
 * the layer's (u, v) = (a·x + b·y + c, d·x + e·y + f). ONLY FOR AN AFFINE
 * MAP: a canvas cannot draw a tilt — a tilted layer goes through
 * warpInto, pixel by pixel.
 */
function alignCanvas(al, iw, ih, OW, OH) {
    const [a, b, c, d, e, f] = al;
    const det = a * e - b * d;
    return [OW * e / (iw * det), -OH * d / (iw * det), -OW * b / (ih * det), OH * a / (ih * det),
        OW * (b * f - e * c) / det, OH * (d * c - a * f) / det];
}

/**
 * THE FIRST GUESS for a layer of another size than the frame: Helicon's
 * other cut — the same pixels, the middles on each other. The aligning
 * takes it from there.
 */
function alignGuess(NW, NH, iw, ih) {
    if (iw === NW && ih === NH) return ALIGN_SAME.slice();
    return [NW / iw, 0, .5 - NW / (2 * iw), 0, NH / ih, .5 - NH / (2 * ih)];
}

/**
 * A map between two grey pictures' PIXELS (align.js) as a map between
 * SHARES of their widths and heights, and back. A pixel's middle lies
 * half a pixel in, so the map moves by that half on both sides.
 */
function sharesOf(m, A, B) {
    return matMap(matMul([[1 / B.w, 0, .5 / B.w], [0, 1 / B.h, .5 / B.h], [0, 0, 1]], matMul(mapMat(m), [[A.w, 0, -.5], [0, A.h, -.5], [0, 0, 1]])));
}
function pixelsOf(al, A, B) {
    return matMap(matMul([[B.w, 0, -.5], [0, B.h, -.5], [0, 0, 1]], matMul(mapMat(al), [[1 / A.w, 0, .5 / A.w], [0, 1 / A.h, .5 / A.h], [0, 0, 1]])));
}

/**
 * A PICTURE THROUGH ANY MAP, PIXEL BY PIXEL — for the tilted layers a
 * canvas cannot draw. The rectangle (X, Y, w, h) of the frame drawn at
 * OW × OH comes back as pixels: each one's middle goes through the map
 * into the layer's picture and is read there between its four
 * neighbours; where the layer does not reach, the pixel stays empty
 * (alpha 0), as a canvas would leave it.
 *
 * ONLY THE PART OF THE PICTURE THE RECTANGLE NEEDS is unpacked — a strip
 * of the finished picture needs a band of the layer, never the hundred
 * megabytes of the whole —, and where the rectangle is much smaller than
 * what it shows (the sharpness is measured at 1536 pixels), that part is
 * first drawn smaller by the canvas, smoothly, so no detail is skipped.
 */
function warpInto(bitmap, al, OW, OH, X, Y, w, h, scratch) {
    const out = new ImageData(w, h);
    const iw = bitmap.width, ih = bitmap.height;
    // Where the rectangle's rim lands in the picture: the part to unpack.
    let x0 = Infinity, x1 = -Infinity, y0 = Infinity, y1 = -Infinity;
    for (let k = 0; k <= 8; k++) {
        for (const [fx, fy] of [[k / 8, 0], [k / 8, 1], [0, k / 8], [1, k / 8]]) {
            const [u, v] = mapPoint(al, (X + fx * w) / OW, (Y + fy * h) / OH);
            if (!Number.isFinite(u) || !Number.isFinite(v)) continue;
            x0 = Math.min(x0, u * iw); x1 = Math.max(x1, u * iw); y0 = Math.min(y0, v * ih); y1 = Math.max(y1, v * ih);
        }
    }
    const bx = Math.max(0, Math.floor(x0) - 2), by = Math.max(0, Math.floor(y0) - 2);
    const bw = Math.min(iw, Math.ceil(x1) + 2) - bx, bh = Math.min(ih, Math.ceil(y1) + 2) - by;
    if (!(bw > 0 && bh > 0)) return out;
    const k = Math.min(1, 1.5 / Math.max(bw / w, bh / h));
    const sw = Math.max(2, Math.ceil(bw * k)), sh = Math.max(2, Math.ceil(bh * k));
    scratch.width = sw;
    scratch.height = sh;
    const sctx = scratch.getContext('2d', { willReadFrequently: true });
    sctx.imageSmoothingQuality = 'high';
    sctx.drawImage(bitmap, bx, by, bw, bh, 0, 0, sw, sh);
    const src = sctx.getImageData(0, 0, sw, sh).data;
    const kx = sw / bw, ky = sh / bh;
    const o = out.data;
    const [a, b, c, d, e, f] = al, g = al[6] ?? 0, hh = al[7] ?? 0;
    for (let j = 0; j < h; j++) {
        const fy = (Y + j + .5) / OH;
        for (let i = 0; i < w; i++) {
            const fx = (X + i + .5) / OW;
            const den = g * fx + hh * fy + 1;
            const u = (a * fx + b * fy + c) / den, v = (d * fx + e * fy + f) / den;
            if (!(u >= 0 && u <= 1 && v >= 0 && v <= 1)) continue;
            // The picture's pixel place, then the unpacked part's — pixel middles on both sides.
            const sx = Math.min(sw - 1, Math.max(0, (u * iw - bx) * kx - .5)), sy = Math.min(sh - 1, Math.max(0, (v * ih - by) * ky - .5));
            const px = Math.min(sw - 2, sx | 0), py = Math.min(sh - 2, sy | 0), tx = sx - px, ty = sy - py;
            const n = (py * sw + px) * 4, m = n + sw * 4, q = (j * w + i) * 4;
            const w00 = (1 - tx) * (1 - ty), w10 = tx * (1 - ty), w01 = (1 - tx) * ty, w11 = tx * ty;
            o[q] = src[n] * w00 + src[n + 4] * w10 + src[m] * w01 + src[m + 4] * w11 + .5;
            o[q + 1] = src[n + 1] * w00 + src[n + 5] * w10 + src[m + 1] * w01 + src[m + 5] * w11 + .5;
            o[q + 2] = src[n + 2] * w00 + src[n + 6] * w10 + src[m + 2] * w01 + src[m + 6] * w11 + .5;
            o[q + 3] = 255;
        }
    }
    return out;
}

/**
 * THE COMMON AREA (step 10): the frame's unit square cut by every map's
 * reach. A map takes the frame's (x, y) to the layer's (u, v); the layer
 * is there where 0 ≤ u ≤ 1 and 0 ≤ v ≤ 1 — four half-planes in (x, y),
 * for an affine map and, multiplied through by its denominator, for a
 * tilted one too. Each cuts the convex polygon once
 * (Sutherland–Hodgman); what stays is convex. Empty: null.
 */
function commonArea(maps) {
    let poly = [[0, 0], [1, 0], [1, 1], [0, 1]];
    const cut = (al, be, ga) => {
        const inside = (p) => al * p[0] + be * p[1] + ga >= -1e-9;
        const out = [];
        for (let i = 0; i < poly.length; i++) {
            const p = poly[i], q = poly[(i + 1) % poly.length];
            const pi = inside(p), qi = inside(q);
            if (pi) out.push(p);
            if (pi !== qi) {
                const fp = al * p[0] + be * p[1] + ga, fq = al * q[0] + be * q[1] + ga;
                const k = fp / (fp - fq);
                out.push([p[0] + (q[0] - p[0]) * k, p[1] + (q[1] - p[1]) * k]);
            }
        }
        poly = out;
    };
    for (const [a, b, c, d, e, f, g = 0, h = 0] of maps) {
        // A tilted map divides by g·x + h·y + 1 — positive wherever the layer is; multiplied through, the four stay half-planes.
        cut(g, h, 1 - 1e-6);            // the denominator > 0
        cut(a, b, c);                   // u ≥ 0
        cut(g - a, h - b, 1 - c);       // u ≤ 1
        cut(d, e, f);                   // v ≥ 0
        cut(g - d, h - e, 1 - f);       // v ≤ 1
        if (poly.length < 3) return null;
    }
    return poly;
}

/**
 * THE LARGEST UPRIGHT RECTANGLE IN A CONVEX POLYGON, by area in pixels
 * (`aspect` = the frame's width over its height, the shares being of
 * each). In a convex polygon a rectangle lies inside when its top and its
 * bottom edge do, so for two heights the width is what both chords share:
 * every pair of heights on a grid, then a finer grid around the best.
 */
function largestRect(poly, aspect) {
    const ys = poly.map((p) => p[1]);
    const lo = Math.min(...ys), hi = Math.max(...ys);
    if (hi - lo < 1e-6) return null;
    const chord = (y) => {
        let l = Infinity, r = -Infinity;
        for (let i = 0; i < poly.length; i++) {
            const p = poly[i], q = poly[(i + 1) % poly.length];
            if ((p[1] - y) * (q[1] - y) > 0) continue;
            if (Math.abs(q[1] - p[1]) < 1e-12) { l = Math.min(l, p[0], q[0]); r = Math.max(r, p[0], q[0]); continue; }
            const x = p[0] + (q[0] - p[0]) * (y - p[1]) / (q[1] - p[1]);
            l = Math.min(l, x);
            r = Math.max(r, x);
        }
        return [l, r];
    };
    let best = null, bestArea = 0;
    const tryPair = (y0, y1) => {
        if (y1 <= y0) return;
        const a = chord(y0), b = chord(y1);
        const x0 = Math.max(a[0], b[0]), x1 = Math.min(a[1], b[1]);
        const area = (x1 - x0) * (y1 - y0) * aspect;
        if (x1 > x0 && area > bestArea) { bestArea = area; best = { x0, x1, y0, y1 }; }
    };
    const N = 240, step = (hi - lo) / N;
    const at = (i) => Math.min(hi, Math.max(lo, lo + i * step));
    const chords = Array.from({ length: N + 1 }, (_, i) => chord(at(i)));
    let bi = 0, bj = N;
    for (let i = 0; i <= N; i++) {
        for (let j = i + 1; j <= N; j++) {
            const x0 = Math.max(chords[i][0], chords[j][0]), x1 = Math.min(chords[i][1], chords[j][1]);
            const area = (x1 - x0) * (j - i) * step * aspect;
            if (x1 > x0 && area > bestArea) { bestArea = area; best = { x0, x1, y0: at(i), y1: at(j) }; bi = i; bj = j; }
        }
    }
    // Finer, a step either side of the best pair.
    const F = 40;
    for (let i = -F; i <= F; i++) {
        for (let j = -F; j <= F; j++) tryPair(at(bi) + i * step / F, at(bj) + j * step / F);
    }
    return best;
}

/**
 * A set's common area in the frame's pixels, { x, y, w, h } — or null where
 * nothing is cut: every shown layer of the frame's own cut,
 * or nothing sensible left. Kept on the set by the maps it was computed
 * from, since the stage asks at every frame it draws.
 */
function cropOf(sess) {
    if (!sess) return null;
    const maps = sess.layers.filter((l) => l.shown && !alignSame(l.align)).map((l) => l.align);
    const key = JSON.stringify(maps);
    if (sess.cropKey === key) return sess.cropBox;
    let box = null;
    const poly = maps.length ? commonArea(maps) : null;
    const r = poly ? largestRect(poly, sess.NW / sess.NH) : null;
    if (r) {
        const { NW, NH } = sess;
        const x = Math.max(0, Math.ceil(r.x0 * NW - 1e-6)), y = Math.max(0, Math.ceil(r.y0 * NH - 1e-6));
        const x1 = Math.min(NW, Math.floor(r.x1 * NW + 1e-6)), y1 = Math.min(NH, Math.floor(r.y1 * NH + 1e-6));
        const whole = x === 0 && y === 0 && x1 === NW && y1 === NH;
        if (!whole && x1 - x >= 16 && y1 - y >= 16) box = { x, y, w: x1 - x, h: y1 - y };
    }
    sess.cropKey = key;
    sess.cropBox = box;
    return box;
}

const VERTEX = `
attribute vec2 p;
varying vec2 v;
void main() {
    v = vec2(p.x * .5 + .5, .5 - p.y * .5);
    gl_Position = vec4(p, 0., 1.);
}`;

/* The ground, then each layer over it by its mask. uView ≥ 0 shows one
   layer alone — the rendering as it came, to compare. uVeil lays the
   chosen layer's mask over whatever is shown, in a colour no fungus has.

   WHERE NOTHING SHOWS, A CHEQUER — the ground switched off and no layer
   painted there. It was a flat dark grey at first, and the maintainer, hiding
   the ground on the first evening, read it as „die anderen zwei Bilder
   sind nicht vorhanden": black says broken, the chequer says empty, as
   every program with layers has taught. */
/* EACH LAYER THROUGH ITS MAP (step 6): uA and uB are the two rows of a
   layer's map, the frame's place in, the layer's out; where the layer
   does not reach (outside 0 … 1) it is not there, and what lies under it
   shows. uC is the map's third row, the denominator of a tilted layer
   (0, 0, 1 for all others). HIGH PRECISION where the card has it: a map turns a share of
   the frame into a share of a picture four thousand pixels wide, and the
   middle precision of a phone's card stops at about two thousand. */
/* VEIL_ROOT — THE MASK SHOWN AS FAR AS IT REACHES (11.11.2026,
   after the same for „Ausbessern": „bei den Ebenen bitte genauso"). The
   veil laid the mask over the picture at its own strength, and a soft
   edge at half the veil's strength was gone to the eye halfway between
   the brush's two rings — the painted place looked smaller than the
   cursor that painted it. Shown by the ROOT of the mask, the soft edge
   stays faintly visible out to the dashed ring. Only the veil: the
   layers themselves are mixed by the mask as it is. */
const FRAGMENT = `
#ifdef GL_FRAGMENT_PRECISION_HIGH
precision highp float;
#else
precision mediump float;
#endif
varying vec2 v;
uniform sampler2D uL0, uL1, uL2, uL3, uM1, uM2, uM3;
uniform vec4 uOn, uGain;
uniform vec3 uA[4], uB[4], uC[4];
uniform float uView, uVeil, uCheck;
vec2 at(vec3 a, vec3 b, vec3 c) { vec3 p = vec3(v, 1.); return vec2(dot(a, p), dot(b, p)) / dot(c, p); }
float inside(vec2 w) { return step(0., w.x) * step(w.x, 1.) * step(0., w.y) * step(w.y, 1.); }
// A layer's own exposure (step 8): a gain on linear light, as exposureCurve does for the finished picture.
vec3 lit(vec3 c, float gain) { return gain == 1. ? c : min(pow(pow(max(c, 0.), vec3(2.2)) * gain, vec3(1. / 2.2)), 1.); }
void main() {
    vec2 w0 = at(uA[0], uB[0], uC[0]), w1 = at(uA[1], uB[1], uC[1]), w2 = at(uA[2], uB[2], uC[2]), w3 = at(uA[3], uB[3], uC[3]);
    vec3 l0 = lit(texture2D(uL0, w0).rgb, uGain.x), l1 = lit(texture2D(uL1, w1).rgb, uGain.y),
         l2 = lit(texture2D(uL2, w2).rgb, uGain.z), l3 = lit(texture2D(uL3, w3).rgb, uGain.w);
    float i0 = inside(w0), i1 = inside(w1), i2 = inside(w2), i3 = inside(w3);
    float m1 = texture2D(uM1, v).r, m2 = texture2D(uM2, v).r, m3 = texture2D(uM3, v).r;
    vec2 g = floor(gl_FragCoord.xy / uCheck);
    vec3 check = vec3(mod(g.x + g.y, 2.) < .5 ? .16 : .24);
    vec3 c;
    if (uView < -.5) {
        c = mix(check, l0, uOn.x * i0);
        c = mix(c, l1, m1 * uOn.y * i1);
        c = mix(c, l2, m2 * uOn.z * i2);
        c = mix(c, l3, m3 * uOn.w * i3);
    } else if (uView < .5) c = mix(check, l0, i0);
    else if (uView < 1.5) c = mix(check, l1, i1);
    else if (uView < 2.5) c = mix(check, l2, i2);
    else c = mix(check, l3, i3);
    float m = uVeil < .5 ? 0. : uVeil < 1.5 ? m1 : uVeil < 2.5 ? m2 : m3;
    // The veil fades by the root of the mask, so its soft edge is seen out to the brush's dashed ring (see VEIL_ROOT above).
    c = mix(c, vec3(1., .1, .45), sqrt(m) * .5);
    gl_FragColor = vec4(c, 1.);
}`;

function el(tag, className = '', text = '') {
    const n = document.createElement(tag);
    if (className) n.className = className;
    if (text) n.textContent = text;
    return n;
}

/** A field blurred with a box of the given radius, rows then columns — twice makes it nearly round. In place. */
function boxBlur(f, w, h, r) {
    if (r < 1) return f;
    const tmp = new Float32Array(Math.max(w, h));
    const pass = (start, step, n) => {
        let sum = 0;
        for (let i = -r; i <= r; i++) sum += f[start + Math.min(n - 1, Math.max(0, i)) * step];
        for (let i = 0; i < n; i++) {
            tmp[i] = sum / (2 * r + 1);
            sum += f[start + Math.min(n - 1, i + r + 1) * step] - f[start + Math.max(0, i - r) * step];
        }
        for (let i = 0; i < n; i++) f[start + i * step] = tmp[i];
    };
    for (let y = 0; y < h; y++) pass(y * w, 1, w);
    for (let x = 0; x < w; x++) pass(x, w, h);
    return f;
}

/**
 * HOW SHARP A PICTURE IS, PLACE BY PLACE — for the proposal of a mask.
 * `rgba` is the picture small (w × h). Sharp means fine detail: how much
 * each pixel differs from the mean of its four neighbours (the Laplacian),
 * gathered over a small neighbourhood so a single edge does not stand
 * alone. Scaled so that the sharpest hundredth of the picture is 1, and
 * the root taken — detail spans orders of magnitude, and a slider over the
 * bare number would have all its travel at one end.
 *
 * `auto` is the threshold that parts the picture's two kinds best (Otsu's
 * rule: the cut that leaves each side most alike in itself) — where the
 * slider starts.
 *
 * @returns {{ sharp: Float32Array, auto: number }}
 */
function sharpness(rgba, w, h) {
    const grey = new Float32Array(w * h);
    for (let n = 0, i = 0; n < grey.length; n++, i += 4) grey[n] = .299 * rgba[i] + .587 * rgba[i + 1] + .114 * rgba[i + 2];
    const sharp = new Float32Array(w * h);
    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const n = y * w + x;
            sharp[n] = Math.abs(4 * grey[n] - grey[n - 1] - grey[n + 1] - grey[n - w] - grey[n + w]);
        }
    }
    const r = Math.max(2, Math.round(Math.max(w, h) * .004));
    boxBlur(boxBlur(sharp, w, h, r), w, h, r);
    // The sharpest hundredth, off a histogram.
    let top = 0;
    for (let n = 0; n < sharp.length; n++) if (sharp[n] > top) top = sharp[n];
    const bins = new Uint32Array(1024);
    for (let n = 0; n < sharp.length; n++) bins[Math.min(1023, (sharp[n] / (top || 1) * 1023) | 0)]++;
    let above = 0, cut = 1023;
    while (cut > 0 && above + bins[cut] < sharp.length / 100) above += bins[cut--];
    const one = Math.max(1e-6, (cut + 1) / 1024 * top);
    const hist = new Float64Array(256);
    for (let n = 0; n < sharp.length; n++) {
        sharp[n] = Math.sqrt(Math.min(1, sharp[n] / one));
        hist[Math.min(255, (sharp[n] * 255) | 0)]++;
    }
    let total = 0;
    for (let i = 0; i < 256; i++) total += i * hist[i];
    let below = 0, belowSum = 0, best = 0, auto = .3;
    for (let i = 0; i < 256; i++) {
        below += hist[i];
        belowSum += i * hist[i];
        const over = sharp.length - below;
        if (!below || !over) continue;
        const gap = belowSum / below - (total - belowSum) / over;
        const between = below * over * gap * gap;
        if (between > best) { best = between; auto = (i + 1) / 256; }
    }
    return { sharp, auto };
}

/**
 * The mask a sharpness field proposes (w × h bytes, 0 or 255 with a soft
 * edge): everything above `threshold`, then made into AREAS —
 *   closed: gaps narrower than a hundred-and-fiftieth of the picture are
 *     bridged, so the fine detail of a subject becomes its surface;
 *   holes filled: what the sharp encloses belongs to it (a smooth cap
 *     inside its sharp rim), found as what cannot be reached from the
 *     picture's edge;
 *   specks dropped: an island smaller than a two-thousandth of the
 *     picture is noise in the blur, not a subject;
 * and blurred by `soft` (0 … 1) for its edge.
 */
function sharpMask(sharp, w, h, threshold, soft) {
    const N = w * h;
    const on = new Float32Array(N);
    for (let n = 0; n < N; n++) on[n] = sharp[n] >= threshold ? 1 : 0;
    // Closing: grown by r, then shrunk by r — as box sums, a square for a disc.
    const r = Math.max(1, Math.round(Math.max(w, h) / 150));
    boxBlur(on, w, h, r);
    for (let n = 0; n < N; n++) on[n] = on[n] > 1e-6 ? 0 : 1;      // grown, and turned over
    boxBlur(on, w, h, r);
    const bit = new Uint8Array(N);
    for (let n = 0; n < N; n++) bit[n] = on[n] > 1e-6 ? 0 : 1;     // the turned-over grown = shrunk
    // Holes: the empty that the picture's edge cannot reach.
    const stack = new Int32Array(N);
    const flood = (seeds, from, to) => {
        let top = 0, count = 0;
        for (const n of seeds) if (bit[n] === from) { bit[n] = to; stack[top++] = n; }
        while (top) {
            const n = stack[--top];
            count++;
            const x = n % w;
            if (x > 0 && bit[n - 1] === from) { bit[n - 1] = to; stack[top++] = n - 1; }
            if (x < w - 1 && bit[n + 1] === from) { bit[n + 1] = to; stack[top++] = n + 1; }
            if (n >= w && bit[n - w] === from) { bit[n - w] = to; stack[top++] = n - w; }
            if (n < N - w && bit[n + w] === from) { bit[n + w] = to; stack[top++] = n + w; }
        }
        return count;
    };
    const rim = [];
    for (let x = 0; x < w; x++) rim.push(x, N - w + x);
    for (let y = 0; y < h; y++) rim.push(y * w, y * w + w - 1);
    flood(rim, 0, 2);                                   // 2: empty, reached from the edge
    for (let n = 0; n < N; n++) bit[n] = bit[n] === 2 ? 0 : 1;
    // Specks: islands too small to be a subject.
    const least = N / 2000;
    for (let n = 0; n < N; n++) {
        if (bit[n] !== 1) continue;
        const size = flood([n], 1, 3);                  // 3: an island, counted
        if (size < least) flood([n], 3, 0);
    }
    const out = new Float32Array(N);
    for (let n = 0; n < N; n++) out[n] = bit[n] ? 255 : 0;
    const blur = Math.round(soft * Math.max(w, h) * .02);
    boxBlur(boxBlur(out, w, h, blur), w, h, blur);
    const bytes = new Uint8Array(N);
    for (let n = 0; n < N; n++) bytes[n] = Math.round(out[n]);
    return bytes;
}

/**
 * The mask brightness proposes (step 7; w × h bytes): everything at or
 * above `threshold` (0 … 1 of the brightest channel), GROWN by the soft
 * edge's reach before it is softened — a burnt-out place glows into its
 * surroundings, and an edge that faded out exactly at its rim would leave
 * that glow standing. Islands smaller than a five-thousandth of the
 * picture are a glint, not a place.
 */
function brightMask(bright, w, h, threshold, soft) {
    const N = w * h;
    const on = new Float32Array(N);
    for (let n = 0; n < N; n++) on[n] = bright[n] >= threshold ? 1 : 0;
    const r = Math.round(soft * Math.max(w, h) * .02);
    if (r >= 1) {
        boxBlur(on, w, h, r);
        for (let n = 0; n < N; n++) on[n] = on[n] > 1e-6 ? 1 : 0;
    }
    // Glints: islands too small to be a place.
    const bit = new Uint8Array(N);
    for (let n = 0; n < N; n++) bit[n] = on[n] ? 1 : 0;
    const stack = new Int32Array(N);
    const least = N / 5000;
    for (let s0 = 0; s0 < N; s0++) {
        if (bit[s0] !== 1) continue;
        const seen = [];
        let top = 0;
        bit[s0] = 2;
        stack[top++] = s0;
        while (top) {
            const n = stack[--top];
            seen.push(n);
            const x = n % w;
            if (x > 0 && bit[n - 1] === 1) { bit[n - 1] = 2; stack[top++] = n - 1; }
            if (x < w - 1 && bit[n + 1] === 1) { bit[n + 1] = 2; stack[top++] = n + 1; }
            if (n >= w && bit[n - w] === 1) { bit[n - w] = 2; stack[top++] = n - w; }
            if (n < N - w && bit[n + w] === 1) { bit[n + w] = 2; stack[top++] = n + w; }
        }
        if (seen.length < least) for (const n of seen) on[n] = 0;
    }
    const out = new Float32Array(N);
    for (let n = 0; n < N; n++) out[n] = on[n] * 255;
    boxBlur(boxBlur(out, w, h, r), w, h, r);
    const bytes = new Uint8Array(N);
    for (let n = 0; n < N; n++) bytes[n] = Math.round(out[n]);
    return bytes;
}

/**
 * The tool, built once per page. The host is the page that holds the find
 * and the photo window:
 *   pictures()   the find's pictures in the gallery's order —
 *                { key, name, n, thumb, original, w, h, title, scale, takenBy, file }
 *   refresh(key) the find's pictures changed (one came, some went): draw them
 *                anew, and show the picture `key` where one is named
 *   point()      the find's id;  baseUrl  where the pages lie
 *   may()        is the tool offered here at all (own find, a cloud …)
 *   shown()      the key of the picture in the viewer
 *   subject(key) the keys of the pictures with that picture's subject name
 *   tiles()      the rail's tiles — [{ key, el }] —, tilesWatch() the box they
 *                stand in, and tilesRoot() the box around that, where the
 *                tick boxes are laid over them
 *   stackChanged() the set being worked on came or went (stack() says
 *                which pictures it holds) — the rail marks them all
 *
 * @returns {{ panel: HTMLElement, stage: HTMLElement, may: () => boolean,
 *             setActive: (on: boolean) => void, pictureShown: () => void, test: object }}
 */
export function mountBlend(host) {
    // --- the two parts the photo window takes in --------------------------
    const panel = el('div', 'blend-panel');

    /* WHAT ONE MAKES, CHOSEN ON TOP (step 9): the tab of the set in hand,
       or — while choosing — the one a new set starts under. */
    let kindLast = settings.get('blendKind', 'stack') === 'hdr' ? 'hdr' : 'stack';
    function kindKeep(next) {
        kindLast = next;
        settings.set('blendKind', next);
    }
    /* TWO BUTTONS, NOT TABS (14.11.2026: „die zwei Tabs oben
       Stacking und HDR als Buttons gestalten … da wir unten drunter viele
       Einstellungsmöglichkeiten haben, dann ein bisschen abgehoben vom
       Rest. Dass man sieht, das sind die zwei Hauptwege"): one split
       button over the column's width, in a bar of its own (the styles,
       .blend-kinds). */
    const kindRow = el('div', 'blend-kinds');
    const kindSwitch = el('div', 'blend-kinds-switch');
    kindSwitch.setAttribute('role', 'tablist');
    kindRow.append(kindSwitch);
    const kindTabs = [['stack', t('Stacking'), t('Die Renderings eines Stapels zusammenrechnen — Helicons Methoden A, B, C')],
        ['hdr', t('HDR'), t('Verschieden belichtete Bilder zusammenrechnen')]].map(([key, name, hint]) => {
        const b = el('button', 'blend-kind', name);
        b.type = 'button';
        b.title = hint;
        b.setAttribute('role', 'tab');
        b.addEventListener('click', () => kindSet(key));
        kindSwitch.append(b);
        return { key, b };
    });
    const kindNow = () => session?.kind ?? kindLast;
    function kindDraw() {
        for (const { key, b } of kindTabs) {
            b.classList.toggle('is-active', key === kindNow());
            b.setAttribute('aria-selected', String(key === kindNow()));
            b.disabled = merging;
        }
    }
    function kindSet(next) {
        kindKeep(next);
        if (session && mode === 'work' && session.kind !== next) {
            // A proposal of the other tab is taken as it stands, as leaving the work takes it.
            proposeEnd(true);
            session.kind = next;
            changed();
            render();
            panelDraw();
        }
        kindDraw();
    }

    // Choosing: which pictures belong together — ticked on the rail's tiles.
    const pick = el('div', 'blend-pick');
    const pickStarted = el('div', 'blend-started');
    pickStarted.hidden = true;
    const pickField = el('fieldset', 'field question');
    // Asked with the button's own word (11.11.2026: „Welche Bilder zeigen dasselbe?" was right but „dumm formuliert").
    pickField.append(el('legend', '', t('Welche Bilder übereinanderlegen?')));
    const pickChosen = el('div', 'blend-chosen');
    const pickProblem = el('p', 'blend-problem');
    pickProblem.hidden = true;
    pickField.append(pickChosen, pickProblem);
    const pickFooter = el('div', 'dialog-footer');
    const pickOpen = el('button', 'button', t('Übereinanderlegen'));
    pickOpen.type = 'button';
    pickFooter.append(pickOpen);
    pick.append(pickStarted, pickField, pickFooter);

    // Working: the panel's half of it; the picture's half is the stage.
    const work = el('div', 'blend-work');
    work.hidden = true;
    /* HOW THE WORK IS KEPT stands at the right end of the head „Ansicht" —
       no line of its own (11.11.2026: the sidebar had grown too
       long for a small screen). The sizes that stood above it went: that
       one paints on a smaller working copy and the picture is computed
       from the originals is said where it is decided (EDGE above), and
       the panel needs the room. */
    const savedNote = el('span', 'blend-saved');
    savedNote.setAttribute('role', 'status');
    const side = el('div', 'blend-side');
    work.append(side);
    panel.append(kindRow, pick, work);

    const stage = el('div', 'blend-stage');
    stage.hidden = true;
    const canvas = el('canvas', 'blend-canvas');
    // The keys (space to move, the numbers to compare) go to the stage, not to whichever button was pressed last.
    stage.tabIndex = -1;
    /* THE BRUSH'S TWO RINGS (11.11.2026: „Das Malen und
       Radieren-Werkzeug bei den Ebenen zeigt mir auch nicht den Verlauf
       an … diese zwei Kreise anzeigst. Am Cursor"), as „Ausbessern" has
       them (photowindow.js, brushRingDraw): SOLID up to where the brush
       paints whole, DASHED where its soft edge has faded to nothing —
       the gradient's language. Each drawn twice, dark under light. */
    const SVGNS = 'http://www.w3.org/2000/svg';
    const ring = el('div', 'blend-ring');
    ring.hidden = true;
    const ringSvg = document.createElementNS(SVGNS, 'svg');
    const ringCircles = [];
    for (const dashed of [false, true]) {
        for (const [stroke, width] of [['#000', 3], ['#fff', 1.5]]) {
            const c = document.createElementNS(SVGNS, 'circle');
            c.setAttribute('fill', 'none');
            c.setAttribute('stroke', stroke);
            c.setAttribute('stroke-width', String(width));
            if (dashed) c.setAttribute('stroke-dasharray', '5 4');
            ringSvg.append(c);
            ringCircles.push({ c, dashed });
        }
    }
    ring.append(ringSvg);
    // The places named while adjusting (adjustPins) — over the picture, under the pointer's ring.
    const pins = el('div', 'blend-pins');
    pins.hidden = true;
    stage.append(canvas, pins, ring);

    // --- the state --------------------------------------------------------
    let active = false;             // the tab „Ebenen" is the one open in „Bearbeiten"
    let mode = 'pick';              // pick: ticking on the rail · work: the stage over the picture
    let touched = false;            // a tick was set by hand since the tab opened — nothing starts by itself any more
    let picked = [];                // keys ticked on the rail, in the gallery's order
    let session = null;             // { layers: [{ key, name, short, thumb, blob, shown, mask, tex, maskTex }], W, H, NW, NH }
    let loading = false;
    let gl = null, program = null, uniforms = null, blank = null;
    let view = -1;                  // -1 the result, else the index of the one layer shown
    let chosen = 1;                 // the layer painted on (never 0, the ground)
    let tool = 'paint';             // paint | erase | fill
    /* THE BRUSH AND THE ERASER EACH KEEP THEIR OWN SETTINGS
       (11.11.2026: „wenn man zwischen Malen und Radieren wechselt, soll sich
       die Einstellung gemerkt werden") — size, soft edge and opacity, 0–100
       each, and the device keeps both. One paints a subject in with a wide
       soft brush and takes a slip back with a small hard one; sharing the
       sliders meant setting them anew at every change.

       THE SOFT EDGE STARTS HIGH („standardmäßig würde ich die weiche Kante
       ziemlich hochsetzen, so auf 82"): two renderings are joined along a
       passage, not along a cut. */
    const brushes = (() => {
        const kept = settings.get('blendBrushes', null) ?? {};
        const one = (b) => {
            // To a hundredth, not whole: size and share follow the core and the soft edge now (brushSet).
            const n = (v, fallback) => (Number.isFinite(Number(v)) && v !== null ? Math.round(Math.min(100, Math.max(0, Number(v))) * 100) / 100 : fallback);
            return { size: n(b?.size, 45), feather: n(b?.feather, 82), strength: n(b?.strength, 100) };
        };
        return { paint: one(kept.paint), erase: one(kept.erase) };
    })();
    // A copy is written, never the object the tool goes on changing (settings.js).
    const brushesKeep = () => settings.set('blendBrushes', { paint: { ...brushes.paint }, erase: { ...brushes.erase } });
    /* THE OPTION KEY TURNS THE BRUSH INTO THE ERASER FOR AS LONG AS IT IS
       HELD (11.11.2026: „wenn ich im Pinselwerkzeug bin und die
       Option-Taste drücke, dass kurz temporär in den Radiergummi
       umgeschaltet wird") — as the mask brush of „Masken" does — and the
       eraser back into the brush the same way. Its button lights. A stroke
       under way stays what it began as.

       THE PARTNER HELD BY OPTION KEEPS THE SETTINGS OF THE TOOL IN HAND
       (13.11.2026: „mit genau den gleichen Einstellungen, also
       der gleichen Größe und der gleichen weichen Kante und der gleichen
       Deckkraft wie der Pinsel" — and the other way round). Option is a
       touch-up in passing: the slip taken back along the edge just painted,
       with that very brush. Until then it took the eraser's own settings,
       and the ring jumped to another size under the hand. The settings of
       11.11.2026 stay per tool for what they were asked for — the CHANGE of
       tool by button, B, E or X. */
    let altHeld = false;
    const partner = { paint: 'erase', erase: 'paint' };
    const toolNow = () => (altHeld && partner[tool] ? partner[tool] : tool);
    /** The settings in force — the chosen tool's, also while Option holds its partner („Füllen" has none of its own). */
    const brush = () => brushes[tool === 'erase' ? 'erase' : 'paint'];
    /* The chosen layer's mask laid over the picture — off unless asked
       for, and the device keeps the choice: two renderings of one stack
       look alike wherever both are sharp, and whoever paints there sees
       only with the veil that paint is laid. */
    let veil = settings.get('blendVeil', false) === true;
    let s = 1, tx = 0, ty = 0;      // the picture on the stage: scale and place, in stage pixels
    let stroke = null;              // the stroke being painted
    let pan = null;                 // the drag that moves the picture
    let spaceDown = false;
    let snap = null, strokeBuf = null;   // the mask as the stroke found it, and the stroke's own cover
    const undo = [];                // { layer, x, y, w, h, before }
    let queued = false;
    let stored = { point: 0, ready: false, sets: [] };   // what the server keeps for this find
    let saveTimer = null, saveChain = Promise.resolve(), saving = 0;

    const radiusPx = () => Math.max(.75, brushRadiusOf(brush().size) * session.W);

    // --- choosing ---------------------------------------------------------
    /** What was begun at this find, each to go on with — or to give up, masks and all. */
    function startedDraw() {
        const pictures = new Map(host.pictures().map((p) => [p.key, p]));
        pickStarted.textContent = '';
        /* THE SET IN THE TOOL'S HANDS comes first and at once — its pictures
           are loaded, its masks here; it needs no fetching, and it may not
           be kept yet (the save is a moment behind, or the cloud refused). */
        const held = session ? session.layers.map((l) => l.key).sort().join(' ') : null;
        const sets = stored.sets.slice();
        if (session && !sets.some((x) => x.layers.map((l) => l.key).sort().join(' ') === held)) {
            sets.unshift({ id: null, layers: session.layers.map((l) => ({ key: l.key })) });
        }
        for (const set of sets) {
            const names = set.layers.map((l) => pictures.get(l.key)?.name).filter(Boolean);
            if (names.length !== set.layers.length) continue;
            const here = held !== null && set.layers.map((l) => l.key).sort().join(' ') === held;
            const row = el('div', 'blend-started-row');
            const go = el('button', 'button', t('Weitermachen: %s', names.join(', ')));
            go.type = 'button';
            go.disabled = loading;
            go.addEventListener('click', () => {
                if (here) return show('work');
                picked = host.pictures().map((p) => p.key).filter((k) => set.layers.some((l) => l.key === k));
                pickDraw();
                pickLoad();
            });
            const drop = el('button', 'button subtle danger blend-started-drop');
            drop.type = 'button';
            drop.title = t('Arbeitsstand verwerfen');
            drop.setAttribute('aria-label', drop.title);
            drop.append(icon('delete'));
            drop.disabled = loading;
            drop.addEventListener('click', async () => {
                const yes = await confirm({
                    title: t('Arbeitsstand verwerfen?'),
                    text: t('Was du bei „%s" eingemalt hast, geht verloren. Die Bilder selbst bleiben, wie sie sind.', names.join(', ')),
                    button: t('Verwerfen'), danger: true,
                });
                if (!yes) return;
                try {
                    if (set.id !== null) await request('layer_sets.php', { method: 'POST', body: JSON.stringify({ action: 'delete', id: set.id }) });
                } catch (error) {
                    return pickSay(error.message || t('Der Arbeitsstand ließ sich nicht verwerfen.'), true);
                }
                if (here) sessionEnd(false);
                await storedFetch();
                pickDraw();
            });
            row.append(go, drop);
            pickStarted.append(row);
        }
        pickStarted.hidden = pickStarted.children.length === 0;
    }

    /** Tick or untick one picture — from its box on the rail. */
    function pickToggle(key) {
        const order = host.pictures().map((p) => p.key);
        picked = picked.includes(key) ? picked.filter((k) => k !== key) : order.filter((k) => k === key || picked.includes(k));
        touched = true;
        pickDraw();
    }

    function pickDraw() {
        const pictures = host.pictures();
        startedDraw();
        const chosenPictures = pictures.filter((p) => picked.includes(p.key));
        pickChosen.textContent = '';
        if (!chosenPictures.length) pickChosen.append(el('p', 'blend-chosen-none', t('Hake links zwei bis vier Bilder an.')));
        for (const p of chosenPictures) {
            const line = el('div', 'blend-chosen-line');
            line.append(el('span', 'blend-chosen-name', p.name));
            if (p.w > 0 && p.h > 0) line.append(el('span', 'blend-chosen-size', `${p.w} × ${p.h}`));
            pickChosen.append(line);
        }
        // Another size is another cut: aligned on opening (step 6), no longer a reason to refuse.
        const problem = chosenPictures.length > LAYERS_MAX ? t('Mehr als vier Bilder lassen sich nicht übereinanderlegen.') : '';
        pickProblem.textContent = problem;
        pickProblem.hidden = problem === '';
        pickProblem.classList.remove('is-quiet');
        pickOpen.disabled = loading || chosenPictures.length < 2 || problem !== '';
        pickOpen.classList.toggle('subtle', pickOpen.disabled);
        ticksDraw();
    }

    /* THE TICK BOXES ON THE RAIL („eine Hakenfläche als Overlay
       auf das Bild, irgendwie rechts unten im Eck oder links unten im
       Eck"). The tiles are the gallery's — a bare <img> for a voucher
       photo, which can hold nothing — and the gallery's box is walked by
       others (the page names every child's group, the sorting takes them
       for tiles), so the boxes are no children of it: they lie in the box
       AROUND the gallery (`host.tilesRoot()`), each over its tile's lower
       left corner, and are laid anew whenever the rail draws itself,
       changes its size, shows another group, or a picture arrives and
       pushes the others down. Only while one chooses. */
    const ticks = [];
    function ticksDraw() {
        const root = host.tilesRoot?.();
        if (!root) return;
        for (const b of ticks.splice(0)) b.remove();
        const on = active && mode === 'pick';
        root.classList.toggle('is-blend-picking', on);
        if (!on) return;
        const names = new Map(host.pictures().map((p) => [p.key, p.name]));
        const from = root.getBoundingClientRect();
        for (const { key, el: tile } of host.tiles()) {
            if (!names.has(key) || tile.offsetParent === null) continue;
            const at = tile.getBoundingClientRect();
            const ticked = picked.includes(key);
            const box = tickBox(ticked ? 'on' : 'off', names.get(key), ticked ? t('Nicht dazunehmen') : t('Dazunehmen'), () => pickToggle(key));
            box.classList.add('blend-tick');
            // Both boxes scroll together in the rail, so their distance holds while it scrolls.
            box.style.left = at.left - from.left + 'px';
            box.style.top = at.bottom - from.top + 'px';
            root.append(box);
            ticks.push(box);
        }
    }
    {
        const watch = host.tilesWatch?.();
        if (watch) {
            const again = () => { if (active && mode === 'pick') ticksDraw(); };
            new MutationObserver(again).observe(watch, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
            new ResizeObserver(again).observe(watch);
            watch.addEventListener('load', again, true);
        }
    }

    function pickSay(text, isProblem = false) {
        pickProblem.textContent = text;
        pickProblem.hidden = text === '';
        pickProblem.classList.toggle('is-quiet', !isProblem);
    }

    async function pickLoad() {
        const pictures = host.pictures().filter((p) => picked.includes(p.key));
        if (loading || pictures.length < 2) return;
        loading = true;
        pickOpen.disabled = true;
        const bitmaps = [];
        let failure = '';
        try {
            const loaded = [];
            for (const [i, p] of pictures.entries()) {
                pickSay(t('Bild %d von %d wird geladen …', i + 1, pictures.length));
                const answer = await fetch(p.original, { credentials: 'same-origin' });
                if (!answer.ok) throw new Error(t('„%s" ließ sich nicht laden.', p.name));
                const blob = await answer.blob();
                const bitmap = await createImageBitmap(blob).catch(() => null);
                if (!bitmap) throw new Error(t('„%s" kann dieser Browser nicht anzeigen.', p.name));
                bitmaps.push(bitmap);
                loaded.push({ p, blob, bitmap });
            }
            /* WHAT WAS BEGUN WITH EXACTLY THESE PICTURES comes back: their
               order, their eyes, their maps, their masks. Asked first, since
               it names the FRAME the masks were painted in; a set without
               one was begun before the aligning, with pictures of one size,
               and the first ticked is as good as any. A mask that cannot be
               fetched stops the opening — painted on without it, the next
               save would write an empty one over an hour's work. */
            await storedFetch();
            const keys = loaded.map((x) => x.p.key).sort().join(' ');
            const begun = stored.sets.find((x) => x.layers.map((l) => l.key).sort().join(' ') === keys);
            const frameKey = begun?.layers.find((l) => l.frame)?.key;
            sessionStart(loaded, Math.max(0, loaded.findIndex((x) => x.p.key === frameKey)));
            if (begun) {
                pickSay(t('Der Arbeitsstand wird geladen …'));
                try {
                    await storedApply(begun);
                } catch (error) {
                    sessionEnd(false);
                    throw error;
                }
            }
            /* ANOTHER CUT IS ALIGNED BY ITSELF — once, on the first opening;
               a map kept with the set is his and stays. */
            const sess = session;
            const unaligned = sess.layers.filter((l) => l.alignPending);
            const failed = [];
            for (const [i, layer] of unaligned.entries()) {
                pickSay(unaligned.length > 1 ? t('Bild %d von %d wird ausgerichtet …', i + 1, unaligned.length) : t('Die Bilder werden ausgerichtet …'));
                if (!(await alignAuto(sess, layer))) failed.push(layer.name);
            }
            pickSay('');
            show('work');
            if (failed.length) mergeSay(t('„%s" ließ sich nicht von selbst ausrichten. Justiere es mit Punkten nach.', failed.join('", „')), true);
        } catch (error) {
            failure = error.message || t('Die Bilder ließen sich nicht laden.');
        } finally {
            for (const b of bitmaps) b.close?.();
            loading = false;
            if (mode === 'pick') {
                // The choosing anew (the button wakes again), and over its own word why it did not work.
                pickDraw();
                if (failure) pickSay(failure, true);
            }
        }
    }

    // --- the layers on the card -------------------------------------------
    function glStart() {
        if (gl) return true;
        gl = canvas.getContext('webgl', { antialias: false, alpha: false, preserveDrawingBuffer: true });
        if (!gl) return false;
        const compile = (type, source) => {
            const shader = gl.createShader(type);
            gl.shaderSource(shader, source);
            gl.compileShader(shader);
            return shader;
        };
        program = gl.createProgram();
        gl.attachShader(program, compile(gl.VERTEX_SHADER, VERTEX));
        gl.attachShader(program, compile(gl.FRAGMENT_SHADER, FRAGMENT));
        gl.linkProgram(program);
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            gl = null;
            return false;
        }
        gl.useProgram(program);
        const quad = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, quad);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
        const at = gl.getAttribLocation(program, 'p');
        gl.enableVertexAttribArray(at);
        gl.vertexAttribPointer(at, 2, gl.FLOAT, false, 0, 0);
        uniforms = {};
        for (const name of ['uOn', 'uGain', 'uView', 'uVeil', 'uCheck', 'uA', 'uB', 'uC']) uniforms[name] = gl.getUniformLocation(program, name);
        ['uL0', 'uL1', 'uL2', 'uL3', 'uM1', 'uM2', 'uM3'].forEach((name, unit) => gl.uniform1i(gl.getUniformLocation(program, name), unit));
        gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
        blank = texture();
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.LUMINANCE, 1, 1, 0, gl.LUMINANCE, gl.UNSIGNED_BYTE, new Uint8Array([0]));
        return true;
    }

    function texture() {
        const tex = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, tex);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        return tex;
    }

    function sessionEnd(keep = true) {
        if (proposal) proposeEnd(keep);
        adjust = null;
        adjustPins();
        // What is still unsaved goes out first — the save holds the set itself, not this variable.
        if (keep) saveNow();
        else if (session) session.changed = false;
        if (session && gl) for (const layer of session.layers) { gl.deleteTexture(layer.tex); gl.deleteTexture(layer.maskTex); }
        session = null;
        undo.length = 0;
        snap = strokeBuf = null;
        stroke = null;
    }

    /**
     * The set on the card. `frame` is the index of the picture whose
     * pixels the masks and the finished picture have (step 6): the canvas
     * and the masks take its size; every layer keeps its own picture at
     * its own size, and its map says where in the frame it lies.
     */
    function sessionStart(loaded, frame = 0) {
        sessionEnd();
        if (!glStart()) throw new Error(t('Dieser Browser kann die Ebenen nicht zeichnen.'));
        const NW = loaded[frame].bitmap.width, NH = loaded[frame].bitmap.height;
        const cap = Math.min(EDGE, gl.getParameter(gl.MAX_TEXTURE_SIZE));
        const scaleOf = (w, h) => Math.min(1, cap / Math.max(w, h));
        const scale = scaleOf(NW, NH);
        const W = Math.max(1, Math.round(NW * scale)), H = Math.max(1, Math.round(NH * scale));
        canvas.width = W;
        canvas.height = H;
        const small = document.createElement('canvas');
        const ctx = small.getContext('2d');
        const shorts = loaded.map((x) => String(x.p.n ?? ''));
        const plain = new Set(shorts).size === shorts.length && shorts.every((x) => /^[1-9]$/.test(x));
        const layers = loaded.map(({ p, blob, bitmap }, i) => {
            const iw = bitmap.width, ih = bitmap.height, k = scaleOf(iw, ih);
            small.width = Math.max(1, Math.round(iw * k));
            small.height = Math.max(1, Math.round(ih * k));
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(bitmap, 0, 0, small.width, small.height);
            const tex = texture();
            gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, small);
            const mask = new Uint8Array(W * H);
            const maskTex = texture();
            gl.texImage2D(gl.TEXTURE_2D, 0, gl.LUMINANCE, W, H, 0, gl.LUMINANCE, gl.UNSIGNED_BYTE, mask);
            const align = alignGuess(NW, NH, iw, ih);
            return { key: p.key, name: p.name, short: plain ? shorts[i] : String(i + 1), thumb: p.thumb, blob, shown: true, mask, share: 0, tex, maskTex,
                unsaved: false, masked: false, iw, ih, align, alignPending: i !== frame && !alignSame(align), exposure: 0,
                // What the finished picture takes over from its layers (mergeRun).
                title: p.title ?? null, scale: p.scale ?? null, takenBy: p.takenBy ?? null, file: p.file ?? p.name };
        });
        small.width = small.height = 0;
        session = { layers, W, H, NW, NH, frame: loaded[frame].p.key, point: host.point(), setId: null, changed: false, failed: '', kind: kindLast };
        snap = new Uint8Array(W * H);
        strokeBuf = new Uint8Array(W * H);
        view = -1;
        chosen = layers.length - 1;
        mergeNote.hidden = true;
    }

    /** How much of a layer is painted in — its mask's mean, 0 … 1; kept on the layer for its row. */
    function shareTake(layer) {
        const m = layer.mask;
        let sum = 0;
        for (let n = 0; n < m.length; n++) sum += m[n];
        layer.share = sum / 255 / m.length;
    }
    function veilSet(on) {
        veil = on;
        settings.set('blendVeil', veil);
        panelSync();
        render();
    }

    // --- keeping the work --------------------------------------------------
    /** What the server keeps for the find in the window: whether it keeps anything, and the sets begun. */
    async function storedFetch() {
        const point = host.point();
        try {
            const answer = await request(`layer_sets.php?point=${point}`);
            stored = { point, ready: answer.ready === true, sets: answer.sets ?? [] };
        } catch {
            stored = { point, ready: false, sets: [], unreachable: true };
        }
        savedSay();
    }

    /** A set as it was left, laid over the session just started: the order, the eyes, the masks. */
    async function storedApply(set) {
        const { W, H } = session;
        const byKey = new Map(session.layers.map((l) => [l.key, l]));
        session.layers = set.layers.map((x) => byKey.get(x.key));
        session.setId = set.id;
        // Its tab: kept on its first layer (step 9); a set begun before the tabs is a stacking.
        session.kind = set.layers[0]?.hdr ? 'hdr' : 'stack';
        kindKeep(session.kind);
        const c = document.createElement('canvas');
        c.width = W;
        c.height = H;
        const ctx = c.getContext('2d', { willReadFrequently: true });
        ctx.imageSmoothingQuality = 'high';
        for (const [i, x] of set.layers.entries()) {
            const layer = session.layers[i];
            layer.shown = x.shown;
            layer.masked = x.mask;
            layer.exposure = Math.min(100, Math.max(-100, Number(x.exposure) || 0));
            // The map kept with the set is the one he left — adjusted perhaps; not done again.
            if (Array.isArray(x.align) && (x.align.length === 6 || x.align.length === 8)) {
                layer.align = x.align.map(Number);
                layer.alignPending = false;
            }
            if (!x.mask) continue;
            const answer = await fetch(`${host.baseUrl}pages/layer_mask.php?set=${set.id}&key=${encodeURIComponent(layer.key)}`,
                { credentials: 'same-origin', cache: 'no-store' });
            // Named in the row and not in the cloud — taken away by hand: the layer starts empty.
            if (answer.status === 404) {
                layer.masked = false;
                continue;
            }
            const bitmap = answer.ok ? await createImageBitmap(await answer.blob()).catch(() => null) : null;
            if (!bitmap) throw new Error(t('Der Arbeitsstand ließ sich nicht laden. Versuch es gleich noch einmal.'));
            // A mask kept smaller than it is painted (maskPng halves a heavy one) comes back smoothly enlarged.
            ctx.clearRect(0, 0, W, H);
            ctx.drawImage(bitmap, 0, 0, W, H);
            bitmap.close?.();
            const d = ctx.getImageData(0, 0, W, H).data;
            for (let n = 0; n < layer.mask.length; n++) layer.mask[n] = d[n * 4];
            maskUpload(layer, 0, 0, W, H);
            shareTake(layer);
        }
        chosen = session.layers.length - 1;
    }

    /** One layer's mask as a PNG — grey, opaque; halved until it fits what the host takes. */
    async function maskPng(sess, layer) {
        const { W, H } = sess;
        let out = document.createElement('canvas');
        out.width = W;
        out.height = H;
        const ctx = out.getContext('2d');
        const image = ctx.createImageData(W, H);
        const d = image.data, m = layer.mask;
        for (let n = 0, i = 0; n < m.length; n++, i += 4) {
            d[i] = d[i + 1] = d[i + 2] = m[n];
            d[i + 3] = 255;
        }
        ctx.putImageData(image, 0, 0);
        for (let tries = 0; tries < 4; tries++) {
            const blob = await new Promise((resolve) => out.toBlob(resolve, 'image/png'));
            if (blob && blob.size <= MASK_BYTES) return blob;
            const half = document.createElement('canvas');
            half.width = Math.max(1, out.width >> 1);
            half.height = Math.max(1, out.height >> 1);
            const hc = half.getContext('2d');
            hc.imageSmoothingQuality = 'high';
            hc.drawImage(out, 0, 0, half.width, half.height);
            out = half;
        }
        throw new Error(t('Die Maske ließ sich nicht speichern.'));
    }

    /** Something worth keeping changed — a layer's mask, or the order and the eyes. */
    function changed(layer = null) {
        if (!session) return;
        if (layer) layer.unsaved = true;
        session.changed = true;
        savedSay();
        clearTimeout(saveTimer);
        if (stored.ready) saveTimer = setTimeout(saveNow, SAVE_AFTER);
    }

    /** Keep the work now. One save after the other: a second one waits for the first. */
    function saveNow() {
        clearTimeout(saveTimer);
        saveTimer = null;
        const sess = session;
        if (!sess || !sess.changed || !stored.ready) return saveChain;
        saveChain = saveChain.then(() => saveRun(sess)).catch(() => {});
        return saveChain;
    }

    async function saveRun(sess) {
        if (!sess.changed) return;
        sess.changed = false;
        saving++;
        savedSay();
        try {
            /* THE MASKS FIRST, each by itself, then the row that names them
               (layer_sets.php says why). A layer painted on while its mask
               is on the way is marked anew by that stroke and goes again. */
            for (const layer of sess.layers.filter((l) => l.unsaved)) {
                layer.unsaved = false;
                const has = layer.share > 0;
                if (has) {
                    const form = new FormData();
                    form.append('action', 'mask');
                    form.append('point', String(sess.point));
                    form.append('key', layer.key);
                    try {
                        form.append('image', await maskPng(sess, layer), 'maske.png');
                        await request('layer_sets.php', { method: 'POST', body: form });
                    } catch (error) {
                        layer.unsaved = true;
                        throw error;
                    }
                }
                layer.masked = has;
            }
            const answer = await request('layer_sets.php', { method: 'POST', body: JSON.stringify({
                point: sess.point,
                layers: sess.layers.map((l, i) => ({ key: l.key, shown: l.shown, mask: l.masked,
                    ...(i === 0 && sess.kind === 'hdr' ? { hdr: true } : {}),
                    ...(l.key === sess.frame ? { frame: true } : {}), ...(alignSame(l.align) ? {} : { align: l.align }),
                    ...(l.exposure ? { exposure: l.exposure } : {}) })),
            }) });
            sess.setId = answer.set?.id ?? sess.setId;
            sess.failed = '';
        } catch (error) {
            // Tried again with the next change, and when the window closes.
            sess.changed = true;
            sess.failed = error.message || t('Der Arbeitsstand ließ sich nicht speichern.');
        } finally {
            saving--;
            savedSay();
        }
    }

    /** The word at the panel's head on how the work is kept. */
    function savedSay() {
        const sess = session;
        let text = '', problem = false;
        if (sess && mode === 'work') {
            if (stored.unreachable) [text, problem] = [t('Der Arbeitsstand wird gerade nicht gespeichert — keine Verbindung.'), true];
            else if (!stored.ready) text = t('Der Arbeitsstand wird noch nicht gespeichert — dafür fehlt die Migration 0191.');
            else if (saving > 0) text = t('wird gespeichert …');
            else if (sess.failed) [text, problem] = [sess.failed, true];
            else if (sess.changed) text = t('noch nicht gespeichert');
            else if (sess.setId !== null) text = t('gespeichert');
        }
        savedNote.textContent = text;
        savedNote.classList.toggle('is-problem', problem);
    }

    // --- the one picture ---------------------------------------------------
    let merging = false;

    /**
     * The layers as one picture, at the originals' full size. The ground
     * whole, then each layer that is switched on and painted, by its mask.
     *
     * IN STRIPS, AND ONE ORIGINAL AT A TIME: a camera picture decoded is a
     * hundred megabytes, the finished one another hundred; three originals
     * open at once beside them would be more than a tab is given. So the
     * ground is drawn and let go, and each layer is opened alone and laid
     * in MERGE_STRIP rows at a time.
     *
     * THE MASK IS ENLARGED WITH ROOM AROUND THE STRIP: it is painted at
     * most EDGE pixels wide and the picture is wider, so each strip's mask
     * is drawn larger out of it — a few rows more than the strip, above
     * and below, so the smoothing at the strip's edge finds its
     * neighbours. Cut exactly, every strip would end in a faint line.
     */
    async function mergeCompute(sess, say, raw = false) {
        const { NW, NH, W, H, layers } = sess;
        /* RAW: the numbers as the files hold them, unconverted (mergeProfile) —
           the profile goes into the finished picture instead. */
        const decode = (blob) => createImageBitmap(blob, raw ? { colorSpaceConversion: 'none' } : {});
        /* ONLY THE COMMON AREA (step 10): the picture is that rectangle of
           the frame, CX, CY its corner there — every drawing below moves by
           it, and the strips run through its rows only. */
        const { x: CX, y: CY, w: CW, h: CH } = cropOf(sess) ?? { x: 0, y: 0, w: NW, h: NH };
        const out = document.createElement('canvas');
        out.width = CW;
        out.height = CH;
        const octx = out.getContext('2d', { willReadFrequently: true });
        /* EACH PICTURE THROUGH ITS MAP (step 6): drawn into the frame's
           pixels by the inverse of its map, smoothly. Where the ground does
           not reach the frame stays black; where a layer does not reach,
           its strip is empty and the picture under it stays. */
        const ground = await decode(layers[0].blob);
        // The part of a tilted layer a strip needs, unpacked here (warpInto).
        const scratch = document.createElement('canvas');
        octx.fillStyle = '#000';
        octx.fillRect(0, 0, CW, CH);
        octx.imageSmoothingQuality = 'high';
        if (alignTilted(layers[0].align)) {
            // A tilted ground (the frame is another picture): strip by strip, over the black where it does not reach.
            const lay = document.createElement('canvas');
            lay.width = CW;
            lay.height = MERGE_STRIP;
            const lctx = lay.getContext('2d');
            for (let y = 0; y < CH; y += MERGE_STRIP) {
                const h = Math.min(MERGE_STRIP, CH - y);
                lctx.clearRect(0, 0, CW, MERGE_STRIP);
                lctx.putImageData(warpInto(ground, layers[0].align, NW, NH, CX, CY + y, CW, h, scratch), 0, 0);
                octx.drawImage(lay, 0, 0, CW, h, 0, y, CW, h);
            }
            lay.width = lay.height = 0;
        } else {
            const [g11, g12, g21, g22, gdx, gdy] = alignCanvas(layers[0].align, ground.width, ground.height, NW, NH);
            octx.setTransform(g11, g12, g21, g22, gdx - CX, gdy - CY);
            octx.drawImage(ground, 0, 0);
            octx.setTransform(1, 0, 0, 1, 0, 0);
        }
        ground.close?.();
        // The ground's own exposure (step 8), strip by strip — never the whole picture twice.
        // Rounded with dither wherever an exposure changes a value (exposureCurve).
        const rnd = ditherSource();
        const groundCurve = exposureCurve(exposureIn(sess, layers[0]));
        if (groundCurve) {
            for (let y = 0; y < CH; y += MERGE_STRIP) {
                const h = Math.min(MERGE_STRIP, CH - y);
                const part = octx.getImageData(0, y, CW, h), o = part.data;
                for (let i = 0; i < o.length; i += 4) {
                    o[i] = exposureRound(groundCurve[o[i]], rnd);
                    o[i + 1] = exposureRound(groundCurve[o[i + 1]], rnd);
                    o[i + 2] = exposureRound(groundCurve[o[i + 2]], rnd);
                }
                octx.putImageData(part, 0, y);
            }
        }
        const PAD = 4;
        const strip = document.createElement('canvas');
        strip.width = CW;
        strip.height = MERGE_STRIP;
        const sctx = strip.getContext('2d', { willReadFrequently: true });
        const mstrip = document.createElement('canvas');
        mstrip.width = CW;
        mstrip.height = MERGE_STRIP + 2 * PAD;
        const mctx = mstrip.getContext('2d', { willReadFrequently: true });
        mctx.imageSmoothingQuality = 'high';
        const small = document.createElement('canvas');
        small.width = W;
        small.height = H;
        const smallCtx = small.getContext('2d');
        const upper = layers.slice(1).filter((l) => l.shown && l.share > 0);
        for (const [k, layer] of upper.entries()) {
            const image = smallCtx.createImageData(W, H);
            const d = image.data, m = layer.mask;
            for (let n = 0, i = 0; n < m.length; n++, i += 4) {
                d[i] = d[i + 1] = d[i + 2] = m[n];
                d[i + 3] = 255;
            }
            smallCtx.putImageData(image, 0, 0);
            const bitmap = await decode(layer.blob);
            const curve = exposureCurve(exposureIn(sess, layer));
            const straight = alignSame(layer.align) && bitmap.width === NW && bitmap.height === NH;
            const tilted = alignTilted(layer.align);
            const toFrame = tilted ? null : alignCanvas(layer.align, bitmap.width, bitmap.height, NW, NH);
            sctx.imageSmoothingQuality = 'high';
            try {
                for (let y = 0; y < CH; y += MERGE_STRIP) {
                    const h = Math.min(MERGE_STRIP, CH - y);
                    const Y = CY + y;   // the strip's first row in the frame
                    mctx.clearRect(0, 0, CW, h + 2 * PAD);
                    mctx.drawImage(small, CX * W / NW, (Y - PAD) * H / NH, CW * W / NW, (h + 2 * PAD) * H / NH, 0, 0, CW, h + 2 * PAD);
                    const mask = mctx.getImageData(0, PAD, CW, h).data;
                    let any = false;
                    for (let i = 0; i < mask.length && !any; i += 4) any = mask[i] > 0;
                    if (any) {
                        let from;
                        if (tilted) from = warpInto(bitmap, layer.align, NW, NH, CX, Y, CW, h, scratch).data;
                        else {
                            sctx.clearRect(0, 0, CW, h);
                            if (straight) sctx.drawImage(bitmap, CX, Y, CW, h, 0, 0, CW, h);
                            else {
                                const [m11, m12, m21, m22, dx, dy] = toFrame;
                                sctx.setTransform(m11, m12, m21, m22, dx - CX, dy - Y);
                                sctx.drawImage(bitmap, 0, 0);
                                sctx.setTransform(1, 0, 0, 1, 0, 0);
                            }
                            from = sctx.getImageData(0, 0, CW, h).data;
                        }
                        const into = octx.getImageData(0, y, CW, h), o = into.data;
                        for (let i = 0; i < o.length; i += 4) {
                            // What the layer does not reach (no picture there) lets the one under it stay.
                            const a = from[i + 3] === 255 ? mask[i] : Math.round(mask[i] * from[i + 3] / 255);
                            if (a === 0) continue;
                            if (curve) {
                                /* Through the layer's own exposure (step 8), the
                                   blend computed unrounded and only then rounded,
                                   with dither — a soft mask edge over a soft
                                   background must not step either. */
                                const f = a / 255;
                                for (let c = 0; c < 3; c++) o[i + c] = exposureRound(o[i + c] + (curve[from[i + c]] - o[i + c]) * f, rnd);
                                continue;
                            }
                            const r = from[i], g = from[i + 1], b = from[i + 2];
                            if (a === 255) {
                                o[i] = r; o[i + 1] = g; o[i + 2] = b;
                            } else {
                                const f = a / 255;
                                o[i] += (r - o[i]) * f;
                                o[i + 1] += (g - o[i + 1]) * f;
                                o[i + 2] += (b - o[i + 2]) * f;
                            }
                        }
                        octx.putImageData(into, 0, y);
                    }
                    // The page breathes: the word on how far it is gets drawn, the tab stays alive.
                    if ((y / MERGE_STRIP) % 4 === 0) {
                        say(t('Wird berechnet … %d %%', Math.round((k + (y + h) / CH) / upper.length * 100)));
                        await new Promise((resolve) => setTimeout(resolve, 0));
                    }
                }
            } finally {
                bitmap.close?.();
            }
        }
        scratch.width = scratch.height = 0;
        return out;
    }

    /**
     * THE COLOUR PROFILE THE MERGE KEEPS (14.11.2026: „die weichen
     * Hintergründe bekommen jetzt so hässliche Artefakte … harte Grenzen").
     * The browser converts a picture with a profile into sRGB while
     * decoding, in eight bits; for Adobe RGB that stretches the dark tones,
     * and a smooth dark ramp went from steps of 2 to steps of 6 (measured
     * in Chrome, 14.11.2026). Helicon keeps the camera's profile, and a
     * camera set to Adobe RGB is common.
     *
     * So where EVERY picture the merge uses carries the SAME profile, it
     * reads them unconverted and writes that profile into the finished
     * file — the numbers stay the files' own, nothing is converted at all.
     * Where none carries one there is nothing to convert (null, as before);
     * where they differ, the browser's conversion is the only way to lay
     * them over each other, and that stays too.
     * @returns {Promise<Uint8Array|null>}
     */
    async function mergeProfile(sess) {
        const used = [sess.layers[0], ...sess.layers.slice(1).filter((l) => l.shown && l.share > 0)];
        let first;
        for (const [i, layer] of used.entries()) {
            // The header is at the front; a megabyte holds even a profile split in parts.
            const head = new Uint8Array(await layer.blob.slice(0, 1 << 20).arrayBuffer());
            const icc = jpegProfile(head);
            if (i === 0) first = icc;
            else if ((first === null) !== (icc === null)
                || (first && (first.length !== icc.length || first.some((b, k) => b !== icc[k])))) return null;
        }
        return first ?? null;
    }

    /**
     * „ZU EINEM BILD VERRECHNEN": compute, lay it into the cloud as a
     * recording of the find, and only then ask about the single pictures.
     *
     * THE ORDER IS THE PROMISE. Nothing is deleted before the new picture
     * lies in the cloud and the server knows it; and nothing is deleted
     * without the answer to a question that names what goes. „Behalten"
     * leaves everything — the single pictures, the set, the masks — so the
     * picture can be made again, better.
     */
    async function mergeRun() {
        const sess = session;
        if (!sess || merging) return;
        merging = true;
        stage.classList.add('is-busy');
        const say = mergeSay;
        panelDraw();
        try {
            await saveNow();
            say(t('Wird berechnet …'));
            const ground = sess.layers[0];
            const profile = await mergeProfile(sess);
            const canvas = await mergeCompute(sess, say, profile !== null);
            say(t('Das Bild wird gespeichert …'));
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', MERGE_QUALITY));
            // Its size — the common area's where it was cut (step 10).
            const size = { w: canvas.width, h: canvas.height };
            canvas.width = canvas.height = 0;
            if (!blob) throw new Error(t('Das Bild ist für diesen Browser zu groß.'));
            // What the camera wrote — the ground's: all layers are the same shot.
            const camera = await readCamera(ground.blob).catch(() => ({}));
            const author = ground.takenBy ?? sess.layers.find((l) => l.takenBy)?.takenBy ?? null;
            const bytes = stampJpeg(new Uint8Array(await blob.arrayBuffer()), {
                from: size,
                to: size,
                originalName: ground.file,
                when: new Date(),
                layers: sess.layers.length,
                ...camera,
                ...(author ? { artist: author, copyright: author } : {}),
                ...(profile ? { icc: profile } : {}),
            });
            const title = ground.title ?? sess.layers.find((l) => l.title)?.title ?? null;
            const file = new File([bytes], `${title || t('Bild')} ${t('Ebenen')}.jpg`, { type: 'image/jpeg' });
            /* THE SAME SUBJECT NAME, AND THE SCALE OF WHICHEVER HAS ONE („das
               bekommt das gleiche Label … den Maßstab kannst du übernehmen,
               falls eines der drei Bilder schon einen hat. Sie zeigen ja alle
               genau das Gleiche im genau gleichen Ausschnitt"). */
            const scale = sess.layers.find((l) => l.scale)?.scale ?? null;
            const before = new Set(host.pictures().map((p) => p.key));
            const up = await uploadForPoint(sess.point, [file],
                (text, progress) => say(progress === null || progress === undefined ? text
                    : t('Wird hochgeladen … %d %%', Math.round(progress * 100))),
                [{ title, shrink: false, ...(scale ? { scale } : {}), ...(author ? { takenBy: author } : {}) }]);
            const fresh = (up.files ?? []).map((f) => `cloud:${f.id}`).find((key) => !before.has(key)) ?? null;
            say(t('Das neue Bild liegt beim Fund.'));
            await host.refresh?.(null);

            const names = sess.layers.map((l) => `„${l.name}"`).join(', ');
            const yes = await confirm({
                title: t('Einzelbilder löschen?'),
                text: t('Das neue Bild liegt jetzt beim Fund. Sollen die %d Einzelbilder gelöscht werden — %s? Das lässt sich hier nicht rückgängig machen. pCloud, Google Drive und OneDrive legen gelöschte Dateien in ihren Papierkorb.',
                    sess.layers.length, names),
                button: t('Einzelbilder löschen'),
                cancel: t('Behalten'),
                danger: true,
            });
            if (!yes) return;

            say(t('Die Einzelbilder werden gelöscht …'));
            const failed = [];
            for (const layer of sess.layers) {
                const [kind, id] = layer.key.split(':');
                try {
                    if (kind === 'cloud') {
                        const body = new FormData();
                        body.append('file', id);
                        await request(`cloud.php?id=${sess.point}&action=remove`, { method: 'POST', body });
                    } else {
                        await api.remove('photos', Number(id));
                    }
                } catch {
                    failed.push(layer.name);
                }
            }
            // The set has nothing left to lie over each other; its masks went with their pictures.
            if (sess.setId !== null) {
                await request('layer_sets.php', { method: 'POST', body: JSON.stringify({ action: 'delete', id: sess.setId }) }).catch(() => {});
            }
            if (session === sess) sessionEnd(false);
            picked = [];
            touched = true;
            show('pick');
            await storedFetch();
            pickDraw();
            if (failed.length) pickSay(t('Nicht gelöscht: %s. Lösche sie von Hand.', failed.join(', ')), true);
            await host.refresh?.(fresh);
        } catch (error) {
            say(error.message || t('Das Bild ließ sich nicht verrechnen.'), true);
        } finally {
            merging = false;
            stage.classList.remove('is-busy');
            if (session && mode === 'work') panelDraw();
        }
    }

    /* LEAVING THE PAGE WITH WORK NOT YET KEPT: the browser asks first, as
       while the AI computes (photowindow.js) — the save is a moment behind
       the last stroke, and a mask on its way would be cut off. */
    window.addEventListener('beforeunload', (e) => {
        if (merging || (stored.ready && (saving > 0 || session?.changed))) {
            saveNow();
            e.preventDefault();
            e.returnValue = '';
        }
    });

    /**
     * Draw the layers. `below` (an index) draws only what lies under that
     * layer, as the result shows it — no veil, no single layer — for the
     * proposal from brightness to read back; `small` ([w, h]) draws it
     * that small into the canvas's corner, so only that much comes back
     * from the card (bright).
     */
    function render(below = null, small = null) {
        if (!session || !gl) return;
        const { layers, W, H } = session;
        const under = below !== null;
        gl.viewport(0, 0, small?.[0] ?? W, small?.[1] ?? H);
        for (let i = 0; i < LAYERS_MAX; i++) {
            gl.activeTexture(gl.TEXTURE0 + i);
            gl.bindTexture(gl.TEXTURE_2D, layers[i]?.tex ?? blank);
        }
        for (let i = 1; i < LAYERS_MAX; i++) {
            gl.activeTexture(gl.TEXTURE0 + 3 + i);
            gl.bindTexture(gl.TEXTURE_2D, layers[i]?.maskTex ?? blank);
        }
        gl.uniform4f(uniforms.uOn, ...[0, 1, 2, 3].map((i) => (layers[i]?.shown && (!under || i < below) ? 1 : 0)));
        gl.uniform4f(uniforms.uGain, ...[0, 1, 2, 3].map((i) => exposureGain(exposureIn(session, layers[i]))));
        // „Nachjustieren" shows one picture alone: the frame, or the layer being adjusted.
        const alone = !under && adjust ? layers.indexOf(adjust.side === 'frame' ? adjust.frame : adjust.layer) : -1;
        gl.uniform1f(uniforms.uView, under ? -1 : alone >= 0 ? alone : view);
        /* THE VEIL FOLLOWS ITS SWITCH, ALSO WHILE A PROPOSAL IS OPEN
           (14.11.2026: „Auch der Shortcut M funktioniert nicht mehr"). An open
           proposal used to force the veil on here — two renderings that look
           alike would hide what is proposed —, which left M and the eye
           without any effect for as long as the proposal stood. Opening a
           proposal now switches the veil on itself (proposeStart), so it is
           shown all the same, and M hides it and brings it back as anywhere. */
        gl.uniform1f(uniforms.uVeil, !under && !adjust && veil && chosen > 0 ? chosen : -1);
        gl.uniform1f(uniforms.uCheck, Math.max(8, W / 128));
        const maps = [0, 1, 2, 3].map((i) => layers[i]?.align ?? ALIGN_SAME);
        gl.uniform3fv(uniforms.uA, new Float32Array(maps.flatMap((m) => m.slice(0, 3))));
        gl.uniform3fv(uniforms.uB, new Float32Array(maps.flatMap((m) => m.slice(3, 6))));
        gl.uniform3fv(uniforms.uC, new Float32Array(maps.flatMap((m) => [m[6] ?? 0, m[7] ?? 0, 1])));
        // The common area changed (step 10): the stage fits itself to it anew — the brightness proposal's reading leaves it be.
        if (!under) {
            const box = shownCrop();
            const key = box ? `${box.x} ${box.y} ${box.w} ${box.h}` : '';
            if (key !== shownCropKey) {
                shownCropKey = key;
                fit();
            }
        }
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }
    function soon() {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; render(); });
    }

    /** A rectangle of a layer's mask, handed to the card. */
    function maskUpload(layer, x, y, w, h) {
        const { W } = session;
        gl.activeTexture(gl.TEXTURE0 + 7);
        gl.bindTexture(gl.TEXTURE_2D, layer.maskTex);
        let rows;
        if (x === 0 && w === W) rows = layer.mask.subarray(y * W, (y + h) * W);
        else {
            rows = new Uint8Array(w * h);
            for (let r = 0; r < h; r++) rows.set(layer.mask.subarray((y + r) * W + x, (y + r) * W + x + w), r * w);
        }
        gl.texSubImage2D(gl.TEXTURE_2D, 0, x, y, w, h, gl.LUMINANCE, gl.UNSIGNED_BYTE, rows);
    }

    // --- the picture on the stage -----------------------------------------
    /* WHAT THE STAGE SHOWS (step 10): the common area in the mask's
       pixels — or null, the whole frame: switched off, nothing to cut, or
       while places are named („Nachjustieren"), which wants every corner. */
    let shownCropKey = '';
    function shownCrop() {
        const box = adjust ? null : cropOf(session);
        if (!box) return null;
        const kx = session.W / session.NW, ky = session.H / session.NH;
        return { x: box.x * kx, y: box.y * ky, w: box.w * kx, h: box.h * ky };
    }
    function place() {
        canvas.style.transform = `translate(${tx}px, ${ty}px) scale(${s})`;
        // Cut, not dimmed: the canvas keeps the frame, the rest is clipped away.
        const c = session ? shownCrop() : null;
        canvas.style.clipPath = c ? `inset(${c.y}px ${session.W - c.x - c.w}px ${session.H - c.y - c.h}px ${c.x}px)` : '';
        adjustPins();
    }
    function fit() {
        if (!session) return;
        const box = stage.getBoundingClientRect();
        if (!box.width || !box.height) return;
        // Fitted to what is shown — the common area where it is cut.
        const c = shownCrop() ?? { x: 0, y: 0, w: session.W, h: session.H };
        s = Math.min(box.width / c.w, box.height / c.h);
        tx = (box.width - c.w * s) / 2 - c.x * s;
        ty = (box.height - c.h * s) / 2 - c.y * s;
        place();
    }
    function zoomAt(clientX, clientY, factor) {
        const box = stage.getBoundingClientRect();
        const floor = Math.min(box.width / session.W, box.height / session.H) * .5;
        const next = Math.min(16, Math.max(floor, s * factor));
        const px = clientX - box.left, py = clientY - box.top;
        tx = px - (px - tx) * next / s;
        ty = py - (py - ty) * next / s;
        s = next;
        place();
    }
    /** A pointer's place in the mask's pixels. */
    function maskPoint(e) {
        const box = stage.getBoundingClientRect();
        return [(e.clientX - box.left - tx) / s, (e.clientY - box.top - ty) / s];
    }

    // --- painting ---------------------------------------------------------
    const paintable = () => session && chosen > 0 && chosen < session.layers.length;

    /** The mask's value under the stroke's cover: laid over what the stroke found, or taken off it. */
    function compose(mask, n, erase, k) {
        const a = strokeBuf[n] * k;
        mask[n] = erase ? Math.round(snap[n] * (1 - a)) : Math.round(snap[n] + (255 - snap[n]) * a);
    }

    function dirty(x0, y0, x1, y1) {
        const d = stroke.box;
        d[0] = Math.min(d[0], x0); d[1] = Math.min(d[1], y0); d[2] = Math.max(d[2], x1); d[3] = Math.max(d[3], y1);
    }

    /** One stretch of the stroke laid into the mask, softly. */
    function stamp(ax, ay, bx, by) {
        const { W, H } = session;
        const layer = session.layers[chosen];
        const r = stroke.r, lut = stroke.lut;
        const x0 = Math.max(0, Math.floor(Math.min(ax, bx) - r)), x1 = Math.min(W, Math.ceil(Math.max(ax, bx) + r));
        const y0 = Math.max(0, Math.floor(Math.min(ay, by) - r)), y1 = Math.min(H, Math.ceil(Math.max(ay, by) + r));
        if (x1 <= x0 || y1 <= y0) return;
        const k = stroke.k;
        /* The distance by hand and squared first: a brush a third of the
           picture wide asks eight million pixels per stretch, and most of
           them only whether they are within reach at all. */
        const vx = bx - ax, vy = by - ay, len = vx * vx + vy * vy, rr = r * r;
        for (let y = y0; y < y1; y++) {
            const py = y + .5 - ay;
            for (let x = x0; x < x1; x++) {
                const px = x + .5 - ax;
                const u = len > 0 ? Math.max(0, Math.min(1, (px * vx + py * vy) / len)) : 0;
                const ex = px - u * vx, ey = py - u * vy, dd = ex * ex + ey * ey;
                if (dd >= rr) continue;
                const cover = lut[(dd / rr * 1024) | 0];
                const n = y * W + x;
                if (cover <= strokeBuf[n]) continue;
                strokeBuf[n] = cover;
                compose(layer.mask, n, stroke.erase, k);
            }
        }
        dirty(x0, y0, x1, y1);
        maskUpload(layer, x0, y0, x1 - x0, y1 - y0);
    }

    /** The inside of the stroke's closed way, covered whole (even-odd, row by row). */
    function fillInside() {
        const { W, H } = session;
        const layer = session.layers[chosen];
        const pts = stroke.path;
        let minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
        for (const [x, y] of pts) { minx = Math.min(minx, x); maxx = Math.max(maxx, x); miny = Math.min(miny, y); maxy = Math.max(maxy, y); }
        const x0 = Math.max(0, Math.floor(minx)), x1 = Math.min(W, Math.ceil(maxx) + 1);
        const y0 = Math.max(0, Math.floor(miny)), y1 = Math.min(H, Math.ceil(maxy) + 1);
        if (x1 <= x0 || y1 <= y0) return;
        const k = stroke.k;
        const xs = [];
        for (let y = y0; y < y1; y++) {
            const py = y + .5;
            xs.length = 0;
            for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
                const ay = pts[i][1], by = pts[j][1];
                if ((ay > py) === (by > py)) continue;
                xs.push(pts[i][0] + (py - ay) / (by - ay) * (pts[j][0] - pts[i][0]));
            }
            xs.sort((a, b) => a - b);
            for (let i = 0; i + 1 < xs.length; i += 2) {
                const from = Math.max(x0, Math.ceil(xs[i] - .5)), to = Math.min(x1 - 1, Math.floor(xs[i + 1] - .5));
                for (let x = from; x <= to; x++) {
                    const n = y * W + x;
                    if (strokeBuf[n] === 255) continue;
                    strokeBuf[n] = 255;
                    compose(layer.mask, n, stroke.erase, k);
                }
            }
        }
        dirty(x0, y0, x1, y1);
        maskUpload(layer, x0, y0, x1 - x0, y1 - y0);
    }

    /** Remember what a change replaced — the rectangle of the mask as it was. */
    function undoPush(layer, x0, y0, x1, y1) {
        const { W } = session;
        const w = x1 - x0, h = y1 - y0;
        if (w <= 0 || h <= 0) return;
        const before = new Uint8Array(w * h);
        for (let r = 0; r < h; r++) before.set(snap.subarray((y0 + r) * W + x0, (y0 + r) * W + x0 + w), r * w);
        undo.push({ layer, x: x0, y: y0, w, h, before });
        shareTake(layer);
        changed(layer);
        let bytes = undo.reduce((n, u) => n + u.before.length, 0);
        while (bytes > UNDO_BYTES && undo.length > 1) bytes -= undo.shift().before.length;
        panelSync();
    }

    function undoStep() {
        const u = undo.pop();
        if (!u || !session || !session.layers.includes(u.layer)) return panelSync();
        const { W } = session;
        for (let r = 0; r < u.h; r++) u.layer.mask.set(u.before.subarray(r * u.w, (r + 1) * u.w), (u.y + r) * W + u.x);
        maskUpload(u.layer, u.x, u.y, u.w, u.h);
        shareTake(u.layer);
        changed(u.layer);
        render();
        panelSync();
    }

    function strokeBegin(e) {
        const { W, H } = session;
        const p = maskPoint(e);
        snap.set(session.layers[chosen].mask);
        /* The brush's edge, worked out once per stroke: how much it covers
           at each distance from its middle (by the squared distance, in
           1024 steps) — full within the soft edge's inner line, none at
           the radius, smooth between. */
        const r = radiusPx(), inner = Math.min(r * (1 - brush().feather / 100), r - .75);
        const lut = new Uint8Array(1025);
        for (let i = 0; i <= 1024; i++) {
            const d = Math.sqrt(i / 1024) * r;
            const q = d <= inner ? 0 : Math.min(1, (d - inner) / (r - inner));
            lut[i] = Math.round((1 - q * q * (3 - 2 * q)) * 255);
        }
        stroke = { id: e.pointerId, path: [p], r, lut, erase: toolNow() === 'erase', k: brush().strength / 100 / 255, box: [W, H, 0, 0] };
        stamp(p[0], p[1], p[0], p[1]);
        soon();
    }
    function strokeMove(e) {
        for (const c of e.getCoalescedEvents?.().length ? e.getCoalescedEvents() : [e]) {
            const p = maskPoint(c), last = stroke.path[stroke.path.length - 1];
            /* A stretch is laid exactly, however long — so a wide brush may
               take longer ones: its eight million pixels per stretch are
               then asked a few times a sweep, not at every twitch. */
            if (Math.hypot(p[0] - last[0], p[1] - last[1]) < Math.max(.5, stroke.r * (stroke.r > 300 ? .25 : .08))) continue;
            stamp(last[0], last[1], p[0], p[1]);
            stroke.path.push(p);
        }
        soon();
    }
    function strokeEnd() {
        const layer = session.layers[chosen];
        /* COME ROUND, IT FILLS ITS INSIDE — the pen along the sharp edge
           and back to where it began, as he does it; the rule is the
           retouching's (develop.js, spotLoopCloses). */
        if (spotLoopCloses(stroke.path, stroke.r)) {
            const first = stroke.path[0], last = stroke.path[stroke.path.length - 1];
            stamp(last[0], last[1], first[0], first[1]);
            fillInside();
        }
        const [x0, y0, x1, y1] = stroke.box;
        // The cover is the next stroke's too: wiped where this one lay.
        for (let y = y0; y < y1; y++) strokeBuf.fill(0, y * session.W + x0, y * session.W + x1);
        undoPush(layer, x0, y0, x1, y1);
        stroke = null;
        render();
    }

    /**
     * „FÜLLEN": the area one clicks into, up to the painted line around it
     * — or up to the picture's edge, which closes a line that ran out of
     * the picture. Clicked on what is still empty it fills; clicked on
     * what is filled it empties.
     *
     * THE SOFT EDGE OF THE LINE IS TAKEN ALONG. A line has a slope on both
     * sides; filling only what is below the half would leave a pale seam
     * along its inner side. So from the filled area the fill climbs the
     * slope — onwards only where the mask rises (or, emptying, falls) —
     * and stops at the line's ridge, never going down its far side.
     */
    function bucket(e) {
        const { W, H } = session;
        const layer = session.layers[chosen], mask = layer.mask;
        const [fx, fy] = maskPoint(e);
        const sx = Math.floor(fx), sy = Math.floor(fy);
        if (sx < 0 || sy < 0 || sx >= W || sy >= H) return;
        snap.set(mask);
        const filling = snap[sy * W + sx] < 128;
        const inside = (v) => (filling ? v < 128 : v >= 128);
        const target = filling ? 255 : 0;
        const seen = new Uint8Array(W * H);
        let x0 = W, y0 = H, x1 = 0, y1 = 0;
        const rim = [];
        const seeds = [sy * W + sx];
        while (seeds.length) {
            const n = seeds.pop();
            if (seen[n] || !inside(snap[n])) continue;
            const y = (n / W) | 0, row = y * W;
            let a = n - row, b = a;
            while (a > 0 && !seen[row + a - 1] && inside(snap[row + a - 1])) a--;
            while (b < W - 1 && !seen[row + b + 1] && inside(snap[row + b + 1])) b++;
            for (let x = a; x <= b; x++) { seen[row + x] = 1; mask[row + x] = target; }
            x0 = Math.min(x0, a); x1 = Math.max(x1, b + 1); y0 = Math.min(y0, y); y1 = Math.max(y1, y + 1);
            if (a > 0) rim.push(row + a - 1);
            if (b < W - 1) rim.push(row + b + 1);
            for (const other of [y - 1, y + 1]) {
                if (other < 0 || other >= H) continue;
                const base = other * W;
                let run = false;
                for (let x = a; x <= b; x++) {
                    const k = base + x;
                    if (seen[k]) { run = false; continue; }
                    if (inside(snap[k])) {
                        if (!run) seeds.push(k);
                        run = true;
                    } else {
                        rim.push(k);
                        run = false;
                    }
                }
            }
        }
        // Up (or down) the slope of the line around it, as far as its ridge.
        const from = rim.map((n) => [n, filling ? 127 : 128]);
        while (from.length) {
            const [n, before] = from.pop();
            if (seen[n]) continue;
            const v = snap[n];
            if (inside(v) || (filling ? v < before : v > before)) continue;
            seen[n] = 1;
            if (v === target) continue;
            mask[n] = target;
            const y = (n / W) | 0, x = n - y * W;
            x0 = Math.min(x0, x); x1 = Math.max(x1, x + 1); y0 = Math.min(y0, y); y1 = Math.max(y1, y + 1);
            if (x > 0) from.push([n - 1, v]);
            if (x < W - 1) from.push([n + 1, v]);
            if (y > 0) from.push([n - W, v]);
            if (y < H - 1) from.push([n + W, v]);
        }
        if (x1 <= x0 || y1 <= y0) return;
        maskUpload(layer, x0, y0, x1 - x0, y1 - y0);
        undoPush(layer, x0, y0, x1, y1);
        render();
    }

    function maskClear() {
        if (!paintable()) return;
        const { W, H } = session;
        const layer = session.layers[chosen];
        snap.set(layer.mask);
        layer.mask.fill(0);
        maskUpload(layer, 0, 0, W, H);
        undoPush(layer, 0, 0, W, H);
        render();
    }

    // --- aligning (step 6) ---------------------------------------------------
    /**
     * A layer aligned on the frame by itself (assets/js/align.js says how):
     * corners and pairs at a thousand pixels, measured exactly at two
     * thousand. TWO GUESSES AT THE SCALE, since the pairs are found by
     * their neighbourhoods and a neighbourhood looks alike only at a like
     * scale: Helicon's other cut has the frame's pixels, a picture saved
     * smaller or larger as a whole has the frame's shape — whichever more
     * pairs agree with wins. False where neither found the picture.
     */
    async function alignAuto(sess, layer) {
        const frame = sess.layers.find((l) => l.key === sess.frame);
        if (!frame || frame === layer) return false;
        const { NW, NH } = sess;
        const [fb, lb] = await Promise.all([createImageBitmap(frame.blob), createImageBitmap(layer.blob)]);
        const c = document.createElement('canvas');
        const greyAt = (bitmap, k) => greyOfBitmap(bitmap, k, c);
        // The page breathes between the steps: the word on what happens gets drawn.
        const breathe = () => new Promise((resolve) => setTimeout(resolve, 0));
        try {
            const k1 = Math.min(1, ALIGN_COARSE / Math.max(NW, NH));
            const A1 = greyAt(fb, k1);
            const guesses = [k1, Math.min(1, ALIGN_COARSE / Math.max(lb.width, lb.height))];
            if (Math.abs(guesses[1] / guesses[0] - 1) < .02) guesses.pop();
            let best = null;
            for (const kb of guesses) {
                await breathe();
                const B1 = greyAt(lb, kb);
                const found = alignCoarse(A1, B1);
                if (found && (!best || found.inliers > best.found.inliers)) best = { found, kb, B1 };
            }
            if (!best) return false;
            await breathe();
            const k2 = Math.min(1, ALIGN_FINE / Math.max(NW, NH));
            const A2 = greyAt(fb, k2), B2 = greyAt(lb, best.kb * k2 / k1);
            // The coarse map carried to the finer pixels of both — as matrices, so a tilted one goes the same way.
            const ax = A2.w / A1.w, ay = A2.h / A1.h, bx = B2.w / best.B1.w, by = B2.h / best.B1.h;
            const m2 = matMap(matMul([[bx, 0, 0], [0, by, 0], [0, 0, 1]], matMul(mapMat(best.found.m), [[1 / ax, 0, 0], [0, 1 / ay, 0], [0, 0, 1]])));
            if (!m2) return false;
            await breathe();
            const fine = alignFine(A2, B2, m2, 6);
            const al = sharesOf(fine?.m ?? m2, A2, B2);
            if (!al) return false;
            if (session !== sess) return false;
            layer.align = al;
            layer.alignPending = false;
            layer.sharp = null;
            changed();
            return true;
        } catch {
            return false;
        } finally {
            fb.close?.();
            lb.close?.();
            c.width = c.height = 0;
        }
    }

    /* „NACHJUSTIEREN" — PLACES NAMED IN BOTH PICTURES
       (13.11.2026). It replaces aligning by hand, which laid the layer at
       half over the ground to be dragged, turned and scaled by two
       sliders: „eigentlich unnutzbar … wirklich schwer zu steuern". What
       he asked for instead: „Wenn man mit dem Ergebnis nicht zufrieden
       ist, kann man auf einen Button klicken und Punkte zuordnen. Man
       schaltet zum Beispiel mit Shift zwischen zwei Ansichten hin und her
       und sagt, dieser Punkt hier ist auf dem anderen Bild hier … um
       nochmal den Automatismus fein zu justieren."

       TWO VIEWS, each picture alone: the frame — what the layer is aligned
       on — and the layer as it lies now. A click names a place in the one
       shown; the view changes by itself to the other for the same place
       there, and back for the next pair. Shift changes it at any time, to
       compare.

       THE CLICK NEED NOT BE EXACT: the place in the layer is measured
       around the click to a fraction of a pixel (align.js, refinePoint) —
       where it can be; a click on a place without drawing stays a click,
       `exact` false, good to a few pixels. THE PAIRS ARE THE TRUTH
       (mapFromPairs: one moves, two turn and scale, three shear, four and
       more tilt; the measured ones alone once there are four of them),
       AND THE AUTOMATISM TAKES IT FROM THERE: the fine measuring runs
       around the map they give, and its map is taken where every measured
       pair lies within two pixels of it and every mere click within ten —
       the precision of hundreds of measured points instead of a few
       clicks. Where it disagrees with a pair, the pairs win: he named
       them because the measuring was wrong there. (The first rule asked
       a pixel and a half of EVERY pair; one click that could not be
       measured, seven pixels off, then kept the fine measuring out for
       good — found on the first test, 13.11.2026.)

       The whole frame shows meanwhile (no cut to the common area), and
       the pictures are measured at ALIGN_FINE pixels, as the aligning
       does. Only the map is kept; the pairs live as long as the tool is
       open. */
    let adjust = null;              // { layer, frame, base, pairs: [{ p, q, click, exact }], side, A, B, ready } — open
    /** A picture's grey values at the scale `k` — what the aligning measures on. */
    function greyOfBitmap(bitmap, k, c) {
        c.width = Math.max(16, Math.round(bitmap.width * k));
        c.height = Math.max(16, Math.round(bitmap.height * k));
        const ctx = c.getContext('2d', { willReadFrequently: true });
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(bitmap, 0, 0, c.width, c.height);
        return { grey: greyOf(ctx.getImageData(0, 0, c.width, c.height).data, c.width, c.height), w: c.width, h: c.height };
    }
    async function adjustStart() {
        if (!paintable() || merging || aligning || adjust) return;
        const sess = session, layer = sess.layers[chosen];
        const frame = sess.layers.find((l) => l.key === sess.frame);
        if (!frame || frame === layer) return;
        proposeEnd(true);
        const adj = adjust = { layer, frame, base: layer.align.slice(), pairs: [], side: 'frame', A: null, B: null, ready: false };
        panelDraw();
        render();
        // The two pictures as the measuring wants them — the layer at the frame's scale, as it lies now.
        let fb = null, lb = null;
        const c = document.createElement('canvas');
        try {
            [fb, lb] = await Promise.all([createImageBitmap(frame.blob), createImageBitmap(layer.blob)]);
            const kA = Math.min(1, ALIGN_FINE / Math.max(sess.NW, sess.NH));
            const al = layer.align;
            const lies = Math.sqrt(Math.abs((al[0] * al[4] - al[1] * al[3]) * (lb.width * lb.height) / (sess.NW * sess.NH))) || 1;
            adj.A = alignReady(greyOfBitmap(fb, kA, c));
            adj.B = alignReady(greyOfBitmap(lb, Math.min(1, kA / lies), c));
            adj.ready = true;
        } catch {
            if (adjust === adj) {
                adjustEnd(false);
                mergeSay(t('Die Bilder ließen sich nicht zum Nachjustieren vorbereiten.'), true);
            }
        } finally {
            fb?.close?.();
            lb?.close?.();
            c.width = c.height = 0;
        }
        if (adjust === adj) panelSync();
    }
    /** The pair still missing its other side — the last one, if any. */
    const adjustOpen = () => {
        const last = adjust?.pairs.at(-1);
        return last && (!last.p || !last.q) ? last : null;
    };
    function adjustSide(side) {
        if (!adjust || adjust.side === side) return;
        adjust.side = side;
        panelSync();
        render();
        adjustPins();
    }
    /** A click on the stage names a place in the picture shown. */
    function adjustClick(e) {
        if (!adjust?.ready) return;
        const { layer, A, B } = adjust;
        const [mx, my] = maskPoint(e);
        const f = [mx / session.W, my / session.H];
        if (!(f[0] >= 0 && f[0] <= 1 && f[1] >= 0 && f[1] <= 1)) return;
        let pair = adjustOpen();
        if (adjust.side === 'frame') {
            if (!pair) adjust.pairs.push(pair = { p: null, q: null, click: null, exact: false });
            pair.p = f;
        } else {
            // The place in the layer's own picture: through the map it lies by now.
            const q = mapPoint(layer.align, f[0], f[1]);
            if (!(q[0] >= 0 && q[0] <= 1 && q[1] >= 0 && q[1] <= 1)) return;
            if (!pair) adjust.pairs.push(pair = { p: null, q: null, click: null, exact: false });
            pair.q = pair.click = q;
            pair.exact = false;
        }
        if (pair.p && pair.q) {
            // Either side may have been named anew: measured again from the click.
            pair.q = pair.click;
            pair.exact = false;
            adjustFit();
            adjust.side = 'frame';
        } else {
            adjust.side = adjust.side === 'frame' ? 'layer' : 'frame';
        }
        panelSync();
        render();
        adjustPins();
    }
    /** The map the named pairs ask for, laid on the layer — and the fine measuring from there, where it agrees with them. */
    function adjustFit() {
        if (!adjust?.ready) return;
        const { layer, base, A, B } = adjust;
        const whole = adjust.pairs.filter((x) => x.p && x.q);
        // A pair's two places in the measured pictures' pixels (each on its own line: tests/module.mjs reads by the line).
        const inA = (x) => [x.p[0] * A.w - .5, x.p[1] * A.h - .5];
        const inB = (q) => [q[0] * B.w - .5, q[1] * B.h - .5];
        const basePx = pixelsOf(base, A, B);
        const fromPairs = () => {
            // The measured pairs alone once they can say a tilt by themselves; else every pair.
            const exact = whole.filter((x) => x.exact);
            const set = exact.length >= 4 ? exact : whole;
            return basePx ? mapFromPairs(basePx, set.map(inA), set.map((x) => inB(x.q))) : null;
        };
        // Each click measured around itself, through the map as it lies by then — twice: the second round reads the layer truer.
        let m = basePx ? (pixelsOf(layer.align, A, B) ?? basePx) : null;
        for (let round = 0; round < 2 && m; round++) {
            for (const x of whole) {
                if (x.exact && round === 0) continue;
                const found = refinePoint(A, B, m, inA(x), inB(x.click));
                if (found) {
                    x.q = [(found[0] + .5) / B.w, (found[1] + .5) / B.h];
                    x.exact = true;
                }
            }
            m = fromPairs();
        }
        if (m && whole.length > 0) {
            const fine = alignFine(A, B, m, 8);
            /* A MAP FROM THE MEASURING ONLY WHERE IT MEASURED SOMETHING: a
               pair named wrongly (thirty pixels off, on the first test) sent
               it looking in the wrong place, where a dozen chance likenesses
               agreed on a map of their own — and with the pair. So: a good
               share of the places looked for found, and found closely. */
            const sound = fine && fine.points >= Math.max(30, fine.corners * .15) && fine.rms <= 1;
            const agrees = sound && whole.every((x) => {
                const [fx, fy] = mapPoint(fine.m, ...inA(x)), [qx, qy] = inB(x.exact ? x.q : x.click);
                return Math.hypot(fx - qx, fy - qy) <= (x.exact ? 2 : 10);
            });
            if (agrees) m = fine.m;
        }
        const al = m && whole.length > 0 ? sharesOf(m, A, B) : null;
        layer.align = al ?? base.slice();
        layer.sharp = null;
        changed();
    }
    function adjustUndo() {
        if (!adjust || adjust.pairs.length === 0) return;
        adjust.pairs.pop();
        adjust.side = 'frame';
        adjustFit();
        panelSync();
        render();
        adjustPins();
    }
    function adjustEnd(keep = true) {
        if (!adjust) return;
        if (!keep) {
            adjust.layer.align = adjust.base;
            adjust.layer.sharp = null;
            changed();
        }
        adjust = null;
        adjustPins();
        panelSync();
        render();
    }
    /** The named places as numbered pins over the picture shown — the frame's in its view, the layer's in its own. */
    function adjustPins() {
        pins.textContent = '';
        pins.hidden = !adjust;
        if (!adjust || !session) return;
        const back = adjust.side === 'layer' ? mapInverse(adjust.layer.align) : null;
        adjust.pairs.forEach((pair, n) => {
            let at = null;
            if (adjust.side === 'frame') at = pair.p;
            else if (pair.q && back) at = mapPoint(back, pair.q[0], pair.q[1]);
            if (!at) return;
            const pin = el('span', 'blend-pin' + (pair.p && pair.q ? '' : ' is-open'), String(n + 1));
            pin.style.transform = `translate(${tx + at[0] * session.W * s}px, ${ty + at[1] * session.H * s}px)`;
            pins.append(pin);
        });
    }
    async function alignAgain() {
        if (!paintable() || merging || aligning) return;
        const sess = session, layer = sess.layers[chosen];
        adjustEnd(true);
        proposeEnd(true);
        aligning = true;
        mergeSay(t('Wird ausgerichtet …'));
        panelDraw();
        const done = await alignAuto(sess, layer);
        aligning = false;
        if (session !== sess) return;
        mergeSay(done ? '' : t('„%s" ließ sich nicht von selbst ausrichten. Justiere es mit Punkten nach.', layer.name), !done);
        panelSync();
        render();
    }
    let aligning = false;

    // --- the proposals: from sharpness, from brightness ---------------------
    let proposal = null;            // { kind, layer, before, sharp | bright, aw, ah, threshold, soft, invert } — open, not yet taken
    let proposing = null;           // the kind being measured
    let proposeQueued = false;

    /**
     * Measure and lay the proposal over the chosen layer's mask — open, to
     * be set by the two sliders. „sharp" measures the chosen layer itself:
     * where it is sharp it should show. „bright" measures what lies UNDER
     * it, as the result shows it: where that is nearly burnt out, the
     * darker rendering laid over it should show (step 7).
     *
     * IN THE FRAME'S PIXELS, both: the mask is the frame's, and a layer
     * cut otherwise is drawn into it through its map first (step 6). Where
     * it does not reach, nothing is measured — and its rim, a hard edge
     * between picture and nothing, is no detail either: the field is
     * cleared a little way inside it.
     */
    async function proposeStart(kind = 'sharp') {
        if (!paintable() || proposal || proposing || merging || adjust) return;
        const sess = session, layer = sess.layers[chosen];
        proposing = kind;
        panelDraw();
        try {
            const scale = Math.min(1, SHARP_EDGE / Math.max(sess.NW, sess.NH));
            const aw = Math.max(8, Math.round(sess.NW * scale)), ah = Math.max(8, Math.round(sess.NH * scale));
            const c = document.createElement('canvas');
            c.width = aw;
            c.height = ah;
            const ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.imageSmoothingQuality = 'high';
            if (kind === 'sharp') {
                const key = layer.align.join(' ');
                if (!layer.sharp || layer.sharp.key !== key) {
                    const bitmap = await createImageBitmap(layer.blob);
                    if (alignTilted(layer.align)) {
                        const scratch = document.createElement('canvas');
                        ctx.putImageData(warpInto(bitmap, layer.align, aw, ah, 0, 0, aw, ah, scratch), 0, 0);
                        scratch.width = scratch.height = 0;
                    } else {
                        ctx.setTransform(...alignCanvas(layer.align, bitmap.width, bitmap.height, aw, ah));
                        ctx.drawImage(bitmap, 0, 0);
                    }
                    bitmap.close?.();
                    const rgba = ctx.getImageData(0, 0, aw, ah).data;
                    const measured = sharpness(rgba, aw, ah);
                    if (!alignSame(layer.align) || layer.iw !== sess.NW || layer.ih !== sess.NH) {
                        // Cleared within reach of the rim: the measuring gathers over that much.
                        const cover = new Float32Array(aw * ah);
                        for (let n = 0; n < cover.length; n++) cover[n] = rgba[n * 4 + 3] === 255 ? 1 : 0;
                        const r = Math.max(3, Math.round(Math.max(aw, ah) * .006));
                        boxBlur(boxBlur(cover, aw, ah, r), aw, ah, r);
                        for (let n = 0; n < cover.length; n++) if (cover[n] < .999) measured.sharp[n] = 0;
                    }
                    layer.sharp = { ...measured, aw, ah, key };
                }
                if (session !== sess || sess.layers[chosen] !== layer) return;
                proposal = { kind, layer, before: layer.mask.slice(), sharp: layer.sharp.sharp, aw, ah,
                    threshold: Math.round(layer.sharp.auto * 100), soft: 25, invert: false };
            } else {
                /* What lies under the chosen layer, drawn by the card AT THE
                   SMALL SIZE and only that read back — drawn whole and made
                   small on the processor, a camera picture took ten seconds
                   (measured at 6000 × 4500, 11.11.2026). The card's rows
                   come bottom up. */
                const rgba = new Uint8Array(aw * ah * 4);
                render(chosen, [aw, ah]);
                gl.readPixels(0, 0, aw, ah, gl.RGBA, gl.UNSIGNED_BYTE, rgba);
                render();
                // The brightest of the three: a burnt-out sky is often burnt out in one colour first.
                const bright = new Float32Array(aw * ah);
                for (let y = 0; y < ah; y++) {
                    for (let x = 0, i = (ah - 1 - y) * aw * 4, n = y * aw; x < aw; x++, i += 4, n++) {
                        bright[n] = Math.max(rgba[i], rgba[i + 1], rgba[i + 2]) / 255;
                    }
                }
                proposal = { kind, layer, before: layer.mask.slice(), bright, aw, ah, threshold: 90, soft: 25, invert: false };
            }
            /* THE MASK SHOWS WHILE A PROPOSAL IS OPEN — AND THE EYE SAYS SO
               (14.11.2026: „Klicke ich bei den Masken auf
               irgendeinen Vorschlag, und komme dann in das
               Einstellungsmenü, muss mir auf jeden Fall die Maske angezeigt
               werden"). The stage already laid the mask over the picture
               for an open proposal (render, „always shown"), but the eye in
               the head stood at „aus" beside it, and with the proposal's
               thin seams that read as not shown. Now the showing is
               switched on for real: the eye lights, and it stays on after
               „Übernehmen" — one goes on working at the mask just proposed. */
            if (!veil) veilSet(true);
            proposeApply();
        } catch {
            mergeSay(kind === 'sharp' ? t('Die Schärfe ließ sich nicht messen.') : t('Die Helligkeit ließ sich nicht messen.'), true);
        } finally {
            proposing = null;
            if (session === sess && mode === 'work') panelDraw();
        }
    }

    /** The proposal as the sliders stand, laid over what was painted before — never taking paint away. */
    function proposeApply() {
        const { kind, layer, before, aw, ah, threshold, soft, invert } = proposal;
        const { W, H } = session;
        const small = kind === 'sharp' ? sharpMask(proposal.sharp, aw, ah, threshold / 100, soft / 100)
            : brightMask(proposal.bright, aw, ah, threshold / 100, soft / 100);
        /* „UMKEHREN" (11.11.2026: „Vielleicht will man ja auch mal
           die unscharfen Bereiche markiert haben"): the same areas, the other
           side of their line — the soft edge stays where it was, only which
           side is painted turns. Still laid over what was painted before. */
        if (invert) for (let n = 0; n < small.length; n++) small[n] = 255 - small[n];
        const from = document.createElement('canvas');
        from.width = aw;
        from.height = ah;
        const fctx = from.getContext('2d');
        const image = fctx.createImageData(aw, ah);
        for (let n = 0, i = 0; n < small.length; n++, i += 4) {
            image.data[i] = image.data[i + 1] = image.data[i + 2] = small[n];
            image.data[i + 3] = 255;
        }
        fctx.putImageData(image, 0, 0);
        const to = document.createElement('canvas');
        to.width = W;
        to.height = H;
        const tctx = to.getContext('2d', { willReadFrequently: true });
        tctx.imageSmoothingQuality = 'high';
        tctx.drawImage(from, 0, 0, W, H);
        const d = tctx.getImageData(0, 0, W, H).data;
        const mask = layer.mask;
        let added = false;
        for (let n = 0; n < mask.length; n++) {
            const v = d[n * 4];
            if (v > before[n]) added = true;
            mask[n] = Math.max(before[n], v);
        }
        proposeNone.hidden = added;
        maskUpload(layer, 0, 0, W, H);
        shareTake(layer);
        render();
        panelSync();
    }
    function proposeSoon() {
        if (proposeQueued) return;
        proposeQueued = true;
        setTimeout(() => {
            proposeQueued = false;
            if (proposal) proposeApply();
        }, 60);
    }

    /** Take the proposal (one step back undoes it whole) or leave it (the mask as it was). */
    function proposeEnd(take) {
        if (!proposal) return;
        const { layer, before } = proposal;
        proposal = null;
        if (session && session.layers.includes(layer)) {
            const { W, H } = session;
            if (take) {
                snap.set(before);
                undoPush(layer, 0, 0, W, H);
            } else {
                layer.mask.set(before);
                maskUpload(layer, 0, 0, W, H);
                shareTake(layer);
            }
            render();
        }
        panelSync();
    }

    // --- the pointer on the stage -----------------------------------------
    /** Whether the Option key is held — heard from the keys and from every pointer event, whichever comes. */
    function altSet(on) {
        if (on === altHeld) return;
        altHeld = on;
        if (partner[tool]) panelSync();
    }
    function ringPlace(e) {
        if (typeof e.altKey === 'boolean') altSet(e.altKey);
        const shown = paintable() && tool !== 'fill' && !pan && !spaceDown && !adjust;
        ring.hidden = !shown;
        if (!shown) return;
        const box = stage.getBoundingClientRect();
        // Where the stroke paints whole and where it ends — as strokeBegin works its edge out (`inner`), on the screen.
        const r = radiusPx() * s, full = Math.max(0, r * (1 - brush().feather / 100));
        const soft = r - full >= 2;
        const d = 2 * r + 6, mid = d / 2;
        ring.style.width = ring.style.height = d + 'px';
        ring.style.transform = `translate(${e.clientX - box.left - mid}px, ${e.clientY - box.top - mid}px)`;
        ringSvg.setAttribute('viewBox', `0 0 ${d} ${d}`);
        for (const { c, dashed } of ringCircles) {
            // A hard edge: only the solid ring, at the size; a fully soft one: only the dashed.
            const radius = dashed ? r : soft ? full : r;
            c.style.display = (dashed ? soft : (!soft || full >= 1)) ? '' : 'none';
            c.setAttribute('cx', String(mid));
            c.setAttribute('cy', String(mid));
            c.setAttribute('r', String(Math.max(.5, radius)));
        }
    }

    stage.addEventListener('wheel', (e) => {
        if (!session) return;
        e.preventDefault();
        /* SHIFT AND THE WHEEL SIZE THE BRUSH (11.11.2026). With
           Shift held a Mac hands the turn over as a sideways one, so
           whichever of the two came is taken — trackpad and mouse apart, the
           trackpad slower (shortcuts.js, wheelStep). */
        if (e.shiftKey || wheelWithCtrl(e)) {
            // Ctrl with the wheel sets the soft edge (since 13.11.2026; Cmd zooms his Mac's screen) — the real key, not a pinch.
            const step = wheelStep(e);
            if (tool !== 'fill' && step && !adjust) brushSet(e.shiftKey ? 'size' : 'feather', brushShown(e.shiftKey ? 'size' : 'feather') + step);
        } else {
            zoomAt(e.clientX, e.clientY, Math.exp(-e.deltaY * (e.ctrlKey ? .01 : .0015)));
        }
        ringPlace(e);
    }, { passive: false });
    stage.addEventListener('contextmenu', (e) => e.preventDefault());
    stage.addEventListener('pointerdown', (e) => {
        if (!session || stroke || pan) return;
        stage.focus({ preventScroll: true });
        if (e.button === 1 || e.button === 2 || (e.button === 0 && spaceDown)) {
            e.preventDefault();
            pan = { id: e.pointerId, x: e.clientX, y: e.clientY, tx, ty };
            stage.setPointerCapture(e.pointerId);
            stage.classList.add('is-panning');
            ring.hidden = true;
            return;
        }
        if (e.button === 0 && adjust) {
            // „Nachjustieren": the press names a place in the picture shown.
            e.preventDefault();
            adjustClick(e);
            return;
        }
        if (e.button !== 0 || !paintable() || merging || proposal) return;
        e.preventDefault();
        altSet(e.altKey);
        if (tool === 'fill') return bucket(e);
        stage.setPointerCapture(e.pointerId);
        strokeBegin(e);
    });
    stage.addEventListener('pointermove', (e) => {
        if (pan && e.pointerId === pan.id) {
            tx = pan.tx + e.clientX - pan.x;
            ty = pan.ty + e.clientY - pan.y;
            place();
            return;
        }
        if (stroke && e.pointerId === stroke.id) strokeMove(e);
        ringPlace(e);
    });
    const release = (e) => {
        if (pan && e.pointerId === pan.id) {
            pan = null;
            stage.classList.remove('is-panning');
            return;
        }
        if (stroke && e.pointerId === stroke.id) strokeEnd();
    };
    stage.addEventListener('pointerup', release);
    stage.addEventListener('pointercancel', release);
    stage.addEventListener('pointerleave', () => { if (!stroke) ring.hidden = true; });

    /* THE KEYS OF THE LAYERS (the system in shortcuts.js, 11.11.2026):
         B paint · E erase · G fill — Photoshop's letters, which he knows
         X paint and erase swapped · Option held: the eraser while held
         M the mask shown or not · 1–4 one layer alone, again: the result
         Y fitted · Space held: moving · Cmd+Z: a step back
       They count while the stage is in front, whether IT holds the focus
       or a slider just let go does — and they stop here: the photo window
       listens on the whole page for its own keys, and a key meant for the
       layers must not also reach the picture lying under them. Every key
       taken is claimed from the browser too (Firefox's „Suchen beim
       Tippen", a Mac's accent menu on a held letter — imagezoom.js). */
    function layerKeys(e) {
        // „Nachjustieren": Shift shows the other of the two pictures — one press, one change.
        if (e.key === 'Shift' && adjust) {
            if (!e.repeat) adjustSide(adjust.side === 'frame' ? 'layer' : 'frame');
            return true;
        }
        if (e.key === 'Alt') {
            altSet(true);
            e.preventDefault();
            return true;
        }
        if (e.key === ' ') {
            spaceDown = true;
            stage.classList.add('is-pan-ready');
            ring.hidden = true;
            e.preventDefault();
            return true;
        }
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            if (!stroke && !e.repeat) undoStep();
            return true;
        }
        if (e.metaKey || e.ctrlKey || e.altKey) return false;
        const k = e.key.toLowerCase();
        /* M, NOT H, SINCE 14.11.2026 — and here too: the key moved from H to
           M in the photo window and in the branch below, but this gate still
           let only h through, so M never reached the layers and switched
           nothing („Auch der Shortcut M funktioniert nicht mehr"). */
        if (!/^([1-9]|[begxmy])$/.test(k)) return false;
        e.preventDefault();
        if (e.repeat) return true;
        if (/^[1-9]$/.test(k)) {
            const i = session.layers.findIndex((l) => l.short === k);
            if (i >= 0) viewSet(view === i ? -1 : i);
        } else if (k === 'b') toolSet('paint');
        else if (k === 'e') toolSet('erase');
        else if (k === 'g') toolSet('fill');
        else if (k === 'x') toolSet(tool === 'erase' ? 'paint' : 'erase');
        else if (k === 'm') veilSet(!veil);
        else fit();
        return true;
    }
    const layersInFront = () => mode === 'work' && session && !stage.hidden && active;
    stage.addEventListener('keydown', (e) => {
        if (!layersInFront()) return;
        if (e.key !== 'Escape' && e.key !== 'Tab') e.stopPropagation();
        layerKeys(e);
    });
    window.addEventListener('keydown', (e) => {
        if (!layersInFront() || typingIn(e.target) || document.querySelector('dialog.dialog[open]')) return;
        if (layerKeys(e)) e.stopPropagation();
    }, true);
    const layerKeyUp = (e) => {
        if (e.key === 'Alt') altSet(false);
        if (e.key !== ' ') return;
        spaceDown = false;
        stage.classList.remove('is-pan-ready');
    };
    stage.addEventListener('keyup', (e) => {
        if (!layersInFront()) return;
        e.stopPropagation();
        layerKeyUp(e);
    });
    window.addEventListener('keyup', (e) => { if (layersInFront()) layerKeyUp(e); }, true);

    shortcutsProvide(() => (layersInFront() ? [{ title: t('Ebenen'), rows: [
        ['B', t('Malen')], ['E', t('Radieren')], ['G', t('Füllen')], ['X', t('Malen und Radieren wechseln')],
        [t('Option halten'), t('Malen und Radieren wechseln, solange gehalten')], [t('Shift + Mausrad'), t('Pinselgröße')],
        [t('Ctrl + Mausrad'), t('weiche Kante')], ['M', t('Maske zeigen')],
        ['1–4', t('eine Ebene allein, noch einmal: das Ergebnis')], ['Y', t('einpassen')],
        [t('Leertaste halten'), t('Bild verschieben')], [t('Cmd + Z'), t('Rückgängig')],
        ['Shift', t('beim Nachjustieren: das andere Bild zeigen')],
    ] }] : []));
    stage.addEventListener('blur', () => {
        altSet(false);
        spaceDown = false;
        stage.classList.remove('is-pan-ready');
    });
    /* The pointer too: the stage lies in the picture's frame, and what is
       done on it must not reach the frame's own listeners — the zoom, the
       measuring, the retouching of the picture underneath. */
    for (const type of ['pointerdown', 'pointermove', 'pointerup', 'pointercancel', 'click', 'dblclick', 'wheel', 'contextmenu']) {
        stage.addEventListener(type, (e) => e.stopPropagation());
    }
    // A press in the panel — a tool, an eye — hands the keys back to the stage; a slider keeps them while it is dragged.
    panel.addEventListener('click', (e) => {
        if (mode === 'work' && session && !e.target.closest('input')) stage.focus({ preventScroll: true });
    });

    // --- the panel --------------------------------------------------------
    /* „ERGEBNIS 1 2" IN THE STICKY BAR, UNDER „STACKING | HDR"
       (14.11.2026: „die zwei Buttons, Stacking und HDR, und die Ansicht als
       einen Block zusammenfassen und beides sticky oben lassen"): what one
       looks at is asked for while painting far down the column — a press
       on 1 or 2 no longer needs the way up. One block, one rule under it. */
    const viewRow = el('div', 'blend-views');
    viewRow.setAttribute('role', 'group');
    viewRow.setAttribute('aria-label', t('Ansicht'));
    viewRow.hidden = true;
    kindRow.append(viewRow);
    const layerList = el('div', 'blend-layers');
    /* THE MASK'S TOOLS ON A RAIL AT THE RIGHT EDGE (14.11.2026:
       „die drei Werkzeuge malen, radieren, füllen … im Bereich Masken außen
       eine Sidebar auf der rechten Seite, wo wir nur die Symbole anzeigen.
       Wo man zwischen den Werkzeugen umschalten kann und auf der linken
       Seite die Einstellung für die Werkzeuge"): the rails of „Bearbeiten"
       (photowindow.js, their classes and look), the sliders left of it.
       It was a row of three words over the sliders. */
    const toolRail = el('div', 'photo-develop-rail blend-tool-rail');
    toolRail.setAttribute('role', 'group');
    toolRail.setAttribute('aria-label', t('Werkzeug'));
    const sliders = el('div', 'blend-sliders');
    /* „MASKE ZEIGEN" IN THE HEAD OF „MASKE", at its right end
       (11.11.2026: „brauche ich ziemlich häufig … bei kleineren Monitoren
       muss ich dazu immer hinscrollen"): one line higher up, and no line of
       its own any more. M does the same from the keyboard. A BUTTON THAT
       IS ON OR OFF since 14.11.2026 („das Zeigen als Button machen, nicht
       als Anhang"), the eye, as the head of „Ebenen" carries its signs. */
    const veilRow = el('span', 'blend-head-signs');
    const veilButton = el('button', 'button subtle small square');
    veilButton.type = 'button';
    veilButton.setAttribute('aria-label', t('Maske zeigen'));
    veilButton.title = t('Maske zeigen (Taste M)');
    veilButton.addEventListener('click', () => veilSet(!veil));
    veilRow.append(veilButton);
    /* „MASKE LEEREN" AND THE PROPOSAL AS A HEAD OF THE TOOLS (the same
       day: „Maske leeren und Vorschlag … kommt ganz oben hin, dort wo
       momentan die Werkzeuge sind … die beiden Buttons bleiben einzeilig,
       brechen nicht um. Und werden zur Not zu Symbolen. Also gerade Maske
       leeren kann man ja standardmäßig in einen Mülleimer-Symbol
       umwandeln"; „noch so eine Art Kopfzeile … die Leiste mit den
       Werkzeugen auf der rechten Seite liegt darunter"). The bucket for
       emptying, at the row's right end over the rail; the proposal with
       the wand — Photoshop's sign for choosing by likeness, which is what
       it does — and its words, which give way to the sign alone where the
       column is narrow (.blend-mask-head). „RÜCKGÄNGIG" WENT: Cmd/Ctrl+Z
       does it (the stage's keys), and the shortcut overview says so. */
    const clearButton = el('button', 'button subtle small square blend-mask-clear');
    clearButton.type = 'button';
    clearButton.append(icon('delete'));
    clearButton.setAttribute('aria-label', t('Maske leeren'));
    clearButton.title = t('Maske leeren');
    const mergeNote = el('p', 'blend-merge-note');
    mergeNote.setAttribute('role', 'status');
    mergeNote.hidden = true;
    const mergeSay = (text, problem = false) => {
        mergeNote.textContent = text;
        mergeNote.hidden = text === '';
        mergeNote.classList.toggle('is-problem', problem);
    };
    /* THE PROPOSAL'S PLACE IN THE PANEL: a button among the mask's tools;
       while a proposal is open, the tools give way to its two sliders and
       „Übernehmen" / „Verwerfen" — one thing at a time, and no stroke
       painted into a mask that may still be taken back. */
    const maskTools = el('div', 'blend-mask-tools');
    const proposeWords = el('span', '', t('Vorschlag aus der Schärfe'));
    const proposeButton = el('button', 'button subtle small blend-propose-button');
    proposeButton.type = 'button';
    proposeButton.append(icon('wand'), proposeWords);
    const brightWords = el('span', '', t('Vorschlag aus der Helligkeit'));
    const brightButton = el('button', 'button subtle small blend-propose-button');
    brightButton.type = 'button';
    brightButton.append(icon('wand'), brightWords);
    const maskHead = el('div', 'blend-mask-head');
    maskHead.append(proposeButton, brightButton, clearButton);
    const maskBody = el('div', 'blend-mask-body');
    maskBody.append(sliders, toolRail);
    const proposeBox = el('div', 'blend-propose');
    proposeBox.hidden = true;
    const proposeSliders = el('div', 'blend-sliders');
    const proposeFooter = el('div', 'dialog-footer');
    const proposeDrop = el('button', 'button subtle small', t('Verwerfen'));
    proposeDrop.type = 'button';
    const proposeTake = el('button', 'button small', t('Übernehmen'));
    proposeTake.type = 'button';
    proposeFooter.append(proposeDrop, proposeTake);
    // The tick that turns the proposal round — drawn with the sliders (panelSync).
    const invertRow = el('label', 'blend-veil blend-invert');
    /* A PROPOSAL THAT MARKS NOTHING SAYS SO (14.11.2026): nothing in the
       picture under the layer reaches the threshold — a mask one cannot
       see, and M that changes nothing, read as a tool that does not work.
       A state, so it stands in the flow; it goes with the first pixel.
       IT STAYS (the same evening, after I had taken it out on a
       misheard „lass den Hinweis einfach da": „Ich hatte doch gesagt, der
       Hinweis soll drin bleiben … wird mir nichts markiert und kein
       Hinweis angezeigt, dass auch nichts gefunden wurde"). */
    const proposeNone = el('p', 'blend-propose-none', t('Bei dieser Schwelle ist nichts markiert. Schiebe die Schwelle nach links.'));
    proposeNone.hidden = true;
    proposeBox.append(proposeSliders, invertRow, proposeNone, proposeFooter);
    const proposeParts = [['threshold', () => t('Schwelle')], ['soft', () => t('Weiche Kante')]].map(([key, name]) => {
        const row = el('label', 'photo-develop-row');
        const rowHead = el('span', 'photo-develop-row-head');
        const out = el('span', 'photo-develop-value');
        rowHead.append(el('span', '', name()), out);
        const input = el('input');
        input.type = 'range';
        input.min = '0';
        input.max = '100';
        input.step = '1';
        input.addEventListener('input', () => {
            if (!proposal) return;
            proposal[key] = Number(input.value);
            out.textContent = input.value;
            proposeSoon();
        });
        row.append(rowHead, input);
        proposeSliders.append(row);
        return { key, input, out };
    });
    /* „AUSRICHTEN" (step 6): the chosen layer aligned again by itself —
       and „Nachjustieren" where that is not good enough: places named in
       both pictures (adjustStart). While it is open the mask's tools give
       way to it, as they do to a proposal — one thing at a time.
       AS SIGNS IN THE HEAD „EBENEN" since 14.11.2026 („da oben
       rechts als Symbol ausrichten und nachjustieren reinbringen. Also
       hinter Ebenen in die Zeile, in die Überschriftenzeile"), where the
       crop tick's long line had stood under the layers before; their
       words are in the tooltips. */
    const layerActions = el('span', 'blend-head-signs');
    const alignButton = el('button', 'button subtle small square');
    alignButton.type = 'button';
    alignButton.append(icon('align-layers'));
    alignButton.setAttribute('aria-label', t('Ausrichten'));
    const alignHint = t('Ausrichten: die gewählte Ebene von selbst auf die anderen ausrichten');
    const adjustButton = el('button', 'button subtle small square');
    adjustButton.type = 'button';
    adjustButton.append(icon('crosshair'));
    adjustButton.setAttribute('aria-label', t('Nachjustieren'));
    adjustButton.title = t('Nachjustieren: Stellen anklicken, die in beiden Bildern dieselben sind');
    layerActions.append(alignButton, adjustButton);
    /* „BELICHTUNG" — ONE SLIDER PER LAYER (step 8), the ground too: the
       ground is often the very rendering that wants lifting, and the
       chosen layer is never the ground. A double click sets a slider back
       to 0. IN THE LAYER'S OWN ROW since 14.11.2026 („die
       Belichtung würde ich bei den Ebenen direkt unterhalb der Kachel
       machen. Der einzelnen Ebene … direkt in der Ebene, in der
       Ebenenbeschriftung kann man die Belichtung einstellen"), under its
       name and across the rest of the row, so the slider stays long; it
       was a group of its own under the list before, the names repeated.
       ONE SLIDER PER LAYER FOR GOOD: the rows of the list are rebuilt at
       every panelDraw (the Option key, a tool, a share computed), and a
       slider rebuilt under the dragging hand lets go — so each layer's
       slider is made once and only moved into its new row, and while one
       is held the list is not rebuilt at all (exposureHeld). */
    const exposureRows = new WeakMap();   // layer -> { row, input, out }
    let exposureHeld = false;
    const exposureLetGo = () => { exposureHeld = false; };
    window.addEventListener('pointerup', exposureLetGo);
    window.addEventListener('pointercancel', exposureLetGo);
    const exposureSay = (v) => (v > 0 ? '+' : '') + v;
    /** The layer's exposure slider, as it stands now. */
    function exposureRow(layer) {
        let r = exposureRows.get(layer);
        if (!r) {
            const row = el('label', 'photo-develop-row blend-layer-exposure');
            const rowHead = el('span', 'photo-develop-row-head');
            const out = el('span', 'photo-develop-value');
            rowHead.append(el('span', '', t('Belichtung')), out);
            const input = el('input');
            input.type = 'range';
            input.min = '-100';
            input.max = '100';
            input.step = '1';
            const set = (v) => {
                layer.exposure = v;
                input.value = String(v);
                out.textContent = exposureSay(v);
                changed();
                soon();
            };
            input.addEventListener('input', () => set(Number(input.value)));
            input.addEventListener('pointerdown', () => { exposureHeld = true; });
            row.addEventListener('dblclick', (e) => {
                e.stopPropagation();
                set(0);
            });
            // Working the slider is not choosing the layer.
            row.addEventListener('click', (e) => e.stopPropagation());
            row.title = t('Doppelklick setzt auf 0 zurück');
            row.append(rowHead, input);
            r = { row, input, out };
            exposureRows.set(layer, r);
        }
        const v = layer.exposure ?? 0;
        r.input.value = String(v);
        r.out.textContent = exposureSay(v);
        r.input.disabled = !paintable() || merging;
        return r.row;
    }
    /* THE ADJUSTING'S PLACE IN THE PANEL: what to do next in one line —
       a state, not an explanation: it names the picture the next click
       belongs to —, the two pictures as two buttons (the one shown lit;
       Shift changes it), and the way out: the last pair taken back, all
       of it given up, or done. */
    const adjustBox = el('div', 'blend-propose blend-adjust');
    adjustBox.hidden = true;
    const adjustSay = el('p', 'blend-adjust-say');
    adjustSay.setAttribute('role', 'status');
    const adjustViews = el('div', 'blend-views');
    adjustViews.setAttribute('role', 'group');
    const adjustFooter = el('div', 'dialog-footer');
    // A sign, square: three words beside „Abbrechen" and „Fertig" broke the row in two.
    const adjustBack = el('button', 'button subtle small blend-move');
    adjustBack.type = 'button';
    adjustBack.title = t('Letzten Punkt löschen');
    adjustBack.setAttribute('aria-label', adjustBack.title);
    adjustBack.append(icon('undo'));
    const adjustCancel = el('button', 'button subtle small', t('Abbrechen'));
    adjustCancel.type = 'button';
    const adjustDone = el('button', 'button small', t('Fertig'));
    adjustDone.type = 'button';
    adjustFooter.append(adjustBack, adjustCancel, adjustDone);
    adjustBox.append(adjustSay, adjustViews, adjustFooter);
    function adjustDraw() {
        if (!adjust) return;
        const { frame, layer, side, pairs, ready } = adjust;
        const open = adjustOpen();
        const n = open ? pairs.length : pairs.length + 1;
        const shown = side === 'frame' ? frame : layer;
        adjustSay.textContent = !ready ? t('Die Bilder werden vorbereitet …')
            : open ? t('Punkt %d: Klicke dieselbe Stelle in „%s" an.', n, shown.name)
            : t('Punkt %d: Klicke eine Stelle in „%s" an.', n, shown.name);
        adjustViews.textContent = '';
        for (const [key, l] of [['frame', frame], ['layer', layer]]) {
            const b = el('button', 'button subtle small' + (side === key ? ' is-active' : ''), l.name);
            b.type = 'button';
            b.title = t('Dieses Bild zeigen (Shift wechselt)');
            b.setAttribute('aria-pressed', String(side === key));
            b.addEventListener('click', () => adjustSide(key));
            adjustViews.append(b);
        }
        adjustBack.disabled = pairs.length === 0;
    }
    alignButton.addEventListener('click', alignAgain);
    adjustButton.addEventListener('click', adjustStart);
    adjustBack.addEventListener('click', adjustUndo);
    adjustCancel.addEventListener('click', () => adjustEnd(false));
    adjustDone.addEventListener('click', () => adjustEnd(true));
    /* The footer that sticks: what the tool has to say (mergeNote — the
       merge's progress, a proposal that could not be measured) and the two
       concluding buttons beneath it. THE NOTE IN IT since 14.11.2026: it
       stood in the column above the footer, which was the end of „Maske"
       until „Ebenen" moved down the same day — after that a message about
       a proposal appeared a screen below the button that caused it, out
       of sight. */
    const sideFooter = el('div', 'blend-side-footer');
    const sideButtons = el('div', 'dialog-footer blend-side-buttons');
    /* „EBENEN AUFLÖSEN" INSTEAD OF „ANDERE BILDER" (13.11.2026:
       „den würde ich komplett entfernen … und stattdessen einen Button,
       mit dem man die aktuelle Konfiguration … auflösen kann, löschen").
       „Andere Bilder" went back to the choosing and left the set standing
       beside it — two ways into the same place and a set one never quite
       left. Now the footer offers the end of this set: the row, the masks
       in the cloud, the aligning and exposures go; the pictures stay as
       they are. The bucket, because the set ceases to exist (CLAUDE.md,
       three symbols). Afterwards the choosing, with the same pictures
       ticked ahead. */
    /* ONE LINE, SIGN AND SHORT WORD (14.11.2026: „beide
       Schaltflächen sollen immer eine Zeile maximal einnehmen, also wenn es
       zu eng wird, dann nur noch ein Symbol und die Texte kannst du
       eigentlich auch kürzen"). Under the head „Ebenen" the short words say
       enough; the whole sentence stays in the tooltip and in the question
       that follows the bucket. Where even the short words do not fit, the
       stylesheet keeps the signs alone (.blend-side-footer). */
    const dissolveButton = el('button', 'button subtle small danger');
    dissolveButton.type = 'button';
    dissolveButton.setAttribute('aria-label', t('Ebenen auflösen'));
    dissolveButton.title = t('Ebenen auflösen');
    dissolveButton.append(icon('delete'), el('span', '', t('Auflösen')));
    /* THE CONCLUDING BUTTON, last and at the right — quiet until something
       is painted: a picture „made of" the ground alone is the ground. */
    const mergeButton = el('button', 'button small');
    mergeButton.type = 'button';
    mergeButton.setAttribute('aria-label', t('Zu einem Bild verrechnen'));
    mergeButton.append(icon('stack'), el('span', '', t('Verrechnen')));
    sideButtons.append(dissolveButton, mergeButton);
    sideFooter.append(mergeNote, sideButtons);
    side.append(
        head(t('Maske'), veilRow), maskTools, proposeBox,
        head(t('Ebenen'), layerActions), layerList, adjustBox,
        sideFooter,
    );
    maskTools.append(maskHead, maskBody);

    /** A group's head with something at its right end. */
    function head(title, extra) {
        const h = el('h3', 'list-label blend-head');
        h.append(el('span', '', title), extra);
        return h;
    }

    const TOOLS = [['paint', () => t('Malen')], ['erase', () => t('Radieren')], ['fill', () => t('Füllen')]];
    function toolSet(next) {
        tool = next;
        panelSync();
    }
    function viewSet(next) {
        view = next;
        panelSync();
        render();
    }

    /** The brush in hand as { core, soft } (develop.js, brushCore). */
    const brushTool = () => brushCore(brushRadiusOf(brush().size), brush().feather, BLEND_REACH);
    /** What a slider shows: the core's size, the soft edge beyond it, the strength as it is. */
    const brushShown = (key) => (key === 'size' ? brushSizeOf(brushTool().core) : key === 'feather' ? brushTool().soft : Number(brush()[key]) || 0);
    /** A slider's value into the brush: 'size' sets the core, 'feather' the soft edge — neither moves the other. */
    function brushApply(key, value) {
        const v = Math.min(100, Math.max(0, Number(value) || 0));
        const b = brush();
        if (key !== 'size' && key !== 'feather') {
            b[key] = Math.round(v);
            return;
        }
        const now = brushTool();
        const o = brushOuter(key === 'size' ? brushRadiusOf(v) : now.core, key === 'feather' ? v : now.soft, BRUSH_MAX, BLEND_REACH);
        b.size = brushSizeOf(o.radius);
        b.feather = o.feather;
    }
    /** The brush's size or soft edge from elsewhere than its slider — the wheel — with the slider following. */
    function brushSet(key, next) {
        brushApply(key, next);
        brushesKeep();
        slidersSync();
    }

    const SLIDERS = [['size', () => t('Größe')], ['feather', () => t('Weiche Kante')], ['strength', () => t('Deckkraft')]];
    const sliderParts = SLIDERS.map(([key, name]) => {
        const row = el('label', 'photo-develop-row');
        const rowHead = el('span', 'photo-develop-row-head');
        const out = el('span', 'photo-develop-value');
        rowHead.append(el('span', '', name()), out);
        const input = el('input');
        input.type = 'range';
        input.min = '0';
        input.max = '100';
        input.step = '1';
        input.addEventListener('input', () => {
            brushApply(key, Number(input.value));
            out.textContent = input.value;
            brushesKeep();
        });
        row.append(rowHead, input);
        sliders.append(row);
        return { key, row, input, out };
    });
    /* The sliders show the settings of the tool in hand. „FÜLLEN" HAS
       NONE: it fills the area whole or empties it whole, so its sliders
       stand grey with the brush's values („da man da ja nichts machen
       kann"). */
    function slidersSync() {
        for (const { key, row, input, out } of sliderParts) {
            // Core and soft edge, not what the brush keeps (brushShown); whole numbers on the sliders.
            input.value = String(Math.round(brushShown(key)));
            out.textContent = input.value;
            input.disabled = tool === 'fill';
            row.classList.toggle('is-off', tool === 'fill');
        }
    }

    function panelDraw() {
        const { layers } = session;
        // The result, and each rendering alone — to compare them in place.
        viewRow.textContent = '';
        const viewButton = (label, value, hint) => {
            const b = el('button', 'button subtle small' + (view === value ? ' is-active' : ''), label);
            b.type = 'button';
            b.title = hint;
            b.setAttribute('aria-pressed', String(view === value));
            b.addEventListener('click', () => viewSet(value));
            viewRow.append(b);
        };
        viewButton(t('Ergebnis'), -1, t('Ergebnis (die Zahl der gezeigten Ebene noch einmal)'));
        layers.forEach((l, i) => viewButton(l.short, i, t('Nur „%s" (Taste %s)', l.name, l.short)));
        /* NO HEAD „ANSICHT" (14.11.2026: „Was mir nicht gefällt,
           ist, dass Ansicht eine eigene Überschrift hat. Das nimmt viel
           Platz weg"): the row stands right under „Stacking | HDR" — the
           buttons say what they are —, and how the work is kept, which sat
           at the head's right end, stands at the row's. */
        viewRow.append(savedNote);

        // The layers, the topmost first — as they lie. Not while a layer's exposure slider is held (exposureRow).
        if (!exposureHeld) layerList.textContent = '';
        for (let i = exposureHeld ? -1 : layers.length - 1; i >= 0; i--) {
            const l = layers[i];
            const row = el('div', 'blend-layer' + (i === chosen ? ' is-active' : '') + (i === 0 ? ' is-ground' : ''));
            const eye = el('button', 'button subtle small blend-eye');
            eye.type = 'button';
            eye.title = l.shown ? t('Ausblenden: %s', l.name) : t('Einblenden: %s', l.name);
            eye.setAttribute('aria-label', eye.title);
            eye.setAttribute('aria-pressed', String(l.shown));
            eye.append(icon(l.shown ? 'eye' : 'eye-off'));
            eye.addEventListener('click', (e) => {
                e.stopPropagation();
                l.shown = !l.shown;
                changed();
                panelDraw();
                render();
            });
            const thumb = el('img');
            thumb.src = l.thumb;
            thumb.alt = '';
            const words = el('span', 'blend-layer-words');
            words.append(el('span', 'blend-layer-name', l.name));
            /* EVERY ROW SAYS WHAT ITS LAYER GIVES: the ground everything, the
               others what was painted in — „noch nichts" at the start, which
               is why hiding the ground shows nothing of them yet. */
            const percent = Math.round(l.share * 100);
            words.append(el('span', 'blend-layer-tag', i === 0 ? t('Grundlage')
                : l.share === 0 ? t('noch nichts eingemalt')
                : percent < 1 ? t('unter %d %% eingemalt', 1) : t('%d %% eingemalt', percent)));
            row.append(eye, thumb, words);
            /* UP AND DOWN (11.11.2026: „wenn zwei Ebenen an der
               gleichen Stelle eine Maske haben … gewinnt ja dann die Ebene,
               die ganz oben liegt … da müssten wir dann noch ein Verschieben
               einführen, oberhalb der Grundebene"). The upper one wins —
               in the shader's order and in the merge's alike —, and the list
               shows the topmost first, so a row's arrows move it in the
               order one sees. The ground stays at the bottom; only where
               more than one layer lies over it is there anything to move. */
            /* „ALS GRUNDLAGE NEHMEN" IS A SIGN AT THE ROW'S END
               (13.11.2026: „bei den einzelnen Ebenen hinten als Symbol …
               eine Ebene, die flach unten liegt, und mittig ein Pfeil, der
               von oben auf die Ebene zeigt"). It was a button under the
               list that acted on the chosen layer; now each layer over the
               ground carries it, and the ground has none — it is the ground.
               Something other than moving: the layer goes under ALL at once. */
            const moves = i > 0 ? el('span', 'blend-moves') : null;
            if (i > 0 && layers.length > 2) {
                for (const [sign, by, words] of [['arrow-up', 1, t('Nach oben: %s', l.name)], ['arrow-down', -1, t('Nach unten: %s', l.name)]]) {
                    const b = el('button', 'button subtle small blend-move');
                    b.type = 'button';
                    b.title = words;
                    b.setAttribute('aria-label', words);
                    b.append(icon(sign));
                    b.disabled = !paintable() || i + by < 1 || i + by >= layers.length;
                    b.addEventListener('click', (e) => {
                        e.stopPropagation();
                        layerMove(i, by);
                    });
                    moves.append(b);
                }
            }
            if (moves) {
                const words = t('Als Grundlage nehmen: %s', l.name);
                const b = el('button', 'button subtle small blend-move');
                b.type = 'button';
                b.title = words;
                b.setAttribute('aria-label', words);
                b.append(icon('to-ground'));
                b.disabled = merging;
                b.addEventListener('click', (e) => {
                    e.stopPropagation();
                    groundTake(i);
                });
                moves.append(b);
                row.append(moves);
            }
            // Under HDR its exposure, under the name and across the rest of the row.
            if (session.kind === 'hdr') row.append(exposureRow(l));
            row.addEventListener('click', () => {
                if (i === 0) return;
                // Another layer: the proposal open on this one is taken, not lost — and so is the hand's work.
                proposeEnd(true);
                if (i !== chosen) adjustEnd(true);
                chosen = i;
                panelDraw();
                render();
            });
            layerList.append(row);
        }

        toolRail.textContent = '';
        for (const [key, name] of TOOLS) {
            // Lit is the tool in force — the eraser while the Option key holds it there.
            const b = el('button', 'photo-develop-rail-sign' + (toolNow() === key ? ' is-active' : ''));
            b.type = 'button';
            b.append(icon({ paint: 'brush', erase: 'eraser', fill: 'bucket' }[key]));
            b.title = { paint: t('Malen (Taste B)'), erase: t('Radieren (Taste E, Option halten, X wechselt)'), fill: t('Füllen (Taste G)') }[key];
            b.setAttribute('aria-label', name());
            b.setAttribute('aria-pressed', String(toolNow() === key));
            b.addEventListener('click', () => toolSet(key));
            toolRail.append(b);
        }
        veilButton.textContent = '';
        veilButton.append(icon(veil ? 'eye' : 'eye-off'));
        veilButton.classList.toggle('is-active', veil);
        veilButton.setAttribute('aria-pressed', String(veil));
        maskTools.hidden = proposal !== null || adjust !== null;
        proposeBox.hidden = proposal === null;
        adjustBox.hidden = adjust === null;
        layerActions.hidden = adjust !== null;
        adjustDraw();
        // The frame is what the others are aligned on — it has nothing to align or adjust itself.
        alignButton.disabled = adjustButton.disabled = !paintable() || merging || aligning || session.layers[chosen]?.key === session.frame;
        // While it works the sign breathes, and its tooltip says so.
        alignButton.classList.toggle('is-busy', !!aligning);
        alignButton.title = aligning ? t('Wird ausgerichtet …') : alignHint;
        proposeButton.disabled = brightButton.disabled = !paintable() || merging || proposing !== null || aligning;
        proposeWords.textContent = proposing === 'sharp' ? t('Die Schärfe wird gemessen …') : t('Vorschlag aus der Schärfe');
        brightWords.textContent = proposing === 'bright' ? t('Die Helligkeit wird gemessen …') : t('Vorschlag aus der Helligkeit');
        // The words may be hidden where the column is narrow: the tooltip keeps them.
        proposeButton.title = proposeWords.textContent;
        brightButton.title = proposing === 'bright' ? brightWords.textContent
            : t('Vorschlag aus der Helligkeit: markiert, wo das Bild unter dieser Ebene fast ausgebrannt ist');
        if (proposal) {
            for (const { key, input, out } of proposeParts) {
                input.value = String(proposal[key]);
                out.textContent = input.value;
            }
            invertRow.textContent = '';
            const turned = proposal.kind === 'sharp' ? t('Die unscharfen Bereiche markieren statt der scharfen')
                : t('Die dunklen Bereiche markieren statt der hellen');
            invertRow.append(
                tickBox(proposal.invert ? 'on' : 'off', t('Umkehren'), turned, () => {
                    if (!proposal) return;
                    proposal.invert = !proposal.invert;
                    proposeApply();
                }),
                el('span', '', t('Umkehren')),
            );
            invertRow.title = turned;
        }
        // Per tab (step 9): sharpness under Stacking; exposure and brightness under HDR.
        const hdr = session.kind === 'hdr';
        proposeButton.hidden = hdr;
        brightButton.hidden = !hdr;
        kindDraw();
        clearButton.disabled = !paintable() || merging;
        /* NOTHING PAINTED, NO BUCKET (14.11.2026: „Sollte
           überhaupt noch nichts an Maske eingezeichnet sein, brauchen wir
           den Mülleimer auch nicht"): the chosen layer's share — the same
           number its row says „noch nichts eingemalt" by. */
        clearButton.hidden = !(layers[chosen]?.share > 0);
        dissolveButton.disabled = merging;
        const mergeable = layers.slice(1).some((l) => l.shown && l.share > 0);
        mergeButton.disabled = merging || !mergeable || proposal !== null || adjust !== null;
        mergeButton.classList.toggle('subtle', mergeButton.disabled);
        mergeButton.title = mergeable ? t('Zu einem Bild verrechnen') : t('Erst wenn etwas eingemalt ist.');
        stage.classList.toggle('is-fill', tool === 'fill');
        stage.classList.toggle('is-adjust', adjust !== null);
        slidersSync();
    }
    function panelSync() {
        if (session && mode === 'work') panelDraw();
    }

    /** A layer over the ground trades places with its neighbour — its mask, eye and name go with it. */
    function layerMove(i, by) {
        const ls = session?.layers;
        const j = i + by;
        if (!ls || !paintable() || i < 1 || j < 1 || j >= ls.length) return;
        proposeEnd(true);
        adjustEnd(true);
        [ls[i], ls[j]] = [ls[j], ls[i]];
        // The chosen layer and the one shown alone stay the same layers.
        if (chosen === i) chosen = j; else if (chosen === j) chosen = i;
        if (view === i) view = j; else if (view === j) view = i;
        changed();
        panelDraw();
        render();
    }

    /* A LAYER GOES UNDER ALL: it becomes the ground, and what was the
       ground lies over it, with an empty mask. Nothing painted is lost — a
       ground's mask is merely not asked; moved up again, the layer shows
       where it was painted as before. The chosen layer and the one shown
       alone stay the same LAYERS; the new ground, if it was the chosen
       one, hands the choice to the layer right over it (the ground is
       never painted on). */
    function groundTake(i) {
        const ls = session?.layers;
        if (!ls || merging || i < 1 || i >= ls.length) return;
        proposeEnd(true);
        adjustEnd(true);
        const chosenLayer = ls[chosen], viewLayer = view >= 0 ? ls[view] : null;
        const [layer] = ls.splice(i, 1);
        ls.unshift(layer);
        chosen = Math.max(1, ls.indexOf(chosenLayer));
        view = viewLayer ? ls.indexOf(viewLayer) : -1;
        changed();
        panelDraw();
        render();
    }
    clearButton.addEventListener('click', maskClear);
    mergeButton.addEventListener('click', mergeRun);
    proposeButton.addEventListener('click', () => proposeStart('sharp'));
    brightButton.addEventListener('click', () => proposeStart('bright'));
    proposeTake.addEventListener('click', () => proposeEnd(true));
    proposeDrop.addEventListener('click', () => proposeEnd(false));

    // --- showing, and the tab coming and going ------------------------------
    function show(what) {
        // Leaving the work — to the choosing, to another set — takes an open proposal along as painted, and the hand's aligning.
        if (what !== 'work') {
            proposeEnd(true);
            adjustEnd(true);
        }
        mode = what;
        pick.hidden = what !== 'pick';
        work.hidden = what !== 'work';
        // The view buttons stand in the sticky bar on top, but only while a set is worked on.
        viewRow.hidden = what !== 'work';
        // The stage lies over the picture only while one works on a set in the open tab.
        stage.hidden = !(active && what === 'work' && session);
        savedSay();
        host.stackChanged?.();
        kindDraw();
        if (what === 'pick') pickDraw();
        else {
            ticksDraw();
            panelDraw();
            if (!stage.hidden) {
                fit();
                render();
                stage.focus({ preventScroll: true });
            }
        }
    }

    pickOpen.addEventListener('click', pickLoad);
    dissolveButton.addEventListener('click', async () => {
        const sess = session;
        if (!sess || merging) return;
        const yes = await confirm({
            title: t('Ebenen auflösen?'),
            text: t('Die Ebenen von %s werden aufgelöst. Was du eingemalt, ausgerichtet und eingestellt hast, geht verloren. Die Bilder selbst bleiben, wie sie sind.',
                sess.layers.map((l) => `„${l.name}"`).join(', ')),
            button: t('Ebenen auflösen'),
            danger: true,
        });
        if (!yes || session !== sess) return;
        // Nothing more is kept — and a save under way finishes first, or it would make the row anew after the delete.
        clearTimeout(saveTimer);
        saveTimer = null;
        sess.changed = false;
        await saveChain;
        try {
            if (sess.setId !== null) await request('layer_sets.php', { method: 'POST', body: JSON.stringify({ action: 'delete', id: sess.setId }) });
        } catch (error) {
            mergeSay(error.message || t('Die Ebenen ließen sich nicht auflösen.'), true);
            return;
        }
        picked = sess.layers.map((l) => l.key);
        touched = true;
        sessionEnd(false);
        show('pick');
        await storedFetch();
        if (active && mode === 'pick') pickDraw();
    });
    new ResizeObserver(() => { if (session && !stage.hidden) fit(); }).observe(stage);

    /**
     * THE TAB OPENS AND CLOSES (the photo window says which). Opening it:
     * a set one worked on and left stands as one left it, while the page
     * lives. Else the choosing, with the pictures of the shown picture's
     * subject name ticked ahead — his three „Hauptmotiv" — and, WHAT WAS
     * BEGUN WITH THE SHOWN PICTURE COMES BY ITSELF („oder es wird
     * vollautomatisch … übereinandergelegt"): there the question which
     * pictures is answered already. A new set waits for the button — three
     * originals are sixty megabytes of a mobile plan, and a tick set on
     * the way to the third must not fetch the first two.
     */
    function setActive(on) {
        if (on === active) return;
        active = on;
        if (!on) {
            proposeEnd(true);
            saveNow();
            stage.hidden = true;
            ticksDraw();
            host.stackChanged?.();
            return;
        }
        touched = false;
        const keys = new Set(host.pictures().map((p) => p.key));
        if (session && (session.point !== host.point() || !session.layers.every((l) => keys.has(l.key)))) sessionEnd();
        const shown = host.shown();
        if (session) show('work');
        else {
            const same = host.subject(shown);
            picked = same.length >= 2 && same.length <= LAYERS_MAX ? same : [];
            show('pick');
        }
        storedFetch().then(() => {
            if (!active || mode !== 'pick') return;
            const begun = touched || loading ? null : stored.sets.find((x) => x.layers.some((l) => l.key === shown));
            if (begun) {
                picked = host.pictures().map((p) => p.key).filter((k) => begun.layers.some((l) => l.key === k));
                pickDraw();
                pickLoad();
            } else {
                pickDraw();
            }
        });
    }

    /**
     * The viewer shows another picture (a click on the rail). Working on a
     * set, a layer's own tile shows that rendering alone — the same as its
     * number under „Ansicht"; any other picture is a step out of the set,
     * back to the choosing.
     */
    function pictureShown() {
        if (!active || mode !== 'work' || !session) return;
        const i = session.layers.findIndex((l) => l.key === host.shown());
        if (i >= 0) viewSet(i);
        else {
            picked = [];
            touched = true;
            show('pick');
        }
    }

    /**
     * Does a begun set hold the picture shown? For the dot on the tab
     * (photowindow.js, developTabsMark). What the server keeps is asked once
     * per find, quietly; `then` is called when that answer came, so the tab
     * can light up after it. A set being worked on counts at once.
     */
    let begunAsking = null;
    function begun(then) {
        if (!host.may()) return false;
        const shown = host.shown();
        if (session && session.point === host.point() && session.layers.some((l) => l.key === shown)) return true;
        if (stored.point !== host.point()) {
            if (!begunAsking) {
                begunAsking = storedFetch().finally(() => {
                    begunAsking = null;
                    if (stored.point === host.point()) then?.();
                });
            }
            return false;
        }
        return stored.sets.some((x) => x.layers.some((l) => l.key === shown));
    }

    return {
        panel,
        stage,
        may: () => host.may(),
        setActive,
        pictureShown,
        begun,
        /** The keys of the pictures in the set being worked on in the open tab, or null. */
        stack: () => (active && mode === 'work' && session ? session.layers.map((l) => l.key) : null),
        /** For the measurements in the browser — the state, read only. */
        test: {
            session: () => session,
            maskAt: (layer, x, y) => session.layers[layer].mask[y * session.W + x],
            render,
            // The finished picture as the merge computes it, without laying it into the cloud.
            merge: () => mergeCompute(session, () => {}),
        },
    };
}
