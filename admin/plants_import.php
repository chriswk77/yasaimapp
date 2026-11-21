<?php
/**
 * Import the German plant list. Command line only.
 *
 *   php admin/plants_import.php <RoteListe_Farn-_und_Bluetenpflanzen….zip> [--really]
 *
 * The file is free and can be obtained anew at any time — which is why it
 * does not live in the repository:
 *
 *   curl -sLO https://www.rote-liste-zentrum.de/wp-content/uploads/Download_RoteListe_Farn-20und20Bluetenpflanzen_2018_20240820-1534.zip
 *
 * 5,312 taxa of the 2018 Red List (Metzing et al.), published by the BfN's
 * Rote-Liste-Zentrum — the ferns and flowering plants of Germany, trees as
 * well as herbs, with German name and threat classification.
 *
 * THE ARCHIVE ALSO CONTAINS A "06_Checkliste", and that one is deliberately
 * not read: it does carry more names, but in an entirely different format —
 * seventy columns of nomenclatural apparatus and no German names. For what
 * is needed here, the data table is the right one: a name, a German name, a
 * classification.
 *
 * Reading the archive lives in includes/bfn_list.php; the lichen list
 * comes from the same source and in the same cut.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/bfn_list.php';

$source   = $argv[1] ?? '';
$really = in_array('--really', $argv, true) || in_array('--wirklich', $argv, true);

if ($source === '' || !is_readable($source)) {
    fwrite(STDERR, "Aufruf: php admin/plants_import.php <RoteListe_Farn-_und_Bluetenpflanzen….zip> [--really]\n");
    exit(1);
}

// Only the data table, not the checklist next to it.
$groupFor = static fn(string $entry): ?string
    => str_contains($entry, '02_Datentabelle_RL_') ? 'Farn- und Blütenpflanze' : null;

try {
    $parsed = bfn_archive_read($source, $groupFor);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$withGerman = 0;
$perStatus   = [];
foreach ($parsed['rows'] as $z) {
    if ($z['german'] !== '') $withGerman++;
    if ($z['status']  !== '') $perStatus[$z['status']] = ($perStatus[$z['status']] ?? 0) + 1;
}

printf("  %d gültige Namen, %d Synonyme, %d Zeilen gesamt\n",
    $parsed['valid'], count($parsed['rows']) - $parsed['valid'], count($parsed['rows']));
printf("  %d mit deutschem Namen (%.1f %% der gültigen)\n",
    $withGerman, $withGerman / max(1, $parsed['valid']) * 100);
ksort($perStatus);
foreach ($perStatus as $s => $n) {
    printf("  Status %-3s %5d\n", $s, $n);
}
if ($parsed['empty'] > 0) printf("  %d Zeilen ohne Namen übersprungen\n", $parsed['empty']);

if (!$really) {
    echo "\nPROBELAUF — nichts geschrieben. Beispiele:\n";
    foreach (array_slice($parsed['rows'], 0, 3) as $z) {
        printf("  %-42s %-28s %s\n", $z['binomial'], $z['german'], $z['rl']);
    }
    echo "\nZum tatsächlichen Einspielen mit --really erneut aufrufen.\n";
    exit(0);
}

bfn_write($pdo, 'map_tax_plants', $parsed['rows']);
printf("\n%d Zeilen eingespielt.\n", count($parsed['rows']));
