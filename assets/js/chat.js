/**
 * The chat — of a container, and of a single friend.
 *
 * ONE WINDOW PER ROOM, TWO KINDS OF ROOM. The personal chat
 * (22.08.2026) is the container chat with a friendship as the room: the
 * same endpoint decides by whether {container: id} or {friend: id}
 * travels along, and everything in here carries that ROOM object instead
 * of a bare container id. The one visible difference: a conversation
 * between two people gets no "Wer liest mit" column — it would name
 * exactly the person one is talking to.
 *
 * SEVERAL WINDOWS AT THE DESK (27.11.2026: „Mehrfachchats erlauben …
 * mehrere Chatfenster mit verschiedenen Personen offen halten"). Until
 * then there was one window and another chat was loaded into it. Now
 * every room has a window of its own — a copy of the page's dialog — with
 * its own poll, its own draft and its own place; they line up along the
 * bottom edge from the right, like a messenger's. The phone keeps ONE
 * full-screen window: there a second one could only lie under the first.
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

/* Two rooms are the same when both parts agree — undefined === undefined
   included, which is exactly why this is not a bare id comparison: a
   container and a friend that happen to share a number are different
   rooms. The key says the same as one string, for the map of windows. */
const sameRoom = (a, b) => !!a && !!b && a.container === b.container && a.friend === b.friend;
const roomKey = (r) => (r.container !== undefined ? 'c' + r.container : 'f' + r.friend);

/** The page's dialog: the first window, and the pattern of every further one. */
const template = document.getElementById('chat-window');

/** Every open window by its room's key, in the order they were opened. */
const wins = new Map();

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
function applyWide(w) {
    // Assigned, not only opened: on the phone the fold-out starts closed
    // — a column would take a third of the screen away from the very thing
    // one opened the window for.
    if (!w.people.hidden) w.people.open = wide.matches;
}
wide.addEventListener('change', () => { for (const w of wins.values()) applyWide(w); });

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
/* THE FRIENDS A CONVERSATION EXISTS WITH (27.11.2026) — the chat list
   draws a bubble with lines for them and an empty one for the rest. From
   the start data, and it only grows: a line that arrives or one sent here
   starts a conversation for good. */
const friendStarted = new Set();

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

/** Has anything been said with this friend yet? */
export function friendStartedWith(friendId) {
    return friendStarted.has(friendId) || (friendUnread.get(friendId) ?? 0) > 0;
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
        if (f.chat?.started) friendStarted.add(f.id);
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
function insertMention(w, name) {
    const field  = w.text;
    const at     = field.selectionStart ?? field.value.length;
    const before = field.value.slice(0, at);
    const after  = field.value.slice(field.selectionEnd ?? at);

    // A space in front, unless there is one already or the field is empty.
    const piece = (before !== '' && !/\s$/.test(before) ? ' ' : '') + '@' + name + ' ';

    field.value = before + piece + after;
    const caret = (before + piece).length;
    field.setSelectionRange(caret, caret);

    // autoheight.js listens for 'input' — without this the field would not
    // grow when a name pushes it into the second line.
    field.dispatchEvent(new Event('input', { bubbles: true }));

    // On the phone the fold-out has done its job and takes room the
    // conversation wants back.
    if (!wide.matches) w.people.open = false;

    field.focus();
}

/**
 * A message as one row.
 *
 * MINE ON THE RIGHT, THE OTHERS ON THE LEFT — the oldest habit of every
 * chat, and the one thing that lets you tell at a glance who is speaking
 * without reading a name. Which is why one's own rows carry no name at
 * all: it would be the same word on every second row.
 */
function row(message, me, company, w) {
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
        who.addEventListener('click', () => insertMention(w, short));
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
function atBottom(w) {
    return w.list.scrollHeight - w.list.scrollTop - w.list.clientHeight < 40;
}

/**
 * Fetch and append what is new — for one window.
 *
 * @param {boolean} force  draw from scratch, for the first opening
 */
async function fetchNew(w, force = false) {
    const room = w.room;
    if (!room) return;
    let answer;
    try {
        answer = await api.get('chat', { ...room, since: force ? 0 : w.highest });
    } catch {
        // No network, or the migration is not in yet. Silence is right
        // here: the window stands there with what it has, and the next
        // attempt comes in five seconds by itself.
        return;
    }
    if (!sameRoom(w.room, room)) return;   // closed or reused in the meantime

    if (force) w.list.replaceChildren();

    const stick = force || atBottom(w);
    for (const m of answer.messages ?? []) {
        /* A FOLDED CHANGE LINE REPLACES ITS PREDECESSOR (24.09.2026): the
           server deleted the old line and wrote one that says both — an
           open chat drops the old one instead of showing the two. */
        if (m.replaces) w.list.querySelector(`[data-id="${m.replaces}"]`)?.remove();
        w.list.append(row(m, w.me, w.company, w));
        // Folded on the desktop: the head says that somebody wrote.
        if (!force && m.user_id !== w.me && w.el.classList.contains('is-minimized')) {
            w.el.classList.add('has-news');
        }
        w.highest = Math.max(w.highest, m.id);
    }

    if ((answer.messages ?? []).length > 0 && stick) {
        w.list.scrollTop = w.list.scrollHeight;
    }

    // What is shown is read. Reporting it to the server AND clearing the
    // badge here — the two must not wait for each other, or the dot stays
    // on a phone with no reception although one is looking at the message.
    const state = answer.latest ?? w.highest;
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
        const key = roomKey(room);
        if (state > (markedUpTo.get(key) ?? 0)) {
            markedUpTo.set(key, state);
            api.post('chat', { ...room, read: state }).catch(() => {});
        }
    }
}

/** The highest message id each room was reported read up to, this visit. */
const markedUpTo = new Map();

/**
 * THE QUIET ASKING, for the dots.
 *
 * The dot on the sidebar row exists for exactly the moment when one is NOT
 * in the chat — so somebody has to ask then too. Rarely, though: this runs
 * as long as the map is open, and the answer is almost always "nothing".
 * Half a minute is often enough for a conversation among a handful of
 * people and seldom enough to be felt.
 *
 * IT GOES ON WHILE WINDOWS ARE OPEN (27.11.2026). It used to stop while
 * the one window was open, which asked every five seconds anyway — but
 * with that the dots of every OTHER conversation stood still as long as
 * one chatted. The rooms that have a window are left out of the answer
 * instead: their window reports them read, and two askers counting the
 * same room would make its dot flicker.
 */
const BADGE_SECONDS = 30;
let badgeTimer = null;

async function fetchUnread() {
    if (document.hidden) return;
    try {
        const answer = await api.get('chat', { unread: 1 });

        /* Containers and friends are the same bookkeeping over two maps —
           including the sweep: what is no longer in the answer has
           nothing waiting, otherwise a dot would stay standing after
           somebody read on another device. */
        const take = (store, personalStore, incoming, prefix) => {
            let changed = false;
            for (const [id, entry] of Object.entries(incoming ?? {})) {
                const open = wins.has(prefix + id);
                const n = open ? 0 : (entry?.unread ?? 0);
                // An older server sends no split — then all of it counts as written.
                const p = open ? 0 : (entry?.personal ?? n);
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

        const changed = take(unread, unreadPersonal, answer.unread, 'c');
        // Not short-circuited on purpose: both stores must be swept.
        const friendsChanged = take(friendUnread, friendPersonal, answer.friends, 'f');

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

/** Start one window's asking — only while it is open and the page visible. */
function startPolling(w) {
    stopPolling(w);
    w.timer = setInterval(() => {
        if (document.hidden) return;
        fetchNew(w);
    }, POLL_SECONDS * 1000);
}

function stopPolling(w) {
    if (w.timer !== null) clearInterval(w.timer);
    w.timer = null;
}

// Coming back to the page: ask at once instead of waiting out the interval.
document.addEventListener('visibilitychange', () => {
    if (document.hidden) return;
    for (const w of wins.values()) fetchNew(w);
    fetchUnread();
});

/* THE HEADING IN TWO LENGTHS: the full one for the open window, the bare
   name for the folded one, which shrinks to a small rectangle (chat.css,
   27.11.2026). Both stand in the heading and the stylesheet shows one —
   a sentence like „Chat mit %s" cannot be cut apart for the translation. */
function setHeading(w, full, short) {
    const a = document.createElement('span');
    a.className = 'chat-title-full';
    a.textContent = full;
    const b = document.createElement('span');
    b.className = 'chat-title-short';
    b.textContent = short;
    w.head.replaceChildren(a, b);
    w.head.title = '';
}

/* ---- One window ---------------------------------------------------------- */

/** The parts of a window, found by class — a copy has no ids. */
function makeWin(el) {
    const w = {
        el,
        list:     el.querySelector('.chat-messages'),
        form:     el.querySelector('.chat-write'),
        text:     el.querySelector('.chat-write textarea'),
        head:     el.querySelector('.chat-title'),
        headBar:  el.querySelector('.chat-head'),
        members:  el.querySelector('.chat-people-list'),
        people:   el.querySelector('.chat-people'),
        minimize: el.querySelector('.chat-minimize'),
        room: null,     // {container} or {friend}, null while the window is free
        me: null,
        company: [],
        highest: 0,     // the highest number drawn
        timer: null,
        entry: null,    // what the tab remembers of it — see the floating chat below
    };
    bindWin(w);
    resizeWatch.observe(el);
    return w;
}

/** The page's own dialog, the first window. */
let primary = null;

/**
 * The window for a room: the one it already has, or a free one.
 *
 * ON THE PHONE ALWAYS THE ONE: another room is loaded into it, as before
 * — full screen, a second window could only lie under the first.
 * AT THE DESK the page's dialog while it is free, else a copy of it.
 */
function windowFor(room) {
    primary ??= makeWin(template);
    const kept = wins.get(roomKey(room));
    if (kept) return { w: kept, fresh: false };
    if (!wide.matches) {
        if (primary.room) release(primary);
        return { w: primary, fresh: true };
    }
    if (!primary.room) return { w: primary, fresh: true };
    const el = template.cloneNode(true);
    // A copy without ids — two of the same id would be one too many — and
    // without whatever the first window carried at that moment.
    el.removeAttribute('id');
    for (const n of el.querySelectorAll('[id]')) n.removeAttribute('id');
    el.removeAttribute('open');
    el.removeAttribute('style');
    el.classList.remove('is-minimized', 'has-news');
    el.querySelector('.chat-messages').replaceChildren();
    template.after(el);
    return { w: makeWin(el), fresh: true };
}

/** A window lets go of its room — polling stops, the map forgets it. */
function release(w) {
    stopPolling(w);
    if (w.room) wins.delete(roomKey(w.room));
    w.room = null;
    w.entry = null;
}

/**
 * Open a room: bring its window forward if it has one, otherwise fill a
 * window and start asking.
 */
function open(room, data, fill, restoring, kept) {
    const { w, fresh } = windowFor(room);
    if (!fresh) {
        // Already open: unfolded, in front, and the cursor in its field.
        if (w.el.classList.contains('is-minimized')) setMinimized(w, false);
        raise(w);
        layout();
        if (!restoring) w.text.focus();
        return w;
    }
    w.room = room;
    w.highest = 0;
    wins.set(roomKey(room), w);
    fill(w);
    w.list.replaceChildren();
    w.text.value = kept?.draft ?? '';
    w.entry = { room: data, min: !!kept?.min, draft: kept?.draft ?? '', x: kept?.x ?? null, y: kept?.y ?? null };
    present(w);
    fetchNew(w, true).then(() => { if (!restoring) w.text.focus(); });
    startPolling(w);
    return w;
}

/**
 * Open the chat of a container.
 *
 * @param {object} container  the entry from the start data — id, name and
 *   members; the last one is both the list above the conversation and
 *   the company the author names are shortened against.
 * @param {number} me  my user id
 */
export function openChat(container, me, restoring = false, kept = null) {
    const data = { kind: 'container', me, container: {
        id: container.id, name: container.name,
        members: (container.members ?? []).map(m => ({ id: m.id, display_name: m.display_name })),
    } };
    return open({ container: container.id }, data, (w) => {
        w.people.hidden = false;
        setHeading(w, container.name, container.name);

        /* THE COMPANY IS THIS CONTAINER'S, not the whole sidebar's. Here the
           names of THESE members have to be told apart — a second Finn in some
           other container would lengthen the name for no reason, because he is
           not in the room. */
        const members = (container.members ?? []).map(m => m.display_name);
        const short   = shortNames(members);
        w.members.replaceChildren(...(container.members ?? []).map((m) => {
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
                inner.addEventListener('click', () => insertMention(w, label));
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
        w.me = me;
        w.company = members;
        applyWide(w);
    }, restoring, kept);
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
export function openFriendChat(friend, me, restoring = false, kept = null) {
    const data = { kind: 'friend', me, friend: { id: friend.id, name: friend.name } };
    return open({ friend: friend.id }, data, (w) => {
        w.people.hidden = true;
        w.people.open = false;
        setHeading(w, t('Chat mit %s', friend.name), friend.name);
        w.members.replaceChildren();
        w.me = me;
        w.company = [friend.name];
    }, restoring, kept);
}

/* ---- The floating chat on the desktop ---------------------------------------

   IT OUTLIVES THE PAGE (14.10.2026: „bleibt das Chatfenster
   immer geöffnet … während ich zwischen den verschiedenen Hauptbereichen
   wechsle"; and the reason: „Somit könnte ich auch Support leisten für
   einen meiner Freunde"). Karte, Fundbrowser, Bestimmung and Verwaltung
   are separate documents, so the windows cannot really stay; they are
   REMEMBERED instead — which rooms, folded or not, the unsent text and a
   place one dragged a window to — in sessionStorage, the tab's own memory,
   and reopened at once by whichever page loads next (the restore at the
   end of this file). Closing a window with „✕" forgets it.

   THE SIZE IS THE WINDOW'S, NOT THE CHAT'S (15.11.2026: „das
   Chatfenster merkt sich nicht Position und Größe. Also es braucht sich
   nicht pro Chat die Position und Größe merken, aber zumindest mal vom
   Fenster selbst"). It is a setting of the device (settings, 'chatWindow')
   and outlives the closing, the tab and the day; every new window takes
   it. So does the place a window was last dragged to — for the first
   window that opens while none is open, the one that was meant then.

   THE OTHERS LINE UP (27.11.2026). A window nobody dragged stands at the
   bottom edge, the first at the right and every further one to the left
   of the one before, each as wide as it is — a folded one is a small
   rectangle, so the row packs tight (layout()). A dragged window leaves
   the row and stays where it was put.

   NOT MODAL above the phone breakpoint: show(), not showModal(), so the
   page behind stays usable — the header's buttons included. The phone
   keeps the full-screen modal it had: there the areas are not reachable
   beside a chat anyway („Handy … ist ja immer Fullscreen"). */
const FLOAT_KEY = 'chat.floating';
const DOCK_EDGE = 24;   // px from the window's right and bottom edge — the 1.5rem of chat.css
const DOCK_GAP = 12;
let topZ = 1500;        // the z-index of chat.css; a window clicked goes above the others

/** The remembered windows: [{room, min, draft, x, y}, …]. A tab of before the change kept one. */
function floatRooms() {
    let kept = null;
    try { kept = JSON.parse(sessionStorage.getItem(FLOAT_KEY) ?? 'null'); } catch { /* private window */ }
    if (Array.isArray(kept?.rooms)) return kept.rooms;
    return kept?.room ? [kept] : [];
}
function saveRooms() {
    const rooms = [...wins.values()]
        .filter(w => w.entry && w.el.classList.contains('is-floating'))
        .map(w => w.entry);
    try {
        if (rooms.length > 0) sessionStorage.setItem(FLOAT_KEY, JSON.stringify({ rooms }));
        else sessionStorage.removeItem(FLOAT_KEY);
    } catch { /* private window */ }
}

/** Size and last place as the device keeps them. */
function geometry() {
    const kept = settings.get('chatWindow', null);
    return kept && typeof kept === 'object' ? kept : {};
}
function saveGeometry(patch) {
    settings.set('chatWindow', { ...geometry(), ...patch });
}

/** The size every window takes, kept inside the screen. */
function applySize(w) {
    const g = geometry();
    if (g.w && g.h) {
        w.el.style.setProperty('--chat-w', Math.min(g.w, innerWidth - 16) + 'px');
        w.el.style.setProperty('--chat-h', Math.min(g.h, innerHeight - 16) + 'px');
    }
}

/**
 * Place every floating window: a dragged one where it was put (kept inside
 * the screen), the rest in the row along the bottom edge, from the right.
 * Called whenever a window opens, closes, folds, unfolds or is resized —
 * each changes how wide the row is.
 */
function layout() {
    if (!wide.matches) return;
    let right = DOCK_EDGE;
    for (const w of wins.values()) {
        if (!w.el.classList.contains('is-floating') || !w.el.open) continue;
        const st = w.el.style;
        const e = w.entry;
        if (e?.x != null && e?.y != null) {
            const width = w.el.getBoundingClientRect().width;
            st.setProperty('--chat-x', Math.max(8, Math.min(e.x, innerWidth - width - 8)) + 'px');
            st.setProperty('--chat-y', Math.max(8, Math.min(e.y, innerHeight - 60)) + 'px');
            st.setProperty('--chat-right', 'auto');
            st.setProperty('--chat-bottom', 'auto');
            continue;
        }
        st.setProperty('--chat-x', 'auto');
        st.setProperty('--chat-y', 'auto');
        st.setProperty('--chat-right', right + 'px');
        st.setProperty('--chat-bottom', DOCK_EDGE + 'px');
        right += w.el.getBoundingClientRect().width + DOCK_GAP;
    }
}

function raise(w) {
    if (w.el.classList.contains('is-floating')) w.el.style.zIndex = String(++topZ);
}

function setMinimized(w, on) {
    w.el.classList.toggle('is-minimized', on);
    if (!on) w.el.classList.remove('has-news');
    if (w.entry) w.entry.min = on;
    const b = w.minimize;
    if (b) {
        b.setAttribute('aria-expanded', String(!on));
        b.title = on ? t('Aufklappen') : t('Zuklappen');
        b.setAttribute('aria-label', on ? t('Chat aufklappen') : t('Chat zuklappen'));
    }
}

/** Open the window in the shape the width calls for, and remember the room. */
function present(w) {
    if (wide.matches) {
        w.el.classList.add('is-floating');
        applySize(w);
        // The place the device remembers belongs to the first window only —
        // further ones join the row instead of covering it.
        const g = geometry();
        if (w.entry.x == null && wins.size === 1 && g.x != null && g.y != null) {
            w.entry.x = g.x;
            w.entry.y = g.y;
        }
        if (w.el.open && w.el.matches(':modal')) w.el.close();
        if (!w.el.open) w.el.show();
        setMinimized(w, w.entry.min);
        raise(w);
        layout();
        saveRooms();
    } else {
        w.el.classList.remove('is-floating', 'is-minimized', 'has-news');
        if (w.el.open && !w.el.matches(':modal')) w.el.close();
        if (!w.el.open) w.el.showModal();
    }
}

/** „✕": the window goes, and the tab forgets it. A copy leaves the page. */
function close(w) {
    release(w);
    if (w.el.open) w.el.close();
    if (w !== primary) w.el.remove();
    saveRooms();
    layout();
}

// The size the corner grip gave a window — not while folded, that is no size.
const sizeTimers = new WeakMap();
const elWin = new WeakMap();
const resizeWatch = new ResizeObserver((entries) => {
    for (const { target } of entries) {
        const w = elWin.get(target);
        if (!w || !w.el.open || !w.el.classList.contains('is-floating') || w.el.classList.contains('is-minimized')) continue;
        clearTimeout(sizeTimers.get(w));
        sizeTimers.set(w, setTimeout(() => {
            const b = w.el.getBoundingClientRect();
            if (b.width > 0 && b.height > 0) {
                w.el.style.setProperty('--chat-w', Math.round(b.width) + 'px');
                w.el.style.setProperty('--chat-h', Math.round(b.height) + 'px');
                saveGeometry({ w: Math.round(b.width), h: Math.round(b.height) });
                layout();
            }
        }, 250));
    }
});

/** Everything a window answers to, bound once per window. */
function bindWin(w) {
    elWin.set(w.el, w);

    // A press anywhere on a floating window brings it to the front.
    w.el.addEventListener('pointerdown', () => raise(w));

    /* DRAGGED BY ITS HEAD. A press that does not travel is a click — on a
       folded window it unfolds it, so the head is the one target there. */
    w.headBar.addEventListener('pointerdown', (e) => {
        if (!w.el.classList.contains('is-floating') || e.button !== 0 || e.target.closest('button')) return;
        const box = w.el.getBoundingClientRect();
        const dx = e.clientX - box.left, dy = e.clientY - box.top;
        let moved = false;
        w.headBar.setPointerCapture(e.pointerId);
        const move = (ev) => {
            if (!moved && Math.hypot(ev.clientX - e.clientX, ev.clientY - e.clientY) < 4) return;
            moved = true;
            const st = w.el.style;
            st.setProperty('--chat-x', Math.max(0, Math.min(ev.clientX - dx, innerWidth - box.width)) + 'px');
            st.setProperty('--chat-y', Math.max(0, Math.min(ev.clientY - dy, innerHeight - 40)) + 'px');
            st.setProperty('--chat-right', 'auto');
            st.setProperty('--chat-bottom', 'auto');
        };
        const up = () => {
            w.headBar.removeEventListener('pointermove', move);
            w.headBar.removeEventListener('pointerup', up);
            w.headBar.removeEventListener('pointercancel', up);
            if (moved) {
                const b = w.el.getBoundingClientRect();
                const x = Math.round(b.left), y = Math.round(b.top);
                if (w.entry) { w.entry.x = x; w.entry.y = y; }
                saveGeometry({ x, y });
                saveRooms();
                layout();   // it left the row — the others close up
            } else if (w.el.classList.contains('is-minimized')) {
                setMinimized(w, false);
                saveRooms();
                layout();
            }
        };
        w.headBar.addEventListener('pointermove', move);
        w.headBar.addEventListener('pointerup', up);
        w.headBar.addEventListener('pointercancel', up);
    });

    w.minimize?.addEventListener('click', () => {
        setMinimized(w, !w.el.classList.contains('is-minimized'));
        saveRooms();
        layout();
    });

    w.el.querySelector('.chat-close').addEventListener('click', () => close(w));
    // Escape on the phone's modal window closes it without the button.
    w.el.addEventListener('close', () => {
        if (!w.room) return;
        release(w);
        saveRooms();
    });

    // The unsent text travels to the next page.
    w.text.addEventListener('input', () => {
        if (w.entry && w.el.classList.contains('is-floating')) {
            w.entry.draft = w.text.value;
            saveRooms();
        }
    });

    w.form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = w.text.value.trim();
        if (text === '' || w.room === null) return;

        // EMPTIED BEFORE SENDING, not after. Out in the woods the answer takes
        // seconds, and a field that still holds what one has written invites
        // sending it a second time. If it fails, the text comes back.
        w.text.value = '';
        const room = w.room;
        try {
            if (w.entry) { w.entry.draft = ''; saveRooms(); }
            const answer = await api.post('chat', { ...room, text });
            if (room.friend && !friendStarted.has(room.friend)) {
                friendStarted.add(room.friend);
                tellBadges();
            }
            if (sameRoom(w.room, room) && answer?.message) {
                w.list.append(row(answer.message, w.me, w.company, w));
                w.highest = Math.max(w.highest, answer.message.id);
                w.list.scrollTop = w.list.scrollHeight;
            }
        } catch (err) {
            w.text.value = text;
            // The reason belongs next to the field, not in the console — one is
            // standing in front of a message that did not go out.
            w.head.title = err?.message ?? '';
            w.text.setAttribute('aria-invalid', 'true');
            setTimeout(() => w.text.removeAttribute('aria-invalid'), 3000);
        }
        w.text.focus();
    });

    /* ENTER SENDS, SHIFT+ENTER MAKES A LINE. On the phone the keyboard's
       return key inserts a line — there one sends with the button, and that is
       why the button is there at all rather than only a hint. */
    w.text.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !matchMedia('(max-width: 860px)').matches) {
            e.preventDefault();
            w.form.requestSubmit();
        }
    });
}

/* THE RESTORE: the page before had floating chats open — open them again,
   folded or not, each with what was being typed and where it was dragged,
   and without taking the focus from the page that just loaded. Only on the
   desktop; on the phone the remembered windows wait until the screen is
   wide again. */
if (wide.matches) {
    for (const kept of floatRooms()) {
        const r = kept.room;
        if (r?.kind === 'friend' && r.friend) openFriendChat(r.friend, r.me, true, kept);
        else if (r?.kind === 'container' && r.container) openChat(r.container, r.me, true, kept);
    }
}
