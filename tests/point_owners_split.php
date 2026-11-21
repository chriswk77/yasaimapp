<?php
/**
 * Tests the split of a shared find when a friendship ends
 * (point_owners_split in includes/point_owners.php; 08.10.2026).
 *
 *   php tests/point_owners_split.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   EACH KEEPS HIS WORK. The co-owner gets a find of his own with the
 *   texts, the species and his filing; the holder keeps his. Neither can
 *   touch the other's afterwards.
 *
 *   PICTURES GO WITH THEIR PHOTOGRAPHER, and each side keeps the other's
 *   where its own cloud holds a finished copy — as a picture of its own,
 *   with the origin's title and measures. No copy, no picture: the server
 *   never holds one.
 *
 *   A THIRD IN THE CIRCLE keeps the leaving picture through his copy, and
 *   stays ONE record: his copy now copies the find's own row.
 *
 * No cloud is called — the copies are rows as the worker leaves them.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/point_owners.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

if (!point_owners_ready($pdo) || !cloud_copies_ready($pdo)) {
    echo "Migration 0098 missing — nothing to check.\n";
    exit(1);
}

$accounts = [];
foreach (['pruef-anna', 'pruef-ben', 'pruef-dora'] as $who) {
    $pdo->prepare(
        "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
         VALUES (?, '', 'PRUEF', ?, 'user', NOW())"
    )->execute([$who . '@pruefung.local', ucfirst(substr($who, 6))]);
    $accounts[$who] = (int) $pdo->lastInsertId();
}
[$anna, $ben, $dora] = array_values($accounts);

$cloudRow = static function (int $user, int $point, string $fileid, string $title) use ($pdo): int {
    $pdo->prepare("INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, path, title, measures, uploaded_at)
                   VALUES (?, ?, 'pcloud', ?, ?, '', ?, ?, NOW())")
        ->execute([$user, $point, $fileid, $fileid . '.jpg', $title, '[{"type":"line","points":[[1,1],[5,5]]}]']);
    return (int) $pdo->lastInsertId();
};
$copyRow = static function (string $kind, int $origin, int $user, string $fileid, string $status = 'copied') use ($pdo): void {
    $pdo->prepare("INSERT INTO map_cloud_copies (origin_kind, origin_id, user_id, provider, fileid, name, path, status)
                   VALUES (?, ?, ?, 'pcloud', ?, ?, '', ?)")
        ->execute([$kind, $origin, $user, $fileid, $fileid . '.jpg', $status]);
};

try {
    foreach ([[$anna, $ben], [$anna, $dora]] as [$x, $y]) {
        friendship_request($pdo, $x, $y);
        $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $x AND to_user_id = $y")->fetchColumn();
        friendship_accept($pdo, $y, $fId);
    }
    $annaList = list_create($pdo, $anna, ['name' => 'PRUEF Annas', 'color' => '#336699', 'symbol' => 'circle']);
    $benList  = list_create($pdo, $ben, ['name' => 'PRUEF Bens', 'color' => '#336699', 'symbol' => 'circle']);
    $find = point_create($pdo, $anna, ['lat' => 50.1, 'lon' => 8.1, 'title' => 'Boletus edulis', 'note' => 'unter Buchen', 'lists' => [$annaList]]);
    $pdo->prepare('UPDATE map_points SET find_number = ? WHERE id = ?')->execute(['AN-2026-0001', $find]);

    // Ben and Dora attached pictures: both co-own. Ben files it in his list.
    $pdo->prepare('INSERT INTO map_point_owners (point_id, user_id, list_id) VALUES (?, ?, ?)')->execute([$find, $ben, $benList]);
    $pdo->prepare('INSERT INTO map_point_owners (point_id, user_id) VALUES (?, ?)')->execute([$find, $dora]);
    $annasPic = $cloudRow($anna, $find, 'anna-1', 'Annas Hut');
    $bensPic  = $cloudRow($ben, $find, 'ben-1', 'Bens Sporen');
    $dorasPic = $cloudRow($dora, $find, 'dora-1', 'Doras Lamellen');
    // The copies as the worker leaves them: anna and dora hold ben's, ben holds anna's;
    // ben's copy of dora's picture never finished.
    $copyRow('cloud', $bensPic, $anna, 'anna-copy-of-ben');
    $copyRow('cloud', $bensPic, $dora, 'dora-copy-of-ben');
    $copyRow('cloud', $annasPic, $ben, 'ben-copy-of-anna');
    $copyRow('cloud', $dorasPic, $ben, 'ben-copy-of-dora', 'waiting');

    echo "The friendship ends:\n";
    $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $ben")->fetchColumn();
    friendship_remove($pdo, $ben, $fId);

    check(!point_owner_is($pdo, $find, $ben), 'ben is no co-owner of anna’s find any more');
    $copy = (int) $pdo->query("SELECT id FROM map_points WHERE user_id = $ben AND title = 'Boletus edulis'")->fetchColumn();
    check($copy > 0, 'ben has a find of his own');
    $row = $pdo->query("SELECT note, find_number, lat FROM map_points WHERE id = $copy")->fetch();
    check($row['note'] === 'unter Buchen' && (float) $row['lat'] === 50.1, 'with the texts and the place');
    check($row['find_number'] === null, 'but not anna’s find number — that is her series');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_point_lists WHERE point_id = $copy AND list_id = $benList")->fetchColumn() === 1,
        'filed where ben had filed the shared one');

    echo "The pictures:\n";
    check((int) $pdo->query("SELECT point_id FROM map_cloud_files WHERE id = $bensPic")->fetchColumn() === $copy,
        'ben’s picture went with him');
    $bensOnCopy = $pdo->query("SELECT fileid, title, measures FROM map_cloud_files WHERE user_id = $ben AND point_id = $copy AND id <> $bensPic")
        ->fetchAll();
    check(array_column($bensOnCopy, 'fileid') === ['ben-copy-of-anna'], 'he keeps anna’s picture through his copy — not dora’s, which never arrived');
    check(($bensOnCopy[0]['title'] ?? '') === 'Annas Hut' && str_contains((string) ($bensOnCopy[0]['measures'] ?? ''), 'line'),
        'with the origin’s title and measures');
    if (column_exists($pdo, 'map_cloud_files', 'taken_by')) {
        $takenBy = (string) $pdo->query("SELECT taken_by FROM map_cloud_files WHERE fileid = 'ben-copy-of-anna'")->fetchColumn();
        check(str_contains($takenBy, 'Anna'), 'and anna’s name stays on it as who took it (0161)');
    }

    $annasNow = $pdo->query("SELECT id, fileid, title FROM map_cloud_files WHERE user_id = $anna AND point_id = $find")->fetchAll();
    check(in_array('anna-copy-of-ben', array_column($annasNow, 'fileid'), true), 'anna keeps ben’s picture through her copy');
    $adopted = 0;
    foreach ($annasNow as $r) {
        if ($r['fileid'] === 'anna-copy-of-ben') { $adopted = (int) $r['id']; }
    }
    $dorasCopy = $pdo->query("SELECT origin_kind, origin_id FROM map_cloud_copies WHERE user_id = $dora AND fileid = 'dora-copy-of-ben'")->fetch();
    check($dorasCopy !== false && $dorasCopy['origin_kind'] === 'cloud' && (int) $dorasCopy['origin_id'] === $adopted,
        'dora’s copy now copies the find’s own row — still one record per picture');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_cloud_copies WHERE user_id = $ben")->fetchColumn() === 0,
        'ben holds no copy rows any more — what he keeps is his');
    check((int) $pdo->query("SELECT point_id FROM map_cloud_files WHERE id = $dorasPic")->fetchColumn() === $find,
        'dora’s picture stays on anna’s find');
    check(point_owner_is($pdo, $find, $dora), 'and dora, still anna’s friend, is still in the circle');
} finally {
    $idList = implode(',', $accounts);
    $pdo->exec("DELETE FROM map_cloud_copies WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_friends WHERE from_user_id IN ($idList) OR to_user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_users WHERE id IN ($idList)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
