<?php
/**
 * Tests the conversion of list trees into Mappen (migration 0168,
 * includes/folders_convert_content.php).
 *
 *   php tests/folders_convert.php    (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   EVERY HEADING BECOMES A MAPPE, a heading inside one a Mappe of its own,
 *   and nothing hangs under anything afterwards — a Mappe in a Mappe is
 *   what the rebuild ends.
 *
 *   EVERY LIST TAKES ALONG WHAT IT INHERITED: species list, fields, the
 *   share with every recipient's rights and filter, the joint flag. If one
 *   falls, a list silently shows less or to fewer people than before the
 *   button — the user did nothing and something changed.
 *
 *   THE CHAT STAYS WITH THE FIRST LIST, the others start empty.
 *
 *   AUTO LISTS take the lists instead of the heading; points stay where
 *   they lay; leftovers in a heading are caught; a second press does nothing.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/folders_convert_content.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

if (!folders_ready($pdo) || !list_shares_ready($pdo)) {
    echo "Migrations 0160/0168 missing — nothing to check.\n";
    exit(1);
}

$accounts = [];
foreach (['pruef-anna', 'pruef-ben', 'pruef-carla'] as $who) {
    $pdo->prepare(
        "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
         VALUES (?, '', 'PRUEF', ?, 'user', NOW())"
    )->execute([$who . '@pruefung.local', ucfirst(substr($who, 6))]);
    $accounts[$who] = (int) $pdo->lastInsertId();
}
[$anna, $ben, $carla] = array_values($accounts);

/* THE OLD TREE, BY HAND: list_create refuses a parent since the Mappen,
   and this test is exactly about the trees that existed before them. */
$legacy = static function (int $user, string $name, int $parent, string $color, string $symbol) use ($pdo): int {
    $pdo->prepare('INSERT INTO map_lists (user_id, parent_id, name, color, symbol, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, NOW(), NOW())')->execute([$user, $parent, $name, $color, $symbol]);
    return (int) $pdo->lastInsertId();
};

$list = static function (int $id) use ($pdo): array {
    return $pdo->query("SELECT * FROM map_lists WHERE id = $id")->fetch() ?: [];
};
$fieldsOf = static function (int $id) use ($pdo): array {
    return $pdo->query("SELECT name FROM map_list_fields WHERE list_id = $id ORDER BY sort_order, id")->fetchAll(PDO::FETCH_COLUMN);
};
$membersOf = static function (?int $cId) use ($pdo): array {
    if ($cId === null) {
        return [];
    }
    $out = [];
    foreach ($pdo->query("SELECT type, target_id, rights, filter FROM map_container_members WHERE container_id = $cId ORDER BY target_id") as $r) {
        $out[(int) $r['target_id']] = $r;
    }
    return $out;
};

try {
    foreach ([$ben, $carla] as $who) {
        friendship_request($pdo, $anna, $who);
        $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $who")->fetchColumn();
        friendship_accept($pdo, $who, $fId);
    }

    /* Anna's tree:
         Meine Funde (species, field Substrat, shared: Ben may set titles,
                      Carla sees only boletes, both work in it)
         ├ Pilze      (own field Geruch)
         ├ Flechten   (+ a deleted „Alt" below it)
         └ Gebiete    (field Hektar)
             ├ Wiese
             └ Wald
         Waldbrände   (plain)
         Auto         (auto list over Meine Funde and Waldbrände) */
    $root = list_create($pdo, $anna, ['name' => 'PRUEF Meine Funde', 'color' => '#336699', 'symbol' => 'circle']);
    $pdo->exec("UPDATE map_lists SET is_species = 1 WHERE id = $root");
    $pilze    = $legacy($anna, 'PRUEF Pilze', $root, '#996633', 'mushroom');
    $flechten = $legacy($anna, 'PRUEF Flechten', $root, '#669933', 'lichen');
    $gebiete  = $legacy($anna, 'PRUEF Gebiete', $root, '#999999', 'circle');
    $wiese    = $legacy($anna, 'PRUEF Wiese', $gebiete, '#aaaa00', 'circle');
    $wald     = $legacy($anna, 'PRUEF Wald', $gebiete, '#00aa00', 'circle');
    $alt      = $legacy($anna, 'PRUEF Alt', $flechten, '#000000', 'circle');
    $pdo->exec("UPDATE map_lists SET deleted_at = NOW() WHERE id = $alt");
    $brand    = list_create($pdo, $anna, ['name' => 'PRUEF Waldbrände', 'color' => '#cc3300', 'symbol' => 'circle']);
    $pdo->exec("UPDATE map_lists SET sort_order = 0 WHERE id = $root");
    foreach ([$pilze, $flechten, $gebiete] as $i => $l) {
        $pdo->exec("UPDATE map_lists SET sort_order = $i WHERE id = $l");
    }
    $pdo->exec("UPDATE map_lists SET sort_order = 1 WHERE id = $wald");
    $pdo->exec("UPDATE map_lists SET sort_order = 5 WHERE id = $brand");
    $insField = $pdo->prepare('INSERT INTO map_list_fields (list_id, name, type, is_species, sort_order) VALUES (?, ?, ?, 0, ?)');
    $insField->execute([$root, 'PRUEF Substrat', 'text', 0]);
    $insField->execute([$pilze, 'PRUEF Geruch', 'text', 0]);
    $insField->execute([$gebiete, 'PRUEF Hektar', 'text', 0]);

    $boletus = point_create($pdo, $anna, ['lat' => 50.1, 'lon' => 8.1, 'title' => 'Boletus edulis', 'lists' => [$pilze]]);
    $wiesenPunkt = point_create($pdo, $anna, ['lat' => 50.2, 'lon' => 8.2, 'title' => 'Hygrocybe', 'lists' => [$wiese]]);

    $bothWork = ['title', 'own_points'];
    $saved = list_share_save($pdo, $anna, $root, [
        ['type' => 'friend', 'id' => $ben, 'rights' => $bothWork],
        ['type' => 'friend', 'id' => $carla, 'rights' => ['own_points'],
         'filter' => ['terms' => [['type' => 'contains', 'text' => 'boletus']]]],
    ]);
    $shareId = (int) $saved['container'];
    $pdo->prepare("INSERT INTO map_container_messages (container_id, user_id, text, created_at) VALUES (?, ?, 'PRUEF hallo', NOW())")
        ->execute([$shareId, $ben]);
    shared_look_set($pdo, $ben, $root, '#123456', null);

    $auto = list_create($pdo, $anna, ['name' => 'PRUEF Auto', 'color' => '#ffffff', 'symbol' => 'circle']);
    $pdo->prepare('UPDATE map_lists SET filter = ? WHERE id = ?')
        ->execute([json_encode(['sources' => [$root, $brand], 'terms' => []]), $auto]);

    // A leftover: a point still filed in the heading „Gebiete" itself.
    $altPunkt = point_create($pdo, $anna, ['lat' => 50.3, 'lon' => 8.3, 'title' => 'Altlast', 'lists' => [$wald]]);
    $pdo->exec("INSERT INTO map_point_lists (point_id, list_id) VALUES ($altPunkt, $gebiete)");
    $pdo->exec("DELETE FROM map_point_lists WHERE point_id = $altPunkt AND list_id = $wald");

    echo "The preview:\n";
    $plan = folders_convert_plan($pdo, [$anna]);
    $u = $plan['users'][$anna] ?? null;
    check($u !== null && array_column($u['folders'], 'name') === ['PRUEF Meine Funde', 'PRUEF Gebiete'],
        'two Mappen: the heading and the heading inside it, in that order');
    check($u !== null && array_keys($u['folders'][0]['lists']) === [$pilze, $flechten]
        && array_keys($u['folders'][1]['lists']) === [$wiese, $wald], 'each holds the lists that lay directly in it');
    check(($u['folders'][1]['rescue'] ?? 0) === 1, 'the point left in „Gebiete" is noticed');

    echo "The button:\n";
    $done = folders_convert_run($pdo, [$anna]);
    check($done === ['folders' => 2, 'lists' => 5, 'accounts' => 1], 'two Mappen, five lists (one of them the catch list)');

    $folders = $pdo->query("SELECT id, name, sort_order FROM map_list_folders WHERE user_id = $anna ORDER BY sort_order")->fetchAll();
    $fMeine = (int) $folders[0]['id'];
    $fGebiete = (int) $folders[1]['id'];
    check(count($folders) === 2 && $folders[0]['name'] === 'PRUEF Meine Funde' && $folders[1]['name'] === 'PRUEF Gebiete', 'the Mappen exist, in order');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_lists WHERE user_id = $anna AND parent_id IS NOT NULL")->fetchColumn() === 0,
        'nothing hangs under anything');
    check($list($root) === [] && $list($gebiete) === [], 'the headings are gone');
    check((int) $list($pilze)['folder_id'] === $fMeine && (int) $list($wald)['folder_id'] === $fGebiete, 'the lists lie in their Mappe');
    check((int) $list($alt)['folder_id'] === $fMeine && $list($alt)['deleted_at'] !== null, 'a deleted list lands in the Mappe, still deleted');
    check($list($brand)['folder_id'] === null && (int) $list($brand)['sort_order'] > (int) $folders[1]['sort_order'],
        'a plain list stays outside, after the Mappen');
    check($list($pilze)['symbol'] === 'mushroom' && $list($pilze)['color'] === '#996633', 'colour and shape untouched');

    echo "What the lists took along:\n";
    check((bool) $list($pilze)['is_species'] && (bool) $list($wiese)['is_species'], 'species list — also two levels down');
    check($fieldsOf($pilze) === ['PRUEF Substrat', 'PRUEF Geruch'], "the heading's field before the list's own");
    check($fieldsOf($wiese) === ['PRUEF Substrat', 'PRUEF Hektar'], 'fields of every heading above, root first');
    check(list_share_container($pdo, $pilze) === $shareId, 'the first list takes the share itself');
    check($pdo->query("SELECT name FROM map_containers WHERE id = $shareId")->fetchColumn() === 'PRUEF Pilze',
        "… named after it now, not after the heading");
    $chatCount = static fn(?int $c): int => $c === null ? -1
        : (int) $pdo->query("SELECT COUNT(*) FROM map_container_messages WHERE container_id = $c")->fetchColumn();
    check($chatCount($shareId) === 1, '… and its chat');
    $others = [$flechten, $wiese, $wald];
    $allShared = true;
    $sameRights = true;
    $emptyChat = true;
    $joint = true;
    foreach ($others as $l) {
        $c = list_share_container($pdo, $l);
        $allShared = $allShared && $c !== null && $c !== $shareId;
        $m = $membersOf($c);
        $sameRights = $sameRights && isset($m[$ben], $m[$carla])
            && json_decode((string) $m[$ben]['rights'], true) === $bothWork
            && str_contains((string) $m[$carla]['filter'], 'boletus');
        $emptyChat = $emptyChat && $chatCount($c) === 0;
        $joint = $joint && (int) $pdo->query("SELECT joint FROM map_container_lists WHERE container_id = " . (int) $c)->fetchColumn() === 1;
    }
    check($allShared, 'every further list has a share of its own');
    check($sameRights, '… with the same recipients, rights and filters');
    check($emptyChat, '… and an empty chat');
    check($joint, '„gemeinsam bearbeiten" is on for each');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_shared_looks WHERE user_id = $ben AND color = '#123456'")->fetchColumn() === 5,
        "Ben's own colour for the heading reaches every list (and the catch list)");

    echo "Points, leftovers, auto lists:\n";
    $filed = static fn(int $p): array => array_map('intval', $pdo->query("SELECT list_id FROM map_point_lists WHERE point_id = $p")->fetchAll(PDO::FETCH_COLUMN));
    $own = static fn(int $p): array => array_values(array_diff($filed($p), [$auto]));   // the auto list files them too
    check($own($boletus) === [$pilze] && $own($wiesenPunkt) === [$wiese], 'points stay where they lay');
    $catch = (int) $pdo->query("SELECT id FROM map_lists WHERE user_id = $anna AND name = 'PRUEF Gebiete' AND deleted_at IS NULL")->fetchColumn();
    check($catch > 0 && $own($altPunkt) === [$catch] && (int) $list($catch)['folder_id'] === $fGebiete,
        'the leftover point went into a list „Gebiete" inside the Mappe');
    check($fieldsOf($catch) === ['PRUEF Substrat', 'PRUEF Hektar'] && list_share_container($pdo, $catch) !== null,
        "the catch list carries the heading's fields and share too");
    $sources = json_decode((string) $list($auto)['filter'], true)['sources'] ?? [];
    check($sources === [$pilze, $flechten, $wiese, $wald, $brand], 'the auto list takes the lists instead of the heading');

    echo "Once only:\n";
    check(folders_convert_plan($pdo, [$anna])['users'] === [], 'nothing left to convert');
    check(folders_convert_run($pdo, [$anna]) === ['folders' => 0, 'lists' => 0, 'accounts' => 0], 'a second press does nothing');
} finally {
    $idList = implode(',', $accounts);
    foreach (['map_container_state', 'map_container_messages', 'map_container_left', 'map_container_members', 'map_container_lists'] as $table) {
        if (table_exists($pdo, $table)) {
            $pdo->exec("DELETE FROM $table WHERE container_id IN (SELECT id FROM map_containers WHERE owner_user_id IN ($idList))");
        }
    }
    $pdo->exec("DELETE FROM map_container_state WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_containers WHERE owner_user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_shared_looks WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_friends WHERE from_user_id IN ($idList) OR to_user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_point_lists WHERE point_id IN (SELECT id FROM map_points WHERE user_id IN ($idList))");
    $pdo->exec("DELETE FROM map_points WHERE user_id IN ($idList)");
    $pdo->exec("UPDATE map_lists SET parent_id = NULL WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_lists WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_list_folders WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_users WHERE id IN ($idList)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
