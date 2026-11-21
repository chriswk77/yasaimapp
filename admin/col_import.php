<?php
/**
 * Import the Catalogue of Life. Command line only.
 *
 *   php admin/col_import.php <col_fungi.tsv> [--really]
 *
 * The file is produced from COL's monthly Darwin Core archive:
 *
 *   curl -sL -o col.zip \
 *     https://download.checklistbank.org/col/monthly/2026-07-17_xr_dwca.zip
 *   unzip -p col.zip Taxon.tsv > taxon_all.tsv
 *
 * Then filter in two passes. That is not a complication but necessary: for
 * synonyms the kingdom column is empty, because they inherit their placement
 * from the accepted name. A filter on kingdom = Fungi therefore discards
 * them all — the first attempt yielded 620,974 names with not a single
 * synonym among them. So first one collects the IDs of the accepted fungi,
 * then one takes along all rows that point to them.
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
    fwrite(STDERR, "Aufruf: php admin/col_import.php <col_fungi.tsv> [--really]\n");
    exit(1);
}

/**
 * Canonical name from the components.
 *
 * Without rank abbreviation, i.e. "Boletus edulis albus" instead of "Boletus
 * edulis var. albus" — GBIF does it the same way, and only then can the two
 * be compared.
 */
function col_canonical(string $genus, string $epithet, string $infra, string $name, string $author): ?string
{
    $parts = array_filter([trim($genus), trim($epithet), trim($infra)], static fn($t) => $t !== '');
    if ($parts !== []) {
        return implode(' ', $parts);
    }
    // No decomposed name available: cut the author off the end.
    $rest = trim($name);
    if ($author !== '' && str_ends_with($rest, $author)) {
        $rest = trim(substr($rest, 0, -strlen($author)));
    }
    return $rest !== '' ? $rest : null;
}

$handle = fopen($source, 'r');
$header  = fgetcsv($handle, 0, "\t", '"', '');

$expected = ['col_id','parent_id','accepted_id','status','taxon_rank','name','author',
             'genus','epithet','infra','class','order_name','family'];
if ($header !== $expected) {
    fwrite(STDERR, "Kopfzeile passt nicht. Erwartet:\n  " . implode(', ', $expected) . "\n");
    exit(1);
}

printf("%s\n\n", $really ? 'Einspielen' : 'PROBELAUF — nichts wird geschrieben');

if (!$really) {
    $n = $syn = 0;
    while (($z = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
        $n++;
        if (($z[2] ?? '') !== '') $syn++;
    }
    printf("  %s Einträge, davon %s Synonyme\n\nZum tatsächlichen Einspielen mit --really erneut aufrufen.\n",
        number_format($n, 0, ',', '.'), number_format($syn, 0, ',', '.'));
    exit;
}

$pdo->exec('TRUNCATE TABLE map_tax_col');

$stmt = $pdo->prepare(
    'INSERT INTO map_tax_col
        (col_id, accepted_id, parent_id, status, taxon_rank, name, canonical, author,
         family, order_name, class)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);

$value = static fn($v) => trim((string) $v) === '' ? null : trim((string) $v);
$short = static fn($v, $n) => $v === null ? null : mb_substr($v, 0, $n);

$pdo->beginTransaction();
$n = 0;

while (($z = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
    if (count($z) < 13) continue;

    $canonical = col_canonical((string) $z[7], (string) $z[8], (string) $z[9],
                               (string) $z[5], (string) $z[6]);

    $stmt->execute([
        mb_substr((string) $z[0], 0, 24),
        $short($value($z[2]), 24),
        $short($value($z[1]), 24),
        $short($value($z[3]), 30),
        $short($value($z[4]), 24),
        mb_substr((string) $z[5], 0, 400),
        $short($canonical, 255),
        $short($value($z[6]), 255),
        $short($value($z[12]), 120),
        $short($value($z[11]), 120),
        $short($value($z[10]), 120),
    ]);

    if (++$n % 25000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        printf("  %s …\n", number_format($n, 0, ',', '.'));
    }
}
$pdo->commit();
fclose($handle);

printf("\n  %s Einträge eingespielt\n", number_format($n, 0, ',', '.'));

$openCount = $pdo->query(
    'SELECT COUNT(*) FROM map_tax_col t
      WHERE t.accepted_id IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM map_tax_col z WHERE z.col_id = t.accepted_id)'
)->fetchColumn();
printf("  Synonymverweise ohne Ziel: %s\n", number_format((int) $openCount, 0, ',', '.'));
