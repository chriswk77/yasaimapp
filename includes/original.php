<?php
/**
 * The lifeline: per field, the state before the first foreign change.
 *
 * The model is in migration 0044. Here the four moves:
 *
 *   original_remember()           before a FOREIGN write — anchors touched
 *                               fields, each only the first time
 *   original_maintain()          before an OWN write — releases the anchors
 *                               of the touched fields, because what the
 *                               owner enters themselves is the new original
 *   point_deviations()        what currently deviates from the original,
 *                               per field
 *   original_restore() one field back onto the anchor
 *   original_forget()        accept the foreign change — anchor gone
 *
 * THE FIELD KEYS are the same as with the field times: title, note, position,
 * extra.<Name>. Two namespaces for the same fields would be the place
 * where a field gets forgotten in one of the two.
 */

declare(strict_types=1);

function original_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_points', 'original');
}

/**
 * Which fields an input would REALLY change on this point — compared
 * with the stored state, as anchor keys.
 *
 * Used jointly by remember() and pflegen(), so "touched" is defined only
 * once. Returns per key the OLD value (for the anchor).
 *
 * @return array<string,mixed>  e.g. ['title' => 'alt', 'position' => ['lat'=>..,'lon'=>..]]
 */
function original_touched_fields(PDO $pdo, int $pointId, array $input): array
{
    $stmt = $pdo->prepare('SELECT title, note, lat, lon, extra, outline FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $p = $stmt->fetch();
    if ($p === false) {
        return [];
    }

    $touched = [];

    // THE OUTLINE ANCHORS LIKE THE LOCATION — since the move permission
    // covers the whole geometry (foreign_input_sift, 14.08.2026), a
    // member can drag the area, and exactly such interventions are what
    // the anchor is meant to catch. Compared normalized as in the
    // field-times review: the dragged corner carries more decimal places
    // than the stored one.
    if (array_key_exists('outline', $input)) {
        $oldOutline = point_outline_read($p['outline'] ?? null);
        if (point_outline_write($input['outline']) !== point_outline_write($oldOutline)) {
            $touched['outline'] = $oldOutline;
        }
    }

    if (array_key_exists('title', $input)
        && trim((string) $input['title']) !== (string) ($p['title'] ?? '')) {
        $touched['title'] = $p['title'];
    }
    if (array_key_exists('note', $input)
        && trim((string) $input['note']) !== (string) ($p['note'] ?? '')) {
        $touched['note'] = $p['note'];
    }

    if (array_key_exists('lat', $input) || array_key_exists('lon', $input)) {
        // The same threshold as in point_update(): one millionth of an
        // arc minute is the stored resolution.
        $lat = (float) ($input['lat'] ?? $p['lat']);
        $lon = (float) ($input['lon'] ?? $p['lon']);
        if (abs($lat - (float) $p['lat']) > 1e-6 || abs($lon - (float) $p['lon']) > 1e-6) {
            $touched['position'] = ['lat' => (float) $p['lat'], 'lon' => (float) $p['lon']];
        }
    }

    if (array_key_exists('extra', $input)) {
        $old = json_decode((string) ($p['extra'] ?? ''), true) ?: [];
        foreach ((array) $input['extra'] as $name => $value) {
            $before = (string) ($old[$name] ?? '');
            if (trim((string) $value) !== $before) {
                $touched['extra.' . $name] = $before === '' ? null : $before;
            }
        }
        // Keys the input OMITS although they are stored: for the owner
        // that means "field cleared" (their form sends the whole block),
        // for a stranger such a thing does not even make it through the
        // sieve. That too is a touch.
        foreach ($old as $name => $value) {
            if (!array_key_exists($name, (array) $input['extra']) && (string) $value !== '') {
                $touched['extra.' . $name] = $value;
            }
        }
    }

    return $touched;
}

/**
 * Before a FOREIGN write: anchor the touched fields — each only if it
 * has no anchor yet. Must run BEFORE point_update(), afterwards the old
 * value is gone.
 *
 * Even if the field times discard the change right after, the anchor is
 * correct: its value is then the still-valid state, simply nothing
 * deviates — and the next real change finds it in place.
 */
function original_remember(PDO $pdo, int $pointId, array $input, string $byNick): void
{
    if (!original_ready($pdo)) {
        return;
    }
    $touched = original_touched_fields($pdo, $pointId, $input);
    if ($touched === []) {
        return;
    }

    $stmt = $pdo->prepare('SELECT original FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $raw      = $stmt->fetchColumn();
    $original = is_string($raw) ? (json_decode($raw, true) ?: []) : [];

    $added = false;
    foreach ($touched as $field => $old) {
        if (array_key_exists($field, $original)) {
            continue;
        }
        $original[$field] = ['value' => $old, 'by' => $byNick, 'at' => gmdate('Y-m-d\TH:i:s\Z')];
        $added = true;
    }

    if ($added) {
        $pdo->prepare('UPDATE map_points SET original = ? WHERE id = ?')
            ->execute([json_encode($original, JSON_UNESCAPED_UNICODE), $pointId]);
    }
}

/**
 * Before an OWN write: release the anchors of the touched fields.
 *
 * What the owner enters themselves IS the original — if the old anchor
 * kept standing, the deviation list would show their own improvement as
 * a foreign intervention. Runs in the own PATCH path; on foreign points
 * the WHERE with user_id does not match and nothing happens.
 */
function original_maintain(PDO $pdo, int $userId, int $pointId, array $input): void
{
    if (!original_ready($pdo)) {
        return;
    }
    $stmt = $pdo->prepare('SELECT original FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $raw = $stmt->fetchColumn();
    if (!is_string($raw) || $raw === '') {
        return;
    }
    $original = json_decode($raw, true) ?: [];

    $touched = original_touched_fields($pdo, $pointId, $input);
    $rest     = array_diff_key($original, $touched);
    if (count($rest) === count($original)) {
        return;
    }

    $pdo->prepare('UPDATE map_points SET original = ? WHERE id = ?')
        ->execute([$rest === [] ? null : json_encode($rest, JSON_UNESCAPED_UNICODE), $pointId]);
}

/**
 * What currently deviates from the original among one's own points —
 * one row per field, for the view in the administration.
 *
 * EVEN WITHOUT A RUNNING SHARE: the anchor came into being BECAUSE
 * things were shared. If the list no longer hangs anywhere by now, the
 * deviation is still there and the way back shall stay open. Deleted
 * points stay out — their way back is the trash.
 *
 * @return array[]  per row: point_id, point_title, field, original, now, by, at
 */
function point_deviations(PDO $pdo, int $userId): array
{
    if (!original_ready($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, title, note, lat, lon, extra, outline, original
           FROM map_points
          WHERE user_id = ? AND original IS NOT NULL AND deleted_at IS NULL
          ORDER BY id'
    );
    $stmt->execute([$userId]);

    $out = [];
    foreach ($stmt->fetchAll() as $p) {
        $original = json_decode((string) $p['original'], true) ?: [];
        $extra   = json_decode((string) ($p['extra'] ?? ''), true) ?: [];

        foreach ($original as $field => $anchor) {
            $old = $anchor['value'] ?? null;

            if ($field === 'position') {
                $same = is_array($old)
                    && abs((float) $old['lat'] - (float) $p['lat']) <= 1e-6
                    && abs((float) $old['lon'] - (float) $p['lon']) <= 1e-6;
                $current = ['lat' => (float) $p['lat'], 'lon' => (float) $p['lon']];
            } elseif ($field === 'outline') {
                $current  = point_outline_read($p['outline'] ?? null);
                $same = point_outline_write(is_array($old) ? $old : null)
                       === point_outline_write($current);
            } elseif (str_starts_with((string) $field, 'extra.')) {
                $name   = substr((string) $field, 6);
                $current  = $extra[$name] ?? null;
                $same = (string) ($old ?? '') === (string) ($current ?? '');
            } else {
                $current  = $p[$field] ?? null;
                $same = (string) ($old ?? '') === (string) ($current ?? '');
            }

            if ($same) {
                continue;
            }
            $out[] = [
                /* 'point_title', and the reader says 'by': this row kept
                   its German key and the reader invented 'from' — two
                   halves of the English move that never met. Warnings
                   only, until t() was wrapped around the name and a null
                   became a TypeError: from then on the administration
                   broke off in the middle for everybody with a
                   deviation (23.09.2026, found at Finn's — the page
                   stopped before its scripts, the area list stayed on
                   „wird geholt …", and it looked like a hang). */
                'point_id'    => (int) $p['id'],
                'point_title' => (string) ($p['title'] ?? ''),
                'field'        => (string) $field,
                'original'    => $old,
                'now'       => $current,
                'by'         => (string) ($anchor['by'] ?? ''),
                'at'          => (string) ($anchor['at'] ?? ''),
            ];
        }
    }
    return $out;
}

/**
 * One field back onto its original — in the owner's name, with the field
 * time set to now, so the restoration wins everywhere and syncs through
 * to all devices.
 *
 * THE ANCHOR STAYS PUT: current == original means the deviation has
 * vanished from the list — but the next foreign change shall again be
 * measured against THIS original, not against itself.
 */
function original_restore(PDO $pdo, int $userId, int $pointId, string $field): void
{
    $anchor = original_entry($pdo, $userId, $pointId, $field);
    $old   = $anchor['value'] ?? null;

    if ($field === 'position') {
        if (!is_array($old)) {
            throw new InvalidArgumentException('Zu diesem Anker fehlt der Ort.');
        }
        $data = ['lat' => (float) $old['lat'], 'lon' => (float) $old['lon']];
    } elseif (str_starts_with($field, 'extra.')) {
        $name = substr($field, 6);
        $stmt = $pdo->prepare('SELECT extra FROM map_points WHERE id = ? AND user_id = ?');
        $stmt->execute([$pointId, $userId]);
        $extra = json_decode((string) ($stmt->fetchColumn() ?: ''), true) ?: [];
        if ($old === null || $old === '') {
            unset($extra[$name]);
        } else {
            $extra[$name] = $old;
        }
        $data = ['extra' => $extra];
    } elseif ($field === 'outline') {
        // null means: there was no area — restoring then removes it again
        // (point_update understands null as "outline gone").
        $data = ['outline' => is_array($old) ? $old : null];
    } elseif ($field === 'title' || $field === 'note') {
        $data = [$field => (string) ($old ?? '')];
    } else {
        throw new InvalidArgumentException('Unbekanntes Ankerfeld: ' . $field);
    }

    if (field_times_ready($pdo)) {
        // field_times_now(), not gmdate with ".000": the zero
        // milliseconds would lose by string comparison against a foreign
        // change from the SAME second — exactly the moment you press the
        // button while the other is still typing.
        $data['field_times'] = [$field => field_times_now()];
    }

    point_update($pdo, $userId, $pointId, $data);
}

/**
 * Accept the foreign change: the anchor entry falls, the current state
 * is the new baseline. If no entry remains, the column becomes NULL —
 * "NULL means: no one else has ever written" is to stay true.
 */
function original_forget(PDO $pdo, int $userId, int $pointId, string $field): void
{
    $stmt = $pdo->prepare('SELECT original FROM map_points WHERE id = ? AND user_id = ?');
    $stmt->execute([$pointId, $userId]);
    $raw = $stmt->fetchColumn();
    $original = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    if (!array_key_exists($field, $original)) {
        throw new InvalidArgumentException('Zu diesem Feld gibt es keinen Anker (mehr).');
    }
    unset($original[$field]);

    $pdo->prepare('UPDATE map_points SET original = ? WHERE id = ?')
        ->execute([$original === [] ? null : json_encode($original, JSON_UNESCAPED_UNICODE), $pointId]);
}

/** Load an anchor entry — or fail loudly if it is missing. */
function original_entry(PDO $pdo, int $userId, int $pointId, string $field): array
{
    if (!original_ready($pdo)) {
        throw new InvalidArgumentException('Der Rettungsanker braucht die Migration 0044.');
    }
    $stmt = $pdo->prepare('SELECT original FROM map_points WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$pointId, $userId]);
    $raw = $stmt->fetchColumn();
    $original = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    if (!isset($original[$field])) {
        throw new InvalidArgumentException('Zu diesem Feld gibt es keinen Anker (mehr).');
    }
    return (array) $original[$field];
}
