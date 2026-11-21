<?php
/**
 * The rain of the week before last and the week before that — one picture
 * of Germany, made once a day.
 *
 * WHY THAT WINDOW (01.10.2026): „Was man für die Pilzsuche
 * braucht, ist nicht der aktuelle Regen, ist nicht der Regen eine Woche
 * zurück, aber es ist der Regen, der vorletzte und vorvorletzte Woche
 * gefallen ist. Da Pilze circa eine Woche brauchen, um zu wachsen." So the
 * fourteen days that END A WEEK AGO, summed: today the 1st, the rain of
 * the 10th to the 23rd.
 *
 * THE DATA is the Deutscher Wetterdienst's RADOLAN SF: the radar's rain of
 * 24 hours, adjusted to the rain gauges of its stations, on a grid of
 * 900 × 900 square kilometres over Germany and its rim, in tenths of a
 * millimetre. Open data (CC BY 4.0), a file of ~300 KB per day:
 *   raa01-sf_10000-YYMMDD0550-dwd---bin.gz
 * The one of 05:50 UTC sums the DWD's day — 05:50 UTC the day before to
 * 05:50 that morning —, so the file dated the 24th is the rain of the 23rd.
 *
 * THE PICTURE is made here and not in the browser: one PNG in the map's
 * own projection (Web Mercator) over the grid's box, which the map lays on
 * as it is (an image source; Leaflet's imageOverlay). The grid is a polar
 * stereographic one, so every pixel of the picture asks which square
 * kilometre it falls on. Written by hand (rain_png) rather than with GD:
 * a palette of eight colours and zlib is all a PNG needs, and nothing has
 * to be installed on the host for it.
 *
 * Kept on the server: the day files of the last weeks (data/rain/days/),
 * the picture and its description (data/rain/rain.png, rain.json). The
 * cron job (rain_run, in cronjobs/daily.php) fetches what is missing and draws
 * anew when the window moved; pages/rain.php hands both out.
 */

declare(strict_types=1);

const RAIN_SOURCE = 'https://opendata.dwd.de/climate_environment/CDC/grids_germany/daily/radolan/recent/bin/';
const RAIN_DIR = PROJECT_PATH . '/data/rain';

/** Fourteen days summed, ending seven days before today. */
const RAIN_DAYS = 14;
const RAIN_GAP = 7;

/* THE RADOLAN NATIONAL GRID: polar stereographic on a sphere of 6370.04 km,
   true at 60° N, centred on 10° E; 900 × 900 cells of 1 km, the first row
   the southernmost, its lower left corner at these coordinates (km). */
const RADOLAN_SIZE = 900;
const RADOLAN_EARTH = 6370.04;
const RADOLAN_X0 = -523.4622;
const RADOLAN_Y0 = -4658.645;

/* THE PICTURE'S BOX in degrees — the grid's corners, rounded outward, so the
   whole grid fits. The registry (map_layers.php 'rain') names the same box;
   tests/rain.php holds the two together. */
const RAIN_BOX = ['west' => 2.0, 'south' => 46.9, 'east' => 15.8, 'north' => 54.8];
const RAIN_WIDTH = 1200;

/* THE STEPS in millimetres over the fourteen days, and their colours — a
   depiction (rain), not a surface. Dry is a pale sand, so a dry region is
   told and not left blank; then lighter to darker blue. The legend in the
   registry spells the same steps. */
const RAIN_STEPS = [5, 15, 30, 50, 80, 120];
const RAIN_COLOURS = ['#e9d8ae', '#cfe7f3', '#9dcde9', '#5fa8d8', '#2f7fc1', '#1d559b', '#3a2a86'];

/** The day files' folder. */
function rain_days_dir(): string
{
    return RAIN_DIR . '/days';
}

/**
 * The days of the window: the rain days from RAIN_GAP + RAIN_DAYS days ago
 * to RAIN_GAP + 1 days ago, oldest first, as Y-m-d.
 *
 * @return string[]
 */
function rain_window(DateTimeImmutable $today): array
{
    $days = [];
    for ($back = RAIN_GAP + RAIN_DAYS; $back > RAIN_GAP; $back--) {
        $days[] = $today->modify("-$back days")->format('Y-m-d');
    }
    return $days;
}

/** The file that holds a rain day: the one of 05:50 UTC the morning after. */
function rain_file_name(string $day): string
{
    $morning = (new DateTimeImmutable($day . ' 00:00:00', new DateTimeZone('UTC')))->modify('+1 day');
    return 'raa01-sf_10000-' . $morning->format('ymd') . '0550-dwd---bin.gz';
}

/**
 * A day file, fetched once and kept. Null when the DWD has none (yet) or
 * the line failed — the next run asks again.
 */
function rain_fetch(string $day): ?string
{
    $path = rain_days_dir() . '/' . rain_file_name($day);
    if (is_file($path) && filesize($path) > 1000) {
        return $path;
    }
    @mkdir(rain_days_dir(), 0775, true);
    $curl = curl_init(RAIN_SOURCE . rain_file_name($day));
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_USERAGENT      => 'YASAIMAPP rain map',
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($status !== 200 || !is_string($body) || strlen($body) < 1000) {
        return null;
    }
    file_put_contents($path . '.part', $body, LOCK_EX);
    rename($path . '.part', $path);
    return $path;
}

/**
 * The grid of a day file: 900 × 900 little-endian 16-bit words, the first
 * row the southernmost. The ASCII header ends at the first ETX (0x03).
 * Null when the file is not what it should be.
 */
function rain_grid(string $path): ?string
{
    $raw = @gzdecode((string) file_get_contents($path));
    if (!is_string($raw)) {
        return null;
    }
    $end = strpos($raw, "\x03");
    if ($end === false || !str_contains(substr($raw, 0, $end), 'GP 900x 900')) {
        return null;
    }
    $grid = substr($raw, $end + 1, RADOLAN_SIZE * RADOLAN_SIZE * 2);
    return strlen($grid) === RADOLAN_SIZE * RADOLAN_SIZE * 2 ? $grid : null;
}

/**
 * The sum over the grids, in tenths of a millimetre, per cell; -1 where
 * more than two of the days had no data (bit 0x2000 — outside the radars'
 * reach). A day the DWD lacks counts as missing for every cell.
 *
 * @param string[] $grids
 */
function rain_sum(array $grids, int $days): SplFixedArray
{
    $cells = RADOLAN_SIZE * RADOLAN_SIZE;
    $sum = new SplFixedArray($cells);
    $gaps = new SplFixedArray($cells);
    for ($c = 0; $c < $cells; $c++) {
        $sum[$c] = 0;
        $gaps[$c] = $days - count($grids);
    }
    foreach ($grids as $grid) {
        // Row by row: one unpack of the whole grid would hold 810,000 PHP
        // values at once, a row holds 900.
        for ($row = 0; $row < RADOLAN_SIZE; $row++) {
            $values = unpack('v' . RADOLAN_SIZE, $grid, $row * RADOLAN_SIZE * 2);
            $base = $row * RADOLAN_SIZE;
            foreach ($values as $k => $v) {
                $c = $base + $k - 1;
                if ($v & 0x2000) {
                    $gaps[$c] = $gaps[$c] + 1;
                } else {
                    $sum[$c] = $sum[$c] + ($v & 0x0FFF);
                }
            }
        }
    }
    for ($c = 0; $c < $cells; $c++) {
        if ($gaps[$c] > 2) {
            $sum[$c] = -1;
        }
    }
    return $sum;
}

/** Degrees to the RADOLAN grid: [column, row] as floats, row 0 the southernmost. */
function rain_grid_point(float $lon, float $lat): array
{
    $phi = deg2rad($lat);
    $lambda = deg2rad($lon - 10.0);
    $m = (1 + sin(deg2rad(60.0))) / (1 + sin($phi));
    $x = RADOLAN_EARTH * $m * cos($phi) * sin($lambda);
    $y = -RADOLAN_EARTH * $m * cos($phi) * cos($lambda);
    return [$x - RADOLAN_X0, $y - RADOLAN_Y0];
}

/** The picture's height in pixels: the box's Mercator aspect at RAIN_WIDTH. */
function rain_height(): int
{
    $merc = static fn(float $lat): float => log(tan(M_PI / 4 + deg2rad($lat) / 2));
    $w = deg2rad(RAIN_BOX['east'] - RAIN_BOX['west']);
    $h = $merc(RAIN_BOX['north']) - $merc(RAIN_BOX['south']);
    return (int) round(RAIN_WIDTH * $h / $w);
}

/**
 * The picture: palette index per pixel, row by row from the north — 0
 * transparent, 1 … 7 the steps.
 */
function rain_raster(SplFixedArray $sum): string
{
    $width = RAIN_WIDTH;
    $height = rain_height();
    $merc = static fn(float $lat): float => log(tan(M_PI / 4 + deg2rad($lat) / 2));
    $top = $merc(RAIN_BOX['north']);
    $bottom = $merc(RAIN_BOX['south']);
    $steps = array_map(static fn(int $mm): int => $mm * 10, RAIN_STEPS);   // tenths of a millimetre

    // The column's longitude is the same down the picture, the row's
    // latitude the same across it: worked out once each.
    $lons = [];
    for ($x = 0; $x < $width; $x++) {
        $lons[$x] = RAIN_BOX['west'] + ($x + 0.5) / $width * (RAIN_BOX['east'] - RAIN_BOX['west']);
    }
    $rows = '';
    for ($y = 0; $y < $height; $y++) {
        $lat = rad2deg(atan(sinh($top - ($y + 0.5) / $height * ($top - $bottom))));
        $line = "\0";                                         // PNG filter: none
        for ($x = 0; $x < $width; $x++) {
            [$gx, $gy] = rain_grid_point($lons[$x], $lat);
            $i = (int) floor($gx);
            $j = (int) floor($gy);
            if ($i < 0 || $j < 0 || $i >= RADOLAN_SIZE || $j >= RADOLAN_SIZE) {
                $line .= "\0";
                continue;
            }
            $v = $sum[$j * RADOLAN_SIZE + $i];
            if ($v < 0) {
                $line .= "\0";
                continue;
            }
            $index = 1;
            foreach ($steps as $limit) {
                if ($v < $limit) {
                    break;
                }
                $index++;
            }
            $line .= chr($index);
        }
        $rows .= $line;
    }
    return $rows;
}

/** An indexed PNG from filtered rows: the palette, index 0 transparent. */
function rain_png(string $rows, int $width, int $height): string
{
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };
    $palette = "\0\0\0";
    foreach (RAIN_COLOURS as $hex) {
        $palette .= pack('C3', hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
    }
    $alpha = "\0" . str_repeat("\xFF", count(RAIN_COLOURS));
    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 3, 0, 0, 0))
        . $chunk('PLTE', $palette)
        . $chunk('tRNS', $alpha)
        . $chunk('IDAT', (string) gzcompress($rows, 9))
        . $chunk('IEND', '');
}

/** The picture's description as last written, or null. */
function rain_meta(): ?array
{
    $meta = json_decode((string) @file_get_contents(RAIN_DIR . '/rain.json'), true);
    return is_array($meta) ? $meta : null;
}

/**
 * The cron job's body: fetch what the window needs, draw when it moved or
 * a day came in, drop day files older than three windows.
 *
 * @return array{parts: string[], open: int}
 */
function rain_run(float $deadline, ?DateTimeImmutable $today = null): array
{
    $today ??= new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin'));
    $window = rain_window($today);
    $paths = [];
    $fetched = 0;
    $open = 0;
    foreach ($window as $day) {
        if (microtime(true) >= $deadline) {
            $open++;
            continue;
        }
        $had = is_file(rain_days_dir() . '/' . rain_file_name($day));
        $path = rain_fetch($day);
        if ($path !== null) {
            $paths[$day] = $path;
            $fetched += $had ? 0 : 1;
        }
    }

    $meta = rain_meta();
    $parts = [];
    if ($fetched > 0) {
        $parts[] = "$fetched Tag" . ($fetched === 1 ? '' : 'e') . ' geholt';
    }
    $sameWindow = $meta !== null && ($meta['from'] ?? '') === $window[0]
        && ($meta['to'] ?? '') === end($window) && ($meta['days'] ?? 0) === count($paths);
    if ($open === 0 && ($fetched > 0 || !$sameWindow) && $paths !== []) {
        $grids = array_filter(array_map('rain_grid', $paths));
        $sum = rain_sum(array_values($grids), RAIN_DAYS);
        $height = rain_height();
        @mkdir(RAIN_DIR, 0775, true);
        file_put_contents(RAIN_DIR . '/rain.png.part', rain_png(rain_raster($sum), RAIN_WIDTH, $height), LOCK_EX);
        rename(RAIN_DIR . '/rain.png.part', RAIN_DIR . '/rain.png');
        file_put_contents(RAIN_DIR . '/rain.json', json_encode([
            'from' => $window[0],
            'to' => end($window),
            'days' => count($grids),
            'made' => date('c'),
        ]), LOCK_EX);
        $parts[] = 'Bild ' . date('d.m.', strtotime($window[0])) . '–' . date('d.m.', strtotime(end($window))) . ' neu';
    }

    // Day files older than three windows go: nothing reads them any more.
    $oldest = $today->modify('-' . (3 * RAIN_DAYS + RAIN_GAP) . ' days')->format('ymd');
    foreach (glob(rain_days_dir() . '/raa01-sf_10000-*-dwd---bin.gz') ?: [] as $file) {
        if (preg_match('/raa01-sf_10000-(\d{6})/', basename($file), $m) && $m[1] < $oldest) {
            @unlink($file);
        }
    }
    return ['parts' => $parts, 'open' => $open];
}
