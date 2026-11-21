<?php
/**
 * Excursions, stage 1: create and manage.
 *
 * THE CARRYING THOUGHT (the concept of 23.08.2026): the right
 * hangs on the EXCURSION — not on the point, not on the account. A leader
 * may edit and delete what belongs to his excursion; the time window says
 * when anything may come into being; the later attribution of guest finds
 * works via the same reference plus an e-mail. What is built HERE is only
 * the anchor: the excursion itself with leader, window and photo policy.
 * QR access, the guest account and the point reference come in later
 * stages and hang themselves onto this table.
 *
 * WHY NOT A CONTAINER: a container presumes an account and a per-list
 * release from every participant. An excursion is a group WITHOUT common
 * accounts — the QR code replaces both. Close relative, separate thing.
 */

declare(strict_types=1);

require_once __DIR__ . '/friends.php';
// For point_create — the guest finds are ordinary points with the
// excursion's reference on them.
require_once __DIR__ . '/data.php';

function excursions_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_excursions');
}

/** Stage 1b (co-leaders) has its own readiness: the tab must keep
 *  working between deploying the code and applying migration 0069. */
function excursion_leaders_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_excursion_leaders');
}

/**
 * Normalize a datetime-local value ("2026-08-30T09:00") to DATETIME.
 * @throws InvalidArgumentException when unreadable
 */
function excursion_time(string $value, string $label): string
{
    $t = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($value))
        ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', trim($value));
    if ($t === false) {
        throw new InvalidArgumentException(t('„%s“ braucht Datum und Uhrzeit.', $label));
    }
    return $t->format('Y-m-d H:i:00');
}

/**
 * Name, window and photo policy out of a form — shared by create and
 * save, so both reject the same things with the same words.
 *
 * @return array{name:string, starts_at:string, ends_at:string,
 *               photos_allowed:int, photo_limit:?int}
 * @throws InvalidArgumentException
 */
function excursion_values(array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException(t('Die Exkursion braucht einen Namen.'));
    }
    $startsAt = excursion_time((string) ($input['starts_at'] ?? ''), t('Von'));
    $endsAt   = excursion_time((string) ($input['ends_at'] ?? ''), t('Bis'));
    if ($endsAt <= $startsAt) {
        throw new InvalidArgumentException(t('Das Zeitfenster endet, bevor es beginnt.'));
    }

    // Empty means: the operator's default applies (NULL in the column).
    $limit = trim((string) ($input['photo_limit'] ?? ''));
    if ($limit !== '' && (!ctype_digit($limit) || (int) $limit < 1)) {
        throw new InvalidArgumentException(t('Das Fotomaß ist eine Anzahl — oder bleibt leer für die Vorgabe.'));
    }

    // The description is the invitation (24.08.2026) — free prose, and
    // empty stays NULL: most excursions need none.
    $description = trim((string) ($input['description'] ?? ''));

    /* The offline map: an own area, a zoom level, and the estimate the
       browser computed while picking (tileList × 25 kB, areas.js). The
       figures are a HINT for the guest's page, not a contract — hence
       taken as given and only bounded. Without an area everything falls
       to NULL together: half a reference would be a map nobody can
       load. */
    $areaId   = (int) ($input['area_id'] ?? 0);
    $areaZoom = (int) ($input['area_zoom'] ?? 0);
    if ($areaId > 0 && ($areaZoom < 13 || $areaZoom > 20)) {
        throw new InvalidArgumentException(t('Die Zoomstufe der Offline-Karte liegt zwischen 13 und 20.'));
    }

    return [
        'name'           => mb_substr($name, 0, 200),
        'starts_at'      => $startsAt,
        'ends_at'        => $endsAt,
        'photos_allowed' => !empty($input['photos_allowed']) ? 1 : 0,
        'photo_limit'    => $limit === '' ? null : (int) $limit,
        'description'    => $description === '' ? null : mb_substr($description, 0, 5000),
        'area_id'        => $areaId > 0 ? $areaId : null,
        'area_zoom'      => $areaId > 0 ? $areaZoom : null,
        'area_tiles'     => $areaId > 0 ? max(0, (int) ($input['area_tiles'] ?? 0)) : null,
        'area_bytes'     => $areaId > 0 ? max(0, (int) ($input['area_bytes'] ?? 0)) : null,
    ];
}

/** Migration 0078 — the code goes live before it. */
function excursion_area_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_excursions', 'area_id');
}

/**
 * The offline area of an excursion, as the guest page and the map need
 * it: name, outline, zoom and the leader's estimate. Null when none is
 * set — or when the area has meanwhile been deleted, which is the same
 * thing from here.
 */
function excursion_area(PDO $pdo, array $e): ?array
{
    if (!excursion_area_ready($pdo) || ($e['area_id'] ?? null) === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, name, corners FROM map_areas WHERE id = ?');
    $stmt->execute([(int) $e['area_id']]);
    $area = $stmt->fetch();
    if ($area === false) {
        return null;
    }
    $corners = json_decode((string) $area['corners'], true);
    if (!is_array($corners) || count($corners) < 3) {
        return null;
    }
    return [
        'id'      => (int) $area['id'],
        'name'    => (string) $area['name'],
        'corners' => $corners,
        // 18 as the fallback, like the form's default — see there.
        'zoom'    => (int) ($e['area_zoom'] ?? 18),
        /* HOW LONG THE DEVICE MAY KEEP THE TILES (09.09.2026): the
           excursion's last day. A participant BORROWS this area — it
           belongs to the leader and stands in nobody else's directory —
           and without a loan period his device would either sweep the
           tiles away as orphans or keep somebody else's maps for ever.
           The date travels as a plain day; areas.js writes it into the
           device state, where the expiry sweep already reads it. */
        'held_until' => substr((string) $e['ends_at'], 0, 10),
        'tiles'   => (int) ($e['area_tiles'] ?? 0),
        'bytes'   => (int) ($e['area_bytes'] ?? 0),
    ];
}

/** The leader's own areas, for the excursion form's picker. */
function excursion_areas_of(PDO $pdo, int $userId): array
{
    if (!excursion_area_ready($pdo) || !table_exists($pdo, 'map_areas')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id, name, corners FROM map_areas WHERE user_id = ? ORDER BY name');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Migration 0077 — the code goes live before it. */
function excursion_description_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_excursions', 'description');
}

/** Create; returns the new id. The join token is born with the row. */
function excursion_create(PDO $pdo, int $userId, array $input): int
{
    $v = excursion_values($input);
    $withDescription = excursion_description_ready($pdo);
    $withArea        = excursion_area_ready($pdo);
    excursion_area_check($pdo, $userId, $v['area_id']);
    $pdo->prepare(
        'INSERT INTO map_excursions
                (leader_user_id, name, starts_at, ends_at, join_token,
                 photos_allowed, photo_limit' . ($withDescription ? ', description' : '')
        . ($withArea ? ', area_id, area_zoom, area_tiles, area_bytes' : '') . ', created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?' . ($withDescription ? ', ?' : '')
        . ($withArea ? ', ?, ?, ?, ?' : '') . ', NOW())'
    )->execute(array_merge(
        [$userId, $v['name'], $v['starts_at'], $v['ends_at'],
         bin2hex(random_bytes(16)), $v['photos_allowed'], $v['photo_limit']],
        $withDescription ? [$v['description']] : [],
        $withArea ? [$v['area_id'], $v['area_zoom'], $v['area_tiles'], $v['area_bytes']] : []));
    return (int) $pdo->lastInsertId();
}

/**
 * An excursion's offline area must belong to the person setting it —
 * otherwise a stranger's outline would travel onto guests' phones, and
 * whoever tried ids would learn which exist.
 * @throws InvalidArgumentException
 */
function excursion_area_check(PDO $pdo, int $userId, ?int $areaId): void
{
    if ($areaId === null || !excursion_area_ready($pdo)) {
        return;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_areas WHERE id = ? AND user_id = ?');
    $stmt->execute([$areaId, $userId]);
    if ($stmt->fetchColumn() === false) {
        throw new InvalidArgumentException(t('Dieses Gebiet gibt es nicht (mehr).'));
    }
}

/**
 * The excursion, for ANY of its leaders — main or co-leader. Every
 * professional write (saving now, tidying points and photos later) goes
 * through here, so widening "the leader" to "a leader" happened in
 * exactly one place (22.08.2026).
 * @throws InvalidArgumentException
 */
function excursion_of_leader(PDO $pdo, int $userId, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM map_excursions WHERE id = ? AND leader_user_id = ?');
    $stmt->execute([$id, $userId]);
    $e = $stmt->fetch();
    if ($e !== false) {
        return $e;
    }
    if (excursion_leaders_ready($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT e.* FROM map_excursions e
               JOIN map_excursion_leaders l ON l.excursion_id = e.id AND l.user_id = ?
              WHERE e.id = ?');
        $stmt->execute([$userId, $id]);
        $e = $stmt->fetch();
        if ($e !== false) {
            return $e;
        }
    }
    throw new InvalidArgumentException(t('Diese Exkursion gibt es nicht mehr.'));
}

/**
 * The excursion, but only for its MAIN leader — deleting and appointing
 * or removing co-leaders stay with the creator, the same division of
 * roles as with a container's owner.
 * @throws InvalidArgumentException
 */
function excursion_of_main_leader(PDO $pdo, int $userId, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM map_excursions WHERE id = ? AND leader_user_id = ?');
    $stmt->execute([$id, $userId]);
    $e = $stmt->fetch();
    if ($e === false) {
        throw new InvalidArgumentException(t('Diese Exkursion gibt es nicht mehr.'));
    }
    return $e;
}

function excursion_save(PDO $pdo, int $userId, int $id, array $input): void
{
    excursion_of_leader($pdo, $userId, $id);
    $v = excursion_values($input);
    // By id alone: the line above IS the authorization. An additional
    // "AND leader_user_id" stood here and silently un-did the widening —
    // the co-leader was allowed through the check, and his UPDATE then
    // matched zero rows (tests/excursions.php caught it).
    $withDescription = excursion_description_ready($pdo);
    $withArea        = excursion_area_ready($pdo);
    // Against the person SAVING: a co-leader picks from his own areas,
    // and the one already stored stays untouched when he picks none.
    excursion_area_check($pdo, $userId, $v['area_id']);
    $pdo->prepare(
        'UPDATE map_excursions
            SET name = ?, starts_at = ?, ends_at = ?, photos_allowed = ?, photo_limit = ?'
        . ($withDescription ? ', description = ?' : '')
        . ($withArea ? ', area_id = ?, area_zoom = ?, area_tiles = ?, area_bytes = ?' : '') . '
          WHERE id = ?'
    )->execute(array_merge(
        [$v['name'], $v['starts_at'], $v['ends_at'], $v['photos_allowed'], $v['photo_limit']],
        $withDescription ? [$v['description']] : [],
        $withArea ? [$v['area_id'], $v['area_zoom'], $v['area_tiles'], $v['area_bytes']] : [],
        [$id]));
}

function excursion_delete(PDO $pdo, int $userId, int $id): void
{
    // Loaded here like at the other call sites: the guests' photos are
    // deleted or moved below, and excursions.php otherwise gets along
    // without the photo module.
    require_once __DIR__ . '/photos.php';

    excursion_of_main_leader($pdo, $userId, $id);

    /* THREE FATES FOR THE FINDS (27.08.2026). Deleting used to
       remove only the excursion row, and the guests' finds became what
       the maintainer called „Herrenlose": finds still stored in the guest
       account, reachable by nobody — every view runs over „which
       excursions do I lead", and that answer was empty. His word is the
       whole reason this function grew: better gone for good, said out
       loud in the dialog, than kept and lost. He decided what should
       really happen:

       1. UNCLAIMED GUEST FINDS GO FOR GOOD, photos included („endgültig
          gelöscht. Das soll auch so gesagt werden"). They belong to the
          guest account, an archive nobody can log into; kept, they would
          only count against the disk for ever. The confirmation dialog
          says it in plain words — that is why no trash detour is needed.

       2. CLAIMED GUEST FINDS MOVE TO THEIR OWNER („die gehören ja den
          Benutzern selber"). Technically they still belong to the guest
          account — the claim is only a mark on the guest row, and the
          path over that row dies with the excursion. So the move has to
          happen NOW or never: point, species rows, photo rows and the
          photo FILES, which live in the guest account's directory.

       3. FINDS OF LEADERS AND LOGGED-IN USERS merely lose the reference
          („verlieren dann nur ihren Exkursionsbezug") — they always
          belonged to real accounts and stay put. */
    if (excursion_guests_ready($pdo) && ($guestUser = excursion_guest_user_id($pdo)) !== null) {
        $stmt = $pdo->prepare(
            'SELECT p.id, u.id AS claim
               FROM map_points p
               LEFT JOIN map_excursion_guests g ON g.id = p.excursion_guest_id
               LEFT JOIN map_users u ON u.id = g.user_id
              WHERE p.excursion_id = ? AND p.user_id = ?'
        );
        $stmt->execute([$id, $guestUser]);

        foreach ($stmt->fetchAll() as $p) {
            $pointId = (int) $p['id'];

            /* A CLAIM ON A VANISHED ACCOUNT counts as none — the join
               against map_users is that check. Handing a find to an id
               nobody owns would create the very orphan this is meant to
               prevent. */
            if ($p['claim'] === null) {
                // Files first, row second — the same order and the same
                // reasoning as point_delete_permanently: after the DELETE
                // the foreign key has taken the photo rows with it, and
                // nobody knows any more which files belonged to it.
                photos_of_point_remove($pdo, $guestUser, $pointId);
                $pdo->prepare('DELETE FROM map_points WHERE id = ? AND user_id = ?')
                    ->execute([$pointId, $guestUser]);
                continue;
            }

            $claim = (int) $p['claim'];

            /* THE PHOTO FILES MOVE HOUSE. They lie in the guest account's
               directory, and the row about to say „user $claim" would
               otherwise point at files under the wrong roof — photo_file()
               builds the path FROM the user id. Big picture and preview
               each; a file already missing is skipped, not fatal: a lost
               thumbnail must not strand the whole find. */
            if (photos_ready($pdo)) {
                $q = $pdo->prepare('SELECT filename FROM map_photos WHERE point_id = ? AND user_id = ?');
                $q->execute([$pointId, $guestUser]);
                $names = $q->fetchAll(PDO::FETCH_COLUMN);
                if ($names !== []) {
                    user_photo_directory_create($claim);
                    foreach ($names as $name) {
                        foreach ([false, true] as $small) {
                            $from = photo_file($guestUser, (string) $name, $small);
                            $to   = photo_file($claim, (string) $name, $small);
                            if ($from !== null && $to !== null && is_file($from)) {
                                @rename($from, $to);
                            }
                        }
                    }
                    $pdo->prepare('UPDATE map_photos SET user_id = ? WHERE point_id = ? AND user_id = ?')
                        ->execute([$claim, $pointId, $guestUser]);
                }
            }
            if (column_exists($pdo, 'map_point_species', 'user_id')) {
                $pdo->prepare('UPDATE map_point_species SET user_id = ? WHERE point_id = ? AND user_id = ?')
                    ->execute([$claim, $pointId, $guestUser]);
            }
            $pdo->prepare(
                'UPDATE map_points SET user_id = ?, excursion_id = NULL, excursion_guest_id = NULL
                  WHERE id = ? AND user_id = ?'
            )->execute([$claim, $pointId, $guestUser]);
        }

        // The guest rows die with their excursion — claimed or not, what
        // they described no longer exists.
        $pdo->prepare('DELETE FROM map_excursion_guests WHERE excursion_id = ?')->execute([$id]);
    }

    // Fate three: whatever still carries the reference belongs to a real
    // account — leaders who mapped along, users who joined logged in.
    if (column_exists($pdo, 'map_points', 'excursion_id')) {
        $pdo->prepare(
            'UPDATE map_points SET excursion_id = NULL, excursion_guest_id = NULL
              WHERE excursion_id = ?'
        )->execute([$id]);
    }

    $pdo->prepare('DELETE FROM map_excursions WHERE id = ? AND leader_user_id = ?')
        ->execute([$id, $userId]);
    if (excursion_leaders_ready($pdo)) {
        $pdo->prepare('DELETE FROM map_excursion_leaders WHERE excursion_id = ?')->execute([$id]);
    }
}

// ---------------------------------------------------------------------------
// Co-leaders (stage 1b)
// ---------------------------------------------------------------------------

/**
 * Whom one can still appoint: my friends, minus those already appointed.
 *
 * ONE FUNCTION FOR BOTH WINDOWS (11.09.2026). The edit window asked this
 * question from the start; since the creation window asks it too
 * („Wenn man eine Exkursion anlegt, soll man gleich die Möglichkeit
 * bekommen, weitere Leiter hinzuzufügen"), it must not be written twice —
 * the friend list is the door through which people are admitted here, and
 * a second copy of that rule would be the one that keeps the old door
 * open after the first has been changed.
 *
 * @param int[] $taken user ids that are already leaders
 * @return array<int, array{id: int, display_name: string}>
 */
function excursion_leader_candidates(PDO $pdo, int $userId, array $taken = []): array
{
    $friendIds = friend_ids($pdo, $userId);
    if ($friendIds === []) {
        return [];
    }
    $space = implode(',', array_fill(0, count($friendIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT u.id, ' . sql_display_name('u') . " AS display_name
           FROM map_users u WHERE u.id IN ($space) ORDER BY display_name");
    $stmt->execute($friendIds);
    return array_values(array_filter(
        $stmt->fetchAll(),
        static fn(array $u): bool => !in_array((int) $u['id'], $taken, true)
    ));
}

/**
 * Appoint several co-leaders at once — what the creation window sends.
 *
 * EVERY ONE THROUGH THE SAME GATE as a single appointment: the check for
 * the main leader and the friendship sits in excursion_leader_add(), and
 * a loop that did its own checking would be the second rule that drifts.
 * A name that does not pass is skipped SILENTLY: the excursion has just
 * come into being, and a refusal about one companion must not read like
 * "creating failed". Whoever is missing is added in the edit window,
 * where the refusal has room to be explained.
 *
 * @param mixed $whoIds whatever the form sent — a list of ids, or nothing
 * @return int how many were really appointed
 */
function excursion_leaders_take(PDO $pdo, int $userId, int $id, mixed $whoIds): int
{
    if (!is_array($whoIds) || !excursion_leaders_ready($pdo)) {
        return 0;
    }
    $taken = 0;
    foreach ($whoIds as $raw) {
        $whoId = (int) $raw;
        if ($whoId <= 0) {
            continue;
        }
        try {
            excursion_leader_add($pdo, $userId, $id, $whoId);
            $taken++;
        } catch (InvalidArgumentException) {
            // Not a friend (any more), or oneself — see above.
        }
    }
    return $taken;
}

/**
 * Appoint a co-leader — main leader only, and only among his FRIENDS:
 * the same door through which containers admit people. Twice is just a
 * double click.
 * @throws InvalidArgumentException
 */
function excursion_leader_add(PDO $pdo, int $userId, int $id, int $whoId): void
{
    excursion_of_main_leader($pdo, $userId, $id);
    if ($whoId === $userId) {
        throw new InvalidArgumentException(t('Du bist schon der Leiter.'));
    }
    if (!in_array($whoId, friend_ids($pdo, $userId), true)) {
        throw new InvalidArgumentException(t('Mitleiter nimmst du aus deinen Freunden auf.'));
    }
    $pdo->prepare(
        'INSERT INTO map_excursion_leaders (excursion_id, user_id, added_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE added_at = added_at'
    )->execute([$id, $whoId]);
}

/**
 * End a co-leadership. Two doors lead here: the main leader removes
 * anyone, and a co-leader steps out HIMSELF — that one is the door
 * (sign-out), not the bucket: the excursion carries on without him.
 * @throws InvalidArgumentException
 */
function excursion_leader_remove(PDO $pdo, int $userId, int $id, int $whoId): void
{
    if ($userId !== $whoId) {
        excursion_of_main_leader($pdo, $userId, $id);
    } else {
        // Leaving needs no main-leader blessing — but it must BE a
        // co-leadership, otherwise the delete below would silently do
        // nothing and still report success.
        excursion_of_leader($pdo, $userId, $id);
    }
    $pdo->prepare('DELETE FROM map_excursion_leaders WHERE excursion_id = ? AND user_id = ?')
        ->execute([$id, $whoId]);
}

/** The co-leaders, with their names — for the edit window's list. */
function excursion_leaders(PDO $pdo, int $id): array
{
    if (!excursion_leaders_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT u.id, ' . sql_display_name('u') . ' AS display_name
           FROM map_excursion_leaders l
           JOIN map_users u ON u.id = l.user_id
          WHERE l.excursion_id = ?
          ORDER BY display_name'
    );
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

/**
 * My excursions — as main leader AND as co-leader, the next-starting
 * first. is_main tells the view which tools to hang on the row; a co-led
 * one carries its main leader's name, because "wessen Exkursion ist das"
 * is the first question at a shared row.
 */
function excursions_mine(PDO $pdo, int $userId): array
{
    if (!excursion_leaders_ready($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT e.*, 1 AS is_main, NULL AS leader_name
               FROM map_excursions e WHERE e.leader_user_id = ?
              ORDER BY e.starts_at DESC, e.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM (
            SELECT e.*, 1 AS is_main, NULL AS leader_name
              FROM map_excursions e
             WHERE e.leader_user_id = ?
            UNION ALL
            SELECT e.*, 0 AS is_main, ' . sql_display_name('u') . ' AS leader_name
              FROM map_excursions e
              JOIN map_excursion_leaders l ON l.excursion_id = e.id AND l.user_id = ?
              JOIN map_users u ON u.id = e.leader_user_id
        ) x ORDER BY x.starts_at DESC, x.id DESC'
    );
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * All finds of the excursions this account leads — main leader or co.
 *
 * WHY THIS EXISTS AS A FUNCTION (26.08.2026, wanting the finds
 * on the MAP and not only in Organisieren). The query stood twice inline
 * in organize_content.php; a third caller would have made it the place
 * that drifts apart. Now it is one, and the map and the table read the
 * same stock.
 *
 * FOREIGN BUT EDITABLE. The guest account owns these points, so they can
 * never come out of points_load(), which asks for p.user_id. The right to
 * change them hangs on the EXCURSION REFERENCE on the point, not on
 * ownership — the browser is told so with a flag instead of guessing it
 * from the owner.
 *
 * ONE'S OWN FINDS STAY OUT. A leader who mapped along has those points in
 * his own stock already; handed out a second time they would stand twice
 * on the map — once as his, once as a stranger's, and labelled „von Gast"
 * at that. Exactly the trap the table walked into on 24.08.2026. The map
 * shows them through the excursion all the same: an own point carries the
 * excursion reference too, and the sidebar reads it (map.js).
 *
 * @return array<int, array> point id => prepared foreign point
 */
function excursion_points(PDO $pdo, int $userId): array
{
    if (!excursions_ready($pdo) || !excursion_guests_ready($pdo)) {
        return [];
    }
    $mine = array_column(excursions_mine($pdo, $userId), 'id');
    if ($mine === []) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($mine), '?'));
    $stmt  = $pdo->prepare(
        'SELECT p.*, ' . (excursion_guest_names_ready($pdo)
            ? "TRIM(CONCAT(COALESCE(g.first_name, ''), ' ', COALESCE(g.last_name, '')))"
            : 'g.name') . ' AS guest_name
           FROM map_points p
           LEFT JOIN map_excursion_guests g ON g.id = p.excursion_guest_id
          WHERE p.excursion_id IN (' . $marks . ')
            AND p.deleted_at IS NULL
            AND p.user_id <> ?
          ORDER BY p.updated_at DESC'
    );
    $stmt->execute([...$mine, $userId]);
    return excursion_prepare_rows($pdo, $userId, $stmt->fetchAll());
}

/**
 * The finds of the excursions this account TOOK PART in as a guest — the
 * own old finds, tied to the account through the e-mail claim at login.
 *
 * THE SECOND SOURCE ORGANISIEREN READS (its stage 7, „the participant's
 * view"), and until 12.09.2026 the only place that read it: the report
 * and the file export took their foreign finds from export_points(),
 * which knew containers and co-owned finds and nothing of excursions.
 * After the first real excursion: „die Fundliste, die ich bei
 * Organisieren erstellen kann, hat die Gastfunde nicht dabei." Both
 * views now go through the same preparation, so what the table shows is
 * what the report prints.
 *
 * Where one both leads and took part, the leader's view already holds
 * every find; the caller de-duplicates by id, as export_points() does.
 *
 * @return array<int, array> point id => prepared foreign point
 */
function excursion_participant_points(PDO $pdo, int $userId): array
{
    if (!excursions_ready($pdo) || !excursion_guests_ready($pdo)
        || !excursion_attribution_ready($pdo)) {
        return [];
    }
    $rows = [];
    foreach (excursions_participated($pdo, $userId) as $part) {
        $stmt = $pdo->prepare(
            'SELECT p.*, ? AS guest_name FROM map_points p
              WHERE p.excursion_id = ? AND p.excursion_guest_id = ?
                AND p.deleted_at IS NULL
              ORDER BY p.updated_at DESC');
        $stmt->execute([(string) $part['guest_name'], (int) $part['id'], (int) $part['guest_id']]);
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = $row;
        }
    }
    return excursion_prepare_rows($pdo, $userId, $rows);
}

/**
 * Raw excursion rows (with guest_name) into prepared foreign points —
 * photos, endangerment and taxonomy in one sweep each, the owner named
 * after the guest. Shared by the leader's and the participant's view.
 *
 * @return array<int, array> point id => prepared foreign point
 */
function excursion_prepare_rows(PDO $pdo, int $userId, array $rows): array
{
    if ($rows === []) {
        return [];
    }

    /* The same three sweeps points_load() makes, and for the same reason:
       one query for the whole batch instead of one per find. */
    $ids     = array_column($rows, 'id');
    $space   = implode(',', array_fill(0, count($ids), '?'));
    $photos  = photos_for_points($pdo, $userId, $ids);

    $identifiers = [];
    foreach ($rows as $p) {
        foreach (point_species_keys($p['extra_species'] ?? null) as $k) {
            $identifiers[$k] = true;
        }
    }
    $endangerment = $identifiers === []
        ? [] : species_endangerment($pdo, array_keys($identifiers));
    $marksOf = species_dgfm_marks($pdo, array_keys($identifiers));

    $taxonomyOf = [];
    if (column_exists($pdo, 'map_point_species', 'genus')) {
        $g = $pdo->prepare("SELECT point_id, genus, source FROM map_point_species
                             WHERE point_id IN ($space) AND field = '' AND genus <> ''");
        $g->execute($ids);
        $keyOf = $entries = [];
        foreach ($g as $z) {
            $key = taxonomy_key((string) $z['genus'], (string) ($z['source'] ?? ''));
            $keyOf[(int) $z['point_id']] = $key;
            $entries[$key] = ['genus' => $z['genus'], 'source' => $z['source'] ?? ''];
        }
        $ranks = taxonomy_of_genera($pdo, array_values($entries));
        foreach ($keyOf as $id => $key) {
            if (isset($ranks[$key])) {
                $taxonomyOf[$id] = $ranks[$key];
            }
        }
    }

    /* WHO MAPPED ALONG WITH AN ACCOUNT. A logged-in participant's finds
       lie in her own stock and carry no guest row — and the row named
       her „Gast" (found 12.09.2026 on the first real excursion: Marlene
       stood as „Gast" in the report's header and its finder column). The
       account names come in one query for the batch, like everything
       else here. */
    $accountIds = array_values(array_unique(array_map(
        static fn(array $p): int => (int) $p['user_id'], $rows)));
    $accountName = [];
    if ($accountIds !== []) {
        $marks = implode(',', array_fill(0, count($accountIds), '?'));
        $q = $pdo->prepare('SELECT u.id, ' . sql_display_name() . " AS display_name FROM map_users u WHERE u.id IN ($marks)");
        $q->execute($accountIds);
        foreach ($q as $u) {
            $accountName[(int) $u['id']] = trim((string) $u['display_name']);
        }
    }
    $guestAccount = excursion_guest_user_id($pdo);

    // The „bearbeitet" mark for the leader's view too — a flat 0 until
    // 06.10.2026, as at the shares (cloud_recordings_for_points).
    require_once __DIR__ . '/cloud.php';
    $recordings = cloud_recordings_for_points($pdo, array_map('intval', array_column($rows, 'id')));

    $out = [];
    foreach ($rows as $p) {
        $id = (int) $p['id'];
        $prepared = point_prepare(
            $p,
            [],                                   // guests keep no lists
            $photos[$id] ?? [],
            point_endangerment($p['extra_species'] ?? null, $endangerment),
            $taxonomyOf[$id] ?? null,
            $recordings[$id] ?? 0,
            point_dgfm_marks($p['extra_species'] ?? null, $marksOf, $p['title'] ?? null)
        );
        // „von Paula" answers the question the row actually raises — the
        // guest's name where there is a guest row, the account's where a
        // member mapped along under her own name, and „Gast" only for a
        // guest who gave none.
        $guestName = trim((string) ($p['guest_name'] ?? ''));
        $isGuest   = $guestAccount !== null && (int) $p['user_id'] === $guestAccount;
        $prepared['owner'] = $guestName !== '' ? $guestName
            : (!$isGuest && ($accountName[(int) $p['user_id']] ?? '') !== ''
                ? $accountName[(int) $p['user_id']] : t('Gast'));
        $prepared['owner_id']     = (int) $p['user_id'];
        $prepared['excursion_id'] = (int) $p['excursion_id'];
        $prepared['editable']     = true;
        $out[$id] = $prepared;
    }
    return $out;
}

/**
 * The species of an excursion for its CLOSING PAGE — public, and built
 * so that it can be (13.09.2026: „auf der Abschlussseite,
 * wenn man den Link später aufruft und die Exkursion schon vorbei ist,
 * bitte kurz die Artenliste — wissenschaftliche und, falls es das gibt,
 * deutsche Namen. Ohne Finder und ohne Bestimmer, die Namen sollen nicht
 * öffentlich zugänglich sein. Dafür mit MTB, gekürzt auf das
 * Viertelquadrat, und mit Rote-Liste-Status").
 *
 * WHAT LEAVES THE HOUSE: the species name, its German name where a list
 * carries one, and the Red List category where one stands. WHAT DOES
 * NOT: any person (finder, determiner, guest, account) and any place —
 * the first draft carried the map sheet to the sixteenth, and the maintainer
 * took it out the same hour („Lass das MTB ganz weg"): a public page
 * says what was found, not where. The query below selects only what the
 * list shows, so nothing else can leak through a later edit of the page.
 *
 * ONE LINE PER SPECIES, the report's own merging (report_merge_groups:
 * by taxon reference, and by name where a find never got one) — on an
 * excursion several people map the same fungus, and a list that names
 * it three times says three times nothing.
 *
 * @return list<array{name: string, german: string, rl: string, finds: int}>
 */
function excursion_species_list(PDO $pdo, array $e): array
{
    require_once __DIR__ . '/export.php';
    require_once __DIR__ . '/species.php';

    $stmt = $pdo->prepare(
        'SELECT id, title, extra_species FROM map_points
          WHERE excursion_id = ? AND deleted_at IS NULL AND title <> \'\'
          ORDER BY title, id');
    $stmt->execute([(int) $e['id']]);
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        return [];
    }

    $refs = [];
    foreach ($rows as $p) {
        foreach (report_species_refs($p) as $ref) {
            $refs[substr($ref, 4)] = true;
        }
    }
    $germanOf = $refs === [] ? [] : species_german_names($pdo, array_keys($refs));
    $rlOf     = $refs === [] ? [] : species_endangerment($pdo, array_keys($refs));

    $out = [];
    foreach (report_merge_groups($rows) as $members) {
        $name = $german = $rl = '';
        foreach ($members as $p) {
            if ($name === '') {
                $name = preg_replace('/\s+/u', ' ', trim((string) $p['title'])) ?? '';
            }
            foreach (report_species_refs($p) as $ref) {
                $id = substr($ref, 4);
                if ($german === '' && isset($germanOf[$id])) {
                    $german = $germanOf[$id];
                }
                // „Ungefährdet" and „nicht bewertet" are no status worth a
                // column; the sheet's own scale keeps the rest in order.
                if ($rl === '' && isset($rlOf[$id]) && !in_array($rlOf[$id], ['*', 'nb'], true)) {
                    $rl = $rlOf[$id];
                }
            }
        }
        if ($name === '') {
            continue;
        }
        $out[] = ['name' => $name, 'german' => $german, 'rl' => $rl, 'finds' => count($members)];
    }
    usort($out, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
    return $out;
}

/**
 * Where the finds of an excursion lie — for the picture map on its closing
 * page (13.09.2026: „oben drüber auch die Karte einblenden — eine
 * Karte, die nicht zoombar ist, nicht verschiebbar, nur der Ausschnitt mit
 * den Punkten"). Bare coordinates, nothing else: the picture shows dots,
 * and dots have no name.
 *
 * @return list<array{0: float, 1: float}>  [lat, lon] per find
 */
function excursion_find_spots(PDO $pdo, array $e): array
{
    $stmt = $pdo->prepare(
        'SELECT lat, lon FROM map_points
          WHERE excursion_id = ? AND deleted_at IS NULL AND lat IS NOT NULL AND lon IS NOT NULL');
    $stmt->execute([(int) $e['id']]);
    return array_map(static fn(array $p): array => [(float) $p['lat'], (float) $p['lon']], $stmt->fetchAll());
}

/**
 * WHERE AN EXCURSION'S FINDS LIVE — the same WHERE for the fingerprints
 * and for the points themselves.
 *
 * IT MUST BE ONE EXPRESSION, not two that look alike. The device compares
 * a fingerprint with what it holds and fetches only what differs; if the
 * two sides disagreed by a single row, a bucket would be fetched, come
 * back with a different content than the fingerprint promised, and be
 * fetched again on the next load — for ever, and silently.
 *
 * One's own finds stay out here too (excursion_points explains why).
 */
function excursion_bucket_where(): string
{
    return 'p.deleted_at IS NULL AND p.excursion_id = ? AND p.user_id <> ?';
}

/**
 * The fingerprints of one excursion, bucket by bucket.
 *
 * The twin of container_bucket_status(); what it does and why a bucket is
 * always replaced whole stands in includes/bucket.php.
 */
function excursion_bucket_status(PDO $pdo, int $excursionId, int $userId): array
{
    if (!bucket_ready($pdo) || !excursions_ready($pdo)) {
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
          WHERE ' . excursion_bucket_where() . '
          GROUP BY bucket_key
          ORDER BY bucket_key DESC'
    );
    $stmt->execute([$excursionId, $userId]);

    $finger = $counts = [];
    foreach ($stmt as $z) {
        // Without a time no bucket — the same caution as everywhere else.
        if (($z['bucket_key'] ?? '') === '') {
            continue;
        }
        $finger[(string) $z['bucket_key']] = (string) $z['finger'];
        $counts[(string) $z['bucket_key']] = (int) $z['total'];
    }
    return ['finger' => $finger, 'counts' => $counts];
}

/**
 * The points of the requested buckets — prepared as foreign, editable
 * finds, exactly as excursion_points() hands them out.
 *
 * @return array{buckets: array<string, array>, open: string[]}
 */
function excursion_bucket_points(PDO $pdo, int $userId, int $excursionId, array $keys): array
{
    $wanted = array_values(array_unique(array_filter($keys, 'bucket_key_valid')));
    if (!bucket_ready($pdo) || $wanted === []) {
        return ['buckets' => [], 'open' => []];
    }
    // The leader's right, asked once for the whole request — a point of a
    // foreign excursion must not come out here even if its key is guessed.
    try {
        excursion_of_leader($pdo, $userId, $excursionId);
    } catch (InvalidArgumentException) {
        return ['buckets' => [], 'open' => []];
    }

    $marks = implode(',', array_fill(0, count($wanted), '?'));
    $stmt  = $pdo->prepare(
        'SELECT p.id, ' . bucket_expression('p') . ' AS bucket_key
           FROM map_points p
          WHERE ' . excursion_bucket_where() . '
            AND ' . bucket_expression('p') . ' IN (' . $marks . ')'
    );
    $stmt->execute([$excursionId, $userId, ...$wanted]);

    $idsOf = [];
    foreach ($stmt as $z) {
        $idsOf[(string) $z['bucket_key']][] = (int) $z['id'];
    }

    /* EVERY BUCKET AS {finger, points} — THE SHAPE IS THE CONTRACT, and
       getting it wrong was invisible until the sidebar stayed empty. The
       first version handed out a bare list of points per key; the device
       stored it as a record WITHOUT finger and WITHOUT points (put()
       reads exactly those two names), the count showed six and the map
       showed none — and on every load the bucket was fetched anew,
       because a missing fingerprint never matches. The shape stands in
       container_bucket_points(), and this time it is copied, not guessed.

       AN EMPTY BUCKET IS STILL AN ANSWER ('empty', like the containers):
       without it the device would ask for ever for something the
       excursion no longer holds. */
    $finger   = excursion_bucket_status($pdo, $excursionId, $userId)['finger'];
    $prepared = excursion_points($pdo, $userId);
    $out = [];
    foreach ($wanted as $key) {
        $out[$key] = ['finger' => $finger[$key] ?? 'empty', 'points' => []];
        foreach ($idsOf[$key] ?? [] as $id) {
            if (isset($prepared[$id])) {
                $out[$key]['points'][] = $prepared[$id];
            }
        }
    }
    return ['buckets' => $out, 'open' => []];
}

/** "30.08.2026 09:00" — the window as every view writes it. Lives here
 *  and not in the content file, because the guest page needs it too. */
function excursion_readable_time(string $t): string
{
    [$date, $clock] = explode(' ', $t);
    [$y, $m, $d] = explode('-', $date);
    return "$d.$m.$y " . substr($clock, 0, 5);
}

/**
 * Where the excursion stands relative to now — the word the list shows.
 * One place, so "läuft" can never mean two different comparisons.
 */
function excursion_phase(array $e, ?string $now = null): string
{
    $now ??= date('Y-m-d H:i:s');
    if ($now < $e['starts_at']) return 'geplant';
    if ($now > $e['ends_at']) return 'beendet';
    return 'läuft';
}

// ---------------------------------------------------------------------------
// The guest access (stage 2)
// ---------------------------------------------------------------------------

/** The one collecting account for all guest finds — see migration 0074. */
const EXCURSION_GUEST_EMAIL = 'gast@exkursion.local';

/** Stage 2 readiness: guest table, point reference AND the account. */
function excursion_guests_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_excursion_guests')
        && column_exists($pdo, 'map_points', 'excursion_id')
        && excursion_guest_user_id($pdo) !== null;
}

function excursion_guest_user_id(PDO $pdo): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM map_users WHERE email = ?');
    $stmt->execute([EXCURSION_GUEST_EMAIL]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** The excursion behind a join token — null instead of an exception:
 *  a stale QR photo is the NORMAL case at this door, not a fault. */
function excursion_by_token(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_excursions WHERE join_token = ?');
    $stmt->execute([$token]);
    $e = $stmt->fetch();
    return $e === false ? null : $e;
}

/** Stage 8 readiness: the split name columns of migration 0076. */
function excursion_guest_names_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_excursion_guests', 'first_name');
}

/**
 * Register a guest — or recognize one: the same e-mail on the same
 * excursion is the same person coming back (a new phone, a reloaded
 * page), and gets the same row so the later attribution counts him once.
 * Without an e-mail every registration is a new guest; there is nothing
 * to recognize him by, and that is the named price of withholding it.
 *
 * FIRST AND LAST NAME SEPARATELY (24.08.2026), both
 * required: a later report sorts by last name, and a single field can
 * never be split reliably again. The NAME column stays the composed
 * display name every view reads.
 *
 * @return int the guest id
 * @throws InvalidArgumentException
 */
function excursion_guest_register(PDO $pdo, int $excursionId,
                                  string $firstName, string $lastName, string $email,
                                  ?int $proven = null): int
{
    $firstName = mb_substr(trim($firstName), 0, 100);
    $lastName  = mb_substr(trim($lastName), 0, 100);
    if ($firstName === '' || $lastName === '') {
        throw new InvalidArgumentException(
            t('Vor- und Nachname sind Pflicht — sie stehen später als Finder am Fund.'));
    }
    $name = $firstName . ' ' . $lastName;
    $split = excursion_guest_names_ready($pdo);

    $email = mb_strtolower(trim($email));
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(t('Die E-Mail-Adresse ist nicht lesbar — oder lass sie weg.'));
        }
        /* A RETURNING GUEST IS RECOGNIZED ONLY WITH PROOF (security
           review, 23.09.2026). The email alone used to be enough: whoever
           held the join link — a QR code handed round the car park — and
           typed another participant's address BECAME that participant,
           with his finds, photos and email. $proven is the guest this
           browser demonstrably joined as before (a signed cookie, see
           excursion_guest_proof); only that row is continued. Without it
           a new guest row is made, even under the same address — two rows
           for one person are an inconvenience, a stolen identity is not. */
        $stmt = $pdo->prepare(
            'SELECT id FROM map_excursion_guests WHERE excursion_id = ? AND email = ? AND id = ?');
        $stmt->execute([$excursionId, $email, (int) ($proven ?? 0)]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            // The name may have been corrected on the second visit — the
            // newest wording wins, it is the same person.
            $pdo->prepare('UPDATE map_excursion_guests SET name = ?'
                . ($split ? ', first_name = ?, last_name = ?' : '') . ' WHERE id = ?')
                ->execute($split
                    ? [$name, $firstName, $lastName, (int) $existing]
                    : [$name, (int) $existing]);
            return (int) $existing;
        }
    }
    $pdo->prepare(
        'INSERT INTO map_excursion_guests (excursion_id, name'
        . ($split ? ', first_name, last_name' : '') . ', email, created_at)
         VALUES (?, ?' . ($split ? ', ?, ?' : '') . ', ?, NOW())'
    )->execute($split
        ? [$excursionId, $name, $firstName, $lastName, $email === '' ? null : $email]
        : [$excursionId, $name, $email === '' ? null : $email]);
    return (int) $pdo->lastInsertId();
}

/** The cookie that proves a browser joined excursion $excursionId as a guest. */
function excursion_guest_cookie(int $excursionId): string
{
    return 'excursion_guest_' . $excursionId;
}

/** The guest this browser has proven to be on this excursion — or null. */
function excursion_guest_proof(int $excursionId): ?int
{
    require_once __DIR__ . '/secret.php';
    $value = app_unsign((string) ($_COOKIE[excursion_guest_cookie($excursionId)] ?? ''));
    return $value !== null && ctype_digit($value) ? (int) $value : null;
}

/** Every guest this browser has proven to be, on any excursion. */
function excursion_guest_proofs(): array
{
    require_once __DIR__ . '/secret.php';
    $out = [];
    foreach ($_COOKIE as $name => $value) {
        if (!is_string($name) || !str_starts_with($name, 'excursion_guest_') || !is_string($value)) {
            continue;
        }
        $id = app_unsign($value);
        if ($id !== null && ctype_digit($id)) {
            $out[] = (int) $id;
        }
    }
    return array_values(array_unique($out));
}

/** Hand the browser its proof — thirty days, longer than any excursion lasts. */
function excursion_guest_proof_set(int $excursionId, int $guestId): void
{
    require_once __DIR__ . '/secret.php';
    setcookie(excursion_guest_cookie($excursionId), app_sign((string) $guestId), [
        'expires'  => time() + 30 * 86400,
        'path'     => BASE_URL,
        'httponly' => true,
        'secure'   => !IS_LOCAL,
        'samesite' => 'Lax',
    ]);
}

/**
 * A guest find. The window is checked HERE, not only at the door: the
 * page may have been open since morning, and a point at half past
 * midnight belongs to no excursion anymore.
 *
 * AN EXCURSION IS A FIND MAPPING (22.08.2026: „das wird ja
 * eine Artenliste") — guests fill the same fixed find fields every
 * species list carries (find_fields.php): Substrat/Wirt, Begleit-
 * vegetation, Menge, Beleg-Nr. … The names are WHITELISTED against that
 * set here, at the trust boundary: whatever else a request carries does
 * not become a field. The finder defaults to the guest's name; reports
 * and exports need no guest special path.
 *
 * @return int the new point id
 * @throws InvalidArgumentException
 */
function excursion_point_add(PDO $pdo, array $e, int $guestId, string $guestName, array $data): int
{
    /* THE FIND TIME GATES, NOT THE ARRIVAL (offline, stage 4): a find
       set at 15:58 in a dead zone reaches the server when the queue
       drains — maybe after the window closed. The device clock counts,
       as everywhere in this application; what it claims must lie INSIDE
       the window, and the arrival gets a day of grace for the draining.
       Without a claimed time the server's now must do — then the window
       has to be open in the ordinary sense. */
    $foundAt = array_key_exists('found_at', $data) && $data['found_at'] !== null
        ? point_find_datetime($data['found_at'])
        : date('Y-m-d H:i:s');
    if ($foundAt === null || $foundAt < $e['starts_at'] || $foundAt > $e['ends_at']) {
        throw new InvalidArgumentException(
            t('Die Fundzeit liegt außerhalb des Zeitfensters der Exkursion.'));
    }
    if (date('Y-m-d H:i:s') > date('Y-m-d H:i:s', strtotime($e['ends_at'] . ' +1 day'))) {
        throw new InvalidArgumentException(
            t('Das Zeitfenster der Exkursion ist vorbei — es können keine Funde mehr dazukommen.'));
    }
    $guestUser = excursion_guest_user_id($pdo);
    if ($guestUser === null) {
        throw new InvalidArgumentException(t('Das Gastkonto fehlt — die Migration 0074 ist nicht eingespielt.'));
    }

    // 'fields' comes from the page's form, 'extra' from the map's point
    // window — same content, two senders, ONE whitelist.
    $extra = excursion_fields_sift((array) ($data['fields'] ?? $data['extra'] ?? []));
    if (($extra['Finder'] ?? '') === '') {
        $extra['Finder'] = $guestName;
    }

    $pointId = point_create($pdo, $guestUser, [
        'lat'    => $data['lat'] ?? null,
        'lon'    => $data['lon'] ?? null,
        // The provisional key of the offline queue — point_create's
        // double-click shield: a retry after a lost answer returns the
        // existing point instead of a twin.
        'client_id' => $data['client_id'] ?? null,
        'title'  => (string) ($data['title'] ?? ''),
        'note'   => $data['note'] ?? null,
        'found_at' => $foundAt,
        'extra'  => $extra,
        'excursion_id'       => (int) $e['id'],
        'excursion_guest_id' => $guestId,
    ]);

    /* A GUEST WHOSE ROW IS ALREADY TIED TO AN ACCOUNT (co-ownership,
       step 5, 11.09.2026): he registered, his rows were claimed, and his
       phone still maps under the guest session — the find is his the
       moment it exists, exactly as the claim would have made it. Without
       this the claim's hook and this door would disagree about the same
       person. Best effort: a hiccup here must not lose a find. */
    try {
        $tied = $pdo->prepare('SELECT user_id FROM map_excursion_guests WHERE id = ? AND user_id IS NOT NULL');
        $tied->execute([$guestId]);
        $account = $tied->fetchColumn();
        if ($account !== false && $account !== null) {
            require_once __DIR__ . '/point_owners.php';
            point_owner_add($pdo, $pointId, (int) $account);
            cloud_copies_backfill_user($pdo, (int) $account);
        }
    } catch (Throwable $ex) {
        error_log('excursion_point_add co-ownership (' . $pointId . '): ' . $ex->getMessage());
    }
    return $pointId;
}

// ---------------------------------------------------------------------------
// The guest as an actor of the main app (stage 3: "der Gastzugang hat die
// gleichen Funktionen wie der Hauptzugang", 22.08.2026). The
// join page sets the session; the API asks here who is acting.
// ---------------------------------------------------------------------------

/**
 * The guest actor from the session — or null. VALID ONLY WHILE THE
 * WINDOW IS OPEN: the QR access ends with it, without any revocation
 * step, and so does the session's power. A stale actor is cleared, not
 * tolerated — the next request would ask again.
 *
 * @return ?array{excursion: array, guest_id: int, name: string}
 */
function excursion_actor(PDO $pdo): ?array
{
    $raw = $_SESSION['excursion_actor'] ?? null;
    if (!is_array($raw) || !excursions_ready($pdo) || !excursion_guests_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_excursions WHERE id = ?');
    $stmt->execute([(int) ($raw['excursion_id'] ?? 0)]);
    $e = $stmt->fetch();
    /* DIES WITH THE WINDOW'S END, NOT BEFORE ITS START
       (13.09.2026, after an excursion where most phones had no reception
       at the car park: „den Link kann man doch auch vorher verschicken,
       und man kann sich dann vorher die Karte runterladen"). A guest may
       come through the door days ahead, walk the onboarding and load
       the leader's area onto his phone at home; what he cannot do is
       add a find — every find is held to the window by its own time
       (excursion_point_add), whatever the door let in. Over is still
       over: an ended excursion clears the session. */
    if ($e === false || excursion_phase($e) === 'beendet') {
        unset($_SESSION['excursion_actor']);
        return null;
    }
    $stmt = $pdo->prepare('SELECT name FROM map_excursion_guests WHERE id = ? AND excursion_id = ?');
    $stmt->execute([(int) ($raw['guest_id'] ?? 0), (int) $e['id']]);
    $name = $stmt->fetchColumn();
    if ($name === false) {
        unset($_SESSION['excursion_actor']);
        return null;
    }
    return ['excursion' => $e, 'guest_id' => (int) $raw['guest_id'], 'name' => (string) $name];
}

/**
 * THE EXCURSION A LOGGED-IN PARTICIPANT IS MAPPING FOR, or null.
 *
 * Not an actor and not a right: he stays himself, his finds stay in his
 * own stock, and this says only which reference they carry (the
 * concept's ruling — „als Verweis, nicht als Kopie"). The session key is
 * set at the QR door and in the leader's own tab; the map's badge offers
 * the way out of it.
 *
 * IT DIES WITH THE WINDOW'S END and clears itself doing so, exactly like
 * the guest actor above: over means over for everyone, and a session that
 * outlived the day would go on stamping finds with a finished excursion.
 * BEFORE THE START IT STANDS (13.09.2026, the same reason as the actor's):
 * the participant who joined ahead sees the badge with the start time on
 * it and loads the map; the stamp itself waits for the window
 * (api/v1/points.php asks the phase at every save).
 *
 * TWO CALLERS READ IT — the map's start payload and the onboarding's map
 * offer — and they must not answer the question differently. That is the
 * whole reason it stands here instead of inline in one of them.
 */
function excursion_mapping_current(PDO $pdo): ?array
{
    $id = (int) ($_SESSION['excursion_mapping'] ?? 0);
    if ($id <= 0 || !excursions_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM map_excursions WHERE id = ?');
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    if ($e === false || excursion_phase($e) === 'beendet') {
        unset($_SESSION['excursion_mapping']);
        return null;
    }
    return $e;
}

/** May somebody still come through the door — ahead of the window or inside it. */
function excursion_joinable(array $e): bool
{
    return excursion_phase($e) !== 'beendet';
}

/**
 * ONE of the guest's own finds — scoped by excursion AND guest, never by
 * the account alone: the guest account is the archive of every
 * excursion, and this line is the border between "meins" and all of it.
 */
function excursion_guest_point(PDO $pdo, array $actor, int $pointId): ?array
{
    $guestUser = excursion_guest_user_id($pdo);
    if ($guestUser === null) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM map_points
          WHERE id = ? AND user_id = ? AND excursion_id = ? AND excursion_guest_id = ?');
    $stmt->execute([$pointId, $guestUser, (int) $actor['excursion']['id'], $actor['guest_id']]);
    $p = $stmt->fetch();
    return $p === false ? null : $p;
}

/**
 * The find-field whitelist, shared by creating and changing: only the
 * fixed set becomes a field (find_fields.php), a choice only its own
 * options — whatever else a request carries is not data but noise.
 */
function excursion_fields_sift(array $raw): array
{
    require_once __DIR__ . '/find_fields.php';
    $extra = [];
    foreach (find_fields() as $f) {
        $value = trim((string) ($raw[$f['name']] ?? ''));
        if ($value === '') {
            continue;
        }
        if ($f['type'] === 'choice' && !in_array($value, (array) $f['options'], true)) {
            continue;
        }
        $extra[$f['name']] = mb_substr($value, 0, 2000);
    }
    return $extra;
}

/**
 * THE LEADER'S RIGHT AT THE POINT — the carrying thought of the concept
 * made executable: the right hangs on the excursion reference, not on
 * the account. If $userId leads (main or co) the excursion this point
 * carries, the point's OWNER id comes back — the id to write with
 * (point_update, point_delete are owner-scoped). Null means: no
 * excursion point, or not your excursion — indistinguishable on
 * purpose, like everywhere else.
 */
function excursion_point_leader_scope(PDO $pdo, int $userId, int $pointId): ?int
{
    if (!excursion_guests_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT user_id, excursion_id FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $p = $stmt->fetch();
    if ($p === false || $p['excursion_id'] === null) {
        return null;
    }
    try {
        excursion_of_leader($pdo, $userId, (int) $p['excursion_id']);
    } catch (InvalidArgumentException) {
        return null;
    }
    return (int) $p['user_id'];
}

/**
 * A REGISTERED PARTICIPANT joins (24.08.2026): whoever scans
 * the QR code while logged in needs no form — name and e-mail stand in
 * his account, and the e-mail is the recognition key anyway. He gets a
 * guest row like everyone else, tied to his account from the first
 * second, so the later attribution has nothing left to do.
 *
 * HIS FINDS STAY IN HIS OWN ACCOUNT (the concept's ruling: „als Verweis,
 * nicht als Kopie") — they merely carry the excursion's reference, and
 * the leader's right hangs on exactly that, not on the guest account.
 *
 * @return int the guest id
 */
function excursion_join_as_user(PDO $pdo, array $e, array $user): int
{
    $email = mb_strtolower(trim((string) ($user['email'] ?? '')));
    if (excursion_attribution_ready($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT id FROM map_excursion_guests
              WHERE excursion_id = ? AND (user_id = ? OR (id = ? AND user_id IS NULL)) LIMIT 1');
        // His own row, or the UNCLAIMED guest row this browser proves —
        // never a row found by the address alone (see excursion_claim),
        // and never one already tied to somebody else.
        $stmt->execute([(int) $e['id'], (int) $user['id'], (int) (excursion_guest_proof((int) $e['id']) ?? 0)]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            $pdo->prepare('UPDATE map_excursion_guests SET user_id = ? WHERE id = ?')
                ->execute([(int) $user['id'], (int) $existing]);
            return (int) $existing;
        }
    }
    $first = (string) ($user['first_name'] ?? '');
    $last  = (string) ($user['last_name'] ?? '');
    // An account without a name would fail the guest rules — then its
    // display name does, and that one always exists.
    if (trim($first) === '' || trim($last) === '') {
        $first = user_display_name($user);
        $last  = '·';
    }
    $guestId = excursion_guest_register($pdo, (int) $e['id'], $first, $last, $email);
    if (excursion_attribution_ready($pdo)) {
        $pdo->prepare('UPDATE map_excursion_guests SET user_id = ? WHERE id = ?')
            ->execute([(int) $user['id'], $guestId]);
    }
    return $guestId;
}

/*
 * MAPPING ALONG FROM THE MAP (13.09.2026: „Das Teilnehmen an
 * der eigenen Exkursion ist etwas kompliziert in der Verwaltung
 * versteckt. Alle Exkursionsleiter sollen automatisch, wenn sie die App
 * öffnen, eine Badge angezeigt kriegen — jetzt teilnehmen —, wenn sie im
 * Zeitfenster der Exkursion sind"). The administration's 'map_along'
 * did exactly this behind a tab and a button; now the map offers it in
 * its badge stack (map.js, drawMappingOffer), and both go through here.
 *
 * WHO MAY: a leader, main or co — and a registered participant who
 * already stands in the guest list under his account, because the same
 * afternoon a participant tapped the badge's cross away by mistake and
 * had no way back but the QR code. The window must be open; the
 * excursion decides that, not the caller.
 */

/** The excursion, for a leader OR a participant already on its list. */
function excursion_of_participant(PDO $pdo, int $userId, int $id): array
{
    try {
        return excursion_of_leader($pdo, $userId, $id);
    } catch (InvalidArgumentException $refused) {
        if (!excursion_attribution_ready($pdo)) {
            throw $refused;
        }
        $stmt = $pdo->prepare(
            'SELECT e.* FROM map_excursions e
               JOIN map_excursion_guests g ON g.excursion_id = e.id AND g.user_id = ?
              WHERE e.id = ? LIMIT 1');
        $stmt->execute([$userId, $id]);
        $e = $stmt->fetch();
        if ($e === false) {
            throw $refused;
        }
        return $e;
    }
}

/**
 * Start mapping for an excursion: a guest row under the account (so the
 * report knows the finder) and the mark in the session that every new
 * find reads (api/v1/points.php). Returns the excursion.
 * @throws InvalidArgumentException  not his, or the window is shut
 */
function excursion_map_along(PDO $pdo, int $userId, int $excursionId): array
{
    $e = excursion_of_participant($pdo, $userId, $excursionId);
    if (excursion_phase($e) !== 'läuft') {
        throw new InvalidArgumentException(t('Das Zeitfenster ist gerade nicht offen.'));
    }
    if (excursion_guests_ready($pdo)) {
        excursion_join_as_user($pdo, $e, user_current() ?? ['id' => $userId]);
    }
    $_SESSION['excursion_mapping'] = (int) $e['id'];
    return $e;
}

/**
 * What the map is told about the excursion it maps for — the start
 * payload's 'mapping' (index.php) and the answer to joining from the map
 * (api/v1/excursion_mapping.php) are the same shape, built here once.
 */
function excursion_mapping_payload(PDO $pdo, array $e): array
{
    return [
        'id'   => (int) $e['id'],
        'name' => (string) $e['name'],
        // Until when — the badge's question says so before one leaves —
        // and from when, for whoever joined ahead: the badge then says
        // „ab 09:00" until the window opens (map.js, drawMappingBadge).
        'until' => excursion_readable_time((string) $e['ends_at']),
        'from'  => excursion_readable_time((string) $e['starts_at']),
        'starts_at' => (string) $e['starts_at'],
        /* THE LEADER'S AREA RIDES ALONG (09.09.2026). The
           guest gets it offered as the onboarding's third step and
           carries it in 'guest'; the registered participant stands at
           the SAME car park with the same reception and used to walk
           past the offer, because everything about that area hung on
           the guest key. It hangs here instead because he is not a
           guest — and the offline zoom cap needs the outline too, or the
           tiles he just fetched stay unused (storedZoomHere in map.js). */
        'area' => excursion_area($pdo, $e),
    ];
}

/**
 * The excursions the map may offer to map along for: the ones he leads
 * and the ones he already joined, each with its window — the map keeps
 * the clock, so an offer can appear while the page is already open, and
 * ended ones stay out of the payload altogether.
 *
 * @return list<array{id:int, name:string, starts_at:string, ends_at:string}>
 */
function excursion_mapping_offers(PDO $pdo, int $userId): array
{
    if (!excursions_ready($pdo)) {
        return [];
    }
    $now = date('Y-m-d H:i:s');
    $rows = [];
    foreach (excursions_mine($pdo, $userId) as $e) {
        $rows[(int) $e['id']] = $e;
    }
    if (excursion_attribution_ready($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT e.* FROM map_excursions e
               JOIN map_excursion_guests g ON g.excursion_id = e.id AND g.user_id = ?
              WHERE e.ends_at >= ?');
        $stmt->execute([$userId, $now]);
        foreach ($stmt as $e) {
            $rows[(int) $e['id']] ??= $e;
        }
    }
    $out = [];
    foreach ($rows as $e) {
        if ((string) $e['ends_at'] < $now) {
            continue;
        }
        $out[] = ['id' => (int) $e['id'], 'name' => (string) $e['name'],
                  'starts_at' => (string) $e['starts_at'], 'ends_at' => (string) $e['ends_at']];
    }
    usort($out, static fn(array $a, array $b): int => strcmp($a['starts_at'], $b['starts_at']));
    return $out;
}

/**
 * A guest corrects his own name — or hands in the e-mail he kept at the
 * door (24.08.2026: „solange die Exkursion läuft").
 *
 * THE NAME TRAVELS TO THE FINDS ALREADY MADE. It sits in each point's
 * Finder field, written at birth (excursion_point_add) — a correction
 * that only reached future finds would leave the day's harvest under the
 * old spelling, and the report would show both. Only where the field
 * still holds the OLD name: whoever typed a different finder into a
 * single find meant it.
 *
 * THE E-MAIL IS THE RECOGNITION KEY, so a second row on the same
 * excursion must not take it: that would be two people claiming one
 * later attribution. Said plainly instead of silently keeping the old.
 *
 * @throws InvalidArgumentException
 */
function excursion_guest_update(PDO $pdo, array $actor,
                                string $firstName, string $lastName, string $email): array
{
    $firstName = mb_substr(trim($firstName), 0, 100);
    $lastName  = mb_substr(trim($lastName), 0, 100);
    if ($firstName === '' || $lastName === '') {
        throw new InvalidArgumentException(t('Vor- und Nachname sind Pflicht.'));
    }
    $name = $firstName . ' ' . $lastName;
    $email = mb_strtolower(trim($email));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(t('Die E-Mail-Adresse ist nicht lesbar — oder lass sie weg.'));
    }

    $excursionId = (int) $actor['excursion']['id'];
    $guestId     = $actor['guest_id'];

    if ($email !== '') {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM map_excursion_guests
              WHERE excursion_id = ? AND email = ? AND id <> ?');
        $stmt->execute([$excursionId, $email, $guestId]);
        if ($stmt->fetchColumn() !== false) {
            throw new InvalidArgumentException(
                t('Diese E-Mail gehört bei dieser Exkursion schon zu jemand anderem.'));
        }
    }

    /* THE OLD SPELLING COMES FROM THE ROW, and the finds are renamed
       BEFORE it is overwritten. Both matter, and one request taught me
       why: the first attempt updated the row and then died in the loop
       (a fatal error, arriving as a bare 500) — on the retry the "old"
       name was already the new one, no find matched, and the day's
       harvest kept the old spelling for good. In this order an
       interrupted run leaves the row untouched, so a second attempt
       still finds its work. */
    $stmt = $pdo->prepare('SELECT name FROM map_excursion_guests WHERE id = ?');
    $stmt->execute([$guestId]);
    $before = (string) ($stmt->fetchColumn() ?: $actor['name']);

    // The finds of this excursion that still carry the old spelling.
    $guestUser = excursion_guest_user_id($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, extra FROM map_points
          WHERE user_id = ? AND excursion_id = ? AND excursion_guest_id = ?');
    $stmt->execute([$guestUser, $excursionId, $guestId]);
    $renamed = 0;
    foreach ($stmt->fetchAll() as $p) {
        // point_extra_read gives an OBJECT, not an array — it says so in
        // its signature, and reading it as an array is a fatal error that
        // arrives as a bare 500 (measured, 24.08.2026). Cast once, here.
        $extra = (array) point_extra_read($p['extra'] ?? null);
        $finder = (string) ($extra['Finder'] ?? '');
        // Only where the old spelling still stands: whoever typed a
        // different finder into a single find meant it.
        if ($finder !== $before) {
            continue;
        }
        $extra['Finder'] = $name;
        point_update($pdo, $guestUser, (int) $p['id'], ['extra' => $extra]);
        $renamed++;
    }

    $split = excursion_guest_names_ready($pdo);
    $pdo->prepare('UPDATE map_excursion_guests SET name = ?, email = ?'
        . ($split ? ', first_name = ?, last_name = ?' : '') . ' WHERE id = ?')
        ->execute($split
            ? [$name, $email === '' ? null : $email, $firstName, $lastName, $guestId]
            : [$name, $email === '' ? null : $email, $guestId]);

    $_SESSION['excursion_guest'][$excursionId]['name'] = $name;
    return ['name' => $name, 'renamed' => $renamed];
}

/**
 * The guest's slice of the points API (api/v1/points.php). Answers and
 * TERMINATES like every endpoint — points.php only decides who is
 * acting. What a guest may do is exactly this list, nothing more:
 *
 *   GET            his own finds of THIS excursion
 *   GET ?id=…      one of them
 *   POST           a new find (window checked again in excursion_point_add)
 *   PATCH ?id=…    title, note, spot and find fields of his own
 *   DELETE ?id=…   his own, into the guest account's trash
 *
 * No restore, no permanent delete, no foreign rights: the trash of an
 * excursion belongs to its leader (concept, 23.08.2026), and everything
 * beyond this list is the account's ARCHIVE — other people's excursions.
 */
function excursion_guest_api(PDO $pdo, array $actor, string $method, array $input): void
{
    $e         = $actor['excursion'];
    $guestUser = excursion_guest_user_id($pdo);

    switch ($method) {
        case 'GET':
            $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
            if ($id > 0) {
                $own = excursion_guest_point($pdo, $actor, $id);
                $own === null
                    ? json_error(t('Punkt nicht gefunden.'), 404)
                    : json_response(excursion_guest_prepared($pdo, $own));
            }
            json_response([
                'points'  => excursion_guest_points_full($pdo, $actor),
                'touched_at' => now(),
            ]);
            // json_response terminates

        case 'POST':
            $id = excursion_point_add($pdo, $e, $actor['guest_id'], $actor['name'], $input);
            json_response(excursion_guest_prepared($pdo,
                excursion_guest_point($pdo, $actor, $id)), 201);

        case 'PATCH':
        case 'PUT':
            $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
            if (excursion_guest_point($pdo, $actor, $id) === null) {
                json_error(t('Punkt nicht gefunden.'), 404);
            }
            // Only what a find is made of — no lists, no photos yet, and
            // the extra runs through the same whitelist as at birth. The
            // finder never falls empty: without him the find would lose
            // the one thing the registration was for.
            $sifted = array_intersect_key($input, array_flip(['title', 'note', 'lat', 'lon']));
            if (isset($input['extra'])) {
                $extra = excursion_fields_sift((array) $input['extra']);
                if (($extra['Finder'] ?? '') === '') {
                    $extra['Finder'] = $actor['name'];
                }
                $sifted['extra'] = $extra;
            }
            point_update($pdo, $guestUser, $id, $sifted);
            json_response(excursion_guest_prepared($pdo, excursion_guest_point($pdo, $actor, $id)));

        case 'DELETE':
            $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
            if (excursion_guest_point($pdo, $actor, $id) === null) {
                json_error(t('Punkt nicht gefunden.'), 404);
            }
            point_delete($pdo, $guestUser, $id);
            json_response(['deleted' => $id]);

        default:
            json_error(t('Methode nicht unterstützt.'), 405);
    }
}

/**
 * Living photos of ONE guest on this excursion — what the measure
 * counts. PER PARTICIPANT, not per excursion (24.08.2026):
 * a shared pool would refuse a guest for pictures he cannot see and
 * cannot delete — an error nobody can act on. Photos on trashed finds
 * do not count; the leader's tidying frees room like one's own
 * deleting does.
 */
function excursion_guest_photo_count(PDO $pdo, int $excursionId, int $guestId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM map_photos f
           JOIN map_points p ON p.id = f.point_id
          WHERE p.excursion_id = ? AND p.excursion_guest_id = ? AND p.deleted_at IS NULL');
    $stmt->execute([$excursionId, $guestId]);
    return (int) $stmt->fetchColumn();
}

/**
 * The gate before any guest upload. Throws with a sentence the guest
 * can ACT on — that is the whole point of the per-participant measure.
 * @throws InvalidArgumentException
 */
function excursion_photo_gate(PDO $pdo, array $e, int $guestId): void
{
    if (empty($e['photos_allowed'])) {
        throw new InvalidArgumentException(
            t('Der Leiter hat Fotos für diese Exkursion ausgeschaltet.'));
    }
    $limit = $e['photo_limit'] === null ? null : (int) $e['photo_limit'];
    if ($limit !== null
        && excursion_guest_photo_count($pdo, (int) $e['id'], $guestId) >= $limit) {
        throw new InvalidArgumentException(
            t('Deine %d Fotos für diese Exkursion sind voll — lösch eines deiner Fotos, dann passt ein neues.', $limit));
    }
}

// ---------------------------------------------------------------------------
// The attribution (stage 7): a guest becomes — or already was — an
// account, and his guest rows learn it. The finds never move; the tie is
// everything ("immer nur ein Verweis, für alle gleich").
// ---------------------------------------------------------------------------

/** Stage 7 readiness — the code goes live before migration 0075. */
function excursion_attribution_ready(PDO $pdo): bool
{
    return excursion_guests_ready($pdo)
        && column_exists($pdo, 'map_excursion_guests', 'user_id');
}

/**
 * Tie every unclaimed guest row with this e-mail to the account. Runs on
 * EVERY login — idempotent and one cheap UPDATE, and only that makes it
 * complete: it catches the guest who registers later AND the participant
 * who had an account all along and joined excursions in between. Only
 * unclaimed rows: a tie, once made, is never silently rewritten.
 *
 * @return int how many rows were newly tied
 */
function excursion_claim(PDO $pdo, int $userId, string $email = ''): int
{
    /* BY PROOF, NOT BY ADDRESS (security review, 23.09.2026). The rows
       used to be tied by the e-mail alone — and an account's address can
       be changed at will, unconfirmed (the application sends no mail).
       Whoever set a guest's address as his own and logged in again became
       co-owner of all that guest's finds, with their coordinates, their
       pictures and copies in his cloud. Now a row is tied only when THIS
       browser proves it joined as that guest — the signed cookie the join
       left behind (excursion_guest_proof). The address no longer takes
       part — unless it is CONFIRMED (below). $email stays in the
       signature for the callers. */
    if (!excursion_attribution_ready($pdo)) {
        return 0;
    }
    $proven = excursion_guest_proofs();
    /* AND A CONFIRMED ADDRESS COUNTS AGAIN (migration 0116, the same
       evening): once the account's address is proven by its link, the
       guest rows under that address are its own on any device — the
       cross-device claim the proof cookie alone had to give up. An
       unconfirmed address still ties nothing. */
    $confirmedEmail = '';
    if (column_exists($pdo, 'map_users', 'email_confirmed_at')) {
        $q = $pdo->prepare('SELECT email FROM map_users WHERE id = ? AND email_confirmed_at IS NOT NULL');
        $q->execute([$userId]);
        $confirmedEmail = mb_strtolower(trim((string) ($q->fetchColumn() ?: '')));
    }
    if ($proven === [] && $confirmedEmail === '') {
        return 0;
    }
    $where = [];
    $args  = [];
    if ($proven !== []) {
        $where[] = 'id IN (' . implode(',', array_fill(0, count($proven), '?')) . ')';
        $args = $proven;
    }
    if ($confirmedEmail !== '') {
        $where[] = 'email = ?';
        $args[] = $confirmedEmail;
    }
    // Which rows are about to be tied — the UPDATE below cannot say
    // afterwards, and the co-ownership hook needs exactly those.
    $stmt = $pdo->prepare('SELECT id FROM map_excursion_guests WHERE (' . implode(' OR ', $where) . ') AND user_id IS NULL');
    $stmt->execute($args);
    $tied = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if ($tied === []) {
        return 0;
    }
    $space = implode(',', array_fill(0, count($tied), '?'));
    $stmt = $pdo->prepare("UPDATE map_excursion_guests SET user_id = ? WHERE id IN ($space) AND user_id IS NULL");
    $stmt->execute(array_merge([$userId], $tied));
    $n = $stmt->rowCount();

    /* THE PARTICIPANT BECOMES A CO-OWNER OF HIS OLD GUEST FINDS
       (co-ownership, step 5, 11.09.2026). They stay in the guest account
       — that is the excursion's archive — but he took their voucher
       photos, and taking a picture is what makes one a co-owner by
       the rule. Through the row he reaches them like any co-owned
       find: in his stock, with every picture the leader attached, and —
       if he has a cloud, now or later — with copies of them (the backfill
       runs here and again at connecting). Before this, a participant's
       finds reached his map through no channel at all. */
    if ($n > 0 && $tied !== []) {
        try {
            require_once __DIR__ . '/point_owners.php';
            $space = implode(',', array_fill(0, count($tied), '?'));
            $q = $pdo->prepare("SELECT id FROM map_points WHERE excursion_guest_id IN ($space) AND deleted_at IS NULL");
            $q->execute($tied);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $pointId) {
                point_owner_add($pdo, (int) $pointId, $userId);
            }
            cloud_copies_backfill_user($pdo, $userId);
        } catch (Throwable $e) {
            error_log('excursion_claim co-ownership (' . $userId . '): ' . $e->getMessage());
        }
    }
    return $n;
}

/**
 * The excursions this account took part in as a guest — one entry per
 * guest row, with the excursion attached. What the Touren pane lists
 * beneath the led ones.
 */
function excursions_participated(PDO $pdo, int $userId): array
{
    if (!excursion_attribution_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT e.*, g.id AS guest_id, g.name AS guest_name
           FROM map_excursion_guests g
           JOIN map_excursions e ON e.id = g.excursion_id
          WHERE g.user_id = ?
          ORDER BY e.starts_at DESC, e.id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * The PARTICIPANT'S right at the point: it is his own old guest find —
 * the point's guest row is tied to his account. No time window: the
 * excursion is over, but these finds are HIS memory of it, and tending
 * them (a corrected name, a better note) needs no leader's blessing.
 * Returns the owner id to write with, like the leader scope.
 */
function excursion_point_participant_scope(PDO $pdo, int $userId, int $pointId): ?int
{
    if (!excursion_attribution_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT p.user_id
           FROM map_points p
           JOIN map_excursion_guests g ON g.id = p.excursion_guest_id AND g.user_id = ?
          WHERE p.id = ?');
    $stmt->execute([$userId, $pointId]);
    $owner = $stmt->fetchColumn();
    return $owner === false ? null : (int) $owner;
}

/**
 * THE TRASH PER EXCURSION (stage 6b, concept of 23.08.2026): the leader
 * sees what was deleted in HIS excursion and may remove it for good —
 * otherwise the truly bad finds would lie in the guest account's trash
 * forever, photos included. The OPERATOR sees every excursion's trash;
 * deliberately NOT "the admin sees foreign trashes" in general — the
 * right stays at the excursion reference, like everything else.
 *
 * @return array [['excursion' => row, 'leader_name' => ?string,
 *                 'points' => rows+guest_name], …] — only non-empty ones
 */
function excursion_trash(PDO $pdo, array $me): array
{
    if (!excursions_ready($pdo) || !excursion_guests_ready($pdo)) {
        return [];
    }
    $userId = (int) $me['id'];
    if (is_admin($me)) {
        $stmt = $pdo->query(
            'SELECT e.*, 1 AS is_main, ' . sql_display_name('u') . ' AS leader_name
               FROM map_excursions e JOIN map_users u ON u.id = e.leader_user_id
              ORDER BY e.starts_at DESC, e.id DESC');
        $mine = $stmt->fetchAll();
    } else {
        $mine = excursions_mine($pdo, $userId);
    }

    $out = [];
    foreach ($mine as $e) {
        $stmt = $pdo->prepare(
            'SELECT p.*, g.name AS guest_name
               FROM map_points p
               LEFT JOIN map_excursion_guests g ON g.id = p.excursion_guest_id
              WHERE p.excursion_id = ? AND p.deleted_at IS NOT NULL
              ORDER BY p.deleted_at DESC');
        $stmt->execute([(int) $e['id']]);
        $points = $stmt->fetchAll();
        if ($points !== []) {
            $out[] = [
                'excursion'   => $e,
                'leader_name' => $e['leader_name'] ?? null,
                'points'      => $points,
            ];
        }
    }
    return $out;
}

/**
 * With whose id a trash action on this point runs — or null. Own points
 * never come through here (the caller tried them first): this answers
 * for EXCURSION finds, first by the leader's right, then by the
 * operator's. An admin reaches ONLY excursion points this way — ordinary
 * strangers' trashes stay theirs.
 */
function excursion_point_trash_scope(PDO $pdo, array $me, int $pointId): ?int
{
    $owner = excursion_point_leader_scope($pdo, (int) $me['id'], $pointId);
    if ($owner !== null) {
        return $owner;
    }
    if (!is_admin($me) || !excursion_guests_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT user_id, excursion_id FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $p = $stmt->fetch();
    return $p !== false && $p['excursion_id'] !== null ? (int) $p['user_id'] : null;
}

/**
 * The guest's slice of the photos API (api/v1/photos.php) — the same
 * pattern as excursion_guest_api: answers and terminates, and the list
 * of what a guest may do is exactly this.
 *
 *   GET ?point=…   the photos of one of his finds
 *   POST           a new picture onto one of them (gate: allowed, measure)
 *   DELETE ?id=…   one of his own — final, and it frees measure
 *
 * No cloud sweep after the upload: the guest account has no cloud, the
 * pictures stay in its directory and count only against the measure.
 */
function excursion_guest_photos_api(PDO $pdo, array $actor, string $method, array $input): void
{
    require_once __DIR__ . '/photos.php';
    $guestUser = excursion_guest_user_id($pdo);

    switch ($method) {
        case 'GET':
            $pointId = (int) ($_GET['point'] ?? 0);
            if (excursion_guest_point($pdo, $actor, $pointId) === null) {
                json_error(t('Punkt nicht gefunden.'), 404);
            }
            json_response(['photos' => photos_of_point($pdo, $guestUser, $pointId)]);
            // json_response terminates

        case 'POST':
            // The same silent-failure guard as the logged-in path below in
            // photos.php: past post_max_size PHP drops the whole body.
            if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                json_error(t('Das Bild war zu gross für diesen Server (Grenze: %s).',
                    (string) ini_get('post_max_size')), 413);
            }
            $pointId = (int) ($input['point_id'] ?? 0);
            if (excursion_guest_point($pdo, $actor, $pointId) === null) {
                json_error(t('Punkt nicht gefunden.'), 404);
            }
            excursion_photo_gate($pdo, $actor['excursion'], $actor['guest_id']);
            json_response(photo_create($pdo, $guestUser, $pointId,
                $_FILES['image'] ?? [], $_FILES['preview'] ?? null, $input), 201);

        case 'DELETE':
            $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT point_id FROM map_photos WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $guestUser]);
            $pointId = $stmt->fetchColumn();
            if ($pointId === false
                || excursion_guest_point($pdo, $actor, (int) $pointId) === null
                || !photo_delete($pdo, $guestUser, $id)) {
                json_error(t('Foto nicht gefunden.'), 404);
            }
            json_response(['deleted' => $id]);

        default:
            json_error(t('Methode nicht unterstützt.'), 405);
    }
}

/** All of the guest's living finds on this excursion, prepared like the
 *  ordinary points payload — the map draws them without a special path. */
function excursion_guest_points_full(PDO $pdo, array $actor): array
{
    require_once __DIR__ . '/photos.php';
    $guestUser = excursion_guest_user_id($pdo);
    $stmt = $pdo->prepare(
        'SELECT * FROM map_points
          WHERE user_id = ? AND excursion_id = ? AND excursion_guest_id = ?
            AND deleted_at IS NULL
          ORDER BY updated_at DESC');
    $stmt->execute([$guestUser, (int) $actor['excursion']['id'], $actor['guest_id']]);
    $rows = $stmt->fetchAll();
    // The photos come along since stage 6a — the map's gallery reads
    // them off the point like everywhere else.
    $photos = photos_for_points($pdo, $guestUser, array_column($rows, 'id'));
    return array_map(static fn(array $p): array =>
        point_prepare($p, [], $photos[(int) $p['id']] ?? [], []), $rows);
}

/** One guest find, prepared with its photos — the single-point answers. */
function excursion_guest_prepared(PDO $pdo, array $row): array
{
    require_once __DIR__ . '/photos.php';
    $photos = photos_for_points($pdo, excursion_guest_user_id($pdo), [(int) $row['id']]);
    return point_prepare($row, [], $photos[(int) $row['id']] ?? [], []);
}

/** A guest's own finds on this excursion — what the page shows him. */
function excursion_guest_points(PDO $pdo, int $excursionId, int $guestId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, lat, lon, title FROM map_points
          WHERE excursion_id = ? AND excursion_guest_id = ? AND deleted_at IS NULL
          ORDER BY created_at DESC'
    );
    $stmt->execute([$excursionId, $guestId]);
    return array_map(static fn(array $p): array => [
        'id' => (int) $p['id'], 'lat' => (float) $p['lat'], 'lon' => (float) $p['lon'],
        'title' => (string) $p['title'],
    ], $stmt->fetchAll());
}
