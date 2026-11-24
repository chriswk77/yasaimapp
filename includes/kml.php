<?php
/**
 * Reading KML and KMZ — the format of Google Earth and Google My Maps.
 *
 * Google Earth ("Ort speichern unter"), My Maps ("In KML/KMZ exportieren"),
 * Maps.me, Organic Maps: they all write KML, often as KMZ — the same file,
 * just packed into a ZIP. What gets taken over are the PLACEMARKS
 * (<Placemark> with <Point>):
 *
 *   name          becomes the title
 *   description   becomes the note. It often arrives as HTML in CDATA —
 *                 Google Earth writes <br> and whole tables into it; what
 *                 gets stored is the text from it, not the markup.
 *   ExtendedData  the table columns of My Maps. They are appended to the
 *                 note as "column: value", so nothing is lost silently —
 *                 unless the value is already in the description anyway
 *                 (My Maps likes to write both).
 *   TimeStamp     becomes the find time, UTC → creation-time conversion as
 *                 with GPX.
 *   coordinates   lon,lat[,alt] — careful, THE OTHER WAY ROUND than
 *                 everywhere else. The third number becomes the elevation
 *                 (elevation_m), but only if it is not 0: in KML, 0 almost
 *                 always means "clampToGround", i.e. no measurement. As
 *                 with GPX it does NOT go through the geoid conversion;
 *                 on creation the DGM1 wins anyway.
 *
 * FOLDERS BECOME LISTS — but flat, with the path as the name ("Wälder /
 * Buchen"): import_remove() clears away exactly one level below the
 * import root when rolling back; a deeper tree would let orphaned
 * grandchildren move up on deletion. The path name preserves the ordering
 * regardless.
 *
 * LINES AND POLYGONS STAY OUT — tracks, routes, area outlines are not
 * finds. They are counted and named in the report.
 */

declare(strict_types=1);

require_once __DIR__ . '/gpx.php';   // gpx_datetime: the same UTC conversion

/**
 * Read a KML or KMZ file.
 *
 * @return array{lists: array[], skipped: int, ways: int}
 *         lists: one entry {name, points} per folder path
 */
function kml_read(string $path): array
{
    // No LIBXML_NOENT: external entities stay shut — foreign content.
    // LIBXML_NOCDATA on the other hand yes: description mostly sits in
    // CDATA, and without the option, passing the tree on would yield
    // empty text.
    $xml = @simplexml_load_string(kml_content($path), SimpleXMLElement::class, LIBXML_NOCDATA);
    if ($xml === false || strtolower($xml->getName()) !== 'kml') {
        throw new RuntimeException(t('Das sieht nicht nach einer KML-Datei aus.'));
    }

    $collected = ['groups' => [], 'skipped' => 0, 'ways' => 0];
    kml_layer($xml, '', $collected);

    // Placemarks outside any folder: if nothing else is in the file, the
    // name does not matter (they land directly in the import list). If
    // folders exist alongside, the group is named after the document —
    // with My Maps that is the map name, i.e. what the person typed
    // themselves.
    $documentName = trim((string) ($xml->Document->name ?? ''));

    $lists = [];
    foreach ($collected['groups'] as $groupPath => $points) {
        $lists[] = [
            'name'   => $groupPath !== '' ? $groupPath
                      : ($documentName !== '' ? $documentName : 'Ohne Ordner'),
            'points' => $points,
        ];
    }

    return ['lists' => $lists, 'skipped' => $collected['skipped'], 'ways' => $collected['ways']];
}

/** Get hold of the KML text — from the file itself or from the KMZ within. */
function kml_content(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException(t('Datei nicht lesbar: %s', basename($path)));
    }
    if (!str_starts_with($content, "PK\x03\x04")) {
        return $content;
    }

    // KMZ. The convention says doc.kml in the root directory; whoever does
    // not stick to it gets the first .kml that can be found.
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException(t('KMZ-Datei nicht lesbar: %s', basename($path)));
    }
    $index = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = strtolower((string) $zip->getNameIndex($i));
        if ($entry === 'doc.kml') {
            $index = $i;
            break;
        }
        if ($index === null && str_ends_with($entry, '.kml')) {
            $index = $i;
        }
    }
    $content = $index === null ? false : $zip->getFromIndex($index);
    $zip->close();

    if ($content === false) {
        throw new RuntimeException(t('In der KMZ-Datei steckt keine KML-Datei.'));
    }
    return $content;
}

/**
 * Walk one level: collect placemarks, descend into documents (without
 * extending the path — the document name is the map title, not an
 * ordering), folders deepen the path.
 */
function kml_layer(SimpleXMLElement $level, string $path, array &$collected): void
{
    foreach ($level->Placemark as $placemark) {
        kml_placemark($placemark, $path, $collected);
    }
    foreach ($level->Document as $document) {
        kml_layer($document, $path, $collected);
    }
    foreach ($level->Folder as $folder) {
        $name = trim((string) $folder->name);
        $name = $name !== '' ? $name : 'Ohne Namen';
        kml_layer($folder, $path === '' ? $name : $path . ' / ' . $name, $collected);
    }
}

/** File a single placemark — or count why not. */
function kml_placemark(SimpleXMLElement $placemark, string $path, array &$collected): void
{
    $point = $placemark->Point;
    if (!isset($point[0]) && isset($placemark->MultiGeometry->Point[0])) {
        // MultiGeometry with a point in it: the point counts, the rest not.
        $point = $placemark->MultiGeometry->Point;
    }
    if (!isset($point[0])) {
        $gx = $placemark->children('http://www.google.com/kml/ext/2.2');
        if (isset($placemark->LineString[0]) || isset($placemark->Polygon[0])
            || isset($placemark->MultiGeometry[0]) || isset($gx->Track[0])) {
            $collected['ways']++;
        } else {
            $collected['skipped']++;
        }
        return;
    }

    $coords = kml_coordinates((string) $point[0]->coordinates);
    if ($coords === null) {
        $collected['skipped']++;
        return;
    }

    $name = trim((string) $placemark->name);
    $collected['groups'][$path][] = [
        'title'       => $name !== '' ? $name : 'Ohne Titel',
        'note'       => kml_note($placemark),
        'lat'         => $coords[0],
        'lon'         => $coords[1],
        'found_at' => gpx_datetime((string) $placemark->TimeStamp->when),
        'elevation_m'     => $coords[2],
    ];
}

/**
 * Coordinates of a point: "lon,lat[,alt]" — longitude first, this is the
 * one place where KML flips the order. Returns [lat, lon, elevation_m|null]
 * or null for anything unusable (including 0/0, the Gulf of Guinea,
 * which devices write when reception is missing).
 */
function kml_coordinates(string $raw): ?array
{
    $parts = preg_split('~[,\s]+~', trim($raw)) ?: [];
    if (count($parts) < 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
        return null;
    }
    $lon = (float) $parts[0];
    $lat = (float) $parts[1];
    if (abs($lat) > 90 || abs($lon) > 180 || ($lat === 0.0 && $lon === 0.0)) {
        return null;
    }

    $elevation = null;
    if (isset($parts[2]) && is_numeric($parts[2]) && (float) $parts[2] !== 0.0) {
        $elevation = (int) round((float) $parts[2]);
    }
    return [$lat, $lon, $elevation];
}

/** Description and ExtendedData as the note — see the header comment. */
function kml_note(SimpleXMLElement $placemark): ?string
{
    $parts = [];
    $description = kml_text((string) $placemark->description);
    if ($description !== null) {
        $parts[] = $description;
    }

    if (isset($placemark->ExtendedData)) {
        foreach ($placemark->ExtendedData->Data as $entry) {
            $value = trim((string) $entry->value);
            if ($value === '') {
                continue;
            }
            if ($description !== null && str_contains($description, $value)) {
                continue;   // already in the description — My Maps likes to write both
            }
            $field = trim((string) $entry->displayName);
            $field = $field !== '' ? $field : trim((string) ($entry['name'] ?? ''));
            $parts[] = ($field !== '' ? $field . ': ' : '') . $value;
        }
    }

    return $parts === [] ? null : implode("\n", $parts);
}

/** HTML from a description to plain text — <br> becomes a line break. */
function kml_text(string $raw): ?string
{
    $text = preg_replace('~<br\s*/?\s*>~i', "\n", $raw);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r", "\u{00A0}"], ['', ' '], $text);
    $text = trim((string) preg_replace('~\n{3,}~', "\n\n", $text));
    return $text !== '' ? $text : null;
}

/**
 * Apply a parsed KML or dry-run it — the same two-step mechanic as
 * everywhere (takeout_import explains it).
 *
 * ONE FOLDER → STRAIGHT INTO THE ROOT, several → one leaf per folder
 * under the import list, as with Takeout. Without the species flag:
 * placemarks are places, not finds — the person picks the proper list
 * afterwards.
 *
 * @return array{import: string, import_id: ?int, lists: array[],
 *               created: int, assigned: int, skipped: int, ways: int}
 */
function kml_import(PDO $pdo, int $userId, array $data, bool $apply,
                        ?string $name = null, array $post = []): array
{
    require_once __DIR__ . '/takeout.php';   // takeout_key, takeout_list_add
    require_once __DIR__ . '/imports.php';

    /* THE TRASH AND OTHER PEOPLE'S FINDS stay out, and the preview says
       which and why (includes/import_screen.php). */
    require_once __DIR__ . '/import_screen.php';
    $screen = import_screen_start($pdo, $userId, $post);
    import_screen_prepare($screen, array_merge(...array_values(array_map(
        static fn(array $list): array => $list['points'], $data['lists']))));

    $single = count($data['lists']) <= 1;
    $import  = $apply ? import_create($pdo, $userId, 'kml', desiredName: $name, several: !$single) : null;

    $colors = ['#6aa84f', '#3a86c8', '#d98b3a', '#a05fc8', '#c8506a', '#3aa8a0', '#8a8f3a', '#c85f3a'];
    $colorNo = 0;

    $report = ['import' => $import['name'] ?? import_name($pdo, $userId),
                'import_id' => $import['id'] ?? null,
                'lists' => [], 'created' => 0, 'assigned' => 0,
                'skipped' => $data['skipped'], 'ways' => $data['ways']];

    foreach ($data['lists'] as $list) {
        $listId = null;
        if ($apply) {
            $listId = $single ? $import['list_id']
                : import_list($pdo, $userId, $import, (string) $list['name'], $colors[$colorNo % count($colors)]);
        }
        $colorNo++;

        $new = $assigned = 0;
        foreach ($list['points'] as $p) {
            $p = import_fields($p);
            /* ONE VERDICT PER ENTRY, the decisions of the preview applied
               (import_screen_row): skip it, file a find here already, or create. */
            $verdict = import_screen_row($pdo, $screen, $p, $apply);
            if ($verdict['action'] === 'skip') {
                continue;
            }
            $key = takeout_key((string) $p['title'], (float) $p['lat'], (float) $p['lon']);
            if ($verdict['action'] === 'existing') {
                $assigned++;
                if ($apply && $listId !== null && $verdict['point_id'] > 0) {
                    takeout_list_add($pdo, $userId, $verdict['point_id'], $listId);
                }
                continue;
            }
            $p = $verdict['p'];

            $new++;
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

        $report['created']   += $new;
        $report['assigned'] += $assigned;
        $report['lists'][]    = ['name' => $list['name'], 'neu' => $new, 'assigned' => $assigned];
    }

    if ($apply) {
        import_screen_species($pdo, $userId, $screen, $import['list_id'] ?? null);
        import_finish($pdo, $import['id'], $report['created'], $report['assigned']);
    }

    $report['screen'] = import_screen_report($screen);
    return $report;
}
