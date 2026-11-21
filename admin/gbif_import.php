<?php
/**
 * Import the GBIF fungi taxonomy. Command line only.
 *
 *   php admin/gbif_import.php <fungi.tsv> [--really]
 *
 * The file is produced from the GBIF backbone. Obtaining it is free and
 * needs no account — which is exactly why this table can be rebuilt at any
 * time, even without a backup:
 *
 *   curl -sL https://hosted-datasets.gbif.org/datasets/backbone/current/simple.txt.gz \
 *     | gzip -dc \
 *     | awk -F'\t' -v OFS='\t' '$11=="5" {
 *           print $1,$2,$3,$4,$5,$6,$12,$13,$14,$15,$16,$17,$19,$20,$21,$22,$23,
 *                 $25,$26,$27,$28 }' \
 *     > fungi.tsv
 *
 * Column 11 is the kingdom, 5 stands for Fungi. The remaining column
 * numbers come from the backbone in "simple" format (30 columns, no header
 * row).
 *
 * Not imported are the species hypotheses from UNITE — names like
 * "SH1611007.08FU", DNA clusters without a scientific name. They make up
 * more than half of the stock, but are no good for a find database: nobody
 * records a find under a cluster number. It was verified that no named
 * entry points to such a one — so nothing is lost.
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
    fwrite(STDERR, "Aufruf: php admin/gbif_import.php <fungi.tsv> [--really]\n");
    exit(1);
}

/** Species hypothesis from UNITE instead of a name? */
function gbif_is_hypothesis(string $name): bool
{
    return preg_match('~^SH\d~', $name) === 1;
}

// First count what would come in — in the dry run too.
$total = $named = $hypotheses = 0;
$handle = fopen($source, 'r');
while (($z = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
    if (count($z) < 21) continue;
    $total++;
    gbif_is_hypothesis((string) $z[12]) ? $hypotheses++ : $named++;
}
fclose($handle);

printf("%s\n\n", $really ? 'Einspielen' : 'PROBELAUF — nichts wird geschrieben');
printf("  Zeilen in der Datei          %9s\n", number_format($total, 0, ',', '.'));
printf("  davon UNITE-Hypothesen       %9s  (werden übergangen)\n", number_format($hypotheses, 0, ',', '.'));
printf("  einzuspielende Taxa          %9s\n", number_format($named, 0, ',', '.'));

if (!$really) {
    echo "\nZum tatsächlichen Einspielen mit --really erneut aufrufen.\n";
    exit;
}

$pdo->exec('TRUNCATE TABLE map_tax_gbif');

$stmt = $pdo->prepare(
    'INSERT INTO map_tax_gbif
        (gbif_key, parent_key, basionym_key, is_synonym, status, taxon_rank,
         phylum_key, class_key, order_key, family_key, genus_key, species_key,
         name, canonical, genus, epithet, infra_epithet,
         author, year, basionym_author, basionym_year)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);

/** "\N" is the backbone's sign for "no value". */
$value = static fn($v) => ($v === '\\N' || $v === '') ? null : $v;
$number = static fn($v) => ($v === '\\N' || $v === '') ? null : (int) $v;

$pdo->beginTransaction();
$imported = 0;
$handle = fopen($source, 'r');

while (($z = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
    if (count($z) < 21 || gbif_is_hypothesis((string) $z[12])) continue;

    $stmt->execute([
        (int) $z[0], $number($z[1]), $number($z[2]),
        $z[3] === 't' ? 1 : 0, $value($z[4]), $value($z[5]),
        $number($z[6]), $number($z[7]), $number($z[8]), $number($z[9]), $number($z[10]), $number($z[11]),
        mb_substr((string) $z[12], 0, 400),
        $value($z[13]) !== null ? mb_substr((string) $z[13], 0, 255) : null,
        $value($z[14]) !== null ? mb_substr((string) $z[14], 0, 120) : null,
        $value($z[15]) !== null ? mb_substr((string) $z[15], 0, 120) : null,
        $value($z[16]) !== null ? mb_substr((string) $z[16], 0, 120) : null,
        $value($z[17]) !== null ? mb_substr((string) $z[17], 0, 255) : null,
        $value($z[18]) !== null ? mb_substr((string) $z[18], 0, 10)  : null,
        $value($z[19]) !== null ? mb_substr((string) $z[19], 0, 255) : null,
        $value($z[20]) !== null ? mb_substr((string) $z[20], 0, 10)  : null,
    ]);

    // Commit in between: a single transaction over 400,000 rows lets the
    // undo log grow needlessly.
    if (++$imported % 20000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        printf("  %s …\n", number_format($imported, 0, ',', '.'));
    }
}
$pdo->commit();
fclose($handle);

printf("\n  %s Taxa eingespielt\n", number_format($imported, 0, ',', '.'));
