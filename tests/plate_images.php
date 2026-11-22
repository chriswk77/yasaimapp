<?php
/**
 * A plate's drawn pages in the maker's cloud (includes/plate_images.php,
 * 13.10.2026) — against pCloud played in memory (tests/fake_pcloud.php):
 * the name on top of the find's pictures, the replace of a page drawn
 * anew, the page that goes with a save and the plate that goes whole.
 *
 *   php tests/plate_images.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/plates.php';
require_once __DIR__ . '/../includes/plate_images.php';
require_once __DIR__ . '/fake_pcloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo ($b ? '  ok    ' : '  FAILED ') . $what . "\n";
    if (!$b) {
        $failed++;
    }
}
function refused(callable $fn): string
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
    return '';
}
function page_jpeg(int $w, int $h): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    ob_start();
    imagejpeg($im, null, 80);
    imagedestroy($im);
    return (string) ob_get_clean();
}

echo "Plate pages in the cloud\n";
if (!plates_ready($pdo) || !plate_images_ready($pdo)) {
    echo "\n  (migration 0155 or 0169 not applied — nothing to test)\n\nAll good.\n";
    exit(0);
}

$pdo->exec("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
            VALUES ('pruef-tafelbild@pruefung.local', '', 'PRUEF', 'Tafelbild', 'user', NOW())");
$user = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
            VALUES ('pruef-tafelbild2@pruefung.local', '', 'PRUEF', 'Fremd', 'user', NOW())");
$stranger = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, found_at, country, region, municipality, created_at, updated_at)
               VALUES (?, 47.48, 11.27, "Boletus edulis", "2026-08-29 10:12:00", "DE", "BAY", "Krün", NOW(), NOW())')
    ->execute([$user]);
$point = (int) $pdo->lastInsertId();
$twoPages = ['v' => 1, 'pages' => [['root' => ['t' => 'cell']], ['root' => ['t' => 'cell']]]];
$pdo->prepare('INSERT INTO map_plates (point_id, user_id, layout, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')
    ->execute([$point, $user, json_encode($twoPages)]);
$plate = (int) $pdo->lastInsertId();

$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];
$open = static fn(int $u, string $p): array => $fake;
FakePcloud::reset([100 => ['', null]], []);
stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    $bytes = page_jpeg(1654, 2339);
    $a = plate_image_store($pdo, $user, $plate, 1, 'abc123', $bytes, $open);
    $row = $pdo->query('SELECT * FROM map_plate_images WHERE plate_id = ' . $plate . ' AND page = 1')->fetch();
    check($a['stored'] && $row !== false && FakePcloud::name((int) $row['fileid']) === 'Boletus edulis 2026-08-29 DE,BAY, Krün 00 Tafel 1.jpg',
        'a page lands in the find\'s folder as „<head> 00 Tafel 1.jpg" — 00 before the pictures\' 01');
    check(strcmp('Boletus edulis 2026-08-29 DE,BAY, Krün 00 Tafel 1.jpg', 'Boletus edulis 2026-08-29 DE,BAY, Krün 01 Hauptmotiv.JPG') < 0
          && strcmp('… 00 Tafel', '… 001 Hauptmotiv') < 0,
        'and sorts before „01 …" as before „001 …"');
    check((int) $row['width'] === 1654 && (int) $row['height'] === 2339 && $row['hash'] === 'abc123', 'the row keeps its size and what it was drawn from');
    $preview = plate_image_preview_path($user, $plate, 1);
    $size = @getimagesize($preview);
    check(is_array($size) && max($size[0], $size[1]) === PLATE_IMAGE_PREVIEW_EDGE, 'a small preview is kept on this server');

    $files = count(FakePcloud::$files);
    plate_image_store($pdo, $user, $plate, 1, 'def456', $bytes, $open);
    $again = $pdo->query('SELECT * FROM map_plate_images WHERE plate_id = ' . $plate . ' AND page = 1')->fetch();
    check(count(FakePcloud::$files) === $files && $again['fileid'] === $row['fileid'] && $again['hash'] === 'def456',
        'drawn anew, the page replaces itself in place — no „(1)" beside it');

    plate_image_store($pdo, $user, $plate, 2, 'p2', $bytes, $open);
    check(str_contains(refused(fn() => plate_image_store($pdo, $user, $plate, 3, 'x', $bytes, $open)), 'Seite')
          && str_contains(refused(fn() => plate_image_store($pdo, $stranger, $plate, 1, 'x', $bytes, $open)), 'nicht deine')
          && str_contains(refused(fn() => plate_image_store($pdo, $user, $plate, 1, 'x', 'not a picture', $open)), 'Bild'),
        'a page the plate lacks, somebody else\'s plate and bytes that are no picture are refused');
    check(plate_image_store($pdo, $user, $plate, 1, 'x', $bytes, static fn() => null) === ['stored' => false],
        'no cloud, no page — and no error');

    $images = plate_images_of($pdo, [$plate])[$plate] ?? [];
    check(array_column($images, 'page') === [1, 2], 'the plate hands its pages\' images to the browser');

    // THE PAGES FOLLOW THEIR FIND (22.11.2026): its pictures lie in another folder now.
    $pointRow = static fn(): array => $pdo->query('SELECT * FROM map_points WHERE id = ' . $point)->fetch();
    $pageRow = static fn(int $n): array => $pdo->query('SELECT * FROM map_plate_images WHERE plate_id = ' . $plate . ' AND page = ' . $n)->fetch();
    $was = (int) $pageRow(1)['folderid'];
    FakePcloud::$folders[700] = ['Neuer Ordner des Fundes', 100];
    FakePcloud::$files[7001] = ['Bild.jpg', 700];
    FakePcloud::$files[7002] = ['Boletus edulis 2026-08-29 DE,BAY, Krün 00 Tafel 2.jpg', 700];   // a page from before lies there
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, folderid, name, original_name, path, size, sort_order, uploaded_at)
                   VALUES (?, ?, "pcloud", "7001", "700", "Bild.jpg", "Bild.jpg", "Neu", 1, 0, NOW())')->execute([$user, $point]);
    $r = plate_images_follow($pdo, $pointRow(), $open);
    check($r === ['moved' => 2, 'failed' => 0] && $pageRow(1)['folderid'] === '700' && $pageRow(2)['folderid'] === '700'
          && FakePcloud::names(700) === ['Bild.jpg', 'Boletus edulis 2026-08-29 DE,BAY, Krün 00 Tafel 1.jpg',
                                         'Boletus edulis 2026-08-29 DE,BAY, Krün 00 Tafel 2.jpg'],
        'the find\'s pictures lie elsewhere — both pages follow into their folder');
    check(!isset(FakePcloud::$files[7002]) && FakePcloud::name((int) $pageRow(2)['fileid']) !== null,
        'a page from before that held the name there goes — the row\'s own page takes it');
    check(!isset(FakePcloud::$folders[$was]), 'and the folder the pages left, empty now, is gone');
    $calls = count(FakePcloud::$calls);
    check(plate_images_follow($pdo, $pointRow(), $open) === ['moved' => 0, 'failed' => 0] && count(FakePcloud::$calls) === $calls,
        'pages that lie right cost no call to the cloud');
    // The find renamed: the pages carry the head the pictures carry.
    $pdo->prepare('UPDATE map_points SET title = "Boletus cf. edulis" WHERE id = ?')->execute([$point]);
    plate_images_follow($pdo, $pointRow(), $open);
    check(FakePcloud::name((int) $pageRow(1)['fileid']) === 'Boletus cf. edulis 2026-08-29 DE,BAY, Krün 00 Tafel 1.jpg'
          && $pageRow(1)['name'] === 'Boletus cf. edulis 2026-08-29 DE,BAY, Krün 00 Tafel 1.jpg' && $pageRow(1)['folderid'] === '700',
        'renamed, the find\'s pages are renamed where they lie');
    // While the pictures lie in two folders, the pages wait.
    FakePcloud::$folders[701] = ['Noch einer', 100];
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, folderid, name, original_name, path, size, sort_order, uploaded_at)
                   VALUES (?, ?, "pcloud", "7003", "701", "Bild2.jpg", "Bild2.jpg", "Noch", 1, 1, NOW())')->execute([$user, $point]);
    check(plate_images_follow($pdo, $pointRow(), $open) === ['moved' => 0, 'failed' => 0] && $pageRow(1)['folderid'] === '700',
        'while the pictures lie in two folders the pages stay where they are');
    $pdo->prepare('DELETE FROM map_cloud_files WHERE point_id = ?')->execute([$point]);
    $pdo->prepare('UPDATE map_points SET title = "Boletus edulis" WHERE id = ?')->execute([$point]);
    plate_images_follow($pdo, $pointRow(), $open);   // no picture of the maker's there: the name alone tells, and the find's own folder is asked for

    // A save down to one page takes page 2 along — its file, row and preview.
    $p2 = (int) $pdo->query('SELECT fileid FROM map_plate_images WHERE plate_id = ' . $plate . ' AND page = 2')->fetchColumn();
    plate_images_prune($pdo, $user, $plate, 1, $open);
    check(FakePcloud::name($p2) === null && (plate_images_of($pdo, [$plate])[$plate] ?? []) !== []
          && count(plate_images_of($pdo, [$plate])[$plate]) === 1 && !is_file(plate_image_preview_path($user, $plate, 2)),
        'a page taken away goes in the cloud and here');

    // A one-page plate says „00 Tafel", without the number.
    $pdo->prepare('UPDATE map_plates SET layout = ? WHERE id = ?')
        ->execute([json_encode(['v' => 1, 'pages' => [['root' => ['t' => 'cell']]]]), $plate]);
    plate_image_store($pdo, $user, $plate, 1, 'one', $bytes, $open);
    $one = $pdo->query('SELECT * FROM map_plate_images WHERE plate_id = ' . $plate . ' AND page = 1')->fetch();
    check(FakePcloud::name((int) $one['fileid']) === 'Boletus edulis 2026-08-29 DE,BAY, Krün 00 Tafel.jpg'
          && FakePcloud::name((int) $row['fileid']) === null,
        'a plate of one page is „… 00 Tafel.jpg", and the file named for two pages goes');

    plate_images_prune($pdo, $user, $plate, 0, $open);
    check(FakePcloud::name((int) $one['fileid']) === null && (plate_images_of($pdo, [$plate])[$plate] ?? []) === [],
        'the plate deleted, its pages go with it');
} finally {
    stream_wrapper_restore('https');
    @unlink(plate_image_preview_path($user, $plate, 1));
    @unlink(plate_image_preview_path($user, $plate, 2));
    @rmdir(dirname(plate_image_preview_path($user, $plate, 1)));
    @rmdir(user_directory($user));
    $pdo->prepare('DELETE FROM map_plates WHERE id = ?')->execute([$plate]);
    $pdo->prepare('DELETE FROM map_cloud_files WHERE point_id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_users WHERE id IN (?, ?)')->execute([$user, $stranger]);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
