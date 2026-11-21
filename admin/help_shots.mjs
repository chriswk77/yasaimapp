/**
 * Shoot the pictures of the help page — pages/help.php reads them out of
 * assets/help/ and prints nothing where one is missing.
 *
 *   node admin/help_shots.mjs            all seven
 *   node admin/help_shots.mjs karte      one of them
 *
 * WHY A TOOL AND NOT A HAND (15.09.2026: „das Anlegen der
 * Hilfe hat nicht geklappt, es gibt keine Screenshots"). The help was
 * built on 26.08.2026 with its texts and legends, and the pictures were
 * left for later — and later never came, because a picture shot by hand
 * has to be shot again after every changed button, and nobody does that
 * twice. This tool does: the same seven views, the same width, the same
 * fixture, on every run. Whoever moves a button reruns it and checks the
 * marks in help.php.
 *
 * IT DRIVES THE CHROME ON THE MACHINE through the DevTools protocol, the
 * way tests/neighbours.mjs does, and needs the same three things: the
 * local server, Chrome in one of the usual places, and the demodata
 * fixture (php admin/demodata.php --new) — it shoots anna's stock, which
 * has one of everything the pictures need (a heading list with sublists,
 * a shared container, an area with an outline).
 *
 * NO PASSWORD PASSES THROUGH HERE. The session is SEEDED: a session file
 * with anna's id is written where PHP keeps them, and the cookie is set on
 * the browser — the same move the local measuring sessions make by hand.
 * MAP_SESSIONS names the directory (MAMP's by default).
 *
 * 1200 PIXELS WIDE, and the picture is CLIPPED to the piece that the
 * section is about — a window, the sidebar, one tab — so a button stays
 * readable at the column width the help has. PNG, because a screenshot
 * of an interface is lines and text, and JPEG smears both.
 */

import { spawn, execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync, mkdtempSync, rmSync, unlinkSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT     = join(dirname(fileURLToPath(import.meta.url)), '..');
const OUT      = join(ROOT, 'assets', 'help');
const BASE     = (process.env.MAP_URL ?? 'http://localhost:8888/map/').replace(/\/?$/, '/');
const SESSIONS = process.env.MAP_SESSIONS ?? '/Applications/MAMP/tmp/php';
const WIDTH    = 1200;
const HEIGHT   = 800;

const CHROMES = [
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/Applications/Chromium.app/Contents/MacOS/Chromium',
    '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
];

function stop(text) {
    console.error('\n  ' + text + '\n');
    process.exit(1);
}

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

/* THE SHOTS. Each names its page, a preparation that runs inside the page
   and returns the clip — the bounding box of the piece to keep, or null
   for the whole viewport — and a settling time. The preparations click
   through the interface the way a hand would; a selector that goes
   missing throws, and the run says which picture it could not take. */
/* Opening the filter on the „Punkte" tab — pasted into the shots above. */
const filterOpen = `const tab = [...document.querySelectorAll('[role="tab"]')].find(t => /^Punkte/.test(t.textContent.trim()));
            if (!tab) throw new Error('the „Punkte" tab is missing');
            tab.click();
            await new Promise(r => setTimeout(r, 700));
            const open = document.getElementById('filter-open');
            const section = document.getElementById('filter-section');
            if (section.hidden) open.click();
            // A little wider than the default sidebar, so a filter line like
            // „Boletus edulis, Boletus reticulatus" is not cut off.
            document.documentElement.style.setProperty('--sidebar-width', '420px');
            // The filter section scrolls inside the sidebar; for the picture
            // every box on the way up may grow, so a tall group is captured
            // whole instead of the list behind it.
            for (let el = section; el && el !== document.body; el = el.parentElement) {
                el.style.maxHeight = 'none'; el.style.height = 'auto'; el.style.overflow = 'visible';
            }
            document.body.style.overflow = 'visible'; document.body.style.height = 'auto';
            await new Promise(r => setTimeout(r, 400));
            document.activeElement?.blur();`;

/* The text group with the given rows typed in — each row a text, and
   `not` for the ones that read „enthält nicht". The type button opens a
   menu, so the switch is a click on the button and one on the entry. */
const filterTextShot = (rows) => ({ page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="text"]');
            if (!group) throw new Error('group text missing');
            // The text group stands open by itself — a click would fold it.
            await new Promise(r => setTimeout(r, 400));
            const wanted = ${JSON.stringify(rows)};
            for (const row of wanted) {
                const rows = [...group.querySelectorAll('.filter-row')];
                const field = rows[rows.length - 1].querySelector('input');
                field.value = row.text;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                await new Promise(r => setTimeout(r, 200));
            }
            for (const [n, row] of wanted.entries()) {
                if (!row.not) continue;
                [...group.querySelectorAll('.filter-row')][n].querySelector('.filter-row-type')?.click();
                await new Promise(r => setTimeout(r, 400));
                [...document.querySelectorAll('.popup-menu-entry')].find(b => /enthält nicht/.test(b.textContent))?.click();
                await new Promise(r => setTimeout(r, 400));
            }
            document.activeElement?.blur();
            return ${aired}(${box}(group), 16, 10);
        })()` });

const box = `(el => { const r = el.getBoundingClientRect(); return { x: r.left, y: r.top, width: r.width, height: r.height }; })`;
/* A box with air around it — for a picture of one filter group, which
   sat edge to edge in its frame (17.09.2026: „ziemlich eng
   umrandet … ein bisschen mehr Platz zu den Elementen und zum äußeren
   Rahmen, jedenfalls nach rechts und links"). THE AIR STOPS AT THE
   FILTER WINDOW: the first cut reached into the sidebar's own ground,
   which is another tone, and the group stood in a two-coloured frame
   („wir haben da eine andere Farbe außenrum … nur so weit erweitern,
   dass einfach das Filterfenster angezeigt wird"). */
const aired = `((b, dx, dy) => {
    const win = document.getElementById('filter-section').getBoundingClientRect();
    const x = Math.max(win.left, b.x - dx), right = Math.min(win.right, b.x + b.width + dx);
    const y = Math.max(win.top, b.y - dy), bottom = Math.min(win.bottom, b.y + b.height + dy);
    return { x, y, width: right - x, height: bottom - y };
})`;

const SHOTS = {
    // The map with the sidebar: lists, points, the tools. The friendship
    // badges of the fixture are dismissed — they are news, not the map.
    // WITH THE POINT WINDOW OPEN (15.09.2026): the overview
    // shows the three parts — sidebar left, map, point window right.
    karte: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            document.getElementById('sidebar-expand')?.click();
            for (const b of document.querySelectorAll('button')) {
                if (/^Gelesen/.test(b.getAttribute('aria-label') ?? b.title ?? '')) b.click();
            }
            const row = [...document.querySelectorAll('.point-row')]
                .find(r => r.textContent.includes('Boletus edulis'));
            if (!row) throw new Error('point row „Boletus edulis" not found');
            (row.querySelector('button, .point-row-content') ?? row).click();
            document.activeElement?.blur();
            await new Promise(r => setTimeout(r, 1200));
            // THE SIDEBAR IN ITS SIMPLEST FORM (15.09.2026: „erstmal
            // ohne Geteilt"): the shared block and everything after it goes,
            // for the picture only — the overview shows one's own lists.
            const lists = document.getElementById('list-section');
            const kids = [...lists.children];
            const at = kids.findIndex(el => /Geteilt/.test(el.textContent));
            if (at >= 0) for (const el of kids.slice(at)) el.remove();
            return null;
        })()` },

    // A point's window with its fields — TWICE, at two heights
    // (18.09.2026: „der Hauptscreenshot, der nur Kopfzeile, Reiter und
    // Fußzeile zeigt, ist zu hoch — der kann ruhig eine Scrollbar zeigen").
    // punkt-rahmen is the frame for 1.7: short, the fields cut off below,
    // because head, tabs and footer are its subject. punkt is the Text tab
    // for 1.7.1: tall enough for Beleg-Nr. and Notiz to be in the picture.
    ...Object.fromEntries([['punkt-rahmen', 560], ['punkt', 1400]].map(([file, height]) => [file,
    { page: 'index.php', wait: 3000, viewport: { width: 1200, height }, prep: `
        (async () => {
            const row = [...document.querySelectorAll('.point-row')]
                .find(r => r.textContent.includes('Boletus edulis'));
            if (!row) throw new Error('point row „Boletus edulis" not found');
            (row.querySelector('button, .point-row-content') ?? row).click();
            await new Promise(r => setTimeout(r, 900));
            document.activeElement?.blur();
            const panel = document.getElementById('detail');
            if (!panel || panel.hidden) throw new Error('the point window did not open');
            return ${box}(panel);
        })()` }])),

    // THE OTHER TWO TABS of the same window (18.09.2026, help
    // 1.7: „von jedem Tab einen Screenshot") — Fotos with the gallery in
    // its motif groups and the sequences (Belege), Karte with list and outline.
    ...Object.fromEntries([['punkt-fotos', 'd-tab-photos'], ['punkt-karte', 'd-tab-place']].map(([file, tab]) => [file,
    { page: 'index.php', wait: 3000, viewport: { width: 1200, height: tab === 'd-tab-place' ? 760 : 1100 }, prep: `
        (async () => {
            const row = [...document.querySelectorAll('.point-row')]
                .find(r => r.textContent.includes('Boletus edulis'));
            if (!row) throw new Error('point row „Boletus edulis" not found');
            (row.querySelector('button, .point-row-content') ?? row).click();
            await new Promise(r => setTimeout(r, 900));
            document.getElementById(${JSON.stringify(tab)}).click();
            document.activeElement?.blur();
            await new Promise(r => setTimeout(r, 1200));
            const panel = document.getElementById('detail');
            if (!panel || panel.hidden) throw new Error('the point window did not open');
            return ${box}(panel);
        })()` }])),

    // A FOREIGN FIND — ben's „Hallimasch am Stubben", as anna sees it
    // through his share: the origin line, the grey fields, no save button.
    // His list has to be switched on first, or the row is not in the tab.
    'punkt-fremd': { page: 'index.php', wait: 3000, viewport: { width: 1200, height: 560 }, settings: (ids) => ({ visibleShared: [ids.bens] }), prep: `
        (async () => {
            const tab = [...document.querySelectorAll('[role="tab"]')].find(t => /^Punkte/.test(t.textContent.trim()));
            tab?.click();
            await new Promise(r => setTimeout(r, 700));
            const row = [...document.querySelectorAll('.point-row')]
                .find(r => r.textContent.includes('Hallimasch'));
            if (!row) throw new Error('point row „Hallimasch" not found');
            row.click();
            await new Promise(r => setTimeout(r, 1200));
            document.activeElement?.blur();
            const panel = document.getElementById('detail');
            if (!panel || panel.hidden) throw new Error('the point window did not open');
            return ${box}(panel);
        })()` },

    // THE WAY IN ON THE PHONE (18.09.2026: „das ist ja jetzt
    // etwas versteckt"): a row in the sidebar shows the point on the map
    // with the peek line — and only the pencil on it opens the window.
    // Two pictures: the peek, and the window it opens. Whole viewport,
    // because on the phone both ARE the whole screen.
    ...Object.fromEntries([['punkt-handy-peek', false], ['punkt-handy', true]].map(([file, open]) => [file,
    { page: 'index.php', wait: 3000, viewport: { width: 375, height: 812 }, prep: `
        (async () => {
            for (const b of document.querySelectorAll('button')) {
                if (/^Gelesen/.test(b.getAttribute('aria-label') ?? b.title ?? '')) b.click();
            }
            document.getElementById('sidebar-expand')?.click();
            await new Promise(r => setTimeout(r, 700));
            const tab = [...document.querySelectorAll('[role="tab"]')].find(t => /^Punkte/.test(t.textContent.trim()));
            tab?.click();
            await new Promise(r => setTimeout(r, 800));
            const row = [...document.querySelectorAll('.point-row')].find(r => r.textContent.includes('Boletus edulis'));
            if (!row) throw new Error('point row „Boletus edulis" not found');
            row.click();
            document.activeElement?.blur();
            await new Promise(r => setTimeout(r, 1500));
            if (document.getElementById('point-peek').hidden) throw new Error('the peek did not show');
            if (${open}) {
                document.getElementById('point-peek-open').click();
                await new Promise(r => setTimeout(r, 1200));
                document.activeElement?.blur();
                const panel = document.getElementById('detail');
                if (!panel || panel.hidden) throw new Error('the point window did not open');
            }
            return null;
        })()` }])),

    // MOVING ON THE PHONE (18.09.2026: „am Handy müssen wir
    // auch den Zeigen-Button erklären, denn das ist dort der einzige Weg,
    // um den Punkt zu verschieben"): „Zeigen" steps the window aside, the
    // bar names the coordinate, the marker can be dragged.
    'punkt-handy-move': { page: 'index.php', wait: 3000, viewport: { width: 375, height: 812 }, prep: `
        (async () => {
            for (const b of document.querySelectorAll('button')) {
                if (/^Gelesen/.test(b.getAttribute('aria-label') ?? b.title ?? '')) b.click();
            }
            document.getElementById('sidebar-expand')?.click();
            await new Promise(r => setTimeout(r, 700));
            const tab = [...document.querySelectorAll('[role="tab"]')].find(t => /^Punkte/.test(t.textContent.trim()));
            tab?.click();
            await new Promise(r => setTimeout(r, 800));
            const row = [...document.querySelectorAll('.point-row')].find(r => r.textContent.includes('Boletus edulis'));
            if (!row) throw new Error('point row „Boletus edulis" not found');
            row.click();
            await new Promise(r => setTimeout(r, 1500));
            document.getElementById('point-peek-open').click();
            await new Promise(r => setTimeout(r, 1200));
            document.getElementById('d-center').click();
            await new Promise(r => setTimeout(r, 1500));
            document.activeElement?.blur();
            if (document.getElementById('move-bar').hidden) throw new Error('the move bar did not show');
            return null;
        })()` },

    // The filter groups, opened. They live on the sidebar's „Punkte" page,
    // so that tab comes first — the pages scroll sideways, and the clip is
    // measured only once the section stands still.
    // THE FILTER, folded: the seven groups as headings — the overview of
    // 1.6.2.2.1. The helpers below open the section on the „Punkte" tab.
    filter: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            await new Promise(r => setTimeout(r, 400));
            return ${box}(section);
        })()` },

    // ONE GROUP EACH, opened alone. The text group is pictured twice,
    // one example per picture (17.09.2026: „die dürfen nicht
    // zusammen in einem Screenshot vorkommen" — a search for the two
    // boletes excludes every morel by itself, so the pair would show
    // nothing): one row „Boletus edulis, Boletus reticulatus" (either),
    // and one row set to „enthält nicht" with „Morchella, Verpa" (neither).
    'filter-text':   filterTextShot([]),   // the group at rest, one empty row
    'filter-text-1': filterTextShot([{ text: 'Boletus edulis, Boletus reticulatus' }]),
    'filter-text-2': filterTextShot([{ text: 'Morchella, Verpa', not: true }]),
    'filter-time': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="time"]');
            if (!group) throw new Error('group time missing');
            // Open it only if it is folded — the text group stands open by
            // itself, and a click would fold it.
            const title = group.querySelector('.filter-title');
            if (group.dataset.group !== 'text') title?.click();
            await new Promise(r => setTimeout(r, 400));
            
            return ${aired}(${box}(group), 16, 10);
        })()` },
    'filter-place': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="place"]');
            if (!group) throw new Error('group place missing');
            // Open it only if it is folded — the text group stands open by
            // itself, and a click would fold it.
            const title = group.querySelector('.filter-title');
            if (group.dataset.group !== 'text') title?.click();
            await new Promise(r => setTimeout(r, 400));
            
            return ${aired}(${box}(group), 16, 10);
        })()` },
    /* DRAWING AN AREA (18.09.2026: „das ist eine Hauptfunktion,
       die ich oft nutze — das Bereich zeichnen muss hervorgehoben werden").
       The same steps up to the button, then four corners into the map and
       the pointer moved on; the first picture is the map with the drawing
       bar, the second the place group after „Fertig". */
    'filter-area-drawing': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="place"]');
            group.querySelector('.filter-title')?.click();
            await new Promise(r => setTimeout(r, 400));
            group.querySelector('.area-filter-draw').click();
            await new Promise(r => setTimeout(r, 700));
            document.getElementById('map-badges')?.remove();
            return { clicks: [{ x: 420, y: 300 }, { x: 700, y: 260 }, { x: 760, y: 470 }, { x: 520, y: 520 }], move: { x: 380, y: 430 } };
        })()`,
        after: `
        (async () => {
            await new Promise(r => setTimeout(r, 300));
            const bar = ${box}(document.getElementById('draw-bar'));
            return { x: bar.x, y: bar.y, width: bar.width, height: 600 - bar.y };
        })()` },
    'filter-area-done': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="place"]');
            group.querySelector('.filter-title')?.click();
            await new Promise(r => setTimeout(r, 400));
            group.querySelector('.area-filter-draw').click();
            await new Promise(r => setTimeout(r, 700));
            return { clicks: [{ x: 420, y: 300 }, { x: 700, y: 260 }, { x: 760, y: 470 }, { x: 520, y: 520 }] };
        })()`,
        after: `
        (async () => {
            document.getElementById('draw-done').click();
            await new Promise(r => setTimeout(r, 900));
            document.activeElement?.blur();
            const section = document.getElementById('filter-section');
            const group = section.querySelector('.filter-group[data-group="place"]');
            return ${aired}(${box}(group), 16, 10);
        })()` },
    'filter-elevation': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="elevation"]');
            if (!group) throw new Error('group elevation missing');
            // Open it only if it is folded — the text group stands open by
            // itself, and a click would fold it.
            const title = group.querySelector('.filter-title');
            if (group.dataset.group !== 'text') title?.click();
            await new Promise(r => setTimeout(r, 400));
            
            return ${aired}(${box}(group), 16, 10);
        })()` },
    'filter-identification': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="identification"]');
            if (!group) throw new Error('group identification missing');
            // Open it only if it is folded — the text group stands open by
            // itself, and a click would fold it.
            const title = group.querySelector('.filter-title');
            if (group.dataset.group !== 'text') title?.click();
            await new Promise(r => setTimeout(r, 400));
            
            return ${aired}(${box}(group), 16, 10);
        })()` },
    'filter-taxonomy': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="taxonomy"]');
            if (!group) throw new Error('group taxonomy missing');
            // Open it only if it is folded — the text group stands open by
            // itself, and a click would fold it.
            const title = group.querySelector('.filter-title');
            if (group.dataset.group !== 'text') title?.click();
            await new Promise(r => setTimeout(r, 400));
            
            return ${aired}(${box}(group), 16, 10);
        })()` },
    'filter-red-list': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="red-list"]');
            if (!group) throw new Error('group red-list missing');
            // Open it only if it is folded — the text group stands open by
            // itself, and a click would fold it.
            const title = group.querySelector('.filter-title');
            if (group.dataset.group !== 'text') title?.click();
            await new Promise(r => setTimeout(r, 400));
            
            return ${aired}(${box}(group), 16, 10);
        })()` },
    'filter-edibility': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            ${filterOpen}
            for (const h of section.querySelectorAll('.filter-title[aria-expanded="true"]')) h.click();
            const group = section.querySelector('.filter-group[data-group="edibility"]');
            if (!group) throw new Error('group edibility missing');
            // Open it only if it is folded — the text group stands open by
            // itself, and a click would fold it.
            const title = group.querySelector('.filter-title');
            if (group.dataset.group !== 'text') title?.click();
            await new Promise(r => setTimeout(r, 400));
            
            return ${aired}(${box}(group), 16, 10);
        })()` },

    // THE WINDOW AFTER „PUNKT SETZEN": tour name, „Neue Tour", the lists.
    // A real click on the map is needed — Leaflet listens on its container
    // — so prep presses the button and names the spot, the runner clicks
    // it through the DevTools input, and `after` measures the window.
    tour: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            document.getElementById('sidebar-expand')?.click();
            const button = document.getElementById('tool-point-set');
            if (!button) throw new Error('„Punkt setzen" not found');
            button.click();
            await new Promise(r => setTimeout(r, 400));
            const m = document.getElementById('map').getBoundingClientRect();
            return { click: { x: m.left + m.width * 0.55, y: m.top + m.height * 0.6 } };
        })()`, after: `
        (async () => {
            await new Promise(r => setTimeout(r, 900));
            const w = document.getElementById('list-selection-window');
            if (!w || !w.open) throw new Error('the list window did not open');
            document.activeElement?.blur();
            return ${box}(w);
        })()` },

    // THE MAP PICKER, unfolded: base maps on top, overlays below.
    layer: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const control = document.querySelector('.leaflet-control-layers');
            if (!control) throw new Error('the layer control is missing');
            control.classList.add('leaflet-control-layers-expanded');
            await new Promise(r => setTimeout(r, 300));
            return ${box}(control);
        })()` },

    // THE LEGEND BOXES bottom left, with three overlays switched on — the
    // rows in them are the sub-layers one can switch off by a click.
    legende: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const control = document.querySelector('.leaflet-control-layers');
            if (!control) throw new Error('the layer control is missing');
            for (const label of control.querySelectorAll('label')) {
                const text = label.textContent.trim();
                if (/^(Baumarten|Schutzgebiete|Gesteinstyp)/.test(text)) {
                    const input = label.querySelector('input');
                    if (input && !input.checked) input.click();
                }
            }
            await new Promise(r => setTimeout(r, 2500));
            const legend = document.querySelector('.legend');
            if (!legend) throw new Error('no legend appeared');
            return ${box}(legend);
        })()` },

    // THE SIDEBAR WHOLE, head to foot — the overview of chapter 1.6.
    leiste: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const side = document.querySelector('.sidebar');
            if (!side) throw new Error('sidebar not found');
            // With the grip on its edge — the desk's way of folding it.
            const grip = document.getElementById('sidebar-grip');
            // The shared block goes, for the picture only (see karte).
            const lists = document.getElementById('list-section');
            const kids = [...lists.children];
            const at = kids.findIndex(el => /Geteilt/.test(el.textContent));
            if (at >= 0) for (const el of kids.slice(at)) el.remove();
            // THE GRIP LIT AS UNDER THE POINTER (15.09.2026): the
            // mouse is moved onto it for real, so the picture shows the
            // hover state a reader will meet — not a style painted on.
            const g = grip ? ${box}(grip) : null;
            return g ? { hover: { x: g.x + g.width / 2, y: g.y + g.height / 2 } } : ${box}(side);
        })()`, after: `
        (async () => {
            await new Promise(r => setTimeout(r, 300));
            const side = document.querySelector('.sidebar');
            const grip = document.getElementById('sidebar-grip');
            // Up to the grip's outer edge and not a pixel further: the map's
            // chat bubble is not the sidebar's.
            // And a strip of map beyond the grip (16.09.2026:
            // „etwas breiter, so dass man noch Teile der Karte sieht") —
            // the sidebar reads as a sidebar only next to what it sits by.
            const b = ${box}(side), g = ${box}(grip);
            return { x: b.x, y: b.y, width: (g.x + g.width) - b.x + 90, height: b.height };
        })()` },

    // „MEINE LISTEN" FOR 1.6.2.1: a Mappe open with its lists, a second
    // Mappe FOLDED with a list on inside it (it lights up and says 1/2),
    // a list outside any Mappe, and „Ohne Liste". One list on, as the
    // mantra wants — the one inside the folded Mappe.
    listen: { page: 'index.php', wait: 3000, settings: (ids) => ({ visibleLists: [ids.insects], collapsedLists: [ids.animals] }), prep: `
        (async () => {
            const lists = document.getElementById('list-section');
            const kids = [...lists.children];
            const at = kids.findIndex(el => /Geteilt/.test(el.textContent));
            if (at >= 0) for (const el of kids.slice(at)) el.remove();
            // From the tabs down to the last row, and a strip of room on
            // the right for the numbers — they must not cover the text.
            const tabs = document.querySelector('.sidebar-tabs');
            const t = ${box}(tabs), b = ${box}(lists);
            const rows = [...lists.querySelectorAll('.list-row')];
            const last = rows[rows.length - 1].getBoundingClientRect().bottom;
            return { x: t.x, y: t.y, width: t.width + 56, height: last - t.y + 10 };
        })()` },

    // THE LIST WINDOW of „Pilze", opened through its pencil — the whole
    // form, from name to the footer (with the Mappe choice, since the
    // Mappen).
    liste: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const row = [...document.querySelectorAll('#list-section .list-row')]
                .find(r => r.querySelector('.list-row-name')?.textContent.trim() === 'Pilze');
            if (!row) throw new Error('„Pilze" not found');
            row.querySelector('.list-row-edit, [title="Bearbeiten"], .symbol-button')?.click();
            await new Promise(r => setTimeout(r, 700));
            const w = document.getElementById('list-window');
            if (!w || !w.open) throw new Error('the list window did not open');
            document.activeElement?.blur();
            // THE WHOLE FORM, not the window's scrolling slice: the picture
            // is meant to show everything the pencil opens, and the capture
            // reaches beyond the viewport.
            const form = w.querySelector('form');
            w.style.maxHeight = 'none'; w.style.height = 'auto'; w.style.top = '0'; w.style.bottom = 'auto';
            form.style.height = 'auto'; form.style.overflow = 'visible';
            await new Promise(r => setTimeout(r, 200));
            return ${box}(w);
        })()` },

    // THE „GETEILT" BLOCK: anna's own share and ben's, each unfolded,
    // with pencil, chat bubble and the foreign list's own pencil.
    geteilt: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const lists = document.getElementById('list-section');
            const kids = [...lists.children];
            const at = kids.findIndex(el => /Geteilt/.test(el.textContent));
            if (at < 0) throw new Error('no „Geteilt" block');
            for (const el of kids.slice(0, at)) el.remove();
            await new Promise(r => setTimeout(r, 200));
            const rows = [...lists.querySelectorAll('.list-row')];
            const first = kids[at].getBoundingClientRect();
            const last = rows[rows.length - 1].getBoundingClientRect().bottom;
            const b = ${box}(lists);
            return { x: b.x, y: first.top, width: b.width + 56, height: last - first.top + 10 };
        })()` },

    // THE SHARE WINDOW, opened through the pencil on a container row —
    // anna's own („Gemeinsame Funde") and ben's („Pilzfreunde Nord"),
    // whole form each.
    ...Object.fromEntries([['freigabe', 'Gemeinsame Funde'], ['freigabe-fremd', 'Pilzfreunde Nord']].map(([file, name]) => [file,
    { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const row = [...document.querySelectorAll('#list-section .list-row.is-shared')]
                .find(r => r.querySelector('.list-row-name')?.textContent.trim() === ${JSON.stringify(name)});
            if (!row) throw new Error(${JSON.stringify(name)} + ' not found');
            row.querySelector('[aria-label^="Freigabe"]')?.click();
            await new Promise(r => setTimeout(r, 700));
            const w = document.getElementById('container-info-window');
            if (!w || !w.open) throw new Error('the share window did not open');
            document.activeElement?.blur();
            const form = w.querySelector('form');
            w.style.maxHeight = 'none'; w.style.height = 'auto'; w.style.top = '0'; w.style.bottom = 'auto';
            if (form) { form.style.height = 'auto'; form.style.overflow = 'visible'; form.style.maxHeight = 'none'; }
            await new Promise(r => setTimeout(r, 200));
            return ${box}(w);
        })()` }])),

    // THE LOOK WINDOW of a foreign list — the pencil on „Bens
    // Beobachtungen" in anna's own share.
    'fremde-liste': { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const row = [...document.querySelectorAll('#list-section .list-row.is-shared')]
                .find(r => r.querySelector('.list-row-name')?.textContent.trim().startsWith('Bens'));
            if (!row) throw new Error('Bens list not found');
            row.querySelector('[aria-label^="Geteilte Liste"]')?.click();
            await new Promise(r => setTimeout(r, 600));
            const w = document.getElementById('shared-look-window');
            if (!w || !w.open) throw new Error('the look window did not open');
            document.activeElement?.blur();
            return ${box}(w);
        })()` },

    // THE „EXKURSIONEN" BLOCK of the sidebar: „Alle Exkursionen", then
    // one row per excursion with its leader.
    exkursionen: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const lists = document.getElementById('list-section');
            const kids = [...lists.children];
            const at = kids.findIndex(el => /^\s*Exkursionen/.test(el.textContent) && el.classList.contains('list-section-title') || /Exkursionen/.test(el.textContent) && el.querySelector?.('.section-title-name'));
            const start = at >= 0 ? at : kids.findIndex(el => /Exkursionen/.test(el.textContent));
            if (start < 0) throw new Error('no „Exkursionen" block');
            for (const el of kids.slice(0, start)) el.remove();
            await new Promise(r => setTimeout(r, 200));
            const rows = [...lists.querySelectorAll('.list-row')];
            const first = kids[start].getBoundingClientRect();
            const last = rows[rows.length - 1].getBoundingClientRect().bottom;
            const b = ${box}(lists);
            return { x: b.x, y: first.top, width: b.width + 56, height: last - first.top + 10 };
        })()` },

    // THE HEADER ALONE, with room below it for the numbers.
    kopfzeile: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const head = document.querySelector('.sidebar-header');
            if (!head) throw new Error('sidebar header not found');
            const b = ${box}(head);
            return { ...b, height: b.height + 24 };
        })()` },

    // THE SEARCH, opened and asked for a place — the hits stand under the
    // field. Clipped from the header down to the end of the hit list.
    suche: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            document.getElementById('header-search-button')?.click();
            await new Promise(r => setTimeout(r, 300));
            const field = document.getElementById('coord-search');
            if (!field) throw new Error('search field not found');
            field.value = 'Arenshausen';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise(r => setTimeout(r, 3500));
            const head = document.querySelector('.sidebar-header');
            const results = document.getElementById('coord-results');
            const h = ${box}(head), r = ${box}(results);
            const bottom = results.hidden ? h.y + h.height + 60 : r.y + r.height + 8;
            return { x: h.x, y: h.y, width: h.width, height: bottom - h.y };
        })()` },

    // A SPOT FOUND: a coordinate typed, the map jumped there and marks the
    // spot with the crosshair. A piece of map around the centre.
    gefunden: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            document.getElementById('header-search-button')?.click();
            await new Promise(r => setTimeout(r, 300));
            const field = document.getElementById('coord-search');
            field.value = '51.3721, 9.9755';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise(r => setTimeout(r, 2500));
            const m = document.getElementById('map').getBoundingClientRect();
            const cx = m.left + m.width / 2, cy = m.top + m.height / 2;
            return { x: cx - 210, y: cy - 150, width: 420, height: 300 };
        })()` },

    // THE SIDEBAR ON THE PHONE: it lies over the map, and its footer
    // carries „Schließen" — the way back to the map.
    'leiste-handy': { page: 'index.php', wait: 3000, viewport: { width: 375, height: 812 }, prep: `
        (async () => {
            document.getElementById('sidebar-expand')?.click();
            await new Promise(r => setTimeout(r, 700));
            const side = document.querySelector('.sidebar');
            if (!side) throw new Error('sidebar not found');
            // The shared block goes, for the picture only (see karte).
            const lists = document.getElementById('list-section');
            const kids = [...lists.children];
            const at = kids.findIndex(el => /Geteilt/.test(el.textContent));
            if (at >= 0) for (const el of kids.slice(at)) el.remove();
            return ${box}(side);
        })()` },

    // The „Punkte" tab: filter chip above the point rows, the order below.
    punkte: { page: 'index.php', wait: 3000, prep: `
        (async () => {
            const tab = [...document.querySelectorAll('[role="tab"]')].find(t => /^Punkte/.test(t.textContent.trim()));
            if (!tab) throw new Error('the „Punkte" tab is missing');
            tab.click();
            // The click leaves a focus ring on the tab — not part of the picture.
            document.activeElement?.blur();
            await new Promise(r => setTimeout(r, 800));
            // ONE FIND CHOSEN (16.09.2026, help 1.6.2.2: „ein
            // markierter Fund"): the second row, so the mark is not the
            // first thing under the filter chip.
            const pick = document.querySelectorAll('.sidebar .point-row')[1];
            pick?.click();
            document.activeElement?.blur();
            await new Promise(r => setTimeout(r, 600));
            const side = document.querySelector('.sidebar');
            const rows = [...side.querySelectorAll('.point-row')];
            if (rows.length === 0) throw new Error('no point rows');
            // The grip lit under the pointer, and the picture reaches past
            // it — the grip is what tells the desk from the phone here.
            const grip = document.getElementById('sidebar-grip');
            const g = ${box}(grip);
            return { hover: { x: g.x + g.width / 2, y: g.y + g.height / 2 } };
        })()`, after: `
        (async () => {
            await new Promise(r => setTimeout(r, 300));
            // The friend notices over the map are not the picture's subject.
            document.getElementById('map-badges')?.remove();
            const side = document.querySelector('.sidebar');
            const grip = document.getElementById('sidebar-grip');
            const b = ${box}(side), g = ${box}(grip);
            return { x: b.x, y: b.y, width: (g.x + g.width) - b.x + 90, height: b.height };
        })()` },

    // THE SAME TAB ON THE PHONE (16.09.2026): the sidebar over
    // the map, opened by ☰; the peek instead of the window on a tap.
    'punkte-handy': { page: 'index.php', wait: 3000, viewport: { width: 375, height: 812 }, prep: `
        (async () => {
            document.getElementById('sidebar-expand')?.click();
            await new Promise(r => setTimeout(r, 700));
            const tab = [...document.querySelectorAll('[role="tab"]')].find(t => /^Punkte/.test(t.textContent.trim()));
            tab?.click();
            document.activeElement?.blur();
            await new Promise(r => setTimeout(r, 800));
            const side = document.querySelector('.sidebar');
            const rows = [...side.querySelectorAll('.point-row')];
            if (rows.length === 0) throw new Error('no point rows');
            // THE CHOSEN ROW, as it stands after the window was open and
            // closed again: on the phone a tap on a row first shows the
            // point on the map, so the state is set here by its class —
            // the same one the app gives the chosen row.
            rows[1]?.classList.add('is-active');
            const b = ${box}(side);
            return b;
        })()` },

    // The administration's areas and the tabs of „Mein Bereich".
    // THE ADMINISTRATION'S TABS, as a NORMAL USER sees them
    // (16.09.2026: „nicht in der Administrationssicht, sondern für einen
    // normalen Benutzer") — ben, who is no admin: one row of tabs.
    verwaltung: { page: 'pages/administration.php', wait: 2500, user: 'ben@demo.local', prep: `
        (async () => {
            const tabs = document.querySelector('#section-mine .tab[role="tablist"]') ?? document.querySelector('.tab[role="tablist"]');
            if (!tabs) throw new Error('the tabs are missing');
            return ${box}(tabs);
        })()` },

    // Organisieren, the points table.
    organisieren: { page: 'pages/administration.php#organize', wait: 2500, prep: `
        (async () => {
            // The tab by its button, not by the hash: a second address on the
            // same page changes only the hash, and the page does not reload.
            document.getElementById('tab-organize')?.click();
            await new Promise(r => setTimeout(r, 900));
            const panel = document.getElementById('section-organize');
            if (!panel || panel.hidden) throw new Error('Organisieren is not showing');
            return ${box}(panel);
        })()` },

    // Offline, with the fixture's area.
    offline: { page: 'pages/administration.php#offline', wait: 2500, prep: `
        (async () => {
            // The tab by its button, not by the hash: a second address on the
            // same page changes only the hash, and the page does not reload.
            document.getElementById('tab-offline')?.click();
            await new Promise(r => setTimeout(r, 900));
            const panel = document.getElementById('section-offline');
            if (!panel || panel.hidden) throw new Error('Offline is not showing');
            return ${box}(panel);
        })()` },

    // Import, the drop zone.
    import: { page: 'pages/administration.php#import', wait: 2500, prep: `
        (async () => {
            // The tab by its button, not by the hash: a second address on the
            // same page changes only the hash, and the page does not reload.
            document.getElementById('tab-import')?.click();
            await new Promise(r => setTimeout(r, 900));
            const panel = document.getElementById('section-import');
            if (!panel || panel.hidden) throw new Error('Import is not showing');
            return ${box}(panel);
        })()` },
};

// --- A DevTools client, as small as it can be (tests/neighbours.mjs) --------
function connect(url) {
    return new Promise((ok, no) => {
        const socket = new WebSocket(url);
        const waiting = new Map();
        let next = 0;
        socket.addEventListener('message', (e) => {
            const message = JSON.parse(e.data);
            const seat = waiting.get(message.id);
            if (!seat) return;
            waiting.delete(message.id);
            message.error ? seat.no(new Error(message.error.message)) : seat.ok(message.result);
        });
        socket.addEventListener('error', () => no(new Error('DevTools connection failed')));
        socket.addEventListener('open', () => ok({
            send(method, params = {}, sessionId) {
                const id = ++next;
                return new Promise((ok2, no2) => {
                    waiting.set(id, { ok: ok2, no: no2 });
                    socket.send(JSON.stringify({ id, method, params, sessionId }));
                });
            },
            close: () => socket.close(),
        }));
    });
}

async function startChrome(binary) {
    const profile = mkdtempSync(join(tmpdir(), 'map-help-shots-'));
    const chrome = spawn(binary, [
        '--headless=new', '--remote-debugging-port=0', '--user-data-dir=' + profile,
        '--no-first-run', '--no-default-browser-check', '--disable-gpu',
        '--hide-scrollbars', `--window-size=${WIDTH},${HEIGHT}`,
    ], { stdio: ['ignore', 'ignore', 'pipe'] });
    const note = join(profile, 'DevToolsActivePort');
    const url = await new Promise((ok, no) => {
        const until = Date.now() + 20000;
        const look = () => {
            if (chrome.exitCode !== null) return no(new Error('Chrome quit at once'));
            if (existsSync(note)) {
                const [port, path] = readFileSync(note, 'utf8').split('\n');
                if (port && path) return ok(`ws://127.0.0.1:${port.trim()}${path.trim()}`);
            }
            if (Date.now() > until) return no(new Error('Chrome did not open a debugging port'));
            setTimeout(look, 150);
        };
        look();
    });
    return { chrome, url, profile };
}

// --- The run ----------------------------------------------------------------
const wanted = process.argv.slice(2);
for (const w of wanted) {
    if (!SHOTS[w]) stop(`No such picture: ${w}. Known: ${Object.keys(SHOTS).join(', ')}`);
}
const names = wanted.length ? wanted : Object.keys(SHOTS);

const binary = CHROMES.find(p => existsSync(p));
if (!binary) stop('No Chrome found. Looked in:\n  ' + CHROMES.join('\n  '));
if (!existsSync(SESSIONS)) stop(`No session directory at ${SESSIONS} — name it: MAP_SESSIONS=… node admin/help_shots.mjs`);
try {
    const answer = await fetch(BASE + 'login.php', { redirect: 'manual' });
    if (!answer.ok && answer.status !== 302) throw new Error('status ' + answer.status);
} catch (e) {
    stop(`The local server does not answer at ${BASE} (${e.message}).`);
}

// anna's id out of the database — the fixture is rebuilt now and then, and
// the number changes with it.
const annaId = Number(execFileSync('php', ['-r',
    'require "includes/bootstrap.php"; echo (int) $pdo->query("SELECT id FROM map_users WHERE email = \'anna@demo.local\'")->fetchColumn();',
], { cwd: ROOT }).toString().trim());
if (!annaId) stop('The demodata fixture is missing — run php admin/demodata.php --new first.');

/* ONE LIST SHOWN, THE ONE MAPPED INTO (15.09.2026: „eigentlich
   gehst du in den Wald und hast genau eine Liste an, nämlich das, was du
   kartieren willst — so ist das Prinzip der App"). The pictures show the
   app used right: anna's species list alone is switched on, so the window
   after „Punkt setzen" has exactly that one ticked. */
const speciesList = Number(execFileSync('php', ['-r',
    'require "includes/bootstrap.php"; echo (int) $pdo->query("SELECT id FROM map_lists WHERE user_id = ' + annaId + ' AND name = \'Pilze\'")->fetchColumn();',
], { cwd: ROOT }).toString().trim());

/* The ids of anna's lists, for shots that arrange the sidebar — looked
   up when the shot runs, after the fixture check above. */
function listIds() {
    const lookup = (sql, name) => Number(execFileSync('php', ['-r',
        'require "includes/bootstrap.php"; echo (int) $pdo->query(' + sql + ')->fetchColumn();',
        name], { cwd: ROOT }).toString().trim());
    const own = Object.fromEntries(Object.entries({ insects: 'Insekten' })
        .map(([key, name]) => [key, lookup('"SELECT id FROM map_lists WHERE user_id = ' + annaId + ' AND name = " . $pdo->quote($argv[1])', name)]));
    // The Mappe „Tiere": folded under the key the sidebar uses, 'f' + id.
    own.animals = 'f' + lookup('"SELECT id FROM map_list_folders WHERE user_id = ' + annaId + ' AND name = " . $pdo->quote($argv[1])', 'Tiere');
    // And ben's list, for the foreign find — it hangs in both shares.
    own.bens = lookup('"SELECT l.id FROM map_lists l JOIN map_users u ON u.id = l.user_id WHERE u.email = \'ben@demo.local\' AND l.name = " . $pdo->quote($argv[1])', 'Bens Beobachtungen');
    return own;
}

const sid = randomBytes(13).toString('hex');
const sessionFile = join(SESSIONS, 'sess_' + sid);
writeFileSync(sessionFile, `user_id|i:${annaId};`);

const { chrome, url, profile } = await startChrome(binary);
let failed = 0;
try {
    const browser = await connect(url);
    const { targetId } = await browser.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await browser.send('Target.attachToTarget', { targetId, flatten: true });
    const run = (method, params) => browser.send(method, params, sessionId);
    await run('Page.enable');
    await run('Runtime.enable');
    await run('Network.enable');
    await run('Emulation.setDeviceMetricsOverride', { width: WIDTH, height: HEIGHT, deviceScaleFactor: 1, mobile: false });
    await run('Network.setCookie', { name: 'kartenapp', value: sid, url: BASE, path: new URL(BASE).pathname });

    /* THE PLAIN MAP FOR THE PICTURES. The fixture opens on aerial imagery,
       and a megabyte of fields and forests is not what the help is about;
       OpenStreetMap draws flat colours that a PNG keeps small. Set once
       in the fresh profile's storage, the way the layer picker remembers
       it, before the map is ever opened. */
    await run('Page.navigate', { url: BASE + 'pages/help.php' });
    await sleep(800);
    const evaluate = async (expression) => {
        const { result, exceptionDetails } = await run('Runtime.evaluate', {
            expression, awaitPromise: true, returnByValue: true,
        });
        if (exceptionDetails) throw new Error(exceptionDetails.exception?.description ?? 'page error');
        return result.value;
    };

    await evaluate(`localStorage.setItem('map.settings', JSON.stringify({ baseMap: 'OpenStreetMap', visibleIndividually: true, visibleLists: [${speciesList}] })); 'set'`);

    console.log('\nHelp pictures → assets/help/\n');
    for (const name of names) {
        const shot = SHOTS[name];
        // A shot may want another account — the session file is rewritten
        // for it and set back to anna afterwards.
        const asUser = shot.user ? Number(execFileSync('php', ['-r',
            'require "includes/bootstrap.php"; echo (int) $pdo->query("SELECT id FROM map_users WHERE email = " . $pdo->quote($argv[1]))->fetchColumn();',
            shot.user], { cwd: ROOT }).toString().trim()) : annaId;
        writeFileSync(sessionFile, `user_id|i:${asUser};`);
        // A shot may arrange the sidebar differently — which lists are on,
        // which headings folded — before its page loads; the next shot
        // gets the plain arrangement back.
        const arranged = shot.settings ? shot.settings(listIds()) : {};
        // SEEDED FROM A QUIET PAGE, NOT FROM THE MAP. The map keeps its
        // settings in memory and writes the bundle back whenever anything
        // in it moves — a seeding done while the map is still the page
        // was overwritten before the navigation, and the next shot
        // inherited the previous prep's typed filter rows (the second
        // text-filter picture carried the first one's row). The help page
        // is the same origin, so the storage is the same, and nothing on
        // it writes the bundle.
        await run('Page.navigate', { url: BASE + 'pages/help.php' });
        await sleep(600);
        await evaluate(`localStorage.setItem('map.settings', JSON.stringify(${JSON.stringify({ baseMap: 'OpenStreetMap', visibleIndividually: true, visibleLists: [speciesList], ...arranged })})); 'set'`);
        try {
            // A shot may ask for another viewport — the phone's — and the
            // desk's comes back for the next one.
            const vp = shot.viewport ?? { width: WIDTH, height: HEIGHT };
            await run('Emulation.setDeviceMetricsOverride', {
                width: vp.width, height: vp.height, deviceScaleFactor: 1, mobile: vp.width < 860,
            });
            await run('Page.navigate', { url: BASE + shot.page });
            await sleep(shot.wait);
            const landed = await evaluate('location.pathname');
            if (/login\.php/.test(landed)) throw new Error('not logged in — the seeded session was not accepted');
            let clip = await evaluate(shot.prep);
            if (clip && clip.click) {
                const { x, y } = clip.click;
                for (const type of ['mousePressed', 'mouseReleased']) {
                    await run('Input.dispatchMouseEvent', { type, x, y, button: 'left', clickCount: 1 });
                }
                clip = await evaluate(shot.after);
            } else if (clip && clip.hover) {
                // A real pointer over the spot, for a picture of a hover state.
                const { x, y } = clip.hover;
                await run('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y });
                clip = await evaluate(shot.after);
            } else if (clip && clip.clicks) {
                // A SEQUENCE OF CLICKS INTO THE MAP — the corners of a drawn
                // area (help 1.6.2.2.1, Ort). Leaflet needs real pointer
                // events for them; a DOM click on the container sets no
                // corner. The pointer moves on afterwards so the rubber
                // band from the last corner shows where the next would go.
                for (const { x, y } of clip.clicks) {
                    await run('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y });
                    for (const type of ['mousePressed', 'mouseReleased']) {
                        await run('Input.dispatchMouseEvent', { type, x, y, button: 'left', clickCount: 1 });
                    }
                    await sleep(250);
                }
                if (clip.move) {
                    await run('Input.dispatchMouseEvent', { type: 'mouseMoved', x: clip.move.x, y: clip.move.y });
                    await sleep(250);
                }
                clip = await evaluate(shot.after);
            }
            const params = { format: 'png', captureBeyondViewport: true };
            if (clip) {
                const vw = shot.viewport?.width ?? WIDTH;
                params.clip = { x: Math.max(0, clip.x - 1), y: Math.max(0, clip.y - 1),
                                width: Math.min(vw, clip.width + 2), height: clip.height + 2, scale: 1 };
            }
            const { data } = await run('Page.captureScreenshot', params);
            writeFileSync(join(OUT, name + '.png'), Buffer.from(data, 'base64'));
            const size = Buffer.from(data, 'base64').length;
            console.log(`  ok    ${name}.png  ${clip ? Math.round(clip.width) + '×' + Math.round(clip.height) : WIDTH + '×' + HEIGHT}  ${Math.round(size / 1024)} kB`);
        } catch (e) {
            failed++;
            console.log(`  FAIL  ${name}.png — ${e.message}`);
        }
    }
    browser.close();
} finally {
    // Chrome writes its profile while it goes down — wait for it, or the
    // directory is „not empty" the moment it is removed.
    await new Promise(r => { chrome.once('exit', r); chrome.kill(); setTimeout(r, 4000); });
    rmSync(profile, { recursive: true, force: true, maxRetries: 3 });
    try { unlinkSync(sessionFile); } catch { /* already gone */ }
}
console.log(failed ? `\n${failed} picture(s) missing.\n` : '\nAll good.\n');
process.exit(failed ? 1 : 0);
