/**
 * Neighbour heights: what stands side by side in one row is the same height.
 *
 *   node tests/neighbours.mjs
 *
 * WHY THIS ONE NEEDS A BROWSER, unlike every other test here. The question
 * is not what a stylesheet SAYS but what the browser DRAWS: a height comes
 * from a token, from padding, from a line box, from a parent's align — and
 * whether two things end up equal can only be measured after layout. The
 * static twin already exists (tests/sizes.php) and guards that sizes are
 * taken from tokens; it cannot see that two DIFFERENT tokens met in one
 * row.
 *
 * WHAT IT FOUND WHEN IT WAS WRITTEN (09.09.2026), and none of it was
 * visible to the size test: the tour list's search field at 37 next to its
 * two sort buttons at 31, the column head's search at 31 next to its sort
 * arrow at 20, and the photo window's measuring row with three fields at
 * 37 beside five tools at 32. All three are correct sizes on their own —
 * they only go wrong as neighbours.
 *
 * NO DEPENDENCY. It drives the Chrome that is on the machine anyway,
 * through the DevTools protocol over the WebSocket Node brings itself.
 * Nothing to install, nothing in package.json — the project has no build
 * step and does not get one for a test.
 *
 * WHAT IT NEEDS, and it says so itself when something is missing:
 *   - the local server answering (MAP_URL, default http://localhost:8888/map/)
 *   - Chrome in one of the usual places
 *   - the demodata fixture, because it logs in as anna@demo.local
 *     (php admin/demodata.php --new)
 */

import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync, existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = (process.env.MAP_URL ?? 'http://localhost:8888/map/').replace(/\/?$/, '/');
const USER = process.env.MAP_USER ?? 'anna@demo.local';
const PASS = process.env.MAP_PASS ?? 'Demo-2026';

/* The two widths that matter: one comfortably above the 860 breakpoint,
   one a common phone. A mismatch usually lives on ONE of them — the phone
   pushes everything to 44 and hides differences the desk shows, and the
   desk keeps the small sizes the phone flattens. */
const WIDTHS = [[1400, 900], [393, 852]];

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

/* ---------------------------------------------------------------------------
 * The measurement itself — a string, because it runs in the page.
 *
 * A CHILD COUNTS AS A CONTROL when it is one, or when it hugs one: at most
 * four pixels taller than the tallest control inside it. That admits the
 * shell a text field wears (field-with-x, which holds the field AND its
 * clearing ×) and keeps out a label that carries its caption above the
 * field — that one is legitimately taller and would be a false alarm.
 *
 * FORM ROWS ONLY: at least one field has to be in the group. A list row
 * with a small pencil in it is a different thing — an icon sitting in a
 * row, not a button standing next to a field — and without this condition
 * every row of the sidebar reports itself.
 *
 * The tolerance is 3 px. Below that lies the pixel a date field brings
 * along in one browser and not in another; above it, someone reached for
 * the wrong token.
 * ------------------------------------------------------------------------- */
const AUDIT = `
(() => {
  const IS = 'button, input:not([type=hidden]), select, a.button, a.symbol-button';
  const FIELD = 'input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=range]), select';
  const high = e => e.getBoundingClientRect().height;
  window.__audit = (root = document) => {
    const found = [];
    const seen = new Set();
    for (const parent of root.querySelectorAll('*')) {
      const kids = [...parent.children].filter(k => {
        if (high(k) === 0) return false;
        if (k.matches(IS)) return true;
        const inside = [...k.querySelectorAll(IS)].filter(e => high(e) > 0);
        if (inside.length === 0) return false;
        return high(k) - Math.max(...inside.map(high)) <= 4;
      });
      if (kids.length < 2) continue;
      const middle = k => { const r = k.getBoundingClientRect(); return r.y + r.height / 2; };
      const row = kids.filter(k => Math.abs(middle(k) - middle(kids[0])) < 6);
      if (row.length < 2) continue;
      if (!row.some(k => k.matches(FIELD) || k.querySelector(FIELD))) continue;
      const hs = row.map(k => Math.round(high(k)));
      const spread = Math.max(...hs) - Math.min(...hs);
      if (spread <= 3) continue;
      const key = String(parent.className).slice(0, 40) + ' ' + hs.join('/');
      if (seen.has(key)) continue;
      seen.add(key);
      found.push({
        row: String(parent.className || parent.tagName).slice(0, 40),
        parts: row.map(k => k.tagName.toLowerCase() + '.' + String(k.className).slice(0, 22)
                          + ':' + Math.round(high(k))).join('  |  '),
        spread,
      });
    }
    return found;
  };
  return true;
})()
`;

// --- A DevTools client, as small as it can be -------------------------------
//
// Every command carries an id and comes back under it; everything else is an
// event. That is the whole protocol as far as this test needs it.
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
    const profile = mkdtempSync(join(tmpdir(), 'map-neighbours-'));
    const chrome = spawn(binary, [
        '--headless=new', '--remote-debugging-port=0', '--user-data-dir=' + profile,
        '--no-first-run', '--no-default-browser-check', '--disable-gpu',
    ], { stdio: ['ignore', 'ignore', 'pipe'] });

    /* THE PORT COMES OUT OF THE PROFILE, not out of the console. Chrome
       writes DevToolsActivePort there the moment it is ready — two lines,
       the port and the browser's path. Reading its stderr instead looks
       obvious and is not: the line arrives on a stream that also carries
       every graphics warning the machine has, and on a fresh profile it
       can be seconds late. The file is there or it is not. */
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
console.log('\nNeighbour heights\n');

const binary = CHROMES.find(p => existsSync(p));
if (!binary) {
    stop('No Chrome found. Looked in:\n  ' + CHROMES.join('\n  '));
}

try {
    const answer = await fetch(BASE + 'login.php', { redirect: 'manual' });
    if (!answer.ok && answer.status !== 302) throw new Error('status ' + answer.status);
} catch (e) {
    stop(`The local server does not answer at ${BASE} (${e.message}).\n`
       + '  Start MAMP, or name another address: MAP_URL=… node tests/neighbours.mjs');
}

const { chrome, url, profile } = await startChrome(binary);
let failures = 0;

try {
    const browser = await connect(url);
    const { targetId } = await browser.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await browser.send('Target.attachToTarget', { targetId, flatten: true });
    const run = (method, params) => browser.send(method, params, sessionId);
    await run('Page.enable');
    await run('Runtime.enable');

    /** Evaluate in the page and hand back the value. */
    const evaluate = async (expression) => {
        const { result, exceptionDetails } = await run('Runtime.evaluate', {
            expression, awaitPromise: true, returnByValue: true,
        });
        if (exceptionDetails) throw new Error(exceptionDetails.exception?.description ?? 'page error');
        return result.value;
    };

    const open = async (page) => {
        await run('Page.navigate', { url: BASE + page });
        // Settling instead of listening for the load event: the map fills
        // itself from several requests, and "loaded" says nothing about
        // whether the sidebar has drawn.
        await new Promise(r => setTimeout(r, 2600));
        await evaluate(AUDIT);
    };

    // Log in through the form, from inside the page — that way the cookie
    // lands where the browser will use it.
    await run('Page.navigate', { url: BASE + 'login.php' });
    await new Promise(r => setTimeout(r, 900));
    const landed = await evaluate(`
        (async () => {
          const body = new URLSearchParams();
          body.set('email', ${JSON.stringify(USER)});
          body.set('password', ${JSON.stringify(PASS)});
          const token = document.querySelector('input[name=csrf_token], input[name=csrf]');
          if (token) body.set(token.name, token.value);
          const r = await fetch('login.php', { method: 'POST', body, redirect: 'follow' });
          return r.url;
        })()
    `);
    if (/login\.php/.test(landed)) {
        stop(`Could not log in as ${USER}.\n`
           + '  The fixture may be missing: php admin/demodata.php --new\n'
           + '  Or name another account: MAP_USER=… MAP_PASS=… node tests/neighbours.mjs');
    }

    /* THE PAGES AS PAGES. At the desk an area's page sends itself into the
       frame page (includes/shell.php), and this test would then measure a
       page that holds nothing but frames. 'shell.off' is the tab's „no" to
       that; the session is no desk's either, it logged in without saying
       so. */
    await evaluate(`sessionStorage.setItem('shell.off', '1')`);

    for (const [width, height] of WIDTHS) {
        await run('Emulation.setDeviceMetricsOverride', {
            width, height, deviceScaleFactor: 1, mobile: width < 860,
        });

        // --- The map, both sidebar pages, and every dialog it carries ------
        await open('index.php');
        const map = await evaluate(`
            (async () => {
              const wait = ms => new Promise(r => setTimeout(r, ms));
              const out = [];
              const open = document.getElementById('sidebar-expand');
              if (document.body.classList.contains('sidebar-collapsed') && open) {
                open.click(); await wait(700);
              }
              for (const id of ['sidebar-tab-lists', 'sidebar-tab-points']) {
                const tab = document.getElementById(id);
                if (!tab) continue;
                tab.click(); await wait(600);
                out.push(...window.__audit().map(f => ({ ...f, where: 'map/' + id })));
              }
              for (const d of document.querySelectorAll('dialog')) {
                let mine = false;
                try { if (!d.open) { d.showModal(); mine = true; } } catch { continue; }
                await wait(120);
                out.push(...window.__audit(d).map(f => ({ ...f, where: d.id || 'dialog' })));
                if (mine) d.close();
              }
              return out;
            })()
        `);

        // --- The administration, every tab, each with its parents opened ---
        await open('pages/administration.php');
        const admin = await evaluate(`
            (async () => {
              const wait = ms => new Promise(r => setTimeout(r, ms));
              const tabs = [...document.querySelectorAll('[role="tab"]')];
              const byPanel = new Map(tabs.map(t => [t.getAttribute('aria-controls'), t]));
              /* A sub-tab shows nothing while its parent is closed, so the
                 whole chain is opened from the top down. */
              const chain = (t) => {
                const way = [t];
                let el = t.closest('[role="tabpanel"], section[id]');
                while (el) {
                  const up = byPanel.get(el.id);
                  if (up && !way.includes(up)) way.unshift(up);
                  el = el.parentElement?.closest('[role="tabpanel"], section[id]');
                }
                return way;
              };
              const out = [];
              for (const t of tabs) {
                for (const step of chain(t)) { step.click(); await wait(170); }
                await wait(560);
                out.push(...window.__audit().map(f => ({ ...f, where: 'admin/' + t.textContent.trim().slice(0, 18) })));
              }
              return out;
            })()
        `);

        const found = [...map, ...admin];
        // The same row reports itself on several tabs; one line per shape.
        const once = [...new Map(found.map(f => [f.row + f.parts, f])).values()];
        if (once.length === 0) {
            console.log(`  ok    ${width} px — nothing out of line`);
            continue;
        }
        failures += once.length;
        for (const f of once) {
            console.log(`  FAIL  ${width} px  ${f.where}`);
            console.log(`        ${f.row}  —  ${f.parts}   (${f.spread} px apart)`);
        }
    }
} finally {
    chrome.kill();
    try { rmSync(profile, { recursive: true, force: true }); } catch { /* never mind */ }
}

if (failures > 0) {
    console.log(`\n${failures} row${failures === 1 ? '' : 's'} where neighbours differ by more than 3 px.\n`);
    process.exit(1);
}
console.log('\nAll good.\n');
