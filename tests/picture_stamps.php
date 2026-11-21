<?php
/**
 * Tests the find's GPS and name inside its pictures (includes/picture_stamps.php).
 *
 *   php tests/picture_stamps.php
 *
 * THE WRITER is held to exiftool's reading where exiftool is installed —
 * the program every photographer checks his files with — and to PHP's own
 * exif_read_data everywhere: a JPEG without EXIF gets a block, a camera's
 * file keeps every entry, its thumbnail and its keywords, its phone GPS is
 * replaced, and not one byte of the picture data changes. THE WORKER runs
 * against pCloud played in memory (tests/fake_pcloud.php) and a voucher
 * photo waiting on this server. Needs migration 0152 for the worker; the
 * test rows and files are removed at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/picture_stamps.php';
require_once __DIR__ . '/../includes/photos.php';
require_once __DIR__ . '/../includes/cloud.php';
require_once __DIR__ . '/fake_pcloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$work = sys_get_temp_dir() . '/picture-stamps-' . getmypid();
@mkdir($work);
$exiftool = trim((string) shell_exec('command -v exiftool 2>/dev/null'));

/** exiftool's reading of a JPEG as JSON (numbers as numbers), or null without exiftool. */
function exiftool_read(string $exiftool, string $work, string $jpeg): ?array
{
    if ($exiftool === '') {
        return null;
    }
    file_put_contents("$work/read.jpg", $jpeg);
    $json = shell_exec(escapeshellcmd($exiftool) . ' -j -n -G1 -a ' . escapeshellarg("$work/read.jpg") . ' 2>&1');
    return json_decode((string) $json, true)[0] ?? null;
}

/** PHP's own reading of the GPS — decimal degrees and metres. */
function php_gps(string $work, string $jpeg): ?array
{
    file_put_contents("$work/php.jpg", $jpeg);
    $e = @exif_read_data("$work/php.jpg", 'GPS');
    if (!$e || !isset($e['GPSLatitude'])) {
        return null;
    }
    $num = static function (string $r): float { [$a, $b] = explode('/', $r) + [1 => 1]; return (float) $a / (float) $b; };
    $deg = static fn(array $d): float => $num($d[0]) + $num($d[1]) / 60 + $num($d[2]) / 3600;
    return [
        'lat' => ($e['GPSLatitudeRef'] === 'S' ? -1 : 1) * $deg($e['GPSLatitude']),
        'lon' => ($e['GPSLongitudeRef'] === 'W' ? -1 : 1) * $deg($e['GPSLongitude']),
        'alt' => isset($e['GPSAltitude']) ? $num($e['GPSAltitude']) : null,
    ];
}

/** Everything from the picture data on — what must never change. Found by
 *  walking the segments: the EXIF block may hold a thumbnail, itself a JPEG
 *  with picture data of its own. */
function picture_data(string $jpeg): string
{
    $pos = 2;
    while ($pos + 4 <= strlen($jpeg) && $jpeg[$pos + 1] !== "\xDA") {
        $pos += 2 + unpack('n', substr($jpeg, $pos + 2, 2))[1];
    }
    return substr($jpeg, $pos);
}

$image = imagecreatetruecolor(64, 48);
imagefilledrectangle($image, 0, 0, 63, 47, imagecolorallocate($image, 120, 90, 40));
ob_start();
imagejpeg($image, null, 90);
$plain = (string) ob_get_clean();

$want = ['lat' => 50.123456, 'lon' => 9.654321, 'alt' => 245.3, 'name' => 'Boletus edulis'];

echo "\nWhat a find wants\n";
$point = ['lat' => '50.1234561', 'lon' => '9.6543209', 'elevation_m' => '245.31', 'title' => ' Boletus  edulis ',
          'extra' => json_encode(['Bestimmungssicherheit' => 'mikroskopisch sicher'])];
check(picture_stamp_wanted($point) === $want, 'place to six places, height to a tenth, the title as its name');
foreach (['unsicher', 'unbestimmt', 'mak. sicher', ''] as $step) {
    check(picture_stamp_wanted(['extra' => json_encode(['Bestimmungssicherheit' => $step])] + $point)['name'] === null,
        "„$step\" is no certain name — only the three „sicher\" steps are");
}
check(picture_stamp_wanted(['extra' => null, 'elevation_m' => null] + $point)['name'] === null
      && picture_stamp_wanted(['elevation_m' => null] + $point)['alt'] === null,
    'without the field no name, without a height no height');
$line = picture_stamp_line($want);
check($line === 'v1|50.123456|9.654321|245.3|Boletus edulis', 'the stamp line: ' . $line);
check(picture_stamp_old_name('fail|' . $line) === 'Boletus edulis'
      && picture_stamp_old_name('v1|1.000000|2.000000||') === null, 'the line gives back the name it wrote');

echo "\nA JPEG without EXIF\n";
$out = jpeg_stamp($plain, $want);
$gps = php_gps($work, $out);
check($gps !== null && abs($gps['lat'] - 50.123456) < 1e-6 && abs($gps['lon'] - 9.654321) < 1e-6
      && abs($gps['alt'] - 245.3) < 1e-6, 'PHP reads the find\'s place and height back');
check(picture_data($out) === picture_data($plain), 'not one byte of the picture data changed');
$x = exiftool_read($exiftool, $work, $out);
if ($x !== null) {
    check(abs($x['GPS:GPSLatitude'] - 50.123456) < 1e-6 && abs($x['GPS:GPSLongitude'] - 9.654321) < 1e-6
          && ($x['GPS:GPSMapDatum'] ?? '') === 'WGS-84', 'exiftool reads the same, datum WGS-84');
    check(($x['XMP-dc:Subject'] ?? null) === 'Boletus edulis' && ($x['XMP-dwc:ScientificName'] ?? null) === 'Boletus edulis',
        'the name as a keyword and as Darwin Core\'s scientific name');
    check(!isset($x['ExifTool:Warning']) && !isset($x['ExifTool:Error']), 'exiftool finds nothing to warn about');
}
check(str_starts_with($out, "\xFF\xD8\xFF\xE0"), 'the JFIF header stays first');

$south = jpeg_stamp($plain, ['lat' => -33.5, 'lon' => -70.25, 'alt' => -12.0, 'name' => null]);
$gps = php_gps($work, $south);
check($gps !== null && abs($gps['lat'] + 33.5) < 1e-6 && abs($gps['lon'] + 70.25) < 1e-6,
    'south and west keep their signs');
check(!str_contains($south, 'http://ns.adobe.com/xap/1.0/'), 'without a name no XMP is written into a file that had none');

try {
    jpeg_stamp('GIF89a…', $want);
    check(false, 'anything not a JPEG is refused');
} catch (RuntimeException) {
    check(true, 'anything not a JPEG is refused');
}

if ($exiftool !== '') {
    echo "\nA camera's file, with a phone's GPS (exiftool builds it)\n";
    file_put_contents("$work/plain.jpg", $plain);
    imagejpeg(imagescale($image, 16, 12), "$work/thumb.jpg", 80);
    shell_exec(escapeshellcmd($exiftool) . ' -q -o ' . escapeshellarg("$work/camera.jpg")
        . ' -Make=OM -Model="OM-1" -Orientation#=6 -DateTimeOriginal="2026:09:01 10:11:12"'
        . ' -GPSLatitude=48.1 -GPSLatitudeRef=N -GPSLongitude=11.5 -GPSLongitudeRef=E'
        . ' -XMP-dc:Subject=Pilze -XMP-dc:Subject=Wald -XMP-dc:Description=Waldrand'
        . ' "-ThumbnailImage<=' . "$work/thumb.jpg" . '" ' . escapeshellarg("$work/plain.jpg") . ' 2>&1');
    $camera = (string) file_get_contents("$work/camera.jpg");
    $thumb = (string) shell_exec(escapeshellcmd($exiftool) . ' -b -ThumbnailImage ' . escapeshellarg("$work/camera.jpg"));
    $out = jpeg_stamp($camera, $want);
    $x = exiftool_read($exiftool, $work, $out);
    check(($x['IFD0:Make'] ?? '') === 'OM' && ($x['IFD0:Model'] ?? '') === 'OM-1' && ($x['IFD0:Orientation'] ?? 0) === 6
          && ($x['ExifIFD:DateTimeOriginal'] ?? '') === '2026:09:01 10:11:12', 'camera, model, turn and time of the shot stay');
    file_put_contents("$work/out.jpg", $out);
    check($thumb !== '' && shell_exec(escapeshellcmd($exiftool) . ' -b -ThumbnailImage ' . escapeshellarg("$work/out.jpg")) === $thumb,
        'the thumbnail behind IFD0 is found where it was, byte for byte');
    check(abs(($x['GPS:GPSLatitude'] ?? 0) - 50.123456) < 1e-6 && abs(($x['GPS:GPSLongitude'] ?? 0) - 9.654321) < 1e-6,
        'the phone\'s GPS is replaced by the find\'s');
    check(($x['XMP-dc:Subject'] ?? null) === ['Pilze', 'Wald', 'Boletus edulis'] && ($x['XMP-dc:Description'] ?? '') === 'Waldrand',
        'the name joins the keywords that were there; the rest of the XMP stays');
    check(!isset($x['ExifTool:Warning']) && picture_data($out) === picture_data($camera), 'no warning, no pixel changed');

    $again = jpeg_stamp($out, ['name' => 'Boletus reticulatus'] + $want, 'Boletus edulis');
    $x = exiftool_read($exiftool, $work, $again);
    check(($x['XMP-dc:Subject'] ?? null) === ['Pilze', 'Wald', 'Boletus reticulatus']
          && ($x['XMP-dwc:ScientificName'] ?? '') === 'Boletus reticulatus',
        'another name under the microscope: the old one goes, the new one comes, the others stay');
    $gone = jpeg_stamp($again, ['name' => null] + $want, 'Boletus reticulatus');
    $x = exiftool_read($exiftool, $work, $gone);
    check(($x['XMP-dc:Subject'] ?? null) === ['Pilze', 'Wald'] && !isset($x['XMP-dwc:ScientificName']),
        'a name no longer certain is taken out again — only ours');
    check(strlen($gone) - strlen($camera) < 2048,
        'written three times, the file differs by ' . (strlen($gone) - strlen($camera)) . ' bytes (the XMP\'s padding goes)');
} else {
    echo "\n  (exiftool not installed — the camera's file is not tested)\n";
}

if (!picture_stamps_ready($pdo)) {
    echo "\n  (migration 0152 not applied — the worker is not tested)\n";
    echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
    exit($failed === 0 ? 0 : 1);
}

echo "\nThe worker, against pCloud played in memory and a photo waiting here\n";
$pdo->exec("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
            VALUES ('pruef-stempel@pruefung.local', '', 'PRUEF', 'Stempel', 'user', NOW())");
$user = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, elevation_m, title, extra, created_at, updated_at)
               VALUES (?, 50.123456, 9.654321, 245.3, "Boletus edulis", ?, NOW(), NOW())')
    ->execute([$user, json_encode(['Bestimmungssicherheit' => 'makroskopisch sicher'])]);
$point = (int) $pdo->lastInsertId();
$directory = user_photo_directory_create($user);
$fileName = bin2hex(random_bytes(16)) . '.jpg';
file_put_contents("$directory/$fileName", $plain);
$pdo->prepare('INSERT INTO map_photos (user_id, point_id, filename, width, height, bytes, created_at)
               VALUES (?, ?, ?, 64, 48, ?, NOW())')->execute([$user, $point, $fileName, strlen($plain)]);
$photo = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, folderid, name, path, size, uploaded_at)
               VALUES (?, ?, "pcloud", "900", "106", "P1.JPG", "x", ?, NOW()),
                      (?, ?, "pcloud", "901", "106", "Liste.pdf", "x", 3, NOW())')
    ->execute([$user, $point, strlen($plain), $user, $point]);
// Two rows in one INSERT: MySQL hands back the FIRST one's id.
$recording = (int) $pdo->lastInsertId();
$document = $recording + 1;
$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];
$open = static fn(int $u, string $p): array => $fake;
FakePcloud::reset([100 => ['', null], 106 => ['x', 100]], [900 => ['P1.JPG', 106], 901 => ['Liste.pdf', 106]]);
FakePcloud::$bytes = [900 => $plain, 901 => '%PDF'];
$stamp = static function (string $table, int $id) use ($pdo): ?string {
    $q = $pdo->prepare("SELECT stamp FROM $table WHERE id = ?");
    $q->execute([$id]);
    return $q->fetchColumn() ?: null;
};

stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    $r = picture_stamps_run($pdo, microtime(true) + 30, $open);
    $gps = php_gps($work, FakePcloud::$bytes[900]);
    check($gps !== null && abs($gps['lat'] - 50.123456) < 1e-6, 'the recording in the cloud carries the find\'s place');
    check(FakePcloud::name(900) === 'P1.JPG' && isset(FakePcloud::$bytes[900]) && count(FakePcloud::$files) === 2,
        'replaced under its name and id — no second file beside it');
    $gps = php_gps($work, (string) file_get_contents("$directory/$fileName"));
    check($gps !== null && abs($gps['lon'] - 9.654321) < 1e-6, 'the voucher photo waiting here is written in place');
    // The find keeps its height in whole metres — 245.3 went in as 245.
    $stored = 'v1|50.123456|9.654321|245.0|Boletus edulis';
    check($stamp('map_cloud_files', $recording) === $stored && $stamp('map_photos', $photo) === $stored,
        'both rows carry the line of what is in them');
    check(str_starts_with((string) $stamp('map_cloud_files', $document), 'skip|') && FakePcloud::$bytes[901] === '%PDF',
        'a PDF is left as it is, and noted');

    $calls = count(FakePcloud::$calls);
    $r = picture_stamps_run($pdo, microtime(true) + 30, $open);
    check($r['stamped'] === 0 && count(FakePcloud::$calls) === $calls, 'run again, nothing is fetched — nothing changed');

    $pdo->prepare('UPDATE map_points SET title = "Boletus reticulatus", updated_at = NOW() + INTERVAL 1 SECOND WHERE id = ?')
        ->execute([$point]);
    sleep(1);
    $r = picture_stamps_run($pdo, microtime(true) + 30, $open);
    check($r['stamped'] === 2 && str_ends_with((string) $stamp('map_photos', $photo), '|Boletus reticulatus'),
        'another name: both pictures are written again');
    $x = exiftool_read($exiftool, $work, FakePcloud::$bytes[900]);
    if ($x !== null) {
        check(($x['XMP-dc:Subject'] ?? null) === 'Boletus reticulatus', '… and carry the new name alone');
    }

    $pdo->prepare('UPDATE map_points SET extra = ?, updated_at = NOW() + INTERVAL 1 SECOND WHERE id = ?')
        ->execute([json_encode(['Bestimmungssicherheit' => 'unsicher']), $point]);
    sleep(1);
    picture_stamps_run($pdo, microtime(true) + 30, $open);
    $x = exiftool_read($exiftool, $work, FakePcloud::$bytes[900]);
    check(str_ends_with((string) $stamp('map_cloud_files', $recording), '|')
          && ($x === null || (!isset($x['XMP-dc:Subject']) && abs($x['GPS:GPSLatitude'] - 50.123456) < 1e-6)),
        'set back to „unsicher", the name leaves the picture, the place stays');
} finally {
    stream_wrapper_restore('https');
    @unlink("$directory/$fileName");
    @rmdir($directory);
    @rmdir(dirname($directory));
    $pdo->prepare('DELETE FROM map_cloud_files WHERE point_id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_photos WHERE point_id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_users WHERE id = ?')->execute([$user]);
    array_map('unlink', glob("$work/*") ?: []);
    @rmdir($work);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
