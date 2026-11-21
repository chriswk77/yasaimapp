<?php
/**
 * Tests the Mappen themselves (migration 0168, includes/folders.php).
 *
 *   php tests/folders.php    (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   NOBODY LAYS A LIST INTO SOMEBODY ELSE'S MAPPE — the one rule a Mappe
 *   has to keep, since it holds nothing else.
 *
 *   A NEW THING GOES TO THE END of its level, and a list that changes its
 *   Mappe goes to the end of the new one — but a list saved in its Mappe
 *   keeps its place.
 *
 *   DELETING A MAPPE DELETES NO LIST: they stand where the Mappe stood.
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

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

if (!folders_exist($pdo)) {
    echo "Migration 0168 missing — nothing to check.\n";
    exit(1);
}

$accounts = [];
foreach (['pruef-anna', 'pruef-ben'] as $who) {
    $pdo->prepare("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
                   VALUES (?, '', 'PRUEF', ?, 'user', NOW())")->execute([$who . '@pruefung.local', ucfirst(substr($who, 6))]);
    $accounts[$who] = (int) $pdo->lastInsertId();
}
[$anna, $ben] = array_values($accounts);
$row = static fn(int $id): array => $pdo->query("SELECT folder_id, sort_order FROM map_lists WHERE id = $id")->fetch();

try {
    echo "Creating:\n";
    $a = list_create($pdo, $anna, ['name' => 'PRUEF A', 'color' => '#111111', 'symbol' => 'circle']);
    $folder = folder_create($pdo, $anna, ['name' => 'PRUEF Mappe']);
    $b = list_create($pdo, $anna, ['name' => 'PRUEF B', 'color' => '#222222', 'symbol' => 'circle', 'folder_id' => $folder]);
    $c = list_create($pdo, $anna, ['name' => 'PRUEF C', 'color' => '#333333', 'symbol' => 'circle', 'folder_id' => $folder]);
    $folders = folders_load($pdo, $anna);
    check(count($folders) === 1 && $folders[0]['name'] === 'PRUEF Mappe', 'the Mappe exists');
    check($folders[0]['sort_order'] > (int) $row($a)['sort_order'], 'a new Mappe goes after the lists already there');
    check((int) $row($b)['folder_id'] === $folder && (int) $row($c)['sort_order'] > (int) $row($b)['sort_order'],
        'lists made in the Mappe lie in it, in the order they came');
    $loaded = array_column(lists_load($pdo, $anna), 'folder_id', 'id');
    check($loaded[$b] === $folder && $loaded[$a] === null, 'lists_load carries folder_id');

    echo "Somebody else's:\n";
    $refused = false;
    try {
        list_create($pdo, $ben, ['name' => 'PRUEF fremd', 'color' => '#444444', 'symbol' => 'circle', 'folder_id' => $folder]);
    } catch (InvalidArgumentException $e) {
        $refused = true;
    }
    check($refused, "nobody lays a list into somebody else's Mappe");
    $refused = false;
    $own = list_create($pdo, $ben, ['name' => 'PRUEF Bens', 'color' => '#555555', 'symbol' => 'circle']);
    try {
        list_update($pdo, $ben, $own, ['folder_id' => $folder]);
    } catch (InvalidArgumentException $e) {
        $refused = true;
    }
    check($refused, '… not even by moving it there');
    check(!folder_update($pdo, $ben, $folder, ['name' => 'gekapert']) && !folder_delete($pdo, $ben, $folder),
        "nor rename or delete somebody else's Mappe");

    echo "Moving:\n";
    list_update($pdo, $anna, $a, ['name' => 'PRUEF A', 'folder_id' => $folder]);
    check((int) $row($a)['folder_id'] === $folder && (int) $row($a)['sort_order'] > (int) $row($c)['sort_order'],
        'a list moved into the Mappe goes to its end');
    $before = (int) $row($b)['sort_order'];
    list_update($pdo, $anna, $b, ['name' => 'PRUEF B neu', 'folder_id' => $folder]);
    check((int) $row($b)['sort_order'] === $before, 'a list saved in its Mappe keeps its place');
    list_update($pdo, $anna, $a, ['name' => 'PRUEF A', 'folder_id' => null]);
    check($row($a)['folder_id'] === null, 'and out again');

    echo "Deleting:\n";
    // A after the Mappe, so the freed lists must land between.
    $pdo->exec("UPDATE map_lists SET sort_order = 30000 WHERE id = $a");
    check(folder_delete($pdo, $anna, $folder), 'the Mappe goes');
    check(folders_load($pdo, $anna) === [], '… and is gone');
    check($row($b)['folder_id'] === null && $row($c)['folder_id'] === null, 'its lists stay');
    check((int) $row($b)['sort_order'] < (int) $row($c)['sort_order']
        && (int) $row($c)['sort_order'] < (int) $row($a)['sort_order'],
        '… where the Mappe stood, in their own order');
} finally {
    $idList = implode(',', $accounts);
    $pdo->exec("DELETE FROM map_lists WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_list_folders WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_users WHERE id IN ($idList)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
