/**
 * „Teilen" in the window „Liste bearbeiten" — who gets the list, and what
 * each one may (08./09.10.2026; the server half is
 * includes/list_shares.php, migration 0160).
 *
 * THE PICTURE OF „DIREKT TEILEN": every friend and every own working group
 * stands in sight with a box („dort wird die Freundesliste
 * angezeigt und man setzt da Haken rein raus"). A ticked one carries one
 * line with exactly what applies — no levels like „mitarbeiten", the row
 * names the rights and the filter — and the gear to change them.
 *
 * EVERY TICK ASKS, AND THEN APPLIES AT ONCE (12.11.2026, when
 * the window began to save itself: „Setzt du einen Haken? Bitte nochmal
 * kurz bestätigen lassen. Nimmst du einen Haken raus? Nochmal kurz
 * bestätigen lassen"). The rest of the window keeps itself a moment after
 * each change (map.js, listSaveSoon); sharing is the one part where a slip
 * costs — a friend sees a list he should not, or one who works in it gets
 * his points sent home —, so a tick there is a question, and the yes is
 * sent straight away (commit). The gear's window has its own „Übernehmen",
 * which is that confirmation for the rights; it applies at once as well.
 *
 * ITS OWN MODULE, not more of map.js (the plan of 24.09.2026: whatever a
 * task works in moves out of map.js).
 */

import { request } from './api.js';
import { confirm } from './dialogs.js';
import { t } from './i18n.js';
import { icon } from './icons.js';
import { tickBox } from './tickbox.js';
import * as shareFilter from './recipientfilter.js';

/** The fixed rights, in the order the gear's table lists them; fields go after note. */
const RIGHTS = ['title', 'note', 'move', 'attach_photos', 'delete_photos', 'measure', 'edit_photos', 'delete', 'own_points'];

/* „Alles erlauben" asks first from this many points on
   (08.10.2026: the one place a slip costs dearly — „wenn jemand seine
   Pilzliste mit zigtausend Funden aus Versehen … teilt"). */
const BIG_LIST = 100;

const section = document.getElementById('list-recipients-section');
const holder  = document.getElementById('list-recipients');
const rightsWindow = document.getElementById('recipient-rights');

/** The window's state: the list on screen, the server's answers, what changed. */
let root = null;          // the main list being edited
let shown = null;         // the list whose share is drawn
const overviews = new Map();   // list id → the server's overview
const pending   = new Map();   // list id → Map(key → recipient) — changed, not yet sent
let loadToken = 0;

const keyOf = (r) => `${r.type}:${r.id}`;

/** The list's points for the filter's offers — map.js knows them. */
let pointsOf = () => [];
/** The map's status line, for what a commit did. */
let say = () => {};

/** Called once by map.js: where the points are, the sidebar's areas, the find fields. */
export function configure(options) {
    pointsOf = options.pointsOf ?? pointsOf;
    say = options.status ?? say;
    shareFilter.configure(options);
}

/** Open with the list window. null = a new list, which cannot be shared yet. */
export function open(list) {
    root = list;
    shown = list?.id ?? null;
    overviews.clear();
    pending.clear();
    section.hidden = false;
    holder.textContent = '';
    if (!list || !(list.id > 0)) {
        holder.append(hint(t('Teilen kannst du, sobald die Liste gespeichert ist.')));
        return;
    }
    load(list.id);
}

/** Hide (a guest, an auto list's window — the caller decides). */
export function hide() {
    section.hidden = true;
    root = null;
}

async function load(listId) {
    const token = ++loadToken;
    holder.textContent = '';
    holder.append(hint(t('Lädt …')));
    try {
        const answer = await request(`list_recipients.php?list=${listId}`);
        if (token !== loadToken) return;
        overviews.set(listId, answer);
        draw();
    } catch (error) {
        if (token !== loadToken) return;
        holder.textContent = '';
        holder.append(hint(error?.status === 503
            ? t('Listen teilen braucht die Migration 0160 — sie ist noch nicht eingespielt.')
            : t('Teilen braucht eine Verbindung. Versuch es noch einmal, wenn du online bist.')));
    }
}

function hint(text) {
    const p = document.createElement('p');
    p.className = 'hint-text small recipient-hint';
    p.textContent = text;
    return p;
}

/** The recipients as they stand now — the pending set if there is one. */
function recipientsOf(listId) {
    if (pending.has(listId)) return pending.get(listId);
    const map = new Map();
    for (const r of overviews.get(listId)?.recipients ?? []) map.set(keyOf(r), { ...r });
    return map;
}

function touch(listId, map) {
    pending.set(listId, map);
    draw();
}

function draw() {
    const data = overviews.get(shown);
    holder.textContent = '';
    if (!data) return;

    const recipients = recipientsOf(shown);
    if (data.friends.length === 0 && data.groups.length === 0) {
        holder.append(hint(t('Freunde findest du in der Verwaltung unter Kontakte.')));
        return;
    }
    if (data.friends.length > 0) {
        holder.append(subhead(t('Freunde')));
        for (const f of data.friends) holder.append(row({ type: 'friend', id: f.id, name: f.name }, recipients));
    }
    if (data.groups.length > 0) {
        holder.append(subhead(t('Arbeitsgruppen')));
        for (const g of data.groups) {
            holder.append(row({ type: 'group', id: g.id, name: g.name,
                members: g.members }, recipients));
        }
    }
}

function subhead(text) {
    const h = document.createElement('div');
    // A group's head with its surface (.list-label, CLAUDE.md „A group's head is a surface") — under the part's title „Teilen".
    h.className = 'recipient-subhead list-label';
    h.textContent = text;
    return h;
}

function row(who, recipients) {
    const key = keyOf(who);
    const on = recipients.get(key) ?? null;
    const line = document.createElement('div');
    line.className = 'recipient-row' + (on ? ' is-active' : '');

    const box = tickBox(on ? 'on' : 'off', who.name,
        on ? t('Nicht mehr teilen') : t('Mit %s teilen', who.name), () => toggle(who, recipients));
    const text = document.createElement('span');
    text.className = 'recipient-who';
    const name = document.createElement('span');
    name.className = 'recipient-name';
    name.textContent = who.type !== 'group' || who.members == null ? who.name
        : who.members === 1 ? t('%s (1 Person)', who.name) : t('%s (%d Personen)', who.name, who.members);
    text.append(name);
    if (on) {
        const what = document.createElement('span');
        what.className = 'recipient-what';
        what.textContent = summary(on);
        text.append(what);
    }
    // The name toggles too — a box alone is a small target for a thumb.
    name.addEventListener('click', () => toggle(who, recipients));
    line.append(box, text);

    if (on) {
        const gear = document.createElement('button');
        gear.type = 'button';
        gear.className = 'symbol-button small recipient-gear';
        gear.title = t('Was %s sieht und darf', who.name);
        gear.setAttribute('aria-label', gear.title);
        gear.append(icon('gear'));
        gear.addEventListener('click', () => openRights(who));
        line.append(gear);
    }
    return line;
}

async function toggle(who, recipients) {
    const key = keyOf(who);
    const map = new Map(recipients);
    const had = map.get(key);
    const listName = shown === root?.id ? root.name : (overviews.get(root?.id)?.sublists ?? []).find(x => x.list === shown)?.name ?? root?.name ?? '';
    if (had) {
        /* SOMEBODY WHO WORKS IN THE LIST is asked about more plainly: his
           points go back to him, into a list of his own — nothing is lost,
           but the list gets emptier for everybody else. */
        const working = (had.rights ?? []).includes('own_points');
        const yes = await confirm(working ? {
            title: t('%s herausnehmen?', who.name),
            text: t('%s setzt eigene Punkte in diese Liste. Die gehen dann zurück zu %s, in eine eigene Liste.', who.name, who.name),
            button: t('Herausnehmen'),
            danger: true,
        } : {
            title: t('Nicht mehr mit %s teilen?', who.name),
            text: t('%s sieht die Liste „%s" dann nicht mehr. An den Punkten ändert sich nichts.', who.name, listName),
            button: t('Nicht mehr teilen'),
        });
        if (!yes) return;
        map.delete(key);
    } else {
        // A new tick sees every point and may change nothing — more only
        // through the gear.
        const yes = await confirm({
            title: t('Mit %s teilen?', who.name),
            text: t('%s sieht dann die Punkte der Liste „%s" mit allen Bildern. Ändern darf %s nichts, bis du es am Zahnrad erlaubst.', who.name, listName, who.name),
            button: t('Teilen'),
        });
        if (!yes) return;
        map.set(key, { type: who.type, id: who.id, name: who.name, rights: [], filter: null, filter_text: null });
    }
    await commit(shown, map);
}

/**
 * Send one list's recipients as they now stand — at once, after the
 * question (the module's head says why). The overview is fetched anew
 * afterwards, so what is drawn is what the server holds; a refusal says so
 * and draws the server's state again.
 */
async function commit(listId, map) {
    touch(listId, map);
    const entry = { list: listId, recipients: [...map.values()].map(r => ({ type: r.type, id: r.id, rights: r.rights ?? [], filter: r.filter ?? null })) };
    try {
        const done = await send([entry]);
        say(done.home > 0
            ? t('Teilen gespeichert. %d Punkte sind zurück bei denen, die nicht mehr mitarbeiten.', done.home)
            : t('Teilen gespeichert.'));
    } catch (error) {
        say(t('Das Teilen ließ sich nicht speichern: %s', error.message), true);
    }
    pending.delete(listId);
    if (root?.id > 0 && shown === listId) await load(listId);
}

/** What applies, in one line — exactly, no levels. */
function summary(r) {
    const rights = r.rights ?? [];
    const text = r.filter ? shareFilter.readable(r.filter) : null;
    const sees = text ? t('Filter: %s', text) : t('sieht alle Punkte');
    if (allowsAll(rights)) {
        return rights.includes('own_points')
            ? `${sees} · ${t('setzt eigene Punkte, darf alles')}` : `${sees} · ${t('darf alles')}`;
    }
    const words = [];
    const say = {
        title: t('Titel'), note: t('Notiz'), move: t('verschieben'),
        attach_photos: t('Bilder anhängen'), delete_photos: t('Bilder löschen'),
        // „Bilder bearbeiten" is the edits' since 11.11.2026 (edit_photos); measuring says what it does.
        measure: t('Bilder vermessen'), edit_photos: t('Bilder bearbeiten'), delete: t('Papierkorb'),
    };
    for (const k of rights) {
        if (say[k]) words.push(say[k]);
        else if (k === 'field:*') words.push(t('alle Felder'));
        else if (k.startsWith('field:')) words.push(t('Feld „%s"', k.slice(6)));
    }
    const parts = [sees];
    if (rights.includes('own_points')) parts.push(t('setzt eigene Punkte'));
    parts.push(words.length > 0 ? t('darf: %s', words.join(', ')) : t('darf nichts ändern'));
    return parts.join(' · ');
}

function allowsAll(rights) {
    const fields = overviews.get(shown)?.fields ?? [];
    return RIGHTS.every(k => rights.includes(k))
        && (rights.includes('field:*') || fields.every(f => rights.includes('field:' + f)));
}

// ---------------------------------------------------------------------------
// The gear: one recipient's rights
// ---------------------------------------------------------------------------

let editing = null;   // { who, rights: Set, filter }

function rightRows(who) {
    const fields = overviews.get(shown)?.fields ?? [];
    const rows = [
        ['title', t('den Titel ändern')],
        ['note', t('die Notiz ändern')],
        ...fields.map(f => ['field:' + f, t('das Feld „%s" ausfüllen', f)]),
        ['move', t('Punkte verschieben und ihren Umriss ändern')],
        ['attach_photos', t('Bilder an Punkte hängen'),
            t('Wer ein Bild an deinen Fund hängt, wird Mitbesitzer. Deine Bilder dieses Fundes kommen dann auch in die Cloud von %s und bleiben dort, auch wenn eure Freundschaft endet.', who.name)],
        ['delete_photos', t('Bilder löschen, auch deine'), t('Die eigenen Bilder darf jeder immer löschen.')],
        ['measure', t('deine Bilder vermessen: Maßstab, Messungen und Bildname'),
            t('Ohne diesen Haken misst %s nur für sich. An deinen Bildern ändert sich dann nichts.', who.name)],
        /* „BILDER BEARBEITEN" (11.11.2026): edits of one's own
           beside the sharer's — includes/picture_edits.php. */
        ['edit_photos', t('deine Bilder bearbeiten: eigene Bearbeitungen anlegen'),
            t('%s legt eigene Bearbeitungen neben deine. Deine Bearbeitungen bleiben, wie sie sind. Gültig machen kannst nur du.', who.name)],
        ['delete', t('Punkte in deinen Papierkorb legen'), t('Nur du holst sie zurück.')],
        ['own_points', t('eigene Punkte in die Liste setzen'),
            t('Dann arbeitet ihr gemeinsam an der Liste. Was %s hineinsetzt, gehört weiter %s. Die Liste steht dann unter „Gemeinsame Listen", und ihr habt einen Chat dazu.', who.name, who.name)],
    ];
    return rows;
}

function has(rights, key) {
    return rights.has(key) || (key.startsWith('field:') && rights.has('field:*'));
}

function openRights(who) {
    const current = recipientsOf(shown).get(keyOf(who));
    if (!current) return;
    editing = { who, rights: new Set(current.rights ?? []), filter: current.filter ?? null };
    const listName = shown === root.id ? root.name
        : (overviews.get(root.id)?.sublists ?? []).find(s => s.list === shown)?.name ?? root.name;
    document.getElementById('recipient-rights-heading').textContent = t('„%s" für %s', listName, who.name);
    editing.listName = listName;
    drawRights();
    drawFilterLine();
    rightsWindow.showModal();
}

/** What the filter lets through, in one line — with the cross that lifts it. */
function drawFilterLine() {
    const line = document.getElementById('recipient-filter-line');
    line.textContent = '';
    const text = editing.filter ? shareFilter.readable(editing.filter) : null;
    if (!text) {
        line.textContent = t('Kein Filter: %s sieht alle Punkte der Liste.', editing.who.name);
        line.classList.remove('is-set');
        return;
    }
    line.classList.add('is-set');
    const chip = document.createElement('span');
    chip.className = 'recipient-filter-chip';
    chip.textContent = t('Filter: %s', text);
    const cross = document.createElement('button');
    cross.type = 'button';
    cross.className = 'symbol-button small';
    cross.title = t('Filter aufheben');
    cross.setAttribute('aria-label', cross.title);
    cross.append(icon('close'));
    cross.addEventListener('click', () => { editing.filter = null; drawFilterLine(); });
    line.append(chip, cross);
}

document.getElementById('recipient-filter-open').addEventListener('click', async () => {
    if (!editing) return;
    const result = await shareFilter.edit({
        title: t('Filter für %s', editing.who.name),
        value: editing.filter,
        listPoints: pointsOf(shown),
    });
    if (result === undefined || !editing) return;
    editing.filter = result;
    drawFilterLine();
});

function drawRights() {
    const table = document.getElementById('recipient-rights-table');
    table.textContent = '';
    const head = document.createElement('thead');
    head.innerHTML = '<tr><th></th><th class="recipient-allowed"></th></tr>';
    head.querySelector('th').textContent = t('%s darf …', editing.who.name);
    head.querySelector('.recipient-allowed').textContent = t('erlaubt');
    table.append(head);
    const body = document.createElement('tbody');

    // „Sehen" is always on — without it there would be no share.
    body.append(rightRow(t('die Punkte sehen, mit allen Bildern'),
        t('Immer an. Messen kann man auf jedem Bild, das man sieht — aber nur für sich.'), 'on', null));
    for (const [key, label, note] of rightRows(editing.who)) {
        body.append(rightRow(label, note ?? null, has(editing.rights, key) ? 'on' : 'off', () => {
            if (has(editing.rights, key)) {
                // Taking one field out of „alle Felder" spells the others out.
                if (key.startsWith('field:') && editing.rights.has('field:*')) {
                    editing.rights.delete('field:*');
                    for (const f of overviews.get(shown)?.fields ?? []) editing.rights.add('field:' + f);
                }
                editing.rights.delete(key);
            } else {
                editing.rights.add(key);
            }
            drawRights();
        }));
    }
    table.append(body);
}

function rightRow(label, note, tick, onToggle) {
    const tr = document.createElement('tr');
    const text = document.createElement('td');
    text.textContent = label;
    if (note) {
        const n = document.createElement('span');
        n.className = 'recipient-right-note';
        n.textContent = note;
        text.append(n);
    }
    const cell = document.createElement('td');
    cell.className = 'check-cell';
    cell.append(tickBox(tick, label, label, onToggle));
    if (onToggle) {
        text.classList.add('is-clickable');
        text.addEventListener('click', onToggle);
    }
    tr.append(text, cell);
    return tr;
}

document.getElementById('recipient-all').addEventListener('click', () => {
    if (!editing) return;
    for (const k of RIGHTS) editing.rights.add(k);
    editing.rights.add('field:*');
    drawRights();
});

document.getElementById('recipient-rights-cancel').addEventListener('click', () => {
    editing = null;
    rightsWindow.close();
});

rightsWindow.querySelector('form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!editing) return;
    const map = new Map(recipientsOf(shown));
    const key = keyOf(editing.who);
    const before = new Set(map.get(key)?.rights ?? []);
    const after = [...editing.rights];
    const points = overviews.get(shown)?.points ?? 0;
    const wider = (after.includes('own_points') && !before.has('own_points'))
        || (allowsAll(after) && !allowsAll([...before]));
    if (wider && points >= BIG_LIST) {
        const yes = await confirm({
            title: t('Wirklich so viel erlauben?'),
            text: t('Deine Liste hat %d Punkte. %s darf sie danach alle ändern.', points, editing.who.name),
            button: t('Erlauben'),
        });
        if (!yes) return;
    }
    map.set(key, { ...map.get(key), rights: after, filter: editing.filter });
    editing = null;
    rightsWindow.close();
    // „Übernehmen" is the confirmation — applied at once, like a tick.
    await commit(shown, map);
});

// ---------------------------------------------------------------------------
// Saving — after the list itself
// ---------------------------------------------------------------------------

/** What changed and was not sent yet — since every change commits at once, normally nothing. */
export function collect() {
    const out = [];
    for (const [listId, map] of pending) {
        out.push({
            list: listId,
            recipients: [...map.values()].map(r => ({ type: r.type, id: r.id, rights: r.rights ?? [], filter: r.filter ?? null })),
        });
    }
    return out;
}

/**
 * Send what collect() gathered. Resolves with what the server says it did
 * — a message for the status line — or throws with its refusal.
 */
export async function send(entries) {
    let added = 0;
    let removed = 0;
    let home = 0;
    for (const e of entries) {
        const answer = await request('list_recipients.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'save', list: e.list, recipients: e.recipients }),
        });
        added += answer?.added ?? 0;
        removed += answer?.removed ?? 0;
        home += answer?.home ?? 0;
    }
    return { added, removed, home };
}
