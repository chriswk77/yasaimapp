<?php
/**
 * Tests „Listen teilen" — one share per list, rights and filter per
 * recipient (migration 0160, includes/list_shares.php).
 *
 *   php tests/list_recipients.php    (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   ONLY THE OWNER SHARES, ONLY HIS OWN LIST, ONLY WITH FRIENDS AND OWN
 *   GROUPS. If that falls, somebody hangs a list into a circle its owner
 *   never chose — the very chaos the redesign ends.
 *
 *   THE FILTER IS EVALUATED ON THE SERVER, per recipient: a point he may
 *   not see must not reach his device — not in the delivery, not in a
 *   bucket, not through the single access.
 *
 *   RIGHTS ARE EXACTLY THE TICKS, and two rows reaching one person add up.
 *
 *   WORKING IN THE LIST (`own_points`): he may file into it, he is in its
 *   chat, and when he stops — tick gone or removed — his points come home
 *   to him. Points always stay their maker's.
 *
 *   THE HEIR is whoever has worked in the list longest; the share follows
 *   the list, everybody else stays, the chat says it, the heir is told.
 *
 *   THE OLD DOORS REFUSE a list share — hanging in, taking people in.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/list_shares.php';
require_once __DIR__ . '/../includes/joint_lists.php';
require_once __DIR__ . '/../includes/point_owners.php';
require_once __DIR__ . '/../includes/chat.php';
require_once __DIR__ . '/../includes/bucket.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

function rejected(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
}

if (!list_shares_ready($pdo)) {
    echo "Migration 0160 missing — nothing to check.\n";
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

$ids = static fn(array $points): array => array_map(static fn(array $p): int => (int) $p['id'], $points);

try {
    // Anna is friends with ben, carla, dora; emil is a stranger to her.
    foreach ([$ben, $carla, $dora] as $who) {
        friendship_request($pdo, $anna, $who);
        $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $who")->fetchColumn();
        friendship_accept($pdo, $who, $fId);
    }
    $group = group_create($pdo, $anna, 'PRUEF Kartierung');
    group_add($pdo, $anna, $group, $carla);
    $bensGroup = group_create($pdo, $ben, 'PRUEF Bens Gruppe');

    // ONE LIST SINCE THE MAPPEN (0168) — $root and $leaf name the same one,
    // so the checks below read as they did when it was a tree.
    $root = list_create($pdo, $anna, ['name' => 'PRUEF Pilze', 'color' => '#996633', 'symbol' => 'circle']);
    $leaf = $root;
    $pdo->prepare('INSERT INTO map_list_fields (list_id, name, type, is_species, sort_order) VALUES (?, ?, ?, 0, 0)')
        ->execute([$root, 'PRUEF Substrat', 'text']);

    $boletus = point_create($pdo, $anna, ['lat' => 50.1, 'lon' => 8.1, 'title' => 'Boletus edulis', 'lists' => [$leaf]]);
    $amanita = point_create($pdo, $anna, ['lat' => 50.2, 'lon' => 8.2, 'title' => 'Amanita muscaria', 'lists' => [$leaf]]);
    $cf      = point_create($pdo, $anna, ['lat' => 50.3, 'lon' => 8.3, 'title' => 'Russula cf. vesca', 'lists' => [$leaf]]);

    echo "Who may share what:\n";
    check(rejected(fn() => list_share_save($pdo, $ben, $root, [['type' => 'friend', 'id' => $anna]])),
        "somebody else's list cannot be shared");
    check(rejected(fn() => list_share_save($pdo, $anna, $root, [['type' => 'friend', 'id' => $emil]])),
        'only with friends');
    check(rejected(fn() => list_share_save($pdo, $anna, $root, [['type' => 'group', 'id' => $bensGroup]])),
        "only with one's own working groups");
    check(rejected(fn() => list_share_save($pdo, $anna, $root, [['type' => 'friend', 'id' => $ben, 'rights' => ['fly']]])),
        'an unknown right fails loudly');

    $saved = list_share_save($pdo, $anna, $root, [
        ['type' => 'friend', 'id' => $ben],
        ['type' => 'group', 'id' => $group, 'rights' => ['title', 'field:PRUEF Substrat'],
         'filter' => ['terms' => [['type' => 'contains', 'text' => 'boletus']]]],
    ]);
    $cId = (int) $saved['container'];
    check($cId > 0 && list_share_container($pdo, $root) === $cId, 'the first recipient brings the share into being');
    check(list_share_list($pdo, $cId) === $root, 'the share holds exactly the list');
    $members = container_member_ids($pdo, $cId);
    sort($members);
    check($members === [$anna, $ben, $carla], 'owner, friend and group member are its members');

    echo "The old doors refuse:\n";
    check(rejected(fn() => container_add($pdo, $anna, $cId, 'friend', $dora)), 'no taking in through the box door');
    $own = list_create($pdo, $anna, ['name' => 'PRUEF Andere', 'color' => '#111111', 'symbol' => 'circle']);
    check(rejected(fn() => share_create($pdo, $anna, $cId, $own)), 'no second list hung in');
    check(rejected(fn() => share_create($pdo, $ben, $cId, $own)), "nobody else's list hung in");

    echo "The filter, on the server:\n";
    check($ids(container_points($pdo, $cId, [], $ben)) == [$cf, $amanita, $boletus]
        || count(container_points($pdo, $cId, [], $ben)) === 3, 'ben (no filter) gets every point');
    $carlaGets = $ids(container_points($pdo, $cId, [], $carla));
    check($carlaGets === [$boletus], 'carla (filter „boletus") gets only the Boletus');
    check(point_foreign_rights($pdo, $carla, $amanita) === null, 'the single access refuses her the Amanita too');
    $bucket = container_bucket_status($pdo, $cId, $carla);
    check($bucket['counts'] === [], 'a filtered viewer gets no bucket counts that would lie');
    $bucketBen = container_bucket_status($pdo, $cId, $ben);
    check(array_sum($bucketBen['counts']) === 3, 'an unfiltered one gets them');

    echo "Rights are the ticks:\n";
    $benRights = point_foreign_rights($pdo, $ben, $boletus);
    check($benRights !== null && $benRights['rights'] === [], 'ben may only look');
    check(rejected(fn() => foreign_input_sift($pdo, $boletus, $benRights['rights'], ['title' => 'x'])),
        'his title change is refused');
    $carlaRights = point_foreign_rights($pdo, $carla, $boletus);
    check(in_array('title', $carlaRights['rights'], true) && in_array('field:PRUEF Substrat', $carlaRights['rights'], true),
        'carla may change the title and the field');
    check(empty($carlaRights['joint']), 'a list share is never written as the holder');
    check(point_attach_scope($pdo, $carla, $boletus) === null, 'without attach_photos no picture');

    // Ben joins the group too — the two rows add up.
    group_add($pdo, $anna, $group, $ben);
    $benRights = point_foreign_rights($pdo, $ben, $amanita);
    check($benRights !== null, 'his friend row still shows him every point');
    check(in_array('title', $benRights['rights'], true), 'and the group row adds its rights');
    group_remove($pdo, $anna, $group, $ben);

    echo "Identification filter:\n";
    list_share_save($pdo, $anna, $root, [
        ['type' => 'friend', 'id' => $ben],
        ['type' => 'friend', 'id' => $dora, 'filter' => ['identification' => ['open' => true]]],
        ['type' => 'group', 'id' => $group, 'rights' => ['title', 'field:PRUEF Substrat'],
         'filter' => ['terms' => [['type' => 'contains', 'text' => 'boletus']]]],
    ]);
    check($ids(container_points($pdo, $cId, [], $dora)) === [$cf], 'dora („nicht bestimmt") gets only the cf.');
    $sstr = point_create($pdo, $anna, ['lat' => 50.4, 'lon' => 8.4, 'title' => 'Russula vesca s. str.', 'lists' => [$leaf]]);
    check(!in_array($sstr, $ids(container_points($pdo, $cId, [], $dora)), true), 's. str. is no open mark');

    echo "Working in the list:\n";
    list_share_save($pdo, $anna, $root, [
        ['type' => 'friend', 'id' => $ben, 'rights' => ['own_points']],
        ['type' => 'friend', 'id' => $dora, 'rights' => ['own_points', 'delete'], 'filter' => ['identification' => ['open' => true]]],
        ['type' => 'group', 'id' => $group, 'rights' => ['attach_photos'],
         'filter' => ['terms' => [['type' => 'contains', 'text' => 'boletus']]]],
    ]);
    check(in_array($leaf, joint_list_ids($pdo, $ben), true), 'with own_points ben may file into the list');
    check(!in_array($leaf, joint_list_ids($pdo, $carla), true), 'carla, who only looks, may not');
    $bens = point_create($pdo, $ben, ['lat' => 50.5, 'lon' => 8.5, 'title' => 'Boletus reticulatus', 'lists' => [$leaf]]);
    check((int) $pdo->query("SELECT COUNT(*) FROM map_point_lists WHERE point_id = $bens AND list_id = $leaf")->fetchColumn() === 1,
        "ben's own find lies in anna's list");
    check(in_array($bens, $ids(container_points($pdo, $cId, [], $carla)), true), "carla's filter lets ben's Boletus through");
    check(point_attach_scope($pdo, $carla, $boletus) !== null, 'attach_photos lets carla attach a picture');
    check(list_share_chat_member($pdo, $ben, $cId), 'ben works in the list — he is in its chat');
    check(list_share_chat_member($pdo, $carla, $cId), 'carla came through a group — she is in it too');
    $joint = (int) $pdo->query("SELECT joint FROM map_container_lists WHERE container_id = $cId")->fetchColumn();
    check($joint === 1, 'the share row is joint while somebody works in it');
    $participants = array_keys(list_share_participants($pdo, $cId));
    check($participants === [$anna, $ben, $dora], 'participants: owner first, then by seniority');

    // Dora (filter: only open names) never sees ben's Boletus, ben has no delete.
    $shares = point_joint_shares($pdo, $bens, $ben);
    check(isset($shares[$cId]), "ben reaches his own find through the share's list");

    echo "Stopping takes one's points home:\n";
    list_share_save($pdo, $anna, $root, [
        ['type' => 'friend', 'id' => $ben],
        ['type' => 'friend', 'id' => $dora, 'rights' => ['own_points', 'delete'], 'filter' => ['identification' => ['open' => true]]],
        ['type' => 'group', 'id' => $group, 'rights' => ['attach_photos'],
         'filter' => ['terms' => [['type' => 'contains', 'text' => 'boletus']]]],
    ]);
    $home = $pdo->query("SELECT l.name FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id WHERE pl.point_id = $bens")
        ->fetchAll(PDO::FETCH_COLUMN);
    check($home === [joint_home_name('PRUEF Pilze')], "without the tick ben's find went home into his own list");
    check(!list_share_chat_member($pdo, $ben, $cId), 'and he, who only looks now, is out of the chat');

    echo "Looks per viewer:\n";
    shared_look_set($pdo, $ben, $leaf, '#00ff00', 'flag');
    $look = null;
    foreach (container_shares($pdo, $cId, $ben) as $f) {
        foreach ($f['lists'] as $l) {
            if ($l['id'] === $leaf) { $look = $l['look'] ?? null; }
        }
    }
    check($look === ['color' => '#00ff00', 'symbol' => 'flag'], 'ben sees the colour and shape he chose');
    check(rejected(fn() => shared_look_set($pdo, $ben, $leaf, 'green', null)), 'only real colours');

    echo "The heir:\n";
    // ben works in it again (taken in earlier than dora's tick? no — the
    // row date counts: ben's row is older, so ben inherits).
    list_share_save($pdo, $anna, $root, [
        ['type' => 'friend', 'id' => $ben, 'rights' => ['own_points']],
        ['type' => 'friend', 'id' => $dora, 'rights' => ['own_points'], 'filter' => ['identification' => ['open' => true]]],
        ['type' => 'group', 'id' => $group, 'rights' => ['attach_photos']],
    ]);
    $pdo->prepare("UPDATE map_container_members SET added_at = '2026-01-01 10:00:00' WHERE container_id = ? AND type = 'friend' AND target_id = ?")
        ->execute([$cId, $ben]);
    $handed = list_delete($pdo, $anna, $root);
    check(($handed['handed_over']['to'] ?? null) === $ben, 'deleting the list hands it to whoever worked in it longest');
    check((int) $pdo->query("SELECT owner_user_id FROM map_containers WHERE id = $cId")->fetchColumn() === $ben,
        'the share follows the list to ben');
    check((int) $pdo->query("SELECT user_id FROM map_lists WHERE id = $root")->fetchColumn() === $ben, 'the list is his');
    $members = container_member_ids($pdo, $cId);
    sort($members);
    check(in_array($dora, $members, true), 'dora stays, though she is no friend of ben');
    check(in_array($carla, $members, true), "carla stays, though anna's group is gone from the share");
    check(!in_array($anna, $members, true), 'anna is out');
    $annaHome = $pdo->query("SELECT l.name FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id WHERE pl.point_id = $boletus")
        ->fetchAll(PDO::FETCH_COLUMN);
    check($annaHome === [joint_home_name('PRUEF Pilze')], "anna's finds went home with her");
    check(array_column(list_share_admin_notices($pdo, $ben), 'list') === [$root], 'ben gets the administrator window');
    list_share_admin_notice_seen($pdo, $ben, $cId);
    check(list_share_admin_notices($pdo, $ben) === [], 'once seen, it is gone');
    $line = (string) $pdo->query("SELECT text FROM map_container_messages WHERE container_id = $cId ORDER BY id DESC LIMIT 1")->fetchColumn();
    check(str_contains($line, 'Listenadministrator') && str_contains($line, 'Ben'), 'the chat says who the administrator is now');

    echo "Ending:\n";
    $result = list_share_save($pdo, $ben, $root, []);
    check($result['container'] === null && list_share_container($pdo, $root) === null, 'without recipients the share is gone');
    $doraLeft = (int) $pdo->query("SELECT COUNT(*) FROM map_container_members WHERE container_id = $cId")->fetchColumn();
    check($doraLeft === 0, 'and its rows with it');
} finally {
    $idList = implode(',', $accounts);
    foreach (['map_container_state', 'map_container_messages', 'map_container_left'] as $table) {
        if (table_exists($pdo, $table)) {
            $pdo->exec("DELETE FROM $table WHERE container_id IN (SELECT id FROM map_containers WHERE owner_user_id IN ($idList))");
        }
    }
    $pdo->exec("DELETE FROM map_container_state WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_container_lists WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_container_members WHERE container_id IN (SELECT id FROM map_containers WHERE owner_user_id IN ($idList))");
    $pdo->exec("DELETE FROM map_containers WHERE owner_user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_shared_looks WHERE user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_friends WHERE from_user_id IN ($idList) OR to_user_id IN ($idList)");
    $pdo->exec("DELETE FROM map_group_members WHERE user_id IN ($idList)
                 OR group_id IN (SELECT id FROM map_groups WHERE owner_user_id IN ($idList))");
    $pdo->exec("DELETE FROM map_groups WHERE owner_user_id IN ($idList)");
    if (table_exists($pdo, 'map_list_trash')) {
        $pdo->exec("DELETE FROM map_list_trash WHERE removed_by IN ($idList)");
    }
    $pdo->exec("DELETE FROM map_users WHERE id IN ($idList)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
