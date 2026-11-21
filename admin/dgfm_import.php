<?php
/**
 * Import the German reference list (DGfM). Command line only.
 *
 *   php admin/dgfm_import.php data/taxonomie/DgfmTaxref.tsv [--really]
 *
 * The file lives in the repository because — unlike the GBIF backbone — it
 * cannot readily be obtained anew: the PSV trainer it comes from has moved
 * to third-party hosting.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

$source   = $argv[1] ?? '';
$really = in_array('--really', $argv, true) || in_array('--wirklich', $argv, true);

if ($source === '' || !is_readable($source)) {
    fwrite(STDERR, "Aufruf: php admin/dgfm_import.php <DgfmTaxref.tsv> [--really]\n");
    exit(1);
}

$handle   = fopen($source, 'r');
$columns = fgetcsv($handle, 0, "\t", '"', '');

$expected = ['taxnr','sipnr','taxon_rank','binomial','full_name','genus','epithet','author','year',
             'german_name','red_list_de','red_list_by','family','order_name','class','group_name'];
if ($columns !== $expected) {
    fwrite(STDERR, "Kopfzeile passt nicht. Erwartet:\n  " . implode(', ', $expected) . "\n");
    exit(1);
}

printf("%s\n\n", $really ? 'Einspielen' : 'PROBELAUF — nichts wird geschrieben');

if (!$really) {
    $n = 0;
    while (fgetcsv($handle, 0, "\t", '"', '') !== false) $n++;
    printf("  %s Zeilen bereit\n\nZum tatsächlichen Einspielen mit --really erneut aufrufen.\n",
        number_format($n, 0, ',', '.'));
    exit;
}

$pdo->exec('TRUNCATE TABLE map_tax_dgfm');

$stmt = $pdo->prepare(
    'INSERT INTO map_tax_dgfm
        (taxnr, sipnr, taxon_rank, binomial, full_name, genus, epithet, author, year,
         german_name, red_list_de, red_list_by, family, order_name, class, group_name)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);

/** Empty text is not a value in this list, but a gap. */
$value = static fn($v) => trim((string) $v) === '' ? null : trim((string) $v);

$pdo->beginTransaction();
$n = 0;

while (($z = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
    if (count($z) < 16) continue;

    $stmt->execute([
        (int) $z[0], (int) $z[1], $value($z[2]), $value($z[3]), $value($z[4]),
        $value($z[5]), $value($z[6]), $value($z[7]), $value($z[8]),
        $value($z[9]), $value($z[10]), $value($z[11]),
        $value($z[12]), $value($z[13]), $value($z[14]), $value($z[15]),
    ]);

    if (++$n % 10000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();
fclose($handle);

printf("  %s Einträge eingespielt\n", number_format($n, 0, ',', '.'));

/* THE SEARCH TEXT, OR THE SEARCH FINDS NOTHING. The column was first
   filled by migration 0013 and never by this script — a re-import left
   it NULL, and the full-text search would have answered every query with
   silence (found 13.09.2026 while adding the sensu qualifier). Both names,
   hyphens as spaces, plus the qualifier: species_qualifier_sql() says why. */
require_once __DIR__ . '/../includes/species.php';
$pdo->exec(
    "UPDATE map_tax_dgfm SET search_text = TRIM(CONCAT_WS(' ',
         REPLACE(binomial, '-', ' '),
         REPLACE(COALESCE(german_name, ''), '-', ' '),
         " . species_qualifier_sql('map_tax_dgfm') . "))"
);
echo "  Suchtext gebaut\n";

// The synonymy must be closed, otherwise it is of no use.
$openCount = $pdo->query(
    'SELECT COUNT(*) FROM map_tax_dgfm t
      WHERE NOT EXISTS (SELECT 1 FROM map_tax_dgfm z WHERE z.taxnr = t.sipnr)'
)->fetchColumn();
printf("  Synonymverweise ohne Ziel: %d\n", $openCount);
