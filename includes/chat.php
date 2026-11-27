<?php
/**
 * The chat of a container.
 *
 * MEMBERSHIP IS THE READING RIGHT, and there is nothing else. A message
 * has no recipients and no visibility of its own — whoever is in the
 * container reads everything, including what was written before they
 * joined. A shared memory that starts at the moment of joining is
 * worthless to the newcomer, and it is the newcomer who needs it most.
 *
 * EVERY CALL CHECKS MEMBERSHIP AGAIN. Not because the interface would let
 * anybody through, but because it is not the boundary — the boundary is
 * here, and it has to hold even if somebody addresses the endpoint by
 * hand.
 *
 * ONE GUARD AGAINST THE MISSING MIGRATION, AND ONLY ONE. The code goes
 * live before the migration, so for a quarter of an hour the table is not
 * there yet. Guarding every function against that would be a net for a
 * situation that lasts fifteen minutes among three people who know each
 * other (20.08.2026) — the chat simply does not work in that
 * window, and it says so.
 *
 * The exception is chat_unread(): it runs on EVERY load of the map, and an
 * exception there would take the map down with it, including the page on
 * which one applies the migration. That one asks.
 *
 * ---------------------------------------------------------------------
 * WHAT IS GUARANTEED HERE, and what is not. Measured on 20.08.2026, all
 * of it against a real non-member and with real payloads:
 *
 *   Reading, writing and the read mark in a foreign container    403
 *   Writing without the form token                               419
 *   The unread counts                    only one's own containers
 *   A message longer than CHAT_MAX               cut, not refused
 *
 * HTML AND JAVASCRIPT IN A MESSAGE ARE HARMLESS, and the reason matters:
 * the text is stored RAW and set on display via textContent (chat.js).
 * The browser therefore never gets it as markup. A script tag, an img with
 * onerror, a javascript: link and an svg with onload were sent through and
 * arrive as letters — zero elements in the conversation area.
 *
 * (Written out in words and not as tags: tests/icons.php hunts for the
 * opening svg tag outside the icon library, and it is right to — a comment
 * that names an attack must not look like a drawing. It cost exactly one
 * red test, and the test was in the right.)
 *
 * DO NOT "SANITISE" ON THE WAY IN. Whoever strips tags here breaks
 * somebody writing about script tags and gains nothing: the defence is at
 * the output, and it holds for everything, including what is already in
 * the database. A filter would be a second, weaker defence in front of a
 * complete one — and the first thing to be trusted the day somebody uses
 * innerHTML somewhere.
 *
 * WHAT IS NOT GUARDED: the number of messages. Nobody is stopped from
 * writing a thousand of them. Among people who know each other that is a
 * social problem, not a technical one — and a rate limit that fires in the
 * woods, where one writes three lines in a row, would cost more than it
 * saves. If it ever has to happen: per user and container, not per
 * request.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/container.php';

/**
 * How long a message may be. THE ONLY PLACE THIS NUMBER STANDS.
 *
 * The column is TEXT since migration 0061 and would take far more — the
 * limit is a reasonableness one, not a technical one: ten thousand
 * characters are about fifteen hundred words, more than anything somebody
 * types into a field at the bottom edge of a window. Whoever really wants
 * more writes two messages, and that reads better anyway.
 *
 * CUT OFF, NOT REFUSED. Somebody who has written a long message and gets
 * it back with "too long" has to shorten it himself, in a field that has
 * meanwhile scrolled away. The cut is the friendlier answer — and at ten
 * thousand characters nobody reaches it by accident.
 */
const CHAT_MAX = 10000;

/** How many messages one look fetches at most. */
const CHAT_PAGE = 200;

/** Does the table exist yet? Migration 0060 — see the note at the top. */
function chat_ready(PDO $pdo): bool
{
    static $there = null;
    return $there ??= table_exists($pdo, 'map_container_messages');
}

/**
 * The messages of a container, oldest first.
 *
 * @param int $since only what is NEWER than this number — 0 means
 *   everything. The device asks with the highest number it already holds,
 *   so polling costs an empty answer and not the whole conversation.
 * @return array{messages: array, latest: int}  latest is the highest
 *   number in the container, even when nothing new came along: without it
 *   a device that has fallen behind would never learn where it stands.
 */
/**
 * Who may read and write a container's chat. A box: every member. A list
 * share (migration 0160): the owner, whoever works in the list and whoever
 * is reached through a working group — a friend who only looks talks to
 * the owner in their friend chat (list_share_chat_member).
 */
function chat_member(PDO $pdo, int $userId, int $containerId): bool
{
    if (!container_visible($pdo, $userId, $containerId)) {
        return false;
    }
    require_once __DIR__ . '/list_shares.php';
    return list_share_list($pdo, $containerId) === null || list_share_chat_member($pdo, $userId, $containerId);
}

function chat_messages(PDO $pdo, int $userId, int $containerId, int $since = 0): array
{
    if (!chat_member($pdo, $userId, $containerId)) {
        return ['messages' => [], 'latest' => 0];
    }

    $withKind = chat_kind_ready($pdo);
    $withMeta = chat_meta_ready($pdo);
    $stmt = $pdo->prepare(
        'SELECT n.id, n.user_id, n.text, n.created_at, ' . sql_display_name() . ' AS display_name'
        . ($withKind ? ', n.kind' : '') . ($withMeta ? ', n.meta' : '') . '
           FROM map_container_messages n
           JOIN map_users u ON u.id = n.user_id
          WHERE n.container_id = ? AND n.id > ?
          ORDER BY n.id
          LIMIT ' . CHAT_PAGE
    );
    $stmt->execute([$containerId, $since]);

    $rows = array_map(static fn(array $z): array => [
        'id'          => (int) $z['id'],
        'user_id'     => (int) $z['user_id'],
        'text'        => $z['text'],
        'created_at' => $z['created_at'],
        'display_name' => $z['display_name'],
        // 'change' is what the map wrote about a joint point (0111);
        // the browser draws it quietly, in the actor's name.
        'kind'        => (string) ($z['kind'] ?? 'chat'),
        // A folded change line names the line it replaced (0121), so an
        // open chat swaps it instead of showing both.
        'replaces'    => (int) ((json_decode((string) ($z['meta'] ?? ''), true) ?: [])['replaces'] ?? 0),
    ], $stmt->fetchAll());

    $latest = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM map_container_messages WHERE container_id = ?');
    $latest->execute([$containerId]);

    return ['messages' => $rows, 'latest' => (int) $latest->fetchColumn()];
}

/**
 * Write a message. Returns it in the same shape chat_messages() delivers.
 *
 * @throws InvalidArgumentException when the text is empty or one is not a
 *   member — the two are told apart in the message, because for the
 *   writer they mean entirely different things.
 */
function chat_write(PDO $pdo, int $userId, int $containerId, string $text): array
{
    if (!chat_member($pdo, $userId, $containerId)) {
        throw new InvalidArgumentException(t('Du bist in dieser Freigabe nicht mehr dabei.'));
    }

    // Trimmed, then measured: a text of nothing but spaces is empty, and a
    // row of empty lines in the conversation is worse than a refusal.
    $text = trim($text);
    if ($text === '') {
        throw new InvalidArgumentException(t('Die Nachricht ist leer.'));
    }
    $text = mb_substr($text, 0, CHAT_MAX);

    $pdo->prepare(
        'INSERT INTO map_container_messages (container_id, user_id, text, created_at)
         VALUES (?, ?, ?, NOW())'
    )->execute([$containerId, $userId, $text]);

    $id = (int) $pdo->lastInsertId();

    /* THE WRITER HAS READ HIS OWN MESSAGE. Without this the badge would
       light up on one's own device the moment one sends something, and one
       would have to open the very window one is looking at to clear it. */
    chat_mark_read($pdo, $userId, $containerId, $id);

    $stmt = $pdo->prepare(
        'SELECT n.id, n.user_id, n.text, n.created_at, ' . sql_display_name() . ' AS display_name
           FROM map_container_messages n
           JOIN map_users u ON u.id = n.user_id
          WHERE n.id = ?'
    );
    $stmt->execute([$id]);
    $z = $stmt->fetch();

    return [
        'id'          => (int) $z['id'],
        'user_id'     => (int) $z['user_id'],
        'text'        => $z['text'],
        'created_at' => $z['created_at'],
        'display_name' => $z['display_name'],
    ];
}

/**
 * Remember up to where somebody has read.
 *
 * ONLY FORWARD. Two open devices would otherwise push each other back and
 * forth: the phone reports 12, the desktop 40, and the next look on the
 * desktop shows twenty-eight messages as unread again. GREATEST() decides
 * that in the database and not in a read-then-write, which two devices
 * could interleave.
 */
/** Whether messages carry a kind (migration 0111). */
function chat_kind_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_container_messages', 'kind');
}

/** Whether change lines carry what they are about (migration 0121). */
function chat_meta_ready(PDO $pdo): bool
{
    return chat_kind_ready($pdo) && column_exists($pdo, 'map_container_messages', 'meta');
}

/**
 * The newest line of a share's conversation, with its meta — what a new
 * change line may fold into (joint_line in joint_lists.php). 'fresh' says
 * whether it is younger than $minutes.
 */
function chat_latest_line(PDO $pdo, int $containerId, int $minutes): ?array
{
    if (!chat_meta_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, user_id, kind, meta, created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE) AS fresh
           FROM map_container_messages WHERE container_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$minutes, $containerId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }
    $row['meta'] = json_decode((string) ($row['meta'] ?? ''), true) ?: null;
    return $row;
}

/**
 * A line the MAP writes into a share's conversation, in the name of the
 * member who did something — „hat „Boletus edulis" geändert: Titel"
 * (joint lists, 22.09.2026). Kind 'change', so the browser draws it as a
 * fact and not as a spoken word; read for the actor at once, like his
 * own message, so his own deed does not light his own badge. No
 * membership check: the caller has already established who acted where.
 *
 * $meta (0121) says what the line is about; with $replaces the line takes
 * the place of an older one — deleted and written anew, so the folded
 * line gets a NEW number and lights the badge of whoever had already read
 * the old one: what they read is no longer all that happened.
 */
function chat_note(PDO $pdo, int $userId, int $containerId, string $text,
                   ?array $meta = null, ?int $replaces = null): void
{
    if (!chat_ready($pdo)) {
        return;
    }
    $text = mb_substr(trim($text), 0, CHAT_MAX);
    if ($text === '') {
        return;
    }
    if (chat_meta_ready($pdo) && ($meta !== null || $replaces !== null)) {
        if ($replaces !== null) {
            $pdo->prepare('DELETE FROM map_container_messages WHERE id = ? AND container_id = ?')
                ->execute([$replaces, $containerId]);
            $meta = ($meta ?? []) + ['replaces' => $replaces];
        }
        $pdo->prepare(
            'INSERT INTO map_container_messages (container_id, user_id, text, kind, meta, created_at)
             VALUES (?, ?, ?, \'change\', ?, NOW())'
        )->execute([$containerId, $userId, $text, json_encode($meta, JSON_UNESCAPED_UNICODE)]);
    } elseif (chat_kind_ready($pdo)) {
        $pdo->prepare(
            'INSERT INTO map_container_messages (container_id, user_id, text, kind, created_at)
             VALUES (?, ?, ?, \'change\', NOW())'
        )->execute([$containerId, $userId, $text]);
    } else {
        $pdo->prepare(
            'INSERT INTO map_container_messages (container_id, user_id, text, created_at)
             VALUES (?, ?, ?, NOW())'
        )->execute([$containerId, $userId, $text]);
    }
    chat_mark_read($pdo, $userId, $containerId, (int) $pdo->lastInsertId());
}

function chat_mark_read(PDO $pdo, int $userId, int $containerId, int $upTo): void
{
    if ($upTo <= 0) {
        return;
    }
    /* The row may not exist yet — somebody who has never looked at the
       members has none. INSERT ... ON DUPLICATE covers both cases in one
       statement, and 'members' stays untouched on the update: it
       belongs to the other purpose of this table.

       AN EMPTY STRING AND NOT NULL for the insert: the column is NOT NULL
       (it has been since its own migration), and an empty list is what it
       means — nobody seen yet. NULL would have been the tidier word for
       it and cost a constraint violation on the very first message. */
    $pdo->prepare(
        'INSERT INTO map_container_state (user_id, container_id, members, read_up_to, updated_at)
              VALUES (?, ?, \'\', ?, NOW())
         ON DUPLICATE KEY UPDATE read_up_to = GREATEST(read_up_to, VALUES(read_up_to))'
    )->execute([$userId, $containerId, $upTo]);
}

// ---------------------------------------------------------------------------
// The personal chat between two friends (migration 0068).
//
// THE FRIENDSHIP IS THE ROOM — the map_friends row plays the part the
// container plays above: being friends IS the read right, checked on every
// call, and the conversation dies with the friendship (friendship_remove()
// deletes it along). Everything else is the container chat's law verbatim:
// raw storage, defence at the output, the cap of CHAT_MAX, read marks that
// only move forward.
//
// THE INTERFACE SPEAKS IN FRIENDS, NOT IN FRIENDSHIP ROWS. The row id is
// an implementation detail nobody outside this file needs to know — the
// browser says "the chat with user 7", and friend_chat_room() translates
// that into the row or refuses. That refusal is the whole access check.
// ---------------------------------------------------------------------------

/** Do the tables exist yet? Migration 0068 — same caution as chat_ready(). */
function friend_chat_ready(PDO $pdo): bool
{
    static $there = null;
    return $there ??= table_exists($pdo, 'map_friend_messages');
}

/** Whether a friend's messages carry a kind and a meta (migration 0150). */
function friend_chat_kind_ready(PDO $pdo): bool
{
    return friend_chat_ready($pdo) && column_exists($pdo, 'map_friend_messages', 'kind');
}

/**
 * A line the MAP writes into a friend chat in the name of the one who did
 * something — „hat den Punkt „Boletus edulis" direkt mit dir geteilt …"
 * (02.10.2026, includes/point_hints.php). The twin of chat_note() for the
 * friendship room: kind 'change', so it is drawn as a fact; read for the
 * actor at once, so his own deed does not light his own badge. Nothing is
 * written before migration 0150 — a plain line would look as if he had
 * typed it.
 *
 * @return ?int the line's number, or null when none was written
 */
function friend_chat_note(PDO $pdo, int $me, int $friendUserId, string $text, ?array $meta = null): ?int
{
    if (!friend_chat_kind_ready($pdo)) {
        return null;
    }
    $room = friend_chat_room($pdo, $me, $friendUserId);
    $text = mb_substr(trim($text), 0, CHAT_MAX);
    if ($room === null || $text === '') {
        return null;
    }
    $pdo->prepare(
        "INSERT INTO map_friend_messages (friendship_id, user_id, text, kind, meta, created_at)
         VALUES (?, ?, ?, 'change', ?, NOW())"
    )->execute([$room, $me, $text, $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE)]);
    $id = (int) $pdo->lastInsertId();
    friend_chat_mark_read($pdo, $me, $friendUserId, $id);
    return $id;
}

/**
 * The friendship row the two of us share — or null, which means: no room.
 *
 * ACCEPTED ONLY. An open request is not a connection yet; writing into it
 * would be a way to message people who have not said yes.
 */
function friend_chat_room(PDO $pdo, int $me, int $friendUserId): ?int
{
    if ($friendUserId <= 0 || $friendUserId === $me) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT id FROM map_friends
          WHERE ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))
            AND status = 'accepted' LIMIT 1"
    );
    $stmt->execute([$me, $friendUserId, $friendUserId, $me]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/**
 * The conversation with one friend, oldest first — the shape of
 * chat_messages(), so the browser draws both with the same code.
 */
function friend_chat_messages(PDO $pdo, int $me, int $friendUserId, int $since = 0): array
{
    $room = friend_chat_room($pdo, $me, $friendUserId);
    if ($room === null) {
        return ['messages' => [], 'latest' => 0];
    }

    // The kind comes along since 0150, so a change line is drawn as one.
    $withKind = friend_chat_kind_ready($pdo);
    $stmt = $pdo->prepare(
        'SELECT n.id, n.user_id, n.text, n.created_at, ' . sql_display_name() . ' AS display_name'
        . ($withKind ? ', n.kind' : '') . '
           FROM map_friend_messages n
           JOIN map_users u ON u.id = n.user_id
          WHERE n.friendship_id = ? AND n.id > ?
          ORDER BY n.id
          LIMIT ' . CHAT_PAGE
    );
    $stmt->execute([$room, $since]);

    $rows = array_map(static fn(array $z): array => [
        'id'          => (int) $z['id'],
        'user_id'     => (int) $z['user_id'],
        'text'        => $z['text'],
        'created_at' => $z['created_at'],
        'display_name' => $z['display_name'],
        'kind'        => $z['kind'] ?? 'chat',
    ], $stmt->fetchAll());

    $latest = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM map_friend_messages WHERE friendship_id = ?');
    $latest->execute([$room]);

    return ['messages' => $rows, 'latest' => (int) $latest->fetchColumn()];
}

/** Write to one friend. Same agreement as chat_write(), same shape back. */
function friend_chat_write(PDO $pdo, int $me, int $friendUserId, string $text): array
{
    $room = friend_chat_room($pdo, $me, $friendUserId);
    if ($room === null) {
        throw new InvalidArgumentException(t('Ihr seid nicht (mehr) befreundet.'));
    }

    $text = trim($text);
    if ($text === '') {
        throw new InvalidArgumentException(t('Die Nachricht ist leer.'));
    }
    $text = mb_substr($text, 0, CHAT_MAX);

    $pdo->prepare(
        'INSERT INTO map_friend_messages (friendship_id, user_id, text, created_at)
         VALUES (?, ?, ?, NOW())'
    )->execute([$room, $me, $text]);

    $id = (int) $pdo->lastInsertId();

    // The writer has read his own message — chat_write() says why.
    friend_chat_mark_read($pdo, $me, $friendUserId, $id);

    $stmt = $pdo->prepare(
        'SELECT n.id, n.user_id, n.text, n.created_at, ' . sql_display_name() . ' AS display_name
           FROM map_friend_messages n
           JOIN map_users u ON u.id = n.user_id
          WHERE n.id = ?'
    );
    $stmt->execute([$id]);
    $z = $stmt->fetch();

    return [
        'id'          => (int) $z['id'],
        'user_id'     => (int) $z['user_id'],
        'text'        => $z['text'],
        'created_at' => $z['created_at'],
        'display_name' => $z['display_name'],
        'kind'        => 'chat',
    ];
}

/** Up to where I have read — only forward, for chat_mark_read()'s reason. */
function friend_chat_mark_read(PDO $pdo, int $me, int $friendUserId, int $upTo): void
{
    if ($upTo <= 0) {
        return;
    }
    $room = friend_chat_room($pdo, $me, $friendUserId);
    if ($room === null) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO map_friend_message_state (friendship_id, user_id, read_up_to, updated_at)
              VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE read_up_to = GREATEST(read_up_to, VALUES(read_up_to)),
                                 updated_at = NOW()'
    )->execute([$room, $me, $upTo]);
}

/**
 * How many unread messages wait with each friend — keyed by the FRIEND'S
 * user id, because that is the word the interface speaks.
 *
 * One query for all conversations, and guarded itself: like chat_unread()
 * it runs on every load of the map, where an exception over a missing
 * table would take down the very page one applies the migration on.
 */
function friend_chat_unread(PDO $pdo, int $me): array
{
    if (!friend_chat_ready($pdo)) {
        return [];
    }
    // Personal apart from status — see chat_unread().
    $personal = friend_chat_kind_ready($pdo) ? "SUM(n.kind = 'chat')" : 'COUNT(*)';
    $stmt = $pdo->prepare(
        'SELECT IF(f.from_user_id = ?, f.to_user_id, f.from_user_id) AS friend_id,
                COUNT(*) AS unread,
                ' . $personal . ' AS personal,
                MAX(n.id) AS highest
           FROM map_friend_messages n
           JOIN map_friends f ON f.id = n.friendship_id
           LEFT JOIN map_friend_message_state g
                  ON g.friendship_id = n.friendship_id AND g.user_id = ?
          WHERE (f.from_user_id = ? OR f.to_user_id = ?)
            AND f.status = \'accepted\'
            AND n.user_id <> ?
            AND n.id > COALESCE(g.read_up_to, 0)
          GROUP BY friend_id'
    );
    $stmt->execute([$me, $me, $me, $me, $me]);

    $out = [];
    foreach ($stmt as $z) {
        $out[(int) $z['friend_id']] = [
            'unread' => (int) $z['unread'],
            'personal' => (int) $z['personal'],
            'latest'     => (int) $z['highest'],
        ];
    }
    return $out;
}

/**
 * The friends I already have a conversation with — any line at all, mine,
 * theirs or one of the map's own. The chat list marks them with a bubble
 * that holds lines, the others with an empty one (27.11.2026: „wenn es
 * schon einen Chat gibt … und bei Leuten, bei denen noch nichts ist, eben
 * entsprechend einen leeren Chatverlauf"). A status line counts: the
 * window it opens is not empty, and the empty bubble promises one that is.
 *
 * @return array<int, true>  friend's user id => true
 */
function friend_chat_started(PDO $pdo, int $me): array
{
    if (!friend_chat_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT IF(f.from_user_id = ?, f.to_user_id, f.from_user_id) AS friend_id
           FROM map_friends f
          WHERE (f.from_user_id = ? OR f.to_user_id = ?)
            AND f.status = \'accepted\'
            AND EXISTS (SELECT 1 FROM map_friend_messages n WHERE n.friendship_id = f.id)'
    );
    $stmt->execute([$me, $me, $me]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $out[(int) $id] = true;
    }
    return $out;
}

/**
 * Take the conversation with the friendship — friendship_remove() calls
 * this INSIDE its own flow, before the row disappears. Guarded, because
 * ending a friendship must work even while 0068 is not applied yet.
 */
function friend_chat_forget(PDO $pdo, int $friendshipId): void
{
    if (!friend_chat_ready($pdo)) {
        return;
    }
    $pdo->prepare('DELETE FROM map_friend_messages WHERE friendship_id = ?')->execute([$friendshipId]);
    $pdo->prepare('DELETE FROM map_friend_message_state WHERE friendship_id = ?')->execute([$friendshipId]);
}

/**
 * How many unread messages wait in each of my containers.
 *
 * IN ONE QUERY FOR ALL OF THEM, because it runs on every page load of the
 * map: one query per container would be a handful of round trips for a
 * number that is usually zero.
 *
 * @return array<int,array{unread: int, latest: int}> keyed by container
 */
function chat_unread(PDO $pdo, int $userId): array
{
    if (!chat_ready($pdo)) {
        return [];
    }
    $ids = array_map(static fn(array $c): int => (int) $c['id'], container_mine($pdo, $userId));
    // Not the list shares whose chat one does not belong to (chat_member).
    require_once __DIR__ . '/list_shares.php';
    $ids = array_values(array_filter($ids, static fn(int $id): bool =>
        list_share_list($pdo, $id) === null || list_share_chat_member($pdo, $userId, $id)));
    if ($ids === []) {
        return [];
    }
    $space = implode(',', array_fill(0, count($ids), '?'));

    /* PERSONAL APART FROM STATUS (10.10.2026): a line somebody
       wrote lights the dot in full, the map's own lines about a change only
       quietly — and the header's collective bubble only for the first. */
    $personal = chat_kind_ready($pdo) ? "SUM(n.kind = 'chat')" : 'COUNT(*)';
    $stmt = $pdo->prepare(
        "SELECT n.container_id,
                COUNT(*) AS unread,
                $personal AS personal,
                MAX(n.id) AS highest
           FROM map_container_messages n
           LEFT JOIN map_container_state g
                  ON g.container_id = n.container_id AND g.user_id = ?
          WHERE n.container_id IN ($space)
            AND n.user_id <> ?
            AND n.id > COALESCE(g.read_up_to, 0)
          GROUP BY n.container_id"
    );
    $stmt->execute(array_merge([$userId], $ids, [$userId]));

    $out = [];
    foreach ($stmt as $z) {
        $out[(int) $z['container_id']] = [
            'unread' => (int) $z['unread'],
            'personal' => (int) $z['personal'],
            'latest'     => (int) $z['highest'],
        ];
    }
    return $out;
}
