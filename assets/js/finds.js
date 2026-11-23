/**
 * Fundbrowser — the finds as tiles, a search that lists every find, and the
 * find opened as the cockpit (pages/finds.php, includes/finds.php).
 *
 * ONE LOAD, THEN EVERYTHING HAPPENS HERE. The stock comes whole from
 * api/v1/finds.php — a few hundred bytes per find, the pictures only as
 * addresses —, and browsing by whose finds, by year and by a word is a
 * matter of showing and hiding, without a request. The pictures themselves
 * load when their tile scrolls into sight (loading="lazy"): a recording's
 * preview is a round trip to its cloud.
 *
 * TWO WAYS TO SEE THE STOCK (11.10.2026): browsing shows the
 * finds WITH pictures as tiles, Lightroom's grid; typing a word lists
 * EVERY find that matches — also those without a picture — as rows, newest
 * find first, with day, place and tour, so a find can be told apart by its
 * text („Weil die letzten bearbeitet man in aller Regel"). The search is
 * the one way to a find without a picture, and it needs no switch.
 *
 * A FIND IS OPENED IN PLACE (one click since 15.11.2026, or Enter — the
 * pencil that once stood on every tile and row went the same day): it takes the whole area from the tiles — NO PANEL BESIDE THEM any
 * more — and stands at #fund=<id>, so the browser's back button, a reload
 * and a link all land where one was. Escape goes back with the selection and
 * the scroll position kept. The opened find is the point window's panels as
 * the page's tabs, each with the whole width (12.10.2026): Text with the
 * find's face beside it, Bilder — the rail of pictures and the picture
 * viewer IN the page, its third host after the map and Organisieren —,
 * Sequenzen, Dateien, Karte. Viewer and plates are each asked what they
 * ask of a host (photowindow.js, plates.js), the window by cockpit.js.
 *
 * THE COUNTS BESIDE A CHOICE ANSWER „HOW MANY WOULD I SEE": each side's
 * numbers are counted under the OTHER side's choice and the search word,
 * over what the current view holds (the finds with pictures while
 * browsing, every find while searching or while a kind of the section
 * „Kultur" is chosen).
 */

import { settings } from './settings.js';
import { t } from './i18n.js';
import { iconHtml } from './icons.js';
import { readableDate } from './dates.js';
import { request } from './api.js';
import { toast } from './toast.js';
import { mountPhotoWindow } from './photowindow.js';
import { developThumb } from './develop.js';
import { mountPlates } from './plates.js';
import { findDetails } from './finddetails.js';
import { mountCockpit } from './cockpit.js';
import { saveSequenceFasta } from './photogallery.js';
import { fitRows, widthsForHeight } from './rowfit.js';
import { findNumberFallback } from './findnumber.js';
import { subjectGroups } from './gallerysort.js';
import { subjectIcon } from './subjecticons.js';
import { vocab } from './vocabulary.js';
import { hoverPick } from './hoverpick.js';
import { mountArchive } from './findsarchive.js';
import { mountBlend } from './blend.js';
import { wordsOrSigns } from './wordsorsigns.js';
import { personName } from './names.js';
import { scrollRemember } from './scrollkeep.js';

const root = document.getElementById('finds');
const API = root.dataset.api;
const MAP = root.dataset.map;
const browse = document.getElementById('finds-browse');
const side = document.getElementById('finds-side');
const grid = document.getElementById('finds-grid');
const empty = document.getElementById('finds-empty');
const results = document.getElementById('finds-results');
const resultRows = document.getElementById('finds-results-rows');
const count = document.getElementById('finds-count');
const search = document.getElementById('finds-search');
const size = document.getElementById('finds-size');
const sizeLabel = document.getElementById('finds-size-label');
const fund = document.getElementById('finds-fund');
const back = document.getElementById('finds-back');
/* THE POINT WINDOW'S HEADER MOVES INTO THE FIND'S BAR
   (12.10.2026): the name becomes the bar's heading, the trash can its right
   end — the window under it starts with its tabs, and nothing is written
   twice. Moved, not copied: the elements keep their ids and pointwindow.js
   writes into the very same ones.

   TWO LINES, EVERY FACT IN SIGHT, NO ⓘ (13.10.2026: „zweizeilig
   … sodass die Informationen unter dem Artnamen stehen. Und dann könnten
   wir uns den Info-Button sparen und einfach alle Infos anzeigen mit
   entsprechenden Icons vorne dran"). Under the name every fact the ⓘ's
   table holds — number, place, height, accuracy, map sheet, protected
   areas, Red List, DGfM —, each behind its sign, its explanation in the
   tooltip; drawn from that table (pointwindow.js, drawFactsTable, which
   names each row's kind), so the window stays the one that knows the
   facts. The window's short line and its ⓘ stay in the page unseen. In the
   ⓘ's place „Zeigen" („statt des Info-Buttons würde ich den Zeigen-Button
   nehmen … mit der gleichen Hintergrundfarbe wie der Löschen-Button, nur
   vielleicht das Zielkreuz blau"). */
const fundTitle = document.getElementById('detail-title');
const fundWhere = document.getElementById('d-accuracy');
fundTitle.classList.add('finds-fund-title');
fundWhere.classList.add('finds-fund-where');
const factsLine = document.createElement('div');
factsLine.className = 'finds-fund-facts';
/* WHOSE FIND STANDS RIGHT BEHIND ITS NAME (15.10.2026: „das
   direkt hinter den Namen schreiben. In der Kopfzeile ‚von Finn' zum
   Beispiel. Aber die zweite Zeile unten muss weg"): the window's own „von …"
   line under the bar said it a second time and goes (app.css). */
const fundOwner = document.createElement('span');
fundOwner.className = 'finds-fund-owner';
const titleRow = document.createElement('div');
titleRow.className = 'finds-fund-titlerow';
titleRow.append(fundTitle, fundOwner);
document.getElementById('finds-fund-heading').append(titleRow, factsLine, fundWhere);
const factsTable = document.getElementById('d-facts-more');
// „Zurück zum Schlüssel" first: a word among the signs, and the one that is due when it shows.
document.getElementById('finds-fund-actions').append(
    ...[document.getElementById('d-photo-microscope'), document.getElementById('d-key-back'),
        document.getElementById('d-key')].filter(Boolean),
    document.getElementById('d-center'), document.getElementById('d-delete'),
    document.getElementById('d-facts-toggle'));
/* MIKROSKOPIEREN AND SCHLÜSSELN IN THE BAR (20.11.2026: „oben
   in der Kopfzeile … den Button mikroskopieren und einen Button schlüsseln,
   falls ich schlüsseln darf … Und die Buttons, wo sie vorher waren … die
   kannst du entfernen" — first in the middle, then the same hour: „doch
   nicht in die Mitte der Kopfzeile, sondern rechts an den Rand. Zu dem
   Löschen und zum Punkt gehen Button dazu"). The two buttons MOVE into the
   actions, ids and all: microscope.js still shows „Mikroskopieren" only
   where a camera is set up and a cloud takes the uploads, photogallery.js
   still opens it, and pointwindow.js still shows the key only to whoever
   may read the keys and write the find's species (drawKeyings) and leads
   it to the identification module. Before, „Mikroskop" stood in the
   pictures' rail and the key as a bare sign behind „Art". The map's point
   window keeps both where they were.
   ONE PLACE FOR THE KEY (the same message: „Für den Schlüssel gibt es in
   der Kopfzeile ja schon einen Button zurück zum Schlüssel. Das ist im
   Prinzip der gleiche Button dann, der nur seine Aufschrift ändert"):
   „Zurück zum Schlüssel" stands right before „Schlüsseln", and while it
   shows, „Schlüsseln" steps back (app.css) — one button that changes its
   words. */
{
    const key = document.getElementById('d-key');
    if (key) {
        key.className = 'button subtle small';
        key.append(t('Schlüsseln'));
        key.removeAttribute('aria-label');
    }
}
// „Zeigen" as a sign alone, like its neighbours (pages/finds.php says why) — the word stays as tooltip.
{
    const show = document.getElementById('d-center');
    for (const node of [...show.childNodes]) if (node.nodeType === Node.TEXT_NODE) node.remove();
    show.className = 'symbol-button small';
    show.title = t('Zeigen');
    show.setAttribute('aria-label', show.title);
}
// The table the line is drawn from, and the „von …" line of a foreign find, under the bar.
document.getElementById('finds-fund-bar').after(factsTable, document.getElementById('d-origin'));

/* One fact: its sign, its value, the label and the explanation as tooltip. */
const FACT_SIGNS = { number: 'hash', day: 'calendar', owner: 'person', place: 'place', elevation: 'mountain',
    accuracy: 'accuracy', mtb: 'map', protected: 'area', redlist: 'warning', dgfm: 'badge' };
function factPiece(kind, value, tip) {
    const piece = document.createElement('span');
    piece.className = 'finds-fact';
    piece.innerHTML = iconHtml(FACT_SIGNS[kind] ?? 'info');
    piece.append(value);
    if (tip) piece.title = tip;
    return piece;
}

/**
 * The second line: the overview's words while the find loads (number, day,
 * whose), then every row of the window's facts table, the day and the owner
 * kept in after the number — the table has neither.
 */
function drawFacts() {
    const f = current;
    factsLine.textContent = '';
    if (!f) return;
    const pieces = [];
    const rows = [...factsTable.querySelectorAll('dt')].map((dt) => {
        const dd = dt.nextElementSibling;
        const value = [...dd.querySelectorAll('.detail-facts-line')].map((l) => l.textContent).join('; ');
        const hint = dd.querySelector('.detail-facts-hint')?.textContent ?? '';
        return { kind: dt.dataset.kind ?? '', label: dt.textContent, value, hint };
    });
    const number = rows.find((r) => r.kind === 'number');
    pieces.push(number ? factPiece('number', number.value, number.label + ' — ' + number.hint)
        : factPiece('number', f.number, t('Fundnummer')));
    if (dayOf(f)) pieces.push(factPiece('day', dayOf(f), t('Funddatum')));
    fundOwner.textContent = f.own ? '' : t('von %s', personName(f.owner) || t('Unbekannt'));
    fundOwner.hidden = f.own;
    for (const r of rows) {
        if (r.kind === 'number') continue;
        pieces.push(factPiece(r.kind, r.value, r.hint ? r.label + ' — ' + r.hint : r.label));
    }
    factsLine.append(...pieces);
}
new MutationObserver(() => drawFacts()).observe(factsTable, { childList: true });

/* THE PICTURES' WORK MOVES INTO THE RAIL of the tab „Bilder"
   (12.10.2026): out of the window's Belege panel — the upload button with its
   hint and file inputs, the plates' row, the gallery. Moved, not copied: the
   ids stay, and the gallery and the plates draw into the very same elements.

   „AUSWÄHLEN" SAYS „BILDER HOCHLADEN" HERE. On the phone it stands beside
   „Aufnehmen", and the pair says camera or file; at the desk there is no
   camera (app.css hides it), and the maintainer, meeting the word alone, did not
   know what it does (12.10.2026). It takes a finished file — a stack, a
   micrograph, a raw file — into one's own cloud. */
document.getElementById('finds-belege').append(
    ...['d-photo-buttons', 'd-photo-camera', 'd-photo-file', 'd-photos']
        .map((id) => document.getElementById(id)));
/* THE PLATES HAVE A TAB OF THEIR OWN (12.10.2026): their row
   moves out of the rail into „Tafeln", and the plate editor's <dialog> under
   it, opened there in the page (plates.js, host.inPage) — as the viewer in
   „Bilder". */
const plateRow = document.getElementById('d-plates');
document.getElementById('finds-plate-row').append(plateRow);
const plateWindowEl = document.getElementById('plate-window');
document.getElementById('finds-plate-editor').append(plateWindowEl);
const plateCount = document.getElementById('finds-plate-count');
const plateEmpty = document.getElementById('finds-plate-empty');
const uploadWord = document.getElementById('d-photo-select')?.lastChild;
if (uploadWord?.nodeType === Node.TEXT_NODE) uploadWord.textContent = t('Hochladen');
/* THE SHORT WORD (14.11.2026): „Hochladen" — over the picture
   rail it is plain what is uploaded. The map's point window keeps
   „Auswählen". („Mikroskop" stood beside it until 20.11.2026 and is
   „Mikroskopieren" in the find's bar now.) */
/* THE NARROW RAIL KEEPS THE SIGNS (04.11.2026: the rail dragged
   as small as it goes, and „Mikroskopieren" a bit wider than it — a bar
   under the column). Each button's word goes into a span the stylesheet can
   fold away when it no longer fits (wordsorsigns.js, is-signs); the word stays
   as the button's tooltip and its name for a screen reader. */
for (const b of document.querySelectorAll('#d-photo-buttons > .button')) {
    const word = b.lastChild;
    if (word?.nodeType !== Node.TEXT_NODE || !word.textContent.trim()) continue;
    const span = document.createElement('span');
    span.className = 'finds-button-word';
    span.textContent = word.textContent;
    word.replaceWith(span);
    b.title = span.textContent;
}
/* THE BUTTONS STAY ON TOP while the pictures scroll under them
   (07.11.2026: „oben sticky hängen bleiben", app.css). Their height — one
   row of buttons, or two with the cloud's hint — is handed to the
   stylesheet, so the group menu beside the pictures sticks just below them
   instead of sliding under them. */
{
    const head = document.getElementById('d-photo-buttons');
    const rail = head?.closest('.finds-rail');
    // Words or signs, one row either way (wordsorsigns.js, app.css says why).
    if (head && rail) wordsOrSigns(head, () => rail.style.setProperty('--finds-rail-head', head.offsetHeight + 'px'));
    // The rail's own height, for the quick choice's ground down to its foot (app.css, .finds-rail-menu).
    if (rail) new ResizeObserver(() => rail.style.setProperty('--finds-rail-h', rail.clientHeight + 'px')).observe(rail);
}
const noPictures = document.getElementById('finds-no-pictures');
const gallery = document.getElementById('d-photos');

/* „EBENEN" (11.11.2026; assets/js/blend.js): the renderings of
   one focus stack — Helicon's methods A, B and C — laid over each other
   and painted into one picture. A TAB OF „BEARBEITEN" in the viewer (it
   was a button here and a window of its own for a day): the tool is
   mounted here, where the find and the rail are, and handed to the photo
   window as host.layers. Offered on one's own find with at least two
   pictures and a cloud — the finished picture and the work on it go
   there, and without a cloud nothing is edited (the rule of 10.11.2026).

   THE PICTURES AS THE TOOL WANTS THEM: in the gallery's order, each with
   the name one would say — „Hauptmotiv 2", the subject name and its place
   among those of that name, which is what the renaming calls the files
   too — and the address of the picture itself, not of a preview. */
function blendPictures() {
    if (!current || !detail) return [];
    const count = new Map();
    return current.pictures.map((p) => {
        const title = (p.title ?? '').trim();
        const n = (count.get(title) ?? 0) + 1;
        count.set(title, n);
        const row = p.kind === 'cloud' ? detail.files.find((x) => x.id === p.id) : detail.point.photos.find((x) => x.id === p.id);
        return {
            // The name shown international (vocabulary.js); `file` below keeps the stored word, as before.
            key: keyOf(p), n, name: title ? `${vocab(title)} ${n}` : t('Bild %d', n), thumb: p.thumb,
            original: p.kind === 'cloud' ? `${START.baseUrl}pages/cloud_thumb.php?id=${p.id}&original=1`
                : `${START.baseUrl}pages/photo.php?id=${p.id}`,
            w: Number(row?.width) || 0, h: Number(row?.height) || 0,
            // What the one picture made of the layers takes over (blend.js, mergeRun).
            title: title || null, scale: row?.scale ?? null, takenBy: row?.taken_by ?? null,
            file: row?.name ?? row?.original_name ?? (title ? `${title} ${n}` : null),
        };
    });
}
const blend = mountBlend({
    pictures: blendPictures,
    point: () => current?.id ?? 0,
    baseUrl: window.START_DATA.baseUrl,
    may: () => !!(current && detail?.own && current.pictures.length >= 2 && window.START_DATA.cloudConnected === true),
    // The picture in the viewer — the viewer's own word, as soon as it has one.
    shown: () => tools?.viewer.pictureKey() ?? lastKey,
    // The pictures that carry a picture's subject name — his three „Hauptmotiv".
    subject: (key) => {
        const shown = current?.pictures.find((p) => keyOf(p) === key);
        return shown ? current.pictures.filter((p) => groupOf(p) === groupOf(shown)).map(keyOf) : [];
    },
    /* THE RAIL'S TILES, for the tick boxes: every thumbnail says whose it
       is (develop.js, data-develop-of); a recording's tile is the link
       around its picture. The boxes lie in the box AROUND the gallery —
       the gallery's own children are walked as tiles (railGroups, the
       sorting). */
    tiles: () => [...gallery.querySelectorAll('img[data-develop-of]')]
        .map((img) => ({ key: img.dataset.developOf, el: img.closest('.cloud-file') ?? img })),
    tilesWatch: () => gallery,
    tilesRoot: () => document.getElementById('finds-belege'),
    stackChanged: () => markCurrent(),
    /* The layers became one picture, and perhaps the single ones went: the
       records anew, the rail with them — and the new picture into the
       viewer where the one it showed is gone. */
    refresh: async (key) => {
        await refreshDetail();
        if (key) showKey(key);
    },
});

/* THE WINDOW'S FOOTER: „Zeigen" went up into the find's bar (above), the
   line that says how the autosave went stands at the tab bar's right end
   (cockpit.js). Speichern goes along and stays unseen (app.css) — the window
   saves itself at the desk since 13.10.2026; „Schließen" is the bar's
   „Zurück" here. */
document.getElementById('finds-tabs-end').append(document.getElementById('d-save'));
/* The sub-tabs' numbers ride on the page's tabs — pointwindow.js goes on
   writing into them by their ids. */
const tabBar = document.getElementById('finds-tabs');
const tabButtons = [...tabBar.querySelectorAll('[data-tab]')];
const tabButton = (name) => tabButtons.find((b) => b.dataset.tab === name);
tabButton('sequences').append(' ', document.getElementById('d-subcount-sequences'));
tabButton('files').append(' ', document.getElementById('d-subcount-files'));
const pictureCount = document.getElementById('finds-picture-count');

/* THE FIND'S HEAD FOLDS AWAY (09.11.2026: „der komplette Fund
   braucht eigentlich mehr Platz … oberhalb dieser Linie das einklappbar
   machen und nur mit einem Mausüberfahren wieder ausklappen … ein kleines
   Symbol mittig setzen zum Zuklappen und wenn es zugeklappt ist, dann
   natürlich einen Handle-Balken oben hin, den man mit der Maus überfahren
   kann zum Aufklappen oder draufklicken kann, um es offen zu lassen").
   What folds is the find's bar and its tabs — everything above the tabs'
   line. Folded, a thin bar stands at the top: the pointer over it lays the
   head OVER the content (is-head-peek — the content keeps its place, so no
   picture or plate is fitted anew for a glance), and it folds again once
   the pointer has left the head; a click (a tap on a touch screen) opens it
   for good. Kept per device (settings findHeadFolded). */
const fundEl = document.getElementById('finds-fund');
const fundBarEl = document.getElementById('finds-fund-bar');
const headFoldBtn = document.createElement('button');
headFoldBtn.type = 'button';
headFoldBtn.className = 'finds-head-fold';
headFoldBtn.title = t('Kopf einklappen');
headFoldBtn.setAttribute('aria-label', t('Kopf einklappen'));
headFoldBtn.innerHTML = iconHtml('fold-triangle');
tabBar.append(headFoldBtn);
const headHandle = document.createElement('button');
headHandle.type = 'button';
headHandle.className = 'finds-head-handle';
headHandle.title = t('Kopf ausklappen');
headHandle.setAttribute('aria-label', t('Kopf ausklappen'));
headHandle.innerHTML = iconHtml('fold-triangle');
fundEl.prepend(headHandle);
let headPeekTimer = null;
function headFold(on, keep = true) {
    fundEl.classList.toggle('is-head-folded', on);
    fundEl.classList.remove('is-head-peek');
    if (keep) settings.set('findHeadFolded', on);
    // Whatever fits itself to its room (viewer, plate editor, map) hears of the new room.
    window.dispatchEvent(new Event('resize'));
}
function headPeek() {
    clearTimeout(headPeekTimer);
    if (!fundEl.classList.contains('is-head-folded') || fundEl.classList.contains('is-head-peek')) return;
    fundEl.classList.add('is-head-peek');
    // The content steps up by exactly the head's height, so it stays where it was.
    fundEl.style.setProperty('--head-peek', fundBarEl.offsetHeight + tabBar.offsetHeight + 'px');
}
function headPeekEnd(e) {
    if ([headHandle, fundBarEl, tabBar].some((n) => n.contains(e.relatedTarget))) return;
    clearTimeout(headPeekTimer);
    headPeekTimer = setTimeout(() => fundEl.classList.remove('is-head-peek'), 250);
}
headHandle.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') headPeek(); });
for (const n of [headHandle, fundBarEl, tabBar]) {
    n.addEventListener('pointerenter', () => { if (fundEl.classList.contains('is-head-peek')) clearTimeout(headPeekTimer); });
    n.addEventListener('pointerleave', headPeekEnd);
}
headHandle.addEventListener('click', () => headFold(false));
headFoldBtn.addEventListener('click', () => headFold(true));
/* THE EMPTY BAND TO THE WINDOW'S EDGE COUNTS AS THE BAR
   (09.11.2026: „wenn ich das Handle … nach oben überfahre bis zum
   Fensterrand, klappt es mir wieder kurz ein … Könntest du diesen
   Außenbereich noch mit dazu nehmen, wenn es eingeklappt ist, diesen
   Leerbereich quasi, von Handle bis Fensterrand?"). The page keeps a margin
   to the window; a pointer that overshoots the thin bar into it opens the
   head as well, and keeps it open (edgeZone). The same for the rail below
   and the right-hand sides in the photo window. */
const edgeZone = (handle, side, e) => {
    const r = handle.getBoundingClientRect();
    if (!r.width || !r.height) return false;
    if (side === 'top') return e.clientY <= r.bottom;
    const across = e.clientY >= r.top && e.clientY <= r.bottom;
    return across && (side === 'left' ? e.clientX <= r.right : e.clientX >= r.left);
};
document.addEventListener('pointermove', (e) => {
    if (e.pointerType !== 'mouse' || e.buttons || fundEl.hidden || !fundEl.classList.contains('is-head-folded')) return;
    if (edgeZone(headHandle, 'top', e)) headPeek();
});
if (settings.get('findHeadFolded', false)) headFold(true, false);

/* THE VIEWER STANDS IN THE PAGE (12.10.2026: „zwei
   Bildbetrachter hintereinander gebaut. Was schwachsinnig ist"): its
   <dialog> moves into the tab „Bilder" and opens there with show()
   (photowindow.js, host.inPage), beside the rail that replaces its strip. */
const viewerBox = document.getElementById('finds-viewer');
const photoWindowEl = document.getElementById('photo-window');
viewerBox.append(photoWindowEl);
const face = document.getElementById('finds-face');

let finds = [];                  // every find, newest first (the server's order)
const byId = new Map();
const tiles = new Map();         // find id → its tile (finds with pictures only)
const rows = new Map();          // find id → its row in the search's answer
let rowsBuilt = false;
/* COPIES, NOT THE STORED OBJECTS — settings.get() hands out the live
   value, and one changed in place compares equal to itself in set(). */
const filter = { who: 'all', year: 'all', kind: 'all', mode: 'finds', ...(structuredClone(settings.get('findsFilter', null)) ?? {}) };
let word = '';
let selected = null;             // the find id marked in the tiles or rows
let opened = null;               // the find id standing open, or null
let current = null;              // the find open
let scrolled = { grid: 0, results: 0 };
let detail = null;               // what the cockpit asked of the open find: point, files, rights …
let detailLoad = null;           // …and the promise of it, for whoever clicks before it is there
let tools = null;                // the viewer and the plates, mounted at the first find opened
let cockpit = null;              // the point window on this page, mounted likewise
let tab = 'text';                // the find's tab standing
let wantedTab = null;            // the remembered tab of this find, while it is not there yet
let lastKey = null;              // the picture the viewer showed last („photo:63", „cloud:17")
let views = new Map();           // this find's pictures as they were last looked at: key → zoom view (keepPicture)
let refreshing = false;          // the find's pictures are being fetched anew
let leaving = false;             // the find is being closed (close)
let listStale = false;           // the list wants fetching anew once the open find is left (listReload)
let listHiddenAt = null;         // when the tab went out of sight (listReload)
const LIST_AWAY_MS = 3000;       // a glance at another tab shorter than this keeps the list (listReload)

/* THE BILDARCHIV (02.11.2026: „Mach Variante 1, nenn es
   Bildarchiv"): the older pictures of one's own and one's friends' picture
   archives, a gallery beside the finds — findsarchive.js holds it, this page
   only switches. The switch („Funde | Bildarchiv") stands on top of the
   side where there is an archive to see at all; the server counts it with
   the finds (archiveCount), and the archive itself is fetched the first
   time one turns to it. */
const archiveBox = document.getElementById('finds-archive');
const archive = mountArchive({ box: archiveBox, base: MAP });
const archiveEmpty = document.createElement('p');
archiveEmpty.className = 'finds-empty hint-text';
archiveEmpty.hidden = true;
archiveBox.append(archiveEmpty);
let archiveCount = 0;
const inArchive = () => filter.mode === 'archive' && archiveCount > 0;

const resultsEmpty = document.createElement('p');
resultsEmpty.className = 'finds-empty hint-text';
resultsEmpty.hidden = true;
results.insertBefore(resultsEmpty, resultRows);

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const withPictures = (f) => f.pictures.length > 0;
/* A TILE ON THE WALL: a find with a picture — and no Aufsammlung
   (15.11.2026: „Bilder von Aufsammlungen, also der Aufsammlung, nicht der
   darauf gefundenen Arten, gehören nicht in den Fundbrowser … jedenfalls
   nicht in der normalen Ansicht. Natürlich in der Aufsammlungsansicht
   schon"). The dish of dung is no find to browse; the species grown from
   it are, and keep their tiles. Under „Aufsammlungen" and in a search the
   rows stand anyway (listing), and there it is. */
const onWall = (f) => withPictures(f) && !f.is_collection;
const dayOf = (f) => (f.found_at ? readableDate(f.found_at).split(' ')[0] : '');
const nameOf = (f) => f.title || t('ohne Namen');
/* What a word is searched in: name, number, place, tour, owner, the day
   written both ways — „14.09.2026" and „2026-09-14" — and the culture's
   word, so „kultur" finds the Aufsammlungen and what grew from them. */
const hayOf = (f) => [f.title, f.number, f.place, f.tour, f.owner, dayOf(f), (f.found_at ?? '').slice(0, 10), cultureWord(f)]
    .join(' ').toLowerCase();

// --- Size ---------------------------------------------------------------------

/* THE TILE'S EDGE IS ONE NUMBER, remembered per device: a large screen
   wants more of them, a tired eye larger ones. */
function applySize(px) {
    grid.style.setProperty('--tile', px + 'px');
    archiveBox.style.setProperty('--tile', px + 'px');
}
size.value = String(settings.get('findsTileSize', 180));
applySize(Number(size.value));
size.addEventListener('input', () => applySize(Number(size.value)));
size.addEventListener('change', () => settings.set('findsTileSize', Number(size.value)));

// --- Choosing -----------------------------------------------------------------

/* THE ROWS STAND WHILE A WORD IS TYPED OR A KIND IS CHOSEN. An Aufsammlung
   mostly has no picture of its own — the dung in its dish is rarely
   photographed —, and its species are often still waiting for theirs: a
   tile wall would show none of them, the rows show all. */
const listing = () => word !== '' || filter.kind !== 'all';

/** What the current view holds: the tiles' finds, or every find while the rows stand. */
const inView = (f) => listing() || onWall(f);

/** Aufsammlungen (0174): the sample itself, or a species grown from one. */
const isCultured = (f) => !f.is_collection && f.collection_id != null;

function passesKind(f, kind = filter.kind) {
    if (kind === 'all') return true;
    if (kind === 'collections') return f.is_collection;
    return isCultured(f);
}

/** The number under the name — not a second time where the name already is it („Aufsammlung A-2026-01"). */
const numberBesideName = (f) => (f.number && f.title.includes(f.number) ? '' : f.number);

/* Aufsammlung id → the species grown from it that this page knows, in the
   order they were determined (undated last) — the culture's own chronology.
   Made at the load; adding a species reloads the page (collectionui.js). */
const grownBy = new Map();
const grownOf = (id) => grownBy.get(id) ?? [];

function collectGrown() {
    grownBy.clear();
    for (const f of finds) {
        if (!isCultured(f)) continue;
        if (!grownBy.has(f.collection_id)) grownBy.set(f.collection_id, []);
        grownBy.get(f.collection_id).push(f);
    }
    for (const species of grownBy.values()) {
        species.sort((a, b) => (a.identified_at ?? '9999').localeCompare(b.identified_at ?? '9999') || a.id - b.id);
    }
}

/** Whole days from the collecting day to the determination, or null. */
function daysToIdentified(f) {
    if (!f.found_at || !f.identified_at) return null;
    const from = Date.parse(f.found_at.slice(0, 10) + 'T00:00:00Z');
    const to = Date.parse(f.identified_at + 'T00:00:00Z');
    return Number.isFinite(from) && Number.isFinite(to) ? Math.round((to - from) / 86400000) : null;
}

/** What a find says of its culture, in the tile's and the row's quiet line. */
function cultureWord(f) {
    if (isCultured(f)) {
        const days = daysToIdentified(f);
        return days === null ? t('aus Kultur') : t('aus Kultur, nach %d Tagen bestimmt', days);
    }
    if (!f.is_collection) return '';
    const state = f.culture === 'ended' ? t('Kultur beendet')
        : f.culture === 'running' ? t('in Kultur') : t('noch nicht in Kultur');
    const n = grownOf(f.id).length;
    return n === 0 ? state : n === 1 ? t('%s · 1 Art', state) : t('%s · %d Arten', state, n);
}

function passesWho(f, who = filter.who) {
    if (who === 'all') return true;
    if (who === 'own') return f.own;
    if (who === 'others') return !f.own;
    return !f.own && 'u' + f.owner_id === who;
}

function passesYear(f, year = filter.year) {
    if (year === 'all') return true;
    if (year === 'none') return f.year === null;
    return String(f.year) === year;
}

function passesWord(f) {
    return word === '' || word.split(/\s+/).every((w) => f.hay.includes(w));
}

/** What is shown now, in the order it is shown. */
const shown = () => finds.filter((f) => inView(f) && passesWho(f) && passesYear(f) && passesKind(f)
    // An Aufsammlung is found by the name of a species grown from it as well.
    && (passesWord(f) || (f.is_collection && grownOf(f.id).some(passesWord))));

/** „Funde | Bildarchiv" on top of the side — only where there is an archive to see. */
function modeHtml() {
    if (archiveCount === 0) return '';
    const tab = (value, label) => `<button type="button" role="tab" data-mode="${value}"
        aria-selected="${filter.mode === value}"${filter.mode === value ? '' : ' tabindex="-1"'}>${esc(label)}</button>`;
    return `<div class="detail-tabs detail-subtabs finds-mode" role="tablist" aria-label="${esc(t('Funde oder Bildarchiv'))}">`
        + tab('finds', t('Funde')) + tab('archive', t('Bildarchiv')) + '</div>';
}

function drawSide() {
    if (inArchive()) {
        side.innerHTML = modeHtml() + (archive.ready ? archive.sideHtml(word) : '');
        return;
    }
    /* THE ROWS COME FROM WHAT THE CHOSEN CULTURE HOLDS
       (18.10.2026: with „Aufsammlungen" chosen, years with none of them
       stood there at 0). A year or a person outside the chosen kind is not
       an empty choice, it is no choice — while a typed word only greys a
       row out, so the list does not jump under the typing. */
    const pool = finds.filter((f) => inView(f) && passesKind(f));
    const byWho = pool.filter((f) => passesYear(f) && passesKind(f) && passesWord(f));
    const byYear = pool.filter((f) => passesWho(f) && passesKind(f) && passesWord(f));
    /* The kinds are counted over EVERY find, pictures or not — choosing one
       turns the rows on, so the number has to say what they will hold. */
    const byKind = finds.filter((f) => passesWho(f) && passesYear(f) && passesWord(f));

    // The others by name — whoever shared most first, then alphabetically.
    const people = new Map();
    for (const f of pool) {
        if (f.own) continue;
        const key = 'u' + f.owner_id;
        if (!people.has(key)) people.set(key, { name: f.owner || t('Unbekannt'), n: 0 });
    }
    // The chosen person stays in sight even where the culture holds none of his — or the choice would be invisible.
    if (filter.who.startsWith('u') && !people.has(filter.who)) {
        const f = finds.find((x) => 'u' + x.owner_id === filter.who);
        if (f) people.set(filter.who, { name: f.owner || t('Unbekannt'), n: 0 });
    }
    for (const f of byWho) if (!f.own) people.get('u' + f.owner_id).n++;
    const sortedPeople = [...people.entries()].sort((a, b) => b[1].n - a[1].n || a[1].name.localeCompare(b[1].name, 'de'));

    const years = new Map();
    let undated = 0;
    let anyUndated = false;
    for (const f of pool) {
        if (f.year === null) anyUndated = true;
        else if (!years.has(f.year)) years.set(f.year, 0);
    }
    // The chosen year, likewise.
    if (filter.year === 'none') anyUndated = true;
    else if (filter.year !== 'all' && !years.has(Number(filter.year))) years.set(Number(filter.year), 0);
    for (const f of byYear) {
        if (f.year === null) undated++;
        else years.set(f.year, years.get(f.year) + 1);
    }

    const row = (group, value, label, n, depth = 0) =>
        `<button type="button" class="finds-row${filter[group] === value ? ' is-active' : ''}${n === 0 ? ' is-empty' : ''}"
                 data-group="${group}" data-value="${esc(value)}" style="--depth:${depth}"
                 aria-pressed="${filter[group] === value}">
            <span class="finds-row-name">${esc(label)}</span><span class="finds-row-n">${n}</span>
        </button>`;

    let html = modeHtml() + `<div class="list-label">${esc(t('Funde'))}</div><div class="finds-rows">`;
    html += row('who', 'all', t('Alle'), byWho.length);
    html += row('who', 'own', t('Eigene'), byWho.filter((f) => f.own).length);
    if (people.size) {
        html += row('who', 'others', t('Von anderen'), byWho.filter((f) => !f.own).length);
        for (const [key, p] of sortedPeople) html += row('who', key, p.name, p.n, 1);
    }
    html += `</div><div class="list-label">${esc(t('Jahre'))}</div><div class="finds-rows">`;
    html += row('year', 'all', t('Alle Jahre'), byYear.length);
    for (const y of [...years.keys()].sort((a, b) => b - a)) html += row('year', String(y), String(y), years.get(y));
    if (anyUndated) html += row('year', 'none', t('Ohne Datum'), undated);
    html += '</div>';
    // Only for whoever has cultured something — a head over nothing names nothing.
    if (finds.some((f) => f.is_collection || isCultured(f))) {
        /* NO „ALLE FUNDE" HERE (18.10.2026: „schwer zu
           verstehen … bei Alle Funde wird mir dann die Gesamtzahl angezeigt.
           Bei mir jetzt 72, Aufsammlungen 1"). The group's own „Alle" read
           as part of the culture and repeated the total of „Funde" above.
           Its two rows switch instead: a click chooses one, a second click
           on the chosen one lets go and the tiles come back. */
        html += `<div class="list-label">${esc(t('Kultur'))}</div><div class="finds-rows">`;
        html += row('kind', 'collections', t('Aufsammlungen'), byKind.filter((f) => passesKind(f, 'collections')).length);
        html += row('kind', 'cultured', t('Aus Kultur gewachsen'), byKind.filter((f) => passesKind(f, 'cultured')).length);
        html += '</div>';
    }
    side.innerHTML = html;
}

side.addEventListener('click', (e) => {
    const mode = e.target.closest('[data-mode]');
    if (mode) {
        filter.mode = mode.dataset.mode;
        settings.set('findsFilter', { ...filter });
        show();
        return;
    }
    const button = e.target.closest('.finds-row');
    if (!button) return;
    const { group, value } = button.dataset;
    if (group.startsWith('archive-')) {
        archive.choose(group, value);
        show();
        return;
    }
    // A culture's row lets go on its second click — the group has no „Alle" (drawSide).
    filter[group] = group === 'kind' && filter.kind === value ? 'all' : value;
    settings.set('findsFilter', { ...filter });
    show();
});

search.addEventListener('input', () => {
    word = search.value.trim().toLowerCase();
    show();
});
document.getElementById('finds-search-form').addEventListener('submit', (e) => e.preventDefault());
search.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && search.value !== '') {
        search.value = '';
        word = '';
        show();
        e.preventDefault();
    } else if (e.key === 'ArrowDown' && inArchive()) {
        archiveBox.querySelector('[data-archive-at]:not([hidden])')?.focus();
        e.preventDefault();
    } else if (e.key === 'ArrowDown') {
        // From the field into what it found.
        const first = (listing() ? resultRows : grid).querySelector('[data-id]:not([hidden])');
        if (first) {
            first.focus();
            choose(Number(first.dataset.id));
            e.preventDefault();
        }
    }
});

// --- Tiles and rows -----------------------------------------------------------

/* NO PENCIL ANY MORE (15.11.2026: „ja, nimm den Stift weg") —
   it opened the find, and since one click on the tile or row does that, it
   only repeated the click.
   THE CROSSHAIR ON A TILE (15.10.2026: „ein Zielkreuz, dass man
   von dort aus direkt zur Karte kommt und die Position angezeigt kriegt"):
   the map at the find — map.js opens ?point= there,
   flies to it and shows its name tag. */
const toMap = () => `<button type="button" class="symbol-button small finds-to-map" title="${esc(t('Auf der Karte zeigen'))}"
    aria-label="${esc(t('Auf der Karte zeigen'))}">${iconHtml('crosshair')}</button>`;

/* The parts below are made first and put in. Written so when
   tests/module.mjs still read a backtick string flat and lost every function
   after a template inside a template; since 15.10.2026 it reads them
   nested, so this is a matter of taste now, no longer a trap. */
const quiet = (text) => '<span class="finds-quiet">' + esc(text) + '</span>';

function tileHtml(f) {
    const cover = f.pictures[0];
    const meta = [numberBesideName(f), dayOf(f), cultureWord(f)].filter(Boolean).join(' · ');
    const count = f.pictures.length > 1
        ? '<span class="finds-tile-n">' + iconHtml('photo') + f.pictures.length + '</span>' : '';
    const name = f.title ? esc(f.title) : quiet(t('ohne Namen'));
    const owner = f.own ? ''
        : '<span class="finds-tile-owner">' + esc(t('von %s', personName(f.owner) || t('Unbekannt'))) + '</span>';
    return `<span class="finds-tile-image">
            <img src="${esc(cover.thumb)}" alt="" loading="lazy" decoding="async">
            <span class="finds-tile-missing">${iconHtml('photo')}</span>
            ${count}
        </span>
        <span class="finds-tile-name">${name}</span>
        <span class="finds-tile-meta">${esc(meta)}</span>
        ${owner}
        ${toMap()}`;
}

/** A tile's or row's cover as the picture is edited („Bearbeiten") — and marked, so an edit later reaches it. */
function coverThumb(el, f) {
    const cover = f.pictures[0];
    const img = el.querySelector('.finds-tile-image img, .finds-result-thumb img');
    // The key spelled out, not keyOf(): that one is declared further down, and tiles may be built first.
    if (cover && img) developThumb(img, cover.thumb, cover.develop, `${cover.kind === 'cloud' ? 'cloud' : 'photo'}:${cover.id}`);
}

function rowHtml(f) {
    const cover = f.pictures[0];
    const thumb = cover
        ? '<img src="' + esc(cover.thumb) + '" alt="" loading="lazy" decoding="async">'
        : '<span class="finds-quiet">' + iconHtml('photo') + '</span>';
    const name = f.title ? esc(f.title) : quiet(t('ohne Namen'));
    return `<span class="finds-result-thumb">${thumb}</span>
        <span class="finds-result-name"><span class="finds-result-title">${name}</span>
            ${quiet([numberBesideName(f), cultureWord(f)].filter(Boolean).join(' · '))}</span>
        <span>${esc(dayOf(f))}</span>
        <span>${esc(f.place)}</span>
        <span>${esc(f.tour)}</span>
        <span>${f.own ? '' : esc(f.owner || t('Unbekannt'))}</span>`;
}

function buildTiles() {
    const fragment = document.createDocumentFragment();
    for (const f of finds) {
        if (!onWall(f)) continue;
        const tile = document.createElement('div');
        tile.className = 'finds-tile' + (f.own ? '' : ' is-foreign');
        tile.setAttribute('role', 'button');
        tile.tabIndex = 0;
        tile.dataset.id = String(f.id);
        tile.innerHTML = tileHtml(f);
        coverThumb(tile, f);
        tiles.set(f.id, tile);
        fragment.append(tile);
    }
    grid.append(fragment);
}

/* THE ROWS ARE MADE AT THE FIRST SEARCH, not at the load: most visits only
   browse, and thousands of rows nobody looks at are thousands of elements. */
function buildRows() {
    if (rowsBuilt) return;
    rowsBuilt = true;
    const fragment = document.createDocumentFragment();
    for (const f of finds) {
        const r = document.createElement('div');
        r.className = 'finds-result' + (f.own ? '' : ' is-foreign');
        r.setAttribute('role', 'button');
        r.tabIndex = 0;
        r.dataset.id = String(f.id);
        r.innerHTML = rowHtml(f);
        coverThumb(r, f);
        rows.set(f.id, r);
        fragment.append(r);
    }
    resultRows.append(fragment);
}

/* A PICTURE THAT DOES NOT COME — a cloud disconnected, a raw file the
   provider makes no preview of — leaves the tile standing with a quiet
   sign instead of the browser's broken-image glyph. Errors do not bubble,
   hence the capture. */
grid.addEventListener('error', (e) => {
    if (e.target.tagName === 'IMG') e.target.closest('.finds-tile-image')?.classList.add('is-broken');
}, true);
resultRows.addEventListener('error', (e) => {
    if (e.target.tagName === 'IMG') e.target.hidden = true;
}, true);

/* THE OVERVIEW OF THE AUFSAMMLUNGEN (15.10.2026: „läuft noch,
   abgeschlossen … die und die Arten"). Not a page of its own: the kind
   „Aufsammlungen" turns the rows into it. They stand in groups by the
   culture's state — running first, it is what one works on —, and under
   each Aufsammlung the species grown from it, indented, in the order they
   were determined. The rows are the search's own rows MOVED into the
   groups, not copies, so choosing, walking and the pencil work unchanged;
   leaving the overview puts every row back in the page's order. */
let groups = [];
let arranged = false;

function arrange(list) {
    for (const g of groups) {
        for (const r of g.querySelectorAll('.finds-result')) {
            r.classList.remove('is-grown');
            resultRows.append(r);
        }
        g.remove();
    }
    groups = [];
    if (list === null) {
        if (arranged) resultRows.append(...finds.map((f) => rows.get(f.id)));
        arranged = false;
        return;
    }
    arranged = true;
    const states = [
        ['running', t('In Kultur')],
        ['waiting', t('Noch nicht in Kultur')],
        ['ended', t('Kultur beendet')],
    ].filter(([state]) => list.some((f) => f.culture === state));
    const fragment = document.createDocumentFragment();
    for (const [state, label] of states) {
        const g = document.createElement('div');
        g.className = 'finds-results-group';
        // A head only over more than one group — over a single one it names it twice.
        if (states.length > 1) g.innerHTML = `<div class="list-label">${esc(label)}</div>`;
        for (const f of list) {
            if (f.culture !== state) continue;
            g.append(rows.get(f.id));
            for (const s of grownOf(f.id)) {
                const r = rows.get(s.id);
                r.classList.add('is-grown');
                g.append(r);
            }
        }
        groups.push(g);
        fragment.append(g);
    }
    resultRows.prepend(fragment);
}

/* THE BILDARCHIV IN VIEW: its tiles instead of the finds' — tiles also
   while a word is typed, there are no rows for pictures without a find.
   Fetched the first time; until it is there the side shows the switch. */
function showArchive() {
    grid.hidden = true;
    results.hidden = true;
    archiveBox.hidden = false;
    sizeLabel.hidden = false;
    if (!archive.ready) {
        count.textContent = '';
        archiveEmpty.hidden = false;
        archiveEmpty.textContent = t('Bildarchiv wird geladen …');
        archive.load().then(show, (e) => { archiveEmpty.textContent = t('Das Bildarchiv ließ sich nicht laden: %s', e.message); });
        drawSide();
        return;
    }
    const { n, pictures } = archive.show(word);
    count.textContent = n === 1 ? t('1 Fund · %d Bilder', pictures) : t('%d Funde · %d Bilder', n, pictures);
    archiveEmpty.hidden = n > 0;
    if (n === 0) {
        archiveEmpty.textContent = word !== ''
            ? t('Kein Fund passt zu „%s“.', search.value.trim())
            : t('Kein Fund passt zu dieser Auswahl.');
    }
    drawSide();
}

function show() {
    if (inArchive()) {
        showArchive();
        return;
    }
    archiveBox.hidden = true;
    const list = shown();
    const searching = listing();
    grid.hidden = searching;
    results.hidden = !searching;
    sizeLabel.hidden = searching;
    const keep = new Set(list.map((f) => f.id));

    const overview = searching && filter.kind === 'collections';
    if (searching) {
        buildRows();
        let species = 0;
        if (overview) {
            for (const f of list) for (const s of grownOf(f.id)) { keep.add(s.id); species++; }
        }
        arrange(overview ? list : null);
        for (const [id, r] of rows) r.hidden = !keep.has(id);
        count.textContent = overview
            ? t('%d Aufsammlungen · %d Arten', list.length, species)
            : list.length === 1 ? t('1 Treffer') : t('%d Treffer', list.length);
        resultsEmpty.hidden = list.length > 0;
        if (list.length === 0) {
            resultsEmpty.textContent = word !== ''
                ? t('Kein Fund passt zu „%s“.', search.value.trim())
                : t('Kein Fund passt zu dieser Auswahl.');
        }
        empty.hidden = true;
    } else {
        if (rowsBuilt) arrange(null);
        let pictures = 0;
        for (const [id, tile] of tiles) {
            tile.hidden = !keep.has(id);
            if (keep.has(id)) pictures += byId.get(id).pictures.length;
        }
        count.textContent = list.length === 1
            ? t('1 Fund · %d Bilder', pictures)
            : t('%d Funde · %d Bilder', list.length, pictures);
        empty.hidden = list.length > 0;
        if (list.length === 0) {
            empty.textContent = finds.length === 0
                ? t('Noch keine Funde.')
                : !finds.some(onWall)
                    ? t('Noch kein Fund mit Bildern. Such oben nach einem Namen, dann siehst du alle Funde.')
                    : t('Kein Fund passt zu dieser Auswahl.');
        }
    }
    drawSide();
}

// --- Walking and opening ------------------------------------------------------

/** Mark a find in the tiles and the rows (whichever are drawn). */
function choose(id) {
    if (selected !== null) {
        tiles.get(selected)?.classList.remove('is-active');
        rows.get(selected)?.classList.remove('is-active');
    }
    selected = id;
    tiles.get(id)?.classList.add('is-active');
    rows.get(id)?.classList.add('is-active');
}

function onClick(e) {
    const item = e.target.closest('[data-id]');
    if (!item) return;
    const id = Number(item.dataset.id);
    if (e.target.closest('.finds-to-map')) location.href = MAP + '?point=' + id;
    /* ONE CLICK OPENS (15.11.2026: „Im Fund-Browser muss man
       momentan ja doppelt klicken, um den Fund zu öffnen. Das ist natürlich
       was bei Touchgeräten stört"). Until then a click only marked the tile,
       Lightroom's way, and a second one opened — a finger has no double
       click worth the name. Marking stays with the arrow keys (onKey). */
    else open(id);
}

/* THE ARROWS WALK, as in Lightroom's grid: in the tiles left and right one
   tile, up and down one row — the row read off the tiles' positions, since
   the number per row follows the window and the size slider —; in the
   list up and down one row. Enter opens the find. */
function onKey(e) {
    const item = e.target.closest('[data-id]');
    if (!item || e.target.closest('.finds-to-map') || e.altKey || e.ctrlKey || e.metaKey) return;
    const id = Number(item.dataset.id);
    if (e.key === 'Enter') {
        open(id);
        e.preventDefault();
        return;
    }
    const tilesNow = item.classList.contains('finds-tile');
    // From the list's root, not the row's parent: in the overview the rows stand in groups.
    const visible = [...(tilesNow ? grid : resultRows).querySelectorAll('[data-id]:not([hidden])')];
    const at = visible.indexOf(item);
    let next = null;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        const down = e.key === 'ArrowDown';
        if (tilesNow) {
            const x = item.offsetLeft;
            const further = visible.filter((v) => (down ? v.offsetTop > item.offsetTop : v.offsetTop < item.offsetTop));
            if (further.length) {
                const rowTop = down ? Math.min(...further.map((v) => v.offsetTop)) : Math.max(...further.map((v) => v.offsetTop));
                next = further.filter((v) => v.offsetTop === rowTop)
                    .sort((a, b) => Math.abs(a.offsetLeft - x) - Math.abs(b.offsetLeft - x))[0];
            }
        } else {
            next = visible[at + (down ? 1 : -1)];
        }
    } else if (tilesNow && e.key === 'ArrowRight') next = visible[at + 1];
    else if (tilesNow && e.key === 'ArrowLeft') next = visible[at - 1];
    else return;
    e.preventDefault();
    if (next) {
        next.focus();
        next.scrollIntoView({ block: 'nearest' });
        choose(Number(next.dataset.id));
    }
}

for (const list of [grid, resultRows]) {
    list.addEventListener('click', onClick);
    list.addEventListener('keydown', onKey);
}

// --- The find opened ----------------------------------------------------------

function open(id) {
    if (opened === id || !byId.has(id)) return;
    scrolled = { grid: grid.scrollTop, results: results.scrollTop };
    history.pushState({ fund: id }, '', '#fund=' + id);
    route();
}

/** Whether one may leave the find in the window: nothing typed, or the window's own question answered. */
async function mayLeave() {
    // The plate in the tab saves itself on closing, and asks only when that fails.
    if (tools && !(await tools.plates.close())) return false;
    /* The window saves itself too (cockpit.js, autosave): what waits for its
       second goes now, and only a save that fails asks — Speichern or
       Verwerfen, the window's own question. */
    if (!cockpit || !cockpit.dirty() || await cockpit.flush()) return true;
    return cockpit.leaveOk();
}

async function close() {
    /* ON THE WAY OUT nothing opens again: closing the plate fires its close
       event, and platesIdle would otherwise put the first plate back up in
       the moment before the tiles come. */
    leaving = true;
    if (!(await mayLeave())) {
        leaving = false;
        return;
    }
    closeNow();
}

function closeNow() {
    /* THE QUESTION IS ANSWERED: whoever chose „Verwerfen" left the typing in
       the fields, and history.back() below arrives at the popstate guard,
       which would find them still dirty and ask a second time. The window
       closes first — a closed window holds nothing unsaved. */
    cockpit?.close();
    /* Opened by the map's pencil: back to the map. Through the history when
       the map is the page before — it comes back as it was left, at its
       place and zoom —, by its address when the find was opened anew (a
       link in a new tab has no page before). */
    if (history.state?.fromMap) {
        const ref = document.referrer ? new URL(document.referrer) : null;
        const fromMap = ref?.origin === location.origin && !ref.pathname.includes('/pages/');
        if (fromMap && history.length > 1) history.back();
        else location.href = START.baseUrl;
    } else if (history.state?.fund) history.back();
    else {
        history.replaceState(null, '', location.pathname + location.search);
        route();
    }
}

/* THE BROWSER'S OWN WAYS OUT ask too: the back button and a reload or a
   closed tab. The first cannot be stopped once the address has changed —
   so the address is put back and the question asked; the second gets the
   browser's own sentence. */
window.addEventListener('beforeunload', (e) => {
    if (cockpit?.dirty()) e.preventDefault();
});

/** What the address says: a find open, or the tiles. */
function route() {
    const hit = /^#fund=(\d+)((?:&\w+=\w+)*)$/.exec(location.hash);
    const id = hit ? Number(hit[1]) : null;
    const extra = new URLSearchParams(hit?.[2] ?? '');
    if (id !== null && byId.has(id)) {
        drawFund(byId.get(id));
        /* A TAB NAMED IN THE ADDRESS: the map sends a point just set to its
           texts (#fund=<id>&tab=text, map.js findsAddress). Said once — the
           address forgets it, so going back and forth opens the find the way
           every find opens (drawFund).

           THE ARROW LEADS BACK WHERE ONE CAME FROM (07.11.2026:
           „der Zurück-Button muss einen da hin zurückführen, wo man
           herkommt"). The map's pencil says from=map; it is kept in the
           history entry rather than the address, so a reload still knows
           it, and a find opened from here afterwards — a new entry — goes
           back to this one as before. */
        if (extra.has('tab')) showTab(extra.get('tab'));
        if (extra.size > 0) {
            const state = extra.get('from') === 'map' ? { ...history.state, fromMap: true } : history.state;
            history.replaceState(state, '', '#fund=' + id);
        }
        const toMap = history.state?.fromMap === true;
        back.title = toMap ? t('Zurück zur Karte') : t('Zurück');
        back.setAttribute('aria-label', back.title);
    } else {
        showBrowse();
    }
}

function showBrowse() {
    keepPicture();
    const left = current;
    opened = null;
    current = null;
    detail = null;
    detailLoad = null;
    cockpit?.close();
    tools?.plates.show(0);
    if (photoWindowEl.open) photoWindowEl.close();
    tools?.plates.close();
    lastKey = null;
    fund.hidden = true;
    browse.hidden = false;
    // The find just left: its tile as the find stands now — or the whole list, where it came in meanwhile (listReload).
    if (listStale) listReload();
    else if (left) findRedraw(left);
    grid.scrollTop = scrolled.grid;
    results.scrollTop = scrolled.results;
    if (selected !== null) {
        const el = (listing() ? rows : tiles).get(selected);
        if (el && !el.hidden) {
            el.focus({ preventScroll: true });
            el.scrollIntoView({ block: 'nearest' });
        }
    }
}

function drawFund(f) {
    keepPicture();
    leaving = false;
    opened = f.id;
    current = f;
    choose(f.id);
    browse.hidden = true;
    fund.hidden = false;
    /* The rail keeps its place per find (scrollkeep.js): back from the map,
       after a reload, out of another tab — the pictures stand where one
       left them. Another find is other pictures and starts at the top. */
    scrollRemember(document.querySelector('#finds-pictures .finds-rail'), 'finds:rail:' + f.id);

    fundTitle.textContent = nameOf(f);
    fundTitle.classList.toggle('finds-quiet', !f.title);
    fundWhere.textContent = [f.number, dayOf(f), f.own ? null : t('von %s', personName(f.owner) || t('Unbekannt'))]
        .filter(Boolean).join(' · ');
    // The previous find's facts go; the window draws this one's into the table when it fills.
    factsTable.textContent = '';
    drawFacts();

    cockpit?.close();   // the previous find's texts go; this one's come with its record
    // The previous find's picture goes with it; this one's comes with its records.
    if (photoWindowEl.open) photoWindowEl.close();
    tools?.plates.close();
    /* THE PICTURE LOOKED AT LAST, as it was looked at (keptPicture): it comes
       back when „Bilder" is opened, at its zoom and place. */
    const keptPic = keptPicture(f.id);
    lastKey = keptPic?.pic ?? null;
    views = new Map(keptPic?.view ? [[keptPic.pic, keptPic.view]] : []);
    /* THE RAIL'S GROUP COMES BACK TOO (07.11.2026: „Seite neu
       geladen und war bei Sporen … die Auswahl wird nicht
       wiederhergestellt"). A picture shown always pulls the menu to its
       own group, so a kept group that differs from the kept picture's was
       chosen AFTER it — the later act wins, and the group's first picture
       is shown instead (viewerIdle). */
    railGroup = keptPic?.group ?? null;
    if (railGroup !== null && lastKey !== null) {
        const pic = f.pictures.find((p) => keyOf(p) === lastKey);
        if (pic && groupOf(pic) !== railGroup) lastKey = null;
    }
    face.textContent = '';

    drawPictures(f);
    /* EVERY FIND OPENS ON „TEXT" (12.10.2026, the third answer of
       one evening: first „lass die Bilder gleich öffnen", then the texts for a
       find without pictures, then „generell in den Text-Tab. Egal von wo und
       egal ob Bilder oder nicht"). The face beside the texts shows the plate
       or the Hauptmotiv anyway, and one click on it leads to „Bilder".
       EVERY FIND KEEPS ITS OWN TAB (15.10.2026: „jedes Mal, wenn
       ich aktualisiere, falle ich zurück auf den Text-Tab … für jeden
       Fundpunkt individuell"): the last tab of each find is remembered on
       the device, so a reload — or coming back from the map or the
       identification — stands where one was in that find, and a find never
       opened before opens on „Text". A tab that is not there yet (Dateien
       waits for the cloud's answer) is taken up the moment it appears
       (syncTabs). */
    const kept = (settings.get('findsTabs', null) ?? {})['f' + f.id];
    wantedTab = kept && Date.now() - kept.at < TABS_KEEP_MS ? kept.tab : null;
    showTab(wantedTab ?? 'text');
    fund.scrollTop = 0;
    loadDetail(f);
}

/** The hint for a find without pictures and the number on „Bilder" — drawn again when the records change (the gallery draws itself). */
function drawPictures(f) {
    noPictures.hidden = f.pictures.length > 0;
    pictureCount.textContent = String(f.pictures.length);
    pictureCount.hidden = f.pictures.length === 0;
    syncPlateTab();
}

/* „TAFELN" STANDS ALWAYS (12.10.2026, after „ab zwei Bildern"
   the same evening: „zeig mir den Tafeltab immer an, denn man kann ja auch
   auf einem einzelnen Foto seine Fundangaben einblenden wollen"). Its
   number says how many plates there are.

   NO ROW OF TILES OVER THE EDITOR (13.10.2026: „bei den Tafeln
   bitte keine Vorschauleiste oben drüber. Da brauchen wir wirklich jeden
   Platz, den wir haben"). The plates' row stays in the page unseen —
   plates.js draws it, and whether it holds „Neue Tafel erstellen" says
   whether one may make a plate. Where there is more than one to choose —
   one's own, the co-owners', the offer of a new one — the choice stands as
   small buttons in the editor's head beside its title, as the pages do;
   with no plate at all the editor's place says so and offers the new one.
   Asked again when the pictures change and whenever the row is drawn. */
const plateSwitch = document.createElement('div');
plateSwitch.className = 'plate-pages finds-plate-switch';
plateSwitch.setAttribute('role', 'group');
plateSwitch.setAttribute('aria-label', t('Tafeln'));
document.getElementById('plate-window-title').after(plateSwitch);
const plateMake = document.getElementById('finds-plate-make');
plateMake.addEventListener('click', () => plateRow.querySelector('.plate-make')?.click());

function syncPlateTab() {
    const f = current;
    const plates = f && tools ? tools.plates.list() : [];
    plateCount.textContent = String(plates.length);
    plateCount.hidden = plates.length === 0;
    const offer = plateRow.querySelector('.plate-make');
    plateMake.hidden = !offer;
    plateEmpty.textContent = f && f.pictures.length === 0 && plates.length === 0
        ? t('Für eine Tafel braucht der Fund zuerst ein Bild. Lade es im Reiter Bilder hoch.')
        : plates.length === 0 ? t('Zu diesem Fund gibt es noch keine Tafel.') : '';

    plateSwitch.textContent = '';
    const open = tools?.plates.current() ?? null;
    const entry = (label, active, act) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'button subtle small' + (active ? ' is-active' : '');
        b.setAttribute('aria-pressed', String(active));
        b.textContent = label;
        b.addEventListener('click', act);
        plateSwitch.append(b);
    };
    for (const p of plates) {
        entry(p.mine ? t('Meine Tafel') : t('Tafel von %s', personName(p.maker)), open !== null && open.id === p.id,
            () => tools.plates.openPlate(p));
    }
    if (offer) entry(t('Neue Tafel'), open !== null && open.id === null, () => offer.click());
    plateSwitch.hidden = plateSwitch.children.length < 2;
}
new MutationObserver(() => syncPlateTab()).observe(plateRow, { childList: true });

// --- The tabs -----------------------------------------------------------------

/**
 * One tab, the others away — data-tab on the section says which, and
 * app.css shows the window's panel or sub-panel that belongs to it. Like the
 * window's own bar NOT remembered: every find starts on „Text" (drawFund).
 */
/* The finds' tabs, per find, FOR A FEW WEEKS (15.10.2026: „mit
   einem Verfallsdatum … nach ein paar Wochen kann das dann gelöscht
   werden"): each with the day it was chosen; what is older than four weeks
   counts as never chosen and goes at the next writing, and never more than
   the last hundred finds stand in the device's bundle. „Text" is not kept —
   it is what a find opens on anyway. A copy is written, never the stored
   object (settings.js says why); the keys carry a letter, because numeric
   keys are ordered by number, not by when they came. */
const TABS_KEEP_MS = 28 * 86400000;
const TABS_KEPT = 100;
function rememberTab(id, name) {
    const now = Date.now();
    const all = {};
    for (const [key, entry] of Object.entries(settings.get('findsTabs', null) ?? {})) {
        if (entry && typeof entry === 'object' && now - entry.at < TABS_KEEP_MS) all[key] = entry;
    }
    delete all['f' + id];
    if (name !== 'text') all['f' + id] = { tab: name, at: now };
    const keys = Object.keys(all);
    for (const old of keys.slice(0, Math.max(0, keys.length - TABS_KEPT))) delete all[old];
    settings.set('findsTabs', all);
}

/* THE PICTURE TOO, AND HOW IT WAS LOOKED AT (03.11.2026:
   „Was allerdings nicht geöffnet wird, ist das zuletzt geöffnete Bild dort.
   Das bitte auch speichern und wiederherstellen. So wie die Zoom-Stufe … Und
   die Position"). Coming back from the key, a reload, another find and back:
   „Bilder" shows the picture it showed, magnified and moved as it was. Kept
   like the tabs — per find, four weeks, the last hundred finds — and taken
   when the find is left (another find, the overview, the page going), so
   the zoom is not written on every turn of the wheel.
   WHILE THE FIND STANDS, EVERY PICTURE KEEPS ITS OWN VIEW („wenn sich der
   Bild-Tab die letzte Position merken würde"): a tab away and back, a
   plate that had the viewer over it, another picture and back — each
   comes at the zoom and place one left it at. In memory only (views),
   filled by the viewer's own moves (zoomMoved), never by its resets; the
   device keeps the one shown last. */
function keepPicture() {
    if (!current || (!lastKey && railGroup === null)) return;
    const v = views.get(lastKey) ?? null;
    const now = Date.now();
    const all = {};
    for (const [k, entry] of Object.entries(settings.get('findsPictures', null) ?? {})) {
        if (entry && typeof entry === 'object' && now - entry.at < TABS_KEEP_MS) all[k] = entry;
    }
    delete all['f' + current.id];
    all['f' + current.id] = { pic: lastKey, group: railGroup, view: v && v.s > 1.001 ? { s: +v.s.toFixed(3), x: +v.x.toFixed(4), y: +v.y.toFixed(4) } : null, at: now };
    const keys = Object.keys(all);
    for (const old of keys.slice(0, Math.max(0, keys.length - TABS_KEPT))) delete all[old];
    settings.set('findsPictures', all);
}
// The page going: written at once — settings.set waits 300 ms, and the navigation wins (settings.js, flush).
addEventListener('pagehide', () => {
    keepPicture();
    settings.flush();
});

function keptPicture(id) {
    const kept = (settings.get('findsPictures', null) ?? {})['f' + id];
    return kept && Date.now() - kept.at < TABS_KEEP_MS ? kept : null;
}

function showTab(name, focus = false) {
    const button = [tabButton(name), tabButton('text')].find((b) => b && !b.hidden);
    tab = button.dataset.tab;
    fund.dataset.tab = tab;
    // Remembered only where the tab asked for stands — a fallback (Dateien not there yet) must not overwrite it.
    if (tab === name) {
        wantedTab = null;
        if (current) rememberTab(current.id, tab);
    }
    for (const b of tabButtons) {
        const on = b === button;
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        b.tabIndex = on ? 0 : -1;
    }
    if (focus) button.focus();
    if (tab === 'text') drawFace();
    if (tab === 'pictures') viewerIdle();
    if (tab === 'plates') platesIdle();
    if (tab === 'place') cockpit?.mapShown();
}

/**
 * „TAFELN" OPENS ON A PLATE: the one open stays and is drawn again with what
 * the viewer may have changed meanwhile (plates.js, refresh); with none
 * open, one's own first, else the first there is.
 *
 * WITHOUT ANY, AN EMPTY ONE STANDS THERE AT ONCE (15.11.2026:
 * „Wenn es noch keine Tafel gibt und ich in den Tafel-Tab gehe, soll mir
 * gleich eine leere Tafel erscheinen, ohne diese Abfrage Tafel erstellen.
 * Allerdings, wenn ich dort nichts reinspeichere, also nichts reinziehe,
 * dann gilt es auch nicht als Tafel und wird mir nicht bei der Übersicht
 * angezeigt"). It is the plate „Neue Tafel erstellen" opened — the same
 * press, made for him —, and that one already comes into being only with
 * the first thing laid on it (plates.js, autosave): left empty it is never
 * sent, so it is in no count, no tile and no overview. Where the offer is
 * not there (no picture yet, the phone, a find of someone else's), the
 * editor's place still says why.
 */
function platesIdle() {
    if (!current || !detail || !tools || leaving || tab !== 'plates' || document.querySelector('dialog:modal')) return;
    if (plateWindowEl.open) {
        tools.plates.refresh();
        return;
    }
    const plates = tools.plates.list();
    const first = plates.find((p) => p.mine) ?? plates[0];
    if (first) tools.plates.openPlate(first);
    else plateRow.querySelector('.plate-make')?.click();
}

for (const b of tabButtons) {
    b.addEventListener('click', () => showTab(b.dataset.tab));
    // The same keyboard as the window's bar: arrows switch, Home and End jump.
    b.addEventListener('keydown', (e) => {
        const order = tabButtons.filter((x) => !x.hidden);
        const here = order.indexOf(b);
        const last = order.length - 1;
        const next = { ArrowRight: (here + 1) % order.length, ArrowLeft: (here + last) % order.length,
                       Home: 0, End: last }[e.key];
        if (next === undefined || here < 0) return;
        e.preventDefault();
        showTab(order[next].dataset.tab, true);
    });
}

/* WHETHER A TAB CAN BE THERE IS THE WINDOW'S ANSWER: pointwindow.js hides
   its sub-tab where a block cannot be — no pictures for this viewer, the
   files only on one's own finds with a cloud (detailSubTabsDraw). The page's
   tab follows its twin, and a tab that goes while it stands hands over. */
const twins = { 'd-subtab-photos': 'pictures', 'd-subtab-sequences': 'sequences', 'd-subtab-files': 'files' };
function syncTabs() {
    for (const [id, name] of Object.entries(twins)) {
        const twin = document.getElementById(id);
        if (twin) tabButton(name).hidden = twin.hidden;
    }
    if (tabButton(tab).hidden) showTab(tab);
    // The find's remembered tab, now that it may have appeared.
    else if (wantedTab && wantedTab !== tab && !tabButton(wantedTab).hidden) showTab(wantedTab);
}
for (const id of Object.keys(twins)) {
    const twin = document.getElementById(id);
    if (twin) new MutationObserver(syncTabs).observe(twin, { attributes: true, attributeFilter: ['hidden'] });
}
syncTabs();

/* THE RAIL'S WIDTH IS DRAGGED AT ITS EDGE, as the plates' strip's
   (13.10.2026: first „genauso wie in der Karte. Mit einem
   Handle", then „mach es so, wie bei den Tafeln" — plates.js, stripEdge):
   seven pixels over the rule, no bar of its own. Dragged narrower than
   RAIL_FOLD the rail folds away, and from its folded edge it is pulled out
   again — the fold is the drag's end, not a second control. Width and fold
   are remembered per device: a long measuring session wants the picture
   wide, a find with forty pictures the rail. */
const picturesPanel = document.getElementById('finds-pictures');
const railEdge = document.getElementById('finds-rail-edge');
const RAIL_MIN = 160;
const RAIL_FOLD = 80;
const railMax = () => Math.min(640, picturesPanel.clientWidth * 0.5);

let railWidth = Number(settings.get('findsRailWidth', 0)) || null;
function railWidthApply(px) {
    railWidth = Math.round(px);
    picturesPanel.style.setProperty('--finds-rail-open', railWidth + 'px');
}
if (railWidth >= RAIL_MIN) railWidthApply(railWidth);
picturesPanel.classList.toggle('is-rail-closed', settings.get('findsRailClosed', false) === true);

/* THE RAIL FOLDS AS THE HEAD DOES (09.11.2026: „Sowas dann bitte
   auch beim Tab Bilder. Dort für die linke Seitenleiste mit den
   Vorschaubildern"): a small sign in the middle of its rule folds it;
   folded, a thin bar at the left stays — the pointer over it lays the rail
   OVER the picture (is-rail-peek, the viewer keeps its room), and it folds
   again once the pointer has left; a click on the bar keeps it open. The
   drag at the edge still sets the width and still folds below RAIL_FOLD. */
const railFoldBtn = document.createElement('button');
railFoldBtn.type = 'button';
railFoldBtn.className = 'finds-rail-fold';
railFoldBtn.title = t('Bildleiste einklappen');
railFoldBtn.setAttribute('aria-label', t('Bildleiste einklappen'));
railFoldBtn.innerHTML = iconHtml('fold-triangle');
const railHandle = document.createElement('button');
railHandle.type = 'button';
railHandle.className = 'finds-rail-handle';
railHandle.title = t('Bildleiste ausklappen');
railHandle.setAttribute('aria-label', t('Bildleiste ausklappen'));
railHandle.innerHTML = iconHtml('fold-triangle');
picturesPanel.append(railFoldBtn, railHandle);
const railEl = picturesPanel.querySelector('.finds-rail');
let railPeekTimer = null;
function railFold(on) {
    picturesPanel.classList.toggle('is-rail-closed', on);
    picturesPanel.classList.remove('is-rail-peek');
    settings.set('findsRailClosed', on);
    window.dispatchEvent(new Event('resize'));
}
function railPeek() {
    clearTimeout(railPeekTimer);
    if (picturesPanel.classList.contains('is-rail-closed')) picturesPanel.classList.add('is-rail-peek');
}
railHandle.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') railPeek(); });
// The band left of the bar, to the window's edge, counts as the bar (edgeZone, above).
document.addEventListener('pointermove', (e) => {
    if (e.pointerType !== 'mouse' || e.buttons || !picturesPanel.classList.contains('is-rail-closed')) return;
    if (edgeZone(railHandle, 'left', e)) railPeek();
});
for (const n of [railHandle, railEl]) {
    n.addEventListener('pointerenter', () => clearTimeout(railPeekTimer));
    n.addEventListener('pointerleave', (e) => {
        if ([railHandle, railEl].some((m) => m.contains(e.relatedTarget))) return;
        clearTimeout(railPeekTimer);
        railPeekTimer = setTimeout(() => picturesPanel.classList.remove('is-rail-peek'), 250);
    });
}
railHandle.addEventListener('click', () => railFold(false));
railFoldBtn.addEventListener('click', () => railFold(true));

{
    let drag = null;   // {x, width}
    railEdge.addEventListener('pointerdown', (e) => {
        e.preventDefault();   // no text selection riding along
        const closed = picturesPanel.classList.contains('is-rail-closed');
        drag = { x: e.clientX, width: closed ? 0 : picturesPanel.querySelector('.finds-rail').offsetWidth };
        picturesPanel.classList.add('is-resizing');
        try { railEdge.setPointerCapture(e.pointerId); } catch { /* without, then */ }
    });
    railEdge.addEventListener('pointermove', (e) => {
        if (drag === null) return;
        const wanted = drag.width + e.clientX - drag.x;
        const fold = wanted < RAIL_FOLD;
        picturesPanel.classList.toggle('is-rail-closed', fold);
        if (!fold) railWidthApply(Math.min(Math.max(wanted, RAIL_MIN), railMax()));
    });
    const railDragEnd = () => {
        if (drag === null) return;
        drag = null;
        picturesPanel.classList.remove('is-resizing');
        const closed = picturesPanel.classList.contains('is-rail-closed');
        settings.set('findsRailClosed', closed);
        if (!closed && railWidth) settings.set('findsRailWidth', railWidth);
    };
    railEdge.addEventListener('pointerup', railDragEnd);
    railEdge.addEventListener('pointercancel', railDragEnd);
}

// --- The viewer in the page ---------------------------------------------------

/** The key the gallery marks a picture's tile with. */
const keyOf = (p) => `${p.kind === 'cloud' ? 'cloud' : 'photo'}:${p.id}`;

/** A group's name as the rail's menu shows it (photogallery.js, galleryLayout). */
const groupName = (name) => name ?? t('Ohne Motivnamen');
/** The group a picture stands in — subjectGroups' rule: its subject name trimmed, none when empty. */
const groupOf = (p) => groupName((p.title ?? '').trim() || null);

/** One picture of the open find into the viewer, by its key; false where the find does not hold it. */
function showKey(key) {
    if (!detail || !tools) return false;
    const id = Number(key.slice(key.indexOf(':') + 1));
    if (key.startsWith('photo:')) {
        const rec = detail.point.photos.find((x) => x.id === id);
        if (rec) tools.viewer.showPhoto(rec);
        return !!rec;
    }
    const rec = tools.viewer.cloudFilesShown.files.find((x) => x.id === id);
    if (rec) tools.viewer.showCloudPhoto(rec);
    return !!rec;
}

/**
 * THE VIEWER NEVER STANDS EMPTY WHILE THERE IS A PICTURE: on „Bilder" with
 * nothing in it — the find just opened, the shown picture deleted, a plate
 * closed that had the viewer over it — the picture shown last comes back,
 * or the first one (the Hauptmotiv, includes/finds.php sorts it there).
 * Not while something modal lies over the page, and not while the
 * pictures are fetched anew: a deleted one would come back for a moment.
 */
function viewerIdle() {
    const f = current;
    if (!f || !detail || !tools || tab !== 'pictures' || refreshing || leaving) return;
    if (photoWindowEl.open || document.querySelector('dialog:modal')) return;
    const keys = f.pictures.map(keyOf);
    /* A PICTURE GONE LEAVES ONE IN ITS GROUP (07.11.2026: in
       „Sporen" a picture deleted, and the page came back on the
       Hauptmotive). The first picture of the whole find is the Hauptmotiv —
       shown, it pulled the rail's menu over to its group (the load handler
       below). The first picture of the group one was in keeps one there. */
    let key = keys.includes(lastKey) ? lastKey : null;
    if (!key && railGroup !== null) {
        /* FROM THE RECORDS, NOT THE TILES: on a fresh drawing the gallery is
           still empty at this moment (it fills after its own await), and the
           tiles' group is only set afterwards. subjectGroups is the order the
           gallery itself draws by, so its first picture of the group is the
           first tile there. */
        const group = subjectGroups(f.pictures).find((g) => groupName(g.name) === railGroup);
        if (group?.items.length) key = keyOf(group.items[0]);
    }
    key ??= keys[0];
    if (key) showKey(key);
}

/* THE RAIL'S MENU OF THE GROUPS, as the plates' strip has it (plates.js,
   stripDraw; 13.10.2026: „maximal eine Kachel nebeneinander.
   Also alle untereinander, aber ab einer bestimmten Breite bekomme ich
   eine Menüspalte. Wo ich schnell wechseln kann"). The gallery draws one
   flat grid — a heading per subject name, its tiles after it —, so each
   tile is told its group here, after every drawing, and the menu is built
   from the headings. Whether one sees the menu and the chosen group alone,
   or every group under its heading, is the stylesheet's to say (@container
   finds-rail): dragging the edge across the threshold rebuilds nothing.
   With fewer than two groups there is nothing to switch between. */
const railMenu = document.createElement('nav');
railMenu.className = 'finds-rail-menu';
railMenu.setAttribute('aria-label', t('Motivnamen'));
/* THE GROUP ONE IS IN, OVER THE WHOLE WIDTH (13.11.2026: „wir
   brauchen in der Sidebar bei den Symbolen dann quasi noch eine
   Sticky-Überschrift, wo wir gerade sind. In voller Sidebar-Breite wie wir
   das bereits bei Bearbeiten haben … gerade bei den Mikrofotos, wenn sie
   etwas verkleinert sind, kann man nicht sehen, ob man gerade bei den
   Sporen oder bei irgendwelchen Askos ist. Wenn alles in Wasser ist und
   nicht eingefärbt"): the chosen group's
   symbol and name as a list head, sticking under the buttons while the
   pictures scroll (app.css). */
const railNow = document.createElement('p');
railNow.className = 'list-label finds-rail-now';
railNow.hidden = true;
gallery.before(railNow, railMenu);
let railGroup = null;
/* THREE WIDTHS (13.11.2026): narrow the one list; from the
   middle on a column of SYMBOLS alone (subjecticons.js), the name under the
   pointer as its title; wide, symbol and name. Every item carries both,
   the stylesheet decides (app.css, @container finds-rail). Pointing at a
   symbol switches to its group, with the guard against grazing
   (hoverpick.js). */
hoverPick(railMenu, (item) => item.dataset.group === railGroup, (item) => railGroupShow(item.dataset.group));

function railGroups() {
    const names = [];
    let name = '';
    for (const el of gallery.children) {
        if (el.classList.contains('photo-group-title')) {
            // The stored name (photogallery.js keeps it on the head); shown international below.
            name = el.dataset.name ?? el.textContent;
            names.push(name);
        }
        el.dataset.group = name;
    }
    railMenu.textContent = '';
    railMenu.hidden = names.length < 2;
    railNow.hidden = railMenu.hidden;
    /* An EMPTY drawing keeps the choice: the gallery is cleared before it is
       filled anew, and taken as „no groups" the choice fell to the first
       group — the Hauptmotive — before the pictures were back. */
    if (names.length > 0 && !names.includes(railGroup)) railGroup = names[0];
    for (const n of names) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'plate-strip-menu-item';
        item.dataset.group = n;
        item.title = vocab(n);
        const word = document.createElement('span');
        word.className = 'plate-strip-menu-word';
        word.textContent = vocab(n);
        item.append(subjectIcon(n, n === t('Ohne Motivnamen') ? 'label' : null), word);
        item.addEventListener('click', () => railGroupShow(n));
        railMenu.append(item);
    }
    railGroupShow(railGroup);
}

function railGroupShow(name) {
    railGroup = name;
    railNow.replaceChildren(...(name === null ? [] : [subjectIcon(name, name === t('Ohne Motivnamen') ? 'label' : null), vocab(name)]));
    for (const el of gallery.children) el.classList.toggle('is-off', name !== null && el.dataset.group !== name);
    for (const item of railMenu.children) item.classList.toggle('is-active', item.dataset.group === name);
}

/* The tile of the picture in the viewer is marked in the rail — set anew
   when a picture has come and whenever the gallery draws its tiles again.
   WORKING ON A SET IN „EBENEN" EVERY PICTURE OF IT IS
   (11.11.2026: „wird mir links in der Thumbnail-Sidebar aber nur ein Bild
   immer markiert … dass alle Bilder, die in diesem Stapel sind, aktiv
   werden"): what lies on the stage is all of them at once. */
function markCurrent() {
    const stack = photoWindowEl.open ? blend.stack() : null;
    const key = photoWindowEl.open ? tools?.viewer.pictureKey() : null;
    for (const tile of gallery.querySelectorAll('[data-sort-key]')) {
        tile.classList.toggle('is-current', stack ? stack.includes(tile.dataset.sortKey) : tile.dataset.sortKey === key);
    }
}
document.getElementById('photo-image').addEventListener('load', () => {
    const key = tools?.viewer.pictureKey();
    if (key) lastKey = key;
    // The picture's own view, where it had one.
    if (key && views.has(key)) tools.viewer.zoomSet(views.get(key));
    markCurrent();
    // A picture of another group (from the plate, say): the menu follows it.
    const group = key ? gallery.querySelector(`[data-sort-key="${key}"]`)?.dataset.group : undefined;
    if (group && group !== railGroup) railGroupShow(group);
});
photoWindowEl.addEventListener('close', () => markCurrent());
new MutationObserver(() => { railGroups(); markCurrent(); }).observe(gallery, { childList: true });
// A plate had the viewer over it (openPicture below): back in the page when the plate goes.
/* A plate closed — deleted, or had the viewer over it on the map's way:
   the viewer comes back into its tab, and „Tafeln" opens the next plate. */
plateWindowEl.addEventListener('close', () => setTimeout(() => { viewerIdle(); platesIdle(); }, 0));

/**
 * „ÜBERSICHT" — the find at one glance (23.10.2026: „den Reiter
 * Text … in sowas wie Übersicht verwandeln. Links die Spalte mit dem Text,
 * die editierbar ist. Und rechts dann die Bilder, ein Zugang zu den
 * Dateien, also nur aufrufend oder downloadend. Und ein Zugang zu den
 * Sequenzen, auch nur downloadend"). The texts stay the point window's and
 * are written there, the key behind „Art" included; this column only shows.
 *
 * THE PICTURES: where the find has plates, ONLY its plates — the plate is
 * the find's pictures already laid out, the single ones would say it twice.
 * Without a plate EVERY picture, the Hauptmotive first (f.pictures holds
 * that order, picturesFrom). Each leads where it lives: a plate into
 * „Tafeln", a picture into „Bilder", shown there at once.
 *
 * FILES AND SEQUENCES only to open and to fetch; adding, renaming and
 * deleting stay in their tabs. The files are the list the „Dateien" tab
 * drew last (pointfiles.js, changed), the sequences the find's own.
 */
let faceFiles = { id: 0, files: [] };

function faceHead(text) {
    const head = document.createElement('div');
    head.className = 'list-label';
    head.textContent = text;
    return head;
}

function faceButton(iconName, label, act) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'symbol-button small';
    b.title = label;
    b.setAttribute('aria-label', label);
    b.innerHTML = iconHtml(iconName);
    b.addEventListener('click', act);
    return b;
}

function faceRow(name, meta, onName, buttons) {
    const row = document.createElement('div');
    row.className = 'finds-face-row';
    const text = document.createElement(onName ? 'button' : 'span');
    text.className = 'finds-face-row-text';
    if (onName) {
        text.type = 'button';
        text.addEventListener('click', onName);
    }
    const top = document.createElement('span');
    top.className = 'finds-face-row-name';
    top.textContent = name;
    top.title = name;
    const below = document.createElement('span');
    below.className = 'finds-face-row-meta';
    below.textContent = meta;
    text.append(top, below);
    row.append(text, ...buttons);
    return row;
}

function drawFace() {
    const f = current;
    face.textContent = '';
    if (!f || !tools || !detail) {
        face.hidden = true;
        fitFace(true);
        return;
    }
    // Shown while it is drawn: a plate takes its proportion from its box.
    face.hidden = false;

    // The plates' column — the plates themselves, laid out by fitFace.
    const platesBox = document.createElement('div');
    platesBox.className = 'finds-face-plates';
    for (const p of tools.plates.list()) {
        const box = document.createElement('div');
        box.className = 'finds-face-item';
        box.title = t('Im Reiter Tafeln öffnen');
        box._plate = p;
        platesBox.append(box);
        // Drawn once at a trial width: its height there gives the page's proportion.
        if (!tools.plates.face(box, FACE_TRIAL, p)) box.remove();
        else box._ratio = FACE_TRIAL / (box.offsetHeight || FACE_TRIAL * 1.414);
    }
    if (platesBox.children.length) face.append(platesBox);

    // The last column: every picture as a tile, the files, the sequences.
    const side = document.createElement('div');
    side.className = 'finds-face-side';
    if (f.pictures.length) {
        const tilesBox = document.createElement('div');
        tilesBox.className = 'finds-face-tiles';
        for (const p of f.pictures) {
            const tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'finds-face-tile';
            tile.title = [p.title ? vocab(p.title) : null, t('Im Reiter Bilder öffnen')].filter(Boolean).join(' · ');
            tile._pic = p;
            const img = document.createElement('img');
            img.alt = '';
            // A picture whose file is not there keeps its tile as an empty frame.
            img.addEventListener('error', () => { tile.classList.add('is-broken'); fitFaceSoon(); }, { once: true });
            /* Its proportion is known once the preview is there — laid out
               again then, and again when the edited one replaces it: a cut
               picture has another shape. */
            img.addEventListener('load', () => fitFaceSoon());
            developThumb(img, p.thumb, p.develop, keyOf(p));
            tile.append(img);
            tile.addEventListener('click', () => {
                lastKey = keyOf(p);
                showKey(lastKey);
                showTab('pictures');
            });
            tilesBox.append(tile);
        }
        side.append(tilesBox);
    }

    const files = cockpit && faceFiles.id === f.id ? faceFiles.files : [];
    if (files.length) {
        const block = document.createElement('div');
        block.className = 'finds-face-block';
        block.append(faceHead(t('Dateien')));
        for (const file of [...files].sort((a, b) => `${a.dir ?? ''}/${a.name}`.localeCompare(`${b.dir ?? ''}/${b.name}`, 'de'))) {
            const look = cockpit.files.lookable(file);
            const meta = [file.dir, file.size != null ? tools.viewer.cloudBytes(file.size) : '', readableDate(file.uploaded_at)]
                .filter(Boolean).join(' · ');
            const drive = file.provider === 'gdrive';
            block.append(faceRow(file.name, meta, look ? () => cockpit.files.view(file) : null, [
                faceButton('import', drive ? t('In Google Drive öffnen') : t('Herunterladen'), () => cockpit.files.download(file)),
            ]));
        }
        side.append(block);
    }

    const sequences = detail.point?.sequences ?? [];
    if (sequences.length) {
        const block = document.createElement('div');
        block.className = 'finds-face-block';
        block.append(faceHead(t('Sequenzen')));
        for (const s of sequences) {
            const name = [s.marker || t('Sequenz'), t('%d bp', s.length)].join(' · ');
            const meta = [s.accession, s.label].filter(Boolean).join(' · ');
            block.append(faceRow(name, meta, null, [
                faceButton('import', t('Als FASTA-Datei speichern'), () => saveSequenceFasta(s)),
            ]));
        }
        side.append(block);
    }
    if (side.children.length) face.append(side);

    /* WITH A PLATE, ONE OF TWO AT A TIME (30.10.2026: „wenn es
       eine Tafel gibt, kannst du nur die Tafel anzeigen, beziehungsweise
       … eine Tab-Leiste. Mit Tafel und Bildern. Auf der rechten Seite").
       Plates and tiles side by side split the width between two views of
       the same pictures; one at a time, the chosen one takes all of it.
       „Tafel" comes first and is where a find starts — the plate is the
       find's pictures already laid out. The files and the sequences go
       with the pictures: the plate stands alone. The choice holds while
       one walks from find to find, as the page's own tabs do. Without a
       plate there is no bar: a bar over one view names it twice.

       THE BAR STANDS AT THE RIGHT EDGE, two tall buttons with their words
       turned (16.11.2026: „eine Schnellauswahl rechts an den
       Rand … 2 Buttons … wir können auch den Text drehen und ihn
       vertikal anzeigen, dass wir große Schaltflächen haben. Allerdings
       hier mit Umschalten nicht beim Überfahren, sondern zum Anklicken").
       Over the column it was a thin line of small words, far from the
       hand; at the edge each button is half the window high and is hit
       without aiming. A click switches, the pointer passing over does
       nothing. The height it took above is the pictures' now; its width
       comes off theirs (fitFace). */
    const tabbed = platesBox.isConnected && side.isConnected;
    face.classList.toggle('has-tabs', tabbed);
    if (tabbed) {
        const bar = document.createElement('div');
        bar.className = 'finds-face-tabs';
        bar.setAttribute('role', 'tablist');
        bar.setAttribute('aria-orientation', 'vertical');
        bar.setAttribute('aria-label', t('Tafel oder Bilder'));
        const plates = platesBox.children.length;
        const pictures = f.pictures.length;
        const tabFor = (view, label, count, panel) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'finds-face-tab';
            b.setAttribute('role', 'tab');
            b.dataset.view = view;
            const words = document.createElement('span');
            words.className = 'finds-face-tab-words';
            words.textContent = label;
            if (count > 1) {
                const n = document.createElement('span');
                n.className = 'tab-count';
                n.textContent = String(count);
                words.append(' ', n);
            }
            b.append(words);
            b.addEventListener('click', () => {
                faceView = view;
                faceViewShow(bar, platesBox, side);
                fitFace(true);
            });
            panel.setAttribute('role', 'tabpanel');
            return b;
        };
        // The second tab says what it holds where a find has files but no pictures.
        const second = pictures ? t('Bilder') : (faceFiles.id === f.id && faceFiles.files.length ? t('Dateien') : t('Sequenzen'));
        bar.append(
            tabFor('plates', plates > 1 ? t('Tafeln') : t('Tafel'), plates, platesBox),
            tabFor('pictures', second, pictures, side),
        );
        face.append(bar);
        faceViewShow(bar, platesBox, side);
        // Up and down move between the two, as the bar stands upright.
        bar.addEventListener('keydown', (e) => {
            const to = { ArrowDown: 'pictures', ArrowRight: 'pictures', ArrowUp: 'plates', ArrowLeft: 'plates', Home: 'plates', End: 'pictures' }[e.key];
            if (!to || to === faceView) return;
            e.preventDefault();
            bar.querySelector(`[data-view="${to}"]`)?.click();
            bar.querySelector(`[data-view="${to}"]`)?.focus();
        });
    }
    face.hidden = face.children.length === 0;
    fitFace(true);
}

let faceView = 'plates';

/** The chosen one of plate and pictures shown, its tab marked. */
function faceViewShow(bar, platesBox, side) {
    for (const b of bar.children) {
        const on = b.dataset.view === faceView;
        b.setAttribute('aria-selected', String(on));
        b.tabIndex = on ? 0 : -1;
    }
    platesBox.hidden = faceView !== 'plates';
    side.hidden = faceView === 'plates';
}

/*
 * THREE COLUMNS AND NO SCROLL BAR BUT THE TEXTS' (23.10.2026:
 * „Vielleicht machen wir bei der Übersicht drei Spalten … ganz links die
 * Textfassung, als zweite Spalte die Tafeln, als dritte alle Bilder als
 * Vorschau, Kacheln, plus Dateien, plus Sequenzen … dass sich das Ganze ein
 * bisschen maximieren würde, was wir wirklich pro Spalte an Höhe brauchen").
 * Every column has the window's height below the bar and the tabs; the
 * widths follow from what each needs at that height:
 *
 * - THE TEXTS need no more than their widest one-line field takes without
 *   being cut — a label, the value, the clear cross, the key, the
 *   coordinate's buttons (textNeed). Short texts give the rest away; the
 *   text areas wrap anyway and only grow downwards, where this column alone
 *   may scroll.
 * - THE PLATES fill the height, so their column is as wide as the plates at
 *   that height are (rowfit.js, widthsForHeight): one plate as wide as it is
 *   at full height, two side by side or one above the other — side by side
 *   where the width is there, as large as possible.
 * - THE TILES take the rest: square, all of them in sight above the files and
 *   the sequences, as large as the height allows. Where the tiles need less than is
 *   left, the texts get the surplus.
 *
 * On the phone the columns stand under one another and none of this holds.
 */
const FACE_TRIAL = 300;
const FACE_GAP = 8;
const SIDE_MIN = 256;
let faceFitAt = '';
let faceFitFrame = 0;
const textBox = () => document.querySelector('#finds-texts > .detail');

/** Laid out again in the next frame — forced after a change of content, else only where the size changed. */
function fitFaceSoon(force = true) {
    cancelAnimationFrame(faceFitFrame);
    faceFitFrame = requestAnimationFrame(() => fitFace(force));
}

/** What the texts need across: the widest one-line field, whole. */
function textNeed() {
    const panel = document.getElementById('d-panel-text');
    const rem = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
    const canvas = textNeed.canvas ??= document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    let widest = 0;
    for (const el of panel.querySelectorAll('input:not([type="checkbox"]):not([type="hidden"]), select')) {
        if (!el.offsetParent) continue;
        const cs = getComputedStyle(el);
        ctx.font = `${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
        const text = el.tagName === 'SELECT' ? (el.selectedOptions[0]?.textContent ?? '') : (el.value || el.placeholder || '');
        let need = ctx.measureText(text).width + parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight) + 4;
        if (el.tagName === 'SELECT' || el.closest('.clear-shell')) need += 28;            // the arrow, the clear cross
        if (el.type === 'datetime-local' || el.type === 'date') need = Math.max(need, 13 * rem);
        // Buttons beside the field in its row (the coordinate's pencil and copy).
        const row = el.closest('.coord-row');
        if (row) for (const c of row.children) if (c !== el && c.offsetParent) need += c.offsetWidth + 6;
        // The key behind „Art" stands in a third column of that field.
        const field = el.closest('.field');
        const key = field?.querySelector(':scope > #d-key:not([hidden])');
        if (key) need += key.offsetWidth + 0.8 * rem;
        widest = Math.max(widest, need);
    }
    /* Around the values, as the page draws it now: the label's track and its
       gap (read off a field's grid, not reckoned from rem), and whatever the
       column holds beside its fields — padding, a scroll bar. */
    const field = panel.querySelector('.field:not(.field-tick):not([hidden])');
    const box = textBox();
    let around = 11 * rem + 0.8 * rem + 0.4 * rem + 16;
    if (field && box && field.offsetWidth) {
        const grid = getComputedStyle(field);
        const label = parseFloat(grid.gridTemplateColumns) || 11 * rem;
        around = label + (parseFloat(grid.columnGap) || 0) + (box.offsetWidth - field.offsetWidth) + 2;
    }
    return Math.max(22 * rem, around + widest);
}

/*
 * SHARP AT ANY SIZE (25.10.2026: „es ist ziemlich unscharf …
 * gerade wenn nur ein Bild da ist und das eben extrem vergrößert wird").
 * The tiles start from the 320-pixel preview; where a tile stands larger
 * than that on the screen's own pixels, the large picture is fetched and
 * swapped in once it has arrived — the preview stays until then, never an
 * empty frame. Asked once per tile.
 */
function sharpen(tile, w, h) {
    const img = tile.querySelector('img');
    const p = tile._pic;
    if (!img || !p?.large || tile._sharp || tile.classList.contains('is-broken')) return;
    const need = Math.max(w, h) * (window.devicePixelRatio || 1);
    if (need <= Math.max(img.naturalWidth, img.naturalHeight, 320) * 1.1) return;
    tile._sharp = true;
    const large = new Image();
    large.addEventListener('load', () => { if (tile.isConnected) developThumb(img, p.large, p.develop, keyOf(p)); }, { once: true });
    large.src = p.large;
}

function fitFace(force = false) {
    const box = textBox();
    const phone = matchMedia('(max-width: 860px)').matches;
    // Of plate and pictures only the one on view when the bar stands above them (drawFace).
    const platesBox = face.querySelector('.finds-face-plates:not([hidden])');
    const side = face.querySelector('.finds-face-side:not([hidden])');
    const tilesBox = side?.querySelector('.finds-face-tiles');
    if (face.hidden || phone || tab !== 'text') {
        if (box) box.style.flex = '';
        face.style.flex = '';
        if (phone) {
            for (const el of face.querySelectorAll('.finds-face-plates, .finds-face-side')) el.style.width = '';
        }
        return;
    }
    const all = document.getElementById('finds-texts');
    const gap = parseFloat(getComputedStyle(all).columnGap) || 25;
    const total = all.clientWidth;
    // The bar at the right edge and its gap come off the width the pictures may take (drawFace).
    const bar = face.querySelector('.finds-face-tabs');
    const barW = bar ? bar.offsetWidth + (parseFloat(getComputedStyle(face).columnGap) || 0) : 0;
    const H = face.clientHeight - (parseFloat(getComputedStyle(face).paddingBottom) || 0);
    const items = platesBox ? [...platesBox.querySelectorAll('.finds-face-item')] : [];
    const tiles = tilesBox ? (tilesBox._items ??= [...tilesBox.children]) : [];
    const at = `${total}x${H}x${items.length}x${tiles.length}x${platesBox ? 'p' : ''}${side ? 's' : ''}`;
    if (!total || H <= 0 || (!force && at === faceFitAt)) return;
    faceFitAt = at;

    const need = textNeed();
    const sideMin = side ? SIDE_MIN : 0;
    const gaps = gap * ((platesBox ? 1 : 0) + (side ? 1 : 0)) + barW;

    // The plates' column: the widest arrangement that leaves the texts and the tiles their least.
    let platesW = 0;
    let ratios = [];
    if (platesBox) {
        ratios = items.map((el) => el._ratio);
        const room = total - need - sideMin - gaps;
        const widths = widthsForHeight(ratios, H, FACE_GAP);
        platesW = widths.find((w) => w.width <= room)?.width ?? Math.max(160, room);
        platesW = Math.floor(platesW);
    }

    /* The pictures: AS LARGE AS THE HEIGHT ALLOWS (23.10.2026, at
       a foreign find with four pictures and short texts: „die Bildkacheln
       könnten eigentlich größer werden, sodass sie die volle Höhe des
       Fensters ausnutzen"), and IN THEIR OWN PROPORTION, no longer cut to a
       square (25.10.2026: „nicht mehr als Quadrat anzeigen, sondern im
       Originalformat"). As the plates: the widest arrangement in rows that
       fills the height and fits the width left (rowfit.js), else that width
       with the rows shrunk to it; the column is just as wide — the texts get
       the rest. A column with files or sequences keeps SIDE_MIN for their
       names. A preview not there yet counts as 4:3 and is laid out again. */
    let sideW = 0;
    let picRows = null;
    if (side) {
        const left = total - need - platesW - gaps;
        const rowGap = parseFloat(getComputedStyle(side).rowGap) || 0;
        const below = [...side.children].filter((c) => c !== tilesBox)
            .reduce((a, c) => a + c.offsetHeight + rowGap, 0);
        const tileH = Math.max(80, H - below);
        const lists = side.children.length > (tilesBox ? 1 : 0);
        sideW = Math.min(left, lists ? SIDE_MIN : 0);
        if (tiles.length) {
            const picRatios = tiles.map((tile) => {
                const img = tile.querySelector('img');
                return img?.naturalWidth && img.naturalHeight ? img.naturalWidth / img.naturalHeight : 4 / 3;
            });
            const fits = widthsForHeight(picRatios, tileH, FACE_GAP).find((w) => w.width <= left - 1);
            const picW = Math.floor(fits ? fits.width : left - 1);
            picRows = { ratios: picRatios, ...fitRows(picRatios, picW, tileH, FACE_GAP) };
            const used = Math.max(0, ...picRows.rows.map((row, j) =>
                row.reduce((a, i) => a + Math.floor(picRows.heights[j] * picRatios[i]), 0) + FACE_GAP * (row.length - 1)));
            sideW = Math.max(sideW, used + 1);
        }
        sideW = Math.floor(Math.max(0, sideW));
        side.style.width = `${sideW}px`;
    }

    // The texts take what the other two leave.
    if (box) box.style.flex = '1 1 0';
    face.style.flex = `0 0 ${Math.max(platesW + sideW + (platesBox && side ? gap : 0), bar ? SIDE_MIN : 0) + barW}px`;

    if (picRows) {
        tilesBox.textContent = '';
        picRows.rows.forEach((row, j) => {
            const line = document.createElement('div');
            line.className = 'finds-face-line';
            for (const i of row) {
                const tile = tiles[i];
                const h = Math.floor(picRows.heights[j]);
                const w = Math.floor(picRows.heights[j] * picRows.ratios[i]);
                tile.style.width = `${w}px`;
                tile.style.height = `${h}px`;
                sharpen(tile, w, h);
                line.append(tile);
            }
            tilesBox.append(line);
        });
    }

    if (platesBox) {
        platesBox.style.width = `${platesW}px`;
        const { rows, heights } = fitRows(ratios, platesW, H, FACE_GAP);
        platesBox.textContent = '';
        rows.forEach((row, j) => {
            const line = document.createElement('div');
            line.className = 'finds-face-line';
            for (const i of row) {
                const el = items[i];
                const w = Math.floor(heights[j] * ratios[i]);
                el.style.width = `${w}px`;
                tools.plates.face(el, w, el._plate);
                line.append(el);
            }
            platesBox.append(line);
        });
    }
}

// A column that changes size — the window, the rail beside — lays its pictures out anew.
new ResizeObserver(() => { if (tab === 'text') fitFaceSoon(false); }).observe(document.getElementById('finds-texts'));
// A value typed longer may want a wider text column.
document.getElementById('finds-texts').addEventListener('change', () => { if (tab === 'text') fitFaceSoon(); });

back.addEventListener('click', close);
/* A FIND NAMED FROM INSIDE ANOTHER — an Aufsammlung's species, the way back
   from a species (collectionui.js) — opens in place, after what the open
   find still owes is saved. Answered only for a find this page knows; the
   sender loads the page anew for any other. */
document.addEventListener('finds-open', (e) => {
    const id = Number(e.detail?.id);
    if (!byId.has(id)) return;
    e.preventDefault();
    (async () => {
        if (!(await mayLeave())) return;
        open(id);
        if (e.detail?.tab) showTab(e.detail.tab);
    })();
});

window.addEventListener('popstate', async () => {
    if (opened !== null && cockpit?.dirty() && !(await cockpit.flush()) && !(await cockpit.leaveOk())) {
        history.pushState({ fund: opened }, '', '#fund=' + opened);
        return;
    }
    route();
});

/* ESCAPE GOES BACK — but never while one is typing or a window stands open:
   the key then belongs to the field or the window. (The arrows no longer
   walk to the next find: with the window's tabs on the page they were one
   key too many, and the maintainer took the walk out on 12.10.2026.) */
document.addEventListener('keydown', (e) => {
    if (opened === null || e.altKey || e.ctrlKey || e.metaKey) return;
    /* On „Bilder" the key is the viewer's: it ends a tool or takes back a
       point, and one Escape too many must not throw the find away. */
    if (tab === 'pictures' || tab === 'plates') return;
    if (document.querySelector('dialog:modal') || e.target.closest?.('input, textarea, select, [contenteditable]')) return;
    if (e.key !== 'Escape') return;
    close();
    e.preventDefault();
});

// --- The tools: picture viewer and plates ------------------------------------

const START = window.START_DATA;
const me = START.user.id;

/** Whose picture it is, where it is not mine — the rule of the map's gallery (photoBy). */
const by = (f) => (f.taken_by || (f.photographer_id != null && f.photographer_id !== me && f.mine !== true)
    ? f.photographer ?? null : null);

/**
 * What the cockpit asks of the open find — the find as the server prepares
 * it, its recordings, the rights one has on it (api/v1/finds.php?id=,
 * find_cockpit). Asked once per find opened; the viewer and the plates
 * read it through their hosts.
 */
function loadDetail(f) {
    detail = null;
    const load = request(`finds.php?id=${f.id}`)
        .then((d) => {
            if (current !== f) return null;
            detail = d;
            tools ??= mountTools();
            Object.assign(tools.viewer.cloudFilesShown, { pointId: f.id, files: d.files });
            if (!cockpit) {
                cockpit = mountCockpit(START, {
                    tools,
                    status: (text, isError) => toast(text, isError ? 'error' : 'success'),
                    leave: () => closeNow(),
                    gone: (id) => forget(id),
                    changed: (point) => pointSaved(point),
                    picturesChanged: () => syncStage(),
                    filesChanged: (list) => {
                        faceFiles = { id: current?.id ?? 0, files: list };
                        if (tab === 'text') drawFace();
                    },
                    mapAddress: (id) => MAP + '?point=' + id,
                    mapBox: document.getElementById('finds-map'),
                    savedLine: document.getElementById('finds-saved'),
                });
                cockpit.overview(finds);
            }
            cockpit.open(d);
            // The plates come with their own answer; the face and the viewer once they are there.
            Promise.resolve(tools.plates.show(f.id)).then(() => {
                if (current !== f) return;
                syncPlateTab();
                if (tab === 'text') drawFace();
                viewerIdle();
                platesIdle();
            });
            viewerIdle();
            return d;
        })
        .catch((e) => {
            if (current === f) toast(t('Der Fund ließ sich nicht laden: %s', e.message), 'error');
            return null;
        });
    detailLoad = load;
    return load;
}

/**
 * The Belege changed the pictures (a drag into another order, an upload):
 * the stage beside the window draws them as they stand now — from the live
 * records the gallery keeps (the find's photos and the viewer's cloud list),
 * and only when something is different, so no drawing sets off another.
 */
function syncStage() {
    const f = current;
    if (!f || !detail || !tools || tools.viewer.cloudFilesShown.pointId !== f.id) return;
    detail.files = tools.viewer.cloudFilesShown.files;
    const next = picturesFrom(detail);
    const sig = (list) => list.map((p) => `${p.kind}${p.id}:${p.title ?? ''}`).join('|');
    if (sig(next) === sig(f.pictures)) return;
    f.pictures = next;
    drawPictures(f);
    // The first picture uploaded comes into the empty viewer.
    viewerIdle();
}

/**
 * The find was saved in the window: the page's lists follow — the tile and
 * the row with the new name, number, day, place and tour, the header, the
 * counts at the side — and the record the viewer and the plates read.
 */
function pointSaved(point) {
    const f = byId.get(point.id);
    if (!f) return;
    f.title = String(point.title ?? '').trim();
    f.number = point.is_collection ? String(point.collection_number ?? '')
        : point.find_number || findNumberFallback(point.id);
    if (point.is_collection) {
        f.culture = point.culture_ended_at ? 'ended' : point.cultured_at ? 'running' : 'waiting';
    }
    if (point.collection_id != null) {
        f.identified_at = point.identified_at ? String(point.identified_at).slice(0, 10) : null;
        collectGrown();   // the order under the Aufsammlung follows the day
    }
    f.found_at = point.found_at || null;
    f.year = f.found_at === null ? null : Number(f.found_at.slice(0, 4));
    f.place = String(point.municipality || point.region || point.country || '').trim();
    f.tour = String(point.tour ?? '').trim();
    f.lat = Number(point.lat);
    f.lon = Number(point.lon);
    f.hay = hayOf(f);
    if (tiles.has(f.id)) { tiles.get(f.id).innerHTML = tileHtml(f); coverThumb(tiles.get(f.id), f); }
    if (rows.has(f.id)) { rows.get(f.id).innerHTML = rowHtml(f); coverThumb(rows.get(f.id), f); }
    if (detail && detail.point.id === point.id) Object.assign(detail.point, { ...point, photos: detail.point.photos });
    if (current === f) drawFacts();   // a changed day stands in the head
    drawSide();
}

/** The find went to the trash: gone from the page too, and back to the tiles. */
function forget(id) {
    const at = finds.findIndex((f) => f.id === id);
    if (at >= 0) finds.splice(at, 1);
    byId.delete(id);
    tiles.get(id)?.remove();
    tiles.delete(id);
    rows.get(id)?.remove();
    rows.delete(id);
    if (selected === id) selected = null;
    show();
    closeNow();
}

/** The pictures of the open find in the shape the strip draws, from the records the viewer holds. */
function picturesFrom(d) {
    // The Hauptmotiv first — the tile shows the first picture (includes/finds.php has the same rule).
    const rank = (x) => [(x.title ?? '').trim().toLowerCase() === 'hauptmotiv' ? 0 : 1, x.order ?? 65536, x.kind === 'photo' ? 0 : 1, x.id];
    return [
        ...d.point.photos.map((x) => ({
            kind: 'photo', id: x.id, title: x.title ?? null, by: by(x), order: x.sort_order ?? 0,
            thumb: `${START.baseUrl}pages/photo.php?id=${x.id}&k=1`,
            large: `${START.baseUrl}pages/photo.php?id=${x.id}`,
            develop: x.develop ?? null,
        })),
        ...d.files.map((x) => ({
            kind: 'cloud', id: x.id, title: x.title ?? null, name: x.name, by: by(x), order: x.sort_order ?? 65536,
            thumb: x.preview, large: `${x.preview}&large=1`,
            develop: x.develop ?? null,
        })),
    ].sort((a, b) => { const [p, q] = [rank(a), rank(b)]; return p[0] - q[0] || p[1] - q[1] || p[2] - q[2] || p[3] - q[3]; });
}

/* AFTER A CHANGE IN THE VIEWER — a name, a deletion — it asks its host to
   draw the find's gallery again. Here that is the strip and the plates'
   row: the records are fetched anew, and the objects the viewer already
   holds are kept, so the picture in it and its mark in the strip stay the
   same objects (as at Organisieren's refreshPictures). */
async function refreshDetail() {
    const f = current;
    if (!f || !detail) return;
    let fresh;
    refreshing = true;
    try {
        fresh = await request(`finds.php?id=${f.id}`);
    } catch {
        return;   // the strip keeps what it showed
    } finally {
        refreshing = false;
    }
    if (current !== f) return;
    const keep = (list, next) => {
        const known = new Map(list.map((x) => [x.id, x]));
        return next.map((x) => known.get(x.id) ?? x);
    };
    detail.point.photos = keep(detail.point.photos, fresh.point.photos);
    detail.files = keep(detail.files, fresh.files);
    Object.assign(tools.viewer.cloudFilesShown, { pointId: f.id, files: detail.files });
    f.pictures = picturesFrom(detail);
    drawPictures(f);
    tools.plates.show(f.id);
    cockpit?.redrawGallery();
    // A picture deleted in the viewer closed it: the next one takes its place.
    viewerIdle();
}

/** The right to tend the find's pictures — the map's mayEditPointPhotos, from what the server said. */
function mayEditPictures(right = 'measure') {
    if (!detail) return false;
    if (detail.own) return true;
    const p = detail.point;
    // A co-owner tends the find's pictures — his own; the others' the server refuses by their photographer.
    if (p.co_owned) return true;
    if (p.excursion_id != null && p.editable) return true;
    return new Set(detail.rights).has(right);
}

function mountTools() {
    const point = () => detail?.point ?? null;
    const viewer = mountPhotoWindow({
        start: START,
        // „Ebenen", a tab of the viewer's „Bearbeiten" (blend.js, mounted above).
        layers: blend,
        guest: false,
        pointId: () => point()?.id ?? null,
        point: (id) => (point()?.id === id ? point() : null),
        drawGallery: () => refreshDetail(),
        /* The picture that followed a deleted one (photowindow.js,
           galleryPlace): viewerIdle shows it instead of the group's first,
           so one goes on from where one stood (18.11.2026). */
        pictureNext: (key) => { if (key) lastKey = key; },
        mayEditPhotos: mayEditPictures,
        foreign: () => (detail && !detail.own ? detail.point : null),
        sharedRights: (f) => (f.excursion_id != null && f.editable
            ? { has: () => true }
            : new Set(detail?.rights ?? [])),
        status: (text, isError = false) => toast(text, isError ? 'error' : 'success'),
        // In the tab „Bilder" — unless something modal lies over the page (a plate).
        inPage: () => opened !== null && !document.querySelector('dialog:modal'),
        // New settings stored: the find's own picture list carries them too, or a tile drawn anew would show the old.
        developed: (key, d) => {
            for (const p of current?.pictures ?? []) if (keyOf(p) === key) p.develop = d;
        },
        zoomMoved: () => {
            const key = viewer.pictureKey();
            if (current && key) views.set(key, viewer.zoomView());
        },
    });
    const plates = mountPlates({
        request,
        point: (id) => (point()?.id === id ? point() : undefined),
        pictures(id) {
            if (point()?.id !== id) return [];
            const out = [];
            for (const f of detail.point.photos) {
                out.push({
                    key: `photo:${f.id}`, title: f.title ?? null, order: f.sort_order ?? undefined,
                    w: f.width ?? 0, h: f.height ?? 0, scale: f.scale ?? null, measures: f.measures ?? [], media: f.media ?? null, develop: f.develop ?? null,
                    src: `${START.baseUrl}pages/photo.php?id=${f.id}`,
                    thumb: `${START.baseUrl}pages/photo.php?id=${f.id}&k=1`,
                    by: by(f),
                });
            }
            for (const f of viewer.cloudFilesShown.files) {
                out.push({
                    key: `cloud:${f.id}`, title: f.title ?? null, order: f.sort_order ?? undefined,
                    w: f.width ?? 0, h: f.height ?? 0, scale: f.scale ?? null, measures: f.measures ?? [], media: f.media ?? null, develop: f.develop ?? null,
                    src: viewer.cloudPhotoUrl(f), thumb: f.preview,
                    // The recording itself — the printed page fetches it where a cutout needs it.
                    original: `${START.baseUrl}pages/cloud_thumb.php?id=${f.id}&original=1`,
                    by: by(f),
                });
            }
            return out;
        },
        /* A PICTURE OF THE PLATE IN THE VIEWER: the same window the strip
           opens — false where the find does not hold it (any more). */
        openPicture(id, key) {
            if (point()?.id !== id) return false;
            /* FROM THE PLATE IN ITS TAB: the picture goes to „Bilder", where
               the viewer stands — the plate stays open in its tab and is
               drawn again when one comes back (platesIdle, refresh). */
            if (plateWindowEl.open && !plateWindowEl.matches(':modal')) {
                if (!showKey(key)) return false;
                lastKey = key;
                showTab('pictures');
                return true;
            }
            const show = key.startsWith('photo:')
                ? ((f) => f && (() => viewer.showPhoto(f)))(detail.point.photos.find((x) => `photo:${x.id}` === key))
                : ((f) => f && (() => viewer.showCloudPhoto(f)))(viewer.cloudFilesShown.files.find((x) => `cloud:${x.id}` === key));
            if (!show) return false;
            /* OVER THE PLATE, NOT BEHIND IT: the viewer standing in the page
               would show the picture under the modal plate, unseen. It closes
               first and comes back as a window over the plate (inPage says no
               while the plate is open); when the plate goes, viewerIdle puts
               it back into the page. THE NEW PICTURE WAITS FOR THE CLOSE
               EVENT: the viewer tidies up in it (measures, the image's
               address), and what it tidied would be the new picture. Nobody
               after this listener hears of that close — the plate's own
               listener (viewPicture) is meant for the viewer's real closing. */
            if (photoWindowEl.open && !photoWindowEl.matches(':modal')) {
                photoWindowEl.addEventListener('close', (e) => {
                    e.stopImmediatePropagation();
                    show();
                }, { once: true });
                photoWindowEl.close();
            } else {
                show();
            }
            return true;
        },
        // The words the plate prints — one answer for the map and here (finddetails.js).
        // Every find of this page is a find: the number it would carry anyway is printed.
        details: (id) => findDetails(point()?.id === id ? point() : null, true),
        // The holder and the co-owners — the circle that works on the find.
        mayMake: (id) => point()?.id === id && detail.may_make === true,
        photoTitles: () => viewer.measureStyle.photo_titles,
        // The account's measuring style — the air around a cut-out spore, per layer.
        style: () => viewer.measureStyle,
        // In the tab „Tafeln" — unless something modal lies over the page.
        inPage: () => opened !== null && !document.querySelector('dialog:modal'),
        // The face beside the texts leads into the plate in its tab.
        showPlate: (p) => {
            tools.plates.openPlate(p).then((ok) => { if (ok) showTab('plates'); });
        },
    });
    return { viewer, plates };
}

// --- Loading ------------------------------------------------------------------

async function load() {
    const response = await fetch(API, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    let data = null;
    try { data = await response.json(); } catch { /* not JSON — handled below */ }
    if (!response.ok || data === null) {
        throw new Error(data?.error || t('Server meldet %s.', response.status));
    }
    archiveCount = Number(data.archive) || 0;
    return data.finds;
}

/** The server's finds into the page's records — at the load, and again when the page comes back (listReload). */
function takeFinds(list) {
    finds = list;
    byId.clear();
    collectGrown();
    for (const f of finds) {
        byId.set(f.id, f);
        f.hay = hayOf(f);
    }
}

try {
    const list = await load();
    // A remembered „Bildarchiv" whose archive is gone (or not yet there) shows the finds.
    if (filter.mode === 'archive' && archiveCount === 0) filter.mode = 'finds';
    takeFinds(list);
    // A choice remembered from a visit whose person or year is gone now.
    if (!['all', 'own', 'others'].includes(filter.who) && !finds.some((f) => inView(f) && passesWho(f))) filter.who = 'all';
    if (filter.year !== 'all' && !finds.some((f) => inView(f) && passesYear(f))) filter.year = 'all';
    if (filter.kind !== 'all' && !finds.some((f) => passesKind(f))) filter.kind = 'all';
    buildTiles();
    show();
    route();
} catch (e) {
    empty.textContent = t('Die Funde ließen sich nicht laden: %s', e.message);
}

/*
 * THE TILES ARE KEPT CURRENT (14.11.2026: „der Fund-Browser mit
 * seiner Thumbnail-Ansicht ist oftmals nicht aktuell … setze ich irgendwo
 * eine Bearbeitung und gehe zurück … müssen wir an diesem Punkt das Bild
 * aktualisieren"). The tiles were built once, at the load, from the list
 * the server gave then, and two ways led back to them unchanged:
 *
 * - FROM THE FIND OPEN HERE. Its record follows everything done in it —
 *   pictures uploaded, deleted, sorted, renamed, edited (picturesFrom,
 *   syncStage, host.developed) — but its tile was never drawn again, so a
 *   new Hauptmotiv or a count stayed as it was. showBrowse draws the tile
 *   of the find it leaves (findRedraw).
 * - FROM ANOTHER PAGE OR TAB. The map, another tab, another device changed
 *   things the list knows nothing of; and the browser's back button brings
 *   this page out of its back/forward cache exactly as it was left (Firefox
 *   above all), with no load and no visibilitychange. So the list is asked
 *   anew when the page is shown out of that cache (pageshow, persisted) and
 *   when its tab comes back after a while away (visibilitychange; a glance
 *   at another tab shorter than LIST_AWAY_MS is not worth the list). While
 *   a find stands open, the tiles are not in sight and the open records
 *   belong to the cockpit: the fresh list waits until the find is left.
 */
/** Every tile and row drawn anew from the records — where a find gained or lost its tile, the order needs the whole grid. */
function listRedraw() {
    for (const el of tiles.values()) el.remove();
    tiles.clear();
    for (const el of rows.values()) el.remove();
    rows.clear();
    rowsBuilt = false;
    buildTiles();
    show();
}

/** One find's tile and row from its record, as pointSaved does — the whole grid only where its tile comes or goes. */
function findRedraw(f) {
    if (!byId.has(f.id)) return;
    if (onWall(f) !== tiles.has(f.id)) {
        listRedraw();
        return;
    }
    if (tiles.has(f.id)) { tiles.get(f.id).innerHTML = tileHtml(f); coverThumb(tiles.get(f.id), f); }
    if (rows.has(f.id)) { rows.get(f.id).innerHTML = rowHtml(f); coverThumb(rows.get(f.id), f); }
    show();
}

/** The list fetched anew and drawn — at once in the tiles, after the find when one stands open. */
async function listReload() {
    if (opened !== null) {
        listStale = true;
        return;
    }
    listStale = false;
    let list;
    try {
        list = await load();
    } catch {
        return;   // no connection: the tiles keep what they showed
    }
    // A find opened while the list was on its way: it waits for that one to be left.
    if (opened !== null) {
        listStale = true;
        return;
    }
    const at = { grid: grid.scrollTop, results: results.scrollTop };
    takeFinds(list);
    listRedraw();
    grid.scrollTop = at.grid;
    results.scrollTop = at.results;
}

window.addEventListener('pageshow', (e) => { if (e.persisted) listReload(); });
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        listHiddenAt = Date.now();
        return;
    }
    if (listHiddenAt !== null && Date.now() - listHiddenAt >= LIST_AWAY_MS) listReload();
    listHiddenAt = null;
});
