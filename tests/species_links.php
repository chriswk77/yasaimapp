<?php
/**
 * Tests the references into the name list.
 *
 *   php tests/species_links.php        (from the project directory)
 *
 * WHAT THIS IS ABOUT. Next to the name someone entered sits a reference to
 * the entry in the name list. It keeps pointing to the same taxon even if
 * its valid name changes later — see migration 0030. A multi-line field
 * holds several names and therefore gets its own reference per name.
 *
 * WHAT IS TESTED HERE, and why exactly that — these are all mistakes that
 * happen silently and only surface after years:
 *
 *   That a reference SURVIVES a later change to the point. The browser only
 *   knows what was chosen in this session; without the preserving, the
 *   column was recomputed from nothing on every save. Measured exactly like
 *   that before this file existed.
 *
 *   That an inserted name does NOT shift the others. That is why the
 *   reference hangs on the name and not on the line number.
 *
 *   That a deleted name takes its reference with it — and only its own.
 *
 *   That the server does not believe what it is sent: a reference to a
 *   taxon whose name is not in the field at all must not come into being.
 *
 * The created point is removed again at the end.
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

echo "\nReferences into the name list\n\n";

if (!column_exists($pdo, 'map_points', 'extra_species')) {
    echo "  (skipped — migration 0030 is not applied)\n\n";
    exit(0);
}

// ---------------------------------------------------------------------------
echo "Splitting a field value\n";

check(species_entries("Fagus sylvatica\nQuercus robur") === ['Fagus sylvatica', 'Quercus robur'],
    'the line break separates');
check(species_entries('Fagus sylvatica, Quercus robur') === ['Fagus sylvatica', 'Quercus robur'],
    'the comma too, and the whitespace falls away');
check(species_entries('Fagus sylvatica; Quercus robur') === ['Fagus sylvatica', 'Quercus robur'],
    'and the semicolon');
check(species_entries("Fagus sylvatica\n\n, ;  ") === ['Fagus sylvatica'],
    'empty sections fall away');
check(species_entries('Fagus sylvatica, Fagus sylvatica') === ['Fagus sylvatica'],
    'the same name counts once');
check(species_entries('') === [], 'nothing yields nothing');

// ---------------------------------------------------------------------------
echo "\nOn the point\n";

$userId = (int) $pdo->query('SELECT id FROM map_users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId <= 0) {
    echo "  (skipped — there is no user)\n";
    exit($failed > 0 ? 1 : 0);
}

// Three plants that really exist in the list.
$exists = static function (string $name) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM map_tax_plants WHERE binomial = ? AND taxnr = sipnr LIMIT 1');
    $s->execute([$name]);
    return $s->fetchColumn() !== false;
};
foreach (['Fagus sylvatica', 'Quercus robur', 'Betula pendula', 'Picea abies'] as $n) {
    if (!$exists($n)) {
        echo "  (skipped — '$n' is missing from map_tax_plants, taxonomy not loaded?)\n";
        exit($failed > 0 ? 1 : 0);
    }
}

$pointId = point_create($pdo, $userId, [
    'lat' => 51.0, 'lon' => 7.0, 'title' => 'PRÜFUNG Artverweise',
]);

/** The stored references of a field, as name => reference. */
$references = static function (string $field = 'Begleitvegetation') use ($pdo, $pointId): array {
    $raw = $pdo->query("SELECT extra_species FROM map_points WHERE id = $pointId")->fetchColumn();
    $d = json_decode((string) $raw, true);
    return is_array($d) && isset($d[$field]) && is_array($d[$field]) ? $d[$field] : [];
};

$three = "Fagus sylvatica\nQuercus robur\nBetula pendula";

point_update($pdo, $userId, $pointId, [
    'extra'         => ['Begleitvegetation' => $three],
    'extra_sources' => ['Begleitvegetation' => [
        'Fagus sylvatica' => 'plants', 'Quercus robur' => 'plants', 'Betula pendula' => 'plants',
    ]],
]);
$v = $references();
check(count($v) === 3, 'three names yield three references');
check(str_starts_with((string) ($v['Quercus robur'] ?? ''), 'plants:'),
    'and each one points into the plant list');
$oak = $v['Quercus robur'] ?? '';

// The bug that triggered all this.
point_update($pdo, $userId, $pointId, [
    'note'  => 'nur die Notiz geändert',
    'extra' => ['Begleitvegetation' => $three],
]);
check(count($references()) === 3, 'a change without a new choice does NOT delete them');

// Reordering must not shift anything.
point_update($pdo, $userId, $pointId, [
    'extra' => ['Begleitvegetation' => "Betula pendula\nFagus sylvatica\nQuercus robur"],
]);
check(($references()['Quercus robur'] ?? '') === $oak,
    'after reordering the oak still points to the same taxon');

// Insert a name at the top.
point_update($pdo, $userId, $pointId, [
    'extra'         => ['Begleitvegetation' => "Picea abies, Betula pendula\nFagus sylvatica\nQuercus robur"],
    'extra_sources' => ['Begleitvegetation' => ['Picea abies' => 'plants']],
]);
$v = $references();
check(count($v) === 4, 'an inserted name is added');
check(($v['Quercus robur'] ?? '') === $oak, 'and the others stay where they belong');

// Delete a name.
point_update($pdo, $userId, $pointId, [
    'extra' => ['Begleitvegetation' => "Picea abies, Fagus sylvatica\nQuercus robur"],
]);
$v = $references();
check(!isset($v['Betula pendula']), 'a deleted name takes its reference with it');
check(count($v) === 3 && ($v['Quercus robur'] ?? '') === $oak, 'and only its own');

// What is not written there gets no reference.
point_update($pdo, $userId, $pointId, [
    'extra'         => ['Begleitvegetation' => "Picea abies, Fagus sylvatica\nQuercus robur"],
    'extra_sources' => ['Begleitvegetation' => ['Amanita phalloides' => 'fungi']],
]);
check(!isset($references()['Amanita phalloides']),
    'a smuggled-in name that is missing from the field is rejected');

// A name that does not exist in the list.
point_update($pdo, $userId, $pointId, [
    'extra'         => ['Begleitvegetation' => 'Fichte, vermutlich'],
    'extra_sources' => ['Begleitvegetation' => ['Fichte' => 'plants']],
]);
check($references() === [], 'what is in no list gets no reference — and that is the normal case');

// Field emptied.
point_update($pdo, $userId, $pointId, ['extra' => ['Begleitvegetation' => '']]);
$raw = $pdo->query("SELECT extra_species FROM map_points WHERE id = $pointId")->fetchColumn();
check($raw === null, 'an emptied field leaves nothing behind');

// ---------------------------------------------------------------------------
echo "\nThe old form (one field, one taxon)\n";

// This is how a browser sent it before this change — and how it may still
// sit in the database. Both must keep working.
point_update($pdo, $userId, $pointId, [
    'extra'         => ['Wirt' => 'Fagus sylvatica'],
    'extra_sources' => ['Wirt' => 'plants'],
]);
$v = $references('Wirt');
check(($v['Fagus sylvatica'] ?? '') !== '', 'a source as a plain string is accepted');

// And on the next save the old storage form becomes the new one.
$pdo->exec("UPDATE map_points SET extra_species = '{\"Wirt\": \"plants:abc\"}' WHERE id = $pointId");
point_update($pdo, $userId, $pointId, ['extra' => ['Wirt' => 'Fagus sylvatica']]);
check(($references('Wirt')['Fagus sylvatica'] ?? '') === 'plants:abc',
    'an old reference is carried over and gets its name');

$pdo->exec("UPDATE map_points SET extra_species = '{\"Wirt\": \"plants:abc\"}' WHERE id = $pointId");
point_update($pdo, $userId, $pointId, ['extra' => ['Wirt' => 'Quercus robur']]);
check(!isset($references('Wirt')['Fagus sylvatica']),
    'if something else stands there by now, it is not carried over');

// ---------------------------------------------------------------------------
$pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$pointId]);

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
