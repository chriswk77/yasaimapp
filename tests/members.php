<?php
/**
 * Tests the deletion of an account against the database.
 *
 *   php tests/members.php        (from the project directory)
 *
 * The message promises "gelöscht, samt Punkten, Listen und Dateien" —
 * here we count whether that is true. Most of it ON DELETE CASCADE
 * clears away; map_point_overwritten (migration 0039) as well as
 * map_friends, map_groups and map_group_members (migration 0041)
 * deliberately have no foreign key and must be cleared by
 * members_delete() itself. Exactly such places break silently — a
 * table added next year that hangs on the account without cascading
 * would only show up here. That is why the test counts every table
 * individually, not just a sum.
 *
 * The friendships need a counterpart: a second test account that stays.
 * It also shows that the deletion only takes the relationships of the
 * deleted one along — not the friend's group.
 *
 * Created rows and files are removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/members_content.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

if (!members_ready($pdo)
    || !table_exists($pdo, 'map_point_overwritten')
    || !table_exists($pdo, 'map_friends')) {
    exit("The local database is not up to date with the migrations — skipped.\n");
}

const TEST_EMAIL  = 'pruefung-loeschen@example.invalid';
const TEST_FRIEND = 'pruefung-freund@example.invalid';

// Clear away leftovers of an earlier, aborted run — in the same order as
// the function itself, for the cascade does not take the rows without a
// foreign key along.
$cleanUp = static function (PDO $pdo): void {
    $old = $pdo->prepare('SELECT id FROM map_users WHERE email IN (?, ?)');
    $old->execute([TEST_EMAIL, TEST_FRIEND]);
    foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $oldId) {
        $pdo->prepare('DELETE FROM map_point_overwritten WHERE user_id = ?')->execute([$oldId]);
        $pdo->prepare('DELETE FROM map_friends WHERE from_user_id = ? OR to_user_id = ?')
            ->execute([$oldId, $oldId]);
        $pdo->prepare('DELETE FROM map_group_members WHERE user_id = ?
                          OR group_id IN (SELECT id FROM map_groups WHERE owner_user_id = ?)')
            ->execute([$oldId, $oldId]);
        $pdo->prepare('DELETE FROM map_groups WHERE owner_user_id = ?')->execute([$oldId]);
        directory_remove(user_directory((int) $oldId));
        $pdo->prepare('DELETE FROM map_users WHERE id = ?')->execute([$oldId]);
    }
};
$cleanUp($pdo);

$foreignPoints = (int) $pdo->query('SELECT COUNT(*) FROM map_points')->fetchColumn();

echo "\nCreating an account, hanging data on it\n";
$pdo->prepare(
    'INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())'
)->execute([TEST_EMAIL, password_hash('nur-zum-loeschen', PASSWORD_DEFAULT), 'PRUEF', 'Loeschen', 'user']);
$id = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO map_lists (user_id, name, created_at, updated_at)
               VALUES (?, 'Prüfliste', NOW(), NOW())")->execute([$id]);
$list = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO map_list_fields (list_id, name) VALUES (?, 'Prüffeld')")
    ->execute([$list]);

$pdo->prepare("INSERT INTO map_points (user_id, lat, lon, title, created_at, updated_at)
               VALUES (?, 50.0, 10.0, 'Prüfpunkt', NOW(), NOW())")->execute([$id]);
$point = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO map_point_lists (point_id, list_id) VALUES (?, ?)')
    ->execute([$point, $list]);

$pdo->prepare("INSERT INTO map_point_species (user_id, point_id, field, name)
               VALUES (?, ?, '', 'Prüfname')")->execute([$id, $point]);

$pdo->prepare("INSERT INTO map_photos (user_id, point_id, filename, created_at)
               VALUES (?, ?, 'pruefung.jpg', NOW())")->execute([$id, $point]);

$pdo->prepare("INSERT INTO map_areas (user_id, name, corners, created_at, updated_at)
               VALUES (?, 'Prüfgebiet', '[[50,10],[50,11],[51,10]]', NOW(), NOW())")->execute([$id]);

$pdo->prepare("INSERT INTO map_point_overwritten (user_id, point_id, field, value)
               VALUES (?, ?, 'title', '\"alte Fassung\"')")->execute([$id, $point]);

$pdo->prepare("INSERT INTO map_tokens (user_id, token_hash, device, created_at)
               VALUES (?, ?, 'PRUEFUNG', NOW())")
    ->execute([$id, hash('sha256', bin2hex(random_bytes(16)))]);

// The counterpart for the friendships. It stays put during the deletion
// — only its relationships TO the deleted one must go along.
$pdo->prepare(
    'INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())'
)->execute([TEST_FRIEND, password_hash('nur-zum-loeschen', PASSWORD_DEFAULT), 'PRUEF', 'Freund', 'user']);
$friend = (int) $pdo->lastInsertId();

// Both directions, so that from_user_id AND to_user_id are checked. In
// domain terms there would be only one row per pair — for the clean-up
// each counts on its own.
$pdo->prepare("INSERT INTO map_friends (from_user_id, to_user_id, status) VALUES (?, ?, 'accepted')")
    ->execute([$id, $friend]);
$pdo->prepare("INSERT INTO map_friends (from_user_id, to_user_id, status) VALUES (?, ?, 'requested')")
    ->execute([$friend, $id]);

// A group of the deleted one with the friend in it — and a group of the
// friend with the deleted one in it. The first must vanish entirely, the
// second only lose its member.
$pdo->prepare("INSERT INTO map_groups (owner_user_id, name) VALUES (?, 'Prüfgruppe')")
    ->execute([$id]);
$group = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_group_members (group_id, user_id) VALUES (?, ?)')
    ->execute([$group, $friend]);

$pdo->prepare("INSERT INTO map_groups (owner_user_id, name) VALUES (?, 'Prüfgruppe')")
    ->execute([$friend]);
$friendGroup = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_group_members (group_id, user_id) VALUES (?, ?)')
    ->execute([$friendGroup, $id]);

$directory = user_directory($id);
@mkdir($directory . '/photos', 0775, true);
file_put_contents($directory . '/photos/pruefung.jpg', 'kein echtes Bild');
check(is_file($directory . '/photos/pruefung.jpg'), "account $id with data and files stands");

echo "\nDeleting via members_delete()\n";
$result = members_delete(
    $pdo,
    ['id' => $id, 'first_name' => 'PRUEF', 'last_name' => 'Loeschen', 'email' => TEST_EMAIL, 'role' => 'user'],
    ['message' => null, 'error' => []]
);
check($result['error'] === [], 'runs through without errors');
check(is_string($result['message']) && str_contains($result['message'], 'gelöscht'),
    'reports the completion');

echo "\nNothing is left behind\n";
$count = static function (string $sql, int $value) use ($pdo): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$value]);
    return (int) $stmt->fetchColumn();
};
check($count('SELECT COUNT(*) FROM map_users WHERE id = ?', $id) === 0, 'map_users');
check($count('SELECT COUNT(*) FROM map_points WHERE user_id = ?', $id) === 0, 'map_points');
check($count('SELECT COUNT(*) FROM map_lists WHERE user_id = ?', $id) === 0, 'map_lists');
check($count('SELECT COUNT(*) FROM map_point_lists WHERE point_id = ?', $point) === 0, 'map_point_lists');
check($count('SELECT COUNT(*) FROM map_point_species WHERE user_id = ?', $id) === 0, 'map_point_species');
check($count('SELECT COUNT(*) FROM map_photos WHERE point_id = ?', $point) === 0, 'map_photos');
check($count('SELECT COUNT(*) FROM map_list_fields WHERE list_id = ?', $list) === 0, 'map_list_fields');
check($count('SELECT COUNT(*) FROM map_areas WHERE user_id = ?', $id) === 0, 'map_areas');
check($count('SELECT COUNT(*) FROM map_tokens WHERE user_id = ?', $id) === 0, 'map_tokens');
check($count('SELECT COUNT(*) FROM map_point_overwritten WHERE user_id = ?', $id) === 0,
    'map_point_overwritten — without a foreign key, cleared by hand');

// The friends tables (migration 0041), likewise without a foreign key.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM map_friends WHERE from_user_id = ? OR to_user_id = ?');
$stmt->execute([$id, $id]);
check((int) $stmt->fetchColumn() === 0, 'map_friends — both directions');
check($count('SELECT COUNT(*) FROM map_groups WHERE owner_user_id = ?', $id) === 0,
    'map_groups — the own group');
check($count('SELECT COUNT(*) FROM map_group_members WHERE group_id = ?', $group) === 0,
    'map_group_members — the members of the own group');
check($count('SELECT COUNT(*) FROM map_group_members WHERE user_id = ?', $id) === 0,
    'map_group_members — the own membership with the friend');

echo "\nFiles and foreign data\n";
check(!is_dir($directory), 'the directory on disk is gone');
check((int) $pdo->query('SELECT COUNT(*) FROM map_points')->fetchColumn() === $foreignPoints,
    'points of other accounts are untouched');
check($count('SELECT COUNT(*) FROM map_users WHERE id = ?', $friend) === 1,
    'the friend himself remains standing');
check($count('SELECT COUNT(*) FROM map_groups WHERE id = ?', $friendGroup) === 1,
    'and his group with him — only without the deleted one');

$cleanUp($pdo);
echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
