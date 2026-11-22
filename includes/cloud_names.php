<?php
/**
 * THE NAME OF A PICTURE IN THE CLOUD — derived, never handed out once
 * (04.10.2026: „Würden sich die Namen aktuell halten? … wenn
 * ich ein Label ändere, würde sich das Bild dann gleich mit umbenennen.
 * Wenn ich die Reihenfolge ändere, würden die Nummern dann auch bei den
 * Bildern umbenannt. Das wäre natürlich klasse.").
 *
 *     2026-08-29 Krün wk-00554/
 *         Boletus edulis 2026-08-29 DE,BAY, Krün 01 Hauptmotiv.jpg
 *         Boletus edulis 2026-08-29 DE,BAY, Krün 02 Sporen.jpg
 *         Boletus edulis 2026-08-29 DE,BAY, Krün 03 Sporen.jpg
 *
 * HIS SCHEME, HIS ANSWERS (30.09. and 04.10.2026): the species by the
 * VALID name, as the folders have it, with his open marks put back (cf.,
 * aff., s. l., agg.); the day; the country and the app's three-letter
 * region (HES, BAY, TIR — Austria would have only digits otherwise, see
 * MUNICIPALITY_PARTS); the municipality alone — the closer description
 * mattered only while finds had no GPS; then the picture's place in the
 * gallery, two digits, and its subject name (cloud_name_stems). The
 * find number stands on the FOLDER, not the file (cloud_path_parts), so
 * every find has a folder of its own and two finds never share names.
 *
 * NOTHING IS STORED THAT COULD GO STALE. What a name is made of lives on
 * the find and the pictures; this file computes the wanted name and folder
 * and moves the file there when they differ from where it lies. A label
 * changed, the order dragged, a picture added or deleted (the numbers
 * shift), „cf." taken out, another genus under the microscope, a
 * renumbering of all one's finds — each marks the find due
 * (cloud_names_touch), and the worker brings its pictures in line: right
 * after the request where the server allows it (cloud_names_soon), else
 * in the five-minute bundle. The daily bundle marks every find with a
 * picture in a cloud once more, so whatever no hook saw is caught by
 * tomorrow — a GBIF renaming, say.
 *
 * WHAT MOVES: the photographer's own pictures in his own cloud — voucher
 * photos pushed there (map_photos.cloud_*) and recordings (map_cloud_files)
 * — and every co-owner's COPY in his (map_cloud_copies), under the same
 * name. A picture still waiting on this server has a number but no file
 * to rename. The measure sidecar is written anew under the new name, in
 * the folder's „Bearbeitung" (measure_sidecar_store, from the row — it says the new name inside, and
 * the camera's original). What the camera called the picture stays on the
 * row: map_photos.original_name, map_cloud_files.original_name (0151).
 *
 * NEVER ONTO ANOTHER FILE. pCloud's renamefile REPLACES a namesake in the
 * target without a word (its documentation, read 04.10.2026). So the target
 * folder is listed first: a name held by another picture of this find
 * that is about to move too (two pictures swapping places) is cleared by
 * parking that one under a temporary name; a name held by anything else —
 * a file the user laid there himself — makes this picture „… (2)".
 */

declare(strict_types=1);

/** How long the after-request run may work — the person has their answer already. */
const CLOUD_NAMES_SOON_SECONDS = 20;

/** Failing calls before a find leaves the queue; the daily sweep brings it back tomorrow. */
const CLOUD_NAMES_ATTEMPTS = 5;

/** The subject name that leads every gallery, and so the numbers (cloud_names_gallery_order). */
const CLOUD_NAMES_LEAD = 'hauptmotiv';

/** The suffix a parked picture wears for the moment of a swap. */
const CLOUD_NAMES_PARKED = ' umbenennen';

/**
 * A FOCUS STACK SAYS SO IN ITS NAME (27.10.2026: „gestackte Bilder
 * im Dateinamen auszeichnen … stacked oder sowas dazu schreiben. Ja, da bin
 * ich dafür") — for a voucher it is part of the truth about the picture.
 * The microscope window uploads a stack as „Mikroskop <moment> stacked <n>"
 * (assets/js/microscope.js); the renaming reads the token off the original
 * name, which every row keeps (0151), and writes „stacked" after the
 * subject name: „… Krün 02 Sporen stacked.jpg". A fixed, untranslated word
 * on purpose: the original name is made in the uploader's language, and
 * the mark must be found whatever that was. The number of frames stays on
 * the original name; in the file name it would only make it longer.
 */
const CLOUD_NAMES_STACKED = 'stacked';

/** Was this picture a focus stack — its original name carries the token? */
function cloud_name_stacked(?string $originalName): bool
{
    return $originalName !== null
        && preg_match('/(^|\s)' . CLOUD_NAMES_STACKED . ' \d+(\.[a-z0-9]+)?$/iu', trim($originalName)) === 1;
}

/**
 * Mark a find: its pictures' names may have changed. Cheap and quiet — a
 * hook in a save must never be the reason the save fails.
 */
function cloud_names_touch(PDO $pdo, int $pointId): void
{
    if ($pointId <= 0 || !table_exists($pdo, 'map_cloud_names_due')) {
        return;
    }
    try {
        $pdo->prepare('INSERT INTO map_cloud_names_due (point_id, due_at) VALUES (?, NOW(6))
                       ON DUPLICATE KEY UPDATE due_at = NOW(6)')->execute([$pointId]);
    } catch (PDOException $e) {
        error_log('cloud_names_touch (' . $pointId . '): ' . $e->getMessage());
        return;
    }
    cloud_names_soon($pdo, $pointId);
}

/**
 * RIGHT AFTER THE ANSWER, where the server can: fastcgi_finish_request()
 * hands the person their answer and lets this request go on renaming —
 * a label changed is a name changed within seconds, not five minutes. The
 * session is closed first, or the person's next click would wait for its
 * lock. Without PHP-FPM the function does not exist, and the five-minute
 * bundle does it; on the command line (tests, cron) never. MEASURED LIVE
 * on 04.10.2026: at manitu it exists — the maintainer changed a subject name
 * and pCloud showed the new file name at once, „nicht erst nach Minuten".
 */
function cloud_names_soon(PDO $pdo, int $pointId): void
{
    static $points = null;
    if (PHP_SAPI === 'cli' || !function_exists('fastcgi_finish_request')) {
        return;
    }
    if ($points === null) {
        $points = [];
        register_shutdown_function(static function () use ($pdo, &$points): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            ignore_user_abort(true);
            fastcgi_finish_request();
            $deadline = microtime(true) + CLOUD_NAMES_SOON_SECONDS;
            foreach (array_keys($points) as $id) {
                if (microtime(true) >= $deadline) {
                    break;
                }
                try {
                    cloud_names_claimed($pdo, (int) $id, $deadline);
                } catch (Throwable $e) {
                    error_log('cloud_names_soon (' . $id . '): ' . $e->getMessage());
                }
            }
        });
    }
    $points[$pointId] = true;
}

/** How many finds wait for their names. */
function cloud_names_pending(PDO $pdo): int
{
    return table_exists($pdo, 'map_cloud_names_due')
        ? (int) $pdo->query('SELECT COUNT(*) FROM map_cloud_names_due')->fetchColumn()
        : 0;
}

/**
 * EVERY find with a picture in a cloud, marked once more — the daily
 * safety net, and what migration 0151 does once for the whole stock.
 * INSERT IGNORE: a find already waiting keeps its place in the line.
 */
function cloud_names_all(PDO $pdo): int
{
    require_once __DIR__ . '/cloud.php';
    if (!cloud_names_ready($pdo)) {
        return 0;
    }
    // A find with nothing but other files has a folder to keep in line too.
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO map_cloud_names_due (point_id, due_at)
         SELECT point_id, NOW(6) FROM map_cloud_files
          UNION SELECT point_id, NOW(6) FROM map_photos WHERE cloud_fileid IS NOT NULL'
        . (table_exists($pdo, 'map_point_files') ? ' UNION SELECT point_id, NOW(6) FROM map_point_files' : '')
    );
    $stmt->execute();
    return $stmt->rowCount();
}

/**
 * Work through the waiting finds until the deadline — the five-minute
 * bundle's job. $open is the test's door to a fake cloud, as at the
 * copies (cloud_copies_run).
 *
 * @return array{points: int, renamed: int, failed: int, open: int}
 */
function cloud_names_run(PDO $pdo, float $deadline, ?callable $open = null): array
{
    require_once __DIR__ . '/cloud.php';
    $sum = ['points' => 0, 'renamed' => 0, 'failed' => 0, 'open' => 0];
    if (!cloud_names_ready($pdo)) {
        return $sum;
    }
    // Fewest failures first, then oldest — a find whose cloud refuses does
    // not stand in front of the others.
    $ids = $pdo->query(
        'SELECT point_id FROM map_cloud_names_due
          WHERE claimed_at IS NULL OR claimed_at < NOW() - INTERVAL 10 MINUTE
          ORDER BY attempts, due_at LIMIT 500'
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        if (microtime(true) >= $deadline) {
            break;
        }
        $r = cloud_names_claimed($pdo, (int) $id, $deadline, $open);
        if ($r !== null) {
            $sum['points']++;
            $sum['renamed'] += $r['renamed'];
            $sum['failed']  += $r['failed'];
        }
    }
    $sum['open'] = cloud_names_pending($pdo);
    return $sum;
}

/**
 * One find, CLAIMED first — the after-request run and the bundle may reach
 * for the same find, and two of them renaming one folder would fight over
 * every name. A claim older than ten minutes was a run that died.
 *
 * @return ?array{renamed: int, failed: int, done: bool} null when another run has it
 */
function cloud_names_claimed(PDO $pdo, int $pointId, float $deadline, ?callable $open = null): ?array
{
    $claim = $pdo->prepare('UPDATE map_cloud_names_due SET claimed_at = NOW()
                             WHERE point_id = ? AND (claimed_at IS NULL OR claimed_at < NOW() - INTERVAL 10 MINUTE)');
    $claim->execute([$pointId]);
    if ($claim->rowCount() !== 1) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT due_at, attempts FROM map_cloud_names_due WHERE point_id = ?');
    $sum = ['renamed' => 0, 'failed' => 0, 'done' => true];

    /* AGAIN WHILE IT WAS MARKED AGAIN. A recording is filed, and its
       subject name follows in the next request a moment later — that
       second mark finds this run holding the claim. So when the find was
       marked while it ran, it runs once more at once, as long as there is
       time; otherwise the next run takes it up. */
    for ($round = 0; $round < 3; $round++) {
        $stmt->execute([$pointId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $sum;
        }
        try {
            $r = cloud_names_point($pdo, $pointId, $deadline, $open);
        } catch (Throwable $e) {
            error_log('cloud_names_point (' . $pointId . '): ' . $e->getMessage());
            $r = ['renamed' => 0, 'failed' => 1, 'done' => true];
        }
        $sum['renamed'] += $r['renamed'];
        $sum['failed']  += $r['failed'];
        $sum['done']     = $r['done'];
        if (!$r['done'] || $r['failed'] > 0) {
            break;
        }
        $gone = $pdo->prepare('DELETE FROM map_cloud_names_due WHERE point_id = ? AND due_at = ?');
        $gone->execute([$pointId, $row['due_at']]);
        if ($gone->rowCount() === 1) {
            return $sum;
        }
        if (microtime(true) >= $deadline) {
            break;
        }
    }
    $r = $sum;

    if ($r['done'] && $r['failed'] === 0) {
        // Marked again and no time left: the newer due time stands, and the
        // next run takes it up.
        $pdo->prepare('UPDATE map_cloud_names_due SET claimed_at = NULL WHERE point_id = ?')->execute([$pointId]);
    } elseif (!$r['done']) {
        $pdo->prepare('UPDATE map_cloud_names_due SET claimed_at = NULL WHERE point_id = ?')->execute([$pointId]);
    } elseif ((int) $row['attempts'] + 1 >= CLOUD_NAMES_ATTEMPTS) {
        $pdo->prepare('DELETE FROM map_cloud_names_due WHERE point_id = ?')->execute([$pointId]);
    } else {
        $pdo->prepare('UPDATE map_cloud_names_due SET claimed_at = NULL, attempts = attempts + 1 WHERE point_id = ?')
            ->execute([$pointId]);
    }
    return $r;
}

/**
 * The species as a file name starts: the valid genus and epithet the
 * folders use (cloud_find_from_point), with the open marks of the title
 * put back where they are written — „cf." and „aff." between the names,
 * „s. l.", „agg." and „s. str." behind. „Boletus spec." for a genus alone,
 * „Unbestimmt" without one, as the folders say.
 */
function cloud_name_species(PDO $pdo, array $point): string
{
    require_once __DIR__ . '/cloud.php';
    require_once __DIR__ . '/mykis.php';
    /* A POINT OUTSIDE EVERY SPECIES LIST is named by its own name — a burn
       site is „Brandfläche Hagen 2026-08-29 …", not „Unbestimmt …"
       (cloud_list_parts). Without one, its folder's last part. */
    $listed = cloud_list_parts($pdo, $point);
    if ($listed !== null) {
        return trim((string) ($point['title'] ?? '')) ?: $listed[2];
    }
    // „Myxo spec." keeps the words written — a slime mould not yet named, not just anything (cloud_myxo_unnamed).
    if (cloud_myxo_unnamed((string) ($point['title'] ?? ''))) {
        return trim((string) $point['title']);
    }
    $find = cloud_find_from_point($pdo, (int) ($point['user_id'] ?? 0), $point);
    if ($find === null || trim((string) $find['genus']) === '') {
        return 'Unbestimmt';
    }
    $genus   = trim((string) $find['genus']);
    $epithet = trim((string) $find['species']);
    if ($epithet === '') {
        return $genus . ' spec.';
    }
    $marks = mykis_open_marks((string) ($point['title'] ?? ''));
    $front = in_array('cf', $marks, true) ? 'cf. ' : (in_array('aff', $marks, true) ? 'aff. ' : '');
    $back  = in_array('sl', $marks, true) ? ' s. l.'
           : (in_array('agg', $marks, true) ? ' agg.'
           : (in_array('sstr', $marks, true) ? ' s. str.' : ''));
    return $genus . ' ' . $front . $epithet . $back;
}

/**
 * Where and when, as the name carries it: „2026-08-29 DE,BAY, Krün" — the
 * country and the region joined by a comma, the municipality after a comma
 * and a space, exactly as the maintainer wrote his own names for years. A part
 * the find does not know is left out with its comma.
 */
// TWIN: nameDay() and namePlace() in assets/js/finddetails.js write day and place the same way for the export's file name.
function cloud_name_head(string $species, array $point): string
{
    $day   = substr((string) (($point['found_at'] ?? '') ?: ($point['created_at'] ?? '')), 0, 10);
    $codes = implode(',', array_filter([
        mb_strtoupper(trim((string) ($point['country'] ?? ''))),
        mb_strtoupper(trim((string) ($point['region'] ?? ''))),
    ], static fn(string $c): bool => $c !== ''));
    $town  = trim((string) ($point['municipality'] ?? ''));
    $place = $codes !== '' && $town !== '' ? $codes . ', ' . $town : $codes . $town;
    return trim(implode(' ', array_filter([$species, $day, $place], static fn(string $p): bool => $p !== '')));
}

/**
 * The names of a find's pictures, in gallery order — the pure core, held
 * to the examples by tests/cloud_names.php.
 *
 * THE NUMBER IS THE PLACE IN THE GALLERY, counting every picture of the
 * find, the ones still waiting on this server too — so the number in a
 * name is the number one sees, and the Hauptmotiv and the macro pictures
 * he drags to the top stand on top in the folder too.
 *
 * ONE NUMBER, NO COUNTER (04.10.2026, after the first cut wrote
 * „6 Sporen 1": „Diese Doppelnummerierung gefällt mir ja nicht"). Asked
 * between „06 Sporen a", „F Sporen 1", the number alone and the picture's
 * part first, he chose the number alone: no two pictures share a place,
 * so „06 Sporen" and „07 Sporen" are told apart without a second count.
 * TWO DIGITS (three from the hundredth picture), because a listing that
 * sorts letter by letter puts „10" and „11" before „2" — measured in the
 * draft, where exactly that happened.
 *
 * @param array<int, ?string> $labels the subject names in gallery order
 * @param array<int, bool> $stacked which of them are focus stacks (same
 *        keys; missing means no) — „stacked" after the subject name
 * @return array<int, string> the stems, same keys, without extension
 */
function cloud_name_stems(string $head, array $labels, array $stacked = []): array
{
    $width = max(2, strlen((string) count($labels)));
    $stems = [];
    $nr = 0;
    foreach ($labels as $key => $label) {
        $nr++;
        $l = cloud_name_clean((string) $label);
        $tail = str_pad((string) $nr, $width, '0', STR_PAD_LEFT) . ($l === '' ? '' : ' ' . $l)
            . (!empty($stacked[$key]) ? ' ' . CLOUD_NAMES_STACKED : '');
        // NO COMMA BEFORE THE NUMBER (04.10.2026: „Nach der
        // Gemeinde kommt aber kein Komma, sondern gleich die Zahl mit einem
        // Leerzeichen") — the commas belong to the place, the number to
        // the picture.
        $stems[$key] = mb_substr(cloud_name_clean($head) . ' ' . $tail, 0, 200);
    }
    return $stems;
}

/**
 * A piece of a file name: what no file system takes goes — the slash
 * becomes a hyphen („Sporen 10/100" stays readable), the rest of
 * \ : * ? " < > | and control characters vanish — runs of blanks shrink
 * to one, and no dot or blank stands at the end (Windows drops them).
 */
function cloud_name_clean(string $raw): string
{
    $s = str_replace(['/', '\\'], '-', $raw);
    $s = preg_replace('/[\x00-\x1F<>:"|?*]+/u', '', $s) ?? $s;
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    // One spelling of an umlaut leaves this server (cloud_name_key, cloud.php).
    return cloud_name_key(trim($s, " \t."));
}

/** A name that is the wanted one — or the wanted one with „ (2)" because a foreign file held it. */
function cloud_name_fits(string $current, string $stem, string $ext): bool
{
    $dot = $ext === '' ? '' : '.' . $ext;
    return $current === $stem . $dot
        || preg_match('/^' . preg_quote($stem, '/') . ' \(\d+\)' . preg_quote($dot, '/') . '$/u', $current) === 1;
}

/**
 * Bring one find's pictures to their names and folders.
 *
 * @return array{renamed: int, failed: int, done: bool}
 */
function cloud_names_point(PDO $pdo, int $pointId, float $deadline, ?callable $open = null): array
{
    require_once __DIR__ . '/cloud.php';
    require_once __DIR__ . '/photos.php';
    require_once __DIR__ . '/photo_cloud.php';

    $out = ['renamed' => 0, 'failed' => 0, 'done' => true];
    $stmt = $pdo->prepare('SELECT * FROM map_points WHERE id = ? LIMIT 1');
    $stmt->execute([$pointId]);
    $point = $stmt->fetch();
    /* A FIND IN THE TRASH MOVES LIKE ANY OTHER (22.11.2026:
       „Papierkorb-Bilder ziehen mit um"). Until then its pictures stayed
       where they lay — restored, the daily sweep would have named them
       again. But when the folders were rebuilt („Arten/" and „Listen/",
       18.11.2026) that rule left the old top folders standing for the
       sake of thirteen finds nobody was looking at: sixty pictures under
       „Fungi (Pilze)/" and „Unbestimmt/", and the question why the move
       had not finished. The trash is a state of the find, not a second
       place in the cloud; whoever restores it finds its pictures where
       every find's are. */
    if ($point === false) {
        return $out;
    }

    // Every picture of the find, in the gallery's order (photos_reorder's
    // tie-break: number, the photo before the recording, the id).
    $pictures = [];
    $q = $pdo->prepare('SELECT * FROM map_photos WHERE point_id = ?');
    $q->execute([$pointId]);
    foreach ($q as $r) {
        $pictures[] = ['kind' => 'photo', 'row' => $r];
    }
    if (table_exists($pdo, 'map_cloud_files')) {
        $q = $pdo->prepare('SELECT * FROM map_cloud_files WHERE point_id = ?');
        $q->execute([$pointId]);
        foreach ($q as $r) {
            $pictures[] = ['kind' => 'cloud', 'row' => $r];
        }
    }
    /* THE FIND'S OTHER FILES FOLLOW TOO (05.10.2026, point_files.php): its
       „Dateien" folder moves whole into wherever the find now belongs —
       before the pictures, and whether or not the find has any. */
    require_once __DIR__ . '/point_files.php';
    $files = point_files_follow($pdo, $point, $open);
    $out['failed'] += $files['failed'];
    /* AND THE PLATE'S PAGES (22.11.2026, plate_images_follow): they go
       where the maker's pictures of the find lie — so AFTER the pictures,
       at whichever end this function leaves by. A find whose pictures are
       all in place still asks its pages; that is how the pages left behind
       by the rebuilding of the folders come after. */
    $plates = static function () use ($pdo, $point, $open, &$out): array {
        require_once __DIR__ . '/plate_images.php';
        if ($out['done']) {
            $out['failed'] += plate_images_follow($pdo, $point, $open)['failed'];
        }
        return $out;
    };
    if ($pictures === []) {
        return $plates();
    }
    /* IN THE GALLERY'S ORDER, AS IT IS SHOWN — grouped by subject name in
       the holder's own order of names, and within a group as dragged
       (cloud_names_gallery_order). Until 06.10.2026 the numbers followed
       the dragged order alone: Finn uploaded the spores before the
       Hauptmotiv, the gallery showed the Hauptmotiv first, and his file
       „01" was the spores („Das Hauptmotiv ist nicht die Nummer
       1, sondern Nummer 1 ist das Sporenbild"). */
    $holder = $pdo->prepare('SELECT measure_style FROM map_users WHERE id = ?');
    $holder->execute([(int) $point['user_id']]);
    $pictures = cloud_names_gallery_order($pictures,
        measure_style_read($holder->fetchColumn() ?: null)['photo_titles'] ?? []);

    $stems = cloud_name_stems(
        cloud_name_head(cloud_name_species($pdo, $point), $point),
        array_map(static fn(array $p): ?string => photo_title_clean($p['row']['title'] ?? null), $pictures),
        array_map(static fn(array $p): bool => cloud_name_stacked($p['row']['original_name'] ?? null), $pictures)
    );

    // What lies in a cloud, as one list of moves to check — the own
    // pictures, then every co-owner's copy of each under the same name.
    $items = [];
    foreach ($pictures as $i => $p) {
        $r = $p['row'];
        if ($p['kind'] === 'photo') {
            if ((string) ($r['cloud_fileid'] ?? '') === '') {
                continue;   // still on this server — it has its number, no file to name
            }
            $items[] = ['kind' => 'photo', 'id' => (int) $r['id'], 'user' => (int) $r['user_id'],
                'provider' => (string) ($r['cloud_provider'] ?? '') ?: 'pcloud',
                'fileid' => (string) $r['cloud_fileid'], 'folder' => (string) ($r['cloud_folderid'] ?? ''),
                'path' => (string) ($r['cloud_path'] ?? ''), 'name' => $r['cloud_name'] ?? null,
                'stem' => $stems[$i], 'row' => $r];
        } else {
            if ((string) ($r['fileid'] ?? '') === '') {
                continue;
            }
            $items[] = ['kind' => 'cloud', 'id' => (int) $r['id'], 'user' => (int) $r['user_id'],
                'provider' => (string) ($r['provider'] ?? '') ?: 'pcloud',
                'fileid' => (string) $r['fileid'], 'folder' => (string) ($r['folderid'] ?? ''),
                'path' => (string) ($r['path'] ?? ''), 'name' => $r['name'] ?? null,
                'stem' => $stems[$i], 'row' => $r];
        }
        $c = $pdo->prepare('SELECT * FROM map_cloud_copies WHERE origin_kind = ? AND origin_id = ? AND status = "copied"');
        $c->execute([$p['kind'], (int) $r['id']]);
        foreach ($c as $copy) {
            if ((string) ($copy['fileid'] ?? '') === '') {
                continue;
            }
            $items[] = ['kind' => 'copy', 'id' => (int) $copy['id'], 'user' => (int) $copy['user_id'],
                'provider' => (string) ($copy['provider'] ?? '') ?: 'pcloud',
                'fileid' => (string) $copy['fileid'], 'folder' => (string) ($copy['folderid'] ?? ''),
                'path' => (string) ($copy['path'] ?? ''), 'name' => $copy['name'] ?? null,
                'stem' => $stems[$i], 'row' => $copy];
        }
    }

    // One storage at a time — each cloud is its own folder tree.
    $groups = [];
    foreach ($items as $item) {
        $groups[$item['user'] . ':' . $item['provider']][] = $item;
    }
    foreach ($groups as $group) {
        $first = $group[0];
        if (cloud_names_settled($pdo, $point, $group)) {
            continue;
        }
        $s = $open !== null ? $open($first['user'], $first['provider'])
                            : cloud_storage_for($pdo, $first['user'], $first['provider']);
        if ($s === null) {
            continue;   // no cloud to reach — nothing can be named there now
        }
        $r = cloud_names_group($pdo, $s, $point, $group, $deadline);
        $out['renamed'] += $r['renamed'];
        $out['failed']  += $r['failed'];
        if (!$r['done']) {
            $out['done'] = false;
            break;
        }
    }
    return $plates();
}

/**
 * The pictures of a find in the order its gallery shows them — THE TWIN OF
 * subjectGroups() in assets/js/gallerysort.js, which draws that gallery
 * (and the photo window's strip): grouped by subject name, the names in the
 * order of the suggestion list (photos.php, PHOTO_TITLES_MACRO / _MICRO), a name
 * typed freely after them alphabetically, no name last — and the
 * Hauptmotiv before all of them, wherever the list has it
 * (06.10.2026: „dass das Hauptmotiv immer ganz oben ist"); within a group by
 * the dragged place, a voucher photo before a recording, then the id. Two
 * copies, because the names are made on the server and in a cron job while
 * the gallery is drawn in the browser; whoever changes one changes the
 * other — tests/cloud_names.php holds this one to the gallery's rule.
 *
 * @param list<array{kind: string, row: array}> $pictures
 * @param list<string> $suggestions the find holder's subject names, in his order
 * @return list<array{kind: string, row: array}>
 */
function cloud_names_gallery_order(array $pictures, array $suggestions): array
{
    $order = array_map(static fn(string $w): string => mb_strtolower(trim($w)), $suggestions);
    $groups = [];   // name as typed (or '') => pictures
    foreach ($pictures as $p) {
        $name = trim((string) ($p['row']['title'] ?? ''));
        $groups[$name][] = $p;
    }
    $named = array_values(array_filter(array_keys($groups), static fn($k): bool => (string) $k !== ''));
    usort($named, static function ($a, $b) use ($order): int {
        $leadA = mb_strtolower((string) $a) === CLOUD_NAMES_LEAD;
        $leadB = mb_strtolower((string) $b) === CLOUD_NAMES_LEAD;
        if ($leadA !== $leadB) {
            return $leadA ? -1 : 1;
        }
        $ia = array_search(mb_strtolower((string) $a), $order, true);
        $ib = array_search(mb_strtolower((string) $b), $order, true);
        if ($ia !== false && $ib !== false) {
            return $ia <=> $ib;
        }
        if ($ia !== false) {
            return -1;
        }
        if ($ib !== false) {
            return 1;
        }
        return strnatcasecmp((string) $a, (string) $b);
    });
    if (isset($groups[''])) {
        $named[] = '';
    }
    $out = [];
    foreach ($named as $key) {
        $group = $groups[$key];
        usort($group, static fn(array $a, array $b): int =>
            [$a['row']['sort_order'] ?? PHP_INT_MAX, $a['kind'] === 'cloud', (int) $a['row']['id']]
            <=> [$b['row']['sort_order'] ?? PHP_INT_MAX, $b['kind'] === 'cloud', (int) $b['row']['id']]);
        array_push($out, ...$group);
    }
    return $out;
}

/**
 * Where a find belongs in a tree — [the named path or null, the
 * „Unbestimmt" path]. Named: the genus tree. Unnamed: „Unbestimmt", but
 * only for pictures that lie there already; a find that lost its name for
 * a moment keeps them where the last name put them (cloud_refile_due's
 * rule, cloud_names_moves).
 *
 * @return array{0: ?string, 1: string}
 */
function cloud_names_places(PDO $pdo, int $user, array $point): array
{
    $loose = implode('/', cloud_undetermined_parts($point, cloud_find_number($point), $pdo));
    /* „MYXO SPEC." IS NO NAME LOST FOR A MOMENT but a slime mould not yet
       named (cloud_myxo_unnamed, 18.11.2026): its pictures go to the slime
       moulds' „Unbestimmt" even from a genus folder — the „Myxo" of GBIF's
       bacteria had put them under „Fungi/M/Myxo". Handed back as the named
       place, so the rule that keeps a nameless find's pictures where they
       lie does not hold them there. */
    if (cloud_myxo_unnamed((string) ($point['title'] ?? ''))) {
        return [$loose, $loose];
    }
    return [cloud_find_path($pdo, $user, $point), $loose];
}

/** Does this picture have to change folders? */
function cloud_names_moves(array $item, ?string $named, string $loose): bool
{
    $stay = $named === null && !cloud_path_undetermined($item['path']) && $item['path'] !== '';
    $wantPath = $named ?? ($stay ? $item['path'] : $loose);
    return !$stay && $wantPath !== $item['path'];
}

/**
 * ALL RIGHT ALREADY, from the rows alone? Then the cloud is not even
 * opened. Measured 04.10.2026 after the maintainer asked what the daily look
 * at every find costs once his stock is renamed: about three
 * milliseconds a find in the database — but at Drive opening the storage
 * and finding the app's folder are calls to Google, one per find and day.
 * A find whose rows know every name and place, and whose names fit,
 * costs no call anywhere.
 */
function cloud_names_settled(PDO $pdo, array $point, array $items): bool
{
    [$named, $loose] = cloud_names_places($pdo, $items[0]['user'], $point);
    foreach ($items as $item) {
        if ($item['name'] === null || $item['folder'] === '' || cloud_names_moves($item, $named, $loose)
            || !cloud_name_fits((string) $item['name'], $item['stem'],
                (string) pathinfo((string) $item['name'], PATHINFO_EXTENSION))) {
            return false;
        }
    }
    return true;
}

/**
 * The pictures of one find in one cloud — find each one's place, then
 * move what is not there yet, swaps and foreign namesakes included.
 *
 * @return array{renamed: int, failed: int, done: bool}
 */
function cloud_names_group(PDO $pdo, array $s, array $point, array $items, float $deadline): array
{
    $out  = ['renamed' => 0, 'failed' => 0, 'done' => true];
    $user = $items[0]['user'];
    // The app's folder only when something moves — at Drive and S3 asking
    // for it is a call to the provider (cloud_home_resolve).
    $home = null;
    $homeOf = static function () use ($pdo, $user, $s, &$home): string|int {
        return $home ??= cloud_home_resolve($pdo, $user, $s);
    };

    [$named, $loose] = cloud_names_places($pdo, $user, $point);
    $target = null;   // resolved on first need: [folderid, path]
    $resolve = static function (bool $isNamed) use ($pdo, $s, $homeOf, $user, $point, &$target): array {
        $home = $homeOf();
        if ($target === null) {
            if ($isNamed) {
                $t = cloud_target_folder($s, $home, $pdo, $user, $point);
                $target = [(string) $t['folderid'], mb_substr(implode('/', $t['path']), 0, 512)];
            } else {
                $parts = cloud_undetermined_parts($point, cloud_find_number($point), $pdo);
                $target = [(string) storage_path_ensure($s, $home, $parts), mb_substr(implode('/', $parts), 0, 512)];
            }
        }
        return $target;
    };

    // Each item: its current name (asked once where the row never knew
    // it), the folder it belongs in, and whether anything is to do.
    $todo = [];
    foreach ($items as $item) {
        try {
            if ($item['name'] === null || $item['folder'] === '') {
                $meta = storage_file_meta($s, $item['fileid']);
                if ($meta === null) {
                    continue;   // gone from the cloud — nothing to name
                }
                $item['name'] ??= $meta['name'];
                if ($item['folder'] === '') {
                    $item['folder'] = (string) $meta['parent'];
                }
            }
            $item['ext'] = (string) pathinfo((string) $item['name'], PATHINFO_EXTENSION);
            $moves = cloud_names_moves($item, $named, $loose);
            if (!$moves && cloud_name_fits((string) $item['name'], $item['stem'], $item['ext'])) {
                continue;
            }
            [$item['to'], $item['toPath']] = $moves ? $resolve($named !== null) : [$item['folder'], $item['path']];
            $todo[] = $item;
        } catch (RuntimeException $e) {
            error_log('cloud_names_group (' . $item['kind'] . ' ' . $item['id'] . '): ' . $e->getMessage());
            $out['failed']++;
        }
    }
    if ($todo === []) {
        return $out;
    }

    $listings = [];
    $byFile = [];
    foreach ($todo as $n => $item) {
        $byFile[$item['fileid']] = $n;
    }
    $left = [];   // folders something moved out of — tidied at the end
    $into = [];   // folders a picture was named in or moved into — swept at the end

    foreach (array_keys($todo) as $n) {
        if (microtime(true) >= $deadline) {
            $out['done'] = false;
            break;
        }
        $item = $todo[$n];
        try {
            $dot  = $item['ext'] === '' ? '' : '.' . $item['ext'];
            $want = $item['stem'] . $dot;
            $here = cloud_names_listing($s, $item['to'], $listings);
            $holder = $here[cloud_name_key($want)] ?? null;
            if ($holder !== null && $holder !== $item['fileid']) {
                $other = $byFile[$holder] ?? null;
                if ($other !== null && $other > $n) {
                    // A picture of this find that moves on later holds the
                    // name: it steps aside under a name of its own first —
                    // where it lies, which the listing just showed.
                    $todo[$other]['folder'] = $item['to'];
                    $todo[$other] = cloud_names_move($pdo, $s, $todo[$other],
                        $todo[$other]['stem'] . CLOUD_NAMES_PARKED . ' ' . $todo[$other]['id'] . $dot,
                        $item['to'], $item['toPath'], $listings, $s['provider'] === 's3' ? $homeOf() : '', false);
                    $byFile[$todo[$other]['fileid']] = $other;
                } else {
                    // Somebody else's file — this picture gets the next free
                    // „ (2)", never the other's name.
                    for ($k = 2; isset($here[cloud_name_key($item['stem'] . ' (' . $k . ')' . $dot)]); $k++) {
                        // the next free number
                    }
                    $want = $item['stem'] . ' (' . $k . ')' . $dot;
                }
            }
            $from = $item['folder'];
            $todo[$n] = cloud_names_move($pdo, $s, $item, $want, $item['to'], $item['toPath'], $listings,
                $s['provider'] === 's3' ? $homeOf() : '', true);
            if ($from !== '' && $from !== $item['to']) {
                $left[$from] = true;
            }
            $into[(string) $item['to']] = true;
            $out['renamed']++;
        } catch (RuntimeException $e) {
            error_log('cloud_names_group (' . $item['kind'] . ' ' . $item['id'] . '): ' . $e->getMessage());
            $out['failed']++;
        }
    }

    foreach (array_keys($left) as $folder) {
        try {
            storage_prune_empty($pdo, $user, $s, $folder);
        } catch (RuntimeException $e) {
            error_log('cloud_names_group prune: ' . $e->getMessage());
        }
    }
    // Only after a run that came to its end: a picture still parked under
    // its temporary name would make its neighbours' sidecars look paired
    // or not by accident.
    if ($out['done']) {
        foreach (array_keys($into) as $folder) {
            try {
                cloud_names_sweep($s, (string) $folder);
            } catch (RuntimeException $e) {
                error_log('cloud_names_group sweep: ' . $e->getMessage());
            }
        }
    }
    return $out;
}

/**
 * After the renaming: the sidecars in a folder's „Bearbeitung" that no
 * picture of the folder answers to any more go.
 *
 * WHY THEY ARISE (found in the maintainer's pCloud, 22.11.2026: 203 of
 * them, 184 from two days at the microscope). Two things run at the same
 * moment. A request saves a scale and writes the sidecar — it asks the
 * provider for the picture's name, then uploads under it
 * (measure_sidecar_write). The renaming gives that picture its derived
 * name in between: it looks for the old sidecar, finds none yet, and moves
 * on. Then the upload arrives — under the name the picture had a second
 * ago. „Mikroskop 2026-11-22 15-22-27.jpg.messwerte.json" stayed beside
 * „… 18 Sporen.jpg.messwerte.json", and nothing ever looked at it again,
 * because the run's listings are asked once and kept
 * (cloud_names_listing).
 *
 * SO THE FOLDER IS LOOKED AT ONCE MORE, FRESH, when the run is through —
 * not through the kept listings, which are exactly what missed it. What
 * arrives later still is the writer's own to catch
 * (measure_sidecar_settle, photo_cloud.php); the two together close the
 * gap from both ends.
 *
 * ONLY THE JSON SIDECARS, never a patch of „Entfernen" or a mask of
 * „Ebenen": a sidecar is a copy of what the row holds and is written anew
 * at the next change — a patch is pixels and exists once. Those keep to
 * the tidying that runs when a folder empties (storage_prune_empty). And
 * paired by the comparison key (cloud_name_key), so the two spellings of
 * an umlaut do not turn a good sidecar into a stray.
 *
 * Not at S3, which has no folder to ask the state of.
 *
 * @return int how many went
 */
function cloud_names_sweep(array $s, string $folder): int
{
    require_once __DIR__ . '/cloud.php';
    $state = $folder === '' ? null : storage_folder_state($s, $folder);
    if ($state === null) {
        return 0;
    }
    $pictures = [];
    $sub = null;
    foreach ($state['entries'] as $e) {
        if (!$e['folder']) {
            $pictures[] = $e['name'];
        } elseif ($e['name'] === SIDECAR_FOLDER) {
            $sub = $e['id'];
        }
    }
    $inner = $sub === null ? null : storage_folder_state($s, $sub);
    if ($inner === null) {
        return 0;
    }
    $gone = 0;
    foreach (storage_stray_sidecars($inner['entries'], $pictures, true) as $id) {
        storage_file_delete($s, $id);
        $gone++;
    }
    return $gone;
}

/**
 * Move one picture to a name in a folder, write it down on its row, and
 * give it its sidecar under the new name. $final is false for a parked
 * picture: it gets no sidecar yet — its old one is gone, so no stale file
 * keeps the name its neighbour is about to take, and the real one comes
 * with its final name.
 */
function cloud_names_move(PDO $pdo, array $s, array $item, string $name, string $folder, string $path,
                          array &$listings, string|int $home, bool $final): array
{
    $oldName = (string) $item['name'];
    $oldFolder = $item['folder'];
    $oldId = $item['fileid'];

    $newId = storage_file_place($s, $oldId, $folder, $name);

    // The run's picture of the folders follows the move: the old name is
    // free now, the new one held.
    foreach ([$oldFolder, $folder] as $f) {
        if (isset($listings[$f])) {
            $listings[$f] = array_filter($listings[$f], static fn(string $id): bool => $id !== $oldId);
        }
    }
    if (isset($listings[$folder])) {
        $listings[$folder][cloud_name_key($name)] = $newId;
    }

    match ($item['kind']) {
        'photo' => $pdo->prepare('UPDATE map_photos SET cloud_fileid = ?, cloud_folderid = ?, cloud_path = ?, cloud_name = ? WHERE id = ?')
            ->execute([$newId, $folder, $path, mb_substr($name, 0, 255), $item['id']]),
        'cloud' => $pdo->prepare('UPDATE map_cloud_files SET fileid = ?, folderid = ?, path = ?, name = ? WHERE id = ?')
            ->execute([$newId, $folder, $path, mb_substr($name, 0, 255), $item['id']]),
        default => $pdo->prepare('UPDATE map_cloud_copies SET fileid = ?, folderid = ?, path = ?, name = ? WHERE id = ?')
            ->execute([$newId, $folder, $path, mb_substr($name, 0, 255), $item['id']]),
    };

    // At S3 the key IS the id, and the previews this app drew mirror it.
    if ($s['provider'] === 's3' && $newId !== $oldId) {
        foreach ([320, 2048] as $edge) {
            try {
                s3_move($s['token'], s3_preview_key((string) $home, $oldId, $edge),
                    s3_preview_key((string) $home, $newId, $edge));
            } catch (RuntimeException $e) {
                // Not every picture has both — a missing preview is drawn anew.
            }
        }
    }

    // The sidecar: the old one goes, a new one is written for the new
    // name from the row — it names the picture inside, so a moved copy of
    // the old one would lie. Copies carry none (their measures live on the
    // origin). The old one is looked for in „Bearbeitung" and, from before
    // 10.11.2026, beside the picture (SIDECAR_FOLDER in cloud.php) — both
    // through the run's listings, so a folder is asked once, not per picture.
    if ($item['kind'] !== 'copy') {
        try {
            $sideName = cloud_name_key(measure_sidecar_name($oldName));   // the listings' key
            if ($oldFolder !== '') {
                $sub = cloud_names_sidecar_folder($s, (string) $oldFolder, $listings);
                foreach ($sub === null ? [(string) $oldFolder] : [(string) $oldFolder, $sub] as $where) {
                    $side = cloud_names_listing($s, $where, $listings)[$sideName] ?? null;
                    if ($side !== null) {
                        storage_file_delete($s, $side);
                        unset($listings[$where][$sideName]);
                    }
                }
            }
            if ($final) {
                cloud_names_sidecar($pdo, $s, $item, $newId);
            }
        } catch (RuntimeException $e) {
            error_log('cloud_names_move sidecar (' . $item['kind'] . ' ' . $item['id'] . '): ' . $e->getMessage());
        }
        /* THE PATCHES OF „ENTFERNEN" FOLLOW BY NAME (11.11.2026) — and the
           mask of „Ebenen" with them: they are
           pixels, not a description, so they cannot be written anew like
           the sidecar — each one is renamed after the picture's new name
           and goes into the new folder's „Bearbeitung". Also on the way
           through a temporary name: a patch left under the old name would
           belong to whichever picture takes that name next. */
        try {
            if ($oldFolder !== '') {
                $sub = cloud_names_sidecar_folder($s, (string) $oldFolder, $listings);
                $moving = [];
                foreach ($sub === null ? [] : cloud_names_listing($s, $sub, $listings) as $fileName => $fid) {
                    $patch = develop_patch_split((string) $fileName);
                    if ($patch !== null && $patch['picture'] === cloud_name_key($oldName)) {
                        // A patch keeps its id; a layer mask („ebene", cloud.php) has only the picture's name.
                        $moving[(string) $fileName] = [$fid, $patch['kind'] === 'ebene' ? layer_mask_name($name)
                            : develop_patch_name($name, $patch['id'])];
                    }
                }
                if ($moving !== []) {
                    $into = (string) $folder === (string) $oldFolder ? $sub
                        : ($listings['sub:' . $folder] = (string) storage_sidecar_folder($s, $folder, true));
                    foreach ($moving as $fileName => [$fid, $to]) {
                        $placed = storage_file_place($s, $fid, $into, $to);
                        unset($listings[$sub][$fileName]);
                        if (isset($listings[$into])) {
                            $listings[$into][cloud_name_key($to)] = $placed;
                        }
                    }
                }
            }
        } catch (RuntimeException $e) {
            error_log('cloud_names_move patches (' . $item['kind'] . ' ' . $item['id'] . '): ' . $e->getMessage());
        }
    }

    $item['fileid'] = $newId;
    $item['folder'] = $folder;
    $item['path']   = $path;
    $item['name']   = $name;
    return $item;
}

/**
 * The files of a folder, name => id, asked once per run. A folder whose
 * pictures all move holds them all: one listing instead of one per picture
 * — the first renaming of the whole stock is thousands of moves.
 */
function cloud_names_listing(array $s, string $folder, array &$listings): array
{
    if (!isset($listings[$folder])) {
        /* UNDER THE COMPARISON KEY (22.11.2026, cloud_name_key): what this
           list is asked is always „is that name taken?" and „where is the
           file of that name?" — and a name taken in the other spelling of
           an umlaut is taken. Asked byte for byte, a picture got the name
           of a file lying there spelt the other way, the two stood side by
           side looking alike, and an old sidecar or patch was not found
           under its picture's name. */
        $listings[$folder] = [];
        foreach (storage_folder_files($s, $folder) as $name => $f) {
            $listings[$folder][cloud_name_key((string) $name)] = (string) $f['fileid'];
        }
    }
    return $listings[$folder];
}

/**
 * The „Bearbeitung" folder in a folder, asked once per run like the
 * listings — null where there is none. Kept under its own key beside them.
 */
function cloud_names_sidecar_folder(array $s, string $folder, array &$listings): ?string
{
    $key = 'sub:' . $folder;
    if (!array_key_exists($key, $listings)) {
        $found = storage_sidecar_folder($s, $folder, false);
        $listings[$key] = $found === null ? null : (string) $found;
    }
    return $listings[$key];
}

/**
 * The sidecar of a renamed picture, written from its row as everywhere.
 *
 * FROM THE ROW AS IT IS NOW, not as the run read it at its start
 * (22.11.2026): a find with forty pictures is renamed over many seconds,
 * and a scale saved meanwhile is in the database but not in the copy the
 * run carries. Written from that copy, the new sidecar would say less than
 * the row — and replace the one the save itself just wrote. One SELECT per
 * renamed picture; the row the run carries stays the fallback.
 */
function cloud_names_sidecar(PDO $pdo, array $s, array $item, string $fileid): void
{
    $r = $item['row'];
    $table = $item['kind'] === 'photo' ? 'map_photos' : ($item['kind'] === 'cloud' ? 'map_cloud_files' : null);
    if ($table !== null && isset($r['id'])) {
        $fresh = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $fresh->execute([(int) $r['id']]);
        $r = $fresh->fetch() ?: $r;
    }
    $scale    = photo_scale_read($r['scale'] ?? null);
    $measures = photo_measures_read($r['measures'] ?? null);
    $title    = photo_title_clean($r['title'] ?? null);
    $media    = photo_media_read($r['media'] ?? null);
    $develop  = photo_develop_read($r['develop'] ?? null);
    if ($scale === null && $measures === [] && $title === null && $media === null && $develop === null) {
        return;
    }
    $original = $item['kind'] === 'photo' ? ($r['original_name'] ?? null) : ($r['original_name'] ?? $r['name'] ?? null);
    measure_sidecar_write($s, $fileid, $scale, $measures,
        isset($r['width']) && $r['width'] !== null ? (int) $r['width'] : null,
        isset($r['height']) && $r['height'] !== null ? (int) $r['height'] : null,
        $title, $original === null ? null : (string) $original, $media, $develop);
}
