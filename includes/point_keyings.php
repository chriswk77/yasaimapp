<?php
/**
 * The identification walks stored at a find (migration 0170).
 *
 * 14.10.2026: the species of a find and the identification keys
 * are joined — a key button behind „Art" opens the identification module
 * for the find, every way walked there (through several books, dead ends
 * and rejected results included) is kept at the find, and the confirmed way
 * stands under „Art" in the point window. The module itself is not part
 * of the application (includes/modules.php); this file is the find's side
 * of the contract between the two.
 *
 * ONE WALK PER FIND AND PERSON. Each co-owner keeps his own — nobody's way
 * is overwritten by another's, and the circle can talk about where they
 * parted. Everybody who sees the find sees every walk (point_view_scope);
 * only the keyer writes his own, and only while he may read the keys at all
 * (the hook walk_writer). Writing a walk changes nothing else of the find: taking a
 * confirmed species over as the find's „Art" is an ordinary change of the
 * point (PATCH points.php, with all its rights), done by the browser.
 */

declare(strict_types=1);

require_once __DIR__ . '/point_owners.php';

/** Whether migration 0170 has run — the code goes live before it. */
function point_keyings_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_point_keyings');
}

/** The longest typed way kept — a line per book, with room for a remark each. */
const POINT_KEYING_WAY_MAX = 4000;

/** Whether the typed way can be stored — migration 0198. */
function point_keying_way_ready(PDO $pdo): bool
{
    return point_keyings_ready($pdo) && column_exists($pdo, 'map_point_keyings', 'way');
}

/** Whether a walk's confirmed ways can be kept as lines — migration 0199. */
function point_keying_lines_ready(PDO $pdo): bool
{
    return point_keyings_ready($pdo) && column_exists($pdo, 'map_point_keyings', 'walk_lines');
}

/**
 * The confirmed ways of a walk as the lines to keep — asked of the walk's
 * tool (includes/modules.php, hook walk_lines), which alone can read a walk.
 *
 * WHY THE CORE KEEPS THEM (20.11.2026, migration 0199): a walk is lead ids,
 * and without the tool it says nothing. The lines are the record that has
 * to stand without it — „Gröger 2 · Hauptschlüssel: 1a → 5b → Boletus
 * edulis" —, made once when the walk is saved and shown wherever no tool
 * draws the ways itself.
 *
 * Null where no tool answers: then nothing is known, and nothing is written
 * over lines that were made when one was there. An empty text is an answer
 * — the walk has no confirmed way.
 */
function point_keying_lines(PDO $pdo, array $walk): ?string
{
    $lines = module_first('walk_lines', $pdo, $walk);
    if (!is_array($lines)) {
        return null;
    }
    $clean = [];
    foreach ($lines as $line) {
        // One way, one line: whatever the tool sends, no break inside it.
        $line = trim((string) preg_replace('/\s+/u', ' ', (string) $line));
        if ($line !== '') {
            $clean[] = $line;
        }
    }
    return mb_substr(implode("\n", $clean), 0, 20000);
}

/**
 * Every walk at a find, for whoever may see it: one's own as „mine", the
 * others' with their keyer's name. The summary of each walk's ways comes
 * from the identification module (keys_walk_summary), where it knows how —
 * until then null, and the window shows only the confirmed species.
 *
 * @return ?array{ready: bool, point: array{id:int, title:string, number:string}, mine: ?array, others: list<array>}
 */
function point_keyings_for(PDO $pdo, int $userId, int $pointId): ?array
{
    if (point_view_scope($pdo, $userId, $pointId) === null) {
        return null;
    }
    /* THE FIND AS IT IS CALLED TODAY — the identification module names it
       in its head („Bestimmung für Fund Y-10043 Boletus edulis"). */
    require_once __DIR__ . '/find_number.php';
    $q = $pdo->prepare('SELECT title' . (find_number_ready($pdo) ? ', find_number' : '') . ' FROM map_points WHERE id = ?');
    $q->execute([$pointId]);
    $row = $q->fetch() ?: [];
    $point = [
        'id'     => $pointId,
        'title'  => (string) ($row['title'] ?? ''),
        'number' => ($row['find_number'] ?? '') !== '' ? (string) $row['find_number'] : find_number_fallback($pointId),
    ];
    if (!point_keyings_ready($pdo)) {
        return ['ready' => false, 'way_ready' => false, 'point' => $point, 'mine' => null, 'others' => []];
    }
    $typed = point_keying_way_ready($pdo);
    $kept  = point_keying_lines_ready($pdo);
    $stmt = $pdo->prepare(
        'SELECT k.user_id, k.walk, ' . ($typed ? 'k.way' : 'NULL AS way') . ', ' . ($kept ? 'k.walk_lines' : 'NULL AS walk_lines')
            . ', k.confirmed_name, k.confirmed_at, k.updated_at, ' . sql_display_name() . ' AS name
           FROM map_point_keyings k
           JOIN map_users u ON u.id = k.user_id
          WHERE k.point_id = ?
          ORDER BY k.updated_at'
    );
    $stmt->execute([$pointId]);
    $mine = null;
    $others = [];
    foreach ($stmt->fetchAll() as $r) {
        $walk = json_decode((string) $r['walk'], true);
        /* A WALK FROM BEFORE THE LINES gets them now, while a tool is here
           to make them — quietly, without touching updated_at: nothing about
           the walk changed, only its record caught up. Whoever reads the
           ways first does the work once; without a tool the row waits. */
        $lines = $r['walk_lines'];
        if ($kept && $lines === null && is_array($walk)) {
            $lines = point_keying_lines($pdo, $walk);
            if ($lines !== null) {
                $pdo->prepare('UPDATE map_point_keyings SET walk_lines = ?, updated_at = updated_at WHERE point_id = ? AND user_id = ?')
                    ->execute([$lines, $pointId, (int) $r['user_id']]);
            }
        }
        $entry = [
            'user_id'        => (int) $r['user_id'],
            'name'           => (string) $r['name'],
            'walk'           => is_array($walk) ? $walk : null,
            // The walk's confirmed ways as kept lines (0199) — what stands where no tool draws them.
            'walk_lines'     => $lines !== null && $lines !== '' ? (string) $lines : null,
            // The way as its keyer typed it (0198) — beside the walk, never instead of it.
            'way'            => ($r['way'] ?? null) !== null && $r['way'] !== '' ? (string) $r['way'] : null,
            'confirmed_name' => $r['confirmed_name'] !== null ? (string) $r['confirmed_name'] : null,
            'confirmed_at'   => $r['confirmed_at'],
            'updated_at'     => $r['updated_at'],
            // What the walk's tool makes of it (includes/modules.php, walk_summary) — null where no tool is here.
            'ways'           => is_array($walk) ? module_first('walk_summary', $pdo, $walk) : null,
        ];
        if ((int) $r['user_id'] === $userId) {
            $mine = $entry;
        } else {
            $others[] = $entry;
        }
    }
    return ['ready' => true, 'way_ready' => $typed, 'point' => $point, 'mine' => $mine, 'others' => $others];
}

/**
 * Store the way one typed at a find — „Gröger I, Schlüssel 3 → 12 → 15",
 * a line per book (20.11.2026, migration 0198).
 *
 * WHO MAY: whoever sees the find, his own way and nobody else's — the rule
 * of the walks, and for the same reason: it is a statement about one's own
 * keying, not a change of the find. NOT bound to the identification module
 * (no walk_writer asked here): this is the way of whoever keys in the book
 * itself, and the module is one more hand that may write a walk beside it.
 *
 * AN EMPTY TEXT TAKES THE WAY AWAY, and a row that is left with neither a
 * typed way nor a walk goes with it — an empty row would stand under „Art"
 * as a keyer who said nothing.
 *
 * False where it may not be written: the find out of sight, the migration
 * missing.
 */
function point_keying_way_save(PDO $pdo, array $me, int $pointId, string $way): bool
{
    $userId = (int) $me['id'];
    if (!point_keying_way_ready($pdo) || point_view_scope($pdo, $userId, $pointId) === null) {
        return false;
    }
    // One shape whatever typed it: \n between lines, no blanks at a line's end, none around the whole.
    $way = str_replace(["\r\n", "\r"], "\n", $way);
    $way = trim((string) preg_replace('/[ \t]+$/mu', '', $way));
    $way = mb_substr($way, 0, POINT_KEYING_WAY_MAX);
    $now = date('Y-m-d H:i:s');
    if ($way === '') {
        $pdo->prepare('UPDATE map_point_keyings SET way = NULL, updated_at = ? WHERE point_id = ? AND user_id = ?')
            ->execute([$now, $pointId, $userId]);
        $pdo->prepare('DELETE FROM map_point_keyings WHERE point_id = ? AND user_id = ? AND walk IS NULL AND way IS NULL')
            ->execute([$pointId, $userId]);
        return true;
    }
    $pdo->prepare(
        'INSERT INTO map_point_keyings (point_id, user_id, walk, way, created_at, updated_at)
         VALUES (?, ?, NULL, ?, ?, ?)
         ON DUPLICATE KEY UPDATE way = VALUES(way), updated_at = VALUES(updated_at)'
    )->execute([$pointId, $userId, $way, $now, $now]);
    return true;
}

/**
 * Store one's own walk at a find — and, when a species was confirmed on it,
 * that name. False where the walk may not be written: the find out of
 * sight, the keys out of reach, the migration missing.
 */
function point_keying_save(PDO $pdo, array $me, int $pointId, array $walk, ?string $confirmed = null): bool
{
    $userId = (int) $me['id'];
    // A walk is written by a tool, and the tool says for whom (hook walk_writer) — without one, nobody.
    if (!point_keyings_ready($pdo) || module_first('walk_writer', $me) !== true || point_view_scope($pdo, $userId, $pointId) === null) {
        return false;
    }
    $json = json_encode($walk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // A walk is lead ids and a few flags — a megabyte would be no walk.
    if ($json === false || strlen($json) > 1_000_000) {
        return false;
    }
    $name = $confirmed !== null ? mb_substr(trim($confirmed), 0, 255) : null;
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO map_point_keyings (point_id, user_id, walk, confirmed_name, confirmed_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE walk = VALUES(walk),
             confirmed_name = COALESCE(VALUES(confirmed_name), confirmed_name),
             confirmed_at   = COALESCE(VALUES(confirmed_at), confirmed_at),
             updated_at     = VALUES(updated_at)'
    );
    $stmt->execute([$pointId, $userId, $json, $name ?: null, $name ? $now : null, $now, $now]);
    /* THE LINES GO WITH THE WALK (migration 0199): made anew on every save,
       because every save may have confirmed, rejected or walked on. A
       statement of its own, so the walk is safe before the tool is asked —
       and survives a tool that fails at the lines. */
    if (point_keying_lines_ready($pdo)) {
        try {
            $lines = point_keying_lines($pdo, $walk);
        } catch (Throwable $e) {
            error_log('walk_lines (' . $pointId . '/' . $userId . '): ' . $e->getMessage());
            $lines = null;
        }
        if ($lines !== null) {
            $pdo->prepare('UPDATE map_point_keyings SET walk_lines = ? WHERE point_id = ? AND user_id = ?')
                ->execute([$lines, $pointId, $userId]);
        }
    }
    return true;
}

/**
 * THE WAYS A PLATE MAY CARRY (09.11.2026: „für die Tafeln dann
 * auch die Möglichkeit geben, den Schlüsselweg mit einzufügen … und eben
 * die Bemerkung dann zu dem Buch oder nur die Bemerkung mit Buchname oder
 * Schlüsselname"). Only CONFIRMED ways — the one per book that led to the
 * result; detours, dead ends and rejected results are the key's to show,
 * not a plate's. Every keyer's, marked `mine` and `by`: the plate offers
 * one's own (assets/js/findkeying.js, keyingBlocks), the others ride along
 * for the day the circle's ways are wanted too.
 *
 * Delivered WITH THE FIND (find_cockpit → point.keyings), because a plate
 * draws synchronously, in the editor and in print, from the find object.
 * `key_id` is the key the way ends in — the plate remembers the way by it.
 *
 * @return list<array{key_id:?string, source:?string, book:?string, key:?string,
 *   keys:list<array{key_id:string, title:?string}>,
 *   steps:list<array{key_id:?string, label:string, sure:bool}>, result:?string,
 *   remarks:list<array{key_id:string, key:?string, text:string}>, mine:bool, by:string}>
 */
function point_keyings_plate(PDO $pdo, int $userId, int $pointId): array
{
    $all = point_keyings_for($pdo, $userId, $pointId);
    if ($all === null || !$all['ready']) {
        return [];
    }
    $out = [];
    foreach ([$all['mine'], ...$all['others']] as $entry) {
        if ($entry === null) continue;
        foreach ((array) ($entry['ways'] ?? []) as $way) {
            if (empty($way['confirmed'])) continue;
            $out[] = [
                'key_id'  => $way['key_id'] ?? null,
                'source'  => $way['source'] ?? null,
                'book'    => $way['book'] ?? null,
                'key'     => $way['last_key'] ?? $way['key'] ?? null,
                'keys'    => $way['keys'] ?? [],
                'steps'   => $way['steps'],
                'result'  => $way['result'],
                'remarks' => $way['remarks'] ?? [],
                'mine'    => (int) $entry['user_id'] === $userId,
                'by'      => $entry['name'],
            ];
        }
    }
    return $out;
}
