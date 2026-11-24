<?php
/**
 * Reading GPX — the exchange format of GPS devices and outdoor apps.
 *
 * Garmin, Locus, OsmAnd, OruxMaps, Komoot: whoever presses "export"
 * somewhere mostly gets GPX. The WAYPOINTS (<wpt>) are taken over:
 *
 *   lat/lon   the mandatory attributes of every waypoint
 *   name      becomes the title
 *   desc, cmt become the note (both, if they differ — some programs write
 *             the same text into both fields)
 *   time      becomes the find time: on a GPS device that is the moment
 *             someone pressed "mark waypoint" out in the field — exactly
 *             the clock that stood next to the find. GPX writes UTC; it is
 *             converted into the installation's time.
 *   ele       becomes the elevation, as elevation_m: GPX elevations usually
 *             refer to sea level already — sending them through the geoid
 *             conversion once more (the path for raw receiver heights)
 *             would turn every elevation into a wrong one. On creation
 *             point_create asks the DGM1 anyway — if it delivers, the
 *             terrain elevation wins as everywhere; the ele remains the
 *             fallback for everything outside the coverage.
 *
 * TRACKS AND ROUTES STAY OUT — they are paths, not finds. A track with
 * thousands of position points would flood the map as a point cloud. They
 * are counted and named in the report so nobody thinks they were lost.
 */

declare(strict_types=1);

/**
 * Read out a GPX file.
 *
 * @return array{points: array[], skipped: int, ways: int}
 */
function gpx_read(string $path): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException(t('Datei nicht lesbar: %s', basename($path)));
    }

    // No LIBXML_NOENT: external entities stay shut — an uploaded file is
    // foreign content, even if it comes from one's own GPS device.
    $xml = @simplexml_load_string($content);
    if ($xml === false || strtolower($xml->getName()) !== 'gpx') {
        throw new RuntimeException(t('Das sieht nicht nach einer GPX-Datei aus.'));
    }

    $points = [];
    $skipped = 0;

    foreach ($xml->wpt as $w) {
        $lat = isset($w['lat']) ? (float) $w['lat'] : null;
        $lon = isset($w['lon']) ? (float) $w['lon'] : null;
        if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180
            || ($lat === 0.0 && $lon === 0.0)) {
            // 0/0 is the Gulf of Guinea — nobody marks a find there, but
            // that is exactly what some devices write when reception is
            // missing. Better skipped than placed wrongly.
            $skipped++;
            continue;
        }

        $name = trim((string) $w->name);
        $ele  = trim((string) $w->ele);

        $points[] = [
            'title'       => $name !== '' ? $name : 'Ohne Titel',
            'note'       => gpx_note((string) $w->desc, (string) $w->cmt),
            'lat'         => $lat,
            'lon'         => $lon,
            'found_at' => gpx_datetime((string) $w->time),
            'elevation_m'     => is_numeric($ele) ? (int) round((float) $ele) : null,
        ];
    }

    return [
        'points'      => $points,
        'skipped' => $skipped,
        'ways'        => count($xml->trk) + count($xml->rte),
    ];
}

/** desc and cmt as the note — both only if they say different things. */
function gpx_note(string $desc, string $cmt): ?string
{
    $desc = trim($desc);
    $cmt  = trim($cmt);
    if ($desc === '' && $cmt === '') {
        return null;
    }
    if ($desc === '' || $cmt === '' || $desc === $cmt) {
        return $desc !== '' ? $desc : $cmt;
    }
    return $desc . "\n" . $cmt;
}

/** The waypoint's UTC time in the installation's time, or null. */
function gpx_datetime(string $time): ?string
{
    $time = trim($time);
    if ($time === '') {
        return null;
    }
    $t = strtotime($time);
    return $t === false ? null : date('Y-m-d H:i:s', $t);
}

/**
 * Apply a read GPX file or do a dry run — the same two-step mechanism as
 * with the other sources (takeout_import).
 *
 * EVERYTHING INTO THE IMPORT LIST, without the species mark: waypoints are
 * places, not finds — which subject-matter list they should carry in the
 * end, the human decides after the import. Duplicates (title plus
 * coordinates) are only assigned, as everywhere.
 *
 * @return array{import: string, import_id: ?int, created: int,
 *               assigned: int, skipped: int, ways: int}
 */
function gpx_import(PDO $pdo, int $userId, array $data, bool $apply,
                        ?string $name = null, array $post = []): array
{
    require_once __DIR__ . '/takeout.php';   // takeout_key, takeout_list_add
    require_once __DIR__ . '/imports.php';

    /* THE TRASH AND OTHER PEOPLE'S FINDS stay out, and the preview says
       which and why (includes/import_screen.php). */
    require_once __DIR__ . '/import_screen.php';
    $screen = import_screen_start($pdo, $userId, $post);
    import_screen_prepare($screen, $data['points']);

    $import  = $apply ? import_create($pdo, $userId, 'gpx', desiredName: $name) : null;
    $listId = $import['list_id'] ?? null;

    $report = ['import' => $import['name'] ?? import_name($pdo, $userId),
                'import_id' => $import['id'] ?? null,
                'created' => 0, 'assigned' => 0,
                'skipped' => $data['skipped'], 'ways' => $data['ways']];

    foreach ($data['points'] as $p) {
        $p = import_fields($p);
        /* ONE VERDICT PER ENTRY, the decisions of the preview applied
           (import_screen_row): skip it, file a find here already, or create. */
        $verdict = import_screen_row($pdo, $screen, $p, $apply);
        if ($verdict['action'] === 'skip') {
            continue;
        }
        $key = takeout_key((string) $p['title'], (float) $p['lat'], (float) $p['lon']);
        if ($verdict['action'] === 'existing') {
            $report['assigned']++;
            if ($apply && $listId !== null && $verdict['point_id'] > 0) {
                takeout_list_add($pdo, $userId, $verdict['point_id'], $listId);
            }
            continue;
        }
        $p = $verdict['p'];

        $report['created']++;
        import_screen_created($screen, $p);
        if ($apply) {
            $created = point_create($pdo, $userId, [
                'lat'         => $p['lat'],
                'lon'         => $p['lon'],
                'title'       => $p['title'],
                'note'       => $p['note'],
                'extra'       => $p['extra'] ?? [],
                'tour'        => $p['tour'] ?? null,
                'found_at' => $p['found_at'],
                'elevation_m'     => $p['elevation_m'],
                'find_number' => $p['find_number'] ?? null,
                'find_number_draw' => $p['find_number_draw'] ?? false,
                'import_id'   => $import['id'],
                'lists'      => $listId !== null ? [$listId] : [],
            ]);
        import_screen_made($screen, $p, $created);
        } else {
            import_screen_made($screen, $p, 0);
        }
    }

    if ($apply) {
        import_screen_species($pdo, $userId, $screen, $import['list_id'] ?? null);
        import_finish($pdo, $import['id'], $report['created'], $report['assigned']);
    }

    $report['screen'] = import_screen_report($screen);
    return $report;
}
