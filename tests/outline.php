<?php
/**
 * Tests areas: the outline on the point, through the database and back.
 *
 *   php tests/outline.php        (from the project directory)
 *
 * WHY REAL AND NOT REBUILT. The computation itself sits in the browser
 * and is tested in tests/polygon.mjs. This is about what only the
 * database can answer:
 *
 *   DO THE DIGITS STAY. lat and lon sit in DECIMAL columns for good
 *   reason — a find point that drifts a bit on every save would be
 *   exactly the bug this application is meant to fix. The outline, by
 *   contrast, sits in a JSON column, and there numbers are stored as
 *   floating point. Whether the same six decimal places come back is not
 *   a question of reading, but of measuring.
 *
 *   DOES IT NOT VANISH BY ACCIDENT. A save that only changes the title
 *   must not turn an area into a point. Exactly this kind of mistake has
 *   caught the species references once already, and it only shows up
 *   when the work is gone.
 *
 *   AND IS IT CHECKED. What comes out of a tampered request must not go
 *   into the column unseen.
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

echo "\nAreas on the point\n\n";

if (!column_exists($pdo, 'map_points', 'outline')) {
    echo "  (skipped — migration 0034 is not applied)\n\n";
    exit(0);
}

$userId = (int) $pdo->query('SELECT id FROM map_users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId <= 0) {
    echo "  (skipped — there is no user)\n\n";
    exit(0);
}

$created = [];
$create = static function (array $data) use ($pdo, $userId, &$created): int {
    $id = point_create($pdo, $userId, $data + ['title' => 'PRUEF Umriss']);
    $created[] = $id;
    return $id;
};

// A polygon with six decimal places at every corner — and with digits
// that would give themselves away when rounded to a floating-point value.
$outline = [
    [50.123456, 8.654321],
    [50.123457, 8.754321],
    [50.223456, 8.754322],
    [50.223455, 8.654320],
    [50.173456, 8.700001],
];

// ---------------------------------------------------------------------------
echo "There and back\n";

$id = $create(['lat' => 50.173456, 'lon' => 8.704321, 'outline' => $outline]);
$p  = point_load($pdo, $userId, $id);

check($p['outline'] !== null, 'the outline comes back');
check(count($p['outline']) === 5, 'with all five corners');
check($p['outline'] === $outline,
    'digit for digit the same — ' . json_encode($p['outline']));

// And the spot itself stays what it was.
check(abs($p['lat'] - 50.173456) < 1e-9 && abs($p['lon'] - 8.704321) < 1e-9,
    'lat and lon stay untouched');

// A point without an outline stays one.
$without = $create(['lat' => 50.1, 'lon' => 8.1]);
check(point_load($pdo, $userId, $without)['outline'] === null,
    'a point without an outline has none either');

// ---------------------------------------------------------------------------
echo "\nWhat does not come along stays in place\n";

point_update($pdo, $userId, $id, ['title' => 'PRUEF Umriss, umbenannt']);
$p = point_load($pdo, $userId, $id);
check($p['title'] === 'PRUEF Umriss, umbenannt', 'the title is changed');
check($p['outline'] === $outline, 'the outline stands there unchanged');

// A move alone must not take it along either.
point_update($pdo, $userId, $id, ['lat' => 50.17, 'lon' => 8.70]);
check(point_load($pdo, $userId, $id)['outline'] === $outline,
    'a move likewise leaves it standing');

// ---------------------------------------------------------------------------
echo "\nRemoving means null, not omitting\n";

point_update($pdo, $userId, $id, ['outline' => null]);
check(point_load($pdo, $userId, $id)['outline'] === null, 'null takes it away');

point_update($pdo, $userId, $id, ['outline' => $outline]);
check(point_load($pdo, $userId, $id)['outline'] === $outline,
    'and it can be created again');

// ---------------------------------------------------------------------------
echo "\nWhat is unfit does not get in\n";

$unfit = [
    'two corners are no area'           => [[50, 8], [51, 9]],
    'no corners at all'                 => [],
    'a number instead of a list'        => 42,
    'text instead of coordinates'       => [['a', 'b'], ['c', 'd'], ['e', 'f']],
    'latitude beyond the pole'          => [[91, 8], [92, 9], [93, 10]],
    'longitude beyond the date line'    => [[50, 181], [51, 182], [52, 183]],
];
foreach ($unfit as $what => $value) {
    check(point_outline_write($value) === null, $what . ' — rejected');
}

// A SINGLE UNUSABLE CORNER MUST NOT PREVENT THE WHOLE SAVE. Whoever
// stands in the forest and saves should not fail on a number they never
// saw — the rest of the outline is fine after all.
$mixed = point_outline_write([[50, 8], [50, 9], ['x', 'y'], [51, 9], [51, 8]]);
check($mixed !== null && count(json_decode($mixed, true)) === 4,
    'a broken corner falls out, the remaining ones stay');

// Rounding is to six decimal places, like lat and lon.
$rounded = json_decode(point_outline_write([
    [50.1234567891, 8.9876543219], [50.2, 8.9], [50.3, 8.8],
]), true);
check($rounded[0] === [50.123457, 8.987654],
    'more than six decimal places are rounded — ' . json_encode($rounded[0]));

// The upper limit takes hold, and what lies above it is cut off instead
// of rejected — otherwise an import would lose the whole area instead of
// its tips.
$many = [];
for ($i = 0; $i < OUTLINE_CORNERS_MAX + 500; $i++) {
    $many[] = [50 + $i / 100000, 8 + $i / 100000];
}
check(count(json_decode(point_outline_write($many), true)) === OUTLINE_CORNERS_MAX,
    'at ' . OUTLINE_CORNERS_MAX . ' corners it ends');

// ---------------------------------------------------------------------------
echo "\nWhat sits in the column is re-checked on reading\n";

foreach ([
    'no JSON'                    => 'irgendwas',
    'an object instead of a list' => '{"a": 1}',
    'too few corners'            => '[[50,8],[51,9]]',
    'a corner without a longitude' => '[[50,8],[51,9],[52]]',
] as $what => $raw) {
    check(point_outline_read($raw) === null, $what . ' — yields null');
}

// ---------------------------------------------------------------------------
echo "\nThe outline goes along through the trash\n";

point_delete($pdo, $userId, $id);
point_restore($pdo, $userId, $id);
check(point_load($pdo, $userId, $id)['outline'] === $outline,
    'after deleting and restoring it is still there');

// ---------------------------------------------------------------------------
foreach ($created as $pid) {
    $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ?')->execute([$pid]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ? AND user_id = ?')->execute([$pid, $userId]);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
