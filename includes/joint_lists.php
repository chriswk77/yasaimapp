<?php
/**
 * Joint lists — „gemeinsam bearbeiten" — beyond the switch: the list's
 * own trash, the chat lines about every change and the handover chain.
 * Steps 2 and 3 (22.09.2026);
 * the switch itself and the rights are step 1 and live in container.php.
 *
 * THE MODEL, AS AGREED (21./22.09.2026):
 *
 *   - Every change to a point that lies in a joint list is a line in the
 *     chat of that share — one line per save, the changed fields named
 *     („alle Änderungen ins Chatfenster, auch wenn das mal viel ist —
 *     bleibt man so auf dem Laufenden"). The line is written by the map
 *     in the name of whoever changed something (kind 'change'), so it
 *     reads as a fact and not as something they said.
 *
 *   - Deleting in a joint list takes the point's FILING out of that list
 *     into the list's trash (map_list_trash: who, when) — option A. The
 *     record lives while any filing lives; a point without a filing left
 *     is in the trash as a whole (deleted_at) and revives from any list
 *     trash with that one filing. Any member restores. Nobody purges: a
 *     trash row here keeps the point (point_delete_permanently asks).
 *
 *   - The holder's private filings are not a member's to touch: taking
 *     the point out of a joint list leaves it in his own lists, and he
 *     reads in the chat what happened.
 *
 *   - THE LIST ITSELF BELONGS TO THE GROUP (step 3, 22.09.2026, the
 *     handover chain at the end of this file): whoever hung it in hands
 *     it to the most senior member when he deletes it or leaves the
 *     share; only the last one left deletes. Whoever leaves takes his
 *     own points out with him, and whoever unhooks the list or switches
 *     „gemeinsam" off gives the others their points back. Every one of
 *     these is a line in the chat — nothing here happens in silence.
 *
 * WHY A FILE OF ITS OWN: container.php holds the shares and their rights,
 * chat.php the conversation, data.php the points — this here is the one
 * place where all three meet, and a fourth reader of any of them should
 * not have to know about the other two.
 */

declare(strict_types=1);

require_once __DIR__ . '/container.php';
require_once __DIR__ . '/chat.php';

/** Whether the list trash exists (migration 0111). */
function joint_trash_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_list_trash');
}

/**
 * The joint shares whose branch holds one of the given lists — keyed by
 * container, with the lists that hit. For a point these are its live
 * filings; for a trash row, the list the filing was taken out of.
 *
 * @return array<int, array{container_id:int, share_id:int, sharer:int, root:int, lists:int[]}>
 */
function joint_shares_of_lists(PDO $pdo, array $listIds): array
{
    $listIds = array_values(array_unique(array_map('intval', $listIds)));
    if ($listIds === [] || !share_joint_ready($pdo)) {
        return [];
    }
    $shares = $pdo->query('SELECT id, container_id, list_id, user_id FROM map_container_lists WHERE joint = 1')->fetchAll();
    if ($shares === []) {
        return [];
    }
    $branches = container_branches($pdo, $shares);
    $out = [];
    foreach ($shares as $f) {
        $hits = array_values(array_intersect($branches[(int) $f['list_id']] ?? [], $listIds));
        if ($hits === []) {
            continue;
        }
        $cId = (int) $f['container_id'];
        $out[$cId] = [
            'container_id' => $cId,
            'share_id'     => (int) $f['id'],
            'sharer'       => (int) $f['user_id'],
            'root'         => (int) $f['list_id'],
            'lists'        => array_values(array_unique(array_merge($out[$cId]['lists'] ?? [], $hits))),
        ];
    }
    return $out;
}

/** The point's live filings — every list it lies in, the auto lists included. */
function point_filings(PDO $pdo, int $pointId): array
{
    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * The joint shares a point lies in — all of them, or only those $userId
 * is a member of (the window's choice, the API's delete).
 */
function point_joint_shares(PDO $pdo, int $pointId, ?int $userId = null): array
{
    $all = joint_shares_of_lists($pdo, point_filings($pdo, $pointId));
    if ($userId === null) {
        return $all;
    }
    return array_filter($all, static fn(array $s): bool => container_visible($pdo, $userId, $s['container_id']));
}

/** Whether a filing of this point lies in a list trash — then nobody purges it. */
function point_in_joint_trash(PDO $pdo, int $pointId): bool
{
    if (!joint_trash_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_list_trash WHERE point_id = ? LIMIT 1');
    $stmt->execute([$pointId]);
    return $stmt->fetchColumn() !== false;
}

/** The list trashes holding filings of the given points: point id → list names. */
function joint_trash_held(PDO $pdo, array $pointIds): array
{
    $pointIds = array_values(array_unique(array_map('intval', $pointIds)));
    if ($pointIds === [] || !joint_trash_ready($pdo)) {
        return [];
    }
    $space = implode(',', array_fill(0, count($pointIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT t.point_id, l.name FROM map_list_trash t
           JOIN map_lists l ON l.id = t.list_id
          WHERE t.point_id IN ($space)
          ORDER BY l.name"
    );
    $stmt->execute($pointIds);
    $out = [];
    foreach ($stmt as $z) {
        $out[(int) $z['point_id']][] = (string) $z['name'];
    }
    return $out;
}

/** A line into the chats of the given containers, as the acting user. */
function joint_note(PDO $pdo, int $userId, string $text, array $containerIds): void
{
    if (!chat_ready($pdo)) {
        return;
    }
    foreach (array_values(array_unique(array_map('intval', $containerIds))) as $cId) {
        chat_note($pdo, $userId, $cId, $text);
    }
}

/** Several list names for inside one pair of quotes: the sentence's own „…" close the first and last, so „A", „B" comes out whole. */
function joint_list_names(PDO $pdo, array $listIds): string
{
    $names = array_map(static fn(int $id): string => joint_list_name($pdo, $id), array_map('intval', $listIds));
    return implode('", „', $names);
}

/** The point's title for a sentence — „Ohne Titel" when it has none. */
function joint_point_name(PDO $pdo, int $pointId): string
{
    $stmt = $pdo->prepare('SELECT title FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $title = trim((string) $stmt->fetchColumn());
    return $title !== '' ? $title : t('Ohne Titel');
}

/** A list's name for a sentence. */
function joint_list_name(PDO $pdo, int $listId): string
{
    $stmt = $pdo->prepare('SELECT name FROM map_lists WHERE id = ?');
    $stmt->execute([$listId]);
    $name = $stmt->fetchColumn();
    return $name === false ? t('(Liste gelöscht)') : (string) $name;
}

/** The buckets learn of a filing that moved — the same line point_lists_set draws. */
function joint_point_touch(PDO $pdo, int $pointId): void
{
    if (column_exists($pdo, 'map_points', 'touched_at')) {
        $pdo->prepare('UPDATE map_points SET touched_at = CURRENT_TIMESTAMP(6) WHERE id = ?')->execute([$pointId]);
    }
}

/**
 * What a change line may name — the point as it is, before and after an
 * update. Read raw and viewer-free: the comparison must not depend on
 * who is looking.
 */
function joint_snapshot(PDO $pdo, int $pointId): ?array
{
    $columns = 'title, note, lat, lon, found_at, outline, extra'
        . (column_exists($pdo, 'map_points', 'tour') ? ', tour' : '');
    $stmt = $pdo->prepare("SELECT $columns FROM map_points WHERE id = ?");
    $stmt->execute([$pointId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }
    $row['lists'] = point_filings($pdo, $pointId);
    return $row;
}

/**
 * Say in the joint chats what changed between two snapshots, and a line
 * for every joint filing that came or went (the holder ticking his point
 * into a joint list, or out of it — the one move the group cannot undo,
 * so it must at least read it).
 *
 * WHAT CHANGED IS COLLECTED FIRST, AS DATA, and only then said — because
 * it may fold into the line before (joint_line): one member filling in a
 * point field by field wrote five lines in six minutes
 * (24.09.2026: „wir könnten das etwas komprimieren").
 */
function joint_change_note(PDO $pdo, int $userId, int $pointId, ?array $before, ?array $after): void
{
    if ($before === null || $after === null || !chat_ready($pdo) || !share_joint_ready($pdo)) {
        return;
    }
    /* BEFORE AND AFTER, per field (23.09.2026: „man weiß, wie
       es vorher hieß und wie es jetzt heißt, und kann prüfen, ob das
       richtig ist"). Long values are cut at 60 characters. */
    $name  = trim((string) $after['title']) !== '' ? (string) $after['title'] : t('Ohne Titel');
    $show  = static function (mixed $value): string {
        $text = is_array($value) ? implode(', ', array_map('strval', $value)) : trim((string) ($value ?? ''));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return mb_strlen($text) > 60 ? mb_substr($text, 0, 59) . '…' : $text;
    };
    $day = static function (?string $at): string {
        $ts = $at ? strtotime($at) : false;
        return $ts ? date('d.m.Y H:i', $ts) : '';
    };

    $changes = [];
    $field = static function (string $key, string $label, string $old, string $new) use (&$changes): void {
        if ($old !== $new) {
            $changes[$key] = ['kind' => 'field', 'label' => $label, 'from' => $old, 'to' => $new];
        }
    };
    $oldTitle = trim((string) ($before['title'] ?? ''));
    if ($oldTitle !== trim((string) $after['title'])) {
        $changes['title'] = ['kind' => 'title', 'label' => t('Titel'),
            'from' => $oldTitle !== '' ? $show($oldTitle) : t('Ohne Titel'), 'to' => $show($name)];
    }
    $field('note', t('Notiz'), $show($before['note'] ?? ''), $show($after['note'] ?? ''));
    $field('found_at', t('Fundzeit'), $day($before['found_at'] ?? null), $day($after['found_at'] ?? null));
    if (array_key_exists('tour', $before)) {
        $field('tour', t('Tour'), $show($before['tour'] ?? ''), $show($after['tour'] ?? ''));
    }
    $extraBefore = is_string($before['extra'] ?? null) ? (json_decode($before['extra'], true) ?: []) : [];
    $extraAfter  = is_string($after['extra'] ?? null) ? (json_decode($after['extra'], true) ?: []) : [];
    foreach (array_unique(array_merge(array_keys($extraBefore), array_keys($extraAfter))) as $key) {
        // Through t(): the standard fields (Finder, Bestimmer …) translate.
        $field('extra.' . $key, t((string) $key), $show($extraBefore[$key] ?? ''), $show($extraAfter[$key] ?? ''));
    }
    if (number_format((float) $before['lat'], 6) !== number_format((float) $after['lat'], 6)
        || number_format((float) $before['lon'], 6) !== number_format((float) $after['lon'], 6)) {
        $changes['position'] = ['kind' => 'moved', 'label' => '', 'from' => '', 'to' => ''];
    }
    if ((string) ($before['outline'] ?? '') !== (string) ($after['outline'] ?? '')) {
        // Presence only: the corners themselves would tell nobody anything.
        $changes['outline'] = ['kind' => 'outline', 'label' => '',
            'from' => (string) ($before['outline'] ?? '') !== '' ? 'x' : '',
            'to'   => (string) ($after['outline'] ?? '') !== '' ? 'x' : ''];
    }

    /* THE LIST IS NAMED (23.09.2026): per share, the joint
       lists of THAT share the point lies in. */
    if ($changes !== []) {
        foreach (joint_shares_of_lists($pdo, $after['lists']) as $cId => $share) {
            joint_line($pdo, $userId, (int) $cId, $pointId, $name, joint_where($pdo, $share['lists']), $changes, false);
        }
    }

    // Filings that went or came — announced in the share of the list concerned.
    foreach (joint_shares_of_lists($pdo, array_diff($before['lists'], $after['lists'])) as $cId => $s) {
        foreach ($s['lists'] as $listId) {
            joint_note($pdo, $userId, t('hat „%s" aus der gemeinsamen Liste „%s" genommen', $name, joint_list_name($pdo, $listId)), [$cId]);
        }
    }
    foreach (joint_shares_of_lists($pdo, array_diff($after['lists'], $before['lists'])) as $cId => $s) {
        foreach ($s['lists'] as $listId) {
            joint_note($pdo, $userId, t('hat „%s" in die gemeinsame Liste „%s" gelegt', $name, joint_list_name($pdo, $listId)), [$cId]);
        }
    }
}

/** „in der Liste „X"" or „in den Listen „X", „Y"" — the tail of every change line. */
function joint_where(PDO $pdo, array $listIds): string
{
    return count($listIds) === 1
        ? t('in der Liste „%s"', joint_list_name($pdo, (int) reset($listIds)))
        : t('in den Listen „%s"', joint_list_names($pdo, $listIds));
}

/** How long a line stays open for the next change of the same member on the same point. */
const JOINT_FOLD_MINUTES = 30;

/**
 * Write a change line into one share's chat — or fold it into the line
 * before (24.09.2026).
 *
 * IT FOLDS when the newest line of the share is a change line of the SAME
 * member about the SAME point, younger than half an hour. Then the old
 * line is replaced by one that says both: each field keeps its first
 * „before" and takes the latest „after", and a field brought back to where
 * it was drops out. Anything in between — a word from someone, another
 * point, another member — ends the fold, so the chat never pretends an
 * order that was not.
 *
 * A NEW POINT IS ONE LINE. Its creation line swallows what its creator
 * fills in right after — title, note, outline — and says only „neu
 * angelegt" under the current name: the details are on the point, and
 * nobody needs the minutes of its birth.
 *
 * @param array<string, array{kind: string, label: string, from: string, to: string}> $changes
 */
function joint_line(PDO $pdo, int $userId, int $containerId, int $pointId, string $name,
                    string $where, array $changes, bool $created): void
{
    $prev = chat_latest_line($pdo, $containerId, JOINT_FOLD_MINUTES);
    $fold = $prev !== null
        && (int) $prev['user_id'] === $userId
        && (string) $prev['kind'] === 'change'
        && (int) $prev['fresh'] === 1
        && (int) ($prev['meta']['point'] ?? 0) === $pointId;

    if ($fold) {
        $created = $created || !empty($prev['meta']['created']);
        $merged  = (array) ($prev['meta']['changes'] ?? []);
        foreach ($changes as $key => $c) {
            if (isset($merged[$key])) {
                $c['from'] = (string) $merged[$key]['from'];
            }
            $merged[$key] = $c;
            // Back where it was: nothing left to say about it. A move and an
            // outline carry no values to compare, so they stay said.
            if (in_array($c['kind'], ['title', 'field'], true) && $c['from'] === $c['to']) {
                unset($merged[$key]);
            }
        }
        $changes = $merged;
    }

    if (!$created && $changes === []) {
        // Everything undone within the fold: the line had nothing left to say.
        if ($fold) {
            $pdo->prepare('DELETE FROM map_container_messages WHERE id = ?')->execute([(int) $prev['id']]);
        }
        return;
    }

    $text = $created ? t('hat „%s" neu angelegt, %s', $name, $where) : joint_change_text($name, $where, $changes);
    $meta = ['point' => $pointId, 'created' => $created, 'changes' => $created ? [] : $changes];
    chat_note($pdo, $userId, $containerId, $text, $meta, $fold ? (int) $prev['id'] : null);
}

/**
 * The sentence for a set of changes. ONE change keeps the full sentence it
 * always had („hat den Titel von „A" in „B" geändert, …"); SEVERAL become
 * one line with the parts in brackets — „hat „X" bearbeitet (Titel „A" →
 * „B", Notiz „…" eingetragen, verschoben), in der Liste „L"".
 */
function joint_change_text(string $name, string $where, array $changes): string
{
    if (count($changes) === 1) {
        $c = reset($changes);
        return match (true) {
            $c['kind'] === 'title'   => t('hat den Titel von „%s" in „%s" geändert, %s', $c['from'], $c['to'], $where),
            $c['kind'] === 'moved'   => t('hat „%s" verschoben, %s', $name, $where),
            $c['kind'] === 'outline' => match (true) {
                $c['from'] === '' => t('hat bei „%s" einen Umriss gezogen, %s', $name, $where),
                $c['to'] === ''   => t('hat bei „%s" den Umriss entfernt, %s', $name, $where),
                default           => t('hat den Umriss von „%s" geändert, %s', $name, $where),
            },
            $c['from'] === '' => t('hat bei „%s" %s „%s" eingetragen, %s', $name, $c['label'], $c['to'], $where),
            $c['to'] === ''   => t('hat bei „%s" %s „%s" gelöscht, %s', $name, $c['label'], $c['from'], $where),
            default           => t('hat bei „%s" %s von „%s" in „%s" geändert, %s', $name, $c['label'], $c['from'], $c['to'], $where),
        };
    }
    $parts = [];
    foreach ($changes as $c) {
        $parts[] = match (true) {
            $c['kind'] === 'title'   => t('Titel „%s" → „%s"', $c['from'], $c['to']),
            $c['kind'] === 'moved'   => t('verschoben'),
            $c['kind'] === 'outline' => match (true) {
                $c['from'] === '' => t('Umriss gezogen'),
                $c['to'] === ''   => t('Umriss entfernt'),
                default           => t('Umriss geändert'),
            },
            $c['from'] === '' => t('%s „%s" eingetragen', $c['label'], $c['to']),
            $c['to'] === ''   => t('%s „%s" gelöscht', $c['label'], $c['from']),
            default           => t('%s „%s" → „%s"', $c['label'], $c['from'], $c['to']),
        };
    }
    return t('hat „%s" bearbeitet (%s), %s', $name, implode(', ', $parts), $where);
}

/** A point just set into a joint list — the first line about it, one per share. */
function joint_created_note(PDO $pdo, int $userId, int $pointId): void
{
    if (!chat_ready($pdo)) {
        return;
    }
    $name = joint_point_name($pdo, $pointId);
    foreach (point_joint_shares($pdo, $pointId) as $cId => $s) {
        joint_line($pdo, $userId, (int) $cId, $pointId, $name, joint_where($pdo, $s['lists']), [], true);
    }
}

/**
 * Take a point's filing out of joint lists into their trash — only lists
 * the point lies in and $userId is a member of; the rest of the request
 * is ignored, not refused, because the window offers only what it sees.
 *
 * @return array{removed:int[], whole:bool} the lists it left, and whether
 *         the record itself went to the trash for want of any filing
 */
function joint_filing_remove(PDO $pdo, int $userId, int $pointId, array $listIds): array
{
    if (!joint_trash_ready($pdo)) {
        throw new InvalidArgumentException(t('Der Papierkorb der Liste braucht die Migration 0111 — sie ist noch nicht eingespielt.'));
    }
    $allowed = [];   // list id → container id
    foreach (point_joint_shares($pdo, $pointId, $userId) as $cId => $s) {
        foreach ($s['lists'] as $l) {
            $allowed[$l] = $cId;
        }
    }
    $todo = array_values(array_unique(array_filter(array_map('intval', $listIds),
        static fn(int $l): bool => isset($allowed[$l]))));
    if ($todo === []) {
        return ['removed' => [], 'whole' => false];
    }
    $name   = joint_point_name($pdo, $pointId);
    $delete = $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ? AND list_id = ?');
    $insert = $pdo->prepare(
        'INSERT INTO map_list_trash (point_id, list_id, removed_by, removed_at) VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE removed_by = VALUES(removed_by), removed_at = VALUES(removed_at)'
    );
    foreach ($todo as $listId) {
        $delete->execute([$pointId, $listId]);
        $insert->execute([$pointId, $listId, $userId]);
        joint_note($pdo, $userId,
            t('hat „%s" in den Papierkorb der Liste „%s" gelegt', $name, joint_list_name($pdo, $listId)),
            [$allowed[$listId]]);
    }

    /* NO FILING LEFT — the record itself goes to the trash. Auto lists do
       not count: their rows follow a filter, not a hand, and a point kept
       alive by a filter alone would be alive for nobody. The holder finds
       it in his trash (and cannot purge it while a row lies here); any
       member brings it back into their list. */
    $withFilter = column_exists($pdo, 'map_lists', 'filter');
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM map_point_lists pl
           JOIN map_lists l ON l.id = pl.list_id
          WHERE pl.point_id = ?' . ($withFilter ? ' AND l.filter IS NULL' : '')
    );
    $stmt->execute([$pointId]);
    $whole = (int) $stmt->fetchColumn() === 0;
    if ($whole) {
        $pdo->prepare('UPDATE map_points SET deleted_at = COALESCE(deleted_at, NOW()), updated_at = NOW() WHERE id = ?')
            ->execute([$pointId]);
    }
    joint_point_touch($pdo, $pointId);
    return ['removed' => $todo, 'whole' => $whole];
}

/**
 * Put a filing back — any member of a joint share of that list. The record
 * revives with it if it lay in the trash as a whole.
 */
function joint_filing_restore(PDO $pdo, int $userId, int $pointId, int $listId): bool
{
    if (!joint_trash_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_list_trash WHERE point_id = ? AND list_id = ?');
    $stmt->execute([$pointId, $listId]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }
    $mine = array_filter(joint_shares_of_lists($pdo, [$listId]),
        static fn(array $s): bool => container_visible($pdo, $userId, $s['container_id']));
    if ($mine === []) {
        throw new InvalidArgumentException(t('Du bist in keiner Freigabe dieser Liste.'));
    }
    $pdo->prepare('INSERT IGNORE INTO map_point_lists (point_id, list_id) VALUES (?, ?)')->execute([$pointId, $listId]);
    $pdo->prepare('DELETE FROM map_list_trash WHERE point_id = ? AND list_id = ?')->execute([$pointId, $listId]);
    $pdo->prepare('UPDATE map_points SET deleted_at = NULL, updated_at = NOW() WHERE id = ? AND deleted_at IS NOT NULL')
        ->execute([$pointId]);
    joint_point_touch($pdo, $pointId);
    joint_note($pdo, $userId,
        t('hat „%s" in die Liste „%s" zurückgeholt', joint_point_name($pdo, $pointId), joint_list_name($pdo, $listId)),
        array_keys($mine));
    return true;
}

/**
 * The list trashes I may look into — every joint share I am a member of,
 * per list its entries with holder, remover and time. For Verwaltung →
 * Papierkorb, section „Gemeinsame Listen". Lists without entries are left
 * out: an empty trash is no news.
 *
 * @return array[] per list: list (id, name), container (name), sharer (name), entries[]
 */
function joint_trash_lists(PDO $pdo, int $userId): array
{
    if (!joint_trash_ready($pdo) || !share_joint_ready($pdo) || !container_ready($pdo)) {
        return [];
    }
    $shares = $pdo->query(
        'SELECT cl.id, cl.container_id, cl.list_id, cl.user_id, c.name AS container_name,
                ' . sql_display_name() . ' AS sharer_name
           FROM map_container_lists cl
           JOIN map_containers c ON c.id = cl.container_id
           JOIN map_users u ON u.id = cl.user_id
          WHERE cl.joint = 1
          ORDER BY c.name, cl.id'
    )->fetchAll();
    $shares = array_values(array_filter($shares,
        static fn(array $f): bool => container_visible($pdo, $userId, (int) $f['container_id'])));
    if ($shares === []) {
        return [];
    }
    $branches = container_branches($pdo, $shares);
    $out = [];
    $entries = $pdo->prepare(
        'SELECT t.point_id, t.removed_at, p.title, p.lat, p.lon, p.deleted_at,
                ' . sql_display_name('h') . ' AS holder_name,
                ' . sql_display_name('r') . ' AS remover_name
           FROM map_list_trash t
           JOIN map_points p ON p.id = t.point_id
           JOIN map_users h ON h.id = p.user_id
           JOIN map_users r ON r.id = t.removed_by
          WHERE t.list_id = ?
          ORDER BY t.removed_at DESC'
    );
    $listName = $pdo->prepare('SELECT name FROM map_lists WHERE id = ?');
    $seen = [];
    foreach ($shares as $f) {
        foreach ($branches[(int) $f['list_id']] ?? [] as $listId) {
            if (isset($seen[$listId])) {
                continue;
            }
            $seen[$listId] = true;
            $entries->execute([$listId]);
            $rows = $entries->fetchAll();
            if ($rows === []) {
                continue;
            }
            $listName->execute([$listId]);
            $out[] = [
                'list'      => ['id' => $listId, 'name' => (string) $listName->fetchColumn()],
                'container' => (string) $f['container_name'],
                'sharer'    => (string) $f['sharer_name'],
                'entries'   => array_map(static fn(array $z): array => [
                    'point_id'   => (int) $z['point_id'],
                    'title'      => (string) $z['title'],
                    'lat'        => (float) $z['lat'],
                    'lon'        => (float) $z['lon'],
                    'removed_at' => (string) $z['removed_at'],
                    'holder'     => (string) $z['holder_name'],
                    'remover'    => (string) $z['remover_name'],
                    'whole'      => $z['deleted_at'] !== null,
                ], $rows),
            ];
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Step 3 — the handover chain (22.09.2026)
//
// „Löscht der Besitzer die Liste, geht sie an das dienstälteste Mitglied;
// löscht das letzte Mitglied, wandert alles in dessen persönlichen
// Papierkorb." A joint list belongs to the group in everything but the
// column: whoever hung it in may not take it away from the others by
// deleting it — or by leaving. Both hand it over instead, to the member
// who has been in the share longest; only the last one left deletes.
// ---------------------------------------------------------------------------

/**
 * The members of a container with the moment each joined — the owner
 * since the container exists, a friend since his row, a group member
 * since the later of the group's row and his own in the group. Departed
 * ones are already subtracted (container_member_ids knows them).
 *
 * @return array<int, string> user id → since
 */
function container_members_since(PDO $pdo, int $containerId): array
{
    $since = [];
    $keep = static function (int $id, ?string $at) use (&$since): void {
        $at = $at ?? '9999-12-31 00:00:00';
        if (!isset($since[$id]) || $at < $since[$id]) {
            $since[$id] = $at;
        }
    };
    $stmt = $pdo->prepare('SELECT owner_user_id, created_at FROM map_containers WHERE id = ?');
    $stmt->execute([$containerId]);
    $c = $stmt->fetch();
    if ($c === false) {
        return [];
    }
    $keep((int) $c['owner_user_id'], $c['created_at']);
    $stmt = $pdo->prepare("SELECT target_id, added_at FROM map_container_members WHERE container_id = ? AND type = 'friend'");
    $stmt->execute([$containerId]);
    foreach ($stmt as $z) {
        $keep((int) $z['target_id'], $z['added_at']);
    }
    $stmt = $pdo->prepare(
        "SELECT gm.user_id, GREATEST(COALESCE(cm.added_at, gm.added_at), COALESCE(gm.added_at, cm.added_at)) AS since
           FROM map_container_members cm
           JOIN map_group_members gm ON gm.group_id = cm.target_id
          WHERE cm.container_id = ? AND cm.type = 'group'"
    );
    $stmt->execute([$containerId]);
    foreach ($stmt as $z) {
        $keep((int) $z['user_id'], $z['since']);
    }
    $members = container_member_ids($pdo, $containerId);
    return array_filter($since, static fn(int $id): bool => in_array($id, $members, true), ARRAY_FILTER_USE_KEY);
}

/** The most senior member of a container apart from the given ones — or null. */
function container_senior_member(PDO $pdo, int $containerId, array $except): ?int
{
    $since = container_members_since($pdo, $containerId);
    foreach ($except as $id) {
        unset($since[(int) $id]);
    }
    if ($since === []) {
        return null;
    }
    uksort($since, static fn(int $a, int $b): int => strcmp($since[$a], $since[$b]) ?: $a <=> $b);
    return (int) array_key_first($since);
}

/** A person's name for a sentence. */
function joint_user_name(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare('SELECT ' . sql_display_name() . ' FROM map_users u WHERE u.id = ?');
    $stmt->execute([$userId]);
    return (string) $stmt->fetchColumn();
}

/**
 * Hand a joint list over to the most senior other member — the whole
 * branch, its fields with it, and the share row so the list stays in the
 * circle. The old owner's points stay his, filed where they are; from now
 * on he reaches the list as every member does.
 *
 * @param ?int $containerId only the share in this container (a departure);
 *             null means every share this owner has the list joint in
 * @return ?array{to:int, to_name:string, lists:int[]} null when nobody is
 *         left to take it — then the caller deletes
 */
function joint_list_hand_over(PDO $pdo, int $ownerId, int $listId, ?int $containerId = null): ?array
{
    if (!share_joint_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, container_id FROM map_container_lists
          WHERE list_id = ? AND user_id = ? AND joint = 1' . ($containerId !== null ? ' AND container_id = ?' : '')
    );
    $stmt->execute($containerId !== null ? [$listId, $ownerId, $containerId] : [$listId, $ownerId]);
    $shares = $stmt->fetchAll();
    if ($shares === []) {
        return null;
    }
    /* IN A LIST SHARE THE HEIR IS WHOEVER HAS WORKED IN IT LONGEST
       (09.10.2026) — a recipient who only looks does not
       inherit a list he never filled. */
    $heir = null;
    foreach ($shares as $f) {
        $cId  = (int) $f['container_id'];
        $heir = list_share_list($pdo, $cId) !== null
            ? list_share_heir($pdo, $cId, $ownerId)
            : container_senior_member($pdo, $cId, [$ownerId]);
        if ($heir !== null) {
            break;
        }
    }
    if ($heir === null) {
        return null;
    }
    $branch = container_branches($pdo, [['list_id' => $listId, 'user_id' => $ownerId]])[$listId] ?? [$listId];
    $name   = joint_list_name($pdo, $listId);
    $space  = implode(',', array_fill(0, count($branch), '?'));
    $pdo->prepare("UPDATE map_lists SET user_id = ?, updated_at = NOW() WHERE id IN ($space) AND user_id = ?")
        ->execute(array_merge([$heir], $branch, [$ownerId]));
    // The root of the branch stands at the heir's top: its old parent was a
    // list of the old owner nobody here sees.
    if (column_exists($pdo, 'map_lists', 'parent_id')) {
        $pdo->prepare('UPDATE map_lists SET parent_id = NULL WHERE id = ?')->execute([$listId]);
    }
    // Out of the old owner's Mappe too (0168): the heir cannot see it.
    if (folders_exist($pdo)) {
        $pdo->prepare("UPDATE map_lists SET folder_id = NULL WHERE id IN ($space)")->execute($branch);
    }
    /* EVERY SHARE OF THE LIST BY THE OLD OWNER FOLLOWS IT — the joint ones
       that made the handover, and any other one he had elsewhere (the demo
       hangs Ben's list in two containers, one of them privately): a share
       row names its list's owner, and that is the heir now. A row left in
       the old owner's name would point at a list he no longer has, and
       container_branches would read an empty branch from it. Where the
       heir is no member, the row falls — a joint one sends the others'
       points home first. */
    $all = $pdo->prepare('SELECT id, container_id, joint FROM map_container_lists WHERE list_id = ? AND user_id = ?');
    $all->execute([$listId, $ownerId]);
    $containers = [];
    foreach ($all->fetchAll() as $f) {
        $cId = (int) $f['container_id'];
        /* THE LIST SHARE GOES WITH THE LIST: the heir owns it now, says
           the chat in the words, and the old owner's points go
           home with him — he leaves the list, he is no recipient of it. */
        if (list_share_list($pdo, $cId) !== null) {
            $pdo->prepare('UPDATE map_container_lists SET user_id = ? WHERE id = ?')->execute([$heir, (int) $f['id']]);
            list_share_hand_over($pdo, $cId, $ownerId, $heir, $name);
            $q = $pdo->prepare('SELECT pl.point_id, pl.list_id, p.user_id FROM map_point_lists pl
                                  JOIN map_points p ON p.id = pl.point_id
                                 WHERE pl.list_id IN (' . $space . ') AND p.user_id = ?');
            $q->execute(array_merge($branch, [$ownerId]));
            joint_points_home($pdo, $listId, $q->fetchAll());
            continue;
        }
        if (container_visible($pdo, $heir, $cId)) {
            $pdo->prepare('UPDATE map_container_lists SET user_id = ? WHERE id = ?')->execute([$heir, (int) $f['id']]);
            $containers[] = $cId;
            continue;
        }
        if ((int) $f['joint'] === 1) {
            $n = joint_unfile_others($pdo, $heir, $listId, (int) $f['id']);
            joint_note($pdo, $ownerId, $n === 0
                ? t('hat die gemeinsame Liste „%s" ausgehängt', $name)
                : t('hat die gemeinsame Liste „%s" ausgehängt — die Punkte der anderen liegen jetzt bei ihnen in einer eigenen Liste „%s"', $name, joint_home_name($name)),
                [$cId]);
        }
        $pdo->prepare('DELETE FROM map_container_lists WHERE id = ?')->execute([(int) $f['id']]);
    }
    $heirName = joint_user_name($pdo, $heir);
    if ($containers !== []) {
        joint_note($pdo, $ownerId, t('hat die Liste „%s" an %s übergeben', $name, $heirName), $containers);
    }
    return ['to' => $heir, 'to_name' => $heirName, 'lists' => $branch];
}

/** A list of a joint branch is being deleted for good — the shares read it. */
function joint_list_deleted_note(PDO $pdo, int $userId, int $listId): void
{
    $shares = joint_shares_of_lists($pdo, [$listId]);
    if ($shares === []) {
        return;
    }
    $rootName = joint_list_name($pdo, (int) reset($shares)['root']);
    joint_note($pdo, $userId,
        t('hat die Liste „%s" gelöscht — die Punkte der anderen liegen jetzt bei ihnen in einer eigenen Liste „%s"', joint_list_name($pdo, $listId), joint_home_name($rootName)),
        array_keys($shares));
}

/**
 * A member who is no longer one takes his points out of the container's
 * joint lists with him (22.09.2026): they are his, and a list he cannot
 * see is no place for them. One line per list about it, in his name.
 */
function joint_unfile_departed(PDO $pdo, int $containerId, array $remaining): void
{
    if (!share_joint_ready($pdo)) {
        return;
    }
    // The joint roots of the container, each with its branch — the points
    // come home per ROOT, the list the member knew by name.
    $q = $pdo->prepare('SELECT list_id, user_id FROM map_container_lists WHERE container_id = ? AND joint = 1');
    $q->execute([$containerId]);
    $shares = $q->fetchAll();
    if ($shares === []) {
        return;
    }
    $branches = container_branches($pdo, $shares);
    $rootOf   = [];
    foreach ($shares as $f) {
        foreach ($branches[(int) $f['list_id']] ?? [] as $id) {
            $rootOf[$id] = (int) $f['list_id'];
        }
    }
    if ($rootOf === []) {
        return;
    }
    $space = implode(',', array_fill(0, count($rootOf), '?'));
    $sql = "SELECT pl.point_id, pl.list_id, p.user_id
              FROM map_point_lists pl
              JOIN map_points p ON p.id = pl.point_id
             WHERE pl.list_id IN ($space)";
    $values = array_keys($rootOf);
    if ($remaining !== []) {
        $sql .= ' AND p.user_id NOT IN (' . implode(',', array_fill(0, count($remaining), '?')) . ')';
        $values = array_merge($values, $remaining);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    /* STILL JOINT ELSEWHERE, THEN IT STAYS (24.09.2026). Finn
       had hung his „Waldbrände" jointly into two shares, the maintainer was in
       both, and left one because the list showed twice — and his burn site
       went home into „Waldbrände (ehemals gemeinsam)", although he went on
       tending that very list through the other share. Leaving a SHARE is
       not leaving a LIST: a point stays wherever its holder still reaches
       the list jointly through another share he is a member of — the rule
       joint_unfile_others() has always applied to unhooking. */
    $elsewhere = [];
    $q = $pdo->prepare('SELECT container_id, list_id, user_id FROM map_container_lists
                         WHERE joint = 1 AND container_id <> ?');
    $q->execute([$containerId]);
    $otherShares = $q->fetchAll();
    if ($otherShares !== []) {
        $otherBranches = container_branches($pdo, $otherShares);
        foreach ($otherShares as $o) {
            foreach (container_member_ids($pdo, (int) $o['container_id']) as $member) {
                foreach ($otherBranches[(int) $o['list_id']] ?? [] as $id) {
                    $elsewhere[(int) $member][(int) $id] = true;
                }
            }
        }
    }

    $perRoot = [];
    foreach ($stmt->fetchAll() as $z) {
        if (isset($elsewhere[(int) $z['user_id']][(int) $z['list_id']])) {
            continue;
        }
        $perRoot[$rootOf[(int) $z['list_id']]][] = $z;
    }
    foreach ($perRoot as $rootId => $gone) {
        foreach (joint_points_home($pdo, (int) $rootId, $gone) as $userId => $n) {
            joint_note($pdo, $userId,
                $n === 1
                    ? t('ist ausgetreten — der eigene Punkt geht mit aus „%s"', joint_list_name($pdo, (int) $rootId))
                    : t('ist ausgetreten — die eigenen %d Punkte gehen mit aus „%s"', $n, joint_list_name($pdo, (int) $rootId)),
                [$containerId]);
        }
    }
}

/** The name of the list a member's points come home to. */
function joint_home_name(string $rootName): string
{
    return t('%s (ehemals gemeinsam)', $rootName);
}

/**
 * POINTS COME HOME INTO A LIST OF THEIR OWN, not under „Ohne Liste"
 * (22.09.2026: „damit man nicht zig Punkte wieder sortieren
 * muss" — like the import, which gets a list of its own so it stays
 * clear what belonged together). Per holder ONE list named after the
 * joint root, „… (ehemals gemeinsam)", in the root's colour and shape;
 * created once and reused as long as it is still a plain leaf. The
 * filing MOVES there — same row, new list —, so a point that also lies
 * elsewhere keeps every other place it had.
 *
 * @param array<array{point_id:int|string,list_id:int|string,user_id:int|string}> $gone
 * @return array<int,int> holder → how many of his filings came home
 */
function joint_points_home(PDO $pdo, int $rootId, array $gone): array
{
    if ($gone === []) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT name, color, symbol FROM map_lists WHERE id = ?');
    $stmt->execute([$rootId]);
    $root = $stmt->fetch() ?: [];
    $name = joint_home_name((string) ($root['name'] ?? ''));
    $withFilter = column_exists($pdo, 'map_lists', 'filter');
    $withParent = column_exists($pdo, 'map_lists', 'parent_id');
    $find = $pdo->prepare(
        'SELECT l.id FROM map_lists l
          WHERE l.user_id = ? AND l.name = ? AND l.deleted_at IS NULL'
        . ($withFilter ? ' AND l.filter IS NULL' : '')
        . ($withParent ? ' AND NOT EXISTS (SELECT 1 FROM map_lists c WHERE c.parent_id = l.id AND c.deleted_at IS NULL)' : '')
        . ' ORDER BY l.id LIMIT 1'
    );
    $has  = $pdo->prepare('SELECT 1 FROM map_point_lists WHERE point_id = ? AND list_id = ?');
    $move = $pdo->prepare('UPDATE map_point_lists SET list_id = ? WHERE point_id = ? AND list_id = ?');
    $drop = $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ? AND list_id = ?');
    $homes  = [];
    $counts = [];
    foreach ($gone as $z) {
        $holder  = (int) $z['user_id'];
        $pointId = (int) $z['point_id'];
        $from    = (int) $z['list_id'];
        if (!isset($homes[$holder])) {
            $find->execute([$holder, $name]);
            $found = $find->fetchColumn();
            $homes[$holder] = $found !== false
                ? (int) $found
                : list_create($pdo, $holder, [
                    'name'   => $name,
                    'color'  => $root['color']  ?? '#336699',
                    'symbol' => $root['symbol'] ?? 'circle',
                ]);
        }
        $has->execute([$pointId, $homes[$holder]]);
        if ($has->fetchColumn() !== false) {
            $drop->execute([$pointId, $from]);
        } else {
            $move->execute([$homes[$holder], $pointId, $from]);
        }
        joint_point_touch($pdo, $pointId);
        $counts[$holder] = ($counts[$holder] ?? 0) + 1;
    }
    return $counts;
}

/** Whether this owner has the list hung in somewhere as a joint one. */
function joint_list_is_hung(PDO $pdo, int $ownerId, int $listId): bool
{
    if (!share_joint_ready($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM map_container_lists WHERE list_id = ? AND user_id = ? AND joint = 1 LIMIT 1');
    $stmt->execute([$listId, $ownerId]);
    return $stmt->fetchColumn() !== false;
}

/**
 * A joint list stops being the group's — unhooked by its sharer, switched
 * back to private rights, or its share dies with the container: the other
 * members' points go back to them, into a list of their own (joint_points_home), because a
 * list they can no longer see is no place for their finds and their finds
 * are theirs. Spared are the members of every OTHER share the list is
 * still joint in ($exceptShareId names the one that is ending): they keep
 * seeing it. The list's own trash goes with the last claim — a private
 * list has none, and a row left there would block a purge nobody could
 * see any more (point_in_joint_trash). THE CHAT LINE IS THE CALLER'S:
 * only it knows the deed, and the sentence has to be one piece.
 *
 * @return int how many filings of the others went
 */
function joint_unfile_others(PDO $pdo, int $ownerId, int $listId, int $exceptShareId): int
{
    $stmt = $pdo->prepare('SELECT container_id FROM map_container_lists WHERE list_id = ? AND joint = 1 AND id <> ?');
    $stmt->execute([$listId, $exceptShareId]);
    $spared = [$ownerId];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cId) {
        $spared = array_merge($spared, container_member_ids($pdo, (int) $cId));
    }
    $spared = array_values(array_unique(array_map('intval', $spared)));

    $branch = container_branches($pdo, [['list_id' => $listId, 'user_id' => $ownerId]])[$listId] ?? [$listId];
    $lists  = implode(',', array_fill(0, count($branch), '?'));
    $people = implode(',', array_fill(0, count($spared), '?'));
    $stmt   = $pdo->prepare(
        "SELECT pl.point_id, pl.list_id, p.user_id FROM map_point_lists pl
           JOIN map_points p ON p.id = pl.point_id
          WHERE pl.list_id IN ($lists) AND p.user_id NOT IN ($people)"
    );
    $stmt->execute(array_merge($branch, $spared));
    $gone = $stmt->fetchAll();
    joint_points_home($pdo, $listId, $gone);
    if (count($spared) === 1 && joint_trash_ready($pdo)) {
        $pdo->prepare("DELETE FROM map_list_trash WHERE list_id IN ($lists)")->execute($branch);
    }
    return count($gone);
}
