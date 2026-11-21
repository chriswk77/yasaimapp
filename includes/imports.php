<?php
/**
 * Imports as a manageable unit — the bracket around one import run.
 *
 * The model is laid down in migration 0045: one row per import, a dedicated
 * list "Import <date>", the marker on every newly created point. Here are
 * the four operations: finding a name, creating, listing, taking back.
 * What reads and applies the source formats lives with the sources
 * (takeout.php, meinepilze.php).
 */

declare(strict_types=1);

function imports_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_imports')
        && column_exists($pdo, 'map_points', 'import_id');
}

/**
 * The name of the next import: "Import 12.08.2026", for the second on the
 * same day "Import 12.08.2026 (2)". Counted are the day's import rows — if
 * one is taken back, its number is free again, along with its lists, so
 * nothing collides.
 */
function import_name(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM map_imports WHERE user_id = ? AND DATE(created_at) = CURDATE()'
    );
    $stmt->execute([$userId]);
    $today = (int) $stmt->fetchColumn();

    return 'Import ' . date('d.m.Y') . ($today > 0 ? ' (' . ($today + 1) . ')' : '');
}

/**
 * Open an import: row and root list — or, for a source that brings SEVERAL
 * lists (Takeout, a KML with folders), a MAPPE of the import's name that
 * import_list() lays them into (migration 0168: lists no longer hang under
 * lists). Without 0168 the old way: a root list with the leaves below it.
 *
 * A desired name replaces the date scheme entirely — it is the memory aid,
 * the date stands next to it in the overview anyway.
 *
 * @return array{id: int, name: string, list_id: ?int, folder_id: ?int}
 */
function import_create(PDO $pdo, int $userId, string $source, bool $rootIsSpecies = false,
                        ?string $desiredName = null, bool $several = false): array
{
    $desiredName = mb_substr(trim((string) $desiredName), 0, 100);
    $name = $desiredName !== '' ? $desiredName : import_name($pdo, $userId);

    if ($several && folders_exist($pdo) && column_exists($pdo, 'map_imports', 'folder_id')) {
        $folderId = folder_create($pdo, $userId, ['name' => $name]);
        $pdo->prepare('INSERT INTO map_imports (user_id, source, name, folder_id) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $source, $name, $folderId]);
        return ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'list_id' => null, 'folder_id' => $folderId];
    }

    $listId = list_create($pdo, $userId, [
        'name'      => $name,
        'color'     => '#8a6b3a',
        /* 'flag', not 'fahne': since the schema move LIST_SYMBOLS speaks
           English, and list_symbol() silently turns an unknown value into
           'circle' — every import root lost its flag without a word. */
        'symbol'    => 'flag',
        'is_species' => $rootIsSpecies,
    ]);

    $pdo->prepare(
        'INSERT INTO map_imports (user_id, source, name, list_id) VALUES (?, ?, ?, ?)'
    )->execute([$userId, $source, $name, $listId]);

    return ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'list_id' => $listId, 'folder_id' => null];
}

/**
 * One of the several lists of an import: into its Mappe, or — before
 * migration 0168 — below its root list.
 */
function import_list(PDO $pdo, int $userId, array $import, string $name, string $color): int
{
    return list_create($pdo, $userId, ['name' => $name, 'color' => $color]
        + ($import['folder_id'] !== null ? ['folder_id' => $import['folder_id']] : ['parent_id' => $import['list_id']]));
}

/**
 * Rename an import — the memory aid may also come later.
 * Row and root list together, otherwise the same thing would have two
 * names; the list via list_update, so the rename syncs to all devices.
 */
function import_rename(PDO $pdo, int $userId, int $importId, string $name): void
{
    $name = mb_substr(trim($name), 0, 100);
    if ($name === '') {
        throw new InvalidArgumentException('Der Import braucht einen Namen.');
    }

    $row = import_row($pdo, $userId, $importId);

    $pdo->prepare('UPDATE map_imports SET name = ? WHERE id = ?')->execute([$name, $importId]);
    if ($row['list_id'] !== null) {
        list_update($pdo, $userId, (int) $row['list_id'], ['name' => $name]);
    }
    if ($row['folder_id'] !== null) {
        folder_update($pdo, $userId, (int) $row['folder_id'], ['name' => $name]);
    }
}

/** An import's row with its root list or Mappe — or an exception. */
function import_row(PDO $pdo, int $userId, int $importId): array
{
    $withFolder = column_exists($pdo, 'map_imports', 'folder_id');
    $stmt = $pdo->prepare('SELECT list_id' . ($withFolder ? ', folder_id' : ', NULL AS folder_id')
        . ' FROM map_imports WHERE id = ? AND user_id = ?');
    $stmt->execute([$importId, $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
        throw new InvalidArgumentException('Diesen Import gibt es nicht (mehr).');
    }
    return $row;
}

/** Write the counts to the row after applying — for the overview. */
function import_finish(PDO $pdo, int $importId, int $created, int $assigned): void
{
    $pdo->prepare('UPDATE map_imports SET points_new = ?, points_assigned = ? WHERE id = ?')
        ->execute([$created, $assigned, $importId]);
}

/**
 * Where the original file of an import USED TO lie.
 *
 * NOTHING IS ARCHIVED ANY MORE (the decision, 25.08.2026). The
 * original was kept so that columns the import does not map could be
 * backfilled later without a re-import — a possibility that stood there
 * a year and was never taken up, while a Google Takeout may weigh fifty
 * megabytes. An import is finished when it is applied; whatever has to
 * be backfilled is uploaded again.
 *
 * THE PATH STAYS, and only for the cleaning up: files from before this
 * decision still lie on the server, and they should still go when their
 * import is taken back (import_remove). Whoever wants them gone sooner
 * empties data/import/archiv by hand — nothing reads it.
 */
function import_archive_path(int $importId): string
{
    return PROJECT_PATH . '/data/import/archiv/' . $importId . '.dat';
}

/** All of a user's imports, newest first — for the administration. */
function imports_list(PDO $pdo, int $userId): array
{
    if (!imports_ready($pdo)) {
        return [];
    }
    // The file name only once its column exists (migration 0080) — the
    // code is live before it, and a SELECT naming a missing column would
    // take the whole tab down.
    $withName = column_exists($pdo, 'map_imports', 'file_name');
    $stmt = $pdo->prepare(
        'SELECT id, source, name, list_id, points_new, points_assigned, created_at'
        . ($withName ? ', file_name' : '') . '
           FROM map_imports WHERE user_id = ? ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Take an import back out of the system.
 *
 * THE POINTS GO INTO THE TRASH, not into nothingness: a point may have been
 * edited since the import, carry photos, sit in further lists — final
 * deletion is the trash's decision, as everywhere. Points that existed
 * before the import and were merely assigned carry no import marker and
 * stay untouched; their assignment falls with the lists.
 *
 * THE LISTS: first the sublists, then the root — list_delete
 * deliberately lets sublists MOVE UP (whoever deletes "Meine Funde" almost
 * never wants to lose everything below it); here we want exactly the
 * opposite, the whole branch gone, so bottom to top.
 *
 * @return int how many points went into the trash
 */
function import_remove(PDO $pdo, int $userId, int $importId): int
{
    $row = import_row($pdo, $userId, $importId);

    // Via point_delete, not via a bulk UPDATE: this way every point gets
    // its stand bump and disappears from all other devices at the next
    // sync as well.
    $stmt = $pdo->prepare(
        'SELECT id FROM map_points WHERE user_id = ? AND import_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$userId, $importId]);
    $trashed = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $pointId) {
        if (point_delete($pdo, $userId, (int) $pointId)) {
            $trashed++;
        }
    }

    $root = $row['list_id'] === null ? null : (int) $row['list_id'];
    // AN IMPORT WITH A MAPPE (0168): its lists go, then the Mappe. Lists
    // moved into it by hand go along — exactly as sublists hung under an
    // import's root always did.
    if ($row['folder_id'] !== null) {
        $stmt = $pdo->prepare('SELECT id FROM map_lists WHERE user_id = ? AND folder_id = ? AND deleted_at IS NULL');
        $stmt->execute([$userId, (int) $row['folder_id']]);
        $lists = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($lists !== []) {
            $space = implode(',', array_fill(0, count($lists), '?'));
            // The species lock would be a locked door in front of one's own
            // cleanup, as below.
            $pdo->prepare("UPDATE map_lists SET is_species = 0 WHERE user_id = ? AND id IN ($space)")
                ->execute([$userId, ...$lists]);
            foreach ($lists as $listId) {
                list_delete($pdo, $userId, $listId);
            }
        }
        folder_delete($pdo, $userId, (int) $row['folder_id']);
    }
    if ($root !== null) {
        $stmt = $pdo->prepare(
            'SELECT id FROM map_lists WHERE user_id = ? AND parent_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$userId, $root]);
        $children = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        // list_delete() refuses for species lists that still carry
        // finds — and exactly that can be the case here despite the trash:
        // ASSIGNED duplicates (points that existed before the import) carry
        // no import marker, live on and still hang in the import list. But
        // the confirmation, point count included, has been answered — the
        // guard would here be a locked door in front of one's own cleanup.
        // So still detach the mark first.
        $all  = [...$children, $root];
        $space = implode(',', array_fill(0, count($all), '?'));
        $pdo->prepare("UPDATE map_lists SET is_species = 0 WHERE user_id = ? AND id IN ($space)")
            ->execute([$userId, ...$all]);

        foreach ($children as $childId) {
            list_delete($pdo, $userId, $childId);
        }
        list_delete($pdo, $userId, $root);
    }

    $pdo->prepare('DELETE FROM map_imports WHERE id = ?')->execute([$importId]);
    // The kept original goes with the import — what it belonged to no
    // longer exists, and the file still lies on the computer of whoever
    // uploaded it.
    @unlink(import_archive_path($importId));

    return $trashed;
}
