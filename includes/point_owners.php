<?php
/**
 * Co-ownership of a find (migration 0098) — the shared-record half of
 * the model of 11.09.2026: „irgendwie ist dann jeder Besitzer des
 * Punktes, der dort Arbeit reinsteckt … mit Besitzer wird nur der, der
 * Fotos anhängt. Das ist die Arbeit."
 *
 * THE HOLDER STAYS ONE (map_points.user_id); everybody else stands in
 * map_point_owners. So every path that reads „my points" is untouched,
 * and a co-owner reaches the find the way a member reaches a shared one.
 *
 * WHO MAY ATTACH A PICTURE TO A FIND THAT IS NOT HIS: a share with the
 * right `attach_photos` (until the list shares of 0160 it rode on
 * delete_photos — 08.10.2026, named the three apart: attach,
 * delete the others', store measures), the excursion's leader, the
 * participant on his own old guest find, and whoever is a co-owner
 * already.
 *
 * ATTACHING IS WHAT MAKES ONE A CO-OWNER — measuring or editing a field
 * does not (his line: „eine Messung machen oder ein Feld ändern reicht
 * nicht aus"). The hook at the bottom is called from the two places a
 * picture arrives, photo_create() and cloud.php's record; it adds the
 * row and sets the copies going.
 */

declare(strict_types=1);

require_once __DIR__ . '/container.php';
require_once __DIR__ . '/excursions.php';
require_once __DIR__ . '/cloud_copies_content.php';
require_once __DIR__ . '/data.php';

/** Migration 0098 — the code goes live before it. */
function point_owners_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_point_owners');
}

/** The circle of a find: the holder and every co-owner who is not in his
 *  trash — those who see ALL its pictures, whoever took them.
 *  @return int[] */
function point_owner_circle(PDO $pdo, int $pointId): array
{
    $stmt = $pdo->prepare('SELECT user_id FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $holder = $stmt->fetchColumn();
    if ($holder === false) {
        return [];
    }
    $circle = [(int) $holder];
    if (point_owners_ready($pdo)) {
        $stmt = $pdo->prepare('SELECT user_id FROM map_point_owners WHERE point_id = ? AND deleted_at IS NULL');
        $stmt->execute([$pointId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $u) {
            $circle[] = (int) $u;
        }
    }
    return array_values(array_unique($circle));
}

/** Holder or present co-owner? */
function point_in_circle(PDO $pdo, int $userId, int $pointId): bool
{
    return in_array($userId, point_owner_circle($pdo, $pointId), true);
}

/**
 * The finds I co-own, shaped like every other foreign point the map
 * knows (foreign_point_load): the holder's point with all its pictures,
 * his name, and — new — my own filing. 'lists' carries MY list, so the
 * sidebar tree treats the find like one of mine; empty means unfiled.
 *
 * Delivered whole in the start payload, like the excursion finds without
 * buckets: a co-owned find is rare, and one payload is one payload.
 */
function co_owned_points(PDO $pdo, int $userId): array
{
    if (!point_owners_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT o.point_id, o.list_id, p.user_id AS holder, ' . sql_display_name() . ' AS holder_name
           FROM map_point_owners o
           JOIN map_points p ON p.id = o.point_id AND p.deleted_at IS NULL
           JOIN map_users u ON u.id = p.user_id
          WHERE o.user_id = ? AND o.deleted_at IS NULL'
    );
    $stmt->execute([$userId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $point = point_load($pdo, (int) $row['holder'], (int) $row['point_id']);
        if ($point === null) {
            continue;
        }
        unset($point['field_times']);
        $point['owner']    = (string) $row['holder_name'];
        $point['owner_id'] = (int) $row['holder'];
        $point['co_owned'] = true;
        $point['lists']    = $row['list_id'] === null ? [] : [(int) $row['list_id']];
        $out[(int) $row['point_id']] = $point;
    }
    return $out;
}

/**
 * „Jeder mit seiner eigenen Ablage": a co-owner files the find in ONE of
 * his own lists, or in none. The list has to be his — filing a find into
 * somebody else's list would hang it into a share he does not hold.
 */
function point_owner_list_set(PDO $pdo, int $pointId, int $userId, ?int $listId): bool
{
    if (!point_owner_is($pdo, $pointId, $userId)) {
        return false;
    }
    if ($listId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM map_lists WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
        $stmt->execute([$listId, $userId]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }
    }
    $pdo->prepare('UPDATE map_point_owners SET list_id = ? WHERE point_id = ? AND user_id = ?')
        ->execute([$listId, $pointId, $userId]);
    return true;
}

/* ---------------------------------------------------------------------------
 * The door (step 4 of the plan, the answers of 11.09.2026): a
 * co-owner who presses the trash STEPS OUT — his row gets deleted_at, the
 * find stays with the others, and his own trash lets him back in. The
 * holder who presses it HANDS OVER to the most senior co-owner
 * („dienstältester Besitzer") and steps out the same way. Only a holder
 * without co-owners reaches the bucket, as ever.
 * ------------------------------------------------------------------------- */

/** The most senior present co-owner — the one the find goes to. */
function point_owner_senior(PDO $pdo, int $pointId): ?int
{
    if (!point_owners_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT user_id FROM map_point_owners
          WHERE point_id = ? AND deleted_at IS NULL ORDER BY since, user_id LIMIT 1'
    );
    $stmt->execute([$pointId]);
    $u = $stmt->fetchColumn();
    return $u === false ? null : (int) $u;
}

/** The names of a find's present co-owners, by seniority — for the
 *  window's wording („geht an Anna weiter") and the door instead of the
 *  bucket. @return array<int, string[]> point_id => names */
function co_owners_of_points(PDO $pdo, array $pointIds): array
{
    if ($pointIds === [] || !point_owners_ready($pdo)) {
        return [];
    }
    $space = implode(',', array_fill(0, count($pointIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT o.point_id, ' . sql_display_name() . ' AS name
           FROM map_point_owners o JOIN map_users u ON u.id = o.user_id
          WHERE o.point_id IN (' . $space . ') AND o.deleted_at IS NULL
          ORDER BY o.since, o.user_id'
    );
    $stmt->execute($pointIds);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['point_id']][] = (string) $row['name'];
    }
    return $out;
}

/** A co-owner steps out: into his trash, reversible. */
function point_owner_leave(PDO $pdo, int $pointId, int $userId): bool
{
    if (!point_owners_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'UPDATE map_point_owners SET deleted_at = NOW() WHERE point_id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$pointId, $userId]);
    $left = $stmt->rowCount() > 0;
    // Out of an Aufsammlung: his own species from it go with him (collections.php).
    if ($left && function_exists('collection_species_leave')) {
        collection_species_leave($pdo, $pointId, $userId);
    }
    return $left;
}

/** Back from the trash: a co-owner again (the find must still stand). */
function point_owner_restore(PDO $pdo, int $pointId, int $userId): bool
{
    if (!point_owners_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'UPDATE map_point_owners o JOIN map_points p ON p.id = o.point_id AND p.deleted_at IS NULL
            SET o.deleted_at = NULL
          WHERE o.point_id = ? AND o.user_id = ? AND o.deleted_at IS NOT NULL'
    );
    $stmt->execute([$pointId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * For good, from his trash: the row goes, and so do HIS copies of the
 * others' pictures — out of his cloud, by the worker. His own pictures
 * stay attached to the find: they are his, the others hold copies, and
 * a find that survives its holder's leaving survives a co-owner's too.
 */
function point_owner_forget(PDO $pdo, int $pointId, int $userId): bool
{
    if (!point_owners_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'DELETE FROM map_point_owners WHERE point_id = ? AND user_id = ? AND deleted_at IS NOT NULL'
    );
    $stmt->execute([$pointId, $userId]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    cloud_copies_release($pdo, $userId, $pointId);
    return true;
}

/**
 * THE HANDOVER: the holder steps out, the most senior co-owner becomes
 * holder. The point row changes hands; the new holder's own filing
 * (his list) becomes the point's list; the old holder gets a co-owner
 * row that starts in his trash — from there he can come back as a
 * co-owner, never again as the holder unless it is handed to him.
 * Pictures, cloud rows and copies are untouched: they belong to their
 * photographers, wherever the find lives.
 */
function point_hand_over(PDO $pdo, int $pointId, int $fromHolder, int $toUser): bool
{
    $stmt = $pdo->prepare(
        'SELECT list_id FROM map_point_owners WHERE point_id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$pointId, $toUser]);
    $row = $stmt->fetch();
    if ($row === false) {
        return false;
    }
    $newList = $row['list_id'] === null ? null : (int) $row['list_id'];

    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ? ORDER BY list_id LIMIT 1');
    $stmt->execute([$pointId]);
    $oldList = $stmt->fetchColumn();
    $oldList = $oldList === false ? null : (int) $oldList;

    $stmt = $pdo->prepare(
        'UPDATE map_points SET user_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$toUser, $pointId, $fromHolder]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    $pdo->prepare('DELETE FROM map_point_owners WHERE point_id = ? AND user_id = ?')->execute([$pointId, $toUser]);
    $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ?')->execute([$pointId]);
    if ($newList !== null) {
        $pdo->prepare('INSERT INTO map_point_lists (point_id, list_id) VALUES (?, ?)')->execute([$pointId, $newList]);
    }
    $pdo->prepare(
        'INSERT INTO map_point_owners (point_id, user_id, list_id, deleted_at) VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE deleted_at = NOW(), list_id = VALUES(list_id)'
    )->execute([$pointId, $fromHolder, $oldList]);
    /* THE OLD HOLDER'S HINTS GO WITH HIM (migration 0149; found by the other
       session's review, 02.10.2026, the maintainer's „Okay"). Showing the find
       to a friend was his gesture, and he has stepped out: left standing,
       the friend kept seeing a find that was no longer his to show, and
       his tab listed it with ticks that failed. Not handed to the new
       holder either — he never chose to show it, perhaps to somebody he
       does not know. point_hints_reaching_sql() asks for the holder as
       well, so no other road can leave such a hint standing. */
    require_once __DIR__ . '/point_hints.php';
    if (point_hints_ready($pdo)) {
        $pdo->prepare('DELETE FROM map_point_hints WHERE point_id = ? AND user_id = ?')
            ->execute([$pointId, $fromHolder]);
    }
    // The new holder's bucket learns of its new member at once.
    require_once __DIR__ . '/photos.php';
    photo_point_touched($pdo, $pointId);
    return true;
}

/* ---------------------------------------------------------------------------
 * THE SPLIT — a friendship ends, a shared find becomes two
 * (08.10.2026: „aufteilen wäre hier natürlich der sicherere Fall, seiner
 * Arbeit auch wirklich zu behalten … angenommen, man geht im Streit
 * auseinander, könnte die Gegenseite trotzdem den Fund oder die eigenen
 * Bilder rausnehmen oder etwas Falsches reinschreiben"). A shared record
 * would keep the two tied after the friendship: each would go on seeing
 * what the other changes or removes. So the co-owner gets a full copy of
 * the working state — texts, coordinates, species, sequences — as a find of
 * his own, and from then on the two run apart. Duplicates are no harm in
 * mapping: on an excursion the same find on the same day by several people
 * is the rule.
 *
 * PICTURES GO WITH THEIR PHOTOGRAPHER, and each side keeps the other's
 * where it already holds a copy in its own cloud: that copy becomes a
 * picture of its own (a map_cloud_files row of the recipient, with the
 * origin's title, scale and measures). NO CLOUD, NO COPY, as everywhere:
 * whoever has none keeps only his own pictures — the server never holds a
 * copy. Whoever else is still in the find's circle keeps it too: the copy
 * he holds of a picture that leaves is turned into the find's own row,
 * and the others' copies point at that one, so the find keeps ONE record
 * per picture.
 *
 * ONLY BETWEEN HOLDER AND CO-OWNER. Two co-owners of a third person's
 * find stay tied through him; ending their friendship does not cut that.
 * ------------------------------------------------------------------------- */

/**
 * Split every find shared between these two — called by friendship_remove().
 *
 * @return int how many finds were split
 */
function point_owners_split(PDO $pdo, int $a, int $b): int
{
    if (!point_owners_ready($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'SELECT o.point_id, o.user_id AS co, o.list_id, o.deleted_at, p.user_id AS holder
           FROM map_point_owners o
           JOIN map_points p ON p.id = o.point_id AND p.deleted_at IS NULL
          WHERE (p.user_id = ? AND o.user_id = ?) OR (p.user_id = ? AND o.user_id = ?)'
    );
    $stmt->execute([$a, $b, $b, $a]);
    $n = 0;
    foreach ($stmt->fetchAll() as $row) {
        $pointId = (int) $row['point_id'];
        $co      = (int) $row['co'];
        // Stepped out already: no copy wanted — the door back closes, and
        // his copies leave his cloud like a „Endgültig" from the trash.
        if ($row['deleted_at'] !== null) {
            $pdo->prepare('DELETE FROM map_point_owners WHERE point_id = ? AND user_id = ?')->execute([$pointId, $co]);
            cloud_copies_release($pdo, $co, $pointId);
            continue;
        }
        point_owner_split_one($pdo, $pointId, (int) $row['holder'], $co,
            $row['list_id'] === null ? null : (int) $row['list_id']);
        $n++;
    }
    return $n;
}

/** One find: the co-owner leaves with a copy of his own. @return int the copy's id */
function point_owner_split_one(PDO $pdo, int $pointId, int $holder, int $co, ?int $coList): int
{
    // THE RECORD — every column, but as HIS find: no device id, no find
    // number (that belongs to the holder's series), no tour, excursion or
    // import of the holder's, no rescue anchor of somebody else's wording.
    $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $point = $stmt->fetch();
    unset($point['id']);
    $point['user_id'] = $co;
    foreach (['client_id', 'find_number', 'tour', 'tour_id', 'excursion_id', 'excursion_guest_id', 'import_id', 'original'] as $col) {
        if (array_key_exists($col, $point)) {
            $point[$col] = null;
        }
    }
    $point['updated_at'] = date('Y-m-d H:i:s');
    $cols = array_keys($point);
    $pdo->prepare('INSERT INTO map_points (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
        ->execute(array_values($point));
    $copy = (int) $pdo->lastInsertId();

    if ($coList !== null) {
        $pdo->prepare('INSERT IGNORE INTO map_point_lists (point_id, list_id) VALUES (?, ?)')->execute([$copy, $coList]);
    }
    // What describes the record goes along as a copy: the species links
    // and the sequences (text only). His own attachments, plates and
    // shares of the find move with him.
    $copyRows = static function (string $table, array $set) use ($pdo, $pointId): void {
        if (!table_exists($pdo, $table)) {
            return;
        }
        $q = $pdo->prepare("SELECT * FROM $table WHERE point_id = ?");
        $q->execute([$pointId]);
        foreach ($q->fetchAll() as $r) {
            unset($r['id']);
            $r = array_merge($r, $set);
            $c = array_keys($r);
            $pdo->prepare("INSERT INTO $table (" . implode(', ', $c) . ') VALUES (' . implode(', ', array_fill(0, count($c), '?')) . ')')
                ->execute(array_values($r));
        }
    };
    $copyRows('map_point_species', ['point_id' => $copy, 'user_id' => $co]);
    $copyRows('map_point_sequences', ['point_id' => $copy, 'user_id' => $co]);
    foreach (['map_point_files', 'map_plates', 'map_measure_air', 'map_point_hints'] as $table) {
        if (table_exists($pdo, $table)) {
            $pdo->prepare("UPDATE $table SET point_id = ? WHERE point_id = ? AND user_id = ?")->execute([$copy, $pointId, $co]);
        }
    }

    // THE PICTURES — each kind, each row.
    $stay = array_values(array_diff(point_owner_circle($pdo, $pointId), [$co]));   // the holder first
    foreach (['photo' => 'map_photos', 'cloud' => 'map_cloud_files'] as $kind => $table) {
        $q = $pdo->prepare("SELECT * FROM $table WHERE point_id = ?");
        $q->execute([$pointId]);
        foreach ($q->fetchAll() as $pic) {
            $picId = (int) $pic['id'];
            if ((int) $pic['user_id'] === $co) {
                // His picture goes with him; whoever stays keeps his copy.
                $pdo->prepare("UPDATE $table SET point_id = ? WHERE id = ?")->execute([$copy, $picId]);
                point_owner_copies_settle($pdo, $kind, $picId, $pic, $pointId, $stay);
            } else {
                // Somebody else's picture stays; HE keeps it only as his copy.
                point_owner_copy_adopt($pdo, $kind, $picId, $pic, $co, $copy);
            }
        }
    }

    $pdo->prepare('DELETE FROM map_point_owners WHERE point_id = ? AND user_id = ?')->execute([$pointId, $co]);
    require_once __DIR__ . '/photos.php';
    require_once __DIR__ . '/cloud_names.php';
    foreach ([$pointId, $copy] as $id) {
        photo_point_touched($pdo, $id);
        cloud_names_touch($pdo, $id);
    }
    return $copy;
}

/**
 * A copy becomes the recipient's own picture on the given find — a cloud
 * row of his, the bytes where the copy already lies, the words (title,
 * scale, measures) taken from the picture it copied. The copy row goes:
 * the worker must not retire a file that is somebody's picture now.
 *
 * @return ?int the new row, null when he held no finished copy
 */
function point_owner_copy_adopt(PDO $pdo, string $kind, int $originId, array $origin, int $userId, int $pointId): ?int
{
    if (!cloud_copies_ready($pdo)) {
        return null;
    }
    $q = $pdo->prepare('SELECT * FROM map_cloud_copies WHERE origin_kind = ? AND origin_id = ? AND user_id = ?');
    $q->execute([$kind, $originId, $userId]);
    $c = $q->fetch();
    if ($c === false) {
        return null;
    }
    $pdo->prepare('DELETE FROM map_cloud_copies WHERE id = ?')->execute([(int) $c['id']]);
    if ($c['status'] !== 'copied' || (string) $c['fileid'] === '') {
        return null;
    }
    /* THE PHOTOGRAPHER'S NAME STAYS ON THE PICTURE (migration 0161): the
       row is the keeper's now, the picture is still the photographer's
       work. The name as it stood — an account deleted later must not take
       it with it. */
    $takenBy = $origin['taken_by'] ?? null;
    if ($takenBy === null || $takenBy === '') {
        require_once __DIR__ . '/joint_lists.php';
        $takenBy = joint_user_name($pdo, (int) $origin['user_id']);
    }
    $withTakenBy = column_exists($pdo, 'map_cloud_files', 'taken_by');
    $pdo->prepare(
        'INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, folderid, name, original_name, path, size,
                                      width, height, title, sort_order, scale, measures, uploaded_at'
        . ($withTakenBy ? ', taken_by' : '') . ')
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()' . ($withTakenBy ? ', ?' : '') . ')'
    )->execute(array_merge([
        $userId, $pointId, $c['provider'], $c['fileid'], $c['folderid'],
        (string) ($c['name'] ?? $origin['cloud_name'] ?? $origin['name'] ?? ''),
        $origin['original_name'] ?? null, (string) ($c['path'] ?? ''), $c['size'],
        $origin['width'] ?? null, $origin['height'] ?? null, $origin['title'] ?? null,
        (int) ($origin['sort_order'] ?? 0), $origin['scale'] ?? null, $origin['measures'] ?? null,
    ], $withTakenBy ? [mb_substr($takenBy, 0, 120)] : []));
    return (int) $pdo->lastInsertId();
}

/**
 * A picture leaves the find with its photographer. Of those who stay, the
 * first with a finished copy (the holder first) keeps it as the find's own
 * picture; the others' copies now copy THAT one — one record per picture,
 * as the model wants. Copies nobody finished go.
 */
function point_owner_copies_settle(PDO $pdo, string $kind, int $originId, array $origin, int $pointId, array $stay): void
{
    if (!cloud_copies_ready($pdo)) {
        return;
    }
    $newOrigin = null;
    foreach ($stay as $userId) {
        $newOrigin = point_owner_copy_adopt($pdo, $kind, $originId, $origin, (int) $userId, $pointId);
        if ($newOrigin !== null) {
            break;
        }
    }
    if ($newOrigin !== null) {
        $pdo->prepare("UPDATE map_cloud_copies SET origin_kind = 'cloud', origin_id = ? WHERE origin_kind = ? AND origin_id = ?")
            ->execute([$newOrigin, $kind, $originId]);
    } else {
        $pdo->prepare('DELETE FROM map_cloud_copies WHERE origin_kind = ? AND origin_id = ? AND user_id <> ?')
            ->execute([$kind, $originId, (int) $origin['user_id']]);
    }
}

/** What lies in MY trash as a former co-owner — for Verwaltung → Papierkorb. */
function point_owner_trash_entries(PDO $pdo, int $userId): array
{
    if (!point_owners_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT o.point_id AS id, o.deleted_at, p.title, p.lat, p.lon, p.deleted_at AS point_gone,
                ' . sql_display_name() . ' AS holder
           FROM map_point_owners o
           JOIN map_points p ON p.id = o.point_id
           JOIN map_users u ON u.id = p.user_id
          WHERE o.user_id = ? AND o.deleted_at IS NOT NULL
          ORDER BY o.deleted_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Is this user a co-owner of the point (and not in his trash there)? */
function point_owner_is(PDO $pdo, int $pointId, int $userId): bool
{
    if (!point_owners_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM map_point_owners WHERE point_id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$pointId, $userId]);
    return $stmt->fetchColumn() !== false;
}

/**
 * In whose name a picture is touched: its photographer's own, or — for a
 * picture of somebody else — the photographer's, when a share grants the
 * right (`delete_photos` to remove it, `measure` to store scale, measures
 * and name). Null when neither.
 *
 * PICTURES ARE PICTURES (08.10.2026: „ich gebe nicht
 * unterschiedliche Rechte auf Belegbilder und auf Cloud-Bilder"). The
 * voucher photos had these doors since the shares; the cloud recordings
 * — the larger half of a find's pictures — had only the photographer's.
 * This is the one answer both ask.
 */
function picture_actor(PDO $pdo, int $userId, int $pointId, int $photographer, string $right): ?int
{
    if ($photographer === $userId) {
        return $userId;
    }
    $foreign = point_foreign_rights($pdo, $userId, $pointId);
    return $foreign !== null && in_array($right, $foreign['rights'], true) ? $photographer : null;
}

/**
 * May $userId attach a picture to this point? Returns the HOLDER's id —
 * the id to write the point with (touching its bucket, filing the copy
 * under his find) — or null, which is also what „no such point" says:
 * indistinguishable on purpose, like every other scope in this project.
 */
function point_attach_scope(PDO $pdo, int $userId, int $pointId): ?int
{
    $stmt = $pdo->prepare('SELECT user_id FROM map_points WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$pointId]);
    $holder = $stmt->fetchColumn();
    if ($holder === false) {
        return null;
    }
    $holder = (int) $holder;
    if ($holder === $userId) {
        return $holder;
    }
    if (point_owner_is($pdo, $pointId, $userId)) {
        return $holder;
    }
    // `attach_photos` since the list shares (0160); an old box's „Belegbilder
    // löschen" carries it along (rights_compat in container.php).
    $foreign = point_foreign_rights($pdo, $userId, $pointId);
    if ($foreign !== null && in_array('attach_photos', $foreign['rights'], true)) {
        return $holder;
    }
    return excursion_point_leader_scope($pdo, $userId, $pointId)
        ?? excursion_point_participant_scope($pdo, $userId, $pointId);
}

/**
 * May $userId LOOK at this point's pictures? Returns the HOLDER's id —
 * whose picture set the viewer gets, the same set the point payload
 * carries — or null for „not yours to see", which again reads exactly
 * like „no such point".
 *
 * THE SEEING GATE BESIDE THE ATTACHING ONE, and it is wider: every door
 * of point_attach_scope() plus the plain share — whoever may see a find
 * may see what it looks like (23.08.2026: „mit den Punkten
 * gibt man seine Fotos auch frei", in the whole interface). Until
 * 14.09.2026 that sentence lived in pages/photo.php alone, spelled out
 * as three checks; the fresh photo fetch the co-ownership step of 11.09
 * added to the gallery (api/v1/photos.php) knew none of them and
 * answered a share member with HIS pictures on the find — none — and
 * the gallery took the empty list for the truth. A friend saw an empty
 * gallery on every shared find and reported „die Fotos in der Cloud
 * fehlen", because all of them happened to be there. One gate now, and
 * every place that hands out pictures asks it: photo.php, photos.php,
 * cloud.php's listing, cloud_thumb.php.
 */
function point_view_scope(PDO $pdo, int $userId, int $pointId): ?int
{
    $stmt = $pdo->prepare('SELECT user_id FROM map_points WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$pointId]);
    $holder = $stmt->fetchColumn();
    if ($holder === false) {
        return null;
    }
    $holder = (int) $holder;
    if ($holder === $userId || point_owner_is($pdo, $pointId, $userId)) {
        return $holder;
    }
    if (point_foreign_rights($pdo, $userId, $pointId) !== null) {
        return $holder;
    }
    /* A FIND SHOWN TO ME (migration 0149) is a find I may see — and so
       are its pictures, the same rule as for a share. The hint hands out
       no other right; point_attach_scope() does not know it. */
    require_once __DIR__ . '/point_hints.php';
    if (point_hint_reaches($pdo, $userId, $pointId)) {
        return $holder;
    }
    return excursion_point_leader_scope($pdo, $userId, $pointId)
        ?? excursion_point_participant_scope($pdo, $userId, $pointId);
}

/**
 * Make $userId a co-owner — or take him out of his trash again if he had
 * left (the row survives leaving; deleted_at is what leaving sets).
 * list_id stays what it was or null: „jeder mit seiner eigenen Ablage"
 * is step 3's business, the client will offer the list there.
 *
 * @return bool true when he was not a (present) co-owner before
 */
function point_owner_add(PDO $pdo, int $pointId, int $userId, ?int $listId = null): bool
{
    if (!point_owners_ready($pdo)) {
        return false;
    }
    /* A CO-OWNER OF A SPECIES FROM AN AUFSAMMLUNG IS A CO-OWNER OF THE
       AUFSAMMLUNG TOO (15.10.2026: Ben hangs his micro picture
       at the maintainer's species instead of making a find of his own). Without
       it, the maintainer deleting the Aufsammlung would hand the species to Ben
       but trash the Aufsammlung it hangs at. The holder of the Aufsammlung
       is no co-owner of his own. */
    if (function_exists('collections_ready') && collections_ready($pdo)) {
        $stmt = $pdo->prepare('SELECT c.id, c.user_id FROM map_points p JOIN map_points c ON c.id = p.collection_id WHERE p.id = ?');
        $stmt->execute([$pointId]);
        $parent = $stmt->fetch();
        if ($parent !== false && (int) $parent['user_id'] !== $userId) {
            point_owner_add($pdo, (int) $parent['id'], $userId, $listId);
        }
    }
    $stmt = $pdo->prepare('SELECT deleted_at FROM map_point_owners WHERE point_id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
        $pdo->prepare('INSERT INTO map_point_owners (point_id, user_id, list_id) VALUES (?, ?, ?)')
            ->execute([$pointId, $userId, $listId]);
        return true;
    }
    if ($row['deleted_at'] !== null) {
        $pdo->prepare('UPDATE map_point_owners SET deleted_at = NULL WHERE point_id = ? AND user_id = ?')
            ->execute([$pointId, $userId]);
        return true;
    }
    return false;
}

/**
 * THE HOOK: a picture arrived on a find. Called after the row exists —
 * from photo_create() with kind 'photo', from cloud.php's record with
 * kind 'cloud'. Three things happen, in this order:
 *
 *   1. a photographer who is not the holder becomes a co-owner (or
 *      returns from his trash);
 *   2. the new picture is queued for every other recipient with a cloud;
 *   3. a NEW co-owner gets every picture that was already there queued
 *      for himself — the complete set, from the first moment.
 *
 * Everything here is bookkeeping; the bytes move later, in the copy
 * worker. Nothing in here may throw past the caller: a picture that is
 * saved must never be reported as lost because the ledger hiccupped.
 */
function point_photo_attached(PDO $pdo, int $pointId, int $photographerId, string $kind, int $originId): void
{
    if (!point_owners_ready($pdo) || !cloud_copies_ready($pdo)) {
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT user_id FROM map_points WHERE id = ?');
        $stmt->execute([$pointId]);
        $holder = $stmt->fetchColumn();
        if ($holder === false) {
            return;
        }
        $joined = (int) $holder !== $photographerId && point_owner_add($pdo, $pointId, $photographerId);
        if ($kind === 'cloud') {
            cloud_copies_enqueue_file($pdo, $originId);
        } else {
            cloud_copies_enqueue_photo($pdo, $originId);
        }
        if ($joined) {
            cloud_copies_enqueue_point($pdo, $pointId, $photographerId);
        }
    } catch (Throwable $e) {
        error_log('point_photo_attached (' . $pointId . '/' . $kind . ' ' . $originId . '): ' . $e->getMessage());
    }
}
