<?php
/**
 * Tests the GPX import.
 *
 *   php tests/gpx.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE NAMESPACE. Real GPX files carry xmlns — whoever accesses elements
 *   naively with SimpleXML gets nothing with some access styles. The test
 *   file carries the namespace the way Garmin writes it.
 *
 *   THE TIME: GPX writes UTC ("...Z"), stored is the installation's
 *   time — one hour off would mean every find lay in the wrong evening
 *   light.
 *
 *   THE HEIGHT passes through as elevation_m, NOT through the geoid
 *   conversion: GPX heights are usually already sea-level referenced; the
 *   conversion (the route for raw receiver heights) turned that into an
 *   error of ~48 m.
 *
 *   TRACKS STAY OUT, 0/0 waypoints too — both counted, not swallowed.
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
require_once __DIR__ . '/../includes/gpx.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-gpx@pruefung.local', '', 'PRUEF', 'Gpx', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

$gpxPath = tempnam(sys_get_temp_dir(), 'pruef-gpx-') . '.gpx';
file_put_contents($gpxPath, <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Garmin" xmlns="http://www.topografix.com/GPX/1/1">
  <wpt lat="50.169729" lon="8.227692">
    <ele>516.4</ele>
    <time>2024-09-08T09:17:28Z</time>
    <name>Alte Buche</name>
    <desc>Höhle im Stammfuss</desc>
    <cmt>Höhle im Stammfuss</cmt>
  </wpt>
  <wpt lat="50.2" lon="8.3">
    <name>Quellsumpf</name>
    <desc>Nass</desc>
    <cmt>Gummistiefel</cmt>
  </wpt>
  <wpt lat="0" lon="0"><name>Geisterpunkt</name></wpt>
  <trk><name>Heimweg</name><trkseg><trkpt lat="50.1" lon="8.2"/></trkseg></trk>
</gpx>
XML);

try {
    echo "Reading:\n";
    $data = gpx_read($gpxPath);
    check(count($data['points']) === 2,
        'two waypoints arrive — despite the namespace in the root');
    check($data['skipped'] === 1, 'the 0/0 ghost point is passed over, not placed');
    check($data['ways'] === 1, 'the track is counted, not read in');

    $first = $data['points'][0];
    check($first['title'] === 'Alte Buche' && $first['elevation_m'] === 516,
        'name and rounded height stand on the waypoint');
    check($first['note'] === 'Höhle im Stammfuss',
        'desc and cmt with the same text yield ONE note line');
    check($first['found_at'] === date('Y-m-d H:i:s', strtotime('2024-09-08T09:17:28Z')),
        "the UTC time is converted into the installation's time");

    $second = $data['points'][1];
    check($second['note'] === "Nass\nGummistiefel",
        'different desc and cmt stand one below the other');
    check($second['found_at'] === null,
        'without time the find time stays empty — nothing is invented');

    echo "Dry run and import:\n";
    $plan = gpx_import($pdo, $userId, $data, false);
    check($plan['created'] === 2 && str_starts_with($plan['import'], 'Import '),
        'two waypoints announced, into an import list of their own');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_points WHERE user_id = $userId")->fetchColumn() === 0,
        'the dry run wrote nothing');

    $run = gpx_import($pdo, $userId, $data, true);
    check($run['created'] === 2 && $run['import_id'] !== null,
        'the real run does what the dry run announced, and names its id');

    $beech = $pdo->query(
        "SELECT elevation_m, elevation_source, elevation_ellipsoid_m, found_at, import_id FROM map_points
          WHERE user_id = $userId AND title = 'Alte Buche'"
    )->fetch();
    // On creation point_create asks the DGM1 — if it delivers (the test
    // spot lies in Hesse), the terrain height wins; without the service
    // the GPX height would remain. Wrong would only be: no height at all,
    // or one that went through the geoid conversion (then a
    // elevation_ellipsoid_m would be there).
    check($beech['elevation_m'] !== null && $beech['elevation_ellipsoid_m'] === null
          && in_array($beech['elevation_source'], ['dgm1', null], true),
        'the height stands on the point — DGM1 wins, the GPX height is the fallback, never the geoid conversion');
    check((int) $beech['import_id'] === (int) $run['import_id'],
        'the point carries the id of its import');

    $list = $pdo->query(
        "SELECT is_species FROM map_lists WHERE user_id = $userId AND deleted_at IS NULL LIMIT 1"
    )->fetch();
    check((int) $list['is_species'] === 0,
        'the import list is NOT a species list — waypoints are places, not finds');

    echo "Once more — nothing duplicates itself:\n";
    $run2 = gpx_import($pdo, $userId, $data, true);
    check($run2['created'] === 0 && $run2['assigned'] === 2,
        'the same export a second time creates nothing');
} finally {
    @unlink($gpxPath);
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
