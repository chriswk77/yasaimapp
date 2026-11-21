<?php
/**
 * Tests that no list lies under a list any more.
 *
 *   php tests/sublists.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   Since the Mappen (migration 0168, 11.10.2026) lists are flat
 *   and a Mappe gathers them. The column parent_id still exists, so every
 *   door that could write it must refuse: creating with a parent, moving
 *   under a list — from an old device, an old queue entry, a stray call.
 *   One list under another would bring back the heading that holds nothing
 *   and passes its settings down, and nothing in the interface could show
 *   or undo it any more.
 *
 *   A null parent is no move and must pass — old clients send it along.
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

function rejected(callable $call): bool
{
    try {
        $call();
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
}

$pdo->prepare("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
               VALUES ('pruef-flach@pruefung.local', '', 'PRUEF', 'Flach', 'user', NOW())")->execute();
$userId = (int) $pdo->lastInsertId();

try {
    echo "No list under a list:\n";
    $a = list_create($pdo, $userId, ['name' => 'PRUEF A']);
    check(rejected(fn() => list_create($pdo, $userId, ['name' => 'PRUEF B', 'parent_id' => $a])),
        'creating under a list is refused');
    $b = list_create($pdo, $userId, ['name' => 'PRUEF B', 'parent_id' => null]);
    check($b > 0, 'a null parent passes — old clients send it along');
    check(rejected(fn() => list_update($pdo, $userId, $b, ['parent_id' => $a])),
        'moving under a list is refused');
    check(!rejected(fn() => list_update($pdo, $userId, $b, ['name' => 'PRUEF B neu', 'parent_id' => null])),
        'a save with a null parent passes');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_lists WHERE user_id = $userId AND parent_id IS NOT NULL")->fetchColumn() === 0,
        'nothing hangs anywhere');
    $loaded = lists_load($pdo, $userId);
    check(count($loaded) === 2 && array_unique(array_column($loaded, 'parent_id')) === [null],
        'lists_load hands out flat lists');
} finally {
    $pdo->exec("DELETE FROM map_lists WHERE user_id = $userId");
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
