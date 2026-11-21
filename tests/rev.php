<?php
/**
 * Tests that no write path can sneak past stand.
 *
 *   php tests/rev.php
 *
 * WHY THIS TEST EXISTS. stand is the column by which a device recognizes
 * that something changed — the fingerprint of the buckets runs over it.
 * If ONE write path does not move it, its changes reach no device, and
 * silently at that: the data is right on the server, the phone shows the
 * old, and nobody looks in this place.
 *
 * EXACTLY THAT HAS ALREADY HAPPENED, with updated_at: of five writing
 * places exactly one thought of it. 5,000 backfilled municipalities left
 * a fingerprint over them unchanged character for character.
 *
 * The column is therefore maintained by the database itself (ON UPDATE
 * CURRENT_TIMESTAMP), not by the code. This test pins that down — and it
 * does not check the spelling of the column definition but what ends up
 * in the row: it really writes and really looks.
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

echo "\nstand — the fingerprint of every change\n\n";

if (!column_exists($pdo, 'map_points', 'touched_at')) {
    echo "  (skipped — migration 0038 has not been applied)\n\n";
    exit(0);
}

$userId = (int) $pdo->query('SELECT id FROM map_users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId <= 0) {
    echo "  (skipped — there is no user)\n\n";
    exit(0);
}

/** The current stand of a point. */
$stateOf = static function (int $id) use ($pdo): ?string {
    $s = $pdo->prepare('SELECT touched_at FROM map_points WHERE id = ?');
    $s->execute([$id]);
    return $s->fetchColumn() ?: null;
};

$created = [];
$make = static function (array $data = []) use ($pdo, $userId, &$created): int {
    $id = point_create($pdo, $userId, $data + [
        'lat' => 49.605077, 'lon' => 9.086776, 'title' => 'PRUEF stand',
    ]);
    $created[] = $id;
    return $id;
};

// ---------------------------------------------------------------------------
echo "The column maintains itself\n";

$column = $pdo->query("SHOW COLUMNS FROM map_points LIKE 'touched_at'")->fetch();
check(str_contains(strtolower((string) $column['Extra']), 'on update'),
    'ON UPDATE stands in the table definition, not in the code');
check(str_contains(strtolower((string) $column['Type']), '(6)'),
    'with microseconds — otherwise chunks in the same second collide');
check(stripos((string) $column['Type'], 'datetime') === 0,
    'DATETIME, not TIMESTAMP — that one ends in 2038');

// ---------------------------------------------------------------------------
echo "\nEvery write path moves it\n";

$id = $make();
$before = $stateOf($id);
check($before !== null, 'a new point gets a stand immediately');

// 1. The ordinary change by the user.
usleep(2000);
point_update($pdo, $userId, $id, ['title' => 'PRUEF stand, umbenannt']);
$after1 = $stateOf($id);
check($after1 !== $before, 'point_update() moves it');

// 2. A derivation that DELIBERATELY does not touch updated_at — the
//    case the old solution failed on.
usleep(2000);
$g = $pdo->prepare('SELECT updated_at FROM map_points WHERE id = ?');
$g->execute([$id]);
$changedBefore = $g->fetchColumn();

// A DIFFERENT VALUE THAN THE EXISTING ONE. The first attempt wrote
// "Kirchzell" — and exactly that already stood there, because
// point_create() fetches the municipality itself and the test point
// really lies there. MySQL rightly did not touch the row, and the test
// failed although the column did what it should. It almost seduced into
// "repairing" the column.
$pdo->prepare(
    'UPDATE map_points SET municipality = ?, region = ?, country = ?, municipality_source = ?
      WHERE id = ? AND user_id = ?'
)->execute(['Musterhausen', 'SLH', 'DE', 'osm', $id, $userId]);

$g->execute([$id]);
$after2 = $stateOf($id);
check($after2 !== $after1,
    'a derivation without updated_at moves it anyway — THE actual purpose');
check($g->fetchColumn() === $changedBefore,
    'and leaves updated_at alone while doing so — the precedence in the sync stays');

// 3. Deleting and bringing back.
usleep(2000);
point_delete($pdo, $userId, $id);
$after3 = $stateOf($id);
check($after3 !== $after2, 'deleting moves it');

usleep(2000);
point_restore($pdo, $userId, $id);
check($stateOf($id) !== $after3, 'bringing back moves it');

// ---------------------------------------------------------------------------
echo "\nAnd it stands still where nothing happens\n";

$quiet = $stateOf($id);
usleep(2000);

// WRITE BACK THE EXISTING VALUE, not just any. What stands here depends
// on what the tests above have done — a hard-coded name would be a real
// change again tomorrow, and the test would fail without anything being
// broken. Exactly that has happened here twice already.
$s = $pdo->prepare('SELECT municipality FROM map_points WHERE id = ?');
$s->execute([$id]);
$existing = $s->fetchColumn();

// MySQL recognizes the unchanged value and does not touch the row — for
// a fingerprint exactly right: nothing changed, nothing to fetch.
// Without this property every backfill run would trigger a full sync
// even when it found nothing to do.
$pdo->prepare('UPDATE map_points SET municipality = ? WHERE id = ?')->execute([$existing, $id]);
check($stateOf($id) === $quiet,
    'an UPDATE with unchanged values leaves it standing');

// ---------------------------------------------------------------------------
echo "\nTwo points in the same second are distinguishable\n";

// The case from backfilling in chunks: dozens of rows per second.
// Without microseconds they would get the same stand, and a fingerprint
// would overlook half of them.
$many = [];
for ($i = 0; $i < 20; $i++) {
    $pdo->prepare('UPDATE map_points SET note = ? WHERE id = ?')->execute(["Lauf $i", $id]);
    $many[] = $stateOf($id);
}
check(count(array_unique($many)) === count($many),
    '20 changes in the same instant yield 20 different stands');

// ---------------------------------------------------------------------------
echo "\nThe fingerprint of a bucket detects a silent derivation\n";

/*
 * NOT MD5(GROUP_CONCAT(...)) — THE TRAP THAT STOOD HERE FIRST.
 *
 * GROUP_CONCAT cuts off at group_concat_max_len, 1024 bytes by default.
 * Measured, the hash thereby covered 35 of 156 points; at 20,000 it would
 * be 35 of 20,000, and the fingerprint would be blind to 99.8 % of all
 * changes. No warning, no error — it would look just like a proper one.
 *
 * Instead three figures that together leave no hole and get by without a
 * buffer, no matter how many rows there are:
 *
 *   COUNT      catches every final deletion
 *   MAX(touched_at) catches every change, because stand always moves forward
 *   SUM(CRC32) catches the case that both happen to stay the same — and
 *              is independent of the order
 */
$fingerprint = static fn(): string => (string) $pdo->query(
    "SELECT CONCAT(COUNT(*), '|', COALESCE(MAX(touched_at), ''), '|',
                   COALESCE(SUM(CRC32(CONCAT_WS(':', id, touched_at))), 0))
       FROM map_points WHERE user_id = $userId AND deleted_at IS NULL"
)->fetchColumn();

$fpBefore = $fingerprint();
$pdo->prepare('UPDATE map_points SET mtb = ? WHERE id = ?')->execute(['9999', $id]);
check($fingerprint() !== $fpBefore,
    'a backfilled map sheet number changes the fingerprint');

// And the cross-check: what changes nothing does not change the
// fingerprint either. Without it the test above would be content with a
// fingerprint that changes on every call — and that one would be
// useless, because then everything would always be reloaded.
$resting = $fingerprint();
check($fingerprint() === $resting, 'asked twice, the same answer twice');

// ---------------------------------------------------------------------------
foreach ($created as $pid) {
    $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ?')->execute([$pid]);
    $pdo->prepare('DELETE FROM map_point_species WHERE point_id = ?')->execute([$pid]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ? AND user_id = ?')->execute([$pid, $userId]);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
