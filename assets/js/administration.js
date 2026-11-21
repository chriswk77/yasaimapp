/**
 * The offline areas in the administration — without a map.
 *
 * WHAT WORKS HERE AND WHAT DOES NOT, and why the boundary lies exactly there:
 *
 *   WITHOUT MAP  Listing how much lies where, taking off the device,
 *                deleting everywhere, emptying the incidental cache. Those
 *                are questions to the server and to this browser's storage —
 *                for none of them does a map have to be drawn.
 *
 *   MAP ONLY     Outlining (you tap it in corner by corner), showing (it is
 *                supposed to appear on the map, after all) and loading the
 *                tiles. Loading because the address of every tile comes from
 *                Leaflet's own computation — same layer, same projection,
 *                same zoom level. Rebuilding it here would mean duplicating
 *                the computation, and with the next Leaflet it would drift
 *                apart without anyone noticing.
 *
 * These three therefore lead via a link back to the map, which then opens
 * the matching window — see windowFromUrl() in map.js.
 */

import * as areas from './areas.js';
import { t, lang } from './i18n.js';
import * as protectedAreas from './protectedareas.js';
import { statesMap } from './statesmap.js';
import * as inpaint from './inpaint.js';
import { ORIGINAL_BUDGETS, originalBudget, originalBudgetSet, originalCacheClear, originalCacheState, originalCacheUsable,
    originalKeeping, originalKeepingSet } from './originalcache.js';
// The recorded tracks on this device — read only, for the Offline tab.
import { confirm, prompt, wireForms } from './dialogs.js';
import { toast, liftNotices } from './toast.js';
import { mountOrganize } from './organize.js';
import { mountShared } from './shared.js';
import { mountHints } from './hints.js';
import { mountArchiveViewers } from './archiveviewers.js';
import { mountWorkingNames } from './workingnames.js';
import { mountTabs } from './tabs.js';
import { icon } from './icons.js';
import { renderFindNumber, normalizePattern, periodUnit, periodKey, findNumberReserved } from './findnumber.js';
import { mountImportRows } from './importrows.js';

const section = document.getElementById('area-list');
if (section) draw();

function toMap(params) {
    return (window.START_DATA?.baseUrl ?? '') + 'index.php?window=areas&' + params;
}

function hint(text) {
    const p = document.createElement('p');
    p.className = 'hint-text';
    p.textContent = text;
    return p;
}

/**
 * The area list — first from the mirror, then from the server.
 *
 * WHY NOT SIMPLY ASK THE SERVER. That is exactly what it did, and the maintainer
 * watched "wird geholt …" for half to three quarters of a second even though
 * the device had long had the list. Measured, it is not the counting of the
 * tiles — 6,100 of them across three areas cost 48 ms — but the request
 * itself: just getting to the first byte takes 130 to 415 ms to the live
 * server, and the area query does session and database on top of that.
 *
 * But the mirror in local storage exists for exactly this. It is kept up to
 * date with every server response and maintained on create and delete (see
 * writeSnapshot in areas.js), so it is not merely an old state but the
 * last known one — as a rule the right one.
 *
 * SO DRAW FIRST, THEN ASK. Redrawing happens only when the answer really
 * says something different: otherwise the list would flicker on every
 * opening, and the gain would be gone again.
 */
async function draw() {
    const snapshot = areas.areasFromSnapshot();
    /* WHETHER A LIST STANDS YET, and not merely whether something changed.
       Without this marker, „wird geholt …" stood forever as soon as
       somebody had no area at all: the snapshot was empty, the server
       answered empty as well, sameList() said "same" — and since redrawing
       happens only on a change, „Noch kein Gebiet angelegt." never
       appeared. The placeholder looked exactly like a hanging load, and
       the whole administration seemed broken although every other tab
       worked.

       Zeichnen ist billig, wenn es nur einmal passiert. Der Vergleich
       bleibt trotzdem: er verhindert das Flackern bei JEDEM weiteren
       Öffnen, und dafür war er gedacht. */
    let drawn = false;
    if (snapshot.length > 0) {
        await drawList(snapshot);
        drawn = true;
    } else {
        // Without a mirror only waiting remains — the very first time, and
        // then never again.
        section.replaceChildren(hint(t('wird geholt …')));
    }

    let list;
    try {
        list = await areas.areasFromServer();
    } catch (error) {
        // No network: the mirror is already there and is the right answer.
        // Only whoever does not even have that needs a message.
        if (snapshot.length === 0) {
            section.replaceChildren(hint(t('Die Gebiete lassen sich nicht holen: %s',
                error?.message || t('unbekannter Fehler'))));
        }
        return;
    }

    // Clean up only with a directory that really came from the server: from
    // the local mirror an empty list would be a delete order for everything.
    const orphaned = await areas.evictOrphanedTiles(list);
    const expired  = await areas.evictExpiredTiles();
    /* SAID, NOT SILENT (27.09.2026). The maintainer asked whether the tiles of
       an area he had deleted weeks ago still haunt his phone — the sweep
       had long taken them, and nothing had ever said so. A deed is a
       toast; the words are the map's, which says the same at its start. */
    if (orphaned > 0) {
        toast(orphaned === 1
            ? t('1 Gebiet anderswo gelöscht — Kacheln entfernt.')
            : t('%d Gebiete anderswo gelöscht — Kacheln entfernt.', orphaned));
    }
    if (expired > 0) {
        toast(expired === 1
            ? t('1 Gebiet: Karten abgelaufen und entfernt. Die Umrandung bleibt.')
            : t('%d Gebiete: Karten abgelaufen und entfernt. Die Umrandung bleibt.', expired));
    }

    // Redraw only when there is something to see: a different list — or a
    // cleanup that has changed the numbers in the rows.
    if (!drawn || orphaned > 0 || expired > 0 || !sameList(snapshot, list)) {
        await drawList(list);
    }
}

/** Two directories that would display the same? Id and name count. */
function sameList(a, b) {
    const short = (l) => JSON.stringify(l.map(g => [String(g.id), g.name]));
    return short(a) === short(b);
}

async function drawList(list) {
    const fragment = document.createDocumentFragment();
    if (list.length === 0) {
        fragment.append(hint(t('Noch kein Gebiet angelegt.')));
    }
    for (const g of list) {
        fragment.append(await row(g));
    }
    // Swap only AT THE VERY END: appending row by row would mean the old
    // list standing there half deleted during the build.
    section.replaceChildren(fragment);
}

/**
 * Why the last download of an area did not finish, in the row's words —
 * or null when it did, or when nobody recorded it (areas loaded before the
 * run record, 27.09.2026). First in the line: it is the one part that
 * asks for something.
 */
function unfinished(deviceState) {
    const run = deviceState?.run;
    if (!run || run.complete) return null;
    const n = (x) => Number(x ?? 0).toLocaleString('de-DE');
    if (run.error === 'quota') return t('Nicht fertig: Speicher voll');
    if (run.error === 'storage') return t('Nicht fertig: Speichern ging nicht');
    if (run.total > 0 && run.done < run.total) return t('Nicht fertig: %s von %s geladen', n(run.done), n(run.total));
    if (run.unreachable > 0) return t('Nicht fertig: %s Kacheln ohne Verbindung', n(run.unreachable));
    return t('Nicht fertig');
}

/** What stands under the name: size, tile count, zoom, validity. */
function deviceLine(here, deviceState) {
    if (here === 0) return t('nicht auf diesem Gerät');
    return [
        unfinished(deviceState),
        deviceState?.bytes > 0 ? areas.readableSize(deviceState.bytes) : null,
        /* UNKNOWN IS NOT NOTHING (27.09.2026): past about 10 MB of
           addresses the browser refuses to list an area's tiles (see
           tileCount), and an area loaded before the download kept its own
           count cannot be counted any more. It is there — the map finds
           every tile — only the number is missing. */
        here === null ? t('Kachelzahl unbekannt') : t('%s Kacheln', here.toLocaleString('de-DE')),
        deviceState?.zoom_to ? t('bis Zoom %s', deviceState.zoom_to) : null,
        // No date means no expiry — and that has to be readable, otherwise
        // the row for a permanently kept area looks like one whose date
        // got lost (22.08.2026).
        deviceState?.valid_until
            ? t('bis %s', deviceState.valid_until.split('-').reverse().join('.'))
            : t('dauerhaft'),
    ].filter(Boolean).join(' · ');
}

async function row(g) {
    const here = await areas.tileCount(g.id);
    const deviceState = areas.readDeviceState(g.id);

    const row = document.createElement('div');
    row.className = 'item-row';

    const left = document.createElement('div');
    left.className = 'item-text';

    const name = document.createElement('div');
    name.className = 'item-name';
    name.textContent = g.name;

    const device = document.createElement('div');
    device.className = here !== 0 ? 'item-here' : 'item-data';
    device.textContent = deviceLine(here, deviceState);

    /* THE SIZE IS HANDED IN AFTERWARDS, NOT WAITED FOR. It used to stand
       as an await before the row was built, and the finished list is
       deliberately only inserted at the very end — so „wird geholt …"
       stayed there for as long as the measuring took. And measuring is
       expensive: areaSize() reads every single tile whose answer carries
       no Content-Length, which with a tile server that grants no CORS is
       all of them. With three areas of a few thousand tiles the
       administration therefore looks frozen — it is not, it is counting
       bytes.

       Now the row stands there at once, with count, zoom and validity; the
       size appears once it has been determined, and is remembered, so that
       the next opening does not do it again at all. An error while
       measuring costs only that one number and not the list. */
    if (Number.isFinite(here) && here > 0 && deviceState?.bytes === undefined && !deviceState?.run) {
        (async () => {
            try {
                const bytes = await areas.areaSize(g.id);
                const created = { ...(areas.readDeviceState(g.id) ?? {}), bytes };
                areas.writeDeviceState(g.id, created);
                device.textContent = deviceLine(here, created);
            } catch { /* dann eben ohne Größe */ }
        })();
    }

    left.append(name, device);

    const buttons = document.createElement('div');
    buttons.className = 'item-buttons';

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button danger small';
    // Across the whole project the trash can means "delete a record" — here
    // that is true: the button removes the area on all devices.
    remove.append(icon('delete'), document.createTextNode(t('Löschen')));
    remove.addEventListener('click', async () => {
        const yes = await confirm({
            title: t('Gebiet „%s" löschen?', g.name),
            text: t('Damit ist die Umrandung weg, und die Offline-Daten dieses '
                + 'Gebiets werden auf deinen Geräten mitgelöscht — jeweils beim '
                + 'nächsten Aufruf mit dem Gerät.'),
            button: t('Gebiet löschen'),
            danger: true,
        });
        if (!yes) return;
        await areas.removeArea(g.id);
        draw();
    });

    // Showing and loading belong on the map — as a link, not a button: this
    // way both can be opened in a new tab without losing this page.
    const show = document.createElement('a');
    // 'zeigen' blue as on the point window: it is the same button everywhere
    // — the one that leads to the spot on the map (see --show in app.css).
    show.className = 'button show small';
    show.href = toMap('show=' + g.id);
    /* THE NAME SAYS BOTH SINCE 22.08.2026. The outline has
       lain there ready to grab since 14.08., but the button only promised
       looking — and whoever expects to look does not try to pull. The
       icon stays the crosshair: it points at the spot, which is still
       what the press does first. */
    show.title = t('Auf der Karte zeigen — dort lässt sich die Umrandung auch ändern');
    show.append(icon('crosshair'), document.createTextNode(t('Zeigen/Editieren')));

    buttons.append(remove, show);

    if (here !== 0) {
        const unload = document.createElement('button');
        unload.type = 'button';
        // item-load-button: see app.css — keeps this button and "Herunterladen"
        // equally wide, otherwise Löschen and Zeigen drift sideways with each
        // row's load state, because the group is right-aligned.
        unload.className = 'button subtle small item-load-button';
        // The device with the red cross: this is about THIS device, not the
        // record — hence no trash can as with "Löschen".
        unload.append(icon('device-remove'), document.createTextNode(t('Vom Gerät löschen')));
        unload.addEventListener('click', async () => {
            await areas.removeTiles(g.id);
            draw();
        });
        buttons.append(unload);
        /* „WEITERLADEN" WHERE THE LAST RUN DID NOT FINISH, or where nobody
           knows (27.09.2026): the same window as „Herunterladen", and
           loadArea() skips every tile already stored — so it fetches only
           what is missing, and its end writes the count the row lacks. An
           area loaded before the run record counts as whole as long as it
           can still be counted, exactly as it always did; only the one too
           big to count is offered to go on. */
        const resumable = deviceState?.run ? !deviceState.run.complete : here === null;
        if (resumable) {
            const resume = document.createElement('a');
            resume.className = 'button small item-load-button';
            resume.href = toMap('load=' + g.id);
            resume.append(icon('import'), document.createTextNode(t('Weiterladen')));
            buttons.append(resume);
        }
    } else {
        const load = document.createElement('a');
        load.className = 'button small item-load-button';
        load.href = toMap('load=' + g.id);
        // The same arrow as with the import — into the tray, here: onto the
        // device.
        load.append(icon('import'), document.createTextNode(t('Herunterladen')));
        buttons.append(load);
    }

    row.append(left, buttons);
    return row;
}

/* THE NAME LISTS USED TO BE CHOSEN HERE — four rows with a description, a
   source and a "keep current" checkbox. They are gone
   (19.08.2026): since all four arrive on their own and keep themselves
   current, there was nothing left to decide, and a page that explains a
   decision nobody has to make is worse than an empty one.

   NOTHING HANGS ON THIS having been removed. The fetching never sat here —
   map.js asks refreshSpeciesList() four seconds after loading, whether
   anybody ever opens this tab or not. What is gone is only the looking at
   it, and with it the possibility of taking a list off the device again;
   removePackage() stays in species.js for the day somebody wants that
   back. */

/* THE OWN POINTS USED TO BE REPORTED HERE — how many, in how many periods,
   how old the oldest piece was. The paragraph beneath said what the numbers
   were worth: "Nur zur Auskunft." The maintainer struck it on 19.08.2026, and
   rightly: a line that reports and offers nothing to do is read once out of
   curiosity and never again.

   The reconciling has always been the map's job and is untouched: on
   opening, the device compares its periods with the server and fetches what
   has changed. */

// ---------------------------------------------------------------------------
// Shared lists: the quick picks of the permission forms
//
// The three buttons ONLY SET CHECKBOXES; saving happens with the form. This
// way you see before saving what a "Stufe" concretely means — and can still
// flip individual boxes afterwards. "Mitarbeiten" is content without
// intervention: titles, notes and fields yes, moving/deleting no.
// ---------------------------------------------------------------------------

/**
 * Everything in ONE tab that has to be wired by hand.
 *
 * A FUNCTION OVER A SUBTREE, not four loops over the document (25.08.2026).
 * The tabs fetch their content on demand, so at load most of these
 * elements do not exist yet — they appear when somebody opens their tab,
 * and then nothing would have bound them: the share permission buttons
 * dead, the import's checkboxes without their live count, „Umbenennen"
 * without its window.
 *
 * Called once over the whole document at load, and once per fragment
 * afterwards. The self-attaching helpers (autoheight, tables, folds,
 * clearable) watch the document themselves and need nothing here;
 * dialogs and toast are called by loadTab, which knows the fragment.
 */
function wireTab(root = document) {
    for (const form of root.querySelectorAll('.share-permissions-form')) {
        form.addEventListener('click', (e) => {
            /* Attribute AND values English in one move — they had come apart:
               the markup said data-schnellwahl="inhalt", this line asked for
               'content', and "Mitarbeiten" cleared every box instead of
               selecting the content rights. Attribute and value travel in the
               same document, so there is no version seam to bridge. */
            const selection = e.target.closest('[data-quick-select]')?.dataset.quickSelect;
            if (!selection) return;
            // The rights only — „Alles" must not tick „gemeinsam" on the
            // side, that box is a decision of its own (below).
            for (const box of form.querySelectorAll('input[type="checkbox"][name="right[]"]')) {
                box.checked = selection === 'all'
                    || (selection === 'content' && box.dataset.group === 'content');
            }
            // Ticked from code, so no event of its own — the save button
            // below (quietsave.js) has to hear that the rights moved.
            form.dispatchEvent(new Event('change'));
        });

        /* GEMEINSAM BEARBEITEN ASKS ONCE MORE (22.09.2026): the
           box is a different thing from the rights beside it — after it the
           list is the group's, and a list of 1,800 finds handed to a circle
           by one stray tick is exactly the slip worth a question. The
           question says what happens, with the number. Unticking asks
           nothing: that is the way back. */
        const joint = form.querySelector('input[name="joint"]');
        joint?.addEventListener('change', async () => {
            if (!joint.checked) return;
            const n = Number(joint.dataset.points ?? 0);
            const yes = await confirm({
                title: t('Liste wirklich gemeinsam machen?'),
                text: n === 1
                    ? t('Sie trägt 1 Punkt. Sobald du die Rechte speicherst, gehört sie allen Mitgliedern der Freigabe: jeder bearbeitet, verschiebt und löscht jeden Punkt darin und setzt eigene hinein. Löschst du die Liste oder verlässt du die Freigabe, geht sie an ein anderes Mitglied über. Was die anderen ändern, bleibt — auch wenn du den Schalter später wieder ausstellst.')
                    : t('Sie trägt %d Punkte. Sobald du die Rechte speicherst, gehört sie allen Mitgliedern der Freigabe: jeder bearbeitet, verschiebt und löscht jeden Punkt darin und setzt eigene hinein. Löschst du die Liste oder verlässt du die Freigabe, geht sie an ein anderes Mitglied über. Was die anderen ändern, bleibt — auch wenn du den Schalter später wieder ausstellst.', n),
                button: t('Ja, gemeinsam'),
                danger: true,
            });
            if (!yes) {
                joint.checked = false;
                // Back as it was, and the save button (quietsave.js) should
                // know — unticking from code sends no event.
                form.dispatchEvent(new Event('change'));
            }
        });
    }
    
    // ---------------------------------------------------------------------------
    // Import: renaming via the name prompt
    //
    // One button per row, ONE hidden form for all (see import_content.php) — the
    // prompt yields the name, the form carries it to the server. Empty or
    // unchanged means: do nothing.
    // ---------------------------------------------------------------------------
    
    /* THE IMPORT NAME SURVIVES THE COLUMN ASSIGNMENT (25.08.2026).
       Since the name field moved to the head of the preview, it stands
       ABOVE a form that reloads the page — and it belongs to the apply form
       below, so „Zuordnung anwenden" left it behind. Measured: typed
       „Nordhang Altbestand", pressed the button, field empty.
    
       The assignment form carries a hidden twin; here it is filled at the
       moment of sending. Not a value written into the markup: what counts
       is what stands in the field NOW, not what stood there when the page
       was built. */
    for (const form of root.querySelectorAll('.import-interpretation')) {
        form.addEventListener('submit', () => {
            const field = document.getElementById('import-name');
            const twin  = document.getElementById('import-name-carried');
            if (field && twin) twin.value = field.value;
        });
    }
    
    /* THE BUTTON SAYS THAT IT HEARD THE CLICK (25.08.2026: „Wenn
       ich auf Einspielen klicke, dauert es ewig, ohne dass irgendwas tut.
       Nur der Browser symbolisiert mir, dass was passiert").
    
       NOT A MODAL, although he offered one. A window that says „bitte
       warten" is a second thing to look at and a thing to dismiss; the
       button one just pressed is where the eye already is. Same handle the
       map uses while it downloads tiles (getAreaTiles in map.js):
       disabled plus a changed word. Disabled also means it cannot be
       pressed twice — a second POST would apply the same file again.
    
       The exits go dead with it: past this point they no longer decide
       anything, and „Alle verwerfen" pressed mid-apply would throw away a
       queue whose head is already being written.
    
       Nothing has to switch it back. The answer to this POST is a whole new
       page. */
    for (const form of root.querySelectorAll('#import-apply-form')) {
        form.addEventListener('submit', () => {
            for (const button of root.querySelectorAll(
                     '#import-apply-form button, [form="import-skip-form"],'
                   + ' [form="import-discard-form"]')) {
                button.disabled = true;
            }
            /* :not([form]) — THE EXITS SIT INSIDE THIS FORM TOO. „Alle
               verwerfen" and „Diese Datei überspringen" are DOM children of
               the apply form (one row, right-aligned) and only belong
               elsewhere through their form attribute. Without this guard
               querySelector took the first submit button in document order,
               and since the row ends with „Einspielen" that was „Verwerfen"
               — measured: it read „spielt ein …" while the real button kept
               its word. */
            const apply = form.querySelector('button[type="submit"]:not([form])');
            if (apply) apply.textContent = t('spielt ein …');
        });
    }
    
    /* THE PREVIEW'S TABLE AND THE COUNT ABOVE IT (importrows.js): filters,
       the ticks of the conflicts, „alle einspielen", the questions that
       follow an answer, and the count that must not lie while one decides
       (25.08.2026: unticking two while „4 Funde würden angelegt" kept
       standing above was no detail — that line is why one reads the
       preview). It stops „Einspielen" in the capture phase, so an open
       question holds the click before the handler above greys the
       buttons out. */
    mountImportRows(root);
    
    for (const button of root.querySelectorAll('[data-import-rename]')) {
        button.addEventListener('click', async () => {
            const newName = await prompt({
                title: t('Import umbenennen'),
                label: t('Name (als Gedächtnisstütze)'),
                value: button.dataset.name,
                button: t('Umbenennen'),
            });
            if (newName === null || newName.trim() === '' || newName.trim() === button.dataset.name) return;
            const form = document.getElementById('import-rename-form');
            form.querySelector('[name="import"]').value = button.dataset.importRename;
            form.querySelector('[name="name"]').value = newName.trim();
            form.submit();
        });
    }
}

wireTab();

// ---------------------------------------------------------------------------
// The tabs fetch their content when somebody opens them
// ---------------------------------------------------------------------------

/* WHY AT ALL, measured on 25.08.2026 with 1.640 points: this page used to
   build every tab on every call — about a megabyte of markup, of which
   the Organisieren tab alone was 820 KB, its whole point stock as
   embedded JSON. Shipped even to somebody who came for the import.

   A section that waits carries an empty <div data-tab-src="…"> (see
   tab_content in administration.php). The one that just processed a form
   is NOT waiting: its answer lives in the state of that request and is
   rendered inline over there. */

const TAB_ENDPOINT = (window.START_DATA?.baseUrl ?? '') + 'pages/administration_tab.php?tab=';

/* WHAT THIS FILE ITSELF HAS TO BIND INTO FRESHLY ARRIVED MARKUP.
   Registering here does BOTH halves at once: it runs the binder now, for
   whatever the first page build already carries, and again for every
   fragment that arrives later.

   THE TRAP THIS CLOSES, and it caught two bindings on 25.08.2026 the day
   the tabs became lazy. Before that every tab stood in the first page
   build, so a plain IIFE at the bottom of this file found its fields and
   bound them. Since tab_content() the Einstellungen and Exkursionen
   markup arrives seconds later — the IIFE had long since run, found
   nothing, and returned without a word. The find-number preview stopped
   following the typing and the excursion's plus button stopped opening
   its window; nothing threw, nothing was logged, and both looked like a
   dead page rather than a missing listener.

   So: never reach into tab markup from an IIFE again. A binder takes its
   root and goes through here, and then it cannot forget the second half
   — which is the whole point of registering being SHORTER than writing
   the IIFE was. */
const tabBinders = [];
function onTabContent(bind) {
    tabBinders.push(bind);
    bind(document);
}

// „Direkt geteilt" under Gemeinsam — its ticks and doors (hints.js).
onTabContent(mountHints);

// „Freunde" under Bildarchive — its ticks and the filter window (archiveviewers.js).
onTabContent(mountArchiveViewers);

// „Arbeitsnamen" under Einstellungen → Arten — the one window for new and change (workingnames.js).
onTabContent(mountWorkingNames);

/* A BUTTON THAT OPENS A WINDOW of its tab (data-open-window="<id>") —
   „Gerät hinzufügen" under Geräte (28.09.2026). The window stands in the
   same section as its button, so it is visible whenever the button is.

   ITS „ABBRECHEN" IS type="button" WITH data-close-window, NEVER a submit
   with formmethod="dialog": Enter in a field presses the form's FIRST
   submit button, and in a footer that is the cancel on the left. Measured
   on the name window — a name typed, Enter, and the window simply closed.
   The cancel also empties the form, so the next opening starts clean. */
onTabContent((root) => {
    for (const button of root.querySelectorAll('[data-open-window]')) {
        button.addEventListener('click', () => {
            const win = document.getElementById(button.dataset.openWindow);
            win?.showModal();
            win?.querySelector('input:not([type="hidden"])')?.focus();
        });
    }
    for (const button of root.querySelectorAll('[data-close-window]')) {
        button.addEventListener('click', () => {
            button.form?.reset();
            button.closest('dialog')?.close();
        });
    }
});

/* A BUTTON THAT LEADS TO ANOTHER TAB (data-goto-tab="<anchor>") — „Zu den
   Cloud-Anbietern" in a Cloud tile that does not work yet (02.10.2026).
   A plain link to #cloud_providers would only change the hash on the same
   page, and tabs.js reads the hash once at load. So the button presses
   the tabs themselves, outermost first: Administration, then
   Cloud-Anbieter — each tab's panel tells which tab above it opens it. */
onTabContent((root) => {
    for (const button of root.querySelectorAll('[data-goto-tab]')) {
        button.addEventListener('click', () => {
            const chain = [];
            let tab = document.querySelector(`[role="tab"][data-anchor="${CSS.escape(button.dataset.gotoTab)}"]`);
            while (tab) {
                chain.unshift(tab);
                const panel = tab.closest('[role="tabpanel"]');
                tab = panel ? document.querySelector(`[role="tab"][aria-controls="${CSS.escape(panel.id)}"]`) : null;
            }
            for (const t of chain) t.click();
            window.scrollTo({ top: 0 });
        });
    }
});

/* THE PENCIL AT A DEVICE (Geräte, 28.09.2026): one window for every row
   of both lists, filled from the row's button BEFORE it opens — the save
   button measures on opening (data-quiet-until-changed="open"). A browser
   may be left without a name, its automatic label comes back; an app may
   not, so its field is required. */
onTabContent((root) => {
    for (const button of root.querySelectorAll('[data-device-rename]')) {
        button.addEventListener('click', () => {
            const win = document.getElementById('device-rename-window');
            if (!win) return;
            const field = win.querySelector('input[name="name"]');
            win.querySelector('input[name="id"]').value = button.dataset.id;
            field.value = button.dataset.name ?? '';
            field.placeholder = button.dataset.placeholder ?? '';
            field.required = 'required' in button.dataset;
            win.querySelector('[data-rename-hint]').textContent = button.dataset.hint ?? '';
            win.showModal();
            field.focus();
            field.select();
        });
    }
});

/* A WINDOW THE PAGE OPENS BY ITSELF (27.09.2026). Where a save
   rebuilds what the user sees — the DGfM consent turns its tab into
   „Meldung" and „Genauigkeit" — a line of text is read past; the server
   then renders a dialog[data-open-at-load] saying what is new. A button
   whose value names a tab's anchor opens that tab as it closes the window
   (form method="dialog" puts the value into returnValue).

   MOVED TO <body> FIRST: the dialog is rendered inside its tab's section,
   and at load that section may still be hidden — a modal whose ancestor
   is display:none shows nothing. */
onTabContent((root) => {
    for (const win of root.querySelectorAll('dialog[data-open-at-load]')) {
        win.removeAttribute('data-open-at-load');
        document.body.append(win);
        win.addEventListener('close', () => {
            const anchor = win.returnValue;
            if (!anchor) return;
            document.querySelector(`[role="tab"][data-anchor="${CSS.escape(anchor)}"]`)?.click();
            window.scrollTo(0, 0);
        });
        win.showModal();
    }
});

/** Is nothing hiding this box any more? Walks up for a hidden ancestor —
 *  the tabs are nested, so a section can be shown while its parent is
 *  not. */
const uncovered = (box) => box.isConnected && !box.closest('[hidden]');

async function loadTab(box) {
    const name = box.dataset.tabSrc;
    // Taken away first: a second event during the fetch would otherwise
    // start the same load again.
    delete box.dataset.tabSrc;
    box.innerHTML = '<p class="hint-text">' + t('Wird geladen …') + '</p>';

    let markup;
    try {
        const answer = await fetch(TAB_ENDPOINT + encodeURIComponent(name),
            { credentials: 'same-origin' });
        if (!answer.ok) throw new Error('HTTP ' + answer.status);
        markup = await answer.text();
        /* A WHOLE PAGE IS NOT A FRAGMENT. When the session has run out,
           login_required() answers with the login page — status 200, so
           answer.ok says yes —, and unchecked it would be poured into the
           tab: a login form inside the administration, with a second
           <head> and scripts of its own. Seen on the test bench, where
           there is no session by definition. */
        if (/^\s*(<!doctype|<html)/i.test(markup)) {
            throw new Error(t('keine Teilseite — vermutlich abgemeldet'));
        }
    } catch (e) {
        // Given back so a second attempt is possible — a tab that stays
        // empty for good would look like a bug in the page.
        box.innerHTML = '';
        box.dataset.tabSrc = name;
        toast(t('Der Bereich liess sich nicht laden. Noch einmal öffnen versucht es erneut — '
            + 'hilft das nicht, ist die Anmeldung abgelaufen.'), 'error');
        return;
    }

    box.innerHTML = markup;

    /* SCRIPTS DO NOT RUN FROM innerHTML — the browser parses them and
       leaves them lying. Two fragments carry a real one (Einstellungen
       and Papierkorb, both a <script type="module"> of their own), and
       without this they would simply do nothing: the trash would not ask
       before emptying. Replaced by a fresh element, which the browser
       then executes. Data blocks are skipped — #organize-data and
       #shared-data are JSON, not code, and re-creating them would only
       cost. */
    for (const old of box.querySelectorAll('script')) {
        if ((old.type || '').includes('json')) continue;
        const fresh = document.createElement('script');
        for (const a of old.attributes) fresh.setAttribute(a.name, a.value);
        fresh.textContent = old.textContent;
        old.replaceWith(fresh);
    }

    // What does not watch the document by itself, in the order of the
    // page: the confirmations, then the answers that are to float, then
    // this file's own handles, then the two modules that wait for a data
    // block.
    wireForms(box);
    liftNotices(box);
    wireTab(box);
    /* A FRAGMENT CAN BRING A TAB BAR OF ITS OWN — Einstellungen does, with
       Konto, Oberfläche and the rest. Without this its buttons do
       nothing. */
    mountTabs(box);
    mountShared();
    mountOrganize();
    // And this file's own, which until 25.08.2026 were nowhere — see
    // onTabContent above for what that cost.
    for (const bind of tabBinders) bind(box);
}

function loadUncovered() {
    for (const box of document.querySelectorAll('[data-tab-src]')) {
        if (uncovered(box)) loadTab(box);
    }
}

/* WHEN A TAB IS OPENED, tabs.js sets 'hidden' on the sections — and that
   is what is watched here, rather than binding to every tab button.
   Reason: the bars are nested and a button of the outer bar uncovers a
   whole group at once, so the attribute is the honest signal and the
   click only one of its causes. */
new MutationObserver(loadUncovered).observe(document.body,
    { subtree: true, attributes: true, attributeFilter: ['hidden'] });

/* THE FIRST SWEEP STANDS AT THE FOOT OF THIS FILE, not here — it would
   otherwise start loading tabs while the binders below have not yet
   registered, and the first tab of the visit would be the one that comes
   back unbound. It survives today only because loadTab awaits a fetch
   before it reads the list, and a rule that rests on an await landing
   late is not a rule. */


// ---------------------------------------------------------------------------
// Protected-area boundaries, by federal state
//
// A map of the sixteen since 14.11.2026 (drawProtected says why): a click
// fetches a state or takes it off the device. The unit is a state and not
// an outlined area, because that is how one is out and about.
// ---------------------------------------------------------------------------

const protectedSection = document.getElementById('protected-list');
if (protectedSection) drawProtected();

async function drawProtected() {
    if (!protectedAreas.registryEntry()) {
        protectedSection.replaceChildren(hint(
            t('Diese Anlage bietet keine Schutzgebiete an.')));
        return;
    }

    const here = await protectedAreas.stored();

    /* THE PROVIDER'S VINTAGE, ONCE AT THE TOP (22.08.2026).
       It is the same for the whole country — the service merges the
       states once a year — so it stands once above the map. Against it a
       state's own vintage says whether it is outdated (the warning edge).

       Awaited before the map is drawn: the request is one small document; a
       failure yields null and the line simply stays away. Never a
       placeholder that later turns into a number — that is how the area
       list once looked hung. */
    const currency = await protectedAreas.providerCurrency();

    protectedSection.replaceChildren();
    if (currency) protectedSection.append(currencyLine(currency));

    /* THE MAP IS THE WHOLE OF IT (14.11.2026: „eine
       Deutschlandkarte … die ich an- und abklicken kann", then „damit
       brauche ich die Tabelle gar nicht mehr … diese hässliche Tabelle,
       diese ewig lange abschaffen", and of the short list of loaded states
       that was left: „die brauchst du doch auch nicht … das wird doch in
       der Karte markiert"). A click on a state fetches it, a second takes
       it off the device (statesmap.js); what is here wears the accent.
       What the rows used to say went where it is still needed:
         - each state's count, size and vintage into its tooltip;
         - while a state loads, ONE line with its bar under the map, gone
           again when it is done („allerhöchstens … einen Statusbalken beim
           Laden, der dann wieder ausgeblendet wird") — no sum, no list;
         - a state the provider has newer data for wears a warning edge,
           and its tooltip says so; a click takes it off, a second fetches
           it fresh — no „Erneuern" button of its own;
         - a failure as a toast, a deed that did not work (CLAUDE.md). */
    const figure = document.createElement('figure');
    figure.className = 'protected-map';
    const map = statesMap(protectedAreas.STATES, (code) => {
        if (protectedRuns.has(code)) return;
        const state = protectedAreas.STATES.find(s => s.code === code);
        if (here.get(code)) protectedRemove(state);
        else protectedFetch(state);
    });
    for (const path of map.querySelectorAll('path')) {
        const code = path.dataset.state;
        const state = protectedAreas.STATES.find(s => s.code === code);
        const pack = here.get(code);
        const outdated = Boolean(pack) && isOutdated(pack.vintage, currency);
        path.classList.toggle('is-here', Boolean(pack));
        path.classList.toggle('is-outdated', outdated);
        path.classList.toggle('is-busy', protectedRuns.has(code));
        const words = protectedTooltip(state, pack, currency, outdated);
        path.querySelector('title').textContent = words;
        path.setAttribute('aria-label', words);
    }
    const source = document.createElement('figcaption');
    source.className = 'provider-currency';
    source.textContent = t('Umrisse: © GeoBasis-DE / BKG');
    figure.append(map, source);

    // The line under the map, only while something loads (protectedProgressShow).
    const status = document.createElement('div');
    status.className = 'protected-status';
    status.hidden = true;
    const line = document.createElement('p');
    line.className = 'hint-text protected-line';
    const progress = document.createElement('div');
    progress.className = 'area-progress';
    const bar = document.createElement('div');
    bar.className = 'area-bar';
    bar.append(document.createElement('div'));
    progress.append(bar);
    status.append(line, progress);

    protectedSection.append(figure, status);
    protectedProgressShow();
}

/** A state's tooltip: its name and what lies here of it. */
function protectedTooltip(state, pack, currency, outdated) {
    if (protectedRuns.has(state.code)) return t('%s — wird geladen …', state.name);
    if (!pack) return t('%s — nicht auf diesem Gerät. Klicken lädt es.', state.name);
    /* THE COUNT MAY BE MISSING. Whoever fetched a state before 20.08.2026
       has no summary written alongside, and computing it afterwards would
       mean unpacking the package — the very thing that froze the
       administration. Then only the size stands there. */
    const what = [pack.count !== null ? t('%s Gebiete', pack.count) : null,
        areas.readableSize(pack.bytes)].filter(Boolean).join(' · ');
    /* THE VINTAGE ONLY WHEN THE PACK CARRIES ONE: everything fetched before
       22.08.2026 has none, and inventing one is out of the question. */
    const when = pack.vintage
        ? ' · ' + t('Stand: %s', vintageText(pack.vintage))
            + (outdated ? t(' — beim Anbieter liegt Neueres') : currency ? t(' — aktuell') : '')
        : '';
    return t('%s — %s%s. Klicken löscht es vom Gerät.', state.name, what, when);
}

/* THE STATES BEING FETCHED, across redraws, with how far each is. A click
   on the map makes it easy to start three at once, and every finished one
   redraws the whole block — without this the ones still running would lose
   their bar and their busy colour on the map, and a second click would
   start them twice. */
const protectedRuns = new Map();

/** Fetch a state: the map breathes, the bar runs under it, and a failure
    is a toast — a deed that did not work (CLAUDE.md). */
async function protectedFetch(state) {
    if (protectedRuns.has(state.code)) return;
    protectedRuns.set(state.code, { name: state.name, text: t('%s wird geladen …', state.name), done: 0, total: 0 });
    await drawProtected();
    try {
        await protectedAreas.download(state.code, (text, done, total) => {
            protectedRuns.set(state.code, { name: state.name, text, done, total });
            protectedProgressShow();
        });
    } catch (error) {
        toast(t('%s ließ sich nicht laden: %s', state.name,
            error.message || t('Der Dienst hat nicht geantwortet.')), 'error');
    }
    protectedRuns.delete(state.code);
    await drawProtected();
}

async function protectedRemove(state) {
    await protectedAreas.remove(state.code);
    drawProtected();
}

/* The runs onto the line under the map, wherever the last redraw put it —
   the latest run's words, and with more than one the others named after it.
   WITHOUT A SHARE THE BAR RUNS, it does not stand at zero. That happens for
   real: the service worker hands out the modules one at a time
   (stale-while-revalidate, see sw.js), so right after a deployment this file
   can already ask for the share while protectedareas.js still only sends
   the sentence. A bar frozen at nought then says "broken", when the truth is
   "working, share unknown" — and the maintainer saw exactly that. */
function protectedProgressShow() {
    const runs = [...protectedRuns.values()];
    const status = protectedSection.querySelector('.protected-status');
    if (!status || runs.length === 0) return;
    const line = status.querySelector('.protected-line');
    const progress = status.querySelector('.area-progress');
    status.hidden = false;
    const run = runs[runs.length - 1];
    line.textContent = runs.length === 1
        ? run.text
        : t('%s (danach noch: %s)', run.text, runs.slice(0, -1).map(r => r.name).join(', '));
    progress.classList.toggle('is-indeterminate', !run.total);
    if (run.total) progress.querySelector('.area-bar > div').style.width = (run.done / run.total * 100).toFixed(0) + '%';
}

/** "Stand beim Anbieter: FFH-Gebiete 2019 · Nationalparke 2025 · …" —
    a footnote, not a third sentence of the explanation
    (23.09.2026: „ein Zusatz, der weniger wichtig ist"). */
function currencyLine(currency) {
    const p = hint(t('Stand beim Anbieter: %s', vintageText(currency)));
    p.classList.add('small', 'provider-currency');
    return p;
}

/** The years of one vintage as one readable line. */
function vintageText(vintage) {
    return Object.entries(vintage)
        .map(([label, year]) => `${label} ${year}`)
        .join(' · ');
}

/** Is anything in the stored vintage older than what the provider has? */
function isOutdated(mine, theirs) {
    if (!mine || !theirs) return false;
    return Object.entries(mine).some(([label, year]) => (theirs[label] ?? year) > year);
}

// ---------------------------------------------------------------------------
// The „Entfernen" tool's files (inpaint.js, 11.11.2026)
//
// One row in the shape of a state's above: what lies here, and the one
// button that answers it. Fetching from here goes through the same window
// as the first use in the photo window — the size and the „stays on your
// device" are said once, in one place.
// ---------------------------------------------------------------------------

const inpaintSection = document.getElementById('inpaint-tool');
if (inpaintSection) drawInpaint();

async function drawInpaint() {
    /* TWO ROWS SINCE 14.11.2026: the tool with its quick model, and the
       strong model beside it (inpaint.js, STRONG_FILES) — its own size, its
       own „Vom Gerät löschen", because 623 MB is what one most wants to be
       able to take off again without losing the tool. The strong model's
       row stands only where there is something to say: the server offers
       the model (data-strong), or a copy still lies on this device.
       Deleting the tool takes the strong model with it — it cannot run
       without the tool's files. */
    const [here, strong] = await Promise.all([inpaint.stored(), inpaint.storedStrong()]);
    const box = document.createElement('div');

    const rowOf = (words, state, total, fetch, forget) => {
        const row = document.createElement('div');
        row.className = 'item-row';
        const left = document.createElement('div');
        left.className = 'item-text';
        const name = document.createElement('div');
        name.className = 'item-name';
        name.textContent = words;
        const line = document.createElement('div');
        line.className = state.complete ? 'item-here' : 'item-data';
        /* A HALF DOWNLOAD SAYS SO. What came before a cut stays and is not
           fetched again; the row tells how much, and „Weiterladen" fetches
           the rest instead of pretending the device is empty. */
        line.textContent = state.complete
            ? t('%s auf diesem Gerät', inpaint.megabytes(state.bytes))
            : state.bytes > 0
                ? t('%s von %s geladen', inpaint.megabytes(state.bytes), inpaint.megabytes(total))
                : t('nicht auf diesem Gerät · %s', inpaint.megabytes(total));
        left.append(name, line);

        const buttons = document.createElement('div');
        buttons.className = 'item-buttons';
        if (!state.complete && fetch) {
            const get = document.createElement('button');
            get.type = 'button';
            get.className = 'button small subtle item-load-button';
            get.append(icon('import'), document.createTextNode(state.bytes > 0 ? t('Weiterladen') : t('Herunterladen')));
            get.addEventListener('click', async () => {
                await fetch();
                drawInpaint();
            });
            buttons.append(get);
        }
        if (state.bytes > 0) {
            const away = document.createElement('button');
            away.type = 'button';
            away.className = 'button subtle small item-load-button';
            away.append(icon('device-remove'), document.createTextNode(t('Vom Gerät löschen')));
            away.addEventListener('click', async () => {
                await forget();
                drawInpaint();
            });
            buttons.append(away);
        }
        row.append(left, buttons);
        return row;
    };

    box.append(rowOf(t('Entfernen'), here, inpaint.TOTAL_BYTES, () => inpaint.ensure(), () => inpaint.remove()));
    const offered = inpaintSection.dataset.strong === '1';
    if (offered || strong.bytes > 0) {
        box.append(rowOf(t('Entfernen – starkes Modell'), strong, inpaint.STRONG_BYTES,
            offered ? () => inpaint.ensureStrong() : null, () => inpaint.removeStrong()));
    }
    inpaintSection.replaceChildren(box);
}

// ---------------------------------------------------------------------------
// The full pictures kept at the desk (originalcache.js, 18.11.2026)
//
// What lies here and the button that takes it off, in the shape of the
// rows above; then the tick — keep at all — and, while it is set, the
// size as answers to choose among. Each change is the device's own and
// stands at once (settings bundle), so there is no save button.
// ---------------------------------------------------------------------------

const originalsSection = document.getElementById('originals-cache');
if (originalsSection) drawOriginals();

/** Bytes in the size one reads them out in — „1,2 GB", „350 MB", decimal as the choices are (originalcache.js). */
function bytesSaid(n) {
    if (n >= 1e9) return t('%s GB', (n / 1e9).toLocaleString(lang, { maximumFractionDigits: 1 }));
    if (n >= 1e6) return t('%s MB', Math.round(n / 1e6).toLocaleString(lang));
    return t('weniger als 1 MB');
}

function drawOriginals() {
    if (!originalCacheUsable()) {
        const p = document.createElement('p');
        p.className = 'hint-text';
        p.textContent = t('Auf diesem Gerät bleiben keine vollen Bilder liegen. Das geht nur am Computer.');
        originalsSection.replaceChildren(p);
        return;
    }
    const state = originalCacheState();
    const box = document.createElement('div');

    const row = document.createElement('div');
    row.className = 'item-row';
    const left = document.createElement('div');
    left.className = 'item-text';
    const name = document.createElement('div');
    name.className = 'item-name';
    name.textContent = t('Volle Bilder');
    const line = document.createElement('div');
    line.className = state.bytes > 0 ? 'item-here' : 'item-data';
    line.textContent = state.bytes > 0
        ? (state.count === 1 ? t('%s auf diesem Gerät · 1 Bild', bytesSaid(state.bytes))
            : t('%s auf diesem Gerät · %d Bilder', bytesSaid(state.bytes), state.count))
        : t('nichts auf diesem Gerät');
    left.append(name, line);
    const buttons = document.createElement('div');
    buttons.className = 'item-buttons';
    if (state.bytes > 0) {
        const away = document.createElement('button');
        away.type = 'button';
        away.className = 'button subtle small item-load-button';
        away.append(icon('device-remove'), document.createTextNode(t('Vom Gerät löschen')));
        away.addEventListener('click', async () => {
            await originalCacheClear();
            toast(t('Die vollen Bilder sind von diesem Gerät gelöscht.'));
            drawOriginals();
        });
        buttons.append(away);
    }
    row.append(left, buttons);
    box.append(row);

    const keep = document.createElement('label');
    keep.className = 'choice-row';
    const tick = document.createElement('input');
    tick.type = 'checkbox';
    tick.checked = originalKeeping();
    const words = document.createElement('span');
    words.textContent = t('Volle Bilder auf diesem Gerät behalten');
    keep.append(tick, words);
    tick.addEventListener('change', async () => {
        await originalKeepingSet(tick.checked);
        drawOriginals();
    });
    // In a field of its own, so it keeps the air of a setting from the row above.
    const keepField = document.createElement('div');
    keepField.className = 'field';
    keepField.append(keep);
    box.append(keepField);

    if (originalKeeping()) {
        const set = document.createElement('fieldset');
        set.className = 'field question';
        const legend = document.createElement('legend');
        legend.textContent = t('Wie viel Platz dürfen sie höchstens belegen?');
        set.append(legend);
        for (const size of ORIGINAL_BUDGETS) {
            const choice = document.createElement('label');
            choice.className = 'choice-row';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'originals-budget';
            radio.checked = size === originalBudget();
            radio.addEventListener('change', async () => {
                await originalBudgetSet(size);
                drawOriginals();
            });
            const said = document.createElement('span');
            said.textContent = bytesSaid(size);
            choice.append(radio, said);
            set.append(choice);
        }
        box.append(set);
    }
    originalsSection.replaceChildren(box);
}

// ---------------------------------------------------------------------------
// The find-number preview — live while somebody types
// (23.08.2026: "Dein nächster Fundpunkt sieht so aus" — since the
// plain-words pass „Dein nächster Fund bekommt diese Nummer").
//
// THE ARITHMETIC IS THE BROWSER TWIN (findnumber.js) of the server's
// renderer; the server draws the same line on load, this here only keeps
// it current between keystrokes. When a written-out number stands in the
// pattern, the preview already counts on past it — the typed one is the
// last given, the next find is what the line promises — and says on the
// side what saving will make of the pattern.
// ---------------------------------------------------------------------------

onTabContent(function bindFindNumberPreview(root) {
    /* ROOT, NOT document: the fields arrive with the Einstellungen
       fragment, and this runs once for the page and once per fragment
       (onTabContent). Asking the document would bind a second listener to
       the same field every time any OTHER tab is fetched. */
    const patternField = root.querySelector('#settings-find-pattern');
    const preview      = root.querySelector('#find-number-preview');
    const fallback     = root.querySelector('#find-number-preview-fallback');
    const note         = root.querySelector('#find-number-preview-note');
    const restartRow   = root.querySelector('#find-number-restart-row');
    const restartUnit  = root.querySelector('#find-number-restart-unit');
    const restartBox   = root.querySelector('#find-number-restart');
    const row          = preview?.closest('.find-number-preview-row');
    if (!patternField || !preview || !row) return;

    /* WHAT THE NEXT FIND OF TODAY WOULD DRAW — the twin of
       find_number_upcoming() and of the save's rule for the section. The
       floor is the counter as it stands, but 1 where a restart that was
       on already meets a section it has not counted in (a new year, a new
       morning); a restart ticked just now begins in today's section with
       the counter as it is. Above the floor stands the highest number
       already given — the mint goes on past it (find_number_highest). The
       server counted those for the SAVED pattern only, so they count
       while the typed one keeps that shape; a new shape has none yet.
       A written-out number is the floor itself: the last one given, the
       next counts on past it. */
    const savedPattern = patternField.defaultValue.trim();
    function upcoming(pattern, stated) {
        let floor = stated ?? Math.max(1, parseInt(row.dataset.counter, 10) || 1);
        const sectioned = restartBox?.checked === true && periodUnit(pattern) !== null;
        if (stated === null && sectioned && row.dataset.restart === '1') {
            const key = periodKey(pattern, new Date());
            if (key !== row.dataset.period) floor = 1;
        }
        const highest = pattern !== savedPattern ? 0
            : parseInt(sectioned ? row.dataset.highestSection : row.dataset.highestAll, 10) || 0;
        return Math.max(floor, highest + 1);
    }

    function draw() {
        note.textContent = '';
        // The number in its big type — or, without a pattern, the sentence
        // with YASAIMAPP's example in it (the markup's comment says why).
        preview.hidden = false;
        if (fallback) fallback.hidden = true;
        const normalized = normalizePattern(patternField.value);

        /* The restart row follows the pattern live, like the preview: it
           only stands there while a date part gives the numbers a
           section, and the unit word names the finest one — the same
           reading as find_number_period() on the server. */
        if (restartRow && restartUnit) {
            const unit = periodUnit(normalized?.pattern ?? patternField.value);
            restartRow.hidden = unit === null;
            if (unit !== null) restartUnit.textContent = t(unit);
        }

        // YASAIMAPP's own mark (find_number_fallback) — saving refuses it,
        // so the preview says so while typing.
        if (findNumberReserved(patternField.value)) {
            preview.textContent = '?';
            note.textContent = t(' — ein Muster darf nicht mit „Y-" anfangen.');
            return;
        }
        if (normalized === null) {
            preview.textContent = '?';
            note.textContent = t(' — im Muster fehlt noch die laufende Nummer, zum Beispiel NNNN.');
            return;
        }
        if (normalized.pattern === '') {
            preview.hidden = true;
            if (fallback) fallback.hidden = false;
            return;
        }

        const n = upcoming(normalized.pattern, normalized.next);
        preview.textContent = renderFindNumber(normalized.pattern, new Date(), n);
    }

    patternField.addEventListener('input', draw);
    restartBox?.addEventListener('change', draw);
});

// ---------------------------------------------------------------------------
// Excursions (Gemeinsam → Exkursionen): only the window openers live
// here — creating, saving and deleting are classic POSTs of the forms in
// excursions_content.php, and the delete confirmation comes from
// data-confirm (dialogs.js) like everywhere else.
// ---------------------------------------------------------------------------

onTabContent((root) => {
    /* THE BINDINGS ASK root, THE HANDLERS ASK document — and the
       difference matters. A binding must only ever see the markup that
       just arrived, or the same button collects a listener per fetched
       tab. A handler runs on click, when the dialog it opens is long
       since part of the page; looking it up by id there is right. */
    const plus = root.querySelector('#excursion-plus');
    if (plus) {
        plus.addEventListener('click', () =>
            document.getElementById('excursion-new-window').showModal());
    }
    for (const button of root.querySelectorAll('.excursion-edit')) {
        button.addEventListener('click', () =>
            document.getElementById(button.dataset.window).showModal());
    }
    for (const button of root.querySelectorAll('.excursion-cancel')) {
        button.addEventListener('click', () => button.closest('dialog').close());
    }

    /* THE OFFLINE MAP'S SIZE, while picking (24.08.2026): the
       leader chooses area and zoom, so he should see what he is asking of
       other people's data plans — and the guest's page can then name a
       figure without walking the tile grid on a phone. Computed from the
       same functions the download uses (areas.js), so estimate and
       reality speak one language. */
    for (const picker of root.querySelectorAll('.excursion-area-picker')) {
        const prefix = picker.dataset.prefix;
        const el = (suffix) => document.getElementById(prefix + suffix);
        const zoom = el('-area-zoom');
        const sizeLine = el('-area-size');
        const zoomField = el('-area-zoom-field');

        const draw = () => {
            const option = picker.selectedOptions[0];
            const raw = option?.dataset.corners;
            if (!raw || picker.value === '0') {
                zoomField.hidden = true;
                el('-area-tiles').value = '0';
                el('-area-bytes').value = '0';
                return;
            }
            zoomField.hidden = false;
            let corners;
            try {
                corners = JSON.parse(raw);
            } catch {
                sizeLine.textContent = t('Die Umrandung dieses Gebiets ist unlesbar.');
                return;
            }
            const tiles = areas.tileList(corners, Number(zoom.value)).length;
            const bytes = areas.estimateSize(tiles);
            el('-area-tiles').value = String(tiles);
            el('-area-bytes').value = String(bytes);
            sizeLine.textContent =
                t('Etwa %s je Kartenart — %s Kacheln.', areas.readableSize(bytes), tiles.toLocaleString('de-DE'));
        };

        picker.addEventListener('change', draw);
        zoom.addEventListener('change', draw);
        draw();
    }

    /* The QR window. The drawing library (assets/vendor/qrcode) is
       fetched lazily on the FIRST opening — most visits to the tab never
       need it. If it is missing on the server, the window says so and
       the link below does the same job in words: the code is chrome, the
       address is the substance. */
    let libraryLoading = null;
    function loadQrLibrary(src) {
        libraryLoading ??= new Promise((done) => {
            const s = document.createElement('script');
            s.src = src;
            s.onload = done;
            s.onerror = done;
            document.head.append(s);
        });
        return libraryLoading;
    }
    /* THE PAIRING QR DRAWS ITSELF, without a button in front of it: it
       stands in a block that only exists in the one page build right after
       pairing (settings_content.php), and there it is the point of the
       block — a button would be a step between the user and the thing they
       just asked for. The excursion's code sits behind a button because
       its window opens on demand and may never be needed. */
    const pairBox = root.querySelector('#pair-qr');
    if (pairBox) {
        (async () => {
            await loadQrLibrary(pairBox.dataset.lib);
            if (!window.qrcode) {
                pairBox.textContent = t('Die QR-Bibliothek fehlt auf dem Server — nimm einen der beiden Wege daneben.');
                return;
            }
            const qr = window.qrcode(0, 'M');
            qr.addData(pairBox.dataset.link);
            qr.make();
            pairBox.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
        })();

        /* THE KEY WINDOW IS LEFT THROUGH „FERTIG". A stray Escape would
           drop a key that is shown only once, before it reached the app,
           so the first one is refused. Should the window close anyway
           (Chrome lets a second Escape through), it goes where „Fertig"
           goes: the page under it answers a post, and a reload would
           pair the device a second time. */
        const win = pairBox.closest('dialog');
        win?.addEventListener('cancel', (e) => e.preventDefault());
        win?.addEventListener('close', () => {
            const done = win.querySelector('.dialog-footer a[href]');
            if (done) location.replace(done.href);
        });
    }

    /* COPY, WITH A FALLBACK THAT IS NOT A DEAD END. navigator.clipboard
       needs a secure context — over plain http (a local test server) it is
       simply absent. Selecting the field then costs the user one Ctrl+C
       and works everywhere; the same two-step stands at the coordinate
       field in map.js. */
    for (const button of root.querySelectorAll('[data-copy]')) {
        button.addEventListener('click', async () => {
            const field = document.querySelector(button.dataset.copy);
            if (!field) return;
            /* INSIDE A WINDOW THE BUTTON ANSWERS ITSELF: a toast lies behind
               a modal and would say its word to nobody — the pairing key
               sits in a window since 28.09.2026. */
            const inWindow = button.closest('dialog[open]') !== null;
            const say = (text, kind) => {
                if (!inWindow) { toast(text, kind); return; }
                button.dataset.label ??= button.innerHTML;
                button.textContent = text;
                setTimeout(() => { button.innerHTML = button.dataset.label; }, 2500);
            };
            try {
                await navigator.clipboard.writeText(field.value);
                say(t('Kopiert.'));
            } catch {
                field.focus();
                field.select();
                // Neutral wording: the same handler serves the pairing key AND the
                // invitation's code and link (12.09.2026).
                say(t('Kopieren ging nicht — das Feld ist markiert, Strg+C genügt.'), 'warning');
            }
        });
    }

    for (const button of document.querySelectorAll('.excursion-qr')) {
        button.addEventListener('click', async () => {
            const window_ = document.getElementById(button.dataset.window);
            window_.showModal();
            const box = window_.querySelector('.excursion-qr-code');
            if (box.dataset.done) return;
            await loadQrLibrary(box.dataset.lib);
            if (!window.qrcode) {
                box.textContent = t('Die QR-Bibliothek fehlt auf dem Server — der Link darunter tut denselben Dienst.');
                return;
            }
            // Type 0 = pick the smallest that fits; 'M' corrects a
            // smudged phone display.
            const qr = window.qrcode(0, 'M');
            qr.addData(box.dataset.link);
            qr.make();
            box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
            box.dataset.done = '1';
        });
    }
});

/* THE FIRST SWEEP, now that every binder above has registered. Standing up
   with the observer it would have raced them — see there. */
loadUncovered();

/* GEMEINSAM → GETEILT: „Nach Listen" / „Nach Freunden"
   (09.10.2026) — the same rows of „Von dir geteilt", turned round. Here and
   not inline in the fragment: a lazily fetched tab arrives as innerHTML,
   and a <script> inside it never runs. */
onTabContent((root) => {
    for (const radio of root.querySelectorAll('input[name="given-order"]')) {
        radio.addEventListener('change', () => {
            for (const frame of root.querySelectorAll('[data-given]')) {
                frame.hidden = frame.dataset.given !== radio.value;
            }
        });
    }
});
