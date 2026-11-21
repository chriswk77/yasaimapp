<?php
/**
 * Tests what every import must not bring a second time, and the fields
 * that go back into their fields (includes/import_screen.php).
 *
 *   php tests/import_screen.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE FIELDS OUT OF THE NOTE — the lines our export writes
 *   (export_note), the multi-line Begleitvegetation with its follow-on
 *   lines, a Bestimmungssicherheit the field could not show, a foreign
 *   file's „Substrat" that must stay text. And the round trip: what the
 *   export writes, the import reads back as the same fields.
 *
 *   THE FIND NUMBER THERE AND BACK — the „Fundnummer:" line KML and GPX
 *   carry, GeoJSON's property, and YASAIMAPP's own „Y-" mark, which is
 *   dropped.
 *
 *   ONE'S OWN NAME IN ANY USUAL SPELLING — and an initial NOT: the rule
 *   decides whose finds come in, so both directions are held.
 *
 *   A WHOLE CSV IMPORT: an own find, somebody else's, one the user
 *   determined, and one that lies in the trash — the dry run's verdicts,
 *   the ticks of the apply form, the fields in the fields, and the import
 *   list that becomes a species list. And that each box brings exactly
 *   what its count said: the finder's box not the finds counted under the
 *   determiner's question.
 *
 *   THE NUMBERS OF A WHOLE CSV IMPORT: a find here without a number gets
 *   the file's, one with another number keeps its own, a number another
 *   find carries is named, and another finder's number goes into the
 *   note.
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
require_once __DIR__ . '/../includes/import_screen.php';
require_once __DIR__ . '/../includes/export.php';
require_once __DIR__ . '/../includes/geojson.php';
require_once __DIR__ . '/../includes/kml.php';
require_once __DIR__ . '/../includes/gpx.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "The fields out of the note:\n";
[$note, $fields, $tour] = import_fields_from_note(
    "Höhle im Stammfuss\nFinder: Volker Maier\nBestimmer: Sebastian Schröder\n"
    . "Bestimmungssicherheit: Mikroskopisch sicher\nBegleitvegetation: Fagus sylvatica\nQuercus petraea\n"
    . "Beleg-Nr.: 11234\nTour: Wetterau");
check($note === 'Höhle im Stammfuss', 'the note keeps nothing but the remark');
check(($fields['Finder'] ?? '') === 'Volker Maier' && ($fields['Bestimmer'] ?? '') === 'Sebastian Schröder'
    && ($fields['Beleg-Nr.'] ?? '') === '11234', 'finder, determiner and voucher go into their fields');
check(($fields['Bestimmungssicherheit'] ?? '') === 'mikroskopisch sicher',
    'the certainty takes its stored spelling, whatever the case in the file');
check(($fields['Begleitvegetation'] ?? '') === "Fagus sylvatica\nQuercus petraea",
    'a multi-line field takes the lines that follow it');
check($tour === 'Wetterau', 'the tour comes back as the tour');

[$note2, $fields2] = import_fields_from_note("Bestimmungssicherheit: ziemlich sicher\nSubstrat: Erle");
check($fields2 === [] && $note2 === "Bestimmungssicherheit: ziemlich sicher\nSubstrat: Erle",
    'a value the field cannot show and a foreign short name stay text in the note');

// The round trip: what the export writes, the import reads back.
$point = ['note' => 'Am Wegrand', 'tour' => 'Taunus', 'extra' => (object) [
    'Finder' => 'Anna Musterfrau', 'Substrat / Wirt' => 'Fagus sylvatica',
    'Begleitvegetation' => "Quercus robur\nCarpinus betulus", 'Anzahl / Menge' => 'ca. 20']];
$back = import_fields(['note' => export_note($point)]);
check($back['note'] === 'Am Wegrand' && ($back['tour'] ?? null) === 'Taunus'
    && $back['extra'] == ['Finder' => 'Anna Musterfrau', 'Substrat / Wirt' => 'Fagus sylvatica',
                          'Begleitvegetation' => "Quercus robur\nCarpinus betulus", 'Anzahl / Menge' => 'ca. 20'],
    'export and import meet: the same fields, the same note, the same tour');

echo "The find number there and back:\n";
[, , , $number] = import_fields_from_note("Am Weg\nFundnummer: Y-55\nFundnummer: wk-00123\nFundnummer: wk-00999");
$p = import_fields(['note' => "Am Weg\nFundnummer: Y-55\nFundnummer: wk-00123"]);
check($number === 'wk-00123' && $p['note'] === 'Am Weg' && $p['find_number'] === 'wk-00123',
    'the first number that is no „Y-" mark — and every „Fundnummer:" line leaves the note');
/* THE VOUCHER TICK (26.09.2026) reads a sheet's usual yes and no: „x" sets
   it, „nein" leaves it empty and the line goes, other words stay text. */
$ticked = import_fields(['note' => "Beleg aufbewahrt: x"]);
check((($ticked['extra'] ?? [])['Beleg aufbewahrt'] ?? null) === 'ja' && trim((string) ($ticked['note'] ?? '')) === '',
    'a tick column with „x" sets „Beleg aufbewahrt"');
$unticked = import_fields(['note' => "Am Weg\nBeleg aufbewahrt: nein"]);
check(!isset(($unticked['extra'] ?? [])['Beleg aufbewahrt']) && trim((string) $unticked['note']) === 'Am Weg',
    '„nein" leaves it empty — the empty tick says it, the line is not kept as text');
$numbered = import_fields(['note' => "Beleg-Nr.: HB-77"]);
check((($numbered['extra'] ?? [])['Beleg aufbewahrt'] ?? null) === 'ja' && ($numbered['extra']['Beleg-Nr.'] ?? null) === 'HB-77',
    'a voucher number brought along sets the tick — a number means a voucher');
$muddled = import_fields(['note' => "Beleg aufbewahrt: im Herbar Frankfurt"]);
check(!isset(($muddled['extra'] ?? [])['Beleg aufbewahrt']) && str_contains((string) $muddled['note'], 'im Herbar Frankfurt'),
    'anything else stays in the note — nothing is thrown away');
check(import_fields(['note' => 'Fundnummer: wk-1', 'find_number' => 'wk-2'])['find_number'] === 'wk-2',
    'a number the point brings itself (a CSV column) wins over the line');
$numbered = ['title' => 'PRUEF Rund', 'lat' => 50.1, 'lon' => 8.1, 'note' => 'Am Weg', 'find_number' => 'wk-00123',
             'extra' => (object) ['Finder' => 'Anna Musterfrau']];
// Through the real readers, as a file goes out and comes back.
$numbered['found_at'] = '2026-08-14 09:20:00';
$numbered['tour'] = 'Taunus';
$kmlPath = tempnam(sys_get_temp_dir(), 'pruef-kml-') . '.kml';
$gpxPath = tempnam(sys_get_temp_dir(), 'pruef-gpx-') . '.gpx';
file_put_contents($kmlPath, export_kml([$numbered], 'PRUEF'));
file_put_contents($gpxPath, export_gpx([$numbered], 'PRUEF'));
$fromKml = null;
foreach (kml_read($kmlPath)['lists'] as $list) {
    $fromKml = $list['points'][0] ?? $fromKml;
}
$fromGpx = gpx_read($gpxPath)['points'][0] ?? null;
@unlink($kmlPath);
@unlink($gpxPath);
foreach (['KML' => $fromKml, 'GPX' => $fromGpx] as $format => $read) {
    $back = import_fields((array) $read);
    check(($back['find_number'] ?? null) === 'wk-00123' && $back['note'] === 'Am Weg' && ($back['tour'] ?? null) === 'Taunus',
        "$format: the number comes back through the reader, and the note keeps nothing of it");
}
check(import_fields(['note' => export_note_numbered(export_row(['find_number' => 'Y-7'] + $numbered))])['find_number'] ?? null
      === null, 'a „Y-" number names the old find and is not taken');
check((import_fields(['note' => "Am Weg\nFundnummer: Y:55"])['find_number'] ?? null) === null,
    'and so does a „Y:" from an export written before 04.10.2026');
$geoPath = tempnam(sys_get_temp_dir(), 'pruef-geo-') . '.geojson';
file_put_contents($geoPath, export_geojson([['find_number' => '12', 'note' => 'Am 12. Mai'] + $numbered]));
$geo = geojson_read($geoPath);
@unlink($geoPath);
check(($geo['points'][0]['find_number'] ?? null) === '12' && !str_contains((string) $geo['points'][0]['note'], 'Fundnummer'),
    'GeoJSON: the property comes back as the number — even a „12" in a description with a 12');

echo "One's own name:\n";
$self = person_name_tokens('Sebastian Schröder');
check(person_names_self('Schröder, Sebastian', $self) && person_names_self('sebastian SCHROEDER', $self)
    && person_names_self('Volker Maier und Sebastian Schröder', $self),
    'any order, any case, umlauts written out, among several finders');
check(!person_names_self('S. Schröder', $self) && !person_names_self('Volker Maier', $self),
    'an initial is not enough — it may be somebody else of the family');

echo "A whole CSV import:\n";
$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-screen@pruefung.local', '', 'Pruef', 'Siebmann', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();
$path = tempnam(sys_get_temp_dir(), 'pruef-screen-') . '.csv';
try {
    // A point that lies in the trash — the file brings it once more.
    $trashed = point_create($pdo, $userId, ['lat' => 50.3, 'lon' => 8.3, 'title' => 'PRUEF Weggeworfen']);
    point_delete($pdo, $userId, $trashed);

    file_put_contents($path, implode("\n", [
        'Titel;Breitengrad;Längengrad;Finder;Bestimmer;Bestimmungssicherheit;Begleitvegetation',
        'PRUEF Eigener;50,1;8,1;Siebmann, Pruef;;makroskopisch sicher;',
        'PRUEF Fremder;50,2;8,2;Volker Maier;;;',
        'PRUEF Bestimmt;50,4;8,4;Volker Maier;Pruef Siebmann;mikroskopisch sicher;',
        'PRUEF Weggeworfen;50,3;8,3;;;;',
    ]) . "\n");
    $data = csv_interpret(csv_read($path));

    $plan = csv_import($pdo, $userId, $data, false);
    $screen = $plan['screen'];
    $row = array_column($screen['rows'], null, 'title');
    check($plan['created'] === 1, 'only the own find would come without a tick — ' . $plan['created']);
    check($row['PRUEF Weggeworfen']['verdict'] === 'trash', 'the point in the trash is not brought back — the table says so');
    check($row['PRUEF Fremder']['kinds'] === ['finder'] && $row['PRUEF Fremder']['finder'] === 'Volker Maier',
        'somebody else\'s find is a conflict of its own, with its finder');
    check($row['PRUEF Bestimmt']['kinds'] === ['determiner'], 'the find the user determined is asked about, not taken');
    check($screen['fields'] === 1, 'the fields of the own find are noticed — the list will be a species list');
    check(($row['PRUEF Eigener']['row'] ?? 0) === 2, 'every entry carries its row as the spreadsheet counts it');

    $one = csv_import($pdo, $userId, $data, false, null, ['take' => [(string) $row['PRUEF Fremder']['i']]]);
    check($one['created'] === 2, 'the tick at Volker\'s find brings exactly that find');
    $nothing = csv_import($pdo, $userId, $data, false, null, ['take' => [(string) $row['PRUEF Weggeworfen']['i']]]);
    check($nothing['created'] === 1, 'a tick where nothing is asked brings nothing — the trash stays shut');

    $run = csv_import($pdo, $userId, $data, true, 'PRUEF Sieb',
        ['take' => [(string) $row['PRUEF Fremder']['i'], (string) $row['PRUEF Bestimmt']['i']]]);
    check($run['created'] === 3, 'with both ticks three finds come in — ' . $run['created']);
    $rows = $pdo->query("SELECT title, note, extra FROM map_points WHERE user_id = $userId AND deleted_at IS NULL
                         ORDER BY title")->fetchAll();
    $by = [];
    foreach ($rows as $r) {
        $by[$r['title']] = ['note' => $r['note'], 'extra' => json_decode((string) $r['extra'], true) ?: []];
    }
    check(($by['PRUEF Fremder']['extra']['Finder'] ?? '') === 'Volker Maier',
        'a ticked find keeps its finder — in the field, not the note');
    check(($by['PRUEF Bestimmt']['extra']['Bestimmer'] ?? '') === 'Pruef Siebmann'
        && ($by['PRUEF Bestimmt']['extra']['Bestimmungssicherheit'] ?? '') === 'mikroskopisch sicher',
        'determiner and certainty sit in their fields');
    check(trim((string) $by['PRUEF Eigener']['note']) === '', 'and no field line is left in the note');
    $species = (int) $pdo->query("SELECT is_species FROM map_lists WHERE user_id = $userId AND name = 'PRUEF Sieb'")
        ->fetchColumn();
    check($species === 1, 'the import list became a species list, where these fields exist');

    echo "The numbers of a whole CSV import:\n";
    point_create($pdo, $userId, ['lat' => 50.5, 'lon' => 8.5, 'title' => 'PRUEF Ohne Nummer']);
    point_create($pdo, $userId, ['lat' => 50.6, 'lon' => 8.6, 'title' => 'PRUEF Mit Nummer', 'find_number' => 'ALT-7']);
    $read = static function (string $path, array $lines): array {
        file_put_contents($path, implode("\n", $lines) . "\n");
        return csv_interpret(csv_read($path));
    };
    $numbers = $read($path, [
        'Titel;Breitengrad;Längengrad;Finder;Fundnummer',
        'PRUEF Ohne Nummer;50,5;8,5;Siebmann, Pruef;ALT-5',
        'PRUEF Mit Nummer;50,6;8,6;;ALT-8',
        'PRUEF Doppelt;50,7;8,7;;alt-7',
        'PRUEF Fremde Nummer;50,8;8,8;Volker Maier;HM-0543',
    ]);
    $dry = array_column(csv_import($pdo, $userId, $numbers, false)['screen']['rows'], null, 'title');
    check($dry['PRUEF Ohne Nummer']['verdict'] === 'filled', 'a find here without a number gets the file\'s');
    check($dry['PRUEF Mit Nummer']['kinds'] === ['number'] && $dry['PRUEF Mit Nummer']['stored'] === 'ALT-7',
        'one with another number is asked about, with the number it has');
    check($dry['PRUEF Doppelt']['kinds'] === ['twice'] && $dry['PRUEF Doppelt']['carrier']['title'] === 'PRUEF Mit Nummer',
        'a number another find carries is named with that find, whatever its case');
    check($dry['PRUEF Fremde Nummer']['kinds'] === ['finder'], 'the number of another finder\'s find asks nothing of its own');
    csv_import($pdo, $userId, $numbers, true, 'PRUEF Nummern', [
        'take' => [(string) $dry['PRUEF Fremde Nummer']['i']],
        'twice' => [$dry['PRUEF Doppelt']['i'] => 'own'],
        'twice_number' => [$dry['PRUEF Doppelt']['i'] => 'ALT-9'],
    ]);
    $stored = static fn(): array => $pdo->query(
        "SELECT title, find_number, note FROM map_points WHERE user_id = $userId AND deleted_at IS NULL")
        ->fetchAll(PDO::FETCH_UNIQUE);
    $now = $stored();
    check(($now['PRUEF Ohne Nummer']['find_number'] ?? null) === 'ALT-5'
        && ($now['PRUEF Mit Nummer']['find_number'] ?? null) === 'ALT-7',
        'applied: the missing number filled in, the other one kept — keeping is preselected');
    check(($now['PRUEF Doppelt']['find_number'] ?? null) === 'ALT-9', 'the doubled number replaced by the one typed');
    check(isset($now['PRUEF Fremde Nummer']) && $now['PRUEF Fremde Nummer']['find_number'] === null
        && str_contains((string) $now['PRUEF Fremde Nummer']['note'], 'Fundnummer von Volker Maier: HM-0543'),
        'another finder\'s number stays text in the note, with its finder — not in my series');

    $again = $read($path, ['Titel;Breitengrad;Längengrad;Fundnummer', 'PRUEF Mit Nummer;50,6;8,6;ALT-8']);
    $index = csv_import($pdo, $userId, $again, false)['screen']['rows'][0]['i'];
    csv_import($pdo, $userId, $again, true, null, ['keep' => [$index => 'file']]);
    check(($stored()['PRUEF Mit Nummer']['find_number'] ?? null) === 'ALT-8', '„aus der Datei nehmen" takes the file\'s number');

    // With a pattern the next free number is the preselected way out.
    find_number_settings_save($pdo, $userId, 'PS-NNN');
    $twice = $read($path, ['Titel;Breitengrad;Längengrad;Fundnummer', 'PRUEF Doppelt 2;50,71;8,71;ALT-8']);
    $plan2 = csv_import($pdo, $userId, $twice, false)['screen']['rows'][0];
    check($plan2['kinds'] === ['twice'] && ($plan2['proposal'] ?? null) === 'PS-001',
        'with a pattern the preview proposes the next free number of the series');
    csv_import($pdo, $userId, $twice, true);
    check(($stored()['PRUEF Doppelt 2']['find_number'] ?? null) === 'PS-001', 'and the import draws that very number');


    // A find one only determined may get a number of one's own series.
    $det = $read($path, ['Titel;Breitengrad;Längengrad;Finder;Bestimmer;Fundnummer',
                         'PRUEF Bestimmt 2;50,95;8,95;Jonas Wolf;Pruef Siebmann;JW-112']);
    $detIndex = csv_import($pdo, $userId, $det, false)['screen']['rows'][0]['i'];
    csv_import($pdo, $userId, $det, true, null, ['take' => [(string) $detIndex], 'det_number' => [$detIndex => 'next']]);
    $detRow = $stored()['PRUEF Bestimmt 2'] ?? [];
    check(($detRow['find_number'] ?? null) === 'PS-002' && str_contains((string) ($detRow['note'] ?? ''), 'Fundnummer von Jonas Wolf: JW-112'),
        'the voucher with the user: the next number of their series, the finder\'s number in the note');

    /* ONE NUMBER, ANY SPELLING (review of 26.09.2026): „ps-1" is PS-001 —
       compared by the number in the series (find_number_key), and stored
       in the series' spelling, as the point window stores a typed one. */
    $spelled = $read($path, ['Titel;Breitengrad;Längengrad;Fundnummer',
                             'PRUEF Anders geschrieben;50,72;8,72;ps-1',
                             'PRUEF Kurz geschrieben;50,73;8,73;PS-7']);
    $rowsSpelled = array_column(csv_import($pdo, $userId, $spelled, false)['screen']['rows'], null, 'title');
    check($rowsSpelled['PRUEF Anders geschrieben']['kinds'] === ['twice']
        && $rowsSpelled['PRUEF Anders geschrieben']['carrier']['title'] === 'PRUEF Doppelt 2',
        '„ps-1" in the file is the PS-001 a find carries — named as taken, not slipped in beside it');
    csv_import($pdo, $userId, $spelled, true, null,
        ['twice' => [$rowsSpelled['PRUEF Anders geschrieben']['i'] => 'none']]);
    check(($stored()['PRUEF Kurz geschrieben']['find_number'] ?? null) === 'PS-007',
        'a number of the series\' shape comes in in the series\' spelling: PS-7 → PS-007');

    /* A NUMBER FILLED IN REACHES THE DEVICES: written through point_update,
       it moves updated_at — the bare UPDATE it replaces did not, and a
       phone's sync (asking for what changed since) never saw it. */
    $late = point_create($pdo, $userId, ['lat' => 50.74, 'lon' => 8.74, 'title' => 'PRUEF Nachgetragen']);
    $pdo->exec("UPDATE map_points SET find_number = NULL, updated_at = '2026-01-01 00:00:00' WHERE id = $late");
    csv_import($pdo, $userId, $read($path, ['Titel;Breitengrad;Längengrad;Fundnummer', 'PRUEF Nachgetragen;50,74;8,74;PS-20']), true);
    $after = $pdo->query("SELECT find_number, updated_at FROM map_points WHERE id = $late")->fetch();
    check($after['find_number'] === 'PS-020' && $after['updated_at'] > '2026-01-01 00:00:00',
        'a missing number filled in moves the find\'s updated_at, so every device fetches it');

    echo "Perhaps renamed:\n";
    point_create($pdo, $userId, ['lat' => 50.9, 'lon' => 8.9, 'title' => 'PRUEF Russula sp.',
        'found_at' => '2024-09-03 11:20:00', 'find_number' => 'R-11']);
    $renamed = $read($path, ['Titel;Breitengrad;Längengrad;Fundzeit;Fundnummer',
                             'PRUEF Russula nobilis;50,9;8,9;2024-09-03 11:20:00;R-12']);
    $ask = csv_import($pdo, $userId, $renamed, false);
    $asked = $ask['screen']['rows'][0];
    check($asked['kinds'] === ['renamed'] && $asked['candidate']['title'] === 'PRUEF Russula sp.'
        && $ask['screen']['missing'] === 1 && $ask['created'] === 0,
        'the same spot and minute under a new name is asked about — nothing preselected, nothing counted');
    $both = $read($path, ['Titel;Breitengrad;Längengrad;Fundzeit;Fundnummer',
                          'PRUEF Russula nobilis;50,9;8,9;2024-09-03 11:20:00;R-12',
                          'PRUEF Russula sp.;50,9;8,9;2024-09-03 11:20:00;R-11']);
    $second = csv_import($pdo, $userId, $both, false);
    check($second['screen']['missing'] === 0 && $second['created'] === 1,
        'while the old name still stands in the file, the new one is a second species at that tree');
    $skip = csv_import($pdo, $userId, $renamed, false, null, ['renamed' => [$asked['i'] => 'skip']]);
    check($skip['created'] === 0 && $skip['assigned'] === 0 && $skip['screen']['missing'] === 0,
        '„nicht einspielen" leaves the entry out entirely');
    csv_import($pdo, $userId, $renamed, true, null,
        ['renamed' => [$asked['i'] => 'rename'], 'renamed_number' => [$asked['i'] => 'file']]);
    $after = $stored();
    check(!isset($after['PRUEF Russula sp.']) && ($after['PRUEF Russula nobilis']['find_number'] ?? null) === 'R-12',
        '„neuen Namen übernehmen": the find here takes the new name — and the file\'s number, where chosen');
} finally {
    @unlink($path);
    $pdo->exec("DELETE FROM map_point_lists WHERE point_id IN (SELECT id FROM map_points WHERE user_id = $userId)");
    $pdo->exec("DELETE FROM map_points WHERE user_id = $userId");
    $pdo->exec("DELETE FROM map_imports WHERE user_id = $userId");
    $pdo->exec("DELETE FROM map_lists WHERE user_id = $userId");
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
