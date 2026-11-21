<?php
/**
 * Finds shown to a friend — includes/point_hints.php (migration 0149).
 *
 *   php tests/hints.php
 *
 * Against the demodata fixture: anna is friends with ben and carla, ben
 * and carla are not friends with each other. anna's Boletus edulis carries
 * a sequence and photos, so the delivery is measured on a real find. What
 * the test sets it takes back; the friendship it loosens for one check is
 * restored in the finally.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/point_hints.php';
require_once __DIR__ . '/../includes/point_owners.php';
require_once __DIR__ . '/../includes/container.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}
function rejected(callable $fn, ?string &$reason = null): bool
{
    try { $fn(); return false; } catch (InvalidArgumentException $e) { $reason = $e->getMessage(); return true; }
}

if (!point_hints_ready($pdo)) {
    echo "Migration 0149 is not applied — nothing to test.\n";
    exit(1);
}
$id = static fn(string $email): int => (int) $pdo->query(
    'SELECT id FROM map_users WHERE email = ' . $pdo->quote($email))->fetchColumn();
$anna = $id('anna@demo.local'); $ben = $id('ben@demo.local'); $carla = $id('carla@demo.local');
$find = (int) $pdo->query(
    "SELECT id FROM map_points WHERE user_id = $anna AND title = 'Boletus edulis' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
$benPoint = (int) $pdo->query(
    "SELECT id FROM map_points WHERE user_id = $ben AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if (!$anna || !$ben || !$carla || !$find || !$benPoint) {
    echo "The demodata fixture is missing — run php admin/demodata.php --new first.\n";
    exit(1);
}
$friendship = (int) $pdo->query(
    "SELECT id FROM map_friends WHERE status = 'accepted'
       AND ((from_user_id = $anna AND to_user_id = $carla) OR (from_user_id = $carla AND to_user_id = $anna))")->fetchColumn();

$pdo->prepare('DELETE FROM map_point_hints WHERE point_id IN (?, ?)')->execute([$find, $benPoint]);
// The chat lines this test causes are taken back by number in the finally.
require_once __DIR__ . '/../includes/chat.php';
$messagesBefore = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM map_friend_messages')->fetchColumn();

try {
    echo "Showing and taking back, one friend at a time:\n";
    check(point_hint_set($pdo, $anna, $find, $ben, true) === [$ben], 'anna shows her find to ben');
    check(point_hint_set($pdo, $anna, $find, $carla, true) === [$ben, $carla], 'and to carla');
    check(point_hint_set($pdo, $anna, $find, $carla, true) === [$ben, $carla], 'a second tick changes nothing');
    check(point_hint_set($pdo, $anna, $find, $ben, false) === [$carla], 'ben out — carla keeps it');

    if (friend_chat_kind_ready($pdo)) {
        echo "The friend hears of it in the chat:\n";
        $lines = static fn(int $friend): array => array_values(array_filter(
            friend_chat_messages($pdo, $friend, $anna, $messagesBefore)['messages'],
            static fn(array $m): bool => $m['kind'] === 'change'));
        $toCarla = $lines($carla);
        check(count($toCarla) === 1 && $toCarla[0]['user_id'] === $anna
            && str_contains($toCarla[0]['text'], 'Boletus edulis') && str_contains($toCarla[0]['text'], 'Direkt geteilt'),
            'carla finds one change line from anna: „' . ($toCarla[0]['text'] ?? '') . '"');
        check(count($lines($ben)) === 1, 'ben got his own line when anna shared with him');
        check((friend_chat_unread($pdo, $anna)[$carla]['unread'] ?? 0) === 0, "anna's own deed lights no badge of hers");
        check((friend_chat_unread($pdo, $carla)[$anna]['unread'] ?? 0) >= 1, "it lights carla's");
        point_hint_set($pdo, $anna, $find, $carla, true);
        check(count($lines($carla)) === 1, 'a second tick on carla says nothing new');
    }
    check(rejected(fn() => point_hint_set($pdo, $ben, $find, $carla, true)), "ben cannot show anna's find");
    check(rejected(fn() => point_hint_set($pdo, $ben, $benPoint, $carla, true), $r),
        'ben cannot show to carla, who is no friend of his: ' . $r);

    echo "What carla sees:\n";
    check(point_hint_reaches($pdo, $carla, $find), 'the hint reaches carla');
    check(!point_hint_reaches($pdo, $ben, $find), 'and no longer ben');
    check(point_view_scope($pdo, $carla, $find) === $anna, "the seeing gate lets carla see it — anna's picture set");
    check(point_attach_scope($pdo, $carla, $find) === null, 'but attaching stays closed: a hint hands out no right');
    $givers = point_hints_givers($pdo, $carla);
    check(count($givers) === 1 && $givers[0]['id'] === $anna && $givers[0]['count'] === 1,
        'one row „Von anna" with one find');

    $status = point_hints_bucket_status($pdo, $carla);
    $key = (string) $pdo->query("SELECT DATE_FORMAT(COALESCE(found_at, created_at), '%Y-%m') FROM map_points WHERE id = $find")->fetchColumn();
    check(($status['counts'][$key] ?? 0) === 1 && isset($status['finger'][$key]), "its month $key has a fingerprint and the count 1");
    $delivery = point_hints_bucket_points($pdo, $carla, [$key]);
    $points = $delivery['buckets'][$key]['points'] ?? [];
    $p = $points[0] ?? [];
    check(count($points) === 1 && ($p['id'] ?? 0) === $find, 'the bucket delivers the find');
    check(($p['hint_from'] ?? 0) === $anna && ($p['lists'] ?? null) === [], 'marked as shown by anna, in no list');
    $stored = count(sequences_for_points($pdo, [$find])[$find] ?? []);
    check(count($p['sequences'] ?? []) === $stored && $stored > 0,
        "with its sequences ($stored) — foreign_points_prepare hands them on now");
    check(count($p['photos'] ?? []) === count(photos_for_points($pdo, $anna, [$find])[$find] ?? []), 'and its photos');
    $leafColor = (string) $pdo->query(
        "SELECT l.color FROM map_point_lists pl JOIN map_lists l ON l.id = pl.list_id
          WHERE pl.point_id = $find AND l.user_id = $anna ORDER BY l.id LIMIT 1")->fetchColumn();
    check(($p['hint_look']['color'] ?? null) === $leafColor && ($p['hint_look']['symbol'] ?? '') !== '',
        "it wears its leaf's colour ($leafColor) and its root's shape ({$p['hint_look']['symbol']})");
    check(in_array('Substrat / Wirt', array_column($p['hint_fields'] ?? [], 'name'), true)
        || count($p['hint_fields'] ?? []) > 0, 'and brings its root\'s fields');

    $before = $status['finger'][$key];
    point_hint_set($pdo, $anna, $find, $ben, true);
    check(point_hints_bucket_status($pdo, $carla)['finger'][$key] === $before,
        "a hint to ben leaves carla's fingerprint alone");

    echo "The friendship is asked on every read:\n";
    $pdo->prepare("UPDATE map_friends SET status = 'requested' WHERE id = ?")->execute([$friendship]);
    check(!point_hint_reaches($pdo, $carla, $find) && point_hints_givers($pdo, $carla) === [],
        'with the friendship loosened carla sees nothing');
    $pdo->prepare("UPDATE map_friends SET status = 'accepted' WHERE id = ?")->execute([$friendship]);
    check(point_hint_reaches($pdo, $carla, $find), 'and with it back, the hint is there again');

    echo "The overviews:\n";
    $given = point_hints_given($pdo, $anna);
    $row = array_values(array_filter($given, fn($g) => $g['point_id'] === $find))[0] ?? null;
    $names = $row === null ? [] : array_column($row['recipients'], 'id');
    sort($names);
    check($names === [$ben, $carla], "anna's tab lists the find with both friends");
    check((point_hints_given_map($pdo, $anna)[$find] ?? []) === [$ben, $carla], 'the start data map says the same');
    $received = point_hints_received($pdo, $carla);
    check(count($received) === 1 && $received[0]['giver_id'] === $anna, "carla's tab lists it as shown by anna");

    echo "The door:\n";
    check(point_hint_leave($pdo, $carla, $find) === true, 'carla takes the hint away');
    check(!point_hint_reaches($pdo, $carla, $find) && point_hints_givers($pdo, $carla) === [], 'it is gone for her');
    check(point_hint_recipients($pdo, $anna, $find)['all'] === [$ben], 'ben still has it');
    check(point_hint_leave($pdo, $carla, $find) === false, 'a second door finds nothing');

    echo "A co-owner shares too (06.10.2026), the holder hears of it and decides:\n";
    // ben and carla become friends for this part only — the fixture has
    // them apart — and ben a co-owner of anna's find, unless he is one.
    $pdo->prepare("INSERT INTO map_friends (from_user_id, to_user_id, status, created_at) VALUES (?, ?, 'accepted', NOW())")
        ->execute([$ben, $carla]);
    $benCarla = (int) $pdo->lastInsertId();
    $benWasOwner = point_owner_is($pdo, $find, $ben);
    if (!$benWasOwner) {
        point_owner_add($pdo, $find, $ben);
    }
    point_hint_set($pdo, $anna, $find, $carla, true);
    check(rejected(fn() => point_hint_set($pdo, $ben, $find, $carla, false), $r),
        "ben, a co-owner, cannot take back what anna shared: $r");
    check(point_hint_set($pdo, $anna, $find, $carla, false) === [$ben], 'anna takes it back herself');
    check(point_hint_set($pdo, $ben, $find, $carla, true) === [$ben, $carla], 'ben, the co-owner, shares it with his friend carla');
    check(point_hint_reaches($pdo, $carla, $find), 'it reaches carla');
    $givers = point_hints_givers($pdo, $carla);
    check(count($givers) === 1 && $givers[0]['id'] === $ben, 'her row says „Von ben", who shared it');
    $mine = array_values(array_filter(point_hints_given($pdo, $anna), fn($g) => $g['point_id'] === $find))[0] ?? null;
    $byCarla = array_values(array_filter($mine['recipients'] ?? [], fn($r) => $r['id'] === $carla))[0] ?? null;
    check($byCarla !== null && $byCarla['by'] !== null, "anna's tab lists carla, shared by ben ({$byCarla['by']})");
    check(in_array($carla, point_hint_recipients($pdo, $anna, $find)['others'], true)
        && !in_array($carla, point_hint_recipients($pdo, $ben, $find)['others'], true),
        "for anna carla's row is somebody else's share, for ben it is his own");
    if (friend_chat_kind_ready($pdo)) {
        $toAnna = array_values(array_filter(
            friend_chat_messages($pdo, $anna, $ben, $messagesBefore)['messages'],
            static fn(array $m): bool => $m['kind'] === 'change' && $m['user_id'] === $ben));
        check(count($toAnna) === 1 && str_contains($toAnna[0]['text'], 'deinen Punkt'),
            'anna, the holder, finds a line from ben: „' . ($toAnna[0]['text'] ?? '') . '"');
    }
    point_owner_leave($pdo, $find, $ben);
    check(!point_hint_reaches($pdo, $carla, $find), 'ben steps out into his trash — carla no longer sees his share');
    point_owner_restore($pdo, $find, $ben);
    check(point_hint_reaches($pdo, $carla, $find), 'and back in, it is there again');
    check(point_hint_set($pdo, $anna, $find, $carla, false) === [$ben], 'anna, the holder, takes back what ben shared');

    echo "The name 'Direkt geteilt' is taken:\n";
    check(container_name_reserved('Direkt geteilt') && container_name_reserved('  direkt   GETEILT '),
        'in any case and spacing');
    check(!container_name_reserved('Direkt geteilt 2026'), 'but only the name itself');
    check(rejected(fn() => container_create($pdo, $anna, 'Direkt geteilt')), 'no Freigabe may be created under it');

    echo "A find that changes hands takes no hint along:\n";
    $speciesList = (int) $pdo->query(
        "SELECT l.id FROM map_lists l WHERE l.user_id = $anna AND l.is_species = 1
            AND l.deleted_at IS NULL ORDER BY l.id LIMIT 1")->fetchColumn();
    $handed = point_create($pdo, $anna, ['lat' => 51.5, 'lon' => 10.5, 'title' => 'PRUEF Übergabe']);
    point_lists_set($pdo, $anna, $handed, [$speciesList]);
    point_owner_add($pdo, $handed, $carla);
    point_hint_set($pdo, $anna, $handed, $ben, true);
    check(point_hint_reaches($pdo, $ben, $handed), 'anna shows ben a find carla co-owns');
    point_delete($pdo, $anna, $handed);
    check((int) $pdo->query("SELECT user_id FROM map_points WHERE id = $handed")->fetchColumn() === $carla,
        'anna trashes it — carla, the co-owner, becomes the holder');
    check(!point_hint_reaches($pdo, $ben, $handed), "ben no longer sees it through anna's hint");
    check(!in_array($handed, array_column(point_hints_given($pdo, $anna), 'point_id'), true)
        && (int) $pdo->query("SELECT COUNT(*) FROM map_point_hints WHERE point_id = $handed")->fetchColumn() === 0,
        "anna's tab no longer lists it, and the row is gone");
} finally {
    $pdo->prepare('DELETE FROM map_friend_messages WHERE id > ?')->execute([$messagesBefore]);
    if (isset($benCarla) && $benCarla > 0) {
        $pdo->prepare('DELETE FROM map_friends WHERE id = ?')->execute([$benCarla]);
    }
    if (isset($benWasOwner) && !$benWasOwner) {
        $pdo->prepare('DELETE FROM map_point_owners WHERE point_id = ? AND user_id = ?')->execute([$find, $ben]);
    }
    if (isset($handed)) {
        $pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$handed]);
    }
    $pdo->prepare('DELETE FROM map_point_hints WHERE point_id IN (?, ?)')->execute([$find, $benPoint]);
    if ($friendship) {
        $pdo->prepare("UPDATE map_friends SET status = 'accepted' WHERE id = ?")->execute([$friendship]);
    }
}

echo $failed === 0 ? "All good.\n" : "$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
