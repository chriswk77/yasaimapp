<?php
/**
 * Import — processing and view (Mein Bereich → Import).
 *
 * ONE FILE PICKER, THE ORIGIN IS DETECTED. Nobody has to know what their
 * export is called internally — they upload the file, and the registry
 * (import_sources) asks each source in turn: do you recognize this? Every
 * source stands there exactly once: detection, reader, applier and its two
 * views (preview, report). A new source (GPX, KML, whatever an app spits
 * out) is: one includes/<source>.php with detecting, reading and applying,
 * two render functions here, one registry entry.
 *
 *   kml       KML/KMZ from Google Earth, My Maps and many map apps
 *   takeout   Google Maps — the Takeout ZIP of the saved places
 *   fungi     the CSV export of the "Meine Pilze" app
 *   gpx       waypoints from GPS devices and outdoor apps
 *   geojson   GeoJSON from web maps, QGIS and converters
 *   csv       arbitrary CSV tables, with an assignment step (csv.php)
 *
 * EVERY IMPORT IS A BRACKET (migration 0045): one list "Import <date>",
 * the marker on every new point, one row in the overview — and a button
 * that takes the whole import back out of the system (points into the
 * trash, lists gone).
 *
 * THE FLOW HAS TWO STEPS: uploading computes a dry run and shows what
 * WOULD happen — applying happens only after the second button. In
 * between, the file lies under data/import/, that is, outside the web
 * and outside the photo quota (which counts only data/users/). Exactly
 * one per user: a new upload replaces the old one, applying and
 * discarding clear it away. If it stays behind (browser closed, power
 * gone), the next visit finds it again and shows the preview once more —
 * nothing is lost, nothing happens unasked.
 */

declare(strict_types=1);

require_once __DIR__ . '/takeout.php';
require_once __DIR__ . '/meinepilze.php';
require_once __DIR__ . '/gpx.php';
require_once __DIR__ . '/kml.php';
require_once __DIR__ . '/geojson.php';
require_once __DIR__ . '/csv.php';
require_once __DIR__ . '/imports.php';

/**
 * The registry. 'detect' looks into the file (never at the name —
 * browsers and operating systems rename), 'read' delivers the raw data,
 * 'import' takes (PDO, userId, data, wirklich) and delivers the
 * report — as a dry run the same calculation as in earnest, that is the
 * agreement. The order here is the checking order.
 *
 * HERE THE FORMAT MAY LEAD, and in the instructions it may not
 * (25.08.2026, in two sentences that draw the line exactly:
 * „was erkannt wird, ist dann schon das Format … das ist dann ein
 * technisch korrekter Hinweis" — but „wenn man aufklappt, will man erst
 * mal wissen, wie das bei seiner eigenen App funktioniert").
 *
 * These names answer „what did you recognize in my file", and the honest
 * answer to that IS the format — the program is only where it probably
 * came from, and the parenthesis says so. The fold-outs answer a
 * different question, „how do I get the file out of MY program", and
 * they therefore begin with the program. Same subject, two questions,
 * two orders.
 */
function import_sources(): array
{
    return [
        // KML stands BEFORE the Takeout, and that is not cosmetics: a KMZ
        // is also a ZIP, and the Takeout detection settles for the ZIP
        // signature. If it came first, it would gobble up every KMZ — and
        // reading would then run against a ZIP without CSVs, into the void.
        'kml' => [
            'name'       => 'KML/KMZ (Google Earth, My Maps)',
            'detect'   => static function (string $path): bool {
                $head = (string) file_get_contents($path, false, null, 0, 512);
                if (str_starts_with($head, "PK\x03\x04")) {
                    // KMZ: a ZIP with a .kml lying inside.
                    $zip = new ZipArchive();
                    if ($zip->open($path) !== true) {
                        return false;
                    }
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), '.kml')) {
                            $zip->close();
                            return true;
                        }
                    }
                    $zip->close();
                    return false;
                }
                return str_contains($head, '<kml');
            },
            'read'      => 'kml_read',
            'import' => 'kml_import',
            'preview'   => 'import_kml_preview',
            'report'    => 'import_kml_report',
        ],
        'takeout' => [
            'name'       => 'Google Maps (Takeout)',
            'detect'   => static fn(string $path): bool =>
                // A ZIP starts with "PK\x03\x04" — nothing more is needed;
                // whether usable CSVs lie inside, the reading itself says.
                file_get_contents($path, false, null, 0, 4) === "PK\x03\x04",
            'read'      => 'takeout_read',
            'import' => 'takeout_import',
            'preview'   => 'import_takeout_preview',
            'report'    => 'import_takeout_report',
        ],
        'fungi' => [
            'name'       => 'Meine Pilze (App)',
            'detect'   => static function (string $path): bool {
                $head = (string) file_get_contents($path, false, null, 0, 512);
                return str_contains($head, 'Wiss.Name') && str_contains($head, ';');
            },
            'read'      => 'pilze_read',
            'import' => 'pilze_import',
            'preview'   => 'import_pilze_preview',
            'report'    => 'import_pilze_report',
        ],
        'gpx' => [
            'name'       => t('GPX (GPS-Geräte und Karten-Apps)'),
            'detect'   => static fn(string $path): bool =>
                // The root is called <gpx ...> — it sits in the first few
                // hundred bytes, after the XML declaration and any comments.
                str_contains((string) file_get_contents($path, false, null, 0, 512), '<gpx'),
            'read'      => 'gpx_read',
            'import' => 'gpx_import',
            'preview'   => 'import_gpx_preview',
            'report'    => 'import_gpx_report',
        ],
        'geojson' => [
            'name'       => t('GeoJSON (Web-Karten und Konverter)'),
            'detect'   => static function (string $path): bool {
                // A JSON object, and "Feature" or "features" near the start
                // — the reading itself checks more closely. Do not just look
                // for '{': otherwise this source would snatch any arbitrary
                // JSON and report it as broken GeoJSON.
                $head = ltrim((string) file_get_contents($path, false, null, 0, 512));
                return str_starts_with($head, '{')
                    && (str_contains($head, '"Feature') || str_contains($head, '"features"'));
            },
            'read'      => 'geojson_read',
            'import' => 'geojson_import',
            'preview'   => 'import_geojson_preview',
            'report'    => 'import_geojson_report',
        ],
        // The catch-all source — ALL THE WAY AT THE END: it takes any text
        // table, so it may only check once all more precise ones (the
        // Pilze CSV!) have waved off. 'interpret' is its special hook: the
        // columns have arbitrary names, the human assigns them in the preview.
        'csv' => [
            'name'       => t('CSV-Tabelle (beliebige Spalten)'),
            'detect'   => static function (string $path): bool {
                $head = (string) file_get_contents($path, false, null, 0, 4096);
                if ($head === '' || str_contains($head, "\0")) {
                    return false;   // empty or binary
                }
                $firstChar = substr(ltrim($head), 0, 1);
                if (in_array($firstChar, ['<', '{', '['], true)) {
                    return false;   // XML or JSON — not our department
                }
                $rows = preg_split('~\r\n|\r|\n~', $head) ?: [];
                return count($rows) >= 2 && preg_match('~[;,\t]~', $rows[0]) === 1;
            },
            'read'      => 'csv_read',
            'interpret'     => 'csv_interpret',
            'import' => 'csv_import',
            'preview'   => 'import_csv_preview',
            'report'    => 'import_csv_report',
        ],
    ];
}

/** Which source recognizes this file — or null. */
function import_detect(string $path): ?string
{
    foreach (import_sources() as $sourceKey => $q) {
        if (($q['detect'])($path)) {
            return $sourceKey;
        }
    }
    return null;
}

/** Where a user's waiting file lies. The extension is deliberately
 *  meaningless — what it is, the detection says, not the name. */
function import_file_path(int $userId, int $slot = 0): string
{
    return PROJECT_PATH . '/data/import/' . $userId . '-pending'
        . ($slot > 0 ? '-' . $slot : '') . '.dat';
}

/**
 * THE QUEUE OF UPLOADED FILES (25.08.2026: several at once,
 * „pro Datei eine eigene Liste, eine eigene Rücknahme").
 *
 * WHY A QUEUE AND NOT A BATCH. The way through is upload → preview →
 * apply, and the preview is not decoration: for a CSV it is where the
 * columns are assigned, and that assignment belongs to ONE file. Several
 * files therefore do not become one big import but a row of ordinary
 * ones — the first is previewed and applied, then the next steps up. Each
 * gets its own list and its own undo, exactly as before; only the
 * carrying-in happens once for all of them.
 *
 * The list lives beside the files as JSON, because the ORIGINAL NAME
 * cannot survive in the file name: the staged file is called
 * „12-pending-2.dat" on purpose — whatever a browser hands over is a
 * stranger's text and has no business being a path.
 *
 * @return array<int, array{slot:int, name:string}>
 */
function import_queue(int $userId): array
{
    // Only entries whose file really lies there — a run that broke off
    // half-way must not leave a phantom in the list.
    return array_values(array_filter(import_queue_raw($userId), static fn($r): bool =>
        is_file(import_file_path($userId, (int) $r['slot']))));
}

/**
 * The list as it stands, WITHOUT asking whether the files are there.
 *
 * IT MATTERS WHICH OF THE TWO ONE ASKS. Applying archives the file
 * first and takes the entry out afterwards — in between, the head has no
 * file any more, the filtered list therefore begins at the NEXT one, and
 * a blind array_shift removed exactly the file that had not had its turn
 * (caught in the browser, 25.08.2026: after the first import the second
 * one had vanished from the queue while its file still lay there).
 * Whoever changes the list reads it raw.
 */
function import_queue_raw(int $userId): array
{
    $file = import_queue_path($userId);
    $rows = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    if (!is_array($rows)) {
        return [];
    }
    return array_values(array_filter($rows, static fn($r): bool =>
        is_array($r) && isset($r['slot'])));
}

function import_queue_path(int $userId): string
{
    return PROJECT_PATH . '/data/import/' . $userId . '-pending.json';
}

/** @param array<int, array{slot:int, name:string}> $rows */
function import_queue_write(int $userId, array $rows): void
{
    $file = import_queue_path($userId);
    if ($rows === []) {
        if (is_file($file)) unlink($file);
        return;
    }
    file_put_contents($file, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE));
}

/** The file being worked on right now — the head of the queue. */
function import_current(int $userId): ?array
{
    return import_queue($userId)[0] ?? null;
}

/** Done with THIS one: it leaves the list by its slot, the next steps up. */
function import_queue_drop(int $userId, int $slot): void
{
    import_queue_write($userId, array_filter(import_queue_raw($userId),
        static fn($r): bool => (int) $r['slot'] !== $slot));
}

/** Everything a user has lying in the staging area — files and list. */
function import_clear(int $userId): void
{
    foreach (import_queue($userId) as $row) {
        $file = import_file_path($userId, (int) $row['slot']);
        if (is_file($file)) unlink($file);
    }
    // The single slot of the days before the queue, and any leftover
    // whose entry the list had already lost.
    for ($slot = 0; $slot <= 40; $slot++) {
        $file = import_file_path($userId, $slot);
        if (is_file($file)) unlink($file);
    }
    import_queue_write($userId, []);
}

/** The storage directory, with its own web block as with the photos. */
function import_directory_secure(): string
{
    $path = PROJECT_PATH . '/data/import';
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
    $lock = $path . '/.htaccess';
    if (!is_file($lock)) {
        file_put_contents($lock, "Deny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n");
    }
    return $path;
}

/** None of the exports gets bigger — these are text and CSV files. */
const IMPORT_MAX_BYTES = 50 * 1024 * 1024;

/**
 * Must run BEFORE any output. 'section' tells the administration's forms
 * apart from one another — see invitations_content.php.
 *
 * @return array{message: ?string, error: string[], result: ?array, source: string}
 */
function import_process(PDO $pdo, int $userId): array
{
    $state = ['message' => null, 'error' => []];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['section'] ?? '') !== 'import') {
        // The silent failure first, as with the photo upload: if the call
        // exceeds post_max_size, PHP throws the whole body away — $_POST is
        // empty, and with it 'section'. Without this check the user would
        // simply see the empty page again.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST === [] && $_FILES === []
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $state['error'][] = t('Die Datei war zu gross für diesen Server (Grenze: %s).',
                (string) ini_get('post_max_size'));
        }
        return $state;
    }
    csrf_check();

    /* THE PATH IS THE HEAD OF THE QUEUE. Everything below works on ONE
       file — the one being previewed — and the queue says which. Before
       there was a single slot per user; the name of that slot is now the
       first entry's. */
    $head = import_current($userId);
    $path = import_file_path($userId, (int) ($head['slot'] ?? 0));

    try {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'upload':
                if (!imports_ready($pdo)) {
                    throw new InvalidArgumentException(t('Der Import braucht die Migration 0045 — sie ist noch nicht eingespielt.'));
                }
                /* SEVERAL AT ONCE (25.08.2026). PHP hands a
                   multiple field over transposed — one array per
                   property, not one per file —, so the loop reads across
                   them by index. A single-file post still arrives here:
                   then it is a list of one. */
                $files = $_FILES['file'] ?? null;
                $count = is_array($files) && is_array($files['name'] ?? null)
                    ? count($files['name']) : 0;
                if ($count === 0) {
                    throw new InvalidArgumentException(t('Es ist keine Datei angekommen.'));
                }

                import_directory_secure();
                // Whatever still lies around belongs to a run somebody
                // abandoned — a new upload starts a new queue.
                import_clear($userId);

                $queue  = [];
                $slot   = 0;
                $failed = [];
                $doubles = [];
                // What this user has imported before — the yardstick for
                // „already had that one". Empty until migration 0080.
                $hashes = column_exists($pdo, 'map_imports', 'file_hash')
                    ? array_values(array_filter($pdo->query(
                        'SELECT file_hash FROM map_imports WHERE user_id = ' . $userId
                      )->fetchAll(PDO::FETCH_COLUMN)))
                    : [];
                for ($i = 0; $i < $count; $i++) {
                    $name = (string) ($files['name'][$i] ?? '');
                    if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        $failed[] = $name !== ''
                            ? t('„%s": nicht angekommen.', $name)
                            : t('nicht angekommen.');
                        continue;
                    }
                    if ((int) ($files['size'][$i] ?? 0) > IMPORT_MAX_BYTES) {
                        $failed[] = t('„%s" ist grösser als 50 MB — keine der bekannten Ausfuhren ist so gross. Beim Google-Takeout hilft: nur „Gespeichert" auswählen.', $name);
                        continue;
                    }
                    $target = import_file_path($userId, $slot);
                    if (!move_uploaded_file((string) $files['tmp_name'][$i], $target)) {
                        $failed[] = t('„%s" liess sich nicht ablegen.', $name);
                        continue;
                    }
                    /* THE FORMAT IS CHECKED AT THE DOOR, per file: a
                       stack of five with one unreadable among them should
                       say WHICH one, now — not four imports later. */
                    $source = import_detect($target);
                    if ($source === null) {
                        unlink($target);
                        $failed[] = t('„%s": Das Format wurde nicht erkannt. Bekannt sind: %s.',
                            $name, implode(', ', array_column(import_sources(), 'name')));
                        continue;
                    }
                    try {
                        // Read it fully right away — a broken file should
                        // stand out NOW, not only when applying.
                        (import_sources()[$source]['read'])($target);
                    } catch (Throwable $e) {
                        unlink($target);
                        $failed[] = t('„%s": %s', $name, error_for_user($e));
                        continue;
                    }
                    /* THE SAME FILE TWICE IS NO QUESTION
                       (25.08.2026: „wenn du eh prüfst, ob der Inhalt
                       gleich ist, dann stillschweigend ignorieren").
                       Identical CONTENT — the hash, not the name: a file
                       renamed is the same file, and two different files
                       may well share a name. Checked against what this
                       user has already imported AND against this very
                       stack, because the second copy usually comes from
                       the same drop. It is skipped, not refused, and the
                       message says how many — silent means „do not ask",
                       not „hide it". */
                    $hash = hash_file('sha256', $target);
                    if (in_array($hash, $hashes, true)) {
                        unlink($target);
                        $doubles[] = $name;
                        continue;
                    }
                    $hashes[] = $hash;
                    $queue[] = ['slot' => $slot, 'name' => mb_substr($name, 0, 200),
                                'hash' => $hash];
                    $slot++;
                }

                import_queue_write($userId, $queue);
                $state['error'] = array_merge($state['error'], $failed);
                if ($doubles !== []) {
                    /* AS A TOAST, like every answer to a deed
                       (25.08.2026 — and CLAUDE.md says the same): this
                       reports what the upload just did, it does not
                       describe a lasting state. Appended to the message
                       so both travel together; notice() here would print
                       into the page before the redirect. */
                    $state['message'] = trim(($state['message'] ?? '') . ' '
                        . (count($doubles) === 1
                            ? t('„%s" lag schon eingespielt vor und wurde übersprungen.', $doubles[0])
                            : t('%d Dateien lagen schon eingespielt vor und wurden übersprungen: „%s".',
                                count($doubles), implode('", „', $doubles))));
                }
                if ($queue === []) {
                    throw new InvalidArgumentException($doubles !== []
                        ? t('Alles davon war schon eingespielt — nichts Neues dabei.')
                        : t('Keine der Dateien war brauchbar.'));
                }
                if (count($queue) > 1) {
                    $state['message'] = t('%d Dateien liegen bereit — eine nach der anderen, jede wird eine eigene Liste.',
                        count($queue));
                }
                break;

            case 'apply':
                if (!is_file($path)) {
                    throw new InvalidArgumentException(t('Es liegt keine hochgeladene Datei mehr bereit.'));
                }
                $current = import_current($userId);
                $source = import_detect($path);
                if ($source === null) {
                    throw new InvalidArgumentException(t('Das Format wurde nicht erkannt.'));
                }
                $q = import_sources()[$source];
                $data = ($q['read'])($path);
                if (isset($q['interpret'])) {
                    // The CSV source's assignment hook: it must run here AND
                    // before the preview, so both compute the same reading —
                    // otherwise the preview would promise something other
                    // than what applying does.
                    $data = ($q['interpret'])($data);
                }
                /* Local, no longer in $state: since applying leaves
                   through Location, nothing downstream ever reads it —
                   and a value in $state that no view touches looks like
                   an intention. */
                /* WHAT THE HUMAN UNTICKED STAYS OUT — before the
                   importer ever sees it, so it cannot count it. Only
                   „Meine Pilze" offers those boxes, see the function. */
                if ($source === 'fungi') {
                    $data = import_fungi_selection($data, $_POST);
                }
                /* A QUESTION WITHOUT AN ANSWER BLOCKS (26.09.2026:
                   „eine Auswahl muss man treffen"). The browser stops the
                   click already (importrows.js); this is for whatever gets
                   past it. The dry run with the same form counts what is
                   still open — BEFORE the real run has written anything,
                   so an import is never half applied. The file stays, and
                   the preview appears again. */
                $check = ($q['import'])($pdo, $userId, $data, false, null, $_POST);
                $open = (int) ($check['screen']['missing'] ?? 0);
                if ($open > 0) {
                    throw new InvalidArgumentException($open === 1
                        ? t('Bei einem Fund fehlt noch deine Entscheidung.')
                        : t('Bei %d Funden fehlt noch deine Entscheidung.', $open));
                }
                /* The apply form travels along: every entry's decision
                   (import_screen_start names them). */
                $result = ($q['import'])($pdo, $userId, $data, true,
                    (string) ($_POST['name'] ?? ''), $_POST);
                // The original file stays with the import — for a later
                // backfill of columns not mapped today. When the import is
                // taken back, it disappears along with it.
                if (($result['import_id'] ?? null) !== null) {
                    /* THE ORIGINAL IS NOT KEPT (the decision,
                       25.08.2026). It used to move into an archive beside
                       the import, so that columns the import does not map
                       could be backfilled later without a re-import — a
                       possibility never taken up in the year it stood
                       there, while a Takeout may weigh fifty megabytes.
                       Now the import is finished when it is applied; what
                       has to be backfilled is uploaded again. What
                       REMAINS is the file's name and its hash (migration
                       0080) — a few bytes that say which file it was and
                       recognise it when it comes a second time. */
                    unlink($path);
                    // The name it arrived under, kept with the import
                    // (migration 0080) — the source and the day alone do
                    // not tell two takeouts of one afternoon apart.
                    if ($current !== null && column_exists($pdo, 'map_imports', 'file_name')) {
                        // The hash goes with it — it is what the next
                        // upload measures against.
                        $pdo->prepare('UPDATE map_imports SET file_name = ?, file_hash = ?
                                        WHERE id = ? AND user_id = ?')
                            ->execute([$current['name'], $current['hash'] ?? null,
                                       (int) $result['import_id'], $userId]);
                    }
                } else {
                    unlink($path);
                }
                import_queue_drop($userId, (int) ($current['slot'] ?? 0));
                $rest = count(import_queue($userId));

                /* NOTHING IS SHOWN AFTER THE CLICK, EVER
                   (25.08.2026, and the sentence settles a design I had
                   only half-drawn: „Wenn mir vor dem Bestätigen schon
                   etwas angezeigt wird, das ich bestätigt habe, braucht
                   es mir danach nichts mehr angezeigt werden").

                   Every block the applied report used to draw — without
                   a position, without brauchbare Koordinaten, the folder
                   table — stood identically in the preview one click
                   earlier. Showing it again is not a report, it is a
                   repetition, and a page that repeats itself reads as if
                   the click had done nothing. What is genuinely new
                   after the click is a single number, and that flies
                   past as a toast.

                   So: out through Location, always. That is also what
                   makes walking on possible at all — the report WAS the
                   answer to this POST, and as long as we answer, the
                   next file's preview cannot appear. The browser fetches
                   the page anew, the head of the queue gets previewed,
                   and with an empty queue one lands on the upload area
                   with the list of imports underneath. The message
                   travels through the session, the way the cloud round
                   trip already carries its outcome
                   (pages/cloud_return.php).

                   The address is built here and not taken from the view:
                   $target lives in import_render(), which never runs
                   when we leave this way. */
                $created = (int) ($result['created'] ?? 0);
                $fileName = (string) ($current['name'] ?? t('Datei'));
                $_SESSION['import_message'] = ($created === 1
                        ? t('„%s" eingespielt — ein Punkt in „%s".',
                            $fileName, (string) ($result['import'] ?? ''))
                        : t('„%s" eingespielt — %d Punkte in „%s".',
                            $fileName, $created, (string) ($result['import'] ?? '')))
                    . ($rest > 0 ? ' ' . ($rest === 1
                        ? t('Noch %d Datei — die nächste steht bereit.', $rest)
                        : t('Noch %d Dateien — die nächste steht bereit.', $rest)) : '');
                redirect_to(url('pages/administration.php') . '#import');

            /* PAST THIS ONE, ON TO THE NEXT. With a queue this is the
               only honest way out: without it a stack of five whose
               second file one does not want could be left only by
               throwing all five away. */
            case 'skip':
                $skipped = import_current($userId);
                if (is_file($path)) unlink($path);
                import_queue_drop($userId, (int) ($skipped['slot'] ?? 0));
                $rest = count(import_queue($userId));
                $state['message'] = ($skipped !== null
                        ? t('„%s" übersprungen.', (string) $skipped['name'])
                        : t('Übersprungen.'))
                    . ' ' . ($rest > 0
                        ? ($rest === 1 ? t('Noch %d Datei.', $rest) : t('Noch %d Dateien.', $rest))
                        : t('Nichts liegt mehr bereit.'));
                break;

            case 'interpret':
                // Nothing to do: the submitted assignment is read by the
                // interpret hook from $_POST while building the preview — here
                // the action only has to be known so it does not fall
                // through as an error.
                break;

            /* VERWERFEN MEANS THE WHOLE STACK — „skip" is the one-file
               way out. Whoever presses this wants to be rid of what they
               just carried in, not to work through four more. */
            case 'discard':
                $waiting = count(import_queue($userId));
                import_clear($userId);
                $state['message'] = $waiting > 1
                    ? t('Verworfen — alle %d Dateien, nichts wurde eingespielt.', $waiting)
                    : t('Verworfen — nichts wurde eingespielt.');
                break;

            case 'import_name':
                import_rename($pdo, $userId,
                    (int) ($_POST['import'] ?? 0), (string) ($_POST['name'] ?? ''));
                $state['message'] = t('Umbenannt — auch die Liste des Imports heisst jetzt so.');
                break;

            case 'import_remove':
                $trashed = import_remove($pdo, $userId, (int) ($_POST['import'] ?? 0));
                $state['message'] = match (true) {
                    $trashed === 0 => t('Der Import ist zurückgenommen — seine Listen sind weg.'),
                    $trashed === 1 => t('Der Import ist zurückgenommen — sein Punkt liegt im Papierkorb, seine Listen sind weg.'),
                    default       => t('Der Import ist zurückgenommen — %d Punkte liegen im Papierkorb, seine Listen sind weg.', $trashed),
                };
                break;
        }
    } catch (InvalidArgumentException | RuntimeException $e) {
        $state['error'][] = error_for_user($e);
        // Do not leave an unreadable or unrecognized file lying around — it
        // would produce the same error on every page build, with no way
        // out. When applying, though, only on the RuntimeException (file
        // broken): the InvalidArgumentException there is an operating error
        // (say, a missing column assignment) — the file is fine and should
        // stay, so the preview appears again.
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'upload'
            || ($action === 'apply' && $e instanceof RuntimeException)) {
            @unlink($path);
        }
    }

    return $state;
}

/** The view. $state comes from import_process(). */
function import_render(PDO $pdo, int $userId, array $state): void
{
    $target    = url('pages/administration.php') . '#import';
    $sources = import_sources();

    notice('error', $state['error'], true);
    if ($state['message'] !== null) {
        notice('success', $state['message'], true);
    }

    /* Laid down by the apply case before it walked on to the next file,
       and picked up exactly once — a reload must not repeat it. Same
       handle the cloud tab uses for its round trip. */
    if (isset($_SESSION['import_message'])) {
        $carried = (string) $_SESSION['import_message'];
        unset($_SESSION['import_message']);
        notice('success', $carried, true);
    }

    if (!imports_ready($pdo)) {
        notice('hint', t('Der Import braucht die Migration 0045 — sie ist noch nicht eingespielt.'));
    }


    // If a file is waiting (freshly uploaded or from the last visit), every
    // page build recomputes the preview — the same calculation as applying
    // itself, just without writing.
    /* THE HEAD OF THE QUEUE is what gets previewed — one file at a time,
       so a CSV's column assignment stays a question about ONE file. */
    $waiting = import_queue($userId);
    $head    = $waiting[0] ?? null;
    $path = import_file_path($userId, (int) ($head['slot'] ?? 0));
    $previewShown = false;
    if (is_file($path)) {
        $sourceKey = import_detect($path);
        if ($sourceKey !== null) {
            try {
                $q = $sources[$sourceKey];
                /* THE HEAD, IN THE ORDER ONE ASKS
                   (25.08.2026: „ganz oben Import 1 von, und dann gross
                   der Name der Datei"). Where am I in the stack, WHICH
                   file is this — and only then the format, small,
                   because it is a technically correct note and not what
                   one came for. The name used to hang behind „Erkannt:
                   GPX (GPS-Geräte und Karten-Apps) —", where it arrived
                   last and set in the same size as the machinery. */
                if (count($waiting) > 1) {
                    echo '<p class="hint-text small">'
                        . h(t('Import 1 von %d — jeder wird seine eigene Liste und lässt sich einzeln zurücknehmen.', count($waiting)))
                        . '</p>';
                }
                echo '<h3 class="import-file">'
                    . ($head !== null ? h($head['name']) : h(t('Hochgeladene Datei'))) . '</h3>';
                echo '<p class="hint-text small">' . h(t('Erkannt: %s', $q['name'])) . '</p>';
                /* ALWAYS THE SECOND THING, WITHOUT EXCEPTION
                   (25.08.2026: „Dateiname, Importname. Ganz klare
                   Regel"). It used to be printed by each preview, and
                   the CSV therefore put it BELOW its column assignment —
                   in the one place a rule was needed, the page had a
                   special case. Printed here it cannot drift again: it
                   stands under the file name for every format, before
                   anything a particular format has to ask. */
                import_name_field();
                $data = ($q['read'])($path);
                if (isset($q['interpret'])) {
                    $data = ($q['interpret'])($data);
                }
                ($q['preview'])(($q['import'])($pdo, $userId, $data, false), $target,
                    count($waiting));
                $previewShown = true;
            } catch (RuntimeException $e) {
                notice('error', [error_for_user($e)], true);
                @unlink($path);
            }
        } else {
            @unlink($path);
        }
    }

    if (!$previewShown) {
        import_upload_form($target);
    }

    /* THE LIST OF EARLIER IMPORTS STEPS ASIDE WHILE ONE IS RUNNING
       (25.08.2026: „während des ganzen Upload-Vorgangs wird
       die Liste unten ausgeblendet, dass man sich nur noch auf das
       Wesentliche konzentriert"). While a file lies in the queue, every
       question on this page is about THAT file — and a table of a dozen
       earlier imports below it, each with its own „Zurücknehmen", is a
       dozen offers to do something else.

       Bound to the QUEUE, not to the preview: after a file with
       leftovers one reads its block, and the next file is still waiting
       — that is still the same run. Empty queue means done, and then the
       list comes back, with the fresh import at its top. */
    if (import_queue($userId) === []) {
        import_overview($pdo, $userId, $target);
    }
}

/** The single file picker, with the per-source instructions below it. */
function import_upload_form(string $target): void
{
    ?>
<?php /* ONE SENTENCE (25.08.2026: „ganz kurz halten"). What
     stood here — self-recognition, own list, undoable as a whole — was
     true and none of it was needed to press the button; it moved into
     the fold below, where one looks when one wants to know. */ ?>
<p class="hint-text"><?= h(t('Hier kannst du verschiedene Formate hochladen.')) ?></p>

<?php /* ONE DOOR IN FRONT OF SIX (25.08.2026: „dass wir etwas
     Platz sparen"). Six foldable lines under the upload field were six
     headings for one subject, and the list of imports below them was
     pushed off the screen. Now the subject is named once; the six stay
     inside unchanged, because each is the answer to a different „where
     do I get the file".

     AND IT STANDS ABOVE THE AREA, not below it (25.08.2026:
     „erst da drunter kommt die Dropdown-Fläche"). One reads what is
     possible, THEN one drops the file — below the area the answer would
     arrive after the question. It costs one folded line of height,
     because closed the door is one line.

     A section-fold with an id: folds.js gives it a memory, so whoever
     opened it once finds it open. And the short line inside carries the
     two facts the intro gave up, plus the file extensions that used to
     label the area — the import recognizes the format, and each one
     stays undoable as a whole. */ ?>
<details class="section-fold import-formats" id="import-formats">
    <summary><?= icon('fold-triangle', ['class' => 'collapse-triangle']) ?><?= h(t('Welche Formate gehen — und wie komme ich an die Daten?')) ?></summary>

    <?php /* THE FIRST LINE ANSWERS THE FIRST HALF OF THE HEADING — „welche
         Formate gehen" (25.08.2026: „Hier werden alle Formate
         genannt, die unser Import fressen kann"). So it leads with the
         extensions, and it is the ONE place that lists them all. The
         fold-outs below answer the second half — „wie komme ich an die
         Daten" — and therefore begin with the program instead. */ ?>
    <p class="hint-text small">
        <?= t('Ausfuhrdatei: <strong>.zip, .kml, .kmz, .gpx, .geojson oder .csv</strong> — aus Google Maps, Google Earth und My Maps, GPS-Geräten, Karten- und Fund-Apps, Web-Karten und jeder eigenen Tabelle. Woher die Datei stammt, erkennt der Import selbst. Jeder Import bekommt eine eigene Liste „Import %s" und lässt sich unten als Ganzes wieder zurücknehmen.', h(date('d.m.Y'))) ?>
    </p>

<details class="import-instructions">
    <summary><?= icon('fold-triangle', ['class' => 'collapse-triangle import-pfeil']) ?><?= h(t('Google Maps: So kommst du an die Takeout-Datei (.zip)')) ?></summary>
    <ol>
        <li><?= t('%s öffnen und mit dem Google-Konto anmelden, in dem die Karten-Listen liegen.', '<a href="https://takeout.google.com" target="_blank" rel="noopener">takeout.google.com</a>') ?></li>
        <li><?= t('Unter „Neue Ausfuhr erstellen" erst <strong>„Auswahl aufheben"</strong> — sonst kommt das ganze Google-Leben als Archiv.') ?></li>
        <li><?= t('In der Liste nur <strong>„Gespeichert"</strong> ankreuzen. Dahinter stecken die Maps-Listen: Favoriten, „Möchte ich besuchen", eigene Listen.') ?></li>
        <li><?= h(t('„Nächster Schritt", dann so lassen, wie es steht (einmal exportieren, als .zip) und „Ausfuhr erstellen".')) ?></li>
        <li><?= h(t('Nach ein paar Minuten kommt eine E-Mail mit dem Download. Die .zip-Datei unverändert hier hochladen.')) ?></li>
    </ol>
    <p class="hint-text small">
        <?= t('Zwei Dinge kann auch der Import nicht heilen: Google speichert <strong>nicht, wann</strong> ein Ort gesichert wurde — die Punkte kommen ohne Fundzeit an. Und bei Orten aus Googles eigenem Verzeichnis stehen <strong>keine Koordinaten</strong> in der Ausfuhr; solche Einträge werden einzeln aufgelistet, statt ihre Lage zu raten.') ?>
    </p>
</details>

<details class="import-instructions">
    <summary><?= icon('fold-triangle', ['class' => 'collapse-triangle import-pfeil']) ?><?= h(t('Google Earth und My Maps: die Karte ausgeben (.kml/.kmz)')) ?></summary>
    <p class="hint-text small"><?= t('<strong>Google My Maps:</strong> die Karte öffnen, im Menü neben dem Kartennamen (drei Punkte) „In KML/KMZ exportieren", dort „Gesamte Karte" wählen und herunterladen. Ob .kml oder .kmz — beides geht hier.') ?></p>
    <p class="hint-text small"><?= t('<strong>Google Earth im Browser:</strong> links unter „Projekte" das Projekt öffnen, im Menü „…" den Punkt zum Ausgeben als KML wählen.') ?></p>
    <p class="hint-text small"><?= t('<strong>Google Earth Pro (Rechner):</strong> links unter „Orte" mit der rechten Maustaste auf den Ordner, „Ort speichern unter&nbsp;…", als .kmz oder .kml sichern.') ?></p>
    <p class="hint-text small">
        <?= t('Übernommen werden die <strong>Ortsmarken</strong> mit Name, Beschreibung und — wo vorhanden — Zeitstempel und Höhe; Ordner werden eigene Listen. Linien und Flächen bleiben draussen: Das sind Wege und Umrisse, keine Funde.') ?>
    </p>
</details>

<details class="import-instructions">
    <summary><?= icon('fold-triangle', ['class' => 'collapse-triangle import-pfeil']) ?><?= h(t('GPS-Geräte und Karten-Apps: Garmin, Locus, OsmAnd, OruxMaps (.gpx)')) ?></summary>
    <p class="hint-text small"><?= t('<strong>In der App:</strong> der Weg heisst fast überall „Exportieren" oder „Teilen" — bei den Wegpunkten selbst oder beim Ordner, in dem sie liegen. Wo nach dem Format gefragt wird: GPX. Bei OsmAnd liegt er unter „Meine Orte", bei Locus unter „Punkte", bei OruxMaps unter den Wegpunkten.') ?></p>
    <p class="hint-text small"><?= t('<strong>Am Garmin-Gerät:</strong> per USB anschliessen — es meldet sich als Laufwerk. Im Ordner <code>Garmin</code> liegt <code>GPX/</code>, darin die Dateien. Die gewünschte kopieren und hier hochladen.') ?></p>
    <p class="hint-text small">
        <?= t('Übernommen werden die <strong>Wegpunkte</strong> mit Name, Beschreibung, Höhe und dem Zeitpunkt der Markierung als Fundzeit. Tracks und Routen bleiben draussen: Das sind Wege, keine Funde.') ?>
    </p>
</details>

<details class="import-instructions">
    <summary><?= icon('fold-triangle', ['class' => 'collapse-triangle import-pfeil']) ?><?= h(t('Web-Karten, QGIS und Konverter: uMap, geojson.io (.geojson)')) ?></summary>
    <p class="hint-text small"><?= t('<strong>QGIS:</strong> im Ebenenbereich mit der rechten Maustaste auf die Ebene, „Exportieren" → „Objekte speichern als&nbsp;…", als Format GeoJSON wählen.') ?></p>
    <p class="hint-text small"><?= t('<strong>geojson.io:</strong> oben „Save" → „GeoJSON". <strong>uMap:</strong> in den Karteneinstellungen der Bereich zum Teilen und Herunterladen bietet die Daten als GeoJSON an — auch je Ebene.') ?></p>
    <p class="hint-text small"><?= t('<strong>Sonst:</strong> fast jeder Umwandler im Netz gibt GeoJSON aus; wenn deine Anwendung nichts davon kennt, ist eine Tabelle der kürzere Weg (siehe unten).') ?></p>
    <p class="hint-text small">
        <?= t('Übernommen werden die <strong>Punkte</strong>; Titel, Beschreibung und Zeitstempel werden unter ihren üblichen Namen in den Eigenschaften gesucht, alle weiteren Eigenschaften wandern als „Feld: Wert" in die Notiz. Linien und Flächen bleiben draussen.') ?>
    </p>
</details>

<details class="import-instructions">
    <summary><?= icon('fold-triangle', ['class' => 'collapse-triangle import-pfeil']) ?><?= h(t('Alles andere: eine Tabelle mit Kopfzeile (.csv)')) ?></summary>
    <p class="hint-text small"><?= t('Für alles, was oben nicht steht: Tabellen aus Excel, LibreOffice, Datenbanken und Fund-Apps. In Excel „Speichern unter" → <strong>CSV UTF-8</strong>, in LibreOffice „Speichern unter" → <strong>Text CSV</strong>. Wichtig ist nur eine Kopfzeile über den Spalten.') ?></p>
    <p class="hint-text small">
        <?= h(t('Der Import rät, welche Spalte Titel, Koordinaten und Fundzeit trägt; die Zuordnung lässt sich vor dem Einspielen prüfen und ändern. Alle übrigen Spalten wandern als „Spalte: Wert" in die Notiz.')) ?>
    </p>
    <?php /* „Meine Pilze" HAD ITS OWN FOLD HERE UNTIL 25.08.2026 (
         „das ist ein CSV, das fällt unter alles andere"). He is right
         about the instruction: one exports a CSV from the app and
         uploads it, there is nothing else to tell. The IMPORTER keeps
         its own source — it recognizes the app's columns and gets find
         time, accuracy, notes and the resolved scientific names out of
         them, which the generic table cannot. That extra is worth a
         sentence, not a chapter. */ ?>
    <p class="hint-text small">
        <?= h(t('Kommt die Tabelle aus einer Fund-App wie „Meine Pilze", erkennt der Import sie an ihren Spalten und holt Fundzeit, Genauigkeit und Bemerkungen von selbst heraus; die wissenschaftlichen Namen werden gegen die Namensliste aufgelöst, damit Synonyme zusammenfinden.')) ?>
    </p>
</details>

</details>

<form method="post" action="<?= h($target) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="import">
    <input type="hidden" name="action" value="upload">
    <?php /* NO LABEL LINE ABOVE THE AREA (25.08.2026: „das will
         ich sonst nirgends anders sehen"). „Ausfuhrdatei (.zip, .csv,
         …)" stood here and once more inside the fold — the same list
         twice, and the second one was the one with the explanation
         beside it. The area itself is a <label for> and says what it is
         for; the input keeps its accessible name from it. */ ?>
    <div class="field">
        <?php /* A DROP AREA INSTEAD OF THE BARE FILE BUTTON
             (25.08.2026). The browser's own button is the one control in
             the application nobody can style — every browser draws its
             own, with its own words. The area takes a dragged file AND
             opens the picker on click, so the old way is still the way;
             the input itself stays in the form, only hidden, because it
             is what carries the file to the server.

             A LABEL, NOT A DIV: a label pointing at a file input opens
             the picker by itself — with the keyboard too, and with a
             screen reader saying what it is for. No script needed for
             the click, only for the dragging. */ ?>
        <label class="drop-area" id="import-drop" for="import-file" data-send-on-drop>
            <span class="drop-area-main"><?= icon('import') ?><?= h(t('Dateien hierher ziehen')) ?></span>
            <span class="hint-text small"><?= h(t('oder klicken — mehrere auf einmal gehen auch')) ?></span>
            <span class="drop-area-file" id="import-file-name" hidden></span>
        </label>
        <input type="file" id="import-file" name="file[]" multiple class="drop-area-input"
               accept=".zip,.csv,.gpx,.kml,.kmz,.geojson,.json,application/zip,text/csv,application/gpx+xml,application/vnd.google-earth.kml+xml,application/vnd.google-earth.kmz,application/geo+json,application/json" required>
        <div class="button-row right">
            <?php /* STILL HERE, and normally never pressed: droparea.js sends
                 as soon as a file lies there (25.08.2026).
                 This is the way for a browser without modules — and the
                 place from which the script takes its „lädt hoch …". */ ?>
            <button class="button" type="submit"><?= icon('import') ?><?= h(t('Hochladen und prüfen')) ?></button>
        </div>
    </div>
</form>


    <?php
}

/** The imports so far, with the way back. */
function import_overview(PDO $pdo, int $userId, string $target): void
{
    $imports = imports_list($pdo, $userId);
    if ($imports === []) {
        return;
    }
    $names = array_column(import_sources(), 'name');
    $readable = array_combine(array_keys(import_sources()), $names);
    ?>
<div class="column-header">
    <h4><?= h(t('Bisherige Importe')) ?></h4>
</div>
<div class="table-frame">
    <table class="list">
        <tbody>
        <?php foreach ($imports as $i): ?>
            <tr>
                <td>
                    <?php /* TWO LINES (25.08.2026): the name above,
                         the particulars below — side by side the name
                         drowned between file name, source, count and
                         date. */ ?>
                    <strong class="import-row-name"><?= h($i['name']) ?></strong>
                    <span class="item-data import-row-data"><?php if (($i['file_name'] ?? '') !== ''): ?>
                        <?php /* The name it arrived under (migration 0080):
                             „Takeout · 25.08.2026" looks the same for two
                             exports of one afternoon; „nordhang-2024.gpx"
                             says which is which. */ ?>
                        <?= h((string) $i['file_name']) ?> ·
                    <?php endif; ?><?= h($readable[$i['source']] ?? $i['source']) ?> ·
                        <?= h((int) $i['points_new'] === 1 ? t('1 Punkt') : t('%d Punkte', (int) $i['points_new'])) ?><?=
                        (int) $i['points_assigned'] > 0 ? ' ' . h(t('(+%d zugeordnet)', (int) $i['points_assigned'])) : '' ?> ·
                        <?= h(date('d.m.Y H:i', strtotime((string) $i['created_at']))) ?></span>
                </td>
                <td class="button-cell">
                    <?php /* type=button: the button only opens the name prompt
                         (administration.js); what gets submitted is the one
                         hidden form below the table. */ ?>
                    <button type="button" class="button subtle small"
                            data-import-rename="<?= (int) $i['id'] ?>"
                            data-name="<?= h($i['name']) ?>"><?= icon('pencil') ?><?= h(t('Umbenennen')) ?></button>
                    <form method="post" action="<?= h($target) ?>"
                          data-confirm="<?= h((int) $i['points_new'] === 1
                              ? t('Sein Punkt wandert in den Papierkorb, die Listen des Imports verschwinden. Punkte, die es schon vorher gab, bleiben unberührt.')
                              : t('%d Punkte wandern in den Papierkorb, die Listen des Imports verschwinden. Punkte, die es schon vorher gab, bleiben unberührt.', (int) $i['points_new'])) ?>"
                          data-confirm-title="<?= h(t('Import zurücknehmen?')) ?>"
                          data-confirm-button="<?= h(t('Zurücknehmen')) ?>"
                          data-confirm-danger>
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="import">
                        <input type="hidden" name="action" value="import_remove">
                        <input type="hidden" name="import" value="<?= (int) $i['id'] ?>">
                        <button class="button subtle small danger" type="submit"><?= icon('undo') ?><?= h(t('Zurücknehmen')) ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php /* ONE form for all renames: administration.js fills in id and name from the
     prompt and submits. A separate name field per row would be a row of
     open inputs that nobody needs. */ ?>
<form method="post" action="<?= h($target) ?>" id="import-rename-form" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="import">
    <input type="hidden" name="action" value="import_name">
    <input type="hidden" name="import" value="">
    <input type="hidden" name="name" value="">
</form>
    <?php
}

/**
 * The import's name — directly under the file name
 * (25.08.2026: „darunter kommt dann das Inputfeld, Importname").
 *
 * IT REACHES ITS FORM FROM OUTSIDE, with form="…". The form itself
 * stands at the foot with the buttons, because that is where a page
 * concludes — but the field belongs at the head, where one is still
 * deciding what to call the thing. HTML allows exactly this: an input
 * carrying form="id" is submitted with that form, wherever it stands.
 *
 * PRINTED IN THE HEAD, once, for all six formats
 * (25.08.2026). It used to sit inside each preview so that a CSV could
 * put it after its column assignment — well meant, since without
 * latitude and longitude nothing can be applied. But that made the
 * position depend on the format, and the rule is the opposite: file
 * name, import name, then whatever this particular format has to ask.
 */
function import_name_field(): void
{
    ?>
<div class="field import-name-field">
    <label for="import-name"><?= h(t('Importname (frei wählbar — leer wird das Datum verwendet)')) ?></label>
    <?php /* IT SURVIVES THE ROUND TRIP (25.08.2026). Standing above the
         column assignment, the field is now filled BEFORE a step that
         reloads the page — measured: the typed name was gone after
         „Zuordnung anwenden". So the assignment carries it along
         (hidden mirror below, filled by administration.js), and here it
         comes back out. */ ?>
    <input type="text" id="import-name" name="name" maxlength="100"
           form="import-apply-form" value="<?= h((string) ($_POST['name'] ?? '')) ?>"
           placeholder="<?= h(t('z. B. Altbestand vom alten Handy')) ?>">
</div>
    <?php
}

/**
 * The two ways out of the file lying in front — as BARE BUTTONS, so they
 * can stand in the same row as „Einspielen" (25.08.2026).
 *
 * HTML knows no nested forms; it does know form="…". The buttons live
 * wherever the layout needs them and submit the empty carriers of
 * import_exit_forms(), which must be printed once per page. Same trick
 * the dialog footers use to reach a form of their own from outside.
 */
function import_exits(int $waiting): void
{
    /* THE HEAVIEST ONE FARTHEST FROM THE THUMB. The row ends bottom
       right with „Einspielen", the way every concluding row in this
       application does; before it the ways out, and of those „Alle
       verwerfen" first — it is the one that throws the whole stack
       away, so it sits at the far end, not next to the button one is
       aiming at. */
    echo '<button class="button subtle" type="submit" form="import-discard-form">'
       . h($waiting > 1 ? t('Alle verwerfen') : t('Verwerfen')) . '</button>';
    if ($waiting > 1) {
        // THE ONE-FILE WAY OUT (25.08.2026). „Verwerfen" throws the whole
        // stack away — with a queue there has to be a way past a single
        // file, or the second of five could only be got rid of by giving
        // up all five.
        echo '<button class="button subtle" type="submit" '
           . 'form="import-skip-form">' . h(t('Diese Datei überspringen')) . '</button>';
    }
}

/** The carriers for import_exits(): nothing to see, only what is sent.
 *  hidden and not display:none — the attribute says what is meant, and
 *  the project's own „[hidden] { display: none !important }" makes it
 *  stick (trap 1 in CLAUDE.md). */
function import_exit_forms(string $target, int $waiting): void
{
    foreach ($waiting > 1 ? ['skip', 'discard'] : ['discard'] as $action) {
        printf('<form method="post" action="%s" id="import-%s-form" hidden>%s'
             . '<input type="hidden" name="section" value="import">'
             . '<input type="hidden" name="action" value="%s"></form>' . "\n",
            h($target), $action, csrf_field(), $action);
    }
}

/** Name field, apply and the two ways out — all in one row. */
function import_buttons(string $target, int $waiting = 1): void
{
    ?>
<?php /* NO CONFIRMATION BEFORE APPLYING (25.08.2026: „das
     sofort entfernen"). It asked exactly the question the page had just
     answered — the preview above it says what will come in, and the
     button says „Einspielen". And the deed is undoable as a whole: the
     import stands in the list below with its own way back. A prompt
     belongs in front of a loss, not in front of a step one can walk
     back — and one that appears every time teaches the hand to click it
     away, including on the day it would have mattered. */ ?>
<?php /* ALL THREE IN ONE ROW (25.08.2026). They belong
     together: they are the three ways out of this one file — take it,
     step past it, throw the stack away. Standing on two rows they read
     like two decisions.

     HTML KNOWS NO NESTED FORMS, but it does know form="…": the two
     right-hand buttons sit in this row and submit the two empty
     carriers below, and the name field at the head of the preview
     submits with THIS form. Same trick the dialog footers use. */ ?>
<form method="post" action="<?= h($target) ?>" id="import-apply-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="import">
    <input type="hidden" name="action" value="apply">
    <div class="button-row right">
        <?php import_exits($waiting); ?>
        <button class="button" type="submit"><?= h(t('Einspielen')) ?></button>
    </div>
</form>
<?php import_exit_forms($target, $waiting); ?>
    <?php
}

// ---------------------------------------------------------------------------
// Google Maps (Takeout)
// ---------------------------------------------------------------------------

function import_takeout_preview(array $report, string $target, int $waiting = 1): void
{
    ?>
<?php import_takeout_report($report); ?>

<p class="hint-text small">
    <?= h(t('Je Google-Liste entsteht darunter eine eigene. Punkte, die es hier schon gibt (gleicher Titel, gleiche Stelle), werden nicht doppelt angelegt, sondern nur zugeordnet.')) ?>
</p>

<p class="hint-text small">
    <?= t('Die Punkte kommen <strong>ohne Fundzeit</strong> an — Google speichert nicht, wann ein Ort gesichert wurde. Im Zeitfilter liegen sie deshalb alle im Monat des Imports.') ?>
</p>

<?php import_screen_render($report); ?>

<?php import_buttons($target, $waiting ?? 1); ?>
    <?php
}

/**
 * The leading sentence of a report — how many will come in, into which
 * import, and what comes along.
 *
 * THERE IS ONLY THE PREVIEW ANY MORE (25.08.2026: „wenn mir
 * vor dem Bestätigen schon etwas angezeigt wird, braucht es mir danach
 * nichts mehr angezeigt werden"). This used to be printed twice — once
 * before the click as a state, once after it as a toast; applying now
 * leaves through Location and carries its one new fact, the count, in
 * the message.
 *
 * WHAT WAS RECOGNIZED IS THE LOUDEST THING ON THE PAGE. Measured before
 * that change, and it was exactly backwards: „Ohne Position — 3
 * Einträge" sat in <h4> at 16 px, bold, in the full text colour; „2
 * Wegpunkte würden angelegt" sat in .hint-text at 14.4 px, weight 400,
 * in the quiet grey. The page shouted about its failures and whispered
 * its result. Now the count is an h3 — bigger than the h4 of the
 * leftovers — and everything around it goes small underneath.
 */
/**
 * Drop the finds the human unticked in the „Ohne Namenstreffer" list.
 *
 * ONLY FOR MEINE PILZE, and the maintainer named the reason on the day it was
 * built (25.08.2026): „bei den anderen Sachen können ja auch andere
 * Punkte gemeint sein". This source is the only one whose titles ARE
 * species names — its import list is created with rootIsSpecies, and it
 * is the only one that looks a title up in a name list at all. A place
 * mark from Google Earth called „Parkplatz" has no name list to miss, so
 * there would be nothing to decide.
 *
 * CHECKED IS THE DEFAULT (his call, same day), and that decides the
 * mechanics: HTML sends no unchecked box, so the checked ones come back
 * in 'take' and the COMPLETE offer in 'offered' beside them. What is
 * missing from the first but present in the second was unticked. Whoever
 * touches nothing sends the offer back whole and loses nothing — the
 * import then behaves exactly as it did before this existed.
 *
 * A key that nobody offered is ignored on its own: only what stands in
 * 'offered' can be dropped, so a hand-made POST cannot silently thin out
 * a find that never had a box.
 */
function import_fungi_selection(array $data, array $post): array
{
    $offered = array_map('strval', (array) ($post['offered'] ?? []));
    if ($offered === []) {
        return $data;
    }
    $taken = array_map('strval', (array) ($post['take'] ?? []));
    $drop  = array_flip(array_diff($offered, $taken));
    if ($drop === []) {
        return $data;
    }

    require_once __DIR__ . '/takeout.php';   // takeout_key
    $data['points'] = array_values(array_filter(
        $data['points'],
        static fn(array $p): bool => !isset($drop[takeout_key(
            (string) $p['title'], (float) $p['lat'], (float) $p['lon'])])
    ));
    return $data;
}

/**
 * The kinds of conflict an entry can carry, in the order the filters and
 * a row's chips name them — each with its own colour
 * (26.09.2026: „dass wir da mal etwas bunt werden bei den Konflikten";
 * app.css, [data-kind]).
 */
const IMPORT_CONFLICTS = [
    'twice'      => 'Nummer doppelt',
    'finder'     => 'Anderer Finder',
    'determiner' => 'Du bist Bestimmer',
    'number'     => 'Andere Nummer',
    'renamed'    => 'Vielleicht umbenannt',
];

/**
 * EVERY ENTRY OF THE FILE, WHAT HAPPENS TO IT, AND THE WAY OUT OF A
 * CONFLICT BESIDE IT — the screening of includes/import_screen.php, the
 * same table under every preview (25./26.09.2026, over eight
 * drafts: „welcher Punkt angelegt würde und welcher verworfen, sehe ich
 * gar nicht … das muss beim Fund stehen … ein realer Import hat 100
 * Einträge, wie soll man die finden? … ich möchte gleich eine Lösung mit
 * angeboten bekommen").
 *
 * THE CONFLICTS FIRST: the filter „Alle Konflikte" is chosen when there
 * are any, one filter per kind beside it in its colour, and „Neu", „Schon
 * da" and „Alle" below for what needs nothing. Left the entry — its row
 * in the file, its chips, what it brings, what the matter is; right the
 * decision. Every input reaches the apply form through form="…", keyed by
 * the entry's place in the file (import_screen_start lists the names).
 * assets/js/importrows.js filters, counts along, ticks a finder's whole
 * lot and blocks „Einspielen" while „vielleicht umbenannt" has no answer.
 */
function import_screen_render(array $report): void
{
    $s = $report['screen'] ?? null;
    if ($s === null) {
        return;
    }
    if (($s['fields'] ?? 0) > 0): ?>
<p class="hint-text"><?= h(t('Finder, Bestimmer und die anderen Fundfelder aus der Datei kommen in ihre Felder. Deshalb wird die neue Liste eine Artenliste.')) ?></p>
    <?php endif;
    $rows = $s['rows'] ?? [];
    if ($rows === []) {
        return;
    }
    $count = static fn(callable $test): int => count(array_filter($rows, $test));
    $conflicted = $count(static fn(array $r): bool => $r['kinds'] !== []);
    $plain = static fn(string ...$verdicts): callable
        => static fn(array $r): bool => $r['kinds'] === [] && in_array($r['verdict'], $verdicts, true);
    $finders = [];
    $determined = 0;
    $twice = 0;
    foreach ($rows as $r) {
        if (in_array('finder', $r['kinds'], true)) {
            $finders[$r['finder']] = ($finders[$r['finder']] ?? 0) + 1;
        }
        $determined += in_array('determiner', $r['kinds'], true) ? 1 : 0;
        $twice += in_array('twice', $r['kinds'], true) ? 1 : 0;
    }
    $start = $conflicted > 0 ? 'conflict' : 'all';
    ?>
<div class="import-rows" data-start="<?= h($start) ?>"<?= ($s['given'] ?? []) === [] ? ''
    : ' data-given="' . h((string) json_encode(array_values($s['given']), JSON_UNESCAPED_UNICODE)) . '"'
      . (($s['series_pattern'] ?? null) === null ? '' : ' data-pattern="' . h((string) $s['series_pattern']) . '"') ?>>
    <div class="import-filters">
        <?php if ($conflicted > 0): ?>
            <button type="button" class="import-filter" data-filter="conflict" data-kind="conflict"><span class="import-dot"></span><?= h(t('Alle Konflikte %d', $conflicted)) ?></button>
            <?php foreach (IMPORT_CONFLICTS as $kind => $label):
                $n = (int) ($s['counts'][$kind] ?? 0);
                if ($n === 0) continue; ?>
                <button type="button" class="import-filter" data-filter="<?= h($kind) ?>" data-kind="<?= h($kind) ?>"><span class="import-dot"></span><?= h(t($label)) ?> <?= $n ?></button>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="import-filters">
        <?php foreach (['new' => [t('Neu'), $count($plain('new'))],
                        'there' => [t('Schon da'), $count($plain('there', 'filled'))],
                        'trash' => [t('Im Papierkorb'), $count($plain('trash'))],
                        'all' => [t('Alle'), count($rows)]] as $filter => [$label, $n]):
            if ($n === 0) continue; ?>
            <button type="button" class="import-filter" data-filter="<?= h($filter) ?>" data-kind="<?= h($filter) ?>"><?php if ($filter !== 'all'): ?><span class="import-dot"></span><?php endif; ?><?= h($label) ?> <?= $n ?></button>
        <?php endforeach; ?>
    </div>

    <?php if ($finders !== []): ?>
        <?php /* One bar per finder under their filter: the whole lot at once
             („bei einer Liste von 100 Funden und 50 davon von Volker, alle
             automatisch angehakt"). */ ?>
        <div class="import-group-bar" data-bar="finder" hidden>
            <?php foreach ($finders as $finder => $n): ?>
                <span class="import-group">
                    <span><?= h(t('%s — %d Funde', (string) $finder, $n)) ?></span>
                    <button type="button" class="button subtle small" data-take-all="finder" data-finder="<?= h((string) $finder) ?>"
                            data-on="<?= h(t('Alle einspielen')) ?>" data-off="<?= h(t('Keinen einspielen')) ?>"><?= h(t('Alle einspielen')) ?></button>
                </span>
            <?php endforeach; ?>
        </div>
        <p class="hint-text small import-spelling"><?= h(t('Sind das deine Funde, nur anders geschrieben? Dann ändere in deiner Datei den Finder auf „%s" und spiele die Datei noch einmal ein.', (string) $s['name'])) ?></p>
    <?php endif; ?>
    <?php if ($determined > 0): ?>
        <div class="import-group-bar" data-bar="determiner" hidden>
            <span><?= h($determined === 1 ? t('Ein Fund, bei dem du Bestimmer bist') : t('%d Funde, bei denen du Bestimmer bist', $determined)) ?></span>
            <button type="button" class="button subtle small" data-take-all="determiner"
                    data-on="<?= h(t('Alle einspielen')) ?>" data-off="<?= h(t('Keinen einspielen')) ?>"><?= h(t('Alle einspielen')) ?></button>
        </div>
    <?php endif; ?>
    <?php if ($twice > 1): ?>
        <div class="import-group-bar" data-bar="twice" hidden>
            <span><?= h(t('%d doppelte Nummern', $twice)) ?></span>
            <?php if (!empty($s['pattern'])): ?>
                <button type="button" class="button subtle small" data-twice-all="next"><?= h(t('Allen die nächste freie Nummer geben')) ?></button>
            <?php endif; ?>
            <button type="button" class="button subtle small" data-twice-all="none"><?= h(t('Allen keine eigene Nummer geben')) ?></button>
        </div>
    <?php endif; ?>

    <div class="table-frame">
        <table class="list no-stack import-table">
            <thead><tr><th><?= h(t('Fund und Konflikt')) ?></th><th><?= h(t('Deine Entscheidung')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r) { import_row_render($r, $s, $finders, $determined, $twice); } ?>
            </tbody>
        </table>
    </div>
</div>
    <?php
}

/** One entry of the table: what it is and brings, left; the decision, right. */
function import_row_render(array $r, array $s, array $finders, int $determined, int $twice): void
{
    $i = (int) $r['i'];
    $form = 'form="import-apply-form"';
    $day = static fn(?string $at): string => $at === null || $at === '' ? '' : date('d.m.Y H:i', strtotime($at));
    $code = static fn(string $number): string => '<code>' . h($number) . '</code>';
    $verdictChip = match (true) {
        $r['kinds'] !== []           => null,
        $r['verdict'] === 'filled'   => ['ok', t('Schon da · bekommt Nummer')],
        $r['verdict'] === 'there'    => ['quiet', t('Schon da')],
        $r['verdict'] === 'trash'    => ['quiet', t('Im Papierkorb')],
        default                      => ['ok', t('Neu')],
    };
    $data = [];
    if ($day($r['found_at']) !== '') {
        $data[] = $day($r['found_at']);
    }
    $data[] = number_format($r['lat'], 5) . ', ' . number_format($r['lon'], 5);
    if ($r['number'] !== null) {
        $data[] = t('Fundnummer: %s', $r['number']);
    }
    foreach ($r['extra'] as $name => $value) {
        if ($name !== '' && trim((string) $value) !== '') {
            $data[] = $name . ': ' . str_replace("\n", ', ', trim((string) $value));
        }
    }
    ?>
<tr data-kinds="<?= h(implode(' ', $r['kinds'])) ?>" data-verdict="<?= h($r['kinds'] !== [] ? 'conflict' : $r['verdict']) ?>"<?=
    in_array('finder', $r['kinds'], true) ? ' data-finder="' . h($r['finder']) . '"' : '' ?>>
    <td class="import-entry">
        <div class="import-entry-head">
            <span class="import-row-no"><?= h(t('Zeile %d', (int) $r['row'])) ?></span>
            <?php foreach ($r['kinds'] as $kind): ?>
                <span class="import-chip" data-kind="<?= h($kind) ?>"<?= $kind === 'number' && in_array('renamed', $r['kinds'], true) ? ' data-follows="same" hidden' : '' ?>><?= h(t(IMPORT_CONFLICTS[$kind])) ?></span>
            <?php endforeach; ?>
            <?php if ($verdictChip !== null): ?>
                <span class="import-chip" data-kind="<?= h($verdictChip[0]) ?>"><?= h($verdictChip[1]) ?></span>
            <?php endif; ?>
        </div>
        <strong><?= h($r['title']) ?></strong>
        <?php if ($data !== []): ?><div class="item-data"><?= h(implode(' · ', $data)) ?></div><?php endif; ?>
        <?php if (trim((string) ($r['note'] ?? '')) !== ''): ?><div class="item-data"><?= h(mb_substr(trim((string) $r['note']), 0, 120)) ?></div><?php endif; ?>
        <?php
        $problems = [];
        if (in_array('twice', $r['kinds'], true)) {
            $c = $r['carrier'];
            $problems[] = !empty($c['file'])
                ? t('%s trägt schon „%s" weiter oben in dieser Datei.', $code((string) $r['number']), h((string) $c['title']))
                : (($c['found_at'] ?? null)
                    ? t('%s trägt schon „%s" vom %s.', $code((string) $r['number']), h((string) $c['title']), h(date('d.m.Y', strtotime((string) $c['found_at']))))
                    : t('%s trägt schon „%s".', $code((string) $r['number']), h((string) $c['title'])))
                  . (!empty($c['trash']) ? ' ' . h(t('Der liegt im Papierkorb.')) : '');
        }
        if (in_array('finder', $r['kinds'], true)) {
            $problems[] = h(t('Nicht dein Fund. Eingespielt bleibt %s der Finder.', $r['finder']))
                . ($r['number'] !== null ? ' ' . t('Die Nummer %s steht dann in der Notiz, sie gehört zur Sammlung des Finders.', $code($r['number'])) : '');
        }
        if (in_array('determiner', $r['kinds'], true)) {
            $problems[] = h(t('Gefunden hat ihn %s, bestimmt hast du ihn. Eingespielt bleibt %s der Finder.', $r['finder'], $r['finder']))
                . ($r['number'] !== null ? ' ' . t('Die Nummer %s steht dann in der Notiz.', $code($r['number'])) : '');
        }
        if (in_array('number', $r['kinds'], true) && !in_array('renamed', $r['kinds'], true)) {
            $problems[] = t('Diesen Fund gibt es schon, mit der Nummer %s.', $code((string) $r['stored']));
        }
        if (in_array('renamed', $r['kinds'], true)) {
            $c = $r['candidate'];
            $what = array_filter([$day($c['found_at'] ?? null), ($c['number'] ?? null) !== null ? t('Nummer %s', (string) $c['number']) : '']);
            $problems[] = t('An genau dieser Stelle liegt schon „%s"%s. Ist das derselbe Fund mit neuem Namen?',
                h((string) $c['title']), $what === [] ? '' : h(' (' . implode(', ', $what) . ')'));
        }
        if ($r['kinds'] === []) {
            $problems[] = match ($r['verdict']) {
                'filled' => t('Bekommt die Nummer %s aus der Datei.', $code((string) $r['number'])),
                'there'  => h(t('Gleicher Name, gleiche Stelle — bleibt, wie er ist.')),
                'trash'  => h(t('Liegt in deinem Papierkorb und bleibt draußen.')),
                default  => '',
            };
        }
        foreach (array_filter($problems) as $problem): ?>
            <div class="import-problem"><?= $problem ?></div>
        <?php endforeach; ?>
    </td>
    <td class="import-decision">
        <?php if (in_array('twice', $r['kinds'], true)) { import_twice_render($r, $s, $twice); } ?>
        <?php if (in_array('finder', $r['kinds'], true) || in_array('determiner', $r['kinds'], true)):
            $kind = in_array('finder', $r['kinds'], true) ? 'finder' : 'determiner'; ?>
            <div class="import-fix">
                <label class="checkbox-row"><input type="checkbox" name="take[]" value="<?= $i ?>" <?= $form ?> data-take="<?= $kind ?>"<?=
                    $kind === 'finder' ? ' data-finder="' . h($r['finder']) . '"' : '' ?>> <span><?= h(t('Diesen Fund einspielen')) ?></span></label>
                <?php if ($kind === 'finder' && ($finders[$r['finder']] ?? 0) > 1): ?>
                    <button type="button" class="button subtle small" data-take-all="finder" data-finder="<?= h($r['finder']) ?>"
                            data-on="<?= h(t('Alle %d von %s einspielen', $finders[$r['finder']], $r['finder'])) ?>"
                            data-off="<?= h(t('Keinen von %s einspielen', $r['finder'])) ?>"><?= h(t('Alle %d von %s einspielen', $finders[$r['finder']], $r['finder'])) ?></button>
                <?php elseif ($kind === 'determiner' && $determined > 1): ?>
                    <button type="button" class="button subtle small" data-take-all="determiner"
                            data-on="<?= h(t('Alle %d einspielen, bei denen du Bestimmer bist', $determined)) ?>"
                            data-off="<?= h(t('Keinen einspielen, bei dem du Bestimmer bist')) ?>"><?= h(t('Alle %d einspielen, bei denen du Bestimmer bist', $determined)) ?></button>
                <?php endif; ?>
            </div>
            <?php if ($kind === 'determiner' && !empty($s['pattern'])): ?>
                <div class="import-fix" data-follows="take" hidden>
                    <span class="import-question"><?= h(t('Welche Nummer?')) ?></span>
                    <label class="choice-row"><input type="radio" name="det_number[<?= $i ?>]" value="none" <?= $form ?> checked> <span><?= h(t('Keine Nummer deiner Reihe')) ?></span></label>
                    <label class="choice-row"><input type="radio" name="det_number[<?= $i ?>]" value="next" <?= $form ?>> <span><?= h(t('Die nächste freie deiner Reihe')) ?></span></label>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (in_array('number', $r['kinds'], true) && !in_array('renamed', $r['kinds'], true)): ?>
            <div class="import-fix">
                <span class="import-question"><?= h(t('Welche Nummer?')) ?></span>
                <label class="choice-row"><input type="radio" name="keep[<?= $i ?>]" value="stored" <?= $form ?> checked> <span><?= t('%s behalten', $code((string) $r['stored'])) ?></span></label>
                <label class="choice-row"><input type="radio" name="keep[<?= $i ?>]" value="file" <?= $form ?>> <span><?= t('%s aus der Datei nehmen', $code((string) $r['number'])) ?></span></label>
            </div>
        <?php endif; ?>
        <?php if (in_array('renamed', $r['kinds'], true)) { import_renamed_render($r, $s, $twice); } ?>
    </td>
</tr>
    <?php
}

/**
 * The ways out of a number another find carries. $ask false where the block
 * stands under a question already (the new find's number of „vielleicht
 * umbenannt") — one question, not the same one twice.
 */
function import_twice_render(array $r, array $s, int $twice, bool $ask = true): void
{
    $i = (int) $r['i'];
    $form = 'form="import-apply-form"';
    $hasPattern = !empty($s['pattern']);
    ?>
    <div class="import-fix" data-twice>
        <?php if ($ask): ?>
            <span class="import-question"><?= h(t('Welche Nummer?')) ?></span>
        <?php endif; ?>
        <?php if ($hasPattern): ?>
            <label class="choice-row"><input type="radio" name="twice[<?= $i ?>]" value="next" <?= $form ?> checked> <span><?= t('Nächste freie Nummer: %s', '<code>' . h((string) ($r['proposal'] ?? '')) . '</code>') ?></span></label>
        <?php endif; ?>
        <label class="choice-row import-own"><input type="radio" name="twice[<?= $i ?>]" value="own" <?= $form ?>> <?= h(t('Diese Nummer:')) ?>
            <input type="text" class="import-number" name="twice_number[<?= $i ?>]" <?= $form ?> maxlength="80" autocomplete="off" spellcheck="false"
                   data-taken-state data-free="<?= h(t('frei')) ?>" data-taken="<?= h(t('gibt es schon')) ?>">
            <span class="import-number-state"></span></label>
        <label class="choice-row"><input type="radio" name="twice[<?= $i ?>]" value="none" <?= $form ?><?= $hasPattern ? '' : ' checked' ?>> <span><?= h(t('Ohne eigene Nummer')) ?>
            <span class="hint-text small"><?= h(t('— er bekommt eine von YASAIMAPP')) ?></span></span></label>
        <?php if ($twice > 1): ?>
            <button type="button" class="button subtle small" data-twice-same
                    data-next="<?= h(t('Bei allen %d doppelten: nächste freie Nummer', $twice)) ?>"
                    data-none="<?= h(t('Bei allen %d doppelten: ohne eigene Nummer', $twice)) ?>"><?= h($hasPattern
                ? t('Bei allen %d doppelten: nächste freie Nummer', $twice)
                : t('Bei allen %d doppelten: ohne eigene Nummer', $twice)) ?></button>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * „Vielleicht umbenannt": four answers, none preselected
 * (26.09.2026: „eine Auswahl muss man treffen") — and the number as a
 * second question that follows the first: the same find settles its
 * number, a new find takes the file's if it is free.
 */
function import_renamed_render(array $r, array $s, int $twice): void
{
    $i = (int) $r['i'];
    $form = 'form="import-apply-form"';
    $c = $r['candidate'];
    $number = $r['number'];
    ?>
    <div class="import-fix" data-renamed>
        <span class="import-question"><?= h(t('Was soll passieren?')) ?></span>
        <label class="choice-row"><input type="radio" name="renamed[<?= $i ?>]" value="rename" <?= $form ?>> <span><?= h(t('Neuen Namen übernehmen')) ?></span></label>
        <label class="choice-row"><input type="radio" name="renamed[<?= $i ?>]" value="keep" <?= $form ?>> <span><?= h(t('Alten Namen lassen')) ?></span></label>
        <label class="choice-row"><input type="radio" name="renamed[<?= $i ?>]" value="new" <?= $form ?>> <span><?= h(t('Neuen Fund anlegen')) ?></span></label>
        <label class="choice-row"><input type="radio" name="renamed[<?= $i ?>]" value="skip" <?= $form ?>> <span><?= h(t('Nicht einspielen')) ?></span></label>
        <span class="import-missing" hidden><?= h(t('Bitte wähle eine Möglichkeit.')) ?></span>
    </div>
    <?php if ($number !== null && ($c['number'] ?? null) !== null && mb_strtolower((string) $c['number']) !== mb_strtolower($number)): ?>
        <div class="import-fix" data-follows="same" hidden>
            <span class="import-question"><?= h(t('Welche Nummer?')) ?></span>
            <label class="choice-row"><input type="radio" name="renamed_number[<?= $i ?>]" value="stored" <?= $form ?> checked> <span><?= t('%s behalten', '<code>' . h((string) $c['number']) . '</code>') ?></span></label>
            <label class="choice-row"><input type="radio" name="renamed_number[<?= $i ?>]" value="file" <?= $form ?>> <span><?= t('%s aus der Datei nehmen', '<code>' . h($number) . '</code>') ?></span></label>
        </div>
    <?php elseif ($number !== null && ($c['number'] ?? null) === null): ?>
        <div class="import-fix" data-follows="same" hidden>
            <span class="import-question"><?= h(t('Welche Nummer?')) ?></span>
            <span class="hint-text small"><?= t('Bekommt die Nummer %s aus der Datei.', '<code>' . h($number) . '</code>') ?></span>
        </div>
    <?php endif; ?>
    <?php if ($number !== null): ?>
        <div class="import-fix" data-follows="new" hidden>
            <span class="import-question"><?= h(t('Welche Nummer?')) ?></span>
            <?php if (($r['new_carrier'] ?? null) === null): ?>
                <span class="hint-text small"><?= t('Der neue Fund bekommt %s.', '<code>' . h($number) . '</code>') ?></span>
            <?php else: ?>
                <span class="hint-text small"><?= t('%s trägt schon „%s".', '<code>' . h($number) . '</code>', h((string) $r['new_carrier']['title'])) ?></span>
                <?php import_twice_render(['i' => $i, 'proposal' => $r['new_proposal'] ?? ''] + $r, $s, 1, false); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
}

/**
 * The headline of a report, and the quiet line under it.
 *
 * THE COUNT COUNTS ALONG WHILE ONE DECIDES (25.08.2026 for Meine Pilze's
 * boxes, since 26.09.2026 for every source): the ticks of the table change
 * exactly the number the headline states, so it carries its two wordings
 * (data-one / data-many — German does not accept „1 Funde"). Under it, in
 * the warning colour, how many entries wait for a decision — the maintainer's
 * sentence: „bei vier Punkten brauche ich deine Entscheidung". The count
 * of finds here already went from the head: „2 gibt es schon" read as a
 * riddle; the filter „Schon da" says it where one can look.
 */
function import_summary(string $headline, string $detail, array $counter = [], ?array $screen = null): void
{
    echo '<h3 class="import-count"'
       . ($counter !== []
           ? ' data-count="' . (int) $counter['count'] . '"'
             . ' data-one="' . h($counter['one']) . '"'
             . ' data-many="' . h($counter['many']) . '"'
           : '')
       . '>' . h($headline) . '</h3>';
    $open = $screen === null ? 0 : count(array_filter($screen['rows'] ?? [], static fn(array $r): bool => $r['kinds'] !== []));
    if ($open > 0) {
        echo '<p class="import-needs">' . h($open === 1
            ? t('Bei einem Fund brauche ich deine Entscheidung.')
            : t('Bei %d Funden brauche ich deine Entscheidung.', $open)) . '</p>';
    }
    if (trim($detail) !== '') {
        echo '<p class="hint-text small">' . h($detail) . '</p>';
    }
}

function import_takeout_report(array $report): void
{
    ?>
    <div class="table-frame">
        <table class="list">
            <thead>
            <tr><th><?= h(t('Liste aus dem Takeout')) ?></th><th><?= h(t('Punkte')) ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($report['lists'] as $l): ?>
                <tr>
                    <td><strong><?= h($l['name']) ?></strong></td>
                    <td>
                        <?php if ($l['empty']): ?>
                            <?= h(t('leer, wird übersprungen')) ?>
                        <?php else: ?>
                            <?php
                            $parts = [t('%d neu', (int) $l['new'])];
                            if ($l['assigned']) { $parts[] = t('%d schon vorhanden', (int) $l['assigned']); }
                            if ($l['without'])  { $parts[] = t('%d ohne Position', (int) $l['without']); }
                            echo h(implode(', ', $parts));
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $n = (int) $report['created'];
    $headline = $n === 1 ? t('Ein Punkt würde angelegt') : t('%d Punkte würden angelegt', $n);
    $detail = t('In die Liste „%s".', (string) $report['import']);
    import_summary($headline, $detail, ['count' => $n, 'one' => t('Ein Punkt würde angelegt'),
        'many' => t('%d Punkte würden angelegt')], $report['screen'] ?? null);
    ?>

    <?php if ($report['withoutPosition'] !== []): ?>
        <h4><?= h(count($report['withoutPosition']) === 1
            ? t('Ohne Position — ein Eintrag')
            : t('Ohne Position — %d Einträge', count($report['withoutPosition']))) ?></h4>
        <p class="hint-text small">
            <?= h(t('Diese Orte tragen bei Google nur einen internen Verweis, aus dem sich keine Lage ableiten lässt — und raten wäre schlimmer als fehlen. Der Weg von Hand: Eintrag bei Google öffnen, mit Rechtsklick auf die Stelle die Koordinaten kopieren und hier über die Suche als neuen Punkt setzen.')) ?>
        </p>
        <div class="table-frame">
            <table class="list">
                <tbody>
                <?php foreach ($report['withoutPosition'] as $o): ?>
                    <tr>
                        <td>
                            <strong><?= h($o['title'] !== '' ? $o['title'] : t('Ohne Titel')) ?></strong>
                            <span class="item-data"><?= h(t('aus „%s"', (string) $o['list'])) ?></span>
                            <?php if (($o['note'] ?? null) !== null): ?>
                                <div class="item-data"><?= h($o['note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="button-cell">
                            <?php if (str_starts_with((string) $o['url'], 'http')): ?>
                                <a class="button subtle small" target="_blank" rel="noopener"
                                   href="<?= h($o['url']) ?>"><?= h(t('Bei Google öffnen')) ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php
}

// ---------------------------------------------------------------------------
// Meine Pilze (App)
// ---------------------------------------------------------------------------

function import_pilze_preview(array $report, string $target, int $waiting = 1): void
{
    ?>
<?php import_pilze_report($report); ?>

<p class="hint-text small">
    <?= h(t('Funde, die es hier schon gibt (gleicher Name, gleiche Stelle), werden nicht doppelt angelegt.')) ?>
</p>

<?php import_screen_render($report); ?>

<?php import_buttons($target, $waiting ?? 1); ?>
    <?php
}

function import_pilze_report(array $report): void
{
    ?>
    <?php
    $n = (int) $report['created'];
    $headline = $n === 1 ? t('Ein Fund würde angelegt') : t('%d Funde würden angelegt', $n);
    $detail = '';
    ?>
    <?php
    $detail .= t('In die Liste „%s", mit Fundzeit, Genauigkeit und Bemerkungen aus der App.',
        (string) $report['import']);
    if ($report['skipped'] > 0) {
        $detail .= ' ' . ($report['skipped'] === 1
            ? t('%d Zeile ohne Artnamen übergangen.', (int) $report['skipped'])
            : t('%d Zeilen ohne Artnamen übergangen.', (int) $report['skipped']));
    }
    /* SHOWN ONCE, BEFORE THE CLICK (25.08.2026: „Ich
       bekomme erst angezeigt, was kein Namensverweis hat, sage
       einspielen und bekomme dann nochmal angezeigt, was kein
       Namensverweis hat" — and, after thinking it through: „wenn mir
       vor dem Bestätigen schon etwas angezeigt wird, das ich bestätigt
       habe, braucht es mir danach nichts mehr angezeigt werden").

       This block, like every other in this file, is a DECISION AID: one
       can still go fix the CSV and drop it again. After the click there
       is nothing new in it, so there is no report after the click at
       all — applying leaves through Location and takes the one new
       fact, the count, along as a toast. The count of unresolved names
       rides in the small line under the headline, where one reads it
       before deciding. */
    if ($report['withoutReference'] !== []) {
        $detail .= ' ' . (count($report['withoutReference']) === 1
            ? t('%d Name ohne Treffer — unten einzeln abwählbar.', count($report['withoutReference']))
            : t('%d Namen ohne Treffer — unten einzeln abwählbar.', count($report['withoutReference'])));
    }
    /* The count changes under the hand here, so it carries its two
       wordings along: with checkboxes below it, a fixed sentence would
       go on claiming four while one has just unticked two. */
    import_summary($headline, $detail, [
        'count' => $n,
        'one'   => t('Ein Fund würde angelegt'),
        'many'  => t('%d Funde würden angelegt'),
    ], $report['screen'] ?? null);
    ?>

    <?php if ($report['withoutReference'] !== []): ?>
        <h4><?= h(count($report['withoutReference']) === 1
            ? t('Ohne Treffer in einer Namensliste — ein Fund')
            : t('Ohne Treffer in einer Namensliste — %d Funde', count($report['withoutReference']))) ?></h4>
        <?php /* CHECKED, AND ONE CAN UNTICK (25.08.2026). Until
             today these came in silently, and he had not reckoned with
             it („das hatte ich gar nicht so auf dem Schirm") — an open
             determination like „Cortinarius anomalus agg." is meant to
             come, a typo is not, and nothing here could tell them apart
             because only he can.

             The default is the old behaviour: whoever reads past this
             block loses nothing. Only the hand that unticks changes
             something, and it changes exactly one thing — that find
             stays out of the file entirely. */ ?>
        <p class="hint-text small">
            <?= h(t('Diese Namen stehen in keiner Namensliste — vielleicht eine offene Bestimmung („agg.", „spec."), vielleicht ein Schreibfehler. Angekreuzt werden sie eingespielt, der Titel lässt sich später im Punktfenster nachtragen. Wegklicken, was gar nicht mitkommen soll.')) ?>
        </p>
        <div class="table-frame">
            <table class="list">
                <tbody>
                <?php foreach ($report['withoutReference'] as $i => $o): ?>
                    <tr>
                        <td class="check-cell">
                            <?php /* form="…" reaches the apply form further
                                 down: the box sits in a table outside it,
                                 and HTML knows no nested forms. The same
                                 trick the name field and the dialog
                                 footers use.

                                 The hidden twin is what makes „unticked"
                                 legible at all — an unchecked box sends
                                 nothing, so the offer has to travel
                                 beside the answer. */ ?>
                            <input type="checkbox" name="take[]" checked
                                   id="take-<?= (int) $i ?>" data-import-take
                                   value="<?= h((string) ($o['key'] ?? '')) ?>"
                                   form="import-apply-form">
                            <input type="hidden" name="offered[]"
                                   value="<?= h((string) ($o['key'] ?? '')) ?>"
                                   form="import-apply-form">
                        </td>
                        <td><label for="take-<?= (int) $i ?>"><strong><?= h($o['title']) ?></strong></label></td>
                        <td><?= $o['found_at'] !== null
                            ? h(date('d.m.Y', strtotime((string) $o['found_at']))) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($report['withoutPosition'] !== []): ?>
        <h4><?= h(count($report['withoutPosition']) === 1
            ? t('Ohne Position — ein Fund')
            : t('Ohne Position — %d Funde', count($report['withoutPosition']))) ?></h4>
        <p class="hint-text small">
            <?= h(t('Bei diesen Zeilen fehlen brauchbare Koordinaten — sie werden nicht eingespielt, denn ein falsch gesetzter Punkt ist schlimmer als ein fehlender.')) ?>
        </p>
        <div class="table-frame">
            <table class="list">
                <tbody>
                <?php foreach ($report['withoutPosition'] as $o): ?>
                    <tr>
                        <td><strong><?= h($o['title']) ?></strong>
                            <?php if (($o['note'] ?? null) !== null): ?>
                                <div class="item-data"><?= h($o['note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $o['found_at'] !== null
                            ? h(date('d.m.Y', strtotime((string) $o['found_at']))) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php
}

// ---------------------------------------------------------------------------
// KML/KMZ (Google Earth, My Maps)
// ---------------------------------------------------------------------------

function import_kml_preview(array $report, string $target, int $waiting = 1): void
{
    ?>
<?php import_kml_report($report); ?>

<p class="hint-text small">
    <?= h(t('Ortsmarken, die es hier schon gibt (gleicher Name, gleiche Stelle), werden nicht doppelt angelegt.')) ?>
</p>

<?php import_screen_render($report); ?>

<?php import_buttons($target, $waiting ?? 1); ?>
    <?php
}

function import_kml_report(array $report): void
{
    ?>
    <?php
    $n = (int) $report['created'];
    $headline = $n === 1 ? t('Eine Ortsmarke würde angelegt') : t('%d Ortsmarken würden angelegt', $n);
    $detail = '';
    ?>
    <?php
    $detail .= t('In die Liste „%s", mit Name, Beschreibung und — wo vorhanden — Zeitstempel und Höhe.',
        (string) $report['import']);
    if ($report['skipped'] > 0) {
        $detail .= ' ' . ($report['skipped'] === 1
            ? t('%d Ortsmarke ohne brauchbare Koordinaten übergangen.', (int) $report['skipped'])
            : t('%d Ortsmarken ohne brauchbare Koordinaten übergangen.', (int) $report['skipped']));
    }
    if ($report['ways'] > 0) {
        $detail .= ' ' . ((int) $report['ways'] === 1
            ? t('Eine Linie bzw. Fläche bleibt draussen — das sind Wege und Umrisse, keine Funde.')
            : t('%d Linien und Flächen bleiben draussen — das sind Wege und Umrisse, keine Funde.', (int) $report['ways']));
    }
    import_summary($headline, $detail, ['count' => $n, 'one' => t('Eine Ortsmarke würde angelegt'), 'many' => t('%d Ortsmarken würden angelegt')], $report['screen'] ?? null);
    ?>

    <?php if (count($report['lists']) > 1): ?>
        <?php /* The folder table only if there really are folders — with a
             single one everything lands directly in the import list, and a
             one-row table would say the same thing more clumsily. */ ?>
        <div class="table-frame">
            <table class="list">
                <thead>
                <tr><th><?= h(t('Ordner aus der Datei')) ?></th><th><?= h(t('Ortsmarken')) ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($report['lists'] as $l): ?>
                    <tr>
                        <td><strong><?= h($l['name']) ?></strong></td>
                        <td><?= h(t('%d neu', (int) $l['new'])) ?><?=
                            $l['assigned'] ? ', ' . h(t('%d schon vorhanden', (int) $l['assigned'])) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php
}

// ---------------------------------------------------------------------------
// GPX (GPS devices and map apps)
// ---------------------------------------------------------------------------

function import_gpx_preview(array $report, string $target, int $waiting = 1): void
{
    ?>
<?php import_gpx_report($report); ?>

<p class="hint-text small">
    <?= h(t('Wegpunkte, die es hier schon gibt (gleicher Name, gleiche Stelle), werden nicht doppelt angelegt.')) ?>
</p>

<?php import_screen_render($report); ?>

<?php import_buttons($target, $waiting ?? 1); ?>
    <?php
}

function import_gpx_report(array $report): void
{
    ?>
    <?php
    $n = (int) $report['created'];
    $headline = $n === 1 ? t('Ein Wegpunkt würde angelegt') : t('%d Wegpunkte würden angelegt', $n);
    $detail = '';
    ?>
    <?php
    $detail .= t('In die Liste „%s", mit Name, Beschreibung, Höhe und dem Zeitpunkt der Markierung als Fundzeit.',
        (string) $report['import']);
    if ($report['skipped'] > 0) {
        $detail .= ' ' . ($report['skipped'] === 1
            ? t('%d Wegpunkt ohne brauchbare Koordinaten übergangen.', (int) $report['skipped'])
            : t('%d Wegpunkte ohne brauchbare Koordinaten übergangen.', (int) $report['skipped']));
    }
    if ($report['ways'] > 0) {
        $detail .= ' ' . ((int) $report['ways'] === 1
            ? t('Ein Track bzw. eine Route bleibt draussen — das sind Wege, keine Funde.')
            : t('%d Tracks und Routen bleiben draussen — das sind Wege, keine Funde.', (int) $report['ways']));
    }
    import_summary($headline, $detail, ['count' => $n, 'one' => t('Ein Wegpunkt würde angelegt'), 'many' => t('%d Wegpunkte würden angelegt')], $report['screen'] ?? null);
    ?>
    <?php
}

// ---------------------------------------------------------------------------
// GeoJSON (web maps and converters)
// ---------------------------------------------------------------------------

function import_geojson_preview(array $report, string $target, int $waiting = 1): void
{
    ?>
<?php import_geojson_report($report); ?>

<p class="hint-text small">
    <?= h(t('Punkte, die es hier schon gibt (gleicher Name, gleiche Stelle), werden nicht doppelt angelegt.')) ?>
</p>

<?php import_screen_render($report); ?>

<?php import_buttons($target, $waiting ?? 1); ?>
    <?php
}

function import_geojson_report(array $report): void
{
    ?>
    <?php
    $n = (int) $report['created'];
    $headline = $n === 1 ? t('Ein Punkt würde angelegt') : t('%d Punkte würden angelegt', $n);
    $detail = '';
    ?>
    <?php
    $detail .= t('In die Liste „%s", mit Titel, Beschreibung und allen weiteren Eigenschaften als Notiz.',
        (string) $report['import']);
    if ($report['skipped'] > 0) {
        $detail .= ' ' . ($report['skipped'] === 1
            ? t('%d Eintrag ohne brauchbare Koordinaten übergangen.', (int) $report['skipped'])
            : t('%d Einträge ohne brauchbare Koordinaten übergangen.', (int) $report['skipped']));
    }
    if ($report['ways'] > 0) {
        $detail .= ' ' . ((int) $report['ways'] === 1
            ? t('Eine Linie bzw. Fläche bleibt draussen — das sind Wege und Umrisse, keine Funde.')
            : t('%d Linien und Flächen bleiben draussen — das sind Wege und Umrisse, keine Funde.', (int) $report['ways']));
    }
    import_summary($headline, $detail, ['count' => $n, 'one' => t('Ein Punkt würde angelegt'), 'many' => t('%d Punkte würden angelegt')], $report['screen'] ?? null);
    ?>
    <?php
}

// ---------------------------------------------------------------------------
// CSV table (arbitrary columns)
// ---------------------------------------------------------------------------

function import_csv_preview(array $report, string $target, int $waiting = 1): void
{
    ?>
<p class="hint-text">
    <?= h(t('Eine Tabelle mit %d Spalten und %d Zeilen. Welche Spalte was bedeutet, ist hier geraten — bitte prüfen und bei Bedarf umstellen; alles ohne Zuordnung wandert als „Spalte: Wert" in die Notiz.',
        count($report['columns']), (int) $report['rows'])) ?>
</p>

<form method="post" action="<?= h($target) ?>" class="import-interpretation">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="import">
    <input type="hidden" name="action" value="interpret">
    <?php /* The import name typed at the head: it belongs to the apply form,
         not to this one, so it would not travel by itself. This mirror
         is filled at submit time by administration.js. */ ?>
    <input type="hidden" name="name" id="import-name-carried" value="">
    <?php foreach (CSV_ROLES as $role => $label): ?>
        <div class="field">
            <label for="interpretation-<?= h($role) ?>"><?= h(t($label)) ?></label>
            <select id="interpretation-<?= h($role) ?>" name="mapping[<?= h($role) ?>]">
                <option value="">—</option>
                <?php foreach ($report['columns'] as $i => $column): ?>
                    <option value="<?= $i ?>"<?= $report['interpretation'][$role] === $i ? ' selected' : '' ?>>
                        <?= h($column) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>
    <button class="button subtle" type="submit"><?= h(t('Zuordnung anwenden')) ?></button>
</form>

<?php if ($report['incomplete']): ?>
    <p class="hint-text">
        <?= t('<strong>Ohne Breiten- und Längenspalte geht es nicht weiter</strong> — bitte oben zuordnen und anwenden. Steht die Lage in EINER Spalte („50.1, 8.2"), einfach dieselbe Spalte als Breiten- UND Längengrad wählen.') ?>
    </p>
    <?php import_csv_discard($target, $waiting); ?>
    <?php return; ?>
<?php endif; ?>

<?php import_csv_report($report); ?>

<?php /* The rows as they were read stand in the screening's table below —
     every row, not the first five: title, find time, number, fields,
     position and note, so the assignment above can be checked against
     them (import_row_render). */ ?>
<?php import_screen_render($report); ?>

<?php /* Its own form instead of import_buttons: applying must carry the
     checked assignment along — otherwise applying would guess anew, and
     the preview would have promised something else.

     THE NAME FIELD IS NOT IN HERE (25.08.2026: „ich hatte
     zweimal die Möglichkeit, den Namen anzugeben, einmal oben und einmal
     unten"). When the field moved up to the head, I rewrote the general
     import_buttons() and overlooked this second, CSV-only copy — so on a
     table the field stood twice, with the same id both times, and only
     the lower one would have been submitted. It belongs at the head like
     everywhere else; it reaches this form through form="…", which is why
     the id must exist exactly once. */ ?>
<form method="post" action="<?= h($target) ?>" id="import-apply-form">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="import">
    <input type="hidden" name="action" value="apply">
    <?php foreach ($report['interpretation'] as $role => $i): ?>
        <input type="hidden" name="mapping[<?= h($role) ?>]" value="<?= h((string) $i) ?>">
    <?php endforeach; ?>
    <div class="button-row right">
        <?php import_exits($waiting); ?>
        <button class="button" type="submit"><?= h(t('Einspielen')) ?></button>
    </div>
</form>
<?php import_exit_forms($target, $waiting); ?>
    <?php
}

/**
 * The ways out WITHOUT an apply button — the one case where they stand
 * alone: a CSV whose latitude and longitude columns are not assigned yet
 * cannot be applied at all, so there is no row for them to join.
 */
function import_csv_discard(string $target, int $waiting = 1): void
{
    echo '<div class="button-row right">';
    import_exits($waiting);
    echo '</div>';
    import_exit_forms($target, $waiting);
}

function import_csv_report(array $report): void
{
    ?>
    <?php
    $n = (int) $report['created'];
    $headline = $n === 1 ? t('Ein Punkt würde angelegt') : t('%d Punkte würden angelegt', $n);
    $detail = '';
    ?>
    <?php import_summary($headline, $detail . t('In die Liste „%s".', (string) $report['import']),
        ['count' => $n, 'one' => t('Ein Punkt würde angelegt'), 'many' => t('%d Punkte würden angelegt')], $report['screen'] ?? null); ?>

    <?php if ($report['skipped'] !== []): ?>
        <h4><?= h(count($report['skipped']) === 1
            ? t('Ohne brauchbare Koordinaten — eine Zeile')
            : t('Ohne brauchbare Koordinaten — %d Zeilen', count($report['skipped']))) ?></h4>
        <p class="hint-text small">
            <?= h(t('Diese Zeilen werden nicht eingespielt — ein falsch gesetzter Punkt ist schlimmer als ein fehlender. Die Zeilennummern zählen wie in der Tabellenkalkulation, samt Kopfzeile.')) ?>
        </p>
        <div class="table-frame">
            <table class="list">
                <tbody>
                <?php foreach (array_slice($report['skipped'], 0, 20) as $o): ?>
                    <tr>
                        <td><?= h(t('Zeile %d', (int) $o['row'])) ?></td>
                        <td><?= h($o['title']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($report['skipped']) > 20): ?>
                    <tr><td colspan="2"><?= h(t('… und %d weitere', count($report['skipped']) - 20)) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php
}
