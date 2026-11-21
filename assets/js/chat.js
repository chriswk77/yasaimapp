/**
 * The chat — of a container, and of a single friend.
 *
 * ONE WINDOW, ONE POLL, TWO KINDS OF ROOM. The personal chat
 * (22.08.2026) is the container chat with a friendship as the room: the
 * same endpoint decides by whether {container: id} or {friend: id}
 * travels along, and everything in here carries that ROOM object instead
 * of a bare container id. The one visible difference: a conversation
 * between two people gets no "Wer liest mit" column — it would name
 * exactly the person one is talking to.
 *
 * WHO MAY READ IS DECIDED BY THE ROOM, and the server decides it on
 * every call (includes/chat.php). Nothing here is a boundary; this is the
 * view onto it.
 *
 * IT ASKS, IT IS NOT TOLD. There is no push and no socket — the map is a
 * page one keeps open for hours in the woods, and a connection held open
 * for that long costs battery for something that happens three times a
 * day. So: a question every few seconds while the window is open, and
 * nothing at all while it is closed.
 *
 * THE QUESTION CARRIES THE HIGHEST NUMBER ONE ALREADY HOLDS, so the usual
 * answer is an empty list. What still comes along is the current number of
 * the container — a device that has fallen behind learns from it that it
 * has, without the conversation being sent every time.
 */

import { api } from './api.js';
import { t } from './i18n.js';
import { icon } from './icons.js';
import { shortName, shortNames } from './names.js';
import { settings } from './settings.js';

/** How often the open window asks. Seconds, not milliseconds — see below. */
const POLL_SECONDS = 5;

/**
 * ONLY WHILE VISIBLE. A phone in a pocket has the page open and asks
 * nothing: document.hidden is true there, and every ask would be a request
 * for a screen nobody is looking at. On returning it asks once immediately,
 * so the conversation is current before the thumb arrives.
 */
let timer   = null;
let openFor = null;   // the room the window shows — {container} or {friend} — or null
let highest = 0;      // the highest number drawn

/* Two rooms are the same when both parts agree — undefined === undefined
   included, which is exactly why this is not a bare id comparison: a
   container and a friend that happen to share a number are different
   rooms. */
const sameRoom = (a, b) => !!a && !!b && a.container === b.container && a.friend === b.friend;

const windowEl = document.getElementById('chat-window');
const listEl   = document.getElementById('chat-messages');
const formEl   = document.getElementById('chat-form');
const textEl   = document.getElementById('chat-text');
const headEl    = document.getElementById('chat-heading');
const membersEl = document.getElementById('chat-members');
const peopleEl  = document.getElementById('chat-people');

/* THE COLUMN IS REALLY OPEN, not just styled open.
 *
 * On the desktop the stylesheet hides the summary and shows the list — but
 * that means overruling how the browser hides the content of a closed
 * <details>, and the browsers do not do that the same way. Chromium let
 * the author rule through, Firefox did not: the maintainer saw an empty column
 * (20.08.2026), and here everything measured fine.
 *
 * So the shape stays with the stylesheet and the STATE moves here: above
 * the phone breakpoint the details is opened for real, and there is
 * nothing left to overrule. The 860 stands in two places now, which is one
 * too many — but a number in two places is cheaper than a column that is
 * empty in one browser.
 */
const wide = matchMedia('(min-width: 861px)');
function applyWide() {
    // Assigned, not only opened: on the phone the fold-out starts closed
    // — a column would take a third of the screen away from the very thing
    // one opened the window for.
    peopleEl.open = wide.matches;
}
wide.addEventListener('change', applyWide);

/* WHOEVER DRAWS A BADGE registers here. It became a set the day the
   speech bubble arrived: the sidebar rows and the bubble both listen, and
   a single slot would mean the later one silently unhooks the earlier. */
const badgeListeners = new Set();
export function onUnreadChange(f) {
    badgeListeners.add(f);
}
function tellBadges() {
    for (const f of badgeListeners) { try { f(); } catch { /* one bad drawer must not stop the rest */ } }
}

/** The unread counts, kept here so every drawer has one source. */
const unread = new Map();          // containerId -> count
const friendUnread = new Map();    // friend userId -> count
/* OF THOSE, HOW MANY SOMEBODY WROTE (10.10.2026): the rest are
   the map's own lines — „Finn hat … geteilt", a change in a joint list.
   Written ones light the dot in full and the header's bubble; status lines
   only a quiet dot at their own place. */
const unreadPersonal = new Map();
const friendPersonal = new Map();

/* THE OPEN FRIEND REQUESTS, kept here for the same reason as the counts
   above: they arrive in the very same poll (api/v1/chat.php), and
   whoever draws them is told through the same listener. A list and not a
   number, because the badge over the map names who is asking.

   That this lives in a file called "chat" is the price of ONE timer. The
   alternative was a second poll for the same moment, and this file
   already carries the friends' unread counts for exactly that reason. */
let requests = [];

/** The open requests to me: [{id, name}, …]. */
export function friendRequests() {
    return requests;
}

/** After answering one, the endpoint says what is left — no waiting for
 *  the next poll. */
export function setFriendRequests(list) {
    requests = Array.isArray(list) ? list : [];
    tellBadges();
}

/* THE ANSWERS TO MY OWN REQUESTS (13.09.2026, „Bekomme ich
   eine Matchnachricht, wenn eine Freundschaftsanfrage angenommen
   wurde?"). The same poll carries them, the same listener draws them,
   the same reason puts them in this file. [{id, name}], each an accepted
   friendship I asked for and have not taken note of yet. */
let accepted = [];

export function friendAccepted() {
    return accepted;
}

export function setFriendAccepted(list) {
    accepted = Array.isArray(list) ? list : [];
    tellBadges();
}

/** The requests the document brought — before the first poll, without a redraw. */
export function adoptRequests(list) {
    requests = Array.isArray(list) ? list : [];
}

/** What the document brought — before the first poll, without a redraw. */
export function adoptAccepted(list) {
    accepted = Array.isArray(list) ? list : [];
}

export function setUnread(containerId, count) {
    unread.set(containerId, count);
    if (count === 0) unreadPersonal.set(containerId, 0);
    tellBadges();
}

/** How many of a container's unread lines somebody wrote himself. */
export function unreadPersonalFor(containerId) {
    return unreadPersonal.get(containerId) ?? 0;
}

export function unreadFor(containerId) {
    return unread.get(containerId) ?? 0;
}

export function setFriendUnread(friendId, count) {
    friendUnread.set(friendId, count);
    if (count === 0) friendPersonal.set(friendId, 0);
    tellBadges();
}

/** How many of a friend's unread lines he wrote himself. */
export function friendPersonalFor(friendId) {
    return friendPersonal.get(friendId) ?? 0;
}

export function friendUnreadFor(friendId) {
    return friendUnread.get(friendId) ?? 0;
}

/** Has anybody WRITTEN something? The header bubble's dot lives off this —
 *  status lines alone leave it dark (10.10.2026). */
export function friendUnreadAny() {
    for (const n of friendPersonal.values()) {
        if (n > 0) return true;
    }
    return false;
}

/** Read the counts out of the start data — once, when the map comes up. */
export function adoptStart(containers) {
    for (const c of containers ?? []) {
        unread.set(c.id, c.chat?.unread ?? 0);
        unreadPersonal.set(c.id, c.chat?.personal ?? c.chat?.unread ?? 0);
    }
    tellBadges();
}

export function adoptFriends(friends) {
    for (const f of friends ?? []) {
        friendUnread.set(f.id, f.chat?.unread ?? 0);
        friendPersonal.set(f.id, f.chat?.personal ?? f.chat?.unread ?? 0);
    }
    tellBadges();
}

/**
 * Put a name into the writing field, with an @ in front.
 *
 * AT THE CARET AND NOT AT THE END. Whoever is in the middle of a sentence
 * and taps a name means it there — appending it at the end would make him
 * cut and paste in a field the size of one line.
 *
 * NOTHING PARSES THIS AFTERWARDS. The @ is a convention between the people
 * in this container, not a mechanism: nobody is notified, nothing is
 * linked. It says "I mean you", and among a handful of people that is all
 * it has to do. Anything more would be a second address book beside the
 * container's membership.
 */
function insertMention(name) {
    const at     = textEl.selectionStart ?? textEl.value.length;
    const before = textEl.value.slice(0, at);
    const after  = textEl.value.slice(textEl.selectionEnd ?? at);

    // A space in front, unless there is one already or the field is empty.
    const piece = (before !== '' && !/\s$/.test(before) ? ' ' : '') + '@' + name + ' ';

    textEl.value = before + piece + after;
    const caret = (before + piece).length;
    textEl.setSelectionRange(caret, caret);

    // autoheight.js listens for 'input' — without this the field would not
    // grow when a name pushes it into the second line.
    textEl.dispatchEvent(new Event('input', { bubbles: true }));

    // On the phone the fold-out has done its job and takes room the
    // conversation wants back.
    if (!wide.matches) peopleEl.open = false;

    textEl.focus();
}

/**
 * A message as one row.
 *
 * MINE ON THE RIGHT, THE OTHERS ON THE LEFT — the oldest habit of every
 * chat, and the one thing that lets you tell at a glance who is speaking
 * without reading a name. Which is why one's own rows carry no name at
 * all: it would be the same word on every second row.
 */
function row(message, me, company) {
    const el = document.createElement('div');
    el.className = 'chat-message' + (message.user_id === me ? ' is-mine' : '');
    // Its number — a folded change line names the one it replaces (0121).
    el.dataset.id = String(message.id);

    /* A CHANGE LINE (joint lists, 22.09.2026): what the map wrote about a
       point of a joint list — „Ben hat „Boletus edulis" geändert: Titel".
       Quiet, in one line with the actor's name in front, never in a
       bubble: it is a fact of the share, not something somebody said. */
    if (message.kind === 'change') {
        el.className = 'chat-message is-change';
        const text = document.createElement('div');
        text.className = 'chat-text';
        // The actor's name — mine too: the line is written in the third
        // person („hat"), and „Du hat" would be a sentence nobody says.
        const who = shortName(message.display_name, company);
        /* THE NAME IN ITS OWN QUIET COLOUR (23.09.2026: „damit
           man schneller erkennt, wo der Name ist, was der Text ist, wer was
           gemacht hat"): a span of its own, the sentence stays one line. */
        const name = document.createElement('span');
        name.className = 'chat-change-who';
        name.textContent = who;
        text.append(name, ' ' + message.text);
        text.title = message.display_name + ' ' + message.text;
        el.append(text);
        const when = document.createElement('div');
        when.className = 'chat-when';
        when.textContent = readableTime(message.created_at);
        el.append(when);
        return el;
    }

    if (message.user_id !== me) {
        // The author's name over a message does the same as the one in the
        // column: it is the name one has in front of one's eyes at the
        // moment one wants to answer somebody.
        const who = document.createElement('button');
        who.type = 'button';
        who.className = 'chat-author';
        const short = shortName(message.display_name, company);
        who.textContent = short;
        who.title = t('%s — antworten', message.display_name);
        who.addEventListener('click', () => insertMention(short));
        el.append(who);
    }

    const text = document.createElement('div');
    text.className = 'chat-text';
    // As a text node and never as markup: what somebody writes is text,
    // and a chat is the one place where that has to hold without exception.
    text.textContent = message.text;
    el.append(text);

    const when = document.createElement('div');
    when.className = 'chat-when';
    when.textContent = readableTime(message.created_at);
    el.append(when);

    return el;
}

/**
 * The time of a message, as short as it may be.
 *
 * Today only the clock, this year without the year — a conversation that
 * one reads from the bottom up needs the day, not the date. The full
 * moment stands in the title attribute for whoever wants it.
 */
function readableTime(stamp) {
    const when = new Date(String(stamp).replace(' ', 'T'));
    if (Number.isNaN(when.getTime())) return '';

    const now  = new Date();
    const same = (a, b) => a.toDateString() === b.toDateString();
    const clock = when.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });

    if (same(when, now)) return clock;

    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);
    if (same(when, yesterday)) return t('gestern %s', clock);

    return when.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }) + ' ' + clock;
}

/** Is the conversation scrolled to the bottom? Decides whether it follows. */
function atBottom() {
    return listEl.scrollHeight - listEl.scrollTop - listEl.clientHeight < 40;
}

/**
 * Fetch and append what is new.
 *
 * @param {boolean} force  draw from scratch, for the first opening
 */
async function fetchNew(room, me, company, force = false) {
    let answer;
    try {
        answer = await api.get('chat', { ...room, since: force ? 0 : highest });
    } catch {
        // No network, or the migration is not in yet. Silence is right
        // here: the window stands there with what it has, and the next
        // attempt comes in five seconds by itself.
        return;
    }
    if (!sameRoom(openFor, room)) return;   // closed in the meantime

    if (force) listEl.replaceChildren();

    const stick = force || atBottom();
    for (const m of answer.messages ?? []) {
        /* A FOLDED CHANGE LINE REPLACES ITS PREDECESSOR (24.09.2026): the
           server deleted the old line and wrote one that says both — an
           open chat drops the old one instead of showing the two. */
        if (m.replaces) listEl.querySelector(`[data-id="${m.replaces}"]`)?.remove();
        listEl.append(row(m, me, company));
        // Folded on the desktop: the head says that somebody wrote.
        if (!force && m.user_id !== me && windowEl.classList.contains('is-minimized')) {
            windowEl.classList.add('has-news');
        }
        highest = Math.max(highest, m.id);
    }

    if ((answer.messages ?? []).length > 0 && stick) {
        listEl.scrollTop = listEl.scrollHeight;
    }

    // What is shown is read. Reporting it to the server AND clearing the
    // badge here — the two must not wait for each other, or the dot stays
    // on a phone with no reception although one is looking at the message.
    const state = answer.latest ?? highest;
    if (state > 0) {
        if (room.container !== undefined) setUnread(room.container, 0);
        else setFriendUnread(room.friend, 0);
        /* ONLY WHEN THERE IS SOMETHING NEW TO MARK (23.09.2026:
           „bei jedem Polling blinkt kurz eine Badge auf — einer, lokal").
           The mark went out with EVERY five-second poll, and a writing
           call that fails on the way — thin reception in the woods — is
           enqueued: the queue's counter then showed „1" until the retry
           got through, twelve times a minute. The server's answer does
           not change while nothing arrives, so nothing has to be said. */
        const key = room.container !== undefined ? 'c' + room.container : 'f' + room.friend;
        if (state > (markedUpTo.get(key) ?? 0)) {
            markedUpTo.set(key, state);
            api.post('chat', { ...room, read: state }).catch(() => {});
        }
    }
}

/** The highest message id each room was reported read up to, this visit. */
const markedUpTo = new Map();

/**
 * THE QUIET ASKING, while no window is open.
 *
 * The dot on the sidebar row exists for exactly the moment when one is NOT
 * in the chat — so somebody has to ask then too. Rarely, though: this runs
 * as long as the map is open, and the answer is almost always "nothing".
 * Half a minute is often enough for a conversation among a handful of
 * people and seldom enough to be felt.
 *
 * It stops while the chat window is open. That one asks every five seconds
 * anyway and reports what it shows as read — two askers would only get in
 * each other's way.
 */
const BADGE_SECONDS = 30;
let badgeTimer = null;

async function fetchUnread() {
    if (document.hidden || openFor !== null) return;
    try {
        const answer = await api.get('chat', { unread: 1 });

        /* Containers and friends are the same bookkeeping over two maps —
           including the sweep: what is no longer in the answer has
           nothing waiting, otherwise a dot would stay standing after
           somebody read on another device. */
        const take = (store, personalStore, incoming) => {
            let changed = false;
            for (const [id, entry] of Object.entries(incoming ?? {})) {
                const n = entry?.unread ?? 0;
                // An older server sends no split — then all of it counts as written.
                const p = entry?.personal ?? n;
                if (store.get(Number(id)) !== n) { store.set(Number(id), n); changed = true; }
                if (personalStore.get(Number(id)) !== p) { personalStore.set(Number(id), p); changed = true; }
            }
            for (const id of store.keys()) {
                if (!(id in (incoming ?? {})) && store.get(id) !== 0) {
                    store.set(id, 0); personalStore.set(id, 0); changed = true;
                }
            }
            return changed;
        };

        const changed = take(unread, unreadPersonal, answer.unread);
        // Not short-circuited on purpose: both stores must be swept.
        const friendsChanged = take(friendUnread, friendPersonal, answer.friends);

        /* Compared as text, and that is enough: the list is short, comes
           sorted by name from the server, and carries only id and name.
           Anything finer would be more code for a question nobody asks —
           whether it changed, not how. */
        const fresh = JSON.stringify(answer.friendRequests ?? []);
        const requestsChanged = fresh !== JSON.stringify(requests);
        if (requestsChanged) requests = JSON.parse(fresh);
        const freshAccepted = JSON.stringify(answer.friendAccepted ?? []);
        const acceptedChanged = freshAccepted !== JSON.stringify(accepted);
        if (acceptedChanged) accepted = JSON.parse(freshAccepted);

        if (changed || friendsChanged || requestsChanged || acceptedChanged) tellBadges();
    } catch { /* no network — the next attempt comes by itself */ }
}

export function watchUnread() {
    if (badgeTimer !== null) clearInterval(badgeTimer);
    badgeTimer = setInterval(fetchUnread, BADGE_SECONDS * 1000);
}

/** Start the asking — only while the window is open and the page visible. */
function startPolling(room, me, company) {
    stopPolling();
    timer = setInterval(() => {
        if (document.hidden) return;
        fetchNew(room, me, company);
    }, POLL_SECONDS * 1000);
}

function stopPolling() {
    if (timer !== null) clearInterval(timer);
    timer = null;
}

// Coming back to the page: ask at once instead of waiting out the interval.
document.addEventListener('visibilitychange', () => {
    if (document.hidden) return;
    if (openFor !== null) fetchNew(openFor, ...openArgs);
    else fetchUnread();
});
let openArgs = [];

/**
 * Open the chat of a container.
 *
 * @param {object} container  the entry from the start data — id, name and
 *   members; the last one is both the list above the conversation and
 *   the company the author names are shortened against.
 * @param {number} me  my user id
 */
export function openChat(container, me, restoring = false) {
    openFor = { container: container.id };
    highest = 0;

    peopleEl.hidden = false;
    headEl.textContent = container.name;

    /* THE COMPANY IS THIS CONTAINER'S, not the whole sidebar's. Here the
       names of THESE members have to be told apart — a second Finn in some
       other container would lengthen the name for no reason, because he is
       not in the room. */
    const members = (container.members ?? []).map(m => m.display_name);
    const short   = shortNames(members);
    membersEl.replaceChildren(...(container.members ?? []).map((m) => {
        const li = document.createElement('li');
        const mine = m.id === me;
        if (mine) li.classList.add('is-me');

        const label = mine ? 'du' : (short.get(m.display_name) ?? m.display_name);

        /* ONE'S OWN ENTRY IS NOT A BUTTON. Everybody else can be addressed;
           oneself cannot, and a control that does nothing is worse than
           none — one taps it, nothing happens, and one taps again. */
        const inner = mine ? document.createElement('span') : document.createElement('button');
        if (!mine) {
            inner.type = 'button';
            inner.addEventListener('click', () => insertMention(label));
        }
        inner.className = 'chat-person';
        inner.append(icon('person'), document.createTextNode(label));
        // The full name in the title: the column is narrow, the tooltip is
        // not, and this way the short form costs nobody the information.
        inner.title = mine ? m.display_name : t('%s — ansprechen', m.display_name);

        li.append(inner);
        return li;
    }));

    // The authors in the conversation are measured against the SAME
    // company as the row above — otherwise the same person would state
    // there as "Finn" and in the messages as "Finn H."
    openArgs = [me, members];

    applyWide();

    listEl.replaceChildren();
    textEl.value = '';

    present({ kind: 'container', me, container: {
        id: container.id, name: container.name,
        members: (container.members ?? []).map(m => ({ id: m.id, display_name: m.display_name })),
    } });
    fetchNew(openFor, me, members, true).then(() => { if (!restoring) textEl.focus(); });
    startPolling(openFor, me, members);
}

/**
 * Open the personal conversation with one friend.
 *
 * @param {object} friend  the entry from the start data — id and name
 * @param {number} me  my user id
 *
 * NO MEMBERS COLUMN. Between two people it would name exactly the person
 * one is talking to — the heading already does that, and better. The
 * company the author names shorten against is that one name; one's own
 * rows carry no name anyway (row() above).
 */
export function openFriendChat(friend, me, restoring = false) {
    openFor = { friend: friend.id };
    highest = 0;

    peopleEl.hidden = true;
    peopleEl.open = false;
    headEl.textContent = t('Chat mit %s', friend.name);
    membersEl.replaceChildren();

    const company = [friend.name];
    openArgs = [me, company];

    listEl.replaceChildren();
    textEl.value = '';

    present({ kind: 'friend', me, friend: { id: friend.id, name: friend.name } });
    fetchNew(openFor, me, company, true).then(() => { if (!restoring) textEl.focus(); });
    startPolling(openFor, me, company);
}

/* ---- The floating chat on the desktop ---------------------------------------

   IT OUTLIVES THE PAGE (14.10.2026: „bleibt das Chatfenster
   immer geöffnet … während ich zwischen den verschiedenen Hauptbereichen
   wechsle"; and the reason: „Somit könnte ich auch Support leisten für
   einen meiner Freunde"). Karte, Fundbrowser, Bestimmung and Verwaltung
   are separate documents, so the window cannot really stay; it is
   REMEMBERED instead — which room, folded or not, and the unsent text —
   in sessionStorage, the tab's own memory, and reopened at once by
   whichever page loads next (the restore at the end of this file).
   Closing with „✕" forgets it.

   WHERE IT STANDS AND HOW BIG IT IS, IS THE WINDOW'S, NOT THE CHAT'S
   (15.11.2026: „das Chatfenster merkt sich nicht Position und
   Größe. Also es braucht sich nicht pro Chat die Position und Größe
   merken, aber zumindest mal vom Fenster selbst"). There is one window;
   another chat is loaded into it. Until then place and size lay in the
   same sessionStorage entry as the room — „✕" threw them away with it,
   and a new tab never had them. Now they are a setting of the device
   (settings, 'chatWindow') and outlive the closing, the tab and the day.

   NOT MODAL above the phone breakpoint: show(), not showModal(), so the
   page behind stays usable — the header's buttons included. The phone
   keeps the full-screen modal it had: there the areas are not reachable
   beside a chat anyway („Handy … ist ja immer Fullscreen"). */
const FLOAT_KEY = 'chat.floating';
const minimizeEl = document.getElementById('chat-minimize');
const headBar = windowEl.querySelector('.chat-head');

function floatState() {
    try { return JSON.parse(sessionStorage.getItem(FLOAT_KEY) ?? 'null'); } catch { return null; }
}
function saveFloat(patch) {
    try { sessionStorage.setItem(FLOAT_KEY, JSON.stringify({ ...(floatState() ?? {}), ...patch })); } catch { /* private window */ }
}
function forgetFloat() {
    try { sessionStorage.removeItem(FLOAT_KEY); } catch { /* private window */ }
}

/** Place and size as the device keeps them — what an open chat of before the change still carries counts once. */
function geometry() {
    const kept = settings.get('chatWindow', null);
    if (kept && typeof kept === 'object') return kept;
    const { x, y, w, h } = floatState() ?? {};
    return { x, y, w, h };
}
function saveGeometry(patch) {
    settings.set('chatWindow', { ...geometry(), ...patch });
}

/** Where it stood and how big it was, kept inside the window's edges. */
function applyGeometry() {
    const g = geometry();
    const st = windowEl.style;
    if (g.w && g.h) {
        st.setProperty('--chat-w', Math.min(g.w, innerWidth - 16) + 'px');
        st.setProperty('--chat-h', Math.min(g.h, innerHeight - 16) + 'px');
    }
    if (g.x != null && g.y != null) {
        const w = Math.min(g.w ?? 416, innerWidth - 16);
        st.setProperty('--chat-x', Math.max(8, Math.min(g.x, innerWidth - w - 8)) + 'px');
        st.setProperty('--chat-y', Math.max(8, Math.min(g.y, innerHeight - 60)) + 'px');
        st.setProperty('--chat-right', 'auto');
        st.setProperty('--chat-bottom', 'auto');
    }
}

function setMinimized(on) {
    windowEl.classList.toggle('is-minimized', on);
    if (!on) windowEl.classList.remove('has-news');
    minimizeEl?.setAttribute('aria-expanded', String(!on));
    if (minimizeEl) {
        minimizeEl.title = on ? t('Aufklappen') : t('Zuklappen');
        minimizeEl.setAttribute('aria-label', on ? t('Chat aufklappen') : t('Chat zuklappen'));
    }
}

/** Open the window in the shape the width calls for, and remember the room. */
function present(room) {
    if (wide.matches) {
        windowEl.classList.add('is-floating');
        applyGeometry();
        if (windowEl.open && windowEl.matches(':modal')) windowEl.close();
        if (!windowEl.open) windowEl.show();
        const keep = floatState();
        // A different room starts unfolded and without the old draft.
        const same = keep?.room && JSON.stringify(keep.room.friend ?? keep.room.container?.id)
            === JSON.stringify(room.friend ?? room.container?.id) && keep.room.kind === room.kind;
        saveFloat({ room, ...(same ? {} : { min: false, draft: '' }) });
        if (!same) setMinimized(false);
    } else {
        windowEl.classList.remove('is-floating', 'is-minimized', 'has-news');
        if (windowEl.open && !windowEl.matches(':modal')) windowEl.close();
        if (!windowEl.open) windowEl.showModal();
    }
}

/* DRAGGED BY ITS HEAD. A press that does not travel is a click — on a
   folded window it unfolds it, so the head is the one target there. */
headBar.addEventListener('pointerdown', (e) => {
    if (!windowEl.classList.contains('is-floating') || e.button !== 0 || e.target.closest('button')) return;
    const box = windowEl.getBoundingClientRect();
    const dx = e.clientX - box.left, dy = e.clientY - box.top;
    let moved = false;
    headBar.setPointerCapture(e.pointerId);
    const move = (ev) => {
        if (!moved && Math.hypot(ev.clientX - e.clientX, ev.clientY - e.clientY) < 4) return;
        moved = true;
        const st = windowEl.style;
        st.setProperty('--chat-x', Math.max(0, Math.min(ev.clientX - dx, innerWidth - box.width)) + 'px');
        st.setProperty('--chat-y', Math.max(0, Math.min(ev.clientY - dy, innerHeight - 40)) + 'px');
        st.setProperty('--chat-right', 'auto');
        st.setProperty('--chat-bottom', 'auto');
    };
    const up = () => {
        headBar.removeEventListener('pointermove', move);
        headBar.removeEventListener('pointerup', up);
        headBar.removeEventListener('pointercancel', up);
        if (moved) {
            const b = windowEl.getBoundingClientRect();
            saveGeometry({ x: Math.round(b.left), y: Math.round(b.top) });
        } else if (windowEl.classList.contains('is-minimized')) {
            setMinimized(false);
            saveFloat({ min: false });
        }
    };
    headBar.addEventListener('pointermove', move);
    headBar.addEventListener('pointerup', up);
    headBar.addEventListener('pointercancel', up);
});

// The size the corner grip gave it — not while folded, that is no size.
let sizeTimer = null;
new ResizeObserver(() => {
    if (!windowEl.open || !windowEl.classList.contains('is-floating') || windowEl.classList.contains('is-minimized')) return;
    clearTimeout(sizeTimer);
    sizeTimer = setTimeout(() => {
        const b = windowEl.getBoundingClientRect();
        if (b.width > 0 && b.height > 0) {
            windowEl.style.setProperty('--chat-w', Math.round(b.width) + 'px');
            windowEl.style.setProperty('--chat-h', Math.round(b.height) + 'px');
            saveGeometry({ w: Math.round(b.width), h: Math.round(b.height) });
        }
    }, 250);
}).observe(windowEl);

minimizeEl?.addEventListener('click', () => {
    const on = !windowEl.classList.contains('is-minimized');
    setMinimized(on);
    saveFloat({ min: on });
});

// The unsent text travels to the next page.
textEl.addEventListener('input', () => {
    if (windowEl.classList.contains('is-floating')) saveFloat({ draft: textEl.value });
});

function close() {
    stopPolling();
    openFor = null;
    forgetFloat();
    if (windowEl.open) windowEl.close();
}

document.getElementById('chat-close').addEventListener('click', close);
windowEl.addEventListener('close', () => { stopPolling(); openFor = null; });

formEl.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = textEl.value.trim();
    if (text === '' || openFor === null) return;

    // EMPTIED BEFORE SENDING, not after. Out in the woods the answer takes
    // seconds, and a field that still holds what one has written invites
    // sending it a second time. If it fails, the text comes back.
    textEl.value = '';
    const room = openFor;
    try {
        saveFloatDraftCleared();
        const answer = await api.post('chat', { ...room, text });
        if (sameRoom(openFor, room) && answer?.message) {
            listEl.append(row(answer.message, ...openArgs));
            highest = Math.max(highest, answer.message.id);
            listEl.scrollTop = listEl.scrollHeight;
        }
    } catch (err) {
        textEl.value = text;
        // The reason belongs next to the field, not in the console — one is
        // standing in front of a message that did not go out.
        headEl.title = err?.message ?? '';
        textEl.setAttribute('aria-invalid', 'true');
        setTimeout(() => textEl.removeAttribute('aria-invalid'), 3000);
    }
    textEl.focus();
});

/* ENTER SENDS, SHIFT+ENTER MAKES A LINE. On the phone the keyboard's
   return key inserts a line — there one sends with the button, and that is
   why the button is there at all rather than only a hint. */
textEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !matchMedia('(max-width: 860px)').matches) {
        e.preventDefault();
        formEl.requestSubmit();
    }
});

function saveFloatDraftCleared() {
    if (windowEl.classList.contains('is-floating')) saveFloat({ draft: '' });
}

/* THE RESTORE: the page before had a floating chat open — open it again,
   folded or not, with what was being typed, and without taking the focus
   from the page that just loaded. Only on the desktop; on the phone the
   remembered window waits until the screen is wide again. */
{
    const kept = floatState();
    if (kept?.room && wide.matches) {
        const r = kept.room;
        if (r.kind === 'friend' && r.friend) openFriendChat(r.friend, r.me, true);
        else if (r.kind === 'container' && r.container) openChat(r.container, r.me, true);
        setMinimized(!!kept.min);
        if (kept.draft) textEl.value = kept.draft;
    }
}
