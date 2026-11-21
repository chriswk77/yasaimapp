<?php
/**
 * The stored point and the version that guards it.
 *
 *   php tests/pointstore.php
 *
 * WHY THIS TEST EXISTS. Three times in one day a field was added to the
 * point payload without raising VERSION in assets/js/pointstore.js — the
 * photos and the Red List (22.08.2026), the taxonomy, and finally
 * excursion_id. Every time the same silence followed: a fingerprint hashes
 * VALUES, not names, so a bucket written before the change counts as
 * current for ever, and its points simply lack the new field. Nothing
 * breaks. Something merely stops working — a filter that finds nothing, a
 * count that says zero — and no picture says why.
 *
 * A RULE ONE HAS TO REMEMBER IS NOT A GUARD. The reasoning stood in
 * pointstore.js in capitals and did not save me the third time. This does:
 * it holds the KEY SET of the payload against a frozen record, together
 * with the version number. Add a field and this test fails until both are
 * raised.
 *
 * THE SAME TRICK tests/services.php PLAYS with the external addresses:
 * whoever changes one changes the record too, and thereby STATES that
 * they meant it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';

$failed = 0;
function check(bool $ok, string $what): void
{
    global $failed;
    if ($ok) {
        echo "  ok    $what\n";
        return;
    }
    $failed++;
    echo "  FAILED  $what\n";
}

/**
 * What a point in a bucket carries.
 *
 * SORTED, so the record does not depend on the order point_prepare()
 * happens to build them in — that order is a matter of reading, not of
 * contract.
 */
$expected = [
    'accuracy_m', 'co_owners', 'country', 'created_at', 'deleted_at', 'dgfm',
    'elevation_accuracy_m', 'elevation_ellipsoid_m', 'elevation_m', 'elevation_source',
    'excursion_id', 'extra', 'field_times', 'find_number', 'folder', 'found_at',
    'id', 'lat', 'lists', 'lon', 'mtb', 'mtb_quadrant', 'municipality',
    'note', 'outline', 'photos', 'protected_area', 'recordings', 'red_list', 'region',
    'taxonomy', 'title', 'tour', 'tour_id', 'updated_at',
];

/** The version this record belongs to — raise BOTH or neither.
 *  7: 'scale' on the photo objects INSIDE a point (migration 0088) — a
 *  sub-shape change this record cannot see; the version had to move
 *  alone. Whoever changes a nested shape is on their own honour here.
 *  8: 'measures' on the same photo objects (migration 0092) — the same
 *  invisible move as 7.
 *  9: 'dgfm' — the DGfM's marks on the title species (17.09.2026). Two
 *  fields ride along under this number that arrived earlier without a
 *  bump and without this record noticing them: 'co_owners' (co-ownership,
 *  11.09.2026) and 'recordings' (the cloud-recording count). Both are
 *  read defensively in map.js, so their absence hid nothing; 'dgfm' is
 *  not — a stored point without it fails the Speisewert filter. */
$expectedVersion = 9;

echo "\nThe shape of a stored point\n";

$user = (int) $pdo->query('SELECT user_id FROM map_points LIMIT 1')->fetchColumn();
$points = $user > 0 ? points_load($pdo, $user) : [];

if ($points === []) {
    echo "  (übersprungen — kein Punkt in dieser Datenbank)\n";
} else {
    $keys = array_keys($points[array_key_first($points)]);
    sort($keys);

    $added   = array_values(array_diff($keys, $expected));
    $removed = array_values(array_diff($expected, $keys));

    check($added === [], 'no field has been added'
        . ($added ? ' — new: ' . implode(', ', $added) : ''));
    check($removed === [], 'and none has vanished'
        . ($removed ? ' — gone: ' . implode(', ', $removed) : ''));

    if ($added !== [] || $removed !== []) {
        echo "\n  THE SHAPE HAS CHANGED. Every device holds points in the old one,\n"
           . "  and their fingerprints will never say so. Two things belong\n"
           . "  together now:\n\n"
           . "    1. raise VERSION in assets/js/pointstore.js (currently "
           . $expectedVersion . " here),\n"
           . "    2. write the new field into the record in this file.\n\n"
           . "  Whoever does only the first leaves this test red; whoever does\n"
           . "  only the second silently breaks every phone in the woods.\n\n";
    }
}

echo "\nThe version that guards it\n";

$source = (string) @file_get_contents(__DIR__ . '/../assets/js/pointstore.js');
check($source !== '', 'assets/js/pointstore.js is readable');

if (preg_match('/^const VERSION = (\d+);/m', $source, $m)) {
    $version = (int) $m[1];
    check($version === $expectedVersion,
        "VERSION is $expectedVersion — pointstore.js says $version");
} else {
    check(false, 'VERSION stands in pointstore.js as "const VERSION = <n>;"');
}

/* THE CLEARING MUST REACH EVERY OLDER STORE. It once read "< 4" as a
   written-out number, and the next raise would have left version 4 and 5
   devices holding their old records — the very failure the version exists
   to prevent, one step further along. */
check(str_contains($source, 'event.oldVersion < VERSION'),
    'the upgrade clears everything older than VERSION, not older than a fixed number');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
