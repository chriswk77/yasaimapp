<?php
/**
 * Tests the import from the "Meine Pilze" app.
 *
 *   php tests/meinepilze.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE ENCODING. The app writes Windows-1252 — "Schleimrübling" would
 *   otherwise become character salad, silently, in the middle of the
 *   notes.
 *
 *   THE NUMBER AND TIME FORMAT: comma coordinates, two-digit years. Both
 *   look harmless and are exactly the kind of detail on which an import
 *   would silently push all points to 0° or into the year 1924.
 *
 *   THE NAME REFERENCE, also for synonyms: Hymenopellis radicata stands
 *   in the DGFM as a synonym (taxnr ≠ sipnr) — before the extension of
 *   species_reference(), of all things the app's sample record would have got
 *   no reference.
 *
 *   And as with the Takeout: the dry run writes nothing, importing twice
 *   doubles nothing.
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
require_once __DIR__ . '/../includes/meinepilze.php';
require_once __DIR__ . '/../includes/point_species.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-fungi@pruefung.local', '', 'PRUEF', 'Pilze', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

// A file as the app writes it: Windows-1252, semicolons, comma
// coordinates, two-digit year. The first record is the real sample find
// (DGFM synonym), the second an invented name, the third without
// coordinates, the fourth a blank line without a species name.
$csvPath = tempnam(sys_get_temp_dir(), 'pruef-fungi-') . '.csv';
$content =
    "Datum;Zeit;RefNr;Wiss.Name;Autor;Deut.Name;Finder;Bestimmer;Bemerkung;Breitengrad;Laengengrad;Genauigkeit;MTB\n"
  . "08.09.24;11:17:28;2524;Hymenopellis radicata;(Relhan 1786) R.H. Petersen 2010;Wurzelnder Schleimrübling;;;Bei der alten Eiche;50,169729;8,227692;5;MTB5815 Q1 MF9\n"
  . "09.09.24;;2525;PRUEF Fantasius nonexistens;;;Bruno;Anna;Übergangsmoor;50,2;8,3;12;\n"
  . "10.09.24;09:00:00;2526;Amanita muscaria;;;;;;;;;\n"
  . ";;;;;;;;;;;;\n";
file_put_contents($csvPath, mb_convert_encoding($content, 'Windows-1252', 'UTF-8'));

try {
    echo "Reading:\n";
    $data = pilze_read($csvPath);
    check(count($data['points']) === 2 && count($data['withoutPosition']) === 1,
        'two usable finds, one without coordinates');

    $first = $data['points'][0];
    check($first['title'] === 'Hymenopellis radicata'
          && $first['found_at'] === '2024-09-08 11:17:28',
        'two-digit year and time of day yield the find timestamp');
    check(abs($first['lat'] - 50.169729) < 1e-6 && abs($first['lon'] - 8.227692) < 1e-6,
        'comma coordinates have become numbers');
    check($first['accuracy_m'] === 5, 'the accuracy comes along in metres');
    check($first['note'] === 'Bei der alten Eiche',
        'the remark becomes the note — despite Windows-1252 in the file');

    $second = $data['points'][1];
    check($second['note'] === 'Übergangsmoor'
          && $second['finder'] === 'Bruno' && $second['bestimmer'] === 'Anna',
        'Finder and Bestimmer are their own entries, the note stays the remark — umlauts intact');
    check($second['found_at'] === '2024-09-09 00:00:00',
        'without a time of day it stays at the start of the day');

    echo "Name reference:\n";
    check(species_reference($pdo, 'Hymenopellis radicata', 'fungi') !== null,
        'the DGFM synonym gets its reference — the extension of species_reference takes effect');

    /* SOMEBODY ELSE'S FIND WAITS FOR ITS TICK (import_screen, 25./26.09.2026):
       the second find names „Bruno" as finder, and the account is „PRUEF
       Pilze" — without the tick it stays out, and the preview's table
       names it as „Anderer Finder" with its finder. The tick is the
       entry's place in the file, as the table sends it. */
    echo "Somebody else's find:\n";
    $held = pilze_import($pdo, $userId, $data, false);
    $chris = array_values(array_filter($held['screen']['rows'],
        static fn(array $r): bool => in_array('finder', $r['kinds'], true) && $r['finder'] === 'Bruno'));
    check($held['created'] === 1 && count($chris) === 1,
        'a find by someone else stays out until it is ticked, and the table names its finder');
    $take = ['take' => array_map(static fn(array $r): string => (string) $r['i'], $chris)];

    echo "Dry run:\n";
    $plan = pilze_import($pdo, $userId, $data, false, null, $take);
    check($plan['created'] === 2 && str_starts_with($plan['import'], 'Import '),
        'two finds announced, into an import list of their own');
    check(count($plan['withoutReference']) === 1
          && $plan['withoutReference'][0]['title'] === 'PRUEF Fantasius nonexistens',
        'the invented name already appears in the preview as "without name reference"');
    $points = (int) $pdo->query("SELECT COUNT(*) FROM map_points WHERE user_id = $userId")->fetchColumn();
    check($points === 0, 'the dry run wrote nothing');

    echo "Importing:\n";
    $run = pilze_import($pdo, $userId, $data, true, null, $take);
    check($run['created'] === 2, 'the real run does what the dry run announced');

    $list = $pdo->query(
        "SELECT id, is_species FROM map_lists WHERE user_id = $userId
          AND name = " . $pdo->quote($run['import'])
    )->fetch();
    check($list !== false && (int) $list['is_species'] === 1,
        'the import list is there and carries species — the title counts as a find');

    $find = $pdo->query(
        "SELECT id, found_at, accuracy_m, extra, extra_species
           FROM map_points WHERE user_id = $userId AND title = 'Hymenopellis radicata'"
    )->fetch();
    check($find['found_at'] === '2024-09-08 11:17:28' && (int) $find['accuracy_m'] === 5,
        'find time and accuracy sit on the point');
    check(!array_key_exists('', json_decode((string) $find['extra'] ?: '{}', true)),
        'the empty field name stays out of the extra block');
    $references = json_decode((string) $find['extra_species'], true);
    check(str_starts_with((string) ($references['']['Hymenopellis radicata'] ?? ''), 'fungi:'),
        'the title carries its name reference under the empty field name');

    $species = $pdo->prepare("SELECT source, identifier, genus FROM map_point_species WHERE point_id = ? AND field = ''");
    $species->execute([(int) $find['id']]);
    $row = $species->fetch();
    check($row !== false && $row['source'] === 'fungi' && $row['identifier'] !== ''
          && $row['genus'] === 'Hymenopellis',
        'the species table knows source, identifier and genus of the find');

    echo "Finder and Bestimmer sit in the find fields:\n";
    $extra = json_decode((string) $find['extra'] ?: '{}', true);
    check(($extra['Finder'] ?? null) === 'PRUEF Pilze',
        'an empty Finder becomes the importing user, with first and last name — not the login handle');
    $secondFind = $pdo->query(
        "SELECT extra, note FROM map_points WHERE user_id = $userId AND title = 'PRUEF Fantasius nonexistens'"
    )->fetch();
    $extra2 = json_decode((string) $secondFind['extra'], true);
    check(($extra2['Finder'] ?? null) === 'Bruno' && ($extra2['Bestimmer'] ?? null) === 'Anna',
        'Finder and Bestimmer from the file sit in the find fields — a ticked find keeps its finder');
    check($secondFind['note'] === 'Übergangsmoor',
        'the note carries nothing but the remark any more');

    echo "Once more — nothing doubles, this time with a chosen name:\n";
    $run2 = pilze_import($pdo, $userId, $data, true, 'PRUEF Altbestand vom Handy');
    check($run2['created'] === 0 && $run2['assigned'] === 2,
        'the same export a second time creates nothing');
    check($run2['import'] === 'PRUEF Altbestand vom Handy',
        'the chosen name replaces the date scheme');
    check((int) $pdo->query(
        "SELECT COUNT(*) FROM map_lists WHERE user_id = $userId
          AND name = 'PRUEF Altbestand vom Handy' AND deleted_at IS NULL"
    )->fetchColumn() === 1, 'the import list is called that too');

    echo "Renaming:\n";
    require_once __DIR__ . '/../includes/imports.php';
    $secondId = (int) $pdo->query(
        "SELECT id FROM map_imports WHERE user_id = $userId ORDER BY id DESC LIMIT 1"
    )->fetchColumn();
    import_rename($pdo, $userId, $secondId, 'PRUEF Umbenannt');
    check($pdo->query("SELECT name FROM map_imports WHERE id = $secondId")->fetchColumn() === 'PRUEF Umbenannt'
          && (int) $pdo->query("SELECT COUNT(*) FROM map_lists WHERE user_id = $userId
              AND name = 'PRUEF Umbenannt' AND deleted_at IS NULL")->fetchColumn() === 1,
        'renaming drags row and list along together');

    echo "Undoing:\n";
    // The import list CARRIES is_species — exactly that once made the undo
    // fail: list_delete() protects species lists, and the protection
    // stood in the way of its own clean-up. The test pins the case down.
    require_once __DIR__ . '/../includes/imports.php';
    $firstId = (int) $pdo->query(
        "SELECT id FROM map_imports WHERE user_id = $userId ORDER BY id LIMIT 1"
    )->fetchColumn();
    /* THE ARCHIVE IS GONE (the decision, 25.08.2026): nothing is
       kept beside an import any more. What the cleanup must still do is
       take along what OLDER versions left lying there — so the file is
       placed by hand here, exactly as it would be found on a server that
       imported before this day. */
    $leftover = import_archive_path($firstId);
    if (!is_dir(dirname($leftover))) {
        mkdir(dirname($leftover), 0775, true);
    }
    file_put_contents($leftover, 'Originaldatei von früher');

    $inTrash = import_remove($pdo, $userId, $firstId);
    check($inTrash === 2, 'undoing puts both finds into the trash');
    check((int) $pdo->query(
        "SELECT COUNT(*) FROM map_lists WHERE user_id = $userId AND deleted_at IS NULL AND id = " . (int) $list['id']
    )->fetchColumn() === 0, 'the import list is gone — despite the species flag');
    check(!is_file(import_archive_path($firstId)),
        'an archive file from before still goes when the import is taken back');
} finally {
    @unlink($csvPath);
    // Points, lists, species rows go with the account via ON DELETE CASCADE.
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
