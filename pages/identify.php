<?php
/**
 * THE FIND'S „BESTIMMUNG", AS EVERY ACCOUNT HAS IT — the Internet-Links to
 * the name of a find and the finds of that species with a picture.
 *
 * (24.11.2026: the button at the find „ist ja viel mehr Bestimmung … bei
 * denen, die das Modul nicht freigegeben haben, eben nur die Links"; and
 * „diese beiden Sachen, Links und die Funde, könnten wir quasi immer
 * anzeigen".) Neither holds anything that is not the installation's own:
 * the links are written from the name (includes/web_links.php), the finds
 * are the Fundbrowser's stock (includes/species_finds.php). Where a module
 * offers a key tool for the account (includes/modules.php, key_tool), ITS
 * page is opened instead of this one and shows the same two beside its own
 * content — so this page reads the same parameters as that contract:
 *
 *   find   the find one comes from — its tile is marked among the finds,
 *          and outside a frame it is where „Zurück zum Fund" leads
 *   name   the species as it stands in the find's field
 *   embed  1 = inside another page's frame: no header bar, no way back
 *          (the find's own bar stands above the frame)
 *
 * resume, user and way belong to a key tool and are passed over.
 *
 * NOTHING HERE TALKS TO A FOREIGN SERVER: a link is an address that opens
 * in a tab of its own when one clicks it (assets/js/weblinks.js) — no
 * preview, no icon of the site is fetched before.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/species_finds.php';
require_once __DIR__ . '/../includes/figure_window.php';

$me = login_required();

$embed = ($_GET['embed'] ?? '') === '1';
$find  = max(0, (int) ($_GET['find'] ?? 0));
$title = mb_substr(trim((string) ($_GET['name'] ?? '')), 0, 190);

/* THE CODE GOES LIVE BEFORE THE MIGRATIONS: without the taxonomy tables the
   name is taken as it stands, and the links that need no number still come. */
$lists   = table_exists($pdo, 'map_tax_gbif') && table_exists($pdo, 'map_tax_dgfm');
$asked   = species_asked($title);
$unknown = $lists && $asked['name'] !== '' && !$asked['species'] && !species_genus_known($pdo, $asked['name']);
$numbers = $lists && $asked['species'] ? species_numbers($pdo, $asked['name']) : ['gbif' => null, 'dgfm' => null];

page_header(t('Bestimmung'), ['wide' => true, 'embed' => $embed]);

$back = !$embed && $find > 0
    ? '<a class="button subtle small" href="' . h(url('pages/finds.php') . '#fund=' . $find) . '">' . icon('arrow-left') . ' ' . h(t('Zurück zum Fund')) . '</a>'
    : '';

if ($asked['name'] === '' || $unknown): ?>
<div class="identify">
    <div class="identify-head"><h2><?= h(t('Bestimmung')) ?></h2><?= $back ?></div>
    <?php /* A quiet sentence instead of empty tabs: the name is what everything here hangs on. */ ?>
    <p class="hint-text"><?= $title === ''
        ? h(t('Dieser Fund hat noch keinen Namen. Trag bei „Art" einen Namen ein. Dann stehen hier die Internet-Links und die Funde dazu.'))
        : h(t('„%s" ist kein Art- oder Gattungsname, den die Listen kennen. Deshalb gibt es dazu keine Links und keine Funde.', $title)) ?></p>
</div>
<?php
    page_footer();
    exit;
endif;
?>
<div class="identify" id="identify"
     data-name="<?= h($asked['name']) ?>" data-species="<?= $asked['species'] ? '1' : '0' ?>"
     data-gbif="<?= (int) ($numbers['gbif'] ?? 0) ?>" data-dgfm="<?= (int) ($numbers['dgfm'] ?? 0) ?>"
     data-find="<?= $find ?>" data-embedded="<?= $embed ? '1' : '0' ?>">
    <div class="identify-head"><h2><em><?= h($asked['name']) ?></em></h2><?= $back ?></div>
    <?php if (!$asked['species']): ?>
    <?php /* The finds are matched by the species in a find's title; a genus alone has none to show. */ ?>
    <p class="hint-text small"><?= h(t('Der Fund nennt nur die Gattung. Die Funde dazu zeigen wir, sobald eine Art eingetragen ist.')) ?></p>
    <?php endif; ?>
</div>
<?php figure_window_render(); ?>
<script>
    // START_DATA before the modules: api.js (behind archiveexport.js) reads the
    // base URL and the form token the moment it loads.
    window.START_DATA = {
        baseUrl: <?= json_encode(BASE_URL) ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        // FOR „EXPORTIEREN" on an older picture (archiveexport.js): the account's
        // export templates and name pattern, and the name an own archive's pictures carry.
        <?php require_once __DIR__ . '/../includes/photos.php'; ?>
        measure_style: <?= json_encode(column_exists($pdo, 'map_users', 'measure_style')
            ? measure_style_read($me['measure_style'] ?? null) : measure_style_read(null),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        user: <?= json_encode(['name' => user_display_name($me),
            'finder' => user_full_name($me) !== '' ? user_full_name($me) : user_display_name($me)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        // The circle the names on this page are told apart in („Finn H." beside „Finn B.", names.js personName).
        <?php require_once __DIR__ . '/../includes/friends.php'; ?>
        friends: <?= json_encode(friends_ready($pdo)
            ? array_map(static fn(array $f): array => ['name' => $f['display_name']], friends_overview($pdo, (int) $me['id'])['friends'])
            : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    };
</script>
<?php
page_footer(['scripts' => ['assets/js/identify.js']]);
