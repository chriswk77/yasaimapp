<?php
/**
 * Tests that buttons and fields take their sizes from the tokens.
 *
 *   php tests/sizes.php
 *
 * WHY THIS TEST EXISTS: the maintainer asked which rule applies to button and
 * field sizes because he saw "different sizes on the page" that he could
 * not explain. The rule existed — button and field are both 45 px tall so
 * that side by side they form one line —, but it was written down
 * nowhere, and six places deviated from it. The worst:
 *
 *   .button.small EXISTED TWICE, in app.css with .35rem/.84rem and in
 *   map.css with .42rem/.87rem. Because the map loads both sheets, the
 *   same button was 37 px tall there and 34 in the administration. Nobody
 *   saw it, because you never see both pages side by side — exactly the
 *   kind of mistake a test helps against and a good intention does not.
 *
 * WHAT IS READ IS THE SOURCE TEXT, not the running browser: the test
 * needs neither database nor display and thus runs everywhere. It
 * therefore cannot check the computed height, only that the values come
 * from the tokens — which is exactly the point: whoever writes them out
 * will sooner or later write them differently.
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

echo "\nSizes of buttons and fields\n\n";

$app = (string) file_get_contents(__DIR__ . '/../assets/css/app.css');
$map = (string) file_get_contents(__DIR__ . '/../assets/css/map.css');
// The picture viewer's rules stood in map.css until 30.09.2026 and must not
// slip out of this net by moving house.
$viewer = (string) file_get_contents(__DIR__ . '/../assets/css/photowindow.css');

echo "The tokens stand ready:\n";
foreach (['--button-padding', '--button-font', '--button-padding-small', '--button-font-small',
          '--field-padding', '--field-font', '--field-padding-small', '--field-font-small',
          '--field-height', '--field-height-small'] as $token) {
    check(preg_match('/^\s*' . preg_quote($token, '/') . ':/m', $app) === 1,
        "$token is defined in :root");
}

echo "Every size comes from a token:\n";

/*
 * Padding and font-size on a button or field rule may only appear as
 * var(--…). The search goes line by line, because a rule spans several
 * lines and the selector stands before it.
 */
$suspicious = [];
$sheets = [['app.css', $app], ['map.css', $map], ['photowindow.css', $viewer]];
// A module's stylesheet stays in the same net (21.11.2026): the key module's
// rules stood in app.css until then and must not slip out by moving house.
foreach (glob(__DIR__ . '/../modules/*/assets/css/*.css') ?: [] as $sheet) {
    $sheets[] = [substr((string) realpath($sheet), strlen((string) realpath(__DIR__ . '/..')) + 1), (string) file_get_contents($sheet)];
}
foreach ($sheets as [$name, $content]) {
    $selector = '';
    foreach (explode("\n", $content) as $lineNo => $row) {
        if (str_contains($row, '{')) {
            $selector = trim(explode('{', $row)[0]);
        }
        // Only rules that mean a button or a field. The element names must
        // stand as their own token: since the rename, "list-selection"
        // carries "select" as a substring, and the German class names never
        // did — without the boundaries this net suddenly caught label rules
        // that no button ever meant.
        $matchesControl = preg_match('/\.button\b|(?<![\w-])input\b|(?<![\w-])select\b|(?<![\w-])textarea\b|\.filter-chip\b/', $selector) === 1;
        if (!$matchesControl || str_starts_with(trim($row), '*') || str_starts_with(trim($row), '/*')) {
            continue;
        }
        if (preg_match('/^\s*(padding|font-size|height)\s*:\s*([^;]+);/', $row, $t)) {
            $value = trim($t[2]);
            // Allowed: token, 0, auto, inherit — and padding-inline with
            // its own number (that only shifts sideways, not the height).
            $okay = str_contains($value, 'var(--')
                      || in_array($value, ['0', 'auto', 'inherit', '100%'], true);
            if (!$okay) {
                $suspicious[] = "$name:" . ($lineNo + 1) . "  $selector { {$t[1]}: $value }";
            }
        }
    }
}

// The named exceptions: values that do NOT determine the height of a
// control but do something else.
$exceptions = [
    'padding-right',        // room for the X in the search field
    'padding-inline',       // sideways, not in the height
    'padding-left',
    'min-height',
    '.symbol-button',         // own rule, justified in map.css
    '.shape-button',           // marker shape choice, square
    '.color-button',
    '.tab',              // tabs are not buttons in the sense of .button
    '.text-button',           // looks like a link
    '.organize-text-button',
    'textarea',             // grows with its content
    // A slider's track and knob, drawn by hand (photowindow.css, „Bearbeiten",
    // 07.11.2026): a drawing inside the control, not the height it stands at
    // beside a button.
    '-slider-runnable-track',
    '-slider-thumb',
    '-range-track',
    '-range-thumb',
];
$real = array_values(array_filter($suspicious, static function (string $line) use ($exceptions): bool {
    foreach ($exceptions as $a) {
        if (str_contains($line, $a)) {
            return false;
        }
    }
    return true;
}));

foreach ($real as $line) {
    echo "  written out instead of a token: $line\n";
}
check($real === [], 'no written-out size on buttons and fields');

echo "No rule stands twice:\n";

/*
 * ONLY THE UNSCOPED RULE IS COUNTED, and the difference matters.
 *
 * What this guards against is what actually happened: .button.small stood
 * in BOTH stylesheets, with different padding, so the same button was 37 px
 * tall on the map and 34 in the administration. Two rules, both claiming to
 * say what a small button is.
 *
 * A rule like "table.list:not(.no-stack) .button.small" is not that. It
 * says nothing about the size of small buttons in general — it raises the
 * touch target inside a stacked table on the phone, with min-height and
 * padding-inline, neither of which touches the height elsewhere. Counting
 * it along made this test red for a legitimate rule, and a test one learns
 * to look past is none.
 *
 * The pattern therefore demands that the selector STARTS with .button.small
 * — after a line break or a closing brace, not after another selector.
 * A selector list ("\.button\.small, .foo {") would slip through; that was
 * true of the old substring count too, and a parser for the sake of it
 * would be the more expensive answer.
 */
$unscoped = static fn(string $sheet): int
    => preg_match_all('/(?:^|[\r\n{}])\s*\.button\.small\s*\{/', $sheet);

check($unscoped($app) + $unscoped($map) === 1,
    '.button.small is defined exactly once, unscoped');
// The footer bars once had three sizes of their own, each in a different
// sheet. .detail-footer and .sidebar-footer meanwhile carry "klein"
// directly on their buttons (the maintainer wanted both footers exactly the
// same height, 14.08.2026) and therefore no longer need a rule of their
// own. Only .list-window-footer stays at the normal size and keeps its
// narrower side padding so that "Liste löschen" does not wrap — that
// does not touch the height.
foreach (['.detail-footer .button {', '.dialog-footer .button {'] as $rule) {
    check(substr_count($map, $rule) === 0 && substr_count($app, $rule) === 0,
        "$rule no longer has an own size, in either sheet");
}
check(substr_count($app, '.list-window-footer .button { padding-inline') === 1,
    '.list-window-footer keeps its own, narrower side padding');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
