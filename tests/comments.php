<?php
/**
 * Tests that no comment travels to the browser inside a page.
 *
 *   php tests/comments.php
 *
 * WHY THIS TEST EXISTS. A comment in a template used to be an HTML comment,
 * and an HTML comment is part of the answer: on 20.11.2026 the map sent
 * 115 of its 425 KB as comments, the Fundbrowser 95 of 296, and whoever
 * opened the page source read the reasoning of the whole project. That day
 * all 584 of them became PHP comments — the same words at the same spot,
 * and they stay on the server.
 *
 * WHAT IS LOOKED AT: the parts of a PHP file that PHP hands on untouched
 * (T_INLINE_HTML). The source is read with PHP's own tokenizer, so a „<!--"
 * inside a string or inside a comment — this file has both — is nobody's
 * business; only one the browser would really receive counts.
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

echo "\nComments — none of them in a page's answer\n\n";

$root = dirname(__DIR__);
$files = [];
foreach (['*.php', 'pages/*.php', 'includes/*.php', 'admin/*.php', 'api/v1/*.php', 'cronjobs/*.php',
          // a module's templates answer the same browsers (21.11.2026)
          'modules/*/*.php', 'modules/*/pages/*.php', 'modules/*/includes/*.php', 'modules/*/admin/*.php', 'modules/*/api/*.php'] as $pattern) {
    $files = array_merge($files, glob($root . '/' . $pattern) ?: []);
}
check(count($files) > 100, 'the templates were found (' . count($files) . ' files)');

$found = [];
foreach ($files as $file) {
    foreach (token_get_all((string) file_get_contents($file)) as $token) {
        if (is_array($token) && $token[0] === T_INLINE_HTML && str_contains($token[1], '<!--')) {
            $line = $token[2] + substr_count((string) strstr($token[1], '<!--', true), "\n");
            $found[] = substr($file, strlen($root) + 1) . ':' . $line;
        }
    }
}
check($found === [], $found === []
    ? 'no HTML comment in any template — write <?php /* … */ ?> instead'
    : 'HTML comments that would reach the browser, write <?php /* … */ ?> instead: ' . implode(', ', $found));

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
