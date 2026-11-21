<?php
/**
 * Field times: per field one modification time, the younger wins — and what
 * loses is preserved instead of thrown away.
 *
 * THE CASE. In the forest a point is renamed, without network; the change
 * waits in the phone's queue. At home the same point gets a note on the
 * desktop. When the phone comes online in the evening, it used to send the
 * whole record — including the old, empty note. The afternoon's note was
 * gone, silently.
 *
 * THE RULE, PER FIELD:
 *
 *   zeit     when the device changed this field (its clock)
 *   gesehen  which state of the field the device knew at the time
 *   alt      the stored time of the field
 *
 *   alt missing        → apply                        (first fine time)
 *   gesehen == alt     → apply                        (clean follow-on write)
 *   zeit > alt         → apply, preserve OLD value    (younger wins)
 *   otherwise          → leave, preserve NEW value    (older loses)
 *
 * The third line is the core: if the device wins over a state it NEVER SAW
 * (gesehen != alt), then somebody wrote in between — and their version is
 * preserved, not overwritten-and-forgotten. That is why nobody ever loses
 * anything here, no matter how the clocks run.
 *
 * THE TIMES COME FROM THE DEVICE. The server only learns of a change from a
 * dead zone in the evening — by its clock the forest change from 2 pm would
 * be "younger" than the desktop change from 4 pm, merely because it arrived
 * later. The same decision as with found_at: the clock that stood next
 * to the mushroom counts. Skewed clocks decide wrongly at worst, but lose
 * nothing — the losing version lies in map_point_overwritten.
 *
 * AN OLD BROWSER SENDS NO TIMES and gets the behavior of old: its value
 * holds. The field time advances to server time in the process, so the new
 * devices see its change when comparing. And fields that stayed the same
 * are not touched at all — otherwise every save of the whole form would
 * stamp all eight fields to "now", and every still-waiting change from
 * another device would lose against a phantom.
 *
 * MINIMAL BOOKKEEPING: field_times stays NULL until a field is finely
 * changed for the first time. The archive — almost all points — never
 * carries one.
 */

declare(strict_types=1);

/** Are the prerequisites in place? Both arrive with migration 0039. */
function field_times_ready(PDO $pdo): bool
{
    return column_exists($pdo, 'map_points', 'field_times');
}

function overwritten_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'map_point_overwritten');
}

/**
 * Server time in the device format — for changes arriving without their own.
 */
function field_times_now(): string
{
    // gmdate knows no milliseconds; those come from microtime. Rounded to
    // .000 would be fine too, but two legacy writes within the same second
    // should be distinguishable.
    $ms = (int) floor((microtime(true) - floor(microtime(true))) * 1000);
    return gmdate('Y-m-d\TH:i:s') . sprintf('.%03dZ', $ms);
}

/**
 * Only the fixed format "2026-08-11T14:32:05.123Z" is accepted.
 *
 * NOT strtotime and not DateTime: the times are compared as STRINGS, and
 * that is only correct if all are built exactly alike — same width, same
 * time zone (Z), same number of digits. Whatever looks different is treated
 * as "no time", not reinterpreted.
 */
function field_times_valid(mixed $time): bool
{
    return is_string($time)
        && (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $time);
}

/**
 * Screen the submitted changes against the stored state.
 *
 * Called BEFORE the actual field processing in point_update(), because it
 * changes what gets processed: losing fields vanish from $data — a losing
 * location then also triggers no new terrain elevation and no new
 * municipality. Equal values vanish likewise; they are no change.
 *
 * 'field_times' and 'field_seen' are ALWAYS removed from $data, even while
 * the column is still missing (the code goes live before the migration) —
 * they are accompanying data, not field values.
 *
 * EXTRA FIELDS INDIVIDUALLY ("extra.substrat"). Substrate and accompanying
 * vegetation are, to whoever fills them in, two fields — if the phone
 * changes one and the desktop the other, both should stay. A device with
 * field times sends only the changed subfields; here they are mixed into
 * the stored state, and $data['extra'] leaves the screening COMPLETE
 * again — the downstream processing (point_extra_write,
 * point_species_references) remains untouched by it. An empty value means delete.
 *
 * @return array{json: ?string, losing: array<array{field:string, value:mixed, time:?string}>}
 */
function point_field_times_review(PDO $pdo, int $userId, int $pointId, array &$data): array
{
    $times = $data['field_times']  ?? null;
    $seen  = $data['field_seen'] ?? null;
    unset($data['field_times'], $data['field_seen']);

    $empty = ['json' => null, 'losing' => []];
    if (!field_times_ready($pdo)) {
        return $empty;
    }

    $times = is_array($times) ? $times : [];
    $seen  = is_array($seen)  ? $seen  : [];

    require_once __DIR__ . '/find_number.php';
    $numbers = find_number_ready($pdo);
    $stmt = $pdo->prepare(
        'SELECT lat, lon, title, note, extra, found_at, outline, field_times'
        . ($numbers ? ', find_number' : '') . '
           FROM map_points WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$pointId, $userId]);
    $current = $stmt->fetch();
    if (!$current) {
        // If the point does not exist, point_update fails on its own in a
        // moment — just decide nothing here about what does not exist.
        return $empty;
    }

    $old      = json_decode((string) ($current['field_times'] ?? ''), true);
    $old      = is_array($old) ? $old : [];
    $newTimes = $old;
    $moved    = false;
    $losing   = [];
    $now      = field_times_now();

    /**
     * The one decision, the same for every field.
     *
     * @return bool apply?
     */
    $decide = function (string $field, mixed $newValue, mixed $oldValue)
        use (&$newTimes, &$moved, &$losing, $times, $seen, $old, $now): bool {

        $time = field_times_valid($times[$field] ?? null) ? $times[$field] : null;

        if ($time === null) {
            // Old browser or missing data: apply as ever, but the time
            // advances to now — otherwise the change would stay invisible
            // to every device with field times.
            $newTimes[$field] = $now;
            $moved = true;
            return true;
        }

        $oldTime = $old[$field] ?? null;

        if ($oldTime === null || ($seen[$field] ?? null) === $oldTime) {
            $newTimes[$field] = $time;
            $moved = true;
            return true;
        }

        if (strcmp($time, $oldTime) > 0) {
            // Younger, but over a never-seen state: the old value comes
            // from someone else and is preserved.
            $losing[] = ['field' => $field, 'value' => $oldValue, 'time' => $oldTime];
            $newTimes[$field] = $time;
            $moved = true;
            return true;
        }

        $losing[] = ['field' => $field, 'value' => $newValue, 'time' => $time];
        return false;
    };

    // --- Location: lat and lon are ONE field. Dragging the marker changes both. ---
    if (array_key_exists('lat', $data) || array_key_exists('lon', $data)) {
        $coords = coordinates_read($data['lat'] ?? null, $data['lon'] ?? null);
        // Do NOT intercept unreadable coordinates here — point_update
        // throws the fitting exception itself in a moment, and the same one
        // for old and new clients alike.
        if ($coords !== null) {
            $same = abs((float) $current['lat'] - (float) $coords[0]) <= 1e-6
                   && abs((float) $current['lon'] - (float) $coords[1]) <= 1e-6;

            if ($same) {
                // Not moved: taking lat/lon out spares the map-sheet
                // arithmetic and the elevation logic. The accuracy stays in
                // — the same spot may have been measured better.
                unset($data['lat'], $data['lon']);
            } elseif (!$decide('position',
                ['lat' => (float) $coords[0], 'lon' => (float) $coords[1]],
                ['lat' => (float) $current['lat'], 'lon' => (float) $current['lon']])) {
                // The location loses — and with it everything describing
                // the measurement at this spot. A GPS accuracy for the old
                // location would be a number without a referent.
                unset($data['lat'], $data['lon'], $data['accuracy_m'],
                      $data['elevation_ellipsoid_m'], $data['elevation_m'], $data['elevation_accuracy_m']);
            }
        }
    }

    // --- The simple fields: same comparison, same decision. ---
    $simple = [
        'title'       => static fn($w) => mb_substr(trim((string) $w), 0, 200),
        'note'       => static fn($w) => point_note($w),
        'found_at' => static fn($w) => point_find_datetime($w),
    ];
    // The find number by hand — a field like the title (point 3 of the
    // numbers, 26.09.2026).
    if ($numbers) {
        // Compared in the series' own spelling, as point_update stores it:
        // „wk-01" against a stored „wk-00001" is no change.
        $ownPattern = find_number_pattern($pdo, $userId);
        $simple['find_number'] = static function ($w) use ($ownPattern): ?string {
            $w = trim((string) ($w ?? ''));
            if ($w !== '' && $ownPattern !== null) {
                $w = find_number_normalize($ownPattern, $w);
            }
            return $w === '' ? null : mb_substr($w, 0, 80);
        };
    }
    foreach ($simple as $field => $norm) {
        if (!array_key_exists($field, $data)) {
            continue;
        }
        $new = $norm($data[$field]);
        $existing = $norm($current[$field]);
        if ($new === $existing) {
            unset($data[$field]);
        } elseif (!$decide($field, $new, $existing)) {
            unset($data[$field]);
        }
    }

    // --- Lists: the assignment as a whole. ---
    if (array_key_exists('lists', $data)) {
        $new = array_map('intval', (array) $data['lists']);
        sort($new);
        $new = array_values(array_unique($new));

        $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ? ORDER BY list_id');
        $stmt->execute([$pointId]);
        $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($new === $existing) {
            unset($data['lists']);
        } elseif (!$decide('lists', $new, $existing)) {
            unset($data['lists']);
        }
    }

    // --- Outline: normalized via the write path, then compared. ---
    if (array_key_exists('outline', $data)) {
        $new = point_outline_write($data['outline']);
        $existing = point_outline_write(point_outline_read($current['outline'] ?? null));

        if ($new === $existing) {
            unset($data['outline']);
        } elseif (!$decide('outline',
            $new === null ? null : json_decode($new, true),
            $existing === null ? null : json_decode($existing, true))) {
            unset($data['outline']);
        }
    }

    // --- Extra fields, individually. ---
    if (array_key_exists('extra', $data) && (is_array($data['extra']) || is_object($data['extra']))) {
        $incoming = (array) $data['extra'];
        $oldExtra = json_decode((string) ($current['extra'] ?? ''), true);
        $oldExtra = is_array($oldExtra) ? $oldExtra : [];
        $merged   = $oldExtra;

        // WHETHER THE CLIENT SENDS ONLY CHANGED SUBFIELDS OR ALL no longer
        // matters here: equal values fall out through the comparison,
        // what is missing stays. An old client sending the whole block
        // thereby, however, no longer deletes anything by OMITTING a field
        // — it deletes via an empty value, and that is exactly what the
        // form sends for a cleared field. Behavior unchanged.
        foreach ($incoming as $name => $value) {
            $name = mb_substr(trim((string) $name), 0, 60);
            if ($name === '') {
                continue;
            }
            $key      = 'extra.' . $name;
            $new      = trim((string) $value);
            $existing = trim((string) ($oldExtra[$name] ?? ''));

            if ($new === $existing) {
                continue;
            }
            if ($decide($key, $new === '' ? null : $new, $existing === '' ? null : $existing)) {
                if ($new === '') {
                    unset($merged[$name]);
                } else {
                    $merged[$name] = $new;
                }
            }
        }

        // ALWAYS WRITE BACK, even if nothing got through. Writing extra
        // pulls the reference table along (point_species_references), and that
        // converts old storage forms into the new one as it goes — a side
        // effect tests/species_links.php explicitly insists on.
        // Triggering it only on "really changed" would mean legacy stock
        // stays put exactly when nobody changes anything anymore. The field
        // times do not move from this — they only advance on real changes.
        $data['extra'] = $merged;
    }

    return [
        'json'       => $moved ? json_encode($newTimes, JSON_UNESCAPED_UNICODE) : null,
        'losing' => $losing,
    ];
}

/**
 * Record the losing versions — inside the caller's transaction, because
 * they belong to exactly the UPDATE that displaced them.
 */
function overwritten_remember(PDO $pdo, int $userId, int $pointId, array $losing): void
{
    if ($losing === [] || !overwritten_ready($pdo)) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO map_point_overwritten (user_id, point_id, field, value, value_time)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($losing as $entry) {
        $stmt->execute([
            $userId,
            $pointId,
            $entry['field'],
            json_encode($entry['value'], JSON_UNESCAPED_UNICODE),
            $entry['time'],
        ]);
    }
}

/**
 * What nobody has seen yet — youngest first, capped.
 *
 * The cap is not thrift but a statement: whoever has 200 open entries has a
 * different problem than a full window. Displaying and confirming happens
 * in rounds.
 */
function overwritten_pending(PDO $pdo, int $userId, int $atMost = 100): array
{
    if (!overwritten_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT id, point_id, field, value, value_time, created_at
           FROM map_point_overwritten
          WHERE user_id = ? AND seen_at IS NULL
          ORDER BY id DESC
          LIMIT ' . max(1, $atMost)
    );
    $stmt->execute([$userId]);

    $out = [];
    foreach ($stmt as $row) {
        $out[] = [
            'id'            => (int) $row['id'],
            'point_id'      => (int) $row['point_id'],
            'field'          => (string) $row['field'],
            'value'          => json_decode((string) $row['value'], true),
            'value_time'     => $row['value_time'],
            'created_at' => $row['created_at'],
        ];
    }
    return $out;
}

function overwritten_count(PDO $pdo, int $userId): int
{
    if (!overwritten_ready($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM map_point_overwritten WHERE user_id = ? AND seen_at IS NULL'
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** Confirm as seen — only then does an entry vanish from the bar. */
function overwritten_seen(PDO $pdo, int $userId, array $ids): int
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn($i) => $i > 0));
    if ($ids === [] || !overwritten_ready($pdo)) {
        return 0;
    }
    $space = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare(
        "UPDATE map_point_overwritten SET seen_at = NOW()
          WHERE user_id = ? AND seen_at IS NULL AND id IN ($space)"
    );
    $stmt->execute(array_merge([$userId], $ids));
    return $stmt->rowCount();
}
