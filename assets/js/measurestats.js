/**
 * THE MEASURES' ARITHMETIC — what a find's measured structures add up to:
 * their lengths and breadths in µm, the Piximètre notation, the
 * distribution with its two ellipses, a spore cut out and stood upright.
 *
 * OUT OF photowindow.js ON 06.10.2026, as it stood, for the plates' third
 * step (plates.js): the photo window's statistics and a plate's blocks
 * „Werte", „Verteilung" and „in Reihe" must show the same numbers, and a
 * second copy of the arithmetic would have shown different ones the first
 * time either was touched. Pure functions only — the window keeps its
 * DOM, its state and its buttons.
 *
 * THE PHP TWIN is includes/measures.php (the find report the server
 * writes): measureUmPerPx, rectDims, frameValues, measureRows and
 * valueLines function for function, held to hand-computed numbers by
 * tests/measures.php. Whoever changes one side changes the other.
 */

import { t } from './i18n.js';
import { boxLabel } from './measuredraw.js';

/** A measure's label. Absent means „Sporen" — stage two measured
 *  nothing else, and the rows from before the labels are exactly that. */
export function measureLabel(m) {
    return m.label ?? 'Sporen';
}

/**
 * A measure's MARK — what kind of the layer's structure it is, beside
 * where it sits (07.10.2026: a chrysocystidium is a
 * pleurocystidium AND a chrysocystidium — „will ich mal nur eine
 * Statistik über die Chrysozystiden würde ich mich entscheiden müssen").
 * The layer stays the place; the mark lets the same measure count in the
 * layer's statistic and, filtered, among its own kind. Empty = none.
 */
export function measureMark(m) {
    return typeof m.mark === 'string' ? m.mark : '';
}

/** The rows of one mark — null keeps them all, '' the unmarked ones. */
export function rowsMarked(rows, mark) {
    return mark == null ? rows : rows.filter(r => r.mark === mark);
}

/**
 * µm per pixel of the space a measure was tapped in, through its picture's
 * calibration — the calibration was drawn on scale.width pixels, the
 * measure tapped on m.width; the ratio carries the µm across.
 */
export function measureUmPerPx(m, scale) {
    if (!scale) return null;
    const [sa, sb] = scale.line;
    const lineLen = Math.hypot(sb[0] - sa[0], sb[1] - sa[1]);
    if (!lineLen) return null;
    let umPerPx = scale.um / lineLen;
    if (scale.width && m.width) umPerPx *= scale.width / m.width;
    return umPerPx;
}

/**
 * The scale bar's length: the largest 1-2-5 length whose bar stays under
 * maxPx — or null when not even one fits at fingertip size (24 px), where
 * a bar would only pretend precision. ONE RULE for the photo window and
 * the microscope's live picture, so a bar says the same in both. No PHP
 * twin: the server draws no bar.
 */
export function scaleBarLength(pxPerUm, maxPx) {
    let best = null;
    for (let exp = 0; exp <= 6; exp++) {
        for (const m of [1, 2, 5]) {
            const um = m * 10 ** exp;
            const px = um * pxPerUm;
            if (px <= maxPx && (best === null || px > best.px)) best = { um, px };
        }
    }
    return best === null || best.px < 24 ? null : best;
}

/** "200 µm", "5 mm", "2 cm" — the 1-2-5 series keeps these integral. */
export function scaleLabel(um) {
    if (um < 1000)    return um + ' µm';
    if (um < 10000)   return (um / 1000) + ' mm';
    if (um < 1000000) return (um / 10000) + ' cm';
    return (um / 1000000) + ' m';
}

/**
 * Length and breadth of one rectangle measure in µm — the same geometry
 * as drawMeasure (axis from taps 1+2, breadth as the projection across it).
 */
export function rectDims(m, scale) {
    if (m.type !== 'rect') return null;
    const umPerPx = measureUmPerPx(m, scale);
    if (umPerPx === null) return null;
    const [a, b, c, d] = m.points;
    const ax = b[0] - a[0], ay = b[1] - a[1];
    const len = Math.hypot(ax, ay);
    if (!len) return null;
    const nx = -ay / len, ny = ax / len;
    const o1 = (c[0] - a[0]) * nx + (c[1] - a[1]) * ny;
    const o2 = (d[0] - a[0]) * nx + (d[1] - a[1]) * ny;
    const spore = { L: len * umPerPx, B: Math.abs(o2 - o1) * umPerPx };
    return spore.L > 0 && spore.B > 0 ? spore : null;
}

/**
 * The statistic values of a CUTOUT rectangle — the lengths of its inner
 * measures in µm, through the parent's own conversion chain.
 *
 * THE SLOT RULE (30.08.2026: „Linie 1 zu Linie 1 und Linie 2
 * zu Linie 2 … Durchschnitt aus den Polygonen und den Durchschnitt aus
 * den Linien"): two measures of one kind pool by DRAW ORDER across the
 * find's cutouts; a mixed pair pools polyline against polylines and line
 * against lines — so the polyline, the length of the curved structure,
 * takes the first slot regardless of which was drawn first. Q exists
 * only where the pair means length over breadth of one straight thing:
 * two plain lines („Ein Q braucht man natürlich nur bei zwei Linien").
 *
 * THE LENGTH STRAIGHT, AS THE BOOKS MEASURE IT (07.10.2026:
 * „kleine allantoide Sporen wurden klassisch nach Länge und Dicke
 * gemessen … das würde mit den klassischen Büchern nicht mehr
 * übereinstimmen"). With `straight` a polyline's length is the straight
 * line from its first point to its last — it was traced from tip to tip,
 * so its ends ARE the spore's ends, and every cutout ever measured holds
 * both lengths without a second tap. Q then divides that length. `bent`
 * says the length came from a polyline, whichever way it is read: only
 * there do the two readings differ, and only there is the choice offered.
 */
export function frameValues(m, scale, straight = false) {
    const umPerPx = measureUmPerPx(m, scale);
    if (umPerPx === null) return null;
    const len = (mi) => {
        let sum = 0;
        for (let i = 1; i < mi.points.length; i++) {
            sum += Math.hypot(mi.points[i][0] - mi.points[i - 1][0],
                              mi.points[i][1] - mi.points[i - 1][1]);
        }
        return sum * umPerPx;
    };
    const chord = (mi) => {
        const a = mi.points[0], b = mi.points[mi.points.length - 1];
        return Math.hypot(b[0] - a[0], b[1] - a[1]) * umPerPx;
    };
    if (CYSTID_PROFILES[m.profile]) return cystidValues(m, len, chord, straight);
    let [first, second] = m.inner;
    if (second && first.type === 'line' && second.type === 'path') {
        [first, second] = [second, first];
    }
    const bent = first.type === 'path';
    const L = bent && straight ? chord(first) : len(first);
    if (!(L > 0)) return null;
    const B = second ? len(second) : null;
    /* Q WHENEVER THE BREADTH IS A STRAIGHT LINE — the length may be
       bent (04.10.2026, on the allantoid spore: „Machen wir
       auch bei einer Allantoidenspore, also eben nach dem Polygon").
       Until then Q was for two straight lines only. Two polylines —
       which the cutout no longer offers, and old cutouts may hold —
       have no breadth to divide by. */
    const Q = B > 0 && second.type === 'line' ? L / B : null;
    return { L, B, Q, bent };
}

/*
 * A CYSTIDIUM MEASURED IN ITS PARTS — the third rectangle tool „Zystide"
 * (05.11.2026: „beim Messen von Zystiden brauche ich beim
 * Rechteck zwei Sonderfälle"). A frame around one cystidium and, inside
 * it, four measures in a fixed order, each one part of THAT cystidium, so
 * the ratios are its own:
 *
 * HEBELOMA, after Fungi Europaei 14 (Beker, Eberhardt & Vesterholt 2016,
 * p. 26: „a simple measurement of the length and width at apex is
 * considered inadequate"): the length l, A the largest width near the
 * apex, M the smallest in the median part, B the largest in the lower
 * third — and A/M, A/B, B/M. The book prints them „A 3.8–8.0; M 3.4–6.9;
 * B 5.8–13.2; 0.81 < A/M < 1.43".
 *
 * CONOCYBE (and Galerina's tibiiform ones), after Fungi Europaei 11
 * (Hausknecht 2009): „17–25 × 7–12 µm, with 4.5–6.5 µm wide capitula" —
 * the length, the belly, the head, and the neck's length the maintainer asked
 * for besides. THE BELLY IS THE BREADTH: the book's length × breadth is
 * length × belly, so the first line reads as the book's without a word.
 *
 * The ratios are averaged per cystidium (the maintainer agreed, 05.11.2026, as
 * the spores' Q is): each cystidium its own A/M, the line the span of
 * them. The keys stand in the measure (m.profile) and in every row
 * (parts); the words are the books'. includes/measures.php is the twin.
 *
 * `stacked`: EACH PART AND EACH RATIO ON A LINE OF ITS OWN
 * (08.11.2026, for Hebeloma: „die Werte A, M und B untereinander. Ebenso
 * wie den Quotienten untereinander und den Durchschnitt auch
 * untereinander"). Three widths and three ratios in one line ran on past
 * the eye; one under the other they read like the book's table.
 */
export const CYSTID_PROFILES = {
    hebeloma: {
        parts: [{ key: 'L' }, { key: 'A', word: () => 'A' }, { key: 'M', word: () => 'M' }, { key: 'B', word: () => 'B' }],
        ratios: [['A', 'M'], ['A', 'B'], ['B', 'M']],
        breadth: null,
        stacked: true,
    },
    conocybe: {
        parts: [{ key: 'L' }, { key: 'V' }, { key: 'C', word: () => t('Köpfchen') }, { key: 'N', word: () => t('Hals') }],
        ratios: [],
        breadth: 'V',
    },
    /* THE PARAPHYSIS (18.11.2026: „bei Paraphysen fehlt
       eventuell noch der Kopfdurchmesser … Länge mal Breite gebogen und plus
       Kopfdurchmesser. Das mit einer einfachen Strecke zu messen, ist etwas
       unkomfortabel", and his yes to it as a third method here): the length
       along the bent paraphysis, its breadth below the head, and the head's
       diameter — a key's „paraphyses 2–3 µm, apex up to 6 µm". The breadth
       is the breadth: the first line reads „L × B", the head beside it.
       THREE PLACES KNOW THE METHODS: here, its twin in includes/measures.php,
       and the cleaner in includes/photos.php that lets a method be saved. */
    paraphysis: {
        parts: [{ key: 'L' }, { key: 'B' }, { key: 'K', word: () => t('Kopf') }],
        ratios: [],
        breadth: 'B',
    },
};

/** The values of one cystidium: its length, its parts in µm, its ratios. */
function cystidValues(m, len, chord, straight) {
    const profile = CYSTID_PROFILES[m.profile];
    const inner = m.inner ?? [];
    const parts = {};
    profile.parts.forEach(({ key }, i) => {
        const mi = inner[i];
        if (!mi) return;
        // The length may be traced bent; read straight where the layer says so, as the cutout's.
        const v = key === 'L' && mi.type === 'path' && straight ? chord(mi) : len(mi);
        if (v > 0) parts[key] = v;
    });
    if (!(parts.L > 0)) return null;
    const ratios = {};
    for (const [a, b] of profile.ratios) {
        if (parts[a] > 0 && parts[b] > 0) ratios[`${a}/${b}`] = parts[a] / parts[b];
    }
    const B = profile.breadth ? (parts[profile.breadth] ?? null) : null;
    return { L: parts.L, B, Q: null, bent: inner[0]?.type === 'path', profile: m.profile, parts, ratios };
}

/**
 * All measured structures of a find under one label:
 * {kind, obj, m, L, B, Q, cropL, cropB} each. L is always there; B and
 * Q may be null (a cutout holding a single measure). cropL/cropB are
 * the RECTANGLE'S geometric µm — they size a cut-out, which for a cutout
 * is not the same thing as the values. `bent` marks a length traced as a
 * polyline; `straight` reads those lengths from end to end (frameValues);
 * `mark` is the measure's kind within the layer (measureMark).
 *
 * @param {{kind: string, obj: {measures?: object[], scale?: object}}[]} sources
 */
export function measureRows(sources, label, straight = false) {
    const rows = [];
    for (const s of sources) {
        for (const m of (s.obj.measures ?? [])) {
            if (measureLabel(m) !== label) continue;
            const dims = rectDims(m, s.obj.scale ?? null);
            if (!dims) continue;
            if (Array.isArray(m.inner) && m.inner.length > 0) {
                // A cutout: the rectangle only frames the thumbnail,
                // the values come from inside it.
                const v = frameValues(m, s.obj.scale ?? null, straight);
                if (v) rows.push({ ...s, m, ...v, mark: measureMark(m), cropL: dims.L, cropB: dims.B });
            } else {
                rows.push({ ...s, m, ...dims, Q: dims.L / dims.B, bent: false, mark: measureMark(m),
                    cropL: dims.L, cropB: dims.B });
            }
        }
    }
    return rows;
}

/**
 * THE PART MEASURES of a layer (07.10.2026): a germ pore, the
 * height of the ornament, an oil drop, a sterigma, a cystidium's head —
 * PARTS of the structure, each a plain line named for what it measures
 * (`part`), kept in the structure's own layer so one spore is measured in
 * one place. {part, um} with the source; a line without a name counts
 * nowhere, as before.
 */
export function partRows(sources, label) {
    const rows = [];
    for (const s of sources) {
        for (const m of (s.obj.measures ?? [])) {
            if (m.type !== 'line' || measureLabel(m) !== label || !measurePart(m)) continue;
            const umPerPx = measureUmPerPx(m, s.obj.scale ?? null);
            if (umPerPx === null) continue;
            const [a, b] = m.points;
            const um = Math.hypot(b[0] - a[0], b[1] - a[1]) * umPerPx;
            if (um > 0) rows.push({ ...s, m, part: measurePart(m), um });
        }
    }
    return rows;
}

/* --- Picture cutouts: no measures, a row of pictures ------------------------ */

/** A cutout's name — '' for one without; null for anything that is no
 *  cutout (07.10.2026, photos.php says what a cutout is). */
export function measureCrop(m) {
    return m.type === 'crop' ? (typeof m.name === 'string' ? m.name : '') : null;
}

/** The names the cutouts of a layer carry, in the order first met. */
export function cropNames(sources, label) {
    const names = [];
    for (const s of sources) {
        for (const m of (s.obj.measures ?? [])) {
            const name = measureCrop(m);
            if (name !== null && measureLabel(m) === label && !names.includes(name)) names.push(name);
        }
    }
    return names;
}

/**
 * A LAYER'S CUTOUTS OF ONE NAME as the rows of a plate's row
 * (07.10.2026: „eine Bildausschnittreihe, ohne Messen") — shaped like the
 * structures' rows, so drawSporeRow stands them upright by their axis,
 * base down and tip up, with no second geometry. Where every cutout's
 * picture has a scale they keep their sizes in µm — the row has one
 * scale and a bar; where one lacks it, all of them are drawn at one
 * length (cropL 1, the breadth in proportion) and the row is `unscaled`,
 * without a bar. In the order they were cut.
 */
export function cropRows(sources, label, name) {
    const found = [];
    for (const s of sources) {
        for (const m of (s.obj.measures ?? [])) {
            if (measureCrop(m) !== name || measureLabel(m) !== label) continue;
            const [a, b, c, d] = m.points;
            const ax = b[0] - a[0], ay = b[1] - a[1];
            const len = Math.hypot(ax, ay);
            if (!len) continue;
            const nx = -ay / len, ny = ax / len;
            const breadth = Math.abs(((d[0] - a[0]) * nx + (d[1] - a[1]) * ny) - ((c[0] - a[0]) * nx + (c[1] - a[1]) * ny));
            if (!breadth) continue;
            found.push({ s, m, len, breadth, umPerPx: measureUmPerPx(m, s.obj.scale ?? null) });
        }
    }
    const scaled = found.length > 0 && found.every(f => f.umPerPx !== null);
    return found.map(({ s, m, len, breadth, umPerPx }) => {
        const L = scaled ? len * umPerPx : 1;
        const B = scaled ? breadth * umPerPx : breadth / len;
        return { ...s, m, L, B, Q: null, cropL: L, cropB: B, crop: name, unscaled: !scaled };
    });
}

/** A line's part name, '' for none — only a line carries one. */
export function measurePart(m) {
    return m.type === 'line' && typeof m.part === 'string' ? m.part : '';
}

/** One line per part, in the order first met: the range notation from two
 *  on, the bare value for one, the count — and the plain average
 *  (07.10.2026: „bei den Teilmessungen überall noch den
 *  Durchschnitt"), left out for one, where it would repeat the value. */
export function partLines(rows) {
    const parts = [...new Set(rows.map(r => r.part))];
    return parts.map((part) => {
        const vals = rows.filter(r => r.part === part).map(r => r.um);
        return { part, n: vals.length,
            text: vals.length >= 2
                ? `${rangeText(vals, umText)} µm · N = ${vals.length} · Ø ${umText(mean(vals))} µm`
                : `${umText(vals[0])} µm · N = 1` };
    });
}

export function percentileOf(sorted, q) {
    const pos = (sorted.length - 1) * q;
    const lo = Math.floor(pos);
    const hi = Math.min(lo + 1, sorted.length - 1);
    return sorted[lo] + (sorted[hi] - sorted[lo]) * (pos - lo);
}

/* DECILES 1 AND 9, Piximètre's classical formula (Henriot, „La formule
   dimensionnelle": sort, cut the bottom and top tenth, the interval holds
   80 % of the measures, the parentheses the observed extremes) — switched
   from the 5/95 percentiles on 28.08.2026 so the maintainer's numbers read side
   by side with Piximètre output and the publications that follow it.

   BELOW TEN MEASURES THERE ARE NO DECILES WORTH THE NAME, and then the
   line is the plain span, smallest to largest (07.10.2026,
   over six spores: „in der Klammer das Gleiche wie der Bereich … wenn
   man da nichts Gescheites rechnen kann, dann würde ich die Klammer
   weglassen"). The outer tenth of six holds not even one spore; the
   „decile" is only a point between the two smallest, and rounded it said
   the extreme a second time. From ten on the formula stands, and a
   parenthesis still goes where, rounded, it repeats the bound beside it
   — „(5) 5 – 7 (7)" says nothing „5 – 7" does not. The PHP twin is
   measure_range() in includes/measures.php. */
export const RANGE_DECILES_FROM = 10;

export function rangeText(vals, um) {
    const s = [...vals].sort((x, y) => x - y);
    const min = um(s[0]), max = um(s[s.length - 1]);
    const span = (a, b) => (a === b ? a : `${a} – ${b}`);
    if (s.length < RANGE_DECILES_FROM) return span(min, max);
    const lo = um(percentileOf(s, .10)), hi = um(percentileOf(s, .90));
    return (min !== lo ? `(${min}) ` : '') + span(lo, hi) + (max !== hi ? ` (${max})` : '');
}

const umText = v => v.toLocaleString('de-DE', { maximumFractionDigits: 1 });
const qText  = v => v.toLocaleString('de-DE', { maximumFractionDigits: 2 });
const mean = a => a.reduce((x, y) => x + y, 0) / a.length;

/**
 * The numbers, as lines: {key, text, quiet}. THE MYCOLOGICAL STANDARD
 * LEADS (28.08.2026: „den mykologischen Standard … Mit
 * maximal, Bereich, minimal"): the notation every description and every
 * key uses for length and breadth ('range'), then the Q span and N
 * ('qn'), and the plain average last ('mean'). From a single spore the
 * notation would collapse into four copies of one number; until there
 * are two, the bare measure says it better (one 'range' line).
 *
 * SINCE THE CUTOUTS the pools may differ in size: every row carries a
 * first value, but a cutout holding a single measure has no second, and
 * Q exists only where a pair means length over breadth (frameValues).
 * Each pool is averaged against ITSELF, and a missing pool simply leaves
 * its part of the sentence out.
 */
export function valueLines(rows) {
    if (rows.length === 0) return [];
    if (rows.length === 1) {
        const r = rows[0];
        const profile = CYSTID_PROFILES[r.profile];
        if (profile?.stacked) {
            // One cystidium, stacked: its length, then each part, then each ratio, a line each.
            const parts = profile.parts.filter(p => p.word && p.key !== profile.breadth && r.parts[p.key] > 0)
                .map(p => ({ key: 'range', quiet: false, text: `${p.word()} ${umText(r.parts[p.key])} µm` }));
            const ratios = Object.entries(r.ratios).map(([w, v]) => ({ key: 'range', quiet: true, text: `${w} ≈ ${qText(v)}` }));
            return [{ key: 'range', quiet: false, text: `L ${umText(r.L)}${r.B != null ? ` × ${umText(r.B)}` : ''} µm` }, ...parts, ...ratios];
        }
        // One cystidium: its parts and ratios in the same line, plainly.
        const parts = profile ? profile.parts.filter(p => p.word && p.key !== profile.breadth && r.parts[p.key] > 0)
            .map(p => ` · ${p.word()} ${umText(r.parts[p.key])}`).join('') : '';
        const ratios = profile ? Object.entries(r.ratios).map(([w, v]) => ` · ${w} ≈ ${qText(v)}`).join('') : '';
        return [{ key: 'range', quiet: false, text: `${umText(r.L)}${r.B != null ? ` × ${umText(r.B)}` : ''} µm`
            + (r.Q != null ? ` · Q ≈ ${qText(r.Q)}` : '') + parts + ratios }];
    }
    const Ls = rows.map(r => r.L);
    const Bs = rows.filter(r => r.B != null).map(r => r.B);
    const Qs = rows.filter(r => r.Q != null).map(r => r.Q);
    const dims = list => (list.length >= 2 ? rangeText(list, umText) : umText(list[0]));
    // Q as the plain observed span — the (min) … (max) brackets are the
    // dimensions' notation, doubling them around Q reads twice. Unequal
    // pools show both counts: 12 lengths against 9 breadths.
    const bits = [];
    if (Qs.length >= 2) bits.push(`Q = ${qText(Math.min(...Qs))} – ${qText(Math.max(...Qs))}`);
    else if (Qs.length === 1) bits.push(`Q ≈ ${qText(Qs[0])}`);
    bits.push(`N = ${Ls.length}${Bs.length && Bs.length !== Ls.length ? '/' + Bs.length : ''}`);
    const cystid = cystidLines(rows, dims);
    /* THE LENGTH NAMED TOO where the parts stand one under the other
       (08.11.2026: „für die Länge auch noch ein L vorne dran") —
       among A, M and B a bare number would be the one line without a name. */
    const L = rows.every(r => CYSTID_PROFILES[r.profile]?.stacked) ? 'L ' : '';
    return [
        { key: 'range', quiet: false, text: `${L}${dims(Ls)}${Bs.length ? ` × ${dims(Bs)}` : ''} µm` },
        ...cystid.range,
        { key: 'qn', quiet: true, text: bits.join(' · ') },
        ...cystid.qn,
        { key: 'mean', quiet: true, text: `Ø ${L}${umText(mean(Ls))}${Bs.length ? ` × ${umText(mean(Bs))}` : ''} µm`
            + (Qs.length ? ` · Q ≈ ${qText(mean(Qs))}` : '') + cystid.mean },
        ...cystid.meanLines,
    ];
}

/* A CYSTIDIUM'S PARTS AND RATIOS beside the standard lines, under the same
   keys — a plate that shows the range shows the parts with it, one that
   shows Q shows the ratios: „A 3,8 – 8,0 · M 3,4 – 6,9 · B 5,8 – 13,2",
   „A/M 0,81 – 1,43 · A/B …", „Köpfchen 4,5 – 6,5 µm · Hals 2 – 4 µm".
   The length and the belly stand in the first line already. */
function cystidLines(rows, dims) {
    const out = { range: [], qn: [], mean: '', meanLines: [] };
    const profiles = [...new Set(rows.map(r => r.profile).filter(p => CYSTID_PROFILES[p]))];
    for (const name of profiles) {
        const profile = CYSTID_PROFILES[name];
        const mine = rows.filter(r => r.profile === name);
        const named = profile.parts.filter(p => p.word && p.key !== profile.breadth);
        const parts = named.map(p => [p.word(), mine.map(r => r.parts[p.key]).filter(v => v > 0)]).filter(([, vs]) => vs.length);
        const ratios = profile.ratios.map(([a, b]) => [`${a}/${b}`, mine.map(r => r.ratios[`${a}/${b}`]).filter(v => v > 0)]).filter(([, vs]) => vs.length);
        const span = ([w, vs]) => (vs.length >= 2 ? `${w} = ${qText(Math.min(...vs))} – ${qText(Math.max(...vs))}` : `${w} ≈ ${qText(vs[0])}`);
        if (profile.stacked) {
            for (const [w, vs] of parts) out.range.push({ key: 'range', quiet: false, text: `${w} ${dims(vs)} µm` });
            /* NO SPAN OF THE RATIOS (08.11.2026, after asking whether
               it matters): the literature gives ONE ratio per collection —
               its average (hebeloma.org, the holotype of H. vesterholtii:
               „A/M = 1.44") — and a species' span is the span of those
               averages over many collections. The spread of the single
               cystidia within one find compares with nothing printed and,
               laid beside a species' span, misleads. The averages stay. */
            for (const [w, vs] of parts) out.meanLines.push({ key: 'mean', quiet: true, text: `Ø ${w} ${umText(mean(vs))} µm` });
            for (const [w, vs] of ratios) out.meanLines.push({ key: 'mean', quiet: true, text: `Ø ${w} ≈ ${qText(mean(vs))}` });
            continue;
        }
        if (parts.length) out.range.push({ key: 'range', quiet: false, text: parts.map(([w, vs]) => `${w} ${dims(vs)}`).join(' · ') + ' µm' });
        if (ratios.length) {
            out.qn.push({ key: 'qn', quiet: true, text: ratios.map(span).join(' · ') });
            out.mean += ratios.map(([w, vs]) => ` · ${w} ≈ ${qText(mean(vs))}`).join('');
        }
        out.mean += parts.map(([w, vs]) => ` · ${w} ${umText(mean(vs))} µm`).join('');
    }
    return out;
}

/* --- The distribution ----------------------------------------------------- */

/* THE CORE HOLDS 68 % AND NOT 1 σ, since 10.09.2026. Until then the
   semi-axes lay at one standard deviation, and the maintainer found only three
   or four of 26 measured spores inside: „ich dachte irgendwie, innerhalb
   dieser Ellipse müssen sich die Mehrzahl der Punkte befinden." The
   expectation comes from ONE dimension, where ±1 σ holds two thirds of
   the values. In the plane a point has to lie close to the mean in both
   directions at once, and only 1 − e^(−½) ≈ 39 % manage that — simulated
   with the formulas from here: 37 % of 26. A 1-σ ellipse is therefore
   NEVER the majority, and without a label everyone asks it again.

   When asked, he chose 68 %: the same number as ±σ in one dimension, that
   is, what one knows from spore measures. The radius for it is k·σ with
   k = √(−2·ln(1 − 0.683)) ≈ 1.52; simulated 67 % of 26. Since then the
   number also stands IN the picture, in the ellipse's colour. */
export const CORE_SHARE = 0.683;

/* THE ELLIPSES COME FROM THE CORE, NOT FROM THE CROWD
   (07.10.2026: „unser Verteilungsdiagramm wäre ehrlicher, wenn der grüne
   innere Kreis sich eher um den Kern schließen würde und die Ausreißer
   wirklich außen vor lässt … kein Mensch vergleicht die
   Verteilungsellipsen. Jeder wird die Zahlen vergleichen"). The numbers
   stay Piximètre's; the picture may be the better statistic.

   Until then centre and spread were the plain mean and covariance of all
   measures, and one giant spore pulled the green core towards itself and
   blew it up. Now they are the MINIMUM COVARIANCE DETERMINANT (Rousseeuw):
   of all subsets holding three quarters of the measures, the one that
   lies tightest — the smallest determinant of its covariance — gives
   centre and spread. Found by „C-steps" (take the subset's centre and
   spread, keep the h measures nearest by Mahalanobis distance, repeat;
   the determinant never grows), started once from every measure's
   neighbourhood so the result is the same at every drawing — no dice.
   Then the usual two corrections: the spread scaled so the median
   distance fits the normal distribution's, and one reweighting — every
   measure inside the 97.5 % boundary counts again, the ones beyond it
   do not, and the spread is scaled for that cut.

   NOTHING IS MARKED: the far measures stay ordinary dots
   (the same day: „die Ausreißer nicht eigens kennzeichnen … nur die
   Ellipse dementsprechend neu berechnen"). Only the ellipses leave them
   out — the green one holds 68 % of the core, the dashed one is where
   the core's spread ends, at 97.5 %.

   BELOW TEN MEASURES NOTHING CAN TELL A FAR SPORE FROM SPREAD, and the
   plain mean and covariance of all of them draw both ellipses — the same
   threshold as the deciles (RANGE_DECILES_FROM). */
const CUT_FAR = -2 * Math.log(1 - .975);        // χ²₂ at 97.5 % ≈ 7.38
const CUT_MEDIAN = 2 * Math.log(2);             // χ²₂ at 50 %
// What a cut at CUT_FAR takes from the spread of a normal cloud, given back.
const REWEIGHT = .975 / (1 - Math.exp(-CUT_FAR / 2) * (1 + CUT_FAR / 2));

/** Centre and covariance (divided by n) of [x, y] points. */
function scatterOf(pts) {
    const n = pts.length;
    let mx = 0, my = 0;
    for (const [x, y] of pts) { mx += x; my += y; }
    mx /= n; my /= n;
    let sxx = 0, syy = 0, sxy = 0;
    for (const [x, y] of pts) {
        sxx += (x - mx) ** 2; syy += (y - my) ** 2;
        sxy += (x - mx) * (y - my);
    }
    return { mx, my, sxx: sxx / n, syy: syy / n, sxy: sxy / n };
}
const detOf = s => s.sxx * s.syy - s.sxy * s.sxy;
const scaled = (s, f) => ({ ...s, sxx: s.sxx * f, syy: s.syy * f, sxy: s.sxy * f });
/** The squared Mahalanobis distance of a point from a scatter. */
function farness(s, [x, y]) {
    const dx = x - s.mx, dy = y - s.my;
    return (s.syy * dx * dx - 2 * s.sxy * dx * dy + s.sxx * dy * dy) / detOf(s);
}
const medianOf = (a) => {
    const s = [...a].sort((x, y) => x - y), m = s.length >> 1;
    return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2;
};

/**
 * The cloud's core: centre {mx, my} and covariance {sxx, syy, sxy} —
 * robust from RANGE_DECILES_FROM measures on, plain below (see above).
 * @param {number[][]} pts  [x, y] each
 */
export function cloudCore(pts) {
    const n = pts.length;
    const plain = scatterOf(pts);
    const tiny = 1e-12 * ((plain.sxx + plain.syy) ** 2 || 1);
    if (n < RANGE_DECILES_FROM || detOf(plain) <= tiny) return plain;
    const h = Math.max(Math.floor((n + 3) / 2), Math.ceil(.75 * n));
    // The starts' neighbourhoods are measured with each axis on its own
    // scale — a length spans more µm than a breadth.
    const xs = pts.map(p => p[0]), ys = pts.map(p => p[1]);
    const cx = medianOf(xs), cy = medianOf(ys);
    const sx = medianOf(xs.map(x => Math.abs(x - cx))) || Math.sqrt(plain.sxx) || 1;
    const sy = medianOf(ys.map(y => Math.abs(y - cy))) || Math.sqrt(plain.syy) || 1;
    const nearest = (rank) => pts.map((p, i) => [rank(p), i]).sort((a, b) => a[0] - b[0]).slice(0, h).map(([, i]) => i);
    // Every measure seeds a start — up to sixty of them, spread evenly
    // over the cloud, beyond that the drawing would wait for them.
    const step = Math.max(1, Math.ceil(n / 60));
    let best = null;
    for (let seed = 0; seed < n; seed += step) {
        const [qx, qy] = pts[seed];
        let subset = nearest(([x, y]) => ((x - qx) / sx) ** 2 + ((y - qy) / sy) ** 2);
        let s = scatterOf(subset.map(i => pts[i]));
        for (let round = 0; round < 30 && detOf(s) > tiny; round++) {
            const next = nearest(p => farness(s, p));
            const t = scatterOf(next.map(i => pts[i]));
            const same = next.slice().sort((a, b) => a - b).join() === subset.slice().sort((a, b) => a - b).join();
            subset = next;
            s = t;
            if (same) break;
        }
        if (detOf(s) > tiny && (best === null || detOf(s) < detOf(best))) best = s;
    }
    if (best === null) return plain;
    const raw = scaled(best, medianOf(pts.map(p => farness(best, p))) / CUT_MEDIAN);
    const kept = pts.filter(p => farness(raw, p) <= CUT_FAR);
    if (kept.length < 3) return raw;
    const again = scaled(scatterOf(kept), REWEIGHT);
    return detOf(again) > tiny ? again : raw;
}

/*
 * THE ELLIPSES ARE CALIBRATED FOR THE NUMBER OF MEASURES (09.11.2026).
 * Measured with simulated clean clouds (normal, no outlier at all) while
 * building the spread line below: the core's spread is too TIGHT for few
 * measures. At 26 spores the green ellipse held 63 % instead of 68 %, and
 * 7 % stood beyond the dashed one instead of 2.5 % — the line would have
 * called two spores of a spotless series outliers. Two causes:
 *
 *   below ten, the plain spread of all measures, divided by n (not n − 1)
 *   and judged on the very points it came from — a little tight inside,
 *   and nothing can ever stand beyond the dashed rim;
 *   from ten on, the robust core (cloudCore) — a subset of three quarters
 *   is by chance sometimes very tight, so the far tail is HEAVY: at 12
 *   measures the 97.5 % rim had to reach four times the squared distance.
 *   Trying variants (no reweighting, n − 1) moved the numbers little —
 *   it is the price of not being dragged by a giant spore, not a slip.
 *
 * So each ellipse gets its own factor on the squared distance, MEASURED
 * per number of measures and not derived: [n, inner, outer] — the factor
 * that makes, averaged over many clean clouds of that size, 68.3 % lie in
 * the green ellipse and 2.5 % beyond the dashed. 80 000 simulated
 * measures per row up to 80, 60 000 above; between rows linear, beyond
 * 300 none (the core is consistent there). The zigzag between 10 and 17
 * is real: the subset is ceil(¾ n), so its share jumps with n. Below ten
 * the outer factor stays 1 — shrinking the rim to its 2.5 % would invent
 * outliers among five spores. Whoever changes cloudCore measures the
 * table again (the script: the same loop as the commit of this date).
 */
const CORE_CALIBRATION = [
    [3, .87, 1], [4, 1.18, 1], [5, 1.19, 1], [6, 1.16, 1], [7, 1.14, 1], [8, 1.12, 1], [9, 1.11, 1],
    [10, 1.23, 4.06], [11, 1.2, 3.25], [12, 1.29, 4.37], [13, 1.25, 3.55], [14, 1.21, 2.99],
    [15, 1.18, 2.64], [16, 1.24, 2.99], [17, 1.21, 2.64], [18, 1.18, 2.39], [19, 1.17, 2.27],
    [20, 1.2, 2.36], [21, 1.19, 2.22], [22, 1.16, 2.03], [23, 1.15, 1.94], [24, 1.17, 2],
    [25, 1.15, 1.88], [26, 1.14, 1.85], [27, 1.14, 1.77], [28, 1.14, 1.76], [29, 1.13, 1.68],
    [30, 1.13, 1.67], [32, 1.13, 1.64], [34, 1.11, 1.55], [36, 1.11, 1.51], [38, 1.1, 1.48],
    [40, 1.1, 1.45], [45, 1.1, 1.38], [50, 1.08, 1.31], [55, 1.07, 1.25], [60, 1.07, 1.23],
    [70, 1.06, 1.17], [80, 1.04, 1.11], [100, 1.04, 1.11], [130, 1.02, 1.04], [170, 1.03, 1.03],
    [220, 1.02, 1.02], [300, 1, 1],
];
/** The two factors for n measures: on the squared distance of the green ellipse and of the dashed one. */
export function coreScale(n) {
    const table = CORE_CALIBRATION;
    if (n <= table[0][0]) return { inner: table[0][1], outer: table[0][2] };
    for (let i = 1; i < table.length; i++) {
        const [n1, a1, b1] = table[i];
        if (n > n1) continue;
        const [n0, a0, b0] = table[i - 1];
        const f = (n - n0) / (n1 - n0);
        return { inner: a0 + (a1 - a0) * f, outer: b0 + (b1 - b0) * f };
    }
    return { inner: 1, outer: 1 };
}

/**
 * THE CLOUD IN TWO NUMBERS (09.11.2026: „ob es ein extrem stark
 * verstreuter Fund ist … oder ob die Kernzahl wirklich sich nur um ein
 * Zentrum dreht und wir ein paar Ausreißer haben … Ich bin keine
 * Statistiker. Vielleicht mit einer Streuungszahlangabe"). The „68 %" at
 * the green ellipse is what it is BUILT to hold, not a count — the cloud's
 * own character needed numbers of its own:
 *
 *   spread    how far the core strays from its centre, as a share of the
 *             size: the core's standard deviation over its centre, length
 *             and breadth apart (the coefficient of variation). ± 6 % says
 *             two thirds of the spores lie within 6 % of the average.
 *             From the CORE, so a giant spore does not inflate it.
 *   far       the measures beyond the dashed ellipse — what a clean
 *             series has almost none of.
 *
 * Asked against three shares (core, ring, outside) he chose these two, the
 * count first: „ganz am Anfang die Anzahl nennen … und nur die Anzahl der
 * Ausreißer, also nicht mehr ‚von'". Below ten measures the core is the
 * plain mean and spread of all of them (cloudCore), so a far one can
 * hardly stand outside — the line says so by its small N.
 * Null without three measures that carry a breadth.
 */
export function cloudSpread(rows) {
    const pts = rows.filter(r => r.B != null && r.L > 0 && r.B > 0).map(r => [r.L, r.B]);
    if (pts.length < 3) return null;
    const core = cloudCore(pts);
    if (!(detOf(core) > 0) || !(core.mx > 0) || !(core.my > 0)) return null;
    // The same calibration as the ellipses drawn (coreScale): the line says what the picture shows.
    const { inner, outer } = coreScale(pts.length);
    return {
        n: pts.length,
        length: 100 * Math.sqrt(core.sxx * inner) / core.mx,
        breadth: 100 * Math.sqrt(core.syy * inner) / core.my,
        far: pts.filter(p => farness(core, p) > CUT_FAR * outer).length,
    };
}
/**
 * The same as one line: „N = 26 · Streuung Länge ± 6 %, Breite ± 5 % ·
 * Ausreißer 1"; '' where there is none. WITH THE COUNT under the
 * distribution, WITHOUT it among the values (09.11.2026: „bei
 * der Statistik … dort aber dann ohne n und wenn wir sie bei der
 * Verteilung einblenden mit n") — the values' Q line says N already, and a
 * distribution standing alone on a plate needs its own.
 */
export function spreadText(rows, withCount = true) {
    const s = cloudSpread(rows);
    if (!s) return '';
    const L = Math.round(s.length), B = Math.round(s.breadth);
    return withCount
        ? t('N = %d · Streuung Länge ± %d %%, Breite ± %d %% · Ausreißer %d', s.n, L, B, s.far)
        : t('Streuung Länge ± %d %%, Breite ± %d %% · Ausreißer %d', L, B, s.far);
}

/**
 * Length against breadth, every spore a dot, the core's centre as a dashed cross —
 * the picture Piximètre made the habit of, because a cloud shows two
 * species where an average hides them. Returns the SVG's inner markup for
 * a box W × H; the colours are the caller's — theme variables in the
 * window, ink on paper on a plate.
 *
 * THE TREND ELLIPSES, after Piximètre's own book (Henriot's Comparateur
 * page, read 28.08.2026 — his words: the ellipse is descriptive, „sans
 * signification mathématique particulière"): centred on the CENTROID, the
 * major axis along the cloud's trend line (the principal axis of the 2×2
 * covariance), the OUTER ellipse spanning the raw series' full length and
 * width. OURS ADDS AN INNER ONE along the same axes — the maintainer asked to
 * see „das engere Feld" against the outliers (28.08.2026). SINCE
 * 07.10.2026 BOTH COME FROM THE CORE (cloudCore, above): centre, axes and
 * spread robust, the outer rim at 97.5 % of that spread instead of at the
 * farthest measure — a giant spore stands outside it as an ordinary dot
 * and no longer drags either ellipse along. Drawn as sampled polygons, because the plot's x and y
 * scales are different µm-per-pixel and a rotated <ellipse> cannot
 * survive anisotropic mapping.
 *
 * @param {{L: number, B: number}[]} rows  two or more, each with a breadth
 * @param {{axis: string, quiet: string, accent: string, dot?: string}} ink
 */
export function plotSvg(rows, ink, { W = 220, H = 150, font = 9, dot = 3 } = {}) {
    const k = font / 9;   // everything around the data grows with the type
    /* THE AXIS TITLES ARE SMALLER THAN THE NUMBERS AND STAND AT THE AXES'
       ENDS (09.11.2026: „von der Schrift kleiner machen und
       Länge … ans Ende der Längenachse … dass man noch sieht, das eine ist
       die Achsbezeichnung, das andere ist das Maximale"). „Länge µm" sits
       ABOVE the length axis at its right end, the largest length's number
       BELOW it — the axis line runs between them, so they cannot touch.
       Above the axis there is room by construction: the domain keeps 12 %
       air under the lowest dot and the lowest point of the dashed rim, and
       nothing else reaches that band at the right end (the mean's dashed
       line stands at the centre). Before, the title had a line of its own
       under the numbers (07.10.2026, „Länge µm und die Zahl … sind zu
       dicht" — in the corner it had sat right under the largest number);
       that line went, and the bottom strip with it. */
    // A strip above the data for the „68 %" (below), so no dot runs under it.
    const left = 34 * k, right = 10 * k, top = 16 * k, bottom = 18 * k;
    const titleSize = font * .8;
    const Ls = rows.map(r => r.L), Bs = rows.map(r => r.B);
    // 12 % air each side; a single-valued axis still gets a span.
    const pad = (lo, hi) => {
        const d = (hi - lo) || hi * .1 || 1;
        return [lo - d * .12, hi + d * .12];
    };
    const n = rows.length;
    // Each ellipse calibrated for this many measures (coreScale, CORE_CALIBRATION).
    const scale = coreScale(n);
    const core = Math.sqrt(-2 * Math.log(1 - CORE_SHARE) * scale.inner);
    // The core's centre and spread — robust from ten measures on (cloudCore).
    const { mx, my, sxx, syy, sxy } = cloudCore(rows.map(r => [r.L, r.B]));
    const half = (sxx + syy) / 2;
    const spread = Math.sqrt(Math.max(0, half * half - (sxx * syy - sxy * sxy)));
    const l1 = half + spread, l2 = Math.max(0, half - spread);
    // Eigen direction of the larger root; a cloud without covariance
    // keeps its axes straight.
    const theta = Math.abs(sxy) > 1e-9 ? Math.atan2(l1 - sxx, sxy) : 0;
    const ux = Math.cos(theta), uy = Math.sin(theta);
    const vx = -uy, vy = ux;
    // The outer rim is where the core's spread ends, at 97.5 % — no longer
    // the farthest measure, which a single giant spore set.
    const far = Math.sqrt(CUT_FAR * scale.outer);
    const rim = (a, b) => {
        const points = [];
        for (let i = 0; i < 48; i++) {
            const tt = i / 48 * 2 * Math.PI;
            points.push([mx + ux * a * Math.cos(tt) + vx * b * Math.sin(tt),
                         my + uy * a * Math.cos(tt) + vy * b * Math.sin(tt)]);
        }
        return points;
    };
    const ellipses = n >= 3 && l1 > 0 && l2 > 0 ? {
        outer: rim(far * Math.sqrt(l1), far * Math.sqrt(l2)),
        inner: rim(core * Math.sqrt(l1), core * Math.sqrt(l2)),
        axis: [[mx - ux * far * Math.sqrt(l1), my - uy * far * Math.sqrt(l1)],
               [mx + ux * far * Math.sqrt(l1), my + uy * far * Math.sqrt(l1)]],
    } : null;

    // The domain breathes with the rims, or the outer ellipse would
    // poke out of its own picture.
    const domain = (vals) => [Math.min(...vals), Math.max(...vals)];
    const rimL = ellipses ? ellipses.outer.map(p => p[0]) : [];
    const rimB = ellipses ? ellipses.outer.map(p => p[1]) : [];
    const [x0, x1] = pad(...domain([...Ls, ...rimL]));
    const [yLow, y1] = pad(...domain([...Bs, ...rimB]));
    /* AIR FOR „LÄNGE µm" over the axis: the band under the lowest dot or
       rim point is at least the title's height and a half. The 12 % alone
       were not always enough — a cloud falling to the right lays the end
       of its dashed rim into exactly that corner. */
    const lowest = Math.min(...Bs, ...rimB);
    const need = titleSize * 1.5 / (H - top - bottom);
    const y0 = Math.min(yLow, (lowest - need * y1) / (1 - need));
    const X = v => left + (v - x0) / (x1 - x0) * (W - left - right);
    const Y = v => H - bottom - (v - y0) / (y1 - y0) * (H - top - bottom);
    const path = (points) => points.map(p => `${X(p[0])},${Y(p[1])}`).join(' ');
    const label = (x, y, text, anchor) =>
        `<text x="${x}" y="${y}" font-size="${font}" fill="${ink.quiet}"
           text-anchor="${anchor}">${text}</text>`;
    let inner =
          `<line x1="${left}" y1="${H - bottom}" x2="${W - right}" y2="${H - bottom}"
             stroke="${ink.axis}" stroke-width="${k}"/>`
        + `<line x1="${left}" y1="${top}" x2="${left}" y2="${H - bottom}"
             stroke="${ink.axis}" stroke-width="${k}"/>`
        // The data's own extremes as tick labels — rounder numbers would
        // be prettier and say less.
        + label(X(Math.min(...Ls)), H - bottom + 11 * k, umText(Math.min(...Ls)), 'middle')
        + label(X(Math.max(...Ls)), H - bottom + 11 * k, umText(Math.max(...Ls)), 'middle')
        + label(left - 3 * k, Y(Math.min(...Bs)) + 3 * k, umText(Math.min(...Bs)), 'end')
        + label(left - 3 * k, Y(Math.max(...Bs)) + 3 * k, umText(Math.max(...Bs)), 'end')
        + `<text x="${W - right}" y="${H - bottom - 2.5 * k}" font-size="${titleSize}" fill="${ink.quiet}"
             text-anchor="end">${t('Länge µm')}</text>`
        /* At the breadth axis' top end, in the strip over the data, on one
           line with the „68 %" opposite — with its unit like the length's
           (09.11.2026: „bei der Breite sollten wir auch noch µm
           dazu schreiben"). The unit goes to the titles, not to the
           numbers: written at each number it would widen the very ones
           that must not touch the titles. It BEGINS at the axis instead of
           ending before it: „Breite µm" is wider than the margin the
           numbers leave and would run out of the picture on the left. */
        + `<text x="${left - 2 * k}" y="${top - 5 * k}" font-size="${titleSize}" fill="${ink.quiet}"
             text-anchor="start">${t('Breite µm')}</text>`
        + `<line x1="${X(mx)}" y1="${top}" x2="${X(mx)}" y2="${H - bottom}"
             stroke="${ink.quiet}" stroke-dasharray="${3 * k} ${3 * k}" stroke-width="${.7 * k}"/>`
        + `<line x1="${left}" y1="${Y(my)}" x2="${W - right}" y2="${Y(my)}"
             stroke="${ink.quiet}" stroke-dasharray="${3 * k} ${3 * k}" stroke-width="${.7 * k}"/>`;
    if (ellipses !== null) {
        inner += `<polygon points="${path(ellipses.outer)}" fill="none"
                stroke="${ink.quiet}" stroke-width="${.8 * k}"
                stroke-dasharray="${4 * k} ${3 * k}"/>`
            + `<polygon points="${path(ellipses.inner)}"
                fill="${ink.accent}" fill-opacity=".08"
                stroke="${ink.accent}" stroke-width="${.8 * k}"/>`
            /* What the green rim means, in its own colour — top right, in
               a strip OVER the data (07.10.2026). Inside the corner it sat
               on the largest spore, which is exactly where a giant lands;
               above the axis' top end no dot and no rim can reach it. */
            + `<text x="${W - right}" y="${top - 5 * k}" font-size="${font}"
                 fill="${ink.accent}" text-anchor="end">${Math.round(CORE_SHARE * 100)} %</text>`
            + `<line x1="${X(ellipses.axis[0][0])}" y1="${Y(ellipses.axis[0][1])}"
                x2="${X(ellipses.axis[1][0])}" y2="${Y(ellipses.axis[1][1])}"
                stroke="${ink.quiet}" stroke-width="${.6 * k}"
                stroke-dasharray="${2 * k} ${4 * k}"/>`;
    }
    for (const r of rows) {
        inner += `<circle class="photo-stats-dot" cx="${X(r.L)}" cy="${Y(r.B)}"
                      r="${dot}" fill="${ink.dot ?? ink.accent}" fill-opacity=".7"/>`;
    }
    return inner;
}

/**
 * The spores SMALL TO LARGE by the chosen value — 'L', 'B' or 'Q'; a
 * cutout without its breadth has no B and no Q and goes to the end; ties
 * keep the measuring order, so a tile never jumps for nothing. A copy:
 * the caller's rows stay as they were measured.
 */
export function sporeOrder(rows, key) {
    return rows.map((r, i) => [r, i]).sort(([a, i], [b, j]) => {
        const x = a[key], y = b[key];
        if (x == null || y == null) return x == null && y == null ? i - j : (x == null ? 1 : -1);
        return (x - y) || (i - j);
    }).map(([r]) => r);
}

/* --- One part measure: its numbers, its curve, its cut-out ------------------- */

/**
 * A part's numbers in its own tab (07.10.2026: „wenn man die
 * anklickt, bekommt man da eine Statistik"): the range notation first,
 * then the count and the plain average, quiet — valueLines() for a
 * measure with ONE dimension.
 */
export function partValueLines(rows) {
    if (rows.length === 0) return [];
    const vals = rows.map(r => r.um);
    if (vals.length === 1) return [{ key: 'range', quiet: false, text: `${umText(vals[0])} µm` }];
    return [
        { key: 'range', quiet: false, text: `${rangeText(vals, umText)} µm` },
        { key: 'qn', quiet: true, text: `N = ${vals.length} · Ø ${umText(mean(vals))} µm` },
    ];
}

/**
 * ONE DIMENSION, ONE CURVE (07.10.2026: „eine Verteilung,
 * meinetwegen nur eine Kurve bei einer Höhe"). A germ pore has no breadth
 * to plot against, so its values lie along one axis: every measure a dot
 * on the base line, the mean dashed, and over them a smoothed curve — a
 * Gaussian kernel at Silverman's bandwidth, the curve a histogram would
 * be without the arbitrary bins. Returns the SVG's inner markup for a box
 * W × H in the caller's ink, the dots carry class photo-stats-dot like
 * the scatter's, in the order of the rows.
 */
export function curveSvg(rows, ink, { W = 220, H = 110, font = 9 } = {}) {
    const vals = rows.map(r => r.um);
    if (vals.length < 2) return '';
    // Everything around the data grows with the type, as in plotSvg — a
    // plate draws at print scale.
    const k = font / 9;
    // The unit on a line of its own under the numbers, as in plotSvg.
    const left = 8 * k, right = 8 * k, top = 10 * k, bottom = 30 * k;
    const lo = Math.min(...vals), hi = Math.max(...vals);
    const span = (hi - lo) || hi * .2 || 1;
    const x0 = lo - span * .25, x1 = hi + span * .25;
    const avg = mean(vals);
    const sd = Math.sqrt(mean(vals.map(v => (v - avg) ** 2)));
    const h = sd > 0 ? 1.06 * sd * vals.length ** -0.2 : span * .1;
    const density = (x) => vals.reduce((sum, v) => sum + Math.exp(-0.5 * ((x - v) / h) ** 2), 0);
    const samples = [];
    for (let i = 0; i <= 80; i++) {
        const x = x0 + (x1 - x0) * i / 80;
        samples.push([x, density(x)]);
    }
    const peak = Math.max(...samples.map(p => p[1])) || 1;
    const X = v => left + (v - x0) / (x1 - x0) * (W - left - right);
    const Y = d => H - bottom - d / peak * (H - top - bottom - 6 * k);
    const base = H - bottom;
    const curve = samples.map(([x, d]) => `${X(x)},${Y(d)}`).join(' ');
    let inner = `<polygon points="${X(x0)},${base} ${curve} ${X(x1)},${base}"
            fill="${ink.accent}" fill-opacity=".08" stroke="none"/>`
        + `<polyline points="${curve}" fill="none" stroke="${ink.accent}" stroke-width="${1.2 * k}"/>`
        + `<line x1="${left}" y1="${base}" x2="${W - right}" y2="${base}" stroke="${ink.axis}" stroke-width="${k}"/>`
        + `<line x1="${X(avg)}" y1="${top}" x2="${X(avg)}" y2="${base}" stroke="${ink.quiet}"
            stroke-dasharray="${3 * k} ${3 * k}" stroke-width="${.7 * k}"/>`
        + `<text x="${X(lo)}" y="${base + 11 * k}" font-size="${font}" fill="${ink.quiet}" text-anchor="middle">${umText(lo)}</text>`
        + `<text x="${X(hi)}" y="${base + 11 * k}" font-size="${font}" fill="${ink.quiet}" text-anchor="middle">${umText(hi)}</text>`
        + `<text x="${(left + W - right) / 2}" y="${H - 4 * k}" font-size="${font}" fill="${ink.quiet}" text-anchor="middle">µm</text>`;
    for (const v of vals) {
        inner += `<circle class="photo-stats-dot" cx="${X(v)}" cy="${base - 4 * k}" r="${3 * k}"
            fill="${ink.dot ?? ink.accent}" fill-opacity=".7"/>`;
    }
    return inner;
}

/** Which way a part's line points in its tiles — the account's choice per
 *  part („Alle drehen" turns it a quarter at a time), right without one. */
export function partDirection(style, label, part) {
    const v = style?.part_direction?.[partAirKey(label, part)];
    return ['right', 'down', 'left', 'up'].includes(v) ? v : 'right';
}

/**
 * One part measure cut out — the line LYING, centred, at `pxPerUm`, so
 * every tile of a part stands at one µm scale and a short germ pore looks
 * short beside a long one. The picture around it is the context: a pore
 * means nothing without the spore's apex it sits in (the air, per part).
 * Only the line's neighbourhood is drawn, as with the spores.
 */
export function lineDraw(ctx, img, row, pxPerUm, frame = null, direction = 'right') {
    const m = row.m;
    const across = img.naturalWidth / (m.width ?? img.naturalWidth);
    const [a, b] = m.points.map(([x, y]) => [x * across, y * across]);
    const dx = b[0] - a[0], dy = b[1] - a[1];
    const len = Math.hypot(dx, dy) || 1;
    const cx = (a[0] + b[0]) / 2, cy = (a[1] + b[1]) / 2;
    const zoom = row.um * pxPerUm / len;
    // The tile's mirror, as a spore's (sporeDraw): screen terms, first.
    const mirror = String(m.mirror ?? '');
    // The part's direction: the first click's end of the line points there.
    const turn = { right: 0, down: Math.PI / 2, left: Math.PI, up: -Math.PI / 2 }[direction] ?? 0;
    ctx.save();
    ctx.scale(mirror.includes('h') ? -1 : 1, mirror.includes('v') ? -1 : 1);
    ctx.rotate(turn - Math.atan2(dy, dx));
    ctx.scale(zoom, zoom);
    ctx.translate(-cx, -cy);
    let sx = 0, sy = 0, sw = img.naturalWidth, sh = img.naturalHeight;
    if (frame) {
        const reach = Math.hypot(frame.W, frame.H) / 2 / zoom + 2;
        sx = Math.max(0, Math.floor(cx - reach));
        sy = Math.max(0, Math.floor(cy - reach));
        sw = Math.min(img.naturalWidth, Math.ceil(cx + reach)) - sx;
        sh = Math.min(img.naturalHeight, Math.ceil(cy + reach)) - sy;
    }
    if (sw > 0 && sh > 0) ctx.drawImage(img, sx, sy, sw, sh, sx, sy, sw, sh);
    ctx.restore();
}

/** The air around a part's cut-out: its own slider value, else the
 *  spores' snug 118 % — the same start as every other cut-out
 *  (07.10.2026: „den Standardrahmen für die Teilmessung bitte runtersetzen,
 *  angleichen auf die normale Messung"; it began at 300 % for a day). */
export function partAir(style, label, part) {
    const v = Number(style?.spore_air?.[partAirKey(label, part)]);
    return v >= 100 && v <= 300 ? v / 100 : 1.18;
}

/** Where a part's air lives in the account's per-layer map. */
export function partAirKey(label, part) {
    return `${label} · ${part}`.slice(0, 40);
}

/* --- Counting: frames and sectors ------------------------------------------ */

/**
 * The ellipse of a sector: its middle, its long axis (a, along u), its
 * short one (b, along n), and the way between the picture and the cap
 * seen from above. Null for taps that make no ellipse. In whatever
 * pixels the taps are given: the photo window's picture, a plate's view.
 */
export function sectorShape(taps) {
    const [c, ta, tb] = taps;
    const ax = ta[0] - c[0], ay = ta[1] - c[1];
    const a = Math.hypot(ax, ay);
    if (a < 1) return null;
    const u = [ax / a, ay / a], n = [-u[1], u[0]];
    // The short side counts across the long axis: a tap a little off it still says how wide.
    const b = Math.max(1, Math.abs((tb[0] - c[0]) * n[0] + (tb[1] - c[1]) * n[1]));
    const at = (x, y) => [c[0] + u[0] * x + n[0] * y, c[1] + u[1] * x + n[1] * y];
    return {
        c, a, b, u, n,
        theta: Math.atan2(u[1], u[0]),
        // How far from straight above it was taken: the short axis is the long one foreshortened.
        tilt: Math.acos(Math.min(1, Math.min(a, b) / Math.max(a, b))) * 180 / Math.PI,
        /** A point as seen from above: its angle (radians) round the middle and its distance in rims. */
        param([px, py]) {
            const dx = px - c[0], dy = py - c[1];
            const x = (dx * u[0] + dy * u[1]) / a, y = (dx * n[0] + dy * n[1]) / b;
            return { t: Math.atan2(y, x), r: Math.hypot(x, y) };
        },
        /** The rim at angle t (radians, as seen from above), in the picture. */
        rim: (t, k = 1) => at(a * Math.cos(t) * k, b * Math.sin(t) * k),
        corners: [at(-a, -b), at(a, -b), at(a, b), at(-a, b)],
        short: at(0, b),
    };
}

/** The slice in radians, from and to as swept, or null. */
export const sectorArc = (m) => (Array.isArray(m.arc) ? m.arc.map((d) => d * Math.PI / 180) : null);

/** Is the angle t inside the slice from t0 swept to t1? */
export function sectorInside(t, t0, t1) {
    const span = t1 - t0, full = 2 * Math.PI;
    if (Math.abs(span) >= full - 1e-9) return true;
    const d = span >= 0 ? ((t - t0) % full + full) % full : ((t0 - t) % full + full) % full;
    return d <= Math.abs(span);
}

/** The slice as an SVG path: the middle, along the rim from t0 to t1, back to the middle. */
export function sectorSlicePath(g, t0, t1, apex = g.stem ?? g.c) {
    const span = t1 - t0;
    if (Math.abs(span) >= 2 * Math.PI - 1e-6) {
        const [x1, y1] = g.rim(0), [x2, y2] = g.rim(Math.PI);
        const deg = g.theta * 180 / Math.PI;
        return `M${x1} ${y1}A${g.a} ${g.b} ${deg} 1 1 ${x2} ${y2}A${g.a} ${g.b} ${deg} 1 1 ${x1} ${y1}Z`;
    }
    const [x0, y0] = g.rim(t0), [xe, ye] = g.rim(t1);
    const large = Math.abs(span) > Math.PI ? 1 : 0, sweep = span > 0 ? 1 : 0;
    return `M${apex[0]} ${apex[1]}L${x0} ${y0}A${g.a} ${g.b} ${g.theta * 180 / Math.PI} ${large} ${sweep} ${xe} ${ye}Z`;
}

/* THE STEM WHERE IT SITS (18.11.2026, for Crepidotus and the
   oyster mushrooms: „bei Arten, die einen exzentrischen Stiel haben, würde
   unsere Ellipse mit dem Mittelpunkt ja gar nicht richtig funktionieren …
   der Zentrum der Lamellen liegt halt nicht mehr im Zentrum des Hutes").
   The ellipse stays the cap's outline; `stem` (measure pixels, absent =
   the middle) is where the lamellae run together. What is evenly spread
   is not the angle at the stem but the lamellae ALONG THE RIM — so a
   point belongs to the slice where the line from the stem through it
   meets the rim within the slice's angles, and the angles stay the
   rim's own as seen from above (param): taking round is still by the
   share of the rim, and for a stem in the middle nothing changes at all. */

/** The stem of a sector in the pixels its geometry was made in — the middle where none was set. */
export function sectorStemOf(m, g, across = 1) {
    return Array.isArray(m?.stem) && m.stem.length === 2 ? [m.stem[0] * across, m.stem[1] * across] : g.c;
}

/** Where the line from `stem` through `q` meets the rim, as the rim's angle t (radians, from above). */
export function sectorRimT(g, stem, q) {
    const local = ([x, y]) => {
        const dx = x - g.c[0], dy = y - g.c[1];
        return [(dx * g.u[0] + dy * g.u[1]) / g.a, (dx * g.n[0] + dy * g.n[1]) / g.b];
    };
    const s0 = local(stem), q0 = local(q);
    const dx = q0[0] - s0[0], dy = q0[1] - s0[1];
    const A = dx * dx + dy * dy;
    if (A < 1e-12) return Math.atan2(q0[1], q0[0]);
    const B = 2 * (s0[0] * dx + s0[1] * dy), C = s0[0] * s0[0] + s0[1] * s0[1] - 1;
    const disc = B * B - 4 * A * C;
    if (disc < 0) return Math.atan2(q0[1], q0[0]);
    // The far crossing: from a stem inside the only one ahead, from one at the rim the lamella's other end.
    const k = (-B + Math.sqrt(disc)) / (2 * A);
    return Math.atan2(s0[1] + k * dy, s0[0] + k * dx);
}

/* THE RIM THAT CARRIES LAMELLAE (the same evening: „Müsste man
   beim exzentrischen Stiel … nicht auch einfach sagen, welcher Teil der
   Ellipse ist nicht mehr Hutrand? … Der Hutrand geht ja nicht einmal
   außenrum, sondern er hat irgendwo ein Tortenstück … wo keine Lamellen
   mehr hinlaufen"). `rim` [from, to] in degrees, as `arc`: the stretch of
   the ellipse where lamellae end — absent, the whole round. Taking round
   is by THIS stretch, not by 360°, and a slice counts only as far as it
   lies on it. „Den ganzen Hut zählen" is the slice that is the rim. */

/** The lamellae's rim in radians, from and to as swept, or null for the whole round. */
export const sectorRimArc = (m) => (Array.isArray(m?.rim) ? m.rim.map((d) => d * Math.PI / 180) : null);

/** How many degrees two arcs [from, to] (degrees, either way swept) share on the circle. */
function arcOverlap(a, b) {
    const norm = ([f, t]) => {
        const len = Math.min(360, Math.abs(t - f));
        const start = (((t >= f ? f : t) % 360) + 360) % 360;
        return [start, len];
    };
    const [s1, l1] = norm(a), [s2, l2] = norm(b);
    let shared = 0;
    for (const k of [-360, 0, 360]) {
        shared += Math.max(0, Math.min(s1 + l1, s2 + k + l2) - Math.max(s1, s2 + k));
    }
    return Math.min(shared, l1, l2);
}

/** The degrees a sector's slice counts (on the rim) and the degrees of the rim it is taken round to — or null without a slice. */
export function sectorShare(m) {
    if (!Array.isArray(m?.arc)) return null;
    const rim = Array.isArray(m.rim) ? m.rim : [0, 360];
    const whole = Math.min(360, Math.abs(rim[1] - rim[0]));
    return { slice: arcOverlap(m.arc, rim), whole };
}

/** A count taken round to the whole rim that carries lamellae. */
export function sectorTakenRound(m, n) {
    const share = sectorShare(m);
    return share && share.slice > 0 ? Math.round(n * share.whole / share.slice) : 0;
}

/** Does the slice cover the whole rim — counted all round, nothing taken round? */
export const sectorWhole = (m) => {
    const share = sectorShare(m);
    return share !== null && share.slice >= share.whole - .01;
};

/** A stretch of the ellipse's outline, from t0 to t1 (radians, as swept) — no middle, no spokes. */
export function sectorRimPath(g, t0, t1) {
    const [x0, y0] = g.rim(t0), [xe, ye] = g.rim(t1);
    const span = t1 - t0;
    const large = Math.abs(span) > Math.PI ? 1 : 0, sweep = span > 0 ? 1 : 0;
    return `M${x0} ${y0}A${g.a} ${g.b} ${g.theta * 180 / Math.PI} ${large} ${sweep} ${xe} ${ye}`;
}

/* TWO KINDS OF POINTS IN ONE SLICE (18.11.2026: „bei den
   Sektoren noch zwei Untergruppen … Lamellen und Lamelletten, dass man
   die einzeln in eine Statistik messen kann", and asked how, „ein Sektor,
   zwei Punktarten"): one ellipse, one slice, and the line over the
   picture switches what the next click counts — the lamellae that reach
   the stipe (`count`) or the short ones between them (`lamellulae`).
   Both are taken round with the same angle.
   EACH KIND ITS COLOUR, PALE (the same day: „die Zählpunkte
   kannst du ruhig farbig machen, Lamellen grün, Lamelletten rot, aber
   nicht zu kräftig, ein bisschen Pastellfarben"). Written out, not
   tokens: like the import conflicts' kinds they name a category in the
   picture and must read the same in every theme. A dark hairline rings
   each dot — the measure's own seam is often white, and white round
   pastel on a pale cap was a smudge; on a dark cap the pastel carries
   itself. The switch over
   the picture carries the same dot. (Before, a lamellula was a ring.) */
export const SECTOR_KINDS = [
    { key: 'count', name: () => t('Lamellen'), colour: '#a6dba0' },
    { key: 'lamellulae', name: () => t('Lamelletten'), colour: '#f2a7a7' },
];

/** The slice's angle in degrees as seen from above, or null without a slice. */
export function sectorDegrees(m) {
    const arc = sectorArc(m);
    return arc === null ? null : Math.abs(arc[1] - arc[0]) * 180 / Math.PI;
}

/** A count taken round the whole cap with the slice's angle. */
export const sectorRound = (n, deg) => (deg > 0 ? Math.round(n * 360 / deg) : 0);

/* THE COUNT PER AREA (18.11.2026: „bei den Porenwerkzeugen …
   eine Runterrechnung … auf ein Quadratzentimeter, wenn wir im größeren
   Bereich sind … Aber das kann man ja im Prinzip an Maßstab festmachen,
   ob man auf einen Quadratzentimeter hochrechnet oder ob man auf 10 mal
   10 μ hochrechnet"). The frame's own size picks the area it is told
   for: from a millimetre a side — the cap's underside under the lens —
   per square centimetre; from a tenth of a millimetre per square
   millimetre, where a square centimetre would give thousands and ten
   micrometres a crumb; below, under the microscope, per 10 × 10 µm.
   Not for „Lamellen": lamellae per area say nothing. */
export function boxDensity(m, n) {
    if (measureLabel(m) === 'Lamellen' || !Array.isArray(m.um)) return '';
    const area = m.um[0] * m.um[1];
    if (!(area > 0)) return '';
    const side = Math.max(...m.um);
    const [per, words] = side >= 1000 ? [1e8, t('je cm²')] : side >= 100 ? [1e6, t('je mm²')] : [100, t('je 10 × 10 µm')];
    const v = n * per / area;
    const said = v.toLocaleString('de-DE', { maximumFractionDigits: v >= 100 ? 0 : v >= 10 ? 1 : 2 });
    return t('≈ %s %s', said, words);
}


/**
 * WHAT THE SECTORS OF A LAYER ADD UP TO, as lines of text — the photo
 * window's statistics and a plate's „Werte" both (18.11.2026).
 * Per kind the mean of the counts taken round to 360°, with its range and
 * the number of sectors, as a key writes it: „Lamellen: Ø ≈ 15 (14–15),
 * N = 2" — one sector has no range and says „≈ 15, N = 1". A sector where
 * a kind is not counted yet stays out of that kind's mean. Then the RATIO
 * pooled over the sectors that counted both, „L/l ≈ 1 : 2,3": all their
 * lamellulae over all their lamellae, so a wide slice weighs as much as it
 * holds; the angle drops out, both kinds were counted in the same slice.
 * „L/l", slash and no spaces (the same day): L the lamellae, l
 * the lamellulae, as keys abbreviate them.
 * `askew` says how many sectors lie past 35° of tilt — the window notes it.
 */
export function sectorSummary(sectors) {
    const withSlice = sectors.filter((m) => sectorDegrees(m) !== null);
    const lines = [];
    for (const kind of SECTOR_KINDS) {
        const rounds = withSlice.filter((m) => (m[kind.key] ?? []).length > 0)
            .map((m) => sectorTakenRound(m, m[kind.key].length));
        if (rounds.length === 0) continue;
        const avg = Math.round(rounds.reduce((a, v) => a + v, 0) / rounds.length);
        lines.push(rounds.length >= 2
            ? t('%s: Ø ≈ %d (%d–%d), N = %d', kind.name(), avg, Math.min(...rounds), Math.max(...rounds), rounds.length)
            : t('%s: ≈ %d, N = 1', kind.name(), avg));
    }
    const both = withSlice.filter((m) => (m.count ?? []).length > 0 && (m.lamellulae ?? []).length > 0);
    if (both.length > 0) {
        const lamellae = both.reduce((a, m) => a + m.count.length, 0);
        const lamellulae = both.reduce((a, m) => a + m.lamellulae.length, 0);
        lines.push(t('L/l ≈ 1 : %s', (lamellulae / lamellae).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })));
    }
    const askew = withSlice.filter((m) => (sectorShape(m.points)?.tilt ?? 0) > 35).length;
    return { lines, askew };
}

/** A counting frame's line: its kind and size, its count, its count per area. */
export function boxLine(m, n) {
    const square = Math.abs(m.um[0] - m.um[1]) < .05;
    const density = n > 0 ? boxDensity(m, n) : '';
    return (square ? t('Quadrat %s · Anzahl %d', boxLabel(m), n) : t('Rechteck %s · Anzahl %d', boxLabel(m), n))
        + (density ? ' · ' + density : '');
}

/** The counting measures of a layer — frames with a size, sectors — with their source. */
export function countMeasures(sources, label) {
    const out = [];
    for (const s of sources) {
        for (const m of (s.obj.measures ?? [])) {
            if (measureLabel(m) !== label) continue;
            if ((m.type === 'box' && Array.isArray(m.um)) || (m.type === 'sector' && Array.isArray(m.points) && m.points.length === 3)) out.push({ ...s, m });
        }
    }
    return out;
}

/**
 * A plate's lines for a layer's counts: the sectors' means and ratio, then
 * a line per frame. `named` where the line names its own kind („Lamellen:
 * Ø …", „L/l …") — the plate sets no layer name before those.
 */
export function countLines(sources, label) {
    const found = countMeasures(sources, label);
    const lines = sectorSummary(found.filter((r) => r.m.type === 'sector').map((r) => r.m)).lines.map((text) => ({ text, named: true }));
    for (const r of found) if (r.m.type === 'box') lines.push({ text: boxLine(r.m, (r.m.count ?? []).length), named: false });
    return lines;
}

/**
 * A LAYER'S COUNTS AS THE ROWS OF A PLATE'S ROW (18.11.2026:
 * „Bei den Tafeln kann ich noch nicht die Sektorenauswertung einfügen.
 * Also Bildausschnitte und Text"). Shaped like the cutouts' rows, so
 * rowGeometry lays them out with no second geometry: each frame or
 * ellipse in the AXIS-PARALLEL box about it, never turned — „immer so,
 * wie das Foto ausgerichtet ist … weil dann passen Schatten und Lichter
 * nicht mehr" — cropL its height, cropB its width in µm. A sector counts
 * once its slice is set; a frame needs its picture's scale, it IS its µm.
 * Where every picture has a scale the row keeps real sizes and a bar;
 * otherwise all stand in picture pixels and the row is `unscaled`, as a
 * row of cutouts. In the order they were set. countDraw draws one.
 */
export function countRows(sources, label) {
    const found = [];
    for (const r of countMeasures(sources, label)) {
        const { m } = r;
        const umPerPx = measureUmPerPx(m, r.obj.scale ?? null);
        let cx, cy, ex, ey;
        if (m.type === 'sector') {
            if (sectorArc(m) === null) continue;
            const g = sectorShape(m.points);
            if (g === null) continue;
            [cx, cy] = g.c;
            ex = Math.hypot(g.a * g.u[0], g.b * g.n[0]);
            ey = Math.hypot(g.a * g.u[1], g.b * g.n[1]);
        } else {
            if (umPerPx === null) continue;
            [cx, cy] = m.points[0];
            const hw = m.um[0] / umPerPx / 2, hh = m.um[1] / umPerPx / 2;
            const ang = (m.angle ?? 0) * Math.PI / 180, cos = Math.abs(Math.cos(ang)), sin = Math.abs(Math.sin(ang));
            ex = hw * cos + hh * sin;
            ey = hw * sin + hh * cos;
        }
        if (!(ex > 0 && ey > 0)) continue;
        found.push({ r, cx, cy, ex, ey, umPerPx });
    }
    const scaled = found.length > 0 && found.every((f) => f.umPerPx !== null);
    return found.map(({ r, cx, cy, ex, ey, umPerPx }) => {
        const k = scaled ? umPerPx : 1;
        return { ...r, L: 2 * ey * k, B: 2 * ex * k, Q: null, cropL: 2 * ey * k, cropB: 2 * ex * k,
            count: true, centre: [cx, cy], spanPx: 2 * ey, umPerPx, unscaled: !scaled };
    });
}

/**
 * Draw one count UPRIGHT AS PHOTOGRAPHED, centred on the context's origin,
 * at `pxPerUm` (the caller translates to the frame's centre and clips, as
 * for sporeDraw): the picture, then the frame or the ellipse with its
 * slice, then the counted points in their kinds' colours. The lines are
 * white over a dark seam, so they read on any cap; their strength and the
 * points' size follow the frame the row gives, not the picture's pixels.
 */
export function countDraw(ctx, img, row, pxPerUm, frame) {
    const m = row.m;
    const across = img.naturalWidth / (m.width ?? img.naturalWidth);
    const zoom = row.cropL * pxPerUm / (row.spanPx * across);
    const [cx, cy] = row.centre.map((v) => v * across);
    ctx.save();
    ctx.scale(zoom, zoom);
    ctx.translate(-cx, -cy);
    const reach = Math.hypot(frame.W, frame.H) / 2 / zoom + 2;
    const sx = Math.max(0, Math.floor(cx - reach)), sy = Math.max(0, Math.floor(cy - reach));
    const sw = Math.min(img.naturalWidth, Math.ceil(cx + reach)) - sx, sh = Math.min(img.naturalHeight, Math.ceil(cy + reach)) - sy;
    if (sw > 0 && sh > 0) ctx.drawImage(img, sx, sy, sw, sh, sx, sy, sw, sh);
    // One unit of line in the frame's own measure, read back into the picture's pixels.
    const unit = Math.max(frame.W, frame.H) / 160 / zoom;
    const stroke = (path) => {
        ctx.lineJoin = 'round';
        ctx.strokeStyle = 'rgba(0, 0, 0, .45)';
        ctx.lineWidth = 2.2 * unit;
        ctx.stroke(path);
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 1 * unit;
        ctx.stroke(path);
    };
    const dot = (x, y, colour) => {
        ctx.beginPath();
        ctx.arc(x, y, 3.2 * unit, 0, Math.PI * 2);
        ctx.fillStyle = colour;
        ctx.fill();
        ctx.lineWidth = .9 * unit;
        ctx.strokeStyle = 'rgba(0, 0, 0, .55)';
        ctx.stroke();
    };
    if (m.type === 'sector') {
        const g = sectorShape(m.points.map(([x, y]) => [x * across, y * across]));
        g.stem = sectorStemOf(m, g, across);
        const arc = sectorArc(m);
        const rim = sectorRimArc(m);
        if (rim === null) {
            const ellipse = new Path2D();
            ellipse.ellipse(g.c[0], g.c[1], g.a, g.b, g.theta, 0, Math.PI * 2);
            stroke(ellipse);
        } else {
            // The rim that carries lamellae drawn, the rest of the ellipse dashed: no rim there.
            stroke(new Path2D(sectorRimPath(g, rim[0], rim[1])));
            ctx.save();
            ctx.setLineDash([4 * unit, 3 * unit]);
            const rest = rim[1] >= rim[0] ? [rim[1], rim[0] + 2 * Math.PI] : [rim[1], rim[0] - 2 * Math.PI];
            stroke(new Path2D(sectorRimPath(g, rest[0], rest[1])));
            ctx.restore();
        }
        // The whole cap counted: no slice to draw, the ellipse is it.
        if (!sectorWhole(m)) {
            const slice = new Path2D(sectorSlicePath(g, arc[0], arc[1]));
            ctx.fillStyle = 'rgba(255, 255, 255, .14)';
            ctx.fill(slice);
            stroke(slice);
        }
        for (const kind of SECTOR_KINDS) for (const [x, y] of (m[kind.key] ?? [])) dot(x * across, y * across, kind.colour);
    } else {
        const umPx = row.umPerPx / across;
        const ang = (m.angle ?? 0) * Math.PI / 180, cos = Math.cos(ang), sin = Math.sin(ang);
        const local = (u, v) => [cx + u * cos - v * sin, cy + u * sin + v * cos];
        const hw = m.um[0] / umPx / 2, hh = m.um[1] / umPx / 2;
        const box = new Path2D();
        [[-hw, -hh], [hw, -hh], [hw, hh], [-hw, hh]].forEach(([u, v], i) => {
            const [x, y] = local(u, v);
            if (i === 0) box.moveTo(x, y); else box.lineTo(x, y);
        });
        box.closePath();
        stroke(box);
        for (const [u, v] of (m.count ?? [])) {
            const [x, y] = local(u / umPx, v / umPx);
            dot(x, y, SECTOR_KINDS[0].colour);
        }
    }
    ctx.restore();
}

/* --- One spore, cut out and stood upright ----------------------------------- */

/** The air around a cut-out spore — the account's slider per layer. */
export function sporeAir(style, label) {
    const v = Number(style?.spore_air?.[label]);
    return v >= 100 && v <= 300 ? v / 100 : 1.18;
}

/** Whether the account reads a layer's bent lengths straight, as the
 *  books do — its switch in the photo window, per layer (frameValues). */
export function lengthStraight(style, label) {
    return Array.isArray(style?.straight_length) && style.straight_length.includes(label);
}

/**
 * Draw one spore UPRIGHT, the long axis vertical (28.08.2026:
 * „hochkant, die lange Seite vertikal"), centred on the context's origin,
 * at `pxPerUm` — the caller translates to the frame's centre and clips.
 * The zoom comes from this spore's real length, so every spore drawn at
 * one pxPerUm stands at the same µm scale, whichever photo it came from.
 *
 * THE TILE'S MIRROR, in screen terms about the centre and before the
 * turn — so 'h' always swaps what stands left and right, whichever way
 * the axis ran in the picture.
 */
export function sporeDraw(ctx, img, spore, pxPerUm, frame = null) {
    const m = spore.m;
    const across = img.naturalWidth / (m.width ?? img.naturalWidth);
    const [a, b, c, d] = m.points.map(([x, y]) => [x * across, y * across]);
    const ax = b[0] - a[0], ay = b[1] - a[1];
    const len = Math.hypot(ax, ay) || 1;
    const nx = -ay / len, ny = ax / len;
    const o1 = (c[0] - a[0]) * nx + (c[1] - a[1]) * ny;
    const o2 = (d[0] - a[0]) * nx + (d[1] - a[1]) * ny;
    const cx = (a[0] + b[0]) / 2 + nx * (o1 + o2) / 2;
    const cy = (a[1] + b[1]) / 2 + ny * (o1 + o2) / 2;
    // The RECTANGLE'S µm span exactly len image pixels — that pins the
    // zoom. cropL, not L: a cutout's value is an inner length and says
    // nothing about how large the framed region is.
    const zoom = spore.cropL * pxPerUm / len;
    const mirror = String(m.mirror ?? '');
    ctx.save();
    ctx.scale(mirror.includes('h') ? -1 : 1, mirror.includes('v') ? -1 : 1);
    // π/2 minus the axis angle stands the axis upright (+y).
    ctx.rotate(Math.PI / 2 - Math.atan2(ay, ax));
    ctx.scale(zoom, zoom);
    ctx.translate(-cx, -cy);
    /* ONLY THE SPORE'S NEIGHBOURHOOD with a frame to fill: the circle its
       frame can reach at this zoom, not the whole photo — a row of 25
       spores from a 2048-pixel picture pushed the full picture through the
       canvas 25 times for a few hundred pixels each. */
    let sx = 0, sy = 0, sw = img.naturalWidth, sh = img.naturalHeight;
    if (frame) {
        const reach = Math.hypot(frame.W, frame.H) / 2 / zoom + 2;
        sx = Math.max(0, Math.floor(cx - reach));
        sy = Math.max(0, Math.floor(cy - reach));
        sw = Math.min(img.naturalWidth, Math.ceil(cx + reach)) - sx;
        sh = Math.min(img.naturalHeight, Math.ceil(cy + reach)) - sy;
    }
    if (sw > 0 && sh > 0) ctx.drawImage(img, sx, sy, sw, sh, sx, sy, sw, sh);
    ctx.restore();
}

/** How many image pixels one µm of this spore spans in `img` — for the
 *  printed page, which asks whether the preview is sharp enough. */
export function sporeImagePxPerUm(img, spore) {
    const m = spore.m;
    const across = img.naturalWidth / (m.width ?? img.naturalWidth);
    // A count's span is its upright box's height (countRows), not a line between two taps.
    if (spore.count) return spore.cropL > 0 ? spore.spanPx * across / spore.cropL : 0;
    const [a, b] = m.points;
    const len = Math.hypot((b[0] - a[0]) * across, (b[1] - a[1]) * across);
    return spore.cropL > 0 ? len / spore.cropL : 0;
}
