/* THE FIND ON THE PUBLIC SITE — the button in the Fundbrowser's find head
   and its menu (pages/finds.php, includes/plate_publish.php).

   IN THE HEAD, NOT IN THE PLATE WINDOW (25.11.2026: „Positionieren würde
   ich es gerne woanders. Und zwar allgemeiner, ein Fund direkt in der
   Kopfzeile. Mit einem vielleicht Dropdown für den Zusatz"). A maker has
   one plate per find (plates.php), so the find's release is its plate's.
   The button says where the find stands — published, not published, or
   waiting for somebody's consent —, the menu holds the tick, every other
   photographer as a tick of his own, and the addition to the licence.

   The markup stands only for the account that owns the site; the button
   shows only where the open find has a plate of his. Every change goes to
   the server at once (plates.php, action publish) and is answered with a
   toast; the server refuses a release while a photographer is unconfirmed,
   and the menu goes back to what is true. The site takes it up within the
   hour (its cron job). */

import { request } from './api.js';
import { toast } from './toast.js';
import { t } from './i18n.js';
import { personName } from './names.js';

const box = document.getElementById('finds-publish');
const toggle = document.getElementById('finds-publish-toggle');
const menu = document.getElementById('finds-publish-menu');
const on = document.getElementById('finds-publish-on');
const note = document.getElementById('finds-publish-note');
const people = document.getElementById('finds-publish-people');

let pointId = 0;
let plateId = 0;
let state = null;

/** The open find changed (finds.js drawFund) — or none is open (0). */
export async function publishFind(id) {
    if (!box) return;
    // The same find asked again (a plate saved) keeps its button and menu standing while the answer comes.
    if (id !== pointId) {
        pointId = id;
        plateId = 0;
        state = null;
        box.hidden = true;
        menuShow(false);
    }
    if (!id) return;
    let answer;
    try {
        answer = await request(`plates.php?point=${id}`);
    } catch {
        return;
    }
    if (id !== pointId) return;
    const mine = (answer?.plates ?? []).find(p => p.mine && p.publish);
    if (!mine) {
        box.hidden = true;
        menuShow(false);
        return;
    }
    plateId = mine.id;
    state = mine.publish;
    draw();
    box.hidden = false;
}

function draw() {
    const waiting = state.others.filter(o => !o.cleared).map(o => personName(o.name));
    const word = !state.published ? t('Nicht veröffentlicht')
        : waiting.length > 0 ? t('Wartet auf Einverständnis') : t('Veröffentlicht');
    // No word on the button any more — the globe's colour says it, the tooltip in words.
    toggle.classList.toggle('is-published', state.shown);
    toggle.classList.toggle('is-waiting', state.published && !state.shown);
    toggle.title = word;
    toggle.setAttribute('aria-label', word);

    document.getElementById('finds-publish-question').textContent = t('Auf %s zeigen?', state.site || t('der öffentlichen Seite'));
    on.checked = state.published;
    document.getElementById('finds-publish-licence').textContent =
        t('Lizenz %s: Jeder darf die Tafel mit deinem Namen weitergeben, aber nicht verkaufen.', state.licence);
    document.getElementById('finds-publish-others').hidden = state.others.length === 0;
    people.replaceChildren(...state.others.map(o => {
        const l = document.createElement('label');
        l.className = 'choice-row';
        const tick = document.createElement('input');
        tick.type = 'checkbox';
        tick.value = String(o.id);
        tick.checked = o.cleared;
        tick.addEventListener('change', () => send(t('Gespeichert.')));
        const words = document.createElement('span');
        words.textContent = t('%s ist einverstanden', personName(o.name));
        l.append(tick, words);
        return l;
    }));
    if (document.activeElement !== note) note.value = state.note;
    document.getElementById('finds-publish-state').textContent = !state.published ? ''
        : waiting.length > 0 ? t('Noch nicht zu sehen: %s muss erst einverstanden sein.', waiting.join(', '))
        : t('Veröffentlicht. Änderungen erscheinen innerhalb einer Stunde.');
}

async function send(said) {
    const id = plateId;
    if (!id) return;
    const cleared = [...people.querySelectorAll('input:checked')].map(x => Number(x.value));
    try {
        const answer = await request('plates.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'publish', id, on: on.checked, note: note.value, cleared }),
        });
        if (id === plateId) state = answer.publish;
        toast(said, 'success');
        /* Whoever shows the find elsewhere on the page follows at once — the
           Fundbrowser's tile carries a mark for a published find (finds.js). */
        const now = answer.publish;
        document.dispatchEvent(new CustomEvent('publish:changed', { detail: {
            pointId, state: !now.published ? null : now.shown ? 'shown' : 'waiting',
        } }));
    } catch (e) {
        toast(e.message || t('Das ließ sich nicht speichern.'), 'error');
    }
    if (id === plateId && state) draw();
}

function menuShow(open) {
    if (!menu) return;
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
}

if (box) {
    toggle.addEventListener('click', () => menuShow(menu.hidden));
    // A click or a press elsewhere folds it; the menu's own fields keep it open.
    document.addEventListener('pointerdown', (e) => {
        if (!menu.hidden && !box.contains(e.target)) menuShow(false);
    });
    // Escape folds the menu and nothing else — the page's own Escape (back to the list) waits for the next press.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !menu.hidden) {
            e.stopPropagation();
            menuShow(false);
            toggle.focus();
        }
    }, true);
    on.addEventListener('change', () => send(on.checked
        ? t('Die Tafel wird veröffentlicht. Sie erscheint innerhalb einer Stunde.')
        : t('Die Tafel wird nicht mehr veröffentlicht. Sie verschwindet innerhalb einer Stunde.')));
    note.addEventListener('change', () => send(t('Zusatz gespeichert.')));
    /* A plate made or changed in the tab „Tafeln" may be the find's first,
       or carry somebody else's picture now (plates.js, save). */
    document.addEventListener('plates:saved', (e) => {
        if (e.detail?.pointId === pointId) publishFind(pointId);
    });
}
