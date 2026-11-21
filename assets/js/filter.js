/**
 * The point filter — ONCE, for the sidebar and for Organisieren.
 *
 * Until 14.08.2026 the two filtered differently: the sidebar searched all
 * fields, Organisieren only the title, and what "enthält nicht" means existed
 * solely in the containers' birth filter. Three answers to the same
 * question are two too many — whoever searched for something in Organisieren and
 * typed the same into the sidebar got different sets and looked for the
 * mistake in themselves.
 *
 * ONE ROW IS ONE CONDITION: a text and the question of whether it should
 * occur or precisely not. Several rows apply AND-linked — each must hold.
 * That is the linkage you have in your head while searching anyway
 * ("Buche, but not Fomes"). The OR lives INSIDE the row: a comma
 * separates alternatives ("Boletus edulis, Boletus aereus" — one of them
 * suffices). A selectable OR between the rows was considered and lost —
 * it would have needed a precedence rule and brackets to show it, while
 * the layout already says everything: alternatives sit side by side,
 * requirements stack.
 *
 * ACROSS ALL FIELDS, not only the title: the search runs over a point's
 * haystack (title, note, extra fields, map sheet, municipality, protected
 * area). Both views build it with the SAME function since 25.08.2026 —
 * pointHaystack in assets/js/haystack.js; Organisieren used to get it
 * ready-made from the server and no longer does. What remains is the PHP
 * twin (point_haystack in data.php), for the auto lists, which match where
 * no browser is: TWO PLACES THAT MUST ENUMERATE THE SAME FIELDS. Whoever
 * adds one there takes it along here; tests/haystack.php keeps both in
 * lockstep.
 */

import { icon } from './icons.js';
import { t } from './i18n.js';
import * as polygon from './polygon.js';
import { OPEN_MARKS, CERTAINTY_FIELD } from './identification.js';

/*
 * THE DROP-DOWN MENU IS FETCHED ONLY ON CLICK, not imported up top.
 *
 * dialogs.js attaches itself to the document on load (wireForms
 * on DOMContentLoaded). An import up here would drag that in — and then
 * this module could no longer be loaded in Node, where there is no
 * document. But that is exactly what tests/filter.mjs needs: the
 * computational parts of this module (matchTerms and the
 * serialization) are the yardstick against which the auto lists on the
 * server are measured, and a yardstick you cannot check is none.
 *
 * In the browser this costs nothing: dialogs.js has long been loaded by
 * the time someone taps the icon.
 */

/**
 * Fold the whole filter section behind a button.
 *
 * The call of 14.08.2026: in sidebar and Organisieren the section should
 * fold in and out. The reason is room — search rows, time range and "Nicht
 * bestimmt" otherwise stand above the point list permanently, and on the
 * phone that is half of what you see at a glance.
 *
 * THE BUTTON SAYS WHETHER SOMETHING APPLIES. A folded-away filter that
 * hides something would otherwise be the best way to think your points
 * have vanished: you see a short list and nowhere the cause. When
 * conditions are set, the button lights up and carries their count.
 *
 * FOLDED DOES NOT MEAN OFF: what applies keeps applying. The button folds
 * away only the controls, not the filter — to lift it you empty the rows.
 *
 * @param {object} o
 * @param {HTMLElement} o.button
 * @param {HTMLElement} o.content
 * @param {() => number} o.count  how many conditions currently apply
 * @param {?boolean} [o.openAtStart]  state from the last visit, if remembered
 * @param {(openCount: boolean) => void} [o.onToggle]  for remembering the fold state
 * @returns {{refresh: () => void}}
 */
export function wireCollapsible({ button, content, count, openAtStart = null,
                                 onToggle = null, clear = null }) {
    // REMEMBERED LIKE THE FILTER ITSELF (the call, 14.08.2026):
    // whoever had it folded away with the conditions set finds it folded
    // away again next time — otherwise a section you had just arranged for
    // yourself would stand open every time. Without a value
    // (openAtStart === null) the state from the markup stands.
    if (openAtStart !== null) content.hidden = !openAtStart;

    /* THE WAY OUT NEXT TO THE SIGN (26.08.2026: „einen ganz
       kleinen Button, um alle Filter einfach wieder zu löschen").

       The button says HOW MANY conditions apply; folded shut that is the
       only trace of them. Whoever wants to be rid of them had to unfold
       the section and empty five groups by hand — and the fifth is the
       one you forget, so the list stays short for a reason nobody can
       see any more.

       BUILT HERE, NOT IN THE MARKUP, so both surfaces get it from one
       place: the sidebar and Organisieren share this function, and a
       second copy in two templates would be the pair that drifts apart.
       It appears only where there is something to clear — an offer to
       delete nothing is noise. */
    let bin = null;
    if (clear) {
        bin = document.createElement('button');
        bin.type = 'button';
        bin.className = 'filter-chip filter-clear';
        bin.title = t('Alle Filter löschen');
        bin.setAttribute('aria-label', bin.title);
        bin.append(icon('delete'));
        bin.hidden = true;
        bin.addEventListener('click', clear);

        /* WRAPPED, NOT JUST APPENDED. .filter-header is a flex row with
           space-between — in Organisieren the columns chip sits at its
           right end. A bin inserted as a plain second child would drift
           into the middle of that row, halfway between the two things it
           belongs to neither of. As a pair the two stay together and the
           row keeps its ends. */
        const pair = document.createElement('span');
        pair.className = 'filter-header-pair';
        button.replaceWith(pair);
        pair.append(button, bin);
    }

    const refresh = () => {
        const n = count();
        if (bin) bin.hidden = n === 0;
        button.classList.toggle('is-active', n > 0);
        // The number stands NEXT TO the word, not in its place: "Filter 2"
        // reads as a state, a naked 2 as a riddle.
        button.querySelector('.filter-button-count').textContent = n > 0 ? String(n) : '';
        button.title = n > 0
            ? (n === 1 ? t('%s Bedingung gesetzt — zum Ansehen aufklappen', n)
                       : t('%s Bedingungen gesetzt — zum Ansehen aufklappen', n))
            : t('Punkte filtern');
    };

    button.addEventListener('click', () => {
        const open = content.hidden;
        content.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        onToggle?.(open);
    });
    button.setAttribute('aria-expanded', String(!content.hidden));

    refresh();
    return { refresh };
}

/**
 * Fold the single groups WITHIN the section, each at its label heading
 * (22.08.2026: the unfolded filter had grown past the capped
 * column in Organisieren, and the threat categories are a long list you rarely
 * need). One implementation for both views — the groups carry the same
 * data-group keys in the sidebar and in Organisieren, and the callers share one
 * settings key, so a group folded on the map is folded in Organisieren too:
 * the same rule as for the list tree (collapsedLists).
 *
 * FOLDED DOES NOT MEAN OFF, exactly as with the section as a whole: a
 * collapsed group whose condition applies shows its count on the heading
 * — the sidebar's light rule. Open, the controls themselves say it.
 *
 * THE FOLD STATE IS NOT REMEMBERED BUT COMPUTED (22.08.2026:
 * "noch nichts ausgewählt — alles eingeklappt außer Text; ist was
 * ausgewählt, automatisch ausgeklappt"): whenever the section opens,
 * reset() unfolds exactly Text and every group whose condition applies.
 * In between, folding is free and stays for the session — furniture,
 * not a setting. A stored fold would contradict the rule the moment a
 * condition changed behind it.
 *
 * @param {object} o
 * @param {HTMLElement} o.section  holds the .filter-group[data-group] blocks
 * @param {(key: string) => number} o.counts  active conditions of one group
 * @returns {{refresh: () => void, reset: () => void}}  reset on every
 *          opening of the section
 */
export function wireGroupFolds({ section, counts }) {
    const collapsed = new Set();
    const groups = [...section.querySelectorAll('.filter-group[data-group]')];

    for (const group of groups) {
        const title = group.querySelector('.filter-title');
        // The heading becomes a button between its two dashes — triangle,
        // text, count. The h4 keeps the lines (::before/::after), so the
        // look stays the one of every group heading.
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'filter-title-button';
        button.append(icon('fold-triangle', { class: 'collapse-triangle' }),
            document.createTextNode(title.textContent.trim()));
        const badge = document.createElement('span');
        badge.className = 'filter-title-count';
        button.append(badge);
        title.textContent = '';
        title.append(button);

        const toggle = () => {
            const key = group.dataset.group;
            collapsed.has(key) ? collapsed.delete(key) : collapsed.add(key);
            refresh();
        };
        button.addEventListener('click', toggle);
        /* THE WHOLE LINE FOLDS, NOT ONLY THE WORD (12.09.2026:
           „dass ich nicht nur auf den Text klicken muss, sondern auch
           hinten dran oder vorne dran auf den Strich"). The dashes are
           the h4's pseudo-elements, so a tap on them lands on the h4 and
           not on the button; the h4 hands it on. The button keeps the
           keyboard and the aria state — it stays THE control, the line
           is merely its larger target. Not two toggles for one tap: a
           click on the button bubbles up to the h4, and only what did
           not come through the button counts here. */
        title.addEventListener('click', (e) => {
            if (button.contains(e.target)) return;
            toggle();
        });
    }

    const refresh = () => {
        for (const group of groups) {
            const away = collapsed.has(group.dataset.group);
            const n = counts(group.dataset.group);
            group.classList.toggle('is-collapsed', away);
            const button = group.querySelector('.filter-title-button');
            button.setAttribute('aria-expanded', String(!away));
            button.querySelector('.collapse-triangle').classList.toggle('is-turned', !away);
            // The count only when folded — open, the checkmarks and rows
            // say it themselves, and a number beside them would count the
            // visible.
            button.querySelector('.filter-title-count').textContent
                = away && n > 0 ? String(n) : '';
        }
    };

    /** Text open, everything else only where its condition applies. */
    const reset = () => {
        collapsed.clear();
        for (const group of groups) {
            const key = group.dataset.group;
            if (key !== 'text' && counts(key) === 0) collapsed.add(key);
        }
        refresh();
    };

    reset();
    return { refresh, reset };
}

/** The two kinds of a row, with their icon and their color. */
export const TYPES = {
    contains:       { icon: 'check',      text: t('enthält'),       class: 'is-yes' },
    contains_not: { icon: 'close', text: t('enthält nicht'), class: 'is-no' },
};

/** A fresh, empty row. It stands on "enthält" — the common case. */
export function newRow() {
    return { type: 'contains', text: '' };
}

/**
 * Bring the rows into a shape you can trust: only the two known kinds,
 * text without fringe, empties fall out.
 *
 * Throwing away empty rows is at the same time the way to get rid of one:
 * deleting the text suffices. A trash can next to it would stand for
 * deleting a record — and there is none here.
 */
export function readTerms(raw) {
    const out = [];
    for (const z of Array.isArray(raw) ? raw : []) {
        const text = String(z?.text ?? '').trim();
        if (text === '') continue;
        out.push({
            type:  z?.type === 'contains_not' ? 'contains_not' : 'contains',
            text: text.slice(0, 100),
        });
    }
    return out;
}

/**
 * Does the haystack meet all conditions?
 *
 * @param {Array} terms  rows as readTerms yields them
 * @param {string} haystack      the point's haystack, ALREADY lowercased
 */
export function matchTerms(terms, haystack) {
    for (const z of terms) {
        // EMPTY ROWS DO NOT COUNT, and that is no side matter: at the end
        // of the list there is always an empty one so you can keep typing.
        // If it stood on "enthält nicht", the condition would be "contains
        // nothing anywhere" — and the map would be empty with one click,
        // without a word standing anywhere that you could delete.
        const text = String(z?.text ?? '').trim().toLowerCase();
        if (text === '') continue;
        /* COMMA MEANS OR, within the row (22.08.2026): „Boletus
           edulis, Boletus aereus" wants either of the two — across rows
           that wish is unspeakable, they demand everything at once. For
           „enthält nicht" the same split reads as: none of them. A row of
           bare commas has no alternatives and, like the empty row, does
           not count. THE SERVER TWIN in autolist_matches (data.php) splits
           identically — tests/filter.mjs and tests/autolists.php hold the
           two together. */
        const alternatives = text.split(',').map((t) => t.trim()).filter((t) => t !== '');
        if (alternatives.length === 0) continue;
        if (alternatives.some((t) => haystack.includes(t)) !== (z.type === 'contains')) return false;
    }
    return true;
}

// ---------------------------------------------------------------------------
// The place — drawn areas ("Ort", the call of 22.08.2026)
//
// A ROW IS AN AREA, and the areas are alternatives: a point counts when it
// lies in ONE of them — the same reading as the threat categories, one
// question ("where?") with several allowed answers. Drawing happens on the
// map (beginOutline in map.js); this module only keeps the list, the
// matching and the rows. The areas live in the settings bundle
// ('filterAreas') and are thereby the SAME on the map and in Organisieren — not
// merely the same control, the same state: Organisieren has no map and could
// never draw its own.
// ---------------------------------------------------------------------------

/** Bring stored areas into a shape you can trust — the readTerms of the
 *  place: only usable outlines survive. */
export function readAreas(raw) {
    return (Array.isArray(raw) ? raw : []).filter((corners) => polygon.isUsable(corners));
}

/**
 * Does the point lie in one of the areas? Empty means: no condition.
 *
 * THE SERVER TWIN is autolist_matches in data.php (point_in_polygon) —
 * tests/filter.mjs and tests/autolists.php hold the two together.
 */
export function areaMatches(areas, lat, lon) {
    if (!Array.isArray(areas) || areas.length === 0) return true;
    return areas.some((corners) => polygon.inOutline([lat, lon], corners));
}

/**
 * Draw the area rows: name, size, and the cross that takes one out of the
 * filter again. The cross, not the bucket — a filter condition is nothing
 * that exists as a record; taking it away is the same gesture as emptying
 * a search row.
 *
 * @param {HTMLElement} container
 * @param {Array} areas  changed in place
 * @param {() => void} onChange
 * @param {?() => void} onDraw  starts drawing on the map — null in Organisieren,
 *        which has no map; a quiet line then says where drawing lives.
 */
export function drawAreas(container, areas, onChange, onDraw = null) {
    container.textContent = '';

    areas.forEach((corners, index) => {
        const row = document.createElement('div');
        row.className = 'area-filter-row';

        const name = document.createElement('span');
        name.className = 'area-filter-name';
        name.textContent = t('Bereich %s', index + 1);

        const size = document.createElement('span');
        size.className = 'area-filter-size';
        size.textContent = polygon.readableArea(polygon.areaM2(corners));

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'symbol-button area-filter-remove';
        remove.title = t('Bereich %s aus dem Filter nehmen', index + 1);
        remove.setAttribute('aria-label', remove.title);
        remove.append(icon('close'));
        remove.addEventListener('click', () => {
            areas.splice(index, 1);
            drawAreas(container, areas, onChange, onDraw);
            onChange();
        });

        row.append(name, size, remove);
        container.append(row);
    });

    if (onDraw) {
        const draw = document.createElement('button');
        draw.type = 'button';
        draw.className = 'filter-chip area-filter-draw';
        // "Weiterer" from the second one on: the first press should say
        // what happens, every further one that it adds instead of replaces.
        draw.textContent = areas.length > 0 ? t('Weiterer Bereich …') : t('Bereich zeichnen …');
        draw.addEventListener('click', onDraw);
        container.append(draw);
    } else {
        const hint = document.createElement('p');
        hint.className = 'hint-text small';
        hint.textContent = t('Bereiche zeichnest du auf der Karte.');
        container.append(hint);
    }
}

// ---------------------------------------------------------------------------
// The place, part two: region, municipality, map sheet
// (22.08.2026). Three more answers to the same question — each OR within
// itself, AND against the others and against the drawn areas: whoever
// checks THU and draws an area in Hesse asks for the intersection, and
// that intersection is empty on purpose.
//
// ALL THREE OFFER ONLY WHAT THE POINTS KNOW. The region list and the
// municipality suggestions come from the stock ("Vorschlagsliste natürlich
// nur aus den Punkten, wo die Gemeinde schon feststeht") — a fixed list of
// sixteen federal states would show fourteen dead checkboxes and break on
// the first find in Tyrol. A point whose lookup has not run yet (region
// null) falls out of every checked selection: a filter that cannot ask
// "where?" must not answer "in".
//
// NOT REMEMBERED, unlike the drawn areas: an area stands visibly on the
// map as long as it filters, a checked code in a folded compartment would
// be exactly the hidden condition the RL comment warns about.
// ---------------------------------------------------------------------------

/**
 * The region codes written out (22.08.2026: „schreib sie voll
 * aus") — German names for the four countries where mapping actually
 * happens; every other code shows itself, which is truthful and typable.
 * The CODE stays the value everywhere (municipality_subdivision writes
 * it, the folders carry it); this table is display only, and a missing
 * entry costs nothing but polish.
 */
const REGION_NAMES = {
    // Germany
    BAW: 'Baden-Württemberg', BAY: 'Bayern', BLN: 'Berlin', BRB: 'Brandenburg',
    BRM: 'Bremen', HAM: 'Hamburg', HES: 'Hessen', MVP: 'Mecklenburg-Vorpommern',
    NDS: 'Niedersachsen', NRW: 'Nordrhein-Westfalen', RLP: 'Rheinland-Pfalz',
    SAL: 'Saarland', SAC: 'Sachsen', SAH: 'Sachsen-Anhalt',
    SLH: 'Schleswig-Holstein', THU: 'Thüringen',
    // Austria
    BGL: 'Burgenland', KTN: 'Kärnten', NOE: 'Niederösterreich',
    OOE: 'Oberösterreich', SBG: 'Salzburg', STM: 'Steiermark', TIR: 'Tirol',
    VBG: 'Vorarlberg', WIE: 'Wien',
    // Switzerland
    AAR: 'Aargau', APA: 'Appenzell Ausserrhoden', API: 'Appenzell Innerrhoden',
    BER: 'Bern', BSL: 'Basel-Landschaft', BST: 'Basel-Stadt', FRI: 'Freiburg',
    GEN: 'Genf', GLA: 'Glarus', GRA: 'Graubünden', JUR: 'Jura', LUZ: 'Luzern',
    NEU: 'Neuenburg', NIW: 'Nidwalden', OBW: 'Obwalden', SCH: 'Schaffhausen',
    SGA: 'St. Gallen', SOL: 'Solothurn', SWZ: 'Schwyz', THG: 'Thurgau',
    TIC: 'Tessin', URI: 'Uri', VAL: 'Wallis', VAU: 'Waadt', ZUG: 'Zug',
    ZUR: 'Zürich',
    // Italy — incl. the two autonomous provinces that replace TAA
    // (MUNICIPALITY_PARTS_FINE in municipality.php).
    ABR: 'Abruzzen', BAS: 'Basilikata', CAL: 'Kalabrien', CAM: 'Kampanien',
    EMR: 'Emilia-Romagna', FVG: 'Friaul-Julisch Venetien', LAZ: 'Latium',
    LIG: 'Ligurien', LOM: 'Lombardei', MAR: 'Marken', MOL: 'Molise',
    PIE: 'Piemont', PUG: 'Apulien', SAR: 'Sardinien', SDT: 'Südtirol',
    SIC: 'Sizilien', TAA: 'Trentino-Südtirol', TOS: 'Toskana',
    TRN: 'Trentino', UMB: 'Umbrien', VDA: 'Aostatal', VEN: 'Venetien',
};

/** The full name of a code — or the code itself, which at least is true. */
export function regionName(code) {
    // Translated at the accessor, not in the table: the table stays the
    // plain German record, and an unknown code passes through untouched.
    return REGION_NAMES[code] ? t(REGION_NAMES[code]) : code;
}

/** The distinct region codes of the stock, sorted — THU, BAY, TIR … as
 *  municipality_subdivision writes them (the codes ARE the project's
 *  region vocabulary; they name the folders too). */
export function regionsOf(points) {
    const set = new Set();
    for (const p of points) {
        if (p.region) set.add(p.region);
    }
    return [...set].sort((a, b) => a.localeCompare(b, 'de'));
}

/** The distinct municipalities of the stock, sorted. */
export function municipalitiesOf(points) {
    const set = new Set();
    for (const p of points) {
        if (p.municipality) set.add(p.municipality);
    }
    return [...set].sort((a, b) => a.localeCompare(b, 'de'));
}

/** Empty means: no condition. Otherwise the point's region must be among
 *  the checked ones — exact, it is a code. */
export function regionMatches(chosen, region) {
    if (!Array.isArray(chosen) || chosen.length === 0) return true;
    return region != null && chosen.includes(region);
}

export function municipalityMatches(chosen, municipality) {
    if (!Array.isArray(chosen) || chosen.length === 0) return true;
    return municipality != null && chosen.includes(municipality);
}

/** The map-sheet alternatives of a raw input: split at commas (comma is
 *  OR here as everywhere), all whitespace out — "4625 / 2" and "4625/2"
 *  mean the same sheet. */
export function mtbAlternatives(text) {
    return String(text ?? '').split(',')
        .map((t) => t.replace(/\s+/g, ''))
        .filter((t) => t !== '');
}

/**
 * Does the sheet fit? A PREFIX comparison on "mtb/quadrant" carries the
 * whole hierarchy by itself: "4625" means the sheet with all quarters,
 * "4625/2" the second quarter with all its sixteenths, "4625/24" exactly
 * that sixteenth. The server twin sits in autolist_matches (data.php).
 */
export function mtbMatches(text, mtb, quadrant) {
    const alternatives = mtbAlternatives(text);
    if (alternatives.length === 0) return true;
    if (!mtb) return false;
    const full = String(mtb) + '/' + String(quadrant ?? '');
    return alternatives.some((t) => full.startsWith(t));
}

/**
 * The region fold: a quiet button with triangle, beneath it one checkbox
 * per occurring code. Session fold state lives with the CALLER — it is
 * view furniture, not a setting.
 */
export function drawRegions(container, { available, chosen, open, onFold, onChange }) {
    container.textContent = '';
    /* LAZY OFFERS: on the map the points arrive AFTER this control is
       wired, and with the section remembered open, no reopening ever
       rebuilt it — live the fold stayed invisible (the maintainer found it).
       A function as `available` is resolved at render, and the fold
       click renders anew: whoever opens it sees the current stock. */
    const offers = typeof available === 'function' ? available() : available;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'place-fold' + (!open && chosen.length > 0 ? ' is-active' : '');
    button.setAttribute('aria-expanded', String(open));
    button.append(icon('fold-triangle', { class: 'collapse-triangle' + (open ? ' is-turned' : '') }),
        document.createTextNode(t('Region')));
    if (!open && chosen.length > 0) {
        const badge = document.createElement('span');
        badge.className = 'filter-title-count';
        badge.textContent = String(chosen.length);
        button.append(badge);
    }
    button.addEventListener('click', () => { onFold(!open); });
    container.append(button);

    if (!open) return;
    /* THE CHOSEN STAY LISTED even when the other place conditions push
       them out of the offers (the maintainer's walk of 22.08.2026: Hessen
       checked, then an area drawn in France — the facet stock empties,
       and without this union the checked Hessen would filter on,
       invisible and unremovable). */
    const shown = [...new Set([...offers, ...chosen])];
    if (shown.length === 0) {
        const hint = document.createElement('p');
        hint.className = 'hint-text small';
        hint.textContent = t('Noch bei keinem Punkt bestimmt.');
        container.append(hint);
        return;
    }
    const list = document.createElement('div');
    list.className = 'rl-selection';
    // Sorted by what the eye reads, not by the code behind it — with
    // written-out names an order by THU/TIR would look like disorder.
    const sorted = shown.sort((a, b) => regionName(a).localeCompare(regionName(b), 'de'));
    for (const code of sorted) {
        const label = document.createElement('label');
        label.className = 'filter-check';
        const box = document.createElement('input');
        box.type = 'checkbox';
        box.checked = chosen.includes(code);
        box.addEventListener('change', () => {
            box.checked ? chosen.push(code) : chosen.splice(chosen.indexOf(code), 1);
            onChange();
        });
        label.append(box, document.createTextNode(' ' + regionName(code)));
        list.append(label);
    }
    container.append(list);
}

/**
 * The municipality search: a field with the stock's suggestions
 * (native datalist — the browser brings the picker), every accepted one
 * becomes a row with its cross. Only KNOWN municipalities are accepted:
 * free text that matches nothing would filter everything away and look
 * like a fault.
 */
export function drawMunicipalities(container, { available, chosen, onChange }) {
    container.textContent = '';

    for (const name of chosen) {
        const row = document.createElement('div');
        row.className = 'area-filter-row';
        const text = document.createElement('span');
        text.textContent = name;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'symbol-button area-filter-remove';
        remove.title = t('„%s" aus dem Filter nehmen', name);
        remove.setAttribute('aria-label', remove.title);
        remove.append(icon('close'));
        remove.addEventListener('click', () => {
            chosen.splice(chosen.indexOf(name), 1);
            onChange();
        });
        row.append(text, remove);
        container.append(row);
    }

    const field = document.createElement('input');
    field.type = 'search';
    field.className = 'place-input';
    field.autocomplete = 'off';
    field.placeholder = t('Gemeinde …');
    field.setAttribute('aria-label', t('Nach Gemeinde filtern'));
    const listId = container.id + '-suggestions';
    field.setAttribute('list', listId);
    const suggestions = document.createElement('datalist');
    suggestions.id = listId;
    /* Lazy like the regions (see drawRegions), refreshed on FOCUS: the
       moment before typing is the last one at which the list can still
       become complete. */
    const offers = () => (typeof available === 'function' ? available() : available);
    const fillSuggestions = () => {
        suggestions.textContent = '';
        for (const name of offers()) {
            if (chosen.includes(name)) continue;
            const option = document.createElement('option');
            option.value = name;
            suggestions.append(option);
        }
    };
    fillSuggestions();
    field.addEventListener('focus', fillSuggestions);
    field.addEventListener('input', () => {
        const value = field.value.trim();
        if (offers().includes(value) && !chosen.includes(value)) {
            chosen.push(value);
            onChange();
        }
    });
    container.append(field, suggestions);
}

/** The distinct sheets of the stock ("4625/243"), sorted — for the
 *  map-sheet field's suggestions. */
export function mtbOf(points) {
    const set = new Set();
    for (const p of points) {
        if (p.mtb) set.add(String(p.mtb) + (p.mtb_quadrant ? '/' + p.mtb_quadrant : ''));
    }
    return [...set].sort();
}

/**
 * ONE test for the whole place — the four answers AND-linked. `except`
 * leaves one of them out: that is how each offer list computes its stock
 * from the points meeting all OTHER place conditions
 * (22.08.2026: with everything AND-linked, the offers must narrow along).
 * Never the facet's own condition — otherwise checking Hessen would
 * strike Thüringen from the very list you would widen it with.
 */
export function placeMatches({ areas, regions, municipalities, mtb }, p, except = null) {
    return (except === 'areas' || areaMatches(areas, p.lat, p.lon))
        && (except === 'regions' || regionMatches(regions, p.region))
        && (except === 'municipalities' || municipalityMatches(municipalities, p.municipality))
        && (except === 'mtb' || mtbMatches(mtb, p.mtb, p.mtb_quadrant));
}

/**
 * The map-sheet field. Wired ONCE — it must not be redrawn on its own
 * input, or the caret would fall out of the field mid-typing. The
 * suggestions are lazy like the municipalities': filled on focus, from
 * whatever `available` yields at that moment.
 */
export function drawMtb(container, { get, available = [], onChange }) {
    container.textContent = '';
    const field = document.createElement('input');
    field.type = 'search';
    field.className = 'place-input';
    field.autocomplete = 'off';
    field.placeholder = t('MTB, z. B. 4625/2 …');
    field.setAttribute('aria-label', t('Nach Messtischblatt filtern'));
    field.title = t('Blatt, Viertel oder Sechzehntel — Komma heißt oder');
    field.value = get();
    const listId = container.id + '-suggestions';
    field.setAttribute('list', listId);
    const suggestions = document.createElement('datalist');
    suggestions.id = listId;
    const fillSuggestions = () => {
        suggestions.textContent = '';
        const offers = typeof available === 'function' ? available() : available;
        for (const sheet of offers) {
            const option = document.createElement('option');
            option.value = sheet;
            suggestions.append(option);
        }
    };
    fillSuggestions();
    field.addEventListener('focus', fillSuggestions);
    field.addEventListener('input', () => onChange(field.value));
    container.append(field, suggestions);
}

/** For the address bar and the hidden form fields: "a~Buche|n~Fomes". */
export function writeTerms(terms) {
    return readTerms(terms)
        .map((z) => (z.type === 'contains' ? 'a~' : 'n~') + z.text.replaceAll('|', ' '))
        .join('|');
}

/** And back. The unknown falls out silently — it is a view, not a contract. */
export function termsFromText(raw) {
    const out = [];
    for (const part of String(raw ?? '').split('|')) {
        const text = part.slice(2).trim();
        if (text === '' || (part[0] !== 'a' && part[0] !== 'n')) continue;
        out.push({ type: part[0] === 'n' ? 'contains_not' : 'contains', text });
    }
    return out;
}

/**
 * Draw the rows.
 *
 * THE LAST ROW IS ALWAYS EMPTY, and that is where the list grows: whoever
 * types into the empty one gets the next below it. An "add condition"
 * beneath would be a button you first have to look for and that costs
 * permanent room in the narrow sidebar — here the room comes into being
 * exactly when it is needed.
 *
 * @param {HTMLElement} container
 * @param {Array} terms  changed in place
 * @param {() => void} onChange
 * @param {string} placeholder  label of the first row
 */
export function drawTermRows(container, terms, onChange, placeholder = t('Punkte filtern …')) {
    // Always exactly one empty row at the end — no more, no less.
    while (terms.length > 0 && terms.at(-1).text === '' && terms.length > 1
           && terms.at(-2).text === '') {
        terms.pop();
    }
    if (terms.length === 0 || terms.at(-1).text !== '') {
        terms.push(newRow());
    }

    // Which row has the caret, so a redraw does not take it away — the
    // list is rebuilt on every keystroke.
    const active = container.querySelector('input:focus');
    const activeIndex = active ? Number(active.dataset.index) : -1;
    const activePos = active ? active.selectionStart : 0;

    container.textContent = '';

    terms.forEach((row, index) => {
        const rowEl = document.createElement('div');
        rowEl.className = 'filter-row';

        // The icon BEFORE the field is display AND handle: it says whether
        // the row demands or excludes, and a click folds open the choice
        // (the call, 14.08.2026) — first the button, then the
        // field, as with every input that has a toggle in front. Two
        // buttons side by side or a select box would have eaten, in the
        // narrow sidebar, half of the row that belongs to the text.
        const kind = TYPES[row.type] ?? TYPES.contains;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'filter-row-type ' + kind.class;
        button.title = t('%s — zum Umstellen anklicken', kind.text);
        button.setAttribute('aria-label', t('Bedingung: %s', kind.text));
        button.append(icon(kind.icon));
        button.addEventListener('click', async () => {
            const { popupMenu } = await import('./dialogs.js');
            const choice = await popupMenu({
                anchor: button,
                entries: [
                    { value: 'contains',       label: t('enthält') },
                    { value: 'contains_not', label: t('enthält nicht') },
                ],
            });
            if (choice === null || choice === row.type) return;
            row.type = choice;
            drawTermRows(container, terms, onChange, placeholder);
            onChange();
        });
        rowEl.append(button);

        const field = document.createElement('input');
        field.type = 'search';
        field.className = 'filter-row-text';
        field.autocomplete = 'off';
        field.value = row.text;
        field.dataset.index = String(index);
        field.placeholder = index === 0 ? placeholder : t('und …');
        // The tooltip may say it in full — the short version stands on
        // the page (the hint below the rows).
        field.title = t('Mehrere Begriffe, mit Komma getrennt, gelten als oder — Zeilen untereinander als und.');
        field.addEventListener('input', () => {
            row.text = field.value;
            drawTermRows(container, terms, onChange, placeholder);
            onChange();
        });
        rowEl.append(field);

        container.append(rowEl);
    });

    /* THE CONVENTION ON THE PAGE, not only in the tooltip
       (22.08.2026 — a tooltip is only found by whoever already suspects
       something). One quiet line under the rows; it comes into being
       here with them, so both views carry it and neither can forget it.
       No species example in it: the map is neutral. */
    const hint = document.createElement('p');
    hint.className = 'hint-text small';
    hint.textContent = t('Durch Komma Getrenntes gilt als oder.');
    container.append(hint);

    if (activeIndex >= 0) {
        const again = container.querySelector(`input[data-index="${activeIndex}"]`);
        if (again) {
            again.focus();
            again.setSelectionRange(activePos, activePos);
        }
    }
}

// ---------------------------------------------------------------------------
// The systematic ranks above the genus (26.08.2026)
// ---------------------------------------------------------------------------

/**
 * The ranks in the order one reads them — key as it travels, German as it
 * is shown. The twin is TAXONOMY_RANKS in includes/taxonomy.php; one word
 * runs end to end, from the backbone column to the label on the field.
 *
 * THE GENUS CAME LAST, AND ONLY AFTER AN OBJECTION
 * (26.08.2026). It was left out on the grounds that the title of every find
 * already carries it — true, and beside the point. What the text row
 * cannot do is OFFER: it matches a substring, so „Boletus" also finds
 * Pseudoboletus, and it never says which genera are in the stock at all.
 * As a rank it is a picked list, it narrows what stands above it, and
 * choosing one fills all of that in.
 */
export const TAXONOMY_RANKS = [
    ['kingdom', t('Reich')],
    ['phylum',  t('Stamm')],
    ['class',   t('Klasse')],
    ['order',   t('Ordnung')],
    ['family',  t('Familie')],
    ['genus',   t('Gattung')],
];

/**
 * Up to this many offers a rank gets a drop-down, above it a text field
 * with suggestions („wo die Auswahl nicht allzu groß ist, wo es
 * nur ein paar gibt, können wir auch einen Dropdown machen").
 *
 * DECIDED ON WHAT IS LEFT, not on the whole stock — corrected 26.08.2026.
 * It was the other way round first, out of a fear that turned out to be
 * unfounded: a field would change its KIND while one types in it. It
 * cannot. A rank's offer is computed from the OTHER conditions, never from
 * its own value, so typing into „Klasse" leaves the class list untouched.
 * The kind only changes when a DIFFERENT rank changes — which is exactly
 * the moment one expects the rows to be redrawn.
 *
 * And the narrowed count is the whole point (the maintainer's own picture):
 * pick the kingdom, and the phylum has three left — a list. Pick the
 * phylum, and the class may have four — a list again. Where it stays
 * large, a text field whose suggestions are already narrowed to what the
 * ranks above allow.
 *
 * TWENTY, AND THE PHONE IS THE REASON IT IS NOT SMALLER (the maintainer asked
 * on 26.08.2026 how it behaves there). One expects the opposite: that a
 * drop-down is the desktop control and a text field the mobile one. It is
 * the other way round. A <select> opens the OPERATING SYSTEM'S own picker
 * — a wheel on iOS, a sheet on Android — and no keyboard appears at all.
 * A text field with a suggestion list pulls the keyboard up AND wants to
 * show the list above it, and the two then fight over the same half of
 * the screen. For twenty entries one scrolls with a thumb, the picker is
 * the quieter way.
 *
 * Twenty rows also fit in a desktop drop-down without a scrollbar. Above
 * that, typing three letters beats scrolling, and the field wins.
 */
export const TAXONOMY_DROPDOWN_MAX = 20;

/** Does this point's genus satisfy the chosen ranks? */
export function taxonomyMatches(conditions, ranks) {
    const asked = TAXONOMY_RANKS
        .map(([key]) => [key, String(conditions?.[key] ?? '').trim()])
        .filter(([, value]) => value !== '');
    if (asked.length === 0) return true;
    // A genus we cannot resolve is in no family — the same rule the height
    // band follows for a point without an elevation.
    if (!ranks) return false;
    return asked.every(([key, value]) =>
        String(ranks[key] ?? '').toLowerCase() === value.toLowerCase());
}

/** What a set condition reads as — for the group heading and the title. */
export function taxonomyLabel(conditions) {
    const set = TAXONOMY_RANKS
        .filter(([key]) => String(conditions?.[key] ?? '').trim() !== '')
        .map(([key, german]) => `${german}: ${conditions[key]}`);
    return set.length === 0 ? '' : set.join(' · ');
}

/**
 * Fill in the ranks ABOVE the chosen one and say which those were.
 *
 * WHAT LIES ABOVE FOLLOWS BY ITSELF (26.08.2026: „ich kann ja
 * nicht Ascomycota wählen und das Reich Plantae").
 *
 * A rank above a chosen one is not a second question — it is already
 * answered. Whoever picks the phylum Ascomycota has picked the kingdom
 * Fungi with it, and leaving that open invites a contradiction that yields
 * an empty map and no explanation.
 *
 * AND IT NEEDS NO CONNECTION, contrary to what one would expect. The ranks
 * hang on the POINTS, which are loaded anyway — the stock itself says what
 * Ascomycota belongs to. No backbone on the device, no request: if a rank
 * leaves exactly ONE value over among the finds that satisfy the explicit
 * conditions, that value is the answer. In the woods it therefore works
 * exactly as at the desk.
 *
 * ONLY UPWARDS. Downwards the same reasoning would be wrong: a kingdom
 * does not determine a family, and a stock that happens to hold only one
 * Fungi family would silently narrow to it.
 *
 * ITS OWN FUNCTION, not a passage inside the drawing: this is arithmetic
 * over the stock, and the test can run it without a browser.
 *
 * @param conditions mutated in place; carries its own _derived list
 * @param offers     (rank, others) => string[]
 * @return Set of the ranks this filled in
 */
export function taxonomyDerive(conditions, offers) {
    /* WHAT WAS DERIVED LAST TIME IS RELEASED FIRST. Otherwise clearing the
       phylum would leave „Fungi" standing as a condition nobody ever chose
       — a filter one cannot see the reason for is the worst kind. An
       EXPLICIT choice is never touched by this: only what this function
       wrote down itself is listed in _derived. */
    for (const key of conditions._derived ?? []) {
        conditions[key] = '';
    }

    let deepest = -1;
    TAXONOMY_RANKS.forEach(([key], index) => {
        if (String(conditions[key] ?? '').trim() !== '') deepest = index;
    });

    const derived = new Set();
    if (deepest > 0) {
        // The explicit conditions are the ones AT OR BELOW the deepest —
        // everything above is up for derivation and must not narrow itself.
        const explicit = Object.fromEntries(TAXONOMY_RANKS
            .filter((_, index) => index >= deepest)
            .map(([key]) => [key, conditions[key] ?? '']));
        for (let index = 0; index < deepest; index++) {
            const [key] = TAXONOMY_RANKS[index];
            const only = offers(key, explicit);
            if (only.length === 1) {
                conditions[key] = only[0];
                derived.add(key);
            }
        }
    }
    conditions._derived = [...derived];
    return derived;
}

/**
 * Five rows, one per rank.
 *
 * @param container  where it goes
 * @param conditions the state object, mutated in place
 * @param offers     (rank, others) => string[] — what is on offer for this
 *                   rank when the OTHER conditions apply. Faceted like the
 *                   place filter: choosing a kingdom narrows the classes.
 * @param onChange   called after every change
 */
export function drawTaxonomy(container, conditions, offers, onChange) {
    const derived = taxonomyDerive(conditions, offers);

    // activeElement, not :focus — the latter matches nothing while the
    // window itself is not focused (DevTools open, the pane in the
    // background), and the caret would be lost on every keystroke.
    const focused = container.contains(document.activeElement) ? document.activeElement : null;
    const focusedRank = focused?.dataset.rank ?? null;
    const caret = focused && focused.tagName === 'INPUT' ? focused.selectionStart : null;

    container.textContent = '';

    for (const [key, german] of TAXONOMY_RANKS) {
        const row = document.createElement('div');
        row.className = 'field taxonomy-row';

        const label = document.createElement('label');
        label.textContent = german;
        label.htmlFor = container.id + '-' + key;
        row.append(label);

        // What the OTHER conditions leave over for this rank.
        const others = Object.fromEntries(
            TAXONOMY_RANKS.filter(([k]) => k !== key)
                .map(([k]) => [k, conditions[k] ?? '']));
        const list = offers(key, others);

        // The kind follows what is LEFT — see TAXONOMY_DROPDOWN_MAX.
        let control;
        if (list.length <= TAXONOMY_DROPDOWN_MAX) {
            control = document.createElement('select');
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = list.length === 0 ? t('— nichts im Bestand —') : t('— alle —');
            control.append(empty);
            for (const value of list) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                control.append(option);
            }
            /* A CHOSEN VALUE THAT IS NO LONGER ON OFFER STAYS SELECTABLE,
               otherwise narrowing would silently drop it: pick a family,
               then a kingdom that excludes it, and the family would vanish
               from the box while still filtering. It stands there marked,
               so one can see what to undo. */
            const chosen = String(conditions[key] ?? '');
            if (chosen !== '' && !list.includes(chosen)) {
                const stale = document.createElement('option');
                stale.value = chosen;
                stale.textContent = chosen;
                stale.title = t('Passt zu den anderen Bedingungen nicht mehr');
                control.append(stale);
            }
            control.value = chosen;
        } else {
            control = document.createElement('input');
            control.type = 'search';
            control.autocomplete = 'off';
            control.placeholder = `${german} …`;
            control.value = String(conditions[key] ?? '');
            /* OUR OWN LIST UNDER THE FIELD, not a <datalist>
               (19.09.2026: „während der Eingabe einer Gattung kannst du ja
               auch Vorschläge machen, dass man nicht den ganzen Namen
               eingeben muss"). A datalist stood here and did exactly that
               on the desk — on the phone it is the browser's affair: iOS
               shows it as a strip over the keyboard that nobody reads as a
               list, and Android as it pleases. The same list the title
               field draws (species-suggestions), so it looks and behaves
               the same everywhere: what matches at the START comes first,
               then what CONTAINS the typed text, a tap fills the field. */
            const suggestions = document.createElement('ul');
            suggestions.className = 'species-suggestions taxonomy-suggestions';
            suggestions.hidden = true;
            row.append(suggestions);
            row.dataset.offers = JSON.stringify(list.slice(0, 500));
        }

        control.id = container.id + '-' + key;
        control.dataset.rank = key;
        if (derived.has(key)) {
            /* LOCKED, NOT HIDDEN. It says what the choice below it means,
               and that is worth a row: whoever reads „Reich: Fungi" over
               „Stamm: Ascomycota" has learnt something. Undone by clearing
               the deeper rank — which is where the decision actually
               sits. */
            control.disabled = true;
            control.dataset.derived = '1';
            control.title = t('Ergibt sich aus der Wahl darunter');
        } else {
            control.title = list.length === 0
                ? t('Kein %s im Bestand — mit den anderen Bedingungen zusammen', german)
                : t('%s zur Auswahl', list.length);
        }

        control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', () => {
            conditions[key] = control.value.trim();
            onChange();
        });

        row.append(control);
        container.append(row);
    }

    // The caret back where it was — the whole block is rebuilt on every
    // keystroke, because every change renarrows the other four offers.
    if (focusedRank) {
        const again = container.querySelector(`[data-rank="${focusedRank}"]`);
        if (again) {
            again.focus();
            if (caret !== null && again.tagName === 'INPUT') {
                again.setSelectionRange(caret, caret);
                taxonomySuggest(again, conditions, onChange);
            }
        }
    }
}

/**
 * The suggestions under a rank's text field — drawn from the row's offers
 * against what is typed. Called on every input (through the redraw, which
 * rebuilds the row) and on focus; hidden on blur, Escape and an exact hit.
 */
function taxonomySuggest(control, conditions, onChange) {
    const row = control.closest('.taxonomy-row');
    const list = row?.querySelector('.taxonomy-suggestions');
    if (!list) return;
    const typed = control.value.trim().toLowerCase();
    const offers = JSON.parse(row.dataset.offers ?? '[]');
    let hits = [];
    if (typed !== '') {
        const starts = offers.filter(v => v.toLowerCase().startsWith(typed));
        const within = offers.filter(v => !v.toLowerCase().startsWith(typed) && v.toLowerCase().includes(typed));
        hits = [...starts, ...within].slice(0, 12);
    }
    // The exact name already stands in the field — nothing left to offer.
    if (hits.length === 1 && hits[0].toLowerCase() === typed) hits = [];
    list.textContent = '';
    for (const value of hits) {
        const item = document.createElement('li');
        item.textContent = value;
        // pointerdown first, so the field does not lose its focus (and
        // its list) before the click arrives.
        item.addEventListener('pointerdown', (e) => e.preventDefault());
        item.addEventListener('click', () => {
            control.value = value;
            conditions[control.dataset.rank] = value;
            list.hidden = true;
            onChange();
        });
        list.append(item);
    }
    list.hidden = hits.length === 0;
}

/* THE KEYS OF THE LIST, bound once for every taxonomy block on the page:
   arrows walk it, Enter takes the marked entry, Escape folds it. On the
   document, because the rows are rebuilt on every keystroke and a handler
   on the field would go with them. GUARDED, because
   tests/filter.mjs imports this module in Node, where there is no
   document. */
if (typeof document !== 'undefined') {
document.addEventListener('keydown', (e) => {
    const control = e.target;
    if (!(control instanceof HTMLInputElement) || !control.closest('.taxonomy-row')) return;
    const list = control.closest('.taxonomy-row').querySelector('.taxonomy-suggestions');
    if (!list || list.hidden) return;
    const items = [...list.children];
    const at = items.findIndex(i => i.classList.contains('is-active'));
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const next = e.key === 'ArrowDown' ? Math.min(at + 1, items.length - 1) : Math.max(at - 1, 0);
        items.forEach((i, n) => i.classList.toggle('is-active', n === next));
        items[next]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter' && at >= 0) {
        e.preventDefault();
        items[at].click();
    } else if (e.key === 'Escape') {
        list.hidden = true;
    }
});
document.addEventListener('focusin', (e) => {
    const control = e.target;
    if (control instanceof HTMLInputElement && control.closest('.taxonomy-row')) {
        // The block knows its conditions only through the redraw; on a
        // plain focus the list shows what the row already holds.
        const row = control.closest('.taxonomy-row');
        const list = row.querySelector('.taxonomy-suggestions');
        if (list && list.children.length > 0 && control.value.trim() !== '') list.hidden = false;
    }
});
document.addEventListener('focusout', (e) => {
    const list = e.target?.closest?.('.taxonomy-row')?.querySelector('.taxonomy-suggestions');
    if (list) list.hidden = true;
});
}

// ---------------------------------------------------------------------------
// Elevation (26.08.2026)
// ---------------------------------------------------------------------------

/**
 * The upper end of the slider — and it means "and above", not "at most".
 *
 * Germany's highest point is 2962 m, and above roughly this line nobody
 * looks for mushrooms any more („da oben braucht man nicht mehr
 * nach Pilzen Ausschau halten"). But a find in the Alps must not fall out
 * silently because the scale ends here: with the upper handle at the top
 * the condition is open-ended, and the label says so.
 */
export const ELEVATION_MAX = 2500;

/** A hundred metres is the smallest step that says anything about a stand. */
export const ELEVATION_STEP = 100;

/**
 * Does the point lie in the chosen band?
 *
 * ONE RULE FOR THREE SURFACES — the sidebar, Organisieren and the server's
 * auto lists (elevation_matches in data.php is the twin). A point WITHOUT
 * an elevation drops out as soon as the filter applies, exactly as a point
 * without a find time drops out of the time filter: a band is a statement
 * about a number, and "unknown" is not in any band.
 */
export function elevationMatches(range, elevation) {
    const from = Number(range?.from) || 0;
    const rawTo = Number(range?.to);
    const to = Number.isFinite(rawTo) ? rawTo : ELEVATION_MAX;
    // Open at the top, and thus no condition there.
    const openEnd = to >= ELEVATION_MAX;
    if (from <= 0 && openEnd) return true;

    if (elevation === null || elevation === undefined || elevation === '') return false;
    const m = Number(elevation);
    if (!Number.isFinite(m)) return false;
    return m >= from && (openEnd || m <= to);
}

/** How the chosen band reads — also the group's own heading suffix. */
export function elevationLabel(range) {
    const from = Number(range?.from) || 0;
    const rawTo = Number(range?.to);
    const to = Number.isFinite(rawTo) ? rawTo : ELEVATION_MAX;
    const openEnd = to >= ELEVATION_MAX;
    // The span beside „Alle Höhen" (19.09.2026, as with the year
    // band): „0 – ∞ m" — the top is open, and „ab 2500 m" in this spot
    // read as if the band ended there (same day: „das
    // verwirrt"). The scale's number belongs to the handle, not here.
    if (from <= 0 && openEnd) return t('Alle Höhen') + ' · 0 – ∞ m';
    if (from <= 0) return t('bis %s m', to);
    if (openEnd) return t('ab %s m', from);
    return `${from}–${to} m`;
}

/**
 * Two handles on one track.
 *
 * WHY TWO NATIVE FIELDS AND NOT A DRAWN ONE. HTML has no range with two
 * handles, so one either overlays two <input type="range"> or paints the
 * whole thing oneself and listens to pointer events. The overlay wins on
 * the thing that is hardest to add later: the keyboard. Each handle is a
 * real field, reachable with Tab, movable with the arrow keys, and it
 * announces its value — a painted slider would have to be taught all of
 * that, and usually is not.
 *
 * THE TRAP OF THE OVERLAY is that the upper field swallows every click,
 * including those meant for the lower handle. Hence pointer-events: none
 * on the fields and auto on the THUMBS alone (app.css) — the track below
 * them stays visible, and each handle catches only its own grip.
 *
 * AND THE SECOND TRAP: with both handles on the same spot, only the one on
 * top can be moved, and the band can never be opened again. Which of the
 * two lies on top is therefore decided anew after every change — whichever
 * still has room in front of it.
 */
export function drawElevation(container, range, onChange) {
    container.textContent = '';

    const slider = document.createElement('div');
    slider.className = 'band-slider';

    const track = document.createElement('div');
    track.className = 'band-track';
    const span = document.createElement('div');
    span.className = 'band-span';
    track.append(span);

    const make = (value, label) => {
        const el = document.createElement('input');
        el.type = 'range';
        el.className = 'band-handle';
        el.min = '0';
        el.max = String(ELEVATION_MAX);
        el.step = String(ELEVATION_STEP);
        el.value = String(value);
        el.setAttribute('aria-label', label);
        return el;
    };
    // Clamped on the way in too: a stored band from before this rule, or
    // a hand-written one, must not put the two grips on the same spot —
    // with the same exception at the ceiling.
    const startTo = Number.isFinite(Number(range.to)) ? Number(range.to) : ELEVATION_MAX;
    const wantFrom = Number(range.from) || 0;
    const startFrom = (wantFrom >= ELEVATION_MAX && startTo >= ELEVATION_MAX)
        ? ELEVATION_MAX
        : Math.max(0, Math.min(wantFrom, startTo - ELEVATION_STEP));
    const fromEl = make(startFrom, t('Untere Höhe'));
    const toEl = make(Math.max(startTo, ELEVATION_STEP), t('Obere Höhe'));

    const caption = document.createElement('p');
    caption.className = 'hint-text small band-label';

    const paint = () => {
        const from = Number(fromEl.value);
        const to = Number(toEl.value);
        span.style.left = (from / ELEVATION_MAX * 100) + '%';
        span.style.right = (100 - to / ELEVATION_MAX * 100) + '%';
        caption.textContent = elevationLabel({ from, to });
        // Whoever still has room in front of them lies on top — otherwise
        // two handles on the same spot lock each other for good.
        // At the right end the lower grip needs to be on top, otherwise
        // the upper one covers it and the band cannot be widened again.
        const atEnd = from >= ELEVATION_MAX - ELEVATION_STEP;
        fromEl.style.zIndex = atEnd ? '4' : '2';
        toEl.style.zIndex = atEnd ? '2' : '4';
        fromEl.title = caption.textContent;
        toEl.title = caption.textContent;
    };

    const changed = (moved) => {
        /* ALWAYS ONE STEP APART (26.08.2026: „beide Griffe
           sollten nie auf demselben Punkt sein"). A band of zero width is
           not a band — it would select the one contour line and nothing
           else, and on the track the two grips would lie exactly on top of
           each other. The one being dragged keeps its value, the other
           gives way; only at the very end of the scale does the dragged
           one stop, because there is nowhere left to push to.

           This also takes the ground from the trap the comment above
           describes: handles that can never coincide can never lock each
           other. The z-index rule stays anyway — at the smallest distance
           the two thumbs still overlap by a few pixels, and then it
           decides which of them the finger catches. */
        let from = Number(fromEl.value);
        let to = Number(toEl.value);

        /* ONE EXCEPTION, AT THE TOP (26.08.2026: „außer bei
           über 2500"). Both grips at the ceiling is not a band of zero
           width but the OPEN END — „ab 2500 m", everything above the
           scale. That is a real answer and the only one the right edge
           can give, so there the two may meet. */
        const openTop = from >= ELEVATION_MAX && to >= ELEVATION_MAX;

        if (!openTop && to - from < ELEVATION_STEP) {
            if (moved === fromEl) {
                to = Math.min(ELEVATION_MAX, from + ELEVATION_STEP);
                from = Math.min(from, to - ELEVATION_STEP);
            } else {
                from = Math.max(0, to - ELEVATION_STEP);
                to = Math.max(to, from + ELEVATION_STEP);
            }
            fromEl.value = String(from);
            toEl.value = String(to);
        }

        range.from = from;
        range.to = to;
        paint();
        onChange();
    };

    fromEl.addEventListener('input', () => changed(fromEl));
    toEl.addEventListener('input', () => changed(toEl));

    slider.append(track, fromEl, toEl);
    container.append(slider, caption);
    paint();
}

// ---------------------------------------------------------------------------
// The threat status — Red List of Germany
//
// EVERY CATEGORY CHECKABLE INDIVIDUALLY (the call, 15.08.2026). A
// collective checkbox "Red List species only" was on the table and did not
// make it: where the line between "gefährdet" and "nicht gefährdet" runs
// is a matter of expert determination — V stands on the early-warning list
// and counts as precisely NOT threatened, R is extremely rare without
// threat. One checkbox would have hidden that line inside the program; the
// checkmarks show it.
//
// NOT AN EITHER-OR QUESTION: you check what you want to see, several at
// once. Nothing checked means everything — not nothing. That is the same
// reading as with the lists in the sidebar.
//
// THE CLASSIFICATION IS NOT ON THE POINT but on the species (species.php,
// arten_gefaehrdung). The server attaches it on load — it is thus always
// as current as the Red List, instead of freezing at the state of the find
// day.
// ---------------------------------------------------------------------------

/**
 * The whole scale, in the order of the official lists.
 *
 * COMPLETE, even though not everything is filtered by: it is also the name
 * table for the display — a find with "D" should carry "Daten unzureichend"
 * in the title of the point window, whether or not you can filter by it.
 *
 * displayOnly MEANS: no checkbox. Three categories carry none
 * (the decision, 15.08.2026):
 *
 *   D, nb   say nothing about the threat, only that nobody has assessed
 *           it.
 *   *       "ungefährdet" — AND HERE THE REASON IS A DIFFERENT ONE: the
 *           DGfM list does not award it at all. Of 39,248 fungus names not
 *           a single one carries a star; only lichens (588) and plants
 *           (2,153) know it. A checkbox that in principle never matches
 *           for this map's main group looks like a bug — and for a fungus
 *           without an entry, "ungefährdet" cannot be told apart from
 *           "nicht bewertet" anyway.
 *
 * All three nevertheless stay here: they occur in the data and are
 * DISPLAYED on the find — you just cannot filter by them.
 *
 * 'meaning' IS THE LEVEL SAID IN PLAIN WORDS (01.10.2026: „die
 * Rote-Liste-Stufen in der ausführlichen Info ausschreiben,
 * ausformulieren"), for the point window's facts table. The BfN's own
 * definitions retold for a beginner — „merklich zurückgegangen oder durch
 * laufende bzw. absehbare menschliche Einwirkungen bedroht" becomes
 * „deutlich zurückgegangen oder bedroht". „Kommt vor", never „wächst":
 * the scale holds stag beetles too.
 */
export const RL_CATEGORIES = [
    { value: '0',  name: t('ausgestorben oder verschollen'),
      meaning: t('Die Art ist in Deutschland verschwunden. Seit langer Zeit hat sie niemand mehr wild gefunden.') },
    { value: '1',  name: t('vom Aussterben bedroht'),
      meaning: t('Die Art kann bald ganz verschwinden, wenn sich nichts ändert.') },
    { value: '2',  name: t('stark gefährdet'),
      meaning: t('Die Art ist stark zurückgegangen oder stark bedroht.') },
    { value: '3',  name: t('gefährdet'),
      meaning: t('Die Art ist deutlich zurückgegangen oder bedroht.') },
    { value: 'G',  name: t('Gefährdung unbekannten Ausmaßes'),
      meaning: t('Die Art ist gefährdet. Wie stark, weiß man noch nicht genau.') },
    { value: 'R',  name: t('extrem selten'),
      meaning: t('Die Art kommt nur an sehr wenigen Stellen vor. Sie geht nicht zurück, aber ein einziger Eingriff kann sie dort auslöschen.') },
    { value: 'V',  name: t('Vorwarnliste'),
      meaning: t('Die Art ist noch nicht gefährdet, aber schon deutlich zurückgegangen. Geht es so weiter, wird sie gefährdet.') },
    { value: '*',  name: t('ungefährdet'),       displayOnly: true,
      meaning: t('Die Art ist nicht gefährdet.') },
    { value: 'D',  name: t('Daten unzureichend'), displayOnly: true,
      meaning: t('Über die Art weiß man zu wenig, um sie einzustufen.') },
    { value: 'nb', name: t('nicht bewertet'),     displayOnly: true,
      meaning: t('Die Art wurde nicht eingestuft.') },
];

/**
 * Only known categories, without duplicates, in the order of the scale.
 *
 * CHECKED AGAINST THE WHOLE SCALE, not only the checkable ones: an auto
 * list saved with "D" before 15.08.2026 should keep running. What can no
 * longer be checked need not, on top of that, be tacitly thrown out of
 * other people's filters.
 */
export function readRl(raw) {
    const inside = new Set(Array.isArray(raw) ? raw : []);
    return RL_CATEGORIES.map((k) => k.value).filter((w) => inside.has(w));
}

/**
 * Does the point match the chosen categories?
 *
 * NOTHING CHOSEN MEANS EVERYTHING. Otherwise the map would be empty until
 * you set the first checkmark — the filter thus on without anyone having
 * switched it on.
 *
 * @param {string[]} chosen
 * @param {string[]} categories  the point's, mostly none or one
 */
export function rlMatches(chosen, categories) {
    if (chosen.length === 0) return true;
    for (const k of Array.isArray(categories) ? categories : []) {
        if (chosen.includes(k)) return true;
    }
    return false;
}

/** For form field and settings: "1,2,3". */
export function writeRl(chosen) {
    return readRl(chosen).join(',');
}

/** And back. */
export function rlFromText(raw) {
    return readRl(String(raw ?? '').split(','));
}

/**
 * Draw the checkmarks.
 *
 * NUMBER AND WORD TOGETHER: "3" alone tells nobody anything, "gefährdet"
 * alone leaves open the category that stands in every report. The number
 * therefore stands in front with its own styling and the word next to it.
 *
 * @param {HTMLElement} container  emptied and refilled
 * @param {string[]} chosen      changed in place
 */
export function drawRl(container, chosen, onChange) {
    container.textContent = '';

    for (const k of RL_CATEGORIES) {
        if (k.displayOnly) continue;

        const row = document.createElement('label');
        row.className = 'filter-check rl-check';

        const box = document.createElement('input');
        box.type = 'checkbox';
        box.checked = chosen.includes(k.value);
        box.addEventListener('change', () => {
            const inside = chosen.indexOf(k.value);
            if (box.checked && inside === -1) chosen.push(k.value);
            if (!box.checked && inside !== -1) chosen.splice(inside, 1);
            onChange();
        });

        const code = document.createElement('span');
        code.className = 'rl-code';
        code.textContent = k.value;

        const name = document.createElement('span');
        name.className = 'rl-name';
        name.textContent = k.name;

        row.append(box, code, name);
        container.append(row);
    }
}

// ---------------------------------------------------------------------------
// The DGfM's lists (17.09.2026) — food value and responsibility
// ---------------------------------------------------------------------------

/**
 * The three food-value marks of the DGfM's lists — Positivliste der
 * Speisepilze, Pilze mit uneinheitlich beurteiltem Speisewert, Giftpilze
 * — in the order the Speisewert group shows them. A species may carry
 * several at once (Morchella: edible with a warning, inconsistently rated,
 * poisonous raw), so they are checkmarks and OR-linked like the Red List
 * categories. The words are DGFM_LISTS's (includes/dgfm_marks.php); the
 * browser cannot read that file, so this is the deliberate twin.
 */
export const EDIBILITY_MARKS = [
    { value: 'edible',       name: t('Speisepilz') },
    { value: 'inconsistent', name: t('uneinheitlich beurteilt') },
    { value: 'poisonous',    name: t('Giftpilz') },
];

/** Every DGfM mark as the word the user reads — for the point window and the table. */
export function dgfmWord(mark) {
    const words = {
        edible: t('Speisepilz'), inconsistent: t('uneinheitlich beurteilt'),
        poisonous: t('Giftpilz'), responsibility: t('Verantwortungsart'),
    };
    const word = words[mark?.list] ?? String(mark?.list ?? '');
    return mark?.value ? word + ' ' + mark.value : word;
}

/** Only known marks, without duplicates, in the group's order. */
export function readEdibility(raw) {
    const inside = new Set(Array.isArray(raw) ? raw : []);
    return EDIBILITY_MARKS.map((k) => k.value).filter((w) => inside.has(w));
}

/**
 * Does the point carry one of the chosen marks? Nothing chosen means
 * everything, like rlMatches — the twin of dgfm_marks_match() on the
 * server (includes/dgfm_marks.php).
 *
 * @param {string[]} chosen
 * @param {Array<{list: string}>} marks  the point's dgfm marks, mostly none
 */
export function edibilityMatches(chosen, marks) {
    if (chosen.length === 0) return true;
    for (const mark of Array.isArray(marks) ? marks : []) {
        if (chosen.includes(mark.list)) return true;
    }
    return false;
}

/** The hook „nur Verantwortungsarten": off means everything. */
export function responsibilityMatches(only, marks) {
    if (!only) return true;
    return (Array.isArray(marks) ? marks : []).some((mark) => mark.list === 'responsibility');
}

/** For form field and settings: "edible,poisonous". */
export function writeEdibility(chosen) {
    return readEdibility(chosen).join(',');
}

/** And back. */
export function edibilityFromText(raw) {
    return readEdibility(String(raw ?? '').split(','));
}

/**
 * Draw the three checkmarks — the same rows as the Red List's, without
 * the code in front: the word is the whole statement here.
 *
 * @param {HTMLElement} container  emptied and refilled
 * @param {string[]} chosen      changed in place
 */
export function drawEdibility(container, chosen, onChange) {
    container.textContent = '';
    for (const k of EDIBILITY_MARKS) {
        const row = document.createElement('label');
        row.className = 'filter-check rl-check';
        const box = document.createElement('input');
        box.type = 'checkbox';
        box.checked = chosen.includes(k.value);
        box.addEventListener('change', () => {
            const inside = chosen.indexOf(k.value);
            if (box.checked && inside === -1) chosen.push(k.value);
            if (!box.checked && inside !== -1) chosen.splice(inside, 1);
            onChange();
        });
        const name = document.createElement('span');
        name.className = 'rl-name';
        name.textContent = k.name;
        row.append(box, name);
        container.append(row);
    }
}

/**
 * The responsibility hook under the Red List categories — one checkbox,
 * an AND beside the OR of the categories: „gefährdet, und Deutschland
 * trägt dafür Verantwortung".
 *
 * @param {boolean} checked
 * @param {(on: boolean) => void} onChange
 */
export function drawResponsibility(container, checked, onChange) {
    container.textContent = '';
    const row = document.createElement('label');
    row.className = 'filter-check';
    const box = document.createElement('input');
    box.type = 'checkbox';
    box.checked = checked;
    box.addEventListener('change', () => onChange(box.checked));
    const name = document.createElement('span');
    name.textContent = t('nur Verantwortungsarten');
    name.title = t('Arten, für deren Erhalt Deutschland eine besondere Verantwortung trägt — die Liste der DGfM');
    row.append(box, name);
    container.append(row);
}

/**
 * Draw the identification group — one function for the sidebar and for
 * Organisieren, like drawRl above, so the two surfaces cannot drift apart.
 *
 * THREE LAYERS, ALL OR-LINKED (identificationMatches in
 * identification.js): the collective hook on top, under it the
 * abbreviation FAMILIES from the title (sp. means sp/spp/spec/species at
 * once — the spellings are noise, the family is the statement), and the
 * steps of the field "Bestimmungssicherheit". The steps are read from
 * find_fields, not written out here: the checkboxes then always offer
 * exactly what the form offers, and a fifth step added over there arrives
 * by itself.
 *
 * @param {HTMLElement} container  emptied and refilled
 * @param {{open: boolean, marks: string[], certainty: string[]}} sel
 *        changed in place; 'open' via the returned setter's row
 * @param {() => void} onChange
 * @param {Array} findFields  the fixed fields from the start data
 */
export function drawIdentification(container, sel, onChange, findFields) {
    container.textContent = '';

    const check = (label, isOn, toggle, extraClass = '') => {
        const row = document.createElement('label');
        row.className = ('filter-check ' + extraClass).trim();
        const box = document.createElement('input');
        box.type = 'checkbox';
        box.checked = isOn();
        box.addEventListener('change', () => { toggle(box.checked); onChange(); });
        row.append(box, document.createTextNode(label));
        return row;
    };

    const inList = (list, value, on) => {
        const at = list.indexOf(value);
        if (on && at === -1) list.push(value);
        if (!on && at !== -1) list.splice(at, 1);
    };

    // The collective hook — the old "nur nicht bestimmte", now including
    // the field value 'unbestimmt' (see identificationMatches).
    container.append(check(t('nur nicht bestimmte'),
        () => sel.open, (on) => { sel.open = on; }));

    const sub = (text) => {
        // 'p', not 't' — that name would shadow t() from i18n.js.
        const p = document.createElement('p');
        p.className = 'hint-text small filter-sub';
        p.textContent = text;
        return p;
    };

    container.append(sub(t('Kürzel im Titel')));
    const marksRow = document.createElement('div');
    marksRow.className = 'mark-checks';
    for (const m of OPEN_MARKS) {
        marksRow.append(check(m.label,
            () => sel.marks.includes(m.value),
            (on) => inList(sel.marks, m.value, on)));
    }
    container.append(marksRow);

    /* THE MATERIAL STATUS (30.08.2026: „Mach mir noch einen
       Filter für bearbeitet und unbearbeitet. In Bestimmung.") — worked
       up means cloud recordings hang on the find (workedMatches in
       identification.js). Two mutually exclusive checks, both off = no
       condition: three states, no third checkbox needed. */
    container.append(sub(t('Material')));
    const workedRow = document.createElement('div');
    workedRow.className = 'mark-checks';
    const yesRow = check(t('bearbeitet'),
        () => sel.worked === 'yes',
        (on) => {
            sel.worked = on ? 'yes' : '';
            if (on) noRow.querySelector('input').checked = false;
        });
    const noRow = check(t('unbearbeitet'),
        () => sel.worked === 'no',
        (on) => {
            sel.worked = on ? 'no' : '';
            if (on) yesRow.querySelector('input').checked = false;
        });
    workedRow.append(yesRow, noRow);
    container.append(workedRow);

    // The steps of the certainty field. Without the field (a start
    // document from before it existed) this part simply stays away.
    const field = (findFields ?? []).find((f) => f.name === CERTAINTY_FIELD);
    if (!field?.options?.length) return;

    container.append(sub(t('Feld „%s"', CERTAINTY_FIELD)));
    for (const option of field.options) {
        container.append(check(option,
            () => sel.certainty.includes(option),
            (on) => inList(sel.certainty, option, on)));
    }
}
