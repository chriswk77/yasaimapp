<?php
/**
 * Tests friendships and work groups (stage 1 of sharing).
 *
 *   php tests/friends.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE SEARCH. Requests go through name suggestions (user_search).
 *   It must find fragments, stay silent about myself and blocked users,
 *   and say at every hit where you stand — 'free', 'open', 'friend'.
 *
 *   THE DIRECTION. As long as a request is open, only the one asked may
 *   accept — otherwise the requester would declare the friendship himself.
 *
 *   THE COUNTER-PAIR. The UNIQUE in the table only secures A→B; that B→A
 *   does not come into being as a second row is held by the code alone.
 *   If it fell, there would be friendships that are half ended.
 *
 *   FOREIGN GROUPS. Only the owner may add, and only friends — otherwise
 *   you would put strangers into groups that later get to see lists.
 *
 *   THE END CARRIES THROUGH: whoever ends a friendship flies out of the
 *   other's work groups and vice versa. If the membership remained, it
 *   would keep alive exactly the connection that was just ended.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/friends.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** Calls and says whether it was rejected. ?string as in sublists.php. */
function rejected(callable $do, ?string &$reason = null): bool
{
    try {
        $do();
        return false;
    } catch (InvalidArgumentException $e) {
        $reason = $e->getMessage();
        return true;
    }
}

if (!friends_ready($pdo)) {
    echo "Migration 0041 is missing — nothing to check.\n";
    exit(1);
}

// Three test accounts. The PRUEF prefix keeps them away from real
// accounts — important also for the search tests, which run over the
// whole installation.
$accounts = [];
foreach (['pruef-anna', 'pruef-ben', 'pruef-carla'] as $who) {
    $pdo->prepare(
        "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
         VALUES (?, '', 'PRUEF', ?, 'user', NOW())"
    )->execute([$who . '@pruefung.local', ucfirst(substr($who, 6))]);
    $accounts[$who] = (int) $pdo->lastInsertId();
}
[$anna, $ben, $carla] = array_values($accounts);

try {
    echo "Searching:\n";
    $matches = user_search($pdo, $carla, 'uef ben');
    check(count($matches) === 1 && $matches[0]['id'] === $ben && $matches[0]['state'] === 'free',
        'a fragment across first and last name finds exactly the right one, as requestable — case does not matter');
    check(array_column(user_search($pdo, $carla, 'ruef-ben'), 'id') === [$ben],
        'the email address is searched too');
    check(user_search($pdo, $ben, 'ruef-ben') === [],
        'you do not find yourself');
    check(user_search($pdo, $anna, 'x') === [],
        'below two characters no search happens');

    echo "Requests:\n";
    check(rejected(fn() => friendship_request($pdo, $anna, $anna)),
        'requesting yourself is rejected');
    check(rejected(fn() => friendship_request($pdo, $anna, 999999999)),
        'an unknown id is rejected');

    $who = friendship_request($pdo, $anna, $ben);
    check((int) $who['id'] === $ben, 'the request returns the one asked');
    check(friends_pending($pdo, $ben) === 1, 'the request counts as open for the one asked');
    check(friends_pending($pdo, $anna) === 0, 'for the requester it does not count');

    check(rejected(fn() => friendship_request($pdo, $anna, $ben)),
        'the same request again is rejected');
    check(rejected(fn() => friendship_request($pdo, $ben, $anna)),
        'the opposite direction is rejected as long as the request is open');
    check((user_search($pdo, $ben, 'ruef-anna')[0]['state'] ?? '') === 'open',
        'the search shows the open request on the counterpart too');

    $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $ben")->fetchColumn();

    echo "Accepting:\n";
    check(rejected(fn() => friendship_accept($pdo, $anna, $fId)),
        'the requester cannot accept himself');
    check(rejected(fn() => friendship_accept($pdo, $carla, $fId)),
        'a third party cannot accept');
    friendship_accept($pdo, $ben, $fId);
    check(friend_ids($pdo, $anna) === [$ben] && friend_ids($pdo, $ben) === [$anna],
        'after accepting both sides see the same friendship');
    check(rejected(fn() => friendship_request($pdo, $ben, $anna)),
        'requesting friends again is rejected');
    check((user_search($pdo, $anna, 'ruef-ben')[0]['state'] ?? '') === 'friend',
        'the search shows the friendship on the hit');

    /* THE ANSWER REACHES THE ASKER (13.09.2026, migration 0105): the
       acceptance stands as news for anna until she takes note; ben,
       who answered, is told nothing he did not do himself. */
    if (column_exists($pdo, 'map_friends', 'seen_at')) {
        $news = friends_accepted_unseen($pdo, $anna);
        check(count($news) === 1 && $news[0]['id'] === $fId && $news[0]['name'] !== '',
            'the asker is told of the acceptance, by name');
        check(friends_accepted_unseen($pdo, $ben) === [],
            'the one who accepted is not');
        friendship_seen($pdo, $ben, $fId);
        check(count(friends_accepted_unseen($pdo, $anna)) === 1,
            'only the asker can take note of it');
        friendship_seen($pdo, $anna, $fId);
        check(friends_accepted_unseen($pdo, $anna) === [],
            'taken note, the news is gone');
    }

    echo "Groups:\n";
    $group = group_create($pdo, $anna, 'PRUEF Nordhang');
    check(rejected(fn() => group_create($pdo, $anna, 'PRUEF Nordhang')),
        'the same group name for the same owner is rejected');
    check(rejected(fn() => group_add($pdo, $anna, $group, $carla)),
        'whoever is no friend does not get into the group');
    check(rejected(fn() => group_add($pdo, $ben, $group, $ben)),
        "you do not add anyone to someone else's group");
    group_add($pdo, $anna, $group, $ben);
    group_add($pdo, $anna, $group, $ben); // twice is just a double click
    $members = groups_overview($pdo, $anna)['own'][0]['members'];
    check(count($members) === 1 && (int) $members[0]['id'] === $ben,
        'adding a friend works, twice stays single');
    $atBen = groups_overview($pdo, $ben)['member_of'];
    check(count($atBen) === 1, 'the member sees the group on his side');
    check($atBen[0]['owner_name'] === 'PRUEF Anna'
          && array_column($atBen[0]['members'], 'id') === [$ben],
        'and also who is in it — you share with the group, so it should be visible who reads along');

    check(rejected(fn() => group_remove($pdo, $carla, $group, $ben)),
        'a third party cannot remove anyone');
    group_remove($pdo, $ben, $group, $ben);
    check(groups_overview($pdo, $anna)['own'][0]['members'] === [],
        'the member itself may leave');

    echo "The end carries through:\n";
    group_add($pdo, $anna, $group, $ben);
    $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $ben")->fetchColumn();
    friendship_remove($pdo, $ben, $fId);
    check(friend_ids($pdo, $anna) === [], 'the friendship is ended');
    check(groups_overview($pdo, $ben)['member_of'] === [],
        'with the friendship the group membership ends');

    check(rejected(fn() => group_delete($pdo, $ben, $group)),
        "you do not delete someone else's group");
    group_delete($pdo, $anna, $group);
    check(groups_overview($pdo, $anna)['own'] === [], "one's own group is deleted");

    echo "Blocked users:\n";
    $pdo->exec("UPDATE map_users SET locked_at = NOW() WHERE id = $carla");
    check(user_search($pdo, $anna, 'ruef-carla') === [],
        'blocked users do not show up in the search');
    check(rejected(fn() => friendship_request($pdo, $anna, $carla)),
        'blocked users cannot be requested — not even with a stale id');
} finally {
    $ids = implode(',', $accounts);
    $pdo->exec("DELETE FROM map_friends WHERE from_user_id IN ($ids) OR to_user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_group_members WHERE user_id IN ($ids)
                 OR group_id IN (SELECT id FROM map_groups WHERE owner_user_id IN ($ids))");
    $pdo->exec("DELETE FROM map_groups WHERE owner_user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_users WHERE id IN ($ids)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
