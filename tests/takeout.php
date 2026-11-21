<?php
/**
 * Tests the Google Takeout import.
 *
 *   php tests/takeout.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE DRY RUN WRITES NOTHING. It is the same computation as the real
 *   run — if the preview promises something other than what happens
 *   afterwards, it is worse than none.
 *
 *   IDEMPOTENCE. The same export a second time must double nothing —
 *   that is the promise that lets you repeat it safely after something
 *   was added at Google.
 *
 *   THE IMPORT BRACKET (migration 0045): every run gets its own root
 *   list with the Takeout lists as leaves, every new point the import
 *   id — and the undo puts exactly these points into the trash, not the
 *   duplicates that already existed before.
 *
 *   WITHOUT A POSITION stays without a position: a place reference
 *   without coordinates is reported, never guessed.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/takeout.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

// Create the test account.
$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-takeout@pruefung.local', '', 'PRUEF', 'Takeout', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

// Build a Takeout ZIP as Google writes it: one CSV per list with the
// header row Titel,Notiz,URL,Tags,Kommentar — once with a heading ABOVE
// the header row, as occurs in real exports.
$zipPath = tempnam(sys_get_temp_dir(), 'pruef-takeout-') . '.zip';
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE);
$zip->addFromString('Takeout/Gespeichert/PRUEF Wiese.csv',
    "Meine schönsten Wiesen\n"
    . "Titel,Notiz,URL,Tags,Kommentar\n"
    . "\"Orchideenwiese\",\"Magerrasen\",\"https://www.google.com/maps/search/49.6558946,7.5029825\",\"\",\"Juni\"\n"
    . "\"Hutewald\",\"\",\"https://www.google.com/maps/place/50.1000000,8.2000000\",\"\",\"\"\n"
    . "\"Murnauer Moos\",\"\",\"https://www.google.com/maps/place/Murnauer+Moos/data=!4m2!3m1!1s0x479daeae\",\"\",\"\"\n");
$zip->addFromString('Takeout/Gespeichert/PRUEF Steinbrüche.csv',
    "Titel,Notiz,URL,Tags,Kommentar\n"
    . "\"Orchideenwiese\",\"\",\"https://www.google.com/maps/search/49.6558946,7.5029825\",\"\",\"\"\n");
$zip->close();

try {
    $data = takeout_read($zipPath);

    echo "Reading:\n";
    check(isset($data['lists']['PRUEF Wiese'], $data['lists']['PRUEF Steinbrüche']),
        'both lists come out of the archive, umlaut in the name included');
    $meadow = $data['lists']['PRUEF Wiese'];
    check(count($meadow['points']) === 2 && count($meadow['withoutPosition']) === 1,
        'the heading above the header row does no harm, the place reference stays without a position');
    check($meadow['points'][0]['note'] === "Magerrasen\nJuni",
        'note and comment are merged');

    echo "Dry run:\n";
    $plan = takeout_import($pdo, $userId, $data, false);
    check($plan['created'] === 2 && $plan['assigned'] === 1,
        'two points announced — the duplicate counts as an assignment, not as a new one');
    check(str_starts_with($plan['import'], 'Import '),
        'the preview names the future import name');
    $points = (int) $pdo->query("SELECT COUNT(*) FROM map_points WHERE user_id = $userId")->fetchColumn();
    check($points === 0, 'the dry run wrote nothing');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_imports WHERE user_id = $userId")->fetchColumn() === 0,
        'no import row either');

    echo "Importing:\n";
    $run1 = takeout_import($pdo, $userId, $data, true);
    check($run1['created'] === 2 && $run1['assigned'] === 1,
        'the real run does exactly what the dry run announced');
    $points = (int) $pdo->query("SELECT COUNT(*) FROM map_points WHERE user_id = $userId")->fetchColumn();
    check($points === 2, 'two points sit in the database');

    // The bracket since the Mappen (0168): a Mappe „Import …", the Takeout
    // lists in it — no list hangs under a list any more.
    $importRow = $pdo->query("SELECT * FROM map_imports WHERE user_id = $userId")->fetch();
    check($importRow !== false && $importRow['name'] === $run1['import'],
        'the import row is there and carries the name from the report');
    $folderId = (int) $importRow['folder_id'];
    $leaves = $pdo->query(
        "SELECT name FROM map_lists WHERE user_id = $userId AND folder_id = $folderId
          AND parent_id IS NULL AND deleted_at IS NULL ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);
    check($importRow['list_id'] === null && $leaves === ['PRUEF Steinbrüche', 'PRUEF Wiese']
        && $pdo->query("SELECT name FROM map_list_folders WHERE id = $folderId")->fetchColumn() === $run1['import'],
        "the Takeout lists lie in a Mappe of the import's name");

    $orchid = $pdo->query(
        "SELECT id, found_at, import_id FROM map_points WHERE user_id = $userId AND title = 'Orchideenwiese'"
    )->fetch();
    check($orchid['found_at'] === null,
        'without a time in the Takeout, found_at stays empty — nothing is invented');
    check((int) $orchid['import_id'] === (int) $importRow['id'],
        'every new point carries the identifier of its import');
    $listCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM map_point_lists WHERE point_id = ' . (int) $orchid['id']
    )->fetchColumn();
    check($listCount === 2, 'the duplicate sits in both leaves, created it is once');

    echo "Once more — its own import, nothing doubles:\n";
    $run2 = takeout_import($pdo, $userId, $data, true);
    check($run2['created'] === 0 && $run2['assigned'] === 3,
        'the same export a second time creates not a single point — everything merely assigned');
    check($run2['import'] !== $run1['import'] && str_contains($run2['import'], '(2)'),
        'the second import on the same day gets the running number');
    $points = (int) $pdo->query("SELECT COUNT(*) FROM map_points WHERE user_id = $userId")->fetchColumn();
    check($points === 2, 'two points remain');

    echo "Undoing:\n";
    $secondId = (int) $pdo->query(
        "SELECT id FROM map_imports WHERE user_id = $userId ORDER BY id DESC LIMIT 1"
    )->fetchColumn();
    $inTrash = import_remove($pdo, $userId, $secondId);
    check($inTrash === 0,
        'the second import had no points of its own — nothing wanders into the trash');
    check($points === (int) $pdo->query("SELECT COUNT(*) FROM map_points WHERE user_id = $userId AND deleted_at IS NULL")->fetchColumn(),
        'the points of the first import remain untouched');

    $firstId = (int) $importRow['id'];
    $inTrash = import_remove($pdo, $userId, $firstId);
    check($inTrash === 2, 'undoing the first import puts its two points into the trash');
    check((int) $pdo->query(
        "SELECT COUNT(*) FROM map_points WHERE user_id = $userId AND deleted_at IS NOT NULL"
    )->fetchColumn() === 2, 'there they lie — the trash decides what is final');
    check((int) $pdo->query(
        "SELECT COUNT(*) FROM map_lists WHERE user_id = $userId AND deleted_at IS NULL"
    )->fetchColumn() === 0, 'the import lists are gone, root included');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_list_folders WHERE user_id = $userId")->fetchColumn() === 0,
        "and the import's Mappe with them");
    check((int) $pdo->query("SELECT COUNT(*) FROM map_imports WHERE user_id = $userId")->fetchColumn() === 0,
        'the overview is empty');
} finally {
    @unlink($zipPath);
    // Points, lists, assignments go with the account via ON DELETE CASCADE;
    // the Mappen hang on no foreign key.
    if (folders_exist($pdo)) {
        $pdo->exec("DELETE FROM map_list_folders WHERE user_id = $userId");
    }
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
