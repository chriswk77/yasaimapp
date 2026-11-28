<?php
/**
 * Tests the find numbers (migration 0070).
 *
 *   php tests/find_number.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   THE PATTERN ARITHMETIC. Dates from the FIND time, the longest N-run
 *   as the padded counter, growth past the digits without cutting — a
 *   cut number would collide with an earlier one, silently.
 *
 *   THE GATES OF MINTING. Only with a pattern, only into a species
 *   branch (a waypoint must not use up a collection number), never for
 *   an import — and a number the import DELIVERS is stored verbatim
 *   without touching the counter.
 *
 *   THE COUNTER COUNTS ON, exactly once per minted number, across
 *   whatever else happens in between.
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
require_once __DIR__ . '/../includes/find_number.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

function rejected(callable $do, ?string &$reason = null): bool
{
    try {
        $do();
        return false;
    } catch (InvalidArgumentException $e) {
        $reason = $e->getMessage();
        return true;
    }
}

if (!find_number_ready($pdo)) {
    echo "Migration 0070 is missing — nothing to check.\n";
    exit(1);
}

echo "The pattern arithmetic:\n";
check(find_number_render('CW-JJJJ-NNNN', '2026-08-14 09:20:00', 7) === 'CW-2026-0007',
    'prefix, find year and padded counter');
check(find_number_render('JJ/MM/TT-NN', '2026-08-04 09:20:00', 3) === '26/08/04-03',
    'short year, month and day come from the find time');
check(find_number_render('JJJJNNNN', '2026-08-14 09:20:00', 12) === '20260012',
    'year glued to counter — neither eats the other');
check(find_number_render('NN', '2026-08-14 09:20:00', 100) === '100',
    'the counter grows past its digits instead of being cut');
check(find_number_render('N-NNN-N', '2026-08-14 09:20:00', 5) === 'N-005-N',
    'only the longest N-run is the counter, the others stay literal');
check(find_number_render('Nr. NNN', null, 1) === 'Nr. 001',
    'without a find time the pattern still renders (today fills the dates)');

// The pattern turned round (find_number_matcher): what the renderer
// writes, the matcher reads back — the counter group and nothing else.
[$rx] = find_number_matcher('JJJJNNNN', [], new DateTime('2026-08-14 09:20:00'));
check(preg_match($rx, '20260012', $m) === 1 && (int) $m[1] === 12,
    'year glued to counter reads back — the year is not taken for the number');
[$rx, $prefix] = find_number_matcher('CW-JJJJ-NNN', ['JJJJ', 'JJ'], new DateTime('2026-08-14 09:20:00'));
check($prefix === 'CW-2026-' && preg_match($rx, 'cw-2026-058', $m) === 1 && (int) $m[1] === 58
    && preg_match($rx, 'CW-2025-058') === 0,
    'a section fixes its year: another year does not match, the case does not matter');

echo "The settings guard:\n";
$pdo->prepare(
    "INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
     VALUES ('pruef-fundnr@pruefung.local', '', 'PRUEF', 'Fundnummer', 'user', NOW())"
)->execute();
$userId = (int) $pdo->lastInsertId();

check(rejected(fn() => find_number_settings_save($pdo, $userId, 'ohne Zaehler', '1')),
    'a pattern without an N-run is refused — a number that does not number is none');
check(rejected(fn() => find_number_settings_save($pdo, $userId, 'NNNN', '0')),
    'the next number must be at least 1');
find_number_settings_save($pdo, $userId, 'PRUEF-JJJJ-NNN', '41');
check(find_number_pattern($pdo, $userId) === 'PRUEF-JJJJ-NNN' && find_number_next($pdo, $userId) === 41,
    'pattern and next number are stored');

// A written-out number counts on from itself — the door for existing
// collections: the digit run becomes the counter, places and start
// included; the next-number field is overridden by what was typed.
find_number_settings_save($pdo, $userId, 'wk-JJJJ-MM-TT-00000543', '1');
check(find_number_pattern($pdo, $userId) === 'wk-JJJJ-MM-TT-NNNNNNNN'
    && find_number_next($pdo, $userId) === 544,
    'a typed number becomes N-places — it is the LAST given one, the next find counts past it');
check(rejected(fn() => find_number_settings_save($pdo, $userId, 'gar kein Zaehler hier', '1')),
    'without N and without digits there is still no counter');
find_number_settings_save($pdo, $userId, 'WK JJJJ NNNN', '5');
check(find_number_render('WK JJJJ NNNN', '2026-08-23 10:00:00', 5) === 'WK 2026 0005',
    'spaces are ordinary pattern text — inside they stay, only the edges are trimmed');
find_number_settings_save($pdo, $userId, 'Revier7-NNNN', '9');
check(find_number_pattern($pdo, $userId) === 'Revier7-NNNN' && find_number_next($pdo, $userId) === 9,
    'with N present, digits stay literal text — the 7 belongs to the name');
find_number_settings_save($pdo, $userId, 'PRUEF-JJJJ-NNN', '41');

// THE FORM HAS NO NEXT NUMBER ANY MORE (25.09.2026): a pattern with N's
// leaves the counter where it is — only a written-out number moves it.
find_number_settings_save($pdo, $userId, 'ANDERS-JJJJ-NNN');
check(find_number_pattern($pdo, $userId) === 'ANDERS-JJJJ-NNN' && find_number_next($pdo, $userId) === 41,
    'a new pattern without a number keeps the counter');
find_number_settings_save($pdo, $userId, 'PRUEF-JJJJ-NNN');

// A number typed by hand, in the series' own spelling (26.09.2026) — the
// cases tests/findnumber.mjs holds the browser's twin to.
check(find_number_normalize('wk-NNNNN', 'wk-01') === 'wk-00001' && find_number_normalize('wk-NNNNN', 'WK-00001') === 'wk-00001',
    'a typed number takes the pattern\'s text and padding');
check(find_number_normalize('wk-NNNNN', 'wk-1234567') === 'wk-1234567', 'padded, never cut');
check(find_number_normalize('wk-NNNNN', 'HM-0543') === 'HM-0543', 'another shape is a series of its own and stays as typed');
check(find_number_normalize('CW-JJJJ-NNN', 'cw-2024-12') === 'CW-2024-012'
    && find_number_normalize('JJMMTT-NN', '260925-3') === '260925-03', 'the date digits as typed, the counter padded');
check(find_number_normalize('Fund (NNN).JJ', 'fund (7).26') === 'Fund (007).26', 'brackets and dots are text, not rules');

/* THE MARK OF A FIND WITHOUT A PATTERN (25.09.2026): „Y-" and the id, and
   no pattern may begin with it — so a minted „100" and a find's „Y-100"
   can never read the same. */
echo "The mark without a pattern:\n";
check(find_number_fallback(100) === 'Y-100', 'a find without a number reads „Y-100"');
check(find_number_reserved('Y-NNN') && find_number_reserved('  y-NNN') && !find_number_reserved('CW-Y-NNN')
    && !find_number_reserved('YNNN'), 'the mark counts at the start, whatever the case — and only there');
check(find_number_reserved('Y:100') && find_number_reserved(' y:7'),
    'the old colon („Y:100", until 04.10.2026) still counts as the mark — old exports name their find');
$refused = false;
try {
    find_number_settings_save($pdo, $userId, 'Y-NNN', '1');
} catch (InvalidArgumentException $e) {
    $refused = true;
}
check($refused && find_number_pattern($pdo, $userId) === 'PRUEF-JJJJ-NNN',
    'a pattern beginning with „Y-" is refused, and the old one stays');

echo "The gates of minting:\n";
try {
    $speciesList = list_create($pdo, $userId, ['name' => 'PRUEF Arten', 'is_species' => true]);
    // A second species list (once a sublist that inherited the flag).
    $branchLeaf  = list_create($pdo, $userId, ['name' => 'PRUEF Ast', 'is_species' => true]);
    $plainList   = list_create($pdo, $userId, ['name' => 'PRUEF Wege']);

    $find = point_create($pdo, $userId, ['lat' => 50.1, 'lon' => 8.2,
        'title' => 'PRUEF Fund', 'lists' => [$branchLeaf], 'found_at' => '2026-08-14 09:20:00']);
    $number = $pdo->query("SELECT find_number FROM map_points WHERE id = $find")->fetchColumn();
    check($number === 'PRUEF-2026-041',
        'a find in a species list draws the next number');

    $waypoint = point_create($pdo, $userId, ['lat' => 50.1, 'lon' => 8.2,
        'title' => 'PRUEF Rastplatz', 'lists' => [$plainList], 'found_at' => '2026-08-14 09:20:00']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $waypoint")->fetchColumn() === null,
        'a waypoint outside every species list draws none');

    $second = point_create($pdo, $userId, ['lat' => 50.2, 'lon' => 8.3,
        'title' => 'PRUEF Fund 2', 'lists' => [$speciesList], 'found_at' => '2026-08-15 10:00:00']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $second")->fetchColumn() === 'PRUEF-2026-042',
        'the counter moved exactly once past the waypoint');

    // The import gate: import_id set means no minting — and a delivered
    // number is stored verbatim, counter untouched.
    require_once __DIR__ . '/../includes/imports.php';
    $import = import_create($pdo, $userId, 'csv', desiredName: 'PRUEF Einfuhr');
    $imported = point_create($pdo, $userId, ['lat' => 50.3, 'lon' => 8.4,
        'title' => 'PRUEF Import', 'lists' => [$speciesList],
        'found_at' => '2020-05-01 08:00:00', 'import_id' => $import['id']]);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $imported")->fetchColumn() === null,
        'an import mints nothing — the past does not stamp the counter through');

    $carried = point_create($pdo, $userId, ['lat' => 50.4, 'lon' => 8.5,
        'title' => 'PRUEF Import 2', 'lists' => [$speciesList],
        'found_at' => '2020-05-01 08:00:00', 'import_id' => $import['id'],
        'find_number' => 'ALT-0433']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $carried")->fetchColumn() === 'ALT-0433',
        'a number the source brings along is stored verbatim');
    check(find_number_next($pdo, $userId) === 43,
        'and the counter has not moved for any of it');

    // YASAIMAPP's own mark coming back in an import names the OLD find —
    // it is dropped, and the new one wears its own (find_number_fallback).
    $marked = point_create($pdo, $userId, ['lat' => 50.4, 'lon' => 8.5,
        'title' => 'PRUEF Import 3', 'lists' => [$speciesList],
        'found_at' => '2020-05-01 08:00:00', 'import_id' => $import['id'],
        'find_number' => 'Y-100']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $marked")->fetchColumn() === null,
        'an imported „Y-100" is dropped — the mark belongs to the find it came from');

    /* THE NEXT NUMBER GOES ON ABOVE THE HIGHEST ONE GIVEN (25.09.2026):
       the finds of a second phone, mapped with another app and imported
       with their numbers — the counter goes on past them by itself, other
       case and other year included. No rule that they stay below it: a
       fresh series has no gap to put them in. */
    point_create($pdo, $userId, ['lat' => 50.41, 'lon' => 8.51, 'title' => 'PRUEF Zweithandy',
        'lists' => [$speciesList], 'found_at' => '2020-05-02 08:00:00', 'import_id' => $import['id'],
        'find_number' => 'pruef-2019-500']);
    check(find_number_upcoming($pdo, $userId, 'PRUEF-JJJJ-NNN') === 501,
        'an imported number above the counter moves the next one past it — the preview says so');
    $after = point_create($pdo, $userId, ['lat' => 50.42, 'lon' => 8.52, 'title' => 'PRUEF Danach',
        'lists' => [$speciesList], 'found_at' => '2026-08-17 09:00:00']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $after")->fetchColumn() === 'PRUEF-2026-501',
        'and the next find draws it');
    find_number_settings_save($pdo, $userId, 'PRUEF-JJJJ-010');
    $wound = point_create($pdo, $userId, ['lat' => 50.43, 'lon' => 8.53, 'title' => 'PRUEF Zurückgedreht',
        'lists' => [$speciesList], 'found_at' => '2026-08-18 09:00:00']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $wound")->fetchColumn() === 'PRUEF-2026-502',
        'a counter set below the highest number goes on above it — nothing is given twice');

    // Without a pattern nothing is minted — the id speaks.
    find_number_settings_save($pdo, $userId, '', '43');
    $plain = point_create($pdo, $userId, ['lat' => 50.5, 'lon' => 8.6,
        'title' => 'PRUEF Fund 3', 'lists' => [$speciesList], 'found_at' => '2026-08-16 09:00:00']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $plain")->fetchColumn() === null,
        'without a pattern nothing is stored — the database id is the number');

    if (column_exists($pdo, 'map_users', 'find_number_restart')) {
        echo "The restart per date section (migration 0072):\n";

        // The section follows the finest COMPLETE chain from the year down
        // (the question of 23.08.2026 was the test case: a TT
        // alone is not unique).
        check(find_number_period('A-JJJJ-MM-TT-NNN', '2026-08-23 10:00:00')
                === ['unit' => 'Tag', 'key' => '2026-08-23'],
            'year, month and day: the section is the day');
        check(find_number_period('A-JJJJ-MM-NNN', '2026-08-23 10:00:00')
                === ['unit' => 'Monat', 'key' => '2026-08'],
            'month without day: the section is the month');
        check(find_number_period('A-JJ-NNN', '2026-08-23 10:00:00')
                === ['unit' => 'Jahr', 'key' => '2026'],
            'the two-digit year counts as a year too');
        check(find_number_period('A-NNN', '2026-08-23 10:00:00') === null,
            'without a date part there is no section');
        check(find_number_period('A-TT-NNN', '2026-08-23 10:00:00') === null,
            'a day alone identifies no day — the 05 of September and of October would collide');
        check(find_number_period('A-MM-TT-NNN', '2026-08-23 10:00:00') === null,
            'month and day without a year collide across years — no section');
        check(find_number_period('A-JJJJ-TT-NNN', '2026-08-23 10:00:00')
                === ['unit' => 'Jahr', 'key' => '2026'],
            'a day without a month is decoration — the chain falls back to the year');

        // Year pattern, restart on: within the year it counts on, the
        // first find of the NEXT year begins at 1 again. Dates relative
        // to today's year — the baseline the save stamps is the current
        // section, and a frozen fixture year would drift past it.
        $y = (int) date('Y');
        find_number_settings_save($pdo, $userId, 'T-JJJJ-NNN', '7', true);
        check(find_number_restart($pdo, $userId), 'the flag sticks with a date in the pattern');

        $r1 = point_create($pdo, $userId, ['lat' => 50.6, 'lon' => 8.7,
            'title' => 'PRUEF Neustart 1', 'lists' => [$speciesList],
            'found_at' => "$y-03-01 10:00:00"]);
        check($pdo->query("SELECT find_number FROM map_points WHERE id = $r1")->fetchColumn() === "T-$y-007",
            'within the section the counter simply counts on');

        /* The year boundary via find_number_mint DIRECTLY: point_create
           would be the nicer path, but point_find_datetime rightly
           discards a find time in the future — a fixture "next January"
           never reaches the mint through it. The mint itself is exactly
           the piece under test. */
        $y2 = $y + 1;
        check(find_number_mint($pdo, $userId, [$speciesList], "$y2-01-05 09:00:00") === "T-$y2-001",
            'the first find of the new year begins at 1');
        check(find_number_mint($pdo, $userId, [$speciesList], "$y2-02-01 09:00:00") === "T-$y2-002",
            'and the second counts on within it');

        /* THE SECTION WHEN NOTHING IS STATED (25.09.2026, the form lost its
           next-number field): a restart that was on keeps its section, so
           the old year's count never lands on the new one; one switched on
           just now begins in today's; the preview (find_number_upcoming)
           promises what the mint will draw. */
        find_number_settings_save($pdo, $userId, 'U-JJJJ-NNN', '58', true);
        check(find_number_upcoming($pdo, $userId, 'U-JJJJ-NNN') === 58,
            'a stated number belongs to today\'s section — the next find draws it');
        $pdo->prepare('UPDATE map_users SET find_counter_period = ? WHERE id = ?')
            ->execute([(string) ($y - 1), $userId]);   // the last find was last year
        check(find_number_upcoming($pdo, $userId, 'U-JJJJ-NNN') === 1,
            'a new year with the restart on: the preview promises 1, as the mint will draw');
        find_number_settings_save($pdo, $userId, 'U2-JJJJ-NNN', null, true);
        check(find_number_counter_period($pdo, $userId) === (string) ($y - 1)
            && find_number_upcoming($pdo, $userId, 'U2-JJJJ-NNN') === 1 && find_number_next($pdo, $userId) === 58,
            'saving again without a number keeps the section — no 58 stamped onto the new year');
        find_number_settings_save($pdo, $userId, 'U2-JJJJ-NNN', null, false);
        check(!find_number_restart($pdo, $userId) && find_number_upcoming($pdo, $userId, 'U2-JJJJ-NNN') === 58,
            'without the restart the counter simply goes on');
        find_number_settings_save($pdo, $userId, 'U2-JJJJ-NNN', null, true);
        check(find_number_counter_period($pdo, $userId) === null
            && find_number_upcoming($pdo, $userId, 'U2-JJJJ-NNN') === 1,
            'a restart switched on where today\'s section has no number yet begins at 1 at once');
        find_number_settings_save($pdo, $userId, 'T-JJJJ-NNN', null, false);
        find_number_settings_save($pdo, $userId, 'T-JJJJ-NNN', null, true);
        check(find_number_counter_period($pdo, $userId) === (string) $y
            && find_number_upcoming($pdo, $userId, 'T-JJJJ-NNN') === 58,
            'one switched on where this year has numbers already counts on — ' . find_number_upcoming($pdo, $userId, 'T-JJJJ-NNN'));
        find_number_settings_save($pdo, $userId, 'V-JJJJ-NNN', null, false);
        find_number_settings_save($pdo, $userId, 'V-JJJJ-NNN', null, true);
        check(find_number_mint($pdo, $userId, [$speciesList], null) === "V-$y-001",
            'and the first find after such a tick draws 1 and stamps the section');
        check(find_number_counter_period($pdo, $userId) === (string) $y,
            'the section is stamped by that find');

        /* THE RESTART AND THE FINDS OF AN OLDER SECTION (measured
           25.09.2026): two finds this morning, then yesterday's offline
           finds handed in, then the next of today — it drew 01 a second
           time, because the one counter restarted at every change of
           section, backwards too. Times just after midnight: point_create
           rightly drops a find time in the future. */
        find_number_settings_save($pdo, $userId, 'JJMMTT-NN', '1', true);
        $day = date('Y-m-d');
        $dayBefore = date('Y-m-d', strtotime('-1 day'));
        $drawn = [];
        foreach (["$day 00:00:01", "$day 00:00:02", "$dayBefore 15:00:00", "$dayBefore 15:20:00", "$day 00:00:03"]
                 as $i => $at) {
            $id = point_create($pdo, $userId, ['lat' => 50.7, 'lon' => 8.8 + $i / 100,
                'title' => "PRUEF Tag $i", 'lists' => [$speciesList], 'found_at' => $at]);
            $drawn[] = $pdo->query("SELECT find_number FROM map_points WHERE id = $id")->fetchColumn();
        }
        check(count(array_unique($drawn)) === 5 && $drawn[4] === date('ymd') . '-03',
            'yesterday\'s finds handed in between do not reset today — the third of today is 03');
        check($drawn[2] === date('ymd', strtotime('-1 day')) . '-01' && $drawn[3] === date('ymd', strtotime('-1 day')) . '-02',
            'and yesterday counts in its own section');

        // A find from an old photo in the middle of the year.
        find_number_settings_save($pdo, $userId, 'F-JJJJ-NNN', '1', true);
        $old = $y - 1;
        $years = [];
        foreach (["$y-01-01 00:00:01", "$old-06-01 10:00:00", "$y-01-01 00:00:02", "$old-07-01 10:00:00"] as $i => $at) {
            $id = point_create($pdo, $userId, ['lat' => 50.8, 'lon' => 8.9 + $i / 100,
                'title' => "PRUEF Foto $i", 'lists' => [$speciesList], 'found_at' => $at]);
            $years[] = $pdo->query("SELECT find_number FROM map_points WHERE id = $id")->fetchColumn();
        }
        check($years === ["F-$y-001", "F-$old-001", "F-$y-002", "F-$old-002"],
            'a find of last year counts in last year, and this year goes on — ' . implode(' ', $years));

        // Without a date part the flag cannot stick — a restart there
        // would mint the same number twice.
        find_number_settings_save($pdo, $userId, 'X-NNN', '5', true);
        check(!find_number_restart($pdo, $userId),
            'without a date part the flag does not stick');
    } else {
        echo "  (restart skipped — migration 0072 is not applied)\n";
    }
    echo "The number field saves in the series' spelling:\n";
    find_number_settings_save($pdo, $userId, 'wk-NNNNN');
    $typed = point_create($pdo, $userId, ['lat' => 51.0, 'lon' => 9.0, 'title' => 'PRUEF Von Hand',
        'lists' => [$speciesList], 'found_at' => '2026-08-20 09:00:00']);
    point_update($pdo, $userId, $typed, ['find_number' => 'WK-01']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $typed")->fetchColumn() === 'wk-00001',
        '„WK-01" typed in the window is stored as wk-00001');
    point_update($pdo, $userId, $typed, ['find_number' => 'HM-0543']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $typed")->fetchColumn() === 'HM-0543',
        'a number of another shape stays as typed');
    check(find_number_series($pdo, $userId)['pattern'] === 'wk-NNNNN', 'the series the device gets names the pattern');

    echo "One number, any spelling (find_number_key — twin in tests/findnumber.mjs):\n";
    check(find_number_key('wk-NNNNN', 'WK-1') === 'wk-00001' && find_number_key('wk-NNNNN', 'wk-01') === 'wk-00001'
        && find_number_key('wk-NNNNN', ' wk-00001 ') === 'wk-00001', '„WK-1", „wk-01" and „wk-00001" are one number');
    check(find_number_key('wk-NNNNN', 'HM-0543') === 'hm-0543' && find_number_key(null, ' WK-1 ') === 'wk-1',
        'another shape, or no pattern: its letters, lower-cased');
    check(find_number_key('JJMMTT-NN', '190824-1') === '190824-01', 'a dated series pads its counter too');
    $given = point_create($pdo, $userId, ['lat' => 51.0, 'lon' => 9.0, 'title' => 'PRUEF Mitgebracht',
        'lists' => [$speciesList], 'found_at' => '2026-08-20 09:00:00', 'find_number' => 'WK-7']);
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $given")->fetchColumn() === 'wk-00007',
        'a number brought along at creation (an import) is stored in the series\' spelling, like a typed one');
    point_delete($pdo, $userId, $given);
    check(in_array(['number' => 'wk-00007', 'title' => 'PRUEF Mitgebracht'], find_number_trash($pdo, $userId), true),
        'the trash hands over its numbers — a find put back keeps its number');

    echo "The export shows a number on finds only:\n";
    require_once __DIR__ . '/../includes/export.php';
    // Into the LEAF: a point set into the heading above it draws a number
    // but ends in no list at all — headings hold nothing (CLAUDE.md).
    $inBranch = point_create($pdo, $userId, ['lat' => 51.1, 'lon' => 9.1, 'title' => 'PRUEF Im Ast',
        'lists' => [$branchLeaf], 'found_at' => '2026-08-21 09:00:00']);
    $minted = $pdo->query("SELECT find_number FROM map_points WHERE id = $inBranch")->fetchColumn();
    check(is_string($minted) && $minted !== '', 'a find set into the branch draws a number');
    $numbers = static fn(): array => array_column(
        export_points($pdo, $userId, [$inBranch, $waypoint]), 'find_number', 'id');
    check(($numbers()[$inBranch] ?? null) === $minted, 'a find exports its own number');
    // It wanders out of the species branch — the number stays in the
    // database, the file leaves it out; back in, it shows again.
    point_update($pdo, $userId, $inBranch, ['lists' => [$plainList]]);
    $out = $numbers();
    check($pdo->query("SELECT find_number FROM map_points WHERE id = $inBranch")->fetchColumn() === $minted,
        'a find moved out of the species branch keeps its number');
    // array_key_exists, not ??: a null is exactly the answer wanted here.
    check(array_key_exists($inBranch, $out) && $out[$inBranch] === null,
        '… but the export leaves it out while the point is no find');
    check(array_key_exists($waypoint, $out) && $out[$waypoint] === null,
        'a waypoint without a number exports none — no „Y-" either');
    point_update($pdo, $userId, $inBranch, ['lists' => [$branchLeaf]]);
    check(($numbers()[$inBranch] ?? null) === $minted, 'back in the branch, the same number shows again');
    $pdo->exec("UPDATE map_points SET find_number = NULL WHERE id = $inBranch");
    check(($numbers()[$inBranch] ?? null) === 'Y-' . $inBranch, 'a find without a number exports its „Y-" mark');

    /* MANY NUMBERS AT ONCE (Organisieren → Fundnummern, 26.09.2026). The
       first half are the very cases tests/numbering.mjs holds the browser
       twin to — the finds the maintainer tried the draft with. */
    echo "Many numbers at once — what „Fortlaufend ab\" reads (twin of readStart):\n";
    $s = find_number_start('wk-NNNNN', 'neu-01');
    check($s['kind'] === 'number' && $s['first'] === 1 && $s['width'] === 2 && $s['head'] === 'neu-' && $s['tail'] === '' && !$s['own'],
        'a first number of its own shape: its last digits count');
    $s = find_number_start('wk-NNNNN', 'wk-01');
    check($s['kind'] === 'number' && $s['own'] && $s['head'] === 'wk-' && $s['width'] === 5, 'the series\' own shape is spelled the series\' way');
    $s = find_number_start('wk-NNNNN', 'CW-JJJJ-NNNN');
    check($s['kind'] === 'pattern' && $s['pattern'] === 'CW-JJJJ-NNNN' && $s['first'] === 1 && $s['width'] === 4 && $s['dated'] && $s['unit'] === 'Jahr',
        'a pattern: the date from each find, the N-run from 1');
    $s = find_number_start('wk-NNNNN', 'wk-JJJJ-0100');
    check($s['kind'] === 'pattern' && $s['pattern'] === 'wk-JJJJ-NNNN' && $s['first'] === 100, 'a pattern with a written number starts there');
    check(find_number_start('wk-NNNNN', 'JJMMTT-NN')['unit'] === 'Tag', 'a pattern with day, month and year begins anew by the day');
    check(find_number_start('wk-NNNNN', 'NEU-01')['kind'] === 'number', 'a single N is a letter, not a placeholder');
    check(isset(find_number_start('wk-NNNNN', 'Y-5')['error']), 'the „Y-" mark is refused');
    check(isset(find_number_start('wk-NNNNN', 'HM-')['error']) && isset(find_number_start('wk-NNNNN', 'CW-JJJJ-')['error']),
        'nothing that could count is refused');
    check(find_number_start('wk-NNNNN', '  ') === null, 'nothing typed is no answer yet');

    echo "Many numbers at once — the plan (twin of planNumbers):\n";
    $wk  = ['pattern' => 'wk-NNNNN', 'counter' => 555, 'restart' => false, 'period' => null];
    $day = ['pattern' => 'JJMMTT-NN', 'counter' => 3, 'restart' => true, 'period' => '2026-09-26'];
    $chosen = [
        ['id' => 3120, 'title' => 'Boletus edulis', 'number' => null, 'found_at' => '2019-08-24 10:12:00'],
        ['id' => 3121, 'title' => 'Cantharellus cibarius', 'number' => null, 'found_at' => '2019-08-24 11:40:00'],
        ['id' => 3122, 'title' => 'Amanita muscaria', 'number' => 'wk-00003', 'found_at' => '2019-08-31 09:05:00'],
        ['id' => 3123, 'title' => 'Russula emetica', 'number' => null, 'found_at' => '2019-09-07 15:30:00'],
        ['id' => 3124, 'title' => 'Macrolepiota procera', 'number' => 'HM-0543', 'found_at' => '2019-09-07 16:02:00'],
        ['id' => 3125, 'title' => 'Lactarius deliciosus', 'number' => null, 'found_at' => '2019-09-14 13:20:00'],
        ['id' => 3126, 'title' => 'Craterellus cornucopioides', 'number' => null, 'found_at' => '2019-09-21 12:00:00'],
        ['id' => 4410, 'title' => 'Boletus reticulatus', 'number' => 'wk-00553', 'found_at' => '2026-09-20 11:05:00'],
        ['id' => 4418, 'title' => 'Amanita rubescens', 'number' => 'wk-00554', 'found_at' => '2026-09-24 15:40:00'],
        ['id' => 3127, 'title' => 'Hydnum repandum', 'number' => null, 'found_at' => null],
    ];
    $taken = ['wk-00006' => 'Russula ochroleuca', '190824-01' => 'Xerocomus badius',
              '260926-01' => 'Suillus luteus', '260926-02' => 'Suillus grevillei'];
    for ($i = 544; $i <= 552; $i++) {
        $taken['wk-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT)] = 'Fund aus der App';
    }
    $line = static fn(array $plan): string => implode(' ', array_map(static fn(array $r): string =>
        $r['id'] . ':' . $r['state'] . ($r['number'] !== null ? ':' . $r['number'] : '') . ($r['conflict'] !== null ? '@' . $r['conflict'] : ''),
        $plan['rows']));
    $plan = static fn(array $o): array => find_number_plan($o['series'] ?? $wk, $o['finds'] ?? $chosen, $o['taken'] ?? $taken,
        $o['mode'], $o['start'] ?? null, $o['restart'] ?? false, $o['scope'] ?? 'missing');

    $p = $plan(['mode' => 'next']);
    check($line($p) === '3120:new:wk-00555 3121:new:wk-00556 3122:kept 3123:new:wk-00557 3124:kept 3125:new:wk-00558 '
        . '3126:new:wk-00559 4410:kept 4418:kept 3127:new:wk-00560', 'the next free numbers, in the given order');
    check($p['counter'] === 561, 'and the counter goes on behind them');
    $p = $plan(['mode' => 'from', 'start' => find_number_start('wk-NNNNN', 'neu-01')]);
    check($line($p) === '3120:new:neu-01 3121:new:neu-02 3122:kept 3123:new:neu-03 3124:kept 3125:new:neu-04 '
        . '3126:new:neu-05 4410:kept 4418:kept 3127:new:neu-06', 'a new series walks past the kept numbers — no neu-04 after wk-00003');
    $p = $plan(['mode' => 'from', 'start' => find_number_start('wk-NNNNN', 'wk-01')]);
    check($line($p) === '3120:new:wk-00001 3121:new:wk-00002 3122:kept 3123:new:wk-00004 3124:kept 3125:new:wk-00005 '
        . '3126:taken:wk-00006@Russula ochroleuca 4410:kept 4418:kept 3127:new:wk-00007',
        'the notebook: wk-00003 holds its place, a taken number is left out, this year\'s numbers hold none');
    $p = $plan(['mode' => 'from', 'scope' => 'all', 'restart' => true, 'start' => find_number_start('wk-NNNNN', 'CW-JJJJ-NNNN')]);
    check($line($p) === '3120:new:CW-2019-0001 3121:new:CW-2019-0002 3122:new:CW-2019-0003 3123:new:CW-2019-0004 '
        . '3124:new:CW-2019-0005 3125:new:CW-2019-0006 3126:new:CW-2019-0007 4410:new:CW-2026-0001 4418:new:CW-2026-0002 3127:undated',
        'a new pattern, every year anew — a find without a find time stays');
    $p = $plan(['mode' => 'from', 'scope' => 'all', 'restart' => false, 'start' => find_number_start('wk-NNNNN', 'CW-JJJJ-NNNN')]);
    check($p['rows'][8]['number'] === 'CW-2026-0009', 'the same pattern running through the years');
    $p = $plan(['mode' => 'next', 'series' => $day, 'scope' => 'all']);
    check($line($p) === '3120:new:190824-02 3121:new:190824-03 3122:new:190831-01 3123:new:190907-01 3124:new:190907-02 '
        . '3125:new:190914-01 3126:new:190921-01 4410:new:260920-01 4418:new:260924-01 3127:undated',
        'the next free per day — a day that holds 01 goes on at 02');
    check($p['counter'] === 3 && $p['period'] === '2026-09-26', 'older days leave the counter of today alone');
    $p = $plan(['mode' => 'remove']);
    check($line($p) === '3120:kept 3121:kept 3122:new 3123:kept 3124:new 3125:kept 3126:kept 4410:new 4418:new 3127:kept',
        'removing touches only the finds with an own number');

    echo "Many numbers at once — no number twice, whatever the order:\n";
    $pair = [['id' => 1, 'title' => 'A', 'number' => 'wk-00007', 'found_at' => '2019-01-01 10:00:00'],
             ['id' => 2, 'title' => 'B', 'number' => 'wk-00001', 'found_at' => '2019-01-02 10:00:00']];
    $p = $plan(['mode' => 'from', 'scope' => 'all', 'finds' => $pair, 'taken' => ['wk-00002' => 'X'],
                'start' => find_number_start('wk-NNNNN', 'wk-01')]);
    check($line($p) === '1:taken:wk-00001@B 2:taken:wk-00002@X', 'B cannot move, so it keeps wk-00001 — and A may not take it');
    $p = $plan(['mode' => 'from', 'scope' => 'all', 'finds' => [$pair[1]], 'taken' => [],
                'start' => find_number_start('wk-NNNNN', 'wk-01')]);
    check($line($p) === '2:kept', 'a find that already carries its number stays as it is');
    $p = $plan(['mode' => 'from', 'scope' => 'all', 'finds' => [['id' => 9, 'title' => 'C', 'number' => null, 'found_at' => null]],
                'taken' => [], 'start' => find_number_start('wk-NNNNN', 'wk-NNN')]);
    check($line($p) === '9:new:wk-00001', 'a number of the series\' shape is spelled the series\' way, as saving will');

    /* And for real: the action writes through point_update, under the
       account's lock, and refuses a missing answer before anything moves. */
    echo "Many numbers at once — written (organize_numbers_apply):\n";
    require_once __DIR__ . '/../includes/organize_content.php';
    find_number_settings_save($pdo, $userId, 'wk-NNNNN');
    $mk = static function (string $title, ?string $at, array $lists) use ($pdo, $userId): int {
        $id = point_create($pdo, $userId, ['lat' => 50.3, 'lon' => 8.4, 'title' => $title, 'lists' => $lists,
            'found_at' => $at ?? '2026-09-01 09:00:00']);
        $pdo->exec("UPDATE map_points SET find_number = NULL" . ($at === null ? ', found_at = NULL' : '') . " WHERE id = $id");
        return $id;
    };
    $a = $mk('PRUEF Buch A', '2019-08-24 10:12:00', [$branchLeaf]);
    $b = $mk('PRUEF Buch B', '2019-08-31 09:05:00', [$branchLeaf]);
    $c = $mk('PRUEF Buch C', '2019-09-07 15:30:00', [$branchLeaf]);
    $way = $mk('PRUEF Parkplatz', '2019-08-24 09:40:00', [$plainList]);
    $pdo->exec("UPDATE map_points SET find_number = 'wk-00003', note = 'Am Hang' WHERE id = $b");
    $num = static fn(int $id): ?string => ($v = $pdo->query("SELECT find_number FROM map_points WHERE id = $id")->fetchColumn()) === false ? null : $v;
    $ask = static fn(array $o): array => $o + ['mode' => '', 'start' => '', 'restart' => '', 'scope' => '', 'old' => '', 'adopt' => ''];

    $done = organize_numbers_apply($pdo, $userId, [$a, $b, $c, $way], $ask(['mode' => 'from', 'start' => 'wk-01', 'scope' => 'missing']));
    check($num($a) === 'wk-00001' && $num($b) === 'wk-00003' && $num($c) === 'wk-00002' && $num($way) === null && $done['given'] === 2,
        'fortlaufend from wk-01: the finds without a number get theirs, the waypoint none');
    check(rejected(fn() => organize_numbers_apply($pdo, $userId, [$a, $b, $c], $ask(['mode' => 'from', 'start' => 'neu-01', 'scope' => 'all']))),
        'a find about to lose its number needs the answer what becomes of it');
    check($num($b) === 'wk-00003', '… and nothing was written before the refusal');
    $done = organize_numbers_apply($pdo, $userId, [$a, $b, $c], $ask(['mode' => 'from', 'start' => 'neu-01', 'scope' => 'all', 'old' => 'note']));
    check($num($a) === 'neu-01' && $num($b) === 'neu-02' && $num($c) === 'neu-03' && $done['noted'],
        'all of them anew from neu-01');
    // A number stored the old way, unpadded — as imports kept them before
    // the review — is the number the count reaches all the same.
    $pdo->exec("UPDATE map_points SET find_number = 'wk-2' WHERE id = $way");
    $done = organize_numbers_apply($pdo, $userId, [$a, $b, $c], $ask(['mode' => 'from', 'start' => 'wk-01', 'scope' => 'all', 'old' => 'drop']));
    check($num($a) === 'wk-00001' && $num($b) === 'neu-02' && $num($c) === 'wk-00003' && $done['taken'] === 1,
        '„wk-2" stored the old way is the wk-00002 the count reaches — that find is left out, nothing doubled');
    $pdo->exec("UPDATE map_points SET find_number = NULL WHERE id = $way");
    check($pdo->query("SELECT note FROM map_points WHERE id = $b")->fetchColumn() === "Am Hang\nFrühere Fundnummer: wk-00003",
        'the old number stands in the note, under what was there');
    check(rejected(fn() => organize_numbers_apply($pdo, $userId, [$a, $b, $c],
            $ask(['mode' => 'from', 'start' => 'CW-JJJJ-NNNN', 'scope' => 'all', 'old' => 'drop', 'restart' => 'yes']))),
        'a new pattern needs the answer whether new finds follow it');
    $done = organize_numbers_apply($pdo, $userId, [$a, $b, $c],
        $ask(['mode' => 'from', 'start' => 'CW-JJJJ-NNNN', 'scope' => 'all', 'old' => 'drop', 'restart' => 'yes', 'adopt' => 'yes']));
    check($num($a) === 'CW-2019-0001' && $num($b) === 'CW-2019-0002' && $num($c) === 'CW-2019-0003' && $done['adopted'] === 'CW-JJJJ-NNNN',
        'the whole system switched: a pattern, every year anew');
    check(find_number_pattern($pdo, $userId) === 'CW-JJJJ-NNNN' && find_number_restart($pdo, $userId),
        'and new finds follow it — the settings carry the pattern and the restart');
    $late = point_create($pdo, $userId, ['lat' => 50.3, 'lon' => 8.4, 'title' => 'PRUEF Nachzügler', 'lists' => [$branchLeaf],
        'found_at' => '2019-10-01 09:00:00']);
    check($num($late) === 'CW-2019-0004', 'a find of that year handed in later goes on behind them');
    $done = organize_numbers_apply($pdo, $userId, [$a, $b, $c], $ask(['mode' => 'next', 'scope' => 'all', 'old' => 'drop']));
    check($num($a) === 'CW-2019-0005' && $num($c) === 'CW-2019-0007', 'the next free ones: behind the highest of their year');
    $done = organize_numbers_apply($pdo, $userId, [$a, $b], $ask(['mode' => 'remove', 'old' => 'drop']));
    check($num($a) === null && $num($b) === null && $done['given'] === 2, 'removed: the finds wear their „Y-" mark again');
    check(organize_numbers_message($done) === '2 Funde tragen wieder ihre Nummer von YASAIMAPP.', 'the toast says what happened');
} finally {
    $pdo->exec("DELETE FROM map_point_lists WHERE point_id IN (SELECT id FROM map_points WHERE user_id = $userId)");
    $pdo->exec("DELETE FROM map_points WHERE user_id = $userId");
    $pdo->exec("DELETE FROM map_lists WHERE user_id = $userId");
    $pdo->exec("DELETE FROM map_imports WHERE user_id = $userId");
    $pdo->exec("DELETE FROM map_users WHERE id = $userId");
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
