<?php
/**
 * Tests the fence around every picture the server hands on
 * (includes/picture_fence.php, cloud_deliver() in includes/cloud.php).
 *
 *   php tests/picture_fence.php
 *
 * WHY: a picture from somebody else's cloud goes out under our address,
 * with the viewer's session. A file that only CALLS itself a picture — a
 * page saved as „.jpg", an SVG with script — must never reach a browser
 * as something it would run, whatever the provider says it is. That is
 * a promise about headers, and headers are only seen over HTTP: so the
 * second half starts a server of its own on a free port, plays the
 * provider on one address and the delivery on another, and reads what
 * really goes out. Nothing of this app's database or any cloud.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('PROJECT_PATH', dirname(__DIR__));
require_once __DIR__ . '/../includes/cloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nThe picture fence — what may leave the server as a picture\n\n";

// --- by the first bytes --------------------------------------------------------

$jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00";
check(picture_type($jpeg) === 'image/jpeg', 'JPEG by its first bytes');
check(picture_type("\x89PNG\r\n\x1A\n\x00\x00\x00\x0DIHDR") === 'image/png', 'PNG');
check(picture_type('GIF89a' . "\x01\x00\x01\x00") === 'image/gif', 'GIF');
check(picture_type("RIFF\x24\x00\x00\x00WEBPVP8 ") === 'image/webp', 'WebP');
check(picture_type("\x00\x00\x00\x1CftypavifXXXX") === 'image/avif', 'AVIF');
check(picture_type("\x00\x00\x00\x18ftypheicXXXX") === 'image/heic', 'HEIC, as a phone writes it');
check(picture_type("II*\x00\x08\x00\x00\x00") === 'image/tiff', 'TIFF');
check(picture_type('<!DOCTYPE html><script>') === null, 'a page that calls itself .jpg is no picture');
check(picture_type('<svg xmlns="http://www.w3.org/2000/svg"><script>') === null
    && picture_type('<?xml version="1.0"?><svg') === null, 'nor is an SVG — it can carry script');
check(picture_type("\x00\x00\x00\x18ftypisomXXXX") === null, 'nor a video in the same box format');
check(picture_type('') === null, 'nor nothing');

// --- what GD may decode ------------------------------------------------------

if (function_exists('imagecreatetruecolor')) {
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($image);
    $small = (string) ob_get_clean();
    check(picture_decodable($small), 'a small real picture may be decoded');
    // A JPEG head that declares 60,000 × 60,000 pixels in a few dozen bytes.
    $bomb = "\xFF\xD8\xFF\xC0\x00\x11\x08" . pack('n', 60000) . pack('n', 60000)
        . "\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01\xFF\xD9";
    check(!picture_decodable($bomb), 'a few bytes that declare 3.6 billion pixels are not (PICTURE_DECODE_PIXELS)');
    check(!picture_decodable('<html>'), 'nor what is no picture at all');
} else {
    echo "  --    GD missing, the decoding checks are skipped\n";
}

// --- the coordinates taken out --------------------------------------------------

/**
 * A JPEG with an EXIF block as a phone writes it: IFD0 with the
 * orientation and the pointer to the GPS block, the GPS block with
 * latitude and longitude — written out by hand, little-endian, because
 * PHP can read EXIF but not write it.
 */
function jpeg_with_gps(string $pixels): string
{
    $le16 = fn(int $v) => pack('v', $v);
    $le32 = fn(int $v) => pack('V', $v);
    $entry = fn(int $tag, int $type, int $count, string $value) => $le16($tag) . $le16($type) . $le32($count) . str_pad($value, 4, "\0");
    // TIFF head (8) + IFD0: count (2) + 2 entries (24) + next (4) = 38 → GPS IFD at 38.
    $ifd0 = $le16(2) . $entry(0x0112, 3, 1, $le16(6)) . $entry(0x8825, 4, 1, $le32(38)) . $le32(0);
    // GPS IFD: count (2) + 4 entries (48) + next (4) = 54 → the values from 38 + 54 = 92.
    $lat = $le32(50) . $le32(1) . $le32(6) . $le32(1) . $le32(28125) . $le32(1000);
    $lon = $le32(8) . $le32(1) . $le32(12) . $le32(1) . $le32(54321) . $le32(1000);
    $gps = $le16(4) . $entry(1, 2, 2, "N\0") . $entry(2, 5, 3, $le32(92)) . $entry(3, 2, 2, "E\0") . $entry(4, 5, 3, $le32(116)) . $le32(0);
    $tiff = 'II' . $le16(42) . $le32(8) . $ifd0 . $gps . $lat . $lon;
    $app1 = "Exif\0\0" . $tiff;
    $xmp = "http://ns.adobe.com/xap/1.0/\0" . '<x:xmpmeta><rdf:Description exif:GPSLatitude="50,6.28125N" exif:GPSLongitude="8,12.54321E">'
        . '<exif:GPSAltitude>412/1</exif:GPSAltitude><dc:title>Steinpilz</dc:title></rdf:Description></x:xmpmeta>';
    return "\xFF\xD8" . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1
        . "\xFF\xE1" . pack('n', strlen($xmp) + 2) . $xmp . substr($pixels, 2);
}

if (function_exists('imagecreatetruecolor') && function_exists('exif_read_data')) {
    $read = fn(string $b) => @exif_read_data('data://image/jpeg;base64,' . base64_encode($b), null, true) ?: [];
    $with = jpeg_with_gps($small);
    $before = $read($with);
    check(isset($before['GPS']['GPSLatitude'], $before['GPS']['GPSLongitude']), 'the hand-made picture carries GPS as a phone\'s does');
    $without = picture_without_gps($with);
    $after = $read($without);
    check(!isset($after['GPS']['GPSLatitude']) && !isset($after['GPS']['GPSLongitude']) && !isset($after['GPS']['GPSLatitudeRef']),
        'without it, no reader finds a coordinate in EXIF');
    check(!str_contains($without, pack('V', 28125)) && !str_contains($without, pack('V', 54321)), 'and the numbers are gone from the bytes, not only from the index');
    check(($after['IFD0']['Orientation'] ?? null) === 6, 'the orientation stays — a phone\'s picture would otherwise lie on its side');
    check(!str_contains($without, '50,6.28125N') && !str_contains($without, '412/1') && str_contains($without, 'Steinpilz'),
        'the XMP coordinates become spaces, the rest of XMP stays');
    check(strlen($without) === strlen($with), 'every byte keeps its place, so the announced length stays true');
    check(@imagecreatefromstring($without) !== false, 'and the picture still opens');
    check(picture_without_gps($small) === $small, 'a picture without GPS stays exactly as it was');
    check(picture_without_gps("\x89PNG\r\n\x1A\nrest") === "\x89PNG\r\n\x1A\nrest", 'what is no JPEG is not touched');
} else {
    echo "  --    GD or EXIF missing, the GPS checks are skipped\n";
}

// --- over HTTP ---------------------------------------------------------------

$scratch = sys_get_temp_dir() . '/picture_fence_' . getmypid();
@mkdir($scratch);
file_put_contents("$scratch/router.php", '<?php
define("PROJECT_PATH", ' . var_export(dirname(__DIR__), true) . ');
require ' . var_export(dirname(__DIR__) . '/includes/cloud.php', true) . ';
$q = $_GET;
if (($q["as"] ?? "") === "provider") {
    // The provider: answers with whatever type and bytes it is told to.
    header("Content-Type: " . $q["type"]);
    echo base64_decode($q["body"]);
    exit;
}
// The delivery: asks the provider above and hands on, as cloud_thumb.php does.
$source = "http://" . $_SERVER["HTTP_HOST"] . "/?as=provider&type=" . rawurlencode($q["type"]) . "&body=" . rawurlencode($q["body"]);
if (!cloud_deliver([["url" => $source, "headers" => ""]], "test", "private, max-age=60", !empty($q["nogps"]))) {
    http_response_code(502);
}
');
$port = 0;
$socket = stream_socket_server('tcp://127.0.0.1:0');
if ($socket !== false) {
    $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
}
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", "$scratch/router.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes,
    $scratch, ['PHP_CLI_SERVER_WORKERS' => '2'] + getenv());

/** What the delivery sends when the provider says $type and hands $body. */
function delivered(int $port, string $type, string $body, bool $withoutGps = false): array
{
    $url = "http://127.0.0.1:$port/?type=" . rawurlencode($type) . '&body=' . rawurlencode(base64_encode($body))
        . ($withoutGps ? '&nogps=1' : '');
    $got = @file_get_contents($url, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]));
    $headers = [];
    foreach ($http_response_header ?? [] as $h) {
        if (str_contains($h, ':')) {
            [$k, $v] = explode(':', $h, 2);
            $headers[strtolower(trim($k))] = trim($v);
        } elseif (preg_match('~ (\d{3}) ~', $h . ' ', $m)) {
            $headers['status'] = (int) $m[1];
        }
    }
    return [$headers, (string) $got];
}

$up = false;
for ($i = 0; $i < 50 && !$up; $i++) {
    usleep(100_000);
    $up = @fsockopen('127.0.0.1', $port) !== false;
}
if (!$up) {
    check(false, 'the test server on port ' . $port . ' answers');
} else {
    $picture = $jpeg . str_repeat("\x00", 200);
    [$h, $body] = delivered($port, 'image/jpeg', $picture);
    check(($h['content-type'] ?? '') === 'image/jpeg' && str_starts_with($h['content-disposition'] ?? '', 'inline')
        && $body === $picture, 'a real JPEG goes out inline, every byte of it');
    check(($h['x-content-type-options'] ?? '') === 'nosniff'
        && str_contains($h['content-security-policy'] ?? '', 'sandbox'), 'with nosniff and in a sandbox');

    [$h] = delivered($port, 'image/jpeg', '<!DOCTYPE html><script>alert(document.cookie)</script>');
    check(($h['content-type'] ?? '') === 'application/octet-stream' && str_starts_with($h['content-disposition'] ?? '', 'attachment'),
        'a page the provider calls image/jpeg goes out as a download, never inline');

    [$h] = delivered($port, 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    check(($h['content-type'] ?? '') === 'application/octet-stream' && str_starts_with($h['content-disposition'] ?? '', 'attachment'),
        'an SVG goes out as a download');

    [$h] = delivered($port, 'image/png', $picture);
    check(($h['content-type'] ?? '') === 'image/jpeg', 'the type that goes out is the bytes\', not the provider\'s word');

    if (isset($with)) {
        [$h, $body] = delivered($port, 'image/jpeg', $with, true);
        check($body === picture_without_gps($with) && ($h['content-type'] ?? '') === 'image/jpeg',
            'streamed for a friend of a hiding archive, the picture arrives without its GPS');
        [$h, $body] = delivered($port, 'image/jpeg', $with);
        check($body === $with, 'for anybody else, as it is');
    }
    [$h] = delivered($port, 'image/png', "\x89PNG\r\n\x1A\n" . str_repeat("\x00", 40), true);
    check(($h['status'] ?? 0) === 502, 'a PNG whose GPS cannot be taken out is refused, so the caller offers the preview');

    [$h] = delivered($port, 'text/plain', 'This link was generated for another IP address');
    check(($h['status'] ?? 0) === 502, 'a refusal in plain text is no picture at all (502)');
}

proc_terminate($server);
proc_close($server);
@unlink("$scratch/router.php");
@rmdir($scratch);

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
