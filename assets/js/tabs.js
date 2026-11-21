/**
 * Tabs for the simple pages.
 *
 * Deliberately without reloading: The areas of the administration are all
 * already there, one of them visible. A page change for a change of
 * viewpoint would be waiting time without return.
 *
 * THE SELECTION LIVES IN THE ADDRESS. Whoever applies the migrations and
 * comes back afterwards otherwise lands in the first tab again and
 * searches from scratch. As an anchor (#server), not as a query: It is
 * not sent to the server, and a bookmark on it works.
 *
 * AND IT SURVIVES THE VISIT: The last chosen tab additionally lies in the
 * device's settings bundle. Whoever opens the administration without an
 * anchor — via the map's header link — stands where they last were again,
 * instead of starting at "Offline-Gebiete" every time. An anchor in the
 * address still wins.
 *
 * Keyboard as in the recommendations for tabs: arrows switch, Home and
 * End jump to the edge. Only the active tab is reachable with the Tab
 * key — otherwise you have to clamber through all of them to get to the
 * content.
 */

/*
 * The desired tab, once for the whole document.
 *
 * It can lie deep: '#invitations' is a tab INSIDE the server
 * administration. The outer bar does not find it among its own — it must
 * still choose 'server', otherwise the chosen content sits in a hidden
 * area, and you see "Mein Bereich" instead of what stood in the address.
 * Each bar therefore takes the tab that IS the target or contains it.
 */
import { settings } from './settings.js';

// The anchor from the address wins; without it the remembered tab counts.
// If the remembered one finds no button (different account without admin
// tabs, renamed area), it stays with the first — remembered is a
// suggestion, not an address.
/* THE REMEMBERED ANCHOR CAN BE A GERMAN ONE. The anchors moved to English
   with everything else, but the devices had the old name in their settings
   bundle — and the bundle takeover renamed the KEY, not its value. Whoever
   last stood in "Organisieren" would land in the first tab instead. Translating
   at READ time and not in the takeover is deliberate: the takeover runs
   once, and a device that already migrated would never be reached again.
   Goes away with legacy.js once every device has been here. */
const OLD_ANCHORS = {
    abzug: 'dump', arten: 'species', 'container-eigene': 'container-own',
    'container-fremde': 'container-others', daten: 'data',
    einladungen: 'invitations', einstellungen: 'settings', freunde: 'friends',
    gebiete: 'areas', gemeinden: 'municipalities', gemeinsam: 'shared',
    gruppen: 'groups', hoehen: 'elevation', account: 'account', mein: 'mine',
    migrationen: 'migrations', members: 'members', nachtragen: 'backfill',
    oberflaeche: 'appearance', ordnen: 'organize', papierkorb: 'trash',
    schutzgebiete: 'protected-areas', taxonomie: 'taxonomy', teilen: 'share',
    zahlen: 'numbers',
};
const remembered = settings.get('administrationTab', null);
const anchor = location.hash.length > 1
    ? location.hash.slice(1)
    : (OLD_ANCHORS[remembered] ?? remembered);
const target = anchor
    ? document.querySelector(`[role="tab"][data-anchor="${CSS.escape(anchor)}"]`)
    : null;
if (target) settings.set('administrationTab', anchor);

/* ONCE AT LOAD, AND AGAIN FOR EVERY BAR THAT ARRIVES LATER (25.08.2026).
 *
 * The administration fetches its tabs on demand, and one of those tabs
 * brings a tab bar of its OWN along: Einstellungen has Konto, Oberfläche
 * and the rest inside it. Wired only at load, that bar was never wired at
 * all — its buttons did nothing, and the maintainer found exactly that.
 *
 * A bar is wired at most once, with the bar element as the key: a
 * fragment fetched anew brings a NEW bar, and that one is wired again,
 * rightly. */
const wiredBars = new WeakSet();

export function mountTabs(root = document) {
for (const bar of root.querySelectorAll('[role="tablist"]')) {
    if (wiredBars.has(bar)) continue;
    wiredBars.add(bar);
    const tabs = [...bar.querySelectorAll('[role="tab"]')];
    if (tabs.length < 2) continue;

    // 'focus' distinguishes the click from establishing the initial state,
    // and more hangs on it than the keyboard focus: On load, NO bar may
    // write the anchor. Two nested bars did that one after the other — the
    // outer found '#trash' not among its tabs, took its first and
    // wrote '#mine'; the inner saw that and likewise took its first. The
    // anchor was gone before anyone could evaluate it.
    /* EVERY BAR REMEMBERS ITSELF, under its own label.
       Before, all of them wrote into ONE setting — and on load the anchor
       from the address overwrote it as well. The forms jump back to
       #share, so after every action that is what stood there, and the
       inner bar fell back to its first tab, "Eigene". Whoever last stood
       at "Von anderen" landed somewhere else every time
       (20.08.2026).

       The label is the key because it describes the bar and not its
       position: eight bars, eight distinct names, checked. A bar that is
       moved or renamed loses at most its memory — it never inherits
       somebody else's.

       The old setting is still written alongside: it answers the other
       question, namely where one last WAS, when the administration is
       opened with no anchor at all. */
    const memoryKey = 'tab:' + (bar.getAttribute('aria-label') || '');

    const choose = (button, focus = true) => {
        for (const r of tabs) {
            const active = r === button;
            r.setAttribute('aria-selected', active ? 'true' : 'false');
            r.tabIndex = active ? 0 : -1;
            const field = document.getElementById(r.getAttribute('aria-controls'));
            if (field) field.hidden = !active;
        }
        chooserSync();
        if (!focus) return;
        button.focus();
        history.replaceState(null, '', '#' + button.dataset.anchor);
        settings.set('administrationTab', button.dataset.anchor);
        settings.set(memoryKey, button.dataset.anchor);
    };

    for (const r of tabs) {
        r.addEventListener('click', () => choose(r));
        r.addEventListener('keydown', (e) => {
            const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
            let target = null;
            if (step) target = tabs[(tabs.indexOf(r) + step + tabs.length) % tabs.length];
            if (e.key === 'Home') target = tabs[0];
            if (e.key === 'End')  target = tabs.at(-1);
            if (!target) return;
            e.preventDefault();
            choose(target);
        });
    }

    // What stands in the address wins — then this bar's own memory,
    // otherwise the first tab.
    /* THE ADDRESS IS READ AGAIN FOR EVERY BAR, not only once at load: a
       bar that arrives in a fragment (Einstellungen → Fotos → …) is the
       first moment its anchor exists at all. And a tab whose content is
       still a waiting shell counts as holding an anchor its shell names
       (data-anchors, administration.php tab_anchors) — opening it fetches
       the fragment, whose bars then come through here and find it. Without
       both, #export or #microscope-camera opened whatever was open last. */
    const wantedAnchor = location.hash.length > 1 ? location.hash.slice(1) : anchor;
    const wantedTab = wantedAnchor
        ? document.querySelector(`[role="tab"][data-anchor="${CSS.escape(wantedAnchor)}"]`)
        : null;
    const sectionOf = (r) => document.getElementById(r.getAttribute('aria-controls'));
    const wanted = tabs.find(r => r === wantedTab || (wantedTab && sectionOf(r)?.contains(wantedTab)))
        ?? (wantedAnchor ? tabs.find(r => sectionOf(r)?.querySelector(
            `[data-tab-src][data-anchors~="${CSS.escape(wantedAnchor)}"]`)) : undefined);

    const rememberedHere = settings.get(memoryKey, null);
    const fromMemory = rememberedHere
        ? tabs.find(r => r.dataset.anchor === (OLD_ANCHORS[rememberedHere] ?? rememberedHere))
        : null;

    choose(wanted ?? fromMemory ?? tabs[0], false);
}
chooserSync();
}

/* --- The tabs as choosers ----------------------------------------------
 *
 * 09.09.2026: „können wir in der Verwaltung die Tab-Ebenen eher
 * als Drop-downs machen, natürlich mit Icon und so, so wie wir das beim
 * Bildbetrachter bei den Messwerkzeugen haben, dass man einen auswählt und
 * es klappt zusammen und der aktuelle wird angezeigt. Und falls dieser Tab
 * noch weitere Untertabs hat, geht rechts daneben ein neues Drop-down auf
 * mit den jeweiligen Untertabs."
 *
 * WHY AS A LAYER AND NOT AS A REBUILD. The tabs stay exactly the buttons
 * they were — with their anchors, their keyboard handling, their memory
 * and the on-demand loading of their content. The only thing that changes
 * is WHERE they stand: no longer as a row above the content, but in a
 * menu under a button that shows the current one. No second truth about
 * "which tab is open" comes into being; the bar itself stays the truth,
 * and the button merely copies it.
 *
 * THREE LEVELS SIDE BY SIDE. In the document the bars lie INSIDE one
 * another — the second sits in the section of the first, the third in the
 * section of the second. So what stands side by side is not the bars but
 * their BUTTONS: a line at the very top collects them, in the order in
 * which they stand in the document. Whoever chooses another level thereby
 * changes which bars are visible at all — and the line is filled anew
 * each time.
 *
 * THE MENU STAYS WHERE IT IS. It goes on lying in the section it belongs
 * to, and is only placed under its button when it opens (position:
 * fixed). That way it vanishes by itself when its section closes — no
 * tidying up, no second list that could be forgotten.
 */

const triggers = new WeakMap();

/* ONLY ON THE PHONE (09.09.2026, after he had seen it: „lass
   uns diese Klappmenüs nur im Mobilemodus machen. Im Desktopmodus lassen
   wir's so, wie's vorher war"). At the desk there is room for the rows,
   and a row shows all targets at once — a menu shows one and demands a
   tap for the rest. It only gets tight on the phone, and there it used
   to be up to seven lines above the content.

   The same number as everywhere: the 860 from the stylesheet. */
const phone = window.matchMedia('(max-width: 860px)');

/** Does this bar stand on an open page? */
function live(bar) {
    for (let el = bar.parentElement; el && el !== document.body; el = el.parentElement) {
        if (el.hidden) return false;
    }
    return true;
}

function chooserRow() {
    let row = document.getElementById('tab-choosers');
    if (row) return row;
    row = document.createElement('div');
    row.id = 'tab-choosers';
    row.className = 'tab-choosers';
    (document.querySelector('.content') ?? document.body).prepend(row);
    return row;
}

/** All menus shut — except the one that is meant right now. */
function closeMenus(except = null) {
    for (const bar of document.querySelectorAll('[role="tablist"].is-menu')) {
        if (bar === except) continue;
        bar.classList.remove('is-open');
        triggers.get(bar)?.setAttribute('aria-expanded', 'false');
    }
}

function place(bar) {
    const button = triggers.get(bar);
    if (!button) return;
    const r = button.getBoundingClientRect();
    bar.style.left = Math.round(r.left) + 'px';
    bar.style.top  = Math.round(r.bottom + 4) + 'px';
    /* Not narrower than the button, and never out past the right edge —
       the names are longer than „Cloud", and a menu that runs out of the
       picture hides exactly the entries one does not know. */
    bar.style.minWidth = Math.round(r.width) + 'px';
    bar.style.maxWidth = Math.round(window.innerWidth - r.left - 8) + 'px';
}

function triggerFor(bar) {
    let button = triggers.get(bar);
    if (button) return button;
    button = document.createElement('button');
    button.type = 'button';
    button.className = 'tab-chooser';
    button.setAttribute('aria-haspopup', 'true');
    button.setAttribute('aria-expanded', 'false');
    button.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = bar.classList.contains('is-open');
        closeMenus();
        if (open) return;
        bar.classList.add('is-open');
        button.setAttribute('aria-expanded', 'true');
        place(bar);
    });
    triggers.set(bar, button);
    bar.classList.add('is-menu');
    return button;
}

/** The button shows what is chosen right now — the tab's sign and word. */
function label(bar) {
    const button = triggers.get(bar);
    const active = bar.querySelector('[role="tab"][aria-selected="true"]')
        ?? bar.querySelector('[role="tab"]');
    if (!button || !active) return;
    button.replaceChildren(...[...active.cloneNode(true).childNodes]);
    const caret = document.createElement('span');
    caret.className = 'tab-chooser-caret';
    caret.setAttribute('aria-hidden', 'true');
    caret.textContent = '▾';
    button.append(caret);
    button.title = active.textContent.trim();
    button.setAttribute('aria-label', active.textContent.trim());
}

export function chooserSync() {
    const bars = [...document.querySelectorAll('[role="tablist"]')]
        /* SINGLE BARS STAY ROWS, on the phone too: the mark stands in
           the markup (data-tabs="row") and not here, because whether two
           entries deserve a menu is known by the place where they are
           built — see the shares in share_content.php. */
        .filter(b => b.dataset.tabs !== 'row')
        .filter(b => b.querySelectorAll('[role="tab"]').length >= 2);
    if (bars.length === 0) return;
    if (!phone.matches) {
        // At the desk everything is a row again — and the line empty,
        // instead of standing there as a leftover.
        for (const bar of bars) bar.classList.remove('is-menu', 'is-open');
        document.getElementById('tab-choosers')?.replaceChildren();
        return;
    }
    const row = chooserRow();
    const wanted = [];
    for (const bar of bars) {
        const button = triggerFor(bar);
        if (!live(bar)) { bar.classList.remove('is-open'); continue; }
        label(bar);
        wanted.push(button);
    }
    // Touch only what has changed — otherwise a button under the pointer
    // loses its state at every switch.
    if (wanted.length !== row.children.length
        || wanted.some((b, i) => row.children[i] !== b)) {
        row.replaceChildren(...wanted);
    }
}

// A tap beside it puts the menu away — as everywhere in this application.
// Whoever drags the window size across the 860 gets the rows back, and
// the other way round — without reloading.
phone.addEventListener('change', () => chooserSync());
document.addEventListener('click', () => closeMenus());
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenus(); });
// On scrolling and on resizing the button moves; the open menu hangs on
// it and not on the document.
for (const type of ['scroll', 'resize']) {
    window.addEventListener(type, () => {
        const open = document.querySelector('[role="tablist"].is-menu.is-open');
        if (open) place(open);
    }, true);
}

mountTabs();
