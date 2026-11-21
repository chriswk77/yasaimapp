<?php
/**
 * Finds shown to a friend — „Direkt teilen" (migration 0149,
 * 02.10.2026: „die direkt geteilten Punkte sind sowas wie Hinweise. Diesen
 * Hinweis kann der Freund wieder entfernen. Dabei wird der Punkt aber nicht
 * aus den anderen Listen, die ich mit ihm teile, entfernt. Also dass man
 * ihm eine Stelle zeigen kann, und er sie einfach bei Geteilt abrufen
 * kann").
 *
 * A HINT IS ONE ROW, NOT A LIST. The Freigaben (container.php) share lists,
 * with members, rights and a birth filter; a single find in that machinery
 * would have needed a list of its own in the holder's tree. Here the row
 * says: this find, shown to this friend, by this person — and the friend
 * reads it under „Geteilt" in a row „Von <Name>". READ ONLY: a hint shows a
 * spot, it hands out no right; whoever should tend the find gets it through
 * a share.
 *
 * WHO SHOWS: the holder of the find, to his accepted friends, one tick per
 * friend (the point window's tile and the tab under Gemeinsam). WHO ENDS
 * IT: the holder, per friend; the friend, for himself — the door, his
 * other ways to the find stay untouched.
 *
 * THE FRIENDSHIP IS ASKED ON EVERY READ as well (point_hints_friends_sql):
 * friendship_remove() deletes the pair's hints, and the reading side does
 * not rely on that alone — a hint never outlives the friendship it rests
 * on, not even between two statements.
 *
 * THE WAY TO THE DEVICE is the containers' and the excursions': buckets
 * with fingerprints (bucket.php?hints=1), stored under „h:" on the device,
 * so a shown find is readable without reception. One namespace for all
 * friends — the sidebar groups by who showed it, the store does not need
 * to.
 */

declare(strict_types=1);

require_once __DIR__ . '/container.php';
require_once __DIR__ . '/bucket.php';

/** Migration 0149 — the code goes live before it. */
function point_hints_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_point_hints');
}

/**
 * The SQL condition „the two are friends", accepted, either direction —
 * over two column or parameter expressions.
 */
function point_hints_friends_sql(string $giver, string $recipient): string
{
    return "EXISTS (SELECT 1 FROM map_friends f WHERE f.status = 'accepted'
              AND ((f.from_user_id = $giver AND f.to_user_id = $recipient)
                OR (f.from_user_id = $recipient AND f.to_user_id = $giver)))";
}

/**
 * The SQL condition „h's giver may show p": the holder, or a co-owner who is
 * not in his trash there (map_point_owners, 0098).
 *
 * CO-OWNERS SHARE TOO since 06.10.2026 („Mitbesitzer ja, Halter
 * bekommt Chatzeile"). Before, only the holder showed — and the tile hid on
 * every foreign find, a co-owner's included. A co-owner took pictures there;
 * he is part of the find, and sharing it with his own friends is his to do.
 * Whoever merely SEES a find — through a Freigabe or a direct share — may
 * not pass it on: then the holder would no longer decide who knows his spot.
 *
 * EVERY READ ASKS IT, as it asked „holder only" before: a find that changed
 * hands keeps no hint of the one who gave it away (point_hand_over deletes
 * them), and a co-owner who steps out into his trash takes his hints out of
 * sight with him until he comes back.
 */
function point_hints_giver_sql(): string
{
    return '(h.user_id = p.user_id
          OR EXISTS (SELECT 1 FROM map_point_owners o
                      WHERE o.point_id = p.id AND o.user_id = h.user_id AND o.deleted_at IS NULL))';
}

/** The hints that reach $userId, as a condition on map_points p (one ? for him). */
function point_hints_reaching_sql(): string
{
    return 'p.deleted_at IS NULL
        AND EXISTS (SELECT 1 FROM map_point_hints h
                     WHERE h.point_id = p.id AND h.recipient_id = ? AND ' . point_hints_giver_sql() . '
                       AND ' . point_hints_friends_sql('h.user_id', 'h.recipient_id') . ')';
}

/** May $userId show this find — is he its holder or one of its co-owners? */
function point_hint_may_show(PDO $pdo, int $userId, int $pointId): ?bool
{
    $stmt = $pdo->prepare('SELECT user_id FROM map_points WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$pointId]);
    $holder = $stmt->fetchColumn();
    if ($holder === false) {
        return null;
    }
    if ((int) $holder === $userId) {
        return true;
    }
    require_once __DIR__ . '/point_owners.php';
    return point_owner_is($pdo, $pointId, $userId) ? false : null;
}

// ---------------------------------------------------------------------------
// The holder's side
// ---------------------------------------------------------------------------

/**
 * To whom this find is shown — by me, by its holder or by a co-owner —
 * and which of them somebody else showed it to. Friend ids, ascending.
 *
 * ALL OF THEM, not only mine: the tile ticks a friend who sees the find,
 * whoever ticked him first (a recipient is one row, PRIMARY KEY point and
 * recipient). The ones somebody else gave are named apart, because only
 * the giver and the holder can take them back (point_hint_set).
 *
 * @return array{all: int[], others: int[]}
 */
function point_hint_recipients(PDO $pdo, int $userId, int $pointId): array
{
    if (!point_hints_ready($pdo)) {
        return ['all' => [], 'others' => []];
    }
    $stmt = $pdo->prepare(
        'SELECT h.recipient_id, h.user_id FROM map_point_hints h
           JOIN map_points p ON p.id = h.point_id
          WHERE h.point_id = ? AND ' . point_hints_giver_sql() . '
            AND ' . point_hints_friends_sql('h.user_id', 'h.recipient_id') . '
          ORDER BY h.recipient_id'
    );
    $stmt->execute([$pointId]);
    $all = $others = [];
    foreach ($stmt as $row) {
        $all[] = (int) $row['recipient_id'];
        if ((int) $row['user_id'] !== $userId) {
            $others[] = (int) $row['recipient_id'];
        }
    }
    return ['all' => $all, 'others' => $others];
}

/**
 * Every find I show, and to whom — point id => friend ids. For the map's
 * start data: the tile in the point window draws its ticks from it without
 * a request per opening, and it stays a handful of rows.
 *
 * @return array<int, int[]>
 */
function point_hints_given_map(PDO $pdo, int $userId): array
{
    return point_hints_given_maps($pdo, $userId)['all'];
}

/**
 * Both maps for the start data: every find I hold or co-own → everyone it
 * is shown to, and → those somebody else showed it to (see
 * point_hint_recipients for why the second exists).
 *
 * @return array{all: array<int, int[]>, others: array<int, int[]>}
 */
function point_hints_given_maps(PDO $pdo, int $userId): array
{
    if (!point_hints_ready($pdo)) {
        return ['all' => [], 'others' => []];
    }
    $stmt = $pdo->prepare(
        'SELECT h.point_id, h.recipient_id, h.user_id FROM map_point_hints h
           JOIN map_points p ON p.id = h.point_id AND p.deleted_at IS NULL
          WHERE (p.user_id = ?
                 OR EXISTS (SELECT 1 FROM map_point_owners mo
                             WHERE mo.point_id = p.id AND mo.user_id = ? AND mo.deleted_at IS NULL))
            AND ' . point_hints_giver_sql() . '
            AND ' . point_hints_friends_sql('h.user_id', 'h.recipient_id') . '
          ORDER BY h.point_id, h.recipient_id'
    );
    $stmt->execute([$userId, $userId]);
    $all = $others = [];
    foreach ($stmt as $row) {
        $all[(int) $row['point_id']][] = (int) $row['recipient_id'];
        if ((int) $row['user_id'] !== $userId) {
            $others[(int) $row['point_id']][] = (int) $row['recipient_id'];
        }
    }
    return ['all' => $all, 'others' => $others];
}

/**
 * Show one find of mine to one friend, or stop showing it to him.
 *
 * ONE FRIEND PER CALL, and on purpose: the tile and the tab both tick one
 * name at a time, and „Ben out, Carla stays" (02.10.2026) is
 * then simply what happens — there is no set to overwrite that could lose
 * Carla on the way.
 *
 * @return int[] the friends it is shown to afterwards
 */
function point_hint_set(PDO $pdo, int $userId, int $pointId, int $friendId, bool $on): array
{
    if (!point_hints_ready($pdo)) {
        throw new RuntimeException(t('Direkt teilen braucht die Migration 0149 — sie ist noch nicht eingespielt.'));
    }
    // The holder (true) or a co-owner (false); anybody else gets the same
    // answer as for a find that does not exist.
    $holder = point_hint_may_show($pdo, $userId, $pointId);
    if ($holder === null) {
        throw new InvalidArgumentException(t('Punkt nicht gefunden.'));
    }

    if ($on) {
        require_once __DIR__ . '/friends.php';
        if (!in_array($friendId, friend_ids($pdo, $userId), true)) {
            throw new InvalidArgumentException(t('Direkt teilen kannst du nur mit deinen Freunden.'));
        }
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO map_point_hints (point_id, recipient_id, user_id, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $insert->execute([$pointId, $friendId, $userId]);
        if ($insert->rowCount() > 0) {
            point_hint_announce($pdo, $userId, $friendId, $pointId);
            if (!$holder) {
                point_hint_tell_holder($pdo, $userId, $friendId, $pointId);
            }
        }
    } else {
        /* TAKING BACK: one's own share, and the holder every share of his
           find — he decides who knows his spot, and his tab under
           „Direkt geteilt" lists what his co-owners shared. */
        $delete = $pdo->prepare('DELETE FROM map_point_hints WHERE point_id = ? AND recipient_id = ?'
            . ($holder ? '' : ' AND user_id = ?'));
        $delete->execute($holder ? [$pointId, $friendId] : [$pointId, $friendId, $userId]);
        if ($delete->rowCount() === 0
            && in_array($friendId, point_hint_recipients($pdo, $userId, $pointId)['others'], true)) {
            throw new InvalidArgumentException(t('Zurücknehmen kann das nur, wer es geteilt hat, oder wem der Punkt gehört.'));
        }
    }
    return point_hint_recipients($pdo, $userId, $pointId)['all'];
}

/**
 * THE FRIEND HEARS OF IT in the chat the two share (02.10.2026:
 * „im Chat eine kleine Statusmeldung: Anna hat mit dir den Punkt
 * Boletus edulis geteilt. Findest du bei den Listen unter Geteilt, dort bei
 * Direkt geteilt und dort Von Anna"). Only for a NEW share — a second
 * tick on the same friend inserts nothing and says nothing. The way there
 * is named with the words the sidebar shows, heading by heading — and it
 * starts at the tab („bei den Listen", his wording), because a friend who
 * stands on „Punkte" would look for „Geteilt" in vain. Written in the sharer's language,
 * as the shares' change lines are (joint_line).
 */
function point_hint_announce(PDO $pdo, int $userId, int $friendId, int $pointId): void
{
    require_once __DIR__ . '/chat.php';
    // The SHARER's name, not the holder's: the sidebar's row „Von <Name>"
    // groups by who shared (point_hints_givers), and since co-owners share
    // that is not always the holder.
    $stmt = $pdo->prepare(
        'SELECT p.title, ' . sql_display_name() . ' AS name
           FROM map_points p JOIN map_users u ON u.id = ? WHERE p.id = ?'
    );
    $stmt->execute([$userId, $pointId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return;
    }
    $title = (string) $row['title'] !== '' ? (string) $row['title'] : t('Ohne Titel');
    friend_chat_note($pdo, $userId, $friendId,
        t('hat den Punkt „%s" direkt mit dir geteilt. Du findest ihn bei den Listen unter „Geteilt" → „Direkt geteilt" → „%s".',
            $title, t('Von %s', (string) $row['name'])),
        ['hint' => $pointId]);
}

/**
 * THE HOLDER HEARS WHEN A CO-OWNER SHARES HIS FIND (06.10.2026:
 * „Halter bekommt Chatzeile"). In the chat the two share as friends; where
 * they are none — a co-owner comes in through a Freigabe as often as not —
 * in the chat of a Freigabe both belong to that carries the find. Where
 * neither exists (an excursion) there is no room for the line, and the
 * holder sees it in his tab „Direkt geteilt", where he can take it back.
 */
function point_hint_tell_holder(PDO $pdo, int $sharer, int $friendId, int $pointId): void
{
    require_once __DIR__ . '/chat.php';
    $stmt = $pdo->prepare(
        'SELECT p.user_id, p.title, ' . sql_display_name() . ' AS friend
           FROM map_points p JOIN map_users u ON u.id = ? WHERE p.id = ?'
    );
    $stmt->execute([$friendId, $pointId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return;
    }
    $holder = (int) $row['user_id'];
    $title  = (string) $row['title'] !== '' ? (string) $row['title'] : t('Ohne Titel');
    $text   = t('hat deinen Punkt „%s" direkt mit %s geteilt. Zurücknehmen kannst du das in der Verwaltung unter „Gemeinsam" → „Direkt geteilt".',
        $title, (string) $row['friend']);
    $meta   = ['hint' => $pointId];
    if (friend_chat_note($pdo, $sharer, $holder, $text, $meta) !== null) {
        return;
    }
    // A Freigabe both belong to in which the find's list hangs.
    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $lists = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($lists === [] || !container_ready($pdo)) {
        return;
    }
    foreach (container_mine($pdo, $sharer) as $c) {
        if (in_array($holder, $c['member_ids'], true)
            && array_intersect($lists, array_map('intval', container_list_ids($pdo, $c['id']))) !== []) {
            chat_note($pdo, $sharer, $c['id'], $text, $meta);
            return;
        }
    }
}

/**
 * My shown finds for the tab under Gemeinsam — newest find first, each
 * with the friends it is shown to.
 *
 * @return array<int, array{point_id:int, title:string, found_at:?string, created_at:string, municipality:?string, recipients: array<int, array{id:int, name:string}>}>
 */
function point_hints_given(PDO $pdo, int $userId): array
{
    if (!point_hints_ready($pdo)) {
        return [];
    }
    /* WHAT I SHARED, AND ON MY OWN FINDS WHAT MY CO-OWNERS SHARED (06.10.2026):
       the holder takes a co-owner's share back here, so it has to stand here
       — with the co-owner's name on it ('by'), and possibly a recipient who
       is no friend of the holder at all. */
    $stmt = $pdo->prepare(
        'SELECT h.point_id, p.title, p.found_at, p.created_at, p.municipality,
                h.recipient_id, h.user_id AS giver_id, ' . sql_display_name() . ' AS name,
                ' . sql_display_name('g') . ' AS giver
           FROM map_point_hints h
           JOIN map_points p ON p.id = h.point_id AND p.deleted_at IS NULL
           JOIN map_users u ON u.id = h.recipient_id
           JOIN map_users g ON g.id = h.user_id
          WHERE (h.user_id = ? OR p.user_id = ?)
            AND ' . point_hints_giver_sql() . '
            AND ' . point_hints_friends_sql('h.user_id', 'h.recipient_id') . '
          ORDER BY COALESCE(p.found_at, p.created_at) DESC, h.point_id, name'
    );
    $stmt->execute([$userId, $userId]);
    $out = [];
    foreach ($stmt as $row) {
        $id = (int) $row['point_id'];
        $out[$id] ??= [
            'point_id'     => $id,
            'title'        => (string) $row['title'],
            'found_at'     => $row['found_at'],
            'created_at'   => (string) $row['created_at'],
            'municipality' => $row['municipality'],
            'recipients'   => [],
        ];
        $out[$id]['recipients'][] = [
            'id'   => (int) $row['recipient_id'],
            'name' => (string) $row['name'],
            // Who shared it, where that is not me — a co-owner of my find.
            'by'   => (int) $row['giver_id'] === $userId ? null : (string) $row['giver'],
        ];
    }
    return array_values($out);
}

// ---------------------------------------------------------------------------
// The friend's side
// ---------------------------------------------------------------------------

/**
 * Take a hint away — for oneself alone. The find stays where it is: in
 * its holder's lists, and in every share that brings it to me otherwise.
 */
function point_hint_leave(PDO $pdo, int $recipientId, int $pointId): bool
{
    if (!point_hints_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM map_point_hints WHERE point_id = ? AND recipient_id = ?');
    $stmt->execute([$pointId, $recipientId]);
    return $stmt->rowCount() > 0;
}

/** Is this find shown to me? The seeing gate asks (point_view_scope). */
function point_hint_reaches(PDO $pdo, int $userId, int $pointId): bool
{
    if (!point_hints_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_points p WHERE p.id = ? AND ' . point_hints_reaching_sql() . ' LIMIT 1');
    $stmt->execute([$pointId, $userId]);
    return $stmt->fetchColumn() !== false;
}

/**
 * The friends who show me finds, with how many — the rows „Von <Name>"
 * under „Geteilt".
 *
 * @return array<int, array{id:int, name:string, count:int}>
 */
function point_hints_givers(PDO $pdo, int $userId): array
{
    if (!point_hints_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT h.user_id AS id, ' . sql_display_name() . ' AS name, COUNT(*) AS count
           FROM map_point_hints h
           JOIN map_points p ON p.id = h.point_id AND p.deleted_at IS NULL
           JOIN map_users u ON u.id = h.user_id
          WHERE h.recipient_id = ? AND ' . point_hints_giver_sql() . '
            AND ' . point_hints_friends_sql('h.user_id', 'h.recipient_id') . '
          GROUP BY h.user_id, name
          ORDER BY name'
    );
    $stmt->execute([$userId]);
    return array_map(static fn(array $r): array => [
        'id' => (int) $r['id'], 'name' => (string) $r['name'], 'count' => (int) $r['count'],
    ], $stmt->fetchAll());
}

/**
 * The finds shown to me for the tab under Gemeinsam.
 *
 * @return array<int, array{point_id:int, title:string, found_at:?string, created_at:string, municipality:?string, giver_id:int, giver:string}>
 */
function point_hints_received(PDO $pdo, int $userId): array
{
    if (!point_hints_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT h.point_id, p.title, p.found_at, p.created_at, p.municipality,
                h.user_id AS giver_id, ' . sql_display_name() . ' AS giver
           FROM map_point_hints h
           JOIN map_points p ON p.id = h.point_id AND p.deleted_at IS NULL
           JOIN map_users u ON u.id = h.user_id
          WHERE h.recipient_id = ? AND ' . point_hints_giver_sql() . '
            AND ' . point_hints_friends_sql('h.user_id', 'h.recipient_id') . '
          ORDER BY COALESCE(p.found_at, p.created_at) DESC, h.point_id'
    );
    $stmt->execute([$userId]);
    return array_map(static fn(array $r): array => [
        'point_id'     => (int) $r['point_id'],
        'title'        => (string) $r['title'],
        'found_at'     => $r['found_at'],
        'created_at'   => (string) $r['created_at'],
        'municipality' => $r['municipality'],
        'giver_id'     => (int) $r['giver_id'],
        'giver'        => (string) $r['giver'],
    ], $stmt->fetchAll());
}

/**
 * The finds shown to me, prepared like every foreign point — and marked:
 * 'hint_from' is who showed it, 'hint_from_name' his name. They hang in no
 * list I may see ('lists' stays empty); the sidebar's row „Von <Name>" and
 * sharedVisible() in map.js go by 'hint_from'.
 *
 * @param string[] $buckets  only these buckets, or all
 */
function point_hints_points(PDO $pdo, int $userId, array $buckets = []): array
{
    if (!point_hints_ready($pdo)) {
        return [];
    }
    $where  = [point_hints_reaching_sql()];
    $values = [$userId];
    if ($buckets !== []) {
        $where[] = bucket_expression('p') . ' IN (' . implode(',', array_fill(0, count($buckets), '?')) . ')';
        $values  = array_merge($values, $buckets);
    }
    $stmt = $pdo->prepare(
        'SELECT p.*, ' . sql_display_name() . ' AS owner_name
           FROM map_points p
           JOIN map_users u ON u.id = p.user_id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY p.updated_at DESC'
    );
    $stmt->execute($values);
    $points = $stmt->fetchAll();
    if ($points === []) {
        return [];
    }

    // Who showed each — the holder at the time, mostly the holder still.
    $ids  = array_map(static fn(array $p): int => (int) $p['id'], $points);
    $stmt = $pdo->prepare(
        'SELECT h.point_id, h.user_id, ' . sql_display_name() . ' AS name
           FROM map_point_hints h JOIN map_users u ON u.id = h.user_id
          WHERE h.recipient_id = ? AND h.point_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
    );
    $stmt->execute(array_merge([$userId], $ids));
    $from = [];
    foreach ($stmt as $row) {
        $from[(int) $row['point_id']] = ['id' => (int) $row['user_id'], 'name' => (string) $row['name']];
    }

    /* HOW IT LOOKS AND WHAT IT CARRIES, from the holder's list. A foreign
       point takes colour, shape and fields from the shared list it arrives
       through — a hint arrives through none, and would stand grey and
       round with its Substrat unread. So it brings its look along: colour,
       shape and fields of the list it lies in. The holder's first list
       decides. */
    $stmt = $pdo->prepare(
        'SELECT pl.point_id, l.id, l.color, l.symbol FROM map_point_lists pl
           JOIN map_lists l ON l.id = pl.list_id
           JOIN map_points p ON p.id = pl.point_id AND l.user_id = p.user_id
          WHERE pl.point_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
          ORDER BY pl.point_id, l.id'
    );
    $stmt->execute($ids);
    $leaf = [];
    foreach ($stmt as $row) {
        $leaf[(int) $row['point_id']] ??= ['id' => (int) $row['id'], 'color' => (string) $row['color'],
                                           'symbol' => (string) ($row['symbol'] ?: 'circle')];
    }
    $roots = [];   // list id => [symbol, fields], asked once per list
    $look = static function (int $listId, string $symbol) use ($pdo, &$roots): array {
        return $roots[$listId] ??= [
            'symbol' => $symbol,
            'fields' => array_map(static fn(array $f): array => array_merge($f, ['is_species' => false]),
                list_fields_of_root($pdo, $listId)),
        ];
    };

    $prepared = foreign_points_prepare($pdo, $points, []);
    foreach ($prepared as &$p) {
        $pid = (int) $p['id'];
        $p['hint_from']      = $from[$pid]['id'] ?? (int) $p['owner_id'];
        $p['hint_from_name'] = $from[$pid]['name'] ?? (string) $p['owner'];
        if (isset($leaf[$pid])) {
            $root = $look($leaf[$pid]['id'], $leaf[$pid]['symbol']);
            $p['hint_look']   = ['color' => $leaf[$pid]['color'], 'symbol' => $root['symbol']];
            $p['hint_fields'] = $root['fields'];
        }
    }
    unset($p);
    return $prepared;
}

/**
 * Fingerprint and count per bucket over the finds shown to me — the twin
 * of container_bucket_status(), the same three figures. A hint set or
 * taken away changes the count and the sum, so the device fetches the
 * month again; a find that changes moves touched_at as everywhere.
 *
 * @return array{finger: array<string,string>, counts: array<string,int>}
 */
function point_hints_bucket_status(PDO $pdo, int $userId): array
{
    if (!bucket_ready($pdo) || !point_hints_ready($pdo)) {
        return ['finger' => [], 'counts' => []];
    }
    $stmt = $pdo->prepare(
        'SELECT ' . bucket_expression('p') . ' AS bucket_key,
                COUNT(*) AS total,
                SUBSTRING(MD5(CONCAT_WS("|",
                    COUNT(*),
                    MAX(p.touched_at),
                    COALESCE(SUM(CRC32(CONCAT_WS(":", p.id, p.touched_at))), 0),
                    ' . bucket_derived_stamp($pdo) . '
                )), 1, 12) AS finger
           FROM map_points p
          WHERE ' . point_hints_reaching_sql() . '
          GROUP BY bucket_key
          ORDER BY bucket_key DESC'
    );
    $stmt->execute([$userId]);
    $finger = [];
    $counts = [];
    foreach ($stmt as $row) {
        if ($row['bucket_key'] === null || $row['bucket_key'] === '') {
            continue;
        }
        $finger[(string) $row['bucket_key']] = (string) $row['finger'];
        $counts[(string) $row['bucket_key']] = (int) $row['total'];
    }
    return ['finger' => $finger, 'counts' => $counts];
}

/**
 * The points of requested buckets — the twin of container_bucket_points(),
 * and THE KEYS ARE SPELLED THE SAME ('buckets', 'open', 'points'): the
 * device reads all of them through one code path, and a name that differs
 * stores empty buckets as complete (see there).
 *
 * @param string[] $keys
 * @return array{buckets: array<string,array{finger:string,points:array}>, open: string[]}
 */
function point_hints_bucket_points(PDO $pdo, int $userId, array $keys): array
{
    $requested = array_values(array_unique(array_filter($keys, 'bucket_key_valid')));
    if (!bucket_ready($pdo) || !point_hints_ready($pdo) || $requested === []) {
        return ['buckets' => [], 'open' => []];
    }
    $status = point_hints_bucket_status($pdo, $userId);

    // Whole buckets up to the limit, the rest reported open — never a
    // bucket cut in half (includes/bucket.php, BUCKET_MAX).
    $take  = [];
    $open  = [];
    $total = 0;
    foreach ($requested as $key) {
        $n = $status['counts'][$key] ?? 0;
        if ($take !== [] && $total + $n > BUCKET_MAX) {
            $open[] = $key;
            continue;
        }
        $take[] = $key;
        $total += $n;
    }

    $delivered = [];
    foreach ($take as $key) {
        $delivered[$key] = ['finger' => $status['finger'][$key] ?? 'empty', 'points' => []];
    }
    foreach ($take === [] ? [] : point_hints_points($pdo, $userId, $take) as $point) {
        $key = bucket_key_of($point);
        if (isset($delivered[$key])) {
            $delivered[$key]['points'][] = $point;
        }
    }
    return ['buckets' => $delivered, 'open' => $open];
}
