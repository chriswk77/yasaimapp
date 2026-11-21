<?php
/**
 * Tests the web addresses of a species (includes/web_links.php,
 * migration 0166).
 *
 *   php tests/web_links.php        (from the project directory)
 *
 * WHAT IS TESTED HERE, and why exactly that:
 *
 *   A TEMPLATE WITHOUT ITS VALUE DROPS OUT — no GBIF key, no GBIF map; a
 *   link that points at „taxon_key=" is worse than none.
 *
 *   THE IMPORT REPLACES AND READS ITS COLUMNS BY NAME — plain and gzip;
 *   several pages of one site become one entry with its parts.
 *
 *   A NAME COUPLES ONLY WHERE NO KEY DOES — a homonym in another kingdom
 *   must not hand its pages over.
 *
 * The import part runs only on an EMPTY table: it replaces everything, and
 * a test must not wipe the real export of the database it runs on.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/web_links.php';

$failed = 0;
function check(bool $b, string $what): void
{
    global $failed;
    if ($b) { echo "  ok    $what\n"; } else { $failed++; echo "  FAILED: $what\n"; }
}

echo "Written from the name and the keys:\n";
$all = web_links_generated('Boletus edulis Bull.', 5249365, 1234);
$urls = array_merge(...array_map(static fn(array $l): array => array_column($l, 'url'), array_values($all)));
check(in_array('https://www.gbif.org/occurrence/map?taxon_key=5249365', $urls, true), 'the GBIF map with the key');
check(in_array('https://www.pilze-deutschland.de/organismen/101234', $urls, true), 'pilze-deutschland.de through the DGfM number');
check(in_array('https://www.google.com/search?q=%22Boletus%20edulis%22', $urls, true), 'Google with the binomial in quotes, no author');
check(in_array('https://www.instagram.com/explore/tags/boletusedulis/', $urls, true), 'the hashtag in lower case');
$bare = web_links_generated('Boletus edulis', null, null);
check(!isset($bare['Verbreitungskarte']) && !isset($bare['Porträt']), 'without keys the map and the gallery drop out');

if (!web_links_ready($pdo)) {
    echo "Migration 0166 missing — the stored half is skipped.\n";
} elseif ((int) $pdo->query('SELECT COUNT(*) FROM map_web_links')->fetchColumn() > 0) {
    echo "The table holds a real export — the import part is skipped.\n";
} else {
    echo "The import:\n";
    $csv = "\xEF\xBB\xBFgbif_key,dgfm_taxnr,wiss_name,quelle,kategorie,label,unterlabel,url\n"
         . "5249365,1234,Boletus edulis,pilz123,Porträt,123Pilze,,https://www.123pilze.de/x/steinpilz.htm\n"
         . "5249365,,Boletus edulis,interhias,Porträt,Interhias,Makro,https://www.interhias.de/makro\n"
         . "5249365,,Boletus edulis,interhias,Porträt,Interhias,Mikro,https://www.interhias.de/mikro\n"
         . ",,Agaricus campestris,taunuspilz,Porträt,Taunuspilz,,https://taunuspilz.de/agaricus\n"
         . "999,,Agaricus campestris,mycodb,Porträt,MycoDB,,https://mycodb.fr/other\n"
         . ",,,broken,Porträt,Leer,,not-a-url\n";
    $file = tempnam(sys_get_temp_dir(), 'links') . '.csv.gz';
    file_put_contents($file, gzencode($csv));
    try {
        $done = web_links_import($pdo, $file);
        check($done['rows'] === 5 && $done['skipped'] === 1, 'five rows in from a gzip file, the broken one skipped');
        $portraits = web_links($pdo, 'Boletus edulis', 5249365, 1234);
        $byCat = array_column($portraits, 'links', 'category');
        $labels = array_column($byCat['Porträt'] ?? [], 'label');
        check(in_array('123Pilze', $labels, true), 'the stored portrait joins by key');
        $inter = array_values(array_filter($byCat['Porträt'], static fn(array $l): bool => $l['label'] === 'Interhias'))[0] ?? null;
        check(isset($inter['multi']) && array_column($inter['multi'], 'label') === ['Makro', 'Mikro'], 'Interhias as one entry with Makro and Mikro');
        $agaricus = array_column(array_column(web_links($pdo, 'Agaricus campestris', 1111, null), 'links', 'category')['Porträt'] ?? [], 'label');
        check(in_array('Taunuspilz', $agaricus, true), 'a row without keys couples by name');
        check(!in_array('MycoDB', $agaricus, true), 'a row with another key does not — no homonym joins');
        $again = web_links_import($pdo, $file);
        check((int) $pdo->query('SELECT COUNT(*) FROM map_web_links')->fetchColumn() === 5, 'importing again replaces, it does not add');
        // A file with one site only leaves the others standing.
        $one = tempnam(sys_get_temp_dir(), 'links') . '.csv';
        file_put_contents($one, "gbif_key,dgfm_taxnr,wiss_name,quelle,kategorie,label,unterlabel,url\n"
            . "5249365,,Boletus edulis,cema,Porträt,CEMA (Asturias),,https://cema.example/boletus\n");
        web_links_import($pdo, $one);
        @unlink($one);
        check((int) $pdo->query('SELECT COUNT(*) FROM map_web_links')->fetchColumn() === 6, 'a file of one new site adds it and keeps the others');
    } finally {
        $pdo->exec('DELETE FROM map_web_links');
        @unlink($file);
    }
}

echo $failed === 0 ? "\nAll good.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
