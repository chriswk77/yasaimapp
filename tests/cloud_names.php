<?php
/**
 * Tests the pictures' names in the cloud (includes/cloud_names.php).
 *
 *   php tests/cloud_names.php
 *
 * THE SCHEME is held to the maintainer's own examples (30.09. and 04.10.2026):
 * „Boletus edulis 2026-08-29 DE,BAY, Krün 02 Sporen.jpg" in the folder
 * „2026-08-29 Krün wk-00554". THE WORKER is run against pCloud played in
 * memory (tests/fake_pcloud.php), which replaces a namesake on rename as
 * the real one does — so a test that passes here proves no picture was
 * renamed onto another. Needs migration 0151; the test find and its rows
 * are removed at the end.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud_names.php';
require_once __DIR__ . '/../includes/cloud.php';
require_once __DIR__ . '/fake_pcloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nThe name, as it is written by hand\n";
$krun = ['found_at' => '2026-08-29 10:12:00', 'country' => 'DE', 'region' => 'BAY', 'municipality' => 'Krün'];
check(cloud_name_head('Boletus edulis', $krun) === 'Boletus edulis 2026-08-29 DE,BAY, Krün',
    'species, day, country and region joined by a comma, the municipality after a comma and a space');
check(cloud_name_head('Boletus edulis', ['region' => ''] + $krun) === 'Boletus edulis 2026-08-29 DE, Krün'
      && cloud_name_head('Boletus edulis', ['municipality' => ''] + $krun) === 'Boletus edulis 2026-08-29 DE,BAY',
    'a part the find does not know is left out with its comma');
$stems = cloud_name_stems('Boletus edulis 2026-08-29 DE,BAY, Krün', ['Hauptmotiv', 'Sporen', null, 'Sporen', 'Sporen 10/100']);
check($stems === [
        'Boletus edulis 2026-08-29 DE,BAY, Krün 01 Hauptmotiv',
        'Boletus edulis 2026-08-29 DE,BAY, Krün 02 Sporen',
        'Boletus edulis 2026-08-29 DE,BAY, Krün 03',
        'Boletus edulis 2026-08-29 DE,BAY, Krün 04 Sporen',
        'Boletus edulis 2026-08-29 DE,BAY, Krün 05 Sporen 10-100',
    ], 'one number, the place in the gallery, two digits — no second count for a repeated name; the slash a hyphen');
$stack = cloud_name_stems('X', ['Hauptmotiv', 'Sporen', null], [1 => true, 2 => true]);
check($stack === ['X 01 Hauptmotiv', 'X 02 Sporen stacked', 'X 03 stacked'],
    'a focus stack says „stacked" after its subject name, or after the number without one (27.10.2026)');
check(cloud_name_stacked('Mikroskop 2026-10-27 18-41-44 stacked 10.jpg')
      && cloud_name_stacked('Microscope 2026-10-27 18-41-44 stacked 3')
      && !cloud_name_stacked('Mikroskop 2026-10-27 18-41-44.jpg')
      && !cloud_name_stacked('stacked rocks.jpg') && !cloud_name_stacked(null),
    'a stack is told by „stacked <frames>" at the end of its original name, whatever the language before it');
$many = cloud_name_stems('X', array_fill(0, 120, 'Sporen'));
check($many[0] === 'X 001 Sporen' && $many[119] === 'X 120 Sporen', 'from the hundredth picture on, three digits');
check(cloud_name_fits('X 04 (2).jpg', 'X 04', 'jpg') && cloud_name_fits('X 04.jpg', 'X 04', 'jpg')
      && !cloud_name_fits('X 05.jpg', 'X 04', 'jpg'),
    'the wanted name fits, and so does its „ (2)" beside a foreign namesake');

echo "\nThe numbers follow the gallery as it is shown (06.10.2026)\n";
// Finn's find: the spores went up first, the Hauptmotiv after — the gallery
// shows the Hauptmotiv first, and so must the file names.
$pic = static fn(string $kind, int $id, ?string $title, ?int $order): array =>
    ['kind' => $kind, 'row' => ['id' => $id, 'title' => $title, 'sort_order' => $order]];
$ordered = cloud_names_gallery_order([
    $pic('cloud', 1, 'Sporen', 0),
    $pic('cloud', 2, 'Hauptmotiv', 1),
    $pic('cloud', 3, null, 2),
    $pic('cloud', 4, 'Habitat', 3),
    $pic('photo', 5, 'Sporen', 4),
    $pic('cloud', 6, 'Mein eigener Name', 5),
], ['Hauptmotiv', 'Habitat', 'Sporen']);
check(array_map(static fn(array $p): int => $p['row']['id'], $ordered) === [2, 4, 1, 5, 6, 3],
    'grouped as the gallery: Hauptmotiv, Habitat, the two Sporen as dragged, a free name, the unnamed last');
$tied = cloud_names_gallery_order([$pic('cloud', 9, 'Sporen', 0), $pic('photo', 8, 'Sporen', 0), $pic('cloud', 7, 'Sporen', null)],
    ['Sporen']);
check(array_map(static fn(array $p): int => $p['row']['id'], $tied) === [8, 9, 7],
    'on the same place the voucher photo before the recording; one without a place behind');
// The lists of the maintainer and Finn start with „Belegfoto" — the Hauptmotiv
// leads all the same, the Belegfoto follows.
$led = cloud_names_gallery_order([
    $pic('cloud', 11, 'Sporen', 0), $pic('cloud', 12, 'Belegfoto', 1), $pic('cloud', 13, 'Hauptmotiv', 2),
], ['Belegfoto', 'Hauptmotiv', 'Kollektion', 'Sporen']);
check(array_map(static fn(array $p): int => $p['row']['id'], $led) === [13, 12, 11],
    'the Hauptmotiv leads although the list starts with Belegfoto — then Belegfoto, then the spores');

echo "\nThe species and the find's own folder\n";
check(cloud_name_species($pdo, ['user_id' => 1, 'title' => 'Boletus cf. edulis']) === 'Boletus cf. edulis',
    '„cf." stands between the names — and no longer files the find under „B. cf."');
check(cloud_name_species($pdo, ['user_id' => 1, 'title' => 'Boletus edulis s. l.']) === 'Boletus edulis s. l.',
    '„s. l." stands behind');
check(cloud_name_species($pdo, ['user_id' => 1, 'title' => 'Scutellinia spec.']) === 'Scutellinia spec.',
    'a genus alone is „spec.", as its folder');
check(cloud_name_species($pdo, ['user_id' => 1, 'title' => 'Xyzzy plugh']) === 'Unbestimmt',
    'no genus, „Unbestimmt", as its folder');
$parts = cloud_path_parts(['genus' => 'Boletus', 'species' => 'edulis', 'kingdom' => 'Fungi (Pilze)', 'group' => 'Fungi',
    'division' => 'Basidiomycota', 'found_at' => '2026-08-29 10:12:00', 'municipality' => 'Krün', 'number' => 'wk-00554']);
check(end($parts['below']) === '2026-08-29 Krün wk-00554', 'the find\'s folder carries its number: „2026-08-29 Krün wk-00554"');
check(cloud_undetermined_parts(['found_at' => '2026-08-29', 'municipality' => 'Krün'], 'Y-17')
      === ['Arten', 'Unbestimmt', '2026-08-29 Krün Y-17'], 'so does a find without a name, under „Arten/Unbestimmt"');
// „Unbestimmt" inside a kingdom (18.11.2026) is still „Unbestimmt" — a picture there follows its find, as on top.
check(cloud_path_undetermined('Fungi (Pilze)/Lichenes/Unbestimmt/2026-08-29 Krün Y-17')
      && cloud_path_undetermined('Unbestimmt/2026-08-29 Krün Y-17'), 'an „Unbestimmt" folder is known on top and inside a kingdom');
check(!cloud_path_undetermined('Fungi (Pilze)/Fungi/Basidiomycota/B/Boletus/B. edulis/2026-08-29 Krün wk-00554'),
      'a named find\'s folder is none');
// „Myxo spec." is a slime mould without a name, not a genus „Myxo" (18.11.2026) — and Myxomphalia stays a fungus.
check(cloud_myxo_unnamed('Myxo spec.') && cloud_myxo_unnamed('Myxo') && cloud_myxo_unnamed('Myxomyceten')
      && cloud_myxo_unnamed('Schleimpilz sp.'), '„Myxo spec.", „Myxo", „Myxomyceten", „Schleimpilz sp." are slime moulds without a name');
check(!cloud_myxo_unnamed('Myxomphalia maura') && !cloud_myxo_unnamed('Physarum album') && !cloud_myxo_unnamed(''),
      'a genus that only begins like it, a named slime mould and an empty title are not');

if (!cloud_names_ready($pdo)) {
    echo "\n  (migration 0151 not applied — the worker is not tested)\n";
    echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
    exit($failed === 0 ? 0 : 1);
}

echo "\nThe worker, against pCloud played in memory\n";
$pdo->exec("INSERT INTO map_users (email, password_hash, first_name, last_name, role, created_at)
            VALUES ('pruef-namen@pruefung.local', '', 'PRUEF', 'Namen', 'user', NOW())");
$user = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO map_points (user_id, lat, lon, title, found_at, country, region, municipality, find_number, created_at, updated_at)
               VALUES (?, 47.48, 11.27, ?, ?, "DE", "BAY", "Krün", "wk-T1", NOW(), NOW())')
    ->execute([$user, 'Boletus edulis', '2026-08-29 10:12:00']);
$point = (int) $pdo->lastInsertId();
$recording = static function (int $fileid, string $name, ?string $title, int $order) use ($pdo, $user, $point): int {
    $pdo->prepare('INSERT INTO map_cloud_files (user_id, point_id, provider, fileid, folderid, name, original_name, path, size, title, sort_order, uploaded_at)
                   VALUES (?, ?, "pcloud", ?, "106", ?, ?, "alt", 1, ?, ?, NOW())')
        ->execute([$user, $point, (string) $fileid, $name, $name, $title, $order]);
    return (int) $pdo->lastInsertId();
};

$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];
$open = static fn(int $u, string $p): array => $fake;
FakePcloud::reset([100 => ['', null], 106 => ['alt', 100]], [
    900 => ['P1010001.JPG', 106],
    901 => ['P1010002.JPG', 106],
    902 => ['P1010003.JPG', 106],
    904 => ['P1010002.JPG.messwerte.json', 106],   // the old sidecar of 901
    950 => ['Fremd.JPG', 106],                     // another find's, stays
]);
$a = $recording(900, 'P1010001.JPG', 'Hauptmotiv', 0);
$b = $recording(901, 'P1010002.JPG', 'Sporen', 1);
$c = $recording(902, 'P1010003.JPG', 'Sporen', 2);
$name = static function (int $row) use ($pdo): array {
    $q = $pdo->prepare('SELECT name, path, folderid, original_name FROM map_cloud_files WHERE id = ?');
    $q->execute([$row]);
    return $q->fetch();
};

stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    $r = cloud_names_point($pdo, $point, microtime(true) + 30, $open);
    $folder = (int) $name($a)['folderid'];
    check($r['done'] && $r['renamed'] === 3 && $r['failed'] === 0, 'three pictures moved and renamed, nothing failed');
    check((FakePcloud::$folders[$folder][0] ?? '') === '2026-08-29 Krün wk-T1',
        'into the find\'s own folder „2026-08-29 Krün wk-T1"');
    $head = 'Boletus edulis 2026-08-29 DE,BAY, Krün ';
    check(FakePcloud::name(900) === $head . '01 Hauptmotiv.JPG' && FakePcloud::name(901) === $head . '02 Sporen.JPG'
          && FakePcloud::name(902) === $head . '03 Sporen.JPG', 'named by place and subject: 01 Hauptmotiv, 02 Sporen, 03 Sporen');
    check($name($b)['name'] === $head . '02 Sporen.JPG' && $name($b)['original_name'] === 'P1010002.JPG'
          && str_ends_with($name($b)['path'], 'B. edulis/2026-08-29 Krün wk-T1'),
        'the row knows the new name and place — and still the camera\'s');
    $bearbeitung = null;
    foreach (FakePcloud::$folders as $fid => [$fn, $fp]) {
        if ($fp === $folder && $fn === SIDECAR_FOLDER) { $bearbeitung = $fid; }
    }
    check(!isset(FakePcloud::$files[904]) && $bearbeitung !== null
          && in_array($head . '02 Sporen.JPG.messwerte.json', FakePcloud::names($bearbeitung), true)
          && !in_array($head . '02 Sporen.JPG.messwerte.json', FakePcloud::names($folder), true),
        'the old sidecar went, a new one stands under the new name in „Bearbeitung“');
    $side = null;
    foreach (FakePcloud::$files as $id => [$n]) {
        if ($n === $head . '02 Sporen.JPG.messwerte.json') { $side = json_decode(FakePcloud::$bytes[$id], true); }
    }
    check(($side['image']['name'] ?? '') === $head . '02 Sporen.JPG' && ($side['image']['original'] ?? '') === 'P1010002.JPG'
          && ($side['title'] ?? '') === 'Sporen', 'the sidecar names the picture inside, and the camera\'s name');
    check(isset(FakePcloud::$folders[106]) && FakePcloud::names(106) === ['Fremd.JPG'],
        'the old day folder stays while another find\'s picture lies in it');

    // A swap: the third picture moves to second place — its wanted name is
    // the one the second still wears.
    $pdo->prepare('UPDATE map_cloud_files SET sort_order = 1 WHERE id = ?')->execute([$c]);
    $pdo->prepare('UPDATE map_cloud_files SET sort_order = 2 WHERE id = ?')->execute([$b]);
    $r = cloud_names_point($pdo, $point, microtime(true) + 30, $open);
    check(FakePcloud::name(902) === $head . '02 Sporen.JPG' && FakePcloud::name(901) === $head . '03 Sporen.JPG',
        'dragged into each other\'s places, the two swap names');
    check(FakePcloud::$replaced === [] && count(array_filter(FakePcloud::names($folder),
            static fn(string $n): bool => str_contains($n, CLOUD_NAMES_PARKED))) === 0,
        'no picture replaced another on the way, and none is left parked');
    $sides = FakePcloud::names((int) $bearbeitung);
    check($sides === [$head . '01 Hauptmotiv.JPG.messwerte.json', $head . '02 Sporen.JPG.messwerte.json',
            $head . '03 Sporen.JPG.messwerte.json'],
        'in „Bearbeitung“ one sidecar per picture under its new name — none left from before the swap');

    // A foreign file holds the name a fourth picture wants.
    FakePcloud::$files[960] = [$head . '04.JPG', $folder];
    FakePcloud::$files[903] = ['IMG_9.JPG', 106];
    $d = $recording(903, 'IMG_9.JPG', null, 3);
    cloud_names_point($pdo, $point, microtime(true) + 30, $open);
    check(FakePcloud::name(960) === $head . '04.JPG' && FakePcloud::name(903) === $head . '04 (2).JPG',
        'beside a file of the user\'s own the picture becomes „04 (2)" — the other file is untouched');
    $calls = count(FakePcloud::$calls);
    $r = cloud_names_point($pdo, $point, microtime(true) + 30, $open);
    check($r['renamed'] === 0 && count(FakePcloud::$calls) === $calls,
        'a find whose names are right costs no call to the cloud');
    $opened = 0;
    cloud_names_point($pdo, $point, microtime(true) + 30, static function (int $u, string $p) use (&$opened, $fake): array {
        $opened++;
        return $fake;
    });
    check($opened === 0, '… and the cloud is not even opened — at Drive that alone would be a call to Google');

    // „cf." taken in, then a new find number.
    $pdo->prepare('UPDATE map_points SET title = "Boletus cf. edulis" WHERE id = ?')->execute([$point]);
    cloud_names_point($pdo, $point, microtime(true) + 30, $open);
    check(FakePcloud::name(900) === 'Boletus cf. edulis 2026-08-29 DE,BAY, Krün 01 Hauptmotiv.JPG'
          && (int) $name($a)['folderid'] === $folder, '„cf." in the title is „cf." in every name — the folder stays');
    $pdo->prepare('UPDATE map_points SET find_number = "wk-T2" WHERE id = ?')->execute([$point]);
    cloud_names_point($pdo, $point, microtime(true) + 30, $open);
    $moved = (int) $name($a)['folderid'];
    check((FakePcloud::$folders[$moved][0] ?? '') === '2026-08-29 Krün wk-T2' && FakePcloud::names($folder) === [$head . '04.JPG'],
        'renumbered, the pictures follow into „… wk-T2" — only the user\'s own file stays behind');

    // LEFT-OVER SIDECARS (22.11.2026): a save wrote its sidecar under the
    // name a picture had a moment before the renaming — 203 of those lay in
    // the maintainer's cloud. The run sweeps its folder when it is through.
    $sub = null;
    foreach (FakePcloud::$folders as $fid => [$fn, $fp]) {
        if ($fp === $moved && $fn === SIDECAR_FOLDER) { $sub = $fid; }
    }
    $nfd = static fn(string $n): string => str_replace("\u{00FC}", "u\u{0308}", $n);
    FakePcloud::$files[970] = ['Mikroskop 2026-11-22 15-22-27.JPG.messwerte.json', (int) $sub];   // under the upload name
    FakePcloud::$files[971] = ['Weg.JPG.flicken-abcdef12.png', (int) $sub];                         // pixels: not the sweep's
    // A picture named before the provider kept the spelling: „ü" as u + dots up there, one sign in the row.
    $second = FakePcloud::name(901);
    FakePcloud::$files[901][0] = $nfd((string) $second);
    check($nfd((string) $second) !== $second && cloud_name_key($nfd((string) $second)) === $second,
        'the two spellings of „ü" are different bytes and one name to the comparison key');
    $pdo->prepare('UPDATE map_cloud_files SET title = "Sporen" WHERE id = ?')->execute([$d]);
    $r = cloud_names_point($pdo, $point, microtime(true) + 30, $open);
    $sides = FakePcloud::names((int) $sub);
    check($r['renamed'] === 1 && !isset(FakePcloud::$files[970]), 'after a renaming the sidecar left under an upload name is gone');
    check(isset(FakePcloud::$files[971]), 'a patch is pixels and is not the sweep\'s to take');
    check(in_array($second . '.messwerte.json', $sides, true),
        'the sidecar of a picture spelt the other way stays — it only looks unpaired');
    check(in_array((string) FakePcloud::name(903) . '.messwerte.json', $sides, true)
          && in_array((string) FakePcloud::name(900) . '.messwerte.json', $sides, true), 'every picture keeps its own');
    unset(FakePcloud::$files[971]);
    FakePcloud::$files[901][0] = (string) $second;

    // The writer's side of the same gap: its upload landed after the renaming.
    $write = static fn(string $fileid, string $title): ?array => measure_sidecar_write($fake, $fileid, null, [], null, null, $title);
    FakePcloud::$files[972] = ['P1010001.JPG.messwerte.json', (int) $sub];
    measure_sidecar_settle($pdo, $user, $fake, '900', ['name' => 'P1010001.JPG', 'folder' => (string) $moved,
        'sidecar' => '972', 'again' => static fn() => $write('900', 'Hauptmotiv')]);
    check(!isset(FakePcloud::$files[972]) && in_array((string) FakePcloud::name(900) . '.messwerte.json', FakePcloud::names((int) $sub), true),
        'a sidecar written under the name of a moment ago goes, and stands under the name the picture has now');
    // … and it had replaced the sidecar of the picture that took that name: that one gets its own back.
    $third = (string) FakePcloud::name(902);
    $thirdSide = null;
    foreach (FakePcloud::$files as $id => [$n, $parent]) {
        if ($parent === (int) $sub && $n === $third . '.messwerte.json') { $thirdSide = $id; }
    }
    FakePcloud::$bytes[$thirdSide] = '{"image":{"name":"somebody else"}}';
    measure_sidecar_settle($pdo, $user, $fake, '900', ['name' => $third, 'folder' => (string) $moved,
        'sidecar' => (string) $thirdSide, 'again' => static fn() => $write('900', 'Hauptmotiv')]);
    $back = null;
    foreach (FakePcloud::$files as $id => [$n, $parent]) {
        if ($parent === (int) $sub && $n === $third . '.messwerte.json') { $back = json_decode(FakePcloud::$bytes[$id], true); }
    }
    check(($back['image']['name'] ?? '') === $third && ($back['title'] ?? '') === 'Sporen',
        'the picture that holds the old name now has its own sidecar again, not the other\'s measures');
    // The usual save: the row's name is the one written under — the cloud is not asked a second time.
    $calls = count(FakePcloud::$calls);
    measure_sidecar_settle($pdo, $user, $fake, '900', ['name' => (string) FakePcloud::name(900), 'folder' => (string) $moved, 'sidecar' => '1']);
    check(count(FakePcloud::$calls) === $calls, 'a save while nothing is renamed costs no further call');
    // A file renamed by hand: row and cloud disagree for good — the sidecar lies right where it is.
    $own = (string) FakePcloud::name(903);
    FakePcloud::$files[903][0] = 'Von Hand.JPG';
    FakePcloud::$files[973] = ['Von Hand.JPG.messwerte.json', (int) $sub];
    measure_sidecar_settle($pdo, $user, $fake, '903', ['name' => 'Von Hand.JPG', 'folder' => (string) $moved, 'sidecar' => '973']);
    check(isset(FakePcloud::$files[973]), 'beside a picture renamed by hand its sidecar stays');
    unset(FakePcloud::$files[973]);
    FakePcloud::$files[903][0] = $own;

    // The queue: marked, claimed, done, gone.
    cloud_names_touch($pdo, $point);
    $due = static fn(): int => (int) $pdo->query('SELECT COUNT(*) FROM map_cloud_names_due WHERE point_id = ' . $point)->fetchColumn();
    check($due() === 1, 'a change marks the find');
    $r = cloud_names_claimed($pdo, $point, microtime(true) + 30, $open);
    check($r !== null && $r['done'] && $due() === 0, 'the run takes it, and done it leaves the queue');
} finally {
    stream_wrapper_restore('https');
    $pdo->prepare('DELETE FROM map_cloud_names_due WHERE point_id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_cloud_files WHERE point_id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_points WHERE id = ?')->execute([$point]);
    $pdo->prepare('DELETE FROM map_users WHERE id = ?')->execute([$user]);
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
