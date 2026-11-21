<?php
/**
 * Administration — one place for everything that is not the map itself.
 *
 * TWO AREAS, ONE LINK. Before, the paths were scattered: "Offline-Gebiete"
 * and "Papierkorb" at the bottom of the sidebar, the server tools under an
 * entry of their own that only administrators saw anyway. Whoever looked for
 * something had to know in which of the two corners it lived.
 *
 *   MEIN BEREICH     is open to everyone and concerns one's own data.
 *   SERVER           only administrators see; this is about the installation
 *                    as a whole, not about a single account.
 *
 * The cut is not cosmetic: what stands together here shares the question
 * "who owns this?". The trash belongs to the user, the migrations belong to
 * the server — and that both used to run under the same word "Verwaltung"
 * was the reason there was no administration at all for non-administrators.
 *
 * The offline areas are the one entry that leads from here back onto the map:
 * outlining and weighing the tiles need the map layers, that cannot be lifted
 * onto a page of its own. The link opens the window over there — see
 * windowFromUrl() in map.js.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/trash_content.php';
require_once __DIR__ . '/../includes/friends_content.php';
require_once __DIR__ . '/../includes/groups_content.php';
require_once __DIR__ . '/../includes/share_content.php';
require_once __DIR__ . '/../includes/hints_content.php';
require_once __DIR__ . '/../includes/given_content.php';
require_once __DIR__ . '/../includes/excursions_content.php';
require_once __DIR__ . '/../includes/import_content.php';
require_once __DIR__ . '/../includes/organize_content.php';
require_once __DIR__ . '/../includes/settings_content.php';
require_once __DIR__ . '/../includes/cloud_content.php';
require_once __DIR__ . '/../includes/archives_content.php';
// The picture viewer Organisieren opens — the map's own (photowindow.js).
require_once __DIR__ . '/../includes/photos.php';
require_once __DIR__ . '/../includes/photo_window.php';
// For the offline list of the protected areas: it takes the provider's
// address and the kinds of area out of the registry instead of keeping its
// own copy of them.
require_once __DIR__ . '/../includes/map_layers.php';

/**
 * A tab that only builds when somebody opens it.
 *
 * WHY, MEASURED (25.08.2026): this page used to build every tab on every
 * call. With 1.640 points that was about a megabyte of markup per view,
 * and the Organisieren tab alone 820 KB of it — the whole point stock as
 * embedded JSON, shipped even to somebody who came for the import. On an
 * M1 that is a blink; on an older phone it is the difference between a
 * page and a wait, and a content blocker walks every node of it besides.
 *
 * THE ONE THAT JUST PROCESSED A FORM STILL BUILDS HERE. A post lands on
 * this page, its *_process() has already run above, and the answer lives
 * in that state — fetching the tab afterwards would run the renderer in a
 * SECOND request, where the state is gone. So: whoever posted gets his
 * result inline, exactly as before; everybody else gets an empty shell
 * and pages/administration_tab.php fills it on demand.
 *
 * The section names are the ones the forms carry in their hidden 'section'
 * field AND the ones the tab buttons carry as data-anchor — one word from
 * the form through the loader to the endpoint, no translation table.
 */
function tab_content(string $name, callable $render): void
{
    if (($_POST['section'] ?? '') === $name) {
        $render();
        return;
    }
    echo '<div data-tab-src="' . h($name) . '" data-anchors="' . h(implode(' ', tab_anchors($name))) . '"></div>';
}

/**
 * THE ANCHORS A WAITING TAB HOLDS (08.11.2026: the link from the
 * export window to „Einstellungen → Fotos → Export" landed on the last tab
 * one had open). An address like #export names a tab INSIDE a fragment that
 * is not fetched yet — at load there is no button with that anchor, so the
 * bars fell back to their memory, for every sub-tab alike. The empty shell
 * now says which anchors it will bring, and tabs.js opens the tab whose
 * shell holds the wanted one; when the fragment arrives, its own bars find
 * the anchor in the address and open it.
 *
 * READ FROM THE RENDERER'S OWN FILE (includes/<name>_content.php), not
 * written down here: a list kept by hand would be the second truth that
 * forgets the next sub-tab. A file read per page build is cheap — two of
 * them carry anchors at all.
 */
function tab_anchors(string $name): array
{
    $file = __DIR__ . '/../includes/' . basename($name) . '_content.php';
    if (!is_file($file)) {
        return [];
    }
    preg_match_all('/data-anchor="([a-z0-9-]+)"/', (string) file_get_contents($file), $m);
    return array_values(array_unique($m[1]));
}

$me    = login_required();
$userId = (int) $me['id'];
$admin  = is_admin($me);

// Before any output: the forms of trash, friends and groups send back to
// here.
$trashMessage  = trash_process($pdo, $userId);
$friendsState    = friends_process($pdo, $userId);
$groupsState    = groups_process($pdo, $userId);
$shareState    = share_process($pdo, $userId);
$givenState    = given_process($pdo, $userId);
$hintsState    = hints_process($pdo, $userId);
$excursionsState = excursions_process($pdo, $userId);
$importState    = import_process($pdo, $userId);
$organizeState   = organize_process($pdo, $userId);
$settingsState   = settings_process($pdo, $userId);
$cloudState    = cloud_process($pdo, $userId);
$archivesState = archives_process($pdo, $userId);

// Open requests to me belong on the tab as a number, like with the trash:
// otherwise nobody opens a tab in which someone is waiting.
$friendsPending = friends_pending($pdo, $userId);

// Deviations from the original (the lifeline, stage 4 of sharing) —
// counted AFTER processing, otherwise the old number would sit on the
// tab after restoring.
$shareDeviations = container_ready($pdo) ? point_deviations($pdo, $userId) : [];

// And after it for the same reason: where the space of a container has
// grown since someone last looked (migration 0049). Computed once, shared
// by the tab number and the view.
$shareAdditions = container_ready($pdo) ? additions_per_container($pdo, $userId) : [];

// How much sits in the trash belongs on the entry: otherwise you have to
// look inside to know whether it is worth it.
$trashCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM map_points WHERE user_id = ' . $userId . ' AND deleted_at IS NOT NULL'
)->fetchColumn();

$pending       = [];
$counts      = null;
$invitationsState   = ['code' => null, 'error' => []];
$membersState  = ['message' => null, 'error' => []];
$migrationsReport  = [];
$elevationState = null;
$mtbState    = null;
$speciesState  = null;
$dgfmMarksState = null;
$municipalitiesState    = null;
$protectedAreasState = null;
$cloudCopiesState = null;
$taxRun     = null;
$mykisState = null;
$cloudProvidersState = null;
$shareConvertState = null;
$webLinksState = null;
$shareConvertPending = 0;
$secretWarnings = [];

if ($admin) {
    require_once __DIR__ . '/../includes/migrations.php';
    require_once __DIR__ . '/../includes/members_content.php';
    require_once __DIR__ . '/../includes/invitations_content.php';
    require_once __DIR__ . '/../includes/migrations_content.php';
    require_once __DIR__ . '/../includes/elevation_content.php';
    require_once __DIR__ . '/../includes/mtb_content.php';
    require_once __DIR__ . '/../includes/point_species_content.php';
    require_once __DIR__ . '/../includes/dgfm_marks_content.php';
    require_once __DIR__ . '/../includes/municipalities_content.php';
    require_once __DIR__ . '/../includes/protected_areas_content.php';
    require_once __DIR__ . '/../includes/cloud_copies_content.php';
    require_once __DIR__ . '/../includes/thumbs_content.php';
    require_once __DIR__ . '/../includes/dump_content.php';
    require_once __DIR__ . '/../includes/tax_transfer_content.php';
    require_once __DIR__ . '/../includes/mykis_content.php';
    require_once __DIR__ . '/../includes/cloud_providers_content.php';
    require_once __DIR__ . '/../includes/share_convert_content.php';
    require_once __DIR__ . '/../includes/folders_convert_content.php';
    require_once __DIR__ . '/../includes/web_links_content.php';

    // All four before the output, each one tells by the 'bereich' field
    // whether it was meant. Only administrators get here — which is why the
    // files are only loaded at this point at all.
    $membersState  = members_process($pdo, $userId);
    $invitationsState   = invitations_process($pdo, $userId);
    $migrationsReport  = migrations_process($pdo);
    $elevationState = elevation_process($pdo);
    $mtbState    = mtb_process($pdo);
    $speciesState  = point_species_process($pdo);
    $dgfmMarksState = dgfm_marks_process($pdo);
    $municipalitiesState    = municipalities_process($pdo);
    $protectedAreasState = protected_areas_process($pdo);
    $cloudCopiesState = cloud_copies_process($pdo);
    $thumbsState = thumbs_process($pdo);
    $taxRun     = tax_transfer_process($pdo);
    $mykisState = mykis_process($pdo, $userId);
    $cloudProvidersState = cloud_providers_process($pdo);
    $shareConvertState = share_convert_process($pdo);
    $webLinksState = web_links_process($pdo);
    $shareConvertPending = share_convert_pending($pdo);
    $foldersConvertState = folders_convert_process($pdo);
    // Released with the sidebar's Mappen (FOLDERS_CONVERT_RELEASED).
    $foldersConvertShown = FOLDERS_CONVERT_RELEASED
        && (folders_convert_pending($pdo) > 0 || $foldersConvertState['message'] !== null);
    // After processing: a secret just renewed must not warn any more.
    $secretWarnings = cloud_secret_warnings();

    // Count only afterwards: otherwise the number from before would sit in
    // the notice and on the tab after applying.
    $pending = migrations_pending($pdo);

    $counts = $pdo->query(
        'SELECT
            (SELECT COUNT(*) FROM map_users)  AS accounts,
            (SELECT COUNT(*) FROM map_points WHERE deleted_at IS NULL) AS points,
            (SELECT COUNT(*) FROM map_lists  WHERE deleted_at IS NULL) AS lists,
            (SELECT COUNT(*) FROM map_invites WHERE redeemed_by IS NULL) AS invitations_open'
    )->fetch();
}

// Almost full width (24.08.2026): Organisieren's table wants every
// column it can get, and the prose protects its own measure (.hint-text).
page_header(t('Verwaltung'), [
    'wide' => true,
    // The viewer's own stylesheet, the one the map loads — see below.
    'head_extra' => '<link rel="stylesheet" href="' . h(asset_url('assets/css/photowindow.css')) . '">',
]);
?>
<?php if ($admin && $pending !== []): ?>
    <?php notice('hint', t('%d Migration(en) noch nicht eingespielt.', count($pending))); ?>
<?php endif; ?>
<?php /* A CLIENT SECRET RUNNING OUT (OneDrive's lives 24 months at most) —
         a STATE, so it stands in the flow on every tab until somebody
         enters a new one; the map shows the same sentence in a window once
         a day (cloud_secret_warnings, 02.10.2026). */ ?>
<?php foreach ($secretWarnings as $w): ?>
    <?php notice($w['expired'] ? 'error' : 'warning', cloud_secret_warning_text($w)); ?>
<?php endforeach; ?>

<?php if ($admin): ?>
<div class="tab" role="tablist" aria-label="<?= h(t('Bereiche der Verwaltung')) ?>">
    <button type="button" role="tab" id="tab-mine" aria-controls="section-mine" data-anchor="mine"><?= icon('person') ?><?= h(t('Mein Bereich')) ?></button>
    <?php /* The anchor is still called 'server' — bookmarks and the remembered
         tab are not supposed to notice the renaming. */ ?>
    <button type="button" role="tab" id="tab-server" aria-controls="section-server" data-anchor="server"><?= icon('sliders') ?><?= h(t('Administration')) ?></button>
    <button type="button" role="tab" id="tab-numbers" aria-controls="section-numbers" data-anchor="numbers"><?= icon('bars') ?><?= h(t('Statistik')) ?></button>
</div>
<?php endif; ?>

<section id="section-mine" <?= $admin ? 'role="tabpanel" aria-labelledby="tab-mine"' : '' ?>>

    <?php /* Tabs within a tab. Both belong to the user and both are a list one
         leafs through — demanding yet another page change for that would be
         a detour without gain. */ ?>
    <?php /* THE ORDER IS A DECISION (02.10.2026): Einstellungen, Kontakte,
         Offline, Gemeinsam, Organisieren, Cloud, Import, Papierkorb — what
         one comes for often up front, what one sets up once (the cloud) and
         the rarer chores at the back. Not alphabetical. The sections below
         stay in their old order — only one is visible anyway, and the
         buttons here determine the row. */ ?>
    <div class="tab" role="tablist" aria-label="<?= h(t('Mein Bereich')) ?>">
        <button type="button" role="tab" id="tab-settings" aria-controls="section-settings"
                data-anchor="settings"><?= icon('gear') ?><?= h(t('Einstellungen')) ?></button>
        <?php /* KONTAKTE AND GEMEINSAM, two tabs since 02.10.2026 (
             „thematisch sind die beiden Sachen ja schon voneinander
             getrennt"). WITH WHOM — friends and working groups — and WHAT
             TOGETHER — Freigaben, direct shares, excursions. „Kontakte" and
             not „Freunde": the tab would bear the name of its own first
             sub-tab. And each count is now its own: an open request stands
             at Kontakte, a change in a Freigabe at Gemeinsam — the one sum
             before said neither. */ ?>
        <button type="button" role="tab" id="tab-contacts" aria-controls="section-contacts"
                data-anchor="contacts"><?= icon('people') ?><?= h(t('Kontakte')) ?><?= $friendsPending > 0 ? ' (' . $friendsPending . ')' : '' ?></button>
        <button type="button" role="tab" id="tab-offline" aria-controls="section-offline"
                data-anchor="offline"><?= icon('device') ?><?= h(t('Offline')) ?></button>
        <?php $sharePending = count($shareDeviations) + count($shareAdditions); ?>
        <button type="button" role="tab" id="tab-shared" aria-controls="section-shared"
                data-anchor="shared"><?= icon('network') ?><?= h(t('Gemeinsam')) ?><?= $sharePending > 0 ? ' (' . $sharePending . ')' : '' ?></button>
        <button type="button" role="tab" id="tab-organize" aria-controls="section-organize"
                data-anchor="organize"><?= icon('list') ?><?= h(t('Organisieren')) ?></button>
        <button type="button" role="tab" id="tab-cloud" aria-controls="section-cloud"
                data-anchor="cloud"><?= icon('cloud') ?><?= h(t('Cloud')) ?></button>
        <?php /* BILDARCHIVE BESIDE CLOUD, NOT INSIDE IT (02.11.2026):
             Cloud is where one's own cloud is connected to STORE finds; an
             archive is somebody's read share hung in — perhaps from the
             same provider, but a different thing, and with its own long
             list of names to assign, skip and hide. */ ?>
        <button type="button" role="tab" id="tab-archives" aria-controls="section-archives"
                data-anchor="archives"><?= icon('photo') ?><?= h(t('Bildarchive')) ?></button>
        <button type="button" role="tab" id="tab-import" aria-controls="section-import"
                data-anchor="import"><?= icon('import') ?><?= h(t('Import')) ?></button>
        <button type="button" role="tab" id="tab-trash" aria-controls="section-trash"
                data-anchor="trash"><?= icon('delete') ?><?= h(t('Papierkorb')) ?><?= $trashCount > 0 ? ' (' . $trashCount . ')' : '' ?></button>
    </div>

    <section id="section-offline" role="tabpanel" aria-labelledby="tab-offline">
    <?php /* Areas and data under ONE roof: both are "what sits on this device",
         just once as tiles, once as name lists. The anchors moved to
         'areas' and 'data' with everything else; tabs.js carries the old
         German ones as a takeover map, so bookmarks and the remembered
         tab survive. */ ?>
    <div class="tab" role="tablist" aria-label="<?= h(t('Offline')) ?>">
        <button type="button" role="tab" id="tab-areas" aria-controls="section-areas"
                data-anchor="areas"><?= icon('outline') ?><?= h(t('Offline-Gebiete')) ?></button>
        <button type="button" role="tab" id="tab-data" aria-controls="section-data"
                data-anchor="data"><?= icon('storage') ?><?= h(t('Offline-Daten')) ?></button>
    </div>

    <section id="section-areas" role="tabpanel" aria-labelledby="tab-areas">
        <p class="hint-text">
            <?= h(t('Markiere hier Gebiete, die du gerne als Offline-Karten herunterladen willst. Herunterladen musst du sie dann auf das Gerät, das du in dem Gebiet benutzt — vermutlich dein Handy.')) ?>
        </p>

        <?php /* Header as with the friends (.column-kopf): heading on the left,
             the action — creating, in this case — on the right as a round
             plus. Outlining only works on the map: you have to tap it in
             corner by corner. */ ?>
        <div class="column-header">
            <h3><?= h(t('Gebiete')) ?></h3>
            <a class="button small" href="<?= h(url('index.php?window=areas&new=1')) ?>"
               title="<?= h(t('Neues Gebiet')) ?>" aria-label="<?= h(t('Neues Gebiet')) ?>"><?= icon('plus') ?><?= h(t('Neu')) ?></a>
        </div>

        <div id="area-list"><p class="hint-text"><?= h(t('wird geholt …')) ?></p></div>

        <?php /* HERE STOOD A STORAGE LINE AND "ZWISCHENSPEICHER LEEREN" —
             the same ones as in the map's areas window, and gone for the
             same reason: the number came from navigator.storage.estimate()
             and counts opaque tiles with a deliberate random padding of
             7.7 MB apiece on average (measured, see layers.js). The maintainer
             saw 825 MB where roughly one megabyte lay. The button thereby
             promised to free hundreds of megabytes that never existed — and
             a button that keeps a false promise is worse than none. The
             incidentally collected tiles are now managed by the browser
             alone; the 800 cap in sw.js keeps cleaning up on its own. What
             really sits on this device is listed per area in the list
             above. */ ?>
    </section>

    <?php /* Offline data: what lies on THIS device.
 
         THE NAME LISTS ARE NOT IN HERE ANYMORE, and their absence is the
         point. Four rows stood here with a description, a source and a
         "keep current" checkbox — a whole apparatus for a decision that no
         longer exists, since all four arrive on their own and keep
         themselves current (19.08.2026: "einfach stillschweigend
         automatisch laden"). They are fetched by map.js a few seconds after
         loading, whether anybody opens this tab or not.
 
         The report on one's own points went the same way, and for the same
         reason: it said "Nur zur Auskunft" itself. A line that reports and
         offers nothing to do gets read once out of curiosity and never
         again. The reconciling stays the map's job — on opening it compares
         its periods with the server and fetches what has changed.

         What remains is the one thing one really does decide: which states'
         protected-area boundaries to carry along. */ ?>
    <section id="section-data" role="tabpanel" aria-labelledby="tab-data" hidden>
        <?php /* The protected-area boundaries — what one needs where one had
             not planned to be. By state and not by outlined area, because a
             detour does not ask the outline first; and it fits, 3.1 MB for
             Hesse's 768 nature reserves, measured before this was built.

             NO HEADING — it is the only block in the tab again, and a
             heading over a single block names it twice. From 11.09. to
             23.09.2026 the recorded tracks stood below it with a „Vom
             Gerät löschen" that meant nothing: the map fetched them back
             on the next opening, and since the smoothing a walk weighs
             about 4 KB on the device (13 walks, 54 KB, measured).
             „das kannst du einfach löschen". The tracks now simply stay,
             like the finds.

             TWO BLOCKS AGAIN SINCE 11.11.2026, so each has its heading:
             the „Entfernen" tool of the photo window joined, the second
             thing one carries by decision — 95 MB nobody should find on
             the device without knowing where to take them off again. */ ?>
        <h3><?= h(t('Schutzgebiete')) ?></h3>
        <p class="hint-text">
            <?= h(t('Hier holst du die Schutzgebiete eines Bundeslands aufs Gerät: Nationalparke, Naturschutz- und FFH-Gebiete. Dann siehst du auch im Funkloch, ob du gerade in einem unterwegs bist — samt Namen und Steckbrief.')) ?>
            <?= h(t('Klicke auf der Karte ein Bundesland an, dann wird es geladen. Klickst du es noch einmal an, ist es wieder vom Gerät gelöscht.')) ?>
        </p>

        <p class="hint-text">
            <?= h(t('Jedes Schutzgebiet zählt beim Bundesamt für Naturschutz zu genau einem Bundesland und kommt mit diesem vollständig, auch mit dem Teil jenseits der Grenze. Hessen bringt also seine Gebiete ganz mit, aber nicht die rheinland-pfälzischen, die nach Hessen hineinragen. Wer nahe einer Landesgrenze unterwegs ist, lädt deshalb beide Länder.')) ?>
        </p>

        <div id="protected-list"><p class="hint-text"><?= h(t('wird nachgesehen …')) ?></p></div>

        <h3 class="offline-block-head"><?= h(t('KI-Werkzeug')) ?></h3>
        <p class="hint-text">
            <?= h(t('„Entfernen“ beim Bearbeiten eines Bildes arbeitet mit einer künstlichen Intelligenz. Sie rechnet hier auf deinem Gerät und braucht dafür eigene Dateien. Das Gerät lädt sie beim ersten Benutzen, nachdem du zugestimmt hast — oder hier.')) ?>
        </p>
        <div id="inpaint-tool"<?= inpaint_strong_offered() ? ' data-strong="1"' : '' ?>><p class="hint-text"><?= h(t('wird nachgesehen …')) ?></p></div>

        <?php /* THE FULL PICTURES KEPT AT THE DESK (18.11.2026:
             „bei den Offline-Daten … die Größe einstellen … ihn ganz
             ausschalten … leeren, Größe ändern und gar nicht benutzen", and
             „meinen eigenen Haken"): the third thing that lies on a device,
             and the one that fills itself — so here it is said what it is,
             how much lies there, and how to make it less or nothing.
             administration.js draws state and choices (originalcache.js). */ ?>
        <h3 class="offline-block-head"><?= h(t('Volle Bilder')) ?></h3>
        <p class="hint-text">
            <?= h(t('Öffnest du am Computer ein Bild, holt YASAIMAPP es in voller Größe. Damit es beim nächsten Öffnen nicht wieder über das Internet kommen muss, bleibt es auf diesem Gerät liegen. Wird der Platz knapp, fallen die Bilder heraus, die du am längsten nicht mehr geöffnet hast. Auf dem Handy bleibt nichts liegen.')) ?>
        </p>
        <div id="originals-cache"><p class="hint-text"><?= h(t('wird nachgesehen …')) ?></p></div>

    </section>
    </section>

    <section id="section-contacts" role="tabpanel" aria-labelledby="tab-contacts" hidden>
    <?php /* Friends and working groups: WITH WHOM. Until 02.10.2026 they stood
         under one roof with the shares, in Gemeinsam. The anchors 'friends'
         and 'groups' stayed as they were, so form round-trips, bookmarks and
         the remembered tab find them in their new place by themselves
         (tabs.js opens the bar that holds the anchor). */ ?>
    <div class="tab" role="tablist" aria-label="<?= h(t('Kontakte')) ?>">
        <button type="button" role="tab" id="tab-friends" aria-controls="section-friends"
                data-anchor="friends"><?= icon('people') ?><?= h(t('Freunde')) ?><?= $friendsPending > 0 ? ' (' . $friendsPending . ')' : '' ?></button>
        <button type="button" role="tab" id="tab-groups" aria-controls="section-groups"
                data-anchor="groups"><?= icon('group') ?><?= h(t('Arbeitsgruppen')) ?></button>
    </div>

    <section id="section-friends" role="tabpanel" aria-labelledby="tab-friends" hidden>
        <?php tab_content('friends', fn() => friends_render($pdo, $userId, $friendsState)); ?>
    </section>

    <section id="section-groups" role="tabpanel" aria-labelledby="tab-groups" hidden>
        <?php tab_content('groups', fn() => groups_render($pdo, $userId, $groupsState)); ?>
    </section>
    </section>

    <section id="section-shared" role="tabpanel" aria-labelledby="tab-shared" hidden>
    <?php /* WHAT TOGETHER: the Freigaben, the single finds shared directly, the
         excursions. The anchor 'share' keeps its name as always; the numbers
         for which one opens the tab (deviations, additions) stand outside as
         a sum and here individually. */ ?>
    <?php
    /* THE OLD BOXES' TAB only while one has any (list_id NULL) — once the
       administration has converted them, „Geteilt" is the whole story
       (09.10.2026). */
    $oldBoxes = 0;
    foreach (container_mine($pdo, $userId) as $c) {
        if (list_share_list($pdo, (int) $c['id']) === null) {
            $oldBoxes++;
        }
    }
    ?>
    <div class="tab" role="tablist" aria-label="<?= h(t('Gemeinsam')) ?>">
        <?php /* „GETEILT" (09.10.2026): which lists I share with whom
             — by list or by friend — and which others share with me. Only
             an overview; sharing itself happens at the list. The anchor is
             'given' and no id says share: see the note below. */ ?>
        <button type="button" role="tab" id="tab-given" aria-controls="section-given"
                data-anchor="given"><?= icon('people') ?><?= h(t('Geteilt')) ?></button>
        <?php /* Was called "Geteilte Listen"; the anchor 'teilen' stays, as always.

             THE ID IS 'tab-container' AND NOT 'tab-share', AND THAT IS NOT
             COSMETIC. Content blockers carry cosmetic rules against social
             share buttons and hide, quite generically, what is called
             'share'. On the maintainer's desktop this tab was display:none —
             measured 20.08.2026: present in the HTML, tabs.js had wired it
             (aria-selected, tabindex), our stylesheet byte-identical to the
             local one, no rule in it that could match, no script setting
             display. On the phone, without the extension, the same page
             showed it. Half a day went into the search.

             The ANCHOR stays 'share': it stands in bookmarks, in form
             round-trips and in the devices' remembered tab, and a blocker
             does not read the address bar. Only the id had to go — and it
             was wrong anyway. */ ?>

        <?php /* „FREIGABEN", NOT „CONTAINER" ON SCREEN (25.08.2026:
             „eigentlich sind es Freigaben"). The word describes what the
             thing does — somebody frees their lists for others — while
             „Container" described the vessel and left the user to guess
             what goes in it. It was called „Geteilte Listen" before
             13.08.2026 and „Container" after.

             THE CODE KEEPS CONTAINER, and that is not laziness: „share" is
             already taken in this project for a DIFFERENT thing — hanging
             one list into one container (api/v1/shares.php, table
             map_shares). Renaming the vessel to „share" too would make one
             word mean two things, which is exactly the confusion the
             rename was meant to remove. Six tables, five foreign keys and
             a migration would have been the price for making the code
             harder to read. Display name here, technical name below — the
             same split as YASAIMAPP itself. */ ?>
        <?php if ($oldBoxes > 0 || ($_POST['section'] ?? '') === 'share'): ?>
        <button type="button" role="tab" id="tab-container" aria-controls="section-container"
                data-anchor="share"><?= icon('container') ?><?= h(t('Alte Freigaben')) ?><?= $sharePending > 0 ? ' (' . $sharePending . ')' : '' ?></button>
        <?php endif; ?>
        <?php /* „DIREKT GETEILT" (02.10.2026): single finds shown to
             single friends — the Freigaben's small sibling, so it stands
             beside them. The eye, because a hint SHOWS a spot and hands out
             no right. The id says hints and never share: content blockers
             hide what is called share (see tab-container above). */ ?>
        <button type="button" role="tab" id="tab-hints" aria-controls="section-hints"
                data-anchor="hints"><?= icon('eye') ?><?= h(t('Direkt geteilt')) ?></button>
        <?php /* The outing into the terrain — hence the mountain, not another
             people icon: what sets an excursion apart from friends,
             groups and containers is not WHO but WHERE and WHEN. */ ?>
        <button type="button" role="tab" id="tab-excursions" aria-controls="section-excursions"
                data-anchor="excursions"><?= icon('mountain') ?><?= h(t('Exkursionen')) ?></button>
    </div>

    <section id="section-given" role="tabpanel" aria-labelledby="tab-given" hidden>
        <?php tab_content('given', fn() => given_render($pdo, $userId, $givenState)); ?>
    </section>

    <?php if ($oldBoxes > 0 || ($_POST['section'] ?? '') === 'share'): ?>
    <section id="section-container" role="tabpanel" aria-labelledby="tab-container" hidden>
        <?php share_render($pdo, $userId, $shareState, $shareDeviations, $shareAdditions); ?>
    </section>
    <?php endif; ?>

    <section id="section-hints" role="tabpanel" aria-labelledby="tab-hints" hidden>
        <?php tab_content('hints', fn() => hints_render($pdo, $userId, $hintsState)); ?>
    </section>

    <section id="section-excursions" role="tabpanel" aria-labelledby="tab-excursions" hidden>
        <?php tab_content('excursions', fn() => excursions_render($pdo, $userId, $excursionsState)); ?>
    </section>
    </section>

    <section id="section-import" role="tabpanel" aria-labelledby="tab-import" hidden>
        <?php tab_content('import', fn() => import_render($pdo, $userId, $importState)); ?>
    </section>

    <section id="section-organize" role="tabpanel" aria-labelledby="tab-organize" hidden>
        <?php tab_content('organize', fn() => organize_render($pdo, $userId, $organizeState)); ?>
    </section>

    <section id="section-cloud" role="tabpanel" aria-labelledby="tab-cloud" hidden>
        <?php tab_content('cloud', fn() => cloud_render($pdo, $userId, $cloudState)); ?>
    </section>

    <section id="section-archives" role="tabpanel" aria-labelledby="tab-archives" hidden>
        <?php tab_content('archives', fn() => archives_render($pdo, $userId, $archivesState)); ?>
    </section>

    <section id="section-trash" role="tabpanel" aria-labelledby="tab-trash" hidden>
        <p class="hint-text">
            <?= h(t('Gelöschte Punkte bleiben hier liegen, bis du sie endgültig entfernst. Ein Punkt ist zu mühsam erarbeitet, um ihn mit einem Fehlklick zu verlieren.')) ?>
        </p>
        <?php tab_content('trash', fn() => trash_render($pdo, $userId, $trashMessage)); ?>
    </section>

    <section id="section-settings" role="tabpanel" aria-labelledby="tab-settings" hidden>
        <?php tab_content('settings', fn() => settings_render($pdo, $userId, $settingsState)); ?>
    </section>
</section>

<?php if ($admin): ?>
<section id="section-server" role="tabpanel" aria-labelledby="tab-server" hidden>
    <?php /* Tabs within a tab, as in "Mein Bereich". Before, three tiles stood
         here that led to pages of their own: a map sheet that only showed
         the way to the content, and back again from every page. The content
         fits right here.

         The tiles described what awaits you there — that is not lost: it
         now stands as the first sentence IN each tab, where it is at hand
         while reading instead of in front of it. */ ?>
    <div class="tab" role="tablist" aria-label="<?= h(t('Administration')) ?>">
        <button type="button" role="tab" id="tab-members" aria-controls="section-members"
                data-anchor="members"><?= icon('badge') ?><?= h(t('Benutzerverwaltung')) ?></button>
        <button type="button" role="tab" id="tab-migrations" aria-controls="section-migrations"
                data-anchor="migrations"<?= $pending !== [] ? ' class="attention"' : '' ?>><?= icon('stack') ?><?= h(t('Migrationen')) ?><?= $pending !== [] ? ' (' . count($pending) . ')' : '' ?></button>
        <?php /* The one-time conversion of the old boxes into list shares
             (0160) — beside the migrations, because it is the same kind of
             deed: once, by an administrator, after looking. It glows while
             old boxes are left. */ ?>
        <?php if ($shareConvertPending > 0 || $shareConvertState['message'] !== null): ?>
        <button type="button" role="tab" id="tab-boxes_convert" aria-controls="section-boxes_convert"
                data-anchor="boxes_convert"<?= $shareConvertPending > 0 ? ' class="attention"' : '' ?>><?= icon('people') ?><?= h(t('Freigaben umstellen')) ?></button>
        <?php endif; ?>
        <?php /* The one-time conversion of the list trees into Mappen (0168),
             the same kind of deed as the one above. */ ?>
        <?php if ($foldersConvertShown): ?>
        <button type="button" role="tab" id="tab-folders_convert" aria-controls="section-folders_convert"
                data-anchor="folders_convert"<?= folders_convert_pending($pdo) > 0 ? ' class="attention"' : '' ?>><?= icon('folder') ?><?= h(t('Mappen umstellen')) ?></button>
        <?php endif; ?>
        <button type="button" role="tab" id="tab-backfill" aria-controls="section-backfill"
                data-anchor="backfill"><?= icon('pencil') ?><?= h(t('Nachtragen')) ?></button>
        <?php /* The delivery to the DGfM's state coordinators (0127): the list,
             their consent, and what waits to go out. */ ?>
        <button type="button" role="tab" id="tab-mapping" aria-controls="section-mapping"
                data-anchor="mapping"><?= icon('people') ?><?= h(t('DGfM-Kartierung')) ?></button>
        <?php /* The installation's registrations at the cloud providers
             (02.10.2026) — here and not in Mein Bereich → Cloud, where a
             connected cloud hides every other provider's tile. */ ?>
        <button type="button" role="tab" id="tab-cloud_providers" aria-controls="section-cloud_providers"
                data-anchor="cloud_providers"<?= $secretWarnings !== [] ? ' class="attention"' : '' ?>><?= icon('cloud') ?><?= h(t('Cloud-Anbieter')) ?></button>
        <button type="button" role="tab" id="tab-dump" aria-controls="section-dump"
                data-anchor="dump"><?= icon('dump') ?><?= h(t('Datenbankabzug')) ?></button>
        <?php /* Last, because needed most rarely: only when the name registers
             have been renewed. No warning color, even when something is
             missing — the application runs without the tables, it then asks
             online. A tab that glows orange forever on a server without a
             transfer admonishes to something nobody has to do. */ ?>
        <button type="button" role="tab" id="tab-taxonomy" aria-controls="section-taxonomy"
                data-anchor="taxonomy"><?= icon('tree') ?><?= h(t('Taxonomie einspielen')) ?></button>
        <?php /* Pilz-Link's portrait and map links per species (0166) — beside
             the taxonomy, the other data that comes from outside in one go. */ ?>
        <button type="button" role="tab" id="tab-web_links" aria-controls="section-web_links"
                data-anchor="web_links"><?= icon('link') ?><?= h(t('Artlinks')) ?></button>
    </div>

    <?php /* Users and invitations stand together, not side by side in two tabs:
         whoever invites does so BECAUSE they just looked at the list of
         users — and whoever assigns a role does it while inviting. A tab
         switch in between separates two moves that belong together.

         INVITATIONS FIRST (25.08.2026). They are the short
         list and the one that WANTS something: an open code is a person
         who has not arrived yet, and it stands there until someone acts
         on it. The user table is the long list and the one that is
         merely true — it grows without end and pushed the codes further
         down the page with every new account. */ ?>
    <section id="section-members" role="tabpanel" aria-labelledby="tab-members">
        <h2><?= h(t('Einladungen')) ?></h2>
        <?php invitations_render($pdo, $invitationsState); ?>

        <h2 style="margin-top:2.4rem"><?= h(t('Benutzer')) ?></h2>
        <?php members_render($pdo, $userId, $membersState); ?>
    </section>

    <section id="section-migrations" role="tabpanel" aria-labelledby="tab-migrations" hidden>
        <?php migrations_render($pdo, $migrationsReport, url('pages/administration.php') . '#migrations'); ?>
    </section>

    <?php if ($shareConvertPending > 0 || $shareConvertState['message'] !== null): ?>
    <section id="section-boxes_convert" role="tabpanel" aria-labelledby="tab-boxes_convert" hidden>
        <?php share_convert_render($pdo, $shareConvertState); ?>
    </section>
    <?php endif; ?>

    <?php if ($foldersConvertShown): ?>
    <section id="section-folders_convert" role="tabpanel" aria-labelledby="tab-folders_convert" hidden>
        <?php folders_convert_render($pdo, $foldersConvertState); ?>
    </section>
    <?php endif; ?>

    <section id="section-backfill" role="tabpanel" aria-labelledby="tab-backfill" hidden>
    <?php /* The backfill tools under ONE roof: all do the same thing — compute
         a derived value for points that still lack it. The anchors of the
         sub-tabs stay (hoehen, mtb, arten, gemeinden, schutzgebiete); the
         "nachtragen" in the name sits on the roof, not next to it.

         The protected areas are the only one that does NOT mean all points
         but only finds — they still stand here because the move is the
         same. */ ?>
    <div class="tab" role="tablist" aria-label="<?= h(t('Nachtragen')) ?>">
        <button type="button" role="tab" id="tab-elevation" aria-controls="section-elevation"
                data-anchor="elevation"><?= icon('mountain') ?><?= h(t('Höhen')) ?></button>
        <button type="button" role="tab" id="tab-cloud_copies" aria-controls="section-cloud_copies"
                data-anchor="cloud_copies"><?= icon('cloud') ?><?= h(t('Kopien')) ?></button>
        <button type="button" role="tab" id="tab-thumbs" aria-controls="section-thumbs"
                data-anchor="thumbs"><?= icon('photo') ?><?= h(t('Vorschauen')) ?></button>
        <button type="button" role="tab" id="tab-mtb" aria-controls="section-mtb"
                data-anchor="mtb"><?= icon('map') ?><?= h(t('Blätter')) ?></button>
        <button type="button" role="tab" id="tab-species" aria-controls="section-species"
                data-anchor="species"><?= icon('label') ?><?= h(t('Arten')) ?></button>
        <button type="button" role="tab" id="tab-municipalities" aria-controls="section-municipalities"
                data-anchor="municipalities"><?= icon('place') ?><?= h(t('Gemeinden')) ?></button>
        <button type="button" role="tab" id="tab-protected-areas" aria-controls="section-protected-areas"
                data-anchor="protected-areas"><?= icon('outline') ?><?= h(t('Schutzgebiete')) ?></button>
    </div>

    <section id="section-elevation" role="tabpanel" aria-labelledby="tab-elevation" hidden>
        <?php elevation_render($pdo, $elevationState); ?>
    </section>
    <section id="section-cloud_copies" role="tabpanel" aria-labelledby="tab-cloud_copies" hidden>
        <?php cloud_copies_render($pdo, $cloudCopiesState); ?>
    </section>
    <section id="section-thumbs" role="tabpanel" aria-labelledby="tab-thumbs" hidden>
        <?php thumbs_render($pdo, $thumbsState); ?>
    </section>

    <section id="section-mtb" role="tabpanel" aria-labelledby="tab-mtb" hidden>
        <?php mtb_render($pdo, $mtbState); ?>
    </section>

    <section id="section-species" role="tabpanel" aria-labelledby="tab-species" hidden>
        <?php point_species_render($pdo, $speciesState); ?>
        <?php dgfm_marks_render($pdo, $dgfmMarksState); ?>
    </section>

    <section id="section-municipalities" role="tabpanel" aria-labelledby="tab-municipalities" hidden>
        <?php municipalities_render($pdo, $municipalitiesState); ?>
    </section>

    <section id="section-protected-areas" role="tabpanel" aria-labelledby="tab-protected-areas" hidden>
        <?php protected_areas_render($pdo, $protectedAreasState); ?>
    </section>
    </section>

    <section id="section-mapping" role="tabpanel" aria-labelledby="tab-mapping" hidden>
        <?php mykis_render($pdo, $mykisState); ?>
    </section>

    <section id="section-cloud_providers" role="tabpanel" aria-labelledby="tab-cloud_providers" hidden>
        <?php cloud_providers_render($pdo, $cloudProvidersState); ?>
    </section>

    <section id="section-dump" role="tabpanel" aria-labelledby="tab-dump" hidden>
        <?php dump_render($pdo); ?>
    </section>

    <section id="section-taxonomy" role="tabpanel" aria-labelledby="tab-taxonomy" hidden>
        <?php tax_transfer_render($pdo, $taxRun); ?>
    </section>

    <section id="section-web_links" role="tabpanel" aria-labelledby="tab-web_links" hidden>
        <?php web_links_render($pdo, $webLinksState); ?>
    </section>
</section>

<section id="section-numbers" role="tabpanel" aria-labelledby="tab-numbers" hidden>
    <p class="hint-text"><?= h(t('Stand dieser Anlage, über alle Konten.')) ?></p>

    <div class="table-frame">
        <table class="list">
            <tr><th><?= h(t('Konten')) ?></th><td><?= (int) $counts['accounts'] ?></td></tr>
            <tr><th><?= h(t('Punkte')) ?></th><td><?= (int) $counts['points'] ?></td></tr>
            <tr><th><?= h(t('Listen')) ?></th><td><?= (int) $counts['lists'] ?></td></tr>
            <tr><th><?= h(t('Offene Einladungen')) ?></th><td><?= (int) $counts['invitations_open'] ?></td></tr>
            <tr><th><?= h(t('Im Papierkorb (dieses Konto)')) ?></th><td><?= $trashCount ?></td></tr>
        </table>
    </div>
</section>
<?php endif; ?>

<?php /* The icon stock comes from page_footer() since clearable.js — printed
     here a second time it would duplicate #icon-stock. */ ?>

<?php /* The marker shapes: the containers' mount-in window shows
     each list with its symbol in its color (shared.js via
     assets/js/shapes.js) — the geometry comes from includes/shapes.php, as
     everywhere. */ ?>
<?= shape_stock() ?>

<?php /* THE MAP'S PICTURE VIEWER, printed here too (30.09.2026): Organisieren
     opens it for a find's pictures instead of the small viewer it used to
     build itself. In the page and not in the Organisieren fragment,
     because photowindow.js looks its elements up once, when it is
     mounted — a fragment fetched again would leave it holding the old
     ones. */ ?>
<?php photo_window_render(); ?>

<script>
    // The same start data as on the map: api.js reads the base URL and the
    // form token from it. Points and layers are not needed here.
    window.START_DATA = {
        baseUrl: <?= json_encode(BASE_URL) ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        // FOR THE PICTURE VIEWER, what the map hands it too: who is
        // looking (a picture is one's own or somebody else's), how this
        // account measures, and its scale presets.
        // The name too, since 08.11.2026: an export fills {Name} with it
        // where no author is written (photowindow.js, exportValues).
        user: { id: <?= (int) $userId ?>, name: <?= json_encode(user_display_name($me), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                finder: <?= json_encode(user_full_name($me), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?> },
        measure_style: <?= json_encode(column_exists($pdo, 'map_users', 'measure_style')
            ? measure_style_read($me['measure_style'] ?? null)
            : measure_style_read(null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        // JSON_HEX_TAG as on the map: the names are the account's own
        // text, and a closing script tag typed into one must not end this
        // block. (Nor may this comment spell that tag out — it did, once,
        // for one test run, and ended the block right here.)
        photo_scales: <?= json_encode(photo_scales($pdo, $userId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        scale_devices: <?= json_encode(scale_devices($pdo, $userId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        // THE OUTLINE LAYER, and only that one. The offline list for the
        // protected areas needs the provider's address and the kinds of
        // area — and it takes them from the registry rather than keeping a
        // second copy, so a fourth kind added over there arrives here by
        // itself. The whole layer list has no business on this page; it is
        // long, and nothing here draws a map. THE KEY IS 'layers', THE SAME
        // WORD AS ON THE MAP: registryEntry() in protectedareas.js reads
        // one name and finds its outline entry in either page's list.
        layers: <?= json_encode(array_values(array_filter(
            map_layers_for_js(), static fn(array $e): bool => ($e['type'] ?? '') === 'wfs-outline'
        )), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<?php /* tabs.js, organize.js AND shared.js ARE NO LONGER ENTRY POINTS
     (25.08.2026).
     administration.js imports them by their bare name, because it has to
     call their mount functions when a fetched tab brings their data block
     along. Listed here as well they would arrive a SECOND time under
     „…?v=123" — and the same file under two addresses is two modules with
     two separate states. organize.js guards its start with a flag; that
     flag would exist twice, and the table would be built twice. The trap
     is written down in CLAUDE.md; this is what it looks like in the
     wild. */ ?>
<?php page_footer(['scripts' => ['assets/js/administration.js', 'assets/js/user.js',
                                 'assets/js/calibrate.js', 'assets/js/measurepreview.js',
                                 'assets/js/cloudmove.js', 'assets/js/microscopesettings.js',
                                 'assets/js/exportsettings.js']]); ?>
