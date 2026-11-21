/* THE FUNDBROWSER'S BUTTON „Öffentliche Seite aktualisieren" — refreshes
   the public find book on wkpilz.de in the background and says how it went
   in ONE toast, nothing more (20.11.2026: „Einfach stillschweigend …
   mit einer Toast-Nachricht benachrichtigen, dass alles geklappt hat oder
   nicht geklappt hat, aber mehr nicht. Ich will auch keinen Link danach").

   The server works in slices of some seconds (api/v1/public_site.php, the
   site's sync_run); this asks again until it answers `done`. While it runs
   the button rests and turns its globe — a second click would only queue
   behind the site's lock.

   THE BODY IS AN EMPTY FormData ON PURPOSE: request() gives a JSON call
   twelve seconds and a file upload two minutes (api.js, TIMEOUT_FILE); a
   slice that fetches a large plate from the cloud needs the longer of the
   two. */

import { request } from './api.js';
import { toast } from './toast.js';
import { t } from './i18n.js';

const button = document.getElementById('finds-public');

/** No round may come back without progress more than this often — then the cloud has stopped answering. */
const STUCK_ROUNDS = 3;

async function refresh() {
    button.disabled = true;
    button.classList.add('is-busy');
    button.setAttribute('aria-busy', 'true');
    try {
        let answer;
        let stuck = 0;
        let left = Infinity;
        for (;;) {
            answer = await request('public_site.php', { method: 'POST', body: new FormData() });
            if (answer.done) break;
            stuck = answer.left < left ? 0 : stuck + 1;
            left = answer.left;
            if (stuck >= STUCK_ROUNDS) throw new Error(t('Die Cloud antwortet nicht.'));
        }
        if (answer.held) {
            toast(t('Öffentliche Seite nicht aktualisiert: Zu viele Tafeln ließen sich nicht aus der Cloud holen.'), 'error');
        } else {
            toast(t('Öffentliche Seite aktualisiert: %d Funde.', answer.finds), 'success');
        }
    } catch (e) {
        toast(t('Öffentliche Seite nicht aktualisiert: %s', e.message), 'error');
    } finally {
        button.disabled = false;
        button.classList.remove('is-busy');
        button.removeAttribute('aria-busy');
    }
}

button?.addEventListener('click', refresh);
