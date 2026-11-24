<?php
/**
 * Fundbrowser — the finds with pictures, as tiles (includes/finds.php says
 * what reaches the page and why).
 *
 * THE SHAPE IS LIGHTROOM'S (10.10.2026): on the left what one
 * browses by — whose finds, which year —, in the middle the tiles, on the
 * right the one find one clicked, its pictures large and the way back to
 * the map. The right column is where the point window's work will move
 * later („da hätten wir natürlich mehr Platz"); for now it shows and
 * leads to the map, where the editing lives.
 *
 * The page is a frame; assets/js/finds.js fills it from api/v1/finds.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/photos.php';
require_once __DIR__ . '/../includes/photo_window.php';
require_once __DIR__ . '/../includes/plate_window.php';
require_once __DIR__ . '/../includes/point_window.php';
require_once __DIR__ . '/../includes/gallery_windows.php';
require_once __DIR__ . '/../includes/figure_window.php';

$me = login_required();

/* THE PICTURE VIEWER'S AND THE PLATE EDITOR'S STYLES — this page is a host
   of both since 11.10.2026, as the map and Organisieren are of the viewer. */
page_header(t('Fundbrowser'), [
    'wide'       => true,
    'tab'        => 'finds',
    'head_extra' => '<link rel="stylesheet" href="' . h(asset_url('assets/css/photowindow.css')) . '">'
                  . '<link rel="stylesheet" href="' . h(asset_url('assets/css/plates.css')) . '">'
                  . '<link rel="stylesheet" href="' . h(asset_url('assets/css/pointwindow.css')) . '">',
]);
?>
<div class="finds" id="finds" data-api="<?= h(url('api/v1/finds.php')) ?>" data-map="<?= h(url()) ?>">

    <?php /* BROWSING: the bar, then the choices, the tiles and — as soon as
         something is typed — the list of what matches. */ ?>
    <div class="finds-browse" id="finds-browse">
        <div class="finds-bar">
            <form class="finds-search" id="finds-search-form" role="search">
                <input type="search" id="finds-search" autocomplete="off"
                       aria-label="<?= h(t('Funde durchsuchen')) ?>"
                       placeholder="<?= h(t('Art, Fundnummer, Ort oder Tour suchen')) ?>">
            </form>
            <span class="finds-count" id="finds-count" aria-live="polite"></span>
            <label class="finds-size" id="finds-size-label" title="<?= h(t('Kachelgröße')) ?>">
                <?= icon('photo') ?>
                <input type="range" id="finds-size" min="120" max="320" step="10" value="180"
                       aria-label="<?= h(t('Kachelgröße')) ?>">
            </label>
        </div>

        <div class="finds-body">
            <?php /* WHAT ONE BROWSES BY: whose finds, then which year. Filled by
                 the script with the counts of what is there. */ ?>
            <nav class="finds-side" id="finds-side" data-scroll-remember="finds:side" aria-label="<?= h(t('Funde eingrenzen')) ?>"></nav>

            <section class="finds-grid" id="finds-grid" data-scroll-remember="finds:grid" aria-label="<?= h(t('Funde')) ?>">
                <p class="finds-empty hint-text" id="finds-empty"><?= h(t('Funde werden geladen …')) ?></p>
            </section>

            <?php /* THE BILDARCHIV (02.11.2026: „Mach Variante 1, nenn
                 es Bildarchiv"): the older pictures of one's own and one's
                 friends' picture archives, in tiles like the finds' — a
                 gallery, not finds: a click shows the pictures, nothing opens
                 a find. assets/js/findsarchive.js fills it. */ ?>
            <section class="finds-grid finds-archive" id="finds-archive" data-scroll-remember="finds:archive" aria-label="<?= h(t('Bildarchiv')) ?>" hidden></section>

            <?php /* THE SEARCH'S ANSWER: every find that matches — also those
                 without a picture —, newest find first, with day, place and
                 tour, so a find can be told apart by its text
                 (11.10.2026). */ ?>
            <section class="finds-results" id="finds-results" data-scroll-remember="finds:results" aria-label="<?= h(t('Treffer')) ?>" hidden>
                <div class="finds-results-head" aria-hidden="true">
                    <span></span>
                    <span><?= h(t('Fund')) ?></span>
                    <span><?= h(t('Funddatum')) ?></span>
                    <span><?= h(t('Ort')) ?></span>
                    <span><?= h(t('Tour')) ?></span>
                    <span><?= h(t('Von')) ?></span>
                </div>
                <div id="finds-results-rows"></div>
            </section>
        </div>
    </div>

    <?php /* THE FIND OPENED — THE COCKPIT (11.10.2026: „der
         Fundbrowser soll das neue Cockpit werden für die Funde"). It takes
         the whole area from the tiles; there is no side panel beside them
         any more. */ ?>
    <section class="finds-fund" id="finds-fund" data-tab="text" aria-label="<?= h(t('Fund')) ?>" hidden>
        <div class="finds-fund-bar" id="finds-fund-bar">
            <?php /* Signs without words, all at the bar's top (13.10.2026:
                 „Zurück und Zeigen mal ohne Texte und alle Buttons immer oben
                 anzeigen"); the word stays as tooltip and for the screen reader. */ ?>
            <button type="button" class="symbol-button small" id="finds-back"
                    title="<?= h(t('Zurück')) ?>" aria-label="<?= h(t('Zurück')) ?>"><?= icon('arrow-left') ?></button>
            <?php /* THE WINDOW'S OWN HEADER STANDS IN THIS BAR
                 (12.10.2026: „der doppelte Artname und die Info … ganz in die
                 Kopfzeile"): its name and facts line, and at the right the ⓘ
                 and the trash can that stood beside them. assets/js/finds.js
                 moves the elements here — they keep their ids, and
                 pointwindow.js goes on writing into them. */ ?>
            <div class="finds-fund-heading" id="finds-fund-heading"></div>
            <div class="finds-fund-actions" id="finds-fund-actions">
                <?php /* FUND · MIKROSKOP · SCHLÜSSEL, ONE ROW FOR ALL THREE
                     (24.11.2026: „eine einheitliche Kopfzeile mit den 3
                     Buttons … und dort switchen wir zwischen den Sachen …
                     von allen drei Modulen kann man in jeweils die 2 anderen
                     springen"). Whoever identifies a find changes between
                     its pictures, the microscope and the key all the time,
                     and every change used to be a page of its own: the key
                     a full load away, the camera's session ended by it.
                     Now the three are areas of the ONE find that stands in
                     this page, and nothing is loaded anew between them —
                     the key's page lives in the frame below, the microscope
                     is the window it always was, with the same row in its
                     head (gallery_windows.php).

                     ALL THREE FOLLOW THE FIND („der Fundwechsel wechselt
                     alle Module mit … wir bleiben nicht bei einem alten Stand
                     in irgendeinem Modul hängen"): another find opened here
                     is the find the camera saves to and the key keys for
                     (finds.js, areas).

                     The two buttons beside „Fund" show only where their
                     area can work — finds.js mirrors the point window's own
                     „Mikroskopieren" and „Schlüsseln", which keep deciding
                     that and stay in the page unseen.

                     THE THIRD IS CALLED „BESTIMMUNG" AND IS EVERYBODY'S
                     (24.11.2026; it was „Schlüssel" and stood only where a
                     module offers a key tool): identify_tool() names where
                     it leads — the module's tool, else the core's page with
                     the Internet-Links and the finds of the species. */ ?>
                <div class="area-switch" id="finds-areas" role="group" aria-label="<?= h(t('Bereich')) ?>" hidden>
                    <button type="button" class="button subtle small is-active" data-area="find" aria-pressed="true"><?= icon('photo') ?><?= h(t('Fund')) ?></button>
                    <button type="button" class="button subtle small" data-area="microscope" aria-pressed="false" hidden><?= icon('microscope') ?><?= h(t('Mikroskop')) ?></button>
                    <?php if (identify_tool(user_current()) !== null): ?>
                    <button type="button" class="button subtle small" data-area="key" aria-pressed="false" hidden><?= icon('key') ?><?= h(t('Bestimmung')) ?></button>
                    <?php endif; ?>
                </div>
                <?php /* FULL SCREEN FROM THE FIND'S BAR TOO (24.11.2026: „der
                     Wechsel in den Vollbildmodus ist aber momentan nur aus der
                     Yasai-Map Hauptkopfzeile möglich. Nicht aus der
                     Fundkopfzeile"). With a find open the page's own header
                     steps aside and this bar is the head — and the button
                     went with the header. The same button, by its
                     attribute: fullscreen.js serves every [data-fullscreen]
                     and shows it only where the browser can. finds.js puts
                     it at the bar's very end. */ ?>
                <button type="button" class="symbol-button small fullscreen-button" id="finds-fullscreen" data-fullscreen hidden
                        title="<?= h(t('Vollbild')) ?>" aria-label="<?= h(t('Vollbild')) ?>" aria-pressed="false"><?= icon('fullscreen', ['class' => 'when-off']) ?><?= icon('fullscreen-exit', ['class' => 'when-on']) ?></button>
            </div>
        </div>
        <?php /* THE FIND'S TABS, EACH WITH THE WHOLE WIDTH
             (12.10.2026: „komplett in eine Tab-Ansicht … dann haben wir für
             jeden Tab eine quasi volle Breite"). The same bar as the point
             window's own (.detail-tabs), one level up: Text, Bilder,
             Dateien, Sequenzen and Karte are that window's panels and
             sub-panels, shown one at a time by data-tab on the section
             (app.css), and the window's own two bars stay hidden. A find
             opens on „Text" („generell in den Text-Tab", finds.js drawFund). Speichern
             and „Zeigen" stand at the bar's right end, where every tab sees
             them — finds.js moves them out of the window's footer. */ ?>
        <div class="sidebar-tabs detail-tabs finds-tabs" role="tablist" id="finds-tabs"
             aria-label="<?= h(t('Text, Tafeln, Bilder, Dateien, Sequenzen oder Karte')) ?>">
            <button type="button" role="tab" data-tab="text" aria-selected="true"><?= h(t('Übersicht')) ?></button>
            <button type="button" role="tab" data-tab="plates" aria-selected="false" tabindex="-1"><?= h(t('Tafeln')) ?> <span class="tab-count" id="finds-plate-count" hidden>0</span></button>
            <button type="button" role="tab" data-tab="pictures" aria-selected="false" tabindex="-1"><?= h(t('Bilder')) ?> <span class="tab-count" id="finds-picture-count" hidden>0</span></button>
            <button type="button" role="tab" data-tab="files" aria-selected="false" tabindex="-1" hidden><?= h(t('Dateien')) ?></button>
            <button type="button" role="tab" data-tab="sequences" aria-selected="false" tabindex="-1"><?= h(t('Sequenzen')) ?></button>
            <button type="button" role="tab" data-tab="place" aria-selected="false" tabindex="-1"><?= h(t('Karte')) ?></button>
            <div class="finds-tabs-end" id="finds-tabs-end">
                <span class="finds-saved" id="finds-saved" aria-live="polite"></span>
            </div>
        </div>
        <div class="finds-fund-body">
            <?php /* BILDER: the rail on the left, the viewer beside it. THE RAIL
                 IS THE ONE PLACE TO ORDER THE PICTURES (12.10.2026:
                 „eine Leiste mit den Vorschaubildern, wo ich sortieren kann …
                 die die Vorschauleiste beim Bildbetrachter ersetzt … immer
                 aus- und einklappbar"): the point window's gallery — tiles
                 grouped by subject name, dragged into order, the plates'
                 row on top, the button that uploads —, moved here by
                 finds.js with its ids kept, so photogallery.js and plates.js
                 go on drawing into it. A .field because the window hides
                 „the field of the pictures" where a find may not have any
                 (applyDetailRights asks closest('.field')).

                 THE VIEWER IS NOT A SECOND WINDOW HERE: the picture viewer's
                 <dialog> moves into .finds-viewer and opens IN the page
                 (photowindow.js, host.inPage), its own strip hidden — a
                 click in the rail shows the picture beside it. */ ?>
            <div class="finds-pictures" id="finds-pictures">
                <div class="finds-rail">
                    <div class="field finds-belege" id="finds-belege">
                        <p class="finds-no-pictures hint-text" id="finds-no-pictures" hidden><?= h(t('Zu diesem Fund gibt es noch keine Bilder.')) ?></p>
                    </div>
                </div>
                <?php /* The rail's width is dragged here, as the plates' strip's
                     (finds.js, railEdge; 13.10.2026: „mach es so,
                     wie bei den Tafeln"). */ ?>
                <span class="finds-rail-edge" id="finds-rail-edge" aria-hidden="true"></span>
                <div class="finds-viewer" id="finds-viewer">
                    <p class="finds-viewer-empty hint-text" id="finds-viewer-empty"><?= h(t('Klicke links auf ein Bild, dann steht es hier groß.')) ?></p>
                </div>
            </div>
            <?php /* TAFELN: the plates of the find (12.10.2026: „Sobald
                 der Tab Bilder mindestens zwei Bilder enthält, sollten wir einen
                 neuen Tab machen für die Tafeln … die Tafel-Bearbeitung ähnlich
                 wie den Bildbetrachter dann komplett in den Tab Tafeln legen").
                 On top the plates' row — each plate a tile, „Neue Tafel
                 erstellen" where one may make one —, moved here by finds.js
                 out of the rail; beneath it the plate editor's <dialog>,
                 opened IN the page (plates.js, host.inPage). The tab always
                 stands — a plate of one picture with the find's details or a
                 measurement's statistics is a plate too. */ ?>
            <div class="finds-plates" id="finds-plates">
                <?php /* The plates' row stands here unseen (app.css): plates.js keeps
                     drawing it, and finds.js reads from it whether one may make a
                     plate. What one sees of it is the switch in the editor's head
                     and the offer below — a row of tiles over the editor took the
                     height the page needs (13.10.2026). */ ?>
                <div class="finds-plate-row" id="finds-plate-row"></div>
                <div class="finds-plate-editor" id="finds-plate-editor">
                    <div class="finds-plate-empty" id="finds-plate-empty-box">
                        <p class="hint-text" id="finds-plate-empty"></p>
                        <button type="button" class="button" id="finds-plate-make" hidden><?= icon('plate') ?><?= h(t('Neue Tafel erstellen')) ?></button>
                    </div>
                </div>
            </div>
            <?php /* KARTE: the find's own map (assets/js/findmap.js), beside the
                 window's panel with the direction, the outline, „Direkt
                 teilen" and the lists. The point is dragged or set by a
                 click, the outline drawn corner by corner — into the
                 window's fields, saved with „Speichern". Leaflet's files
                 are named here and loaded the first time the tab opens. */ ?>
            <div class="finds-map" id="finds-map"
                 data-leaflet-js="<?= h(asset_url('assets/vendor/leaflet/leaflet.js')) ?>"
                 data-leaflet-css="<?= h(asset_url('assets/vendor/leaflet/leaflet.css')) ?>">
                <div class="finds-map-canvas" id="finds-map-canvas"></div>
                <div class="finds-map-bar">
                    <button type="button" class="button subtle small" data-map-action="place" hidden><?= icon('place') ?><?= h(t('Position setzen')) ?></button>
                    <button type="button" class="button subtle small" data-map-action="fit" hidden
                            title="<?= h(t('Den ganzen Fund ins Bild holen')) ?>"><?= icon('fit') ?><?= h(t('Zum Fund')) ?></button>
                </div>
                <div class="finds-map-bar finds-map-draw" hidden>
                    <b><?= h(t('Umriss ziehen')) ?></b>
                    <button type="button" class="button subtle small" data-draw="back"><?= icon('undo') ?><?= h(t('Ecke zurück')) ?></button>
                    <button type="button" class="button subtle small" data-draw="cancel"><?= h(t('Abbrechen')) ?></button>
                    <button type="button" class="button small" data-draw="done"><?= icon('check') ?><?= h(t('Fertig')) ?></button>
                </div>
                <p class="finds-map-message" hidden></p>
            </div>
            <?php /* TEXT, SEQUENZEN, DATEIEN, KARTE: the point window itself
                 (includes/point_window.php, assets/js/pointwindow.js), mounted
                 by assets/js/cockpit.js — the same window as on the map, with
                 the same fields, the same species search and the same saving.
                 „ÜBERSICHT" (the tab keeps its old inner name „text", which
                 the remembered tabs and the addresses #fund=ID&tab=text
                 carry): the texts on the left, written here; on the right
                 the plates or else every picture, the files and the
                 sequences — only to open and to fetch (finds.js, drawFace). */ ?>
            <div class="finds-texts" id="finds-texts">
                <?php point_window_render(); ?>
                <aside class="finds-face" id="finds-face" aria-label="<?= h(t('Bilder, Dateien und Sequenzen des Fundes')) ?>"></aside>
            </div>
        </div>
        <?php /* THE „BESTIMMUNG" FOR THIS FIND, under the find's bar — the
             page identify_tool() names (includes/modules.php: a module's key
             tool, else the core's pages/identify.php), without its header
             bar (its address with embed=1). It takes the place of the
             tabs and their content while the area „Bestimmung" shows
             (data-area on the section, app.css), and STAYS LOADED while
             the find stays: back from the microscope or from the pictures,
             a key stands where it was left. Not for a guest — without the
             page neither the frame nor the button above exists, and
             finds.js steps over both. */ ?>
        <?php /* THE MICROSCOPE, AN AREA LIKE THE KEY (24.11.2026: „momentan
             ist das Mikroskop ja ein Overlay-Modal. Das könnten wir dann
             quasi in den Inhalt packen, wie den Schlüssel" — so that the
             find's bar with its row stays the same in all three). The
             microscope's <dialog> (gallery_windows.php) is moved in here by
             finds.js and opened IN the page with show(), as the picture
             viewer is in its tab; its own head is not drawn. The camera
             runs on between the find's areas and ends when the find is
             left. On the map it stays the full-screen window it was. */ ?>
        <div class="finds-microview" id="finds-microview"></div>
        <?php if (identify_tool(user_current()) !== null): ?>
        <div class="finds-keyview" id="finds-keyview" hidden>
            <iframe id="finds-key-frame" title="<?= h(t('Bestimmung')) ?>"></iframe>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php photo_window_render(); ?>
<?php plate_window_render(); ?>
<?php gallery_windows_render(); ?>
<?php figure_window_render(); ?>

<script>
    /* The same start data the map hands its windows (api.js reads the base
       URL and the form token from it): who is looking, how this account
       measures, its scale presets — and what the point window reads of the
       start data: lists, shared lists, find fields, number series
       (point_window_start). JSON_HEX_TAG as on the map: the names are the
       account's own text, and a closing script tag typed into one must not
       end this block. */
    window.START_DATA = <?= json_encode([
        'baseUrl' => BASE_URL,
        'csrf' => csrf_token(),
        'measure_style' => column_exists($pdo, 'map_users', 'measure_style')
            ? measure_style_read($me['measure_style'] ?? null)
            : measure_style_read(null),
        'photo_scales' => photo_scales($pdo, (int) $me['id']),
        'scale_devices' => scale_devices($pdo, (int) $me['id']),
        // The whole layer registry, for the tab „Karte" (findmap.js builds the big map's layers from it).
        'mapLayers' => map_layers_for_js(),
    ] + point_window_start($pdo, $me), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<?php
page_footer(['scripts' => ['assets/js/finds.js']]);
