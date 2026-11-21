/**
 * Organisieren (Mein Bereich → Organisieren): tree, point table, selection and the
 * three-state list dialog.
 *
 * All reading and filtering happens here in the browser — the data lies as
 * JSON in the page (#organize-data). Writing goes through two classic
 * forms; the page comes back with a message and the previous view.
 *
 * THE THREE-STATE CHECKBOX: check = all chosen points hang in the list,
 * DASH = some, empty = none. A click moves on (dash → check → empty →
 * dash again), so you always get back to "leave as is". The middle state
 * carried the same check as the first until 21.08.2026, only in a quieter
 * colour — and that made the first click look like nothing had happened
 * (see .is-partial in app.css). Only the two deltas
 * (added, removed) are sent — never a finished list set, for that would
 * steamroll the mixed assignments of the untouched lists. Exactly that
 * must never happen here.
 */

import { confirm, prompt, choose, popupMenu, report } from './dialogs.js';
import { t } from './i18n.js';
import { icon } from './icons.js';
import { tickBox, tickOf } from './tickbox.js';
import { toast } from './toast.js';
import { settings } from './settings.js';
import { shortNames } from './names.js';
import { shapePoint } from './shapes.js';
import { pointHaystack } from './haystack.js';
import { findNumberFallback, findNumberKey } from './findnumber.js';
import { openNumbers } from './numberdialog.js';
import { mountPhotoWindow } from './photowindow.js';
import { api, request } from './api.js';
import * as filter from './filter.js';
import * as timerange from './timerange.js';
import { identificationMatches, workedMatches, OPEN_MARKS, CERTAINTY_FIELD } from './identification.js';

/* The searchable text per point, built once and kept.
 *
 * SAFE TO KEEP because nothing on this page edits a point in place:
 * every change goes out as form.submit() and comes back as a fresh page
 * with fresh point objects and an empty memory. Checked before this was
 * written — there is no assignment to a point's title, note or extra
 * anywhere in this file. Should that ever change, the memory has to be
 * dropped along with it, and this paragraph is where to look.
 *
 * A WeakMap and not a field on the point: these objects come out of JSON
 * and get handed around and compared; a field of ours would travel into
 * places that know nothing about it. The map holds only what it was
 * asked about and lets go by itself.
 *
 * Worth remembering at all because this runs on every keystroke of the
 * search field, over the whole stock. */
const haystacks = new WeakMap();

function haystackOf(point) {
    let text = haystacks.get(point);
    if (text === undefined) {
        text = pointHaystack(point);
        haystacks.set(point, text);
    }
    return text;
}

/* The point data arrives as JSON in the fragment, and the fragment
 * arrives when somebody opens the tab (25.08.2026) — at load there is
 * nothing here to read. administration.js calls this once the markup
 * stands. Guarded against a second call: startOrganize builds the whole
 * view, and running it twice would double every row. */
let started = false;

export function mountOrganize() {
    if (started) return;
    const dataEl = document.getElementById('organize-data');
    if (!dataEl) return;
    started = true;
    startOrganize(JSON.parse(dataEl.textContent));
}

mountOrganize();

/* ONE ROW SHAPE FOR BOTH COLUMN WINDOWS — the table's and the report's.
   Both ask the same question, which fields and in which order, so both
   give the same answer: a checkmark, and two arrows on the right by which
   the ORDER OF THE ROWS IS THE ORDER OF THE COLUMNS. They were built
   separately for a day, and the report's rows came out without arrows at
   all — its column order was whatever the table happened to have.

   `fixed` pins a row: no arrows, the checkmark cannot be cleared, and
   nothing may move above it either. The report's title column is pinned
   that way — in the tiles form it is the headline of the tile
   (pages/report.php takes $chosen[0] for it), so a field that slipped
   above it would silently become the heading of every find. */
function columnChoiceRow(key, label, { checked = false, fixed = false } = {}) {
    const row = document.createElement('div');
    row.className = 'organize-column-row';
    row.dataset.key = key;
    if (fixed) row.dataset.fixed = '1';

    const box = document.createElement('label');
    const check = document.createElement('input');
    check.type = 'checkbox';
    check.value = key;
    check.checked = checked;
    check.disabled = fixed;
    box.append(check, ` ${label}`);
    row.append(box);

    if (!fixed) {
        const move = (dir) => {
            const sib = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
            if (!sib || sib.dataset.fixed) return;
            dir < 0 ? sib.before(row) : sib.after(row);
        };
        for (const [dir, sign, title] of [[-1, '↑', t('Nach oben')], [1, '↓', t('Nach unten')]]) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'organize-column-move';
            button.textContent = sign;
            button.title = title;
            button.setAttribute('aria-label', `${label}: ${title}`);
            button.addEventListener('click', () => move(dir));
            row.append(button);
        }
    }
    return row;
}

function startOrganize({ lists, folders = [], points, shared = [], foreign_points: foreignPoints = [],
                         preset, find_fields: findFields, tour_names: tourNames = [],
                         excursions = [], find_series: findSeries = null,
                         trash_numbers: trashNumbers = [], mykis = null }) {
    const el = (id) => document.getElementById(id);
    const treeEl       = el('organize-tree');
    const toursEl      = el('organize-tours');
    const rowsEl       = el('organize-rows');
    const listCountEl  = el('organize-list-count');
    const pointCountEl = el('organize-point-count');
    const selectionEl  = el('organize-selection');
    const headerCheck  = el('organize-header-check');
    const dialog       = el('organize-dialog');

    const listById   = new Map(lists.map((l) => [l.id, l]));
    const childrenOf = (id) => lists.filter((l) => l.parent_id === id);
    const isLeaf     = (id) => childrenOf(id).length === 0;
    // Children as the tree draws them — container lists never appear here.
    const drawableKids = (id) => childrenOf(id).filter((l) => !l.is_container_list);

    /* THE FOLD STATE IS THE SIDEBAR'S (22.08.2026: one concept
       through the whole page). Same settings, same keys — collapsedLists
       carries own list ids, collapsedShared carries 'c<id>' for containers
       and list ids for mounted branches, exactly as map.js writes them. A
       heading folded away on the map is folded away here, and the other
       way round: it is the same tree, and two views that fold it
       differently would show the same thing differently (the argument
       CLAUDE.md already makes for redrawing). */
    /* An appearance key names PLACE AND LIST — 'r:<container>:<liste>'
       for own lists, 'f:<container>:<liste>' for foreign ones. The
       container is part of the key since 22.08.2026: with the list alone,
       „Waldbrandflächen" hanging in two containers shared one key, and
       choosing it in one place marked it in the other (the maintainer found
       it, again). listIdOf() is the single reader — everything that needs
       the LIST behind an appearance goes through it. */
    const listIdOf = (key) => Number(key.split(':')[2]);

    const collapsedOwn    = new Set(settings.get('collapsedLists', []) || []);
    const collapsedShared = new Set(settings.get('collapsedShared', []) || []);

    /* THE SHORT NAMES OF THE SIDEBAR (22.08.2026): „· Finn"
       instead of „· Finn Horn" — shortened against the same company, the
       shared module names.js decides how far a surname has to come along.
       One map for the whole section, so the same person is never „Finn"
       in one row and „Finn H." in the next. */
    const shortShared = shortNames(new Set([
        ...shared.map((c) => c.owner).filter(Boolean),
        ...shared.flatMap((c) => (c.shares ?? []).map((f) => f.display_name)).filter(Boolean),
    ]));
    const shortOf = (n) => shortShared.get(n) ?? n;

    /* "Ohne Liste" means: in no ORDINARY list. Auto lists do not count as
       classification — they are the result of their filter, not a filing
       place. The server computes the same reading (autoliste_trifft,
       data.php); if the two diverged, an auto list with the without-source
       would take its points out of the row here but not out of the list
       there. */
    const isWithoutList = (p) => p.lists.every((id) => listById.get(id)?.is_autolist);

    /* A list's branch as a set of ids — for filtering and counting. */
    const branchSets = new Map();
    function branchSet(id) {
        if (!branchSets.has(id)) {
            const set = new Set([id]);
            for (const child of childrenOf(id)) {
                for (const t of branchSet(child.id)) set.add(t);
            }
            branchSets.set(id, set);
        }
        return branchSets.get(id);
    }
    /* COUNT points per branch, not assignments: a point in two leaves of
       the same branch is ONE point — the sum of the leaf counts would lie. */
    const branchCount = (id) => {
        const set = branchSet(id);
        return points.reduce((n, p) => n + (p.lists.some((x) => set.has(x)) ? 1 : 0), 0);
    };
    const directCount = (id) => points.reduce((n, p) => n + (p.lists.includes(id) ? 1 : 0), 0);

    /* The root of a branch — as on the map: only it carries the custom
       fields, and its species-list checkmark makes the branch a find
       mapping. */
    function rootOf(id) {
        let l = listById.get(id);
        while (l && l.parent_id !== null && listById.has(l.parent_id)) {
            l = listById.get(l.parent_id);
        }
        return l;
    }

    // -----------------------------------------------------------------
    // The containers — my own and those I am a member of
    //
    // They stand as their own section beneath the tree: one row per
    // container, beneath it the mounted lists. An OWN mounted list keeps
    // its ordinary id and behaves like up in the tree — it IS the same
    // list, and it is into it that you sort. Foreign lists ('f:<id>') and
    // whole containers ('c:<id>') are pure views: their points appear on
    // the right without a checkbox, for the ordering of a foreign point
    // belongs to its owner.
    // -----------------------------------------------------------------

    const sharedById  = new Map(shared.map((c) => ['c:' + c.id, c]));
    const foreignById    = new Map();
    for (const p of foreignPoints) foreignById.set(p.id, { ...p, foreign: true });
    /* The excursions the user leads (stage 5) — their finds sit in
       foreignPoints too, marked editable by the server: foreign in
       ownership, the leader's in right. */
    const excursionById = new Map(excursions.map((e) => [e.id, e]));
    /* One's own points by id — an excursion row may name a find that
       belongs to oneself (a leader who took part), and the foreign index
       deliberately does not carry it. */
    const ownById = new Map(points.map((p) => [p.id, p]));

    /* Name and color of every container list — for tree and lists column. */
    const foreignListById = new Map();
    /* Per container list its branch WITHIN the share, as a set of ids. */
    const foreignBranches = new Map();
    for (const c of shared) {
        for (const f of c.shares) {
            const inside = new Set(f.lists.map((l) => l.id));
            const children = new Map();
            for (const l of f.lists) {
                foreignListById.set(l.id, l);
                // The root of a shared branch can have a parent list at
                // home — here only what the share knows counts.
                const e = l.parent_id !== null && inside.has(l.parent_id) ? l.parent_id : null;
                if (!children.has(e)) children.set(e, []);
                children.get(e).push(l);
            }
            const descend = (id) => {
                const set = new Set([id]);
                for (const child of children.get(id) ?? []) {
                    for (const t of descend(child.id)) set.add(t);
                }
                foreignBranches.set(id, set);
                return set;
            };
            for (const l of children.get(null) ?? []) descend(l.id);
            f.children = children;
        }
    }

    const foreignBranchCount = (id) => {
        const set = foreignBranches.get(id) ?? new Set();
        return foreignPoints.reduce((n, p) => n + (p.lists.some((x) => set.has(x)) ? 1 : 0), 0);
    };

    /* The tree as a flat sequence in display order — for select lists. */
    function treeSequence() {
        const sequence = [];
        const descend = (parentId, depth) => {
            for (const l of childrenOf(parentId)) {
                // Container lists also stay out of the structure select
                // lists (moving, merging): they belong to their container,
                // not to the tree you would hang something into.
                if (l.is_container_list) continue;
                // Auto lists likewise: nothing moves under them (the server
                // would refuse it, liste_eltern_pruefen), and nothing
                // merges into them — both consumers of this sequence are
                // target lists.
                if (l.is_autolist) continue;
                sequence.push({ l, depth });
                descend(l.id, depth + 1);
            }
        };
        descend(null, 0);
        return sequence;
    }

    const state = {
        // SEVERAL TOGETHER (the call, 14.08.2026): each row toggles
        // on and off, the table shows the union. The empty set means "Alle
        // Punkte" — a dedicated id for that would just give two spellings
        // for the same state.
        // 'rl' keeps its word: it is
        //         // the point's threat list and thus column-true. 'lists'
        // was called 'lists' until 21.08.2026 and was defended as "the
        // point field" — it is not one: it holds the list keys CHOSEN in
        // the tree, an invention of this file, and nobody outside reads
        // it.
        lists: new Set(),
        /* THE LEFT COLUMN HAS TWO PANES (23.08.2026): Listen or
           Touren. The ACTIVE one alone governs the table — switching
           resets the other pane's choice, so the switch means what it
           says. 'tours' holds chosen tour names; 'none' stands for the
           points without one, like it does for the lists.

           WHICH PANE OUTLIVES THE VISIT (23.08.2026), like the
           column width and the chosen columns beside it: whoever organizes
           by tours comes back to tours. The chosen ROWS deliberately do
           not — they are working state, and a filter nobody remembers
           setting is the worse greeting (the same argument the column
           choice makes about a stored sort). A preset from a just-finished
           action still wins over this, see below. */
        pane: settings.get('organizePane', 'lists') === 'tours' ? 'tours' : 'lists',
        tours: new Set(),
        // The filter rows (assets/js/filter.js) — the same as in the map's
        // sidebar, and via the same fields.
        terms:  [],
        from:    '',
        to:    '',
        noTime: false,
        // The systematic ranks above the genus — the same shape and the
        // same rule as on the map (taxonomyMatches in filter.js).
        taxonomy: { kingdom: '', phylum: '', class: '', order: '', family: '', genus: '' },
        // The height band — the same shape and the same rule as on the
        // map (elevationMatches in filter.js). Both at 0 and MAX is no
        // condition; the upper end at MAX means „and above".
        elevation: { from: 0, to: 2500 },
        // The identification group, the same shape as on the map
        // (identificationMatches in identification.js): collective hook,
        // abbreviation families from the title, certainty steps.
        identification: { open: false, marks: [], certainty: [], worked: '' },
        // Whether the filter section is unfolded — remembered like the
        // conditions themselves, via the same state[] field of the forms.
        filterOpen: false,
        // The checked threat categories (the call, 15.08.2026).
        // Empty means all — not none.
        rl: [],
        // The DGfM's marks (17.09.2026), like on the map: the food-value
        // checkmarks and the responsibility hook.
        edibility: [],
        responsibility: false,
        // The place's other answers (22.08.2026): region codes,
        // municipalities, the map-sheet text. Empty means all.
        regions: [],
        municipalities: [],
        mtbText: '',
    };

    /* Restore the view from before the last action — whoever works through
       143 points in stages should not have to find their way anew each
       time. */
    if (preset && typeof preset === 'object') {
        for (const part of String(preset.list ?? '').split(',')) {
            if (part === '' || part === 'alle') continue;
            if (part === 'none') state.lists.add('none');
            // Only leaves: a heading folds instead of selecting since
            // 22.08.2026, so a remembered heading id would mark nothing.
            else if (/^\d+$/.test(part) && listById.has(Number(part))
                     && drawableKids(Number(part)).length === 0) state.lists.add(Number(part));
            else if (/^f:\d+:\d+$/.test(part) && foreignBranches.has(listIdOf(part))) state.lists.add(part);
        }
        state.terms = filter.termsFromText(preset.search ?? '');
        state.from      = preset.from ?? '';
        state.to      = preset.to ?? '';
        state.noTime = preset.no_time === '1';
        state.identification.open = preset.unidentified === '1';
        // Only known values survive the round trip — an unknown one would
        // sit invisibly in the state and filter without a checkbox saying
        // so. The certainty steps are measured against the field's own
        // options, the same source the checkboxes draw from.
        const steps = (findFields ?? []).find((f) => f.name === CERTAINTY_FIELD)?.options ?? [];
        state.identification.marks = String(preset.marks ?? '').split(',')
            .filter((m) => OPEN_MARKS.some((k) => k.value === m));
        state.identification.certainty = String(preset.certainty ?? '').split('|')
            .map((s) => s.trim()).filter((s) => steps.includes(s));
        state.filterOpen = preset.filter_open === '1';
        state.rl = filter.rlFromText(preset.rl ?? '');
        state.edibility = filter.edibilityFromText(preset.edibility ?? '');
        state.responsibility = preset.responsibility === '1';
        state.regions = String(preset.regions ?? '').split(',')
            .map((s) => s.trim()).filter(Boolean);
        state.municipalities = String(preset.municipalities ?? '').split('|')
            .map((s) => s.trim()).filter(Boolean);
        state.mtbText = String(preset.mtb ?? '');
        /* The pane and its chosen tours — '|'-separated like the
           municipalities, and only names that still stand on points
           survive the round trip ('none' is the without-row).

           ONLY WHEN THE FIELD REALLY CAME ALONG: on a fresh visit the
           whole preset is empty, and an unconditional read would then
           overwrite the remembered pane with the "lists" that merely
           means "nothing was sent". After an action the field carries
           its explicit value and rightly wins. */
        if (preset.pane !== undefined) {
            state.pane = preset.pane === 'tours' ? 'tours' : 'lists';
        }
        /* The keys the tour pane uses: 'i:<id>' since migration 0079,
           'n:<name>' for what has none, 'none' for the without-row and
           'e:<id>' for an excursion. A remembered key that no longer
           matches anything falls out silently — the view is a view, not
           a contract. */
        const tourKeys = new Set(points.map((p) => {
            const name = (p.tour ?? '').trim();
            if (name === '') return null;
            const id = (p.tour_id ?? '').trim();
            return id !== '' ? 'i:' + id : 'n:' + name;
        }).filter(Boolean));
        for (const part of String(preset.tours ?? '').split('|')) {
            const name = part.trim();
            if (name === 'none' || tourKeys.has(name)) state.tours.add(name);
            // An excursion row ('e:<id>') — only ones that still exist.
            else if (/^e:\d+$/.test(name) && excursionById.has(Number(name.slice(2)))) {
                state.tours.add(name);
            }
        }
    }

    function drawRl() {
        const changed = () => { filterFlap.refresh(); drawTable(); };
        filter.drawRl(el('organize-rl'), state.rl, changed);
        // The DGfM's two questions with it, as on the map (drawRl there).
        filter.drawResponsibility(el('organize-rl-responsibility'), state.responsibility, (on) => {
            state.responsibility = on;
            changed();
        });
        filter.drawEdibility(el('organize-edibility'), state.edibility, changed);
    }
    function drawSearchRows() {
        filter.drawTermRows(el('organize-search-rows'), state.terms,
            () => { filterFlap.refresh(); drawTable(); }, t('Punkte filtern …'));
    }

    /* THE DRAWN AREAS come from the settings bundle — the same list the
       sidebar draws on the map ('filterAreas'). Not view state that
       travels through the forms: Organisieren cannot draw, it only shows,
       applies and deletes; after a POST the list is simply read again.
       Only the AUTOLIST takes them along (stateFields), because its
       filter has to keep meaning what the table showed. */
    const areas = filter.readAreas(settings.get('filterAreas', []));
    function drawAreaRows() {
        const changed = () => {
            settings.set('filterAreas', areas);
            // A drawn area narrows the region offers too.
            drawRegionBox();
            filterFlap.refresh();
            drawTable();
        };
        /* DRAWING GOES TO THE MAP AND COMES BACK (23.08.2026) —
           the same path the container's birth filter already takes
           (window=filter-area in map.js) and the offline areas beside it.
           A window with a small map of its own was the first attempt and
           the wrong one: whoever frames an area wants to SEE what falls
           inside it — his lists, his points, the layer he is used to. A
           picker in a box has none of that, and a second drawing
           implementation beside beginOutline() is a debt on top.

           No handover needed here, unlike the container: the drawn areas
           live in the settings bundle, and that is the same list on both
           pages ('filterAreas'). The map appends, this page reads it on
           return. */
        filter.drawAreas(el('organize-area-rows'), areas, changed, () => {
            location.href = (window.START_DATA?.baseUrl ?? '')
                + 'index.php?window=organize-area';
        });
    }

    /* Region, municipality and map sheet — offered from the stock, own
       and foreign points alike, like the day list of the time filter.
       THE OFFERS NARROW ALONG (22.08.2026): each list draws
       its stock from the points meeting all OTHER place conditions,
       never its own — see placeMatches in filter.js. */
    const placeConditions = () => ({ areas, regions: state.regions,
        municipalities: state.municipalities, mtb: state.mtbText });
    const placeFacetStock = (except) => [...points, ...foreignPoints]
        .filter((p) => filter.placeMatches(placeConditions(), p, except));

    /* HOW THE TOURS ARE SORTED (24.08.2026) — the same two
       buttons the sidebar has above its points, with the same icons and
       the same words: by time the newest first, by name alphabetically.
       Remembered per device, like every view setting here. */
    let tourSorting = settings.get('organizeTourSorting', 'time') === 'name' ? 'name' : 'time';
    /* THE SEARCH ABOVE THE LIST (24.08.2026) — over everything
       a row SHOWS: its name, its day and its municipality. Deliberately
       not remembered: it narrows what one sees right now, and a search
       still in force after a week would hide outings without saying why
       (the same reason the drawn areas are remembered and the region
       checkmarks are not). */
    let tourSearch = '';

    /* THE COLUMN FEEDS ITSELF (24.08.2026) — the same answer
       the points table got. Measured on this very column: a thousand
       rows cost 56 ms to build and lay out, and drawTours() runs on
       EVERY keystroke in the search above, so the cost would land on the
       typing finger. Sixty rows are about two screens; the rest follows
       when the watchman at the end comes into view.

       WHY NOT SIMPLY CAP IT: two outings a week over ten years are a
       thousand rows, and the thousandth is exactly the one somebody
       looks for — a cap would hide it, feeding only delays it. */
    const TOUR_CHUNK = 60;
    let tourLimit = TOUR_CHUNK;

    let regionsOpen = state.regions.length > 0;
    function drawRegionBox() {
        filter.drawRegions(el('organize-region-filter'), {
            available: () => filter.regionsOf(placeFacetStock('regions')),
            chosen:    state.regions,
            open:      regionsOpen,
            onFold:    (open) => { regionsOpen = open; drawRegionBox(); },
            onChange:  () => { drawRegionBox(); filterFlap.refresh(); drawTable(); },
        });
    }
    function drawMunicipalityBox() {
        filter.drawMunicipalities(el('organize-municipality-filter'), {
            available: () => filter.municipalitiesOf(placeFacetStock('municipalities')),
            chosen:    state.municipalities,
            onChange:  () => { drawMunicipalityBox(); drawRegionBox(); filterFlap.refresh(); drawTable(); },
        });
    }

    // The conditions per group — the button on the section shows the sum,
    // a FOLDED group shows its own share (filter.wireGroupFolds). One
    // table for both, so the two numbers cannot drift apart.
    // 'red-list' is one condition, not one per checkmark — as in the sidebar.
    const groupCount = {
        'text': () => filter.readTerms(state.terms).length,
        // Areas, regions, municipalities, map sheet — four questions,
        // AND-linked, one count each; within each of them comma and
        // checkmark mean OR.
        'place': () => (areas.length > 0 ? 1 : 0)
            + (state.regions.length > 0 ? 1 : 0)
            + (state.municipalities.length > 0 ? 1 : 0)
            + (filter.mtbAlternatives(state.mtbText).length > 0 ? 1 : 0),
        'time': () => (state.from !== '' || state.to !== '' ? 1 : 0) + (state.noTime ? 1 : 0),
        // Three questions, like on the map: the collective hook, the
        // abbreviation families as one, the certainty steps as one.
        'identification': () => (state.identification.open ? 1 : 0)
            + (state.identification.marks.length > 0 ? 1 : 0)
            + (state.identification.certainty.length > 0 ? 1 : 0)
            + (state.identification.worked !== '' ? 1 : 0),
        'red-list': () => (state.rl.length > 0 ? 1 : 0) + (state.responsibility ? 1 : 0),
        'edibility': () => (state.edibility.length > 0 ? 1 : 0),
        'elevation': () => (state.elevation.from > 0
            || state.elevation.to < filter.ELEVATION_MAX ? 1 : 0),
        // Counted is what one chose, not what follows from it — see the
        // twin in map.js.
        'taxonomy': () => {
            const derived = new Set(state.taxonomy._derived ?? []);
            return filter.TAXONOMY_RANKS.filter(([key]) =>
                !derived.has(key) && String(state.taxonomy[key] ?? '').trim() !== '').length;
        },
    };

    // The whole filter section folds away — the same button as in the
    // sidebar (filter.js). The number on it says whether something applies
    // regardless.
    const sectionFlap = filter.wireCollapsible({
        button:  el('organize-filter-open'),
        content: el('organize-filter-section'),
        count: () => Object.values(groupCount).reduce((n, f) => n + f(), 0),
        clear: () => clearAllFilters(),
        openAtStart: state.filterOpen,
        // Every OPENING recomputes the group folds
        // (22.08.2026): Text open, the rest only where its condition
        // applies.
        onToggle: (openCount) => {
            state.filterOpen = openCount;
            if (openCount) {
                groupFolds.reset();
                // The inner region fold follows the same rule.
                regionsOpen = state.regions.length > 0;
                drawRegionBox();
                // The systematic offers are read from the stock — redrawn
                // on opening, like the place boxes above.
                drawTaxonomyControl();
            }
        },
    });
    /* And the single groups fold at their headings
       (22.08.2026) — no stored state: reset() computes the folds from the
       conditions, see wireGroupFolds. */
    const groupFolds = filter.wireGroupFolds({
        section: el('organize-filter-section'),
        counts:  (key) => groupCount[key](),
    });
    const filterFlap = { refresh: () => { sectionFlap.refresh(); groupFolds.refresh(); } };

    drawSearchRows();
    drawAreaRows();
    drawRegionBox();
    drawMunicipalityBox();
    // Named for the same reason as in the sidebar: clearAllFilters has to
    // redraw it, and no other drawer reaches this field.
    function drawMtbControl() {
        filter.drawMtb(el('organize-mtb-filter'), {
            get: () => state.mtbText,
            available: () => filter.mtbOf(placeFacetStock('mtb')),
            onChange: (value) => { state.mtbText = value; drawRegionBox(); filterFlap.refresh(); drawTable(); },
        });
    }
    drawMtbControl();
    drawRl();

    // The time filter — the same control as in the map's sidebar. The day
    // list runs over ALL points, own and foreign alike: which outings
    // exist does not depend on which list is currently chosen.
    const timeFilterControl = timerange.buildTimeFilter({
        root: el('organize-time-range'),
        get:  () => ({ from: state.from, to: state.to }),
        // A friend's day says whose, as in the sidebar (26.09.2026) — only
        // the foreign points carry an owner here, one's own carry none.
        days: () => timerange.collectDays([...points, ...foreignPoints], (p) => p.tag ?? null,
            (p) => (p.owner ? shortOf(p.owner) : null)),
        years: () => {
            let min = null, max = null;
            for (const p of [...points, ...foreignPoints]) {
                if (!p.tag) continue;
                const y = Number(String(p.tag).slice(0, 4));
                if (min === null || y < min) min = y;
                if (max === null || y > max) max = y;
            }
            return min === null ? null : [min, max];
        },
        /* The parameters were still called von/bis after the English move,
           while the body already said from/to — every click on a day threw
           a ReferenceError and the time filter silently did nothing. */
        set:  (from, to) => {
            state.from = from;
            state.to = to;
            filterFlap.refresh();
            drawTable();
        },
    });
    el('organize-no-time').checked = state.noTime;
    // The identification group, drawn like the threat categories — the
    // same function as in the sidebar, over the same state shape.
    function identificationApplied() {
        filterFlap.refresh();
        drawTable();
    }

    /* What a rank offers when the OTHER conditions apply — faceted like
       the place filter, and read from the whole stock, own points and
       foreign ones alike. The twin is taxonomyOffers in map.js. */
    function taxonomyOffers(rank, others) {
        const values = new Set();
        for (const p of [...points, ...foreignPoints]) {
            const ranks = p.taxonomy;
            if (!ranks || !ranks[rank]) continue;
            if (!filter.taxonomyMatches(others, ranks)) continue;
            values.add(ranks[rank]);
        }
        return [...values].sort((a, b) => a.localeCompare(b, 'de'));
    }

    function drawTaxonomyControl() {
        filter.drawTaxonomy(el('organize-taxonomy-build'), state.taxonomy,
            taxonomyOffers, () => {
                drawTaxonomyControl();
                filterFlap.refresh();
                drawTable();
            });
    }
    drawTaxonomyControl();

    function drawElevationControl() {
        filter.drawElevation(el('organize-elevation-build'), state.elevation, () => {
            filterFlap.refresh();
            drawTable();
        });
    }
    drawElevationControl();
    filter.drawIdentification(el('organize-identification-build'),
        state.identification, identificationApplied, findFields);

    /**
     * Every filter off, in one press — the counterpart to the sidebar's
     * clearAllFilters (26.08.2026), and it has to fall exactly
     * where groupCount above counts. A condition left standing would make
     * the table short for a reason the button no longer names.
     *
     * 'areas' is not on state and is mutated IN PLACE: the same array is
     * handed to the drawer and written to the settings bundle, so a fresh
     * one would leave both pointing at the old contents. The same holds
     * for identification and rl, whose drawers hold their reference from
     * the first drawing.
     */
    function clearAllFilters() {
        state.terms = [];
        areas.length = 0;
        state.regions = [];
        state.municipalities = [];
        state.mtbText = '';
        state.from = '';
        state.to = '';
        state.noTime = false;
        state.identification.open = false;
        state.identification.marks.length = 0;
        state.identification.certainty.length = 0;
        state.identification.worked = '';
        state.rl.length = 0;
        state.edibility.length = 0;
        state.responsibility = false;
        state.elevation.from = 0;
        state.elevation.to = filter.ELEVATION_MAX;
        for (const [key] of filter.TAXONOMY_RANKS) state.taxonomy[key] = '';

        // The drawn areas outlive the visit and are shared with the map —
        // clearing them here clears them there (settings bundle).
        settings.set('filterAreas', areas);

        drawSearchRows();
        drawAreaRows();
        regionsOpen = false;
        drawRegionBox();
        drawMunicipalityBox();
        drawMtbControl();
        drawElevationControl();
        drawTaxonomyControl();
        drawRl();
        filter.drawIdentification(el('organize-identification-build'),
            state.identification, identificationApplied, findFields);
        timeFilterControl.refresh();
        el('organize-no-time').checked = false;

        filterFlap.refresh();
        drawTable();
        toast(t('Filter gelöscht.'));
    }

    const selection = new Set();

    // -----------------------------------------------------------------
    // Filtering
    // -----------------------------------------------------------------

    function matches() {
        let set;
        if (state.pane === 'tours') {
            /* THE TOURS PANE GOVERNS ALONE — the list choice was reset on
               switching. Own points for the tours (a tour is personal, a
               foreign point was not along on the outing) — and since
               stage 5 the EXCURSIONS beside them ('e:<id>'): their finds
               are foreign (the guest account's) and come from the
               server-listed ids. Union like in the lists pane. */
            if (state.tours.size === 0) {
                set = points;
            } else {
                const inside = new Set();
                set = [];
                const take = (p) => {
                    if (p && !inside.has(p.id)) { inside.add(p.id); set.push(p); }
                };
                for (const key of state.tours) {
                    if (key.startsWith('e:')) {
                        const ex = excursionById.get(Number(key.slice(2)));
                        /* FROM BOTH SIDES: an excursion holds guests'
                           finds AND, since a registered participant maps
                           into his own stock, one's own. The server
                           leaves the latter out of the foreign index —
                           they are already here — so the lookup falls
                           back to the own points. */
                        (ex?.points ?? [])
                            .map((id) => foreignById.get(id) ?? ownById.get(id))
                            .forEach(take);
                    } else if (key === 'none') {
                        points.filter((p) => (p.tour ?? '').trim() === '').forEach(take);
                    } else {
                        /* THE KEY SAYS BY WHAT (migration 0079): 'i:' is
                           an identity, 'n:' a bare name — the latter for
                           points from before the column and for empty
                           tours from the registry, which have no id to
                           be found by. */
                        const byId = key.startsWith('i:');
                        const wanted = key.slice(2);
                        points.filter((p) => {
                            const name = (p.tour ?? '').trim();
                            if (name === '') return false;
                            const id = (p.tour_id ?? '').trim();
                            return byId ? id === wanted : (id === '' && name === wanted);
                        }).forEach(take);
                    }
                }
            }
        } else if (state.lists.size === 0) {
            set = points;
        } else {
            // THE UNION of the chosen rows — a point hanging in two chosen
            // lists appears once. Container sets come from the server and
            // are sifted through the birth filter: the tree shows what the
            // container REALLY contains, not everything the lists at home
            // hold.
            const inside = new Set();
            set = [];
            const take = (p) => {
                if (p && !inside.has(p.id)) { inside.add(p.id); set.push(p); }
            };
            for (const key of state.lists) {
                if (key === 'none') {
                    points.filter(isWithoutList).forEach(take);
                } else if (typeof key === 'number') {
                    const branch = branchSet(key);
                    points.filter((p) => p.lists.some((x) => branch.has(x))).forEach(take);
                } else if (key.startsWith('r:')) {
                    // The container appearance of an own list — the same
                    // branch as its original, only chosen at another spot.
                    const branch = branchSet(listIdOf(key));
                    points.filter((p) => p.lists.some((x) => branch.has(x))).forEach(take);
                } else if (key.startsWith('c:')) {
                    const c = sharedById.get(key);
                    const own = new Set(c.own_points);
                    points.filter((p) => own.has(p.id)).forEach(take);
                    c.foreign_points.map((id) => foreignById.get(id)).forEach(take);
                } else {
                    const branch = foreignBranches.get(listIdOf(key)) ?? new Set();
                    foreignPoints.map((p) => foreignById.get(p.id))
                        .filter((p) => p.lists.some((x) => branch.has(x))).forEach(take);
                }
            }
        }
        /* ACROSS ALL FIELDS, not only the title — title, note, extra
           fields, map sheet, municipality and protected area, the same
           text the sidebar searches. It used to arrive ready-made from
           the server; since 25.08.2026 it is built here, because every
           ingredient travels in the payload anyway and the finished text
           was 17 % of it (measured on 1.640 points). Remembered per
           point — see haystackOf(). */
        set = set.filter((p) => filter.matchTerms(state.terms, haystackOf(p)));
        if (state.noTime) {
            /* The only filter that means the MISSING find time — and
               therefore looks strictly at found_at, not at the day: a
               point whose day comes from the creation time is exactly one
               of those you are looking for here. */
            set = set.filter((p) => p.found_at === null);
        } else {
            /* On days, as in the sidebar (assets/js/timerange.js). 'tag'
               comes ready-made from the server — find time, failing that
               creation time. */
            set = set.filter((p) => timerange.inTimeRange(p.tag ?? null, state.from, state.to));
        }
        /* The abbreviation checks read THE TITLE ONLY (why is written at
           openMarksIn in identification.js); the certainty checks read
           the point's own field. The same call as on the map. */
        set = set.filter((p) => identificationMatches(state.identification,
            p.title, p.extra?.[CERTAINTY_FIELD]));
        // The material status — the AND beside the OR-machine above,
        // the same reading as the map's (workedMatches).
        set = set.filter((p) => workedMatches(state.identification, p.recordings));
        /* The threat status comes ready-made from the server ('rl', looked
           up on the taxon) and likewise means only the title species. */
        set = set.filter((p) => filter.rlMatches(state.rl, p.rl));
        // The DGfM marks ('dgfm', looked up beside the threat status) —
        // the same two readings as on the map and in dgfm_marks_match().
        set = set.filter((p) => filter.edibilityMatches(state.edibility, p.dgfm));
        set = set.filter((p) => filter.responsibilityMatches(state.responsibility, p.dgfm));
        /* The place — drawn areas, region, municipality, map sheet in
           ONE test (placeMatches in filter.js), the same reading as on
           the map (pointVisible). lat/lon travels in the lean payload
           for exactly this line. */
        set = set.filter((p) => filter.placeMatches(placeConditions(), p));
        /* The height band, the same rule as on the map and on the server
           (elevationMatches in filter.js, elevation_matches in data.php).
           A point without an elevation drops out as soon as it applies. */
        set = set.filter((p) => filter.elevationMatches(state.elevation, p.elevation_m));
        /* The systematic ranks, the same rule as on the map and on the
           server (taxonomy_matches in includes/taxonomy.php). */
        set = set.filter((p) => filter.taxonomyMatches(state.taxonomy, p.taxonomy));
        return set;
    }

    // -----------------------------------------------------------------
    // The divider between the columns
    // -----------------------------------------------------------------

    /* DRAGGABLE, AND THE CHOICE SURVIVES (22.08.2026: the list
       side had grown narrow — everyone sets the split themselves). The
       width lives as a CSS variable on the grid; dragging writes it,
       letting go stores it in the settings bundle like everything that
       outlives a visit. Clamped so neither side can be crushed: the list
       keeps its old minimum, the table keeps room to be a table. Double
       click — and Home on the keyboard — return to the built-in split;
       stored is then nothing, so a later change of the default reaches
       everyone who never chose. */
    (() => {
        const splitter = document.getElementById('organize-splitter');
        const box = document.querySelector('.organize');
        if (!splitter || !box) return;

        const MIN = 180;
        const apply = (px) => box.style.setProperty('--organize-list-width', px + 'px');
        /* The right side only needs room for a readable STRIP now, not for
           the whole table: since it scrolls sideways (app.css, min-width
           on the table), pushing the divider far right crushes nothing —
           the table slides instead (22.08.2026).

           A HIDDEN BOX CAPS NOTHING. This code runs at module start, and
           Organisieren is one tab of many — whoever arrives on another tab has
           this section hidden, and a hidden box measures 0 wide. Clamped
           against that, every stored width collapsed to the minimum:
           the maintainer dragged the column wide again and again, and every
           visit that began elsewhere crushed it back to 180 px (measured
           23.08.2026: stored 420, applied 180). The real cap returns with
           the first drag, when the box is visible. */
        const clamp = (px) => {
            const width = box.getBoundingClientRect().width;
            return Math.max(MIN, Math.min(px, width > 0 ? width - 260 : px));
        };

        const stored = settings.get('organizeListWidth', null);
        if (typeof stored === 'number') apply(clamp(stored));

        const current = () =>
            parseFloat(getComputedStyle(box).getPropertyValue('--organize-list-width'));

        splitter.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            // Capture keeps the drag alive when the pointer hurries ahead
            // of the strip; where it is refused, plain move events remain.
            try { splitter.setPointerCapture(e.pointerId); } catch { /* fine */ }
            const move = (ev) => apply(clamp(ev.clientX - box.getBoundingClientRect().left));
            const up = () => {
                splitter.removeEventListener('pointermove', move);
                settings.set('organizeListWidth', Math.round(current()));
            };
            splitter.addEventListener('pointermove', move);
            splitter.addEventListener('pointerup', up, { once: true });
        });

        splitter.addEventListener('dblclick', () => {
            box.style.removeProperty('--organize-list-width');
            settings.forget('organizeListWidth');
        });

        splitter.addEventListener('keydown', (e) => {
            if (e.key === 'Home') {
                box.style.removeProperty('--organize-list-width');
                settings.forget('organizeListWidth');
                return;
            }
            const step = { ArrowLeft: -16, ArrowRight: 16 }[e.key];
            if (!step) return;
            e.preventDefault();
            apply(clamp(current() + step));
            settings.set('organizeListWidth', Math.round(current()));
        });
    })();

    // -----------------------------------------------------------------
    // The tree on the left
    // -----------------------------------------------------------------

    // -----------------------------------------------------------------
    // The two panes of the left column: Listen and Touren
    // -----------------------------------------------------------------

    const paneButtons = {
        lists: el('organize-pane-lists'),
        tours: el('organize-pane-tours'),
    };

    function switchPane(pane) {
        if (state.pane === pane) return;
        // THE SWITCH RESETS THE OTHER PANE (23.08.2026): the
        // active pane alone governs the table, and a hidden leftover
        // choice would filter without any visible reason.
        state.pane = pane;
        state.lists.clear();
        state.tours.clear();
        settings.set('organizePane', pane);
        drawAll();
    }
    paneButtons.lists.addEventListener('click', () => switchPane('lists'));
    paneButtons.tours.addEventListener('click', () => switchPane('tours'));

    function drawPanes() {
        const tours = state.pane === 'tours';
        paneButtons.lists.classList.toggle('is-active', !tours);
        paneButtons.tours.classList.toggle('is-active', tours);
        paneButtons.lists.setAttribute('aria-pressed', String(!tours));
        paneButtons.tours.setAttribute('aria-pressed', String(tours));
        treeEl.hidden  = tours;
        toursEl.hidden = !tours;
        // The button under the table says what it would create here.
        el('organize-new-list').textContent = tours ? t('Neue Tour …') : t('Neue Liste …');
        // The badge counts the ACTIVE pane's chosen rows.
        listCountEl.textContent = String(tours ? state.tours.size : state.lists.size);
    }

    /* The tours of the stock, most recent first — grouped by NAME, exactly
       as the map's time filter does (collectTours in timerange.js): two
       outings on one day stay two rows. Rebuilt on every draw; a few
       dozen tours cost nothing. */
    function tourRows() {
        // name -> {tag, count, places} — places counts the municipalities,
        // so the second line can name the one where most of the tour lay.
        const tours = new Map();
        let without = 0;
        /* THE REGISTERED NAMES FIRST, with zero points: a tour created
           empty (migration 0073) has nothing to be recognized by and
           would otherwise not appear at all — which would read as "the
           creating did not work". Points below raise the count. */
        /* GROUPED BY IDENTITY, not by name (migration 0079,
           „vergib doch Tour-IDs, dann können wir auch gleiche Namen
           nutzen"). The key is the tour_id where a point has one;
           everything older — and every registered empty name — falls back
           to the name, exactly as it behaved before. So two outings called
           „Tagestour" stand as two rows, and one outing keeps its points
           together even after a rename. */
        for (const p of points) {
            const name = (p.tour ?? '').trim();
            if (name === '') { without++; continue; }
            const id  = (p.tour_id ?? '').trim();
            const key = id !== '' ? 'i:' + id : 'n:' + name;
            const t = tours.get(key)
                ?? { name, tag: '', count: 0, places: new Map(), id: id || null };
            t.count++;
            // The newest name of the outing wins — a rename that has not
            // reached every point yet must not split the row.
            t.name = name;
            if ((p.tag ?? '') > t.tag) t.tag = p.tag;
            if (p.municipality) t.places.set(p.municipality, (t.places.get(p.municipality) ?? 0) + 1);
            tours.set(key, t);
        }
        /* THE REGISTERED NAMES LAST, and only where no outing carries
           that name already (migration 0073 meets 0079): a tour created
           empty has no id — its points, once they exist, do. Seeding the
           registry FIRST split „Nachtfalter September" into two rows, the
           registry's empty one and the points' own (caught in the browser,
           24.08.2026). A name that already stands is the same tour. */
        const named = new Set([...tours.values()].map((t) => t.name));
        for (const name of tourNames) {
            if (named.has(name)) continue;
            tours.set('n:' + name, { name, tag: '', count: 0, places: new Map(), id: null });
        }

        const rows = [...tours.entries()]
            .map(([key, t]) => ({ key, name: t.name, id: t.id, tag: t.tag, count: t.count,
                                   place: mostNamed(t.places) }))
            .sort(tourOrder);
        return { rows, without };
    }

    /* WHERE MOST OF IT LAY — one name, not a list: the second line of a
       row has room for a place, not for an itinerary. Ties go to the
       first, which is the earliest find of the outing. */
    /* ONE COMPARATOR FOR BOTH LISTS — tours and excursions sort by the
       same rule, or the two blocks under one pair of buttons would
       contradict each other. By time the newest first (the date decides,
       the name only breaks a tie); by name the German collation, which
       puts „Ärmelkanal" where a reader looks for it. */
    const tourOrder = (a, b) => tourSorting === 'name'
        ? a.name.localeCompare(b.name, 'de')
        : (String(b.tag ?? '').localeCompare(String(a.tag ?? ''))
           || a.name.localeCompare(b.name, 'de'));

    function mostNamed(counted) {
        let best = null, most = 0;
        for (const [name, n] of counted) {
            if (n > most) { best = name; most = n; }
        }
        return best;
    }

    /* EVERY WAY A DATE GETS TYPED (24.08.2026: „kann ich auch
       nur einen Monat und ein Jahr eingeben, z. B. 8.26?"). Measured
       before: no. The row shows „23.08.2026", and a plain substring
       search finds „08.2026" and „2026" in it, but never „8.26" — the
       zero and the long year sit in the way.

       So each row carries the other spellings as an INVISIBLE search
       text, exactly as point_haystack does for map sheets („4625" and
       „4625/2" both find). Nothing is guessed at the query end: what one
       types is compared as it stands, only the row knows more forms of
       itself. The month name comes along too — „august 2026" is how one
       says it out loud. */
    const MONTHS = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli',
                    'August', 'September', 'Oktober', 'November', 'Dezember'];

    function dateSpellings(tag) {
        if (!tag) return '';
        const [year, month, day] = tag.split('-');
        const shortYear = year.slice(2);
        const bareMonth = String(Number(month));
        const bareDay   = String(Number(day));
        const monthName = MONTHS[Number(month) - 1] ?? '';
        return [
            tag, `${year}-${month}`,
            `${day}.${month}.${year}`, `${bareDay}.${bareMonth}.${year}`,
            `${day}.${month}.${shortYear}`, `${bareDay}.${bareMonth}.${shortYear}`,
            `${month}.${year}`, `${bareMonth}.${year}`,
            `${month}.${shortYear}`, `${bareMonth}.${shortYear}`,
            `${monthName} ${year}`, monthName,
        ].join(' ');
    }

    /* Fetch the next helping as soon as the watchman is within reach.
       No IntersectionObserver: the column is its own scroll box, one
       listener on it answers the same question and needs no cleanup when
       the row is rebuilt — which happens on every keystroke. */
    let tourFeeding = null;
    function tourWatchman(marker, total) {
        tourFeeding?.();
        const check = () => {
            if (tourLimit >= total) return;
            const box = toursEl.getBoundingClientRect();
            const seen = marker.getBoundingClientRect().top - box.bottom < 200;
            if (!seen) return;
            tourLimit += TOUR_CHUNK;
            // The place stays: rebuilding empties the box and would
            // otherwise throw the reader back to the first row.
            const where = toursEl.scrollTop;
            drawTours();
            toursEl.scrollTop = where;
        };
        toursEl.addEventListener('scroll', check);
        tourFeeding = () => toursEl.removeEventListener('scroll', check);
        // At once as well: on a tall screen the watchman may already be
        // in sight, and then nobody would ever scroll.
        check();
    }

    /** One of the two sort buttons — the sidebar's shape and words. */
    function tourSortButton(type, label, iconName) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'sort-button' + (tourSorting === type ? ' is-active' : '');
        button.title = label;
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-pressed', String(tourSorting === type));
        button.append(icon(iconName));
        button.addEventListener('click', () => {
            tourSorting = type;
            settings.set('organizeTourSorting', type);
            drawTours();
        });
        return button;
    }

    function drawTours() {
        /* THE CARET STAYS, AND IT MUST SURVIVE EVERY REBUILD
           (24.08.2026: after each letter the focus was gone). It sat in
           the keystroke handler first, and that was one rebuild too
           early: the watchman at the end of the list fetches its next
           helping straight away on a tall screen — that is another
           drawTours(), and it happened AFTER the focus had been given
           back. Here it is the last word of every redraw, whoever
           triggered it. The same pattern as the filter's term rows
           (drawTermRows in filter.js). */
        const hadFocus = document.activeElement?.classList?.contains('organize-tour-search');
        const caret = hadFocus ? document.activeElement.selectionStart : null;

        toursEl.textContent = '';

        /* THE TWO BUTTONS ABOVE BOTH LISTS (24.08.2026): they
           govern tours AND excursions, so they stand above everything
           they order — under the column head, before the first row. */
        const sorting = document.createElement('div');
        sorting.className = 'organize-tour-sorting';
        const field = document.createElement('input');
        field.type = 'search';
        field.className = 'organize-tour-search';
        field.value = tourSearch;
        field.placeholder = t('Suchen …');
        field.setAttribute('aria-label', t('Touren und Exkursionen durchsuchen'));
        field.title = t('Sucht in Name, Tag und Gemeinde');
        field.addEventListener('input', () => {
            tourSearch = field.value;
            // A fresh result set starts at the top — an inherited limit
            // would hide rows the new search just let through.
            tourLimit = TOUR_CHUNK;
            drawTours();
        });
        sorting.append(
            field,
            tourSortButton('time', t('Nach Zeit — die neuesten zuerst'), 'clock'),
            tourSortButton('name', t('Nach Namen — von A bis Z'), 'az'),
        );
        toursEl.append(sorting);
        const { rows, without } = tourRows();

        const add = (key, name, count, data = '') => {
            const rowEl = document.createElement('div');
            rowEl.className = 'organize-tree-row'
                + (key !== 'alle' && state.tours.has(key) ? ' is-active' : '');
            rowEl.style.setProperty('--depth', 0);

            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'organize-tree-row-main';
            // The tick box, as in the list tree (see row there).
            rowEl.append(tickBox(
                (key === 'alle' ? state.tours.size === 0 : state.tours.has(key)) ? 'on' : 'off', name,
                key === 'alle' ? t('Alle Punkte zeigen') : t('Wählen oder abwählen'), () => b.click()));

            /* THE SIGN BEFORE THE NAME (24.08.2026): the hiker
               for a tour, the mountains for an excursion — the same two
               icons their tabs carry, so the row says at a glance which
               kind of thing it is. „Alle Punkte" and „Ohne Tour" are
               neither and get the EMPTY placeholder: without it their
               names would sit further left than the rest, and the column
               of names would stop being one (the same trap as the fold
               triangle, see CLAUDE.md). */
            const sign = document.createElement('span');
            sign.className = 'organize-row-icon';
            if (key.startsWith('e:')) sign.append(icon('mountain'));
            else if (key !== 'alle' && key !== 'none') sign.append(icon('hiker'));
            b.append(sign);

            /* TWO LINES (24.08.2026): the name above, day and
               municipality below. Beside each other they competed for the
               same narrow column — the name got shortened by the date,
               and the date said nothing without the place. Stacked, each
               line has the width of the whole column. */
            const lines = document.createElement('span');
            lines.className = 'organize-tree-lines';
            const nameEl = document.createElement('span');
            nameEl.className = 'organize-tree-name';
            nameEl.textContent = name;
            lines.append(nameEl);
            if (data) {
                const h = document.createElement('span');
                h.className = 'item-data organize-tree-meta';
                h.textContent = data;
                lines.append(h);
            }
            b.append(lines);
            const countEl = document.createElement('span');
            countEl.className = 'organize-tree-count';
            countEl.textContent = count;
            b.append(countEl);
            b.addEventListener('click', () => {
                // Toggling as in the tree; "Alle Punkte" clears the choice.
                if (key === 'alle') state.tours.clear();
                else if (state.tours.has(key)) state.tours.delete(key);
                else state.tours.add(key);
                drawAll();
            });
            rowEl.append(b);

            /* THE PENCIL, as on a list row (23.08.2026) — and
               with it the placeholder on the rows that carry none, so the
               column of numbers stays flush ("Alle Punkte" and "Ohne
               Tour" have nothing to edit; the same trap as the sidebar's
               fold triangle, see CLAUDE.md). */
            /* No pencil on an excursion row: it is groomed in Gemeinsam →
               Exkursionen — this here is the working view of its finds. */
            if (key !== 'alle' && key !== 'none' && !key.startsWith('e:')) {
                const pencil = document.createElement('button');
                pencil.type = 'button';
                pencil.className = 'symbol-button small';
                pencil.title = t('Tour bearbeiten');
                pencil.setAttribute('aria-label', t('Tour %s bearbeiten', name));
                pencil.append(icon('pencil'));
                pencil.addEventListener('click', () => tourMenu(key, name, pencil));
                rowEl.append(pencil);
            } else {
                const space = document.createElement('span');
                space.className = 'symbol-button pencil-space';
                space.setAttribute('aria-hidden', 'true');
                rowEl.append(space);
            }
            toursEl.append(rowEl);
        };

        /* Day and place as the second line — the name says what is meant,
           the date whether it is the right one, and the municipality
           where it was (the time filter's words, one line lower). */
        const secondLine = (tag, place) =>
            [tag ? timerange.readableDay(tag) : '', place].filter(Boolean).join(' · ');

        add('alle', t('Alle Punkte'), points.length);

        /* ONE LIST FOR BOTH (24.08.2026): tours and excursions
           lie mixed, ordered by the same buttons — the icon in front says
           which kind a row is, and that makes the „Exkursionen" heading
           that used to separate them pointless. It also told the truth
           only by accident: an excursion IS a tour here, that was the
           whole idea of putting them in this pane.

           „Ohne Tour" stays at the very end, whatever the sorting: it is
           not an outing but the remainder, and a remainder belongs after
           everything it is left over from. */
        const entries = [
            ...rows.map((r) => ({
                key: r.key, name: r.name, count: r.count,
                meta: secondLine(r.tag, r.place), tag: r.tag,
            })),
            ...excursions.map((e) => {
                /* The excursion's place comes from ITS finds — they lie in
                   the foreign index (the guests') or among one's own (a
                   leader who took part), so both are asked.
                   „teilgenommen" tells the two roles apart: a guest row
                   holds only the OWN finds, a led one all of them. */
                const places = new Map();
                for (const id of e.points) {
                    const place = (foreignById.get(id) ?? ownById.get(id))?.municipality;
                    if (place) places.set(place, (places.get(place) ?? 0) + 1);
                }
                return {
                    key: 'e:' + e.id, name: e.name, count: e.points.length, tag: e.tag,
                    meta: secondLine(e.tag, mostNamed(places))
                        + (e.as_guest ? t(' · teilgenommen') : (e.phase === 'läuft' ? t(' · läuft') : '')),
                };
            }),
        ].sort(tourOrder);

        /* „Ohne Tour" DIRECTLY BELOW „Alle Punkte" (24.08.2026):
           at the bottom it sank out of sight as soon as the list grew,
           and it is the one row one reaches for on purpose — everything
           that never got an outing. Both are buckets, not outings, so
           they belong together at the top, above the chronology. */
        if (without > 0) add('none', t('Ohne Tour'), without);

        /* SIFTED OVER WHAT THE ROW SHOWS — name and second line, in one
           text: whoever reads „Bornhagen" in a row may type it, and
           whoever remembers the day may type „23.08." Both work, because
           both stand there. Lowercased on both sides; the buckets above
           („Alle Punkte", „Ohne Tour") stay regardless — they are the way
           back out of a search that found nothing. */
        const wanted = tourSearch.trim().toLowerCase();
        const shown = wanted === ''
            ? entries
            : entries.filter((e) => (e.name + ' ' + e.meta + ' ' + dateSpellings(e.tag))
                .toLowerCase().includes(wanted));

        for (const entry of shown.slice(0, tourLimit)) {
            add(entry.key, entry.name, entry.count, entry.meta);
        }
        /* THE WATCHMAN AT THE END — it says how many are still waiting and
           fetches them when it comes into view. Same shape as the points
           table's: a plain row, no button, because scrolling is what
           triggers it and a button would promise a second way. */
        if (shown.length > tourLimit) {
            const more = document.createElement('div');
            more.className = 'organize-tour-more';
            more.textContent = t('… %s weitere', shown.length - tourLimit);
            toursEl.append(more);
            tourWatchman(more, shown.length);
        }
        /* A SEARCH THAT FINDS NOTHING SAYS SO. Without this line the
           column would simply end after „Ohne Tour", and that looks like
           an empty stock rather than a narrow search. */
        if (wanted !== '' && shown.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'hint-text small organize-tour-empty';
            empty.textContent = t('Keine Tour und keine Exkursion passt dazu.');
            toursEl.append(empty);
        }

        if (hadFocus) {
            const again = toursEl.querySelector('.organize-tour-search');
            again?.focus();
            if (again && caret !== null) again.setSelectionRange(caret, caret);
        }
    }

    /* Through submitForm and not past it, like submitTree — writing the
       fields by hand would be one more place that can miss one without
       saying so. */
    function submitTour(action, fields = {}) {
        submitForm(el('organize-tour-form'), {
            action,
            tour_from: fields.from ?? '',
            tour_to:   fields.to ?? '',
            // Which outing, not which name (migration 0079) — with
            // repeatable names the id is the only unambiguous answer.
            tour_from_id: fields.fromId ?? '',
            tour_to_id:   fields.toId ?? '',
        });
    }

    /* THE PENCIL'S THREE MOVES, in the order (23.08.2026):
       rename, merge, delete. All three end in the same server operation
       ('tour_set') — a tour is a label, so merging is a rename onto an
       existing name and deleting a rename onto nothing. What differs is
       the question asked here. */
    async function tourMenu(key, name, anchor) {
        // 'i:<id>' or 'n:<name>' — the row's key carries which outing is
        // meant; the id, where there is one, is what the server acts on.
        const rowId = key.startsWith('i:') ? key.slice(2) : '';
        const choice = await popupMenu({
            anchor,
            entries: [
                { value: 'name',   label: t('Umbenennen') },
                /* Only with somewhere to merge INTO: a lone tour would
                   open a window with an empty list. */
                ...(tourRows().rows.length > 1
                    ? [{ value: 'merge', label: t('Zusammenführen') }] : []),
                { value: 'delete', label: t('Löschen'), danger: true },
            ],
        });
        if (choice === 'name')   renameTour(key, name, rowId);
        if (choice === 'merge')  mergeTour(key, name, rowId);
        if (choice === 'delete') deleteTour(key, name, rowId);
    }

    async function renameTour(key, name, rowId) {
        const fresh = await prompt({
            title: t('Tour umbenennen'),
            label: t('Name'),
            value: name,
            button: t('Umbenennen'),
        });
        const wanted = (fresh ?? '').trim();
        if (wanted === '' || wanted === name) return;

        /* NO MERGE WARNING ANY MORE (migration 0079). Until today a tour
           WAS its name, so renaming onto an existing one swallowed the
           other outing and had to be asked about first. With identities
           two tours may share a name and stay two — renaming is now
           what it says, and merging has its own entry in the menu. */
        submitTour('tour_set', { from: name, to: wanted, fromId: rowId, toId: rowId });
    }

    function mergeTour(key, name, rowId) {
        const rows = tourRows().rows;
        const n = rows.find((r) => r.key === key)?.count ?? 0;
        /* THE CANDIDATES ARE ROWS, not names (migration 0079): two
           outings may be called the same, so the value carries the KEY
           and the label the name — otherwise one would pick a word and
           the other tour would answer. The day comes along in the text
           for exactly that case. */
        openTarget({
            title: t('Tour zusammenführen'),
            label: t('In welche Tour?'),
            button: t('Zusammenführen'),
            candidates: rows.filter((r) => r.key !== key)
                .map((r) => ({ value: r.key,
                               text: r.name + (r.tag ? ' · ' + timerange.readableDay(r.tag) : '') })),
            hint: (value) => {
                const target = rows.find((r) => r.key === value);
                return n === 1
                    ? t('Ein Punkt gehört danach zu „%s", und „%s" gibt es nicht mehr.', target?.name ?? '', name)
                    : t('%s Punkte gehören danach zu „%s", und „%s" gibt es nicht mehr.', n, target?.name ?? '', name);
            },
            done: (value) => {
                const target = rows.find((r) => r.key === value);
                submitTour('tour_set', {
                    from: name, to: target?.name ?? '',
                    fromId: rowId,
                    toId: target?.id ?? '',
                });
            },
        });
    }

    async function deleteTour(key, name, rowId) {
        const n = tourRows().rows.find((r) => r.key === key)?.count ?? 0;
        /* THE POINTS STAY, and that is the sentence this window exists
           for: a tour is a label, deleting it takes the label off — not
           the finds. Without the sentence "Löschen" beside a count of
           points reads like the trash. */
        const ok = await confirm({
            title: t('Tour löschen?'),
            text: n === 1
                ? t('„%s" verschwindet. Der Punkt bleibt — er steht danach unter „Ohne Tour".', name)
                : t('„%s" verschwindet. Die %s Punkte bleiben — sie stehen danach unter „Ohne Tour".', name, n),
            button: t('Löschen'),
            danger: true,
        });
        if (ok) submitTour('tour_set', { from: name, to: '', fromId: rowId });
    }

    /* THE CHOSEN POINTS INTO ONE TOUR — the counterpart of the lists
       dialog, and the way an empty tour gets filled. Available in BOTH
       panes (23.08.2026), which is what makes crosswise
       sorting possible: pick by tour, set lists — or pick by list, set
       the tour.

       ONE CHOICE, NOT TRI-STATE CHECKMARKS: a point hangs in many lists
       but is on exactly one outing, so there is nothing to mix and
       nothing to leave untouched. The empty entry takes the tour off —
       that is how a point leaves one without joining another. */
    /* THE EXCURSIONS STAND IN THE SAME LIST (12.09.2026: „ich
       würd dafür keinen neuen Button machen, sondern das bei Touren mit
       auflisten"). The left column already shows tours and excursions as
       one list; the dialog does the same. Only the excursions one LEADS —
       the ones taken part in as a guest are somebody else's to file into.
       An excursion target goes to its own server action (excursion_assign,
       own points only, the window reported and not enforced); a tour
       target to the tour's, exactly as before. */
    const ledExcursions = excursions.filter((e) => !e.as_guest);
    el('organize-tour-button').addEventListener('click', () => {
        const n = selection.size;
        const rows = tourRows().rows;
        /* MIXED AND SORTED LIKE THE LEFT COLUMN (27.09.2026,
           during an excursion still running: „sortier sie wie links,
           neueste zuerst"). The excursions used to follow ALL tours — with
           forty of them, today's excursion stood at the very end of the
           dropdown, and he took it for missing. tourOrder is the column's
           own comparator, so the dialog follows its buttons too: newest
           first by time, the German collation by name. */
        const outings = [
            ...rows.map((r) => ({ key: r.key, name: r.name, tag: r.tag,
                                  text: r.name + (r.tag ? ' · ' + timerange.readableDay(r.tag) : '') })),
            ...ledExcursions.map((e) => ({ key: 'e:' + e.id, name: e.name, tag: e.tag,
                text: t('Exkursion %s', e.name) + (e.tag ? ' · ' + timerange.readableDay(e.tag) : '') })),
        ].sort(tourOrder);
        openTarget({
            title: t('Tour setzen'),
            label: t('In welche Tour?'),
            button: t('Übernehmen'),
            /* The KEY is the value here too (migration 0079) — picking
               „Tagestour" out of two must land in the one meant, and the
               day beside the name is what tells them apart. */
            candidates: [
                ...outings.map((o) => ({ value: o.key, text: o.text })),
                { value: '', text: t('— keine Tour —') },
            ],
            preselect: outings[0]?.key ?? '',
            /* Both halves of the sentence follow the number — a "Ein Punkt
               … stehen" slipped through the first draft and was caught in
               the browser. */
            hint: (value) => {
                if (value.startsWith('e:')) {
                    const e = ledExcursions.find((x) => 'e:' + x.id === value);
                    return n === 1
                        ? t('Ein Punkt gehört danach zur Exkursion „%s" — auch wenn er außerhalb ihres Zeitfensters liegt.', e?.name ?? '')
                        : t('%s Punkte gehören danach zur Exkursion „%s" — auch die außerhalb ihres Zeitfensters.', n, e?.name ?? '');
                }
                const target = rows.find((r) => r.key === value);
                return value === ''
                    ? (n === 1
                        ? t('Ein Punkt trägt danach keine Tour mehr und steht unter „Ohne Tour".')
                        : t('%s Punkte tragen danach keine Tour mehr und stehen unter „Ohne Tour".', n))
                    : (n === 1
                        ? t('Ein Punkt gehört danach zu „%s". Eine Tour, die er bisher trug, gibt er damit auf.', target?.name ?? '')
                        : t('%s Punkte gehören danach zu „%s". Eine Tour, die sie bisher trugen, geben sie damit auf.', n, target?.name ?? ''));
            },
            done: (value) => {
                if (value.startsWith('e:')) {
                    submitForm(el('organize-excursion-form'), { excursion_to: value.slice(2) });
                    return;
                }
                const target = rows.find((r) => r.key === value);
                submitTour('tour_assign', { to: target?.name ?? '', toId: target?.id ?? '' });
            },
        });
    });

    /* THE EXPORT — what we can read we can write (includes/export.php).
       Formats live there; this only asks which one and what for.

       WHAT GOES OUT: the checked points if any are checked, otherwise
       everything the table currently shows. That is the usual case —
       whoever filters wants exactly that filtered set out — and the
       dialog says which of the two it will be, so nobody has to guess
       from the file afterwards.

       THE FORMATS STAY A DROPDOWN, on purpose. The rule „a few options
       are radios, in sight" (CLAUDE.md) was put to the maintainer for these
       five on 26.09.2026, right after the find report's questions became
       radios — his answer: „Nein, dort nicht". Nobody needs to ask again. */
    const EXPORT_FORMATS = [
        { value: 'csv',     text: t('CSV-Tabelle (Excel, LibreOffice)') },
        { value: 'geojson', text: t('GeoJSON (QGIS, uMap, geojson.io)') },
        { value: 'kml',     text: t('KML (Google Earth)') },
        { value: 'gpx',     text: t('GPX (GPS-Geräte)') },
        // The sheet for the DGfM's mapping — includes/mykis.php says what
        // goes in and, above all, what stays out.
        { value: 'mykis',   text: t('MykIS-Tabelle (Pilzkartierung der DGfM)') },
        /* THE REPORT IS NOT IN THIS LIST ANY MORE (23.08.2026):
           it is not a data format but a document one configures, and as
           a fifth entry in a format dropdown nobody found it. It has its
           own button beside this one. */
    ];

    /* THE REPORT'S SECOND STEP. It starts with the columns the table
       currently shows — whoever has chosen what to see has chosen what to
       print — and remembers the rest of the settings in the bundle, like
       the column choice beside it: a report is made again and again with
       the same shape. */
    const reportDialog = el('organize-report-dialog');
    /* ABBRECHEN CLOSES BY HAND, like in every other window here. The
       button is type="button" — as a submit it would build the report —
       and nothing was listening to it, so it sat there dead until
       24.08.2026. It went unnoticed because Escape always worked. */
    reportDialog.querySelector('.dialog-footer .subtle')
        .addEventListener('click', () => reportDialog.close());

    el('organize-report-button').addEventListener('click', () => {
        const chosen = [...selection];
        const ids = (chosen.length > 0 ? chosen : visiblePoints().map((p) => p.id))
            .map(Number).filter((id) => id > 0);
        openReport(ids, ids.filter((id) => foreignById.has(id)).length);
    });

    function openReport(ids, foreign) {
        el('organize-report-hint').textContent =
            (ids.length === 1 ? t('Ein Fund kommt in den Bericht.') : t('%s Funde kommen in den Bericht.', ids.length))
            + (foreign > 0
                ? (foreign === 1
                    ? t(' Einer davon ist ein fremder Fund — er steht mit dem Namen seines Finders darin.')
                    : t(' %s davon sind fremde Funde — sie stehen mit dem Namen ihrer Finder darin.', foreign))
                : '');

        const stored = settings.get('reportColumns', null);
        const wanted = Array.isArray(stored) && stored.length > 0 ? stored : shownKeys;

        const box = el('organize-report-columns');
        box.textContent = '';
        /* 'title' leads and is not up for choice — a report row without
           the find's name would be unreadable, the same argument the
           table makes about its first column. In the tiles form it is the
           headline of the tile. Everything after it is ordered by the
           arrows, and the order of the rows is the order of the columns
           in the report (24.08.2026: the ticks alone said WHAT
           should be printed, never in which order — so the report always
           followed the table's order, even where a report wants another
           one). The chosen ones stand at the top in their stored order,
           the rest below as the catalogue lists them. */
        const keys = ['title', ...wanted.filter((k) => k !== 'title'),
            ...catalogue.map((c) => c.key).filter((k) => k !== 'title' && !wanted.includes(k))];
        for (const key of keys) {
            const column = columnByKey.get(key);
            if (!column) continue;
            box.append(columnChoiceRow(key, column.label, {
                checked: key === 'title' || wanted.includes(key),
                fixed: key === 'title',
            }));
        }

        /* THE LAST CHOICE STANDS CHOSEN — radios since 26.09.2026 („die
           Fragen überall so"); an unknown stored value falls back to the
           first answer rather than to none. */
        const pick = (name, value) => {
            const answer = reportDialog.querySelector(`input[name="${name}"][value="${value}"]`)
                ?? reportDialog.querySelector(`input[name="${name}"]`);
            answer.checked = true;
        };
        pick('report-layout', settings.get('reportLayout', 'list'));
        pick('report-photos', settings.get('reportPhotos', 'none'));
        el('organize-report-map').checked   = settings.get('reportMap', false) === true;
        el('organize-report-merge').checked = settings.get('reportMerge', false) === true;
        el('organize-report-german').checked = settings.get('reportGerman', false) === true;

        const shape = reportDialog.querySelector('form');
        const onSubmit = (e) => {
            e.preventDefault();
            shape.removeEventListener('submit', onSubmit);
            reportDialog.close();

            const columns = [...box.querySelectorAll('input:checked')].map((c) => c.value);
            const layout  = reportDialog.querySelector('input[name="report-layout"]:checked')?.value ?? 'list';
            const photos  = reportDialog.querySelector('input[name="report-photos"]:checked')?.value ?? 'none';
            const withMap = el('organize-report-map').checked;
            const merge   = el('organize-report-merge').checked;
            const german  = el('organize-report-german').checked;
            settings.set('reportColumns', columns);
            settings.set('reportGerman', german);
            settings.set('reportLayout', layout);
            settings.set('reportPhotos', photos);
            settings.set('reportMap', withMap);
            settings.set('reportMerge', merge);

            const form = el('organize-report-form');
            form.querySelector('[name="points"]').value = ids.join(',');
            form.querySelector('[name="layout"]').value = layout;
            form.querySelector('[name="photos"]').value = photos;
            form.querySelector('[name="map"]').value    = withMap ? '1' : '';
            form.querySelector('[name="merge"]').value  = merge ? '1' : '';
            // 'german' needs no line here: the checkbox is a member of the
            // report form itself (form="…" in the markup) and travels on
            // its own — see the reasoning at the checkbox.
            // The chosen columns as a repeated field — rebuilt every time,
            // or a second report would carry the first one's ticks too.
            const fields = el('organize-report-fields');
            fields.textContent = '';
            for (const key of columns) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'columns[]';
                input.value = key;
                fields.append(input);
            }
            form.submit();
        };
        shape.addEventListener('submit', onSubmit);
        reportDialog.addEventListener('close', () => shape.removeEventListener('submit', onSubmit),
            { once: true });
        reportDialog.showModal();
    }

    el('organize-export-button').addEventListener('click', () => {
        const chosen  = [...selection];
        const shown   = visiblePoints();
        /* FOREIGN POINTS GO ALONG (23.08.2026): a find report
           of an excursion lists what the GROUP found. What may leave is
           what this account may see, and the server checks that itself
           through the containers (export_points) — here nothing is sifted
           out, so the file holds what the table shows. */
        const ids = (chosen.length > 0 ? chosen : shown.map((p) => p.id))
            .map(Number).filter((id) => id > 0);
        const foreign = ids.filter((id) => foreignById.has(id)).length;

        openTarget({
            title: t('Export'),
            label: t('In welches Format?'),
            button: t('Herunterladen'),
            candidates: EXPORT_FORMATS,
            preselect: 'csv',
            hint: (value) => (chosen.length > 0
                    ? (ids.length === 1 ? t('Der gewählte Punkt kommt in die Datei.')
                                        : t('Die %s gewählten Punkte kommen in die Datei.', ids.length))
                    : (ids.length === 1 ? t('Der eine Punkt der Tabelle kommt in die Datei.')
                                        : t('Alle %s Punkte der Tabelle kommen in die Datei.', ids.length)))
                + (foreign > 0
                    ? (foreign === 1
                        ? t(' Einer davon ist ein fremder Fund aus einer Freigabe — er kommt mit, mit dem Namen seines Finders.')
                        : t(' %s davon sind fremde Funde aus einer Freigabe — sie kommen mit, mit dem Namen ihrer Finder.', foreign))
                    : ''),
            /* WHAT THE MYKIS SHEET LEAVES OUT belongs here, before the
               choice is made — not in a message after an empty file. BEHIND
               A QUESTION MARK since 28.09.2026 („mach das so mit
               dem Fragezeichen"), the same question and the same sentences
               as in the DGfM tab: a paragraph over the questions was read
               by nobody. */
            explain: (value) => (value !== 'mykis' ? null : {
                title: t('Welche Funde gehen mit?'),
                lead: t('Ein Fund geht nur mit, wenn alles zutrifft:'),
                items: [
                    t('gefunden in Deutschland,'),
                    t('mit Fundtag,'),
                    t('mit einem Namen aus der Artenliste der DGfM,'),
                    t('sicher bestimmt: makroskopisch, mikroskopisch oder genetisch.'),
                ],
                tail: [
                    t('Hast du nichts angegeben, gilt ein Fund als sicher — außer im Namen steht cf., aff. oder sp. Sammelarten mit agg. und Namen mit s. l. gehen mit.'),
                    ...(mykis?.excluded ? [t(' Was du unter DGfM-Kartierung → Genauigkeit ausgenommen hast, kommt nicht mit.').trim()] : []),
                ],
            }),
            /* THE MYKIS SHEET ASKS ITSELF (27.09.2026): how
               precisely, and whose finds — in the window, where the sheet
               is made, and no longer silently from the settings, which now
               open only with the consent. Preselected with the account's
               own choices; „Nach deinen Regeln" only for whoever agreed,
               because the rules live behind the consent (mykis.php,
               mykis_export_choices). */
            questions: (value) => (value !== 'mykis' || !mykis ? [] : [
                {
                    name: 'precision',
                    legend: t('Wie genau soll der Fundort mitgehen?'),
                    options: [
                        ...(mykis.consented ? [{ value: 'rules', text: t('Nach deinen Regeln unter DGfM-Kartierung') }] : []),
                        ...mykis.steps,
                    ],
                    chosen: mykis.consented ? 'rules' : mykis.precision,
                },
                /* YOUR OWN FINDS ALWAYS, THE OTHERS BY TWO TICKS — the same
                   two as under DGfM-Kartierung (0144,
                   27.09.2026): the finds you determined, and the rest,
                   the lists you were given to enter. Ticks, not answers:
                   each adds a group, neither excludes the other. */
                {
                    name: 'others',
                    legend: t('Welche Funde sollen in die Tabelle?'),
                    note: t('Deine eigenen Funde immer. Dazu:'),
                    ticks: [
                        { name: 'determined', text: t('Auch Funde, bei denen ich nicht Finder, aber Bestimmer bin'), checked: !!mykis.determined },
                        { name: 'foreign', text: t('Auch Funde, bei denen ich weder Finder noch Bestimmer bin'), checked: !!mykis.foreign },
                    ],
                },
            ]),
            done: (value, answers) => {
                const form = el('organize-export-form');
                form.querySelector('[name="points"]').value = ids.join(',');
                form.querySelector('[name="format"]').value = value;
                // Empty for every other format — the server asks only for
                // MykIS, and an empty answer is no answer.
                form.querySelector('[name="precision"]').value = answers.precision ?? '';
                form.querySelector('[name="determined"]').value = answers.determined ?? '';
                form.querySelector('[name="foreign"]').value = answers.foreign ?? '';
                form.submit();
            },
        });
    });

    function drawTree() {
        drawPanes();
        drawTours();
        treeEl.textContent = '';
        /* 'reference' MARKS THE CONTAINER SECTION (22.08.2026:
           the same list stood there looking like a second original, was
           marked along with it, and its pencil offered rename and move —
           on a row that is only a pointer). A reference row is italic,
           the same convention as the map's sidebar: the row says itself
           that it is not the thing. It carries no pencil — the thing is
           edited where it lives, one row further up — and it has a
           selection key of its own, so marked is always exactly the row
           that was clicked. No explaining text anywhere; the slant and
           the missing pencil say it (the maintainer: designed so you see it,
           not told). */
        /* 'fold' MAKES A ROW COLLAPSIBLE — the sidebar's concept, carried
           over whole (22.08.2026: one concept through the whole
           page). fold.heading says the row IS a heading: a list with
           sublists holds nothing (CLAUDE.md), so there is nothing to
           select — the whole row folds, as on the map since 20.08. A
           container folds too but stays selectable (its view is a real
           set), so only its triangle folds.

           THE ARROW COLUMN IS ALSO THERE WHERE THERE IS NOTHING TO FOLD —
           empty then. Otherwise rows without sublists would shift left by
           its width, and the indent that shows the level would mean two
           things (the sidebar's own rule, quoted in CLAUDE.md). */
        const row = (key, name, count, depth, color, hint = null, reference = false, fold = null, symbol = 'circle') => {
            const active = key === 'alle'
                ? state.lists.size === 0
                : state.lists.has(key);

            /* THE SIDEBAR'S LIGHT RULE (map.js, carried over word for
               word): collapsed, a heading lights up as soon as something
               below it is chosen — you see at a glance that the branch has
               something on without expanding it. Expanded, the leaves
               light up themselves and the heading stays quiet. */
            const lit = fold?.heading
                ? (fold.collapsed && fold.lit === true)
                : active;

            const rowEl = document.createElement('div');
            rowEl.className = 'organize-tree-row'
                + (reference ? ' is-reference' : '')
                + (fold?.heading ? ' is-heading' : '')
                + (lit ? ' is-active' : '');
            rowEl.style.setProperty('--depth', depth);

            if (fold) {
                const arrow = document.createElement('button');
                arrow.type = 'button';
                arrow.className = 'organize-fold' + (fold.collapsed ? '' : ' is-open');
                arrow.title = fold.collapsed ? t('Aufklappen') : t('Einklappen');
                arrow.setAttribute('aria-expanded', String(!fold.collapsed));
                arrow.setAttribute('aria-label', fold.collapsed ? t('%s aufklappen', name) : t('%s einklappen', name));
                arrow.append(icon('fold-triangle', { class: 'collapse-triangle' }));
                arrow.addEventListener('click', (e) => { e.stopPropagation(); fold.toggle(); });
                rowEl.append(arrow);
            } else {
                const space = document.createElement('span');
                space.className = 'organize-fold fold-space';
                space.setAttribute('aria-hidden', 'true');
                rowEl.append(space);
            }

            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'organize-tree-row-main';
            /* THE SIDEBAR'S TICK BOX, IN FRONT (28.09.2026: „bei
               Organisieren ist noch das alte Markieren da … können wir da
               auch die Hakenvariante machen?"). It says what the frame
               round a chosen row used to say, and — on a heading — what no
               row could: SOME of the branch is chosen. A leaf's box does
               what a click on the row does; a heading's switches its whole
               branch, while the heading's row keeps folding. „Alle Punkte"
               is ticked while nothing is chosen, and its box clears the
               choice. Counts, owners and hints stay where they were. */
            const leaves = fold?.heading ? (fold.leaves ?? []) : null;
            rowEl.prepend(leaves
                ? tickBox(tickOf(state.lists, leaves), name, t('Alle Listen darunter wählen oder abwählen'),
                    leaves.length ? () => {
                        const on = leaves.some((k) => !state.lists.has(k));
                        for (const k of leaves) on ? state.lists.add(k) : state.lists.delete(k);
                        drawAll();
                    } : null)
                : tickBox(active ? 'on' : 'off', name,
                    key === 'alle' ? t('Alle Punkte zeigen') : t('Wählen oder abwählen'), () => b.click()));
            /* THE LIST'S OWN DRAWING instead of a bare dot
               (22.08.2026): the same shape the sidebar and the map markers
               show — mushroom, deer, lichen … — chosen by whoever owns
               the list. shapes.js draws it; a heading carries none, as
               everywhere (a heading holds nothing, so it has no shape). */
            if (color && !fold?.heading) {
                const dotEl = document.createElement('span');
                dotEl.className = 'organize-point-shape';
                dotEl.innerHTML = shapePoint(color, symbol);
                b.append(dotEl);
            }
            const nameEl = document.createElement('span');
            nameEl.className = 'organize-tree-name';
            nameEl.textContent = name;
            b.append(nameEl);
            if (hint) {
                const h = document.createElement('span');
                h.className = 'item-data';
                /* A LITTLE PERSON INSTEAD OF A BULLET in the container
                   section — the sidebar's words (map.js, 20.08.2026): the
                   figure says what the word IS, somebody, and it carries
                   „meine" too. Only there: the own tree's hint („·
                   automatisch") is not a person and keeps its dot. */
                if (reference) h.append(icon('person'), document.createTextNode(hint));
                else h.textContent = hint;
                b.append(h);
            }
            /* THE TOTAL ONLY WHILE FOLDED (22.08.2026, in two
               steps): open, the leaves stand there with their own numbers
               and the branch sum reads like one more choosable size —
               folded, the leaves are hidden and the sum is the one thing
               the row can still say. The sidebar argues the same way
               about its collapsed headings. Leaves, „Alle Punkte" and
               „Ohne Liste" always keep their numbers. */
            const countEl = document.createElement('span');
            countEl.className = 'organize-tree-count';
            /* Folded with leaves chosen inside: what THEY contribute, not
               the branch total — see countForKeys above. Open, a heading
               shows a DASH instead of nothing (22.08.2026):
               the empty spot read like a missing value, the dash says
               "look below, the numbers stand at the leaves". */
            countEl.textContent = !fold?.heading ? count
                : !fold.collapsed ? '–'
                : fold.chosenCount !== null ? fold.chosenCount
                : count;
            b.append(countEl);
            b.addEventListener('click', () => {
                // Each row toggles on and off, "Alle Punkte" clears the
                // choice. The point selection on the right stays put —
                // drawState clears only what the filter no longer
                // shows.
                if (fold?.heading) { fold.toggle(); return; }
                if (key === 'alle') state.lists.clear();
                else if (state.lists.has(key)) state.lists.delete(key);
                else state.lists.add(key);
                drawAll();
            });
            rowEl.append(b);

            // The pencil opens the list's hand moves — renaming, moving,
            // merging, deleting. It replaces the button bar that hung at
            // the bottom of the CHOSEN list: since several can be chosen,
            // the tool belongs on the row, like the pencil in the map's
            // sidebar.
            //
            // THE SPACE IS ALSO THERE WHERE THERE IS NOTHING TO EDIT — then
            // empty. Otherwise the count at "Alle Punkte", "Ohne Liste"
            // and the foreign branches would shift right by the pencil's
            // width, and the column of numbers would fall apart (the same
            // trap as with the sidebar's fold triangle, see CLAUDE.md).
            // The empty placeholder carries the same class and thus the
            // same width — including the 44 px of the phone breakpoint,
            // without a second number.
            if (!reference && typeof key === 'number' && listById.has(key)) {
                const pencil = document.createElement('button');
                pencil.type = 'button';
                pencil.className = 'symbol-button small';
                pencil.title = t('Liste bearbeiten');
                pencil.setAttribute('aria-label', t('Liste %s bearbeiten', name));
                pencil.append(icon('pencil'));
                pencil.addEventListener('click', () => listMenu(key, pencil));
                rowEl.append(pencil);
            } else if (!reference) {
                const space = document.createElement('span');
                space.className = 'symbol-button pencil-space';
                space.setAttribute('aria-hidden', 'true');
                rowEl.append(space);
            }
            /* NO PLACEHOLDER IN THE CONTAINER SECTION
               (22.08.2026): references never carry a pencil, so there is no
               column of numbers to keep straight against one — the count
               moves flush to the edge and the name gets the room. The
               placeholder rule holds only where pencils EXIST in the same
               section. */

            treeEl.append(rowEl);
        };

        /* THE HONEST NUMBER OF A FOLDED BRANCH (22.08.2026):
           with leaves chosen inside, the head shows what those leaves
           contribute right now — not the branch total, which would claim
           more than the table shows. As a UNION, not a sum: a point may
           hang in several of the chosen leaves and must count once, the
           same arithmetic matches() uses. Foreign points carry a prefix so
           their ids cannot collide with own ones. */
        const countForKeys = (keys) => {
            const inside = new Set();
            for (const key of keys) {
                if (typeof key === 'number' || key.startsWith('r:')) {
                    const branch = branchSet(typeof key === 'number' ? key : listIdOf(key));
                    for (const p of points) if (p.lists.some((x) => branch.has(x))) inside.add(p.id);
                } else {
                    const branch = foreignBranches.get(listIdOf(key)) ?? new Set();
                    for (const p of foreignPoints) if (p.lists.some((x) => branch.has(x))) inside.add('f' + p.id);
                }
            }
            return inside.size;
        };

        // The chosen leaves of an own branch. Leaves carry plain ids.
        const ownChosen = (id) => {
            const branch = branchSet(id);
            return [...state.lists].filter((k) => typeof k === 'number' && branch.has(k));
        };

        // Every leaf of an own branch — what a heading's box switches.
        const ownLeafIds = (id) => {
            const out = [];
            (function collect(parentId) {
                for (const l of drawableKids(parentId)) {
                    if (drawableKids(l.id).length > 0) collect(l.id);
                    else out.push(l.id);
                }
            })(id);
            return out;
        };

        const foldOwn = (id) => {
            const chosen = ownChosen(id);
            return {
            heading: true,
            leaves: ownLeafIds(id),
            lit: chosen.length > 0,
            chosenCount: chosen.length > 0 ? countForKeys(chosen) : null,
            collapsed: collapsedOwn.has(id),
            toggle: () => {
                if (collapsedOwn.has(id)) collapsedOwn.delete(id); else collapsedOwn.add(id);
                settings.set('collapsedLists', [...collapsedOwn]);
                drawTree();
            },
            };
        };
        const foldShared = (key, heading, chosen = [], leaves = []) => ({
            heading,
            leaves,
            lit: chosen.length > 0,
            chosenCount: chosen.length > 0 ? countForKeys(chosen) : null,
            collapsed: collapsedShared.has(key),
            toggle: () => {
                if (collapsedShared.has(key)) collapsedShared.delete(key); else collapsedShared.add(key);
                settings.set('collapsedShared', [...collapsedShared]);
                drawTree();
            },
        });

        row('alle', t('Alle Punkte'), points.length, 0, null);

        /* THE TWO SECTION HEADS OF THE SIDEBAR, HERE TOO
           (12.09.2026: „bei Organisieren in der Listenansicht bitte auch
           meine Listen und geteilt im gleichen Style wie in der
           Sidebar"). „Meine Listen" over the own tree, „Geteilt" over
           the containers — the same band, the same fold, the same pill
           „n/m" of chosen leaves while folded, lit from the first
           checkmark. The keys live beside the container keys in
           collapsedShared ('own', 'containers'); ids there are numbers
           or 'c<id>', so the words cannot collide. */
        const sectionHead = (label, key, choice) => {
            const collapsed = collapsedShared.has(key);
            const head = document.createElement('button');
            head.type = 'button';
            head.className = 'organize-tree-title is-foldable'
                + (collapsed && choice.checked > 0 ? ' is-active' : '');
            head.setAttribute('aria-expanded', String(!collapsed));
            head.append(icon('fold-triangle', { class: 'collapse-triangle' }),
                document.createTextNode(label));
            if (!collapsed) head.querySelector('.collapse-triangle').classList.add('is-turned');
            if (collapsed && choice.total > 0) {
                const pill = document.createElement('span');
                pill.className = 'list-row-selected' + (choice.checked > 0 ? ' is-active' : '');
                pill.textContent = `${choice.checked}/${choice.total}`;
                pill.title = t('%d von %d Listen gewählt', choice.checked, choice.total);
                head.append(pill);
            }
            head.addEventListener('click', () => {
                if (collapsed) collapsedShared.delete(key);
                else collapsedShared.add(key);
                settings.set('collapsedShared', [...collapsedShared]);
                drawTree();
            });
            treeEl.append(head);
            return collapsed;
        };

        // The own leaves — what can be chosen — for the pill.
        const ownLeaves = ['none'];
        (function collect(parentId) {
            for (const l of childrenOf(parentId)) {
                if (l.is_container_list) continue;
                if (drawableKids(l.id).length > 0) collect(l.id);
                else ownLeaves.push(l.id);
            }
        })(null);
        const ownCollapsed = sectionHead(t('Meine Listen'), 'own', {
            checked: ownLeaves.filter((k) => state.lists.has(k)).length,
            total: ownLeaves.length,
        });

        const descend = (parentId, depth, items = childrenOf(parentId)) => {
            for (const l of items) {
                // Container lists (migration 0051) only down at the container.
                if (l.is_container_list) continue;
                const kids = drawableKids(l.id).length > 0;
                /* A list with sublists is a heading and folds instead of
                   selecting — the collective branch choice that used to
                   sit on this row is gone with it (22.08.2026:
                   fold like the sidebar, not Sammelauswahl). The count
                   stays the branch sum, as on the map. */
                row(l.id, l.name, branchCount(l.id), depth, l.color,
                    l.is_autolist ? t('· automatisch') : null,
                    false, kids ? foldOwn(l.id) : null, l.symbol);
                if (!kids || !collapsedOwn.has(l.id)) descend(l.id, depth + 1);
            }
        };
        /* THE MAPPEN (0168), the sidebar's top row carried over: Mappen
           and loose lists in one order, a Mappe folding like a heading
           under the same key ('f' + id in collapsedLists) — fold it on the
           map and it is folded here. Its count is the union of its lists'
           points, a point in two of them counting once. */
        const drawTop = () => {
            const loose = [];
            const inFolder = new Map(folders.map((f) => [f.id, []]));
            for (const l of childrenOf(null)) {
                if (l.folder_id != null && inFolder.has(l.folder_id)) inFolder.get(l.folder_id).push(l);
                else loose.push({ l, sort: l.sort_order ?? 0, name: l.name });
            }
            const items = [...loose, ...folders.map((f) => ({ f, sort: f.sort_order ?? 0, name: f.name }))]
                .sort((a, b) => (a.sort - b.sort) || String(a.name).localeCompare(String(b.name), 'de'));
            for (const item of items) {
                if (item.l) { descend(null, 0, [item.l]); continue; }
                const key = 'f' + item.f.id;
                const inside = inFolder.get(item.f.id).filter((l) => !l.is_container_list);
                const leaves = inside.flatMap((l) => drawableKids(l.id).length > 0 ? ownLeafIds(l.id) : [l.id]);
                const chosen = leaves.filter((k) => state.lists.has(k));
                const ids = new Set(inside.flatMap((l) => [...branchSet(l.id)]));
                row(key, item.f.name, points.filter((p) => p.lists.some((x) => ids.has(x))).length, 0, null, null, false, {
                    heading: true,
                    leaves,
                    lit: chosen.length > 0,
                    chosenCount: chosen.length > 0 ? countForKeys(chosen) : null,
                    collapsed: collapsedOwn.has(key),
                    toggle: () => {
                        if (collapsedOwn.has(key)) collapsedOwn.delete(key); else collapsedOwn.add(key);
                        settings.set('collapsedLists', [...collapsedOwn]);
                        drawTree();
                    },
                });
                if (!collapsedOwn.has(key)) descend(null, 1, inside);
            }
        };
        if (!ownCollapsed) {
            row('none', t('Ohne Liste'), points.filter(isWithoutList).length, 0, null);
            drawTop();
        }

        // The containers beneath, as in the map's sidebar: one row per
        // container, beneath it the mounted branches with the sharer's
        // name at the root. An OWN mounted list carries its ordinary id —
        // the same row as up in the tree, just sorted in where it hangs in
        // the container.
        /* THE SECTION LABEL FOLDS THE WHOLE SECTION
           (22.08.2026) — same triangle, same light rule as every head:
           collapsed it lights up when anything inside is chosen, open the
           chosen rows show themselves. The key lives beside the container
           keys in collapsedShared; ids there are numbers or 'c<id>', so
           the word cannot collide. */
        if (shared.length === 0) return;
        // The shared leaves across every container — the pill's numbers.
        const sharedLeaves = [];
        for (const c of shared) {
            const keyOf = (l) => (listById.has(l.id) ? 'r:' : 'f:') + c.id + ':' + l.id;
            for (const f of c.shares) {
                (function collect(id) {
                    for (const ch of f.children.get(id) ?? []) {
                        if ((f.children.get(ch.id) ?? []).length > 0) collect(ch.id);
                        else sharedLeaves.push(keyOf(ch));
                    }
                })(null);
            }
        }
        // „Geteilt", the sidebar's word — „Freigaben" said the same thing
        // in the other place's words.
        const sectionCollapsed = sectionHead(t('Geteilt'), 'containers', {
            checked: sharedLeaves.filter((k) => state.lists.has(k)).length,
            total: sharedLeaves.length,
        });
        if (sectionCollapsed) return;
        for (const c of shared) {
            /* A CONTAINER CANNOT BE SELECTED AS A WHOLE any more
               (22.08.2026: „Man selektiert immer nur die
               Listen"). Its row folds, like a heading — the content is
               reached through the branches inside. The container-view
               selection ('c:') that used to sit on this row is gone with
               it. */
            const keyOf = (l) => (listById.has(l.id) ? 'r:' : 'f:') + c.id + ':' + l.id;
            const subtreeKeys = (f, id, acc) => {
                for (const ch of f.children.get(id) ?? []) {
                    acc.push(keyOf(ch));
                    subtreeKeys(f, ch.id, acc);
                }
                return acc;
            };
            const containerChosen = c.shares.flatMap((f) =>
                subtreeKeys(f, null, []).filter((k) => state.lists.has(k)));
            // The leaves below an entry of a share — what a box switches.
            const leafKeys = (f, id, acc = []) => {
                for (const ch of f.children.get(id) ?? []) {
                    if ((f.children.get(ch.id) ?? []).length > 0) leafKeys(f, ch.id, acc);
                    else acc.push(keyOf(ch));
                }
                return acc;
            };

            row('c:' + c.id, c.name, c.own_points.length + c.foreign_points.length, 0,
                null, c.is_mine ? null : shortOf(c.owner), true,
                foldShared('c' + c.id, true, containerChosen, c.shares.flatMap((f) => leafKeys(f, null))));
            if (collapsedShared.has('c' + c.id)) continue;
            for (const f of c.shares) {
                const branch = (parentId, depth) => {
                    for (const l of f.children.get(parentId) ?? []) {
                        /* At the branch root „meine" instead of one's own
                           name — the sidebar's wording (map.js,
                           20.08.: the name would be the one entry that
                           tells the reader what he already knows). Foreign
                           sharers keep their short name. */
                        const hint = parentId !== null ? null
                            : (f.is_mine ? 'meine' : shortOf(f.display_name));
                        const kids = (f.children.get(l.id) ?? []).length > 0;
                        const lFold = kids
                            ? foldShared(l.id, true,
                                subtreeKeys(f, l.id, []).filter((k) => state.lists.has(k)),
                                leafKeys(f, l.id))
                            : null;
                        if (listById.has(l.id)) {
                            /* 'r:' AND NOT THE BARE ID
                               (22.08.2026: „nur den markieren, den ich
                               angeklickt habe"). With the shared key the
                               original above lit up whenever this row was
                               chosen and the other way round — two rows
                               claiming one choice. Each appearance now
                               carries its own key; that they show the same
                               branch is resolved where keys become point
                               sets (matches) and where they leave the page
                               (stateFields translates back to the number,
                               so server and autolist keep seeing lists). */
                            row(keyOf(l), l.name, branchCount(l.id), depth, listById.get(l.id).color, hint, true, lFold, listById.get(l.id).symbol);
                        } else {
                            row(keyOf(l), l.name, foreignBranchCount(l.id), depth, l.color, hint, true, lFold, l.symbol);
                        }
                        if (!kids || !collapsedShared.has(l.id)) branch(l.id, depth + 1);
                    }
                };
                branch(null, 1);
            }
        }
    }

    // -----------------------------------------------------------------
    // The picture viewer — THE MAP'S OWN since 30.09.2026 (photowindow.js;
    // the maintainer, on finding the two were different windows: „Das wäre ja
    // irgendwie sinnvoll"). Until then Organisieren built a small one
    // here: zoom, leaf through, delete. Now the same window opens as on
    // the map — name, scale, measuring, the strip, the statistics — and
    // this page is its second HOST (photowindow.js lists what a host is
    // asked).
    //
    // THE ROW CARRIES ONLY KEYS ('photo:63', 'cloud:17' —
    // organize_pictures), the viewer wants the full records: name, scale,
    // measures, dimensions, photographer. They are fetched when a picture
    // is opened, the two answers the map's gallery asks for — photos.php
    // for the voucher photos, cloud.php's listing for the recordings.
    //
    // DELETING stays what it was here: one's own pictures on one's own
    // finds, and every picture on a guest's excursion find — Organisieren
    // is the leader's sorting-out place (api/v1/photos.php, „THE LEADER
    // SORTS OUT"). mayDelete says so, because the map's rule alone would
    // hide the button at a picture that is the guest's.
    // -----------------------------------------------------------------

    /* EVERY PICTURE OF THE FIND, recordings included (30.09.2026) — the
       keys the server hands out in the gallery's order
       (organize_pictures), or, from a page that predates them, the
       voucher photos alone. */
    const picturesOf = (p) => p.pictures ?? (p.photos ?? []).map((id) => 'photo:' + id);
    const pictureOf = (key) => {
        const [kind, id] = key.split(':');
        return { cloud: kind === 'cloud', id: Number(id) };
    };
    // Relative to pages/ — this table lives on the administration page,
    // next door to both deliveries.
    const pictureUrl = (key, small) => {
        const { cloud, id } = pictureOf(key);
        return cloud
            ? 'cloud_thumb.php?id=' + id + '&c=1' + (small ? '' : '&large=1')
            : 'photo.php?id=' + id + (small ? '&k=1' : '');
    };

    /* The find in the window: its table row, and the records fetched for
       it. The viewer asks the host for „the find in the window" and „a
       find by id" — both are this one. */
    const inWindow = { row: null, point: null };
    // Mounted on the first picture opened, not at load: most visits here
    // never open one, and the markup waits in the page either way.
    let pictureWindow = null;
    const me = window.START_DATA?.user?.id ?? null;

    /** A find's picture records, the way the map's gallery gets them. The
     *  cloud listing only where the row names a recording — it is the
     *  slower of the two, and a find of photos alone has nothing there. */
    async function fetchPictures(row) {
        const hasCloud = picturesOf(row).some((key) => pictureOf(key).cloud);
        const [photos, files] = await Promise.all([
            api.get('photos', { point: row.id }).then((a) => a.photos ?? []),
            hasCloud
                ? request(`cloud.php?id=${row.id}&action=files`, { method: 'POST' }).then((a) => a.files ?? [])
                : [],
        ]);
        return { photos, files };
    }

    /** The row's keys out of fresh records, in the server's order
     *  (organize_pictures): the place, then the photo before the
     *  recording, then the id. */
    function keysOf(photos, files) {
        return [
            ...photos.map((f) => [f.sort_order ?? 0, 0, f.id]),
            ...files.map((f) => [f.sort_order ?? 65536, 1, f.id]),
        ].sort((a, b) => a[0] - b[0] || a[1] - b[1] || a[2] - b[2])
            .map(([, kind, id]) => (kind ? 'cloud:' : 'photo:') + id);
    }

    /* AFTER A CHANGE IN THE WINDOW — a name, a deletion — the viewer asks
       its host to draw the find's gallery again. Here that is the row's
       thumbnails: the records are fetched anew and the table redrawn. The
       objects the window already holds are kept, so the picture in it and
       its mark in the strip stay the same objects. */
    async function refreshPictures(point) {
        const row = inWindow.row;
        if (!row || !point || point.id !== row.id) return;
        let fresh;
        try {
            fresh = await fetchPictures(row);
        } catch {
            return;   // the table keeps what it showed
        }
        const keep = (list, next) => {
            const known = new Map(list.map((f) => [f.id, f]));
            return next.map((f) => known.get(f.id) ?? f);
        };
        inWindow.point.photos = keep(inWindow.point.photos, fresh.photos);
        if (pictureWindow) {
            Object.assign(pictureWindow.cloudFilesShown,
                { pointId: row.id, files: keep(pictureWindow.cloudFilesShown.files, fresh.files) });
        }
        row.pictures = keysOf(fresh.photos, fresh.files);
        row.photos = fresh.photos.map((f) => f.id);
        drawTable();
    }

    const pictureHost = () => ({
        start: window.START_DATA,
        guest: false,
        pointId: () => inWindow.point?.id ?? null,
        point: (id) => (inWindow.point && inWindow.point.id === id ? inWindow.point : null),
        drawGallery: (point) => refreshPictures(point),
        mayEditPhotos: () => !inWindow.row?.foreign || !!inWindow.row?.editable,
        foreign: () => (inWindow.row?.foreign ? inWindow.row : null),
        sharedRights: (f) => new Set(f?.editable ? ['delete_photos'] : []),
        mayDelete: (f) => (inWindow.row?.foreign
            ? !!inWindow.row.editable
            : !f.photographer_id || f.photographer_id === me),
        status: (text, isError = false) => toast(text, isError ? 'error' : 'success'),
    });

    async function openPhotos(p, nr) {
        const key = picturesOf(p)[nr];
        if (!key) return;
        let records;
        try {
            records = await fetchPictures(p);
        } catch (error) {
            toast(t('Die Bilder ließen sich nicht laden: %s', error.message), 'error');
            return;
        }
        inWindow.row = p;
        inWindow.point = { id: p.id, photos: records.photos };
        pictureWindow ??= mountPhotoWindow(pictureHost());
        Object.assign(pictureWindow.cloudFilesShown, { pointId: p.id, files: records.files });
        const { cloud, id } = pictureOf(key);
        const picture = (cloud ? records.files : records.photos).find((f) => f.id === id);
        if (!picture) {
            toast(t('Dieses Bild gibt es nicht mehr.'), 'error');
            refreshPictures(inWindow.point);
            return;
        }
        if (cloud) pictureWindow.showCloudPhoto(picture);
        else pictureWindow.showPhoto(picture);
    }

    // -----------------------------------------------------------------
    // The table on the right
    // -----------------------------------------------------------------

    // Rows per helping — see appendRows(): the table feeds itself while
    // scrolling, this is only how much lands in the DOM at once.
    const CHUNK = 200;

    function timeText(value) {
        if (value === null) return '—';
        const [date, clock] = value.split(' ');
        const [y, m, d] = date.split('-');
        return `${d}.${m}.${y}` + (clock && clock !== '00:00:00' ? ' ' + clock.slice(0, 5) : '');
    }

    // -----------------------------------------------------------------
    // The columns of the table
    // -----------------------------------------------------------------

    /* WHICH COLUMNS, AND IN WHICH ORDER, IS THE USER'S CHOICE
       (22.08.2026) — the „Spalten" chip beside the filter opens the window.
       The catalogue is everything a point can show here: the fixed point
       fields, then every find field and custom root field of the account.
       „Titel" stands outside the choice — a row without its title would
       be unreadable — and is always the first column. The chosen set and
       its order go into the settings bundle; sort and the per-column
       search do NOT: they are working state, not a preference, and a
       stored sort would greet the next visit with an inexplicable order.

       Hand-built instead of a table library (22.08.2026):
       sorting is one comparator, the column search one includes() per
       cell — a library would bring paging, Ajax and export along and
       still have to be bent around the selection logic below. */

    const cellText = (v) => v === null || v === undefined ? ''
        : Array.isArray(v) ? v.join(', ') : String(v);

    const TITLE_COLUMN = { key: 'title', label: t('Titel'), value: (p) => p.title };

    const catalogue = [
        { key: 'lists', label: t('Listen'), value: (p) => p.lists
            .map((id) => listById.get(id)?.name ?? foreignListById.get(id)?.name)
            .filter(Boolean).join(', ') },
        /* Sorted on the raw value: the display turns 2026-08-22 into
           22.08.2026, and THAT text would order by day-of-month. */
        { key: 'found_at', label: t('Fundzeit'), value: (p) => timeText(p.found_at),
          raw: (p) => p.found_at },
        /* The same rule as the window's fact row and the sidebar
           (pointFacts in map.js): finds only — a waypoint has no
           collection number —, the minted number or, without a pattern,
           the database id. FOREIGN FINDS SHOW THEIRS TOO
           (23.08.2026: the number is the shorthand friends cite to each
           other); their leaves carry the branch-rooted is_species
           ready-made from the server, because the root can lie outside
           the mounted branch and no device here could walk to it. */
        { key: 'find_number', label: t('Fund-Nr.'), value: (p) =>
            // An Aufsammlung shows its box number, not a „Y-" mark.
            p.is_collection ? (p.collection_number ?? '') :
            p.lists.some((id) => rootOf(id)?.is_species || foreignListById.get(id)?.is_species)
                ? (p.find_number ?? (p.id > 0 ? findNumberFallback(p.id) : ''))
                : '' },
        /* THE PICTURE COLUMN (24.08.2026): thumbnails in the
           cell, a click opens the viewer — the sorting-out place for ALL
           points with photos, guests' excursion finds included. Sorted
           and searched by the count; the drawing has its own renderer. */
        { key: 'photos', label: t('Bilder'),
          value: (p) => picturesOf(p).length > 0 ? String(picturesOf(p).length) : '',
          raw: (p) => picturesOf(p).length > 0 ? picturesOf(p).length : '',
          render: (p, td) => {
              td.className = 'organize-thumbs';
              const ids = picturesOf(p);
              ids.slice(0, 3).forEach((key, nr) => {
                  const img = document.createElement('img');
                  img.src = pictureUrl(key, true);
                  /* A RECORDING WITHOUT A PREVIEW is common — a raw file,
                     or a provider that has not drawn it yet — and a broken
                     picture in the cell reads like a lost file. The same
                     sentence the map's gallery gives it, in a quiet tile
                     that still opens the viewer. */
                  if (pictureOf(key).cloud) {
                      img.addEventListener('error', () => {
                          const gap = document.createElement('span');
                          gap.className = 'organize-thumb is-missing';
                          gap.title = t('Für diese Aufnahme gibt es keine Vorschau — die Datei selbst liegt in der Cloud.');
                          gap.append(icon('cloud'));
                          gap.addEventListener('click', () => openPhotos(p, nr));
                          img.replaceWith(gap);
                      }, { once: true });
                  }
                  img.alt = t('Foto %s von %s', nr + 1, p.title);
                  img.loading = 'lazy';
                  img.className = 'organize-thumb';
                  img.addEventListener('click', () => openPhotos(p, nr));
                  td.append(img);
              });
              if (ids.length > 3) {
                  const more = document.createElement('button');
                  more.type = 'button';
                  more.className = 'text-button organize-thumb-more';
                  more.textContent = '+' + (ids.length - 3);
                  more.addEventListener('click', () => openPhotos(p, 3));
                  td.append(more);
              }
          } },
        { key: 'note', label: t('Notiz'), value: (p) => cellText(p.note) },
        { key: 'municipality', label: t('Gemeinde'), value: (p) => cellText(p.municipality) },
        { key: 'region', label: t('Region'), value: (p) => cellText(p.region) },
        { key: 'country', label: t('Land'), value: (p) => cellText(p.country) },
        { key: 'mtb', label: 'MTB', value: (p) => p.mtb
            ? p.mtb + (p.mtb_quadrant ? '/' + p.mtb_quadrant : '') : '' },
        { key: 'protected_area', label: t('Schutzgebiet'), value: (p) => cellText(p.protected_area) },
        { key: 'elevation', label: t('Höhe'), value: (p) => p.elevation_m == null ? '' : p.elevation_m + ' m',
          raw: (p) => p.elevation_m },
        { key: 'rl', label: t('Rote Liste'), value: (p) => cellText(p.rl) },
        { key: 'dgfm', label: t('DGfM-Listen'), value: (p) => (p.dgfm ?? []).map(filter.dgfmWord).join(', ') },
        { key: 'tour', label: t('Tour'), value: (p) => cellText(p.tour) },
        /* 'Koordinaten' like the field in the point window — and here as
           well as there with the comma as the decimal mark: this is a
           value to READ, not one to feed a machine (the export writes
           them with a point, where a machine reads them). The report
           offered this column while the dialog could not, because the
           dialog is built from this catalogue. */
        { key: 'coordinates', label: t('Koordinaten'),
          value: (p) => `${p.lat.toFixed(5).replace('.', ',')} / ${p.lon.toFixed(5).replace('.', ',')}` },
        /* WHOSE FIND — empty on one's own, the owner's name on a shared
           one. In a mixed table that is the column that says why a row
           cannot be edited, and the find report needs it as its author
           line (23.08.2026).

           'Geteilt von' AND NOT 'Von' OR 'Finder' (the maintainer asked
           exactly that, same day): this is the ACCOUNT the find comes
           from, not the Finder field — that one is an ordinary find field
           next to Bestimmer, it can name somebody who was not the account
           holder, and it stands in the list under its own name. Two
           different things must not share a word, and „Von" was vague
           enough to be mistaken for either. */
        { key: 'owner', label: t('Geteilt von'), value: (p) => cellText(p.owner) },
    ];
    /* The field columns: the fixed find set plus every custom root field,
       deduplicated by name — a custom field bearing a find field's name is
       suppressed everywhere else too (find_fields.php). The values sit in
       the point's extra block under the field name, on foreign points the
       same as on one's own. */
    {
        const seen = new Set();
        const fieldColumn = (name) => {
            const flat = name.toLowerCase();
            if (seen.has(flat)) return;
            seen.add(flat);
            catalogue.push({ key: 'field:' + name, label: name,
                value: (p) => cellText(p.extra?.[name]) });
        };
        for (const f of [...(findFields ?? []), ...lists.flatMap((l) => l.fields ?? [])]) {
            fieldColumn(f.name);
        }
        /* Foreign containers bring the fields of their lists along only
           as VALUES — the definitions stay with their owners. So the
           names standing on the foreign points themselves join the
           catalogue too (22.08.2026): whoever shares a find
           mapping shares its columns with it. Data-driven, which means a
           foreign field appears once a point carries a value in it. */
        for (const p of foreignPoints) {
            for (const name of Object.keys(p.extra ?? {})) fieldColumn(name);
        }
    }
    const columnByKey = new Map([TITLE_COLUMN, ...catalogue].map((c) => [c.key, c]));

    /* The stored choice — unknown keys (a custom field deleted since) fall
       out silently. Without a stored choice the table shows what it has
       always shown: lists and find time. */
    let shownKeys = (settings.get('organizeColumns', null) ?? ['lists', 'found_at'])
        .filter((k) => columnByKey.has(k) && k !== 'title');
    const columns = () => [TITLE_COLUMN, ...shownKeys.map((k) => columnByKey.get(k))];

    /* One column, ascending or descending; the third click returns to the
       server order (newest change first). Empty cells sort last in BOTH
       directions — whoever sorts by elevation is looking for the measured
       points, not for a block of blanks at the top. */
    let sortBy = null; // { key, dir: 1 | -1 }

    const columnSearch = new Map(); // key -> the field's raw text

    function compareBy(c) {
        const raw = c.raw ?? c.value;
        return (a, b) => {
            const x = raw(a), y = raw(b);
            const xEmpty = x === null || x === undefined || x === '';
            const yEmpty = y === null || y === undefined || y === '';
            if (xEmpty || yEmpty) return xEmpty === yEmpty ? 0 : (xEmpty ? 1 : -1);
            if (typeof x === 'number' && typeof y === 'number') return sortBy.dir * (x - y);
            /* numeric: true keeps „MTB 4948" before „MTB 5049" even when a
               quadrant hangs behind one of them. */
            return sortBy.dir * String(x).localeCompare(String(y), 'de', { numeric: true });
        };
    }

    /* THE VIEW OF THE TABLE: the filtered set, sifted through the column
       search fields and sorted. drawTable AND the head checkbox both go
       through here — the checkbox promises "all visible", and visible is
       only what survives these fields too. */
    function visiblePoints() {
        let set = matches();
        for (const [key, text] of columnSearch) {
            const needle = text.trim().toLowerCase();
            if (needle === '') continue;
            const c = columnByKey.get(key);
            if (c) set = set.filter((p) => c.value(p).toLowerCase().includes(needle));
        }
        if (sortBy) set = [...set].sort(compareBy(columnByKey.get(sortBy.key)));
        return set;
    }

    const headRow = el('organize-head-row');

    function drawHead() {
        /* Everything but the first cell — that one holds the head checkbox
           and stands in the markup. */
        while (headRow.children.length > 1) headRow.lastChild.remove();
        for (const c of columns()) {
            const th = document.createElement('th');
            /* THE COLUMN NAME LIVES IN THE PLACEHOLDER
               (22.08.2026: „als Platzhaltertexte in die Inputfelder") — no
               label line of its own. A hidden span still says the name:
               tables.js reads th.textContent for the stacked phone
               labels, and screen readers need a heading too. Only the
               span may carry text — an arrow as button TEXT would ride
               along into the phone labels (the ::after rules in app.css
               exist for exactly that reason). */
            const name = document.createElement('span');
            name.className = 'organize-column-name';
            name.textContent = c.label;
            const cell = document.createElement('span');
            cell.className = 'organize-head-cell';
            /* The search field per column — desktop only: app.css hides it
               below 860px, where the table stacks anyway
               (22.08.2026). */
            const search = document.createElement('input');
            search.type = 'search';
            search.className = 'organize-column-search';
            search.value = columnSearch.get(c.key) ?? '';
            search.placeholder = c.label;
            search.setAttribute('aria-label', t('Spalte „%s" durchsuchen', c.label));
            search.addEventListener('input', () => {
                columnSearch.set(c.key, search.value);
                drawTable();
            });
            const sort = document.createElement('button');
            sort.type = 'button';
            sort.className = 'organize-sort';
            if (sortBy?.key === c.key) {
                sort.classList.add(sortBy.dir === 1 ? 'is-asc' : 'is-desc');
            }
            sort.title = sortBy?.key === c.key && sortBy.dir === -1
                ? t('Sortierung aufheben') : t('Nach %s sortieren', c.label);
            sort.setAttribute('aria-label', sort.title);
            sort.addEventListener('click', () => {
                sortBy = sortBy?.key !== c.key ? { key: c.key, dir: 1 }
                    : sortBy.dir === 1 ? { key: c.key, dir: -1 } : null;
                drawHead();
                drawTable();
            });
            cell.append(search, sort);
            th.append(name, cell);
            headRow.append(th);
        }
        /* The pencil column's head — sticky like its cells, so the strip
           at the right edge is one piece from top to bottom. */
        const edit = document.createElement('th');
        edit.className = 'organize-row-edit';
        const editName = document.createElement('span');
        editName.className = 'organize-column-name';
        editName.textContent = t('Bearbeiten');
        edit.append(editName);
        headRow.append(edit);
    }

    const columnsDialog = el('organize-columns-dialog');

    function fillColumnsDialog() {
        const box = el('organize-columns-rows');
        box.textContent = '';
        /* The chosen ones first, in their order, then the rest as the
           catalogue lists them — so the window mirrors the table. */
        const keys = [...shownKeys,
            ...catalogue.map((c) => c.key).filter((k) => !shownKeys.includes(k))];
        for (const key of keys) {
            const c = columnByKey.get(key);
            box.append(columnChoiceRow(key, c.label, { checked: shownKeys.includes(key) }));
        }
    }

    el('organize-columns-open').addEventListener('click', () => {
        fillColumnsDialog();
        columnsDialog.showModal();
    });
    columnsDialog.querySelector('form').addEventListener('submit', (e) => {
        e.preventDefault();
        shownKeys = [...el('organize-columns-rows').children]
            .filter((r) => r.querySelector('input').checked)
            .map((r) => r.dataset.key);
        settings.set('organizeColumns', shownKeys);
        /* A dropped column takes its search text with it — an invisible
           field must not go on sifting the table. */
        for (const key of [...columnSearch.keys()]) {
            if (key !== 'title' && !shownKeys.includes(key)) columnSearch.delete(key);
        }
        columnsDialog.close();
        drawHead();
        drawTable();
    });
    columnsDialog.querySelector('.dialog-footer .subtle').addEventListener('click', () => columnsDialog.close());

    function pointRow(p) {
        const tr = document.createElement('tr');

        // A foreign point gets no checkbox: the actions write into the
        // owner's ordering, and that belongs to him. It still stands in
        // the table — seeing what hangs in the container is half the
        // purpose. THE EXCEPTION IS THE EXCURSION FIND (editable, stage
        // 5): foreign in ownership, the leader's in right — field and
        // trash work, only the lists stay barred (it lives in none).
        const tdCheck = document.createElement('td');
        if (!p.foreign || p.editable) {
            const check = document.createElement('input');
            check.type = 'checkbox';
            check.checked = selection.has(p.id);
            check.setAttribute('aria-label', t('%s wählen', p.title));
            check.addEventListener('change', () => {
                check.checked ? selection.add(p.id) : selection.delete(p.id);
                drawState(currentSet);
            });
            tdCheck.append(check);
        }

        tr.append(tdCheck);
        /* Every cell from its column — the title keeps its bold face
           and the owner's name on foreign points, everything else is
           plain text from the column's getter. */
        for (const c of columns()) {
            const td = document.createElement('td');
            if (c.key === 'title') {
                const strong = document.createElement('strong');
                strong.textContent = p.title;
                td.append(strong);
                if (p.foreign) {
                    const byWhom = document.createElement('span');
                    byWhom.className = 'item-data';
                    byWhom.textContent = t(' · von ') + p.owner;
                    td.append(byWhom);
                }
            } else if (c.render) {
                c.render(p, td);
            } else {
                td.className = 'organize-list-cell';
                td.textContent = c.value(p);
            }
            tr.append(td);
        }

        /* THE ROW PENCIL, STICKY AT THE RIGHT EDGE (the sketch,
           24.08.2026): the table scrolls sideways, the way to editing
           must not. Everything at once — title, note, time, fields —
           instead of one Feld-setzen round per value. Only where a right
           exists; a viewer's row keeps the empty cell so the columns
           stay flush. */
        const tdEdit = document.createElement('td');
        tdEdit.className = 'organize-row-edit';
        if (!p.foreign || p.editable) {
            const pencil = document.createElement('button');
            pencil.type = 'button';
            pencil.className = 'symbol-button';
            pencil.title = t('Punkt bearbeiten');
            pencil.setAttribute('aria-label', t('%s bearbeiten', p.title));
            pencil.append(icon('pencil'));
            pencil.addEventListener('click', () => openPointEditor(p));
            tdEdit.append(pencil);
        }
        tr.append(tdEdit);
        return tr;
    }

    /* Which fields the editor offers for THIS point: on an excursion
       find the fixed find set (the whitelist the server enforces too);
       on one's own the roots' custom fields, plus the find set when a
       root is a species list — the same reading as the map's point
       window. */
    function editorFields(p) {
        if (p.foreign) return findFields ?? [];
        const out = [];
        const seen = new Set();
        const push = (f) => {
            const flat = f.name.toLowerCase();
            if (!seen.has(flat)) { seen.add(flat); out.push(f); }
        };
        const roots = [...new Set(p.lists.map((id) => rootOf(id)).filter(Boolean))];
        if (roots.some((r) => r.is_species)) (findFields ?? []).forEach(push);
        for (const r of roots) (r.fields ?? []).forEach(push);
        return out;
    }

    function openPointEditor(p) {
        const box = el('organize-point-dialog');
        /* „Art" instead of „Titel" in a species list
           (24.08.2026) — the same wording as in the map's point window;
           only the label changes, the field stays the title. Foreign finds
           come out of excursions and are always species finds. */
        const species = p.foreign
            || p.lists.some((id) => rootOf(id)?.is_species);
        document.querySelector('label[for="organize-point-title"]').textContent =
            species ? t('Art') : t('Titel');
        el('organize-point-title').placeholder = species ? t('Gefundene Art') : '';
        el('organize-point-title').value = p.title;
        el('organize-point-note').value = p.note ?? '';
        // '2026-08-24 19:30:00' -> what datetime-local speaks.
        el('organize-point-time').value = p.found_at
            ? p.found_at.slice(0, 16).replace(' ', 'T') : '';

        const host = el('organize-point-fields');
        host.textContent = '';
        for (const f of editorFields(p)) {
            const wrap = document.createElement('div');
            wrap.className = 'field';
            const label = document.createElement('label');
            label.textContent = f.name;
            let input;
            /* A choice with a single answer is a tick, as in the point
               window („Beleg aufbewahrt") — its answer or nothing. */
            if (f.type === 'choice' && (f.options ?? []).length === 1) {
                input = document.createElement('input');
                input.type = 'checkbox';
                input.dataset.tick = f.options[0];
                if (f.reveals) input.dataset.reveals = f.reveals;
                input.checked = cellText(p.extra?.[f.name]).trim() !== '';
                input.dataset.fieldName = f.name;
                const words = document.createElement('span');
                words.textContent = t(f.name);
                const row = document.createElement('label');
                row.className = 'checkbox';
                row.append(input, words);
                wrap.append(row);
                host.append(wrap);
                continue;
            }
            if (f.type === 'choice') {
                input = document.createElement('select');
                const none = document.createElement('option');
                none.value = '';
                none.textContent = '—';
                input.append(none);
                for (const o of f.options ?? []) {
                    const option = document.createElement('option');
                    option.value = o;
                    option.textContent = o;
                    input.append(option);
                }
            } else if (f.type === 'multiline') {
                input = document.createElement('textarea');
                input.rows = 2;
            } else {
                input = document.createElement('input');
                input.type = 'text';
            }
            input.value = cellText(p.extra?.[f.name]);
            input.dataset.fieldName = f.name;
            wrap.append(label, input);
            host.append(wrap);
        }
        /* The tick that reveals a field, as in the point window: the field
           only while it is set, and a number already there sets it. Set,
           it fills an empty „Beleg-Nr." with the find's own number, never
           the „Y-" mark (map.js says why, 29.09.2026). */
        for (const tick of host.querySelectorAll('input[type="checkbox"][data-reveals]')) {
            const target = [...host.querySelectorAll('[data-field-name]')]
                .find((e) => e.dataset.fieldName === tick.dataset.reveals);
            const wrapOf = target?.closest('.field');
            if (!wrapOf) continue;
            if (String(target.value ?? '').trim() !== '') tick.checked = true;
            const follow = () => { wrapOf.hidden = !tick.checked; };
            follow();
            tick.addEventListener('change', () => {
                follow();
                const number = String(p.find_number ?? '').trim();
                if (tick.checked && number !== '' && String(target.value ?? '').trim() === '') {
                    target.value = number;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        }

        box.dataset.point = String(p.id);
        box.returnValue = '';
        box.showModal();
    }

    el('organize-point-dialog').querySelector('.dialog-footer .subtle')
        .addEventListener('click', () => el('organize-point-dialog').close());
    el('organize-point-dialog').addEventListener('close', () => {
        const box = el('organize-point-dialog');
        if (box.returnValue !== 'ja') return;
        const form = el('organize-save-form');
        // The previous save's dynamic inputs go first — which fields
        // exist depends on the point.
        for (const stale of form.querySelectorAll('[data-dynamic]')) stale.remove();
        // Unticked, the field a tick reveals goes with it (no voucher, no number).
        const emptied = new Set([...box.querySelectorAll('#organize-point-fields input[type="checkbox"][data-reveals]')]
            .filter((tick) => !tick.checked).map((tick) => tick.dataset.reveals));
        for (const input of box.querySelectorAll('#organize-point-fields [data-field-name]')) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `fields[${input.dataset.fieldName}]`;
            hidden.value = input.type === 'checkbox' ? (input.checked ? input.dataset.tick : '')
                : emptied.has(input.dataset.fieldName) ? '' : input.value;
            hidden.dataset.dynamic = '1';
            form.append(hidden);
        }
        submitForm(form, {
            point:    box.dataset.point,
            title:    el('organize-point-title').value,
            note:     el('organize-point-note').value,
            found_at: el('organize-point-time').value,
        });
    });

    /* THE TABLE FEEDS ITSELF while you scroll (22.08.2026 —
       the row „… und N weitere: enger filtern" made the reader do what
       the machine can). The data is all here anyway; only the DOM is fed
       in helpings, because 2000 rows at once are felt at every redraw.
       The quiet remainder row at the end is the watchman: as soon as it
       comes near the viewport, it makes way for the next helping.

       A CAPTURE LISTENER ON THE DOCUMENT, not an IntersectionObserver:
       scroll events do not bubble but they do capture, so ONE listener
       hears both scrollers — the table box on the desktop, the page on
       the phone. The observer would be the textbook tool, but its
       callbacks hang on rendering frames, and a document that is not
       being drawn (background tab, the panel of a test run) never fires
       them — the position check below works everywhere and is one rect
       per scroll event. */
    let currentSet = [];
    let renderedCount = 0;
    let sentinel = null;

    function feedCheck() {
        if (!sentinel || !sentinel.isConnected) return;
        /* 600px of lead: the next helping stands ready before the reader
           reaches the edge — nobody sees the seam. */
        if (sentinel.getBoundingClientRect().top > window.innerHeight + 600) return;
        sentinel.remove();
        sentinel = null;
        appendRows();
    }
    document.addEventListener('scroll', feedCheck, true);
    window.addEventListener('resize', feedCheck);

    function appendRows() {
        const upTo = Math.min(renderedCount + CHUNK, currentSet.length);
        for (const p of currentSet.slice(renderedCount, upTo)) {
            rowsEl.append(pointRow(p));
        }
        renderedCount = upTo;
        if (currentSet.length > renderedCount) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = columns().length + 2;
            td.className = 'organize-list-cell';
            td.textContent = t('… %s weitere', currentSet.length - renderedCount);
            tr.append(td);
            rowsEl.append(tr);
            sentinel = tr;
            /* Straight away once more: on a screen tall enough to show
               the whole helping the watchman is already in sight, and
               the next helping must follow without a scroll happening.
               Runs until the sentinel stands beyond the lead or the set
               is exhausted. */
            feedCheck();
        }
    }

    function drawTable() {
        currentSet = visiblePoints();
        rowsEl.textContent = '';
        renderedCount = 0;
        sentinel = null;
        appendRows();
        /* SAY WHY THE TABLE IS EMPTY (22.08.2026: Hessen plus
           ein Bereich in Frankreich — kein Fehler, aber eine Erklärung
           muss her). A STATE line in the flow, not a toast: it has to
           stand as long as the emptiness lasts. Only when conditions
           actually apply — an empty selection without filters explains
           itself through the numbers in the tree. */
        if (currentSet.length === 0 && points.length + foreignPoints.length > 0) {
            const conditions = Object.values(groupCount).reduce((n, f) => n + f(), 0);
            if (conditions > 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = columns().length + 2;
                td.className = 'organize-list-cell filter-empty-hint';
                td.textContent = conditions === 1
                    ? t('Der Filter lässt keinen Punkt durch.')
                    : t('Die Filter schließen sich gegenseitig aus — zusammen bleibt kein Punkt übrig.');
                tr.append(td);
                rowsEl.append(tr);
            }
        }
        drawState(currentSet);
    }

    function drawState(set) {
        /* Get rid of orphaned checkmarks: what the filter no longer shows
           should no longer be chosen either — invisible selection would be
           the sort of surprise that throws points into the wrong basket. */
        const visible = new Set(set.map((p) => p.id));
        for (const id of selection) {
            if (!visible.has(id)) selection.delete(id);
        }

        /* Selectable is what actions can reach: one's own, and since
           stage 5 the excursion finds (editable — the leader's right at
           the reference). Plain foreign container points stay view-only. */
        const own = set.filter((p) => !p.foreign || p.editable);

        /* CHOSEN OF SHOWN, at the column head. The dedicated row above the
           table used to carry this as "N gewählt" next to two buttons
           ("alle wählen", "Auswahl leeren") — the buttons the head
           checkbox on the left can do itself (on for all, again for
           empty), the number needed a place. The slash says it without a
           word: 0/5 is a figure, not a sentence, and stays narrow enough
           for the pill. */
        pointCountEl.textContent = '';
        pointCountEl.append(`${selection.size}/${set.length}`);
        if (own.length < set.length) {
            pointCountEl.append(t(' (%s von anderen)', set.length - own.length));
        }

        /* The WHOLE filtered set, not the rendered slice: since the table
           feeds itself, „alle sichtbaren" means everything the filters
           let through — the old cap text promised that already, while the
           code quietly stopped at the first 200. */
        headerCheck.disabled = own.length === 0;
        headerCheck.checked = own.length > 0 && own.every((p) => selection.has(p.id));
        headerCheck.indeterminate = !headerCheck.checked && own.some((p) => selection.has(p.id));

        /* Lists and tour stay barred while an EXCURSION FIND is chosen:
           both would sort into one's own ordering, and a guest's find
           lives in the guest account — it has no list and no tour of
           yours to join. Field and trash are exactly the leader's right. */
        const excursionChosen = [...selection].some((id) => foreignById.get(id)?.editable);
        /* HIDDEN, NOT GREYED (12.09.2026: „ich will bei
           Organisieren auch nur Buttons sehen, die wirklich Funktion
           haben — die ausgegrauten bitte komplett ausblenden"). A button
           that cannot act is furniture, and greyed furniture still asks
           to be read. What a selection wakes appears with it; without a
           selection the row holds only what needs none — the new list,
           the auto list, export and report. `disabled` stays set beside
           `hidden`, so a stale focus or a script cannot press what is
           not there. */
        const away = (id, condition, why = '') => {
            const b = el(id);
            b.hidden = condition;
            b.disabled = condition;
            b.title = why;
        };
        /* THE AUTO LIST ONLY WITH A FILTER (12.09.2026:
           „Autoliste soll auch nur aktiv sein, wenn wirklich Filter
           gesetzt sind"). It follows the filter; without a condition it
           would follow nothing and mirror the whole stock — or one list,
           which the list already is. A chosen list alone is not a
           condition here, the same reading autoFilterText() gives. */
        away('organize-auto-button', Object.values(groupCount).reduce((n, f) => n + f(), 0) === 0);
        away('organize-lists-button', selection.size === 0 || excursionChosen);
        away('organize-tour-button',  selection.size === 0 || excursionChosen);
        away('organize-field-button', selection.size === 0);
        /* „Fundnummern …" wakes only for an OWN FIND in the selection — a
           find number belongs to a find in a species list, and a guest's
           excursion find carries its finder's. */
        away('organize-number-button', ![...selection].some((id) => isOwnFind(ownById.get(id))));
        away('organize-trash-button', selection.size === 0);
        selectionEl.textContent = selection.size > 0
            ? (selection.size === 1
                ? t('Aktionen wirken auf den gewählten Punkt.')
                : t('Aktionen wirken auf alle %s gewählten.', selection.size))
            : '';
    }

    headerCheck.addEventListener('change', () => {
        const shown = visiblePoints().filter((p) => !p.foreign || p.editable);
        shown.forEach((p) => headerCheck.checked ? selection.add(p.id) : selection.delete(p.id));
        drawTable();
    });

    function drawAll() {
        drawTree();
        drawTable();
    }

    // -----------------------------------------------------------------
    // Filter fields
    // -----------------------------------------------------------------

    // Filter rows and time filter report on their own (wired up above) —
    // what remains here is only the checkbox for the missing find time.
    el('organize-no-time').addEventListener('change', (e) => {
        state.noTime = e.target.checked;
        filterFlap.refresh();
        drawTable();
    });

    // -----------------------------------------------------------------
    // Submitting
    // -----------------------------------------------------------------

    /** The view as form fields — for our own POSTs and for the mount
     *  window that is opened from within the dialog. */
    function stateFields() {
        return {
            // Empty means "Alle Punkte" — on reading back, the empty falls
            // through the validity check anyway.
            // 'r:' goes out as its number: for everything beyond this page
            // the reference IS its list, and the server knows only lists.
            list:     [...new Set([...state.lists].map((k) =>
                          typeof k === 'string' && k.startsWith('r:') ? listIdOf(k) : k))].join(','),
            // The rows as one string ("a~Buche|n~Fomes") — it travels
            // through the same field as the single search text used to, so
            // the view stands there again after every action.
            search:   filter.writeTerms(state.terms),
            from:     state.from,
            to:       state.to,
            no_time:  state.noTime ? '1' : '',
            unidentified: state.identification.open ? '1' : '',
            // The families as "sp,cf", the steps '|'-separated like the
            // municipalities — a step name carries spaces and dots, a bar
            // it never does.
            marks:     state.identification.marks.join(','),
            certainty: state.identification.certainty.join('|'),
            filter_open:  state.filterOpen ? '1' : '',
            rl:       filter.writeRl(state.rl),
            // The DGfM marks the same way, and the hook as '1'.
            edibility:      filter.writeEdibility(state.edibility),
            responsibility: state.responsibility ? '1' : '',
            // The drawn areas as JSON — the view itself reads them from
            // the settings again anyway; this field exists for the
            // AUTOLIST, whose stored filter has to keep meaning what the
            // table showed (organize_autofilter → autolist_filter_read).
            areas:    areas.length > 0 ? JSON.stringify(areas) : '',
            regions:  state.regions.join(','),
            // '|' like the term rows — a municipality name can carry a
            // comma sooner than a bar.
            municipalities: state.municipalities.join('|'),
            mtb:      state.mtbText,
            // The height band, as two numbers — the auto list has to keep
            // meaning what the table showed (autolist_filter_read reads
            // them back under the same names).
            elevation_from: String(state.elevation.from),
            elevation_to:   String(state.elevation.to),
            // The systematic ranks, one field per rank — autolist_filter_read
            // reads them back under exactly these names.
            ...Object.fromEntries(filter.TAXONOMY_RANKS.map(([key]) =>
                ['taxonomy_' + key, String(state.taxonomy[key] ?? '')])),
            // '|' like the municipalities — a tour name is free text and
            // can carry a comma sooner than a bar.
            tours:    [...state.tours].join('|'),
            pane:     state.pane === 'tours' ? 'tours' : '',
        };
    }

    /**
     * Fill a hidden form and send it off.
     *
     * IT SAYS SO WHEN A FIELD IS NOT THERE, and that is the whole point of
     * the detour. Until 21.08.2026 this wrote straight into
     * querySelector(...).value — and where the field was missing, that was
     * a TypeError on null: the loop broke off BEFORE form.submit(), the
     * dialog had already closed, and the page stood there as if nothing
     * had been asked of it. Two of the four mass actions had been dead
     * that way for who knows how long, because the field names in the
     * markup were translated and the ones here were not (the maintainer's
     * report of 21.08.2026: "und weiter wurde mir auch nichts
     * gespeichert").
     *
     * A silent failure in a mass action is the worst kind: you do not
     * repeat what you believe you have already done. So it is better to
     * stand in the way loudly and wrongly than to nod quietly.
     */
    function submitForm(form, fields = {}) {
        const values = { points: [...selection].join(','), ...fields };
        for (const [field, value] of Object.entries(stateFields())) {
            values[`state[${field}]`] = value;
        }

        const missing = [];
        for (const [name, value] of Object.entries(values)) {
            const input = form.querySelector(`[name="${name}"]`);
            if (input === null) { missing.push(name); continue; }
            input.value = value;
        }
        if (missing.length > 0) {
            report({
                title: t('Nicht abgeschickt'),
                text: t('Dem Formular „%s" fehlen die Felder %s. Es wurde nichts geändert — deine Auswahl steht noch.',
                    form.id, missing.map((n) => `„${n}"`).join(', ')),
            });
            return;
        }
        form.submit();
    }

    el('organize-trash-button').addEventListener('click', async () => {
        const n = selection.size;
        if (!n) return;
        const ok = await confirm({
            title: t('In den Papierkorb?'),
            text: n === 1
                ? t('Der gewählte Punkt wandert in den Papierkorb — samt Listenzuordnung und Fotos. Endgültig gelöscht wird dort erst auf deinen Befehl.')
                : t('%s Punkte wandern in den Papierkorb — samt Listenzuordnung und Fotos. Endgültig gelöscht wird dort erst auf deinen Befehl.', n),
            button: t('In den Papierkorb'),
            danger: true,
        });
        if (ok) submitForm(el('organize-trash-form'));
    });

    // -----------------------------------------------------------------
    // The list dialog
    // -----------------------------------------------------------------

    el('organize-lists-button').addEventListener('click', () => {
        if (selection.size === 0) return;
        fillDialog();
        dialog.showModal();
        // Take the focus off the first checkbox — its ring looked like a
        // mark (see tabindex on the window in the markup).
        dialog.focus();
    });

    /**
     * Keep the built-in checkbox state in step with our three — for
     * keyboard and screen reader that is what counts, not the colour.
     *
     * IN A MICROTASK AND NOT RIGHT AWAY, and that is not a nicety.
     * e.preventDefault() in the click handler undoes the native toggle
     * AFTER the event has been dispatched, so anything written to
     * .checked inside paint() is thrown away again a moment later.
     * Measured: inside the handler checked reads false, right after
     * dispatch it is true once more. The microtask runs after that and
     * therefore has the last word.
     *
     * The picture no longer depends on this — app.css draws the box
     * solely from is-full / is-partial (see the note there). Which is
     * deliberate: whoever repairs one of the two halves should not
     * silently break the other.
     */
    function nativeState(check, z) {
        queueMicrotask(() => {
            check.checked = z === 'full';
            check.indeterminate = z === 'partial';
        });
    }

    function fillDialog() {
        const container = el('organize-dialog-lists');
        container.textContent = '';

        /* Per leaf: how many of the chosen points hang in it? */
        const chosen = points.filter((p) => selection.has(p.id));
        const countIn = (id) => chosen.reduce((n, p) => n + (p.lists.includes(id) ? 1 : 0), 0);

        /* THE SAME LIST CAN STAND THERE TWICE — once in one's own tree,
           once under the container it is mounted in. It is ONE checkbox in
           two rows: a click on the one flips the other too, otherwise two
           statements about the same list would stand in the window, and
           deltas() would not know which one counts. */
        const mirror = new Map();   // list id -> [{ check, paint }]

        const headingRow = (name, depth, hint = null) => {
            const heading = document.createElement('div');
            heading.className = 'organize-dialog-row is-heading';
            heading.style.setProperty('--depth', depth);
            heading.textContent = name;
            if (hint) {
                const h = document.createElement('span');
                h.className = 'item-data';
                h.textContent = ' ' + hint;
                heading.append(h);
            }
            container.append(heading);
        };

        const leafRow = (l, depth) => {
            const have    = countIn(l.id);
            const initial = have === 0 ? 'empty' : (have === chosen.length ? 'full' : 'partial');

            const row = document.createElement('label');
            row.className = 'organize-dialog-row';
            row.style.setProperty('--depth', depth);

            const check = document.createElement('input');
            check.type = 'checkbox';
            check.className = 'organize-dialog-check';
            check.dataset.list  = l.id;
            check.dataset.start = initial;
            check.dataset.state = initial;

            const nameEl = document.createElement('span');
            nameEl.textContent = l.name;
            const countEl = document.createElement('span');
            countEl.className = 'organize-dialog-count';

            const paint = () => {
                const z = check.dataset.state;
                check.classList.toggle('is-full', z === 'full');
                check.classList.toggle('is-partial', z === 'partial');
                countEl.textContent = z === 'partial' ? t('%s von %s', have, chosen.length)
                    : (z === 'full' ? t('alle') : '');
                nativeState(check, z);
            };
            paint();

            check.addEventListener('click', (e) => {
                e.preventDefault();   // we toggle ourselves, in three steps
                const z = check.dataset.state;
                const next =
                    z === 'partial' ? 'full'
                  : z === 'full' ? 'empty'
                  : (check.dataset.start === 'partial' ? 'partial' : 'full');
                for (const s of mirror.get(l.id) ?? []) {
                    s.check.dataset.state = next;
                    s.paint();
                }
                summary();
            });

            if (!mirror.has(l.id)) mirror.set(l.id, []);
            mirror.get(l.id).push({ check, paint });

            row.append(check, nameEl, countEl);
            container.append(row);
        };

        const descend = (parentId, depth) => {
            for (const l of childrenOf(parentId)) {
                // Container lists stand in the section below, at their
                // container — up here they would be the same checkbox
                // twice.
                if (l.is_container_list) continue;
                // Auto lists are no targets: their content comes solely
                // from their filter. A checkbox here would promise a
                // filling that the server (punkt_listen_setzen) does not
                // accept at all.
                if (l.is_autolist) continue;
                if (!isLeaf(l.id)) {
                    /* A heading, not a receptacle: points live only in
                       leaves (see punkt_listen_setzen). */
                    headingRow(l.name, depth);
                    descend(l.id, depth + 1);
                    continue;
                }
                leafRow(l, depth);
            }
        };
        descend(null, 0);

        /* THE CONTAINERS AS TARGETS — the checkbox sits on the CONTAINER,
           always, and only there (the call of 14.08.2026: the
           result is hung onto the container; the lists inside are content,
           not targets). It toggles in three stages like a list checkbox:
           in, partly, out — "in" means the point lies in one of my lists
           mounted there. Putting in, the server handles via the vehicle
           list (organize_container_target); taking out releases the points
           from all own leaves mounted there. */
        if (shared.length > 0) {
            const titleEl = document.createElement('div');
            titleEl.className = 'organize-tree-title';
            titleEl.textContent = t('Freigaben');
            container.append(titleEl);

            for (const c of shared) {
                const leaves = ownContainerLeaves(c);
                const have  = chosen.reduce((n, p) =>
                    n + (p.lists.some((id) => leaves.has(id)) ? 1 : 0), 0);
                const initial = have === 0 ? 'empty'
                    : (have === chosen.length ? 'full' : 'partial');

                const row = document.createElement('label');
                row.className = 'organize-dialog-row';
                row.style.setProperty('--depth', 0);

                const check = document.createElement('input');
                check.type = 'checkbox';
                check.className = 'organize-dialog-check';
                check.dataset.container = c.id;
                check.dataset.start  = initial;
                check.dataset.state = initial;

                const nameEl = document.createElement('span');
                nameEl.textContent = c.name;
                const hintEl = document.createElement('span');
                hintEl.className = 'item-data';
                hintEl.textContent = c.is_mine ? '' : '· ' + c.owner;
                const countEl = document.createElement('span');
                countEl.className = 'organize-dialog-count';

                const paint = () => {
                    const z = check.dataset.state;
                    check.classList.toggle('is-full', z === 'full');
                    check.classList.toggle('is-partial', z === 'partial');
                    countEl.textContent = z === 'partial' ? t('%s von %s', have, chosen.length)
                        : (z === 'full' ? t('alle') : '');
                    nativeState(check, z);
                };
                paint();

                check.addEventListener('click', (e) => {
                    e.preventDefault();
                    const z = check.dataset.state;
                    check.dataset.state =
                        z === 'partial' ? 'full'
                      : z === 'full' ? 'empty'
                      : (initial === 'partial' ? 'partial' : 'full');
                    paint();
                    summary();
                });

                row.append(check, nameEl, hintEl, countEl);
                container.append(row);
            }
        }
        summary();
    }

    /** The leaves of my branches mounted in this container — that is where
     *  the points lie that are "in the container". */
    function ownContainerLeaves(c) {
        const leaves = new Set();
        const descend = (id) => {
            const children = childrenOf(id);
            if (children.length === 0) { leaves.add(id); return; }
            for (const k of children) descend(k.id);
        };
        for (const f of c.shares) {
            for (const root of f.children.get(null) ?? []) {
                if (listById.has(root.id)) descend(root.id);
            }
        }
        return leaves;
    }

    function deltas() {
        const added            = [];
        const removed          = [];
        const containers       = [];
        const containersOut    = [];
        /* A list can stand in the window twice (own tree and container
           section, see mirror in fillDialog) — it is counted once.
           The states are equal via the mirror anyway; here it is only
           about not sending them twice. */
        const seen = new Set();
        for (const check of el('organize-dialog-lists').querySelectorAll('.organize-dialog-check')) {
            // The checkbox on a container toggles like a list checkbox —
            // in, the server handles via one's own list there
            // (organize_container_target); out releases the points from all
            // own leaves mounted there.
            if (check.dataset.container) {
                // start/state and their three values live SOLELY in this
                // file — fillDialog() sets them, this loop reads them.
                // Which is exactly why they could be translated without a
                // risk the form field names did not have: a name only one
                // file uses cannot fall out of step with a second one.
                // The CSS had been saying is-full/is-partial all along.
                const { start: initial, state: z } = check.dataset;
                if (z === 'full' && initial !== 'full') containers.push(check.dataset.container);
                if (z === 'empty' && initial !== 'empty') containersOut.push(check.dataset.container);
                continue;
            }
            const { start: initial, state: z, list } = check.dataset;
            if (seen.has(list)) continue;
            seen.add(list);
            if (z === 'full' && initial !== 'full') added.push(list);
            if (z === 'empty' && initial !== 'empty') removed.push(list);
        }
        return { added, removed, containers, containersOut };
    }

    function summary() {
        const { added, removed, containers, containersOut } = deltas();
        const parts = [
            ...added.map((id) => `+„${listById.get(Number(id))?.name}"`),
            ...removed.map((id) => `−„${listById.get(Number(id))?.name}"`),
            ...containers.map((id) => `+„${sharedById.get('c:' + id)?.name}"`),
            // On taking out, the line says WHAT the points fall out of:
            // if an original list hangs in the container, they leave that
            // one too — it is the same assignment, and keeping quiet would
            // be the sort of surprise you only understand days later.
            ...containersOut.map((id) => {
                const c = sharedById.get('c:' + id);
                const leaves = [...ownContainerLeaves(c)]
                    .map((l) => listById.get(l)).filter(Boolean);
                const originals = leaves
                    .filter((l) => !l.is_container_list && !l.is_autolist)
                    .map((l) => `„${l.name}"`);
                /* If an AUTO LIST hangs in the container, the checkbox
                   cannot take its points out — their assignment belongs
                   to the filter. That has to stand here, or you wonder
                   why the points "stay in the container". */
                const auto = leaves.filter((l) => l.is_autolist)
                    .map((l) => `„${l.name}"`);
                let text = `−„${c?.name}"` + (originals.length > 0
                    ? t(' (auch aus %s)', originals.join(', ')) : '');
                if (auto.length > 0) {
                    text += auto.length === 1
                        ? t(' — aus %s fallen sie nicht, die folgt ihrem Filter', auto.join(', '))
                        : t(' — aus %s fallen sie nicht, die folgen ihrem Filter', auto.join(', '));
                }
                return text;
            }),
        ];
        const n = selection.size;
        el('organize-dialog-summary').textContent = parts.length === 0
            ? t('Keine Änderung — nur angefasste Haken zählen.')
            : (n === 1
                ? t('Bei einem Punkt: %s. Alles andere bleibt, wie es ist.', parts.join(', '))
                : t('Bei %s Punkten: %s. Alles andere bleibt, wie es ist.', n, parts.join(', ')));
        el('organize-dialog-ok').disabled = parts.length === 0;
    }

    /**
     * Completely chosen own lists — the topmost nodes beneath which EVERY
     * point lies in the selection. Empty leaves do not count (they contain
     * nothing one could share), container lists neither (they are
     * vehicles, not lists one keeps). The server computes the same thing
     * once more — this here only decides whether to ask.
     *
     * THE COUNTERS WERE COUNTING PAST THEMSELVES until 21.08.2026: the
     * object was created as {total, inside} and raised as z.gesamt++ /
     * z.drin++ — the halves of one rename, and neither side complains. In
     * JavaScript undefined++ is NaN and costs not a word, so total stayed
     * at 0, isComplete() bailed out at its first line, and this function
     * always returned the empty list. The consequence was invisible and
     * pointed the wrong way: whole lists went into a container as a
     * snapshot without anyone being asked whether they should grow along.
     */
    function completeLists() {
        const per = new Map();   // list id -> { total, inside }
        for (const p of points) {
            for (const id of p.lists) {
                if (!per.has(id)) per.set(id, { total: 0, inside: 0 });
                const z = per.get(id);
                z.total++;
                if (selection.has(p.id)) z.inside++;
            }
        }
        const isComplete = (id) => {
            let full = 0, missing = false;
            (function ab(i) {
                const children = childrenOf(i);
                if (children.length === 0) {
                    const z = per.get(i);
                    if (!z || z.total === 0) return;
                    if (z.inside === z.total) full++; else missing = true;
                    return;
                }
                for (const k of children) ab(k.id);
            })(id);
            return full > 0 && !missing;
        };
        const topmost = [];
        const descend = (parentId) => {
            for (const l of childrenOf(parentId)) {
                if (l.is_container_list) continue;
                if (isComplete(l.id)) { topmost.push(l); continue; }
                descend(l.id);
            }
        };
        descend(null);
        return topmost;
    }

    dialog.querySelector('form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const { added, removed, containers, containersOut } = deltas();
        if (added.length === 0 && removed.length === 0
            && containers.length === 0 && containersOut.length === 0) {
            dialog.close();
            return;
        }

        // IF WHOLE LISTS SIT IN THE SELECTION, the maintainer decides himself
        // HOW they go into the container — when sharing, nothing should
        // happen tacitly, and the two paths differ exactly where it
        // counts: whether the share grows along or is frozen. Cancel
        // leads back into the open dialog, without consequence.
        let snapshot  = containers;
        let originals = [];
        if (containers.length > 0) {
            const full = completeLists();
            if (full.length > 0) {
                const names = full.map((l) => `„${l.name}"`).join(', ');
                const single  = full.length === 1;
                const choice = await choose({
                    title: t('Wie in die Freigabe?'),
                    text: single
                        ? t('Die Auswahl enthält die Liste %s vollständig.', names)
                        : t('Die Auswahl enthält die Listen %s vollständig.', names),
                    options: [
                        { value: 'originals',
                          label: single ? t('Originalliste einhängen') : t('Originallisten einhängen'),
                          hint: t('wachsend — auch was du dort später hinzufügst, sehen die Mitglieder') },
                        { value: 'snapshot', label: t('Momentaufnahme'),
                          hint: t('eigene Liste mit genau dieser Auswahl — sie wächst nur, wenn du selbst nachlegst') },
                    ],
                });
                if (choice === null) return;
                if (choice === 'originals') { originals = containers; snapshot = []; }
            }
        }

        dialog.close();
        // THE KEYS ARE THE FORM'S FIELD NAMES — English, like the names in
        // the markup and the $_POST keys in organize_content.php. They
        // were German here until 21.08.2026 while the other two sides had
        // long been translated, and nothing said a word (see submitForm).
        submitForm(el('organize-lists-form'), { add: added.join(','), remove: removed.join(','),
                                               add_container: snapshot.join(','),
                                               add_container_original: originals.join(','),
                                               remove_container: containersOut.join(',') });
    });
    dialog.querySelector('.dialog-footer .subtle').addEventListener('click', () => dialog.close());

    // -----------------------------------------------------------------
    // Setting a field — the mass update
    // -----------------------------------------------------------------

    const fieldDialog = el('organize-field-dialog');

    /* Which fields the selection knows at all: title and note always, the
       find fields as soon as a chosen point hangs in a species branch,
       plus the custom fields of the affected root lists — the same
       derivation as at the point window (extraFieldsFor in map.js). */
    function fieldCandidates() {
        const chosen = points.filter((p) => selection.has(p.id));
        const roots = new Set();
        let hasSpecies = false;
        for (const p of chosen) {
            for (const id of p.lists) {
                const w = rootOf(id);
                if (!w) continue;
                roots.add(w.id);
                if (w.is_species) hasSpecies = true;
            }
        }
        const fields = [];
        if (hasSpecies) fields.push(...(findFields ?? []));
        for (const rootId of roots) {
            for (const f of listById.get(rootId)?.fields ?? []) {
                if (!fields.some((x) => x.name.toLowerCase() === f.name.toLowerCase())) {
                    fields.push(f);
                }
            }
        }
        return fields;
    }

    function fillFieldDialog() {
        const choice = el('organize-field-selection');
        choice.textContent = '';
        const fixed = [['title', t('Titel')], ['note', t('Notiz')]];
        for (const [value, text] of fixed) {
            const o = document.createElement('option');
            o.value = value;
            o.textContent = text;
            choice.append(o);
        }
        const candidates = fieldCandidates();
        for (const f of candidates) {
            const o = document.createElement('option');
            o.value = f.name;
            o.textContent = f.name;
            choice.append(o);
        }
        choice.dataset.candidates = JSON.stringify(candidates);
        drawFieldInput();
    }

    /* The value field follows the field type: text, multiline or the
       select with its levels — the same three shapes as at the point
       window. */
    function drawFieldInput() {
        const choice = el('organize-field-selection');
        const space = el('organize-field-input');
        const candidates = JSON.parse(choice.dataset.candidates || '[]');
        const field = candidates.find((f) => f.name === choice.value);
        const type = choice.value === 'note' ? 'multiline' : (field?.type ?? 'text');

        space.textContent = '';
        let input;
        if (type === 'choice' && Array.isArray(field?.options)) {
            input = document.createElement('select');
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = '—';
            input.append(empty);
            for (const o of field.options) {
                const opt = document.createElement('option');
                opt.value = o;
                opt.textContent = o;
                input.append(opt);
            }
        } else if (type === 'multiline') {
            input = document.createElement('textarea');
            input.rows = 3;
        } else {
            input = document.createElement('input');
            input.type = 'text';
        }
        input.id = 'organize-field-value';
        input.addEventListener('input', fieldSummary);
        input.addEventListener('change', fieldSummary);
        space.append(input);

        const clear = el('organize-field-clear');
        clear.checked = false;
        /* A point without a title is a nobody on the map — the title can
           only be set, never emptied. */
        clear.disabled = choice.value === 'title';
        fieldSummary();
    }

    function fieldSummary() {
        const choice = el('organize-field-selection');
        const value  = el('organize-field-value')?.value.trim() ?? '';
        const clear  = el('organize-field-clear').checked;
        const name   = choice.selectedOptions[0]?.textContent ?? choice.value;
        const n      = selection.size;
        const at     = n === 1 ? t('beim gewählten Punkt') : t('bei allen %s gewählten', n);

        el('organize-field-value').disabled = clear;
        let text;
        let ready = true;
        if (clear) {
            text = t('„%s" wird %s geleert.', name, at);
        } else if (value !== '') {
            text = t('„%s" wird %s auf „%s" gesetzt.', name, at, value);
        } else if (choice.value === 'note') {
            text = t('Die Notiz wird %s geleert — ein leerer Wert und Leeren sind hier dasselbe.', at);
        } else {
            text = t('Ohne Wert bitte „Feld leeren" ankreuzen.');
            ready = false;
        }
        el('organize-field-summary').textContent = text;
        el('organize-field-ok').disabled = !ready;
    }

    // -----------------------------------------------------------------
    // Many find numbers at once — the window lives in numberdialog.js
    // -----------------------------------------------------------------

    /* A find is an own point in a species branch — the rule of the
       Fund-Nr. column and of find_number_mint(). */
    function isOwnFind(p) {
        return !!p && !p.foreign && p.lists.some((id) => rootOf(id)?.is_species);
    }

    /* THE TABLE'S ORDER, not the order of the ticks: the chosen finds as
       the table shows them — the window numbers by find time unless the
       user picks the table's sort. What stays numbered outside the choice
       is taken, the trash included (a find put back keeps its number). */
    el('organize-number-button').addEventListener('click', () => {
        const chosen = visiblePoints().filter((p) => selection.has(p.id));
        const finds = chosen.filter((p) => isOwnFind(ownById.get(p.id)))
            .map((p) => ({ id: p.id, title: p.title, number: p.find_number ?? null, found_at: p.found_at ?? null }));
        if (finds.length === 0) return;
        const numbered = new Set(finds.map((f) => f.id));
        // Keyed by the number in the series, not by its letters (findNumberKey).
        const key = (number) => findNumberKey(findSeries?.pattern ?? null, number);
        const taken = new Map();
        for (const p of points) {
            if (p.find_number && !numbered.has(p.id)) taken.set(key(p.find_number), p.title);
        }
        for (const n of trashNumbers) {
            taken.set(key(n.number), t('%s (im Papierkorb)', n.title));
        }
        // Sorted by find time, oldest first, is what the window does anyway.
        const column = sortBy ? columnByKey.get(sortBy.key) : null;
        const sorted = column && !(sortBy.key === 'found_at' && sortBy.dir === 1)
            ? { label: column.label, dir: sortBy.dir } : null;
        openNumbers({
            finds,
            outside: chosen.filter((p) => !numbered.has(p.id)).map((p) => p.title || t('Ohne Titel')),
            taken,
            series: findSeries,
            sorted,
            done: (answer) => submitForm(el('organize-number-form'), {
                points: answer.points, mode: answer.mode, start: answer.start, restart: answer.restart,
                scope: answer.scope, old: answer.old, adopt: answer.adopt,
            }),
        });
    });

    el('organize-field-selection').addEventListener('change', drawFieldInput);
    el('organize-field-clear').addEventListener('change', fieldSummary);

    el('organize-field-button').addEventListener('click', () => {
        if (selection.size === 0) return;
        fillFieldDialog();
        fieldDialog.showModal();
    });

    fieldDialog.querySelector('form').addEventListener('submit', (e) => {
        e.preventDefault();
        const choice = el('organize-field-selection');
        const value  = el('organize-field-value')?.value.trim() ?? '';
        const clear  = el('organize-field-clear').checked;
        fieldDialog.close();
        submitForm(el('organize-field-form'), {
            field: choice.value,
            value: clear ? '' : value,
            clear: clear ? '1' : '',
        });
    });
    fieldDialog.querySelector('.dialog-footer .subtle').addEventListener('click', () => fieldDialog.close());

    // -----------------------------------------------------------------
    // The structure hand moves per list — via the pencil on its row
    // -----------------------------------------------------------------

    /**
     * The pencil menu: rename, move, merge, delete.
     *
     * Until 14.08.2026 these four hung as a button bar at the bottom of
     * the CHOSEN list. Since the choice holds several lists at once,
     * "the chosen one" no longer exists — the tool belongs on the row,
     * like the pencil in the map's sidebar. As a small drop-down menu on
     * the pencil that only opens a window via the chosen entry — the
     * explanations live there, not in the menu.
     */
    async function listMenu(listId, anchor) {
        const list = listById.get(listId);
        if (!list) return;
        const choice = await popupMenu({
            anchor: anchor,
            /* An auto list keeps name and delete: moving does not exist
               (it stands outside the tree, liste_eltern_pruefen), merging
               neither — its content belongs to the filter. */
            entries: list.is_autolist
                ? [
                    { value: 'name', label: t('Umbenennen') },
                    { value: 'delete', label: t('Löschen'), danger: true },
                ]
                : [
                    { value: 'name', label: t('Umbenennen') },
                    /* „Verschieben" means into a Mappe or out of it since
                       0168 — offered while there is somewhere to go. */
                    ...(folders.length > 0 || list.folder_id != null
                        ? [{ value: 'parent', label: t('In Mappe legen') }] : []),
                    /* Merging exists only from leaf to leaf — a branch is
                       dissolved by merging its leaves one by one and
                       deleting the empty superstructure. */
                    ...(isLeaf(listId)
                        ? [{ value: 'merge', label: t('Zusammenführen') }] : []),
                    { value: 'delete', label: t('Löschen'), danger: true },
                ],
        });
        if (choice === 'name')   renameList(listId);
        if (choice === 'parent') moveList(listId);
        if (choice === 'merge')  mergeList(listId);
        if (choice === 'delete') deleteList(listId);
    }

    /* Through submitForm and not past it: writing the four fields itself
       would be the fifth place that can miss one without saying so. */
    function submitTree(action, listId, fields = {}) {
        submitForm(el('organize-tree-form'), {
            action,
            list:   String(listId),
            name:   fields.name ?? '',
            target: fields.target ?? '',
        });
    }

    /* THE BUTTON FOLLOWS THE PANE (23.08.2026): under the
       Touren pane it creates a tour, under Listen a list. One button, not
       two side by side of which one is always the wrong one — it belongs
       to what stands on the left.

       A NEW TOUR IS EMPTY, deliberately (same day): the
       checked points are NOT taken along. So it is the NAME that comes
       into being here, in the registry of migration 0073; the points join
       it in the field, where the point window's tour picker offers it. */
    el('organize-new-list').addEventListener('click', async () => {
        const tours = state.pane === 'tours';
        const name = await prompt({
            title: tours ? t('Neue Tour') : t('Neue Liste'),
            label: t('Name'),
            value: '',
            button: t('Anlegen'),
        });
        if (name === null || name.trim() === '') return;
        if (tours) submitTour('tour_new', { to: name.trim() });
        else       submitTree('list_new', '', { name: name.trim() });
    });

    async function renameList(listId) {
        const list = listById.get(listId);
        if (!list) return;
        const name = await prompt({
            title: t('Liste umbenennen'),
            label: t('Name'),
            value: list.name,
            button: t('Umbenennen'),
        });
        if (name !== null && name.trim() !== '' && name.trim() !== list.name) {
            submitTree('list_name', listId, { name: name.trim() });
        }
    }

    /* The target window — one shell for moving and merging. */
    const targetDialog = el('organize-target-dialog');
    let targetDone = null;

    /* `questions` (optional) are asked beneath the hint for the current
       choice — each a question with its answers as radios, all in sight
       and one preselected, so none can stay open (CLAUDE.md: a question
       is as large as its answers). Only the export uses them, for the
       MykIS sheet; done() receives the answers by name. A change of the
       choice redraws them, so switching away from MykIS and back starts
       again from the preselection. */
    /* `explain` (optional) folds an explanation behind a question mark
       under the hint — {title, lead, items, tail}, the same .info-fold
       as in the settings. Only the export uses it, for the MykIS sheet. */
    function openTarget({ title, label, button, candidates, preselect, hint, done, questions = () => [],
                          explain = () => null }) {
        el('organize-target-title').textContent = title;
        el('organize-target-label').textContent = label;
        el('organize-target-ok').textContent = button;

        const choice = el('organize-target-selection');
        choice.textContent = '';
        for (const k of candidates) {
            const o = document.createElement('option');
            o.value = k.value;
            o.textContent = k.text;
            choice.append(o);
        }
        choice.value = preselect ?? candidates[0]?.value ?? '';
        if (choice.value === '' && preselect !== '') choice.selectedIndex = 0;

        const show = () => {
            el('organize-target-hint').textContent = hint(choice.value);
            const fold = el('organize-target-explain');
            fold.textContent = '';
            const about = explain(choice.value);
            if (about) {
                const details = document.createElement('details');
                details.className = 'info-fold';
                const summary = document.createElement('summary');
                summary.append(icon('help'), about.title);
                details.append(summary);
                const paragraph = (text) => {
                    const p = document.createElement('p');
                    p.className = 'dialog-text';
                    p.textContent = text;
                    return p;
                };
                if (about.lead) details.append(paragraph(about.lead));
                if (about.items?.length) {
                    const list = document.createElement('ul');
                    list.className = 'dialog-text';
                    for (const item of about.items) {
                        const li = document.createElement('li');
                        li.textContent = item;
                        list.append(li);
                    }
                    details.append(list);
                }
                for (const text of about.tail ?? []) details.append(paragraph(text));
                fold.append(details);
            }
            const box = el('organize-target-questions');
            box.textContent = '';
            for (const q of questions(choice.value)) {
                const set = document.createElement('fieldset');
                set.className = 'field question';
                const legend = document.createElement('legend');
                legend.textContent = q.legend;
                set.append(legend);
                if (q.note) {
                    const note = document.createElement('p');
                    note.className = 'question-note';
                    note.textContent = q.note;
                    set.append(note);
                }
                // Ticks instead of answers: each one its own yes or no,
                // sent as '1' or '0' under its own name.
                for (const tick of q.ticks ?? []) {
                    const row = document.createElement('label');
                    row.className = 'choice-row';
                    const box = document.createElement('input');
                    box.type = 'checkbox';
                    box.name = 'target-' + tick.name;
                    box.checked = !!tick.checked;
                    row.append(box, ' ' + tick.text);
                    set.append(row);
                }
                for (const option of q.options ?? []) {
                    const row = document.createElement('label');
                    row.className = 'choice-row';
                    const radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = 'target-' + q.name;
                    radio.value = option.value;
                    radio.checked = option.value === q.chosen;
                    row.append(radio, ' ' + option.text);
                    set.append(row);
                }
                box.append(set);
            }
        };
        choice.onchange = show;
        show();

        targetDone = done;
        targetDialog.showModal();
    }

    targetDialog.querySelector('form').addEventListener('submit', (e) => {
        e.preventDefault();
        const value = el('organize-target-selection').value;
        const answers = {};
        for (const radio of el('organize-target-questions').querySelectorAll('input[type="radio"]:checked')) {
            answers[radio.name.replace(/^target-/, '')] = radio.value;
        }
        for (const box of el('organize-target-questions').querySelectorAll('input[type="checkbox"]')) {
            answers[box.name.replace(/^target-/, '')] = box.checked ? '1' : '0';
        }
        targetDialog.close();
        targetDone?.(value, answers);
    });
    targetDialog.querySelector('.dialog-footer .subtle').addEventListener('click', () => targetDialog.close());

    /* Non-breaking spaces: ordinary ones fell victim to HTML's collapsing
       of whitespace, and the indent would be gone. */
    const indent = (depth) => '   '.repeat(depth);

    function moveList(listId) {
        const list = listById.get(listId);
        if (!list) return;
        openTarget({
            title: t('In Mappe legen'),
            label: t('In welche Mappe?'),
            button: t('Verschieben'),
            candidates: [
                { value: '', text: t('— keine Mappe —') },
                ...[...folders]
                    .sort((a, b) => (a.sort_order - b.sort_order) || a.name.localeCompare(b.name, 'de'))
                    .map((f) => ({ value: String(f.id), text: f.name })),
            ],
            preselect: list.folder_id == null ? '' : String(list.folder_id),
            hint: () => t('„%s" behält alle Punkte und Einstellungen. Die Mappe fasst nur zusammen.', list.name),
            done: (value) => submitTree('list_folder', listId, { target: value }),
        });
    }

    function mergeList(listId) {
        const source = listById.get(listId);
        if (!source) return;
        const n = directCount(source.id);
        openTarget({
            title: t('Liste zusammenführen'),
            label: t('In welche Liste?'),
            button: t('Zusammenführen'),
            candidates: treeSequence()
                .filter(({ l }) => l.id !== source.id && isLeaf(l.id))
                .map(({ l, depth }) => ({ value: String(l.id), text: indent(depth) + l.name })),
            hint: (value) => {
                const target = listById.get(Number(value));
                if (!target) return '';
                let text = n === 1
                    ? t('Ein Punkt zieht nach „%s", dann verschwindet „%s". Was schon im Ziel hängt, wird nicht doppelt zugeordnet.', target.name, source.name)
                    : t('%s Punkte ziehen nach „%s", dann verschwindet „%s". Was schon im Ziel hängt, wird nicht doppelt zugeordnet.', n, target.name, source.name);
                if (rootOf(source.id)?.is_species && !rootOf(target.id)?.is_species) {
                    text += t(' Achtung: „%s" gehört zu einer Artenliste, das Ziel nicht — die Funde verlieren dort Fundfelder-Anzeige und Artenauswertung.', source.name);
                }
                return text;
            },
            done: (value) => submitTree('list_merge', listId, { target: value }),
        });
    }

    // -----------------------------------------------------------------
    // The auto list — the configured filter as a list
    // -----------------------------------------------------------------

    /* What the filter currently covers, as a sentence for the create
       window — including the announcement of what does NOT come along:
       container views carry foreign points (those cannot live in an own
       list), and an auto list is no good as the source of an auto list
       (cycles). Without the announcement the new list would be quietly
       smaller than the table on the right, and when sharing nothing may
       happen tacitly. */
    function autoFilterText() {
        const sources = [];
        const dropped = [];
        for (const s of state.lists) {
            if (s === 'none') { sources.push(t('„Ohne Liste"')); continue; }
            if (typeof s === 'number') {
                const l = listById.get(s);
                if (!l) continue;
                if (l.is_autolist) dropped.push(t('„%s" (selbst eine Autoliste)', l.name));
                else sources.push(`„${l.name}"`);
            } else if (s.startsWith('r:')) {
                const l = listById.get(listIdOf(s));
                if (!l) continue;
                if (l.is_autolist) dropped.push(t('„%s" (selbst eine Autoliste)', l.name));
                else sources.push(`„${l.name}"`);
            } else if (s.startsWith('c:')) {
                dropped.push(t('„%s" (Freigabe-Sicht)', sharedById.get(s)?.name));
            } else {
                dropped.push(t('„%s" (fremde Liste)', foreignListById.get(listIdOf(s))?.name));
            }
        }
        const date = (w) => w.split('-').reverse().join('.');
        const parts = [sources.length > 0 ? t('aus %s', sources.join(', ')) : t('aus allen deinen Punkten')];
        for (const b of filter.readTerms(state.terms)) {
            parts.push(b.type === 'contains' ? t('enthält „%s"', b.text) : t('enthält nicht „%s"', b.text));
        }
        if (state.noTime) parts.push(t('ohne Fundzeit'));
        else {
            if (state.from !== '') parts.push(t('ab %s', date(state.from)));
            if (state.to !== '') parts.push(t('bis %s', date(state.to)));
        }
        let text = t('Die Liste folgt dem eingestellten Filter: %s. Sie führt sich von selbst nach — von Hand füllen lässt sie sich nicht, in Freigaben einhängen schon.',
            parts.join(', '));
        if (dropped.length > 0) {
            text += t(' Nicht in den Filter kommen %s — eine Autoliste führt nur eigene Punkte aus gewöhnlichen Listen.',
                dropped.join(', '));
        }
        return text;
    }

    el('organize-auto-button').addEventListener('click', async () => {
        const name = await prompt({
            title: t('Autoliste anlegen'),
            text: autoFilterText(),
            label: t('Name'),
            button: t('Anlegen'),
        });
        if (name !== null && name.trim() !== '') {
            // Via the tree form: action and name travel there, the
            // state — that is, the filter itself — regardless
            // (submitForm).
            submitTree('autolist', '', { name: name.trim() });
        }
    });

    async function deleteList(listId) {
        const list = listById.get(listId);
        if (!list) return;
        const children = childrenOf(list.id).length;
        /* An empty list without sublists goes without a confirmation —
           there is nothing it could inform about. This is the import-list
           wrap-up: everything moved out, empty shell gone, one click. */
        if (children === 0 && branchCount(list.id) === 0) {
            submitTree('list_delete', listId);
            return;
        }
        const ok = await confirm({
            title: t('Liste löschen?'),
            text: t('„%s" verschwindet. Ihre Punkte bleiben — was NUR hier stand, findest du danach unter „Ohne Liste".', list.name)
                + (children > 0
                    ? (children === 1
                        ? t(' Die Unterliste rückt eine Ebene auf, nichts darunter geht verloren.')
                        : t(' Die %s Unterlisten rücken eine Ebene auf, nichts darunter geht verloren.', children))
                    : ''),
            button: t('Löschen'),
            danger: true,
        });
        if (ok) submitTree('list_delete', listId);
    }

    drawHead();
    drawAll();
}
