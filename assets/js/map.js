/**
 * The map.
 *
 * Structure:
 *   api        — thin wrapper around fetch, takes care of CSRF and errors
 *   state      — points, lists, selection; one source of truth
 *   layers     — base maps and overlays from the registry
 *   marker     — Leaflet markers, mirrored from state.points
 *   sidebar    — lists on the left
 *   detail     — window on the right
 */

import './legacy.js';
import { t } from './i18n.js';
import { pointHaystack } from './haystack.js';
import { findNumberFallback, findNumberReserved, readFindNumber, normalizeFindNumber, numberDate, nextFreeNumber, findNumberKey } from './findnumber.js';
import { api, sendEntry, request } from './api.js';
import * as queue from './queue.js';
import * as areas from './areas.js';
import { buildLayers } from './layers.js';
import { tileUrls as tileUrlsFor, drawsAt } from './tileurls.js';
import { settings } from './settings.js';
import { createDirection } from './direction.js';
import { headingOf } from './heading.js';
import { declination } from './declination.js';
import { confirm, report, choose, ask, infoWindow } from './dialogs.js';
import { loadSpeciesList, refreshSpeciesList, searchSpeciesLocally, familiarFirst, groupOf, listGroups, setNeededGroups, syncPackages } from './species.js';
import * as photos from './photos.js';
import * as galleryOriginals from './galleryoriginals.js';
import * as formState from './formstate.js';
import { recognizeCoordinates } from './coordinates.js';
import { icon, iconHtml } from './icons.js';
import { shapeGeometry, shapePoint } from './shapes.js';
import { personName } from './names.js';
import * as chat from './chat.js';
// No named import: the module binds itself to every multi-line field and
// watches the document for new ones — there is nothing to call here. Why it
// is built that way is explained in its header.
import './autoheight.js';
import './fullscreen.js';
import './scrollkeep.js';
// The same kind: an X in every filled text field. It skips fields that
// already sit in a .field-with-x shell — the sidebar search wires itself.
import './clearable.js';
// And one more: the administrator's reminder of a client secret running out
// opens itself once a day, when the page drew its window (reminder.js).
import './reminder.js';
import { findPlace, findPlaces } from './placesearch.js';
import * as polygon from './polygon.js';

import * as pointStore from './pointstore.js';
// The recorded tracks — their own store, the same buckets (trackstore.js).
import * as trackStore from './trackstore.js';
// The promise applies to the origin, not the database — it lives there because
// that is where it was needed, and it protects the queue just as well. See below.
import { requestPersistence } from './photostore.js';
import { identificationMatches, workedMatches, openMarksIn, familiarName, CERTAINTY_FIELD } from './identification.js';
import * as filter from './filter.js';
import * as timeRange from './timerange.js';
import * as tour from './tour.js';
import { differences, outlineEqual } from './fieldtimes.js';
import { tickBox, tickOf } from './tickbox.js';
import { sortableGallery, subjectGroups } from './gallerysort.js';
import { mountPhotoWindow } from './photowindow.js';
import { mountPlates } from './plates.js';
import { shortcutsProvide } from './shortcuts.js';
import { mountListTree } from './listtree.js';
import { mountFindRights } from './findrights.js';
import { mountPointWindow, pointWindowElements } from './pointwindow.js';
import { mountPhotoGallery } from './photogallery.js';
import { mountPointFiles } from './pointfiles.js';
import * as listShare from './recipients.js';
import { readableDate } from './dates.js';
import { findDetails, placeWritten } from './finddetails.js';

const start = window.START_DATA;

/* THE GUEST OF AN EXCURSION (stage 4, 22.08.2026): the same map — full
   screen, location, point setting, offline queue —, but the start data
   came stripped (index.php: no lists, no containers, no friends) and
   the API holds him to his own finds of his running excursion
   (points.php, excursion_guest_api). This flag guards the handful of
   subsystems whose data or endpoints a guest does not have; everything
   else degrades through the empty lists on its own. */
const GUEST = Boolean(start.guest);

/**
 * Whether the server can accept outlines.
 *
 * Before migration 0034 the column does not exist; the server would then
 * silently not accept the outline. A painstakingly traced area that comes
 * back as an ordinary point would be worse than a tool that does not exist
 * in this window yet.
 *
 * AT THE VERY TOP, although it belongs with the areas further down: it is
 * read in functions that live everywhere, and a const cannot be touched
 * before its line. If it sat below, the page would break the moment someone
 * moved one of those calls one line further up.
 */
const outlinesPossible = start.outlines !== false;

/* THE STATUS ROW'S STATE, AT THE TOP for the same reason (the maintainer's live
   console, 15.10.2026): status() is a function of the very end of this file,
   but it is called long before — the point window's mount applies the stored
   width, the map resizes, the resize ends a move, and the zoom hint writes a
   status line. With these three below, that was „can't access lexical
   declaration 'statusRow' before initialization", thrown through the mount,
   and the map's script stopped half built. */
const statusRow = document.getElementById('status-row');
let statusTimer  = null;
let statusSource = null;   // who wrote the message (status, removeStatus)

/**
 * Did the view come from the default because there was neither a remembered
 * one nor a point? Then establishInventory() follows up as soon as the points
 * are there.
 *
 * UP HERE AND NOT AT loadView(), for the same reason as outlinesPossible
 * above — and this time it is not precautionary but after the fact: it used
 * to sit down at loadView(), and loadView() runs four hundred lines
 * further up. A let cannot be touched before its line.
 *
 * WHY NOBODY NOTICED. The assignment sits in the LAST branch of the function
 * — it is only reached when there is neither a remembered view nor a point.
 * So exclusively on a fresh login in a fresh browser. Every machine that has
 * ever been worked on has a remembered view and turns back before that.
 * Exactly the kind of trap you never catch while trying things out and the
 * first unfamiliar machine catches immediately.
 */
let viewWasDefault = false;

// Adopt the remembered list filter, but only lists that still exist —
// otherwise the map would stay inexplicably empty after deleting a list.
const existingLists = new Set(start.lists.map(l => l.id));

/**
 * Adopt the remembered selection, but only lists that still exist — otherwise
 * the map would stay inexplicably empty after deleting a list.
 *
 * The difference between "nothing remembered" and "explicitly nothing chosen"
 * matters: on the very first start all lists are on, so you see anything at
 * all. Whoever deselects everything afterwards gets an empty map and keeps it.
 */
const rememberedRaw = settings.get('visibleLists', null);
const rememberedFilter = Array.isArray(rememberedRaw)
    ? rememberedRaw.filter(id => id === 0 || existingLists.has(id))
    : [...existingLists];

/* A LIST THIS DEVICE HAS NEVER SEEN STARTS TICKED (23.09.2026). The
   remembered choice is a set of ids; a list that came into being
   ELSEWHERE — on another device, or by a migration such as 0113, which
   gave every test account „Flechten / Pflanzen / Pilze" — stood in no set,
   and its points lay nowhere on the map (the night of 22.09. showed what
   that feels like). The device keeps the ids it has met; whatever is new
   since is added to the choice once, and from then on the tick is the
   user's. The very first visit only records: ticking everything a user
   had deliberately switched off would be the opposite mistake. */
{
    const known = settings.get('knownLists', null);
    if (Array.isArray(known) && Array.isArray(rememberedRaw)) {
        const seen = new Set(known);
        for (const id of existingLists) {
            if (!seen.has(id) && !rememberedFilter.includes(id)) rememberedFilter.push(id);
        }
    } else if (Array.isArray(rememberedRaw)) {
        /* THE FIRST VISIT WITH THIS CODE has no record yet — and may come
           after migration 0113 turned a ticked list („Meine Punkte") into a
           heading with fresh sublists below it. A heading holds nothing,
           so the tick on it would show nothing: its sublists take it over.
           Only here, once; later a heading's tick is the user's affair. */
        const parentOf = new Map(start.lists.map(l => [l.id, l.parent_id ?? null]));
        for (const l of start.lists) {
            if (rememberedFilter.includes(l.id)) continue;
            for (let p = parentOf.get(l.id); p != null; p = parentOf.get(p)) {
                if (rememberedFilter.includes(p)) { rememberedFilter.push(l.id); break; }
            }
        }
    }
    settings.set('knownLists', [...existingLists]);
}

const state = {
    points:   new Map(start.points.map(p => [p.id, p])),
    /* THE RECORDED TRACKS, by their device-minted client_id (11.09.2026,
       the first step of the maintainer's „Wegstrecken pauschal an- oder
       ausschalten"). Filled from the device's store after the points,
       each with its DRAWING — the sieved, simplified line the buckets
       carry since migration 0100; the original positions stay on the
       server. drawTracks() reads from here. */
    tracks:   new Map(),
    lists:   new Map(start.lists.map(l => [l.id, l])),
    /* THE MAPPEN (migration 0168): a name that gathers lists in the
       sidebar, nothing else (includes/folders.php). Their fold state lives
       in `collapsed` beside the headings', under 'f' + id — a list id is
       a number, so the two can never meet. */
    folders: new Map((start.folders ?? []).map(f => [f.id, f])),
    // Visible lists. Empty means: show all.
    visible: new Set(rememberedFilter),
    // Collapsed branches. PURELY A MATTER OF OVERVIEW: what is collapsed
    // stays visible on the map — otherwise it would be a second, hidden
    // selection next to the real one, and nobody would figure out why points
    // are missing.
    collapsed: new Set(
        (settings.get('collapsedLists', []) || []).filter(id => existingLists.has(id)
            || (start.folders ?? []).some(f => 'f' + f.id === id))
    ),
    selectedId: null,
    /* THE POINT THE PHONE'S PEEK POINTS AT (peekPoint, further down). It
       carries the SAME ring as the selected one — the ring means "this
       here is what I mean right now", and the peek says nothing else —
       but no open window and no drag handle.

       IT LIVES HERE AND NOT BESIDE THE PEEK, although everything else
       about the peek stands together down there: drawMarker() has to read
       it, and that function is declared thousands of lines earlier. A
       `let` beside the peek block would be in its temporal dead zone for
       every call that comes before the block is reached. */
    peekedId: null,
    // The filter rows (assets/js/filter.js) — the same as in Organisieren.
    // Filled further down from the settings once the module is there;
    // this here is only the empty starting position.
    terms: [],
    // rewritten once right after construction — see below.
    /* REMEMBERED SINCE 30.08.2026 („die letzte Tour … wird
       wohl nicht persistiert … Überprüft doch mal den ganzen Filter") —
       like every condition below, restored from the filterConditions
       bundle further down. The old worry (a filter you do not see at
       start makes you believe your points are lost) is answered by the
       Filter button's count, which shows active conditions even folded.
       Days as "YYYY-MM-DD", empty means open — the same form as in
       Organisieren and the auto lists (assets/js/timerange.js). */
    timeRange: { from: '', to: '' },
    // The tour filter — one name, or empty. It replaces the time range and
    // vice versa (see the time filter block): a name and a date range at
    // once would intersect to nothing anyone means.
    tour: '',
    // The only time filter that means the MISSING find time — and therefore
    // looks strictly at found_at, not at the day: a point whose day comes
    // from the creation time is exactly one of those you are looking for
    // here. Organisieren had it long since; in the sidebar it was missing.
    noTime: false,

    /* THE SYSTEMATIC RANKS above the genus (26.08.2026):
       kingdom, phylum, class, order, family. Empty means „no condition"
       for that rank; several set ranks must all hold.

       THE GENUS IS NOT AMONG THEM, deliberately — it stands in the title
       of every find, so the text rows answer it already, and a second way
       to the same answer is a second thing that can disagree. */
    taxonomy: { kingdom: '', phylum: '', class: '', order: '', family: '', genus: '' },
    /* THE HEIGHT BAND (26.08.2026). from/to in metres; the
       upper end at filter.ELEVATION_MAX means „and above", so the band is
       open there and an alpine find does not fall out because the scale
       stops. Both at 0 and MAX is no condition at all. */
    elevation: { from: 0, to: 2500 },
    // The identification group (identificationMatches in
    // identification.js): the collective hook, the checked abbreviation
    // families from the title and the checked steps of the field
    // Bestimmungssicherheit. worked is the material status beside them
    // ('' | 'yes' | 'no', workedMatches) — an AND, not a fourth OR layer.
    identification: { open: false, marks: [], certainty: [], worked: '' },

    // The checked Red List threat categories ("1", "3", "*" …, see
    // RL_CATEGORIES in filter.js). Empty means all — not none.
    rl: [],
    // The DGfM's lists (17.09.2026): the checked food-value marks
    // (EDIBILITY_MARKS in filter.js, empty means all) and the hook „nur
    // Verantwortungsarten" under the Red List categories.
    edibility: [],
    responsibility: false,

    // Sort order of the point list: 'time' or 'name'. Remembered — whoever
    // has switched it once wants to find it again next time.
    sorting: settings.get('sorting', 'time') === 'name' ? 'name' : 'time',

    // Which name groups are expanded. Deliberately for this session only:
    // the point of the grouping is the short list, and one that has
    // remembered that three weeks ago everything was open is not one.
    openNames: new Set(),
    placing: false,
};
/* THE LIST TREE — out of this file on 11.10.2026, into listtree.js (the point window needs it, and the Fundbrowser mounts the point window).
   What it asks of the map, in one place: the values and functions below. */
const listTree = mountListTree({
    start,
    state,
    drawList,
    shortNameOf,
    get sharedLists() { return sharedLists; },
    get sharedStart() { return sharedStart; },
});
const {
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
} = listTree;
/* WHO A FIND BELONGS TO AND WHAT I MAY DO WITH IT — out of this file on 11.10.2026, into findrights.js (the Fundbrowser's window asks the same questions).
   What it asks of the map, in one place: the values and functions below. */
const findRights = mountFindRights({
    listRoot,
    state,
    get sharedLists() { return sharedLists; },
});
const {
    foreignPoint,
    pointIsFind,
    sharedRights,
} = findRights;



// ONE-TIME REWRITE OF THE REMEMBERED SELECTION. Up to here a selected parent
// list meant "the whole branch"; from now on every leaf selects for itself
// (single selection, see visibleBranch). So that nothing disappears from the
// map during the switch, every selected parent list gets its descendants
// added explicitly ONCE. Only once, with a marker: whoever deselects a leaf
// afterwards, while the parent id is still lying around, must not find it
// selected again on the next start.
//
// The parent id itself stays put: points that still live directly in a
// heading from the old world hang on exactly it.
if (settings.get('visibleIndividually', false) !== true) {
    for (const id of [...state.visible]) {
        if (id === 0) continue;
        for (const descendant of listBranch(id)) state.visible.add(descendant);
    }
    settings.set('visibleIndividually', true);
    settings.set('visibleLists', [...state.visible]);
}

const marker = new Map();   // pointId -> L.Marker
const polygons = new Map(); // pointId -> L.Polygon, only for points with an outline

// ---------------------------------------------------------------------------
// Shared: the collection containers (see includes/container.php)
//
// FOREIGN POINTS ARE NOT OWN ONES. They live in a store of their own
// (state.sharedPoints), never go through the queue, know no editing and
// no selecting — a click opens the reading window. Anything else would mean
// remembering at every write site that the point belongs to someone else.
// ---------------------------------------------------------------------------

const sharedStart = Array.isArray(start.shared) ? start.shared : [];

// Every shared list under its id — for color, shape and name of the foreign
// points. Ids are unique across all accounts (one table), so the store next
// to state.lists does not collide.
const sharedLists = new Map();

/* HOW A FOREIGN LIST LOOKS HERE IS MY BUSINESS, not its owner's.
   Colour and shape arrive with the share, and there they are the owner's
   decision — but on my map they compete with MY lists, and two branches
   in the same red are two branches I cannot tell apart. The override
   lives on the device (settings), never on the server: it changes nothing
   for anybody else, and there is nothing to ask permission for.

   IT IS APPLIED HERE, and every drawing path has to ask HERE. The
   markers do so by themselves (pointColor/pointShape look the list up in
   this map); the sidebar row did NOT — it read colour and shape straight
   out of the share, and the override went nowhere. Measured, not assumed.
   The owner's values stay next to it as 'original', for the reset and for
   saying what they had intended. */
/* SINCE 09.10.2026 THE LOOK IS THE ACCOUNT'S, not the device's (
   „individuell jedem Benutzer, also nicht jedes Gerät") — it comes with the
   share as l.look (map_shared_looks, migration 0160). What a device still
   holds from before is carried over once (below) and then forgotten. */
const sharedLooks = settings.get('sharedLooks', {}) || {};
const sharedLooksToCarry = [];
for (const c of sharedStart) {
    for (const f of c.shares ?? []) {
        for (const l of f.lists ?? []) {
            const device = sharedLooks[l.id] ?? null;
            if (device && l.look === null) sharedLooksToCarry.push([l.id, device]);
            const look = l.look ?? device;
            sharedLists.set(l.id, {
                ...l,
                ...(look?.color  ? { color:  look.color  } : {}),
                ...(look?.symbol ? { symbol: look.symbol } : {}),
                original: { color: l.color, symbol: l.symbol },
                containerId: c.id, sharing: f, container: c,
            });
        }
    }
}

/* THE EXCURSIONS ONE LEADS, and their foreign finds
   (26.08.2026). The points go into the SAME store as the container ones:
   they are foreign in the same way, and everything that store can do —
   the ring on the marker, the drag right, every filter — they need too. A
   third stock beside it would mean teaching each of those a third set.

   What tells them apart is the excursion reference on the point:
   sharedVisible() reads it where a container point is read for its
   lists. */
const excursionStart = Array.isArray(start.excursions) ? start.excursions : [];

/* THE SHORT NAMES OF EVERYBODY ONE CAN SEE: the company decides how short
   a name may be (see names.js), so it has to be the SAME everywhere —
   computed per row, the same person could stand as "Finn" next to one list
   and as "Finn Ho" next to the next. names.js keeps the one company of the
   page (personName); this map once kept its own of sharers alone. */
/** The short form for the sidebar — the full name when it is not in the set. */
/* The short form — the one company of the whole page since 15.11.2026
   (names.js, personName: friends and sharers together), so a name reads the
   same in the sidebar as in the edit bar and on a tile. */
function shortNameOf(name) {
    return personName(name);
}

/**
 * What this container is called HERE.
 *
 * ONE PLACE FOR THE DECISION. The own name (migration 0062) beats the
 * creator's — but only if there is one, and only in the places where one
 * reads the container: the sidebar row, the chat heading, the info window.
 * Written out at each of them, one of them would keep showing the old name
 * on the day a fourth is added.
 */
function containerName(c) {
    return c.own_name || c.name;
}

// The unread counts come with the document; afterwards the chat module
// keeps them and says when the sidebar has to be redrawn. The friends'
// counts travel the same way and ride the same quiet poll — one asker
// for both kinds of room.
chat.adoptStart(sharedStart);
chat.adoptFriends(start.friends ?? []);
chat.onUnreadChange(() => drawList());
/* FOR EVERYBODY WHO IS SIGNED IN (08.11.2026). Until then the poll ran
   only for whoever had a share, a friend or an open request of his own
   (13.09.2026) — and so never for the one person a first friendship
   request is meant for: somebody with no friend yet. His request reached
   him only in the administration („und nicht erst umständlich
   über die Verwaltung"). A request can come to anyone at any time, so
   everyone asks — one small question every thirty seconds, and only
   while the page is visible (fetchUnread). */
if (!GUEST) chat.watchUnread();

/* The device's old looks into the account, once — then the device forgets
   them, so a change on the phone is not undone by the tablet's old value. */
if (sharedLooksToCarry.length > 0 && !GUEST) {
    (async () => {
        const left = { ...(settings.get('sharedLooks', {}) || {}) };
        for (const [listId, look] of sharedLooksToCarry) {
            try {
                await request('list_recipients.php', { method: 'POST',
                    body: JSON.stringify({ action: 'look', list: listId, color: look.color ?? null, symbol: look.symbol ?? null }) });
                delete left[listId];
            } catch {
                return;   // offline or before 0160 — next visit
            }
        }
        settings.set('sharedLooks', left);
    })();
}

/** Store or clear the look of a foreign list, then redraw everything. */
function setSharedLook(listId, look) {
    /* INTO THE ACCOUNT (migration 0160); the device keeps it only when the
       server cannot be reached, and carries it over on the next visit. */
    request('list_recipients.php', { method: 'POST',
        body: JSON.stringify({ action: 'look', list: listId, color: look?.color ?? null, symbol: look?.symbol ?? null }) })
        .then(() => {
            const all = { ...(settings.get('sharedLooks', {}) || {}) };
            if (listId in all) { delete all[listId]; settings.set('sharedLooks', all); }
        })
        .catch(() => {});
    /* A COPY, AND THAT IS NOT TIDINESS. settings.get() hands out the LIVE
       value, and settings.set() refuses to write what it considers
       unchanged — it compares the stored value against the one handed in.
       Modify the object in place and the comparison is the object against
       itself: equal, so nothing is written.

       It cost exactly one measurement to see: the row took on the new
       colour (that goes through sharedLists), the storage kept the old one
       (that goes through here), and after a reload the choice was gone.
       The first change always worked, because there was nothing stored yet
       and get() returned the fresh fallback. */
    const all = { ...(settings.get('sharedLooks', {}) || {}) };
    if (look) all[listId] = look; else delete all[listId];
    settings.set('sharedLooks', all);

    const list = sharedLists.get(listId);
    if (list) {
        list.color  = look?.color  ?? list.original.color;
        list.symbol = look?.symbol ?? list.original.symbol;
    }
    drawMarker();
    drawList();
}

state.sharedPoints = new Map();  // pointId -> point, foreign only
state.visibleShared = new Set(
    (settings.get('visibleShared', []) || []).filter(id => sharedLists.has(id))
);
state.collapsedShared = new Set(settings.get('collapsedShared', []) || []);

/* FINDS FRIENDS SHOW ME — „Direkt teilen" (migration 0149,
   includes/point_hints.php). null while the table is missing: then no row
   under „Geteilt" and no tile in the point window. The finds themselves
   come like a container's, through buckets under „h:" (syncHints); the
   rows „Von <Name>" are counted from what lies on the device.

   HIDDEN AND NOT VISIBLE is remembered, the other way round from the
   shared lists: a friend who shows me a find wants me to see it, and a
   new friend's row must not start switched off. */
const hintStart = start.hints ?? null;
state.hiddenHints = new Set(settings.get('hiddenHints', []) || []);
/* SINCE 09.10.2026 A SHARED FIND IS SWITCHED ON ITS OWN, under „Von <Name>"
   („direkt von Finn Boletus edulis"). hiddenHints still holds
   friends hidden as a whole from before; a find is hidden when either says
   so, and ticking one find of such a friend turns his row-wide switch into
   single ones (hintPointToggle). */
state.hiddenHintPoints = new Set(settings.get('hiddenHintPoints', []) || []);

/** Is this directly shared find switched off? */
function hintHidden(point) {
    return state.hiddenHints.has(point.hint_from) || state.hiddenHintPoints.has(point.id);
}
let hintsSynced = false;   // until the first sync the document's counts stand in

/* MY OWN JOINT LISTS ARE CHOSEN UNDER „GETEILT" (22.09.2026:
   „Es sind eben Sonderlisten … oben bei meinen eigenen möchte ich sie
   gar nicht mehr sehen"). They no longer stand under „Meine Listen", so
   their choice moves from the own set into the shared one — once, here —
   and the rows below govern them from then on. Without this the
   remembered own choice would show their points with no row saying so. */
{
    let moved = false;
    /* ONCE PER DEVICE, AN OWN JOINT LIST THAT STANDS IN NEITHER CHOICE IS
       TICKED (23.09.2026). Measured on the maintainer's dump: the two Gebiete
       lists were born on the 21st, before a new list started ticked, hung
       in jointly the same evening and so left „Meine Listen" with today's
       change — never ticked anywhere, their five night points lay
       nowhere on the map, and the tour answered „Nichts gefunden". A
       list one made and shared is one one means to see; from then on
       the tick under „Geteilt" is the user's and stays what he sets. */
    const healed = settings.get('ownJointTickedOnce', false) === true;
    const ownJoint = ownJointIds();
    /* AND BACK AGAIN WHEN IT STOPS BEING JOINT (Finn, 20.10.2026, filmed:
       every box empty, „Listen 1", his 451 finds on the map). A list whose
       „Gemeinsam bearbeiten" was switched off stands under „Meine Listen"
       again, but its tick still lay in the shared set — where no row
       showed it any more, and where the filter at its reading above lets
       it pass, because an own list one shares at all is among sharedLists.
       Its points stayed on the map, and no box could take them off. The
       tick goes home with the list, so the box under „Meine Listen" shows
       what the map shows and can switch it off. */
    for (const id of [...state.visibleShared]) {
        if (sharedLists.get(id)?.sharing?.user_id === start.user?.id && !ownJoint.has(id)) {
            state.visibleShared.delete(id);
            state.visible.add(id);
            moved = true;
        }
    }
    for (const id of ownJoint) {
        if (state.visible.has(id)) {
            state.visible.delete(id);
            if (sharedLists.has(id)) state.visibleShared.add(id);
            moved = true;
        } else if (!healed && sharedLists.has(id) && !state.visibleShared.has(id)
            && (listChildren().get(id) ?? []).length === 0) {
            state.visibleShared.add(id);
            moved = true;
        }
    }
    if (!healed) settings.set('ownJointTickedOnce', true);
    if (moved) {
        settings.set('visibleLists', [...state.visible]);
        settings.set('visibleShared', [...state.visibleShared]);
    }
}

/* THE EXCURSION FINDS, into the SAME store — filled here and not further
   up, because state.sharedPoints only exists from the line above: a fill
   before it wrote into undefined.
 *
 * WHICH EXCURSIONS ARE SHOWN is remembered like every other choice of the
 * sidebar. 'all' is the entry at the top and means every excursion,
 * including ones that only appear later — ticking them one at a time
 * cannot say that. An id that no longer exists is dropped on reading, as
 * with the shared lists above. */
/* THE FALLBACK FOR A SERVER WITHOUT BUCKETS — and nothing more, since
   the excursions got their own (26.08.2026, hours after they got their
   section).
   
   FIRST they rode along in the document, and that was a second origin
   inside state.sharedPoints: rebuildShared() builds a fresh Map out of
   IndexedDB, so everything that had never been in there was swept away.
   They flashed up once from the start data and were gone with the first
   sync — the maintainer saw exactly that („ganz kurz die sechs Punkte, beim
   weiteren Laden wieder null"), and this function was the patch.
   
   NOW THEY LIE IN THE STORE like every other foreign point, and the patch
   has become what it should have been from the start: the branch for a
   server where migration 0038 is missing. With buckets ready
   start.excursionPoints is empty and this does nothing. */
/* ONE FIND, SEVERAL ROADS. A find shown to me (bucket „h:") may come
   through a share as well (bucket „g…:") — then it is one marker, and the
   copy keeps both: the share's lists, so its rights and its row hold, and
   the hint's mark, so „Von <Name>" counts it and the door is offered. The
   copy that came with lists is the base; the hint only lends its fields. */
function addForeign(p) {
    const had = state.sharedPoints.get(p.id);
    if (!had || (had.hint_from == null && p.hint_from == null)) {
        state.sharedPoints.set(p.id, p);
        return;
    }
    const base = (p.lists?.length ?? 0) >= (had.lists?.length ?? 0) ? p : had;
    const hint = p.hint_from != null ? p : had;
    state.sharedPoints.set(p.id, {
        ...base,
        lists: [...new Set([...(had.lists ?? []), ...(p.lists ?? [])])],
        hint_from: hint.hint_from,
        hint_from_name: hint.hint_from_name,
        hint_look: hint.hint_look,
        hint_fields: hint.hint_fields,
    });
}

function addExcursionPoints() {
    for (const p of start.excursionPoints ?? []) {
        state.sharedPoints.set(p.id, p);
    }
    // The finds shown to me, where the server has no buckets yet — then
    // they came whole with the document, like the containers' points.
    for (const p of hintStart?.points ?? []) {
        addForeign(p);
    }
    /* AND THE FINDS I CO-OWN (11.09.2026) — the same road, for the same
       reason: they are foreign points that come whole in the document and
       would be swept out of the store-built Map otherwise. Marked
       co_owned; sharedVisible() and the window read that. */
    for (const p of start.coOwnedPoints ?? []) {
        state.sharedPoints.set(p.id, p);
    }
}
addExcursionPoints();
const excursionIds = new Set(excursionStart.map(e => e.id));
state.visibleExcursions = new Set(
    (settings.get('visibleExcursions', []) || [])
        .filter(v => v === 'all' || excursionIds.has(v))
);

// Collapsed SECTIONS of the list page ('mine', 'shared') — one level above
// the branches. Its own memory, not in zugeklappt/zugeklapptGeteilt: those
// hold list ids, and 'mine' is not one.
state.sectionsCollapsed = new Set(settings.get('sidebarSectionsCollapsed', []) || []);

/** A foreign point is visible when one of its lists is selected — the same
 *  text and time filters as for own points apply along with it. */
/**
 * Does the point belong to what the time filter picked — a tour or an
 * excursion?
 *
 * ONE FUNCTION FOR BOTH STOCKS, because both have to answer identically:
 * an excursion that showed the guests' finds but not one's own would show
 * exactly half of what its row promises.
 *
 * @param {?string} day  the item's day — a point's by default; a walk
 *        hands in its start (trackDay), which dayOf cannot read off it
 */
function tourMatches(point, day = timeRange.dayOf(point)) {
    if (state.tour === '') return true;
    if (state.tour.startsWith('e:')) {
        return point.excursion_id != null
            && String(point.excursion_id) === state.tour.slice(2);
    }
    /* „d:<day>" IS SOMEBODY ELSE'S DAY (26.09.2026: „nur Finns
       Punkte zeigen"): the tourless finds of others on that day, exactly
       what its row gathered — asked with the same tourOwnerOf the row was
       built with, so the two cannot disagree. One's own points never. */
    if (timeRange.othersDayOf(state.tour) !== null) {
        return timeRange.othersDayHolds(point, state.tour, tourOwnerOf, day);
    }
    /* „t:<id>" IS AN OUTING, NOT A WORD (migration 0079). Since the
       proposed name is plainly „Tagestour" again, the name cannot pick a
       tour any more — every day carries the same one. The key does, and
       collectTours hands it out. */
    if (state.tour.startsWith('t:')) {
        return String(point.tour_id ?? '') === state.tour.slice(2);
    }
    // A tour from before the id: then the name IS the identity, and the
    // key is the name. Nothing about those rows has changed.
    return (point.tour ?? '') === state.tour;
}

function sharedVisible(point) {
    /* TWO KINDS OF FOREIGN POINT, TWO GATES. A container point is chosen
       through the LIST it hangs in; an excursion find hangs in no list at
       all — guests have none — and is chosen through its excursion. Both
       live in the same store, so the question has to be asked here.

       'all' is the entry at the top of the section: it means every
       excursion, including the ones loaded later, and therefore cannot be
       replaced by ticking them one at a time. */
    if (point.co_owned) {
        /* A CO-OWNED FIND HANGS IN MY OWN TREE: 'lists' is the list I
           filed it in, so its checkmark under „Meine Listen" governs it
           like one of mine. Unfiled it stands regardless — a find one
           just took part in must not vanish into a list one never
           chose. BEFORE the excursion gate, since step 5: a participant's
           old guest finds are co-owned AND excursion finds, and the
           excursions he merely took part in are not in his tick list —
           behind that gate they would never show at all. */
        if (point.lists.length > 0 && !point.lists.some(id => state.visible.has(id))) return false;
    } else {
        /* THREE ROADS, AND ANY ONE OF THEM SHOWS THE POINT — the list it
           came through, the excursion it belongs to, the row „Von <Name>"
           of a find shown to me. An OR, as for one's own finds
           (pointVisible).

           THE EXCURSION WAS ONCE A GATE OF ITS OWN, checked INSTEAD of the
           lists — right for a guest's find, which hangs in no list, and
           wrong for everything else: a find the sharer had set during his
           excursion carries its excursion_id through the list share too,
           and the recipient, who was not on that outing, could tick the
           list as often as he liked and never saw it (Finn and the maintainer's
           wk-00671 from the „Vogelsberg", 16.10.2026 — 116 finds of that
           one list were invisible to everyone it was shared with). */
        // state.visible too: a member's point in MY joint list follows the
        // tick on my own list under „Meine Listen" (22.09.2026).
        const byList = point.lists.some(id => state.visibleShared.has(id) || state.visible.has(id));
        const byExcursion = point.excursion_id != null
            && (state.visibleExcursions.has('all') || state.visibleExcursions.has(point.excursion_id));
        // A FIND SHOWN TO ME follows its row „Von <Name>" — and if it ALSO
        // comes through a share, either tick shows it: the hint is a road
        // of its own, not a second switch on the same one.
        const byHint = point.hint_from != null && !hintHidden(point);
        if (!byList && !byExcursion && !byHint) return false;
    }
    if (!filter.matchTerms(state.terms, pointHaystack(point))) return false;
    // The identification group applies to foreign points as to own ones.
    // It was missing here as long as the shared ones were only markers and
    // stood in no list — a filter that is visibly on and has no effect on
    // half the stock is worse than none.
    if (!workedMatches(state.identification, point.recordings)) return false;
    if (!identificationMatches(state.identification, point.title,
            point.extra?.[CERTAINTY_FIELD])) return false;
    if (state.noTime && point.found_at !== null) return false;
    if (!filter.rlMatches(state.rl, point.red_list)) return false;
    if (!filter.edibilityMatches(state.edibility, point.dgfm)) return false;
    if (!filter.responsibilityMatches(state.responsibility, point.dgfm)) return false;
    if (!filter.elevationMatches(state.elevation, point.elevation_m)) return false;
    if (!filter.taxonomyMatches(state.taxonomy, point.taxonomy)) return false;
    if (!pointInTimeRange(point)) return false;
    /* A TOUR IS PERSONAL — a foreign point never carries mine, so with a
       tour filtered the shared stock disappears, and rightly: it was not
       along on the outing.

       AN EXCURSION IS THE OPPOSITE: it is the whole outing, and the
       guests' finds are the larger half of it. Same field, two meanings,
       told apart by the „e:" in front. */
    if (state.tour !== '' && !tourMatches(point)) return false;
    if (!filter.placeMatches(placeConditions(), point)) return false;
    return true;
}

// ---------------------------------------------------------------------------
// Map
// ---------------------------------------------------------------------------

// Remember the last position so you continue there after reloading.
const remembered = loadView();

/**
 * How far one wheel notch zooms — reckoned against Leaflet's platform
 * conversion.
 *
 * Leaflet divides the wheel delta differently per platform before zooming
 * (getWheelDelta in leaflet.js): on the Mac by three times the device pixel
 * density, on Linux with Chrome by it once, otherwise by twice it. On a
 * Retina device that is a factor of six against two on Windows — the same
 * fixed number thus feels three times more sluggish on the Mac, and on the
 * iPad trackpad it became a crawl.
 *
 * Therefore no fixed number, but the divisor cancelled out: below stands the
 * distance in real device pixels that one zoom step should cost, and that
 * then applies equally everywhere. A smaller number means faster zooming —
 * that is the screw to turn when it feels wrong.
 */
const RAW_PIXELS_PER_LEVEL = 80;

function wheelSensitivity() {
    const density = window.devicePixelRatio || 1;
    const divisor = L.Browser.mac                       ? 3 * density
                 : L.Browser.linux && L.Browser.chrome ? density
                 : density > 0                          ? 2 * density
                 : 1;
    // Bounded from below: beneath that even the smallest notch already
    // jumps across several levels.
    return Math.max(5, Math.round(RAW_PIXELS_PER_LEVEL / divisor));
}

/* THE VECTOR MAP, FOR EVERYBODY (01.10.2026: „Vektorkarte für
   alle"; built beside Leaflet since 29.09.2026, vectormap.js says why a
   bridge). Leaflet stays only as the quiet fallback, so nobody stands
   without a map:

   - A DEVICE THAT CANNOT DRAW IT — no WebGL, an old phone, a browser with
     the graphics switched off. MapLibre throws then, and Leaflet draws.
   - ITS STYLE CANNOT BE HAD — the service down, and nothing on the device.
   - WITHOUT A LINE, WHERE ITS BASE MAP DOES NOT LIE ON THE DEVICE. The
     service worker keeps MapLibre, style and fonts; aerial images and
     overlays share Leaflet's stored tiles; the base map comes with an area
     download from the Europe file. Where the map was last looked at and
     the device holds no base-map tile, the vector map would draw a picture
     without roads and names — less than the Leaflet map shows there. A
     vector map started WITH a line goes on drawing from the device when
     reception drops.

   Leaflet's own code goes once the vector map has proved itself in the
   woods; until then it is the net under the high wire. */
let vectorKit = null;

/* One base-map tile at the remembered view, under the key the area
   download stores it by (omtKey in vectorlayers.js): level 14, the finest
   the tiles have and the one every area holds. */
async function vectorBaseOnDevice() {
    try {
        const z = 14, n = 2 ** z;
        const [latitude, longitude] = remembered.center;       // loadView's [lat, lon]
        const lat = latitude * Math.PI / 180;
        const x = Math.floor((longitude + 180) / 360 * n);
        const y = Math.floor((1 - Math.asinh(Math.tan(lat)) / Math.PI) / 2 * n);
        const key = new URL(`${start.baseUrl}offline/omt/${z}/${x}/${y}.pbf`, location.href).href;
        return !!(await caches.match(key));
    } catch {
        return false;
    }
}

/** Can this device draw with WebGL at all? MapLibre needs it. */
function webglAvailable() {
    try {
        const canvas = document.createElement('canvas');
        return !!(canvas.getContext('webgl2') || canvas.getContext('webgl'));
    } catch {
        return false;
    }
}

const vectorOffline = navigator.onLine === false;
if (webglAvailable() && (!vectorOffline || await vectorBaseOnDevice())) {
    try {
        const [bridge, kit] = await Promise.all([import('./vectormap.js'), import('./vectorlayers.js')]);
        vectorKit = { bridge, kit, style: await kit.vectorStyle() };
    } catch (e) {
        console.warn('Vector map not available, starting on Leaflet:', e);
        vectorKit = null;
    }
}

/* The map itself — and the last fallback: a MapLibre that cannot get its
   drawing surface throws here, not at the import above. */
let vectorMap = null;
if (vectorKit) {
    try {
        vectorMap = vectorKit.bridge.createVectorMap('map', {
            center: remembered.center,
            zoom: remembered.zoom,
            maxZoom: 20,
            glyphs: vectorKit.style.glyphs,
            sprite: vectorKit.style.sprite,
        });
    } catch (e) {
        console.warn('Vector map cannot draw here, starting on Leaflet:', e);
        vectorKit = null;
        document.getElementById('map').replaceChildren();
        document.getElementById('map').classList.remove('is-vector', 'maplibregl-map');
    }
}
const VECTOR = vectorKit !== null;
/* SAID ONCE A DAY, after the map stands: offline the simpler map opened,
   because this spot's map data is not on the device. Every start would be
   too often — whoever never loaded an area would read it each time. */
if (!VECTOR && vectorOffline) {
    const last = Number(settings.get('offlineMapNoticeAt', 0)) || 0;
    if (Date.now() - last > 20 * 60 * 60 * 1000) {
        settings.set('offlineMapNoticeAt', Date.now());
        setTimeout(() => status(t('Ohne Netz zeigt die Karte hier die einfachere Ansicht: Für diese Stelle liegen keine Kartendaten auf dem Gerät.')), 1500);
    }
}

const map = VECTOR ? vectorMap : L.map('map', {
    center: remembered.center,
    zoom: remembered.zoom,
    zoomControl: false,
    // With the official aerial imagery, high levels are the whole point.
    maxZoom: 20,
    // No fade-in. Leaflet fades tiles in via requestAnimationFrame; if that
    // gets throttled — background window, power-saving mode on the phone —
    // fully loaded tiles stop halfway, invisible. A map that stays grey in
    // the woods although the data is there is not worth the pretty
    // transition.
    fadeAnimation: false,

    // Continuous zooming instead of whole jumps: Leaflet otherwise snaps to
    // whole levels, with 0 it holds every intermediate level and scales the
    // tiles to it. On the trackpad and when pinching open with two fingers
    // the map thus follows the motion instead of jumping in steps.
    //
    // The price: between two levels the tiles are upscaled and thus a bit
    // softer. When letting go on a fractional level it stays that way —
    // that is the trade that "seamless" simply means.
    zoomSnap: 0,
    // Buttons and keyboard still jump by a whole level. Otherwise you would
    // need several clicks for what used to be one.
    zoomDelta: 1,
    wheelPxPerZoomLevel: wheelSensitivity(),
});

// Make it reachable for debugging in the browser console. For a map
// application that is worth gold: set the position, check layers, trace
// tile requests.
window.map = map;

/* Leaflet's corners stack from the edge inward, the newest nearest the
   middle: what is added first lies outermost. So the attribution comes
   first on the vector map — the footer at the very bottom (map.css), the
   compass above it; Leaflet adds its own at creation. NO ZOOM BUTTONS on
   the vector map (01.10.2026: „weder das Plus-Minus-Symbol"):
   the corner is the compass's, fingers pinch and the wheel turns. */
if (VECTOR) L.control.attribution({ prefix: '<a href="https://maplibre.org">MapLibre</a>' }).addTo(map);
else L.control.zoom({ position: 'bottomright' }).addTo(map);
L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);

const { baseMaps, overlays, tracksLayer, startLayer, updateAfterArea, currentServices,
        protectedAreasState, contourKey } = VECTOR
    ? await vectorKit.kit.buildVectorLayers(start.layers, map, start.baseUrl, vectorKit.style, settings,
        { offlineFiles: start.offlineMap ?? [] })
    : buildLayers(start.layers, map, start.baseUrl);

// Useful when looking things up in the console: which state services are
// currently stacked, and what all is remembered?
window.mapServices = currentServices;
window.mapSettings = settings;
window.mapLayers = { baseMaps, overlays };
// Points, lists, selection — worth gold when tracing a report:
// "is the point really still in the old list, or does it only look that way?"
window.mapState = state;

const layerControl = L.control.layers(
    baseMaps, overlays, { position: 'topright', collapsed: true }
).addTo(map);

/**
 * Attach the hint texts from the registry to the layer picker.
 *
 * They explain what an overlay shows and where its limits lie — for the soil
 * pH, say, that yellow means acidic and the resolution is only 250 m.
 * Without this they sat unused in the registry.
 */
for (const label of layerControl.getContainer().querySelectorAll('label')) {
    const name = label.textContent.trim();
    const hint = (overlays[name] ?? baseMaps[name])?.mapHint;
    if (hint) label.title = hint;
}

// ---------------------------------------------------------------------------
// A failing overlay says so
// ---------------------------------------------------------------------------

/* WHY (27.08.2026): the DLR's tree-species service was down for
   hours — every tile a 502 — and the map showed exactly nothing about it.
   The checkmark was on, the picture did not change, and „geht gar nicht
   mehr" was the only reading available. A base map failing is loud (grey
   squares everywhere); an overlay failing is SILENT, because drawing
   nothing on top looks like drawing nothing on top.

   ONLY WHEN IT COULD THEORETICALLY WORK — the condition, and the
   whole art of this block. Out in the woods every service fails, and a bar
   blaming the provider for a dead zone would teach people to swipe the bar
   away. Two guards, because "offline" is two different things:

     navigator.onLine    catches the switched-off radio. It does NOT catch
                         the dead zone — out there the flag is true, the
                         phone hangs on a tower (api.js says why at length).
     a probe to api/     catches the dead zone. api/ is the one path the
                         service worker never intercepts (sw.js), so the
                         answer really comes from the network — ANY answer,
                         a 401 included, proves the line carries; only a
                         thrown fetch means it does not. A loaded TILE could
                         not prove that: tiles come out of the offline cache.

   The probe costs one request, and only when a layer has already failed
   three tiles in a row — not per tile, not per visit.

   ONLY THE PLAIN TILE OVERLAYS. The composed ones (the states' layers,
   the protected areas) are groups whose inner errors do not bubble, and
   the protected areas already carry their own notice through zoomHint(). */
{
    const TILE_ERRORS_ENOUGH = 3;
    const reset = new Map();   // leaflet layer -> back to the fresh state

    for (const [name, layer] of Object.entries(overlays)) {
        if (typeof layer.on !== 'function') continue;
        let failed   = 0;
        let reported = false;
        let probing  = false;

        reset.set(layer, () => {
            failed   = 0;
            reported = false;
            removeStatus('service:' + name);
        });

        // A tile that arrives ends the episode: the service is back, and a
        // message still standing would now be wrong.
        layer.on('tileload', () => reset.get(layer)());

        layer.on('tileerror', () => {
            failed++;
            if (failed < TILE_ERRORS_ENOUGH || reported || probing) return;
            if (!navigator.onLine) { failed = 0; return; }

            probing = true;
            fetch(`${start.baseUrl}api/v1/bucket.php`, { method: 'HEAD', cache: 'no-store' })
                .then(() => {
                    reported = true;
                    status(t('„%s" antwortet gerade nicht — der Dienst dahinter ist wohl gestört.', name),
                        true, 'service:' + name);
                })
                // Dead zone: the service cannot be blamed. failed goes back
                // to zero below, so the question is asked again later —
                // when the net is back and the service still fails, the
                // message then comes.
                .catch(() => {})
                .finally(() => { probing = false; failed = 0; });
        });
    }

    // Switching off takes the message along; switching on starts a fresh
    // episode, so a still-broken service is reported anew rather than
    // being a thing one was told about once, last Tuesday.
    map.on('overlayadd overlayremove', (e) => reset.get(e.layer)?.());
}

/* WHAT A CONTOUR LINE MEANS, right above the scale (01.10.2026:
   „Bei eingeschalteten Höhenlinien ist es oftmals nicht ersichtlich, wie
   viel Höhenmeter von einer Linie bis zur nächsten Linie sind. Das ändert
   sich auch mit dem Zoom-Faktor … möglichst kurzer Text, vielleicht ein
   Symbol"). A thick line over a thin one, drawn in the lines' own colour,
   each followed by its step: „50 Hm" and „10 Hm". Höhenmeter and not plain
   metres: beside a line „10 m" reads as a distance on the ground, and
   „Hm" is what every hiking sign says. The whole sentence stands in the
   title for whoever wants it. Only while the lines are on and drawn;
   added once right after the scale, so it always sits just above it. */
const contourKeyControl = L.control({ position: 'bottomleft' });
contourKeyControl.onAdd = () => {
    const box = L.DomUtil.create('div', 'contour-key');
    box.hidden = true;
    return box;
};
function updateContourKey() {
    const box = contourKeyControl.getContainer();
    if (!box) return;
    const step = contourKey?.() ?? null;
    box.hidden = step === null;
    if (!step) return;
    // The steepness of the land under the view is still being measured: once more when it is known.
    step.pending?.then(() => updateContourKey());
    const sample = (major) => {
        const line = document.createElement('span');
        line.className = 'contour-key-line' + (major ? ' is-major' : '');
        line.style.borderTopColor = step.colour;     // the lines' own colour — a depiction
        return line;
    };
    const label = (text) => Object.assign(document.createElement('span'), { textContent: text });
    // The thick line on top, each row first the line and then its number.
    box.replaceChildren(sample(true), label(t('%d Hm', step.major)), sample(false), label(t('%d Hm', step.minor)));
    box.title = t('Höhenlinien: eine Linie alle %d Höhenmeter, eine dicke Linie alle %d Höhenmeter.', step.minor, step.major);
}
if (contourKey) {
    contourKeyControl.addTo(map);
    map.on('zoomend moveend overlayadd overlayremove baselayerchange', updateContourKey);
    updateContourKey();
}

// The official aerial images change per federal state; after every movement
// check which services are responsible.
map.on('moveend', () => {
    updateAfterArea();
    rememberView();
    zoomHint();
});

map.on('baselayerchange', (e) => {
    activeBaseMap = e.layer;
    // Always re-evaluate when switching, even if the level was already too
    // high before — otherwise you wonder about a blurry image.
    zoomHint(true);
});

// Whoever switches on the protected areas should learn right away whether
// they draw at this level — not only after the next map movement.
map.on('overlayadd overlayremove', () => zoomHint(true));

// ---------------------------------------------------------------------------
// Legend
// ---------------------------------------------------------------------------

/**
 * Shows the legend for every switched-on overlay.
 *
 * Deliberately kept generic: what is shown is what the registry holds under
 * 'legend'. A new layer with a color key only needs the entry there,
 * nothing here. Without a legend — hillshade, contour lines — nothing
 * appears either.
 *
 * The 'hint' field from the registry lands as a footnote below it. Until
 * then it was maintained everywhere but displayed nowhere.
 */
const legendControl = L.control({ position: 'bottomleft' });

legendControl.onAdd = function () {
    const box = L.DomUtil.create('div', 'legend');
    // Otherwise a double click on the legend zooms the map, and when
    // scrolling in a long legend the map moves along.
    L.DomEvent.disableClickPropagation(box);
    L.DomEvent.disableScrollPropagation(box);
    return box;
};

let legendPlaced = false;
let legendSignature = null;          // how we recognize that nothing has changed

// The remembered class filter is applied once per layer, not on every
// rebuild of the legend. Otherwise the tree-species layer would push all
// tiles through the color filter anew on every zoom step.
const filterApplied = new WeakSet();

function drawLegend() {
    const layers = Object.values(overlays).filter(e =>
        map.hasLayer(e)
        && e.mapLegend?.length
        // Layers that only load from a zoom level on have nothing to show
        // further out — a legend for them would then be false information.
        && (e.mapIsDrawing?.(map) ?? true));

    // Only rebuild when the composition has changed: otherwise half the
    // interface would go through the rebuild on every zoom step.
    const signature = layers.map(e => e.mapName).join('|');
    if (signature === legendSignature && legendPlaced === !!layers.length) return;
    legendSignature = signature;

    if (!layers.length) {
        if (legendPlaced) { legendControl.remove(); legendPlaced = false; }
        return;
    }

    if (!legendPlaced) { legendControl.addTo(map); legendPlaced = true; }

    const collapsed = new Set(settings.get('legendsCollapsed', []) || []);
    const allOff = settings.get('legendsOff', {}) || {};

    const box = legendControl.getContainer();
    box.replaceChildren(...layers.map((layer, index) => {
        const name  = layer.mapName || '';
        const block = L.DomUtil.create('div', 'legend-block');
        if (index > 0) block.classList.add('has-divider');
        if (collapsed.has(name)) block.classList.add('is-collapsed');

        // The heading is a button, not mere text: it can be clicked, so it
        // must also be reachable with the keyboard and announce its state.
        const title = L.DomUtil.create('button', 'legend-title', block);
        title.type = 'button';
        title.setAttribute('aria-expanded', String(!collapsed.has(name)));

        const arrow = L.DomUtil.create('span', 'legend-arrow', title);
        arrow.setAttribute('aria-hidden', 'true');
        arrow.innerHTML = disclosureTriangle();
        arrow.classList.toggle('is-open', !collapsed.has(name));
        L.DomUtil.create('span', '', title).textContent = name;

        title.addEventListener('click', () => {
            /* TWO NAMES, NOT ONE. The English sweep (fa6b8d5) turned both
               'zu' (this click's answer) and 'zugeklappt' (the remembered
               set) into 'collapsed', so the inner boolean shadowed the set:
               folding a legend threw "collapsed.add is not a function" and
               was never remembered (found 30.09.2026). */
            const shut = block.classList.toggle('is-collapsed');
            arrow.classList.toggle('is-open', !shut);
            title.setAttribute('aria-expanded', String(!shut));
            shut ? collapsed.add(name) : collapsed.delete(name);
            settings.set('legendsCollapsed', [...collapsed]);
        });

        const content = L.DomUtil.create('div', 'legend-content', block);

        // Individual classes deselectable, provided the layer can do that:
        // for the protected areas the kind is unhooked, for the tree species
        // the color is filtered out of the image. Layers without this
        // ability keep a plain listing, so nothing looks clickable that is
        // not.
        const switchable = typeof layer.setMapClasses === 'function';
        const off = new Set(allOff[name] || []);
        if (switchable && !filterApplied.has(layer)) {
            layer.setMapClasses(off);
            filterApplied.add(layer);
        }

        const list = L.DomUtil.create('ul', 'legend-list', content);
        if (switchable) list.classList.add('is-switchable');

        for (const entry of layer.mapLegend) {
            /* 'wide' AND NOT 'breit'. The registry has been writing the
               English key since the schema move; this line kept reading
               the German one and therefore never found it, so the wide
               rows lost their full-width class without a sound. Found on
               11.09.2026 while the tree-species key was being worked on.
               The German-word sweep of CLAUDE.md found the second spot
               too: the comment in map.css said 'breit' as well. */
            const row = L.DomUtil.create('li', entry.wide ? 'legend-wide' : '', list);
            if (entry.extra) row.title = entry.extra;

            const inner = switchable
                ? L.DomUtil.create('button', 'legend-toggle', row)
                : row;
            if (switchable) {
                inner.type = 'button';
                inner.setAttribute('aria-pressed', String(!off.has(entry.color)));
            }

            /* THE PATCH SHOWS WHAT THE MAP SHOWS, and that is not always
               what is recognised: where the key carries 'show', the tile
               filter repaints that class, and a patch in the old colour
               would send the eye looking for something that is no longer
               there. Switching on and off goes on running over 'color' —
               it is the pixel's own number, and it is what is already
               stored under 'legendsOff'. */
            const field = L.DomUtil.create('span', 'legend-color', inner);
            field.style.background = entry.show ?? entry.color;
            /* THE KIND'S SHORT NAME BEHIND ITS NAME — „Naturschutzgebiet
               (NSG)" (30.09.2026: „die Legende auch mit
               Zeichen", the signs became short names on 01.10.2026). The
               point window shows the kind by its short name alone; here is
               where one reads what it stands for. */
            L.DomUtil.create('span', '', inner).textContent = entry.short
                ? `${entry.text} (${entry.short})` : entry.text;

            if (!switchable) continue;

            row.classList.toggle('is-off', off.has(entry.color));
            inner.addEventListener('click', () => {
                const nowOff = !off.has(entry.color);
                nowOff ? off.add(entry.color) : off.delete(entry.color);
                row.classList.toggle('is-off', nowOff);
                inner.setAttribute('aria-pressed', String(!nowOff));

                layer.setMapClasses(off);
                allOff[name] = [...off];
                settings.set('legendsOff', allOff);
            });
        }

        /* A LAYER MAY NAME ITS OWN LINE UNDER THE KEY (mapLegendNote) instead
           of its hint — the rain names only the fortnight its colours
           hold for (01.10.2026: „Eine Legende ist frei von
           Erklärungen"); the hint stays in the picker's title. */
        const note = layer.mapLegendNote ?? layer.mapHint;
        if (note) {
            L.DomUtil.create('p', 'legend-footnote', content).textContent = note;
        }
        return block;
    }));
}

// 'zoomend' must be included: the protected areas only load from a level
// on, and their legend should come and go with them.
map.on('overlayadd overlayremove zoomend', drawLegend);
// A layer that learned its line under the key later (the rain's fortnight): drawn anew.
map.on('layerdescribed', () => { legendSignature = null; drawLegend(); });
drawLegend();

/**
 * Make the legend step back while a profile is open.
 *
 * Leaflet fundamentally places its controls above the popups, and no z-index
 * inside the map changes that: because of its translation the map area forms
 * a stacking context of its own that a popup cannot escape. The legend at
 * the bottom left therefore covered the profile.
 *
 * Hiding it is bearable: the profile names the area kind anyway, and in the
 * same color at that.
 */
map.on('popupopen',  () => legendControl.getContainer()?.classList.add('is-back'));
map.on('popupclose', () => legendControl.getContainer()?.classList.remove('is-back'));

// ---------------------------------------------------------------------------
// Notice when the chosen map no longer supports the current zoom level
// ---------------------------------------------------------------------------

let activeBaseMap = startLayer;
let lastReported  = null;   // so the notice does not come on every zoom step

/**
 * 'erzwingen' on a map change: then things are re-evaluated even when the
 * situation has not changed arithmetically — the message applies to another
 * map after all, and an old one left standing would simply be wrong.
 */
function zoomHint(force = false) {
    const info = activeBaseMap?.mapInfo;
    // Since the continuous zoom, getZoom() is fractional. Leaflet still
    // fetches tiles from the nearest whole level though — that governs
    // whether anything is really upscaled. Unrounded, the notice already
    // reported at 18.2 an upscaling that does not exist at all.
    const zoom = Math.round(map.getZoom());

    let situation = null;
    let text = null;

    const protection = protectedAreasState();

    if (protection && protection.kind === 'zoom') {
        // Here too: without a notice the switched-on checkmark looks broken.
        // The limit is not arbitrary but the data volume — further out it
        // would be megabytes per map movement.
        situation = 'protection-zoom';
        text = t('Schutzgebiete werden erst ab Stufe %d geladen, gerade ist Stufe %d.', protection.fromZoom, zoom);
    } else if (info && zoom > info.nativeMax) {
        situation = 'beyond-native:' + info.name;
        text = t('%s hat Kacheln bis Stufe %d. Darüber wird nur vergrößert, nicht schärfer.', info.name, info.nativeMax);
    }

    if (!force && situation === lastReported) return;
    lastReported = situation;

    if (text !== null) {
        status(text, false, 'zoom');
    } else {
        // Nothing left to report — take away a zoom notice still standing
        // instead of letting it run out. Messages of other origin remain.
        removeStatus('zoom');
    }
}

/*
 * On the very first visit there is no remembered view AND no points yet —
 * those only come from the store or from the server. Then Germany stands
 * here at first, and establishInventory() follows up as soon as the first
 * point is there. Without this marker the first visit would stay stuck at
 * zoom level 6.
 *
 * The marker itself sits at the very top with the other advance
 * declarations — this function runs four hundred lines earlier than where
 * it stands here.
 */
function loadView() {
    const a = settings.get('view');
    if (a && Number.isFinite(a.lat) && Number.isFinite(a.lon)) {
        return { center: [a.lat, a.lon], zoom: a.zoom || 13 };
    }

    // Without a remembered view: onto the existing points, else the middle of Germany.
    if (start.points.length > 0) {
        const p = start.points[0];
        return { center: [p.lat, p.lon], zoom: 14 };
    }
    viewWasDefault = true;
    return { center: [51.3, 9.5], zoom: 6 };
}

function rememberView() {
    const m = map.getCenter();
    settings.set('view', {
        lat: m.lat, lon: m.lng, zoom: map.getZoom(),
    });
}

// ---------------------------------------------------------------------------
// Marker
// ---------------------------------------------------------------------------

/**
 * THE SAME COLOUR, LOUDER — FOR THE MAP ONLY (23.09.2026: „auf
 * der Karte, wenn das Straßennetz und die anderen Symbole eingeblendet
 * sind, müssen unsere Punkte gut sichtbar sein … vielleicht in die
 * Neontöne gehen"). The list keeps its stored value, and the sidebar keeps
 * showing it damped (map.css, .list-row-arrow .color-point) — this is a
 * way of DRAWING it, like that damping, not a second value that could
 * drift. Hue kept, saturation to full, lightness into the neon band: a
 * road's orange is a print colour, the marker's orange glows beside it.
 * Greys and browns (low saturation) stay as they are: pushed to full they
 * would turn into an orange nobody chose. Cached per colour.
 */
const neonCache = new Map();
/** Relative luminance (WCAG) of a #rrggbb colour, 0 black to 1 white. */
function luminance(hex) {
    const c = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255)
        .map(v => v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
    return .2126 * c[0] + .7152 * c[1] + .0722 * c[2];
}
function neonColor(hex) {
    if (neonCache.has(hex)) return neonCache.get(hex);
    let out = hex;
    const m = /^#([0-9a-f]{6})$/i.exec(String(hex).trim());
    if (m) {
        const [r, g, b] = [0, 2, 4].map(i => parseInt(m[1].slice(i, i + 2), 16) / 255);
        const max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2, d = max - min;
        const sat = d === 0 ? 0 : d / (l > .5 ? 2 - max - min : max + min);
        if (sat >= .3) {
            const h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
            let light = Math.min(.62, Math.max(.55, l));
            out = hslToHex(h * 60, 1, light);
            /* GREEN AND TURQUOISE GLOW TOO BRIGHT at the same lightness
               (23.09.2026: „das Türkis für Flechten wird mir auf
               der Karte zu hell"). The eye weighs green seven times as
               heavily as blue: at 55 % the turquoise measured a relative
               luminance of 0.74, the blue 0.29. So the lightness comes
               down until the colour reads no brighter than 0.42 — hue and
               full saturation stay, it only stops shining white. Yellow
               (40–75°) is spared: darkened it turns olive, and its glow is
               the point of it. */
            const hue = h * 60;
            if (hue < 40 || hue > 75) {
                while (luminance(out) > .42 && light > .3) {
                    light -= .01;
                    out = hslToHex(hue, 1, light);
                }
            }
        }
    }
    neonCache.set(hex, out);
    return out;
}

/** Color of a point: that of its first list, otherwise a neutral grey. */
function pointColor(point) {
    for (const listId of point.lists) {
        // THE LEAF determines the appearance, no longer the main list:
        // points now live only in the last level, and since migration 0040
        // every sublist carries its own color — starting out with the
        // root's, so a branch does not fall apart by itself, it now only
        // LETS itself be fanned out. Old points still hanging directly in a
        // parent list continue to find a color there: the columns have
        // stayed.
        // Own lists first, then the shared ones: a foreign point finds its
        // color in the container's list — symbolFor() thereby works for
        // both without there being a second drawing path.
        const list = state.lists.get(listId) ?? sharedLists.get(listId);
        if (list) return list.color;
    }
    // A find shown to me hangs in no list of mine — it brings its holder's look.
    if (point.hint_look) return point.hint_look.color;
    return '#8a97a4';
}

/*
 * FORMEN USED TO STAND HERE, the table of marker shapes. Since 14.08.2026 it
 * lives in includes/shapes.php and comes back via shapes.js (shapeGeometry)
 * — because the containers in the administration show their attached lists
 * with those lists' own symbol and PHP must reach the same paths for that.
 * Two copies would be two shapes as soon as somebody changes one.
 *
 * What stayed here is map and not drawing: SHAPE_ANCHOR (Leaflet's
 * arithmetic, right below) and SHAPE_NAMES (the labels of the picker).
 */

/**
 * Where the point actually lies — for the little flag the tip, otherwise the
 * center.
 *
 * What stands on the ground hangs by its foot: tree, deer and lichen like
 * the mushroom. The numbers are the measured bottom edges of the paths, not
 * estimated — an anchor two units below the figure would visibly shift the
 * marker against the coordinate. The insect flies and stays centered.
 */
const SHAPE_ANCHOR = {
    flag: [6, 21.5], mushroom: [12, 21],
    // The three further mushrooms stand on their stems, like the pair.
    single_mushroom: [12, 21], bolete: [12, 21.1], fly_agaric: [12, 21.2],
    // The flame stands on its base, like the mushroom.
    fire: [12, 20.6],
    // The campsite stands on its line of ground, tent and tree together.
    campsite: [12.1, 21.5],
    // The houses stand on their ground, the bed on its legs.
    guesthouse: [12, 21.5], hotel: [12, 21.5], lodging: [12, 20.8], rest_area: [12.0, 21.0],
    plant: [12, 21.5], conifer: [12, 21.5], broadleaf: [12, 21.5], lichen: [12, 21.5],
    // The mammals stand on their hooves. They look right, head and antlers
    // over the right half, so the feet lie left of centre — measured, the
    // middle between the outermost hooves.
    deer: [9.9, 21.6], roe_deer: [9.9, 21.6], wild_boar: [11.1, 21.6], cow: [10.6, 21.5], sheep: [10.6, 21.5],
    // The tuft reaches 21.7 with the corners of its slanted blades. The
    // cattail stands on its stalks at 21.5 — only the corner of its leaf
    // dips to 22.2 — and its foot lies between leaf and stalks.
    flower: [12, 21.5], rushes: [12, 21.7], cattail: [12.2, 21.5],
    // The grasslands stand on their ground; the limestone's foot lies a
    // little left of centre, between the stone and the tufts.
    poor_grassland: [12, 21.5], calcareous_grassland: [11.7, 21.3],
    // The bog stands on its water: the troughs of the wave are its bottom edge.
    sphagnum_bog: [12, 21.8],
    // What sits on the ground hangs by its foot: the snail and the slug at
    // the middle of their sole, the door snail at its lower tip, the mussel
    // at its lower margin (all measured).
    // Spider and fish stay centred — a spider hangs from its thread as often
    // as it sits, and the fish swim anyway; so do the tick, the frog, the
    // lizard, the snake, the many-legged one and the crayfish, drawn from
    // above, the bird of prey, which soars, and the woodpecker, whose trunk
    // runs the full height.
    snail: [10.6, 21.4], slug: [10.0, 21.4], door_snail: [13.6, 20.6], mussel: [10.3, 18.0],
    // The spider ON ITS THREAD hangs by the thread's top: the point is where
    // the thread is fixed, the spider dangles below it. The scorpion stands
    // on its legs, the middle between the outermost feet.
    hanging_spider: [12, .4], scorpion: [12.2, 21.6],
    // The songbird sits on its twig, the owl on its branch, the waterbird
    // floats on its wave, the wader stands on its feet (measured).
    bird: [11.5, 18.9], owl: [12, 21.0], waterbird: [10.6, 22.0], wader: [11.2, 21.5],
    // The harvestman, drawn from the side, stands on its feet.
    harvestman: [12.4, 21.5],
};


/**
 * Draw the marker, selected ones with a ring.
 *
 * Merely somewhat bigger is not enough: with several points close together
 * the size difference is barely visible. The selected one therefore gets a
 * sharp ring — no gradient, no aura: a line stays recognizable on any
 * aerial image and does not look like a mistake.
 *
 * THE RING WEARS THE ACCENT ON ONE'S OWN POINT, ITS OPPOSITE ON A FOREIGN
 * ONE (15.09.2026, seeing a magenta ring on his own find in the
 * help's picture: „das sind doch eigentlich Fremdpunkte — eigene Punkte
 * sollten in der Akzentfarbe umrandet werden"). Until then the ring took
 * the counter-colour of the LIST's colour, which said „selected" but
 * nothing about whose; the accent is the user's own colour everywhere
 * else, and the opposite hue is what a foreign walk already wears
 * (drawTracks) — one statement for marker and line. The black ring
 * underneath keeps either readable on a marker of the same colour.
 *
 * The ring needs room outside the shape. Instead of shrinking the shape,
 * the field of view is widened — that way all paths stay unchanged, and
 * only the anchor point reckons with the shifted origin.
 */
/* ONE ICON PER LOOK, KEPT (29.09.2026: zoomed out to a find ten
   kilometres off, his two thousand finds on the screen, and the phone „kommt
   nicht zur Ruhe"). drawMarker() handed every visible marker a FRESH icon at
   every 'moveend' — and with the location display following, every GPS
   report moves the map a little. Leaflet rebuilds a marker's element on
   setIcon, so that was up to MARKER_MAX elements parsed anew per report,
   once a second, without end. The look depends on colour, shape and the
   ring alone; one icon per such look is kept here, and drawMarker sets it
   only when it is another one. Icons are factories — one serves any number
   of markers. */
const symbolCache = new Map();

function symbolFor(point, selected, count = 0) {
    const color   = neonColor(pointColor(point));
    const shape    = pointShape(point);

    let ringColor = '';
    if (selected) {
        const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#96ff50';
        const foreign = point.owner_id != null && point.owner_id !== start.user?.id;
        ringColor = foreign ? hueOpposite(accent) : accent;
    }
    const key = `${color}|${shape}|${selected ? 1 : 0}|${ringColor}|${count}`;
    let icon = symbolCache.get(key);
    if (!icon) {
        icon = drawSymbol(color, shape, selected, ringColor, count);
        symbolCache.set(key, icon);
    }
    return icon;
}

/** The icon itself — symbolFor() decides whether a new one is needed.
 *  count: the species of an Aufsammlung, as a small tag at the shape. */
function drawSymbol(color, shape, selected, ringColor, count = 0) {
    const [ax, ay] = SHAPE_ANCHOR[shape] ?? [12, 12];

    const view   = selected ? { x: -5, width: 34 } : { x: 0, width: 24 };
    const size = selected ? 40 : 24;

    const ring = selected
        ? `<circle cx="12" cy="12" r="14.5" fill="none"`
        + ` stroke="rgba(0,0,0,.55)" stroke-width="4.5"/>`
        + `<circle cx="12" cy="12" r="14.5" fill="none"`
        + ` stroke="${ringColor}" stroke-width="2.5"/>`
        : '';

    return L.divIcon({
        className: 'point-symbol' + (selected ? ' is-selected' : ''),
        html: `<svg viewBox="${view.x} ${view.x} ${view.width} ${view.width}"`
            + ` width="${size}" height="${size}">`
            + ring
            // THE WHITE BORDER IS 0.5 PX SINCE 24.09.2026 (after
            // the landscape shapes had lost every detail on the map). The
            // border runs round EVERY part of a shape, so a gap narrower
            // than twice its width — between two blades, two stalks, two
            // branches — fills up with white completely: at the old 1 px
            // the rushes were a white fan and the bog a white block.
            // Compared on the aerial image, TopPlusOpen and OpenTopoMap at
            // 1, 0.7, 0.5 and 0.3 px, every shape at real size and three
            // times enlarged, he took 0.3 first („liefert wirklich die
            // besten Ergebnisse") and came back to 0.5 once it was live on
            // his phone („ich glaube 0,5 ist doch besser"). Whoever thins it
            // further: at 0.3 a brown marker sank into the aerial image. The
            // same width for ALL markers — one frame for every marker, and
            // deer and butterfly gain detail too; the drop shadow (map.css,
            // .point-symbol svg) does the rest of lifting it off the ground.
            //
            // THE BORDER LIES OUTSIDE ONLY — more than looks hangs on that.
            // Without paint-order SVG fills first and strokes on top: the
            // stroke sits centered on the contour, so it eats half of its
            // width INWARDS. At the old width 2 that was one unit from each
            // side, and everything narrower than two units vanished
            // completely under white — the deer's legs, the antler beams,
            // the notch between wing and body.
            //
            // With 'stroke' first, the stroke is drawn and the fill laid on
            // top. The seam to the outside stays exactly as wide as before,
            // the figure stays complete. Older browsers that do not know
            // the attribute draw as before — worse, but not broken.
            //
            // IT ALSO LOOSENS THE ONE-PATH RULE, but only exactly this far:
            // within ONE <path> all strokes are drawn first and then all
            // fills — overlapping subpaths thus merge cleanly into one
            // silhouette. Across SEVERAL <path> elements that does not
            // hold: there, drawing goes element by element, and the stroke
            // of the second lies above the fill of the first. Measured with
            // two overlapping circles — one element: one shape. Two
            // elements: a white arc right through the middle.
            //
            // So a shape may consist of parts, as long as all parts stand
            // in the same d="".
            + `<g fill="${color}" stroke="#fff" stroke-width="1" stroke-linejoin="round"`
            + ` paint-order="stroke">`
            + shapeGeometry(shape)
            + '</g>'
            /* THE SPECIES OF AN AUFSAMMLUNG (0174): one marker for the
               sample, the count of what grew from it at its upper right
               corner. INSIDE THE SVG, not as an element beside it: the
               vector map draws a find's marker as a picture of this SVG
               (vectormap.js, iconImage), and anything outside it would
               simply be missing there. */
            + (count > 0
                ? '<g class="point-count">'
                + '<circle cx="19.5" cy="4.5" r="4.6" fill="#fff" stroke="rgba(0,0,0,.65)" stroke-width=".6"/>'
                + `<text x="19.5" y="6.7" text-anchor="middle" font-size="${count > 9 ? 5.2 : 6.4}"`
                + ` font-weight="700" font-family="system-ui, sans-serif" fill="#111">${count}</text>`
                + '</g>'
                : '')
            + '</svg>',
        iconSize: [size, size],
        iconAnchor: [(ax - view.x) / view.width * size,
                     (ay - view.x) / view.width * size],
    });
}

function hslToHex(hue, saturation, lightness) {
    const a = saturation * Math.min(lightness, 1 - lightness);
    const part = (n) => {
        const k = (n + hue / 30) % 12;
        const value = lightness - a * Math.max(-1, Math.min(k - 3, 9 - k, 1));
        return Math.round(value * 255).toString(16).padStart(2, '0');
    };
    return `#${part(0)}${part(8)}${part(4)}`;
}

/**
 * The two sort glyphs, 15 pixels like the line they stand in.
 *
 * The drawings come from the library (includes/icon.php) — the clock
 * once stood here a second time, next to the same clock in the fact line of
 * a point, and the two had already drifted apart: one hand started at 7.2,
 * the other at 7.4.
 */
const CLOCK = iconHtml('clock');
const AZ  = iconHtml('az');

/** Shape of the first list the point stands in — of the leaf, like the color. */
function pointShape(point) {
    for (const listId of point.lists) {
        const list = state.lists.get(listId) ?? sharedLists.get(listId);
        if (list) return list.symbol || 'circle';
    }
    if (point.hint_look) return point.hint_look.symbol || 'circle';
    return 'circle';
}

function createMarker(point) {
    const m = L.marker([point.lat, point.lon], {
        icon: symbolFor(point, false),
        // Not yet at the server: somewhat paler, so you can see it without
        // the list color getting lost.
        opacity: point.pending ? 0.65 : 1,
        // Draggable only after selecting. That way every point can be
        // corrected without points accidentally wandering while panning
        // the map.
        draggable: false,
        keyboard: true,
        title: point.title || t('Ohne Titel'),
    });

    m.on('click', () => selectPoint(point.id));

    /**
     * Dragging does not write to the database.
     *
     * The new position only lands in the coordinate field and takes effect
     * with "Speichern". Whoever closes the window without confirming finds
     * the point back at its old place — drawMarker() resets it from the
     * saved state.
     *
     * Previously it wrote immediately. But for points you find again over
     * years, an accidentally dragged-along marker is expensive: the old
     * coordinate was gone, without a prompt and without a way back.
     */
    m.on('dragend', () => {
        lastDragEnd = Date.now();
        const pos = m.getLatLng();
        fields.coord.value = `${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}`;
        markUnsaved(true);
        // In move mode the field is not visible — then the coordinate runs
        // along in the bar, otherwise you would not know whether the drag
        // arrived at all.
        showMoveCoords();
        // Redraw, because that is where the notice stands in case the spot
        // was just dragged out of its own area.
        drawOutlineRow(outlineNow(point));
    });

    m.addTo(map);
    marker.set(point.id, m);
    return m;
}

/**
 * A foreign point from a container. Deliberately NOT createMarker(): the
 * title carries the owner — otherwise you cannot see on the map whose find
 * stands there.
 *
 * THE CLICK SELECTS, as with an own point. Until 14.08.2026 it opened a
 * modal of its own; now the detail window shows both kinds, and what you
 * may not do stands greyed out in it (applyDetailRights).
 *
 * DRAGGING ONLY WITH THE RIGHT AND ONLY ON THE SELECTED ONE, likewise as
 * with an own point: dragging writes into the coordinate field, it takes
 * effect with "Speichern". The confirmation prompt that used to replace
 * this is gone with that — it was needed as long as no window was open in
 * which the new spot could be discarded again. Dragging is switched on and
 * off in drawMarker().
 */
function createSharedMarker(point) {
    const m = L.marker([point.lat, point.lon], {
        icon: symbolFor(point, false),
        draggable: false,
        keyboard: true,
        title: `${point.title || t('Ohne Titel')} — ${point.owner}`,
    });
    m.on('click', () => selectPoint(point.id));
    m.on('dragend', () => {
        lastDragEnd = Date.now();
        const pos = m.getLatLng();
        fields.coord.value = `${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}`;
        markUnsaved(true);
        showMoveCoords();
    });
    m.addTo(map);
    marker.set(point.id, m);
    return m;
}

/** Reconcile markers with the state: create, update, remove. */
/**
 * How far beyond the screen edge markers are created.
 *
 * A quarter of the screen width. Without a margin, panning would leave a
 * strip without markers right at the edge until 'moveend' fires — you would
 * see the map dragging its own points behind. With a margin, what is about
 * to come in already stands there.
 */
const MARKER_MARGIN = 0.25;

/**
 * How many markers the map carries at most at the same time.
 *
 * MEASURED, NOT GUESSED. Creation grows disproportionately: 500 markers
 * cost 21 ms, 2,000 cost 47 ms, 8,000 already 356 ms and 20,000 a whole
 * 1,797 ms — and that on a desktop machine, a phone needs three to five
 * times as much. At 2,000 a redraw stays below the threshold where you
 * notice it.
 *
 * WHY A LIMIT IS NEEDED AT ALL, when only the visible is drawn: zoomed out,
 * everything IS visible. The viewport helps when working in the field,
 * where you look at one valley; it does not help when someone pulls all of
 * Germany onto the screen. That is exactly where this number bites.
 */
const MARKER_MAX = 2000;

/** Remembers whether the last draw was capped — so the notice does not appear on every pan. */
let markersCapped = false;

/**
 * Is the point on screen?
 *
 * AREAS BY THEIR EXTENT, not by their center. A survey area can fill the
 * whole screen while the computed center lies just outside — asked by the
 * center, precisely the area you are looking at would vanish.
 */
function inView(point, frame) {
    const draft = point.id === state.selectedId && outlineDraft !== undefined;
    const outline  = draft ? outlineDraft : point.outline;

    if (polygon.isUsable(outline)) return frame.overlaps(L.latLngBounds(polygon.bounds(outline)));
    return frame.contains([point.lat, point.lon]);
}

/**
 * When a drag on the map last ended — marker, foreign marker or corner
 * handle.
 *
 * THE CLICK AFTER THE DRAG IS NOT A CLICK, and Firefox delivers it while
 * Chromium swallows it. Measured on 25.08.2026 from the maintainer's live
 * console: releasing a dragged marker ran map.on('click') →
 * clearSelection() → detailVisible(false), so the window with the new
 * coordinate closed before it could be saved. Rebuilt in the embedded
 * Chromium three times, including with the icon swapped mid-drag — there
 * Leaflet suppresses the click every time. One browser's measurement is
 * not the other's.
 *
 * A TIMESTAMP, NOT A FLAG. A flag set on dragend would stay set in every
 * browser that never delivers the click — and would then swallow the next
 * genuine click on the map, minutes later and for no visible reason. A
 * timestamp heals itself: after the window it simply no longer applies.
 */
let lastDragEnd = 0;

/** How long after a drag a click still counts as its tail. */
const DRAG_TAIL_MS = 400;

/* WHAT RUNS AFTER EVERY MARKER REDRAW. drawMarker() is the one call every
   filter change ends in — forty-eight call sites, and a new consumer of
   the filter would have to find them all. It registers here instead; the
   tracks were the first (drawTracksIfFilterChanged, further down). A hook
   must be CHEAP when nothing changed: drawMarker runs on every pan. */
const afterMarkerDraw = [];

/* A marker onto its point's place — only when that place is another one:
   setLatLng repositions the element even when nothing moved, and a
   redraw asks this of every visible marker (see symbolFor). */
function moveMarker(m, point) {
    const at = m.getLatLng();
    const lat = Number(point.lat), lon = Number(point.lon);
    if (at.lat !== lat || at.lng !== lon) m.setLatLng([lat, lon]);
}

function drawMarker() {
    const wanted = new Set();
    const visibleLists = visibleBranch();
    const frame = map.getBounds().pad(MARKER_MARGIN);
    let outside = 0;

    /* AUFSAMMLUNGEN (0174, 15.10.2026: „auf der Karte machen wir
       die Probe"). The species of a sample stand at its spot, every one —
       so the map shows the sample once, with their count, and leaves the
       species out while the sample is known here. A species whose sample
       this device does not see (somebody else's, not shared) keeps its
       own marker. The sample stands when it or any of its species is
       shown by the lists. */
    const grown = new Map();   // sample id → its species (own and shared)
    for (const store of [state.points, state.sharedPoints]) {
        for (const p of store.values()) {
            if (p.collection_id == null || p.deleted_at) continue;
            if (!grown.has(p.collection_id)) grown.set(p.collection_id, []);
            grown.get(p.collection_id).push(p);
        }
    }
    const sampleKnown = (id) => {
        const s = state.points.get(id) ?? state.sharedPoints.get(id);
        return !!s && !s.deleted_at;
    };
    const anyGrownShown = (id) => (grown.get(id) ?? []).some(p =>
        state.points.has(p.id) ? pointVisible(p, visibleLists) : sharedVisible(p));

    for (const point of state.points.values()) {
        // THE SELECTED ONE ALWAYS. It has an open window, a drag handle and
        // possibly an outline in draft; clearing it away because the map
        // happens to stand elsewhere would tear something away mid-work.
        //
        // THE FILTER USED TO BE EXEMPT FROM THAT EXEMPTION (found
        // 25.08.2026), and only the viewport and the marker cap honoured
        // it. So with a filter on, a point could lose its marker while its
        // window stood open beside it — and „Speichern" would then move
        // something nobody can see. The reasoning of the line above holds
        // for the filter word for word: whoever has a point open is
        // working on it.
        const chosen = point.id === state.selectedId;
        /* AND THE PEEKED ONE, FOR THE SAME REASON ONE STEP FURTHER. The
           peek's whole purpose is to SHOW a point (peekPoint); if the
           filter, the viewport or the cap dropped its marker, the flight
           would end on an empty patch of map with a name floating under
           it. It is exempt from all three — but it stays out of `chosen`
           below, which is about a point being WORKED ON: nothing is open
           on a peeked point, so it gets no drag handle. */
        const peeked = point.id === state.peekedId;
        const shown  = chosen || peeked;

        // A species stands in its sample's marker (see above).
        if (!shown && point.collection_id != null && sampleKnown(point.collection_id)) continue;
        if (!shown && !pointVisible(point, visibleLists)
            && !(point.is_collection && anyGrownShown(point.id))) continue;
        if (!shown && !inView(point, frame)) continue;
        if (!shown && wanted.size >= MARKER_MAX) { outside++; continue; }

        wanted.add(point.id);

        let m = marker.get(point.id);
        if (!m) {
            m = createMarker(point);
        } else {
            /* A DRAGGED, UNSAVED MARKER KEEPS ITS SPOT (25.08.2026).
               point.lat/lon is the SAVED position — writing it back here
               undoes a drag that has not been confirmed yet.

               That struck on an ordinary path: a drag so short that
               Leaflet scores it as a click instead of a drag ends in
               selectPoint() on the same point, and that ends in
               drawMarker(). The marker sprang back while the coordinate
               field kept the dragged value — field and marker then said
               different things, and nothing showed which one counted.

               The unsaved mark is the right question to ask, not „is it
               selected": closing and switching points both clear the mark
               (clearSelection, selectPoint) BEFORE they redraw, so there
               the marker still springs back to where the database has
               it — which is exactly what should happen. */
            const held = chosen && fields.coord.classList.contains('is-unsaved');
            if (!held) moveMarker(m, point);
            m.options.title = point.title || t('Ohne Titel');
        }

        const selected = point.id === state.selectedId;
        // THE RING GOES TO `shown`, NOT TO `selected`: it marks the point
        // the map is pointing at, and on the phone that is the peeked one.
        // Dragging below stays with `selected` — see the note there.
        // Only a changed look is set — see symbolFor().
        const icon = symbolFor(point, shown, point.is_collection ? (grown.get(point.id) ?? []).length : 0);
        if (m.options.icon !== icon) m.setIcon(icon);
        if (m.dragging) {
            // AREAS CANNOT BE DRAGGED. Their marker is not a spot someone
            // set but the center computed from the outline. Moving it would
            // not take the area along — the point would then lie next to
            // what was mapped, and map sheet and terrain height would come
            // from there. An area is traced over the aerial image anyway;
            // moving it as a whole afterwards does not happen. Whoever
            // wants it different redraws the outline.
            selected && !polygon.isUsable(outlineNow(point))
                ? m.dragging.enable()
                : m.dragging.disable();
        }
    }

    // THE SHARED ONES AFTER THE OWN ONES, in the same budget: if capping
    // happens, the own stock wins — it is the reason you open the map.
    // Points that also exist as own ones (the list you attached yourself)
    // are not in the store in the first place — see rebuildShared().
    for (const point of state.sharedPoints.values()) {
        /* THE SAME EXEMPTION AS FOR ONE'S OWN, and here it was missing
           ENTIRELY (found 25.08.2026 while fixing the loop above): a
           selected foreign point lost its marker to the filter, to the
           viewport and to the marker cap alike, while its window stood
           open. And a foreign point CAN be dragged — with the 'move'
           right, see below — so this is not theory. */
        const selected = point.id === state.selectedId;
        // The peek reaches a foreign point through the very same row, so
        // it is exempt here too — the reasoning stands in the loop above.
        const shown = selected || point.id === state.peekedId;

        if (!shown && point.collection_id != null && sampleKnown(point.collection_id)) continue;
        if (!shown && !sharedVisible(point) && !(point.is_collection && anyGrownShown(point.id))) continue;
        if (!shown && !inView(point, frame)) continue;
        if (!shown && wanted.size >= MARKER_MAX) { outside++; continue; }

        wanted.add(point.id);

        let m = marker.get(point.id) ?? createSharedMarker(point);

        // A dragged, unsaved marker keeps its spot — the reasoning stands
        // at the same line in the loop above.
        const held = selected && fields.coord.classList.contains('is-unsaved');
        if (!held) moveMarker(m, point);
        m.options.title = `${point.title || t('Ohne Titel')} — ${point.owner}`;
        // THE RING HERE TOO. It says "this is the one I mean", and that
        // holds for a foreign point just the same — it gets the same
        // window, and on the phone the same peek.
        const icon = symbolFor(point, shown, point.is_collection ? (grown.get(point.id) ?? []).length : 0);
        if (m.options.icon !== icon) m.setIcon(icon);
        // Dragging only on the selected one and only with the right — as
        // with an own point, see createSharedMarker().
        if (m.dragging) {
            selected && sharedRights(point).has('move')
                ? m.dragging.enable()
                : m.dragging.disable();
        }
    }

    for (const [id, m] of marker) {
        if (!wanted.has(id)) {
            map.removeLayer(m);
            marker.delete(id);
        }
    }

    drawPolygons(wanted);

    for (const hook of afterMarkerDraw) hook();

    // SAY IT ONLY ON THE CHANGE, not on every pan. A message that flashes up
    // again on every map movement is read by nobody after the third time —
    // and then not even the one that says something new.
    //
    // The LIST stays complete: what gets capped is what the map draws, not
    // what was found. That is why the message also says "auf der Karte".
    if (outside > 0 && !markersCapped) {
        status(t('Zu viele Punkte für diesen Ausschnitt — auf der Karte stehen %d von %d. Näher heranzoomen zeigt alle.',
            MARKER_MAX, MARKER_MAX + outside));
    }
    markersCapped = outside > 0;
}

/**
 * The outlines for the points that have one.
 *
 * THE MARKER STAYS. An area gets its outline in addition, not instead of
 * the marker: everything familiar hangs on the marker — selecting, moving,
 * the list's color, the title on hover —, and it also shows the spot from
 * which map sheet and height were derived. Leaflet places markers above the
 * polygons anyway, so it does not disappear underneath.
 *
 * @param {Set<number>} sichtbare  the same selection that also gets the markers
 */
function drawPolygons(visible) {
    const wanted = new Set();

    for (const id of visible) {
        // THE SHARED ONES TOO: drawMarker puts foreign points into the
        // visible budget as well, but here they were only looked up in the
        // own store — a foreign point with an outline therefore stood there
        // as a bare marker for the members, and nobody saw the mapped area
        // that is half the content of the share. pointColor() already knows
        // the shared lists; still no handles come into being, because
        // syncHandles only asks the own store — foreign outlines are
        // for looking at, not for dragging.
        const point = state.points.get(id) ?? state.sharedPoints.get(id);
        const selected = id === state.selectedId;

        // FOR THE SELECTED POINT THE DRAFT COUNTS, not the saved state —
        // the same rule as when dragging a marker, which also sits at the
        // new spot immediately and only springs back on closing. Without
        // this a freshly traced outline would vanish without a trace after
        // "Fertig" and only come back with saving: you would have walked
        // around the stand and would see nothing of it.
        const draft = selected && outlineDraft !== undefined;
        const outline = draft ? outlineDraft : point?.outline;
        if (!polygon.isUsable(outline)) continue;
        wanted.add(id);

        // The outline glows like its marker (neonColor).
        const color = neonColor(pointColor(point));
        const pending = draft || point.pending;
        const style = {
            color: color,
            weight: selected ? 4 : 2,
            opacity: point.pending ? 0.65 : 0.95,
            fillColor: color,
            fillOpacity: selected ? 0.3 : 0.18,
            // Dashed as long as none of it is at the server: either because
            // it has not been saved yet or because the transfer is waiting.
            // The same sign as the paler marker, just readable for a line.
            dashArray: pending ? '6 5' : null,
        };

        let f = polygons.get(id);
        if (!f) {
            f = L.polygon(outline, {
                ...style,
                className: 'point-polygon',
                // WITHOUT THIS, VISIBLY NOTHING HAPPENS ON TAP.
                //
                // Leaflet passes clicks on a path on to the map — unlike
                // markers, which carry bubblingMouseEvents: false out of
                // the box for exactly this. So the area selected the point,
                // and immediately afterwards the click on the map lifted
                // the selection again. Measured, not read: Leaflet reported
                // two targets for the same click.
                bubblingMouseEvents: false,
            }).addTo(map);
            f.on('click', () => selectPoint(id));
            // No point from a long press or right click above an area either
            // — one way to a point, everywhere (see map.on('contextmenu')).
            f.on('contextmenu', (e) => L.DomEvent.stop(e));
            polygons.set(id, f);
        } else {
            f.setLatLngs(outline);
            f.setStyle(style);
        }
    }

    for (const [id, f] of polygons) {
        if (!wanted.has(id)) {
            map.removeLayer(f);
            polygons.delete(id);
        }
    }

    // BIG ONES TO THE BACK. If a small area lies inside a big one — a fairy
    // ring in the searched stand —, it would otherwise no longer be
    // clickable depending on drawing order. Leaflet does not sort paths on
    // its own; the order is that of insertion.
    //
    // Only when the set or the sizes have changed: bringToFront() re-hangs
    // every area in the tree, and drawMarker() runs on every selection.
    // But the order depends on the outlines alone.
    const sizes = [...polygons.entries()]
        .map(([id, f]) => [id, polygon.areaM2(f.getLatLngs()[0].map(p => [p.lat, p.lng])) ?? 0])
        .sort((a, b) => b[1] - a[1]);

    const signature = sizes.map(([id, m2]) => `${id}:${Math.round(m2)}`).join(',');
    if (signature !== polygonOrder) {
        polygonOrder = signature;
        for (const [id] of sizes) polygons.get(id).bringToFront();
        // The strip for inserting a corner stays on top (see outlineLine):
        // reordered below the area, a tap just inside the edge went to the
        // area, which is already selected, and nothing happened.
        outlineLine?.bringToFront();
    }

    syncHandles();
}

let polygonOrder = '';

// ---------------------------------------------------------------------------
// Readjusting corners
//
// When tracing you tap corner by corner, and in the field over an aerial
// image rarely does each one sit right straight away. Redrawing a whole
// area because of that is too much — you want to grab the one corner that
// sits off.
//
// DRAG ONLY, NO ADDING OR REMOVING. This is readjusting, and for that it
// suffices. Inserting and deleting corners would need gestures of their own
// (long press is already taken) and an undo of its own — with redrawing you
// get both for free.
//
// THE SPOT FOLLOWS. When a corner is moved, the center computed from the
// outline moves along, and with it map sheet and terrain height.
// ---------------------------------------------------------------------------

/**
 * How big an area must be on screen for handles to appear.
 *
 * A finger covers around 45 pixels. If the whole area is hardly bigger, the
 * corners' hit fields overlap and you always catch the wrong one. Then
 * better no handles: whoever wants to readjust zooms in, and has to anyway
 * to see where to.
 */
const HANDLES_FROM_PIXELS = 90;

const HANDLE_ICON = L.divIcon({
    className: 'outline-handle-field',
    html: '<span class="outline-handle"></span>',
    // Clearly bigger than the visible dot: this is the hit field for the
    // finger. A 14-pixel circle can be seen but not grabbed.
    iconSize:   [44, 44],
    iconAnchor: [22, 22],
});

let handles     = [];
let handlesFor = null;
let handleDragging = false;

/**
 * WHOSE OUTLINE THE HANDLES ARE CURRENTLY DRIVING. null means: the selected
 * point, as ever. When editing an offline area the area hooks itself in
 * here (beginAreaEditing) — the SAME handles, gestures and menus,
 * including long press and insertion line, instead of a second version that
 * would have to be kept up with every phone subtlety separately. The source
 * supplies corners and polygon and takes the changed outline back.
 *
 * As long as a source is set, the point handles pause: whoever selects a
 * point on the side sees its area but no handles on it — two sets of
 * handles on one map, and you would pull the wrong one.
 */
let handleSource = null;

/**
 * The invisible version of the outline, for inserting a corner.
 *
 * WHY A SECOND LINE. The visible one is two pixels wide, and exactly that
 * wide is it to hit — impossible with a finger. This one is 26 pixels wide
 * and fully transparent; it only catches the tap. A visible strip of this
 * width would lie over half the forest edge one wants to see right now.
 *
 * It lies in the same pane as the polygon, thus BELOW the handles: at a
 * corner the handle wins, in between the line. Exactly that way around.
 */
let outlineLine = null;

function removeHandles() {
    for (const g of handles) map.removeLayer(g);
    handles = [];
    handlesFor = null;
    if (outlineLine) { map.removeLayer(outlineLine); outlineLine = null; }
}

/** Reconcile the handles with the source's outline — point or area. */
function syncHandles() {
    // Touch nothing mid-drag: a rebuild would take the handle out of the
    // finger's hand, and the gesture would break off midway.
    if (handleDragging) return;

    // The key distinguishes the sources ('point:5' / 'area:3'): if it
    // changes with the same corner count, the handles must still be redone.
    let key, corners, polygonThere;
    if (handleSource) {
        key = handleSource.key;
        corners      = handleSource.corners();
        polygonThere  = handleSource.polygon() != null;
    } else {
        const id    = state.selectedId;
        // The shared ones too — but ONLY with the move right: it covers the
        // whole geometry (the call, 14.08.2026), marker and area
        // alike. Without the right, the foreign outline stays handle-less
        // viewing.
        const point = id === null ? null
            : state.points.get(id) ?? state.sharedPoints.get(id);
        key  = 'point:' + id;
        corners       = point ? outlineNow(point) : null;
        if (point && state.sharedPoints.has(id)
            && !sharedRights(point).has('move')) {
            corners = null;
        }
        polygonThere   = polygons.has(id);
    }

    // Not while tracing: there every tap sets a new corner, and a handle in
    // between would intercept it.
    let wanted = !document.body.classList.contains('outlined')
        && polygon.isUsable(corners) && polygonThere;

    if (wanted) {
        const [sw, ne] = polygon.bounds(corners);
        const a = map.latLngToContainerPoint(sw);
        const b = map.latLngToContainerPoint(ne);
        wanted = Math.max(Math.abs(a.x - b.x), Math.abs(a.y - b.y)) >= HANDLES_FROM_PIXELS;
    }

    if (!wanted) { removeHandles(); return; }

    // Where the polygon follows during dragging — per source, captured once
    // here so the drag handle below does not have to think for itself.
    const handlePolygon = () => handleSource
        ? handleSource.polygon()
        : polygons.get(state.selectedId);

    if (handlesFor !== key || handles.length !== corners.length) {
        removeHandles();
        handlesFor = key;

        for (const corner of corners) {
            const g = L.marker(corner, {
                icon: HANDLE_ICON,
                draggable: true,
                keyboard: false,
                // Above the point markers, so a handle at the edge does not
                // vanish under a marker that happens to lie there.
                zIndexOffset: 1000,
            }).addTo(map);

            // A SHORT TAP ON A CORNER MUST NOT CLOSE EVERYTHING.
            //
            // Without this hook it lifted the selection and the handles
            // were gone — while readjusting you constantly miss by a bit,
            // and each time you started over. The reason is the same as
            // with the polygon: Leaflet picks its target only among the
            // layers that listen to the kind at all. Whoever does not
            // listen to 'click' counts as not hit, and then the map is the
            // target.
            //
            // The tap is meant to do nothing. The corner already sits there.
            g.on('click', (e) => L.DomEvent.stop(e));

            // The polygon follows during the drag already. Without that you
            // would pull a loose dot around and only see on release what
            // has become of it.
            g.on('dragstart', () => { handleDragging = true; closeCornerMenu(); });
            g.on('drag', () => {
                handlePolygon()?.setLatLngs(handlesAsCorners());
            });
            g.on('dragend', () => {
                lastDragEnd = Date.now();
                handleDragging = false;
                cornerMoved();
            });

            // THIS IS THE CONFLICT THAT WAS TO BE WARNED ABOUT — measured: a
            // contextmenu on a handle runs through to the map and creates a
            // point there. Leaflet passes it on because the handle itself
            // does not listen to it; with that the map becomes the target.
            // A long press on a corner would thus have created a new find
            // point in the middle of the area.
            //
            // Here it listens to it, and with that it stops.
            g.on('contextmenu', (e) => {
                L.DomEvent.preventDefault(e);
                L.DomEvent.stop(e);
                showCornerMenu(g);
            });

            bindLongPress(g);
            handles.push(g);
        }

        outlineLine = L.polygon(corners, {
            className: 'outline-line',
            fill: false,
            weight: 26,
            opacity: 0,
            bubblingMouseEvents: false,
        }).addTo(map);
        outlineLine.on('click', (e) => {
            L.DomEvent.stop(e);
            insertCorner(e.latlng);
        });
        // Here too: otherwise a long press on the line would create a point.
        outlineLine.on('contextmenu', (e) => L.DomEvent.stop(e));
    } else {
        corners.forEach((corner, index) => handles[index].setLatLng(corner));
        outlineLine?.setLatLngs(corners);
    }
}

/**
 * The long press on a handle — counted ourselves.
 *
 * WHY NOT VIA contextmenu ALONE. Leaflet produces it from a long press only
 * on Safari (Map.TapHold); Android delivers it from the operating system,
 * and there the drag mechanics with their preventDefault suppress it
 * sometimes one way, sometimes the other. A gesture that works on one
 * device and not on the other is none. The hook on contextmenu stays
 * nevertheless — as right click on the desktop and as a lock against the
 * point that would otherwise appear underneath.
 *
 * MOVEMENT ABORTS. Whoever drags the handle wants to drag it, not delete
 * it — eight pixels of wobble is still a hold, no more.
 */
const HANDLE_LONG_PRESS_MS = 550;
const HANDLE_WOBBLE_PX   = 8;

function bindLongPress(g) {
    const el = g.getElement();
    if (!el) return;

    let timer = null;
    let start = null;
    let grabbed = false;   // the long press has taken over, the click no longer counts

    const cancel = () => {
        if (timer !== null) { clearTimeout(timer); timer = null; }
        start = null;
    };

    el.addEventListener('pointerdown', (e) => {
        if (e.button !== 0) return;
        start = { x: e.clientX, y: e.clientY };
        grabbed = false;
        timer = setTimeout(() => {
            timer = null;
            grabbed = true;
            navigator.vibrate?.(20);
            showCornerMenu(g);
        }, HANDLE_LONG_PRESS_MS);
    });

    el.addEventListener('pointermove', (e) => {
        if (!start) return;
        if (Math.abs(e.clientX - start.x) > HANDLE_WOBBLE_PX
         || Math.abs(e.clientY - start.y) > HANDLE_WOBBLE_PX) cancel();
    });

    for (const eventType of ['pointerup', 'pointercancel', 'pointerleave']) {
        el.addEventListener(eventType, cancel);
    }

    // THE CLICK AFTER THE LONG PRESS MUST GO, otherwise the menu closes
    // again the very moment you lift the finger — and the "Ecke löschen"
    // button can never be hit.
    //
    // The reason lies in Leaflet: closePopupOnClick makes the map close all
    // popups on every click. Whoever opens a popup IN the click notices
    // nothing of it — the closing (preclick) runs before. This one opens
    // during the press already though, long before the release, and the
    // click afterwards clears it away.
    //
    // Do not switch the closing off: a click elsewhere SHOULD take the menu
    // away. Only this one click does not count, because it belongs to the
    // gesture that just opened it.
    el.addEventListener('click', (e) => {
        if (!grabbed) return;
        grabbed = false;
        e.stopPropagation();
        e.preventDefault();
    }, true);
}

/**
 * The little menu on a corner.
 *
 * One action, not a menu in the proper sense — but a button that must be
 * hit, instead of a press that deletes immediately. A corner that vanishes
 * when you grab slightly off would be expensive: you only notice once the
 * area already looks different.
 */
function showCornerMenu(g) {
    const index = handles.indexOf(g);
    if (index < 0) return;

    const box = L.DomUtil.create('div', 'eckenmenue');
    if (handles.length <= 3) {
        // No dead button: where there is nothing to delete, it says why.
        box.innerHTML = '<span class="corner-menu-hint">' + t('Drei Ecken müssen bleiben.') + '</span>';
    } else {
        const removeButton = L.DomUtil.create('button', 'button danger small', box);
        removeButton.type = 'button';
        removeButton.textContent = t('Ecke löschen');
        L.DomEvent.on(removeButton, 'click', (e) => {
            L.DomEvent.stop(e);
            closeCornerMenu();
            deleteCorner(index);
        });
    }

    g.unbindPopup();
    g.bindPopup(box, {
        className: 'corner-window',
        closeButton: false,
        offset: [0, -8],
        maxWidth: 170,
        // autoPan MUST be on. The popup sits centered above the corner, and
        // a corner at the screen edge is the normal case — that is exactly
        // where you drag. Without the nudge, half the menu stood outside
        // ("Drei Ecken müssen ble…"), and the delete button could not be
        // hit.
        autoPan: true,
        autoPanPadding: [20, 20],
    }).openPopup();
}

function closeCornerMenu() {
    for (const g of handles) g.closePopup();
}

/** The handles' positions, rounded to the resolution of the coordinate columns. */
function handlesAsCorners() {
    return handles.map((g) => {
        const p = g.getLatLng();
        return [Number(p.lat.toFixed(6)), Number(p.lng.toFixed(6))];
    });
}

/**
 * Adopt a changed outline — moved, inserted, deleted.
 *
 * Always the same path: into the fields first, effective with "Speichern".
 * Three ways here, but only one place that knows everything that has to be
 * pulled along afterwards.
 *
 * The three ways are HANDLE gestures and thus belong to the source: if an
 * offline area is currently driving the handles, it takes the outline — the
 * point fields stay untouched. The trace-finished jobs (tracing an area,
 * redrawing an outline) bypass this function and never hit the switch.
 */
function applyOutline(corners, what) {
    if (handleSource) {
        handleSource.apply(corners, what);
        return;
    }
    const center = polygon.center(corners);
    if (!center) return;

    outlineDraft = corners;
    fields.coord.value = `${center[0].toFixed(6)}, ${center[1].toFixed(6)}`;
    drawOutlineRow(corners);
    showMoveCoords();
    markUnsaved(true);
    drawMarker();

    status(t('%s — %s. Noch nicht gespeichert.', what, polygon.readableArea(polygon.areaM2(corners))));
}

function cornerMoved() {
    applyOutline(handlesAsCorners(), t('Ecke verschoben'));
}

/**
 * Take a corner away.
 *
 * BELOW THREE CORNERS THERE IS NO AREA. Then nothing is deleted; instead
 * the why is said — whoever wants to be rid of the area has "Umriss
 * remove" for that.
 */
function deleteCorner(index) {
    const corners = handlesAsCorners();
    if (corners.length <= 3) {
        status(t('Drei Ecken müssen bleiben. Zum Auflösen „Umriss entfernen".'), true);
        return;
    }
    corners.splice(index, 1);
    applyOutline(corners, t('Ecke gelöscht'));
}

/**
 * Put a corner in between, where the line was tapped.
 *
 * THE MATH IS DONE IN PIXELS, not in degrees. In degrees, "near" is at our
 * latitude horizontally only two thirds of what it is vertically — the new
 * corner would then jump onto the wrong edge as soon as two edges lie
 * nearby.
 */
function insertCorner(latlng) {
    const corners = handlesAsCorners();
    if (corners.length < 3) return;

    const pointer = map.latLngToLayerPoint(latlng);
    let best = null;

    for (let i = 0; i < corners.length; i++) {
        const a = map.latLngToLayerPoint(L.latLng(corners[i]));
        const b = map.latLngToLayerPoint(L.latLng(corners[(i + 1) % corners.length]));
        const foot = L.LineUtil.closestPointOnSegment(pointer, a, b);
        const distance  = pointer.distanceTo(foot);
        if (best === null || distance < best.distance) best = { distance, at: i, foot };
    }

    const newCorner = map.layerPointToLatLng(best.foot);
    corners.splice(best.at + 1, 0, [
        Number(newCorner.lat.toFixed(6)), Number(newCorner.lng.toFixed(6)),
    ]);
    applyOutline(corners, t('Ecke eingefügt'));
}

/** How deep a list hangs; the top level is 0. */
function listDepth(id) {
    return Math.max(0, listAncestors(id).length - 1);
}

/**
 * Which lists really count right now.
 *
 * SINGLE SELECTION: every leaf is chosen for itself, a heading cannot be
 * chosen at all anymore — its click only folds the branch. It used to be
 * the opposite ("checked is the parent list, meant is the whole branch");
 * on the switch, startup rewrites the remembered selection once, see above
 * at the start data.
 *
 * Parent ids can STILL stand in the selection: from old settings, and so
 * that the legacy points still living directly in a heading stay visible.
 * They now act only on exactly these points — no longer on the branch.
 */
/**
 * In no ORDINARY list — that is the reading of "Ohne Liste".
 *
 * Auto lists do not count as filing: they are the result of their filter,
 * not a store, and a point that only a filter has caught is still not
 * sorted in. The server computes with the same reading (autoliste_trifft,
 * data.php) — if the two diverged, a point would stand under "Ohne Liste"
 * here and no longer be one there.
 */
function pointWithoutList(point) {
    return point.lists.every(id => state.lists.get(id)?.is_autolist);
}

function visibleBranch() {
    /* THE SHARED CHOICE COUNTS TOO. An OWN list that you hung into a
       container yourself stands in the sidebar twice: above under "Meine
       Listen" and below under "Geteilt". Its points are own ones though —
       rebuildShared() deliberately leaves them out of the foreign
       store, otherwise the same point would lie on the map twice. So if you
       check it BELOW, the choice must arrive here; otherwise the checkmark
       does nothing, and exactly that was the case: the maintainer checked his
       attached list under "Geteilt" and got neither markers nor rows.

       Ids are unique across all accounts (one table), so the two sets
       cannot get in each other's way. Without a shared choice the existing
       set goes back unchanged — no new Set per pass, and this runs over all
       points. */
    if (state.visibleShared.size === 0) return state.visible;
    return new Set([...state.visible, ...state.visibleShared]);
}

/**
 * @param {Set<number>} [gilt] The resolved branch, computed once per pass.
 *        Without the argument it is built here — convenient for single
 *        calls, but in a loop over all points that would be the whole tree
 *        per point. With a few hundred points that goes unnoticed, with a
 *        few thousand very much not, and this application is meant to grow.
 */
function pointVisible(point, visibleLists = visibleBranch()) {
    if (point.deleted_at) return false;

    // A guest's finds lie in no list, and there is no tree to choose one
    // from — the list question does not exist for him. Everything else
    // (search, time, filters) applies as for everyone.
    if (!GUEST) {
        // … or lies in a joint list ticked under „Geteilt" (22.09.2026).
        const matches = point.lists.some(id => visibleLists.has(id) || state.visibleShared.has(id));
        // "Ohne Liste" is represented in the selection as id 0.
        const withoutList = pointWithoutList(point) && state.visible.has(0);
        /* AN OWN FIND OF A TICKED EXCURSION COMES ALONG, whatever list it
           lies in (26.08.2026: „eine Liste, nur Exkursionen, wo
           erstmal alles drin ist … meine Punkte und alle Gastpunkte").
           
           Whoever mapped along on an excursion filed those finds in a list
           of his own — and would then see the guests' finds but not his
           beside them, which is the one comparison the entry exists for.
           An OR, not a replacement: the list choice keeps working exactly
           as before while no excursion is ticked. */
        const inExcursion = point.excursion_id != null
            && (state.visibleExcursions.has('all')
                || state.visibleExcursions.has(point.excursion_id));
        if (!matches && !withoutList && !inExcursion) return false;
    }

    if (!filter.matchTerms(state.terms, pointHaystack(point))) return false;

    // The abbreviation checks read THE TITLE ONLY (why is written at
    // openMarksIn); the certainty checks read the point's own field.
    if (!workedMatches(state.identification, point.recordings)) return false;
    if (!identificationMatches(state.identification, point.title,
            point.extra?.[CERTAINTY_FIELD])) return false;

    if (state.noTime && point.found_at !== null) return false;

    // The threat status comes ready-made from the server (arten_gefaehrdung
    // in species.php) and likewise means only the title — for the same reason
    // as above.
    if (!filter.rlMatches(state.rl, point.red_list)) return false;
    // The DGfM marks — the same reading as the Red List's, see filter.js.
    if (!filter.edibilityMatches(state.edibility, point.dgfm)) return false;
    if (!filter.responsibilityMatches(state.responsibility, point.dgfm)) return false;

    // The height band applies to foreign points as to own ones — a filter
    // that is visibly on and has no effect on half the stock is worse than
    // none (the same reasoning as for the identification group above).
    if (!filter.elevationMatches(state.elevation, point.elevation_m)) return false;

    // The ranks apply to foreign points as to own ones — container.php
    // hands them the same way, so this is not a promise but a fact.
    if (!filter.taxonomyMatches(state.taxonomy, point.taxonomy)) return false;

    if (!pointInTimeRange(point)) return false;
    if (state.tour !== '' && !tourMatches(point)) return false;

    // The place — drawn areas, region, municipality, map sheet in one
    // test (placeMatches in filter.js): each OR within, AND against the
    // rest. lat/lon is the marker's spot, for an area point the spot
    // inside its outline — good enough: whoever draws a filter area
    // around a stand has its point in it.
    if (!filter.placeMatches(placeConditions(), point)) return false;

    return true;
}


/**
 * Does the point lie in the chosen time range?
 *
 * Without a time filter always yes. A point without any time drops out as
 * soon as filtering happens — it cannot lie in a range, and showing it
 * anyway would mean silently watering down the result.
 */
function pointInTimeRange(point) {
    // ON DAYS, NOT ON TIMESTAMPS — since 14.08.2026 the same arithmetic as
    // in Organisieren (assets/js/timerange.js), so both views show the same set
    // for the same input. A point's day is its find time, failing that the
    // creation time.
    const { from, to } = state.timeRange;
    return timeRange.inTimeRange(timeRange.dayOf(point), from, to);
}
const { fields, photoFields, sequenceFields } = pointWindowElements();

let outlineDraft = undefined;

/** The outline that currently applies: the draft, otherwise the saved one. */
function outlineNow(point) {
    return outlineDraft === undefined ? (point?.outline ?? null) : outlineDraft;
}

/**
 * SYNCHRONOUS UNLESS A QUESTION IS DUE. For a day (20./21.09.2026) this
 * was an async function with `await leaveOk()` at its head, on the
 * belief that without unsaved input it would run through before
 * returning. It does not: an await yields ALWAYS, promise or not. The
 * outline tool calls selectPoint(pointId) and then, in the same breath,
 * writes the drawn corners into the draft and marks the window unsaved
 * — and the suspended selectPoint resumed afterwards, filled the window
 * afresh and threw the draft away (fillDetail resets it). „Ich konnte
 * das Polygon nicht speichern" (21.09.2026). So the question
 * is asked only where it is due, and everything else stays on the stack.
 */
function selectPoint(pointId) {
    if (state.selectedId !== pointId && detailDirty()) {
        leaveOk().then((ok) => { if (ok) selectPointNow(pointId); });
        return;
    }
    selectPointNow(pointId);
}

function selectPointNow(pointId) {
    // At the desk no window opens — the name tag stands instead (deskCockpit).
    if (deskCockpit()) {
        const found = state.points.get(pointId) ?? state.sharedPoints.get(pointId);
        if (found) deskPeek(found);
        return;
    }
    /* THE SAME POINT AGAIN IS NOT A NEW SELECTION, and telling the two
       apart is what keeps a typed title alive.
    
       Everything below used to be unconditional, the refill FORCED. For a
       different point that is right: whoever opens another one wants to
       see it as it is saved, discarded leftovers included. For the point
       that is ALREADY OPEN it is the opposite of right — it throws away
       what has been typed since, and clears the unsaved mark on top, so
       nothing is left to show that anything was lost.
    
       And it happens on ordinary paths, not exotic ones: "Umriss ziehen"
       re-selects the point when the polygon is done (below, in the drawing
       tool), and a tap on the point's own row in the sidebar does the
       same. Type a name, walk the outline, confirm — name gone, and the
       window says "Noch nicht gespeichert" about the outline while the
       name it just deleted goes unmentioned.
    
       fillDetail() has had the right guard all along: it leaves the fields
       alone when somebody has typed in them. It only needs to be allowed
       to work. */
    const again = state.selectedId === pointId;

    // Whatever opens the window supersedes the peek — a marker tap
    // included, which never passes through the peek's own button.
    peekHide();

    state.selectedId = pointId;
    // OWN OR FOREIGN — the same window. Until 14.08.2026 a foreign point
    // got a modal of its own that listed the same details once more in its
    // own form: two windows for one point, and neither could do what the
    // other could. Now the detail window shows both, and what you may not
    // do stands greyed out in it (see applyDetailRights).
    const point = state.points.get(pointId) ?? state.sharedPoints.get(pointId);
    if (!point) return;

    if (!again) {
        markUnsaved(false);
        speciesSources = {};    // the choices belonged to the previous point
    }
    fillDetail(point, !again);
    detailVisible(true);
    // Incidentally resets a previously moved but unsaved marker to its
    // saved position.
    drawMarker();
    drawList();
}

function clearSelection() {
    state.selectedId = null;
    // What was not saved does not apply — the outline included. Otherwise,
    // after closing, an area would stand on the map that does not exist in
    // the database.
    outlineDraft = undefined;
    markUnsaved(false);
    detailVisible(false);
    drawMarker();
    drawList();
}

/** Close the window the user's way: asking first when something is unsaved. */
async function closeDetail() {
    if (await leaveOk()) clearSelection();
}
/* THE TAB IS „BELEGE" SINCE 22.09.2026 and holds the sequences too
   („in dem mittleren Tab wird man ja zum Mitbesitzer — das
   wäre Fotos und Sequenzen"). Its number counts both, and it goes only
   when BOTH have nothing to show: pictures switched off or hidden, and
   no sequence field either. */
let sequenceTabCount = 0;
/* AND THE FIND'S OTHER FILES since 05.10.2026 (pointfiles.js): offered on
   one's own find once the cloud's answer says the server keeps them
   (filesOffered), counted like the others. */
let filesTabCount = 0;
let filesOffered = false;

/* MAPPING FOR AN EXCURSION — a badge, not a message
   (24.08.2026: „ganz knapper Text, der auf einem Mobiltelefon nicht einen
   Großteil der Fläche frisst"). The name and a cross, next to the
   waiting badge: whoever ends it taps the cross, and from the next find
   on nothing carries the excursion any more.

   THE SERVER DECIDES, NOT THIS BADGE. It reads the session, and so does
   point_create's caller — this here says what is true and offers the
   way out; it cannot pin a find onto anything by itself.

   THE CROSS ASKS FIRST (13.09.2026: „Eine Teilnehmerin
   gestern hat das wohl weggeklickt und danach natürlich nicht mehr für
   die Exkursion kartiert"). One tap on a small cross at the top of the
   screen ended the day's mapping without a word — and every find after
   it was hers alone. Now the question stands, with what it costs and
   the way back in it; and the way back is real since the same day: the
   offer below returns the moment the badge goes, because the window is
   still open. Not red, therefore — a step one can undo alone is no
   farewell (CLAUDE.md, the three farewells). */
function mappingBadgeInto(stack) {
    /* Into the stack, not onto the map: there it stands in the row of
       the map's buttons, between them, and everything else queues up
       underneath (map.css, .map-badges). AT ITS HEAD, so the markup
       reads like the picture — the stylesheet places this badge by row
       and column and would not care, but a keyboard walks the markup,
       and the cross of the outing should come before the answers to a
       request that arrived later. The fallback keeps an old cached page
       working — the badge is then simply the map's child, as it was
       before. */
    return (badge) => {
        if (stack) stack.prepend(badge);
        else document.getElementById('map').append(badge);
    };
}

let mappingBadgeRefresh = null;

function drawMappingBadge(mapping) {
    document.querySelector('.excursion-badge')?.remove();
    mappingBadgeRefresh = null;
    const badge = document.createElement('div');
    badge.className = 'excursion-badge';
    /* The name in a span, like the friendship label below: a bare text
       node in a flex row cannot give way, and since 12.09.2026 the badge
       stands in the narrow column between the map's buttons, where a
       long name has to wrap (map.css, .excursion-badge-name). */
    const name = document.createElement('span');
    name.className = 'excursion-badge-name';
    /* „AB 09:00" WHILE THE WINDOW IS AHEAD (13.09.2026): whoever joined at
       home the day before wears the badge already, and it must not claim
       what is not yet true — the finds carry the excursion only from the
       start (api/v1/points.php). The minute clock of the offer below
       rewrites it when the window opens. */
    const label = () => excursionAhead(mapping.starts_at)
        ? t('Exkursion: %s · ab %s', mapping.name, mapping.from)
        : t('Exkursion: %s', mapping.name);
    name.textContent = label();
    badge.dataset.ahead = excursionAhead(mapping.starts_at) ? '1' : '';
    badge.append(name);
    mappingBadgeRefresh = () => {
        const ahead = excursionAhead(mapping.starts_at) ? '1' : '';
        if (badge.dataset.ahead === ahead) return;
        badge.dataset.ahead = ahead;
        name.textContent = label();
    };
    const stop = document.createElement('button');
    stop.type = 'button';
    stop.className = 'symbol-button small';
    stop.title = t('Nicht mehr für diese Exkursion kartieren');
    stop.setAttribute('aria-label', stop.title);
    stop.append(icon('close'));
    stop.addEventListener('click', async () => {
        const sure = await confirm({
            title: t('Nicht mehr für die Exkursion kartieren?'),
            text: t('Deine nächsten Funde gehören dann nur noch dir, nicht mehr zu „%s". Zurück geht es über „Teilnehmen", solange die Exkursion läuft — bis %s.',
                mapping.name, mapping.until ?? '…'),
            button: t('Nicht mehr kartieren'),
        });
        if (!sure) return;
        stop.disabled = true;
        try {
            await request('excursion_mapping.php', { method: 'DELETE' });
            badge.remove();
            mappingBadgeRefresh = null;
            start.mapping = null;
            status(t('Deine Funde gehören ab jetzt nur noch dir.'));
            // The way back, right where the badge stood.
            drawMappingOffer();
        } catch {
            stop.disabled = false;
            status(t('Ging gerade nicht — ohne Netz bleibt es, wie es ist.'), true);
        }
    });
    badge.append(stop);
    mappingBadgeInto(document.getElementById('map-badges'))(badge);
}

/* THE OFFER TO MAP ALONG (13.09.2026: „Das Teilnehmen an der
   eigenen Exkursion ist etwas kompliziert in der Verwaltung versteckt.
   Alle Exkursionsleiter sollen automatisch, wenn sie die App öffnen,
   eine Badge angezeigt kriegen — jetzt teilnehmen —, wenn sie im
   Zeitfenster der Exkursion sind, dort, wo die anderen Badges auch
   angezeigt werden, oben zwischen den beiden Buttons").

   WHAT IS OFFERED: the excursions the server listed (start.mappingOffers
   — the ones he leads and the ones he already joined, so a participant
   who tapped the cross away finds the way back too), whose window is
   open NOW by the map's own clock — the page may have been opened before
   nine for a nine o'clock start, so this is asked again every minute and
   whenever the tab comes back. Not while mapping for one already, and
   not one that was declined: the cross on the offer means „heute nicht",
   remembered per excursion in the settings, and an excursion's id never
   comes twice. One offer at a time, the earliest window first — two
   badges in the button row would stand on each other.

   ACCEPTING IS THE ADMINISTRATION'S 'map_along' WITHOUT THE DETOUR
   (excursion_map_along on the server): the answer carries the same
   'mapping' the start payload does, and the badge above takes the
   offer's place without a reload. */
const mappingOfferTimer = { id: null };

function mappingOfferNow() {
    if (start.mapping) return null;
    const declined = new Set(settings.get('mappingDeclined', []) || []);
    const now = Date.now();
    for (const e of start.mappingOffers ?? []) {
        if (declined.has(e.id)) continue;
        const from = new Date(String(e.starts_at).replace(' ', 'T')).getTime();
        const to   = new Date(String(e.ends_at).replace(' ', 'T')).getTime();
        if (Number.isFinite(from) && Number.isFinite(to) && from <= now && now <= to) return e;
    }
    return null;
}

function drawMappingOffer() {
    const stack = document.getElementById('map-badges');
    if (!stack) return;
    const offer = mappingOfferNow();
    const shown = stack.querySelector('.excursion-badge.is-offer');
    if (!offer) { shown?.remove(); return; }
    if (shown && Number(shown.dataset.excursion) === offer.id) return;
    shown?.remove();

    /* TWO PARTS (13.09.2026, on the first draft: „kannst Du
       ruhig zweiteilig machen — den Exkursionsnamen oben, dann
       teilnehmen / nicht teilnehmen"): the name on its own line, the
       two answers as words underneath. No cross: „Nicht teilnehmen" is
       the cross, spelled out. */
    const badge = document.createElement('div');
    badge.className = 'excursion-badge is-offer';
    badge.dataset.excursion = String(offer.id);
    const name = document.createElement('span');
    name.className = 'excursion-badge-name';
    name.textContent = t('„%s" läuft', offer.name);
    const choice = document.createElement('div');
    choice.className = 'excursion-badge-choice';
    const join = document.createElement('button');
    join.type = 'button';
    join.className = 'text-button excursion-badge-join';
    join.textContent = t('Teilnehmen');
    join.addEventListener('click', async () => {
        join.disabled = true;
        try {
            const back = await request('excursion_mapping.php', {
                method: 'POST', body: JSON.stringify({ excursion: offer.id }),
            });
            start.mapping = back.mapping;
            badge.remove();
            drawMappingBadge(back.mapping);
            status(t('Du kartierst jetzt für „%s".', back.mapping.name));
        } catch (e) {
            join.disabled = false;
            status(e?.message || t('Ging gerade nicht — ohne Netz lässt sich nicht teilnehmen.'), true);
        }
    });
    const no = document.createElement('button');
    no.type = 'button';
    no.className = 'text-button excursion-badge-decline';
    no.textContent = t('Nicht teilnehmen');
    no.addEventListener('click', () => {
        const declined = [...new Set(settings.get('mappingDeclined', []) || [])];
        declined.push(offer.id);
        settings.set('mappingDeclined', declined);
        badge.remove();
        drawMappingOffer();
    });
    /* NO LEFT, YES RIGHT (13.09.2026: „dass Teilnehmen auf der
       rechten Seite steht, weil das für Rechtshänder einfach zu erreichen
       ist") — the same order as the friendship badge's answers, for the
       same thumb. */
    choice.append(no, join);
    badge.append(name, choice);
    mappingBadgeInto(stack)(badge);
}

if (start.mapping) drawMappingBadge(start.mapping);
drawMappingOffer();
// The clock: a window that opens while the page is up, or after the
// phone slept through the start — for the offer and for the badge's
// „ab 09:00" alike.
const mappingClock = () => { mappingBadgeRefresh?.(); drawMappingOffer(); };
mappingOfferTimer.id = setInterval(mappingClock, 60 * 1000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) mappingClock(); });

/* A FRIENDSHIP REQUEST IS A KNOCK ON THE DOOR — and until 25.08.2026
   nobody heard it. The count stood on a tab of the administration and
   nowhere else, so a request reached whoever happened to walk past that
   tab; a guest or a fresh test friend never learned of it at all
   („oder muss er da zufällig in die Verwaltung und das
   entdecken?").

   ANSWERED IN THE BADGE ITSELF (his call, same day). The two buttons are
   the whole point: an announcement that only says „go to the
   administration" is a second errand, and the errand was the problem.
   Yes and no are one tap away, and the badge disappears with the answer.

   ONE BADGE PER REQUEST. Two people asking on one day is rare, and
   rolling them into „2 Anfragen" would take away the name — and the name
   is what makes the answer possible without going somewhere else. They
   queue in the stack like everything else.

   THE LIST COMES FROM THE CHAT POLL, which runs anyway; see chat.js.
   Nothing is asked more often because of this. */
function drawFriendRequests() {
    const stack = document.getElementById('map-badges');
    if (!stack) return;

    const open = chat.friendRequests();
    const shown = new Map();
    for (const badge of stack.querySelectorAll('.friend-badge')) {
        shown.set(badge.dataset.friendship, badge);
    }

    // Gone means answered — here, on another device, or by the asker
    // withdrawing it.
    for (const [id, badge] of shown) {
        if (!open.some(r => String(r.id) === id)) badge.remove();
    }

    for (const r of open) {
        if (shown.has(String(r.id))) continue;

        const badge = document.createElement('div');
        badge.className = 'friend-badge';
        badge.dataset.friendship = String(r.id);
        /* THE LABEL IN A SPAN, and that is not decoration: a bare text
           node cannot be shrunk. Measured on a 375 px phone with a long
           name — „Maximiliane von Hohenzollern-Sigmaringen" pushed BOTH
           buttons off the screen, and a yes one cannot reach is no
           answer. Only an element takes flex — and, since 12.09.2026,
           wraps instead of ending in three dots (map.css). */
        const label = document.createElement('span');
        label.className = 'friend-badge-name';
        label.textContent = t('Freundschaftsanfrage von %s', r.name);
        badge.append(icon('people'), label);

        const answer = async (action, button) => {
            for (const b of badge.querySelectorAll('button')) b.disabled = true;
            try {
                const back = await request('friends.php', {
                    method: 'POST',
                    body: JSON.stringify({ id: r.id, action }),
                });
                chat.setFriendRequests(back.friendRequests ?? []);
                status(action === 'accept'
                    ? t('%s ist jetzt dein Freund.', r.name)
                    : t('Anfrage von %s abgelehnt.', r.name));
            } catch {
                for (const b of badge.querySelectorAll('button')) b.disabled = false;
                status(t('Ging gerade nicht — ohne Netz bleibt die Anfrage stehen.'), true);
            }
        };

        /* NO FIRST, YES SECOND (25.08.2026: „weil man nimmt ja
           öfters an, als man ablehnt"). The right edge is where the thumb
           lands, so the common answer belongs there — and the rare one
           moves out of that path, which is the same move that prevents
           the mis-tap.

           The class says which is which; the ORDER must not, or the
           colours would follow the arrangement instead of the meaning
           the next time somebody rearranges them. */
        for (const [name, label, action, role] of [
            ['close', t('Ablehnen'), 'decline', 'friend-badge-no'],
            ['check', t('Annehmen'), 'accept', 'friend-badge-yes'],
        ]) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'symbol-button small ' + role;
            b.title = label;
            b.setAttribute('aria-label', label + ' — ' + r.name);
            b.append(icon(name));
            b.addEventListener('click', () => answer(action, b));
            badge.append(b);
        }

        stack.append(badge);
    }
}

/* THE ANSWER COMES BACK AS A BADGE TOO (13.09.2026: „Bekomme
   ich eine Matchnachricht, wenn eine Freundschaftsanfrage angenommen
   wurde? Wenn nicht, macht das bitte"). Until then the asker learned it
   from the friends list, if he looked. Not a toast: a message that fades
   while the phone lies in the pocket is not delivered. The badge stands
   until its cross, and the cross tells the server (friendship_seen), so
   the second device stays quiet. Green rim like the request's — it is
   the same friendship, now made. */
function drawFriendAccepted() {
    const stack = document.getElementById('map-badges');
    if (!stack) return;

    const news = chat.friendAccepted();
    const shown = new Map();
    for (const badge of stack.querySelectorAll('.friend-badge.is-accepted')) {
        shown.set(badge.dataset.friendship, badge);
    }
    for (const [id, badge] of shown) {
        if (!news.some(r => String(r.id) === id)) badge.remove();
    }
    for (const r of news) {
        if (shown.has(String(r.id))) continue;
        const badge = document.createElement('div');
        badge.className = 'friend-badge is-accepted';
        badge.dataset.friendship = String(r.id);
        const label = document.createElement('span');
        label.className = 'friend-badge-name';
        label.textContent = t('%s hat deine Anfrage angenommen — ihr seid jetzt Freunde.', r.name);
        badge.append(icon('people'), label);
        const ok = document.createElement('button');
        ok.type = 'button';
        ok.className = 'symbol-button small';
        ok.title = t('Gelesen');
        ok.setAttribute('aria-label', ok.title + ' — ' + r.name);
        ok.append(icon('close'));
        ok.addEventListener('click', async () => {
            ok.disabled = true;
            // Gone at once; the server's word follows, and if the network
            // is away the next poll brings it back — better twice than
            // never.
            badge.remove();
            try {
                const back = await request('friends.php', {
                    method: 'POST', body: JSON.stringify({ id: r.id, action: 'seen' }),
                });
                chat.setFriendAccepted(back.friendAccepted ?? []);
            } catch { /* the poll will say */ }
        });
        badge.append(ok);
        stack.append(badge);
    }
}

// Drawn on every change the poll reports, and once right away for what
// was already waiting when the map opened.
chat.onUnreadChange(drawFriendRequests);
chat.onUnreadChange(drawFriendAccepted);
chat.adoptAccepted(start.friendAccepted ?? []);
chat.adoptRequests(start.friendRequests ?? []);
drawFriendRequests();
drawFriendAccepted();

/* THE NEW LISTENADMINISTRATOR IS TOLD IN A WINDOW (09.10.2026):
   whoever inherits a joint list by chance — the one who has worked in it
   longest — may never have shared a list; a line in the chat he would read
   past, if he opens the chat at all. One window per list, once; the help
   explains what the role means (the chapter comes with the help's rebuild). */
/* „IN DER LISTE ÄNDERN" from the administration (Gemeinsam → Geteilt):
   index.php?edit_list=<id> opens that list's window once the map stands.
   A tick later, not now: the window's code lies further down this module.
   The address is cleaned at once, so a reload does not open it again. */
{
    const wanted = Number(new URLSearchParams(location.search).get('edit_list'));
    if (wanted > 0) {
        history.replaceState(null, '', location.pathname + location.hash);
        setTimeout(() => editList(wanted), 0);
    }
}

(async function tellListAdmins() {
    for (const n of start.list_admin_notices ?? []) {
        const answer = await ask({
            title: t('Du bist jetzt Listenadministrator von „%s"', n.name),
            text: t('Wer die Liste bisher verwaltet hat, ist gegangen. Weil du am längsten mitarbeitest, verwaltest jetzt du sie: Du bestimmst, wer sie sieht und was jeder darf. Das stellst du im Fenster „Liste bearbeiten" unter „Teilen" ein. Wer schon mitgearbeitet hat, bleibt dabei.'),
            buttons: [
                { label: t('In der Hilfe nachlesen'), value: 'help', subtle: true },
                { label: t('Zur Liste'), value: 'list' },
            ],
        });
        try {
            await request('list_recipients.php', { method: 'POST', body: JSON.stringify({ action: 'admin_seen', container: n.container }) });
        } catch { /* asked again next time — better twice than never */ }
        if (answer === 'help') window.open(`${start.baseUrl}pages/help.php#listenadministrator`, '_blank');
        if (answer === 'list') editList(n.list);
    }
})();

/* THE VISIBLE VIEWPORT now comes from assets/js/viewport.js — it lives
   there because every page needs it, not only this one: a full-screen
   window in the administration pushed its footer under the keyboard
   while the map knew better (the maintainer's Xiaomi tablet, 24.08.2026).
   Imported for its side effect, like dialogs.js and autoheight.js. */
import './viewport.js';
// „ẞ" in every heading the stylesheet sets in capitals — eszett.js.
import './eszett.js';

const moveBar = document.getElementById('move-bar');
const moveCoord  = document.getElementById('move-coord');
const moveTitle  = document.getElementById('move-title');

/**
 * Move mode on the phone.
 *
 * There the detail window fills the whole screen — so you do not see the
 * map at all and cannot grab the marker. "Zeigen" therefore folds the
 * window away and leaves the bar standing, through which you find your way
 * back.
 *
 * Nothing is saved: dragging only writes the new position into the
 * coordinate field, as on the desktop. Whoever goes back and presses
 * "Speichern" secures move and edit in one go; whoever closes the window
 * finds the point at its old spot.
 */
function moveMode(on) {
    document.body.classList.toggle('moves-point', on);
    moveBar.hidden = !on;
    if (on) {
        // With an area there is nothing to move here — the window only
        // steps aside so you can see the map.
        const point = state.points.get(state.selectedId)
            ?? state.sharedPoints.get(state.selectedId);
        moveTitle.textContent = polygon.isUsable(outlineNow(point))
            ? t('Fläche ansehen')
            : t('Marker verschieben');
        // The sidebar must go too. Otherwise the detail window does step
        // aside, but behind it the list stands across the full width — and
        // you still do not see the map this is about.
        setSidebar(true);
        showMoveCoords();
    }
}

function showMoveCoords() {
    moveCoord.textContent = fields.coord.value;
}

/**
 * Where the name in a field came from — the source per field name.
 *
 * Only what somebody CHOSE from the suggestion list goes in here. Whoever
 * types by hand leaves no entry; no reference is made of it either. Exactly
 * right that way: "Fichte, vermutlich" denotes no taxon.
 *
 * The server turns this into the stable reference. It knows which id
 * stands in its table — the browser would otherwise have to lug it along,
 * and in the offline packages it would be 36 characters per species, more
 * than the name itself.
 */
let speciesSources = {};

/** Narrow screen — the detail window then lies over the whole map. */
function isPhone() {
    return window.matchMedia('(max-width: 860px)').matches;
}

/* AT THE DESK A FIND IS EDITED IN THE FUNDBROWSER (12.10.2026:
   „dass in der Kartenansicht in Desktop die Fund-Seitenleiste komplett
   verschwindet. Wenn wir da was editieren wollen, gehen wir in den
   Fundbrowser"). The point window stays the phone's — there it is no
   sidebar but the whole screen, and everything there stays as it was
   („All das lass so erstmal in der Mobilansicht"). At the desk a click on a
   point shows its name tag (deskPeek), the pencil in the tag and in the
   points list leads to the Fundbrowser, and a point just set opens there on
   its texts.

   NOT FOR A GUEST, who has no Fundbrowser, AND NOT OFFLINE: the Fundbrowser
   is the desk's and online only (11.10.2026); a desk without
   network keeps the window, which works through the queue. */
function deskCockpit() {
    return !isPhone() && !GUEST && navigator.onLine !== false;
}

/** Where the Fundbrowser opens a find — on its texts for a find just set.
    `from=map` lets the find's arrow lead back HERE rather than to the tiles
    (finds.js, route). */
function findsAddress(id, tab = null) {
    return `${start.baseUrl}pages/finds.php#fund=${id}${tab ? `&tab=${tab}` : ''}&from=map`;
}

document.getElementById('move-done').addEventListener('click', () => moveMode(false));

/**
 * What was last written into the window — and for which point.
 *
 * This tells apart whether the field still holds what this file wrote into
 * it, or whether somebody has typed since. The comparison is purely local;
 * it does not need the server.
 */
let lastFilled = { id: null, state: null };

/* THE DOOR, NOT THE BUCKET: the find carries on without me — at its holder,
   and in every list he shares with me otherwise. ONE deed for both places
   it is offered: the point window and the find's row under „Von <Name>"
   (09.10.2026: „lösch diese direkte Teilung, um aufzuräumen"). */
async function hintLeave(point) {
    if (!point || point.hint_from == null) return;
    const who = shortNameOf(point.hint_from_name ?? point.owner);
    const ok = await confirm({
        title: t('Direkte Teilung entfernen?'),
        text: point.lists.length > 0
            ? t('„%s" steht dann nicht mehr einzeln unter „Von %s". Du siehst den Punkt weiter in der Liste, die %s mit dir teilt.', point.title || t('Ohne Titel'), who, who)
            : t('„%s" verschwindet von deiner Karte. Wieder direkt teilen kann ihn nur %s.', point.title || t('Ohne Titel'), who),
        button: t('Entfernen'),
        danger: true,
    });
    if (!ok) return;
    try {
        await request('hints.php', { method: 'POST', body: JSON.stringify({ action: 'leave', point_id: point.id }) });
    } catch (e) {
        status(t('Die direkte Teilung ließ sich nicht entfernen: %s', e.message), true);
        return;
    }
    const open = state.selectedId === point.id;
    if (point.lists.length > 0) {
        // Still mine to see through a share — only the hint's mark goes.
        state.sharedPoints.set(point.id, { ...point, hint_from: null, hint_from_name: null });
        if (open) fillDetail(state.sharedPoints.get(point.id), true);
    } else {
        state.sharedPoints.delete(point.id);
        if (open) clearSelection();
    }
    drawMarker();
    drawList();
    status(t('Direkte Teilung entfernt.'));
    // The bucket on the device still holds it — the fresh fingerprints clear it.
    syncHints(true).then(() => { drawMarker(); drawList(); }).catch(() => {});
}

/**
 * The second line of an entry: what makes the point recognizable again.
 *
 * ONLY WHAT IS THERE. Counted across 145 grown points: sheet on 139, height
 * on all, a find date by contrast on two. A fixed row of fields would yield
 * for most entries a chain of separator dots without content — so what
 * exists is collected and the rest falls away.
 *
 * THE FIND DATE BEATS THE CREATION DATE. They are almost always the same;
 * if not, the point was entered later than the find was, and then the find
 * date is the one meant. It is still not labeled — two words per line for a
 * distinction that rarely occurs cost more space than they explain.
 *
 * THE NOTE IS NOT INCLUDED, although 127 of 145 points have one. It varies
 * in length and would push the details you are looking for out of view.
 * Facts stand here in fixed order; whoever wants the text opens the point.
 */
/**
 * The glyphs before the details — kalender, karte, berg, flaeche, foto,
 * uhr, ort, points, all from the library (includes/icon.php).
 *
 * THAT THEY STAND THERE AND NOT HERE has a reason you can see in this
 * sentence: it once stood here as — "The map sheet is the same as in the
 * sidebar header; a glyph drawn differently in two places is two glyphs."
 * A copy stood right next to it all the same, because the sentence alone
 * holds nothing together. Now the library holds it.
 *
 * No size given: eleven pixels are set by the fact line's CSS, and so it
 * should stay.
 */
function factIcon(name) {
    return iconHtml(name);
}

function pointFacts(point) {
    const parts = [];

    /* THE FIND NUMBER FIRST — the same rule as in the point window's fact
       row (fillDetailUneditable): minted number or, without a pattern,
       the database id; finds only, and nothing while the point is still
       provisional. The hash icon carries the label so the line stays
       narrow — "AM-2026-0001" alone would read as one more mystery code,
       the # says what it is. */
    // An Aufsammlung carries its box number (CW-A-…), never a „Y-" mark
    // (0174) — it lies in a species list, but it is no find.
    if (point.is_collection) {
        if (point.collection_number) parts.push(['hash', point.collection_number]);
    } else if (pointIsFind(point)) {
        const number = point.find_number ?? (point.id > 0 ? findNumberFallback(point.id) : null);
        if (number !== null) parts.push(['hash', number]);
    }

    const date = point.found_at || point.created_at;
    if (date) parts.push(['calendar', readableDate(date)]);

    // THE MUNICIPALITY, NOT THE SHEET. "5816/311" is the place reference
    // for the mapping office, for a human it is none — "Mittenwald" is.
    // Whoever skims a list looks for the area they recognize.
    //
    // ONLY THE NAME, not the whole path. "DE/BAY/" stands on almost every
    // point and would add nothing to the line; in the point window it
    // stands complete, because that is where you copy it from.
    //
    // THE SHEET LEFT THE ROW on 30.09.2026, as it left the point window's
    // short line („Das MTB brauchen wir eigentlich auch nicht
    // … aus dem gleichen Grund" — „das ist eher was zum Kartieren").
    // Whoever maps still finds it: the search matches it (haystack.js),
    // the filter has its own field, and the point window's facts table
    // shows it.
    if (point.municipality) parts.push(['place', point.municipality]);

    if (point.elevation_m != null) parts.push(['mountain', `${point.elevation_m} m`]);

    // For an area the size: in a list of fifty rows you otherwise cannot
    // tell from an entry whether a pin or a ninety-hectare survey area is
    // behind it.
    if (polygon.isUsable(point.outline)) {
        parts.push(['area', polygon.readableArea(polygon.areaM2(point.outline))]);
    }

    /* THE COUNT TAKES THE CLOUD ALONG (11.09.2026: „in der
       linken Sidebar bei Punkte werden nur die Aufnahmen als Fotos
       gezählt, nicht die Fotos, die in der Cloud liegen"). The same
       correction the photo tab got on 10.09.2026, and for the same
       reason: to the eye a picture is a picture — where its bytes lie
       is the storage's business, not the reader's. The two sets do not
       overlap, a voucher photo that moved to pCloud stays its
       map_photos row and never becomes a map_cloud_files one.
       `recordings` is the count the server derives; it is corrected
       from the gallery's own cloud answer as soon as the find is
       opened (drawPhotoGallery), so a picture uploaded on this device
       shows in the row without waiting for the next round. */
    const photoCount = (point.photos?.length ?? 0) + (point.recordings ?? 0);
    if (photoCount > 0) parts.push(['photo', photoCount === 1 ? t('1 Foto') : t('%d Fotos', photoCount)]);

    /* THE „BEARBEITET" MARK (30.08.2026): cloud recordings
       hang on the find — micrographs, stacking shots, identification
       material, better than the plain phone photo. The count is derived
       server-side from the recordings themselves and can only exist on
       one's own stock. */
    if (point.recordings > 0) parts.push(['microscope', t('bearbeitet')]);

    // What still lies on the device deserves saying — on the map you see it
    // by the paler marker, in the list so far not at all.
    if (point.pending) parts.push(['clock', t('wartet')]);

    return parts;
}

/** Build the detail line from the parts. */
function factsRow(parts) {
    const row = document.createElement('span');
    row.className = 'point-row-facts';

    for (const [icon, text] of parts) {
        const fact = document.createElement('span');
        fact.className = 'fact';
        fact.innerHTML = factIcon(icon);
        // The text as a node of its own: innerHTML would read it as markup,
        // and while a title does not go in here, a field value one day
        // will.
        fact.append(document.createTextNode(text));
        row.append(fact);
    }
    return row;
}

// readableDate() lives in dates.js since 30.09.2026 — the picture viewer
// writes it under every photo on both of its pages.

// ---------------------------------------------------------------------------
// Voucher photos
// ---------------------------------------------------------------------------


/* THE POINT WINDOW — out of this file on 11.10.2026, into pointwindow.js (the Fundbrowser mounts it as a second host).
   What it asks of the map, in one place: the values and functions below. */
const pointWindow = mountPointWindow({
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
    // Arrows: the gallery mounts further down (photogallery.js) and is not there yet when this host is built.
    drawPhotoGallery: (point) => drawPhotoGallery(point),
    drawSequences: (point) => drawSequences(point),
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
    get filesOffered() { return filesOffered; },
    set filesOffered(v) { filesOffered = v; },
    get filesTabCount() { return filesTabCount; },
    set filesTabCount(v) { filesTabCount = v; },
    get lastFilled() { return lastFilled; },
    set lastFilled(v) { lastFilled = v; },
    get outlineDraft() { return outlineDraft; },
    set outlineDraft(v) { outlineDraft = v; },
    get sequenceFields() { return sequenceFields; },
    get sequenceTabCount() { return sequenceTabCount; },
    set sequenceTabCount(v) { sequenceTabCount = v; },
    get speciesSources() { return speciesSources; },
    set speciesSources(v) { speciesSources = v; },
});
const {
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
} = pointWindow;

/* THE PICTURE VIEWER lives in photowindow.js since 30.09.2026 — step one
   of one viewer for the map and the administration. The map is its host:
   it says which find stands in the window, where a find is to be looked
   up, what the viewer may do there and how a message reaches the user.
   The mount stands exactly where the viewer's code stood, so everything
   it wires up at load happens in the old order. */
/* The sub-tab „Dateien" under „Belege" (pointfiles.js): it tells how many
   files there are, the tab bar does the rest. */
const pointFiles = mountPointFiles({
    request,
    bytes: (n) => cloudBytes(n),
    baseUrl: start.baseUrl,
    count: (n) => { filesTabCount = n; detailPhotoTab(); },
});

const { showPhoto, showCloudPhoto, cloudPhotoUrl, cloudBytes, measureStyle, cloudFilesShown } = mountPhotoWindow({
    start,
    guest: GUEST,
    pointId: () => state.selectedId,
    point: (id) => state.points.get(id) ?? state.sharedPoints.get(id),
    drawGallery: (point) => drawPhotoGallery(point),
    mayEditPhotos: mayEditPointPhotos,
    foreign: foreignInWindow,
    sharedRights,
    status,
});

// ---------------------------------------------------------------------------
// Plates (06.10.2026) — A4 compositions of a find's pictures, plates.js. It
// gets the pictures the gallery shows (both halves, everybody's in the
// circle), the find's details as words, and who may make one.
// ---------------------------------------------------------------------------

const plates = mountPlates({
    request,
    point: (id) => state.points.get(id) ?? state.sharedPoints.get(id),
    pictures(id) {
        const point = state.points.get(id) ?? state.sharedPoints.get(id);
        // Whose it is, where it is not mine — photoBy()'s rule.
        const by = (f) => (f.taken_by
            || (f.photographer_id != null && f.photographer_id !== start.user.id && f.mine !== true)
            ? f.photographer ?? null : null);
        const out = [];
        for (const f of point?.photos ?? []) {
            out.push({
                key: `photo:${f.id}`, title: f.title ?? null, order: f.sort_order ?? undefined,
                w: f.width ?? 0, h: f.height ?? 0, scale: f.scale ?? null, measures: f.measures ?? [], media: f.media ?? null, develop: f.develop ?? null,
                src: `${start.baseUrl}pages/photo.php?id=${f.id}`,
                thumb: `${start.baseUrl}pages/photo.php?id=${f.id}&k=1`,
                by: by(f),
            });
        }
        if (cloudFilesShown.pointId === id) {
            for (const f of cloudFilesShown.files) {
                out.push({
                    key: `cloud:${f.id}`, title: f.title ?? null, order: f.sort_order ?? undefined,
                    w: f.width ?? 0, h: f.height ?? 0, scale: f.scale ?? null, measures: f.measures ?? [], media: f.media ?? null, develop: f.develop ?? null,
                    src: cloudPhotoUrl(f), thumb: f.preview,
                    // The recording itself — the printed page fetches it where a cutout needs it.
                    original: `${start.baseUrl}pages/cloud_thumb.php?id=${f.id}&original=1`,
                    by: by(f),
                });
            }
        }
        return out;
    },
    /* A PICTURE OF THE PLATE IN THE VIEWER (08.10.2026): the same windows the
       gallery opens — false where the find does not hold it (any more). */
    openPicture(id, key) {
        const point = state.points.get(id) ?? state.sharedPoints.get(id);
        if (key.startsWith('photo:')) {
            const f = (point?.photos ?? []).find(p => `photo:${p.id}` === key);
            if (f) showPhoto(f);
            return !!f;
        }
        if (key.startsWith('cloud:') && cloudFilesShown.pointId === id) {
            const f = cloudFilesShown.files.find(p => `cloud:${p.id}` === key);
            if (f) showCloudPhoto(f);
            return !!f;
        }
        return false;
    },
    /* THE FIND'S DETAILS AS WORDS, the same words the point window shows —
       the plate prints them, it does not interpret them (finddetails.js,
       which the Fundbrowser's plates ask as well). */
    details(id) {
        const point = state.points.get(id) ?? state.sharedPoints.get(id);
        return findDetails(point, point ? pointIsFind(point) : false);
    },
    // The holder and the co-owners — the circle that works on the find.
    mayMake: (id) => !GUEST && (state.points.has(id) || foreignPoint(id)?.co_owned === true),
    photoTitles: () => measureStyle.photo_titles,
    // The account's measuring style — the air around a cut-out spore, per layer.
    style: () => measureStyle,
});
/* THE BELEGE — the gallery of a find's pictures (voucher photos and cloud recordings), the upload, the subject names, the sequences — out of this file on 12.10.2026, into photogallery.js (the Fundbrowser mounts the point window and wants the same Belege).
   What it asks of the map, in one place: the values and functions below. */
const photoGallery = mountPhotoGallery({
    GUEST,
    cloudBytes,
    cloudFilesShown,
    cloudPhotoUrl,
    detailPhotoTab,
    detailState,
    foreignPoint,
    mayEditPointPhotos,
    measureStyle,
    photoFields,
    plates,
    pointFiles,
    sequenceFields,
    showCloudPhoto,
    showPhoto,
    start,
    state,
    drawList,
    pointReady,
    status,
    get filesOffered() { return filesOffered; },
    set filesOffered(v) { filesOffered = v; },
    get sequenceTabCount() { return sequenceTabCount; },
    set sequenceTabCount(v) { sequenceTabCount = v; },
});
const {
    drawPhotoGallery,
    drawSequences,
} = photoGallery;


/**
 * Wait until the point has a real id.
 *
 * Since the window opens before the server has answered, one can type
 * faster than the line is. A PATCH or DELETE on the made-up negative number
 * the server would not find — so wait for the creation first and then fetch
 * the point anew from the stock; it no longer lies under the old id.
 *
 * Without network there is nothing to wait for: api.post has long assigned
 * a provisional id of its own, and the queue rewrites both later.
 *
 * @returns {Promise<object|null>} the point with a valid id, or null
 */
async function pointReady(point) {
    if (!point) return null;
    if (point.createRunning) {
        try { await point.createRunning; } catch { /* the message comes from createPoint */ }
    }
    /* THE POINT THAT WAS ASKED ABOUT, NOT THE ONE SELECTED NOW. Here stood
       state.points.get(state.selectedId), and while waiting the selection
       can move: the window gets closed, another marker is tapped, a sync
       clears it. The caller then went on with a DIFFERENT record than the
       one it had handed in — a photo landed on the wrong point, a delete
       asked about the wrong one.

       Following it works because swapPointKey mutates this very object
       (point.id = newKey) instead of replacing it. So the object stays the
       same across the swap from the provisional number to the real one,
       and its id says where it now lives. */
    return state.points.get(point.id) ?? null;
}

// ---------------------------------------------------------------------------
// Sidebar
// ---------------------------------------------------------------------------

const listSection = document.getElementById('list-section');
const pointSection  = document.getElementById('point-section');

/**
 * Lists on the left, points on the right.
 *
 * The two are connected — which points stand on the right is decided by the
 * selection on the left —, which is why one function draws both columns.
 */
/**
 * Heading of a section of the list page — "Meine Listen", "Geteilt".
 *
 * IT STANDS EVEN WHEN THERE IS ONLY ONE SECTION. Appearing only with the
 * first foreign share would mean the sidebar changes its face as soon as
 * somebody shares — and the fold handle has value on its own: whoever
 * keeps twenty lists and only needs the points page simply folds the block
 * shut.
 *
 * It carries the number (chosen/total) ONLY WHEN COLLAPSED, like the
 * branch headings in the tree: expanded, every row says it itself, and the
 * same statement twice on top of each other would be noise.
 */
function sectionTitle({ name, key, collapsed, choice = null, unread = 0 }) {
    const title = document.createElement('button');
    title.type = 'button';
    // COLLAPSED, THE TITLE LIGHTS UP as soon as something below it is
    // chosen — the same rule as on the branch headings in the tree, for the
    // same reason: you see that something in the block is on without having
    // to expand it for that. Expanded, the rows light up themselves.
    const active = collapsed && (choice?.checked ?? 0) > 0;
    title.className = 'sidebar-subheading is-collapsible'
        + (collapsed ? '' : ' is-open') + (active ? ' is-active' : '');
    title.setAttribute('aria-expanded', String(!collapsed));
    title.innerHTML = disclosureTriangle();

    const text = document.createElement('span');
    text.textContent = name;
    title.append(text);

    /* COLLAPSED IT ALWAYS STANDS THERE, EVEN AT ZERO
       (11.09.2026: „bei meine Listen und geteilt kannst Du die Badge auch
       anzeigen, wenn sie zusammengeklappt ist und nichts markiert ist —
       also null von soundsoviel"). Until then it appeared only from the
       first tick on, and so its absence said two things: „nothing on" and
       „nothing counts here". A head that is collapsed hides its whole
       block — then both belong there, the number and its denominator.
       It still lights up only with a tick (choiceBadge). */
    if (collapsed && choice && choice.total > 0) {
        title.append(choiceBadge(choice.checked, choice.total,
            t('%d von %d Listen sichtbar', choice.checked, choice.total)));
    }

    /* WAITING MESSAGES SURVIVE THE FOLD (26.08.2026, asking
       whether they were shown at all — they were not).

       Folded, this section returns before a single container row is
       built, so the speech bubbles that carry the mark never come into
       being. Somebody who keeps „Geteilt" shut — and one does, it is the
       block one does not need every day — would learn of a message only
       by opening it, which is precisely the state one does not open.

       A bubble on the heading, no number: the count belongs to the
       container it concerns, and the heading only has to say THAT
       something waits. Which one it is stands one click away. */
    if (collapsed && unread > 0) {
        const mark = document.createElement('span');
        mark.className = 'section-unread';
        mark.append(icon('speech-bubble'));
        mark.title = unread === 1
            ? t('In einem Chat wartet etwas')
            : t('In %d Chats wartet etwas', unread);
        mark.setAttribute('aria-label', mark.title);
        title.append(mark);
    }

    title.addEventListener('click', () => {
        if (state.sectionsCollapsed.has(key)) state.sectionsCollapsed.delete(key);
        else state.sectionsCollapsed.add(key);
        settings.set('sidebarSectionsCollapsed', [...state.sectionsCollapsed]);
        drawList();
    });

    return title;
}

/* How many filter conditions currently apply — assigned by the filter
   wiring further down (the single source is filterGroupCount there).
   Before that wiring runs, no condition can be active, and the default
   says so without touching consts that do not exist yet. */
let activeFilterConditions = () => 0;

/* The sentence under the time filter — assigned by the time filter's
   wiring further down, for the same reason as above: drawList runs long
   before the control exists, and it hands in the count it has just
   made (see there). Until then there is no sentence to say. */
let timeFilterHintRefresh = (_n) => {};

function drawList() {
    /* AT THE HEAD, because the function returns early in places: every
       filter change ends in a drawList call with the state already set,
       so this one line keeps the remembered bundle current without any
       control having to know about it — the next filter added cannot
       forget what it never had to call. Cheap when nothing changed
       (rememberFilterConditions compares before writing). */
    rememberFilterConditions();
    listSection.innerHTML = '';
    pointSection.innerHTML  = '';

    // The tree, level by level. Collapsed branches are skipped — their
    // points remain untouched by that, collapsing is a matter of overview
    // and not of selection.
    const children = listChildren();
    /* MY OWN JOINT LISTS STAND UNDER „GETEILT" ONLY
       (22.09.2026): special lists, shown where the others see them, with
       their pencil there (sharedRow). Up here they are skipped — the whole
       branch, and out of the heading's count as well. */
    const ownJoint = ownJointIds(children);

    // The leaves of a branch — for the heading's box and for the number on
    // a collapsed heading. Only leaves count: they are the only thing that
    // can be chosen.
    const branchLeaves = (id) => {
        const leaves = [];
        (function branch(parentId) {
            for (const k of children.get(parentId) ?? []) {
                if (ownJoint.has(k.id)) continue;
                // Container lists do not stand in the tree — then their
                // choice may not enter its number either (it counts
                // checkmarks one can see).
                if (k.is_container_list) continue;
                if ((children.get(k.id) ?? []).length > 0) { branch(k.id); continue; }
                leaves.push(k.id);
            }
        })(id);
        return leaves;
    };
    const leafChoice = (id) => {
        const leaves = branchLeaves(id);
        return { checked: leaves.filter(k => state.visible.has(k)).length, total: leaves.length };
    };

    const drawOne = (list, depth) => {
            // Container lists (migration 0051) do not belong in "Meine
            // Listen" — they appear under their container in the "Geteilt"
            // section, and only there. In the point window they stay as
            // checkmarks (drawListSelection): there you manage
            // memberships, that is not browsing the tree.
            if (list.is_container_list) return;
            if (ownJoint.has(list.id)) return;
            const hasChildren = (children.get(list.id) ?? []).length > 0;
            const collapsed = state.collapsed.has(list.id);
            // A heading's box stands for its leaves, folded or not.
            const leaves = hasChildren ? branchLeaves(list.id) : null;
            // Collapsed, the leaves are not visible — then the heading must
            // say how many of them are chosen.
            const choiceBelow = hasChildren && collapsed
                ? { checked: leaves.filter(k => state.visible.has(k)).length, total: leaves.length }
                : null;
            listSection.append(listRow({
                id: list.id,
                name: list.name,
                // The appearance comes from the list ITSELF: leaves carry
                // it, and only leaves show it — a list with sublists is a
                // heading and gets no dot in listRow(), only the
                // triangle.
                color: list.color,
                shape: list.symbol,
                // Headings no longer know a choice of their own — only
                // leaves do. COLLAPSED, the heading still lights up like a
                // chosen row as soon as something below it is chosen: you
                // see at a glance that something in the branch is on
                // without having to expand it. Expanded, the leaves light
                // up themselves and the heading stays quiet.
                active: hasChildren
                    ? (choiceBelow?.checked ?? 0) > 0
                    : state.visible.has(list.id),
                // The pencil stands on every main list, in a Mappe too
                // (depth 1 there): sublists are managed in their main
                // list's window, and a pencil that only leads there anyway
                // is twelve times the same target.
                editable: list.parent_id == null,
                depth,
                hasChildren,
                collapsed,
                chosen: choiceBelow,
                leaves,
            }));
            if (hasChildren && !collapsed) {
                drawLevel(list.id, depth + 1);
            }
    };
    const drawLevel = (parentId, depth) => {
        for (const list of children.get(parentId) ?? []) drawOne(list, depth);
    };

    /* THE TOP: MAPPEN AND LOOSE LISTS IN ONE ROW (migration 0168). A Mappe
       draws like a heading — box for all its lists, triangle, its own
       pencil — and its lists one step in. It is never a list: it holds
       nothing, carries nothing, and nothing can be ticked on it but what
       lies in it. */
    const drawTop = () => {
        for (const item of topLevel(children)) {
            if (item.list) { drawOne(item.list, 0); continue; }
            const key = 'f' + item.folder.id;
            const collapsed = state.collapsed.has(key);
            const leaves = item.lists.flatMap(l =>
                l.is_container_list || ownJoint.has(l.id) ? []
                    : (children.get(l.id) ?? []).length > 0 ? branchLeaves(l.id) : [l.id]);
            const chosen = { checked: leaves.filter(k => state.visible.has(k)).length, total: leaves.length };
            listSection.append(listRow({
                id: key,
                name: item.folder.name,
                folder: item.folder,
                active: collapsed && chosen.checked > 0,
                editable: true,
                hasChildren: true,
                collapsed,
                chosen: collapsed ? chosen : null,
                leaves,
            }));
            if (!collapsed) for (const l of item.lists) drawOne(l, 1);
        }
    };
    const without = [...state.points.values()].filter(p => !p.deleted_at && pointWithoutList(p)).length;

    // "Ohne Liste" counts towards the choice: it is choosable like a leaf,
    // and a number that omits it says "2/7" where three checkmarks stand.
    const myChoice = (() => {
        const w = leafChoice(null);
        if (without > 0) {
            w.total++;
            if (state.visible.has(0)) w.checked++;
        }
        return w;
    })();

    const myCollapsed = state.sectionsCollapsed.has('mine');
    listSection.append(sectionTitle({
        name: t('Meine Listen'), key: 'mine', collapsed: myCollapsed,
        choice: myCollapsed ? myChoice : null,
    }));

    if (!myCollapsed) {
        drawTop();

        if (without > 0) {
            listSection.append(listRow({
                id: 0,
                name: t('Ohne Liste'),
                color: '#8a97a4',
                active: state.visible.has(0),
            }));
        }
    }

    drawShared(listSection);
    drawExcursions(listSection);

    // The number on the lists tab: chosen lists, own and shared together.
    // It is visible from the points page — whoever wonders there about
    // missing points sees without switching that a filter is on.
    //
    // COUNTED IN LEAVES (myChoice.checked), not as state.visible.size: from the
    // first start, heading lists lie in there too, and those cannot be
    // checked at all. A 12 on the tab where at most 9 checkmarks exist
    // would be a number without a counterpart.
    // The ticked excursions count too (27.08.2026 — the tab
    // stayed at 6 with one ticked): each ticked row is one, and „Alle
    // Exkursionen" is one row like any other. The set holds exactly the
    // ticked keys, so its size IS that number.
    setTabCount(sidebarTabs.listCount,
        myChoice.checked + state.visibleShared.size + state.visibleExcursions.size);

    // Right column: the points of the chosen lists, filtered by the search.
    //
    // OWN AND FOREIGN IN ONE LIST. They lie in two stores (foreign points
    // are not own ones, see above) — but that is a matter of bookkeeping,
    // not of the view. Whoever checks a shared list wants to see its finds,
    // and namely where finds stand. Until here they stood ONLY as markers
    // on the map: a checked branch with 41 points left the "Punkte" tab at
    // 3, and you looked for the fault in yourself.
    const visibleLists = visibleBranch();
    const visible = [
        ...[...state.points.values()].filter(p => pointVisible(p, visibleLists)),
        ...[...state.sharedPoints.values()].filter(p => sharedVisible(p)),
    ];

    // The same number stands on the tab — visible there while you are still
    // on the list page.
    setTabCount(sidebarTabs.count, visible.length);
    // And in the sentence under the time filter — here, before the early
    // return below, so an empty result says „0 Punkte" there too.
    timeFilterHintRefresh(visible.length);

    pointSection.append(pointsHeader(visible));

    if (visible.length === 0) {
        /* SAY WHY (22.08.2026) — a state line in the flow,
           standing as long as the emptiness lasts. Only when conditions
           apply and points exist at all: an unchecked tree explains
           itself. */
        const conditions = activeFilterConditions();
        if (conditions > 0 && (state.points.size > 0 || state.sharedPoints.size > 0)) {
            const hint = document.createElement('p');
            hint.className = 'hint-text small filter-empty-hint';
            hint.textContent = conditions === 1
                ? t('Der Filter lässt keinen Punkt durch.')
                : t('Die Filter schließen sich gegenseitig aus — zusammen bleibt kein Punkt übrig.');
            pointSection.append(hint);
        }
        return;
    }

    if (state.sorting === 'name') {
        for (const group of byName(visible)) {
            // A single point needs no wrapper: a group of one that must be
            // expanded to see the same name once more would only be one
            // more click.
            if (group.points.length === 1) {
                pointSection.append(pointRow(group.points[0]));
                continue;
            }

            pointSection.append(groupRow(group));
            if (state.openNames.has(group.name)) {
                for (const point of group.points) {
                    pointSection.append(pointRow(point, true));
                }
            }
        }
        return;
    }

    for (const point of byTime(visible)) {
        pointSection.append(pointRow(point));
    }
}

/** Newest first — by find time, failing that by creation. */
function byTime(points) {
    return [...points].sort((a, b) => (timeRange.timestamp(b) ?? 0) - (timeRange.timestamp(a) ?? 0));
}

/**
 * Group by name.
 *
 * GROUPING GOES BY THE TITLE, not by the derived species table. The title
 * is what stands in the row — and what one wants to group is what one sees
 * twice. Grouping by something invisible would merge rows that look
 * different, and that would not be understandable.
 *
 * Case-insensitive, but the first occurring name wins for display:
 * "boletus edulis" and "Boletus edulis" are the same find, typed twice.
 */
function byName(points) {
    const groups = new Map();

    for (const point of points) {
        const name = (point.title || '').trim() || t('Ohne Titel');
        const key = name.toLocaleLowerCase('de');
        if (!groups.has(key)) groups.set(key, { name, points: [] });
        groups.get(key).points.push(point);
    }

    for (const group of groups.values()) {
        group.points = byTime(group.points);
    }

    // localeCompare, not the plain character comparison: otherwise
    // "Ähnliche" would stand after "Zypresse".
    return [...groups.values()].sort((a, b) => a.name.localeCompare(b.name, 'de'));
}

/**
 * The header above the list — number on the left, sort order on the right.
 *
 * It is the only row that always stands there; a strip of its own for two
 * buttons would be wasted height.
 */
function pointsHeader(visible) {
    const header = document.createElement('div');
    header.className = 'sidebar-subheading point-header';

    const text = document.createElement('span');
    if (visible.length === 0) {
        // The shared choice counts too: whoever has checked ONLY a shared
        // branch has very much chosen a list — "Keine Liste gewählt" would
        // have told him it was his own fault.
        const chosen = state.visible.size + state.visibleShared.size;
        // The guest has no lists to choose — his empty list means what it
        // says: nothing there yet.
        text.textContent = chosen === 0 && !GUEST ? t('Keine Liste gewählt') : t('Nichts gefunden');
    } else if (state.sorting === 'name') {
        const names = byName(visible).length;
        text.textContent = names === 1
            ? t('%d Punkte · %d Name', visible.length, names)
            : t('%d Punkte · %d Namen', visible.length, names);
    } else {
        text.textContent = visible.length === 1
            ? t('%d Punkt', visible.length)
            : t('%d Punkte', visible.length);
    }
    header.append(text);

    if (visible.length > 0) {
        const buttons = document.createElement('span');
        buttons.className = 'sort-buttons';
        buttons.append(
            sortButton('time', t('Nach Zeit — die neuesten zuerst'), CLOCK),
            sortButton('name', t('Nach Namen — gleiche zusammengefasst'), AZ),
        );
        header.append(buttons);
    }

    return header;
}

// The third parameter was once called 'zeichen' — and thereby shadowed the
// library function of the same name in the whole body. Such names you
// assign wrongly once and search for the bug three times.
function sortButton(type, label, image) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'sort-button' + (state.sorting === type ? ' is-active' : '');
    button.title = label;
    button.setAttribute('aria-label', label);
    button.setAttribute('aria-pressed', String(state.sorting === type));
    button.innerHTML = image;
    button.addEventListener('click', () => {
        state.sorting = type;
        settings.set('sorting', type);
        drawList();
    });
    return button;
}

/** One row for several points of the same name. */
function groupRow(group) {
    const open = state.openNames.has(group.name);

    const row = document.createElement('button');
    row.type = 'button';
    row.className = 'point-row point-group' + (open ? ' is-open' : '');
    row.setAttribute('aria-expanded', String(open));
    row.innerHTML = disclosureTriangle();

    const content = document.createElement('span');
    content.className = 'point-row-content';

    const title = document.createElement('span');
    title.className = 'point-row-title';
    title.textContent = group.name;
    content.append(title);

    // How many, and over which period — for a grouping that is the detail
    // that distinguishes a series of finds from a single find.
    const times = group.points.map(p => timeRange.timestamp(p)).filter(t => t !== null);
    const span = times.length === 0 ? '' : (() => {
        const day = (ms) => new Date(ms).toLocaleDateString('de-DE',
            { day: '2-digit', month: '2-digit', year: 'numeric' });
        const from = day(Math.min(...times)), to = day(Math.max(...times));
        return from === to ? from : `${from} – ${to}`;
    })();

    // The same make as for a single point: glyph, value, wrappable.
    const parts = [['points', t('%d Punkte', group.points.length)]];
    if (span) parts.push(['calendar', span]);
    content.append(factsRow(parts));

    row.append(content);

    row.addEventListener('click', () => {
        if (open) state.openNames.delete(group.name);
        else state.openNames.add(group.name);
        drawList();
    });

    return row;
}

/** A row for a single point. */
function pointRow(point, indented = false) {
    // WHETHER FOREIGN IS DECIDED BY THE STORE, not the caller. The list
    // mixes both kinds, and pointRow() is called in three places — a flag
    // each of them would have to set correctly itself would at one of them
    // eventually be wrong.
    const foreign = state.sharedPoints.has(point.id);

    const row = document.createElement('button');
    row.type = 'button';
    row.className = 'point-row'
        + (point.id === state.selectedId || point.id === state.peekedId ? ' is-active' : '')
        + (indented ? ' is-child' : '');
    row.dataset.pointId = String(point.id);
    row.innerHTML = shapePoint(pointColor(point), pointShape(point));

    const content = document.createElement('span');
    content.className = 'point-row-content';

    const text = document.createElement('span');
    text.className = 'point-row-title';
    text.textContent = point.title || t('Ohne Titel');
    content.append(text);

    const facts = pointFacts(point);
    // WHOSE FIND, AND FIRST OF ALL. In a mixed list you otherwise cannot
    // tell from a row whether it belongs to you — and what you can do with
    // it hangs on that. Foreign ones only: on the own ones the same name
    // would stand in every row and say nothing.
    // The short form, the same one the sidebar carries: a name that reads
    // as "Finn" over there and as "Finn Horn" here would look like two
    // people. The little person in front of it comes from the fact itself.
    if (foreign && point.owner) facts.unshift(['person', shortNameOf(point.owner)]);
    if (facts.length > 0) content.append(factsRow(facts));

    row.append(content);

    /* THE PENCIL AT THE DESK (12.10.2026: „an jedem Eintrag, wenn
       man drüber fährt, ein Bearbeiten-Stift. Klickt man den, kommt man
       direkt in die Bearbeitung"): into the Fundbrowser. A span and not a
       button — the row is a button already, and a button inside a button
       is no HTML; map.css shows it under the pointer and only beside the
       phone's 860. Not on a point the server does not know yet. */
    if (!GUEST && point.id > 0) {
        const edit = document.createElement('span');
        edit.className = 'point-row-edit';
        edit.setAttribute('role', 'link');
        edit.title = t('Im Fundbrowser bearbeiten');
        edit.setAttribute('aria-label', edit.title);
        edit.innerHTML = iconHtml('pencil');
        edit.addEventListener('click', (e) => {
            e.stopPropagation();
            location.href = findsAddress(point.id);
        });
        row.append(edit);
    }

    row.addEventListener('click', () => {
        /* ON THE PHONE THE MAP COMES FIRST (30.08.2026: „soll
           nicht gleich das Bearbeiten aufgehen, sondern der Punkt soll
           in der Karte gezeigt werden") — the detail window would cover
           the very map the tap was aiming for. The peek shows the spot,
           names it and offers the one button; at the desk list and
           window stand side by side, so it opens directly as ever. */
        if (isPhone()) {
            peekPoint(point);
            return;
        }
        // Own or foreign — the same window; what you may not do stands
        // greyed out in it (applyDetailRights). At the desk: the name tag (deskCockpit).
        selectPoint(point.id);
        // A deliberate jump elsewhere — the location must not pull the
        // map back (see pauseFollowing).
        pauseFollowing();
        // Areas fully into view, points zoomed to — see "Zeigen". In the
        // middle of what the window leaves visible (centreVisible).
        if (polygon.isUsable(point.outline)) {
            fitVisible(polygon.bounds(point.outline));
        } else {
            centreVisible([point.lat, point.lon], Math.max(map.getZoom(), 16));
        }
    });

    /* THE DOUBLE CLICK EDITS (28.10.2026: „bei Punkte auf einen
       Punkt doppelklicke, soll er mir gleich zur Bearbeitung aufgehen") —
       the pencil's way, into the Fundbrowser. The first click of the two
       has already shown the point; that is fine, the page leaves anyway.
       Only where the pencil stands: at the desk, online, a known point. */
    row.addEventListener('dblclick', () => {
        if (GUEST || !(point.id > 0) || !deskCockpit()) return;
        location.href = findsAddress(point.id);
    });

    return row;
}

/* --- The phone's point peek ---------------------------------------------
   One floating line over the map: the point's name and ONE small button —
   the pencil where editing is allowed, the eye where the united rights of
   a shared find allow only looking (30.08.2026). The button
   opens the same window a desk tap opens; a tap into the map dismisses
   the peek without opening anything. */

function peekHide() {
    /* NOTHING PEEKED, NOTHING TO DO — and that is not a saving but the
       point: this runs on EVERY tap into the map, and without the guard
       every one of them would redraw all markers. */
    if (state.peekedId === null) return;
    state.peekedId = null;
    document.getElementById('point-peek').hidden = true;
    peekPlace = null;
    // The ring goes with the bubble; both said the same thing.
    drawMarker();
    markPointRows();
}

function peekPoint(point) {
    /* THE MARK BEFORE THE FLIGHT. Moving the map fires 'moveend', and
       that redraws the markers — a redraw that must already know which
       point may not be capped away, or the flight lands on nothing. */
    state.peekedId = point.id;
    /* Close the overlaying sidebar so the map the peek points into is
       actually visible — WITHOUT remembering: this is navigation, not
       the user's fold decision (see setSidebar). */
    setSidebar(true, false);
    pauseFollowing();
    if (polygon.isUsable(point.outline)) {
        map.fitBounds(polygon.bounds(point.outline), { padding: [40, 40] });
    } else {
        map.setView([point.lat, point.lon], Math.max(map.getZoom(), 16));
    }
    peekFill(point);
    document.getElementById('point-peek-image').hidden = true;
    // The phone's peek floats bottom centre — the desk's placing goes.
    const peek = document.getElementById('point-peek');
    peek.classList.remove('is-desk', 'is-above');
    document.getElementById('point-peek-open').classList.remove('subtle');
    peek.style.left = peek.style.top = '';
    peekPlace = null;
    peek.hidden = false;
    // The ring, as on the selected point — and nothing else opened.
    drawMarker();
}

/** The tag's name and its one button — the phone's peek and the desk's name tag alike. */
function peekFill(point) {
    document.getElementById('point-peek-title').textContent
        = point.title || t('Ohne Titel');
    /* The pencil or the eye. sharedRights can hand back an always-yes
       object instead of a Set (an excursion find) — its size is
       undefined, and undefined must read as "may write". */
    const foreign = foreignPoint(point.id);
    const rights = foreign === null ? null : sharedRights(foreign);
    const mayWrite = rights === null || rights.size === undefined || rights.size > 0;
    const open = document.getElementById('point-peek-open');
    open.innerHTML = iconHtml(mayWrite ? 'pencil' : 'eye');
    open.title = deskCockpit()
        ? (mayWrite ? t('Im Fundbrowser bearbeiten') : t('Im Fundbrowser ansehen'))
        : (mayWrite ? t('Bearbeiten') : t('Ansehen'));
    open.setAttribute('aria-label', open.title);
    // A point not yet on the server has nothing to open in the Fundbrowser.
    open.hidden = deskCockpit() && !(point.id > 0);
}

/**
 * THE DESK'S NAME TAG (12.10.2026: „Punkt anklicken zeigt nur
 * noch den Namen … und vielleicht eine kleine Bildvorschau"): the phone's
 * peek, where the map stays where it is — the click was aimed at what one
 * sees —, with the find's first picture small in front of the name (the
 * Hauptmotiv, api/v1/finds.php?cover=). The pencil leads to the Fundbrowser.
 */
/* UNDER THE POINT, THE PICTURE BELOW THE NAME (15.10.2026:
   „Kann man das direkt unter den Punkt setzen? Und das Bildchen dann ein
   bisschen größer … unterhalb des Textes, dass wir so eine kleine
   Vorstellung haben von dem Fund"). The tag hangs from the point with a
   small tip and travels with it while the map moves; where there is no
   room below, it stands above. */
function deskPeek(point) {
    state.peekedId = point.id;
    peekFill(point);
    const peek = document.getElementById('point-peek');
    peek.classList.add('is-desk');
    /* QUIET AT THE DESK (15.10.2026: „in der Desktop-Version
       brauche ich das nicht"): the tag hangs at the point, so the pencil
       needs no accent to be found. The phone's peek floats apart from its
       point and keeps the loud one (peekPoint). */
    document.getElementById('point-peek-open').classList.add('subtle');
    const image = document.getElementById('point-peek-image');
    image.hidden = true;
    image.removeAttribute('src');
    if (point.id > 0) {
        request(`finds.php?cover=${point.id}`)
            .then((answer) => {
                if (state.peekedId !== point.id || !answer?.thumb) return;
                image.src = answer.thumb;
                image.hidden = false;
            })
            .catch(() => { /* a tag without a picture is a tag */ });
    }
    peekPlace = [point.lat, point.lon];
    peek.hidden = false;
    placeDeskPeek();
    drawMarker();
    markPointRows(true);
}

/* THE ROW SAYS IT TOO (28.10.2026: „wenn ich in der Sidebar
   einen Punkt anklicke und er mir hervorgehoben wird, in der Karte kann er
   in der Sidebar auch umrandet sein"). At the desk no window opens, so
   selectedId stays empty and the row knew nothing of the tag on the map;
   the peeked point is chosen just as much. Toggled on the rows that stand
   instead of redrawing the list — a click on a marker must not rebuild
   a sidebar of hundreds of rows — and the row comes into sight when the
   marker was clicked on the map. */
function markPointRows(reveal = false) {
    for (const row of document.querySelectorAll('.point-row[data-point-id]')) {
        const id = Number(row.dataset.pointId);
        const on = id === state.selectedId || id === state.peekedId;
        row.classList.toggle('is-active', on);
        if (on && reveal && id === state.peekedId && row.offsetParent !== null) revealRow(row);
    }
}

/* UP AND DOWN ONLY, never scrollIntoView (08.11.2026). That one scrolls
   every ancestor that can scroll, sideways too — and the sidebar holds its
   two pages side by side on one track, with overflow hidden but still
   scrollable by script. With the lists page showing, a point clicked on
   the map pulled the points page half into view („ich sehe
   quasi beides, die Listen und die Punkte"), the sidebar scrolled 315 px
   sideways while it still said „Listen". So the row's own list scrolls,
   and only vertically: on the lists page the row waits, in view, for the
   moment one switches over. */
function revealRow(row) {
    const box = row.closest('.point-section');
    if (!box) return;
    const r = row.getBoundingClientRect();
    const b = box.getBoundingClientRect();
    if (r.top < b.top) box.scrollTop -= b.top - r.top;
    else if (r.bottom > b.bottom) box.scrollTop += r.bottom - b.bottom;
}

/** Where the desk's tag hangs — the point's spot on the map, or null. */
let peekPlace = null;
function placeDeskPeek() {
    const peek = document.getElementById('point-peek');
    if (!peekPlace || peek.hidden || !peek.classList.contains('is-desk')) return;
    const at = map.latLngToContainerPoint(peekPlace);
    const mapBox = map.getContainer().getBoundingClientRect();
    const host = (peek.offsetParent ?? document.body).getBoundingClientRect();
    const x = mapBox.left - host.left + at.x;
    const y = mapBox.top - host.top + at.y;
    const w = peek.offsetWidth, h = peek.offsetHeight;
    const GAP = 16;   // past the marker's own drawing
    // Below unless it would leave the map; then above.
    const above = at.y + GAP + h > mapBox.height - 8 && at.y - GAP - h > 8;
    peek.classList.toggle('is-above', above);
    const left = Math.max(mapBox.left - host.left + 8, Math.min(x - w / 2, mapBox.right - host.left - w - 8));
    peek.style.left = left + 'px';
    peek.style.top = (above ? y - GAP - h : y + GAP) + 'px';
    // The tip keeps pointing at the point even when the tag is pushed in.
    peek.style.setProperty('--tip-x', Math.max(14, Math.min(w - 14, x - left)) + 'px');
}
map.on('move', placeDeskPeek);
map.on('zoom', placeDeskPeek);
// A picture that does not come leaves the name alone; one that comes makes
// the tag taller, so it is placed again.
document.getElementById('point-peek-image').addEventListener('error', (e) => { e.target.hidden = true; placeDeskPeek(); });
document.getElementById('point-peek-image').addEventListener('load', placeDeskPeek);

document.getElementById('point-peek-open').addEventListener('click', () => {
    const id = state.peekedId;
    peekHide();
    if (id === null) return;
    if (deskCockpit()) location.href = findsAddress(id);
    else selectPoint(id);
});
// A tap into the map walks away from the peek — nothing opens.
map.on('click', peekHide);

/**
 * The number on a collapsed heading: how many of the lists below it are
 * on right now.
 *
 * ONE FUNCTION FOR THREE PLACES — the heading list in the tree, the
 * container row and the heads „Meine Listen" and „Geteilt". It stood
 * written out three times, and when the accent colour came along
 * (11.09.2026: „ist da was Aktives markiert, soll die Badge,
 * also der Rand und die Schrift, in der Akzentfarbe angezeigt werden —
 * dasselbe gilt für die Hauptbereiche meiner Listen und geteilt"), it
 * would otherwise have arrived in two of three rows and not in the third.
 *
 * is-active ONLY FROM ONE TICK ON: „0/5" is the information that nothing
 * under this heading is on right now — it must not look like its
 * opposite.
 */
function choiceBadge(checked, total, title) {
    const badge = document.createElement('span');
    badge.className = 'list-row-selected' + (checked > 0 ? ' is-active' : '');
    badge.textContent = `${checked}/${total}`;
    badge.title = title;
    return badge;
}

/* THE TICK BOX lives in tickbox.js since 28.09.2026 — the sidebar and
   Organisieren draw the same one. switchBranch stays here: it speaks the
   map's settings and redraws its markers. */

/**
 * A heading's box, clicked: the whole branch on — or off, when all of it is
 * on already. HALF ON COUNTS AS NOT YET ON, so the first click completes the
 * branch: whoever reaches for the box of a half-lit branch wants it whole,
 * and the next click empties it.
 */
function switchBranch(chosen, leaves, settingsKey) {
    const on = leaves.some(id => !chosen.has(id));
    for (const id of leaves) {
        if (on) chosen.add(id);
        else chosen.delete(id);
    }
    settings.set(settingsKey, [...chosen]);
    drawMarker();
    drawList();
}

function listRow({ id, name, color, shape, active, editable,
                       depth = 0, hasChildren = false, collapsed = false, chosen = null, leaves = null,
                       folder = null }) {
    const row = document.createElement('div');
    /* is-heading: A LIST WITH SUBLISTS IS A HEADING and can hold no
       points (CLAUDE.md). Until now only the missing shape dot said so —
       an absence one notices only when looking for it. The class carries
       no logic, only the appearance. */
    row.className = 'list-row' + (hasChildren ? ' is-heading' : '') + (folder ? ' is-folder' : '')
        + (active ? ' is-active' : '');
    // The indent via a variable instead of fixed classes: the depth is a
    // number, not an enumeration, and with five levels it would be five
    // classes that all do the same thing with a different value.
    if (depth > 0) row.style.setProperty('--depth', String(depth));

    // The box first — see tickBox. A leaf's box does what a click on the
    // leaf does; a heading's box switches its branch.
    row.append(hasChildren
        ? tickBox(tickOf(state.visible, leaves ?? []), name,
            folder ? t('Alle Listen der Mappe ein- oder ausblenden') : t('Alle Listen darunter ein- oder ausblenden'),
            leaves?.length ? () => switchBranch(state.visible, leaves, 'visibleLists') : null)
        : tickBox(active ? 'on' : 'off', name, t('Auf der Karte ein- oder ausblenden'), () => button.click()));

    // ONE GLYPH COLUMN FOR BOTH: triangle OR symbol, at the same spot.
    // Since headings no longer carry a symbol, the two never occur together
    // — the empty arrow placeholder that used to keep the names in one
    // flush line would now only mean: leaves indent for no reason. The
    // column still carries the indent (--depth in the CSS), the name
    // begins at the same spot in every row of the same level.
    const arrow = document.createElement('button');
    arrow.className = 'list-row-arrow';
    arrow.type = 'button';
    if (hasChildren) {
        arrow.innerHTML = disclosureTriangle();
        arrow.classList.add('is-collapsible');
        arrow.classList.toggle('is-open', !collapsed);
        arrow.title = folder
            ? (collapsed ? t('Listen der Mappe zeigen') : t('Mappe einklappen'))
            : (collapsed ? t('Unterlisten zeigen') : t('Unterlisten einklappen'));
        arrow.setAttribute('aria-expanded', String(!collapsed));
        const toggle = () => {
            if (state.collapsed.has(id)) state.collapsed.delete(id);
            else state.collapsed.add(id);
            settings.set('collapsedLists', [...state.collapsed]);
            drawList();
        };
        arrow.addEventListener('click', (e) => { e.stopPropagation(); toggle(); });
        /* Here the row was already almost entirely clickable, because the
           main button spans the width. „Almost": the edge to its right and
           everything that steps in beside it in future belonged to nobody.
           The same rule as for the container row, so that the bar feels
           the same everywhere. */
        foldOnRowClick(row, toggle);
    } else {
        // The leaf's symbol. It clicks like the name next to it — whoever
        // taps the glyph means the same list; it used to sit in the button
        // and did exactly that, so nothing about it may change.
        arrow.innerHTML = shapePoint(color, shape);
        arrow.tabIndex = -1;
        arrow.setAttribute('aria-hidden', 'true');
        arrow.addEventListener('click', () => button.click());
    }
    row.append(arrow);

    const button = document.createElement('button');
    button.className = 'list-row-main';

    const text = document.createElement('span');
    text.className = 'list-row-name';
    text.textContent = name;
    button.append(text);

    // Collapsed, the leaves are not visible — then the heading says how
    // many of them are chosen. Expanded, every row says it itself.
    if (chosen) {
        button.append(choiceBadge(chosen.checked, chosen.total, folder
            ? t('%d von %d Listen der Mappe sichtbar', chosen.checked, chosen.total)
            : t('%d von %d Unterlisten sichtbar', chosen.checked, chosen.total)));
    }

    // A HEADING CANNOT BE CHOSEN — every leaf is chosen for itself. Its
    // click folds the branch, like the triangle next to it: the name is the
    // bigger target, and two neighboring surfaces that do different things
    // would be a guessing game under the finger.
    button.addEventListener('click', () => {
        if (hasChildren) {
            if (state.collapsed.has(id)) state.collapsed.delete(id);
            else state.collapsed.add(id);
            settings.set('collapsedLists', [...state.collapsed]);
            drawList();
            return;
        }
        if (state.visible.has(id)) {
            state.visible.delete(id);
        } else {
            state.visible.add(id);
        }
        settings.set('visibleLists', [...state.visible]);
        drawMarker();
        drawList();
    });

    row.append(button);

    /* THE PENCIL RIGHT BEHIND THE NAME (16.09.2026: „den Stift
       direkt hinter dem Namen, so wie bei den Freigaben"). The main button
       stops behind the name and a gap stretches to the row's end — the
       same grammar the container row has had since 20.08.2026. The gap is
       not dead: a click anywhere on a leaf row still toggles the list
       (foldOnRowClick below), as it does on the headings.

       NO NUMBER CLOSES THE ROW ANY MORE (24.09.2026: „die
       Anzahl der Punkte ist nicht so wichtig" — the Punkte tab counts what
       is shown, Organisieren counts per list). With it went the empty
       pencil cell that stood where there is nothing to edit: it existed
       only to keep the numbers in one column, and now it would only cut
       long names a pencil's width short. */
    if (editable) {
        const pencil = document.createElement('button');
        pencil.className = 'symbol-button small';
        pencil.title = folder ? t('Mappe bearbeiten') : t('Liste bearbeiten');
        pencil.setAttribute('aria-label', folder ? t('Mappe %s bearbeiten', name) : t('Liste %s bearbeiten', name));
        // The library's pencil, not the text glyph: the shared rows carry
        // icon('pencil'), and two pencils in one column read as two
        // different tools (16.09.2026).
        pencil.append(icon('pencil'));
        pencil.addEventListener('click', (e) => {
            e.stopPropagation();
            if (folder) openFolderWindow(folder); else editList(id);
        });
        row.append(pencil);
    }
    const gap = document.createElement('span');
    gap.className = 'list-row-gap';
    row.append(gap);
    if (!hasChildren) foldOnRowClick(row, () => button.click());
    return row;
}

/**
 * WHOSE IT IS, AS A LITTLE PERSON ALONE (23.09.2026: „das nimmt
 * zu viel Platz weg — nur das Symbol, und beim Klick oder Überfahren den
 * Namen"). The figure stays — it has said „somebody" since 20.08.2026 —,
 * the name moved into its tooltip and, for a thumb that cannot hover,
 * into a small window on a tap. The colour tells the two apart at a
 * glance: MINE in the text colour, SOMEBODY ELSE'S in a tone drawn from
 * the page's own ground — never the accent, which is the user's dress
 * and says „chosen".
 *
 * @param {string} hint  „meine" or the owner's short name, as the rows
 *        have always received it
 */
function ownerMark(hint) {
    const mine = hint === t('meine');
    const label = mine ? t('Gehört dir') : t('Gehört %s', hint);
    const mark = document.createElement('span');
    mark.className = 'list-row-owner' + (mine ? ' is-mine' : '');
    mark.setAttribute('role', 'button');
    mark.tabIndex = 0;
    mark.title = label;
    mark.setAttribute('aria-label', label);
    mark.append(icon('person'));
    // Three seconds, then it goes by itself; a second tap on the little
    // person closes it sooner (25.09.2026 — see infoWindow).
    const show = (e) => {
        e.preventDefault();
        e.stopPropagation();
        infoWindow({ anchor: mark, rows: [{ text: label }], closeAfter: 3000 });
    };
    mark.addEventListener('click', show);
    mark.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') show(e); });
    return mark;
}

// ---------------------------------------------------------------------------
// Excursions: their own section in the sidebar
// ---------------------------------------------------------------------------

/** How many excursion rows stand there before one asks for more. */
const EXCURSION_ROWS_FIRST = 10;

/** And how many each further request adds. */
const EXCURSION_ROWS_MORE = 15;

let excursionRowsShown = EXCURSION_ROWS_FIRST;

/**
 * The "Exkursionen" section (26.08.2026).
 *
 * ITS OWN RENDERER, like drawShared() beside it and for the reason
 * written there: its clicks write into state.visibleExcursions, not into
 * the selection of lists. A switch inside one painter would make every
 * later change be measured against three worlds at once.
 *
 * „ALLE EXKURSIONEN" STANDS ON TOP, and it is not the same as ticking
 * every row. It means EVERY excursion — including the ones still folded
 * away under „weitere laden", and the ones that will exist next season.
 * That is why it is its own key and not a convenience button.
 *
 * TEN ROWS, THEN MORE ON REQUEST („alle Exkursionen aufführen
 * würde irgendwann zu ewig langen Listen führen"). The names all came
 * with the document — a name and a date weigh nothing — so „weitere
 * laden" costs no request, it only unfolds what is already here. WHEN an
 * excursion took place is a question for the time filter, not for this
 * list.
 */
function drawExcursions(container) {
    if (excursionStart.length === 0) return;

    const collapsed = state.sectionsCollapsed.has('excursions');
    const chosen = state.visibleExcursions.has('all')
        ? excursionStart.length
        : excursionStart.filter(e => state.visibleExcursions.has(e.id)).length;

    container.append(sectionTitle({
        name: t('Exkursionen'), key: 'excursions', collapsed,
        choice: collapsed ? { checked: chosen, total: excursionStart.length } : null,
    }));
    if (collapsed) return;

    const row = ({ key, name, hint, active }) => {
        const el = document.createElement('div');
        el.className = 'list-row is-shared' + (active ? ' is-active' : '');

        // The box does what a click on the row does (tickBox).
        el.append(tickBox(active ? 'on' : 'off', name, t('Auf der Karte ein- oder ausblenden'), () => toggle()));

        const arrow = document.createElement('button');
        arrow.className = 'list-row-arrow';
        arrow.type = 'button';
        arrow.innerHTML = shapePoint(null, 'circle');
        arrow.tabIndex = -1;
        arrow.setAttribute('aria-hidden', 'true');
        el.append(arrow);

        const button = document.createElement('button');
        button.className = 'list-row-main';

        const text = document.createElement('span');
        text.className = 'list-row-name';
        text.textContent = name;
        button.append(text);

        const who = hint ? ownerMark(hint) : null;

        /* „ALLE" AND A SINGLE CHOICE EXCLUDE EACH OTHER
           (27.08.2026: „wenn ich dann eine einzelne anklicke, soll Alle
           automatisch abgewählt werden"). Both directions, for the same
           reason: whoever picks the one entry means the whole, whoever
           then picks a single one means exactly that one — keeping both
           would make the single click invisible, since „Alle" already
           shows everything. */
        const toggle = () => {
            if (key === 'all') {
                const on = !state.visibleExcursions.has('all');
                state.visibleExcursions.clear();
                if (on) state.visibleExcursions.add('all');
            } else if (state.visibleExcursions.has('all')) {
                state.visibleExcursions.clear();
                state.visibleExcursions.add(key);
            } else if (state.visibleExcursions.has(key)) {
                state.visibleExcursions.delete(key);
            } else {
                state.visibleExcursions.add(key);
            }
            settings.set('visibleExcursions', [...state.visibleExcursions]);
            drawMarker();
            drawList();
        };
        button.addEventListener('click', toggle);
        arrow.addEventListener('click', () => button.click());

        // The same order as every other row: name, the gap, the owner at
        // the row's end — in one line with the owners of the shared rows
        // above, because it closes every row that has one (sharedRow).
        const gap = document.createElement('span');
        gap.className = 'list-row-gap';
        el.append(button, gap);
        if (who) el.append(who);
        foldOnRowClick(el, toggle);
        return el;
    };

    container.append(row({
        key: 'all', name: t('Alle Exkursionen'),
        active: state.visibleExcursions.has('all'),
    }));

    // The most recent first — excursions_mine() already orders that way,
    // and the row limit only makes sense on that order.
    const shown = excursionStart.slice(0, excursionRowsShown);
    for (const e of shown) {
        container.append(row({
            key: e.id,
            name: e.name,
            // Whose it is, but only where it is not one's own: „von Anna"
            // on a co-led excursion answers the question the row raises.
            // The leader at the row's end — „meine" for an own excursion,
            // like the shares (16.09.2026); the full name
            // wrapped into two lines and squeezed the excursion's name.
            hint: e.is_main ? t('meine') : (e.leader ? shortNameOf(e.leader) : null),
            // NOT lit while „Alle" is on (27.08.2026): the top
            // entry is a choice of its own, and ten rows lighting up with
            // it read as ten choices one would want to undo one by one.
            active: state.visibleExcursions.has(e.id),
        }));
    }

    const left = excursionStart.length - shown.length;
    if (left > 0) {
        const more = document.createElement('button');
        more.type = 'button';
        more.className = 'button subtle small sidebar-more';
        const next = Math.min(left, EXCURSION_ROWS_MORE);
        more.textContent = left <= EXCURSION_ROWS_MORE
            ? t('Die restlichen %d zeigen', left)
            : t('Weitere %d zeigen', next);
        more.addEventListener('click', () => {
            excursionRowsShown += EXCURSION_ROWS_MORE;
            drawList();
        });
        container.append(more);
    }
}

// ---------------------------------------------------------------------------
// Shared: sidebar, reading window and sync
// ---------------------------------------------------------------------------

/**
 * The "Geteilt" section below the own lists.
 *
 * ROWS OF ITS OWN, NOT listRow(): its clicks write into
 * state.visible and state.collapsed — the selection of the OWN
 * lists. A switch inside it ("is this foreign?") would make every future
 * change be measured against both worlds at once; two small renderers with
 * the same classes are cheaper than one with two memories.
 */
/**
 * The friends who show me finds, each with how many lie on this device —
 * the rows „Von <Name>" under „Geteilt". Counted from the points, so the
 * row says what the map can really show; until the first sync of this
 * visit the document's count stands in for a friend whose finds are not
 * fetched yet.
 */
function hintGivers() {
    const byId = new Map();
    for (const p of state.sharedPoints.values()) {
        if (p.hint_from == null) continue;
        const g = byId.get(p.hint_from) ?? { id: p.hint_from, name: p.hint_from_name ?? p.owner, count: 0 };
        g.count++;
        byId.set(p.hint_from, g);
    }
    if (!hintsSynced) {
        for (const g of hintStart?.givers ?? []) {
            if (!byId.has(g.id)) byId.set(g.id, { ...g });
        }
    }
    return [...byId.values()].filter(g => g.count > 0)
        .sort((a, b) => a.name.localeCompare(b.name, 'de'));
}

/** The containers the section draws — not my own list shared only for looking. */
function sharedShown() {
    const me = start.user?.id;
    return sharedStart.filter(c => c.list_id == null || c.owner_id !== me
        || (c.shares ?? []).some(f => f.joint === true));
}

function drawShared(container) {
    const givers = hintGivers();
    if (sharedShown().length === 0 && givers.length === 0) return;

    // For the number on the collapsed heading: only leaves are choosable,
    // so the total counts exactly those. Chosen is what stands in
    // sichtbarGeteilt — only leaves land there anyway.
    let leaves = 0;
    for (const c of sharedShown()) {
        for (const f of c.shares ?? []) {
            const parent = new Set((f.lists ?? []).map(l => l.parent_id).filter(e => e != null));
            leaves += (f.lists ?? []).filter(l => !parent.has(l.id)).length;
        }
    }

    const collapsed = state.sectionsCollapsed.has('shared');
    // Counted only while folded — expanded, every row carries its own
    // bubble and the same statement twice would be noise.
    const waiting = collapsed
        ? sharedShown().filter((c) => chat.unreadFor(c.id) > 0).length
        : 0;
    // Every directly shared find counts along: each is one more thing to pick.
    const hintPoints = [...state.sharedPoints.values()].filter(p => p.hint_from != null);
    const hintsOn = hintPoints.filter(p => !hintHidden(p)).length;
    container.append(sectionTitle({
        name: t('Geteilt'), key: 'shared', collapsed,
        choice: collapsed ? { checked: state.visibleShared.size + hintsOn, total: leaves + hintPoints.length } : null,
        unread: waiting,
    }));
    if (collapsed) return;

    /* SORTED BY WHAT IS SHOWN, and sorted HERE and not only on the server.
       The server orders by the own name too (container_mine), but that
       happens once when the document is built — whoever renames a
       container in order to move it would have to reload to see it move,
       and then the renaming would have shifted the label and not the
       place. That is the one thing it was for (20.08.2026). */
    /* LISTS, NOT BOXES (08./09.10.2026). A list share (0160)
       is drawn by whom it comes from — „Von Finn" and his lists below —,
       or under „Gemeinsame Listen" when I work in it (owner included:
       a project list of several people, its points not all one person's,
       its chat the group's, and it keeps its place when its administrator
       changes). My own list shared with others for looking stays where my
       lists are and is not drawn here at all. Old boxes keep their rows
       below until the administration converts them. */
    const me = start.user?.id;
    const listShares = sharedStart.filter(c => c.list_id != null);
    const jointShares = listShares.filter(c => (c.shares ?? []).some(f => f.joint === true));
    const fromOthers = listShares.filter(c => c.owner_id !== me && !jointShares.includes(c));

    const byOwner = new Map();
    for (const c of fromOthers) {
        if (!byOwner.has(c.owner_id)) byOwner.set(c.owner_id, { name: c.owner, shares: [], points: [] });
        byOwner.get(c.owner_id).shares.push(c);
    }
    /* THE FINDS A FRIEND SHARES DIRECTLY stand under his „Von <Name>" too,
       after his lists, each by its own name (09.10.2026: „da
       brauchen wir sicherlich keine eigene Kategorie … direkt von Finn
       Boletus edulis"). Until then they gathered under a section of their
       own, „Direkt geteilt", one row per friend. */
    for (const g of givers) {
        if (!byOwner.has(g.id)) byOwner.set(g.id, { name: g.name, shares: [], points: [] });
        byOwner.get(g.id).points = hintPoints
            .filter(p => p.hint_from === g.id)
            .sort((a, b) => (a.title || '').localeCompare(b.title || '', 'de'));
    }
    const leavesOf = (cs) => {
        const rows = cs.flatMap(c => (c.shares ?? []).flatMap(f => f.lists ?? []));
        const parents = new Set(rows.map(l => l.parent_id).filter(x => x != null));
        return rows.filter(l => !parents.has(l.id)).map(l => l.id);
    };
    for (const [ownerId, group] of [...byOwner].sort((a, b) => a[1].name.localeCompare(b[1].name, 'de'))) {
        const key = `o${ownerId}`;
        const folded = state.collapsedShared.has(key);
        const leaves = leavesOf(group.shares);
        const points = group.points ?? [];
        // The box and the count take lists and single finds together.
        const on = leaves.filter(id => state.visibleShared.has(id)).length
            + points.filter(p => !hintHidden(p)).length;
        const total = leaves.length + points.length;
        container.append(sharedRow({
            key, name: t('Von %s', shortNameOf(group.name)),
            depth: 0, hasChildren: true, collapsed: folded,
            active: folded && on > 0,
            chosen: folded && total > 0 ? { checked: on, total } : null,
            leaves,
            tick: {
                state: on === 0 ? 'off' : on === total ? 'on' : 'some',
                toggle: total === 0 ? null : () => {
                    const allOn = on === total;
                    for (const id of leaves) {
                        if (allOn) state.visibleShared.delete(id); else state.visibleShared.add(id);
                    }
                    for (const p of points) {
                        if (allOn) state.hiddenHintPoints.add(p.id); else state.hiddenHintPoints.delete(p.id);
                    }
                    state.hiddenHints.delete(ownerId);
                    settings.set('visibleShared', [...state.visibleShared]);
                    settings.set('hiddenHintPoints', [...state.hiddenHintPoints]);
                    settings.set('hiddenHints', [...state.hiddenHints]);
                    drawMarker();
                    drawList();
                },
            },
            /* The speech bubble is our friend chat (08.10.2026),
               and it STAYS although the header's bubble reaches the same
               room (asked 10.10.2026): its dot lights up right where Finn's
               lists are, so a word about a share is seen on the spot, not
               only on the collective button. */
            friendChat: (start.friends ?? []).find(f => f.id === ownerId) ?? null,
        }));
        if (folded) continue;
        for (const c of group.shares) {
            // A list shared with a working group has a chat of its own.
            for (const f of c.shares ?? []) {
                drawShareLists(container, c, f, 1, { rootHint: false, chatFor: c.chat_member ? c : null });
            }
        }
        /* THE SINGLE FINDS UNDER A SMALL HEADING OF THEIR OWN
           (09.10.2026: „ein kleines Zwischenlabel … direkt geteilt. Das,
           was da drunter ist, ist nicht einklappbar, sondern immer voll
           sichtbar"). The lists above can be ticked as branches; these are
           single points, and the label says so once for all of them. */
        if (points.length > 0) {
            const label = document.createElement('div');
            label.className = 'list-subhead';
            label.style.setProperty('--depth', '1');
            label.textContent = t('Direkt geteilt');
            container.append(label);
            for (const p of points) container.append(hintPointRow(p, points));
        }
    }

    if (jointShares.length > 0) {
        const key = 'joint';
        const folded = state.collapsedShared.has(key);
        const leaves = leavesOf(jointShares);
        const chosen = leaves.filter(id => state.visibleShared.has(id)).length;
        container.append(sharedRow({
            key, name: t('Gemeinsame Listen'),
            depth: 0, hasChildren: true, collapsed: folded,
            active: folded && chosen > 0,
            chosen: folded && leaves.length > 0 ? { checked: chosen, total: leaves.length } : null,
            leaves,
        }));
        if (!folded) {
            const sorted = [...jointShares].sort((a, b) =>
                (a.shares?.[0]?.lists?.[0]?.name ?? '').localeCompare(b.shares?.[0]?.lists?.[0]?.name ?? '', 'de'));
            for (const c of sorted) {
                for (const f of c.shares ?? []) {
                    drawShareLists(container, c, f, 1, {
                        rootHint: c.owner_id === me ? t('meine') : shortNameOf(c.owner),
                        chatFor: c,
                    });
                }
            }
        }
    }

    const containers = sharedStart.filter(c => c.list_id == null)
        .sort((a, b) => containerName(a).localeCompare(containerName(b), 'de'));

    for (const c of containers) {
        const key = `c${c.id}`;
        const collapsed = state.collapsedShared.has(key);

        /* AN OLD CONTAINER, without a list of its own — from before „Listen
           teilen" (0160), when one share held several people's lists. Every
           share since hangs at one list (list_id), so these rows only stand
           where an old one was never converted. Its window — own name,
           members, the door out, hanging lists in — went on 12.11.2026
           („Entfern den alten Code der Container-Info"); the row
           keeps its owner at the end and its chat. */
        /* FOLDED SHUT, THE CONTAINER LIGHTS UP as soon as something below
           it is picked — exactly like a heading list of one's own
           (20.08.2026). Otherwise a folded container does not
           show that it is putting points on the map right now, and one
           looks for them elsewhere.

           LEAVES AND NOT EVERY ROW: only leaves can be picked, a heading
           inside the container merely folds. Counting it in would say
           „2/5" where only three rows can be picked at all. */
        const rows = (c.shares ?? []).flatMap(f => f.lists ?? []);
        const parents = new Set(rows.map(l => l.parent_id).filter(x => x != null));
        const leaves = rows.filter(l => !parents.has(l.id));
        const chosen = leaves.filter(l => state.visibleShared.has(l.id)).length;

        container.append(sharedRow({
            key, name: containerName(c),
            depth: 0, hasChildren: true, collapsed,
            active: collapsed && chosen > 0,
            chosen: collapsed && leaves.length > 0 ? { checked: chosen, total: leaves.length } : null,
            // Its box switches every leaf of every share in it.
            leaves: leaves.map(l => l.id),
            info: c,
            // WHOSE SHARE (16.09.2026: „es fehlt bei den Freigaben
            // noch, wem sie gehört"): the owner at the row's end, „meine"
            // for one's own — the same word the hung-in lists use.
            hint: c.owner_id === start.user?.id ? t('meine') : shortNameOf(c.owner),
        }));
        if (collapsed) continue;

        for (const f of c.shares ?? []) drawShareLists(container, c, f, 1);
    }

}

/**
 * One share's list tree under „Geteilt" — the rows of a box's hung-in
 * branch, and since the list shares (migration 0160) of a list under „Von
 * <Name>" or „Gemeinsame Listen". ONE drawing for the three places; what
 * differs is the depth it starts at, what the top row says at its end and
 * whether the top row carries a chat.
 *
 * @param {object} c  the container
 * @param {object} f  the share (one list's branch)
 * @param {number} baseDepth  the depth of the top row
 * @param {object} [o]
 * @param {?string|false} [o.rootHint]  the top row's end; undefined = the
 *        sharer (or „meine"), false = none
 * @param {?object} [o.chatFor]  a container whose chat the top row carries
 */
function drawShareLists(container, c, f, baseDepth, o = {}) {
    const children = new Map();
    for (const l of f.lists ?? []) {
        const e = l.parent_id ?? null;
        if (!children.has(e)) children.set(e, []);
        children.get(e).push(l);
    }

    /* WHAT IS ON UNDER A COLLAPSED SHARED HEADING
       (11.09.2026: „und natürlich bei den Freigaben
       überall, und darauf hinzuweisen, dass sich darunter eine
       Markierung versteckt"). Until then a collapsed shared
       heading list said nothing at all: its own tick is none —
       only leaves are chosen —, and the number existed only on
       the container above. Whoever had ticked a sublist and then
       collapsed it saw a point on the map and found nowhere where
       it came from. The same information as for one's own lists
       (branchLeaves), only on the tree of this share: what is
       counted are LEAVES, because only they carry points and only
       they can be ticked. The heading's box switches the same
       leaves. */
    const sharedLeaves = (top) => {
        const leaves = [];
        (function walk(l) {
            const kids = children.get(l.id) ?? [];
            if (kids.length === 0) {
                leaves.push(l.id);
                return;
            }
            for (const k of kids) walk(k);
        })(top);
        return leaves;
    };

    (function level(parentId, depth) {
        for (const l of children.get(parentId) ?? []) {
            const hasChildren = (children.get(l.id) ?? []).length > 0;
            const lCollapsed = state.collapsedShared.has(l.id);
            /* COLOUR AND SHAPE OUT OF sharedLists, not out of the
               share. Both hold the same list, but only the map
               carries the override for this device — reading from
               the share here meant the pencil changed the stored
               value and the row went on showing the owner's.
               Measured before it was believed. */
            const look = sharedLists.get(l.id) ?? l;
            const leaves = hasChildren ? sharedLeaves(l) : null;
            // Collapsed, the heading says what is on below it —
            // expanded, the rows say it themselves, and the same
            // statement twice would be noise.
            const choiceBelow = hasChildren && lCollapsed
                ? { checked: leaves.filter(k => state.visibleShared.has(k)).length, total: leaves.length }
                : null;
            container.append(sharedRow({
                key: l.id, name: l.name,
                color: look.color, shape: look.symbol,
                depth, hasChildren, collapsed: lCollapsed, leaves,
                selectable: !hasChildren,
                // A heading lights up when something below it
                // is on — as with one's own lists.
                active: hasChildren
                    ? (choiceBelow?.checked ?? 0) > 0
                    : state.visibleShared.has(l.id),
                chosen: choiceBelow,
                // Whose branch this is stands on its topmost row —
                // deeper down it would only repeat itself.
                /* THE OWN NAME NEXT TO THE OWN LIST SAYS NOTHING.
                   In a container the name answers "whose branch is
                   this?" — and for one's own the answer is not
                   "Anna" but "mine". The name would be the
                   one entry in the column that tells the reader
                   something he already knows
                   (20.08.2026). */
                hint: parentId !== null || o.rootHint === false ? null
                    : (o.rootHint !== undefined ? o.rootHint
                        : (f.user_id === start.user?.id
                            ? t('meine')
                            : shortNameOf(f.display_name))),
                // The list's own chat, on its top row (list shares).
                chatFor: parentId === null ? (o.chatFor ?? null) : null,
                /* THE SHARE THIS ROW STANDS IN — the same list can
                   hang in two shares, and sharedLists (keyed by list
                   id) knows only the last one it was filled from.
                   The look window names THIS share and its rights. */
                share: { container: c, sharing: f },
            }));
            if (hasChildren && !lCollapsed) level(l.id, depth + 1);
        }
    })(null, baseDepth);
}

/**
 * A find a friend shares directly, as a row under his „Von <Name>" — its
 * own name, the look of its own list, its own box (09.10.2026). The name
 * opens the find; the box shows or hides it on the map.
 */
function hintPointRow(point, siblings) {
    const on = !hintHidden(point);
    const flip = () => {
        /* A FRIEND HIDDEN AS A WHOLE (before 09.10.2026) becomes single
           finds: his switch goes, and the ones not just ticked stay hidden. */
        if (state.hiddenHints.has(point.hint_from)) {
            state.hiddenHints.delete(point.hint_from);
            for (const p of siblings) state.hiddenHintPoints.add(p.id);
            settings.set('hiddenHints', [...state.hiddenHints]);
        }
        if (state.hiddenHintPoints.has(point.id)) state.hiddenHintPoints.delete(point.id);
        else state.hiddenHintPoints.add(point.id);
        settings.set('hiddenHintPoints', [...state.hiddenHintPoints]);
        drawMarker();
        drawList();
    };
    const name = point.title || t('Ohne Titel');

    const row = document.createElement('div');
    // is-hint: A SINGLE FIND, NOT A LIST (09.10.2026: „was mir
    // Finn direkt teilt, muss markiert werden") — upright where the lists
    // are italic, under the small heading „Direkt geteilt".
    row.className = 'list-row is-shared is-hint' + (on ? ' is-active' : '');
    row.style.setProperty('--depth', '1');
    row.append(tickBox(on ? 'on' : 'off', name, t('Auf der Karte ein- oder ausblenden'), flip));

    // The find's own look, as its marker wears it.
    const arrow = document.createElement('button');
    arrow.type = 'button';
    arrow.className = 'list-row-arrow';
    arrow.tabIndex = -1;
    arrow.setAttribute('aria-hidden', 'true');
    arrow.innerHTML = shapePoint(pointColor(point), pointShape(point));
    row.append(arrow);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'list-row-main';
    button.title = t('%s teilt diesen Punkt direkt mit dir.', point.hint_from_name ?? point.owner ?? '');
    const text = document.createElement('span');
    text.className = 'list-row-name';
    text.textContent = name;
    button.append(text);
    /* THE WHOLE ROW TICKS, as on every list row (09.10.2026:
       „kann man nicht an- und abhaken, wenn man in der Zeile klickt, nur
       wenn man auf den Haken klickt"). The find opens from its marker. */
    button.addEventListener('click', flip);
    row.append(button);

    /* TWO DEEDS AT THE FIND ITSELF (09.10.2026): the way there —
       „Richtung zeigen", the arrow and the distance from where one stands,
       exactly as the button in the point window's „Karte" tab (he meant
       „navigieren zu", not „auf der Karte zeigen") — and „lösch diese
       direkte Teilung, um aufzuräumen". The door, not the bucket: the find
       goes on at its holder's. */
    const way = document.createElement('button');
    way.type = 'button';
    way.className = 'symbol-button small';
    way.title = t('Richtung zeigen');
    way.setAttribute('aria-label', t('Richtung zu „%s" zeigen', name));
    way.append(icon('arrow-right'));
    way.addEventListener('click', (e) => {
        e.stopPropagation();
        direction.start({ id: point.id, lat: point.lat, lon: point.lon });
        if (!locationActive) {
            askForCompass();
            locationOn();
        }
        // On the phone the sidebar lies over the map the arrow is drawn on.
        if (isPhone()) setSidebar(true);
    });
    row.append(way);

    const gap = document.createElement('span');
    gap.className = 'list-row-gap';
    row.append(gap);

    const leave = document.createElement('button');
    leave.type = 'button';
    leave.className = 'symbol-button small delete';
    leave.title = t('Direkte Teilung entfernen');
    leave.setAttribute('aria-label', t('Direkte Teilung von „%s" entfernen', name));
    leave.append(icon('sign-out'));
    leave.addEventListener('click', (e) => { e.stopPropagation(); hintLeave(point); });
    row.append(leave);
    foldOnRowClick(row, flip);
    return row;
}

/**
 * A row in the Geteilt section — the same classes as listRow().
 *
 * @param {?object} info  set only on the container row: the container
 *        object (owner, filterText) for the info window, which its pencil
 *        opens, and for the chat.
 * @param {?number[]} leaves  on a heading: the leaves its box switches
 */
/**
 * The whole row of a heading row folds — except where there are buttons.
 *
 * WHAT FOR (09.09.2026, first for one's own heading lists,
 * then: „bei den Containern kannst Du das natürlich auch machen. Das wär
 * in der Sidebar überall das gleiche Verhalten"). On one's own heading
 * list the main button spans the width, and so everything already
 * folded. The CONTAINER ROW is built differently: arrow, a short name,
 * „anpassen", then a gap, the number, the chat — and the gap is a plain
 * span. Measured, that was 276 of the 441 px, so 63 % of the row, that
 * reacted to nothing.
 *
 * ON THE ROW AND NOT ON THE GAP, so that the next piece somebody inserts
 * there joins in by itself as well.
 *
 * BUTTONS KEEP THEIR WORK: whoever lands in a button is let through
 * here. At the same time that protects against folding twice — the main
 * button of one's own heading list folds by itself, its click rises up
 * here, and without this line it would have cancelled itself at once.
 */
function foldOnRowClick(row, toggle) {
    row.addEventListener('click', (e) => {
        if (e.target.closest('button, a')) return;
        toggle();
    });
}

function sharedRow({ key, name, color, shape, active = false, depth = 0, chosen = null,
                        hasChildren = false, collapsed = false, selectable = false, hint = null,
                        info = null, share = null, leaves = null, chatFor = null, friendChat = null,
                        tick = null }) {
    const row = document.createElement('div');
    /* is-shared: THE ROW SAYS ITSELF THAT IT IS FOREIGN. Own and shared
       lists stand below one another in the same sidebar, and up to here
       only the heading above told them apart — whoever reads further down
       no longer sees it. The class carries no logic, only the appearance;
       the two painters stay separate (see above). */
    /* THE CONTAINER ROW IN ITALICS TOO. I had exempted it — it is not a
       heading list but a vessel. The maintainer decided otherwise (20.08.2026),
       and seen from the sidebar that holds: what counts there is not what
       a row is in the database but what it does while being read. A
       triangle in front means "it continues here, nothing lies here" —
       and that is as true of the container as of a list with sublists. */
    row.className = 'list-row is-shared'
        + (hasChildren ? ' is-heading' : '')
        + (active ? ' is-active' : '');
    if (depth > 0) row.style.setProperty('--depth', String(depth));

    // The box first, as on the own rows (tickBox).
    // A row may bring its own box („Von <Name>“ switches lists and finds).
    row.append(tick ? tickBox(tick.state, name, t('Alles darunter ein- oder ausblenden'), tick.toggle) : hasChildren
        ? tickBox(tickOf(state.visibleShared, leaves ?? []), name, t('Alle Listen darunter ein- oder ausblenden'),
            leaves?.length ? () => switchBranch(state.visibleShared, leaves, 'visibleShared') : null)
        : tickBox(active ? 'on' : 'off', name, t('Auf der Karte ein- oder ausblenden'),
            selectable ? () => button.click() : null));

    const toggle = () => {
        if (state.collapsedShared.has(key)) state.collapsedShared.delete(key);
        else state.collapsedShared.add(key);
        settings.set('collapsedShared', [...state.collapsedShared]);
        drawList();
    };

    const arrow = document.createElement('button');
    arrow.className = 'list-row-arrow';
    arrow.type = 'button';
    if (hasChildren) {
        arrow.innerHTML = disclosureTriangle();
        arrow.classList.add('is-collapsible');
        arrow.classList.toggle('is-open', !collapsed);
        arrow.title = collapsed ? t('Aufklappen') : t('Einklappen');
        arrow.setAttribute('aria-expanded', String(!collapsed));
        arrow.addEventListener('click', (e) => { e.stopPropagation(); toggle(); });
        foldOnRowClick(row, toggle);
    } else {
        arrow.innerHTML = shapePoint(color, shape);
        arrow.tabIndex = -1;
        arrow.setAttribute('aria-hidden', 'true');
        arrow.addEventListener('click', () => button.click());
    }
    row.append(arrow);

    const button = document.createElement('button');
    button.className = 'list-row-main';

    const text = document.createElement('span');
    text.className = 'list-row-name';
    text.textContent = name;
    button.append(text);

    // Collapsed, the lists below are not visible — then the container says
    // how many of them are chosen. The same as on the own branch headings
    // (listRow), and for the same reason: expanded, every row says it
    // itself, and the same statement twice over would be noise.
    if (chosen) {
        button.append(choiceBadge(chosen.checked, chosen.total,
            t('%d von %d Listen sichtbar', chosen.checked, chosen.total)));
    }

    const who = hint ? ownerMark(hint) : null;

    button.addEventListener('click', () => {
        if (hasChildren) { toggle(); return; }
        if (!selectable) return;
        if (state.visibleShared.has(key)) state.visibleShared.delete(key);
        else state.visibleShared.add(key);
        settings.set('visibleShared', [...state.visibleShared]);
        drawMarker();
        drawList();
    });
    row.append(button);

    /* ONE ORDER FOR EVERY ROW (16.09.2026): name, then the
       pencil — the look-pencil on a foreign list —, then the stretching
       gap, then what belongs to the far end:
       the chat bubble on a container, and last the owner. A button inside
       a button is not valid markup, so the main button ends behind the
       name and everything else stands beside it.

       A PENCIL ON A FOREIGN LIST edits nothing of the owner's. It opens
       how the list looks HERE — colour, shape — and says in one line what
       the share allows me. Only on leaves that really belong to somebody
       else: my own lists hung into a container are edited where every
       own list is edited. */
    const foreignList = !info && !hasChildren ? sharedLists.get(key) : null;
    if (foreignList && foreignList.user_id === start.user?.id && share?.sharing?.joint === true) {
        /* MY OWN JOINT LIST STANDS ONLY HERE (22.09.2026) — so here is
           its pencil, the real one into the list window, the same tool
           the own rows above carry. */
        const pencil = document.createElement('button');
        pencil.className = 'symbol-button small';
        pencil.title = t('Liste bearbeiten');
        pencil.setAttribute('aria-label', t('Liste %s bearbeiten', name));
        pencil.append(icon('pencil'));
        pencil.addEventListener('click', (e) => {
            e.stopPropagation();
            editList(key);
        });
        row.append(pencil);
    } else if (foreignList && foreignList.user_id !== start.user?.id) {
        row.append(sharedLookButton(foreignList, share));
    }

    const gap = document.createElement('span');
    gap.className = 'list-row-gap';
    row.append(gap);
    /* THE CHAT, THEN THE OWNER (16.09.2026: „links daneben die
       Sprechblase"). The chat on a container row and nowhere else: it is
       the container that decides who reads along, so it is the container
       row that carries the conversation.

       REAL COLUMNS (23.09.2026: „richtige Spalten, alle
       Männchen untereinander") — and since the count went (24.09.2026)
       they hold WITHOUT EMPTY CELLS. The owner closes every row that has
       one, flush with the row's end, so the little people stand in one
       line by themselves; a bubble stands only on a container row, and
       that row always has an owner right after it. The empty chat, owner
       and pencil cells that held the columns open existed for the count
       at the far end; with nothing out there to align, they would only
       cut the names short — 88 px on an excursion row on the phone. */
    if (info) row.append(chatButton(info));
    // A list share's own chat on its top row, or the friend chat on „Von X".
    else if (chatFor) row.append(chatButton(chatFor));
    else if (friendChat) row.append(friendChatButton(friendChat));
    if (who) row.append(who);
    if (!hasChildren && selectable) foldOnRowClick(row, () => button.click());
    return row;
}

/** The pencil on a foreign list: how it looks here, and what I may do. */
function sharedLookButton(list, share = null) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'symbol-button small';
    button.title = t('Farbe und Form von „%s"', list.name);
    button.setAttribute('aria-label', t('Geteilte Liste %s anpassen', list.name));
    button.append(icon('pencil'));
    button.addEventListener('click', (e) => { e.stopPropagation(); openSharedLook(list, share); });
    return button;
}

const sharedLookWindow = document.getElementById('shared-look-window');

function openSharedLook(list, share = null) {
    /* THE HEADING NAMES THE SHARE AND ITS OWNER (16.09.2026):
       „Bens Beobachtungen — Freigabe „Pilzfreunde Nord" von Ben" — the
       list alone would not say where it comes from, and the same list
       can hang in two shares. „Von" is the SHARE's owner — the list's
       owner already stands in the row beside the count. */
    const c = share?.container ?? list.container;
    const sharing = share?.sharing ?? list.sharing;
    /* A LIST SHARE (0160) is its list and its owner — no box name to tell
       — and says its rights EXACTLY, the ticks one by one
       (08.10.2026: „nicht abgeholt werden mit allgemeinen Phrasen"). */
    const isListShare = c?.list_id != null;
    document.getElementById('shared-look-heading').textContent = !c ? list.name
        : isListShare
            ? t('„%s" von %s', list.name, shortNameOf(c.owner))
            : c.owner_id === start.user?.id
                ? t('%s — meine Freigabe „%s"', list.name, containerName(c))
                : t('%s — Freigabe „%s" von %s', list.name, containerName(c), shortNameOf(c.owner));
    document.getElementById('shared-look-rights').textContent = isListShare
        ? listShareRightsReadable(c, sharing?.rights)
        : sharing?.joint === true
            ? t('Gemeinsame Liste: alle bearbeiten alles, und jeder setzt Punkte hinein.')
            : rightsReadable(sharing?.rights);

    /* THE DOOR (08.10.2026): I step out, the list goes on at its
       owner's. Whatever I set into it goes home with me. */
    const leave = document.getElementById('look-window-leave');
    leave.hidden = !isListShare || c.owner_id === start.user?.id;
    leave.onclick = async () => {
        const yes = await confirm({
            title: t('„%s" nicht mehr sehen?', list.name),
            text: t('Die Liste verschwindet bei dir. Bei %s läuft sie weiter. Punkte, die du hineingesetzt hast, bekommst du in eine eigene Liste zurück. Wieder sehen kannst du sie nur, wenn %s sie dir noch einmal teilt.', c.owner, c.owner),
            button: t('Nicht mehr sehen'),
            danger: true,
        });
        if (!yes) return;
        try {
            await api.remove('containers', c.id);
        } catch (error) {
            status(t('Das ging nicht: %s', error?.message || t('unbekannter Fehler')), true);
            return;
        }
        sharedLookWindow.close();
        location.reload();
    };

    const store = () => setSharedLook(list.id, {
        color:  readChoice('shared-look-colors'),
        symbol: readChoice('shared-look-shapes'),
    });

    buildChoice('shared-look-colors', COLOR_ROW.map(c => [c, c, null]),
        list.color, 'color', store);
    buildChoice('shared-look-shapes', SHAPE_ROW, list.symbol, 'symbol', store);

    document.getElementById('shared-look-reset').onclick = () => {
        setSharedLook(list.id, null);
        sharedLookWindow.close();
    };
    document.getElementById('shared-look-close').onclick = () => sharedLookWindow.close();

    sharedLookWindow.showModal();
}

/**
 * The chat button of a container, with the mark for unread messages.
 *
 * A DOT AND NOT A NUMBER. How many are waiting is not the question one has
 * standing in the woods — whether something is waiting is. The number
 * would also be the one thing in this row that changes while one reads,
 * and a row that twitches draws the eye away from the map.
 */
function chatButton(c) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'symbol-button small chat-button';

    const waiting = chat.unreadFor(c.id);
    // Written lines: the full dot; only the map's status lines: a quiet one.
    const written = chat.unreadPersonalFor(c.id) > 0;
    button.classList.toggle('has-unread', written);
    button.classList.toggle('has-status', waiting > 0 && !written);
    const wie = containerName(c);
    button.title = waiting > 0
        ? t('Chat von „%s" — Neues da', wie)
        : t('Chat von „%s"', wie);
    button.setAttribute('aria-label', button.title);

    button.append(icon('speech-bubble'));
    button.addEventListener('click', (e) => {
        e.stopPropagation();
        chat.openChat(c, start.user?.id);
    });
    return button;
}

/** The friend chat on a row „Von <Name>" — the same bubble, the friend's room. */
function friendChatButton(friend) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'symbol-button small chat-button';
    const waiting = chat.friendUnreadFor(friend.id);
    const written = chat.friendPersonalFor(friend.id) > 0;
    button.classList.toggle('has-unread', written);
    button.classList.toggle('has-status', waiting > 0 && !written);
    button.title = waiting > 0 ? t('Chat mit %s — Neues da', friend.name) : t('Chat mit %s', friend.name);
    button.setAttribute('aria-label', button.title);
    button.append(icon('speech-bubble'));
    button.addEventListener('click', (e) => {
        e.stopPropagation();
        chat.openFriendChat(friend, start.user?.id);
    });
    return button;
}


// ---------------------------------------------------------------------------
// Shared: the sync
//
// OWN NAMESPACE IN THE SAME STORE. Container buckets lie under
// "g<container>:<monat>" next to the own ones ("<monat>") in
// pointstore.js — the colon is the boundary. The own sync only touches
// keys without it (ownFingerprints, ownPointsFromFiling), this one only
// keys with it. That way both can run at the same time without throwing
// each other's buckets away.
// ---------------------------------------------------------------------------

function sharedPrefix(containerId) {
    return `g${containerId}:`;
}

/**
 * The same for an excursion — a namespace of its own in the store.
 *
 * 'e' and not 'g': the two must never collide, and a container 7 and an
 * excursion 7 are entirely different things. rebuildShared() takes both
 * (it asks for a colon, not for a letter), so an excursion find lands in
 * state.sharedPoints like every other foreign point.
 */
function excursionPrefix(excursionId) {
    return `e${excursionId}:`;
}

/** The foreign points from the store into the state — own ones sieved out. */
async function rebuildShared() {
    const all = Object.keys(await pointStore.fingerprints()).filter(s => s.includes(':'));
    const points = all.length > 0 ? await pointStore.points(all) : [];

    state.sharedPoints = new Map();
    for (const p of points) {
        // The list you attached yourself: these points you already have —
        // as own, editable ones. A second, read-only marker on top would be
        // the same point twice.
        if (p.owner_id === start.user.id) continue;
        addForeign(p);
    }
    // They live in the document, not in the store — see addExcursionPoints.
    addExcursionPoints();
}

/** Like getBucket(), only per container and with a key prefix. */
async function getShared(containerId, key) {
    const open = [...key].sort().reverse();
    let rounds = 0;

    while (open.length > 0) {
        if (++rounds > 500) throw new Error(t('Die Freigabe-Eimer kommen nicht zum Ende.'));

        const batch = open.splice(0, BUCKET_BATCH);
        const response = await api.get('bucket', { container: containerId, get: batch.join(',') });
        if (Object.keys(response.buckets || {}).length === 0) {
            throw new Error(t('Der Server liefert keine Freigabe-Eimer mehr, meldet aber offene.'));
        }

        const prefix = sharedPrefix(containerId);
        const filed = {};
        for (const [s, set] of Object.entries(response.buckets)) {
            filed[prefix + s] = set;
        }
        await pointStore.put(filed);

        if (response.open?.length) open.unshift(...response.open);
    }
}

/** Like getShared(), only per excursion. */
async function getExcursion(excursionId, key) {
    const open = [...key].sort().reverse();
    let rounds = 0;

    while (open.length > 0) {
        if (++rounds > 500) throw new Error(t('Die Exkursions-Eimer kommen nicht zum Ende.'));

        const batch = open.splice(0, BUCKET_BATCH);
        const response = await api.get('bucket', { excursion: excursionId, get: batch.join(',') });
        if (Object.keys(response.buckets || {}).length === 0) {
            throw new Error(t('Der Server liefert keine Exkursions-Eimer mehr, meldet aber offene.'));
        }

        const prefix = excursionPrefix(excursionId);
        const filed = {};
        for (const [s, set] of Object.entries(response.buckets)) {
            filed[prefix + s] = set;
        }
        await pointStore.put(filed);

        if (response.open?.length) open.unshift(...response.open);
    }
}

/**
 * Bring store and server into accord for all excursions one leads.
 *
 * THE TWIN OF syncShared(), and deliberately its own function: one
 * excursion that fails must not drag the others down, and neither must it
 * drag the containers down. The reasoning for that is written at
 * syncShared and cost a whole afternoon on 20.08.2026.
 *
 * WHY THE FINDS LIE ON THE DEVICE AT ALL (26.08.2026: „lokal
 * gecachede Punkte für die Exkursionen, für den Offline-Betrieb"). They
 * used to ride along in the document — fine for six finds, and not for a
 * season of them. Stored, they are readable without reception, and they
 * stop being a second origin inside state.sharedPoints: every rebuild
 * finds them by itself.
 */
async function syncExcursions(fresh = false) {
    if (GUEST || start.bucketsReady !== true) return;

    const all   = await pointStore.fingerprints();
    const sizes = await pointStore.bucketSizes();

    // Buckets of excursions one no longer leads — the leadership was
    // withdrawn, the excursion deleted. Without this their last state
    // would lie on the device for ever.
    const known = excursionStart.map(e => excursionPrefix(e.id));
    for (const s of Object.keys(all)) {
        if (/^e\d+:/.test(s) && !known.some(prefix => s.startsWith(prefix))) {
            await pointStore.throwAway(s);
        }
    }

    const failed = [];
    for (const e of excursionStart) {
        try {
            const prefix = excursionPrefix(e.id);
            const answer = fresh ? await api.get('bucket', { excursion: e.id }) : null;
            const there  = answer ? answer.buckets : (e.buckets || {});
            const counts = answer ? (answer.counts || {}) : (e.counts || {});

            for (const s of Object.keys(all)) {
                if (s.startsWith(prefix) && !(s.slice(prefix.length) in there)) {
                    await pointStore.throwAway(s);
                }
            }

            const stale = Object.keys(there).filter(m =>
                all[prefix + m] !== there[m] || bucketShort(prefix + m, counts[m], sizes));
            if (stale.length > 0) await getExcursion(e.id, stale);
        } catch (err) {
            failed.push(e.name);
            console.warn('Exkursion ' + e.id + ' (' + e.name + ') nicht abgeglichen:', err);
        }
    }

    await rebuildShared();

    if (failed.length > 0) {
        status(failed.length === 1
            ? t('Die Funde aus „%s" konnten nicht geholt werden.', failed[0])
            : t('Die Funde aus %d Exkursionen konnten nicht geholt werden.', failed.length), true);
    }
}

/** The finds friends show me — one namespace for all of them (see hintStart). */
const HINT_PREFIX = 'h:';

/** Like getExcursion(), only for the hints. */
async function getHints(key) {
    const open = [...key].sort().reverse();
    let rounds = 0;

    while (open.length > 0) {
        if (++rounds > 500) throw new Error(t('Die direkt geteilten Punkte kommen nicht zum Ende.'));

        const batch = open.splice(0, BUCKET_BATCH);
        const response = await api.get('bucket', { hints: 1, get: batch.join(',') });
        if (Object.keys(response.buckets || {}).length === 0) {
            throw new Error(t('Der Server liefert keine direkt geteilten Punkte mehr, meldet aber offene.'));
        }
        const filed = {};
        for (const [s, set] of Object.entries(response.buckets)) {
            filed[HINT_PREFIX + s] = set;
        }
        await pointStore.put(filed);

        if (response.open?.length) open.unshift(...response.open);
    }
}

/**
 * Bring store and server into accord for the finds friends show me.
 *
 * THE THIRD TWIN, after syncShared() and syncExcursions(), and on its own
 * for their reason: if it fails it must drag neither down. It clears only
 * its own namespace — and all of it when the table is missing or no friend
 * shows anything any more, so a hint taken away does not live on in the
 * store.
 */
async function syncHints(fresh = false) {
    if (GUEST || start.bucketsReady !== true) return;

    const all   = await pointStore.fingerprints();
    const sizes = await pointStore.bucketSizes();
    try {
        const answer = hintStart !== null && fresh ? await api.get('bucket', { hints: 1 }) : null;
        const there  = hintStart === null ? {} : (answer ? answer.buckets : (hintStart.buckets || {}));
        const counts = answer ? (answer.counts || {}) : (hintStart?.counts || {});

        for (const s of Object.keys(all)) {
            if (s.startsWith(HINT_PREFIX) && !(s.slice(HINT_PREFIX.length) in there)) {
                await pointStore.throwAway(s);
            }
        }
        const stale = Object.keys(there).filter(m =>
            all[HINT_PREFIX + m] !== there[m] || bucketShort(HINT_PREFIX + m, counts[m], sizes));
        if (stale.length > 0) await getHints(stale);
        hintsSynced = true;
    } catch (err) {
        console.warn('Gezeigte Funde nicht abgeglichen:', err);
        status(t('Die direkt geteilten Punkte deiner Freunde konnten nicht geholt werden.'), true);
    }
    await rebuildShared();
}

/**
 * Bring store and server into accord for all containers.
 *
 * @param {boolean} fresh   true fetches the fingerprints fresh from the
 *        server; false takes those from the document — at startup an
 *        unchanged container then costs no request, as with the own stock.
 */
/**
 * Is the stored bucket shorter than the server says?
 *
 * Without a count from the server the weaker statement remains: empty is
 * always wrong. If the bucket is not stored at all, nothing speaks here —
 * the fingerprint comparison has already handled that, and reporting the
 * same thing twice would mean fetching it twice.
 */
function bucketShort(key, expected, sizes) {
    const have = sizes[key];
    if (have === undefined) return false;
    return typeof expected === 'number' ? have !== expected : have === 0;
}

async function syncShared(fresh = false) {
    // Containers are the logged-in world — the endpoint answers a guest
    // 401, and there is nothing of his to reconcile anyway.
    if (GUEST) return;

    const all = await pointStore.fingerprints();
    /* HOW MANY POINTS REALLY LIE THERE. The comparison below saw only
       fingerprints; a bucket that looks current and holds too few points
       was therefore never fetched again. That is exactly what the maintainer's
       container hung on: „Finns Pilze" showed 382 on the row — the number
       comes from the document — and on the device there was nothing. No
       error, no message, permanently.

       The server now names the count per bucket beside the fingerprint,
       and that can be held against this directly. Where it is missing — a
       document out of the cache still stemming from before — at least the
       weaker statement remains: empty is always wrong, because on the
       server the fingerprints arise OUT OF the points, and an empty bucket
       does not appear in the directory at all. */
    const sizes = await pointStore.bucketSizes();

    // Buckets of containers that no longer exist for this account — left,
    // deleted, share gone. Without this the last state would lie on the
    // device forever and come back up on every start.
    /* ONLY ITS OWN NAMESPACE. This once threw away everything with a
       colon that was not a known container — and since the excursions
       moved into the same store, that would have been exactly their
       buckets, on every single load. Each sweeper now clears up after
       itself and leaves the other's keys alone (syncExcursions does the
       mirror image). */
    const known = sharedStart.map(c => sharedPrefix(c.id));
    for (const s of Object.keys(all)) {
        if (/^g\d+:/.test(s) && !known.some(p => s.startsWith(p))) {
            await pointStore.throwAway(s);
        }
    }

    // WITHOUT BUCKETS (migration 0038 missing) the points came in one piece
    // with the document — then nothing more is due here.
    if (start.bucketsReady !== true) {
        state.sharedPoints = new Map();
        for (const c of sharedStart) {
            for (const p of c.points ?? []) {
                if (p.owner_id !== start.user.id) state.sharedPoints.set(p.id, p);
            }
        }
        addExcursionPoints();
        return;
    }

    /* EVERY CONTAINER ON ITS OWN. Until now they all lay in ONE loop with
       no guard of their own: if one throws — a broken connection, a round
       that does not get through — the loop ends, and the containers BEHIND
       it are not synced at all. Which ones that hits is decided purely by
       the order in which the server delivers them.

       That is exactly how it looked at the maintainer's (20.08.2026): „Finns
       Pilze" stood at the front and stayed empty, the wildfire container
       behind it showed what still lay on the device from earlier. The
       number on the row meanwhile came from the document and said 382 —
       the container looked full and was not.

       A failed container must not drag the others down with it. */
    const failed = [];
    for (const c of sharedStart) {
        try {
            const prefix = sharedPrefix(c.id);
            const answer = fresh ? await api.get('bucket', { container: c.id }) : null;
            const there  = answer ? answer.buckets : (c.buckets || {});
            /* c.mengen: the name the start document carried before the move
               to English. Pages are network-first in the service worker, so
               the old name only ever arrives OFFLINE, from a cached page —
               and offline is exactly when this fallback branch runs. Without
               it that load would see every bucket as complete. Can go once
               every device has loaded the page online once. */
            const counts = answer ? (answer.counts || {}) : (c.counts || c.mengen || {});

            for (const s of Object.keys(all)) {
                if (s.startsWith(prefix) && !(s.slice(prefix.length) in there)) {
                    await pointStore.throwAway(s);
                }
            }

            const stale = Object.keys(there).filter(m =>
                all[prefix + m] !== there[m] || bucketShort(prefix + m, counts[m], sizes));
            if (stale.length > 0) await getShared(c.id, stale);
        } catch (e) {
            /* AND IT MUST NOT STAY SILENT. The error went to console.warn
               until now, and nobody who uses the map ever looks there.
               What one does see is a list carrying a number and showing no
               points — and that looks like a broken program, not like a
               connection that has torn. */
            failed.push(c.name);
            console.warn('Container ' + c.id + ' (' + c.name + ') nicht abgeglichen:', e);
        }
    }

    await rebuildShared();

    if (failed.length > 0) {
        status(failed.length === 1
            ? t('Die Punkte aus „%s" konnten nicht geholt werden.', failed[0])
            : t('Die Punkte aus %d Freigaben konnten nicht geholt werden.', failed.length), true);
    }
}

/**
 * At startup: first show what lies on the device, then sync.
 *
 * NOT VIA withInventory(): the latch protects the own buckets from concurrent
 * runs — the shared ones touch other keys and may run in parallel,
 * otherwise the map would wait for other people's archive. NO FALLBACK
 * like allAtOnce(): foreign points are copies and can be fetched again
 * anytime — an empty shared view is an annoyance, not a loss.
 */
async function establishShared() {
    try {
        // RUN EVEN WITHOUT CONTAINERS: syncShared clears away the
        // buckets of left containers. Whoever last had a container and
        // loses it would otherwise keep its last state on the device
        // forever.
        if (sharedStart.length === 0) {
            await syncShared(false);
            // AND THE EXCURSIONS ANYWAY: whoever has no container may well
            // lead an excursion, and syncExcursions also clears away what
            // no longer belongs to anybody.
            await syncExcursions(false);
            // And the finds friends show — no container is needed for them.
            await syncHints(false);
            await rebuildShared();
            drawMarker();
            drawList();
            return;
        }
        await rebuildShared();
        drawMarker();
        drawList();
        await syncShared(false);
        await syncExcursions(false);
        await syncHints(false);
        drawMarker();
        drawList();
    } catch (e) {
        console.warn('Geteilt-Abgleich fehlgeschlagen:', e);
    }
}

/**
 * List window: name, color, shape, species list — and deletion.
 *
 * Previously name and color went through prompt(), deletion hid behind an
 * empty name. Neither was findable nor usable on the phone.
 */
const COLOR_ROW = ['#c0392b', '#d98b3a', '#c9b037', '#6aa84f', '#3d9970',
                   '#3a86c8', '#8e6bb5', '#b5527f', '#8a97a4', '#6b5b4a'];

/**
 * Labels for the shapes, in the order of the picker. The paths stand in
 * includes/shapes.php — only the names here, so the two do not drift apart
 * (tests/symbols.php keeps them in step).
 */
// The geometric ones first, then the living ones — and within the living
// ones nothing that asserts an order. What a symbol stands for is decided
// by the user anyway: a tree can mark a site just as well as a plant list.
const SHAPE_NAMES = {
    circle: t('Kreis'),
    flag: t('Fähnchen'), cross: t('Kreuz'),
    banned: t('Durchgestrichen'), check: t('Haken'), fire: t('Feuer'), parking: t('Parkplatz'), campsite: t('Campingplatz'),
    guesthouse: t('Pension'), hotel: t('Hotel'), lodging: t('Übernachtung'), rest_area: t('Rastplatz'),
    mushroom: t('Pilz'), single_mushroom: t('Einzelner Pilz'), bolete: t('Speisepilz'), fly_agaric: t('Giftpilz'),
    plant: t('Pflanze'), conifer: t('Nadelbaum'), broadleaf: t('Laubbaum'),
    flower: t('Blütenpflanze'), rushes: t('Binsen'), cattail: t('Rohrkolben'),
    poor_grassland: t('Magerrasen'), calcareous_grassland: t('Kalktrockenrasen'), sphagnum_bog: t('Sphagnumsumpf'),
    insect: t('Insekt'), beetle: t('Käfer'), ladybird: t('Marienkäfer'), dragonfly: t('Libelle'),
    deer: t('Hirsch'), roe_deer: t('Reh'), wild_boar: t('Wildschwein'), cow: t('Kuh'), sheep: t('Schaf'),
    lichen: t('Flechte'), foliose_lichen: t('Blattflechte'),
    spider: t('Spinne'), hanging_spider: t('Spinne am Faden'), harvestman: t('Weberknecht'), tick: t('Zecke'),
    scorpion: t('Skorpion'), snail: t('Schnecke'), slug: t('Nacktschnecke'), door_snail: t('Schließmundschnecke'),
    mussel: t('Muschel'),
    bird: t('Singvogel'), waterbird: t('Wasservogel'), bird_of_prey: t('Greifvogel'), owl: t('Eule'),
    woodpecker: t('Specht'), wader: t('Stelzvogel'), frog: t('Frosch'), lizard: t('Eidechse'), snake: t('Schlange'),
    fish: t('Friedfisch'), trout: t('Forelle'), predatory_fish: t('Raubfisch'), worm: t('Vielfüßer'), crayfish: t('Krebs'),
};

const SHAPE_ROW = Object.entries(SHAPE_NAMES).map(([value, name]) => [value, name, shapeGeometry(value)]);

const listWindow = document.getElementById('list-window');
let listBeingEdited = null;      // null = new list

/* „Teilen" needs three things only the map knows: the points of a list's
   branch (what a recipient's filter can choose from), the sidebar's drawn
   areas (taken over into a filter — drawing lives on the map), and the
   find fields (the certainty steps of the identification group). */
listShare.configure({
    pointsOf: (listId) => {
        const branch = new Set(listBranch(listId));
        return [...state.points.values()].filter(p => !p.deleted_at && (p.lists ?? []).some(id => branch.has(id)));
    },
    sidebarAreas: () => state.areas ?? [],
    findFields: start.find_fields ?? [],
    status: (text, error = false) => status(text, error),
});


/**
 * @param {object|null} list    What is being edited; null means "new list".
 *                              Determines heading, delete button and hints.
 * @param {object|null} entwurf What should stand in the fields, if not the
 *                              saved state. Needed when the server has
 *                              rejected: then the window stands there again
 *                              like a new list, but with the input in it.
 */
function openListWindow(list, draft = null) {
    listBeingEdited = list;
    const fresh = list === null;
    const values = draft ?? list;

    document.getElementById('list-heading').textContent = fresh ? t('Neue Liste') : t('Liste bearbeiten');
    document.getElementById('list-name').value = values?.name ?? '';
    document.getElementById('list-species').checked = values?.is_species ?? false;
    listGroupsDraw(fresh && !draft ? [] : listGroups(values));
    listPartShow('look');
    showFindFields();

    // A species list with finds cannot be deleted — and that belongs
    // BEFORE the click, not behind it. Previously the button stood there,
    // the prompt came, and only the server said no. A lock you only learn
    // about after agreeing is no lock but a trap.
    //
    // An EMPTY species list (no point in the whole branch) may go, though —
    // the lock protects the collection, not the empty shell; that is how
    // you get rid of a cleared-out import list (server:
    // liste_ast_hat_punkte).
    //
    // Decisive is the SAVED state, not the checkbox in the window: whoever
    // has just taken it out but not saved is still a species list in the
    // database, and the deletion would be refused.
    document.getElementById('list-delete').hidden = fresh || (list != null && speciesLocked(list));

    drawListFields(values?.fields ?? []);
    showListProtection(list);

    // Colour and shape — every list carries its own (lists are flat since
    // the Mappen, migration 0168).
    {
        const defaultColor = values?.color ?? COLOR_ROW[state.lists.size % COLOR_ROW.length];
        buildChoice('list-colors', COLOR_ROW.map(f => [f, f, null]), defaultColor, 'color');
        buildChoice('list-shapes', SHAPE_ROW, values?.symbol ?? 'circle', 'form');
    }

    // The sublist tree — only on a saved list. A new one has no sublists,
    // and creating is only possible under something that exists. An AUTO
    // LIST never has any: nothing may live under it (the server would
    // refuse, liste_eltern_pruefen — as a heading it would lose its points
    // to the first child), so no plus either that can only fail.

    drawListFolders(list);

    // „Teilen" (recipients.js) — a guest has no friends to share with.
    if (GUEST) listShare.hide(); else listShare.open(fresh ? null : list);

    listWindow.showModal();
    document.getElementById('list-name').focus();
}

/**
 * Say why deletion is missing — and how to free it up.
 *
 * The hint follows the checkbox, so the answer to "why can I not delete
 * this" does not come only after saving.
 */
function showListProtection(list) {
    /* IN THE FOOT, WHERE „LISTE LÖSCHEN" WOULD STAND, and only when it does
       not (12.11.2026) — the same question speciesLocked asks for
       the button. The window saves itself now, so the old second sentence
       („nach dem Speichern …") had no moment left to be true in. */
    document.getElementById('list-hint').textContent = list && speciesLocked(list)
        ? t('Als Artenliste ist diese Liste gegen Löschen geschützt.') : '';
}

/**
 * A list's custom fields for editing.
 *
 * The name is both the label and the key under which the value lies on the
 * point. Whoever changes it thereby creates a new field — the old value
 * stays on the point but is no longer displayed. That is the flip side of
 * fields finding each other via their name, and clearly more harmless than
 * the alternative: with two lists having a "Substrat" there would
 * otherwise be two values for one field.
 */
function drawListFields(fieldList) {
    const section = document.getElementById('list-fields');
    section.innerHTML = '';
    for (const f of fieldList) listFieldRow(f.name, f.type, f.is_species, f.options);
}

function listFieldRow(name = '', type = 'text', isSpecies = false, options = null) {
    const section = document.getElementById('list-fields');

    const row = document.createElement('div');
    row.className = 'field-row';

    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 60;
    input.placeholder = t('Bezeichnung des Feldes');
    input.value = name;

    const choice = document.createElement('select');
    for (const [value, text] of [['text', t('einzeilig')], ['multiline', t('mehrzeilig')], ['choice', t('Auswahl')]]) {
        const o = document.createElement('option');
        o.value = value;
        o.textContent = text;
        o.selected = value === type;
        choice.append(o);
    }

    // The choice values, comma-separated in one line — only visible when
    // the type needs them. An empty select field the server stores as a
    // text field, there would be nothing to choose after all.
    const optionsField = document.createElement('input');
    optionsField.type = 'text';
    optionsField.className = 'field-row-options';
    optionsField.placeholder = t('Auswahlwerte, durch Komma getrennt');
    optionsField.value = Array.isArray(options) ? options.join(', ') : '';
    optionsField.hidden = type !== 'choice';
    choice.addEventListener('change', () => {
        optionsField.hidden = choice.value !== 'choice';
        if (!optionsField.hidden) optionsField.focus();
    });

    // Species suggestions are a property of the FIELD, not the list: a find
    // list carries the fungus in the title and the host plant in a field —
    // two names from two groups in the same record.
    const speciesToggle = document.createElement('label');
    speciesToggle.className = 'field-row-species';
    speciesToggle.title = t('Beim Tippen Namen aus der Artendatenbank vorschlagen');
    const speciesCheck = document.createElement('input');
    speciesCheck.type = 'checkbox';
    speciesCheck.checked = !!isSpecies;
    speciesToggle.append(speciesCheck, document.createTextNode(' ' + t('Arten')));

    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'symbol-button small delete';
    removeButton.title = t('Feld entfernen');
    removeButton.setAttribute('aria-label', t('Feld entfernen'));
    removeButton.append(icon('delete'));
    removeButton.addEventListener('click', () => row.remove());

    row.append(input, choice, speciesToggle, removeButton, optionsField);
    section.append(row);
    return input;
}

function readListFields() {
    return [...document.querySelectorAll('#list-fields .field-row')]
        .map(z => ({
            name: z.querySelector('input[type="text"]:not(.field-row-options)').value.trim(),
            type:  z.querySelector('select').value,
            is_species: z.querySelector('input[type="checkbox"]').checked,
            options: z.querySelector('.field-row-options').value
                .split(',').map(o => o.trim()).filter(Boolean),
        }))
        .filter(f => f.name !== '');
}

document.getElementById('list-field-new').addEventListener('click', () => {
    listFieldRow().focus();
});

document.getElementById('list-species').addEventListener('change', () => {
    showListProtection(listBeingEdited);
    showFindFields();
});

/**
 * THE GROUPS A LIST HOLDS, as ticks (12.11.2026, migration
 * 0195): every group the name database knows, with its shape, in the order
 * the server lists them (start.groupShapes) — fungi, lichens, plants, the
 * animals. Shown with the species tick; a new list starts with none ticked,
 * an old one that never chose with fungi (listGroups), as its title has
 * always suggested.
 */
function listGroupsDraw(chosen) {
    const box = document.getElementById('list-groups-choice');
    box.textContent = '';
    for (const [key, group] of Object.entries(start.groupShapes ?? {})) {
        const row = document.createElement('label');
        row.className = 'choice-row';
        const tick = document.createElement('input');
        tick.type = 'checkbox';
        tick.value = key;
        tick.checked = chosen.includes(key);
        const sign = document.createElement('span');
        sign.className = 'list-groups-shape';
        sign.innerHTML = shapePoint('currentColor', group.form);
        const name = document.createElement('span');
        name.textContent = group.name;
        row.append(tick, sign, name);
        box.append(row);
    }
}
/**
 * THE LIST WINDOW'S PARTS AS TABS (12.11.2026; index.php says
 * when they are tabs and when columns): one part at a time, the window
 * opens on „Aussehen". „Teilen" stands only while its section does —
 * recipients.js hides it for a new list and where there is nobody to share
 * with — so the observer below follows that one attribute.
 */
function listPartShow(part) {
    for (const tab of document.querySelectorAll('.list-window-tabs [role="tab"]')) {
        const on = tab.dataset.part === part;
        tab.setAttribute('aria-selected', String(on));
        tab.tabIndex = on ? 0 : -1;
    }
    for (const col of document.querySelectorAll('.list-window-col')) col.classList.toggle('is-active', col.dataset.part === part);
}
for (const tab of document.querySelectorAll('.list-window-tabs [role="tab"]')) {
    tab.addEventListener('click', () => listPartShow(tab.dataset.part));
}
{
    const section = document.getElementById('list-recipients-section');
    const shareTab = document.querySelector('.list-window-tabs [data-part="share"]');
    const follow = () => {
        shareTab.hidden = section.hidden;
        if (section.hidden && shareTab.getAttribute('aria-selected') === 'true') listPartShow('look');
    };
    new MutationObserver(follow).observe(section, { attributes: true, attributeFilter: ['hidden'] });
    follow();
}
function readListGroups() {
    return [...document.querySelectorAll('#list-groups-choice input:checked')].map(b => b.value);
}

/**
 * The fixed field set of every species list, view only — it follows the
 * checkbox above live, so you see BEFORE saving what the list gets.
 * Nothing about it is configurable; that the fields are named the same for
 * everyone is their whole purpose.
 */
function showFindFields() {
    // With the species tick: what the list holds. The find fields are no longer listed here (12.11.2026).
    document.getElementById('list-groups').hidden = !document.getElementById('list-species').checked;
}

/**
 * Build the color or shape picker as a row of buttons.
 *
 * Takes an id OR an element: the main window has fixed boxes with ids, the
 * sublist tree builds its boxes per row itself. 'onChoice' is for the tree
 * — there every choice is saved immediately, not only with a save button
 * at the end.
 */
function buildChoice(section, entries, chosen, type, onChoice = null) {
    if (typeof section === 'string') section = document.getElementById(section);
    section.replaceChildren(...entries.map(([value, label, svg]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = type === 'color' ? 'color-button' : 'shape-button';
        button.dataset.value = value;
        button.title = label;
        button.setAttribute('role', 'radio');
        button.setAttribute('aria-checked', String(value === chosen));
        button.setAttribute('aria-label', label);

        if (type === 'color') {
            button.style.background = value;
        } else {
            // No size given: that stands at .formknopf svg in the
            // stylesheet, as with every glyph of this application.
            button.innerHTML = `<svg viewBox="0 0 24 24" aria-hidden="true">`
                            + `<g fill="currentColor">${svg}</g></svg>`;
        }

        button.addEventListener('click', () => {
            for (const other of section.children) other.setAttribute('aria-checked', 'false');
            button.setAttribute('aria-checked', 'true');
            onChoice?.(value);
        });
        return button;
    }));
}

function readChoice(section) {
    if (typeof section === 'string') section = document.getElementById(section);
    return section?.querySelector('[aria-checked="true"]')?.dataset.value ?? null;
}



/* THE LIST WINDOW SAVES AS IT GOES (12.11.2026: „Wir sollten
   mal über ein Autosave nachdenken" — after finding Speichern below the
   screen's edge, where one discovers only at the end that the settings
   must be kept). Every change is kept a moment after the last one; the foot
   only closes, and says what it kept. A NEW LIST is born with its first
   name: until it has one there is nothing to keep, and from then on it is
   a list like any other, its sharing open. Sharing itself is not part of
   this — each tick there asks and then applies at once (recipients.js).

   ONE SAVE AT A TIME, in order (listSaving): a change made while the new
   list is still on its way waits for its real id, and two answers cannot
   overtake each other. A refused save puts the stock back and says so; the
   window keeps what was typed, and the next change tries again. */
const listSavedNote = document.getElementById('list-saved');
let listSaveTimer = 0;
let listSaving = Promise.resolve();

/** What the window holds now, as the server takes it — null without a name. */
function listData() {
    const data = { name: document.getElementById('list-name').value.trim() };
    if (data.name === '') return null;
    data.is_species = document.getElementById('list-species').checked;
    // What it holds (0195) — kept with the tick off too, so switching back finds them as they were.
    data.species_groups = readListGroups();
    data.fields = readListFields();
    const folderId = readListFolder();
    if (folderId !== undefined) data.folder_id = folderId;
    data.color  = readChoice('list-colors');
    data.symbol = readChoice('list-shapes');
    return data;
}

function listSaveSoon(delay = 500) {
    if (!listWindow.open) return;
    clearTimeout(listSaveTimer);
    listSavedNote.textContent = '';
    listSaveTimer = setTimeout(listSaveNow, delay);
}

function listSaveNow() {
    clearTimeout(listSaveTimer);
    listSaveTimer = 0;
    const data = listData();
    if (!data) {
        // An existing list keeps its old name; a new one simply is not born yet.
        if (listBeingEdited) listSavedNote.textContent = t('Ohne Namen wird nichts gespeichert.');
        return listSaving;
    }
    listSaving = listSaving.then(() => listSend(data)).catch(() => {});
    return listSaving;
}

async function listSend(data) {
    // WRITE IT DOWN FIRST, THEN SEND — as with the point. A new list gets a
    // made-up negative id for that; swapListKey() carries points, the
    // visibility filter and remembered settings over to the real one.
    const edited    = listBeingEdited;
    const before    = edited ? { ...state.lists.get(edited.id) } : null;
    const signature = edited ? edited.id : queue.provisionalKey();
    state.lists.set(signature, {
        ...LIST_DEFAULT,
        ...(state.lists.get(signature) ?? {}),
        ...data,
        id: signature,
        ...(edited ? {} : { pending: true }),
    });
    applyLists([...state.lists.values()]);
    /* A NEW LIST STARTS TICKED (23.09.2026): whoever makes a list means to
       see it; swapListKey() carries the tick over to the server's id. */
    if (!edited) {
        state.visible.add(signature);
        settings.set('visibleLists', [...state.visible]);
        listBeingEdited = state.lists.get(signature);
        document.getElementById('list-heading').textContent = t('Liste bearbeiten');
    }
    computeListCounts();
    drawMarker();
    drawList();
    listSavedNote.textContent = t('Wird gespeichert …');
    /* The window's list as it stood when this save left — compared by the
       object, not the id: swapListKey() gives a new list its real number in
       place, and an id compared afterwards no longer matches. */
    const handle = listBeingEdited;

    try {
        const response = edited
            ? await api.patch('lists', edited.id, data)
            : await api.post('lists', data);
        let id = signature;
        if (edited) {
            // When changing, the server sends the whole table back.
            if (response?.folders) applyFolders(response.folders);
            if (response?.lists) applyLists(response.lists);
        } else {
            // When creating, the new list comes back — or, without network,
            // a second provisional id from the queue; the real one follows.
            swapListKey(signature, response.id);
            id = response.id;
            if (!response.pending) {
                state.lists.set(response.id, { ...state.lists.get(response.id), ...response });
                applyLists([...state.lists.values()]);
            } else {
                state.lists.get(response.id).pending = true;
            }
        }
        // The window goes on with the list as it now stands — a new one can be shared from here on.
        if (listWindow.open && listBeingEdited === handle) {
            listBeingEdited = state.lists.get(id) ?? listBeingEdited;
            if (!edited && !GUEST) listShare.open(listBeingEdited);
            document.getElementById('list-delete').hidden = speciesLocked(listBeingEdited);
            showListProtection(listBeingEdited);
        }
        computeListCounts();
        drawMarker();
        drawList();
        listSavedNote.textContent = response?.pending ? t('Wird übertragen, sobald Verbindung da ist.') : t('Gespeichert');
    } catch (error) {
        // The stock goes back; the window keeps what was typed.
        if (before) {
            state.lists.set(signature, before);
            if (listBeingEdited === handle) listBeingEdited = before;
        } else {
            state.lists.delete(signature);
            state.visible.delete(signature);
            settings.set('visibleLists', [...state.visible]);
            if (listBeingEdited === handle) listBeingEdited = null;
        }
        applyLists([...state.lists.values()]);
        computeListCounts();
        drawMarker();
        drawList();
        listSavedNote.textContent = '';
        status(t('Speichern fehlgeschlagen: %s', error.message), true);
    }
}

{
    const form = document.getElementById('list-form');
    // Sharing applies itself (recipients.js) — its ticks are no change of the list.
    const mine = (e) => !e.target.closest?.('#list-recipients-section');
    form.addEventListener('submit', (e) => { e.preventDefault(); listSaveNow(); });
    // Typing waits a little longer than a tick, so a name is kept once, not per letter.
    form.addEventListener('input', (e) => { if (mine(e)) listSaveSoon(e.target.matches('input[type="text"], textarea') ? 800 : 300); });
    form.addEventListener('change', (e) => { if (mine(e)) listSaveSoon(300); });
    // Colour and shape are buttons (buildChoice), a field taken away is a click.
    form.addEventListener('click', (e) => {
        if (!mine(e)) return;
        if (e.target.closest('#list-colors [role="radio"], #list-shapes [role="radio"], #list-fields .delete')) listSaveSoon(300);
    });
    // Closing keeps what is still waiting.
    listWindow.addEventListener('close', () => { if (listSaveTimer) listSaveNow(); });
}
document.getElementById('list-close').addEventListener('click', () => listWindow.close());

/**
 * Delete a list — with prompt, sync and word on where the orphaned points
 * have gone.
 *
 * ONE FUNCTION FOR BOTH PATHS: the main list's delete button in the
 * window's footer and deleting a sublist in the tree. The rules — prompt,
 * replacement list, message with names — are the same, and two copies of
 * them drifted apart.
 *
 * @returns {Promise<boolean>} whether deletion happened
 */
/**
 * Is this list locked against deletion? A species list that still holds
 * finds (23.09.2026). The server says the same in list_delete(); this only
 * keeps the button from promising what it refuses.
 */
function speciesLocked(list) {
    if (!list.is_species) return false;
    for (const p of state.points.values()) {
        if (!p.deleted_at && p.lists?.includes(list.id)) return true;
    }
    return false;
}

async function deleteListWithConfirmation(list) {
    const joint = ownJointPlace(list.id);
    const handsOver = joint?.root === true && !joint.alone;
    // Empty and without sublists: nothing a prompt could clarify — the
    // cleared-out (import) list goes with one click. NOT A JOINT ROOT:
    // that one changes hands, and a handover unasked is a surprise.
    const silent = !handsOver && !listBranchHasPoints(list.id)
        && (listChildren().get(list.id) ?? []).length === 0;

    const yes = silent || await confirm({
        title: handsOver ? t('Liste „%s" abgeben?', list.name) : t('Liste „%s" löschen?', list.name),
        text: handsOver
            ? t('Sie ist gemeinsam und gehört der Gruppe: sie geht mit allen Punkten an ein anderes Mitglied der Freigabe über, und du findest sie danach unter „Geteilt".')
            : (joint !== null && !joint.root
                ? t('Sie gehört zu einer gemeinsamen Liste. Die Punkte der anderen liegen danach bei ihnen in einer eigenen Liste „… (ehemals gemeinsam)" — und der Chat der Freigabe meldet es.')
                : t('Die Punkte darin bleiben erhalten. Punkte, die in keiner weiteren Liste stehen, findest du danach unter „Ohne Liste".')),
        button: handsOver ? t('Liste abgeben') : t('Liste löschen'),
        danger: true,
    });
    if (!yes) return false;

    try {
        const response = await api.remove('lists', list.id);
        /* HANDED OVER, NOT DELETED: the list stands from now on under
           „Geteilt", and the shares come with the document — so the page
           is loaded anew, after the sentence had its moment to be read
           (the same reason leaving a share reloads, further down). */
        if (response?.handed_over) {
            /* WHAT WAS TICKED STAYS TICKED: the branch's ids move from the
               own choice into the shared one (pointVisible reads both),
               so the map after the reload shows what it showed before.
               Measured without this: „Keine Liste gewählt", an empty map —
               the handover looked like the list had vanished. */
            const shared = new Set(settings.get('visibleShared', []) || []);
            for (const id of listBranch(list.id)) {
                if (state.visible.has(id)) shared.add(id);
                state.visible.delete(id);
            }
            settings.set('visibleShared', [...shared]);
            settings.set('visibleLists', [...state.visible]);
            status(t('Die Liste ist an %s übergegangen — sie bleibt der Gruppe, du findest sie unter „Geteilt".', response.handed_over.to_name));
            setTimeout(() => location.reload(), 2500);
            return true;
        }
        state.visible.delete(list.id);

        // Whoever was visible a moment ago should stay so: the orphans now
        // stand under "Ohne Liste" — the grey gathering place (id 0) or the
        // hand-made catch list of the same name, if it exists
        // (antwort.auffang). Check the right entry immediately, otherwise
        // the points would vanish from the map at the moment of deletion,
        // and that is exactly what the prompt does not announce.
        const count = response?.points ?? 0;
        if (count > 0) state.visible.add(response?.catch_all ?? 0);

        settings.set('visibleLists', [...state.visible]);
        await reloadLists();
        await reloadPoints();
        drawMarker();
        drawList();

        // Where the remaining points have gone deserves saying — otherwise
        // you puzzle over where the markers went.
        status(count > 0
            ? (count === 1
                ? t('Liste gelöscht. 1 Punkt steht jetzt unter „Ohne Liste".')
                : t('Liste gelöscht. %d Punkte stehen jetzt unter „Ohne Liste".', count))
            : t('Liste gelöscht.'));
        return true;
    } catch (error) {
        status(t('Löschen fehlgeschlagen: %s', error.message), true);
        return false;
    }
}

document.getElementById('list-delete').addEventListener('click', async () => {
    const list = listBeingEdited;
    if (!list) return;
    if (await deleteListWithConfirmation(list)) listWindow.close();
});

async function editList(listId) {
    const list = state.lists.get(listId);
    if (list) openListWindow(list);
}

document.getElementById('list-new').addEventListener('click', () => openListWindow(null));

/* ---- Mappen (migration 0168) ------------------------------------------------

   A Mappe is a name that gathers lists in the sidebar and nothing else
   (includes/folders.php). Its window therefore holds only the name; which
   lists lie in it is set at each list.

   ONLY WITH A CONNECTION. Lists and points are made offline with a
   provisional id that the queue swaps later; a Mappe made in a dead zone
   would need the same journey through every list laid into it. For a step
   one takes at home, rarely, the honest answer is a message instead. */

const folderWindow = document.getElementById('folder-window');
let folderBeingEdited = null;

function applyFolders(list) {
    state.folders = new Map((list ?? []).map(f => [f.id, f]));
}

function openFolderWindow(folder) {
    folderBeingEdited = folder;
    document.getElementById('folder-heading').textContent = folder ? t('Mappe bearbeiten') : t('Neue Mappe');
    document.getElementById('folder-name').value = folder?.name ?? '';
    document.getElementById('folder-delete').hidden = folder === null;
    folderWindow.showModal();
    document.getElementById('folder-name').focus();
}

async function folderRequest(id, method, data = {}) {
    const path = 'folders.php' + (id ? '?id=' + encodeURIComponent(id) : '');
    const answer = await request(path, {
        method: 'POST',
        body: JSON.stringify(method === 'POST' ? data : { ...data, _methode: method }),
    });
    if (answer?.folders) applyFolders(answer.folders);
    if (answer?.lists) applyLists(answer.lists);
    drawList();
    return answer;
}

document.getElementById('folder-new').addEventListener('click', () => openFolderWindow(null));
document.getElementById('folder-cancel').addEventListener('click', () => folderWindow.close());

document.getElementById('folder-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('folder-name').value.trim();
    if (name === '') return;
    const editing = folderBeingEdited;
    try {
        if (editing) {
            await folderRequest(editing.id, 'PATCH', { name });
            status(t('Mappe umbenannt.'));
        } else {
            await folderRequest(null, 'POST', { name, client_id: crypto.randomUUID() });
            status(t('Mappe angelegt. Lege Listen hinein, indem du bei einer Liste unter „Mappe" diese Mappe wählst.'));
        }
        folderWindow.close();
    } catch (error) {
        status(error.isNetworkError
            ? t('Mappen lassen sich nur mit Verbindung anlegen und ändern.')
            : error.message, true);
    }
});

document.getElementById('folder-delete').addEventListener('click', async () => {
    const folder = folderBeingEdited;
    if (!folder) return;
    const ok = await confirm({
        title: t('Mappe „%s" löschen?', folder.name),
        text: t('Nur die Mappe verschwindet. Die Listen darin bleiben, mit allen Punkten, und stehen danach ohne Mappe in der Seitenleiste.'),
        button: t('Mappe löschen'),
        danger: true,
    });
    if (!ok) return;
    try {
        await folderRequest(folder.id, 'DELETE');
        folderWindow.close();
        status(t('Mappe gelöscht. Die Listen sind noch da.'));
    } catch (error) {
        status(error.isNetworkError
            ? t('Mappen lassen sich nur mit Verbindung anlegen und ändern.')
            : error.message, true);
    }
});

/** The Mappe radios in „Liste bearbeiten" — only for a main list, only when a Mappe exists. */
function drawListFolders(list) {
    const section = document.getElementById('list-folder-section');
    const box = document.getElementById('list-folders');
    box.innerHTML = '';
    const folders = [...state.folders.values()]
        .sort((a, b) => (a.sort_order - b.sort_order) || a.name.localeCompare(b.name, 'de'));
    section.hidden = folders.length === 0 || (list != null && list.parent_id != null);
    if (section.hidden) return;
    const current = list?.folder_id ?? null;
    for (const [value, label] of [[null, t('Keine Mappe')], ...folders.map(f => [f.id, f.name])]) {
        const row = document.createElement('label');
        row.className = 'choice-row';
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'list-folder';
        radio.value = value === null ? '' : String(value);
        radio.checked = value === current;
        const text = document.createElement('span');
        text.textContent = label;
        row.append(radio, text);
        box.append(row);
    }
}

/** What the radios say — undefined while they are not shown, so nothing is sent. */
function readListFolder() {
    if (document.getElementById('list-folder-section').hidden) return undefined;
    const chosen = document.querySelector('input[name="list-folder"]:checked');
    return chosen && chosen.value !== '' ? Number(chosen.value) : null;
}

/**
 * Attach the X to a search field.
 *
 * IT TRIGGERS 'input' instead of filtering itself. Both fields already
 * have a listener that does everything needed — remember, redraw, close
 * the hit list. Writing the same thing here once more would mean
 * maintaining two versions, one of which eventually forgets something.
 *
 * THE FOCUS STAYS IN THE FIELD. Whoever clears usually wants to type
 * something else, not look at the map — and on the phone the keyboard
 * would otherwise be gone in one stroke.
 *
 * MOUSEDOWN IS INTERCEPTED: without that the button takes the focus out of
 * the field when pressed, and with the header search the hit list thereby
 * closes before the click even arrives.
 */
function bindClearButton(field, button) {
    if (!field || !button) return;

    const refresh = () => { button.hidden = field.value === ''; };

    field.addEventListener('input', refresh);
    button.addEventListener('mousedown', (e) => e.preventDefault());
    button.addEventListener('click', () => {
        field.value = '';
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.focus();
    });

    // Right at startup: the point filter comes back from the settings and
    // then already stands in the field before anyone has typed anything.
    refresh();
}

// THE POINT FILTER — the same rows as in Organisieren (assets/js/filter.js).
//
// Remembered via the settings as before; only the row list now stands
// there instead of a single text. Important that it is visible — a silent
// filter that hides half the map would be a riddle.
const searchRows = document.getElementById('search-rows');

/* NO FILTER FOR THE GUEST (24.08.2026): on an excursion so
   few finds accrue that filtering answers no question — the section is
   hidden (map.css). And with it hidden, NOTHING may be restored from
   the bundle either: on a borrowed phone the owner's search terms or
   drawn areas would sift the guest's finds out of his own map, with no
   visible cause and no way to clear them. Exactly the hidden condition
   the rules warn about; the guest therefore starts blank. */
state.terms = GUEST ? [] : filter.termsFromText(settings.get('searchTerms', ''));
// ADOPT THE OLD SETTING: whoever left the map with a filter before the
// rebuild finds it again as the first row instead of losing it without a
// word. Only the first time — after that the new version stands there.
if (!GUEST && state.terms.length === 0) {
    const old = String(settings.get('searchText', '') || '').trim();
    if (old !== '') state.terms = [{ type: 'contains', text: old }];
}

function drawSearchRows() {
    filter.drawTermRows(searchRows, state.terms, () => {
        settings.set('searchTerms', filter.writeTerms(state.terms));
        filterPanel.refresh();
        drawMarker();
        drawList();
    });
}

/* THE DRAWN AREAS ("Ort", the call, 22.08.2026): a point counts
   when it lies in ONE of them. REMEMBERED, although the rule above says a
   hidden condition that hides much is dangerous — here the cause is not a
   checkmark in a folded-shut compartment but literally drawn on the map:
   as long as an area filters, its dashed outline stands there. The
   settings bundle is at the same time the bridge to Organisieren, which cannot
   draw and reads the same list. */
state.areas = GUEST ? [] : filter.readAreas(settings.get('filterAreas', []));

const areaRows = document.getElementById('area-filter-rows');
let filterAreaShapes = [];

/* The dashed twin of every filter area on the map — visible as long as it
   filters, in the accent so it reads as interface, not as terrain. Not
   interactive: it is a display, and a catch surface over half the forest
   would swallow every tap meant for a marker. */
function drawFilterAreaShapes() {
    for (const shape of filterAreaShapes) map.removeLayer(shape);
    const color = getComputedStyle(document.documentElement)
        .getPropertyValue('--accent').trim() || '#888';
    filterAreaShapes = state.areas.map((corners) => L.polygon(corners, {
        color, weight: 2, dashArray: '6 6', fillOpacity: .05, interactive: false,
    }).addTo(map));
}

function areasChanged() {
    settings.set('filterAreas', state.areas);
    drawAreaRows();
    drawFilterAreaShapes();
    // A drawn area narrows the region offers too — see placeChanged.
    drawRegionBox();
    filterPanel.refresh();
    drawMarker();
    drawList();
}

function drawAreaRows() {
    filter.drawAreas(areaRows, state.areas, areasChanged, () => {
        beginOutline({
            title:   t('Bereich zeichnen'),
            message: t('Bereich zeichnen: Ecke für Ecke antippen, dann „Fertig".'),
            done(corners) {
                endDrawing();
                state.areas.push(corners);
                areasChanged();
                status(t('Bereich %d filtert jetzt — %s.', state.areas.length,
                    polygon.readableArea(polygon.areaM2(corners))));
            },
        });
    });
}

/* Region, municipality and map sheet — the place's other three answers
   (22.08.2026). Filled from the remembered bundle right
   below, like everything else since 30.08.2026. */
state.regions = [];
state.municipalities = [];
state.mtbText = '';
let regionsOpen = false;

/* THE WHOLE FILTER IS REMEMBERED (30.08.2026: „die letzte
   Tour … wird wohl nicht persistiert … Überprüft doch mal den ganzen
   Filter"). For a long time most conditions were per-session ON
   PURPOSE — a remembered checkmark in a folded-shut compartment hid
   points without visible cause. That worry has lost its ground: the
   Filter button carries a COUNT of active conditions even while the
   section is folded, so a filtering start is never silent. Terms and
   areas keep their own long-standing keys; everything else travels in
   ONE bundle, validated field by field — a bent entry falls back to
   open, never to a filter nobody can see or clear.

   MUTATED IN PLACE for identification and rl, never replaced: their
   drawers get the objects handed ONCE and hold the reference — the
   same rule clearAllFilters spells out. */
if (!GUEST) {
    const f = settings.get('filterConditions', null);
    if (f && typeof f === 'object') {
        const str = v => typeof v === 'string' ? v : '';
        const list = v => Array.isArray(v) ? v.filter(x => typeof x === 'string') : [];
        const num = (v, fallback) => (typeof v === 'number' && Number.isFinite(v)) ? v : fallback;
        state.timeRange = { from: str(f.timeRange?.from), to: str(f.timeRange?.to) };
        state.tour   = str(f.tour);
        state.noTime = f.noTime === true;
        state.regions        = list(f.regions);
        state.municipalities = list(f.municipalities);
        state.mtbText        = str(f.mtbText);
        state.identification.open = f.identification?.open === true;
        state.identification.marks.push(...list(f.identification?.marks));
        state.identification.certainty.push(...list(f.identification?.certainty));
        state.identification.worked = ['yes', 'no'].includes(f.identification?.worked)
            ? f.identification.worked : '';
        state.rl.push(...list(f.rl));
        state.edibility.push(...filter.readEdibility(list(f.edibility)));
        state.responsibility = f.responsibility === true;
        state.elevation.from = Math.max(0, num(f.elevation?.from, 0));
        state.elevation.to   = Math.min(filter.ELEVATION_MAX,
            num(f.elevation?.to, filter.ELEVATION_MAX));
        for (const [key] of filter.TAXONOMY_RANKS) {
            state.taxonomy[key] = str(f.taxonomy?.[key]);
        }
        state.taxonomy._derived = list(f.taxonomy?._derived);
    }
}

/* Written at drawList's tail — the one mouth every filter change runs
   through — and only when the bundle really changed. */
let filterConditionsWritten = '';
function rememberFilterConditions() {
    if (GUEST) return;
    const bundle = {
        timeRange: state.timeRange,
        tour: state.tour,
        noTime: state.noTime,
        regions: state.regions,
        municipalities: state.municipalities,
        mtbText: state.mtbText,
        identification: state.identification,
        rl: state.rl,
        edibility: state.edibility,
        responsibility: state.responsibility,
        elevation: state.elevation,
        taxonomy: state.taxonomy,
    };
    const written = JSON.stringify(bundle);
    if (written === filterConditionsWritten) return;
    filterConditionsWritten = written;
    // A COPY, not the live objects — the bundle aliases state, and a
    // store that serialises later would write whatever state is by then.
    settings.set('filterConditions', JSON.parse(written));
}

/* Offered is only what the stock knows — own and shared points alike,
   like the day list of the time filter. Rebuilt on every opening of the
   section, because the points arrive after this module wires up. */
function placeStock() {
    return [...state.points.values(), ...state.sharedPoints.values()];
}

/* The place conditions as one bundle — for the sieve and for the facet
   stocks below. */
function placeConditions() {
    return { areas: state.areas, regions: state.regions,
             municipalities: state.municipalities, mtb: state.mtbText };
}

/* THE OFFERS NARROW ALONG (22.08.2026): everything in the
   place is AND-linked, so each list offers only what can still match —
   computed from the points meeting all OTHER place conditions, never the
   own one (see placeMatches). The other filter groups stay out, the same
   call as for the time filter's day list: which places exist does not
   depend on what you are searching for. */
function placeFacetStock(except) {
    return placeStock().filter((p) => filter.placeMatches(placeConditions(), p, except));
}

/* Every place change redraws the region list too — it is the one offer
   standing OPEN on the page; municipality and sheet refresh themselves
   on focus. */
function placeChanged() {
    drawRegionBox();
    filterPanel.refresh();
    drawMarker();
    drawList();
}

function drawRegionBox() {
    // LAZY (the function, not the list): the points arrive after wiring,
    // and with the section remembered open no reopening rebuilds — the
    // fold click and the field focus fetch the current stock themselves.
    filter.drawRegions(document.getElementById('region-filter'), {
        available: () => filter.regionsOf(placeFacetStock('regions')),
        chosen:    state.regions,
        open:      regionsOpen,
        onFold:    (open) => { regionsOpen = open; drawRegionBox(); },
        onChange:  placeChanged,
    });
}

function drawMunicipalityBox() {
    filter.drawMunicipalities(document.getElementById('municipality-filter'), {
        available: () => filter.municipalitiesOf(placeFacetStock('municipalities')),
        chosen:    state.municipalities,
        onChange:  () => { drawMunicipalityBox(); placeChanged(); },
    });
}

function drawPlaceControls() {
    // The inner fold follows the group rule: open when something is
    // chosen, otherwise shut.
    regionsOpen = state.regions.length > 0;
    drawRegionBox();
    drawMunicipalityBox();
}

/* The threat categories, each checked individually (the call,
   15.08.2026).

   NOT REMEMBERED, for the same reason as time range and "Nicht bestimmt"
   (see state above): this filter hides far more than those — of two
   hundred finds a handful carry a classification at all. Whoever opens
   the map and sees three points goes looking for the fault on the server.
   The search rows are remembered because one's own text stands there and
   with it the cause; a checkmark in a folded-shut compartment says
   nothing. */
/**
 * What a rank has on offer when the OTHER conditions apply.
 *
 * FACETED, like the place filter: choosing a kingdom narrows the classes.
 * Otherwise one would pick a family that excludes everything already
 * chosen and stand in front of an empty map without being told why.
 *
 * READ FROM THE WHOLE STOCK, own points and foreign ones alike — what
 * exists does not depend on which list is currently checked, exactly as
 * the day list of the time filter does not.
 */
function taxonomyOffers(rank, others) {
    const values = new Set();
    for (const point of [...state.points.values(), ...state.sharedPoints.values()]) {
        const ranks = point.taxonomy;
        if (!ranks || !ranks[rank]) continue;
        if (!filter.taxonomyMatches(others, ranks)) continue;
        values.add(ranks[rank]);
    }
    return [...values].sort((a, b) => a.localeCompare(b, 'de'));
}

function drawTaxonomyControl() {
    filter.drawTaxonomy(document.getElementById('taxonomy-build'), state.taxonomy,
        taxonomyOffers, () => {
            // Redrawn, because every change renarrows the other four offers.
            drawTaxonomyControl();
            filterPanel.refresh();
            drawMarker();
            drawList();
        });
}

function drawElevationControl() {
    filter.drawElevation(document.getElementById('elevation-build'), state.elevation, () => {
        filterPanel.refresh();
        drawMarker();
        drawList();
    });
}

function drawRl() {
    const changed = () => {
        filterPanel.refresh();
        drawMarker();
        drawList();
    };
    filter.drawRl(document.getElementById('rl-selection'), state.rl, changed);
    // The DGfM's two questions (17.09.2026) are drawn with the Red List's:
    // the responsibility hook lives in its group, the food-value marks in
    // their own — one call, so a redraw cannot forget one of them.
    filter.drawResponsibility(document.getElementById('rl-responsibility'), state.responsibility, (on) => {
        state.responsibility = on;
        changed();
    });
    filter.drawEdibility(document.getElementById('edibility-selection'), state.edibility, changed);
}

/* How many conditions currently apply — for the number on the filter
   button. Time range and "Nicht bestimmt" count too: the button should say
   whether anything filters at all, not only whether text stands there.

   THE THREAT STATUS COUNTS AS ONE CONDITION, not as one per checkmark:
   three checked categories are one question with three allowed answers,
   not three conditions — a "5" on the button where two filters are set
   would be false information. */
const filterGroupCount = {
    'text': () => filter.readTerms(state.terms).length,
    // As with the threat status: several areas are ONE question with
    // several allowed answers — but areas, regions, municipalities and
    // map sheet are FOUR questions, AND-linked, and count one each.
    'place': () => (state.areas.length > 0 ? 1 : 0)
        + (state.regions.length > 0 ? 1 : 0)
        + (state.municipalities.length > 0 ? 1 : 0)
        + (filter.mtbAlternatives(state.mtbText).length > 0 ? 1 : 0),
    'time': () => (state.timeRange.from !== '' || state.timeRange.to !== ''
            || state.tour !== '' ? 1 : 0)
        + (state.noTime ? 1 : 0),
    // Three questions, like place counts its four: the collective hook,
    // the abbreviation families as one, the certainty steps as one.
    'identification': () => (state.identification.open ? 1 : 0)
        + (state.identification.marks.length > 0 ? 1 : 0)
        + (state.identification.certainty.length > 0 ? 1 : 0)
        + (state.identification.worked !== '' ? 1 : 0),
    // The categories as one question, the responsibility hook as a second.
    'red-list': () => (state.rl.length > 0 ? 1 : 0) + (state.responsibility ? 1 : 0),
    'edibility': () => (state.edibility.length > 0 ? 1 : 0),
    // One question with two ends — a band is one condition, not two.
    'elevation': () => (state.elevation.from > 0
        || state.elevation.to < filter.ELEVATION_MAX ? 1 : 0),
    /* COUNTED IS WHAT ONE CHOSE, not what follows from it. Picking a
       family sets four ranks above it, and „Systematik 5" would claim five
       decisions where there was one. The derived ones carry their own
       mark (drawTaxonomy). */
    'taxonomy': () => {
        const derived = new Set(state.taxonomy._derived ?? []);
        return filter.TAXONOMY_RANKS.filter(([key]) =>
            !derived.has(key) && String(state.taxonomy[key] ?? '').trim() !== '').length;
    },
};

// From here on the empty-list line can trust the real numbers — see the
// default near drawList.
activeFilterConditions = () => Object.values(filterGroupCount).reduce((n, f) => n + f(), 0);

const sectionPanel = filter.wireCollapsible({
    button:  document.getElementById('filter-open'),
    content: document.getElementById('filter-section'),
    count: () => Object.values(filterGroupCount).reduce((n, f) => n + f(), 0),
    clear: clearAllFilters,
    // Remembered like the conditions themselves (settings, the same
    // bundle as 'searchTerms' above) — otherwise the section would stand
    // open again after every visit, no matter how it was left.
    openAtStart: settings.get('filterOpen', false) === true,
    // Every OPENING recomputes the group folds (22.08.2026):
    // Text open, the rest only where its condition applies.
    onToggle: (open) => {
        settings.set('filterOpen', open);
        // Opening recomputes folds AND the place offers — the points
        // arrive after wiring, and new finds bring new municipalities.
        /* THE OFFERS ARE READ FROM THE STOCK, and the stock arrives AFTER
           the wiring — with the buckets on, the start data carries no
           points at all. Drawn only once, the place boxes and the
           systematic ranks would stand empty for the whole visit. */
        if (open) { drawPlaceControls(); drawTaxonomyControl(); filterGroupFolds.reset(); }
    },
});
/* The single groups fold at their headings (22.08.2026) — no
   stored state: reset() computes the folds from the conditions, see
   wireGroupFolds. The old key from the one day of stored folds goes. */
settings.forget('filterGroups');
const filterGroupFolds = filter.wireGroupFolds({
    section: document.getElementById('filter-section'),
    counts:  (key) => filterGroupCount[key](),
});
const filterPanel = {
    refresh: () => { sectionPanel.refresh(); filterGroupFolds.refresh(); },
};

drawSearchRows();
drawAreaRows();
drawFilterAreaShapes();
drawPlaceControls();
drawElevationControl();
drawTaxonomyControl();
/* NAMED, because clearAllFilters has to redraw it. drawPlaceControls()
   does not reach it — it folds regions and municipalities —, and a map
   sheet left standing in the field while its filter is off is exactly the
   half-cleared state that makes a short list a riddle. */
function drawMtbControl() {
    filter.drawMtb(document.getElementById('mtb-filter'), {
        get: () => state.mtbText,
        available: () => filter.mtbOf(placeFacetStock('mtb')),
        onChange: (value) => { state.mtbText = value; placeChanged(); },
    });
}
drawMtbControl();
drawRl();

// ---------------------------------------------------------------------------
// Time filter: last tour and time range
//
// TWO WAYS TO THE SAME THING, and both are needed. "Letzte Tour" is the
// question about what you just brought home — one press, no thinking about
// dates. The time range is the question about a season or a year, and for
// that it takes two calendars.
//
// THEY EXCLUDE EACH OTHER. Both at once would yield an intersection nobody
// holds in their head — and usually the empty one. Whoever chooses the one
// switches the other off.
// ---------------------------------------------------------------------------

const timeFilterBox = document.getElementById('time-filter-build');
const timeFilterHintEl = document.getElementById('time-filter-hint');

/** Say what the filter currently means — otherwise the list is just shorter. */
function timeFilterHint(text) {
    timeFilterHintEl.textContent = text ?? '';
    timeFilterHintEl.hidden = !text;
}

/*
 * THE DAYS ON WHICH POINTS EXIST — across the WHOLE stock, not the
 * currently visible ones: the last tour is the last tour even when its
 * points lie in a list that is not currently checked. Otherwise the offer
 * would depend on what was filtered before.
 *
 * The shared points count too — they stand in the same list and are
 * covered by the same time filter.
 */
/* WHOSE A POINT IS, for the tour rows (14.09.2026): somebody else's when
   it carries another account's id — the same question trackForeign()
   asks of a walk — and then in the SHORT FORM the sidebar uses for the
   shares („so wie wir's in der Sidebar haben"): „Anna", or „Finn
   Ho." where two Finns share the column. One's own points answer null,
   so an own outing never wears one's own name. */
function tourOwnerOf(p) {
    return p.owner_id != null && p.owner_id !== start.user?.id && p.owner
        ? shortNameOf(String(p.owner)) : null;
}

function timeFilterDays() {
    // Tours first, days for everything without one — collectTours leaves
    // the old stock reading exactly as before.
    const rows = timeRange.collectTours(
        [...state.points.values(), ...state.sharedPoints.values()], undefined, tourOwnerOf);

    /* THE EXCURSIONS STAND BESIDE THE TOURS (26.08.2026), and
       the two answer different questions on the same day:
       
           the tour        only MY finds of that outing
           the excursion   all of them — mine, the guests', the co-leaders'
       
       Both therefore belong in the list, and the excursion says its own
       name so nobody has to guess which of the two he picked. The key is
       „e:<id>" and not the name: two excursions may share one, and a name
       could collide with a tour's. */
    const withExcursion = new Set();
    for (const p of state.points.values()) {
        if (!p.deleted_at && p.excursion_id != null) withExcursion.add(p.excursion_id);
    }
    for (const p of state.sharedPoints.values()) {
        if (p.excursion_id != null) withExcursion.add(p.excursion_id);
    }
    // Only excursions that really hold something — an empty one in the
    // picker would be a row one presses to see nothing.
    for (const e of excursionStart) {
        if (!withExcursion.has(e.id)) continue;
        rows.push({
            tour:  'e:' + e.id,
            label: e.name,
            tag:   (e.starts_at ?? '').slice(0, 10) || null,
            orte:  '',
        });
    }
    // Most recent first, as collectTours already sorts — a row appended
    // afterwards would otherwise always sit at the bottom.
    rows.sort((a, b) => String(b.tag ?? '').localeCompare(String(a.tag ?? '')));
    return rows;
}

/** Redraw and report — shared tail of both time-filter callbacks. The
 *  sentence under the filter comes out of drawList (timeFilterHintRefresh). */
function timeFilterApplied() {
    filterPanel.refresh();
    drawMarker();
    drawList();
}

const timeFilterControl = timeRange.buildTimeFilter({
    root: timeFilterBox,
    get:  () => state.timeRange,
    days: timeFilterDays,
    /* The span of the year band: oldest and newest year with a find, over
       the whole stock like the day list — read on every refresh, which is
       one pass over the points and nothing more. */
    years: () => {
        let min = null, max = null;
        for (const p of [...state.points.values(), ...state.sharedPoints.values()]) {
            const d = timeRange.dayOf(p);
            if (d === null) continue;
            const y = Number(d.slice(0, 4));
            if (min === null || y < min) min = y;
            if (max === null || y > max) max = y;
        }
        return min === null ? null : [min, max];
    },
    set:  (from, to) => {
        state.timeRange = { from, to };
        timeFilterApplied();
    },
    getTour: () => state.tour,
    setTour: (name) => {
        state.tour = name;
        timeFilterApplied();
    },
    /* For a RESTORED tour (remembered filter, 30.08.2026): its key was
       never picked this visit, so the control's own label map is empty —
       the name is derived from the stock instead. An excursion knows its
       name in the start data; a tour key finds it on any of its points;
       an old name-keyed tour IS its name. Before the buckets arrive the
       raw key may flash — filterStockArrived() refreshes right after. */
    labelFallback: (key) => {
        if (key.startsWith('e:')) {
            return excursionStart.find(e => 'e:' + e.id === key)?.name ?? key;
        }
        // Somebody else's day is called by its date, as its row is.
        const othersDay = timeRange.othersDayOf(key);
        if (othersDay !== null) return timeRange.readableDay(othersDay);
        if (key.startsWith('t:')) {
            const id = key.slice(2);
            for (const p of [...state.points.values(), ...state.sharedPoints.values()]) {
                if (String(p.tour_id ?? '') === id && p.tour) return p.tour;
            }
        }
        return key;
    },
    /* And the PERSON of a restored foreign tour, by the same walk: null
       as soon as one own point carries the id (then it is my outing),
       otherwise the first foreign point's short name. */
    ownerFallback: (key) => {
        /* Somebody else's day names its persons the way its row did —
           collectDays over the finds the key picks, so the chip after a
           reload reads exactly as the row read before it. */
        if (timeRange.othersDayOf(key) !== null) {
            const held = [...state.sharedPoints.values()]
                .filter(p => timeRange.othersDayHolds(p, key, tourOwnerOf));
            return timeRange.collectDays(held, undefined, tourOwnerOf)[0]?.owner ?? null;
        }
        if (!key.startsWith('t:')) return null;
        const id = key.slice(2);
        let by = null;
        for (const p of [...state.points.values(), ...state.sharedPoints.values()]) {
            if (String(p.tour_id ?? '') !== id) continue;
            const who = tourOwnerOf(p);
            if (who === null) return null;
            by ??= who;
        }
        return by;
    },
});

/* THE SENTENCE UNDER THE TIME FILTER FOLLOWS EVERY REDRAW
   (26.09.2026: „mach alle drei", after it was named to him that its count
   stood still when a list was ticked afterwards). It was counted only
   when the time filter itself changed — tick Ben's list a moment later
   and the list said „1 Punkt" under a sentence that still said
   „0 Punkte". Now drawList, the one mouth every filter change runs
   through, hands in the count it has just made: the same two tests
   (pointVisible, sharedVisible), one pass, and the sentence and the
   list can no longer disagree.

   WHAT IS COUNTED IS WHAT THEN STANDS THERE — not the day's points. The
   day is determined across the whole stock, but the list shows only the
   checked lists: counted beforehand, the hint claimed "145 Punkte" while
   thirty-seven were visible. */
timeFilterHintRefresh = (n) => {
    const { from, to } = state.timeRange;
    if (state.tour === '' && (from === '' || from !== to)) {
        timeFilterHint(null);
        return;
    }
    /* „Exkursion" and not „Tour" where an excursion was picked — the word
       is the whole difference between one's own finds and everyone's.

       THE NAME COMES FROM THE CONTROL, not from the key. A tour key is
       „t:9f3c…" since the outings are told apart by identity, and written
       into this sentence raw it would read „Tour „t:9f3c…"" — which is
       exactly what the button beside it did with an excursion until now. */
    /* Somebody else's day is no tour of anybody's: its date and whose,
       joined like its row („… · von Finn"). NOT t('%s von %s') — that
       sentence means „3 von 5" everywhere else. */
    const othersDay = timeRange.othersDayOf(state.tour);
    const by = othersDay !== null ? timeFilterControl.personOf(state.tour) : null;
    const chosen = state.tour.startsWith('e:')
        ? t('Exkursion „%s"', excursionStart.find(e => 'e:' + e.id === state.tour)?.name ?? '?')
        : othersDay !== null
            ? [timeRange.readableDay(othersDay), by ? t('von %s', personName(by)) : ''].filter(Boolean).join(' · ')
            : t('Tour „%s"', timeFilterControl.labelOf(state.tour));
    timeFilterHint((state.tour !== '' ? chosen : timeRange.readableDay(from))
        + ' · ' + (n === 1 ? t('%d Punkt', n) : t('%d Punkte', n)));
};

/* The stock is here — now a restored time or tour filter can say its
 *  name and its count: the hint under the filter and the button's word
 *  both read from the points, which did not exist at wiring time. */
function filterStockArrived() {
    timeFilterControl.refresh();
    if (state.tour !== '' || state.timeRange.from !== '' || state.timeRange.to !== '') {
        timeFilterApplied();
    }
}

// ---------------------------------------------------------------------------
// Not identified
//
// One's own work list: everything whose name carries a cf., sp. or s. l.
// Which spellings apply and why checking goes word by word stands in
// identification.js.
//
// A SWITCH OF ITS OWN, NOT PART OF THE TIME FILTER. It can be combined
// with a time range — "what did I bring home from the last tour and not
// identify yet" is the question for which you need both at once. That is
// why "Zurücksetzen" does not clear it either: that belongs to the time
// filter and would otherwise clear away something it does not refer to.
// ---------------------------------------------------------------------------

// Built by drawIdentification (filter.js) — the same drawing as in
// Organisieren, over the same state shape, so the two surfaces cannot drift
// apart. The steps of the certainty field come from find_fields in the
// start data; what the form offers is what can be filtered.
/** Named, because clearAllFilters redraws this control and has to hand
 *  the same callback back in. */
function identificationApplied() {
    filterPanel.refresh();
    drawMarker();
    drawList();
}

filter.drawIdentification(
    document.getElementById('identification-build'),
    state.identification,
    identificationApplied,
    start.find_fields
);

/**
 * Every filter off, in one press (26.08.2026).
 *
 * THE LIST HAS TO BE COMPLETE, and that is the whole difficulty: a
 * "clear everything" that leaves one condition standing is worse than
 * none — the list stays short, the button says nothing any more, and one
 * searches for a find that is being held back by something one has just
 * been told is gone. The yardstick is filterGroupCount above: what counts
 * there has to fall here. Whoever adds a group changes both.
 *
 * MUTATED IN PLACE, NOT REPLACED, for identification and rl: their
 * drawers were handed the object ONCE at startup and hold on to that
 * reference. A fresh object would leave them drawing the old one, and the
 * checkmarks would stand while the filter is off.
 */
function clearAllFilters() {
    state.terms = [];
    state.areas = [];
    state.regions = [];
    state.municipalities = [];
    state.mtbText = '';
    state.timeRange = { from: '', to: '' };
    state.tour = '';
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

    // The two that outlive a visit — the rest lives only in this session.
    settings.set('searchTerms', filter.writeTerms(state.terms));
    settings.set('filterAreas', state.areas);

    // Every control redrawn, so no checkmark survives its condition.
    drawSearchRows();
    drawAreaRows();
    drawFilterAreaShapes();
    drawPlaceControls();
    drawMtbControl();
    drawElevationControl();
    drawTaxonomyControl();
    drawRl();
    filter.drawIdentification(document.getElementById('identification-build'),
        state.identification, identificationApplied, start.find_fields);
    timeFilterControl.refresh();
    noTimeCheck.checked = false;
    timeFilterHint('');

    filterPanel.refresh();
    drawMarker();
    drawList();
    status(t('Filter gelöscht.'));
}

const noTimeCheck = document.getElementById('time-filter-no-time');
// The restored bundle may already say yes — the static checkbox has to
// catch up, where the drawn controls read their state themselves.
noTimeCheck.checked = state.noTime;
noTimeCheck.addEventListener('change', () => {
    state.noTime = noTimeCheck.checked;
    filterPanel.refresh();
    drawMarker();
    drawList();
});

// ---------------------------------------------------------------------------
// Coordinate search in the header
//
// SEPARATE FROM THE POINT FILTER, and that is the difference this is
// about: the filter searches what you HAVE. The coordinate search leads to
// a spot where nothing is yet — often the first step before a point comes
// into being there at all. Putting both into one field meant mixing two
// moves that have nothing to do with each other.
//
// WHAT IT EATS stands in coordinates.js: decimal degrees,
// degrees/minutes/seconds, degrees with decimal minutes, UTM,
// Gauss-Krueger, map sheet, map links — with dot or comma, compass point
// in front or behind. Recognition is automatic; what was recognized is
// stated. A silent conversion that lands off target would be worse than a
// follow-up question.
// ---------------------------------------------------------------------------

const headerSearch    = document.getElementById('header-search');
const coordField    = document.getElementById('coord-search');
const coordResults = document.getElementById('coord-results');
const headerSearchButton = document.getElementById('header-search-button');

/**
 * The search flap open or closed — and the state outlives the visit.
 *
 * WHOEVER NEEDS IT NEEDS IT CONSTANTLY. It folds open instead of being
 * permanently visible because it is rarely needed — but "rarely" holds for
 * the users as a whole, not for the individual. Whoever works by
 * coordinates would otherwise fold it open anew on every visit.
 *
 * $focus IS OFF WHEN RESTORING. On the phone a focus() brings the keyboard
 * up, and it covers half the map. When folding open by hand, the cursor in
 * the field is exactly what you want; on merely restoring a view it would
 * be an intrusion.
 */
function showHeaderSearch(on, focus = true) {
    headerSearch.hidden = !on;
    headerSearchButton.setAttribute('aria-expanded', String(on));
    headerSearchButton.classList.toggle('is-active', on);
    settings.set('headerSearchOpen', on);
    if (on) {
        if (focus) coordField.focus();
    } else {
        coordField.value = '';
        showCoordMatches('');
        // The mark belongs to the search. If it stays, tomorrow it still
        // points at a spot searched for today — and the button would keep
        // promising "Punkt hier".
        removeCrosshair();
    }
}

headerSearchButton.addEventListener('click', () => showHeaderSearch(headerSearch.hidden));

/* AT THE DESK THE SEARCH STANDS IN THE PAGE'S BAR, OPEN
   (09.10.2026). A field in a bar is not folded away behind a button — the
   button is hidden there (bar-moved) and the field is simply there; Escape
   then clears it instead of folding it. Below 861 px, or for a guest who
   has no bar, the block goes back under the sidebar's head and is as open
   or shut as the visitor left it. */
const barSearchSlot = document.getElementById('app-bar-search');
const barWide = window.matchMedia('(min-width: 861px)');
const searchInBar = () => barSearchSlot !== null && barWide.matches;
function placeHeaderSearch() {
    if (searchInBar()) {
        barSearchSlot.append(headerSearch);
        headerSearch.hidden = false;
    } else {
        document.querySelector('.sidebar-header').after(headerSearch);
        headerSearch.hidden = settings.get('headerSearchOpen', false) !== true;
    }
}
placeHeaderSearch();
barWide.addEventListener('change', placeHeaderSearch);

coordField.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    e.preventDefault();
    if (searchInBar()) {
        coordField.value = '';
        showCoordMatches('');
        removeCrosshair();
        coordField.blur();
    } else {
        showHeaderSearch(false);
    }
});

/**
 * A short Plus Code with a place name — then the lookup is worth it.
 *
 * The request goes outside, and with exactly one word: the place name
 * somebody typed themselves. No coordinate, no species, no point. That is
 * why it is defensible here and at no other place in this application.
 *
 * It runs AFTER the display: the map view stands there immediately, and
 * when the service answers, it gets refined. No waiting, no flickering,
 * and without network it simply stays with the first result.
 */
let placeLookupRun = 0;
async function lookUpPlace(text, hit) {
    if (!hit?.completed || !hit.place) return;

    const cursor = ++placeLookupRun;
    const place = await findPlace(hit.place);
    // Typed on in the meantime? Then the answer belongs to a question from
    // earlier.
    if (!place || cursor !== placeLookupRun) return;
    if (coordField.value !== text) return;

    const better = recognizeCoordinates(text, { lat: place.lat, lon: place.lon });
    if (better) jumpToSpot(better.lat, better.lon);
}

// ---------------------------------------------------------------------------
// The crosshair
//
// WHAT THE SEARCH FINDS IS NOT A POINT. It is a spot — nothing saved yet,
// only a place one is currently looking at. That is why it carries none of
// the shapes that denote a find: a circle or a little flag there would
// mean something already exists.
//
// THE OUTLINE IS THE SAME as on the selected point — two circles, a dark
// halo underneath. It is this map's way of saying "this here is what I
// mean right now", and that should look the same for both. Only the
// content changes: instead of a shape, the four strokes pointing at the
// center.
//
// THE COLOR COMES FROM :root and does not stand here a second time. A
// second color value in the script would be exactly the place where a
// future theme change gets stuck.
// ---------------------------------------------------------------------------

let crosshair = null;

function crosshairSymbol() {
    const color = getComputedStyle(document.documentElement)
        .getPropertyValue('--accent').trim() || '#6aa84f';

    // Ring as on the selected point (r 14.5), plus four strokes with a gap
    // in the middle. The gap IS the statement — a dot in it would at this
    // size be a blot and cover exactly what it is meant to show.
    const geo = '<circle cx="12" cy="12" r="14.5"/>'
              + '<path d="M12 -4V7M12 17V28M-4 12H7M17 12H28"/>';

    // THE SIZE EXCEPTIONALLY STANDS IN THE SVG HERE, against this
    // application's rule: it feeds into iconSize and iconAnchor below,
    // with which Leaflet aligns the marker over its coordinate. If it came
    // from the stylesheet, it would run away from the anchor arithmetic —
    // the marker would sit next to the spot it is meant to show.
    return L.divIcon({
        className: 'crosshair',
        html: '<svg viewBox="-5 -5 34 34" width="40" height="40" fill="none" stroke-linecap="round">'
            + `<g stroke="rgba(0,0,0,.55)" stroke-width="4.5">${geo}</g>`
            + `<g stroke="${color}" stroke-width="2.5">${geo}</g>`
            + '</svg>',
        iconSize:   [40, 40],
        iconAnchor: [20, 20],
    });
}

/* --- Centring on what one SEES, not on the map box ---------------------
   17.09.2026: „Wenn ich aus der linken Seitenleiste einen Punkt
   anklicke, öffnet sich rechts das Punktfenster — aber der Punkt zentriert
   sich in der Karte, ohne die rechte Seitenleiste zu beachten. Manche
   Punkte liegen dann unter ihr." The map box does not shrink when the
   window opens; the window lies over its right edge (map.css, .detail),
   which is why the controls and the centred button pair step aside by its
   width. A view centred on the box therefore puts the point under the
   window as soon as the visible strip is narrow. These take the covered
   width out of the middle — the window with its grip while open, the grip
   alone while folded, nothing on the phone (there the window covers
   everything, and the peek stands in for it) or while closed. Read from
   the two variables, not from the window's box: its width is in
   transition for two hundred milliseconds after every opening. */
function coveredRight() {
    if (isPhone() || !document.querySelector('.app').classList.contains('detail-open')) return 0;
    const root = getComputedStyle(document.documentElement);
    const rem  = parseFloat(root.fontSize) || 16;
    const px   = (raw, fallback) => {
        const v = String(raw).trim();
        if (v.endsWith('rem')) return parseFloat(v) * rem;
        if (v.endsWith('px'))  return parseFloat(v);
        return fallback;
    };
    const grip = px(root.getPropertyValue('--grip-width'), 1.25 * rem);
    if (document.body.classList.contains('detail-collapsed')) return grip;
    return px(root.getPropertyValue('--detail-width'), 340) + grip;
}

/** setView, with the spot in the middle of the visible map. */
function centreVisible(latlng, zoom) {
    const covered = coveredRight();
    if (covered === 0) {
        map.setView(latlng, zoom);
        return;
    }
    const middle = map.project(L.latLng(latlng), zoom).add(L.point(covered / 2, 0));
    map.setView(map.unproject(middle, zoom), zoom);
}

/** fitBounds, with the covered strip kept free. */
function fitVisible(bounds) {
    map.fitBounds(bounds, { paddingTopLeft: [40, 40], paddingBottomRight: [40 + coveredRight(), 40] });
}

/** Jump to the spot and mark it. */
function jumpToSpot(lat, lon, zoom = 17) {
    // A deliberate jump elsewhere — the location must not pull the map
    // back (see pauseFollowing).
    pauseFollowing();
    centreVisible([lat, lon], Math.max(map.getZoom(), zoom));

    if (crosshair === null) {
        // Not clickable: it often lies exactly where a point is about to be
        // set — and a target that intercepts the click would then be in the
        // way.
        crosshair = L.marker([lat, lon], {
            icon: crosshairSymbol(), interactive: false, zIndexOffset: 500,
        }).addTo(map);
    } else {
        crosshair.setLatLng([lat, lon]);
    }

    // The button now says something else: it aims at this spot.
    drawPointButton();
    if (isPhone()) setSidebar(true);
}

function removeCrosshair() {
    if (crosshair === null) return;
    crosshair.remove();
    crosshair = null;
    drawPointButton();
}

/**
 * The searched-for spot, provided it is still on screen.
 *
 * THE VISIBILITY CHECK IS NOT COSMETICS. "Punkt hier" must point at
 * something you see — otherwise a press creates the point somewhere you
 * were three map movements ago. The same rule holds for one's own
 * location, and for the same reason.
 */
function crosshairSpot() {
    if (crosshair === null) return null;
    const where = crosshair.getLatLng();
    return map.getBounds().contains(where) ? { lat: where.lat, lon: where.lng } : null;
}

function showCoordMatches(text) {
    // THE MAP CENTER AS REFERENCE — only a short Plus Code needs it, and
    // only until the place name has been looked up.
    const center = map.getCenter();
    const hits = recognizeCoordinates(text, { lat: center.lat, lon: center.lng });

    if (!hits) {
        // NO COORDINATE — then it is probably a place. "München",
        // "Wiesbaden", "Krün": the browser cannot know that by itself, it
        // takes a gazetteer, and that is millions of names.
        //
        // Only what was typed here goes out. No point, no species, no id —
        // the same kind of statement as when completing a Plus Code, and
        // the same service.
        //
        // A name cannot be jumped to as long as nobody knows where it
        // lies. That is why the hit list stays here: there is a dozen of
        // "Neustadt", and silently taking the first would mean sending
        // somebody to a spot they did not mean.
        triggerPlaceSearch(text);
        return;
    }

    // A COORDINATE IS UNAMBIGUOUS — so go there immediately. It is already
    // fixed as soon as it is readable; showing a list first and then
    // waiting for a button would mean two moves for an answer that leaves
    // no choice open.
    coordResults.hidden = true;
    coordResults.replaceChildren();
    jumpToSpot(hits.lat, hits.lon);

    // A short Plus Code gets refined via the place name. The crosshair then
    // moves after — usually by a few kilometers.
    lookUpPlace(text, hits);
}

/**
 * Place search — delayed, so not every keystroke goes out.
 *
 * 600 ms after the last character. Whoever types "Wiesbaden" thereby
 * triggers one request instead of nine. In addition, placesearch.js lets at
 * most one request per second through and remembers what has already been
 * asked.
 */
let placeSearchTimer = null;
let placeSearchRun = 0;

function triggerPlaceSearch(text) {
    clearTimeout(placeSearchTimer);
    const query = text.trim();

    if (query.length < 3) {
        coordResults.hidden = true;
        coordResults.replaceChildren();
        // Clearing the field arrives here too. Then the search is over, and
        // so is its mark.
        if (query === '') removeCrosshair();
        return;
    }

    // Write something down while the search runs: a field that does nothing
    // for a second after typing looks broken.
    coordResults.replaceChildren();
    const waiting = document.createElement('div');
    waiting.className = 'hint-text small';
    waiting.textContent = navigator.onLine ? t('Suche …') : t('Ortssuche braucht Netz.');
    coordResults.append(waiting);
    coordResults.hidden = false;
    if (!navigator.onLine) return;

    const cursor = ++placeSearchRun;
    placeSearchTimer = setTimeout(async () => {
        const places = await findPlaces(query, 5);
        // Typed on in the meantime? Then the answer belongs to a question
        // from earlier.
        if (cursor !== placeSearchRun || coordField.value.trim() !== query) return;
        paintPlaceList(places);
    }, 600);
}

function paintPlaceList(places) {
    coordResults.replaceChildren();

    if (places.length === 0) {
        const nothing = document.createElement('div');
        nothing.className = 'hint-text small';
        nothing.textContent = t('Nichts gefunden.');
        coordResults.append(nothing);
        coordResults.hidden = false;
        return;
    }

    // SEVERAL TO CHOOSE FROM. There is a dozen of "Neustadt"; silently
    // taking the first would mean sending somebody to a spot they did not
    // mean — and not even showing them that there was a choice.
    const list = document.createElement('div');
    list.className = 'place-list';

    for (const place of places) {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'place-row';

        const name = document.createElement('span');
        name.className = 'place-row-name';
        name.textContent = place.short;

        const where = document.createElement('span');
        where.className = 'place-row-where';
        where.textContent = place.label;

        row.append(name, where);
        row.addEventListener('click', () => {
            // Level 13 instead of 17: a place name means the settlement,
            // not a square meter in it. Whoever wants closer zooms —
            // whoever lands at 17 sees three houses and does not know
            // where they are.
            jumpToSpot(place.lat, place.lon, 13);
            // The list has served its purpose. Leaving it standing would
            // mean covering half the map one just wanted to see.
            coordResults.hidden = true;
            coordResults.replaceChildren();
        });
        list.append(row);
    }

    coordResults.append(list);
    coordResults.hidden = false;
}

coordField.addEventListener('input', (e) => showCoordMatches(e.target.value));

bindClearButton(coordField, document.getElementById('coord-search-clear'));

/**
 * Fold the sidebar in and out.
 *
 * On wide screens it slides away to the side and frees the map; on narrow
 * ones it lies on top anyway and is only hidden. One state for both cases,
 * remembered via the settings — whoever works with the map in the woods
 * does not want to fold the sidebar shut anew on every visit.
 */
const sidebar = document.getElementById('sidebar');

/* remember=false is the START, not a choice: the opening default must
   not write itself into the bundle, or the guest's forced-shut sidebar
   would look like a decision on the next visit — and the phone's would
   follow the same person to the desk. Only a click stores. */
function setSidebar(collapsed, remember = true) {
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    sidebar.classList.toggle('is-open', !collapsed);
    /* THE HANDLE SAYS WHERE IT LEADS, not where it stands: ‹ takes the
       sidebar away, › brings it back. Here and not on its own click,
       because the ☰ and (on the phone) „Schließen" go through this
       function too — a handle that listens only to itself would point the
       wrong way after the next route. The same lesson as with the
       statistics button the day before. */
    const grip = document.getElementById('sidebar-grip');
    grip.textContent = collapsed ? '›' : '‹';
    grip.title = collapsed ? t('Seitenleiste ausklappen') : t('Seitenleiste einklappen');
    grip.setAttribute('aria-label', grip.title);
    grip.setAttribute('aria-expanded', String(!collapsed));
    if (remember) settings.set('sidebarClosed', collapsed);
    // Leaflet only recalculates its size on request; without this the map
    // stays at the old width after unfolding.
    setTimeout(() => map.invalidateSize(), 220);
}

/* --- The handle: dragging changes the width, clicking folds ------------
 *
 * 09.09.2026: „rüste das Ziehen der Breite nach." The same
 * double role as with the statistics handle, and therefore deliberately
 * the same mechanics: the MOVEMENT tells them apart, not a second
 * control. Four pixels of travel separate the drag from the click —
 * below that it is the trembling of a hand.
 *
 * THE WIDTH SITS ON THE ROOT ELEMENT, because two rules read it: the
 * page's grid and the move bar that begins beside the sidebar. A second
 * place would be the one that lags behind some day.
 *
 * DRAGGING THE COLLAPSED SIDEBAR BRINGS IT OUT — one gesture, not two.
 * The starting width is then the remembered one, not the single pixel
 * the collapsed column measures. */
const SIDEBAR_MIN = 240;

/* WHAT WAS LAST SET, and not what is being measured right now: the
   page's grid has a transition of .2s, and a drag that brings out the
   collapsed sidebar ends in the middle of that movement. Whoever reads
   offsetWidth there remembers an intermediate state — measured on
   09.09.2026, a 0 was stored and after reloading the sidebar stood at
   its default again. */
let sidebarWidthLast = null;

function sidebarWidthApply(px) {
    sidebarWidthLast = Math.round(px);
    document.documentElement.style.setProperty('--sidebar-width', sidebarWidthLast + 'px');
    // Leaflet only recalculates its size when told to.
    map.invalidateSize();
}

{
    const grip = document.getElementById('sidebar-grip');
    const stored = Number(settings.get('sidebarWidth', 0));
    if (stored >= SIDEBAR_MIN) sidebarWidthApply(stored);

    let drag = null;               // {startX, startWidth, moved}
    let swallowClick = false;

    grip.addEventListener('pointerdown', (e) => {
        const open = sidebar.offsetWidth;
        drag = {
            startX: e.clientX,
            startWidth: open > 40 ? open
                : (Number(settings.get('sidebarWidth', 0)) || 340),
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
            // Whoever drags the collapsed sidebar wants to see it.
            if (document.body.classList.contains('sidebar-collapsed')) setSidebar(false);
        }
        sidebarWidthApply(Math.min(Math.max(drag.startWidth + way, SIDEBAR_MIN),
            Math.min(640, window.innerWidth * 0.6)));
    });
    grip.addEventListener('pointerup', () => {
        if (drag === null) return;
        const wasDrag = drag.moved;
        drag = null;
        if (wasDrag) {
            if (sidebarWidthLast !== null) settings.set('sidebarWidth', sidebarWidthLast);
            swallowClick = true;
        }
    });
    grip.addEventListener('click', () => {
        // The click that trails a finished drag says nothing.
        if (swallowClick) { swallowClick = false; return; }
        setSidebar(!document.body.classList.contains('sidebar-collapsed'));
    });
}

document.getElementById('sidebar-expand').addEventListener('click', () => setSidebar(false));
document.getElementById('sidebar-close').addEventListener('click', () => setSidebar(true));

/* THE FIRST VISIT SHOWS THE MAP (24.08.2026): a guest who
   just joined landed in the list of his nought points, and on a phone
   the sidebar covers everything — one sees a list where one expected a
   map. Closed at the start therefore on the phone and for guests;
   at the desk the list keeps its place beside the map.

   ONLY WHERE NOBODY EVER CHOSE. The stored answer beats the default in
   both directions — that is the difference between "absent" and
   "false" the settings bundle is built for (see settings.forget). */
const sidebarStored = settings.get('sidebarClosed', null);
setSidebar(sidebarStored === null ? (GUEST || isPhone()) : sidebarStored === true,
           sidebarStored !== null);

// ---------------------------------------------------------------------------
// The personal chats: the speech bubble and its flyout
//
// TWO BUBBLES, ONE MEANING. One sits in the sidebar's corner, one floats
// next to the ☰ while the sidebar is collapsed — whichever is visible,
// both carry the same green dot and open the same flyout: one row per
// friend, "Chat mit …", the rows with something new marked. ALL friends
// stand in it, not only the conversations that already exist — the list
// is the one door on the map through which a NEW conversation can start
// (22.08.2026).
//
// The section only wires what the page rendered: without friends there
// are no bubbles, and everything here stays quiet.
// ---------------------------------------------------------------------------
(function bindFriendChat() {
    const bubbles = [
        document.getElementById('friend-chat-entry'),
        document.getElementById('friend-chat-entry-floating'),
        // The bar's at the desk (10.10.2026) — the floating one hides there.
        document.getElementById('friend-chat-entry-bar'),
    ].filter(Boolean);
    const flyout = document.getElementById('friend-chat-flyout');
    if (bubbles.length === 0 || !flyout) return;

    const friends = start.friends ?? [];

    function drawRows() {
        flyout.replaceChildren(...friends.map((f) => {
            const rowButton = document.createElement('button');
            rowButton.type = 'button';
            rowButton.className = 'chat-flyout-row';
            const waiting = chat.friendUnreadFor(f.id) > 0;
            // The row says which kind is waiting, as the sidebar's bubble does.
            const written = chat.friendPersonalFor(f.id) > 0;
            rowButton.classList.toggle('has-unread', written);
            rowButton.classList.toggle('has-status', waiting && !written);
            rowButton.title = waiting ? t('Chat mit %s — Neues da', f.name) : t('Chat mit %s', f.name);
            rowButton.append(icon('speech-bubble'), document.createTextNode(t('Chat mit %s', f.name)));
            rowButton.addEventListener('click', () => {
                closeFlyout();
                chat.openFriendChat(f, start.user?.id);
            });
            return rowButton;
        }));
    }

    function closeFlyout() {
        flyout.hidden = true;
        for (const b of bubbles) b.setAttribute('aria-expanded', 'false');
    }

    /* UNDER THE BUBBLE THAT OPENED IT (13.09.2026: „die
       Benutzer klappen nicht an der Sprechblase auf, sondern über der
       Sidebar — ein Überbleibsel aus der Zeit, als die Sprechblase in
       der Sidebar war"). The stylesheet pinned the list to the window's
       top-left corner, where the bubble once stood; since it floats in
       the MAP's corner, that corner lies beyond the open sidebar. So the
       list takes its place from the bubble's own box at the moment of
       opening, kept inside the window on the right. */
    function placeFlyout(bubble) {
        const box = bubble.getBoundingClientRect();
        flyout.style.top = `${Math.round(box.bottom + 6)}px`;
        const width = flyout.offsetWidth;
        const left = Math.min(box.left, window.innerWidth - width - 8);
        flyout.style.left = `${Math.round(Math.max(8, left))}px`;
    }

    function toggleFlyout(bubble) {
        const opening = flyout.hidden;
        if (opening) drawRows();
        flyout.hidden = !opening;
        if (opening) placeFlyout(bubble);
        for (const b of bubbles) b.setAttribute('aria-expanded', opening ? 'true' : 'false');
    }

    for (const b of bubbles) {
        b.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleFlyout(b);
        });
    }
    // A window that changes shape moves the bubble; the list closes
    // rather than staying where the bubble was.
    window.addEventListener('resize', closeFlyout);

    // A tap anywhere else closes the list — the same manners as every
    // small popover: it is a menu, not a window.
    document.addEventListener('click', (e) => {
        if (!flyout.hidden && !flyout.contains(e.target)) closeFlyout();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !flyout.hidden) closeFlyout();
    });

    // The dot on the bubbles — and the rows, when the list happens to be
    // open while the quiet poll brings something: the marks must not wait
    // for the next opening.
    chat.onUnreadChange(() => {
        const any = chat.friendUnreadAny();
        for (const b of bubbles) b.classList.toggle('has-unread', any);
        if (!flyout.hidden) drawRows();
    });
    const any = chat.friendUnreadAny();
    for (const b of bubbles) b.classList.toggle('has-unread', any);
})();

// ---------------------------------------------------------------------------
// The sidebar's two pages: lists and points
//
// TWO WORK STEPS, NOT TWO COLUMNS. First you choose WHAT you want to see,
// then you search within it. Placed side by side, that costs half the
// width for something you are not currently looking at; stacked, it caps
// both pages at half height — and exactly that left the footer with
// "Verwaltung" in the middle of the view.
//
// THE TAB CARRIES THE NUMBER. Whoever checks a list sees the number jump
// over there; that is the hint that something waits there. A button "8
// Punkte ansehen" below the list would do the same but be a second footer
// — space permanently gone, precisely where you scroll through fifty
// lists.
// ---------------------------------------------------------------------------

const sidebarTabs = {
    lists:     document.getElementById('sidebar-tab-lists'),
    points:     document.getElementById('sidebar-tab-points'),
    count:       document.getElementById('sidebar-tab-count'),
    listCount: document.getElementById('sidebar-tab-list-count'),
    track:       document.getElementById('sidebar-track'),
};

function sidebarPage(name, focus = true) {
    sidebar.dataset.page = name;
    settings.set('sidebarPage', name);
    for (const [which, button] of [['lists', sidebarTabs.lists], ['points', sidebarTabs.points]]) {
        const on = which === name;
        button.setAttribute('aria-selected', on ? 'true' : 'false');
        button.tabIndex = on ? 0 : -1;
    }
    // sidebarTabs[name] works again: page name and object key say the same
    // word since the page is called 'points' — the mismatch that once made
    // this line throw is gone.
    if (focus) sidebarTabs[name].focus();
}

for (const [name, button] of [['lists', sidebarTabs.lists], ['points', sidebarTabs.points]]) {
    button.addEventListener('click', () => sidebarPage(name));
    button.addEventListener('keydown', (e) => {
        if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
        e.preventDefault();
        sidebarPage(name === 'lists' ? 'points' : 'lists');
    });
}

/**
 * Keep the number on the tab up to date.
 *
 * Flash only on a real change: it is set on every draw, and blinking on
 * every scroll or keystroke would be restlessness without a statement.
 */
const lastCounted = new Map();
function setTabCount(target, count) {
    if (!target) return;
    target.textContent = String(count);
    if (lastCounted.has(target) && lastCounted.get(target) !== count) {
        target.classList.remove('is-fresh');
        // Forces the transition timer to restart; without this a second
        // change in quick succession goes unnoticed.
        void target.offsetWidth;
        target.classList.add('is-fresh');
        setTimeout(() => target.classList.remove('is-fresh'), 700);
    }
    lastCounted.set(target, count);
}

/**
 * Swiping between the pages.
 *
 * HORIZONTAL ONLY AND CLEAR ONLY: the pages scroll vertically, and a swipe
 * that means both at once makes scrolling unusable. Only when the
 * horizontal movement clearly outweighs the vertical does the switch take
 * over — before that the gesture stays with the scroll area.
 */
(function bindSwipe() {
    const track = sidebarTabs.track;
    if (!track) return;

    let start = null;
    let direction = null;      // null = still undecided, 'x' or 'y'

    track.addEventListener('pointerdown', (e) => {
        if (e.pointerType === 'mouse') return;   // on the desktop there are the tabs
        start = { x: e.clientX, y: e.clientY };
        direction = null;
    });

    track.addEventListener('pointermove', (e) => {
        if (!start) return;
        const dx = e.clientX - start.x;
        const dy = e.clientY - start.y;

        if (direction === null) {
            if (Math.hypot(dx, dy) < 12) return;             // still too small
            direction = Math.abs(dx) > Math.abs(dy) * 1.5 ? 'x' : 'y';
            if (direction === 'x') sidebar.classList.add('swiping');
        }
        if (direction !== 'x') return;

        // Follow the finger, but not beyond the edges.
        const base = sidebar.dataset.page === 'points' ? -50 : 0;
        const share = Math.max(-50, Math.min(0, base + (dx / track.offsetWidth) * 100));
        track.style.transform = `translateX(${share}%)`;
        e.preventDefault();
    });

    const release = (e) => {
        if (!start) return;
        const dx = e.clientX - start.x;
        start = null;
        sidebar.classList.remove('swiping');
        track.style.transform = '';
        if (direction !== 'x') return;

        // A quarter of the width suffices — whoever pulls further has
        // decided, and a swipe across half the sidebar would be laborious.
        const threshold = track.offsetWidth / 8;
        if (dx < -threshold) sidebarPage('points', false);
        else if (dx > threshold) sidebarPage('lists', false);
    };
    track.addEventListener('pointerup', release);
    track.addEventListener('pointercancel', release);
})();

// 'punkte' is the value the setting carried before the move to English —
// it may still sit in the bundle of a device that has not visited since.
sidebarPage(['points', 'punkte'].includes(settings.get('sidebarPage', 'lists')) ? 'points' : 'lists', false);

if (GUEST) {
    // The lists page is hidden (CSS via body.guest-access) — the guest
    // starts, and stays, on his finds.
    sidebarPage('points', false);
    // The window's list block would offer him locked rooms.
    // The list block: hidden here for the window that may already stand
    // open, and again at every opening — see the GUEST condition there.
    document.getElementById('d-lists').closest('.field').hidden = true;

    /* NEITHER COORDINATES NOR FIND TIME (24.08.2026): both are
       corrections a registered person may want — a spot mistyped from a
       GPS device, a find entered a week late. A guest stands IN the woods
       AT the find while the excursion runs: his coordinates come from the
       tap or from the location, his time from the clock, and both are
       right. What he cannot mistype, he needs no field for.

       The spot itself stays movable on the map (the marker's handles),
       for that is a gesture in place, not a number typed in. */
    for (const id of ['d-coord', 'd-found']) {
        document.getElementById(id)?.closest('.field')?.setAttribute('hidden', '');
    }
    // Where he is and how long the door stays open — once, at arrival.
    // DEFERRED past module setup: the status bar's own consts stand
    // further down, and a direct call from up here died in their TDZ and
    // took the whole module with it — the map looked wired and was dead.
    setTimeout(() => status(excursionAhead(start.guest.starts_at)
        ? t('Exkursion „%s“ — als %s, beginnt am %s.', start.guest.excursion, start.user.name, start.guest.from)
        : t('Exkursion „%s“ — als %s, läuft bis %s.', start.guest.excursion, start.user.name, start.guest.until)), 0);

    /* THE GUEST'S SETTINGS WINDOW (24.08.2026): appearance —
       the same tiles and the same wiring as the settings tab
       (appearance.js) — and who he is on this excursion. Everything else
       of the administration stays closed to him.

       The person form saves through api/v1/excursion_guest.php, and the
       server carries a rename over to the finds already made: the name
       sits in each point's Finder field, and a correction that reached
       only future finds would leave the day's harvest under the old
       spelling. */
    (async () => {
        const { wireAppearance } = await import('./appearance.js');
        wireAppearance({
            themeDefault:  start.themeDefault ?? 'petrol',
            accentDefault: start.accentDefault ?? 'lime',
        });
    })();

    const guestWindow = document.getElementById('guest-settings');
    document.getElementById('guest-settings-open')?.addEventListener('click', async () => {
        try {
            const who = await request('excursion_guest.php');
            document.getElementById('guest-set-first').value = who.first_name ?? '';
            document.getElementById('guest-set-last').value  = who.last_name ?? '';
            document.getElementById('guest-set-email').value = who.email ?? '';
        } catch {
            // Without a network the appearance still works — and that is
            // the half one is most likely to want in the woods.
        }
        guestWindow.showModal();
    });
    /* THE FIELD LETS GO BEFORE THE WINDOW DOES (24.08.2026:
       „dieses ‚Adresse auswählen' bleibt dauerhaft stehen, als ob das
       Inputfeld noch versteckt aktiv wäre" — it is exactly that). A
       dialog that closes while an input still holds the focus leaves the
       keyboard and its autofill bar standing on Android: the element is
       hidden, the focus is not. Blurring first ends both. */
    const closeGuestWindow = () => {
        document.activeElement?.blur?.();
        guestWindow.close();
    };
    document.getElementById('guest-settings-close')?.addEventListener('click', closeGuestWindow);
    // Escape and the backdrop reach the same door.
    guestWindow?.addEventListener('cancel', () => document.activeElement?.blur?.());

    /* THE REFUSAL GETS A VOICE (24.08.2026: he cleared the
       name, could not save, and saw nothing). The browser blocks by
       itself — the fields are required — but its bubble is invisible in
       a full-screen dialog. So the check happens on the CLICK, before
       the native one, and says in the window what is missing. Cleared
       again on the next attempt and after a save: a reason that outlives
       its cause is worse than none. */
    const personError = document.getElementById('guest-person-error');
    const sayWhy = (text) => {
        if (!personError) return;
        personError.textContent = text ?? '';
        personError.hidden = !text;
    };
    guestWindow?.querySelector('.dialog-footer button[type="submit"]')
        ?.addEventListener('click', (e) => {
            const form = document.getElementById('guest-person');
            sayWhy('');
            if (form.checkValidity()) return;
            e.preventDefault();
            const bad = form.querySelector(':invalid');
            bad?.focus();
            bad?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            sayWhy(bad?.type === 'email'
                ? t('Die E-Mail-Adresse ist nicht lesbar — oder lass sie ganz weg.')
                : t('Vor- und Nachname sind Pflicht: Der Name steht als Finder an deinen Funden.'));
        });

    document.getElementById('guest-person')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        sayWhy('');
        /* The button stands in the FOOTER, outside the form (see the rule
           in CLAUDE.md) — e.submitter is the one that pressed, and
           querySelector inside the form finds nothing at all. */
        const button = e.submitter
            ?? guestWindow.querySelector('.dialog-footer button[type="submit"]');
        if (button) button.disabled = true;
        try {
            /* JSON.stringify HERE, not in request(): that one hands its
               body straight to fetch — an object would arrive as
               "[object Object]", the server would read nothing and answer
               „Vor- und Nachname sind Pflicht" for fields that were
               filled (measured). Every other caller in this file does it
               the same way. */
            const answer = await request('excursion_guest.php', {
                method: 'PATCH',
                body: JSON.stringify({
                    first_name: document.getElementById('guest-set-first').value,
                    last_name:  document.getElementById('guest-set-last').value,
                    email:      document.getElementById('guest-set-email').value,
                }),
            });
            // Through the same door as the close button — with the blur,
            // or the autofill bar outlives the window (see above).
            closeGuestWindow();
            status(answer.renamed > 0
                ? (answer.renamed === 1
                    ? t('Gespeichert — der Name steht jetzt auch an deinem bisherigen Fund.')
                    : t('Gespeichert — der Name steht jetzt auch an deinen %d bisherigen Funden.', answer.renamed))
                : t('Gespeichert.'));
            drawList();
        } catch (error) {
            /* IN THE WINDOW, not on the status bar behind it: the bar
               lies over the MAP, and a full-screen dialog covers it whole
               — the answer to a refused save would have gone to a place
               nobody can see. The server's own sentence, unchanged: it
               names the e-mail that is taken, and that is the only thing
               worth reading here. */
            sayWhy(error?.message ?? t('Speichern fehlgeschlagen.'));
        } finally {
            if (button) button.disabled = false;
        }
    });

    /* THE NAME LISTS, RIGHT AWAY (24.08.2026: on his phone
       the first typed name got no suggestions). For a registered person
       they are fetched on first focus of a species field, and rightly
       so — whoever maps parking spots should not download 560 kB of
       fungus names. A GUEST is here for exactly one purpose, and it is
       certain from the first second: better now, over the reception at
       the car park, than later under the trees.

       The two PERMISSIONS are not asked here: they belong to a click and
       have their own explained pages before the map (onboarding.php). */
    setTimeout(getSpeciesList, 1200);
}

/* THE HANDOVER FROM THE ONBOARDING'S MAP STEP: „?offline=aerial_official"
   or „…=osm" — the leader's area onto this phone, over the
   reception at the car park. The download itself is the one the
   Offline tab runs (getAreaTiles): the tile addresses are built by
   the layer machinery above, which knows which service draws at
   which zoom in which region, and a second copy of that on the
   onboarding page would be the twin that drifts.

   OUTSIDE THE GUEST BLOCK SINCE 09.09.2026, and that was the whole of
   it: the block sat inside, so a REGISTERED participant could be handed
   the link and the map would quietly do nothing with it. He is not a
   guest — his finds stay his own — so his copy of the area hangs on
   'mapping' instead (index.php). Same offer, same download, two keys. */
const excursionArea = start.guest?.area ?? start.mapping?.area;
const wanted = new URLSearchParams(location.search).get('offline');
if (wanted && excursionArea) {
    // The address must not survive a reload — otherwise every return
    // to the map would start the download anew.
    history.replaceState(null, '', location.pathname);
    setTimeout(() => {
        const area = excursionArea;
        /* THE LAYER LIST IS KEYED BY THE DISPLAY NAME ('Luftbild'),
           the handover speaks the registry KEY ('aerial_official') —
           they are two vocabularies, and matching the wrong one
           silently found nothing twice while I built this. The
           registry (start.layers) is the translator between them. */
        const named = (start.layers ?? []).find((l) => l.id === wanted)?.name;
        const entry = collectLayers().find((e) => e.name === named);
        // 'notLoadable' is the field collectLayers() writes — the
        // volunteer service (OpenStreetMap's raster on the Leaflet map)
        // carries a reason there and must never be prefetched; so does
        // the vector base map as long as the Europe file is missing.
        if (!entry || entry.notLoadable) {
            status(t('Diese Kartenart lässt sich nicht auf Vorrat laden.'), true);
            return;
        }
        status(t('„%s" wird geladen — bleib solange in Empfang.', area.name));
        // The excursion's level — 18 as the fallback, the same
        // recommendation the leader's form makes (excursions.php).
        const level = area.zoom || 18;
        /* THE LEVEL BELONGS ON RECORD, and the load window taught me
           so only by its absence: it writes zoom_to itself, this way
           in never did, and afterwards nothing on the device knew how
           far the tiles reach — the offline zoom cap read nothing and
           capped nothing (measured right after building it). */
        areas.writeDeviceState(area.id, {
            ...(areas.readDeviceState(area.id) ?? {}),
            zoom_to: level,
            /* BORROWED, NOT OWNED, and the record has to say so: this
               area stands in the leader's directory, not in this
               device's owner's — without the mark the next startup
               sweep throws the tiles out as orphans (evictOrphanedTiles
               explains the rest). The loan runs to the excursion's last
               day and then expires like any other stock. */
            borrowed: true,
            valid_until: area.held_until,
        });
        getAreaTiles(area, level, null, [entry]);
    }, 2000);
}

// ---------------------------------------------------------------------------
// Setting points
// ---------------------------------------------------------------------------

const setHint = document.getElementById('set-hint');
const pointButton    = document.getElementById('tool-point-set');
const pointButtonText = pointButton.querySelector('.tool-text');

function setPointMode(on) {
    state.placing = on;
    setHint.hidden = !on;
    pointButton.classList.toggle('is-active', on);
    document.getElementById('map').classList.toggle('sets-point', on);
}

/**
 * The button says beforehand what it will do.
 *
 * THREE STATES, IN THIS ORDER:
 *
 *   CROSSHAIR  It only stands there because somebody just searched for it.
 *              That is the most recent and most explicit intention — it
 *              takes precedence.
 *   LOCATION   You walked there; that too is an intention, only an older
 *              one.
 *   OTHERWISE  The usual way: arm, tap into the map.
 *
 * BOTH TARGETS MUST BE ON SCREEN. A "here" that points at something
 * outside the viewport creates the point somewhere you were three map
 * movements ago. Because the button is rewritten on every 'moveend', the
 * label changes by itself when panning away.
 */
function drawPointButton() {
    if (state.placing) return;

    const spot = crosshairSpot();
    const here    = spot ? null : locationForPoint();

    pointButtonText.textContent = (spot || here) ? t('Punkt hier') : t('Punkt setzen');
    pointButton.title = (spot
        ? t('Punkt an der gesuchten Stelle anlegen')
        : here
            ? t('Punkt an deinem Standort anlegen')
            : t('Punkt setzen — danach in die Karte tippen'))
        + (outlinesPossible ? ' · ' + t('lange drücken für eine Fläche') : '');
}

// ---------------------------------------------------------------------------
// Long press: an area instead of a point
//
// WHY ON THIS BUTTON. An area is not another tool, it is the same thing in
// large: the same procedure, the same list choice, the same point
// afterwards. A second button next to it would tear that apart and narrow
// the bar in the thumb zone, where space is scarce anyway.
//
// THE CLICK AFTERWARDS MUST GO. After a long press the click event still
// arrives — without the lock below, the same press would first create the
// area and then toggle set mode.
// ---------------------------------------------------------------------------

const LONG_PRESS_MS = 550;

let longPressTimer = null;
let longPressGrabbed = false;   // the long press has taken over, the click no longer counts

function cancelLongPress() {
    if (longPressTimer !== null) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
    }
}

pointButton.addEventListener('pointerdown', (e) => {
    // Only the main button; a right click on the button is something else.
    if (e.button !== 0) return;
    if (!outlinesPossible || state.placing) return;

    longPressGrabbed = false;
    cancelLongPress();
    longPressTimer = setTimeout(() => {
        longPressTimer = null;
        longPressGrabbed = true;
        // On the phone the only feedback that the long press has arrived —
        // you cannot see the button under your thumb.
        navigator.vibrate?.(20);
        beginPolygonOutline();
    }, LONG_PRESS_MS);
});

for (const type of ['pointerup', 'pointercancel', 'pointerleave']) {
    pointButton.addEventListener(type, cancelLongPress);
}

// Android otherwise shows its own selection menu over it on a long press.
pointButton.addEventListener('contextmenu', (e) => e.preventDefault());

/** Is an excursion's window still ahead — by the device's clock, like every time here. */
function excursionAhead(startsAt) {
    const from = new Date(String(startsAt ?? '').replace(' ', 'T')).getTime();
    return Number.isFinite(from) && Date.now() < from;
}

pointButton.addEventListener('click', async () => {
    if (longPressGrabbed) { longPressGrabbed = false; return; }
    /* THE GUEST WHO CAME AHEAD OF THE WINDOW (13.09.2026) is here for the
       map, not for finds: the server would refuse the find by its time
       (excursion_point_add), and a refusal from the queue would come
       late and say less than this. */
    if (GUEST && excursionAhead(start.guest.starts_at)) {
        status(t('Die Exkursion beginnt am %s — bis dahin lässt sich hier nichts eintragen.', start.guest.from), true);
        return;
    }

    // The searched-for spot first — it is the most recent intention.
    //
    // CLEAN UP ONLY WHEN THE POINT REALLY STANDS THERE. createPoint()
    // first asks for the lists, and that prompt can be cancelled. Without
    // the await the crosshair vanished while the dialog still stood open —
    // and stayed gone although nothing was created at all.
    const spot = state.placing ? null : crosshairSpot();
    if (spot) {
        if (await createPoint(spot.lat, spot.lon, {},
                               t('Punkt an der gesuchten Stelle angelegt.'))) {
            showHeaderSearch(false);   // takes the crosshair with it
        }
        return;
    }

    const here = locationForPoint();
    if (here && !state.placing) {
        createPoint(here.lat, here.lon, {
            accuracy_m:       here.accuracy_m,
            elevation_ellipsoid_m:   here.elevation_ellipsoid_m,
            elevation_accuracy_m: here.elevation_accuracy_m,
        }, here.accuracy_m === null
            ? t('Punkt an deinem Standort angelegt.')
            : t('Punkt an deinem Standort angelegt — ±%s m.', here.accuracy_m)
              + locationHint(here.accuracy_m));
        return;
    }
    setPointMode(!state.placing);
});

/* The hint's „Abbrechen" is gone (index.php, 24.08.2026) — „Punkt
   setzen" itself toggles the mode off. Nothing to wire here any more;
   an addEventListener on the vanished element would have thrown at
   evaluation and taken everything below it with it. */

map.on('click', (e) => {
    /* THE TAIL OF A DRAG IS NOT A CLICK — see lastDragEnd. Guarded before
       BOTH branches on purpose: in placing mode the same stray click would
       set a point where somebody had just dragged a marker to. */
    if (Date.now() - lastDragEnd < DRAG_TAIL_MS) return;

    /* A CORNER IS NOT A WAY OUT OF THE WINDOW. While an outline is being
       traced, every tap on the map is a corner (setSketchCorner) — and
       this handler ran on the same tap: it used to clear the selection
       silently (the outline tool re-selected the point at „Fertig"), and
       since the window asks before dropping unsaved input, the first
       corner of a freshly typed find opened „Noch nicht gespeichert —
       Speichern / Verwerfen" over the map (21.09.2026: „nach
       dem ersten Tipp eine Meldung, ob ich die Seite verlassen will").
       The window keeps standing aside until the tracing ends. */
    if (sketchJob !== null) return;

    if (state.placing) {
        setPointMode(false);
        createPoint(e.latlng.lat, e.latlng.lng);
    } else if (state.selectedId !== null) {
        closeDetail();
    }
});

/* ONE WAY TO A POINT, EVERYWHERE (15.09.2026): the „Punkt
   setzen" button, then — unless the location follows — a click or tap on
   the map. No shortcut on the map itself, neither the finger's long press
   nor the mouse's right click: both fired when nobody meant a find, and
   a second path to the same thing is one more to explain. The browser's
   own context menu is kept off the map, so a right click does nothing
   visible instead of opening a menu over a marker. */
map.on('contextmenu', (e) => L.DomEvent.stop(e));

// ---------------------------------------------------------------------------
// Areas
//
// Not every find is a pin. A fairy ring, a stand on a slope, a searched
// sample plot — that has an outline, and boiling it down to a point throws
// away exactly the detail this is about.
//
// IT STILL REMAINS A POINT. The same lists, the same custom fields, the
// same voucher photos, the same map sheet, the same queue, the same
// trash. The outline comes in addition; lat/lon then point at a spot
// inside it. If an area were a thing of its own, all of that would have
// to be built a second time — and with the next addition a third time.
// ---------------------------------------------------------------------------

function beginPolygonOutline() {
    beginOutline({
        title:   t('Fläche umranden'),
        message: t('Fläche umranden: Ecke für Ecke antippen, dann „Fertig".'),
        done:    applyPolygon,
    });
}

/**
 * Make a point out of the finished outline.
 *
 * The title stays empty as with every new point — what grows here only the
 * person standing in front of it knows. The size is reported because it is
 * the one thing a freshly drawn area does not show and one wants to know
 * immediately: whether one was off by a factor of ten.
 */
function applyPolygon(corners) {
    const center = polygon.center(corners);
    if (!center) {
        endDrawing();
        status(t('Der Umriss taugt nicht.'), true);
        return;
    }

    endDrawing();
    createPoint(center[0], center[1], { outline: corners },
        t('Fläche angelegt — %s.', polygon.readableArea(polygon.areaM2(corners))));
}

/**
 * Redraw the outline of an existing point.
 *
 * An ordinary point becomes an area this way too. That is the usual path:
 * at the find you quickly set a point and only trace the stand once you
 * have walked it.
 *
 * THE SPOT MOVES ALONG. lat/lon are set to the center of the new outline —
 * and map sheet and terrain height hang on that. A point that kept its old
 * spot while its outline lies elsewhere would have a sheet nobody was
 * ever in.
 */
function redrawOutline(pointId) {
    beginOutline({
        title:   t('Umriss neu ziehen'),
        message: t('Ecke für Ecke antippen, dann „Fertig".'),
        done(corners) {
            const center = polygon.center(corners);
            endDrawing();
            if (!center) {
                status(t('Der Umriss taugt nicht.'), true);
                return;
            }

            // Foreign ones too: "Umriss neu ziehen" stands there only with
            // the move right (applyDetailRights), and as always it only
            // takes effect with "Speichern" — via saveForeign.
            const point = state.points.get(pointId) ?? state.sharedPoints.get(pointId);
            if (!point) { status(t('Den Punkt gibt es nicht mehr.'), true); return; }

            // Only into the fields, not into the database — as when moving
            // a marker. Only "Speichern" makes it valid, and whoever
            // closes the window finds the old outline.
            selectPoint(pointId);
            outlineDraft = corners;
            fields.coord.value = `${center[0].toFixed(6)}, ${center[1].toFixed(6)}`;
            drawOutlineRow(corners);
            // selectPoint() still drew with the saved outline — that was one
            // line too early.
            drawMarker();
            markUnsaved(true);
            status(t('Umriss gezogen — %s. Noch nicht gespeichert.',
                polygon.readableArea(polygon.areaM2(corners))));
        },
    });
}

/**
 * Rename the running tour — on every point that already carries it.
 *
 * THROUGH THE ORDINARY PATCH PATH, one point at a time: that is a handful
 * of requests for a handful of points, and in exchange the rename works in
 * a dead zone exactly like every other edit — queued, rewritten onto the
 * real id, synced to the other devices. A dedicated bulk endpoint would
 * have to relearn all of that. Restricted to the tour's own day: a custom
 * name may repeat across days ("Wetterau"), and last month's outing must
 * not be dragged along.
 */
function renameTour(active, newName) {
    // FROM the start day on, not only ON it: a night tour's points lie on
    // two calendar days (six-hour rule, tour.js), and the ones after
    // midnight must not keep the old name. Days before the start stay out
    // — a custom name may repeat across outings ("Wetterau"), and last
    // month's must not be dragged along.
    const affected = [...state.points.values()].filter(p =>
        (p.tour ?? '') === active.name && (timeRange.dayOf(p) ?? '') >= active.day);
    for (const p of affected) {
        p.tour = newName;
        api.patch('points', p.id, { tour: newName }).catch(() => {});
    }
    /* THE WALKS OF THAT TOUR TAKE THE NAME TOO (13.09.2026:
       „dass man beim Umbenennen der Tagestouren auch gleich die
       Wegstrecken-Verknüpfung mit anpackt"). The server does it for
       real when the patches land (point_update → track_tour_name_follow)
       and moves the bucket, so the store catches up on the next sync;
       this here is the same picture a minute early, and the honest one
       while the patches still wait in the queue. Matched by id, not by
       name: the name is exactly what is changing. */
    const tourIds = new Set(affected.map(p => p.tour_id).filter(Boolean));
    for (const w of state.tracks.values()) {
        if (w.tour_id && tourIds.has(w.tour_id)) w.tour = newName;
    }
    if (affected.length > 0) {
        drawList();
        status(affected.length === 1
            ? t('Tour umbenannt — 1 Punkt trägt jetzt „%s".', newName)
            : t('Tour umbenannt — %d Punkte tragen jetzt „%s".', affected.length, newName));
    }
}

/**
 * First step when creating: which tour, and where does the point belong?
 *
 * Previously it was filed silently when exactly one list happened to be
 * filtered, and otherwise not at all. That was convenient and wrong:
 * points landed under "Ohne Liste" and stayed there, because filing later
 * is something nobody does.
 *
 * Preselected is what is currently filtered — the old automatism thus
 * survives as a suggestion, only visible and changeable.
 *
 * THE TOUR SITS ON TOP (23.08.2026). The first point of the day
 * quietly begins one, named after the date; the name is editable, and
 * "Neue Tour" ends the running one mid-day — several outings on one day
 * are the point. Everything takes effect only with "Weiter": whoever
 * cancels has begun, renamed and ended nothing.
 *
 * @returns {Promise<{lists: number[], tour: string|null}|null>} null on cancel
 */
// Whether „Ohne Tour" was ticked the last time this visit (see the window below).
let tourNoneWish = false;

function selectLists() {
    /* A POINT MAY CARRY BOTH A TOUR AND AN EXCURSION, and that is not a
       leftover to be tidied away (the decision, 24.08.2026): the
       two orderings answer two different questions. The excursion row in
       Organisieren shows what EVERYBODY collected — guests, co-leaders,
       oneself —, the tour row only one's own finds. Whoever maps along
       therefore names a tour as usual; both labels travel on the point,
       the tour from this window, the excursion from the session. */

    // A guest has no lists and no tours — his find needs no basket (the
    // excursion reference comes from the session, server-side), and the
    // window would offer him an empty tree plus a tour name that is
    // dropped at the API's sift. Straight through instead.
    if (GUEST) return Promise.resolve({ lists: [], tour: null });

    const windowEl = document.getElementById('list-selection-window');
    const container = document.getElementById('list-selection-lists');
    // „Aufsammlung" — off at every new window, pressed for this point only.
    const asCollection = document.getElementById('point-as-collection');
    asCollection.setAttribute('aria-pressed', 'false');
    asCollection.classList.remove('is-active');
    asCollection.onclick = () => {
        const on = asCollection.getAttribute('aria-pressed') !== 'true';
        asCollection.setAttribute('aria-pressed', String(on));
        asCollection.classList.toggle('is-active', on);
    };
    const tourField = document.getElementById('tour-name');
    const tourNewButton = document.getElementById('tour-new');
    const tourHint = document.getElementById('tour-hint');

    // The same tree as in the point window and the sidebar. Preselected is
    // what is currently filtered.
    // The joint lists come along (22.09.2026) — preselected when switched on under „Geteilt".
    /* FIRST ONLY THE TICKED ONES (12.10.2026: „ich würde nur die
       Listen anzeigen mit einem Haken und dann einen Button ändern"). On a
       fungi day the window shows „Pilze" alone, and „Weiter" is the whole
       step. „Listen ändern" opens the full tree, carrying over the ticks
       as they stand now — one taken out in the short view stays out.
       Nothing ticked: there is nothing to shorten, the full tree comes
       straight away. */
    const preselected = (list) => state.visible.has(list.id) || state.visibleShared.has(list.id);
    const more = document.getElementById('list-selection-more');
    // The row, not the button, so no empty gap stays once it is pressed.
    const moreRow = document.getElementById('list-selection-more-row');
    const drawFull = (checked) => {
        drawListSelection(container, { checked, joint: true });
        moreRow.hidden = true;
    };
    drawListSelection(container, { checked: preselected, joint: true, skip: (list) => !preselected(list) });
    if (container.querySelector('input[type="checkbox"]') === null) {
        drawFull(preselected);
    } else {
        moreRow.hidden = false;
        more.onclick = () => {
            const ticked = new Set([...container.querySelectorAll('input:checked')].map(b => Number(b.value)));
            drawFull((list) => ticked.has(list.id));
        };
    }

    /* Names already standing on points — the counter for "…(2)" reads
       them, so a second outing never silently merges into a finished one.
       The names REGISTERED in Organisieren count as taken too (start.tourNames,
       migration 0073): an empty tour is a name somebody has chosen, and
       the counter must not hand it out a second time. */
    const takenNames = () => [
        ...[...state.points.values()].map(p => (p.tour ?? '').trim()).filter(Boolean),
        ...(start.tourNames ?? []),
    ];

    /* WHAT IS TAKEN TODAY — and that is the counter's whole world since
       27.08.2026 („Nur wenn man an einem Tag eine zweite Tour
       macht, sollte man das im Namen dann kennzeichnen"). Counted against
       the full stock, the second outing of the year became „Tagestour #2"
       and the seventh „#7" — numbers that answered a question nobody had.

       THE REGISTERED EMPTY NAMES STAY OUT (start.tourNames, migration
       0073). They carry no day — an empty tour has no points to date it —
       so they cannot say whether they belong to today. They remain in
       takenNames() above, which is what the picker and the hint read. */
    const takenToday = () => {
        const day = tour.today();
        return [...state.points.values()]
            .filter(p => timeRange.dayOf(p) === day)
            .map(p => (p.tour ?? '').trim())
            .filter(Boolean);
    };

    /* The picker: every known name, most recent outings first, the
       registered ones after them. Without this the empty tour created in
       Organisieren would be unreachable in the field — which is the one place
       it was created FOR. */
    const suggestions = document.getElementById('tour-names');
    if (suggestions) {
        suggestions.textContent = '';
        /* THE LABEL, NOT THE KEY. This list fills a text field somebody
           types in — a „t:9f3c…" offered here would be a suggestion that
           inserts machine noise into the tour's name. The rows have said
           key and label apart since the grouping follows the id. */
        const seen = new Set();
        for (const row of timeRange.collectTours([...state.points.values()])) {
            const name = row.tour ? (row.label ?? row.tour) : '';
            if (name === '' || seen.has(name)) continue;
            seen.add(name);
        }
        for (const name of [...seen, ...(start.tourNames ?? [])]) {
            if (name === '') continue;
            const option = document.createElement('option');
            option.value = name;
            suggestions.append(option);
        }
    }

    const active = tour.activeTour();
    // Without a running tour this confirm begins one — editing the proposed
    // name is then naming, not renaming.
    let fresh = active === null;

    /* A NAME OF TODAY CONTINUES TODAY'S TOUR (18.09.2026: he
       had recorded „Tagestour 2" with its track, ended it, later set a
       point and chose that name from the list — „der Punkt ist nicht bei
       der Tour dabei"). Since migration 0079 a fresh tour with a familiar
       name stayed its own: right for „Tagestour" on every new day, wrong
       on the SAME day — whoever picks a name that already stands on
       today's points means that outing, not a twin of it. So without a
       running tour, and without „Neue Tour" pressed, a name of today
       rejoins its id and becomes the running tour again; „Neue Tour"
       still mints. Yesterday's names stay strangers: a point of today
       does not belong in yesterday's outing. */
    const todaysTours = () => {
        const day = tour.today();
        const out = new Map();
        for (const p of state.points.values()) {
            const name = (p.tour ?? '').trim();
            if (name !== '' && p.tour_id && timeRange.dayOf(p) === day) {
                out.set(name, { id: String(p.tour_id), day });
            }
        }
        return out;
    };
    tourField.value = active ? active.name : tour.nextName(tour.today(), takenToday());
    const tourHintText = () => active
        ? t('Läuft — Umbenennen gilt für alle Punkte dieser Tour.')
        : t('Beginnt mit diesem Punkt.');
    tourHint.textContent = tourHintText();

    /* OHNE TOUR (22.09.2026: „ein Gebiet oder eine Brandfläche
       in einer Tourliste verwirrt mich — vielleicht ein Haken ohne Tour").
       Ticked, the field is switched off and the point goes out without a
       tour; the running tour is not touched, it simply runs on without
       this point. The wish is kept for the next window of THIS visit
       (tourNoneWish) — an evening of area entries should not mean ticking
       it ten times — and forgotten with the reload: remembered across
       days it would strip tomorrow's finds of their tour unnoticed. */
    const tourNone = document.getElementById('tour-none');
    const tourFieldBox = tourNone.closest('.tour-field');
    /* A RUNNING TOUR SHOWS ITSELF (22.09.2026): the name field
       wears the accent hairline of a chosen row, and „Neue Tour" is the
       one live button here — it ends this outing — so it wears the
       accent too. Without a running tour, or once „Neue Tour" was
       pressed (fresh), both fall back to quiet: the window then begins
       a tour by itself, and the button would only mint another name. */
    const applyTourNone = () => {
        const on = tourNoneWish;
        const running = !on && active !== null && !fresh;
        tourNone.classList.toggle('is-active', on);
        tourNone.setAttribute('aria-pressed', on ? 'true' : 'false');
        tourFieldBox.classList.toggle('is-tourless', on);
        tourFieldBox.classList.toggle('is-running', running);
        tourNewButton.classList.toggle('is-active', running);
        tourField.disabled = on;
        tourNewButton.disabled = on;
        tourHint.textContent = on
            ? t('Der Punkt gehört zu keiner Tour — die laufende Tour läuft ohne ihn weiter.')
            : tourHintText();
    };
    applyTourNone();
    tourNone.onclick = () => {
        tourNoneWish = !tourNoneWish;
        applyTourNone();
    };

    return new Promise((done) => {
        /* „NEUE TOUR" MINTS AN IDENTITY (migration 0079). Since then the
           name is a pure label: two outings may be called „Tagestour"
           and stay two, because they carry different ids. The proposal
           still counts up against taken names — reading „Tagestour"
           twice in the list would be no help even when it is correct. */
        let freshId = null;
        const onNewTour = () => {
            fresh = true;
            freshId = tour.mintTourId();
            tourField.value = tour.nextName(tour.today(),
                [...takenToday(), tourField.value]);
            tourHint.textContent = t('Neue Tour — beginnt mit diesem Punkt.');
            // The outing ends here: the field and the button go quiet.
            tourFieldBox.classList.remove('is-running');
            tourNewButton.classList.remove('is-active');
            tourField.focus();
        };
        const cleanUp = () => {
            windowEl.removeEventListener('close', onClose);
            shape.removeEventListener('submit', onConfirm);
            cancel.removeEventListener('click', onCancel);
            tourNewButton.removeEventListener('click', onNewTour);
            tourField.removeEventListener('input', onNameTyped);
        };
        const onConfirm = (e) => {
            e.preventDefault();
            const chosen = [...container.querySelectorAll('input:checked')].map(b => Number(b.value));

            const name = tourNoneWish ? '' : tourField.value.trim().slice(0, 120);
            // An edited name on the RUNNING tour renames it, points and all.
            // A fresh tour renames nothing — the old one simply ends, its
            // points keep their name.
            if (!fresh && active && name !== '' && name !== active.name) {
                renameTour(active, name);
            }
            // A continued (or renamed) tour keeps its START day — that is
            // what lets the six-hour rule and the rename span reach across
            // midnight. Only a fresh one begins today.
            /* The identity: a fresh tour brings the minted one, a
               continued one keeps what it has, and a window opened
               without any running tour mints on confirmation — that is
               the case of the very first point of an outing. */
            const rejoin = (fresh && freshId === null && name !== '') ? (todaysTours().get(name) ?? null) : null;
            const id = name === '' ? null
                : rejoin ? rejoin.id
                : (fresh ? (freshId ??= tour.mintTourId()) : (active?.id ?? tour.mintTourId()));
            if (name !== '') {
                tour.rememberTour(name, rejoin ? rejoin.day : (!fresh && active ? active.day : tour.today()), id);
            }

            cleanUp();
            windowEl.close();
            done({ lists: chosen, tour: name === '' ? null : name, tourId: id,
                   kind: asCollection.getAttribute('aria-pressed') === 'true' ? 'collection' : 'find' });
        };
        /* A NAME THAT ALREADY EXISTS IS NO LONGER THE SAME TOUR, and this
           hint says the opposite of what it said yesterday.

           On 24.08.2026 it was right: „meine zweite Tour verschmilzt mit
           der ersten, selbst wenn ich auf Neue Tour geklickt habe" —
           back then two identical labels WERE one outing, so the warning
           had to come. The id of migration 0079 was the answer to exactly
           that, and since the grouping follows it on both sides
           (collectTours here, organize.js there), a fresh tour with a
           familiar name stays its own. That is the whole point of
           „Tagestour" every day.

           THE HINT STAYS, BECAUSE THE QUESTION STAYS. Whoever types a name
           they have used before wants to know what happens now — and being
           told nothing reads like the old merging. It just says the other
           thing today. */
        const onNameTyped = () => {
            const typed = tourField.value.trim();
            if (typed !== '' && fresh && freshId === null && todaysTours().has(typed)) {
                tourHint.textContent = t('Tour von heute — der Punkt kommt zu ihr dazu.');
                return;
            }
            const known = takenNames().some((n) => n === typed);
            if (typed !== '' && known && (fresh || typed !== active?.name)) {
                tourHint.textContent = t('Diesen Namen gibt es schon — das bleibt trotzdem eine eigene Tour.');
                return;
            }
            tourHint.textContent = fresh
                ? t('Neue Tour — beginnt mit diesem Punkt.')
                : t('Läuft — Umbenennen gilt für alle Punkte dieser Tour.');
        };
        tourField.addEventListener('input', onNameTyped);

        const onCancel = () => { cleanUp(); windowEl.close(); done(null); };
        // Also catches the Escape key.
        const onClose = () => { cleanUp(); done(null); };

        const shape = document.getElementById('list-selection-form');
        const cancel = document.getElementById('list-selection-cancel');
        shape.addEventListener('submit', onConfirm);
        cancel.addEventListener('click', onCancel);
        windowEl.addEventListener('close', onClose);
        tourNewButton.addEventListener('click', onNewTour);

        windowEl.showModal();
        /* THE WINDOW GETS THE FOCUS, NOT THE TOUR FIELD
           (24.08.2026, from the phone: „das Listenfeld wird
           zusammengeschoben — ich konnte gar keine Listen anhaken").
           showModal() focuses the first focusable element, and since the
           tour moved in at the top that is a TEXT FIELD: the phone
           answered with its keyboard, --view-height shrank to the strip
           above it, and the list tree lay squeezed below the fold. The
           same cure as the organize dialog's (see its tabindex note):
           focus the window itself — whoever wants to rename the tour
           taps into the field, and only then may the keyboard come. */
        windowEl.focus();
    });
}

/**
 * @return {Promise<boolean>} whether a point really stands there in the end
 *
 * THE RETURN VALUE IS NO ORNAMENT. This function begins with a prompt for
 * the lists, and that can be cancelled; after that the server can still
 * refuse. Whoever wants to clean up afterwards — take the crosshair away,
 * close the search — must be able to tell the two apart. Without it the
 * mark vanished while the list dialog still stood open, and stayed gone
 * when it was cancelled.
 */
async function createPoint(lat, lon, extra = {}, message = t('Punkt angelegt.')) {
    const choice = await selectLists();
    if (choice === null) {
        status(t('Abgebrochen.'));
        return false;
    }
    const { lists, tour: tourName, tourId, kind } = choice;

    // WRITE IT DOWN FIRST, THEN SEND.
    //
    // An await used to stand here, and the window only opened once the
    // server had answered — from the phone, depending on reception, half a
    // second to several, in which nothing happened and you did not know
    // whether the click had arrived.
    //
    // The point therefore gets a made-up id immediately, negative so it
    // collides with no real one. With that it stands on the map, the
    // cursor blinks in the window, and the request runs alongside. When
    // the answer comes, it is rehung.
    //
    // Nothing about this is new: exactly this is how the application has
    // always behaved in a dead zone. New is only that it always does it —
    // and a path that is always walked is also always tested.
    const provisional = queue.provisionalKey();

    // THE FIND TIME COMES FROM HERE, not from the server.
    //
    // The server only knows the moment it learns of the point — and in a
    // dead zone that is the evening on the home WiFi, when the queue
    // drains. A whole tour would then lie within one minute, precisely
    // where mapping happens. This clock here stood in front of the
    // mushroom.
    const found = nowForFind();

    // THE FINDER IS PREFILLED — with whoever sets the point, and with first
    // and last name (start.user.finder): a find belongs to a person,
    // not to a login handle. Whoever enters somebody else's find changes
    // the field. Species lists only: elsewhere the field does not exist,
    // and an invisible value in the extra block would be data litter.
    // A guest maps ALWAYS as a find („das wird ja eine
    // Artenliste") — his excursion is the species list he cannot have.
    /* AN AUFSAMMLUNG (0174) carries its collector as the finder — always,
       whatever list it lies in — and no certainty: it is no species. */
    if (kind === 'collection') {
        extra = { ...extra, is_collection: true, extra: {
            ...(start.user?.finder ? { Finder: start.user.finder } : {}),
            ...(extra.extra ?? {}),
        } };
    } else if (GUEST || lists.some(id => listRoot(id)?.is_species)) {
        extra = { ...extra, extra: {
            ...(start.user?.finder ? { Finder: start.user.finder } : {}),
            // A FRESH POINT IN A SPECIES LIST IS AN OPEN FIND
            // (23.08.2026) — title or no title, 'unbestimmt' until a name
            // is picked from the suggestions; the pick then sets
            // 'makroskopisch sicher' itself (applySpecies).
            [CERTAINTY_FIELD]: 'unbestimmt',
            ...(extra.extra ?? {}),
        } };
    }

    /* NO TICK FOLLOWS THE POINT (26.09.2026, from the woods:
       „Das Gebiet hat sich also selbst angehakt, was nicht sein darf").
       From 23.09.2026 the lists a point was set into were ticked right
       here, so that a find would not vanish the moment it was made. It
       broke two things, and whoever wants it back meets both again:

       THE WINDOW PRESELECTS WHAT IS TICKED (selectLists), so a tick set
       here turned a one-off choice into the standing one. The concept the
       help builds everything on — one main list on, the find out of the
       row filed by ticking its list in the window (help.php, „immer nur
       eine Hauptliste") — fell over: one area set into the joint area
       list, and the next mushroom stood preselected into it as well.

       AND AN OWN JOINT LIST LANDED IN THE WRONG SET. It is in state.lists,
       so its tick went into the own choice — but its row stands under
       „Geteilt" and reads state.visibleShared. The map showed its points
       (pointVisible reads both), no row carried a tick to take off, and
       only a reload moved it across (the own-joint block near the top).

       WHAT THE TICK WAS MEANT FOR HOLDS WITHOUT IT. The point being worked
       on is always drawn (drawMarker exempts the selected one), and the
       night of 22.09. that started it had its cause elsewhere — lists that
       stood in no choice at all —, answered where it arose: a new list
       starts ticked (the list form), and so does a list first met on this
       device (knownLists). The sidebar's tick is the user's: a point sent
       into a list that is switched off goes there and leaves the map with
       its window. */
    const point = { id: provisional, lat, lon, title: '', note: '', lists: lists,
                    found_at: found, pending: true,
                    ...(tourName ? { tour: tourName, tour_id: tourId } : {}), ...extra };

    state.points.set(provisional, point);
    computeListCounts();
    drawMarker();
    drawList();
    // At the desk the point opens in the Fundbrowser once the server has it (below).
    if (!deskCockpit()) {
        selectPoint(provisional);
        fields.title.focus();
    }
    status(message);

    try {
        // The queue lies beneath the interface: without network a usable
        // answer still comes back here — with a provisional id of its own
        // and the note 'pending'.
        // Noted on the point so saving and deleting can wait for it: a
        // PATCH on a made-up number the server would not find.
        /* THE SAME PROVISIONAL NUMBER ON BOTH SIDES. Until 21.08.2026 the
           application drew the point under the number above while api.post
           made up a second one of its own for the queue. As long as the
           save waited for the create, nobody noticed. It must not wait any
           more — and then a title saved in the meantime stands in the
           queue under THIS number while the create carries the other one.
           Nothing matches them up, and the change is discarded as
           unassignable. One number, no matching-up. */
        const promise = api.post('points',
            { lat, lon, title: '', lists: lists, found_at: found,
              ...(tourName ? { tour: tourName, tour_id: tourId } : {}), ...extra }, provisional);
        point.createRunning = promise;

        const response = await promise;
        delete point.createRunning;

        swapPointKey(provisional, response.id, response);
        drawMarker();
        drawList();

        /* AT THE DESK THE NEW POINT GOES STRAIGHT INTO EDITING
           (12.10.2026: „Was passiert, wenn ich in der Karte einen neuen Punkt
           setze? Dann komme ich auch direkt in die Bearbeitung") — in the
           Fundbrowser, on its texts: a find without a picture yet is named
           first. Only with the server's number: the Fundbrowser knows no
           provisional one, and a point still waiting in the queue stays on
           the map with its name tag. */
        if (deskCockpit() && !response.pending && response.id > 0) {
            location.href = findsAddress(response.id, 'text');
            return true;
        }

        // Height and timestamp are only there now. The window takes them in
        // without touching an input in progress — see fillDetail().
        if (state.selectedId === response.id) {
            fillDetail(state.points.get(response.id));
        }
        if (response.pending) status(t('Wird übertragen, sobald Verbindung da ist.'));
        return true;
    } catch (e) {
        // The server has refused — not a network error, that would have
        // landed in the queue. The point must go again, otherwise something
        // stands on the map that exists nowhere.
        delete point.createRunning;
        state.points.delete(provisional);
        if (state.selectedId === provisional) clearSelection();
        computeListCounts();
        drawMarker();
        drawList();
        status(t('Anlegen fehlgeschlagen: %s', e.message), true);
        return false;
    }
}

// ---------------------------------------------------------------------------
// Location
// ---------------------------------------------------------------------------

/**
 * Continuous display of one's own location.
 *
 * The button does not switch once but continuously: as long as the display
 * is on, the mark wanders along. Recording is nothing the page can do — as
 * soon as the screen goes dark, the browser stops the positioning, and
 * deliberately so. For a track it takes an app of its own via the API.
 * Displaying while you are looking works fine though, and because turning
 * the screen off ends the positioning by itself, it also only costs
 * battery while you are really looking.
 *
 * THREE STATES, one button — the same pattern as in the well-known map
 * apps:
 *
 *   off                     nothing runs
 *   on and following        mark wanders, the map pulls along
 *   on, not following       mark wanders, the map stays put
 *
 * The third state arises by itself as soon as you pan the map: whoever is
 * looking elsewhere right now does not want to be yanked back constantly.
 * One press on the button brings you back and lets the map run along
 * again, the next switches off.
 *
 * EVERY REPORT COUNTS, not only the best. For the one-off search it was
 * right to adopt only improvements — otherwise the map jumped back and
 * forth between cell tower and satellite. Here the same rule would be a
 * mistake: the mark would stay stuck at the spot with the best accuracy
 * ever measured and would not wander along while walking. Therefore only
 * what is obviously unusable is discarded.
 *
 * ON SWITCHING ON, for half a minute the position is additionally polled
 * every two seconds. watchPosition only reports on change — whoever
 * switches on while standing still would otherwise get the first rough
 * estimate and then nothing more, while the receiver in the background has
 * long become better. After that the polling stops; while walking the
 * watch delivers by itself.
 */

const LOCATION_TARGET_M    = 15;      // from here on the positioning has settled
const LOCATION_WARNING_M = 25;      // above this too coarse for a point
const LOCATION_LIMIT_M  = 150;     // above this unusable, gets discarded
const LOCATION_CADENCE_MS   = 2000;    // polling beat during the warm-up
const LOCATION_WARMUP_MS = 30000;   // how long polling goes on

// A report older than this counts as stale: the mark then no longer says
// "you are here" but "you were here". Fifteen seconds is generous — the
// watch delivers every couple of seconds while walking.
const LOCATION_OLD_MS    = 15000;
// How often the watchdog looks whether anything is still coming in.
const LOCATION_WATCHDOG_MS   = 4000;
// Not more often than this does it take up the watch anew. Without the
// brake a receiver that genuinely finds nothing (indoors, in the car)
// would be restarted every four seconds — that costs power and changes
// nothing.
const LOCATION_RENEW_MS    = 25000;

const LOCATION_OPTIONS = {
    enableHighAccuracy: true,
    timeout: 30000,
    maximumAge: 0,
};

/* The cone before the dot, so the dot stands on top of it (map.css).
   Hidden until a direction is known — see the heading further down. */
const LOCATION_ICON = L.divIcon({
    className: '',
    html: '<span class="location-heading" hidden></span><span class="location-point"></span>',
    iconSize:   [18, 18],
    iconAnchor: [9, 9],
});

let locationActive      = false;
let locationFollows   = false;
let locationWatch   = null;
let locationCadence    = null;
let locationWarmup  = null;
let locationMarker   = null;
let locationCircle   = null;
let locationBest   = null;        // best since switching on, for the display
let locationAccuracy = null;    // that of the last report, for new points
let locationTime   = null;        // when the last report arrived
let locationWatchdog    = null;        // the watchdog's beat
let locationRenewAt   = 0;           // not before then does re-arming happen again
let locationElevation   = null;        // above the WGS84 ellipsoid, not above NHN
let locationElevationError = null;
let locationWarned = false;

const locationButton = document.getElementById('tool-location');

/* „RICHTUNG ZEIGEN" lives in direction.js; the map hands in where one
   stands. Created here, after the location's variables and before the
   first report can arrive, since each report moves the arrow along
   (acceptLocation). A find remembered from before a reload comes back. */
const direction = createDirection(map, {
    origin: () => locationMarker?.getLatLng() ?? null,
    // How far the button rows reach into the map, top and bottom: the
    // floating buttons and Leaflet's controls above, the tools, the zoom,
    // the scale and the attribution below.
    bars: () => {
        const box = map.getContainer().getBoundingClientRect();
        const middle = box.top + box.height / 2;
        let top = 0, bottom = 0;
        for (const el of document.querySelectorAll('#sidebar-expand, #friend-chat-entry-floating, #map-tools, .leaflet-control')) {
            if (!el.offsetParent) continue;
            const r = el.getBoundingClientRect();
            if (r.width === 0 || r.bottom <= box.top || r.top >= box.bottom) continue;
            if (r.bottom < middle) top = Math.max(top, r.bottom - box.top);
            else if (r.top > middle) bottom = Math.max(bottom, box.bottom - r.top);
        }
        return { top, bottom };
    },
});
{
    const kept = direction.remembered();
    if (kept) direction.start(kept);
}

/**
 * Addendum when the positioning stays poor.
 *
 * Measured at the same place, in the same moment, on the same Android
 * phone: Chrome reached ±4 m, Firefox stayed at 30 m and climbed to 60 to
 * 100 m while walking around. Both ask the same Android via the same API —
 * the difference arises in the browser, and from here it cannot be fixed.
 * Then the only honest way to handle it is to say so: a point that lies
 * 60 m off is worse than none at all, because you can see that it was set
 * but not that it is wrong.
 */
function locationHint(accuracy) {
    if (accuracy === null || accuracy <= LOCATION_WARNING_M) return '';

    const signature = navigator.userAgent;
    if (/Android/.test(signature) && /Firefox/.test(signature)) {
        return ' ' + t('Firefox ortet auf Android deutlich schlechter als Chrome — für genaue Punkte diese Seite dort öffnen.');
    }
    return ' ' + t('Freier Himmel hilft: weg von Hauswänden, raus aus dem Auto.');
}

function drawLocationButton() {
    locationButton.classList.toggle('active', locationActive);
    locationButton.classList.toggle('following', locationActive && locationFollows);
    // The pulsing stops as soon as the positioning has settled — it says
    // "still warming up", not "is on".
    locationButton.classList.toggle('settled',
        locationActive && locationBest !== null && locationBest <= LOCATION_TARGET_M);
    locationButton.title = !locationActive      ? t('Standort dauerhaft anzeigen')
                        : locationFollows    ? t('Standortanzeige ausschalten')
                                           : t('Karte wieder mitlaufen lassen');
}

/**
 * One's own location, provided it is fit for a new point.
 *
 * Two conditions, and the second is the more important: the display must
 * be running, AND the mark must lie in the visible viewport. That is the
 * difference between "I am standing here and see myself on the map" and
 * "I have pushed the map into the next valley to enter something there" —
 * in the second case one's own position would be the wrong answer, even
 * though it is known.
 *
 * That is why looking at the following state is not enough: whoever pans
 * the map a bit to tap next to themselves has switched the following off
 * but still means their location.
 */
function locationForPoint() {
    if (!locationActive || locationMarker === null) return null;

    const where = locationMarker.getLatLng();
    if (!map.getBounds().contains(where)) return null;

    return {
        lat: where.lat,
        lon: where.lng,
        accuracy_m: locationAccuracy === null ? null : Math.round(locationAccuracy),
        // Deliberately passed as ellipsoid height and not as sea level: the
        // receiver measures against the WGS84 ellipsoid, and in Germany
        // that lies around 46 m below sea level. Conversion happens on the
        // server, where the geoid grid lies.
        elevation_ellipsoid_m:   locationElevation    === null ? null : Math.round(locationElevation),
        elevation_accuracy_m: locationElevationError === null ? null : Math.round(locationElevationError),
    };
}

/** Adopt one report. */
function acceptLocation(pos) {
    if (!locationActive) return;

    const { latitude, longitude, accuracy, altitude, altitudeAccuracy, heading, speed } = pos.coords;

    // Obvious nonsense flies out — otherwise the mark jumps clear across
    // town on an interspersed cell-tower fix.
    if (accuracy > LOCATION_LIMIT_M) return;

    // For a new point the current measurement counts, not the best ever
    // measured: that one belongs to a spot where you may long have stopped
    // standing.
    locationAccuracy  = accuracy;
    locationTime        = Date.now();
    locationElevation        = (altitude ?? null) === null ? null : altitude;
    locationElevationError = (altitudeAccuracy ?? null) === null ? null : altitudeAccuracy;

    const where = [latitude, longitude];

    if (locationMarker === null) {
        locationMarker = L.marker(where, {
            icon: LOCATION_ICON,
            interactive: true,
            keyboard: false,
            zIndexOffset: 500,
        }).addTo(map);
        locationCircle = L.circle(where, {
            radius: accuracy,
            className: 'location-circle',
            interactive: false,
        }).addTo(map);
        locationMarker.bindTooltip('');
    } else {
        locationMarker.setLatLng(where);
        locationCircle.setLatLng(where).setRadius(accuracy);
    }

    /* THE WALKING DIRECTION, the cone's second source: the receiver
       reports it only while one moves — null or NaN standing — and below
       a slow step it is noise. */
    if (Number.isFinite(heading) && Number.isFinite(speed) && speed >= HEADING_WALK_MPS) {
        walkHeading = heading;
        walkHeadingAt = Date.now();
        drawHeadingSoon();
    }

    showLocationFreshness();
    direction.redraw();

    if (locationFollows) {
        // No setView: that would reset the zoom level on every report and
        // choke off a zoom the user has in progress.
        followLocation(where);
    }

    const first = locationBest === null;
    if (first || accuracy < locationBest) locationBest = accuracy;
    drawLocationButton();
    if (first) drawPointButton();

    if (first) {
        status(accuracy <= LOCATION_TARGET_M
            ? t('Standort auf ±%d m genau.', Math.round(accuracy))
            : t('Standort auf ±%d m — wird noch schärfer …', Math.round(accuracy)));
    }
}

/** Pull the map along without touching the zoom level. */
function followLocation(where) {
    /* NOT WHILE A FINGER MOVES THE MAP. On the vector map every camera
       move by code ends the gesture in progress — a pinch broke off at
       the next location report, once a second, and had to be started
       anew (30.09.2026). The report after the gesture pulls
       the map along again. Leaflet's map has no userMoving and pans as
       it always did. */
    if (map.userMoving?.()) return;
    /* WITH „MITDREHEN" THE GLIDE TAKES THE BEARING ALONG (03.10.2026). A
       setBearing from the heading frame would stop it one frame after it
       began (vectormap.js, followTo) — so for its half second the glide
       turns the map itself, and the heading frame waits; the cone at the
       dot keeps turning freely in the meantime. */
    if (mapTurns && shownHeading !== null && map.followTo) {
        followGlideUntil = Date.now() + 500;
        map.followTo(where, shownHeading, { duration: 0.5 });
        return;
    }
    map.panTo(where, { animate: true, duration: 0.5 });
}

/**
 * Say how old the mark is — in its look and in its tooltip.
 *
 * A STALE MARK LOOKS EXACTLY LIKE A CURRENT ONE, and that is the actual
 * damage: whoever comes back after locking the phone believes the blue dot,
 * walks on and sets a point in the wrong place. There is no way to tell by
 * looking that the last report is three minutes old.
 *
 * Hence the mark fades and names its age. It stays where it is: a position
 * that WAS measured is better than none — it just must not pretend to be
 * the current one.
 */
function showLocationFreshness() {
    if (locationMarker === null) return;

    const age = locationTime === null ? null : Date.now() - locationTime;
    const old   = age !== null && age > LOCATION_OLD_MS;

    const el = locationMarker.getElement()?.querySelector('.location-point');
    if (el) el.classList.toggle('is-stale', old);
    locationCircle?.getElement()?.classList.toggle('is-stale', old);
    // A direction from a place one has left points nowhere.
    locationStale = old;
    drawHeadingSoon();

    const accuracy = locationAccuracy === null ? '?' : Math.round(locationAccuracy);
    locationMarker.setTooltipContent(old
        ? t('Zuletzt vor %s · ±%s m', readableTimeSpan(age), accuracy)
        : t('Standort · ±%s m', accuracy));
}

/** A rough duration, the way one says it aloud. */
function readableTimeSpan(ms) {
    const s = Math.round(ms / 1000);
    if (s < 90)   return `${s} s`;
    const m = Math.round(s / 60);
    if (m < 90)   return `${m} min`;
    return `${Math.round(m / 60)} h`;
}

/**
 * The watchdog: does anything still come in?
 *
 * WHY THIS EXISTS ALONGSIDE THE visibilitychange HANDLER. That one hands the
 * watch back when hiding and takes it up again on return — and on paper that
 * covers the locked phone. In practice it does not always: a page that
 * Android has frozen resumes without the event being usable for it, a return
 * out of the back/forward cache does not fire visibilitychange at all, and a
 * watch registered while the page was still suspended can stay silent
 * forever. All three look identical from here — nothing comes in.
 *
 * So the watchdog does not ask WHY but only WHETHER: if no report has
 * arrived for longer than LOCATION_OLD_MS while the page is visible, the
 * watch gets taken up anew. That heals every one of those causes and needs
 * to know none of them.
 */
function startLocationWatchdog() {
    clearInterval(locationWatchdog);
    locationWatchdog = setInterval(() => {
        if (!locationActive) return;

        showLocationFreshness();

        if (document.hidden || locationTime === null) return;
        if (Date.now() - locationTime <= LOCATION_OLD_MS) return;
        if (Date.now() < locationRenewAt) return;

        locationRenewAt = Date.now() + LOCATION_RENEW_MS;
        listenForLocation();
    }, LOCATION_WATCHDOG_MS);
}

function locationError(error) {
    if (!locationActive) return;

    // A single failed query breaks nothing off — the next one comes right
    // away. Only a denied permission is final.
    if (error.code !== 1 && locationBest !== null) return;

    const texts = {
        1: t('Standortzugriff wurde abgelehnt.'),
        2: t('Standort ist gerade nicht zu ermitteln.'),
        3: t('Die Standortbestimmung hat zu lange gedauert.'),
    };
    locationOff(texts[error.code] || t('Standort nicht verfügbar.'), true);
}

/** Take up the watch and the warm-up polling. */
function listenForLocation() {
    releaseLocation();

    locationWatch = navigator.geolocation.watchPosition(
        acceptLocation, locationError, LOCATION_OPTIONS);

    navigator.geolocation.getCurrentPosition(
        acceptLocation, locationError, LOCATION_OPTIONS);

    locationCadence = setInterval(() => {
        navigator.geolocation.getCurrentPosition(
            acceptLocation, locationError, LOCATION_OPTIONS);
    }, LOCATION_CADENCE_MS);

    // After the warm-up the watch suffices. Continuing to poll only costs
    // power without contributing anything.
    locationWarmup = setTimeout(() => {
        clearInterval(locationCadence);
        locationCadence = null;

        if (!locationWarned && locationBest !== null && locationBest > LOCATION_WARNING_M) {
            locationWarned = true;
            status(t('Standort bleibt bei ±%d m.', Math.round(locationBest))
                + locationHint(locationBest), true);
        }
    }, LOCATION_WARMUP_MS);
}

/**
 * Hand back watch and polling — the mark stays put.
 *
 * The watchdog deliberately keeps running: it is the thing that notices
 * nothing is coming in. It is only stopped by locationOff().
 */
function releaseLocation() {
    if (locationWatch !== null) {
        navigator.geolocation.clearWatch(locationWatch);
        locationWatch = null;
    }
    clearInterval(locationCadence);
    clearTimeout(locationWarmup);
    locationCadence   = null;
    locationWarmup = null;
}

/*
 * THE DIRECTION ONE LOOKS IN — a cone on the location mark, as Google Maps
 * draws it (29.09.2026, agreed with the app session: „Kompass-
 * richtung und Blickrichtung über Gehrichtung und über Kompass-Signal").
 *
 * TWO SOURCES, THE COMPASS FIRST. The phone's compass says where the phone
 * points, standing or walking — in the woods the question is „where is the
 * find from here", and that is the direction one faces. The WebView of the
 * app delivers it by itself (measured by the app session: the event
 * deviceorientationabsolute, north-referenced, about 57 a second, without
 * asking), and so does Chrome. Where there is no compass — a laptop, a
 * phone without a magnetometer — the receiver's walking direction stands
 * in, which exists only while one moves (acceptLocation). Neither: no cone.
 *
 * WHERE ONE LOOKS, FROM THE WHOLE TURN OF THE PHONE — not from alpha alone.
 * Two things decide it (forwardHeading): which edge of the phone is the top
 * of the SCREEN (held across, the screen turns and another edge is on top —
 * screen.orientation.angle says which), and how the phone is held. Flat,
 * one looks where the top of the screen points; upright in front of the
 * eyes, where the back of the phone points — the top of the screen then
 * points at the sky. The sum of both directions points forward in every
 * way one holds a phone in between, and it never meets the gimbal jump
 * alpha alone makes around upright landscape.
 *
 * A DETOUR ON 29.09.2026, written down because it looked like proof: the
 * first test turned the phone FLAT on the table into landscape. A flat
 * phone does not turn its screen — it cannot tell where down is — so the
 * cone rightly followed the phone's top edge by a quarter; read as an
 * error, it made me drop the screen angle (f4ecf13). Held across in front
 * of the eyes, the cone then lay a quarter beside, one way with the top
 * edge left and the other way with it right. The angle belongs
 * in; alpha, beta and gamma are the phone's own, as the specification says.
 * Checked against poses built by hand — flat, tilted and upright, upright
 * and across both ways — before it went in.
 *
 * Safari on the iPhone speaks webkitCompassHeading, already clockwise from
 * north, and asks first; how it behaves across is not measured.
 *
 * SMOOTHED AND AT MOST ONCE A FRAME: 57 raw readings a second tremble by a
 * few degrees, and each one redrawn would cost the battery what the eye
 * cannot see. The cone turns the shorter way round, never across the back.
 */
const HEADING_WALK_MPS   = 0.7;     // slower than this, the walking direction is noise
const HEADING_COMPASS_MS = 2000;    // a compass silent this long has stopped
const HEADING_WALK_MS    = 10000;   // a walking direction older than this is stale
const HEADING_SMOOTHING  = 0.25;    // share of the new reading per frame

let compassHeading   = null;
let compassHeadingAt = 0;
let walkHeading      = null;
let walkHeadingAt    = 0;
let shownHeading     = null;
let headingFrame     = null;
// Until when a follow glide that carries the bearing runs (followLocation) —
// the heading frame leaves the map's bearing alone until then.
let followGlideUntil = 0;
let locationStale    = false;
let headingListening = false;

/* MAGNETIC NORTH IS NOT THE MAP'S NORTH (03./04.10.2026: in
   town the cone pointed „leicht falsche Richtungen"). The compass reading
   is magnetic, the map's up true north, and no browser corrects for it;
   around Wiesbaden that is 3.7° east in 2026, the same off every street.
   declination() reckons it with the World Magnetic Model (declination.js),
   asked again only when one has gone a quarter degree — some twenty
   kilometres, over which it changes by a few hundredths. Before the first
   fix the map's centre stands in; the walking direction from GPS is true
   north already and stays as it is. */
let magneticAt = null;   // { lat, lng, east }
function magneticEast() {
    const at = locationMarker?.getLatLng?.() ?? map.getCenter?.();
    if (!at) return 0;
    if (magneticAt === null || Math.abs(at.lat - magneticAt.lat) > 0.25 || Math.abs(at.lng - magneticAt.lng) > 0.25) {
        magneticAt = { lat: at.lat, lng: at.lng, east: declination(at.lat, at.lng, 0) };
    }
    return magneticAt.east;
}

function onOrientation(e) {
    const heading = headingOf(e);
    if (heading === null) return;
    compassHeading = (heading + magneticEast() + 360) % 360;
    compassHeadingAt = Date.now();
    drawHeadingSoon();
}

/** Ask for the compass where it has to be asked for — from a tap. */
function askForCompass() {
    const ask = globalThis.DeviceOrientationEvent?.requestPermission;
    if (typeof ask !== 'function') return;
    ask.call(DeviceOrientationEvent).catch(() => { /* then the walking direction alone */ });
}

function listenForHeading() {
    if (headingListening) return;
    headingListening = true;
    // Chrome and the app's WebView fire the absolute one; Safari only the
    // plain one, which carries webkitCompassHeading. Listened to both —
    // headingOf() takes from each only what is a compass.
    window.addEventListener('deviceorientationabsolute', onOrientation);
    window.addEventListener('deviceorientation', onOrientation);
}

function releaseHeading() {
    window.removeEventListener('deviceorientationabsolute', onOrientation);
    window.removeEventListener('deviceorientation', onOrientation);
    headingListening = false;
    compassHeading = walkHeading = shownHeading = null;
    cancelAnimationFrame(headingFrame);
    headingFrame = null;
}

// A turned map moves the screen's north — the cone follows it.
map.on('rotate', () => drawHeadingSoon());

/* „MITDREHEN" — THE MAP TURNS WITH THE VIEW (29.09.2026: „dass
   die Karte immer in Blickrichtung sich dreht"). The vector map only:
   Leaflet cannot turn. A compass button under the layer picker, its
   needle pointing where north is on the screen. A tap lets the map follow
   the cone — up is where one looks — and switches the location display
   on if it was off, since the compass listens only with it; the next tap
   stands north up again. Turning the map with two fingers ends the
   following: whoever turns by hand wants it where he put it. Remembered
   for this device. */
let mapTurns = VECTOR && settings.get('mapTurns', false) === true;
if (VECTOR) {
    const TurnControl = L.Control.extend({
        onAdd() {
            const bar = L.DomUtil.create('div', 'leaflet-bar turn-control');
            const a = L.DomUtil.create('a', '', bar);
            a.href = '#';
            a.setAttribute('role', 'button');
            a.title = t('Karte mit der Blickrichtung drehen');
            a.setAttribute('aria-label', a.title);
            const needle = L.DomUtil.create('span', 'turn-needle', a);
            const show = () => {
                needle.style.transform = `rotate(${(-map.getBearing()).toFixed(1)}deg)`;
                a.classList.toggle('is-active', mapTurns);
                a.setAttribute('aria-pressed', String(mapTurns));
            };
            a.addEventListener('click', (e) => {
                e.preventDefault();
                mapTurns = !mapTurns;
                settings.set('mapTurns', mapTurns);
                if (mapTurns && !locationActive) { askForCompass(); locationOn(); }
                // North up at once, not eased: the location display pans
                // the map on every report, and a pan cuts an easing turn
                // off where it stands (measured: stuck at 82°).
                if (!mapTurns) map.setBearing(0);
                show();
                drawHeadingSoon();
            });
            map.on('rotate', show);
            map.gl.on('rotatestart', (e) => {
                if (e.originalEvent && mapTurns) { mapTurns = false; settings.set('mapTurns', false); show(); }
            });
            L.DomEvent.disableClickPropagation(bar);
            show();
            return bar;
        },
    });
    new TurnControl({ position: 'bottomright' }).addTo(map);
}

function drawHeadingSoon() {
    if (headingFrame === null) headingFrame = requestAnimationFrame(drawHeading);
}

function drawHeading() {
    headingFrame = null;
    const cone = locationMarker?.getElement()?.querySelector('.location-heading');
    if (!cone) return;

    const now = Date.now();
    const target = compassHeading !== null && now - compassHeadingAt < HEADING_COMPASS_MS ? compassHeading
                 : walkHeading !== null && now - walkHeadingAt < HEADING_WALK_MS ? walkHeading
                 : null;
    if (target === null || locationStale) {
        cone.hidden = true;
        shownHeading = null;
        return;
    }

    // The shorter way round: from 350° to 10° is twenty degrees, not 340.
    const step = shownHeading === null ? 0 : ((target - shownHeading + 540) % 360) - 180;
    shownHeading = shownHeading === null ? target : (shownHeading + step * HEADING_SMOOTHING + 360) % 360;
    // „Mitdrehen": the map turns so that up is where one looks. Only when
    // it really moved — a bearing set to what it already is still fires
    // 'rotate', which asks for this frame again: a loop at sixty a second.
    // Not while a finger moves the map: setting the camera ends the
    // gesture (userMoving in vectormap.js).
    // Nor while a follow glide carries the bearing: turning now would stop
    // it (followLocation).
    if (mapTurns && !map.userMoving?.() && Date.now() >= followGlideUntil
        && Math.abs((((map.getBearing() - shownHeading) % 360) + 540) % 360 - 180) > 0.3) {
        map.setBearing(shownHeading);
    }
    // On a turned map (the vector map, 29.09.2026) the screen's up is not
    // north; the cone turns back by the map's own bearing.
    const bearing = map.getBearing?.() ?? 0;
    cone.style.transform = `rotate(${(shownHeading - bearing).toFixed(1)}deg)`;
    cone.hidden = false;

    // Still turning towards the reading: one more frame.
    if (Math.abs(step) > 0.5) drawHeadingSoon();
}

function locationOn() {
    if (!navigator.geolocation) {
        status(t('Dieser Browser kennt keine Standortbestimmung.'), true);
        return;
    }
    locationActive      = true;
    locationFollows   = true;
    locationBest   = null;
    locationWarned = false;
    locationTime   = null;
    locationRenewAt   = 0;
    drawLocationButton();
    drawPointButton();
    settings.set('location', true);
    status(t('Standort wird bestimmt …'));
    listenForLocation();
    startLocationWatchdog();
    listenForHeading();
}

function locationOff(text = '', error = false) {
    locationActive    = false;
    locationFollows = false;
    releaseLocation();
    clearInterval(locationWatchdog);
    locationWatchdog  = null;
    locationTime = null;

    if (locationMarker) { map.removeLayer(locationMarker); locationMarker = null; }
    if (locationCircle) { map.removeLayer(locationCircle); locationCircle = null; }
    releaseHeading();

    locationAccuracy  = null;
    locationElevation        = null;
    locationElevationError = null;
    drawLocationButton();
    drawPointButton();
    settings.set('location', false);
    if (text) status(text, error);
}

/* The button in the point window's „Karte" tab: the direction to this
   find. The location display comes on if it was off — the arrow and the
   distance are FROM where one stands. The window closes, since on a phone
   it lies over the very map the arrow is drawn on (and asks first when
   something is unsaved, as every closing does). */
document.getElementById('d-direction')?.addEventListener('click', async () => {
    const point = state.points.get(state.selectedId) ?? state.sharedPoints.get(state.selectedId);
    if (!point) return;
    direction.start({ id: point.id, lat: point.lat, lon: point.lon });
    if (!locationActive) {
        askForCompass();
        locationOn();
    }
    await closeDetail();
});

locationButton.addEventListener('click', () => {
    if (!locationActive) {
        // Safari on the iPhone hands out the compass only after asking, and
        // only from a tap — this one is the tap (see listenForHeading).
        askForCompass();
        locationOn();
    } else if (!locationFollows) {
        // Let it run along again and jump there once.
        locationFollows = true;
        drawLocationButton();
        if (locationMarker) followLocation(locationMarker.getLatLng());
    } else {
        locationOff();
    }
});

/**
 * Whoever pans the map themselves wants to stay there for now.
 *
 * A flag variable initially stood here that was to tell our own following
 * apart from a user's drag for 700 ms. That was doubly wrong: needless,
 * because 'dragstart' comes solely from dragging with finger or mouse and
 * panTo does not trigger it at all — and harmful, because while walking a
 * report arrives every one to two seconds. The time window would thus have
 * been open almost always, and a user's drag would have been swallowed:
 * the map could not have been held still.
 *
 * AND NOT ONLY THE DRAG: clicking a point in the sidebar, "Zeigen" in the
 * window, a coordinate search — every deliberate jump elsewhere says the
 * same thing, only through setView instead of a finger, and setView fires
 * no 'dragstart'. Without pauseFollowing() at those spots the very next
 * location report yanked the map back to the standing spot, and the point
 * one had just asked for slid out from under the eye
 * (23.08.2026). The mark keeps wandering; the button brings the map back.
 */
function pauseFollowing() {
    if (locationActive && locationFollows) {
        locationFollows = false;
        drawLocationButton();
    } else if (!locationActive) {
        // The display is not running (yet) — remember the intent anyway.
        // The resume after a reload sits behind a promise and thus always
        // fires AFTER the module's top-level code: a jump target in the
        // address has long run when it switches the display back on, and
        // without this note it would re-arm the following and pan away
        // from the very spot the link asked for. Only the resume reads
        // the flag — a fresh press on the button always follows.
        followingPausedEarly = true;
    }
}

let followingPausedEarly = false;

map.on('dragstart', pauseFollowing);

// The location can wander out of view when you pan the map or zoom out —
// then the set button says "Punkt setzen" again.
//
// The hook hangs here and not up with the other setting things: Leaflet
// fires 'moveend' already while building the map, and drawPointButton()
// accesses the location variables. Registered further up, it would run
// against their not-yet-executed declarations.
map.on('moveend zoomend', () => drawPointButton());

/*
 * And pull the markers along — THAT IS THE PRICE OF THE VIEWPORT.
 *
 * Only what is on screen gets drawn; so on every map movement it must be
 * decided anew what that is. Without this hook the map would stay empty
 * while panning as soon as you leave the initially loaded area.
 *
 * That costs one pass through all points per movement — at 20,000 a
 * measured seven milliseconds, and that does not change as the stock
 * grows, because only the markers on screen really come into being. Before
 * it was 1,797 ms, but those fell due on every filter change.
 *
 * Together with the line above, not before it: drawPointButton() is
 * cheap, drawMarker() is not — and the button should not wait for the
 * drawing.
 */
map.on('moveend zoomend', () => drawMarker());

// The corner handles only appear when the area stands large enough on
// screen — that changes on zooming without any point changing. For the
// same reason as above, the hook hangs down here.
map.on('zoomend', () => syncHandles());

/**
 * Screen off or app switched: hand back the watch.
 *
 * The browser stops the positioning in that case anyway. Leaving it
 * registered regardless only keeps a consumer open with the operating
 * system that delivers nothing. When the page comes back, it starts over —
 * warm-up polling included, since after a pause the first report is a
 * rough one again.
 */
document.addEventListener('visibilitychange', () => {
    if (!locationActive) return;
    if (document.hidden) {
        releaseLocation();
        releaseHeading();
    } else {
        resumeLocation();
    }
});

/**
 * Back from the back/forward cache.
 *
 * NOT COVERED BY visibilitychange: whoever leaves the page and comes back
 * via the back button gets it out of the cache, frozen mid-motion — and the
 * browser does not fire a visibility change for that, because from its point
 * of view nothing was ever hidden. The watch from before, however, is gone.
 */
window.addEventListener('pageshow', (e) => {
    if (e.persisted && locationActive) resumeLocation();
});

/**
 * The page is in front again: take up the watch, and say that the mark is
 * old until a new report has come in.
 *
 * The status message matters more than it looks. Whoever unlocks the phone
 * sees the blue dot and believes it — the sentence is the only thing that
 * says the dot is still the one from before the break.
 */
function resumeLocation() {
    locationWarned = false;
    locationRenewAt   = Date.now() + LOCATION_RENEW_MS;
    listenForLocation();
    startLocationWatchdog();
    /* THE COMPASS STARTS AFRESH, as it does when the display is switched
       off and on (29.09.2026: out of the lock screen the cone
       pointed the wrong way, off and on again it was right). Resumed, the
       phone's sensor fusion went on from where it stood when the screen
       went dark — or from a disturbed first reading at a metal desk — and
       held that offset; a fresh listener makes the browser start the
       sensor anew from the magnetic field as it is now. Until the first
       fresh reading the cone stays away rather than show the old
       direction. */
    releaseHeading();
    listenForHeading();
    showLocationFreshness();

    if (locationTime !== null && Date.now() - locationTime > LOCATION_OLD_MS) {
        status(t('Standort wird neu bestimmt — die Markierung ist noch die von vorhin.'));
    }
}

/**
 * Switch back on after a reload if it was last on.
 *
 * But only if the permission is already granted: otherwise the browser's
 * prompt would stand on screen unasked when opening the page, and that is
 * nobody's idea of a map that simply opens.
 */
if (settings.get('location', false) && navigator.permissions?.query) {
    navigator.permissions.query({ name: 'geolocation' })
        .then(permission => {
            if (permission.state !== 'granted') return;
            locationOn();
            // A jump target in the address has already run by now — see
            // the note in pauseFollowing(). The mark comes, the map stays.
            if (followingPausedEarly) pauseFollowing();
        })
        .catch(() => { /* then just not */ });
}

drawLocationButton();

/**
 * Jump to a spot that stands in the address as #zeige=lat,lon.
 *
 * The trash links to deleted points this way: they no longer exist on the
 * map, they are drawn nowhere — yet one wants to look at the spot before
 * deleting for good. So the map sets a temporary mark here that is stored
 * nowhere and vanishes again on the next tap on the map.
 */
function spotFromUrl() {
    const hits = /^#zeige=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)$/.exec(location.hash);
    if (!hits) return;

    const where = [parseFloat(hits[1]), parseFloat(hits[2])];
    if (!Number.isFinite(where[0]) || !Number.isFinite(where[1])) return;

    // Whoever follows a "Zeigen" link wants to see THAT spot — the
    // resumed location display must not pull the map away from it.
    pauseFollowing();
    centreVisible(where, Math.max(map.getZoom(), 17));

    const pointer = L.marker(where, {
        icon: L.divIcon({
            className: '',
            html: '<span class="show-point"></span>',
            iconSize: [22, 22],
            iconAnchor: [11, 11],
        }),
        zIndexOffset: 600,
        keyboard: false,
    }).addTo(map);
    pointer.bindTooltip(t('Gelöschter Punkt — nur angezeigt'), { permanent: false });

    status(t('Stelle aus dem Papierkorb. Die Markierung ist nicht gespeichert.'));

    const removeButton = () => { map.removeLayer(pointer); map.off('click', removeButton); };
    map.on('click', removeButton);

    // Clean the address up again so a reload does not jump there anew.
    history.replaceState(null, '', location.pathname + location.search);
}

// ---------------------------------------------------------------------------
// Reloading and status messages
// ---------------------------------------------------------------------------

/**
 * Fetch lists and points fresh — and without network simply not.
 *
 * Both are called right after every write. If they threw an exception in a
 * dead zone, the caller would land in its error branch and report "Anlegen
 * fehlgeschlagen" — although the point has long lain in the queue and
 * stands on the map.
 *
 * Reading without a connection is not an error but a non-event: the local
 * state stays as it is. It is the more current one anyway, since it holds
 * what still waits.
 */
async function reloadLists() {
    try {
        const response = await api.get('lists');
        applyLists(response.lists);
    } catch (e) {
        if (!e?.isNetworkError) throw e;
    }
}

/**
 * Adopt a list answer from the server — in its order.
 *
 * Sorting by ordinal and name is done by the server; a Map keeps the order
 * of insertion. Whoever inserts individually here must therefore sort
 * themselves, otherwise a newly created list slips to the end and jumps
 * back to its place on the next full load.
 */
/**
 * What a list must bring along so it can be drawn.
 *
 * STANDS UP HERE because two places need it: creating in the window and
 * replaying the queue after a reload. A list from a dead zone would
 * otherwise have no color and no symbol — the server only sets both when
 * answering, and the answer is still pending.
 */
const LIST_DEFAULT = { color: '#8a97a4', symbol: 'circle', total: 0, fields: [], is_species: false };

function applyLists(list) {
    // As with the points: what still waits lies above the server's state.
    // A list born in a dead zone thus survives the reload — and with it the
    // assignment of the points laid into it.
    list = queue.replay('lists', list, LIST_DEFAULT);

    const sorted = [...list].sort((a, b) =>
        (a.sort_order ?? 0) - (b.sort_order ?? 0) || String(a.name).localeCompare(String(b.name)));
    state.lists = new Map(sorted.map(l => [l.id, l]));

    // RECALCULATE RIGHT HERE, not at every call site individually.
    //
    // The fresh list objects carry the server's number, and the server
    // knows nothing of what still waits in the queue (see
    // computeListCounts). This function is the only place where the tree
    // is swapped out — so it belongs here and not in the five calls above.
    computeListCounts();
    speciesNeedsSoon();
}

/**
 * WHAT THIS DEVICE KEEPS OF THE NAME PACKAGES (12.11.2026,
 * migration 0195): the groups of every species list one can write into —
 * one's own and the shared ones („ist da was drin, dann müssen die auch
 * für den Offline-Betrieb gedownloadet werden") —, and wherever there is a
 * species list, the fixed groups of „Substrat / Wirt" and
 * „Begleitvegetation" (find_fields.php). A guest records into an
 * excursion, which reads as fungi. Handed to species.js, which fetches what
 * is missing AT ONCE and takes away what no list holds any more
 * (syncPackages) — the search list itself is built only at the first
 * typed name.
 *
 * Here because this is where the lists change: applyLists runs for every
 * load, save and swap of the own table, and the shared ones stand from the
 * start data. Once a tick later, so a burst of changes asks once.
 */
let speciesNeedsTimer = 0;
function speciesNeedsSoon() {
    clearTimeout(speciesNeedsTimer);
    speciesNeedsTimer = setTimeout(speciesNeedsSync, 0);
}
function speciesNeedsSync() {
    const groups = new Set();
    let anySpecies = GUEST;
    if (GUEST) groups.add('fungi');
    for (const list of [...state.lists.values(), ...sharedLists.values()]) {
        if (!list?.is_species) continue;
        anySpecies = true;
        for (const g of listGroups(list)) groups.add(g);
    }
    if (anySpecies) {
        for (const f of start.find_fields ?? []) for (const g of f.species_groups ?? []) groups.add(g);
    }
    setNeededGroups([...groups]);
    if (navigator.onLine) syncPackages();
}

/**
 * Compute the number per list from the points that lie here anyway.
 *
 * WHY THIS IS A FUNCTION AT ALL. After every create, change and delete of
 * a point, the entire list table was fetched anew from the server — for
 * nothing but this number. On the phone that is 150 to 400 milliseconds of
 * waiting per action, in which the browser obtains information it already
 * has: which point lies in which list stands in state.points.
 *
 * drawList() has always computed the number for "Ohne Liste" itself.
 * Nothing different stands here, just for the remaining lists.
 *
 * WHAT STILL WAITS IS COUNTED TOO. The server knows nothing of a point
 * from a dead zone, so its number would be too small — the point is
 * visible regardless, on the map and in the list below. A number that does
 * not match what stands next to it is worse than one running ahead of the
 * server.
 */
/**
 * The points standing directly in each list (list.total).
 *
 * NO LONGER A NUMBER ON SCREEN — the sidebar stopped showing counts on
 * 24.09.2026, and with them went the branch sums that were computed here
 * for its headings. What stays is the number the code asks for: a list
 * getting its first sublist hands its points down, deleting a list is
 * about the points that REALLY hang on it.
 *
 * FROM THE POINTS UPWARDS, not from every list across all points. The
 * second way would be lists × points — with fifty lists and five thousand
 * points a quarter million comparisons on every change.
 */
function computeListCounts() {
    const own = new Map([...state.lists.keys()].map(id => [id, 0]));
    for (const point of state.points.values()) {
        if (point.deleted_at) continue;
        for (const id of point.lists ?? []) {
            if (own.has(id)) own.set(id, own.get(id) + 1);
        }
    }
    for (const [id, list] of state.lists) {
        list.total = own.get(id) ?? 0;
    }
}

// ---------------------------------------------------------------------------
// The stock: time buckets instead of everything at once
//
// Why at all, and how the server handles it: includes/bucket.php. Only the
// device's side stands here.
//
// THE FALLBACK IS NO ORNAMENT. This is about the one question where a
// mistake is unforgivable — "are my points still there". That is why EVERY
// failure falls back to the old way: fetch everything in one piece, as the
// application did for two years. A slow start is an annoyance, an empty
// map is a loss.
// ---------------------------------------------------------------------------

/** Do not go via the buckets — because they are missing or somebody switched them off. */
function removeBucket() {
    return start.bucketsReady !== true || settings.get('bucketsOff', false) === true;
}

/**
 * Fetch the named buckets and store them.
 *
 * IN ROUNDS, because the server delivers whole buckets and returns the
 * rest as 'open' instead of cutting one open. On a cold start with ten
 * years that is a few rounds; in everyday use it is one.
 */
/**
 * How many buckets are fetched per draw.
 *
 * THE FIRST DRAW IS SMALL. On a fresh device the youngest three months are
 * almost always what somebody is looking for — the rest is archive. Three
 * buckets are around 270 kB and stand there in the blink of an eye; if
 * everything were fetched in one draw, with ten years you would see an
 * empty map for ten seconds although what you sought could long have been
 * there.
 *
 * BIGGER DRAWS AFTERWARDS, because every request has its own overhead. Two
 * years per draw stay below the limit from which the server splits by
 * itself (5,000 points) — it then does not have to hand anything in later,
 * which saves a round trip.
 */
const BUCKET_FIRST_BATCH = 3;
const BUCKET_BATCH        = 24;

/**
 * Fetch the named buckets and store them — young first.
 *
 * YOUNG FIRST IS THE WHOLE INTENT. What you look for in the field is
 * almost always from this or the last tour; a bucket from 2019 may wait.
 * The keys are "YYYY-MM", so sorting descending suffices — and because a
 * draw is drawn as soon as it is there, the map fills from the front.
 *
 * WHAT THE SERVER COULD NOT DELIVER goes back to the FRONT of the queue:
 * it only splits when a draw bursts its point limit, and then the rest is
 * still younger than everything that comes after.
 *
 * @param {(done:number, total:number) => Promise<void>} [onBatch]
 */
async function getBucket(key, onBatch = null) {
    const open  = [...key].sort().reverse();
    const total = open.length;
    let rounds = 0;

    while (open.length > 0) {
        // A bound against the loop that should not be able to exist: if the
        // server for some reason delivers nothing and reports everything as
        // open, this would run forever and the device would be unusable.
        // Generously sized — with a hundred and twenty buckets and draws of
        // twenty-four it is five rounds, not five hundred.
        if (++rounds > 500) throw new Error(t('Die Eimer kommen nicht zum Ende.'));

        const batch = open.splice(0, rounds === 1 ? BUCKET_FIRST_BATCH : BUCKET_BATCH);
        const response = await api.get('bucket', { get: batch.join(',') });

        if (Object.keys(response.buckets || {}).length === 0) {
            throw new Error(t('Der Server liefert keine Eimer mehr, meldet aber offene.'));
        }
        await pointStore.put(response.buckets);

        // What is still to come goes to the front — it is younger than the
        // unfetched rest.
        if (response.open?.length) open.unshift(...response.open);

        if (onBatch) await onBatch(total - open.length, total);
    }
}

/**
 * Bring store and server into accord.
 *
 * @param {Object<string,string>|null} fingerprints  from the server; null means
 *        fetch fresh. On page build they already stand in the document —
 *        then a sync without changes costs not a single request.
 * @param {boolean} [mitwachsen]  draw after every draw already. At startup
 *        yes, when refreshing after a sync no — there usually exactly one
 *        bucket moves, and an intermediate state would only be flicker.
 */
/**
 * Only the OWN keys of the store — without the container buckets
 * ("g<container>:<monat>", see the shared section). If the own sync
 * touched them all, it would throw every container bucket away as
 * "unknown on the server" — and the shared sync, conversely, the own
 * ones.
 */
function ownFingerprints(all) {
    return Object.fromEntries(Object.entries(all).filter(([s]) => !s.includes(':')));
}

async function ownPointsFromFiling() {
    const key = Object.keys(await pointStore.fingerprints()).filter(s => !s.includes(':'));
    return key.length > 0 ? pointStore.points(key) : [];
}

async function syncBucket(fingerprints, withProgress = false, counts = null) {
    const answer = fingerprints ? null : await api.get('bucket');
    const there  = fingerprints ?? answer.buckets;
    const sizes  = counts ?? (answer ? answer.counts : null) ?? {};
    const here = ownFingerprints(await pointStore.fingerprints());

    // What no longer exists on the server must go here. Otherwise the
    // device would keep a bucket whose points were all deleted — and keep
    // showing them.
    for (const s of Object.keys(here)) {
        if (!(s in there)) await pointStore.throwAway(s);
    }

    /* THE SAME CURE AS FOR THE SHARED ONES, and here it weighs heavier:
       these are one's OWN finds. A bucket with a matching fingerprint and
       no points counts as current and is never fetched again — the points
       would be permanently gone on this device without anything looking
       like it. put() guards the writing side against this (see there);
       this is the reading one. It surfaced on a shared container, not
       here — but the mechanics are the same, and waiting for a second
       proof would mean risking it. */
    const have  = await pointStore.bucketSizes();
    const stale = Object.keys(there).filter(s => here[s] !== there[s] || bucketShort(s, sizes[s], have));

    if (stale.length > 0) {
        await getBucket(stale, !withProgress ? null : async (done, total) => {
            // DRAW AFTER EVERY DRAW. The point of it all: after the first
            // draw the youngest finds stand on the map, instead of waiting
            // for the oldest, which you are not even looking for.
            applyInventory(await ownPointsFromFiling());
            drawMarker();
            drawList();
            computeListCounts();

            // Only report while something is really still outstanding — and
            // only if there is more than one draw at all. A notice that
            // flashes up on every start is invisible after a week.
            if (done < total) {
                status(t('Ältere Funde werden geholt … (%d von %d Zeiträumen)', done, total));
            } else if (total > BUCKET_FIRST_BATCH) {
                status(t('Alle Funde sind da.'));
            }
        });
    }

    applyInventory(await ownPointsFromFiling());
    return stale.length;
}

// ---------------------------------------------------------------------------
// The recorded tracks: the same buckets, their own store
//
// Why they get buckets at all: includes/tracks.php, above
// tracks_bucket_status(). This is the device's half, and it is the
// point sync a second time in small — compare fingerprints, throw away
// what the server no longer has, fetch what moved, young first, whole
// buckets per draw. What it does NOT have is a fallback to "everything at
// once": a map without tracks is a map, a map without points is a loss.
// ---------------------------------------------------------------------------

/**
 * How many track buckets one draw asks for.
 *
 * SMALLER THAN THE POINTS' DRAWS, because a bucket weighs what its walks
 * hold — a busy month is a few megabytes, and the server splits by
 * positions anyway (TRACK_BUCKET_MAX_POSITIONS). Six months per draw is
 * around a year of walking in two requests.
 */
const TRACK_BATCH = 6;

/**
 * @param {string[]} keys   bucket months
 * @param {object} scope    {} for the own walks, {excursion: id} or
 *                          {container: id} for the others' — the store
 *                          key then carries the same prefix the points
 *                          use (excursionPrefix, sharedPrefix), so both
 *                          stores speak one language.
 */
async function getTrackBucket(keys, scope = {}, prefix = '') {
    const open = [...keys].sort().reverse();
    let rounds = 0;
    while (open.length > 0) {
        if (++rounds > 500) throw new Error(t('Die Eimer kommen nicht zum Ende.'));
        const batch = open.splice(0, TRACK_BATCH);
        const response = await api.get('bucket', { tracks: 1, ...scope, get: batch.join(',') });
        if (Object.keys(response.buckets || {}).length === 0) {
            throw new Error(t('Der Server liefert keine Eimer mehr, meldet aber offene.'));
        }
        const named = {};
        for (const [m, set] of Object.entries(response.buckets)) named[prefix + m] = set;
        await trackStore.put(named);
        if (response.open?.length) open.unshift(...response.open);
    }
}

/** The store into the state — client_id is the key, as on the server. */
function applyTracks(list) {
    state.tracks = new Map(list.map(w => [w.client_id, w]));
    drawTracks();
}

/*
 * DRAWING THE TRACKS (11.09.2026, step two: „weiter mit dem Schalter und
 * dem Zeichnen"). The switch is an ordinary overlay in the picker
 * (registry entry 'tracks', layers.js hands its empty group out as
 * tracksLayer); what goes into the group is decided here, from
 * state.tracks.
 *
 * ON A CANVAS, NOT IN SVG, and that is the whole feasibility answer to
 * the question: three hundred walks are around six hundred
 * thousand vertices, and an SVG path per walk would give the browser six
 * hundred thousand DOM-attached nodes to hit-test and re-layout on every
 * pan. One canvas draws them in one pass, Leaflet clips every line to
 * the view and simplifies it per zoom level, and a walk that lies off
 * screen costs nothing at all.
 *
 * TWO PASSES, HALO FIRST. The sketch lines on this map wear a CSS
 * drop-shadow so they read on aerial imagery; a canvas knows no CSS, so
 * the shadow is drawn — every walk once as a wide dark line, then every
 * walk once in colour on top. All halos before any line: drawn per walk,
 * the halo of the next would cut the line of the previous wherever two
 * walks cross, and a crossing is exactly where one wants to see both.
 *
 * THE COLOUR IS THE ACCENT, read at draw time. A track is nobody's list,
 * it is the user's own path, and the accent is his colour — the same
 * statement the sidebar makes with a chosen row.
 */
let trackRenderer = null;

/*
 * THE ELEVATION PROFILE IN THE POPUP (13.09.2026: „bau doch mal
 * in die Linieninfoblase das Höhenprofil mit ein").
 *
 * DRAWN FROM THE DRAWING'S OWN VERTICES — every one of them is a recorded
 * position with its elevation, and since migration 0101 the drawing keeps
 * a crest even where the line runs straight (TRACK_DRAWING_TOLERANCE_ELE_M
 * in includes/tracks.php). So the profile comes out of the bucket like the
 * line itself, offline included, and never asks the server for the
 * original.
 *
 * SMOOTHED OVER FIVE VERTICES — about a hundred metres of walking —
 * because the receiver's height is the noisiest thing it reports.
 * Measured on the walk of 12.09.2026: neighbouring positions differ by
 * four metres in the median and twelve at the ninetieth percentile, on a
 * walk whose whole range is 57 m. Drawn raw the profile is a saw, and the
 * ascent summed from the raw heights is 929 m on a walk that climbs
 * perhaps a hundred and fifty; smoothed it is still 250. WHICH IS WHY NO
 * ASCENT FIGURE STANDS BESIDE THE CHART: a number wrong by a factor of
 * two to six looks exactly as trustworthy as one that is right. The chart
 * shows the shape, which is honest, and the two heights that frame it.
 *
 * THE TAPPED SPOT IS MARKED — a thin bar where the tap lies along the
 * walk — so the chart and the map say the same „hier".
 *
 * THE HEIGHT AXIS IS NOT STRETCHED TO THE DATA (13.09.2026,
 * on the first version, which did exactly that: „die Kurve sieht jetzt
 * extrem dramatisch aus, obwohl die Strecke nur über fünfunddreißig
 * Meter Höhenunterschied ging"). Every profile exaggerates heights —
 * drawn true to scale, 35 m over 4.4 km is a line — but a chart that
 * fills its height with whatever range it finds makes every stroll a
 * mountain and keeps no difference between the two. So the axis spans
 * at least what a FOURFOLD exaggeration allows: the chart is 300 wide
 * and 52 tall, and its height stands for at least a fourth of the
 * ground the width stands for, scaled by that ratio. For the 4.4 km walk
 * that is 190 m of axis, and its 35 m sit in a seventh of the height —
 * a flat walk with a few gentle bumps, which is what it was. A real
 * climb of 150 m over 6 km fills more than half. The axis ends on round
 * numbers, and those are the two labels: they say the scale, so a flat
 * band reads as flat and not as „nothing measured".
 *
 * SMOOTHED BY DISTANCE, not by vertex count: fifty metres of walk to
 * either side, however many vertices the drawing kept there.
 *
 * An SVG element built by the DOM, not written out as a tag: it is a
 * canvas for user data like the spore plot, not an icon (tests/icons.php
 * counts the written-out tags, comments included).
 */
const TRACK_PROFILE_W = 300;
const TRACK_PROFILE_H = 64;
const TRACK_PROFILE_EXAGGERATION = 4;
const TRACK_PROFILE_SMOOTH_M = 50;

function trackProfile(w, spot = null) {
    const pts = w.drawing ?? [];
    if (pts.length < 3) return null;
    // Distance along the drawing for every vertex; the heights only from
    // those that have one — a vertex without lends no height and keeps
    // its distance.
    const dist = [0];
    for (let i = 1; i < pts.length; i++) {
        dist.push(dist[i - 1] + L.latLng(pts[i - 1][0], pts[i - 1][1]).distanceTo([pts[i][0], pts[i][1]]));
    }
    const total = dist[pts.length - 1];
    const known = [];
    for (let i = 0; i < pts.length; i++) if (Number.isFinite(pts[i][2])) known.push(i);
    if (known.length < 3 || total < 50) return null;

    const ele = known.map((i) => {
        let sum = 0, n = 0;
        for (const j of known) {
            if (Math.abs(dist[j] - dist[i]) <= TRACK_PROFILE_SMOOTH_M) { sum += pts[j][2]; n++; }
        }
        return sum / n;
    });
    let lo = Infinity, hi = -Infinity;
    for (const e of ele) { if (e < lo) lo = e; if (e > hi) hi = e; }

    const W = TRACK_PROFILE_W, H = TRACK_PROFILE_H, top = 6, bottom = 58;
    // The axis: the data's range, but at least what the exaggeration cap
    // allows — and ten metres, so a level walk is not a wall — ending on
    // round numbers around the data's middle.
    const span = Math.max(hi - lo, total * ((bottom - top) / W) / TRACK_PROFILE_EXAGGERATION, 10);
    const step = span < 60 ? 5 : span < 150 ? 10 : span < 400 ? 50 : 100;
    const mid = (hi + lo) / 2;
    const axisLo = Math.floor((mid - span / 2) / step) * step;
    const axisHi = Math.ceil((mid + span / 2) / step) * step;
    const X = i => (dist[i] / total * W).toFixed(1);
    const Y = e => (bottom - (e - axisLo) / (axisHi - axisLo) * (bottom - top)).toFixed(1);
    const line = known.map((i, k) => `${X(i)},${Y(ele[k])}`).join(' L');
    const m = v => Math.round(v).toLocaleString('de-DE') + ' m';

    let here = '';
    if (spot) {
        // The vertex nearest the tap, by ground distance — a few metres
        // beside the line at most, the popup opened on it.
        let near = 0, nearD = Infinity;
        const tap = L.latLng(spot.lat, spot.lng);
        for (let i = 0; i < pts.length; i++) {
            const d = tap.distanceTo([pts[i][0], pts[i][1]]);
            if (d < nearD) { nearD = d; near = i; }
        }
        here = `<line class="track-profile-here" x1="${X(near)}" y1="${top}" x2="${X(near)}" y2="${bottom}"/>`;
    }

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'track-profile');
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', t('Höhenprofil, %s bis %s', m(lo), m(hi)));
    svg.innerHTML =
          `<path class="track-profile-area" d="M${X(known[0])},${bottom} L${line} L${X(known[known.length - 1])},${bottom} Z"/>`
        + `<path class="track-profile-line" d="M${line}"/>`
        + here
        + `<text class="track-profile-label" x="3" y="${top + 8}">${m(axisHi)}</text>`
        + `<text class="track-profile-label" x="3" y="${bottom - 2}">${m(axisLo)}</text>`;
    return svg;
}

/** What a walk says about itself when tapped. Built as nodes: the name is user data. */
function trackLabel(w, cuttable = false, spot = null) {
    const box = document.createElement('div');
    box.className = 'track-popup';
    const name = document.createElement('strong');
    name.textContent = w.name || t('Wegstrecke');
    const facts = document.createElement('div');
    facts.className = 'hint-text small';
    const parts = [];
    if (w.started_at) parts.push(readableDate(w.started_at));
    if (w.distance_m !== null && w.distance_m !== undefined) {
        parts.push((w.distance_m / 1000).toFixed(1).replace('.', ',') + ' km');
    }
    if (w.started_at && w.ended_at) {
        const ms = new Date(w.ended_at.replace(' ', 'T')) - new Date(w.started_at.replace(' ', 'T'));
        if (ms > 0) parts.push(readableTimeSpan(ms));
    }
    // 'App: Pixel im Rucksack' is how the pairing files a device; the
    // prefix is bookkeeping, the name is what the user typed.
    if (w.device) parts.push(String(w.device).replace(/^App:\s*/, ''));
    facts.textContent = parts.join(' · ');
    box.append(name, facts);
    if (trackForeign(w)) {
        // Whose it is stands first among the facts of a foreign walk — and
        // it is read-only: cutting, deleting and filing are the recorder's.
        const whose = document.createElement('div');
        whose.className = 'hint-text small';
        whose.textContent = t('von %s', personName(w.owner));
        box.append(whose);
        cuttable = false;
    }

    /* THE OUTING (migration 0099, 12.09.2026): tour and excursion, as the
       finds carry them — derived on upload, corrected here. Shown only
       once the fields exist; a track from before the migration says
       nothing rather than „keine". */
    if ('tour_id' in w) {
        const outing = document.createElement('div');
        outing.className = 'hint-text small track-popup-outing';
        const bits = [];
        if (w.tour_id) bits.push(t('Tour: %s', w.tour || t('Tagestour')));
        const e = w.excursion_id ? excursionStart.find(x => x.id === w.excursion_id) : null;
        if (w.excursion_id) bits.push(t('Exkursion: %s', e?.name ?? '#' + w.excursion_id));
        outing.textContent = bits.length ? bits.join(' · ') : t('keiner Tour zugeordnet');
        box.append(outing);
    }

    // The profile, between the facts and the deeds — see trackProfile().
    const profile = trackProfile(w, spot);
    if (profile) box.append(profile);

    /* CUT AND DELETE, RIGHT IN THE POPUP (12.09.2026: „ich hätte
       gerne die Wegstrecken bearbeitbar, und zwar mit Teilen an einem
       Punkt und dann Löschen"). The case is the forgotten recording — the
       drive home is on the walk. Tap the line where the walk ended, cut,
       tap the tail, delete: two taps and a question. Two cuts and the
       middle piece answer the drive between two sites the same way.

       „Hier teilen" cuts at the tapped spot, and the SPOT is all that
       travels: the map holds the drawing — a sieved, simplified copy —
       while the cut goes into the original on the server, and an index
       into the one would point somewhere else in the other. The server
       projects the spot onto the nearest segment of the original
       (track_nearest_segment). `cuttable` only says whether the line is
       long enough to have a middle.

       THE STUB KEEPS ITS BUTTONS (13.09.2026, after cutting off
       the calibration at a recording's start: „vier Sekunden lang, null
       Komma null Kilometer — wenn ich dieses kleine Stück anklicke,
       bekomme ich nicht die Lösch-Buttons"). The whole row hung on
       cuttable, so a piece of two positions — exactly what a cut leaves
       behind, and exactly what one wants to delete next — offered
       nothing. Only the cut needs a middle; deleting and filing do not.
       Foreign walks show no row at all, as before. */
    if (!trackForeign(w)) {
        const row = document.createElement('div');
        row.className = 'button-row track-popup-actions';
        if (cuttable) {
            const cut = document.createElement('button');
            cut.type = 'button';
            cut.className = 'button small';
            /* „Teilen", no longer „Hier teilen" (13.09.2026: „versuch mal
               alle drei Buttons in eine Zeile zu setzen") — measured, the
               three with their icons wanted 327 px of the popup's 320, and
               the third dropped to a second line. The popup opens ON the
               spot; the title keeps the „hier" for the pointer. */
            cut.title = t('An dieser Stelle teilen');
            cut.append(icon('scissors'), document.createTextNode(t('Teilen')));
            cut.addEventListener('click', () => trackSplit(w, spot));
            row.append(cut);
        }
        const drop = document.createElement('button');
        drop.type = 'button';
        drop.className = 'button small danger';
        drop.append(icon('delete'), document.createTextNode(t('Löschen')));
        drop.addEventListener('click', () => trackDelete(w));
        row.append(drop);
        if ('tour_id' in w) {
            const file = document.createElement('button');
            file.type = 'button';
            file.className = 'button small';
            file.append(icon('pencil'), document.createTextNode(t('Zuordnen')));
            file.addEventListener('click', () => trackAssignAsk(w));
            row.append(file);
        }
        box.append(row);
    }
    return box;
}

/**
 * Ask which outing a walk belongs to, and tell the server.
 *
 * WHAT IS OFFERED: the tours of the user's finds within a day of the walk
 * — an outing that stretched past midnight is still on offer — and every
 * excursion he is on, plus „keine". Choosing a tour leaves the excursion
 * as it is and the other way round; only „keine" clears both. That is
 * what track_assign() reads out of the keys that are sent.
 */
async function trackAssignAsk(w) {
    map.closePopup();
    const day = trackDay(w);
    const near = (tag) => {
        if (!day || !tag) return false;
        const a = new Date(day + 'T12:00:00'), b = new Date(tag + 'T12:00:00');
        return Math.abs(a - b) <= 36 * 3600 * 1000;
    };
    const options = [];
    for (const row of timeRange.collectTours([...state.points.values()].filter(p => !p.deleted_at))) {
        if (!row.tour || !row.tour.startsWith('t:') || !near(row.tag)) continue;
        options.push({ value: row.tour, label: row.label || t('Tagestour'),
                       hint: t('Tour vom %s', timeRange.readableDay(row.tag)) });
    }
    for (const e of excursionStart) {
        options.push({ value: 'e:' + e.id, label: e.name,
                       hint: t('Exkursion ab %s', readableDate(e.starts_at)) });
    }
    options.push({ value: '', label: t('Keine Zuordnung'), hint: t('Tour und Exkursion lösen') });
    const pick = await choose({
        title: t('Wegstrecke zuordnen'),
        text: t('Zu welcher Tour oder Exkursion gehört „%s"?', w.name || t('Wegstrecke')),
        options,
    });
    if (pick === null) return;
    const body = { action: 'assign', id: w.client_id };
    if (pick === '') { body.tour_id = null; body.tour = null; body.excursion_id = null; }
    else if (pick.startsWith('t:')) {
        body.tour_id = pick.slice(2);
        body.tour = options.find(o => o.value === pick)?.label ?? '';
    } else body.excursion_id = Number(pick.slice(2));
    try {
        await request('tracks.php', { method: 'POST', body: JSON.stringify(body) });
    } catch (e) {
        status(e?.message || t('Ohne Verbindung lässt sich nichts zuordnen.'), true);
        return;
    }
    await syncTracks(null);
    status(t('Zugeordnet.'));
}

/**
 * Whether a walk can be cut at all: a line needs a middle to be cut in.
 *
 * WHICH SEGMENT the tap meant used to be decided here, in screen space
 * (12.09.2026: „dass ich auch zwischendrin trennen kann"), and
 * sent along as an index. Since the drawing is a simplified copy and the
 * cut goes into the original, the index would point into the wrong array
 * — the server searches the original itself, in metres, from the spot
 * (track_nearest_segment). What is left here is the one question the
 * drawing can still answer: is there a middle.
 */
function trackCuttable(w) {
    return (w.drawing ?? []).length >= 3;
}

async function trackSplit(w, spot) {
    map.closePopup();
    if (!spot) return;
    /* SPLITTING NEEDS THE SERVER, and it says so instead of queueing: the
       halves get their ids there (track_split), and a cut that waits in
       a queue would show a walk in one piece that the user has just cut. */
    try {
        await request('tracks.php', {
            method: 'POST',
            // The spot alone: the server finds the segment of the original
            // it lies on and cuts between its two positions, or at one of
            // them when the spot is as good as it (track_split_between).
            body: JSON.stringify({ action: 'split', id: w.client_id,
                                   spot: [spot.lat, spot.lng] }),
        });
    } catch (e) {
        status(e?.message || t('Ohne Verbindung lässt sich nichts teilen.'), true);
        return;
    }
    await syncTracks(null);
    status(t('Geteilt — zwei Strecken. Tippe die, die weg soll.'));
}

async function trackDelete(w) {
    map.closePopup();
    const ok = await confirm({
        title: t('Wegstrecke löschen?'),
        text: t('„%s" vom %s wird endgültig gelöscht — eine Strecke ist eine Aufzeichnung, kein Fund, und hat keinen Papierkorb.',
            w.name || t('Wegstrecke'), w.started_at ? readableDate(w.started_at) : '—'),
        button: t('Löschen'),
        danger: true,
    });
    if (!ok) return;
    // Gone from the picture at once; the store follows with the next sync,
    // whose fingerprint for that month has moved.
    state.tracks.delete(w.client_id);
    drawTracks();
    const answer = await api.remove('tracks', w.client_id);
    if (!answer?.pending) await syncTracks(null);
    status(t('Wegstrecke gelöscht.'));
}

/*
 * THE POINT FILTER REACHES THE TRACKS (11.09.2026, step three: „Über den
 * Punktefilter kann man dann eben seine Tagesexkursion oder sein Gebiet,
 * seine Läufe dort filtern"). No second filter beside the first — the
 * same rows, the same state, read a second time for a different kind of
 * thing — how, stands above visibleTracks().
 */

/*
 * A WALK FOLLOWS ITS FINDS (12.09.2026, the third word on the
 * matter after „der Höhenfilter soll die Strecke gar nicht berühren" and
 * „die anderen auch nicht": „Können wir es so machen, dass wenn auf der
 * Strecke ein Punkt liegt — Datum und Punktdatum übereinstimmen —, wir
 * die Strecke auf jeden Fall mit anzeigen, und wenn alle ihre Punkte
 * ausgefiltert werden, die Strecke auch nicht?").
 *
 * So the filter never asks a walk anything. It asks the finds, as it
 * always has, and the walk goes where its finds go: a walk with a shown
 * find on it stays, a walk whose finds are all sifted out goes. That
 * answers every filter at once — list, species, time, tour, area,
 * search — without a second rule for any of them, and it cannot drift
 * from what the markers show, because it reads the same two tests the
 * markers pass (pointVisible, sharedVisible).
 *
 * WHICH FINDS LIE ON A WALK — BY ITS SPAN, NOT BY ITS DAY
 * (16.09.2026: „ich habe an einem Tag zwei Touren gemacht, sogar drei —
 * und nur der Tag wird ausgefiltert, nicht aber die Strecke an sich").
 * The first cut matched by the calendar day alone, and with three walks
 * on one day every find of the day kept all three: a genus searched for
 * lit up the two walks it was never found on. Now a find lies on the
 * walks of its day whose span (started_at … ended_at) contains its
 * moment. A find of that day that no span contains — noted at home in
 * the evening, or a minute before the recorder was started — goes to the
 * walk of that day it is nearest to in time, so no find is orphaned and
 * a day with one walk still behaves exactly as before. Several walks may
 * hold the same find where their spans overlap (two friends recording
 * the same outing); that is right, both walked past it.
 *
 * A WALK WITHOUT ANY FIND HAS NOTHING TO FOLLOW. It answers the calendar
 * (the time range) and the chosen tour, and beyond that only whether a
 * sifting condition stands at all: while none does, an outing without a
 * harvest still appears when its month is chosen; as soon as a word, a
 * rank, a band, a place or a mark is being searched for, it goes — it
 * cannot carry a matching find, and the maintainer's ask was that the filter
 * „auch die Wegstrecken ausfiltert, auf denen es keine passenden Punkte
 * gibt". The gates of a foreign walk (share, excursion) stand in front
 * of all of this, exactly as the foreign finds' do.
 */

/** The day a walk began — its find day, in the words of the time filter. */
function trackDay(w) {
    return timeRange.dayOf({ found_at: w.started_at, created_at: w.created_at });
}

/** A walk recorded by somebody else — arrived through an excursion or a share. */
function trackForeign(w) {
    return w.owner_id != null && w.owner_id !== start.user?.id;
}

/* WHICH WALK A FIND LIES ON lives in timerange.js since 26.09.2026
   (walksOf) — beside the tours whose rule it follows, and where
   tests/timerange.mjs can hold it: the rule got a third question that
   day, whose walk it is, and that deserves a test the map cannot give. */

/* THE FOREIGN GATES: a foreign walk shows where its finds show — through
   the shared list it arrived by (via_list, the container's checkmark) or
   through its excursion (the excursion's checkmark), the same two gates
   the foreign points pass (sharedVisible). */
function trackGateOpen(w) {
    if (!trackForeign(w)) return true;
    if (w.via_list != null) return state.visibleShared.has(w.via_list);
    if (w.excursion_id != null) {
        return state.visibleExcursions.has('all') || state.visibleExcursions.has(w.excursion_id);
    }
    return false;
}

/* The groups that sift finds by what they are — everything but the time
   group, which a walk without finds answers itself (calendar and tour).

   DERIVED FROM THE FILTER, NOT WRITTEN OUT (23.09.2026: „die
   Wegstrecken, auf denen keine Pilze liegen, werden nicht ausgeblendet,
   egal über welchen Filter"). The list stood here by hand, and the
   edibility group of 17.09.2026 never made it in: with „Speisepilz"
   chosen, every walk without a single find kept standing. Every group
   filterGroupCount knows is a sifting one unless it is time — so the
   next group added cannot be forgotten here. */
const TRACK_SIFTING_GROUPS = Object.keys(filterGroupCount).filter(k => k !== 'time');

/** A walk that carries no find at all: calendar, tour, and no sifting. */
function trackAloneVisible(w) {
    if (state.noTime) return false;
    if (TRACK_SIFTING_GROUPS.some(k => filterGroupCount[k]() > 0)) return false;
    // tourMatches reads tour_id, tour and excursion_id — a walk carries
    // all three (migration 0099), so the same test serves. Its day goes in
    // by hand: a walk has no find time, and the day of others asks for it.
    if (!tourMatches(w, trackDay(w))) return false;
    return timeRange.inTimeRange(trackDay(w), state.timeRange.from, state.timeRange.to);
}

/** The client_ids of the walks that show right now. */
function visibleTracks() {
    const lists = visibleBranch();
    const byDay = new Map();
    for (const w of state.tracks.values()) {
        const d = trackDay(w);
        if (d === null) continue;
        if (!byDay.has(d)) byDay.set(d, []);
        byDay.get(d).push(w);
    }
    const carrying = new Set();   // walks with any find on them
    const lit      = new Set();   // walks with a SHOWN find on them
    const take = (p, visible) => {
        if (p.deleted_at) return;
        const walks = byDay.get(timeRange.dayOf(p));
        if (!walks) return;
        for (const w of timeRange.walksOf(walks, p, start.user?.id ?? null)) {
            carrying.add(w.client_id);
            if (visible) lit.add(w.client_id);
        }
    };
    for (const p of state.points.values())       take(p, pointVisible(p, lists));
    for (const p of state.sharedPoints.values()) take(p, sharedVisible(p));

    const shown = new Set();
    for (const w of state.tracks.values()) {
        if (!trackGateOpen(w)) continue;
        if (carrying.has(w.client_id) ? lit.has(w.client_id) : trackAloneVisible(w)) shown.add(w.client_id);
    }
    return shown;
}

/* REDRAWN ONLY WHEN THE FILTER CHANGED. drawMarker() runs on every pan,
   and rebuilding six hundred thousand vertices for a pan would undo the
   whole canvas argument. The signature is the answer itself — which walks
   show — so whatever condition moved it, this string moves, and nothing
   else can make it move: a few hundred ids to stringify, under a
   millisecond, against a redraw that is not. */
let trackFilterSignature = null;

function trackFilterSignatureOf(shown) {
    return JSON.stringify([...shown].sort());
}

function drawTracksIfFilterChanged() {
    if (!tracksLayer || !map.hasLayer(tracksLayer)) return;
    if (trackFilterSignatureOf(visibleTracks()) === trackFilterSignature) return;
    drawTracks();
}
afterMarkerDraw.push(drawTracksIfFilterChanged);

/* The two strokes of a walk at rest, and the same two lifted while its
   popup stands open (12.09.2026: „vielleicht auch ein kleines
   Highlighten, wenn eine Strecke markiert ist"). Lifted means: the line a
   little wider and fully opaque, the dark halo turned into a light one —
   a glow rather than a colour change, so the accent still says „walk"
   and the glow says „this one". */
const TRACK_HALO = { color: '#000', opacity: .55, weight: 6 };
const TRACK_LINE = { opacity: .9, weight: 3 };
const TRACK_HALO_LIT = { color: '#fff', opacity: .95, weight: 9 };
const TRACK_LINE_LIT = { opacity: 1, weight: 4 };
let trackSelected = null;

function trackSelect(next) {
    if (trackSelected) {
        trackSelected.halo?.setStyle(TRACK_HALO);
        trackSelected.line?.setStyle(TRACK_LINE);
    }
    trackSelected = next;
    if (next) {
        next.halo?.setStyle(TRACK_HALO_LIT);
        next.line?.setStyle(TRACK_LINE_LIT);
        // Not brought to the front: the dots for start and end are drawn
        // after every line and stay on top, glow or no glow.
    }
}
// The popup is the selection; closing it — by a tap elsewhere, Escape,
// the cross — lets the walk sink back.
map.on('popupclose', () => trackSelect(null));

/**
 * The opposite of a colour — its hue turned half a circle, lightness and
 * saturation kept. Hex in, hex out; anything that is not a hex colour
 * comes back as it came, so a theme written in another notation still
 * draws something rather than nothing.
 */
function hueOpposite(hex) {
    const m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(hex.trim());
    if (!m) return hex;
    const h6 = m[1].length === 3 ? [...m[1]].map(c => c + c).join('') : m[1];
    const [r, g, b] = [0, 2, 4].map(i => parseInt(h6.slice(i, i + 2), 16) / 255);
    const max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2;
    if (max === min) return hex;   // grey has no hue to turn
    const d = max - min;
    const sat = d / (l > .5 ? 2 - max - min : max + min);
    let h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
    h = (h / 6 + .5) % 1;
    const q = l < .5 ? l * (1 + sat) : l + sat - l * sat, p = 2 * l - q;
    const channel = (t) => {
        t = (t + 1) % 1;
        const v = t < 1 / 6 ? p + (q - p) * 6 * t : t < .5 ? q : t < 2 / 3 ? p + (q - p) * (2 / 3 - t) * 6 : p;
        return Math.round(v * 255).toString(16).padStart(2, '0');
    };
    return '#' + channel(h + 1 / 3) + channel(h) + channel(h - 1 / 3);
}

function drawTracks() {
    if (!tracksLayer) return;
    tracksLayer.clearLayers();
    const shown = visibleTracks();
    trackFilterSignature = trackFilterSignatureOf(shown);
    if (!map.hasLayer(tracksLayer)) return;

    trackRenderer ??= L.canvas({ padding: 0.5 });
    const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#96ff50';
    /* A FOREIGN WALK IN THE ACCENT'S OPPOSITE HUE (14.09.2026).
       It was dashed, and the dashes were a mistake he saw at once: a dash
       pattern is counted in screen pixels along the line, and when the
       map zooms the line's screen length changes — so the pattern
       re-phases, strokes and gaps wander along the walk, and a track full
       of bends flickers („wirkt sehr unruhig"). A colour holds still.

       THE OPPOSITE HUE AND NOT A FIXED SECOND COLOUR: the accent is the
       user's chosen dress and differs per theme, so a fixed colour would
       be a stranger in every theme and the accent's twin in one — a blue
       line beside the steelblue accent. Turning the hue half a circle
       keeps lightness and saturation and gives every theme its own
       farthest colour: violet beside lime, orange beside steelblue,
       turquoise beside red. The same move the chat bubbles make with
       twenty degrees (map.css, .chat-message.is-mine), only all the way
       round, because here the two must be told apart at a glance, not
       merely feel related. The black halo under every line keeps any hue
       readable on aerial imagery and topographic maps alike. */
    const foreignTone = hueOpposite(accent);

    const lines = [];
    const stubs = [];   // walks of a single position — no line to draw, see below
    for (const w of state.tracks.values()) {
        if (!shown.has(w.client_id)) continue;
        // The drawing, not the original: the buckets carry the sieved and
        // simplified line (includes/tracks.php, track_drawing) — the
        // original never leaves the server, and this loop is the reason.
        const latlngs = (w.drawing ?? []).map(p => [p[0], p[1]]);
        if (latlngs.length >= 2) lines.push({ w, latlngs });
        else if (latlngs.length === 1) stubs.push({ w, latlng: latlngs[0] });
    }
    trackSelected = null;
    const halos = new Map();
    for (const { w, latlngs } of lines) {
        halos.set(w.client_id, L.polyline(latlngs, {
            renderer: trackRenderer, ...TRACK_HALO, interactive: false, smoothFactor: 1.5,
        }).addTo(tracksLayer));
    }
    for (const { w, latlngs } of lines) {
        // Own walks in the accent, foreign ones in its opposite (above);
        // the popup says whose.
        const line = L.polyline(latlngs, {
            renderer: trackRenderer, color: trackForeign(w) ? foreignTone : accent,
            ...TRACK_LINE, smoothFactor: 1.5,
        }).addTo(tracksLayer);
        // The popup is built on the tap, not bound in advance: it carries
        // the tapped spot, and that is only known then.
        line.on('click', (e) => {
            L.DomEvent.stop(e);
            trackSelect({ line, halo: halos.get(w.client_id) });
            L.popup({ maxWidth: 320 })
                .setLatLng(e.latlng)
                .setContent(trackLabel(w, trackCuttable(w), e.latlng))
                .openOn(map);
        });
    }
    /* START AND END OF EVERY WALK (12.09.2026, after the first
       cut: „man sieht die Trennung nicht, weil alles eine Farbe ist — wir
       sollten den Wegstrecken einen Start- und Endpunkt geben, damit man
       sieht, wo getrennt wurde"). Two halves that share a position draw
       as one unbroken line; the dots say where a recording begins and
       ends, and a cut is exactly that — one end, one beginning, on the
       same spot. The start is filled in the accent, the end is dark with
       an accent ring: the walk reads in one direction. NOT INTERACTIVE:
       a dot on top of the line would catch the tap the line is meant to
       get, and the popup belongs to the line. Drawn AFTER all lines so
       no later line covers them. The dots wear the walk's colour — a
       foreign walk's ends in the foreign tone, or they would look like
       the ends of somebody else's line. */
    for (const { w, latlngs } of lines) {
        const tone = trackForeign(w) ? foreignTone : accent;
        L.circleMarker(latlngs[0], {
            renderer: trackRenderer, radius: 5, weight: 2, color: '#000', opacity: .7,
            fillColor: tone, fillOpacity: 1, interactive: false,
        }).addTo(tracksLayer);
        L.circleMarker(latlngs[latlngs.length - 1], {
            renderer: trackRenderer, radius: 5, weight: 2.5, color: tone, opacity: 1,
            fillColor: '#000', fillOpacity: .85, interactive: false,
        }).addTo(tracksLayer);
    }
    /* A WALK OF ONE POSITION IS A DOT, AND THE DOT CAN BE TAPPED
       (13.09.2026, found beside the maintainer's stub: three test recordings
       of a single fix lay in the store and were nowhere — a line needs
       two ends, and the dots above are deliberately deaf). Such a walk
       is a recording that never got going, and the one thing anyone
       wants of it is the delete button; so it draws as the start dot,
       interactive, with the same popup as a line — minus the cut, which
       needs a middle. */
    for (const { w, latlng } of stubs) {
        L.circleMarker(latlng, {
            renderer: trackRenderer, radius: 6, weight: 2, color: '#000', opacity: .7,
            fillColor: trackForeign(w) ? foreignTone : accent, fillOpacity: 1,
        }).on('click', (e) => {
            L.DomEvent.stop(e);
            L.popup({ maxWidth: 320 })
                .setLatLng(e.latlng)
                .setContent(trackLabel(w, false, e.latlng))
                .openOn(map);
        }).addTo(tracksLayer);
    }
}

/*
 * THE RUNNING RECORDING, LIVE FROM THE SHELL (12.09.2026: „die
 * Daten vom Rekorder direkt live auch im Offlinebetrieb auf der Karte").
 *
 * The app's shell hands the page a bridge, window.yasaimapp (its CLAUDE.md
 * writes the shape down): recording() says whether a recording runs and
 * under which tour, positions() gives every position recorded so far —
 * from the file on the phone, so it works without reception — and the
 * document event 'yasaimapp:recording' fires on every change, carrying
 * the same object plus `last`, the newest position. So: read positions()
 * once, append `last` on every event, draw. The drawing is the map's —
 * the same strokes the stored tracks get, with another source.
 *
 * ALWAYS ON THE MAP WHILE IT RUNS, whatever the „Wegstrecken" switch
 * says. That switch answers „show me my recorded walks", and a hundred of
 * them may well be off while one is being walked; the running one is not
 * a stored walk but where I am going right now — the same standing as the
 * location marker, and drawn beside it.
 *
 * NOT ONLY AT LOAD. The bridge may be wired after this module has run,
 * and a recording may start while the page is open; the first event with
 * running: true is therefore also a start and reads positions() then.
 * Outside the shell there is no bridge, no event, and nothing here runs.
 *
 * AND THE TOUR CROSSES OVER. The app asks for the tour name when the
 * recording starts, as the map asks at the first find; a find set in the
 * page during the walk must fall on THAT tour. tour.rememberTour() makes
 * it the running tour, and the list window then continues it instead of
 * asking for a new name — the same path a tour begun on the map takes.
 * The start day is the recording's, so the six-hour rule keeps naming the
 * evening the walk began.
 */
const live = { positions: [], startedAt: null, tourId: null };
let liveLayer = null;

function liveBridge() {
    return globalThis.yasaimapp ?? null;
}

/** Whatever the bridge hands out — an array, or the string Android sends. */
function liveRows(raw) {
    if (typeof raw === 'string') { try { raw = JSON.parse(raw); } catch { raw = []; } }
    return Array.isArray(raw) ? raw.filter(p => Array.isArray(p) && p.length >= 2) : [];
}

/* The three strokes of the running walk — halo, line, start — made ONCE
   and then only moved. */
let liveStrokes = null;

function liveClear() {
    live.positions = [];
    live.startedAt = null;
    live.tourId = null;
    if (liveLayer) { liveLayer.clearLayers(); }
    liveStrokes = null;
}

/* THE STROKES STAY, ONLY THEIR POINTS MOVE (24.11.2026, measured in the
   app on the vector map). Every event used to throw the three strokes
   away and draw three new ones. Leaflet does that in a blink; the vector
   map turns every new stroke into a source of its own, and a new source
   is cut into tiles in a worker before it can be shown — one to three
   seconds in the emulator. The recorder fires every one to three
   seconds, so each line was taken off before it ever appeared, and the
   walk showed nothing until the events paused. setLatLngs() hands the
   SAME source new data instead, and MapLibre keeps drawing the old tiles
   until the new ones are cut — the line grows instead of blinking. */
function liveDraw() {
    liveLayer ??= L.layerGroup().addTo(map);
    const latlngs = live.positions.map(p => [p[0], p[1]]);
    if (latlngs.length === 0) {
        liveLayer.clearLayers();
        liveStrokes = null;
        return;
    }
    // One position is a line of zero length — its round cap hides under
    // the start dot, and the strokes exist from the first fix on.
    const path = latlngs.length >= 2 ? latlngs : [latlngs[0], latlngs[0]];
    const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#96ff50';
    if (!liveStrokes) {
        trackRenderer ??= L.canvas({ padding: 0.5 });
        liveStrokes = {
            accent,
            halo: L.polyline(path, { renderer: trackRenderer, ...TRACK_HALO, interactive: false, smoothFactor: 1.5 }).addTo(liveLayer),
            line: L.polyline(path, { renderer: trackRenderer, color: accent, ...TRACK_LINE, interactive: false, smoothFactor: 1.5 }).addTo(liveLayer),
            // The start as on every walk; the head is where the location
            // marker stands, and a second dot there would only fight it.
            start: L.circleMarker(latlngs[0], {
                renderer: trackRenderer, radius: 5, weight: 2, color: '#000', opacity: .7,
                fillColor: accent, fillOpacity: 1, interactive: false,
            }).addTo(liveLayer),
        };
        return;
    }
    liveStrokes.halo.setLatLngs(path);
    liveStrokes.line.setLatLngs(path);
    liveStrokes.start.setLatLng(latlngs[0]);
    // A theme switched during the walk dresses the line anew.
    if (liveStrokes.accent !== accent) {
        liveStrokes.accent = accent;
        liveStrokes.line.setStyle({ color: accent });
        liveStrokes.start.setStyle({ fillColor: accent });
    }
}

/**
 * Fetch the walk that was just recorded — and keep trying for a minute.
 *
 * THE STOP COMES BEFORE THE UPLOAD (21.09.2026: „sobald ich
 * die Aufnahme stoppe, wird die Live-Wegstrecke ausgeblendet — das ist
 * richtig so; allerdings wird die aufgezeichnete dann nicht vom Server
 * geholt, ich muss immer einmal aktualisieren"). The bridge fires
 * `running: false` the moment the recording ends; the app uploads the
 * file right after, and a single sync fired on the event asked the server
 * before the walk had arrived there — so it found nothing, and only the
 * next page load showed the line. Now the sync is repeated at growing
 * intervals — after 1, 2, 3, 4, 5, 10, 15 and 30 seconds — until one of
 * them brings a changed bucket (syncTracks answers how many it fetched),
 * or the half minute has passed: an upload takes seconds
 * on the clearing, and without reception the next load still does it.
 */
function liveFetchUploaded() {
    // Tight at the start (21.09.2026: „ein, zwei, drei, vier,
    // fünf Sekunden, dann zehn, 15, 30") — an upload on the clearing is
    // through in a few seconds, and the line should follow it at once.
    const delays = [0, 1000, 1000, 1000, 1000, 1000, 5000, 5000, 15000];
    let n = 0;
    const attempt = async () => {
        try {
            if (await syncTracks(null) > 0) return;
        } catch { /* no reception — the next try, or the next load */ }
        if (++n < delays.length) setTimeout(attempt, delays[n]);
    };
    attempt();
}

function liveAdopt(detail) {
    const d = detail && typeof detail === 'object' ? detail : null;
    if (!d || d.running !== true) {
        const wasRunning = live.startedAt !== null;
        liveClear();
        // The walk just ended — the app uploads it now, and the sync
        // fetches it into the store; without reception the next load does.
        if (wasRunning) liveFetchUploaded();
        return;
    }
    const startedAt = Number(d.started_at) || null;
    const fresh = live.startedAt === null || startedAt !== live.startedAt;
    if (fresh) {
        live.startedAt = startedAt;
        let rows = [];
        try { rows = liveRows(liveBridge()?.positions?.()); } catch { rows = []; }
        live.positions = rows;
    }
    if (Array.isArray(d.last) && d.last.length >= 2) {
        const tail = live.positions[live.positions.length - 1];
        // The same fix twice — the event repeats it when only count or
        // metres changed — is one position, not two.
        if (!tail || tail[3] !== d.last[3] || tail[0] !== d.last[0] || tail[1] !== d.last[1]) {
            live.positions.push(d.last);
        }
    }
    /* THE GAP AFTER A DARK SCREEN (12.09.2026: „die Livestrecke
       springt, wenn das Handy aus war — die Punkte, die zwischenzeitlich
       gesammelt wurden, werden nicht nachgereicht"). While the screen is
       off the page rests, and the events of that time are gone; the next
       one brings only its own `last`, and the line leaps from the old
       tail to it. The event carries `count`, the recorder's own tally —
       whenever ours does not match it, the list is read whole again from
       the phone. That is one file read per gap, not per fix. */
    if (Number.isFinite(Number(d.count)) && Number(d.count) !== live.positions.length) {
        try { live.positions = liveRows(liveBridge()?.positions?.()); } catch { /* keep what we have */ }
    }
    if (d.tour_id && d.tour_id !== live.tourId) {
        live.tourId = String(d.tour_id);
        if (tour.activeTour()?.id !== live.tourId) {
            const day = startedAt ? tour.today(new Date(startedAt)) : tour.today();
            tour.rememberTour(String(d.tour ?? '').trim() || t('Tagestour'), day, live.tourId);
        }
    }
    liveDraw();
}

document.addEventListener('yasaimapp:recording', (e) => liveAdopt(e.detail));
/* AND ON WAKING, WITHOUT WAITING FOR AN EVENT: the recorder may go on for
   an hour before the next fix reaches a page that has just come back —
   at one fix a minute during a rest, say — and until then the line would
   still end where the screen went dark. So the page asks the bridge
   itself the moment it becomes visible again; recording() answers with
   the current count, and the mismatch above does the reading. */
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible' || live.startedAt === null) return;
    try { liveAdopt(liveBridge()?.recording?.()); } catch { /* no bridge, nothing to catch up */ }
});
try {
    const bridge = liveBridge();
    if (bridge?.recording) liveAdopt(bridge.recording());
} catch { /* a bridge that cannot answer is no bridge */ }

// Switched on: draw what the store holds. Switched off: the group is
// emptied, so six hundred thousand vertices do not sit in memory for a
// picture nobody asked for.
map.on('overlayadd', (e) => { if (e.layer === tracksLayer) drawTracks(); });
map.on('overlayremove', (e) => { if (e.layer === tracksLayer) tracksLayer.clearLayers(); });

/**
 * Bring the track store and the server into accord.
 *
 * @param {Object<string,string>|null} fingerprints  from the document, or
 *        null to fetch fresh — the same two moments as syncBucket().
 */
async function syncTracks(fingerprints, counts = null) {
    if (GUEST || start.tracksReady !== true) return 0;

    const answer = fingerprints ? null : await api.get('bucket', { tracks: 1 });
    const there  = fingerprints ?? answer.buckets;
    const sizes  = counts ?? (answer ? answer.counts : null) ?? {};
    const here   = await trackStore.fingerprints();

    // ONLY THE OWN KEYS — the ones without a colon. The others' walks lie
    // under „e<id>:" and „g<id>:" in the same store and are the business
    // of syncTracksShared(); a sweeper that took them for orphans would
    // throw them away on every start (the lesson syncShared learned).
    for (const s of Object.keys(here)) {
        if (!s.includes(':') && !(s in there)) await trackStore.throwAway(s);
    }

    // The count beside the print — the same belt as for the points.
    const have  = await trackStore.bucketSizes();
    const stale = Object.keys(there).filter(s => here[s] !== there[s] || bucketShort(s, sizes[s], have));
    if (stale.length > 0) await getTrackBucket(stale);

    await syncTracksShared(fingerprints === null);
    applyTracks(await trackStore.tracks());
    return stale.length;
}

/**
 * The others' walks — per excursion and per container, into their own
 * namespaces of the track store.
 *
 * THE TWIN OF syncExcursions() AND syncShared() IN ONE, and every source
 * on its own: one that fails must not drag the rest down. Fingerprints
 * come with the document (e.tracks, c.tracks) or fresh when asked, the
 * same two moments as for the finds. A source that vanished — the
 * leadership withdrawn, the container left — loses its buckets here.
 */
async function syncTracksShared(fresh = false) {
    if (GUEST || start.tracksReady !== true) return;
    const all   = await trackStore.fingerprints();
    const sizes = await trackStore.bucketSizes();

    const sources = [
        ...excursionStart.map(e => ({ prefix: excursionPrefix(e.id), scope: { excursion: e.id }, given: e.tracks, name: e.name })),
        ...sharedStart.map(c => ({ prefix: sharedPrefix(c.id), scope: { container: c.id }, given: c.tracks, name: c.name })),
    ];
    const known = sources.map(x => x.prefix);
    for (const s of Object.keys(all)) {
        if (s.includes(':') && !known.some(p => s.startsWith(p))) await trackStore.throwAway(s);
    }

    for (const src of sources) {
        try {
            const answer = fresh || !src.given ? await api.get('bucket', { tracks: 1, ...src.scope }) : null;
            const there  = answer ? (answer.buckets || {}) : (src.given.finger || src.given.buckets || {});
            const counts = answer ? (answer.counts || {}) : (src.given.counts || {});
            for (const s of Object.keys(all)) {
                if (s.startsWith(src.prefix) && !(s.slice(src.prefix.length) in there)) {
                    await trackStore.throwAway(s);
                }
            }
            const stale = Object.keys(there).filter(m =>
                all[src.prefix + m] !== there[m] || bucketShort(src.prefix + m, counts[m], sizes));
            if (stale.length > 0) await getTrackBucket(stale, src.scope, src.prefix);
        } catch (err) {
            console.warn('Wegstrecken von „' + src.name + '" nicht abgeglichen:', err);
        }
    }
}

/**
 * Only one works on the stock at a time.
 *
 * AT STARTUP TWO SYNCS SET OFF AT THE SAME TIME: establishInventory()
 * further down and flushWaiting(), which drains the queue and then
 * calls reloadPoints(). Both go through syncBucket(), both store
 * buckets and throw some away. On a cold start the first takes seconds —
 * so they really overlap, not just in theory.
 *
 * AND THEY COMPARE AGAINST DIFFERENT LISTS. establishInventory() takes
 * start.buckets, the state from the document; reloadPoints() fetches
 * fresh. Exactly that turns into a loss: whoever comes out of the woods
 * hands in points whose find time lies in a month that did not even exist
 * at page build.
 *
 *   reloadPoints()   sees the new bucket 2026-07, fetches it, stores it
 *   establishInventory() compares with start.buckets — no 2026-07 stands
 *                       there — and throws it away again as orphaned
 *
 * The tour just handed in would be gone. Not from the server, but from the
 * device, and on the next opening everything would look right — which
 * makes searching for it hopeless.
 *
 * A latch, not a lock: the jobs still start side by side, they just pass
 * through one after the other. And a failure does not tear the chain — the
 * fallback to allAtOnce() must still be walkable even then.
 */
let inventoryChain = Promise.resolve();

function withInventory(work) {
    inventoryChain = inventoryChain.catch(() => {}).then(work);
    return inventoryChain;
}

/**
 * Establish the stock at startup.
 *
 * TWO STEPS, AND THE FIRST ALREADY DRAWS. What lies on the device is there
 * before anything goes over the network — measured 22 ms for 20,400
 * points. Only afterwards comes comparing and catching up. Whoever opens
 * up in a dead zone still sees their map.
 */
function establishInventory() {
    return withInventory(async () => {
        await establishInventoryRun();
        /* THE TRACKS AFTER THE POINTS, in the same latch so no second
           sync of the same store can run beside it — and in a try of
           their own: whatever goes wrong here must not send the POINTS
           down the fallback path. */
        try {
            await syncTracks(start.trackBuckets || {}, start.trackBucketCounts || {});
        } catch (e) {
            console.warn('Track sync failed:', e);
        }
    });
}

async function establishInventoryRun() {
    // IF THE SERVER CANNOT DO BUCKETS, it has already sent the points along
    // (see index.php) — then there is nothing to do here.
    if (start.bucketsReady !== true) return;

    // IF IT CAN, the document came empty. Whoever switched the buckets off
    // must therefore fetch the stock THEMSELVES.
    //
    // Exactly here the first draft had a hole: it turned back at this spot
    // and left the map empty, because it confused "no buckets" with "points
    // already lie in the document". But the switch sits on the device and
    // the embedding is decided by the server — two different questions. A
    // fallback that leads into the void is worse than none: one relies on
    // it.
    if (settings.get('bucketsOff', false) === true) {
        await allAtOnce();
        return;
    }

    try {
        const local = await ownPointsFromFiling();
        if (local.length > 0) {
            applyInventory(local);
            drawMarker();
            drawList();
            computeListCounts();
        }

        // The fingerprints came with the document — no second trip outside.
        const fetched = await syncBucket(start.buckets || {}, true, start.bucketCounts || {});

        drawMarker();
        drawList();
        computeListCounts();
        /* THE OFFERS COME FROM THE STOCK, and the stock is only here now.
           Drawing on opening the section is not enough: whoever left the
           filter open finds it open again, so that moment never comes —
           the five rank boxes then stood empty for the whole visit while
           the points sat right beside them (found 26.08.2026, and only
           because the boxes were counted instead of glanced at). */
        drawTaxonomyControl();
        // A restored time or tour filter learns its name and count now.
        filterStockArrived();

        // On the very first visit no point was available yet when the view
        // was chosen. Now there is.
        if (viewWasDefault && state.points.size > 0) {
            const p = [...state.points.values()][0];
            map.setView([p.lat, p.lon], 14);
            viewWasDefault = false;
        }

    } catch (e) {
        // THE FALLBACK. Whatever went wrong — IndexedDB locked, a second
        // tab, an endpoint saying 503 —, the stock now comes in one piece.
        // Slow, but complete.
        console.warn('Bucket sync failed, fetching everything in one piece:', e);
        await allAtOnce();
    }
}

/**
 * The old way: everything at once from the server.
 *
 * Stays as the fallback line, and permanently so. This is about the one
 * question where a mistake is unforgivable — a slow start is an annoyance,
 * an empty map is a loss.
 */
async function allAtOnce() {
    try {
        applyInventory((await api.get('points')).points);
        drawMarker();
        drawList();
        computeListCounts();
        // The fallback path needs it too — same reason as above.
        drawTaxonomyControl();
        filterStockArrived();
    } catch (e) {
        if (!e?.isNetworkError) throw e;
        // Without network, what is already there stays — on the very first
        // visit thus nothing. That deserves saying, otherwise you look for
        // the fault in yourself.
        status(t('Keine Verbindung — es steht, was auf dem Gerät liegt.'), true);
    }
}

/**
 * Adopt a freshly fetched stock into the state.
 *
 * Waiting points survive this: they do not stand on the server yet, so its
 * answer would wipe them away — and with them the evidence that something
 * is there.
 *
 * BUT ONLY AS LONG AS SOMETHING IS REALLY STILL OUTSTANDING. If the server
 * refuses an entry, it flies out of the queue; the point here would
 * otherwise keep its 'pending' and its provisional id forever and be
 * brought out again on every reload. Such a ghost exists only on this
 * device, nothing can remove it — and whoever thereupon creates the same
 * point once more has it twice.
 *
 * A FUNCTION OF ITS OWN since there are two ways to the stock: via the
 * buckets and in one piece. The rule of which waiting one stays may exist
 * only once — it is the difference between "this morning's point is still
 * there" and "it is gone".
 */
function applyInventory(points) {
    const open = queue.openKeys();
    const pendingOnes = [...state.points.values()].filter(p => p.pending && open.has(p.id));
    const ghosts = [...state.points.values()]
        .filter(p => p.pending && !open.has(p.id) && !points.some(s => s.id === p.id));

    // FIRST LAY THE QUEUE OVER THE SERVER STATE — it is the more current
    // source, since it holds what has not even arrived there yet. That
    // carries the case for which state.points yields nothing as a
    // source: after a RELOAD the map is empty, and the rescue below would
    // find nothing to rescue. Exactly then the morning's tour was missing.
    points = queue.replay('points', points);

    state.points = new Map(points.map(p => [p.id, p]));

    // And after that, what stands ONLY in memory: a point whose creation is
    // currently under way. It lies in no queue — the queue only gets it
    // when the line tears off.
    for (const p of pendingOnes) {
        if (!state.points.has(p.id)) state.points.set(p.id, p);
    }

    // What is left over here never arrived. It is not reported from here
    // but out of the queue's safekeeping — see showRejected(). The
    // full content stands there, here only the half copy.
    if (ghosts.length > 0) {
        console.warn('Nie angekommen und deshalb entfernt:', ghosts);
    }
}

/** This too via the latch — see withInventory(). */
function reloadPoints() {
    return withInventory(async () => {
        try {
            if (removeBucket()) {
                applyInventory((await api.get('points')).points);
                return;
            }
            // Via the buckets: one small request for the fingerprints, then
            // only the stale ones. After a sync usually exactly one bucket
            // has moved — the current month.
            await syncBucket(null);
            // The containers along with it, fresh from the server: whoever
            // lands here wants the current state — the others' too.
            await syncShared(true);
            // And what friends show me — a new hint arrives with this round.
            await syncHints(true);
            // And the tracks: a walk uploaded by the app in the meantime
            // arrives with this round, not with the next page load.
            await syncTracks(null);
        } catch (e) {
            if (!e?.isNetworkError) throw e;
        }
    });
}

/**
 * Show a brief message. 'source' makes it possible to take one's own
 * message away again without swallowing others'.
 */
function status(text, isError = false, source = null) {
    statusRow.textContent = text;
    statusRow.classList.toggle('is-error', isError);
    statusRow.hidden = false;
    statusSource = source;

    clearTimeout(statusTimer);
    // Let errors and notices stand a bit longer — those one wants to read
    // to the end, while a confirmation only has to flash briefly.
    const duration = isError ? 6000 : (source === 'zoom' ? 4500 : 2500);
    statusTimer = setTimeout(() => {
        statusRow.hidden = true;
        statusSource = null;
    }, duration);
}

/** Hide the message of the given origin, if it still stands. */
function removeStatus(source) {
    if (statusSource !== source) return;
    clearTimeout(statusTimer);
    statusRow.hidden = true;
    statusSource = null;
}

/* LEAVING THE PAGE with unsaved input in the window — the gear to the
   administration, a reload, the tab closed. The browser asks with its
   own sentence when the event is cancelled; ours cannot stand there,
   and nothing else runs any more at this point. Same reason as
   leaveOk(), one door further out. */
window.addEventListener('beforeunload', (e) => {
    if (detailDirty()) e.preventDefault();
});

// ---------------------------------------------------------------------------
// Keyboard
// ---------------------------------------------------------------------------

/* A MODAL WINDOW OWNS THE KEYBOARD (07.10.2026: in the photo
   viewer he cancelled a mis-tapped measure with Escape, and then first the
   statistics, then the picture strip vanished). The keys below are the
   MAP's — and Escape here closed the find behind the viewer as well:
   the viewer asks the open find for its cloud recordings
   (host.pointId), found none any more, and drew an empty statistics pane
   and an empty strip. A window opened with showModal() answers its own
   Escape (its cancel event); the map stays out of it, and out of „n" and
   „/" too. `:modal` where the browser knows it, else any open dialog. */
function modalOpen() {
    try {
        return document.querySelector('dialog:modal') !== null;
    } catch {
        return document.querySelector('dialog[open]') !== null;
    }
}

document.addEventListener('keydown', (e) => {
    if (modalOpen()) return;
    if (e.key === 'Escape') {
        if (state.placing) { setPointMode(false); return; }
        if (state.selectedId !== null) closeDetail();
    }
    // No shortcut while typing in a field.
    const inField = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
    if (inField) return;

    /* S PUTS THE CURSOR INTO THE SEARCH (11.11.2026, the system in
       shortcuts.js): a single key under the left hand. It was „/" — Shift
       and 7 on a German keyboard — and led to the first filter row, which
       stands folded away most of the time. Now the search one SEES: the
       head's „Suche …" at the desk, else the filter's row where it is open.
       „N" for a new find went the same day („einen Shortcut für einen neuen
       Fund brauchen wir gar nicht"). */
    if ((e.key === 's' || e.key === 'S') && !e.metaKey && !e.ctrlKey && !e.altKey) {
        const field = [document.getElementById('coord-search'), document.querySelector('#search-rows input')]
            .find((f) => f && f.offsetParent !== null);
        if (field) {
            e.preventDefault();
            field.focus();
            field.select?.();
        }
    }
});
shortcutsProvide(() => (modalOpen() ? [] : [{ title: t('Karte'), rows: [['S', t('in die Suche')]] }]));

// ---------------------------------------------------------------------------
// Go
// ---------------------------------------------------------------------------

updateAfterArea();
// AT STARTUP TOO, not only after the first change. The server sends only
// each list's own number; the sum across the branch arises here. Without
// this call "Meine Funde" would show a 0 although a hundred finds lie
// below — until at some point a point is touched and the number becomes
// right by itself. A bug that no longer shows after the first click.
computeListCounts();
drawMarker();
drawList();
// Report right on opening when the remembered view exceeds the remembered
// map's resolution — otherwise you puzzle over the blurry image.
zoomHint();

// At the very end, not where the function stands: it accesses status() and
// with it identifiers declared as const further down. Called further up it
// aborted with "Cannot access 'status-row' before initialization" — and
// an error at the top level of a module ends its execution, everything
// following no longer ran. All you could see of it was that the map came
// up and the rest was silently missing.
spotFromUrl();

// ---------------------------------------------------------------------------
// Handing in what waits for a connection
// ---------------------------------------------------------------------------

/**
 * Work through the queue and bring the map up to date afterwards.
 *
 * This is triggered in three places: on page load, as soon as the browser
 * reports network again, and by hand via the sidebar. The button is no
 * ornament — 'online' does not fire reliably, for instance when the WiFi
 * is connected but lets nothing through.
 */
/**
 * Present to the user what the server has refused.
 *
 * IT COMES BACK UNTIL SOMEBODY HAS ACKNOWLEDGED IT. A message you miss
 * once is, with lost input, no message — then you never learn what is
 * missing. That is why the refused material lies in the queue and is
 * presented anew on every start.
 *
 * And it says WHAT was in it. Referring to "it's in the console" is, on a
 * phone, the same as saying nothing at all.
 */
function describeRejected(entry) {
    const d = entry?.data ?? {};
    const rows = [];

    if (d.title) rows.push(t('Titel: %s', d.title));
    if (d.note) rows.push(t('Notiz: %s', String(d.note).slice(0, 200)));
    if (d.lat != null && d.lon != null) {
        rows.push(t('Ort: %s, %s', Number(d.lat).toFixed(6), Number(d.lon).toFixed(6)));
    }
    if (d.name) rows.push(t('Name: %s', d.name));

    // Nothing recognizable in it? Then better the raw data than nothing.
    if (rows.length === 0) rows.push(JSON.stringify(d).slice(0, 300));
    return rows.join('\n');
}

async function showRejected() {
    const all = queue.discarded();
    if (all.length === 0) return;

    // THE KEYS ARE THE SECTION NAMES OF THE QUEUE ENTRY, and those are
    // English since queue.js speaks English. As long as 'lists'/'gebiete'
    // stood here, every refused list and every refused area was announced
    // as a bare "Eintrag".
    const what = { points: t('Punkt'), lists: t('Liste'), areas: t('Gebiet') };
    const parts = all.map((record) => {
        const type = what[record.entry?.section] ?? t('Eintrag');
        return `${type}:\n${describeRejected(record.entry)}\n${t('Grund: %s', rejectionReason(record))}`;
    });

    const single = all.length === 1;

    // NO BUTTON THAT CANNOT KEEP ITS PROMISE. An entry without a final id
    // fails again at the very next attempt, however good the connection —
    // offering "Noch einmal versuchen" for it sends the user in a circle.
    const hopeless = all.every(d => d.hoffnungslos === true);

    // With the offer to try once more. Some causes are fixed by now — the
    // deselected list back, the point restored elsewhere. And even if not:
    // the choice belongs to whoever made the input, not to the program.
    const again = await confirm({
        title: single ? t('Eine Änderung wurde nicht gespeichert')
                     : t('%d Änderungen wurden nicht gespeichert', all.length),
        text: (single
                ? t('Sie entstand, während das Gerät keine Verbindung hatte. Beim späteren Übertragen hat der Server sie zurückgewiesen — gespeichert ist davon nichts.')
                : t('Sie entstanden, während das Gerät keine Verbindung hatte. Beim späteren Übertragen hat der Server sie zurückgewiesen — gespeichert ist davon nichts.'))
            + '\n\n'
            + parts.join('\n\n'),
        button: hopeless ? t('Verstanden') : t('Noch einmal versuchen'),
        cancel: hopeless ? null : t('Verwerfen'),
    });

    // Forget only now — before this, a missed window would have cost the
    // input for good.
    queue.forgetDiscarded();

    if (again && !hopeless) {
        for (const { entry } of all) queue.enqueue(entry);
        status(t('Wird noch einmal versucht …'));
        flushWaiting();
    }
}

/**
 * Say what happened, not what the server said.
 *
 * "Punkt nicht gefunden." is true and useless — it names the answer, not
 * the situation (19.08.2026). What actually happened behind a
 * 404 is one specific thing, and it is worth a whole sentence: the record
 * no longer exists on the server. One device is holding a change from a
 * time when it still did.
 *
 * That case comes up with one account on two devices, and it is the
 * realistic one: the phone brings changes home from a tour while the desk
 * has long since tidied up. Whoever reads "not found" wonders whether
 * something is broken. Whoever reads what stands here knows it is not, and
 * can decide.
 *
 * THE SERVER'S OWN WORDING STAYS where we have nothing better to say:
 * inventing a sentence for a case we do not know would be worse than
 * passing the original along.
 *
 * THE 404 BRANCH IS ALMOST HISTORY. Since the decision of the same day, an
 * entry whose target no longer exists leaves quietly and never reaches this
 * list at all — it is settled, not failed. What still comes through here is
 * the backlog: entries that were recorded as refused BEFORE that change and
 * are lying on somebody's device. They should read properly once, and then
 * this branch will never fire again.
 */
function rejectionReason({ entry, reason, status }) {
    if (status === 404 && entry?.section === 'points') {
        return t('Diesen Punkt gibt es auf dem Server nicht mehr — er wurde dort endgültig gelöscht, vermutlich von einem anderen Gerät aus. Die Änderung von hier hat damit kein Ziel mehr. Ein erneuter Versuch ändert daran nichts; ihr Inhalt steht oben, damit er nicht verloren geht.');
    }
    if (status === 404) {
        return t('Den Eintrag gibt es auf dem Server nicht mehr — er wurde dort endgültig gelöscht. Ein erneuter Versuch ändert daran nichts.');
    }
    return reason;
}

/**
 * Replace a provisional id with the real one.
 *
 * A point gets its number from the server, and until its answer arrives it
 * carries a made-up negative one. Rehanging happens here: the entry in the
 * stock, the selection, and the detail window's memory — otherwise it
 * takes the point for a different one and overwrites input in progress.
 *
 * @param {object} [daten] What the server returned. Only what arose there
 *   is adopted: id, timestamp, converted height. Title and note do NOT
 *   come from there — they stand in the window, possibly half typed, and
 *   the server does not even know them yet.
 */
function swapPointKey(old, newKey, data = null) {
    const point = state.points.get(old);
    if (point) {
        state.points.delete(old);
        point.id = newKey;

        if (data?.pending) {
            // Only the id has changed, nothing has arrived yet.
            point.pending = true;
        } else {
            delete point.pending;
            // find_number rides along: it is minted inside the creating
            // transaction, so the answer is the FIRST and only place the
            // device can learn it from — without this line the window
            // showed the bare id until the next full load.
            for (const field of ['created_at', 'updated_at', 'elevation_m', 'elevation_source',
                                'elevation_ellipsoid_m', 'elevation_accuracy_m', 'find_number', 'tour']) {
                if (data?.[field] !== undefined) point[field] = data[field];
            }
        }
        state.points.set(newKey, point);
    }
    if (state.selectedId === old) state.selectedId = newKey;
    swapDetailKey(old, newKey);

    // And the photos still waiting for this point. They lie in a store of
    // their own that the queue does not rewrite along — without this nudge
    // they would wait for an id that will never exist.
    photos.swapPointKey(old, newKey).then((count) => {
        if (count > 0 && state.selectedId === newKey) {
            drawPhotoGallery(state.points.get(newKey));
        }
    });
    // And the originals waiting in the gallery for it (galleryoriginals.js).
    galleryOriginals.swapPoint(old, newKey);
}

/**
 * Replace a provisional list id with the real one.
 *
 * A list born in a dead zone first carries a made-up negative number — and
 * that travels further than a point's: into the 'lists' field of every
 * point laid into it, into the visibility filter, and from there into the
 * remembered settings.
 *
 * If it stayed there, the list was hidden after the next load, and nobody
 * knew why: the filter pointed at a number that never existed. The queue
 * does rewrite nested data too, but only its own entries — the stock in
 * the browser does not belong to it.
 *
 * @returns {boolean} whether the id belonged to a list at all
 */
function swapListKey(old, newKey) {
    const list = state.lists.get(old);
    if (!list) return false;

    state.lists.delete(old);
    list.id = newKey;
    delete list.pending;
    state.lists.set(newKey, list);
    applyLists([...state.lists.values()]);

    for (const point of state.points.values()) {
        if (!Array.isArray(point.lists)) continue;
        const index = point.lists.indexOf(old);
        if (index >= 0) point.lists[index] = newKey;
    }

    // And the children pointing at the provisional number. Whoever creates
    // a list and right away a sublist in it in a dead zone would otherwise
    // have a branch hanging on a number that never existed — visible
    // nowhere afterwards, since nobody finds its parent list.
    for (const list of state.lists.values()) {
        if (list.parent_id === old) list.parent_id = newKey;
    }
    if (state.collapsed.has(old)) {
        state.collapsed.delete(old);
        state.collapsed.add(newKey);
        settings.set('collapsedLists', [...state.collapsed]);
    }

    if (state.visible.has(old)) {
        state.visible.delete(old);
        state.visible.add(newKey);
        settings.set('visibleLists', [...state.visible]);
    }
    // Met on this device — the next start must not tick it again (knownLists).
    if (newKey > 0) {
        const known = settings.get('knownLists', []) || [];
        if (!known.includes(newKey)) settings.set('knownLists', [...known, newKey]);
    }

    computeListCounts();
    return true;
}

/**
 * The queue reports a real id — for a point or for a list. It does not say
 * for which; the numbers come from a shared counter, so each belongs to
 * exactly one of the two.
 */
function swapKey(old, newKey) {
    if (!swapListKey(old, newKey)) swapPointKey(old, newKey);
}

async function flushWaiting() {
    if (queue.openCount() === 0) return;

    const before = queue.openCount();

    // Points that arrived in the trash on handing in: another device
    // deleted them in the meantime. The server accepts the change anyway —
    // into the trash, so it does not get lost —, but on the map such a
    // point simply no longer shows up after the sync. Exactly this silent
    // vanishing needs a word; see below.
    const inTrash = new Set();
    const bilanz = await queue.drain(async (entry) => {
        const response = await sendEntry(entry);
        if (entry.section === 'points' && entry.type === 'update' && response?.deleted_at) {
            inTrash.add(entry.id);
        }
        return response;
    }, swapKey);

    // NO MESSAGE when it does not work out. Bad connection in the woods is
    // the normal case and no event; whoever gets addressed on every attempt
    // tunes out — and then overlooks the one message that matters. What
    // hangs stands quietly in the sidebar; see updatePendingBar().
    updatePendingBar();

    const now = queue.openCount();
    if (now === before) return;      // nothing got through



    // A REFUSED ENTRY ALSO LEAVES THE QUEUE. So a shorter queue is no
    // measure of success — see drain(). Four changes of which one got
    // through and three were refused used to be announced as "4 Änderungen
    // nachgetragen", while the warning bar stood right beside the sentence
    // and said the opposite.

    // Fetch fresh from the server: on creation, height and timestamp arose
    // there, the device does not know them.
    try {
        await reloadLists();
        await reloadPoints();
        drawMarker();
        drawList();
        if (state.selectedId !== null) {
            const p = state.points.get(state.selectedId);
            if (p) fillDetail(p);
        }
        // The conflicts arise on handing in — if a version from this device
        // has lost, the sidebar should say so NOW, not only on the next
        // page load.
        await countConflicts();
    } catch { /* then next time */ }

    updatePendingBar();

    // NOTHING SENT AND NOTHING REFUSED — then there is nothing to report.
    // The queue can get shorter without anything having happened that
    // concerns the user: entries whose target no longer exists leave
    // quietly (see drain). A sentence about them would be a message about a
    // decision they made themselves, somewhere else, some time ago. The
    // refresh above has run all the same — the map should show the state
    // the server has, whether there is anything to say about it or not.
    if (bilanz.sent === 0 && bilanz.rejected === 0) return;

    const parts = [];
    if (bilanz.sent > 0)     parts.push(bilanz.sent === 1
        ? t('1 Änderung nachgetragen')
        : t('%d Änderungen nachgetragen', bilanz.sent));
    if (bilanz.rejected > 0) parts.push(bilanz.rejected === 1
        ? t('1 Änderung hat der Server abgelehnt — siehe „Ansehen"')
        : t('%d Änderungen hat der Server abgelehnt — siehe „Ansehen"', bilanz.rejected));
    // 'wartet', NOT 'pending': this is USER TEXT. The English campaign hit
    // the German singular here while the plural next to it survived —
    // "1 pending weiter" stood on the map (23.08.2026).
    if (now > 0)             parts.push(now === 1
        ? t('1 wartet weiter')
        : t('%d warten weiter', now));
    const reason = parts.join(', ') + '.';

    if (inTrash.size > 0) {
        // Colored as a notice and thus visible longer — whoever is just
        // coming out of a dead zone is not necessarily looking at the
        // device.
        status(reason + ' ' + (inTrash.size === 1
            ? t('Eine davon betraf einen Punkt, der inzwischen im Papierkorb liegt — sie ist dort mit dabei.')
            : t('%d davon betrafen Punkte, die inzwischen im Papierkorb liegen — sie sind dort mit dabei.', inTrash.size)), true);
    } else {
        // Anything refused is worth colour: it is the one case that does not
        // sort itself out by waiting.
        status(reason, bilanz.rejected > 0);
    }
}

// ---------------------------------------------------------------------------
// Overwritten changes
//
// Two devices changed the same field; the younger version has won
// (includes/field_times.php), the losing one lies on the server. Here it is
// presented — per field, both versions side by side, and the user chooses.
// Bringing it back is an ordinary PATCH with a fresh field time.
//
// The version replaced in the process stood right next to it — it does NOT
// come back as a new entry (gesehen == alt, clean continued writing),
// otherwise the window would go in circles. Only what a third device wrote
// in between is kept in turn. Nothing is lost unseen.
// ---------------------------------------------------------------------------

let conflicts = start.conflicts || 0;

/** Fetch the counter fresh — after every save and every handing in. */
async function countConflicts() {
    if (GUEST || start.fieldTimesReady !== true) return;
    try {
        const response = await api.get('conflicts', { count: 1 });
        conflicts = response.count ?? 0;
        updatePendingBar();
    } catch { /* then next time */ }
}

function conflictFieldName(field) {
    const names = {
        title: t('Titel'), note: t('Notiz'), position: t('Ort'), lists: t('Listen'),
        // 'Fundzeit', like the field label and everything else — this was
        // the third word for one datum (23.08.2026).
        outline: t('Fläche'), found_at: t('Fundzeit'), find_number: t('Fundnummer'),
    };
    if (field.startsWith('extra.')) {
        const n = field.slice(6);
        return n.charAt(0).toUpperCase() + n.slice(1);
    }
    return names[field] ?? field;
}

/** A kept or current value, made readable. */
function conflictValueText(field, value) {
    if (field === 'position') {
        return value == null ? t('– leer –')
            : `${Number(value.lat).toFixed(6)}, ${Number(value.lon).toFixed(6)}`;
    }
    if (field === 'lists') {
        const names = (value ?? []).map((id) => state.lists.get(id)?.name ?? t('Liste %s (gelöscht)', id));
        return names.length ? names.join(', ') : t('– keine –');
    }
    if (field === 'outline') {
        return Array.isArray(value) && value.length ? t('Fläche mit %d Ecken', value.length) : t('– keine –');
    }
    if (value == null || value === '') return t('– leer –');
    if (field === 'found_at') return String(value).replace('T', ' ').slice(0, 16);
    return String(value).slice(0, 300);
}

/** What currently applies — from the stock, not from a second query. */
function conflictCurrentValue(point, field) {
    if (!point) return null;
    if (field === 'position') return { lat: point.lat, lon: point.lon };
    if (field === 'lists') return point.lists ?? [];
    if (field === 'outline') return point.outline ?? null;
    if (field.startsWith('extra.')) return point.extra?.[field.slice(6)] ?? null;
    return point[field] ?? null;
}

/** Put a brought-back value into a PATCH package. */
function conflictInPatch(patch, field, value) {
    if (field === 'position') {
        patch.lat = value?.lat;
        patch.lon = value?.lon;
    } else if (field === 'lists') {
        patch.lists = value ?? [];
    } else if (field === 'outline') {
        patch.outline = value ?? null;
    } else if (field.startsWith('extra.')) {
        patch.extra = { ...(patch.extra ?? {}), [field.slice(6)]: value ?? '' };
    } else {
        patch[field] = value ?? '';
    }
}

const conflictWindow = document.getElementById('conflict-window');
const conflictList   = document.getElementById('conflict-list');

async function showConflicts() {
    let open;
    try {
        open = (await api.get('conflicts')).open ?? [];
    } catch {
        status(t('Die überschriebenen Änderungen liegen auf dem Server — gerade nicht erreichbar.'), true);
        return;
    }
    if (open.length === 0) {
        conflicts = 0;
        return;
    }

    // BUILT WITH createElement, NOT WITH innerHTML: titles and notes from
    // the user's hand stand here — exactly what one never wants to embed in
    // markup.
    conflictList.textContent = '';

    const row = (cssClass, text) => {
        const el = document.createElement('div');
        el.className = cssClass;
        if (text) el.textContent = text;
        return el;
    };

    const byPoint = new Map();
    for (const e of open) {
        if (!byPoint.has(e.point_id)) byPoint.set(e.point_id, []);
        byPoint.get(e.point_id).push(e);
    }

    for (const [pointId, entries] of byPoint) {
        const point = state.points.get(pointId);
        conflictList.append(row('conflict-point',
            point?.title || (point ? t('Ohne Titel') : t('Punkt Nr. %s (inzwischen gelöscht)', pointId))));

        for (const e of entries) {
            const field = row('conflict-field');
            const when = e.value_time
                ? new Date(e.value_time).toLocaleString('de-DE', {
                    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
                : null;
            field.append(row('conflict-field-name', conflictFieldName(e.field)));

            const choice = (value, option, time, chosen) => {
                const label = document.createElement('label');
                // An answer row like every other — the drawn dot of app.css,
                // not the browser's grey one (.choice-row, 26.09.2026).
                label.className = 'choice-row';
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'cf-' + e.id;
                radio.value = option;
                radio.checked = chosen;
                // Nothing can be brought back to a deleted point — but
                // viewing and ticking off works.
                if (option === 'restore' && !point) radio.disabled = true;
                label.append(radio);
                const text = row('conflict-value', conflictValueText(e.field, value));
                label.append(text);
                if (time) label.append(row('conflict-time', time));
                return label;
            };

            field.append(choice(conflictCurrentValue(point, e.field), 'keep', t('jetzige Fassung'), true));
            field.append(choice(e.value, 'restore', when ? t('vom %s', when) : t('überschriebene Fassung'), false));
            conflictList.append(field);
        }
    }

    conflictWindow.dataset.ids = JSON.stringify(open.map((e) => e.id));
    conflictWindow.dataset.entries = JSON.stringify(open);
    conflictWindow.showModal();
}

document.getElementById('conflict-later').addEventListener('click', () => {
    // Later means later: nothing is confirmed as seen, the bar stays. A
    // message about lost material that can be clicked away without dealing
    // with it would be none.
    conflictWindow.close();
});

document.getElementById('conflict-form').addEventListener('submit', async () => {
    const ids       = JSON.parse(conflictWindow.dataset.ids || '[]');
    const entries = JSON.parse(conflictWindow.dataset.entries || '[]');

    // ONE package per point, not one PATCH per field: whoever brings three
    // fields back should send the server one write, not three.
    const packets = new Map();
    for (const e of entries) {
        const radio = conflictList.querySelector(`input[name="cf-${e.id}"]:checked`);
        if (radio?.value !== 'restore') continue;
        const point = state.points.get(e.point_id);
        if (!point) continue;

        if (!packets.has(e.point_id)) {
            packets.set(e.point_id, { patch: {}, times: { ...(point.field_times ?? {}) }, seen: {} });
        }
        const p = packets.get(e.point_id);
        conflictInPatch(p.patch, e.field, e.value);
        p.times[e.field]  = new Date().toISOString();
        p.seen[e.field] = point.field_times?.[e.field] ?? null;
    }

    let restored = 0;
    try {
        for (const [pointId, p] of packets) {
            p.patch.field_times  = p.times;
            p.patch.field_seen = p.seen;
            await api.patch('points', pointId, p.patch);
            restored++;
        }
        const response = await api.post('conflicts', { seen: ids });
        conflicts = response.count ?? 0;
    } catch (e) {
        status(t('Das hat nicht geklappt: %s', e.message), true);
    }

    // Pull the stock along — the brought-back values come fresh from the
    // server, together with their new field times.
    try {
        await reloadPoints();
        drawMarker();
        drawList();
        computeListCounts();
        if (state.selectedId !== null) {
            const p = state.points.get(state.selectedId);
            if (p) fillDetail(p);
        }
    } catch { /* then on the next sync */ }

    updatePendingBar();
    if (restored > 0) status(t('Zurückgeholt und gespeichert.'));
});

const queueBar = document.getElementById('queue-bar');
const queueText   = document.getElementById('queue-text');
const queueButton  = document.getElementById('queue-now');

/**
 * Quiet, frequent and persistent — in the background.
 *
 * Bad connection in the woods is the normal case and no event. Previously
 * attempts happened only on page load, on 'online' and at a button press;
 * whoever stood in a dead zone had to think of it themselves. Now the
 * application keeps trying on its own:
 *
 *   twice after 30 seconds, then every minute — for hours.
 *
 * No backing off to long intervals: an attempt without reception fails on
 * the device without even bothering the radio, and thus costs practically
 * nothing. In return, the transfer is there within a minute as soon as you
 * step out of the woods — instead of somebody waiting a quarter of an hour
 * because the interval has grown so large in the meantime.
 */
const CADENCES = [30, 30, 60];
let cadenceIndex  = 0;
let cadenceTimer = null;

function setCadence() {
    clearTimeout(cadenceTimer);
    if (queue.openCount() === 0) { cadenceIndex = 0; return; }

    const seconds = CADENCES[Math.min(cadenceIndex, CADENCES.length - 1)];
    cadenceTimer = setTimeout(async () => {
        const before = queue.openCount();
        await flushWaiting();
        // If something got through, tighten the beat again; otherwise keep
        // widening.
        cadenceIndex = queue.openCount() < before ? 0 : cadenceIndex + 1;
        setCadence();
    }, seconds * 1000);
}

/** How long something has been waiting, in words. */
function sinceWhen(timestamp) {
    const min = Math.floor((Date.now() - timestamp) / 60000);
    // Below five minutes it stays away: "3 Änderungen warten gerade eben"
    // is no information but filler. The duration only becomes interesting
    // once it is meant to make somebody wonder.
    if (min < 5)   return null;
    if (min < 60)  return t('seit %d Minuten', min);
    const hours = Math.floor(min / 60);
    if (hours < 24)  return hours === 1 ? t('seit 1 Stunde') : t('seit %d Stunden', hours);
    const days = Math.floor(hours / 24);
    return days === 1 ? t('seit 1 Tag') : t('seit %d Tagen', days);
}

/**
 * The bar is the ONLY hint — small, calm, clickable.
 *
 * No window pops up anymore: neither on the first failed attempt nor at
 * startup. Whoever wants to know what is going on taps it.
 */
function updatePendingBar() {
    const open     = queue.openCount();
    const discarded = queue.discarded().length;
    const full      = queue.full();

    queueBar.hidden = open === 0 && discarded === 0 && conflicts === 0 && !full;
    queueBar.classList.toggle('has-error', discarded > 0 || full !== null);

    /* RIGHT TO THE FRONT, BEFORE EVERYTHING ELSE. A full device store
       means: what was just entered lives only in RAM and is gone on the
       next load — see queue.js. Everything else in this bar waits or is
       already decided; this here is the only thing still avertible, and
       only as long as the page stays open. */
    if (full !== null) {
        queueText.textContent = t('Der Gerätespeicher ist voll — Eingaben gehen beim nächsten Laden verloren. Bitte jetzt übertragen.');
        queueButton.textContent = t('Was tun?');
        return;
    }

    if (discarded > 0) {
        queueText.textContent = discarded === 1
            ? t('1 Änderung konnte nicht gespeichert werden')
            : t('%d Änderungen konnten nicht gespeichert werden', discarded);
        queueButton.textContent = t('Ansehen');
        return;
    }

    // OVERWRITTEN BEFORE WAITING. Waiting things take care of themselves,
    // overwritten things wait for a human — and the bar is one line, so it
    // shows what would go under without it.
    if (conflicts > 0) {
        queueText.textContent = conflicts === 1
            ? t('1 Änderung wurde von einer jüngeren überschrieben')
            : t('%d Änderungen wurden von jüngeren überschrieben', conflicts);
        queueButton.textContent = t('Ansehen');
        return;
    }

    /* AS SHORT AS IT GETS (24.08.2026: the bar had grown huge
       on his phone and ate the map). „3 lokal" says everything one can
       act on: something is here and not yet over there. HOW LONG it has
       been waiting was the longest part of the sentence and the least
       useful — nobody does anything differently because it has been five
       hours; the only lever is reception, and that is not in anyone's
       hand. Whoever wants the detail taps „Ansehen", where the age
       stands at each entry. */
    queueText.textContent = t('%d lokal', open);
    queueButton.textContent = t('Ansehen');
}

/** What is behind the bar — on request, not unasked. */
async function viewWaiting() {
    /* FIRST, FOR THE SAME REASON AS IN THE BAR: only here is there still
       something to save, and only as long as the page stays open. */
    if (queue.full() !== null) {
        await report({
            title: t('Der Gerätespeicher ist voll'),
            text: t('Die Warteschlange konnte nicht mehr auf das Gerät geschrieben werden. Was seitdem eingegeben wurde, liegt nur im Arbeitsspeicher dieses Fensters.')
                + '\n\n'
                + t('Solange du diese Seite offen lässt, geht es beim nächsten Netz von selbst hoch. Lade sie bitte NICHT neu und schließe sie nicht — dabei ginge es verloren.')
                + '\n\n'
                + t('Sobald du wieder Empfang hast, überträgt sich alles und die Meldung verschwindet. Hilft das nicht, schafft Platz: unter Verwaltung → Offline lassen sich heruntergeladene Kartenausschnitte vom Gerät nehmen.'),
        });
        updatePendingBar();
        return;
    }

    if (queue.discarded().length > 0) {
        await showRejected();
        updatePendingBar();
        setCadence();
        return;
    }

    if (conflicts > 0) {
        await showConflicts();
        updatePendingBar();
        return;
    }

    const old = queue.oldest();
    const duration = old ? sinceWhen(old) : null;
    const again = await confirm({
        title: t('Warten auf Übertragung'),
        text: (queue.openCount() === 1
                ? t('1 Änderung %snoch nicht beim Server.', duration ? duration + ' ' : '')
                : t('%d Änderungen %snoch nicht beim Server.', queue.openCount(), duration ? duration + ' ' : ''))
            + '\n\n'
            + t('Sie sind auf diesem Gerät gespeichert und gehen von selbst durch, sobald wieder Verbindung da ist — es wird im Hintergrund immer wieder versucht. Du musst nichts tun.'),
        button: t('Jetzt versuchen'),
        cancel: t('Schließen'),
    });
    if (again) {
        await flushWaiting();
        cadenceIndex = 0;
        setCadence();
    }
}

/**
 * Ask for persistence as soon as something waits for the first time.
 *
 * NOT AT STARTUP, AND NOT ONLY AT THE FIRST PHOTO. Until now only photos.js
 * asked, before the first image — a tour without a photo thus ran without
 * any promise from the browser, and the queue lies in localStorage, where
 * the browser may clean up without asking when space runs short. The only
 * copy of the points not yet at the server stands there then.
 *
 * Asking on EVERY page build would be the other mistake: Firefox shows a
 * window for it. Whoever clicks it away once clicks it away always. The
 * right moment is the one in which there is something to lose for the
 * first time.
 */
let inventoryRequested = false;
queue.onChange((open) => {
    updatePendingBar();
    setCadence();
    if (open > 0 && !inventoryRequested) {
        inventoryRequested = true;
        requestPersistence();
    }
});

queueButton.addEventListener('click', viewWaiting);
queueBar.addEventListener('click', (e) => { if (e.target === queueBar || e.target === queueText) viewWaiting(); });

// 'online' does not fire reliably — for instance when the WiFi is
// connected but lets nothing through. Therefore additionally when the map
// comes back to the foreground: whoever takes the phone out of their
// pocket often has reception again exactly then.
window.addEventListener('online', () => { cadenceIndex = 0; flushWaiting().then(setCadence); });
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && queue.openCount() > 0) {
        cadenceIndex = 0;
        flushWaiting().then(setCadence);
    }
});

// Try right on load — whoever was in the woods yesterday and opens the map
// today should not have to do anything more.
updatePendingBar();
flushWaiting().then(setCadence);

// ---------------------------------------------------------------------------
// Making it available as an app
// ---------------------------------------------------------------------------

/**
 * Register the service worker.
 *
 * It answers requests from the cache when nothing gets through — the page
 * itself, program and styles, and above all the map tiles. Without it the
 * map in a dead zone would need no connection for its data anymore (the
 * queue takes care of that), but it would not open at all.
 *
 * Failures are without consequence here: without the worker everything
 * runs as before, just not offline.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(start.baseUrl + 'sw.js', { scope: start.baseUrl })
            .catch(e => console.warn('Service Worker nicht angemeldet:', e));
    });
}

// ---------------------------------------------------------------------------
// Areas for offline
// ---------------------------------------------------------------------------

/* The addresses themselves are asked in tileurls.js — shared with the
   vector map, which draws its raster tiles from the same Leaflet layers.
   I DO NOT REBUILD THE URLS: see there. */
function tileUrls(k, layers) {
    return tileUrlsFor(k, layers, map);
}

/**
 * Hostnames of the services that allow no prefetching.
 *
 * Derived from the registry instead of enumerated here: if a layer is
 * added or a service changes its name, this list stays right.
 */
function blockedServices() {
    const hosts = new Set();
    for (const layer of Object.values({ ...baseMaps, ...overlays })) {
        if (!layer.mapNoPrefetch) continue;
        for (const part of tileLayers(layer)) {
            // {s} is the placeholder for the subdomain — replace it with
            // something arbitrary so the URL can be parsed.
            const raw = (part._url || '').replace(/\{s\}/g, 'a');
            try { hosts.add(new URL(raw).hostname.replace(/^a\./, '')); } catch { /* not a URL */ }
        }
    }
    return [...hosts];
}

/** All tile layers within a layer — compound layers are groups. */
function tileLayers(layer) {
    if (typeof layer.getTileUrl === 'function') return [layer];
    if (typeof layer.eachLayer === 'function') {
        const out = [];
        layer.eachLayer(k => out.push(...tileLayers(k)));
        return out;
    }
    return [];
}

// ---------------------------------------------------------------------------
// Offline: never grey where something was preloaded
//
// WHAT HAPPENS WITHOUT THIS (read out of the vendored Leaflet, then
// decided with the maintainer on 24.08.2026): an area loaded up to level 18
// has nothing at 19. Zooming IN from 18 still looks right — Leaflet
// retains the parent tile of anything not yet loaded, up to five levels
// up (_pruneTiles → _retainParent), and a tile that errors never becomes
// "active", so the coarse one stays and is scaled. But whoever CLOSES
// the app at level 19 and opens it again in the woods has no parent to
// retain: the map is grey and only comes back on zooming out.
//
// maxNativeZoom ends that: above it Leaflet does not ask for tiles at
// all, it enlarges the highest real level. Blurry instead of empty — and
// deliberately so, instead of by accident.
//
// ONLY WHILE OFFLINE, and only where tiles were really stored: the cap
// comes from the device state of the areas whose outline holds the
// current view (the excursion's area counts too, for a guest has no
// area list of his own). With reception everything returns to the
// service's own maximum — sharp again the moment it can be.
// ---------------------------------------------------------------------------

function storedZoomHere() {
    const middle = map.getCenter();
    const spot = [middle.lat, middle.lng];
    let cap = null;
    const take = (id, corners, zoom) => {
        if (!zoom || !polygon.inOutline(spot, corners ?? [])) return;
        cap = Math.max(cap ?? 0, zoom);
    };
    for (const area of areas.areasFromSnapshot()) {
        take(area.id, area.corners, areas.readDeviceState(area.id)?.zoom_to);
    }
    /* An excursion's area is not in the snapshot — it belongs to the
       LEADER, and both kinds of participant may hold its tiles without
       ever owning the area (see the handover above). */
    if (excursionArea) take(excursionArea.id, excursionArea.corners, excursionArea.zoom);
    return cap;
}

let nativeZoomCap = undefined;   // undefined = never applied

function applyNativeZoomCap() {
    const cap = navigator.onLine ? null : storedZoomHere();
    if (cap === nativeZoomCap) return;
    nativeZoomCap = cap;

    for (const layer of Object.values({ ...baseMaps, ...overlays })) {
        for (const part of tileLayers(layer)) {
            /* The service's own maximum, remembered once — it is what we
               return to, and reading it back from the capped value would
               ratchet the layer down step by step.

               'in' AND NOT ??=, and the difference is not pedantry: a WMS
               brings no maxNativeZoom at all, so the remembered value is
               null — and ??= overwrites exactly null. On the way back
               that layer then took the CAP for its own maximum and stayed
               at 18 while everything else returned to 19 (measured, that
               is how this comment came to be). */
            if (!('_nativeMaxOnline' in part)) {
                part._nativeMaxOnline = part.options.maxNativeZoom ?? null;
            }
            const own = part._nativeMaxOnline;
            const wanted = cap === null ? own
                : (own === null ? cap : Math.min(own, cap));
            if ((part.options.maxNativeZoom ?? null) === wanted) continue;
            /* GIVING IT BACK MEANS DELETING IT, not writing null: Leaflet
               asks `void 0 !== maxNativeZoom` (_clampZoom in the vendored
               file) — null passes that test and would then clamp EVERY
               level to null. A layer that brought no maximum must get
               none back. */
            if (wanted === null) delete part.options.maxNativeZoom;
            else part.options.maxNativeZoom = wanted;
            // Only what is on the map needs redrawing; the rest reads the
            // option when it is switched on.
            if (map.hasLayer(part) || map.hasLayer(layer)) part.redraw();
        }
    }
}

window.addEventListener('offline', applyNativeZoomCap);
window.addEventListener('online', applyNativeZoomCap);
// The cap belongs to the SPOT: driving into a loaded area offline should
// bring its levels along. moveend is where the map already tells the
// application it stands somewhere new.
map.on('moveend', applyNativeZoomCap);
applyNativeZoomCap();

/**
 * All layers for the choice — including those not currently on the map.
 *
 * Whoever takes a patch of forest offline wants to switch between aerial
 * image and topographic map there. Offering only what happens to be on
 * would mean: before tracing you must think of switching every layer on
 * once — and notice the omission only in the woods, where nothing can be
 * loaded anymore.
 */
function collectLayers() {
    return Object.entries({ ...baseMaps, ...overlays })
        /* THE OUTLINE LAYERS ARE NOT IN THE LIST AT ALL ANY MORE
           (22.08.2026). They never could be loaded here — they
           come from a WFS and have no tiles — and used to sit in the list
           greyed out saying so. That was worth a line as long as there was
           nowhere else to get them. Since whole states can be downloaded
           under Verwaltung → Offline → Offline-Daten, a dead row is only
           noise, and the hint below the list names the place instead. */
        .filter(([, layer]) => !layer.mapOutline)
        .map(([name, layer]) => ({
            name,
            layer,
            on: map.hasLayer(layer),
            /* Why this layer is not prefetchable — empty means: it is.
               OpenStreetMap and OpenTopoMap would have tiles, but expressly
               forbid downloading whole areas; they are volunteer services.
               Those rows STAY, greyed out: there the reason belongs to the
               layer itself and holds nowhere else either.

               AND ON THE VECTOR MAP every layer that cannot load yet says
               so (30.09.2026): until then the window counted nothing for
               them, loaded nothing and called the area done. The terrain
               and the base map can (offlineEntry), the raster layers ask
               Leaflet's own layers (mapRegion, eachLayer). */
            notLoadable: layer.mapNoPrefetch
                || (VECTOR && typeof layer.onAddVector === 'function' && typeof layer.offlineEntry !== 'function'
                    && !layer.mapRegion && typeof layer.eachLayer !== 'function'
                    ? t('Lässt sich noch nicht laden') : null),
        }));
}

// Which tile layers a layer needs for this area at which level. Memoized,
// because for a compound the answer costs point-in-polygon calculations
// across the state borders and would otherwise fall due dozens of times
// while sliding the zoom control.
let layerCache = new Map();
let countCache = new Map();
let layerCacheFor = null;

function checkLayerStorage(bounds) {
    const key = bounds.toBBoxString();
    if (key !== layerCacheFor) {
        layerCache = new Map();
        countCache = new Map();
        layerCacheFor = key;
    }
}

/**
 * A layer's tile layers for this area at this level.
 *
 * PER LEVEL, not once for everything: Saxony staggers its contour lines by
 * scale, and a state service only joins from a certain level on. With a
 * single stack for all levels the coarse tiles would lie under the URLs of
 * the fine ones — offline, of all things, the overview by which you get
 * your bearings would then be empty.
 */
function layersAt(entry, z, bounds) {
    checkLayerStorage(bounds);

    const key = `${entry.name}|${z}`;
    let list = layerCache.get(key);
    if (!list) {
        const region = entry.layer.mapRegion;
        list = (region ? region.tileLayersFor(bounds, z) : tileLayers(entry.layer))
            .filter(e => drawsAt(e, z));
        layerCache.set(key, list);
    }
    return list;
}

/**
 * How many requests a layer triggers at this level.
 *
 * COUNTED, not extrapolated. The hillshade fetches its base in 512 tiles;
 * one of them covers FOUR fields — but only if those four all lie in the
 * area and fit together cleanly. At the edge and on the coarse levels
 * hardly a field does. With the flat factor 1/4 the calculation predicted
 * 3.5 requests, 8 were needed — and the hillshade is by far the heaviest
 * layer, its error strikes through in full.
 */
function requestsAtZoom(entry, fields, z, bounds) {
    const key = `${entry.name}|${z}`;
    let n = countCache.get(key);
    if (n !== undefined) return n;

    n = 0;
    for (const layer of layersAt(entry, z, bounds)) {
        n += areas.tileCountAtSize(fields, layer.getTileSize().x / 256);
    }
    countCache.set(key, n);
    return n;
}

/**
 * What a tile of this layer weighs at THIS level — measured, not guessed.
 *
 * An aerial image weighs a multiple of a topographic map, and a thematic
 * layer with little content almost nothing. With a flat value per tile the
 * answer for the one layer would be off by a factor of three.
 *
 * PER LEVEL, and that is no nicety: measured, a tile at level 18 weighed
 * around 5 kB, the same layer at level 13 though 17 kB, and a WMS below
 * that up to 70 kB. Further out a tile covers much more area and has
 * correspondingly more content. Weighing only the finest level and
 * extrapolating predicted 558 kB for a test area — 1843 kB arrived.
 *
 * The price comes back PER REQUEST. The total is then a multiplication,
 * and the zoom slider stays free of network traffic: if it moves up,
 * exactly one level is added for measuring.
 *
 * @returns {{perRequest:number, measurable:boolean, empty:boolean}}
 */
async function weighLevel(entry, tiles, z, bounds) {
    const nothing = { perRequest: 0, measurable: true, empty: true };

    const layers = layersAt(entry, z, bounds);
    const level = tiles.filter(k => k.z === z);
    if (!layers.length || !level.length) return nothing;

    // From the middle of the area, not from the edge: with a traced patch
    // of forest, that is where the forest lies, and at the edge possibly a
    // field.
    const sample = level[Math.floor(level.length / 2)];

    let bytes = 0, weighed = 0, opaque = 0;
    await Promise.all(tileUrls(sample, layers).map(async (entry) => {
        // Here too an entry can be a chain — what gets weighed is the
        // source that actually answers, since that is what will be loaded
        // later.
        let a = null;
        for (const url of (Array.isArray(entry) ? entry : [entry])) {
            a = await areas.getTile(url);
            if (a) break;
        }
        if (!a) return;
        if (a.type === 'opaque') { opaque++; return; }
        try {
            bytes += (await a.blob()).size;
            weighed++;
        } catch { /* one sample fewer */ }
    }));

    if (weighed > 0) return { perRequest: bytes / weighed, measurable: true, empty: false };
    // Without CORS clearance the tile arrives but cannot be weighed. That
    // is something different from "does not exist here" and must not lead
    // to the layer being locked.
    if (opaque > 0) return { perRequest: 0, measurable: false, empty: false };
    return nothing;
}

/**
 * What a layer costs across the whole area — from the values measured per
 * level.
 *
 * @returns {{bytes:number, requests:number, measurable:boolean, empty:boolean, done:boolean}}
 */
function layerCost(entry, tiles, bounds) {
    const perZoom = new Map();
    for (const k of tiles) {
        if (!perZoom.has(k.z)) perZoom.set(k.z, []);
        perZoom.get(k.z).push(k);
    }

    let bytes = 0, requests = 0;
    let measurable = true, empty = true, done = true;

    for (const [z, fields] of perZoom) {
        const n = requestsAtZoom(entry, fields, z, bounds);
        if (n === 0) continue;
        requests += n;

        const measure = measurements.get(`${entry.name}|${z}`);
        if (!measure) { done = false; continue; }
        if (!measure.empty) empty = false;
        if (!measure.measurable) measurable = false;
        bytes += n * measure.perRequest;
    }

    return { bytes, requests, measurable, empty: empty && done, done };
}

// ---------------------------------------------------------------------------
// Tracing
//
// ONE TOOL, TWO USES. Tap corner by corner, rubber band to the pointer,
// "Ecke zurück" and "Fertig" — for an offline area that is the same as for
// a mapped area. What differs is only what happens with it in the end;
// that is why that stands in sketchJob and not in the tool.
//
// Previously tracing was hard-wired to the area window. Writing it twice
// would mean making every later change — and the drawing has already
// needed two — in two places and forgetting the second.
// ---------------------------------------------------------------------------

let sketchCorners  = [];
let sketchPolygon = null;
let sketchRubberBand   = null;   // line from the last corner to the pointer
let sketchDots  = [];     // the set corners as visible dots
let sketchJob = null;   // what is to happen with the finished outline

const sketchBar  = document.getElementById('draw-bar');
const toolBar = document.querySelector('.map-tools');
const areaWindow  = document.getElementById('area-window');

/**
 * Begin tracing.
 *
 * @param {{title: string, message: string, done: (corners: Array) => void}} job
 */
function beginOutline(job) {
    sketchJob = job;
    document.getElementById('draw-title').textContent = job.title;

    // Not two modes at once: otherwise one click would create a point and
    // set a corner at the same time.
    setPointMode(false);

    sketchCorners = [];
    cleanUpSketch();
    drawSketchPolygon();
    sketchBar.hidden = false;

    // The tool buttons go away while tracing. "Punkt setzen" in the middle
    // of drawing reads like what you are just doing but is something
    // entirely different — and the thumb is down there while tapping
    // anyway.
    toolBar.hidden = true;

    // Crosshair instead of grab hand: the hand says "you can pan here",
    // but what is meant is "you can set a corner here".
    document.getElementById('map').classList.add('drawing');

    // On the phone the point window fills the whole screen. If an outline
    // is drawn from there, it must step aside — otherwise you tap into the
    // list picker instead of the map. On the desktop the class changes
    // nothing.
    document.body.classList.add('outlined');
    // The selected point's corner handles would otherwise intercept the
    // taps with which new corners are being set right now.
    syncHandles();

    // The bar lies over the map, and under it sat the layer switch — so
    // whoever had an overlay in the way could neither tap through it nor
    // turn it off. Its height is measured rather than assumed: it wraps on
    // a narrow screen and is then three times as tall.
    watchDrawBar();

    // The sidebar steps aside for the drawing — but only BORROWED
    // (22.08.2026: after „Fertig" it stayed shut). What stood
    // open before comes back in stopDrawing, through which every exit
    // passes.
    /* … UNLESS THE JOB WANTS IT (23.08.2026, for the two jobs
       that come from the administration: "Ich brauche zusätzlich noch die
       Sidebar, um Listen auszuwählen und Punkte zu filtern" — and the
       container needs it just as much). Whoever frames an AREA is framing
       points, and which points those are is decided in the sidebar: lists
       on and off, the time filter, the search. Whoever draws the outline
       of a single find, by contrast, came from its window and wants the
       map big — that keeps the borrowing.

       On the phone the sidebar covers the map, so there it steps aside
       anyway; the button brings it back when it is really needed. */
    sidebarWasOpen = sidebar.classList.contains('is-open');
    /* AND IT IS OPENED, not merely left alone. Keeping it as it stood
       would have been useless in the common case: whoever has it
       collapsed cannot open it while drawing — the tool bar with the ☰
       is hidden exactly then, and the drawing bar carries only the three
       drawing buttons. Measured on 23.08.2026, that is precisely how it
       came out: the flag was set and no sidebar in sight. */
    // Borrowed, not chosen — hence without remembering (see setSidebar).
    setSidebar(!job.keepSidebar || isPhone(), false);
    status(job.message ?? t('Ecke für Ecke antippen, dann „Fertig".'));
    map.on('click', setSketchCorner);
    map.on('mousemove', dragSketchRubberBand);
}

/** Whether the sidebar stood open when tracing began — see beginOutline. */
let sidebarWasOpen = false;

/**
 * End the drawing but leave the tracing standing.
 *
 * Exactly this separation was missing: "Fertig" only unregistered the
 * clicking, but not the line that follows the pointer. That one then hung
 * around with its mousemove and stood in the air as a frozen V while the
 * window asked for the name.
 *
 * The polygon itself stays visible on purpose: whoever is just naming what
 * they traced wants to see it while doing so.
 */
function stopDrawing() {
    map.off('click', setSketchCorner);
    // Nothing to finish any more — and the map click handler reads this
    // as „a tap is a tap again", not a corner.
    sketchJob = null;
    map.off('mousemove', dragSketchRubberBand);
    sketchBar.hidden = true;
    toolBar.hidden = false;
    document.getElementById('map').classList.remove('drawing');
    document.body.classList.remove('outlined');
    syncHandles();
    watchDrawBar(false);

    if (sketchRubberBand) { map.removeLayer(sketchRubberBand); sketchRubberBand = null; }

    // Give back what was only borrowed — on the desktop. On the phone the
    // sidebar covers the whole map, and whoever just traced something
    // wants to see the result, not the list again.
    if (sidebarWasOpen && !isPhone()) setSidebar(false, false);
    sidebarWasOpen = false;
}

/** And now the tracing goes too. */
function endDrawing() {
    stopDrawing();
    cleanUpSketch();
    sketchCorners = [];
}

function cleanUpSketch() {
    if (sketchPolygon) map.removeLayer(sketchPolygon);
    if (sketchRubberBand)   map.removeLayer(sketchRubberBand);
    for (const m of sketchDots) map.removeLayer(m);
    sketchPolygon = null;
    sketchRubberBand   = null;
    sketchDots  = [];
}

/**
 * Keep the map controls clear of the tracing bar.
 *
 * MEASURED, NOT WRITTEN DOWN. The bar wraps on a narrow screen — 60 px on
 * the desktop, up to 218 on a phone with a long heading — so any number
 * put in the stylesheet would be wrong for one of the two. The observer is
 * for turning the device: the bar rewraps and the controls have to follow.
 */
let drawBarWatch = null;
function watchDrawBar(on = true) {
    const root = document.documentElement;
    drawBarWatch?.disconnect();
    drawBarWatch = null;

    if (!on) {
        root.style.removeProperty('--draw-bar');
        return;
    }

    const measure = () => root.style.setProperty('--draw-bar', sketchBar.offsetHeight + 'px');
    measure();
    if (typeof ResizeObserver === 'function') {
        drawBarWatch = new ResizeObserver(measure);
        drawBarWatch.observe(sketchBar);
    }
}

function setSketchCorner(e) {
    sketchCorners.push([e.latlng.lat, e.latlng.lng]);
    drawSketchPolygon();
}

/**
 * The line that follows the pointer.
 *
 * Without it, visibly nothing happens after the first click: a polygon
 * only exists from three corners on, and until then you wonder whether the
 * click arrived. From the second corner on it additionally runs back to
 * the first — so you see the area that would just come into being before
 * you commit it.
 *
 * On the phone there is no mousemove; the corner marks do that there.
 */
function dragSketchRubberBand(e) {
    if (!sketchCorners.length) return;

    const pointer = [e.latlng.lat, e.latlng.lng];
    const trail = sketchCorners.length >= 2
        ? [sketchCorners.at(-1), pointer, sketchCorners[0]]
        : [sketchCorners.at(-1), pointer];

    if (sketchRubberBand) sketchRubberBand.setLatLngs(trail);
    else sketchRubberBand = L.polyline(trail, { className: 'draw-rubberband', interactive: false }).addTo(map);
}

function drawSketchPolygon() {
    if (sketchPolygon) map.removeLayer(sketchPolygon);
    sketchPolygon = null;
    for (const m of sketchDots) map.removeLayer(m);
    sketchDots = [];

    // Every set corner gets a mark. On the phone that is the only feedback
    // that the first tap arrived at all.
    for (const corner of sketchCorners) {
        sketchDots.push(L.circleMarker(corner, {
            className: 'draw-corner', radius: 5, interactive: false,
        }).addTo(map));
    }

    // interactive: false is no garnish — a clickable polygon swallows the
    // clicks inside it, and then, of all places, you can no longer set a
    // corner where the area lies.
    if (sketchCorners.length >= 3) {
        sketchPolygon = L.polygon(sketchCorners, { className: 'draw-polygon', interactive: false }).addTo(map);
    } else if (sketchCorners.length === 2) {
        sketchPolygon = L.polyline(sketchCorners, { className: 'draw-line', interactive: false }).addTo(map);
    }

    document.getElementById('draw-hint').textContent = [
        t('In die Karte tippen — Ecke für Ecke'),
        t('1 Ecke — mindestens drei'),
        t('2 Ecken — noch eine'),
    ][sketchCorners.length] ?? t('%d Ecken — „Fertig", wenn es passt', sketchCorners.length);

    // What cannot do anything yet visibly shows that it cannot do anything
    // yet.
    document.getElementById('draw-back').disabled = sketchCorners.length === 0;
    document.getElementById('draw-done').disabled  = sketchCorners.length < 3;
}

document.getElementById('draw-back').addEventListener('click', () => {
    sketchCorners.pop();
    if (!sketchCorners.length && sketchRubberBand) {
        map.removeLayer(sketchRubberBand);
        sketchRubberBand = null;
    }
    drawSketchPolygon();
});
document.getElementById('draw-cancel').addEventListener('click', () => {
    endDrawing();
    status(t('Abgebrochen.'));
});
document.getElementById('draw-done').addEventListener('click', () => {
    if (sketchCorners.length < 3) {
        status(t('Mindestens drei Ecken.'), true);
        return;
    }
    const corners = [...sketchCorners];
    const job = sketchJob;
    stopDrawing();
    job?.done(corners);
});

// ---------------------------------------------------------------------------

let areaCorners   = [];
let areaBounds = null;
let areaControl  = { cancel: false };

// The area for which the loading window is currently open.
let loadingArea = null;

// ---------------------------------------------------------------------------
// Step 1: creating the tracing
// ---------------------------------------------------------------------------

/**
 * Only name and polygon.
 *
 * What is NOT asked here — layers, zoom level, expiry — is no omission but
 * the point: whoever traces a patch of forest at the desk does not yet
 * know on which device they will need it and how much room there is. Step
 * two asks these questions, and namely on the device that loads.
 */
/** Tracing for an offline area: at the end the name is asked for. */
function beginAreaOutline() {
    beginOutline({
        title:   t('Gebiet umranden'),
        message: t('Ecke für Ecke antippen, dann „Fertig".'),
        done:    openAreaWindow,
    });
}

function openAreaWindow(corners) {
    areaCorners = corners;
    areaBounds = L.latLngBounds(corners);

    const name = document.getElementById('area-name');
    name.value = '';
    document.getElementById('area-now').checked = false;
    document.getElementById('area-save').disabled = false;

    areaWindow.showModal();
    name.focus();
}

document.getElementById('area-cancel').addEventListener('click', () => {
    areaWindow.close();
    endDrawing();
});

/**
 * Back to where the areas are managed.
 *
 * For areas, the map is only a tool anymore: tracing and loading do not
 * work without it, everything else belongs in the administration. Whoever
 * is done here has more to do there — and sees the result in the same
 * list they came from. Leaving them standing on the map instead would
 * mean: they find their own way back.
 */
function backToAdministration() {
    location.href = (window.START_DATA?.baseUrl ?? '') + 'pages/administration.php#areas';
}

document.getElementById('area-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const name = document.getElementById('area-name').value.trim();
    if (name === '') return;

    const button = document.getElementById('area-save');
    button.disabled = true;

    let area;
    try {
        area = await areas.createArea({ name, corners: areaCorners });
    } catch (error) {
        button.disabled = false;
        status(t('Gebiet anlegen fehlgeschlagen: %s', error.message), true);
        return;
    }

    const loadNow = document.getElementById('area-now').checked;
    areaWindow.close();
    endDrawing();

    if (area.pending) {
        status(t('Gespeichert — wird übertragen, sobald Verbindung da ist. Karten dafür holst du danach.'));
        return;
    }

    if (loadNow) {
        openLoadWindow(area);
    } else {
        // Back to the administration one came from — the new area stands
        // there, and there also sits the button with which one fetches
        // maps for it. A message alone would leave one standing on the
        // empty map.
        backToAdministration();
    }
});

// ---------------------------------------------------------------------------
// Step 2: maps onto this device
// ---------------------------------------------------------------------------

const loadWindow = document.getElementById('load-window');

function openLoadWindow(area) {
    loadingArea    = area;
    areaCorners   = area.corners;
    areaBounds = L.latLngBounds(area.corners);

    document.getElementById('load-name').textContent = area.name;

    /* What this device remembers from last time. The first time, a
       suggestion that fits most tours: gone in four weeks.

       19 AND NOT 18 AS THE FIRST SUGGESTION (22.08.2026): the
       finest level is the one that counts while walking the area — aerials
       are wanted in the highest resolution there is, and whoever needs
       less can pull the slider down before loading. It quadruples the
       amount against 18, which is why the estimate stands right below and
       says so before anything is fetched. */
    const deviceState = areas.readDeviceState(area.id);
    const inFourWeeks = new Date(Date.now() + 28 * 86400000);
    document.getElementById('area-zoom').value = deviceState?.zoom_to ?? 19;

    /* KEPT FOR GOOD IS A STATE THAT WAS ALREADY LOADED HERE, not merely
       the absence of a date: whoever loaded this area before and cleared
       the field back then finds the switch flipped, not an empty field
       that looks like an oversight. Freshly, the four weeks stand — most
       tours want the maps for a while, not forever, and the phone thanks
       you. */
    const keep = document.getElementById('area-keep');
    keep.checked = deviceState !== null && !deviceState.valid_until;
    document.getElementById('area-until').value =
        deviceState?.valid_until ?? inFourWeeks.toISOString().slice(0, 10);
    showKeepState();

    document.getElementById('area-progress').hidden = true;
    const button = document.getElementById('area-load');
    button.disabled = false;

    /* GOING ON, NOT STARTING OVER (27.09.2026). A run that did not reach
       its end — a closed page, a full storage, a dropped connection, or an
       area loaded before the runs kept a record — is continued: loadArea()
       skips every tile already stored, so the same press fetches only
       what is missing. The button says so, and the layers are the ones of
       the broken run, not whatever the map happens to show right now —
       otherwise „Weiterladen" would load something else. */
    const resuming = deviceState !== null && !areas.loadedCompletely(deviceState);
    button.textContent = resuming ? t('Weiterladen') : t('Laden');

    buildLayerSelection();
    const before = deviceState?.run?.layers;
    if (resuming && Array.isArray(before) && before.length > 0) {
        for (const box of document.querySelectorAll('#area-layers input[type="checkbox"]')) {
            if (!box.disabled) box.checked = before.includes(box.value);
        }
    }
    showEstimate();
    loadWindow.showModal();
}

/* The date field only has something to say while an expiry is wanted.
   Hidden and not merely disabled: a greyed-out date still reads as a
   value that applies somehow. */
function showKeepState() {
    document.getElementById('area-until-field').hidden =
        document.getElementById('area-keep').checked;
}

document.getElementById('area-keep').addEventListener('change', showKeepState);

document.getElementById('area-load-cancel').addEventListener('click', () => {
    areaControl.cancel = true;
    loadWindow.close();
});

document.getElementById('load-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!loadingArea) return;

    const zoomTo = Number(document.getElementById('area-zoom').value);
    const to     = document.getElementById('area-keep').checked
        ? null
        : (document.getElementById('area-until').value || null);

    // Merged, not replaced: the count of the tiles already here stays
    // until this run has its own (getAreaTiles), and a cancellation puts
    // back exactly what stood before.
    areas.writeDeviceState(loadingArea.id, {
        ...(areas.readDeviceState(loadingArea.id) ?? {}),
        zoom_to: zoomTo,
        valid_until: to,
    });

    /* WHILE IT RUNS, ONLY THE BAR (22.08.2026). The choices
       above are made; a list of layers and a cost estimate one can no
       longer act on is not information but decoration around the one
       thing that still moves. */
    loadWindow.classList.add('is-loading');

    /* AND IT CLOSES IN EVERY CASE. Until now the closing stood behind the
       await, unprotected: when getAreaTiles threw — and it did, over a
       misread result field — the window simply stayed, the bar full, the
       button dead. Nothing said anything, because a burst promise in a
       submit listener lands only in the console. That was the whole bug
       the maintainer reported: "der Balken ist voll und nichts passiert".
       Whatever happens now, the window goes and the message says what
       happened. */
    try {
        await getAreaTiles(loadingArea, zoomTo, document.getElementById('area-load'), selectedLayers());
    } catch (error) {
        console.error('Gebiet laden fehlgeschlagen:', error);
        status(t('Das Laden ist abgebrochen: %s', error?.message || t('unbekannter Fehler')), true);
    } finally {
        loadWindow.classList.remove('is-loading');
        loadWindow.close();
        // The row in the administration should now say what lies on the device.
        backToAdministration();
    }
});

// ---------------------------------------------------------------------------
// Showing and editing: the tracing on the map
//
// "Zeigen" from the administration used to only jump there — you then
// stood on the view and saw NOTHING, since the tracing was never drawn
// (the call, 14.08.2026: see and edit the polygon, with the tool
// of the point outlines). Now the polygon lies on the map and the handle
// machinery takes it over as its source: the same gestures — drag a
// corner, tap the line to insert, long press for the corner menu.
// ---------------------------------------------------------------------------

const areaBar = document.getElementById('area-bar');

/** Is an area edit currently running? { area, corners, polygon, changed } */
let areaEditing = null;

function beginAreaEditing(g) {
    endAreaEditing();

    // interactive: false as when drawing — a clickable polygon would
    // swallow the taps with which one wants to select points next to it;
    // the handles' insertion line listens by itself anyway.
    const layer = L.polygon(g.corners, { className: 'area-polygon', interactive: false }).addTo(map);

    areaEditing = {
        area:    g,
        corners: g.corners.map((e) => [...e]),
        polygon: layer,
        changed: false,
    };

    handleSource = {
        key:     'area:' + g.id,
        corners: () => areaEditing.corners,
        polygon: () => areaEditing.polygon,
        // The path of every handle result (applyOutline): remember the
        // corners, pull the polygon along, enable saving. It only takes
        // effect with the button — as on the point too.
        apply(corners, what) {
            areaEditing.corners = corners;
            areaEditing.polygon.setLatLngs(corners);
            areaEditing.changed = true;
            syncAreaBar();
            syncHandles();
            status(t('%s — %s. Noch nicht gespeichert.', what, polygon.readableArea(polygon.areaM2(corners))));
        },
    };

    document.getElementById('area-bar-name').textContent = g.name;
    syncAreaBar();
    areaBar.hidden = false;
    syncHandles();
}

function endAreaEditing() {
    if (!areaEditing) return;
    map.removeLayer(areaEditing.polygon);
    areaEditing = null;
    handleSource = null;
    areaBar.hidden = true;
    // Clear away the source's handles — and reconcile right away, so a
    // point selected in the meantime gets its own back.
    removeHandles();
    syncHandles();
}

function syncAreaBar() {
    document.getElementById('area-bar-save').disabled = !areaEditing?.changed;
}

document.getElementById('area-bar-save').addEventListener('click', async () => {
    const b = areaEditing;
    if (!b?.changed) return;

    const button = document.getElementById('area-bar-save');
    button.disabled = true;
    try {
        await areas.updateArea(b.area.id, { corners: b.corners });
    } catch (error) {
        button.disabled = false;
        status(error?.isNetworkError
            ? t('Keine Verbindung — die Umrandung ist nicht gespeichert. Später noch einmal drücken.')
            : t('Speichern fehlgeschlagen: %s', error.message), true);
        return;
    }
    b.changed = false;
    b.area.corners = b.corners.map((e) => [...e]);
    syncAreaBar();
    // The tiles on the devices know nothing of the change — whoever
    // enlarged the area must reload, and that should be stated.
    status(t('Umrandung gespeichert. Karten für neue Flächenteile holst du über „Herunterladen" — vorhandene Kacheln bleiben.'));
});

document.getElementById('area-bar-done').addEventListener('click', async () => {
    if (areaEditing?.changed) {
        const ok = await confirm({
            title: t('Änderung verwerfen?'),
            text: t('Die geänderte Umrandung ist nicht gespeichert. Beim Schliessen fällt sie zurück auf den gespeicherten Stand.'),
            button: t('Verwerfen'),
            danger: true,
        });
        if (!ok) return;
    }
    endAreaEditing();
    status('');
});

/** The download running on this page, if any — for the app's cancel. */
let downloading = null;

/* THE APP CAN CANCEL TOO (agreed with the app session, 27.09.2026): its
   notification carries „Abbrechen", and the shell tells the page through
   this event. In Chrome and in older app versions it never fires. */
document.addEventListener('yasaimapp:download-cancel', (e) => {
    if (downloading && String(e.detail?.area_id) === String(downloading.id)) areaControl.cancel = true;
});

/**
 * Keep the screen on while tiles are fetched — where the browser can.
 *
 * THE SCREEN IS THE ONLY THING THAT CARRIES A DOWNLOAD. With it off,
 * Chromium holds the hidden page back after about a minute — no fetch
 * comes back, no timer fires — and the download pauses until the phone is
 * unlocked, then goes on by itself (27.09.2026: the automatic
 * lock came again and again during 350 MB; the cause measured by the app
 * session, 28.09.2026, see getAreaTiles). The browser's own lock asks the screen to stay
 * on while the page is in front; it lets go by itself when the page is
 * hidden, and is taken again when it returns. The app's WebView has the
 * same lock and grants it (measured by the app session on the Xiaomi,
 * 28.09.2026) — the shell's own screen-on went again. Where there is no
 * lock, nothing happens.
 *
 * @returns {Promise<() => void>} lets go
 */
async function keepScreenOn() {
    let lock = null;
    let done = false;
    const take = async () => {
        if (done || document.visibilityState !== 'visible') return;
        try { lock = await navigator.wakeLock?.request('screen') ?? null; } catch { lock = null; }
        if (done) lock?.release?.().catch(() => {});
    };
    document.addEventListener('visibilitychange', take);
    await take();
    return () => {
        done = true;
        document.removeEventListener('visibilitychange', take);
        lock?.release?.().catch(() => {});
    };
}

/** Fetch an area's tiles onto THIS device. */
async function getAreaTiles(area, zoomTo, button, layers) {
    const bar = document.getElementById('area-progress-inner');
    const text = document.getElementById('area-progress-text');
    document.getElementById('area-progress').hidden = false;
    if (button) { button.disabled = true; button.textContent = t('lädt …'); }

    areaControl = { cancel: false };
    downloading = { id: area.id };
    const bounds = L.latLngBounds(area.corners);

    // Resolved PER LEVEL, not once for everything: which services draw and
    // which layer they deliver hangs on the zoom level. A fixed stack
    // would store the coarse tiles under the URLs of the fine ones —
    // offline the overview would then be empty.
    const urlsFor = (k) => {
        const urls = [];
        for (const e of layers) urls.push(...tileUrls(k, layersAt(e, k.z, bounds)));
        return urls;
    };

    /* THE RUN KEEPS A RECORD ON THE DEVICE (27.09.2026), written at the
       start and every few seconds. A page that dies midway — the screen
       off too long, the tab thrown out, the renderer gone — leaves a row
       that says how far it got and offers „Weiterladen", instead of a full
       bar nobody saw end. The layers go on record so that „Weiterladen"
       continues this run and not whatever the map shows next time. */
    const previous = areas.readDeviceState(area.id);
    const run = {
        started: new Date().toISOString(),
        zoom_to: zoomTo,
        layers: layers.map((e) => e.name),
        total: 0,
        done: 0,
        complete: false,
        persisted: null,
    };
    /* WHAT THE AREA HOLDS AFTER THIS RUN, from two lower bounds: what an
       earlier run counted plus what this one stored, and what this run
       found there plus what it stored — the larger wins. The first keeps
       layers this run does not load; the second catches the tiles an
       interrupted run stored after its last record. */
    const tilesAfter = (counts) => Math.max(
        Number.isFinite(previous?.tiles) ? previous.tiles + counts.stored : 0,
        counts.present + counts.stored);
    /* THE SIZE ONLY WHERE IT IS TRUE. The services send no Content-Length,
       so a tile already here says nothing about its size. With an earlier
       run's size on record, this run adds what it stored; without one,
       the size is known only if nothing here was unmeasured — otherwise
       it stays open rather than showing 12 MB for 350 (the maintainer's area,
       loaded before the runs counted, is exactly that case). */
    const sizeAfter = (counts) => Number.isFinite(previous?.bytes)
        ? previous.bytes + counts.newBytes
        : (counts.presentUnmeasured === 0 ? counts.bytes : undefined);
    const keepRecord = (counts = null, extra = {}) => {
        const state = { ...(areas.readDeviceState(area.id) ?? {}), run: { ...run, ...extra } };
        if (counts) {
            state.tiles = tilesAfter(counts);
            const bytes = sizeAfter(counts);
            if (bytes === undefined) delete state.bytes;
            else state.bytes = bytes;
        }
        areas.writeDeviceState(area.id, state);
    };
    keepRecord();

    /* ASK FOR THE STORAGE TO BE KEPT — without that pledge the browser may
       clear this origin's storage when space runs short, a whole area with
       it. Not awaited before starting; the answer goes on record, because
       whether it was granted is the first question when an area vanished. */
    const persistence = requestPersistence().then((granted) => { run.persisted = granted; });

    /* THE APP'S HALF (agreed with the app session, 27.09.2026): the shell
       holds a foreground service while tiles are fetched, meant to keep
       the page going with the screen off. WHAT STOPS IT IS CHROMIUM, NOT
       THE PHONE (the app session on the maintainer's Xiaomi, 28.09.2026 — a
       first guess at Xiaomi's own freezer was withdrawn): neither Android
       nor HyperOS froze app or renderer, the renderer answered DevTools at
       once, and „Keine Einschränkungen" changed nothing — yet Chromium
       holds back the tasks of a hidden page. AND THE SERVICE DOES NOT
       CARRY THIS LOOP EITHER (measured the same day): a probe firing
       fetches back to back, with no timer, the service held through
       downloadStarted, ran about seven requests a second with the screen
       dark — and a minute after it went off no fetch came back and no
       timer fired, while the service ran on. This loop waits on fetch, so
       it stops the same way and resumes after unlocking. Hence: no text
       may promise a download that runs on in a locked phone; the wake
       lock above is what carries, and the advice is the charger and an
       unlocked screen. SO THE SERVICE WENT from the shell on the maintainer's
       word (app commit 7481200, 28.09.2026): its shim no longer offers
       downloadStarted/Progress/Ended, and 'yasaimapp:download-cancel'
       never comes. The calls below STAY, deliberately and at no cost —
       with ?. they return undefined — because they are the agreed names,
       kept in the app's CLAUDE.md, should a way ever appear to keep a
       hidden WebView going. What they did while the service lived:
       started from
       the press itself while the page is in front — Android forbids
       starting such a service from the background —, progress at most
       once a second, and the end ALWAYS, in the finally below, or the
       service waits for its watchdog (three minutes). The real sign of
       life is the progress out of the loop; the interval is only extra —
       Chromium stretches timers in a page hidden for more than five
       minutes to about one a minute, which the watchdog allows for.
       Objects, not strings; the shell's shim turned them into text.
       Absent in Chrome and, since 7481200, in the app too — every call is
       optional. */
    const letGo = await keepScreenOn();
    let lastDone = 0;
    let lastTotal = 0;
    let lastSent = 0;
    let lastKept = Date.now();
    const heartbeat = setInterval(() => window.yasaimapp?.downloadProgress?.(lastDone, lastTotal), 20000);
    const ended = { area_id: area.id, ok: false, stored: 0, failed: 0, cancelled: false, error: null };

    const report = (done, total, counts) => {
        lastDone = done;
        lastTotal = total;
        if (done === 0) {
            run.total = total;
            keepRecord(counts);
            window.yasaimapp?.downloadStarted?.({ area_id: area.id, name: area.name, total });
        }
        bar.style.width = (total > 0 ? done / total * 100 : 0).toFixed(1) + '%';
        text.textContent = `${done.toLocaleString('de-DE')} / ${total.toLocaleString('de-DE')}`;
        const now = Date.now();
        if (now - lastSent >= 1000) {
            lastSent = now;
            window.yasaimapp?.downloadProgress?.(done, total);
        }
        if (now - lastKept >= 5000) {
            lastKept = now;
            run.done = done;
            keepRecord(counts);
        }
    };

    try {
        const result = await areas.loadArea(area, zoomTo, urlsFor, report, areaControl);
        await persistence;
        const n = (x) => x.toLocaleString('de-DE');
        const failed = result.missing + result.unreachable + result.unsaved;
        const complete = !result.cancelled && !result.stopped
            && result.done === result.total && result.unreachable === 0;

        if (result.cancelled) {
            /* BACK TO BEFORE: the cancellation took out what this run
               added, so the record goes back too — to the count of an
               earlier run, or to nothing where there was none. */
            const had = previous && (Number.isFinite(previous.tiles) || previous.bytes !== undefined || previous.run);
            if (had) areas.writeDeviceState(area.id, previous);
            else areas.forgetDeviceState(area.id);
        } else if (result.tiles === 0 && !(previous?.tiles > 0) && previous?.bytes === undefined) {
            /* NOTHING LIES HERE — then no record should say so either,
               or the row would claim a size for tiles that are not there. */
            areas.forgetDeviceState(area.id);
        } else {
            run.done = result.done;
            keepRecord(result, {
                ended: new Date().toISOString(),
                tiles: result.tiles,
                missing: result.missing,
                unreachable: result.unreachable,
                unsaved: result.unsaved,
                error: result.stopped,
                refusal: result.refusal,
                complete,
            });
        }

        let said;
        if (result.cancelled) {
            /* SAY THAT IT REALLY IS GONE. Whoever cancels wants to be sure
               nothing half-done stays behind; a message that merely says
               "cancelled" leaves the question open, and the row underneath
               answers it only if one goes looking. */
            said = result.removed > 0
                ? t('Abgebrochen — die %s bereits geholten Kacheln wurden wieder entfernt.', n(result.removed))
                : t('Abgebrochen — es war noch nichts geholt.');
        } else if (result.stopped === 'quota') {
            said = t('Der Speicher auf diesem Gerät ist voll. %s Kacheln sind gespeichert. Mach Platz frei und tippe dann unter Offline-Gebiete auf „Weiterladen".', n(tilesAfter(result)));
        } else if (result.stopped === 'storage') {
            said = t('Die Kacheln lassen sich auf diesem Gerät nicht mehr speichern. %s Kacheln sind gespeichert.', n(tilesAfter(result)));
        } else if (result.tiles === 0) {
            // Previously "ist verfuegbar" stood here even then. Exactly so it
            // went unnoticed that nothing arrived at all — until it showed in
            // the woods.
            said = t('„%s" konnte nicht geladen werden: keine einzige Kachel angekommen (%d Versuche). Siehe Konsole.', area.name, failed);
        } else if (result.unreachable > 0) {
            said = t('„%s": %s Kacheln sind auf diesem Gerät, %s kamen ohne Verbindung nicht an. Tippe unter Offline-Gebiete auf „Weiterladen", sobald du wieder Empfang hast.', area.name, n(tilesAfter(result)), n(result.unreachable));
        } else {
            const missing = result.missing > 0 ? ' ' + t('%s Adressen gaben nichts her.', n(result.missing)) : '';
            const size = sizeAfter(result);
            said = t('„%s" ist auf diesem Gerät verfügbar: %s Kacheln', area.name, n(tilesAfter(result)))
                + (size > 0 ? `, ${areas.readableSize(size)}` : '') + '.' + missing;
        }
        const trouble = !result.cancelled && !complete;
        status(said, trouble || result.tiles === 0);

        Object.assign(ended, {
            ok: complete && result.tiles > 0,
            stored: result.cancelled ? (previous?.tiles ?? 0) : tilesAfter(result),
            failed,
            cancelled: result.cancelled,
            // The shell shows this sentence unchanged in its last notice.
            error: trouble || (!result.cancelled && result.tiles === 0) ? said : null,
        });
        return result;
    } catch (error) {
        ended.error = t('Das Laden ist abgebrochen: %s', error?.message || t('unbekannter Fehler'));
        throw error;
    } finally {
        clearInterval(heartbeat);
        letGo();
        downloading = null;
        /* HERE, SYNCHRONOUSLY, AND BEFORE THE PAGE LEAVES. The shell's
           call returns only once the app has noted the end, and the load
           window navigates to the Offline tab right after this function
           (its submit handler's finally). A timer or a postMessage here
           would let that navigation arrive first — and the app reports
           every finished download as „unterbrochen" (agreed with the app
           session, 27.09.2026; its CLAUDE.md says the same from its side). */
        window.yasaimapp?.downloadEnded?.(ended);
    }
}

// ---------------------------------------------------------------------------
// What it costs
// ---------------------------------------------------------------------------

/**
 * Say beforehand what it costs.
 *
 * That is the whole point of this window. One zoom level more quadruples
 * the tile count — whoever only notices that after loading has 300 MB on
 * the phone they did not want.
 */
function showEstimate() {
    const zoom = Number(document.getElementById('area-zoom').value);
    document.getElementById('area-zoom-value').textContent = zoom;
    document.getElementById('area-zoom-text').textContent = {
        15: t('Übersicht — Wege erkennbar'),
        16: t('Wege und Schneisen'),
        17: t('Einzelne Bäume erahnbar'),
        18: t('Einzelne Bäume unterscheidbar — reicht für die Suche'),
        19: t('Feinste Stufe; vervierfacht die Menge gegenüber 18'),
    }[zoom] ?? '';

    const tiles = areas.tileList(areaCorners, zoom);
    const chosen = selectedLayers();

    let bytes = 0, requests = 0, unknown = false, allDone = true;
    for (const e of chosen) {
        const k = layerCost(e, tiles, areaBounds);
        requests += k.requests;
        bytes += k.bytes;
        if (!k.measurable) unknown = true;
        if (!k.done) allDone = false;
    }

    // Keep each layer's sum for the set zoom up to date — without network:
    // weighing happened once per level, here only multiplying.
    for (const e of layerList) {
        const field = document.querySelector(`#area-layers [data-layer="${CSS.escape(e.name)}"]`);
        if (!field || e.notLoadable) continue;
        const k = layerCost(e, tiles, areaBounds);
        if (!k.done) continue;                       // still weighing
        field.textContent = k.empty ? t('hier keine Daten')
            : !k.measurable ? t('%s Kacheln, Größe unbekannt', Math.round(k.requests).toLocaleString('de-DE'))
            : areas.readableSize(k.bytes);
    }

    const field = document.getElementById('area-estimate');
    field.innerHTML = chosen.length === 0
        ? '<strong>' + t('Keine Ebene gewählt') + '</strong> — ' + t('dann gibt es offline kein Kartenbild.')
        : `<strong>${t('%s Kacheln', Math.round(requests).toLocaleString('de-DE'))}</strong>`
          + ` · ${(unknown || !allDone) ? t('mindestens') + ' ' : t('etwa') + ' '}${areas.readableSize(bytes)}`
          + `<br><span class="item-data">${t('%s Felder über %d Zoomstufen',
              tiles.length.toLocaleString('de-DE'), new Set(tiles.map(k => k.z)).size)}`
          + ` · ${chosen.length === 1 ? t('1 Ebene') : t('%d Ebenen', chosen.length)}`
          + (!allDone ? ' · ' + t('wird noch gewogen') : unknown ? ' · ' + t('eine davon ohne messbare Größe') : '')
          + '</span>';
    field.classList.toggle('high', bytes > 200 * 1024 * 1024);
}

let layerList = [];

// Measured price PER REQUEST AND LEVEL, key "Ebene|Stufe". Because the
// price does not hang on the zoom slider, sliding down costs nothing at
// all and sliding up exactly one new level.
const measurements = new Map();

function selectedLayers() {
    const on = new Set([...document.querySelectorAll('#area-layers input:checked')].map(k => k.value));
    return layerList.filter(e => on.has(e.name));
}

function buildLayerSelection() {
    layerList = collectLayers();
    measurements.clear();

    // What another device has already measured, this one need not fetch
    // again: what a tile weighs here hangs on the terrain, not the device.
    applyMeasurements(loadingArea);

    const section = document.getElementById('area-layers');
    section.innerHTML = '';

    for (const e of layerList) {
        const label = document.createElement('label');

        const box = document.createElement('input');
        box.type = 'checkbox';
        box.value = e.name;
        // Preselected is what currently lies on the map — that is the view
        // one presumably wants back offline. But everything is offered,
        // see collectLayers().
        box.checked = e.on && !e.notLoadable;
        box.disabled = !!e.notLoadable;
        box.addEventListener('change', showEstimate);

        const name = document.createElement('span');
        name.className = 'layer-selection-name';
        name.textContent = e.name;

        const cost = document.createElement('span');
        cost.className = 'layer-selection-costs';
        cost.textContent = e.notLoadable ?? t('wird gewogen …');
        cost.dataset.layer = e.name;

        label.append(box, name, cost);
        section.append(label);
    }

    measureCost();
}

/**
 * Adopt measured sizes from the server.
 *
 * They age: a service can fly new imagery, and then the answer no longer
 * holds. After half a year measuring therefore happens anew — often enough
 * not to stay permanently wrong, rarely enough to leave the official
 * services in peace.
 */
const MEASUREMENTS_KEEP_DAYS = 180;

function applyMeasurements(area) {
    if (!area?.measurements || !area.measured_on) return;

    // Do NOT check for alter >= 0: measured_on is a bare date and is read
    // as UTC midnight. East of Greenwich a size measured TODAY is thereby
    // arithmetically of negative age — and flew out of exactly this check.
    // The cache filled up but was never used: 49 requests to third-party
    // services on every opening, although everything already stood there.
    const age = (Date.now() - Date.parse(area.measured_on)) / 86400000;
    if (!Number.isFinite(age) || age > MEASUREMENTS_KEEP_DAYS) return;

    for (const [key, value] of Object.entries(area.measurements)) {
        if (value && typeof value === 'object') measurements.set(key, value);
    }
}

/**
 * Weigh the layers one after another and keep the display current.
 *
 * One after another, not all at once: the official services are slow and
 * belong to somebody else. Whoever decides in the meantime sees the
 * numbers arrive — those of the layers currently on first, since those
 * are the ones most people look at.
 */
async function measureCost() {
    const tiles = areas.tileList(areaCorners, Number(document.getElementById('area-zoom').value));
    const bounds = areaBounds;
    const levels = [...new Set(tiles.map(k => k.z))].sort((a, b) => b - a);
    const cursor = ++costRun;
    let remeasured = false;

    const order = [...layerList].sort((a, b) => (b.on ? 1 : 0) - (a.on ? 1 : 0));

    for (const e of order) {
        if (cursor !== costRun) return;     // different area, old measurement moot
        if (e.notLoadable) continue;

        // A layer's levels side by side: it is the same service, and six
        // requests are exactly as many as run concurrently while loading
        // anyway. One after another it would be six times the wait here,
        // while somebody sits in front of the window.
        const missing = levels.filter(z => !measurements.has(`${e.name}|${z}`));
        if (missing.length) remeasured = true;

        await Promise.all(missing.map(async (z) => {
            const measure = await weighLevel(e, tiles, z, bounds);
            if (cursor === costRun) measurements.set(`${e.name}|${z}`, measure);
        }));
        if (cursor !== costRun) return;

        // What delivers nothing cannot be taken offline either. Leaving it
        // choosable would mean letting somebody download something that
        // does not exist. Merely not weighable is something else and stays
        // choosable.
        if (layerCost(e, tiles, bounds).empty) {
            const box = document.querySelector(`#area-layers input[value="${CSS.escape(e.name)}"]`);
            if (box) { box.checked = false; box.disabled = true; }
        }
        showEstimate();
    }

    // Measure once, remember for all — the next device needs not a single
    // byte for it. Only if something new really came in here.
    if (remeasured && loadingArea?.id > 0) {
        areas.rememberMeasurements(loadingArea.id, Object.fromEntries(measurements));
    }
}
let costRun = 0;

// The zoom slider changes only the number of fields. Down costs nothing at
// all, up exactly one new level per layer — what was weighed stays.
//
// The numbers run along immediately, measuring only happens once the
// slider rests: while dragging, every intermediate value would otherwise
// trigger requests whose result the next intermediate value discards
// again.
let zoomPending = null;
document.getElementById('area-zoom').addEventListener('input', () => {
    showEstimate();
    clearTimeout(zoomPending);
    zoomPending = setTimeout(measureCost, 300);
});

// ---------------------------------------------------------------------------

/**
 * Cleaning up: expired maps, areas deleted elsewhere, foreign tiles.
 *
 * THERE IS NO CALL-OUT. If you delete an area at the desk, the phone
 * learns nothing of it — no server in the world can nudge a telephone that
 * is sitting in a pocket. So cleaning up happens when the device drops by
 * of its own accord: on opening the map and on opening the overview.
 *
 * On the server, by contrast, the area is gone immediately. The other
 * device no longer shows it at the next look; only its tiles lie around
 * until then. So nothing becomes visibly wrong, it just takes a while
 * until the space is free again.
 *
 * @param {Array|null} list   Directory FROM THE SERVER, or null. Only with
 *        it can one decide what was deleted elsewhere — from the local
 *        image an empty directory would be a delete order for everything.
 */
async function cleanUpAreas(list, report = true) {
    const tell = (text) => { if (report) status(text); };

    // The own expiry: purely local, without network. Expired MAPS vanish,
    // the tracing stays — only this device knows when it loaded and how
    // long it wanted to keep it.
    const expired = await areas.evictExpiredTiles();
    if (expired > 0) {
        tell(expired === 1
            ? t('1 Gebiet: Karten abgelaufen und entfernt. Die Umrandung bleibt.')
            : t('%d Gebiete: Karten abgelaufen und entfernt. Die Umrandung bleibt.', expired));
    }

    if (!list) return;

    // What no longer stands in the directory was deleted elsewhere.
    const orphaned = await areas.evictOrphanedTiles(list);
    if (orphaned > 0) tell(orphaned === 1
        ? t('1 Gebiet anderswo gelöscht — Kacheln entfernt.')
        : t('%d Gebiete anderswo gelöscht — Kacheln entfernt.', orphaned));

    // Tiles from services that may not be prefetched at all. They date from
    // the time when prefetching treated every layer alike — and are, of all
    // things, the ones a blocking notice can hide in.
    const foreign = await areas.evictForeignTiles(blockedServices());
    if (foreign > 0) {
        tell(t('%s Kacheln von Diensten entfernt, die kein Vorabladen erlauben.', foreign.toLocaleString('de-DE')));
    }
}

/**
 * The same at startup, so nobody has to open a window for it.
 *
 * Whoever deletes their area at the desk does not want the 300 MB to stay
 * on the phone until they happen to look into the administration there.
 * But the directory is only fetched if this device has stored areas at all
 * — otherwise it would cost every page load a request for a rare case.
 */
async function cleanUpAreasOnStart() {
    try {
        const hasAreas = (await caches.keys()).some(n => n.startsWith('area-'));
        if (!hasAreas) {
            await cleanUpAreas(null, false);
            return;
        }
        // Without network this throws, and then everything just stays put —
        // rightly so.
        await cleanUpAreas(await areas.areasFromServer(), false);
    } catch (e) {
        if (!e?.isNetworkError) console.warn('Areas not cleaned up:', e);
    }
}

// Not immediately: the map should stand first. The cleanup is in no hurry.
window.addEventListener('load', () => setTimeout(cleanUpAreasOnStart, 3000));

/**
 * Honor "Aktuell halten" on page load.
 *
 * WHY HERE AND NOT ONLY ON TYPING. Refreshing used to hang on the title
 * field of a species list — whoever had set the checkmark and afterwards
 * only looked at the map never got a new version. Exactly that was
 * reported: checkmark in, "Aktualisieren" still offered, even after
 * reloading.
 *
 * The concern about work on page load does not apply here. It was aimed at
 * unasked FETCHING — and nothing is fetched: the refresh only touches what
 * already lies on the device and asks with the stored tag. As long as
 * nothing has changed, that is a few hundred bytes for a 304.
 */
window.addEventListener('load', () => setTimeout(refreshSpeciesList, 4000));


/**
 * Open a window from out of the administration.
 *
 * The offline areas are the one entry of the administration that does not
 * fit on a page of its own: tracing and weighing the tiles need the map
 * layers. Instead of hiding it there, the link leads back onto the map and
 * says via the address what is to be opened.
 *
 * The address is cleaned up again afterwards — otherwise the window pops
 * up anew on every reload.
 */
async function windowFromUrl() {
    const p = new URLSearchParams(location.search);

    /* The chat with a friend, opened from another page — the window
       lives on the map. Once the link behind a friend's row of the
       administration (gone 09.09.2026); SINCE 10.10.2026 THE ROAD IS IN
       USE AGAIN: the chat bubble in the header of Fundbrowser,
       Bestimmung and Verwaltung lists the friends, and each row leads
       here (header_chat in includes/page.php). */
    if (p.get('window') === 'friend-chat') {
        history.replaceState(null, '', location.pathname);
        const friend = (start.friends ?? []).find(f => f.id === Number(p.get('friend')));
        if (friend) chat.openFriendChat(friend, start.user?.id);
        else status(t('Diesen Freund gibt es hier nicht mehr.'), true);
        return;
    }

    // The container area (birth filter, share_content.php): the
    // administration sends people here for tracing, because drawing only
    // works on the map — the same bar as for areas and offline areas. The
    // creation window's state meanwhile waits in sessionStorage; "Fertig"
    // adds the corners and goes back.
    /* THE PLACE FILTER OF ORDNEN (23.08.2026): the
       administration sends people here to draw, and here they have what
       framing an area needs — their lists, their points, the layer they
       are used to. Simpler than the container's handover below: the drawn
       areas live in the settings bundle, so this writes into the very list
       Organisieren reads on return; nothing has to be carried over.

       The sidebar is deliberately left as it was: whoever comes to frame
       points wants to switch lists on and off while doing so. */
    if (p.get('window') === 'organize-area') {
        history.replaceState(null, '', location.pathname);
        beginOutline({
            title:   t('Filterbereich umranden'),
            message: t('Ecke für Ecke antippen, dann „Fertig" — es geht zurück ins Organisieren.'),
            // The sidebar stays: the area is drawn AROUND points, and
            // which ones are shown is decided there.
            keepSidebar: true,
            done:    (corners) => {
                // Six decimal places like everywhere — around eleven centimeters.
                state.areas.push(corners.map(([lat, lon]) =>
                    [Number(lat.toFixed(6)), Number(lon.toFixed(6))]));
                areasChanged();
                /* FLUSH BEFORE LEAVING — the bundled write is 300 ms late
                   (settings.js), and the navigation below is faster. The
                   first version drew the area, went back, and it was gone
                   without a word. */
                settings.flush();
                location.href = (window.START_DATA?.baseUrl ?? '')
                    + 'pages/administration.php#organize';
            },
        });
        return;
    }

    if (p.get('window') === 'filter-area') {
        history.replaceState(null, '', location.pathname);
        beginOutline({
            title:   t('Freigabegebiet umranden'),
            message: t('Ecke für Ecke antippen, dann „Fertig" — es geht zurück in die Verwaltung.'),
            // The same reason as at the filter area above (
            // „Das braucht eigentlich der Container auch"): a birth
            // filter is drawn around the finds it is meant to catch.
            keepSidebar: true,
            done:    (corners) => {
                let state = {};
                /* 'map.containerFilter' AND NOT 'karte.…', and the three places have to
                agree: map.js writes the drawn corners here, shared.js picks them up on
                the administration page, and legacy.js renames an entry left over from
                the German names to exactly this one. The renaming was already aiming at
                the new name while both users still wrote the old — so legacy.js deleted,
                on every page load, precisely the entry the handover depends on, and the
                outlined corners never arrived. */
                try { state = JSON.parse(sessionStorage.getItem('map.containerFilter')) ?? {}; } catch { /* then just empty */ }
                // Six decimal places like the point columns — around eleven centimeters.
                state.corners = corners.map(([lat, lon]) => [Number(lat.toFixed(6)), Number(lon.toFixed(6))]);
                sessionStorage.setItem('map.containerFilter', JSON.stringify(state));
                location.href = (window.START_DATA?.baseUrl ?? '') + 'pages/administration.php#share';
            },
        });
        return;
    }

    if (p.get('window') !== 'areas') return;

    // Clean up immediately: otherwise the same thing pops up again on
    // reload, and a back in the browser lands in a loop.
    history.replaceState(null, '', location.pathname);

    // Tracing — the one thing that does not work at all without the map.
    if (p.get('new')) { beginAreaOutline(); return; }

    // Without an id there is nothing left to do here: the list stands in
    // the administration, the map only knows "show this one area" and
    // "load this one". Such an address is a leftover from earlier — then
    // the map simply stays, that is the most harmless answer.
    const id = Number(p.get('show') || p.get('load'));
    if (!id) return;

    const { list } = await areas.getAreas();
    const g = list.find(x => x.id === id);
    if (!g) { status(t('Das Gebiet gibt es nicht mehr.'), true); return; }

    // Whoever follows a "Zeigen" link wants to see THAT area — the
    // resumed location display must not pull the map away from it.
    pauseFollowing();
    map.fitBounds(L.polygon(g.corners).getBounds());
    if (p.get('load')) {
        openLoadWindow(g);
    } else {
        // Since 14.08.2026 "Zeigen" means: see the tracing AND be able to
        // pull at it — not merely stand on an empty view.
        beginAreaEditing(g);
    }
}

windowFromUrl();

/* --- A LAYOUT PROBE FOR THE PHONE (temporary, 30.08.2026) ---------------
   The point window overflows the screen on the maintainer's Xiaomi and on no
   emulation here — Chromium at 320/360/375/393, Firefox via geckodriver,
   enlarged root font: all clean. So the number has to come FROM THE
   DEVICE: ?layoutprobe measures once the window is open and shows every
   viewport figure plus the widest offenders as an alert — the phone has
   no console, and a screenshot of the alert travels well. REMOVE once
   the culprit is caught. */
if (new URLSearchParams(location.search).has('layoutprobe')) {
    const probe = setInterval(() => {
        const d = document.getElementById('detail');
        if (d.getBoundingClientRect().width === 0) return;
        clearInterval(probe);
        // Give the window a beat to fill before measuring it.
        setTimeout(() => {
            const dc = d.querySelector('.detail-content');
            const vv = window.visualViewport;
            const lines = [
                'innerWidth ' + window.innerWidth,
                'html clientWidth ' + document.documentElement.clientWidth,
                'visualViewport ' + (vv ? Math.round(vv.width) + ' px, scale '
                    + vv.scale.toFixed(3) + ', offsetLeft ' + Math.round(vv.offsetLeft) : '—'),
                'screen ' + screen.width + ' × ' + screen.height + ', dpr ' + devicePixelRatio,
                'detail ' + Math.round(d.getBoundingClientRect().width)
                    + ', content ' + dc.clientWidth + '/' + dc.scrollWidth,
                'root font ' + getComputedStyle(document.documentElement).fontSize
                    + ', body scroll ' + document.documentElement.scrollWidth,
            ];
            /* ROUND TWO (the maintainer's alert of 30.08.2026): screen 369,
               but layout viewport and body scroll 406 — the window only
               INHERITS the 406 via width:100%. The culprit sits in the
               body flow OUTSIDE #detail (fixed boxes do not stretch the
               document), so this round scans the whole body and names
               every element that pushes past the screen edge on its own
               account, its computed width alongside — a closed <dialog>
               with display but without [open] would show up here. */
            const edge = document.documentElement.clientWidth;
            const wide = [];
            document.body.querySelectorAll('*').forEach(el => {
                if (el.closest('#detail')) return;
                const r = el.getBoundingClientRect();
                if (r.width > 0 && r.right > edge + 1) {
                    wide.push([Math.round(r.width), Math.round(r.left),
                        (el.id || el.getAttribute('class') || el.tagName) + ''
                            + ' [' + getComputedStyle(el).width + ']']);
                }
            });
            wide.sort((a, b) => b[0] - a[0]);
            lines.push('im Fluss über der Kante (' + wide.length + '):');
            for (const [w, left, name] of wide.slice(0, 10)) {
                lines.push('  ' + name.slice(0, 44) + ' → ' + w + ' @' + left);
            }
            alert(lines.join('\n'));
        }, 800);
    }, 500);
}

/**
 * Bring back an input that did not survive the clearing away.
 *
 * WHEN THIS HAPPENS: whoever takes a photo sends this page into the
 * background — the camera app wants memory, and Android takes it. On
 * coming back, the page reloads, and a half-labeled point would stand
 * there empty again. On a phone with little RAM that is no edge case but
 * the normal case.
 *
 * What comes back here is expressly NOT saved: it stands in the fields and
 * is marked as due, just as it was before the switch. Saving it silently
 * would be the worse solution — then somebody would have changed a point
 * without wanting to.
 */
async function restoreFormState() {
    /* THE NAME MUST NOT BE 'state', AND THAT IS NOT A MATTER OF TASTE.
       Up to 21.08.2026 it was, and it shadowed the application's own
       state — the module-wide object holding points, lists and selection.
       Before the English pass the two were called 'stand' and 'zustand'
       and could not collide; commit fa6b8d5 turned both into 'state'.

       Since then this function did the exact opposite of its job: the
       line below reached into the RESCUED RECORD for .points, found
       undefined, and threw — one line AFTER formState.forget() had
       already thrown the rescue away. Nobody saw it: the call at the
       bottom of this file has no await and no catch, so the rejected
       promise ended up in the console of a phone lying in a jacket
       pocket.

       What that costs is exactly the reported loss. The rescue exists for
       the camera: set a point, type the name, photograph, keep typing.
       Android tears the page down while the camera is in front; on
       coming back the input should be standing there again. Instead it
       was deleted. Whoever labels twenty finds and photographs each one
       comes home with twenty points and no names. */
    const rescue = formState.get();
    if (!rescue) return;

    const point = state.points.get(rescue.pointId);
    if (!point) {
        // The point is gone — then the rescue can go too, it has no
        // addressee any more.
        formState.forget();
        return;
    }

    selectPoint(point.id);

    const w = rescue.values || {};
    if (typeof w.title === 'string') fields.title.value = w.title;
    if (typeof w.note === 'string') fields.note.value = w.note;
    if (typeof w.coord === 'string' && w.coord) fields.coord.value = w.coord;
    if (typeof w.found === 'string') fields.found.value = w.found;
    if (typeof w.number === 'string') fields.number.value = w.number;

    if (Array.isArray(w.lists)) {
        const wanted = new Set(w.lists);
        for (const box of fields.lists.querySelectorAll('input')) {
            box.checked = wanted.has(Number(box.value));
        }
        // The custom fields follow the list selection — draw first, then
        // fill, otherwise you write into fields that are about to be gone.
        drawExtraFields(w.extra ?? {});
    } else if (w.extra) {
        drawExtraFields(w.extra);
    }

    // The comparison state must move along, otherwise the input protection
    // takes what was brought back for untouched and the next sync
    // overwrites it.
    lastFilled = { id: point.id, state: detailState() };
    markUnsaved(true);

    /* ONLY NOW. Forgetting stood at the top, before everything that could
       go wrong — and so a single throw in between turned the rescue into
       a shredder. The input is in the fields; from here on it may be
       dropped. Whoever adds a line above this one should ask themselves
       what happens if it throws. */
    formState.forget();

    status(t('Deine Eingabe von vorhin steht wieder da — noch nicht gespeichert.'));
}

/*
 * Establish the stock — from the store on the device, then caught up from
 * the server where something changed.
 *
 * DOWN HERE, for the same reason as everything else in this section: the
 * function touches drawMarker(), drawList() and the map itself,
 * and those stand as const further up. Called further up it would run
 * into the temporal dead zone.
 *
 * WITHOUT await. The rest of the setup should not wait for it; the map
 * already stands, and the points join as soon as they are there.
 */
const inventoryReady = establishInventory();
const sharedReady = establishShared();
// The name packages the lists need, once both stocks stand — at once, not at the first typed name (speciesNeedsSync).
Promise.all([inventoryReady, sharedReady]).then(speciesNeedsSoon, speciesNeedsSoon);

/* AFTER THE STOCK, and it never was. restoreFormState() stood here as a
   bare call directly beneath establishInventory(), which is deliberately
   NOT awaited — so it looked for its point in a state.points that was
   still empty, found nothing, and gave up. Even with the shadowing bug
   above repaired the rescue could not have worked: it always ran too
   early.

   Only the rescue waits, not the rest of the setup. The map stands, the
   points join, and the moment they are there the begun input goes back
   into the window.

   WITH A CATCH, because without one a throw in here is invisible: an
   unhandled rejection lands in a console nobody opens while standing in
   the woods. If the rescue fails, it must at least say so — and what it
   was rescuing stays put instead of being dropped. */
inventoryReady.then(restoreFormState).catch((e) => {
    console.error('Form state could not be brought back:', e);
    status(t('Deine begonnene Eingabe von vorhin ließ sich nicht zurückholen.'), true);
});

/* THE FUNDBROWSER SENDS A FIND HERE (?point=<id>, 10.10.2026): a double
   click on a tile, or „Auf der Karte öffnen", means „work on this one",
   and the work lives in the point window. AFTER BOTH STOCKS, own and
   foreign — a tile may be Ben's find, and the foreign store fills last;
   asked earlier, the point is simply not there yet, the trap
   restoreFormState fell into above. The address is cleaned at once, or
   every reload would open the window anew. A find in a list that is
   switched off opens all the same and leaves the list's tick alone (a
   point never ticks its list, 26.09.2026). */
{
    const asked = new URLSearchParams(location.search);
    const wantedPoint = Number(asked.get('point'));
    if (wantedPoint) {
        asked.delete('point');
        history.replaceState(null, '', location.pathname + (asked.size ? '?' + asked : '') + location.hash);
        Promise.all([inventoryReady, sharedReady]).then(() => {
            const point = state.points.get(wantedPoint) ?? state.sharedPoints.get(wantedPoint);
            if (!point) {
                status(t('Diesen Fund gibt es hier nicht mehr.'), true);
                return;
            }
            pauseFollowing();
            centreVisible([point.lat, point.lon], Math.max(map.getZoom(), 17));
            selectPoint(wantedPoint);
        }).catch((e) => console.error('The find from the Fundbrowser could not be opened:', e));
    }
}

/*
 * Find the search flap again the way it was left.
 *
 * DOWN HERE AND NOT UP AT showHeaderSearch(). The "closed" path cleans up —
 * it empties the field, closes the hit list and takes the crosshair away
 * —, and removeCrosshair() accesses pointButton, a const that only stands
 * four hundred lines further down. A call further up would run into the
 * temporal dead zone, and only in the branch you catch last while trying
 * things out. Exactly this trap has struck here once before.
 *
 * Therefore: only the "was open" case is called at all. "Was closed" is
 * the document's initial state anyway, nothing to do for that.
 */
if (settings.get('headerSearchOpen', false) === true) {
    showHeaderSearch(true, false);
}

// What is not up yet should get there — not only at the next photo.
photos.transfer();


