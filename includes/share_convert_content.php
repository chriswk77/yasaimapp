<?php
/**
 * The conversion of the old boxes into list shares (migration 0160) — a
 * button with a preview in the server administration, never silent
 * (08.10.2026: „die Umstellung … läuft als Knopf mit Vorschau").
 *
 * WHAT HAPPENS TO EACH LIST HUNG INTO A BOX: it becomes a share of its
 * owner — one container for the list, whichever boxes it hung in — and
 * every member of the box becomes a recipient row with the share's rights
 * and the box's filter. Somebody in two boxes with the same list gets one
 * row with both rights.
 *
 * WHO KEEPS SIGHT: the box's owner and its members — as far as they are
 * FRIENDS of the list's owner (08.10.2026: the new rule is „an
 * meine Freunde"; whoever is not loses sight, the preview names them
 * first). A working group stays a group where it is the list owner's own;
 * a group of somebody else's box is spelled out into its members.
 *
 * A SUBLIST STAYS A SUBLIST. The old boxes often carried a branch („Pilze"
 * below „Meine Funde"); turned into the whole main list, its recipients
 * would suddenly see more than they were ever shown. So the share keeps
 * exactly the branch it was (list_share_container_create takes any list).
 *
 * „GEMEINSAM BEARBEITEN" BECOMES THE TICKS: every right, the fields
 * included, and own_points — no filter, as the joint lists never had one.
 * The members' points stay in the list; whoever loses sight takes his home.
 *
 * THE CHAT: a box's conversation moves to a new share whose recipients all
 * came from that box alone — they had read it. Where boxes merge, nobody
 * from the other box may read it, so it stays behind and goes with the
 * box; the preview says which.
 */

declare(strict_types=1);

require_once __DIR__ . '/list_shares.php';
require_once __DIR__ . '/joint_lists.php';

/** Whether there is anything to convert — the tab glows while there is. */
function share_convert_pending(PDO $pdo): int
{
    if (!list_shares_ready($pdo)) {
        return 0;
    }
    return (int) $pdo->query('SELECT COUNT(*) FROM map_containers WHERE list_id IS NULL')->fetchColumn();
}

/**
 * An old birth filter (migration 0050: time, area, title rules) in the
 * points filter's form — and what reads differently now.
 *
 * @return array{0: ?array, 1: string[]}  the raw filter and the notes
 */
function share_convert_filter(?array $old): array
{
    if ($old === null) {
        return [null, []];
    }
    $new   = [];
    $notes = [];
    if (isset($old['time'])) {
        $new['from']  = (string) ($old['time']['from'] ?? '');
        $new['until'] = (string) ($old['time']['until'] ?? '');
        $notes[] = t('Zeitraum: Punkte ohne Fundzeit zählen jetzt mit dem Tag, an dem sie angelegt wurden.');
    }
    if (isset($old['area']['corners'])) {
        $new['areas'] = [$old['area']['corners']];
    }
    if (!empty($old['terms'])) {
        $new['terms'] = array_map(static fn(array $r): array =>
            ['type' => ($r['type'] ?? '') === 'contains' ? 'contains' : 'contains_not', 'text' => (string) ($r['text'] ?? '')],
            $old['terms']);
        $notes[] = t('Suchwörter suchten bisher nur im Titel, jetzt in allen Feldern des Punkts.');
    }
    return [$new === [] ? null : $new, $notes];
}

/** A person's or group's name for the preview. */
function share_convert_name(PDO $pdo, string $type, int $id): string
{
    if ($type === 'group') {
        $stmt = $pdo->prepare('SELECT name FROM map_groups WHERE id = ?');
        $stmt->execute([$id]);
        return t('Gruppe „%s"', (string) $stmt->fetchColumn());
    }
    return joint_user_name($pdo, $id) ?: t('(nicht mehr vorhanden)');
}

/**
 * The whole conversion, computed and not yet done.
 *
 * @param ?int[] $only  just these boxes — for the test, which must not
 *                      convert the real boxes of the database it runs on
 * @return array{boxes: array, targets: array}
 */
function share_convert_plan(PDO $pdo, ?array $only = null): array
{
    $boxes   = [];
    $targets = [];
    if (!list_shares_ready($pdo)) {
        return ['boxes' => [], 'targets' => []];
    }

    $friendsOf = [];
    $friends = static function (int $userId) use ($pdo, &$friendsOf): array {
        return $friendsOf[$userId] ??= friend_ids($pdo, $userId);
    };

    $withRights = share_rights_ready($pdo);
    $withJoint  = share_joint_ready($pdo);
    $boxRows = $pdo->query(
        'SELECT c.id, c.name, c.owner_user_id, c.created_at, ' . sql_display_name() . ' AS owner_name
           FROM map_containers c JOIN map_users u ON u.id = c.owner_user_id
          WHERE c.list_id IS NULL' . ($only === null ? '' : ' AND c.id IN (' . (implode(',', array_map('intval', $only)) ?: '0') . ')') . '
          ORDER BY c.id'
    )->fetchAll();

    foreach ($boxRows as $c) {
        $cId   = (int) $c['id'];
        $owner = (int) $c['owner_user_id'];
        $left  = [];
        if (container_left_ready($pdo)) {
            $q = $pdo->prepare('SELECT user_id FROM map_container_left WHERE container_id = ?');
            $q->execute([$cId]);
            $left = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        }
        $q = $pdo->prepare('SELECT COUNT(*) FROM map_container_messages WHERE container_id = ?');
        $q->execute([$cId]);
        $boxes[$cId] = [
            'id' => $cId, 'name' => (string) $c['name'], 'owner_name' => (string) $c['owner_name'],
            'messages' => table_exists($pdo, 'map_container_messages') ? (int) $q->fetchColumn() : 0,
            'targets' => [],
        ];

        $q = $pdo->prepare('SELECT type, target_id, added_at FROM map_container_members WHERE container_id = ?');
        $q->execute([$cId]);
        $memberRows = $q->fetchAll();
        [$filter, $filterNotes] = share_convert_filter(container_filter($pdo, $cId));

        $q = $pdo->prepare(
            'SELECT cl.list_id' . ($withRights ? ', cl.rights' : '') . ($withJoint ? ', cl.joint' : '') . ',
                    l.user_id AS list_owner, l.name, l.parent_id
               FROM map_container_lists cl
               JOIN map_lists l ON l.id = cl.list_id AND l.deleted_at IS NULL
              WHERE cl.container_id = ?'
        );
        $q->execute([$cId]);
        foreach ($q->fetchAll() as $s) {
            $listId = (int) $s['list_id'];
            $sharer = (int) $s['list_owner'];
            $joint  = $withJoint && (int) ($s['joint'] ?? 0) === 1;
            $boxes[$cId]['targets'][] = $listId;

            if (!isset($targets[$listId])) {
                $path = (string) $s['name'];
                if ($s['parent_id'] !== null) {
                    $root = list_branch_root($pdo, $listId)['root'];
                    $r = $pdo->prepare('SELECT name FROM map_lists WHERE id = ?');
                    $r->execute([$root]);
                    $path = $r->fetchColumn() . ' › ' . $path;
                }
                $targets[$listId] = [
                    'list' => $listId, 'path' => $path, 'name' => (string) $s['name'],
                    'owner' => $sharer, 'owner_name' => joint_user_name($pdo, $sharer),
                    'sublist' => $s['parent_id'] !== null,
                    'existing' => list_share_container($pdo, $listId),
                    'recipients' => [], 'dropped' => [], 'sources' => [], 'notes' => [], 'home' => [],
                ];
            }
            $t = &$targets[$listId];
            $t['sources'][$cId] = true;

            $rights = $joint
                ? array_merge(LIST_SHARE_RIGHTS, ['field:*'])
                : array_values(array_filter(rights_compat($withRights ? rights_read($s['rights'] ?? null) : []),
                    static fn(string $r): bool => in_array($r, LIST_SHARE_RIGHTS, true) || str_starts_with($r, 'field:')));
            // A field the list no longer has would fail the save loudly.
            $fields = array_column(list_fields_of_root($pdo, $listId), 'name');
            $rights = array_values(array_filter($rights, static fn(string $r): bool =>
                !str_starts_with($r, 'field:') || $r === 'field:*' || in_array(substr($r, 6), $fields, true)));
            $rowFilter = $joint ? null : $filter;
            if (!$joint) {
                foreach ($filterNotes as $n) {
                    $t['notes'][$n] = true;
                }
            }

            // Every person the box reached, with the date he came in.
            $people = [];   // [type, id, since]
            if ($owner !== $sharer) {
                $people[] = ['friend', $owner, (string) $c['created_at']];
            }
            foreach ($memberRows as $m) {
                $mId = (int) $m['target_id'];
                if ($m['type'] === 'friend') {
                    $people[] = ['friend', $mId, (string) $m['added_at']];
                } elseif ($owner === $sharer) {
                    $people[] = ['group', $mId, (string) $m['added_at']];
                } else {
                    $g = $pdo->prepare('SELECT user_id, added_at FROM map_group_members WHERE group_id = ?');
                    $g->execute([$mId]);
                    foreach ($g->fetchAll() as $gm) {
                        $people[] = ['friend', (int) $gm['user_id'], max((string) $m['added_at'], (string) $gm['added_at'])];
                    }
                }
            }

            foreach ($people as [$type, $id, $since]) {
                if ($type === 'friend' && ($id === $sharer || in_array($id, $left, true))) {
                    continue;
                }
                if ($type === 'friend' && !in_array($id, $friends($sharer), true)) {
                    $t['dropped'][$id] = share_convert_name($pdo, 'friend', $id);
                    continue;
                }
                $key = $type . ':' . $id;
                if (!isset($t['recipients'][$key])) {
                    $t['recipients'][$key] = [
                        'type' => $type, 'id' => $id, 'name' => share_convert_name($pdo, $type, $id),
                        'rights' => $rights, 'filter' => $rowFilter, 'since' => $since, 'from' => [$cId => true],
                    ];
                    continue;
                }
                // Reached twice — the rights add up; the filter is the
                // union when one of them lets everything through.
                $r = &$t['recipients'][$key];
                $r['rights'] = array_values(array_unique(array_merge($r['rights'], $rights)));
                if ($r['filter'] !== $rowFilter) {
                    if ($r['filter'] === null || $rowFilter === null) {
                        $r['filter'] = null;
                    } else {
                        $t['notes'][t('Bei %s galten zwei verschiedene Filter — übernommen wird der erste.', $r['name'])] = true;
                    }
                }
                $r['since'] = min($r['since'], $since);
                $r['from'][$cId] = true;
                unset($r);
            }
            unset($t);
        }
    }

    // Whoever loses sight but has points in a list takes them home.
    foreach ($targets as $listId => &$t) {
        foreach (array_keys($t['dropped']) as $id) {
            if (isset($t['recipients']['friend:' . $id])) {
                unset($t['dropped'][$id]);   // reached another way after all
            }
        }
        if ($t['dropped'] !== []) {
            $branch = container_branches($pdo, [['list_id' => $listId, 'user_id' => $t['owner']]])[$listId] ?? [$listId];
            $lists  = implode(',', array_fill(0, count($branch), '?'));
            $people = implode(',', array_fill(0, count($t['dropped']), '?'));
            $q = $pdo->prepare("SELECT p.user_id, COUNT(*) FROM map_point_lists pl JOIN map_points p ON p.id = pl.point_id
                                 WHERE pl.list_id IN ($lists) AND p.user_id IN ($people) GROUP BY p.user_id");
            $q->execute(array_merge($branch, array_keys($t['dropped'])));
            foreach ($q->fetchAll(PDO::FETCH_NUM) as [$userId, $n]) {
                $t['home'][(int) $userId] = (int) $n;
            }
        }
        $t['notes'] = array_keys($t['notes']);
        // The chat comes along only from a box all its recipients sat in.
        $t['chat_from'] = count($t['sources']) === 1 && $t['existing'] === null
            ? (int) array_key_first($t['sources']) : null;
    }
    unset($t);

    foreach ($boxes as $cId => &$b) {
        $b['chat_to'] = [];
        foreach ($targets as $t) {
            if ($t['chat_from'] === $cId) {
                // With the owner: two people's „Meine Funde › Pilze" are two lists.
                $b['chat_to'][] = t('%s (von %s)', $t['path'], $t['owner_name']);
            }
        }
    }
    unset($b);

    return ['boxes' => $boxes, 'targets' => $targets];
}

/**
 * Do it — in one transaction: a half-converted state would show some
 * lists twice and others not at all.
 *
 * @return array{shares:int, boxes:int, home:int}
 */
function share_convert_run(PDO $pdo, ?array $only = null): array
{
    $plan = share_convert_plan($pdo, $only);
    if ($plan['boxes'] === []) {
        return ['shares' => 0, 'boxes' => 0, 'home' => 0];
    }
    $pdo->beginTransaction();
    try {
        // The messages that move, read before their box goes.
        $messages = [];
        foreach ($plan['targets'] as $t) {
            if ($t['chat_from'] !== null && !isset($messages[$t['chat_from']])) {
                $q = $pdo->prepare('SELECT * FROM map_container_messages WHERE container_id = ? ORDER BY id');
                $q->execute([$t['chat_from']]);
                $messages[$t['chat_from']] = $q->fetchAll();
            }
        }

        /* THE BOXES GO FIRST, rows and all — raw, not through
           container_delete(): that one would send the members' points home
           from every joint list, and here they stay. And first, because a
           list share takes its list's name, which an old box of the same
           owner may still carry. */
        $ids = implode(',', array_map('intval', array_keys($plan['boxes'])));
        foreach (['map_container_lists', 'map_container_members', 'map_container_left',
                  'map_container_state', 'map_container_messages'] as $table) {
            if (table_exists($pdo, $table)) {
                $pdo->exec("DELETE FROM $table WHERE container_id IN ($ids)");
            }
        }
        $pdo->exec("DELETE FROM map_containers WHERE id IN ($ids)");

        $home = 0;
        foreach ($plan['targets'] as $listId => $t) {
            $cId = $t['existing'] ?? list_share_container_create($pdo, $t['owner'], $listId, $t['name']);
            $insert = $pdo->prepare(
                'INSERT INTO map_container_members (container_id, type, target_id, rights, filter, added_at)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE rights = rights'
            );
            foreach ($t['recipients'] as $r) {
                $insert->execute([$cId, $r['type'], $r['id'],
                    $r['rights'] === [] ? null : json_encode(array_values($r['rights']), JSON_UNESCAPED_UNICODE),
                    $r['filter'] === null ? null : json_encode($r['filter'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                    $r['since']]);
            }
            list_share_joint_sync($pdo, $cId);
            if ($t['home'] !== []) {
                $home += list_share_send_home($pdo, $cId, $listId, $t['owner'], array_keys($t['home']), $t['name']);
            }
            foreach ($messages[$t['chat_from'] ?? -1] ?? [] as $m) {
                $cols = array_values(array_diff(array_keys($m), ['id', 'container_id']));
                $pdo->prepare('INSERT INTO map_container_messages (container_id, ' . implode(', ', $cols) . ')
                               VALUES (?' . str_repeat(', ?', count($cols)) . ')')
                    ->execute(array_merge([$cId], array_map(static fn(string $col) => $m[$col], $cols)));
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['shares' => count($plan['targets']), 'boxes' => count($plan['boxes']), 'home' => $home];
}

function share_convert_process(PDO $pdo): array
{
    $state = ['message' => null, 'error' => []];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'boxes_convert') {
        return $state;
    }
    csrf_check();
    try {
        $done = share_convert_run($pdo);
        $state['message'] = t('Umgestellt: %d alte Freigaben wurden zu %d geteilten Listen.', $done['boxes'], $done['shares']);
    } catch (Throwable $e) {
        error_log('Umstellung der Freigaben: ' . $e->getMessage());
        $state['error'][] = t('Die Umstellung ist gescheitert, es wurde nichts geändert: %s', $e->getMessage());
    }
    return $state;
}

function share_convert_render(PDO $pdo, array $state): void
{
    foreach ($state['error'] as $e) {
        notice('error', $e, float: true);
    }
    if ($state['message'] !== null) {
        notice('ok', $state['message'], float: true);
    }
    if (!list_shares_ready($pdo)) {
        notice('warning', t('Listen teilen braucht die Migration 0160 — sie ist noch nicht eingespielt.'));
        return;
    }
    $plan = share_convert_plan($pdo);
    ?>
    <p><?= h(t('Früher war eine Freigabe ein Kasten: Wer darin war, konnte eigene Listen hineinhängen, und der Besitzer des Kastens bestimmte den Filter für alle. Jetzt teilt jeder nur seine eigene Liste und stellt für jeden Freund einzeln ein, was er sieht und darf.')) ?></p>
    <p><?= h(t('Dieser Knopf macht aus jeder Liste in einem alten Kasten eine geteilte Liste ihres Besitzers. Die Leute aus dem Kasten bekommen sie mit denselben Rechten und demselben Filter. Wer mit dem Besitzer der Liste nicht befreundet ist, sieht sie danach nicht mehr — das steht unten bei jeder Liste.')) ?></p>

    <?php if ($plan['boxes'] === []): ?>
        <p class="hint-text"><?= h(t('Es gibt keine alten Freigaben mehr. Alles ist umgestellt.')) ?></p>
        <?php return; ?>
    <?php endif; ?>

    <h3><?= h(t('Was daraus wird')) ?></h3>
    <div class="table-frame">
        <table class="list">
            <thead><tr><th><?= h(t('Liste')) ?></th><th><?= h(t('Wer sie danach sieht')) ?></th><th><?= h(t('Wer sie nicht mehr sieht')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($plan['targets'] as $t): ?>
                <tr>
                    <td>
                        <strong><?= h($t['path']) ?></strong>
                        <span class="hint-text small" style="display:block"><?= h(t('von %s', $t['owner_name'])) ?><?= $t['sublist'] ? ' · ' . h(t('nur diese Unterliste')) : '' ?></span>
                        <?php foreach ($t['notes'] as $n): ?>
                            <span class="hint-text small" style="display:block"><?= h($n) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php foreach ($t['recipients'] as $r): ?>
                            <span style="display:block"><?= h($r['name']) ?>
                                <span class="hint-text small">· <?= h(share_convert_rights_readable($r['rights'])) ?><?= $r['filter'] !== null ? ' · ' . h(t('Filter: %s', (string) list_share_filter_readable(list_share_filter_read($r['filter'])))) : '' ?></span>
                            </span>
                        <?php endforeach; ?>
                        <?php if ($t['recipients'] === []): ?><span class="hint-text"><?= h(t('niemand')) ?></span><?php endif; ?>
                    </td>
                    <td>
                        <?php foreach ($t['dropped'] as $id => $name): ?>
                            <span style="display:block"><?= h($name) ?><?php if (isset($t['home'][$id])): ?>
                                <span class="hint-text small">· <?= h(t('nimmt %d eigene Punkte mit', $t['home'][$id])) ?></span><?php endif; ?></span>
                        <?php endforeach; ?>
                        <?php if ($t['dropped'] === []): ?><span class="hint-text">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3><?= h(t('Die alten Kästen und ihr Chat')) ?></h3>
    <div class="table-frame">
        <table class="list">
            <thead><tr><th><?= h(t('Alter Kasten')) ?></th><th><?= h(t('Chat')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($plan['boxes'] as $b): ?>
                <tr>
                    <td><strong><?= h($b['name']) ?></strong>
                        <span class="hint-text small" style="display:block"><?= h(t('von %s', $b['owner_name'])) ?><?= $b['targets'] === [] ? ' · ' . h(t('leer')) : '' ?></span></td>
                    <td><?php if ($b['messages'] === 0): ?>
                            <span class="hint-text"><?= h(t('kein Chat')) ?></span>
                        <?php elseif ($b['chat_to'] !== []): ?>
                            <?= h(t('%d Nachrichten gehen mit zu „%s"', $b['messages'], implode('", „', $b['chat_to']))) ?>
                        <?php else: ?>
                            <?= h(t('%d Nachrichten gehen verloren — die Liste trifft hier auf Leute aus einem anderen Kasten, die den Chat nie gesehen haben.', $b['messages'])) ?>
                        <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="post" action="<?= h(url('pages/administration.php') . '#boxes_convert') ?>" class="button-row right">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="boxes_convert">
        <button type="submit" class="button danger"
                data-confirm="<?= h(t('Alle alten Freigaben werden jetzt umgestellt. Wer oben unter „sieht sie nicht mehr" steht, verliert die Sicht auf diese Listen. Das lässt sich nicht rückgängig machen.')) ?>">
            <?= h(t('Jetzt umstellen')) ?></button>
    </form>
    <?php
}

/** The rights as short words, for the preview. */
function share_convert_rights_readable(array $rights): string
{
    if (in_array('own_points', $rights, true)) {
        return t('arbeitet mit, darf alles');
    }
    $words = [
        'title' => t('Titel'), 'note' => t('Notiz'), 'move' => t('verschieben'),
        'attach_photos' => t('Bilder anhängen'), 'delete_photos' => t('Bilder löschen'),
        'measure' => t('Bilder bearbeiten'), 'delete' => t('Papierkorb'),
    ];
    $out = [];
    foreach ($rights as $r) {
        if (isset($words[$r])) {
            $out[] = $words[$r];
        } elseif (str_starts_with($r, 'field:')) {
            $out[] = $r === 'field:*' ? t('alle Felder') : t('Feld „%s"', substr($r, 6));
        }
    }
    return $out === [] ? t('nur ansehen') : t('darf: %s', implode(', ', $out));
}
