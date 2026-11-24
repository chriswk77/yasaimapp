<?php
/**
 * Read arbitrary CSV tables — the catch-all source for everything that
 * falls out of spreadsheets, databases and unknown apps.
 *
 * The other sources know what their columns are called — here nobody does.
 * That is why this source, alone, has a MAPPING STEP:
 *
 *   1. csv_read takes the table as it is: separator (;, comma, tab) and
 *      character set (UTF-8 or Windows-1252) are detected, the header row
 *      too — if the first row consists only of numbers, there is none, and
 *      the columns are called "Spalte 1..n".
 *   2. csv_guess proposes which column plays which role — via the usual
 *      names (Breitengrad, lat, y ...), and for the coordinates only if
 *      the values also pass as such.
 *   3. The human corrects the interpretation in the preview; it travels as
 *      interpretation[role] through every further step (csv_interpret).
 *
 * What is assigned to NO role hangs on the note as "column: value", as
 * with GeoJSON — so that nothing is lost silently; the original file sits
 * on the import anyway. NO species reference, no species marker: the
 * source is neutral, it does not know what the table is about.
 */

declare(strict_types=1);

/** The roles a column can play — key => label. */
const CSV_ROLES = [
    'title'       => 'Titel',
    'lat'         => 'Breitengrad',
    'lon'         => 'Längengrad',
    'time'        => 'Fundzeit',
    'height'      => 'Höhe (m)',
    'accuracy'    => 'Genauigkeit (m)',
    // Taken over verbatim into the point's find number — an import never
    // mints its own (the reasoning stands at point_create), but whoever
    // brings a numbered collection keeps its numbers.
    'find_number' => 'Fundnummer',
    'note'        => 'Notiz',
];

/**
 * Usual column names per role, lowercased. The exact hit counts first, then
 * "name contains entry" — the latter only for entries of four characters or
 * more, otherwise "art" would find itself in "Karte" and "Quartier".
 *
 * THE GERMAN ENTRIES ARE DATA, NOT VOCABULARY: they are the column headers
 * users' spreadsheets actually carry. The move to English once translated
 * them along with the code — 'breite' became 'width', 'titel' collapsed
 * into a duplicate 'title' — and a CSV with a "Breite" column silently
 * stopped being recognized. Whoever touches these lists adds names, never
 * translates them.
 */
const CSV_NAMES = [
    'lat'      => ['lat', 'latitude', 'breite', 'breitengrad', 'y'],
    'lon'      => ['lon', 'lng', 'long', 'longitude', 'länge', 'laenge',
                   'längengrad', 'laengengrad', 'x'],
    'title'    => ['title', 'name', 'titel', 'art', 'artname', 'fund',
                   'bezeichnung', 'objekt'],
    'time'     => ['datum', 'date', 'zeit', 'time', 'funddatum', 'fundzeit',
                   'timestamp', 'zeitpunkt', 'gefunden am', 'gefunden_am',
                   'found_at'],
    'height'   => ['höhe', 'hoehe', 'höhe (m)', 'hoehe_m', 'elevation_m',
                   'elevation', 'ele', 'altitude', 'alt', 'height'],
    'accuracy' => ['genauigkeit', 'genauigkeit (m)', 'genauigkeit_m',
                   'accuracy_m', 'accuracy', 'acc'],
    'find_number' => ['fundnummer', 'fund-nummer', 'fundnr', 'fund-nr',
                   'fund-nr.', 'nummer', 'nr', 'nr.', 'find_number'],
    'note'     => ['note', 'notiz', 'bemerkung', 'bemerkungen', 'beschreibung',
                   'description', 'kommentar', 'comment', 'notes',
                   'anmerkung'],
];

/** Names of a combined coordinate column ("50.1, 8.2" in one cell). */
const CSV_POSITION_NAMES = ['position', 'koordinaten', 'coordinates', 'coords',
                            'lage', 'standort', 'latlon', 'lat/lon', 'lat, lon'];

/**
 * Read out a CSV file.
 *
 * @return array{columns: string[], rows: array[], separator: string, header: bool}
 */
function csv_read(string $path): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException(t('Datei nicht lesbar: %s', basename($path)));
    }
    $content = (string) preg_replace('~^\xEF\xBB\xBF~', '', $content);   // BOM

    if (!mb_check_encoding($content, 'UTF-8')) {
        // Excel on Windows writes Windows-1252 — the same trap as with
        // "Meine Pilze".
        $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }

    // Separator: do NOT simply take the most frequent character — in
    // "50,1;8,2" two decimal commas count against one semicolon, and the
    // comma would win unjustly. So precedence: tab before semicolon before
    // comma, as soon as the respective character occurs at all. The comma
    // is the separator only where the German Excel world (semicolon plus
    // decimal comma) was certainly not at work.
    $firstLine = strtok($content, "\r\n") ?: '';
    $separator = ',';
    foreach (["\t", ';'] as $candidate) {
        if (substr_count($firstLine, $candidate) > 0) {
            $separator = $candidate;
            break;
        }
    }

    $rows = [];
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $content);
    rewind($handle);
    while (($z = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
        if (array_filter($z, static fn($c) => trim((string) $c) !== '') === []) {
            continue;   // blank line
        }
        $rows[] = array_map(static fn($c) => trim((string) $c), $z);
    }
    fclose($handle);

    if (count($rows) < 2) {
        throw new RuntimeException(t('Die Tabelle hat keine Datenzeilen.'));
    }

    // Header row: the first row, unless it consists only of numbers — then
    // it is already data, and the columns get makeshift names.
    $header = false;
    foreach ($rows[0] as $cell) {
        if ($cell !== '' && csv_number($cell) === null) {
            $header = true;
            break;
        }
    }

    $columns = [];
    $width   = max(array_map('count', $rows));
    for ($i = 0; $i < $width; $i++) {
        $name = $header ? trim((string) ($rows[0][$i] ?? '')) : '';
        $columns[] = $name !== '' ? $name : 'Spalte ' . ($i + 1);
    }

    return [
        'columns'   => $columns,
        'rows'      => array_slice($rows, $header ? 1 : 0),
        'separator' => $separator,
        'header'    => $header,
    ];
}

/**
 * Guess the interpretation: which column plays which role.
 *
 * @return array<string, int|string> role => column index or ''
 */
function csv_guess(array $data): array
{
    $interpretation = array_fill_keys(array_keys(CSV_ROLES), '');

    $names = array_map(static fn($n) => mb_strtolower(trim($n)), $data['columns']);

    foreach (CSV_NAMES as $role => $candidates) {
        // First the exact hit across all columns, then the containment —
        // otherwise "Länge" would snatch a column "Breitengrad (Länge
        // folgt)" via substring, although an exactly matching one stands
        // right beside it.
        foreach ($names as $idx => $name) {
            if ($interpretation[$role] === '' && in_array($name, $candidates, true)
                && !in_array($idx, $interpretation, true)) {
                $interpretation[$role] = $idx;
            }
        }
        foreach ($names as $idx => $name) {
            if ($interpretation[$role] !== '') {
                break;
            }
            foreach ($candidates as $candidate) {
                if (mb_strlen($candidate) >= 4 && str_contains($name, $candidate)
                    && !in_array($idx, $interpretation, true)) {
                    $interpretation[$role] = $idx;
                    break;
                }
            }
        }
    }

    // Combined coordinate column, in case lat/lon are not there individually.
    if ($interpretation['lat'] === '' && $interpretation['lon'] === '') {
        foreach ($names as $idx => $name) {
            if (in_array($name, CSV_POSITION_NAMES, true)) {
                $interpretation['lat'] = $interpretation['lon'] = $idx;
                break;
            }
        }
    }

    // The coordinate guess has to prove itself on the content: if the
    // values do not pass as coordinates, the name was a trap ("y" can also
    // be a counting column).
    foreach (['lat' => 90, 'lon' => 180] as $role => $limit) {
        if ($interpretation[$role] === '') {
            continue;
        }
        $filled = 0;
        foreach ($data['rows'] as $row) {
            $value = trim((string) ($row[$interpretation[$role]] ?? ''));
            if ($value === '') {
                continue;
            }
            $number = $interpretation['lat'] === $interpretation['lon']
                ? (csv_coordinate_pair($value)[$role === 'lat' ? 0 : 1] ?? null)
                : csv_number($value);
            if ($number === null || abs($number) > $limit) {
                $interpretation['lat'] = $interpretation['lon'] = '';
                continue 2;
            }
            $filled++;
        }
        if ($filled === 0) {
            $interpretation['lat'] = $interpretation['lon'] = '';
        }
    }

    return $interpretation;
}

/**
 * Take the interpretation over from the form — or guess if none came.
 * The registry hook: runs before every preview and before importing, so
 * that both compute THE SAME interpretation.
 */
function csv_interpret(array $data): array
{
    $submitted = $_POST['mapping'] ?? null;
    if (!is_array($submitted)) {
        $data['interpretation'] = csv_guess($data);
        return $data;
    }

    $interpretation = [];
    foreach (array_keys(CSV_ROLES) as $role) {
        $value = $submitted[$role] ?? '';
        $interpretation[$role] = ($value !== '' && isset($data['columns'][(int) $value]))
            ? (int) $value : '';
    }
    $data['interpretation'] = $interpretation;
    return $data;
}

/** Number with a point or a German comma, otherwise null. */
function csv_number(string $value): ?float
{
    $value = str_replace(',', '.', trim($value));
    return is_numeric($value) ? (float) $value : null;
}

/**
 * A coordinate pair from ONE cell: "50.1, 8.2", "50.1 8.2" or with German
 * commas "50,1; 8,2". Delivers [lat, lon] or null. Hopeless remains
 * "50,1,8,2" — four commas, no boundary discernible.
 */
function csv_coordinate_pair(string $value): ?array
{
    $value = trim($value);
    if (preg_match('~^(-?\d+(?:\.\d+)?)[,;\s]+(-?\d+(?:\.\d+)?)$~', $value, $t)
        || preg_match('~^(-?\d+(?:,\d+)?)[;\s]+(-?\d+(?:,\d+)?)$~', $value, $t)) {
        return [(float) str_replace(',', '.', $t[1]), (float) str_replace(',', '.', $t[2])];
    }
    return null;
}

/**
 * A point in time from a cell: German (8.9.24, 08.09.2024 09:17) or
 * anything strtotime understands (ISO, also with Z — then converted to
 * local time). Two-digit years up to the current year are 20xx, above that
 * 19xx — "97" is a find from 1997, not an appointment in 2097.
 */
function csv_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('~^(\d{1,2})\.(\d{1,2})\.(\d{2}(?:\d{2})?)(?:[ T]+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$~',
                   $value, $t)) {
        $year = (int) $t[3];
        if (strlen($t[3]) === 2) {
            $year += $year <= (int) date('y') ? 2000 : 1900;
        }
        if (!checkdate((int) $t[2], (int) $t[1], $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, (int) $t[2], (int) $t[1],
            min(23, (int) ($t[4] ?? 0)), min(59, (int) ($t[5] ?? 0)), min(59, (int) ($t[6] ?? 0)));
    }

    // strtotime only gets what looks like a date — a bare number like "42"
    // it would otherwise interpret into something or other.
    if (!preg_match('~[-/:.]|\d{4}~', $value)) {
        return null;
    }
    $t = strtotime($value);
    return $t === false ? null : date('Y-m-d H:i:s', $t);
}

/**
 * Interpret a row as a point — or null if the position is missing.
 */
function csv_point(array $row, array $interpretation, array $columns): ?array
{
    $value = static fn($role) => $interpretation[$role] === '' ? ''
        : trim((string) ($row[$interpretation[$role]] ?? ''));

    if ($interpretation['lat'] === $interpretation['lon']) {
        $pair = csv_coordinate_pair($value('lat'));
        [$lat, $lon] = $pair ?? [null, null];
    } else {
        $lat = csv_number($value('lat'));
        $lon = csv_number($value('lon'));
    }
    if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180
        || ($lat === 0.0 && $lon === 0.0)) {
        return null;
    }

    $title = $value('title');
    $note = [];
    if ($value('note') !== '') {
        $note[] = $value('note');
    }
    // Everything unassigned as "column: value" — unless it already stands
    // in the note column.
    foreach ($columns as $idx => $column) {
        if (in_array($idx, $interpretation, true)) {
            continue;
        }
        $cell = trim((string) ($row[$idx] ?? ''));
        if ($cell === '' || ($note !== [] && str_contains($note[0], $cell))) {
            continue;
        }
        $note[] = $column . ': ' . $cell;
    }

    $elevation = csv_number($value('height'));
    $accuracy = csv_number($value('accuracy'));

    return [
        'title'         => $title !== '' ? $title : 'Ohne Titel',
        'note'         => $note === [] ? null : implode("\n", $note),
        'lat'           => $lat,
        'lon'           => $lon,
        'found_at'   => csv_datetime($value('time')),
        // 0 here too means: no measurement but a filler value — as with
        // KML and GeoJSON. No geoid conversion, the DGM1 wins.
        'elevation_m'       => $elevation !== null && $elevation !== 0.0 ? (int) round($elevation) : null,
        'accuracy_m' => $accuracy !== null && $accuracy > 0 ? $accuracy : null,
        'find_number'  => $value('find_number') !== '' ? mb_substr($value('find_number'), 0, 80) : null,
    ];
}

/**
 * Import an interpreted table or do a dry run — the same two-step mechanic
 * as everywhere (takeout_import explains it).
 *
 * WITHOUT A LATITUDE AND LONGITUDE COLUMN the dry run computes nothing
 * (and reports 'incomplete', so that the preview urges the mapping
 * instead of showing numbers); the real run throws — no button leads
 * there, only a hand-built call.
 *
 * @return array{import: string, import_id: ?int, created: int,
 *               assigned: int, rows: int, columns: string[],
 *               interpretation: array, skipped: array[],
 *               incomplete: bool}
 */
function csv_import(PDO $pdo, int $userId, array $data, bool $real,
                        ?string $name = null, array $post = []): array
{
    require_once __DIR__ . '/takeout.php';   // takeout_key, takeout_list_add
    require_once __DIR__ . '/imports.php';

    $interpretation = $data['interpretation'] ?? csv_guess($data);

    $report = ['import' => import_name($pdo, $userId), 'import_id' => null,
                'created' => 0, 'assigned' => 0,
                'rows' => count($data['rows']), 'columns' => $data['columns'],
                'interpretation' => $interpretation, 'skipped' => [],
                'incomplete' => $interpretation['lat'] === '' || $interpretation['lon'] === ''];

    if ($report['incomplete']) {
        if ($real) {
            throw new InvalidArgumentException(
                'Ohne Breiten- und Längenspalte lässt sich nichts einspielen — bitte erst zuordnen.');
        }
        return $report;
    }

    /* EVERY ROW JUDGED ON ITS OWN, the preview's decisions applied
       (includes/import_screen.php). The rows are read first, all of them:
       the screening needs the whole file before the first verdict — which
       names lie where in it („vielleicht umbenannt"). */
    require_once __DIR__ . '/import_screen.php';
    $screen = import_screen_start($pdo, $userId, $post);

    $points = [];
    foreach ($data['rows'] as $idx => $row) {
        $p = csv_point($row, $interpretation, $data['columns']);
        $spreadsheetRow = $idx + ($data['header'] ? 2 : 1);   // counted as in the spreadsheet
        if ($p === null) {
            $report['skipped'][] = [
                'row' => $spreadsheetRow,
                'title' => $interpretation['title'] !== ''
                    ? trim((string) ($row[$interpretation['title']] ?? '')) : '',
            ];
            continue;
        }
        // The fields out of the note first: the preview shows what
        // applying will store, fields as fields — and the row number the
        // user finds in their spreadsheet.
        $p = import_fields($p);
        $p['_row'] = $spreadsheetRow;
        $points[] = $p;
    }
    import_screen_prepare($screen, $points);

    $import = $real ? import_create($pdo, $userId, 'csv', desiredName: $name) : null;
    $listId = $import['list_id'] ?? null;
    $report['import']    = $import['name'] ?? $report['import'];
    $report['import_id'] = $import['id'] ?? null;

    foreach ($points as $p) {
        $verdict = import_screen_row($pdo, $screen, $p, $real);
        if ($verdict['action'] === 'skip') {
            continue;
        }
        if ($verdict['action'] === 'existing') {
            $report['assigned']++;
            if ($real && $listId !== null && $verdict['point_id'] > 0) {
                takeout_list_add($pdo, $userId, $verdict['point_id'], $listId);
            }
            continue;
        }
        $p = $verdict['p'];

        $report['created']++;
        import_screen_created($screen, $p);
        if ($real) {
            $created = point_create($pdo, $userId, [
                'lat'           => $p['lat'],
                'lon'           => $p['lon'],
                'title'         => $p['title'],
                'note'         => $p['note'],
                'extra'       => $p['extra'] ?? [],
                'tour'        => $p['tour'] ?? null,
                'found_at'   => $p['found_at'],
                'elevation_m'       => $p['elevation_m'],
                'accuracy_m' => $p['accuracy_m'],
                'find_number'  => $p['find_number'] ?? null,
                'find_number_draw' => $p['find_number_draw'] ?? false,
                'import_id'     => $import['id'],
                'lists'        => $listId !== null ? [$listId] : [],
            ]);
            import_screen_made($screen, $p, $created);
        } else {
            import_screen_made($screen, $p, 0);
        }
    }

    if ($real) {
        import_screen_species($pdo, $userId, $screen, $import['list_id'] ?? null);
        import_finish($pdo, $import['id'], $report['created'], $report['assigned']);
    }

    $report['screen'] = import_screen_report($screen);
    return $report;
}
