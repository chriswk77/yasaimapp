<?php
/**
 * The buckets of the recorded tracks and their fingerprints.
 *
 *   php tests/trackbuckets.php
 *
 * THE SAME SILENCE AS AT THE POINTS' BUCKETS (tests/bucket.php explains
 * it): a fingerprint that misses a change looks exactly like one that
 * noticed it, only the phone shows the old walk for ever. So every kind
 * of change a track can undergo is walked through here — there are only
 * two, a re-upload and a deletion — and both are demanded: the right
 * print moves, the others stay put.
 *
 * AND THE SHAPE OF A STORED TRACK IS FROZEN HERE, the way
 * tests/pointstore.php freezes the point's: the device keeps what the
 * server sent under a fingerprint that hashes values, not names. Whoever
 * adds a field to track_shape() raises VERSION in assets/js/trackstore.js
 * — this test fails until the record below is raised with it, and that
 * is the reminder.
 *
 * Everything created is removed again at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/tracks.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nTrack buckets\n\n";

if (!tracks_ready($pdo)) {
    echo "  (skipped — migration 0084 is not applied)\n\n";
    exit(0);
}

$userId = (int) $pdo->query('SELECT id FROM map_users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId <= 0) {
    echo "  (skipped — there is no user)\n\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Fixtures: a short walk beginning at a given moment. Ten metres per step
// so the sieve keeps every position — what is measured here is the
// bucketing, not the sieve (that is tests/tracks.php).
$created = [];
$walk = static function (string $clientId, string $begin, int $steps = 20) use ($pdo, $userId, &$created): array {
    $t = strtotime($begin) * 1000;
    $positions = [];
    for ($i = 0; $i < $steps; $i++) {
        $positions[] = [50.1 + $i * 10 / 111320, 8.7, 200.0, $t + $i * 10000, 4.0];
    }
    $created[$clientId] = true;
    return track_store($pdo, $userId, ['client_id' => $clientId, 'name' => 'PRUEF ' . $clientId, 'positions' => $positions]);
};

$finger = static fn(): array => tracks_bucket_status($pdo, $userId)['finger'];

echo "A track lands in the bucket of the month it began\n";

$a = $walk('PRUEF-EIMER-A', '2019-03-14 10:00:00');
$b = $walk('PRUEF-EIMER-B', '2019-03-20 10:00:00');
$c = $walk('PRUEF-EIMER-C', '2021-07-02 10:00:00', 40);

$e = $finger();
check(isset($e['2019-03']), 'there is a bucket 2019-03');
check(isset($e['2021-07']), 'there is a bucket 2021-07');

$status = tracks_bucket_status($pdo, $userId);
check(($status['counts']['2019-03'] ?? 0) >= 2, 'the count says at least two tracks in March');
// The sum counts what the wire carries — the drawing's vertices, and a
// straight fixture draws as its two ends (0100), not as forty positions.
check(($status['positions']['2021-07'] ?? 0) === 2, 'the vertex sum counts the July walk\'s drawing',
    (string) ($status['positions']['2021-07'] ?? 0));

$get = static fn(array $k): array => tracks_bucket_get($pdo, $userId, $k);

$h = $get(['2019-03']);
$ids = array_column($h['buckets']['2019-03']['tracks'] ?? [], 'client_id');
check(in_array('PRUEF-EIMER-A', $ids, true) && in_array('PRUEF-EIMER-B', $ids, true),
    'both March walks come along');
check(!in_array('PRUEF-EIMER-C', $ids, true), 'the July walk stays out');
check(($h['buckets']['2019-03']['finger'] ?? null) === $e['2019-03'],
    'the delivered fingerprint is the same as in the directory');

$first = $h['buckets']['2019-03']['tracks'][0] ?? [];
check(is_array($first['drawing'] ?? null) && count($first['drawing']) > 0,
    'the drawing rides along — a bucket is the walks themselves, not a listing');

// ---------------------------------------------------------------------------
echo "\nEvery kind of change moves exactly its bucket\n";

$verify = static function (string $what, callable $fn, string $expect) use (&$finger, &$failed): void {
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
    check(in_array($expect, $moved, true), "$what moves $expect");
    check(count($moved) === 1, "… and nothing else (moved: " . (implode(', ', $moved) ?: '—') . ')');
};

// 1. THE RE-UPLOAD — the same client_id, a grown walk. updated_at moves.
$verify('a re-uploaded walk', static function () use ($walk) {
    sleep(1);   // updated_at is a second-precision DATETIME
    $walk('PRUEF-EIMER-A', '2019-03-14 10:00:00', 30);
}, '2019-03');

// 2. THE DELETION — the row goes; only COUNT can see that.
$verify('a deleted walk', static function () use ($pdo, $userId, &$created) {
    track_remove($pdo, $userId, 'PRUEF-EIMER-B');
    unset($created['PRUEF-EIMER-B']);
}, '2019-03');

// ---------------------------------------------------------------------------
echo "\nThe stock from before 0100 gets its drawing on first delivery\n";

{
    // A walk whose drawing is taken away again — the shape of a row that
    // was stored before the column existed.
    $walk('PRUEF-EIMER-D', '2018-05-05 10:00:00', 25);
    $pdo->prepare('UPDATE map_tracks SET drawing = NULL, drawing_count = 0 WHERE user_id = ? AND client_id = ?')
        ->execute([$userId, 'PRUEF-EIMER-D']);
    $stampBefore = $pdo->query("SELECT updated_at FROM map_tracks WHERE client_id = 'PRUEF-EIMER-D'")->fetchColumn();
    $fingerBefore = $finger()['2018-05'] ?? null;
    check(($finger() === $finger()) && $fingerBefore !== null, 'there is a bucket 2018-05');

    $d = $get(['2018-05'])['buckets']['2018-05']['tracks'][0] ?? [];
    check(is_array($d['drawing'] ?? null) && count($d['drawing']) === 2,
        'the delivery draws the walk — a straight fixture as its two ends',
        (string) count($d['drawing'] ?? []));
    $row = $pdo->query("SELECT drawing, drawing_count, updated_at FROM map_tracks WHERE client_id = 'PRUEF-EIMER-D'")->fetch();
    check($row['drawing'] !== null && (int) $row['drawing_count'] === 2, 'and keeps the drawing beside the original');
    check($row['updated_at'] === $stampBefore && ($finger()['2018-05'] ?? null) === $fingerBefore,
        'without moving updated_at or the fingerprint — a derived column is not a change');
}

// ---------------------------------------------------------------------------
echo "\nA cut is placed by the spot, on the original\n";

{
    // The walk runs north, ten metres a step; a spot four metres past
    // position 12 and three metres to the east lies on the segment 12 → 13
    // — and within the floor of position 12, so the cut IS that position
    // (track_split_between snaps). No index travels: -1 says so.
    $before = $finger()['2018-05'] ?? null;
    $cut = track_split($pdo, $userId, 'PRUEF-EIMER-D', -1, [50.1 + 12 * 10 / 111320 + 4 / 111320, 8.7 + 3 / 71000]);
    unset($created['PRUEF-EIMER-D']);
    foreach ($cut['tracks'] as $half) $created[$half['client_id']] = true;
    [$head, $tail] = $cut['tracks'];
    check(count($head['positions']) === 13 && count($tail['positions']) === 13,
        'the spot finds its segment: the head runs to position 12, the tail starts there');
    check($head['positions'][12] === $tail['positions'][0]
        && abs($head['positions'][12][0] - (50.1 + 12 * 10 / 111320)) < 1e-5,
        'the cut is position 12 itself, shared by both halves');
    check(($finger()['2018-05'] ?? null) !== $before, 'and the bucket moves — two walks where one was');
    $halves = $get(['2018-05'])['buckets']['2018-05']['tracks'] ?? [];
    check(count($halves) === 2 && count($halves[0]['drawing']) >= 2 && count($halves[1]['drawing']) >= 2,
        'both halves come with a drawing of their own');
}

// ---------------------------------------------------------------------------
echo "\nThe name of a tour is what its finds say\n";

if (!tracks_assignment_ready($pdo) || !column_exists($pdo, 'map_points', 'tour_id')) {
    echo "  (skipped — migration 0099 is not applied)\n";
} else {
    // A walk the app filed under its own minted name, then a find of the
    // same tour with the name the user gave it.
    $tourId = 'ab12cd34ef56';
    $t = strtotime('2016-08-08 10:00:00') * 1000;
    $pos = [];
    for ($i = 0; $i < 12; $i++) $pos[] = [50.1 + $i * 10 / 111320, 8.7, 200.0, $t + $i * 10000, 4.0];
    $created['PRUEF-EIMER-T'] = true;
    $stored = track_store($pdo, $userId, ['client_id' => 'PRUEF-EIMER-T', 'positions' => $pos,
                                          'tour_id' => $tourId, 'tour' => 'Tagestour #5']);
    check($stored['tour'] === 'Tagestour #5', 'without a find the app\'s name stands');
    $printBefore = $finger()['2016-08'] ?? null;
    sleep(1);   // updated_at is a second-precision DATETIME, and the print hashes it

    $pointId = point_create($pdo, $userId, ['lat' => 50.1, 'lon' => 8.7, 'title' => 'PRUEF Tourname',
                                            'found_at' => '2016-08-08 10:30:00',
                                            'tour_id' => $tourId, 'tour' => 'Soonwald']);
    $named = static fn(): string => (string) $pdo->query(
        "SELECT tour FROM map_tracks WHERE client_id = 'PRUEF-EIMER-T'")->fetchColumn();
    check($named() === 'Soonwald', 'a find born into the tour names the walk after itself');
    check(($finger()['2016-08'] ?? null) !== $printBefore, 'and the bucket moves — a device shows the name');

    sleep(1);   // updated_at is a second-precision DATETIME
    point_update($pdo, $userId, $pointId, ['tour' => 'Soonwald Nord']);
    check($named() === 'Soonwald Nord', 'a renamed tour renames the walk');

    // The app sends the same walk again, still under its minted name: the
    // finds' name stays.
    $again = track_store($pdo, $userId, ['client_id' => 'PRUEF-EIMER-T', 'positions' => $pos,
                                         'tour_id' => $tourId, 'tour' => 'Tagestour #5']);
    check($again['tour'] === 'Soonwald Nord', 'a re-upload does not bring the app\'s name back');

    // A walk of ANOTHER tour keeps its own name.
    $walk('PRUEF-EIMER-U', '2016-08-09 10:00:00');
    $pdo->prepare("UPDATE map_tracks SET tour_id = 'ffffffffffff', tour = 'Andere' WHERE client_id = 'PRUEF-EIMER-U'")->execute();
    point_update($pdo, $userId, $pointId, ['tour' => 'Soonwald Süd']);
    check((string) $pdo->query("SELECT tour FROM map_tracks WHERE client_id = 'PRUEF-EIMER-U'")->fetchColumn() === 'Andere',
        'a walk of another tour is left alone');

    point_delete($pdo, $userId, $pointId);
    point_delete_permanently($pdo, $userId, $pointId);
}

// ---------------------------------------------------------------------------
echo "\nAnd what does not stir stays put\n";

$e1 = $finger();
$e2 = $finger();
check($e1 === $e2, 'asked twice, the same answer twice');

// ---------------------------------------------------------------------------
echo "\nA bucket is never delivered in part\n";

$h = $get(['2019-03', '2021-07']);
check(count($h['buckets']) + count($h['open']) === 2,
    'every requested bucket is either delivered or reported as open');
check(array_intersect(array_keys($h['buckets']), $h['open']) === [],
    'none is both');
check($get(['2019-03', 'nonsense', '2026-13'])['buckets'] !== [] && !isset($get(['nonsense'])['buckets']['nonsense']),
    'a key that is no key is dropped before it reaches a query');

// The reporting of a bucket that no longer exists: empty, with a
// fingerprint of its own, so the device throws its copy away.
$gone = $get(['1999-01']);
check(isset($gone['buckets']['1999-01']) && $gone['buckets']['1999-01']['tracks'] === [],
    'a bucket that does not exist is still delivered — empty');

// ---------------------------------------------------------------------------
echo "\nSQL expression and PHP version say the same\n";

$compare = $pdo->prepare(
    'SELECT id, started_at, created_at, ' . track_bucket_expression() . ' AS from_sql
       FROM map_tracks WHERE user_id = ?'
);
$compare->execute([$userId]);
$disagree = [];
$checked  = 0;
foreach ($compare as $row) {
    $checked++;
    if (track_bucket_key_of($row) !== (string) $row['from_sql']) {
        $disagree[] = $row['id'] . ': SQL=' . $row['from_sql'] . ' PHP=' . track_bucket_key_of($row);
    }
}
check($checked > 0, "there are tracks to compare ($checked)");
check($disagree === [], 'the same bucket for every track' . ($disagree === [] ? '' : ' — ' . implode(', ', $disagree)));

// ---------------------------------------------------------------------------
echo "\nThe shape of a stored track is frozen\n";

/*
 * VERSION 4 of assets/js/trackstore.js stores exactly these keys. Adding
 * one on the server (track_shape) means raising that VERSION — the store
 * cannot grow a field by itself, and a fingerprint would never tell it.
 * (Version 2 brought tour, tour_id and excursion_id, migration 0099;
 * version 3 swapped positions for drawing, migration 0100 — the buckets
 * carry the simplified line, the original stays behind GET ?id=; version
 * 4 changed no key, only the drawing's rule, migration 0101.)
 */
$frozen = ['box', 'client_id', 'created_at', 'device', 'distance_m', 'drawing', 'ended_at',
           'excursion_id', 'id', 'name', 'point_count', 'started_at', 'tour', 'tour_id', 'updated_at'];
$keys = array_keys($first);
sort($keys);
check($keys === $frozen,
    'the keys of a track in a bucket are the frozen set — VERSION 4 of trackstore.js'
    . ($keys === $frozen ? '' : "\n        got: " . implode(', ', $keys)));
check(!array_key_exists('positions', $first) && is_array($first['drawing']),
    'a bucket carries the drawing and not the original');

// ---------------------------------------------------------------------------
foreach (array_keys($created) as $clientId) {
    try { track_remove($pdo, $userId, $clientId); } catch (InvalidArgumentException) {}
}
$left = $pdo->prepare("SELECT COUNT(*) FROM map_tracks WHERE user_id = ? AND client_id LIKE 'PRUEF-EIMER-%'");
$left->execute([$userId]);
check((int) $left->fetchColumn() === 0, 'everything created is gone again');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
