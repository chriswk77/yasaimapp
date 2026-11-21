<?php
/**
 * Test images with real EXIF — for testing the reader in JavaScript.
 *
 *   php tests/exifimages.php <directory>
 *
 * WHAT THIS IS GOOD FOR. assets/js/exif.js reads EXIF by hand: byte order,
 * directory pointers, field types, fractions. This is the kind of code
 * that looks plausible and is off anyway — a pointer relative to the
 * wrong start, and out comes a number that catches nobody's eye.
 *
 * That is why testing is not against my own expectation but against a
 * foreign implementation: the images are built here, and PHP's
 * exif_read_data() says what is in them. Only when both read the same is
 * it right — the same route as with the BfN lists, which were cross-read
 * against openpyxl.
 *
 * Output is one JSON line with what PHP has read. The images land in the
 * given directory.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// The directory must be given. Without the argument this script would, on
// an accidental call — say from a loop over all tests — write images
// somewhere and look as if it had tested.
$target = $argv[1] ?? '';
if ($target === '') {
    fwrite(STDERR, "Usage: php tests/exifimages.php <directory>\n"
        . "Creates the test images for tests/exif.mjs; that one calls this script itself.\n");
    exit(2);
}
if (!is_dir($target)) {
    mkdir($target, 0777, true);
}

/**
 * Build an EXIF section.
 *
 * The layout is fixed so the pointers can be recomputed by hand:
 *
 *   0   byte order ("II" or "MM"), 0x002A, pointer to IFD0
 *   8   IFD0 with five entries
 *   74  strings of IFD0
 *   94  Exif directory (capture time)
 *   132 GPS directory
 *
 * All pointers are relative to the start of the TIFF header, not to the
 * start of the file. That is exactly the spot where readers like to grab
 * wrong.
 */
function exif_section(bool $littleEndian, array $data): string
{
    $fmt16 = $littleEndian ? 'v' : 'n';   // 16 Bit
    $fmt32 = $littleEndian ? 'V' : 'N';   // 32 Bit

    $short = static fn(int $v): string => pack($fmt16, $v);
    $long = static fn(int $v): string => pack($fmt32, $v);

    $make = $data['make'] . "\0";
    $model = $data['model'] . "\0";
    $time = $data['time'] . "\0";

    // Fixed places so everything can be recomputed.
    $posMake  = 74;
    $posModel = $posMake + strlen($make);
    $posExif   = $posModel + strlen($model);
    $posExifData = $posExif + 2 + 12 + 4;
    $posGps    = $posExifData + strlen($time);
    $posGpsData = $posGps + 2 + 4 * 12 + 4;
    $posLat = $posGpsData;
    $posLon = $posLat + 24;

    /** A directory entry. Up to four bytes, the data sits in the entry itself. */
    $entry = static function (int $tag, int $type, int $count, string $content) use ($short, $long): string {
        $raw = $short($tag) . $short($type) . $long($count);
        return $raw . str_pad(substr($content, 0, 4), 4, "\0");
    };

    // --- IFD0 -------------------------------------------------------------
    $ifd0 = $short(5)
        . $entry(0x010F, 2, strlen($make),  $long($posMake))
        . $entry(0x0110, 2, strlen($model), $long($posModel))
        // Orientation: SHORT, one value, sits in the entry. Left-aligned
        // with big endian — which is why this cannot go through $long.
        . $entry(0x0112, 3, 1, $littleEndian ? $short($data['orientation']) . "\0\0" : $short($data['orientation']))
        . $entry(0x8769, 4, 1, $long($posExif))
        . $entry(0x8825, 4, 1, $long($posGps))
        . $long(0);

    // --- Exif directory ----------------------------------------------------
    $ifdExif = $short(1)
        . $entry(0x9003, 2, strlen($time), $long($posExifData))
        . $long(0);

    // --- GPS directory -----------------------------------------------------
    $fraction = static fn(int $num, int $den): string => $long($num) . $long($den);
    $ifdGps = $short(4)
        . $entry(0x0001, 2, 2, $data['lat_ref'] . "\0")
        . $entry(0x0002, 5, 3, $long($posLat))
        . $entry(0x0003, 2, 2, $data['lon_ref'] . "\0")
        . $entry(0x0004, 5, 3, $long($posLon))
        . $long(0);

    $gpsData = $fraction($data['lat'][0], 1) . $fraction($data['lat'][1], 1) . $fraction($data['lat'][2], 10)
              . $fraction($data['lon'][0], 1) . $fraction($data['lon'][1], 1) . $fraction($data['lon'][2], 10);

    $tiff = ($littleEndian ? 'II' : 'MM') . $short(0x002A) . $long(8)
          . $ifd0 . $make . $model . $ifdExif . $time . $ifdGps . $gpsData;

    // Recompute that the pointers are right — a wrong test image would
    // otherwise convict a correct reader.
    $expected = ['Make' => $posMake, 'Exif' => $posExif, 'GPS' => $posGps];
    $actual = ['Make' => 74, 'Exif' => 74 + strlen($make) + strlen($model), 'GPS' => $posGps];
    if ($expected['Exif'] !== $actual['Exif']) {
        fwrite(STDERR, "The pointers in the test image are wrong.\n");
        exit(1);
    }

    $payload = "Exif\0\0" . $tiff;
    return "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
}

/** A JPEG with the EXIF section inserted. */
function image_write(string $path, int $width, int $height, ?string $section): void
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 90, 140, 70));
    imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), 40, 30, imagecolorallocate($image, 220, 210, 120));

    ob_start();
    imagejpeg($image, null, 85);
    $jpeg = (string) ob_get_clean();

    if ($section !== null) {
        // Directly after SOI. That is where APP1 belongs, and where the reader looks.
        $jpeg = substr($jpeg, 0, 2) . $section . substr($jpeg, 2);
    }

    file_put_contents($path, $jpeg);
}

$cases = [
    'small' => [
        'file' => 'exif-intel.jpg',
        'small' => true,
        'fields' => [
            'make' => 'Apple', 'model' => 'iPhone 15 Pro', 'time' => '2026:08:09 14:23:07',
            'orientation' => 6,
            'lat' => [51, 30, 264], 'lat_ref' => 'N',
            'lon' => [7, 28, 120],  'lon_ref' => 'E',
        ],
    ],
    'large' => [
        'file' => 'exif-motorola.jpg',
        'small' => false,
        'fields' => [
            'make' => 'NIKON', 'model' => 'NIKON Z6', 'time' => '2024:10:01 07:05:00',
            'orientation' => 1,
            'lat' => [48, 8, 100], 'lat_ref' => 'S',
            'lon' => [11, 34, 50], 'lon_ref' => 'W',
        ],
    ],
];

$report = [];
foreach ($cases as $name => $case) {
    $path = $target . '/' . $case['file'];
    image_write($path, 640, 480, exif_section($case['small'], $case['fields']));

    // And now the foreign opinion on it.
    $read = @exif_read_data($path, null, true);
    $gps = $read['GPS'] ?? [];

    $toDegrees = static function ($parts, $direction) {
        if (!is_array($parts) || count($parts) < 3) return null;
        $toNumber = static function ($fraction) {
            if (!is_string($fraction) || !str_contains($fraction, '/')) return (float) $fraction;
            [$num, $den] = explode('/', $fraction);
            return (float) $den === 0.0 ? 0.0 : (float) $num / (float) $den;
        };
        $value = $toNumber($parts[0]) + $toNumber($parts[1]) / 60 + $toNumber($parts[2]) / 3600;
        if ($direction === 'S' || $direction === 'W') $value = -$value;
        return round($value, 6);
    };

    $report[$name] = [
        'file'       => $case['file'],
        'make'        => $read['IFD0']['Make'] ?? null,
        'model'       => $read['IFD0']['Model'] ?? null,
        'orientation' => $read['IFD0']['Orientation'] ?? null,
        'time'        => $read['EXIF']['DateTimeOriginal'] ?? null,
        'lat'         => $toDegrees($gps['GPSLatitude'] ?? null,  $gps['GPSLatitudeRef'] ?? ''),
        'lon'         => $toDegrees($gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? ''),
    ];
}

// An image with no EXIF at all — the most common case of all.
image_write($target . '/exif-none.jpg', 800, 600, null);
$report['none'] = ['file' => 'exif-none.jpg'];

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
