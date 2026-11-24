/**
 * The cockpit's window — the point window (pointwindow.js) mounted on the
 * Fundbrowser, the way map.js mounts it on the map.
 *
 * ITS HOST IS THE SMALLEST THAT ANSWERS WHAT THE WINDOW ASKS (the list at
 * the head of mountPointWindow): the finds in store are the ONE find that is
 * open — plus, for the species suggestions, the other finds' titles and
 * days, which the Fundbrowser has anyway; the lists come from the start
 * data as on the map; and everything that is the map's — the marker, the
 * sidebar, the outline tool, the position, the gallery, the offline sync —
 * is a function that does nothing, or one that goes to the map where there
 * is something to go to („Zeigen"). The cockpit is for the desk and online
 * (11.10.2026): there is no queue to wait for here, and a find
 * stays in the window until one leaves it.
 *
 * WHAT THE WINDOW DOES HERE, UNCHANGED: fills its fields, keeps what is
 * typed when fresh data arrives, asks before it is left unsaved, saves a
 * find through api.patch (field times and all), saves a foreign one through
 * the share's rights, draws the custom fields of the lists a find is in.
 *
 * THE BELEGE ARE MOUNTED TOO (12.10.2026, on the first round:
 * „was ich bei den Bildern vermisse, ist die Upload-Funktion … die
 * Reihenfolge der Vorschaubilder umsortieren … die Funktion für die DNS"):
 * the gallery of the find's pictures grouped by their subject names,
 * „Auswählen" into the cloud, dragging into order, the sequences and the
 * other files — the gallery's code (photogallery.js) mounted beside the
 * window's. WHERE THEY STAND is the page's business (finds.js): since
 * 12.10.2026 every panel of the window is a tab of the page with the whole
 * width — the gallery as the rail of „Bilder" beside the viewer, the
 * sequences and the files as tabs of their own. The viewer and the plates
 * are the page's too and handed in. „Aufnehmen" (the phone's camera) is not
 * offered: a photo taken waits in the device's store for the map's
 * transfer, and this page has none.
 *
 * THE TAB „KARTE" HAS A MAP OF ITS OWN since 12.10.2026 (findmap.js): the
 * find's point dragged or set by a click, its outline drawn corner by
 * corner — the window's hooks drawMarker and redrawOutline, which are the
 * big map's there, are this small map's here. „Direkt teilen" reads the
 * start data's hints as on the map (point_window_start).
 */

import { mountListTree } from './listtree.js';
import { mountFindRights } from './findrights.js';
import { mountPointWindow, pointWindowElements } from './pointwindow.js';
import { mountPhotoGallery } from './photogallery.js';
import { mountPointFiles } from './pointfiles.js';
import { request } from './api.js';
import { personName } from './names.js';
import { mountFindMap } from './findmap.js';
import * as polygon from './polygon.js';
import { t } from './i18n.js';

/**
 * @param {object} start    the start data (pages/finds.php): lists, folders,
 *                          shared, find_fields, findNumber, user, …
 * @param {object} host     what the page around hands in:
 *   tools                  { viewer, plates } — the picture viewer's and the
 *                          plates' mounts (the gallery opens pictures in the
 *                          one and shows the plates row of the other)
 *   status(text, isError)  a message about a deed
 *   leave()                the find was closed — back to the tiles
 *   gone(id)               the find went to the trash
 *   changed(point)         the find was saved — the page keeps its lists in step
 *   picturesChanged()      the Belege drew the pictures again (order, upload)
 *   showOnMap(id)          show the find on the map — by its address, or by
 *                          a change of area inside the desk's frame page
 *   mapBox                 the tab „Karte"'s map area (#finds-map)
 *   savedLine              where the autosave says how it went (#finds-saved)
 */
export function mountCockpit(start, host) {
    const GUEST = false;

    /* THE STORE THAT TELLS: the window writes a saved find into
       state.points, and the page learns of it there — one place instead of
       a hook at each of the window's several saves. */
    class Store extends Map {
        set(key, value) {
            super.set(key, value);
            if (key === openId) host.changed(value);
            return this;
        }
    }
    let openId = null;
    const state = {
        points: new Store(),
        sharedPoints: new Map(),
        lists: new Map((start.lists ?? []).map((l) => [l.id, l])),
        folders: new Map((start.folders ?? []).map((f) => [f.id, f])),
        collapsed: new Set(),
        selectedId: null,
    };

    // The lists others share with me, as map.js lays them out (sharedStart / sharedLists).
    const sharedStart = Array.isArray(start.shared) ? start.shared : [];
    const sharedLists = new Map();
    for (const c of sharedStart) {
        for (const f of c.shares ?? []) {
            for (const l of f.lists ?? []) {
                const look = l.look ?? null;
                sharedLists.set(l.id, {
                    ...l,
                    ...(look?.color ? { color: look.color } : {}),
                    ...(look?.symbol ? { symbol: look.symbol } : {}),
                    original: { color: l.color, symbol: l.symbol },
                    containerId: c.id, sharing: f, container: c,
                });
            }
        }
    }
    // The page's one company (names.js, personName) — the same short names as the map.
    const shortNameOf = (name) => personName(name);

    const nothing = () => {};
    const { fields, photoFields, sequenceFields } = pointWindowElements();

    const listTree = mountListTree({
        start, state, drawList: nothing, shortNameOf,
        get sharedLists() { return sharedLists; },
        get sharedStart() { return sharedStart; },
    });
    const { listRoot, drawListSelection } = listTree;
    const findRights = mountFindRights({
        listRoot, state,
        get sharedLists() { return sharedLists; },
    });
    const { foreignPoint, pointIsFind, sharedRights } = findRights;

    // The state both sides of the window write — here only the window does.
    let outlineDraft;
    let sequenceTabCount = 0;
    let filesTabCount = 0;
    let filesOffered = false;
    let speciesSources = {};
    let lastFilled = { id: null, state: null };
    // Where the find lies — the map's centre only serves as a fallback for an unreadable coordinate.
    let centre = { lat: 51, lng: 10 };

    /* THE FIND'S OWN MAP (findmap.js), built when its tab first opens. It
       reads the find from the window each time it draws — the coordinate as
       it stands in the field, the outline as drafted — and writes back the
       way the big map does (createMarker's dragend, redrawOutline's done):
       into the field, marked unsaved, nothing saved. */
    const openPoint = () => (openId === null ? null : state.points.get(openId) ?? state.sharedPoints.get(openId) ?? null);
    const findMap = host.mapBox ? mountFindMap(host.mapBox, {
        start,
        read() {
            const point = openPoint();
            if (!point) return null;
            const typed = /^\s*(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)\s*$/.exec(fields.coord.value);
            const lat = typed ? Number(typed[1]) : Number(point.lat);
            const lon = typed ? Number(typed[2]) : Number(point.lon);
            const list = (point.lists ?? []).map((id) => state.lists.get(id) ?? sharedLists.get(id)).find(Boolean);
            return {
                id: point.id, lat, lon,
                outline: outlineDraft === undefined ? (point.outline ?? null) : outlineDraft,
                color: list?.color || '#c46a2b',
                // The window says whether the coordinate may change (applyDetailRights: the right to move).
                mayMove: !fields.coord.disabled,
            };
        },
        moved(lat, lon) {
            fields.coord.value = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;
            windowApi.markUnsaved(true);
            saveSoon();
            // The outline row says when the spot has left its own area.
            windowApi.drawOutlineRow(outlineDraft === undefined ? (openPoint()?.outline ?? null) : outlineDraft);
            findMap.redraw();
        },
    }) : null;
    // A coordinate typed into the field (the pencil beside it) moves the point on the map too.
    fields.coord.addEventListener('change', () => findMap?.redraw());

    /** An outline drawn on the find's map — redrawOutline's done() in map.js, word for word. */
    function outlineDrawn(corners) {
        const center = polygon.center(corners);
        if (!center) {
            host.status(t('Der Umriss taugt nicht.'), true);
            return;
        }
        outlineDraft = corners;
        fields.coord.value = `${center[0].toFixed(6)}, ${center[1].toFixed(6)}`;
        windowApi.drawOutlineRow(corners);
        findMap.redraw();
        windowApi.markUnsaved(true);
        host.status(t('Umriss gezogen — %s.', polygon.readableArea(polygon.areaM2(corners))));
        saveSoon();
    }

    /* --- THE WINDOW SAVES ITSELF (13.10.2026) -------------------
       „den Speicher-Button abschaffen, da man jetzt aktiv alles bewusst macht
       und nicht mehr aus Versehen einen Punkt versetzen kann." The button was
       born on the map (createMarker: a marker dragged along while panning
       cost a coordinate kept for years); at the desk nothing moves by
       accident any more — the map's markers move only in the window, which
       the desk no longer opens, and the find's own map moves its point only
       after „Position setzen" (findmap.js). So a second after the last change
       the find goes to the server by itself, as a plate does (plates.js,
       AUTOSAVE_MS), and one line beside the tabs says how it went. ONE SAVE AT
       A TIME; what changes meanwhile goes with the next. A save that fails is
       not repeated every second — the next change tries again, and leaving
       asks, as the button's window always did. The phone keeps its button:
       there the window is unchanged („All das lass so erstmal in
       der Mobilansicht"). */
    const AUTOSAVE_MS = 1000;
    let saveTimer = null;
    let saving = null;
    function savedSay(word) {
        if (!host.savedLine) return;
        host.savedLine.textContent = { saving: t('Wird gespeichert …'), saved: t('Gespeichert'),
            failed: t('Nicht gespeichert'), '': '' }[word];
        host.savedLine.classList.toggle('is-failed', word === 'failed');
    }
    /* NOT WHILE THE SPECIES IS BEING CHOSEN (18.10.2026: the
       suggestion list „kollidiert auch ein bisschen mit dem Autosave"). A
       second after „Bol" the half word went to the server as the find's
       name, while the list still stood open to pick from. As long as the
       name field has the focus, the clock waits — not only while its list
       stands open: between two keys the list closes for its next search,
       and that moment was enough for „Scutellinia" to go out half typed.
       The name is saved when one leaves the field (a click anywhere else
       starts the clock), and leaving the find flushes it in any case.

       A PICK FROM THE LIST SAVES AT ONCE (13.11.2026: „sofort
       gespeichert … momentan erst, wenn ich das Feld wieder verlasse"): the
       pick IS the finished word, so the window reports it (speciesPicked)
       and the field may keep its focus for a cf. behind the name.

       EVERY FIELD WITH SUGGESTIONS, not only the title (the same day): a
       „Substrat" or „Begleitvegetation" went out half typed a second after
       „Fag" exactly as the title once did. bindSpecies marks such a field
       as a list box (aria-autocomplete), and that is what is asked here;
       the plain text fields keep saving a second after the last key. */
    const choosingSpecies = () => {
        const el = document.activeElement;
        return el?.getAttribute('aria-autocomplete') === 'list' && windowBox.contains(el);
    };
    function saveSoon() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            if (choosingSpecies()) saveSoon();
            else saveNow();
        }, AUTOSAVE_MS);
    }
    /** Save what the window holds, now; true when nothing is left unsaved. */
    async function saveNow() {
        clearTimeout(saveTimer);
        saveTimer = null;
        if (saving) await saving;
        if (openId === null || !windowApi.detailDirty()) return true;
        savedSay('saving');
        saving = windowApi.autoSave();
        let ok = false;
        try { ok = await saving; } finally { saving = null; }
        if (ok && windowApi.detailDirty()) return saveNow();   // typed on meanwhile
        savedSay(ok ? 'saved' : 'failed');
        return ok;
    }
    /* Every change of the window passes one of these: a key, a choice, a
       tick, a pick from the species list, a button that removes an outline.
       Whether there is something to save is asked when the clock runs out. */
    const windowBox = document.getElementById('detail');
    for (const kind of ['input', 'change', 'click']) windowBox.addEventListener(kind, () => saveSoon());

    const windowApi = mountPointWindow({
        GUEST,
        fields,
        // „Direkt teilen": the start data's hints, as the map has them (point_window_start).
        hintStart: start.hints ?? null,
        map: { getCenter: () => centre, getZoom: () => 17, invalidateSize: nothing },
        // Outlines are drawn on the find's own map — where the column is there.
        outlinesPossible: findMap !== null && start.outlines !== false,
        photoFields,
        sharedLists,
        start,
        state,
        // The map's sidebar: nothing to draw here. The marker and the outline are the find's map's.
        drawList: nothing,
        drawMarker: () => findMap?.redraw(),
        computeListCounts: nothing,
        countConflicts: nothing,
        redrawOutline: () => findMap?.beginOutline(outlineDrawn),
        moveMode: nothing,
        pauseFollowing: nothing,
        // The Belege's gallery and sequences (mounted below — the window is built first).
        drawPhotoGallery: (point) => gallery?.drawPhotoGallery(point),
        drawSequences: (point) => gallery?.drawSequences(point),
        hintLeave: nothing,
        // The key behind „Art" leaves the page: what waits for the autosave goes first.
        beforeLeave: () => saveNow(),
        /* The key BESIDE the find, where the page has that area (finds.js,
           areas): a way under „Art" opens there, and the area hears when
           the find's ways are known. */
        keyOpen: host.areas ? (params, own) => host.areas.keyOpen(params, own) : null,
        keyingsDrawn: (id) => host.areas?.keyingsDrawn(id),
        // A name picked from the species list: saved at once (choosingSpecies says why).
        speciesPicked: () => { saveNow(); },
        syncExcursions: async () => {},
        syncHints: async () => {},
        syncShared: async () => {},
        isPhone: () => false,
        listRoot, drawListSelection, foreignPoint, pointIsFind, sharedRights, shortNameOf,
        status: (text, isError = false) => host.status(text, isError),
        outlineNow: (point) => (outlineDraft === undefined ? (point?.outline ?? null) : outlineDraft),
        pointReady: async (point) => (point ? state.points.get(point.id) ?? null : null),
        // „Zeigen" and the like: the map is where a find has a place.
        centreVisible: () => { host.showOnMap(openId); },
        fitVisible: () => { host.showOnMap(openId); },
        // The window's „Schließen": asks first when something is unsaved.
        closeDetail: async () => { if (await windowApi.leaveOk()) host.leave(); },
        // A find that went to the trash: the page forgets it and goes back.
        clearSelection: () => {
            const id = openId;
            outlineDraft = undefined;
            windowApi.markUnsaved(false);
            if (id !== null) host.gone(id);
        },
        // A saved find that came back rejected is put into the fields again
        // (revertSave) — by selecting it afresh.
        selectPoint: (id) => {
            const point = state.points.get(id) ?? state.sharedPoints.get(id);
            if (point) windowApi.fillDetail(point, true);
        },
        get outlineDraft() { return outlineDraft; },
        set outlineDraft(v) { outlineDraft = v; },
        get sequenceTabCount() { return sequenceTabCount; },
        set sequenceTabCount(v) { sequenceTabCount = v; },
        get filesTabCount() { return filesTabCount; },
        set filesTabCount(v) { filesTabCount = v; },
        get filesOffered() { return filesOffered; },
        set filesOffered(v) { filesOffered = v; },
        get speciesSources() { return speciesSources; },
        set speciesSources(v) { speciesSources = v; },
        get lastFilled() { return lastFilled; },
        set lastFilled(v) { lastFilled = v; },
        get sequenceFields() { return sequenceFields; },
    });

    /* THE GALLERY, mounted on what the window gives and what the page's
       viewer and plates give. The „Dateien" block counts its files into the
       Belege tab's number through the window (detailPhotoTab). */
    const { cloudBytes, cloudFilesShown, cloudPhotoUrl, measureStyle, showPhoto, showCloudPhoto } = host.tools.viewer;
    const pointFiles = mountPointFiles({
        request,
        bytes: (n) => cloudBytes(n),
        baseUrl: start.baseUrl,
        count: (n) => { filesTabCount = n; windowApi.detailPhotoTab(); },
        changed: (files) => host.filesChanged?.(files),
    });
    const gallery = mountPhotoGallery({
        GUEST,
        cloudBytes, cloudFilesShown, cloudPhotoUrl, measureStyle, showPhoto, showCloudPhoto,
        detailPhotoTab: windowApi.detailPhotoTab,
        detailState: windowApi.detailState,
        mayEditPointPhotos: windowApi.mayEditPointPhotos,
        foreignPoint,
        photoFields,
        sequenceFields,
        /* THE PLATES' ROW IS REDRAWN AT THE END OF EVERY DRAWING of the gallery
           (it asks the cloud afresh each time) — which makes it the place to
           tell the page that the pictures may have changed: a drag into
           another order, an upload that came through. The stage beside the
           window then draws them again (finds.js, syncStage). */
        plates: { show: (id) => { host.tools.plates.show(id); host.picturesChanged?.(); } },
        pointFiles,
        start,
        state,
        drawList: nothing,
        pointReady: async (point) => (point ? state.points.get(point.id) ?? null : null),
        // The find's other areas (pages/finds.php) — the microscope steps aside for them.
        areas: host.areas ?? null,
        status: (text, isError = false) => host.status(text, isError),
        get filesOffered() { return filesOffered; },
        set filesOffered(v) { filesOffered = v; },
        get sequenceTabCount() { return sequenceTabCount; },
        set sequenceTabCount(v) { sequenceTabCount = v; },
    });

    return {
        /** The find's other files, to open and fetch from elsewhere on the page (the overview). */
        files: pointFiles,

        /**
         * A species confirmed in the key beside the find (finds.js, areas):
         * the server renamed the find while its texts stood still in the
         * page. Taken in afresh — through fillDetail's guard, which leaves
         * typed fields alone — or the next autosave writes the old name back.
         */
        /** The find's ways changed in the key beside it: the lines under „Art" and „a way of mine stands open" anew. */
        waysChanged: () => windowApi.keyingsRefresh(),

        async speciesConfirmed(id) {
            try {
                const fresh = await request(`points.php?id=${id}`);
                if (!state.points.has(id)) return;
                state.points.set(id, fresh);
                if (state.selectedId === id) windowApi.fillDetail(fresh);
                host.changed?.(fresh);   // the tile and the head take the new name too
            } catch { /* the next load brings it */ }
        },

        /** Draw the Belege of the open find again — after the viewer changed a name or removed a picture. */
        redrawGallery() {
            const point = state.points.get(openId) ?? state.sharedPoints.get(openId);
            if (point) gallery.drawPhotoGallery(point);
        },

        /**
         * The other finds, as far as the species search wants them: it ranks
         * what one finds on this tour, in this season and region by the
         * titles, days and places of the finds one already has.
         */
        overview(finds) {
            for (const f of finds) {
                const day = f.found_at ?? null;
                const point = {
                    id: f.id, title: f.title, found_at: day, created_at: day, lat: f.lat, lon: f.lon,
                    lists: [], extra: {}, deleted_at: null,
                };
                if (f.own) { if (f.id !== openId && !state.points.has(f.id)) Map.prototype.set.call(state.points, f.id, point); }
                else if (!state.sharedPoints.has(f.id)) state.sharedPoints.set(f.id, point);
            }
        },

        /**
         * Open a find in the window — the one find_cockpit() answered
         * (includes/finds.php): own, co-owned, or foreign through a share.
         */
        open(detail) {
            clearTimeout(saveTimer);
            savedSay('');
            const point = detail.point;
            openId = point.id;
            state.selectedId = point.id;
            centre = { lat: point.lat, lng: point.lon };
            outlineDraft = undefined;
            if (detail.own) {
                Map.prototype.set.call(state.points, point.id, point);
                state.sharedPoints.delete(point.id);
            } else {
                state.points.delete(point.id);
                /* The lists a foreign find lies in, as far as they are shared
                   with me — the holder's others are not mine to read. */
                point.lists = (point.lists ?? []).filter((id) => sharedLists.has(id));
                state.sharedPoints.set(point.id, point);
            }
            findMap?.reset();
            windowApi.detailVisible(true);
            windowApi.fillDetail(point, true);
            findMap?.redraw();
        },

        /** The tab „Karte" came into sight — the map is built the first time, sized every time. */
        mapShown: () => findMap?.show(),

        /** Whether something typed is not saved — the page asks before it leaves the find. */
        dirty: () => windowApi.detailDirty(),
        /** Save now what waits for the autosave's second; true when nothing is left unsaved. */
        flush: () => saveNow(),
        /** The window's own question: „Verwerfen — oder Speichern?" — true when one may go. */
        leaveOk: () => windowApi.leaveOk(),

        /** The window is no longer shown. */
        close() {
            findMap?.reset();
            openId = null;
            state.selectedId = null;
            // The pictures go with the find — not when the next one's answer arrives (photogallery.js says why).
            gallery?.clearPhotoGallery();
            windowApi.markUnsaved(false);
            windowApi.detailVisible(false);
        },
    };
}
