<?php
/**
 * Tests the data backup into the user's own storage.
 *
 *   php tests/backup.php
 *
 * WHAT IS REALLY TESTED HERE is the one decision the whole thing hangs on:
 * does this month have to go up again? Everything around it — the folder,
 * the upload, the provider — is one call each and fails loudly. The
 * decision fails QUIETLY, in both directions, and both are expensive:
 *
 *   too eager  →  every month is written every night, and a stock of ten
 *                 years becomes a hundred and twenty uploads a day at
 *                 somebody's provider.
 *   too lazy   →  a changed month is considered done, and the backup goes
 *                 on looking current while it is not. Nobody notices until
 *                 the day it is needed.
 *
 * The second one is what makes this file exist. A backup that lies about
 * being current is worse than none.
 *
 * NOTHING HERE TALKS TO A PROVIDER. backup_due() was split out of the sweep
 * for exactly that reason: the rule is pure, so it can be held against
 * every awkward case without an account, without a network and without
 * putting a single byte into anybody's cloud.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/backup.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** One row as backup_state() hands it over. */
function row(string $key, string $finger): array
{
    return [
        'bucket_key'  => $key,
        'fingerprint' => $finger,
        'filename'    => backup_filename($key),
        'total'       => 3,
        'uploaded_at' => '2026-08-26 03:00:00',
    ];
}

echo "\nThe file name\n";
check(backup_filename('2026-08') === 'Funde 2026-08.csv',
    'a month becomes „Funde 2026-08.csv"');
check(backup_filename('1998-01') < backup_filename('2026-08'),
    'the names sort like the months — that is what the date leading is for');

echo "\nWhen a month has to go up\n";

$finger = ['2026-08' => 'aaa', '2026-07' => 'bbb'];

check(backup_due($finger, [], []) === ['2026-08', '2026-07'],
    'nothing known, nothing there: everything is due');

$known = ['2026-08' => row('2026-08', 'aaa'), '2026-07' => row('2026-07', 'bbb')];
$there = ['Funde 2026-08.csv' => ['fileid' => 1], 'Funde 2026-07.csv' => ['fileid' => 2]];

check(backup_due($finger, $known, $there) === [],
    'print matches and file is there: nothing to do');

check(backup_due(['2026-08' => 'ZZZ', '2026-07' => 'bbb'], $known, $there) === ['2026-08'],
    'a changed print takes only ITS month up again');

/* THE CASE THE FOLDER LISTING EXISTS FOR. Whoever tidies up in their own
   storage must get everything back — with the fingerprint alone our side
   would go on saying "already done", for ever, and nobody would see it. */
check(backup_due($finger, $known, []) === ['2026-08', '2026-07'],
    'the folder emptied over there: everything is written afresh');

check(backup_due($finger, $known, ['Funde 2026-07.csv' => ['fileid' => 2]]) === ['2026-08'],
    'one file deleted over there: exactly that month comes again');

/* The file is looked for under the name it was WRITTEN as. A renamed
   scheme therefore writes everything once more instead of holding a month
   against a file that was never called that. */
$oldName = row('2026-08', 'aaa');
$oldName['filename'] = 'Fundpunkte 2026-08.csv';
check(backup_due(['2026-08' => 'aaa'], ['2026-08' => $oldName],
        ['Funde 2026-08.csv' => ['fileid' => 1]]) === ['2026-08'],
    'a file under a different name does not count as this month');

check(backup_due(['2026-08' => 'aaa'], ['2026-08' => $oldName],
        ['Fundpunkte 2026-08.csv' => ['fileid' => 1]]) === [],
    '… and under its own name it does');

/* A month that has fallen out of the stock — every find of it deleted — is
   not "due". Nothing is deleted up there; the file simply stays. */
check(backup_due([], $known, $there) === [],
    'a month without finds is not written and not touched');

echo "\nThe order survives\n";
check(backup_due(['2026-08' => 'x', '2024-09' => 'y', '2019-03' => 'z'], [], []) === ['2026-08', '2024-09', '2019-03'],
    'newest first, the way bucket_status hands them over');

echo "\nThe note in the folder\n";
$readme = backup_readme();
check(str_starts_with($readme, 'Datensicherung — YASAIMAPP'),
    'says what it is — under the title it always had, so no note up there is rewritten for it');
check(str_contains($readme, 'Import'), 'says how the files get back in');
check(str_contains($readme, 'Papierkorb'),
    'says plainly that it is no protection against deleting by mistake');
/* THE TRAP THAT COST AN AFTERNOON (27.08.2026): the provider's
   own preview reads "5815/431" as a division and shows the result as a
   reduced fraction, "27/2". The file is right, the display is not — and
   without this sentence the next person to see it will report a broken
   backup, exactly as it looked here. */
check(str_contains($readme, '27/2'),
    'and warns that a table viewer may turn the sheet reference into a fraction');
check(!str_contains($readme, 'backup') && !str_contains($readme, 'bucket'),
    'and speaks German — a person reads this, not our code');

/* AND NAMES NO PROVIDER (27.08.2026: „Die Liesmich nicht zu
   speziell. Wir wollen ja noch andere Cloud Services anbinden" — a friend
   of his is on Google Drive). This file lies in somebody's storage for
   years, longer than the assumption that pCloud is the only one. The check
   walks the provider list itself, so a provider added tomorrow is guarded
   without anybody remembering this test. */
foreach (CLOUD_PROVIDERS as $code => $provider) {
    check(!str_contains(mb_strtolower($readme), mb_strtolower($provider['name']))
        && !str_contains(mb_strtolower($readme), $code),
        "says nothing about „{$provider['name']}\" in particular");
}

echo "\nOnly one's own finds\n";
/* THE PROMISE OF THE WHOLE FEATURE: what comes through containers and
   excursions belongs to whoever made it and is backed up in THEIR storage.
   Measured against the real stock, not against a fixture — a hand-made one
   would have nothing foreign in it and could not fail. */
$stmt = $pdo->query(
    'SELECT user_id, COUNT(*) AS n FROM map_points
      WHERE deleted_at IS NULL GROUP BY user_id ORDER BY n DESC LIMIT 1'
);
$biggest = $stmt->fetch();
if ($biggest === false) {
    echo "  (no points in the database — nothing to measure against)\n";
} else {
    $userId = (int) $biggest['user_id'];
    $status = bucket_status($pdo, $userId);
    $key    = (string) array_key_first($status['finger']);

    $names = [];
    foreach (lists_load($pdo, $userId) as $l) {
        $names[(int) $l['id']] = (string) $l['name'];
    }
    $points = backup_points($pdo, $userId, $key, $names);

    check($points !== [], "the month $key of user $userId carries points at all");
    check(count($points) === (int) ($status['counts'][$key] ?? -1),
        'exactly as many as the bucket counts — no more, no fewer');

    $ids   = array_map(static fn(array $p): int => (int) $p['id'], $points);
    $space = implode(',', array_fill(0, count($ids), '?'));
    $check = $pdo->prepare("SELECT COUNT(*) FROM map_points WHERE id IN ($space) AND user_id <> ?");
    $check->execute([...$ids, $userId]);
    check((int) $check->fetchColumn() === 0, 'and not one of them belongs to somebody else');

    // THE ORDER HAS TO BE STABLE. If it were not, an edit to a single find
    // would shuffle every line, and the same content would look like a
    // different file every night.
    $again = backup_points($pdo, $userId, $key, $names);
    check(array_map(static fn(array $p): int => (int) $p['id'], $again) === $ids,
        'twice fetched, twice in the same order');

    // The same fallback the sort uses and bucket_key_of prescribes: an
    // empty string counts as no find time, not as one before the year 0.
    $times = array_map(static function (array $p): string {
        $time = $p['found_at'] ?? null;
        return (string) ($time === null || $time === '' ? ($p['created_at'] ?? '') : $time);
    }, $points);
    $sorted = $times;
    sort($sorted, SORT_STRING);
    check($times === $sorted, 'and sorted by the find time, ascending');

    $csv = export_csv($points);
    check(str_starts_with($csv, "\xEF\xBB\xBF"),
        'the file carries the byte-order mark a German Excel needs');
    check(substr_count($csv, "\n") >= count($points),
        'and one line per point beside the header');
}

echo "\n", $failed === 0 ? "All good.\n\n" : "$failed failed.\n\n";
exit($failed === 0 ? 0 : 1);
