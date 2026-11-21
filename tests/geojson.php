<?php
/**
 * Tests the GeoJSON import.
 *
 *   php tests/geojson.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE COORDINATE ORDER. GeoJSON writes [lon, lat] — flipped like KML.
 *   A flip put every point into Somalia instead of the Taunus.
 *
 *   THE PROPERTY FISHING: title, description and time are named
 *   differently in every application; what remains must land in the note
 *   as "field: value" — except Simplestyle cosmetics and values that
 *   already stand in the description.
 *
 *   THE DETECTION must not swallow just any JSON — otherwise the import
 *   would report every configuration file as broken GeoJSON.
 *
 *   LINES AND AREAS STAY OUT, 0/0 and placeless features too — counted,
 *   not swallowed.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/import_content.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-geojson@pruefung.local', '', 'PRUEF', 'Geojson', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

$geojson = json_encode([
    'type' => 'FeatureCollection',
    'features' => [
        [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [8.227692, 50.169729, 516.4]],
            'properties' => [
                'name'         => 'Alte Buche',
                'description'  => "Höhle im <b>Stammfuss</b><br>Zwei Stämme",
                'time'         => '2024-09-08T09:17:28Z',
                'Substrat'     => 'Fagus',
                'Anzahl'       => 3,
                'marker-color' => '#ff0000',
                'gemerkt'      => true,
            ],
        ],
        [
            'type' => 'Feature',
            'geometry' => ['type' => 'MultiPoint', 'coordinates' => [[8.25, 50.18, 0]]],
            'properties' => ['title' => 'Quellsumpf', 'desc' => 'Nass', 'Zustand' => 'Nass'],
        ],
        [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]],
            'properties' => ['name' => 'Geisterpunkt'],
        ],
        [
            'type' => 'Feature',
            'geometry' => null,
            'properties' => ['name' => 'Ortlos'],
        ],
        [
            'type' => 'Feature',
            'geometry' => ['type' => 'LineString',
                           'coordinates' => [[8.2, 50.1], [8.21, 50.11]]],
            'properties' => ['name' => 'Heimweg'],
        ],
    ],
], JSON_UNESCAPED_UNICODE);

$path = tempnam(sys_get_temp_dir(), 'pruef-geojson-') . '.geojson';
file_put_contents($path, $geojson);

// An arbitrary JSON that is NOT GeoJSON — the detection must leave it
// alone instead of reporting it as broken GeoJSON.
$foreignPath = tempnam(sys_get_temp_dir(), 'pruef-fremd-') . '.json';
file_put_contents($foreignPath, '{"einstellungen": {"sprache": "de"}, "version": 3}');

try {
    echo "Detection:\n";
    check(import_detect($path) === 'geojson', 'the GeoJSON file is detected');
    check(import_detect($foreignPath) === null,
        'an arbitrary other JSON is NOT swallowed');

    echo "Reading:\n";
    $data = geojson_read($path);
    check(count($data['points']) === 2, 'two points arrive');
    check($data['skipped'] === 2,
        'the 0/0 point and the placeless feature are passed over, not placed');
    check($data['ways'] === 1, 'the line is counted, not read in');

    $beech = $data['points'][0];
    check($beech['lat'] === 50.169729 && $beech['lon'] === 8.227692,
        '[lon, lat] from the file arrived as lat/lon — the order is flipped');
    check($beech['elevation_m'] === 516, 'the third coordinate becomes the height, rounded');
    check($beech['note'] === "Höhle im Stammfuss\nZwei Stämme\nSubstrat: Fagus\nAnzahl: 3",
        'HTML becomes text, leftover properties attach as "field: value" — cosmetics and booleans do not');
    check($beech['found_at'] === date('Y-m-d H:i:s', strtotime('2024-09-08T09:17:28Z')),
        "the UTC time from the properties is converted into the installation's time");

    $marsh = $data['points'][1];
    check($marsh['title'] === 'Quellsumpf' && $marsh['lat'] === 50.18,
        'a MultiPoint with exactly one position is the same point in different garb');
    check($marsh['note'] === 'Nass',
        'a value that already stands in the description is not attached again');
    check($marsh['elevation_m'] === null && $marsh['found_at'] === null,
        'height 0 is converter filler, without a time statement the find time stays empty — nothing is invented');

    echo "Dry run and import:\n";
    $plan = geojson_import($pdo, $userId, $data, false);
    check($plan['created'] === 2 && str_starts_with($plan['import'], 'Import '),
        'two points announced, into an import list of their own');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_points WHERE user_id = $userId")->fetchColumn() === 0,
        'the dry run wrote nothing');

    $run = geojson_import($pdo, $userId, $data, true);
    check($run['created'] === 2 && $run['import_id'] !== null,
        'the real run does what the dry run announced, and names its id');

    $point = $pdo->query(
        "SELECT elevation_m, elevation_source, elevation_ellipsoid_m, import_id FROM map_points
          WHERE user_id = $userId AND title = 'Alte Buche'"
    )->fetch();
    // On creation point_create asks the DGM1 — if it delivers (the test
    // spot lies in Hesse), the terrain height wins; without the service
    // the GeoJSON height would remain. Wrong would only be: no height at
    // all, or one that went through the geoid conversion.
    check($point['elevation_m'] !== null && $point['elevation_ellipsoid_m'] === null
          && in_array($point['elevation_source'], ['dgm1', null], true),
        'the height stands on the point — DGM1 wins, the file height is the fallback, never the geoid conversion');
    check((int) $point['import_id'] === (int) $run['import_id'],
        'the point carries the id of its import');

    $lists = $pdo->query(
        "SELECT parent_id, is_species FROM map_lists WHERE user_id = $userId AND deleted_at IS NULL"
    )->fetchAll();
    check(count($lists) === 1 && $lists[0]['parent_id'] === null
          && (int) $lists[0]['is_species'] === 0,
        'everything lies directly in the import list — GeoJSON knows no folders, and points are not finds');

    echo "Once more — nothing duplicates itself:\n";
    $run2 = geojson_import($pdo, $userId, $data, true);
    check($run2['created'] === 0 && $run2['assigned'] === 2,
        'the same export a second time creates nothing');

    echo "The other top-level shapes:\n";
    file_put_contents($path, json_encode([
        'type' => 'Feature',
        'geometry' => ['type' => 'Point', 'coordinates' => [8.3, 50.2]],
        'properties' => ['name' => 'Einzelfeature'],
    ]));
    $single = geojson_read($path);
    check(count($single['points']) === 1 && $single['points'][0]['title'] === 'Einzelfeature',
        'a single feature without a collection reads the same');

    file_put_contents($path, '{"type": "Point", "coordinates": [8.3, 50.2]}');
    $bare = geojson_read($path);
    check(count($bare['points']) === 1 && $bare['points'][0]['title'] === 'Ohne Titel',
        'a bare geometry is legitimate GeoJSON too');
} finally {
    @unlink($path);
    @unlink($foreignPath);
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
