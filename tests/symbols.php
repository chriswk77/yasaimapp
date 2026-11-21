<?php
/**
 * Tests that map and interface know the same marker shapes.
 *
 *   php tests/symbols.php
 *
 * WHY THIS TEST EXISTS. A shape stands in three places: SHAPE_NAMES in
 * map.js says what the picker offers, LIST_SYMBOLS in data.php says
 * what the server accepts, and shapes_all() in shapes.php has the
 * drawing for it. If those drift apart there is no error but a silent
 * fallback — list_symbol() turns everything unknown into a circle.
 *
 * EXACTLY THAT HAPPENED: tree, insect, deer and lichen were only added
 * in map.js. The picker showed them, on saving they became a circle
 * without comment — seen live before anyone here noticed.
 *
 * From map.js the SOURCE TEXT is read, not the running code: the test
 * needs neither database nor browser and thus runs everywhere. The price
 * is that it hangs on the spellings of the constants there — if their
 * form changes, it fails and at least points to the right place. The
 * drawings meanwhile live in PHP and are therefore asked instead of
 * read; that is the more robust route where it is open.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "\nSymbols — map and interface in lockstep\n\n";

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/shapes.php';

$js  = (string) file_get_contents(__DIR__ . '/../assets/js/map.js');
$php = (string) file_get_contents(__DIR__ . '/../includes/data.php');

// --- The two lists from map.js -------------------------------------------

preg_match('/const SHAPE_NAMES = \{(.*?)\};/s', $js, $t);
check(isset($t[1]), 'SHAPE_NAMES found in map.js');
// t?\( — the labels ride through t() since the language sweep
// (28.08.2026); the KEYS of the map are what this test counts.
preg_match_all('/(\w+):\s*(?:t\()?\'/', $t[1] ?? '', $m);
$offered = $m[1];

// The drawings are PHP — so asked instead of read, not parsed out of the
// file: whatever shapes_all() really hands over is what counts.
$drawn = array_keys(shapes_all());
check($drawn !== [], 'shapes_all() found in includes/shapes.php');

preg_match('/const SHAPE_ANCHOR = \{(.*?)\};/s', $js, $t);
preg_match_all('/(\w+):\s*\[/', $t[1] ?? '', $m);
$anchored = $m[1];
preg_match_all('/^SHAPE_ANCHOR\.(\w+)\s*=/m', $js, $m);
$anchored = array_merge($anchored, $m[1]);

// --- The two lists from data.php ------------------------------------------

preg_match('/const LIST_SYMBOLS = \[(.*?)\];/s', $php, $t);
check(isset($t[1]), 'LIST_SYMBOLS found in data.php');
preg_match_all("/'(\w+)'/", $t[1] ?? '', $m);
$allowed = $m[1];

preg_match('/const LIST_SYMBOL_ALIAS = \[(.*?)\];/s', $php, $t);
$alias = [];
if (isset($t[1])) {
    preg_match_all("/'(\w+)'\s*=>\s*'(\w+)'/", $t[1], $m, PREG_SET_ORDER);
    foreach ($m as $pair) {
        $alias[$pair[1]] = $pair[2];
    }
}

// --- The lockstep -----------------------------------------------------------

check($offered !== [] && $allowed !== [], 'both lists are non-empty');

foreach ($offered as $shape) {
    check(in_array($shape, $allowed, true),
        "'$shape' is offered by the picker — does the server accept it?");
}

foreach ($allowed as $shape) {
    check(in_array($shape, $drawn, true),
        "the server accepts '$shape' — can the map draw it?");
}

foreach ($alias as $old => $new) {
    check(in_array($new, $allowed, true),
        "alias '$old' points to '$new' — and that one is allowed");
    check(!in_array($old, $offered, true),
        "alias '$old' no longer stands in the picker");
}

// What has a foot needs an anchor; the reverse check would be wrong, for
// whatever has none simply hangs centred — that is allowed (insect).
foreach ($anchored as $shape) {
    check(in_array($shape, $drawn, true),
        "anchor '$shape' belongs to a drawable shape");
}

/* THE FALLBACK MUST SURVIVE AN UNKNOWN NAME, and without throwing. After
   the rename into English it still pointed at the old key 'kreis', which
   no longer existed: shape_geometry() returned null although it promises
   a string. That surfaced only on the server, where the database still
   carried the German values — there EVERY call ran into the fallback. The
   TypeError struck in the middle of building the administration, and
   everything below it was missing, scripts included: no tabs any more,
   and the area list stood on its placeholder forever.

   An unknown name is nothing out of the ordinary — it arises with every
   rename, every device on an old program, every row from an import. These
   three checks cost nothing and would have prevented the outage. */
foreach (['gibtesnicht', 'kreis', '', null] as $unbekannt) {
    try {
        $geometrie = shape_geometry($unbekannt);
        check(is_string($geometrie) && $geometrie !== '',
            'shape_geometry(' . var_export($unbekannt, true) . ') yields a drawing');
    } catch (Throwable $e) {
        check(false, 'shape_geometry(' . var_export($unbekannt, true) . ') throws: ' . $e->getMessage());
    }
}
try {
    check(str_contains(shape_point('gibtesnicht', null), '<svg'),
        'shape_point() survives an unknown shape');
} catch (Throwable $e) {
    check(false, 'shape_point() throws on an unknown shape: ' . $e->getMessage());
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
