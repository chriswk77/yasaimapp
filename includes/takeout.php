<?php
/**
 * Reading Google Takeout.
 *
 * What Takeout emits: one CSV per saved list, with the columns Titel, Notiz,
 * URL, Tags, Kommentar. The coordinates are nowhere in a column of their
 * own — they hide, if at all, in the URL.
 *
 * And that is exactly the catch. Google writes two different sorts:
 *
 *   .../maps/search/49.6558946,7.5029825
 *       A point placed by hand. The coordinates are in there, done.
 *
 *   .../maps/place/Murnauer+Moos/data=!4m2!3m1!1s0x479daeeafecf04...
 *       A place from Google's own directory. Instead of coordinates there
 *       is a Google-internal id. The location cannot be computed back from
 *       it — two different places in the export carry the same first id,
 *       so it describes no point.
 *
 * Such entries are not guessed but reported as "ohne Position". A place
 * search by name would be the obvious move, but it leads astray:
 * "Mangfalltal" yields a dermatologist's practice, "Alter Steinbruch" a
 * place in Saxony. A wrongly placed point is worse than a missing one.
 */

declare(strict_types=1);

/** Patterns for coordinates in the various Google URL shapes. */
const TAKEOUT_COORDINATES = [
    '~/maps/search/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)~',
    '~/maps/place/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)~',
    '~@(-?\d+\.\d+),(-?\d+\.\d+)~',
    '~[?&]q=(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)~',
    '~[?&]ll=(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)~',
];

/**
 * Read out a Takeout ZIP file.
 *
 * Returns: ['lists' => [name => ['points' => [...], 'withoutPosition' => [...]]]]
 */
function takeout_read(string $zipPath): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('ZIP-Datei nicht lesbar: ' . basename($zipPath));
    }

    $lists = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if (!str_ends_with(strtolower($entry), '.csv')) {
            continue;
        }

        $content = $zip->getFromIndex($i);
        if ($content === false) {
            continue;
        }

        $name = takeout_list_name($entry);
        $lists[$name] = takeout_csv_read($content);
    }
    $zip->close();

    return ['lists' => $lists];
}

/**
 * List name from the path inside the archive.
 *
 * The names in the ZIP directory are mangled to CP437 as soon as umlauts
 * occur — "Urwälder" becomes "Urw+?lder". The original name can be
 * recovered by reading the bytes as UTF-8 again.
 */
function takeout_list_name(string $path): string
{
    $file = basename($path, '.csv');

    $recovered = @iconv('UTF-8', 'CP437//IGNORE', $file);
    if ($recovered !== false && mb_check_encoding($recovered, 'UTF-8')) {
        $file = $recovered;
    }
    return trim($file) !== '' ? trim($file) : 'Ohne Namen';
}

/** Evaluate a single Takeout CSV. */
function takeout_csv_read(string $content): array
{
    $content = preg_replace('~^\xEF\xBB\xBF~', '', $content);   // BOM

    $rows = [];
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $content);
    rewind($handle);
    while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    // The header row is not always at the top: one list carries its own
    // heading above it ("Rote Liste Arten, sowie ökologische Nischen-Arten").
    $headerRow = 0;
    foreach ($rows as $nr => $row) {
        if (isset($row[0]) && trim($row[0]) === 'Titel') {
            $headerRow = $nr;
            break;
        }
    }
    $columns = $rows[$headerRow] ?? [];

    $points = [];
    $withoutPosition = [];

    foreach (array_slice($rows, $headerRow + 1) as $row) {
        if (!array_filter($row, static fn($c) => trim((string) $c) !== '')) {
            continue;   // blank line
        }
        $e = [];
        foreach ($columns as $nr => $column) {
            $e[trim((string) $column)] = trim((string) ($row[$nr] ?? ''));
        }

        $title = $e['Titel'] ?? '';
        $url   = $e['URL'] ?? '';
        $note = takeout_note($e);

        $coord = takeout_coordinates($url);
        if ($coord === null) {
            $withoutPosition[] = ['title' => $title, 'note' => $note, 'url' => $url];
            continue;
        }

        $points[] = [
            'title' => $title !== '' ? $title : 'Ohne Titel',
            'note' => $note,
            'lat'   => $coord[0],
            'lon'   => $coord[1],
        ];
    }

    return ['points' => $points, 'withoutPosition' => $withoutPosition];
}

/** Coordinates from a Google Maps URL, or null. */
function takeout_coordinates(string $url): ?array
{
    foreach (TAKEOUT_COORDINATES as $pattern) {
        if (preg_match($pattern, $url, $t)) {
            $lat = (float) $t[1];
            $lon = (float) $t[2];
            if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
                return [$lat, $lon];
            }
        }
    }
    return null;
}

/**
 * Merge note and comment.
 *
 * Google keeps both fields separate, but what is meant is the same remark
 * about the place. Both deserve to be preserved; only the export keeps them
 * apart.
 */
function takeout_note(array $e): ?string
{
    $parts = array_filter([
        $e['Notiz'] ?? '',
        $e['Kommentar'] ?? '',
        ($e['Tags'] ?? '') !== '' ? 'Tags: ' . $e['Tags'] : '',
    ], static fn($t) => trim((string) $t) !== '');

    return $parts === [] ? null : implode("\n", $parts);
}

// ---------------------------------------------------------------------------
// Importing — ONE computation for command line and administration.
//
// The dry run is the same pass with $forReal = false: what it announces,
// the real thing then also does. Two separate computations would be the
// surest way to build a preview that promises something other than what
// happens afterwards.
// ---------------------------------------------------------------------------

/**
 * Import a read Takeout, or dry-run it.
 *
 * EVERY IMPORT IS A BRACKET OF ITS OWN (migration 0045): a root list
 * "Import <Datum>", below it one leaf per Takeout list bearing its name.
 * NOTHING is sorted into existing lists — an import spread across the
 * existing stock could never be taken out again, and precisely that is the
 * point of the bracket. Whoever wants the points elsewhere assigns them
 * afterwards and removes the import list.
 *
 * NO TIMES. Google does not store when a place was saved — the points
 * arrive without a find time (found_at stays empty) and carry the moment
 * of the import as their creation time. That belongs in every announcement,
 * for in the time filter and the buckets they thus all sit in the import
 * month.
 *
 * Duplicates (title plus coordinates) are not created anew but merely
 * additionally assigned to the import list — they carry no import id and
 * survive the import being withdrawn.
 *
 * @return array{import: string, lists: array[], created: int,
 *               assigned: int, withoutPosition: array[]}
 */
function takeout_import(PDO $pdo, int $userId, array $data, bool $forReal,
                            ?string $name = null, array $post = []): array
{
    require_once __DIR__ . '/imports.php';

    /* THE TRASH AND OTHER PEOPLE'S FINDS stay out, and the preview says
       which and why (includes/import_screen.php). */
    require_once __DIR__ . '/import_screen.php';
    $screen = import_screen_start($pdo, $userId, $post);
    import_screen_prepare($screen, array_merge(...array_values(array_map(
        static fn(array $list): array => $list['points'], $data['lists']))));

    $import = $forReal ? import_create($pdo, $userId, 'takeout', desiredName: $name, several: true) : null;

    // Colors in rotation, so the leaves are tellable apart in the sidebar.
    $colors = ['#6aa84f', '#3a86c8', '#d98b3a', '#a05fc8', '#c8506a', '#3aa8a0', '#8a8f3a', '#c85f3a'];
    $colorNo = 0;

    $report = ['import' => $import['name'] ?? import_name($pdo, $userId),
                'import_id' => $import['id'] ?? null,
                'lists' => [], 'created' => 0, 'assigned' => 0, 'withoutPosition' => []];

    foreach ($data['lists'] as $listName => $list) {
        if ($list['points'] === [] && $list['withoutPosition'] === []) {
            $report['lists'][] = ['name' => $listName, 'empty' => true,
                                    'new' => 0, 'assigned' => 0, 'without' => 0];
            continue;
        }

        $listId = null;
        if ($forReal) {
            $listId = import_list($pdo, $userId, $import, (string) $listName, $colors[$colorNo % count($colors)]);
        }
        $colorNo++;

        $added = $assigned = 0;
        foreach ($list['points'] as $p) {
            $p = import_fields($p);
            /* ONE VERDICT PER ENTRY, the decisions of the preview applied
               (import_screen_row): skip it, file a find here already, or create. */
            $verdict = import_screen_row($pdo, $screen, $p, $forReal);
            if ($verdict['action'] === 'skip') {
                continue;
            }
            $key = takeout_key((string) $p['title'], (float) $p['lat'], (float) $p['lon']);
            // Already there — then it additionally belongs to this list.
            if ($verdict['action'] === 'existing') {
                $assigned++;
                if ($forReal && $listId !== null && $verdict['point_id'] > 0) {
                    takeout_list_add($pdo, $userId, $verdict['point_id'], $listId);
                }
                continue;
            }
            $p = $verdict['p'];

            $added++;
            import_screen_created($screen, $p);
            if ($forReal) {
                $created = point_create($pdo, $userId, [
                    'lat'    => $p['lat'],
                    'lon'    => $p['lon'],
                    'title'  => $p['title'],
                    'note'  => $p['note'],
                    'extra'       => $p['extra'] ?? [],
                    'tour'        => $p['tour'] ?? null,
                    // Explicitly null: Google does not store when a place was
                    // saved — empty is honest, now would be invented.
                    'found_at' => null,
                    'find_number' => $p['find_number'] ?? null,
                    'find_number_draw' => $p['find_number_draw'] ?? false,
                    'import_id'   => $import['id'],
                    'lists' => $listId !== null ? [$listId] : [],
                ]);
            import_screen_made($screen, $p, $created);
            } else {
                import_screen_made($screen, $p, 0);
            }
        }

        foreach ($list['withoutPosition'] as $o) {
            $report['withoutPosition'][] = ['list' => $listName] + $o;
        }

        $report['created']   += $added;
        $report['assigned'] += $assigned;
        $report['lists'][]    = ['name' => $listName, 'empty' => false,
                                   'new' => $added, 'assigned' => $assigned,
                                   'without' => count($list['withoutPosition'])];
    }

    if ($forReal) {
        import_screen_species($pdo, $userId, $screen, $import['list_id'] ?? null);
        import_finish($pdo, $import['id'], $report['created'], $report['assigned']);
    }

    $report['screen'] = import_screen_report($screen);
    return $report;
}

/**
 * Additionally assign an existing point to a list without losing its
 * previous ones — point_lists_set() replaces the assignment completely,
 * which is why the previous state is read first.
 */
function takeout_list_add(PDO $pdo, int $userId, int $pointId, int $listId): void
{
    $stmt = $pdo->prepare('SELECT list_id FROM map_point_lists WHERE point_id = ?');
    $stmt->execute([$pointId]);
    $current = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (in_array($listId, $current, true)) {
        return;
    }
    point_lists_set($pdo, $userId, $pointId, [...$current, $listId]);
}

/** Identifying mark for duplicates: title plus coordinates to six places. */
function takeout_key(string $title, float $lat, float $lon): string
{
    return mb_strtolower(trim($title)) . '|' . number_format($lat, 6, '.', '')
         . '|' . number_format($lon, 6, '.', '');
}
