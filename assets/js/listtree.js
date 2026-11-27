/**
 * The list tree — the model of the lists (children, branches, the roots
 * that answer for a list's settings, my own joint lists and the others')
 * and the checkbox tree one files a find into (drawListSelection).
 *
 * OUT OF map.js ON 11.10.2026, because the point window needs it and the
 * Fundbrowser mounts the point window: a find's custom fields follow the
 * lists it is ticked into, so a window without the tree cannot draw them.
 * Moved as it stood, found by the scope analysis of map.js's top level;
 * mountListTree(host) hands back the nine functions the rest of map.js
 * (the sidebar, Organisieren's tree, the creation window) uses under
 * their old names.
 *
 * THE HOST: start, state (its lists and collapsed set), sharedLists and
 * sharedStart (the lists others share with me), drawList (the sidebar is
 * redrawn when a branch is folded — one tree, not three) and shortNameOf.
 */

import { t } from './i18n.js';
import { settings } from './settings.js';
import { iconHtml } from './icons.js';
import { shapePoint } from './shapes.js';

export function mountListTree(host) {
    const {
        start,
        state,
        drawList,
        shortNameOf,
    } = host;

    /**
     * The fold triangle — sidebar, list picker, legend.
     *
     * AS A DRAWING, NOT AS A GLYPH. '▸' and '▾' looked different everywhere:
     * how big, how bold and how centered they sit is decided by the device's
     * font, not this application. They therefore came out tiny, and making them
     * bigger only worked via the font size — with which the line grew too. A
     * drawing measures what you tell it.
     *
     * ONE TRIANGLE, NOT TWO: closed and open differ by rotation, not by shape.
     * That way there can be no state in which the two look different, and the
     * flip can be shown softly. Pointing right means closed.
     *
     * currentColor, so the existing color rules — quiet, stronger on hover —
     * keep applying without standing here a second time.
     *
     * As a function and not as a constant: it is needed at three places that
     * come earlier in the file than it does itself. A function declaration
     * holds in the whole module, a constant only from its line on.
     */
    function disclosureTriangle() {
        return iconHtml('fold-triangle', { class: 'collapse-triangle' });
    }

    /**
     * Visible is only what stands in a selected list.
     *
     * Without a selection the map stays empty — not showing everything instead.
     * With over a hundred points, "everything" is rarely what you want to see,
     * and the selection would otherwise be no filter but only a highlight.
     */
    // ---------------------------------------------------------------------------
    // The list tree
    //
    // Lists can nest inside one another: "Meine Funde › Pilze › Röhrlinge". The
    // database holds only a pointer to the parent list for this; everything
    // else — order, counting, inheritance, visibility — arises here.
    //
    // A PARENT LIST ALWAYS MEANS ITS WHOLE BRANCH. Whoever checks "Meine Funde"
    // wants to see the finds, not an empty list with the note that the points
    // lie one level deeper. The same holds for the number next to it and for
    // the custom fields inherited from top to bottom.
    // ---------------------------------------------------------------------------

    /** The immediate children per list, by sort order and name. */
    function listChildren() {
        const children = new Map();
        for (const list of state.lists.values()) {
            const parent = list.parent_id ?? null;
            if (!children.has(parent)) children.set(parent, []);
            children.get(parent).push(list);
        }
        for (const row of children.values()) {
            row.sort((a, b) => (a.sort_order - b.sort_order) || a.name.localeCompare(b.name, 'de'));
        }
        return children;
    }

    /**
     * The top of the sidebar: Mappen and the lists outside any Mappe, in ONE
     * row by sort_order (includes/folders.php says why). Each item is either
     * { list } or { folder, lists } — the lists of a Mappe in their own order.
     * A list whose Mappe this device does not know (yet) stands loose rather
     * than vanishing.
     */
    function topLevel(children = listChildren()) {
        const inFolder = new Map([...state.folders.keys()].map(id => [id, []]));
        const items = [];
        for (const list of children.get(null) ?? []) {
            if (list.folder_id != null && inFolder.has(list.folder_id)) inFolder.get(list.folder_id).push(list);
            else items.push({ list, sort: list.sort_order ?? 0, name: list.name });
        }
        for (const folder of state.folders.values()) {
            items.push({ folder, lists: inFolder.get(folder.id), sort: folder.sort_order ?? 0, name: folder.name });
        }
        return items.sort((a, b) => (a.sort - b.sort) || String(a.name).localeCompare(String(b.name), 'de'));
    }

    /**
     * A list and everything below it.
     *
     * The counter is no ornament: should a cycle ever end up in the data through
     * a bug, this loop would otherwise run forever — and the whole interface
     * with it. The server prevents cycles (liste_eltern_pruefen), but a browser
     * that meets an older version must not freeze because of that.
     */
    function listBranch(id, children = listChildren()) {
        const out = [];
        const stack = [id];
        let guard = 0;
        while (stack.length > 0 && guard++ < 500) {
            const current = stack.pop();
            if (out.includes(current)) continue;
            out.push(current);
            for (const child of children.get(current) ?? []) stack.push(child.id);
        }
        return out;
    }

    /**
     * Does a point still hang in this list or anywhere below it?
     *
     * THE BRANCH STAYS IN THE QUESTION although lists are flat since the Mappen
     * (migration 0168): a device that still holds a list with a parent_id — an
     * old offline copy, a list not yet converted — has points one level deeper,
     * and deleting its parent as „empty" would silently take the one-click path
     * past the confirm window. For a flat list the branch is just the list.
     * c790422 removed this helper together with its other caller
     * (speciesLocked) and left this one standing — deleteListWithConfirmation
     * then threw a ReferenceError before the window could open.
     */
    function listBranchHasPoints(id) {
        const branch = listBranch(id);
        for (const p of state.points.values()) {
            if (!p.deleted_at && p.lists?.some(l => branch.includes(l))) return true;
        }
        return false;
    }

    /**
     * The list that answers for a list's settings — the list itself.
     *
     * THE NAME IS FROM THE TREE: colour, fields and the species search once
     * sat on the main list of a branch, and this walked up to it. Since the
     * Mappen (migration 0168) every list carries its settings itself; the
     * function stays because a dozen places ask it.
     */
    function listRoot(id) {
        return state.lists.get(id) ?? null;
    }

    /** The list itself and its ancestors, from bottom to top. */
    function listAncestors(id) {
        const out = [];
        let cursor = id;
        let guard = 0;
        while (cursor != null && guard++ < 50) {
            const list = state.lists.get(cursor);
            if (!list) break;
            out.push(list);
            cursor = list.parent_id ?? null;
        }
        return out;
    }

    /**
     * The list tree with checkboxes — in the point window as in the picker
     * window before creating.
     *
     * ONE FUNCTION FOR BOTH, because both show the same thing: the same tree of
     * the same lists on which you set checkmarks. Two versions had already
     * drifted apart — one built the tree, the other counted the lists down in
     * the order they happened to lie in memory. When creating, a subcategory
     * therefore stood somewhere, just not under its main list.
     *
     * COLLAPSED BRANCHES STAY IN THE FORM, only invisible. That is no
     * convenience but the heart of the matter: whoever took the rows out would
     * take their checkmarks out too. In the point window that would mean
     * collapsing looks like deselecting — the change indicator would kick in,
     * and saving would throw the lists away. Collapsing is a matter of overview
     * and must change nothing about the data.
     *
     * WHAT IS CHECKED INSIDE A COLLAPSED BRANCH SHOWS ON THE BRANCH. Otherwise
     * you would create a point in a list you cannot currently see.
     *
     * The fold state is the same as in the sidebar and outlives the visit — it
     * is one tree, not three, and whoever collapses "Pilze" means it
     * everywhere. That is why the sidebar is redrawn along when folding.
     *
     * POINTS LIVE ONLY IN LEAVES — here stands the front half of this rule (the
     * back half is the sieve in punkt_listen_setzen): a list with sublists is a
     * heading and gets neither checkmark nor symbol.
     *
     * WITH ONE EXCEPTION, the legacy stock: a point that still hangs directly
     * in a parent list from the old world KEEPS showing this checkmark —
     * otherwise every casual save would silently strip the assignment, because
     * what the form does not show, it does not send along either. Unchecking
     * yes, re-checking no. The exception holds only in the point window
     * ('altlast'); when creating there is no stock, hence none.
     *
     * @param {HTMLElement} behaelter  The box that takes the rows
     * @param {(list: object) => boolean} checked   What is checked when drawing
     * @param {?() => void} onChange  After every checkmark, for dependent fields
     * @param {boolean} altlast  Show checked headings as deselectable anyway
     * @param {?(list: object) => boolean} skip  Leave this list out — AND ITS
     *   WHOLE BRANCH WITH IT, because the recursion does not descend into what
     *   it skips. That is what the one caller needs who has it: hanging into a
     *   container, where a branch already shared may not be offered a second
     *   time. Without the option this drawing would exist twice, and the
     *   second copy is the one that stops looking like the first.
     * @param {boolean} unfolded  Every branch open, whatever the sidebar keeps
     *   folded — the point window's rule (02.10.2026: „dass alle
     *   Unterlisten immer ausgeklappt sind. Hier haben wir Platz … aus Local
     *   Storage würde ich das hier nicht nehmen"). The sidebar is a narrow
     *   column one keeps tidy, the point window's „Karte" tab has room, and
     *   whoever files a find wants to see every list it could go into. A fold
     *   still works there, for the moment only: it is not stored, it does not
     *   reach the sidebar, and the next opening starts open again — a
     *   triangle that does nothing would be a promise the window cannot keep.
     */
    /**
     * The joint lists I may file points into — the leaves of every joint
     * branch in the shares I am a member of, minus my own lists (they stand
     * in my own tree). „Gemeinsam bearbeiten" (migration 0110, 22.09.2026):
     * the members fill such a list together, so it is offered wherever a
     * point is filed.
     */
    function jointLists() {
        const out = [];
        for (const list of host.sharedLists.values()) {
            if (list.sharing?.joint !== true || list.user_id === start.user?.id) continue;
            // A leaf of the hung-in branch: no list of the same share has it as parent.
            const hasChild = (list.sharing.lists ?? []).some(x => x.parent_id === list.id);
            if (!hasChild) out.push(list);
        }
        return out;
    }

    function drawListSelection(container, { checked, onChange = null, legacy = false, skip = null, joint = false, unfolded = false, tilePerFolder = false }) {
        const children = listChildren();
        // The folded branches: the sidebar's remembered set, or one of this drawing's own.
        const folded = unfolded ? new Set() : state.collapsed;
        const rows = [];
        const byId = new Map();

        container.innerHTML = '';

        /* ONE TILE PER KIND (22.09.2026: „für gemeinsame Listen
           bitte eine eigene Kachel"). Where the joint block is drawn, the box
           stops being the frame and holds two framed tiles instead — my own
           tree, then the joint lists —, so the special lists stand visibly
           apart and not merely under a heading in the same box. Everything
           stays INSIDE the container: the callers read its checkboxes. */
        const tile = () => {
            const box = document.createElement('div');
            box.className = 'list-selection-tile';
            return box;
        };
        container.classList.toggle('is-tiled', joint);
        let ownTile = joint ? tile() : container;
        if (joint) container.append(ownTile);
        /* A TILE PER MAPPE where the caller has the width for them side by
           side (the Fundbrowser's tab „Listen", 27.11.2026: „alles
           nebeneinander setzen, in einzelne Kacheln aufteilen"): each Mappe
           its own tile, the lists outside a Mappe one tile between them.
           Only the drawing moves — folding goes by the entries' parents,
           not by where their rows stand. */
        let tileKind = null;
        const tileFor = (kind) => {
            if (!(joint && tilePerFolder)) return;
            if (kind === 'loose' && tileKind === 'loose') return;
            if (tileKind !== null) {
                ownTile = tile();
                container.append(ownTile);
            }
            tileKind = kind;
        };

        /* MY OWN JOINT LISTS ARE SPECIAL LISTS AND STAND IN THE BLOCK BELOW,
           not in my tree (22.09.2026: „Es sind eben Sonderlisten.
           Die dürfen ruhig in einem eigenen Block stehen"). The whole branch
           goes — shared is the branch, and its leaves are what the block
           offers, flat, as it offers the others' joint leaves. */
        const ownJoint = joint ? ownJointIds(children) : new Set();

        // `items` and `entryParent` for a Mappe's lists: they hang in the
        // tree under the Mappe's row ('f' + id), not under a list.
        const level = (parentId, depth, items = children.get(parentId) ?? [], entryParent = parentId) => {
            for (const list of items) {
                if (skip?.(list)) continue;
                if (ownJoint.has(list.id)) continue;

                /* AN AUTO LIST HAS NO BUSINESS IN THIS TREE — anywhere
                   (23.08.2026: „In Autolisten kann man keine
                   Punkte anlegen … eine Autoliste dürfte dort überhaupt
                   keine Checkbox haben", und dann: „eigentlich brauchen wir
                   dann in der Punkteliste auch nicht die automatischen
                   Listen aufführen … die bringen uns nichts").

                   THIS TREE IS FOR CHOOSING, and an auto list offers no
                   choice: it follows its filter (Organisieren), the server sieves
                   it out of every hand-made assignment (point_lists_set),
                   and the checkbox was disabled at every call site anyway —
                   a control nobody can operate is a promise nobody can keep.
                   In the window before creating a point it even stood there
                   CHECKED, because it happened to be the filtered list in
                   the sidebar, and promised a filing that never happens.

                   WHERE THE MEMBERSHIP IS VISIBLE: in the sidebar, where an
                   auto list carries its count like every list, and in Organisieren,
                   where its rows stand with „· automatisch". */
                if (list.is_autolist) continue;
                const hasChildren = (children.get(list.id) ?? []).length > 0;
                // Pure heading — or, thanks to legacy stock, a choice after all?
                const heading = hasChildren && !(legacy && checked(list));

                const row = document.createElement('div');
                row.className = 'list-selection-row' + (heading ? ' is-heading' : '');
                if (depth > 0) row.style.setProperty('--depth', String(depth));

                const arrow = document.createElement('button');
                arrow.type = 'button';
                arrow.className = 'list-selection-arrow';

                const label = document.createElement('label');
                label.className = 'list-selection-entry';

                let box = null;
                if (!heading) {
                    box = document.createElement('input');
                    box.type = 'checkbox';
                    box.value = String(list.id);
                    box.checked = checked(list);
                    label.append(box);
                }

                // The symbol is carried by the leaf itself — headings have
                // none, and neither does the legacy row: it is a reminder, not
                // an equal target.
                if (!hasChildren) {
                    const symbol = document.createElement('span');
                    symbol.innerHTML = shapePoint(list.color, list.symbol);
                    label.append(symbol.firstElementChild);
                } else if (box) {
                    label.title = t('Überschrift mit Unterlisten — der Punkt gehört in die letzte Ebene');
                }

                const text = document.createElement('span');
                text.className = 'list-selection-name';
                text.textContent = list.name;

                /* MY OWN LIST, HUNG IN AS A JOINT ONE, SAYS SO
                   (22.09.2026: „ich muss halt im Hinterkopf behalten, das sind
                   gemeinsame Listen"). The others see these lists in the block
                   „Gemeinsame Listen" further down, with my name beside them —
                   I see them among my own, and nothing told me that a point
                   set here is the group's to tend. The list stays in my tree
                   (it may be a sublist, and its branch is what is shared), so
                   the mark stands beside the name, the way the sharer's name
                   does in the block below. */
                if (!joint && ownJointPlace(list.id) !== null) {
                    const mark = document.createElement('span');
                    mark.className = 'list-selection-owner';
                    mark.textContent = ' · ' + t('gemeinsam');
                    text.append(mark);
                }

                const count = document.createElement('span');
                count.className = 'list-selection-count';
                count.hidden = true;

                // The number stands NEXT TO the label, not inside it: a click
                // into the label sets the checkmark, and whoever only wanted to
                // see what is inside the collapsed branch would have put the
                // point into the main list — the exact opposite of what he
                // asked for.
                label.append(text);
                row.append(arrow, label, count);
                ownTile.append(row);

                const entry = { id: list.id, parentId: entryParent, hasChildren, row, arrow, box, count };
                rows.push(entry);
                byId.set(list.id, entry);

                box?.addEventListener('change', () => {
                    applyState();
                    onChange?.();
                });

                if (hasChildren) {
                    const fold = () => {
                        if (folded.has(list.id)) folded.delete(list.id);
                        else folded.add(list.id);
                        applyState();
                        if (unfolded) return;   // a fold of the moment, see the option
                        settings.set('collapsedLists', [...state.collapsed]);
                        // The same tree stands in the sidebar; if it stayed
                        // put, two views would show the same thing differently.
                        drawList();
                    };
                    arrow.addEventListener('click', fold);

                    /* THE WHOLE ROW FOLDS, NOT ONLY THE TRIANGLE. The
                       triangle is a good twelve pixels wide — on the phone one
                       misses it, and whoever misses gets no feedback at all:
                       the row carries no checkbox here, so nothing happens,
                       and one taps again (20.08.2026).

                       ONLY ON THE PURE HEADING. From the old stock a list WITH
                       sublists can still carry a checkmark (legacy) — there a
                       click on the row sets the checkmark, and putting both on
                       one click would mean nobody knows any more what they are
                       about to trigger.

                       The arrow keeps its own listener: the keyboard runs
                       through it. Its click bubbles up to the row, so the
                       row's listener catches it, otherwise it would fold twice
                       and thus not at all. */
                    if (heading) {
                        row.addEventListener('click', (e) => {
                            if (e.target.closest('.list-selection-arrow')) return;
                            fold();
                        });
                    }
                } else {
                    arrow.tabIndex = -1;
                    arrow.setAttribute('aria-hidden', 'true');
                }

                level(list.id, depth + 1);
            }
        };

        /* THE MAPPEN (0168) fold like the headings did and carry no box: a
           point lies in a list, never in a Mappe. A Mappe with nothing to
           choose in it is left out — an empty fold here is a dead end. */
        for (const item of topLevel(children)) {
            if (item.list) {
                tileFor('loose');
                level(null, 0, [item.list]);
                continue;
            }
            tileFor('folder');
            const key = 'f' + item.folder.id;
            const row = document.createElement('div');
            row.className = 'list-selection-row is-heading is-folder';
            const arrow = document.createElement('button');
            arrow.type = 'button';
            arrow.className = 'list-selection-arrow';
            // A label like the headings' rows, although it holds no box: the
            // stylesheet gives only label.list-selection-entry its width.
            const label = document.createElement('label');
            label.className = 'list-selection-entry';
            const text = document.createElement('span');
            text.className = 'list-selection-name';
            text.textContent = item.folder.name;
            label.append(text);
            const count = document.createElement('span');
            count.className = 'list-selection-count';
            count.hidden = true;
            row.append(arrow, label, count);
            ownTile.append(row);
            const entry = { id: key, parentId: null, hasChildren: true, row, arrow, box: null, count };
            const before = rows.length;
            rows.push(entry);
            byId.set(key, entry);
            level(null, 1, item.lists, key);
            if (rows.length === before + 1) {
                rows.pop();
                byId.delete(key);
                row.remove();
                continue;
            }
            const fold = () => {
                if (folded.has(key)) folded.delete(key);
                else folded.add(key);
                applyState();
                if (unfolded) return;
                settings.set('collapsedLists', [...state.collapsed]);
                drawList();
            };
            row.addEventListener('click', fold);
        }

        /* THE JOINT LISTS UNDER MY OWN (22.09.2026): a heading „Gemeinsame
           Listen", then one row per joint leaf with the sharer's name — a
           point filed there is mine and lies in a list that is not. Only
           where a point is FILED (the window before creating one, the list
           tree of an own point); the sidebar shows these lists under „Geteilt"
           as before. */
        // An own tile with nothing in it is a frame around nothing — whoever
        // keeps only joint lists (Ben in the demo data) sees the joint tile alone.
        // With a tile per Mappe, every one that stayed empty (a Mappe left out, a skipped list).
        if (joint) for (const t of [...container.children]) if (t.childElementCount === 0) t.remove();

        if (joint) {
            const extra = [
                ...[...ownJoint].map(id => state.lists.get(id)).filter(l => l && (children.get(l.id) ?? []).length === 0 && !skip?.(l)),
                // skip reaches the others' joint lists too — the short view
                // before setting a point shows only what is ticked.
                ...jointLists().filter(l => !skip?.(l)),
            ];
            if (extra.length > 0) {
                const head = document.createElement('div');
                head.className = 'list-selection-row is-heading list-selection-joint';
                const headArrow = document.createElement('span');
                headArrow.className = 'list-selection-arrow';
                headArrow.setAttribute('aria-hidden', 'true');
                const headLabel = document.createElement('span');
                headLabel.className = 'list-selection-entry';
                const headText = document.createElement('span');
                headText.className = 'list-selection-name';
                headText.textContent = t('Gemeinsame Listen');
                headLabel.append(headText);
                head.append(headArrow, headLabel);
                const jointTile = tile();
                container.append(jointTile);
                jointTile.append(head);
                for (const list of extra) {
                    const row = document.createElement('div');
                    row.className = 'list-selection-row';
                    row.style.setProperty('--depth', '1');
                    const arrow = document.createElement('button');
                    arrow.type = 'button';
                    arrow.className = 'list-selection-arrow';
                    arrow.tabIndex = -1;
                    arrow.setAttribute('aria-hidden', 'true');
                    const label = document.createElement('label');
                    label.className = 'list-selection-entry';
                    const box = document.createElement('input');
                    box.type = 'checkbox';
                    box.value = String(list.id);
                    box.checked = checked(list);
                    const symbol = document.createElement('span');
                    symbol.innerHTML = shapePoint(list.color, list.symbol);
                    const text = document.createElement('span');
                    text.className = 'list-selection-name';
                    text.textContent = list.name;
                    const who = document.createElement('span');
                    who.className = 'list-selection-owner';
                    who.textContent = ' · ' + (list.sharing
                        ? t('von %s', shortNameOf(list.sharing.display_name ?? ''))
                        : t('meine'));
                    text.append(who);
                    label.append(box, symbol.firstElementChild, text);
                    const count = document.createElement('span');
                    count.className = 'list-selection-count';
                    count.hidden = true;
                    row.append(arrow, label, count);
                    jointTile.append(row);
                    const entry = { id: list.id, parentId: null, hasChildren: false, row, arrow, box, count };
                    rows.push(entry);
                    byId.set(list.id, entry);
                    box.addEventListener('change', () => {
                        applyState();
                        onChange?.();
                    });
                }
            }
        }
        applyState();

        /** Does the row lie under a collapsed branch? */
        function isHidden(entry) {
            let cursor = entry.parentId;
            let guard = 0;
            while (cursor != null && guard++ < 50) {
                if (folded.has(cursor)) return true;
                cursor = byId.get(cursor)?.parentId ?? null;
            }
            return false;
        }

        /** How many checkmarks hang below this list, across all levels. */
        function checkedBelow(id) {
            let n = 0;
            for (const e of rows) {
                // Headings have no checkbox (box = null).
                if (!e.box?.checked) continue;
                let cursor = e.parentId;
                let guard = 0;
                while (cursor != null && guard++ < 50) {
                    if (cursor === id) { n++; break; }
                    cursor = byId.get(cursor)?.parentId ?? null;
                }
            }
            return n;
        }

        function applyState() {
            for (const e of rows) {
                e.row.hidden = isHidden(e);
                if (!e.hasChildren) continue;

                const collapsed = folded.has(e.id);
                e.arrow.innerHTML = disclosureTriangle();
                e.arrow.classList.toggle('is-open', !collapsed);
                e.arrow.title = typeof e.id === 'string'
                    ? (collapsed ? t('Listen der Mappe zeigen') : t('Mappe einklappen'))
                    : (collapsed ? t('Unterlisten zeigen') : t('Unterlisten einklappen'));
                e.arrow.setAttribute('aria-expanded', String(!collapsed));

                const n = collapsed ? checkedBelow(e.id) : 0;
                e.count.hidden = n === 0;
                e.count.textContent = n > 0 ? t('%d gewählt', n) : '';
            }
        }
    }

    /* MY OWN LIST IN A JOINT SHARE (joint lists, step 3, 22.09.2026): the
       root I hung in, or a list inside that branch. Deleting the root HANDS
       IT OVER to the senior member instead — unless I am the only one left —;
       deleting a list inside sends the others' points home. Both are said
       before the button, because both are a surprise otherwise. */
    /** Every list of my own joint branches — roots and sublists (22.09.2026). */
    function ownJointIds(children = listChildren()) {
        const me = start.user?.id;
        const out = new Set();
        for (const c of host.sharedStart) {
            for (const f of c.shares ?? []) {
                if (f.joint !== true || f.user_id !== me) continue;
                for (const id of listBranch(f.list_id, children)) out.add(id);
            }
        }
        /* A HEADING LEFT WITH NOTHING UNDER IT GOES TOO (22.09.2026:
           „die beiden Listen waren Unterlisten der Hauptliste Gebiete — die
           Überliste brauche ich auch nicht mehr sehen"). Up the tree, as long
           as every child of a parent is already in the set: an empty heading
           would say there is something to unfold, and there is not. */
        let grew = true;
        while (grew) {
            grew = false;
            for (const [parentId, kids] of children) {
                if (parentId == null || out.has(parentId) || !state.lists.has(parentId)) continue;
                if (kids.length > 0 && kids.every(k => out.has(k.id))) {
                    out.add(parentId);
                    grew = true;
                }
            }
        }
        return out;
    }

    function ownJointPlace(listId) {
        const me = start.user?.id;
        for (const c of host.sharedStart) {
            for (const f of c.shares ?? []) {
                if (f.joint !== true || f.user_id !== me) continue;
                const alone = Array.isArray(c.members) && c.members.every(m => m.id === me);
                if (f.list_id === listId) return { root: true, alone };
                if ((f.lists ?? []).some(l => l.id === listId)) return { root: false, alone };
            }
        }
        return null;
    }

    return {
        disclosureTriangle,
        drawListSelection,
        listAncestors,
        listBranch,
        listBranchHasPoints,
        listChildren,
        listRoot,
        ownJointIds,
        ownJointPlace,
        topLevel,
    };
}
