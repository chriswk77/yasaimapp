<?php
/**
 * Tests the relay for offline map files (includes/offline_map.php).
 *
 *   php tests/offline_map.php
 *
 * The pCloud side cannot be tested without a share link of a real file —
 * set OFFLINE_MAP_TEST_LINK to one to include it. What always runs: the
 * reading of share links (which data centre answers which code) and the
 * range read itself, against Mapterhorn's archive, which answers ranges
 * the way pCloud's file servers do. Without network those are skipped.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/offline_map.php';

$failed = 0;
$skipped = 0;
function check(bool $ok, string $what): void
{
    global $failed;
    if ($ok) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "Share links:\n";
$eu = offline_map_publink('https://e.pcloud.link/publink/show?code=XZabcDEF123ghiJKL456mnoPQR789stuVWX');
check(($eu['api'] ?? '') === 'eapi.pcloud.com', 'e.pcloud.link is asked at eapi — the European data centre');
check(($eu['code'] ?? '') === 'XZabcDEF123ghiJKL456mnoPQR789stuVWX', 'and the code comes out whole');
check((offline_map_publink('https://u.pcloud.link/publink/show?code=kZabc123DEF456ghi')['api'] ?? '') === 'api.pcloud.com',
    'u.pcloud.link at api');
check(offline_map_publink('https://example.org/?code=a b') === null, 'no code, no link');
check(offline_map_publink('nonsense') === null, 'nonsense is none');

echo "Range reads:\n";
try {
    $head = offline_map_range('https://download.mapterhorn.com/6-33-21.pmtiles', 0, 7);
    check($head === 'PMTiles', 'the first seven bytes of an archive are its magic');
    check(strlen(offline_map_range('https://download.mapterhorn.com/6-33-21.pmtiles', 1000, 4096)) === 4096,
        'a piece in the middle comes exactly as long as asked');
} catch (RuntimeException $e) {
    $skipped++;
    echo "  ----  skipped (no network): {$e->getMessage()}\n";
}

$link = getenv('OFFLINE_MAP_TEST_LINK') ?: '';
if ($link !== '') {
    echo "The share link from OFFLINE_MAP_TEST_LINK:\n";
    $url = offline_map_file_url($link, true);
    check(str_starts_with($url, 'https://'), 'pCloud hands the server a download address');
    check(strlen(offline_map_range($url, 0, 16)) === 16, 'and the server may read a piece of it');
    check(offline_map_file_url($link) === $url, 'the address is remembered for the next piece');
}

echo $failed ? "\n$failed check(s) failed.\n" : "\nAll good." . ($skipped ? " ($skipped skipped)" : '') . "\n";
exit($failed ? 1 : 0);
