<?php
/**
 * Tests the patches of „Entfernen" in the picture's cloud.
 *
 *   php tests/develop_patches.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   The gate. A patch comes from the browser as base64 and goes into
 *   somebody's cloud, and later out again as image/png to everybody who may
 *   see the picture — so only PNGs of the patch's size pass, and only under
 *   an id the saved develop names.
 *
 *   The ways that follow the sidecar. A patch is named after its picture
 *   so that refiling, renaming, deleting and tidying find it without a
 *   list of their own; each of them is played here against pCloud in
 *   memory (tests/fake_pcloud.php). A patch left behind under the old name
 *   would fill the NEXT picture of that name.
 *
 * Nothing is written to the database.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud.php';
require_once __DIR__ . '/../includes/cloud_names.php';
require_once __DIR__ . '/../includes/develop_patches.php';
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

/** A real PNG of $w × $h, a little noise so the bytes differ. */
function png(int $w, int $h, int $seed = 1): string
{
    $im = imagecreatetruecolor($w, $h);
    imagesetpixel($im, $seed % $w, 0, $seed * 997 & 0xffffff);
    ob_start();
    imagepng($im);
    return (string) ob_get_clean();
}

echo "The gate\n";
$develop = ['spots' => [
    ['mode' => 'heal', 'path' => [[.5, .5]], 'radius' => .01, 'dx' => .1, 'dy' => 0],
    ['mode' => 'ai', 'path' => [[.1, .1], [.2, .2]], 'radius' => .02,
     'pieces' => [['id' => 'aaaa1111bbbb2222', 'box' => [0, 0, .5, .5], 'a' => 0, 'b' => .5],
                  ['id' => 'cccc3333dddd4444', 'box' => [0, 0, .5, .5], 'a' => .5, 'b' => 1]]],
]];
check(develop_patch_ids($develop) === ['aaaa1111bbbb2222', 'cccc3333dddd4444'], 'the ids are the AI pieces\' — a heal names none');
check(develop_patch_ids(null) === [] && develop_patch_ids(['exposure' => 10]) === [], 'no develop, no AI spot: no ids');
$twoFills = ['spots' => [['mode' => 'ai', 'path' => [[.1, .1]], 'strong' => true,
    'pieces' => [['id' => 'eeee5555ffff6666', 'box' => [0, 0, .5, .5]]],
    'other'  => [['id' => 'aaaa1111bbbb2222', 'box' => [0, 0, .5, .5]]]]]];
check(develop_patch_ids(['spots' => [['mode' => 'heal', 'path' => [[.1, .1]], 'dx' => .1, 'dy' => 0,
    'pieces' => [['id' => 'aaaa1111bbbb2222', 'box' => [0, 0, .5, .5]]]]]]) === ['aaaa1111bbbb2222'],
    'a place shown as „Angleichen" still names the fills it keeps from „Entfernen" (one tool, 14.11.2026)');
check(develop_patch_ids($twoFills) === ['eeee5555ffff6666', 'aaaa1111bbbb2222'],
    'the other model\'s kept fill is named too — a save must not have it deleted as dropped (14.11.2026)');
$good = png(512, 512);
check(develop_patch_png($good), 'a PNG of 512 × 512 passes');
check(!develop_patch_png(png(256, 512)), 'another size does not');
check(!develop_patch_png("\xFF\xD8\xFF\xE0" . str_repeat('x', 100)), 'a JPEG does not');
check(!develop_patch_png('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'an SVG does not');
$got = develop_patches_decode(['aaaa1111bbbb2222' => base64_encode($good), 'ffff0000ffff0000' => base64_encode($good)], $develop);
check(array_keys($got) === ['aaaa1111bbbb2222'] && $got['aaaa1111bbbb2222'] === $good,
    'only an id the develop names is taken, decoded');
$threw = static function (mixed $input) use ($develop): bool {
    try {
        develop_patches_decode($input, $develop);
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
};
check($threw(['aaaa1111bbbb2222' => base64_encode(png(100, 100))]), 'a named id with a wrong picture refuses the save');
check($threw(['aaaa1111bbbb2222' => 'not base64 !!']), 'broken base64 refuses the save');
check($threw(array_fill_keys(array_map(static fn($i) => 'id' . str_pad((string) $i, 8, '0', STR_PAD_LEFT), range(1, DEVELOP_PATCHES_PER_SAVE + 1)), '')),
    'more than one save may carry refuses it');
check($threw('x'), 'no object refuses it');

echo "\nNames\n";
check(develop_patch_name('IMG 1.jpg', 'aaaa1111bbbb2222') === 'IMG 1.jpg.flicken-aaaa1111bbbb2222.png', 'named after the picture');
check(develop_patch_split('IMG 1.jpg.flicken-aaaa1111bbbb2222.png') === ['picture' => 'IMG 1.jpg', 'id' => 'aaaa1111bbbb2222', 'kind' => 'flicken'],
    'taken apart again');
check(develop_patch_split('IMG 1.jpg.messwerte.json') === null && develop_patch_split('flicken-aaaa1111.png') === null,
    'a sidecar or a picture of the user\'s own is no patch');
$side = static fn(string $n): array => ['id' => $n, 'name' => $n, 'folder' => false];
check(storage_stray_sidecars([$side('a.jpg.flicken-aaaa1111bbbb2222.png')], ['a.jpg']) === [],
    'a patch whose picture lies one storey up stays');
check(storage_stray_sidecars([$side('b.jpg.flicken-aaaa1111bbbb2222.png')], ['a.jpg']) === ['b.jpg.flicken-aaaa1111bbbb2222.png'],
    'a patch whose picture is gone is a stray');

echo "\nIn the cloud\n";
$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];
stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    FakePcloud::reset([100 => ['', null], 106 => ['2026-10-03 Lohr', 100], 108 => ['2026-10-04 Krün', 100]],
        [900 => ['a.jpg', 106], 901 => ['b.jpg', 106]]);
    $p1 = png(512, 512, 1);
    $p2 = png(512, 512, 2);
    develop_patches_store($fake, '900', ['aaaa1111bbbb2222' => $p1, 'cccc3333dddd4444' => $p2]);
    $sub = array_search([SIDECAR_FOLDER, 106], FakePcloud::$folders, true);
    check($sub !== false && FakePcloud::names((int) $sub)
        === ['a.jpg.flicken-aaaa1111bbbb2222.png', 'a.jpg.flicken-cccc3333dddd4444.png'],
        'the patches lie in „Bearbeitung", named after their picture');
    develop_patches_store($fake, '900', ['aaaa1111bbbb2222' => $p1]);
    check(count(FakePcloud::names((int) $sub)) === 2, 'a patch sent twice is one file');
    check(develop_patch_bytes($fake, '900', 'cccc3333dddd4444') === $p2, 'read back byte for byte');
    check(develop_patch_bytes($fake, '901', 'cccc3333dddd4444') === null, 'another picture\'s id finds nothing');

    $sides = storage_sidecars_of($fake, 106, 'a.jpg');
    check(count($sides) === 2 && array_column($sides, 'patch') === ['aaaa1111bbbb2222', 'cccc3333dddd4444'],
        'the sidecar list carries the patches, marked');

    develop_patches_gone($fake, '900', ['cccc3333dddd4444']);
    check(FakePcloud::names((int) $sub) === ['a.jpg.flicken-aaaa1111bbbb2222.png'], 'a place taken out takes its patch');

    // Renamed — the patch follows the new name into the new folder's „Bearbeitung".
    $listings = [];
    $item = ['kind' => 'cloud', 'id' => 0, 'name' => 'a.jpg', 'folder' => '106', 'fileid' => '900', 'row' => []];
    $moved = cloud_names_move($pdo, $fake, $item, 'Boletus 01.jpg', '108', 'x', $listings, 100, false);
    $sub2 = array_search([SIDECAR_FOLDER, 108], FakePcloud::$folders, true);
    check($sub2 !== false && FakePcloud::names((int) $sub2) === ['Boletus 01.jpg.flicken-aaaa1111bbbb2222.png']
        && FakePcloud::names((int) $sub) === [], 'renamed and moved: the patch goes along under the new name');
    check(develop_patch_bytes($fake, '900', 'aaaa1111bbbb2222') === $p1, 'and is found there');

    // Carried to another cloud — here: another picture in the same fake.
    develop_patches_carry($fake, '900', $fake, '901', ['aaaa1111bbbb2222', 'eeee5555eeee5555']);
    check(develop_patch_bytes($fake, '901', 'aaaa1111bbbb2222') === $p1, 'a cloud move carries the patch; a missing one is skipped');

    // The picture deleted: its patch is a stray, and „Bearbeitung" goes with it.
    storage_sidecar_delete($fake, 108, 'Boletus 01.jpg');
    check(!isset(FakePcloud::$folders[(int) $sub2]), 'deleting the picture\'s sidecars takes its patches and the emptied folder');
    develop_patches_store($fake, '901', ['cccc3333dddd4444' => $p2]);
    storage_picture_delete($pdo, 0, $fake, 901);
    check(FakePcloud::names((int) $sub) === [] && !isset(FakePcloud::$folders[106]),
        'a picture deleted: its patches are strays, and the empty way goes');
} finally {
    stream_wrapper_restore('https');
}

echo "\nSeveral edits of one picture (0192)\n";
$v1 = ['exposure' => 20];
$cleanAi = photo_develop_clean($develop);
check(develop_patch_ids($cleanAi) === ['aaaa1111bbbb2222', 'cccc3333dddd4444'], 'the cleaning keeps the AI pieces\' ids');
$versions = photo_develop_versions_clean(['at' => 0, 'list' => [$v1, $develop, ['contrast' => 0]]]);
check($versions !== null && $versions['at'] === 0 && $versions['list'][0] === null,
    'the valid edit\'s slot stays null — its settings live in develop');
check($versions['list'][1] === $cleanAi && $versions['list'][2] === null, 'the others cleaned; an edit with nothing set is null');
check(photo_develop_versions_clean(['at' => 0, 'list' => [$v1]]) === null, 'one edit alone is „only one": null');
check(photo_develop_versions_clean(['at' => 3, 'list' => [$v1, $v1]]) === null
    && photo_develop_versions_clean(['list' => [$v1, $v1]]) === null
    && photo_develop_versions_clean('x') === null, 'a slot out of range, no slot, no array: null');
$many = photo_develop_versions_clean(['at' => 11, 'list' => array_fill(0, 12, $v1)]);
check(count($many['list']) === PHOTO_DEVELOP_VERSIONS_MAX && $many['at'] === PHOTO_DEVELOP_VERSIONS_MAX - 1
    && $many['list'][$many['at']] === null, 'more than nine are cut, the valid one kept as the last');
check(photo_develop_versions_read(json_encode($versions)) === $versions, 'the column\'s JSON reads back the same');
$orig = photo_develop_versions_clean(['at' => -1, 'list' => [$v1]]);
check($orig !== null && $orig['at'] === -1 && $orig['list'][0] === photo_develop_clean($v1),
    'the original valid (-1): one edit beside it is kept, and its slot is not emptied');
check(photo_develop_versions_clean(['at' => -1, 'list' => []]) === null
    && photo_develop_versions_clean(['at' => -2, 'list' => [$v1, $v1]]) === null, 'the original valid with no edits, or below -1: null');
$origMany = photo_develop_versions_clean(['at' => -1, 'list' => array_fill(0, 12, $v1)]);
check(count($origMany['list']) === PHOTO_DEVELOP_VERSIONS_MAX && $origMany['at'] === -1, 'the original valid: more than nine are cut, at stays -1');
check(develop_patch_ids_all(['exposure' => 5], $versions) === ['aaaa1111bbbb2222', 'cccc3333dddd4444'],
    'the patches of an edit that is not the valid one count too');
$got = develop_patches_decode(['aaaa1111bbbb2222' => base64_encode($good)], ['exposure' => 5], $versions);
check(array_keys($got) === ['aaaa1111bbbb2222'], 'a patch named only by another edit is taken on save');
check(develop_row_patch_ids(['develop' => json_encode(['exposure' => 5]), 'develop_versions' => json_encode($versions)])
    === ['aaaa1111bbbb2222', 'cccc3333dddd4444'], 'off a row: both columns read — the relay and the move serve them');
$prior = ['develop' => json_encode($develop), 'develop_versions' => null];
$after = ['develop' => json_encode(['exposure' => 5]), 'develop_versions' => json_encode($versions)];
check(array_diff(develop_row_patch_ids($prior), develop_row_patch_ids($after)) === [],
    'the AI edit moved off the valid place into the others: nothing is gone, no patch deleted');
check(photo_develop_any(null, $versions) && !photo_develop_any(null, ['at' => 0, 'list' => [null, null]]),
    'settings anywhere count as edited (the no-cloud gate); all empty does not');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
