<?php
/**
 * Import the animal list with German names. Command line only.
 *
 *   php admin/animals_import.php [<dwc.zip>] [--really]
 *
 * WITHOUT A FILE IT FETCHES ONE. The archive is free, small (460 kB) and
 * lies at a stable address — so unlike the BfN lists there is no reason to
 * make somebody download it by hand first. Given a file, that one is used;
 * that is the way in when the source is unreachable or one wants a
 * particular state.
 *
 *   https://www.gbif.org/dataset/88f4e35a-bdf8-4aa2-9a1b-56401d4eed15
 *   Holstein J, Monje J C: Taxon list of animals with German names
 *   (worldwide) compiled at the SMNS. CC-BY 4.0.
 *
 * WHAT IS IN IT: 18 224 taxa from kingdom down to species, of which 12 664
 * are species; 12 658 carry a German name. Higher ranks come along —
 * "Laufkäfer" for a genus is worth as much in the field as a species name,
 * and they cost nothing.
 *
 * THE AUTHOR SITS IN THE NAME and has to be split off, because the search
 * wants the bare binomial. The rule is "first two words", and it was
 * measured before it was trusted: of 12 664 species names exactly ONE does
 * not follow it — 'Laniarius Voelker & Gnoske, 2010', a genus name filed
 * under species rank. Whatever does not look like an epithet in second
 * position therefore stays out of the name and goes to the author.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

const ANIMALS_SOURCE = 'http://services.snsb.info/DTNtaxonlists/rest/v0.1/lists/'
                   . 'DiversityTaxonNames_TaxaVaria/1144/dwc';

$file   = null;
$really = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--really') {
        $really = true;
    } else {
        $file = $arg;
    }
}

/** The number out of a taxonID like ".../names/DiversityTaxonNames_TaxaVaria/10000/". */
function animals_key(string $raw): int
{
    return preg_match('~/(\d+)/?$~', trim($raw), $m) ? (int) $m[1] : 0;
}

/**
 * Split a scientific name into binomial and author.
 *
 * @return array{0: string, 1: string, 2: string, 3: string} binomial, genus, epithet, author
 */
function animals_split(string $name, string $rank): array
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $genus = $parts[0] ?? '';

    // Only a species has an epithet, and only in second position — and only
    // when it looks like one. See the note at the top about the one name
    // that does not.
    $epithet = ($rank === 'species' && isset($parts[1]) && preg_match('/^[a-zäöüß-]+$/u', $parts[1]))
        ? $parts[1]
        : '';

    $binomial = trim($genus . ' ' . $epithet);
    $author   = trim(mb_substr(trim($name), mb_strlen($binomial)));

    return [$binomial, $genus, $epithet, $author];
}

// --- the archive ------------------------------------------------------------

if ($file === null) {
    echo "Hole das Archiv …\n";
    $file = tempnam(sys_get_temp_dir(), 'animals') . '.zip';
    $bytes = @file_get_contents(ANIMALS_SOURCE, false, stream_context_create([
        'http' => ['timeout' => 120, 'ignore_errors' => true],
    ]));
    if ($bytes === false || strlen($bytes) < 10000) {
        fwrite(STDERR, "Das Archiv ließ sich nicht holen. Von Hand laden und übergeben:\n  "
            . ANIMALS_SOURCE . "\n");
        exit(1);
    }
    file_put_contents($file, $bytes);
    printf("  %s Bytes\n", number_format(strlen($bytes), 0, ',', '.'));
}

if (!is_readable($file)) {
    fwrite(STDERR, "Nicht lesbar: $file\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($file) !== true) {
    fwrite(STDERR, "Kein lesbares Zip: $file\n");
    exit(1);
}

/** Read one CSV out of the archive, keyed by its header row. */
function animals_csv(ZipArchive $zip, string $name): array
{
    $raw = $zip->getFromName($name);
    if ($raw === false) {
        fwrite(STDERR, "Im Archiv fehlt: $name\n");
        exit(1);
    }
    $rows = [];
    $head = null;
    foreach (explode("\n", $raw) as $line) {
        $line = rtrim($line, "\r");
        if ($line === '') {
            continue;
        }
        // The escape character explicitly: PHP 8.4 deprecates the default
        // and would otherwise write a warning per line — eighteen thousand
        // of them. Empty is also the right value here; the file quotes with
        // double quotes and knows no backslash escapes.
        $cells = str_getcsv($line, ',', '"', '');
        if ($head === null) {
            $head = $cells;
            continue;
        }
        if (count($cells) < count($head)) {
            continue;
        }
        $rows[] = array_combine($head, array_slice($cells, 0, count($head)));
    }
    return $rows;
}

$taxa   = animals_csv($zip, 'taxon.csv');
$german = animals_csv($zip, 'common-names.csv');
$zip->close();

printf("Gelesen: %s Taxa, %s deutsche Namen\n",
    number_format(count($taxa), 0, ',', '.'), number_format(count($german), 0, ',', '.'));

// German name per taxon. Only 'de' — the file carries no other language,
// but a second one must not slip in unnoticed later.
$nameFor = [];
foreach ($german as $row) {
    if (strtolower($row['language'] ?? '') !== 'de') {
        continue;
    }
    $key = animals_key($row['taxonID'] ?? '');
    if ($key > 0 && !isset($nameFor[$key])) {
        $nameFor[$key] = trim($row['vernacularName'] ?? '');
    }
}

// The class of a taxon — walked up the parent chain, so a species knows
// whether it is an insect or a bird. That is what the settings switch and
// the folder scheme need later.
$parentOf = [];
$rankOf   = [];
$nameOf   = [];
foreach ($taxa as $row) {
    $key = animals_key($row['taxonID'] ?? '');
    if ($key === 0) {
        continue;
    }
    $parentOf[$key] = animals_key($row['parentNameUsageID'] ?? '');
    $rankOf[$key]   = trim($row['taxonRank'] ?? '');
    $nameOf[$key]   = trim($row['scientificName'] ?? '');
}

$classCache = [];
$classOf = static function (int $key) use (&$classOf, &$classCache, $parentOf, $rankOf, $nameOf): string {
    if (isset($classCache[$key])) {
        return $classCache[$key];
    }
    // Twelve steps are more than the seven ranks this list has — a guard
    // against a chain that points at itself, not a real limit.
    $classCache[$key] = '';
    $at = $key;
    for ($guard = 0; $guard < 12 && $at > 0; $guard++) {
        if (($rankOf[$at] ?? '') === 'class') {
            $classCache[$key] = $nameOf[$at] ?? '';
            break;
        }
        $at = $parentOf[$at] ?? 0;
    }
    return $classCache[$key];
};

if (!$really) {
    echo "\nProbelauf. Mit --really wird geschrieben.\n\n";
    $shown = 0;
    foreach ($taxa as $row) {
        if (($row['taxonRank'] ?? '') !== 'species' || $shown >= 5) {
            continue;
        }
        $key = animals_key($row['taxonID']);
        [$binomial, $genus, $epithet, $author] = animals_split($row['scientificName'], 'species');
        printf("  %-34s %-22s %-16s %s\n", $binomial, $author,
            $classOf($key) ?: '—', $nameFor[$key] ?? '—');
        $shown++;
    }
    exit(0);
}

// --- write ------------------------------------------------------------------

if (!table_exists($pdo, 'map_tax_animals')) {
    fwrite(STDERR, "map_tax_animals gibt es nicht — Migration 0057 fehlt.\n");
    exit(1);
}

$pdo->beginTransaction();
$pdo->exec('DELETE FROM map_tax_animals');

$insert = $pdo->prepare(
    'INSERT INTO map_tax_animals
            (taxnr, sipnr, taxon_rank, binomial, genus, epithet, author, german_name, group_name, search_text)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$written = 0;
foreach ($taxa as $row) {
    $key = animals_key($row['taxonID'] ?? '');
    if ($key === 0) {
        continue;
    }
    $rank = trim($row['taxonRank'] ?? '');
    [$binomial, $genus, $epithet, $author] = animals_split($row['scientificName'] ?? '', $rank);
    if ($binomial === '') {
        continue;
    }

    // Almost everything here is accepted; the six synonyms point at their
    // valid name, everything else at itself — the same shape the other two
    // German lists have, so species.php can treat them alike.
    $valid = animals_key($row['acceptedNameUsageID'] ?? '');
    $german = $nameFor[$key] ?? null;

    $insert->execute([
        $key,
        $valid > 0 ? $valid : $key,
        $rank !== '' ? $rank : null,
        $binomial,
        $genus !== '' ? $genus : null,
        $epithet !== '' ? $epithet : null,
        $author !== '' ? $author : null,
        $german !== '' ? $german : null,
        $classOf($key) ?: null,
        trim($binomial . ' ' . ($german ?? '')),
    ]);
    $written++;
}

$pdo->commit();

printf("\nGeschrieben: %s Zeilen.\n", number_format($written, 0, ',', '.'));
printf("Davon Arten: %s, mit deutschem Namen: %s\n",
    number_format((int) $pdo->query("SELECT COUNT(*) FROM map_tax_animals WHERE taxon_rank = 'species'")->fetchColumn(), 0, ',', '.'),
    number_format((int) $pdo->query('SELECT COUNT(*) FROM map_tax_animals WHERE german_name IS NOT NULL')->fetchColumn(), 0, ',', '.'));
