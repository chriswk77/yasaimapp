<?php
/**
 * Tests the move to another cloud (includes/cloud_moves.php).
 *   php tests/cloud_moves.php
 *
 * WHAT IS TESTED IS THE BOOKKEEPING, NOT THE CLOUDS: that the old cloud
 * stops counting as connected but still opens for its pictures, what the
 * switch writes down, how a picture counts as moved, how a failure counts,
 * when a move waits and when it ends, and what the end takes away. The
 * transfer, the free-room question and the undo are injected as fakes,
 * like the copy worker's — the machine has no cloud and must still prove
 * the loop. The real transfer is one function, measured against real
 * accounts by hand.
 *
 * Needs migration 0142 and the demodata fixture (anna, ben). Everything
 * created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud_moves.php';
require_once __DIR__ . '/../includes/cloud_content.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}

if (!cloud_moves_ready($pdo)) {
    echo "Migration 0142 is not applied — nothing to test.\n";
    exit(1);
}

$id = static fn(string $email): int => (int) $pdo->query(
    'SELECT id FROM map_users WHERE email = ' . $pdo->quote($email))->fetchColumn();
$anna = $id('anna@demo.local');
$ben  = $id('ben@demo.local');
if (!$anna || !$ben) {
    echo "The demodata fixture is missing — run php admin/demodata.php --new first.\n";
    exit(1);
}
if ((int) $pdo->query("SELECT COUNT(*) FROM map_cloud_accounts WHERE user_id = $anna")->fetchColumn() > 0
    || cloud_move_open($pdo, $anna) !== null) {
    echo "anna already has a cloud or a move — the test needs her without one.\n";
    exit(1);
}

$one = static fn(string $sql) => $pdo->query($sql)->fetch();

// anna: a point with a voucher photo, a recording, a calibration image and
// two copies of ben's recordings in pCloud; ben's recordings on her point.
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, created_at, updated_at) VALUES (?, 51.3, 9.9, "Umzugsprobe", NOW(), NOW())')
    ->execute([$anna]);
$pointId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_photos (user_id, point_id, filename, original_name, bytes, cloud_provider, cloud_fileid, cloud_folderid, cloud_path, created_at)
               VALUES (?, ?, "umzug.jpg", "IMG_0001.jpg", 1000, "pcloud", "p-1", "f-1", "Pilze/U", NOW())')->execute([$anna, $pointId]);
$photoId = (int) $pdo->lastInsertId();
// And one whose cloud id is EMPTY — in no cloud at all: neither counted nor carried (12.11.2026).
$pdo->prepare('INSERT INTO map_photos (user_id, point_id, filename, original_name, bytes, cloud_fileid, created_at)
               VALUES (?, ?, "leer.jpg", "IMG_0002.jpg", 9999, "", NOW())')->execute([$anna, $pointId]);
$pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, size, uploaded_at)
               VALUES (?, ?, "pcloud", "r-1", "sporen.jpg", 2000, NOW())')->execute([$anna, $pointId]);
$recId = (int) $pdo->lastInsertId();
// Since migration 0183 every preset belongs to a device — the account's
// first, made for the test if she has none (and removed again below).
require_once __DIR__ . '/../includes/photos.php';
$devicesBefore = scale_devices($pdo, $anna);
$scaleDevice = scale_devices_ready($pdo) ? scale_device_default($pdo, $anna) : null;
$pdo->prepare('INSERT INTO map_photo_scales (user_id, ' . ($scaleDevice !== null ? 'device_id, ' : '') . 'name, um_per_px, fileid, provider, created_at)
               VALUES (?, ' . ($scaleDevice !== null ? '?, ' : '') . '"Umzugsprobe 40x", 0.1, "s-1", "pcloud", NOW())')
    ->execute($scaleDevice !== null ? [$anna, $scaleDevice] : [$anna]);
$scaleId = (int) $pdo->lastInsertId();
$benRec = [];
foreach (['b-1', 'b-2'] as $f) {
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, size, uploaded_at)
                   VALUES (?, ?, "pcloud", ?, "ben.jpg", 500, NOW())')->execute([$ben, $pointId, $f]);
    $benRec[] = (int) $pdo->lastInsertId();
}
$pdo->prepare('INSERT INTO map_cloud_copies (origin_kind, origin_id, user_id, provider, fileid, size, status, created_at)
               VALUES ("cloud", ?, ?, "pcloud", "c-1", 500, "copied", NOW())')->execute([$benRec[0], $anna]);
$copyId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_cloud_copies (origin_kind, origin_id, user_id, provider, fileid, size, status, created_at)
               VALUES ("cloud", ?, ?, "pcloud", "c-2", 500, "deleting", NOW())')->execute([$benRec[1], $anna]);
$deletingId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_cloud_backups (user_id, bucket_key, fingerprint, filename, uploaded_at) VALUES (?, "2026-09", "x", "x.json", NOW())')
    ->execute([$anna]);
// And one of the find's other files, dropped in a folder (Belege → Dateien, 0153).
$fileId = null;
if (table_exists($pdo, 'map_point_files')) {
    $pdo->prepare('INSERT INTO map_point_files (user_id, point_id, provider, fileid, folderid, home_folderid, home_path, dir, name, size, mime, uploaded_at)
                   VALUES (?, ?, "pcloud", "d-1", "f-9", "f-8", "Pilze/U/Dateien", "Stack 1", "IMG_0001.CR3", 3000, NULL, NOW())')
        ->execute([$anna, $pointId]);
    $fileId = (int) $pdo->lastInsertId();
}
$withFiles = $fileId !== null && cloud_move_files_ready($pdo);
// Tolerates a key still there: where an earlier check failed, the move did not take it away,
// and a crash here would skip the remaining checks.
$account = static fn(string $provider) => $pdo->prepare(
    'INSERT INTO map_cloud_accounts (user_id, provider, account, folder, secret, created_at, updated_at)
     VALUES (?, ?, "test", "", "", NOW(), NOW()) ON DUPLICATE KEY UPDATE updated_at = NOW()')->execute([$anna, $provider]);
$account('pcloud');

try {
    echo "Before the switch:\n";
    $held = cloud_pictures_held($pdo, $anna, 'pcloud');
    check($held['photo'] === 1 && $held['cloud'] === 1 && $held['scale'] === 1 && $held['copy'] === 1,
        'held: one voucher photo, one recording, one calibration image, one copy (the deleting one does not count)');
    check($held['bytes'] === ($fileId !== null ? 6500 : 3500), 'held weighs 1000 + 2000 + 500 bytes, and the file\'s 3000');
    check(cloud_pictures_list($held, true) === ($fileId !== null
            ? '1 Belegfoto, 1 Aufnahme, 1 Eichbild, 1 Kopie von gemeinsamen Funden und 1 weitere Datei'
            : '1 Belegfoto, 1 Aufnahme, 1 Eichbild und 1 Kopie von gemeinsamen Funden'),
        'the list says it the way one says it — the find\'s other file too');
    $question = cloud_disconnect_question('pCloud', $held, true);
    check(str_contains($question, $fileId !== null
            ? 'In pCloud liegen 1 Belegfoto, 1 Aufnahme, 1 Eichbild und 1 weitere Datei von dir.'
            : 'In pCloud liegen 1 Belegfoto, 1 Aufnahme und 1 Eichbild von dir.')
        && str_contains($question, 'In andere Cloud umziehen'), 'Trennen asks with numbers, without the copies, and points to the move');
    check(str_starts_with(cloud_disconnect_question('pCloud', ['photo' => 1], false), 'In pCloud liegt ein Bild von dir.')
        && !str_contains(cloud_disconnect_question('pCloud', ['photo' => 1], false), 'umziehen'),
        'one picture is said in the singular, and without a target there is no pointer');
    check(str_starts_with(cloud_disconnect_question('pCloud', [], true), 'Die Verbindung zu pCloud wird gelöst.'),
        'nothing there: the old sentence');

    echo "The switch:\n";
    $account('gdrive');
    $moveId = cloud_move_begin($pdo, $anna, 'pcloud', 'gdrive');
    $move = cloud_move_get($pdo, $moveId);
    check($move['status'] === 'running' && $move['from_provider'] === 'pcloud', 'the move runs');
    check(array_keys(cloud_connections($pdo, $anna)) === ['gdrive'], 'only the new cloud counts as connected');
    check(isset(cloud_connections($pdo, $anna, true)['pcloud']), 'the old one still opens for its pictures');
    check(cloud_retiring_provider($pdo, $anna) === 'pcloud', 'and it is the retiring one');
    check((cloud_home_row($pdo, $anna) ?? [])['provider'] === 'gdrive'
        && (cloud_home_row($pdo, $anna, 'pcloud') ?? [])['provider'] === 'pcloud', 'the home row: the connected one, or the one named');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_cloud_backups WHERE user_id = $anna")->fetchColumn() === 0,
        'the backup starts over in the new cloud');
    $kinds = $pdo->query("SELECT kind FROM map_cloud_move_items WHERE move_id = $moveId ORDER BY kind")->fetchAll(PDO::FETCH_COLUMN);
    $expected = $withFiles ? ['photo', 'cloud', 'scale', 'copy', 'file'] : ['photo', 'cloud', 'scale', 'copy'];
    check(count($kinds) === count($expected) && !array_diff($expected, $kinds),
        $withFiles ? 'five to carry: photo, recording, calibration image, copy — and the find\'s other file'
                   : 'four pictures to carry: photo, recording, calibration image, copy');
    check(cloud_move_top_up($pdo, $move) === 0, 'topping up again adds nothing');
    try {
        cloud_move_begin($pdo, $anna, 'pcloud', 'gdrive');
        check(false, 'a second move is refused');
    } catch (RuntimeException $e) {
        check(true, 'a second move is refused');
    }

    echo "Carrying:\n";
    $calls = [];
    $working = static function (PDO $pdo, array $move, array $item, array $row) use (&$calls): array {
        $calls[] = $item['kind'];
        $old = $item['kind'] === 'photo' ? $row['cloud_fileid'] : $row['fileid'];
        return ['fileid' => 'new-' . $old, 'folderid' => 'G1', 'path' => 'Pilze/U', 'size' => 111]
            + ($item['kind'] === 'file' ? ['home_folderid' => 'GD', 'home_path' => 'Pilze/U/Dateien', 'name' => 'IMG_0001.CR3'] : []);
    };
    $undone = [];
    $undo = static function (PDO $pdo, array $move, array $at) use (&$undone): void { $undone[] = $at['fileid']; };
    $roomy = static fn(): ?int => null;

    // One picture first: the photo fails with an ordinary error.
    $failing = static function (PDO $pdo, array $move, array $item, array $row) use ($working): array {
        if ($item['kind'] === 'photo') throw new RuntimeException('Wolke hustet');
        return $working($pdo, $move, $item, $row);
    };
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $failing, $roomy, $undo);
    check($r['failed'] === 1 && $r['moved'] === ($withFiles ? 4 : 3), 'an ordinary failure counts as failed, the others move');
    if ($withFiles) {
        check($one("SELECT provider, fileid, folderid, home_folderid, home_path FROM map_point_files WHERE id = $fileId") ==
            ['provider' => 'gdrive', 'fileid' => 'new-d-1', 'folderid' => 'G1', 'home_folderid' => 'GD', 'home_path' => 'Pilze/U/Dateien'],
            'the find\'s other file lies in the new cloud, its „Dateien" folder known for the next move of the find');
        check(cloud_move_state($pdo, cloud_move_get($pdo, $moveId))['files'] === 1
            && str_starts_with(cloud_move_count_text(cloud_move_state($pdo, cloud_move_get($pdo, $moveId))), '4 von 5 Dateien'),
            'with a file among them, the bar counts „Dateien"');
    }
    $photoItem = $one("SELECT * FROM map_cloud_move_items WHERE move_id = $moveId AND kind = 'photo'");
    check($photoItem['status'] === 'waiting' && (int) $photoItem['attempts'] === 1 && $photoItem['error'] === 'Wolke hustet',
        'the failed picture waits with its attempt and reason');
    check($one("SELECT provider, fileid, folderid, path FROM map_cloud_files WHERE id = $recId") ==
        ['provider' => 'gdrive', 'fileid' => 'new-r-1', 'folderid' => 'G1', 'path' => 'Pilze/U'],
        'the recording now lies in the new cloud, with folder and path');
    check($one("SELECT provider, fileid FROM map_photo_scales WHERE id = $scaleId") == ['provider' => 'gdrive', 'fileid' => 'new-s-1'],
        'the calibration image too');
    check($one("SELECT provider, fileid, size FROM map_cloud_copies WHERE id = $copyId") == ['provider' => 'gdrive', 'fileid' => 'new-c-1', 'size' => 111],
        'and the copy');
    check(cloud_move_get($pdo, $moveId)['status'] === 'running', 'with a picture still waiting, the move runs on');

    $calls = [];
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $working, $roomy, $undo);
    check($calls === [], 'right after a failure the picture is not due — the back-off holds');
    $pdo->exec("UPDATE map_cloud_move_items SET tried_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE move_id = $moveId AND kind = 'photo'");

    echo "Full:\n";
    $full = static function () { throw new CloudMoveFull('Google Drive lehnt ab (403): The user\'s Drive storage quota has been exceeded.'); };
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $full, $roomy, $undo);
    $photoItem = $one("SELECT * FROM map_cloud_move_items WHERE move_id = $moveId AND kind = 'photo'");
    check($r['waiting'] === 1 && cloud_move_get($pdo, $moveId)['full_since'] !== null, 'full: the whole move waits');
    check((int) $photoItem['attempts'] === 1 && $photoItem['tried_at'] === null, 'the picture gets its attempt back — not its fault');
    $calls = [];
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $working, static fn(): ?int => 10, $undo);
    check($calls === [] && $r['waiting'] === 1, 'too little room (10 bytes for 1000): it asks, and waits on');
    check(cloud_move_state($pdo, cloud_move_get($pdo, $moveId))['full'] === true, 'the panel reads it as full');

    echo "Blocked:\n";
    $pdo->exec("UPDATE map_cloud_moves SET full_since = NULL WHERE id = $moveId");
    $blocked = static function () { throw new CloudMoveBlocked('Google Drive ist gerade nicht erreichbar.'); };
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $blocked, $roomy, $undo);
    check($r['waiting'] === 1 && cloud_move_get($pdo, $moveId)['problem'] === 'Google Drive ist gerade nicht erreichbar.',
        'a cloud that does not answer: the move waits and says why');

    echo "Stuck:\n";
    $gone = static function () { throw new CloudMoveStuck('In pCloud nicht mehr zu finden.'); };
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $gone, $roomy, $undo);
    check($r['stuck'] === 1, 'a picture gone from the old cloud is stuck at once');
    $stuck = cloud_move_stuck($pdo, cloud_move_get($pdo, $moveId));
    check(count($stuck) === 1 && $stuck[0]['name'] === 'IMG_0001.jpg' && $stuck[0]['what'] === 'Belegfoto bei „Umzugsprobe"'
        && $stuck[0]['error'] === 'In pCloud nicht mehr zu finden.', 'the stuck list names file, find and reason');
    check(cloud_move_get($pdo, $moveId)['status'] === 'running', 'a stuck picture keeps the move — and the key — open');
    $calls = [];
    cloud_moves_run($pdo, microtime(true) + 5, $anna, $working, $roomy, $undo);
    check($calls === [], 'a stuck picture is not tried again the same day');
    $pdo->exec("UPDATE map_cloud_move_items SET tried_at = DATE_SUB(NOW(), INTERVAL 25 HOUR) WHERE move_id = $moveId AND kind = 'photo'");
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $failing, $roomy, $undo);
    check($r['stuck'] === 1 && $r['failed'] === 0, 'a day later it is tried once more — one failure puts it back');
    // A try the process never came back from: counted, and no answer.
    $pdo->exec("UPDATE map_cloud_move_items SET status = 'waiting', attempts = " . CLOUD_MOVES_MAX_ATTEMPTS
        . ", error = NULL, tried_at = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE move_id = $moveId AND kind = 'photo'");
    $calls = [];
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $working, $roomy, $undo);
    $photoItem = $one("SELECT * FROM map_cloud_move_items WHERE move_id = $moveId AND kind = 'photo'");
    check($calls === [] && $photoItem['status'] === 'stuck' && str_contains((string) $photoItem['error'], 'brach der Server ab'),
        'tries that all died without an answer: stuck with that said, not tried forever');

    echo "Retry and the end:\n";
    check(cloud_move_retry($pdo, $anna) === 1, '„Nochmal versuchen" starts the stuck picture over');
    check(cloud_move_get($pdo, $moveId)['problem'] === null, 'and clears the waiting reason');
    $calls = [];
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $working, $roomy, $undo);
    check($calls === ['photo'] && $r['moved'] === 1 && $r['done'] === 1, 'the last picture moves, and the move ends');
    check($one("SELECT cloud_provider, cloud_fileid, cloud_folderid, cloud_path FROM map_photos WHERE id = $photoId") ==
        ['cloud_provider' => 'gdrive', 'cloud_fileid' => 'new-p-1', 'cloud_folderid' => 'G1', 'cloud_path' => 'Pilze/U'],
        'the voucher photo lies in the new cloud');
    $move = cloud_move_get($pdo, $moveId);
    check($move['status'] === 'done' && $move['finished_at'] !== null, 'the move is done');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_cloud_accounts WHERE user_id = $anna AND provider = 'pcloud'")->fetchColumn() === 0,
        'the old key is gone');
    check(array_keys(cloud_connections($pdo, $anna, true)) === ['gdrive'], 'the new cloud stays');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_cloud_copies WHERE id = $deletingId")->fetchColumn() === 0,
        'the copy that was to be removed in the old cloud is forgotten — nothing there is touched');
    $last = cloud_move_last($pdo, $anna, 'gdrive');
    check($last !== null && $last['moved'] === ($withFiles ? 5 : 4)
          && str_contains(cloud_move_footnote($last), $withFiles ? 'aus pCloud umgezogen: 5 Dateien.' : 'aus pCloud umgezogen: 4 Bilder.'),
        'the footnote says where they came from — „Dateien" with a file among them');

    echo "Races and the closing by hand:\n";
    // Back the other way, to have a running move again — with a row that
    // changes while it is carried.
    $account('pcloud');
    $pdo->exec("UPDATE map_cloud_accounts SET secret = '' WHERE user_id = $anna");
    $back = cloud_move_begin($pdo, $anna, 'gdrive', 'pcloud');
    $sneaky = static function (PDO $pdo, array $move, array $item, array $row) use ($working, $recId): array {
        if ($item['kind'] === 'cloud') {
            // Deleted by the user in the very second it was carried.
            $pdo->exec("UPDATE map_cloud_files SET fileid = 'elsewhere' WHERE id = $recId");
        }
        return $working($pdo, $move, $item, $row);
    };
    $undone = [];
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $sneaky, $roomy, $undo);
    check($undone === ['new-new-r-1'], 'a row that changed during the carrying: its new copy goes again');
    $again = $one("SELECT status, attempts FROM map_cloud_move_items WHERE move_id = $back AND kind = 'cloud'");
    check($again !== false && $again['status'] === 'waiting' && (int) $again['attempts'] === 0,
        'its item is dropped, not counted — and the top-up brings the row back fresh, since it still lies in the old cloud');
    $pdo->exec("UPDATE map_cloud_files SET fileid = 'new-r-1' WHERE id = $recId");
    $r = cloud_moves_run($pdo, microtime(true) + 5, $anna, $gone, $roomy, $undo);
    $backMove = cloud_move_get($pdo, $back);
    check($backMove['status'] === 'running', 'the top-up brought the recording back — and it got stuck');
    check(cloud_move_close($pdo, $anna) === 1, 'closing by hand counts what stays behind');
    check(cloud_move_get($pdo, $back)['status'] === 'closed'
        && (int) $pdo->query("SELECT COUNT(*) FROM map_cloud_accounts WHERE user_id = $anna AND provider = 'gdrive'")->fetchColumn() === 0,
        'the move is closed and the old key gone');
    check($one("SELECT provider FROM map_cloud_files WHERE id = $recId")['provider'] === 'gdrive',
        'the picture left behind still says where it lies');
} finally {
    $pdo->exec("DELETE FROM map_cloud_moves WHERE user_id = $anna");
    $pdo->exec("DELETE FROM map_cloud_accounts WHERE user_id = $anna");
    $pdo->exec("DELETE FROM map_cloud_backups WHERE user_id = $anna");
    $pdo->exec("DELETE FROM map_cloud_copies WHERE user_id = $anna AND origin_id IN (" . implode(',', $benRec) . ')');
    $pdo->exec("DELETE FROM map_photo_scales WHERE id = $scaleId");
    if ($scaleDevice !== null && $devicesBefore === []) {
        $pdo->exec("DELETE FROM map_scale_devices WHERE id = $scaleDevice");
    }
    $pdo->exec("DELETE FROM map_photos WHERE point_id = $pointId");
    $pdo->exec("DELETE FROM map_cloud_files WHERE point_id = $pointId");
    $pdo->exec("DELETE FROM map_points WHERE id = $pointId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
