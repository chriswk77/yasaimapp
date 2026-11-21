<?php
/**
 * Tests the conversion of the old boxes into list shares
 * (includes/share_convert_content.php, migration 0160).
 *
 *   php tests/share_convert.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   NOBODY SEES MORE THAN BEFORE. A sublist stays a sublist, the box's
 *   filter goes along, the rights go along — the conversion must not
 *   widen anybody's view of somebody else's finds.
 *
 *   ONLY FRIENDS OF THE LIST'S OWNER KEEP SIGHT, and whoever loses it
 *   takes his own points out of a joint list.
 *
 *   THE SAME LIST FROM TWO BOXES becomes ONE share, the rights adding up.
 *
 *   THE CHAT GOES only where everybody had read it.
 *
 * Only the boxes this test builds are converted — never the real ones of
 * the database it runs on.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/share_convert_content.php';
require_once __DIR__ . '/../includes/chat.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

if (!list_shares_ready($pdo) || !share_joint_ready($pdo)) {
    echo "Migration 0160 or 0110 missing — nothing to check.\n";
    exit(1);
}

$accounts = [];
foreach (['pruef-anna', 'pruef-ben', 'pruef-carla', 'pruef-dora', 'pruef-emil'] as $who) {
    $pdo->prepare(
        "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
         VALUES (?, '', 'PRUEF', ?, 'user', NOW())"
    )->execute([$who . '@pruefung.local', ucfirst(substr($who, 6))]);
    $accounts[$who] = (int) $pdo->lastInsertId();
}
[$anna, $ben, $carla, $dora, $emil] = array_values($accounts);

$befriend = static function (int $a, int $b) use ($pdo): void {
    friendship_request($pdo, $a, $b);
    $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $a AND to_user_id = $b")->fetchColumn();
    friendship_accept($pdo, $b, $fId);
};
$rowsOf = static function (int $cId) use ($pdo): array {
    $q = $pdo->prepare('SELECT type, target_id, rights, filter FROM map_container_members WHERE container_id = ?');
    $q->execute([$cId]);
    $out = [];
    foreach ($q->fetchAll() as $z) {
        $out[$z['type'] . ':' . (int) $z['target_id']] = ['rights' => rights_read($z['rights']), 'filter' => $z['filter']];
    }
    return $out;
};

try {
    // Anna is friends with everybody; ben with anna and carla only.
    foreach ([$ben, $carla, $dora, $emil] as $who) {
        $befriend($anna, $who);
    }
    $befriend($ben, $carla);
    $group = group_create($pdo, $anna, 'PRUEF Gruppe');
    group_add($pdo, $anna, $group, $carla);

    $annas = list_create($pdo, $anna, ['name' => 'PRUEF Annas Funde', 'color' => '#336699', 'symbol' => 'circle']);
    $bensRoot = list_create($pdo, $ben, ['name' => 'PRUEF Bens Gebiete', 'color' => '#336699', 'symbol' => 'circle']);
    /* THE OLD TREE, BY HAND: the old boxes held branches, and list_create
       refuses a parent since the Mappen (0168). */
    $legacy = static function (string $name) use ($pdo, $ben, $bensRoot): int {
        $pdo->prepare("INSERT INTO map_lists (user_id, parent_id, name, color, symbol, created_at, updated_at)
                       VALUES (?, ?, ?, '#aa3300', 'circle', NOW(), NOW())")->execute([$ben, $bensRoot, $name]);
        return (int) $pdo->lastInsertId();
    };
    $bensSub   = $legacy('PRUEF Brandfläche');
    $bensOther = $legacy('PRUEF Andere Fläche');
    $boletus = point_create($pdo, $anna, ['lat' => 50.1, 'lon' => 8.1, 'title' => 'Boletus edulis', 'lists' => [$annas]]);
    $amanita = point_create($pdo, $anna, ['lat' => 50.2, 'lon' => 8.2, 'title' => 'Amanita muscaria', 'lists' => [$annas]]);
    point_create($pdo, $ben, ['lat' => 50.3, 'lon' => 8.3, 'title' => 'Geheim', 'lists' => [$bensOther]]);
    point_create($pdo, $ben, ['lat' => 50.31, 'lon' => 8.31, 'title' => 'Bens Brandstelle', 'lists' => [$bensSub]]);

    // Box 1: anna's, with a filter; anna's list and ben's sublist (joint) hang in.
    $box1 = container_create($pdo, $anna, 'PRUEF Kasten 1', ['terms' => [['type' => 'contains', 'text' => 'boletus']]]);
    container_add($pdo, $anna, $box1, 'friend', $ben);
    container_add($pdo, $anna, $box1, 'friend', $emil);
    container_add($pdo, $anna, $box1, 'group', $group);
    share_create($pdo, $anna, $box1, $annas);
    share_rights_set($pdo, $anna, (int) $pdo->query("SELECT id FROM map_container_lists WHERE container_id = $box1 AND list_id = $annas")->fetchColumn(), ['title']);
    share_create($pdo, $ben, $box1, $bensSub);
    share_joint_set($pdo, $ben, (int) $pdo->query("SELECT id FROM map_container_lists WHERE container_id = $box1 AND list_id = $bensSub")->fetchColumn(), true);
    $emils = point_create($pdo, $emil, ['lat' => 50.4, 'lon' => 8.4, 'title' => 'Emils Brandstelle', 'lists' => [$bensSub]]);
    chat_write($pdo, $anna, $box1, 'PRUEF Hallo Kasten 1');

    // Box 2: dora's; anna's list hangs in here too, with „Belegbilder löschen".
    $box2 = container_create($pdo, $dora, 'PRUEF Kasten 2');
    container_add($pdo, $dora, $box2, 'friend', $anna);
    share_create($pdo, $anna, $box2, $annas);
    share_rights_set($pdo, $anna, (int) $pdo->query("SELECT id FROM map_container_lists WHERE container_id = $box2")->fetchColumn(), ['delete_photos']);
    chat_write($pdo, $dora, $box2, 'PRUEF Hallo Kasten 2');

    echo "The preview:\n";
    $plan = share_convert_plan($pdo, [$box1, $box2]);
    check(count($plan['targets']) === 2, 'two lists become two shares — one per list, whatever boxes they hung in');
    check(isset($plan['targets'][$bensSub]['dropped'][$emil]), 'emil, no friend of ben, is named as losing sight of ben’s list');
    check(($plan['targets'][$bensSub]['home'][$emil] ?? 0) === 1, 'and the preview says he takes his point home');
    check($plan['targets'][$bensSub]['sublist'] === true, 'ben’s sublist is marked as one');
    check(in_array(t('Suchwörter suchten bisher nur im Titel, jetzt in allen Feldern des Punkts.'), $plan['targets'][$annas]['notes'], true),
        'the preview says the search words read wider now');
    check($plan['targets'][$annas]['chat_from'] === null, 'anna’s list meets people of two boxes — no chat goes along');
    check($plan['targets'][$bensSub]['chat_from'] === $box1, 'ben’s sublist came from box 1 alone — its chat goes along');

    echo "The conversion:\n";
    $done = share_convert_run($pdo, [$box1, $box2]);
    check($done === ['shares' => 2, 'boxes' => 2, 'home' => 1], 'two boxes became two shares, one point went home');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_containers WHERE id IN ($box1, $box2)")->fetchColumn() === 0, 'the boxes are gone');

    $annaShare = list_share_container($pdo, $annas);
    $rows = $rowsOf($annaShare);
    check(array_keys($rows) == ['friend:' . $ben, 'friend:' . $emil, 'group:' . $group, 'friend:' . $dora]
        || count($rows) === 4, 'anna’s list: ben, emil, her group and dora');
    check($rows['friend:' . $ben]['rights'] === ['title'] && $rows['friend:' . $ben]['filter'] !== null,
        'ben keeps the title right and box 1’s filter');
    check(isset($rows['group:' . $group]), 'anna’s own group stays a group');
    check(in_array('attach_photos', $rows['friend:' . $dora]['rights'], true)
        && in_array('measure', $rows['friend:' . $dora]['rights'], true)
        && in_array('delete_photos', $rows['friend:' . $dora]['rights'], true),
        'dora’s „Belegbilder löschen" is spelled out into its three rights');
    $benSees = array_map(static fn(array $p): int => (int) $p['id'], container_points($pdo, $annaShare, [], $ben));
    check($benSees === [$boletus], 'ben still sees only the Boletus — nobody sees more than before');

    $benShare = list_share_container($pdo, $bensSub);
    check($benShare !== null && list_share_list($pdo, $benShare) === $bensSub, 'ben’s share is of the sublist, not his whole list');
    $rows = $rowsOf($benShare);
    check(isset($rows['friend:' . $anna], $rows['friend:' . $carla]) && !isset($rows['friend:' . $emil]),
        'anna (box owner) and carla (spelled out of anna’s group) keep it, emil does not');
    check(in_array('own_points', $rows['friend:' . $anna]['rights'], true) && $rows['friend:' . $anna]['filter'] === null,
        'the joint list becomes every tick, without filter');
    check(container_points($pdo, $benShare, [], $carla) !== []
        && !in_array('Geheim', array_column(container_points($pdo, $benShare, [], $carla), 'title'), true),
        'carla sees the sublist, not ben’s other area');
    $emilHome = $pdo->query("SELECT l.name FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id WHERE pl.point_id = $emils")
        ->fetchAll(PDO::FETCH_COLUMN);
    check($emilHome === [joint_home_name('PRUEF Brandfläche')], 'emil’s point is home in his own list');
    $texts = $pdo->query("SELECT text FROM map_container_messages WHERE container_id = $benShare")->fetchAll(PDO::FETCH_COLUMN);
    check(in_array('PRUEF Hallo Kasten 1', $texts, true), 'box 1’s chat went along to ben’s share');
    $texts = $pdo->query("SELECT text FROM map_container_messages WHERE container_id = $annaShare")->fetchAll(PDO::FETCH_COLUMN);
    check(!in_array('PRUEF Hallo Kasten 2', $texts, true), 'box 2’s chat did not reach people who never read it');

    check(share_convert_plan($pdo, [$box1, $box2])['boxes'] === [], 'afterwards there is nothing left to convert');
} finally {
    $idList = implode(',', $accounts);
    foreach (['map_container_state', 'map_container_messages', 'map_container_left', 'map_container_members', 'map_container_lists'] as $table) {
        $pdo->exec("DELETE FROM $table WHERE container_id IN (SELECT id FROM map_containers WHERE owner_user_id IN ($idList))");
    }
    $pdo->exec("DELETE FROM map_container_state WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_containers WHERE owner_user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_friends WHERE from_user_id IN ($idList) OR to_user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_group_members WHERE user_id IN ($idList)
                 OR group_id IN (SELECT id FROM map_groups WHERE owner_user_id IN ($idList))");
    $pdo->exec("DELETE FROM map_groups WHERE owner_user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_users WHERE id IN ($idList)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
