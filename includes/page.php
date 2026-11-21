<?php
/**
 * Page skeleton. Two guises:
 *
 *   page_header($title)                for ordinary pages with content width
 *   page_header($title, ['wide'=>true]) for tables that need more room
 *   page_header($title, ['full'=>true])  for the map, which fills the whole screen
 */

declare(strict_types=1);

/**
 * THE APPLICATION'S WAYS, AS BUTTONS (09.10.2026: „in der
 * Desktop-Version bekommt die ganze Seite eine Kopfzeile … mit Reitern" —
 * and then, seeing tabs: „mach mir in der Kopfleiste keine Tabs, sondern
 * ein Knopf-Design"): Karte, the finds' browser still to come, Verwaltung,
 * three small subtle buttons, the one one stands on framed in the accent.
 * The same row stands in the map's bar
 * and in the administration's header, so one can walk from one to the
 * other the way one walks between tabs. Desktop only — the phone keeps its
 * own ways (app.css hides the row below 861 px).
 *
 * A TAB THAT LEADS NOWHERE is shown as what it is — quiet, no link,
 * „kommt noch" in its title (a null address below). The Fundbrowser stood
 * so until 10.10.2026, when its page came (pages/finds.php). Whether
 * Verwaltung stays a tab or goes back to being a button is the maintainer's to
 * decide; it is one entry here.
 *
 * MODULES ADD THEIR OWN BETWEEN THE FUNDBROWSER AND THE ADMINISTRATION
 * (since 09.10.2026 one could; since 20.11.2026 through includes/modules.php,
 * hook 'tabs'). A module decides for itself whom its button is for — for
 * everyone else it is simply not there, and the row is the core's three.
 *
 * @param string $active 'map' | 'finds' | 'admin' | a module's key
 */
function app_tabs(string $active): void
{
    $tabs = [
        'map'    => [t('Karte'), url(), 'map'],
        'finds'  => [t('Fundbrowser'), url('pages/finds.php'), 'photo'],
    ];
    foreach (module_each('tabs', user_current()) as $added) {
        $tabs += (array) $added;
    }
    $tabs['admin'] = [t('Verwaltung'), url('pages/administration.php'), 'gear'];
    echo '<nav class="app-tabs" aria-label="' . h(t('Bereiche')) . '">';
    foreach ($tabs as $key => [$label, $href, $symbol]) {
        $inner = icon($symbol) . '<span>' . h($label) . '</span>';
        if ($href === null) {
            echo '<span class="button subtle small app-tab is-disabled" aria-disabled="true" title="' . h(t('Kommt noch')) . '">' . $inner . '</span>';
        } else {
            echo '<a class="button subtle small app-tab' . ($key === $active ? ' is-active' : '') . '" href="' . h($href) . '"'
                . ($key === $active ? ' aria-current="page"' : '') . '>' . $inner . '</a>';
        }
    }
    echo '</nav>';
}

/**
 * THE CHAT BUBBLE IN THE HEADER of the pages with the app's buttons —
 * Fundbrowser, Bestimmung, Verwaltung (10.10.2026, after it
 * moved into the map's bar: „ja, bau ihn dort auch ein"). The same list
 * as on the map, „Chat mit …" per friend, the green dot where somebody
 * wrote. ON THE DESKTOP THE WINDOW OPENS RIGHT HERE (14.10.2026): the page
 * carries the chat window too (chat_window(), printed by page_footer once
 * this bubble stood), and headerchat.js opens it floating. On the phone
 * each row stays a link to the map, which opens the conversation from its
 * address (?window=friend-chat, windowFromUrl in map.js). The dot is the
 * state at the page's loading — these pages do not poll; the map does.
 *
 * Nothing without friends, like the map's bubble.
 */
function header_chat(PDO $pdo, int $me): void
{
    require_once __DIR__ . '/friends.php';
    require_once __DIR__ . '/chat.php';
    if (!friends_ready($pdo)) {
        return;
    }
    $friends = friends_overview($pdo, $me)['friends'];
    if ($friends === []) {
        return;
    }
    $unread = friend_chat_unread($pdo, $me);
    $any = false;
    $rows = '';
    foreach ($friends as $f) {
        $id = (int) $f['user_id'];
        $waiting = ($unread[$id]['unread'] ?? 0) > 0;
        $any = $any || $waiting;
        $name = (string) $f['display_name'];
        $rows .= '<a class="chat-flyout-row' . ($waiting ? ' has-unread' : '') . '"'
            . ' href="' . h(url() . '?window=friend-chat&friend=' . $id) . '"'
            . ' data-friend="' . $id . '" data-name="' . h($name) . '"'
            . ' title="' . h($waiting ? t('Chat mit %s — Neues da', $name) : t('Chat mit %s', $name)) . '">'
            . icon('speech-bubble') . h(t('Chat mit %s', $name)) . '</a>';
    }
    // The window comes along with the bubble (page_footer prints it).
    $GLOBALS['chat_window_wanted'] = true;
    echo '<div class="header-chat">'
        . '<button type="button" class="symbol-button chat-entry' . ($any ? ' has-unread' : '') . '" id="header-chat"'
        . ' data-me="' . $me . '"'
        . ' aria-expanded="false" aria-controls="header-chat-list"'
        . ' title="' . h(t('Chats mit Freunden')) . '" aria-label="' . h(t('Chats mit Freunden')) . '">'
        . icon('speech-bubble') . '</button>'
        . '<div class="chat-flyout" id="header-chat-list" hidden>' . $rows . '</div>'
        . '</div>';
}

function page_header(string $title, array $options = []): void
{
    $full     = !empty($options['full']);
    /* EMBEDDED in another page's window — the key inside the microscope
       window (05.11.2026: „ich muß ständig dazwischen wechseln
       beim schlüsseln"). No header bar: its buttons would lead the frame
       away from the page it sits in, and the window around it has its
       own head. The body says so, for whatever else must step back. */
    $embed    = !empty($options['embed']);
    $user = user_current();

    // Already standing on the administration? Recognized by the script name
    // and not by a parameter at the call site: that way it holds everywhere,
    // including for every page added later that would not think of it.
    $alreadyHere = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'administration.php';
    // Which of the app's buttons this page is — the administration by its
    // name, any other page that belongs in the row by saying so ('tab').
    $tab = $options['tab'] ?? ($alreadyHere ? 'admin' : null);
    ?>
<!DOCTYPE html>
<html lang="<?= h(lang_current()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <title><?= h($title) ?> · YASAIMAPP<?= IS_LOCAL ? ' (lokal)' : '' ?></title>
    <?php /* THE SAME MANIFEST ON EVERY PAGE (12.09.2026). It stood on the map
         alone; the guest pages of an excursion and the administration had
         none. A guest who added the excursion page to the home screen got
         a shortcut named after the page's title, and the first opening of
         the map afterwards showed Chrome's „Umbenennung akzeptieren?" —
         the manifest's name and the shortcut's disagreed. The maintainer saw
         that on the phones of new guests, on the first real excursion.
         One manifest everywhere, and there is nothing to rename. */ ?>
    <link rel="manifest" href="<?= h(url('pages/manifest.webmanifest.php')) ?>">
    <meta name="theme-color" content="#14181c">
    <?php /* THE TAB'S ICON on every page, as the map has it (index.php): without
         it the browser asks the site's root for /favicon.ico — outside /map/,
         a 404 in every console (the maintainer saw it in the Fundbrowser,
         15.11.2026). */ ?>
    <link rel="icon" href="<?= h(url('assets/symbole/symbol-32.png')) ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= h(url('assets/symbole/symbol-180.png')) ?>">
    <script>
    /* BEFORE the stylesheet and without a module: theme and accent must
       hang on the <html> before the browser paints for the first time —
       otherwise the default flashed up first on every switch. What is read
       is the settings bundle from settings.js; its key name lives
       there and here, that is the price of the preamble.

       CARRY OLD VALUES OVER (the call, 15.08.2026, eight themes
       instead of two): 'dunkel' corresponds to Kohle, 'hell' corresponds to
       Papier — both the previous defaults of their family. The same move
       lives in settings_content.php, for reading happens there too. */
    try {
        /* ONE-TIME TAKEOVER, INLINE AND FIRST: the bundle and its keys move
           to English names. It cannot wait for legacy.js — this very block
           reads the theme from the bundle before the first paint, and the
           settings page writes it before any module loads. The same block
           sits in index.php; two copies are the price of the preamble. */
        if (localStorage.getItem('map.settings') === null) {
            const old = JSON.parse(localStorage.getItem('karte.einstellungen')
                ?? localStorage.getItem('pilzkarte.einstellungen') ?? '{}') || {};
            for (const [a, z] of [['pilzkarte.ansicht', 'ansicht'], ['pilzkarte.grundkarte', 'grundkarte']]) {
                const r = localStorage.getItem(a);
                if (r !== null && !(z in old)) { try { old[z] = JSON.parse(r); } catch { old[z] = r; } }
            }
            const names = { ansicht: 'view', auflagen: 'overlays', eimerAus: 'bucketsOff',
                filterOffen: 'filterOpen', grundkarte: 'baseMap', 'kachel-cors': 'tile-cors',
                kopfsucheOffen: 'headerSearchOpen', legendenAus: 'legendsOff',
                legendenZu: 'legendsCollapsed', leisteBereicheZu: 'sidebarSectionsCollapsed',
                leisteSeite: 'sidebarPage', leisteZu: 'sidebarClosed',
                sichtbarEinzeln: 'visibleIndividually', sichtbareGeteilte: 'visibleShared',
                sichtbareListen: 'visibleLists', sort_order: 'sorting', standort: 'location',
                suchbegriffe: 'searchTerms', search_text: 'searchText', thema: 'theme',
                akzent: 'accent', verwaltungReiter: 'administrationTab',
                zugeklappteGeteilte: 'collapsedShared', zugeklappteListen: 'collapsedLists' };
            const fresh = {};
            for (const [n, w] of Object.entries(old)) fresh[names[n] ?? n] = w;
            if (Object.keys(fresh).length) localStorage.setItem('map.settings', JSON.stringify(fresh));
            for (const n of ['karte.einstellungen', 'pilzkarte.einstellungen',
                             'pilzkarte.ansicht', 'pilzkarte.grundkarte']) localStorage.removeItem(n);
        }
        const stored = JSON.parse(localStorage.getItem('map.settings') || '{}');
        /* CARRY THE RETIRED NAMES ALONG. 'dunkel'/'hell' are the two
           themes from before 15.08.2026; 'papier' and 'mitternacht' went
           on 22.08.2026, and the four accents were renamed with them
           because their colour changed (Gold → Orange and so on). The
           German value names moved to English on 22.08.2026 with the rest
           of the identifiers and joined this map. A device still carrying
           an old name would otherwise land in the default without a word —
           and on a light theme that means a dark page from one moment to
           the next. */
        const theme = { dunkel: 'charcoal', hell: 'warmlight',
                        papier: 'warmlight', mitternacht: 'charcoal',
                        kohle: 'charcoal', warmlicht: 'warmlight',
                        bernstein: 'amber', schiefer: 'slate' }[stored.theme] ?? stored.theme;
        const accent = { gold: 'orange', pink: 'purple',
                         moosgruen: 'steelblue', jade: 'red',
                         stahlblau: 'steelblue', rot: 'red',
                         minze: 'mint', lila: 'purple' }[stored.accent] ?? stored.accent;
        if (theme && theme !== <?= json_encode(THEME_DEFAULT) ?>) document.documentElement.dataset.theme = theme;
        if (accent && accent !== <?= json_encode(ACCENT_DEFAULT) ?>) document.documentElement.dataset.accent = accent;
    } catch (e) { /* private window or the like — then the default appearance it is */ }
    </script>
    <?php /* Each file carries its own version (asset_url, helpers.php). */ ?>
    <link rel="stylesheet" href="<?= h(asset_url('assets/css/app.css')) ?>">
    <?php if ($tab !== null && $user !== null): ?>
    <?php /* The chat window floats over these pages too (chat_window()). */ ?>
    <link rel="stylesheet" href="<?= h(asset_url('assets/css/chat.css')) ?>">
    <?php endif; ?>
    <?php /* The same version tag for every module the page's scripts
       import — in the head, before the first module script anywhere on
       the page, the footer's and the inline ones in the tabs alike. */ ?>
    <?= asset_import_map() ?>
    <?php if (!empty($options['head_extra'])): ?>
        <?= $options['head_extra'] ?>
    <?php endif; ?>
</head>
<body class="<?= $full ? 'page-full' : 'page-content' ?><?= $embed ? ' is-embedded' : '' ?>">
<?php if (!$full): ?>
<?php if (!$embed): ?>
<header class="header-bar<?= $tab !== null && $user !== null ? ' has-tabs' : '' ?>">
    <a class="brand" href="<?= h(url()) ?>">
        <?= icon('map', ['class' => 'brand-icon']) ?>
        <span class="brand-text">YASAIMAPP</span>
    </a>
    <?php if ($tab !== null && $user !== null) app_tabs($tab); ?>
    <?php /* THE PAGE TITLE STANDS HERE AND NO LONGER BELOW IT
         (09.09.2026: „in der Verwaltung haben wir ja eine
         Extraüberschrift Verwaltung. Können wir das nicht in die
         Kopfzeile oben schreiben?").

         It stood twice: once in the <title> and so in the browser's
         tab, once as <h1> in the first line of the content. Two
         places, the same word — and the second cost a line above
         everything one actually wanted to see.

         WHERE A PAGE REPEATED THE SAME WORD AS <h1>, it is gone there
         — Verwaltung, Hilfe, Standort-Probe. Where its heading says
         something DIFFERENT („YASAIMAPP einrichten" instead of
         „Einrichtung", the excursion's name instead of „Exkursion"), it
         stays: it says more than the title, and that is no
         repetition. Those pages then carry two headings, and that is
         the lesser untidiness — the other would have been to give
         every page a new title of its own, one by one.

         It is the first to give way when space gets tight — the
         application's name and the way out matter more than repeating
         what stands in the tab. */ ?>
    <h1 class="header-title"><?= h($title) ?></h1>
    <?php if ($user !== null): ?>
    <nav class="header-nav">
        <?php if ($tab !== null) { global $pdo; header_chat($pdo, (int) $user['id']); } ?>
        <?php /* NO sign-out on the pages without the app's buttons — removed
             at the request, 14.08.2026; this header stays out of
             the coming-and-going between the tools. WHERE THE APP'S
             BUTTONS STAND it is back since 10.10.2026, as in the map's
             bar, and replaces „Schließen" there (below). */ ?>
        <?php if ($tab === null): ?>
        <?php /* Not where the app's buttons stand: Verwaltung is one of them.
             For everyone: the administration has an area for one's own
             account and one for the server. Only the second is reserved for
             administrators, and the page decides that itself.

             Not on the administration itself: a link leading to where you
             already stand looks like an option and is none. It also costs
             the space the map link occupies — and on narrow screens that is
             the difference between one line and two. */ ?>
        <a href="<?= h(url('pages/administration.php')) ?>"><?= h(t('Verwaltung')) ?></a>
        <?php endif; ?>
        <?php if ($tab !== null): ?>
        <?php /* SYMBOLS WHERE THE APP'S BUTTONS STAND (10.10.2026:
             „im Fundbrowser, Bestimmung und Verwaltung habe ich jetzt Hilfe
             und Schließen als Text, während ich es bei Karte als Symbol
             habe. Ich hätte gerne überall die Symbole"). The same small
             square buttons as the chat bubble beside them, each with its
             word in title and aria-label; the other pages keep their words. */ ?>
        <?php /* FULL SCREEN, as in the map's bar (fullscreen.js). */ ?>
        <button type="button" class="symbol-button fullscreen-button" data-fullscreen hidden
                title="<?= h(t('Vollbild')) ?>" aria-label="<?= h(t('Vollbild')) ?>" aria-pressed="false"><?= icon('fullscreen', ['class' => 'when-off']) ?><?= icon('fullscreen-exit', ['class' => 'when-on']) ?></button>
        <a class="symbol-button" href="<?= h(url('pages/help.php')) ?>"
           title="<?= h(t('Hilfe')) ?>" aria-label="<?= h(t('Hilfe')) ?>"><?= icon('help') ?></a>
        <?php /* AND THE DOOR, as in the map's bar — not „Schließen" (his
             correction the same minute: „schließen gibt es ja eigentlich
             nicht mehr. Das wäre Exit-Abmelden"): the way back to the
             map is the Karte button now. A POST with its token, like the
             map's (a plain link would let any page sign one out). */ ?>
        <form method="post" action="<?= h(url('logout.php')) ?>" class="logout-form">
            <?= csrf_field() ?>
            <button type="submit" class="symbol-button"
                    title="<?= h(t('Abmelden')) ?>" aria-label="<?= h(t('Abmelden')) ?>"><?= icon('sign-out') ?></button>
        </form>
        <?php else: ?>
        <?php if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'help.php'): ?>
        <?php /* On every page except the help itself — a link to where one
             already stands looks like an option and is none (the same
             reasoning as for the administration link above). */ ?>
        <a href="<?= h(url('pages/help.php')) ?>"><?= h(t('Hilfe')) ?></a>
        <?php endif; ?>
        <?php /* "Schließen", not "Zur Karte": from here you come out of a tool,
             not from a second page — the same word as on the window button
             everywhere else in the application. */ ?>
        <a href="<?= h(url()) ?>"><?= h(t('Schließen')) ?></a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</header>
<?php endif; ?>
<main class="content<?= !empty($options['wide']) ? ' wide' : '' ?>">
<?php endif; ?>
<?php
}

function page_footer(array $options = []): void
{
    if (empty($options['full'])) {
        echo "</main>\n";
    }

    // Our own dialogs belong on every page: the trash and the invitation
    // administration also ask before deleting, and nowhere should the
    // browser's own window open for that anymore.
    //
    // The growing text fields likewise, and for the same reason: they are
    // not a property of a particular page but the way a multi-line field
    // behaves in this application. autoheight.js attaches itself; there is
    // nothing to call here.
    //
    // eszett.js too: a „ß" in a heading the stylesheet sets in capitals
    // becomes the capital „ẞ" instead of „SS", wherever such a heading
    // stands. Last in the row, so tables.js has read its headings first.
    //
    // tables.js belongs in the same row and for the same reason: below
    // 860 px every table.list becomes a stack of blocks, and each value
    // needs the column heading it loses there. It attaches itself to every
    // table on the page and watches for the ones a script builds later.
    //
    // toast.js likewise: a confirmation is a floating message on every
    // page outside the map, and it takes the server-rendered ones out of
    // the flow by itself (see the head of that file). Nothing to call
    // here either.
    //
    // folds.js is the fourth of that kind: it gives every
    // <details class="section-fold"> with an id a memory, so a section
    // somebody folded away stays folded away on the next visit.
    //
    // clearable.js is the fifth: an X in every filled text field
    // (22.08.2026). It needs the icon stock below to draw it —
    // which is why the stock now comes along on EVERY page, not only on
    // the two that used to print it themselves: two and a half kilobytes,
    // and the alternative is a module that works on some pages.
    //
    // quietsave.js is the sixth: a save button marked
    // data-quiet-until-changed stays quiet until its form really changed
    // (25.09.2026: „leuchten wie ein Weihnachtsbaum").
    //
    // 'scripts' takes further modules along — at the end of the page so
    // they do not hold up painting, and only where they are needed.
    require_once __DIR__ . '/icons.php';
    echo icon_stock() . "\n";
    // The chat window, where the header's chat bubble stood (header_chat).
    if (!empty($GLOBALS['chat_window_wanted'])) {
        require_once __DIR__ . '/chat_window.php';
        chat_window();
    }
    /* The language catalog for the browser side (assets/js/i18n.js) —
       BEFORE the modules, which read it at import. Absent for German:
       t() without a catalog is the identity, and the German app pays
       nothing for the languages it does not speak. */
    if (lang_current() !== 'de') {
        echo '<script>window.LANG_DATA = '
            . json_encode(lang_catalog(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . ";</script>\n";
    }
    foreach (array_merge(['assets/js/dialogs.js', 'assets/js/autoheight.js', 'assets/js/tables.js',
                          'assets/js/toast.js', 'assets/js/folds.js', 'assets/js/clearable.js',
                          'assets/js/viewport.js', 'assets/js/droparea.js', 'assets/js/quietsave.js',
                          'assets/js/eszett.js', 'assets/js/headerchat.js', 'assets/js/scrollkeep.js',
                          'assets/js/fullscreen.js'],
                         $options['scripts'] ?? []) as $file) {
        printf('<script type="module" src="%s"></script>' . "\n", h(asset_url($file)));
    }

    echo "</body>\n</html>\n";
}

/**
 * Message block. $kind is 'error', 'success', 'hint' or 'warning'.
 * $messages may be a single text or a list.
 *
 * $float marks the block as ANSWER TO A DEED and not as a state of the
 * page: it then leaves the flow at load and floats past as a toast
 * (assets/js/toast.js). The distinction does not run along the kind, and
 * that cost a round: „Diese Gruppe gibt es nicht mehr." is an error and
 * still belongs in the corner, because it says how a click went — while
 * „Freunde brauchen die Migration 0041" is a hint and has to stand until
 * somebody applies it, otherwise a whole tab looks empty for no reason
 * (the maintainer saw exactly that error hanging in the page, 22.08.2026).
 *
 * The rule is therefore: everything a *_process() puts out floats;
 * everything a *_render() writes about the world stays.
 */
function notice(string $kind, string|array $messages, bool $float = false): void
{
    $list = is_array($messages) ? $messages : [$messages];
    if ($list === []) {
        return;
    }
    /* The kind IS the CSS word since the last German contract values left
       the code (21.08.2026) — the translation map that used to sit here
       was the one layer of its sort still standing. */
    $cssClass = h($kind);
    echo '<div class="notice notice-' . $cssClass . '"'
       . ($float ? ' data-toast="1"' : '') . '>';
    if (count($list) === 1) {
        echo '<p>' . h($list[0]) . '</p>';
    } else {
        echo '<ul>';
        foreach ($list as $text) {
            echo '<li>' . h($text) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}
