/**
 * The move to another cloud, in the Cloud tab — the switch and its reasons
 * stand in includes/cloud_moves.php.
 *
 * TWO THINGS HAPPEN HERE:
 *
 *   the window    „In andere Cloud umziehen …" opens it; it says what goes
 *                 along and asks where to. Its form is a plain POST to the
 *                 administration, which sends the user on to the new
 *                 cloud's consent page — nothing to do for a script there
 *                 but open, close and name the button.
 *
 *   the carrying  WHILE THE TAB IS OPEN it carries the move step by step
 *                 (the choice, 26.09.2026) and the bar grows with
 *                 it; closed, the five-minute cron carries on. A step that
 *                 finds the cron at work hears 'busy' and asks again a
 *                 little later. As soon as the move needs somebody — it is
 *                 done, full, stuck, or a cloud does not answer — the page
 *                 is loaded anew, and the server draws what there is to
 *                 decide.
 *
 * SELF-ATTACHING WITH A WATCHER, like calibrate.js: the administration
 * renders its tabs lazily, so when this module runs, the Cloud tab's markup
 * is usually not there yet.
 */

import { request } from './api.js';
import { t } from './i18n.js';

const pause = (ms) => new Promise((done) => setTimeout(done, ms));

/** Bytes as cloud_size() in includes/cloud.php writes them. */
function size(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1).replace('.', ',') + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
    return Math.round(bytes / 1024).toLocaleString('de-DE') + ' kB';
}

/** The bar and its line — the same words the server draws (cloud_move_count_text). */
function paint(panel, state) {
    const share = state.total > 0 ? Math.floor(100 * state.moved / state.total) : 100;
    const bar = panel.querySelector('.area-bar > div');
    if (bar) bar.style.width = share + '%';
    panel.querySelector('.move-progress')?.setAttribute('aria-valuenow', String(share));
    const line = panel.querySelector('.move-count');
    if (line) {
        // „Dateien" once a find's other files are among them (cloud_move_count_text).
        let text = state.files > 0
            ? (state.total === 1
                ? t('%d von 1 Datei ist umgezogen', state.moved)
                : t('%d von %d Dateien sind umgezogen', state.moved, state.total))
            : (state.total === 1
                ? t('%d von 1 Bild ist umgezogen', state.moved)
                : t('%d von %d Bildern sind umgezogen', state.moved, state.total));
        if (state.bytes > 0) text += ' · ' + t('%s von %s', size(state.bytes_moved), size(state.bytes));
        line.textContent = text;
    }
}

/* LOADED ANEW BY ADDRESS, NOT BY location.reload(): the page may be the
   answer to a POST („Nochmal versuchen"), and a reload would ask the user
   whether to send that form again. */
function redraw() {
    location.replace((window.START_DATA?.baseUrl ?? '') + 'pages/administration.php?fresh=' + Date.now() + '#cloud');
}

/** Carry the move step by step while the panel is on the page. */
async function drive(panel) {
    if (panel.dataset.driving) return;
    panel.dataset.driving = '1';
    while (panel.isConnected) {
        let state;
        try {
            state = await request('cloud_move.php', { method: 'POST', body: new FormData() });
        } catch (e) {
            // A hiccup of the line or the server: the cron carries on
            // anyway, and the tab asks again in a while.
            await pause(15000);
            continue;
        }
        if (state.status === 'none') {
            redraw();
            return;
        }
        paint(panel, state);
        if (state.busy) {
            await pause(5000);
            continue;
        }
        if (state.status !== 'running' || state.full || state.problem || state.waiting === 0) {
            redraw();
            return;
        }
    }
}

function bindWindow(win) {
    if (win.dataset.bound) return;
    win.dataset.bound = '1';
    win.querySelector('[data-cloud-move-cancel]')?.addEventListener('click', () => win.close());
    // The button names where it leads — „Weiter zu Google Drive".
    const go = win.querySelector('#cloud-move-go');
    for (const radio of win.querySelectorAll('input[type="radio"][name="provider"]')) {
        radio.addEventListener('change', () => {
            if (radio.checked && go) go.textContent = go.dataset.label.replace('%s', radio.dataset.name);
        });
    }
}

function scan() {
    const opener = document.getElementById('cloud-move-open');
    if (opener && !opener.dataset.bound) {
        opener.dataset.bound = '1';
        opener.addEventListener('click', () => document.getElementById('cloud-move-window')?.showModal());
    }
    const win = document.getElementById('cloud-move-window');
    if (win) bindWindow(win);
    for (const panel of document.querySelectorAll('[data-cloud-move="running"]')) drive(panel);
}

new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
scan();
