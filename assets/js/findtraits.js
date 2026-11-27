/**
 * ODOUR, TASTE AND SPORE PRINT — three find fields with a picker
 * (09.11.2026: „bei den Gerüchen … eine Vorauswahl … würzig
 * Gemüse und darunter dann nochmal Knoblauch, Kohl, Rettich …" and for the
 * spore print „erstmal eine Hauptgruppe anklicken … dann … auf einer
 * Fläche die Sporenpulverfarbe auswählen … von hell nach dunkel"; the
 * draft of the colour field he was „absolut zufrieden" with).
 *
 * THE VALUES ARE PLAIN TEXT in the find's extra block (find_fields.php,
 * 'picker'), so export, import, search, field times and sharing read them
 * as they read Substrat — and a word typed by hand stays beside the picked
 * ones. The pickers only write that text:
 *
 *   Geruch, Geschmack    the terms, „, " between them — „mehlartig,
 *                        gurkenartig, frisch wie Schnittgras". A term is a
 *                        stored word in German, like a choice's option;
 *                        the chips show it through t().
 *   Sporenpulverfarbe    „<group>: <words> #RRGGBB" — „Gelb – Ocker: dunkel
 *                        gelb #E5B563". Readable in a table, and the colour
 *                        and its group can be read back for statistics
 *                        (sporePrintRead).
 *
 * The lists are fixed here and nowhere else; a term added later is added
 * here and to the English catalogue.
 *
 *   Wuchsstelle          the same as odour and taste, but its groups come
 *                        WITH THE FIELD (find_fields.php, 'groups') — MykIS'
 *                        own words, which the server reads too for the DGfM
 *                        sheet, so they live once, there (27.11.2026).
 */

import { t } from './i18n.js';

/** Odours by group — the group heads the chips, a term is what is stored.
 *
 * THE VAGUE WORD FIRST IN ITS GROUP (15.11.2026: „da brauche ich
 * noch etwas schwammigere Gerüche wie würzig, einfach nur würzig. Oder
 * wohlriechend. Wenn man es jetzt nicht genau zuordnen kann"): whoever
 * smells something spicy without naming garlic or curry, something
 * pleasant without naming anise, takes the group's own word — würzig,
 * wohlriechend, fruchtig, unangenehm. At the head of its group, because
 * that is where one looks before the particular ones. */
export const ODOUR_GROUPS = [
    { name: 'ohne', terms: ['geruchlos', 'schwach pilzig'] },
    { name: 'Mehl und Gurke', terms: ['mehlartig', 'gurkenartig'] },
    { name: 'süß und blumig', terms: ['wohlriechend', 'fruchtig', 'süßlich', 'honigartig', 'blumig', 'Veilchen / Iris', 'Anis',
        'Bittermandel / Marzipan', 'Kokos', 'Birne'] },
    { name: 'würzig und Gemüse', terms: ['würzig', 'Knoblauch', 'Kohl', 'Rettich / Rübe', 'rohe Kartoffel', 'Kartoffelkeller',
        'Maggi / Liebstöckel', 'Curry', 'Pelargonie', 'Sellerie'] },
    { name: 'unangenehm', terms: ['unangenehm', 'Leuchtgas', 'Karbol / Tinte', 'Hering / Fisch', 'spermatisch', 'Chlor / Jod',
        'Wanze', 'Weidenbohrer / Raupe', 'Aas', 'Scheunenstaub'] },
    { name: 'erdig und Holz', terms: ['erdig', 'muffig / Keller', 'harzig', 'holzig'] },
];

/** Tastes — one group. */
export const TASTE_GROUPS = [
    { name: '', terms: ['mild', 'bitter', 'scharf, sofort', 'scharf, verzögert', 'mehlig', 'süßlich',
        'rettichartig', 'zusammenziehend', 'unangenehm'] },
];

/* „scharf, sofort" holds a comma — so the terms are not split at every
   comma but read against the known ones first (termsRead). */

/**
 * The spore print's groups (the draft of 09.11.2026, taken over as it
 * was): each a field whose left is light and right dark, its rows walking
 * the hue from `H[0]` at the top to `H[1]` at the bottom, in OKLCH. White
 * grows its tint down the rows instead of with the darkness. `rows` name
 * the top, the middle and the bottom; `ex` are genera to recognise it by.
 */
export const SPORE_GROUPS = [
    /* WHITE TO OCHRE IS ONE RUN (the same evening: „wir müssen
       unsere zwei Gruppen weiß bis creme und gelb bis ocker zusammenlegen.
       Von weiß bis ocker"): from pure white on the left over cream to dark
       ochre on the right, the colour growing with the darkness; the rows
       lean a little green at the top and a little red at the bottom, never
       as far as the olive the old yellow field ran into. Its words come by
       the step along the run (zones), the rows only add a lean. */
    { name: 'Weiß – Ocker', dot: 'oklch(0.9 0.08 88)', L: [0.995, 0.72], C: [0.0, 0.15], H: [91, 66],
      rows: ['grünlich', 'rein', 'rötlich'], zones: ['weiß', 'cremeweiß', 'creme', 'blass ocker', 'ocker', 'dunkel ocker'],
      romagnesi: true },
    { name: 'Rosa', dot: 'oklch(0.82 0.07 25)', L: [0.92, 0.68], C: [0.03, 0.10], H: [45, 355],
      rows: ['lachsrosa', 'rosa', 'fleischrosa'] },
    { name: 'Braun', dot: 'oklch(0.55 0.09 60)', L: [0.72, 0.32], C: [0.06, 0.11], H: [105, 30],
      rows: ['olivbraun', 'zimt- bis rostbraun', 'rotbraun'] },
    { name: 'Purpurbraun – Schwarz', dot: 'oklch(0.32 0.05 330)', L: [0.48, 0.14], C: [0.06, 0.03], H: [40, 310],
      rows: ['schwarzbraun', 'purpurbraun', 'violettschwarz'] },
    { name: 'Grün', dot: 'oklch(0.72 0.07 140)', L: [0.84, 0.55], C: [0.04, 0.09], H: [125, 160],
      rows: ['gelbgrün', 'graugrün', 'blaugrün'] },
];
const LIGHT_WORDS = ['sehr hell', 'hell', 'mittel', 'dunkel', 'sehr dunkel'];

/*
 * ROMAGNESI'S FOURTEEN GRADES for the russules (09.11.2026: „bau
 * die 14 Stufen so ein") — I a–b white, II a–d cream, III a–c ochre, IV a–e
 * yellow, as Kibby (The Genus Russula in Great Britain, 2026, Fig. 6) shows
 * them and his and Marxmüller's keys name them (IId, IVd, IVe dozens of
 * times — reducing to a–c, first asked for, would have made those keys'
 * words unenterable). The colours: the ten Romagnesi's own chart prints,
 * from the yellow/magenta values on Kränzlin's sheet (Der Tintling
 * 3/2014), as macOS renders them; IIb, IIIb, IVb and IVd, which it leaves
 * out, laid between their neighbours in OKLab. Numbers, not a scan — a
 * print and a scan each shift the colour, as the maintainer said.
 * [grade, colour, printed?, words]
 */
export const ROMAGNESI = [
    ['Ia', '#FFFFFE', true, 'rein weiß'], ['Ib', '#FFFAE3', true, 'weißlich'],
    ['IIa', '#FEEEC2', true, 'blass creme'], ['IIb', '#FEECBC', false], ['IIc', '#FDE9B6', true, 'mittel creme'], ['IId', '#FDE1AB', true, 'dunkel creme'],
    ['IIIa', '#FEDF90', true, 'blass ocker'], ['IIIb', '#FCDA86', false], ['IIIc', '#FAD47C', true, 'dunkel ocker'],
    ['IVa', '#F9C971', true, 'blass gelb'], ['IVb', '#F8C467', false], ['IVc', '#F6BE5C', true, 'mittel gelb'], ['IVd', '#F5B957', false], ['IVe', '#F4B352', true, 'dunkel gelb'],
];

/** A colour in OKLab, where a distance is what the eye sees. */
function oklab(hex) {
    const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255)
        .map(c => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
    const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
    const m = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
    const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
    return [0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s,
        1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s,
        0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s];
}
/** The Romagnesi grade nearest to a colour — shown beside a pick in the white and yellow groups. */
export function romagnesiNearest(hex) {
    const p = oklab(hex);
    let best = ROMAGNESI[0], d = Infinity;
    for (const grade of ROMAGNESI) {
        const q = oklab(grade[1]);
        const dist = Math.hypot(p[0] - q[0], p[1] - q[1], p[2] - q[2]);
        if (dist < d) { d = dist; best = grade; }
    }
    return best[0];
}

/* --- Reading and writing the text ------------------------------------------ */

/** The terms a value holds, known ones first (they may hold a comma), the rest split at commas. */
export function termsRead(value, known) {
    let rest = `, ${String(value ?? '').trim()}, `;
    const found = [];
    // The longest first, so „scharf, verzögert" is not eaten by a shorter one.
    for (const term of [...known].sort((a, b) => b.length - a.length)) {
        const at = rest.toLowerCase().indexOf(`, ${term.toLowerCase()}, `);
        if (at === -1) continue;
        found.push({ term, at });
        rest = rest.slice(0, at) + ', ' + rest.slice(at + term.length + 4);
    }
    const free = rest.split(',').map(s => s.trim()).filter(Boolean);
    return { picked: found.map(f => f.term), free };
}

/** The text after a term was ticked or unticked — the order of the list, free words behind. */
export function termsToggle(value, term, on, groups) {
    const known = groups.flatMap(g => g.terms);
    const { picked, free } = termsRead(value, known);
    const set = new Set(picked.filter(p => p !== term));
    if (on) set.add(term);
    return [...known.filter(k => set.has(k)), ...free].join(', ');
}

/** A spore print's text read back: {group, words, color} — or null for a hand-written one. */
export function sporePrintRead(value) {
    const m = String(value ?? '').trim().match(/^(.+?):\s*(.*?)\s*(#[0-9a-f]{6})$/i);
    if (!m) return null;
    return { group: m[1].trim(), words: m[2].trim(), color: m[3].toUpperCase() };
}

/*
 * THE SPORE PRINT SHOWS AS ITS WORDS AND ITS COLOUR (09.11.2026:
 * „ich brauche nicht den Text weiß-creme Doppelpunkt … und dann den
 * Farbcode. Ich brauche nur die Info … und dann ein Kästchen mit der
 * Farbe"). The field keeps the whole text for statistics, but in the
 * window it shows only the words, a square of the colour before it — big
 * enough to hold against the field below while choosing. The whole value
 * rides on the input as data-stored, which collectExtraFields reads
 * (pointwindow.js); a word typed by hand replaces it and has no colour.
 */
export function sporeFieldBind(input, swatch) {
    const show = () => {
        const read = sporePrintRead(input.dataset.stored ?? '');
        swatch.hidden = !read;
        if (read) swatch.style.background = read.color;
    };
    const full = input.value;
    input.dataset.stored = full;
    input.value = sporePrintRead(full)?.words ?? full;
    // Typed by hand: the words are the value, without a colour.
    input.addEventListener('input', () => {
        if (input.dataset.picking) return;
        input.dataset.stored = input.value;
        show();
    });
    input.sporeShow = show;
    show();
}
/** A pick into a bound field: the whole value stored, its words shown, the square coloured. */
function sporeSet(input, full) {
    input.dataset.stored = full;
    input.value = sporePrintRead(full)?.words ?? full;
    input.sporeShow?.();
    input.dataset.picking = '1';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    delete input.dataset.picking;
}

/** The colour at a place of a group's field, as CSS. */
export function sporeColourAt(gr, fx, fy) {
    const mix = (a, b, f) => a + (b - a) * f;
    let d = gr.H[1] - gr.H[0];
    if (d > 180) d -= 360; else if (d < -180) d += 360;
    const H = (gr.H[0] + d * fy + 360) % 360;
    const L = mix(gr.L[0], gr.L[1], fx);
    const C = gr.chromaByRow ? mix(gr.C[0], gr.C[1], fy) : mix(gr.C[0], gr.C[1], fx);
    return `oklch(${L.toFixed(3)} ${C.toFixed(3)} ${H.toFixed(1)})`;
}

/** The words for a place: „dunkel rotbraun", „grünlich creme" — „mittel" and the middle row are left out. */
export function sporeWordsAt(gr, fx, fy) {
    if (gr.zones) {
        const step = gr.zones[Math.min(gr.zones.length - 1, Math.floor(fx * gr.zones.length))];
        const lean = fy < 1 / 3 ? gr.rows[0] : fy > 2 / 3 ? gr.rows[2] : '';
        return lean && step !== 'weiß' ? `${lean} ${step}` : step;
    }
    const light = LIGHT_WORDS[Math.min(4, Math.floor(fx * 5))];
    const tone = gr.rows[Math.min(2, Math.floor(fy * 3))];
    return light === 'mittel' ? tone : `${light} ${tone}`;
}

/* --- The pickers ------------------------------------------------------------ */

/**
 * A picker for a field: a quiet button at the field's end that opens a
 * panel in place under it — no window over the window. Returns both; the
 * window sets the button beside the field (09.11.2026: „ans
 * Ende des Input-Feldes … hinten dran und nicht in einer Zeile unten
 * drunter"), as tall as the field, and the panel below. `input` is the
 * field; whatever is picked is written into it and announced with an input
 * event, so the window marks itself unsaved as for a typed word.
 * @returns {{button: HTMLButtonElement, panel: HTMLElement}}
 */
export function traitPicker(kind, input, iconHtml, groups = null) {
    const open = document.createElement('button');
    open.type = 'button';
    /* A SIGN, NOT WORDS (12.11.2026: „ein einfaches Symbol …
       kein Text dazu, einfach, dass wir einen quadratischen Button haben,
       der nicht so viel Platz wegnimmt"): the hand that picks something up
       (icons.php, 'pick'), the words as its tooltip and its name. */
    open.className = 'button subtle trait-open';
    open.innerHTML = iconHtml('pick');
    const words = kind === 'spore_print' ? t('Farbe wählen') : t('Auswählen');
    open.title = words;
    open.setAttribute('aria-label', words);
    const panel = document.createElement('div');
    panel.className = 'trait-panel';
    panel.hidden = true;
    open.setAttribute('aria-expanded', 'false');
    open.addEventListener('click', () => {
        if (!panel.hidden) { close(); return; }
        panel.hidden = false;
        open.setAttribute('aria-expanded', 'true');
        fill();
        document.addEventListener('pointerdown', outside, true);
        document.addEventListener('keydown', escape, true);
    });
    /* A TICK OF OUR OWN DOES NOT REDRAW THE PANEL (27.11.2026, seen with
       the growth site's second group): the redraw below is for a word typed
       by hand, and it opens the FIRST group holding a pick — so a tick in
       the second group threw the panel back to the first. Our own
       announcement passes it by; the panel has already marked the tick. */
    let announcing = false;
    const announce = () => {
        announcing = true;
        try { input.dispatchEvent(new Event('input', { bubbles: true })); } finally { announcing = false; }
    };
    /* IT CLOSES WHEN ONE MOVES ON (09.11.2026: „Das Angeklickte
       soll übernommen werden. Und damit brauche ich das aufgeklappte Menü
       auch nicht mehr") — a press anywhere outside its field, or Escape.
       Not after each tick: odours are ticked several at a time. */
    const outside = (e) => { if (!open.closest('.field')?.contains(e.target)) close(); };
    const escape = (e) => { if (e.key === 'Escape') close(); };
    const close = () => {
        panel.hidden = true;
        open.setAttribute('aria-expanded', 'false');
        document.removeEventListener('pointerdown', outside, true);
        document.removeEventListener('keydown', escape, true);
    };
    const fill = () => {
        panel.textContent = '';
        if (kind === 'spore_print') sporePanel(panel, input, announce);
        else termsPanel(panel, input, groups ?? (kind === 'odour' ? ODOUR_GROUPS : TASTE_GROUPS), announce);
    };
    // A word typed by hand moves the chips along while the panel is open.
    input.addEventListener('input', () => { if (!panel.hidden && kind !== 'spore_print' && !announcing) fill(); });
    return { button: open, panel };
}

/*
 * THE TERMS BY GROUP, LIKE THE COLOURS (09.11.2026: „Beim Geruch
 * bitte genauso machen wie bei der Farbe. Wir haben oben Gruppenbuttons …
 * und wenn man die anklickt, bekommt man unten … die Unterpunkte zur
 * Auswahl"): a row of groups, and under it only the chosen group's terms.
 * A group holding a picked term says so with a dot, so a pick in another
 * group is not lost from sight. One group without a name (the tastes)
 * shows its terms straight away. No hints: the app is for people who know
 * what they are describing (the same evening: „für professionelle
 * Nutzung").
 */
/* THE TERMS ARE TICKS, NOT BUTTONS (09.11.2026: „Ich kann ja
   mehrere auswählen … Dann würde ich das auch so markieren als zum
   Anhaken"): a box per term in a flowing grid, as the plate's values ask
   which lines to show. Several may be ticked; the panel stays open for
   that and closes when one moves on (traitPicker). */
function termsPanel(panel, input, groups, announce) {
    const known = groups.flatMap(g => g.terms);
    const pickedNow = () => termsRead(input.value, known).picked;
    let open = groups.find(g => g.terms.some(term => pickedNow().includes(term))) ?? groups[0];
    /* THE GROUPS LOOK LIKE TABS, not like the chips beneath
       (the same evening: „wie Tabs aussehen lassen, weil sonst haben wir
       Buttons … und die erste Zeile der Gruppenbuttons kann ja auch
       umbrechen und zweizeilig sein und dann verliert man den Überblick"):
       two rows of equal chips read as one heap; a tab row over its own
       rule says „choose where", the chips under it „choose what". */
    const heads = document.createElement('div');
    heads.className = 'trait-tabs';
    heads.setAttribute('role', 'tablist');
    const chips = document.createElement('div');
    chips.className = 'trait-ticks';
    const marks = () => heads.querySelectorAll('.trait-tab').forEach((head, i) => {
        head.classList.toggle('has-pick', groups[i].terms.some(term => pickedNow().includes(term)));
    });
    const fill = () => {
        chips.textContent = '';
        for (const term of open.terms) {
            const row = document.createElement('label');
            row.className = 'choice-row';
            const box = document.createElement('input');
            box.type = 'checkbox';
            box.checked = pickedNow().includes(term);
            box.addEventListener('change', () => {
                input.value = termsToggle(input.value, term, box.checked, groups);
                marks();
                announce();
            });
            const words = document.createElement('span');
            words.textContent = t(term);
            row.append(box, words);
            chips.append(row);
        }
    };
    if (groups.length > 1 || groups[0].name) {
        for (const g of groups) {
            const head = document.createElement('button');
            head.type = 'button';
            head.className = 'trait-tab' + (g === open ? ' is-active' : '');
            head.setAttribute('role', 'tab');
            head.setAttribute('aria-selected', String(g === open));
            head.textContent = t(g.name);
            head.addEventListener('click', () => {
                open = g;
                heads.querySelectorAll('.trait-tab').forEach(h => {
                    h.classList.toggle('is-active', h === head);
                    h.setAttribute('aria-selected', String(h === head));
                });
                fill();
            });
            heads.append(head);
        }
        marks();
    }
    /* GROUPS AND TERMS IN ONE BOX (12.11.2026), so the stylesheet can set
       them side by side where the panel is narrow (.trait-terms,
       pointwindow.css) — a panel cannot ask its own width, a box in it can. */
    const box = document.createElement('div');
    box.className = 'trait-terms';
    if (heads.childElementCount) box.append(heads);
    box.append(chips);
    panel.append(box);
    fill();
}

function sporePanel(panel, input, announce) {
    const now = sporePrintRead(input.dataset.stored ?? input.value);
    let group = SPORE_GROUPS.find(g => g.name === now?.group) ?? SPORE_GROUPS[0];
    const groups = document.createElement('div');
    groups.className = 'trait-chips';
    const field = document.createElement('div');
    field.className = 'spore-field';
    const canvas = document.createElement('canvas');
    canvas.width = 600;
    canvas.height = 300;
    const marker = document.createElement('span');
    marker.className = 'spore-marker';
    marker.hidden = true;
    field.append(canvas, marker);
    /* THE TWO DIRECTIONS SAID APART (09.11.2026: „oben einen
       leicht grünlichen Stich und unten einen rötlichen Stich"): under the
       field only light and dark, beside it from top to bottom the lean of
       the rows. One line under it once read „hell · grünlich … dunkel ·
       rötlich", as if the corners were meant. */
    const axis = document.createElement('div');
    axis.className = 'spore-axis';
    const side = document.createElement('div');
    side.className = 'spore-side';
    const area = document.createElement('div');
    area.className = 'spore-area';
    area.append(side, field, document.createElement('span'), axis);
    /* THE GRADES OVER THE WHITE-TO-OCHRE FIELD (the same evening:
       „bei Weiß bis Ocker die Romagnesi-Farben einfach oben drüber setzen,
       dass man sie direkt auswählen kann. Und nicht einen eigenen Punkt"):
       a row of fourteen, the groups I–IV beneath as wide as their grades. A
       click writes „Weiß – Ocker: Romagnesi IIIa #FEDF90"; a pick on the
       field below lights up its nearest grade. */
    const scale = document.createElement('div');
    scale.className = 'spore-scale';
    const roman = document.createElement('div');
    roman.className = 'spore-roman';
    for (const [n, words] of [[2, 'I · ' + t('weiß')], [4, 'II · ' + t('creme')], [3, 'III · ' + t('ocker')], [5, 'IV · ' + t('gelb')]]) {
        const span = document.createElement('span');
        span.style.gridColumn = `span ${n}`;
        span.textContent = words;
        roman.append(span);
    }
    for (const [name, colour, printed, words] of ROMAGNESI) {
        const cell = document.createElement('button');
        cell.type = 'button';
        cell.className = 'spore-grade' + (printed ? '' : ' is-between') + (now?.words === 'Romagnesi ' + name ? ' is-active' : '');
        cell.dataset.grade = name;
        cell.title = words ? t(words) : t('zwischen den gedruckten Stufen');
        cell.innerHTML = `<span class="spore-grade-chip" style="background:${colour}"></span>`;
        cell.append(name);
        cell.addEventListener('click', () => {
            scale.querySelectorAll('.spore-grade').forEach(c => c.classList.toggle('is-active', c === cell));
            marker.hidden = true;
            near.hidden = true;
            sporeSet(input, `${group.name}: Romagnesi ${name} ${colour}`);
        });
        scale.append(cell);
    }
    // Beside a pick from white to ochre: the nearest grade, to compare with a key.
    const near = document.createElement('p');
    near.className = 'hint-text small spore-near';
    near.hidden = true;
    const g = canvas.getContext('2d', { willReadFrequently: true });
    const draw = () => {
        scale.hidden = roman.hidden = !group.romagnesi;
        near.hidden = true;
        const step = 6;
        for (let x = 0; x < canvas.width; x += step) {
            for (let y = 0; y < canvas.height; y += step) {
                g.fillStyle = sporeColourAt(group, x / (canvas.width - step), y / (canvas.height - step));
                g.fillRect(x, y, step, step);
            }
        }
        axis.innerHTML = '';
        for (const words of [t('hell'), t('dunkel')]) {
            const s = document.createElement('span');
            s.textContent = words;
            axis.append(s);
        }
        side.innerHTML = '';
        for (const words of [t(group.rows[0]), t(group.rows[2])]) {
            const s = document.createElement('span');
            s.textContent = words;
            side.append(s);
        }
    };
    for (const gr of SPORE_GROUPS) {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'trait-chip' + (gr === group ? ' is-active' : '');
        chip.innerHTML = `<span class="spore-dot" style="background:${gr.dot}"></span>`;
        /* The word in a span of its own: on the phone the dot stands alone
           (pointwindow.css, 12.11.2026: „im Handy-Modus den Text
           weg"), and the word goes on as tooltip and name. */
        const name = document.createElement('span');
        name.className = 'trait-chip-words';
        name.textContent = t(gr.name);
        chip.append(name);
        chip.title = t(gr.name);
        chip.setAttribute('aria-label', t(gr.name));
        chip.addEventListener('click', () => {
            group = gr;
            groups.querySelectorAll('.trait-chip').forEach(c => c.classList.toggle('is-active', c === chip));
            marker.hidden = true;
            draw();
        });
        groups.append(chip);
    }
    /* THE COLOUR TRAVELS WITH THE FINGER (12.11.2026: „die
       eigentliche Farbfläche, die sich dann oben verändert, ist bei mir am
       Handy schon nicht mehr sichtbar … ein Stück über dem Finger … eine
       Kachel, die mitfährt und die aktuelle Farbe anzeigt"): while the
       field is pressed — and under a mouse while it hovers — a tile above
       the point shows the colour there, large. Above, because a finger
       covers what is under it; higher for a finger than for a mouse. */
    const loupe = document.createElement('span');
    loupe.className = 'spore-loupe';
    loupe.hidden = true;
    field.append(loupe);
    const colourAt = (ev) => {
        const r = canvas.getBoundingClientRect();
        const fx = Math.min(1, Math.max(0, (ev.clientX - r.left) / r.width));
        const fy = Math.min(1, Math.max(0, (ev.clientY - r.top) / r.height));
        const [red, green, blue] = g.getImageData(Math.round(fx * (canvas.width - 1)), Math.round(fy * (canvas.height - 1)), 1, 1).data;
        return { fx, fy, hex: '#' + [red, green, blue].map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase() };
    };
    const loupeAt = (ev) => {
        const { fx, fy, hex } = colourAt(ev);
        loupe.hidden = false;
        loupe.classList.toggle('is-finger', ev.pointerType !== 'mouse');
        loupe.style.left = fx * 100 + '%';
        loupe.style.top = fy * 100 + '%';
        loupe.style.background = hex;
    };
    const pick = (ev) => {
        const { fx, fy, hex } = colourAt(ev);
        marker.hidden = false;
        marker.style.left = fx * 100 + '%';
        marker.style.top = fy * 100 + '%';
        const full = `${group.name}: ${sporeWordsAt(group, fx, fy)} ${hex}`;
        if (group.romagnesi) {
            const grade = romagnesiNearest(hex);
            near.textContent = t('Nächste Stufe nach Romagnesi: %s', grade);
            near.hidden = false;
            scale.querySelectorAll('.spore-grade').forEach(c => c.classList.toggle('is-active', c.dataset.grade === grade));
        }
        sporeSet(input, full);
    };
    let held = false;
    canvas.addEventListener('pointerdown', (e) => {
        held = true;
        try { canvas.setPointerCapture(e.pointerId); } catch { /* without, then */ }
        pick(e);
        loupeAt(e);
    });
    canvas.addEventListener('pointermove', (e) => {
        if (held) pick(e);
        if (held || e.pointerType === 'mouse') loupeAt(e);
    });
    const letGo = (e) => {
        held = false;
        // A mouse still over the field keeps its tile; a finger lifted takes it along.
        if (e.pointerType !== 'mouse' || e.type === 'pointerleave') loupe.hidden = true;
    };
    canvas.addEventListener('pointerup', letGo);
    canvas.addEventListener('pointercancel', letGo);
    canvas.addEventListener('pointerleave', (e) => { if (!held) letGo(e); });
    // No hint above it (09.11.2026): the people who use it know what a spore print is.
    panel.append(groups, scale, roman, area, near);
    draw();
    /* WORDS WHERE THEY FIT ON ONE LINE, DOTS WHERE THEY WOULD NOT
       (12.11.2026: „würde diese Leiste umbrechen, mit Text dann
       lieber ohne Text. Wenn sie nicht umbricht … den Text dran lassen …
       die hätte ich gerne nur in einer Zeile"). Measured, not guessed from
       a width: laid out with their words, and if any chip drops below the
       first, the row goes compact (.is-compact, pointwindow.css). Again
       whenever the panel's width changes — only then, or the row's own
       change of height would ask again and again. */
    const fit = () => {
        groups.classList.remove('is-compact');
        const chips = [...groups.children];
        if (chips.some(c => c.offsetTop !== chips[0].offsetTop)) groups.classList.add('is-compact');
    };
    let width = 0;
    new ResizeObserver(([entry]) => {
        const w = Math.round(entry.contentRect.width);
        if (w === width || w === 0) return;
        width = w;
        fit();
    }).observe(panel);
}
