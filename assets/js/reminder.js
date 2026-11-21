/* THE ADMINISTRATOR'S REMINDER ON THE MAP — a client secret running out
 * (index.php renders #secret-reminder only when cloud_secret_warnings() has
 * something to say; 02.10.2026).
 *
 * ONCE A DAY PER DEVICE. Every map load would turn a reminder into a
 * nuisance one clicks away blind; never again after „Später" would let a
 * month pass in silence. The day is remembered through settings, like
 * everything that outlives a visit (CLAUDE.md, Offline).
 *
 * NOT OVER ANOTHER WINDOW. The map opens windows by itself at load — a
 * link with ?window=…, the onboarding — and a second modal on top would
 * bury the one the user came for. Then the reminder waits for the next
 * load; the administration says the same at its head anyway.
 *
 * Its own module and not more lines in map.js (split gradually,
 * 24.09.2026): it shares nothing with the map but the page. */

import { settings } from './settings.js';

const KEY = 'secretReminderDay';

function today() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

window.addEventListener('load', () => {
    const win = document.getElementById('secret-reminder');
    if (!win || settings.get(KEY, null) === today()) return;
    // A moment later, so the windows the map opens at load come first.
    setTimeout(() => {
        if (document.querySelector('dialog[open]')) return;
        settings.set(KEY, today());
        win.showModal();
    }, 1500);
});
