<?php
/**
 * Keeps the two haystacks in lockstep.
 *
 *   php tests/haystack.php        (from the project directory)
 *
 * WHAT THIS IS ABOUT. The filter (assets/js/filter.js) searches across
 * ALL fields of a point in the sidebar and in Organisieren. But each page
 * builds the searchable text itself:
 *
 *   pointHaystack()  in assets/js/haystack.js — BOTH browser views since
 *                     25.08.2026; Organisieren used to get the finished
 *                     text from the server and builds it itself now
 *   point_haystack() in includes/data.php — for the auto lists, which
 *                     match server-side where no browser is
 *
 * TWO COPIES OF THE SAME FIELD LIST. Whoever adds a field over there and
 * not here gets two search results in two views — and only notices when
 * somebody searches in Organisieren for something they found in the sidebar.
 * Exactly the state the shared filter set out against.
 *
 * Therefore both are tested: that the PHP side really takes every field
 * in, AND that the JS side names the same fields. The second goes via
 * the source text — rough, but it fires when a field is added there
 * that is missing here.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/data.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

/** The fields that belong in the haystack. One list, two tests. */
const FIELDS = ['title', 'note', 'mtb', 'municipality', 'region', 'country',
                'protected_area', 'extra'];

echo "The PHP side takes every field in:\n";
$point = [
    'title'        => 'Boletus PRUEFTITEL',
    'note'        => 'PRUEFNOTIZ am Wegrand',
    'mtb'          => '5716',
    'mtb_quadrant' => '442',
    'municipality'     => 'PRUEFGEMEINDE',
    'region'   => 'PRUEFLAND',
    'country'        => 'PRUEFSTAAT',
    'protected_area' => 'Naturschutzgebiet PRUEFGEBIET',
    'extra'       => ['Substrat' => 'PRUEFSUBSTRAT', 'Begleiter' => ['PRUEFEINS', 'PRUEFZWEI']],
];
$haystack = point_haystack($point);

foreach (['prueftitel', 'pruefnotiz', '5716', 'pruefgemeinde', 'pruefland',
          'pruefstaat', 'pruefgebiet', 'pruefsubstrat', 'pruefeins', 'pruefzwei'] as $word) {
    check(str_contains($haystack, $word), "„$word\" stands in the haystack");
}
check(str_contains($haystack, '5716/442'),
    'the map sheet stands there ALSO with quadrant — one should be able to type "5716/442"');
check($haystack === mb_strtolower($haystack),
    'everything lowercase — matchTerms compares without further ado');

echo "A raw database row also goes through (extra as JSON):\n";
$raw = $point;
$raw['extra'] = json_encode($point['extra']);
check(point_haystack($raw) === $haystack,
    'raw and prepared yield the same text — the auto lists compute over raw rows');

echo "Empty stays harmless:\n";
check(point_haystack([]) === '',
    'a point with nothing at all yields the empty text, not an error');
check(point_haystack(['title' => 'Nur Titel']) === 'nur titel',
    'and a point with only one field exactly that one');

echo "The JS side names the same fields:\n";
$js = (string) file_get_contents(__DIR__ . '/../assets/js/haystack.js');
$start = strpos($js, 'function pointHaystack');
check($start !== false, 'pointHaystack() is to be found in haystack.js');

if ($start !== false) {
    // The body up to the function's closing brace — it stands as a lone
    // '}' at the start of a line, like every function there.
    $end  = strpos($js, "\n}", $start);
    $body = substr($js, $start, ($end === false ? 400 : $end - $start));

    foreach (FIELDS as $field) {
        check(str_contains($body, $field),
            "haystack.js names „$field\" — otherwise the sidebar searches something else than Organisieren");
    }
    // And the opposite direction: does the other side name something that
    // is missing here? Checked via the punkt. accesses in the body.
    preg_match_all('/punkt\.([a-z_]+)/', $body, $matches);
    $there = array_values(array_unique($matches[1]));
    $missing = array_diff($there, [...FIELDS, 'mtb_quadrant']);
    check($missing === [],
        'haystack.js reads no field that is missing here'
        . ($missing === [] ? '' : ' — missing: ' . implode(', ', $missing)));
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
