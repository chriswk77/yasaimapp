<?php
/**
 * Tests the rain picture (includes/rain.php).
 *
 *   php tests/rain.php
 *
 * WHAT CAN GO WRONG UNSEEN: the grid's geometry (a picture a few
 * kilometres off looks right at a glance and is wrong at every find), the
 * window (one day off and the key names the wrong fortnight), the DWD's
 * date convention (the file of the 24th holds the 23rd), the missing-data
 * flag, and the hand-written PNG. The last block reads one real day file
 * of the DWD; without network it is skipped, not failed.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/rain.php';
require_once __DIR__ . '/../includes/map_layers.php';

$failed = 0;
$skipped = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}
$near = static fn(float $a, float $b): bool => abs($a - $b) < 0.05;

echo "The grid's corners (published by the DWD for the 900 × 900 grid):\n";
foreach (['south-west' => [3.5889, 46.9526, 0, 0], 'north-west' => [2.0715, 54.5877, 0, 900],
          'north-east' => [15.7208, 54.7405, 900, 900], 'south-east' => [14.6209, 47.0705, 900, 0]] as $name => [$lon, $lat, $x, $y]) {
    [$gx, $gy] = rain_grid_point($lon, $lat);
    check($near($gx, $x) && $near($gy, $y), sprintf('%s lands on %d/%d (%.2f/%.2f)', $name, $x, $y, $gx, $gy));
}
foreach (['west' => 2.0715, 'south' => 46.9526, 'east' => 15.7208, 'north' => 54.7405] as $side => $edge) {
    $inside = in_array($side, ['west', 'south'], true) ? RAIN_BOX[$side] <= $edge : RAIN_BOX[$side] >= $edge;
    check($inside, "the picture's box holds the grid to the $side");
}

echo "The window and the files:\n";
$window = rain_window(new DateTimeImmutable('2026-10-01'));
check(count($window) === 14 && $window[0] === '2026-09-10' && end($window) === '2026-09-23',
    'on the 1st of October: the 10th to the 23rd of September (' . $window[0] . ' – ' . end($window) . ')');
check(rain_file_name('2026-09-23') === 'raa01-sf_10000-2609240550-dwd---bin.gz', 'the rain of the 23rd is the file of the 24th, 05:50');
check(rain_file_name('2026-12-31') === 'raa01-sf_10000-2701010550-dwd---bin.gz', 'across the turn of the year');

echo "Summing:\n";
$cells = RADOLAN_SIZE * RADOLAN_SIZE;
$grid = static function (array $set) use ($cells): string {
    $g = str_repeat(pack('v', 0), $cells);
    foreach ($set as $cell => $value) {
        $g = substr_replace($g, pack('v', $value), $cell * 2, 2);
    }
    return $g;
};
$a = $grid([0 => 12, 1 => 0x2000, 5 => 0x1000 | 7]);
$b = $grid([0 => 30, 1 => 0x2000, 2 => 0x2000]);
$sum = rain_sum([$a, $b, $b, $b], 4);
check($sum[0] === 12 + 3 * 30, 'tenths of a millimetre add up (102)');
check($sum[1] === -1, 'a cell missing on more than two days has no value');
check($sum[2] === -1, 'and one missing on three days of four too');
check($sum[5] === 7, 'flags above the twelve value bits are not rain');
$short = rain_sum([$a], 4);
check($short[0] === -1, 'three of four days not there: no value rather than too little rain');

echo "The picture:\n";
$h = rain_height();
check($h > 1000 && $h < 1200, "the box is $h pixels high at " . RAIN_WIDTH . ' wide');
$png = rain_png("\0" . "\0\1\2\3\4\5\6\7" . "\0" . "\7\6\5\4\3\2\1\0", 8, 2);
$size = @getimagesizefromstring($png);
check(is_array($size) && $size[0] === 8 && $size[1] === 2 && $size['mime'] === 'image/png', 'the hand-written PNG is a PNG of 8 × 2');
if (function_exists('imagecreatefromstring')) {
    $img = @imagecreatefromstring($png);
    check($img !== false && imagecolorsforindex($img, imagecolorat($img, 1, 0))['red'] === hexdec(substr(RAIN_COLOURS[0], 1, 2)),
        'and its first step has the first colour');
}

echo "The registry speaks the same box and steps:\n";
$entry = array_values(array_filter(map_layers_for_js(), static fn($l) => $l['id'] === 'rain'))[0] ?? null;
check($entry !== null && $entry['box'] === [RAIN_BOX['west'], RAIN_BOX['south'], RAIN_BOX['east'], RAIN_BOX['north']], 'the box');
check($entry !== null && count($entry['legend']) === count(RAIN_COLOURS) && $entry['legend'][0]['color'] === RAIN_COLOURS[0],
    'a legend line per colour, built from RAIN_STEPS');

echo "A real day of the DWD:\n";
$curl = curl_init(RAIN_SOURCE . rain_file_name((new DateTimeImmutable('-3 days'))->format('Y-m-d')));
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'YASAIMAPP rain test']);
$body = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);
if (!is_string($body) || $status === 0) {
    $skipped++;
    echo "  ----  skipped (no network)\n";
} else {
    $file = sys_get_temp_dir() . '/yasaimapp-rain-test.gz';
    file_put_contents($file, $body);
    $real = rain_grid($file);
    @unlink($file);
    check($status === 200 && $real !== null, 'it reads as a grid of 900 × 900');
    if ($real !== null) {
        $values = unpack('v*', $real);
        $missing = count(array_filter($values, static fn($v) => $v & 0x2000));
        check($missing > 0 && $missing < count($values) / 2, "some cells outside the radars' reach, most inside ($missing)");
    }
}

echo $failed ? "\n$failed check(s) failed.\n" : "\nAll good." . ($skipped ? " ($skipped skipped)" : '') . "\n";
exit($failed ? 1 : 0);
