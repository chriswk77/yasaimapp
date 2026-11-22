<?php
/**
 * Tests the sweep of a whole cloud (storage_tidy in includes/cloud.php) —
 * the button under Administration → Cloud-Anbieter → „Aufräumen".
 *
 *   php tests/cloud_tidy.php
 *
 * THE TREE IS WHAT A MOVED STOCK LEAVES (the maintainer's pCloud,
 * 22.11.2026): an old branch whose finds went elsewhere — an empty species
 * folder, a find folder that holds nothing but a sidecar left under an old
 * name, one whose „Bearbeitung" kept a sidecar and a patch of a picture
 * that is gone — beside a living find, and a folder with a file no row
 * knows. Against pCloud played in memory (tests/fake_pcloud.php). Nothing
 * in the database is touched.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cloud.php';
require_once __DIR__ . '/fake_pcloud.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

$fake = ['provider' => 'pcloud', 'connection' => [], 'token' => 'fake', 'host' => 'fake.pcloud'];
$tree = static function (): void {
    FakePcloud::reset([
        100 => ['', null],
        200 => ['Fungi (Pilze)', 100],                // the old branch — everything of ours left it
        210 => ['B. edulis', 200],                    // empty
        220 => ['2026-09-11 Schlangenbad', 200],      // only a sidecar under an old name
        230 => ['2026-11-05 Bettenfeld wk-1', 200],   // only a „Bearbeitung" with left-overs
        231 => ['Bearbeitung', 230],
        240 => ['2026-10-23 Wiesbaden wk-2', 200],    // a file no row knows
        300 => ['Arten', 100],
        310 => ['2026-11-21 Wiesbaden wk-3', 300],    // a living find
        311 => ['Bearbeitung', 310],
        400 => ['Datensicherung', 100],               // kept, empty or not
        500 => ['Kalibrierung', 100],
    ], [
        900 => ['2026-09-11 17891133.jpg.messwerte.json', 220],
        901 => ['Myxo 01 Hauptmotiv.jpg.messwerte.json', 231],
        902 => ['Myxo 01 Hauptmotiv.jpg.flicken-abcdef12.png', 231],
        903 => ['aufnahme-1792746435254.jpg', 240],
        910 => ['Mycena 01 Hauptmotiv.jpg', 310],
        911 => ['Mycena 02 Sporen.jpg', 310],
        912 => ['Mycena 01 Hauptmotiv.jpg.messwerte.json', 311],
        913 => ['Mycena 02 Sporen.jpg.messwerte.json', 311],
        914 => ['Mikroskop 2026-11-22 15-22-27.jpg.messwerte.json', 311],   // under the upload name — a stray
        915 => ['Mycena 01 Hauptmotiv.jpg.flicken-abcdef12.png', 311],
        916 => ['Alt.jpg.messwerte.json', 310],                                // an old one beside the pictures, picture gone
    ]);
};

stream_wrapper_unregister('https');
stream_wrapper_register('https', FakePcloud::class);
try {
    echo "A deadline already passed touches nothing\n";
    $tree();
    $r = storage_tidy($pdo, 0, $fake, microtime(true) - 1);
    check(!$r['done'] && $r['sidecars'] === 0 && $r['folders'] === 0 && count(FakePcloud::$files) === 11 && count(FakePcloud::$folders) === 12,
        'nothing went, and it says it is not through');

    echo "\nThe sweep\n";
    $r = storage_tidy($pdo, 0, $fake, microtime(true) + 30);
    check($r['done'] && $r['sidecars'] === 5 && $r['folders'] === 4,
        'five left-overs and four folders went: the sidecars without a picture, the patch of a picture that is gone, the empty ways');
    check(!isset(FakePcloud::$folders[210]) && !isset(FakePcloud::$folders[220]) && !isset(FakePcloud::$folders[230]) && !isset(FakePcloud::$folders[231]),
        'the empty species folder, the folder with only a left-over sidecar, and the one with only a „Bearbeitung" of left-overs are gone');
    check(isset(FakePcloud::$folders[240]) && isset(FakePcloud::$files[903]) && isset(FakePcloud::$folders[200]),
        'a file no row knows is not the sweep\'s to judge — it stays, and its folders with it');
    check(FakePcloud::names(310) === ['Mycena 01 Hauptmotiv.jpg', 'Mycena 02 Sporen.jpg']
          && FakePcloud::names(311) === ['Mycena 01 Hauptmotiv.jpg.flicken-abcdef12.png', 'Mycena 01 Hauptmotiv.jpg.messwerte.json',
                                         'Mycena 02 Sporen.jpg.messwerte.json'],
        'the living find keeps its pictures, their sidecars and the patch — the sidecar under the upload name and the old one beside went');
    check(isset(FakePcloud::$folders[400]) && isset(FakePcloud::$folders[500]) && isset(FakePcloud::$folders[100]),
        'the backup\'s and the calibration\'s folder stay though empty, and the app\'s own folder always');

    $again = storage_tidy($pdo, 0, $fake, microtime(true) + 30);
    check($again === ['sidecars' => 0, 'folders' => 0, 'done' => true], 'a second sweep finds nothing left');

    // Once the unknown file is gone, the whole old branch goes with the next sweep.
    unset(FakePcloud::$files[903]);
    $r = storage_tidy($pdo, 0, $fake, microtime(true) + 30);
    check($r['folders'] === 2 && !isset(FakePcloud::$folders[200]) && isset(FakePcloud::$folders[300]),
        'with nothing real left in it, the old top folder itself goes — the new one stays');

    echo "\nThe two spellings of an umlaut\n";
    if (class_exists('Normalizer')) {
        $nfd = static fn(string $n): string => Normalizer::normalize($n, Normalizer::FORM_D);
        FakePcloud::reset([100 => ['', null], 310 => ['Fund', 100], 311 => ['Bearbeitung', 310]], [
            910 => [$nfd('Krün 01 Größenvergleich.jpg'), 310],
            912 => ['Krün 01 Größenvergleich.jpg.messwerte.json', 311],
        ]);
        $r = storage_tidy($pdo, 0, $fake, microtime(true) + 30);
        check($r['sidecars'] === 0 && isset(FakePcloud::$files[912]), 'a sidecar whose picture is spelt the other way is paired, not swept');
    } else {
        echo "  (no Normalizer on this PHP)\n";
    }
} finally {
    stream_wrapper_restore('https');
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
