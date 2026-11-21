<?php
/**
 * Tests a find's other files in the cloud (includes/point_files.php).
 *
 *   php tests/point_files.php
 *
 * Against pCloud played in memory (tests/fake_pcloud.php): the find's
 * „Dateien" folder is made inside the find's own folder, a dropped folder
 * inside it; when the find changes folders the whole „Dateien" folder moves
 * along — directly and through the names worker, for a find with no picture
 * at all; deleting takes the file from the cloud and an emptied folder
 * with it; the way out of pCloud is the browser's to ask. Needs migrations
 * 0151 and 0153; the test find and its rows are removed at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/point_files.php';
require_once __DIR__ . '/../includes/cloud_names.php';
require_once __DIR__ . '/fake_pcloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nWhat a file is, where it goes\n";
check(point_file_kind('IMG_0001.CR3') === 'other' && point_file_kind('Habitus.JPG') === 'image'
      && point_file_kind('Notiz.txt') === 'text' && point_file_kind('Schlüssel.PDF') === 'pdf',
    'by the ending: a raw file is a file, a JPEG a picture, a note a text, a PDF a PDF');
check(point_files_dir_clean('Stack 1/../RAW//Teil:2') === 'Stack 1/RAW/Teil 2' && point_files_dir_clean('') === '',
    'a dropped folder\'s path is cleaned: no way up, no empty step, no forbidden sign');

if (!point_files_ready($pdo) || !cloud_names_ready($pdo)) {
    echo "\n  (migrations 0151 and 0153 not applied — the cloud side is not tested)\n";
    echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
    exit($failed === 0 ? 0 : 1);
}

echo "\nIn the cloud, played in memory\n";
$pdo->exec("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
            VALUES ('pruef-dateien@pruefung.local', '', 'PRUEF', 'Dateien', 'user', NOW())");
$user = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, found_at, country, region, municipality, find_number, created_at, updated_at)
               VALUES (?, 47.48, 11.27, "Boletus edulis", "2026-08-29 10:12:00", "DE", "BAY", "Krün", "wk-D1", NOW(), NOW())')
    ->execute([$user]);
$pointId = (int) $pdo->lastInsertId();
$point = static function () use ($pdo, $pointId): array {
    $q = $pdo->prepare('SELECT * FROM map_points WHERE id = ?');
    $q->execute([$pointId]);
    return $q->fetch();
};
$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];
$open = static fn(int $u, string $p): array => $fake;
FakePcloud::reset([100 => ['', null]], []);
$parentName = static fn(int $folder): string => FakePcloud::$folders[FakePcloud::$folders[$folder][1] ?? 0][0] ?? '';
$pathOf = static function (int $folder): string {
    $parts = [];
    for ($f = $folder; $f !== null && $f !== 100 && isset(FakePcloud::$folders[$f]); $f = FakePcloud::$folders[$f][1]) {
        array_unshift($parts, FakePcloud::$folders[$f][0]);
    }
    return implode('/', $parts);
};

stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    $home = point_files_home($pdo, $fake, $user, $point());
    $homeId = (int) $home['folderid'];
    check((FakePcloud::$folders[$homeId][0] ?? '') === 'Dateien' && $parentName($homeId) === '2026-08-29 Krün wk-D1',
        'a folder „Dateien" inside the find\'s own folder „2026-08-29 Krün wk-D1"');
    check(str_ends_with($home['path'], 'B. edulis/2026-08-29 Krün wk-D1/Dateien'), 'its path: ' . $home['path']);

    // Two files as the browser would have sent them: one at the top, one in
    // a dropped folder „Stack 1".
    $stack = (int) storage_path_ensure($fake, $homeId, ['Stack 1']);
    FakePcloud::$files[700] = ['Notiz.txt', $homeId];
    FakePcloud::$bytes[700] = "Hut braun\nPoren weiß";
    FakePcloud::$files[701] = ['IMG_0001.CR3', $stack];
    FakePcloud::$bytes[701] = 'RAW';
    $insert = $pdo->prepare('INSERT INTO map_point_files (user_id, point_id, provider, fileid, folderid, home_folderid, home_path, dir, name, size, uploaded_at)
                             VALUES (?, ?, "pcloud", ?, ?, ?, ?, ?, ?, ?, NOW())');
    $insert->execute([$user, $pointId, '700', (string) $homeId, (string) $homeId, $home['path'], '', 'Notiz.txt', 20]);
    $note = (int) $pdo->lastInsertId();
    $insert->execute([$user, $pointId, '701', (string) $stack, (string) $homeId, $home['path'], 'Stack 1', 'IMG_0001.CR3', 3]);
    $raw = (int) $pdo->lastInsertId();
    check(point_files_home($pdo, $fake, $user, $point())['folderid'] === (string) $homeId,
        'the next file goes into the same folder');
    $list = point_files_list($pdo, $user, $pointId);
    check(count($list) === 2 && $list[0]['name'] === 'Notiz.txt' && $list[1]['dir'] === 'Stack 1'
          && $list[1]['kind'] === 'other', 'the list: the top first, then the dropped folder, each with its kind');

    // Another species under the microscope: the find changes folders.
    $pdo->prepare('UPDATE map_points SET title = "Boletus reticulatus" WHERE id = ?')->execute([$pointId]);
    $r = point_files_follow($pdo, $point(), $open);
    check($r['moved'] === 1 && $r['failed'] === 0 && str_ends_with($pathOf($homeId), 'B. reticulatus/2026-08-29 Krün wk-D1/Dateien'),
        'the whole „Dateien" folder moved into the new find folder: ' . $pathOf($homeId));
    check(FakePcloud::$folders[$stack][1] === $homeId && FakePcloud::$files[701][1] === $stack,
        'the dropped folder and its raw file came along inside it');
    $left = array_filter(FakePcloud::$folders, static fn(array $f): bool => $f[0] === 'B. edulis');
    check($left === [], 'the old find folder, left empty, went — and „B. edulis" above it too');
    $q = $pdo->prepare('SELECT DISTINCT home_path FROM map_point_files WHERE point_id = ?');
    $q->execute([$pointId]);
    check($q->fetchAll(PDO::FETCH_COLUMN) === [$pathOf($homeId)], 'every row knows the new place');
    $calls = count(FakePcloud::$calls);
    $r = point_files_follow($pdo, $point(), $open);
    check($r['moved'] === 0 && count(FakePcloud::$calls) === $calls, 'asked again, nothing moves — and the cloud is not asked');

    // Back to „edulis" through the names worker — a find with no picture at all.
    $pdo->prepare('UPDATE map_points SET title = "Boletus edulis" WHERE id = ?')->execute([$pointId]);
    cloud_names_point($pdo, $pointId, microtime(true) + 30, $open);
    check(str_ends_with($pathOf($homeId), 'B. edulis/2026-08-29 Krün wk-D1/Dateien'),
        'the names worker takes the files along, also for a find without pictures');

    check(point_file_way($fake, ['fileid' => '700']) === ['kind' => 'pcloud', 'url' => 'https://fake.pcloud/getfilelink',
          'token' => 'fake', 'fileid' => '700'], 'out of pCloud the browser asks itself — its link is made out to the asker');
    check(point_file_text($fake, ['fileid' => '700']) === "Hut braun\nPoren weiß", 'a note is read for the preview');

    $gone = point_files_delete($pdo, $fake, $user, $pointId, [$raw, 99999]);
    check($gone === 1 && !isset(FakePcloud::$files[701]) && count(point_files_list($pdo, $user, $pointId)) === 1,
        'deleted: from the cloud and from the list — an id not of this find is ignored');
    check(!isset(FakePcloud::$folders[$stack]) && isset(FakePcloud::$folders[$homeId]),
        'the emptied „Stack 1" went with it, „Dateien" stays while a note lies in it');
} finally {
    stream_wrapper_restore('https');
    $pdo->prepare('DELETE FROM map_cloud_names_due WHERE point_id = ?')->execute([$pointId]);
    $pdo->prepare('DELETE FROM map_point_files WHERE point_id = ?')->execute([$pointId]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$pointId]);
    $pdo->prepare('DELETE FROM map_users WHERE id = ?')->execute([$user]);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
