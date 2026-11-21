/**
 * Who a find belongs to and what one may do with it: is it a find at all
 * (does it lie in a species list), is it somebody else's (a foreign point),
 * and which rights do the shares around it grant (sharedRights).
 *
 * OUT OF map.js ON 11.10.2026 with the list tree (listtree.js) and the
 * point window (pointwindow.js), because the window asks all three on
 * every fill and the Fundbrowser mounts it: one answer for both pages,
 * never a second reading of „what may this share do".
 *
 * THE HOST: listRoot (the list tree's), state (the finds in store) and
 * sharedLists (the lists others share with me, with the rights each
 * recipient row carries).
 */



export function mountFindRights(host) {
    const {
        listRoot,
        state,
    } = host;

    /**
     * Is the point a find — does it carry a collection number?
     *
     * When one of its lists is a species list — a waypoint has no number.
     * For FOREIGN points the server writes the verdict onto every shared list
     * row (list_branch_root in container.php), and this side reads it off
     * there. Foreign numbers show since
     * 23.08.2026: the number is the shorthand friends cite to
     * each other — "Nummer 544" beats describing species, day and place.
     */
    function pointIsFind(point) {
        return point.lists?.some(id =>
            listRoot(id)?.is_species || host.sharedLists.get(id)?.is_species) ?? false;
    }

    /**
     * FOREIGN POINTS IN THE DETAIL WINDOW
     *
     * Until 14.08.2026 they had a modal of their own (#geteiltfenster) that
     * listed the same details once more in its own form — and neither of the
     * two windows could do what the other could: the modal showed neither
     * photos nor area nor municipality, the detail window knew no foreign
     * points. Two windows for one point are a fork at which every future
     * detail must be built in twice, and the second time you forget it.
     *
     * NOW THE DETAIL WINDOW SHOWS BOTH. What the sharer allowed on his share
     * (level 3) stays operable; everything else stands greyed out. GREYED OUT
     * AND NOT GONE: it is the information about the find, and that one wants
     * to see — just not change. An empty field one may not fill says "nothing
     * here" and is thereby information too.
     *
     * The server sieves every input once more against the rights
     * (fremde_eingabe_sieben) — this window is convenience, not the boundary.
     */

    /** The foreign point for an id, or null if it is an own one. */
    function foreignPoint(pointId) {
        if (pointId === null || pointId === undefined) return null;
        if (state.points.has(pointId)) return null;
        return state.sharedPoints.get(pointId) ?? null;
    }

    /** The united rights with which I see this foreign point — it can arrive
     *  via several shares, then whatever any one of them allows applies. */
    function sharedRights(point) {
        /* AN EXCURSION FIND ANSWERS EVERY RIGHT WITH YES
           (27.08.2026: „da ich der Exkursionsersteller war, habe ich da auch
           das Recht, einen Punkt zu löschen und zu bearbeiten"). The rights
           of a container point hang on its LISTS — a guest find hangs in
           none, so the set below came back empty and the whole window stood
           locked.

           Not a filled Set but an always-yes: the field rights are asked as
           'field:<name>' with names nobody can enumerate here. The SERVER
           stays the judge — it asks the leadership per request
           (excursion_point_leader_scope), and 'editable' merely repeats its
           answer. */
        if (point.excursion_id != null && point.editable) {
            return { has: () => true };
        }
        const rights = new Set();
        for (const id of point.lists) {
            for (const r of host.sharedLists.get(id)?.sharing?.rights ?? []) rights.add(r);
        }
        return rights;
    }

    return {
        foreignPoint,
        pointIsFind,
        sharedRights,
    };
}
