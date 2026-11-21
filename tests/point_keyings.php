<?php
/**
 * The keying way typed at a find (migration 0198, includes/point_keyings.php).
 *
 *   php tests/point_keyings.php
 *
 * WHAT IS HELD: one's own way is stored and read back as typed, in one
 * shape; everybody who sees the find reads it, nobody writes another's; an
 * empty text takes the way away and leaves no empty row; and a way typed
 * beside a walk of the identification module touches nothing of the walk.
 *
 * AND THE LINES OF A WALK (migration 0199): what the walk's tool hands over
 * as its confirmed ways is kept with the walk, one line per way; a walk
 * from before gets its lines when it is next read while a tool is there,
 * and without a tool the kept lines are delivered as they are. Asked of a
 * MADE-UP module in a temporary folder — the rule is the core's, whatever
 * tool answers.
 *
 * Against the demodata fixture (admin/demodata.php): anna, her own find,
 * and ben, who sees what she shares with him and nothing else.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/point_keyings.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}

if (!point_keying_way_ready($pdo)) {
    echo "Migration 0198 is not applied — nothing to test.\n";
    exit(1);
}
$user = static function (string $email) use ($pdo): ?array {
    $q = $pdo->prepare('SELECT * FROM map_users WHERE email = ?');
    $q->execute([$email]);
    return $q->fetch() ?: null;
};
$anna = $user('anna@demo.local');
$ben  = $user('ben@demo.local');
if (!$anna || !$ben) {
    echo "The demodata fixture is missing — php admin/demodata.php --new\n";
    exit(1);
}
$a = (int) $anna['id'];
$b = (int) $ben['id'];

// One of anna's finds that ben sees, and one he does not.
$own = array_map('intval', $pdo->query("SELECT id FROM map_points WHERE user_id = $a AND deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
$seen = null; $hidden = null;
foreach ($own as $id) {
    if (point_view_scope($pdo, $b, $id) !== null) { $seen ??= $id; } else { $hidden ??= $id; }
}
check($seen !== null && $hidden !== null, 'the fixture holds a find of anna ben sees, and one he does not');
if ($seen === null || $hidden === null) { echo "$failed failed.\n"; exit(1); }

$rows = static fn(int $point): array => $pdo->query("SELECT user_id, walk, way FROM map_point_keyings WHERE point_id = $point ORDER BY user_id")->fetchAll();
$pdo->exec("DELETE FROM map_point_keyings WHERE point_id IN ($seen, $hidden)");
try {
    echo "My own way:\n";
    check(point_keying_way_save($pdo, $anna, $seen, "  Gröger I, Schlüssel 3 → 12 → 15  \r\nFunga Nordica: Boletus, 4 → 7\t\n\n"), 'anna types a way at her find');
    $mine = point_keyings_for($pdo, $a, $seen);
    check(($mine['way_ready'] ?? false) === true && ($mine['mine']['way'] ?? null) === "Gröger I, Schlüssel 3 → 12 → 15\nFunga Nordica: Boletus, 4 → 7",
        'it comes back in one shape: a line per book, no blanks at the ends');
    check(array_key_exists('walk', $mine['mine']) && $mine['mine']['walk'] === null && $mine['mine']['ways'] === null, 'a typed way alone carries no walk');

    echo "The others:\n";
    $his = point_keyings_for($pdo, $b, $seen);
    check(array_key_exists('mine', $his) && $his['mine'] === null && count($his['others']) === 1 && $his['others'][0]['way'] === $mine['mine']['way'],
        'ben, who sees the find, reads her way among the others');
    check(point_keying_way_save($pdo, $ben, $seen, 'Bresinsky: 2 → 9'), 'ben types his own beside it');
    $both = $rows($seen);
    check(count($both) === 2 && $both[0]['way'] !== $both[1]['way'], 'two rows, one per person — nobody overwrites the other');
    check(!point_keying_way_save($pdo, $ben, $hidden, 'x') && $rows($hidden) === [], 'at a find he does not see, ben writes nothing');
    check(point_keyings_for($pdo, $b, $hidden) === null, 'and reads nothing');

    echo "Beside a walk:\n";
    $walk = '{"n":1,"at":null}';
    $pdo->prepare('UPDATE map_point_keyings SET walk = ?, confirmed_name = ? WHERE point_id = ? AND user_id = ?')->execute([$walk, 'Boletus edulis', $seen, $a]);
    check(point_keying_way_save($pdo, $anna, $seen, 'Gröger I → Boletus edulis'), 'a way typed where a walk stands');
    $r = $rows($seen)[0];
    check($r['walk'] === $walk && $r['way'] === 'Gröger I → Boletus edulis', 'changes the way and leaves the walk alone');

    echo "Taking it away:\n";
    check(point_keying_way_save($pdo, $anna, $seen, "  \n "), 'an empty text is accepted');
    $r = $rows($seen)[0];
    check((int) $r['user_id'] === $a && $r['walk'] === $walk && $r['way'] === null, 'the way is gone, the row with the walk stays');
    check(point_keying_way_save($pdo, $ben, $seen, '') && count($rows($seen)) === 1, 'a row with neither way nor walk goes with it');
    check(mb_strlen((string) (point_keying_way_save($pdo, $ben, $seen, str_repeat('ä', POINT_KEYING_WAY_MAX + 50)) ? $rows($seen)[1]['way'] : '')) === POINT_KEYING_WAY_MAX,
        'a text beyond the limit is cut at ' . POINT_KEYING_WAY_MAX . ' characters');

    if (!point_keying_lines_ready($pdo)) {
        echo "The lines of a walk: migration 0199 is not applied — skipped.\n";
    } else {
        echo "The lines of a walk:\n";
        $pdo->exec("DELETE FROM map_point_keyings WHERE point_id = $seen");
        $tool = sys_get_temp_dir() . '/yasaimapp-tool-' . bin2hex(random_bytes(4));
        mkdir($tool . '/tool', 0700, true);
        file_put_contents($tool . '/tool/module.php', '<?php return ['
            . "'walk_writer' => static fn(array \$user): bool => true,"
            . "'walk_lines' => static fn(PDO \$pdo, array \$walk): array => \$walk['lines'] ?? []];");
        $none = sys_get_temp_dir() . '/yasaimapp-none-' . bin2hex(random_bytes(4));
        mkdir($none);
        $line = static fn(): array => $pdo->query("SELECT walk_lines, updated_at FROM map_point_keyings WHERE point_id = $seen AND user_id = $a")->fetch() ?: [];
        try {
            modules($tool);
            check(point_keying_save($pdo, $anna, $seen, ['lines' => ["Gröger 2 · Hauptschlüssel: 1a →  5b\n→ Boletus edulis ", '', 'Funga Nordica: 4 → 7 → Boletus edulis']]),
                'a walk is saved where a tool says who may');
            check($line()['walk_lines'] === "Gröger 2 · Hauptschlüssel: 1a → 5b → Boletus edulis\nFunga Nordica: 4 → 7 → Boletus edulis",
                'its confirmed ways are kept, one line each — no break inside a line, no empty one');
            $got = point_keyings_for($pdo, $b, $seen);
            check(($got['others'][0]['walk_lines'] ?? null) === $line()['walk_lines'] && $got['others'][0]['ways'] === null,
                'everybody who sees the find gets the lines, and no drawn ways where the tool gives none');

            check(point_keying_save($pdo, $anna, $seen, ['lines' => []]) && $line()['walk_lines'] === '',
                'a walk without a confirmed way keeps an empty text — made, and nothing in it');
            check(point_keyings_for($pdo, $a, $seen)['mine']['walk_lines'] === null, 'which is delivered as no lines');

            $pdo->prepare("UPDATE map_point_keyings SET walk = ?, walk_lines = NULL, updated_at = '2026-01-02 03:04:05' WHERE point_id = ? AND user_id = ?")
                ->execute(['{"lines":["Bresinsky: 2 → 9 → Xerocomus"]}', $seen, $a]);
            check(point_keyings_for($pdo, $b, $seen)['others'][0]['walk_lines'] === 'Bresinsky: 2 → 9 → Xerocomus', 'a walk from before gets its lines when it is read');
            check($line() === ['walk_lines' => 'Bresinsky: 2 → 9 → Xerocomus', 'updated_at' => '2026-01-02 03:04:05'], 'they are kept from then on, and the walk\'s date is left alone');

            modules($none);
            check(point_keyings_for($pdo, $a, $seen)['mine']['walk_lines'] === 'Bresinsky: 2 → 9 → Xerocomus', 'without a tool the kept lines are still delivered');
            $pdo->exec("UPDATE map_point_keyings SET walk_lines = NULL WHERE point_id = $seen AND user_id = $a");
            check(point_keyings_for($pdo, $a, $seen)['mine']['walk_lines'] === null && $line()['walk_lines'] === null,
                'and a walk without lines waits — nothing is made up');
            check(!point_keying_save($pdo, $anna, $seen, ['lines' => ['x']]), 'nor is a walk written without a tool');
        } finally {
            modules(PROJECT_PATH . '/modules');
            unlink($tool . '/tool/module.php'); rmdir($tool . '/tool'); rmdir($tool); rmdir($none);
        }
    }
} finally {
    $pdo->exec("DELETE FROM map_point_keyings WHERE point_id IN ($seen, $hidden)");
}

echo $failed === 0 ? "All good.\n" : "$failed failed.\n";
exit($failed === 0 ? 0 : 1);
