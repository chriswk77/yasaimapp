<?php
/**
 * Tests the language catalogs in includes/lang/ and the ones the modules
 * bring (modules/<name>/lang/).
 *
 *   php tests/lang.php
 *
 * WHY THIS TEST EXISTS: a German sentence that stands twice in a catalog
 * is invisible. PHP keeps the LATER entry of an array literal without a
 * word, so whoever adds a sentence that is already there silently
 * rewrites every other place that says it. Found 26.09.2026: „%s von %s"
 * stood as „%s of %s" (3 von 5, 12 MB von 40 MB) and, added later for
 * the trash's „26.09.2026 14:00 von Anna", as „%s by %s" — and from
 * then on the English cloud move read „12 MB by 40 MB".
 *
 * THE CURE IS NEVER A SECOND ENTRY. The key is the German sentence
 * (includes/i18n.php), so one German sentence can have one translation.
 * Where the German says two things with the same words, the CODE has to
 * say two sentences — the trash now writes its date and then „von %s".
 *
 * No database, no configuration: the catalogs are read as source, token
 * by token, because the loaded array has already lost the duplicate.
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

/** A PHP string literal as the string it stands for. */
function literal_value(string $token): string
{
    $inner = substr($token, 1, -1);
    return $token[0] === "'"
        ? str_replace(['\\\\', "\\'"], ['\\', "'"], $inner)
        : stripcslashes($inner);
}

/**
 * Every key of the catalog's array literal with the line it stands on —
 * a string literal followed by „=>", comments and blanks skipped.
 *
 * @return array<string, int[]>  key => lines
 */
function catalog_keys(string $file): array
{
    $significant = array_values(array_filter(
        token_get_all((string) file_get_contents($file)),
        static fn($t) => !(is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))
    ));
    $keys = [];
    for ($i = 0, $n = count($significant) - 1; $i < $n; $i++) {
        $t = $significant[$i];
        $next = $significant[$i + 1];
        if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING
            && is_array($next) && $next[0] === T_DOUBLE_ARROW) {
            $keys[literal_value($t[1])][] = $t[2];
        }
    }
    return $keys;
}

$files = glob(__DIR__ . '/../includes/lang/*.php') ?: [];
check($files !== [], 'there is at least one catalog');
$moduleFiles = glob(__DIR__ . '/../modules/*/lang/*.php') ?: [];

foreach (array_merge($files, $moduleFiles) as $file) {
    $name = substr((string) realpath($file), strlen((string) realpath(__DIR__ . '/..')) + 1);
    echo "\n$name\n";
    $keys = catalog_keys($file);
    $loaded = require $file;

    // The reading above must see what PHP sees — otherwise a clean
    // result would only mean the tokens were read wrongly.
    check(count($keys) === count($loaded),
        sprintf('the source reading finds every key PHP loads (%d)', count($loaded)));

    $twice = array_filter($keys, static fn(array $lines) => count($lines) > 1);
    foreach ($twice as $key => $lines) {
        check(false, sprintf('„%s" stands %d times (lines %s) — one German sentence, one translation',
            $key, count($lines), implode(', ', $lines)));
    }
    check($twice === [], 'no German sentence stands twice');
}

/* ONE SENTENCE, ONE PLACE — ACROSS THE FILES TOO (21.11.2026). A module's
   catalog is laid over the core's (lang_catalog, includes/i18n.php), and the
   core's entry wins: a sentence standing in both is the same invisible
   duplicate as one standing twice in a file, only that here the module's
   translation is the one nobody ever reads. */
foreach ($moduleFiles as $file) {
    $core = __DIR__ . '/../includes/lang/' . basename($file);
    if (!is_file($core)) continue;
    $both = array_keys(array_intersect_key(require $file, require $core));
    $name = substr((string) realpath($file), strlen((string) realpath(__DIR__ . '/..')) + 1);
    check($both === [], $both === [] ? "$name repeats no sentence of the core's catalog"
        : "$name repeats the core's: „" . implode('", „', array_slice($both, 0, 5)) . '"');
}

/* THE CASE THAT STARTED IT: „%s von %s" is „of" — the counts and sizes
   say it that way everywhere. */
$en = require __DIR__ . '/../includes/lang/en.php';
check(($en['%s von %s'] ?? null) === '%s of %s', '„%s von %s" reads „%s of %s"');

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
