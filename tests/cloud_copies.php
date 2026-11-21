<?php
/**
 * Tests the copy worker's state machine (includes/cloud_copies_content.php).
 *   php tests/cloud_copies.php
 *
 * WHAT IS TESTED IS THE BOOKKEEPING, NOT pCLOUD: who gets a row, how a
 * failure counts, when a copy is given up, what retiring does. The
 * transfer is injected as a fake — the machine has no cloud, and the
 * machine must still be able to prove that the loop is right. The real
 * transfer is one function, and it is measured against pCloud by hand.
 *
 * Needs migration 0098 and the demodata fixture (anna, ben). Everything
 * created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud_copies_content.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}

if (!cloud_copies_ready($pdo)) {
    echo "Migration 0098 is not applied — nothing to test.\n";
    exit(1);
}

$id = static fn(string $email): int => (int) $pdo->query(
    'SELECT id FROM map_users WHERE email = ' . $pdo->quote($email))->fetchColumn();
$anna = $id('anna@demo.local');
$ben = $id('ben@demo.local');
$carla = $id('carla@demo.local');
if (!$anna || !$ben || !$carla) {
    echo "The demodata fixture is missing — run php admin/demodata.php --new first.\n";
    exit(1);
}

// A point of anna's, a recording of hers on it, ben as co-owner WITH a
// cloud account row, carla as co-owner WITHOUT one.
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, created_at, updated_at) VALUES (?, 51.3, 9.9, "Kopierprobe", NOW(), NOW())')
    ->execute([$anna]);
$pointId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, size, uploaded_at)
               VALUES (?, ?, "pcloud", 424242, "sporen.jpg", 1234, NOW())')->execute([$anna, $pointId]);
$fileId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_point_owners (point_id, user_id) VALUES (?, ?), (?, ?)')
    ->execute([$pointId, $ben, $pointId, $carla]);
$hadAccount = static fn(int $u): bool => (bool) $pdo->query(
    "SELECT 1 FROM map_cloud_accounts WHERE user_id = $u AND provider = 'pcloud'")->fetchColumn();
$madeAccount = [];
foreach ([$anna, $ben] as $u) {
    if (!$hadAccount($u)) {
        $pdo->prepare('INSERT INTO map_cloud_accounts (user_id, provider, account, folder, secret, created_at, updated_at)
                       VALUES (?, "pcloud", "test", "", "", NOW(), NOW())')->execute([$u]);
        $madeAccount[] = $u;
    }
}
$carlaHad = $hadAccount($carla);
if ($carlaHad) {
    // carla must stand for "no cloud" — park her row for the duration.
    $pdo->prepare('UPDATE map_cloud_accounts SET provider = "pcloud-parked" WHERE user_id = ? AND provider = "pcloud"')->execute([$carla]);
}

try {
    echo "Recipients:\n";
    $r = cloud_copies_recipients($pdo, $pointId);
    check(in_array($anna, $r, true) && in_array($ben, $r, true), 'holder and co-owner with a cloud are recipients');
    check(!in_array($carla, $r, true), 'a co-owner without a cloud is not — no cloud, no copy');

    echo "Enqueueing:\n";
    check(cloud_copies_enqueue_file($pdo, $fileId) === 1, 'a new recording queues one row: ben, not anna (the origin), not carla');
    check(cloud_copies_enqueue_file($pdo, $fileId) === 0, 'queueing again adds nothing');
    check(cloud_copies_enqueue_point($pdo, $pointId, $carla) === 0, 'a point backfill for a user without cloud queues nothing');
    check(cloud_copies_enqueue_point($pdo, $pointId, $ben) === 0, 'a point backfill for ben finds his row already there');

    echo "The loop:\n";
    $calls = 0;
    $failing = static function () use (&$calls): array {
        $calls++;
        throw new RuntimeException('Wolke zu');
    };
    $r = cloud_copies_run($pdo, microtime(true) + 5, $failing);
    check($r['failed'] === 1 && $r['copied'] === 0, 'a failing transfer counts as failed, not copied');
    $row = $pdo->query("SELECT * FROM map_cloud_copies WHERE origin_id = $fileId")->fetch();
    check($row['status'] === 'waiting' && (int) $row['attempts'] === 1 && $row['error'] === 'Wolke zu', 'the row stays waiting with attempts 1 and the reason');
    $r = cloud_copies_run($pdo, microtime(true) + 5, $failing);
    check($calls === 1 && $r['failed'] === 0, 'right after a failure the row is not due again — the back-off holds');

    // Age the attempt so the row is due, then let it succeed.
    $pdo->exec("UPDATE map_cloud_copies SET tried_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE origin_id = $fileId");
    $seen = null;
    $working = static function (PDO $pdo, array $copy, array $origin, array $point) use (&$seen): array {
        $seen = compact('copy', 'origin', 'point');
        return ['fileid' => 777, 'folderid' => 88, 'path' => 'Pilze/S/Sporen', 'size' => 1234];
    };
    $r = cloud_copies_run($pdo, microtime(true) + 5, $working);
    check($r['copied'] === 1, 'a due row is copied');
    check($seen !== null && (int) $seen['copy']['user_id'] === $ben && (int) $seen['origin']['fileid'] === 424242
        && (int) $seen['point']['id'] === $pointId, 'the transfer receives copy, origin row and point row');
    $row = $pdo->query("SELECT * FROM map_cloud_copies WHERE origin_id = $fileId")->fetch();
    check($row['status'] === 'copied' && (int) $row['fileid'] === 777 && $row['path'] === 'Pilze/S/Sporen' && $row['error'] === null,
        'the row records fileid, path and clears the error');

    echo "Giving up:\n";
    $pdo->exec("UPDATE map_cloud_copies SET status = 'waiting', attempts = " . (CLOUD_COPIES_MAX_ATTEMPTS - 1) . ", tried_at = NULL WHERE origin_id = $fileId");
    $r = cloud_copies_run($pdo, microtime(true) + 5, $failing);
    check($r['given_up'] === 1 && $r['failed'] === 0, 'the last allowed failure gives the row up');
    check($pdo->query("SELECT status FROM map_cloud_copies WHERE origin_id = $fileId")->fetchColumn() === 'given_up', 'and it says so');
    $r = cloud_copies_run($pdo, microtime(true) + 5, $failing);
    check($r['failed'] === 0 && $r['given_up'] === 0, 'a given-up row is never picked up again on its own');

    echo "Retiring:\n";
    check(cloud_copies_retire($pdo, 'cloud', $fileId) === 1, 'retiring marks the copied row for deletion');
    $deleted = 0;
    $r = cloud_copies_run($pdo, microtime(true) + 5, null, static function () use (&$deleted): void { $deleted++; });
    check($r['removed'] === 1 && $deleted === 1, 'the loop deletes the copy and removes the row');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_cloud_copies WHERE origin_id = $fileId")->fetchColumn() === 0, 'nothing of it remains');

    echo "A voucher photo as origin:\n";
    $pdo->prepare('INSERT INTO map_photos (user_id, point_id, filename, original_name, width, height, bytes, created_at)
                   VALUES (?, ?, "probe.jpg", "probe.jpg", 800, 600, 4321, NOW())')->execute([$anna, $pointId]);
    $photoId = (int) $pdo->lastInsertId();
    check(cloud_copies_enqueue_photo($pdo, $photoId) === 1, 'a new voucher photo queues one row for ben');
    $seen = null;
    $r = cloud_copies_run($pdo, microtime(true) + 5, $working);
    check($r['copied'] === 1 && $seen !== null && $seen['copy']['origin_kind'] === 'photo'
        && $seen['origin']['filename'] === 'probe.jpg', 'the loop reads the photo row and hands it to the transfer');
    $pdo->exec("DELETE FROM map_cloud_copies WHERE origin_kind = 'photo' AND origin_id = $photoId");
    $pdo->exec("DELETE FROM map_photos WHERE id = $photoId");

    echo "The backfill (step 5):\n";
    // ben loses his copy row, as if he had had no cloud when it arrived —
    // connecting one now must find anna's recording on his find again.
    $pdo->exec("DELETE FROM map_cloud_copies WHERE origin_kind = 'cloud' AND origin_id = $fileId");
    check(cloud_copies_backfill_user($pdo, $ben) === 1, 'connecting a cloud queues what ben missed on his own find');
    check(cloud_copies_backfill_user($pdo, $ben) === 0, 'and nothing twice');
    check(cloud_copies_backfill_user($pdo, $carla) === 0, 'a co-owner without a cloud still gets nothing');
    check(cloud_copies_backfill_user($pdo, $anna) === 0, 'the photographer owes herself nothing');
    $pdo->exec("DELETE FROM map_cloud_copies WHERE origin_kind = 'cloud' AND origin_id = $fileId");

    echo "An origin that vanished without retiring:\n";
    $pdo->prepare('INSERT INTO map_cloud_copies (origin_kind, origin_id, user_id) VALUES ("photo", 999999999, ?)')->execute([$ben]);
    $r = cloud_copies_run($pdo, microtime(true) + 5, $working);
    check($r['copied'] === 0 && $r['failed'] === 0
        && (int) $pdo->query('SELECT COUNT(*) FROM map_cloud_copies WHERE origin_id = 999999999')->fetchColumn() === 0,
        'is dropped quietly — nothing to copy, nothing to count');
} finally {
    $pdo->exec("DELETE FROM map_cloud_copies WHERE origin_id = $fileId");
    $pdo->exec("DELETE FROM map_cloud_files WHERE id = $fileId");
    $pdo->exec("DELETE FROM map_points WHERE id = $pointId");
    foreach ($madeAccount as $u) {
        $pdo->exec("DELETE FROM map_cloud_accounts WHERE user_id = $u AND provider = 'pcloud' AND account = 'test'");
    }
    if ($carlaHad) {
        $pdo->prepare('UPDATE map_cloud_accounts SET provider = "pcloud" WHERE user_id = ? AND provider = "pcloud-parked"')->execute([$carla]);
    }
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed FAILED.\n";
exit($failed === 0 ? 0 : 1);
