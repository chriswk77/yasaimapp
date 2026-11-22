<?php
/**
 * Tests the plates (includes/plates.php, migration 0155).
 *
 *   php tests/plates.php
 *
 * The stored shape first — every number into its range, every key into its
 * list, a picture the find does not hold dropped to an empty cell, a broken
 * or oversized tree refused with a sentence. Then who: the holder and a
 * co-owner each make one plate of their own, with each other's pictures;
 * only the maker changes or deletes his; a second plate of the same person
 * is refused, and so is a save from an older version; a stranger sees none
 * and touches none. The test users,
 * find and rows are removed at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/plates.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}
function refused(callable $f): string
{
    try {
        $f();
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
    return '';
}

echo "\nThe stored shape\n";
$pictures = ['photo:1' => true, 'cloud:2' => true];
$clean = plate_clean([
    'gutter' => 9, 'gutterColor' => 'red',
    'pages' => [['root' => [
        't' => 'split', 'dir' => 'diagonal', 'sizes' => [3, 1, 'x'],
        'kids' => [
            ['t' => 'cell', 'pic' => 'photo:1', 'z' => 99, 'cx' => -1, 'cy' => .3, 'bar' => 'middle', 'id' => 7,
             'items' => [
                 ['kind' => 'text', 's' => '  Sporen: 74–79 (83) × 32–36 (40) µm ', 'x' => .4, 'y' => 2, 'pt' => 300, 'ground' => 'pink'],
                 ['kind' => 'text', 's' => '   '],
                 ['kind' => 'info', 'x' => .1, 'y' => .1, 'fields' => ['species', 'number', 'password', 'species'], 'flow' => 'one'],
             ]],
            ['t' => 'cell', 'pic' => 'photo:999', 'info' => ['fields' => ['gps']]],
            ['t' => 'cell', 'pic' => 'cloud:2', 'info' => ['fields' => ['gps']]],
        ],
    ]]],
], $pictures);
$root = $clean['pages'][0]['root'];
check($clean['gutter'] === 5.0 && $clean['gutterColor'] === '#ffffff', 'the line\'s width into 0–5 mm, its colour white or black');
check($root['dir'] === 'row' && abs(array_sum($root['sizes']) - 1) < 1e-4 && round($root['sizes'][0], 3) === 0.6,
    'a split runs row or column, its shares sum to one (3 : 1 : 1)');
$a = $root['kids'][0];
check($a['z'] === 40.0 && $a['cx'] === 0.0 && $a['cy'] === 0.3 && $a['bar'] === 'br' && !isset($a['id']),
    'a cell\'s zoom, centre and bar corner into their ranges, the editor\'s id not kept');
$medKids = plate_clean(['pages' => [['root' => ['t' => 'split', 'dir' => 'row', 'kids' => [
    ['t' => 'cell', 'pic' => 'cloud:2', 'med' => 'tl'],
    ['t' => 'cell', 'pic' => 'cloud:2', 'med' => 'everywhere'],
    ['t' => 'cell', 'pic' => null, 'med' => 'br'],
]]]]], $pictures)['pages'][0]['root']['kids'];
check($a['med'] === 'off' && $medKids[0]['med'] === 'tl' && $medKids[1]['med'] === 'off' && $medKids[2]['med'] === 'off',
    'the medium\'s corner: off unless asked for, a corner kept, an unknown one off, no medium on a cell without a picture');
check(count($a['items']) === 1,
    'a free text is dropped, filled or empty — the plate carries none since 09.11.2026');
check($a['items'][0]['kind'] === 'info' && $a['items'][0]['fields'] === ['species', 'number'] && $a['items'][0]['flow'] === 'one',
    'a details box keeps only the details it knows, each once');
check($root['kids'][1]['pic'] === null && $root['kids'][1]['info']['fields'] === ['gps'],
    'a picture the find does not hold becomes an empty cell — here a details tile');
check($root['kids'][1]['info']['x'] === null && plate_details(['x' => 2, 'y' => .25], false)['x'] === 1.0
      && plate_details(['x' => 2, 'y' => .25], false)['y'] === .25,
    'a tile\'s words stand at its padding until moved, then where they were put, inside the cell');
check($root['kids'][2]['pic'] === 'cloud:2' && $root['kids'][2]['info'] === null, 'a picture and a details tile exclude each other: the picture wins');

$blocks = plate_clean(['pages' => [['root' => ['t' => 'split', 'dir' => 'col', 'kids' => [
    ['t' => 'cell', 'block' => ['kind' => 'row', 'layer' => '  Sporen ', 'sort' => 'Z'],
     'items' => [['kind' => 'values', 'layer' => 'Sporen', 'lines' => ['mean', 'range', 'median'], 'x' => .1, 'y' => .2]]],
    ['t' => 'cell', 'block' => ['kind' => 'chart', 'layer' => 'Sporen']],
    ['t' => 'cell', 'pic' => 'photo:1', 'block' => ['kind' => 'plot', 'layer' => 'Basidien'],
     'items' => [['kind' => 'values', 'layer' => '   ']]],
]]]]], $pictures)['pages'][0]['root']['kids'];
check($blocks[0]['block'] === ['kind' => 'row', 'layer' => 'Sporen', 'sort' => 'L', 'mark' => null, 'part' => null, 'air' => null, 'gap' => 0.6, 'gapColor' => '#ffffff', 'pad' => 0.6, 'crop' => null, 'count' => false],
    'a row block keeps its layer, trimmed; an unknown order falls back to length; its look starts at the defaults');
$counted = plate_clean(['pages' => [['root' => ['t' => 'cell', 'block' => ['kind' => 'row', 'layer' => 'Lamellen', 'count' => true]]]]], $pictures)
    ['pages'][0]['root']['block'];
$notCounted = plate_clean(['pages' => [['root' => ['t' => 'cell', 'block' => ['kind' => 'row', 'layer' => 'Lamellen', 'count' => 'yes']]]]], $pictures)
    ['pages'][0]['root']['block'];
check($counted['count'] === true && $notCounted['count'] === false,
    'a row of counts keeps its switch; anything but true is a row of measures');
check(plate_block(['kind' => 'row', 'layer' => 'Asci', 'crop' => ' Ascusspitzen Jod '])['crop'] === 'Ascusspitzen Jod'
      && plate_block(['kind' => 'row', 'layer' => 'Asci', 'crop' => ''])['crop'] === ''
      && !array_key_exists('crop', plate_block(['kind' => 'plot', 'layer' => 'Asci', 'crop' => 'x'])),
    'a row of picture cutouts keeps their name, an empty one for the unnamed; only a row carries one');
$insetPictures = ['photo:1' => true];
$inset = plate_inset(['kind' => 'inset', 'x' => .9, 'y' => -1, 'w' => .3, 'h' => 2, 'cell' => ['pic' => 'photo:1', 'z' => 3, 'items' => [['kind' => 'text', 's' => 'x']]]], $insetPictures);
check($inset !== null && $inset['w'] === .3 && $inset['h'] === 1.0 && $inset['x'] === .7 && $inset['y'] === 0.0
      && $inset['cell']['pic'] === 'photo:1' && $inset['cell']['z'] === 3.0 && $inset['cell']['items'] === [],
    'an inset keeps its picture and cutout, lies wholly in its cell and carries no words of its own');
// The writing a picture cell shows (08.10.2026): layer names, each once, only on a picture.
check(plate_notes(['Beschriftung', ' Zahlen ', 'Beschriftung', '', 7, ['x']]) === ['Beschriftung', 'Zahlen']
      && plate_notes('no') === [] && count(plate_notes(array_map(fn($i) => "L$i", range(1, 50)))) === 30,
    'the layers of a cell\'s writing are names, each once, at most thirty');
$cells0 = 0;
$noted = plate_node(['t' => 'cell', 'pic' => 'photo:1', 'notes' => ['Zahlen']], 0, $insetPictures, $cells0);
$blank = plate_node(['t' => 'cell', 'pic' => 'photo:9', 'notes' => ['Zahlen']], 0, $insetPictures, $cells0);
check($noted['notes'] === ['Zahlen'] && $blank['notes'] === [], 'a picture cell keeps its writing, an emptied one loses it');
$measured = plate_node(['t' => 'cell', 'pic' => 'photo:1', 'measures' => ['Sporen', 'Sporen', ' Cheilozystiden ']], 0, $insetPictures, $cells0);
$unmeasured = plate_node(['t' => 'cell', 'pic' => 'photo:9', 'measures' => ['Sporen']], 0, $insetPictures, $cells0);
check($measured['measures'] === ['Sporen', 'Cheilozystiden'] && $unmeasured['measures'] === [],
      'a picture cell keeps the layers whose measures it shows, an emptied one loses them');
$sized = plate_clean(['pages' => [['root' => ['t' => 'cell']]], 'notePt' => 200], []);
$unsized = plate_clean(['pages' => [['root' => ['t' => 'cell']]]], []);
check($sized['notePt'] === 72 && $unsized['notePt'] === null, 'the writing\'s size is a number in points, or none');
check(plate_inset(['cell' => ['pic' => 'photo:9']], $insetPictures) === null
      && plate_inset(['cell' => ['block' => ['kind' => 'row', 'layer' => 'Asci', 'crop' => 'Spitzen']]], $insetPictures)['cell']['block']['crop'] === 'Spitzen',
    'an inset of a picture the find no longer has goes; one of a row of cutouts stays');
$look = plate_block(['kind' => 'row', 'layer' => 'Sporen', 'air' => 250, 'gap' => 1.5, 'gapColor' => '#000000', 'pad' => 99]);
check($look['air'] === 250 && $look['gap'] === 1.5 && $look['gapColor'] === '#000000' && $look['pad'] === 10.0
      && plate_block(['kind' => 'row', 'layer' => 'Sporen', 'air' => 50])['air'] === null,
    'a row keeps its own air, lines, colour and margin, each in its range; an air out of range follows the photo window');
// A cutout row may stand larger than its cut, down to half of it (22.11.2026); a spore row may not.
check(plate_block(['kind' => 'row', 'layer' => 'Sporen', 'crop' => 'Spitzen', 'air' => 60])['air'] === 60
      && plate_block(['kind' => 'row', 'layer' => 'Sporen', 'crop' => 'Spitzen', 'air' => 40])['air'] === null
      && plate_block(['kind' => 'row', 'layer' => 'Sporen', 'air' => 60])['air'] === null,
    'a cutout row keeps an air down to 50 %, a measured row only from 100 %');
check($blocks[0]['items'][0]['lines'] === ['range', 'mean'] && $blocks[0]['items'][0]['name'] === true,
    'a values text keeps the lines it knows, in their own order, and its name in front');
check($blocks[1]['block'] === null, 'a block of a kind nobody knows is dropped');
// The length along the bend or straight (07.10.2026): the plate's own
// choice where it made one, else none — then the photo window's.
check($blocks[0]['items'][0]['straight'] === null
      && plate_block(['kind' => 'plot', 'layer' => 'Sporen', 'straight' => true])['straight'] === true
      && plate_block(['kind' => 'plot', 'layer' => 'Sporen', 'straight' => 'ja'])['straight'] === null
      && !array_key_exists('straight', plate_block(['kind' => 'row', 'layer' => 'Sporen', 'straight' => true])),
    'a values text and a distribution keep their own length reading or none; a row has none to keep');
// Which of the layer's measures (07.10.2026): all, the unmarked, or one mark.
check($blocks[0]['items'][0]['mark'] === null
      && plate_block(['kind' => 'row', 'layer' => 'Pleurozystiden', 'mark' => ' Chrysozystide '])['mark'] === 'Chrysozystide'
      && plate_block(['kind' => 'plot', 'layer' => 'Pleurozystiden', 'mark' => ''])['mark'] === ''
      && plate_block(['kind' => 'plot', 'layer' => 'Pleurozystiden', 'mark' => 3])['mark'] === null,
    'a values text and every block show all, the unmarked or one mark — anything else is all');
// Part measures (07.10.2026): a block names its part, a values text ticks parts among its lines.
check(plate_block(['kind' => 'plot', 'layer' => 'Sporen', 'part' => ' Keimporus '])['part'] === 'Keimporus'
      && plate_block(['kind' => 'row', 'layer' => 'Sporen', 'part' => ''])['part'] === null,
    'a block keeps the part measure it shows, or none');
check(plate_value_lines(['part:Keimporus', 'mean', 'part:Keimporus', 'range', 'part:', 'nonsense', 7])
      === ['range', 'mean', 'part:Keimporus'],
    'a values text keeps its lines in their order and each part once after them');
check($blocks[2]['block'] === null && $blocks[2]['items'] === [],
    'a picture wins over a block; a values text without a layer is dropped');

check(refused(fn() => plate_clean(['pages' => []], $pictures)) !== '', 'a plate without a page is refused');
check(refused(fn() => plate_clean(['pages' => array_fill(0, 7, ['root' => ['t' => 'cell']])], $pictures)) === 'Eine Tafel hat höchstens 6 Seiten.',
    'seven pages are refused with a sentence');
$many = ['t' => 'split', 'dir' => 'row', 'kids' => array_fill(0, 41, ['t' => 'cell'])];
check(refused(fn() => plate_clean(['pages' => [['root' => $many]]], $pictures)) === 'Eine Seite hat höchstens 40 Kacheln.',
    'forty-one cells on a page are refused');
$deep = ['t' => 'cell'];
for ($i = 0; $i < 12; $i++) {
    $deep = ['t' => 'split', 'dir' => 'row', 'kids' => [$deep, ['t' => 'cell']]];
}
check(refused(fn() => plate_clean(['pages' => [['root' => $deep]]], $pictures)) !== '', 'a tree twelve splits deep is refused');
check(refused(fn() => plate_clean(['pages' => [['root' => ['t' => 'split', 'kids' => [['t' => 'cell']]]]]], $pictures)) !== '',
    'a split with one child is refused — it would be a cell');

// THE SOFT GROUND, a tick for all three since 20.11.2026 (plateparts.js, softWanted):
// the bar's true and a medium's or label's false are kept, nothing else.
check(plate_writing(['bar' => true, 'med' => false, 'label' => 'ja'], 'soft') === ['bar' => true, 'med' => false],
    'soft keeps true and false per piece, drops a word');
check(plate_writing(true, 'soft') === ['bar' => true, 'med' => true, 'label' => true],
    'the one bool of the first build still counts for all three');
check(plate_writing(['bar' => false, 'med' => 'white'], 'ink') === ['med' => 'white'],
    'ink keeps only black or white');

if (!plates_ready($pdo)) {
    echo "\n  (migration 0155 not applied — who may do what is not tested)\n";
    echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
    exit($failed === 0 ? 0 : 1);
}

echo "\nWho\n";
$users = [];
foreach (['Holder', 'Mitbesitzer', 'Fremder'] as $name) {
    $pdo->prepare("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
                   VALUES (?, '', 'PRUEF', ?, 'user', NOW())")
        ->execute(['pruef-tafel-' . strtolower($name) . '@pruefung.local', $name]);
    $users[$name] = (int) $pdo->lastInsertId();
}
['Holder' => $holder, 'Mitbesitzer' => $co, 'Fremder' => $stranger] = $users;
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, created_at, updated_at) VALUES (?, 50.1, 8.6, "Boletus edulis", NOW(), NOW())')
    ->execute([$holder]);
$find = (int) $pdo->lastInsertId();
try {
    $pdo->prepare('INSERT INTO map_photos (user_id, point_id, filename, width, height, created_at) VALUES (?, ?, "a.jpg", 1600, 1200, NOW())')
        ->execute([$holder, $find]);
    $holderPhoto = 'photo:' . (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, name, uploaded_at) VALUES (?, ?, "pcloud", "77", "b.jpg", NOW())')
        ->execute([$co, $find]);
    $coPicture = 'cloud:' . (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO map_point_owners (point_id, user_id) VALUES (?, ?)')->execute([$find, $co]);

    $layout = ['pages' => [['root' => ['t' => 'split', 'dir' => 'row', 'sizes' => [1, 1],
        'kids' => [['t' => 'cell', 'pic' => $holderPhoto], ['t' => 'cell', 'pic' => $coPicture]]]]]];
    check(plate_pictures($pdo, $find) === [$holderPhoto => true, $coPicture => true], 'the find\'s pictures: the holder\'s photo and the co-owner\'s recording');

    $mine = plate_save($pdo, $holder, $find, null, $layout);
    check($mine['mine'] && $mine['maker'] === 'PRUEF Holder'
          && array_column($mine['layout']['pages'][0]['root']['kids'], 'pic') === [$holderPhoto, $coPicture],
        'the holder makes a plate with his photo and the co-owner\'s recording');
    check(refused(fn() => plate_save($pdo, $holder, $find, null, $layout)) === 'Du hast an diesem Fund schon eine Tafel.',
        'one plate per person and find: the holder makes no second one');
    check(refused(fn() => plate_save($pdo, $stranger, $find, null, $layout)) !== '', 'a stranger makes none');

    // EACH CO-OWNER HIS OWN — with the pictures of the whole circle.
    $theirs = plate_save($pdo, $co, $find, null, $layout);
    check($theirs['user_id'] === $co && $theirs['mine'] && $theirs['may_edit'], 'the co-owner makes his own plate beside it');
    check(refused(fn() => plate_save($pdo, $co, $find, $mine['id'], $layout)) !== '', 'the co-owner does not change the holder\'s plate');

    $layout['gutter'] = 1.5;
    $changed = plate_save($pdo, $holder, $find, $mine['id'], $layout, $mine['updated_at']);
    check($changed['id'] === $mine['id'] && $changed['layout']['gutter'] === 1.5, 'the maker changes his own');

    // The same plate open on two devices: the older one does not overwrite the newer.
    $pdo->prepare('UPDATE map_plates SET updated_at = updated_at + INTERVAL 7 SECOND WHERE id = ?')->execute([$mine['id']]);
    check(str_starts_with(refused(fn() => plate_save($pdo, $holder, $find, $mine['id'], $layout, $changed['updated_at'])), 'Die Tafel wurde inzwischen'),
        'a save from an older version is refused instead of overwriting the newer one');

    $seen = plates_for_point($pdo, $co, $find);
    check(count($seen) === 2 && $seen[0]['id'] === $theirs['id'] && $seen[0]['may_edit'] && !$seen[1]['may_edit'],
        'the co-owner sees both, his own first and his alone to work on');
    check(plates_for_point($pdo, $stranger, $find) === [], 'the stranger sees none');

    check(!plate_delete($pdo, $holder, $theirs['id']), 'the holder does not delete the co-owner\'s plate');
    check(plate_delete($pdo, $co, $theirs['id']) && count(plates_for_point($pdo, $holder, $find)) === 1, 'its maker does');

    $pdo->prepare('DELETE FROM map_cloud_files WHERE point_id = ?')->execute([$find]);
    $after = plate_save($pdo, $holder, $find, $mine['id'], $layout);
    check(array_column($after['layout']['pages'][0]['root']['kids'], 'pic') === [$holderPhoto, null],
        'a recording deleted meanwhile leaves an empty cell when the plate is saved again');
} finally {
    $pdo->prepare('DELETE FROM map_plates WHERE point_id = ?')->execute([$find]);
    $pdo->prepare('DELETE FROM map_point_owners WHERE point_id = ?')->execute([$find]);
    $pdo->prepare('DELETE FROM map_cloud_files WHERE point_id = ?')->execute([$find]);
    $pdo->prepare('DELETE FROM map_photos WHERE point_id = ?')->execute([$find]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$find]);
    foreach ($users as $id) {
        $pdo->prepare('DELETE FROM map_users WHERE id = ?')->execute([$id]);
    }
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
