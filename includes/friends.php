<?php
/**
 * Friends and working groups — the logic, separated from the view.
 *
 * The view lives in friends_content.php and groups_content.php; here is what
 * the checks and the later stages (containers, shares) need as well. Errors
 * that follow from user input come as InvalidArgumentException with a text
 * that can go into the message as-is — the same agreement as in data.php.
 */

declare(strict_types=1);

/**
 * Do the tables exist yet? The code goes live before the migration — until
 * 0041 has been applied, the tab only shows a notice.
 */
function friends_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_friends')
        && table_exists($pdo, 'map_groups')
        && table_exists($pdo, 'map_group_members');
}

// ---------------------------------------------------------------------------
// Friendships
// ---------------------------------------------------------------------------

/**
 * Whom can I send a request to? Searches for a name fragment and says for
 * each match where the two of you stand with each other.
 *
 * THE OPPOSITE ONCE APPLIED HERE: Requesting demanded the exact e-mail
 * address so that the site would not become a browsable directory. That
 * worry has outlived itself — only invited people get in anyway, and whoever
 * is in should be able to find the others without knowing their address by
 * heart. Since then there has been this search field with suggestions.
 *
 * The search covers first name, last name, both together, and the e-mail
 * address. Below two characters nothing comes back: a single letter matches
 * everyone, and a list that contains everyone suggests nothing.
 *
 * 'state' per match: 'free' (requestable), 'friend', 'open' (a request is
 * already pending, no matter in which direction). The non-requestable ones
 * are NOT missing from the list — whoever types "Tho" and does not find
 * Tobias would think the search broken. They appear, just as already taken.
 */
function user_search(PDO $pdo, int $me, string $term, int $offset = 0, int $limit = 15, bool $withFriends = true): array
{
    /* AN EMPTY TERM MEANS EVERYBODY, and that is the point of it
       (20.08.2026). Requesting somebody used to work only by
       typing a name — which presumes you know how they are registered
       here. With a handful of accounts that is a riddle with no reason:
       the list is short, so it can simply be shown.

       WHAT IS SHOWN IS NOT NEW. Whoever typed two letters saw the same
       people; the search always went across everybody. Only the entry
       changed, from guessing to reading. The already-befriended and the
       pending ones come along, greyed out with their reason — otherwise
       one asks a second time and wonders why nothing happens.

       PAGE BY PAGE (02.10.2026: „dass die Liste lang sein kann
       und dass da wirklich nur maximal fünfzehn oder so stehen und man
       beim Runterscrollen dann weitere nachlädt"). $limit rows from
       $offset, for the empty term and for a word alike — until then the
       empty term listed up to 200 at once and a word stopped at eight,
       so a common name had no way past its eighth namesake.

       $withFriends = false leaves the friends out IN THE QUERY — the
       request window's wish since 12.09.2026. Filtered afterwards, as it
       was, a page of fifteen would come back with gaps and the window
       could not tell whether more were coming. */
    $term = trim($term);
    $all  = $term === '';
    if (!$all && mb_strlen($term) < 2) {
        return [];
    }
    $like = '%' . strtr($term, ['\\' => '\\\\', '%' => '\%', '_' => '\_']) . '%';

    $stmt = $pdo->prepare(
        'SELECT u.id, ' . sql_display_name() . ' AS display_name, u.email, f.status
           FROM map_users u
           LEFT JOIN map_friends f
             ON (f.from_user_id = u.id AND f.to_user_id = ?)
             OR (f.from_user_id = ? AND f.to_user_id = u.id)
          WHERE u.id <> ? AND u.locked_at IS NULL'
        /* A BLOCK HIDES BOTH FROM EACH OTHER (23.11.2026) — whoever was
           blocked does not find the one who blocked, and the one who
           blocked takes him back only through the list „Blockierte
           Personen". Interpolated ids, not placeholders: $me is an int,
           and the placeholder list below stays as it was. */
        . (user_blocks_ready($pdo) ? '
            AND u.id NOT IN (SELECT blocked_user_id FROM map_user_blocks WHERE user_id = ' . $me . ')
            AND u.id NOT IN (SELECT user_id FROM map_user_blocks WHERE blocked_user_id = ' . $me . ')' : '')
        . ($all ? '' : '
            AND (u.first_name LIKE ? OR u.last_name LIKE ?
                 OR CONCAT(u.first_name, \' \', u.last_name) LIKE ? OR u.email LIKE ?)')
        . ($withFriends ? '' : "
            AND (f.status IS NULL OR f.status <> 'accepted')")
        . '
          ORDER BY display_name, u.id
          LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
    );
    $stmt->execute($all ? [$me, $me, $me] : [$me, $me, $me, $like, $like, $like, $like]);

    /* THE ADDRESS ONLY WHERE IT IS ALREADY KNOWN (security review,
       23.09.2026). The empty term lists everybody — that stays, it is
       the call —, but with it went the e-mail of up to 200
       accounts to anybody logged in. Now the address travels only for a
       friend, or when the term itself is found in it: whoever typed
       „thomas@" already holds that much, and two namesakes are still told
       apart by what was typed. */
    $needle = mb_strtolower($term);
    return array_map(static fn(array $row) => [
        'id'          => (int) $row['id'],
        'display_name' => $row['display_name'],
        'email'       => ($row['status'] === 'accepted'
                          || ($needle !== '' && str_contains($needle, '@') && str_contains(mb_strtolower((string) $row['email']), $needle)))
                         ? $row['email'] : null,
        'state'       => $row['status'] === null ? 'free'
                       : ($row['status'] === 'accepted' ? 'friend' : 'open'),
    ], $stmt->fetchAll());
}

/**
 * Request a friendship. Returns the person requested.
 *
 * The identifier is the user id from a match of user_search() — no
 * longer a typed field, so "empty" and "typo" are not cases here. What
 * remains: the match can be stale (blocked or deleted in the meantime),
 * which is why it is looked up once more.
 */
function friendship_request(PDO $pdo, int $me, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, first_name, last_name, email FROM map_users
          WHERE id = ? AND locked_at IS NULL LIMIT 1'
    );
    $stmt->execute([$userId]);
    $person = $stmt->fetch();

    if ($person === false) {
        throw new InvalidArgumentException(t('Diese Person gibt es nicht mehr.'));
    }
    if ((int) $person['id'] === $me) {
        throw new InvalidArgumentException(t('Das bist du selbst.'));
    }
    /* BLOCKED IN EITHER DIRECTION, the same words as a deleted account:
       whoever was blocked is not told so — that would only invite him to
       try another way. */
    if (user_blocked_between($pdo, $me, (int) $person['id'])) {
        throw new InvalidArgumentException(t('Diese Person gibt es nicht mehr.'));
    }

    // Check both directions: even if the other person has already sent a
    // request, there is no second row — their request already sits in the inbox.
    $stmt = $pdo->prepare(
        'SELECT status FROM map_friends
          WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?) LIMIT 1'
    );
    $stmt->execute([$me, $person['id'], $person['id'], $me]);
    $existing = $stmt->fetchColumn();

    if ($existing === 'accepted') {
        // No h() here: notice() protects every output itself. ONE sentence
        // for t() — the name glued in front could never be reordered.
        throw new InvalidArgumentException(t('Mit %s bist du schon befreundet.', user_display_name($person)));
    }
    if ($existing === 'requested') {
        throw new InvalidArgumentException(t('Zwischen euch läuft schon eine Anfrage.'));
    }

    $pdo->prepare('INSERT INTO map_friends (from_user_id, to_user_id) VALUES (?, ?)')
        ->execute([$me, $person['id']]);

    return $person;
}

/** Accept an open request addressed to me. */
function friendship_accept(PDO $pdo, int $me, int $friendshipId): void
{
    $stmt = $pdo->prepare(
        "UPDATE map_friends SET status = 'accepted', answered_at = NOW()
          WHERE id = ? AND to_user_id = ? AND status = 'requested'"
    );
    $stmt->execute([$friendshipId, $me]);
    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException(t('Diese Anfrage gibt es nicht mehr.'));
    }
}

/**
 * Remove a friendship row — that is declining, withdrawing and ending in
 * one: in all three cases the row disappears, and requesting again is
 * always allowed afterwards.
 *
 * WHEN AN ACCEPTED FRIENDSHIP ENDS, every group membership that rested on it
 * ends too — both are removed from each other's working groups. Otherwise
 * the group would keep alive a connection that was just explicitly ended.
 *
 * RETURNS WHICH OF THE THREE IT WAS — 'declined', 'withdrawn' or 'ended' —
 * because the same click answered with no message at all until 02.10.2026,
 * and a deed without an answer leaves one wondering whether it happened.
 */
function friendship_remove(PDO $pdo, int $me, int $friendshipId): string
{
    $stmt = $pdo->prepare(
        'SELECT from_user_id, to_user_id, status FROM map_friends
          WHERE id = ? AND (from_user_id = ? OR to_user_id = ?) LIMIT 1'
    );
    $stmt->execute([$friendshipId, $me, $me]);
    $row = $stmt->fetch();
    if ($row === false) {
        throw new InvalidArgumentException(t('Diese Freundschaft gibt es nicht mehr.'));
    }

    /* THE CONVERSATION GOES WITH THE FRIENDSHIP — before the row, so the
       messages never outlive their room even if something below throws.
       Declining or withdrawing a request passes through here too; then
       there is nothing to delete, and the call costs two empty DELETEs. */
    require_once __DIR__ . '/chat.php';
    friend_chat_forget($pdo, $friendshipId);

    $pdo->prepare('DELETE FROM map_friends WHERE id = ?')->execute([$friendshipId]);

    if ($row['status'] === 'accepted') {
        $other = (int) ($row['from_user_id'] == $me ? $row['to_user_id'] : $row['from_user_id']);
        $removal = $pdo->prepare(
            'DELETE gm FROM map_group_members gm
               JOIN map_groups g ON g.id = gm.group_id
              WHERE (g.owner_user_id = ? AND gm.user_id = ?)
                 OR (g.owner_user_id = ? AND gm.user_id = ?)'
        );
        $removal->execute([$me, $other, $other, $me]);

        // And from each other's containers — direct membership presupposes
        // friendship. Whatever shares are left standing without membership
        // afterwards, container_cleanup() unhooks.
        require_once __DIR__ . '/container.php';
        if (container_ready($pdo)) {
            $pdo->prepare(
                "DELETE cm FROM map_container_members cm
                   JOIN map_containers c ON c.id = cm.container_id
                  WHERE cm.type = 'friend'
                    AND ((c.owner_user_id = ? AND cm.target_id = ?)
                      OR (c.owner_user_id = ? AND cm.target_id = ?))"
            )->execute([$me, $other, $other, $me]);
            container_cleanup($pdo);
        }
        /* AND THE FINDS THEY OWNED TOGETHER SPLIT (08.10.2026):
           each keeps a copy of his own and they run apart — a record
           still shared would let the other go on changing or removing
           what one worked on. Before the hints below: the co-owner's
           hints move to his copy. */
        require_once __DIR__ . '/point_owners.php';
        point_owners_split($pdo, $me, $other);

        // And the finds they showed each other (migration 0149) — a hint
        // rests on the friendship as a direct membership does.
        require_once __DIR__ . '/point_hints.php';
        if (point_hints_ready($pdo)) {
            $pdo->prepare(
                'DELETE FROM map_point_hints
                  WHERE (user_id = ? AND recipient_id = ?) OR (user_id = ? AND recipient_id = ?)'
            )->execute([$me, $other, $other, $me]);
        }
        return 'ended';
    }
    return (int) $row['to_user_id'] === $me ? 'declined' : 'withdrawn';
}

// ---------------------------------------------------------------------------
// Blocking and reporting (migration 0200, 23.11.2026)
// ---------------------------------------------------------------------------

/*
 * WHY THIS EXISTS: the Play review wants users to block and report each
 * other wherever they can write to one another — and friends can, in the
 * chat. The maintainer's answer set the shape: „Weil man auch eine
 * Freundschaft beenden kann und sagen kann, mit dem will ich nichts mehr zu
 * tun haben, damit ist er blockiert." So there is NO block beside the
 * friendship, no friend whose chat is muted: BLOCKING IS ENDING THE
 * FRIENDSHIP, under ONE button that is called „Blockieren" („keine
 * doppelten Knöpfe … nennen wir ihn einfach Blockieren"), and the row in
 * map_user_blocks only remembers it, so the other cannot ask again.
 *
 * WHAT IT DELIBERATELY DOES NOT DO: reach into a THIRD person's working
 * group or joint list where both happen to be. That group is the third
 * person's; whoever does not want to meet somebody there leaves it.
 */

/** Does the table exist yet? Until migration 0200 the button ends a friendship as before. */
function user_blocks_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_user_blocks');
}

/** Has either of the two blocked the other? */
function user_blocked_between(PDO $pdo, int $a, int $b): bool
{
    if (!user_blocks_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM map_user_blocks
          WHERE (user_id = ? AND blocked_user_id = ?) OR (user_id = ? AND blocked_user_id = ?) LIMIT 1'
    );
    $stmt->execute([$a, $b, $b, $a]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Block a friend: the friendship ends with everything friendship_remove()
 * takes along, and the row keeps the two apart afterwards.
 *
 * ONLY FROM A FRIENDSHIP, on purpose: the friend row is the one place the
 * button stands. A request that is not wanted is declined with the cross
 * and may come again (CLAUDE.md, „Three symbols") — that stays.
 *
 * @return string the other person's display name, for the message
 */
function user_block(PDO $pdo, int $me, int $friendshipId): string
{
    $stmt = $pdo->prepare(
        "SELECT u.id, " . sql_display_name() . " AS display_name
           FROM map_friends f
           JOIN map_users u ON u.id = IF(f.from_user_id = ?, f.to_user_id, f.from_user_id)
          WHERE f.id = ? AND (f.from_user_id = ? OR f.to_user_id = ?) AND f.status = 'accepted' LIMIT 1"
    );
    $stmt->execute([$me, $friendshipId, $me, $me]);
    $other = $stmt->fetch();
    if ($other === false) {
        throw new InvalidArgumentException(t('Diese Freundschaft gibt es nicht mehr.'));
    }

    friendship_remove($pdo, $me, $friendshipId);
    if (user_blocks_ready($pdo)) {
        $pdo->prepare('INSERT IGNORE INTO map_user_blocks (user_id, blocked_user_id, created_at) VALUES (?, ?, NOW())')
            ->execute([$me, (int) $other['id']]);
    }
    return (string) $other['display_name'];
}

/** Lift a block of mine. Afterwards either may request the other again. */
function user_unblock(PDO $pdo, int $me, int $userId): void
{
    if (!user_blocks_ready($pdo)) {
        return;
    }
    $pdo->prepare('DELETE FROM map_user_blocks WHERE user_id = ? AND blocked_user_id = ?')
        ->execute([$me, $userId]);
}

/** The people I blocked, by name — [{user_id, display_name, created_at}]. */
function user_blocks(PDO $pdo, int $me): array
{
    if (!user_blocks_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT u.id AS user_id, ' . sql_display_name() . ' AS display_name, b.created_at
           FROM map_user_blocks b
           JOIN map_users u ON u.id = b.blocked_user_id
          WHERE b.user_id = ?
          ORDER BY display_name'
    );
    $stmt->execute([$me]);
    return $stmt->fetchAll();
}

/** How long a report may be — a reasonableness limit like the chat's. */
const FRIENDS_REPORT_MAX = 5000;

/**
 * Report a friend to the administrators: one mail to each of them, with
 * both names, both addresses and the reporter's words.
 *
 * A MAIL AND NO TABLE (the small version agreed 23.11.2026): the answer
 * to a report is a deed of the administrator — the lock in
 * Benutzerverwaltung —, and a list of reports in the administration would
 * be a second inbox beside the mail one reads anyway.
 *
 * NOTHING OF A FIND TRAVELS. The mail carries names and the reporter's own
 * text — never a coordinate, a list or a picture: secret find spots stay
 * secret, and whoever reports writes what happened in his own words.
 *
 * Reporting does not end the friendship; that is „Blockieren", a step of
 * its own (Google wants the two apart).
 *
 * @throws InvalidArgumentException  no such friend, no text, mail not handed on
 */
function friends_report(PDO $pdo, int $me, int $friendshipId, string $text): void
{
    $text = trim(mb_substr($text, 0, FRIENDS_REPORT_MAX));
    if ($text === '') {
        throw new InvalidArgumentException(t('Schreib bitte kurz, was passiert ist.'));
    }
    $stmt = $pdo->prepare(
        "SELECT u.id, " . sql_display_name() . " AS display_name, u.email
           FROM map_friends f
           JOIN map_users u ON u.id = IF(f.from_user_id = ?, f.to_user_id, f.from_user_id)
          WHERE f.id = ? AND (f.from_user_id = ? OR f.to_user_id = ?) AND f.status = 'accepted' LIMIT 1"
    );
    $stmt->execute([$me, $friendshipId, $me, $me]);
    $other = $stmt->fetch();
    if ($other === false) {
        throw new InvalidArgumentException(t('Diese Freundschaft gibt es nicht mehr.'));
    }
    $stmt = $pdo->prepare('SELECT id, ' . sql_display_name() . ' AS display_name, email FROM map_users u WHERE id = ?');
    $stmt->execute([$me]);
    $reporter = $stmt->fetch();

    $admins = $pdo->query(
        "SELECT email FROM map_users WHERE role = 'admin' AND locked_at IS NULL ORDER BY id"
    )->fetchAll(PDO::FETCH_COLUMN);

    require_once __DIR__ . '/mail.php';
    /* The mail is the administrator's, so it speaks German like the rest of
       what he reads — no t(): the reporter's language must not decide it. */
    $subject = 'YASAIMAPP: Meldung über ' . $other['display_name'];
    $body = "Eine Meldung aus YASAIMAPP.\n\n"
        . 'Gemeldet von: ' . $reporter['display_name'] . ' <' . $reporter['email'] . '> (Konto ' . (int) $reporter['id'] . ")\n"
        . 'Gemeldet wird: ' . $other['display_name'] . ' <' . $other['email'] . '> (Konto ' . (int) $other['id'] . ")\n"
        . 'Zeit: ' . date('d.m.Y H:i') . "\n\n"
        . "Was passiert ist, in den Worten der meldenden Person:\n\n"
        . $text . "\n\n"
        . "Ein Konto sperrst du in der Verwaltung unter Benutzerverwaltung:\n"
        . absolute_url('pages/administration.php') . "#members\n"
        . "Auf diese Mail zu antworten, schreibt der meldenden Person.\n";

    $sent = false;
    foreach ($admins as $address) {
        $sent = mail_send((string) $address, $subject, $body, ['reply_to' => (string) $reporter['email']]) || $sent;
    }
    if (!$sent) {
        error_log('friends report (' . $me . ' about ' . (int) $other['id'] . '): no mail handed on');
        throw new InvalidArgumentException(t('Die Meldung konnte nicht verschickt werden. Bitte versuch es später noch einmal.'));
    }
}

/**
 * Everything for the view: incoming and outgoing open requests and the
 * friends list, each with the other person's nick and name.
 *
 * @return array{incoming: array[], outgoing: array[], friends: array[]}
 */
function friends_overview(PDO $pdo, int $me): array
{
    $stmt = $pdo->prepare(
        'SELECT f.id, f.status, f.from_user_id, f.created_at,
                u.id AS user_id, ' . sql_display_name() . ' AS display_name, u.first_name, u.last_name
           FROM map_friends f
           JOIN map_users u ON u.id = IF(f.from_user_id = ?, f.to_user_id, f.from_user_id)
          WHERE f.from_user_id = ? OR f.to_user_id = ?
          ORDER BY display_name'
    );
    $stmt->execute([$me, $me, $me]);

    $overview = ['incoming' => [], 'outgoing' => [], 'friends' => []];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['status'] === 'accepted') {
            $overview['friends'][] = $row;
        } elseif ((int) $row['from_user_id'] === $me) {
            $overview['outgoing'][] = $row;
        } else {
            $overview['incoming'][] = $row;
        }
    }
    return $overview;
}

/*
 * THE ANSWER REACHES THE ASKER (13.09.2026: „Bekomme ich eine
 * Matchnachricht, wenn eine Freundschaftsanfrage angenommen wurde?"). He
 * did not: only the asked side had a badge. Migration 0105 gives the row
 * seen_at, and an accepted friendship the asker has not seen yet rides
 * along in the chat poll (api/v1/chat.php) as a badge over the map; its
 * cross calls friendship_seen(), so the second device stays quiet.
 */

/** The friendships I asked for that were accepted and I have not seen yet — [{id, name}]. */
function friends_accepted_unseen(PDO $pdo, int $me): array
{
    if (!friends_ready($pdo) || !column_exists($pdo, 'map_friends', 'seen_at')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT f.id, ' . sql_display_name() . " AS display_name
           FROM map_friends f
           JOIN map_users u ON u.id = f.to_user_id
          WHERE f.from_user_id = ? AND f.status = 'accepted' AND f.seen_at IS NULL
          ORDER BY f.answered_at, f.id"
    );
    $stmt->execute([$me]);
    $out = [];
    foreach ($stmt as $row) {
        $out[] = ['id' => (int) $row['id'], 'name' => (string) $row['display_name']];
    }
    return $out;
}

/** The asker has seen the acceptance — the badge may go, on every device. */
function friendship_seen(PDO $pdo, int $me, int $friendshipId): void
{
    if (!column_exists($pdo, 'map_friends', 'seen_at')) {
        return;
    }
    $pdo->prepare(
        "UPDATE map_friends SET seen_at = NOW()
          WHERE id = ? AND from_user_id = ? AND status = 'accepted' AND seen_at IS NULL"
    )->execute([$friendshipId, $me]);
}

/** How many open requests to me? Shown as a number on the tab. */
function friends_pending(PDO $pdo, int $userId): int
{
    if (!friends_ready($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM map_friends WHERE to_user_id = ? AND status = 'requested'"
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** The ids of the accepted friends — for groups and later containers. */
function friend_ids(PDO $pdo, int $me): array
{
    $stmt = $pdo->prepare(
        "SELECT IF(from_user_id = ?, to_user_id, from_user_id) FROM map_friends
          WHERE (from_user_id = ? OR to_user_id = ?) AND status = 'accepted'"
    );
    $stmt->execute([$me, $me, $me]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// ---------------------------------------------------------------------------
// Working groups
// ---------------------------------------------------------------------------

function group_create(PDO $pdo, int $me, string $name): int
{
    $name = mb_substr(trim($name), 0, 100);
    if ($name === '') {
        throw new InvalidArgumentException('Die Gruppe braucht einen Namen.');
    }
    try {
        $pdo->prepare('INSERT INTO map_groups (owner_user_id, name) VALUES (?, ?)')
            ->execute([$me, $name]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            throw new InvalidArgumentException('Du hast schon eine Gruppe mit diesem Namen.');
        }
        throw $e;
    }
    return (int) $pdo->lastInsertId();
}

function group_delete(PDO $pdo, int $me, int $groupId): void
{
    $stmt = $pdo->prepare('DELETE FROM map_groups WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$groupId, $me]);
    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('Diese Gruppe gibt es nicht mehr.');
    }
    $pdo->prepare('DELETE FROM map_group_members WHERE group_id = ?')->execute([$groupId]);

    // Containers that referred to the group lose the entry — and whoever
    // was a member only through it loses their shares.
    require_once __DIR__ . '/container.php';
    if (container_ready($pdo)) {
        $pdo->prepare("DELETE FROM map_container_members WHERE type = 'group' AND target_id = ?")
            ->execute([$groupId]);
        container_cleanup($pdo);
    }
}

/** Add to one's own group — friends only, see migration 0041. */
function group_add(PDO $pdo, int $me, int $groupId, int $userId): void
{
    $stmt = $pdo->prepare('SELECT 1 FROM map_groups WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$groupId, $me]);
    if ($stmt->fetchColumn() === false) {
        throw new InvalidArgumentException('Diese Gruppe gibt es nicht mehr.');
    }
    if (!in_array($userId, friend_ids($pdo, $me), true)) {
        throw new InvalidArgumentException('In eine Gruppe kannst du nur Freunde aufnehmen.');
    }
    // Adding twice is not an error, just a double click.
    $pdo->prepare('INSERT IGNORE INTO map_group_members (group_id, user_id) VALUES (?, ?)')
        ->execute([$groupId, $userId]);

    // If the group hangs in containers, their space has just grown. The
    // person acting knows that — their acknowledgment snapshot moves along
    // (containers with this group belong to them, only one's own groups
    // can be added). Everyone else gets the growth notice.
    require_once __DIR__ . '/container.php';
    if (container_ready($pdo) && additions_ready($pdo)) {
        $stmt = $pdo->prepare(
            "SELECT container_id FROM map_container_members WHERE type = 'group' AND target_id = ?"
        );
        $stmt->execute([$groupId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cId) {
            additions_seen($pdo, $me, (int) $cId);
        }
    }
}

/**
 * Remove from a group. Two may do that: the owner (throws out) and the
 * member themselves (leaves) — the same row, the same path.
 */
function group_remove(PDO $pdo, int $me, int $groupId, int $userId): void
{
    $stmt = $pdo->prepare('SELECT owner_user_id FROM map_groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $owner = $stmt->fetchColumn();
    if ($owner === false || ((int) $owner !== $me && $userId !== $me)) {
        throw new InvalidArgumentException('Das darfst nur du selbst oder der Besitzer der Gruppe.');
    }
    $pdo->prepare('DELETE FROM map_group_members WHERE group_id = ? AND user_id = ?')
        ->execute([$groupId, $userId]);

    // Whoever leaves the group leaves every container that went only through
    // it — their shares there are then left hanging in the air.
    require_once __DIR__ . '/container.php';
    container_cleanup($pdo);
}

/** The members of a group, alphabetically. The owner is not in the table —
 *  whoever wants to show them prepends them themselves. */
function group_members(PDO $pdo, int $groupId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, ' . sql_display_name() . ' AS display_name, u.first_name, u.last_name
           FROM map_group_members gm
           JOIN map_users u ON u.id = gm.user_id
          WHERE gm.group_id = ? ORDER BY display_name'
    );
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

/**
 * One's own groups (with members) and foreign ones I am a member of.
 *
 * THE FOREIGN ONES COME WITH MEMBERS TOO: Only the owner may manage them,
 * but everyone inside may see who else is in — with the group you share
 * your things, and whoever reads along should be visible (the maintainer's
 * decision, 13.08.2026).
 *
 * @return array{eigene: array[], mitglied: array[]}
 */
function groups_overview(PDO $pdo, int $me): array
{
    $stmt = $pdo->prepare('SELECT id, name FROM map_groups WHERE owner_user_id = ? ORDER BY name');
    $stmt->execute([$me]);
    $own = $stmt->fetchAll();

    foreach ($own as &$g) {
        $g['members'] = group_members($pdo, (int) $g['id']);
    }
    unset($g);

    $stmt = $pdo->prepare(
        'SELECT g.id, g.name, ' . sql_display_name() . ' AS owner_name
           FROM map_group_members gm
           JOIN map_groups g ON g.id = gm.group_id
           JOIN map_users u ON u.id = g.owner_user_id
          WHERE gm.user_id = ? ORDER BY g.name'
    );
    $stmt->execute([$me]);
    $memberOf = $stmt->fetchAll();

    foreach ($memberOf as &$g) {
        $g['members'] = group_members($pdo, (int) $g['id']);
    }
    unset($g);

    return ['own' => $own, 'member_of' => $memberOf];
}
