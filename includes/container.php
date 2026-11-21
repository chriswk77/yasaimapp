<?php
/**
 * Shared containers — the logic of sharing, separated from the view.
 *
 * The model sits in migration 0042: a container has members, each member
 * hangs their own lists into it, points always belong to their creator.
 * Here stands who is a member, what they see and which branches may go in.
 * Errors from user input come as InvalidArgumentException with a text for
 * the message — the same agreement as in data.php and friends.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/friends.php';
// For container_points(): point_prepare() along with everything it hangs on.
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/taxonomy.php';
// The rescue anchor (level 4) belongs to sharing: where containers are, it is.
require_once __DIR__ . '/original.php';
// One share per list (migration 0160) lives beside the boxes, on their engine.
require_once __DIR__ . '/list_shares.php';

function container_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_containers')
        && table_exists($pdo, 'map_container_members')
        && table_exists($pdo, 'map_container_lists');
}

// ---------------------------------------------------------------------------
// Containers and members
// ---------------------------------------------------------------------------

/**
 * $filter is the fully normalized birth filter (layout in migration 0050)
 * or null. It is written HERE and only here — there is deliberately no
 * function that changes it: "I only shared the excursion list" must still
 * be true tomorrow.
 */
/**
 * „DIREKT GETEILT" IS TAKEN (02.10.2026: „dieser Name müsste
 * natürlich reserviert werden"). Under „Geteilt" a friend's finds shown to
 * me gather in a row of that name (drawShared in map.js), standing among
 * the Freigaben — a Freigabe called the same would be two rows nobody can
 * tell apart. Checked in the German key and in the reader's language,
 * whatever the case and the spaces.
 */
function container_name_reserved(string $name): bool
{
    $folded = mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)) ?? '');
    return $folded === mb_strtolower('Direkt geteilt') || $folded === mb_strtolower(t('Direkt geteilt'));
}

function container_name_refuse_reserved(string $name): void
{
    if (container_name_reserved($name)) {
        throw new InvalidArgumentException(t('„Direkt geteilt" ist schon vergeben: So heißt unter „Geteilt" die Sammelzeile für die Punkte, die Freunde direkt mit dir teilen.'));
    }
}

function container_create(PDO $pdo, int $userId, string $name, ?array $filter = null): int
{
    $name = mb_substr(trim($name), 0, 100);
    if ($name === '') {
        throw new InvalidArgumentException(t('Die Freigabe braucht einen Namen.'));
    }
    container_name_refuse_reserved($name);
    if ($filter !== null && !filter_ready($pdo)) {
        throw new InvalidArgumentException(t('Filter brauchen die Migration 0050 — sie ist noch nicht eingespielt.'));
    }
    try {
        if ($filter !== null) {
            // PRESERVE_ZERO_FRACTION: otherwise the corner 51.0 would become
            // 51 when encoding, an integer when reading — and a filter that
            // comes back different from how it was stored is none.
            $pdo->prepare('INSERT INTO map_containers (owner_user_id, name, filter) VALUES (?, ?, ?)')
                ->execute([$userId, $name, json_encode($filter, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)]);
        } else {
            $pdo->prepare('INSERT INTO map_containers (owner_user_id, name) VALUES (?, ?)')
                ->execute([$userId, $name]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            throw new InvalidArgumentException(t('Du hast schon eine Freigabe mit diesem Namen.'));
        }
        throw $e;
    }
    return (int) $pdo->lastInsertId();
}

function container_delete(PDO $pdo, int $userId, int $containerId): void
{
    $stmt = $pdo->prepare('DELETE FROM map_containers WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$containerId, $userId]);
    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException(t('Diese Freigabe gibt es nicht mehr.'));
    }
    // The shares go with the container — for the shared lists and their
    // points that changes nothing, they were only hung in. EXCEPT THE
    // JOINT ONES (step 3, 22.09.2026): they go back to their sharers, and
    // the other members' points go back to the other members — the chat
    // dies with the container, so this is the one departure nobody reads.
    $pdo->prepare('DELETE FROM map_container_members WHERE container_id = ?')->execute([$containerId]);
    if (share_joint_ready($pdo)) {
        require_once __DIR__ . '/joint_lists.php';
        $q = $pdo->prepare('SELECT id, list_id, user_id FROM map_container_lists WHERE container_id = ? AND joint = 1');
        $q->execute([$containerId]);
        foreach ($q->fetchAll() as $f) {
            joint_unfile_others($pdo, (int) $f['user_id'], (int) $f['list_id'], (int) $f['id']);
        }
    }
    $pdo->prepare('DELETE FROM map_container_lists WHERE container_id = ?')->execute([$containerId]);
    if (additions_ready($pdo)) {
        $pdo->prepare('DELETE FROM map_container_state WHERE container_id = ?')->execute([$containerId]);
    }
}

/**
 * Who (effectively) belongs to the container? Owner, direct friends and the
 * members of referenced groups — the group counts live, not as a copy taken
 * when adding (see migration 0042).
 *
 * @return int[]
 */
function container_member_ids(PDO $pdo, int $containerId): array
{
    $stmt = $pdo->prepare('SELECT owner_user_id FROM map_containers WHERE id = ?');
    $stmt->execute([$containerId]);
    $owner = $stmt->fetchColumn();
    if ($owner === false) {
        return [];
    }

    $ids = [(int) $owner];

    $stmt = $pdo->prepare(
        "SELECT target_id FROM map_container_members WHERE container_id = ? AND type = 'friend'"
    );
    $stmt->execute([$containerId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $ids[] = (int) $id;
    }

    $stmt = $pdo->prepare(
        "SELECT gm.user_id
           FROM map_container_members cm
           JOIN map_group_members gm ON gm.group_id = cm.target_id
          WHERE cm.container_id = ? AND cm.type = 'group'"
    );
    $stmt->execute([$containerId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $ids[] = (int) $id;
    }

    $ids = array_values(array_unique($ids));

    /* AND THEN THOSE WHO WALKED OUT COME OFF AGAIN (migration 0067).
       The subtraction happens HERE and nowhere else, and that is the whole
       trick: visibility, the buckets, the chat and container_cleanup() all
       ask this one function who belongs — so a departure takes effect in
       every one of them without a single further line. Whoever writes a
       fourth way in writes it above this block, and the exit keeps
       working.
       THE OWNER IS EXEMPT. He cannot walk out of his own container; for
       him there is deleting, and that is a different deed with different
       consequences. Without this exception a stray row would make a
       container ownerless, and container_cleanup() would sweep the shares
       of everybody. */
    if (container_left_ready($pdo)) {
        $stmt = $pdo->prepare('SELECT user_id FROM map_container_left WHERE container_id = ?');
        $stmt->execute([$containerId]);
        $gone = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($gone !== []) {
            $ids = array_values(array_filter(
                $ids,
                static fn(int $id): bool => $id === (int) $owner || !in_array($id, $gone, true)
            ));
        }
    }

    return $ids;
}

/**
 * Is the departure table there? The code goes live before the migration
 * (CLAUDE.md) — until then nobody has left, and everything behaves as it
 * did. Asked once per request, because it is asked in a loop.
 */
function container_left_ready(PDO $pdo): bool
{
    static $there = null;
    if ($there === null) {
        $there = table_exists($pdo, 'map_container_left');
    }
    return $there;
}

/**
 * Leave a container — for oneself alone.
 *
 * THE WAY OUT FOR EVERY KIND OF ACCESS. Whoever was admitted as a friend
 * could always be removed; whoever sits in through a group could not, and
 * their only lever was to leave the whole group — which drops them out of
 * every container it hangs in at once (22.08.2026). Now both
 * cases end here, and the direct entry goes with it: a row that says "may"
 * next to a row that says "has left" would be a contradiction waiting for
 * somebody to read only one of them.
 *
 * The lists this person had hung in are unhooked by container_cleanup(),
 * because after this they are no longer a member. That is the same path
 * every other departure takes; nothing special happens here.
 */
function container_leave(PDO $pdo, int $userId, int $containerId): void
{
    if (!container_left_ready($pdo)) {
        throw new InvalidArgumentException(t('Austreten braucht die Migration 0067 — sie ist noch nicht eingespielt.'));
    }

    $stmt = $pdo->prepare('SELECT owner_user_id FROM map_containers WHERE id = ?');
    $stmt->execute([$containerId]);
    $owner = $stmt->fetchColumn();
    if ($owner === false) {
        throw new InvalidArgumentException(t('Diese Freigabe gibt es nicht mehr.'));
    }
    if ((int) $owner === $userId) {
        throw new InvalidArgumentException(t('Aus der eigenen Freigabe tritt man nicht aus — man löscht sie.'));
    }
    if (!container_visible($pdo, $userId, $containerId)) {
        throw new InvalidArgumentException(t('Du bist in dieser Freigabe nicht dabei.'));
    }

    $pdo->prepare(
        'INSERT INTO map_container_left (container_id, user_id, left_at) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE left_at = VALUES(left_at)'
    )->execute([$containerId, $userId]);

    $pdo->prepare(
        "DELETE FROM map_container_members
          WHERE container_id = ? AND type = 'friend' AND target_id = ?"
    )->execute([$containerId, $userId]);

    container_cleanup($pdo);
}

/**
 * The members of a container, with their names.
 *
 * ON TOP OF container_member_ids() AND NOT NEXT TO IT: who counts as a
 * member is a question with three answers (owner, direct friends, group
 * members), and it is answered there. A second query with the same rules
 * would be the same rules twice — and the second copy is the one that gets
 * forgotten when a fourth way in is added.
 *
 * @return array<int,array{id: int, display_name: string}> the owner first,
 *   the rest by name: in a list of members the owner is not one row among
 *   others, and sorting him into the middle makes him one.
 */
function container_members(PDO $pdo, int $containerId): array
{
    $ids = container_member_ids($pdo, $containerId);
    if ($ids === []) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT owner_user_id FROM map_containers WHERE id = ?');
    $stmt->execute([$containerId]);
    $owner = (int) $stmt->fetchColumn();

    $space = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare(
        // The alias 'u' is not decoration: sql_display_name() builds its
        // expression from u.first_name and u.last_name.
        'SELECT u.id, ' . sql_display_name() . ' AS display_name
           FROM map_users u
          WHERE u.id IN (' . $space . ')
          ORDER BY display_name'
    );
    $stmt->execute($ids);

    $rows = array_map(static fn(array $z): array => [
        'id'          => (int) $z['id'],
        'display_name' => $z['display_name'],
    ], $stmt->fetchAll());

    usort($rows, static fn(array $a, array $b): int =>
        ($b['id'] === $owner ? 1 : 0) - ($a['id'] === $owner ? 1 : 0));

    return $rows;
}

/**
 * Rename a container — for everybody. Only its owner may.
 *
 * THIS DID NOT EXIST UNTIL NOW, not even in the administration: a
 * container got its name when it was created and kept it. That was
 * bearable as long as one only ever made new ones, and it stops being
 * bearable the moment a container survives a season
 * (20.08.2026).
 *
 * NOT THE SAME AS container_own_name(). This one changes what the members
 * see; that one changes what only I see. Which of the two the window
 * offers is decided by who is standing in front of it — and the owner is
 * offered this one, because for him a private label beside his own name
 * would be a distinction without a difference.
 */
function container_rename(PDO $pdo, int $userId, int $containerId, string $name): void
{
    $stmt = $pdo->prepare('SELECT 1 FROM map_containers WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$containerId, $userId]);
    if ($stmt->fetchColumn() === false) {
        throw new InvalidArgumentException(t('Nur wer die Freigabe angelegt hat, kann sie umbenennen.'));
    }

    $name = mb_substr(trim($name), 0, 100);
    if ($name === '') {
        throw new InvalidArgumentException(t('Die Freigabe braucht einen Namen.'));
    }
    container_name_refuse_reserved($name);

    try {
        $pdo->prepare('UPDATE map_containers SET name = ? WHERE id = ? AND owner_user_id = ?')
            ->execute([$name, $containerId, $userId]);
    } catch (PDOException $e) {
        // The same unique key as on creation: one name per owner.
        if ($e->getCode() === '23000') {
            throw new InvalidArgumentException(t('Du hast schon eine Freigabe mit diesem Namen.'));
        }
        throw $e;
    }
}

/**
 * How I call this container. Empty means: as its creator does.
 *
 * ONLY FOR ME, AND NOBODY ELSE SEES IT. That is the whole difference from
 * renaming: the owner keeps his name, the other members keep theirs, and
 * nobody has to be asked. Which is why it does not go into map_containers
 * but into the row this member already has for this container.
 *
 * NULL AND NOT AN EMPTY STRING when it is cleared. Whoever empties the
 * field wants the original back — that is something else than a container
 * with no name at all, and COALESCE in container_mine() can only tell them
 * apart if the difference is kept here.
 */
function container_own_name(PDO $pdo, int $userId, int $containerId, ?string $name): void
{
    if (!container_visible($pdo, $userId, $containerId)) {
        throw new InvalidArgumentException(t('Du bist in dieser Freigabe nicht mehr dabei.'));
    }

    $name = $name === null ? null : mb_substr(trim($name), 0, 100);
    if ($name === '') {
        $name = null;
    }
    // The own name stands under „Geteilt" too, beside the collective row.
    if ($name !== null) {
        container_name_refuse_reserved($name);
    }

    // The row may not exist yet — somebody who has never looked at the
    // members or the chat has none. 'members' takes an empty string on
    // the insert: the column is NOT NULL, and an empty list is what it
    // means (see chat_mark_read, same table, same reason).
    $pdo->prepare(
        'INSERT INTO map_container_state (user_id, container_id, members, own_name, updated_at)
              VALUES (?, ?, \'\', ?, NOW())
         ON DUPLICATE KEY UPDATE own_name = VALUES(own_name)'
    )->execute([$userId, $containerId, $name]);
}

function container_visible(PDO $pdo, int $userId, int $containerId): bool
{
    return in_array($userId, container_member_ids($pdo, $containerId), true);
}

/**
 * Add a friend or a group. Only the owner may do either, and only from
 * their own circle: friends must be accepted friends, groups their own.
 */
function container_add(PDO $pdo, int $userId, int $containerId, string $type, int $targetId): void
{
    container_refuse_list_share($pdo, $containerId);
    $stmt = $pdo->prepare('SELECT 1 FROM map_containers WHERE id = ? AND owner_user_id = ?');
    $stmt->execute([$containerId, $userId]);
    if ($stmt->fetchColumn() === false) {
        throw new InvalidArgumentException(t('Diese Freigabe gibt es nicht mehr.'));
    }

    if ($type === 'friend') {
        if (!in_array($targetId, friend_ids($pdo, $userId), true)) {
            throw new InvalidArgumentException(t('In eine Freigabe kannst du nur Freunde aufnehmen.'));
        }
    } elseif ($type === 'group') {
        $stmt = $pdo->prepare('SELECT 1 FROM map_groups WHERE id = ? AND owner_user_id = ?');
        $stmt->execute([$targetId, $userId]);
        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException(t('Nur eigene Arbeitsgruppen lassen sich aufnehmen.'));
        }
    } else {
        throw new InvalidArgumentException(t('Unbekannte Art.'));
    }

    $pdo->prepare('INSERT IGNORE INTO map_container_members (container_id, type, target_id) VALUES (?, ?, ?)')
        ->execute([$containerId, $type, $targetId]);

    /* ADMITTING SOMEBODY AGAIN LIFTS THEIR DEPARTURE (migration 0067), and
       only for the friend: that is an act aimed at this one person, and
       whoever says "come back in" means it. A GROUP is different — it is
       aimed at the group, and somebody who walked out of this container on
       purpose should not be dragged back in because the owner happened to
       admit a circle they belong to. Their door stays shut until they are
       named. */
    if ($type === 'friend' && container_left_ready($pdo)) {
        $pdo->prepare('DELETE FROM map_container_left WHERE container_id = ? AND user_id = ?')
            ->execute([$containerId, $targetId]);
    }

    // The one adding has the new state before their eyes — their snapshot
    // moves along, so that their own move is not reported to them as
    // growth. Everyone else with shares sees the notice.
    additions_seen($pdo, $userId, $containerId);
}

/**
 * Take an entry (friend or group) back out — the owner throws out, a direct
 * member may remove themselves (leave).
 *
 * WITH THE MEMBERSHIP FALL THE SHARES of everyone who is thereby outside:
 * a hung-in list from somebody who is no longer a member would be a share
 * to people they never meant.
 */
function container_remove(PDO $pdo, int $userId, int $containerId, string $type, int $targetId): void
{
    $stmt = $pdo->prepare('SELECT owner_user_id FROM map_containers WHERE id = ?');
    $stmt->execute([$containerId]);
    $owner = $stmt->fetchColumn();
    if ($owner === false) {
        throw new InvalidArgumentException(t('Diese Freigabe gibt es nicht mehr.'));
    }

    $allowed = (int) $owner === $userId || ($type === 'friend' && $targetId === $userId);
    if (!$allowed) {
        throw new InvalidArgumentException(t('Das darf nur der Besitzer der Freigabe.'));
    }
    if ((int) $owner === $userId) {
        container_refuse_list_share($pdo, $containerId);
    }

    $pdo->prepare('DELETE FROM map_container_members WHERE container_id = ? AND type = ? AND target_id = ?')
        ->execute([$containerId, $type, $targetId]);

    container_cleanup($pdo);
}

/**
 * Unhook shares without membership — across ALL containers.
 *
 * Membership can end in many places: removal from the container, leaving a
 * group, deleting a group, the end of a friendship, deleting an account.
 * Instead of calculating the affected containers individually at each of
 * these places (and forgetting one), there is ONE cleaner that restores the
 * state as a whole: a share exists only from somebody who is a member.
 * Containers are few — the full pass costs nothing.
 *
 * Not only the removed person's shares fall: if a group flies out of the
 * container, it hits everyone who was in only through it.
 */
function container_cleanup(PDO $pdo): void
{
    if (!container_ready($pdo)) {
        return;
    }
    $stmt = $pdo->query('SELECT DISTINCT container_id FROM map_container_lists');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cId) {
        $remaining = container_member_ids($pdo, (int) $cId);
        /* THE JOINT LISTS ARE NOT SWEPT LIKE THE REST (joint lists, step 3,
           22.09.2026). Leaving the circle — walking out, being taken out, a
           friendship ending — must not take the group's list along: the
           senior remaining member takes it (joint_list_hand_over), and
           only without one does the row fall below like any other. A
           departed member's points go with him out of the joint lists
           (joint_unfile_departed): they are his, and a list he cannot see
           is no place for them. And when the container itself is gone,
           every joint list goes back to its sharer, the others' points to
           the others — the chat died with it, nobody can be told here. */
        require_once __DIR__ . '/joint_lists.php';
        $withJoint = share_joint_ready($pdo);
        if ($remaining === []) {
            // The container itself is gone — its shares with it.
            if ($withJoint) {
                $q = $pdo->prepare('SELECT id, list_id, user_id FROM map_container_lists WHERE container_id = ? AND joint = 1');
                $q->execute([(int) $cId]);
                foreach ($q->fetchAll() as $f) {
                    joint_unfile_others($pdo, (int) $f['user_id'], (int) $f['list_id'], (int) $f['id']);
                }
            }
            $pdo->prepare('DELETE FROM map_container_lists WHERE container_id = ?')->execute([$cId]);
            continue;
        }
        $space = implode(',', array_fill(0, count($remaining), '?'));
        if ($withJoint) {
            $q = $pdo->prepare("SELECT id, list_id, user_id FROM map_container_lists
                                 WHERE container_id = ? AND joint = 1 AND user_id NOT IN ($space)");
            $q->execute(array_merge([(int) $cId], $remaining));
            foreach ($q->fetchAll() as $f) {
                if (joint_list_hand_over($pdo, (int) $f['user_id'], (int) $f['list_id'], (int) $cId) === null) {
                    joint_unfile_others($pdo, (int) $f['user_id'], (int) $f['list_id'], (int) $f['id']);
                }
            }
            joint_unfile_departed($pdo, (int) $cId, $remaining);
        }
        $pdo->prepare("DELETE FROM map_container_lists WHERE container_id = ? AND user_id NOT IN ($space)")
            ->execute(array_merge([(int) $cId], $remaining));
    }

    // Acknowledgement snapshots (migration 0049) without one's own share in
    // the container are corpses — the notice only asks where there are
    // shares, so the row loses its purpose with the last share. Also covers
    // deleted containers and deleted accounts.
    if (additions_ready($pdo)) {
        $pdo->exec(
            'DELETE g FROM map_container_state g
              LEFT JOIN map_container_lists cl
                ON cl.container_id = g.container_id AND cl.user_id = g.user_id
             WHERE cl.id IS NULL'
        );
    }
}

/**
 * In which containers a group has been admitted.
 *
 * FOR THE GROUP TAB, and it answers the question one really has there:
 * whoever adds somebody to a group is not adding them to the group but to
 * everything the group opens. That consequence stood nowhere — one saw a
 * name being added and had to remember, or guess, where it would arrive
 * (22.08.2026).
 *
 * Only the containers of the person asking: a group of mine can only ever
 * have been admitted by me, but a foreign container that admitted it would
 * be none of the group owner's business to enumerate — and cannot happen
 * anyway, because container_add() takes only one's OWN groups.
 *
 * @return list<array{id: int, name: string}> by name
 */
function container_using_group(PDO $pdo, int $groupId): array
{
    if (!container_ready($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT c.id, c.name
           FROM map_container_members cm
           JOIN map_containers c ON c.id = cm.container_id
          WHERE cm.type = 'group' AND cm.target_id = ?
          ORDER BY c.name"
    );
    $stmt->execute([$groupId]);

    return array_map(
        static fn(array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']],
        $stmt->fetchAll()
    );
}

/**
 * The entries of the member table with a readable name — friends as nick,
 * groups as group name. For the view in the management area.
 */
function container_entries(PDO $pdo, int $containerId): array
{
    $stmt = $pdo->prepare(
        "SELECT cm.type, cm.target_id,
                CASE cm.type WHEN 'friend' THEN COALESCE(NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.email) ELSE g.name END AS name
           FROM map_container_members cm
           LEFT JOIN map_users u   ON cm.type = 'friend' AND u.id = cm.target_id
           LEFT JOIN map_groups g ON cm.type = 'group' AND g.id = cm.target_id
          WHERE cm.container_id = ?
          ORDER BY cm.type, name"
    );
    $stmt->execute([$containerId]);
    return array_map(static fn(array $z): array => [
        'type'     => $z['type'],
        'target_id' => (int) $z['target_id'],
        // An entry whose target is meanwhile missing (deleted user, because
        // the friendship cleanup does not know this table yet) should stay
        // visible instead of empty — then it can be taken out.
        'name'    => $z['name'] ?? t('(nicht mehr vorhanden)'),
    ], $stmt->fetchAll());
}

/**
 * A LIST SHARE IS SET UP AT ITS LIST, NOT HERE (migration 0160). The old
 * doors — hanging a list in, taking people in, rights per share, the
 * „gemeinsam" switch — would break its one rule (one list, its owner
 * decides per recipient), so on a list share they refuse.
 */
function container_refuse_list_share(PDO $pdo, int $containerId): void
{
    if (list_share_list($pdo, $containerId) !== null) {
        throw new InvalidArgumentException(t('Diese Freigabe stellt der Besitzer an seiner Liste ein, im Fenster „Liste bearbeiten".'));
    }
}

// ---------------------------------------------------------------------------
// Shares
// ---------------------------------------------------------------------------

/**
 * The lists a share stands for — per shared list, the list itself.
 *
 * THE NAME IS FROM THE TREE. Until the Mappen (migration 0168, live
 * 11.10.2026) a share of a heading reached every sublist below it, and
 * this walked the branch. Lists are flat now, so a share is exactly its
 * list; the function stays, with its shape, because some twenty places ask
 * it, and an answer of one list per share is still the right answer.
 *
 * @param array[] $shares  Rows from map_container_lists (list_id, user_id)
 * @return array<int,int[]>   share list_id => [list_id]
 */
function container_branches(PDO $pdo, array $shares): array
{
    $branches = [];
    foreach ($shares as $f) {
        $branches[(int) $f['list_id']] = [(int) $f['list_id']];
    }
    return $branches;
}

/**
 * Hang one's own list (or a branch) into the container.
 *
 * NO OVERLAP: whoever has already shared "Wunschziele" cannot additionally
 * hang in "Wunschziele → Schiefergruben" — the same points would stand in
 * the container twice, and every count over them would lie.
 */
function share_create(PDO $pdo, int $userId, int $containerId, int $listId): void
{
    container_refuse_list_share($pdo, $containerId);
    if (!container_visible($pdo, $userId, $containerId)) {
        throw new InvalidArgumentException(t('Du bist kein Mitglied dieser Freigabe.'));
    }

    $stmt = $pdo->prepare('SELECT id FROM map_lists WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$listId, $userId]);
    if ($stmt->fetchColumn() === false) {
        throw new InvalidArgumentException(t('Diese Liste gehört dir nicht (mehr).'));
    }

    $stmt = $pdo->prepare('SELECT list_id, user_id FROM map_container_lists WHERE container_id = ? AND user_id = ?');
    $stmt->execute([$containerId, $userId]);
    $mine = $stmt->fetchAll();

    if ($mine !== []) {
        // The new branch together with the existing ones through
        // container_branches(): if it lies in an existing branch or an existing
        // one in its, they overlap.
        $all      = array_merge($mine, [['list_id' => $listId, 'user_id' => $userId]]);
        $branches = container_branches($pdo, $all);
        $new      = $branches[$listId] ?? [$listId];
        foreach ($mine as $m) {
            $old = $branches[(int) $m['list_id']] ?? [];
            if (in_array($listId, $old, true) || in_array((int) $m['list_id'], $new, true)) {
                throw new InvalidArgumentException(
                    t('Dieser Zweig überschneidet sich mit einer Liste, die du hier schon teilst.')
                );
            }
        }
    }

    $pdo->prepare('INSERT IGNORE INTO map_container_lists (container_id, list_id, user_id) VALUES (?, ?, ?)')
        ->execute([$containerId, $listId, $userId]);

    // Whoever hangs in has seen the circle at this moment — the first
    // snapshot comes into being here. ONLY the first (INSERT IGNORE, no
    // REPLACE): a further share must not silently swallow a growth notice
    // that is still unacknowledged.
    if (additions_ready($pdo)) {
        $pdo->prepare('INSERT IGNORE INTO map_container_state (user_id, container_id, members) VALUES (?, ?, ?)')
            ->execute([$userId, $containerId, json_encode(container_member_ids($pdo, $containerId))]);
    }
}

/** Taking out is allowed for the sharer themselves and the container's owner. */
function share_remove(PDO $pdo, int $userId, int $shareId): void
{
    share_refuse_list_share($pdo, $shareId);
    $withJoint = share_joint_ready($pdo);
    $stmt = $pdo->prepare(
        'SELECT cl.user_id, cl.list_id, cl.container_id, c.owner_user_id' . ($withJoint ? ', cl.joint' : '') . '
           FROM map_container_lists cl
           JOIN map_containers c ON c.id = cl.container_id
          WHERE cl.id = ?'
    );
    $stmt->execute([$shareId]);
    $row = $stmt->fetch();
    if ($row === false) {
        throw new InvalidArgumentException(t('Diese Freigabe gibt es nicht mehr.'));
    }
    if ((int) $row['user_id'] !== $userId && (int) $row['owner_user_id'] !== $userId) {
        throw new InvalidArgumentException(t('Das darf nur, wer geteilt hat — oder der Besitzer der Freigabe.'));
    }
    // A joint list unhooked (step 3, 22.09.2026): the others' points go
    // back to them, and the chat says who did it.
    if ($withJoint && (int) $row['joint'] === 1) {
        require_once __DIR__ . '/joint_lists.php';
        $name = joint_list_name($pdo, (int) $row['list_id']);
        $n    = joint_unfile_others($pdo, (int) $row['user_id'], (int) $row['list_id'], $shareId);
        joint_note($pdo, $userId, $n === 0
            ? t('hat die gemeinsame Liste „%s" ausgehängt', $name)
            : t('hat die gemeinsame Liste „%s" ausgehängt — die Punkte der anderen liegen jetzt bei ihnen in einer eigenen Liste „%s"', $name, joint_home_name($name)),
            [(int) $row['container_id']]);
    }
    $pdo->prepare('DELETE FROM map_container_lists WHERE id = ?')->execute([$shareId]);
}

// ---------------------------------------------------------------------------
// Rights: what the other members may do to foreign points
//
// They are granted by the SHARER on their share (migration 0043) and apply
// to all members equally. Finer (per member) would be conceivable and is
// deliberately not built: whomever you trust less than the others, you do
// not take into the container.
// ---------------------------------------------------------------------------

/** The fixed keys; per extra field of the root, "field:<name>" is added. */
const SHARE_RIGHTS = ['move', 'delete', 'title', 'note', 'delete_photos'];

/** The same refusal, reached through a share row. */
function share_refuse_list_share(PDO $pdo, int $shareId): void
{
    $stmt = $pdo->prepare('SELECT container_id FROM map_container_lists WHERE id = ?');
    $stmt->execute([$shareId]);
    $cId = $stmt->fetchColumn();
    if ($cId !== false) {
        container_refuse_list_share($pdo, (int) $cId);
    }
}

function share_rights_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_container_lists', 'rights');
}

// ---------------------------------------------------------------------------
// Joint lists — „gemeinsam bearbeiten" (migration 0110)
//
// A hung-in list the members fill and tend together, every one of them
// with every right (21./22.09.2026: „eine Liste, an der man
// gemeinsam arbeiten kann, die jeder füllen kann und auf die jeder volle
// Rechte hat — ähnlich wie bei den Exkursionen"). ONE FLAG ON THE SHARE:
// the list stays its owner's, the share stays the circle, and inside the
// circle the members are the owner's equals — the rights of 0043 all at
// once, plus the one those could not say: setting points INTO the list.
// A point keeps its holder, the member who set it; the others reach it
// through point_foreign_rights like the sharer's own points.
//
// Step 1. The list's own trash (filings, per list) and the handover chain
// follow as steps 2 and 3, each with its own migration.
// ---------------------------------------------------------------------------

/** Whether a share can be switched to „gemeinsam bearbeiten". */
function share_joint_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_container_lists', 'joint');
}

/**
 * Every right there is on this share — SHARE_RIGHTS plus one field: right
 * per extra field of the sharer's root. What a joint list grants to every
 * member; handed out as the same list of keys the granted rights use, so
 * the browser's union of rights (sharedRights) needs no second kind of
 * answer and every existing gate keeps working.
 */
function share_rights_full(PDO $pdo, int $listId): array
{
    $fields = array_column(list_fields_of_root($pdo, $listId), 'name');
    return array_merge(SHARE_RIGHTS, array_map(static fn(string $n): string => 'field:' . $n, $fields));
}

/**
 * Switch a share to „gemeinsam bearbeiten" — or back. Only the sharer,
 * like the rights: the points are set into HIS list, and he decides who
 * fills it.
 */
function share_joint_set(PDO $pdo, int $userId, int $shareId, bool $on): void
{
    share_refuse_list_share($pdo, $shareId);
    if (!share_joint_ready($pdo)) {
        throw new InvalidArgumentException(t('Gemeinsame Listen brauchen die Migration 0110 — sie ist noch nicht eingespielt.'));
    }
    $stmt = $pdo->prepare('SELECT user_id, list_id, container_id, joint FROM map_container_lists WHERE id = ?');
    $stmt->execute([$shareId]);
    $row = $stmt->fetch();
    if ($row === false) {
        throw new InvalidArgumentException(t('Diese Freigabe gibt es nicht mehr.'));
    }
    if ((int) $row['user_id'] !== $userId) {
        throw new InvalidArgumentException(t('Gemeinsam bearbeiten schaltet nur, wem die geteilte Liste gehört.'));
    }
    /* SWITCHED OFF, THE LIST IS THE SHARER'S AGAIN (step 3, 22.09.2026):
       the others' points go back to them — a list they may only read is
       no place for their finds —, and the chat says what happened. */
    if (!$on && (int) $row['joint'] === 1) {
        require_once __DIR__ . '/joint_lists.php';
        $name = joint_list_name($pdo, (int) $row['list_id']);
        $n    = joint_unfile_others($pdo, $userId, (int) $row['list_id'], $shareId);
        joint_note($pdo, $userId, $n === 0
            ? t('hat „%s" nicht mehr gemeinsam gestellt', $name)
            : t('hat „%s" nicht mehr gemeinsam gestellt — die Punkte der anderen liegen jetzt bei ihnen in einer eigenen Liste „%s"', $name, joint_home_name($name)),
            [(int) $row['container_id']]);
    }
    $pdo->prepare('UPDATE map_container_lists SET joint = ? WHERE id = ?')->execute([$on ? 1 : 0, $shareId]);
}

/**
 * The lists $userId may file points into although they are not his: every
 * list of a joint branch in a share he is a member of. His own lists are
 * left out — they are his anyway, and point_lists_set takes them through
 * its own door. Leaf and auto-list conditions are the caller's (they are
 * the same for own and joint lists, and point_lists_set spells them once).
 *
 * @return int[]
 */
function joint_list_ids(PDO $pdo, int $userId): array
{
    if (!container_ready($pdo) || !share_joint_ready($pdo)) {
        return [];
    }
    $shares = $pdo->query('SELECT container_id, list_id, user_id FROM map_container_lists WHERE joint = 1')->fetchAll();
    $mine   = [];
    $member = [];   // containerId -> bool, asked once per container
    foreach ($shares as $f) {
        if ((int) $f['user_id'] === $userId) {
            continue;
        }
        $cId = (int) $f['container_id'];
        // In a list share only whoever has the tick „eigene Punkte setzen".
        $member[$cId] ??= list_share_list($pdo, $cId) !== null
            ? !empty(list_share_viewer($pdo, $userId, $cId)['own_points'])
            : container_visible($pdo, $userId, $cId);
        if ($member[$cId]) {
            $mine[] = $f;
        }
    }
    if ($mine === []) {
        return [];
    }
    return array_values(array_unique(array_merge(...array_values(container_branches($pdo, $mine)))));
}

/** The lists of a container's joint branches — for the birth filter, which passes them by. */
function container_joint_list_ids(PDO $pdo, int $containerId): array
{
    if (!share_joint_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT list_id, user_id FROM map_container_lists WHERE container_id = ? AND joint = 1');
    $stmt->execute([$containerId]);
    $shares = $stmt->fetchAll();
    if ($shares === []) {
        return [];
    }
    return array_values(array_unique(array_merge(...array_values(container_branches($pdo, $shares)))));
}

/** @return string[] The column read — NULL and anything unusable means read-only. */
function rights_read(mixed $json): array
{
    if (!is_string($json) || $json === '') {
        return [];
    }
    $list = json_decode($json, true);
    return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
}

/**
 * An old box's rights in today's words. Its single right „Belegbilder
 * löschen" also let one attach pictures and set scale and measures on
 * them (photo_foreign_rights, point_attach_scope); a list share names the
 * three apart (LIST_SHARE_RIGHTS). Spelled out here, so every gate asks
 * for the new key alone and an old box keeps what it granted until it is
 * converted.
 */
function rights_compat(array $rights): array
{
    if (in_array('delete_photos', $rights, true)) {
        $rights[] = 'attach_photos';
        $rights[] = 'measure';
    }
    return array_values(array_unique($rights));
}

/**
 * Set the rights of a share — only the sharer themselves.
 *
 * Unknown keys fail loudly instead of vanishing silently: a right one
 * believes to be granting that never arrives would be the most unpleasant
 * sort of error. field: keys must hit a field of the sharer's root list.
 */
function share_rights_set(PDO $pdo, int $userId, int $shareId, array $rights): void
{
    share_refuse_list_share($pdo, $shareId);
    if (!share_rights_ready($pdo)) {
        throw new InvalidArgumentException(t('Rechte brauchen die Migration 0043 — sie ist noch nicht eingespielt.'));
    }

    $stmt = $pdo->prepare('SELECT list_id, user_id FROM map_container_lists WHERE id = ?');
    $stmt->execute([$shareId]);
    $share = $stmt->fetch();
    if ($share === false) {
        throw new InvalidArgumentException(t('Diese Freigabe gibt es nicht mehr.'));
    }
    if ((int) $share['user_id'] !== $userId) {
        throw new InvalidArgumentException(t('Rechte vergibt nur, wem die geteilte Liste gehört.'));
    }

    $fields = array_column(list_fields_of_root($pdo, (int) $share['list_id']), 'name');

    $clean = [];
    foreach ($rights as $r) {
        $r = (string) $r;
        $valid = in_array($r, SHARE_RIGHTS, true)
            || (str_starts_with($r, 'field:') && in_array(substr($r, 6), $fields, true));
        if (!$valid) {
            throw new InvalidArgumentException(t('Unbekanntes Recht: %s', $r));
        }
        $clean[] = $r;
    }
    $clean = array_values(array_unique($clean));

    $pdo->prepare('UPDATE map_container_lists SET rights = ? WHERE id = ?')
        ->execute([$clean === [] ? null : json_encode($clean, JSON_UNESCAPED_UNICODE), $shareId]);
}

/**
 * What I may do to a foreign point — or null if I see it through no
 * container (to the outside the same as: it does not exist).
 *
 * The point can arrive at me through SEVERAL shares (two containers, two
 * branches). Then the union of the rights applies: whoever allows me to
 * move in one container means the same point even when I additionally see
 * it elsewhere.
 *
 * @return ?array{owner:int, rights:string[], visible_lists:int[]}
 */
function point_foreign_rights(PDO $pdo, int $userId, int $pointId): ?array
{
    if (!container_ready($pdo)) {
        return null;
    }

    // Right away with position, title and find time: the respective
    // container's birth filter needs them below.
    $stmt = $pdo->prepare(
        'SELECT user_id, lat, lon, title, found_at
           FROM map_points WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$pointId]);
    $point = $stmt->fetch();
    if ($point === false || (int) $point['user_id'] === $userId) {
        return null;
    }
    $owner = (int) $point['user_id'];

    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $pointLists = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($pointLists === []) {
        return null;
    }

    $withRights = share_rights_ready($pdo);
    $withJoint  = share_joint_ready($pdo);
    /* THE OWNER'S SHARES — AND EVERY JOINT SHARE (22.09.2026). A share
       speaks for its sharer's points: whoever hung a list in decides what
       the members may do to what lies in it. A JOINT list is filled by the
       members themselves, so a point in it was set by somebody who did not
       hang the list in — and the share that reaches it is not the
       holder's. Joint shares are therefore read whoever hung them in; a
       share that is not joint still counts only for its sharer's points. */
    $stmt = $pdo->prepare(
        'SELECT container_id, list_id, user_id' . ($withRights ? ', rights' : '') . ($withJoint ? ', joint' : '') . '
           FROM map_container_lists WHERE user_id = ?' . ($withJoint ? ' OR joint = 1' : '')
    );
    $stmt->execute([$owner]);
    $shares = $stmt->fetchAll();
    if ($shares === []) {
        return null;
    }

    $branches = container_branches($pdo, $shares);

    $rights   = null;   // null = no share hits the point
    $full     = null;   // the whole row, fetched once for a list share's filter
    $joint    = false;  // whether one of the hitting shares is a joint one
    $visible  = [];
    $isMember = [];     // containerId -> bool, asked once per container
    $filter   = [];     // containerId -> ?array, likewise
    foreach ($shares as $f) {
        $isJoint = $withJoint && (int) ($f['joint'] ?? 0) === 1;
        if (!$isJoint && (int) $f['user_id'] !== $owner) {
            continue;
        }
        $branch = $branches[(int) $f['list_id']] ?? [];
        $hits   = array_intersect($branch, $pointLists);
        if ($hits === []) {
            continue;
        }
        $cId = (int) $f['container_id'];
        $isMember[$cId] ??= container_visible($pdo, $userId, $cId);
        if (!$isMember[$cId]) {
            continue;
        }
        /* A LIST SHARE (migration 0160) SPEAKS PER RECIPIENT: the rights
           are the union of the rows that reach me, and so is the filter —
           the sidebar's points filter, for every point of the list, the
           ones members set into it included. And it is never written AS
           THE HOLDER: even in a list one works in together, one may do to
           the others' finds exactly what is ticked
           (08.10.2026 — no levels, the ticks are the whole answer). */
        if (list_share_list($pdo, $cId) !== null) {
            $viewer = list_share_viewer($pdo, $userId, $cId);
            if ($viewer === null) {
                continue;
            }
            if ($viewer['filters'] !== null) {
                $full ??= (function () use ($pdo, $pointId): array {
                    $q = $pdo->prepare('SELECT * FROM map_points WHERE id = ?');
                    $q->execute([$pointId]);
                    return $q->fetch() ?: [];
                })();
                if (list_share_filter_pass($pdo, [$full], $viewer['filters']) === []) {
                    continue;
                }
            }
            $rights  = array_merge($rights ?? [], $viewer['rights']);
            $visible = array_merge($visible, $hits);
            continue;
        }
        // The birth filter applies PER container: if the point falls through
        // here, another share (other container, without a filter or with a
        // more permeable one) can still show it. NOT FOR A JOINT LIST
        // (22.09.2026): what somebody sets into it, he means for
        // the group, and a filter over it would only swallow finds.
        if (!$isJoint) {
            $filter[$cId] ??= container_filter($pdo, $cId);
            if (!container_filter_matches($filter[$cId], $point)) {
                continue;
            }
        }
        $rights  = array_merge($rights ?? [], rights_compat($isJoint
            ? share_rights_full($pdo, (int) $f['list_id'])
            : ($withRights ? rights_read($f['rights'] ?? null) : [])));
        $joint   = $joint || $isJoint;
        $visible = array_merge($visible, $hits);
    }

    if ($rights === null) {
        return null;
    }
    return [
        'owner'        => $owner,
        'rights'          => array_values(array_unique($rights)),
        'visible_lists' => array_values(array_unique($visible)),
        // A joint point is written AS THE HOLDER (points.php): no sift, no
        // anchor — the members are his equals in this list.
        'joint'        => $joint,
    ];
}

/**
 * Sift a foreign editor's input against their rights.
 *
 * WHAT IS NOT ALLOWED FAILS LOUDLY — with one exception: in the extra
 * block, keys that are not allowed silently stay at the stored state. The
 * editor only sends what they changed there; if every old value of a locked
 * field sent along became an error, saving the note would fail because the
 * owner next door just touched a different field.
 *
 * The interface only shows what is allowed anyway — this filter is the net
 * for everything that does not come through the interface.
 */
function foreign_input_sift(PDO $pdo, int $pointId, array $rights, array $input): array
{
    // field_times are accompanying data, not a field — they only decide
    // whether the fields sent along win (includes/field_times.php).
    $free    = ['field_times' => true];
    $mapping = [
        'lat'   => 'move',
        'lon'   => 'move',
        // THE OUTLINE IS GEOMETRY LIKE lat/lon (the call,
        // 14.08.2026): whoever may move the spot may also drag the area —
        // both move the same object, and when dragging corners the computed
        // center moves along anyway (the client then sends lat/lon in the
        // same call). A separate right beside it would mean handing the
        // sharer a distinction that does not exist in the subject matter.
        // null means remove — that too is a geometry change.
        'outline' => 'move',
        'title' => 'title',
        'note' => 'note',
    ];

    $data = [];
    foreach ($input as $field => $value) {
        if (isset($free[$field])) {
            $data[$field] = $value;
            continue;
        }
        if (isset($mapping[$field])) {
            if (!in_array($mapping[$field], $rights, true)) {
                throw new InvalidArgumentException(t('Dafür hat dir der Besitzer kein Recht gegeben.'));
            }
            $data[$field] = $value;
            continue;
        }
        if ($field === 'extra' || $field === 'extra_sources') {
            continue; // handled separately below
        }
        throw new InvalidArgumentException(t('An fremden Punkten lässt sich „%s“ nicht ändern.', $field));
    }

    if (array_key_exists('extra', $input)) {
        $stmt = $pdo->prepare('SELECT extra FROM map_points WHERE id = ?');
        $stmt->execute([$pointId]);
        $raw = $stmt->fetchColumn();
        $old = is_string($raw) ? (json_decode($raw, true) ?: []) : [];

        // Take allowed keys over from the input, empty means emptying
        // (point_extra_write throws out empty values anyway) — all
        // others stay as they are stored.
        $new = $old;
        foreach ((array) $input['extra'] as $name => $value) {
            if (!in_array('field:' . $name, $rights, true)) {
                continue;
            }
            $new[$name] = $value;
        }
        $data['extra'] = $new;

        if (array_key_exists('extra_sources', $input)) {
            $data['extra_sources'] = array_filter(
                (array) $input['extra_sources'],
                static fn($name) => in_array('field:' . $name, $rights, true),
                ARRAY_FILTER_USE_KEY
            );
        }
    }

    return $data;
}

/**
 * A foreign point in the shape a member may see it in — the same answer as
 * container_points(), just for a single one. The response to a foreign
 * PATCH: private things (the remaining lists at home, the field times)
 * stay out.
 */
function foreign_point_load(PDO $pdo, int $userId, int $pointId): ?array
{
    $access = point_foreign_rights($pdo, $userId, $pointId);
    if ($access === null) {
        return null;
    }
    $point = point_load($pdo, $access['owner'], $pointId);
    if ($point === null) {
        return null;
    }
    $point['lists'] = $access['visible_lists'];
    unset($point['field_times']);

    $stmt = $pdo->prepare('SELECT ' . sql_display_name() . ' FROM map_users u WHERE u.id = ?');
    $stmt->execute([$access['owner']]);
    $point['owner']    = (string) $stmt->fetchColumn();
    $point['owner_id'] = $access['owner'];
    return $point;
}

/**
 * May I touch this foreign photo? The right hangs on the photo's point —
 * of the share through which I see it: delete_photos to remove it,
 * measure to store scale, measures and its name (08.10.2026:
 * measuring on a picture one sees is always allowed, but only for oneself
 * — storing it over the owner's is a right of its own).
 *
 * @return ?array{owner:int}
 */
function photo_foreign_rights(PDO $pdo, int $userId, int $photoId, string $right = 'delete_photos'): ?array
{
    $stmt = $pdo->prepare('SELECT user_id, point_id FROM map_photos WHERE id = ?');
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();
    if ($photo === false || (int) $photo['user_id'] === $userId) {
        return null;
    }
    $access = point_foreign_rights($pdo, $userId, (int) $photo['point_id']);
    if ($access === null || !in_array($right, $access['rights'], true)) {
        return null;
    }
    return ['owner' => (int) $photo['user_id']];
}

// ---------------------------------------------------------------------------
// Reading: what a member sees
// ---------------------------------------------------------------------------

/**
 * All containers that concern me — my own and those I am a member of. For
 * management area and map alike.
 *
 * VIA THE MEMBER LIST PER CONTAINER, not via one big JOIN: the group
 * resolution (live, not copied) sits in container_member_ids(), and it
 * is supposed to exist exactly once. Containers are few; the loop costs
 * nothing.
 */
function container_mine(PDO $pdo, int $userId): array
{
    /* THE OWN NAME COMES ALONG, and the sorting follows it. Whoever calls
       a container something of his own does so in order to find it — if
       the row then sorted itself by the creator's name, the renaming would
       have moved the label and not the place, and one would search twice.
       Migration 0062; before it the column is not there and everything
       stays as it was. */
    $ownName = column_exists($pdo, 'map_container_state', 'own_name');

    $stmt = $pdo->prepare(
        'SELECT c.id, c.name, c.owner_user_id, ' . sql_display_name() . ' AS owner_name'
        . ($ownName ? ', g.own_name' : ', NULL AS own_name') . '
           FROM map_containers c
           JOIN map_users u ON u.id = c.owner_user_id
           LEFT JOIN map_container_state g
                  ON g.container_id = c.id AND g.user_id = ?
          ORDER BY ' . ($ownName ? 'COALESCE(g.own_name, c.name)' : 'c.name')
    );
    $stmt->execute([$userId]);

    $out = [];
    foreach ($stmt->fetchAll() as $c) {
        $members = container_member_ids($pdo, (int) $c['id']);
        if (in_array($userId, $members, true)) {
            $c['id']               = (int) $c['id'];
            $c['owner_user_id'] = (int) $c['owner_user_id'];
            $c['member_ids']   = $members;
            // What one calls it oneself, and what the creator called it —
            // both travel. The window shows the second next to the first.
            $c['own_name']     = $c['own_name'] ?? null;
            $out[]                = $c;
        }
    }
    return $out;
}

/**
 * The shares of a container, each with the list branch behind it and the
 * fields of the sharer's ROOT — the fields always come from there, no
 * matter which branch hangs (see migration 0042).
 *
 * @return array[]  per share: id, user_id, display_name, lists (rows like
 *                  lists_load, only the branch), fields (of the root)
 */
function container_shares(PDO $pdo, int $containerId, ?int $viewerId = null): array
{
    $withRights = share_rights_ready($pdo);
    $withJoint  = share_joint_ready($pdo);
    $stmt = $pdo->prepare(
        'SELECT cl.id, cl.list_id, cl.user_id, ' . sql_display_name() . ' AS display_name'
        . ($withRights ? ', cl.rights' : '') . ($withJoint ? ', cl.joint' : '') . '
           FROM map_container_lists cl
           JOIN map_users u ON u.id = cl.user_id
          WHERE cl.container_id = ?
          ORDER BY display_name, cl.id'
    );
    $stmt->execute([$containerId]);
    $shares = $stmt->fetchAll();
    if ($shares === []) {
        return [];
    }

    $branches = container_branches($pdo, $shares);

    // All list rows involved in one go, including the point count per list
    // — the same shape as lists_load(), so that the browser can treat
    // both alike.
    $allIds = array_values(array_unique(array_merge(...array_values($branches))));
    $space  = implode(',', array_fill(0, count($allIds), '?'));
    // What each list holds (0195) — its title's suggestions and the packages the recipient's device keeps.
    $withGroups = column_exists($pdo, 'map_lists', 'species_groups');
    $stmt   = $pdo->prepare(
        "SELECT l.id, l.parent_id, l.user_id, l.name, l.color, l.symbol, l.sort_order"
        . ($withGroups ? ', l.species_groups' : '') . ", (
                SELECT COUNT(*)
                  FROM map_point_lists pl
                  JOIN map_points p ON p.id = pl.point_id AND p.deleted_at IS NULL
                 WHERE pl.list_id = l.id
             ) AS total
           FROM map_lists l
          WHERE l.id IN ($space)
          ORDER BY l.sort_order, l.name"
    );
    $stmt->execute($allIds);
    $rows = [];
    foreach ($stmt->fetchAll() as $z) {
        $rows[(int) $z['id']] = [
            'id'         => (int) $z['id'],
            // WHOSE LIST IT IS travels along: the browser puts a pencil on
            // foreign leaves for their appearance on this device, and my
            // own lists hung into a container must not get it — they are
            // edited where every own list is edited.
            'user_id'    => (int) $z['user_id'],
            'parent_id'  => $z['parent_id'] === null ? null : (int) $z['parent_id'],
            'name'       => $z['name'],
            'color'      => $z['color'],
            'symbol'     => $z['symbol'],
            'sort_order' => (int) $z['sort_order'],
            'total'     => (int) $z['total'],
            // Whether the branch is a find mapping — computed at ITS root,
            // which can lie outside the mounted branch (see
            // list_branch_root). The receiving side reads this to decide
            // whether a foreign point carries a find number.
            'is_species' => list_branch_root($pdo, (int) $z['id'])['is_species'],
            'species_groups' => list_species_groups_clean($z['species_groups'] ?? null),
        ];
    }

    // THE NUMBERS SEEN THROUGH THE BIRTH FILTER. total says the same thing
    // everywhere: how many points of this list ARRIVE in the container —
    // not how many it has at home. Unfiltered that is the same; with a
    // filter, the raw number stood in the map's sidebar next to a branch
    // that visibly delivered fewer points, and one searched for the missing
    // ones.
    $filter = container_filter($pdo, $containerId);
    if ($filter !== null) {
        // A joint branch is never filtered (22.09.2026) — its numbers stay raw.
        $jointIds = [];
        foreach ($shares as $f) {
            if ($withJoint && (int) ($f['joint'] ?? 0) === 1) {
                $jointIds = array_merge($jointIds, $branches[(int) $f['list_id']] ?? []);
            }
        }
        $counts = container_filtered_counts($pdo, $allIds, $filter);
        foreach ($rows as $id => $row) {
            if (in_array($id, $jointIds, true)) {
                continue;
            }
            $rows[$id]['total'] = $counts[$id] ?? 0;
        }
    }

    $looks = null;
    foreach ($shares as &$f) {
        $f['id']      = (int) $f['id'];
        $f['list_id'] = (int) $f['list_id'];
        $f['user_id'] = (int) $f['user_id'];
        // Before migration 0043 the column does not exist — then everything
        // is read-only, and that is exactly what the empty list says.
        // A JOINT share hands out every right there is — the same list of
        // keys, so the browser needs no second kind of answer.
        $f['joint']   = $withJoint && (int) ($f['joint'] ?? 0) === 1;
        $f['rights']  = rights_compat($f['joint'] ? share_rights_full($pdo, $f['list_id']) : rights_read($f['rights'] ?? null));
        /* A LIST SHARE ANSWERS FOR THE ONE ASKING (migration 0160): his
           rights, and „joint" only when he himself may set points into
           the list — the others just look at it. */
        if ($viewerId !== null && list_share_list($pdo, $containerId) !== null) {
            $viewer = list_share_viewer($pdo, $viewerId, $containerId);
            $f['rights']     = $viewer['rights'] ?? [];
            $f['joint']      = !empty($viewer['own_points']) && count(list_share_participants($pdo, $containerId)) > 1;
            $f['list_share'] = true;
        }

        $f['lists'] = [];
        foreach ($branches[$f['list_id']] ?? [] as $id) {
            if (isset($rows[$id])) {
                $row = $rows[$id];
                /* THE VIEWER'S OWN LOOK (migration 0160): colour and shape
                   he gave this list, for his account. null = as its owner
                   dresses it. Only for somebody else's list — one's own is
                   dressed where every own list is. */
                if ($viewerId !== null && $row['user_id'] !== $viewerId) {
                    $looks ??= shared_looks($pdo, $viewerId);
                    $row['look'] = $looks[$id] ?? null;
                }
                // The root of the branch stands at the top in the container
                // — its foreign parent_id pointed to a list nobody here
                // sees.
                if ($id === $f['list_id']) {
                    $row['parent_id'] = null;
                }
                $f['lists'][] = $row;
            }
        }

        $f['fields'] = list_fields_of_root($pdo, $f['list_id']);
    }
    unset($f);

    return $shares;
}

/**
 * Is the list a species list — does it carry find numbers and find fields?
 *
 * THE NAME IS FROM THE TREE: the flag used to sit on the root of a branch,
 * and this walked up to it. Since the Mappen (0168) every list carries it
 * itself, so the list is its own root. It still states the answer
 * server-side for every shared list row (container_shares), which is
 * where the receiving browsers read it.
 *
 * @return array{root: int, is_species: bool} the list id and the verdict
 */
function list_branch_root(PDO $pdo, int $listId): array
{
    $stmt = $pdo->prepare('SELECT is_species FROM map_lists WHERE id = ?');
    $stmt->execute([$listId]);
    return ['root' => $listId, 'is_species' => (bool) $stmt->fetchColumn()];
}

/** A list's fields — for a species list with the find fields in front, as
 *  for the owner themselves. („of root" is the tree's name, see above.) */
function list_fields_of_root(PDO $pdo, int $listId): array
{
    ['root' => $listId, 'is_species' => $isSpecies] = list_branch_root($pdo, $listId);

    $leading = [];
    if ($isSpecies) {
        require_once __DIR__ . '/find_fields.php';
        $leading = find_fields();
    }

    try {
        $withOptions = column_exists($pdo, 'map_list_fields', 'options');
        $stmt = $pdo->prepare(
            'SELECT name, type, is_species' . ($withOptions ? ', options' : '') . '
               FROM map_list_fields WHERE list_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$listId]);
        $own = array_map(static fn(array $f): array => [
            'name'      => $f['name'],
            'type'       => $f['type'],
            'is_species' => (bool) $f['is_species'],
            'options'  => isset($f['options']) && is_string($f['options'])
                ? (json_decode($f['options'], true) ?: null) : null,
        ], $stmt->fetchAll());
        return array_merge($leading, $own);
    } catch (PDOException $e) {
        if (($e->errorInfo[0] ?? '') === '42S02') {
            return $leading;
        }
        throw $e;
    }
}

/** All list ids that hang in the container through the shares. */
function container_list_ids(PDO $pdo, int $containerId): array
{
    $stmt = $pdo->prepare('SELECT list_id, user_id FROM map_container_lists WHERE container_id = ?');
    $stmt->execute([$containerId]);
    $shares = $stmt->fetchAll();
    if ($shares === []) {
        return [];
    }
    $branches = container_branches($pdo, $shares);
    return array_values(array_unique(array_merge(...array_values($branches))));
}

/**
 * The points of a container — across all shares, each point with its
 * owner's nick and only the lists visible in the container.
 *
 * Optionally restricted to bucket keys (see includes/bucket.php).
 */
function container_points(PDO $pdo, int $containerId, array $buckets = [], ?int $viewerId = null): array
{
    $listIds = container_list_ids($pdo, $containerId);
    if ($listIds === []) {
        return [];
    }

    $space = implode(',', array_fill(0, count($listIds), '?'));
    // EXISTS instead of JOIN+DISTINCT: a point can sit in several container
    // lists, and DISTINCT over p.* stumbles over the JSON columns.
    $where  = ['p.deleted_at IS NULL',
               "EXISTS (SELECT 1 FROM map_point_lists pl
                         WHERE pl.point_id = p.id AND pl.list_id IN ($space))"];
    $values = $listIds;

    if ($buckets !== []) {
        require_once __DIR__ . '/bucket.php';
        $bucketSpace = implode(',', array_fill(0, count($buckets), '?'));
        $where[]     = bucket_expression('p') . " IN ($bucketSpace)";
        $values      = array_merge($values, $buckets);
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

    /* A LIST SHARE DELIVERS WHAT THIS VIEWER MAY SEE (migration 0160):
       his rows' filters, evaluated here and not on his device — a point
       he is not meant to see must not reach it at all. Without a viewer
       (the owner's own reading) everything. */
    if ($viewerId !== null && list_share_list($pdo, $containerId) !== null) {
        $viewer = list_share_viewer($pdo, $viewerId, $containerId);
        if ($viewer === null) {
            return [];
        }
        if ($viewer['filters'] !== null) {
            $pass   = list_share_filter_pass($pdo, $points, $viewer['filters']);
            $points = array_values(array_filter($points, static fn(array $p): bool =>
                isset($pass[(int) $p['id']]) || (int) $p['user_id'] === $viewerId));
        }
    }

    // The birth filter sifts AFTER the SQL, in PHP: the database does not
    // know the polygon, and an excursion container is small enough. (The
    // bucket size estimate in container_eimer() counts too high because of
    // this — delivered is still only what fits; an overly cautious
    // bundling does no harm.)
    $filter = container_filter($pdo, $containerId);
    if ($filter !== null) {
        /* A JOINT LIST SHOWS EVERYTHING (22.09.2026): the birth
           filter is for private lists hung in — an excerpt of somebody's
           stock. What a member sets into a joint list, he means for the
           group; a filter over it would swallow finds set outside the
           period or the area, and nobody would know why. Points lying in a
           joint branch pass regardless. */
        $inJoint  = [];
        $jointIds = container_joint_list_ids($pdo, $containerId);
        if ($jointIds !== [] && $points !== []) {
            $ids  = array_column($points, 'id');
            $stmt = $pdo->prepare(
                'SELECT DISTINCT point_id FROM map_point_lists
                  WHERE list_id IN (' . implode(',', array_fill(0, count($jointIds), '?')) . ')
                    AND point_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            );
            $stmt->execute(array_merge($jointIds, $ids));
            $inJoint = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
        }
        $points = array_values(array_filter($points,
            fn(array $p) => isset($inJoint[(int) $p['id']]) || container_filter_matches($filter, $p)));
    }
    if ($points === []) {
        return [];
    }

    // Fetch the list assignment, but ONLY the container's lists: that a
    // point at home also sits in other lists is none of the members' business.
    $ids        = array_column($points, 'id');
    $pointSpace = implode(',', array_fill(0, count($ids), '?'));
    $stmt       = $pdo->prepare(
        "SELECT point_id, list_id FROM map_point_lists
          WHERE point_id IN ($pointSpace) AND list_id IN ($space)"
    );
    $stmt->execute(array_merge($ids, $listIds));
    $mapping = [];
    foreach ($stmt as $z) {
        $mapping[(int) $z['point_id']][] = (int) $z['list_id'];
    }

    return foreign_points_prepare($pdo, $points, $mapping);
}

/**
 * Foreign points, prepared for a viewer — photos, sequences, threat status,
 * DGfM marks, genus, and the owner's name — from rows of
 * „SELECT p.*, <display name> AS owner_name".
 *
 * ONE PREPARATION FOR EVERY ROAD A FOREIGN FIND TAKES: the containers
 * (container_points) and since 02.10.2026 the hints a friend gives
 * (point_hints_points). It stood inline in container_points; a second copy
 * for the hints would have been the copy that forgets the next field.
 *
 * @param array $points   the rows
 * @param array $mapping  point id => the list ids the viewer may see it
 *                        under (a hint has none)
 */
function foreign_points_prepare(PDO $pdo, array $points, array $mapping): array
{
    if ($points === []) {
        return [];
    }
    $ids = array_column($points, 'id');

    // The sequences ride along for foreign points too (migration 0112):
    // whoever sees the find sees its vouchers, with the sequencer's name.
    require_once __DIR__ . '/sequences.php';
    $sequences = sequences_for_points($pdo, array_map(static fn(array $p): int => (int) $p['id'], $points));

    /* AND THE „BEARBEITET" MARK — a flat 0 stood here until 06.10.2026,
       and Finn's micrographs on the finds he shares never reached
       the maintainer's sidebar (cloud_recordings_for_points says the rest). */
    require_once __DIR__ . '/cloud.php';
    $recordings = cloud_recordings_for_points($pdo, $ids);

    // Photos per owner — photos_for_points is deliberately account-bound.
    $photos  = [];
    $byOwner = [];
    foreach ($points as $p) {
        $byOwner[(int) $p['user_id']][] = (int) $p['id'];
    }
    foreach ($byOwner as $ownerId => $pointIds) {
        foreach (photos_for_points($pdo, $ownerId, $pointIds) as $pointId => $list) {
            $photos[$pointId] = $list;
        }
    }

    // The threat status hangs on the species and not on the owner — one
    // lookup for all, as in points_load().
    $identifiers = [];
    foreach ($points as $p) {
        foreach (point_species_keys($p['extra_species'] ?? null) as $k) {
            $identifiers[$k] = true;
        }
    }
    $endangerment = $identifiers === []
        ? []
        : species_endangerment($pdo, array_keys($identifiers));
    $marksOf = species_dgfm_marks($pdo, array_keys($identifiers));

    /* THE GENUS OF THE TITLE SPECIES, for the foreign points too — a
       filter that is visibly on and has no effect on half the stock is
       worse than none. The same gap had already been left open once for
       the height band (drawMarker in map.js), and the systematic filter
       would have repeated it: without the genus every shared find drops
       out as soon as a rank is chosen. */
    $taxonomyOf = [];
    if ($ids !== [] && column_exists($pdo, 'map_point_species', 'genus')) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT point_id, genus, source FROM map_point_species
              WHERE point_id IN ($marks) AND field = '' AND genus <> ''");
        $stmt->execute($ids);
        // The SOURCE comes along — a genus name alone is not unique
        // across the kingdoms (TAXONOMY_KINGDOM_OF_SOURCE).
        $keyOf = [];
        $entries = [];
        foreach ($stmt as $z) {
            $key = taxonomy_key((string) $z['genus'], (string) ($z['source'] ?? ''));
            $keyOf[(int) $z['point_id']] = $key;
            $entries[$key] = ['genus' => $z['genus'], 'source' => $z['source'] ?? ''];
        }
        $ranks = taxonomy_of_genera($pdo, array_values($entries));
        foreach ($keyOf as $pointId => $key) {
            if (isset($ranks[$key])) {
                $taxonomyOf[$pointId] = $ranks[$key];
            }
        }
    }

    /* $sequences IN THE use LIST — it was missing from 0112 until
       02.10.2026, and a static closure does not see what it is not
       handed: `$sequences[...] ?? []` read an undefined variable and
       answered „none" without a sound. Every shared find reached its
       members without its sequences, although the comment above promised
       them; found while this preparation moved out for the hints. */
    return array_map(static function (array $p) use ($mapping, $photos, $endangerment, $taxonomyOf, $marksOf, $sequences, $recordings): array {
        $name  = $p['owner_name'];
        $owner = (int) $p['user_id'];
        unset($p['owner_name']);
        $prepared = point_prepare(
            $p,
            $mapping[(int) $p['id']] ?? [],
            $photos[(int) $p['id']] ?? [],
            point_endangerment($p['extra_species'] ?? null, $endangerment),
            $taxonomyOf[(int) $p['id']] ?? null,
            $recordings[(int) $p['id']] ?? 0,
            // The DGfM marks for the foreign points too — a filter that is
            // visibly on and has no effect on half the stock is worse than none.
            point_dgfm_marks($p['extra_species'] ?? null, $marksOf, $p['title'] ?? null)
        );
        $prepared['owner'] = $name;
        $prepared['owner_id'] = $owner;
        $prepared['sequences'] = $sequences[(int) $p['id']] ?? [];
        return $prepared;
    }, $points);
}

// ---------------------------------------------------------------------------
// Container buckets: the sync for shared points
//
// A SEPARATE NAMESPACE PER CONTAINER, apart from the personal buckets.
// Shared points get touched more often than one's own archive — if they ran
// through the same buckets, every foreign change would move one's own
// store. The device files them under "g<container>:<month>"; the server
// knows nothing of that, it simply computes per container the same
// fingerprints as in includes/bucket.php, only over the points of the shares.
// ---------------------------------------------------------------------------

/**
 * Fingerprints of the container buckets — the same three figures as
 * bucket_fingerprints(), and for the same reasons (see there).
 *
 * @return array<string,string>  "2026-08" => fingerprint
 */
function container_bucket_fingerprints(PDO $pdo, int $containerId, ?int $viewerId = null): array
{
    return container_bucket_status($pdo, $containerId, $viewerId)['finger'];
}

/**
 * Fingerprint AND count per bucket — the container's counterpart to
 * bucket_status(). Reasoning stands there.
 *
 * NO COUNTING WHEN THE CONTAINER CARRIES A BIRTH FILTER. The filter
 * sieves in PHP and not in SQL — it can hold a polygon, and the database
 * knows none (see container_points()). The query here therefore counts
 * ALL points of the hung-in lists, while only what passes the filter is
 * delivered.
 *
 * Measured on the maintainer's container „Waldbrände 2026": the query counts
 * 23 for 2026-08, delivered are 21. The two have no find date and sit in
 * the bucket via created_at, but fall through the time filter.
 *
 * For the fingerprint that has always been accepted — it then fires
 * occasionally without need, and a superfluous fetch does no harm. As a
 * CHECK NUMBER it would be fatal: the device would hold the bucket to be
 * permanently incomplete and fetch it anew on every start without
 * anything ever changing. A number that is wrong is worse than none — so
 * none here, and the device falls back on the weaker but safe statement:
 * empty is always wrong.
 *
 * @return array{finger: array<string,string>, counts: array<string,int>}
 */
function container_bucket_status(PDO $pdo, int $containerId, ?int $viewerId = null): array
{
    if (!bucket_ready($pdo)) {
        return ['finger' => [], 'counts' => []];
    }
    $listIds = container_list_ids($pdo, $containerId);
    if ($listIds === []) {
        return ['finger' => [], 'counts' => []];
    }

    $space = implode(',', array_fill(0, count($listIds), '?'));
    $stmt  = $pdo->prepare(
        'SELECT ' . bucket_expression('p') . ' AS bucket_key,
                COUNT(*) AS total,
                SUBSTRING(MD5(CONCAT_WS("|",
                    COUNT(*),
                    MAX(p.touched_at),
                    COALESCE(SUM(CRC32(CONCAT_WS(":", p.id, p.touched_at))), 0),
                    ' . bucket_derived_stamp($pdo) . '
                )), 1, 12) AS finger
           FROM map_points p
          WHERE p.deleted_at IS NULL
            AND EXISTS (SELECT 1 FROM map_point_lists pl
                         WHERE pl.point_id = p.id AND pl.list_id IN (' . $space . '))
          GROUP BY bucket_key
          ORDER BY bucket_key DESC'
    );
    $stmt->execute($listIds);

    $out = [];
    $counts = [];
    foreach ($stmt as $z) {
        // Without a time no bucket — the same caution as with one's own inventory.
        if ($z['bucket_key'] === null || $z['bucket_key'] === '') {
            continue;
        }
        $out[(string) $z['bucket_key']]    = (string) $z['finger'];
        $counts[(string) $z['bucket_key']] = (int) $z['total'];
    }

    /* NO COUNTS EITHER where a list share filters for this viewer — the
       same reason as for the birth filter above: the query counts the
       whole list, he gets only what passes. */
    $filtered = container_filter($pdo, $containerId) !== null;
    if (!$filtered && $viewerId !== null && list_share_list($pdo, $containerId) !== null) {
        $filtered = (list_share_viewer($pdo, $viewerId, $containerId)['filters'] ?? null) !== null;
    }
    return [
        'finger' => $out,
        'counts' => $filtered ? [] : $counts,
    ];
}

/**
 * The points of requested container buckets — whole buckets, fingerprints
 * read BEFORE the points. Both rules and their reasoning: includes/bucket.php.
 *
 * THE KEYS MUST BE SPELLED EXACTLY AS IN bucket_points() — 'buckets', 'open'
 * and, inside a bucket, 'points'. The device fetches its own stock and that
 * of a container through the SAME code path (bucketsReconcile() in map.js),
 * so a name that differs here does not produce an error: the client reads
 * undefined, stores a bucket with zero points and never re-queues the rest.
 * The buckets that were too big for one draw would then be silently missing
 * — and the fingerprint of the empty bucket would sit in the store as if it
 * were complete. That happened once, when the renaming reached bucket.php,
 * map.js and pointstore.js but not this file.
 *
 * @param string[] $keys
 * @return array{buckets: array<string,array{finger:string,points:array}>, open: string[]}
 */
function container_bucket_points(PDO $pdo, int $containerId, array $keys, ?int $viewerId = null): array
{
    $requested = array_values(array_unique(array_filter($keys, 'bucket_key_valid')));
    if (!bucket_ready($pdo) || $requested === []) {
        return ['buckets' => [], 'open' => []];
    }

    $all    = container_bucket_fingerprints($pdo, $containerId, $viewerId);
    $listIds = container_list_ids($pdo, $containerId);

    $sizes = [];
    if ($listIds !== []) {
        $space  = implode(',', array_fill(0, count($listIds), '?'));
        $bucketSpace = implode(',', array_fill(0, count($requested), '?'));
        $stmt   = $pdo->prepare(
            'SELECT ' . bucket_expression('p') . ' AS bucket_key, COUNT(*) AS n
               FROM map_points p
              WHERE p.deleted_at IS NULL
                AND EXISTS (SELECT 1 FROM map_point_lists pl
                             WHERE pl.point_id = p.id AND pl.list_id IN (' . $space . '))
                AND ' . bucket_expression('p') . ' IN (' . $bucketSpace . ')
              GROUP BY bucket_key'
        );
        $stmt->execute(array_merge($listIds, $requested));
        foreach ($stmt as $z) {
            $sizes[(string) $z['bucket_key']] = (int) $z['n'];
        }
    }

    $take  = [];
    $open  = [];
    $total = 0;
    foreach ($requested as $s) {
        $n = $sizes[$s] ?? 0;
        if ($take !== [] && $total + $n > BUCKET_MAX) {
            $open[] = $s;
            continue;
        }
        $take[]  = $s;
        $total  += $n;
    }

    $delivered = [];
    foreach ($take as $s) {
        $delivered[$s] = ['finger' => $all[$s] ?? 'empty', 'points' => []];
    }

    foreach ($take === [] ? [] : container_points($pdo, $containerId, $take, $viewerId) as $point) {
        $s = bucket_key_of($point);
        if (isset($delivered[$s])) {
            $delivered[$s]['points'][] = $point;
        }
    }

    return ['buckets' => $delivered, 'open' => $open];
}

// ---------------------------------------------------------------------------
// Growth: the room has grown
// ---------------------------------------------------------------------------

/** Does the table exist yet? The code goes live before migration 0049. */
function additions_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_container_state');
}

/**
 * Book the current member state as acknowledged.
 *
 * REPLACE, no reconciliation: the row carries only the snapshot, there is
 * nothing to lose here. Called by the "Verstanden" button in the sharing
 * tab — and by container_add() for the one adding, since whoever has
 * just enlarged the room needs no notice of it.
 */
function additions_seen(PDO $pdo, int $userId, int $containerId): void
{
    if (!additions_ready($pdo)) {
        return;
    }
    $pdo->prepare('REPLACE INTO map_container_state (user_id, container_id, members) VALUES (?, ?, ?)')
        ->execute([$userId, $containerId, json_encode(container_member_ids($pdo, $containerId))]);
}

/**
 * Where has the room grown since I last looked?
 *
 * Only containers in which I have hung something are asked about — from
 * whoever has hung in nothing, new eyes can take nothing. The snapshot from
 * map_container_state is compared with the live state from
 * container_member_ids(): that captures every way of growing (added
 * directly, group added, group grown) without comparing timestamps at
 * every source.
 *
 * A SHARE WITHOUT A SNAPSHOT dates from the time before migration 0049.
 * It triggers no alarm but creates the snapshot now — anything else would
 * mean reporting every legacy share as "new" at the first call, and a
 * notice that lights up everywhere right at its introduction only gets
 * clicked away.
 *
 * @return array<int,array{name:string, neu:string[]}>  per container id
 */
function additions_per_container(PDO $pdo, int $userId): array
{
    if (!additions_ready($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT cl.container_id, c.name
           FROM map_container_lists cl
           JOIN map_containers c ON c.id = cl.container_id
          WHERE cl.user_id = ?
          ORDER BY c.name'
    );
    $stmt->execute([$userId]);
    $mine = $stmt->fetchAll();
    if ($mine === []) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT container_id, members FROM map_container_state WHERE user_id = ?');
    $stmt->execute([$userId]);
    $seen = [];
    foreach ($stmt->fetchAll() as $z) {
        $seen[(int) $z['container_id']] = array_map('intval', (array) json_decode((string) $z['members'], true));
    }

    $out = [];
    foreach ($mine as $c) {
        $cId     = (int) $c['container_id'];
        $current = container_member_ids($pdo, $cId);

        if (!isset($seen[$cId])) {
            additions_seen($pdo, $userId, $cId);
            continue;
        }

        // Never report myself: to me, I am never "newly in".
        $new = array_values(array_diff($current, $seen[$cId], [$userId]));
        if ($new === []) {
            continue;
        }

        $space = implode(',', array_fill(0, count($new), '?'));
        $stmt  = $pdo->prepare(
            'SELECT ' . sql_display_name() . ' AS display_name
               FROM map_users u WHERE u.id IN (' . $space . ') ORDER BY display_name'
        );
        $stmt->execute($new);

        $out[$cId] = [
            'name' => (string) $c['name'],
            'added'  => $stmt->fetchAll(PDO::FETCH_COLUMN),
        ];
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Birth filter: what a container lets through at all
// ---------------------------------------------------------------------------

/** Does the filter column exist yet? The code goes live before migration 0050. */
function filter_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_containers', 'filter');
}

/** A container's birth filter — null means: everything is shared. */
function container_filter(PDO $pdo, int $containerId): ?array
{
    if (!filter_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT filter FROM map_containers WHERE id = ?');
    $stmt->execute([$containerId]);
    $raw = $stmt->fetchColumn();
    if ($raw === false || $raw === null) {
        return null;
    }
    $filter = json_decode((string) $raw, true);
    return is_array($filter) && $filter !== [] ? $filter : null;
}

// point_in_polygon lived here until 22.08.2026 and moved to data.php: the
// auto lists need it too (place filter), and data.php must not depend on
// this file — the other way round it does anyway (require up top).

/**
 * Does the point pass the birth filter? All parts are AND-linked.
 *
 * The point comes as a row from map_points (lat, lon, title, found_at).
 * WITHOUT A FIND TIME IT FALLS THROUGH EVERY TIME FILTER: whoever filters
 * by a day means finds of that day — "don't know when" is not among them.
 * The title rules compare without regard to case.
 */
function container_filter_matches(?array $filter, array $point): bool
{
    if ($filter === null) {
        return true;
    }

    if (isset($filter['time'])) {
        if ($point['found_at'] === null) {
            return false;
        }
        $day = substr((string) $point['found_at'], 0, 10);
        if (($filter['time']['from'] ?? null) !== null && $day < $filter['time']['from']) {
            return false;
        }
        if (($filter['time']['until'] ?? null) !== null && $day > $filter['time']['until']) {
            return false;
        }
    }

    if (isset($filter['area']['corners'])
        && !point_in_polygon((float) $point['lat'], (float) $point['lon'], $filter['area']['corners'])) {
        return false;
    }

    foreach ($filter['terms'] ?? [] as $rule) {
        $matches = mb_stripos((string) $point['title'], (string) $rule['text']) !== false;
        if (($rule['type'] === 'contains') !== $matches) {
            return false;
        }
    }

    return true;
}

/** The filter as one readable phrase for the management area. */
function container_filter_readable(?array $filter): ?string
{
    if ($filter === null) {
        return null;
    }
    $parts = [];
    if (isset($filter['time'])) {
        $from  = $filter['time']['from'] ?? null;
        $until = $filter['time']['until'] ?? null;
        $d     = static fn(string $t): string => date('d.m.Y', strtotime($t));
        $parts[] = match (true) {
            $from !== null && $until !== null && $from === $until => t('nur der %s', $d($from)),
            $from !== null && $until !== null => t('%s bis %s', $d($from), $d($until)),
            $from !== null => t('ab %s', $d($from)),
            default => t('bis %s', $d($until)),
        };
    }
    if (isset($filter['area'])) {
        // Old filters (until 13.08.2026) carried the name of an offline
        // area; since then drawing is free-hand, and the nameless is simply
        // called after what it is.
        $parts[] = isset($filter['area']['name'])
            ? t('im Gebiet „%s“', $filter['area']['name'])
            : t('im umrandeten Gebiet');
    }
    foreach ($filter['terms'] ?? [] as $rule) {
        // One sentence per variant instead of splicing „enthält"/„enthält
        // nicht" into a frame — word order is the translation's business.
        $parts[] = $rule['type'] === 'contains'
            ? t('Titel enthält „%s“', $rule['text'])
            : t('Titel enthält nicht „%s“', $rule['text']);
    }
    return implode(' · ', $parts);
}

/**
 * How many points of a branch come through the filter — for the honest
 * number in the share row: there should stand what the others SEE, not
 * what the list contains at home.
 */
/**
 * Point count PER LIST, seen through the birth filter — for the total
 * column in container_shares().
 *
 * Unlike container_filtered_count() (one total per branch, points
 * counted only once across lists), this counts per list — a point in two
 * leaves counts in both, as with the unfiltered total values too. Each
 * point is still SIFTED only once: the polygon is the expensive part, and
 * the same point does not come out differently at the second leaf.
 *
 * @return array<int,int>  list_id => count
 */
function container_filtered_counts(PDO $pdo, array $listIds, array $filter): array
{
    if ($listIds === []) {
        return [];
    }
    $space = implode(',', array_fill(0, count($listIds), '?'));
    $stmt  = $pdo->prepare(
        "SELECT pl.list_id, p.id, p.lat, p.lon, p.title, p.found_at
           FROM map_points p
           JOIN map_point_lists pl ON pl.point_id = p.id
          WHERE p.deleted_at IS NULL AND pl.list_id IN ($space)"
    );
    $stmt->execute($listIds);

    $passes  = [];
    $counts = array_fill_keys(array_map('intval', $listIds), 0);
    foreach ($stmt->fetchAll() as $z) {
        $pointId = (int) $z['id'];
        $passes[$pointId] ??= container_filter_matches($filter, $z);
        if ($passes[$pointId]) {
            $counts[(int) $z['list_id']]++;
        }
    }
    return $counts;
}

function container_filtered_count(PDO $pdo, array $branchIds, ?array $filter): int
{
    if ($branchIds === []) {
        return 0;
    }
    $space = implode(',', array_fill(0, count($branchIds), '?'));
    $stmt  = $pdo->prepare(
        "SELECT DISTINCT p.id, p.lat, p.lon, p.title, p.found_at
           FROM map_points p
           JOIN map_point_lists pl ON pl.point_id = p.id
          WHERE p.deleted_at IS NULL AND pl.list_id IN ($space)"
    );
    $stmt->execute($branchIds);
    $n = 0;
    foreach ($stmt->fetchAll() as $p) {
        $n += container_filter_matches($filter, $p) ? 1 : 0;
    }
    return $n;
}
