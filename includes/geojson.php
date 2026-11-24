<?php
/**
 * Reading GeoJSON — the exchange format of the web maps (RFC 7946).
 *
 * uMap, Overpass, QGIS, geojson.io, plus plenty of converters (togeojson
 * turns GPX and KML into GeoJSON): what comes from the net often comes like
 * this. Features with POINT geometry are taken over.
 *
 * TWO THINGS MAKE THIS SOURCE PECULIAR:
 *
 * THE COORDINATES STAND AS [lon, lat] — flipped, as with KML. The third
 * number becomes the elevation (elevation_m), but only if it is not 0:
 * converters pad missing elevations with 0, and as with KML an invented 0
 * would be worse than none. No geoid conversion, the DGM1 wins on creation
 * anyway — the same trade-off as in gpx.php.
 *
 * THE PROPERTIES ARE A FREE-FORM JUMBLE. GeoJSON does not prescribe what
 * title or description are called — every application names them
 * differently. The usual names are fished for (lists below); whatever is
 * left afterwards hangs on the note as "field: value", so that nothing is
 * lost silently. Except the simplestyle cosmetics (marker-color and
 * relatives): that is paint, not content.
 *
 * LINES, AREAS AND OTHER GEOMETRIES STAY OUT — counted and named in the
 * report, as with GPX and KML.
 */

declare(strict_types=1);

require_once __DIR__ . '/gpx.php';   // gpx_datetime: the same UTC conversion
require_once __DIR__ . '/kml.php';   // kml_text: descriptions come as HTML here too

/** Usual names for the title, in checking order. */
const GEOJSON_TITLE = ['name', 'Name', 'title', 'Title', 'title', 'Titel'];

/** Usual names for the description. */
const GEOJSON_DESCRIPTION = ['description', 'Description', 'desc', 'beschreibung',
                              'Beschreibung', 'note', 'Notiz', 'comment', 'cmt',
                              'bemerkung', 'Bemerkung'];

/** Usual names for the timestamp (togeojson writes 'time' from GPX). */
const GEOJSON_TIME = ['time', 'timestamp', 'when', 'date', 'datum'];

/**
 * The find number — the property our own export writes (export_geojson).
 * Read straight into the number and not through the note: there the
 * leftover properties skip a value the description already contains, and
 * a short number like „12" would vanish into any description with a 12.
 */
const GEOJSON_NUMBER = ['Fundnummer', 'fundnummer'];

/** Simplestyle and converter leftovers: paint, not content — thrown out. */
const GEOJSON_COSMETIC = ['marker-color', 'marker-size', 'marker-symbol',
                          'stroke', 'stroke-width', 'stroke-opacity',
                          'fill', 'fill-opacity', 'styleUrl', 'styleHash',
                          'styleMapHash', 'icon', 'visibility'];

/**
 * Read out a GeoJSON file.
 *
 * @return array{points: array[], skipped: int, ways: int}
 */
function geojson_read(string $path): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException(t('Datei nicht lesbar: %s', basename($path)));
    }

    $json = json_decode($content, true);
    if (!is_array($json) || !isset($json['type'])) {
        throw new RuntimeException(t('Das sieht nicht nach einer GeoJSON-Datei aus.'));
    }

    // Bring all three permitted top-level shapes onto one feature list:
    // the collection, the single feature, the bare geometry.
    $features = match ($json['type']) {
        'FeatureCollection' => is_array($json['features'] ?? null) ? $json['features'] : [],
        'Feature'           => [$json],
        default             => [['type' => 'Feature', 'geometry' => $json, 'properties' => []]],
    };

    $collected = ['points' => [], 'skipped' => 0, 'ways' => 0];
    foreach ($features as $feature) {
        if (!is_array($feature)) {
            $collected['skipped']++;
            continue;
        }
        geojson_feature($feature, $collected);
    }
    return $collected;
}

/** Sort in a single feature — or count why not. */
function geojson_feature(array $feature, array &$collected): void
{
    $geometry = $feature['geometry'] ?? null;
    if (!is_array($geometry) || !isset($geometry['type'])) {
        $collected['skipped']++;   // feature without geometry: allowed, but placeless
        return;
    }

    // A MultiPoint with exactly one position is the same point in different
    // clothing — that one we take. Everything else (lines, areas, real
    // multi-points, collections) stays out: not finds.
    $position = match (true) {
        $geometry['type'] === 'Point'
            => $geometry['coordinates'] ?? null,
        $geometry['type'] === 'MultiPoint' && is_array($geometry['coordinates'] ?? null)
            && count($geometry['coordinates']) === 1
            => $geometry['coordinates'][0],
        default => false,
    };
    if ($position === false) {
        $collected['ways']++;
        return;
    }

    $coords = geojson_coordinates($position);
    if ($coords === null) {
        $collected['skipped']++;
        return;
    }

    $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
    $title = geojson_property($props, GEOJSON_TITLE);

    $collected['points'][] = [
        'title'       => $title !== '' ? $title : 'Ohne Titel',
        'note'       => geojson_note($props),
        'lat'         => $coords[0],
        'lon'         => $coords[1],
        'found_at' => gpx_datetime(geojson_property($props, GEOJSON_TIME)),
        'elevation_m'     => $coords[2],
        'find_number' => mb_substr(geojson_property($props, GEOJSON_NUMBER), 0, 80),
    ];
}

/**
 * Check a position: [lon, lat(, alt)] — longitude first, as with KML.
 * Returns [lat, lon, elevation_m|null] or null for the unusable (including 0/0,
 * the Gulf of Guinea of empty receivers).
 */
function geojson_coordinates(mixed $position): ?array
{
    if (!is_array($position) || count($position) < 2
        || !is_numeric($position[0]) || !is_numeric($position[1])) {
        return null;
    }
    $lon = (float) $position[0];
    $lat = (float) $position[1];
    if (abs($lat) > 90 || abs($lon) > 180 || ($lat === 0.0 && $lon === 0.0)) {
        return null;
    }

    $elevation = null;
    if (isset($position[2]) && is_numeric($position[2]) && (float) $position[2] !== 0.0) {
        $elevation = (int) round((float) $position[2]);
    }
    return [$lat, $lon, $elevation];
}

/** The first non-empty scalar among the given names, otherwise ''. */
function geojson_property(array $props, array $names): string
{
    foreach ($names as $name) {
        $value = $props[$name] ?? null;
        if (is_scalar($value) && !is_bool($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }
    }
    return '';
}

/**
 * Description plus leftover properties as the note — see header comment.
 * Nested things (objects, lists) stay out: a JSON lump in the note would
 * help nobody, and the original file is attached to the import.
 */
function geojson_note(array $props): ?string
{
    $parts = [];
    $description = kml_text(geojson_property($props, GEOJSON_DESCRIPTION));
    if ($description !== null) {
        $parts[] = $description;
    }

    $consumed = [...GEOJSON_TITLE, ...GEOJSON_DESCRIPTION, ...GEOJSON_TIME, ...GEOJSON_NUMBER, ...GEOJSON_COSMETIC];
    foreach ($props as $field => $value) {
        if (in_array((string) $field, $consumed, true)
            || !is_scalar($value) || is_bool($value)) {
            continue;
        }
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        if ($description !== null && str_contains($description, $value)) {
            continue;   // already contained in the description
        }
        $parts[] = $field . ': ' . $value;
    }

    return $parts === [] ? null : implode("\n", $parts);
}

/**
 * Apply a read GeoJSON file or do a dry run — the same two-step mechanism
 * as everywhere (takeout_import explains it).
 *
 * EVERYTHING GOES DIRECTLY INTO THE IMPORT LIST: GeoJSON knows no folders,
 * there is nothing to branch out. Without the species mark — which
 * subject-matter list the points should carry, the human decides after the
 * import.
 *
 * @return array{import: string, import_id: ?int, created: int,
 *               assigned: int, skipped: int, ways: int}
 */
function geojson_import(PDO $pdo, int $userId, array $data, bool $apply,
                            ?string $name = null, array $post = []): array
{
    require_once __DIR__ . '/takeout.php';   // takeout_key, takeout_list_add
    require_once __DIR__ . '/imports.php';

    /* THE TRASH AND OTHER PEOPLE'S FINDS stay out, and the preview says
       which and why (includes/import_screen.php). */
    require_once __DIR__ . '/import_screen.php';
    $screen = import_screen_start($pdo, $userId, $post);
    import_screen_prepare($screen, $data['points']);

    $import  = $apply ? import_create($pdo, $userId, 'geojson', desiredName: $name) : null;
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
