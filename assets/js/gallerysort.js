/**
 * Rearranging a find's pictures by dragging them (30.09.2026:
 * „eine eigene Sortierung festlegen, indem ich die Bilder einfach an den
 * neuen Platz schiebe"). Its own module and not a block in map.js: it
 * knows nothing of points, only of a grid and the tiles in it, and the
 * map's gallery hands it the one thing it cannot know — what to do with
 * the new order.
 *
 * A TILE TAKES PART WHEN IT CARRIES data-sort-key — „photo:63" for a
 * voucher photo, „cloud:17" for a cloud recording, one row for both
 * (migration 0148) — and it moves only within its RUN, the unbroken row
 * of such tiles it stands in. The gallery groups its pictures under their
 * subject names and puts the arranged ones of each group first, so a run
 * IS a group's arranged pictures: a heading ends it, and so do the
 * waiting tiles, which have no row yet to carry an order. Dragging a picture into
 * another group would rename it with one stroke of the finger; renaming
 * stays in the photo window, where the name is written.
 *
 * ON THE PHONE THE FINGER RESTS FIRST, then drags. A finger that moves at
 * once is scrolling the window, and the gallery must not steal that —
 * whoever swipes over their pictures to reach the sequences below would
 * otherwise shuffle them. The mouse has no such ambiguity and starts after
 * a few pixels.
 */

/**
 * The order a find's pictures stand in: grouped by subject name, and
 * within a group as they were dragged.
 *
 * 10.09.2026: „Im Fotos-Tab hätte ich gerne, dass die Bilder
 * einsortiert werden, und zwar nach dem Motivnamen. Immer mit
 * Überschrift: Hauptmotiv, dann unten drunter die Bilder, die mit
 * Hauptmotiv gecheckt sind. Dann unten drunter Sporen … und so weiter."
 *
 * HERE AND NOT IN map.js SINCE 05.10.2026, when the photo window's strip
 * took the same groups („gruppiert, so wie in der
 * Punkte-Sidebar"). Two copies of the rule would have parted company at
 * the first change — a picture standing third in the gallery and fifth in
 * the strip.
 *
 * THE ORDER IS THE STOCK LIST (photos.php, PHOTO_TITLES_MACRO / _MICRO,
 * measureStyle.photo_titles): „Hauptmotiv, dann Sporen" is neither an
 * alphabetical order nor an invented one, but exactly the one in which
 * he keeps his suggestions. A name typed freely stands after them —
 * alphabetically among its kind, so that the order does not depend on
 * which picture was uploaded first. WITHOUT A NAME STANDS LAST.
 *
 * THE HAUPTMOTIV LEADS, whatever place it holds in the list
 * (06.10.2026: „dass das Hauptmotiv immer ganz oben ist. Dann vielleicht
 * das Belegfoto"). The list keeps Belegfoto first for its other job — the
 * quickest tap when naming in the woods (photos.php says so) —, and the
 * gallery is arranged, not picked from: the find's face stands first.
 *
 * WITHIN THE GROUP THE DRAGGED ORDER DECIDES (30.09.2026): `order` is
 * sort_order, one number space for voucher photos and cloud recordings
 * (0148). Whatever has none — a waiting photo, a recording from before
 * the migration — stands behind, in the sequence in which it came. The
 * sort is stable, so with an equal number the one lined up first wins:
 * the same rule as on the server (photos_reorder).
 *
 * A TWIN ON THE SERVER: cloud_names_gallery_order() in
 * includes/cloud_names.php numbers the pictures' file names in this very
 * order (06.10.2026) — whoever changes the rule here changes it there.
 *
 * @param {{title: ?string, order: ?number}[]} entries  anything carrying both
 * @param {?string[]} suggestions  the account's subject names, in his order
 * @returns {{name: ?string, items: object[]}[]}  name null for the unnamed
 */
export function subjectGroups(entries, suggestions) {
    const order = (suggestions ?? []).map(w => w.trim().toLowerCase());
    const lead = (name) => name.toLowerCase() === LEAD_SUBJECT;
    const groups = new Map();          // name (as typed) -> entries
    for (const e of entries) {
        const name = (e.title ?? '').trim();
        const key = name === '' ? null : name;
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(e);
    }
    const named = [...groups.keys()].filter(k => k !== null);
    named.sort((a, b) => {
        if (lead(a) !== lead(b)) return lead(a) ? -1 : 1;
        const ia = order.indexOf(a.toLowerCase());
        const ib = order.indexOf(b.toLowerCase());
        if (ia !== -1 && ib !== -1) return ia - ib;
        if (ia !== -1) return -1;
        if (ib !== -1) return 1;
        return a.localeCompare(b, 'de');
    });
    const keys = groups.has(null) ? [...named, null] : named;
    const place = (e) => e.order ?? Number.POSITIVE_INFINITY;
    return keys.map(name => ({
        name,
        items: [...groups.get(name)].sort((a, b) => place(a) - place(b) || 0),
    }));
}

/** The subject name that stands first in every gallery (subjectGroups). */
const LEAD_SUBJECT = 'hauptmotiv';

const HOLD_MS    = 450;  // touch: how long the finger rests before the tile lifts
const HOLD_SLOP  = 10;   // px a resting finger may wander before it counts as a scroll
const MOUSE_SLOP = 6;    // px the mouse travels with the button down before the drag starts

const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');

/**
 * Make a gallery's marked tiles draggable. Called ONCE per gallery: the
 * listeners sit on the grid, not on the tiles, so a gallery redrawn a
 * hundred times keeps working without anyone registering again.
 *
 * @param {HTMLElement} gallery
 * @param {(keys: string[]) => void} onDrop  every marked tile's key in
 *        the order the gallery now shows — called only when a drag changed it
 */
export function sortableGallery(gallery, onDrop) {
    let hold = null;   // a press that may become a drag: {tile, pointerId, x, y, touch, timer}
    let drag = null;   // the drag itself: {tile, pointerId, run, slots, offX, offY, before}
    let swallowClick = false;

    const order = () => [...gallery.children]
        .filter((n) => n.dataset.sortKey)
        .map((n) => n.dataset.sortKey);

    function runOf(tile) {
        const run = [tile];
        for (let n = tile.previousElementSibling; n?.dataset.sortKey; n = n.previousElementSibling) run.unshift(n);
        for (let n = tile.nextElementSibling; n?.dataset.sortKey; n = n.nextElementSibling) run.push(n);
        return run;
    }

    function letGo() {
        if (hold) clearTimeout(hold.timer);
        hold = null;
    }

    /* THE SLOTS ARE MEASURED ONCE, at the lift, relative to the grid. The
       tiles of a run swap places among themselves, but the places stay
       where they are — so the pointer is tested against the places, not
       against tiles that may be halfway through gliding aside. Tested
       against the moving tiles, a pointer resting on a border made two
       neighbours trade places back and forth. Relative to the grid, so a
       wheel scroll during the drag moves the slots along with it. */
    function lift(x, y) {
        /* THE GRIP IS WHERE THE PRESS WAS, not where the drag set in: the
           tile stays under the spot that was taken hold of, instead of
           trailing the pointer by the slop it had to travel first. */
        const { tile, pointerId, x: pressX, y: pressY } = hold;
        letGo();
        const run = runOf(tile);
        const grid = gallery.getBoundingClientRect();
        const slots = run.map((n) => {
            const r = n.getBoundingClientRect();
            return { left: r.left - grid.left, top: r.top - grid.top, width: r.width, height: r.height };
        });
        const home = slots[run.indexOf(tile)];
        drag = {
            tile, pointerId, run, slots,
            offX: pressX - (grid.left + home.left),
            offY: pressY - (grid.top + home.top),
            before: order().join(),
        };
        // ON THE GRID, which never moves in the document — the tiles do.
        try { gallery.setPointerCapture(pointerId); } catch { /* the pointer is gone already */ }
        tile.classList.add('is-dragging');
        gallery.classList.add('is-sorting');
        // The finger covers the tile it lifts; the phone says it instead.
        navigator.vibrate?.(12);
        follow(x, y);
    }

    function follow(x, y) {
        const { tile, run, slots } = drag;
        const grid = gallery.getBoundingClientRect();
        const px = x - grid.left;
        const py = y - grid.top;
        const to = slots.findIndex((s) =>
            px >= s.left && px < s.left + s.width && py >= s.top && py < s.top + s.height);
        const from = run.indexOf(tile);
        if (to !== -1 && to !== from) {
            glide(run.filter((n) => n !== tile), () => {
                /* THE OTHERS MOVE, NOT THE LIFTED TILE. The finger's touch
                   events keep going to the node they started on; taking
                   that node out of the document, even for the blink of
                   an insertBefore, is the one move here that could cut
                   the drag off. Walking the neighbours past it in turn
                   leaves it where it is and gives the same row. */
                if (to > from) for (let k = from + 1; k <= to; k++) tile.before(run[k]);
                else for (let k = from - 1; k >= to; k--) tile.after(run[k]);
                run.splice(from, 1);
                run.splice(to, 0, tile);
            });
        }
        const home = slots[run.indexOf(tile)];
        tile.style.transform =
            `translate(${x - drag.offX - (grid.left + home.left)}px, ${y - drag.offY - (grid.top + home.top)}px)`;
    }

    /** Move the tiles, then let each glide from where it stood — the eye
     *  follows a neighbour stepping aside, a jump it has to look for. */
    function glide(nodes, move) {
        if (reducedMotion?.matches) { move(); return; }
        const first = new Map(nodes.map((n) => [n, n.getBoundingClientRect()]));
        move();
        for (const n of nodes) {
            n.style.transition = 'none';
            n.style.transform = '';
            const last = n.getBoundingClientRect();
            const was = first.get(n);
            const dx = was.left - last.left;
            const dy = was.top - last.top;
            if (dx === 0 && dy === 0) continue;
            n.style.transform = `translate(${dx}px, ${dy}px)`;
            void n.offsetWidth;
            n.style.transition = 'transform .15s ease';
            n.style.transform = '';
        }
    }

    /* A CANCELLED DRAG KEEPS WHERE IT IS, like a finished one: the order
       the eye saw last is the one the user meant, and snapping back on a
       system interruption would undo work nobody asked to undo. A tile the
       gallery lost in the meantime — a sync redrew it under the finger —
       ends the drag without a word: its order belongs to a picture that
       is no longer standing there. */
    function drop() {
        const { tile, run, pointerId, before } = drag;
        drag = null;
        try { gallery.releasePointerCapture(pointerId); } catch { /* released already */ }
        tile.classList.remove('is-dragging');
        gallery.classList.remove('is-sorting');
        for (const n of run) {
            n.style.transition = '';
            n.style.transform = '';
        }
        // The click the release is about to fire would open the picture.
        swallowClick = true;
        setTimeout(() => { swallowClick = false; }, 400);
        if (!tile.isConnected) return;
        const after = order();
        if (after.join() !== before) onDrop(after);
    }

    gallery.addEventListener('pointerdown', (e) => {
        swallowClick = false;
        if (hold || drag) return;
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        const tile = e.target.closest?.('[data-sort-key]');
        if (!tile || tile.parentElement !== gallery || runOf(tile).length < 2) return;
        hold = { tile, pointerId: e.pointerId, x: e.clientX, y: e.clientY,
            touch: e.pointerType !== 'mouse', timer: null };
        if (hold.touch) {
            hold.timer = setTimeout(() => { if (hold) lift(hold.x, hold.y); }, HOLD_MS);
        }
    });

    gallery.addEventListener('pointermove', (e) => {
        if (hold && e.pointerId === hold.pointerId) {
            const travelled = Math.hypot(e.clientX - hold.x, e.clientY - hold.y);
            if (hold.touch) {
                if (travelled > HOLD_SLOP) letGo();
            } else if (travelled > MOUSE_SLOP) {
                lift(e.clientX, e.clientY);
            }
            return;
        }
        if (drag && e.pointerId === drag.pointerId) {
            if (!drag.tile.isConnected) { drop(); return; }
            follow(e.clientX, e.clientY);
        }
    });

    for (const type of ['pointerup', 'pointercancel']) {
        gallery.addEventListener(type, (e) => {
            if (hold && e.pointerId === hold.pointerId) letGo();
            if (drag && e.pointerId === drag.pointerId) drop();
        });
    }

    /* ONCE LIFTED, THE FINGER BELONGS TO THE TILE. Which way a touch goes —
       scroll or not — is settled at the touch's start, from touch-action,
       and changing that mid-gesture does nothing; refusing the touchmove
       is the one lever left. NOT PASSIVE, or the refusal is ignored. */
    gallery.addEventListener('touchmove', (e) => {
        if (drag) e.preventDefault();
    }, { passive: false });

    // A long press on a picture offers „save image" — not while it is lifted.
    gallery.addEventListener('contextmenu', (e) => {
        if (hold || drag) e.preventDefault();
    });

    // Before the tile's own click handler, which would open the picture.
    gallery.addEventListener('click', (e) => {
        if (!swallowClick) return;
        swallowClick = false;
        e.preventDefault();
        e.stopPropagation();
    }, true);
}
