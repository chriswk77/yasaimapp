<?php
/**
 * Tests the generic CSV import.
 *
 *   php tests/csv.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE GUESSING AND ITS LIMITS. The columns are named arbitrarily —
 *   guessing goes by the usual names, but the coordinate conjecture has
 *   to prove itself against the content: a column "y" full of count
 *   values must NOT pass as latitude.
 *
 *   THE INTERPRETATION FROM THE FORM beats the guessing — and an
 *   incomplete interpretation makes the dry run report empty instead of
 *   throwing, because the preview must be able to demand the assignment.
 *
 *   GERMAN NUMBERS AND DATES: decimal comma, 8.9.97 (that is 1997, not an
 *   appointment in 2097), Windows-1252 from Excel.
 *
 *   THE COMBINED POSITION COLUMN: "50.1, 8.2" in ONE cell when lat and
 *   lon point to the same column.
 *
 *   THE DETECTION: the catch-all source stands at the end — the fungi CSV
 *   must still arrive at its own source.
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
     VALUES ('pruef-csv@pruefung.local', '', 'PRUEF', 'Csv', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

// The main file: German Excel — Windows-1252, semicolon, decimal comma.
$main = "Art;Breitengrad;Längengrad;Datum;Höhe;Genauigkeit;Bemerkung;Sammler\n"
       . "Steinpilz;50,169729;8,227692;08.09.2024 09:17;516,4;5;Unter Buchen;Müller\n"
       . "Pfifferling;50,18;8,25;8.9.97;0;;;Schulz\n"
       . "Ohne Lage;;;01.01.2024;;;fehlt;\n";
$mainPath = tempnam(sys_get_temp_dir(), 'pruef-csv-') . '.csv';
file_put_contents($mainPath, mb_convert_encoding($main, 'Windows-1252', 'UTF-8'));

$fungiPath = tempnam(sys_get_temp_dir(), 'pruef-csv-fungi-') . '.csv';
file_put_contents($fungiPath, "Wiss.Name;Breitengrad;Laengengrad\nBoletus edulis;50,1;8,2\n");

try {
    echo "Detection:\n";
    check(import_detect($mainPath) === 'csv', 'the arbitrary table lands at the catch-all source');
    check(import_detect($fungiPath) === 'fungi',
        'the fungi CSV still arrives at its own source — the catch-all source stands at the end');

    echo "Reading and guessing:\n";
    $data = csv_read($mainPath);
    check($data['separator'] === ';' && $data['header'] === true && count($data['rows']) === 3,
        'semicolon detected, header row detected, three data rows');
    check($data['columns'][4] === 'Höhe',
        'Windows-1252 from Excel arrived as UTF-8');

    $interpretation = csv_guess($data);
    check($interpretation === ['title' => 0, 'lat' => 1, 'lon' => 2, 'time' => 3,
                           'height' => 4, 'accuracy' => 5, 'find_number' => '', 'note' => 6],
        'all roles are guessed via the usual names — "Sammler" stays unassigned, and without a number column the role stays empty');

    $trap = ['columns' => ['name', 'y', 'x'], 'header' => true,
              'rows' => [['A', '500', '3'], ['B', '600', '4']]];
    $guessed = csv_guess($trap);
    check($guessed['lat'] === '' && $guessed['lon'] === '',
        'a column "y" full of count values does NOT pass as latitude — the content refutes the name');

    echo "Numbers, times, position pairs:\n";
    check(csv_datetime('08.09.2024 09:17') === '2024-09-08 09:17:00',
        'the German date including time of day');
    check(csv_datetime('8.9.97') === '1997-09-08 00:00:00',
        'a two-digit 97 is 1997 — not an appointment in 2097');
    check(csv_datetime('2024-09-08T09:17:28Z') === date('Y-m-d H:i:s', strtotime('2024-09-08T09:17:28Z')),
        'ISO with Z is converted into local time');
    check(csv_datetime('42') === null && csv_datetime('31.02.2024') === null,
        'a bare number and an impossible date are not invented into a time');
    check(csv_coordinate_pair('50.1, 8.2') === [50.1, 8.2]
          && csv_coordinate_pair('50,1; 8,2') === [50.1, 8.2]
          && csv_coordinate_pair('50.1 8.2') === [50.1, 8.2]
          && csv_coordinate_pair('Wiesbaden') === null,
        'the position pair in one cell — with dot, German comma or space');

    echo "Dry run and import:\n";
    $data['interpretation'] = $interpretation;
    $plan = csv_import($pdo, $userId, $data, false);
    check($plan['created'] === 2 && count($plan['skipped']) === 1
          && $plan['skipped'][0]['row'] === 4 && $plan['skipped'][0]['title'] === 'Ohne Lage',
        'two points announced, the row without a position named — counted as in the spreadsheet');
    check((int) $pdo->query("SELECT COUNT(*) FROM map_points WHERE user_id = $userId")->fetchColumn() === 0,
        'the dry run wrote nothing');

    $run = csv_import($pdo, $userId, $data, true);
    check($run['created'] === 2 && $run['import_id'] !== null,
        'the real run does what the dry run announced, and names its id');

    $point = $pdo->query(
        "SELECT lat, lon, found_at, accuracy_m, note, elevation_m, elevation_ellipsoid_m,
                elevation_source, import_id
           FROM map_points WHERE user_id = $userId AND title = 'Steinpilz'"
    )->fetch();
    check((float) $point['lat'] === 50.169729 && (float) $point['lon'] === 8.227692,
        'decimal commas arrived as coordinates');
    check($point['found_at'] === '2024-09-08 09:17:00' && (float) $point['accuracy_m'] === 5.0,
        'find time and accuracy stand on the point');
    check($point['note'] === "Unter Buchen\nSammler: Müller",
        'the note column first, the unassigned as "column: value" behind it');
    check($point['elevation_m'] !== null && $point['elevation_ellipsoid_m'] === null
          && in_array($point['elevation_source'], ['dgm1', null], true),
        'the height stands on the point — DGM1 wins, the table height is the fallback, never the geoid conversion');
    check((int) $point['import_id'] === (int) $run['import_id'],
        'the point carries the id of its import');

    $chanterelle = $pdo->query(
        "SELECT found_at, note FROM map_points WHERE user_id = $userId AND title = 'Pfifferling'"
    )->fetch();
    check(str_starts_with((string) $chanterelle['found_at'], '1997-09-08')
          && $chanterelle['note'] === 'Sammler: Schulz',
        'the 97 date lies in 1997, the height 0 was not taken as a measurement');

    $lists = $pdo->query(
        "SELECT parent_id, is_species FROM map_lists WHERE user_id = $userId AND deleted_at IS NULL"
    )->fetchAll();
    check(count($lists) === 1 && $lists[0]['parent_id'] === null
          && (int) $lists[0]['is_species'] === 0,
        'everything lies directly in the import list — the source is neutral, no species flag');

    $run2 = csv_import($pdo, $userId, $data, true);
    check($run2['created'] === 0 && $run2['assigned'] === 2,
        'the same table a second time creates nothing');

    echo "Incomplete interpretation:\n";
    $without = $data;
    $without['interpretation']['lat'] = $without['interpretation']['lon'] = '';
    $empty = csv_import($pdo, $userId, $without, false);
    check($empty['incomplete'] === true && $empty['created'] === 0,
        'without position columns the dry run reports incomplete instead of throwing');
    $thrown = false;
    try {
        csv_import($pdo, $userId, $without, true);
    } catch (InvalidArgumentException) {
        $thrown = true;
    }
    check($thrown, 'the real run without position columns throws — no button leads there');

    echo "Combined position column and headless:\n";
    file_put_contents($mainPath,
        "Name,Position,Datum\n\"Buche\",\"50.169729, 8.227692\",2024-09-08\n");
    $combined = csv_read($mainPath);
    check($combined['separator'] === ',', 'the comma detected as separator');
    $combinedInterpretation = csv_guess($combined);
    check($combinedInterpretation['lat'] === 1 && $combinedInterpretation['lon'] === 1,
        'the position column serves latitude AND longitude at once');
    $combined['interpretation'] = $combinedInterpretation;
    $combinedPlan = csv_import($pdo, $userId, $combined, false);
    check($combinedPlan['created'] === 1
          && $combinedPlan['screen']['rows'][0]['lat'] === 50.169729
          && $combinedPlan['screen']['rows'][0]['lon'] === 8.227692,
        'the position pair from the one cell is split correctly');

    file_put_contents($mainPath, "50,1;8,2\n50,2;8,3\n");
    $headless = csv_read($mainPath);
    check($headless['header'] === false && $headless['columns'] === ['Spalte 1', 'Spalte 2']
          && count($headless['rows']) === 2,
        'a table without a header row loses no row and gets stopgap names');
    $_POST['mapping'] = ['lat' => '0', 'lon' => '1'];
    $headless = csv_interpret($headless);
    unset($_POST['mapping']);
    check($headless['interpretation']['lat'] === 0 && $headless['interpretation']['lon'] === 1
          && $headless['interpretation']['title'] === '',
        'the interpretation from the form beats the guessing');
    $headlessPlan = csv_import($pdo, $userId, $headless, false);
    check($headlessPlan['created'] === 2 && $headlessPlan['screen']['rows'][0]['title'] === 'Ohne Titel',
        'points arise even without a title column — as "Ohne Titel"');
} finally {
    @unlink($mainPath);
    @unlink($fungiPath);
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
