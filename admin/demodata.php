<?php
/**
 * Permanent test data for the local setup. Command line only.
 *
 *   php admin/demodata.php            shows what is there
 *   php admin/demodata.php --new      builds everything anew (deletes first)
 *
 * WHY. Up to here every test built its own fixture and cleaned it away
 * afterwards. That is tidy and was still the cause of an outage: the
 * throwaway user had no shared list, and exactly the one line that draws
 * the symbol of a hung-in list crashed on the live page on 20.08.2026.
 * Whatever the fixture lacks, no test can see — and a fixture reinvented
 * every time has different gaps every time.
 *
 * So the stock now lives here, in one place, and deliberately covers the
 * cases that are rare and hurt:
 *
 *   - a heading list with sublists (points live only in the leaf)
 *   - a species list, so the title field makes suggestions
 *   - shared lists in every kind (since 09.10.2026, „Listen teilen"):
 *     somebody else's list shared with Anna (rights), Anna's list worked
 *     in together (own points), and one shared with a group through a
 *     filter — or, before migration 0160, the old boxes with a FOREIGN
 *     hung-in list
 *   - a friendship and a group
 *   - a point in the trash, one without a title, one without a list
 *   - an area with an outline
 *
 * LOCAL ONLY. On the live server the script refuses: it creates accounts
 * and deletes them again, and neither has any business there.
 *
 * THE PROTECTED-AREA POLYGONS ARE MISSING HERE, and that is no oversight:
 * they live in the browser's Cache Storage, not in the database. They
 * belong to a DEVICE, not to an account — they can only be downloaded in
 * the "Offline" tab, and deliberately so (see assets/js/protectedareas.js).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/friends.php';
require_once __DIR__ . '/../includes/container.php';
require_once __DIR__ . '/../includes/photos.php';

if (!IS_LOCAL) {
    fwrite(STDERR, "Nur örtlich. Auf dem Live-Server legt niemand Prüfkonten an.\n");
    exit(1);
}

/* A domain of its own, NOT the tests' one. tests/*.php do delete via fixed
   identifiers and not via the domain — but the distance makes clear at a
   glance what a test run may sweep away and what has to stay. */
const DEMO_DOMAIN = '@demo.local';
const DEMO_PASSWORD = 'Demo-2026';

// '--neu' stays accepted: the flag stood in notes and shell histories
// before the move to English, and a flag that silently does nothing would
// print the summary instead of rebuilding — which looks like success.
$fresh = in_array('--new', array_slice($argv, 1), true) || in_array('--neu', array_slice($argv, 1), true);

/** Who is already there. */
function demo_users(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT id, email, first_name, last_name, role FROM map_users WHERE email LIKE ?');
    $stmt->execute(['%' . DEMO_DOMAIN]);
    return $stmt->fetchAll();
}

// --- zeigen -----------------------------------------------------------------

if (!$fresh) {
    $existing = demo_users($pdo);
    if ($existing === []) {
        echo "Keine Prüfdaten vorhanden. Anlegen mit:\n  php admin/demodata.php --new\n";
        exit(0);
    }
    echo "Prüfdaten (Kennwort für alle: " . DEMO_PASSWORD . ")\n\n";
    foreach ($existing as $u) {
        $lists = $pdo->prepare('SELECT COUNT(*) FROM map_lists WHERE user_id = ?');
        $lists->execute([$u['id']]);
        $points = $pdo->prepare('SELECT COUNT(*) FROM map_points WHERE user_id = ? AND deleted_at IS NULL');
        $points->execute([$u['id']]);
        $trash = $pdo->prepare('SELECT COUNT(*) FROM map_points WHERE user_id = ? AND deleted_at IS NOT NULL');
        $trash->execute([$u['id']]);
        printf("  %-22s %-8s %2d Listen, %2d Punkte, %d im Papierkorb\n",
            $u['email'], $u['role'], $lists->fetchColumn(), $points->fetchColumn(), $trash->fetchColumn());
    }
    echo "\nNeu aufbauen mit --new.\n";
    exit(0);
}

// --- sweeping up --------------------------------------------------------------

echo "Räume alte Prüfdaten weg …\n";
$oldIds = array_column(demo_users($pdo), 'id');
if ($oldIds !== []) {
    $idList = implode(',', array_map('intval', $oldIds));
    // Everything hanging off an account — the user_id column says where.
    $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $tables = $pdo->prepare(
        'SELECT table_name FROM information_schema.columns
          WHERE table_schema = ? AND column_name = ?'
    );
    $tables->execute([$db, 'user_id']);
    foreach ($tables->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec("DELETE FROM `$table` WHERE user_id IN ($idList)");
    }
    // Containers hang off owner_user_id, not user_id.
    $pdo->exec("DELETE FROM map_containers WHERE owner_user_id IN ($idList)");
    container_cleanup($pdo);
    $pdo->exec("DELETE FROM map_users WHERE id IN ($idList)");
    printf("  %d Konten entfernt\n", count($oldIds));
}

// --- anlegen ----------------------------------------------------------------

/** Ein Konto. */
function demo_user(PDO $pdo, string $first_name, string $last_name, string $role): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        mb_strtolower($first_name) . DEMO_DOMAIN,
        password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT),
        $first_name, $last_name, $role,
    ]);
    return (int) $pdo->lastInsertId();
}

echo "Lege an …\n";
$anna  = demo_user($pdo, 'Anna',  'Musterfrau', 'admin');
$ben = demo_user($pdo, 'Ben', 'Mustermann', 'user');
$carla = demo_user($pdo, 'Carla', 'Beispiel',   'user');
printf("  drei Konten: anna%s (admin), ben%s, carla%s\n",
    DEMO_DOMAIN, DEMO_DOMAIN, DEMO_DOMAIN);

/* „MEINE FUNDE" WITH ONE SUBLIST PER KINGDOM — the app's basic concept
   (15.09.2026: „Jedes Reich gehört in eine eigene Liste —
   Pilze, Pflanzen, Flechten. Alles kommt immer in eine Liste. Mache
   einfach die Hauptliste, die eh vorgeschlagen wird, Meine Funde, und
   dort als Unterliste Pilze, Flechten, Pflanzen."). The fixture is what
   the help's pictures show, so it shows the app used as meant: NO lists
   by place — where a find lies the map knows itself.

   The heading is also the constellation every stock needs (CLAUDE.md,
   19.08.2026): points live exclusively in the leaves, the heading carries
   no shape, only the triangle — it was uneditable for two days once
   because a half-renamed field took it apart. */
/* SINCE THE MAPPEN (migration 0168, 11.10.2026) the kingdoms
   lie in a Mappe „Meine Funde" — a name that gathers them in the sidebar,
   nothing else — and each list is a species list of its own: a Mappe
   carries no settings. A second Mappe „Tiere" for the folded case, one
   loose list „Waldbrände" between them. Before 0168 the old heading with
   sublists, the species flag on the root. */
$folders = folders_exist($pdo);
$meine   = $folders ? folder_create($pdo, $anna, ['name' => 'Meine Funde']) : null;
$root    = $folders ? null : list_create($pdo, $anna, ['name' => 'Meine Funde', 'color' => '#96ff50', 'symbol' => 'mushroom', 'is_species' => true]);
$in      = static fn(?int $folder, ?int $parent): array => $folder !== null ? ['folder_id' => $folder] : ['parent_id' => $parent];
$fungi   = list_create($pdo, $anna, ['name' => 'Pilze',    'color' => '#96ff50', 'symbol' => 'mushroom', 'is_species' => true, 'species_groups' => ['fungi']] + $in($meine, $root));
$lichens = list_create($pdo, $anna, ['name' => 'Flechten', 'color' => '#c9a227', 'symbol' => 'lichen',   'is_species' => true, 'species_groups' => ['lichens']] + $in($meine, $root));
$plants  = list_create($pdo, $anna, ['name' => 'Pflanzen', 'color' => '#6aa84f', 'symbol' => 'tree',     'is_species' => true, 'species_groups' => ['plants']] + $in($meine, $root));
/* AND ONE FLAT LIST BESIDE THE KINGDOMS (16.09.2026, for the
   help's sidebar pictures: „eine Liste mit Unterlisten, eine Liste ohne
   Unterlisten und die Rückfallliste Ohne Liste"). Not a species list —
   places one watches, not finds one names. */
$fires   = list_create($pdo, $anna, ['name' => 'Waldbrände', 'color' => '#e07b1f', 'symbol' => 'flag']);
/* A SECOND MAPPE, FOR THE FOLDED CASE (16.09.2026, help
   1.6.2.1: „eine Liste mit Unterlisten, mit einer Markierung innerhalb
   einer Unterliste, die zusammengeklappt ist"). A kingdom again, so the
   fixture keeps to the mantra. */
$tiere   = $folders ? folder_create($pdo, $anna, ['name' => 'Tiere']) : null;
$animals = $folders ? null : list_create($pdo, $anna, ['name' => 'Tiere', 'color' => '#c0392b', 'symbol' => 'insect', 'is_species' => true]);
$insects = list_create($pdo, $anna, ['name' => 'Insekten', 'color' => '#c0392b', 'symbol' => 'insect', 'is_species' => true, 'species_groups' => ['insects']] + $in($tiere, $animals));
$birds   = list_create($pdo, $anna, ['name' => 'Vögel',    'color' => '#3a86c8', 'symbol' => 'bird',   'is_species' => true, 'species_groups' => ['birds']] + $in($tiere, $animals));
/* AND ONE LIST OUTSIDE ANY MAPPE (11.10.2026, for the help's
   „Meine Listen" picture): „Waldbrände" is worked in with Ben and so stands
   under „Gemeinsame Listen", which leaves the own tree without a plain
   list. A waymark list, no species list, no share. */
$waymarks = list_create($pdo, $anna, ['name' => 'Wegmarken', 'color' => '#4a90b8', 'symbol' => 'cross']);

// Ben has a list of his own; it is hung into Anna's container below.
$bens = list_create($pdo, $ben, ['name' => 'Bens Beobachtungen', 'color' => '#e864af',
    'symbol' => 'bird']);

echo $folders
    ? "  Mappen „Meine Funde\" mit Pilze, Flechten, Pflanzen und „Tiere\" mit Insekten, Vögel, dazu die Listen Waldbrände und Wegmarken — und Bens Liste\n"
    : "  9 Listen: „Meine Funde\" mit Pilze, Flechten, Pflanzen, „Tiere\" mit Insekten, Vögel, dazu Waldbrände — und Bens\n";

/** One point. */
function demo_point(PDO $pdo, int $user, array $data): int
{
    return point_create($pdo, $user, $data);
}

// Points: in the leaf, in the species list, without a list, without a title, in the trash.
// A SPECIES-LIST FIND WITH ITS FIELDS FILLED (15.09.2026, for
// the help's pictures: „nicht Steinpilz am Buchenrand, sondern Boletus
// edulis, mit den ganzen spezifischen Artenlistenfeldern"). It lies in
// the species list „Pilze", as every find of that kingdom.
$p1 = demo_point($pdo, $anna, ['title' => 'Boletus edulis', 'lat' => 51.3721, 'lon' => 9.9755,
    'lists' => [$fungi], 'found_at' => '2026-08-14 09:20:00', 'note' => 'Drei Stück, zwei stehengelassen.',
    // A FINISHED identification with the certainty field filled — the
    // fine identification filter (drawIdentification) needs both kinds
    // in the set, or its field checkboxes can never be seen doing
    // anything against the demo data.
    // A mycorrhizal fungus has no substrate and no host to write down —
    // only its companions, and those under their scientific names, the
    // way the field's own suggestions spell them (15.09.2026).
    'extra' => ['Finder' => 'Anna Musterfrau', 'Bestimmer' => 'Anna Musterfrau',
                'Bestimmungssicherheit' => 'makroskopisch sicher', 'Anzahl / Menge' => '3',
                'Begleitvegetation' => 'Fagus sylvatica, Quercus robur, Picea abies',
                'Beleg-Nr.' => 'AM-2026-014']]);
$p2 = demo_point($pdo, $anna, ['title' => 'Cantharellus cibarius', 'lat' => 51.3688, 'lon' => 9.9812,
    'lists' => [$fungi], 'found_at' => '2026-08-15 16:05:00']);
// AN OPEN IDENTIFICATION, deliberately: the "sp." in the title is what
// the abbreviation hooks of the identification filter grab, and the
// field says the same in its own words. Open identifications belong in
// the stock (the rule) — a demo set of only finished finds
// would leave the whole work-list side of the filter unreachable.
$p3 = demo_point($pdo, $anna, ['title' => 'Scutellinia sp.', 'lat' => 51.3502, 'lon' => 9.9410,
    'lists' => [$fungi], 'found_at' => '2026-07-30 11:00:00',
    'extra' => ['Bestimmungssicherheit' => 'unbestimmt']]);
$p4 = demo_point($pdo, $anna, ['title' => '', 'lat' => 51.3610, 'lon' => 9.9900,
    'lists' => [], 'found_at' => '2026-08-02 08:00:00']);
// THREE YEARS IN THE STOCK (19.09.2026): the lichen from 2025, the stag
// beetle from 2024 — so the year band of the time filter has a span to
// show, and its help picture is not a single step.
$p5 = demo_point($pdo, $anna, ['title' => 'Xanthoria parietina', 'lat' => 51.3555, 'lon' => 9.9600,
    'lists' => [$lichens], 'found_at' => '2025-06-11 13:30:00']);
$p7 = demo_point($pdo, $anna, ['title' => 'Brandfläche Steinbruch', 'lat' => 51.3660, 'lon' => 9.9520,
    'lists' => [$fires], 'found_at' => '2026-07-22 15:10:00']);
$p8 = demo_point($pdo, $anna, ['title' => 'Lucanus cervus', 'lat' => 51.3745, 'lon' => 9.9660,
    'lists' => [$insects], 'found_at' => '2024-06-28 21:40:00']);
$p6 = demo_point($pdo, $ben, ['title' => 'Hallimasch am Stubben', 'lat' => 51.3801, 'lon' => 9.9500,
    'lists' => [$bens], 'found_at' => '2026-08-18 07:45:00']);

// One into the trash — the tab should have something to show.
$path = demo_point($pdo, $anna, ['title' => 'Verworfener Punkt', 'lat' => 51.3400, 'lon' => 9.9300,
    'lists' => [$plants], 'found_at' => '2026-05-01 10:00:00']);
point_delete($pdo, $anna, $path);

printf("  9 Punkte, davon einer ohne Titel, einer ohne Liste, einer im Papierkorb\n");

/* BELEGFOTOS AT THE STEINPILZ (18.09.2026, help 1.7: „von
   jedem Tab einen Screenshot"). The Fotos tab of the point window is
   the one the pictures are taken for, and an empty gallery shows neither
   the motif groups nor the tile with its name. Four drawn pictures,
   three motif names — enough for the grouping to be visible.

   DRAWN, NOT COPIED: there is no photograph in the repository and there
   will be none (data/ is the user's). GD paints a cap and a stem for the
   macro shots and scattered spores for the micro one; nobody needs to
   recognise a fungus in them, only a picture in the tile.

   STRAIGHT INTO THE TABLE, past photo_create(): that function takes an
   upload (is_uploaded_file) and nothing else, and the quota check and
   the co-ownership bookkeeping it does are for real uploads. The columns
   written here are the ones it writes, plus the title (0097). */
function demo_photo(PDO $pdo, int $user, int $point, string $title, string $kind, int $seed): void
{
    if (!function_exists('imagecreatetruecolor') || !column_exists($pdo, 'map_photos', 'title')) {
        return;
    }
    mt_srand($seed);
    $paint = static function (int $w, int $h) use ($kind) {
        $im = imagecreatetruecolor($w, $h);
        if ($kind === 'micro') {
            // A pale field with dark ellipses — spores under the microscope.
            imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 214, 222, 232));
            $ink = imagecolorallocate($im, 96, 72, 48);
            for ($i = 0; $i < 28; $i++) {
                imagefilledellipse($im, mt_rand(20, $w - 20), mt_rand(20, $h - 20),
                    (int) ($w * .06), (int) ($w * .04), $ink);
            }
        } else {
            // Ground, a stem, a cap — the sketch of a bolete.
            imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 92, 108, 70));
            imagefilledrectangle($im, 0, (int) ($h * .72), $w, $h, imagecolorallocate($im, 74, 62, 44));
            imagefilledrectangle($im, (int) ($w * .42), (int) ($h * .45), (int) ($w * .58), (int) ($h * .78),
                imagecolorallocate($im, 222, 206, 168));
            imagefilledellipse($im, (int) ($w * .5), (int) ($h * .42), (int) ($w * .5), (int) ($h * .3),
                imagecolorallocate($im, 132, 84, 44));
            if ($kind === 'cut') {
                // The section: a lighter half laid over the sketch.
                imagefilledrectangle($im, (int) ($w * .5), (int) ($h * .27), $w, (int) ($h * .78),
                    imagecolorallocate($im, 236, 226, 200));
            }
        }
        return $im;
    };
    $dir = user_photo_directory_create($user);
    $stem = bin2hex(random_bytes(16));
    $big = $paint(640, 480);
    imagejpeg($big, $dir . '/' . $stem . '.jpg', 82);
    $small = $paint(320, 240);
    imagejpeg($small, $dir . '/' . $stem . '_k.jpg', 78);
    imagedestroy($big);
    imagedestroy($small);
    $bytes = (int) filesize($dir . '/' . $stem . '.jpg') + (int) filesize($dir . '/' . $stem . '_k.jpg');
    $pdo->prepare('INSERT INTO map_photos
            (user_id, point_id, filename, original_name, width, height, bytes, taken_at, sort_order, created_at, title)
         VALUES (?, ?, ?, ?, 640, 480, ?, ?, ?, NOW(), ?)')
        ->execute([$user, $point, $stem . '.jpg', 'IMG_' . $seed . '.jpg', $bytes,
            '2026-08-14 09:' . (20 + $seed) . ':00', photo_next_sort_order($pdo, $point), $title]);
}
demo_photo($pdo, $anna, $p1, 'Hauptmotiv', 'macro', 1);
demo_photo($pdo, $anna, $p1, 'Hauptmotiv', 'macro', 2);
demo_photo($pdo, $anna, $p1, 'Schnittbild', 'cut', 3);
demo_photo($pdo, $anna, $p1, 'Sporen', 'micro', 4);
printf("  4 Belegfotos am Steinpilz, in drei Motivgruppen\n");

// AND ONE SEQUENCE (migration 0112) — the second voucher, so the help's
// picture of the Belege tab shows both kinds, and so a foreign viewer's
// window has a sequence with the sequencer's name on it.
require_once __DIR__ . '/../includes/sequences.php';
if (sequences_ready($pdo)) {
    sequence_create($pdo, $anna, $p1, [
        'text'      => ">ITS Boletus edulis Probe 7\nTCCGTAGGTGAACCTGCGGAAGGATCATTATTGAATAAACTTGATGGGTTGTTGCTGGCCTTCCGAGGCATGTGCACGCCCTGCTCATCCACTCTACACCTGTGCACTTACTGTGGGTTTCAGGGAGCTTGCGGAGCTTCCGGTCTATGTTTTATTACAAACACTTAAAGTATCAGAATGTGTATTGCGATGTAACGCATCTATATACAACTTTCAGCAACGGATCTCTTGGCTCTCGCATCGATGAAGAACGCAGCGAAATGCGATACGTAATGTGAATTGCAGAATTCAGTGAATCATCGAATCTTTGAACGCACCTTGCGCTCCTTGGTATTCCGAGGAGCATGCCTGTTTGAGTGTCATTAAATTCTCAACCTCTTC\n",
        'marker'    => 'ITS',
        'accession' => 'MN123456',
        'lab'       => 'Alvalab',
        'sample_no' => 'AW-2026-07',
        'primers'   => 'ITS1F / ITS4',
    ]);
    printf("  1 ITS-Sequenz am Steinpilz\n");
}

/**
 * Request and accept in one.
 *
 * friendship_request() returns the PERSON, not the request — its id sits
 * in map_friends and has to be fetched from there. Whoever plugs in the
 * user id instead hits some foreign row or none; exactly that happened on
 * the first attempt here, and it only surfaced because the second
 * friendship then aborted loudly.
 */
function demo_friendship(PDO $pdo, int $from, int $to): void
{
    friendship_request($pdo, $from, $to);
    $stmt = $pdo->prepare(
        "SELECT id FROM map_friends
          WHERE from_user_id = ? AND to_user_id = ? AND status = 'requested' LIMIT 1"
    );
    $stmt->execute([$from, $to]);
    friendship_accept($pdo, $to, (int) $stmt->fetchColumn());
}

// Friendship Anna–Ben and Anna–Carla, plus a group with Carla.
if (friends_ready($pdo)) {
    demo_friendship($pdo, $anna, $ben);
    demo_friendship($pdo, $anna, $carla);
    $group = group_create($pdo, $anna, 'Exkursion');
    group_add($pdo, $anna, $group, $carla);
    echo "  Freundschaften Anna–Ben und Anna–Carla, Gruppe „Exkursion\" mit Carla\n";
}

/* A CONTAINER WITH A FOREIGN HUNG-IN LIST — the case that was missing
   today. Only here is a list's symbol drawn SERVER-SIDE
   (share_content.php), and exactly there the administration broke off when
   the fallback in shapes.php pointed into the void. */
/* SINCE 09.10.2026 THE SHARES ARE LISTS („jeder teilt nur seine
   eigene Liste"). Three kinds, so every road the sidebar and the windows
   take is walked: Ben's list for Anna with ticks („Von Ben"), Anna's
   „Waldbrände" worked in together with Ben („Gemeinsame Listen"), and
   Anna's „Meine Funde" for her group „Exkursion" through a filter. The old
   boxes below stay the fallback before migration 0160. */
require_once __DIR__ . '/../includes/list_shares.php';
if (list_shares_ready($pdo) && friends_ready($pdo)) {
    list_share_save($pdo, $ben, $bens, [
        ['type' => 'friend', 'id' => $anna, 'rights' => ['title', 'note', 'move', 'attach_photos']],
    ]);
    list_share_save($pdo, $anna, $fires, [
        ['type' => 'friend', 'id' => $ben, 'rights' => array_merge(LIST_SHARE_RIGHTS, ['field:*'])],
    ]);
    // With Mappen the share is the list „Pilze" — a Mappe is never shared.
    list_share_save($pdo, $anna, $root ?? $fungi, [
        ['type' => 'group', 'id' => $group, 'rights' => ['title'],
         'filter' => ['terms' => [['type' => 'contains', 'text' => 'Boletus']]]],
    ]);
    echo $root === null
        ? "  Geteilt: Bens Liste für Anna, „Waldbrände“ gemeinsam mit Ben, „Pilze“ für die Gruppe mit Filter\n"
        : "  Geteilt: Bens Liste für Anna, „Waldbrände“ gemeinsam mit Ben, „Meine Funde“ für die Gruppe mit Filter\n";
} elseif (container_ready($pdo)) {
    $container = container_create($pdo, $anna, 'Gemeinsame Funde');
    container_add($pdo, $anna, $container, 'friend', $ben);
    share_create($pdo, $ben, $container, $bens);
    /* WITH A FEW RIGHTS, so the look window's sentence says more than
       „nur ansehen" (16.09.2026, help 1.6.2.1.1 point 6). */
    $shareId = (int) $pdo->query("SELECT id FROM map_container_lists WHERE container_id = $container AND list_id = $bens")->fetchColumn();
    if ($shareId > 0 && share_rights_ready($pdo)) share_rights_set($pdo, $ben, $shareId, ['title', 'note', 'move']);
    /* AND A FOREIGN ONE (16.09.2026, help 1.6.2.1.1: „Bearbeiten
       der Freigabe und Bearbeiten einer fremden Freigabe sind zwei
       verschiedene Dinge"): ben's container with anna as a member, his
       list hung in — anna sees it under „Geteilt" with his name on it. */
    $theirs = container_create($pdo, $ben, 'Pilzfreunde Nord');
    container_add($pdo, $ben, $theirs, 'friend', $anna);
    share_create($pdo, $ben, $theirs, $bens);
    /* AND JOINT (22.09.2026, gemeinsame Listen): ben's list in his own
       share is „gemeinsam bearbeiten" — anna may file points into it and
       tend ben's with every right. The one in anna's share stays as it
       is, with granted rights, so both kinds are in the fixture. */
    if (share_joint_ready($pdo)) {
        $bensShare = (int) $pdo->query("SELECT id FROM map_container_lists WHERE container_id = $theirs AND user_id = $ben")->fetchColumn();
        if ($bensShare > 0) share_joint_set($pdo, $ben, $bensShare, true);
    }
    echo "  Freigaben „Gemeinsame Funde\" (annas) und „Pilzfreunde Nord\" (bens), je mit Bens Liste\n";
}

/* TWO EXCURSIONS FOR THE SIDEBAR (16.09.2026, help 1.6.2.1.2):
   one anna leads, with a find of hers on it; one ben leads with anna as
   co-leader — so the „Exkursionen" block shows „meine" and „von Ben". */
require_once __DIR__ . '/../includes/excursions.php';
if (table_exists($pdo, 'map_excursions')) {
    $trip = excursion_create($pdo, $anna, ['name' => 'Herbstexkursion Rusteberg',
        'starts_at' => '2026-08-15T09:00', 'ends_at' => '2026-08-15T16:00', 'photos_allowed' => '1']);
    $pdo->prepare('UPDATE map_points SET excursion_id = ? WHERE id = ?')->execute([$trip, $p2]);
    $bens_trip = excursion_create($pdo, $ben, ['name' => 'Pilztag Eichsfeld',
        'starts_at' => '2026-09-05T09:00', 'ends_at' => '2026-09-05T17:00', 'photos_allowed' => '1']);
    excursion_leader_add($pdo, $ben, $bens_trip, $anna);
    echo "  Exkursionen „Herbstexkursion Rusteberg\" (anna leitet) und „Pilztag Eichsfeld\" (ben leitet, anna mit)\n";
}

// An area with an outline — for the "Offline" tab.
$corners = json_encode([[51.34, 9.92], [51.39, 9.92], [51.39, 10.00], [51.34, 10.00]]);
$pdo->prepare('INSERT INTO map_areas (user_id, name, corners, created_at, updated_at)
               VALUES (?, ?, ?, NOW(), NOW())')->execute([$anna, 'Hausrevier', $corners]);
echo "  Gebiet „Hausrevier\" mit Umrandung\n";

echo "\nFertig. Anmelden als anna" . DEMO_DOMAIN . " mit " . DEMO_PASSWORD . "\n";
echo "Die Schutzgebietsgrenzen gehören zum Gerät und werden im Reiter „Offline\" geholt.\n";
