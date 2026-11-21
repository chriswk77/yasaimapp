<?php
/**
 * Tests the kept work of „Ebenen" — the sets and their masks.
 *
 *   php tests/layer_sets.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   The shape. A set comes from the browser as JSON and is kept as it
 *   came; two to four distinct pictures, nothing else.
 *
 *   The mask's gate. A mask is a file of megabytes that goes into
 *   somebody's cloud: a PNG within the painted size and the host's limit.
 *
 *   The mask in the cloud, against pCloud in memory (tests/fake_pcloud.php):
 *   one file per picture, replaced by every save; carried along when its
 *   picture is renamed; left alone when the picture's sidecar goes (an
 *   hour's painting must not go with a title); a stray once its picture
 *   is gone — but only in „Bearbeitung", never beside the pictures, where
 *   a file of that name may be the user's own.
 *
 *   The row, against the database, where migration 0191 is applied: kept,
 *   written over by the same pictures, giving way to a set that takes one
 *   of its pictures. Everything created is removed again.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud_names.php';
require_once __DIR__ . '/../includes/layer_sets.php';
require_once __DIR__ . '/fake_pcloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo ($b ? '  ok   ' : '  FAIL ') . $what . "\n";
    if (!$b) {
        $failed++;
    }
}

function png(int $w, int $h, int $seed = 1): string
{
    $im = imagecreatetruecolor($w, $h);
    imagesetpixel($im, $seed % $w, 0, $seed * 997 & 0xffffff);
    ob_start();
    imagepng($im);
    return (string) ob_get_clean();
}

echo "The shape\n";
$three = [['key' => 'cloud:12', 'shown' => true, 'mask' => false], ['key' => 'cloud:13', 'shown' => false, 'mask' => true], ['key' => 'photo:7']];
check(layer_sets_clean($three) === [
    ['key' => 'cloud:12', 'shown' => true, 'mask' => false],
    ['key' => 'cloud:13', 'shown' => false, 'mask' => true],
    ['key' => 'photo:7', 'shown' => true, 'mask' => false],
], 'key, eye and mask are kept; a missing eye is on, a missing mask none');
check(layer_sets_clean([['key' => 'cloud:12']]) === null, 'one picture is no set');
$aligned = layer_sets_clean([
    ['key' => 'cloud:12', 'frame' => true],
    ['key' => 'cloud:13', 'frame' => true, 'align' => [1.0312345678, -0.007, 0.0123, 0.007, 1.031, -0.02]],
    ['key' => 'photo:7', 'align' => [1, 0, 0, 0]],
]);
check($aligned === [
    ['key' => 'cloud:12', 'shown' => true, 'mask' => false, 'frame' => true],
    ['key' => 'cloud:13', 'shown' => true, 'mask' => false, 'align' => [1.031235, -0.007, 0.0123, 0.007, 1.031, -0.02]],
    ['key' => 'photo:7', 'shown' => true, 'mask' => false],
], 'one frame at most, a map of six numbers rounded to a millionth, a broken one left out');
check(layer_align_clean([0, 0, 0, 0, 0, 0]) === null && layer_align_clean([1, 0, 5, 0, 1, 0]) === null
    && layer_align_clean([1, 0, '0', 0, 1, 0]) === null && layer_align_clean([1, 0, NAN, 0, 1, 0]) === null
    && layer_align_clean(['a' => 1, 0, 0, 0, 1, 0]) === null,
    'a map that folds the picture flat, throws it far off, or is no list of numbers is no map');
check(layer_align_clean([1.01, 0.012, -0.004, -0.008, 1.0, 0.003, 0.0256789012, -0.0126])
        === [1.01, 0.012, -0.004, -0.008, 1.0, 0.003, 0.025679, -0.0126]
    && layer_align_clean([1, 0, 0, 0, 1, 0, 0, 0]) === [1.0, 0.0, 0.0, 0.0, 1.0, 0.0]
    && layer_align_clean([1, 0, 0, 0, 1, 0, 0.5, 0.2]) === null && layer_align_clean([1, 0, 0, 0, 1, 0, 0.01]) === null,
    'a tilted layer keeps eight numbers; a tilt of nothing is six; one that would fold the frame, or seven numbers, are none');
check(layer_sets_clean(array_fill(0, 5, ['key' => 'cloud:1'])) === null && layer_sets_clean([['key' => 'cloud:1'], ['key' => 'cloud:1']]) === null,
    'five pictures, or one twice, are none either');
check(layer_sets_clean([['key' => 'cloud:1'], ['key' => '../x']]) === null && layer_sets_clean('x') === null,
    'a key that names no picture refuses the whole');

echo "\nThe mask's gate\n";
check(layer_mask_png(png(4096, 2731)), 'a PNG of the painted size passes');
check(layer_mask_png(png(2048, 1366)), 'a halved one too');
check(!layer_mask_png(png(4100, 100)), 'one wider than a mask can be does not');
check(!layer_mask_png("\xFF\xD8\xFF\xE0" . str_repeat('x', 100)) && !layer_mask_png('<svg xmlns="http://www.w3.org/2000/svg"/>'),
    'a JPEG or an SVG does not');

echo "\nNames\n";
check(layer_mask_name('IMG 1.jpg') === 'IMG 1.jpg.ebene.png', 'named after the picture, one name');
check(develop_patch_split('IMG 1.jpg.ebene.png') === ['picture' => 'IMG 1.jpg', 'id' => '', 'kind' => 'ebene'], 'taken apart as a mask');
check(develop_patch_split('IMG 1.jpg.flicken-aaaa1111bbbb2222.png') === ['picture' => 'IMG 1.jpg', 'id' => 'aaaa1111bbbb2222', 'kind' => 'flicken'],
    'a patch stays a patch');
check(develop_patch_split('.ebene.png') === null, 'a bare suffix is nobody\'s mask');
$side = static fn(string $n): array => ['id' => $n, 'name' => $n, 'folder' => false];
check(storage_stray_sidecars([$side('b.jpg.ebene.png')], ['a.jpg']) === ['b.jpg.ebene.png']
    && storage_stray_sidecars([$side('a.jpg.ebene.png')], ['a.jpg']) === [],
    'in „Bearbeitung" a mask without its picture is a stray, one with it stays');
check(storage_stray_sidecars([$side('a.jpg'), $side('Skizze.ebene.png')]) === [],
    'BESIDE the pictures a file of that name is the user\'s own and is left alone');

echo "\nIn the cloud\n";
$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];
stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    FakePcloud::reset([100 => ['', null], 106 => ['2026-10-03 Lohr', 100], 108 => ['2026-10-04 Krün', 100]],
        [900 => ['a.jpg', 106], 901 => ['b.jpg', 106]]);
    $m1 = png(64, 48, 1);
    $m2 = png(64, 48, 2);
    check(layer_mask_get($fake, '900') === null, 'no mask before one is laid');
    layer_mask_put($fake, '900', $m1);
    $sub = array_search([SIDECAR_FOLDER, 106], FakePcloud::$folders, true);
    check($sub !== false && FakePcloud::names((int) $sub) === ['a.jpg.ebene.png'], 'the mask lies in „Bearbeitung", named after its picture');
    layer_mask_put($fake, '900', $m2);
    check(FakePcloud::names((int) $sub) === ['a.jpg.ebene.png'] && layer_mask_get($fake, '900') === $m2,
        'the next save replaces it — one file, the newer bytes');
    check(layer_mask_get($fake, '901') === null, 'another picture has none');

    // The picture's sidecar goes (nothing left to say in it): the mask stays.
    storage_upload_bytes($fake, $sub, '{}', 'a.jpg.messwerte.json', null, true, 'application/json');
    storage_sidecar_delete($fake, 106, 'a.jpg');
    check(FakePcloud::names((int) $sub) === ['a.jpg.ebene.png'], 'deleting the sidecar leaves the mask — and the folder with it');

    // Renamed into another folder — the mask follows under the new name.
    $listings = [];
    $item = ['kind' => 'cloud', 'id' => 0, 'name' => 'a.jpg', 'folder' => '106', 'fileid' => '900', 'row' => []];
    cloud_names_move($pdo, $fake, $item, 'Boletus 01.jpg', '108', 'x', $listings, 100, false);
    $sub2 = array_search([SIDECAR_FOLDER, 108], FakePcloud::$folders, true);
    check($sub2 !== false && FakePcloud::names((int) $sub2) === ['Boletus 01.jpg.ebene.png'] && FakePcloud::names((int) $sub) === [],
        'renamed and moved: the mask goes along under the new name');
    check(layer_mask_get($fake, '900') === $m2, 'and is found there');

    layer_mask_remove($fake, '900');
    check(!isset(FakePcloud::$folders[(int) $sub2]) && layer_mask_get($fake, '900') === null,
        'taken away, and the emptied „Bearbeitung" with it');

    // The picture deleted: its mask is a stray and goes with the way.
    layer_mask_put($fake, '901', $m1);
    storage_picture_delete($pdo, 0, $fake, 901);
    check(!isset(FakePcloud::$folders[106]), 'a picture deleted: its mask is a stray, and the empty way goes');
} finally {
    stream_wrapper_restore('https');
}

echo "\nThe row\n";
if (!layer_sets_ready($pdo)) {
    echo "  (migration 0191 is not applied here — the row is not tested)\n";
} else {
    // Two own pictures on one own find, as the demo data has them.
    $own = $pdo->query('SELECT user_id, point_id, GROUP_CONCAT(id ORDER BY id) AS ids FROM map_photos
                         GROUP BY user_id, point_id HAVING COUNT(*) >= 3 LIMIT 1')->fetch();
    if ($own === false) {
        echo "  (no find with three own pictures here — run admin/demodata.php)\n";
    } else {
        $userId = (int) $own['user_id'];
        $pointId = (int) $own['point_id'];
        [$a, $b, $c] = array_map(static fn(string $id): string => 'photo:' . $id, array_slice(explode(',', (string) $own['ids']), 0, 3));
        $mine = static fn(): array => array_map(static fn(array $s): array => array_column($s['layers'], 'key'),
            layer_sets_of($GLOBALS['pdo'], $userId, $pointId));
        $before = $pdo->query('SELECT COUNT(*) FROM map_layer_sets')->fetchColumn();
        try {
            $set = layer_set_save($pdo, $userId, $pointId, layer_sets_clean([['key' => $a], ['key' => $b]]));
            check($set['id'] > 0 && in_array([$a, $b], $mine(), true), 'a set is kept and read back, ground first');
            $again = layer_set_save($pdo, $userId, $pointId, layer_sets_clean([['key' => $b, 'shown' => false], ['key' => $a]]));
            check($again['id'] === $set['id'] && $again['layers'][0] === ['key' => $b, 'shown' => false, 'mask' => false],
                'the same pictures in another order write over the same row');
            $other = layer_set_save($pdo, $userId, $pointId, layer_sets_clean([['key' => $b], ['key' => $c]]));
            check($other['id'] !== $set['id'] && !in_array([$b, $a], $mine(), true) && in_array([$b, $c], $mine(), true),
                'a set that takes one of its pictures replaces it — a picture lies in one set');
            $threw = false;
            try {
                layer_set_save($pdo, $userId + 100000, $pointId, layer_sets_clean([['key' => $a], ['key' => $b]]));
            } catch (InvalidArgumentException $e) {
                $threw = true;
            }
            check($threw, 'somebody else\'s pictures make no set');
            check(layer_mask_read($pdo, $userId, $other['id'], $b) === null, 'a layer without a mask hands none out');
            check(layer_mask_read($pdo, $userId + 100000, $other['id'], $b) === null, 'nor to anybody but its maker');
        } finally {
            foreach (layer_sets_of($pdo, $userId, $pointId) as $s) {
                if (array_intersect(array_column($s['layers'], 'key'), [$a, $b, $c]) !== []) {
                    $pdo->prepare('DELETE FROM map_layer_sets WHERE id = ?')->execute([$s['id']]);
                }
            }
        }
        check($pdo->query('SELECT COUNT(*) FROM map_layer_sets')->fetchColumn() === $before, 'nothing is left behind');
    }
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
