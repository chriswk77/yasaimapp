<?php
/**
 * The conversion of the list trees into Mappen (migration 0168) — a button
 * with a preview in the server administration, like „Freigaben umstellen"
 * (11.10.2026: „Es gibt nur noch einfache Listen … und es geht
 * maximal nur noch eine Ebene tiefer").
 *
 * EVERY HEADING BECOMES A MAPPE, holding the lists that lay directly in it.
 * A heading inside a heading becomes a Mappe of its own, standing right
 * after its parent's („mach aus jeder Untersammelgruppe eine eigene
 * Mappe") — a Mappe never holds a Mappe. A heading whose children are all
 * headings brings no Mappe: it would stand there empty.
 *
 * EVERY LIST BELOW A HEADING TAKES ALONG WHAT IT INHERITED, as a COPY of
 * its own, because the heading that passed it down disappears:
 *   - species list: on when the list or any heading above it had it on
 *     (the server's rule in list_branch_root; the browser's root-only rule
 *     is contained in it). Find numbers, MykIS and protected areas follow
 *     it by themselves.
 *   - the fields: every heading's above it, before its own, by name — the
 *     values of a point are stored under the field's NAME, so nothing a
 *     point carries has to move.
 *   - the share: members, rights, filters. The heading's container (and
 *     its chat) goes to the FIRST list below it; every further list gets a
 *     copy with the same recipients and starts with an empty chat. A list
 *     that already has a share of its own (a sublist share left by
 *     „Freigaben umstellen") keeps it and gains the recipients it lacked.
 *   - „gemeinsam bearbeiten": the share row's `joint`, copied with it.
 *   - an auto list's source: the heading is replaced by the lists below it.
 *     Lists laid into the Mappe later do NOT join — a Mappe does nothing
 *     („hat überhaupt keine Funktion, außer optisch zusammengefasst").
 *   - a viewer's own colour and shape for the shared heading.
 *
 * Colour and shape need nothing: every list carries its own since
 * migration 0040.
 *
 * WHAT A HEADING STILL HOLDS ITSELF. Points cannot lie in a heading any
 * more, but a list moved under a filled one once left them there. Should
 * there be any — filings, a co-owner's filing, the list trash — a list of
 * the heading's name catches them inside its Mappe, with the same copies.
 *
 * OLD BOXES FIRST — where a HEADING hangs in one (map_containers.list_id
 * NULL): its row would have to be spread over several rows of somebody
 * else's box. „Freigaben umstellen" turns it into a list share, and this
 * button waits for that. A plain list in an old box stays as it is.
 */

declare(strict_types=1);

require_once __DIR__ . '/list_shares.php';
require_once __DIR__ . '/joint_lists.php';

/**
 * Released with step 2 (11.10.2026), when the sidebar, the list window and
 * the point window's list choice learned to show Mappen. Pressed before,
 * every list would have stood flat and ungrouped there.
 */
const FOLDERS_CONVERT_RELEASED = true;

function folders_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_list_folders') && column_exists($pdo, 'map_lists', 'folder_id');
}

/** How many lists still hang under another one — the tab glows while there are. */
function folders_convert_pending(PDO $pdo): int
{
    if (!folders_ready($pdo)) {
        return 0;
    }
    return (int) $pdo->query('SELECT COUNT(*) FROM map_lists WHERE parent_id IS NOT NULL')->fetchColumn();
}

/**
 * What the button would do, per account.
 *
 * @param int[]|null $only  user ids (the tests), null = everybody
 * @return array{blocked: ?string, users: array<int, array>}
 */
function folders_convert_plan(PDO $pdo, ?array $only = null): array
{
    $plan = ['blocked' => null, 'users' => []];

    $where = $only === null
        ? 'user_id IN (SELECT DISTINCT user_id FROM map_lists WHERE parent_id IS NOT NULL)'
        : 'user_id IN (' . implode(',', array_map('intval', $only ?: [0])) . ')';
    $rows = $pdo->query("SELECT id, user_id, parent_id, name, is_species, filter, sort_order, deleted_at
                           FROM map_lists WHERE $where ORDER BY sort_order, name, id")->fetchAll();
    $byUser = [];
    foreach ($rows as $r) {
        $byUser[(int) $r['user_id']][(int) $r['id']] = $r;
    }

    $fields = folders_convert_fields($pdo, array_map(static fn(array $r): int => (int) $r['id'], $rows));
    $shares = [];
    if (list_shares_ready($pdo)) {
        foreach ($pdo->query('SELECT id, list_id FROM map_containers WHERE list_id IS NOT NULL') as $c) {
            $shares[(int) $c['list_id']] = (int) $c['id'];
        }
    }

    foreach ($byUser as $userId => $lists) {
        $u = folders_convert_user_plan($pdo, $lists, $fields, $shares);
        if ($u !== null) {
            $u['name'] = joint_user_name($pdo, $userId);
            $plan['users'][$userId] = $u;
        }
    }

    // Only a HEADING hung into an old box stops the button: its row would
    // have to be spread over somebody else's box. Old boxes holding plain
    // lists are none of its business.
    $headings = array_merge([], ...array_map(static fn(array $u): array => $u['headings'], array_values($plan['users'])));
    if ($headings !== [] && list_shares_ready($pdo)) {
        $stuck = (int) $pdo->query('SELECT COUNT(*) FROM map_container_lists cl
                                      JOIN map_containers c ON c.id = cl.container_id AND c.list_id IS NULL
                                     WHERE cl.list_id IN (' . implode(',', array_map('intval', $headings)) . ')')->fetchColumn();
        if ($stuck > 0) {
            $plan['blocked'] = t('Eine Hauptliste hängt noch in einer alten Freigabe. Stelle zuerst unter „Freigaben umstellen" um, dann hier.');
        }
    }
    return $plan;
}

/** @return array<int, array> fields per list id, in their order */
function folders_convert_fields(PDO $pdo, array $listIds): array
{
    $out = [];
    if ($listIds === [] || !table_exists($pdo, 'map_list_fields')) {
        return $out;
    }
    foreach (array_chunk($listIds, 500) as $chunk) {
        $stmt = $pdo->query('SELECT * FROM map_list_fields WHERE list_id IN (' . implode(',', $chunk) . ') ORDER BY sort_order, id');
        foreach ($stmt as $f) {
            $out[(int) $f['list_id']][] = $f;
        }
    }
    return $out;
}

/**
 * One account's tree, walked in the sidebar's order.
 *
 * @param array<int, array> $lists  every list of the account, deleted ones too
 */
function folders_convert_user_plan(PDO $pdo, array $lists, array $fields, array $shares): ?array
{
    $children = [];
    $live     = [];
    foreach ($lists as $id => $l) {
        $parent = $l['parent_id'] === null ? null : (int) $l['parent_id'];
        // A parent that is gone or in the trash does not hold anything:
        // its live child stands at the top (list_delete pulls children up,
        // so this is only ever an old leftover).
        if ($parent !== null && (!isset($lists[$parent]) || $lists[$parent]['deleted_at'] !== null)) {
            $parent = $l['deleted_at'] === null ? null : $parent;
        }
        $children[$parent ?? 0][] = $id;
        if ($l['deleted_at'] === null && $parent !== null) {
            $live[$parent] = true;
        }
    }
    $isHeading = static fn(int $id): bool => isset($live[$id]) && $lists[$id]['deleted_at'] === null;

    $headings = array_values(array_filter(array_keys($lists), $isHeading));
    $hanging  = array_filter($lists, static fn(array $l): bool => $l['parent_id'] !== null);
    if ($headings === [] && $hanging === []) {
        return null;
    }

    $u = [
        'folders'  => [],   // in order: name, heading, lists [id => gains], deleted [ids], rescue
        'top'      => [],   // the new sequence at the top: ['folder', i] | ['list', id]
        'headings' => $headings,
        'autolists' => [],  // list id => new raw filter
        'looks'    => [],   // heading id => leaf ids
        'imports'  => [],   // heading id => folder index
        'loose'    => [],   // lists that only lose their parent_id (deleted ones under a leaf)
        'names'    => array_map(static fn(array $l): string => (string) $l['name'], $lists),
    ];

    // The chain of headings above a list, root first.
    $chain = static function (int $id) use ($lists): array {
        $up = [];
        $seen = [];
        $p = $lists[$id]['parent_id'];
        while ($p !== null && isset($lists[(int) $p]) && !isset($seen[(int) $p])) {
            $seen[(int) $p] = true;
            array_unshift($up, (int) $p);
            $p = $lists[(int) $p]['parent_id'];
        }
        return $up;
    };

    $leafGains = function (int $id) use ($lists, $fields, $shares, $chain, $pdo): array {
        $up = array_values(array_filter($chain($id), static fn(int $a): bool => isset($lists[$a])));
        $g = ['species' => false, 'fields' => [], 'shares' => [], 'from' => $up];
        if (!(bool) $lists[$id]['is_species']) {
            foreach ($up as $a) {
                if ((bool) $lists[$a]['is_species']) {
                    $g['species'] = true;
                }
            }
        }
        $own = array_map(static fn(array $f): string => mb_strtolower((string) $f['name']), $fields[$id] ?? []);
        foreach ($up as $a) {
            foreach ($fields[$a] ?? [] as $f) {
                $key = mb_strtolower((string) $f['name']);
                if (!in_array($key, $own, true)) {
                    $own[] = $key;
                    $g['fields'][] = $f;
                }
            }
        }
        // The nearest share first: it decides, the farther ones only add.
        foreach (array_reverse($up) as $a) {
            if (isset($shares[$a])) {
                $g['shares'][] = $shares[$a];
            }
        }
        return $g;
    };

    $leavesBelow = static function (int $id) use (&$leavesBelow, $children, $lists, $isHeading): array {
        $out = [];
        foreach ($children[$id] ?? [] as $c) {
            if ($lists[$c]['deleted_at'] !== null) {
                continue;
            }
            $out = array_merge($out, $isHeading($c) ? $leavesBelow($c) : [$c]);
        }
        return $out;
    };

    $visit = function (int $h) use (&$visit, &$u, $children, $lists, $isHeading, $leafGains, $leavesBelow, $pdo): void {
        $leaves  = [];
        $deleted = [];
        $sub     = [];
        foreach ($children[$h] ?? [] as $c) {
            if ($lists[$c]['deleted_at'] !== null) {
                $deleted[] = $c;
            } elseif ($isHeading($c)) {
                $sub[] = $c;
            } else {
                $leaves[$c] = $leafGains($c);
                // A deleted list under a plain list lands where its parent goes.
                foreach ($children[$c] ?? [] as $gone) {
                    $deleted[] = $gone;
                }
            }
        }
        $rescue = folders_convert_rescue_count($pdo, $h);
        if ($leaves !== [] || $rescue > 0) {
            $u['folders'][] = ['name' => (string) $lists[$h]['name'], 'heading' => $h,
                               'lists' => $leaves, 'deleted' => $deleted, 'rescue' => $rescue,
                               'rescue_gains' => $rescue > 0 ? $leafGains($h) : null];
            $u['top'][] = ['folder', count($u['folders']) - 1];
            $u['imports'][$h] = count($u['folders']) - 1;
        } else {
            $u['loose'] = array_merge($u['loose'], $deleted);
        }
        $u['looks'][$h] = $leavesBelow($h);
        foreach ($sub as $s) {
            $visit($s);
            // An import's root that brought no Mappe itself (only headings
            // below it) goes with the first Mappe of its branch.
            if (!isset($u['imports'][$h]) && isset($u['imports'][$s])) {
                $u['imports'][$h] = $u['imports'][$s];
            }
        }
    };

    foreach ($children[0] ?? [] as $id) {
        if ($isHeading($id)) {
            $visit($id);
        } else {
            $u['top'][] = ['list', $id];
            foreach ($children[$id] ?? [] as $c) {
                $u['loose'][] = $c;   // deleted children of a plain list
            }
        }
    }
    // Deleted children under a deleted parent and similar leftovers: they
    // only lose their parent.
    foreach ($hanging as $id => $l) {
        $known = in_array($id, $u['loose'], true) || in_array($id, $headings, true);
        foreach ($u['folders'] as $f) {
            $known = $known || isset($f['lists'][$id]) || in_array($id, $f['deleted'], true);
        }
        if (!$known) {
            $u['loose'][] = $id;
        }
    }

    // Auto lists whose source is a heading take the lists below it instead.
    foreach ($lists as $id => $l) {
        if ($l['filter'] === null || $l['deleted_at'] !== null) {
            continue;
        }
        $raw = json_decode((string) $l['filter'], true);
        if (!is_array($raw) || !isset($raw['sources']) || !is_array($raw['sources'])) {
            continue;
        }
        $new = [];
        $changed = false;
        foreach ($raw['sources'] as $s) {
            if (is_numeric($s) && in_array((int) $s, $headings, true)) {
                $changed = true;
                foreach ($leavesBelow((int) $s) as $leaf) {
                    $new[] = $leaf;
                }
            } else {
                $new[] = $s;
            }
        }
        if ($changed) {
            $raw['sources'] = array_values(array_unique($new, SORT_REGULAR));
            $u['autolists'][$id] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        }
    }
    return $u;
}

/** What still lies in a heading itself: filings, co-owners' filings, the list trash. */
function folders_convert_rescue_count(PDO $pdo, int $listId): int
{
    $n = 0;
    foreach (['map_point_lists', 'map_point_owners', 'map_list_trash'] as $table) {
        if (table_exists($pdo, $table)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE list_id = ?");
            $stmt->execute([$listId]);
            $n += (int) $stmt->fetchColumn();
        }
    }
    return $n;
}

/** The plain sentences of the preview: what one list takes along. */
function folders_convert_gains_readable(PDO $pdo, array $g, array $listNames): array
{
    $out = [];
    if ($g['species']) {
        $out[] = t('wird Artenliste');
    }
    if ($g['fields'] !== []) {
        $out[] = t('bekommt die Felder %s', '„' . implode('", „', array_map(static fn(array $f): string => (string) $f['name'], $g['fields'])) . '"');
    }
    foreach ($g['shares'] as $c) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM map_container_members WHERE container_id = ?');
        $stmt->execute([$c]);
        $out[] = t('wird geteilt wie „%s" (%d Empfänger)', $listNames[(int) list_share_list($pdo, $c)] ?? '?', (int) $stmt->fetchColumn());
    }
    return $out;
}

/**
 * Does it, everything in one transaction.
 *
 * @return array{folders:int, lists:int, accounts:int}
 */
function folders_convert_run(PDO $pdo, ?array $only = null): array
{
    $plan = folders_convert_plan($pdo, $only);
    if ($plan['blocked'] !== null) {
        throw new RuntimeException($plan['blocked']);
    }
    $done = ['folders' => 0, 'lists' => 0, 'accounts' => 0];
    if ($plan['users'] === []) {
        return $done;
    }
    $now = date('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        foreach ($plan['users'] as $userId => $u) {
            $done['accounts']++;
            $given = [];   // container id => already handed to a list
            $sort = 0;
            $folderIds = [];
            foreach ($u['top'] as [$kind, $ref]) {
                $sort += 10;
                if ($kind === 'list') {
                    $pdo->prepare('UPDATE map_lists SET sort_order = ?, parent_id = NULL WHERE id = ?')->execute([$sort, $ref]);
                    continue;
                }
                $f = $u['folders'][$ref];
                $pdo->prepare('INSERT INTO map_list_folders (user_id, name, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$userId, mb_substr($f['name'], 0, 100), $sort, $now, $now]);
                $folderId = (int) $pdo->lastInsertId();
                $folderIds[$ref] = $folderId;
                $done['folders']++;

                $inner = 0;
                $lists = $f['lists'];
                if ($f['rescue'] > 0) {
                    $rescueId = folders_convert_rescue($pdo, (int) $userId, (int) $f['heading'], $folderId, $now);
                    // It stands for the heading itself, so a viewer's own
                    // look for any heading above reaches it too.
                    foreach ($u['looks'] as $h => $leaves) {
                        if ($h === (int) $f['heading'] || in_array($h, $f['rescue_gains']['from'], true)) {
                            $u['looks'][$h][] = $rescueId;
                        }
                    }
                    $lists = [$rescueId => $f['rescue_gains']] + $lists;
                }
                foreach ($lists as $listId => $g) {
                    $inner += 10;
                    $pdo->prepare('UPDATE map_lists SET parent_id = NULL, folder_id = ?, sort_order = ?, updated_at = ? WHERE id = ?')
                        ->execute([$folderId, $inner, $now, $listId]);
                    folders_convert_take_along($pdo, (int) $listId, $g, $given);
                    $done['lists']++;
                }
                foreach ($f['deleted'] as $listId) {
                    $pdo->prepare('UPDATE map_lists SET parent_id = NULL, folder_id = ? WHERE id = ?')->execute([$folderId, $listId]);
                }
            }
            foreach ($u['loose'] as $listId) {
                $pdo->prepare('UPDATE map_lists SET parent_id = NULL WHERE id = ?')->execute([$listId]);
            }
            foreach ($u['autolists'] as $listId => $raw) {
                $pdo->prepare('UPDATE map_lists SET filter = ? WHERE id = ?')->execute([$raw, $listId]);
            }
            if (table_exists($pdo, 'map_shared_looks')) {
                foreach ($u['looks'] as $h => $leaves) {
                    foreach ($leaves as $leaf) {
                        $pdo->prepare('INSERT IGNORE INTO map_shared_looks (user_id, list_id, color, symbol)
                                       SELECT user_id, ?, color, symbol FROM map_shared_looks WHERE list_id = ?')
                            ->execute([$leaf, $h]);
                    }
                }
            }
            foreach ($u['imports'] as $h => $ref) {
                if (isset($folderIds[$ref])) {
                    $pdo->prepare('UPDATE map_imports SET folder_id = ?, list_id = NULL WHERE list_id = ? AND user_id = ?')
                        ->execute([$folderIds[$ref], $h, $userId]);
                }
            }

            // THE HEADINGS GO LAST, when nothing points at them any more.
            // Their own fields and shared looks go with them; a share of
            // theirs normally went to the first list below it (every
            // heading has a live list below it, or it would not be one).
            if ($u['headings'] !== []) {
                $ids = implode(',', array_map('intval', $u['headings']));
                $pdo->exec("UPDATE map_lists SET parent_id = NULL WHERE parent_id IN ($ids)");
                if (table_exists($pdo, 'map_shared_looks')) {
                    $pdo->exec("DELETE FROM map_shared_looks WHERE list_id IN ($ids)");
                }
                /* A heading's share no list took: every list below it had
                   a share of its own and only gained the recipients. Then
                   the heading's container goes, raw like in
                   share_convert_run — container_delete() would send the
                   members' points home, and they stay. */
                $left = $pdo->query("SELECT id FROM map_containers WHERE list_id IN ($ids)")->fetchAll(PDO::FETCH_COLUMN);
                if ($left !== []) {
                    $cIds = implode(',', array_map('intval', $left));
                    foreach (['map_container_lists', 'map_container_members', 'map_container_left',
                              'map_container_state', 'map_container_messages'] as $table) {
                        if (table_exists($pdo, $table)) {
                            $pdo->exec("DELETE FROM $table WHERE container_id IN ($cIds)");
                        }
                    }
                    $pdo->exec("DELETE FROM map_containers WHERE id IN ($cIds)");
                }
                $pdo->exec("DELETE FROM map_lists WHERE id IN ($ids)");
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $done;
}

/** A list of the heading's name that takes over what still lay in the heading itself. */
function folders_convert_rescue(PDO $pdo, int $userId, int $headingId, int $folderId, string $now): int
{
    $pdo->prepare('INSERT INTO map_lists (user_id, name, color, symbol, is_species, folder_id, sort_order, created_at, updated_at)
                   SELECT user_id, name, color, symbol, is_species, ?, 0, ?, ? FROM map_lists WHERE id = ?')
        ->execute([$folderId, $now, $now, $headingId]);
    $id = (int) $pdo->lastInsertId();
    foreach (['map_point_lists', 'map_point_owners', 'map_list_trash'] as $table) {
        if (table_exists($pdo, $table)) {
            $pdo->prepare("UPDATE IGNORE $table SET list_id = ? WHERE list_id = ?")->execute([$id, $headingId]);
        }
    }
    // The heading's own fields belong to it as its own: the gains carry
    // only those of the headings ABOVE it.
    if (table_exists($pdo, 'map_list_fields')) {
        $pdo->prepare('INSERT IGNORE INTO map_list_fields (list_id, name, type, is_species, options, sort_order)
                       SELECT ?, name, type, is_species, options, sort_order FROM map_list_fields WHERE list_id = ?')
            ->execute([$id, $headingId]);
    }
    if (list_shares_ready($pdo) && ($c = list_share_container($pdo, $headingId)) !== null) {
        // Its own share goes first, so the rescue list is the „first list".
        $pdo->prepare('UPDATE map_containers SET list_id = ? WHERE id = ?')->execute([$id, $c]);
        $pdo->prepare('UPDATE map_container_lists SET list_id = ? WHERE container_id = ?')->execute([$id, $c]);
    }
    return $id;
}

/**
 * One list takes along what it inherited.
 *
 * @param array<int, true> $given  containers already handed to a list (by reference)
 */
function folders_convert_take_along(PDO $pdo, int $listId, array $g, array &$given): void
{
    if ($g['species']) {
        $pdo->prepare('UPDATE map_lists SET is_species = 1 WHERE id = ?')->execute([$listId]);
    }
    foreach ($g['fields'] as $i => $f) {
        // Inherited fields stand before the list's own, as they did.
        $pdo->prepare('INSERT IGNORE INTO map_list_fields (list_id, name, type, is_species, options, sort_order)
                       VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$listId, $f['name'], $f['type'], (int) $f['is_species'], $f['options'] ?? null, -1000 + $i]);
    }
    if ($g['shares'] === [] || !list_shares_ready($pdo)) {
        return;
    }
    $name = (string) $pdo->query('SELECT name FROM map_lists WHERE id = ' . $listId)->fetchColumn();
    $own  = list_share_container($pdo, $listId);
    foreach ($g['shares'] as $source) {
        if ($own === null && !isset($given[$source])) {
            // THE FIRST LIST TAKES THE CONTAINER ITSELF — and its chat, and
            // its name: a share is named after its list (Organisieren shows
            // it under „Geteilt"), and the heading's name would point at
            // something that no longer exists.
            $given[$source] = true;
            $owner = (int) $pdo->query('SELECT owner_user_id FROM map_containers WHERE id = ' . (int) $source)->fetchColumn();
            $taken = $pdo->prepare('SELECT 1 FROM map_containers WHERE owner_user_id = ? AND name = ? AND id <> ?');
            $cName = mb_substr($name, 0, 90);
            $taken->execute([$owner, $cName, $source]);
            if ($taken->fetchColumn() !== false || container_name_reserved($cName)) {
                $cName = mb_substr($name, 0, 80) . ' · ' . $listId;
            }
            $pdo->prepare('UPDATE map_containers SET list_id = ?, name = ? WHERE id = ?')->execute([$listId, $cName, $source]);
            $pdo->prepare('UPDATE map_container_lists SET list_id = ? WHERE container_id = ?')->execute([$listId, $source]);
            $own = $source;
            continue;
        }
        if ($own === null) {
            $owner = (int) $pdo->query('SELECT owner_user_id FROM map_containers WHERE id = ' . (int) $source)->fetchColumn();
            $own = list_share_container_create($pdo, $owner, $listId, $name);
            foreach (['map_container_left' => 'container_id, user_id, left_at',
                      'map_container_state' => 'user_id, container_id, members, own_name, admin_notice'] as $table => $cols) {
                if (table_exists($pdo, $table)) {
                    $select = str_replace('container_id', (string) $own, $cols);
                    $pdo->prepare("INSERT IGNORE INTO $table ($cols) SELECT $select FROM $table WHERE container_id = ?")
                        ->execute([$source]);
                }
            }
        }
        // Recipients the list lacks come along with their rights and filter;
        // one it already has keeps what was set for this list.
        $pdo->prepare('INSERT IGNORE INTO map_container_members (container_id, type, target_id, added_at, rights, filter)
                       SELECT ?, type, target_id, added_at, rights, filter FROM map_container_members WHERE container_id = ?')
            ->execute([$own, $source]);
        if (share_joint_ready($pdo)) {
            $pdo->prepare('UPDATE map_container_lists t JOIN map_container_lists s ON s.container_id = ? AND s.joint = 1
                              SET t.joint = 1 WHERE t.container_id = ?')
                ->execute([$source, $own]);
        }
    }
}

function folders_convert_process(PDO $pdo): array
{
    $state = ['message' => null, 'error' => []];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'folders_convert') {
        return $state;
    }
    csrf_check();
    try {
        $done = folders_convert_run($pdo);
        $state['message'] = t('Umgestellt: %d Konten, %d Mappen, %d Listen.', $done['accounts'], $done['folders'], $done['lists']);
    } catch (Throwable $e) {
        error_log('Umstellung auf Mappen: ' . $e->getMessage());
        $state['error'][] = t('Die Umstellung ist gescheitert, es wurde nichts geändert: %s', $e->getMessage());
    }
    return $state;
}

function folders_convert_render(PDO $pdo, array $state): void
{
    foreach ($state['error'] as $e) {
        notice('error', $e, float: true);
    }
    if ($state['message'] !== null) {
        notice('success', $state['message'], float: true);
    }
    if (!folders_ready($pdo)) {
        notice('warning', t('Mappen brauchen die Migration 0168 — sie ist noch nicht eingespielt.'));
        return;
    }
    ?>
    <p><?= h(t('Bisher konnte eine Liste Unterlisten haben, und die Unterlisten bekamen ihre Einstellungen von der Hauptliste. Jetzt hat jede Liste alle Einstellungen selbst. Mehrere Listen kann man in eine Mappe legen. Die Mappe ist nur ein Name, sie fasst die Listen in der Seitenleiste zusammen.')) ?></p>
    <p><?= h(t('Dieser Knopf macht aus jeder Hauptliste eine Mappe. Jede Unterliste bekommt eine Kopie von allem, was sie bisher von oben bekam: Artenliste, Felder und Teilen. Ein Beispiel: War „Meine Funde" mit Ben geteilt, ist danach jede Liste in der Mappe „Meine Funde" mit Ben geteilt. Danach kann man jede Liste einzeln ändern.')) ?></p>
    <?php
    $plan = folders_convert_plan($pdo);
    if ($plan['users'] === []) {
        ?><p class="hint-text"><?= h(t('Es gibt keine Unterlisten mehr. Alles ist umgestellt.')) ?></p><?php
        return;
    }
    if ($plan['blocked'] !== null) {
        notice('warning', $plan['blocked']);
    }
    ?>
    <h3><?= h(t('Was daraus wird')) ?></h3>
    <div class="given-groups">
        <?php foreach ($plan['users'] as $u): ?>
            <?php $names = $u['names']; ?>
            <div class="person-section given-head"><span><?= h($u['name']) ?></span></div>
            <ul class="person-list">
                <?php foreach ($u['folders'] as $f): ?>
                    <li class="person-row">
                        <span class="person-name">
                            <?= icon('folder', ['class' => 'ct-row-icon']) ?><?= h(t('Mappe „%s"', $f['name'])) ?>
                            <?php foreach ($f['lists'] as $id => $g): ?>
                                <?php $gains = folders_convert_gains_readable($pdo, $g, $names); ?>
                                <span class="item-data">· <?= h($names[$id] ?? '?') ?><?= $gains !== [] ? ' — ' . h(implode(', ', $gains)) : '' ?></span>
                            <?php endforeach; ?>
                            <?php if ($f['rescue'] > 0): ?>
                                <span class="item-data">· <?= h(t('„%s" (neu, für %d Punkte, die noch in der Hauptliste lagen)', $f['name'], $f['rescue'])) ?></span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
                <?php if ($u['autolists'] !== []): ?>
                    <li class="person-row"><span class="person-name"><?= h(t('%d automatische Listen nehmen statt der Hauptliste deren Listen als Quelle.', count($u['autolists']))) ?></span></li>
                <?php endif; ?>
            </ul>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= h(url('pages/administration.php') . '#folders_convert') ?>" class="button-row right" style="margin-top:1.4rem">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="folders_convert">
        <button type="submit" class="button danger"<?= $plan['blocked'] !== null ? ' disabled' : '' ?>
                data-confirm="<?= h(t('Alle Hauptlisten werden jetzt zu Mappen, und jede Unterliste bekommt ihre eigenen Einstellungen. Das lässt sich nicht rückgängig machen.')) ?>">
            <?= h(t('Jetzt umstellen')) ?></button>
    </form>
    <?php
}
