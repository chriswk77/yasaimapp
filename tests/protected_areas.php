<?php
/**
 * Tests the protected-area note on the point (includes/protected_areas.php).
 *
 *   php tests/protected_areas.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   ONLY FOR FINDS. A point outside the species lists gets no note — and
 *   above all: it triggers no request. That is not mere thrift, it is
 *   the promise that a hundred placed waymarks cost no hundred waits.
 *
 *   THE BRANCH COUNTS, NOT THE LEAF. The species-list checkmark usually
 *   sits at the root ("Meine Funde"), the points hang in the leaf below.
 *
 *   THREE STATES OF THE COLUMN: NULL = not asked, '' = asked and nothing
 *   found, 'A, B' = these areas. Without the empty text as its own
 *   state, "lies in none" could not be told apart from "never asked".
 *
 *   THE AREA TYPES COME FROM THE LAYER DEFINITION and are not copied off
 *   — including the name field that differs per type.
 *
 * THE REQUESTS TO THE BfN are part of the test: without them you would
 * only test your own wiring, and the traps of this service (403 without
 * a browser identity, axis order) would lie exactly outside. If the
 * service stays silent, the affected assertions are skipped instead of
 * failing — a test that turns red on foreign disturbance soon gets
 * ignored.
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
require_once __DIR__ . '/../includes/protected_areas.php';

$failed = 0;
$skipped = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}
function skipped(string $what): void
{
    global $skipped;
    $skipped++;
    echo "  ----  skipped (service is silent): $what\n";
}

// The Altkönig in the Taunus: nature reserve AND FFH area, so exactly
// the case for which the column has to carry two names.
const ALTKOENIG = [50.212034, 8.479558];
// Frankfurt city centre — no protected area lies there.
const CITY = [50.110, 8.682];

echo "The layer definition carries the area types:\n";
$service = protected_areas_service();
check($service !== null && count($service['kinds']) === 3,
    'three area types come from map_layers.php, not from a copy');
check($service !== null && array_column($service['kinds'], 'nameField') === ['NAME', 'NAME', 'NAME'],
    'the name fields travel along from map_layers.php — NAME for all three since the provider\'s rebuild of September 2026 (the FFH area said Gebietsname before)');

echo "The service itself:\n";
$reachable = true;
try {
    $names = protected_areas_get(ALTKOENIG[0], ALTKOENIG[1]);
    check(in_array('Naturschutzgebiet Altkönig', $names, true),
        'the point finds its nature reserve, with the area type in front');
    // THE TRAP THE TYPE STANDS IN FRONT FOR: the FFH area here is also
    // called "Altkönig". Without the type both would collapse into one
    // entry, and "two kinds of law apply here" would vanish silently.
    check(in_array('FFH-Gebiet Altkönig', $names, true),
        'and the FFH area of the same name beside it — not collapsed into one');
    check(count($names) === 2,
        'exactly two, no national park in the Taunus');
} catch (RuntimeException $e) {
    $reachable = false;
    skipped('hits at the Altkönig (' . $e->getMessage() . ')');
}

if ($reachable) {
    try {
        check(protected_areas_get(CITY[0], CITY[1]) === [],
            'in the middle of Frankfurt lies none — empty list, no error');
    } catch (RuntimeException) {
        skipped('empty hit in the city');
    }
}

$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-schutz@pruefung.local', '', 'PRUEF', 'Schutz', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

try {
    $column = static function (int $id) use ($pdo): mixed {
        $stmt = $pdo->prepare('SELECT protected_area FROM map_points WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    };

    // A species list ($root and $leaf name the one list; it was a branch
    // with the checkmark at the root until the Mappen, 0168).
    $leaf = list_create($pdo, $userId, ['name' => 'PRUEF Pilze', 'is_species' => true]);
    $root = $leaf;
    // And an ordinary list beside it.
    $other = list_create($pdo, $userId, ['name' => 'PRUEF Wegmarken']);

    echo "A point outside the species lists is left alone:\n";
    $waymark = point_create($pdo, $userId, [
        'lat' => ALTKOENIG[0], 'lon' => ALTKOENIG[1],
        'title' => 'PRUEF Wegmarke', 'lists' => [$other]]);
    check($column($waymark) === null,
        'the column stays NULL — in the middle of the nature reserve, but not a find');
    check(protected_area_record($pdo, $userId, $waymark, true) === null,
        'even the explicit call does nothing — it does not even ask');

    echo "A find in the species list gets its note:\n";
    $find = point_create($pdo, $userId, [
        'lat' => ALTKOENIG[0], 'lon' => ALTKOENIG[1],
        'title' => 'Boletus edulis', 'lists' => [$leaf]]);
    if (!$reachable) {
        skipped('note on the find');
    } else {
        $value = $column($find);
        check(is_string($value) && str_contains($value, 'Altkönig'),
            'the species-list checkmark suffices');
        check($value === 'Naturschutzgebiet Altkönig, FFH-Gebiet Altkönig',
            'both areas stand comma-separated in the one column');
    }

    echo "Moving drags the note along:\n";
    if (!$reachable) {
        skipped('note after moving');
    } else {
        point_update($pdo, $userId, $find, ['lat' => CITY[0], 'lon' => CITY[1]]);
        check($column($find) === '',
            'in the city the empty text remains — asked, lies in none');
        check($column($find) !== null,
            'and precisely NOT null: "in none" is something other than "never asked"');
    }

    echo "The client can no longer set the column:\n";
    point_update($pdo, $userId, $waymark, ['protected_area' => 'PRUEF Untergeschoben']);
    check($column($waymark) === null,
        'a protected_area sent along falls through — the entry belongs to the server');

    echo "The catch-up finds the gaps (protected_areas_content.php):\n";
    require_once __DIR__ . '/../includes/protected_areas_content.php';

    $branches = protected_areas_species_lists($pdo);
    check(in_array($root, $branches, true) && in_array($leaf, $branches, true),
        'the species list is found');
    check(!in_array($other, $branches, true),
        'the ordinary list stays outside');

    // A REAL IMPORT, as "Meine Pilze" creates it: root as a species list,
    // points with import_id. The find lies in the middle of the nature
    // reserve and still gets no note — otherwise an import with hundreds
    // of finds would cost hundreds of requests.
    require_once __DIR__ . '/../includes/imports.php';
    $import = import_create($pdo, $userId, 'fungi', rootIsSpecies: true,
        desiredName: 'PRUEF Einfuhr');
    $before = protected_areas_pending($pdo);
    $imported = point_create($pdo, $userId, [
        'lat' => ALTKOENIG[0], 'lon' => ALTKOENIG[1], 'title' => 'PRUEF Importfund',
        'lists' => [$import['list_id']], 'import_id' => $import['id']]);
    check($column($imported) === null,
        'the imported find stays without a note — the import does not ask the BfN');
    check(protected_areas_pending($pdo) === $before + 1,
        'and for exactly that reason shows up as pending in the catch-up');

    if (!$reachable) {
        skipped('catching up on the imported find');
    } else {
        protected_area_record($pdo, $userId, $imported, true);
        check($column($imported) === 'Naturschutzgebiet Altkönig, FFH-Gebiet Altkönig',
            'the catch-up fetches it — the same value as when created by hand');
        check(protected_areas_pending($pdo) === $before,
            'and with that it is no longer pending');
    }

    // The waymark stands in the middle of the nature reserve and is still
    // never pending: it is not a find. That is the promise that a hundred
    // waymarks cost no hundred requests.
    $openBeforeWaymark = protected_areas_pending($pdo);
    point_create($pdo, $userId, ['lat' => ALTKOENIG[0], 'lon' => ALTKOENIG[1],
        'title' => 'PRUEF Zweite Wegmarke', 'lists' => [$other]]);
    check(protected_areas_pending($pdo) === $openBeforeWaymark,
        'a waymark in the nature reserve does NOT count as pending — it is not a find');
} finally {
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good." : "\n$failed check(s) failed.";
echo $skipped > 0 ? " ($skipped skipped)\n" : "\n";
exit($failed === 0 ? 0 : 1);
