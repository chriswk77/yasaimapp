<?php
/**
 * Tests the measurement arithmetic (includes/measures.php) against
 * hand-computed numbers — the tripwire between the PHP twin and measurestats.js.
 *   php tests/measures.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/measures.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}
$near = static fn(float $a, float $b): bool => abs($a - $b) < 1e-6;

// A calibration of 100 px = 10 µm, drawn on a 1000-px original; the
// measures were tapped on a 500-px preview — the width chain doubles.
$scale = ['line' => [[0, 0], [100, 0]], 'um' => 10, 'width' => 1000];
// Axis a→b is 50 px long (3-4-5), the flanks c and d lie 10 px apart
// across it: L = 50 · 0.2 = 10 µm, B = 10 · 0.2 = 2 µm, Q = 5.
$rect = ['type' => 'rect', 'points' => [[0, 0], [30, 40], [0, 0], [-8, 6]], 'width' => 500];

echo "One rectangle:\n";
check($near(measure_um_per_px($rect, $scale), 0.2), 'µm per pixel carries the width ratio: 0.1 · 1000/500 = 0.2');
$d = measure_rect_dims($rect, $scale);
check($d !== null && $near($d['L'], 10.0) && $near($d['B'], 2.0), 'length 10 µm along the axis, breadth 2 µm across it');
check(measure_rect_dims($rect, null) === null, 'without a calibration there is no size');
check(measure_label($rect) === 'Sporen', 'a measure without a label is a spore — the client says the same');

echo "A cutout with inner measures:\n";
$frame = ['type' => 'rect', 'points' => [[0, 0], [100, 0], [0, 0], [0, 50]], 'width' => 500, 'label' => 'Basidien',
    'inner' => [['type' => 'line', 'points' => [[0, 0], [30, 40]]], ['type' => 'line', 'points' => [[0, 0], [0, 10]]]]];
$v = measure_frame_values($frame, $scale);
check($v !== null && $near($v['L'], 10.0) && $near($v['B'], 2.0) && $near($v['Q'], 5.0), 'two lines: length, breadth and Q from inside the frame');
$frame2 = $frame; $frame2['inner'] = [['type' => 'line', 'points' => [[0, 0], [0, 10]]], ['type' => 'path', 'points' => [[0, 0], [30, 40], [30, 50]]]];
$v2 = measure_frame_values($frame2, $scale);
check($v2 !== null && $near($v2['L'], 12.0) && $near($v2['B'], 2.0) && $near($v2['Q'], 6.0), 'a polyline takes the length slot whatever the draw order, and Q comes from it too (the allantoid spore, 04.10.2026)');
$frame3 = $frame; $frame3['inner'] = [['type' => 'path', 'points' => [[0, 0], [30, 40]]], ['type' => 'path', 'points' => [[0, 0], [0, 10]]]];
$v3 = measure_frame_values($frame3, $scale);
check($v3 !== null && $v3['Q'] === null, 'two polylines (old cutouts only — no longer offered) have no straight breadth, so no Q');

echo "The length straight, as the books measure it (07.10.2026):\n";
// The polyline (0,0) → (30,40) → (30,50) runs 50 + 10 = 60 px along its
// bend, but its ends lie √(30² + 50²) = 58.3095… px apart: 11.6619 µm.
$s2 = measure_frame_values($frame2, $scale, true);
check($s2 !== null && $near($s2['L'], sqrt(30 ** 2 + 50 ** 2) * 0.2) && $near($s2['B'], 2.0)
    && $near($s2['Q'], sqrt(30 ** 2 + 50 ** 2) * 0.2 / 2), 'straight: from the first point to the last, and Q divides that length');
check($v2['bent'] === true && $s2['bent'] === true, 'a polyline length is marked bent, whichever way it is read');
$s1 = measure_frame_values($frame, $scale, true);
check($s1 !== null && $near($s1['L'], 10.0) && $s1['bent'] === false, 'a straight line stays what it was — there is nothing to straighten');
$bentPictures = [['scale' => $scale, 'measures' => [$frame2, $rect]]];
$bentRows = measure_rows($bentPictures, 'Basidien', true);
check(count($bentRows) === 1 && $near($bentRows[0]['L'], sqrt(30 ** 2 + 50 ** 2) * 0.2), 'the rows pass the choice on');
check(measure_rows($bentPictures, 'Sporen', true)[0]['bent'] === false, 'a plain rectangle is never bent');

echo "The mark rides on the rows (07.10.2026):\n";
$markedPictures = [['scale' => $scale, 'measures' => [$rect + ['mark' => 'Chrysozystide'], array_merge($frame2, ['label' => 'Sporen']), $rect]]];
$markRows = measure_rows($markedPictures, 'Sporen');
check(array_column($markRows, 'mark') === ['Chrysozystide', '', ''], 'each row says its mark, the unmarked an empty one — a cutout too');
check(measure_mark(['mark' => 7]) === '' && measure_mark([]) === '', 'anything but a word is no mark');

echo "Part measures (07.10.2026):\n";
// Lines of 50 px (3-4-5) and 10 px at 0.2 µm per px: 10 µm and 2 µm.
$partPictures = [['scale' => $scale, 'measures' => [
    ['type' => 'line', 'points' => [[0, 0], [30, 40]], 'width' => 500, 'label' => 'Basidien', 'part' => 'Sterigmenlänge'],
    ['type' => 'line', 'points' => [[0, 0], [0, 10]], 'width' => 500, 'label' => 'Basidien', 'part' => 'Sterigmenlänge'],
    ['type' => 'line', 'points' => [[0, 0], [0, 10]], 'width' => 500, 'label' => 'Basidien'],
    ['type' => 'rect', 'points' => [[0, 0], [30, 40], [0, 0], [-8, 6]], 'width' => 500, 'label' => 'Basidien', 'part' => 'Kopf'],
    ['type' => 'line', 'points' => [[0, 0], [0, 10]], 'width' => 500, 'label' => 'Sporen', 'part' => 'Keimporus'],
]]];
$partRows = measure_part_rows($partPictures, 'Basidien');
check(count($partRows) === 2 && $near($partRows[0]['um'], 10.0) && $near($partRows[1]['um'], 2.0),
    'only named lines of the layer count, in µm through the picture\'s scale');
check(measure_part_lines($partRows) === ['Sterigmenlänge' => '2 – 10 µm · N = 2 · Ø 6 µm'],
    'one line per part: the span, the count and the average');
check(measure_part_lines(measure_part_rows($partPictures, 'Sporen')) === ['Keimporus' => '2 µm · N = 1'], 'a single one says its bare value');
check(measure_labels([['scale' => $scale, 'measures' => [['type' => 'line', 'points' => [[0, 0], [0, 10]], 'label' => 'Basidien', 'part' => 'Sterigmenlänge']]]]) === ['Basidien'],
    'a layer of part measures alone is a layer the report names');

echo "Rows and the summary:\n";
$pictures = [
    ['scale' => $scale, 'measures' => [$rect, $frame]],
    ['scale' => $scale, 'measures' => [['type' => 'rect', 'points' => [[0, 0], [60, 0], [0, 0], [0, 15]], 'width' => 500]]],
];
check(measure_labels($pictures) === ['Sporen', 'Basidien'], 'the labels, in the order first met');
$rows = measure_rows($pictures, 'Sporen');
check(count($rows) === 2 && $near($rows[1]['L'], 12.0) && $near($rows[1]['B'], 3.0), 'spore rows come from every picture');
$s = measure_summary($rows);
check($s[0] === '10 – 12 × 2 – 3 µm', 'two rows give the plain span, no deciles: ' . $s[0]);
check($s[1] === 'Q = 4 – 5 · N = 2', 'Q span and N: ' . $s[1]);
check($s[2] === 'Ø 11 × 2,5 µm · Q ≈ 4,5', 'the plain average: ' . $s[2]);
check(measure_summary([$rows[0]]) === ['10 × 2 µm · Q ≈ 5'], 'a single row says its bare measure');
check(measure_summary([]) === [], 'nothing measured, nothing said');
echo "Cystidia in their parts (05.11.2026):\n";
// Drawn on the 1000-px original itself: 0.1 µm per pixel. The frame only frames; the four inner measures count.
$cystid = static fn(string $profile, array $px) => ['type' => 'rect', 'points' => [[0, 0], [400, 0], [0, -60], [0, 60]],
    'width' => 1000, 'label' => 'Cheilozystiden', 'profile' => $profile, 'inner' => [
        ['type' => 'path', 'points' => [[0, 0], [$px[0] / 2, 0], [$px[0], 0]]],
        ['type' => 'line', 'points' => [[0, 0], [0, $px[1]]]],
        ['type' => 'line', 'points' => [[0, 0], [0, $px[2]]]],
        ['type' => 'line', 'points' => [[0, 0], [$px[3], 0]]],
    ]];
// Hebeloma: l 20, A 6, M 4, B 9 → A/M 1.5, A/B 0.667, B/M 2.25; and l 30, A 8, M 4, B 10 → 2, 0.8, 2.5.
$h1 = measure_frame_values($cystid('hebeloma', [200, 60, 40, 90]), $scale);
check($h1 !== null && $near($h1['L'], 20.0) && $h1['B'] === null && $h1['Q'] === null, 'a Hebeloma cystidium: its length, no breadth, no Q');
check($near($h1['parts']['A'], 6.0) && $near($h1['parts']['M'], 4.0) && $near($h1['parts']['B'], 9.0), 'A, M and B in µm');
check($near($h1['ratios']['A/M'], 1.5) && $near($h1['ratios']['A/B'], 6 / 9) && $near($h1['ratios']['B/M'], 2.25), 'its own A/M, A/B, B/M');
$heb = measure_rows([['scale' => $scale, 'measures' => [$cystid('hebeloma', [200, 60, 40, 90]), $cystid('hebeloma', [300, 80, 40, 100])]]], 'Cheilozystiden');
$s = measure_summary($heb);
check($s[0] === 'L 20 – 30 µm', 'the length as the first line, named: ' . $s[0]);
// Stacked (08.11.2026): each width, each ratio and each average on a line of its own.
check(array_slice($s, 1, 3) === ['A 6 – 8 µm', 'M 4 µm', 'B 9 – 10 µm'], 'the widths, one under the other: ' . implode(' | ', array_slice($s, 1, 3)));
check($s[4] === 'N = 2', 'N: ' . $s[4]);
check(!in_array('A/M = 1,5 – 2', $s, true), 'no span of the ratios — only their averages compare with the books');
check(array_slice($s, 5) === ['Ø L 25 µm', 'Ø A 7 µm', 'Ø M 4 µm', 'Ø B 9,5 µm', 'Ø A/M ≈ 1,75', 'Ø A/B ≈ 0,73', 'Ø B/M ≈ 2,38'], 'the averages, one under the other: ' . implode(' | ', array_slice($s, 8)));
check(measure_summary(measure_rows([['scale' => $scale, 'measures' => [$cystid('hebeloma', [200, 60, 40, 90])]]], 'Cheilozystiden'))
    === ['L 20 µm', 'A 6 µm', 'M 4 µm', 'B 9 µm', 'A/M ≈ 1,5', 'A/B ≈ 0,67', 'B/M ≈ 2,25'], 'one Hebeloma cystidium, stacked too');
// Conocybe: length 20, belly 8, head 5, neck 3 — the belly is the breadth, as Hausknecht's „17–25 × 7–12 µm".
$con = measure_rows([['scale' => $scale, 'measures' => [$cystid('conocybe', [200, 80, 50, 30])]]], 'Cheilozystiden');
check(measure_summary($con) === ['20 × 8 µm · Köpfchen 5 · Hals 3'], 'one Conocybe cystidium: length × belly, head and neck: ' . (measure_summary($con)[0] ?? ''));
// The paraphysis (18.11.2026): bent length 40, breadth 3, head 6 — „40 × 3 µm · Kopf 6", the bent length marked.
$para = static fn(array $px) => ['type' => 'rect', 'points' => [[0, 0], [400, 0], [0, -60], [0, 60]],
    'width' => 1000, 'label' => 'Paraphysen', 'profile' => 'paraphysis', 'inner' => [
        ['type' => 'path', 'points' => [[0, 0], [$px[0] / 2, 0], [$px[0], 0]]],
        ['type' => 'line', 'points' => [[0, 0], [0, $px[1]]]],
        ['type' => 'line', 'points' => [[0, 0], [$px[2], 0]]],
    ]];
$par = measure_rows([['scale' => $scale, 'measures' => [$para([400, 30, 60])]]], 'Paraphysen');
check(count($par) === 1 && $near($par[0]['L'], 40.0) && $near((float) $par[0]['B'], 3.0) && $par[0]['bent'] === true
      && $near($par[0]['parts']['K'], 6.0), 'a paraphysis: its bent length, its breadth, its head');
check(measure_summary($par) === ['40 × 3 µm · Kopf 6'], 'one paraphysis: length × breadth, and the head: ' . (measure_summary($par)[0] ?? ''));
$pars = measure_rows([['scale' => $scale, 'measures' => [$para([400, 30, 60]), $para([500, 20, 50])]]], 'Paraphysen');
check(measure_summary($pars)[1] === 'Kopf 5 – 6 µm', 'two: the head\'s span under the first line: ' . (measure_summary($pars)[1] ?? ''));
check(measure_frame_values(['type' => 'rect', 'points' => [[0, 0], [10, 0], [0, -5], [0, 5]], 'width' => 1000, 'profile' => 'hebeloma',
    'inner' => [['type' => 'line', 'points' => [[0, 0], [0, 0]]]]], $scale) === null, 'without a length there is no cystidium');

echo "The range notation:\n";
// Ten values 1 … 10: decile 1 at 0.9 between 1 and 2, decile 9 at 8.1 between 9 and 10.
check(measure_range(range(1, 10)) === '(1) 1,9 – 9,1 (10)', 'from ten measures the deciles and the extremes');
// Ten where the outer two equal their neighbours: the parentheses would repeat the bounds.
check(measure_range([5, 5, 6, 6, 6, 6, 6, 6, 7, 7]) === '5 – 7', 'a parenthesis that repeats its bound goes');
check(measure_range([1, 2, 2, 2, 2, 2, 2, 2, 2, 9]) === '(1) 1,9 – 2,7 (9)', 'only where it differs it stays');
// Six spores — the case the maintainer found (07.10.2026): no deciles, the span.
check(measure_range([9.5, 9.6, 9.8, 9.9, 10, 10]) === '9,5 – 10', 'below ten the plain span');
check(measure_range([1.6, 1.6, 1.64]) === '1,6', 'a span that rounds to one number is that number');
check(measure_number(9.0, 1) === '9' && measure_number(1.678, 2) === '1,68' && measure_number(9.05, 1) === '9,1', 'numbers as the browser writes them');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed FAILED.\n";
exit($failed === 0 ? 0 : 1);
