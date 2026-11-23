<?php
/**
 * Tests the display vocabulary — the tripwire between its two halves,
 * assets/js/vocabulary.js and includes/vocabulary.php, and between the
 * table and the stock words it translates (photos.php, photowindow.js).
 *   php tests/vocabulary.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/vocabulary.php';
require_once __DIR__ . '/../includes/photos.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    echo '  ', $b ? 'ok    ' : 'FAIL  ', $what, "\n";
    if (!$b) $failed++;
}

// The browser's table, read out of its file line by line.
$js = (string) file_get_contents(__DIR__ . '/../assets/js/vocabulary.js');
$start = strpos($js, 'const WORDS = {');
$end = strpos($js, '};', (int) $start);
$browser = [];
preg_match_all("/^\\s*'([^']+)': \\[([^\\]]*)\\],/mu", substr($js, (int) $start, (int) $end - (int) $start), $hits, PREG_SET_ORDER);
foreach ($hits as [, $german, $forms]) {
    preg_match_all("/'([^']*)'/u", $forms, $f);
    $browser[$german] = $f[1];
}
check($start !== false && count($browser) > 30, 'the browser table is found (' . count($browser) . ' words)');
check($browser === VOCABULARY, 'both tables hold the same words, forms and order');

// Every stock micro picture name, layer, part and mark has its international word.
$stock = array_merge(PHOTO_TITLES_MICRO, ['Lamellen', 'Poren', 'Hyphen Hutdeckschicht',
    'Keimporus', 'Ornamenthöhe', 'Öltropfen', 'Sterigmenlänge', 'Kopfbreite', 'Halsbreite',
    'Ektalexcipulum', 'Medullarexcipulum', 'Chrysozystide', 'Gloeozystide', 'Lamprozystide',
    'Generative Hyphe', 'Skeletthyphe', 'Bindehyphe']);
$missing = array_values(array_filter(array_unique($stock),
    static fn(string $w): bool => !array_key_exists($w, VOCABULARY) && !in_array($w, ['Velum', 'Hymenium', 'Asci', 'Excipulum', 'Capillitium', 'Sklerotium'], true)));
check($missing === [], 'every micro word of the stock has a table entry' . ($missing ? ' — missing: ' . implode(', ', $missing) : ''));
// The layers as photowindow.js lists them.
$pw = (string) file_get_contents(__DIR__ . '/../assets/js/photowindow.js');
preg_match('/const MEASURE_SHARED = \[([^\]]*)\]/u', $pw, $shared);
preg_match_all("/\\['(?:macro|basidio|asco|hyphae)', \\(\\) => t\\('[^']*'\\), \\[([^\\]]*)\\]\\]/su", $pw, $cols);
$layers = [];
foreach (array_merge([$shared[1] ?? ''], $cols[1]) as $list) {
    preg_match_all("/'([^']+)'/u", $list, $w);
    $layers = array_merge($layers, $w[1]);
}
$untold = array_values(array_filter($layers,
    static fn(string $w): bool => !array_key_exists($w, VOCABULARY) && !in_array($w, ['Velum', 'Asci', 'Excipulum', 'Capillitium'], true)));
check(count($layers) > 15 && $untold === [], 'every measuring layer has a table entry' . ($untold ? ' — missing: ' . implode(', ', $untold) : ''));
// No word turns into another stored word — the way back would be ambiguous.
$shown = array_map(static fn(array $f): string => mb_strtolower($f[0]), VOCABULARY);
check(count(array_unique($shown)) === count($shown)
    && array_intersect($shown, array_map('mb_strtolower', array_keys(VOCABULARY))) === [],
    'every international word is unique and none is a stored word');

check(vocab('Sporen') === 'Spores' && vocab(' cheilozystiden ') === 'Cheilocystidia'
    && vocab('HDS (Hutdeckschicht)') === 'Pileipellis', 'a stock word is shown international, whatever its case');
check(vocab('Belegfoto') === 'Belegfoto' && vocab('Sklerotium') === 'Sklerotium' && vocab('Mein Wort') === 'Mein Wort'
    && vocab(null) === '', 'a macro name, a word of both lists and a word of one\'s own stand as they are');

echo $failed ? "\n$failed failed.\n" : "\nAll good.\n";
exit($failed ? 1 : 0);
