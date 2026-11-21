<?php
/**
 * Mappen (migration 0168, 11.10.2026): a name that gathers lists
 * in the sidebar, and nothing else. No settings, no points, no share, no
 * Mappe inside a Mappe — „Die Mappe hat überhaupt keine Funktion, außer
 * optisch zusammengefasst". Every question a list can be asked (species
 * list, fields, who sees it) is answered by the list itself.
 *
 * ONE SEQUENCE AT THE TOP. A Mappe and a list outside any Mappe stand side
 * by side in the sidebar, so their sort_order counts in one row; the lists
 * inside a Mappe count among themselves.
 *
 * Every function here survives the old schema: before 0168 there simply
 * are no Mappen, and folder_id is never written.
 */

declare(strict_types=1);

function folders_exist(PDO $pdo): bool
{
    static $known = null;
    return $known ??= table_exists($pdo, 'map_list_folders') && column_exists($pdo, 'map_lists', 'folder_id');
}

/** @return array<int, array{id:int, name:string, sort_order:int}> */
function folders_load(PDO $pdo, int $userId): array
{
    if (!folders_exist($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id, client_id, name, sort_order FROM map_list_folders WHERE user_id = ? ORDER BY sort_order, name');
    $stmt->execute([$userId]);
    return array_map(static fn(array $f): array => [
        'id'         => (int) $f['id'],
        'client_id'  => $f['client_id'],
        'name'       => (string) $f['name'],
        'sort_order' => (int) $f['sort_order'],
    ], $stmt->fetchAll());
}

/** A Mappe of this user, or an exception — nobody lays a list into somebody else's. */
function folder_check(PDO $pdo, int $userId, mixed $folderId): ?int
{
    if ($folderId === null || $folderId === '' || (int) $folderId === 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM map_list_folders WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $folderId, $userId]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        throw new InvalidArgumentException(t('Diese Mappe gibt es nicht.'));
    }
    return (int) $id;
}

/**
 * The next place at the end of a level: inside a Mappe among its lists,
 * outside it among the Mappen and the loose lists together. A new thing
 * goes to the END — at 0 it would jump before everything that was
 * numbered by the conversion.
 */
function folders_next_sort(PDO $pdo, int $userId, ?int $folderId): int
{
    if ($folderId !== null) {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM map_lists WHERE user_id = ? AND folder_id = ? AND deleted_at IS NULL');
        $stmt->execute([$userId, $folderId]);
        return (int) $stmt->fetchColumn() + 10;
    }
    $stmt = $pdo->prepare(
        'SELECT GREATEST(
                (SELECT COALESCE(MAX(sort_order), 0) FROM map_lists
                  WHERE user_id = ? AND folder_id IS NULL AND parent_id IS NULL AND deleted_at IS NULL),
                (SELECT COALESCE(MAX(sort_order), 0) FROM map_list_folders WHERE user_id = ?))'
    );
    $stmt->execute([$userId, $userId]);
    return min(32000, (int) $stmt->fetchColumn() + 10);
}

function folder_create(PDO $pdo, int $userId, array $data): int
{
    $clientId = point_client_key($data['client_id'] ?? null);
    if ($clientId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM map_list_folders WHERE user_id = ? AND client_id = ?');
        $stmt->execute([$userId, $clientId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
    }
    $pdo->prepare('INSERT INTO map_list_folders (user_id, client_id, name, sort_order, created_at, updated_at)
                   VALUES (?, ?, ?, ?, NOW(), NOW())')
        ->execute([$userId, $clientId, list_name((string) ($data['name'] ?? '')), folders_next_sort($pdo, $userId, null)]);
    return (int) $pdo->lastInsertId();
}

function folder_update(PDO $pdo, int $userId, int $folderId, array $data): bool
{
    $sets = [];
    $values = [];
    if (array_key_exists('name', $data)) {
        $sets[] = 'name = ?';
        $values[] = list_name((string) $data['name']);
    }
    if (array_key_exists('sort_order', $data)) {
        $sets[] = 'sort_order = ?';
        $values[] = (int) $data['sort_order'];
    }
    if ($sets === []) {
        return false;
    }
    $values[] = $folderId;
    $values[] = $userId;
    $stmt = $pdo->prepare('UPDATE map_list_folders SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute($values);
    return $stmt->rowCount() > 0;
}

/**
 * Deleting a Mappe deletes nothing else: its lists step out and stand where
 * the Mappe stood, in their own order. The foreign key would free them too
 * (ON DELETE SET NULL), but they would land wherever their inner numbers
 * put them at the top — so the whole top row is numbered anew, with the
 * Mappe's lists in its place.
 */
function folder_delete(PDO $pdo, int $userId, int $folderId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM map_list_folders WHERE id = ? AND user_id = ?');
    $stmt->execute([$folderId, $userId]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }
    $top = $pdo->prepare(
        "SELECT 'folder' AS kind, id, sort_order, name FROM map_list_folders WHERE user_id = ?
         UNION ALL
         SELECT 'list', id, sort_order, name FROM map_lists
          WHERE user_id = ? AND folder_id IS NULL AND parent_id IS NULL AND deleted_at IS NULL"
    );
    $top->execute([$userId, $userId]);
    $rows = $top->fetchAll();
    usort($rows, static fn(array $a, array $b): int =>
        ((int) $a['sort_order'] <=> (int) $b['sort_order']) ?: strcasecmp((string) $a['name'], (string) $b['name']));
    $inner = $pdo->prepare('SELECT id FROM map_lists WHERE folder_id = ? AND user_id = ? ORDER BY sort_order, name');
    $inner->execute([$folderId, $userId]);
    $freed = $inner->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE map_lists SET folder_id = NULL, updated_at = NOW() WHERE folder_id = ? AND user_id = ?')
            ->execute([$folderId, $userId]);
        $sort = 0;
        $setList = $pdo->prepare('UPDATE map_lists SET sort_order = ? WHERE id = ?');
        $setFolder = $pdo->prepare('UPDATE map_list_folders SET sort_order = ? WHERE id = ?');
        foreach ($rows as $r) {
            if ($r['kind'] === 'folder' && (int) $r['id'] === $folderId) {
                foreach ($freed as $listId) {
                    $setList->execute([$sort += 10, (int) $listId]);
                }
                continue;
            }
            ($r['kind'] === 'folder' ? $setFolder : $setList)->execute([min(32000, $sort += 10), (int) $r['id']]);
        }
        $pdo->prepare('DELETE FROM map_list_folders WHERE id = ? AND user_id = ?')->execute([$folderId, $userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return true;
}
