<?php
/**
 * Tests the derived species table.
 *
 *   php tests/point_species.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   TWO SOURCES. The title only counts when one of the point's lists
 *   carries is_species; fields only when the field carries it. Whoever
 *   forgets one of the two gets a table that is half right — and that
 *   never shows up in an analysis, something is simply missing.
 *
 *   INHERITANCE. A point in a sublist has the fields of the lists above
 *   it, and their is_species on the title. Without that, the species of
 *   all points in sublists vanish silently — the most likely mistake of
 *   all, because it does not occur when trying things out with a flat
 *   list.
 *
 *   CLEAN-UP. What is deleted on the point must also vanish here.
 *   Leftover names would be worse than missing ones: they claim a find
 *   that does not exist.
 *
 *   THE GENUS MUST NOT BE GUESSED. "Buche" is not a genus "Buche" —
 *   otherwise the genus list fills up with German names and substrates,
 *   and any analysis over it is worthless.
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

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nDerived species table\n\n";

if (!point_species_ready($pdo)) {
    echo "  (skipped — migration 0036 is not applied)\n\n";
    exit(0);
}

$userId = (int) $pdo->query('SELECT id FROM map_users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId <= 0) {
    echo "  (skipped — there is no user)\n\n";
    exit(0);
}

$lists = [];
$points = [];

/** The names of a point, per field, for comparing. */
function namesOf(PDO $pdo, int $pointId): array
{
    $stmt = $pdo->prepare('SELECT field, name, genus FROM map_point_species WHERE point_id = ? ORDER BY field, name');
    $stmt->execute([$pointId]);
    $out = [];
    foreach ($stmt as $row) {
        $out[$row['field']][$row['name']] = $row['genus'];
    }
    return $out;
}

// ---------------------------------------------------------------------------
echo "Title and field\n";

$top = list_create($pdo, $userId, ['name' => 'PRUEF Pilze', 'is_species' => true]);
$lists[] = $top;
list_fields_set($pdo, $top, [
    ['name' => 'Wirt',     'type' => 'text',       'is_species' => true],
    ['name' => 'Substrat', 'type' => 'text',       'is_species' => false],
]);

$p1 = point_create($pdo, $userId, [
    'lat' => 50.1, 'lon' => 8.1,
    'title' => 'Boletus edulis',
    'lists' => [$top],
    'extra' => ['Wirt' => 'Fagus sylvatica', 'Substrat' => 'Buche'],
]);
$points[] = $p1;

$n = namesOf($pdo, $p1);
check(isset($n['']['Boletus edulis']), 'the title is carried as a species');
check(isset($n['Wirt']['Fagus sylvatica']), 'the species field as well');
check(!isset($n['Substrat']), 'an ordinary field is not — ' . json_encode(array_keys($n)));
check(($n['']['Boletus edulis'] ?? '') === 'Boletus', 'the genus comes from the name');

// ---------------------------------------------------------------------------
echo "\nSeveral names in one field\n";

point_update($pdo, $userId, $p1, [
    'extra' => ['Wirt' => "Fagus sylvatica\nQuercus robur, Picea abies", 'Substrat' => 'Buche'],
]);
$n = namesOf($pdo, $p1);
check(count($n['Wirt'] ?? []) === 3, 'line break and comma separate — '
    . implode(' | ', array_keys($n['Wirt'] ?? [])));
check(($n['Wirt']['Picea abies'] ?? '') === 'Picea', 'each one gets its genus');

// ---------------------------------------------------------------------------
echo "\nWhat does not look like a name gets no genus\n";

$p2 = point_create($pdo, $userId, [
    'lat' => 50.2, 'lon' => 8.2,
    'title' => 'irgendein Fund',
    'lists' => [$top],
    'extra' => ['Wirt' => 'buche'],
]);
$points[] = $p2;
$n = namesOf($pdo, $p2);
check(($n['']['irgendein Fund'] ?? 'x') === '',
    'a lower-case title yields no genus');
check(($n['Wirt']['buche'] ?? 'x') === '',
    'a German name in lower case likewise');

// The name still stays — only the genus is missing.
check(isset($n['Wirt']['buche']), 'the name itself is still carried, though');

// ---------------------------------------------------------------------------
echo "\nThe genus of a name beginning with X\n";

/* THE HYBRID MARKER ONCE ATE THE FIRST LETTER (found 25.08.2026 while
   looking into „Xerocomus spec."). The strip was an ltrim over the
   character set '×xX ', and ltrim knows no words: „Xerocomus" lost its
   X, „erocomus" failed the capital-letter test, and the find came out
   with no genus at all. 298 names in the DGfM list and 3.724 in the
   GBIF backbone begin with X, and none of them ever got a genus.

   Guarded from both sides: a real X-name keeps its letter, and a real
   marker still goes. The lone Latin x as a marker substitute must be
   followed by a space — attached, it is part of the word. */
foreach ([
    ['Xerocomus spec.',              'Xerocomus',       'an X-genus keeps its X'],
    ['Xylaria hypoxylon',            'Xylaria',         'Xylaria too'],
    ['Xeromphalina campanella',      'Xeromphalina',    'Xeromphalina too'],
    ['×Cupressocyparis leylandii',   'Cupressocyparis', 'the attached × still goes'],
    ['× Cupressocyparis leylandii',  'Cupressocyparis', 'the loose × as well'],
    ['x Cupressocyparis leylandii',  'Cupressocyparis', 'the lone x with a space too'],
    ['Scutellinia spec.',            'Scutellinia',     'an open determination keeps its genus'],
] as [$name, $expected, $what]) {
    check(species_genus($pdo, $name, '', '') === $expected,
        $what . ' — ' . $name . ' → ' . species_genus($pdo, $name, '', ''));
}

// No inheritance through sublists any more — lists are flat since the
// Mappen (0168), and a point takes its species fields from its own lists.
$leaf = $top;

// ---------------------------------------------------------------------------
echo "\nClean-up on change\n";

point_update($pdo, $userId, $p1, ['extra' => ['Wirt' => 'Fagus sylvatica']]);
$n = namesOf($pdo, $p1);
check(count($n['Wirt'] ?? []) === 1, 'removed names disappear — '
    . implode(' | ', array_keys($n['Wirt'] ?? [])));

point_update($pdo, $userId, $p1, ['title' => 'Boletus reticulatus']);
$n = namesOf($pdo, $p1);
check(isset($n['']['Boletus reticulatus']) && !isset($n['']['Boletus edulis']),
    'a renamed title replaces the old one');

// A list without is_species: then the title no longer counts.
$without = list_create($pdo, $userId, ['name' => 'PRUEF Ohne Arten']);
$lists[] = $without;
point_update($pdo, $userId, $p1, ['lists' => [$without]]);
$n = namesOf($pdo, $p1);
check($n === [], 'from a list without species nothing remains — ' . json_encode($n));

// ---------------------------------------------------------------------------
echo "\nThe title's reference\n";

// A name the reference work is guaranteed to know: any accepted DGfM row of
// this very database — the test must not depend on specific content.
$sample = null;
if (column_exists($pdo, 'map_tax_dgfm', 'taxnr')) {
    $sample = $pdo->query('SELECT binomial FROM map_tax_dgfm WHERE taxnr = sipnr LIMIT 1')->fetchColumn();
    $sample = $sample === false ? null : (string) $sample;
}
if ($sample === null) {
    echo "  (skipped — no DGfM names in this database)\n";
} else {
    $readRefs = $pdo->prepare('SELECT extra_species FROM map_points WHERE id = ?');

    // Into the species list with a known name — the way the organize tab
    // moves points: only title and lists, never a source.
    point_update($pdo, $userId, $p1, ['title' => $sample, 'lists' => [$leaf]]);
    $readRefs->execute([$p1]);
    $refs = json_decode((string) $readRefs->fetchColumn(), true);
    check(str_starts_with((string) ($refs[''][$sample] ?? ''), 'fungi:'),
        'a known title in a species list resolves by itself — ' . json_encode($refs[''] ?? null));

    $stmt = $pdo->prepare("SELECT source FROM map_point_species WHERE point_id = ? AND field = ''");
    $stmt->execute([$p1]);
    check($stmt->fetchColumn() === 'fungi', 'and the derived row carries the source along');

    // Editing the point must not lose it: the browser sends the extra
    // block without the title, species_references_preserve drops the ''
    // entry — and the sync writes it back (measured 23.08.2026: every
    // edit of an imported find silently cost its reference).
    point_update($pdo, $userId, $p1, ['extra' => ['Wirt' => 'Fagus sylvatica']]);
    $readRefs->execute([$p1]);
    $refs = json_decode((string) $readRefs->fetchColumn(), true);
    check(str_starts_with((string) ($refs[''][$sample] ?? ''), 'fungi:'),
        'an ordinary edit keeps the reference');

    // An existing reference is kept, never re-resolved — the import may
    // have chosen its source deliberately.
    $pdo->prepare('UPDATE map_points SET extra_species = ? WHERE id = ?')
        ->execute([json_encode(['' => [$sample => 'fungi:999999999']]), $p1]);
    check(!title_references_sync($pdo, $userId, $p1), 'an existing reference is left alone');

    // Out of the species branch the statement is gone — the title is no
    // longer a find, so the reference goes with it.
    point_update($pdo, $userId, $p1, ['lists' => [$without]]);
    $readRefs->execute([$p1]);
    check($readRefs->fetchColumn() === null, 'moved out, the reference goes too');

    // A name no list knows gets none — and that is the answer, not an error.
    point_update($pdo, $userId, $p1, ['title' => 'PRUEF kein Name', 'lists' => [$leaf]]);
    $readRefs->execute([$p1]);
    check($readRefs->fetchColumn() === null, 'an unknown title stays without a reference');
}

// ---------------------------------------------------------------------------
echo "\nClean-up on deletion\n";

$before = (int) $pdo->query('SELECT COUNT(*) FROM map_point_species')->fetchColumn();
$pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ?')->execute([$p2]);
$pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$p2]);
$after = (int) $pdo->query('SELECT COUNT(*) FROM map_point_species')->fetchColumn();
check($after < $before, 'a removed point takes its species with it (foreign key)');
$points = array_values(array_diff($points, [$p2]));

// ---------------------------------------------------------------------------
foreach ($points as $id) {
    $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
}
foreach (array_reverse($lists) as $id) {
    $pdo->prepare('UPDATE map_lists SET parent_id = NULL WHERE parent_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM map_point_lists WHERE list_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM map_lists WHERE id = ?')->execute([$id]);
}
$pdo->prepare('DELETE FROM map_lists WHERE user_id = ? AND name LIKE ?')->execute([$userId, 'PRUEF %']);

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
