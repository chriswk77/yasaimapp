<?php
/**
 * Tests the one-time job that brings a pCloud to one spelling of its
 * umlauts (includes/cloud_spelling.php) — and that a folder is found
 * however it is spelt (pcloud_folder_ensure).
 *
 *   php tests/cloud_spelling.php
 *
 * THE TREE IS THE MAINTAINER'S, IN SMALL (survey of 22.11.2026): a find
 * folder twice — the old one spelt „u" plus dots with its pictures, the
 * new one spelt „ü" with the plate saved after pCloud stopped rewriting
 * names —, a „Bearbeitung" in each, a plate page twice in one folder, and
 * a list folder that merely has the old spelling. Against pCloud played in
 * memory (tests/fake_pcloud.php), which REPLACES a namesake on rename as
 * the real one does: a run that passes here laid no file over another.
 * The test user, find and rows are removed at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud.php';
require_once __DIR__ . '/../includes/cloud_spelling.php';
require_once __DIR__ . '/fake_pcloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

if (!class_exists('Normalizer')) {
    echo "  (no Normalizer on this PHP — the two spellings cannot be told to be one name here)\nAll good.\n";
    exit(0);
}

// „ü" as one sign, and as the letter with its dots — the same to the eye.
$one = static fn(string $n): string => Normalizer::normalize($n, Normalizer::FORM_C);
$two = static fn(string $n): string => Normalizer::normalize($n, Normalizer::FORM_D);
$leaf = '2026-08-29 Krün wk-1';
$head = 'Boletus edulis 2026-08-29 DE,BAY, Krün ';

echo "The comparison key\n";
check($two($leaf) !== $one($leaf) && cloud_name_key($two($leaf)) === $one($leaf) && cloud_name_key('Wiesbaden') === 'Wiesbaden',
    'two spellings, one key — and a name without an umlaut is its own');
check(cloud_spelling_free('X.jpg', ['X.jpg' => true]) === 'X (2).jpg'
      && cloud_spelling_free('X.jpg', ['X.jpg' => true, 'X (2).jpg' => true]) === 'X (3).jpg', 'beside a namesake the next free „ (2)", „ (3)"');
check(cloud_spelling_kind($head . '02 Sporen.jpg.messwerte.json') === 'sidecar' && cloud_spelling_kind($head . '00 Tafel 2.jpg') === 'plate'
      && cloud_spelling_kind($head . '02 Sporen.jpg') === 'other', 'a sidecar, a plate page and a picture are told apart by their names');

$tree = static function () use ($one, $two, $leaf, $head): void {
    FakePcloud::reset([
        100 => ['', null],
        200 => ['Arten', 100],
        300 => [$two($leaf), 200],           // the old folder, spelt with dots
        310 => ['Bearbeitung', 300],
        301 => [$one($leaf), 200],           // its twin, made when a plate was saved
        311 => ['Bearbeitung', 301],
        400 => ['Listen', 100],
        401 => [$two('Waldbrände 2026'), 400],
        500 => ['Eins', 100],
    ], [
        900 => [$two($head . '01 Hauptmotiv.jpg'), 300],
        901 => [$two($head . '00 Tafel.jpg'), 300],                                  // a page from before, no row
        910 => [$two($head . '01 Hauptmotiv.jpg.messwerte.json'), 310],              // the older sidecar
        911 => [$two($head . '01 Hauptmotiv.jpg.flicken-abcdef12.png'), 310],
        902 => [$one($head . '00 Tafel.jpg'), 301],                                  // the page its row points at
        903 => [$one($head . '02 Sporen.jpg'), 301],
        912 => [$one($head . '01 Hauptmotiv.jpg.messwerte.json'), 311],              // the newer sidecar
        913 => [$one($head . '02 Sporen.jpg.messwerte.json'), 311],
        920 => ['Brand 01.jpg', 401],
        930 => [$two('Größe 00 Tafel.jpg'), 500],                                    // twice in ONE folder: this one has the row
        931 => [$one('Größe 00 Tafel.jpg'), 500],
        932 => [$two('Hütte.jpg'), 500],                                             // two pictures of one name: neither goes
        933 => [$one('Hütte.jpg'), 500],
    ]);
    FakePcloud::$modified = [910 => 10, 912 => 20];
};
$referenced = ['900' => true, '902' => true, '903' => true, '930' => true, '932' => true];
$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];

$pdo->exec("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
            VALUES ('pruef-schreibweise@pruefung.local', '', 'PRUEF', 'Schreibweise', 'user', NOW())");
$user = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, found_at, country, region, municipality, find_number, created_at, updated_at)
               VALUES (?, 47.48, 11.27, "Boletus edulis", "2026-08-29 10:12:00", "DE", "BAY", "Krün", "wk-1", NOW(), NOW())')->execute([$user]);
$point = (int) $pdo->lastInsertId();
$row = static function (int $fileid, int $folder, string $name) use ($pdo, $user, $point): int {
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, folderid, name, original_name, path, size, sort_order, uploaded_at)
                   VALUES (?, ?, "pcloud", ?, ?, ?, ?, "Arten", 1, 0, NOW())')
        ->execute([$user, $point, (string) $fileid, (string) $folder, $name, $name]);
    return (int) $pdo->lastInsertId();
};
$first  = $row(900, 300, $one($head . '01 Hauptmotiv.jpg'));
$second = $row(903, 301, $one($head . '02 Sporen.jpg'));
$hut    = $row(932, 500, $one('Hütte.jpg'));
$folderOf = static function (int $id) use ($pdo): array {
    $q = $pdo->prepare('SELECT folderid, name FROM map_cloud_files WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch();
};

stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    echo "\nA folder is found however it is spelt\n";
    $tree();
    $found = pcloud_folder_ensure('fake.pcloud', 'fake', 400, $one('Waldbrände 2026'));
    check($found['folderid'] === 401 && !$found['created'] && count(FakePcloud::$folders) === 9,
        'asked for in our spelling, the folder spelt the other way is the folder — no twin is made');
    $new = pcloud_folder_ensure('fake.pcloud', 'fake', 400, $one('Brände 2027'));
    check($new['created'] && FakePcloud::$folders[$new['folderid']][0] === $one('Brände 2027'), 'a folder that is not there is made, in our spelling');

    echo "\nThe plan, and that it touches nothing\n";
    $tree();
    $calls = count(FakePcloud::$calls);
    $dry = cloud_spelling_account($pdo, $user, $fake, microtime(true) + 30, true);
    check(count(FakePcloud::$calls) === $calls + 1 && $dry['done'] === 0 && FakePcloud::name(900) === $two($head . '01 Hauptmotiv.jpg'),
        'the dry run lists the tree once and changes nothing');
    $does = array_count_values(array_column(cloud_spelling_plan(cloud_spelling_tree($fake), $referenced), 'do'));
    check(($does['delete_folder'] ?? 0) === 2 && ($does['rows_folder'] ?? 0) === 2 && ($does['rename_folder'] ?? 0) === 1,
        'two folders are laid into their twins (the find\'s and its „Bearbeitung"), one is merely renamed');
    check(($does['delete_file'] ?? 0) === 3, 'three files go: the older sidecar and the two plate pages no row points at');

    echo "\nThe run\n";
    // The plate rows of this test live only in $referenced — the run reads the database, so it is handed the plan's steps.
    $steps = cloud_spelling_plan(cloud_spelling_tree($fake), $referenced);
    foreach ($steps as $step) {
        cloud_spelling_apply($pdo, $user, $fake, $step);
    }
    check(FakePcloud::$replaced === [], 'no file was laid over another on the way');
    check(!isset(FakePcloud::$folders[300]) && !isset(FakePcloud::$folders[310]), 'the old folder and its „Bearbeitung" are gone');
    check(FakePcloud::names(301) === [$one($head . '00 Tafel.jpg'), $one($head . '01 Hauptmotiv.jpg'), $one($head . '02 Sporen.jpg')]
          && isset(FakePcloud::$files[902]) && !isset(FakePcloud::$files[901]),
        'the find\'s folder holds its pictures in one spelling, and of the two plate pages the row\'s own');
    check(FakePcloud::names(311) === [$one($head . '01 Hauptmotiv.jpg.flicken-abcdef12.png'), $one($head . '01 Hauptmotiv.jpg.messwerte.json'),
            $one($head . '02 Sporen.jpg.messwerte.json')] && isset(FakePcloud::$files[912]) && !isset(FakePcloud::$files[910]),
        'in „Bearbeitung" the newer sidecar stayed, the patch came along under the picture\'s name');
    check(FakePcloud::$folders[401][0] === $one('Waldbrände 2026') && FakePcloud::names(401) === ['Brand 01.jpg'],
        'a folder that merely had the other spelling is renamed where it lies');
    check(FakePcloud::names(500) === [$one('Größe 00 Tafel.jpg'), $one('Hütte (2).jpg'), $one('Hütte.jpg')]
          && isset(FakePcloud::$files[930]) && !isset(FakePcloud::$files[931]) && FakePcloud::name(932) === $one('Hütte.jpg'),
        'twice in one folder: the plate page of the row stays; of two pictures neither goes — the one without a row becomes „ (2)"');
    check($folderOf($first)['folderid'] === '301' && $folderOf($second)['folderid'] === '301' && $folderOf($hut)['folderid'] === '500',
        'the rows that named the emptied folder name the other');
    check(cloud_spelling_plan(cloud_spelling_tree($fake), $referenced) === [], 'afterwards there is nothing left to plan');

    echo "\nA run that is cut short\n";
    $tree();
    $r = cloud_spelling_account($pdo, $user, $fake, microtime(true) - 1, false);
    check($r['done'] === 0 && $r['open'] === count($r['steps']) && $r['open'] > 0, 'past its deadline it does nothing and says how much is open');
    $r = cloud_spelling_account($pdo, $user, $fake, microtime(true) + 30, false);
    check($r['failed'] === [] && $r['open'] === 0 && FakePcloud::$replaced === []
          && cloud_spelling_account($pdo, $user, $fake, microtime(true) + 30, true)['steps'] === [],
        'the next run lists again and finishes — the progress is in the cloud, no cursor is kept');
} finally {
    stream_wrapper_restore('https');
    $pdo->prepare('DELETE FROM map_cloud_files WHERE point_id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_users WHERE id = ?')->execute([$user]);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
