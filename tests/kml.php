<?php
/**
 * Tests the KML/KMZ import.
 *
 *   php tests/kml.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE COORDINATE ORDER. KML writes lon,lat — the other way round than
 *   everything else in the house. A flip here put every point into
 *   Somalia instead of the Taunus.
 *
 *   THE DETECTION OF THE KMZ: it is a ZIP, just like the Takeout — the
 *   registry must catch the KMZ BEFORE the Takeout, otherwise the Takeout
 *   reading would run against a ZIP without CSVs. And conversely a real
 *   Takeout ZIP must still arrive at the Takeout.
 *
 *   THE FOLDERS become lists with path names ("Buchen / Alteichen") —
 *   flat, because the undo only clears one level below the root.
 *
 *   THE DESCRIPTION comes as HTML in CDATA; stored is text. ExtendedData
 *   is appended to the note, unless the value already stands in the
 *   description.
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
     VALUES ('pruef-kml@pruefung.local', '', 'PRUEF', 'Kml', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

$kml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <name>Meine Karte</name>
    <Placemark>
      <name>Parkplatz</name>
      <Point><coordinates>8.3,50.2</coordinates></Point>
    </Placemark>
    <Folder>
      <name>Buchen</name>
      <Placemark>
        <name>Alte Buche</name>
        <description><![CDATA[<div>Höhle im <b>Stammfuss</b><br>Zwei Stämme</div>]]></description>
        <TimeStamp><when>2024-09-08T09:17:28Z</when></TimeStamp>
        <Point><coordinates>8.227692,50.169729,516.4</coordinates></Point>
      </Placemark>
      <Placemark>
        <name>Quellsumpf</name>
        <description>Nass</description>
        <ExtendedData>
          <Data name="Substrat"><value>Erle</value></Data>
          <Data name="Bemerkung"><value>Nass</value></Data>
        </ExtendedData>
        <Point><coordinates>8.25,50.18,0</coordinates></Point>
      </Placemark>
      <Folder>
        <name>Alteichen</name>
        <Placemark>
          <name>Eiche 1</name>
          <Point><coordinates>8.26,50.19</coordinates></Point>
        </Placemark>
      </Folder>
    </Folder>
    <Placemark>
      <name>Geisterpunkt</name>
      <Point><coordinates>0,0</coordinates></Point>
    </Placemark>
    <Placemark>
      <name>Heimweg</name>
      <LineString><coordinates>8.2,50.1 8.21,50.11</coordinates></LineString>
    </Placemark>
  </Document>
</kml>
XML;

$kmlPath = tempnam(sys_get_temp_dir(), 'pruef-kml-') . '.kml';
file_put_contents($kmlPath, $kml);

// The same file as KMZ — and next to it a Takeout ZIP, for both are
// ZIPs: the registry must still tell them apart.
$kmzPath = tempnam(sys_get_temp_dir(), 'pruef-kmz-') . '.kmz';
$zip = new ZipArchive();
$zip->open($kmzPath, ZipArchive::CREATE);
$zip->addFromString('doc.kml', $kml);
$zip->close();

$takeoutPath = tempnam(sys_get_temp_dir(), 'pruef-takeout-') . '.zip';
$zip = new ZipArchive();
$zip->open($takeoutPath, ZipArchive::CREATE);
$zip->addFromString('Takeout/Gespeichert/Favoriten.csv',
    "Titel,Notiz,URL\nTest,,https://www.google.com/maps/search/50.1,8.2\n");
$zip->close();

try {
    echo "Detection:\n";
    check(import_detect($kmlPath) === 'kml', 'the .kml is detected as KML');
    check(import_detect($kmzPath) === 'kml',
        'the KMZ is detected as KML — NOT as Takeout, although it is a ZIP');
    check(import_detect($takeoutPath) === 'takeout',
        'a real Takeout ZIP still arrives at the Takeout');

    echo "Reading:\n";
    $data = kml_read($kmlPath);
    $all  = array_merge(...array_column($data['lists'], 'points'));
    check(count($all) === 4, 'four placemarks arrive — despite the namespace in the root');
    check($data['skipped'] === 1, 'the 0/0 ghost point is passed over, not placed');
    check($data['ways'] === 1, 'the line is counted, not read in');
    check(array_column($data['lists'], 'name') === ['Meine Karte', 'Buchen', 'Buchen / Alteichen'],
        'the folders become path names, the loose placemarks are named after the document');

    $beech = $data['lists'][1]['points'][0];
    check($beech['lat'] === 50.169729 && $beech['lon'] === 8.227692,
        'lon,lat from the file has arrived as lat/lon — the KML order is flipped');
    check($beech['elevation_m'] === 516, 'the third coordinate becomes the height, rounded');
    check($beech['note'] === "Höhle im Stammfuss\nZwei Stämme",
        'the HTML of the description has become text, <br> a line break');
    check($beech['found_at'] === date('Y-m-d H:i:s', strtotime('2024-09-08T09:17:28Z')),
        'the UTC time is converted into the local time of the site');

    $swamp = $data['lists'][1]['points'][1];
    check($swamp['note'] === "Nass\nSubstrat: Erle",
        'ExtendedData hangs on the note — the value already in the description not twice');
    check($swamp['elevation_m'] === null && $swamp['found_at'] === null,
        'height 0 means clampToGround, without TimeStamp the find time stays empty — nothing is invented');

    echo "Dry run and importing:\n";
    $plan = kml_import($pdo, $userId, $data, false);
    check($plan['created'] === 4 && str_starts_with($plan['import'], 'Import '),
        'four placemarks announced, into an import list of their own');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_points WHERE user_id = $userId")->fetchColumn() === 0,
        'the dry run has written nothing');

    $run = kml_import($pdo, $userId, $data, true);
    check($run['created'] === 4 && $run['import_id'] !== null,
        'the real run does what the dry run announced, and names its id');

    $folder = (int) $pdo->query(
        "SELECT folder_id FROM map_imports WHERE id = {$run['import_id']}"
    )->fetchColumn();
    $children = $pdo->query(
        "SELECT name FROM map_lists WHERE user_id = $userId AND folder_id = $folder
          AND parent_id IS NULL AND deleted_at IS NULL ORDER BY id"
    )->fetchAll(PDO::FETCH_COLUMN);
    check($children === ['Meine Karte', 'Buchen', 'Buchen / Alteichen'],
        "one list per folder in the import's Mappe — flat, with path names");
    check((int) $pdo->query(
        "SELECT COUNT(*) FROM map_lists WHERE user_id = $userId AND is_species = 1"
    )->fetchColumn() === 0,
        'none of the import lists is a species list — placemarks are places, not finds');

    $point = $pdo->query(
        "SELECT elevation_m, elevation_source, elevation_ellipsoid_m, import_id FROM map_points
          WHERE user_id = $userId AND title = 'Alte Buche'"
    )->fetch();
    // On creation point_create asks the DGM1 — if it delivers (the test
    // spot lies in Hesse), the terrain height wins; without the service
    // the KML height would remain. Wrong would only be: no height at
    // all, or one that went through the geoid conversion.
    check($point['elevation_m'] !== null && $point['elevation_ellipsoid_m'] === null
          && in_array($point['elevation_source'], ['dgm1', null], true),
        'the height stands on the point — DGM1 wins, the KML height is the fallback, never the geoid conversion');
    check((int) $point['import_id'] === (int) $run['import_id'],
        'the point carries the id of its import');

    echo "Once more — nothing doubles:\n";
    $run2 = kml_import($pdo, $userId, $data, true);
    check($run2['created'] === 0 && $run2['assigned'] === 4,
        'the same export a second time creates nothing');

    echo "Undoing and the one-folder case:\n";
    import_remove($pdo, $userId, (int) $run['import_id']);
    import_remove($pdo, $userId, (int) $run2['import_id']);
    check((int) $pdo->query(
        "SELECT COUNT(*) FROM map_lists WHERE user_id = $userId AND deleted_at IS NULL"
    )->fetchColumn() === 0, 'after undoing no list is left');

    $simple = kml_read($kmzPath);           // the KMZ reads like the KML
    $simple['lists'] = [$simple['lists'][1]];   // only "Buchen"
    $run3 = kml_import($pdo, $userId, $simple, true);
    $lists = $pdo->query(
        "SELECT id, parent_id FROM map_lists WHERE user_id = $userId AND deleted_at IS NULL"
    )->fetchAll();
    check(count($lists) === 1 && $lists[0]['parent_id'] === null,
        'a single folder gets no leaf of its own — everything lies directly in the import list');
    check((int) $pdo->query(
        "SELECT COUNT(*) FROM map_point_lists WHERE list_id = " . (int) $lists[0]['id']
    )->fetchColumn() === (int) $run3['created'],
        'the placemarks hang on the root itself');
} finally {
    @unlink($kmlPath);
    @unlink($kmzPath);
    @unlink($takeoutPath);
    if (folders_exist($pdo)) {
        $pdo->exec("DELETE FROM map_list_folders WHERE user_id = $userId");
    }
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
