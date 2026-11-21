<?php
/**
 * Tests the computation of the Messtischblatt.
 *
 *   php tests/mtb.php        (from the project directory)
 *
 * WHAT IS TESTED AGAINST. Against the coordinate tool of the AHO
 * Baden-Württemberg (orchids.de/geozeugs/koordinatenermittler2/) — the
 * tool the mapping community works with. Its answers stand here as fixed
 * values; the test needs no network and still holds when the site is
 * gone one day.
 *
 * THE EDGES ARE THE REAL TOUCHSTONE, not the sheet centres.
 *
 * In the centre of a sheet almost every formula is right — even one that
 * leaves out the datum. Between WGS84 and Potsdam lie about a hundred
 * metres in Germany; whoever skips that gets the same result everywhere
 * and the wrong one at every sheet edge. A strip of a hundred metres
 * around every line in which the neighbour's number silently comes out.
 *
 * That is why this test seeks out the edges and stands just to the right
 * and left of them: 120 transitions, 60 each in latitude and longitude,
 * among them five real sheet borders. The points lie one ten-millionth
 * of a degree — about one centimetre — beside the line. Whoever passes
 * this draws the same lines as the tool.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/mtb.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** The long form "5817/433". */
function longForm(float $b, float $l): string
{
    $m = mtb_from_coordinates($b, $l);
    return $m === null ? '-' : $m['full'];
}

echo "\nMesstischblatt\n\n";

// ---------------------------------------------------------------------------
echo "Known sheets\n";

$known = [
    [53.55,   10.0,    '2426', 'Hamburg'],
    [48.137,  11.575,  '7835', 'München'],
    [51.05,   13.74,   '4948', 'Dresden'],
    [54.32,   10.13,   '1626', 'Kiel'],
    [50.1,     8.6,    '5817', 'Frankfurt a.M. West'],
    [50.9,     6.96,   '5007', 'Köln'],
];
foreach ($known as [$b, $l, $expected, $name]) {
    $m = mtb_from_coordinates($b, $l);
    check(($m['sheet'] ?? '') === $expected, "$name is sheet $expected");
}

// ---------------------------------------------------------------------------
echo "\nStructure of the designation\n";

$m = mtb_from_coordinates(50.1, 8.6);
check($m['full'] === $m['sheet'] . '/' . $m['quadrant'] . $m['q16'] . $m['q64'],
    'the long form is composed of sheet plus three digits');
check(in_array($m['quadrant'], ['1','2','3','4'], true), 'the quadrant lies between 1 and 4');
check(mtb_short($m) === $m['sheet'] . '/' . $m['quadrant'], 'the short form names sheet and quadrant');
check(strlen($m['sheet']) === 4, 'the sheet has four digits');
check(mtb_short(null) === '', 'without a sheet the short form stays empty');

/* WHAT LEAVES THE HOUSE (27.08.2026: „Ich möchte mit 16 und
   64"). ALL THREE DIGITS, and the test says so out loud because the first
   version cut them down to one: sixteenth and sixty-fourth are the
   difference between 5.5 km and 1.4 km, and out of a file nobody can get
   them back. */
check(mtb_full('6022', '222') === '6022/222',
    'the written form carries the sheet and all three quadrant digits');
check(mtb_full($m['sheet'], $m['quadrant'] . $m['q16'] . $m['q64']) === $m['full'],
    'and says the same as the freshly computed long form');
check(mtb_full('6022', null) === '6022',
    'without a quadrant the sheet stands alone — no dangling slash');
check(mtb_full('6022', '') === '6022', 'and an empty one just the same');
check(mtb_full(null, '222') === '', 'without a sheet nothing at all');
check(mtb_full('', '222') === '', 'an empty sheet is no sheet');
check(mtb_full(' 6022 ', ' 222 ') === '6022/222', 'stray spaces do not become part of it');

// Compass direction and quadrant number must fit together.
foreach ([[50.1, 8.6], [53.55, 10.0], [48.137, 11.575], [54.32, 10.13]] as [$b, $l]) {
    $m = mtb_from_coordinates($b, $l);
    $expected = ['1' => 'NW', '2' => 'NO', '3' => 'SW', '4' => 'SO'][$m['quadrant']];
    check($m['compass'] === $expected,
        "quadrant {$m['quadrant']} is called $expected");
}

// ---------------------------------------------------------------------------
echo "\nOutside Germany\n";

check(mtb_from_coordinates(46.5, 11.35) === null, 'Dolomites: no sheet');
check(mtb_from_coordinates(59.33, 18.07) === null, 'Stockholm: no sheet');
check(mtb_from_coordinates(0.0, 0.0)   === null, 'Null Island: no sheet');
check(mtb_from_coordinates(55.5, 10.0) === null, 'north of the border: no sheet');
check(mtb_from_coordinates(50.0, 4.0)  === null, 'west of the border: no sheet');

// ---------------------------------------------------------------------------
echo "\nThe datum counts\n";

// WITHOUT the datum conversion something else would come out here. The
// probe: the raw formula applied to WGS84 — it must deviate from the
// correct result at at least one of these spots, otherwise the edge test
// below tests nothing.
$rawDeviations = 0;
foreach ([[50.111338977, 8.6], [50.1, 8.665675369], [47.9, 8.332404414]] as [$b, $l]) {
    $row  = (int) floor(560 - $b * 10.0);
    $column = (int) floor($l * 6.0 - 34);
    $raw = str_pad((string) ($row * 100 + $column), 4, '0', STR_PAD_LEFT);
    $real = mtb_from_coordinates($b, $l)['sheet'];
    if ($raw !== $real) { $rawDeviations++; }
}
check($rawDeviations > 0,
    'without the datum conversion something else would come out at sheet edges — so the conversion is not without effect');

// ---------------------------------------------------------------------------
echo "\nEdges: 60 in latitude, 60 in longitude\n";

/**
 * Per entry: direction, latitude and longitude of the edge, and what must
 * come out one centimetre before and behind it. The values come from the
 * Koordinatenermittler.
 */
$edges = [
    ['B', 47.911589391, 8.3, '8015/443', '8015/441'],
    ['B', 47.92408796, 8.3, '8015/441', '8015/423'],
    ['B', 47.936586553, 8.3, '8015/423', '8015/421'],
    ['B', 47.949085122, 8.3, '8015/421', '8015/243'],
    ['L', 47.9, 8.311574014, '8015/443', '8015/444'],
    ['L', 47.9, 8.332404414, '8015/444', '8016/333'],
    ['L', 47.9, 8.353234814, '8016/333', '8016/334'],
    ['L', 47.9, 8.374065214, '8016/334', '8016/343'],
    ['B', 48.611522241, 11.2, '7333/334', '7333/332'],
    ['B', 48.62402081, 11.2, '7333/332', '7333/314'],
    ['B', 48.63651938, 11.2, '7333/314', '7333/312'],
    ['B', 48.649017972, 11.2, '7333/312', '7333/134'],
    ['L', 48.6, 11.20699069, '7333/334', '7333/343'],
    ['L', 48.6, 11.227821051, '7333/343', '7333/344'],
    ['L', 48.6, 11.248651431, '7333/344', '7333/433'],
    ['L', 48.6, 11.269481792, '7333/433', '7333/434'],
    ['B', 49.411416349, 7.4, '6510/344', '6510/342'],
    ['B', 49.423914919, 7.4, '6510/342', '6510/324'],
    ['B', 49.436413488, 7.4, '6510/324', '6510/322'],
    ['B', 49.448912058, 7.4, '6510/322', '6510/144'],
    ['L', 49.4, 7.415861687, '6510/344', '6510/433'],
    ['L', 49.4, 7.436692009, '6510/433', '6510/434'],
    ['L', 49.4, 7.457522311, '6510/434', '6510/443'],
    ['L', 49.4, 7.478352633, '6510/443', '6510/444'],
    ['B', 50.111338977, 8.6, '5817/433', '5817/431'],
    ['B', 50.123837547, 8.6, '5817/431', '5817/413'],
    ['B', 50.136336116, 8.6, '5817/413', '5817/411'],
    ['B', 50.148834686, 8.6, '5817/411', '5817/233'],
    ['L', 50.1, 8.603184558, '5817/433', '5817/434'],
    ['L', 50.1, 8.624014822, '5817/434', '5817/443'],
    ['L', 50.1, 8.644845086, '5817/443', '5817/444'],
    ['L', 50.1, 8.665675369, '5817/444', '5818/333'],
    ['B', 50.911244193, 6.96, '5007/443', '5007/441'],
    ['B', 50.923742762, 6.96, '5007/441', '5007/423'],
    ['B', 50.936241332, 6.96, '5007/423', '5007/421'],
    ['B', 50.948739878, 6.96, '5007/421', '5007/243'],
    ['L', 50.9, 6.978421723, '5007/443', '5007/444'],
    ['L', 50.9, 6.999251929, '5007/444', '5008/333'],
    ['L', 50.9, 7.020082154, '5008/333', '5008/334'],
    ['L', 50.9, 7.040912359, '5008/334', '5008/343'],
    ['B', 51.511183733, 9.9, '4425/344', '4425/342'],
    ['B', 51.523682302, 9.9, '4425/342', '4425/324'],
    ['B', 51.536180872, 9.9, '4425/324', '4425/322'],
    ['B', 51.548679418, 9.9, '4425/322', '4425/144'],
    ['L', 51.5, 9.915475291, '4425/344', '4425/433'],
    ['L', 51.5, 9.936305477, '4425/433', '4425/434'],
    ['L', 51.5, 9.957135643, '4425/434', '4425/443'],
    ['L', 51.5, 9.977965829, '4425/443', '4425/444'],
    ['B', 52.311113963, 13.1, '3644/433', '3644/431'],
    ['B', 52.323612533, 13.1, '3644/431', '3644/413'],
    ['B', 52.336111102, 13.1, '3644/413', '3644/411'],
    ['B', 52.348609672, 13.1, '3644/411', '3644/233'],
    ['L', 52.3, 13.10247541, '3644/433', '3644/434'],
    ['L', 52.3, 13.123305538, '3644/434', '3644/443'],
    ['L', 52.3, 13.144135685, '3644/443', '3644/444'],
    ['L', 52.3, 13.164965812, '3644/444', '3645/333'],
    ['B', 53.111002965, 10.4, '2828/344', '2828/342'],
    ['B', 53.123501534, 10.4, '2828/342', '2828/324'],
    ['B', 53.136000104, 10.4, '2828/324', '2828/322'],
    ['B', 53.148498673, 10.4, '2828/322', '2828/144'],
    ['L', 53.1, 10.415376535, '2828/344', '2828/433'],
    ['L', 53.1, 10.436206604, '2828/433', '2828/434'],
    ['L', 53.1, 10.457036674, '2828/434', '2828/443'],
    ['L', 53.1, 10.477866743, '2828/443', '2828/444'],
    ['B', 53.910905638, 9.2, '2021/334', '2021/332'],
    ['B', 53.923404208, 9.2, '2021/332', '2021/314'],
    ['B', 53.935902777, 9.2, '2021/314', '2021/312'],
    ['B', 53.948401347, 9.2, '2021/312', '2021/134'],
    ['L', 53.9, 9.207224028, '2021/334', '2021/343'],
    ['L', 53.9, 9.228054039, '2021/343', '2021/344'],
    ['L', 53.9, 9.248884031, '2021/344', '2021/433'],
    ['L', 53.9, 9.269714041, '2021/433', '2021/434'],
    ['B', 54.610827986, 9.8, '1324/443', '1324/441'],
    ['B', 54.623326556, 9.8, '1324/441', '1324/423'],
    ['B', 54.635825125, 9.8, '1324/423', '1324/421'],
    ['B', 54.648323695, 9.8, '1324/421', '1324/243'],
    ['L', 54.6, 9.811284417, '1324/443', '1324/444'],
    ['L', 54.6, 9.832114369, '1324/444', '1325/333'],
    ['L', 54.6, 9.852944302, '1325/333', '1325/334'],
    ['L', 54.6, 9.873774254, '1325/334', '1325/343'],
    ['B', 48.211578395, 12.7, '7742/334', '7742/332'],
    ['B', 48.224076987, 12.7, '7742/332', '7742/314'],
    ['B', 48.236575557, 12.7, '7742/314', '7742/312'],
    ['B', 48.249074149, 12.7, '7742/312', '7742/134'],
    ['L', 48.2, 12.70678478, '7742/334', '7742/343'],
    ['L', 48.2, 12.72761518, '7742/343', '7742/344'],
    ['L', 48.2, 12.74844558, '7742/344', '7742/433'],
    ['L', 48.2, 12.769275979, '7742/433', '7742/434'],
    ['B', 51.011268398, 13.74, '4948/344', '4948/342'],
    ['B', 51.023766967, 13.74, '4948/342', '4948/324'],
    ['B', 51.03626556, 13.74, '4948/324', '4948/322'],
    ['B', 51.048764129, 13.74, '4948/322', '4948/144'],
    ['L', 51, 13.748242338, '4948/344', '4948/433'],
    ['L', 51, 13.769072563, '4948/433', '4948/434'],
    ['L', 51, 13.789902807, '4948/434', '4948/443'],
    ['L', 51, 13.810733032, '4948/443', '4948/444'],
    ['B', 49.01147007, 10, '6926/333', '6926/331'],
    ['B', 49.023968639, 10, '6926/331', '6926/313'],
    ['B', 49.036467231, 10, '6926/313', '6926/311'],
    ['B', 49.048965801, 10, '6926/311', '6926/133'],
    ['L', 49, 10.019655409, '6926/333', '6926/334'],
    ['L', 49, 10.04048575, '6926/334', '6926/343'],
    ['L', 49, 10.061316092, '6926/343', '6926/344'],
    ['L', 49, 10.082146433, '6926/344', '6926/433'],
    ['B', 52.011121098, 8.5, '3917/333', '3917/331'],
    ['B', 52.023619668, 8.5, '3917/331', '3917/313'],
    ['B', 52.036118237, 8.5, '3917/313', '3917/311'],
    ['B', 52.048616807, 8.5, '3917/311', '3917/133'],
    ['L', 52, 8.519849501, '3917/333', '3917/334'],
    ['L', 52, 8.540679648, '3917/334', '3917/343'],
    ['L', 52, 8.561509795, '3917/343', '3917/344'],
    ['L', 52, 8.582339922, '3917/344', '3917/433'],
    ['B', 54.010910338, 12, '1938/333', '1938/331'],
    ['B', 54.023408907, 12, '1938/331', '1938/313'],
    ['B', 54.035907477, 12, '1938/313', '1938/311'],
    ['B', 54.048406046, 12, '1938/311', '1938/133'],
    ['L', 54, 12.0192726, '1938/333', '1938/334'],
    ['L', 54, 12.040102611, '1938/334', '1938/343'],
    ['L', 54, 12.060932622, '1938/343', '1938/344'],
    ['L', 54, 12.081762613, '1938/344', '1938/433'],];

const EDGE_DELTA = 1e-7;      // about one centimetre

$errors = [];
$latEdges = 0; $lonEdges = 0; $sheetBorders = 0;
foreach ($edges as [$kind, $b, $l, $before, $after]) {
    $kind === 'B' ? $latEdges++ : $lonEdges++;
    if (substr($before, 0, 4) !== substr($after, 0, 4)) { $sheetBorders++; }

    $seenBefore = $kind === 'B' ? longForm($b - EDGE_DELTA, $l) : longForm($b, $l - EDGE_DELTA);
    $seenAfter  = $kind === 'B' ? longForm($b + EDGE_DELTA, $l) : longForm($b, $l + EDGE_DELTA);

    if ($seenBefore !== $before || $seenAfter !== $after) {
        $errors[] = sprintf('%s %.9f/%.9f: %s|%s instead of %s|%s',
            $kind, $b, $l, $seenBefore, $seenAfter, $before, $after);
    }
}

check($latEdges === 60 && $lonEdges === 60, "$latEdges latitude edges and $lonEdges longitude edges");
check($sheetBorders >= 5, "among them $sheetBorders real sheet borders");
check($errors === [], count($edges) . ' edges lie at the same spot as in the Koordinatenermittler');

foreach (array_slice($errors, 0, 8) as $f) {
    echo "        $f\n";
}

// ---------------------------------------------------------------------------
echo "\nThe country beats the rectangle\n";

/* THE BOUNDING BOX REACHES DEEP INTO AUSTRIA — 47.20 to 55.10 north,
   5.80 to 15.10 east —, and Vorarlberg, Tyrol and Salzburg lie inside
   it. Those finds used to get a sheet number that was never printed
   (25.08.2026). Since then the country decides where it is
   known.

   Guarded from both sides, because both mistakes are possible: a German
   find must keep its sheet, and an unknown country must NOT cost one —
   the reverse geocoding runs after a point is created, and until it has
   run, only the coordinates are there. */
foreach ([
    ['Frankfurt, Deutschland',  50.11, 8.68,  'DE', true],
    ['Frankfurt, klein',        50.11, 8.68,  'de', true],
    ['Innsbruck, Österreich',   47.27, 11.39, 'AT', false],
    ['Salzburg, Österreich',    47.80, 13.05, 'AT', false],
    ['Basel, Schweiz',          47.56, 7.59,  'CH', false],
    ['Innsbruck, Land unbekannt', 47.27, 11.39, null, true],
    ['Innsbruck, Land leer',      47.27, 11.39, '',   true],
] as [$what, $lat, $lon, $country, $expected]) {
    $sheet = mtb_from_coordinates($lat, $lon, $country);
    check(($sheet !== null) === $expected,
        $what . ' — ' . ($expected ? 'bekommt ein Blatt' : 'bekommt keins')
        . ($sheet !== null ? ' (' . $sheet['sheet'] . ')' : ''));
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
