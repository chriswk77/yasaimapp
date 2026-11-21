<?php
/**
 * Tests co-ownership (includes/point_owners.php): who may attach a picture
 * to a foreign find, what attaching does to the owner table, and what it
 * sets going in the copy queue.
 *   php tests/point_owners.php
 *
 * Needs migrations 0074 and 0098 and the demodata fixture. Everything
 * created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/point_owners.php';
require_once __DIR__ . '/../includes/photos.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}

if (!point_owners_ready($pdo) || !cloud_copies_ready($pdo)) {
    echo "Migration 0098 is not applied — nothing to test.\n";
    exit(1);
}
$id = static fn(string $email): int => (int) $pdo->query(
    'SELECT id FROM map_users WHERE email = ' . $pdo->quote($email))->fetchColumn();
$anna = $id('anna@demo.local'); $ben = $id('ben@demo.local'); $carla = $id('carla@demo.local');
if (!$anna || !$ben || !$carla) {
    echo "The demodata fixture is missing — run php admin/demodata.php --new first.\n";
    exit(1);
}

// ben's find on an excursion anna leads; carla is nobody to it.
$pdo->prepare('INSERT INTO map_excursions (leader_user_id, name, starts_at, ends_at, join_token, photos_allowed, created_at)
               VALUES (?, "Besitzprobe", NOW() - INTERVAL 1 HOUR, NOW() + INTERVAL 5 HOUR, ?, 1, NOW())')
    ->execute([$anna, bin2hex(random_bytes(16))]);
$excursion = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, excursion_id, created_at, updated_at)
               VALUES (?, 51.3, 9.9, "Besitzprobe", ?, NOW(), NOW())')->execute([$ben, $excursion]);
$pointId = (int) $pdo->lastInsertId();
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
$fileIds = [];

try {
    echo "The attach scope:\n";
    check(point_attach_scope($pdo, $ben, $pointId) === $ben, 'the holder may, and gets his own id back');
    check(point_attach_scope($pdo, $anna, $pointId) === $ben, 'the excursion leader may, and gets the HOLDER id back');
    check(point_attach_scope($pdo, $carla, $pointId) === null, 'a stranger may not — same answer as a missing point');
    check(point_attach_scope($pdo, $anna, 0) === null, 'a missing point is null');

    echo "The seeing gate (14.09.2026):\n";
    check(point_view_scope($pdo, $ben, $pointId) === $ben, 'the holder sees, and gets his own id back');
    check(point_view_scope($pdo, $anna, $pointId) === $ben, 'the excursion leader sees, and gets the HOLDER id back');
    check(point_view_scope($pdo, $carla, $pointId) === null, 'a stranger does not — same answer as a missing point');
    check(point_view_scope($pdo, $anna, 0) === null, 'a missing point is null');
    // The circle's door by itself: carla is nobody to the excursion, a
    // co-owner row alone lets her look.
    point_owner_add($pdo, $pointId, $carla);
    check(point_view_scope($pdo, $carla, $pointId) === $ben, 'a co-owner sees through her row alone');
    point_owner_leave($pdo, $pointId, $carla); point_owner_forget($pdo, $pointId, $carla);
    check(point_view_scope($pdo, $carla, $pointId) === null, 'and not once she has left');
    // The plain share — the fixture's hung-in list: ben's find in anna's
    // container, no right granted. She may look, not attach; that is the
    // whole difference between the two gates, and the friend's empty
    // gallery lived in it.
    $shared = (int) $pdo->query("SELECT p.id FROM map_points p JOIN map_point_lists pl ON pl.point_id = p.id
                                   JOIN map_lists l ON l.id = pl.list_id
                                  WHERE p.user_id = $ben AND l.name = 'Bens Beobachtungen' AND p.deleted_at IS NULL LIMIT 1")->fetchColumn();
    check($shared > 0, 'the fixture has ben\'s find in the hung-in list');
    /* SINCE 22.09.2026 THE FIXTURE ALSO HANGS THIS LIST JOINTLY into ben's
       own container — and in a joint list every member may attach. The
       question here is the PLAIN share, so the joint flag is lifted for
       these checks and put back right after. */
    $jointRows = column_exists($pdo, 'map_container_lists', 'joint')
        ? array_map('intval', $pdo->query("SELECT cl.id FROM map_container_lists cl JOIN map_lists l ON l.id = cl.list_id
                                             WHERE l.name = 'Bens Beobachtungen' AND cl.joint = 1")->fetchAll(PDO::FETCH_COLUMN))
        : [];
    if ($jointRows !== []) {
        $pdo->exec('UPDATE map_container_lists SET joint = 0 WHERE id IN (' . implode(',', $jointRows) . ')');
    }
    /* AND SINCE 09.10.2026 THE FIXTURE GRANTS THE PICTURE RIGHT on this
       very list — „Listen teilen" gives anna title, note, move and
       attach_photos on ben's list (admin/demodata.php), so the look
       window shows ticks. The question here is a recipient WITHOUT it,
       so her row loses that one tick for these checks and gets it back
       right after. Before migration 0160 the rows carry no rights. */
    $recipientRows = column_exists($pdo, 'map_container_members', 'rights')
        ? $pdo->query("SELECT cm.container_id, cm.type, cm.target_id, cm.rights
                         FROM map_container_members cm
                         JOIN map_container_lists cl ON cl.container_id = cm.container_id
                         JOIN map_lists l ON l.id = cl.list_id
                        WHERE l.name = 'Bens Beobachtungen' AND cm.type = 'friend' AND cm.target_id = $anna")->fetchAll()
        : [];
    $setRights = $pdo->prepare('UPDATE map_container_members SET rights = ? WHERE container_id = ? AND type = ? AND target_id = ?');
    foreach ($recipientRows as $r) {
        $without = array_values(array_diff(rights_read($r['rights']), ['attach_photos']));
        $setRights->execute([json_encode($without), $r['container_id'], $r['type'], $r['target_id']]);
    }
    check(point_attach_scope($pdo, $anna, $shared) === null, 'a share member without the photo right may not attach');
    foreach ($recipientRows as $r) {
        $setRights->execute([$r['rights'], $r['container_id'], $r['type'], $r['target_id']]);
    }
    if ($recipientRows !== []) {
        check(point_attach_scope($pdo, $anna, $shared) === $ben, 'with the picture right she may — the holder\'s id');
    }
    if ($jointRows !== []) {
        $pdo->exec('UPDATE map_container_lists SET joint = 1 WHERE id IN (' . implode(',', $jointRows) . ')');
    }
    check(point_view_scope($pdo, $anna, $shared) === $ben, 'but sees — the holder\'s set');
    check(point_view_scope($pdo, $carla, $shared) === null, 'somebody outside the container sees nothing');
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, size, uploaded_at)
                   VALUES (?, ?, "pcloud", 333, "mikro.jpg", 100, NOW())')->execute([$ben, $shared]);
    $fileIds[] = $sharedFile = (int) $pdo->lastInsertId();
    $rows = cloud_files_rows($pdo, $anna, $shared);
    check(count($rows) === 1 && $rows[0]['id'] === $sharedFile && $rows[0]['mine'] === false
        && $rows[0]['photographer_id'] === $ben && $rows[0]['copy'] === null,
        'the share member\'s cloud list carries the holder\'s recording — not hers, no copy of it');
    check(cloud_files_rows($pdo, $ben, $shared)[0]['mine'] === true, 'the holder\'s own list says mine');

    echo "Attaching as the leader:\n";
    // ben's own recording is already there; then anna records one.
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, size, uploaded_at)
                   VALUES (?, ?, "pcloud", 111, "beleg.jpg", 100, NOW())')->execute([$ben, $pointId]);
    $fileIds[] = $berndFile = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, size, uploaded_at)
                   VALUES (?, ?, "pcloud", 222, "sporen.jpg", 100, NOW())')->execute([$anna, $pointId]);
    $fileIds[] = $annaFile = (int) $pdo->lastInsertId();
    point_photo_attached($pdo, $pointId, $anna, 'cloud', $annaFile);
    check(point_owner_is($pdo, $pointId, $anna), 'anna is a co-owner now');
    check(!point_owner_is($pdo, $pointId, $ben), 'the holder never gets an owner row');
    $rows = $pdo->query("SELECT origin_id, user_id FROM map_cloud_copies WHERE origin_id IN ($berndFile, $annaFile) ORDER BY origin_id")->fetchAll();
    check(count($rows) === 2, 'two copies are queued');
    check((int) $rows[0]['origin_id'] === $berndFile && (int) $rows[0]['user_id'] === $anna,
        'ben\'s recording goes to anna — the new co-owner gets what was already there');
    check((int) $rows[1]['origin_id'] === $annaFile && (int) $rows[1]['user_id'] === $ben,
        'anna\'s recording goes to ben — the holder gets the new picture');
    check(point_attach_scope($pdo, $anna, $pointId) === $ben, 'a co-owner keeps the door open by that alone');

    echo "Attaching again:\n";
    point_photo_attached($pdo, $pointId, $anna, 'cloud', $annaFile);
    check((int) $pdo->query("SELECT COUNT(*) FROM map_cloud_copies WHERE origin_id IN ($berndFile, $annaFile)")->fetchColumn() === 2,
        'a second attach queues nothing twice');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_point_owners WHERE point_id = $pointId")->fetchColumn() === 1,
        'and adds no second owner row');

    echo "Leaving and coming back:\n";
    $pdo->exec("UPDATE map_point_owners SET deleted_at = NOW() WHERE point_id = $pointId AND user_id = $anna");
    check(!point_owner_is($pdo, $pointId, $anna), 'in her trash she is no co-owner');
    check(point_attach_scope($pdo, $anna, $pointId) === $ben, 'but the leader door still opens');
    check(point_owner_add($pdo, $pointId, $anna) === true, 'attaching again takes her out of the trash');
    check(point_owner_is($pdo, $pointId, $anna), 'and she is back');

    echo "The co-owner's view (step 3):\n";
    $mine = co_owned_points($pdo, $anna);
    check(isset($mine[$pointId]) && $mine[$pointId]['co_owned'] === true && $mine[$pointId]['owner_id'] === $ben,
        'anna gets the find as a co-owned foreign point with the holder named');
    check($mine[$pointId]['lists'] === [], 'unfiled at first — no list of hers');
    $pdo->prepare('INSERT INTO map_lists (user_id, name, created_at, updated_at) VALUES (?, "Annas Ablage", NOW(), NOW())')->execute([$anna]);
    $annaList = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO map_lists (user_id, name, created_at, updated_at) VALUES (?, "Bens Liste", NOW(), NOW())')->execute([$ben]);
    $berndList = (int) $pdo->lastInsertId();
    check(point_owner_list_set($pdo, $pointId, $anna, $annaList) === true, 'she files it in one of her lists');
    check(point_owner_list_set($pdo, $pointId, $anna, $berndList) === false, 'not in one of ben\'s');
    check(co_owned_points($pdo, $anna)[$pointId]['lists'] === [$annaList], 'and the find now carries her list');
    check(point_owner_list_set($pdo, $pointId, $carla, $annaList) === false, 'a stranger cannot file it at all');

    echo "All pictures for the circle:\n";
    $pdo->prepare('INSERT INTO map_photos (user_id, point_id, filename, original_name, width, height, bytes, created_at)
                   VALUES (?, ?, "annas.jpg", "annas.jpg", 800, 600, 10, NOW())')->execute([$anna, $pointId]);
    $annaPhoto = (int) $pdo->lastInsertId();
    $forBen = photos_for_points($pdo, $ben, [$pointId])[$pointId] ?? [];
    check(count($forBen) === 1 && $forBen[0]['photographer_id'] === $anna && $forBen[0]['photographer'] !== '',
        'the holder sees the co-owner\'s photo, with her name on it');
    $forAnna = co_owned_points($pdo, $anna)[$pointId]['photos'];
    check(count($forAnna) === 1 && $forAnna[0]['id'] === $annaPhoto, 'and the co-owner sees the whole set through her point');
    $forCarla = photos_for_points($pdo, $carla, [$pointId])[$pointId] ?? [];
    check($forCarla === [], 'a stranger asking directly sees nothing');
    $rows = cloud_files_rows($pdo, $ben, $pointId);
    $foreignRows = array_values(array_filter($rows, static fn(array $r) => $r['mine'] === false));
    check(count($foreignRows) === 1 && $foreignRows[0]['photographer_id'] === $anna && $foreignRows[0]['copy']['status'] === 'waiting',
        'the holder\'s cloud list carries anna\'s recording as a reference, with the copy\'s status');

    /* THE HOLDER'S POINT MOVES WHEN THE CO-OWNER WRITES HER PICTURE
       (30.09.2026). photo_point_touched took the writer's id into its
       WHERE, and anna's writes on ben's find hit no row — ben's other
       devices never learned of her name, scale or measures. touched_at
       is read straight off the row: it carries microseconds, so two
       writes within one second still tell apart. */
    if (column_exists($pdo, 'map_points', 'touched_at')) {
        echo "The co-owner's writes reach the holder's bucket:\n";
        $touched = fn(): string => (string) $pdo->query("SELECT touched_at FROM map_points WHERE id = $pointId")->fetchColumn();
        $writes = [
            'naming'      => fn(): bool => photo_title_store($pdo, $anna, $annaPhoto, 'Hauptmotiv'),
            'calibrating' => fn(): bool => photo_scale_store($pdo, $anna, $annaPhoto,
                ['line' => [[0, 0], [100, 0]], 'um' => 1000, 'corner' => 'br']),
            'measuring'   => fn(): bool => photo_measures_store($pdo, $anna, $annaPhoto, null),
            'reordering'  => fn(): bool => photos_reorder($pdo, $pointId, [$annaPhoto]) !== [],
        ];
        foreach ($writes as $what => $write) {
            $before = $touched();
            usleep(1000);
            check($write() === true && $touched() !== $before, "$what her photo moves ben's point");
        }
    }

    echo "The photographer deletes:\n";
    cloud_copies_enqueue_photo($pdo, $annaPhoto);
    $pdo->exec("UPDATE map_cloud_copies SET status = 'copied', fileid = 5 WHERE origin_kind = 'photo' AND origin_id = $annaPhoto");
    check(photo_delete($pdo, $anna, $annaPhoto) === true, 'anna deletes her photo');
    check($pdo->query("SELECT status FROM map_cloud_copies WHERE origin_kind = 'photo' AND origin_id = $annaPhoto")->fetchColumn() === 'deleting',
        'its copy in ben\'s cloud is marked for removal — „bei allen mit gelöscht"');
    $pdo->exec("DELETE FROM map_cloud_copies WHERE origin_kind = 'photo' AND origin_id = $annaPhoto");
    $pdo->exec("DELETE FROM map_lists WHERE id IN ($annaList, $berndList)");

    echo "The holder attaching:\n";
    point_photo_attached($pdo, $pointId, $ben, 'cloud', $berndFile);
    check((int) $pdo->query("SELECT COUNT(*) FROM map_point_owners WHERE point_id = $pointId AND user_id = $ben")->fetchColumn() === 0,
        'the holder attaching to his own find stays the holder');

    echo "The door (step 4) — a co-owner steps out:\n";
    check(point_owner_leave($pdo, $pointId, $anna) === true, 'anna steps out');
    check(!point_owner_is($pdo, $pointId, $anna) && !isset(co_owned_points($pdo, $anna)[$pointId]), 'the find is gone from her stock');
    $entries = point_owner_trash_entries($pdo, $anna);
    check(count($entries) === 1 && (int) $entries[0]['id'] === $pointId && $entries[0]['holder'] !== '', 'and lies in her trash, with the holder named');
    check(point_owner_leave($pdo, $pointId, $anna) === false, 'stepping out twice is nothing');
    check(point_owner_restore($pdo, $pointId, $anna) === true && point_owner_is($pdo, $pointId, $anna), 'restore makes her a co-owner again');
    point_owner_leave($pdo, $pointId, $anna);
    $pdo->exec("UPDATE map_cloud_copies SET status = 'copied', fileid = 9 WHERE origin_id = $berndFile AND user_id = $anna");
    check(point_owner_forget($pdo, $pointId, $anna) === true, 'forgetting from the trash works');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_point_owners WHERE point_id = $pointId AND user_id = $anna")->fetchColumn() === 0, 'her row is gone');
    check($pdo->query("SELECT status FROM map_cloud_copies WHERE origin_id = $berndFile AND user_id = $anna")->fetchColumn() === 'deleting',
        'her copy of ben\'s picture is marked for removal from her cloud');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_cloud_files WHERE id = $annaFile")->fetchColumn() === 1, 'her own picture stays on the find');
    check(point_owner_forget($pdo, $pointId, $anna) === false, 'forgetting twice is nothing');

    echo "The handover — the holder steps out:\n";
    $pdo->prepare('INSERT INTO map_lists (user_id, name, created_at, updated_at) VALUES (?, "Annas Ablage 2", NOW(), NOW())')->execute([$anna]);
    $annaList2 = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO map_lists (user_id, name, created_at, updated_at) VALUES (?, "Bens Fundliste", NOW(), NOW())')->execute([$ben]);
    $berndList2 = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO map_point_lists (point_id, list_id) VALUES (?, ?)')->execute([$pointId, $berndList2]);
    point_owner_add($pdo, $pointId, $anna, $annaList2);
    $pdo->exec("UPDATE map_point_owners SET since = DATE_SUB(NOW(), INTERVAL 2 DAY) WHERE point_id = $pointId AND user_id = $anna");
    point_owner_add($pdo, $pointId, $carla);
    check(point_owner_senior($pdo, $pointId) === $anna, 'the most senior co-owner is anna');
    check((co_owners_of_points($pdo, [$pointId])[$pointId][0] ?? '') === 'Anna Musterfrau', 'and the payload names her first');
    check(point_delete($pdo, $ben, $pointId) === true, 'ben „deletes" his find');
    check((int) $pdo->query("SELECT user_id FROM map_points WHERE id = $pointId")->fetchColumn() === $anna, '— it went to anna instead of the trash');
    check($pdo->query("SELECT deleted_at FROM map_points WHERE id = $pointId")->fetchColumn() === null, 'the find is not deleted');
    check((int) $pdo->query("SELECT list_id FROM map_point_lists WHERE point_id = $pointId")->fetchColumn() === $annaList2, 'it now lies in anna\'s list');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_point_owners WHERE point_id = $pointId AND user_id = $anna")->fetchColumn() === 0, 'anna has no owner row any more — she is the holder');
    $row = $pdo->query("SELECT deleted_at, list_id FROM map_point_owners WHERE point_id = $pointId AND user_id = $ben")->fetch();
    check($row !== false && $row['deleted_at'] !== null && (int) $row['list_id'] === $berndList2, 'ben stands in his trash as a former co-owner, his old list remembered');
    check(point_owner_is($pdo, $pointId, $carla), 'carla stays a co-owner');
    check(point_owner_restore($pdo, $pointId, $ben) === true && point_owner_is($pdo, $pointId, $ben), 'ben can come back — as a co-owner');
    check(point_attach_scope($pdo, $ben, $pointId) === $anna, 'and attaches with anna as the holder now');

    echo "The bucket — a holder without co-owners:\n";
    point_owner_leave($pdo, $pointId, $carla); point_owner_forget($pdo, $pointId, $carla);
    point_owner_leave($pdo, $pointId, $ben); point_owner_forget($pdo, $pointId, $ben);
    check(point_owner_senior($pdo, $pointId) === null, 'nobody left to hand over to');
    check(point_delete($pdo, $anna, $pointId) === true && $pdo->query("SELECT deleted_at FROM map_points WHERE id = $pointId")->fetchColumn() !== null,
        'so anna\'s delete is the ordinary trash');
    check(point_delete_permanently($pdo, $anna, $pointId) === true, 'and the bucket takes it');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_cloud_files WHERE id IN ($berndFile, $annaFile)")->fetchColumn() === 0, 'the cloud rows went with it (cascade)');
    $pdo->exec("DELETE FROM map_lists WHERE id IN ($annaList2, $berndList2)");
} finally {
    if ($fileIds !== []) {
        $pdo->exec('DELETE FROM map_cloud_copies WHERE origin_kind = "cloud" AND origin_id IN (' . implode(',', $fileIds) . ')');
        $pdo->exec('DELETE FROM map_cloud_files WHERE id IN (' . implode(',', $fileIds) . ')');
    }
    $pdo->exec("DELETE FROM map_points WHERE id = $pointId");
    $pdo->exec("DELETE FROM map_excursions WHERE id = $excursion");
    foreach ($madeAccount as $u) {
        $pdo->exec("DELETE FROM map_cloud_accounts WHERE user_id = $u AND provider = 'pcloud' AND account = 'test'");
    }
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed FAILED.\n";
exit($failed === 0 ? 0 : 1);
