<?php
/**
 * Tests the field times: per field the younger change wins, and what
 * loses is kept aside.
 *
 *   php tests/field_times.php
 *
 * THE STORY PLAYED THROUGH HERE is the trigger of the whole thing: the
 * phone changes the title in the forest (2 pm, no network), the desktop
 * meanwhile changes the note (4 pm). The phone hands in at night. Before,
 * the note lost — the phone sent the whole record. Now BOTH changes must
 * remain standing, and where the same field really was changed twice, the
 * losing version must lie in map_point_overwritten.
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
function check(bool $b, string $what, string $seen = ''): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what" . ($seen !== '' ? "  ->  $seen" : '') . "\n"; }
}

echo "\nField times\n\n";

if (!field_times_ready($pdo) || !overwritten_ready($pdo)) {
    echo "  (skipped — migration 0039 is not applied)\n\n";
    exit(0);
}

$userId = (int) $pdo->query('SELECT id FROM map_users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId <= 0) {
    echo "  (skipped — there is no user)\n\n";
    exit(0);
}

// ---------------------------------------------------------------------------
echo "Format of the times\n";

check(field_times_valid('2026-08-01T14:32:05.123Z'), 'the fixed format holds');
foreach (['2026-08-11 14:32:05', '2026-08-01T14:32:05Z', '2026-08-01T14:32:05.123+02:00', 42, null, ''] as $bad) {
    check(!field_times_valid($bad), 'rejected: ' . var_export($bad, true));
}
check(field_times_valid(field_times_now()), 'the server time carries the same format');

// ---------------------------------------------------------------------------
// One point on which everything is played through. point_create calls out
// (height, municipality) — hence inserted directly, as in tests/rev.php.
// ---------------------------------------------------------------------------

$pdo->prepare(
    'INSERT INTO map_points (user_id, lat, lon, title, note, found_at, created_at, updated_at)
     VALUES (?, 49.60, 9.08, "PRUEF Feldzeiten", "Alte Notiz", "2026-03-01 10:00:00", NOW(), NOW())'
)->execute([$userId]);
$pointId = (int) $pdo->lastInsertId();

$read = static function () use ($pdo, $pointId): array {
    $stmt = $pdo->prepare('SELECT title, note, lat, lon, found_at, extra, field_times FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $row = $stmt->fetch();
    $row['field_times'] = json_decode((string) ($row['field_times'] ?? ''), true) ?: [];
    $row['extra']     = json_decode((string) ($row['extra'] ?? ''), true) ?: [];
    return $row;
};

$setAside = static function () use ($pdo, $pointId): array {
    $stmt = $pdo->prepare(
        'SELECT field, value, value_time FROM map_point_overwritten WHERE point_id = ? AND seen_at IS NULL ORDER BY id'
    );
    $stmt->execute([$pointId]);
    $out = [];
    foreach ($stmt as $row) {
        $out[] = ['field' => $row['field'], 'value' => json_decode((string) $row['value'], true), 'time' => $row['value_time']];
    }
    return $out;
};

// ---------------------------------------------------------------------------
echo "\nThe story: the phone changes the title, the desktop the note\n";

// Desktop, 16:00: the note. Clean forward write (seen == old == absent).
point_update($pdo, $userId, $pointId, [
    'note'      => 'Notiz vom Desktop',
    'field_times' => ['note' => '2026-08-01T16:00:00.000Z'],
    'field_seen'=> ['note' => null],
]);

// Phone, handed in at night, change from 14:00: the title. Knew the morning state.
point_update($pdo, $userId, $pointId, [
    'title'      => 'Titel vom Handy',
    'field_times' => ['title' => '2026-08-01T14:00:00.000Z'],
    'field_seen'=> ['title' => null],
]);

$row = $read();
check($row['title'] === 'Titel vom Handy', 'the title from the phone stands', $row['title']);
check($row['note'] === 'Notiz vom Desktop', 'AND the note from the desktop stands', (string) $row['note']);
check($setAside() === [], 'nothing was set aside — there was no conflict');
check(($row['field_times']['title'] ?? null) === '2026-08-01T14:00:00.000Z', "the title time is the phone's");

// ---------------------------------------------------------------------------
echo "\nThe same field twice: the older version loses and is set aside\n";

// The phone hands in an OLDER title change (13:00, no longer knew the
// morning state — seen does not match the stored 14:00).
point_update($pdo, $userId, $pointId, [
    'title'      => 'Älterer Titel aus dem Wald',
    'field_times' => ['title' => '2026-08-01T13:00:00.000Z'],
    'field_seen'=> ['title' => null],
]);

$row = $read();
$a = $setAside();
check($row['title'] === 'Titel vom Handy', 'the younger title stays', $row['title']);
check(count($a) === 1, 'the losing version lies in the holding area', (string) count($a));
check(($a[0]['field'] ?? '') === 'title' && ($a[0]['value'] ?? '') === 'Älterer Titel aus dem Wald',
    'with field and value', json_encode($a[0] ?? null, JSON_UNESCAPED_UNICODE));
check(($a[0]['time'] ?? '') === '2026-08-01T13:00:00.000Z', 'and its time');

// ---------------------------------------------------------------------------
echo "\nThe younger one wins over a never-seen state — the old value is set aside\n";

point_update($pdo, $userId, $pointId, [
    'title'      => 'Noch jüngerer Titel',
    'field_times' => ['title' => '2026-08-01T18:00:00.000Z'],
    'field_seen'=> ['title' => '2026-08-01T09:00:00.000Z'],   // did not know 14:00
]);

$row = $read();
$a = $setAside();
check($row['title'] === 'Noch jüngerer Titel', 'the youngest title stands', $row['title']);
check(count($a) === 2, 'now two versions lie in the holding area', (string) count($a));
check(($a[1]['value'] ?? '') === 'Titel vom Handy' && ($a[1]['time'] ?? '') === '2026-08-01T14:00:00.000Z',
    'the second is the displaced state from 14:00', json_encode($a[1] ?? null, JSON_UNESCAPED_UNICODE));

// ---------------------------------------------------------------------------
echo "\nClean forward writing does not ask and sets nothing aside\n";

point_update($pdo, $userId, $pointId, [
    'title'      => 'Weitergeschrieben',
    'field_times' => ['title' => '2026-08-01T18:05:00.000Z'],
    'field_seen'=> ['title' => '2026-08-01T18:00:00.000Z'],   // exactly the state
]);
check($read()['title'] === 'Weitergeschrieben', 'applied');
check(count($setAside()) === 2, 'without a new set-aside entry', (string) count($setAside()));

// ---------------------------------------------------------------------------
echo "\nSame value is no change\n";

$before = $read()['field_times'];
point_update($pdo, $userId, $pointId, [
    'title'      => 'Weitergeschrieben',
    'field_times' => ['title' => '2026-08-01T19:00:00.000Z'],
    'field_seen'=> ['title' => null],                          // did not even match
]);
check($read()['field_times'] === $before, 'the field time does not move');
check(count($setAside()) === 2, 'and nothing is set aside');

// ---------------------------------------------------------------------------
echo "\nAn old browser without field times\n";

// It sends the whole form — including fields it never touched.
point_update($pdo, $userId, $pointId, [
    'title' => 'Vom alten Browser',
    'note' => 'Notiz vom Desktop',       // sent along unchanged
]);

$row = $read();
check($row['title'] === 'Vom alten Browser', 'its value holds as it always did', $row['title']);
check(field_times_valid($row['field_times']['title'] ?? null)
    && strcmp($row['field_times']['title'], '2026-08-01T19:00:00.000Z') > 0,
    'the title time advances to the server time', (string) ($row['field_times']['title'] ?? 'missing'));
check(($row['field_times']['note'] ?? null) === '2026-08-01T16:00:00.000Z',
    'the note time stays put — same value is no change',
    (string) ($row['field_times']['note'] ?? 'missing'));

// And a waiting phone change from today noon now loses to it:
point_update($pdo, $userId, $pointId, [
    'title'      => 'Wald, Mittag',
    'field_times' => ['title' => '2026-08-01T12:00:00.000Z'],
    'field_seen'=> ['title' => null],
]);
check($read()['title'] === 'Vom alten Browser',
    'an older change handed in late loses against it too');
check(count($setAside()) === 3, 'and is set aside', (string) count($setAside()));

// ---------------------------------------------------------------------------
echo "\nExtra fields individually: substrate from the phone, accompanying vegetation from the desktop\n";

point_update($pdo, $userId, $pointId, [
    'extra'     => ['substrat' => 'Fagus'],
    'field_times' => ['extra.substrat' => '2026-08-01T14:10:00.000Z'],
    'field_seen'=> ['extra.substrat' => null],
]);
point_update($pdo, $userId, $pointId, [
    'extra'     => ['begleitvegetation' => 'Luzula'],
    'field_times' => ['extra.begleitvegetation' => '2026-08-01T16:10:00.000Z'],
    'field_seen'=> ['extra.begleitvegetation' => null],
]);

$row = $read();
check(($row['extra']['substrat'] ?? '') === 'Fagus', 'the substrate stands', json_encode($row['extra']));
check(($row['extra']['begleitvegetation'] ?? '') === 'Luzula', 'AND the accompanying vegetation stands');
check(count($setAside()) === 3, 'without a new set-aside entry — different fields are no conflict');

// The same subfield, older → loses; emptying = deleting.
point_update($pdo, $userId, $pointId, [
    'extra'     => ['substrat' => 'Picea'],
    'field_times' => ['extra.substrat' => '2026-08-01T13:30:00.000Z'],
    'field_seen'=> ['extra.substrat' => null],
]);
$row = $read();
$a = $setAside();
check(($row['extra']['substrat'] ?? '') === 'Fagus', 'an older substrate loses');
check(($a[3]['field'] ?? '') === 'extra.substrat' && ($a[3]['value'] ?? '') === 'Picea',
    'and lies in the holding area', json_encode($a[3] ?? null));

point_update($pdo, $userId, $pointId, [
    'extra'     => ['substrat' => ''],
    'field_times' => ['extra.substrat' => '2026-08-01T17:00:00.000Z'],
    'field_seen'=> ['extra.substrat' => '2026-08-01T14:10:00.000Z'],
]);
$row = $read();
check(!isset($row['extra']['substrat']), 'an emptied subfield is deleted', json_encode($row['extra']));
check(($row['extra']['begleitvegetation'] ?? '') === 'Luzula', 'the others remain — the block is merged, not replaced');

// ---------------------------------------------------------------------------
echo "\nA losing location triggers no derivations\n";

// The location time is planted DIRECTLY instead of via a winning move: a
// winning location fetches terrain height and municipality from outside
// after saving — real requests have no business in a test. The winner path
// itself is tested above on the title; this is about what is SPECIAL about
// the location: that a losing one triggers no derivations.
$pdo->prepare('UPDATE map_points SET elevation_source = "PRUEF",
                   field_times = JSON_SET(COALESCE(field_times, "{}"), "$.position", "2026-08-01T15:00:00.000Z")
                 WHERE id = ?')->execute([$pointId]);

point_update($pdo, $userId, $pointId, [
    'lat' => 51.9, 'lon' => 11.9, 'accuracy_m' => 3,
    'field_times' => ['position' => '2026-08-01T08:00:00.000Z'],   // older than 15:00
    'field_seen'=> ['position' => null],
]);
$row = $read();
$source = $pdo->query("SELECT elevation_source FROM map_points WHERE id = $pointId")->fetchColumn();
check(abs((float) $row['lat'] - 49.60) < 1e-6, 'an older location loses', (string) $row['lat']);
check($source === 'PRUEF', 'and the height is NOT fetched anew — elevation_source still stands', (string) $source);
$a = $setAside();
$last = end($a);
check(($last['field'] ?? '') === 'position' && abs(($last['value']['lat'] ?? 0) - 51.9) < 1e-6,
    'the losing location lies in the holding area with both coordinates', json_encode($last));

// ---------------------------------------------------------------------------
echo "\nThe holding area: view and confirm\n";

$pending = overwritten_pending($pdo, $userId);
$mine = array_values(array_filter($pending, static fn($e) => $e['point_id'] === $pointId));
check(count($mine) === 5, 'five open entries for this point', (string) count($mine));
check($mine[0]['id'] > $mine[1]['id'], 'youngest first');

overwritten_seen($pdo, $userId, array_column($mine, 'id'));
$pending = array_filter(overwritten_pending($pdo, $userId), static fn($e) => $e['point_id'] === $pointId);
check($pending === [], 'after confirming nothing is open anymore');
check(overwritten_seen($pdo, $userId, [999999999]) === 0, 'foreign or invented ids move nothing');

// ---------------------------------------------------------------------------
echo "\nThe find number by hand — a field like the title (26.09.2026)\n";

$number = static function () use ($pdo, $pointId): ?string {
    $stmt = $pdo->prepare('SELECT find_number FROM map_points WHERE id = ?');
    $stmt->execute([$pointId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
};
point_update($pdo, $userId, $pointId, [
    'find_number' => '  wk-00123 ',
    'field_times' => ['find_number' => '2026-08-02T10:00:00.000Z'],
    'field_seen'  => ['find_number' => null],
]);
check($number() === 'wk-00123', 'typed in the window, stored without the blanks', (string) $number());
// The phone hands in an OLDER number for the same find.
point_update($pdo, $userId, $pointId, [
    'find_number' => 'wk-00999',
    'field_times' => ['find_number' => '2026-08-02T09:00:00.000Z'],
    'field_seen'  => ['find_number' => null],
]);
$lost = array_values(array_filter($setAside(), static fn(array $e): bool => $e['field'] === 'find_number'));
check($number() === 'wk-00123' && count($lost) === 1 && $lost[0]['value'] === 'wk-00999',
    'the older number loses and waits in the holding area, like a title');
point_update($pdo, $userId, $pointId, [
    'find_number' => '',
    'field_times' => ['find_number' => '2026-08-02T11:00:00.000Z'],
    'field_seen'  => ['find_number' => '2026-08-02T10:00:00.000Z'],
]);
check($number() === null, 'emptied, the find has no number of its own — it wears its „Y-" again');
$refused = false;
try {
    point_update($pdo, $userId, $pointId, ['find_number' => 'y-5']);
} catch (InvalidArgumentException $e) {
    $refused = true;
}
check($refused && $number() === null, 'YASAIMAPP\'s own mark is refused, whatever its case');

// ---------------------------------------------------------------------------
foreach ([$pointId] as $pid) {
    $pdo->prepare('DELETE FROM map_point_overwritten WHERE point_id = ?')->execute([$pid]);
    $pdo->prepare('DELETE FROM map_point_species WHERE point_id = ?')->execute([$pid]);
    $pdo->prepare('DELETE FROM map_point_lists WHERE point_id = ?')->execute([$pid]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ? AND user_id = ?')->execute([$pid, $userId]);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
