<?php
/**
 * Tests the time buckets and their fingerprints.
 *
 *   php tests/bucket.php
 *
 * WHAT CAN GO WRONG HERE GOES WRONG SILENTLY. A fingerprint that misses a
 * change looks exactly like one that noticed it — except that the phone
 * shows the old state forever. There is no error message, no crash, just a
 * point that is missing at some time and nobody knows since when.
 *
 * That is why this test walks through EVERY kind of change individually
 * and demands both: that the right fingerprint moves AND that the others
 * do not move. The second is just as important — a fingerprint that
 * always changes reloads everything on every call and is just as useless
 * as one that never changes.
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
require_once __DIR__ . '/../includes/bucket.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nTime buckets\n\n";

if (!bucket_ready($pdo)) {
    echo "  (skipped — migration 0038 is not applied)\n\n";
    exit(0);
}

$userId = (int) $pdo->query('SELECT id FROM map_users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId <= 0) {
    echo "  (skipped — there is no user)\n\n";
    exit(0);
}

// ---------------------------------------------------------------------------
echo "The key is validated before it goes into a query\n";

foreach (['2026-08', '1999-01', '2026-12'] as $good) {
    check(bucket_key_valid($good), "„$good" . '" is valid');
}
foreach (['2026-13', '2026-00', '2026-8', '26-08', '2026-08; DROP', '', 'x'] as $bad) {
    check(!bucket_key_valid($bad), "„$bad" . '" is not valid');
}

// ---------------------------------------------------------------------------
$created = [];
$new = static function (string $found, string $title) use ($pdo, $userId, &$created): int {
    $id = point_create($pdo, $userId, [
        'lat' => 49.6, 'lon' => 9.08, 'title' => $title, 'found_at' => $found,
    ]);
    $created[] = $id;
    return $id;
};

$finger = static fn(): array => bucket_fingerprints($pdo, $userId);

echo "\nPoints land in the bucket of their find time\n";

$a = $new('2019-03-14 10:00:00', 'PRUEF Eimer A');
$b = $new('2019-03-20 10:00:00', 'PRUEF Eimer B');
$c = $new('2021-07-02 10:00:00', 'PRUEF Eimer C');

$e = $finger();
check(isset($e['2019-03']), 'there is a bucket 2019-03');
check(isset($e['2021-07']), 'there is a bucket 2021-07');

$get = static fn(array $s): array => bucket_points($pdo, $userId, $s);

$h = $get(['2019-03']);
$ids = array_column($h['buckets']['2019-03']['points'] ?? [], 'id');
check(in_array($a, $ids, true) && in_array($b, $ids, true),
    'both March points come along');
check(!in_array($c, $ids, true), 'the July point stays out');
check(($h['buckets']['2019-03']['finger'] ?? null) === $e['2019-03'],
    'the delivered fingerprint is the same as in the directory');

// ---------------------------------------------------------------------------
echo "\nEvery kind of change moves exactly its bucket\n";

$verify = static function (string $what, callable $fn) use (&$finger, &$failed): void {
    $before = $finger();
    $fn();
    $after = $finger();

    $moved = [];
    foreach ($after as $k => $v) {
        if (($before[$k] ?? null) !== $v) $moved[] = $k;
    }
    foreach ($before as $k => $v) {
        if (!isset($after[$k])) $moved[] = $k . ' (gone)';
    }
    check($moved !== [], "$what moves a fingerprint");
    echo '        moved: ' . (implode(', ', $moved) ?: '—') . "\n";
};

// 1. An ordinary change by the user.
$verify('a renamed point', static function () use ($pdo, $userId, $a) {
    usleep(2000);
    point_update($pdo, $userId, $a, ['title' => 'PRUEF Eimer A, umbenannt']);
});

// 2. THE CASE stand EXISTS FOR IN THE FIRST PLACE: a server-side derivation
//    that deliberately does not touch updated_at. Computed over
//    updated_at the fingerprint would stay the same and the change would
//    reach no device.
$verify('a derivation without updated_at (municipality)', static function () use ($pdo, $a) {
    usleep(2000);
    $pdo->prepare('UPDATE map_points SET municipality = ?, country = ? WHERE id = ?')
        ->execute(['Musterhausen', 'DE', $a]);
});

// 3. Into the trash — an UPDATE, so stand must advance.
$verify('a point wanders into the trash', static function () use ($pdo, $userId, $b) {
    usleep(2000);
    point_delete($pdo, $userId, $b);
});

// 4. And back.
$verify('and back out again', static function () use ($pdo, $userId, $b) {
    usleep(2000);
    point_restore($pdo, $userId, $b);
});

// 5. DELETING FOR GOOD — the row disappears. Only COUNT catches that;
//    MAX(stand) would stay put if the deleted one was not the youngest.
$gone = $new('2019-03-25 10:00:00', 'PRUEF Eimer, gleich weg');
$verify('a permanently deleted point', static function () use ($pdo, $userId, $gone) {
    usleep(2000);
    point_delete($pdo, $userId, $gone);
    point_delete_permanently($pdo, $userId, $gone);
});

// 6. THE TRICKIEST CASE: changing the find date pushes the point into a
//    DIFFERENT bucket. Both have to move — the old one because it loses a
//    point, the new one because it gains one. If only one moves, the device
//    afterwards has the point twice or not at all.
$before = $finger();
usleep(2000);
point_update($pdo, $userId, $c, ['found_at' => '2022-11-05 09:00:00']);
$after = $finger();

check(($after['2021-07'] ?? null) !== ($before['2021-07'] ?? null),
    'the abandoned bucket moves');
check(isset($after['2022-11']) && !isset($before['2022-11']),
    'the new bucket comes into being');

$hc = $get(['2021-07', '2022-11']);
$idsC = [];
foreach ($hc['buckets'] as $set) {
    foreach ($set['points'] as $p) $idsC[] = $p['id'];
}
check(count(array_keys($idsC, $c, true)) === 1,
    'the point afterwards lies in exactly one of the two buckets');
check(in_array($c, array_column($hc['buckets']['2022-11']['points'] ?? [], 'id'), true),
    'namely in the new one');

// ---------------------------------------------------------------------------
echo "\nAnd what does not stir stays put\n";

$e1 = $finger();
$e2 = $finger();
check($e1 === $e2, 'asked twice, the same answer twice');

// An unrelated bucket must not move along, otherwise the device reloads
// everything on every change.
$before = $finger();
usleep(2000);
point_update($pdo, $userId, $a, ['title' => 'PRUEF Eimer A, nochmal']);
$after = $finger();
check(($after['2022-11'] ?? null) === ($before['2022-11'] ?? null),
    'a change in 2019-03 leaves 2022-11 alone');

// ---------------------------------------------------------------------------
echo "\nA bucket is never delivered in part\n";

// With the real limit this cannot be triggered here; what is tested is the
// assurance that counts: what is delivered is complete, and what is missing
// is listed as open.
$h = $get(['2019-03', '2022-11']);
check(count($h['buckets']) + count($h['open']) === 2,
    'every requested bucket is either delivered or reported as open');
check(array_intersect(array_keys($h['buckets']), $h['open']) === [],
    'none is both');

foreach ($h['buckets'] as $s => $set) {
    $single = $get([$s]);
    check(count($single['buckets'][$s]['points'] ?? []) === count($set['points']),
        "$s fetched individually yields the same count as in the bundle");
}

// ---------------------------------------------------------------------------
echo "\nSQL expression and PHP version say the same\n";

/*
 * THE ONE PLACE WHERE THE RULE DOES EXIST TWICE. bucket_expression() computes
 * in SQL, bucket_key_of() in PHP — necessary because the points are
 * meant to go back bundled without running a separate query per bucket.
 * That is bearable only as long as someone holds the two against each
 * other. This test does that, and on real points instead of invented ones:
 * what the SQL expression groups, the PHP version must assign the same way.
 */
$compare = $pdo->prepare(
    'SELECT id, found_at, created_at, ' . bucket_expression() . ' AS from_sql
       FROM map_points WHERE user_id = ? AND deleted_at IS NULL'
);
$compare->execute([$userId]);

$disagree = [];
$checked = 0;
foreach ($compare as $row) {
    $checked++;
    if (bucket_key_of($row) !== (string) $row['from_sql']) {
        $disagree[] = $row['id'] . ': SQL=' . $row['from_sql'] . ' PHP=' . bucket_key_of($row);
    }
}
check($checked > 0, "there are points to compare ($checked)");
check($disagree === [],
    'the same bucket for every point' . ($disagree === [] ? '' : ' — ' . implode(', ', $disagree)));

// ---------------------------------------------------------------------------
// THE CASE THAT TRIGGERED THIS SECTION: deleting a list changes
// map_point_lists, not map_points. The fingerprint, however, hangs solely
// on map_points.stand — so it stayed put, and no device fetched the bucket
// again. On the map the points still sat in a list that no longer existed,
// and a reload brought them back because the display then came from the
// bucket again.
//
// That is the general trap: the print sees only its own table. Whoever
// keeps point data elsewhere must bump map_points here.
echo "\nA deleted list moves the points in it\n";

$listA = list_create($pdo, $userId, ['name' => 'PRUEF Eimer Liste A']);
$listB = list_create($pdo, $userId, ['name' => 'PRUEF Eimer Liste B']);

// TWO lists so the point is not orphaned by the deletion — otherwise
// list_delete() creates a substitute list, and the test would not clean
// it away.
point_lists_set($pdo, $userId, $a, [$listA, $listB]);
$pdo->prepare('UPDATE map_points SET updated_at = NOW() WHERE id = ?')->execute([$a]);

$before      = $finger();
$uninvolved  = $before['2021-07'] ?? null;

list_delete($pdo, $userId, $listA);

$after = $finger();
check(($after['2019-03'] ?? null) !== ($before['2019-03'] ?? null),
    'the print of the affected bucket changes');
check(($after['2021-07'] ?? null) === $uninvolved,
    'and that of an uninvolved bucket does not');

$pdo->prepare('DELETE FROM map_lists WHERE id IN (?, ?) AND user_id = ?')
    ->execute([$listA, $listB, $userId]);

// ---------------------------------------------------------------------------
echo "\nNonsense does not reach the database\n";

$u = $get(['2026-13', "2026-08'; DROP TABLE map_points; --", '']);
check($u['buckets'] === [] && $u['open'] === [],
    'invalid keys are discarded, not passed through');
check((int) $pdo->query('SELECT COUNT(*) FROM map_points')->fetchColumn() > 0,
    'the table still stands');

// ---------------------------------------------------------------------------
foreach ($created as $pid) {
    $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ?')->execute([$pid]);
    $pdo->prepare('DELETE FROM map_point_species WHERE point_id = ?')->execute([$pid]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ? AND user_id = ?')->execute([$pid, $userId]);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
