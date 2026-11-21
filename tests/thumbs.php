<?php
/**
 * Tests the preview cache (includes/thumbs.php).
 *
 *   php tests/thumbs.php
 *
 * THE SHRINKING on pictures drawn here: a provider's preview is kept as it
 * came, a whole picture is drawn into the 320 box and turned upright by
 * its EXIF orientation, keeping its colour profile, what nobody can draw
 * is refused. THE CACHE against
 * pCloud played in memory (tests/fake_pcloud.php): made once and then read
 * from the disk without a call, forgotten with its picture, a failure
 * marked so the provider is not asked again, an orphan swept when its row
 * is gone. The test rows and files are removed at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/thumbs.php';
require_once __DIR__ . '/fake_pcloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** A JPEG of w × h, its left half dark — so a turn can be seen in the result. */
function drawn(int $w, int $h, int $quality = 90): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, imagecolorallocate($im, 240, 240, 240));
    imagefilledrectangle($im, 0, 0, intdiv($w, 2), $h - 1, imagecolorallocate($im, 10, 10, 10));
    ob_start();
    imagejpeg($im, null, $quality);
    imagedestroy($im);
    return (string) ob_get_clean();
}

/** The same JPEG with an EXIF block saying how the camera was held. */
function with_orientation(string $jpeg, int $orientation): string
{
    $tiff = 'II*' . "\0" . pack('V', 8) . pack('v', 1)
          . pack('vvV', 0x0112, 3, 1) . pack('vv', $orientation, 0) . pack('V', 0);
    $app1 = "Exif\0\0" . $tiff;
    return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
}

echo "Shrinking\n";
$preview = drawn(320, 240);
check(thumb_shrink($preview) === $preview, 'a provider\'s preview is kept as it came');

$whole = thumb_shrink(drawn(2400, 1600));
$size = $whole === null ? null : getimagesizefromstring($whole);
check($size !== null && $size[0] === 320 && $size[1] === 213, 'a whole picture is drawn into the 320 box (2400×1600 → 320×213)');

$turned = thumb_shrink(with_orientation(drawn(2400, 1600), 6));
$size = $turned === null ? null : getimagesizefromstring($turned);
check($size !== null && $size[0] === 213 && $size[1] === 320, 'a camera held upright comes out upright (EXIF 6 → 213×320)');
if ($turned !== null) {
    // Turned clockwise, the dark left half lies on top.
    $im = imagecreatefromstring($turned);
    $top = imagecolorat($im, 100, 20) & 0xFF;
    $bottom = imagecolorat($im, 100, 300) & 0xFF;
    check($top < 80 && $bottom > 160, 'and turned the right way round — the left half on top');
}

check(thumb_shrink(random_bytes(5000)) === null, 'what nobody can draw (a raw file) is refused');

/* THE COLOUR PROFILE GOES WITH IT (16.11.2026): a picture in Adobe RGB or
   P3 keeps its profile in the preview, or its tile shows duller and colder
   than the picture. A profile made here — the smallest that passes as
   one: its length, „acsp" at byte 36 —, put in as a camera puts it. */
$icc = pack('N', 600) . str_repeat("\x11", 32) . 'acsp' . str_repeat("\x22", 600 - 40);
$tagged = substr(drawn(2400, 1600), 0, 2) . "\xFF\xE2" . pack('n', strlen($icc) + 16) . "ICC_PROFILE\0\x01\x01" . $icc . substr(drawn(2400, 1600), 2);
check(picture_icc($tagged, 'image/jpeg') === $icc, 'a JPEG\'s profile is read');
$small = thumb_shrink($tagged);
check($small !== null && picture_icc($small, 'image/jpeg') === $icc, 'and written into the preview GD draws');
check($small !== null && @imagecreatefromstring($small) !== false, 'which still opens as a picture');
check($whole !== null && picture_icc($whole, 'image/jpeg') === null, 'a picture without one gets none');
$big = str_repeat('x', 70000);
$bigIcc = pack('N', 70000 + 40) . str_repeat("\x11", 32) . 'acsp' . $big;
$two = jpeg_with_icc(drawn(100, 100), $bigIcc);
check(picture_icc($two, 'image/jpeg') === $bigIcc, 'a profile larger than one segment goes in two parts and comes back whole');
$png = imagecreatetruecolor(10, 10);
ob_start(); imagepng($png); $pngBytes = (string) ob_get_clean();
$iccp = 'ICC Profile' . "\0\0" . gzcompress($icc);
$chunk = pack('N', strlen($iccp)) . 'iCCP' . $iccp . pack('N', crc32('iCCP' . $iccp));
// Behind the 8-byte signature and the 25-byte IHDR chunk.
$pngTagged = substr($pngBytes, 0, 33) . $chunk . substr($pngBytes, 33);
check(picture_icc($pngTagged, 'image/png') === $icc, 'a PNG\'s profile is read as well');

echo "\nFetching\n";
$work = sys_get_temp_dir() . '/thumbs-' . getmypid();
@mkdir($work);
file_put_contents("$work/a.jpg", $preview);
check(thumb_fetch([['url' => "file://$work/missing.jpg", 'headers' => ''],
                   ['url' => "file://$work/a.jpg", 'headers' => '']]) === $preview,
      'the first candidate that answers wins');

echo "\nThe cache, against pCloud played in memory\n";
$pdo->exec("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
            VALUES ('pruef-vorschau@pruefung.local', '', 'PRUEF', 'Vorschau', 'user', NOW())");
$user = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, created_at, updated_at)
               VALUES (?, 50.1, 9.6, "Prüffund", NOW(), NOW())')->execute([$user]);
$point = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, folderid, name, path, size, uploaded_at)
               VALUES (?, ?, "pcloud", "900", "106", "P1.JPG", "x", 1, NOW()),
                      (?, ?, "pcloud", "901", "106", "P2.ORF", "x", 1, NOW())')
    ->execute([$user, $point, $user, $point]);
$recording = (int) $pdo->lastInsertId();   // the FIRST row's id
$raw = $recording + 1;
$row = static function (int $id) use ($pdo): array {
    $q = $pdo->prepare('SELECT id, user_id, provider, fileid FROM map_cloud_files WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch();
};
$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];
$open = static fn(int $u, string $p): array => $fake;
FakePcloud::reset([100 => ['', null], 106 => ['x', 100]], [900 => ['P1.JPG', 106], 901 => ['P2.ORF', 106]]);
FakePcloud::$bytes = [900 => drawn(1600, 1200), 901 => random_bytes(3000)];

stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    $path = thumb_ensure($pdo, 'cloud', $row($recording), $open);
    check($path !== null && is_file($path), 'the first asking fetches the preview and keeps it');
    check($path !== null && getimagesize($path)[0] === 320, 'kept small — 320 wide');
    check($path !== null && str_starts_with($path, user_directory($user) . '/thumbs/'), 'in the owner\'s folder under data/users');

    $calls = count(FakePcloud::$calls);
    check(thumb_ensure($pdo, 'cloud', $row($recording), $open) === $path, 'the second asking finds it');
    check(count(FakePcloud::$calls) === $calls, 'and asks the cloud nothing');

    check(thumb_ensure($pdo, 'cloud', $row($raw), $open) === null, 'a raw file gets no preview');
    check(is_file(thumb_marker($user, 'cloud', $raw)), 'and a marker says so');
    $calls = count(FakePcloud::$calls);
    thumb_ensure($pdo, 'cloud', $row($raw), $open);
    check(count(FakePcloud::$calls) === $calls, 'within the week the cloud is not asked again');

    thumb_forget($user, 'cloud', $recording);
    check(!is_file((string) $path), 'deleting the picture forgets its preview');

    $r = thumbs_run($pdo, microtime(true) + 10, $open, $user);
    check(is_file(thumb_path($user, 'cloud', $recording)), 'the run makes what is missing');
    check($r['made'] >= 1, 'and counts it');

    /* PCLOUD'S OWN PREVIEW LOSES THE PROFILE (16.11.2026, THUMB_PROFILE_LOST):
       it gets the original's back, read from the original's head and kept
       beside it; an original without a profile leaves its preview alone,
       and a preview kept before the fix is mended by the run. */
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, folderid, name, path, size, uploaded_at)
                   VALUES (?, ?, "pcloud", "902", "106", "P3.JPG", "x", 1, NOW()),
                          (?, ?, "pcloud", "903", "106", "P4.JPG", "x", 1, NOW())')
        ->execute([$user, $point, $user, $point]);
    $wide = (int) $pdo->lastInsertId();
    $plain = $wide + 1;
    FakePcloud::$files += [902 => ['P3.JPG', 106], 903 => ['P4.JPG', 106]];
    FakePcloud::$bytes += [902 => $tagged, 903 => drawn(1600, 1200)];
    FakePcloud::$thumbs = [902 => drawn(320, 240), 903 => drawn(320, 240)];
    $kept = thumb_ensure($pdo, 'cloud', $row($wide), $open);
    check($kept !== null && picture_icc((string) file_get_contents($kept), 'image/jpeg') === $icc,
          'pCloud\'s preview gets the original\'s profile back');
    check(is_file(thumb_icc_path($user, 'cloud', $wide)), 'and the profile is kept beside it');
    $calls = count(FakePcloud::$calls);
    check(thumb_icc($pdo, 'cloud', $row($wide), $open) === $icc && count(FakePcloud::$calls) === $calls,
          'asked again, the kept profile answers without the cloud');
    $keptPlain = thumb_ensure($pdo, 'cloud', $row($plain), $open);
    check($keptPlain !== null && (string) file_get_contents($keptPlain) === FakePcloud::$thumbs[903],
          'an original without a profile leaves its preview as it came');
    check(is_file(thumb_icc_path($user, 'cloud', $plain)) && filesize(thumb_icc_path($user, 'cloud', $plain)) === 0,
          'and is marked as having none');
    // A preview kept before the fix: no profile in it, no profile file beside it.
    file_put_contents((string) $kept, FakePcloud::$thumbs[902]);
    unlink(thumb_icc_path($user, 'cloud', $wide));
    $r = thumbs_run($pdo, microtime(true) + 10, $open, $user);
    check($r['profiled'] === 1 && picture_icc((string) file_get_contents((string) $kept), 'image/jpeg') === $icc,
          'the run mends a preview kept without it');
    thumb_forget($user, 'cloud', $wide);
    check(!is_file(thumb_icc_path($user, 'cloud', $wide)), 'forgetting the picture forgets its profile too');

    $pdo->prepare('DELETE FROM map_cloud_files WHERE id = ?')->execute([$recording]);
    thumbs_sweep($pdo);
    check(!is_file(thumb_path($user, 'cloud', $recording)), 'a preview whose row is gone is swept');
    check(!is_file(thumb_marker($user, 'cloud', $recording)), 'its marker too');
} finally {
    stream_wrapper_restore('https');
    array_map('unlink', glob(thumb_directory($user) . '/*') ?: []);
    @rmdir(thumb_directory($user));
    @rmdir(user_directory($user));
    $pdo->prepare('DELETE FROM map_cloud_files WHERE point_id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_users WHERE id = ?')->execute([$user]);
    array_map('unlink', glob("$work/*") ?: []);
    @rmdir($work);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
