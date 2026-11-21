<?php
/**
 * Tests the personal chat between friends (migration 0068).
 *
 *   php tests/friend_chat.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE ROOM. friend_chat_room() is the whole access decision: accepted
 *   friends get the row, strangers and open requests get null — and every
 *   read and write runs through it. If it leaks, everything leaks.
 *
 *   BOTH DIRECTIONS OF UNREAD. The counts are keyed by the FRIEND's user
 *   id and count only the OTHER side's messages — one's own must never
 *   light one's own dot (the writer marks his own message read).
 *
 *   THE READ MARK ONLY MOVES FORWARD — two devices would otherwise push
 *   each other back, the same trap the container chat guards against.
 *
 *   THE END CARRIES THROUGH. friendship_remove() takes the conversation
 *   with it; a new friendship between the same two starts empty. This is
 *   the one behavior that distinguishes this chat from the container's,
 *   so it gets its own check rather than trust.
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
require_once __DIR__ . '/../includes/chat.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

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
if (!friend_chat_ready($pdo)) {
    echo "Migration 0068 is missing — nothing to check.\n";
    exit(1);
}

// Three accounts: two friends and one bystander. The PRUEF prefix keeps
// them away from real accounts, as everywhere.
$accounts = [];
foreach (['pruef-chat-anna', 'pruef-chat-ben', 'pruef-chat-carla'] as $who) {
    $pdo->prepare(
        "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
         VALUES (?, '', 'PRUEF', ?, 'user', NOW())"
    )->execute([$who . '@pruefung.local', ucfirst(substr($who, 11))]);
    $accounts[$who] = (int) $pdo->lastInsertId();
}
[$anna, $ben, $carla] = array_values($accounts);

try {
    echo "The room:\n";
    check(friend_chat_room($pdo, $anna, $ben) === null,
        'without any friendship there is no room');

    friendship_request($pdo, $anna, $ben);
    check(friend_chat_room($pdo, $anna, $ben) === null,
        'an open request is no room yet — writing would reach somebody who has not said yes');
    check(rejected(fn() => friend_chat_write($pdo, $anna, $ben, 'Hallo?')),
        'and writing into it is rejected');

    $fId = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $anna AND to_user_id = $ben")->fetchColumn();
    friendship_accept($pdo, $ben, $fId);

    check(friend_chat_room($pdo, $anna, $ben) === $fId
        && friend_chat_room($pdo, $ben, $anna) === $fId,
        'accepted, both sides get the same room');
    check(friend_chat_room($pdo, $carla, $anna) === null,
        'the bystander gets none');
    check(friend_chat_room($pdo, $anna, $anna) === null,
        'and oneself is not a room either');

    echo "Writing and reading:\n";
    $m1 = friend_chat_write($pdo, $anna, $ben, "Samstag am Nordhang?");
    check($m1['user_id'] === $anna && $m1['text'] === 'Samstag am Nordhang?',
        'the message comes back in the delivery shape');

    $m2 = friend_chat_write($pdo, $ben, $anna, 'Bin dabei.');
    $conversation = friend_chat_messages($pdo, $anna, $ben);
    check(array_column($conversation['messages'], 'id') === [$m1['id'], $m2['id']],
        'both read the same conversation, oldest first');
    check($conversation['latest'] === $m2['id'],
        'latest is the highest number in the room');

    $later = friend_chat_messages($pdo, $anna, $ben, $m1['id']);
    check(array_column($later['messages'], 'id') === [$m2['id']],
        'since only brings what is newer — the polling stays light');

    check(friend_chat_messages($pdo, $carla, $anna) === ['messages' => [], 'latest' => 0],
        'the bystander reads emptiness, not an error that confirms the room exists');
    check(rejected(fn() => friend_chat_write($pdo, $carla, $anna, 'Darf ich mit?')),
        'and cannot write');
    check(rejected(fn() => friend_chat_write($pdo, $anna, $ben, "   \n  ")),
        'a message of nothing but whitespace is empty and refused');

    echo "Unread:\n";
    // Stand: anna wrote m1, ben answered with m2. Writing marks one's
    // own read state up to one's own message — the container chat's law:
    // whoever writes is sitting in the open window. So Ben's mark
    // already covers m1, and only m2 waits for Anna.
    $annaSees = friend_chat_unread($pdo, $anna);
    check(($annaSees[$ben]['unread'] ?? 0) === 1 && ($annaSees[$ben]['latest'] ?? 0) === $m2['id'],
        "for Anna exactly Ben's answer waits — her own messages never count");
    check(friend_chat_unread($pdo, $ben) === [],
        'for Ben nothing waits: answering covered what came before, as in the container');
    check(friend_chat_unread($pdo, $carla) === [],
        'the bystander has nothing waiting anywhere');

    $long = friend_chat_write($pdo, $anna, $ben, str_repeat('x', CHAT_MAX + 500));
    check(mb_strlen($long['text']) === CHAT_MAX,
        'an overlong message is cut, not refused — same law as the container');
    check((friend_chat_unread($pdo, $ben)[$anna]['unread'] ?? 0) === 1,
        'and it waits for Ben like any other message');

    // A fresh message from the other side, so the read mark has real work:
    // Anna's own writing has covered everything older already.
    $m3 = friend_chat_write($pdo, $ben, $anna, 'Gelesen?');
    check((friend_chat_unread($pdo, $anna)[$ben]['unread'] ?? 0) === 1,
        'a new answer lights the count again');

    friend_chat_mark_read($pdo, $anna, $ben, $m3['id']);
    check(friend_chat_unread($pdo, $anna) === [],
        'reading up to the last message clears the count');

    friend_chat_mark_read($pdo, $anna, $ben, 1);
    check(friend_chat_unread($pdo, $anna) === [],
        'the read mark only moves forward — a device that has fallen behind cannot relight the dot');

    echo "The end carries through:\n";
    friendship_remove($pdo, $anna, $fId);
    check((int) $pdo->query("SELECT COUNT(*) FROM map_friend_messages WHERE friendship_id = $fId")->fetchColumn() === 0
        && (int) $pdo->query("SELECT COUNT(*) FROM map_friend_message_state WHERE friendship_id = $fId")->fetchColumn() === 0,
        'ending the friendship takes messages and read marks with it');

    friendship_request($pdo, $ben, $anna);
    $fId2 = (int) $pdo->query("SELECT id FROM map_friends WHERE from_user_id = $ben AND to_user_id = $anna")->fetchColumn();
    friendship_accept($pdo, $anna, $fId2);
    check($fId2 !== $fId, 'asking again makes a new room');
    check(friend_chat_messages($pdo, $anna, $ben) === ['messages' => [], 'latest' => 0],
        'and the new conversation starts empty — the old words are gone for good');
} finally {
    $ids = implode(',', array_values($accounts));
    $pdo->exec("DELETE n FROM map_friend_messages n
                  JOIN map_friends f ON f.id = n.friendship_id
                 WHERE f.from_user_id IN ($ids) OR f.to_user_id IN ($ids)");
    $pdo->exec("DELETE g FROM map_friend_message_state g
                  JOIN map_friends f ON f.id = g.friendship_id
                 WHERE f.from_user_id IN ($ids) OR f.to_user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_friends WHERE from_user_id IN ($ids) OR to_user_id IN ($ids)");
    $pdo->exec("DELETE FROM map_users WHERE id IN ($ids)");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
