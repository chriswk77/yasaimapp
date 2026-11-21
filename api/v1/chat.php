<?php
/**
 * The chat — of a container, and since 0068 of a friendship.
 *
 *   GET  chat.php?container=5            everything
 *   GET  chat.php?container=5&since=41   only what is newer — the polling
 *   POST chat.php?container=5            write ({text: "…"})
 *   POST chat.php?container=5&read=41    remember up to where I have read
 *
 * The personal chat is the same four calls with ?friend=<user id> in the
 * container's place — ONE endpoint, because it is one mechanism and the
 * browser draws both conversations with the same code. Which room a call
 * means is decided by which of the two parameters it carries; both at
 * once is a caller error and answered as one.
 *
 * WHY 'since' AND NOT A TIMESTAMP: two devices have two clocks, and the
 * server's is a third. The running number is the only order all three
 * agree on — and it is the one the device already holds, because it drew
 * the last message from it.
 *
 * THE POLLING ASKS OFTEN AND CARRIES LITTLE. Without anything new the
 * answer is an empty list plus the current number, and that number is what
 * makes it worth it: a device that has fallen behind learns from it that
 * it has, without the conversation being sent along every time.
 *
 * Membership is checked in includes/chat.php on EVERY call, not here — the
 * boundary belongs at the data, not at the door in front of it.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/chat.php';
// For the open friend requests, which ride along in the unread answer —
// see there.
require_once __DIR__ . '/../../includes/friends.php';

$userId = (int) $me['id'];

/* THE CONTAINER MAY COME FROM THE BODY, and for writing it has to. The
   browser's api.post() knows no query parameters — it sends a body and,
   without network, hands the whole call to the queue, which later replays
   exactly that body. A container that stood only in the address would be
   lost on the way through the queue, and the message would arrive
   nowhere. Reading keeps the address: a GET has no body. */
$containerId = (int) ($_GET['container'] ?? $input['container'] ?? 0);

/* THE COUNTS OF ALL CONTAINERS AT ONCE, and therefore without a container
   of its own. This is what the map asks while no chat window is open — the
   dot on the sidebar row has to appear even then, or it would only ever
   show what was true at the last page load. One request for all
   containers, and usually an empty answer.

   THE FRIENDS RIDE ALONG IN THE SAME ANSWER: the speech bubble at the
   sidebar's corner lives off the very same poll, and a second timer for a
   second endpoint would double the asking for the same moment. */
if ($method === 'GET' && isset($_GET['unread'])) {
    /* THE OPEN FRIEND REQUESTS RIDE ALONG TOO (25.08.2026:
       „muss er da zufällig in die Verwaltung und das entdecken?"). They
       did — the count stood on the administration's tab and nowhere
       else, so a request reached whoever happened to walk past it.

       In THIS answer and not in one of its own, for the same reason the
       friends' unread counts are here: the map already asks this
       question every few seconds, and a second timer for a second
       endpoint would ask the same moment twice. Name included, because
       the badge over the map says who is asking — a bare number would
       send one to the administration to find out, which is exactly the
       walk we are saving. */
    $requests = [];
    if (friends_ready($pdo)) {
        foreach (friends_overview($pdo, $userId)['incoming'] as $row) {
            $requests[] = ['id' => (int) $row['id'], 'name' => (string) $row['display_name']];
        }
    }

    json_response([
        'unread' => chat_ready($pdo) ? chat_unread($pdo, $userId) : [],
        'friends' => friend_chat_unread($pdo, $userId),
        'friendRequests' => $requests,
        // And the answers to MY requests (13.09.2026) — the same reason,
        // the same poll: friends_accepted_unseen.
        'friendAccepted' => friends_ready($pdo) ? friends_accepted_unseen($pdo, $userId) : [],
    ]);
}

/* The friend, like the container, may come from the body — the queue
   replays bodies, and a room that stood only in the address would be lost
   on the way through it (see the note below). */
$friendId = (int) ($_GET['friend'] ?? $input['friend'] ?? 0);

if ($friendId > 0 && $containerId > 0) {
    json_error(t('Entweder Freigabe oder Freund — nicht beides.'), 422);
}

if ($friendId > 0) {
    if (!friend_chat_ready($pdo)) {
        json_error(t('Der Freundes-Chat braucht die Migration 0068 — sie ist noch nicht eingespielt.'), 503);
    }
    // The same silence as with a foreign container: whoever is not friends
    // with this id learns nothing beyond that from the answer.
    if (friend_chat_room($pdo, $userId, $friendId) === null) {
        json_error(t('Ihr seid nicht befreundet.'), 403);
    }

    switch ($method) {
        case 'GET':
            json_response(friend_chat_messages($pdo, $userId, $friendId, (int) ($_GET['since'] ?? 0)));

        case 'POST':
            $read = (int) ($_GET['read'] ?? $input['read'] ?? 0);
            if ($read > 0) {
                friend_chat_mark_read($pdo, $userId, $friendId, $read);
                json_response(['read_up_to' => $read]);
            }
            try {
                $message = friend_chat_write($pdo, $userId, $friendId, (string) ($input['text'] ?? ''));
            } catch (InvalidArgumentException $e) {
                json_error($e->getMessage(), 422);
            }
            json_response(['message' => $message]);

        default:
            json_error(t('Methode nicht unterstützt.'), 405);
    }
}

if ($containerId <= 0) {
    json_error(t('Ohne Freigabe oder Freund gibt es keinen Chat.'), 422);
}
if (!container_ready($pdo)) {
    json_error(t('Freigaben brauchen die Migration 0042 — sie ist noch nicht eingespielt.'), 503);
}

// NOT FOUND AND NOT FORBIDDEN: whoever is not a member should not learn
// from the answer whether this container exists at all. The 403 of
// bucket.php says the same thing for the same reason.
if (!container_visible($pdo, $userId, $containerId)) {
    json_error(t('Kein Zugriff auf diese Freigabe.'), 403);
}

switch ($method) {
    case 'GET':
        json_response(chat_messages($pdo, $userId, $containerId, (int) ($_GET['since'] ?? 0)));

    case 'POST':
        // The read mark travels as its own call, without a text: the window
        // reports it when it is opened and when it is scrolled to the end,
        // and neither of those is a message.
        $read = (int) ($_GET['read'] ?? $input['read'] ?? 0);
        if ($read > 0) {
            chat_mark_read($pdo, $userId, $containerId, $read);
            json_response(['read_up_to' => $read]);
        }

        try {
            $message = chat_write($pdo, $userId, $containerId, (string) ($input['text'] ?? ''));
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage(), 422);
        }
        json_response(['message' => $message]);

    default:
        json_error(t('Methode nicht unterstützt.'), 405);
}
