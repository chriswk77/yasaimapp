<?php
/**
 * Tests the endangerment classification on the point.
 *
 *   php tests/redlist.php
 *
 * WHY THIS IS TESTED. The Red List filter hides, and hard at that: of
 * two hundred finds a handful carry a classification at all. Whoever
 * relies on it when reporting and it sieves wrongly reports either too
 * much or — worse — a protected species too little.
 *
 * THE CLASSIFICATION IS NOT ON THE POINT but on the species. What is
 * tested is therefore the whole chain: fetch the id from extra_species,
 * look it up on the taxon, unify the scale, and in the end give the same
 * answer as rlMatches() in the browser (assets/js/filter.js).
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

echo "\nUnifying the scale\n";
check(red_list_category('3') === '3', 'a number stays as it is');
check(red_list_category('*') === '*', 'the star for "not endangered" too');
// The fungi list writes the diamond sign, the plant list "nb" — both
// mean "nicht bewertet". Two checkboxes for the same question would be a
// mistake you only notice when one of the two stays empty.
check(red_list_category('♦') === 'nb', 'the fungi\'s diamond sign becomes "nb"');
check(red_list_category('nb') === 'nb', 'and "nb" stays "nb"');
check(red_list_category(' 1 ') === '1', 'edges fall away');
check(red_list_category(null) === null, 'nothing yields no classification');
check(red_list_category('') === null, 'empty likewise');
check(red_list_category('Quatsch') === null, 'the unknown is not invented');

echo "\nThe keys of the title species\n";
$withTitle = json_encode(['' => ['Boletus edulis' => 'fungi:123']]);
check(point_species_keys($withTitle) === ['fungi:123'], 'the title counts');
// ONLY THE TITLE: what stands in Begleitvegetation and Wirt is
// surroundings. A hit via a field that is not even in the point list
// would look like a bug of the filter.
$withSurroundings = json_encode([
    ''                  => ['Boletus edulis'  => 'fungi:123'],
    'Begleitvegetation' => ['Fagus sylvatica' => 'plants:abc'],
]);
check(point_species_keys($withSurroundings) === ['fungi:123'],
    'Begleitvegetation does NOT count');
check(point_species_keys(null) === [], 'without references no key');
check(point_species_keys('kein JSON') === [], 'and nonsense yields no crash');
check(point_species_keys(json_encode(['' => []])) === [], 'an empty title field likewise');

echo "\nLooking up on the taxon\n";
// Fetch one real species with a known classification from each of the
// three lists and check that exactly that one comes back.
$samples = [];
foreach ([['map_tax_dgfm', 'taxnr', 'fungi'],
          ['map_tax_lichens', 'uuid', 'lichens'],
          ['map_tax_plants', 'uuid', 'plants']] as [$table, $column, $source]) {
    if (!table_exists($pdo, $table)) {
        continue;
    }
    $row = $pdo->query(
        "SELECT $column AS k, red_list_de FROM $table
          WHERE red_list_de IS NOT NULL AND red_list_de <> '' LIMIT 1"
    )->fetch();
    if ($row !== false) {
        $samples[$source . ':' . $row['k']] = red_list_category($row['red_list_de']);
    }
}

if ($samples === []) {
    echo "  (skipped — no taxonomy loaded)\n";
} else {
    $result = species_endangerment($pdo, array_keys($samples));
    foreach ($samples as $key => $expected) {
        check(($result[$key] ?? null) === $expected,
            "$key becomes „$expected\"");
    }
    check(count($result) === count($samples), 'no more and no less');
}

check(species_endangerment($pdo, []) === [], 'without keys no query');
check(species_endangerment($pdo, ['quatsch:1', 'fungi:', 'ohnedoppelpunkt']) === [],
    'unusable keys drop out silently');
// GBIF carries no classification for Germany. That is not an error but
// the normal case for everything not recorded here.
check(species_endangerment($pdo, ['gbif:5284517']) === [],
    'GBIF delivers none — it keeps none for Germany');

echo "\nThe filter hits like in the browser\n";
// The counterpart is rlMatches() in assets/js/filter.js. Both must give
// the same answer, otherwise Organisieren shows a different set than the auto
// list carries.
$filter = static fn(array $rl): array => autolist_filter_read(['rl' => $rl]);
$matches = static fn(array $rl, array $point): bool =>
    autolist_matches($filter($rl), '', null, null, [], [], $point);

check($matches([], []) === true, 'nothing ticked means everything — even without a classification');
check($matches([], ['3']) === true, 'nothing ticked means everything, second case');
check($matches(['3'], ['3']) === true, 'ticked and hit');
check($matches(['3'], ['*']) === false, 'ticked and not hit');
check($matches(['3'], []) === false,
    'without a classification the point drops out as soon as anything is ticked');
check($matches(['1', '2', '3'], ['2']) === true, 'several ticked: one suffices');
check($matches(['1', '2'], ['3']) === false, 'several ticked, none hits');

echo "\nThe stored auto-list filter\n";
check($filter(['3', '1'])['rl'] === ['1', '3'],
    'the categories stand in the order of the scale, not of the input');
check($filter(['3', '3'])['rl'] === ['3'], 'duplicates fall away');
check($filter(['Quatsch'])['rl'] === [], 'the unknown falls away');
check(autolist_filter_read([])['rl'] === [], 'without a value empty');
// An old filter from before 15.08.2026 does not know 'rl'. It must keep
// running, otherwise it would carry an empty list from the deployment on.
check(autolist_matches(autolist_filter_read(['begriffe' => []]),
    'irgendwas', null, null, [], [], []) === true,
    'a filter from before the change sieves nothing out');

echo "\nThe whole chain on a real point\n";
$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-roteliste@pruefung.local', '', 'PRUEF', 'RoteListe', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

try {
    // Pick a species with a known classification and set a point on it.
    $species = table_exists($pdo, 'map_tax_dgfm')
        ? $pdo->query(
            "SELECT taxnr, binomial, red_list_de FROM map_tax_dgfm
              WHERE red_list_de IN ('1','2','3') LIMIT 1"
          )->fetch()
        : false;

    if ($species === false) {
        echo "  (skipped — no fungus taxonomy loaded)\n";
    } else {
        $expected = red_list_category($species['red_list_de']);
        $pdo->prepare(
            'INSERT INTO map_points (user_id, lat, lon, title, extra_species, created_at, updated_at)
             VALUES (?, 50.1, 8.2, ?, ?, NOW(), NOW())'
        )->execute([
            $userId,
            $species['binomial'],
            json_encode(['' => [$species['binomial'] => 'fungi:' . $species['taxnr']]]),
        ]);

        $points = points_load($pdo, $userId);
        check(count($points) === 1, 'the point is there');
        check(($points[0]['red_list'] ?? []) === [$expected],
            "points_load attaches „$expected\" — looked up, not stored");

        $single = point_load($pdo, $userId, (int) $points[0]['id']);
        check(($single['red_list'] ?? []) === [$expected],
            'point_load comes to the same result — otherwise list and window would diverge');

        // And a point without a species reference stays without a classification.
        $pdo->prepare(
            'INSERT INTO map_points (user_id, lat, lon, title, created_at, updated_at)
             VALUES (?, 50.2, 8.3, ?, NOW(), NOW())'
        )->execute([$userId, 'Ohne Verweis']);
        $points = points_load($pdo, $userId);
        $without = array_values(array_filter($points, fn($p) => $p['title'] === 'Ohne Verweis'));
        check(($without[0]['red_list'] ?? null) === [],
            'a point without a species reference carries an empty list, not null');

        // THE AUTO LIST IS THE MOST DANGEROUS ROUTE: it stores the Organisieren
        // filter and recomputes it LATER on the server. If it ignored the
        // endangerment, an "RL 3" list would silently carry everything —
        // and you would only notice when reporting.
        if (autolists_ready($pdo)) {
            echo "\nThe auto list computes the same set\n";
            require_once __DIR__ . '/../includes/organize_content.php';

            // Exactly the route Organisieren takes: the checkmarks travel as
            // "3,1" through a form field (writeRl in filter.js).
            $raw = organize_autofilter($pdo, $userId, [
                'list' => '', 'search' => '', 'from' => '', 'to' => '',
                'no_time' => '', 'rl' => $expected,
            ]);
            check($raw['rl'] === [$expected],
                'the form field turns back into categories');

            [$listId, $count] = autolist_create(
                $pdo, $userId, 'PRUEF Gefährdung', autolist_filter_read($raw));
            check($count === 1,
                "the auto list catches exactly the one find with „$expected\"");

            $stmt = $pdo->prepare(
                'SELECT p.title FROM map_point_lists pl
                   JOIN map_points p ON p.id = pl.point_id
                  WHERE pl.list_id = ?'
            );
            $stmt->execute([$listId]);
            check($stmt->fetchAll(PDO::FETCH_COLUMN) === [$species['binomial']],
                'and the right one at that — not the one without a classification');
        }
    }
} finally {
    // Points go with the account via ON DELETE CASCADE.
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
