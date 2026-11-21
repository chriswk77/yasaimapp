/**
 * The point window — the window a find opens in: its text fields, the
 * species search, the custom fields of its lists, the filing in the list
 * tree, the outline row, „Direkt teilen", the number series' hint, what a
 * foreign find allows, and the save (own, foreign, joint).
 *
 * OUT OF map.js ON 11.10.2026, as step three of the Fundbrowser becoming
 * the place finds are worked on („im Prinzip alles, was die
 * Sidebar, das Punktefenster momentan kann"). THE SAME WAY the picture
 * viewer left it on 30.09.2026 (photowindow.js): moved as it stood, found
 * by a scope analysis of map.js's top level — what the region needs from
 * the rest of the file became its HOST, what the rest of the file uses of
 * it comes back from mountPointWindow(). The code itself is unchanged.
 *
 * A MOUNT FUNCTION AND NOT A MODULE THAT RUNS ON IMPORT, for the viewer's
 * reason: it looks its elements up and wires its listeners when it is
 * mounted, and an imported module is evaluated before the page has built
 * what it asks for. map.js mounts it exactly where it stood in the file's
 * order of events.
 *
 * THE HOST is whatever the destructuring at the head of mountPointWindow
 * names — the values (start, state, the element sets) and the functions of
 * the page around it (drawList, drawMarker, selectPoint, status …) — plus
 * seven pieces of state both sides write, which the host hands over as
 * getter/setter pairs (lastFilled, outlineDraft, speciesSources, the tab
 * counters). A page that does not draw a map hands functions that do
 * nothing for the marker's and the sidebar's parts.
 */

import { t } from './i18n.js';
import { findNumberFallback, findNumberReserved, readFindNumber, normalizeFindNumber, numberDate, nextFreeNumber, findNumberKey } from './findnumber.js';
import { api, request } from './api.js';
import { settings } from './settings.js';
import { confirm, choose, ask } from './dialogs.js';
import { loadSpeciesList, searchSpeciesLocally, listGroups, familiarFirst, groupOf } from './species.js';
import * as photos from './photos.js';
import * as galleryOriginals from './galleryoriginals.js';
import { icon, iconHtml } from './icons.js';
import { traitPicker, sporeFieldBind } from './findtraits.js';
import { shapePoint } from './shapes.js';
import * as polygon from './polygon.js';
import { mountCollection, COLLECTION_COPIED_FIELDS } from './collectionui.js';
import { openMarksIn, familiarName, CERTAINTY_FIELD } from './identification.js';
import * as filter from './filter.js';
import * as timeRange from './timerange.js';
import * as tour from './tour.js';
import { differences, outlineEqual } from './fieldtimes.js';
import { placeWritten } from './finddetails.js';

/**
 * The elements of the point window, by the names the code in
 * mountPointWindow uses — looked up once, from the markup
 * includes/point_window.php prints. A function and not constants of the
 * module because TWO PAGES mount the window now (the map and the
 * Fundbrowser), and each hands the sets to its mount: defined here there
 * is one list of ids, not two.
 *
 * The photo and sequence sets belong to the gallery and the sequence
 * window, which stay with the map for now; a page without those elements
 * gets nulls, and the window's code asks for them with ?. where it must.
 */
export function pointWindowElements() {
    return {
        fields: {
            title:       document.getElementById('d-title'),
            note:       document.getElementById('d-note'),
            lists:      document.getElementById('d-lists'),
            extra:      document.getElementById('d-extra-fields'),
            // The find fields that stand under the find number (find_fields.php, 'after').
            extraAfter: document.getElementById('d-extra-after-number'),
            coord:       document.getElementById('d-coord'),
            found:    document.getElementById('d-found'),
            number:     document.getElementById('d-number'),
            numberField: document.getElementById('d-number-field'),
            numberHint: document.getElementById('d-number-hint'),
            accuracy: document.getElementById('d-accuracy'),
            heading: document.getElementById('detail-title'),
        },
        photoFields: {
            gallery:   document.getElementById('d-photos'),
            hint:   document.getElementById('d-photo-hint'),
            capture: document.getElementById('d-photo-capture'),
            choose:   document.getElementById('d-photo-select'),
            microscope: document.getElementById('d-photo-microscope'),
            camera:    document.getElementById('d-photo-camera'),
            file:     document.getElementById('d-photo-file'),
        },
        sequenceFields: {
            field:  document.getElementById('d-sequences-field'),
            list:   document.getElementById('d-sequences'),
            add:    document.getElementById('d-sequence-add'),
            window: document.getElementById('sequence-window'),
            form:   document.getElementById('sequence-form'),
            point:  document.getElementById('sequence-point'),
            marker: document.getElementById('sequence-marker'),
            text:   document.getElementById('sequence-text'),
            file:   document.getElementById('sequence-file'),
            accession: document.getElementById('sequence-accession'),
            sample: document.getElementById('sequence-sample'),
            lab:    document.getElementById('sequence-lab'),
            primers: document.getElementById('sequence-primers'),
            note:   document.getElementById('sequence-note'),
            cancel: document.getElementById('sequence-cancel'),
            save:   document.getElementById('sequence-save'),
        },
    };
}

export function mountPointWindow(host) {
    const {
        GUEST,
        fields,
        hintStart,
        map,
        outlinesPossible,
        photoFields,
        sharedLists,
        start,
        state,
        centreVisible,
        clearSelection,
        closeDetail,
        computeListCounts,
        countConflicts,
        drawList,
        drawListSelection,
        drawMarker,
        drawPhotoGallery,
        drawSequences,
        fitVisible,
        foreignPoint,
        hintLeave,
        isPhone,
        listRoot,
        moveMode,
        outlineNow,
        pauseFollowing,
        pointIsFind,
        pointReady,
        redrawOutline,
        selectPoint,
        sharedRights,
        shortNameOf,
        status,
        syncExcursions,
        syncHints,
        syncShared,
    } = host;

    /* The point window's pieces of it (drawHintTile, drawHintReceived) — up here
       and not beside them, because fillDetail may run before the file gets there. */
    // Aufsammlungen (0174): the window's parts for a sample and its species.
    const collectionUi = mountCollection({
        state, selectPoint, status,
        isForeign: (id) => foreignPoint(id) != null,
        // An Aufsammlung turned into a find (collection_to_find): what was
        // typed goes first, then the server's answer replaces the point.
        saveFirst: async () => !detailDirty() || (await saveDetail({ quiet: true })) !== false,
        converted: (fresh) => {
            state.points.set(fresh.id, { ...state.points.get(fresh.id), ...fresh });
            computeListCounts();
            drawMarker();
            drawList();
            if (state.selectedId === fresh.id) fillDetail(state.points.get(fresh.id), true);
        },
    });

    const hintFields = {
        tile:         document.getElementById('d-hints'),
        tileField:    document.getElementById('d-hints-field'),
        received:     document.getElementById('d-hint-received'),
        receivedText: document.getElementById('d-hint-received-text'),
        leave:        document.getElementById('d-hint-leave'),
    };

    // ---------------------------------------------------------------------------
    // Selection and detail window
    // ---------------------------------------------------------------------------

    const detail = document.getElementById('detail');

    // ---------------------------------------------------------------------------
    // The outline in the detail window
    //
    // A DRAFT, NO IMMEDIATE WRITING — the same rule as when moving a marker.
    // Whoever redraws or removes an outline sees it immediately in the row, but
    // it takes effect only with "Speichern". For points you find again over
    // years, an accidentally wiped-away outline is expensive.
    //
    //   undefined   nothing changed, point.outline applies
    //   Array       newly drawn
    //   null        removed
    // ---------------------------------------------------------------------------

    const outlineFields = {
        block:    document.getElementById('d-polygon-field'),
        text:     document.getElementById('d-polygon'),
        draw: document.getElementById('d-outline-draw'),
        remove:      document.getElementById('d-outline-remove'),
    };

    function drawOutlineRow(corners) {
        if (!outlinesPossible) {
            outlineFields.block.hidden = true;
            return;
        }

        const has = polygon.isUsable(corners);
        outlineFields.remove.hidden = !has;
        outlineFields.draw.textContent = has ? t('Umriss neu ziehen') : t('Umriss ziehen');

        if (!has) {
            // No outline is the normal case, not a defect. That is why it does
            // not say "keine Fläche" here but what the button next to it
            // offers — „noch", because the button below offers exactly that
            // (02.10.2026, instead of „Ein Punkt ohne Ausdehnung":
            // „das kann man geschickter nennen").
            outlineFields.text.textContent = t('Dieser Punkt hat noch keinen Umriss.');
            return;
        }

        const m2 = polygon.areaM2(corners);
        let text = t('%s · %s Umfang · %d Ecken', polygon.readableArea(m2),
            polygon.readableLength(polygon.perimeterM(corners)), corners.length);

        // IF THE SPOT HAS WANDERED OUT OF ITS OWN AREA, it must say so. This
        // can happen when dragging the marker — the outline stays put, the
        // coordinate does not. And map sheet and terrain height hang on the
        // coordinate: without this note, a report would silently carry a map
        // sheet that belongs to a spot next to what was mapped.
        //
        // Only say it, do not prevent it: there can be good reasons to put the
        // point exactly at the find instead of at the center.
        const where = readCoordinates(fields.coord.value);
        if (where && !polygon.inOutline(where, corners)) {
            text += ' · ' + t('die Stelle liegt außerhalb');
        }

        outlineFields.text.textContent = text;
    }

    outlineFields.draw.addEventListener('click', () => {
        if (state.selectedId === null) return;
        redrawOutline(state.selectedId);
    });

    outlineFields.remove.addEventListener('click', () => {
        host.outlineDraft = null;
        drawOutlineRow(null);
        drawMarker();
        markUnsaved(true);
        status(t('Umriss entfernt — noch nicht gespeichert.'));
    });

    /**
     * Does the window hold something that is not saved?
     *
     * Two witnesses, because the two kinds of change leave different marks:
     * typed text, a ticked list, a changed time move the comparison state
     * away from what fillDetail() last wrote (detailState / lastFilled); a
     * dragged marker or a drawn outline set the unsaved mark on the
     * coordinate field (markUnsaved) — the field itself may read the same
     * when the marker was dragged back to where it was, and the outline
     * is not in the comparison at all.
     */
    function detailDirty() {
        if (state.selectedId === null || detail.hidden || host.lastFilled.state === null) return false;
        return detailState() !== host.lastFilled.state || fields.coord.classList.contains('is-unsaved');
    }

    /**
     * Ask before leaving the window over unsaved input — true means: go on.
     *
     * THERE WAS NO QUESTION (18.09.2026: „es gibt keine Warnung,
     * wenn nicht gespeichert wird — wenn man das Fenster schließt und hat
     * nicht gespeichert, sind die Änderungen weg"). Every way out of the
     * window — Schließen, Escape, a tap into the map, another point's row
     * or marker — dropped the input without a word, and on the phone the
     * likeliest of them is the tap into the map right after „Zeigen", with
     * the marker just dragged.
     *
     * TWO ANSWERS, SPEICHERN AND VERWERFEN (same day: „biete
     * statt Im Fenster bleiben Speichern an") — Escape keeps the window as
     * it is. And the question SAYS WHAT is unsaved („sage das explizit, dass
     * der Punkt verschoben wurde"): a dragged marker is not „Eingaben", and
     * whoever only moved the point should read that its new position is at
     * stake. Only the USER'S ways out ask — a save or a deletion closes
     * without, because there is nothing left to lose.
     */
    async function leaveOk() {
        if (!detailDirty()) return true;
        const point = state.points.get(state.selectedId) ?? state.sharedPoints.get(state.selectedId);
        const name = point?.title || t('Ohne Titel');
        // What exactly is unsaved: the position by the field against the
        // saved one, the outline by its draft, the rest by the comparison
        // state without the coordinate.
        const moved = point != null && fields.coord.value !== `${point.lat.toFixed(6)}, ${point.lon.toFixed(6)}`;
        const outlined = host.outlineDraft !== undefined;
        const without = (s) => { const o = JSON.parse(s); delete o.coord; return JSON.stringify(o); };
        const typed = without(detailState()) !== without(host.lastFilled.state);
        const what = [];
        if (moved) what.push(t('die neue Position'));
        if (outlined) what.push(t('der geänderte Umriss'));
        if (typed || what.length === 0) what.push(t('die Eingaben in den Feldern'));
        const text = moved && what.length === 1
            ? t('Du hast „%s" verschoben — soll die neue Position gespeichert werden? Verwerfen setzt den Marker zurück.', name)
            : t('An „%s" ist noch nicht gespeichert: %s. Speichern — oder verwerfen?', name, what.join(', '));
        const answer = await ask({
            title: t('Noch nicht gespeichert'),
            text,
            buttons: [
                { label: t('Verwerfen'), value: 'discard', danger: true },
                { label: t('Speichern'), value: 'save' },
            ],
        });
        if (answer === 'save') return saveDetail();
        return answer === 'discard';
    }

    /**
     * Show that the coordinate in the field is not yet saved.
     *
     * Without this the marker would visibly sit at a new spot while the
     * database holds the old one — and on closing it would spring back without
     * comment. Instead of a sentence about it, a highlighted field suffices.
     */
    function markUnsaved(on) {
        fields.coord.classList.toggle('is-unsaved', on);
        document.getElementById('d-save').classList.toggle('is-due', on);
    }

    /* ---------------------------------------------------------------------------
       The three tabs of the point window (10.09.2026)
       ------------------------------------------------------------------------ */

    /* Tab, panel and the number on „Fotos" — looked up once. */
    const detailTabs = [
        { tab: 'd-tab-text',   panel: 'd-panel-text' },
        { tab: 'd-tab-photos', panel: 'd-panel-photos' },
        { tab: 'd-tab-place',  panel: 'd-panel-place' },
    ];

    /**
     * Show one panel, hide the others.
     *
     * IT IS NOT tabs.js, and that is on purpose: the administration's module
     * writes the choice into the address and into the settings bundle,
     * because there a tab is a PAGE one finds one's way back to. Here it is
     * a panel in a window that belongs to a single point — a remembered
     * choice would mean opening the next point on the photos, although one
     * has only just set it.
     */
    function detailTab(name, focus = true) {
        for (const entry of detailTabs) {
            const button = document.getElementById(entry.tab);
            const panel  = document.getElementById(entry.panel);
            if (!button || !panel) continue;
            const on = entry.tab === name;
            button.setAttribute('aria-selected', on ? 'true' : 'false');
            button.tabIndex = on ? 0 : -1;
            panel.hidden = !on;
            if (on && focus) button.focus();
        }
    }

    for (const entry of detailTabs) {
        const button = document.getElementById(entry.tab);
        if (!button) continue;
        button.addEventListener('click', () => detailTab(entry.tab, false));
        /* Arrows switch, Home and End jump to the edge — the same keyboard
           as in the administration, because it is the same bar. */
        button.addEventListener('keydown', (e) => {
            const order = detailTabs.filter(x => !document.getElementById(x.tab)?.hidden);
            const here = order.findIndex(x => x.tab === entry.tab);
            let next = null;
            if (e.key === 'ArrowRight') next = order[(here + 1) % order.length];
            else if (e.key === 'ArrowLeft') next = order[(here - 1 + order.length) % order.length];
            else if (e.key === 'Home') next = order[0];
            else if (e.key === 'End') next = order[order.length - 1];
            if (!next) return;
            e.preventDefault();
            detailTab(next.tab);
        });
    }

    /**
     * The number on the photo tab — and the tab itself.
     *
     * BEHIND A TAB ONE CANNOT SEE WHETHER ANYTHING LIES THERE. The same
     * consideration as for the number on the sidebar's points tab, only even
     * more needed here: photos are the reason one often opens the window at
     * all.
     *
     * And where there CAN be no photos at all — a foreign point without
     * pictures, a guest without permission —, the tab disappears along with
     * its panel. If one is standing on it right then, the choice falls back
     * to the first; an empty panel behind a tab that promises nothing would
     * be a dead end.
     */
    let photoTabCount = 0;

    function detailPhotoTab(count = photoTabCount) {
        photoTabCount = count;
        const button = document.getElementById('d-tab-photos');
        const number = document.getElementById('d-photo-count');
        const panel  = document.getElementById('d-panel-photos');
        if (!button || !panel) return;

        const field = photoFields.gallery?.closest('.field');
        const photosGone = start.photos === false || (field?.hidden ?? false);
        const sequencesGone = host.sequenceFields.field?.hidden ?? true;
        const gone  = photosGone && sequencesGone && !host.filesOffered;
        button.hidden = gone;
        panel.hidden = panel.hidden || gone;
        if (gone && button.getAttribute('aria-selected') === 'true') detailTab('d-tab-text', false);

        detailSubTabsDraw(!photosGone, !sequencesGone, host.filesOffered);

        if (!number) return;
        const total = count + host.sequenceTabCount + (host.filesOffered ? host.filesTabCount : 0);
        number.textContent = String(total);
        number.hidden = total === 0;
    }

    /* THE TWO SUB-TABS UNDER „BELEGE" — Fotos and Sequenzen (index.php says
       why). The same small switch as detailTab and for the same reason not
       tabs.js: nothing is written into the address or remembered, every point
       starts at Fotos. */
    const detailSubTabs = [
        { tab: 'd-subtab-photos',    panel: 'd-subpanel-photos',    count: 'd-subcount-photos' },
        { tab: 'd-subtab-sequences', panel: 'd-subpanel-sequences', count: 'd-subcount-sequences' },
        { tab: 'd-subtab-files',     panel: 'd-subpanel-files',     count: 'd-subcount-files' },
    ];

    let detailSubTabChosen = 'd-subtab-photos';

    function detailSubTab(name, focus = true) {
        detailSubTabChosen = name;
        for (const entry of detailSubTabs) {
            const button = document.getElementById(entry.tab);
            if (!button) continue;
            const on = entry.tab === name;
            button.setAttribute('aria-selected', on ? 'true' : 'false');
            button.tabIndex = on ? 0 : -1;
            if (on && focus) button.focus();
        }
        detailPhotoTab();
    }

    /* Which sub-panel stands, and whether the bar does. With TWO OR MORE
       blocks possible the bar shows, each possible one with its tab, and the
       chosen one stands — or the first possible, when the chosen one is not;
       with one alone the bar goes and that one stands: on a waypoint the
       photos, on a foreign find with sequences but no pictures the sequences.
       The files are a third since 05.10.2026, offered on one's own finds. */
    function detailSubTabsDraw(photosThere, sequencesThere, filesThere = false) {
        const bar = document.getElementById('d-subtabs');
        if (!bar) return;
        const there = { 'd-subtab-photos': photosThere, 'd-subtab-sequences': sequencesThere,
                        'd-subtab-files': filesThere };
        const possible = detailSubTabs.filter(entry => there[entry.tab]).map(entry => entry.tab);
        bar.hidden = possible.length < 2;
        const shown = possible.includes(detailSubTabChosen) ? detailSubTabChosen : (possible[0] ?? null);
        const counts = { 'd-subtab-photos': photoTabCount, 'd-subtab-sequences': host.sequenceTabCount,
                         'd-subtab-files': host.filesTabCount };
        for (const entry of detailSubTabs) {
            const button = document.getElementById(entry.tab);
            if (button) {
                button.hidden = !there[entry.tab];
                button.setAttribute('aria-selected', entry.tab === shown ? 'true' : 'false');
                button.tabIndex = entry.tab === shown ? 0 : -1;
            }
            const panel = document.getElementById(entry.panel);
            if (panel) panel.hidden = entry.tab !== shown;
            const number = document.getElementById(entry.count);
            if (number) {
                number.textContent = String(counts[entry.tab]);
                number.hidden = counts[entry.tab] === 0;
            }
        }
    }

    for (const entry of detailSubTabs) {
        const button = document.getElementById(entry.tab);
        if (!button) continue;
        button.addEventListener('click', () => detailSubTab(entry.tab, false));
        // The same keyboard as the main bar: arrows switch, Home and End jump.
        button.addEventListener('keydown', (e) => {
            // Among the tabs that stand — a hidden one is no stop.
            const tabs = detailSubTabs.filter(x => !document.getElementById(x.tab)?.hidden);
            const here = tabs.findIndex(x => x.tab === entry.tab);
            const last = tabs.length - 1;
            const next = { ArrowRight: (here + 1) % tabs.length,
                           ArrowLeft: (here + last) % tabs.length,
                           Home: 0, End: last }[e.key];
            if (next === undefined || here < 0) return;
            e.preventDefault();
            detailSubTab(tabs[next].tab);
        });
    }

    function detailVisible(on) {
        detail.hidden = !on;
        if (!on) { keyingsShown = null; typeAskedFor = null; }
        // Whoever opens a point wants to see it — see setDetailCollapsed().
        if (on) setDetailCollapsed(false);
        // EVERY POINT STARTS AT THE TEXT — see detailTab(): a remembered
        // choice belongs to a page, not to a window over one thing.
        if (on) detailTab('d-tab-text', false);
        if (on) detailSubTab('d-subtab-photos', false);
        // The right-hand Leaflet controls move aside so they do not vanish
        // under the window.
        document.querySelector('.app')?.classList.toggle('detail-open', on);
        if (!on) moveMode(false);
    }

    // ---------------------------------------------------------------------------
    // Species suggestions
    //
    // Only in lists marked as species lists. Whoever uses the map for lookout
    // points or parking spots sees nothing of this — the theme hangs on the
    // list, not on the application.
    // ---------------------------------------------------------------------------

    const speciesList = document.getElementById('d-species');

    let speciesTimer   = null;

    let speciesRows  = [];

    let speciesCursor = -1;

    let speciesSearch  = '';      // what the current list was searched for

    let speciesTotal = 0;       // how many there would be in total

    let speciesLoading  = false;   // a load-more request currently under way

    let speciesServed   = 0;       // rows the SERVER (or the package) delivered — the paging offset; the familiar rows on top are not among them

    /* A FINGER HAS REACHED INTO THE LIST since it was drawn
       (06.10.2026, in the woods: „ich konnte nichts aus dem Dropdown-Menü
       auswählen … das ist mir schon öfters passiert", when the connection
       wavers). The stored package draws the list at once, the server's answer
       replaced it afterwards — and with a weak signal that answer comes
       seconds later, up to the request's twelve-second limit, exactly while
       one is tapping. A tap is a pointerdown on one row and a pointerup on its
       replacement; the browser fires no click on rows that were swapped in
       between, and nothing was picked. So once the list has been touched or
       scrolled by hand, a late answer leaves it alone (bindSpecies). */
    let speciesTouched  = false;

    speciesList.addEventListener('pointerdown', () => { speciesTouched = true; });

    speciesList.addEventListener('wheel', () => { speciesTouched = true; }, { passive: true });

    /**
     * Which field the suggestions currently hang on.
     *
     * It used to always be the title field, and the name was hard-coded
     * everywhere. Now custom fields can get suggestions too — "Wirtspflanze",
     * "Begleitbäume" —, and a list that can only hang on one field would have
     * needed a copy for each further one.
     *
     * The list itself still exists only once; it moves to the field currently
     * being typed in. The styling carries that, because every .field has
     * position: relative — so the list always hangs on its field and not on
     * the window.
     */
    let speciesTarget = null;
    let speciesGroupsNow = null;   // the groups the field in hand suggests from (bindSpecies)

    /**
     * What separates one name from the next.
     *
     * LINE BREAK, COMMA AND SEMICOLON — not only the break. Whoever fills in
     * "Begleitvegetation" writes the names the way one just lists them:
     *
     *     Fagus sylvatica, Quercus robur
     *
     * If only the break counted, after the first name "Fagus sylvatica, Quer"
     * would stand in the search text, and after that there would never be a hit
     * again — no species of that name exists. Reported exactly like that.
     *
     * A comma occurs in author citations ("Boletus edulis Bull., 1782"). No
     * harm here: the search then simply looks for the year and finds nothing —
     * the typed text stays untouched.
     *
     * THE SAME LIST STANDS IN arten_eintraege() in includes/data.php. It must
     * stand there because the server verifies that a reported name really
     * occurs in the field. If the two drift apart, references silently stop
     * being created — without an error message, since each side does something
     * sensible taken by itself.
     */
    const SPECIES_SEPARATOR = /[\n,;]/;

    /**
     * What is searched for, and where the selection is written to.
     *
     * FOR MULTI-LINE FIELDS THIS IS THE CURRENT ENTRY, not the whole field:
     * from the separator before the caret to the separator after it.
     */
    function speciesRowBounds(el) {
        const value = el.value;
        const pos  = Math.min(el.selectionStart ?? value.length, value.length);

        let lineStart = 0;
        for (let i = pos - 1; i >= 0; i--) {
            if (SPECIES_SEPARATOR.test(value[i])) { lineStart = i + 1; break; }
        }

        let lineEnd = value.length;
        for (let i = pos; i < value.length; i++) {
            if (SPECIES_SEPARATOR.test(value[i])) { lineEnd = i; break; }
        }

        return [lineStart, lineEnd];
    }

    function speciesSearchText() {
        if (!speciesTarget) return '';
        if (speciesTarget.tagName !== 'TEXTAREA') return speciesTarget.value.trim();
        const [from, to] = speciesRowBounds(speciesTarget);
        return speciesTarget.value.slice(from, to).trim();
    }

    function insertSpecies(name) {
        if (!speciesTarget) return;

        if (speciesTarget.tagName !== 'TEXTAREA') {
            speciesTarget.value = name;
            // Only for the title: the heading displays it as well.
            if (speciesTarget === fields.title) fields.heading.textContent = name;
            return;
        }

        const [from, to] = speciesRowBounds(speciesTarget);

        // THE SPACE AFTER THE COMMA MUST STAY. The section begins immediately
        // after the separator, so it contains the whitespace somebody typed.
        // If it were replaced along, the inserted name would stick to the
        // comma: "Fagus sylvatica,Quercus robur".
        const section = speciesTarget.value.slice(from, to);
        const indent  = section.match(/^\s*/)[0];

        speciesTarget.value = speciesTarget.value.slice(0, from) + indent + name + speciesTarget.value.slice(to);
        // Cursor behind the inserted name, so you can keep writing right away
        // or start a new line with the Enter key.
        const after = from + indent.length + name.length;
        speciesTarget.setSelectionRange(after, after);
    }

    /**
     * Does the selected point lie in at least one species list?
     *
     * Like the fields: the flag sits on the main list and holds for everything
     * below. If "Meine Funde" is a species list, "Pilze" and "Röhrlinge" are
     * too — there is nothing to configure there at all.
     */
    function speciesListActive() {
        // An Aufsammlung's title names the sample, never a species.
        if (collectionUi.isCollection()) return false;
        // An editable excursion find counts as lying in a species list — the
        // excursion is one (see foreignFields). Its guest had the suggestions
        // when creating; the leader gets them when correcting.
        const foreign = foreignPoint(state.selectedId);
        if (foreign?.excursion_id != null && foreign.editable) return true;
        return GUEST || [...fields.lists.querySelectorAll('input:checked')]
            .some(b => listById(Number(b.value))?.is_species);
    }

    /** A list by its id — one's own or one shared with one. */
    const listById = (id) => listRoot(id) ?? sharedLists.get(id) ?? null;

    /**
     * THE GROUPS THE TITLE SUGGESTS FROM (12.11.2026, migration
     * 0195): what the species lists the find is ticked into hold — in the
     * fungi list fungi, in the lichen list lichens, in both both. A list
     * that never chose reads as fungi (listGroups), as the old device
     * setting did; so do a guest's finds and an excursion's, which lie in
     * no list of the guest's.
     */
    function titleGroups() {
        const foreign = foreignPoint(state.selectedId);
        if (GUEST || (foreign?.excursion_id != null && foreign.editable)) return ['fungi'];
        const out = new Set();
        for (const b of fields.lists.querySelectorAll('input:checked')) {
            const list = listById(Number(b.value));
            if (list?.is_species) for (const g of listGroups(list)) out.add(g);
        }
        return [...out];
    }

    /**
     * THE TITLE IS ALWAYS „ART" (12.11.2026: „das Titelfeld beim
     * Fund, immer ‚Art‘ … YASAIMAPP ist mittlerweile so spezialisiert darauf,
     * Artenlisten zu führen, dass wir nicht mehr auf … Parkplatzlisten,
     * Campingplatzlisten Rücksicht nehmen"). Until then „Art" only while the
     * find lay in a species list, „Titel" elsewhere. An Aufsammlung's keeps
     * „Bezeichnung": it names a sample, never a species. Whether names are
     * suggested still follows the lists (speciesListActive, titleGroups).
     */
    function titleLabel() {
        const label = document.querySelector('label[for="d-title"]');
        if (collectionUi.isCollection()) {
            if (label) label.textContent = t('Bezeichnung');
            fields.title.placeholder = t('zum Beispiel Rehlosung am Waldrand');
            return;
        }
        if (label) label.textContent = t('Art');
        fields.title.placeholder = t('Gefundene Art');
    }

    function closeSpecies() {
        speciesList.hidden = true;
        speciesList.innerHTML = '';
        speciesRows = [];
        speciesCursor = -1;
        speciesSearch = '';
        speciesTotal = 0;
        speciesTarget?.setAttribute('aria-expanded', 'false');
    }

    /**
     * Draw suggestions — either from scratch or appended at the bottom.
     *
     * Appending happens when scrolling on: a genus like Cortinarius has 1,399
     * names, which can neither be sent all at once nor cut off at fifty while
     * pretending that is all.
     */
    /**
     * The species the user has mapped before that fit what is typed — ranked
     * for the top of the suggestion list. THE EXPERIMENT OF 19.09.2026
     * („Arten, die auf der Tour schon mal vorkamen, ganz oben;
     * Arten, die in dem Monat über alle Jahre hinweg gefunden wurden, als
     * zweite Prioritätsstufe; großflächig das Gebiet, 100 km, weil man in
     * den Alpen andere Pilze findet; und nach Häufigkeit — statt eines
     * Monats 30 Tage minus und 30 Tage plus"), behind the switch in
     * Einstellungen → Arten.
     *
     * FOUR WITNESSES, weighed in that order: the running tour (a thousand),
     * the season — within thirty days of the find's day, in any year (a
     * hundred), the region — within twenty kilometres of the find (ten;
     * the maintainer first said a hundred, then „20 Kilometer, ein 40-Kilometer-
     * Durchmesser" the same evening — a hundred reached from Thuringia to
     * Berlin),
     * and how often the species was mapped at all (up to nine). The weights
     * are decades apart so the order never mixes: every species of the tour
     * stands above every species of the season, and so on; the count only
     * orders within a stage. The FIND\'S OWN day and place are the reference,
     * not today\'s and here — whoever enters last week\'s alpine find gets the
     * alpine August, not this town\'s September.
     *
     * HOW OFTEN WEIGHS MORE SINCE 15.11.2026 („Ich habe viele
     * Funde von dem falschen Pfifferling … und nur ein paar Funde von
     * Hygrocybe aurantiosplendens. Aber aurantiosplendens wird mir immer
     * oberhalb … angezeigt. Können wir das ändern, dass hier die Anzahl
     * zählt"). Season and region were stages of their own, and the count —
     * capped at nine — only ordered within a stage: three finds close by
     * beat forty a little further off. Now the tour stays on top (what one
     * entered today one often wants again), and below it the FINDS are
     * counted, each weighed by its witnesses: one in the season counts
     * twice, one in the region twice, one in both four times. So the
     * species found forty times stands above the one found three times,
     * and a spring species in autumn still does not rise just by being
     * common. No cap. The decades above are the old rule, for the record.
     *
     * READ OFF THE TITLES, not off the references: a title „Boletus edulis"
     * names a species whether or not the server has resolved it, and the
     * open marks (sp., cf. …) name none — save „agg." and „s. str.", which
     * are offered as written, ahead of the lists (familiarName, 26.09.2026).
     * THE SHARED FINDS COUNT TOO
     * (19.09.2026: „du schlägst auch Arten vor, die andere Leute
     * gefunden haben — das ist okay so"): what a friend mapped on the same
     * ground in the same season is as good a hint as one's own. Eight at
     * most: a block, not a second list.
     *
     * EVERY LIST COUNTS, NOT ONLY THE SPECIES LISTS — deliberately
     * (19.09.2026, on Boletus pinicola from his wish list: „da hatte ich
     * vermerkt, wo er vorkam — so lassen, das ist gewollt"). A wish-list note
     * of where a species occurs is exactly the kind of hint that should
     * surface near that spot; pointIsFind() would have filtered it out.
     */
    function familiarSpecies(text) {
        const words = text.trim().toLowerCase().split(/\s+/).filter(Boolean);
        if (words.length === 0) return [];
        const fits = (name) => {
            const parts = name.toLowerCase().split(' ');
            // „bol edu" reads word by word; a single word may also start the epithet.
            return words.every((w, i) => (parts[i] ?? '').startsWith(w))
                || (words.length === 1 && parts.some(p => p.startsWith(words[0])));
        };
        const dayOfYear = (day) => {
            const [y, m, d] = day.split('-').map(Number);
            return Math.round((Date.UTC(y, m - 1, d) - Date.UTC(y, 0, 1)) / 86400000);
        };
        const apart = (a, b) => Math.min(Math.abs(a - b), 365 - Math.abs(a - b));

        const active = tour.activeTour();
        const today = tour.today();
        const refDay = /^\d{4}-\d{2}-\d{2}/.test(fields.found.value) ? fields.found.value.slice(0, 10) : today;
        const refDoy = dayOfYear(refDay);
        const centre = map.getCenter();
        const here = readCoordinates(fields.coord.value) ?? [centre.lat, centre.lng];

        const seen = new Map();
        /* ONLY FINDS OF THE FIELD'S GROUPS (12.11.2026): a fungus mapped
           last week is no companion plant. A find counts where one of its
           lists holds a group the field asks for — a list that never chose
           reads as fungi, as everywhere (listGroups), so the wish list of
           19.09.2026 below still speaks in the fungi's title. */
        const wanted = new Set(speciesGroupsNow ?? []);
        const ofGroups = (p) => wanted.size === 0 || (p.lists ?? [])
            .some(id => listGroups(listById(id)).some(g => wanted.has(g)));
        for (const p of [...state.points.values(), ...state.sharedPoints.values()]) {
            if (p.id === state.selectedId) continue;   // the find in the window does not vouch for itself
            if (!ofGroups(p)) continue;
            // „… agg." and „… s. str." stay what the title says (familiarName).
            const name = familiarName(p.title);
            if (name === null || !fits(name)) continue;
            const e = seen.get(name) ?? { name, count: 0, weight: 0, tour: false, season: false, region: false };
            e.count++;
            const day = timeRange.dayOf(p);
            const onTour = active
                ? (active.id !== null && String(p.tour_id ?? '') === String(active.id))
                    || ((p.tour ?? '').trim() === active.name && day !== null && day >= active.day)
                : day === today;
            if (onTour) e.tour = true;
            const inSeason = day !== null && apart(dayOfYear(day), refDoy) <= 30;
            const inRegion = Number.isFinite(p.lat) && Number.isFinite(p.lon)
                && polygon.distanceM([p.lat, p.lon], here) <= 20000;
            if (inSeason) e.season = true;
            if (inRegion) e.region = true;
            // This find's weight: twice in the season, twice in the region (above).
            e.weight += (inSeason ? 2 : 1) * (inRegion ? 2 : 1);
            seen.set(name, e);
        }
        return [...seen.values()]
            .sort((a, b) => Number(b.tour) - Number(a.tour) || b.weight - a.weight
                || b.count - a.count || a.name.localeCompare(b.name))
            .slice(0, 8)
            .map(e => ({
                name: e.name,
                // The reason as facts, not as a sentence — fillSpeciesRow draws
                // them as icons with the full words in their tooltips.
                why: { tour: e.tour, season: e.season, region: e.region, count: e.count },
            }));
    }

    /**
     * The working names that fit what is typed — species no list knows yet,
     * one's own and those friends share (working_names.php). They come from
     * the start data, so they are there offline as well, and only for the
     * title: a working name names a find, not its host.
     *
     * AHEAD OF EVERYTHING, even the familiar ones: whoever coined „Inocybe
     * 'violacea' ad int." and types „ino vio" means that one, and no list
     * would bring it anyway.
     */
    function workingSpecies(text) {
        if (speciesTarget !== fields.title) return [];
        const words = text.trim().toLowerCase().split(/\s+/).filter(Boolean);
        if (words.length === 0) return [];
        return (start.workingNames ?? [])
            .filter(w => {
                const parts = w.name.toLowerCase().replace(/['"‚‘’“”]/g, '').split(' ');
                return words.every((word, i) => (parts[i] ?? '').startsWith(word.replace(/['"‚‘’“”]/g, '')))
                    || (words.length === 1 && parts.some(p => p.startsWith(words[0])));
            })
            .map(w => ({
                name: w.name,
                german: w.owner ? t('Arbeitsname von %s', w.owner) : t('Arbeitsname'),
                working: true,
            }));
    }

    /** Is this title entry one of the working names? Then it is a species, whatever its words. */
    function isWorkingName(entry) {
        const wanted = entry.trim().toLowerCase();
        return (start.workingNames ?? []).some(w => w.name.toLowerCase() === wanted);
    }

    function drawSpecies(hits, total, append = false) {
        /* THE SERVER'S COUNT, KEPT APART from the rows on the screen: the
           familiar rows below sit on top of the list without coming from the
           server, and a duplicate they displace is still a row the server
           delivered — the next page starts where the server stopped, not where
           the list happens to end. */
        const served = hits.length;
        if (!append) {
            speciesRows = [];
            speciesList.innerHTML = '';
            speciesCursor = -1;
            speciesServed = 0;
            speciesTouched = false;
            /* THE FAMILIAR ONES FIRST (19.09.2026, begun as an
               experiment behind a switch; part of the program since 21.09.2026): what the user has mapped before, ranked by
               tour, season, region and count — see familiarSpecies(). Only on
               a fresh list, never on a further page; a name the server brings
               as well stands once, at the top. */
            if (familiarFirst()) {
                const own = familiarSpecies(speciesSearchText());
                if (own.length > 0) {
                    const names = new Set(own.map(s => s.name));
                    // A name the reference brings as well keeps the reference's
                    // row — author, German name, current name — plus the reason.
                    const dressed = own.map(o => {
                        const h = hits.find(x => x.name === o.name);
                        return h ? { ...h, why: o.why } : o;
                    });
                    hits = [...dressed, ...hits.filter(h => !names.has(h.name))];
                }
            }
            const working = workingSpecies(speciesSearchText());
            if (working.length > 0) {
                const names = new Set(working.map(w => w.name));
                hits = [...working, ...hits.filter(h => !names.has(h.name))];
            }
        }
        speciesServed += served;
        speciesTotal = total;
        removeSpeciesFooter();

        if (hits.length === 0 && speciesRows.length === 0) { closeSpecies(); return; }

        const offset = speciesRows.length;
        speciesRows.push(...hits);

        for (const [j, species] of hits.entries()) {
            const i = offset + j;
            const li = document.createElement('li');
            li.dataset.index = String(i);
            fillSpeciesRow(li, species, i);
            speciesList.append(li);
        }

        drawSpeciesFooter();
        speciesList.hidden = false;
        fields.title.setAttribute('aria-expanded', 'true');
        if (!append) enrichFamiliar(hits);
    }

    /**
     * One row of the list — built here so a familiar row can be filled again
     * once its reference data arrives (enrichFamiliar). onclick and not a
     * listener for the same reason: a second fill must not add a second
     * handler.
     */
    function fillSpeciesRow(li, species, i) {
        li.textContent = '';
        li.classList.toggle('is-familiar', !!species.why);

            /* THE SUBJECT AS A SHAPE, far left. The same drawings a list can
               carry as its marker and that stand before the switches in the
               species tab — no second set of pictures, and the mapping arrives
               ready-made from the server (species.php) so no second
               subject → shape table comes into being here.

               WITH A LABEL, not only as a picture: „Vögel" hangs on it as
               title and as aria-label. An outline alone is a riddle to whoever
               sees it for the first time — and nothing at all to a screen
               reader, for the SVG itself is aria-hidden. */
            /* THE SIGN ON EVERY ROW (21.09.2026: „Symbole brauche
               ich da eigentlich nicht — beziehungsweise wenn, dann überall").
               The server's answer carries form and group; a hit out of the
               stored package carries only its source, and the subjects' table
               in the start data (groupShapes) turns that into the same sign
               and the same label. */
            /* WHICH PACKAGE A HIT CAME FROM: the identifier's prefix („fungi:7032",
               „gbif:…") names it; a hit out of the stored package carries no
               identifier and names it as source. The server's `source` is the
               TABLE („dgfm"), not the package — the old „Taxref:" branch tested
               source === 'fungi' and therefore never fired for a server hit,
               which is the inconsistency the maintainer saw on 21.09.2026. */
            const pkg = String(species.identifier ?? '').split(':')[0] || species.source || '';
            const subject = start.groupShapes?.[groupOf(pkg)] ?? null;
            const form = species.form ?? subject?.form ?? null;
            const group = species.group ?? subject?.name ?? null;
            let shape = null;
            if (form) {
                shape = document.createElement('span');
                shape.className = 'species-shape';
                shape.innerHTML = shapePoint('currentColor', form);
                if (group) {
                    shape.title = group;
                    shape.setAttribute('aria-label', group);
                }
            }

            const text = document.createElement('div');
            text.className = 'species-text';

            const name = document.createElement('span');
            name.className = 'species-name';
            name.textContent = species.name;
            /* THE SIGN IN THE NAME LINE, not as a column beside the whole row
               (21.09.2026: „das Symbol nur vor dem wissenschaftlichen
               Namen, der Text darunter kann wieder nach vorne springen") — the
               German name and the reason line start at the left edge again,
               and the narrow phone list gains the sign's width for them. */
            if (shape) name.prepend(shape);

            if (species.author) {
                const author = document.createElement('span');
                author.className = 'species-author';
                author.textContent = ' ' + species.author;
                name.append(author);
            }
            // Red List classification: brief, but it has a say in whether the
            // find may be touched at all.
            if (species.red_list) {
                const rl = document.createElement('span');
                rl.className = 'species-rl';
                rl.textContent = t('RL %s', species.red_list);
                name.append(rl);
            }
            text.append(name);

            /* The German name small below — and, where the hit came through an
               old name, WHAT THE SHOWN NAME IS: the current one for what was
               typed.
     
               It used to say "Synonym: Agaricus silvaticus", which states the
               relation from the wrong end. The reader typed that name; they do
               not need to be told it is a synonym, they need to be told that
               the line above is what it is called today. The wording of
               19.08.2026: "dann muss halt dabeistehen, dass das der aktuelle
               Name ist, von meinem Suchnamen".

               ONE SENTENCE FOR EVERY LIST. From 14.09.2026 the fungi said
               „Taxref: …" instead — the shown name follows GBIF (0106), so
               nearly every DGfM row carries its own Taxref spelling as via,
               and a label seemed enough where a sentence stood under every
               second suggestion. It lasted a week: two rows side by side, one
               saying „Taxref: Russula atropurpurella", the other „aktueller
               Name zu „Russula xerampelina"", and nobody could tell what the
               difference meant (21.09.2026: „wann wird denn Taxref
               angezeigt, und wann aktueller Name zu? Machen wir das Taxref
               doch wieder rückgängig"). The source of a via is nothing the
               reader is asking about; what the line above is called today is. */
            const below = [
                species.german,
                species.via ? t('aktueller Name zu „%s“', species.via) : null,
            ].filter(Boolean);
            if (below.length > 0) {
                const row = document.createElement('span');
                row.className = 'species-german';
                // The dot between the two as an element of its own, so it can
                // be heavy (21.09.2026: „so richtig fett") — a text
                // node cannot be bolder than its line.
                below.forEach((piece, n) => {
                    if (n > 0) {
                        const dot = document.createElement('span');
                        dot.className = 'species-dot';
                        dot.textContent = '·';
                        row.append(dot);
                    }
                    row.append(document.createTextNode(piece));
                });
                /* THE STAR BEHIND THE CURRENT NAME (21.09.2026: „einen
                   großen Stern, bei dem man mit Hover sieht, aus welcher Liste —
                   und das Datum; einfach den Stand der Datenbank"): the list
                   the hit came out of, in the words Einstellungen → Arten
                   uses for it, with the date where the list has one. */
                const origin = species.via ? (start.speciesOrigins?.[pkg] ?? null) : null;
                if (origin) {
                    const star = document.createElement('span');
                    star.className = 'species-origin';
                    star.textContent = '★';
                    star.title = t('Aktueller Name nach: %s', origin);
                    star.setAttribute('aria-label', star.title);
                    // A finger has no hover: a tap on the star says the same in
                    // the map's message bar — and does not pick the row.
                    star.addEventListener('click', (e) => { e.stopPropagation(); status(star.title); });
                    row.append(star);
                }
                text.append(row);
            }
            /* THE REASON LAST, in one short line (21.09.2026: „der
               deutsche Name direkt unter dem wissenschaftlichen, der Autor
               dahinter, und die Trefferquote — warum, was, wo — in die letzte
               Zeile, so gekürzt, dass sie auf dem Handy in eine Zeile passt"). */
            if (species.why) {
                /* AS ICONS (21.09.2026: „die Grundzeile mit SVG-Icons,
                   um sie zu verkürzen — und beim Drüberfahren einen ausführlichen
                   Text"): the hiker for the tour, the calendar for the season,
                   the place mark for the surroundings, the points sign with the
                   number — every one from the icon library, the sentence in its
                   tooltip. Four signs read in a glance where four words wrapped. */
                const row = document.createElement('span');
                row.className = 'species-german species-why';
                const mark = (name, words, extra = '') => {
                    const m = document.createElement('span');
                    m.className = 'species-why-mark';
                    m.title = words;
                    m.setAttribute('aria-label', words);
                    // Tappable for the same reason as the star below.
                    m.addEventListener('click', (e) => { e.stopPropagation(); status(words); });
                    m.append(icon(name));
                    if (extra !== '') m.append(document.createTextNode(extra));
                    row.append(m);
                };
                const w = species.why;
                // A word or a number beside each sign (same day: „das
                // Symbol mit Text markieren — das Ortssymbol und dazu 20 km"):
                // still one line, and the sign no longer has to be guessed.
                if (w.tour) mark('hiker', t('Auf der laufenden Tour schon gefunden'), t('Tour'));
                if (w.season) mark('calendar', t('Um diese Jahreszeit gefunden — 30 Tage davor und danach, in irgendeinem Jahr'), t('±30 Tage'));
                if (w.region) mark('place', t('Im Umkreis von 20 km gefunden'), '20 km');
                mark('points', w.count === 1 ? t('Bisher 1 Fund dieser Art') : t('Bisher %d Funde dieser Art', w.count),
                    w.count === 1 ? t('1 Fund') : t('%d Funde', w.count));
                text.append(row);
            }
            li.append(text);

            // Perfectly ordinary click. The route via pointerdown was wrong
            // twice: first the selection hung on mousedown, which a finger does
            // not trigger at all; then on pointerdown with preventDefault — and
            // that also takes the swipe gesture away from the browser, so the
            // list could no longer be scrolled and every touch selected
            // immediately.
            //
            // click is exactly right: it comes after release, and after a swipe
            // it does not come at all. That it only arrives after the field's
            // blur is no longer a problem — the list no longer closes on blur
            // but only on a touch outside.
            li.onclick = () => applySpecies(i);
    }

    /* What the reference knows about a familiar name, once looked up — the
       same name is typed towards again and again in one session. */
    const familiarInfo = new Map();

    /**
     * Dress the familiar rows with the reference's data — author, German
     * name, the current name where the mapped one is a synonym — so they
     * read like every other row (21.09.2026: „bei den Vorschlägen
     * fehlt mir der Autor, der deutsche Name, und wie der Name aktuell ist").
     * The rows appear at once with the reason alone and are filled in as the
     * answers come: the stored package answers instantly, the server for
     * what the package does not hold. Rows the reference itself brought on
     * this page were dressed at the prepend already and are passed over.
     */
    async function enrichFamiliar(hits) {
        const search = speciesSearch;
        for (const row of hits.filter(h => h.why && !h.author && !h.german && !h.source)) {
            let info = familiarInfo.get(row.name) ?? null;
            if (!info) {
                const same = (h) => h.name.toLowerCase() === row.name.toLowerCase();
                const asSynonym = (h) => (h.via ?? '').toLowerCase() === row.name.toLowerCase();
                const local = searchSpeciesLocally(row.name, 0, speciesGroupsNow);
                info = local?.hits?.find(same) ?? local?.hits?.find(asSynonym) ?? null;
                if (!info) {
                    try {
                        const answer = await api.get('species', { q: row.name, ...speciesGroupParam() });
                        info = answer.hits?.find(same) ?? answer.hits?.find(asSynonym) ?? null;
                    } catch { info = null; }
                }
                if (info) familiarInfo.set(row.name, info);
            }
            if (!info) continue;
            // Typed on in the meantime? Then this list is gone already.
            if (speciesSearch !== search) return;
            const i = speciesRows.indexOf(row);
            if (i < 0) continue;
            const dressed = { ...info, why: row.why };
            speciesRows[i] = dressed;
            const li = speciesList.querySelector(`li[data-index="${i}"]`);
            if (li) fillSpeciesRow(li, dressed, i);
        }
    }

    /** The row at the very bottom that says how much is still to come. */
    function removeSpeciesFooter() {
        speciesList.querySelector('.species-footer')?.remove();
    }

    function drawSpeciesFooter() {
        if (speciesServed >= speciesTotal) return;
        const li = document.createElement('li');
        li.className = 'species-footer';
        li.textContent = t('%d von %d — weiter rollen für mehr', speciesServed, speciesTotal);
        speciesList.append(li);
    }

    /** Fetch the next page when scrolling to the end. */
    speciesList.addEventListener('scroll', () => {
        if (speciesLoading || speciesServed >= speciesTotal) return;
        if (speciesList.scrollTop + speciesList.clientHeight < speciesList.scrollHeight - 60) return;

        const search = speciesSearch;
        const offset = speciesServed;

        // If the list sits in the browser, the next page is there instantly.
        const local = searchSpeciesLocally(search, offset, speciesGroupsNow);
        if (local && local.hits.length > 0) {
            drawSpecies(local.hits, local.total, true);
            return;
        }

        speciesLoading = true;
        api.get('species', { q: search, offset, ...speciesGroupParam() })
            .then((response) => {
                // Typed on in the meantime? Then the answer belongs to a search
                // that no longer exists.
                if (speciesSearch === search && speciesServed === offset) {
                    drawSpecies(response.hits, response.total, true);
                }
            })
            .catch(() => { /* then just no further */ })
            .finally(() => { speciesLoading = false; });
    });

    function applySpecies(index) {
        const species = speciesRows[index];
        if (!species) return;
        const target = speciesTarget;

        // ONE SOURCE PER NAME, not per field. A multi-line field holds several
        // species — "Begleitvegetation" exists exactly for that —, and each of
        // them should get its own reference. Single-line fields go the same way
        // with exactly one entry; a second form just for them would mean every
        // place that reads this must keep the two apart.
        //
        // THE TITLE DELIBERATELY RECORDS NOTHING here (it has no data-field):
        // its reference is the server's business — title_references_sync in
        // includes/point_species.php resolves it on every save, which also
        // covers titles that were typed out by hand or moved into a species
        // list without this code ever running.
        if (target?.dataset.field && species.source) {
            (host.speciesSources[target.dataset.field] ??= {})[species.name] = species.source;
        }
        insertSpecies(species.name);

        /* PICKING A NAME INTO THE TITLE IS AN ACT OF IDENTIFICATION
           (23.08.2026): whoever chooses from the list has just
           identified the find — so the Bestimmer field gets their name
           (only if it is still empty: a recorded third-party determination
           must not be silently reclaimed) and the certainty jumps to
           'makroskopisch sicher' (always: a new pick is a fresh macroscopic
           call, and whoever has been at the microscope sets that afterwards).
           Both remain ordinary fields — overwrite at will.

           ONLY THE TITLE. A pick into "Begleitvegetation" or "Substrat"
           names the neighbour, not the find — it must not declare anything
           identified (the same boundary as in identification.js). */
        if (target === fields.title) {
            const determiner = extraField('Bestimmer');
            if (determiner && determiner.value.trim() === '' && start.user?.finder) {
                determiner.value = start.user.finder;
            }
            const certainty = extraField(CERTAINTY_FIELD);
            if (certainty) certainty.value = 'makroskopisch sicher';
        }

        closeSpecies();
        target?.focus();
        markUnsaved(true);
        /* A PICK IS A FINISHED CHOICE — the Fundbrowser's autosave holds
           back while a species field has the focus (cockpit.js says why) and
           saves the pick at once through this (13.11.2026). */
        host.speciesPicked?.();
    }

    /* THE FIND FIELDS STAND IN TWO PLACES since 09.11.2026 („Beleg
       aufbewahrt bitte mal unterhalb Fundnummer und über Fundzeit"): most in
       their block, a field marked 'after' => 'number' under the find number.
       Everything that looks for them looks in both — through these two. */
    const extraHomes = () => [fields.extra, fields.extraAfter].filter(Boolean);
    const extraAll = (selector) => extraHomes().flatMap(home => [...home.querySelectorAll(selector)]);

    /** A find field's input in the open window — or null when it is not there. */
    function extraField(name) {
        return extraAll(`[data-field="${CSS.escape(name)}"]`)[0] ?? null;
    }

    function setSpeciesCursor(direction) {
        if (speciesRows.length === 0) return;
        speciesCursor = (speciesCursor + direction + speciesRows.length) % speciesRows.length;
        for (const li of speciesList.children) {
            li.classList.toggle('is-active', Number(li.dataset.index) === speciesCursor);
        }
        speciesList.children[speciesCursor]?.scrollIntoView({ block: 'nearest' });
    }

    /**
     * Typing: locally first, then from the server.
     *
     * The stored packages answer without delay and without network — depending
     * on the choice in the administration the fungi (23,440 valid names
     * including synonyms), the lichens (2,409) or both.
     *
     * The server is still asked afterwards, namely because of GBIF: 397,000
     * names stand there, among them newly described species that will not
     * appear in the German list for a long while. Whoever finds such a thing
     * should get the name suggested. The server answer replaces the local one
     * as soon as it arrives — unless scrolling has continued in the meantime,
     * then the jump would be incomprehensible.
     */
    /**
     * Attach a field to the suggestions.
     *
     * @param {HTMLElement} el      Input field or textarea
     * @param {() => boolean} aktiv Whether suggesting should happen right now
     * @param {() => string[]} groups The species groups this field suggests from (12.11.2026)
     */
    function bindSpecies(el, active, groups) {
        el.setAttribute('autocomplete', 'off');
        el.setAttribute('role', 'combobox');
        el.setAttribute('aria-autocomplete', 'list');
        el.setAttribute('aria-expanded', 'false');

        const apply = () => {
            // The field's own groups, asked anew each time: the lists ticked may have changed.
            speciesGroupsNow = groups();
            if (speciesTarget === el) return;
            closeSpecies();
            speciesTarget = el;
            // The one list moves over to this field.
            (el.closest('.field') ?? el.parentElement)?.append(speciesList);
        };

        el.addEventListener('focus', () => {
            if (!active() || groups().length === 0) return;
            apply();
            getSpeciesList();
        });

        el.addEventListener('input', () => {
            if (!active() || groups().length === 0) { closeSpecies(); return; }
            apply();

            // Trigger from here too, not only on focus: that one does not fire
            // in every situation, and a trigger that sometimes stays away is
            // none. The call is guarded against multiple starts.
            getSpeciesList();

            const text = speciesSearchText();
            clearTimeout(speciesTimer);

            if (text.length < 3) { closeSpecies(); return; }

            const local = searchSpeciesLocally(text, 0, speciesGroupsNow);
            if (local && local.hits.length > 0) {
                speciesSearch = text;
                drawSpecies(local.hits, local.total);
            }

            speciesTimer = setTimeout(async () => {
                try {
                    const response = await api.get('species', { q: text, ...speciesGroupParam() });
                    // Typed or scrolled on in the meantime? Then do not barge
                    // in anymore.
                    if (speciesTarget !== el || speciesSearchText() !== text) return;
                    if (speciesSearch === text && speciesServed > 50) return;
                    // NOT UNDER THE FINGER: the list stands already and somebody
                    // is reaching into it (speciesTouched says why).
                    if (local && local.hits.length > 0 && speciesTouched) return;
                    // And not for nothing: the same names in the same order as
                    // the package's would only make the list flicker.
                    if (local && local.total === response.total
                        && local.hits.length === response.hits.length
                        && local.hits.every((h, i) => h.name === response.hits[i]?.name)) return;

                    speciesSearch = text;
                    drawSpecies(response.hits, response.total);
                } catch {
                    // No network. The local suggestions already stand there;
                    // only if they too yielded nothing is there really nothing
                    // to show.
                    if (!local || local.hits.length === 0) closeSpecies();
                }
            }, 180);
        });

        el.addEventListener('keydown', (e) => {
            if (speciesList.hidden || speciesTarget !== el) return;
            if (e.key === 'ArrowDown')      { e.preventDefault(); setSpeciesCursor(1); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); setSpeciesCursor(-1); }
            else if (e.key === 'Enter' && speciesCursor >= 0) { e.preventDefault(); applySpecies(speciesCursor); }
            else if (e.key === 'Escape')    { closeSpecies(); }
        });
    }

    /**
     * Lay out the packages as soon as they could be needed.
     *
     * Not on page load: whoever uses the map for parking spots should not
     * download name lists. Only when somebody in a species list enters the
     * title field is it worth it — and then they stand ready by the second
     * word.
     *
     * Refreshing what is already there runs separately from this on page load
     * — see below at cleanUpAreasOnStart().
     */
    function getSpeciesList() {
        // No gate: whether suggesting happens at all is by now decided by the
        // field itself. And only what already lies on the device is fetched
        // anyway — since the package administration nothing comes by itself
        // anymore.
        loadSpeciesList();
    }

    // The title gets suggestions when the list is marked as a species list.
    // The custom fields attach themselves when they are drawn — see
    // drawExtraFields().
    bindSpecies(fields.title, speciesListActive, titleGroups);

    /* AN OPEN MARK IN THE TITLE PULLS THE CERTAINTY ALONG
       (23.08.2026): whoever picks a name and then writes a cf. or s. l. next
       to it has just declared the identification shaky — 'unsicher'. A bare
       sp. says less than that: genus yes, species no — that is 'unbestimmt',
       the same word the fresh point starts with.

       ON 'change', NOT ON 'input': while "splendens" is being typed the
       title passes through "sp", and the half-typed word must not flip the
       field. The click on Speichern blurs the title first, so the rule also
       fires when nobody tabs out of the field.

       REMOVING a mark moves nothing back — the usual next step is a fresh
       pick from the list, and that sets 'makroskopisch sicher' itself. */
    fields.title.addEventListener('change', () => {
        const certainty = extraField(CERTAINTY_FIELD);
        if (!certainty) return;
        /* A WORKING NAME IS A SPECIES, whatever its words: „Inocybe sp.
           nov. 1" carries „sp" and is no open determination for that. Its
           entries are left out before the marks are read. */
        const marks = openMarksIn(fields.title.value.split(/[\n,;]/)
            .filter(entry => !isWorkingName(entry)).join(', '));
        const should = marks.some((m) => m !== 'sp') ? 'unsicher'
                     : (marks.includes('sp') ? 'unbestimmt' : null);
        if (should !== null && certainty.value !== should) {
            certainty.value = should;
            markUnsaved(true);
        }
    });

    /**
     * Closing happens on a touch outside — not on blur.
     *
     * Closing on blur was the reason for the whole contortion with mousedown
     * and pointerdown: whoever reaches into the list loses the caret in the
     * field, and the list would be gone before the finger has selected
     * anything. The same holds for scrolling. This way around you may scroll,
     * read and then select in peace.
     */
    document.addEventListener('pointerdown', (e) => {
        if (speciesList.hidden) return;
        if (e.target === speciesTarget || speciesList.contains(e.target)) return;
        closeSpecies();
    });

    /**
     * Close the window after saving, but only on the phone.
     *
     * On the desktop it stands next to the map and may stay open — you see the
     * result right beside it. On the phone it covers everything: whoever has
     * saved wants to see where the point now lies.
     *
     * And via clearSelection(), not just by folding it shut. If the point
     * stayed selected, its marker would still be draggable — an accidental drag
     * would write the coordinate into the hidden field and mark it as unsaved,
     * without any of it being visible. Exactly the kind of silent state that
     * later comes back as "the point has wandered".
     */
    function closeDetailAfterSave() {
        if (isPhone()) clearSelection();
    }

    /**
     * The point in the window has received a new id.
     *
     * Without this the protection would fail exactly where it is needed: a
     * point from a dead zone first carries a provisional id and gets the real
     * one when handed in later. If the window knew nothing of that, it would
     * take the point for a different one — and a different point is always
     * filled.
     */
    function swapDetailKey(old, newKey) {
        if (host.lastFilled.id === old) host.lastFilled.id = newKey;
    }

    /** The editable state of the window, in comparable form. */
    function detailState() {
        return JSON.stringify({
            title: fields.title.value,
            note: fields.note.value,
            coord: fields.coord.value,
            found: fields.found.value,
            number: fields.number.value,
            lists: [...fields.lists.querySelectorAll('input:checked')].map(b => Number(b.value)).sort(),
            extra: collectExtraFields(),
            culture: collectionUi.state(),
        });
    }

    /**
     * Fill the window with a point.
     *
     * WHY THIS DOES NOT ALWAYS OVERWRITE EVERYTHING
     * This function is also called with the window OPEN — after the queue has
     * run through and the points came fresh from the server. That happens on
     * its own beat: every half to full minute, as soon as the network returns,
     * when the page is brought back to the foreground.
     *
     * Whoever was typing a title in that moment lost it: the server still knows
     * the point without a title, and exactly that was written into the field.
     * The conditions for this are the normal case in the woods — collected
     * offline, reception returns in the clearing, labeling happens meanwhile.
     *
     * Therefore: if the fields still hold what was last written in here,
     * filling happens. If it deviates, somebody has typed, and then the window
     * stays as it is — except for the details that cannot be edited anyway.
     * Height and timestamp may grow in, the input must not.
     *
     * For a DIFFERENT point filling always happens; otherwise the protection
     * would strike as soon as you switch from a labeled point to another.
     */
    function fillDetail(point, force = false, keepFields = false) {
        const same  = !force && host.lastFilled.id === point.id && host.lastFilled.state !== null;
        // keepFields: the autosave's answer — the typed fields are never written back (saveDetail says why).
        const touched  = (keepFields && same) || (same && detailState() !== host.lastFilled.state);

        // Read-only details — those stand further down and are always set.
        if (touched) {
            fillDetailUneditable(point);
            /* THE MINTED NUMBER COMES IN ALL THE SAME (28.09.2026:
               the finds of the excursion had no find number). The server mints
               it inside the create, and the create's answer is where the device
               first learns it (swapPointKey) — in the woods that answer arrives
               while the title is being typed. The protection above then kept the
               WHOLE window, the untouched number field included; the save
               compared the empty field with the minted number and sent „no
               number". Measured in the live data: 70 finds lost theirs between
               26.09.2026 (df3b789 put the field into the window) and 28.09.2026,
               each one seconds after it was created. The field is therefore
               filled like a read-only detail as long as it still holds what was
               last written into it — only a number typed by hand is input to
               protect, and lastFilled learns the new value so it does not count
               as one. */
            const shown = JSON.parse(host.lastFilled.state);
            const minted = point.find_number ?? '';
            if (fields.number.value === shown.number && minted !== shown.number) {
                fields.number.value = minted;
                host.lastFilled.state = JSON.stringify({ ...shown, number: minted });
            }
            fields.number.placeholder = point.id > 0 ? findNumberFallback(point.id) : '';
            drawNumberHint(point);
            return;
        }

        fields.heading.textContent = point.title || t('Ohne Titel');
        fields.title.value    = point.title || '';
        fields.note.value    = point.note || '';
        fields.coord.value    = `${point.lat.toFixed(6)}, ${point.lon.toFixed(6)}`;
        fields.coord.readOnly = true;   // opened by the pencil for one entry at most
        fields.found.value = forField(point.found_at);
        // Before the fields are drawn: an Aufsammlung's title and fields differ.
        collectionUi.fill(point);

        fillDetailUneditable(point);

        const foreign = foreignPoint(point.id);
        /* THE NUMBER FIELD ONLY ON ONE'S OWN FINDS (index.php says why). The
           placeholder is the mark the find wears without a number of its own
           — a provisional point has no id for it yet. */
        // An Aufsammlung draws no find number — it has its own (0174).
        fields.numberField.hidden = foreign != null || !pointIsFind(point) || !!point.is_collection;
        fields.number.value = point.find_number ?? '';
        fields.number.placeholder = point.id > 0 ? findNumberFallback(point.id) : '';
        drawNumberHint(point);
        if (foreign && foreign.co_owned) {
            /* MY FILING OF A CO-OWNED FIND (11.09.2026, „jeder mit seiner
               eigenen Ablage"): the tree shows my lists, and a checkmark
               saves at once — there is no save button for a foreign point,
               and the filing is mine alone, not a field of the find. ONE
               list: the owner row holds one, so ticking a second one hands
               the checkmark over. */
            drawListSelection(fields.lists, {
                checked: (list) => (point.lists ?? []).includes(list.id),
                onChange: () => {
                    const boxes = [...fields.lists.querySelectorAll('input[type="checkbox"]:checked')];
                    const fresh = boxes.map(b => Number(b.value)).find(id => !(point.lists ?? []).includes(id));
                    const chosen = fresh ?? (boxes.length ? Number(boxes[0].value) : null);
                    for (const b of fields.lists.querySelectorAll('input[type="checkbox"]')) {
                        b.checked = Number(b.value) === chosen;
                    }
                    // The filing is saved right here — the window must not count it as
                    // unsaved input and ask about it on closing (leaveOk).
                    host.lastFilled = { id: point.id, state: detailState() };
                    request('owners.php', { method: 'POST',
                        body: JSON.stringify({ point_id: point.id, list_id: chosen }) })
                        .then((answer) => {
                            point.lists = answer.lists ?? [];
                            drawList(); drawMarker();
                            status(t('Ablage gespeichert.'));
                        })
                        .catch((e) => status(e?.message ?? t('Speichern fehlgeschlagen.'), true));
                },
                legacy: false,
                unfolded: true,   // every sublist open in the point window, see the option
            });
            drawExtraFields(foreignExtraValues(point), foreignFields(point));
        } else if (foreign) {
            // NO LIST TREE AND NO CUSTOM FIELDS. The tree shows MY lists — a
            // foreign point lies in none of them, all checkmarks would be
            // empty, and a checkmark you can set that does nothing is worse
            // than no tree. The extra fields accordingly come from the sharer's
            // share and not from my lists.
            fields.lists.innerHTML = '';
            drawExtraFields(foreignExtraValues(point), foreignFields(point));
        } else {
            // List checkboxes, as a tree.
            //
            // What gets checked is always the list the point REALLY belongs to
            // — that is, the lowest applicable one. That it thereby also shows
            // up in all those above follows by itself and need not be checked;
            // a second checkmark on the parent list would be the same statement
            // twice.
            drawListSelection(fields.lists, {
                checked: (list) => point.lists.includes(list.id),
                // One checkmark more or less can add or remove fields.
                onChange: () => drawExtraFields(),
                // An own point may lie in a joint list of somebody else (22.09.2026).
                joint: true,
                // If the point still hangs in a heading from the old world, this
                // checkmark stays visible and deselectable — otherwise every
                // save would silently strip the assignment.
                legacy: true,
                // Every sublist open, not the sidebar's folds (see the option).
                unfolded: true,
            });

            drawExtraFields(point.extra ?? {});
        }

        // The draft belonged to the previous point or the previous opening —
        // and an outline accidentally sticking to the next point would be hard
        // to notice and hard to get rid of again.
        host.outlineDraft = undefined;
        drawOutlineRow(point.outline ?? null);

        // „Direkt teilen": the tile on my own find, the door on one shown to me.
        drawHintTile(point);
        drawHintReceived(point);

        // LAST, because it builds on everything created above — it locks the
        // extra fields individually, and those only exist now.
        applyDetailRights(point);
        /* AND AFTER THAT THE PHOTO TAB ONCE MORE. The gallery counts its
           pictures already while drawing, but whether the photo field exists
           at all is decided only by applyDetailRights one line above — on a
           foreign point without pictures it disappears. Without this second
           call the tab would still stand there and lead to an empty panel. */
        detailPhotoTab();

        host.lastFilled = { id: point.id, state: detailState() };
        drawKeyings(point, force);
        drawTypeSequences(point);
    }

    // ---------------------------------------------------------------------------
    // The type's sequences from GenBank (15.10.2026;
    // includes/type_sequences.php, api/v1/type_sequences.php)
    // ---------------------------------------------------------------------------

    const typeBox = document.getElementById('d-type-sequences');
    let typeAskedFor = null;     // the species the block was last asked for

    /* The German words for the kinds of type, as the nomenclature writes them. */
    const TYPE_KINDS = {
        holotype: 'Holotypus', epitype: 'Epitypus', neotype: 'Neotypus', lectotype: 'Lectotypus',
        isotype: 'Isotypus', isoepitype: 'Isoepitypus', isoneotype: 'Isoneotypus', isolectotype: 'Isolectotypus',
        syntype: 'Syntypus', paratype: 'Paratypus', 'ex-type': 'Ex-Typus', type: 'Typus',
    };

    /**
     * The block over the find's own sequences: every GenBank record of the
     * type material of the species the find carries — the kind of type, the
     * gene, the accession (to NCBI), length, voucher, country — and a
     * button that saves it as FASTA. Asked again only when the species
     * changed; the server keeps the answer per name for a month.
     */
    async function drawTypeSequences(point) {
        if (!typeBox) return;
        const name = String(point?.title ?? '').trim();
        if (name === typeAskedFor) return;
        typeAskedFor = name;
        typeBox.hidden = true;
        typeBox.textContent = '';
        if (!name || !(point.id > 0) || navigator.onLine === false || !pointIsFind(point)) return;
        let answer;
        try {
            answer = await request(`type_sequences.php?name=${encodeURIComponent(name)}&point=${point.id}`);
        } catch {
            return;
        }
        if (typeAskedFor !== name || !answer?.name) return;
        const head = document.createElement('label');
        // Asked under today's name too (GBIF) where the find carries an older one — then both stand in the head.
        head.textContent = answer.today
            ? t('Typus-Sequenzen von %s, heute %s (GenBank)', answer.name, answer.today)
            : t('Typus-Sequenzen von %s (GenBank)', answer.name);
        const list = document.createElement('div');
        list.className = 'type-sequence-list';
        for (const s of answer.sequences) {
            const row = document.createElement('div');
            row.className = 'type-sequence';
            const kind = document.createElement('span');
            kind.className = 'type-sequence-kind';
            // Of which name, where it is not the one in the head's first place (today's name).
            const of = answer.today && s.of && s.of.toLowerCase() !== String(answer.name).toLowerCase() ? s.of : '';
            kind.textContent = [TYPE_KINDS[s.kind] ?? s.kind, s.marker, of].filter(Boolean).join(' · ');
            const link = document.createElement('a');
            link.href = `https://www.ncbi.nlm.nih.gov/nuccore/${encodeURIComponent(s.accession)}`;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = s.accession;
            link.title = s.title;
            const facts = document.createElement('span');
            facts.className = 'type-sequence-facts';
            facts.textContent = [s.length ? t('%d bp', s.length) : '', s.voucher, s.country].filter(Boolean).join(' · ');
            const save = document.createElement('a');
            save.className = 'button subtle small';
            save.href = `${start.baseUrl}api/v1/type_sequences.php?fasta=${encodeURIComponent(s.accession)}`;
            save.textContent = 'FASTA';
            save.title = t('%s als FASTA-Datei speichern', s.accession);
            row.append(kind, link, facts, save);
            list.append(row);
        }
        /* NOTHING FOUND IS SAID, NOT HIDDEN (15.10.2026, at
           Flammula alnicola: an empty tab read as „no sequences at all",
           while GenBank holds hundreds of it, only none flagged as type).
           The sentence says which, and how many there are without the flag,
           with the way to them at NCBI; or that GenBank did not answer. */
        if (!answer.sequences.length) {
            const none = document.createElement('p');
            none.className = 'hint-text type-sequence-none';
            const names = answer.today ? `${answer.name} / ${answer.today}` : answer.name;
            const all = (answer.all ?? 0) + (answer.all_today ?? 0);
            if (!answer.ok) {
                none.textContent = t('GenBank war gerade nicht zu erreichen. Später noch einmal öffnen.');
            } else if (all > 0) {
                none.append(t('Bei GenBank ist für %s keine Typus-Sequenz gekennzeichnet.', names) + ' ');
                const link = document.createElement('a');
                link.href = `https://www.ncbi.nlm.nih.gov/nuccore/?term=${encodeURIComponent(`"${answer.name}"[Organism]`)}`;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = all === 1 ? t('1 Sequenz ohne Typus ansehen') : t('%d Sequenzen ohne Typus ansehen', all);
                none.append(link);
            } else {
                none.textContent = t('Bei GenBank gibt es für %s keine Sequenz.', names);
            }
            typeBox.append(head, none);
        } else {
            typeBox.append(head, list);
        }
        if (answer.unite) typeBox.append(...uniteRows(answer));
        typeBox.hidden = false;
    }

    /**
     * UNITE'S SPECIES HYPOTHESES under GenBank's types
     * (15.10.2026): one row per hypothesis — the sequence to compare one's
     * own ITS with (the reference a curator set, or else the representative),
     * how many sequences the hypothesis gathers, and its code as the link
     * to its DOI page. The sequence itself lives at GenBank, so its FASTA
     * comes the same way as a type's. Nothing found is said here too.
     */
    function uniteRows(answer) {
        const head = document.createElement('label');
        head.textContent = t('Artenhypothesen von %s (UNITE, ITS)', answer.today && answer.unite.of === answer.today ? answer.today : answer.name);
        const hypotheses = answer.unite.hypotheses ?? [];
        if (!hypotheses.length) {
            const none = document.createElement('p');
            none.className = 'hint-text type-sequence-none';
            none.textContent = answer.unite.ok
                ? t('UNITE hat für diese Art keine Artenhypothese.')
                : t('UNITE war gerade nicht zu erreichen. Später noch einmal öffnen.');
            return [head, none];
        }
        const list = document.createElement('div');
        list.className = 'type-sequence-list';
        for (const sh of hypotheses) {
            const row = document.createElement('div');
            row.className = 'type-sequence';
            const kind = document.createElement('span');
            kind.className = 'type-sequence-kind';
            kind.textContent = [
                sh.kind === 'reference' ? t('Referenzsequenz') : sh.kind === 'representative' ? t('Vertretersequenz') : '',
                sh.from_type ? t('vom Typus') : '',
            ].filter(Boolean).join(' · ') || '–';
            const link = document.createElement(sh.accession ? 'a' : 'span');
            if (sh.accession) {
                link.href = `https://www.ncbi.nlm.nih.gov/nuccore/${encodeURIComponent(sh.accession)}`;
                link.target = '_blank';
                link.rel = 'noopener';
            }
            link.textContent = sh.accession ?? '–';
            const facts = document.createElement('span');
            facts.className = 'type-sequence-facts';
            const code = document.createElement('a');
            code.href = sh.doi;
            code.target = '_blank';
            code.rel = 'noopener';
            code.textContent = sh.code;
            facts.append(code, ' · ' + (sh.sequences === 1 ? t('1 Sequenz') : t('%d Sequenzen', sh.sequences)));
            if (sh.set_by) facts.append(' · ' + t('gesetzt von %s', sh.set_by));
            row.append(kind, link, facts);
            if (sh.accession) {
                const save = document.createElement('a');
                save.className = 'button subtle small';
                save.href = `${start.baseUrl}api/v1/type_sequences.php?fasta=${encodeURIComponent(sh.accession)}`;
                save.textContent = 'FASTA';
                save.title = t('%s als FASTA-Datei speichern', sh.accession);
                row.append(save);
            }
            list.append(row);
        }
        return [head, list];
    }

    // ---------------------------------------------------------------------------
    // The key behind „Art" and the ways walked for the find
    // (14.10.2026; includes/point_keyings.php, api/v1/keyings.php)
    // ---------------------------------------------------------------------------

    const keyButton = document.getElementById('d-key');
    const keyingsBox = document.getElementById('d-keyings');
    const keyBack = document.getElementById('d-key-back');
    let keyingsShown = null;     // the find whose ways stand under „Art"

    /* THE WAY TYPED BY HAND (20.11.2026, migration 0198) — one's own, one
       per find and person, in a field of its own under „Art". It does not
       ride on the find's save: it is no field of the find but a statement
       of its keyer, open on a friend's find too, where there is nothing to
       save. So it saves ITSELF when the field is left (`change`), and
       before the window leads away to the key. */
    const wayField = document.getElementById('d-key-way-field');
    const wayInput = document.getElementById('d-key-way');
    let wayPoint = null;         // the find the text in the field belongs to
    let wayStored = '';          // what the server holds for it

    async function saveWay() {
        const id = wayPoint;
        if (!wayInput || !(id > 0)) return;
        const text = wayInput.value;
        if (text.trim() === wayStored) return;
        try {
            const answer = await request('keyings.php', { method: 'POST', body: JSON.stringify({ point: id, way: text }) });
            if (wayPoint !== id) return;   // another find stands in the window by now
            wayStored = answer?.mine?.way ?? '';
            // The server's shape (no blanks at the ends) — unless typing went on meanwhile.
            if (wayInput.value === text) wayInput.value = wayStored;
        } catch {
            status(t('Der Schlüsselweg ließ sich nicht speichern. Dafür braucht es eine Verbindung.'), true);
        }
    }
    wayInput?.addEventListener('change', saveWay);

    /**
     * THE WAYS UNDER „ART", as small as they can be („möglichst
     * platzsparend"): one line a confirmed way — the book and the species it
     * led to, no steps and no detours since 05.11.2026 (they are the key's,
     * one click away on the line). Every keyer's ways
     * under his name where there is more than one keyer; the others' are
     * looked at, never changed. Whoever reads the keys opens a way in the
     * identification module at its result. Asked of the server once per find
     * shown (and again when the window is filled anew by force), not on every
     * quiet refill after a save.
     */
    async function drawKeyings(point, force = false) {
        if (!keyButton || !keyingsBox) return;
        const id = point?.id ?? null;
        keyButton.hidden = !(start.keyTool && id > 0 && !fields.title.disabled && !GUEST);
        if (id === keyingsShown && !force) return;
        // A way still unsaved belongs to the find that is leaving — it goes first.
        if (id !== wayPoint) {
            await saveWay();
            wayPoint = null;
            if (wayField) wayField.hidden = true;
        }
        keyingsShown = id;
        keyingsBox.hidden = true;
        keyingsBox.textContent = '';
        if (keyBack) keyBack.hidden = true;
        if (!(id > 0) || navigator.onLine === false) return;
        let answer;
        try {
            answer = await request(`keyings.php?point=${id}`);
        } catch {
            return;   // no ways shown is no error
        }
        if (keyingsShown !== id || !answer?.ready) return;
        // My typed way into its field — only now, when it is known what stands there.
        if (wayField && wayInput && answer.way_ready && !GUEST) {
            // A refill of the same find must not take away what is being typed.
            if (wayPoint !== id || wayInput.value.trim() === wayStored) {
                wayStored = answer.mine?.way ?? '';
                wayInput.value = wayStored;
            }
            wayPoint = id;
            wayField.hidden = false;
        }
        /* „Zurück zum Schlüssel" while MY way here stands somewhere — a
           couplet or a result not yet confirmed. A way that ended in the
           confirmed species is done; its line under „Art" still leads there. */
        let mine = answer.mine?.walk ?? null;
        if (typeof mine === 'string') {
            try { mine = JSON.parse(mine); } catch { mine = null; }
        }
        const current = (answer.mine?.ways ?? []).find((w) => w.n === (mine?.n ?? 0));
        if (keyBack && start.keyTool && !GUEST && mine?.at && !current?.confirmed) {
            keyBack.href = `${start.keyTool.url}?` + new URLSearchParams({ find: id, resume: 1 });
            keyBack.hidden = false;
        }
        /* What stands under „Art": every keyer's confirmed ways from the
           module, and the OTHERS' typed ways. My own typed way is in its
           field; an entry of mine that holds nothing else has no line here. */
        const entries = [answer.mine, ...answer.others].filter(Boolean)
            .filter((e) => e !== answer.mine || e.walk != null || e.confirmed_name);
        if (entries.length === 0) return;
        /* THE TOOL'S PAGE — start.keyTool.url, where a module offers a key tool
           to this account (includes/modules.php, key_tool; the parameters
           hung on it here and at the two buttons are listed there). */
        const keysPage = (params) => `${start.keyTool.url}?` + new URLSearchParams(params);
        const many = entries.length > 1 || !entries.includes(answer.mine);
        const line = (entry, way) => {
            const href = start.keyTool
                ? keysPage({ find: id, user: entry.user_id, ...(way?.n != null ? { way: way.n } : {}) }) : null;
            const el = document.createElement(href ? 'a' : 'span');
            el.className = 'detail-keying-way';
            if (href) el.href = href;
            /* THE BOOK AND THE RESULT, NOTHING BETWEEN (05.11.2026:
               „Das ist zu viel Text dort … Nur das Buch und das Ergebnis und
               keine anderen Schlüsselwege … wenn man auf das Buch klickt,
               kommt man direkt zum Schlüssel … und kann sich die Wege und
               auch die Fehlwege anschauen"). The steps, the key's name and
               the detours live in the key; the line is the way into it. */
            if (way?.book) {
                const book = document.createElement('span');
                book.className = 'detail-keying-book';
                book.textContent = way.book;
                el.append(book);
            }
            const result = way ? way.result : entry.confirmed_name;
            if (result) {
                el.append(' → ');
                const name = document.createElement('i');
                name.textContent = result;
                el.append(name);
            }
            el.classList.toggle('is-confirmed', way ? !!way.confirmed : !!entry.confirmed_name);
            el.title = href ? t('Im Schlüssel ansehen — mit allen Wegen') : '';
            return el;
        };
        for (const entry of entries) {
            const group = document.createElement('div');
            group.className = 'detail-keying';
            if (many) {
                const who = document.createElement('span');
                who.className = 'detail-keying-who';
                who.textContent = entry === answer.mine ? t('Mein Weg') : entry.name;
                group.append(who);
            }
            // Only the confirmed ways — the detours and dead ends are the key's to show.
            const ways = Array.isArray(entry.ways) ? entry.ways : null;
            if (ways && ways.length) {
                for (const way of ways) if (way.confirmed) group.append(line(entry, way));
                if (!ways.some((w) => w.confirmed)) {
                    const none = document.createElement('span');
                    none.className = 'detail-keying-none';
                    none.textContent = t('noch keine Art bestätigt');
                    group.append(none);
                }
            } else if (entry.walk_lines) {
                /* NO TOOL DRAWS THE WAYS HERE, but the walk left its lines
                   (migration 0199): one per confirmed way, as the tool wrote
                   them when the walk was saved — the record that stands
                   without it. Plain text; there is nothing to lead to. */
                const kept = document.createElement('span');
                kept.className = 'detail-keying-text';
                kept.textContent = entry.walk_lines;
                group.append(kept);
            } else if (entry.confirmed_name) {
                group.append(line(entry, null));
            } else if (entry === answer.mine || !entry.way) {
                const none = document.createElement('span');
                none.className = 'detail-keying-none';
                none.textContent = t('noch keine Art bestätigt');
                group.append(none);
            }
            // What somebody else typed as his way — read, line for line as he wrote it.
            if (entry !== answer.mine && entry.way) {
                const typed = document.createElement('span');
                typed.className = 'detail-keying-text';
                typed.textContent = entry.way;
                group.append(typed);
            }
            keyingsBox.append(group);
        }
        keyingsBox.hidden = false;
    }

    /* THE KEY: the identification module for this find, with the species as
       it stands in the field — the module offers the genus's or the family's
       keys from it, or its finder where the field is empty. Whatever waits to
       be saved goes first (host.beforeLeave, the Fundbrowser's autosave). */
    // Whatever waits to be saved goes first, as at the key.
    keyBack?.addEventListener('click', async (e) => {
        if (!host.beforeLeave) return;
        e.preventDefault();
        if (await host.beforeLeave()) location.href = keyBack.href;
    });

    keyButton?.addEventListener('click', async (e) => {
        e.preventDefault();
        const id = state.selectedId;
        if (!(id > 0)) return;
        await saveWay();
        if (host.beforeLeave && !(await host.beforeLeave())) return;
        if (!start.keyTool) return;
        location.href = `${start.keyTool.url}?` + new URLSearchParams({ find: id, name: fields.title.value.trim() });
    });

    // ---------------------------------------------------------------------------
    // „Direkt teilen" — a find shown to a friend (includes/point_hints.php)
    // ---------------------------------------------------------------------------

    function hintRecipients(pointId) {
        return hintStart?.given?.[pointId] ?? [];
    }

    /* Recipients somebody ELSE ticked — the holder or another co-owner. A
       co-owner cannot take those back (point_hint_set); the holder can. */
    function hintOthers(pointId) {
        return hintStart?.givenOthers?.[pointId] ?? [];
    }

    /** The tile above the lists — on my own find and on one I co-own (06.10.2026). */
    function drawHintTile(point) {
        const tile = hintFields.tile;
        if (!tile) return;
        tile.textContent = '';
        const foreign = foreignPoint(point.id);
        const field = hintFields.tileField ?? tile;
        field.hidden = hintStart === null || GUEST || (foreign !== null && !foreign.co_owned);
        if (field.hidden) return;

        const holder = foreign === null;
        const friends = [...(start.friends ?? [])].sort((a, b) => a.name.localeCompare(b.name, 'de'));
        const shown = hintRecipients(point.id);
        const others = hintOthers(point.id);
        // Shown, but to nobody among MY friends — a co-owner's friends on my find.
        const elsewhere = shown.filter(id => !friends.some(f => f.id === id)).length;

        /* EVERY FRIEND IN SIGHT, NOTHING TO FOLD (06.10.2026:
           „ich würde direkt alles immer anzeigen. Nichts zum Ein- und
           Ausklappen. Und direkt teilen dann als Überschrift nehmen"). Until
           then the tile was shut at every opening and named only the friends
           the find was shown to; the heading is the field's label now
           (index.php), the friends stand under it with their boxes. What there
           is to choose stands in sight (CLAUDE.md). */
        const box = document.createElement('div');
        box.className = 'list-selection-tile';

        if (friends.length === 0) {
            const none = document.createElement('p');
            none.className = 'hint-text small detail-hints-none';
            none.textContent = t('Freunde findest du in der Verwaltung unter Kontakte.');
            box.append(none);
        }
        for (const friend of friends) {
            const on = shown.includes(friend.id);
            const row = document.createElement('div');
            row.className = 'list-selection-row';
            row.style.setProperty('--depth', '0');
            const spacer = document.createElement('span');
            spacer.className = 'list-selection-arrow';
            spacer.setAttribute('aria-hidden', 'true');
            const entry = document.createElement('label');
            entry.className = 'list-selection-entry';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = on;
            // A provisional find has no id on the server yet to show.
            input.disabled = !(point.id > 0);
            if (input.disabled) entry.title = t('Erst nach dem Speichern');
            // Ticked by the holder or another co-owner: only they take it back.
            if (on && !holder && others.includes(friend.id)) {
                input.disabled = true;
                entry.title = t('Zurücknehmen kann das nur, wer es geteilt hat, oder wem der Punkt gehört.');
            }
            const text = document.createElement('span');
            text.className = 'list-selection-name';
            text.textContent = friend.name;
            entry.append(input, text);
            row.append(spacer, entry);
            input.addEventListener('change', () => hintToggle(point, friend, input));
            box.append(row);
        }
        // Shown to people who are none of my friends — a co-owner's friends on
        // my find. Their boxes stand in my tab „Direkt geteilt", where I can
        // take the shares back; here one quiet line says they exist.
        if (elsewhere > 0) {
            const more = document.createElement('p');
            more.className = 'hint-text small detail-hints-none';
            more.textContent = elsewhere === 1 ? t('Außerdem mit 1 Person über einen Mitbesitzer.')
                : t('Außerdem mit %d Personen über Mitbesitzer.', elsewhere);
            box.append(more);
        }
        tile.append(box);
    }

    /**
     * One tick, one friend, saved at once. THE LAST ONE ASKS
     * (02.10.2026: „wenn der letzte Haken raus ist, ist die Freigabe damit
     * gelöscht. Mit Nachfrage natürlich") — before it, a friend goes and the
     * others keep the find.
     */
    async function hintToggle(point, friend, input) {
        const on = input.checked;
        const shown = hintRecipients(point.id);
        if (!on && shown.length === 1 && shown[0] === friend.id) {
            const ok = await confirm({
                title: t('Mit niemandem mehr teilen?'),
                text: t('%s sieht diesen Punkt dann nicht mehr unter „Direkt geteilt".', friend.name),
                button: t('Nicht mehr teilen'),
                danger: true,
            });
            if (!ok) {
                input.checked = true;
                return;
            }
        }
        input.disabled = true;
        try {
            const answer = await request('hints.php', { method: 'POST',
                body: JSON.stringify({ point_id: point.id, friend_id: friend.id, on }) });
            const now = answer?.recipients ?? [];
            if (now.length > 0) hintStart.given[point.id] = now;
            else delete hintStart.given[point.id];
            if (hintStart.givenOthers) {
                if ((answer?.others ?? []).length > 0) hintStart.givenOthers[point.id] = answer.others;
                else delete hintStart.givenOthers[point.id];
            }
            status(on ? t('%s sieht diesen Punkt jetzt unter „Direkt geteilt".', friend.name)
                      : t('%s sieht diesen Punkt nicht mehr unter „Direkt geteilt".', friend.name));
        } catch (e) {
            input.checked = !on;
            status(e?.message ?? t('Speichern fehlgeschlagen.'), true);
        }
        if (state.selectedId === point.id) drawHintTile(point);
    }

    /** On a find shown to me: who showed it, and the door. */
    function drawHintReceived(point) {
        const field = hintFields.received;
        if (!field) return;
        const foreign = foreignPoint(point.id);
        const from = foreign?.hint_from != null ? (foreign.hint_from_name ?? foreign.owner) : null;
        field.hidden = from === null;
        if (from === null) return;
        const who = shortNameOf(from);
        hintFields.receivedText.textContent = foreign.lists.length > 0
            ? t('%s teilt diesen Punkt direkt mit dir. Entfernst du ihn aus „Direkt geteilt", bleibt er in der Liste, die %s mit dir teilt.', who, who)
            : t('%s teilt diesen Punkt direkt mit dir. Entfernst du ihn aus „Direkt geteilt", verschwindet er von deiner Karte. Wieder direkt teilen kann ihn nur %s.', who, who);
    }

    hintFields.leave?.addEventListener('click', () => hintLeave(foreignPoint(state.selectedId)));

    /**
     * What hangs on the point but does not belong in an input field.
     *
     * These details arise on the server — the height from converting the
     * receiver measurement, the timestamp on creation. They may therefore grow
     * in even while somebody is typing: there is nothing to lose, and without
     * them the row would stand empty although the detail is long there.
     */
    /**
     * The line under the number field, while one types — what the number is
     * and what saving it does, with the way out beside it
     * (26.09.2026: „steht dann irgendwo, diese Nummer ist bereits vergeben?
     * Die nächsthöhere wäre …"). Read from the stock on the device and the
     * series the start data brought (find_number_series), so it answers in
     * the woods as well; the server decides at saving and at the next mint
     * alike (find_number_normalize, find_number_mint).
     *
     *   empty            the button for the next free number of the series
     *   „Y-…"            refused — YASAIMAPP's own mark
     *   another shape    a series of its own; the counter stays untouched
     *   the series' shape
     *     taken          who carries it, and the next free one to take
     *     in the future  the date in it lies ahead
     *     above the next the counter will go on above it — said, not silent
     *     otherwise      which number of the series, and its spelling
     *   With a restart, „next free" is counted within the day, month or year
     *   the number itself names; where that date is not the find's, both are
     *   named.
     */
    function drawNumberHint(point) {
        const hint = fields.numberHint;
        const value = fields.number.value.trim();
        const series = start.findNumber ?? null;
        hint.replaceChildren();
        hint.classList.remove('is-warning', 'is-error');
        hint.hidden = true;

        /* THE TRASH COUNTS TOO (review of 26.09.2026): a find put back keeps
           its number, so a number the trash holds is not free — the server's
           count, the import and the numbering window knew it, this line did
           not. The trash comes with the start data (find_number_trash); what
           was trashed since stands in the store with deleted_at. */
        const titled = (p) => p.title || t('Ohne Titel');
        const others = [...state.points.values()].filter((p) => p.id !== point?.id && p.find_number)
            .map((p) => ({ number: p.find_number, title: p.deleted_at ? t('%s (im Papierkorb)', titled(p)) : titled(p) }));
        for (const n of start.findNumberTrash ?? []) {
            others.push({ number: n.number, title: t('%s (im Papierkorb)', n.title || t('Ohne Titel')) });
        }
        const given = others.map((o) => o.number);
        const findDay = point?.found_at ? new Date(String(point.found_at).replace(' ', 'T')) : new Date();
        const say = (text, kind = null, take = null) => {
            hint.append(text);
            if (take) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'button subtle small';
                button.textContent = t('%s nehmen', take);
                button.addEventListener('click', () => {
                    fields.number.value = take;
                    fields.number.dispatchEvent(new Event('input', { bubbles: true }));
                });
                hint.append(' ', button);
            }
            if (kind) hint.classList.add(kind);
            hint.hidden = false;
        };

        if (value === '') {
            if (series && point) {
                const next = nextFreeNumber(series, [...given, point.find_number].filter(Boolean), findDay);
                say(t('Ohne eigene Nummer trägt der Fund seine Nummer von YASAIMAPP.'), null, next.text);
            }
            return;
        }
        if (findNumberReserved(value)) {
            say(t('Eine Fundnummer darf nicht mit „Y-" anfangen. So beginnen die Nummern, die YASAIMAPP selbst vergibt.'), 'is-error');
            return;
        }
        const read = series ? readFindNumber(series.pattern, value) : null;
        const spelled = read?.normalized ?? value;
        // By the number in the series, not by its letters: „wk-1" is taken
        // when a find carries „wk-00001" (findNumberKey).
        const key = (number) => findNumberKey(series?.pattern ?? null, number);
        const carrier = others.find((o) => key(o.number) === key(spelled));
        if (!read) {
            if (carrier) {
                say(t('Diese Nummer trägt schon „%s".', carrier.title), 'is-warning');
            } else if (series) {
                say(t('Nicht dein Muster %s, sondern eine eigene Reihe. Dein Zähler bleibt davon unberührt.', series.pattern));
            }
            return;
        }

        // The section the number itself names — with a restart the next free
        // one is counted there, without one across the whole series.
        const named = numberDate(read.parts);
        const when = series.restart && named ? named : findDay;
        const next = nextFreeNumber(series, given, when);
        const within = { Tag: t('für diesen Tag'), Monat: t('für diesen Monat'), Jahr: t('für dieses Jahr') }[next.unit] ?? '';
        const lines = [];
        let kind = null;
        let take = null;
        if (carrier) {
            lines.push(t('%s trägt schon „%s".', spelled, carrier.title));
            lines.push(within ? t('Die nächste freie Nummer %s ist %s.', within, next.text) : t('Die nächste freie Nummer ist %s.', next.text));
            kind = 'is-warning';
            take = next.text;
        } else if (read.n > next.n) {
            const after = nextFreeNumber(series, [...given, spelled], when).text;
            lines.push(within ? t('Die nächste freie Nummer %s ist %s.', within, next.text) : t('Die nächste freie Nummer ist %s.', next.text));
            lines.push(t('Nimmst du %s, zählt YASAIMAPP danach bei %s weiter.', spelled, after));
            kind = 'is-warning';
            take = next.text;
        } else if (spelled !== value) {
            /* A NUMBER IN ORDER SAYS NOTHING (18.10.2026: „Nummer
               968 deiner Reihe — so ein Zusatztext unter der Fundnummer bitte
               entfernen, auch unnötig"). The line stood under every find and
               repeated the number it stood under; only a spelling the save
               will change is still worth a word. */
            lines.push(t('Wird als %s gespeichert.', spelled));
        }
        // A date ahead, and a date that is not the find's — said, not refused.
        if (named) {
            const unit = read.parts.TT !== undefined ? 'Tag' : read.parts.MM !== undefined ? 'Monat' : 'Jahr';
            const cut = (d) => unit === 'Tag' ? d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
                : unit === 'Monat' ? `${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}` : String(d.getFullYear());
            const today = new Date();
            if (cut(named) !== cut(today) && named > today) {
                lines.push(t('Das Datum in der Nummer liegt in der Zukunft.'));
                kind = 'is-warning';
            } else if (cut(named) !== cut(findDay)) {
                lines.push(t('Datum in der Nummer: %s. Fundzeit: %s.', cut(named), cut(findDay)));
            }
        }
        if (lines.length === 0) return;
        say(lines.join(' '), kind, take);
    }

    fields.number.addEventListener('input', () => drawNumberHint(state.points.get(state.selectedId)));

    /* THE FACTS TABLE (fillDetailUneditable says what goes into it): one row a
       fact, the value beside its label, the explanation under the value in the
       quiet voice. Built from text nodes — the names come from outside (the
       BfN, the DGfM) and are never markup. */
    const factsMore = document.getElementById('d-facts-more');

    const factsToggle = document.getElementById('d-facts-toggle');

    /* OPEN OR SHUT IS REMEMBERED on the device: whoever opened it to compare
       finds wants it open on the next one too, and whoever shut it wants the
       line alone. A convenience of this device, like the folded lists. */
    function factsOpen(open) {
        if (!factsMore || !factsToggle) return;
        factsMore.hidden = !open || factsMore.childElementCount === 0;
        factsToggle.setAttribute('aria-expanded', String(!factsMore.hidden));
        factsToggle.classList.toggle('is-active', !factsMore.hidden);
        for (const area of fields.accuracy.querySelectorAll('.fact-area')) {
            area.setAttribute('aria-expanded', String(!factsMore.hidden));
        }
    }

    function drawFactsTable(rows) {
        if (!factsMore || !factsToggle) return;
        factsMore.textContent = '';
        for (const [label, value, hint, kind] of rows) {
            const dt = document.createElement('dt');
            dt.textContent = label;
            if (kind) dt.dataset.kind = kind;
            const dd = document.createElement('dd');
            for (const line of Array.isArray(value) ? value : [value]) {
                const span = document.createElement('span');
                span.className = 'detail-facts-line';
                span.append(line);
                dd.append(span);
            }
            if (hint) {
                const small = document.createElement('span');
                small.className = 'detail-facts-hint';
                small.textContent = hint;
                dd.append(small);
            }
            factsMore.append(dt, dd);
        }
        // A point without any fact (a fresh waypoint) has nothing to open.
        factsToggle.hidden = rows.length === 0;
        factsOpen(settings.get('factsOpen', false) === true);
    }

    factsToggle?.addEventListener('click', () => {
        const open = factsMore.hidden;
        settings.set('factsOpen', open);
        factsOpen(open);
    });

    /* The shortened protected area opens the same table — by keyboard too,
       since the piece is a button to the screen reader. One listener on the
       row: its pieces are rebuilt on every drawing. */
    function factAreaOpen(e) {
        const area = e.target.closest?.('.fact-area');
        if (!area) return;
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault();
        const open = factsMore.hidden;
        settings.set('factsOpen', open);
        factsOpen(open);
    }

    fields.accuracy.addEventListener('click', factAreaOpen);

    fields.accuracy.addEventListener('keydown', factAreaOpen);

    /* THE SERVER'S ONE STRING, TAKEN APART into its areas —
       "Naturschutzgebiet Buckelwiesen am Plattele, FFH-Gebiet Mittenwalder
       Buckelwiesen" (protected_areas_get joins them so). It splits only
       where the next area's KIND begins, because a name may carry a comma of
       its own; the kinds and their short names come from the layer
       definition (map_layers.php), the same words the server put in front. */
    function protectedAreasOf(text) {
        const kinds = start.layers.find((layer) => layer.id === 'protected_areas')?.kinds ?? [];
        const words = kinds.map((kind) => kind.text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
        const parts = words.length
            ? text.split(new RegExp(', (?=(?:' + words.join('|') + ') )'))
            : [text];
        return parts.map((name) => ({
            name,
            kind: kinds.find((kind) => kind.short && name.startsWith(kind.text + ' ')) ?? null,
        }));
    }

    function fillDetailUneditable(point) {
        /* TWO FORMS OF THE SAME FACTS (30.09.2026: „In der
           Titelzeile soll eigentlich eine verkürzte Version stehen. Man könnte
           ja einen Info-Knopf machen … Und dann kommen da genau die ganzen
           Daten mit Zusatzinformationen"). `parts` is the short line under the
           name — what one reads at a glance; `more` is the table the ⓘ opens —
           every fact, one row each, with what it means. Each fact below writes
           both, so the two can never tell different stories. */
        const parts = [];
        const more = [];   // [label, value, hint] — the value may be several lines
        // The kind names the fact for whoever draws it elsewhere (the Fundbrowser's head, by its icon).
        const row = (label, value, hint = '', kind = '') => more.push([label, value, hint, kind]);

        /* THE FIND NUMBER FIRST — it is the find's identity in the row of
           derived details. A minted one comes from the server (find_number,
           shaped by the pattern in the settings); without a pattern the
           database id IS the number, read straight off the point. Only for
           finds: a waypoint carries no collection number, so the row stays
           quiet outside species branches. A provisional point (negative id,
           still in the queue) shows nothing yet — its number arrives with
           the server's answer, like the municipality. */
        if (pointIsFind(point)) {
            // An Aufsammlung wears its own number for the box (0174), never the find mark.
            const number = point.is_collection ? (point.collection_number ?? null)
                : point.find_number ?? (point.id > 0 ? findNumberFallback(point.id) : null);
            if (number !== null) {
                /* THE HASH INSTEAD OF „Fund-Nr." (30.09.2026), like
                   the mountain before the height: the sign says what the
                   number is, and speaks „Fundnummer" to the screen reader. */
                const piece = document.createElement('span');
                piece.append(icon('hash', { title: t('Fundnummer') }), number);
                piece.title = point.find_number
                    ? t('Fundnummer — nach deinem Muster aus den Einstellungen')
                    : t('Fundnummer — von YASAIMAPP vergeben. Ein eigenes Muster stellst du unter Einstellungen → Fundnummer ein.');
                parts.push(piece.outerHTML);
                row(t('Fundnummer'), number, point.find_number
                    ? t('Nach deinem Muster aus den Einstellungen.')
                    : t('Von YASAIMAPP vergeben. Ein eigenes Muster stellst du unter Einstellungen → Fundnummer ein.'), 'number');
            }
        }

        /* THE ACCURACY BY ITS SIGN — the circle with its radius
           (30.09.2026: „für die Genauigkeit … auch ein Symbol"). With the sign
           „±5 m" says what it is without the word, and the line stays short. */
        if (point.accuracy_m != null) {
            const piece = document.createElement('span');
            piece.className = 'fact-accuracy';
            piece.append(icon('accuracy', { title: t('Genauigkeit') }), t('±%s m', point.accuracy_m));
            piece.title = t('Genauigkeit ±%s m', point.accuracy_m) + ' — ' + t('So genau kannte das Gerät seinen Standort.');
            parts.push(piece.outerHTML);
            row(t('Genauigkeit'), t('±%s m', point.accuracy_m), t('So genau kannte das Gerät seinen Standort.'), 'accuracy');
        }

        // 'ü. NHN' may stand here because the server converted the raw
        // measurement via the EGM96 grid.
        //
        // The uncertainty deliberately does not stand next to it, although at
        // around 10 m it is large: at that order of magnitude it is acceptable
        // for this purpose, and a second number behind every height only makes
        // the row restless. It is stored nonetheless — whoever evaluates later
        // finds it in elevation_accuracy_m.
        //
        // Where the height comes from matters, in contrast: between ±10 m from
        // the receiver and ±0.3 m from the terrain model lies an order of
        // magnitude — and that is why the SOURCE stands in the table, with its
        // accuracy. The short line carries the mountain and the metres alone
        // (30.09.2026: „statt Meter über NHN nicht einfach das
        // Bergsymbol vorne dran setzen und nur Meter", then „(Copernicus 90 m)
        // eigentlich brauche ich das auch nicht"). The sign speaks „Höhe" to
        // the screen reader, or „943 m" would be read out without a subject.
        if (point.elevation_m != null) {
            const source = {
                dgm1: t('Höhe aus dem Geländemodell DGM1 — auf etwa 0,3 m genau'),
                worldwide: t('Höhe aus dem weltweiten Geländemodell Copernicus — Raster 90 m'),
                gnss: t('Höhe vom Empfänger des Geräts — auf etwa 10 m genau'),
            }[point.elevation_source] ?? '';
            const piece = document.createElement('span');
            piece.className = 'fact-elevation';
            piece.append(icon('mountain', { title: t('Höhe') }), t('%s m', point.elevation_m));
            piece.title = t('%s m ü. NHN', point.elevation_m) + (source ? ' — ' + source : '');
            parts.push(piece.outerHTML);
            row(t('Höhe'), t('%s m ü. NHN', point.elevation_m), source, 'elevation');
        }

        // THE MUNICIPALITY AS A WHOLE PATH, "DE/BAY/Mittenwald". The point list
        // shows only the name — line width counts there. Here it stands
        // complete, because it is the same path in which the voucher photos
        // lie.
        //
        // Assembled it comes from the server (point.folder), so the rules for
        // allowed folder names stand in one place. A point still lying in the
        // queue does not have it — then nothing stands here, and that is right:
        // the municipality is information from outside, not a calculation.
        // point.ablage: the field's name before the move to English — points
        // stored on the device keep it until their bucket is fetched afresh.
        /* WRITTEN AS IN HIS FILE NAMES (30.09.2026: „statt mit einem
           Slash mit einem Komma trennen, so wie es bei mir im Dateinamen ist" —
           „Boletus edulis 2018-10-25 DE,HE, Rambach, Goldsteintal"): state and
           land joined by a bare comma, the place after a comma and a space.
           The folder path with its slashes — where the voucher photos lie —
           stands in the table and the tooltip, which is where one copies it
           from now. */
        if (point.folder || point.ablage) {
            const path = point.folder || point.ablage;
            const shown = placeWritten(path);
            const piece = document.createElement('span');
            piece.className = 'fact-place';
            piece.textContent = shown;
            piece.title = path + ' — ' + t('Staat / Landesteil / Gemeinde — das Ablageschema der Belegfotos');
            parts.push(piece.outerHTML);
            row(t('Ort'), shown, t('Ordner der Belegfotos: %s', path), 'place');
        }

        // THE MAP SHEET, COMPLETE — "5817/444", sheet, quarter, sixteenth,
        // sixty-fourth. That is the form a find report is written in;
        // shortening it to "5817/4" would mean having to look up the finer
        // digits although they are long there.
        //
        // ONLY IN THE TABLE since 30.09.2026 („Das ist eher was zum
        // Kartieren, das sagt keinem Menschen was. Außer natürlich die, die in
        // dem Gebiet häufig kartieren"). Whoever maps finds it one tap away.
        if (point.mtb) {
            const q = point.mtb_quadrant || '';
            row(t('MTB'), point.mtb + (q ? '/' + q : ''), q.length === 3
                ? t('Blatt %s · Viertel %s · Sechzehntel %s · Vierundsechzigstel %s  (1=NW 2=NO 3=SW 4=SO)',
                    point.mtb, q[0], q[1], q[2])
                : '', 'mtb');
        }

        // THE PROTECTED AREA, and it is the most consequential detail of the
        // row: whether a find lies in a nature reserve decides what one may do
        // there at all. It comes from the server (protected_areas.php) and only
        // for finds — a campsite does not need it.
        //
        // EMPTY TEXT MEANS "CHECKED, LIES IN NONE" and therefore does not
        // appear; null means "not yet asked" and just as little. Neither is
        // information that deserves a row — unlike the name of an area.
        /* THE KIND'S SHORT NAME IN THE LINE, the names in the table
           (30.09.2026, first „abgekürzt mit ein paar Punkten", then
           signs, and on 01.10.2026 „ganz normal ab: NSG … FFH"). What the line
           has to say at a glance is WHICH kinds of law apply here — the names
           are for looking up, and at the Altkönig both are called „Altkönig"
           anyway. The short names are the ones the BfN and every survey
           writes; signs had to be learnt first, „NSG" is read by anyone in the
           field. Each kind once, as a piece of its own like „RL 3" beside it,
           in the order of the layer definition, strictest first. A tap on one
           opens the table, where every area has a line with its full name. An
           area of a kind the definition does not know (stored under an older
           word) keeps its name, shortened, rather than vanish. */
        if (point.protected_area) {
            const areas = protectedAreasOf(point.protected_area);
            const groups = new Map();   // kind → its areas' names; null = unknown kind
            for (const { kind, name } of areas) {
                if (!groups.has(kind)) groups.set(kind, []);
                groups.get(kind).push(name);
            }
            for (const [kind, names] of groups) {
                const piece = document.createElement('span');
                piece.className = kind ? 'fact-area' : 'fact-area fact-area-name';
                piece.textContent = kind ? kind.short : names.join(', ');
                piece.title = names.join(', ') + ' — ' + t('Schutzgebiete an dieser Stelle — vom Bundesamt für Naturschutz');
                // The screen reader says the names, not three letters.
                piece.setAttribute('aria-label', names.join(', '));
                piece.tabIndex = 0;
                piece.setAttribute('role', 'button');
                piece.setAttribute('aria-controls', 'd-facts-more');
                parts.push(piece.outerHTML);
            }
            row(t('Schutzgebiete'), areas.map(({ name }) => name), t('Vom Bundesamt für Naturschutz.'), 'protected');
        }

        // THE SPECIES' THREAT STATUS, and until 15.08.2026 it stood ONLY in the
        // suggestion list while typing: once the point was saved, it was
        // nowhere to be seen anymore. Of all things, the detail that has a say
        // in whether the find may be touched at all.
        //
        // IT DOES NOT SIT ON THE POINT but comes fresh from the taxon on load
        // (arten_gefaehrdung in species.php) — a newly issued Red List thereby
        // also changes the display on old finds, and that is exactly how it
        // should be.
        /* IN THE TABLE THE LEVEL IS WRITTEN OUT (01.10.2026):
           „Gefährdet (RL 3)" — the word first, the short form of the line
           behind it, as the legend writes „Naturschutzgebiet (NSG)" — and
           under it what the level means, in plain words (RL_CATEGORIES). */
        for (const category of point.red_list ?? []) {
            const level = filter.RL_CATEGORIES.find(k => k.value === category);
            const name = level?.name ?? category;
            const piece = document.createElement('span');
            piece.className = 'fact-rl';
            piece.textContent = t('RL %s', category);
            piece.title = t('Rote Liste Deutschlands: %s', name);
            parts.push(piece.outerHTML);
            row(t('Rote Liste'), t('%s (RL %s)', name.charAt(0).toUpperCase() + name.slice(1), category),
                [t('Rote Liste Deutschlands.'), level?.meaning].filter(Boolean).join(' '), 'redlist');
        }
        // THE DGfM'S MARKS (17.09.2026) — Speisepilz, Giftpilz,
        // Verantwortungsart — looked up on the species like the category
        // above. The remark and the syndrome are the explanation: „Giftpilz"
        // is the fact, „Amatoxin-Syndrom" the footnote.
        //
        // THE FOOD VALUE LEFT THE SHORT LINE (30.09.2026:
        // „Speisewert kann eigentlich auch weg") and stands in the table with
        // its footnote. What stays in the line is the Verantwortungsart, a
        // statement about protection like the Red List beside it. (The same
        // evening he asked why a Galerina paludosa said „Giftpilz": the DGfM
        // lists „Galerina spp." — the whole genus — and the footnote says so;
        // the species is on no list itself.)
        /* THE DGfM WRITES THE LAW AS SIGNS — „§", „§§, RL 3" — the German
           convention for the Federal Species Protection Ordinance. Spelled
           out, the sign kept in front so it is learnt: „§" alone under
           „Speisepilz" said nothing to anyone who did not know it already,
           and it says the one thing a collector must know. */
        const lawSigns = {
            '§': t('§ besonders geschützt (Bundesartenschutzverordnung)'),
            '§§': t('§§ streng geschützt (Bundesartenschutzverordnung)'),
        };
        const foodValue = new Set(filter.EDIBILITY_MARKS.map((k) => k.value));
        for (const mark of point.dgfm ?? []) {
            const remark = (mark.remark ?? '').split(', ').map((piece) => lawSigns[piece.trim()] ?? piece).join(', ');
            const footnote = [remark, mark.syndrome,
                mark.genus ? t('Aussage der DGfM über die ganze Gattung') : ''].filter(Boolean).join(' · ');
            row(t('DGfM'), filter.dgfmWord(mark), footnote, 'dgfm');
            if (foodValue.has(mark.list)) continue;
            const piece = document.createElement('span');
            piece.className = 'fact-dgfm';
            piece.textContent = filter.dgfmWord(mark);
            piece.title = footnote || t('Liste der DGfM');
            parts.push(piece.outerHTML);
        }

        fields.accuracy.innerHTML = parts.join(' · ');
        drawFactsTable(more);

        /* NO „ANGELEGT …" ANY MORE (18.10.2026: „Ist egal, wann das
           angelegt wurde. Die Fundzeit ist das Wichtige"). The day the record
           was made said nothing about the find and stood beside the find time
           as if it were a second date of it. */

        // The photos stand here and not in fillDetail() because they are no
        // input field: whoever is typing a note should still see that their
        // shot has come through. There is nothing to lose there.
        drawPhotoGallery(point);
        drawSequences(point);
    }

    /**
     * The chosen subjects as a request parameter — or nothing at all.
     *
     * NOTHING MEANS ALL, and that is deliberate: whoever has set nothing gets
     * every suggestion, and the server has the same default. Sending an empty
     * value instead would mean "no subjects" over there and would silently
     * switch the search off.
     */
    function speciesGroupParam() {
        // The field's groups (bindSpecies) — never empty here: a field without groups suggests nothing at all.
        return speciesGroupsNow?.length ? { groups: speciesGroupsNow.join(',') } : {};
    }

    /**
     * The custom fields of all lists the point lies in.
     *
     * Unified, not concatenated: if two lists both define a "Substrat", that is
     * ONE field — the value hangs on the name, not on the list. Otherwise the
     * same thing would stand there twice, and nobody would know which applies.
     */
    function extraFieldsFor(listIds) {
        const byNameMap = new Map();

        // THE FIND FIELDS FIRST: the fixed set of every species list (server,
        // includes/find_fields.php) stands before the custom fields — it is the
        // core of find mapping, the custom ones are the supplement. Entered
        // first, it also wins the name race in case an old custom field of the
        // same name still lies around somewhere.
        // For guests always: their finds lie in no list, and the find fields
        // are the whole reason they are here (Substrat, Menge, Beleg …).
        // A joint list (somebody else's, hung into a share with „gemeinsam
        // bearbeiten") brings its root's fields along with the share — the
        // same set its owner sees (list_fields_of_root).
        if (GUEST || collectionUi.isCollection()
                || listIds.some(id => listRoot(id)?.is_species || sharedLists.get(id)?.is_species)) {
            for (const f of start.find_fields ?? []) {
                byNameMap.set(f.name.toLowerCase(), f);
            }
        }

        for (const id of listIds) {
            // THE MAIN LIST ONLY. A subcategory defines no custom fields — it
            // is a name, not a second list. What is to be filled in stands once
            // at the very top and holds for the whole branch.
            for (const f of listRoot(id)?.fields ?? sharedLists.get(id)?.sharing?.fields ?? []) {
                const key = f.name.toLowerCase();
                if (!byNameMap.has(key)) byNameMap.set(key, f);
            }
        }
        /* AN AUFSAMMLUNG IS NO FIND: of the find fields only who collected
           it, the substrate and what grew around it — determiner, certainty,
           quantity and voucher belong to the species that grow from it. */
        if (collectionUi.isCollection()) {
            const keep = new Set(['finder', 'substrat / wirt', 'begleitvegetation']);
            return [...byNameMap.values()].filter(f => keep.has(f.name.toLowerCase()));
        }
        return [...byNameMap.values()];
    }

    /** They follow the list selection: one checkmark more, one field more. */
    /**
     * @param {object|null} values   What should stand in the fields; without it
     *                               what is already in there stays.
     * @param {Array|null} defaults  WHICH fields — without it those of the
     *                               checked own lists. A foreign point hands in
     *                               the fields of the share here: it lies in
     *                               none of my lists, and the tree above is
     *                               empty for it.
     */
    function drawExtraFields(values = null, defaults = null) {
        const chosen = [...fields.lists.querySelectorAll('input:checked')].map(b => Number(b.value));
        const previous = values ?? collectExtraFields();

        /* IN A SPECIES LIST THE TITLE IS THE SPECIES (24.08.2026) —
           and only the wording changes, not the field: it stays 'title',
           everywhere. Whoever ticks "Artenliste" says that the name of this
           point IS the find; "Titel des Punktes" would then be an invitation
           to write something else in. Redrawn from here, because this runs on
           every change to the list choice — the label follows the tick
           immediately, not only after reopening. Guests always: their finds
           are species finds, the whole reason they are here. */
        titleLabel();

        // The suggestion list may hang on a field that is about to vanish — and
        // would afterwards hang in nothingness.
        if (speciesTarget && extraHomes().some(home => home.contains(speciesTarget))) closeSpecies();

        /* WHATEVER CARRIES A VALUE GETS ITS FIELD — even when no chosen list
           provides for it any more (20.08.2026).

           Otherwise the value vanishes silently and is deleted on the next
           save: the block travels as a whole, drawn are only the fields of the
           chosen lists, and what is not drawn is missing from the block.
           Measured before it was fixed: "Artenliste" unticked on one list,
           then only the title changed and saved — finder, voucher number and
           companion trees were gone, without a word. The loss did not strike
           when the decision was made but some time later, without occasion.
           That is the worst form.

           Dragging the values along invisibly would have been the other
           option, and it is rejected: then the point would hold something
           nobody sees and nobody can delete. Visible also means editable —
           whoever really wants rid of the value empties the field.

           THIS BREAKS THE INHERITANCE FROM THE ROOT NODE, and on purpose:
           which fields a list has is otherwise said by the list alone. Here,
           as an exception, the point decides — but only for names that really
           hold something, and only as long as something is in them. */
        const shown = new Set((defaults ?? extraFieldsFor(chosen)).map(f => f.name));
        const orphaned = Object.entries(previous)
            .filter(([name, value]) => !shown.has(name)
                && String(Array.isArray(value) ? value.join('') : (value ?? '')).trim() !== '')
            // Multiline when the value is multiline — the original kind of the
            // field is recorded nowhere any more; the value itself says enough.
            .map(([name, value]) => ({
                name,
                // Always multiline: it is read, not typed, and a long value
                // cut off after one line would hide exactly what it is kept for.
                type: 'multiline',
                options: null,
                is_species: false,
            }));

        for (const home of extraHomes()) home.innerHTML = '';
        const orphanNames = new Set(orphaned.map(f => f.name));
        for (const f of [...(defaults ?? extraFieldsFor(chosen)), ...orphaned]) {
            const box = document.createElement('div');
            box.className = 'field';
            /* A VALUE WITHOUT A LIST FIELD STAYS THE POINT'S, AND EDITABLE
               (23.09.2026, after trying it locked for an hour: „die
               Felder hängen am Punkt, nicht an der Liste — die Liste definiert
               höchstens neue Felder, sie ist quasi die Maske"). After a move
               into a list without these fields — or into „Ohne Liste" — the
               point still carries finder, voucher, companions, and they stay
               writable as far as the rights reach. Whoever empties one lets
               the point fit its new list: an empty value without a field is
               not drawn again. Only the caption stays quiet, and one short
               line below says why these stand apart. */
            const orphan = orphanNames.has(f.name);
            if (orphan) box.classList.add('is-orphan');

            const elementId = 'dz-' + f.name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
            const label = document.createElement('label');
            label.setAttribute('for', elementId);
            /* Through t(): the STORED field name is the key — the standard
               fields (Finder, Bestimmungssicherheit …) translate, a user's
               own field name has no catalog entry and stays their word
               (found by 29.08.2026: the species-list labels
               stood German in the English app). */
            label.textContent = t(f.name);

            let input;
            /* A CHOICE WITH A SINGLE ANSWER IS A TICK („Beleg aufbewahrt",
               26.09.2026): the answer or nothing, drawn as a box with its words
               beside it. Stored is the answer itself („ja"), as a select would
               store it (collectExtraFields). */
            if (f.type === 'choice' && (f.options ?? []).length === 1) {
                input = document.createElement('input');
                input.type = 'checkbox';
                input.dataset.tick = f.options[0];
                if (f.reveals) input.dataset.reveals = f.reveals;
                input.checked = String(previous[f.name] ?? '').trim() !== '';
                input.addEventListener('change', () => markUnsaved(true));
                input.id = elementId;
                input.dataset.field = f.name;
                const words = document.createElement('span');
                words.textContent = t(f.name);
                const row = document.createElement('label');
                row.className = 'checkbox';
                row.append(input, words);
                box.classList.add('field-tick');
                box.append(row);
                (f.after === 'number' && fields.extraAfter ? fields.extraAfter : fields.extra).append(box);
                continue;
            }
            if (f.type === 'choice') {
                // A fixed set of values as a select, with the empty one on top.
                // IF THE POINT HOLDS A VALUE THAT NO LONGER EXISTS (the option
                // was removed in the meantime), it still appears as an entry —
                // otherwise the field would show empty and the next save would
                // silently throw the value away.
                input = document.createElement('select');
                const values = ['', ...(f.options ?? [])];
                const saved = String(previous[f.name] ?? '');
                if (saved !== '' && !values.includes(saved)) {
                    values.push(saved);
                }
                for (const value of values) {
                    const o = document.createElement('option');
                    o.value = value;
                    // Display through t(), the VALUE stays the stored word.
                    o.textContent = value === '' ? '—' : t(value);
                    o.selected = value === saved;
                    input.append(o);
                }
                input.addEventListener('change', () => markUnsaved(true));
            } else {
                input = document.createElement(f.type === 'multiline' ? 'textarea' : 'input');
                if (f.type !== 'multiline') input.type = 'text';
                input.maxLength = 500;
                // Which keyboard the phone opens first — 'numeric' on
                // "Anzahl / Menge" (find_fields.php says why). The field
                // stays text; the attribute only sorts the keys.
                if (f.inputmode) input.inputMode = f.inputmode;
                input.value = previous[f.name] ?? '';
                input.addEventListener('input', () => markUnsaved(true));

                // Suggestions when the field is marked for them. In a
                // multi-line field every line counts as a name of its own —
                // that way you enter companion plants one below the other and
                // get help for each.
                /* ITS OWN GROUPS WHERE IT HAS THEM (find_fields.php: „Substrat /
                   Wirt" plants, insects and fungi, „Begleitvegetation" plants —
                   12.11.2026); a field of one's own suggests what
                   the title does. */
                if (f.is_species) {
                    bindSpecies(input, () => true, () => f.species_groups ?? titleGroups());
                }
            }
            input.id = elementId;
            input.dataset.field = f.name;
            // One line to start with; autoheight grows it to the text.
            if (orphan && input.tagName === 'TEXTAREA') input.rows = 1;

            /* Odour, taste, spore print: the picker's button at the field's
               end, its panel under it (findtraits.js) — both gone where one
               may not write (pointwindow.css, .is-read). */
            if (f.picker && input.tagName === 'INPUT') {
                const pick = traitPicker(f.picker, input, iconHtml);
                const row = document.createElement('div');
                row.className = 'trait-row';
                row.append(input, pick.button);
                /* The spore print: its words in the field, its colour in a square
                   AFTER it, before the picker's button (15.11.2026:
                   „erst den Text, dann die Farbkachel") — the words are what
                   the row says, the colour shows it (findtraits.js). */
                if (f.picker === 'spore_print') {
                    const swatch = document.createElement('span');
                    swatch.className = 'spore-swatch';
                    input.after(swatch);
                    sporeFieldBind(input, swatch);
                }
                box.append(label, row, pick.panel);
            } else {
                box.append(label, input);
            }
            (f.after === 'number' && fields.extraAfter ? fields.extraAfter : fields.extra).append(box);
        }
        /* A TICK THAT REVEALS A FIELD — „Beleg aufbewahrt" before „Beleg-Nr."
           (26.09.2026: „erst wenn der Haken gesetzt ist, das Feld
           Belegnummer. Außer es ist ausgefüllt. Dann sollte aber auch der
           Haken gesetzt sein"). The field shows only while the tick is set; a
           number already there sets the tick — here, before the window takes
           its measure, so it counts as the state found, not as a change. On a
           foreign find the same holds, read as text. Unticking empties the
           number when saving (collectExtraFields).

           THE VOUCHER TAKES THE FIND NUMBER (29.09.2026: „wenn man
           ein eigenes Fundnummernsystem etabliert hat … gleich die
           Belegnummer vorbelegen mit der Fundnummer"). Setting the tick fills
           an EMPTY „Beleg-Nr." with the number the find field shows — minted
           by the pattern or typed by hand, never the „Y-" mark a find without
           one wears, which is no number of the user's. Only at the tick, the
           user's own act: a voucher number already there is his and stays,
           and a later change of the find number does not reach it. */
        for (const tick of extraAll('input[type="checkbox"][data-reveals]')) {
            const target = extraAll('[data-field]')
                .find((e) => e.dataset.field === tick.dataset.reveals);
            const box = target?.closest('.field');
            if (!box) continue;
            if (String(target.value ?? '').trim() !== '') tick.checked = true;
            const follow = () => { box.hidden = !tick.checked; };
            follow();
            tick.addEventListener('change', () => {
                follow();
                const number = fields.number.value.trim();
                if (tick.checked && number !== '' && String(target.value ?? '').trim() === '') {
                    target.value = number;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        }
        if (orphanNames.size > 0) {
            const note = document.createElement('p');
            note.className = 'hint-text small orphan-note';
            note.textContent = t('Diese Felder trägt nur der Punkt, seine Liste hat sie nicht. Leer gemacht, verschwinden sie.');
            fields.extra.append(note);
        }
    }

    function collectExtraFields() {
        const values = {};
        for (const e of extraAll('[data-field]')) {
            // A tick stores its answer or nothing — never the box's „on".
            // A spore print keeps its whole text beside the words it shows (findtraits.js, sporeFieldBind).
            values[e.dataset.field] = e.type === 'checkbox' ? (e.checked ? e.dataset.tick : '') : (e.dataset.stored ?? e.value);
        }
        // Unticked, the field it reveals goes with it: no voucher, no number.
        for (const e of extraAll('input[type="checkbox"][data-reveals]')) {
            if (!e.checked && e.dataset.reveals in values) values[e.dataset.reveals] = '';
        }
        return values;
    }

    // ---------------------------------------------------------------------------
    /**
     * The time for a new find: this device's clock.
     *
     * IT IS THE ONLY ONE THAT KNOWS. The server learns of the point only when
     * it is handed in — in a dead zone thus hours later, on the home WiFi. A
     * whole tour would then lie within one minute, precisely where mapping
     * happens.
     *
     * AND IT IS THE SAME FOR ALL POINTS. More hangs on that than it looks:
     * tour grouping, sorting and the order across changing reception reckon
     * with intervals, not with absolute times. They are therefore right even
     * when the clock runs wrong — as long as it runs equally wrong for all
     * points, and it does.
     *
     * A sync against the server time was built and is out again: it would only
     * have straightened the displayed date, but pulled a second clock into the
     * calculation that can itself run wrong. Whoever enters a find after the
     * fact changes the time by hand in the point window anyway.
     */
    function nowForFind() {
        const d = new Date();
        const zz = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${zz(d.getMonth() + 1)}-${zz(d.getDate())} `
             + `${zz(d.getHours())}:${zz(d.getMinutes())}:${zz(d.getSeconds())}`;
    }

    /**
     * What goes along as the find time when saving.
     *
     * THE FIELD KNOWS NO SECONDS. A datetime-local without a step attribute
     * shows only down to the minute; what the server sent as "14:23:11" stands
     * there as "14:23". Sending that back, the point would lose its seconds on
     * EVERY save — even if nobody touched the field. For finds of the same
     * minute, that is exactly the digit their order hangs on.
     *
     * Therefore: if the field still holds the same as at load time, the saved
     * value including seconds applies. Only when somebody has really entered
     * something else does the field count.
     *
     * DO NOT DELETE, EVEN IF IT LOOKS SMALL. Exactly that happened once: when
     * dismantling the server time sync (d4dc80d) the definition went along,
     * the call site stayed — and with that the saving of every point was
     * silently broken, without an error message, because the call stands
     * BEFORE the sending. It never had anything to do with the time sync.
     */
    function foundForSaving(point) {
        const inField = fields.found.value;
        if (!inField) return null;
        return inField === forField(point.found_at) ? point.found_at : inField;
    }

    /**
     * MySQL timestamp into what a datetime-local field expects.
     *
     * "2026-08-10 14:23:11" becomes "2026-08-10T14:23". The seconds drop away
     * because without a step attribute the field does not display them at all —
     * if they were in there, the value would be unreadable for the browser and
     * the field would stay empty.
     */
    function forField(mysqlTime) {
        if (!mysqlTime) return '';
        const t = String(mysqlTime).replace(' ', 'T');
        return t.length >= 16 ? t.slice(0, 16) : '';
    }

    /** The voucher-photo half of that question, per POINT rather than per
     *  picture — the statistics sidebar asks it for photos that are not the
     *  one in the window, and the answer is the same for all of a find's. */
    function mayEditPointPhotos(right = 'measure') {
        const foreign = foreignInWindow();
        if (foreign === null) return true;
        // A co-owner tends the find's pictures — his own; the others' the
        // server refuses by their photographer (scaleMayEdit).
        if (foreign.co_owned) return true;
        if (foreign.excursion_id != null && foreign.editable) return true;
        /* THREE RIGHTS SINCE THE LIST SHARES (08.10.2026): storing
           measures and names is `measure`, attaching and arranging is
           `attach_photos` — until then both rode on „Belegbilder löschen", and
           an old box still hands its holders all three (rights_compat). */
        return sharedRights(foreign).has(right);
    }

    document.getElementById('d-close').addEventListener('click', closeDetail);

    /**
     * Take back a save the server has rejected.
     *
     * THE INPUT MUST NOT GET LOST IN THE PROCESS, and exactly that would be
     * the obvious mistake: on the phone the window is long closed, the stock
     * holds the old state again — and what somebody wrote exists nowhere
     * anymore. So the point is opened again and the rejected version put back
     * into the fields, marked as unsaved. Then the message stands next to it
     * and one can try again.
     *
     * The most frequent reason for this is not wrong input but an expired
     * session — and that is settled after logging in again.
     */
    function revertSave(id, before, change, error) {
        state.points.set(id, before);
        computeListCounts();
        drawMarker();
        drawList();

        selectPoint(id);
        fields.title.value = change.title;
        fields.note.value = change.note;
        fields.coord.value = `${change.lat.toFixed(6)}, ${change.lon.toFixed(6)}`;
        fields.found.value = forField(change.found_at);
        if ('find_number' in change) fields.number.value = change.find_number ?? '';
        for (const box of fields.lists.querySelectorAll('input')) {
            box.checked = change.lists.includes(Number(box.value));
        }
        drawExtraFields(change.extra ?? {});
        // The outline goes back into the draft too: it is the most laborious
        // input of all — walking around the same stand a second time is nothing
        // one may impose on somebody because of an expired session.
        host.outlineDraft = change.outline ?? null;
        drawOutlineRow(host.outlineDraft);
        // selectPoint() further up still drew with the saved outline.
        drawMarker();
        markUnsaved(true);

        status(t('Speichern fehlgeschlagen: %s', error.message), true);
    }

    /** Has the number field been changed by hand since the window was filled? */
    function numberTyped() {
        if (host.lastFilled.state === null || host.lastFilled.id !== state.selectedId) return true;
        return fields.number.value !== JSON.parse(host.lastFilled.state).number;
    }

    /** Points whose save is currently under way — against the second tap. */
    const savingNow = new Set();

    /**
     * Save what the window holds — true when it is saved (or on its way in
     * the queue), false when it is not and the input still stands in the
     * window. A FUNCTION, NOT ONLY THE BUTTON'S HANDLER, since the question
     * before leaving (leaveOk) offers „Speichern" as one of its answers and
     * has to know whether it may go on.
     */
    /**
     * QUIET: the Fundbrowser's autosave (13.10.2026: „den
     * Speicher-Button abschaffen, da man jetzt aktiv alles bewusst macht").
     * The same save, without what belongs to a press of the button: no
     * „Gespeichert." toast every second, no window that steps aside, and —
     * above all — no forced refill of the fields, which would put the caret
     * of whoever is still typing back to the end. The fields stay as they
     * are; only the comparison state learns that they are saved now. A save
     * already on its way is no error here: the caller simply tries again.
     */
    async function saveDetail({ quiet = false } = {}) {
        // A foreign point goes its own way: no waiting for a real id (it always
        // has one), no queue, no taking back — and only the fields the share
        // allows.
        const foreign = foreignInWindow();
        if (foreign) { return saveForeign(foreign, quiet); }
        let saved = true;

        /* NO MORE SILENT EXIT. Two bare `return` stood here, and both leave
           the window standing open without a word. Whoever presses save and
           sees nothing happen presses again, then closes and takes the input
           for saved — exactly like that, ten points came back from one tour
           without their titles (20.08.2026).

           Whether it really was this spot is not proven. But a save button
           that CAN stay without consequence and not say so is wrong
           regardless: as long as it keeps quiet, every cause looks the same,
           and none can be told from the next. */
        /* NOT ONE await BEFORE THE FORM HAS BEEN READ, and that is the whole
           lesson of 21.08.2026.

           Here stood `await pointReady(...)`, which waits for the create of
           this point to come back from the server. In the woods it does not
           come back: fetch had no deadline, so a dead line kept the promise
           pending for minutes. The listener hung on its very first line —
           the window did not close, the button went quiet, and the typed
           title stayed in the field and nowhere else. Measured on 21.08.2026:
           after Save the queue held zero entries and no PATCH had been
           attempted. Whoever then gave up and closed the window had the
           point created with title = '' and the name gone.

           There is nothing to wait for either. A change to a point whose
           create is still on the line goes into the queue under the
           provisional number and gets the real one when the create goes
           through (api.patch, queue.replaceKey). The waiting was never the
           point — the id was, and the queue has known how to do that all
           along. */
        const point = state.points.get(state.selectedId);
        if (!point) {
            status(t('Dieser Punkt ist nicht mehr da — die Eingabe wurde nicht gespeichert. Bitte den Text herauskopieren, bevor du das Fenster schließt.'), true);
            return false;
        }
        // Tapped twice used to send the same thing twice. Harmless, but
        // needless — and on a bad line the second waits behind the first.
        if (savingNow.has(point.id)) {
            if (!quiet) status(t('Wird noch gespeichert — einen Augenblick.'));
            return false;
        }

        /* FROM HERE TO THE CLOSE, EVERYTHING RAN WITHOUT A SAFETY NET.
         *
         * The try further down catches only the sending. Everything before it
         * — collecting the fields, reading the find-time field, fetching the
         * outline, computing the differences, redrawing — stood bare. If one
         * of those lines throws, the listener breaks off: the window stays
         * open, nothing happens, and nothing is said, for a burst promise in
         * an event listener lands only in the console.
         *
         * That is the last explanation fitting the maintainer's tour of 20.08.2026
         * that I could not rule out: title entered, saved, window stayed
         * open, saved once more, closed — and on the server the point stood
         * without its title.
         *
         * Proven it is not. But as long as this path CAN keep quiet, the
         * question can never be answered, and the input is gone every time.
         * Now it says what happened and leaves the written text standing as
         * unsaved instead of taking it along. */
        try {

        const coord = readCoordinates(fields.coord.value);
        if (!coord) {
            status(t('Die Koordinaten sind nicht lesbar. Erwartet wird "51.123456, 7.654321".'), true);
            return false;
        }

        const lists = [...fields.lists.querySelectorAll('input:checked')].map(b => Number(b.value));

        const extra = collectExtraFields();

        // A reference only holds as long as its name really still stands in the
        // field. Whoever types on after choosing means something else — and a
        // reference to a taxon whose name no longer stands next to it would be
        // worse than none.
        //
        // Comparison is entry by entry, with the same separators as the
        // suggestion list: whoever writes three trees one below the other and
        // deletes the middle one again should keep the other two.
        const sources = {};
        for (const [field, choices] of Object.entries(host.speciesSources)) {
            const standing = new Set(
                String(extra[field] ?? '').split(SPECIES_SEPARATOR).map(s => s.trim()).filter(Boolean)
            );
            const matching = {};
            for (const [name, source] of Object.entries(choices)) {
                if (standing.has(name)) matching[name] = source;
            }
            if (Object.keys(matching).length > 0) sources[field] = matching;
        }

        const change = {
            title: fields.title.value,
            note: fields.note.value,
            extra: extra,
            extra_sources: sources,
            lat: coord[0],
            lon: coord[1],
            lists: lists,
            // An empty field explicitly means "no find time", not "not sent
            // along" — the server distinguishes the two.
            found_at: foundForSaving(point),
            // Always sent along, even unchanged: the server distinguishes "not
            // included" from "null", and "null" there means remove. Whoever has
            // taken the outline away must really be rid of it.
            outline: outlineNow(point),
            // Only where the field stands — one's own find. Empty: no number of
            // one's own (differences() sends it only when it changed).
            /* AND ONLY WHEN IT WAS TYPED IN: the second guard of 28.09.2026
               (fillDetail says the first). differences() compares with the
               point as the device knows it now, and that may hold a number the
               field never showed — a field nobody touched must never answer
               for it, least of all with „none". */
            ...(fields.numberField.hidden || !numberTyped() ? {} : {
                find_number: normalizeFindNumber(start.findNumber?.pattern, fields.number.value.trim()) || null,
            }),
            // The culture's days or the determination day (collectionui.js).
            ...collectionUi.collect(point),
        };

        // LET IT TAKE EFFECT FIRST, THEN SEND — as when creating.
        //
        // On the phone the window closes after saving. Previously that only
        // happened after the answer, and so out in the woods you hung for
        // seconds in front of a window that would not close.
        //
        // The point here has long had a real id; so it is only the change that
        // runs ahead. And the server only checks the coordinates on it — which
        // the browser already checked itself above. A rejection is thus almost
        // ruled out; for the rest the take-back stands ready.
        const before = { ...point };

        // ONLY THE TOUCHED FIELDS GO TO THE SERVER, with their time. As long as
        // only one device wrote, the full form was fine; with two it would
        // overwrite foreign changes with one's own old state — see
        // fieldtimes.js. Only once the server has the column (migration 0039):
        // a partial extra against the old server REPLACED the whole block
        // there.
        const packet = start.fieldTimesReady
            ? differences(before, change, new Date().toISOString())
            : { data: change, times: null };

        state.points.set(point.id, { ...point, ...change,
            ...(packet.times ? { field_times: packet.times } : {}) });
        markUnsaved(false);
        computeListCounts();
        // Forced: what stands here is the version just entered. Quiet, the
        // fields are left alone and only counted as saved (see the head).
        if (quiet) host.lastFilled = { id: point.id, state: detailState() };
        else fillDetail(state.points.get(point.id), true);
        drawMarker();
        drawList();
        if (!quiet) {
            closeDetailAfterSave();
            status(t('Gespeichert.'));
        }

        // Nothing touched? Then there is nothing to send — and nothing the
        // server could say about it. "Gespeichert." is true regardless.
        if (packet.data === null) return true;

        savingNow.add(point.id);
        try {
            const fresh = await api.patch('points', point.id, packet.data);

            // What the server has added — timestamp, converted height.
            state.points.set(fresh.id, { ...state.points.get(point.id), ...fresh });
            drawMarker();
            drawList();

            if (fresh.deleted_at) {
                // The point has meanwhile been deleted on another device. The
                // change is accepted nonetheless — into the trash, on purpose
                // (see punkt_aendern) —, but the drawing just now took the
                // marker away. Without this word, "Gespeichert." followed by a
                // vanishing point looks like an error you can search for
                // nowhere.
                if (state.selectedId === fresh.id) clearSelection();
                computeListCounts();
                drawList();
                status(t('Der Punkt liegt inzwischen im Papierkorb — die Änderung ist dort mit dabei.'), true);
            } else {
                // Only if the window still shows this point, and then without
                // force: whoever has written on in the meantime should keep it.
                /* THE AUTOSAVE'S ANSWER NEVER WRITES INTO THE FIELDS
                   (18.10.2026: „das Auto-Speichern verschluckt
                   beim Schreiben in der Notiz … Leerzeichen oder vielleicht
                   sogar ganze Buchstaben"). The server trims what it keeps,
                   so a note ending in the space just typed came back
                   without it, and the refill — allowed because nothing was
                   typed DURING the round trip — set it into the field: the
                   space was gone, and the caret jumped to the end, so a
                   letter typed into the middle landed elsewhere. At the
                   button this refill is right (what stands is what was
                   kept); a second after the last key it is the user's text
                   being overwritten under his fingers. Quiet, only the read-
                   only details and a minted number are taken over. */
                if (state.selectedId === fresh.id) fillDetail(state.points.get(fresh.id), false, quiet);

                if (fresh.pending) status(t('Wird übertragen, sobald Verbindung da ist.'));
            }

            // Maybe a version from this device has just lost — then the sidebar
            // should say so, not only the next page load.
            countConflicts();
        } catch (e) {
            revertSave(point.id, before, change, e);
            saved = false;
        } finally {
            savingNow.delete(point.id);
        }

        } catch (e) {
            /* NO revertSave HERE. That puts the stock back to what it was
               before saving and writes the input into the fields again — but
               for that it needs `before` and `change`, and at this point
               those cannot exist yet. What stands in the window is still
               standing there anyway; it simply stays and is marked
               unsaved. */
            console.error('Speichern abgebrochen:', e);
            markUnsaved(true);
            status(t('Speichern nicht möglich: %s — die Eingabe steht noch im Fenster.',
                e?.message || t('unbekannter Fehler')), true);
            return false;
        }
        return saved;
    }

    document.getElementById('d-save').addEventListener('click', () => { saveDetail(); });

    document.getElementById('d-center').addEventListener('click', () => {
        const point = state.points.get(state.selectedId)
            ?? state.sharedPoints.get(state.selectedId);
        if (!point) return;

        // A deliberate jump elsewhere — the location must not pull the map
        // back (see pauseFollowing).
        pauseFollowing();

        // For an area bring the whole into view, do not zoom to the center. At
        // level 17 you would see a cutout of a survey area and not even know in
        // which direction the rest lies.
        const outline = outlineNow(point);
        if (polygon.isUsable(outline)) {
            fitVisible(polygon.bounds(outline));
            if (isPhone()) moveMode(true);
            return;
        }

        // To the spot in the field, not the saved one: whoever has already
        // dragged the marker and then presses "Zeigen" wants to go where the
        // marker now stands — not where it once stood.
        const where = readCoordinates(fields.coord.value) ?? [point.lat, point.lon];
        centreVisible(where, Math.max(map.getZoom(), 17));

        // On the desktop the window stands next to the map, there centering
        // suffices. On the phone it covers everything — then it steps aside.
        if (isPhone()) moveMode(true);
    });

    document.getElementById('d-delete').addEventListener('click', async () => {
        // A foreign point lands in ITS OWNER'S trash — different text,
        // different way back.
        const foreign = foreignInWindow();
        if (foreign) { await deleteForeign(foreign); return; }

        const point = await pointReady(state.points.get(state.selectedId));
        if (!point) return;
        /* IN A JOINT LIST THE HOLDER'S BUCKET IS THE LIST'S TOO (step 2,
           22.09.2026): he takes the filing out like every member, or the point
           as a whole — but never silently past the group. */
        if (jointListsOf(point).length > 0) {
            const choice = await jointDeleteChoice(point, true);
            if (choice === null) return;
            try {
                const response = await api.remove('points', point.id, choice.query);
                const gone = choice.whole || response?.whole === true;
                if (gone) {
                    if (point.id < 0) { await photos.forgetPoint(point.id); galleryOriginals.forgetPoint(point.id); }
                    state.points.delete(point.id);
                    clearSelection();
                } else {
                    const removed = new Set(response?.removed_from ?? choice.lists.map(l => l.id));
                    point.lists = point.lists.filter(id => !removed.has(id));
                    fillDetail(point, true);
                    drawMarker();
                }
                computeListCounts();
                drawList();
                status(response?.pending ? t('Gelöscht — wird übertragen, sobald Verbindung da ist.') : choice.sentence);
            } catch (e) {
                status(t('Löschen fehlgeschlagen: %s', e.message), true);
            }
            return;
        }
        /* THE DOOR, NOT THE BUCKET, when others own the find too (11.09.2026):
           the server hands it to the most senior co-owner and puts me in my
           trash; the sentence says to whom, so nobody reads „Papierkorb" and
           expects the find to lie there. */
        const heir = (point.co_owners ?? [])[0] ?? null;
        /* AN AUFSAMMLUNG TAKES ONE'S OWN SPECIES FROM IT ALONG (0174,
           collection_species_leave) — said before, not discovered after. */
        const withSpecies = point.is_collection
            ? ' ' + t('Die Arten, die du daraus bestimmt hast, gehen mit in deinen Papierkorb.') : '';
        const yes = await confirm(heir !== null ? {
            title: t('Fund abgeben?'),
            text:  t('„%s" geht an %s weiter — du steigst aus und kannst aus deinem Papierkorb zurück.', point.title || t('Ohne Titel'), heir) + withSpecies,
            button: t('Abgeben'),
            danger: true,
        } : {
            title: t('In den Papierkorb legen?'),
            text:  t('„%s" wandert in den Papierkorb und lässt sich von dort zurückholen.', point.title || t('Ohne Titel')) + withSpecies,
            button: t('In den Papierkorb'),
            danger: true,
        });
        if (!yes) return;

        try {
            const response = await api.remove('points', point.id);

            // Waiting photos for a point that never existed on the server.
            //
            // The trash does not help here: the queue cancels the creation and
            // the deletion against each other, and this id will never become a
            // real one. The images would then wait forever — at worst, until an
            // entirely different point happens to get the same number.
            //
            // A point with a real id keeps its waiting photos: it merely lies
            // in the trash and can be brought back.
            if (point.id < 0) { await photos.forgetPoint(point.id); galleryOriginals.forgetPoint(point.id); }

            state.points.delete(point.id);
            clearSelection();
            computeListCounts();
            drawList();
            status(response?.pending
                ? t('Gelöscht — wird übertragen, sobald Verbindung da ist.')
                : (heir !== null ? t('Abgegeben an %s — der Fund liegt in deinem Papierkorb.', heir)
                                 : t('In den Papierkorb gelegt.')));
        } catch (e) {
            status(t('Löschen fehlgeschlagen: %s', e.message), true);
        }
    });

    /* THE PENCIL OPENS THE COORDINATE FIELD FOR ONE ENTRY (22.09.2026): read
       only otherwise — see the markup. Opened, it is selected, so a pasted
       coordinate replaces the old one in one go; fillDetail() closes it
       again with the next find. */
    document.getElementById('d-coord-edit').addEventListener('click', () => {
        fields.coord.readOnly = false;
        fields.coord.focus();
        fields.coord.select();
    });

    document.getElementById('d-coord-copy').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(fields.coord.value);
            status(t('Koordinaten kopiert.'));
        } catch {
            // Without clipboard permission: at least select it, then Ctrl+C works.
            fields.coord.select();
            status(t('Kopieren nicht erlaubt — Text ist markiert.'), true);
        }
    });

    /** Read "51.123456, 7.654321" or "51,123456 7,654321". */
    function readCoordinates(text) {
        const numbers = String(text).match(/-?\d+(?:[.,]\d+)?/g);
        if (!numbers || numbers.length < 2) return null;
        const lat = parseFloat(numbers[0].replace(',', '.'));
        const lon = parseFloat(numbers[1].replace(',', '.'));
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) return null;
        if (lat < -90 || lat > 90 || lon < -180 || lon > 180) return null;
        return [lat, lon];
    }

    /**
     * What the share allows me, in one line.
     *
     * SUMMARISED, NOT ENUMERATED (20.08.2026). The administration
     * lists every single switch, because there one GRANTS them and has to hit
     * each one. Here one only wants to know whether it is worth opening a
     * point at all — and for that "Titel, Notizen" is the answer, while a
     * column of seven ticked and unticked boxes is a form to read.
     *
     * The fields are counted rather than named: they are a set of their own
     * per list, and their names say nothing to somebody who does not have
     * them in front of him. Mirrors share_rights_readable() in PHP, only from
     * the other side — there it says "die anderen", here "du".
     */
    function rightsReadable(rights) {
        const list = [...(rights ?? [])];
        if (list.length === 0) return t('Du darfst diese Punkte nur ansehen.');

        /* TWO SORTS, AND THEY DO NOT FIT IN ONE SENTENCE. "Titel" and
           "Notizen" are things one EDITS; moving, deleting and removing
           pictures are things one DOES. Hung on one verb it came out as "Du
           darfst verschieben, löschen bearbeiten" — so the sentence is built
           from two halves, and each half only appears when it holds
           something.

           Fields are counted, not named: they are a set of their own per
           list, and their names say nothing to somebody who does not have
           them in front of him. */
        const edit    = [];
        const onPoints = [];
        let pictures  = false;
        let fields    = 0;

        for (const r of list) {
            if (r === 'title')                 edit.push(t('Titel'));
            else if (r === 'note')            edit.push(t('Notizen'));
            else if (r === 'move')             onPoints.push(t('verschieben'));
            else if (r === 'delete')           onPoints.push(t('löschen'));
            else if (r === 'delete_photos')  pictures = true;
            else if (String(r).startsWith('field:')) fields++;
        }
        if (fields > 0) edit.push(fields === 1 ? t('1 Feld') : t('%d Felder', fields));

        /** A, B und C — the last one joined with "und", not with a comma. */
        const join = (parts) => parts.length < 2
            ? (parts[0] ?? '')
            : parts.slice(0, -1).join(', ') + ' ' + t('und') + ' ' + parts.at(-1);

        /* "Punkte" ONCE, not per verb. Both actions hang on the same object,
           and "Punkte verschieben und Punkte löschen" says the word twice for
           no gain. Deleting pictures is a third thing and stands for itself,
           joined with a comma — a second "und" in one sentence reads like a
           list that has lost count. */
        const does = [];
        if (onPoints.length) does.push(t('Punkte %s', join(onPoints)));
        if (pictures)        does.push(t('Bilder löschen'));

        const halves = [];
        if (edit.length) halves.push(t('%s bearbeiten', join(edit)));
        if (does.length) halves.push(does.join(', '));

        return t('Du darfst %s.', halves.join(', '));
    }

    /** What a list share lets me do — every tick by name, in the words of the
     *  owner's table, from my side. */
    function listShareRightsReadable(c, rights) {
        const list = new Set(rights ?? []);
        const words = [];
        if (list.has('own_points')) words.push(t('eigene Punkte hineinsetzen'));
        if (list.has('title')) words.push(t('den Titel ändern'));
        if (list.has('note')) words.push(t('die Notiz ändern'));
        const fields = [...list].filter(r => String(r).startsWith('field:')).map(r => r.slice(6));
        if (fields.length > 0) words.push(fields.length === 1 ? t('das Feld „%s" ausfüllen', fields[0]) : t('die Felder %s ausfüllen', fields.map(f => `„${f}"`).join(', ')));
        if (list.has('move')) words.push(t('Punkte verschieben'));
        if (list.has('attach_photos')) words.push(t('Bilder anhängen'));
        if (list.has('delete_photos')) words.push(t('Bilder löschen'));
        if (list.has('measure')) words.push(t('Bilder vermessen'));
        if (list.has('edit_photos')) words.push(t('Bilder bearbeiten'));
        if (list.has('delete')) words.push(t('Punkte in den Papierkorb legen'));
        return words.length === 0
            ? t('%s zeigt dir die Liste nur zum Ansehen.', shortNameOf(c.owner))
            : t('%s erlaubt dir: %s.', shortNameOf(c.owner), words.join(', '));
    }

    /** Whether a foreign point lies in a joint list — then its members are the holder's equals. */
    function jointPoint(point) {
        return (point?.lists ?? []).some(id => sharedLists.get(id)?.sharing?.joint === true);
    }

    /** The joint lists a point lies in, as this device knows them. */
    function jointListsOf(point) {
        return (point?.lists ?? []).map(id => sharedLists.get(id)).filter(l => l?.sharing?.joint === true);
    }

    /**
     * THE BUCKET OF A POINT IN A JOINT LIST (step 2, 22.09.2026, option A):
     * it takes the point's FILING out of the list into the list's trash,
     * where every member can put it back. The choice — which list, all of
     * them, or (the holder alone) the point as a whole — is put here, and
     * the answer travels as query parameters of the DELETE.
     *
     * @return ?{query: string, lists: object[], whole: boolean, sentence: string}
     *         null when the person went back
     */
    async function jointDeleteChoice(point, holder) {
        const lists = jointListsOf(point);
        const name = point.title || t('Ohne Titel');
        const perList = (l) => ({
            value: 'list:' + l.id,
            label: t('Aus „%s" nehmen', l.name),
            hint: t('in den Papierkorb der Liste — jedes Mitglied holt ihn dort zurück'),
        });
        const options = lists.map(perList);
        if (lists.length > 1) {
            options.push({ value: 'all', label: t('Aus allen gemeinsamen Listen nehmen'),
                hint: t('in jeden Papierkorb dieser Listen') });
        }
        if (holder) {
            options.push({ value: 'whole', label: t('Ganz in den Papierkorb'),
                hint: t('aus den gemeinsamen Listen heraus und in deinen eigenen Papierkorb') });
        }
        let picked;
        if (options.length === 1) {
            const ok = await confirm({
                title: t('Aus der Liste nehmen?'),
                text:  t('„%s" geht aus „%s" in den Papierkorb der Liste — jedes Mitglied kann ihn dort zurückholen. Die Gruppe liest davon im Chat.', name, lists[0].name),
                button: t('Herausnehmen'),
                danger: true,
            });
            picked = ok ? options[0].value : null;
        } else {
            picked = await choose({
                title: t('„%s" löschen?', name),
                text:  t('Der Punkt liegt in einer gemeinsamen Liste. Was herausgenommen wird, landet im Papierkorb der Liste, und die Gruppe liest davon im Chat.'),
                options,
            });
        }
        if (picked === null || picked === undefined) return null;
        if (picked === 'whole') {
            return { query: '&whole=1', lists, whole: true,
                sentence: t('In den Papierkorb gelegt — aus den gemeinsamen Listen heraus.') };
        }
        if (picked === 'all') {
            return { query: '&all=1', lists, whole: false,
                sentence: t('Aus allen gemeinsamen Listen genommen — die Gruppe holt ihn im Papierkorb der Liste zurück.') };
        }
        const one = lists.find(l => 'list:' + l.id === picked);
        return { query: '&list=' + one.id, lists: [one], whole: false,
            sentence: t('Aus „%s" genommen — die Gruppe holt ihn im Papierkorb der Liste zurück.', one.name) };
    }

    /** The foreign point the window currently shows — otherwise null. */
    function foreignInWindow() {
        return foreignPoint(state.selectedId);
    }

    /** The shared list through which this point arrives at my end. */
    function sharedOriginList(point) {
        return point.lists.map(id => sharedLists.get(id)).find(Boolean) ?? null;
    }

    /**
     * The extra fields of a foreign point — from the share, not from my lists.
     * They stand in the order of the sharer's root list.
     *
     * is_species is dropped: the suggestions from the name database hang on my
     * species lists, and a foreign field is none of them.
     */
    function foreignFields(point) {
        /* AN EXCURSION FIND CARRIES THE FULL SPECIES SET
           (27.08.2026: „Exkursionen sind ja immer Artenlisten — da fehlen die
           ganzen Artenlistenfelder, die ich nachträglich ergänzen will").
           Until now the window showed only what was already filled in: the
           fields of a foreign point come from its SHARE, and a guest find has
           none, so the list below came back empty.

           The set is the same fixed one the guest himself had when creating
           (excursion_fields_sift sifts against exactly these names), delivered
           with the document as start.find_fields. And it keeps its is_species
           marks — unlike the container branch below, where they are removed
           on purpose: there the suggestions would hang on MY lists, here the
           excursion IS the species list. */
        if (point.excursion_id != null && point.editable) {
            return start.find_fields ?? [];
        }
        // A find shown to me arrives through no share — it brings its root's
        // fields along (point_hints_points), so its Substrat can be read.
        return (sharedOriginList(point)?.sharing?.fields ?? point.hint_fields ?? [])
            .map(f => ({ ...f, is_species: false }));
    }

    /** The values for them, as text — a multi-valued field arrives as a list. */
    function foreignExtraValues(point) {
        const values = {};
        for (const [name, value] of Object.entries(point.extra ?? {})) {
            values[name] = Array.isArray(value) ? value.join(', ') : (value ?? '');
        }
        return values;
    }

    /**
     * Adjust the window to the rights — on EVERY fill, even for an own point.
     *
     * FOR THE OWN ONE TOO, and that is the reason this function does not stand
     * only in the foreign branch: otherwise the lock would remain when
     * switching from a foreign point to an own one — and you would look for
     * the fault in your own list. Every line here sets both cases, none only
     * one.
     */
    function applyDetailRights(point) {
        const foreign  = foreignPoint(point.id);
        const rights = foreign ? sharedRights(foreign) : null;
        // On an own point everything is allowed — then every question is
        // answered with yes, and the lines below read the same for both cases.
        const may   = (right) => rights === null || rights.has(right);

        const origin = document.getElementById('d-origin');
        if (foreign) {
            const list = sharedOriginList(foreign);
            origin.textContent = list
                ? t('von %s · %s → %s', foreign.owner, list.container.name, list.name)
                : (foreign.hint_from != null ? t('von %s · direkt geteilt', foreign.owner) : t('von %s', foreign.owner));
        }
        origin.hidden = !foreign;

        fields.title.disabled = !may('title');
        fields.note.disabled = !may('note');
        fields.coord.disabled = !may('move');
        document.getElementById('d-coord-edit').hidden = !may('move');

        // THERE IS NO RIGHT FOR THE FIND TIME (SHARE_RIGHTS in
        // container.php knows only move, delete, title, note,
        // delete_photos and the fields). A field whose input the server would
        // discard anyway therefore stays closed — visibly closed, so the time
        // can still be read off.
        // IN A JOINT LIST IT IS OPEN (22.09.2026): the members are the
        // holder's equals there, and the server writes as the holder.
        fields.found.disabled = foreign !== null && !jointPoint(foreign);

        for (const e of extraAll('[data-field]')) {
            e.disabled = !may('field:' + e.dataset.field);
        }
        /* A SPECIES FROM AN AUFSAMMLUNG READS ITS FINDER AND ITS PLANTS AROUND
           as the Aufsammlung has them (18.10.2026: „gar nicht
           editierbar, sondern strikt übernommen") — plain text, as every field
           one may not write (detailReadingLook); the Aufsammlung is where they
           are changed, and its species follow (collection_sync). */
        if (point.collection_id != null && !point.is_collection) {
            for (const e of extraAll('[data-field]')) {
                if (COLLECTION_COPIED_FIELDS.includes(e.dataset.field)) e.disabled = true;
            }
        }

        /* The list choice belongs to the owner: in which of his lists his point
           lies is his order, not mine. The whole block goes away instead of
           standing there locked — an empty tree would be no information but a
           question.

           AND FOR A GUEST ALWAYS (24.08.2026), for the same reason
           read the other way round: he has no lists at all, so the block would
           be an empty question above his own find. It used to be hidden once
           at startup — and this line, which runs at EVERY opening, put it back
           for his own points. Whoever writes the state on every pass must
           carry every reason for it; a rule set somewhere else does not
           survive here. */
        // A co-owned find shows the tree too — MY tree, for MY filing.
        fields.lists.closest('.field').hidden = GUEST || (foreign !== null && !foreign.co_owned);

        // Viewing photos yes, adding no: a voucher belongs to the find somebody
        // made, and whoever was there has a point of their own. If the foreign
        // point has none at all, the whole block goes away — without the
        // buttons the bare word "Fotos" would otherwise stand there above
        // nothing.
        /* BY THE ID AND NO LONGER BY BEING SIBLINGS. Here stood
           „#d-photos ~ .photo-buttons", and that was right as long as the
           buttons stood UNDER the gallery. Since 10.09.2026 they stand above
           it — the selector would have found nothing any more and the line
           would have died on a null. An id survives a rearrangement, a
           neighbourhood does not. */
        /* ON A FOREIGN FIND TOO, when one may tend it
           (11.09.2026, co-ownership): the leader attaches their micrograph to
           a guest's find — the same door that already opens scale and
           deleting there. The server checks the same once more
           (point_attach_scope). */
        const mayAttach = foreign === null || mayEditPointPhotos('attach_photos');
        document.getElementById('d-photo-buttons').hidden = !mayAttach;
        // The sequences go through the same door (includes/sequences.php).
        /* AND ONLY ON A FIND (02.10.2026: „nur bei Punkten, die
           auch in einer Artenliste liegen … und keine Parkplätze oder
           Campingplätze"): a sequence is a living thing's, and the species
           branch is what says a point is one. Whatever already hangs on a
           point stays visible — it left its species list, its voucher did
           not vanish with that. The server refuses the same (sequence_create). */
        /* NOR ON AN AUFSAMMLUNG (15.11.2026): the substrate is
           no organism — the sequence goes to the species find that grew
           from it. One already stored there stays in sight (the rule
           above), so nothing disappears. */
        const maySequence = mayAttach && pointIsFind(point) && !point.is_collection;
        const sequencesHeld = (point.sequences ?? foreign?.sequences ?? []).length;
        host.sequenceFields.add.hidden = !maySequence;
        host.sequenceFields.field.hidden = !maySequence && sequencesHeld === 0;
        detailPhotoTab();
        if (start.photos !== false) {
            photoFields.gallery.closest('.field').hidden =
                !mayAttach && (foreign.photos?.length ?? 0) === 0;
        }

        // Drawing and removing an outline is geometry, and since 14.08.2026
        // the move right covers that — marker and area alike, it is the same
        // object. Without the right the row above stays pure information.
        outlineFields.draw.hidden = foreign !== null && !may('move');
        if (foreign && !may('move')) outlineFields.remove.hidden = true;

        /* BUCKET OR DOOR (11.09.2026). The bucket means the find ceases; the
           door means I step out and it carries on — a co-owned find always
           goes on without me, and my own find goes on with its co-owners.
           The symbol says which farewell this is before anyone presses it. */
        const ownPoint = foreign === null ? state.points.get(state.selectedId) : null;
        const door = foreign !== null ? !!foreign.co_owned : (ownPoint?.co_owners?.length ?? 0) > 0;
        const trashButton = document.getElementById('d-delete');
        trashButton.hidden = foreign !== null && !foreign.co_owned && !may('delete');
        trashButton.replaceChildren(icon(door ? 'sign-out' : 'delete'));
        trashButton.title = door
            ? (foreign !== null ? t('Aussteigen') : t('Abgeben'))
            : t('Punkt löschen');
        trashButton.setAttribute('aria-label', trashButton.title);

        // Not a single field operable? Then it is a reading window, and a save
        // button that can save nothing does not belong in it.
        const editable = !foreign || may('title') || may('note') || may('move')
            || extraAll('[data-field]').some(e => !e.disabled);
        document.getElementById('d-save').hidden = !editable;
        detailReadingLook();
    }

    /**
     * A LOCKED FIELD READS AS TEXT, AND AN EMPTY ONE IS NOT THERE
     * (20.09.2026: „bei fremden Punkten sollen die Felder nur als Text
     * angezeigt werden — der Rahmen um das Inputfeld sieht immer nach Eingabe
     * aus, das Lösch-X verwirrt, bei Bestimmungssicherheit gibt es sogar
     * einen Dropdown-Pfeil; es soll nur das angezeigt werden, was wirklich
     * Inhalt hat"). Until now a foreign find showed its fields grey but
     * still as fields: a frame, a cross, an arrow — every sign of a control,
     * on something that is none here. Now every disabled control marks its
     * field is-read, and the stylesheet strips frame, cross and arrow; a
     * read field without content marks itself is-empty and disappears, so
     * an empty „Begleitvegetation" no longer stands there as a box nobody
     * can type into. Called at the end of applyDetailRights — after the
     * extra fields exist and are locked — and again from there on every
     * fill, so an own point gets its frames back (the same reason that
     * function runs for own points too).
     */
    function detailReadingLook() {
        for (const control of detail.querySelectorAll('.detail-content input, .detail-content textarea, .detail-content select')) {
            /* A FIELD'S TICK READS LIKE ANY FIELD: without the right to write,
               its words stand as plain text when it is set („Beleg aufbewahrt")
               and nothing stands when it is not — the box goes (map.css). The
               list tree's boxes are no fields and stay out. */
            if (control.type === 'checkbox' && control.dataset.field) {
                const tickField = control.closest('.field');
                tickField?.classList.toggle('is-read', control.disabled);
                tickField?.classList.toggle('is-empty', control.disabled && !control.checked);
                continue;
            }
            if (control.type === 'checkbox' || control.type === 'file') continue;
            const field = control.closest('.field');
            if (!field) continue;
            /* THE FIND TIME OF A SPECIES FROM AN AUFSAMMLUNG reads too: it is
               the collecting day, held read-only by collectionui.js (readOnly,
               not disabled — applyDetailRights opens the find time for every
               own point afterwards), and it stood framed like a field one
               could type into (18.10.2026: „angleichen"). The
               coordinate's readOnly is its own matter — its pencil opens it. */
            const reading = control.disabled || (control === fields.found && control.readOnly);
            field.classList.toggle('is-read', reading);
            field.classList.toggle('is-empty', reading && String(control.value ?? '').trim() === '');
        }
    }

    /**
     * Save a foreign point — only what has changed.
     *
     * ONLY WHAT WAS TOUCHED: the server lays the sent keys over the saved
     * state (fremde_eingabe_sieben). Sending everything along would mean
     * overwriting, on every save, what somebody else changed in the meantime.
     */
    async function saveForeign(point, quiet = false) {
        const rights   = sharedRights(point);
        const change = {};
        const times    = {};
        const nowIso  = new Date().toISOString();

        if (rights.has('title') && fields.title.value.trim() !== (point.title ?? '')) {
            change.title = fields.title.value.trim();
            times.title = nowIso;
        }
        if (rights.has('note') && fields.note.value !== (point.note ?? '')) {
            change.note = fields.note.value;
            times.note = nowIso;
        }
        // The find time only in a joint list — elsewhere the field is locked
        // and the server has no right to check it against.
        if (jointPoint(point) && fields.found.value !== forField(point.found_at)) {
            change.found_at = foundForSaving(point);
            times.found_at = nowIso;
        }
        if (rights.has('move')) {
            const coord = readCoordinates(fields.coord.value);
            if (!coord) {
                status(t('Die Koordinaten sind nicht lesbar. Erwartet wird "51.123456, 7.654321".'), true);
                return false;
            }
            // To six decimal places, because exactly that much stands in the
            // field — otherwise every opening would count as a move by the
            // truncated remainder.
            if (coord[0].toFixed(6) !== point.lat.toFixed(6)
                || coord[1].toFixed(6) !== point.lon.toFixed(6)) {
                change.lat = coord[0];
                change.lon = coord[1];
                times.position = nowIso;
            }
            // The outline belongs to the same right — geometry is geometry.
            // Comparison uses outlineEqual, exactly like differences() for an
            // own point; null explicitly means remove.
            const outlineNew = outlineNow(point);
            if (!outlineEqual(outlineNew, point.outline)) {
                change.outline = outlineNew;
                times.outline = nowIso;
            }
        }

        const extraNew = {};
        for (const e of extraAll('[data-field]')) {
            if (e.disabled) continue;
            const old = point.extra?.[e.dataset.field];
            const oldText = Array.isArray(old) ? old.join(', ') : String(old ?? '');
            if (e.value.trim() !== oldText) {
                extraNew[e.dataset.field] = e.value.trim();
                times['extra.' + e.dataset.field] = nowIso;
            }
        }
        if (Object.keys(extraNew).length > 0) change.extra = extraNew;

        // Nothing touched? Then saving is a closing — as with an own point,
        // where differences() finds nothing to send.
        if (Object.keys(change).length === 0) {
            if (quiet) {
                host.lastFilled = { id: point.id, state: detailState() };
                markUnsaved(false);
                return true;
            }
            closeDetailAfterSave();
            status(t('Nichts geändert.'));
            return true;
        }
        const ok = await writeShared(point, change, times);
        if (ok) {
            markUnsaved(false);
            // The window afterwards shows the fresh state — writeShared
            // has put it into the store. On the desktop it stays open, on the
            // phone it steps aside; the same rule as with an own point.
            // Quiet (the Fundbrowser's autosave): the fields stay, counted as saved.
            const fresh = state.sharedPoints.get(point.id);
            if (quiet) host.lastFilled = { id: point.id, state: detailState() };
            else if (fresh) fillDetail(fresh, true);
            if (!quiet) {
                closeDetailAfterSave();
                status(t('Gespeichert.'));
            }
        }
        return ok;
    }

    /** Delete a foreign point — it lands in ITS OWNER'S trash. */
    async function deleteForeign(point) {
        /* A CO-OWNED FIND: I step out (11.09.2026). It stays with the holder
           and the others; my trash lets me back in. Not through points.php —
           nothing about the point changes, only my row (owners.php). */
        if (point.co_owned) {
            const ok = await confirm({
                title: t('Aussteigen?'),
                text:  t('Du steigst aus „%s" aus — der Fund bleibt bei %s. Aus deinem Papierkorb kannst du zurück.', point.title || t('Ohne Titel'), point.owner),
                button: t('Aussteigen'),
                danger: true,
            });
            if (!ok) return;
            try {
                await request('owners.php', { method: 'POST',
                    body: JSON.stringify({ action: 'leave', point_id: point.id }) });
                state.sharedPoints.delete(point.id);
                clearSelection();
                drawMarker(); drawList();
                status(t('Ausgestiegen — der Fund liegt in deinem Papierkorb.'));
            } catch (e) {
                status(t('Aussteigen fehlgeschlagen: %s', e.message), true);
            }
            return;
        }
        /* TWO TRUTHS FOR TWO KINDS OF FOREIGN. A container point really can
           only be brought back by its owner. An excursion find lands in the
           guest account's trash, and THAT one the leader reaches himself:
           Verwaltung → Papierkorb lists the per-excursion trashes (stage 6b),
           restore and permanent delete included. The old sentence promised
           the opposite — „zurückholen kann ihn nur Gast", about an account
           nobody can log into. */
        // A joint list's point: out of the list, into the list's trash (step 2).
        if (jointListsOf(point).length > 0) {
            const choice = await jointDeleteChoice(point, false);
            if (choice === null) return;
            try {
                const response = await api.remove('points', point.id, choice.query);
                const removed = new Set(response?.removed_from ?? choice.lists.map(l => l.id));
                point.lists = point.lists.filter(id => !removed.has(id));
                if (point.lists.length === 0 || response?.whole === true) state.sharedPoints.delete(point.id);
                clearSelection();
                drawMarker(); drawList();
                status(response?.pending ? t('Ohne Netz — das Herausnehmen wird nachgereicht.') : choice.sentence);
            } catch (e) {
                status(t('Löschen fehlgeschlagen: %s', e.message), true);
            }
            return;
        }
        const excursion = point.excursion_id != null && point.editable;
        const ok = await confirm({
            title: t('Punkt löschen?'),
            text: excursion
                ? t('Der Fund landet im Papierkorb — du findest ihn in der Verwaltung unter Papierkorb bei seiner Exkursion und kannst ihn dort zurückholen.')
                : t('Der Punkt landet im Papierkorb von %s — zurückholen kann ihn nur %s.', point.owner, point.owner),
            button: t('Löschen'),
            danger: true,
        });
        if (!ok) return;

        try {
            const response = await api.remove('points', point.id);
            state.sharedPoints.delete(point.id);
            clearSelection();
            status(response?.pending
                ? t('Ohne Netz — das Löschen wird nachgereicht.')
                : (excursion
                    ? t('Der Fund liegt jetzt im Papierkorb seiner Exkursion.')
                    : t('Der Punkt liegt jetzt im Papierkorb von %s.', point.owner)));
            if (!response?.pending) {
                syncShared(true)
                    .then(() => syncExcursions(true))
                    .then(() => syncHints(true))
                    .then(() => { drawMarker(); drawList(); }).catch(() => {});
            }
        } catch (e) {
            status(t('Löschen fehlgeschlagen: %s', e.message), true);
        }
    }

    /**
     * A change to a foreign point to the server — the one path for window AND
     * marker dragging.
     *
     * WITHOUT NETWORK IT GOES INTO THE QUEUE (api.patch does that itself) and
     * locally the optimistic state keeps showing — on handing in, the server
     * checks the rights again, and the field times decide, as with own points,
     * whether an intermediate change wins. After a real success,
     * syncShared syncs the store so the bucket on the device is not
     * left lying with an old state.
     */
    async function writeShared(point, change, times, rollback = null) {
        try {
            const response = await api.patch('points', point.id, {
                ...change,
                ...(start.fieldTimesReady ? { field_times: times } : {}),
            });
            if (response?.pending) {
                const fresh = {
                    ...point, ...change,
                    extra: { ...(point.extra ?? {}), ...(change.extra ?? {}) },
                };
                state.sharedPoints.set(point.id, fresh);
                status(t('Ohne Netz — die Änderung wird nachgereicht.'));
            } else {
                state.sharedPoints.set(point.id, response);
                syncShared(true)
                    .then(() => syncExcursions(true))
                    .then(() => syncHints(true))
                    .then(() => { drawMarker(); drawList(); }).catch(() => {});
            }
            drawMarker();
            drawList();
            return true;
        } catch (e) {
            rollback?.();
            status(t('Speichern fehlgeschlagen: %s', e.message), true);
            return false;
        }
    }

    /* --- The point window's handle ----------------------------------------
     *
     * 10.09.2026: „wie die Linke mit einem Handle zum Verschieben
     * und zum Größe ändern". Deliberately THE SAME mechanics as above — four
     * pixels of travel separate the drag from the click, a drag on the
     * collapsed window brings it out, and what was last SET is remembered,
     * not what is being measured right now (the width has a transition of
     * .2s, and a drag ends in the middle of it — on the left exactly that
     * once cost a stored 0).
     *
     * It is a version of its own all the same and not a shared function: the
     * sidebar is a grid column and grows to the RIGHT, this window hangs on
     * the right edge and grows to the LEFT — so the mouse's travel enters
     * with the opposite sign. One function for both would have to carry this
     * difference as a switch, and a switch that turns a direction round is
     * harder to read than two short paragraphs.
     */
    const DETAIL_MIN = 280;

    let detailWidthLast = null;

    function detailWidthApply(px) {
        detailWidthLast = Math.round(px);
        document.documentElement.style.setProperty('--detail-width', detailWidthLast + 'px');
        map.invalidateSize();
    }

    /* COLLAPSED IS NOT REMEMBERED, and that is the difference from the
       sidebar. There collapsing is a stance — „I am working on the map" —
       that is meant to outlast the visit. Here it is a gesture for the
       moment: looking once at the spot the window lies over. Remembered, it
       would be a trap, because the window does not open by itself but
       because somebody tapped a point — and they would find a handle on the
       next visit and nothing else.
       For the same reason every opening brings it out again (detailVisible).
       The WIDTH on the other hand is remembered: that is a stance. */
    function setDetailCollapsed(collapsed) {
        document.body.classList.toggle('detail-collapsed', collapsed);
        const grip = document.getElementById('detail-grip');
        // The handle says where it leads, not where it stands — as on the left.
        grip.textContent = collapsed ? '‹' : '›';
        grip.title = collapsed ? t('Punktfenster ausklappen') : t('Punktfenster einklappen');
        grip.setAttribute('aria-label', grip.title);
        grip.setAttribute('aria-expanded', String(!collapsed));
        setTimeout(() => map.invalidateSize(), 220);
    }

    {
        const grip = document.getElementById('detail-grip');
        const stored = Number(settings.get('detailWidth', 0));
        if (stored >= DETAIL_MIN) detailWidthApply(stored);
        setDetailCollapsed(false);

        let drag = null;
        let swallowClick = false;

        grip.addEventListener('pointerdown', (e) => {
            const open = document.body.classList.contains('detail-collapsed')
                ? 0 : (detail.offsetWidth - grip.offsetWidth);
            drag = {
                startX: e.clientX,
                startWidth: open > 40 ? open
                    : (Number(settings.get('detailWidth', 0)) || 340),
                moved: false,
            };
            try { grip.setPointerCapture(e.pointerId); } catch { /* without, then */ }
        });
        grip.addEventListener('pointermove', (e) => {
            if (drag === null) return;
            const way = e.clientX - drag.startX;
            if (!drag.moved && Math.abs(way) < 4) return;
            if (!drag.moved) {
                drag.moved = true;
                if (document.body.classList.contains('detail-collapsed')) setDetailCollapsed(false);
            }
            // Dragging to the LEFT makes it wider — hence the minus.
            detailWidthApply(Math.min(Math.max(drag.startWidth - way, DETAIL_MIN),
                Math.min(720, window.innerWidth * 0.6)));
        });
        grip.addEventListener('pointerup', () => {
            if (drag === null) return;
            const wasDrag = drag.moved;
            drag = null;
            if (wasDrag) {
                if (detailWidthLast !== null) settings.set('detailWidth', detailWidthLast);
                swallowClick = true;
            }
        });
        grip.addEventListener('click', () => {
            if (swallowClick) { swallowClick = false; return; }
            setDetailCollapsed(!document.body.classList.contains('detail-collapsed'));
        });
    }

    return {
        /** The Fundbrowser's autosave — saveDetail without toast, refill or stepping aside. */
        autoSave: () => saveDetail({ quiet: true }),
        detailDirty,
        detailPhotoTab,
        detailState,
        detailVisible,
        drawExtraFields,
        drawOutlineRow,
        fillDetail,
        foreignInWindow,
        getSpeciesList,
        leaveOk,
        listShareRightsReadable,
        markUnsaved,
        mayEditPointPhotos,
        nowForFind,
        rightsReadable,
        swapDetailKey,
    };
}
