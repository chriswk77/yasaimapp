<?php
/**
 * Import the German lichen list. Command line only.
 *
 *   php admin/lichens_import.php <RoteListe_Flechten….zip> [--really]
 *
 * The file is free and can be obtained anew at any time — which is why it
 * does not live in the repository:
 *
 *   curl -sLO https://www.rote-liste-zentrum.de/wp-content/uploads/Download_RoteListe_Flechten20und20flechtenbewohnende20Pilze_2011_20210317-1606.zip
 *
 * Inside are three data tables — lichens, lichenicolous fungi,
 * lichen-like fungi —, together 2,409 taxa of the 2011 Red List
 * (Wirth et al.), published by the BfN's Rote-Liste-Zentrum.
 *
 * Reading the archive lives in includes/bfn_list.php; the plant list
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
    fwrite(STDERR, "Aufruf: php admin/lichens_import.php <RoteListe_Flechten….zip> [--really]\n");
    exit(1);
}

/** Which data table contains which group — recognizable by the file name. */
$groupFor = static function (string $entry): ?string {
    if (!str_contains($entry, '02_Datentabelle_RL_')) return null;
    if (str_contains($entry, 'Lichenicole'))          return 'lichenicoler Pilz';
    if (str_contains($entry, 'Flechtenaehnliche'))    return 'flechtenähnlicher Pilz';
    if (str_contains($entry, 'Flechten'))             return 'Flechte';
    return null;
};

try {
    $parsed = bfn_archive_read($source, $groupFor);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

foreach ($parsed['per_group'] as $group => $n) {
    printf("  %-24s %5d Taxa\n", $group, $n);
}
printf("\n  %d gültige Namen, %d Synonyme, %d Zeilen gesamt\n",
    $parsed['valid'], count($parsed['rows']) - $parsed['valid'], count($parsed['rows']));
if ($parsed['empty'] > 0) printf("  %d Zeilen ohne Namen übersprungen\n", $parsed['empty']);

if (!$really) {
    echo "\nPROBELAUF — nichts geschrieben. Beispiele:\n";
    foreach (array_slice($parsed['rows'], 0, 3) as $z) {
        printf("  %-46s %-30s %s\n", $z['binomial'], $z['author'], $z['rl']);
    }
    echo "\nZum tatsächlichen Einspielen mit --really erneut aufrufen.\n";
    exit(0);
}

bfn_write($pdo, 'map_tax_lichens', $parsed['rows']);
printf("\n%d Zeilen eingespielt.\n", count($parsed['rows']));
